<?php
namespace App\Controllers;

use App\Models\SessaoModel;
use App\Models\OpenrouterModel;
use App\Models\PacienteModel;
use CodeIgniter\RESTful\ResourceController;

class Webhook extends ResourceController
{
    /* ====================== Utils gerais ====================== */

    private function soDigitos(?string $v): string
    {
        return preg_replace('/\D+/', '', (string) $v);
    }

    /** JSON tolerante */
    private function safeJsonBody(): array
    {
        try {
            $j = $this->request->getJSON(true);
            if (is_array($j)) return $j;
        } catch (\Throwable $e) {}
        $raw = (string) $this->request->getBody();
        if ($raw !== '') {
            $arr = json_decode($raw, true);
            if (is_array($arr)) return $arr;
        }
        $post = $this->request->getPost();
        return is_array($post) ? $post : [];
    }

    /** Instância pela linha (flexível a sufixos) */
    private function obterInstanciaDaLinha(string $nossoNumero): ?array
    {
        $db  = \Config\Database::connect();
        $num = $this->soDigitos($nossoNumero);

        // exato
        $row = $db->table('whatsapp_instancias')
            ->where('linha_msisdn', $num)
            ->orderBy('last_status_at', 'DESC')
            ->limit(1)->get()->getRowArray();
        if ($row) return $row;

        // sufixos
        if (strlen($num) >= 12) {
            $r1 = substr($num, 0, -1);
            $row = $db->table('whatsapp_instancias')->where('linha_msisdn', $r1)->limit(1)->get()->getRowArray();
            if ($row) return $row;

            if (strlen($num) >= 13) {
                $r2 = substr($num, 0, -2);
                $row = $db->table('whatsapp_instancias')->where('linha_msisdn', $r2)->limit(1)->get()->getRowArray();
                if ($row) return $row;
            }
        }

        // sufixo parcial
        for ($take = 12; $take >= 9; $take--) {
            if (strlen($num) < $take) continue;
            $suf = substr($num, -$take);
            $row = $db->query(
                "SELECT * FROM whatsapp_instancias
                  WHERE linha_msisdn LIKE CONCAT('%', ?)
               ORDER BY LENGTH(linha_msisdn) DESC, last_status_at DESC
                  LIMIT 1",
                [$suf]
            )->getRowArray();
            if ($row) return $row;
        }

        return null;
    }

    /** Resolve dono (usuario/assinante) pela linha/SID */
    private function encontrarDono(string $nossoNumero, ?string $sid = null): array
    {
        $db   = \Config\Database::connect();

        $inst = $this->obterInstanciaDaLinha($nossoNumero);
        if ($inst && !empty($inst['usuario_id'])) {
            $u = $db->table('usuarios')->select('id, assinante_id')
                ->where('id', (int) $inst['usuario_id'])->get()->getRowArray();
            if ($u) return [(int) $u['id'], (int) $u['assinante_id']];
        }

        if ($sid) {
            $instBySid = $db->table('whatsapp_instancias')->where('sid', $sid)->get()->getRowArray();
            if ($instBySid && !empty($instBySid['usuario_id'])) {
                $usuarioId = (int) $instBySid['usuario_id'];
                $u = $db->table('usuarios')->select('assinante_id')->where('id', $usuarioId)->get()->getRowArray();
                if ($u) return [$usuarioId, (int) $u['assinante_id']];
            }
        }

        // fallback por telefone principal do usuário
        $num = $this->soDigitos($nossoNumero);
        $u = $db->table('usuarios')->where('telefone_principal', $num)->get()->getRowArray();
        if ($u) return [(int) $u['id'], (int) $u['assinante_id']];

        $len = strlen($num);
        for ($take = 11; $take >= 8; $take--) {
            if ($len < $take) continue;
            $suf = substr($num, -$take);
            $u = $db->query(
                "SELECT id, assinante_id FROM usuarios
                  WHERE telefone_principal LIKE CONCAT('%', ?)
              ORDER BY LENGTH(telefone_principal) DESC
                 LIMIT 1",
                [$suf]
            )->getRowArray();
            if ($u) return [(int) $u['id'], (int) $u['assinante_id']];
        }

        return [null, null];
    }

