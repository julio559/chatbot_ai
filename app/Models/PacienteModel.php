<?php
namespace App\Models;

use CodeIgniter\Model;

class PacienteModel extends Model
{
    protected $table            = 'pacientes';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;

    protected $allowedFields    = [
        'usuario_id',
        'nome',
        'telefone',
        'ultimo_contato',
        'origem_contato',
        'criado_em',
        'atualizado_em',
    ];

    protected $useTimestamps    = true;              // a tabela tem criado_em / atualizado_em
    protected $createdField     = 'criado_em';
    protected $updatedField     = 'atualizado_em';

    protected $validationRules = [
        'nome'            => 'required|min_length[2]|max_length[120]',
        'telefone'        => 'required|min_length[8]|max_length[20]',
        'usuario_id'      => 'permit_empty|integer',
        // 1=WhatsApp (ajuste se usar outra convenção)
        'origem_contato'  => 'permit_empty|in_list[0,1,2,3,4]',
    ];
    protected $skipValidation = false;

    protected $beforeInsert = ['normalizeFields'];
    protected $beforeUpdate = ['normalizeFields'];

    protected function normalizeFields(array $data)
    {
        if (!isset($data['data'])) return $data;

        // telefone: apenas dígitos
        if (array_key_exists('telefone', $data['data'])) {
            $data['data']['telefone'] = preg_replace('/\D+/', '', (string)$data['data']['telefone']);
        }

        // origem_contato default = 1 (WhatsApp) se não vier
        if (!isset($data['data']['origem_contato']) || $data['data']['origem_contato'] === '') {
            $data['data']['origem_contato'] = 1;
        }

        return $data;
    }

    /** Busca paciente por telefone (normalizado) dentro do escopo de um usuário. */
    public function findByTelefoneUsuario(?int $usuarioId, string $telefone): ?array
    {
        $tel = preg_replace('/\D+/', '', $telefone);
        $qb  = $this->where('telefone', $tel);
        if ($usuarioId) {
            $qb->where('usuario_id', $usuarioId);
        } else {
            $qb->where('usuario_id', null); // procura marcados como sem dono
        }
        return $qb->first() ?: null;
    }

    /** Upsert por (usuario_id, telefone). Retorna o ID do paciente. */
    public function upsertByUsuarioTelefone(?int $usuarioId, string $telefone, array $payload): int
    {
        $tel = preg_replace('/\D+/', '', $telefone);

        $qb = $this->where('telefone', $tel);
        if ($usuarioId) $qb->where('usuario_id', $usuarioId); else $qb->where('usuario_id', null);

        $row = $qb->first();

        $payload['telefone']   = $tel;
        $payload['usuario_id'] = $usuarioId;

        if ($row) {
            $this->update($row['id'], $payload);
            return (int)$row['id'];
        }

        return (int)$this->insert($payload);
    }
}
