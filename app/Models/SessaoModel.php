<?php

namespace App\Models;

use CodeIgniter\Model;

class SessaoModel extends Model
{
    // Definindo a tabela e a chave primária
    protected $table = 'sessoes';
    protected $primaryKey = 'numero';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';

    // Campos permitidos para inserção e atualização
    protected $allowedFields = [
        'numero',
        'etapa',
        'ultima_mensagem_usuario',
        'ultima_resposta_ia',
        'etapa_lead',
        'historico' // NOVO: histórico completo da conversa (JSON)
    ];

    // Pega todos os leads de uma etapa específica
    public function getLeadsPorEtapa($etapa)
    {
        return $this->where('etapa', $etapa)->findAll();
    }

    // Atualiza apenas a etapa de um lead
    public function atualizarEtapa($numero, $novaEtapa)
    {
        $this->update($numero, ['etapa' => $novaEtapa]);
    }

    // Cria ou retorna a sessão existente
    public function getOuCriarSessao(string $numero)
    {
        $sessao = $this->where('numero', $numero)->first();

        if ($sessao) {
            return $sessao;
        }

        // Cria nova sessão com histórico vazio
        $this->insert([
            'numero' => $numero,
            'etapa' => 'inicio',
            'ultima_mensagem_usuario' => null,
            'ultima_resposta_ia' => null,
            'etapa_lead' => 'inicio',
            'historico' => json_encode([]) // inicia histórico vazio
        ]);

        return $this->where('numero', $numero)->first();
    }
}
