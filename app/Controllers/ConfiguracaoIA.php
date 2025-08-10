<?php

namespace App\Controllers;

use App\Models\{SessaoModel, OpenrouterModel, ConfigIaModel};
use CodeIgniter\Controller;

class ConfiguracaoIA extends Controller
{
    public function index()
    {
        $model  = new ConfigIaModel();
        $configs = $model->where('assinante_id', 1)->findAll();

        $dados['etapas'] = $configs;
        $dados['config'] = $configs[0] ?? [];

        return view('configuracaoia', $dados);
    }

    public function salvar()
    {
        $model = new ConfigIaModel();

        $etapa = $this->request->getPost('etapa_atual');

        $data = [
            'tempo_resposta'            => (int) $this->request->getPost('tempo_resposta'),
            'prompt_base'               => $this->request->getPost('prompt_etapa'),
            'modo_formal'               => $this->request->getPost('modo_formal') ? 1 : 0,
            'permite_respostas_longas'  => $this->request->getPost('permite_respostas_longas') ? 1 : 0,
            'permite_redirecionamento'  => $this->request->getPost('permite_redirecionamento') ? 1 : 0,
            'assinante_id'              => 1,
        ];

        $configExistente = $model->where('etapa_atual', $etapa)->where('assinante_id', 1)->first();
        if ($configExistente) {
            $model->update($configExistente['id'], $data);
        } else {
            $data['etapa_atual'] = $etapa;
            $model->insert($data);
        }

        return redirect()->to('/configuracaoia')->with('success', 'Configuração da etapa salva!');
    }

    /**
     * Endpoint para o chat de teste em tempo real.
     * POST /configuracaoia/testarchat
     * body: mensagem (string), prompt (opcional)
     * retorno: { resposta: "..." }
     */
    public function testarchat()
    {
        helper('ia');

        $mensagem = trim((string) $this->request->getPost('mensagem'));
        if ($mensagem === '') {
            return $this->response->setJSON(['resposta' => 'Mensagem vazia.'])->setStatusCode(400);
        }

        // Prompt: custom do POST > prompt_base da etapa (primeira) > padrão
        $promptCustom = $this->request->getPost('prompt');
        $configModel  = new ConfigIaModel();
        $config       = $configModel->where('assinante_id', 1)->orderBy('id', 'ASC')->first();

        $promptPadrao = get_prompt_padrao();
        $promptFinal  = $promptCustom ?: ($config['prompt_base'] ?? $promptPadrao);

        // Número fixo para chat de teste
        $numeroTeste  = '99999999999';

        // Pega sessão existente (ou cria)
        $sessaoModel = new SessaoModel();
        $sessao      = $sessaoModel->getOuCriarSessao($numeroTeste);

        // Carrega histórico da session() ou do banco
        $historicoSessao = session()->get("historico_{$numeroTeste}") ?? [];
        $historicoBanco  = [];
        if (!empty($sessao['historico'])) {
            $tmp = json_decode($sessao['historico'], true);
            if (is_array($tmp)) $historicoBanco = $tmp;
        }
        $historico = !empty($historicoSessao) ? $historicoSessao : $historicoBanco;

        // Monta mensagens para a IA
        $mensagens = [['role' => 'system', 'content' => $promptFinal]];
        foreach ($historico as $m) {
            if (isset($m['role'], $m['content'])) {
                $mensagens[] = $m;
            }
        }

        // Adiciona a mensagem do "usuário"
        $historico[] = ['role' => 'user', 'content' => $mensagem];
        $mensagens[] = ['role' => 'user', 'content' => $mensagem];

        // Chama o provedor (OpenRouter)
        $resposta = (new OpenrouterModel())->enviarMensagem($mensagens);

        // Persiste no histórico
        $historico[] = ['role' => 'assistant', 'content' => $resposta];

        // Salva em sessão e banco
        session()->set("historico_{$numeroTeste}", $historico);

        $sessaoModel->where('numero', $numeroTeste)->set([
            'etapa'                    => 'teste',
            'ultima_mensagem_usuario'  => $mensagem,
            'ultima_resposta_ia'       => $resposta,
            'historico'                => json_encode($historico, JSON_UNESCAPED_UNICODE),
        ])->update();

        return $this->response->setJSON([
            'resposta' => $resposta,
        ]);
    }

    /* ====== OPCIONAIS (mantenha se ainda usa em outro lugar) ======
       Se não usar, pode remover esses métodos antigos. */

