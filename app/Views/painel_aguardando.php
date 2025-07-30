<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Painel - Aguardando Atendimento</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

  <div class="flex min-h-screen">

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
        <a href="/paciente" class="flex items-center px-4 py-3 rounded-xl text-gray-700 hover:bg-blue-100 hover:text-blue-700 group">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-3 text-blue-400 group-hover:text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A4 4 0 0116.95 7.05a4 4 0 00-5.656-5.657L5.12 7.05a4 4 0 010 5.657z" />
          </svg>
          <span class="font-medium">Pacientes</span>
        </a>
         <a href="/painel/aguardando" class="flex items-center px-4 py-3 rounded-xl bg-blue-100 text-blue-700 font-semibold transition-all duration-200 group">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-3 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h4l3 8 4-16 3 8h4" />
          </svg>
          <span class="font-medium">Aguardando atendimento</span>
        </a>
    
        <a href="/configuracaoia" class="flex items-center px-4 py-3 rounded-xl text-gray-700 hover:bg-blue-100 hover:text-blue-700 group">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-3 text-blue-400 group-hover:text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 14v1m8-8h1M4 12H3m15.364-6.364l.707.707M6.343 17.657l-.707.707m12.728 0l.707-.707M6.343 6.343l-.707-.707" />
          </svg>
          <span class="font-medium">Configurações</span>
        </a>
      </nav>
    </aside>

    <!-- Conteúdo principal -->
    <main class="flex-1 p-8">
      <h1 class="text-2xl font-bold text-gray-800 mb-6">📋 Pessoas aguardando atendimento</h1>

      <div class="bg-white rounded-xl shadow p-6">
        <?php if (empty($sessoes)): ?>
          <p class="text-gray-600">Nenhum paciente aguardando no momento.</p>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm border">
              <thead class="bg-gray-100 text-gray-700 border-b font-semibold">
                <tr>
                  <th class="py-3 px-4">📱 Telefone</th>
                  <th class="py-3 px-4">👤 Nome</th>
                  <th class="py-3 px-4">⌛ Situação</th>
                </tr>
              </thead>
              <tbody class="text-gray-800">
                <?php foreach ($sessoes as $sessao): ?>
                  <tr class="border-b hover:bg-gray-50">
                    <td class="py-3 px-4"><?= esc($sessao['numero']) ?></td>
                    <td class="py-3 px-4"><?= esc($sessao['nome']) ?></td>
                    <td class="py-3 px-4">
                      <?= $sessao['etapa'] === 'agendamento' ? 'Aguardando agendamento' : 'Aguardando orçamento' ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </main>

  </div>
</body>
</html>
