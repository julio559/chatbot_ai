<?php
namespace App\Controllers;

use App\Models\NotificacaoNumeroModel;
use App\Models\NotificacaoRegraModel;
use App\Models\ConfigIaModel;
use CodeIgniter\Controller;

class Notificacoes extends Controller
{
    protected ?int $usuarioId   = null;
    protected ?int $assinanteId = null;

    public function __construct()
    {
        $this->usuarioId   = (int) (session()->get('usuario_id')   ?? 0) ?: null;
        $this->assinanteId = (int) (session()->get('assinante_id') ?? 0) ?: 1;
    }

    public function index()
    {
        $configIa = new ConfigIaModel();
        $etapas = $configIa->where('assinante_id', $this->assinanteId)
            ->select('etapa_atual')
            ->groupBy('etapa_atual')
            ->orderBy('etapa_atual', 'ASC')
            ->findColumn('etapa_atual') ?? [];

        return view('notificacoes', ['etapas' => $etapas]);
    }

    public function list()
    {
        $model = new NotificacaoNumeroModel();
        $q     = trim((string) $this->request->getGet('q'));

        $builder = $model->where('assinante_id', $this->assinanteId)
                         ->orderBy('ativo', 'DESC')
                         ->orderBy('id', 'DESC');

        if ($q !== '') {
            $somenteDigitos = preg_replace('/\D+/', '', $q);
            $builder->groupStart()
                        ->like('numero', $somenteDigitos)
                        ->orLike('descricao', $q)
                    ->groupEnd();
        }

        $itens = $builder->findAll();
        return $this->response->setJSON(['items' => $itens]);
    }

    public function save()
    {
        $model = new NotificacaoNumeroModel();
        $id    = (int) ($this->request->getPost('id') ?? 0);

        $numero    = preg_replace('/\D+/', '', (string) $this->request->getPost('numero'));
        $descricao = trim((string) $this->request->getPost('descricao'));
        $ativo     = (int) ($this->request->getPost('ativo') ? 1 : 0);

        if ($numero === '' || strlen($numero) < 8) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'msg' => 'Número inválido.']);
        }

        $dupQuery = $model->where('assinante_id', $this->assinanteId)->where('numero', $numero);
        if ($id > 0) { $dupQuery->where('id !=', $id); }
        $dup = $dupQuery->first();
        if ($dup) {
            return $this->response->setStatusCode(409)->setJSON(['ok' => false, 'msg' => 'Já existe esse número.']);
        }

        $data = [
            'assinante_id' => $this->assinanteId,
            'usuario_id'   => $this->usuarioId,
            'numero'       => $numero,
            'descricao'    => $descricao ?: null,
            'ativo'        => $ativo,
        ];

        if ($id > 0) {
            $row = $model->where('assinante_id', $this->assinanteId)->find($id);
            if (!$row) {
                return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'msg' => 'Registro não encontrado.']);
            }
            $model->update($id, $data);
        } else {
            $model->insert($data);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    public function delete($id)
    {
        $model = new NotificacaoNumeroModel();
        $one   = $model->where('assinante_id', $this->assinanteId)->find((int) $id);
        if (!$one) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'msg' => 'Registro não encontrado.']);
        }
        $model->delete((int) $id);
        return $this->response->setJSON(['ok' => true]);
    }

    public function rules()
    {
        $m  = new NotificacaoRegraModel();
        $rs = $m->where('assinante_id', $this->assinanteId)
                ->orderBy('etapa', 'ASC')
                ->findAll();

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

        $stageExists = (new ConfigIaModel())
            ->where('assinante_id', $this->assinanteId)
            ->where('etapa_atual', $etapa)
            ->first();

        if (!$stageExists) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'msg' => 'Etapa inexistente para este assinante.']);
        }

        $exist = $m->where('assinante_id', $this->assinanteId)
                   ->where('etapa', $etapa)
                   ->first();

        $data  = [
            'assinante_id'      => $this->assinanteId,
            'etapa'             => $etapa,
            'mensagem_template' => $msgTmpl ?: null,
            'ativo'             => $ativo
        ];

        if ($exist) {
            $m->update($exist['id'], $data);
        } else {
            $m->insert($data);
        }

        return $this->response->setJSON(['ok' => true]);
    }
}
