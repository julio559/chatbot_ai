<?php

namespace App\Models;

use CodeIgniter\Model;

class SessaoModel extends Model
{
    protected $table = 'sessoes';
    protected $allowedFields = ['numero', 'etapa'];
    protected $primaryKey = 'numero';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';

    public function getOuCriarSessao($numero)
    {
        $sessao = $this->find($numero);

        if (!$sessao) {
            $this->insert(['numero' => $numero, 'etapa' => 'inicio']);
            return ['numero' => $numero, 'etapa' => 'inicio'];
        }

        return $sessao;
    }

   public function atualizarEtapa($numero, $novaEtapa)
{
    $this->update($numero, ['etapa' => $novaEtapa]);
}

}
