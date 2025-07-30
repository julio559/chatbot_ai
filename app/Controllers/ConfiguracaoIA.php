<?php

namespace App\Controllers;

use App\Models\ConfigIaModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\OpenrouterModel;

class ConfiguracaoIA extends Controller
{
    public function index()
    {
        $model = new ConfigIaModel();
        $dados['config'] = $model->first() ?? [];

        return view('configuracaoia', $dados);
    }

    public function salvar()
    {
        $model = new ConfigIaModel();

        $data = [
            'tempo_resposta' => $this->request->getPost('tempo_resposta'),
            'prompt_base' => $this->request->getPost('prompt_base'),
            'modo_formal' => $this->request->getPost('modo_formal') ? 1 : 0,
            'permite_respostas_longas' => $this->request->getPost('permite_respostas_longas') ? 1 : 0,
            'permite_redirecionamento' => $this->request->getPost('permite_redirecionamento') ? 1 : 0,
            'assinante_id' => 1 // Fixo por enquanto
        ];

        if ($model->first()) {
            $model->update(1, $data);
        } else {
            $model->insert($data);
        }

        return redirect()->to('/configuracaoia')->with('success', 'Configuração salva!');
    }

 public function testar()
{
    $mensagem = $this->request->getPost('mensagem');
    $promptPersonalizado = $this->request->getPost('prompt');

    $model = new ConfigIaModel();
    $config = $model->first();

    $promptFinal = $promptPersonalizado ?: ($config['prompt_base'] ?? 'Você é uma assistente gentil.');

    // Usa o OpenrouterModel
    $ia = new OpenrouterModel();
    $respostaIA = $ia->enviarMensagem([
        ['role' => 'system', 'content' => $promptFinal],
        ['role' => 'user', 'content' => $mensagem],
    ]);

    // Retorna para view mantendo os campos preenchidos
    return view('configuracaoia', [
        'config' => $config,
        'respostaTeste' => $respostaIA,
        'prompt' => $promptPersonalizado,
        'mensagem' => $mensagem
    ]);
}

}
