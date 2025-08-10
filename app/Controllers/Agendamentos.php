<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class Agendamentos extends Controller
{
    protected int $assinanteId = 1; // ajuste se tiver multi-assinante

    public function index()
    {
        return view('agendamentos'); // carrega a view abaixo
    }

    // Lista em JSON, com filtro de status e busca por nome/telefone
    public function list()
{
    $db     = \Config\Database::connect();
    $status = trim((string) $this->request->getGet('status'));
    $q      = trim((string) $this->request->getGet('q'));

    $builder = $db->table('mensagens_agendadas ma')
        ->select('ma.*, s.etapa, p.nome as paciente_nome')
        ->join('sessoes s', 's.numero = ma.numero', 'left')
        ->join('pacientes p', 'p.telefone = ma.numero', 'left')
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

    // Atualiza mensagem, data, hora e status
    public function update($id)
    {
        $id = (int) $id;

        $mensagem = trim((string)$this->request->getPost('mensagem'));
        $data     = trim((string)$this->request->getPost('data'));
        $hora     = trim((string)$this->request->getPost('hora'));
        $status   = trim((string)$this->request->getPost('status'));

        if ($mensagem === '' || $data === '' || $hora === '') {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Preencha mensagem, data e hora.'])->setStatusCode(400);
        }
        if (!in_array($status, ['pendente','enviado','cancelado'])) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Status inválido.'])->setStatusCode(400);
        }

        $enviarEm = date('Y-m-d H:i:s', strtotime("$data $hora"));

        $db   = \Config\Database::connect();
        $row  = $db->table('mensagens_agendadas')->where('id', $id)->get()->getFirstRow('array');
        if (!$row) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Agendamento não encontrado.'])->setStatusCode(404);
        }

        $payload = [
            'mensagem'  => $mensagem,
            'enviar_em' => $enviarEm,
            'status'    => $status,
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

    // Exclui o agendamento (remove da base)
    public function delete($id)
    {
        $id = (int) $id;
        $db = \Config\Database::connect();
        $row = $db->table('mensagens_agendadas')->where('id', $id)->get()->getFirstRow('array');
        if (!$row) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Agendamento não encontrado.'])->setStatusCode(404);
        }

        $db->table('mensagens_agendadas')->where('id', $id)->delete();

        return $this->response->setJSON(['ok' => true]);
    }
}
