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

        if (!isset($json['data']['from']) || !isset($json['data']['body']) || $json['data']['fromMe'] === true) {
            return $this->respond(['ignorado' => 'mensagem do atendente ou inválida'], 200);
        }

        $numeroRaw = $json['data']['from'];
        $numero = preg_replace('/[^0-9]/', '', explode('@', $numeroRaw)[0]);
        $nome = $json['data']['pushname'] ?? 'Paciente';
        $mensagem = strtolower(trim($json['data']['body']));

        // Atualizar ou inserir paciente
        $pacienteModel = new PacienteModel();
        $paciente = $pacienteModel->where('telefone', $numero)->first();
        if ($paciente) {
            $pacienteModel->update($paciente['id'], ['ultimo_contato' => date('Y-m-d H:i:s')]);
        } else {
            $pacienteModel->insert(['nome' => $nome, 'telefone' => $numero, 'ultimo_contato' => date('Y-m-d H:i:s')]);
        }

        // Sessão
        $sessaoModel = new SessaoModel();
        $sessao = $sessaoModel->getOuCriarSessao($numero);
        $etapaAtual = $sessao['etapa'];
        $novaEtapa = $etapaAtual;
        $resposta = '';

        // 🔒 Bloqueia resposta da IA se estiver em etapas específicas
        $etapasBloqueadas = ['agendamento', 'finalizado'];
        if (in_array($etapaAtual, $etapasBloqueadas)) {
            return $this->respond(['ignorado' => "IA não responde em etapa '$etapaAtual'"], 200);
        }

        // 🔍 Etapas válidas do banco
        $configModel = new ConfigIaModel();
        $etapasValidas = array_column(
            $configModel->where('assinante_id', 1)->findAll(),
            'etapa_atual'
        );

        // 🔍 Detectar intenção e atualizar etapa se necessário
        $palavrasChave = [
            'agendamento' => ['agendar', 'consulta', 'marcar', 'horário', 'atendimento'],
            'financeiro' => ['valor', 'preço', 'custo', 'quanto', 'pix'],
            'perdido' => ['desistir', 'não quero', 'não tenho interesse', 'não posso'],
            'em_contato' => ['me explica', 'quero saber mais', 'entendi', 'ok', 'vamos conversar'],
        ];

        foreach ($palavrasChave as $etapa => $palavras) {
            foreach ($palavras as $p) {
                if (strpos($mensagem, $p) !== false && in_array($etapa, $etapasValidas)) {
                    $novaEtapa = $etapa;
                    $resposta = "Certo! Me dá só um minutinho aqui...";
                    break 2;
                }
            }
        }

        // 🔁 Histórico
        $historicoSessao = session()->get("historico_{$numero}") ?? [];
        $historicoBanco = json_decode($sessao['historico'] ?? '[]', true);
        $historico = (!empty($historicoSessao)) ? $historicoSessao : $historicoBanco;

        // 🔔 Revisita
        $mensagemRevisita = '';
        if (!empty($historico) && isset($paciente['ultimo_contato'])) {
            $tempoUltimoContato = strtotime($paciente['ultimo_contato']);
            if ($tempoUltimoContato && (time() - $tempoUltimoContato > 604800)) {
                $mensagemRevisita = "Que bom te ver por aqui de novo! 😊";
            }
        }

        // 🧠 Prompt da IA com base na etapa atual
        $promptEtapa = $configModel
            ->where('assinante_id', 1)
            ->where('etapa_atual', $etapaAtual)
            ->first();

        $promptBase = $promptEtapa['prompt_base'] ?? null;
        $prompt = $promptBase ?: get_prompt_padrao();
        $tempoResposta = $promptEtapa['tempo_resposta'] ?? 5;

        // 💬 Envia mensagem para IA
        $mensagens = [['role' => 'system', 'content' => $prompt]];
        foreach ($historico as $msg) $mensagens[] = $msg;
        $mensagens[] = ['role' => 'user', 'content' => $mensagem];

        sleep((int)$tempoResposta);
        $respostaGerada = (new OpenrouterModel())->enviarMensagem($mensagens);

        if ($mensagemRevisita) {
            $respostaGerada = $mensagemRevisita . "\n" . $respostaGerada;
        }

        // 📝 Atualiza tudo
        $historico[] = ['role' => 'user', 'content' => $mensagem];
        $historico[] = ['role' => 'assistant', 'content' => $respostaGerada];

        session()->set("historico_{$numero}", $historico);

        $sessaoModel->where('numero', $numero)->set([
            'etapa' => $novaEtapa,
            'ultima_mensagem_usuario' => $mensagem,
            'ultima_resposta_ia' => $respostaGerada,
            'historico' => json_encode($historico)
        ])->update();

        $this->enviarParaWhatsapp($numero, $respostaGerada);
        return $this->respond(['status' => 'mensagem enviada']);
    }

    private function enviarParaWhatsapp($numero, $mensagem)
    {
        $instanceId = 'instance136009';
        $token = 'rbsu6e74buuzsnjj';
        $url = "https://api.ultramsg.com/{$instanceId}/messages/chat";

        $data = [
            'token' => $token,
            'to' => $numero,
            'body' => $mensagem
        ];

        $headers = ['Content-Type: application/x-www-form-urlencoded'];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_exec($ch);
        curl_close($ch);
    }
}
