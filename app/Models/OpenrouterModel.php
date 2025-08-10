<?php

namespace App\Models;

use CodeIgniter\Model;

class OpenrouterModel extends Model
{
    private string $apiKey;

    /** Modelo padrão (pode trocar por ENV ou parâmetro na chamada) */
    private string $modeloPadrao = 'anthropic/claude-3-haiku';

    /** Máx. de mensagens no contexto */
    private int $maxJanelaHistorico = 40;

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
     *   - 'estiloMocinha' (bool): default **false** (use o prompt do get_prompt_padrao)
     *   - 'continuityGuard' (bool): default **false** (use o prompt do get_prompt_padrao)
     *   - 'max_tokens' (int|null)
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

        // ---------- Prompt base vindo do get_prompt_padrao() ----------
        helper('ia');
        $promptBase = get_prompt_padrao(); // <- usa seu prompt
        if ($promptBase) {
            // Garante que o prompt base seja a PRIMEIRA mensagem system
            // (evita duplicar se já for exatamente o mesmo conteúdo)
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

        // ---------- Injeções opcionais (normalmente desnecessárias pois seu prompt já cobre) ----------
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
        if (!empty($injections)) {
            // injeta logo após o prompt base para manter prioridade
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
            'X-Title: Atendimento Bruna IA'
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
}
