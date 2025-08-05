<?php

namespace App\Controllers;

use App\Models\SessaoModel;
use App\Models\PacienteModel;
use App\Models\ConfigIaModel;
use CodeIgniter\Controller;

class Kanban extends Controller
{
    public function index()
    {
        $sessaoModel = new SessaoModel();
        $configIaModel = new ConfigIaModel();

        // Etapas disponíveis na tabela config_ia para o assinante 1
        $configuracoes = $configIaModel->where('assinante_id', 1)->findAll();

        $etapas = [];
        foreach ($configuracoes as $config) {
            $chave = $config['etapa_atual'];
            $titulo = ucfirst(str_replace('_', ' ', $chave));
            $etapas[$chave] = $titulo;
        }

        // Preenche as colunas com leads de cada etapa
        $sessaoModel = new SessaoModel();
        $dados = [
            'etapas' => $etapas,
            'colunas' => []
        ];

        foreach ($etapas as $key => $titulo) {
            $dados['colunas'][] = [
                'etapa' => $key,
                'titulo' => $titulo,
                'clientes' => $sessaoModel->getLeadsPorEtapa($key)
            ];
        }

        return view('kanban', $dados);
    }

    public function atualizarEtapa()
    {
        $numero = $this->request->getPost('numero');
        $novaEtapa = $this->request->getPost('etapa');

        if (!$numero || !$novaEtapa) {
            return $this->response->setJSON(['status' => 'erro', 'message' => 'Dados inválidos']);
        }

        $sessaoModel = new SessaoModel();
        $sessaoModel->where('numero', $numero)->set(['etapa' => $novaEtapa])->update();

        return $this->response->setJSON(['status' => 'ok']);
    }
}
