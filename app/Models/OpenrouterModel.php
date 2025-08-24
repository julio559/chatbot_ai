<?php

namespace App\Models;

use CodeIgniter\Model;

class OpenrouterModel extends Model
{
    private string $apiKey;

    /** Modelo padrão (fallback) */
    private string $modeloPadrao = 'gpt-4o-mini';

    /** Máx. de mensagens no contexto */
    private int $maxJanelaHistorico = 40;

    /** Limites padrão da base de conhecimento (↑) */
    private int $maxKbItemsDefault  = 30;
    private int $maxKbCharsDefault  = 1200;

    public function __construct()
    {
        parent::__construct();
        // Sempre usar a variável de ambiente OPENAI_API_KEY
        $this->apiKey = (string) (getenv('OPENAI_API_KEY') ?: '');
    }

    /**
     * Envia mensagens ao Chat Completions e retorna apenas o texto.
     */
    public function enviarMensagem(array $mensagens, ?string $modelo = null, array $opts = [])
    {
        $resp = $this->enviarMensagemEstruturada($mensagens, $modelo, $opts);
        if (is_array($resp) && isset($resp['reply'])) {
            return (string) $resp['reply'];
        }
        return is_string($resp) ? $resp : 'Desculpe, não consegui responder agora.';
    }

