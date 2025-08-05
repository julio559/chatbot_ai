<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CRM da Dra. Bruna Sathler</title>
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
        <a href="/dashboard" class="flex items-center px-4 py-3 rounded-xl bg-blue-100 text-blue-700 font-semibold transition-all duration-200 group">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-3 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
        <a href="/painel/aguardando" class="flex items-center px-4 py-3 rounded-xl text-gray-700 hover:bg-blue-100 hover:text-blue-700 group">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-3 text-blue-400 group-hover:text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A4 4 0 0116.95 7.05a4 4 0 00-5.656-5.657L5.12 7.05a4 4 0 010 5.657z" />
          </svg>
          <span class="font-medium">Aguardando atendimento</span>
        </a>
                    <a href="/kanban" class="flex items-center px-4 py-3 rounded-xl text-gray-700 hover:bg-blue-100 hover:text-blue-700 group">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-3 text-blue-400 group-hover:text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A4 4 0 0116.95 7.05a4 4 0 00-5.656-5.657L5.12 7.05a4 4 0 010 5.657z" />
          </svg>
          <span class="font-medium">Leads</span>
        </a>
        <a href="/configuracaoia" class="flex items-center px-4 py-3 rounded-xl text-gray-700 hover:bg-blue-100 hover:text-blue-700 group">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-3 text-blue-400 group-hover:text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 14v1m8-8h1M4 12H3m15.364-6.364l.707.707M6.343 17.657l-.707.707m12.728 0l.707-.707M6.343 6.343l-.707-.707" />
          </svg>
          <span class="font-medium">Configurações</span>
        </a>
      </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-6 overflow-y-auto">
      <h2 class="text-2xl font-semibold text-gray-800 mb-6">Dashboard</h2>

      <!-- Boas-vindas -->
      <div class="bg-gradient-to-r from-blue-100 to-blue-200 p-6 rounded-xl shadow mb-6">
        <h3 class="text-xl font-bold text-blue-800 mb-1">Olá, Dra. Bruna 👩‍⚕️</h3>
        <p class="text-gray-700">Aqui está um resumo do seu atendimento. Continue oferecendo o melhor para seus pacientes!</p>
      </div>

      <!-- Estatísticas -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-5 rounded-xl shadow border">
          <h3 class="text-sm font-medium text-gray-500 mb-1">Total de Pacientes</h3>
          <p class="text-2xl font-bold text-blue-600"><?= $totalPacientes ?></p>
        </div>
        <div class="bg-white p-5 rounded-xl shadow border">
          <h3 class="text-sm font-medium text-gray-500 mb-1">Conversas Ativas</h3>
          <p class="text-2xl font-bold text-green-600"><?= $conversasAtivas ?></p>
        </div>
        <div class="bg-white p-5 rounded-xl shadow border">
          <h3 class="text-sm font-medium text-gray-500 mb-1">Agendamentos</h3>
          <p class="text-2xl font-bold text-orange-500"><?= $agendamentos ?></p>
        </div>
        <div class="bg-white p-5 rounded-xl shadow border">
          <h3 class="text-sm font-medium text-gray-500 mb-1">Encaminhados ao Financeiro</h3>
          <p class="text-2xl font-bold text-red-500"><?= $financeiro ?></p>
        </div>
      </div>

      <!-- Últimos Pacientes -->
      <div class="bg-white p-6 rounded-xl shadow mb-6">
        <h3 class="text-lg font-semibold text-gray-700 mb-4">Últimos Pacientes</h3>
        <ul class="space-y-3">
          <?php foreach ($ultimosPacientes as $p): ?>
            <li class="flex items-center justify-between border-b pb-2">
              <span class="text-gray-700 font-medium"><?= esc($p['nome']) ?></span>
              <span class="text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($p['ultimo_contato'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Simulação de gráfico (ex: uso de IA) -->
      <div class="bg-white p-6 rounded-xl shadow">
        <h3 class="text-lg font-semibold text-gray-700 mb-4">Atendimentos com IA (últimos dias)</h3>
        <div class="flex space-x-3 h-24 items-end">
          <?php foreach ($graficoIA as $dia => $total): ?>
            <div class="flex flex-col items-center flex-1">
              <div class="w-4 bg-blue-500 rounded-t" style="height: <?= $total * 6 ?>px"></div>
              <span class="text-xs mt-1"><?= $dia ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
