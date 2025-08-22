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

    /** Limites padrão da base de conhecimento */
    private int $maxKbItemsDefault  = 8;
    private int $maxKbCharsDefault  = 800;

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
     * Retorna:
     * [
     *   'ok'             => bool,
     *   'reply'          => string,
     *   'etapa_sugerida' => string|null,
     *   'mover_agora'    => bool,
     *   'confianca'      => float,
     *   'raw'            => string|null
     * ]
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

        $profNome          = trim((string) ($opts['profissional_nome'] ?? ''));
        $profTrat          = trim((string) ($opts['profissional_tratamento'] ?? 'Dra./Dr.'));

        $maxKbItems        = isset($opts['max_kb']) ? max(0, (int) $opts['max_kb']) : $this->maxKbItemsDefault;
        $maxKbChars        = isset($opts['max_kb_chars']) ? max(200, (int) $opts['max_kb_chars']) : $this->maxKbCharsDefault;

        // ---------- Inferência automática de tags ----------
        // Se o usuário pedir "procedimentos/serviços/tratamentos", forçamos as tags relacionadas
        if ($tagsFiltro === null) {
            $textoAgregado = mb_strtolower(json_encode($mensagens, JSON_UNESCAPED_UNICODE), 'UTF-8');
            if (preg_match('/procediment|servi[cç]o|tratament|o que a dra|o que o dr|o que a doutora|o que o doutor/u', $textoAgregado)) {
                $tagsFiltro = ['procedimentos', 'tratamentos', 'serviços', 'agenda', 'preços', 'valores'];
                // aumentar um pouco o limite para capturar itens suficientes
                $maxKbItems = max($maxKbItems, 10);
            }
        }

        // --- Carrega base (curta) ---
        $kbItens  = $this->carregarAprendizagemBase($assinanteId, $tagsFiltro, $etapaFiltro, $maxKbItems, $maxKbChars);
        $kbTexto  = '';
        if (!empty($kbItens)) {
            // Linha por item, evitando duplicações de prefixo "- "
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

        // Contexto operacional — identidade do profissional
        $contextoOp = [
            'role' => 'system',
            'content' => (
                "Contexto operacional:\n" .
                "- etapas_validas: " . json_encode($etapasValidas, JSON_UNESCAPED_UNICODE) . "\n" .
                "- responder_permitido: " . ($podeResponder ? 'true' : 'false') . "\n" .
                "- profissional_nome: " . ($profNome !== '' ? $profNome : 'não informado') . "\n" .
                "- profissional_tratamento: " . ($profTrat !== '' ? $profTrat : 'Dra./Dr.') . "\n" .
                "Quando precisar referenciar, use: \"" . ($profTrat ?: 'Dra./Dr.') . " " . ($profNome !== '' ? $profNome : 'da clínica') . "\"."
            )
        ];
        $injections[] = $contextoOp;

        if ($tomProximo) {
            $injections[] = [ 'role' => 'system', 'content' => 'Tom: pt-BR natural, acolhedor e direto (sem formalidade). Fale como alguém da clínica.' ];
        }
        if ($estiloMocinha) {
            $injections[] = [ 'role' => 'system', 'content' => 'Estilo leve/feminino (“menininha”): frases curtas; 0–1 emoji quando fizer sentido; sem floreio.' ];
        }
        if ($conciso) {
            $injections[] = [ 'role' => 'system', 'content' => 'Breviedade: no máximo ' . $maxFrases . ' frases curtas ou ~' . $maxChars . ' caracteres. Evite listas longas.' ];
        }
        if ($perguntaUnica) {
            $injections[] = [ 'role' => 'system', 'content' => 'No máximo UMA pergunta por resposta. Se a pessoa já perguntou algo, responda primeiro.' ];
        }
        if ($continuityGuard) {
            $injections[] = [ 'role' => 'system', 'content' => 'Continuidade: não cumprimente de novo sem necessidade, não se reapresente e não repita o que já foi dito.' ];
        }

        // Regras comerciais sem “enviar catálogo”
        $injections[] = [ 'role' => 'system', 'content' => 'Objetivo: conduzir para o próximo passo com convite leve (ex.: “te passo os valores agora?” “quer agendar avaliação?”) sem parecer vendedor. Nunca prometa enviar catálogo, PDF, link ou arquivos; responda aqui mesmo.' ];

        // Quando forem procedimentos/serviços, responder já com base na base
        $injections[] = [ 'role' => 'system', 'content' =>
            "Se pedirem sobre procedimentos/serviços/tratamentos, responda usando SOMENTE a Base da clínica. Liste 2–4 exemplos com micro-benefício (3–6 palavras cada) e depois um convite leve (ex.: “quer que eu te passe os valores agora?”). Nunca diga “vou te enviar o catálogo”."
        ];

        // Injeta Base (se houver)
        if ($kbTexto !== '') {
            $injections[] = [ 'role' => 'system', 'content' => $kbTexto ];
        } else {
            // Sem base: proíbe alucinação
            $injections[] = [ 'role' => 'system', 'content' =>
                "Se a informação solicitada não estiver na base, seja honesta: diga que não está cadastrada aqui e ofereça confirmar com " .
                ($profTrat ?: 'Dra./Dr.') . ' ' . ($profNome !== '' ? $profNome : 'da clínica') .
                " para responder em seguida, sem prometer prazos. Não invente nem chute."
            ];
        }

        // Especificação de saída JSON estrito
        $schemaInstr = [
            'role' => 'system',
            'content' =>
                "Responda APENAS em JSON válido com as chaves:\n" .
                "{\n  \"reply\": string,\n  \"etapa_sugerida\": string|null,\n  \"mover_agora\": boolean,\n  \"confianca\": number\n}\n\n" .
                "Regras:\n" .
                "- reply: texto curto, gentil, com CTA sutil, no tom definido.\n" .
                "- etapa_sugerida: escolha EXATAMENTE UMA dentre etapas_validas. Se nenhuma fizer sentido, use null.\n" .
                "- mover_agora: true somente com alta certeza e intenção clara; caso contrário false.\n" .
                "- confianca: 0..1.\n" .
                "- Se responder_permitido=false, mantenha reply=\"\" (string vazia). Ainda assim avalie etapa_sugerida e confianca.\n" .
                "- Nunca prometa enviar catálogo/arquivo/link. Responda aqui mesmo usando a Base.\n" .
                "- Se a mensagem do lead for vazia/ruído, retorne reply=\"\" e mover_agora=false."
        ];

        // Inserções
        array_splice($mensagens, 1, 0, $injections);
        // Removemos os few-shots antigos que induziam a “enviar catálogo”

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

        // Acrescenta instrução de schema no final (prioridade baixa, apenas formato)
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

    /**
     * Pós-processa a resposta:
     * - remove formalidade robótica
     * - limita frases e tamanho
     * - mantém no máximo 1 pergunta
     * - 0–1 emoji quando fizer sentido
     */
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
        int $limitQtde = 8,
        int $limitChars = 800
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
}
