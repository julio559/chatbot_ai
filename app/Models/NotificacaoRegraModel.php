<?php
namespace App\Models;

use CodeIgniter\Model;

class NotificacaoRegraModel extends Model
{
    protected $table       = 'notificacoes_regras';
    protected $primaryKey  = 'id';
    protected $returnType  = 'array';

    protected $allowedFields = [
        'assinante_id',
        'etapa',
        'mensagem_template',
        'ativo',
        'criado_em',
        'atualizado_em',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
}
