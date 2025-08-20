<?php
namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class Whatsapp extends BaseController
{
    /** util: pega 1 instância do usuário atual por ID */
    private function findInstanceById(int $id): ?array {
        $db = \Config\Database::connect();
        return $db->table('whatsapp_instancias')
            ->where('id', $id)
            ->where('usuario_id', (int) session('usuario_id'))
            ->get()->getRowArray() ?: null;
    }

    /** util: só dígitos */
    private function digits(?string $v): string {
        return preg_replace('/\D+/', '', (string)$v);
    }

    /** util: normaliza o status retornado pela UltraMSG */
    private function parseUltraStatus(array $raw): array {
        $status     = null; // 'qr' | 'authenticated' | 'loading'...
        $statusText = null; // 'connected' | 'pairing'...
        $statusNote = null;

        if (isset($raw['accountStatus']) && is_array($raw['accountStatus'])) {
            $status     = $raw['accountStatus']['status']    ?? null;
            $statusText = $raw['accountStatus']['substatus'] ?? null;
        }

        if (!$status && isset($raw['status']) && is_array($raw['status'])) {
            if (isset($raw['status']['accountStatus']) && is_array($raw['status']['accountStatus'])) {
                $status     = $raw['status']['accountStatus']['status']    ?? $status;
                $statusText = $raw['status']['accountStatus']['substatus'] ?? $statusText;
            }
            if (!$status && isset($raw['status']['status']) && is_string($raw['status']['status'])) {
                $status = $raw['status']['status'];
            }
            if (!$statusNote && isset($raw['status']['message'])) {
                $statusNote = $raw['status']['message'];
            } elseif (!$statusNote && isset($raw['status']['statusMessage'])) {
                $statusNote = $raw['status']['statusMessage'];
            }
        }

        if (!$status && isset($raw['status']) && is_string($raw['status'])) {
            $status = $raw['status'];
        }

        if (!$statusNote && isset($raw['message'])) {
            $statusNote = $raw['message'];
        } elseif (!$statusNote && isset($raw['statusMessage'])) {
            $statusNote = $raw['statusMessage'];
        }

        return [$status, $statusText, $statusNote];
    }

    /** Página de conexão */
    public function index()
    {
        $db = \Config\Database::connect();
        $lista = $db->table('whatsapp_instancias')
            ->where('usuario_id', (int) session('usuario_id'))
            ->orderBy('id','desc')
            ->get()->getResultArray();

        $webhookBase = base_url('webhook');

        return view('whatsapp_connect', [
            'instancias'  => $lista,
            'webhookBase' => $webhookBase,
        ]);
    }

    /** webhook base JSON */
    public function webhookBase()
    {
        return $this->response->setJSON([
            'webhookBase' => base_url('webhook'),
        ]);
    }

    /** Salva credenciais em uma instância existente (ou cria) */
    public function bind()
    {
        $id       = (int) ($this->request->getPost('id') ?? 0); // 0 = criar
        $instance = trim((string) ($this->request->getPost('instance_id') ?? ''));
        $token    = trim((string) ($this->request->getPost('token') ?? ''));
        $nome     = trim((string) ($this->request->getPost('nome') ?? ''));
        $linha    = $this->digits($this->request->getPost('linha_msisdn') ?? '');

        $nome = $nome !== '' ? $nome : 'Instância';

        // regras:
        // - instance é obrigatório
        // - token é obrigatório na CRIAÇÃO
        // - linha_msisdn é obrigatória na CRIAÇÃO (para roteamento correto)
        if (!$instance || ($id <= 0 && !$token) || ($id <= 0 && !$linha)) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok'=>false,
                'msg'=>'Instance ID obrigatório. Token e Número da linha são obrigatórios na criação.'
            ]);
        }

        $db = \Config\Database::connect();

        // manter token atual se editar e enviar vazio
        $curr = null;
        if ($id > 0) {
            $curr = $db->table('whatsapp_instancias')
                ->where('id', $id)
                ->where('usuario_id', (int) session('usuario_id'))
                ->get()->getRowArray();
            if (!$curr) {
                return $this->response->setStatusCode(404)->setJSON(['ok'=>false,'msg'=>'instância não encontrada']);
            }
            if ($token === '') $token = $curr['token'];
            if ($linha === '') $linha = (string)($curr['linha_msisdn'] ?? '');
        }

        $data = [
            'usuario_id'   => (int) session('usuario_id'),
            'nome'         => $nome,
            'instance_id'  => $instance,
            'token'        => $token,
            'linha_msisdn' => $linha ?: null, // pode ficar null no update se já existir
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            $ok = $db->table('whatsapp_instancias')
                ->where('id', $id)
                ->where('usuario_id', (int) session('usuario_id'))
                ->update($data);
            return $this->response->setJSON(['ok' => (bool) $ok, 'id' => $id]);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $db->table('whatsapp_instancias')->insert($data);
            return $this->response->setJSON(['ok'=>true, 'id' => (int)$db->insertID()]);
        }
    }

    /** QR por ID */
    public function qr($id = null)
    {
        $id = (int) $id;
        $row = $this->findInstanceById($id);
        if (!$row) return $this->response->setStatusCode(404);

        $url = "https://api.ultramsg.com/{$row['instance_id']}/instance/qr?token=" . rawurlencode($row['token']);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $img = curl_exec($ch);
        curl_close($ch);

        if ($img === false) return $this->response->setStatusCode(502);

        \Config\Database::connect()->table('whatsapp_instancias')
            ->where('id', $row['id'])
            ->update(['last_qr_at'=>date('Y-m-d H:i:s')]);

        return $this->response->setHeader('Content-Type','image/png')->setBody($img);
    }

    /** Status por ID */
    public function status($id = null)
    {
        $id = (int) $id;
        $row = $this->findInstanceById($id);
        if (!$row) return $this->response->setStatusCode(404)->setJSON(['ok'=>false,'msg'=>'instância não encontrada']);

        $url = "https://api.ultramsg.com/{$row['instance_id']}/instance/status?token=" . rawurlencode($row['token']);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $json = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($json, true) ?: [];

        [$status, $statusText, $statusNote] = $this->parseUltraStatus($data);

        // tentativa de extrair número da linha do payload (opcional, depende da UltraMSG)
        $linhaDetectada = null;
        // exemplos de lugares possíveis (ajuste conforme seu retorno real):
        if (isset($data['wid'])) $linhaDetectada = $this->digits($data['wid']);
        if (!$linhaDetectada && isset($data['phone'])) $linhaDetectada = $this->digits($data['phone']);
        if (!$linhaDetectada && isset($data['status']['wid'])) $linhaDetectada = $this->digits($data['status']['wid']);

        $upd = [
            'conn_status'    => $status,
            'conn_substatus' => $statusText,
            'status_note'    => $statusNote,
            'status_raw'     => $json,
            'last_status_at' => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];
        if ($linhaDetectada && empty($row['linha_msisdn'])) {
            $upd['linha_msisdn'] = $linhaDetectada;
        }

        \Config\Database::connect()->table('whatsapp_instancias')
            ->where('id', $row['id'])
            ->update($upd);

        return $this->response->setJSON([
            'ok'     => true,
            'status' => $status ?? 'unknown',
            'raw'    => $data,
        ]);
    }

    /** Logout */
    public function logout($id = null)
    {
        $id  = (int) $id;
        $row = $this->findInstanceById($id);
        if (!$row) return $this->response->setStatusCode(404)->setJSON(['ok'=>false]);

        $url = "https://api.ultramsg.com/{$row['instance_id']}/instance/logout";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['token' => $row['token']]),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);

        \Config\Database::connect()->table('whatsapp_instancias')
            ->where('id', $row['id'])
            ->update([
                'conn_status'    => null,
                'conn_substatus' => null,
                'status_note'    => null,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

        return $this->response->setJSON(['ok'=>true, 'resp'=>json_decode($res,true)]);
    }

    /** Settings webhook */
    public function setWebhook($id = null)
    {
        $id  = (int) $id;
        $row = $this->findInstanceById($id);
        if (!$row) return $this->response->setStatusCode(404)->setJSON(['ok'=>false,'msg'=>'instância não encontrada']);

        $hook = (string) $this->request->getPost('webhook_url');
        if (!$hook) return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'msg'=>'webhook_url obrigatório']);

        $url = "https://api.ultramsg.com/{$row['instance_id']}/instance/settings";
        $payload = [
            'token'                    => $row['token'],
            'webhook_url'              => $hook,
            'webhook_message_received' => 'on',
            'webhook_message_create'   => 'on',
            'webhook_message_ack'      => 'on',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);

        \Config\Database::connect()->table('whatsapp_instancias')
            ->where('id', $row['id'])
            ->update([
                'webhook_url' => $hook,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);

        return $this->response->setJSON(['ok'=>true, 'resp'=>json_decode($res,true)]);
    }

    /** Remover instância */
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
                'message' => 'Instância não encontrada'
            ]);
        }

        $client = \Config\Services::curlrequest();

        // Reset na UltraMSG (API oficial)
        try {
            $ultraUrl  = "https://api.ultramsg.com/{$instance['instance_id']}/instance/clear";
            $response  = $client->post($ultraUrl, [
                'form_params' => ['token' => $instance['token']],
                'timeout'     => 20,
            ]);
            $status    = $response->getStatusCode();
            $bodyStr   = (string) $response->getBody();
            $resBody   = json_decode($bodyStr, true);

            if ($status < 200 || $status >= 300) {
                return $this->response->setStatusCode(502)->setJSON([
                    'status'  => 'error',
                    'message' => 'Falha ao resetar a instância na UltraMSG',
                    'ultramsg_response' => $resBody ?: $bodyStr,
                ]);
            }
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Erro ao resetar na UltraMSG: ' . $e->getMessage()
            ]);
        }

        // Remove local
        $model->delete($id);

        return $this->response->setJSON([
            'status'            => 'success',
            'message'           => 'Instância resetada na UltraMSG e removida localmente',
            'ultramsg_response' => $resBody ?? null,
            'endpoint_used'     => 'POST /instance/clear'
        ]);
    }
}
