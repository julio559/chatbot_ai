<?php
namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class Whatsapp extends BaseController
{
    /* =================== Utils DB & num =================== */

    private function findInstanceById(int $id): ?array
    {
        $db = \Config\Database::connect();
        return $db->table('whatsapp_instancias')
            ->where('id', $id)
            ->where('usuario_id', (int) session('usuario_id'))
            ->get()->getRowArray() ?: null;
    }

    private function digits(?string $v): string
    {
        return preg_replace('/\D+/', '', (string) $v);
    }

    private function gwDigits(?string $v): string
    {
        return preg_replace('/\D+/', '', (string) $v);
    }

    /* =================== HTTP helpers =================== */

    /** Lê o body com tolerância: JSON -> raw JSON -> POST form */
    private function safeJsonBody(): array
    {
        try {
            $j = $this->request->getJSON(true);
            if (is_array($j)) {
                return $j;
            }
        } catch (\Throwable $e) {
            // fallback
        }

        $raw = (string) $this->request->getBody();
        if ($raw !== '') {
            $arr = json_decode($raw, true);
            if (is_array($arr)) {
                return $arr;
            }
        }

        $post = $this->request->getPost();
        return is_array($post) ? $post : [];
    }

    /**
     * Cliente HTTP pro gateway (Node Baileys).
     * Retorna: ['ok'=>bool, 'json'=>array|null, 'raw'=>string|null, 'code'=>int, 'err'=>string|null]
     */
    private function gwCall(string $method, string $path, array $data = []): array
    {
        $base = rtrim((string) env('GATEWAY_URL'), '/');
        $key  = (string) env('GATEWAY_KEY');

        if ($base === '' || $key === '') {
            return ['ok' => false, 'err' => 'GATEWAY_URL/GATEWAY_KEY ausentes no .env'];
        }

        $client = \Config\Services::curlrequest();

        $opts = [
            'http_errors' => false,
            'headers'     => ['x-api-key' => $key],
            'timeout'     => 25,
        ];

        if (strtoupper($method) === 'GET') {
            if (!empty($data)) {
                $opts['query'] = $data; // suporta ?autoheal=1 etc
            }
        } else {
            $opts['json'] = $data;
        }

        try {
            $res  = $client->request($method, $base . $path, $opts);
            $code = $res->getStatusCode();
            $body = (string) $res->getBody();
            $ct   = $res->getHeaderLine('Content-Type');

            $json = json_decode($body, true);
            if ($json === null && stripos($ct, 'application/json') === false) {
                return ['ok' => $code >= 200 && $code < 300, 'raw' => $body, 'code' => $code];
            }

            return ['ok' => $code >= 200 && $code < 300, 'json' => $json, 'code' => $code];
        } catch (\Throwable $e) {
            return ['ok' => false, 'err' => 'gateway unreachable: ' . $e->getMessage()];
        }
    }

    /* =================== Endpoint unificado pro gateway =================== */
    /**
     * POST /whatsapp/gw
     * Body:
     *  { "op":"create" }
     *  { "op":"status","sid":"..." }
     *  { "op":"qr","sid":"..." }
     *  { "op":"pair","sid":"...","phone":"553199..." }
     *  { "op":"send","sid":"...","to":"553199...","text":"..." }
     *  { "op":"end","sid":"..." }
     */
    public function gw()
    {
        $payload = $this->safeJsonBody();
        $op      = strtolower((string) ($payload['op'] ?? ''));

        if ($op === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'err' => 'op obrigatório']);
        }

        switch ($op) {
            case 'create': {
                $sid  = isset($payload['sid']) ? (string) $payload['sid'] : null;
                $data = $sid ? ['sid' => $sid] : [];
                $r = $this->gwCall('POST', '/session', $data);
                if (!$r['ok']) {
                    return $this->response->setStatusCode(502)->setJSON([
                        'ok' => false, 'err' => $r['err'] ?? 'gateway error', 'raw' => $r['json'] ?? ($r['raw'] ?? null),
                    ]);
                }
                return $this->response->setJSON($r['json'] ?? ['ok' => true]);
            }

            case 'status': {
                $sid = (string) ($payload['sid'] ?? '');
                if ($sid === '') {
                    return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'err' => 'sid obrigatório']);
                }
                // autoheal on
                $r = $this->gwCall('GET', "/session/{$sid}/status", ['autoheal' => 1]);
                if (!$r['ok']) {
                    return $this->response->setStatusCode(502)->setJSON([
                        'ok' => false, 'err' => $r['err'] ?? 'gateway error', 'raw' => $r['json'] ?? ($r['raw'] ?? null),
                    ]);
                }
                return $this->response->setJSON($r['json'] ?? ['ok' => true, 'status' => 'unknown']);
            }

            case 'qr': {
                $sid = (string) ($payload['sid'] ?? '');
                if ($sid === '') {
                    return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'err' => 'sid obrigatório']);
                }
                $r = $this->gwCall('GET', "/session/{$sid}/qr");
                if (!$r['ok']) {
                    return $this->response->setStatusCode(502)->setJSON([
                        'ok' => false, 'err' => $r['err'] ?? 'gateway error', 'raw' => $r['json'] ?? ($r['raw'] ?? null),
                    ]);
                }
                return $this->response->setJSON($r['json'] ?? ['ok' => true, 'status' => 'unknown', 'qr' => null]);
            }

            case 'pair': {
                $sid   = (string) ($payload['sid'] ?? '');
                $phone = $this->gwDigits($payload['phone'] ?? '');
                if ($sid === '' || $phone === '') {
                    return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'err' => 'sid e phone obrigatórios']);
                }
                $r = $this->gwCall('POST', "/session/{$sid}/pair", ['phone' => $phone]);
                if (!$r['ok']) {
                    $msg = $r['err'] ?? ($r['json']['error'] ?? 'gateway error');
                    return $this->response->setStatusCode(502)->setJSON([
                        'ok' => false, 'err' => $msg, 'raw' => $r['json'] ?? ($r['raw'] ?? null),
                    ]);
                }
                return $this->response->setJSON($r['json'] ?? ['ok' => true]);
            }

            case 'send': {
                $sid  = (string) ($payload['sid'] ?? '');
                $to   = $this->gwDigits($payload['to'] ?? '');
                $text = (string) ($payload['text'] ?? '');
                if ($sid === '' || $to === '' || $text === '') {
                    return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'err' => 'sid, to e text são obrigatórios']);
                }
                $r = $this->gwCall('POST', "/session/{$sid}/send", ['to' => $to, 'text' => $text]);
                if (!$r['ok']) {
                    $msg = $r['err'] ?? ($r['json']['error'] ?? 'gateway error');
                    return $this->response->setStatusCode(502)->setJSON([
                        'ok' => false, 'err' => $msg, 'raw' => $r['json'] ?? ($r['raw'] ?? null),
                    ]);
                }
                return $this->response->setJSON($r['json'] ?? ['ok' => true]);
            }

            case 'end': {
                $sid = (string) ($payload['sid'] ?? '');
                if ($sid === '') {
                    return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'err' => 'sid obrigatório']);
                }
                $r = $this->gwCall('DELETE', "/session/{$sid}");
                if (!$r['ok']) {
                    return $this->response->setStatusCode(502)->setJSON([
                        'ok' => false, 'err' => $r['err'] ?? 'gateway error', 'raw' => $r['json'] ?? ($r['raw'] ?? null),
                    ]);
                }
                return $this->response->setJSON($r['json'] ?? ['ok' => true]);
            }

            default:
                return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'err' => 'op inválida']);
        }
    }

    /* =================== Página =================== */

    public function index()
    {
        $db = \Config\Database::connect();
        $lista = $db->table('whatsapp_instancias')
            ->where('usuario_id', (int) session('usuario_id'))
            ->orderBy('id', 'desc')
            ->get()->getResultArray();

        // nosso webhook próprio
        $webhookBase = base_url('webhook-sessao/receive');

        return view('whatsapp_connect', [
            'instancias'  => $lista,
            'webhookBase' => $webhookBase,
        ]);
    }

    public function webhookBase()
    {
        return $this->response->setJSON([
            'webhookBase' => base_url('webhook-sessao/receive'),
        ]);
    }

    /* =================== Criar/Editar instância =================== */

    public function bind()
    {
        $id       = (int) ($this->request->getPost('id') ?? 0); // 0 = criar
        $nome     = trim((string) ($this->request->getPost('nome') ?? ''));
        $linha    = $this->digits($this->request->getPost('linha_msisdn') ?? '');
        $sidForm  = trim((string) ($this->request->getPost('sid') ?? '')); // permitido informar
        $nome     = $nome !== '' ? $nome : 'Instância';

        $db        = \Config\Database::connect();
        $usuarioId = (int) session('usuario_id');

        if ($id > 0) {
            // editar nome/linha/sid
            $curr = $this->findInstanceById($id);
            if (!$curr) {
                return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'msg' => 'instância não encontrada']);
            }

            $sid = $sidForm !== '' ? $sidForm : (string) ($curr['sid'] ?? $curr['instance_id'] ?? '');

            // se mudou o SID, tenta garantir que sessão exista no gateway
            if ($sid !== '' && $sid !== (string) ($curr['sid'] ?? '')) {
                $this->gwCall('POST', '/session', ['sid' => $sid]);
            }

            $upd = [
                'nome'         => $nome,
                'linha_msisdn' => $linha ?: $curr['linha_msisdn'],
                'sid'          => $sid ?: ($curr['sid'] ?? null),
                'instance_id'  => $sid ?: ($curr['instance_id'] ?? null), // compat UI
                'updated_at'   => date('Y-m-d H:i:s'),
            ];
            $db->table('whatsapp_instancias')
                ->where('id', $id)->where('usuario_id', $usuarioId)->update($upd);

            return $this->response->setJSON(['ok' => true, 'id' => $id]);
        }

        // criar: usa SID informado ou gera no gateway
        if ($sidForm !== '') {
            $r = $this->gwCall('POST', '/session', ['sid' => $sidForm]);
        } else {
            $r = $this->gwCall('POST', '/session', []);
        }

        if (!$r['ok'] || empty($r['json']['sid'])) {
            return $this->response->setStatusCode(502)->setJSON([
                'ok'  => false,
                'msg' => 'Falha ao criar sessão no gateway',
                'raw' => $r['json'] ?? ($r['raw'] ?? null),
            ]);
        }
        $sid = (string) $r['json']['sid'];

        // salva no banco (guarde sid também em instance_id p/ compatibilidade da UI)
        $db->table('whatsapp_instancias')->insert([
            'usuario_id'   => $usuarioId,
            'nome'         => $nome,
            'instance_id'  => $sid,        // compat com a UI atual
            'sid'          => $sid,        // coluna própria
            'linha_msisdn' => $linha ?: null,
            'webhook_url'  => (string) ($this->request->getPost('webhook_url') ?? base_url('webhook-sessao/receive')),
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        $newId = (int) $db->insertID();

        // webhook (além de gravar no banco, seta no gateway por sessão)
        $hook = (string) ($this->request->getPost('webhook_url') ?? base_url('webhook-sessao/receive'));
        if ($hook) {
            $this->gwCall('POST', "/session/{$sid}/webhook", ['webhook_url' => $hook]);
            $db->table('whatsapp_instancias')->where('id', $newId)->update([
                'webhook_url' => $hook,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->response->setJSON(['ok' => true, 'id' => $newId]);
    }

    /* =================== Status/QR/Reset/Logout =================== */

    /** RESET: apaga credenciais e sobe novamente (gera novo QR) */
    public function reset($id = null)
    {
        $id  = (int) $id;
        $row = $this->findInstanceById($id);
        if (!$row) {
            return $this->response->setStatusCode(404)->setJSON(['ok'=>false,'msg'=>'instância não encontrada']);
        }
        $sid = (string) ($row['sid'] ?? '');
        if ($sid === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'msg'=>'instância sem sid']);
        }

        // chama /session/:sid/reset no gateway
        $r = $this->gwCall('POST', "/session/{$sid}/reset");
        if (!$r['ok']) {
            return $this->response->setStatusCode(502)->setJSON([
                'ok'=>false,
                'err'=>$r['err'] ?? 'gateway error',
                'raw'=>$r['json'] ?? ($r['raw'] ?? null),
            ]);
        }

        // limpa status local para forçar novo QR na UI
        \Config\Database::connect()->table('whatsapp_instancias')
            ->where('id', $row['id'])
            ->update([
                'conn_status'    => 'qr',
                'conn_substatus' => 'reset',
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

        return $this->response->setJSON(['ok'=>true]);
    }

    /** STATUS: expõe status + último erro (com autoheal=1) */
    public function status($id = null)
    {
        $id  = (int) $id;
        $row = $this->findInstanceById($id);
        if (!$row) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'msg' => 'instância não encontrada']);
        }

        $sid = (string) ($row['sid'] ?? '');
        if ($sid === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'msg' => 'instância sem sid']);
        }

        $r = $this->gwCall('GET', "/session/{$sid}/status", ['autoheal' => 1]);
        if (!$r['ok']) {
            return $this->response->setStatusCode(502)->setJSON([
                'ok'     => false,
                'status' => 'unknown',
                'raw'    => $r['json'] ?? ($r['raw'] ?? null),
                'err'    => $r['err'] ?? 'gateway error',
            ]);
        }

        $json     = $r['json'] ?? [];
        $status   = (string) ($json['status'] ?? 'unknown');
        $msisdn   = preg_replace('/\D+/', '', (string) ($json['msisdn'] ?? ''));
        $lastErr  = $json['lastError'] ?? null;

        $upd = [
            'conn_status'    => $status,
            'last_status_at' => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];
        if ($msisdn && empty($row['linha_msisdn'])) $upd['linha_msisdn'] = $msisdn;

        \Config\Database::connect()->table('whatsapp_instancias')
            ->where('id', $row['id'])
            ->update($upd);

        return $this->response->setJSON(['ok' => true, 'status' => $status, 'raw' => $json, 'lastError' => $lastErr]);
    }

    /** QR: proxy do QR do gateway (svg/png ou json) */
    public function qr($id = null)
    {
        $id  = (int) $id;
        $row = $this->findInstanceById($id);
        if (!$row) {
            return $this->response->setStatusCode(404);
        }

        $sid = (string) ($row['sid'] ?? '');
        if ($sid === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'msg' => 'instância sem sid']);
        }

        // 1) tenta stream do SVG do gateway
        try {
            $base   = rtrim((string) env('GATEWAY_URL'), '/');
            $key    = (string) env('GATEWAY_KEY');
            $client = \Config\Services::curlrequest();

            $res = $client->get($base . "/session/{$sid}/qr.svg", [
                'http_errors' => false,
                'headers'     => ['x-api-key' => $key],
                'timeout'     => 15,
            ]);

            if ($res->getStatusCode() === 200
                && stripos($res->getHeaderLine('Content-Type'), 'image/svg') !== false) {

                \Config\Database::connect()->table('whatsapp_instancias')
                    ->where('id', $row['id'])
                    ->update(['last_qr_at' => date('Y-m-d H:i:s')]);

                return $this->response
                    ->setHeader('Content-Type', 'image/svg+xml')
                    ->setBody((string) $res->getBody());
            }
        } catch (\Throwable $e) {
            // fallback
        }

        // 2) fallback JSON { qr: data:image/... }
        $r = $this->gwCall('GET', "/session/{$sid}/qr");
        if (!$r['ok']) {
            return $this->response->setStatusCode(502)->setJSON([
                'ok'  => false,
                'err' => $r['err'] ?? 'gateway error',
                'raw' => $r['json'] ?? ($r['raw'] ?? null),
            ]);
        }

        $json = $r['json'] ?? [];
        $qr   = (string) ($json['qr'] ?? '');
        if (strpos($qr, 'data:image') === 0) {
            $b64 = preg_replace('#^data:image/\w+;base64,#', '', $qr);
            $png = base64_decode($b64);
            if ($png !== false) {
                \Config\Database::connect()->table('whatsapp_instancias')
                    ->where('id', $row['id'])
                    ->update(['last_qr_at' => date('Y-m-d H:i:s')]);

                return $this->response->setHeader('Content-Type', 'image/png')->setBody($png);
            }
        }

        return $this->response->setJSON($json + ['ok' => true]);
    }

    /** Força logout total no gateway e zera status local */
    public function logout($id = null)
    {
        $id  = (int) $id;
        $row = $this->findInstanceById($id);
        if (!$row) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'msg' => 'instância não encontrada']);
        }

        $sid = (string) ($row['sid'] ?? '');
        if ($sid === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'msg' => 'instância sem sid']);
        }

        $r = $this->gwCall('DELETE', "/session/{$sid}");
        if (!$r['ok']) {
            return $this->response->setStatusCode(502)->setJSON([
                'ok'  => false,
                'err' => $r['err'] ?? 'gateway error',
                'raw' => $r['json'] ?? ($r['raw'] ?? null),
            ]);
        }

        \Config\Database::connect()->table('whatsapp_instancias')
            ->where('id', $row['id'])
            ->update([
                'conn_status'    => null,
                'conn_substatus' => null,
                'status_note'    => null,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

        return $this->response->setJSON(['ok' => true]);
    }

    /* =================== Webhook (UI) =================== */

    /**
     * Persiste o webhook na tabela e também envia pro gateway por sessão.
     */
    public function setWebhook($id = null)
    {
        $id  = (int) $id;
        $row = $this->findInstanceById($id);
        if (!$row) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'msg' => 'instância não encontrada']);
        }

        $hook = (string) $this->request->getPost('webhook_url');
        if (!$hook) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'msg' => 'webhook_url obrigatório']);
        }

        $sid = (string) ($row['sid'] ?? $row['instance_id'] ?? '');
        if ($sid !== '') {
            $this->gwCall('POST', "/session/{$sid}/webhook", ['webhook_url' => $hook]);
        }

        \Config\Database::connect()->table('whatsapp_instancias')
            ->where('id', $row['id'])
            ->update([
                'webhook_url' => $hook,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);

        return $this->response->setJSON(['ok' => true]);
    }

    /* =================== Excluir instância =================== */

    public function delete($id)
    {
        $id = (int) $id;

        $model    = new \App\Models\WhatsappInstanceModel();
        $instance = $model->where('id', $id)
            ->where('usuario_id', (int) session('usuario_id'))
            ->first();

        if (!$instance) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Instância não encontrada',
            ]);
        }

        $sid = (string) ($instance['sid'] ?? $instance['instance_id'] ?? '');
        if ($sid !== '') {
            $this->gwCall('DELETE', "/session/{$sid}");
        }

        $model->delete($id);

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => 'Instância removida e sessão encerrada no gateway',
        ]);
    }
}
