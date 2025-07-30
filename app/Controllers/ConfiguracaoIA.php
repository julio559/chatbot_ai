<?php

namespace App\Controllers;

use App\Models\ConfigIaModel;
use CodeIgniter\Controller;

class ConfiguracaoIA extends Controller
{
    public function index()
    {
        $model = new ConfigIaModel();

        // Busca apenas a primeira configuração (ajuste para múltiplos assinantes depois)
        $dados['config'] = $model->first();

        return view('configuracaoia', $dados);
    }

    public function salvar()
    {
        $model = new ConfigIaModel();

        $data = [
            'assinante_id' => 1, // futuramente será dinâmico
            'tempo_resposta' => $this->request->getPost('tempo_resposta'),
            'prompt_base' => $this->request->getPost('prompt_base'),
            'modo_formal' => $this->request->getPost('modo_formal') ? 1 : 0,
            'permite_respostas_longas' => $this->request->getPost('permite_respostas_longas') ? 1 : 0,
            'permite_redirecionamento' => $this->request->getPost('permite_redirecionamento') ? 1 : 0
        ];

        $configExistente = $model->where('assinante_id', 1)->first();

        if ($configExistente) {
            $model->update($configExistente['id'], $data);
        } else {
            $model->insert($data);
        }

        return redirect()->to('/configuracaoia');
    }
}