    /** evita eco: número pertence a alguma instância do usuário */
    private function ehNumeroDeInstanciaDoUsuario(int $usuarioId, string $numero): bool
    {
        $db  = \Config\Database::connect();
        $num = $this->soDigitos($numero);

        $q = $db->table('whatsapp_instancias')
            ->where('usuario_id', $usuarioId)
            ->where('linha_msisdn', $num)
            ->limit(1)->get()->getRowArray();
        if ($q) return true;

        $len = strlen($num);
        for ($take = 11; $take >= 8; $take--) {
            if ($len < $take) continue;
            $suf = substr($num, -$take);
            $q = $db->query(
                "SELECT id FROM whatsapp_instancias
                  WHERE usuario_id = ?
                    AND linha_msisdn LIKE CONCAT('%', ?)
                 LIMIT 1",
                [$usuarioId, $suf]
            )->getRowArray();
            if ($q) return true;
        }
        return false;
    }

    /** idempotência */
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

    /** garante canal na sessão */
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

    /** ========= Etapas válidas (DR) ========= */

    private function etapasValidasDoAssinante(int $assinanteId): array
    {
        $db   = \Config\Database::connect();
        $rows = $db->table('config_ia')
            ->select('etapa_atual')
            ->where('assinante_id', $assinanteId)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $ok = [];
        foreach ($rows as $r) {
            $e = trim((string)($r['etapa_atual'] ?? ''));
            if ($e !== '') $ok[$e] = true;
        }
        return array_keys($ok);
    }

    /**
     * Retorna uma etapa válida:
     * - se $desejada é válida, retorna;
     * - senão, retorna $fallbackSeValido se for válido;
     * - senão, retorna primeira válida;
     * - se não houver válidas, retorna null.
     */
    private function coerceEtapaValidaAssinante(int $assinanteId, ?string $desejada, ?string $fallbackSeValido = null): ?string
    {
        $validas = $this->etapasValidasDoAssinante($assinanteId);
        if (empty($validas)) return null;

        $d = trim((string)($desejada ?? ''));
        if ($d !== '' && in_array($d, $validas, true)) return $d;

        $fb = trim((string)($fallbackSeValido ?? ''));
        if ($fb !== '' && in_array($fb, $validas, true)) return $fb;

        return $validas[0] ?? null;
    }

    /** FK de linha_numero -> usuarios_numeros(numero) */
    private function linhaExisteEmUsuariosNumeros(string $numero): bool
    {
        $num = $this->soDigitos($numero);
        if ($num === '') return false;
        $db = \Config\Database::connect();
        $row = $db->table('usuarios_numeros')->select('numero')->where('numero', $num)->limit(1)->get()->getRowArray();
        return (bool) $row;
    }

    /**
     * Atualiza sessão com proteção:
     * - `novaEtapa` pode ser null: não altera etapa
     * - `linha_numero` só grava se existir em usuarios_numeros
     */
    private function moverSessaoParaEtapa(
        SessaoModel $sessaoModel,
        string $numero,
        int $usuarioId,
        ?string $novaEtapa,
        array $camposExtras = []
    ): void {
        $payload = [
            'data_atualizacao' => date('Y-m-d H:i:s'),
        ];

        if ($novaEtapa !== null && $novaEtapa !== '') {
            $payload['etapa']            = $novaEtapa;
            $payload['etapa_changed_at'] = date('Y-m-d H:i:s');
        }

        foreach ($camposExtras as $k => $v) {
            $payload[$k] = $v;
        }

        if (array_key_exists('linha_numero', $payload)) {
            $ln = $this->soDigitos((string) $payload['linha_numero']);
            if ($ln === '' || !$this->linhaExisteEmUsuariosNumeros($ln)) {
                unset($payload['linha_numero']); // evita erro de FK
            } else {
                $payload['linha_numero'] = $ln;
            }
        }

        $sessaoModel->where('numero', $numero)
            ->where('usuario_id', $usuarioId)
            ->set($payload)
            ->update();
    }

