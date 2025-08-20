<?php

namespace App\Controllers;

use App\Models\{SessaoModel, OpenrouterModel, PacienteModel, ConfigIaModel};
use CodeIgniter\Controller;

class ConfiguracaoIA extends Controller
{
    protected ?int $usuarioId   = null;
    protected ?int $assinanteId = null;

    public function __construct()
    {
        $this->usuarioId   = (int) (session()->get('usuario_id')   ?? 0) ?: null;
        // fallback para 1 enquanto o login não estiver ativo
        $this->assinanteId = (int) (session()->get('assinante_id') ?? 0) ?: 1;
    }

    public function index()
    {
        $model   = new ConfigIaModel();
        $configs = $model->where('assinante_id', $this->assinanteId)->findAll();

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
            'assinante_id'             => $this->assinanteId,
            // se sua tabela tiver coluna usuario_id, descomente:
            // 'usuario_id'               => $this->usuarioId,
        ];

        $exist = $model->where('etapa_atual', $etapa)
                       ->where('assinante_id', $this->assinanteId)
                       ->first();

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
     * Garante paciente e sessão do número de teste (por usuário) e devolve histórico + etapa + etapas do banco.
     */
    public function historicoTeste()
    {
        $numero = $this->numeroTeste();

        // garante paciente do usuário
        $pacienteModel = new PacienteModel();
        $pac = $pacienteModel->where('telefone', $numero)->where('usuario_id', $this->usuarioId)->first();
        if (!$pac) {
            $pacienteModel->insert([
                'nome'           => 'Paciente Teste',
                'telefone'       => $numero,
                'ultimo_contato' => date('Y-m-d H:i:s'),
                'origem_contato' => 1,
                'usuario_id'     => $this->usuarioId,
            ]);
            $pac = $pacienteModel->where('telefone', $numero)->where('usuario_id', $this->usuarioId)->first();
        } else {
            $pacienteModel->update($pac['id'], [
                'ultimo_contato' => date('Y-m-d H:i:s'),
            ]);
        }

        // garante sessão do usuário
        $sessaoModel = new SessaoModel();
        $sessao      = $sessaoModel->getOuCriarSessao($numero, $this->usuarioId);

        // histórico salvo
        $historico = [];
        if (!empty($sessao['historico'])) {
            $tmp = json_decode($sessao['historico'], true);
            if (is_array($tmp)) $historico = $tmp;
        }

        // etapas do banco por USUÁRIO (fonte de verdade)
        $etapasDisponiveis = $sessaoModel->listarEtapasUsuario((int)$this->usuarioId);

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
        $numero         = $this->numeroTeste();
        $etapaSolicitada = trim((string)$this->request->getPost('etapa'));
        if ($etapaSolicitada === '') {
            return $this->response->setJSON(['erro' => 'etapa vazia'])->setStatusCode(400);
        }

        $sessaoModel   = new SessaoModel();
        $sessao        = $sessaoModel->getOuCriarSessao($numero, $this->usuarioId);
        $etapaAlinhada = $sessaoModel->alinharEtapaUsuario($etapaSolicitada, (int)$this->usuarioId);

        if (!$etapaAlinhada) {
            return $this->response->setJSON([
                'ok'    => false,
                'erro'  => 'Etapa não existente para este usuário.',
                'input' => $etapaSolicitada
            ])->setStatusCode(422);
        }

        $this->updateSessaoByNumero($numero, $this->usuarioId, [
            'etapa'      => $etapaAlinhada,
            'usuario_id' => $this->usuarioId,
        ]);

        return $this->response->setJSON(['ok' => true, 'etapa' => $etapaAlinhada]);
    }

