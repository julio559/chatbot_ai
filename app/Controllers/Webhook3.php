<?php

namespace App\Controllers;

use App\Models\{SessaoModel, OpenrouterModel, PacienteModel, ConfigIaModel};
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Database\Exceptions\DatabaseException;

class Webhook extends ResourceController
{
    /* ====================== Utils ====================== */

    /** Normaliza número: mantém só dígitos. */
    private function soDigitos(?string $v): string
    {
        return preg_replace('/\D+/', '', (string) $v);
    }

    /**
     * Extrai números do evento UltraMSG.
     * - fromMe=false (lead => nós):  lead = data.from,   nossa linha = data.to
     * - fromMe=true  (nós   => lead): lead = data.to,     nossa linha = data.from
     */
    private function extrairNumerosDoEvento(array $data): array
    {
        $ehNossoEnvio = !empty($data['fromMe']);
        $pacienteRaw  = $ehNossoEnvio ? ($data['to'] ?? '')   : ($data['from'] ?? '');
        $nossoRaw     = $ehNossoEnvio ? ($data['from'] ?? '') : ($data['to']   ?? '');

        $numeroLead  = $this->soDigitos(explode('@', (string)$pacienteRaw)[0] ?? '');
        $nossoNumero = $this->soDigitos(explode('@', (string)$nossoRaw)[0]     ?? '');

        return [$ehNossoEnvio, $numeroLead, $nossoNumero];
    }

    /**
     * Resolve o dono da linha (usuario/assinante) pelo telefone_principal.
     * 1) match exato
     * 2) fallback por sufixo (últimos 8–11 dígitos)
     * Retorna [usuarioId, assinanteId] ou [null, null].
     */
    private function encontrarDonoPorLinha(string $nossoNumero): array
    {
        if ($nossoNumero === '') return [null, null];

        $db = \Config\Database::connect();

        // 1) exato
        $u = $db->table('usuarios')->where('telefone_principal', $nossoNumero)->get()->getRowArray();
        if ($u) return [(int)$u['id'], (int)$u['assinante_id']];

        // 2) por sufixo
        $len = strlen($nossoNumero);
        for ($take = 11; $take >= 8; $take--) {
            if ($len < $take) continue;
            $suf = substr($nossoNumero, -$take);
            $u = $db->query(
                "SELECT id, assinante_id FROM usuarios
                 WHERE telefone_principal LIKE CONCAT('%', ?)
                 ORDER BY LENGTH(telefone_principal) DESC
                 LIMIT 1",
                [$suf]
            )->getRowArray();
            if ($u) return [(int)$u['id'], (int)$u['assinante_id']];
        }

        return [null, null];
    }

    /**
     * *** Pega credenciais UltraMSG da instância correta para (usuario, nossa linha).
     * Match por:
     *   1) linha_msisdn EXATO
     *   2) sufixo (últimos 8–11 dígitos)
     *   3) qualquer instância do usuário com conn_status='authenticated' (fallback)
     */
    private function pegarCredenciaisUltra(int $usuarioId, string $nossoNumero): ?array
    {
        $db  = \Config\Database::connect();
        $num = $this->soDigitos($nossoNumero);

        // 1) exato
        $q = $db->table('whatsapp_instancias')
            ->where('usuario_id', $usuarioId)
            ->where('linha_msisdn', $num)
            ->limit(1)->get()->getRowArray();
        if ($q) return ['instance_id' => $q['instance_id'], 'token' => $q['token']];

        // 2) sufixo (11..8 dígitos)
        $len = strlen($num);
        for ($take = 11; $take >= 8; $take--) {
            if ($len < $take) continue;
            $suf = substr($num, -$take);
            $q = $db->query(
                "SELECT instance_id, token FROM whatsapp_instancias
                 WHERE usuario_id = ? AND linha_msisdn LIKE CONCAT('%', ?)
                 ORDER BY LENGTH(linha_msisdn) DESC
                 LIMIT 1",
                [$usuarioId, $suf]
            )->getRowArray();
            if ($q) return ['instance_id' => $q['instance_id'], 'token' => $q['token']];
        }

        // 3) fallback: última autenticada do usuário
        $q = $db->table('whatsapp_instancias')
            ->where('usuario_id', $usuarioId)
            ->where('conn_status', 'authenticated')
            ->orderBy('last_status_at', 'DESC')
            ->limit(1)->get()->getRowArray();
        if ($q) return ['instance_id' => $q['instance_id'], 'token' => $q['token']];

        return null;
    }