    /** Credenciais do Gateway (SID) pela linha */
    private function pegarCredenciaisGateway(int $usuarioId, string $nossoNumero): ?array
    {
        $db  = \Config\Database::connect();
        $num = $this->soDigitos($nossoNumero);

        $q = $db->table('whatsapp_instancias')
            ->select('sid')
            ->where('usuario_id', $usuarioId)
            ->where('linha_msisdn', $num)
            ->limit(1)->get()->getRowArray();
        if ($q && !empty($q['sid'])) return ['sid' => (string) $q['sid']];

        $len = strlen($num);
        for ($take = 11; $take >= 8; $take--) {
            if ($len < $take) continue;
            $suf = substr($num, -$take);
            $q = $db->query(
                "SELECT sid FROM whatsapp_instancias
                  WHERE usuario_id = ?
                    AND linha_msisdn LIKE CONCAT('%', ?)
               ORDER BY LENGTH(linha_msisdn) DESC
                 LIMIT 1",
                [$usuarioId, $suf]
            )->getRowArray();
            if ($q && !empty($q['sid'])) return ['sid' => (string) $q['sid']];
        }

        $q = $db->table('whatsapp_instancias')
            ->select('sid')
            ->where('usuario_id', $usuarioId)
            ->where('conn_status', 'connected')
            ->orderBy('last_status_at', 'DESC')
            ->limit(1)->get()->getRowArray();
        if ($q && !empty($q['sid'])) return ['sid' => (string) $q['sid']];

        return null;
    }

