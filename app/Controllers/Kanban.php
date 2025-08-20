<?php

namespace App\Controllers;

use App\Models\SessaoModel;
use App\Models\ConfigIaModel;
use CodeIgniter\Controller;

class Kanban extends Controller
{
    protected ?int $usuarioId   = null;
    protected ?int $assinanteId = null;

  public function __construct()
{
    $this->usuarioId   = (int)(session()->get('usuario_id') ?? 0) ?: 1; // <- fallback p/ testes
    $this->assinanteId = (int)(session()->get('assinante_id') ?? 0) ?: 1;
}

    public function index()
    {
        // if (!$this->usuarioId) return redirect()->to('/login');

        $db             = \Config\Database::connect();
        $configIaModel  = new ConfigIaModel();

        // Etapas do assinante (ordenadas)
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

        // Colunas: somente sessões do usuário logado
        $colunas = [];
        foreach ($etapas as $key => $titulo) {
            $clientes = $db->table('sessoes s')
                ->select('s.numero, s.etapa, s.ultima_mensagem_usuario, s.ultima_resposta_ia, p.nome AS paciente_nome, p.ultimo_contato')
                ->join('pacientes p', 'p.telefone = s.numero', 'left')
                ->where('s.usuario_id', $this->usuarioId)
                ->where('s.etapa', $key)
                ->orderBy('p.ultimo_contato', 'DESC')
                ->get()->getResultArray();

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
        if (!$this->usuarioId) {
            return $this->response->setStatusCode(401)->setJSON(['status' => 'erro', 'message' => 'Não autenticado']);
        }

        $numero    = trim((string) $this->request->getPost('numero'));
        $novaEtapa = trim((string) $this->request->getPost('etapa'));

        if ($numero === '' || $novaEtapa === '') {
            return $this->response->setJSON(['status' => 'erro', 'message' => 'Dados inválidos'])->setStatusCode(400);
        }

        // valida se a etapa existe no assinante
        $etapaOk = (new ConfigIaModel())
            ->where('assinante_id', $this->assinanteId)
            ->where('etapa_atual', $novaEtapa)
            ->first();
        if (!$etapaOk) {
            return $this->response->setJSON(['status' => 'erro', 'message' => 'Etapa inexistente'])->setStatusCode(422);
        }

        // garante posse do lead (adota se usuario_id estiver NULL)
        $db   = \Config\Database::connect();
        $sess = $db->table('sessoes')->where('numero', $numero)->get()->getFirstRow('array');
        if (!$sess) {
            return $this->response->setJSON(['status' => 'erro', 'message' => 'Lead não encontrado'])->setStatusCode(404);
        }
        if (!empty($sess['usuario_id']) && (int)$sess['usuario_id'] !== $this->usuarioId) {
            return $this->response->setJSON(['status' => 'erro', 'message' => 'Sem permissão para este lead'])->setStatusCode(403);
        }
        if (empty($sess['usuario_id'])) {
            $db->table('sessoes')->where('numero', $numero)->update(['usuario_id' => $this->usuarioId]);
        }

        $db->table('sessoes')->where('numero', $numero)->update(['etapa' => $novaEtapa]);

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
        if (!$this->usuarioId) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Não autenticado'])->setStatusCode(401);
        }

        $nome = trim((string) $this->request->getPost('nome'));
        $cor  = trim((string) ($this->request->getPost('cor') ?: '#3b82f6'));

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
        if (!$this->usuarioId) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Não autenticado'])->setStatusCode(401);
        }

        $db   = \Config\Database::connect();
        $num  = trim((string)$numero);

