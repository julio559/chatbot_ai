<?php

namespace App\Models;

use CodeIgniter\Model;

class ConfigIaModel extends Model
{
    protected $table = 'config_ia';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'assinante_id',
        'etapa_base',
        'tempo_resposta',
        'prompt_base',
        'modo_formal',
        'permite_respostas_longas',
        'permite_redirecionamento',
    ];
    protected $useTimestamps = false; // Desativa timestamps

    // Função para pegar todas as etapas
    public function getAllEtapas()
    {
        return $this->findAll();
    }
}
