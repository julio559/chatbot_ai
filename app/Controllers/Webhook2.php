<?php

namespace App\Controllers;

use App\Models\SessaoModel;
use App\Models\OpenrouterModel;
use App\Models\PacienteModel;
use CodeIgniter\RESTful\ResourceController;

class Webhook2 extends ResourceController
{
    /* ====================== Utilidades ====================== */

    /** Mantém apenas dígitos. */
    private function soDigitos(?string $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor);
    }

    /** Normaliza texto. */
    private function normalizarTexto(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', trim($s));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($ascii !== false) {
            $s = $ascii;
        }
        return mb_strtolower($s, 'UTF-8');
    }

    /** Extrai primeiro nome a partir do nome completo. */
    private function primeiroNome(?string $nome): string
    {
        $nome = trim((string) $nome);
        if ($nome === '') {
            return '';
        }
        $partes = preg_split('/\s+/', $nome);
        return trim((string) ($partes[0] ?? ''));
    }

    /** Retorna a linha da instância (se existir) pelo msisdn. */
    private function obterInstanciaDaLinha(string $nossoNumero): ?array
    {
        $db = \Config\Database::connect();
        return $db->table('whatsapp_instancias')
            ->where('linha_msisdn', $nossoNumero)
            ->orderBy('last_status_at', 'DESC')
            ->limit(1)->get()->getRowArray() ?: null;
    }

    /** Resolve o dono priorizando a instância; fallback pelo telefone do usuário. */
    private function encontrarDonoPorInstanciaOuLinha(string $nossoNumero): array
    {
        $db = \Config\Database::connect();

        // Pela instância (linha_msisdn)
        $inst = $this->obterInstanciaDaLinha($nossoNumero);
        if ($inst && !empty($inst['usuario_id'])) {
            $u = $db->table('usuarios')->select('id, assinante_id')
                ->where('id', (int) $inst['usuario_id'])->get()->getRowArray();
            if ($u) {
                return [(int) $u['id'], (int) $u['assinante_id']];
            }
        }

        // Telefone exato
        $u = $db->table('usuarios')->where('telefone_principal', $nossoNumero)->get()->getRowArray();
        if ($u) {
            return [(int) $u['id'], (int) $u['assinante_id']];
        }

        // Sufixo
        $len = strlen($nossoNumero);
        for ($take = 11; $take >= 8; $take--) {
            if ($len < $take) {
                continue;
            }
            $suf = substr($nossoNumero, -$take);
            $u = $db->query(
                "SELECT id, assinante_id
                 FROM usuarios
                 WHERE telefone_principal LIKE CONCAT('%', ?)
                 ORDER BY LENGTH(telefone_principal) DESC
                 LIMIT 1",
                [$suf]
            )->getRowArray();
            if ($u) {
                return [(int) $u['id'], (int) $u['assinante_id']];
            }
        }

        return [null, null];
    }

    /**
     * Verifica se o número pertence a alguma instância do usuário (evita eco).
     * Confere por igualdade e por sufixo de oito a onze dígitos.
     */
    private function ehNumeroDeInstanciaDoUsuario(int $usuarioId, string $numero): bool
    {
        $db  = \Config\Database::connect();
        $num = $this->soDigitos($numero);
        if ($num === '') return false;

        // Igualdade
        $q = $db->table('whatsapp_instancias')
            ->where('usuario_id', $usuarioId)
            ->where('linha_msisdn', $num)
            ->limit(1)->get()->getRowArray();
        if ($q) return true;

        // Sufixo
        $len = strlen($num);
        for ($take = 11; $take >= 8; $take--) {
            if ($len < $take) continue;
            $suf = substr($num, -$take);
            $q = $db->query(
                "SELECT id FROM whatsapp_instancias
                 WHERE usuario_id = ? AND linha_msisdn LIKE CONCAT('%', ?)
                 LIMIT 1",
                [$usuarioId, $suf]
            )->getRowArray();
            if ($q) return true;
        }
        return false;
    }

    /** N-ésima instância autenticada do usuário (índice iniciando em um). */
    private function pickNthInstanceMsisdn(int $usuarioId, int $ordem): ?string
    {
        if ($ordem <= 0) return null;
        $db = \Config\Database::connect();
        $rows = $db->table('whatsapp_instancias')
            ->where('usuario_id', $usuarioId)
            ->where('conn_status', 'authenticated')
            ->orderBy('last_status_at', 'DESC')
            ->get()->getResultArray();

        $idx = $ordem - 1;
        return isset($rows[$idx]['linha_msisdn']) ? $this->soDigitos($rows[$idx]['linha_msisdn']) : null;
    }

    /** Decide canal de envio mantendo coerência da linha. */
    private function escolherCanalEnvio(int $usuarioId, string $linhaRecebida, ?array $cfgEtapa): array
    {
        $linhaRx = $this->soDigitos($linhaRecebida);

        // Token preferido (mesma linha)
        $tokenPref = trim((string) ($cfgEtapa['instancia_preferida_token'] ?? ''));
        if ($tokenPref !== '') {
            $inst = $this->pegarInstanciaPorToken($tokenPref);
            if ($inst) {
                $linhaDoToken = $this->soDigitos((string) ($inst['linha_msisdn'] ?? ''));
                if ($linhaDoToken !== '' && $linhaDoToken === $linhaRx) {
                    return [$tokenPref, $linhaRecebida];
                }
            }
        }

        // Número preferido (mesma linha)
        $preferMsisdn = $this->soDigitos($cfgEtapa['instancia_preferida_msisdn'] ?? '');
        if ($preferMsisdn !== '' && $preferMsisdn === $linhaRx) {
            return [null, $linhaRecebida];
        }

        // Enésima instância (mesma linha)
        $preferOrdem = (int) ($cfgEtapa['instancia_preferida_ordem'] ?? 0);
        if ($preferOrdem > 0) {
            $nMsisdn = $this->pickNthInstanceMsisdn($usuarioId, $preferOrdem);
            if ($nMsisdn && $this->soDigitos($nMsisdn) === $linhaRx) {
                return [null, $linhaRecebida];
            }
        }

        return [null, $linhaRecebida];
    }

    /** Credenciais por token (linha atrelada a um access token). */
    private function pegarCredenciaisPorToken(string $token): ?array
    {
        $db = \Config\Database::connect();
        $q = $db->table('whatsapp_instancias')
            ->select('instance_id, token')
            ->where('token', $token)
            ->orderBy('id', 'DESC')
            ->limit(1)->get()->getRowArray();
        return $q ? ['instance_id' => $q['instance_id'], 'token' => $q['token']] : null;
    }

    /** Retorna a instância a partir do token. */
    private function pegarInstanciaPorToken(string $token): ?array
    {
        $db = \Config\Database::connect();
        return $db->table('whatsapp_instancias')
            ->where('token', $token)
            ->orderBy('id', 'DESC')
            ->limit(1)->get()->getRowArray() ?: null;
    }

    /**
     * Credenciais da **Meta** por número da linha (display_phone_number),
     * preferindo igualdade depois sufixo; `instance_id` = phone_number_id.
     */
    private function pegarCredenciaisMeta(int $usuarioId, string $nossoNumero): ?array
    {
        $db  = \Config\Database::connect();
        $num = $this->soDigitos($nossoNumero);

        // Igualdade
        $q = $db->table('whatsapp_instancias')
            ->where('usuario_id', $usuarioId)
            ->where('linha_msisdn', $num)
            ->limit(1)->get()->getRowArray();
        if ($q) {
            return ['instance_id' => $q['instance_id'], 'token' => $q['token']];
        }

        // Sufixo
        $len = strlen($num);
        for ($take = 11; $take >= 8; $take--) {
            if ($len < $take) continue;
            $suf = substr($num, -$take);
            $q = $db->query(
                "SELECT instance_id, token
                 FROM whatsapp_instancias
                 WHERE usuario_id = ?
                   AND linha_msisdn LIKE CONCAT('%', ?)
                 ORDER BY LENGTH(linha_msisdn) DESC
                 LIMIT 1",
                [$usuarioId, $suf]
            )->getRowArray();
            if ($q) {
                return ['instance_id' => $q['instance_id'], 'token' => $q['token']];
            }
        }

        // Última autenticada
        $q = $db->table('whatsapp_instancias')
            ->where('usuario_id', $usuarioId)
            ->where('conn_status', 'authenticated')
            ->orderBy('last_status_at', 'DESC')
            ->limit(1)->get()->getRowArray();
        if ($q) {
            return ['instance_id' => $q['instance_id'], 'token' => $q['token']];
        }

        return null;
    }

    /** Envia mensagem via **WhatsApp Cloud API**. */
    private function enviarParaWhatsapp(int $usuarioId, string $linhaEnvioMsisdn, string $numeroDestino, string $mensagem, ?string $tokenPreferido = null): bool
    {
        // 1) Descobrir credenciais (phone_number_id + access_token)
        $creds = null;
        if ($tokenPreferido) {
            $creds = $this->pegarCredenciaisPorToken($tokenPreferido);
            if (!$creds) {
                log_message('error', "Token preferido sem instância. token={$tokenPreferido}");
            }
        }
        if (!$creds) {
            $creds = $this->pegarCredenciaisMeta($usuarioId, $linhaEnvioMsisdn);
        }
        if (!$creds) {
            log_message('error', "Sem credenciais Meta (usuario={$usuarioId}, linha={$linhaEnvioMsisdn})");
            return false;
        }

        $phoneNumberId = $creds['instance_id']; // <- phone_number_id
        $accessToken   = $creds['token'];

        $url  = "https://graph.facebook.com/v20.0/{$phoneNumberId}/messages";
        $body = [
            'messaging_product' => 'whatsapp',
            'to'                => $this->soDigitos($numeroDestino),
            'type'              => 'text',
            'text'              => ['preview_url' => false, 'body' => $mensagem],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 25,
        ]);

        $result   = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        log_message('error', "Envio WhatsApp META ({$numeroDestino}) via {$phoneNumberId}: HTTP {$httpCode} - {$result}");
        if ($error) {
            log_message('error', "Erro cURL: " . $error);
        }

        return $httpCode >= 200 && $httpCode < 300;
    }

    /** Garante o preenchimento de sessoes.canal. */
    private function garantirCanalSessao(string $numero, int $usuarioId, string $canal = 'whatsapp'): void
    {
        $db = \Config\Database::connect();
        $db->query(
            "UPDATE sessoes
             SET canal = IFNULL(canal, ?)
             WHERE numero = ? AND usuario_id = ?",
            [$canal, $numero, $usuarioId]
        );
    }

    /** Atualiza a etapa da sessão de forma consistente. */
    private function moverSessaoParaEtapa(
        SessaoModel $sessaoModel,
        string $numero,
        int $usuarioId,
        string $novaEtapa,
        array $camposExtras = []
    ): void {
        $payload = array_merge([
            'etapa'            => $novaEtapa,
            'etapa_changed_at' => date('Y-m-d H:i:s'),
            'data_atualizacao' => date('Y-m-d H:i:s'),
        ], $camposExtras);

        $sessaoModel->where('numero', $numero)
            ->where('usuario_id', $usuarioId)
            ->set($payload)
            ->update();
    }

    /** Idempotência por instância e mensagem do provedor. */
    private function registrarIdempotencia(?string $providerId, string $instanciaKey): bool
    {
        if (!$providerId) return true;
        $db = \Config\Database::connect();

        try {
            $db->query(
                "INSERT IGNORE INTO webhook_msgs (instancia_key, provider_msg_id, created_at)
                 VALUES (?, ?, NOW())",
                [$instanciaKey, $providerId]
            );
            return $db->affectedRows() > 0;
        } catch (\Throwable $e1) {
            try {
                $db->query(
                    "INSERT IGNORE INTO webhook_msgs (provider_msg_id, created_at)
                     VALUES (?, NOW())",
                    [$providerId]
                );
                return $db->affectedRows() > 0;
            } catch (\Throwable $e2) {
                return true;
            }
        }
    }

    /** Último papel no feed do chat, com filtro opcional de canal. */
    private function pegarUltimoRoleChat(int $usuarioId, string $numero, ?string $canalExato = null): ?string
    {
        $db = \Config\Database::connect();
        $builder = $db->table('chat_mensagens')
            ->select('role')
            ->where('usuario_id', $usuarioId)
            ->where('numero', $numero);

        if ($canalExato !== null && $canalExato !== '') {
            $builder->where('canal', $canalExato);
        }

        $row = $builder->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
        return $row['role'] ?? null;
    }

    /** Verdadeiro se houve mensagem de humano dentro da janela. */
    private function humanoMandouRecentemente(int $usuarioId, string $numero, ?string $canalExato = null, int $segundos = 120): bool
    {
        $db = \Config\Database::connect();
        $builder = $db->table('chat_mensagens')
            ->select('created_at')
            ->where('usuario_id', $usuarioId)
            ->where('numero', $numero)
            ->where('role', 'humano');

        if ($canalExato !== null && $canalExato !== '') {
            $builder->where('canal', $canalExato);
        }

        $row = $builder->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
        if (!$row || empty($row['created_at'])) {
            return false;
        }

        $ts = strtotime((string) $row['created_at']);
        return $ts && (time() - $ts) <= $segundos;
    }

    /** Salva mensagem no feed do chat. */
    private function salvarMensagemChat(string $numero, string $role, string $texto, ?string $canal = 'whatsapp', ?int $usuarioId = null): void
    {
        $db = \Config\Database::connect();
        $db->table('chat_mensagens')->insert([
            'numero'     => $numero,
            'role'       => $role,
            'canal'      => $canal ?: 'whatsapp',
            'usuario_id' => $usuarioId,
            'texto'      => $texto,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /* ================== Lote de quinze segundos: cache e trava ================== */

    private function batchCacheKey(int $usuarioId, string $linha, string $numero): string
    {
        return "ia_batch_{$usuarioId}_{$linha}_{$numero}";
    }

    private function lockName(int $usuarioId, string $linha, string $numero): string
    {
        $name = "ia:{$usuarioId}:{$linha}:{$numero}";
        return substr($name, 0, 64);
    }

    private function acquireWorkerLock(string $name): bool
    {
        $db  = \Config\Database::connect();
        $row = $db->query("SELECT GET_LOCK(?, 0) AS l", [$name])->getRowArray();
        return isset($row['l']) && (int) $row['l'] === 1;
    }

    private function releaseWorkerLock(string $name): void
    {
        try {
            $db = \Config\Database::connect();
            $db->query("SELECT RELEASE_LOCK(?)", [$name]);
        } catch (\Throwable $e) {
            // Ignorar
        }
    }

    private function appendBatchMessage($cache, string $key, string $texto): array
    {
        $raw     = (string) ($cache->get($key) ?? '');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = ['text' => '', 'first_at' => time(), 'last_at' => 0];
        }

        $texto = trim($texto);
        if ($texto !== '') {
            $payload['text'] = ($payload['text'] !== '' ? ($payload['text'] . "\n") : '') . $texto;
        }
        $payload['last_at'] = time();

        // Tempo de vida de quinze minutos
        $cache->save($key, json_encode($payload, JSON_UNESCAPED_UNICODE), 900);
        return $payload;
    }

    private function lerBatch($cache, string $key): array
    {
        $raw     = (string) ($cache->get($key) ?? '');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = ['text' => '', 'first_at' => time(), 'last_at' => 0];
        }
        return $payload;
    }

    private function limparBatch($cache, string $key): void
    {
        $cache->delete($key);
    }

    /** Detecta mensagem sem intenção (saudação ou ruído curto) no primeiro contato. */
    private function mensagemSemIntent(string $texto): bool
    {
        $t = $this->normalizarTexto($texto);
        if ($t === '') return true;

        // Muito curta
        if (mb_strlen($t, 'UTF-8') <= 2) return true;

        // Sem letras (apenas emojis/pontuação)
        if (!preg_match('/\p{L}/u', $t)) return true;

        // Até 4 palavras e só cumprimentos comuns
        $tlimpo = preg_replace('/[^\p{L}\p{N}\s\?]/u', '', $t);
        $pal    = preg_split('/\s+/', trim((string) $tlimpo));
        $qtd    = count(array_filter($pal, fn($p) => $p !== ''));
        if ($qtd <= 4) {
            $greetings = [
                'oi','ola','olá','eai','e aí','ei','hey','hi','hello','salve',
                'boa tarde','boa noite','bom dia','tudo bem','td bem','td bom',
                'como vai','como vc está','como voce está','como você está',
            ];
            foreach ($greetings as $g) {
                if (strpos($t, $g) !== false) return true;
            }
        }
        return false;
    }

    /* ====================== Meta Webhook helpers ====================== */

    /**
     * Extrai, de um payload da Meta, os campos necessários.
     * Retorna null se não houver "messages" (ex.: apenas "statuses").
     */
    private function extrairMetaMensagem(array $json): ?array
    {
        if (!isset($json['entry']) || !is_array($json['entry'])) {
            return null;
        }

        foreach ($json['entry'] as $entry) {
            if (!isset($entry['changes']) || !is_array($entry['changes'])) continue;

            foreach ($entry['changes'] as $chg) {
                if (!isset($chg['value']) || !is_array($chg['value'])) continue;

                $val = $chg['value'];
                $metadata = $val['metadata'] ?? [];
                $phoneNumberId = (string) ($metadata['phone_number_id'] ?? '');
                $displayPhone  = $this->soDigitos((string) ($metadata['display_phone_number'] ?? ''));

                // Apenas mensagens (ignora statuses)
                if (!empty($val['messages']) && is_array($val['messages'])) {
                    $msg = $val['messages'][0];

                    $from      = $this->soDigitos((string) ($msg['from'] ?? ''));
                    $provider  = (string) ($msg['id'] ?? '');
                    $pushname  = (string) ($val['contacts'][0]['profile']['name'] ?? ($val['contacts'][0]['wa_id'] ?? 'Paciente'));

                    // Texto amigável (text / interactive)
                    $texto = '';
                    $type  = (string) ($msg['type'] ?? '');
                    if ($type === 'text') {
                        $texto = (string) ($msg['text']['body'] ?? '');
                    } elseif ($type === 'interactive') {
                        // button/text ou list_reply/title
                        if (isset($msg['interactive']['button_reply']['title'])) {
                            $texto = (string) $msg['interactive']['button_reply']['title'];
                        } elseif (isset($msg['interactive']['list_reply']['title'])) {
                            $texto = (string) $msg['interactive']['list_reply']['title'];
                        } elseif (isset($msg['interactive']['type'])) {
                            $texto = (string) $msg['interactive']['type'];
                        }
                    } elseif ($type !== '') {
                        $texto = "[{$type}]";
                    }

                    return [
                        'from'             => $from,
                        'display_phone'    => $displayPhone,
                        'phone_number_id'  => $phoneNumberId,
                        'provider_id'      => $provider,
                        'pushname'         => $pushname,
                        'texto'            => $texto,
                    ];
                }
            }
        }

        return null;
    }

    /** Nome e tratamento do profissional (Doutora ou Doutor). */
    private function pegarProfissionalNomeTratamento(int $usuarioId): array
    {
        $db  = \Config\Database::connect();

        $row = $db->table('usuarios')
            ->where('id', $usuarioId)
            ->limit(1)
            ->get()
            ->getRowArray() ?: [];

        $nome    = trim((string)($row['nome'] ?? ''));
        $genero  = strtoupper(trim((string)($row['genero'] ?? ($row['sexo'] ?? ''))));
        $tratCfg = trim((string)($row['tratamento'] ?? ''));

        if ($tratCfg !== '') {
            $trat = $tratCfg;
        } elseif ($genero === 'F') {
            $trat = 'Dra.';
        } elseif ($genero === 'M') {
            $trat = 'Dr.';
        } elseif ($nome !== '' && stripos($nome, 'dra') !== false) {
            $trat = 'Dra.';
        } else {
            $trat = 'Dra./Dr.';
        }

        $primeiro = $nome !== '' ? preg_split('/\s+/', $nome)[0] : '';
        return ['nome' => $primeiro ?: $nome, 'tratamento' => $trat];
    }

    /** Lista etapas válidas do usuário com fallbacks. */
    private function listarEtapasValidas(int $usuarioId, int $assinanteId): array
    {
        $sessaoModel = new SessaoModel();
        $validas = (array) $sessaoModel->listarEtapasUsuario($usuarioId);
        $validas = array_values(array_unique(array_filter(array_map('strval', $validas), fn($v) => trim($v) !== '')));

        if (!empty($validas)) return $validas;

        // Fallback por configurações armazenadas
        $db = \Config\Database::connect();
        $rows = $db->table('config_ia')
            ->select('etapa_atual')
            ->where('assinante_id', $assinanteId)
            ->groupBy('etapa_atual')
            ->orderBy('etapa_atual', 'ASC')
            ->get()->getResultArray();
        $validas = array_values(array_unique(array_filter(array_map(fn($r) => (string) $r['etapa_atual'], $rows), fn($v) => trim($v) !== '')));

        if (!empty($validas)) return $validas;

        // Fallback final
        return ['entrada', 'qualificacao', 'agendamento', 'pagamento', 'finalizado', 'humano'];
    }

    /* ============================ Webhook ============================ */
    public function index()
    {
        helper('ia');
        @set_time_limit(0);

        // 1) Verificação do webhook (Meta)
        if (strtolower($this->request->getMethod()) === 'get') {
            $mode      = $this->request->getGet('hub_mode')      ?? $this->request->getGet('hub.mode');
            $challenge = $this->request->getGet('hub_challenge') ?? $this->request->getGet('hub.challenge');
            $verifyTok = $this->request->getGet('hub_verify_token') ?? $this->request->getGet('hub.verify_token');

            if ($mode === 'subscribe' && $verifyTok && $verifyTok === (string) env('META_VERIFY_TOKEN')) {
                return $this->response->setStatusCode(200)->setBody((string) $challenge);
            }
            return $this->response->setStatusCode(403)->setBody('verificacao falhou');
        }

        // 2) JSON do POST
        $json = $this->request->getJSON(true);
        if (!$json) {
            return $this->respond(['ignorado' => 'payload vazio'], 200);
        }

        /* --------- META Cloud API --------- */
        $ehMeta = isset($json['object']) && $json['object'] === 'whatsapp_business_account';
        if ($ehMeta) {
            $meta = $this->extrairMetaMensagem($json);
            if (!$meta) {
                return $this->respond(['ignorado' => 'sem mensagens (provável status)'], 200);
            }

            // Números e instância
            $ehNossoEnvio = false; // mensagens recebidas não vêm como "fromMe" pela Meta
            $numero       = $meta['from'];                    // lead
            $nossoNumero  = $meta['display_phone'];           // msisdn da linha (digits)
            $mensagem     = trim((string) $meta['texto']);
            $pushname     = (string) ($meta['pushname'] ?? 'Paciente');
            $canalBase    = 'whatsapp';
            $canalLinha   = $canalBase . ':' . $nossoNumero;

            $instRow      = $this->obterInstanciaDaLinha($nossoNumero);
            $instanciaKey = $meta['phone_number_id'] ?: ($instRow['instance_id'] ?? $nossoNumero);
            $providerId   = $meta['provider_id'] ?? null;

            if ($numero === '' || $nossoNumero === '' || $mensagem === '') {
                return $this->respond(['ignorado' => 'dados incompletos'], 200);
            }

            // Idempotência
            if (!$this->registrarIdempotencia(is_string($providerId) ? $providerId : null, $instanciaKey)) {
                return $this->respond(['ignorado' => 'duplicado pela mesma instância'], 200);
            }

            // Dono
            [$usuarioId, $assinanteId] = $this->encontrarDonoPorInstanciaOuLinha($nossoNumero);
            if (!$usuarioId || !$assinanteId) {
                return $this->respond(['ignorado' => 'linha não vinculada a usuário'], 200);
            }
            if ($instRow && (int) $instRow['usuario_id'] !== (int) $usuarioId) {
                return $this->respond(['ignorado' => 'instância pertence a outro usuário'], 200);
            }

            // Continuação usa a mesma lógica já existente
            return $this->processarEntrada($usuarioId, $assinanteId, $numero, $nossoNumero, $mensagem, $pushname, $canalBase, $canalLinha, $instanciaKey);
        }

        /* --------- (Opcional) legado UltraMSG ---------
           Se ainda chegar payload antigo com "data", faz um ignore controlado. */
        if (!isset($json['data'])) {
            return $this->respond(['ignorado' => 'payload não reconhecido'], 200);
        }

        // Se quiser, você pode remover tudo abaixo. Mantive um ignore "limpo".
        return $this->respond(['ignorado' => 'evento não-meta (desativado)'], 200);
    }

    /* ============================ Núcleo do processamento ============================ */


public function verify()
{
    $mode      = $this->request->getGet('hub_mode')      ?? $this->request->getGet('hub.mode');
    $token     = $this->request->getGet('hub_verify_token') ?? $this->request->getGet('hub.verify_token');
    $challenge = $this->request->getGet('hub_challenge') ?? $this->request->getGet('hub.challenge');

    $expected = env('META_VERIFY_TOKEN'); // do .env

    if ($mode === 'subscribe' && $token && $expected && hash_equals((string)$expected, (string)$token)) {
        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', 'text/plain; charset=utf-8')
            ->setBody((string) $challenge);
    }
    return $this->response->setStatusCode(403)->setBody('Forbidden');
}



    private function processarEntrada(
        int $usuarioId,
        int $assinanteId,
        string $numero,
        string $nossoNumero,
        string $mensagem,
        string $pushname,
        string $canalBase,
        string $canalLinha,
        string $instanciaKey
    ) {
        // Modelos e cache
        $pacienteModel = new PacienteModel();
        $sessaoModel   = new SessaoModel();
        $cache         = \Config\Services::cache();

        // Não criar paciente se o número é de alguma instância do usuário
        if ($this->ehNumeroDeInstanciaDoUsuario($usuarioId, $numero)) {
            $this->salvarMensagemChat($numero, 'user', $mensagem, $canalLinha, $usuarioId);
            return $this->respond(['ignorado' => 'mensagem da própria linha ou instância'], 200);
        }

        // Paciente no escopo do usuário (inserção/atualização)
        $paciente = $pacienteModel->where('telefone', $numero)->where('usuario_id', $usuarioId)->first();
        if ($paciente) {
            $pacienteModel->update($paciente['id'], ['ultimo_contato' => date('Y-m-d H:i:s')]);
        } else {
            $pacienteModel->insert([
                'usuario_id'     => $usuarioId,
                'nome'           => $pushname,
                'telefone'       => $numero,
                'ultimo_contato' => date('Y-m-d H:i:s'),
                'origem_contato' => 1,
            ]);
            $paciente = $pacienteModel->where('telefone', $numero)->where('usuario_id', $usuarioId)->first();
        }

        // Log de entrada do paciente
        $this->salvarMensagemChat($numero, 'user', $mensagem, $canalLinha, $usuarioId);

        // Sessão única por par usuário e número
        $sessao     = $sessaoModel->getOuCriarSessao($numero, $usuarioId);
        $etapaAtual = $sessao['etapa'] ?? 'entrada';

        // Garantir canal
        $this->garantirCanalSessao($numero, $usuarioId, $canalBase);

        // Agrupamento de quinze segundos
        $batchKey = $this->batchCacheKey($usuarioId, $nossoNumero, $numero);
        $this->appendBatchMessage(\Config\Services::cache(), $batchKey, $mensagem);

        $lockName  = $this->lockName($usuarioId, $nossoNumero, $numero);
        $souWorker = $this->acquireWorkerLock($lockName);
        if (!$souWorker) {
            return $this->respond(['ok' => 'em espera para agrupar'], 200);
        }

        try {
            // Aguarda silêncio de quinze segundos
            while (true) {
                $payload1 = $this->lerBatch(\Config\Services::cache(), $batchKey);
                $lastAt   = (int) ($payload1['last_at'] ?? time());
                $sleepFor = ($lastAt + 15) - time();
                if ($sleepFor > 0) {
                    sleep(min(15, max(1, $sleepFor)));
                }
                $payload2 = $this->lerBatch(\Config\Services::cache(), $batchKey);
                if ((int) ($payload2['last_at'] ?? 0) === $lastAt) {
                    $mensagemAgregada = trim((string) ($payload2['text'] ?? $mensagem));
                    if ($mensagemAgregada === '') $mensagemAgregada = $mensagem;
                    $mensagem = $mensagemAgregada;
                    break;
                }
            }

            // Silêncio por etapa humano
            if ($etapaAtual === 'humano') {
                $historico = json_decode($sessao['historico'] ?? '[]', true) ?: [];
                $historico[] = ['role' => 'user', 'content' => $mensagem, 'linha' => $nossoNumero];

                $linhaValida = $this->validarLinhaParaSessao($usuarioId, $nossoNumero);
                $extras = [
                    'ultima_mensagem_usuario' => $mensagem,
                    'ultima_resposta_ia'      => null,
                    'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
                ];
                if ($linhaValida) $extras['linha_numero'] = $linhaValida;

                $this->moverSessaoParaEtapa($sessaoModel, $numero, $usuarioId, $etapaAtual, $extras);
                $this->limparBatch(\Config\Services::cache(), $batchKey);
                return $this->respond(['ok' => 'Inteligência Artificial silenciosa na etapa humano'], 200);
            }

            // Silêncio se humano interagiu recentemente
            if ($this->humanoMandouRecentemente($usuarioId, $numero, $canalLinha, 120)) {
                $historico = json_decode($sessao['historico'] ?? '[]', true) ?: [];
                $historico[] = ['role' => 'user', 'content' => $mensagem, 'linha' => $nossoNumero];

                $linhaValida = $this->validarLinhaParaSessao($usuarioId, $nossoNumero);
                $extras = [
                    'ultima_mensagem_usuario' => $mensagem,
                    'ultima_resposta_ia'      => null,
                    'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
                ];
                if ($linhaValida) $extras['linha_numero'] = $linhaValida;

                $this->moverSessaoParaEtapa($sessaoModel, $numero, $usuarioId, $etapaAtual, $extras);
                $this->limparBatch(\Config\Services::cache(), $batchKey);
                return $this->respond(['ok' => 'Inteligência Artificial silenciosa por atividade humana recente'], 200);
            }

            // Verifica se a etapa permite resposta automática
            $cfgEtapaAtual      = $this->obterConfigEtapa($assinanteId, $etapaAtual) ?? [];
            $iaPode             = (int) ($cfgEtapaAtual['ia_pode_responder'] ?? 1) === 1;
            $responderPermitido = $iaPode;

            // Etapas válidas
            $etapasValidas    = $this->listarEtapasValidas($usuarioId, $assinanteId);
            $etapasValidasSet = array_flip($etapasValidas);

            // Histórico completo da sessão
            $sessao = $sessaoModel->getOuCriarSessao($numero, $usuarioId); // refetch
            $historicoCompleto = json_decode($sessao['historico'] ?? '[]', true) ?: [];

            // Histórico filtrado desta linha
            $historicoFiltrado = [];
            foreach ($historicoCompleto as $h) {
                if (!is_array($h)) continue;
                $linhaMsg = (string) ($h['linha'] ?? '');
                if ($linhaMsg === '' || $linhaMsg === $nossoNumero) {
                    $r = ['role' => ($h['role'] ?? ''), 'content' => ($h['content'] ?? '')];
                    if ($linhaMsg !== '') $r['linha'] = $linhaMsg;
                    $historicoFiltrado[] = $r;
                }
            }

            // Primeiro contato com saudação simples
            $isPrimeiroTurno = count($historicoFiltrado) === 0;
            if ($isPrimeiroTurno && $this->mensagemSemIntent($mensagem)) {
                $nomeLead = $this->primeiroNome($pushname);
                $reply = $nomeLead !== ''
                    ? "oi, {$nomeLead}! tudo bem? me conta como posso te ajudar :)"
                    : "oi! tudo bem? me conta como posso te ajudar :)";

                $historicoCompleto[] = ['role' => 'user', 'content' => $mensagem, 'linha' => $nossoNumero];
                if ($responderPermitido) {
                    $historicoCompleto[] = ['role' => 'assistant', 'content' => $reply, 'linha' => $nossoNumero];
                }

                $linhaValida = $this->validarLinhaParaSessao($usuarioId, $nossoNumero);
                $extras = [
                    'ultima_mensagem_usuario' => $mensagem,
                    'historico'               => json_encode($historicoCompleto, JSON_UNESCAPED_UNICODE),
                ];
                if ($linhaValida) $extras['linha_numero'] = $linhaValida;

                $this->moverSessaoParaEtapa($sessaoModel, $numero, $usuarioId, 'entrada', $extras);

                if ($responderPermitido) {
                    [$tokenPreferido, $linhaEnvio] = $this->escolherCanalEnvio($usuarioId, $nossoNumero, null);
                    $this->enviarParaWhatsapp($usuarioId, $linhaEnvio, $numero, $reply, $tokenPreferido ?: null);
                    $this->salvarMensagemChat($numero, 'assistant', $reply, $canalBase . ':' . $linhaEnvio, $usuarioId);
                    $sessaoModel->where('numero', $numero)->where('usuario_id', $usuarioId)
                        ->set(['ultima_resposta_ia' => $reply])->update();
                } else {
                    $sessaoModel->where('numero', $numero)->where('usuario_id', $usuarioId)
                        ->set(['ultima_resposta_ia' => null])->update();
                }

                $this->limparBatch(\Config\Services::cache(), $batchKey);
                return $this->respond([
                    'status'    => 'processado',
                    'etapa'     => 'entrada',
                    'moveu'     => false,
                    'confianca' => 0.0,
                ]);
            }

            // Acrescenta a mensagem consolidada aos históricos
            $historicoFiltrado[] = ['role' => 'user', 'content' => $mensagem, 'linha' => $nossoNumero];
            $historicoCompleto[] = ['role' => 'user', 'content' => $mensagem, 'linha' => $nossoNumero];

            // Nome e tratamento do profissional vinculado à instância
            $prof = $this->pegarProfissionalNomeTratamento($usuarioId);

            // Mensagens para a Inteligência Artificial apenas desta linha
            $promptPadrao = get_prompt_padrao();
            $mensagensIA  = [['role' => 'system', 'content' => $promptPadrao]];
            foreach ($historicoFiltrado as $msg) {
                if (isset($msg['role'], $msg['content'])) {
                    $mensagensIA[] = ['role' => $msg['role'], 'content' => $msg['content']];
                }
            }

            // Chamada estruturada
            $open = new OpenrouterModel();
            $estruturada = $open->enviarMensagemEstruturada($mensagensIA, null, [
                'modelo_humano'           => true,
                'temperatura'             => 0.6,
                'estiloMocinha'           => true,
                'tom_proximo'             => true,
                'conciso'                 => true,
                'max_frases'              => 3,
                'max_chars'               => 280,
                'pergunta_unica'          => true,
                'continuityGuard'         => true,
                'assinante_id'            => $assinanteId,
                'etapa'                   => $etapaAtual,
                'max_tokens'              => 220,
                'etapas_validas'          => $etapasValidas,
                'fallback_etapas'         => ['entrada','qualificacao','agendamento','pagamento','finalizado','humano'],
                'responder_permitido'     => $responderPermitido,
                'profissional_nome'       => $prof['nome'] ?? '',
                'profissional_tratamento' => $prof['tratamento'] ?? 'Dra./Dr.',
                'linha_atual'             => $nossoNumero,
            ]);

            if (!is_array($estruturada) || !($estruturada['ok'] ?? false)) {
                // Sem resposta da IA: apenas persistir histórico
                $linhaValida = $this->validarLinhaParaSessao($usuarioId, $nossoNumero);
                $extras = [
                    'ultima_mensagem_usuario' => $mensagem,
                    'ultima_resposta_ia'      => null,
                    'historico'               => json_encode($historicoCompleto, JSON_UNESCAPED_UNICODE),
                ];
                if ($linhaValida) $extras['linha_numero'] = $linhaValida;

                $this->moverSessaoParaEtapa($sessaoModel, $numero, $usuarioId, $etapaAtual, $extras);
                $this->limparBatch(\Config\Services::cache(), $batchKey);
                return $this->respond(['ignorado' => 'IA não respondeu; sem movimentação'], 200);
            }

            $reply      = (string) ($estruturada['reply'] ?? '');
            $etapaAI    = $estruturada['etapa_sugerida'] ?? null;
            $moverAgora = (bool)   ($estruturada['mover_agora'] ?? false);
            $confianca  = (float)  ($estruturada['confianca'] ?? 0.0);

            // Política de movimentação
            $podeMover  = ($etapaAI && isset($etapasValidasSet[$etapaAI])) && ($moverAgora || $confianca >= 0.5);
            $etapaFinal = $podeMover ? $etapaAI : $etapaAtual;

            // Reativação após sete dias
            if ($reply !== '' && $responderPermitido && !empty($paciente['ultimo_contato'])) {
                $tempoUltimoContato = strtotime((string) $paciente['ultimo_contato']);
                if ($tempoUltimoContato && (time() - $tempoUltimoContato) > 604800) {
                    $reply = "Que bom te ver por aqui de novo! 😊\n" . $reply;
                }
            }

            // Atualiza histórico com a resposta
            if ($reply !== '' && $responderPermitido) {
                $historicoCompleto[] = ['role' => 'assistant', 'content' => $reply, 'linha' => $nossoNumero];
            }

            // Persistência
            $linhaValida = $this->validarLinhaParaSessao($usuarioId, $nossoNumero);
            $extras = [
                'ultima_mensagem_usuario' => $mensagem,
                'historico'               => json_encode($historicoCompleto, JSON_UNESCAPED_UNICODE),
            ];
            if ($linhaValida) $extras['linha_numero'] = $linhaValida;

            $this->moverSessaoParaEtapa($sessaoModel, $numero, $usuarioId, $etapaFinal, $extras);

            // Envio
            if ($reply !== '' && $responderPermitido) {
                [$tokenPreferido, $linhaEnvio] = $this->escolherCanalEnvio($usuarioId, $nossoNumero, null);
                $partes = $this->quebrarMensagemEmDuas($reply, 1200, 1200);
                foreach ($partes as $i => $parte) {
                    $sessaoModel->where('numero', $numero)->where('usuario_id', $usuarioId)
                        ->set(['ultima_resposta_ia' => $parte])->update();

                    $this->enviarParaWhatsapp($usuarioId, $linhaEnvio, $numero, $parte, $tokenPreferido ?: null);
                    $this->salvarMensagemChat($numero, 'assistant', $parte, $canalBase . ':' . $linhaEnvio, $usuarioId);

                    if ($i === 0 && count($partes) > 1) {
                        sleep(1);
                    }
                }
            } else {
                $sessaoModel->where('numero', $numero)->where('usuario_id', $usuarioId)
                    ->set(['ultima_resposta_ia' => null])->update();
            }

            $this->limparBatch(\Config\Services::cache(), $batchKey);

            return $this->respond([
                'status'    => 'processado',
                'etapa'     => $etapaFinal,
                'moveu'     => $podeMover,
                'confianca' => $confianca,
            ]);
        } finally {
            $this->releaseWorkerLock($lockName);
        }
    }

    /** Lê configuração de Inteligência Artificial da etapa. */
    private function obterConfigEtapa(int $assinanteId, string $etapa): ?array
    {
        $db = \Config\Database::connect();
        $row = $db->table('config_ia')
            ->where('assinante_id', $assinanteId)
            ->where('etapa_atual', trim($etapa))
            ->limit(1)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /* =================== Espaço reservado para rotinas futuras =================== */

    private function agendarNotificacaoEtapa(int $usuarioId, string $etapa, string $numeroLead, string $nomeLead): void
    {
        // Exemplo: inserir em fila para notificação posterior.
    }
}
