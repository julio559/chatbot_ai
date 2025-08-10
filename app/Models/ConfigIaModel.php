<?php
namespace App\Models;

use CodeIgniter\Model;

class ConfigIaModel extends Model
{
    protected $table            = 'config_ia';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;

    protected $protectFields    = true;
  // app/Models/ConfigIaModel.php
protected $allowedFields = [
  'assinante_id',
  'etapa_atual',
  'tempo_resposta',
  'prompt_base',
  'modo_formal',
  'permite_respostas_longas',
  'permite_redirecionamento',
  'prompt_etapa',
  'criado_em',
  'ordem',          // 👈 novo
];


    // Se quiser timestamps automáticos do CI4, configure conforme sua tabela.
    protected $useTimestamps = false;
}
