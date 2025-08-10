<?php

namespace App\Controllers;

use App\Models\NotificacaoNumeroModel;
use App\Models\NotificacaoRegraModel;
use App\Models\ConfigIaModel;
use CodeIgniter\Controller;

class Notificacoes extends Controller
{
   public function index()
    {
        // Carrega etapas ativas do assinante (ajuste o ID se precisar)
        $configIa = new ConfigIaModel();
        $etapas = $configIa->where('assinante_id', 1)
            ->select('etapa_atual')
            ->groupBy('etapa_atual')
            ->orderBy('etapa_atual', 'ASC')
            ->findColumn('etapa_atual') ?? [];

        return view('notificacoes', [
            'etapas' => $etapas, // <— PASSA PRA VIEW
        ]);
    }

    // Lista números (JSON)
    public function list()
    {
        $model = new NotificacaoNumeroModel();
        $q     = trim((string) $this->request->getGet('q'));
        $itens = $model->orderBy('ativo', 'DESC')->orderBy('id', 'DESC')->findAll();

        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $itens = array_values(array_filter($itens, function ($r) use ($qLower) {
                return str_contains(mb_strtolower($r['numero']), $qLower)
                    || str_contains(mb_strtolower($r['descricao'] ?? ''), $qLower);
            }));
        }

        return $this->response->setJSON(['items' => $itens]);
    }

    // Salva número (create/update)
    public function save()
    {
        $model = new NotificacaoNumeroModel();
        $id    = (int) ($this->request->getPost('id') ?? 0);

        $numero    = preg_replace('/\D+/', '', (string) $this->request->getPost('numero')); // só dígitos
        $descricao = trim((string) $this->request->getPost('descricao'));
        $ativo     = (int) ($this->request->getPost('ativo') ? 1 : 0);

        if ($numero === '' || strlen($numero) < 8) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'msg' => 'Número inválido.']);
        }

        $data = [
            'numero'    => $numero,
            'descricao' => $descricao ?: null,
            'ativo'     => $ativo,
        ];

        if ($id > 0) {
            $model->update($id, $data);
        } else {
            $model->insert($data);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    public function delete($id)
    {
        $model = new NotificacaoNumeroModel();
        $one   = $model->find((int) $id);
        if (!$one) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'msg' => 'Registro não encontrado.']);
        }
        $model->delete((int) $id);
        return $this->response->setJSON(['ok' => true]);
    }

    // -------- Regras (opcional) --------
    public function rules()
    {
        $m = new NotificacaoRegraModel();
        $rs = $m->orderBy('etapa', 'ASC')->findAll();
        return $this->response->setJSON(['rules' => $rs]);
    }

    public function saveRule()
    {
        $m = new NotificacaoRegraModel();

        $etapa   = trim((string) $this->request->getPost('etapa'));
        $msgTmpl = trim((string) $this->request->getPost('mensagem_template'));
        $ativo   = (int) ($this->request->getPost('ativo') ? 1 : 0);

        if ($etapa === '') {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'msg' => 'Etapa obrigatória.']);
        }

        $exist = $m->where('etapa', $etapa)->first();
        $data  = ['etapa' => $etapa, 'mensagem_template' => $msgTmpl ?: null, 'ativo' => $ativo];

        if ($exist) $m->update($exist['id'], $data);
        else        $m->insert($data);

        return $this->response->setJSON(['ok' => true]);
    }
}
