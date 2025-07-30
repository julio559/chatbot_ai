<?php

namespace App\Models;

use CodeIgniter\Model;

class AssinanteModel extends Model
{
    protected $table = 'assinantes';
    protected $primaryKey = 'id';
    protected $allowedFields = ['nome', 'telefone'];
    protected $useTimestamps = true;
    protected $createdField  = 'criado_em';
}
