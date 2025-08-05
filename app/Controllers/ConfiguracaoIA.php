<?php

namespace App\Controllers;

use App\Models\{SessaoModel, OpenrouterModel, ConfigIaModel};
use CodeIgniter\Controller;

class ConfiguracaoIA extends Controller
{
    public function index()
    {
        $model = new ConfigIaModel();

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
            'tempo_resposta' => $this->request->getPost('tempo_resposta'),
            'prompt_base' => $this->request->getPost('prompt_etapa'),
            'modo_formal' => $this->request->getPost('modo_formal') ? 1 : 0,
            'permite_respostas_longas' => $this->request->getPost('permite_respostas_longas') ? 1 : 0,
            'permite_redirecionamento' => $this->request->getPost('permite_redirecionamento') ? 1 : 0,
            'assinante_id' => 1
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

    public function testar()
    {
        helper('ia');

        $mensagem = $this->request->getPost('mensagem');
        $promptPersonalizado = $this->request->getPost('prompt');

        $model = new ConfigIaModel();
        $config = $model->first();

        $promptPadrao = get_prompt_padrao();
        $promptFinal = $promptPersonalizado ?: ($config['prompt_base'] ?? $promptPadrao);

        $numeroTeste = '99999999999';
        $sessaoModel = new SessaoModel();
        $sessaoModel->delete($numeroTeste);
        $sessao = $sessaoModel->getOuCriarSessao($numeroTeste);

        $historico = [];
        $historico[] = ['role' => 'user', 'content' => $mensagem];

        $mensagens = [['role' => 'system', 'content' => $promptFinal]];
        foreach ($historico as $m) $mensagens[] = $m;

        $respostaIA = (new OpenrouterModel())->enviarMensagem($mensagens);
        $historico[] = ['role' => 'assistant', 'content' => $respostaIA];

        session()->set("historico_{$numeroTeste}", $historico);
        $sessaoModel->where('numero', $numeroTeste)->set([
            'etapa' => 'teste',
            'ultima_mensagem_usuario' => $mensagem,
            'ultima_resposta_ia' => $respostaIA,
            'historico' => json_encode($historico)
        ])->update();

        return view('configuracaoia', [
            'config' => $config,
            'respostaTeste' => $respostaIA,
            'prompt' => $promptPersonalizado,
            'mensagem' => $mensagem,
            'etapas' => $model->where('assinante_id', 1)->findAll()
        ]);
    }

    public function testarSequenciaReal()
    {
        helper('ia');
        $mensagensUsuario = $this->request->getPost('mensagens_sequencia');
        $promptCustom = $this->request->getPost('prompt_sequencia');
        $numeroTeste = '99999999999';

        $promptPadrao = get_prompt_padrao();
        $prompt = $promptCustom ?: $promptPadrao;

        $sessaoModel = new SessaoModel();
        $sessaoModel->delete($numeroTeste);
        $sessao = $sessaoModel->getOuCriarSessao($numeroTeste);

        $historico = [];
        $mensagens = [['role' => 'system', 'content' => $prompt]];

        $respostas = [];
        foreach (explode("\n", trim($mensagensUsuario)) as $msg) {
            $msg = trim($msg);
            if ($msg === '') continue;

            $mensagens[] = ['role' => 'user', 'content' => $msg];
            $resposta = (new OpenrouterModel())->enviarMensagem($mensagens);
            $mensagens[] = ['role' => 'assistant', 'content' => $resposta];

            $respostas[] = ['pergunta' => $msg, 'resposta' => $resposta];
        }

        session()->set("historico_{$numeroTeste}", array_slice($mensagens, 1));
        $sessaoModel->where('numero', $numeroTeste)->set([
            'etapa' => 'teste',
            'ultima_mensagem_usuario' => end($mensagens)['content'],
            'ultima_resposta_ia' => $resposta,
            'historico' => json_encode(array_slice($mensagens, 1))
        ])->update();

        return $this->response->setJSON(['respostas' => $respostas]);
    }

    public function testarChatSimulado()
    {
        helper('ia');

        $numeroTeste = '99999999999';
        $mensagem = $this->request->getPost('mensagem');
        $promptCustom = $this->request->getPost('prompt');

        $promptPadrao = get_prompt_padrao();
        $prompt = $promptCustom ?: $promptPadrao;

        $sessaoModel = new SessaoModel();
        $sessaoModel->delete($numeroTeste);
        $sessao = $sessaoModel->getOuCriarSessao($numeroTeste);

        $historico = [];
        $historico[] = ['role' => 'user', 'content' => $mensagem];

        $mensagens = [['role' => 'system', 'content' => $prompt]];
        foreach ($historico as $m) $mensagens[] = $m;

        $resposta = (new OpenrouterModel())->enviarMensagem($mensagens);
        $historico[] = ['role' => 'assistant', 'content' => $resposta];

        session()->set("historico_{$numeroTeste}", $historico);
        $sessaoModel->where('numero', $numeroTeste)->set([
            'etapa' => 'teste',
            'ultima_mensagem_usuario' => $mensagem,
            'ultima_resposta_ia' => $resposta,
            'historico' => json_encode($historico)
        ])->update();

        return $this->response->setJSON([
            'mensagem' => $mensagem,
            'resposta' => $resposta
        ]);
    }
}