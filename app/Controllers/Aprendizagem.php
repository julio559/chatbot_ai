<?php
namespace App\Controllers;

use App\Models\AprendizagemModel;
use CodeIgniter\Controller;

class Aprendizagem extends Controller
{
    protected ?int $usuarioId   = null;
    protected ?int $assinanteId = null;

    public function __construct()
    {
        $this->usuarioId   = (int) (session()->get('usuario_id')   ?? 0) ?: null;
        // fallback enquanto login não estiver ativo
        $this->assinanteId = (int) (session()->get('assinante_id') ?? 0) ?: 1;
    }

    /** Tela principal (lista + modal) */
    public function index()
    {
        $m = new AprendizagemModel();
        $itens = $m->where('assinante_id', $this->assinanteId)
                   ->orderBy('ativo', 'DESC')
                   ->orderBy('id', 'DESC')
                   ->findAll();

        return view('aprendizagem', ['itens' => $itens]);
    }

    /** GET /aprendizagem/listar  → JSON para o front (busca opcional) */
    public function listar()
    {
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        $m = new AprendizagemModel();

        $builder = $m->where('assinante_id', $this->assinanteId);
        if ($q !== '') {
            $builder->groupStart()
                    ->like('titulo', $q)
                    ->orLike('conteudo', $q)
                    ->orLike('tags', $q)
                    ->groupEnd();
        }

        $rows = $builder->orderBy('ativo', 'DESC')->orderBy('id', 'DESC')->findAll();

        return $this->response->setJSON([
            'ok' => true,
            'data' => $rows
        ]);
    }

    /** GET /aprendizagem/obter/:id  → um item (para editar) */
    public function obter($id = null)
    {
        $id = (int) $id;
        if (!$id) return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'msg' => 'id inválido']);

        $m = new AprendizagemModel();
        $row = $m->where('assinante_id', $this->assinanteId)->find($id);
        if (!$row) return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'msg' => 'não encontrado']);

        return $this->response->setJSON(['ok' => true, 'data' => $row]);
    }

    /**
     * POST /aprendizagem/salvar
     * Campos: id (opcional p/ update), titulo*, conteudo*, tags, ativo (1/0)
     */
    public function salvar()
    {
        $id       = (int) ($this->request->getPost('id') ?? 0);
        $titulo   = trim((string) $this->request->getPost('titulo'));
        $conteudo = trim((string) $this->request->getPost('conteudo'));
        $tags     = trim((string) $this->request->getPost('tags'));
        $ativo    = $this->request->getPost('ativo') ? 1 : 0;

        if ($titulo === '' || $conteudo === '') {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'msg' => 'Título e conteúdo são obrigatórios.']);
        }

        $m = new AprendizagemModel();
        $data = [
            'usuario_id'   => $this->usuarioId,
            'assinante_id' => $this->assinanteId,
            'titulo'       => $titulo,
            'conteudo'     => $conteudo,
            'tags'         => $tags,
            'ativo'        => $ativo,
        ];

        if ($id) {
            // update (reforça escopo do assinante)
            $row = $m->where('assinante_id', $this->assinanteId)->find($id);
            if (!$row) return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'msg' => 'Registro não encontrado.']);
            $m->update($id, $data);
        } else {
            // create
            $m->insert($data);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    /** POST /aprendizagem/excluir  → apaga um item por id */
    public function excluir()
    {
        $id = (int) ($this->request->getPost('id') ?? 0);
        if (!$id) return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'msg' => 'id obrigatório']);

        $m = new AprendizagemModel();
        $row = $m->where('assinante_id', $this->assinanteId)->find($id);
        if (!$row) return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'msg' => 'Registro não encontrado.']);

        $m->delete($id);
        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * GET /aprendizagem/base
     * Retorna somente os textos ATIVOS deste assinante (para injetar no prompt da IA).
     * Aceita ?limit= e ?tags=coma,separadas para filtrar.
     */
    public function base()
    {
        $limit = max(0, (int) ($this->request->getGet('limit') ?? 0));
        $tags  = trim((string) ($this->request->getGet('tags') ?? ''));

        $m = new AprendizagemModel();
        $builder = $m->select('titulo, conteudo, tags')
                     ->where('assinante_id', $this->assinanteId)
                     ->where('ativo', 1);

        if ($tags !== '') {
            $ts = array_filter(array_map('trim', explode(',', $tags)));
            if (!empty($ts)) {
                $builder->groupStart();
                foreach ($ts as $i => $t) {
                    if ($i === 0) $builder->like('tags', $t);
                    else          $builder->orLike('tags', $t);
                }
                $builder->groupEnd();
            }
        }

        $builder->orderBy('id', 'DESC');
        $rows = $limit > 0 ? $builder->findAll($limit) : $builder->findAll();

        return $this->response->setJSON(['ok' => true, 'data' => $rows]);
    }
}
