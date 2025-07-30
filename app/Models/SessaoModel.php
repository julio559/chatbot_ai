<?php

namespace App\Models;

use CodeIgniter\Model;

class SessaoModel extends Model
{
    protected $table = 'sessoes';
    protected $primaryKey = 'numero';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';

    // ✅ Adicione os novos campos aqui
    protected $allowedFields = [
        'numero',
        'etapa',
        'ultima_mensagem_usuario',
        'ultima_resposta_ia'
    ];

    public function getOuCriarSessao(string $numero)
    {
        $sessao = $this->where('numero', $numero)->first();

        if ($sessao) {
            return $sessao;
        }

        // Cria nova sessão com valores padrão
        $this->insert([
            'numero' => $numero,
            'etapa' => 'inicio',
            'ultima_mensagem_usuario' => null,
            'ultima_resposta_ia' => null
        ]);

        return $this->where('numero', $numero)->first();
    }

    public function atualizarEtapa($numero, $novaEtapa)
    {
        $this->update($numero, ['etapa' => $novaEtapa]);
    }
}
