<?php
namespace App\Controllers;

use App\Models\PacienteModel;
use App\Models\SessaoModel;
use App\Models\ConfigIaModel;
use CodeIgniter\Controller;

class Paciente extends Controller
{
    protected ?int $usuarioId   = null;
    protected ?int $assinanteId = null;

    public function __construct()
    {
        $this->usuarioId   = (int) (session()->get('usuario_id')   ?? 0) ?: null;
        // fallback 1 até o login estar ativo
        $this->assinanteId = (int) (session()->get('assinante_id') ?? 0) ?: 1;
    }

    public function index()
    {
        // if (!$this->usuarioId) return redirect()->to('/login');

        $db = \Config\Database::connect();

        $builder = $db->table('pacientes p')
            ->select('p.*, s.etapa')
            ->join('sessoes s', 's.numero = p.telefone', 'left')
            ->where('p.usuario_id', $this->usuarioId)
            ->orderBy('p.ultimo_contato', 'DESC');

        // Etapas do assinante
        $configIaModel = new ConfigIaModel();
        $resultados = $configIaModel
            ->where('assinante_id', $this->assinanteId)
            ->orderBy('id', 'ASC')
            ->findAll();

        $etapas = [];
        foreach ($resultados as $linha) {
            $etapa = (string)$linha['etapa_atual'];
            $etapas[$etapa] = ucfirst(str_replace('_', ' ', $etapa));
        }

        $dados = [
            'pacientes'  => $builder->get()->getResultArray(),
            'etapas'     => $etapas,
            'validation' => \Config\Services::validation(),
        ];

        return view('paciente', $dados);
    }

    public function atualizar($id)
    {
        if (!$this->usuarioId) {
            return redirect()->to('/login');
        }

        $pacienteModel = new PacienteModel();
        $sessaoModel   = new SessaoModel();
        $configIaModel = new ConfigIaModel();

        // Paciente do usuário atual
        $paciente = $pacienteModel->where('usuario_id', $this->usuarioId)->find((int)$id);
        if (!$paciente) {
            return redirect()->to('/paciente')->with('errors', ['notfound' => 'Paciente não encontrado.']);
        }

        // Inputs
        $nome     = trim((string)$this->request->getPost('nome'));
        $telefone = $this->normalizePhone((string)$this->request->getPost('telefone'));
        $etapa    = trim((string)$this->request->getPost('etapa'));

        if ($nome === '' || $telefone === '') {
            return redirect()->to('/paciente')->with('errors', ['valid' => 'Nome e telefone são obrigatórios.'])->withInput();
        }

        // Etapa deve existir para o assinante
        if ($etapa !== '') {
            $stage = $configIaModel->where('assinante_id', $this->assinanteId)
                                   ->where('etapa_atual', $etapa)
                                   ->first();
            if (!$stage) {
                return redirect()->to('/paciente')->with('errors', ['stage' => 'Etapa inválida para este assinante.'])->withInput();
            }
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        // Atualiza paciente
        $pacienteModel->update((int)$id, [
            'nome'       => $nome,
            'telefone'   => $telefone,
            'usuario_id' => $this->usuarioId, // reforça dono
        ]);

        // Sessão (escopo do usuário atual)
        $telAntigo = (string)($paciente['telefone'] ?? '');
        $telNovo   = $telefone;

        // Se trocou o número
        if ($telAntigo !== '' && $telNovo !== '' && $telAntigo !== $telNovo) {
            // Adota/cria nova sessão
            $sessNova = $db->table('sessoes')->where('numero', $telNovo)->get()->getFirstRow('array');
            if (!$sessNova) {
                $db->table('sessoes')->insert([
                    'numero'     => $telNovo,
                    'usuario_id' => $this->usuarioId,
                    'etapa'      => $etapa ?: 'entrada',
                    'historico'  => json_encode([], JSON_UNESCAPED_UNICODE),
                ]);
            } else {
                if (!empty($sessNova['usuario_id']) && (int)$sessNova['usuario_id'] !== $this->usuarioId) {
                    // não deixa colidir com outro dono
                    $db->transRollback();
                    return redirect()->to('/paciente')->with('errors', ['perm' => 'Número já vinculado a outro usuário.']);
                }
                $db->table('sessoes')->where('numero', $telNovo)->update([
                    'usuario_id' => $this->usuarioId,
                    'etapa'      => $etapa ?: ($sessNova['etapa'] ?? 'entrada'),
                ]);
            }

            // Remove sessão antiga (apenas se for do usuário atual)
            $sessAnt = $db->table('sessoes')->where('numero', $telAntigo)->get()->getFirstRow('array');
            if ($sessAnt && (int)$sessAnt['usuario_id'] === $this->usuarioId) {
                $db->table('sessoes')->where('numero', $telAntigo)->delete();
            }
        } else {
            // Mesmo número -> atualiza/insere sessão
            $sess = $db->table('sessoes')->where('numero', $telNovo)->get()->getFirstRow('array');
            if (!$sess) {
                $db->table('sessoes')->insert([
                    'numero'     => $telNovo,
                    'usuario_id' => $this->usuarioId,
                    'etapa'      => $etapa ?: 'entrada',
                    'historico'  => json_encode([], JSON_UNESCAPED_UNICODE),
                ]);
            } else {
                if (!empty($sess['usuario_id']) && (int)$sess['usuario_id'] !== $this->usuarioId) {
                    $db->transRollback();
                    return redirect()->to('/paciente')->with('errors', ['perm' => 'Sem permissão para alterar esta sessão.']);
                }
                $payload = ['usuario_id' => $this->usuarioId];
                if ($etapa !== '') $payload['etapa'] = $etapa;
                $db->table('sessoes')->where('numero', $telNovo)->update($payload);
            }
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            return redirect()->to('/paciente')->with('errors', ['db' => 'Falha ao salvar.'])->withInput();
        }
        $db->transCommit();

        return redirect()->to('/paciente')->with('msg', 'Paciente atualizado!');
    }

    public function excluir($id)
    {
        if (!$this->usuarioId) {
            return redirect()->to('/login');
        }

        $pacienteModel = new PacienteModel();
        $db            = \Config\Database::connect();

        // Paciente do usuário atual
        $paciente = $pacienteModel->where('usuario_id', $this->usuarioId)->find((int)$id);
        if (!$paciente) {
            return redirect()->to('/paciente')->with('errors', ['notfound' => 'Paciente não encontrado.']);
        }

        $telefone = (string)$paciente['telefone'];

        $db->transBegin();

        // Exclui o paciente (do usuário)
        $pacienteModel->delete((int)$id);

        // Remove a sessão associada somente se for do usuário atual
        $sess = $db->table('sessoes')->where('numero', $telefone)->get()->getFirstRow('array');
        if ($sess && (int)$sess['usuario_id'] === $this->usuarioId) {
            $db->table('sessoes')->where('numero', $telefone)->delete();
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            return redirect()->to('/paciente')->with('errors', ['db' => 'Falha ao excluir.']);
        }
        $db->transCommit();

        return redirect()->to('/paciente')->with('msg', 'Paciente excluído!');
    }

    /* ==================== HELPERS ==================== */

    private function normalizePhone(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';
        // somente dígitos (se usa E.164 com +, adapte aqui)
        return preg_replace('/\D+/', '', $raw) ?? '';
    }
}