        if (!$this->assertLeadDoUsuario($num)) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'msg' => 'Sem permissão para este lead']);
        }

        $tags = $db->table('tags')
            ->where('assinante_id', $this->assinanteId)
            ->orderBy('nome', 'ASC')
            ->get()->getResultArray();

        // somente tags válidas do assinante atual
        $doLeadRows = $db->table('sessao_tags st')
            ->select('st.tag_id')
            ->join('tags t', 't.id = st.tag_id', 'inner')
            ->where('st.numero', $num)
            ->where('t.assinante_id', $this->assinanteId)
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
        if (!$this->usuarioId) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Não autenticado'])->setStatusCode(401);
        }

        $db  = \Config\Database::connect();
        $num = trim((string)$numero);

        if (!$this->assertLeadDoUsuario($num, true)) { // adota se sem dono
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'msg' => 'Sem permissão para este lead']);
        }

        $ids = $this->request->getPost('tags'); // pode vir array de strings
        if (!is_array($ids)) $ids = [];

        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            // limpar todas
            $db->table('sessao_tags')->where('numero', $num)->delete();
            return $this->response->setJSON(['ok' => true]);
        }

        // filtra para somente tags do assinante
        $tagsValidas = $db->table('tags')
            ->where('assinante_id', $this->assinanteId)
            ->whereIn('id', $ids)
            ->select('id')->get()->getResultArray();
        $validos = array_map(fn($r) => (int)$r['id'], $tagsValidas);

        $db->transStart();

        $db->table('sessao_tags')->where('numero', $num)->delete();
        foreach ($validos as $tagId) {
            $db->table('sessao_tags')->insert([
                'numero' => $num,
                'tag_id' => $tagId
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Falha ao salvar'])->setStatusCode(500);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    // ---- DETALHES DO LEAD ----
    public function leadDetalhes($numero)
    {
        if (!$this->usuarioId) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Não autenticado'])->setStatusCode(401);
        }

        $db  = \Config\Database::connect();
        $num = trim((string)$numero);

        if (!$this->assertLeadDoUsuario($num)) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'msg' => 'Sem permissão para este lead']);
        }

        $sessao = $db->table('sessoes')->where('numero', $num)->get()->getFirstRow('array') ?? [];
        $paciente = $db->table('pacientes')->where('telefone', $num)->get()->getFirstRow('array') ?? [];

        // histórico (últimas 10)
        $historico = [];
        if (!empty($sessao['historico'])) {
            $tmp = json_decode($sessao['historico'], true);
            if (is_array($tmp)) $historico = array_slice($tmp, -10);
        }

        $tags = $db->table('tags')
            ->where('assinante_id', $this->assinanteId)
            ->orderBy('nome', 'ASC')
            ->get()->getResultArray();

        $doLeadRows = $db->table('sessao_tags st')
            ->select('st.tag_id')
            ->join('tags t', 't.id = st.tag_id', 'inner')
            ->where('st.numero', $num)
            ->where('t.assinante_id', $this->assinanteId)
            ->get()->getResultArray();
        $doLead = array_map(fn($r) => (int)$r['tag_id'], $doLeadRows);

        $notas = $db->table('sessao_notas')
            ->where('numero', $num)
            ->orderBy('criado_em', 'DESC')
            ->get()->getResultArray();

        return $this->response->setJSON([
            'sessao'    => $sessao,
            'paciente'  => $paciente,
            'historico' => $historico,
            'tags'      => $tags,
            'doLead'    => $doLead,
            'notas'     => $notas,
        ]);
    }

    // Salvar nova observação do lead
    public function salvarNota($numero)
    {
        if (!$this->usuarioId) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Não autenticado'])->setStatusCode(401);
        }

        $num   = trim((string)$numero);
        $texto = trim((string)$this->request->getPost('texto'));
        $autor = trim((string)($this->request->getPost('autor') ?: 'atendente'));

        if ($texto === '') {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Texto obrigatório'])->setStatusCode(400);
        }

        if (!$this->assertLeadDoUsuario($num, true)) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Sem permissão para este lead'])->setStatusCode(403);
        }

        $db = \Config\Database::connect();
        $ok = $db->table('sessao_notas')->insert([
            'numero' => $num,
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
        if (!$this->usuarioId) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Não autenticado'])->setStatusCode(401);
        }

        $num = trim((string)$numero);
        if (!$this->assertLeadDoUsuario($num)) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Sem permissão'])->setStatusCode(403);
        }

        $db = \Config\Database::connect();
        $rows = $db->table('mensagens_agendadas')
            ->where('numero', $num)
            ->where('usuario_id', $this->usuarioId) // requer coluna usuario_id (migração já enviada)
            ->orderBy("FIELD(status, 'pendente','enviado','cancelado')", '', false)
            ->orderBy('enviar_em', 'ASC')
            ->get()->getResultArray();

        return $this->response->setJSON(['agendamentos' => $rows]);
    }

    public function agendarMensagem($numero)
    {
        if (!$this->usuarioId) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Não autenticado'])->setStatusCode(401);
        }

        $num      = trim((string)$numero);
        $mensagem = trim((string)$this->request->getPost('mensagem'));
        $data     = trim((string)$this->request->getPost('data')); // YYYY-MM-DD
        $hora     = trim((string)$this->request->getPost('hora')); // HH:MM

        if ($mensagem === '' || $data === '' || $hora === '') {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'msg' => 'Preencha mensagem, data e hora.']);
        }

        $ts = strtotime("$data $hora");
        if ($ts === false) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'msg' => 'Data/hora inválidas.']);
        }
        if ($ts <= time()) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'msg' => 'Escolha um horário no futuro.']);
        }

        // garante posse do lead
        if (!$this->assertLeadDoUsuario($num, true)) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Sem permissão para este lead'])->setStatusCode(403);
        }

        $db = \Config\Database::connect();
        $ok = $db->table('mensagens_agendadas')->insert([
            'numero'       => $num,
            'mensagem'     => $mensagem,
            'enviar_em'    => date('Y-m-d H:i:s', $ts),
            'status'       => 'pendente',
            'usuario_id'   => $this->usuarioId,     // <- escopo do dono
            'criado_em'    => date('Y-m-d H:i:s'),
        ]);

        if (!$ok) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'msg' => 'Falha ao salvar.']);
        }
        return $this->response->setJSON(['ok' => true]);
    }

    public function cancelarAgendamento($id)
    {
        if (!$this->usuarioId) {
            return $this->response->setJSON(['ok' => false, 'msg' => 'Não autenticado'])->setStatusCode(401);
        }

        $db = \Config\Database::connect();
        $ag = $db->table('mensagens_agendadas')
            ->where('id', (int)$id)
            ->where('usuario_id', $this->usuarioId)
            ->get()->getFirstRow('array');

        if (!$ag) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'msg' => 'Agendamento não encontrado.']);
        }
        if ($ag['status'] !== 'pendente') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'msg' => 'Somente pendentes podem ser cancelados.']);
        }

        $db->table('mensagens_agendadas')->where('id', (int)$id)->update(['status' => 'cancelado']);
        return $this->response->setJSON(['ok' => true]);
    }

    /* ==================== HELPERS ==================== */

    /**
     * Verifica se o lead (sessão) pertence ao usuário atual; se estiver sem dono e $adotar=true, adota.
     */
    private function assertLeadDoUsuario(string $numero, bool $adotar = false): bool
    {
        $db   = \Config\Database::connect();
        $sess = $db->table('sessoes')->where('numero', $numero)->get()->getFirstRow('array');

        if (!$sess) {
            // cria sessão já do usuário atual (caso queira)
            if ($adotar) {
                $db->table('sessoes')->insert([
                    'numero'     => $numero,
                    'usuario_id' => $this->usuarioId,
                    'etapa'      => 'entrada',
                    'historico'  => json_encode([], JSON_UNESCAPED_UNICODE),
                ]);
                return true;
            }
            return false;
        }

        if (!empty($sess['usuario_id']) && (int)$sess['usuario_id'] !== $this->usuarioId) {
            return false;
        }

        if (empty($sess['usuario_id']) && $adotar) {
            $db->table('sessoes')->where('numero', $numero)->update(['usuario_id' => $this->usuarioId]);
        }
        return true;
    }

    // dentro da class Kanban

