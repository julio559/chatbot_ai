<?php
namespace App\Models;

use CodeIgniter\Model;

class SessaoModel extends Model
{
    protected $table            = 'sessoes';
    protected $primaryKey       = 'numero';     // PK é 'numero', não 'id'
    protected $useAutoIncrement = false;        // não há auto-increment em 'sessoes'
    protected $returnType       = 'array';

    protected $allowedFields = [
        'numero',
        'linha_numero',              // existe na tabela
        'usuario_id',
        'canal',
        'etapa',
        'ultima_mensagem_usuario',
        'ultima_resposta_ia',
        'etapa_lead',
        'historico',
        'data_atualizacao',
        'criado_em',
        'atualizado_em',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';

    protected $beforeInsert  = ['normalizeAndTouch', 'ensureOwner'];
    protected $beforeUpdate  = ['normalizeAndTouch', 'ensureOwnerOnUpdate'];

    protected function normalizeAndTouch(array $data)
    {
        if (isset($data['data']['numero'])) {
            $data['data']['numero'] = preg_replace('/\D+/', '', (string)$data['data']['numero']);
        }
        $data['data']['data_atualizacao'] = date('Y-m-d H:i:s');

        if (isset($data['data']['historico']) && is_array($data['data']['historico'])) {
            $data['data']['historico'] = json_encode($data['data']['historico'], JSON_UNESCAPED_UNICODE);
        }
        return $data;
    }

    /** Garante usuario_id no INSERT */
    protected function ensureOwner(array $data)
    {
        $uid = $data['data']['usuario_id'] ?? null;
        if (!$uid) {
            $num = $data['data']['numero'] ?? '';
            $uid = $this->resolverUsuarioPorNumero($num)
                ?: (int)(session('usuario_id') ?? 0);
        }
        if (!$uid) {
            throw new \RuntimeException('usuario_id obrigatório para abrir sessão.');
        }
        $data['data']['usuario_id'] = (int)$uid;
        return $data;
    }

    /** Em UPDATE, não zere usuario_id; se vier faltando, mantém o atual */
    protected function ensureOwnerOnUpdate(array $data)
    {
        if (!array_key_exists('usuario_id', $data['data'])) return $data;

        $uid = $data['data']['usuario_id'];
        if (!$uid) {
            // recupera o registro atual e reaplica
            $pkVal = $data['id'][0] ?? null; // aqui é o 'numero'
            if ($pkVal) {
                $curr = $this->find($pkVal);
                if ($curr && !empty($curr['usuario_id'])) {
                    $data['data']['usuario_id'] = (int)$curr['usuario_id'];
                    return $data;
                }
            }
            throw new \RuntimeException('usuario_id não pode ser NULL em atualização de sessão.');
        }
        $data['data']['usuario_id'] = (int)$uid;
        return $data;
    }

    /** Lista leads por etapa (sempre no escopo do dono) */
    public function getLeadsPorEtapa(string $etapa, int $usuarioId): array
    {
        return $this->where('etapa', $etapa)
                    ->where('usuario_id', $usuarioId)
                    ->findAll();
    }

    /** Atualiza etapa para (usuario_id, numero) — evita colisão entre usuários */
    public function atualizarEtapa(string $numero, string $novaEtapa, int $usuarioId): bool
    {
        $numero = preg_replace('/\D+/', '', $numero);
        return (bool)$this->where('numero', $numero)
                         ->where('usuario_id', $usuarioId)
                         ->set(['etapa' => $novaEtapa])
                         ->update();
    }

    /**
     * Busca a sessão do par (usuario_id, numero) ou cria.
     * usuarioId é obrigatório (ou será resolvido). Nunca cria órfã.
     */
    public function getOuCriarSessao(string $numero, ?int $usuarioId = null): array
    {
        $numero = preg_replace('/\D+/', '', $numero);

        if (!$usuarioId) {
            $usuarioId = (int)(session('usuario_id') ?? 0);
        }
        if (!$usuarioId) {
            $usuarioId = $this->resolverUsuarioPorNumero($numero) ?? 0;
        }
        if (!$usuarioId) {
            throw new \RuntimeException('usuario_id obrigatório para abrir sessão.');
        }

        $row = $this->where('numero', $numero)
                    ->where('usuario_id', $usuarioId)
                    ->first();
        if ($row) return $row;

        // como a PK é 'numero' (sem auto-increment), não peça ID de retorno
        $this->insert([
            'numero'                  => $numero,
            'usuario_id'              => $usuarioId,
            'etapa'                   => 'inicio',
            'etapa_lead'              => 'inicio',
            'ultima_mensagem_usuario' => null,
            'ultima_resposta_ia'      => null,
            'historico'               => [],
            'data_atualizacao'        => date('Y-m-d H:i:s'),
        ]);

        return $this->where('numero', $numero)
                    ->where('usuario_id', $usuarioId)
                    ->first();
    }

    /**
     * Resolve dono da linha:
     * 1) whatsapp_instancias.linha_msisdn -> usuario_id
     * 2) usuarios.telefone_principal (exato)
     * 3) por sufixo (8–11 dígitos)
     */
    private function resolverUsuarioPorNumero(string $numero): ?int
    {
        $numero = preg_replace('/\D+/', '', $numero);
        if ($numero === '') return null;

        $db = \Config\Database::connect();

        $u = $db->table('whatsapp_instancias')
                ->select('usuario_id')
                ->where('linha_msisdn', $numero)
                ->get()->getRowArray();
        if (!empty($u['usuario_id'])) return (int)$u['usuario_id'];

        $u = $db->table('usuarios')->select('id')
                ->where('telefone_principal', $numero)
                ->get()->getRowArray();
        if (!empty($u['id'])) return (int)$u['id'];

        for ($take = 11; $take >= 8; $take--) {
            if (strlen($numero) < $take) continue;
            $suf = substr($numero, -$take);
            $u = $db->query(
                "SELECT id FROM usuarios
                 WHERE telefone_principal LIKE CONCAT('%', ?)
                 ORDER BY LENGTH(telefone_principal) DESC
                 LIMIT 1",
                [$suf]
            )->getRowArray();
            if (!empty($u['id'])) return (int)$u['id'];
        }

        return null;
    }

    /**
     * Lista as etapas válidas para o usuário (resolvendo o assinante e lendo de config_ia.etapa_atual).
     * Retorna array simples de strings (ordenado e único).
     */
    public function listarEtapasUsuario(int $usuarioId): array
    {
        $db = \Config\Database::connect();

        // pega o assinante do usuário
        $u = $db->table('usuarios')->select('assinante_id')->where('id', $usuarioId)->get()->getRowArray();
        $assinanteId = $u['assinante_id'] ?? null;
        if (!$assinanteId) return [];

        // em config_ia a coluna é 'etapa_atual' e o filtro é por 'assinante_id'
        $rows = $db->table('config_ia')
            ->select('etapa_atual AS etapa')
            ->where('assinante_id', (int)$assinanteId)
            ->groupBy('etapa_atual')
            ->orderBy('etapa_atual', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_unique(array_map(static fn($r) => (string)$r['etapa'], $rows)));
    }
}
