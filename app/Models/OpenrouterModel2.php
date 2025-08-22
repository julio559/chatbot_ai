<?php

namespace App\Models;

use CodeIgniter\Model;

class OpenrouterModel2 extends Model
{
    private string $apiKey;

    /** Modelo padrão */
    private string $modeloPadrao = 'anthropic/claude-3-haiku';

    /** Máx. de mensagens no contexto */
    private int $maxJanelaHistorico = 40;

    /** Limites default da base de conhecimento (podem ser sobrescritos por $opts) */
    private int $maxKbItemsDefault  = 8;    // quantos itens no máximo
    private int $maxKbCharsDefault  = 800;  // limite de caracteres por item

    public function __construct()
    {
        parent::__construct();
        // ideal: usar getenv('OPENROUTER_API_KEY')
        $this->apiKey = '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';
    }

    /**
     * Envia mensagens ao OpenRouter.
     *
     * @param array       $mensagens  Array no formato OpenAI (role/content).
     * @param string|null $modelo     Slug do modelo (ou null pra usar padrão).
     * @param array       $opts       Opções:
     *   - 'temperatura' (float): default 0.8
     *   - 'top_p' (float): default 0.9
     *   - 'estiloMocinha' (bool): default false
     *   - 'continuityGuard' (bool): default false
     *   - 'max_tokens' (int|null)
     *   - 'assinante_id' (int|null): se não vier, usa session('assinante_id')
     *   - 'tags' (string|array|null): filtrar aprendizagem por tags (LIKE)
     *   - 'etapa' (string|null): etapa atual; usada como tag extra (LIKE)
     *   - 'max_kb' (int): quantos registros da aprendizagem injetar (default 8)
     *   - 'max_kb_chars' (int): chars por item (default 800)
     */
    public function enviarMensagem(array $mensagens, ?string $modelo = null, array $opts = [])
    {
        if (empty($this->apiKey)) {
            return 'Erro: chave da OpenRouter não encontrada.';
        }

        // ---------- Opções ----------
        $temperatura     = isset($opts['temperatura']) ? (float)$opts['temperatura'] : 0.8;
        $topP            = isset($opts['top_p']) ? (float)$opts['top_p'] : 0.9;
        $estiloMocinha   = array_key_exists('estiloMocinha', $opts) ? (bool)$opts['estiloMocinha'] : false;
        $continuityGuard = array_key_exists('continuityGuard', $opts) ? (bool)$opts['continuityGuard'] : false;
        $maxTokens       = $opts['max_tokens'] ?? null;

        $assinanteId     = isset($opts['assinante_id']) ? (int)$opts['assinante_id'] : (int)(session('assinante_id') ?? 0);
        $tagsFiltro      = $opts['tags'] ?? null;   // string|array|null
        $etapaFiltro     = isset($opts['etapa']) ? (string)$opts['etapa'] : null;

        $maxKbItems      = isset($opts['max_kb']) ? max(0, (int)$opts['max_kb']) : $this->maxKbItemsDefault;
        $maxKbChars      = isset($opts['max_kb_chars']) ? max(200, (int)$opts['max_kb_chars']) : $this->maxKbCharsDefault;

        // ---------- Prompt base vindo do get_prompt_padrao() ----------
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

        // ---------- Injeções opcionais ----------
        $injections = [];
        if ($estiloMocinha) {
            $injections[] = [
                'role' => 'system',
                'content' =>
                    "Estilo (pt-BR, feminino leve): frases curtas; 0–1 emoji; carinhosa sem exageros; direta e natural; " .
                    "use o primeiro nome com moderação; sem se reapresentar se já começou; avance o assunto."
            ];
        }
        if ($continuityGuard) {
            $injections[] = [
                'role' => 'system',
                'content' =>
                    "Continuidade: não cumprimente de novo, não reinicie apresentação, não repita o recente; " .
                    "se pedir preço/agenda/pagamento, responda objetivo e sem rodeios."
            ];
        }

        // ---------- Injeção da Aprendizagem (base de conhecimento) ----------
        // Se houver assinante, buscamos os textos ativos e montamos um system message.
        if ($assinanteId) {
            $kb = $this->carregarAprendizagemBase($assinanteId, $tagsFiltro, $etapaFiltro, $maxKbItems, $maxKbChars);
            if ($kb) {
                $kbHeader = "Base de conhecimento da clínica (use somente como contexto; se o usuário contradizer, priorize o que ele disser):\n";
                $kbText = $kbHeader . implode("\n\n", $kb);

                // Inserimos logo após o prompt base (primeira system)
                $injections[] = ['role' => 'system', 'content' => $kbText];
            }
        }

        if (!empty($injections)) {
            array_splice($mensagens, 1, 0, $injections);
        }

        // ---------- Janela de histórico ----------
        if (count($mensagens) > $this->maxJanelaHistorico) {
            $mensagens = array_slice($mensagens, -$this->maxJanelaHistorico);
        }

        // ---------- Chamada OpenRouter ----------
        $url = 'https://openrouter.ai/api/v1/chat/completions';
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: https://atomazai.com.br',
            'X-Title: Atendimento IA'
        ];

