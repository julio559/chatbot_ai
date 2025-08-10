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
        // - Se fromMe=true (você enviou), "to" é o paciente
        // - Se fromMe=false (paciente enviou), "from" é o paciente
        $alvoRaw  = (!empty($data['fromMe'])) ? ($data['to'] ?? null) : ($data['from'] ?? null);
        $body     = $data['body'] ?? null;
        $pushname = $data['pushname'] ?? 'Paciente';

        if (!$alvoRaw || !$body) {
            return $this->respond(['ignorado' => 'mensagem inválida'], 200);
        }

        $numero   = preg_replace('/[^0-9]/', '', explode('@', $alvoRaw)[0]);
        $nome     = $pushname;
        $mensagem = strtolower(trim($body));

        // --------- Models / serviços ---------
        $pacienteModel = new PacienteModel();
        $sessaoModel   = new SessaoModel();
        $configModel   = new ConfigIaModel();
        $cache         = \Config\Services::cache();

        // --------- Se atendente enviou: travar em HUMANO ---------
        if (!empty($data['fromMe'])) {
            // Garante sessão
            $sessaoModel->getOuCriarSessao($numero);

            // Trava para humano
            $sessaoModel->where('numero', $numero)->set([
                'etapa' => 'humano',
                'ultima_mensagem_usuario' => null,
                'ultima_resposta_ia' => null,
            ])->update();

            // Atualiza/insere paciente (último contato)
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
            }

            return $this->respond(['ignorado' => 'atendimento humano ativo (IA travada)'], 200);
        }

        // --------- Mensagem veio do paciente: upsert paciente ---------
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
            // re-carrega para ter $paciente preenchido
            $paciente = $pacienteModel->where('telefone', $numero)->first();
        }

        // --------- Sessão atual ---------
        $sessao = $sessaoModel->getOuCriarSessao($numero);
        $etapaAtual = $sessao['etapa'];
        $novaEtapa  = $etapaAtual;

        // --------- BLOQUEIOS ---------
        // 1) Etapas onde a IA não responde
        $etapasBloqueadas = ['agendamento', 'finalizado', 'humano'];
        if (in_array($etapaAtual, $etapasBloqueadas, true)) {
            return $this->respond(['ignorado' => "IA não responde em etapa '$etapaAtual'"], 200);
        }

        // 2) Anti-duplicata (mesma msg em janela curta)
        $tempoAtual      = time();
        $tsAtualizacao   = !empty($sessao['data_atualizacao']) ? strtotime($sessao['data_atualizacao']) : 0;
        $janelaDuplicata = 10; // seg
        if (!empty($sessao['ultima_mensagem_usuario'])
            && $mensagem === strtolower(trim($sessao['ultima_mensagem_usuario']))
            && $tsAtualizacao && ($tempoAtual - $tsAtualizacao) < $janelaDuplicata
        ) {
            return $this->respond(['ignorado' => 'mensagem duplicada em janela curta'], 200);
        }

        // 3) Debounce via cache (evita responder duas vezes coladas)
        $lockKey = "ia_lock_{$numero}";
        $lockTTL = 8; // seg
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
            $etapasValidasSet = array_flip($etapasValidas); // validação rápida

            // --------- Detectar intenção -> sugerir etapa (se válida) ---------
            $palavrasChave = [
                'agendamento' => ['agendar', 'consulta', 'marcar', 'horário', 'atendimento'],
                'financeiro'  => ['valor', 'preço', 'custo', 'quanto', 'pix'],
                'perdido'     => ['desistir', 'não quero', 'nao quero', 'não tenho interesse', 'nao tenho interesse', 'não posso', 'nao posso'],
                'em_contato'  => ['me explica', 'quero saber mais', 'entendi', 'ok', 'vamos conversar'],
            ];

            $resposta = '';
            foreach ($palavrasChave as $etapa => $palavras) {
                foreach ($palavras as $p) {
                    if (strpos($mensagem, $p) !== false && isset($etapasValidasSet[$etapa])) {
                        $novaEtapa = $etapa;
                        $resposta  = "Certo! Me dá só um minutinho aqui...";
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
                if ($tempoUltimoContato && (time() - $tempoUltimoContato > 604800)) { // 7 dias
                    $mensagemRevisita = "Que bom te ver por aqui de novo! 😊";
                }
            }

            // --------- Prompt por etapa ---------
            $promptEtapa = $configModel
                ->where('assinante_id', 1)
                ->where('etapa_atual', $etapaAtual)
                ->first();

            $prompt        = ($promptEtapa['prompt_base'] ?? null) ?: get_prompt_padrao();
            $tempoResposta = (int)($promptEtapa['tempo_resposta'] ?? 5);

            // --------- Mensagens para IA ---------
            $mensagens = [['role' => 'system', 'content' => $prompt]];
            foreach ($historico as $msg) {
                if (isset($msg['role'], $msg['content'])) {
                    $mensagens[] = $msg;
                }
            }
            $mensagens[] = ['role' => 'user', 'content' => $mensagem];

            // Latência simulada
            if ($tempoResposta > 0) {
                sleep($tempoResposta);
            }

            // --------- Chamada à IA ---------
            $respostaGerada = (new OpenrouterModel())->enviarMensagem($mensagens);
            if ($mensagemRevisita) {
                $respostaGerada = $mensagemRevisita . "\n" . $respostaGerada;
            }

            // --------- Atualiza histórico local ---------
            $historico[] = ['role' => 'user', 'content' => $mensagem];
            $historico[] = ['role' => 'assistant', 'content' => $respostaGerada];
            session()->set("historico_{$numero}", $historico);

            // --------- Decidir etapa a salvar ---------
            $mudouParaEtapaValida = ($novaEtapa !== $etapaAtual && isset($etapasValidasSet[$novaEtapa]));
            $etapaFinal = $mudouParaEtapaValida ? $novaEtapa : $etapaAtual;
            if ($novaEtapa !== $etapaAtual && !$mudouParaEtapaValida) {
                log_message('warning', "Webhook: tentativa de mudar para etapa inválida '{$novaEtapa}' para número {$numero}");
            }

            // --------- Enviar notificação se mudou para etapa monitorada ---------
            if ($mudouParaEtapaValida) {
                $this->enviarNotificacoesSeEtapaMonitorada($etapaFinal, $numero, $nome);
            }

            // --------- Persistir sessão ---------
            $sessaoModel->where('numero', $numero)->set([
                'etapa'                   => $etapaFinal,
                'ultima_mensagem_usuario' => $mensagem,
                'ultima_resposta_ia'      => $respostaGerada,
                'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
            ])->update();

            // --------- Enviar resposta ao paciente ---------
            $this->enviarParaWhatsapp($numero, $respostaGerada);

            return $this->respond(['status' => 'mensagem enviada']);
        } finally {
            // Libera o lock
            $cache->delete($lockKey);
        }
    }

    /**
     * Envia mensagem de WhatsApp usando UltraMSG (já no seu padrão)
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
