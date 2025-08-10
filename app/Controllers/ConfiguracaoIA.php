<?php

namespace App\Controllers;

use App\Models\{SessaoModel, OpenrouterModel, PacienteModel, ConfigIaModel};
use CodeIgniter\Controller;

class ConfiguracaoIA extends Controller
{
    public function index()
    {
        $model   = new ConfigIaModel();
        $configs = $model->where('assinante_id', 1)->findAll();

        return view('configuracaoia', [
            'etapas' => $configs,
            'config' => $configs[0] ?? [],
        ]);
    }

    // permite usar a mesma rota para GET (carrega histórico) e POST (envia msg)
    public function testarChatSimulado()
    {
        if (strtolower($this->request->getMethod()) === 'post') {
            return $this->testarchat();
        }
        return $this->historicoTeste();
    }

    public function salvar()
    {
        $model = new ConfigIaModel();
        $etapa = $this->request->getPost('etapa_atual');

        $data = [
            'tempo_resposta'           => (int) $this->request->getPost('tempo_resposta'),
            'prompt_base'              => $this->request->getPost('prompt_etapa'),
            'modo_formal'              => $this->request->getPost('modo_formal') ? 1 : 0,
            'permite_respostas_longas' => $this->request->getPost('permite_respostas_longas') ? 1 : 0,
            'permite_redirecionamento' => $this->request->getPost('permite_redirecionamento') ? 1 : 0,
            'assinante_id'             => 1,
        ];

        $exist = $model->where('etapa_atual', $etapa)->where('assinante_id', 1)->first();
        if ($exist) {
            $model->update($exist['id'], $data);
        } else {
            $data['etapa_atual'] = $etapa;
            $model->insert($data);
        }

        return redirect()->to('/configuracaoia')->with('success', 'Configuração da etapa salva!');
    }

    /**
     * GET /configuracaoia/historicoTeste
     * Garante paciente e sessão do número de teste e devolve histórico + etapa + etapas do banco.
     */
    public function historicoTeste()
    {
        $numero = '99999999999';

        // garante paciente
        $pacienteModel = new PacienteModel();
        $pac = $pacienteModel->where('telefone', $numero)->first();
        if (!$pac) {
            $pacienteModel->insert([
                'nome'           => 'Paciente Teste',
                'telefone'       => $numero,
                'ultimo_contato' => date('Y-m-d H:i:s'),
                'origem_contato' => 1,
            ]);
            $pac = $pacienteModel->where('telefone', $numero)->first();
        } else {
            $pacienteModel->update($pac['id'], ['ultimo_contato' => date('Y-m-d H:i:s')]);
        }

        // garante sessão
        $sessaoModel = new SessaoModel();
        $sessao = $sessaoModel->getOuCriarSessao($numero);

        // histórico salvo
        $historico = [];
        if (!empty($sessao['historico'])) {
            $tmp = json_decode($sessao['historico'], true);
            if (is_array($tmp)) $historico = $tmp;
        }

        // etapas vindas do banco
        $configModel = new ConfigIaModel();
        $configs = $configModel->where('assinante_id', 1)->findAll();
        $etapasDisponiveis = array_values(array_unique(
            array_map(fn($r) => (string)$r['etapa_atual'], $configs)
        ));
        sort($etapasDisponiveis, SORT_NATURAL);

        return $this->response->setJSON([
            'numero'            => $numero,
            'etapa'             => $sessao['etapa'] ?? 'inicio',
            'paciente'          => ['nome' => $pac['nome'] ?? 'Paciente Teste'],
            'historico'         => $historico,
            'etapasDisponiveis' => $etapasDisponiveis,
        ]);
    }

