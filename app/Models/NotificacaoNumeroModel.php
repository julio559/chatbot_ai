<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificacaoRegraModel extends Model
{
    protected $table         = 'notificacoes_regras';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['etapa', 'mensagem_template', 'ativo'];
    protected $useTimestamps = false;
}
