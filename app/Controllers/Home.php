<?php
namespace App\Controllers;

use CodeIgniter\Controller;

class Home extends Controller
{
    protected ?int $usuarioId   = null;
    protected ?int $assinanteId = null;

    public function __construct()
    {
        helper(['url']);
        $this->usuarioId   = (int) (session()->get('usuario_id')   ?? 0) ?: null;
        // fallback 1 até o login estar 100%
        $this->assinanteId = (int) (session()->get('assinante_id') ?? 0) ?: 1;
    }

    /* ================== VIEW ================== */
    public function index()
    {
        if (!$this->usuarioId) {
            return redirect()->to('/auth');
        }

        $db = \Config\Database::connect();

        // Dados do usuário (da sessão; fallback banco)
        $usuarioNome  = (string) (session()->get('usuario_nome')  ?? '');
        $usuarioEmail = (string) (session()->get('usuario_email') ?? '');
        if ($usuarioNome === '' || $usuarioEmail === '') {
            $usr = $db->table('usuarios')->select('nome, email')
                ->where('id', $this->usuarioId)->get()->getFirstRow('array');
            $usuarioNome  = $usuarioNome  ?: ($usr['nome']  ?? 'Usuário');
            $usuarioEmail = $usuarioEmail ?: ($usr['email'] ?? '');
        }

        $dados = [
            'usuario' => [
                'id'    => $this->usuarioId,
                'nome'  => $usuarioNome,
                'email' => $usuarioEmail,
            ],
            // O restante vem via fetch('/dashboard/metrics')
        ];

        return view('home', $dados);
    }

