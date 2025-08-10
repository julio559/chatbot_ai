<?php

namespace App\Controllers;

use App\Models\ConfigIaModel;
use CodeIgniter\Controller;

class Etapas extends Controller
{
    protected int $assinanteId = 1;

    public function index()
    {
        $model  = new ConfigIaModel();
        $etapas = $model->where('assinante_id', $this->assinanteId)
                        ->orderBy('ordem', 'ASC')
                        ->orderBy('id', 'ASC')
                        ->findAll();

        return view('etapas', [
            'etapas' => $etapas,
            'validation' => \Config\Services::validation()
        ]);
    }

    public function save()
    {
        $model = new ConfigIaModel();
        $db    = \Config\Database::connect();

        $id = (int) ($this->request->getPost('id') ?? 0);

        $rules = [
            'etapa_atual'     => 'required|min_length[2]|max_length[50]',
            'tempo_resposta'  => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[60]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->to('/etapas')->with('errors', $this->validator->getErrors())->withInput();
        }

        // Normaliza nome (tira espaços extras)
        $novoNomeEtapa = trim((string) $this->request->getPost('etapa_atual'));

        $data = [
            'assinante_id'             => $this->assinanteId,
            'etapa_atual'              => $novoNomeEtapa,
            'tempo_resposta'           => (int) $this->request->getPost('tempo_resposta'),
            'prompt_base'              => $this->request->getPost('prompt_base') ?: null,
            'modo_formal'              => $this->request->getPost('modo_formal') ? 1 : 0,
            'permite_respostas_longas' => $this->request->getPost('permite_respostas_longas') ? 1 : 0,
            'permite_redirecionamento' => $this->request->getPost('permite_redirecionamento') ? 1 : 0,
        ];

        if ($id > 0) {
            // EDITAR: se o nome mudou, mover todas as sessões da etapa antiga para a nova
            $etapaAntiga = $model->find($id);
            if (! $etapaAntiga) {
                return redirect()->to('/etapas')->with('errors', ['notfound' => 'Etapa não encontrada.']);
            }

            $nomeAntigo = trim((string) ($etapaAntiga['etapa_atual'] ?? ''));

            // Transação para garantir consistência
            $db->transBegin();

            // Atualiza a etapa no config_ia
            $model->update($id, $data);

            // Se o nome mudou, move as sessões
            if ($nomeAntigo !== '' && $novoNomeEtapa !== '' && $nomeAntigo !== $novoNomeEtapa) {
                // Move todos os leads que estavam na etapa antiga para a nova
                $db->table('sessoes')
                   ->where('etapa', $nomeAntigo)
                   ->update(['etapa' => $novoNomeEtapa]);
            }

            // Finaliza transação
            if ($db->transStatus() === false) {
                $db->transRollback();
                return redirect()->to('/etapas')->with('errors', ['db' => 'Falha ao salvar etapa.'])->withInput();
            }
            $db->transCommit();

            return redirect()->to('/etapas')->with('msg', 'Etapa atualizada com sucesso!');
        } else {
            // CRIAR: define ordem = max + 1
            $max = $model->where('assinante_id', $this->assinanteId)
                         ->selectMax('ordem')->first();
            $proximaOrdem = (int) ($max['ordem'] ?? 0) + 1;
            $data['ordem'] = $proximaOrdem;

            $model->insert($data);
            return redirect()->to('/etapas')->with('msg', 'Etapa criada com sucesso!');
        }
    }

    public function delete($id)
    {
        $model = new ConfigIaModel();
        if (! $model->find($id)) {
            return redirect()->to('/etapas')->with('errors', ['notfound' => 'Etapa não encontrada.']);
        }
        $model->delete($id);
        return redirect()->to('/etapas')->with('msg', 'Etapa excluída com sucesso!');
    }

    // ---- ORDENAR (drag-and-drop) ----
    public function ordenar()
    {
        if ($this->request->getMethod() !== 'post') {
            return $this->response->setStatusCode(405);
        }

        $ids = $this->request->getPost('ids');
        if (!is_array($ids) || empty($ids)) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'lista vazia'])->setStatusCode(400);
        }

        $model = new ConfigIaModel();
        $ordem = 0;
        foreach ($ids as $id) {
            $model->update((int)$id, ['ordem' => $ordem++]);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    // ---- Mover para cima/baixo com swap ----
    public function moverCima($id)
    {
        return $this->swapVizinho((int)$id, 'cima');
    }

    public function moverBaixo($id)
    {
        return $this->swapVizinho((int)$id, 'baixo');
    }

    private function swapVizinho(int $id, string $dir)
    {
        $model = new ConfigIaModel();
        $atual = $model->find($id);
        if (!$atual) return redirect()->to('/etapas')->with('errors', ['notfound' => 'Etapa não encontrada.']);

        $assinante = $this->assinanteId;
        $ordemAtual = (int)$atual['ordem'];

        if ($dir === 'cima') {
            $vizinho = $model->where('assinante_id', $assinante)
                             ->where('ordem <', $ordemAtual)
                             ->orderBy('ordem', 'DESC')
                             ->first();
        } else {
            $vizinho = $model->where('assinante_id', $assinante)
                             ->where('ordem >', $ordemAtual)
                             ->orderBy('ordem', 'ASC')
                             ->first();
        }

        if ($vizinho) {
            $model->update($atual['id'],  ['ordem' => $vizinho['ordem']]);
            $model->update($vizinho['id'], ['ordem' => $ordemAtual]);
        }

        return redirect()->to('/etapas')->with('msg', 'Ordem atualizada!');
    }
}
