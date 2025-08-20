<?php

namespace App\Controllers;

use App\Models\{SessaoModel, OpenrouterModel, PacienteModel};
use CodeIgniter\RESTful\ResourceController;

class Webhook extends ResourceController
{
    /* ====================== Utils ====================== */

    /** Mantém só dígitos. */
    private function soDigitos(?string $v): string
    {
        return preg_replace('/\D+/', '', (string) $v);
    }

    /**
     * UltraMSG:
     * - fromMe=false (lead => nós):  lead=data.from   | nossa linha=data.to
     * - fromMe=true  (nós   => lead): lead=data.to    | nossa linha=data.from
     */
    private function extrairNumerosDoEvento(array $data): array
    {
        $ehNossoEnvio = !empty($data['fromMe']);
        $pacienteRaw  = $ehNossoEnvio ? ($data['to'] ?? '')   : ($data['from'] ?? '');
        $nossoRaw     = $ehNossoEnvio ? ($data['from'] ?? '') : ($data['to']   ?? '');
        $numeroLead   = $this->soDigitos(explode('@', (string)$pacienteRaw)[0] ?? '');
        $nossoNumero  = $this->soDigitos(explode('@', (string)$nossoRaw)[0]     ?? '');
        return [$ehNossoEnvio, $numeroLead, $nossoNumero];
    }

    /** Row da instância (se existir) pela linha (msisdn). */
    private function obterInstanciaDaLinha(string $nossoNumero): ?array
    {
        $db = \Config\Database::connect();
        return $db->table('whatsapp_instancias')
            ->where('linha_msisdn', $nossoNumero)
            ->orderBy('last_status_at', 'DESC')
            ->limit(1)->get()->getRowArray() ?: null;
    }

    /** Resolve dono priorizando a instância; fallback por telefone do usuário. */
    private function encontrarDonoPorInstanciaOuLinha(string $nossoNumero): array
    {
        $db = \Config\Database::connect();

        // via instância
        $inst = $this->obterInstanciaDaLinha($nossoNumero);
        if ($inst && !empty($inst['usuario_id'])) {
            $u = $db->table('usuarios')->select('id, assinante_id')
                ->where('id', (int)$inst['usuario_id'])->get()->getRowArray();
            if ($u) return [(int)$u['id'], (int)$u['assinante_id']];
        }

        // exato
        $u = $db->table('usuarios')->where('telefone_principal', $nossoNumero)->get()->getRowArray();
        if ($u) return [(int)$u['id'], (int)$u['assinante_id']];

        // sufixo
        $len = strlen($nossoNumero);
        for ($take = 11; $take >= 8; $take--) {
            if ($len < $take) continue;
            $suf = substr($nossoNumero, -$take);
            $u = $db->query(
                "SELECT id, assinante_id
                   FROM usuarios
                  WHERE telefone_principal LIKE CONCAT('%', ?)
               ORDER BY LENGTH(telefone_principal) DESC
                  LIMIT 1",
                [$suf]
            )->getRowArray();
            if ($u) return [(int)$u['id'], (int)$u['assinante_id']];
        }

        return [null, null];
    }

    /** Credenciais por MSISDN (preferindo a linha exata). */
    private function pegarCredenciaisUltra(int $usuarioId, string $nossoNumero): ?array
    {
        $db  = \Config\Database::connect();
        $num = $this->soDigitos($nossoNumero);

        // exato
        $q = $db->table('whatsapp_instancias')
            ->where('usuario_id', $usuarioId)
            ->where('linha_msisdn', $num)
            ->limit(1)->get()->getRowArray();
        if ($q) return ['instance_id' => $q['instance_id'], 'token' => $q['token']];

        // sufixo
        $len = strlen($num);
        for ($take = 11; $take >= 8; $take--) {
            if ($len < $take) continue;
            $suf = substr($num, -$take);
            $q = $db->query(
                "SELECT instance_id, token
                   FROM whatsapp_instancias
                  WHERE usuario_id = ?
                    AND linha_msisdn LIKE CONCAT('%', ?)
               ORDER BY LENGTH(linha_msisdn) DESC
                  LIMIT 1",
                [$usuarioId, $suf]
            )->getRowArray();
        if ($q) return ['instance_id' => $q['instance_id'], 'token' => $q['token']];
        }

        // última autenticada
        $q = $db->table('whatsapp_instancias')
            ->where('usuario_id', $usuarioId)
            ->where('conn_status', 'authenticated')
            ->orderBy('last_status_at', 'DESC')
            ->limit(1)->get()->getRowArray();
        if ($q) return ['instance_id' => $q['instance_id'], 'token' => $q['token']];

        return null;
    }

