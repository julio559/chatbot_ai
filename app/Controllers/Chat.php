<?php

namespace App\Controllers;

use App\Models\OpenrouterModel;

class Chat extends BaseController
{
    public function index()
    {
        $modelo = new OpenrouterModel();

        $mensagens = [
            ["role" => "system", "content" => "Você é um atendente simpático da empresa LimpaTudo. Responda com clareza e cordialidade."],
            ["role" => "user", "content" => "Qual o valor da limpeza pesada?"]
        ];

        $resposta = $modelo->enviarMensagem($mensagens);

        return $this->response->setJSON(['resposta' => $resposta]);
    }
}
    