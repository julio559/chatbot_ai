<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class Chat extends ResourceController
{
    protected ?int $usuarioId   = null;
    protected ?int $assinanteId = null;

    public function __construct()
    {
        $this->usuarioId   = (int) (session()->get('usuario_id')   ?? 0) ?: null;
        $this->assinanteId = (int) (session()->get('assinante_id') ?? 0) ?: null;
    }

    /** VIEW SPA */
    public function index()
    {
        // if (!$this->usuarioId) return redirect()->to('/login');
        return view('chat'); // app/Views/chat.php
    }

    /**
     * GET /chat/contacts
     * Lista contatos do usuário logado.
     * Mantém compatibilidade com a UI atual (nome, telefone, ultimo_contato, unread_count).
     */
    public function contacts()
    {
        if (!$this->usuarioId) {
            return $this->fail('Não autenticado', 401);
        }

        $q     = trim((string) $this->request->getGet('q'));
        $limit = max(10, (int) ($this->request->getGet('limit') ?? 100));

        $db = \Config\Database::connect();

        // Critério de ordenação: último contato pelo feed de chat (chat_mensagens),
        // com fallback para pacientes. Assim a lista fica mais “real”.
        // 1) Últimas mensagens por número deste usuário
        $ultimas = $db->query("
            SELECT m.numero,
                   MAX(m.created_at) AS ultimo
              FROM chat_mensagens m
             WHERE m.usuario_id = ?
          GROUP BY m.numero
          ORDER BY ultimo DESC
          LIMIT ?
        ", [$this->usuarioId, $limit])->getResultArray();

        $numerosOrdenados = array_column($ultimas, 'numero');
        $mapUlt = [];
        foreach ($ultimas as $row) {
            $mapUlt[$row['numero']] = $row['ultimo'];
        }

        // 2) Puxa nomes na tabela de pacientes (quando existir)
        $pacientes = [];
        if (!empty($numerosOrdenados)) {
            $in  = implode(',', array_fill(0, count($numerosOrdenados), '?'));
            $par = $numerosOrdenados;
            array_unshift($par, $this->usuarioId); // primeiro param do where
            $pacRows = $db->query("
                SELECT p.nome, p.telefone, p.ultimo_contato
                  FROM pacientes p
                 WHERE p.usuario_id = ?
                   AND p.telefone IN ($in)
            ", $par)->getResultArray();

            foreach ($pacRows as $r) {
                $pacientes[$r['telefone']] = $r;
            }
        }

        // 3) Monta payload final
        $lista = [];
        foreach ($numerosOrdenados as $num) {
            $nome = $pacientes[$num]['nome'] ?? 'Paciente';
            $ultimoContato = $mapUlt[$num] ?? ($pacientes[$num]['ultimo_contato'] ?? null);
            $lista[] = [
                'nome'           => $nome,
                'telefone'       => $num,
                'ultimo_contato' => $ultimoContato,
                'unread_count'   => 0, // se quiser, implemente badge real depois
            ];
        }

        // Busca simples (se vier q), filtrando pelo nome/telefone
        if ($q !== '') {
            $qLower = mb_strtolower($q, 'UTF-8');
            $lista = array_values(array_filter($lista, function ($c) use ($qLower) {
                return (mb_strpos(mb_strtolower($c['nome'] ?? '', 'UTF-8'), $qLower) !== false)
                    || (mb_strpos((string)$c['telefone'], preg_replace('/\D+/', '', $qLower)) !== false);
            }));
        }

        return $this->respond($lista);
    }

    /**
     * GET /chat/messages/{numero}
     * Retorna TODAS as mensagens (unificadas) do paciente para o usuário logado,
     * juntando mensagens de diferentes instâncias (pois usamos chat_mensagens).
     */
    public function messages($numero = null)
    {
        if (!$this->usuarioId) {
            return $this->fail('Não autenticado', 401);
        }
        $numero = $this->normalizePhone((string) $numero);
        if ($numero === '') {
            return $this->fail('numero obrigatório', 400);
        }

        $db = \Config\Database::connect();

        // Garante/ajusta sessão para esse par (numero, usuario)
        $sessao = $db->table('sessoes')
            ->where('numero', $numero)
            ->get()->getFirstRow('array');

        if (!$sessao) {
            $sessao = [
                'numero'             => $numero,
                'usuario_id'         => $this->usuarioId,
                'etapa'              => 'entrada',
                'historico'          => json_encode([], JSON_UNESCAPED_UNICODE),
                'ultima_resposta_ia' => null,
                'data_atualizacao'   => date('Y-m-d H:i:s'),
            ];
            $db->table('sessoes')->insert($sessao);
        } elseif (!empty($sessao['usuario_id']) && (int) $sessao['usuario_id'] !== $this->usuarioId) {
            return $this->fail('sem permissão para este número', 403);
        } elseif (empty($sessao['usuario_id'])) {
            $db->table('sessoes')->where('numero', $numero)->update(['usuario_id' => $this->usuarioId]);
            $sessao['usuario_id'] = $this->usuarioId;
        }

        // Histórico unificado via chat_mensagens
        $rows = $db->query("
            SELECT role, texto AS content, created_at
              FROM chat_mensagens
             WHERE usuario_id = ?
               AND numero     = ?
          ORDER BY created_at ASC, id ASC
        ", [$this->usuarioId, $numero])->getResultArray();

        // Monta payload p/ UI
        $historico = array_map(function ($r) {
            return [
                'role'       => $r['role'],
                'content'    => (string) $r['content'],
                'created_at' => $r['created_at'],
            ];
        }, $rows);

        return $this->respond([
            'numero'    => $numero,
            'historico' => $historico,
            'etapa'     => $sessao['etapa'] ?? 'entrada',
        ]);
    }

    /**
     * POST /chat/send
     * Envia mensagem humana (atendente) e registra no chat_mensagens como 'humano'.
     */
    public function send()
    {
        if (!$this->usuarioId) {
            return $this->fail('Não autenticado', 401);
        }

        $numero   = $this->normalizePhone((string) ($this->request->getPost('numero')   ?? ''));
        $mensagem = trim((string) ($this->request->getPost('mensagem') ?? ''));

        if ($numero === '' || $mensagem === '') {
            return $this->fail('numero e mensagem são obrigatórios', 400);
        }

        $db = \Config\Database::connect();

        // Garante sessão
        $sessao = $db->table('sessoes')->where('numero', $numero)->get()->getFirstRow('array');
        if (!$sessao) {
            $db->table('sessoes')->insert([
                'numero'             => $numero,
                'usuario_id'         => $this->usuarioId,
                'etapa'              => 'entrada',
                'historico'          => json_encode([], JSON_UNESCAPED_UNICODE),
                'ultima_resposta_ia' => null,
                'data_atualizacao'   => date('Y-m-d H:i:s'),
            ]);
        } elseif (!empty($sessao['usuario_id']) && (int) $sessao['usuario_id'] !== $this->usuarioId) {
            return $this->fail('sem permissão para este número', 403);
        } elseif (empty($sessao['usuario_id'])) {
            $db->table('sessoes')->where('numero', $numero)->update(['usuario_id' => $this->usuarioId]);
        }

        // Salva no feed como HUMANO
        $db->table('chat_mensagens')->insert([
            'numero'     => $numero,
            'role'       => 'humano', // <— atendente
            'canal'      => 'whatsapp',
            'usuario_id' => $this->usuarioId,
            'texto'      => $mensagem,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Atualiza sessão (apenas para referência)
        $db->table('sessoes')
           ->where('numero', $numero)
           ->update([
               'ultima_resposta_ia' => null,
               'historico'          => json_encode([], JSON_UNESCAPED_UNICODE), // opcional: não usamos mais o campo historico da sessão
               'usuario_id'         => $this->usuarioId,
               'data_atualizacao'   => date('Y-m-d H:i:s'),
           ]);

        // Envio via UltraMSG (instância padrão do .env)
        $ok = $this->enviarParaWhatsapp($numero, $mensagem);
        if (!$ok['success']) {
            return $this->fail("Falha ao enviar: {$ok['detail']}", 500);
        }

        return $this->respond(['status' => 'enviado']);
    }

    /* ==================== Utils ==================== */

    private function normalizePhone(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';
        return preg_replace('/\D+/', '', $raw) ?? '';
    }

    private function enviarParaWhatsapp(string $numero, string $mensagem): array
    {
        $instanceId = env('ULTRA_INSTANCE_ID', '');
        $token      = env('ULTRA_TOKEN', '');
        if ($instanceId === '' || $token === '') {
            return ['success' => false, 'detail' => 'Credenciais UltraMSG ausentes no .env'];
        }

        $url  = "https://api.ultramsg.com/{$instanceId}/messages/chat";
        $data = http_build_query(['token' => $token, 'to' => $numero, 'body' => $mensagem]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_TIMEOUT        => 20,
        ]);

        $resp     = curl_exec($ch);
        $err      = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        log_message('error', "Chat::enviar WA ({$numero}) HTTP {$httpCode} - {$resp}");
        if ($err) {
            log_message('error', "Chat::cURL error - {$err}");
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'detail'  => $err ?: $resp,
        ];
    }
}