    /**
     * Versão estruturada com controle de etapa.
     */
    public function enviarMensagemEstruturada(array $mensagens, ?string $modelo = null, array $opts = [])
    {
        if (empty($this->apiKey)) {
            return [
                'ok' => false,
                'reply' => 'Erro: OPENAI_API_KEY não encontrada (defina no .env).',
                'etapa_sugerida' => null,
                'mover_agora' => false,
                'confianca' => 0,
                'raw' => null,
            ];
        }

        // ---------- Opções ----------
        $modeloHumano      = array_key_exists('modelo_humano', $opts) ? (bool) $opts['modelo_humano'] : true;
        $temperatura       = isset($opts['temperatura']) ? (float) $opts['temperatura'] : 0.6;
        $topP              = isset($opts['top_p']) ? (float) $opts['top_p'] : 0.9;

        $estiloMocinha     = array_key_exists('estiloMocinha', $opts) ? (bool) $opts['estiloMocinha'] : true;
        $continuityGuard   = array_key_exists('continuityGuard', $opts) ? (bool) $opts['continuityGuard'] : true;
        $tomProximo        = array_key_exists('tom_proximo', $opts) ? (bool) $opts['tom_proximo'] : true;
        $conciso           = array_key_exists('conciso', $opts) ? (bool) $opts['conciso'] : true;
        $maxFrases         = isset($opts['max_frases']) ? max(1, (int) $opts['max_frases']) : 3;
        $maxChars          = isset($opts['max_chars']) ? max(120, (int) $opts['max_chars']) : 280;
        $perguntaUnica     = array_key_exists('pergunta_unica', $opts) ? (bool) $opts['pergunta_unica'] : true;
        $maxTokens         = isset($opts['max_tokens']) ? (int) $opts['max_tokens'] : 220;

        $assinanteId       = isset($opts['assinante_id']) ? (int) $opts['assinante_id'] : (int) (session('assinante_id') ?? 0);
        $tagsFiltro        = $opts['tags'] ?? null;
        $etapaFiltro       = isset($opts['etapa']) ? (string) $opts['etapa'] : null;

        $etapasValidas     = isset($opts['etapas_validas']) && is_array($opts['etapas_validas'])
                                ? array_values(array_filter(array_map('strval', $opts['etapas_validas'])))
                                : [];
        $podeResponder     = array_key_exists('responder_permitido', $opts) ? (bool) $opts['responder_permitido'] : true;

        // Identidade via opts (pode vir do Webhook) + extração automática da base
        $profNomeOpt       = trim((string) ($opts['profissional_nome'] ?? ''));
        $profTratOpt       = trim((string) ($opts['profissional_tratamento'] ?? ''));
        $donoUsuarioNome   = trim((string) ($opts['dono_usuario_nome'] ?? '')); // nome do dono da instância — vindo do Webhook

        $maxKbItems        = isset($opts['max_kb']) ? max(0, (int) $opts['max_kb']) : $this->maxKbItemsDefault;
        $maxKbChars        = isset($opts['max_kb_chars']) ? max(200, (int) $opts['max_kb_chars']) : $this->maxKbCharsDefault;

        // ---------- Inferência automática de tags ----------
        if ($tagsFiltro === null) {
            $textoAgregado = mb_strtolower(json_encode($mensagens, JSON_UNESCAPED_UNICODE), 'UTF-8');
            if (preg_match('/procediment|servi[cç]o|tratament|o que a dra|o que o dr|o que a doutora|o que o doutor|fazem|realizam|quais (s[aã]o|sao)/u', $textoAgregado)) {
                $tagsFiltro = ['procedimentos', 'tratamentos', 'serviços', 'agenda', 'preços', 'valores'];
                $maxKbItems = max($maxKbItems, 12);
            }
        }

        // --- Carrega base (curta) ---
        $kbItens  = $this->carregarAprendizagemBase($assinanteId, $tagsFiltro, $etapaFiltro, $maxKbItems, $maxKbChars);

        // --- EXTRAÇÃO da identidade/procedimentos a partir da base ---
        $ident = $this->extrairIdentidadeDaBase($kbItens);
        $profNome = $profNomeOpt !== '' ? $profNomeOpt : ($ident['nome'] ?? '');
        $profTrat = $profTratOpt !== '' ? $profTratOpt : ($ident['tratamento'] ?? 'Dra.');

        if (!empty($ident['procedimentos'])) {
            $uniq = array_values(array_unique($ident['procedimentos']));
            $kbItens[] = "[Identidade] Profissional: " . (($profTrat ?: 'Dra.') . " " . ($profNome !== '' ? $profNome : 'da clínica')) .
                         " | Procedimentos: " . implode(', ', $uniq);
        }

        $kbTexto  = '';
        if (!empty($kbItens)) {
            $linhas = array_map(function ($i) {
                return ltrim((string) $i, "- \t");
            }, $kbItens);
            $kbTexto = "Base da clínica (use apenas o que está aqui):\n- " . implode("\n- ", $linhas);
        }

        // ---------- Prompt base ----------
        helper('ia');
        $promptBase = get_prompt_padrao();
        if ($promptBase) {
            $jaTemIgual = false;
            foreach ($mensagens as $m) {
                if (($m['role'] ?? '') === 'system' && isset($m['content']) && trim($m['content']) === trim($promptBase)) {
                    $jaTemIgual = true; break;
                }
            }
            if (!$jaTemIgual) {
                array_unshift($mensagens, ['role' => 'system', 'content' => $promptBase]);
            }
        }

        // ---------- Injeções de estilo/controle ----------
        $injections = [];

        // Contexto operacional — identidade + dono
        $contextoOp = [
            'role' => 'system',
            'content' =>
                "Contexto operacional:\n" .
                "- etapas_validas: " . json_encode($etapasValidas, JSON_UNESCAPED_UNICODE) . "\n" .
                "- responder_permitido: " . ($podeResponder ? 'true' : 'false') . "\n" .
                "- profissional_nome: " . ($profNome !== '' ? $profNome : 'não informado') . "\n" .
                "- profissional_tratamento: " . ($profTrat !== '' ? $profTrat : 'Dra./Dr.') . "\n" .
                "- dono_da_instancia: " . ($donoUsuarioNome !== '' ? $donoUsuarioNome : 'não informado') . "\n" .
                "Quando precisar referenciar a profissional, use: \"" . ($profTrat ?: 'Dra./Dr.') . " " . ($profNome !== '' ? $profNome : 'da clínica') . "\"."
        ];
        $injections[] = $contextoOp;

        // Tom “menininha” melhorado
        if ($tomProximo) {
            $injections[] = [ 'role' => 'system', 'content' => 'Tom: pt-BR natural, acolhedor e direto, soando como atendente da clínica; sem formalidade.' ];
        }
        if ($estiloMocinha) {
            $injections[] = [ 'role' => 'system', 'content' => 'Estilo “menininha”: frases curtinhas, zero jargão, 0–1 emoji no máximo, acolha e convide sutilmente (sem vendedor).' ];
        }
        if ($conciso) {
            $injections[] = [ 'role' => 'system', 'content' => 'Breviedade: no máximo ' . $maxFrases . ' frases curtas ou ~' . $maxChars . ' caracteres. Evite listas longas.' ];
        }
        if ($perguntaUnica) {
            $injections[] = [ 'role' => 'system', 'content' => 'No máximo UMA pergunta por resposta. Se a pessoa já perguntou algo, responda primeiro.' ];
        }
        if ($continuityGuard) {
            $injections[] = [ 'role' => 'system', 'content' => 'Continuidade: não se reapresente, não cumprimente novamente sem necessidade e não repita o que já foi dito.' ];
        }

        // Regras comerciais
        $injections[] = [ 'role' => 'system', 'content' => 'Objetivo comercial: conduza para o próximo passo com convite leve (ex.: “te passo os valores agora?” “quer agendar avaliação?”). Não prometa enviar catálogo/arquivo/link; responda aqui.' ];

        // Procedimentos/serviços — usar SOMENTE a base
        $injections[] = [ 'role' => 'system', 'content' =>
            "Quando perguntarem sobre procedimentos/serviços/tratamentos, responda usando SOMENTE a Base da clínica. Liste 2–4 pontos com micro-benefícios (3–6 palavras) e finalize com convite leve. Nunca diga “vou te enviar o catálogo”."
        ];

        // Injeta Base
        if ($kbTexto !== '') {
            $injections[] = [ 'role' => 'system', 'content' => $kbTexto ];
        } else {
            // Sem base: proíbe alucinação
            $injections[] = [ 'role' => 'system', 'content' =>
                "Se a informação solicitada não estiver na base, diga isso com sinceridade e ofereça confirmar com " .
                ($profTrat ?: 'Dra./Dr.') . ' ' . ($profNome !== '' ? $profNome : 'da clínica') .
                " antes de responder. Não invente."
            ];
        }

        // Especificação de saída JSON estrito
        $schemaInstr = [
            'role' => 'system',
            'content' =>
                "Responda APENAS em JSON válido com as chaves:\n" .
                "{\n  \"reply\": string,\n  \"etapa_sugerida\": string|null,\n  \"mover_agora\": boolean,\n  \"confianca\": number\n}\n\n" .
                "Regras:\n" .
                "- reply: texto curto, gentil, com CTA sutil no tom definido.\n" .
                "- etapa_sugerida: escolha EXATAMENTE UMA dentre etapas_validas; se nenhuma, use null.\n" .
                "- mover_agora: true só se houver alta certeza e intenção clara; caso contrário false.\n" .
                "- confianca: 0..1.\n" .
                "- Se responder_permitido=false, mantenha reply=\"\".\n" .
                "- Se a mensagem do lead for vazia/ruído, retorne reply=\"\" e mover_agora=false."
        ];

        // Inserções
        array_splice($mensagens, 1, 0, $injections);

        // ---------- Janela de histórico ----------
        if (count($mensagens) > $this->maxJanelaHistorico) {
            $mensagens = array_slice($mensagens, -$this->maxJanelaHistorico);
        }

        // ---------- Modelo ----------
        $chosenModel = $modelo ?? ($modeloHumano ? 'gpt-4o-mini' : $this->modeloPadrao);
        if (strpos($chosenModel, '/') !== false) {
            $chosenModel = 'gpt-4o-mini';
        }

        // ---------- Chamada OpenAI ----------
        $url = 'https://api.openai.com/v1/chat/completions';
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $payloadMessages = $mensagens;
        $payloadMessages[] = $schemaInstr;

        $payload = [
            'model'           => $chosenModel,
            'messages'        => $payloadMessages,
            'temperature'     => $temperatura,
            'top_p'           => $topP,
            'max_tokens'      => (int) $maxTokens,
            'response_format' => [ 'type' => 'json_object' ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['ok' => false, 'reply' => 'Erro de rede: ' . $curlErr, 'etapa_sugerida' => null, 'mover_agora' => false, 'confianca' => 0, 'raw' => null];
        }
        if (!$response) {
            return ['ok' => false, 'reply' => 'Erro: sem resposta da API.', 'etapa_sugerida' => null, 'mover_agora' => false, 'confianca' => 0, 'raw' => null];
        }

        $json = json_decode($response, true);
        if (isset($json['error']['message'])) {
            return ['ok' => false, 'reply' => 'Erro da IA: ' . $json['error']['message'], 'etapa_sugerida' => null, 'mover_agora' => false, 'confianca' => 0, 'raw' => $response];
        }
        if (!isset($json['choices'][0]['message']['content'])) {
            return ['ok' => false, 'reply' => 'Erro: resposta inesperada da IA.', 'etapa_sugerida' => null, 'mover_agora' => false, 'confianca' => 0, 'raw' => $response];
        }

        $content = (string) $json['choices'][0]['message']['content'];
        $raw = $content;
        $parsed = json_decode($content, true);

        if (!is_array($parsed)) {
            // Fallback: trata como texto puro (ainda respeita limites)
            $reply = $this->lapidarResposta($content, [
                'max_frases'     => $maxFrases,
                'max_chars'      => $maxChars,
                'emoji_ok'       => true,
                'pergunta_unica' => $perguntaUnica,
            ]);
            return ['ok' => true, 'reply' => $reply, 'etapa_sugerida' => null, 'mover_agora' => false, 'confianca' => 0.0, 'raw' => $raw];
        }

        $reply       = (string) ($parsed['reply'] ?? '');
        $etapaSug    = isset($parsed['etapa_sugerida']) && in_array((string) $parsed['etapa_sugerida'], $etapasValidas, true)
                            ? (string) $parsed['etapa_sugerida'] : null;
        $moverAgora  = (bool) ($parsed['mover_agora'] ?? false);
        $confianca   = max(0.0, min(1.0, (float) ($parsed['confianca'] ?? 0)));

        // Pós-processamento do texto (curto e humano)
        $reply = $podeResponder ? $this->lapidarResposta($reply, [
            'max_frases'     => $maxFrases,
            'max_chars'      => $maxChars,
            'emoji_ok'       => true,
            'pergunta_unica' => $perguntaUnica,
        ]) : '';

        return [
            'ok'             => true,
            'reply'          => $reply,
            'etapa_sugerida' => $etapaSug,
            'mover_agora'    => (bool) $moverAgora,
            'confianca'      => $confianca,
            'raw'            => $raw,
        ];
    }

    /* ===================== Utilidades de estilo ===================== */

    private function lapidarResposta(string $txt, array $opts): string
    {
        $maxFrases     = (int) ($opts['max_frases'] ?? 3);
        $maxChars      = (int) ($opts['max_chars'] ?? 280);
        $emojiOk       = (bool) ($opts['emoji_ok'] ?? true);
        $perguntaUnica = (bool) ($opts['pergunta_unica'] ?? true);

        $t = trim($txt);

        // tira formalidade típica
        $t = preg_replace('/\b(Olá|Ola|Olá!|Ola!|Boa tarde|Boa noite|Bom dia)[\s,!]*\s*/iu', 'oi, ', $t, 1);
        $t = preg_replace('/\b(Posso ajudar em algo\??|Como posso ajudar\??)\b/iu', '', $t);

        // compacta espaços
        $t = preg_replace('/\s+/', ' ', $t);

        // limita caracteres com corte elegante
        if (mb_strlen($t, 'UTF-8') > $maxChars) {
            $t = mb_substr($t, 0, $maxChars, 'UTF-8');
            $t = preg_replace('/\p{L}+$/u', '', $t);
            $t = rtrim($t, " ,.;:!?") . '…';
        }

        // quebra em sentenças
        $frases = preg_split('/(?<=[\.\!\?])\s+/u', $t);
        if (!$frases || !is_array($frases)) $frases = [$t];

        // mantém até $maxFrases
        $frases = array_values(array_filter($frases, fn($f) => trim($f) !== ''));
        if (count($frases) > $maxFrases) $frases = array_slice($frases, 0, $maxFrases);

        // no máximo 1 pergunta
        if ($perguntaUnica) {
            $qCount = 0;
            foreach ($frases as &$f) {
                if (strpos($f, '?') !== false) {
                    $qCount++;
                    if ($qCount > 1) {
                        $f = rtrim(str_replace('?', '', $f), ' ') . '.';
                    }
                }
            }
            unset($f);
        }

        $t = trim(implode(' ', $frases));

        // emoji suave
        if ($emojiOk && !preg_match('/[\x{1F300}-\x{1FAFF}]/u', $t)) {
            if (preg_match('/\b(tudo bem|claro|perfeito|legal|ótimo|beleza|combinado)\b/iu', $t)) {
                $t .= ' :)';
            }
        }

        // arruma "oi," isolado
        $t = preg_replace('/^oi,\s*$/iu', 'oi :)', $t);

        // naturaliza “Você”
        $t = preg_replace('/\bVocê\b/u', 'você', $t);

        // remove duplicatas de pontuação
        $t = preg_replace('/([\.!?])\1+/u', '$1', $t);

        return trim($t);
    }

    /**
     * Carrega itens curtos da base de aprendizagem.
     */
    private function carregarAprendizagemBase(
        int $assinanteId,
        $tags = null,
        ?string $etapa = null,
        int $limitQtde = 30,
        int $limitChars = 1200
    ): array {
        $db = \Config\Database::connect();
        $builder = $db->table('aprendizagens')
            ->select('id, titulo, conteudo, tags')
            ->where('assinante_id', $assinanteId)
            ->where('ativo', 1);

        $termos = [];
        if (is_string($tags) && trim($tags) !== '') {
            $termos[] = trim($tags);
        } elseif (is_array($tags)) {
            foreach ($tags as $t) {
                $t = trim((string) $t);
                if ($t !== '') $termos[] = $t;
            }
        }
        if ($etapa) $termos[] = $etapa;

        if (!empty($termos)) {
            $builder->groupStart();
            foreach ($termos as $i => $t) {
                if ($i === 0) $builder->like('tags', $t, 'both');
                else $builder->orLike('tags', $t, 'both');
            }
            $builder->groupEnd();
        }

        $builder->orderBy('id', 'DESC');
        if ($limitQtde > 0) $builder->limit($limitQtde);

        $rows = $builder->get()->getResultArray();
        if (empty($rows)) return [];

        $out = [];
        foreach ($rows as $r) {
            $titulo   = trim((string) ($r['titulo'] ?? ''));
            $conteudo = trim((string) ($r['conteudo'] ?? ''));
            $tagsStr  = trim((string) ($r['tags'] ?? ''));

            if ($limitChars > 0 && mb_strlen($conteudo, 'UTF-8') > $limitChars) {
                $conteudo = mb_substr($conteudo, 0, $limitChars, 'UTF-8') . '…';
            }

            $linhaTitulo = $titulo !== '' ? "[{$titulo}] " : '';
            $linhaTags   = $tagsStr !== '' ? " (tags: {$tagsStr})" : '';
            $out[] = $linhaTitulo . $conteudo . $linhaTags;
        }
        return $out;
    }

    /**
     * Extrai “Dra./Dr.” + nome e lista de procedimentos a partir do texto da base.
     * Suporta frases do tipo:
     * - "Nome da dra é vanessa"
     * - "Dra Vanessa"
     * - "nome: Vanessa"
     * - "faz somente mini lipo", "faz mini lipo", "procedimentos: mini lipo, ..."
     */
    private function extrairIdentidadeDaBase(array $kbItens): array
    {
        $nome = '';
        $trat = 'Dra.';
        $proceds = [];

        $texto = mb_strtolower(implode("\n", $kbItens), 'UTF-8');

        // Tratamento (Dra./Dr.)
        if (preg_match('/\bdr\.\b/u', $texto))  $trat = 'Dr.';
        if (preg_match('/\bdra\.\b/u', $texto)) $trat = 'Dra.';

        // Nome por padrões variados
        if (preg_match('/nome da dr?a?\s*(?:é|:)\s*([a-zá-úãõâêôç ]{2,30})/u', $texto, $m)) {
            $nome = trim($m[1]);
        }
        if ($nome === '' && preg_match('/\bdr?a?\.?\s+([a-zá-úãõâêôç ]{2,30})/u', $texto, $m)) {
            $nome = trim($m[1]);
        }
        if ($nome === '' && preg_match('/\bnome\s*:\s*([a-zá-úãõâêôç ]{2,30})/u', $texto, $m)) {
            $nome = trim($m[1]);
        }

        // Capitaliza
        if ($nome !== '') {
            $nome = preg_replace_callback('/\b([a-zá-úãõâêôç])([a-zá-úãõâêôç]*)/u', function($m) {
                return mb_strtoupper($m[1], 'UTF-8') . $m[2];
            }, $nome);
        }

        // Procedimentos
        if (preg_match_all('/\b(faz(?:\s+somente)?|apenas|procedimentos?\s*:)\s*([a-z0-9 á-úãõâêôç\/\-\,]{2,})/u', $texto, $mm)) {
            foreach ($mm[2] as $blob) {
                $parts = preg_split('/[,\/]| e /u', $blob);
                foreach ($parts as $p) {
                    $pp = trim($p);
                    if ($pp !== '') $proceds[] = $pp;
                }
            }
        }
        if (preg_match('/\bmini\s*lipo\b/u', $texto)) $proceds[] = 'mini lipo';

        return [
            'nome' => $nome,
            'tratamento' => $trat,
            'procedimentos' => array_values(array_unique(array_map('trim', $proceds))),
        ];
    }
}
