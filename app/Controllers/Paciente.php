<?php
namespace App\Controllers;

use App\Models\PacienteModel;
use App\Models\SessaoModel;
use App\Models\ConfigIaModel;
use CodeIgniter\Controller;

class Paciente extends Controller
{
    public function index()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('pacientes');

        $builder->select('pacientes.*, sessoes.etapa');
        $builder->join('sessoes', 'sessoes.numero = pacientes.telefone', 'left');
        $builder->orderBy('pacientes.ultimo_contato', 'DESC');

        // 🔁 Buscar etapas do banco (config_ia)
        $configIaModel = new ConfigIaModel();
        $resultados = $configIaModel
            ->where('assinante_id', 1)
            ->orderBy('id', 'ASC')
            ->findAll();

        $etapas = [];
        foreach ($resultados as $linha) {
            $etapa = $linha['etapa_atual'];
            $etapas[$etapa] = ucfirst(str_replace('_', ' ', $etapa));
        }

        $dados['pacientes'] = $builder->get()->getResultArray();
        $dados['etapas'] = $etapas;

        return view('paciente', $dados);
    }

    public function atualizar($id)
    {
        $pacienteModel = new PacienteModel();
        $sessaoModel = new SessaoModel();

        $nome = $this->request->getPost('nome');
        $telefone = $this->request->getPost('telefone');
        $etapa = $this->request->getPost('etapa');

        // Atualiza o paciente
        $pacienteModel->update($id, [
            'nome' => $nome,
            'telefone' => $telefone
        ]);

        // Atualiza ou cria a sessão
        $sessaoExistente = $sessaoModel->find($telefone);

        if ($sessaoExistente) {
            $sessaoModel->update($telefone, ['etapa' => $etapa]);
        } else {
            $sessaoModel->insert(['numero' => $telefone, 'etapa' => $etapa]);
        }

        return redirect()->to('/paciente');
    }

    public function excluir($id)
    {
        $pacienteModel = new PacienteModel();
        $paciente = $pacienteModel->find($id);

        if ($paciente) {
            $telefone = $paciente['telefone'];

            // Exclui o paciente
            $pacienteModel->delete($id);

            // Também remove a sessão associada
            $sessaoModel = new SessaoModel();
            $sessaoModel->delete($telefone);
        }

        return redirect()->to('/paciente');
    }
}