        $payload = [
            'model'       => $modelo ?? $this->modeloPadrao,
            'messages'    => $mensagens,
            'temperature' => $temperatura,
            'top_p'       => $topP,
        ];
        if ($maxTokens !== null) {
            $payload['max_tokens'] = (int)$maxTokens;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return 'Erro de rede: ' . $curlErr;
        }
        if (!$response) {
            return 'Erro: sem resposta da API.';
        }

        $json = json_decode($response, true);

        if (isset($json['error']['message'])) {
            return 'Erro da IA: ' . $json['error']['message'];
        }

        if (!isset($json['choices'][0]['message']['content'])) {
            return 'Erro: resposta inesperada da IA.';
        }

        return $json['choices'][0]['message']['content'];
    }

    /**
     * Busca a base de aprendizagem (`aprendizagem_base`) do assinante.
     * Retorna uma lista de strings já “compactadas” para injeção no prompt.
     *
     * Regras:
     *  - Somente registros `ativo=1` e do `assinante_id` informado.
     *  - Se vierem `tags` e/ou `etapa`, aplico LIKE em `tags`.
     *  - Limito quantidade e tamanho de cada item pra não estourar contexto.
     *
     * @param int               $assinanteId
     * @param string|array|null $tags
     * @param string|null       $etapa
     * @param int               $limitQtde
     * @param int               $limitChars
     * @return array<string>
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

        // Normaliza filtros de tag
        $termos = [];
        if (is_string($tags) && $tags !== '') $termos[] = $tags;
        if (is_array($tags)) {
            foreach ($tags as $t) { $t = trim((string)$t); if ($t !== '') $termos[] = $t; }
        }
        if ($etapa) $termos[] = $etapa;

        if (!empty($termos)) {
            $builder->groupStart();
            foreach ($termos as $i => $t) {
                // busca por tags contendo o termo (case-insensitive em collation padrão utf8mb4_general_ci)
                if ($i === 0) $builder->like('tags', $t, 'both');
                else          $builder->orLike('tags', $t, 'both');
            }
            $builder->groupEnd();
        }

        // ordem: mais recentes primeiro
        $builder->orderBy('id', 'DESC');

        if ($limitQtde > 0) $builder->limit($limitQtde);

        $rows = $builder->get()->getResultArray();
        if (empty($rows)) return [];

        $out = [];
        foreach ($rows as $r) {
            $titulo   = trim((string)($r['titulo'] ?? ''));
            $conteudo = trim((string)($r['conteudo'] ?? ''));
            $tagsStr  = trim((string)($r['tags'] ?? ''));

            if ($limitChars > 0 && mb_strlen($conteudo, 'UTF-8') > $limitChars) {
                $conteudo = mb_substr($conteudo, 0, $limitChars, 'UTF-8') . '…';
            }

            // Formato compacto por item
            // Ex.: "- [Procedimentos] Informações gerais: ... \n  (tags: procedimentos, agenda)"
            $linhaTitulo = $titulo !== '' ? "[{$titulo}] " : '';
            $linhaTags   = $tagsStr !== '' ? "\n(tags: {$tagsStr})" : '';

            $out[] = "- {$linhaTitulo}{$conteudo}{$linhaTags}";
        }
        return $out;
    }
}
