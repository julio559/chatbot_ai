<?php
$config = $config ?? [
  'tempo_resposta' => 5,
  'prompt_base' => "Você é a assistente humana da Dra. Bruna Sathler. Responda como se estivesse no WhatsApp, com gentileza e naturalidade. Use frases curtas, como um humano faria. Se a pessoa disser 'oi', 'olá', ou 'tudo bem?', apenas cumprimente de volta e pergunte se pode ajudar. Nunca mencione equipe, atendimento, procedimentos ou agendamento, a menos que a pessoa peça algo relacionado. Seja objetiva e educada, sem parecer robô. Não repita informações nem antecipe assuntos.",
  'modo_formal' => false,
  'permite_respostas_longas' => false,
  'permite_redirecionamento' => false
];
$respostaTeste = $respostaTeste ?? null;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>Configurações da IA | CRM da Dra. Bruna Sathler</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
  <div class="flex h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-white shadow-md border-r border-gray-200">
      <div class="px-6 py-4 border-b border-gray-200">
        <h1 class="text-xl font-bold text-gray-800">CRM Assistente</h1>
      </div>
      <nav class="mt-4 space-y-2">
        <a href="/" class="flex items-center px-4 py-3 rounded-xl text-gray-700 hover:bg-blue-100 hover:text-blue-700 group">
          <svg class="w-5 h-5 mr-3 text-blue-400 group-hover:text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path d="M3 10h4l3 8 4-16 3 8h4" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
          </svg>
          <span class="font-medium">Visão Geral</span>
        </a>
        <a href="/paciente" class="flex items-center px-4 py-3 rounded-xl text-gray-700 hover:bg-blue-100 hover:text-blue-700 group">
          <svg class="w-5 h-5 mr-3 text-blue-400 group-hover:text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path d="M5.121 17.804A4 4 0 0116.95 7.05a4 4 0 00-5.656-5.657L5.12 7.05a4 4 0 010 5.657z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
          </svg>
          <span class="font-medium">Pacientes</span>
        </a>
        <a href="/configuracaoia" class="flex items-center px-4 py-3 rounded-xl bg-blue-100 text-blue-700 font-semibold group">
          <svg class="w-5 h-5 mr-3 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path d="M12 4v1m0 14v1m8-8h1M4 12H3m15.364-6.364l.707.707M6.343 17.657l-.707.707m12.728 0l.707-.707M6.343 6.343l-.707-.707" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
          </svg>
          <span class="font-medium">Configurações</span>
        </a>
      </nav>
    </aside>

    <!-- Conteúdo principal -->
    <main class="flex-1 p-6 overflow-y-auto">
      <h2 class="text-2xl font-semibold text-gray-800 mb-6">Configurações da IA</h2>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <!-- Formulário de Configuração -->
        <div class="bg-white rounded-xl shadow p-8">
          <form action="/configuracaoia/salvar" method="post" class="space-y-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">⏱ Tempo de Resposta</label>
              <input type="number" name="tempo_resposta" value="<?= esc($config['tempo_resposta']) ?>" min="1" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400" required>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">🧠 Prompt Base</label>
              <textarea name="prompt_base" rows="6" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400"><?= esc($config['prompt_base']) ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <label class="flex items-center space-x-3">
                <input type="checkbox" name="modo_formal" <?= $config['modo_formal'] ? 'checked' : '' ?> class="form-checkbox text-blue-600 w-5 h-5">
                <span>Modo Formal</span>
              </label>
              <label class="flex items-center space-x-3">
                <input type="checkbox" name="permite_respostas_longas" <?= $config['permite_respostas_longas'] ? 'checked' : '' ?> class="form-checkbox text-blue-600 w-5 h-5">
                <span>Respostas Longas</span>
              </label>
              <label class="flex items-center space-x-3">
                <input type="checkbox" name="permite_redirecionamento" <?= $config['permite_redirecionamento'] ? 'checked' : '' ?> class="form-checkbox text-blue-600 w-5 h-5">
                <span>Redirecionamento</span>
              </label>
            </div>

            <div class="text-right pt-4">
              <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition-all">💾 Salvar</button>
            </div>
          </form>
        </div>

        <!-- Painel de Teste de Prompt -->
       <div class="bg-white rounded-xl shadow p-8">
  <form action="/configuracaoia/testar" method="post" class="space-y-6">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">🗣 Mensagem do Usuário</label>
      <textarea name="mensagem" rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400" required><?= esc($mensagem ?? '') ?></textarea>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">✍️ Prompt Personalizado (opcional)</label>
      <textarea name="prompt" rows="4" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400"><?= esc($prompt ?? '') ?></textarea>
    </div>

    <div class="text-right pt-2">
      <button type="submit" class="px-5 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">🚀 Testar Prompt</button>
    </div>

    <?php if (!empty($respostaTeste)): ?>
      <div class="mt-6">
        <label class="block text-sm font-medium text-gray-700 mb-1">💡 Resposta da IA</label>
        <div class="bg-gray-100 p-4 rounded-lg border border-gray-300 text-gray-800 whitespace-pre-line">
          <?= esc($respostaTeste) ?>
        </div>
      </div>
    <?php endif; ?>
  </form>
</div>

      </div>
    </main>
  </div>
</body>
</html>