    /* ================== API: /dashboard/metrics ==================
       Retorna JSON para o front do dashboard (KPIs, funil, canais).
    =============================================================== */
    public function metrics()
    {
        if (!$this->usuarioId) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'unauthorized']);
        }

        $db = \Config\Database::connect();

        // Período (today|7d|30d) => intervalo [ini, fim] (datas)
        $periodo = (string) ($this->request->getGet('periodo') ?? '30d');
        [$ini, $fim, $iniPrev, $fimPrev] = $this->resolvePeriodo($periodo);
        $t0 = $ini . ' 00:00:00';
        $t1 = $fim . ' 23:59:59';
        $p0 = $iniPrev . ' 00:00:00';
        $p1 = $fimPrev . ' 23:59:59';

        /* ================= KPI: Taxa de resposta =================
         * Definição: (# user msgs respondidas no período) / (# user msgs no período)
         * - User msgs: chat_mensagens.role='user'
         * - Respondidas: v_respostas.user_msg_id (1 linha por user msg responded)
         * - Escopo: apenas sessões do usuário logado
         */
        $denAtual = (int) ($db->query("
            SELECT COUNT(*) c
            FROM chat_mensagens cm
            JOIN sessoes s ON s.numero = cm.numero
            WHERE s.usuario_id = ?
              AND cm.role = 'user'
              AND cm.created_at BETWEEN ? AND ?
        ", [$this->usuarioId, $t0, $t1])->getRow('array')['c'] ?? 0);

        $numAtual = (int) ($db->query("
            SELECT COUNT(DISTINCT r.user_msg_id) c
            FROM v_respostas r
            JOIN sessoes s ON s.numero = r.numero
            WHERE s.usuario_id = ?
              AND r.resposta_em BETWEEN ? AND ?
        ", [$this->usuarioId, $t0, $t1])->getRow('array')['c'] ?? 0);

        $taxaAtual = $denAtual > 0 ? ($numAtual / $denAtual) : 0.0;

        // período anterior
        $denPrev = (int) ($db->query("
            SELECT COUNT(*) c
            FROM chat_mensagens cm
            JOIN sessoes s ON s.numero = cm.numero
            WHERE s.usuario_id = ?
              AND cm.role = 'user'
              AND cm.created_at BETWEEN ? AND ?
        ", [$this->usuarioId, $p0, $p1])->getRow('array')['c'] ?? 0);

        $numPrev = (int) ($db->query("
            SELECT COUNT(DISTINCT r.user_msg_id) c
            FROM v_respostas r
            JOIN sessoes s ON s.numero = r.numero
            WHERE s.usuario_id = ?
              AND r.resposta_em BETWEEN ? AND ?
        ", [$this->usuarioId, $p0, $p1])->getRow('array')['c'] ?? 0);

        $taxaPrev  = $denPrev > 0 ? ($numPrev / $denPrev) : 0.0;
        $taxaTrend = $this->trend($taxaPrev, $taxaAtual);

        /* ================= KPI: Tempo médio de resposta (min) ================= */
        $tempoAtual = (float) ($db->query("
            SELECT AVG(r.latencia_s)/60 v
            FROM v_respostas r
            JOIN sessoes s ON s.numero = r.numero
            WHERE s.usuario_id = ?
              AND r.resposta_em BETWEEN ? AND ?
        ", [$this->usuarioId, $t0, $t1])->getRow('array')['v'] ?? 0.0);

        $tempoPrev = (float) ($db->query("
            SELECT AVG(r.latencia_s)/60 v
            FROM v_respostas r
            JOIN sessoes s ON s.numero = r.numero
            WHERE s.usuario_id = ?
              AND r.resposta_em BETWEEN ? AND ?
        ", [$this->usuarioId, $p0, $p1])->getRow('array')['v'] ?? 0.0);

        $tempoTrend = $this->trend($tempoPrev, $tempoAtual, true); // menor = melhor

        /* ================= Outras KPIs ================= */
        $convHoje = (int) ($db->query("
            SELECT COUNT(*) c
            FROM conversoes c
            JOIN sessoes s ON s.numero = c.numero
            WHERE s.usuario_id = ?
              AND DATE(c.created_at) = CURDATE()
        ", [$this->usuarioId])->getRow('array')['c'] ?? 0);

        $receitaMes = (float) ($db->query("
            SELECT COALESCE(SUM(c.valor),0) v
            FROM conversoes c
            JOIN sessoes s ON s.numero = c.numero
            WHERE s.usuario_id = ?
              AND YEAR(c.created_at) = YEAR(CURDATE())
              AND MONTH(c.created_at) = MONTH(CURDATE())
        ", [$this->usuarioId])->getRow('array')['v'] ?? 0.0);

        $receitaMesPrev = (float) ($db->query("
            SELECT COALESCE(SUM(c.valor),0) v
            FROM conversoes c
            JOIN sessoes s ON s.numero = c.numero
            WHERE s.usuario_id = ?
              AND DATE_FORMAT(c.created_at,'%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m')
        ", [$this->usuarioId])->getRow('array')['v'] ?? 0.0);

        $receitaTrend = $this->trend($receitaMesPrev, $receitaMes);

        $conversasAtivas = (int) ($db->query("
            SELECT COUNT(*) c
            FROM sessoes
            WHERE usuario_id = ?
              AND data_atualizacao >= (NOW() - INTERVAL 24 HOUR)
        ", [$this->usuarioId])->getRow('array')['c'] ?? 0);

        /* ================= Funil (snapshot atual das sessões do usuário) =================
           Ajuste aqui o mapeamento das suas etapas → grupos de funil */
        $funil = $db->query("
            SELECT
              SUM(CASE WHEN s.etapa IS NULL OR s.etapa IN ('entrada','prospect','inicio') THEN 1 ELSE 0 END) AS prospects,
              SUM(CASE WHEN s.etapa IN ('qualificado','lead_qualificado') THEN 1 ELSE 0 END)                 AS qualificados,
              SUM(CASE WHEN s.etapa IN ('oportunidade','agendamento','financeiro') THEN 1 ELSE 0 END)        AS oportunidades,
              SUM(CASE WHEN s.etapa IN ('finalizado','fechado','fechamento') THEN 1 ELSE 0 END)              AS fechamentos
            FROM sessoes s
            WHERE s.usuario_id = ?
        ", [$this->usuarioId])->getRowArray() ?? [
            'prospects'=>0,'qualificados'=>0,'oportunidades'=>0,'fechamentos'=>0
        ];

        /* ================= Canais (apenas do usuário e dentro do período) =================
           Usamos subconsultas separadas para evitar multiplicação de linhas. */
        $canais = $db->query("
            SELECT
                base.nome,
                COALESCE(r.respondidos / NULLIF(cm.user_msgs,0), 0)   AS taxa_resposta,
                COALESCE(r.tempo_medio_min, 0)                        AS tempo_medio_min,
                COALESCE(conv.conversoes, 0)                          AS conversoes,
                COALESCE(conv.receita, 0)                             AS receita
            FROM
                (
                  SELECT COALESCE(NULLIF(s.canal,''),'whatsapp') AS nome
                  FROM sessoes s
                  WHERE s.usuario_id = ?
                  GROUP BY COALESCE(NULLIF(s.canal,''),'whatsapp')
                ) base
            LEFT JOIN
                (
                  SELECT COALESCE(NULLIF(s.canal,''),'whatsapp') AS nome,
                         COUNT(*) AS user_msgs
                  FROM chat_mensagens cm
                  JOIN sessoes s ON s.numero = cm.numero
                  WHERE s.usuario_id = ?
                    AND cm.role = 'user'
                    AND cm.created_at BETWEEN ? AND ?
                  GROUP BY COALESCE(NULLIF(s.canal,''),'whatsapp')
                ) cm ON cm.nome = base.nome
            LEFT JOIN
                (
                  SELECT COALESCE(NULLIF(s.canal,''),'whatsapp') AS nome,
                         COUNT(DISTINCT r.user_msg_id)           AS respondidos,
                         AVG(r.latencia_s)/60                    AS tempo_medio_min
                  FROM v_respostas r
                  JOIN sessoes s ON s.numero = r.numero
                  WHERE s.usuario_id = ?
                    AND r.resposta_em BETWEEN ? AND ?
                  GROUP BY COALESCE(NULLIF(s.canal,''),'whatsapp')
                ) r ON r.nome = base.nome
            LEFT JOIN
                (
                  SELECT COALESCE(NULLIF(s.canal,''),'whatsapp') AS nome,
                         COUNT(c.id)                              AS conversoes,
                         COALESCE(SUM(c.valor),0)                 AS receita
                  FROM conversoes c
                  JOIN sessoes s ON s.numero = c.numero
                  WHERE s.usuario_id = ?
                    AND c.created_at BETWEEN ? AND ?
                  GROUP BY COALESCE(NULLIF(s.canal,''),'whatsapp')
                ) conv ON conv.nome = base.nome
            ORDER BY receita DESC, conversoes DESC
        ", [
            $this->usuarioId,                // base
            $this->usuarioId, $t0, $t1,      // cm
            $this->usuarioId, $t0, $t1,      // r
            $this->usuarioId, $t0, $t1,      // conv
        ])->getResultArray();

        // Normaliza tipos/valores no PHP
        $canais = array_map(function($c) {
            return [
                'nome'            => $c['nome'] ?: 'whatsapp',
                'taxa_resposta'   => (float) $c['taxa_resposta'],
                'tempo_medio_min' => (float) $c['tempo_medio_min'],
                'conversoes'      => (int)   $c['conversoes'],
                'receita'         => (float) $c['receita'],
                'critico'         => ((float)$c['taxa_resposta']) < 0.10,
            ];
        }, $canais);

        $payload = [
            'kpis' => [
                'taxa_resposta'     => (float) $taxaAtual,     // 0..1
                'taxa_trend'        => $taxaTrend,             // "+x.x%" | "-x.x%" | "—"
                'tempo_medio_min'   => (float) $tempoAtual,    // minutos
                'tempo_trend'       => $tempoTrend,            // trend (menor é melhor)
                'conversoes_hoje'   => (int) $convHoje,
                'receita_mes'       => (float) $receitaMes,
                'receita_trend'     => $receitaTrend,
                'conversas_ativas'  => (int) $conversasAtivas,
            ],
            'funil'  => [
                'prospects'     => (int)($funil['prospects'] ?? 0),
                'qualificados'  => (int)($funil['qualificados'] ?? 0),
                'oportunidades' => (int)($funil['oportunidades'] ?? 0),
                'fechamentos'   => (int)($funil['fechamentos'] ?? 0),
            ],
            'canais' => $canais,
            'periodo' => [
                'selecionado' => $periodo,
                'ini' => $ini, 'fim' => $fim,
                'ini_prev' => $iniPrev, 'fim_prev' => $fimPrev,
            ],
        ];

        return $this->response->setJSON($payload);
    }

    /* ================== Helpers ================== */

    /** resolvePeriodo: retorna [ini, fim, iniPrev, fimPrev] (YYYY-mm-dd) */
    private function resolvePeriodo(string $per): array
    {
        $today = new \DateTimeImmutable('today');
        switch ($per) {
            case 'today':
                $ini = $today->format('Y-m-d'); $fim = $ini;
                $iniPrev = $today->modify('-1 day')->format('Y-m-d');
                $fimPrev = $iniPrev;
                break;
            case '7d':
                $fim = $today->format('Y-m-d');
                $ini = $today->modify('-6 days')->format('Y-m-d');
                $fimPrev = $today->modify('-7 days')->format('Y-m-d');
                $iniPrev = (new \DateTimeImmutable($fimPrev))->modify('-6 days')->format('Y-m-d');
                break;
            default: // 30d
                $fim = $today->format('Y-m-d');
                $ini = $today->modify('-29 days')->format('Y-m-d');
                $fimPrev = $today->modify('-30 days')->format('Y-m-d');
                $iniPrev = (new \DateTimeImmutable($fimPrev))->modify('-29 days')->format('Y-m-d');
        }
        return [$ini, $fim, $iniPrev, $fimPrev];
    }

    /**
     * trend: string de variação percentual (+x.x% / -x.x% / —)
     * $lowerIsBetter: quando true, melhora é queda (ex.: tempo médio)
     */
    private function trend(float $prev, float $curr, bool $lowerIsBetter = false): string
    {
        // Casos limites
        if ($prev <= 0 && $curr <= 0) return '—';
        if ($prev <= 0 && $curr > 0)  return '+∞%';
        if ($prev > 0 && $curr <= 0)  return $lowerIsBetter ? '+∞%' : '-100%';

        $delta = ($curr - $prev) / $prev * 100.0;
        $val   = number_format(abs($delta), 1, '.', '');
        $up    = $delta >= 0;

        // se "quanto menor é melhor", invertimos a leitura do “up”
        if ($lowerIsBetter) $up = !$up;

        return ($up ? '+' : '-') . $val . '%';
    }
}
