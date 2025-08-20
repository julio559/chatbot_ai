<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class Tarefas extends ResourceController
{
    protected ?int $usuarioId = null;
    protected $format = 'json';

    public function __construct()
    {
        // Se não houver sessão, define fallback para testes
        $this->usuarioId = (int)(session()->get('usuario_id') ?? 0) ?: 1;
    }

    /* ==================== VIEW ==================== */
    public function index()
    {
        return view('tarefas'); // app/Views/tarefas.php
    }

    /* ==================== API ==================== */

    /** GET /tarefas/listar?q=&status=&data_de=&data_ate= */
    public function listar()
    {
        if (!$this->usuarioId) {
            return $this->failUnauthorized('Não autenticado');
        }

        $q       = trim((string)$this->request->getGet('q'));
        $status  = trim((string)$this->request->getGet('status')) ?: 'pendente'; // pendente|concluida|todas
        $dataDe  = trim((string)$this->request->getGet('data_de'));
        $dataAte = trim((string)$this->request->getGet('data_ate'));

        $db = \Config\Database::connect();
        $b  = $db->table('tarefas')->where('usuario_id', $this->usuarioId);

        if ($status !== 'todas') {
            $b->where('status', $status === 'concluida' ? 'concluida' : 'pendente');
        }

        if ($q !== '') {
            $b->groupStart()
                ->like('titulo', $q)
                ->orLike('descricao', $q)
                ->orLike('lead_numero', preg_replace('/\D+/', '', $q))
              ->groupEnd();
        }

        if ($dataDe !== '') {
            $b->where('DATE(data_hora) >=', $dataDe);
        }
        if ($dataAte !== '') {
            $b->where('DATE(data_hora) <=', $dataAte);
        }

        // Ordem: manual (ordem ASC), secundário por data
        $tarefas = $b->orderBy('ordem', 'ASC')
                     ->orderBy('ISNULL(data_hora)', '', false) // NULLs por último (MySQL/MariaDB)
                     ->orderBy('data_hora', 'ASC')
                     ->get()->getResultArray();

        return $this->respond(['ok' => true, 'tarefas' => $tarefas]);
    }

    /** POST /tarefas/salvar  (create/update) */
    public function salvar()
    {
        if (!$this->usuarioId) {
            return $this->failUnauthorized('Não autenticado');
        }

        $id        = (int)($this->request->getPost('id') ?? 0);
        $titulo    = trim((string)$this->request->getPost('titulo'));
        $descricao = trim((string)$this->request->getPost('descricao'));
        $data      = trim((string)$this->request->getPost('data'));
        $hora      = trim((string)$this->request->getPost('hora'));
        $prioridade= (int)($this->request->getPost('prioridade') ?? 2);
        $lead      = $this->normalizePhone((string)$this->request->getPost('lead_numero'));
        $lembrete  = $this->parseIntOrNull($this->request->getPost('lembrete_minutos'));

        if ($titulo === '') {
            return $this->failValidationErrors('Título é obrigatório.');
        }
        if (!in_array($prioridade, [1,2,3], true)) $prioridade = 2;

        $dataHora = $this->mergeDateTime($data, $hora); // string "Y-m-d H:i:s" ou null
        $db = \Config\Database::connect();

        if ($id > 0) {
            // UPDATE (somente do usuário)
            $row = $db->table('tarefas')->where('id', $id)->where('usuario_id', $this->usuarioId)->get()->getFirstRow('array');
            if (!$row) return $this->failNotFound('Tarefa não encontrada');

            $db->table('tarefas')->where('id', $id)->update([
                'titulo'            => $titulo,
                'descricao'         => $descricao ?: null,
                'data_hora'         => $dataHora,
                'prioridade'        => $prioridade,
                'lead_numero'       => $lead ?: null,
                'lembrete_minutos'  => $lembrete,
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);
            return $this->respond(['ok' => true, 'id' => $id]);
        }

        // INSERT
        // próxima ordem para o usuário
        $max = $db->table('tarefas')->where('usuario_id', $this->usuarioId)->selectMax('ordem')->get()->getFirstRow('array');
        $ordem = (int)($max['ordem'] ?? 0) + 1;

        $db->table('tarefas')->insert([
            'usuario_id'       => $this->usuarioId,
            'titulo'           => $titulo,
            'descricao'        => $descricao ?: null,
            'data_hora'        => $dataHora,
            'prioridade'       => $prioridade,
            'lead_numero'      => $lead ?: null,
            'lembrete_minutos' => $lembrete,
            'status'           => 'pendente',
            'ordem'            => $ordem,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        $newId = (int)$db->insertID();

        return $this->respond(['ok' => true, 'id' => $newId]);
    }

    /** POST /tarefas/concluir (id, done=1|0) */
    public function concluir()
    {
        if (!$this->usuarioId) {
            return $this->failUnauthorized('Não autenticado');
        }
        $id   = (int)$this->request->getPost('id');
        $done = (string)$this->request->getPost('done') === '1';

        if ($id <= 0) return $this->failValidationErrors('ID inválido.');

        $db  = \Config\Database::connect();
        $row = $db->table('tarefas')->where('id', $id)->where('usuario_id', $this->usuarioId)->get()->getFirstRow('array');
        if (!$row) return $this->failNotFound('Tarefa não encontrada');

        $db->table('tarefas')->where('id', $id)->update([
            'status'     => $done ? 'concluida' : 'pendente',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->respond(['ok' => true]);
    }

    /** POST /tarefas/excluir (id) */
    public function excluir()
    {
        if (!$this->usuarioId) {
            return $this->failUnauthorized('Não autenticado');
        }
        $id = (int)$this->request->getPost('id');
        if ($id <= 0) return $this->failValidationErrors('ID inválido.');

        $db  = \Config\Database::connect();
        $row = $db->table('tarefas')->where('id', $id)->where('usuario_id', $this->usuarioId)->get()->getFirstRow('array');
        if (!$row) return $this->failNotFound('Tarefa não encontrada');

        $db->table('tarefas')->where('id', $id)->delete();
        return $this->respond(['ok' => true]);
    }

    /** POST /tarefas/ordenar (ids[]=...) */
    public function ordenar()
    {
        if (!$this->usuarioId) {
            return $this->failUnauthorized('Não autenticado');
        }
        $ids = $this->request->getPost('ids');
        if (!is_array($ids) || empty($ids)) {
            return $this->failValidationErrors('Lista de IDs vazia.');
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $db = \Config\Database::connect();

        $db->transStart();
        $ordem = 1;
        foreach ($ids as $id) {
            $db->table('tarefas')
               ->where('id', $id)
               ->where('usuario_id', $this->usuarioId)
               ->update(['ordem' => $ordem++, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->failServerError('Falha ao salvar ordem.');
        }
        return $this->respond(['ok' => true]);
    }

    /* ==================== HELPERS ==================== */

    private function normalizePhone(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';
        return preg_replace('/\D+/', '', $raw) ?? '';
    }

    private function parseIntOrNull($v): ?int
    {
        if ($v === '' || $v === null) return null;
        if (is_numeric($v)) return (int)$v;
        return null;
    }

    /** Junta data (Y-m-d) e hora (H:i) em "Y-m-d H:i:s" ou retorna null */
    private function mergeDateTime(?string $data, ?string $hora): ?string
    {
        $data = trim((string)$data);
        $hora = trim((string)$hora);
        if ($data === '' && $hora === '') return null;
        if ($data === '') {
            // sem data não faz sentido salvar só a hora; retorna null
            return null;
        }
        $h = $hora !== '' ? $hora . ':00' : '00:00:00';
        $ts = strtotime("{$data} {$h}");
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
