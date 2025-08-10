<?php

namespace App\Controllers;

use App\Models\SessaoModel;
use App\Models\PacienteModel;
use CodeIgniter\Controller;

class Painel extends Controller
{
public function aguardando()
{
    $db = \Config\Database::connect();

    $sessoes = $db->table('sessoes s')
        ->select('s.*, p.nome')
        ->join('pacientes p', 'p.telefone = s.numero', 'left')
        ->whereIn('s.etapa', ['agendamento', 'orcamento', 'financeiro'])
        ->orderBy('s.data_atualizacao', 'DESC')
        ->get()
        ->getResultArray();

    foreach ($sessoes as &$sessao) {
        $sessao['nome'] = $sessao['nome'] ?: 'Sem nome';
    }

    return view('painel_aguardando', ['sessoes' => $sessoes]);
}

}