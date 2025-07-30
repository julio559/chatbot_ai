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
        $paciente = $pacienteModel->where('telefone', $numero)->first();
        if ($paciente) {
            $pacienteModel->update($paciente['id'], ['ultimo_contato' => date('Y-m-d H:i:s')]);
        } else {
            $pacienteModel->insert(['nome' => $nome, 'telefone' => $numero]);
        }

        // Sessão
        $sessaoModel = new SessaoModel();
        $sessao = $sessaoModel->getOuCriarSessao($numero);
        $etapaAtual = $sessao['etapa'];
        $novaEtapa = $etapaAtual;
        $resposta = '';

        // Configuração
        $configModel = new ConfigIaModel();
        $config = $configModel->where('assinante_id', 1)->first() ?? [
            'tempo_resposta' => 5,
            'prompt_base' => "Você é a assistente humana da Dra. Bruna Sathler. Responda como se estivesse no WhatsApp, com gentileza e naturalidade. Use frases curtas, como um humano faria...",
            'modo_formal' => false,
            'permite_respostas_longas' => false,
            'permite_redirecionamento' => false
        ];

        // Se já está em agendamento ou orçamento, não responde
        if (in_array($etapaAtual, ['agendamento', 'orcamento'])) {
            return $this->respond(['status' => 'aguardando equipe'], 200);
        }

        // Palavras-chave
        $palavrasAgendamento = ['agendar', 'consulta', 'marcar', 'horário'];
        $palavrasOrcamento = ['valor', 'preço', 'custo', 'quanto', 'orcamento'];

        foreach ($palavrasAgendamento as $p) {
            if (strpos($mensagem, $p) !== false) {
                $resposta = "Certo! Me dá somente um minutinho.";
                $novaEtapa = 'agendamento';
                break;
            }
        }

        if ($novaEtapa === $etapaAtual) {
            foreach ($palavrasOrcamento as $p) {
                if (strpos($mensagem, $p) !== false) {
                    $resposta = "Certo! Me dá somente um minutinho.";
                    $novaEtapa = 'orcamento';
                    break;
                }
            }
        }

        // IA: continuar conversa se nenhuma palavra-chave
        if ($novaEtapa === $etapaAtual) {
            if ($etapaAtual === 'fim') {
                return $this->respond(['ignorado' => 'sessao finalizada'], 200);
            }

            $openai = new OpenrouterModel();
            $mensagens = [
                ['role' => 'system', 'content' => $config['prompt_base']],
            ];

            if (!empty($sessao['ultima_mensagem_usuario'])) {
                $mensagens[] = ['role' => 'user', 'content' => $sessao['ultima_mensagem_usuario']];
            }
            if (!empty($sessao['ultima_resposta_ia'])) {
                $mensagens[] = ['role' => 'assistant', 'content' => $sessao['ultima_resposta_ia']];
            }

            // Mensagem atual
            $mensagens[] = ['role' => 'user', 'content' => $mensagem];

            sleep((int) $config['tempo_resposta']);
            $resposta = $openai->enviarMensagem($mensagens);
        }

        // Atualizar sessão
        $sessaoModel->where('numero', $numero)->set([
            'etapa' => $novaEtapa,
            'ultima_mensagem_usuario' => $mensagem,
            'ultima_resposta_ia' => $resposta
        ])->update();

        // Envia
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
