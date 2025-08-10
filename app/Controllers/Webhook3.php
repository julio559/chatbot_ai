<?php

namespace App\Controllers;

use App\Models\{SessaoModel, OpenrouterModel, PacienteModel, ConfigIaModel};
use CodeIgniter\RESTful\ResourceController;

class Webhook extends ResourceController
{
    public function index()
    {
        helper('ia');

        $json = $this->request->getJSON(true);

        // --------- Validação básica do payload ---------
        if (!isset($json['data'])) {
            return $this->respond(['ignorado' => 'payload inválido'], 200);
        }
        $data = $json['data'];

        // Quem é o paciente?
        // - Se fromMe=true (nós enviamos — pode ser IA ou operador), "to" é o paciente
        // - Se fromMe=false (paciente enviou), "from" é o paciente
        $alvoRaw  = (!empty($data['fromMe'])) ? ($data['to'] ?? null) : ($data['from'] ?? null);
        $body     = $data['body'] ?? null;
        $pushname = $data['pushname'] ?? 'Paciente';

        if (!$alvoRaw || $body === null) {
            return $this->respond(['ignorado' => 'mensagem inválida'], 200);
        }

        $numero         = preg_replace('/[^0-9]/', '', explode('@', $alvoRaw)[0]);
        $nome           = $pushname;
        $mensagem       = trim($body);
        $mensagemLower  = mb_strtolower($mensagem, 'UTF-8');
        $ehNossoEnvio   = !empty($data['fromMe']); // pode ser IA (UltraMSG) ou humano

        // --------- Models / serviços ---------
        $pacienteModel = new PacienteModel();
        $sessaoModel   = new SessaoModel();
        $configModel   = new ConfigIaModel();
        $cache         = \Config\Services::cache();

        // --------- Upsert Paciente (sempre) ---------
        $paciente = $pacienteModel->where('telefone', $numero)->first();
        if ($paciente) {
            $pacienteModel->update($paciente['id'], ['ultimo_contato' => date('Y-m-d H:i:s')]);
        } else {
            $pacienteModel->insert([
                'nome'            => $nome,
                'telefone'        => $numero,
                'ultimo_contato'  => date('Y-m-d H:i:s'),
                'origem_contato'  => 1, // WhatsApp
            ]);
            $paciente = $pacienteModel->where('telefone', $numero)->first();
        }

        // --------- Sessão atual ---------
        $sessao = $sessaoModel->getOuCriarSessao($numero);
        $etapaAtual = $sessao['etapa'];
        $ultimaRespostaIa = $sessao['ultima_resposta_ia'] ?? null;
        $ultimaMsgUsuario = $sessao['ultima_mensagem_usuario'] ?? null;
        $tsAtualizacao    = !empty($sessao['data_atualizacao']) ? strtotime($sessao['data_atualizacao']) : 0;

        // --------- Se webhook é nosso (fromMe=true) ---------
        if ($ehNossoEnvio) {
            // 1) Evita loop: se o corpo é exatamente a última resposta da IA, ignore
            if ($ultimaRespostaIa && trim($ultimaRespostaIa) === $mensagem) {
                return $this->respond(['ignorado' => 'eco da própria IA (ignorado)'], 200);
            }

            // 2) Comando para pausar IA e passar para humano
            if (preg_match('/#(humano|pausar|pause)/i', $mensagem)) {
                $sessaoModel->where('numero', $numero)->set([
                    'etapa' => 'humano',
                    'ultima_mensagem_usuario' => null,
                    'ultima_resposta_ia' => null,
                ])->update();
                return $this->respond(['ok' => 'atendimento humano ativado por comando'], 200);
            }

            // 3) Comando para reativar IA
            if (preg_match('/#(ia|retomar|continuar)/i', $mensagem)) {
                $sessaoModel->where('numero', $numero)->set(['etapa' => 'em_contato'])->update();
                return $this->respond(['ok' => 'IA reativada'], 200);
            }

            // 4) Caso seja um envio nosso (IA/operador) que não é comando, não altere nada
            return $this->respond(['ok' => 'mensagem nossa (IA/operador) ignorada para fluxo'], 200);
        }

        // --------- Aprender nome se usuário disser "meu nome é ..." ---------
        if (preg_match('/\b(meu\s+nome\s+é|meu\s+nome\s+e|sou|eu\s+me\s+chamo)\s+(.{2,60})/i', $mensagem, $m)) {
            $possivelNome = trim(preg_replace('/[^\p{L}\p{M}\s\'.-]/u', '', $m[2]));
            if ($possivelNome && mb_strlen($possivelNome, 'UTF-8') >= 2) {
                $pacienteModel->update($paciente['id'], ['nome' => $possivelNome]);
                $nome = $possivelNome;
            }
        }

        // --------- Controles anti-spam / single-reply ---------
        $tempoAtual       = time();
        $janelaDuplicata  = 15; // seg — mesma mensagem repetida: ignora
        $cooldownResposta = 6;  // seg — evita responder duas vezes em sequência (1 resposta tranquila)

        // a) mesma msg do usuário em janela curta
        if (!empty($ultimaMsgUsuario)
            && $mensagemLower === mb_strtolower(trim($ultimaMsgUsuario), 'UTF-8')
            && $tsAtualizacao && ($tempoAtual - $tsAtualizacao) < $janelaDuplicata
        ) {
            return $this->respond(['ignorado' => 'mensagem duplicada em janela curta'], 200);
        }

        // b) cooldown entre respostas (evita duas respostas da IA muito próximas)
        if ($tsAtualizacao && ($tempoAtual - $tsAtualizacao) < $cooldownResposta) {
            return $this->respond(['ignorado' => 'cooldown ativo; evitando múltiplas respostas'], 200);
        }

        // c) Debounce via cache (se já existe processamento em andamento, ignore)
        $lockKey = "ia_lock_{$numero}";
        $lockTTL = 10; // seg
        if ($cache->get($lockKey)) {
            return $this->respond(['ignorado' => 'processamento em andamento (debounce)'], 200);
        }
        $cache->save($lockKey, 1, $lockTTL);

        try {
            // --------- Etapas válidas (do config_ia) ---------
            $etapasValidas = array_column(
                $configModel->where('assinante_id', 1)->findAll(),
                'etapa_atual'
            );
            $etapasValidasSet = array_flip($etapasValidas);

            // --------- BLOQUEIOS ---------
            // IA não responde apenas em etapas de fim de funil
            $etapasBloqueadas = ['agendamento', 'finalizado'];
            if (in_array($etapaAtual, $etapasBloqueadas, true)) {
                return $this->respond(['ignorado' => "IA não responde em etapa '$etapaAtual'"], 200);
            }

            // --------- Detectar intenção -> sugerir etapa (se válida) ---------
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

            // --------- Histórico (sessão + banco) ---------
            $historicoSessao = session()->get("historico_{$numero}") ?? [];
            $historicoBanco  = json_decode($sessao['historico'] ?? '[]', true);
            $historico       = (!empty($historicoSessao)) ? $historicoSessao : $historicoBanco;

            // --------- Mensagem de revisita (>7 dias) ---------
            $mensagemRevisita = '';
            if (!empty($historico) && isset($paciente['ultimo_contato'])) {
                $tempoUltimoContato = strtotime($paciente['ultimo_contato']);
                if ($tempoUltimoContato && (time() - $tempoUltimoContato) > 604800) { // 7 dias
                    $mensagemRevisita = "Que bom te ver por aqui de novo! 😊";
                }
            }

            // --------- Prompt por etapa ---------
            $promptEtapa = $configModel
                ->where('assinante_id', 1)
                ->where('etapa_atual', $etapaAtual)
                ->first();

            $prompt        =  get_prompt_padrao();
            $tempoResposta = (int)($promptEtapa['tempo_resposta'] ?? 5);

            // --------- Mensagens para IA ---------
            $mensagens = [['role' => 'system', 'content' => $prompt]];
            foreach ($historico as $msg) {
                if (isset($msg['role'], $msg['content'])) {
                    $mensagens[] = $msg;
                }
            }
            $mensagens[] = ['role' => 'user', 'content' => $mensagem];

            // Latência simulada (curta) para parecer natural sem spam
            if ($tempoResposta > 0) {
                $delay = min($tempoResposta, 5); // no máx 5s
                sleep($delay);
            }

            // --------- Chamada à IA ---------
            $respostaGerada = (new OpenrouterModel())->enviarMensagem($mensagens);

            // Mensagem de revisita no topo (uma linha, mantendo 1 resposta só)
            if ($mensagemRevisita) {
                $respostaGerada = $mensagemRevisita . "\n" . $respostaGerada;
            }

            // --------- Atualiza histórico local ---------
            $historico[] = ['role' => 'user', 'content' => $mensagem];
            $historico[] = ['role' => 'assistant', 'content' => $respostaGerada];
            session()->set("historico_{$numero}", $historico);

            // --------- Persistir sessão ---------
            $etapaFinal = ($novaEtapa !== $etapaAtual && isset($etapasValidasSet[$novaEtapa])) ? $novaEtapa : $etapaAtual;

            // Notificação se mudou para etapa monitorada
            if ($etapaFinal !== $etapaAtual) {
                $this->enviarNotificacoesSeEtapaMonitorada($etapaFinal, $numero, $nome);
            }

            $sessaoModel->where('numero', $numero)->set([
                'etapa'                   => $etapaFinal,
                'ultima_mensagem_usuario' => $mensagem,
                'ultima_resposta_ia'      => $respostaGerada,
                'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
            ])->update();

            // --------- Enviar resposta ao paciente (apenas 1) ---------
            $this->enviarParaWhatsapp($numero, $respostaGerada);

            return $this->respond(['status' => 'mensagem enviada']);
        } finally {
            // Libera o lock
            $cache->delete($lockKey);
        }
    }