// GET /kanban/etapa-config/{etapa}
public function etapaConfig($etapa)
{
    $store = WRITEPATH . 'kanban_cfg_' . $this->assinanteId . '.json';
    $all = is_file($store) ? json_decode(file_get_contents($store), true) : [];
    return $this->response->setJSON($all[$etapa] ?? []);
}

// POST /kanban/etapa-config/{etapa}  (body: json=<payload>)
public function salvarEtapaConfig($etapa)
{
    if (!$this->usuarioId) return $this->response->setStatusCode(401)->setJSON(['ok'=>false,'msg'=>'Não autenticado']);
    $payload = json_decode((string)$this->request->getPost('json'), true) ?? [];
    $store = WRITEPATH . 'kanban_cfg_' . $this->assinanteId . '.json';
    $all = is_file($store) ? json_decode(file_get_contents($store), true) : [];
    $all[$etapa] = $payload;
    file_put_contents($store, json_encode($all, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    return $this->response->setJSON(['ok'=>true]);
}

// POST /kanban/etapa-config/teste/{etapa}
public function testeEtapaConfig($etapa)
{
    if (!$this->usuarioId) return $this->response->setStatusCode(401)->setJSON(['ok'=>false,'msg'=>'Não autenticado']);
    // Aqui só confirmamos o recebimento; o envio real pode ser plugado depois.
    return $this->response->setJSON(['ok'=>true, 'msg'=>'Template recebido para teste.']);
}
// ====== ARQUIVOS DO LEAD ======

/** GET /kanban/lead-files/{numero} */
public function listarArquivos($numero)
{
    if (!$this->usuarioId) {
        return $this->response->setStatusCode(401)->setJSON(['ok'=>false,'msg'=>'Não autenticado']);
    }
    $num = trim((string)$numero);
    if (!$this->assertLeadDoUsuario($num)) {
        return $this->response->setStatusCode(403)->setJSON(['ok'=>false,'msg'=>'Sem permissão']);
    }

    $db = \Config\Database::connect();
    $rows = $db->table('lead_arquivos')
        ->where('numero', $num)
        ->where('usuario_id', $this->usuarioId)
        ->orderBy('uploaded_at', 'DESC')
        ->get()->getResultArray();

    // monta url de download
    foreach ($rows as &$r) {
        $r['url_download'] = base_url('kanban/lead-files/download/'.$r['id']);
    }

    return $this->response->setJSON(['ok'=>true,'arquivos'=>$rows]);
}

/** POST /kanban/lead-files/{numero}  (arquivo + procedimento + valor + observacao) */
public function uploadArquivo($numero)
{
    if (!$this->usuarioId) {
        return $this->response->setStatusCode(401)->setJSON(['ok'=>false,'msg'=>'Não autenticado']);
    }
    $num = trim((string)$numero);

    if (!$this->assertLeadDoUsuario($num, true)) {
        return $this->response->setStatusCode(403)->setJSON(['ok'=>false,'msg'=>'Sem permissão']);
    }

    $file = $this->request->getFile('arquivo');
    if (!$file || !$file->isValid()) {
        return $this->response->setStatusCode(400)->setJSON(['ok'=>false,'msg'=>'Arquivo inválido']);
    }

    // validações básicas
    $allowed = [
        'application/pdf','image/jpeg','image/png','image/webp',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain'
    ];
    $mime = $file->getMimeType();
    $size = $file->getSize(); // bytes
    if (!in_array($mime, $allowed)) {
        return $this->response->setStatusCode(415)->setJSON(['ok'=>false,'msg'=>'Tipo de arquivo não permitido']);
    }
    if ($size > 15*1024*1024) { // 15MB
        return $this->response->setStatusCode(413)->setJSON(['ok'=>false,'msg'=>'Arquivo acima de 15MB']);
    }

    // campos extras
    $procedimento = trim((string)$this->request->getPost('procedimento'));
    $valorStr     = trim((string)$this->request->getPost('valor'));
    $observacao   = trim((string)$this->request->getPost('observacao'));

    // normaliza valor (aceita vírgula)
    $valor = null;
    if ($valorStr !== '') {
        $valorStr = str_replace(['.', ','], ['', '.'], $valorStr); // 1.234,56 -> 1234.56
        if (is_numeric($valorStr)) $valor = (float)$valorStr;
    }

    // caminho físico
    $basePath = WRITEPATH . 'uploads/lead_files/' . $this->usuarioId . '/' . preg_replace('/\D+/', '', $num) . '/';
    if (!is_dir($basePath)) {
        @mkdir($basePath, 0775, true);
    }

    // nome seguro
    $ext   = $file->getClientExtension();
    try {
        $rand = bin2hex(random_bytes(16));
    } catch (\Throwable $e) {
        $rand = uniqid('f', true);
    }
    $stored = $rand . ($ext ? ('.' . strtolower($ext)) : '');

    // move
    if (!$file->move($basePath, $stored)) {
        return $this->response->setStatusCode(500)->setJSON(['ok'=>false,'msg'=>'Falha ao salvar no disco']);
    }

    $db = \Config\Database::connect();
    $ok = $db->table('lead_arquivos')->insert([
        'numero'          => $num,
        'usuario_id'      => $this->usuarioId,
        'assinante_id'    => $this->assinanteId ?: 0,
        'nome_original'   => $file->getClientName(),
        'nome_armazenado' => $stored,
        'mime'            => $mime,
        'tamanho'         => $size,
        'procedimento'    => $procedimento ?: null,
        'valor'           => $valor,
        'observacao'      => $observacao ?: null,
        'uploaded_at'     => date('Y-m-d H:i:s'),
    ]);

    if (!$ok) {
        @unlink($basePath . $stored);
        return $this->response->setStatusCode(500)->setJSON(['ok'=>false,'msg'=>'Falha ao gravar no banco']);
    }

    return $this->response->setJSON(['ok'=>true]);
}

/** GET /kanban/lead-files/download/{id} */
public function baixarArquivo($id)
{
    if (!$this->usuarioId) {
        return $this->response->setStatusCode(401)->setBody('Não autenticado');
    }
    $db = \Config\Database::connect();
    $row = $db->table('lead_arquivos')
        ->where('id', (int)$id)
        ->where('usuario_id', $this->usuarioId)
        ->get()->getFirstRow('array');

    if (!$row) {
        return $this->response->setStatusCode(404)->setBody('Arquivo não encontrado');
    }

    $path = WRITEPATH . 'uploads/lead_files/' . $this->usuarioId . '/' . preg_replace('/\D+/', '', $row['numero']) . '/' . $row['nome_armazenado'];
    if (!is_file($path)) {
        return $this->response->setStatusCode(404)->setBody('Arquivo ausente no disco');
    }

    return $this->response->download($path, null)->setFileName($row['nome_original']);
}

/** POST /kanban/lead-files/delete/{id} */
public function excluirArquivo($id)
{
    if (!$this->usuarioId) {
        return $this->response->setStatusCode(401)->setJSON(['ok'=>false,'msg'=>'Não autenticado']);
    }
    $db = \Config\Database::connect();
    $row = $db->table('lead_arquivos')
        ->where('id', (int)$id)
        ->where('usuario_id', $this->usuarioId)
        ->get()->getFirstRow('array');

    if (!$row) {
        return $this->response->setStatusCode(404)->setJSON(['ok'=>false,'msg'=>'Arquivo não encontrado']);
    }

    $path = WRITEPATH . 'uploads/lead_files/' . $this->usuarioId . '/' . preg_replace('/\D+/', '', $row['numero']) . '/' . $row['nome_armazenado'];

    $db->table('lead_arquivos')->where('id', (int)$id)->delete();
    if (is_file($path)) { @unlink($path); }

    return $this->response->setJSON(['ok'=>true]);
}

}
