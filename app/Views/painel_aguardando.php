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
    <?= view('sidebar') ?>


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
