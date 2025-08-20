<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class Agendamentos extends Controller
{
    protected ?int $usuarioId   = null;
    protected ?int $assinanteId = null;

    public function __construct()
    {
        $this->usuarioId   = (int) (session()->get('usuario_id')   ?? 0) ?: null;
        $this->assinanteId = (int) (session()->get('assinante_id') ?? 0) ?: null;
    }

    public function index()
    {
        // se quiser travar a view quando não logado, descomente:
        // if (!$this->usuarioId) return redirect()->to('/login');
        return view('agendamentos');
    }

    // Lista em JSON, com filtro de status e busca por nome/telefone (escopo do usuário logado)
    public function list()
    {
        if (!$this->usuarioId) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'msg' => 'Não autenticado']);
        }

        $db     = \Config\Database::connect();
        $status = trim((string) $this->request->getGet('status'));
        $q      = trim((string) $this->request->getGet('q'));

        $builder = $db->table('mensagens_agendadas ma')
            ->select('ma.*, s.etapa, p.nome as paciente_nome')
            ->join('sessoes s', 's.numero = ma.numero', 'left')
            ->join('pacientes p', 'p.telefone = ma.numero', 'left')
            ->where('ma.usuario_id', $this->usuarioId) // <--- escopo do dono
            // pendentes primeiro, depois enviados, depois cancelados:
            ->orderBy("FIELD(ma.status, 'pendente','enviado','cancelado')", '', false)
            ->orderBy('ma.enviar_em', 'ASC');

        if ($status !== '' && in_array($status, ['pendente','enviado','cancelado'], true)) {
            $builder->where('ma.status', $status);
        }

        if ($q !== '') {
            $builder->groupStart()
                ->like('ma.numero', $q)
                ->orLike('p.nome', $q)
                ->orLike('ma.mensagem', $q)
            ->groupEnd();
        }

        $items = $builder->get()->getResultArray();
        return $this->response->setJSON(['items' => $items]);
    }

    // Atualiza mensagem, data, hora e status (somente se o agendamento for do usuário logado)
    public function update($id)
    {
        if (!$this->usuarioId) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'msg' => 'Não autenticado']);
        }

        $id = (int) $id;

        $mensagem = trim((string)$this->request->getPost('mensagem'));
        $data     = trim((string)$this->request->getPost('data'));
        $hora     = trim((string)$this->request->getPost('hora'));
        $status   = trim((string)$this->request->getPost('status'));

        if ($mensagem === '' || $data === '' || $hora === '') {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Preencha mensagem, data e hora.'])->setStatusCode(400);
        }
        if (!in_array($status, ['pendente','enviado','cancelado'], true)) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Status inválido.'])->setStatusCode(400);
        }

        $enviarEm = date('Y-m-d H:i:s', strtotime("$data $hora"));

        $db  = \Config\Database::connect();
        $row = $db->table('mensagens_agendadas')
            ->where('id', $id)
            ->where('usuario_id', $this->usuarioId) // <--- garante permissão
            ->get()->getFirstRow('array');

        if (!$row) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Agendamento não encontrado ou sem permissão.'])->setStatusCode(404);
        }

        $payload = [
            'mensagem'    => $mensagem,
            'enviar_em'   => $enviarEm,
            'status'      => $status,
            'usuario_id'  => $this->usuarioId, // reforça dono
        ];

        if ($status === 'enviado' && empty($row['enviado_em'])) {
            $payload['enviado_em'] = date('Y-m-d H:i:s');
        }
        if ($status !== 'enviado') {
            $payload['enviado_em'] = null;
        }

        $db->table('mensagens_agendadas')->where('id', $id)->update($payload);

        return $this->response->setJSON(['ok' => true]);
    }

    // Exclui o agendamento (somente do usuário logado)
    public function delete($id)
    {
        if (!$this->usuarioId) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'msg' => 'Não autenticado']);
        }

        $id = (int) $id;
        $db = \Config\Database::connect();

        $row = $db->table('mensagens_agendadas')
            ->where('id', $id)
            ->where('usuario_id', $this->usuarioId) // <--- garante permissão
            ->get()->getFirstRow('array');

        if (!$row) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Agendamento não encontrado ou sem permissão.'])->setStatusCode(404);
        }

        $db->table('mensagens_agendadas')->where('id', $id)->delete();

        return $this->response->setJSON(['ok' => true]);
    }
}
