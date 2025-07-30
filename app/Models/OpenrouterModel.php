<?php

namespace App\Models;

use CodeIgniter\Model;

class OpenrouterModel extends Model
{
    private $apiKey;

    public function __construct()
    {
        parent::__construct();
        $this->apiKey = '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';
    }

public function enviarMensagem(array $mensagens, string $modelo = 'mistralai/mixtral-8x7b-instruct')


{
    if (empty($this->apiKey)) {
        return 'Chave da OpenRouter não encontrada.';
    }

    $url = 'https://openrouter.ai/api/v1/chat/completions';

    $headers = [
        'Authorization: Bearer ' . $this->apiKey,
        'Content-Type: application/json',
        'HTTP-Referer: https://seusite.com.br',
        'X-Title: atomazAI'
    ];

    $data = [
        'model' => $modelo,
        'messages' => $mensagens
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);

    if (isset($json['error'])) {
        return 'Erro da IA: ' . $json['error']['message'];
    }

    return $json['choices'][0]['message']['content'] ?? 'Erro: resposta inesperada da IA.';
}
}