    /** Envio via Gateway */
    private function enviarParaWhatsapp(int $usuarioId, string $linhaEnvioMsisdn, string $numeroDestino, string $mensagem): bool
    {
        $creds = $this->pegarCredenciaisGateway($usuarioId, $linhaEnvioMsisdn);
        if (!$creds || empty($creds['sid'])) {
            log_message('error', "Sem SID do Gateway (usuario={$usuarioId}, linha={$linhaEnvioMsisdn})");
            return false;
        }

        $sid = $creds['sid'];
        $url = rtrim((string) env('GATEWAY_URL'),'/')."/session/{$sid}/send";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . env('GATEWAY_KEY'),
                'Content-Type: application/json',
            ],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'to'   => $this->soDigitos($numeroDestino),
                'text' => $mensagem,
            ], JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 25,
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) log_message('error', "Erro cURL Gateway: " . $error);
        log_message('info', "Envio WA ({$numeroDestino}) via SID {$sid}: HTTP {$httpCode} - {$result}");

        return $httpCode >= 200 && $httpCode < 300;
    }

    /** Saudações curtas? */
    private function mensagemSemIntent(string $texto): bool
    {
        $t = mb_strtolower(preg_replace('/\s+/u', ' ', trim($texto)), 'UTF-8');
        if ($t === '') return true;
        if (mb_strlen($t, 'UTF-8') <= 2) return true;
        if (!preg_match('/\p{L}/u', $t)) return true;

        $tlimpo = preg_replace('/[^\p{L}\p{N}\s\?]/u', '', $t);
        $pal  = preg_split('/\s+/', trim((string) $tlimpo));
        $qtd  = count(array_filter($pal));
        if ($qtd <= 4) {
            $g = ['oi','ola','olá','eai','e aí','ei','hey','hi','hello','salve',
                  'boa tarde','boa noite','bom dia','tudo bem','td bem','td bom','como vai'];
            foreach ($g as $x) {
                if (strpos($t, $x) !== false) return true;
            }
        }
        return false;
    }

    private function quebrarMensagemEmDuas(string $texto, int $lim1 = 1200, int $lim2 = 1200): array
    {
        $texto = trim($texto);
        if ($texto === '') return [];
        if (mb_strlen($texto, 'UTF-8') <= $lim1) return [$texto];

        $p1    = mb_substr($texto, 0, $lim1, 'UTF-8');
        $resto = mb_substr($texto, mb_strlen($p1, 'UTF-8'), null, 'UTF-8');

        $pos = max(mb_strrpos($p1, "\n\n", 0, 'UTF-8') ?: -1, mb_strrpos($p1, ". ", 0, 'UTF-8') ?: -1);
        if ($pos > 200) {
            $p1    = mb_substr($p1, 0, $pos, 'UTF-8');
            $resto = mb_substr($texto, mb_strlen($p1, 'UTF-8'), null, 'UTF-8');
        }

        $p1 = trim($p1) . "\n\n(1/2) Continuação na próxima mensagem";
        $recap  = mb_substr($p1, max(0, mb_strlen($p1, 'UTF-8') - 160), 160, 'UTF-8');
        $cabeca = "(2/2) Continuação\nContexto anterior: “{$recap}”\n\n";
        $p2     = $cabeca . mb_substr(trim($resto), 0, max(0, $lim2 - mb_strlen($cabeca, 'UTF-8')), 'UTF-8');

        return [$p1, trim($p2)];
    }

    /* ============================ Webhook ============================ */

    public function index()
    {
        helper('ia');
        @set_time_limit(0);

        // segurança
        $key = $this->request->getHeaderLine('x-api-key');
        if ($key !== (string) env('GATEWAY_KEY')) {
            return $this->respond(['ok'=>false,'err'=>'unauthorized'], 401);
        }

        $json = $this->safeJsonBody();
        if (!is_array($json) || empty($json)) {
            return $this->respond(['ignorado' => 'payload vazio'], 200);
        }

        $type = (string) ($json['type'] ?? '');

        /* ===== evento de CONEXÃO ===== */
        if ($type === 'connection') {
            $sid    = (string) ($json['sid'] ?? '');
            $status = (string) ($json['status'] ?? 'connected');
            $msisdn = $this->soDigitos($json['msisdn'] ?? '');

            if ($sid === '') {
                return $this->respond(['ok'=>false,'err'=>'sid ausente'], 422);
            }

            $db  = \Config\Database::connect();
            $upd = [
                'conn_status'    => $status,
                'last_status_at' => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ];
            if ($msisdn !== '') $upd['linha_msisdn'] = $msisdn;

            $db->table('whatsapp_instancias')->where('sid', $sid)->update($upd);

            return $this->respond(['ok'=>true], 200);
        }

        /* ===== mensagem (inbound/outbound) ===== */
        if ($type === 'message' || (isset($json['sid']) && (isset($json['text']) || isset($json['message']) || isset($json['body'])))) {

            $sid        = (string) $json['sid'];
            $fromMe     = (bool)   ($json['fromMe'] ?? false);
            $leadNumRaw = $fromMe ? ($json['to']   ?? '') : ($json['from'] ?? '');
            $lineNumRaw = $fromMe ? ($json['from'] ?? '') : ($json['to']   ?? '');
            $numero     = $this->soDigitos($leadNumRaw);   // lead
            $nossoNumero= $this->soDigitos($lineNumRaw);   // nossa linha
            $mensagem   = trim((string) ($json['text'] ?? $json['message'] ?? $json['body'] ?? ''));
            $pushname   = (string) ($json['pushname'] ?? 'Paciente');
            $providerId = (string) ($json['id'] ?? $json['messageId'] ?? '');

            if ($numero === '' || $nossoNumero === '' || $mensagem === '') {
                return $this->respond(['ignorado' => 'dados incompletos (gateway)'], 200);
            }

            if (!$this->registrarIdempotencia($providerId ?: null, $sid)) {
                return $this->respond(['ignorado' => 'duplicado (gateway)'], 200);
            }

            // Dono (por linha, com fallback por SID)
            [$usuarioId, $assinanteId] = $this->encontrarDono($nossoNumero, $sid);
            if (!$usuarioId || !$assinanteId) {
                return $this->respond(['ignorado' => 'linha sem dono'], 200);
            }

            $canalBase  = 'whatsapp';
            $canalLinha = $canalBase . ':' . $nossoNumero;

            $pacienteModel = new PacienteModel();
            $sessaoModel   = new SessaoModel();

            /* ===== outbound (fromMe=true) ===== */
            if ($fromMe) {
                $sessao = $sessaoModel->getOuCriarSessao($numero, $usuarioId);
                $ultimaRespostaIa = $sessao['ultima_resposta_ia'] ?? null;

                if ($ultimaRespostaIa && trim($ultimaRespostaIa) === $mensagem) {
                    $this->salvarMensagemChat($numero, 'assistant', $mensagem, $canalLinha, $usuarioId);
                    return $this->respond(['ok' => 'eco da IA registrado'], 200);
                }

                $extras = ['ultima_resposta_ia' => null, 'data_atualizacao' => date('Y-m-d H:i:s')];
                // etapa 'humano' só se for válida; senão, não muda etapa
                $etapaHumano = $this->coerceEtapaValidaAssinante($assinanteId, 'humano', null);
                $this->moverSessaoParaEtapa($sessaoModel, $numero, $usuarioId, $etapaHumano, $extras);
                $this->salvarMensagemChat($numero, 'humano', $mensagem, $canalLinha, $usuarioId);
                return $this->respond(['ok' => 'humano assumiu; IA pausada'], 200);
            }

            /* ===== inbound (lead) ===== */

            if ($this->ehNumeroDeInstanciaDoUsuario($usuarioId, $numero)) {
                $this->salvarMensagemChat($numero, 'user', $mensagem, $canalLinha, $usuarioId);
                return $this->respond(['ignorado' => 'mensagem da própria linha/instância'], 200);
            }

            // Upsert paciente
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

            // Log no feed
            $this->salvarMensagemChat($numero, 'user', $mensagem, $canalLinha, $usuarioId);

            // Sessão
            $sessao     = $sessaoModel->getOuCriarSessao($numero, $usuarioId);
            $etapaAtual = (string) ($sessao['etapa'] ?? '');

            // Garante canal
            $this->garantirCanalSessao($numero, $usuarioId, $canalBase);

            // Silenciar se humano falou recentemente
            if ($this->humanoMandouRecentemente($usuarioId, $numero, $canalLinha, 120)) {
                $historico = json_decode($sessao['historico'] ?? '[]', true) ?: [];
                $historico[] = ['role' => 'user', 'content' => $mensagem, 'linha' => $nossoNumero];

                $this->moverSessaoParaEtapa($sessaoModel, $numero, $usuarioId, null, [
                    'ultima_mensagem_usuario' => $mensagem,
                    'ultima_resposta_ia'      => null,
                    'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
                ]);
                return $this->respond(['ok' => 'IA silenciosa por atividade humana recente'], 200);
            }

            // Histórico (apenas desta linha)
            $historicoCompleto = json_decode($sessao['historico'] ?? '[]', true) ?: [];
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

            // Primeiro contato: saudação simples (etapa válida!)
            $isPrimeiroTurno = count($historicoFiltrado) === 0;
            if ($isPrimeiroTurno && $this->mensagemSemIntent($mensagem)) {
                $reply = "oi! tudo bem? me conta como posso te ajudar :)";

                $historicoCompleto[] = ['role' => 'user', 'content' => $mensagem, 'linha' => $nossoNumero];

                $etapaNova = $this->coerceEtapaValidaAssinante($assinanteId, 'entrada', $etapaAtual ?: null); // só válida
                if ($reply !== '') {
                    $historicoCompleto[] = ['role' => 'assistant', 'content' => $reply, 'linha' => $nossoNumero];
                }

                $this->moverSessaoParaEtapa($sessaoModel, $numero, $usuarioId, $etapaNova, [
                    'ultima_mensagem_usuario' => $mensagem,
                    'linha_numero'            => $nossoNumero,
                    'historico'               => json_encode($historicoCompleto, JSON_UNESCAPED_UNICODE),
                ]);

                if ($reply !== '') {
                    $this->enviarParaWhatsapp($usuarioId, $nossoNumero, $numero, $reply);
                    $this->salvarMensagemChat($numero, 'assistant', $reply, $canalLinha, $usuarioId);
                    $sessaoModel->where('numero', $numero)->where('usuario_id', $usuarioId)
                                ->set(['ultima_resposta_ia' => $reply])->update();
                } else {
                    $sessaoModel->where('numero', $numero)->where('usuario_id', $usuarioId)
                                ->set(['ultima_resposta_ia' => null])->update();
                }

                return $this->respond([
                    'status' => 'processado',
                    'etapa'  => $etapaNova ?? $etapaAtual,
                    'moveu'  => $etapaNova && $etapaNova !== $etapaAtual,
                ]);
            }

            // IA
            $historicoFiltrado[] = ['role' => 'user', 'content' => $mensagem, 'linha' => $nossoNumero];
            $historicoCompleto[] = ['role' => 'user', 'content' => $mensagem, 'linha' => $nossoNumero];

            $promptPadrao = get_prompt_padrao();
            $mensagensIA  = [['role' => 'system', 'content' => $promptPadrao]];
            foreach ($historicoFiltrado as $msg) {
                if (isset($msg['role'], $msg['content'])) {
                    $mensagensIA[] = ['role' => $msg['role'], 'content' => $msg['content']];
                }
            }

            $open = new OpenrouterModel();
            $estruturada = $open->enviarMensagemEstruturada($mensagensIA, null, [
                'modelo_humano'       => true,
                'temperatura'         => 0.6,
                'conciso'             => true,
                'max_frases'          => 3,
                'max_chars'           => 280,
                'pergunta_unica'      => true,
                'continuityGuard'     => true,
                'assinante_id'        => $assinanteId,
                'etapa'               => $etapaAtual,
                'max_tokens'          => 220,
                'responder_permitido' => true,
                'linha_atual'         => $nossoNumero,
            ]);

            if (!is_array($estruturada) || !($estruturada['ok'] ?? false)) {
                $this->moverSessaoParaEtapa($sessaoModel, $numero, $usuarioId, null, [
                    'ultima_mensagem_usuario' => $mensagem,
                    'ultima_resposta_ia'      => null,
                    'linha_numero'            => $nossoNumero,
                    'historico'               => json_encode($historicoCompleto, JSON_UNESCAPED_UNICODE),
                ]);
                return $this->respond(['ignorado' => 'IA não respondeu'], 200);
            }

            $reply      = (string) ($estruturada['reply'] ?? '');
            $etapaAI    = $estruturada['etapa_sugerida'] ?? null;
            $moverAgora = (bool)   ($estruturada['mover_agora'] ?? false);
            $confianca  = (float)  ($estruturada['confianca'] ?? 0.0);

            // Decide etapa final sempre válida
            $desejada = $moverAgora || $confianca >= 0.5 ? $etapaAI : $etapaAtual;
            $etapaFinal = $this->coerceEtapaValidaAssinante($assinanteId, $desejada, $etapaAtual ?: null);

            if ($reply !== '') {
                $historicoCompleto[] = ['role' => 'assistant', 'content' => $reply, 'linha' => $nossoNumero];
            }

            $this->moverSessaoParaEtapa($sessaoModel, $numero, $usuarioId, $etapaFinal, [
                'ultima_mensagem_usuario' => $mensagem,
                'linha_numero'            => $nossoNumero,
                'historico'               => json_encode($historicoCompleto, JSON_UNESCAPED_UNICODE),
            ]);

            if ($reply !== '') {
                $partes = $this->quebrarMensagemEmDuas($reply, 1200, 1200);
                foreach ($partes as $i => $parte) {
                    $sessaoModel->where('numero', $numero)->where('usuario_id', $usuarioId)
                        ->set(['ultima_resposta_ia' => $parte])->update();

                    $this->enviarParaWhatsapp($usuarioId, $nossoNumero, $numero, $parte);
                    $this->salvarMensagemChat($numero, 'assistant', $parte, $canalLinha, $usuarioId);

                    if ($i === 0 && count($partes) > 1) sleep(1);
                }
            } else {
                $sessaoModel->where('numero', $numero)->where('usuario_id', $usuarioId)
                    ->set(['ultima_resposta_ia' => null])->update();
            }

            return $this->respond([
                'status'    => 'processado',
                'etapa'     => $etapaFinal ?? $etapaAtual,
                'moveu'     => ($etapaFinal ?? $etapaAtual) !== $etapaAtual,
                'confianca' => $confianca,
            ]);
        }

        return $this->respond(['ignorado' => 'payload não-gateway'], 200);
    }
}