    /**
     * Envia mensagem de WhatsApp usando UltraMSG
     */
    private function enviarParaWhatsapp($numero, $mensagem)
    {
        $instanceId = 'instance136009';
        $token      = 'rbsu6e74buuzsnjj';
        $url        = "https://api.ultramsg.com/{$instanceId}/messages/chat";

        $data = [
            'token' => $token,
            'to'    => $numero,
            'body'  => $mensagem
        ];

        $headers = ['Content-Type: application/x-www-form-urlencoded'];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $result   = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log para debugar
        log_message('error', "Envio para WhatsApp ({$numero}): HTTP {$httpCode} - {$result}");
        if ($error) {
            log_message('error', "Erro cURL: " . $error);
        }

        return $result;
    }

    /**
     * Se a etapa final estiver monitorada, envia notificações para os números ativos
     * de notificacoes_whatsapp. Usa template de notificacoes_regras se existir,
     * senão cai em uma mensagem padrão para 'financeiro'.
     */
    private function enviarNotificacoesSeEtapaMonitorada(string $etapa, string $numeroLead, string $nomeLead): void
    {
        $db = \Config\Database::connect();

        // Carrega regras ativas. Se não houver, cria uma lista default com 'financeiro'
        $regras = $db->table('notificacoes_regras')
            ->where('ativo', 1)
            ->get()->getResultArray();

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
            // fallback: monitora somente 'financeiro'
            $etapasMonitoradas = ['financeiro'];
        }

        if (!in_array($etapa, $etapasMonitoradas, true)) {
            return; // etapa não monitorada: não notifica
        }

        // Busca destinatários ativos
        $destinos = $db->table('notificacoes_whatsapp')
            ->where('ativo', 1)
            ->get()->getResultArray();

        if (empty($destinos)) {
            return; // ninguém para notificar
        }

        // Monta mensagem
        $template = $templatesPorEtapa[$etapa] ?? "Novo lead em *{$etapa}*:\nNome: {nome}\nTelefone: +{numero}";
        $msgBase  = strtr($template, [
            '{etapa}'  => $etapa,
            '{nome}'   => $nomeLead ?: 'Paciente',
            '{numero}' => $numeroLead,
        ]);

        foreach ($destinos as $d) {
            $numeroDestino = preg_replace('/\D+/', '', $d['numero']); // garante só dígitos
            if (!$numeroDestino) continue;

            $this->enviarParaWhatsapp($numeroDestino, $msgBase);
        }
    }
}
