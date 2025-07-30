<?php

namespace App\Controllers;

use App\Models\SessaoModel;
use App\Models\OpenrouterModel;
use App\Models\PacienteModel;
use App\Models\ConfigIaModel;
use CodeIgniter\RESTful\ResourceController;

class Webhook extends ResourceController
{
    public function index()
    {
        $json = $this->request->getJSON(true);

        if (
            !isset($json['data']['from']) ||
            !isset($json['data']['body']) ||
            $json['data']['fromMe'] === true
        ) {
            return $this->respond(['ignorado' => true], 200);
        }

        $numeroRaw = $json['data']['from'];
        $numero = preg_replace('/[^0-9]/', '', explode('@', $numeroRaw)[0]);
        $nome = $json['data']['pushname'] ?? 'Paciente';
        $mensagem = strtolower(trim($json['data']['body']));

        // Atualiza ou cria paciente
        $pacienteModel = new PacienteModel();
        $pacienteExistente = $pacienteModel->where('telefone', $numero)->first();
        if ($pacienteExistente) {
            $pacienteModel->update($pacienteExistente['id'], ['ultimo_contato' => date('Y-m-d H:i:s')]);
        } else {
            $pacienteModel->insert(['nome' => $nome, 'telefone' => $numero]);
        }

        // Sessão
        $sessaoModel = new SessaoModel();
        $sessao = $sessaoModel->getOuCriarSessao($numero);
        $etapaAtual = $sessao['etapa'];
        $novaEtapa = $etapaAtual;
        $resposta = '';

        // Busca Configuração da IA (ID fixo = 1 por enquanto)
        $configModel = new ConfigIaModel();
        $config = $configModel->where('assinante_id', 1)->first();

        // Valores padrão
        $config = $config ?? [
            'tempo_resposta' => 5,
            'prompt_base' => "Você é a assistente humana da Dra. Bruna Sathler. Responda como se estivesse no WhatsApp, com gentileza e naturalidade. Use frases curtas, como um humano faria. Se a pessoa disser 'oi', 'olá', ou 'tudo bem?', apenas cumprimente de volta e pergunte se pode ajudar. Nunca mencione equipe, atendimento, procedimentos ou agendamento, a menos que a pessoa peça algo relacionado. Seja objetiva e educada, sem parecer robô. Não repita informações nem antecipe assuntos.",
            'modo_formal' => false,
            'permite_respostas_longas' => false,
            'permite_redirecionamento' => false
        ];

        // Se já está em agendamento ou orçamento, não responde
        if (in_array($etapaAtual, ['agendamento', 'orcamento'])) {
            return $this->respond(['status' => 'aguardando equipe'], 200);
        }

        // Lógica de detecção de intenção
        $palavrasAgendamento = ['agendar', 'consulta', 'marcar', 'horário'];
        $palavrasOrcamento = ['valor', 'preço', 'custo', 'quanto', 'orcamento'];

        foreach ($palavrasAgendamento as $p) {
            if (strpos($mensagem, $p) !== false) {
                $resposta = "Certo! Já estou chamando alguém da equipe para agendar com você.";
                $novaEtapa = 'agendamento';
                break;
            }
        }

        if ($novaEtapa === $etapaAtual) {
            foreach ($palavrasOrcamento as $p) {
                if (strpos($mensagem, $p) !== false) {
                    $resposta = "Certo! Já estou chamando alguém da equipe para te passar o orçamento.";
                    $novaEtapa = 'orcamento';
                    break;
                }
            }
        }

        // Resposta via IA se nenhuma palavra-chave foi detectada
        if ($novaEtapa === $etapaAtual) {
            if (in_array($etapaAtual, ['fim'])) {
                return $this->respond(['ignorado' => 'sessao finalizada'], 200);
            }

            $openai = new OpenrouterModel();
            $mensagens = [
                ['role' => 'system', 'content' => $config['prompt_base']],
                ['role' => 'user', 'content' => $mensagem]
            ];

            sleep((int) $config['tempo_resposta']);
            $resposta = $openai->enviarMensagem($mensagens);
        }

        $sessaoModel->atualizarEtapa($numero, $novaEtapa);
        $this->enviarParaWhatsapp($numero, $resposta);

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
