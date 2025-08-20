<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ConfigIaModel;
use CodeIgniter\Controller;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\ResponseInterface;

class CriarEtapas extends Controller
{
    protected ?int $usuarioId   = null;
    protected ?int $assinanteId = null;

    public function __construct()
    {
        // ajuste conforme sua sessão real
        $this->usuarioId   = (int) (session()->get('usuario_id')   ?? 0) ?: null;
        // fallback enquanto login não estiver ativo
        $this->assinanteId = (int) (session()->get('assinante_id') ?? 0) ?: 1;
    }

    /**
     * GET /etapas
     * Lista etapas do assinante + carrega instâncias do usuário para o <select>.
     */
    public function index()
    {
        $model = new ConfigIaModel();

        // Etapas (ordem -> id)
        try {
            $etapas = $model->where('assinante_id', $this->assinanteId)
                ->orderBy('ordem', 'ASC')
                ->orderBy('id', 'ASC')
                ->findAll();
        } catch (DatabaseException $e) {
            $etapas = $model->where('assinante_id', $this->assinanteId)
                ->orderBy('id', 'ASC')
                ->findAll();
        }

        // Instâncias do usuário (para o select)
        $db = \Config\Database::connect();
        $instancias = $db->table('whatsapp_instancias')
            ->select('id, instance_id, token, linha_msisdn, nome, created_at')
            ->where('usuario_id', $this->usuarioId)
            ->orderBy('id', 'DESC')
            ->get()->getResultArray();

        // Mapa por token -> label/msisdn (para exibir na tabela)
        $instanciasMap = [];
        foreach ($instancias as $i) {
            $label = $i['nome'] ?? null;
            if (!$label || trim($label) === '') {
                $label = 'Instância ' . substr((string)($i['instance_id'] ?? ''), 0, 6);
            }
            $instanciasMap[(string)($i['token'] ?? '')] = [
                'label'  => $label,
                'msisdn' => $i['linha_msisdn'] ?? null,
            ];
        }

        return view('gerenciar_etapas', [
            'etapas'         => $etapas,
            'instancias'     => $instancias,
            'instanciasMap'  => $instanciasMap,
        ]);
    }

    /**
     * POST /etapas/salvar
     * Upsert por (assinante_id, etapa_atual).
     * Agora aceita: instancia_preferida_token + instancia_preferida_msisdn (vindos do select).
     */
    public function criarOuAtualizarEtapa(): ResponseInterface
    {
        $model = new ConfigIaModel();

        $etapa_atual               = trim((string) ($this->request->getPost('nome_etapa') ?? $this->request->getPost('etapa_atual') ?? ''));
        $prompt_base               = (string) ($this->request->getPost('prompt') ?? $this->request->getPost('prompt_base') ?? '');
        $tempo_resposta            = (int) ($this->request->getPost('tempo_resposta') ?? 5);
        $modo_formal               = $this->request->getPost('modo_formal') ? 1 : 0;
        $permite_respostas_longas  = $this->request->getPost('permite_respostas_longas') ? 1 : 0;
        $permite_redirecionamento  = $this->request->getPost('permite_redirecionamento') ? 1 : 0;
        $ia_pode_responder         = $this->request->getPost('ia_pode_responder') ? 1 : 0;

        // vindos do select
        $inst_token = trim((string)$this->request->getPost('instancia_preferida_token'));
        $inst_msisdn = trim((string)$this->request->getPost('instancia_preferida_msisdn'));
        if ($inst_msisdn !== '') {
            $inst_msisdn = preg_replace('/\D+/', '', $inst_msisdn);
        }

        if ($etapa_atual === '') {
            return $this->response->setStatusCode(400)->setJSON([
                'ok'  => false,
                'msg' => 'Nome da etapa é obrigatório.',
            ]);
        }

        $data = [
            'assinante_id'               => $this->assinanteId,
            'etapa_atual'                => $etapa_atual,
            'prompt_base'                => $prompt_base,
            'tempo_resposta'             => $tempo_resposta,
            'modo_formal'                => $modo_formal,
            'permite_respostas_longas'   => $permite_respostas_longas,
            'permite_redirecionamento'   => $permite_redirecionamento,
            'ia_pode_responder'          => $ia_pode_responder,
            'instancia_preferida_token'  => ($inst_token  !== '' ? $inst_token  : null),
            'instancia_preferida_msisdn' => ($inst_msisdn !== '' ? $inst_msisdn : null),
        ];

        // upsert
        $exist = $model->where('assinante_id', $this->assinanteId)
            ->where('etapa_atual', $etapa_atual)
            ->first();

        if ($exist) {
            $ok = $model->update((int)$exist['id'], $data);
        } else {
            $ok = (bool) $model->insert($data);
        }

        if (!$ok) {
            return $this->response->setStatusCode(500)->setJSON([
                'ok'  => false,
                'msg' => 'Falha ao salvar a etapa.',
                'err' => $model->errors(),
            ]);
        }

        return $this->response->setJSON(['status' => 'ok']);
    }

    /**
     * POST /etapas/excluir
     */
    public function excluirEtapa(): ResponseInterface
    {
        $model = new ConfigIaModel();

        $etapa_atual = trim((string) ($this->request->getPost('nome_etapa') ?? $this->request->getPost('etapa_atual') ?? ''));
        if ($etapa_atual === '') {
            return $this->response->setStatusCode(400)->setJSON([
                'ok'  => false,
                'msg' => 'Nome da etapa é obrigatório.',
            ]);
        }

        $model->where('assinante_id', $this->assinanteId)
            ->where('etapa_atual', $etapa_atual)
            ->delete();

        return $this->response->setJSON(['status' => 'ok']);
    }

    /**
     * POST /etapas/ordenar
     */
    public function ordenar(): ResponseInterface
    {
        $ids = $this->request->getPost('ids'); // array na ordem final

        if (!is_array($ids) || empty($ids)) {
            return $this->response->setStatusCode(400)->setJSON([
                'ok'  => false,
                'msg' => 'Lista vazia',
            ]);
        }

        $model = new ConfigIaModel();
        $db    = \Config\Database::connect();

        try {
            $db->transStart();

            foreach (array_values($ids) as $pos => $id) {
                $row = $model->where('assinante_id', $this->assinanteId)
                    ->where('id', (int)$id)
                    ->first();
                if (!$row) continue;

                $model->update((int)$id, ['ordem' => (int)$pos]);
            }

            $db->transComplete();

            if (!$db->transStatus()) {
                return $this->response->setStatusCode(500)->setJSON([
                    'ok'  => false,
                    'msg' => 'Falha ao salvar ordem',
                ]);
            }

            return $this->response->setJSON(['ok' => true]);
        } catch (DatabaseException $e) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok'  => false,
                'msg' => "A coluna 'ordem' não existe. Crie com:\n\nALTER TABLE config_ia ADD COLUMN ordem INT NULL AFTER ia_pode_responder;",
                'err' => $e->getMessage(),
            ]);
        }
    }
}
