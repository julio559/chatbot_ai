<?php
$config = $config ?? [
  'tempo_resposta' => 5,
  'prompt_base' => "Você é a assistente humana da Dra. Bruna Sathler. Responda como se estivesse no WhatsApp, com gentileza e naturalidade. Use frases curtas, como um humano faria. Se a pessoa disser 'oi', 'olá', ou 'tudo bem?', apenas cumprimente de volta e pergunte se pode ajudar. Nunca mencione equipe, atendimento, procedimentos ou agendamento, a menos que a pessoa peça algo relacionado. Seja objetiva e educada, sem parecer robô. Não repita informações nem antecipe assuntos.",
  'modo_formal' => false,
  'permite_respostas_longas' => false,
  'permite_redirecionamento' => false
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
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
        <a href="/" class="flex items-center px-4 py-3 rounded-xl text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-all duration-200 group">
          <svg class="w-5 h-5 mr-3 text-blue-400 group-hover:text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h4l3 8 4-16 3 8h4" />
          </svg>
          <span class="font-medium">Visão Geral</span>
        </a>
        <a href="/paciente" class="flex items-center px-4 py-3 rounded-xl text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-all duration-200 group">
          <svg class="w-5 h-5 mr-3 text-blue-400 group-hover:text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A4 4 0 0116.95 7.05a4 4 0 00-5.656-5.657L5.12 7.05a4 4 0 010 5.657z" />
          </svg>
          <span class="font-medium">Pacientes</span>
        </a>
        <a href="/configuracaoia" class="flex items-center px-4 py-3 rounded-xl bg-blue-100 text-blue-700 font-semibold transition-all duration-200 group">
          <svg class="w-5 h-5 mr-3 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 14v1m8-8h1M4 12H3m15.364-6.364l.707.707M6.343 17.657l-.707.707m12.728 0l.707-.707M6.343 6.343l-.707-.707" />
          </svg>
          <span class="font-medium">Configurações</span>
        </a>
      </nav>
    </aside>

    <!-- Main content -->
    <main class="flex-1 p-6 overflow-y-auto">
      <h2 class="text-2xl font-semibold text-gray-800 mb-6">Configurações da IA</h2>

      <div class="bg-white rounded-xl shadow p-8 max-w-4xl">
        <form action="/configuracaoia/salvar" method="post" class="space-y-8">

          <!-- Grupo: Tempo e Prompt -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">⏱ Tempo de Resposta (em segundos)</label>
              <input type="number" name="tempo_resposta" value="<?= esc($config['tempo_resposta']) ?>" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400" min="1" required>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">🧠 Prompt Base da IA</label>
              <textarea name="prompt_base" rows="6" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400"><?= esc($config['prompt_base']) ?></textarea>
            </div>
          </div>

          <!-- Grupo: Opções Avançadas -->
          <div class="border-t pt-6">
            <h3 class="text-lg font-medium text-gray-700 mb-4">⚙️ Comportamento da IA</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <label class="flex items-center space-x-3">
                <input type="checkbox" name="modo_formal" <?= $config['modo_formal'] ? 'checked' : '' ?> class="form-checkbox text-blue-600 w-5 h-5">
                <span class="text-gray-700">Usar Modo Formal</span>
              </label>

              <label class="flex items-center space-x-3">
                <input type="checkbox" name="permite_respostas_longas" <?= $config['permite_respostas_longas'] ? 'checked' : '' ?> class="form-checkbox text-blue-600 w-5 h-5">
                <span class="text-gray-700">Permitir Respostas Longas</span>
              </label>

              <label class="flex items-center space-x-3">
                <input type="checkbox" name="permite_redirecionamento" <?= $config['permite_redirecionamento'] ? 'checked' : '' ?> class="form-checkbox text-blue-600 w-5 h-5">
                <span class="text-gray-700">Permitir Redirecionamento</span>
              </label>
            </div>
          </div>

          <!-- Botão -->
          <div class="pt-8 text-right">
            <button type="submit" class="px-6 py-3 bg-blue-600 text-white text-sm font-semibold rounded-xl shadow hover:bg-blue-700 transition-all">💾 Salvar Configuração</button>
          </div>
        </form>
      </div>
    </main>
  </div>
</body>
</html>