    public function testar()
    {
        helper('ia');

        $mensagem           = $this->request->getPost('mensagem');
        $promptPersonalizado = $this->request->getPost('prompt');

        $model  = new ConfigIaModel();
        $config = $model->first();

        $promptPadrao = get_prompt_padrao();
        $promptFinal  = $promptPersonalizado ?: ($config['prompt_base'] ?? $promptPadrao);

        $numeroTeste  = '99999999999';
        $sessaoModel  = new SessaoModel();

        // zera apenas para o modo "testar" tradicional
        $sessaoModel->delete($numeroTeste);
        $sessao = $sessaoModel->getOuCriarSessao($numeroTeste);

        $historico   = [];
        $historico[] = ['role' => 'user', 'content' => $mensagem];

        $mensagens = [['role' => 'system', 'content' => $promptFinal]];
        foreach ($historico as $m) $mensagens[] = $m;

        $respostaIA  = (new OpenrouterModel())->enviarMensagem($mensagens);
        $historico[] = ['role' => 'assistant', 'content' => $respostaIA];

        session()->set("historico_{$numeroTeste}", $historico);
        $sessaoModel->where('numero', $numeroTeste)->set([
            'etapa'                   => 'teste',
            'ultima_mensagem_usuario' => $mensagem,
            'ultima_resposta_ia'      => $respostaIA,
            'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
        ])->update();

        return view('configuracaoia', [
            'config'        => $config,
            'respostaTeste' => $respostaIA,
            'prompt'        => $promptPersonalizado,
            'mensagem'      => $mensagem,
            'etapas'        => $model->where('assinante_id', 1)->findAll()
        ]);
    }

    public function testarSequenciaReal()
    {
        helper('ia');

        $mensagensUsuario = (string) $this->request->getPost('mensagens_sequencia');
        $promptCustom     = $this->request->getPost('prompt_sequencia');
        $numeroTeste      = '99999999999';

        $promptPadrao = get_prompt_padrao();
        $prompt       = $promptCustom ?: $promptPadrao;

        $sessaoModel = new SessaoModel();
        $sessaoModel->delete($numeroTeste); // mantém o comportamento antigo aqui
        $sessao = $sessaoModel->getOuCriarSessao($numeroTeste);

        $mensagens = [['role' => 'system', 'content' => $prompt]];
        $respostas = [];
        $lastUser  = '';

        foreach (explode("\n", trim($mensagensUsuario)) as $msg) {
            $msg = trim($msg);
            if ($msg === '') continue;

            $lastUser = $msg;
            $mensagens[] = ['role' => 'user', 'content' => $msg];
            $resposta = (new OpenrouterModel())->enviarMensagem($mensagens);
            $mensagens[] = ['role' => 'assistant', 'content' => $resposta];

            $respostas[] = ['pergunta' => $msg, 'resposta' => $resposta];
        }

        // salva histórico (sem o primeiro system)
        $hist = array_slice($mensagens, 1);
        session()->set("historico_{$numeroTeste}", $hist);
        $sessaoModel->where('numero', $numeroTeste)->set([
            'etapa'                   => 'teste',
            'ultima_mensagem_usuario' => $lastUser,
            'ultima_resposta_ia'      => end($respostas)['resposta'] ?? '',
            'historico'               => json_encode($hist, JSON_UNESCAPED_UNICODE),
        ])->update();

        return $this->response->setJSON(['respostas' => $respostas]);
    }

    public function testarChatSimulado()
    {
        helper('ia');

        $numeroTeste  = '99999999999';
        $mensagem     = trim((string) $this->request->getPost('mensagem'));
        $promptCustom = $this->request->getPost('prompt');

        $promptPadrao = get_prompt_padrao();
        $prompt       = $promptCustom ?: $promptPadrao;

        $sessaoModel = new SessaoModel();
        $sessaoModel->delete($numeroTeste); // mantém o comportamento antigo aqui
        $sessao = $sessaoModel->getOuCriarSessao($numeroTeste);

        $historico   = [['role' => 'user', 'content' => $mensagem]];
        $mensagens   = [['role' => 'system', 'content' => $prompt], ['role' => 'user', 'content' => $mensagem]];

        $resposta    = (new OpenrouterModel())->enviarMensagem($mensagens);
        $historico[] = ['role' => 'assistant', 'content' => $resposta];

        session()->set("historico_{$numeroTeste}", $historico);
        $sessaoModel->where('numero', $numeroTeste)->set([
            'etapa'                   => 'teste',
            'ultima_mensagem_usuario' => $mensagem,
            'ultima_resposta_ia'      => $resposta,
            'historico'               => json_encode($historico, JSON_UNESCAPED_UNICODE),
        ])->update();

        return $this->response->setJSON([
            'mensagem' => $mensagem,
            'resposta' => $resposta
        ]);
    }
}
