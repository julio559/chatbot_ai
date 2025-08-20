<?php
namespace App\Models;

use CodeIgniter\Model;

class AssinanteModel extends Model
{
    protected $table            = 'assinantes';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;

    // só o que realmente existe/precisa ser gravado
    protected $allowedFields = ['nome', 'telefone'];

    // DESLIGA timestamps automáticos (deixe o DEFAULT do banco preencher criado_em)
    protected $useTimestamps = false;

    protected $validationRules = [
        'nome'     => 'required|min_length[2]|max_length[100]',
        'telefone' => 'required|min_length[8]|max_length[20]',
    ];

    public function getByTelefone(string $telefone): ?array
    {
        $tel = preg_replace('/\D+/', '', $telefone);
        return $this->where('telefone', $tel)->first() ?: null;
    }
}
