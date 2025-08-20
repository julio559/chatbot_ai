<?php
namespace App\Controllers;

use CodeIgniter\Controller;
use App\Models\UsuarioModel;
use App\Models\AssinanteModel;
use App\Models\NotificacaoNumeroModel;

// Opcional: só se você tiver o Model da sessão
use App\Models\SessaoModel;

class Auth extends Controller
{
    public function index()
    {
        return view('auth');
    }

    public function login()
    {
        $email = trim((string)$this->request->getPost('email'));
        $senha = (string)$this->request->getPost('senha');

        $rules = [
            'email' => 'required|valid_email',
            'senha' => 'required|min_length[6]|max_length[100]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->to('/auth')->with('errors_login', $this->validator->getErrors())->withInput();
        }

        $user = (new UsuarioModel())->where('email', $email)->first();
        if (! $user || ! password_verify($senha, (string)$user['senha_hash'])) {
            return redirect()->to('/auth')->with('errors_login', ['auth' => 'E-mail ou senha inválidos.'])->withInput();
        }

        session()->regenerate();
        session()->set([
            'usuario_id'    => (int)$user['id'],
            'assinante_id'  => (int)$user['assinante_id'],
            'usuario_nome'  => (string)$user['nome'],
            'usuario_email' => (string)$user['email'],
            'logado'        => true,
        ]);

        return redirect()->to('/');
    }

    public function register()
    {
        $nome     = trim((string)$this->request->getPost('nome'));
        $email    = trim((string)$this->request->getPost('email'));
        $telefone = $this->normalizePhone((string)$this->request->getPost('telefone'));
        $senha    = (string)$this->request->getPost('senha');
        $senha2   = (string)$this->request->getPost('senha2');

        $rules = [
            'nome'     => 'required|min_length[2]|max_length[120]',
            'email'    => 'required|valid_email|max_length[190]',
            'telefone' => 'required|min_length[8]|max_length[20]',
            'senha'    => 'required|min_length[6]|max_length[100]',
            'senha2'   => 'required|matches[senha]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->to('/auth')
                ->with('errors_register', $this->validator->getErrors())
                ->withInput();
        }

        $db        = \Config\Database::connect();
        $userModel = new UsuarioModel();
        $assModel  = new AssinanteModel();

        if ($userModel->where('email', $email)->first()) {
            return redirect()->to('/auth')
                ->with('errors_register', ['email' => 'Já existe uma conta com este e-mail.'])
                ->withInput();
        }
        if ($assModel->where('telefone', $telefone)->first()) {
            return redirect()->to('/auth')
                ->with('errors_register', ['telefone' => 'Este telefone já está em uso.'])
                ->withInput();
        }

        $db->transBegin();
        try {
            // 1) assinante
            $okAss = $assModel->insert(['nome' => $nome, 'telefone' => $telefone], false);
            if ($okAss === false) {
                $errs = $assModel->errors();
                $db->transRollback();
                return redirect()->to('/auth')->with('errors_register', $errs ?: ['db' => 'Falha ao criar o assinante.'])->withInput();
            }
            $assId = (int)$assModel->getInsertID();
            if ($assId <= 0) {
                $db->transRollback();
                return redirect()->to('/auth')->with('errors_register', ['db' => 'Falha ao criar o assinante (ID vazio).'])->withInput();
            }

            // 2) usuário
            $okUser = $userModel->insert([
                'assinante_id'       => $assId,
                'nome'               => $nome,
                'email'              => $email,
                'senha_hash'         => password_hash($senha, PASSWORD_DEFAULT),
                'telefone_principal' => $telefone,
                'status'             => 'ativo',
            ], false);

            if ($okUser === false) {
                $errs = $userModel->errors();
                $db->transRollback();
                return redirect()->to('/auth')->with('errors_register', $errs ?: ['db' => 'Falha ao criar o usuário.'])->withInput();
            }
            $usuarioId = (int)$userModel->getInsertID();
            if ($usuarioId <= 0) {
                $db->transRollback();
                return redirect()->to('/auth')->with('errors_register', ['db' => 'Falha ao criar o usuário (ID vazio).'])->withInput();
            }

            $db->transCommit();

            // ============ NÚMERO DE TESTE ============
            $numeroTeste = $this->makeTestNumber($usuarioId); // ex.: 99999000005

            // 2.1) Cadastrar em notificacoes_numeros (se existir a tabela)
            try {
                $notif = new NotificacaoNumeroModel();
                // evita duplicar se rodar 2x
                $ja = $notif->where('assinante_id', $assId)->where('numero', $numeroTeste)->first();
                if (!$ja) {
                    $notif->insert([
                        'assinante_id' => $assId,
                        'usuario_id'   => $usuarioId,
                        'numero'       => $numeroTeste,
                        'descricao'    => 'Número de teste',
                        'ativo'        => 1,
                    ]);
                }
            } catch (\Throwable $e) {
                log_message('warning', 'Falha ao criar numero de teste em notificacoes_numeros: {err}', ['err' => $e->getMessage()]);
            }

            // 2.2) Criar uma sessão inicial para esse número (se você usa SessaoModel)
            try {
                if (class_exists(SessaoModel::class)) {
                    $sessao = new SessaoModel();
                    $ex = $sessao->where('usuario_id', $usuarioId)->where('numero', $numeroTeste)->first();
                    if (!$ex) {
                        $sessao->insert([
                            'numero'                 => $numeroTeste,
                            'usuario_id'             => $usuarioId,
                            'etapa'                  => 'inicio',
                            'etapa_lead'             => 'inicio',
                            'ultima_mensagem_usuario'=> null,
                            'ultima_resposta_ia'     => null,
                            'historico'              => '[]',
                            'data_atualizacao'       => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                log_message('warning', 'Falha ao criar sessao de teste: {err}', ['err' => $e->getMessage()]);
            }

            // login automático
            session()->regenerate();
            session()->set([
                'usuario_id'    => $usuarioId,
                'assinante_id'  => $assId,
                'usuario_nome'  => $nome,
                'usuario_email' => $email,
                'logado'        => true,
            ]);

            return redirect()->to('/');

        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Cadastro exception: {e}', ['e' => $e->getMessage()]);
            return redirect()->to('/auth')
                ->with('errors_register', ['db' => 'Erro ao salvar no banco.'])
                ->withInput();
        }
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/auth');
    }

    private function normalizePhone(string $raw): string
    {
        $raw = trim($raw);
        return $raw === '' ? '' : (preg_replace('/\D+/', '', $raw) ?? '');
    }

    private function makeTestNumber(int $usuarioId): string
    {
        // 999990000 + ID zeropadded para no mínimo 2 dígitos (gera 11~13 dígitos conforme o ID)
        // Se você quiser fixar 12 dígitos, use STR_PAD_LEFT para 3.
        $suf = str_pad((string)$usuarioId, 2, '0', STR_PAD_LEFT);
        return '999990000' . $suf; // ex.: id=5 -> 99999000005
    }
}
