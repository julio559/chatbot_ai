<?php

namespace App\Controllers;

use App\Models\ConfigIaModel; // Model para manipulação de configurações de IA
use CodeIgniter\Controller;

class CriarEtapas extends Controller
{
    // Função para exibir as etapas
    public function index()
    {
        $configIaModel = new ConfigIaModel();
        $etapas = $configIaModel->findAll(); // Pega todas as etapas do banco de dados

        // Envia os dados para a view
        return view('gerenciar_etapas', ['etapas' => $etapas]);
    }

    // Função para criar ou atualizar uma etapa
    public function criarOuAtualizarEtapa()
    {
        // Recebe os dados do formulário via POST
        $etapa_base = $this->request->getPost('nome_etapa'); // Nome da etapa
        $prompt_base = $this->request->getPost('prompt');
        $tempo_resposta = $this->request->getPost('tempo_resposta');
        $modo_formal = $this->request->getPost('modo_formal') ? 1 : 0;
        $permite_respostas_longas = $this->request->getPost('permite_respostas_longas') ? 1 : 0;
        $permite_redirecionamento = $this->request->getPost('permite_redirecionamento') ? 1 : 0;

        // Inicializa o model para configuração da IA
        $configIaModel = new ConfigIaModel();

        // Verifica se já existe uma etapa com o mesmo nome no banco
        $etapaExistente = $configIaModel->where('etapa_base', $etapa_base)->first();

        // Dados a serem salvos ou atualizados
        $data = [
            'etapa_base' => $etapa_base,
            'prompt_base' => $prompt_base,
            'tempo_resposta' => $tempo_resposta,
            'modo_formal' => $modo_formal,
            'permite_respostas_longas' => $permite_respostas_longas,
            'permite_redirecionamento' => $permite_redirecionamento
        ];

        if ($etapaExistente) {
            // Se a etapa já existir, atualiza a etapa
            $configIaModel->update($etapaExistente['id'], $data);
        } else {
            // Caso contrário, cria uma nova etapa
            $configIaModel->save($data);
        }

        // Retorna uma resposta JSON indicando sucesso
        return $this->response->setJSON(['status' => 'ok']);
    }

    // Função para excluir uma etapa
    public function excluirEtapa()
    {
        // Recebe o nome da etapa que será excluída
        $etapa_base = $this->request->getPost('nome_etapa');

        // Inicializa o model de configuração de IA
        $configIaModel = new ConfigIaModel();

        // Exclui a etapa do banco de dados
        $configIaModel->where('etapa_base', $etapa_base)->delete();

        // Retorna uma resposta JSON indicando sucesso
        return $this->response->setJSON(['status' => 'ok']);
    }
}
