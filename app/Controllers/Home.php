<?php
namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

class Home extends Controller
{
    protected ?int $usuarioId   = null;
    protected ?int $assinanteId = null;

    public function __construct()
    {
        helper(['url']);
        $this->usuarioId   = (int) (session()->get('usuario_id')   ?? 0) ?: null;
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

        // Período (today|7d|30d) => intervalo [ini, fim]
        [$ini, $fim, $iniPrev, $fimPrev] = $this->resolvePeriodo(
            (string) ($this->request->getGet('periodo') ?? '30d')
        );

        // -------- KPIs --------

        // taxa de resposta (0..1): média da view v_taxa_resposta no período
        $taxaAtual = (float) ($db->query("
            SELECT AVG(taxa) v
            FROM v_taxa_resposta
            WHERE dia BETWEEN ? AND ?
        ", [$ini, $fim])->getRow('array')['v'] ?? 0);

        $taxaPrev = (float) ($db->query("
            SELECT AVG(taxa) v
            FROM v_taxa_resposta
            WHERE dia BETWEEN ? AND ?
        ", [$iniPrev, $fimPrev])->getRow('array')['v'] ?? 0);

        $taxaTrend = $this->trend($taxaPrev, $taxaAtual);

        // tempo médio de resposta (min)
        $tempoAtual = (float) ($db->query("
            SELECT AVG(latencia_s)/60 v
            FROM v_respostas r
            JOIN sessoes s ON s.numero = r.numero
            WHERE s.usuario_id = ?
              AND r.resposta_em BETWEEN ? AND ?
        ", [$this->usuarioId, $ini . ' 00:00:00', $fim . ' 23:59:59'])->getRow('array')['v'] ?? 0);

        $tempoPrev = (float) ($db->query("
            SELECT AVG(latencia_s)/60 v
            FROM v_respostas r
            JOIN sessoes s ON s.numero = r.numero
            WHERE s.usuario_id = ?
              AND r.resposta_em BETWEEN ? AND ?
        ", [$this->usuarioId, $iniPrev . ' 00:00:00', $fimPrev . ' 23:59:59'])->getRow('array')['v'] ?? 0);

        $tempoTrend = $this->trend($tempoPrev, $tempoAtual, true); // true = quanto menor melhor

        // conversões hoje (conta conversões do dia do usuário)
        $convHoje = (int) ($db->query("
            SELECT COUNT(*) c
            FROM conversoes c
            JOIN sessoes s ON s.numero = c.numero
            WHERE s.usuario_id = ?
              AND DATE(c.created_at) = CURDATE()
        ", [$this->usuarioId])->getRow('array')['c'] ?? 0);

        // receita do mês (somatório do mês atual)
        $receitaMes = (float) ($db->query("
            SELECT COALESCE(SUM(c.valor),0) v
            FROM conversoes c
            JOIN sessoes s ON s.numero = c.numero
            WHERE s.usuario_id = ?
              AND YEAR(c.created_at) = YEAR(CURDATE())
              AND MONTH(c.created_at) = MONTH(CURDATE())
        ", [$this->usuarioId])->getRow('array')['v'] ?? 0);

        // receita mês anterior (para trend simples)
        $receitaMesPrev = (float) ($db->query("
            SELECT COALESCE(SUM(c.valor),0) v
            FROM conversoes c
            JOIN sessoes s ON s.numero = c.numero
            WHERE s.usuario_id = ?
              AND DATE_FORMAT(c.created_at,'%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m')
        ", [$this->usuarioId])->getRow('array')['v'] ?? 0);

        $receitaTrend = $this->trend($receitaMesPrev, $receitaMes);

        // conversas ativas (atividade nas últimas 24h)
        $conversasAtivas = (int) ($db->query("
            SELECT COUNT(*) c
            FROM sessoes
            WHERE usuario_id = ?
              AND data_atualizacao >= (NOW() - INTERVAL 24 HOUR)
        ", [$this->usuarioId])->getRow('array')['c'] ?? 0);

        // -------- Funil (sessões do usuário) --------
        $funil = $db->query("
            SELECT
              SUM(CASE WHEN etapa_lead IS NULL OR etapa_lead IN ('entrada','prospect','inicio') THEN 1 ELSE 0 END) AS prospects,
              SUM(CASE WHEN etapa_lead IN ('qualificado','lead_qualificado') THEN 1 ELSE 0 END)                 AS qualificados,
              SUM(CASE WHEN etapa_lead IN ('oportunidade','agendamento','financeiro') THEN 1 ELSE 0 END)        AS oportunidades,
              SUM(CASE WHEN etapa_lead IN ('finalizado','fechado','fechamento') THEN 1 ELSE 0 END)              AS fechamentos
            FROM sessoes
            WHERE usuario_id = ?
        ", [$this->usuarioId])->getRowArray() ?? [
            'prospects'=>0,'qualificados'=>0,'oportunidades'=>0,'fechamentos'=>0
        ];

        // -------- Canais (apenas do usuário) --------
        $canais = $db->query("
            SELECT
              s.canal AS nome,
              COALESCE(SUM(CASE WHEN r.user_msg_id IS NOT NULL THEN 1 ELSE 0 END)
                       / NULLIF(SUM(CASE WHEN cm.role='user' THEN 1 ELSE 0 END),0),0)  AS taxa_resposta,
              COALESCE(AVG(r.latencia_s)/60,0)                                         AS tempo_medio_min,
              COALESCE(COUNT(conv.id),0)                                               AS conversoes,
              COALESCE(SUM(conv.valor),0)                                              AS receita
            FROM sessoes s
            LEFT JOIN chat_mensagens cm ON cm.numero = s.numero
            LEFT JOIN v_respostas r     ON r.user_msg_id = cm.id
            LEFT JOIN conversoes conv   ON conv.numero = s.numero
            WHERE s.usuario_id = ?
            GROUP BY s.canal
            ORDER BY receita DESC, conversoes DESC
        ", [$this->usuarioId])->getResultArray();

        // flag "critico" (ex.: taxa_resposta < 0.1)
        $canais = array_map(function($c){
            $c['taxa_resposta']   = (float) $c['taxa_resposta'];
            $c['tempo_medio_min'] = (float) $c['tempo_medio_min'];
            $c['conversoes']      = (int)   $c['conversoes'];
            $c['receita']         = (float) $c['receita'];
            $c['critico']         = $c['taxa_resposta'] < 0.10;
            $c['nome']            = $c['nome'] ?: 'whatsapp';
            return $c;
        }, $canais);

        $payload = [
            'kpis' => [
                'taxa_resposta'     => $taxaAtual,         // 0..1
                'taxa_trend'        => $taxaTrend,         // string "+x.x%" ou "—"
                'tempo_medio_min'   => $tempoAtual,        // minutos
                'tempo_trend'       => $tempoTrend,        // string (quanto menor, melhor)
                'conversoes_hoje'   => $convHoje,
                'receita_mes'       => $receitaMes,
                'receita_trend'     => $receitaTrend,
                'conversas_ativas'  => $conversasAtivas,
            ],
            'funil'  => [
                'prospects'     => (int)($funil['prospects'] ?? 0),
                'qualificados'  => (int)($funil['qualificados'] ?? 0),
                'oportunidades' => (int)($funil['oportunidades'] ?? 0),
                'fechamentos'   => (int)($funil['fechamentos'] ?? 0),
            ],
            'canais' => $canais,
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

    /** trend: monta string de variação percentual (+x.x% / -x.x% / —)
     *  $lowerIsBetter: quando true, melhora é queda (ex.: tempo médio)
     */
    private function trend(float $prev, float $curr, bool $lowerIsBetter = false): string
    {
        if ($prev <= 0 && $curr <= 0) return '—';
        if ($prev <= 0) return '+∞%';

        $delta = ($curr - $prev) / $prev * 100.0;
        $val   = number_format(abs($delta), 1, '.', '');
        $up    = $delta >= 0;

        // se "quanto menor é melhor", invertimos o sinal lógico
        if ($lowerIsBetter) $up = !$up;

        return ($up ? '+':'-') . $val . '%';
    }
}
