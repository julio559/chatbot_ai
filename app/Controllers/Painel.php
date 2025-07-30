<?php

namespace App\Controllers;

use App\Models\SessaoModel;
use App\Models\PacienteModel;
use CodeIgniter\Controller;

class Painel extends Controller
{
    public function aguardando()
    {
        $sessaoModel = new SessaoModel();
        $pacienteModel = new PacienteModel();

        // Busca todas as sessões com etapa 'agendamento' ou 'orcamento'
        $sessoes = $sessaoModel
            ->whereIn('etapa', ['agendamento', 'orcamento'])
            ->findAll();

        // Adiciona o nome do paciente a cada sessão
        foreach ($sessoes as &$sessao) {
            $paciente = $pacienteModel->where('telefone', $sessao['numero'])->first();
            $sessao['nome'] = $paciente['nome'] ?? 'Sem nome';
        }

        return view('painel_aguardando', ['sessoes' => $sessoes]);
    }
}