<?php
namespace App\Models;

use CodeIgniter\Model;

class AprendizagemModel extends Model
{
    protected $table            = 'aprendizagens';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'usuario_id',
        'assinante_id',
        'titulo',
        'conteudo',
        'tags',
        'ativo',
        'criado_em',
        'atualizado_em',
    ];

    protected $useTimestamps = false; // usamos TIMESTAMP do banco
}
