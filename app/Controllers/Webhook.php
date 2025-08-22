<?php

namespace App\Controllers;

use App\Models\SessaoModel;
use App\Models\OpenrouterModel;
use App\Models\PacienteModel;
use CodeIgniter\RESTful\ResourceController;

class Webhook extends ResourceController
{
    /* ====================== Utilidades ====================== */

    /** Mantém apenas dígitos. */
    private function soDigitos(?string $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor);
    }

    /**
     * UltraMSG:
     * - fromMe=false (lead => nós):  lead=data.from   | nossa linha=data.to
     * - fromMe=true  (nós   => lead): lead=data.to    | nossa linha=data.from
     */
    private function extrairNumerosDoEvento(array $data): array
    {
        $ehNossoEnvio = !empty($data['fromMe']);
        $pacienteRaw  = $ehNossoEnvio ? ($data['to'] ?? '')   : ($data['from'] ?? '');
        $nossoRaw     = $ehNossoEnvio ? ($data['from'] ?? '') : ($data['to']   ?? '');
        $numeroLead   = $this->soDigitos(explode('@', (string) $pacienteRaw)[0] ?? '');
        $nossoNumero  = $this->soDigitos(explode('@', (string) $nossoRaw)[0]     ?? '');
        return [$ehNossoEnvio, $numeroLead, $nossoNumero];
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

        // Pela instância
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

    /**
     * Verifica se o número pertence a alguma instância do usuário (evita eco).
     * Confere por igualdade e por sufixo de oito a onze dígitos.
     */
    private function ehNumeroDeInstanciaDoUsuario(int $usuarioId, string $numero): bool
    {
        $db  = \Config\Database::connect();
        $num = $this->soDigitos($numero);
        if ($num === '') {
            return false;
        }

        // Igualdade
        $q = $db->table('whatsapp_instancias')
            ->where('usuario_id', $usuarioId)
            ->where('linha_msisdn', $num)
            ->limit(1)->get()->getRowArray();
        if ($q) {
            return true;
        }

        // Sufixo
        $len = strlen($num);
        for ($take = 11; $take >= 8; $take--) {
            if ($len < $take) {
                continue;
            }
            $suf = substr($num, -$take);
            $q = $db->query(
                "SELECT id
                   FROM whatsapp_instancias
                  WHERE usuario_id = ?
                    AND linha_msisdn LIKE CONCAT('%', ?)
                  LIMIT 1",
                [$usuarioId, $suf]
            )->getRowArray();
            if ($q) {
                return true;
            }
        }
        return false;
    }

    /**
     * Valida se o msisdn existe em usuarios_numeros.numero para o usuário.
     * Retorna o número válido ou nulo.
     */
    private function validarLinhaParaSessao(int $usuarioId, string $msisdn): ?string
    {
        $db  = \Config\Database::connect();
        $num = $this->soDigitos($msisdn);
        if ($num === '') {
            return null;
        }

        $row = $db->table('usuarios_numeros')
            ->select('numero')
            ->where('usuario_id', $usuarioId)
            ->where('numero', $num)
            ->limit(1)->get()->getRowArray();

        return $row['numero'] ?? null;
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

    /** Nome e tratamento do profissional (Doutora ou Doutor). Independente de coluna existir. */
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

    /** Idempotência por instância e mensagem do provedor. */
    private function registrarIdempotencia(?string $providerId, string $instanciaKey): bool
    {
        if (!$providerId) {
            return true;
        }
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

    /** N-ésima instância autenticada do usuário (índice iniciando em um). */
    private function pickNthInstanceMsisdn(int $usuarioId, int $ordem): ?string
    {
        if ($ordem <= 0) {
            return null;
        }
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

    /** Credenciais por token. */
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

    /** Credenciais Ultra por número da linha, preferindo a linha exata. */
    private function pegarCredenciaisUltra(int $usuarioId, string $nossoNumero): ?array
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
            if ($len < $take) {
                continue;
            }
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

    /** Envia mensagem via UltraMSG. */
    private function enviarParaWhatsapp(int $usuarioId, string $linhaEnvioMsisdn, string $numeroDestino, string $mensagem, ?string $tokenPreferido = null): bool
    {
        $creds = null;
        if ($tokenPreferido) {
            $creds = $this->pegarCredenciaisPorToken($tokenPreferido);
            if (!$creds) {
                log_message('error', "Token preferido sem instância. token={$tokenPreferido}");
            }
        }
        if (!$creds) {
            $creds = $this->pegarCredenciaisUltra($usuarioId, $linhaEnvioMsisdn);
        }
        if (!$creds) {
            log_message('error', "Sem credenciais UltraMSG (usuario={$usuarioId}, linha={$linhaEnvioMsisdn})");
            return false;
        }

        $instanceId = $creds['instance_id'];
        $token      = $creds['token'];
        $url        = "https://api.ultramsg.com/{$instanceId}/messages/chat";

        $data = ['token' => $token, 'to' => $numeroDestino, 'body' => $mensagem];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_TIMEOUT        => 25,
        ]);

        $result   = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        log_message('error', "Envio WhatsApp ({$numeroDestino}) via {$instanceId}: HTTP {$httpCode} - {$result}");
        if ($error) {
            log_message('error', "Erro cURL: " . $error);
        }

        return $httpCode >= 200 && $httpCode < 300;
    }

    /** Quebra a mensagem em até duas partes com contexto. */
    private function quebrarMensagemEmDuas(string $texto, int $limiteParte1 = 1200, int $limiteParte2 = 1200): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return [];
        }
        if (mb_strlen($texto, 'UTF-8') <= $limiteParte1) {
            return [$texto];
        }

        $p1    = mb_substr($texto, 0, $limiteParte1, 'UTF-8');
        $resto = mb_substr($texto, mb_strlen($p1, 'UTF-8'), null, 'UTF-8');

        $pos = max(mb_strrpos($p1, "\n\n", 0, 'UTF-8') ?: -1, mb_strrpos($p1, "\r\n\r\n", 0, 'UTF-8') ?: -1);
        if ($pos > 200) {
            $p1    = mb_substr($p1, 0, $pos, 'UTF-8');
            $resto = mb_substr($texto, mb_strlen($p1, 'UTF-8'), null, 'UTF-8');
        } else {
            $cands    = ['. ', '! ', '? ', ".\n", "!\n", "?\n"];
            $posFrase = -1;
            foreach ($cands as $c) {
                $p = mb_strrpos($p1, $c, 0, 'UTF-8');
                if ($p !== false) {
                    $posFrase = max($posFrase, $p + 1);
                }
            }
            if ($posFrase > 200) {
                $p1    = mb_substr($p1, 0, $posFrase + 1, 'UTF-8');
                $resto = mb_substr($texto, mb_strlen($p1, 'UTF-8'), null, 'UTF-8');
            } else {
                $p = mb_strrpos($p1, ' ', 0, 'UTF-8');
                if ($p !== false && $p > 200) {
                    $p1    = mb_substr($p1, 0, $p, 'UTF-8');
                    $resto = mb_substr($texto, mb_strlen($p1, 'UTF-8'), null, 'UTF-8');
                }
            }
        }

        $p1    = trim($p1);
        $resto = ltrim($resto);

        $recapLen = 160;
        $recap    = mb_substr($p1, max(0, mb_strlen($p1, 'UTF-8') - $recapLen), $recapLen, 'UTF-8');
        $recap    = preg_replace('/\s+/u', ' ', $recap);

        $cabecaP2    = "(2/2) Continuação\nContexto anterior: “{$recap}”\n\n";
        $limiteP2Util = max(0, $limiteParte2 - mb_strlen($cabecaP2, 'UTF-8'));
        $p2           = $cabecaP2 . mb_substr($resto, 0, $limiteP2Util, 'UTF-8');

        $rodapeP1     = "\n\n(1/2) Continuação na próxima mensagem";
        $limiteP1Util = max(0, $limiteParte1 - mb_strlen($rodapeP1, 'UTF-8'));
        if (mb_strlen($p1, 'UTF-8') > $limiteP1Util) {
            $p1 = mb_substr($p1, 0, $limiteP1Util, 'UTF-8');
        }
        $p1 .= $rodapeP1;

        return [$p1, trim($p2)];
    }

    /** Lista etapas válidas do usuário com fallbacks. */
    private function listarEtapasValidas(int $usuarioId, int $assinanteId): array
    {
        $sessaoModel = new SessaoModel();
        $validas = (array) $sessaoModel->listarEtapasUsuario($usuarioId);
        $validas = array_values(array_unique(array_filter(array_map('strval', $validas), fn($v) => trim($v) !== '')));

        if (!empty($validas)) {
            return $validas;
        }

        // Fallback por configurações armazenadas
        $db = \Config\Database::connect();
        $rows = $db->table('config_ia')
            ->select('etapa_atual')
            ->where('assinante_id', $assinanteId)
            ->groupBy('etapa_atual')
            ->orderBy('etapa_atual', 'ASC')
            ->get()->getResultArray();
        $validas = array_values(array_unique(array_filter(array_map(fn($r) => (string) $r['etapa_atual'], $rows), fn($v) => trim($v) !== '')));

        if (!empty($validas)) {
            return $validas;
        }

        // Fallback final
        return ['entrada', 'qualificacao', 'agendamento', 'pagamento', 'finalizado', 'humano'];
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
        if ($t === '') {
            return true;
        }

        // Muito curta
        if (mb_strlen($t, 'UTF-8') <= 2) {
            return true;
        }

        // Sem letras (apenas emojis ou pontuação)
        if (!preg_match('/\p{L}/u', $t)) {
            return true;
        }

        // Até quatro palavras e apenas cumprimentos comuns
        $tlimpo = preg_replace('/[^\p{L}\p{N}\s\?]/u', '', $t);
        $pal    = preg_split('/\s+/', trim((string) $tlimpo));
        $qtd    = count(array_filter($pal, fn($p) => $p !== ''));
        if ($qtd <= 4) {
            $greetings = [
                'oi', 'ola', 'olá', 'eai', 'e aí', 'ei', 'hey', 'hi', 'hello', 'salve',
                'boa tarde', 'boa noite', 'bom dia', 'boa tarde!', 'boa noite!', 'bom dia!',
                'tudo bem', 'td bem', 'td bom', 'como vai', 'como vc está', 'como voce está', 'como você está',
            ];
            foreach ($greetings as $g) {
                if (strpos($t, $g) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /** ============================ Webhook ============================ */
    public function index()
    {
        helper('ia');
        @set_time_limit(0);

        $json = $this->request->getJSON(true);
        if (!isset($json['data'])) {
            return $this->respond(['ignorado' => 'payload inválido'], 200);
        }
        $data = $json['data'];

        // Números e instância
        [$ehNossoEnvio, $numero, $nossoNumero] = $this->extrairNumerosDoEvento($data);
        $mensagem   = trim((string) ($data['body'] ?? ''));
        $pushname   = (string) ($data['pushname'] ?? 'Paciente');
        $canalBase  = 'whatsapp';
        $canalLinha = $canalBase . ':' . $nossoNumero;

        $instanceIdPayload = (string) ($data['instanceId'] ?? $data['instance'] ?? '');
        $instRow           = $this->obterInstanciaDaLinha($nossoNumero);
        $instanciaKey      = $instRow['instance_id'] ?? ($instanceIdPayload ?: $nossoNumero);

        if ($numero === '' || $nossoNumero === '' || $mensagem === '') {
            return $this->respond(['ignorado' => 'dados incompletos'], 200);
        }

        // Idempotência
        $providerId = $data['id'] ?? ($data['messageId'] ?? ($data['message_id'] ?? null));
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

        // Modelos e cache
        $pacienteModel = new PacienteModel();
        $sessaoModel   = new SessaoModel();
        $cache         = \Config\Services::cache();

        /* ===================== Saída: mensagens enviadas por nós ===================== */
        if ($ehNossoEnvio) {
            $sessao = $sessaoModel->getOuCriarSessao($numero, $usuarioId);
            $ultimaRespostaIa = $sessao['ultima_resposta_ia'] ?? null;

            // Eco de resposta da Inteligência Artificial
            if ($ultimaRespostaIa && trim($ultimaRespostaIa) === $mensagem) {
                $this->salvarMensagemChat($numero, 'assistant', $mensagem, $canalLinha, $usuarioId);
                return $this->respond(['ignorado' => 'eco da própria IA (registrado no chat)'], 200);
            }

            // Humano assumiu: Inteligência Artificial em pausa
            $extras = ['ultima_resposta_ia' => null];
            $linhaValida = $this->validarLinhaParaSessao($usuarioId, $nossoNumero);
            if ($linhaValida) {
                $extras['linha_numero'] = $linhaValida;
            }

            $this->moverSessaoParaEtapa($sessaoModel, $numero, $usuarioId, 'humano', $extras);
            $this->salvarMensagemChat($numero, 'humano', $mensagem, $canalLinha, $usuarioId);
            return $this->respond(['ok' => 'humano assumiu; Inteligência Artificial pausada até alterarem a etapa'], 200);
        }

        /* ===================== Entrada: mensagens recebidas do lead ===================== */

        // Não criar paciente se o número é de alguma instância do usuário
        if ($this->ehNumeroDeInstanciaDoUsuario($usuarioId, $numero)) {
            $this->salvarMensagemChat($numero, 'user', $mensagem, $canalLinha, $usuarioId);
            return $this->respond(['ignorado' => 'mensagem da própria linha ou instância'], 200);
        }

        // Paciente no escopo do usuário (inserção ou atualização)
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
        $this->appendBatchMessage($cache, $batchKey, $mensagem);

        $lockName = $this->lockName($usuarioId, $nossoNumero, $numero);
        $souWorker = $this->acquireWorkerLock($lockName);

        if (!$souWorker) {
            return $this->respond(['ok' => 'em espera para agrupar'], 200);
        }

        try {
            // Aguarda silêncio de quinze segundos
            while (true) {
                $payload1 = $this->lerBatch($cache, $batchKey);
                $lastAt   = (int) ($payload1['last_at'] ?? time());
                $sleepFor = ($lastAt + 15) - time();
                if ($sleepFor > 0) {
                    sleep(min(15, max(1, $sleepFor)));
                }
                $payload2 = $this->lerBatch($cache, $batchKey);
                if ((int) ($payload2['last_at'] ?? 0) === $lastAt) {
                    $mensagemAgregada = trim((string) ($payload2['text'] ?? $mensagem));
                    if ($mensagemAgregada === '') {
                        $mensagemAgregada = $mensagem;
                    }
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
                if ($linhaValida) {
                    $extras['linha_numero'] = $linhaValida;
                }

                $this->moverSessaoParaEtapa($sessaoModel, $numero, $usuarioId, $etapaAtual, $extras);
                $this->limparBatch($cache, $batchKey);
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
                if ($linhaValida) {
                    $extras['linha_numero'] = $linhaValida;
                }

                $this->moverSessaoParaEtapa($sessaoModel, $numero, $usuarioId, $etapaAtual, $extras);
                $this->limparBatch($cache, $batchKey);
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
            $historicoCompleto = json_decode($sessao['historico'] ?? '[]', true) ?: [];

            // Histórico filtrado desta linha
            $historicoFiltrado = [];
            foreach ($historicoCompleto as $h) {
                if (!is_array($h)) {
                    continue;
                }
                $linhaMsg = (string) ($h['linha'] ?? '');
                if ($linhaMsg === '' || $linhaMsg === $nossoNumero) {
                    $r = ['role' => ($h['role'] ?? ''), 'content' => ($h['content'] ?? '')];
                    if ($linhaMsg !== '') {
                        $r['linha'] = $linhaMsg;
                    }
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
                if ($linhaValida) {
                    $extras['linha_numero'] = $linhaValida;
                }

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

                $this->limparBatch($cache, $batchKey);
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
                'fallback_etapas'         => ['entrada', 'qualificacao', 'agendamento', 'pagamento', 'finalizado', 'humano'],
                'responder_permitido'     => $responderPermitido,
                'profissional_nome'       => $prof['nome'] ?? '',
                'profissional_tratamento' => $prof['tratamento'] ?? 'Dra./Dr.',
                'linha_atual'             => $nossoNumero,
            ]);

            if (!is_array($estruturada) || !($estruturada['ok'] ?? false)) {
                // Sem resposta da Inteligência Artificial: apenas persistir histórico
                $linhaValida = $this->validarLinhaParaSessao($usuarioId, $nossoNumero);
                $extras = [
                    'ultima_mensagem_usuario' => $mensagem,
                    'ultima_resposta_ia'      => null,
                    'historico'               => json_encode($historicoCompleto, JSON_UNESCAPED_UNICODE),
                ];
                if ($linhaValida) {
                    $extras['linha_numero'] = $linhaValida;
                }

                $this->moverSessaoParaEtapa($sessaoModel, $numero, $usuarioId, $etapaAtual, $extras);
                $this->limparBatch($cache, $batchKey);
                return $this->respond(['ignorado' => 'Inteligência Artificial não respondeu; sem movimentação'], 200);
            }

            $reply      = (string) ($estruturada['reply'] ?? '');
            $etapaAI    = $estruturada['etapa_sugerida'] ?? null;
            $moverAgora = (bool) ($estruturada['mover_agora'] ?? false);
            $confianca  = (float) ($estruturada['confianca'] ?? 0.0);

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
            if ($linhaValida) {
                $extras['linha_numero'] = $linhaValida;
            }

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

            $this->limparBatch($cache, $batchKey);

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
