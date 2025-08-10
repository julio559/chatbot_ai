<?php

namespace App\Controllers;

use App\Models\PacienteModel;
use App\Models\SessaoModel;
use CodeIgniter\RESTful\ResourceController;

class Chat extends ResourceController
{
    // Renderiza a view única com a lista + chat (a view faz chamadas AJAX abaixo)
    public function index()
    {
        return view('chat'); // app/Views/chat.php
    }

    // Lista de contatos (pacientes) para a coluna da esquerda
    public function contacts()
    {
        $pacienteModel = new PacienteModel();

        $pacientes = $pacienteModel
            ->orderBy('ultimo_contato', 'DESC')
            ->select('id, nome, telefone, ultimo_contato')
            ->findAll();

        return $this->respond($pacientes);
    }

    // Mensagens (histórico) de um número
    public function messages($numero = null)
    {
        if (!$numero) {
            return $this->fail('numero obrigatório', 400);
        }

        $sessaoModel = new SessaoModel();
        $sessao = $sessaoModel->find($numero); // PK é 'numero' na tabela sessoes

        $historico = [];
        if ($sessao && !empty($sessao['historico'])) {
            $historico = json_decode($sessao['historico'], true);
        }

        return $this->respond([
            'numero' => $numero,
            'historico' => is_array($historico) ? $historico : [],
            'etapa' => $sessao['etapa'] ?? 'entrada'
        ]);
    }

    // Enviar mensagem (e salva no histórico). A view chama este endpoint via POST.
    public function send()
    {
        $numero   = preg_replace('/[^0-9]/', '', $this->request->getPost('numero') ?? '');
        $mensagem = trim($this->request->getPost('mensagem') ?? '');

        if (!$numero || $mensagem === '') {
            return $this->fail('numero e mensagem são obrigatórios', 400);
        }

        // Atualiza histórico na sessão
        $sessaoModel = new SessaoModel();
        $sessao = $sessaoModel->getOuCriarSessao($numero);

        $historico = [];
        if (!empty($sessao['historico'])) {
            $tmp = json_decode($sessao['historico'], true);
            $historico = is_array($tmp) ? $tmp : [];
        }
        // Adiciona a nova mensagem como assistant (enviada por você)
        $historico[] = ['role' => 'assistant', 'content' => $mensagem];

        $sessaoModel->where('numero', $numero)->set([
            'ultima_resposta_ia' => $mensagem,
            'historico' => json_encode($historico, JSON_UNESCAPED_UNICODE)
        ])->update();

        // Envia via UltraMsg (coloque as credenciais no .env)
        $ok = $this->enviarParaWhatsapp($numero, $mensagem);

        if (!$ok['success']) {
            return $this->fail("Falha ao enviar: {$ok['detail']}", 500);
        }

        return $this->respond(['status' => 'enviado']);
    }

    // --- Utilitários ---

    private function enviarParaWhatsapp(string $numero, string $mensagem): array
    {
        $instanceId = env('ULTRA_INSTANCE_ID', 'instance136009');
        $token      = env('ULTRA_TOKEN', 'rbsu6e74buuzsnjj');
        $url        = "https://api.ultramsg.com/{$instanceId}/messages/chat";

        $data = http_build_query([
            'token' => $token,
            'to'    => $numero,
            'body'  => $mensagem
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_TIMEOUT        => 20,
        ]);

        $resp     = curl_exec($ch);
        $err      = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        log_message('error', "Chat::enviar WA ({$numero}) HTTP {$httpCode} - {$resp}");
        if ($err) {
            log_message('error', "Chat::cURL error - {$err}");
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'detail'  => $err ?: $resp
        ];
    }
}
