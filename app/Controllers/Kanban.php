<?php

namespace App\Controllers;

use App\Models\SessaoModel;
use App\Models\ConfigIaModel;
use CodeIgniter\Controller;

class Kanban extends Controller
{
    protected int $assinanteId = 1;

    public function index()
    {
        $sessaoModel   = new SessaoModel();
        $configIaModel = new ConfigIaModel();

        // Etapas ordenadas
        $configuracoes = $configIaModel->where('assinante_id', $this->assinanteId)
            ->orderBy('ordem', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        $etapas = [];
        foreach ($configuracoes as $config) {
            $chave  = $config['etapa_atual'];
            $titulo = ucfirst(str_replace('_', ' ', $chave));
            $etapas[$chave] = $titulo;
        }

        $colunas = [];
        foreach ($etapas as $key => $titulo) {
            $clientes = $sessaoModel->getLeadsPorEtapa($key);
            $colunas[] = [
                'etapa'    => $key,
                'titulo'   => $titulo,
                'clientes' => $clientes,
                'total'    => is_array($clientes) ? count($clientes) : 0,
            ];
        }

        return view('kanban', [
            'etapas'  => $etapas,
            'colunas' => $colunas
        ]);
    }

    public function atualizarEtapa()
    {
        $numero    = $this->request->getPost('numero');
        $novaEtapa = $this->request->getPost('etapa');

        if (!$numero || !$novaEtapa) {
            return $this->response->setJSON(['status' => 'erro', 'message' => 'Dados inválidos'])->setStatusCode(400);
        }

        $sessaoModel = new SessaoModel();
        $sessaoModel->where('numero', $numero)->set(['etapa' => $novaEtapa])->update();

        return $this->response->setJSON(['status' => 'ok']);
    }

    /* ================= TAGS ================= */

    /** GET /kanban/tags : lista todas as tags do assinante */
    public function tags()
    {
        $db = \Config\Database::connect();
        $tags = $db->table('tags')
            ->where('assinante_id', $this->assinanteId)
            ->orderBy('nome', 'ASC')
            ->get()->getResultArray();

        return $this->response->setJSON(['tags' => $tags]);
    }

    /** POST /kanban/tags : cria uma tag */
    public function criarTag()
    {
        $nome = trim((string) $this->request->getPost('nome'));
        $cor  = trim((string) $this->request->getPost('cor') ?: '#3b82f6');

        if ($nome === '') {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Nome obrigatório'])->setStatusCode(400);
        }

        $db = \Config\Database::connect();
        // evita duplicidade por assinante
        $existe = $db->table('tags')->where([
            'assinante_id' => $this->assinanteId,
            'nome' => $nome
        ])->get()->getFirstRow('array');

        if ($existe) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Tag já existe'])->setStatusCode(409);
        }

        $db->table('tags')->insert([
            'assinante_id' => $this->assinanteId,
            'nome' => $nome,
            'cor'  => $cor,
        ]);

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * GET /kanban/lead-tags/{numero}
     * Retorna: { tags: [...], doLead: [tag_id, ...] }
     */
    public function leadTags($numero)
    {
        $db = \Config\Database::connect();

        $tags = $db->table('tags')
            ->where('assinante_id', $this->assinanteId)
            ->orderBy('nome', 'ASC')
            ->get()->getResultArray();

        $doLeadRows = $db->table('sessao_tags')
            ->select('tag_id')
            ->where('numero', $numero)
            ->get()->getResultArray();

        $doLead = array_map(fn($r) => (int)$r['tag_id'], $doLeadRows);

        return $this->response->setJSON([
            'tags'  => $tags,
            'doLead'=> $doLead
        ]);
    }

    /**
     * POST /kanban/lead-tags/{numero}
     * Body: tags[] (array de IDs)
     */
    public function salvarLeadTags($numero)
    {
        $ids = $this->request->getPost('tags'); // pode vir array de strings
        if (!is_array($ids)) $ids = [];

        $ids = array_values(array_unique(array_map('intval', $ids)));

        $db = \Config\Database::connect();
        $db->transStart();

        // apaga atuais
        $db->table('sessao_tags')->where('numero', $numero)->delete();

        // insere novos
        foreach ($ids as $tagId) {
            $db->table('sessao_tags')->insert([
                'numero' => $numero,
                'tag_id' => $tagId
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Falha ao salvar'])->setStatusCode(500);
        }

        return $this->response->setJSON(['ok' => true]);
    }
    // Detalhes do lead (dados + tags + histórico + notas)
public function leadDetalhes($numero)
{
    $db = \Config\Database::connect();

    // dados da sessão
    $sessao = $db->table('sessoes')->where('numero', $numero)->get()->getFirstRow('array') ?? [];

    // tenta dados do paciente (por telefone)
    $paciente = $db->table('pacientes')->where('telefone', $numero)->get()->getFirstRow('array') ?? [];

    // histórico recente (limita a 10 mensagens)
    $historico = [];
    if (!empty($sessao['historico'])) {
        $tmp = json_decode($sessao['historico'], true);
        if (is_array($tmp)) {
            $historico = array_slice($tmp, -10);
        }
    }

    // tags disponíveis e as do lead (reaproveitando a lógica dos outros endpoints)
    $tags = $db->table('tags')
        ->where('assinante_id', $this->assinanteId)
        ->orderBy('nome', 'ASC')
        ->get()->getResultArray();

    $doLeadRows = $db->table('sessao_tags')
        ->select('tag_id')
        ->where('numero', $numero)
        ->get()->getResultArray();
    $doLead = array_map(fn($r) => (int)$r['tag_id'], $doLeadRows);

    // notas
    $notas = $db->table('sessao_notas')
        ->where('numero', $numero)
        ->orderBy('criado_em', 'DESC')
        ->get()->getResultArray();

    return $this->response->setJSON([
        'sessao'   => $sessao,
        'paciente' => $paciente,
        'historico'=> $historico,
        'tags'     => $tags,
        'doLead'   => $doLead,
        'notas'    => $notas,
    ]);
}

// Salvar nova observação do lead
public function salvarNota($numero)
{
    $texto = trim((string)$this->request->getPost('texto'));
    $autor = trim((string)$this->request->getPost('autor') ?: 'atendente');

    if ($texto === '') {
        return $this->response->setJSON(['ok' => false, 'msg' => 'Texto obrigatório'])->setStatusCode(400);
    }

    $db = \Config\Database::connect();
    $ok = $db->table('sessao_notas')->insert([
        'numero' => $numero,
        'texto'  => $texto,
        'autor'  => $autor,
    ]);

    if (!$ok) {
        return $this->response->setJSON(['ok' => false, 'msg' => 'Falha ao salvar'])->setStatusCode(500);
    }
    return $this->response->setJSON(['ok' => true]);
}
public function listarAgendamentos($numero)
{
    $db = \Config\Database::connect();
    $rows = $db->table('mensagens_agendadas')
        ->where('numero', $numero)
        // pendentes primeiro, depois enviados, depois cancelados (sem escapar a expressão)
        ->orderBy("FIELD(status, 'pendente','enviado','cancelado')", '', false)
        ->orderBy('enviar_em', 'ASC')
        ->get()->getResultArray();

    return $this->response->setJSON(['agendamentos' => $rows]);
}

public function agendarMensagem($numero)
{
    $mensagem = trim((string)$this->request->getPost('mensagem'));
    $data     = trim((string)$this->request->getPost('data')); // YYYY-MM-DD
    $hora     = trim((string)$this->request->getPost('hora')); // HH:MM

    if ($mensagem === '' || $data === '' || $hora === '') {
        return $this->response->setStatusCode(400)
            ->setJSON(['ok' => false, 'msg' => 'Preencha mensagem, data e hora.']);
    }

    $ts = strtotime("$data $hora");
    if ($ts === false) {
        return $this->response->setStatusCode(422)
            ->setJSON(['ok' => false, 'msg' => 'Data/hora inválidas.']);
    }
    if ($ts <= time()) {
        return $this->response->setStatusCode(422)
            ->setJSON(['ok' => false, 'msg' => 'Escolha um horário no futuro.']);
    }

    $db = \Config\Database::connect();
    $ok = $db->table('mensagens_agendadas')->insert([
        'numero'    => $numero,
        'mensagem'  => $mensagem,
        'enviar_em' => date('Y-m-d H:i:s', $ts),
        'status'    => 'pendente',
        'criado_em' => date('Y-m-d H:i:s'),
    ]);

    if (!$ok) {
        return $this->response->setStatusCode(500)
            ->setJSON(['ok' => false, 'msg' => 'Falha ao salvar.']);
    }
    return $this->response->setJSON(['ok' => true]);
}

public function cancelarAgendamento($id)
{
    $db = \Config\Database::connect();
    $ag = $db->table('mensagens_agendadas')->where('id', (int)$id)->get()->getFirstRow('array');
    if (!$ag) {
        return $this->response->setStatusCode(404)
            ->setJSON(['ok' => false, 'msg' => 'Agendamento não encontrado.']);
    }
    if ($ag['status'] !== 'pendente') {
        return $this->response->setStatusCode(422)
            ->setJSON(['ok' => false, 'msg' => 'Somente pendentes podem ser cancelados.']);
    }

    $db->table('mensagens_agendadas')->where('id', (int)$id)->update(['status' => 'cancelado']);
    return $this->response->setJSON(['ok' => true]);
}


}
