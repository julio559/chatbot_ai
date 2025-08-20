<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use App\Models\ConfigIaModel;

class Painel extends Controller
{
    protected ?int $usuarioId   = null;
    protected ?int $assinanteId = null;

    public function __construct()
    {
        // fallbacks enquanto o login não estiver ativo
        $this->usuarioId   = (int) (session()->get('usuario_id')   ?? 0) ?: 1;
        $this->assinanteId = (int) (session()->get('assinante_id') ?? 0) ?: 1;
    }

    /**
     * Lista TODAS as pessoas que estão em etapas onde a IA NÃO pode responder (escopo do assinante).
     * Use ?meus=1 para filtrar somente do usuário logado.
     */
    public function aguardando()
    {
        $db = \Config\Database::connect();

        $etapasBloq = $this->getEtapasBloqueadasDaIA();
        if (empty($etapasBloq)) {
            // Fallback seguro caso ainda não tenha configurado a flag nas etapas
            $etapasBloq = ['agendamento', 'orcamento', 'financeiro'];
        }

        $builder = $db->table('sessoes s')
            ->select('s.numero, s.etapa, s.data_atualizacao, s.ultima_mensagem_usuario, s.ultima_resposta_ia, p.nome, p.ultimo_contato')
            ->join('pacientes p', 'p.telefone = s.numero', 'left')
            ->whereIn('s.etapa', $etapasBloq)
            ->orderBy('s.data_atualizacao', 'DESC');

        // Se quiser só os seus: ?meus=1
        $soMeus = (string) $this->request->getGet('meus') === '1';
        if ($soMeus) {
            $builder->where('s.usuario_id', $this->usuarioId);
        }

        $sessoes = $builder->get()->getResultArray();

        foreach ($sessoes as &$sessao) {
            $sessao['nome'] = $sessao['nome'] ?: 'Sem nome';
        }

        return view('painel_aguardando', [
            'sessoes'          => $sessoes,
            'etapasBloqueadas' => $etapasBloq,
            'soMeus'           => $soMeus,
        ]);
    }

    /**
     * Lê a tabela config_ia do assinante e retorna as etapas onde ia_pode_responder = 0.
     * Ordena por 'ordem' (se existir) e remove duplicidades.
     */
    private function getEtapasBloqueadasDaIA(): array
    {
        $model = new ConfigIaModel();

        try {
            // se houver coluna 'ordem' ela será usada; se não houver, o ORDER BY é ignorado pelo MySQL
            $etapas = $model->select('etapa_atual')
                ->where('assinante_id', $this->assinanteId)
                ->where('ia_pode_responder', 0)
                ->orderBy('ordem', 'ASC')
                ->findColumn('etapa_atual') ?? [];
        } catch (\Throwable $e) {
            // Se por algum motivo a coluna não existir ainda, devolve vazio para cair no fallback
            $etapas = [];
        }

        // Normaliza e remove duplicidades
        $etapas = array_values(array_unique(array_map('strval', $etapas)));

        return $etapas;
    }
}