    /** Credenciais por TOKEN (útil quando etapa define token preferido). */
    private function pegarCredenciaisPorToken(string $token): ?array
    {
        $db = \Config\Database::connect();
        $q = $db->table('whatsapp_instancias')
            ->select('instance_id, token')
            ->where('token', $token)
            ->orderBy('id', 'DESC')
            ->limit(1)->get()->getRowArray();
        return $q ? ['instance_id' => $q['instance_id'], 'token' => $q['token']] : null;
    }

/** Busca a instância pelo token, inclusive linha_msisdn, para coerência de linha. */
private function pegarInstanciaPorToken(string $token): ?array
{
    $db = \Config\Database::connect();
    return $db->table('whatsapp_instancias')
        ->where('token', $token)
        ->orderBy('id', 'DESC')
        ->limit(1)->get()->getRowArray() ?: null;
}


    /**
     * Envio via UltraMSG.
     * - Se $tokenPreferido vier, usa a instância vinculada a esse token.
     * - Caso contrário, resolve pela linha ($linhaEnvioMsisdn).
     */
    private function enviarParaWhatsapp(int $usuarioId, string $linhaEnvioMsisdn, string $numeroDestino, string $mensagem, ?string $tokenPreferido=null): bool
    {
        $creds = null;
        if ($tokenPreferido) {
            $creds = $this->pegarCredenciaisPorToken($tokenPreferido);
            if (!$creds) {
                log_message('error', "Token preferido sem instância. token={$tokenPreferido}");
            }
        }
        if (!$creds) {
            $creds = $this->pegarCredenciaisUltra($usuarioId, $linhaEnvioMsisdn);
        }
        if (!$creds) {
            log_message('error', "Sem credenciais UltraMSG (user={$usuarioId}, linha={$linhaEnvioMsisdn})");
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

    /** Log no feed do chat. */
    private function salvarMensagemChat(string $numero, string $role, string $texto, ?string $canal = 'whatsapp', ?int $usuarioId = null): void
    {
        $db = \Config\Database::connect();
        $db->table('chat_mensagens')->insert([
            'numero'     => $numero,
            'role'       => $role,   // 'user' | 'assistant' | 'humano'
            'canal'      => $canal ?: 'whatsapp',
            'usuario_id' => $usuarioId,
            'texto'      => $texto,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Último role no feed do chat para (usuario, numero). */
    private function pegarUltimoRoleChat(int $usuarioId, string $numero): ?string
    {
        $db = \Config\Database::connect();
        $row = $db->table('chat_mensagens')
            ->select('role')
            ->where('usuario_id', $usuarioId)
            ->where('numero', $numero)
            ->orderBy('id', 'DESC')
            ->limit(1)->get()->getRowArray();
        return $row['role'] ?? null;
    }

    /** Idempotência por (instância, provider_id). */
    private function registrarIdempotencia(?string $providerId, string $instanciaKey): bool
    {
        if (!$providerId) return true;
        $db = \Config\Database::connect();

        try {
            $db->query(
                "INSERT IGNORE INTO webhook_msgs (instancia_key, provider_msg_id, created_at)
                 VALUES (?, ?, NOW())",
                [$instanciaKey, $providerId]
            );
            return $db->affectedRows() > 0;
        } catch (\Throwable $e1) {
            log_message('error', 'Idempotência por instância indisponível. Fallback: ' . $e1->getMessage());
            try {
                $db->query(
                    "INSERT IGNORE INTO webhook_msgs (provider_msg_id, created_at)
                     VALUES (?, NOW())",
                    [$providerId]
                );
                return $db->affectedRows() > 0;
            } catch (\Throwable $e2) {
                log_message('error', 'Idempotência falhou: ' . $e2->getMessage());
                return true;
            }
        }
    }

    /** Garante sessoes.canal preenchido. */
    private function garantirCanalSessao(string $numero, int $usuarioId, string $canal = 'whatsapp'): void
    {
        $db = \Config\Database::connect();
        $db->query(
            "UPDATE sessoes
                SET canal = IFNULL(canal, ?)
              WHERE numero = ? AND usuario_id = ?",
            [$canal, $numero, $usuarioId]
        );
    }

    /* ======== Helpers de etapa/instância ======== */

    /** Pega config da etapa (assinante+etapa) do config_ia. */
    private function obterConfigEtapa(int $assinanteId, string $etapa): ?array
    {
        $db = \Config\Database::connect();
        return $db->table('config_ia')
            ->where('assinante_id', $assinanteId)
            ->where('etapa_atual', $etapa)
            ->limit(1)->get()->getRowArray() ?: null;
    }

    /** Retorna msisdn da N-ésima instância autenticada do usuário (1-based). */
    private function pickNthInstanceMsisdn(int $usuarioId, int $ordem): ?string
    {
        if ($ordem <= 0) return null;
        $db = \Config\Database::connect();
        $rows = $db->table('whatsapp_instancias')
            ->where('usuario_id', $usuarioId)
            ->where('conn_status', 'authenticated')
            ->orderBy('last_status_at', 'DESC')
            ->get()->getResultArray();

        $idx = $ordem - 1;
        return isset($rows[$idx]['linha_msisdn']) ? $this->soDigitos($rows[$idx]['linha_msisdn']) : null;
    }

    /**
     * Decide canal de envio:
     * 1) token preferido da etapa (instancia_preferida_token)
     * 2) msisdn preferido (instancia_preferida_msisdn)
     * 3) mesma linha que recebeu
     *
     * Retorna [tokenPreferido|null, msisdnParaEnvio]
     */
  /**
 * Decide canal de envio (com coerência de linha):
 * - Se a etapa tem token preferido -> só usa se a instância desse token tiver a MESMA linha que recebeu.
 * - Se a etapa tem msisdn preferido -> só usa se for EXATAMENTE a MESMA linha que recebeu.
 * - Se tem "ordem" -> só usa se resolver na MESMA linha (na prática, quase sempre será ignorado).
 * - Caso contrário, SEMPRE responde pela MESMA linha que recebeu.
 *
 * Retorna [tokenPreferido|null, msisdnParaEnvio] — msisdnParaEnvio será sempre a linha que recebeu.
 */
private function escolherCanalEnvio(int $usuarioId, string $linhaRecebida, ?array $cfgEtapa): array
{
    $linhaRx = $this->soDigitos($linhaRecebida);

    // 1) token preferido: só aceita se token pertencer à MESMA linha
    $tokenPref = trim((string)($cfgEtapa['instancia_preferida_token'] ?? ''));
    if ($tokenPref !== '') {
        $inst = $this->pegarInstanciaPorToken($tokenPref);
        if ($inst) {
            $linhaDoToken = $this->soDigitos((string)($inst['linha_msisdn'] ?? ''));
            if ($linhaDoToken !== '' && $linhaDoToken === $linhaRx) {
                // ok: mesmo número
                return [$tokenPref, $linhaRecebida];
            }
            // não é a mesma linha → ignorar token preferido
            log_message('debug', 'Coerência de linha: token preferido ignorado (linha diferente).');
        }
    }

    // 2) msisdn preferido: só aceita se for EXATAMENTE a mesma linha
    $preferMsisdn = $this->soDigitos($cfgEtapa['instancia_preferida_msisdn'] ?? '');
    if ($preferMsisdn !== '' && $preferMsisdn === $linhaRx) {
        return [null, $linhaRecebida];
    }

    // 3) ordem N-ésima (opcional): só se resolver para a MESMA linha
    $preferOrdem = (int)($cfgEtapa['instancia_preferida_ordem'] ?? 0);
    if ($preferOrdem > 0) {
        $nMsisdn = $this->pickNthInstanceMsisdn($usuarioId, $preferOrdem);
        if ($nMsisdn && $this->soDigitos($nMsisdn) === $linhaRx) {
            return [null, $linhaRecebida];
        }
        log_message('debug', 'Coerência de linha: ordem preferida ignorada (linha diferente).');
    }

    // 4) fallback: sempre responder pela MESMA linha que recebeu
    return [null, $linhaRecebida];
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

        // --------- NÚMEROS / INSTÂNCIA ---------
        [$ehNossoEnvio, $numero, $nossoNumero] = $this->extrairNumerosDoEvento($data);
        $mensagem   = trim((string)($data['body'] ?? ''));
        $pushname   = (string)($data['pushname'] ?? 'Paciente');
        $canal      = 'whatsapp';

        $instanceIdPayload = (string)($data['instanceId'] ?? $data['instance'] ?? '');
        $instRow           = $this->obterInstanciaDaLinha($nossoNumero);
        $instanciaKey      = $instRow['instance_id'] ?? ($instanceIdPayload ?: $nossoNumero);

        if ($numero === '' || $nossoNumero === '' || $mensagem === '') {
            return $this->respond(['ignorado' => 'dados incompletos'], 200);
        }

        // --------- Idempotência ---------
        $providerId = $data['id'] ?? ($data['messageId'] ?? ($data['message_id'] ?? null));
        if (!$this->registrarIdempotencia(is_string($providerId) ? $providerId : null, $instanciaKey)) {
            return $this->respond(['ignorado' => 'duplicado pela mesma instância'], 200);
        }

        // --------- DONO ---------
        [$usuarioId, $assinanteId] = $this->encontrarDonoPorInstanciaOuLinha($nossoNumero);
        if (!$usuarioId || !$assinanteId) {
            log_message('error', "Webhook: linha/instância sem dono (to={$nossoNumero})");
            return $this->respond(['ignorado' => 'linha não vinculada a usuário'], 200);
        }
        if ($instRow && (int)$instRow['usuario_id'] !== (int)$usuarioId) {
            return $this->respond(['ignorado' => 'instância pertence a outro usuário (duplicado)'], 200);
        }

        // --------- Models / cache ---------
        $pacienteModel = new PacienteModel();
        $sessaoModel   = new SessaoModel();
        $cache         = \Config\Services::cache();

        /* ===================== de nós -> lead (saída) ===================== */
        if ($ehNossoEnvio) {
            $sessao = $sessaoModel->getOuCriarSessao($numero, $usuarioId);
            $ultimaRespostaIa = $sessao['ultima_resposta_ia'] ?? null;

            // Se for eco da IA, marque como assistant e não mexa em nada
            if ($ultimaRespostaIa && trim($ultimaRespostaIa) === $mensagem) {
                $this->salvarMensagemChat($numero, 'assistant', $mensagem, $canal, $usuarioId);
                return $this->respond(['ignorado' => 'eco da própria IA (registrado no chat)'], 200);
            }

            // Qualquer outra mensagem nossa é HUMANA => travar IA
            $sessaoModel->where('numero', $numero)->where('usuario_id', $usuarioId)
                ->set([
                    'etapa'                   => 'humano',   // trava a IA até mudarem manualmente
                    'ultima_resposta_ia'      => null,
                    // mantém historico/ultima_mensagem_usuario conforme fluxo de entrada
                ])->update();

            $this->salvarMensagemChat($numero, 'humano', $mensagem, $canal, $usuarioId);
            return $this->respond(['ok' => 'humano assumiu; IA pausada até mudarem a etapa'], 200);
        }

        /* ===================== lead -> nós (entrada) ===================== */

        // Upsert Paciente (escopo do usuário)
        $paciente = $pacienteModel->where('telefone', $numero)->where('usuario_id', $usuarioId)->first();
        if ($paciente) {
            $pacienteModel->update($paciente['id'], ['ultimo_contato' => date('Y-m-d H:i:s')]);
        } else {
            $pacienteModel->insert([
                'usuario_id'     => $usuarioId,
                'nome'           => $pushname,
                'telefone'       => $numero,
                'ultimo_contato' => date('Y-m-d H:i:s'),
                'origem_contato' => 1,
            ]);
            $paciente = $pacienteModel->where('telefone', $numero)->where('usuario_id', $usuarioId)->first();
        }

        // >>> Captura o último role ANTES de registrar a mensagem do paciente
        $ultimoRoleAntes = $this->pegarUltimoRoleChat($usuarioId, $numero);

        // loga entrada do paciente
        $this->salvarMensagemChat($numero, 'user', $mensagem, $canal, $usuarioId);

        // aprende nome simples
        if (preg_match('/\b(meu\s+nome\s+é|meu\s+nome\s+e|sou|eu\s+me\s+chamo)\s+(.{2,60})/i', $mensagem, $m)) {
            $possivelNome = trim(preg_replace('/[^\p{L}\p{M}\s\'.-]/u', '', $m[2]));
            if ($possivelNome && mb_strlen($possivelNome, 'UTF-8') >= 2) {
                $pacienteModel->update($paciente['id'], ['nome' => $possivelNome]);
            }
        }

        // Sessão
        $sessao            = $sessaoModel->getOuCriarSessao($numero, $usuarioId);
        $etapaAtual        = $sessao['etapa'] ?? 'inicio';
        $ultimaMsgUsuario  = $sessao['ultima_mensagem_usuario'] ?? null;
        $tsAtualizacao     = !empty($sessao['data_atualizacao']) ? strtotime($sessao['data_atualizacao']) : 0;

        // garante canal
        $this->garantirCanalSessao($numero, $usuarioId, $canal);

        // anti-dup/cooldown
        $tempoAtual       = time();
        $janelaDuplicata  = 15;
        $cooldownResposta = 6;

        if (!empty($ultimaMsgUsuario)
            && mb_strtolower(trim($ultimaMsgUsuario), 'UTF-8') === mb_strtolower($mensagem, 'UTF-8')
            && $tsAtualizacao && ($tempoAtual - $tsAtualizacao) < $janelaDuplicata) {
            return $this->respond(['ignorado' => 'mensagem duplicada (janela curta)'], 200);
        }
        if ($tsAtualizacao && ($tempoAtual - $tsAtualizacao) < $cooldownResposta) {
            return $this->respond(['ignorado' => 'cooldown ativo'], 200);
        }

        // lock curto
        $cacheKey = "ia_lock_{$usuarioId}_{$nossoNumero}_{$numero}";
        if ($cache->get($cacheKey)) {
            return $this->respond(['ignorado' => 'processamento em andamento'], 200);
        }
        $cache->save($cacheKey, 1, 10);

        try {
            // etapas válidas (por usuário)
            $etapasValidas = (array) $sessaoModel->listarEtapasUsuario($usuarioId);
            if (!empty($etapasValidas) && is_array($etapasValidas) && isset($etapasValidas[0]) && is_array($etapasValidas[0]) && isset($etapasValidas[0]['etapa'])) {
                $etapasValidas = array_map(fn($r) => (string)$r['etapa'], $etapasValidas);
            }
            $etapasValidasSet = array_flip($etapasValidas);

            // 1) Se HUMANO assumiu (etapa='humano') => IA SILENCIOSA + não mudar etapa
            if ($etapaAtual === 'humano') {
                $historico = json_decode($sessao['historico'] ?? '[]', true);
                if (!is_array($historico)) $historico = [];
                $historico[] = ['role' => 'user', 'content' => $mensagem];

                $sessaoModel->where('numero', $numero)->where('usuario_id', $usuarioId)->set([
                    'etapa'                   => 'humano', // mantém travada
                    'ultima_mensagem_usuario' => $mensagem,
                    'ultima_resposta_ia'      => null,
                    'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
                ])->update();

                return $this->respond(['ok' => 'IA silenciosa: atendimento humano ativo'], 200);
            }

            // 2) Se a ÚLTIMA mensagem antes desta foi de HUMANO, também silencia (mesmo que tenham mudado etapa)
            if ($ultimoRoleAntes === 'humano') {
                $historico = json_decode($sessao['historico'] ?? '[]', true);
                if (!is_array($historico)) $historico = [];
                $historico[] = ['role' => 'user', 'content' => $mensagem];

                $sessaoModel->where('numero', $numero)->where('usuario_id', $usuarioId)->set([
                    'etapa'                   => $etapaAtual, // não mexe
                    'ultima_mensagem_usuario' => $mensagem,
                    'ultima_resposta_ia'      => null,
                    'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
                ])->update();

                return $this->respond(['ok' => 'IA silenciosa: humano ainda conversando'], 200);
            }

            // 3) intenção -> etapa (simples) + alinhamento (apenas se não for humano)
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
                    if (mb_strpos($mensagemLower, $p, 0, 'UTF-8') !== false) {
                        $alinhada = $sessaoModel->alinharEtapaUsuario($etapa, $usuarioId);
                        if ($alinhada && isset($etapasValidasSet[$alinhada])) {
                            $novaEtapa = $alinhada;
                            break 2;
                        }
                    }
                }
            }

            // etapa final sanitizada
            $etapaFinal = ($novaEtapa !== $etapaAtual && isset($etapasValidasSet[$novaEtapa]))
                ? $novaEtapa
                : (isset($etapasValidasSet[$etapaAtual]) ? $etapaAtual : 'inicio');

            // === Config da ETAPA (respeito ao ia_pode_responder) ===
            $cfgEtapa = $this->obterConfigEtapa($assinanteId, $etapaFinal) ?? [];
            $iaPode   = (int)($cfgEtapa['ia_pode_responder'] ?? 1) === 1;

            // histórico (carrega e adiciona user)
            $historico = json_decode($sessao['historico'] ?? '[]', true);
            if (!is_array($historico)) $historico = [];
            $historico[] = ['role' => 'user', 'content' => $mensagem];

            // Se a etapa não permite IA, apenas registra e sai (não muda etapa por humano)
            if (!$iaPode) {
                $sessaoModel->where('numero', $numero)->where('usuario_id', $usuarioId)
                    ->set([
                        'etapa'                   => $etapaFinal,
                        'ultima_mensagem_usuario' => $mensagem,
                        'ultima_resposta_ia'      => null,
                        'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
                    ])->update();

                return $this->respond(['ok' => "IA silenciosa por configuração da etapa '{$etapaFinal}'"], 200);
            }

            // ========== IA habilitada ==========
            // Revisita (>7 dias)
            $mensagemRevisita = '';
            if (!empty($paciente['ultimo_contato'])) {
                $tempoUltimoContato = strtotime((string)$paciente['ultimo_contato']);
                if ($tempoUltimoContato && (time() - $tempoUltimoContato) > 604800) {
                    $mensagemRevisita = "Que bom te ver por aqui de novo! 😊";
                }
            }

            // prompt + histórico
            $prompt    = get_prompt_padrao();
            $mensagens = [['role' => 'system', 'content' => $prompt]];
            foreach ($historico as $msg) {
                if (isset($msg['role'], $msg['content'])) $mensagens[] = $msg;
            }

            // latência leve (opcional)
            sleep(3);

            // Chamada IA (passando ETAPA FINAL)
            $open            = new OpenrouterModel();
            $respostaGerada  = $open->enviarMensagem($mensagens, null, [
                'temperatura'   => 0.8,
                'top_p'         => 0.9,
                'estiloMocinha' => true,
                'max_tokens'    => 300,
                'assinante_id'  => $assinanteId,
                'etapa'         => $etapaFinal,
            ]);
            if ($mensagemRevisita) {
                $respostaGerada = $mensagemRevisita . "\n" . $respostaGerada;
            }

            // fecha histórico
            $historico[] = ['role' => 'assistant', 'content' => $respostaGerada];

            // persiste sessão
            $sessaoModel->where('numero', $numero)->where('usuario_id', $usuarioId)->set([
                'etapa'                   => $etapaFinal,
                'ultima_mensagem_usuario' => $mensagem,
                'ultima_resposta_ia'      => $respostaGerada,
                'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
            ])->update();

            // === Canal de envio conforme etapa ===
            [$tokenPreferido, $linhaEnvio] = $this->escolherCanalEnvio($usuarioId, $nossoNumero, $cfgEtapa);

            // envia
            $this->enviarParaWhatsapp($usuarioId, $linhaEnvio, $numero, $respostaGerada, $tokenPreferido ?: null);

            // loga saída
            $this->salvarMensagemChat($numero, 'assistant', $respostaGerada, $canal, $usuarioId);

            // notificação se mudou de etapa
            if ($etapaFinal !== $etapaAtual) {
                $this->enviarNotificacoesSeEtapaMonitorada(
                    $usuarioId, $etapaFinal, $numero, $paciente['nome'] ?? 'Paciente', $linhaEnvio, $etapasValidasSet
                );
            }

            return $this->respond(['status' => 'mensagem enviada']);
        } finally {
            $cache->delete($cacheKey);
        }
    }

    /* =================== Notificações por etapa =================== */

    private function enviarNotificacoesSeEtapaMonitorada(
        int $usuarioId,
        string $etapa,
        string $numeroLead,
        string $nomeLead,
        string $nossoNumeroParaEnvio,
        array  $etapasValidasSet
    ): void {
        $db = \Config\Database::connect();

        $regras = $db->table('notificacoes_regras')
            ->where('ativo', 1)
            ->where('usuario_id', $usuarioId)
            ->get()->getResultArray();

        $etapasMonitoradas = [];
        $templatesPorEtapa = [];
        foreach ($regras as $r) {
            $e = (string)$r['etapa'];
            if ($e !== '' && isset($etapasValidasSet[$e])) {
                $etapasMonitoradas[] = $e;
                if (!empty($r['mensagem_template'])) {
                    $templatesPorEtapa[$e] = $r['mensagem_template'];
                }
            }
        }

        if (!in_array($etapa, $etapasMonitoradas, true)) return;

        $destinos = $db->table('notificacoes_whatsapp')
            ->where('ativo', 1)
            ->where('usuario_id', $usuarioId)
            ->get()->getResultArray();
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
                // mesmo canal usado para responder ao lead
                $this->enviarParaWhatsapp($usuarioId, $nossoNumeroParaEnvio, $numeroDestino, $msgBase);
            }
        }
    }
}
