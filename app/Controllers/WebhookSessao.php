<?php
namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class WebhookSessao extends ResourceController
{
    /* =================== Utils =================== */

    private function digits(?string $v): string
    {
        return preg_replace('/\D+/', '', (string) $v);
    }

    /** Lê body com tolerância (JSON -> raw JSON -> POST form). */
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

    /** Idempotência por (sid, provider_msg_id). */
    private function ensureIdempotency(?string $providerId, string $sid): bool
    {
        if (!$providerId) {
            return true;
        }
        $db = \Config\Database::connect();

        try {
            // Tenta com (instancia_key, provider_msg_id)
            $db->query(
                "INSERT IGNORE INTO webhook_msgs (instancia_key, provider_msg_id, created_at)
                 VALUES (?, ?, NOW())",
                [$sid, $providerId]
            );
            return $db->affectedRows() > 0;
        } catch (\Throwable $e1) {
            try {
                // Fallback: só provider_msg_id
                $db->query(
                    "INSERT IGNORE INTO webhook_msgs (provider_msg_id, created_at)
                     VALUES (?, NOW())",
                    [$providerId]
                );
                return $db->affectedRows() > 0;
            } catch (\Throwable $e2) {
                // Se a tabela não existir, não trava o fluxo
                return true;
            }
        }
    }

    /* =================== Endpoint =================== */

    /**
     * POST /webhook-sessao/receive
     * Espera:
     * - Conexão: { type:"connection", sid, status:"connected", msisdn }
     * - Mensagem: { type:"message", sid, from, to, text, pushname?, id?|messageId? , fromMe? }
     */
    public function receive()
    {
        // segurança
        $key = $this->request->getHeaderLine('x-api-key');
        if ($key !== (string) env('GATEWAY_KEY')) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'err' => 'unauthorized']);
        }

        $json = $this->safeJsonBody();
        $type = (string) ($json['type'] ?? '');

        if ($type === 'connection') {
            // { type:'connection', sid, status:'connected', msisdn }
            $sid    = (string) ($json['sid'] ?? '');
            $status = (string) ($json['status'] ?? 'connected');
            $msisdn = $this->digits($json['msisdn'] ?? '');

            if ($sid === '') {
                return $this->respond(['ok' => false, 'err' => 'sid ausente'], 422);
            }

            $db = \Config\Database::connect();
            $upd = [
                'conn_status'    => $status,
                'last_status_at' => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ];
            if ($msisdn !== '') {
                $upd['linha_msisdn'] = $msisdn;
            }

            $db->table('whatsapp_instancias')->where('sid', $sid)->update($upd);

            return $this->respond(['ok' => true]);
        }

        if ($type === 'message') {
            // { type:'message', sid, from, to, text, pushname?, id?|messageId?, fromMe? }
            $sid      = (string) ($json['sid'] ?? '');
            $from     = $this->digits($json['from'] ?? ''); // lead
            $to       = $this->digits($json['to']   ?? ''); // nossa linha
            $text     = (string)  ($json['text']   ?? '');
            $pushname = (string)  ($json['pushname'] ?? 'Contato');

            // aceitar id com fallback para messageId
            $msgId    = (string)  ($json['messageId'] ?? $json['id'] ?? '');
            $fromMe   = (bool)    ($json['fromMe'] ?? false);

            // ignorar mensagens enviadas por nós (evita poluir feed)
            if ($fromMe) {
                return $this->respond(['ok' => true, 'note' => 'fromMe ignored'], 200);
            }

            if ($sid === '' || $from === '' || $to === '' || $text === '') {
                return $this->respond(['ok' => false, 'err' => 'payload incompleto'], 422);
            }

            // idempotência
            if (!$this->ensureIdempotency($msgId ?: null, $sid)) {
                return $this->respond(['ok' => true, 'note' => 'duplicado'], 200);
            }

            $db   = \Config\Database::connect();
            $inst = $db->table('whatsapp_instancias')->where('sid', $sid)->get()->getRowArray();

            if ($inst) {
                $usuarioId = (int) $inst['usuario_id'];
                // persiste no feed
                $db->table('chat_mensagens')->insert([
                    'usuario_id' => $usuarioId,
                    'numero'     => $from,
                    'role'       => 'user',
                    'canal'      => 'whatsapp:' . $to,
                    'texto'      => $text,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Se quiser responder automaticamente aqui, chame sua IA e depois
            // faça POST /session/{sid}/send no gateway.

            return $this->respond(['ok' => true]);
        }

        return $this->respond(['ok' => true, 'note' => 'ignorado'], 200);
    }
}