    /**
     * POST /configuracaoia/atualizarEtapaTeste
     * body: etapa
     */
    public function atualizarEtapaTeste()
    {
        $numero = '99999999999';
        $etapa  = trim((string)$this->request->getPost('etapa'));

        if ($etapa === '') {
            return $this->response->setJSON(['erro' => 'etapa vazia'])->setStatusCode(400);
        }

        $sessaoModel = new SessaoModel();
        $sessaoModel->getOuCriarSessao($numero);
        $sessaoModel->update($numero, ['etapa' => $etapa]);

        return $this->response->setJSON(['ok' => true, 'etapa' => $etapa]);
    }

    /**
     * POST /configuracaoia/testarchat
     * Body: mensagem, (opcional) prompt
     * Retorna: { resposta, partes[], historico[] }
     * Lógica espelhada da webhook + curto-circuito “qual é meu nome?”
     * Agora divide resposta em até 2 bolhas coesas.
     */
    public function testarchat()
    {
        helper('ia');

        $mensagem = trim((string) $this->request->getPost('mensagem'));
        if ($mensagem === '') {
            return $this->response->setJSON(['resposta' => 'Mensagem vazia.'])->setStatusCode(400);
        }

        // Prompt (prioriza config; senão, padrão)
        $configModel  = new ConfigIaModel();
        $configEtapa  = $configModel->where('assinante_id', 1)->orderBy('id', 'ASC')->first();
        $prompt       = get_prompt_padrao();
        $tempoRespCfg = (int)($configEtapa['tempo_resposta'] ?? 3);

        // Número fixo do simulador
        $numero = '99999999999';

        // Upsert paciente
        $pacienteModel = new PacienteModel();
        $paciente = $pacienteModel->where('telefone', $numero)->first();
        if ($paciente) {
            $pacienteModel->update($paciente['id'], ['ultimo_contato' => date('Y-m-d H:i:s')]);
        } else {
            $pacienteModel->insert([
                'nome'           => 'Paciente Teste',
                'telefone'       => $numero,
                'ultimo_contato' => date('Y-m-d H:i:s'),
                'origem_contato' => 1,
            ]);
            $paciente = $pacienteModel->where('telefone', $numero)->first();
        }

        // Sessão atual
        $sessaoModel = new SessaoModel();
        $sessao      = $sessaoModel->getOuCriarSessao($numero);
        $etapaAtual  = $sessao['etapa'] ?? 'inicio';
        $ultimaUser  = $sessao['ultima_mensagem_usuario'] ?? null;

        // Cache (carimbo lógico)
        $cache    = \Config\Services::cache();
        $stampKey = "stamp_{$numero}";
        $tsUpdate = (int)($cache->get($stampKey) ?: 0);
        $agora    = time();
        $janelaDup = 15;
        $cooldown  = 6;

        if (!empty($ultimaUser)
            && mb_strtolower(trim($mensagem), 'UTF-8') === mb_strtolower(trim($ultimaUser), 'UTF-8')
            && $tsUpdate && ($agora - $tsUpdate) < $janelaDup
        ) {
            return $this->response->setJSON(['ignorado' => 'mensagem duplicada em janela curta']);
        }

        if ($tsUpdate && ($agora - $tsUpdate) < $cooldown) {
            return $this->response->setJSON(['ignorado' => 'cooldown ativo; evitando múltiplas respostas']);
        }

        // Debounce
        $lockKey = "ia_lock_{$numero}";
        if ($cache->get($lockKey)) {
            return $this->response->setJSON(['ignorado' => 'processamento em andamento (debounce)']);
        }
        $cache->save($lockKey, 1, 10);

        try {
            // Etapas bloqueadas
            $etapasBloqueadas = ['agendamento', 'finalizado'];
            if (in_array($etapaAtual, $etapasBloqueadas, true)) {
                return $this->response->setJSON(['ignorado' => "IA não responde em etapa '$etapaAtual'"]);
            }

            // Detecta intenção -> nova etapa (sugere)
            $mensagemLower = mb_strtolower($mensagem, 'UTF-8');
            $palavrasChave = [
                'agendamento' => ['agendar', 'consulta', 'marcar', 'horário', 'horario', 'atendimento'],
                'financeiro'  => ['valor', 'preço', 'preco', 'custo', 'quanto', 'pix', 'pagamento', 'pagar'],
                'perdido'     => ['desistir', 'não quero', 'nao quero', 'não tenho interesse', 'nao tenho interesse', 'não posso', 'nao posso', 'depois eu vejo'],
                'em_contato'  => ['me explica', 'quero saber mais', 'entendi', 'ok', 'vamos conversar', 'pode me falar', 'pode explicar'],
            ];
            $novaEtapa = $etapaAtual;
            foreach ($palavrasChave as $etapa => $palavras) {
                foreach ($palavras as $p) {
                    if (mb_strpos($mensagemLower, $p, 0, 'UTF-8') !== false) {
                        $novaEtapa = $etapa;
                        break 2;
                    }
                }
            }

            // Histórico (sessão > banco)
            $historicoSessao = session()->get("historico_{$numero}") ?? [];
            $historicoBanco  = json_decode($sessao['historico'] ?? '[]', true);
            $historico       = (!empty($historicoSessao)) ? $historicoSessao : $historicoBanco;
            if (count($historico) > 40) $historico = array_slice($historico, -40);

            // Aprender nome
            if (preg_match('/\b(meu\s+nome\s+é|meu\s+nome\s+e|sou|eu\s+me\s+chamo)\s+(.{2,60})/i', $mensagem, $m)) {
                $possivelNome = trim(preg_replace('/[^\p{L}\p{M}\s\'.-]/u', '', $m[2]));
                if ($possivelNome && mb_strlen($possivelNome, 'UTF-8') >= 2) {
                    $pacienteModel->update($paciente['id'], ['nome' => $possivelNome]);
                    $perfil = session()->get("perfil_{$numero}") ?? [];
                    $perfil['nome'] = $possivelNome;
                    session()->set("perfil_{$numero}", $perfil);
                }
            }

            // Curto-circuito: “qual é meu nome?”
            $ehPerguntaNome = preg_match(
                '/\b(qual(\s+é)?\s+meu\s+nome|como\s+é\s+meu\s+nome|como\s+eu\s+me\s+chamo|como\s+me\s+cha(?:mo|o)|v(?:c|ocê)\s+lembra\s+meu\s+nome|\bmeu\s+nome\?)\b/iu',
                $mensagemLower
            );
            if ($ehPerguntaNome) {
                $perfil = $this->carregarPerfil($numero);
                $respLocal = !empty($perfil['nome'])
                    ? "Você me disse que seu nome é {$perfil['nome']}."
                    : "Você ainda não me contou seu nome. Se quiser, me diz como prefere ser chamada. 😊";

                $historico[] = ['role' => 'user', 'content' => $mensagem, 'ts' => date('c')];
                $historico[] = ['role' => 'assistant', 'content' => $respLocal, 'ts' => date('c')];

                session()->set("historico_{$numero}", $historico);
                $sessaoModel->update($numero, [
                    'etapa'                   => $novaEtapa,
                    'ultima_mensagem_usuario' => $mensagem,
                    'ultima_resposta_ia'      => $respLocal,
                    'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
                ]);

                $cache->save($stampKey, time(), 60);

                return $this->response->setJSON([
                    'resposta'  => $respLocal,
                    'partes'    => [$respLocal],
                    'historico' => $historico,
                ]);
            }

            // Revisita (>7 dias)
            $mensagemRevisita = '';
            if (!empty($historico) && isset($paciente['ultimo_contato'])) {
                $tempoUltimoContato = strtotime($paciente['ultimo_contato']);
                if ($tempoUltimoContato && (time() - $tempoUltimoContato) > 604800) {
                    $mensagemRevisita = "Que bom te ver por aqui de novo! 😊";
                }
            }

            // Guardas de continuidade
            $continuityGuard =
                "Você está em uma conversa contínua no WhatsApp.\n".
                "- Não cumprimente novamente se já começou.\n".
                "- Não reinicie apresentação; continue de onde parou.\n".
                "- Não repita o que foi dito recentemente.\n".
                "- Responda curto, natural e avance o assunto atual.\n";

            // Monta mensagens para IA
            $mensagensIA = [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'system', 'content' => $continuityGuard],
            ];
            foreach ($historico as $msg) {
                if (isset($msg['role'], $msg['content'])) $mensagensIA[] = $msg;
            }
            $mensagensIA[] = ['role' => 'user', 'content' => $mensagem];

            // Latência simulada leve
            if ($tempoRespCfg > 0) sleep(min($tempoRespCfg, 5));

            // IA
            $open         = new OpenrouterModel();
            $respostaFull = $open->enviarMensagem($mensagensIA, null, [
                'temperatura'     => 0.8,
                'top_p'           => 0.9,
                'estiloMocinha'   => true,
                'continuityGuard' => true,
                'max_tokens'      => 300,
            ]);

            if ($mensagemRevisita) {
                $respostaFull = $mensagemRevisita . "\n" . $respostaFull;
            }

            // ==== NOVO: dividir em até 2 bolhas coesas ====
            $partes = $this->dividirEm2MensagensCoesas($respostaFull, 220);
            if (empty($partes)) $partes = [$respostaFull];

            // Atualiza histórico e persiste
            $historico[] = ['role' => 'user', 'content' => $mensagem, 'ts' => date('c')];
            foreach ($partes as $p) {
                $historico[] = ['role' => 'assistant', 'content' => $p, 'ts' => date('c')];
            }

            session()->set("historico_{$numero}", $historico);
            $sessaoModel->update($numero, [
                'etapa'                   => $novaEtapa,
                'ultima_mensagem_usuario' => $mensagem,
                'ultima_resposta_ia'      => end($partes),
                'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
            ]);

            $cache->save($stampKey, time(), 60);

            return $this->response->setJSON([
                'resposta'  => implode("\n", $partes),
                'partes'    => $partes,
                'historico' => $historico,
            ]);
        } finally {
            $cache->delete($lockKey);
        }
    }

    /* ==================== HELPERS ==================== */

    /** Carrega nome do perfil da sessão; se não houver, pega de pacientes e salva. */
    private function carregarPerfil(string $numero): array
    {
        $perfil = session()->get("perfil_{$numero}") ?? [];
        if (!empty($perfil['nome'])) return $perfil;

        $pacienteModel = new PacienteModel();
        $pac = $pacienteModel->where('telefone', $numero)->first();
        if ($pac && !empty($pac['nome'])) {
            $perfil['nome'] = $pac['nome'];
            session()->set("perfil_{$numero}", $perfil);
        }
        return $perfil;
    }

    /** Divide texto em “bolhas” curtas (mantive caso você use em outra view) */
    private function fatiasWhatsApp(string $texto, int $limite = 220): array
    {
        $texto = trim(preg_replace("/\s+/", " ", $texto));
        if ($texto === '') return [];

        $sentencas = preg_split('/(?<=[\.\!\?])\s+/', $texto) ?: [$texto];

        $bolhas = [];
        $buf = '';

        foreach ($sentencas as $s) {
            if (mb_strlen($s) > $limite) {
                if ($buf !== '') { $bolhas[] = trim($buf); $buf=''; }
                $bolhas = array_merge($bolhas, $this->chunkByLength($s, $limite));
                continue;
            }
            if ($buf === '') {
                $buf = $s;
            } else {
                if (mb_strlen($buf . ' ' . $s) <= $limite) {
                    $buf .= ' ' . $s;
                } else {
                    $bolhas[] = trim($buf);
                    $buf = $s;
                }
            }
        }
        if ($buf !== '') $bolhas[] = trim($buf);

        return array_values(array_filter($bolhas, fn($b) => $b !== ''));
    }

    private function chunkByLength(string $s, int $limite): array
    {
        $ret = [];
        $len = mb_strlen($s);
        for ($i = 0; $i < $len; $i += $limite) {
            $ret[] = trim(mb_substr($s, $i, $limite));
        }
        return $ret;
    }

    /**
     * NOVO: Divide um texto em até 2 mensagens coesas, priorizando limites de frase.
     * $limite é o alvo para a 1ª bolha; a 2ª recebe o restante.
     */
    private function dividirEm2MensagensCoesas(string $texto, int $limite = 220): array
    {
        $t = trim(preg_replace("/\s+/", " ", $texto));
        if ($t === '') return [];

        if (mb_strlen($t, 'UTF-8') <= $limite) {
            return [$t];
        }

        // tenta por sentenças
        $sentencas = preg_split('/(?<=[\.\!\?…])\s+/u', $t) ?: [$t];

        $primeira = '';
        $restante = [];
        foreach ($sentencas as $s) {
            $sTrim = trim($s);
            if ($primeira === '') {
                $primeira = $sTrim;
            } else {
                if (mb_strlen($primeira . ' ' . $sTrim, 'UTF-8') <= $limite) {
                    $primeira .= ' ' . $sTrim;
                } else {
                    $restante[] = $sTrim;
                }
            }
        }

        if (empty($restante)) {
            // tudo coube na primeira (ou não houve separação útil)
            return [$t];
        }

        if (mb_strlen($primeira, 'UTF-8') === 0) {
            // 1ª sentença já maior que o limite -> fallback por palavras
            return $this->fallbackPorPalavras($t, $limite);
        }

        $segunda = trim(implode(' ', $restante));
        return array_values(array_filter([$primeira, $segunda], fn($x) => $x !== ''));
    }

    /** Fallback: quebra por palavras perto do limite, evitando cortar no meio. */
    private function fallbackPorPalavras(string $t, int $limite): array
    {
        if (mb_strlen($t, 'UTF-8') <= $limite) return [$t];

        $corte = $limite;
        $compr = mb_strlen($t, 'UTF-8');

        // tenta achar um separador “amigável” antes do limite
        for ($i = $limite; $i > max(0, $limite - 40); $i--) {
            $char = mb_substr($t, $i, 1, 'UTF-8');
            if ($char === ' ' || $char === ',' || $char === ';' || $char === '—' || $char === '-') {
                $corte = $i;
                break;
            }
        }

        $primeira = trim(mb_substr($t, 0, $corte, 'UTF-8'));
        $segunda  = trim(mb_substr($t, $corte, $compr - $corte, 'UTF-8'));

        return array_values(array_filter([$primeira, $segunda], fn($x) => $x !== ''));
    }

    /**
     * POST /configuracaoia/limparHistoricoTeste
     * Limpa histórico (sessão + banco) e reseta carimbos de cache.
     */
    public function limparHistoricoTeste()
    {
        $numero = '99999999999';

        // limpa sessão
        session()->remove("historico_{$numero}");
        // mantém o nome; se quiser limpar, descomente:
        // session()->remove("perfil_{$numero}");

        // zera histórico no banco
        $sessaoModel = new SessaoModel();
        $sessaoModel->getOuCriarSessao($numero);
        $sessaoModel->update($numero, [
            'ultima_mensagem_usuario' => null,
            'ultima_resposta_ia'      => null,
            'historico'               => json_encode([], JSON_UNESCAPED_UNICODE),
        ]);

        // reseta carimbos de cache (evita false-positives de duplicata/cooldown)
        $cache = \Config\Services::cache();
        $cache->delete("stamp_{$numero}");
        $cache->delete("ia_lock_{$numero}");

        return $this->response->setJSON(['ok' => true]);
    }
}
