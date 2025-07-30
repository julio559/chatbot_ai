<?php

namespace App\Models;

use CodeIgniter\Model;

class PacienteModel extends Model
{
    protected $table = 'pacientes';
    protected $primaryKey = 'id';
    protected $allowedFields = ['nome', 'telefone', 'ultimo_contato'];
    protected $useTimestamps = true;
    protected $createdField = 'criado_em';
    protected $updatedField = 'atualizado_em';
}
