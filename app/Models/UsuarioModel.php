<?php
namespace App\Models;

use CodeIgniter\Model;

class UsuarioModel extends Model
{
    protected $table            = 'usuarios';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;

    protected $allowedFields = [
        'assinante_id',
        'nome',
        'email',
        'senha_hash',
        'telefone_principal',
        'status',
    ];

    // AQUI pode manter timestamps: a tabela tem criado_em e atualizado_em
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';

    protected $validationRules = [
        'assinante_id'       => 'required|integer',
        'nome'               => 'required|min_length[2]|max_length[120]',
        'email'              => 'required|valid_email|max_length[150]',
        'senha_hash'         => 'required',
        'telefone_principal' => 'required|min_length[8]|max_length[30]',
        'status'             => 'permit_empty|in_list[ativo,inativo,suspenso]',
    ];
}
