<?php

namespace App\Models;

use CodeIgniter\Model;

class OpenrouterModel extends Model
{
    private $apiKey;
    private $modeloPadrao = 'anthropic/claude-3-haiku'; // MELHOR modelo para conversa humanizada

    public function __construct()
    {
        parent::__construct();
        $this->apiKey = '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';
    }

public function enviarMensagem(array $mensagens, ?string $modelo = null)
    {
        if (empty($this->apiKey)) {
            return 'Erro: chave da OpenRouter não encontrada.';
        }

        $url = 'https://openrouter.ai/api/v1/chat/completions';

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: https://atomazai.com.br', // personalize para seu domínio
            'X-Title: Atendimento Bruna IA' // nome da aplicação
        ];

        $data = [
            'model' => $modelo ?? $this->modeloPadrao,
            'messages' => $mensagens
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return 'Erro: sem resposta da API.';
        }

        $json = json_decode($response, true);

        if (isset($json['error']['message'])) {
            return 'Erro da IA: ' . $json['error']['message'];
        }

        if (!isset($json['choices'][0]['message']['content'])) {
            return 'Erro: resposta inesperada da IA.';
        }

        return $json['choices'][0]['message']['content'];
    }
}