    /**
     * POST /configuracaoia/testarchat
     * Body: mensagem
     * Retorna: { resposta, partes[], historico[] }
     */
    public function testarchat()
    {
        helper('ia');

        $mensagem = trim((string) $this->request->getPost('mensagem'));
        if ($mensagem === '') {
            return $this->response->setJSON(['resposta' => 'Mensagem vazia.'])->setStatusCode(400);
        }

        // Número fixo do simulador (por usuário)
        $numero = $this->numeroTeste();

        // Upsert paciente (escopo do usuário)
        $pacienteModel = new PacienteModel();
        $paciente = $pacienteModel->where('telefone', $numero)->where('usuario_id', $this->usuarioId)->first();
        if ($paciente) {
            $pacienteModel->update($paciente['id'], ['ultimo_contato' => date('Y-m-d H:i:s')]);
        } else {
            $pacienteModel->insert([
                'nome'           => 'Paciente Teste',
                'telefone'       => $numero,
                'ultimo_contato' => date('Y-m-d H:i:s'),
                'origem_contato' => 1,
                'usuario_id'     => $this->usuarioId,
            ]);
            $paciente = $pacienteModel->where('telefone', $numero)->where('usuario_id', $this->usuarioId)->first();
        }

        // Sessão atual (escopo do usuário)
        $sessaoModel = new SessaoModel();
        $sessao      = $sessaoModel->getOuCriarSessao($numero, $this->usuarioId);
        $etapaAtual  = $sessao['etapa'] ?? 'inicio';
        $ultimaUser  = $sessao['ultima_mensagem_usuario'] ?? null;

        // Cache (carimbo lógico)
        $cache     = \Config\Services::cache();
        $stampKey  = "stamp_{$this->usuarioId}_{$numero}";
        $tsUpdate  = (int)($cache->get($stampKey) ?: 0);
        $agora     = time();
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
        $lockKey = "ia_lock_{$this->usuarioId}_{$numero}";
        if ($cache->get($lockKey)) {
            return $this->response->setJSON(['ignorado' => 'processamento em andamento (debounce)']);
        }
        $cache->save($lockKey, 1, 10);

        try {
            // Etapas bloqueadas
            $etapasBloqueadas = ['orcamento', 'agendamento', 'finalizado'];
            if (in_array($etapaAtual, $etapasBloqueadas, true)) {
                return $this->response->setJSON(['ignorado' => "IA não responde em etapa '$etapaAtual'"]);
            }

            // Detecta intenção -> nova etapa (só se existir p/ usuário)
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
                        $alinhada = $sessaoModel->alinharEtapaUsuario($etapa, (int)$this->usuarioId);
                        if ($alinhada) {
                            $novaEtapa = $alinhada;
                            break 2;
                        }
                    }
                }
            }

            // Histórico (sessão > banco)
            $historicoSessao = session()->get("historico_{$this->usuarioId}_{$numero}") ?? [];
            $historicoBanco  = json_decode($sessao['historico'] ?? '[]', true);
            $historico       = (!empty($historicoSessao)) ? $historicoSessao : (is_array($historicoBanco) ? $historicoBanco : []);
            if (count($historico) > 40) $historico = array_slice($historico, -40);

            // Aprender nome
            if (preg_match('/\b(meu\s+nome\s+é|meu\s+nome\s+e|sou|eu\s+me\s+chamo)\s+(.{2,60})/i', $mensagem, $m)) {
                $possivelNome = trim(preg_replace('/[^\p{L}\p{M}\s\'.-]/u', '', $m[2]));
                if ($possivelNome && mb_strlen($possivelNome, 'UTF-8') >= 2) {
                    $pacienteModel->update($paciente['id'], ['nome' => $possivelNome]);
                    $perfil = session()->get("perfil_{$this->usuarioId}_{$numero}") ?? [];
                    $perfil['nome'] = $possivelNome;
                    session()->set("perfil_{$this->usuarioId}_{$numero}", $perfil);
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

                session()->set("historico_{$this->usuarioId}_{$numero}", $historico);
                $this->updateSessaoByNumero($numero, $this->usuarioId, [
                    'etapa'                   => $novaEtapa,
                    'ultima_mensagem_usuario' => $mensagem,
                    'ultima_resposta_ia'      => $respLocal,
                    'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
                    'usuario_id'              => $this->usuarioId,
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

            // Prompt (pode puxar por etapa atual se quiser; aqui uso padrão)
            $prompt = get_prompt_padrao();

            // Monta mensagens para IA
            $mensagensIA = [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'system', 'content' => $continuityGuard],
            ];
            foreach ($historico as $msg) {
                if (isset($msg['role'], $msg['content'])) $mensagensIA[] = $msg;
            }
            $mensagensIA[] = ['role' => 'user', 'content' => $mensagem];

            // Latência simulada leve (3–5s)
            sleep(3);

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

            // Divide em até 2 bolhas
            $partes = $this->dividirEm2MensagensCoesas($respostaFull, 220);
            if (empty($partes)) $partes = [$respostaFull];

            // Atualiza histórico e persiste
            $historico[] = ['role' => 'user', 'content' => $mensagem, 'ts' => date('c')];
            foreach ($partes as $p) {
                $historico[] = ['role' => 'assistant', 'content' => $p, 'ts' => date('c')];
            }

            session()->set("historico_{$this->usuarioId}_{$numero}", $historico);
            $this->updateSessaoByNumero($numero, $this->usuarioId, [
                'etapa'                   => $novaEtapa,
                'ultima_mensagem_usuario' => $mensagem,
                'ultima_resposta_ia'      => end($partes),
                'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
                'usuario_id'              => $this->usuarioId,
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

    /** número de teste por usuário (evita colisão entre usuários) */
    private function numeroTeste(): string
    {
        $uid = (int) ($this->usuarioId ?? 0);
        // 99999 + 6 dígitos do usuário => 11 dígitos
        return '99999' . str_pad((string)$uid, 6, '0', STR_PAD_LEFT);
    }

    /** Atualiza um registro de sessão pelo par (usuario_id, numero). */
    private function updateSessaoByNumero(string $numero, int $usuarioId, array $data): bool
    {
        $numero = preg_replace('/\D+/', '', $numero);
        $sessaoModel = new SessaoModel();
        return (bool)$sessaoModel->where('numero', $numero)
            ->where('usuario_id', $usuarioId)
            ->set($data)
            ->update();
    }

    /** Carrega nome do perfil da sessão; se não houver, pega de pacientes e salva. */
    private function carregarPerfil(string $numero): array
    {
        $perfil = session()->get("perfil_{$this->usuarioId}_{$numero}") ?? [];
        if (!empty($perfil['nome'])) return $perfil;

        $pacienteModel = new PacienteModel();
        $pac = $pacienteModel->where('telefone', $numero)->where('usuario_id', $this->usuarioId)->first();
        if ($pac && !empty($pac['nome'])) {
            $perfil['nome'] = $pac['nome'];
            session()->set("perfil_{$this->usuarioId}_{$numero}", $perfil);
        }
        return $perfil;
    }

    /** Divide texto em bolhas curtas */
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

    /** Divide em até 2 mensagens coesas */
    private function dividirEm2MensagensCoesas(string $texto, int $limite = 220): array
    {
        $t = trim(preg_replace("/\s+/", " ", $texto));
        if ($t === '') return [];

        if (mb_strlen($t, 'UTF-8') <= $limite) {
            return [$t];
        }

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
            return [$t];
        }

        if (mb_strlen($primeira, 'UTF-8') === 0) {
            return $this->fallbackPorPalavras($t, $limite);
        }

        $segunda = trim(implode(' ', $restante));
        return array_values(array_filter([$primeira, $segunda], fn($x) => $x !== ''));
    }

    private function fallbackPorPalavras(string $t, int $limite): array
    {
        if (mb_strlen($t, 'UTF-8') <= $limite) return [$t];

        $corte = $limite;
        $compr = mb_strlen($t, 'UTF-8');

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
        $numero = $this->numeroTeste();

        // limpa sessão
        session()->remove("historico_{$this->usuarioId}_{$numero}");
        // session()->remove("perfil_{$this->usuarioId}_{$numero}"); // se quiser limpar o nome também

        // zera histórico no banco (escopo do usuário)
        $this->updateSessaoByNumero($numero, $this->usuarioId, [
            'ultima_mensagem_usuario' => null,
            'ultima_resposta_ia'      => null,
            'historico'               => json_encode([], JSON_UNESCAPED_UNICODE),
            'usuario_id'              => $this->usuarioId,
        ]);

        // reseta carimbos de cache
        $cache = \Config\Services::cache();
        $cache->delete("stamp_{$this->usuarioId}_{$numero}");
        $cache->delete("ia_lock_{$this->usuarioId}_{$numero}");

        return $this->response->setJSON(['ok' => true]);
    }

    // GET /configuracaoia/etapas
public function etapas()
{
    $sessaoModel = new \App\Models\SessaoModel();

    $usuarioId   = (int) (session()->get('usuario_id')   ?? 0) ?: null;
    $assinanteId = (int) (session()->get('assinante_id') ?? 0) ?: null;

    // usar só usuarioId; o SessaoModel já resolve assinante internamente
    if (!$usuarioId) {
        return $this->response->setJSON([]); // sem sessão de usuário
    }

    $lista = $sessaoModel->listarEtapasUsuario($usuarioId);
    // garante array simples de strings
    if (!empty($lista) && is_array($lista) && isset($lista[0]) && is_array($lista[0]) && isset($lista[0]['etapa'])) {
        $lista = array_map(fn($r) => (string)$r['etapa'], $lista);
    }

    // ordena de forma estável
    sort($lista, SORT_NATURAL | SORT_FLAG_CASE);

    return $this->response->setJSON(array_values(array_unique($lista)));
}

}
