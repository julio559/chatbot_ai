<?php

namespace App\Controllers;

use App\Models\ConfigIaModel;
use CodeIgniter\Controller;

class Etapas extends Controller
{
    protected ?int $usuarioId   = null;
    protected ?int $assinanteId = null;

    public function __construct()
    {
        $this->usuarioId   = (int) (session()->get('usuario_id')   ?? 0) ?: null;
        // fallback 1 até o login estar ativo
        $this->assinanteId = (int) (session()->get('assinante_id') ?? 0) ?: 1;
    }

    public function index()
    {
        $model  = new ConfigIaModel();
        $etapas = $model->where('assinante_id', $this->assinanteId)
                        ->orderBy('ordem', 'ASC')
                        ->orderBy('id', 'ASC')
                        ->findAll();

        return view('etapas', [
            'etapas'     => $etapas,
            'validation' => \Config\Services::validation(),
        ]);
    }

    public function save()
    {
        $model = new ConfigIaModel();
        $db    = \Config\Database::connect();

        $id = (int) ($this->request->getPost('id') ?? 0);

        $rules = [
            'etapa_atual'    => 'required|min_length[2]|max_length[50]',
            'tempo_resposta' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[60]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->to('/etapas')->with('errors', $this->validator->getErrors())->withInput();
        }

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
            // EDITAR
            $etapaAntiga = $model->where('assinante_id', $this->assinanteId)->find($id);
            if (! $etapaAntiga) {
                return redirect()->to('/etapas')->with('errors', ['notfound' => 'Etapa não encontrada.']);
            }

            $nomeAntigo = trim((string) ($etapaAntiga['etapa_atual'] ?? ''));

            // Garante unicidade por assinante (nome da etapa)
            $dupe = $model->where('assinante_id', $this->assinanteId)
                          ->where('etapa_atual', $novoNomeEtapa)
                          ->where('id !=', $id)
                          ->first();
            if ($dupe) {
                return redirect()->to('/etapas')->with('errors', ['duplicada' => 'Já existe uma etapa com esse nome.'])->withInput();
            }

            $db->transBegin();

            $model->update($id, $data);

            // Se o nome mudou, move as sessões SOMENTE dos usuários desse assinante
            if ($nomeAntigo !== '' && $novoNomeEtapa !== '' && $nomeAntigo !== $novoNomeEtapa) {
                // Usamos SQL direto para garantir WHERE com JOIN
                $sql = "UPDATE sessoes s
                        JOIN usuarios u ON u.id = s.usuario_id
                        SET s.etapa = ?
                        WHERE s.etapa = ? AND u.assinante_id = ?";
                $db->query($sql, [$novoNomeEtapa, $nomeAntigo, $this->assinanteId]);
            }

            if ($db->transStatus() === false) {
                $db->transRollback();
                return redirect()->to('/etapas')->with('errors', ['db' => 'Falha ao salvar etapa.'])->withInput();
            }
            $db->transCommit();

            return redirect()->to('/etapas')->with('msg', 'Etapa atualizada com sucesso!');
        } else {
            // CRIAR
            // Garante unicidade por assinante
            $dupe = $model->where('assinante_id', $this->assinanteId)
                          ->where('etapa_atual', $novoNomeEtapa)
                          ->first();
            if ($dupe) {
                return redirect()->to('/etapas')->with('errors', ['duplicada' => 'Já existe uma etapa com esse nome.'])->withInput();
            }

            // ordem = max + 1 por assinante
            $max = $model->where('assinante_id', $this->assinanteId)
                         ->selectMax('ordem')->first();
            $data['ordem'] = (int) ($max['ordem'] ?? 0) + 1;

            $model->insert($data);
            return redirect()->to('/etapas')->with('msg', 'Etapa criada com sucesso!');
        }
    }

    public function delete($id)
    {
        $model = new ConfigIaModel();

        // garante escopo por assinante
        $row = $model->where('assinante_id', $this->assinanteId)->find((int)$id);
        if (! $row) {
            return redirect()->to('/etapas')->with('errors', ['notfound' => 'Etapa não encontrada.']);
        }

        $model->delete((int)$id);
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

        // Trava aos IDs do assinante corrente
        $validos = $model->where('assinante_id', $this->assinanteId)
                         ->whereIn('id', array_map('intval', $ids))
                         ->select('id')
                         ->findColumn('id') ?? [];

        $ordem = 0;
        foreach ($ids as $id) {
            $iid = (int)$id;
            if (in_array($iid, $validos, true)) {
                $model->update($iid, ['ordem' => $ordem++]);
            }
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

        // escopo por assinante
        $atual = $model->where('assinante_id', $this->assinanteId)->find($id);
        if (!$atual) {
            return redirect()->to('/etapas')->with('errors', ['notfound' => 'Etapa não encontrada.']);
        }

        $ordemAtual = (int)$atual['ordem'];

        if ($dir === 'cima') {
            $vizinho = $model->where('assinante_id', $this->assinanteId)
                             ->where('ordem <', $ordemAtual)
                             ->orderBy('ordem', 'DESC')
                             ->first();
        } else {
            $vizinho = $model->where('assinante_id', $this->assinanteId)
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
