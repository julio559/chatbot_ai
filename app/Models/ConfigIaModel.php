<?php
declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ConfigIaModel extends Model
{
    // Ajuste o nome da tabela conforme seu banco
    // (pelos trechos enviados, você usa 'config_ia')
    protected $table          = 'config_ia';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;

    /**
     * Campos liberados pra insert/update
     * Inclui 'ia_pode_responder' e 'ordem'
     */
protected $allowedFields = [
  'assinante_id','etapa_atual','prompt_base','tempo_resposta',
  'modo_formal','permite_respostas_longas','permite_redirecionamento',
  'ia_pode_responder','ordem',
  'instancia_preferida_token','instancia_preferida_msisdn',
  'criado_em','atualizado_em'
];


    /**
     * Habilite timestamps se você tiver as colunas.
     * Caso não existam, mantenha como false para evitar erro.
     */
    protected $useTimestamps = false; // mude para true se tiver as colunas abaixo
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';

    // Regras de validação (opcional, pode ajustar ao seu gosto)
    protected $validationRules = [
        'assinante_id' => 'required|is_natural_no_zero',
        'etapa_atual'  => 'required|string|min_length[2]|max_length[100]',
        'tempo_resposta' => 'permit_empty|is_natural',
        'ia_pode_responder' => 'in_list[0,1]',
        'modo_formal'               => 'in_list[0,1]',
        'permite_respostas_longas'  => 'in_list[0,1]',
        'permite_redirecionamento'  => 'in_list[0,1]',
    ];

    protected $validationMessages = [];

    protected $skipValidation = false;
}