    /** *** Envia mensagem via UltraMSG usando a instância correta. */
    private function enviarParaWhatsapp(int $usuarioId, string $nossoNumero, string $numeroDestino, string $mensagem): bool
    {
        $creds = $this->pegarCredenciaisUltra($usuarioId, $nossoNumero);
        if (!$creds) {
            log_message('error', "Sem credenciais UltraMSG para usuario={$usuarioId} linha={$nossoNumero}");
            return false;
        }

        $instanceId = $creds['instance_id'];
        $token      = $creds['token'];
        $url        = "https://api.ultramsg.com/{$instanceId}/messages/chat";

        $data = ['token' => $token, 'to' => $numeroDestino, 'body' => $mensagem];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_TIMEOUT        => 20,
        ]);

        $result   = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        log_message('error', "Envio WhatsApp ({$numeroDestino}) via {$instanceId}: HTTP {$httpCode} - {$result}");
        if ($error) log_message('error', "Erro cURL: " . $error);

        return $httpCode >= 200 && $httpCode < 300;
    }

    /** Insere mensagem no log chat_mensagens */
    private function salvarMensagemChat(string $numero, string $role, string $texto, ?string $canal = 'whatsapp', ?int $usuarioId = null): void
    {
        $db = \Config\Database::connect();
        $db->table('chat_mensagens')->insert([
            'numero'     => $numero,
            'role'       => $role,                 // 'user' | 'assistant' | 'humano'
            'canal'      => $canal ?: 'whatsapp',
            'usuario_id' => $usuarioId,
            'texto'      => $texto,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Garante sessoes.canal preenchido */
    private function garantirCanalSessao(string $numero, int $usuarioId, string $canal = 'whatsapp'): void
    {
        $db = \Config\Database::connect();
        $db->query(
            "UPDATE sessoes SET canal = IFNULL(canal, ?) WHERE numero = ? AND usuario_id = ?",
            [$canal, $numero, $usuarioId]
        );
    }

    /** *** Idempotência: registra ID externo e retorna false se já existe */
    private function registrarIdempotencia(?string $providerId): bool
    {
        if (!$providerId) return true; // sem id, deixa passar

        $db = \Config\Database::connect();
        try {
            // INSERT IGNORE evita explosão de erro se já existir
            $db->query("INSERT IGNORE INTO webhook_msgs (provider_msg_id) VALUES (?)", [$providerId]);
            return $db->affectedRows() > 0; // >0 = novo; 0 = já existia
        } catch (\Throwable $e) {
            log_message('error', 'Idempotência falhou: ' . $e->getMessage());
            return true; // na dúvida, processa
        }
    }

    /* ============================ Webhook ============================ */

    public function index()
    {
        helper('ia');

        $json = $this->request->getJSON(true);
        if (!isset($json['data'])) {
            return $this->respond(['ignorado' => 'payload inválido'], 200);
        }
        $data = $json['data'];

        // --------- Idempotência (UltraMSG ID) ---------
        $providerId = $data['id'] ?? ($data['messageId'] ?? ($data['message_id'] ?? null));
        if (!$this->registrarIdempotencia(is_string($providerId) ? $providerId : null)) {
            return $this->respond(['ignorado' => 'duplicado'], 200);
        }

        // --------- NÚMEROS / DONO DA LINHA ---------
        [$ehNossoEnvio, $numero, $nossoNumero] = $this->extrairNumerosDoEvento($data);
        $mensagem   = trim((string)($data['body'] ?? ''));
        $pushname   = (string)($data['pushname'] ?? 'Paciente');
        $canal      = 'whatsapp'; // UltraMSG => WhatsApp

        if ($numero === '' || $nossoNumero === '' || $mensagem === '') {
            return $this->respond(['ignorado' => 'dados incompletos'], 200);
        }

        // Resolve dono SEM depender de sessão
        [$usuarioId, $assinanteId] = $this->encontrarDonoPorLinha($nossoNumero);
        if (!$usuarioId || !$assinanteId) {
            log_message('error', "Webhook: linha sem dono (to={$nossoNumero})");
            return $this->respond(['ignorado' => 'linha não vinculada a usuário'], 200);
        }

        // --------- Services / Models ---------
        $pacienteModel = new PacienteModel();
        $sessaoModel   = new SessaoModel();
        $configModel   = new ConfigIaModel();
        $cache         = \Config\Services::cache();

        // --------- Upsert Paciente (escopo por usuário) ---------
        $paciente = $pacienteModel->where('telefone', $numero)
                                  ->where('usuario_id', $usuarioId)
                                  ->first();
        if ($paciente) {
            $pacienteModel->update($paciente['id'], ['ultimo_contato' => date('Y-m-d H:i:s')]);
        } else {
            $pacienteModel->insert([
                'usuario_id'     => $usuarioId,
                'nome'           => $pushname,
                'telefone'       => $numero,
                'ultimo_contato' => date('Y-m-d H:i:s'),
                'origem_contato' => 1, // WhatsApp
            ]);
            $paciente = $pacienteModel->where('telefone', $numero)
                                      ->where('usuario_id', $usuarioId)
                                      ->first();
        }

        // --------- Sessão atual (vinculada ao usuário dono) ---------
        $sessao = $sessaoModel->getOuCriarSessao($numero, $usuarioId);
        $etapaAtual        = $sessao['etapa'] ?? 'inicio';
        $ultimaRespostaIa  = $sessao['ultima_resposta_ia'] ?? null;
        $ultimaMsgUsuario  = $sessao['ultima_mensagem_usuario'] ?? null;
        $tsAtualizacao     = !empty($sessao['data_atualizacao']) ? strtotime($sessao['data_atualizacao']) : 0;

        // garanta canal na sessão
        $this->garantirCanalSessao($numero, $usuarioId, $canal);

        /* ===================== MENSAGEM SAINDO (fromMe) ===================== */
        if ($ehNossoEnvio) {
            $roleSaida = 'assistant';

            // evita eco de última resposta da IA
            if ($ultimaRespostaIa && trim($ultimaRespostaIa) === $mensagem) {
                $this->salvarMensagemChat($numero, $roleSaida, $mensagem, $canal, $usuarioId);
                return $this->respond(['ignorado' => 'eco da própria IA (registrado no chat)'], 200);
            }

            // comandos
            if (preg_match('/#(humano|pausar|pause)\b/i', $mensagem)) {
                $sessaoModel->where('numero', $numero)
                            ->where('usuario_id', $usuarioId)
                            ->set([
                                'etapa' => 'humano',
                                'ultima_mensagem_usuario' => null,
                                'ultima_resposta_ia'      => null,
                            ])->update();
                $this->salvarMensagemChat($numero, 'humano', $mensagem, $canal, $usuarioId);
                return $this->respond(['ok' => 'atendimento humano ativado por comando'], 200);
            }
            if (preg_match('/#(ia|retomar|continuar)\b/i', $mensagem)) {
                $sessaoModel->where('numero', $numero)
                            ->where('usuario_id', $usuarioId)
                            ->set(['etapa' => 'em_contato'])->update();
                $this->salvarMensagemChat($numero, 'assistant', $mensagem, $canal, $usuarioId);
                return $this->respond(['ok' => 'IA reativada'], 200);
            }

            // padrão: apenas registra saída
            $this->salvarMensagemChat($numero, $roleSaida, $mensagem, $canal, $usuarioId);
            return $this->respond(['ok' => 'mensagem nossa registrada no chat'], 200);
        }

        /* ===================== MENSAGEM ENTRANTE (lead) ===================== */

        // LOGA antes de processar
        $this->salvarMensagemChat($numero, 'user', $mensagem, $canal, $usuarioId);

        // Aprende nome
        if (preg_match('/\b(meu\s+nome\s+é|meu\s+nome\s+e|sou|eu\s+me\s+chamo)\s+(.{2,60})/i', $mensagem, $m)) {
            $possivelNome = trim(preg_replace('/[^\p{L}\p{M}\s\'.-]/u', '', $m[2]));
            if ($possivelNome && mb_strlen($possivelNome, 'UTF-8') >= 2) {
                $pacienteModel->update($paciente['id'], ['nome' => $possivelNome]);
            }
        }

        // Anti-duplicata simples
        $tempoAtual       = time();
        $janelaDuplicata  = 15; // s
        $cooldownResposta = 6;  // s

        if (!empty($ultimaMsgUsuario)
            && mb_strtolower(trim($ultimaMsgUsuario), 'UTF-8') === mb_strtolower($mensagem, 'UTF-8')
            && $tsAtualizacao && ($tempoAtual - $tsAtualizacao) < $janelaDuplicata) {
            return $this->respond(['ignorado' => 'mensagem duplicada (janela curta)'], 200);
        }
        if ($tsAtualizacao && ($tempoAtual - $tsAtualizacao) < $cooldownResposta) {
            return $this->respond(['ignorado' => 'cooldown ativo'], 200);
        }

        // Debounce por número/linha
        $cache     = \Config\Services::cache();
        $cacheKey  = "ia_lock_{$nossoNumero}_{$numero}";
        if ($cache->get($cacheKey)) {
            return $this->respond(['ignorado' => 'processamento em andamento'], 200);
        }
        $cache->save($cacheKey, 1, 10);

        try {
            // Etapas válidas por ASSINANTE
            $etapasValidas = array_column(
                $configModel->where('assinante_id', $assinanteId)->findAll(),
                'etapa_atual'
            );
            $etapasValidasSet = array_flip($etapasValidas);

            // BLOQUEIOS
            $etapasBloqueadas = ['agendamento', 'finalizado'];
            if (in_array($etapaAtual, $etapasBloqueadas, true)) {
                return $this->respond(['ignorado' => "IA não responde em etapa '$etapaAtual'"], 200);
            }

            // Intenção -> etapa (regras simples)
            $mensagemLower = mb_strtolower($mensagem, 'UTF-8');
            $palavrasChave = [
                'agendamento' => ['agendar', 'consulta', 'marcar', 'horário', 'horario', 'atendimento'],
                'financeiro'  => ['valor', 'preço', 'preco', 'custo', 'quanto', 'pix', 'pagamento', 'pagar'],
                'perdido'     => ['desistir', 'não quero', 'nao quero', 'não tenho interesse', 'nao tenho interesse', 'não posso', 'nao posso', 'depois eu vejo'],
                'em_contato'  => ['me explica', 'quero saber mais', 'entendi', 'ok', 'vamos conversar', 'pode me falar', 'pode explicar'],
            ];
            $novaEtapa = $etapaAtual;
            foreach ($palavrasChave as $etapa => $palavras) {
                foreach ($palavras as $p) {
                    if (mb_strpos($mensagemLower, $p, 0, 'UTF-8') !== false && isset($etapasValidasSet[$etapa])) {
                        $novaEtapa = $etapa;
                        break 2;
                    }
                }
            }

            // Histórico
            $histKey          = "historico_{$usuarioId}_{$nossoNumero}_{$numero}";
            $historicoSessao  = session()->get($histKey) ?? [];
            $historicoBanco   = json_decode($sessao['historico'] ?? '[]', true);
            $historico        = (!empty($historicoSessao)) ? $historicoSessao : (is_array($historicoBanco) ? $historicoBanco : []);

            // Revisita (>7 dias)
            $mensagemRevisita = '';
            if (!empty($historico) && !empty($paciente['ultimo_contato'])) {
                $tempoUltimoContato = strtotime((string)$paciente['ultimo_contato']);
                if ($tempoUltimoContato && (time() - $tempoUltimoContato) > 604800) {
                    $mensagemRevisita = "Que bom te ver por aqui de novo! 😊";
                }
            }

            // Prompt por etapa
            $promptEtapa   = $configModel->where('assinante_id', $assinanteId)
                                         ->where('etapa_atual', $etapaAtual)
                                         ->first();
            $prompt        = get_prompt_padrao();
            $tempoResposta = (int)($promptEtapa['tempo_resposta'] ?? 5);

            // Mensagens para IA
            $mensagens = [['role' => 'system', 'content' => $prompt]];
            foreach ($historico as $msg) {
                if (isset($msg['role'], $msg['content'])) $mensagens[] = $msg;
            }
            $mensagens[] = ['role' => 'user', 'content' => $mensagem];

            if ($tempoResposta > 0) sleep(min($tempoResposta, 5));

            // Chama IA
            $respostaGerada = (new OpenrouterModel())->enviarMensagem($mensagens);
            if ($mensagemRevisita) {
                $respostaGerada = $mensagemRevisita . "\n" . $respostaGerada;
            }

            // Atualiza histórico/etapa
            $historico[] = ['role' => 'user', 'content' => $mensagem];
            $historico[] = ['role' => 'assistant', 'content' => $respostaGerada];
            session()->set($histKey, $historico);

            $etapaFinal = ($novaEtapa !== $etapaAtual && isset($etapasValidasSet[$novaEtapa])) ? $novaEtapa : $etapaAtual;

            if ($etapaFinal !== $etapaAtual) {
                $this->enviarNotificacoesSeEtapaMonitorada($assinanteId, $etapaFinal, $numero, $paciente['nome'] ?? 'Paciente', $usuarioId, $nossoNumero);
            }

            // Persiste sessão
            $sessaoModel->where('numero', $numero)
                        ->where('usuario_id', $usuarioId)
                        ->set([
                            'etapa'                   => $etapaFinal,
                            'ultima_mensagem_usuario' => $mensagem,
                            'ultima_resposta_ia'      => $respostaGerada,
                            'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
                        ])->update();

            // *** Envia resposta ao WhatsApp usando a instância correta
            $this->enviarParaWhatsapp($usuarioId, $nossoNumero, $numero, $respostaGerada);

            // Log de saída
            $this->salvarMensagemChat($numero, 'assistant', $respostaGerada, $canal, $usuarioId);

            return $this->respond(['status' => 'mensagem enviada']);
        } finally {
            $cache->delete($cacheKey);
        }
    }

    /* =================== Notificações por etapa =================== */

    private function enviarNotificacoesSeEtapaMonitorada(
        int $assinanteId,
        string $etapa,
        string $numeroLead,
        string $nomeLead,
        int $usuarioId,
        string $nossoNumero
    ): void {
        $db = \Config\Database::connect();

        // Regras por assinante (fallback sem coluna)
        $regras = [];
        try {
            $regras = $db->table('notificacoes_regras')
                ->where('ativo', 1)
                ->where('assinante_id', $assinanteId)
                ->get()->getResultArray();
        } catch (DatabaseException $e) {
            $regras = $db->table('notificacoes_regras')
                ->where('ativo', 1)
                ->get()->getResultArray();
        }

        $etapasMonitoradas = [];
        $templatesPorEtapa = [];
        if (!empty($regras)) {
            foreach ($regras as $r) {
                $etapasMonitoradas[] = $r['etapa'];
                if (!empty($r['mensagem_template'])) {
                    $templatesPorEtapa[$r['etapa']] = $r['mensagem_template'];
                }
            }
        } else {
            $etapasMonitoradas = ['financeiro']; // fallback
        }

        if (!in_array($etapa, $etapasMonitoradas, true)) return;

        // Destinos por assinante (fallback sem coluna)
        $destinos = [];
        try {
            $destinos = $db->table('notificacoes_whatsapp')
                ->where('ativo', 1)
                ->where('assinante_id', $assinanteId)
                ->get()->getResultArray();
        } catch (DatabaseException $e) {
            $destinos = $db->table('notificacoes_whatsapp')
                ->where('ativo', 1)
                ->get()->getResultArray();
        }
        if (empty($destinos)) return;

        $template = $templatesPorEtapa[$etapa] ?? "Novo lead em *{$etapa}*:\nNome: {nome}\nTelefone: +{numero}";
        $msgBase  = strtr($template, [
            '{etapa}'  => $etapa,
            '{nome}'   => $nomeLead ?: 'Paciente',
            '{numero}' => $numeroLead,
        ]);

        foreach ($destinos as $d) {
            $numeroDestino = $this->soDigitos((string)($d['numero'] ?? ''));
            if ($numeroDestino) {
                // *** usa a instância do próprio usuário/linha que recebeu
                $this->enviarParaWhatsapp($usuarioId, $nossoNumero, $numeroDestino, $msgBase);
            }
        }
    }
}
