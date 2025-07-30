<?php
namespace App\Controllers;

use App\Models\PacienteModel;
use App\Models\SessaoModel;
use CodeIgniter\Controller;

class Home extends Controller
{
  public function index()
{
    $pacienteModel = new PacienteModel();
    $sessaoModel = new SessaoModel();

    // Dados reais do banco
    $totalPacientes = $pacienteModel->countAll();
    $conversasAtivas = $sessaoModel->where('etapa !=', 'fim')->countAllResults();
    $agendamentos = $sessaoModel->where('etapa', 'agendamento')->countAllResults();
    $encaminhadosFinanceiro = $sessaoModel->where('etapa', 'financeiro')->countAllResults();

    // Últimos pacientes por último contato
    $ultimosPacientes = $pacienteModel->orderBy('ultimo_contato', 'DESC')->limit(5)->findAll();

    // Simulação de dados de gráfico - em produção, use uma query com agrupamento por data
    $graficoIA = [
        'Seg' => $pacienteModel->like('ultimo_contato', date('Y-m-d', strtotime('monday this week')))->countAllResults(),
        'Ter' => $pacienteModel->like('ultimo_contato', date('Y-m-d', strtotime('tuesday this week')))->countAllResults(),
        'Qua' => $pacienteModel->like('ultimo_contato', date('Y-m-d', strtotime('wednesday this week')))->countAllResults(),
        'Qui' => $pacienteModel->like('ultimo_contato', date('Y-m-d', strtotime('thursday this week')))->countAllResults(),
        'Sex' => $pacienteModel->like('ultimo_contato', date('Y-m-d', strtotime('friday this week')))->countAllResults(),
    ];

    $dados = [
        'totalPacientes' => $totalPacientes,
        'conversasAtivas' => $conversasAtivas,
        'agendamentos' => $agendamentos,
        'financeiro' => $encaminhadosFinanceiro,
        'ultimosPacientes' => $ultimosPacientes,
        'graficoIA' => $graficoIA
    ];

    return view('home', $dados); // substitua "home" pelo nome real da sua view se for diferente
}

}
