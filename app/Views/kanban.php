<?php
$etapas = $etapas ?? [];
$colunas = $colunas ?? [];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>Kanban de Atendimento | CRM da Dra. Bruna Sathler</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-50">

<div class="flex h-screen">

  <!-- Sidebar -->
  <aside class="w-64 bg-white shadow-lg">
    <div class="px-6 py-4 border-b border-gray-200">
      <h1 class="text-xl font-semibold text-gray-800">CRM Assistente</h1>
    </div>
    <nav class="mt-6 space-y-4">
      <a href="/" class="flex items-center px-4 py-3 text-gray-700 hover:bg-blue-100 rounded-xl">
        <span class="font-medium">Visão Geral</span>
      </a>
      <a href="/paciente" class="flex items-center px-4 py-3 text-gray-700 hover:bg-blue-100 rounded-xl">
        <span class="font-medium">Pacientes</span>
      </a>
      <a href="/kanban" class="flex items-center px-4 py-3 text-blue-700 bg-blue-100 rounded-xl font-semibold">
        <span class="font-medium">Leads</span>
      </a>
      <a href="/painel/aguardando" class="flex items-center px-4 py-3 text-gray-700 hover:bg-blue-100 rounded-xl">
        <span class="font-medium">Aguardando atendimento</span>
      </a>
      <a href="/configuracaoia" class="flex items-center px-4 py-3 text-gray-700 hover:bg-blue-100 rounded-xl">
        <span class="font-medium">Configurações</span>
      </a>
    </nav>
  </aside>

  <!-- Conteúdo principal -->
  <main class="flex-1 p-8 overflow-y-auto">
    <h2 class="text-3xl font-semibold text-gray-800 mb-6">Kanban de Leads</h2>

  <div class="flex h-screen overflow-x-auto px-4 space-x-4">

  <?php foreach ($etapas as $key => $titulo): ?>
    <div class="kanban-column flex flex-col bg-white rounded-lg shadow-md w-72 min-w-[18rem]">
      <div class="p-3 text-center font-bold text-gray-700 border-b"><?= $titulo ?></div>

      <div class="cards-container flex-1 overflow-y-auto p-3 space-y-3 min-h-[50px]" data-etapa="<?= $key ?>">
        <?php foreach ($colunas as $coluna): ?>
          <?php if ($coluna['etapa'] == $key): ?>
            <?php foreach ($coluna['clientes'] as $lead): ?>
              <div class="kanban-card bg-gray-100 p-3 rounded shadow text-sm" data-lead-id="<?= $lead['numero'] ?>" id="lead-<?= $lead['numero'] ?>">
                <div class="font-medium text-gray-800"><?= substr($lead['numero'], 0, 10) ?></div>
                <div class="text-gray-500"><?= substr($lead['ultima_mensagem_usuario'], 0, 40) ?>...</div>
                <div class="text-gray-500"><?= substr($lead['ultima_resposta_ia'], 0, 40) ?>...</div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

  </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const kanbanContainers = document.querySelectorAll('.cards-container');

  kanbanContainers.forEach((container) => {
    new Sortable(container, {
      group: 'kanban',
      animation: 150,
      fallbackOnBody: true,
      swapThreshold: 0.65,
      forceFallback: true, // corrige movimentação em colunas vazias
      onEnd(evt) {
        const leadElement = evt.item;
        const leadId = leadElement.getAttribute('data-lead-id');
        const destinoEtapa = evt.to.getAttribute('data-etapa');

        // Proteção adicional
        if (!leadId || !destinoEtapa) {
          alert("Erro ao mover o lead: dados incompletos.");
          return;
        }

        // Atualiza visualmente no DOM antes do AJAX
        evt.to.appendChild(leadElement);

        // Envia para o backend
        $.ajax({
          url: '<?= base_url("kanban/atualizarEtapa") ?>',
          method: 'POST',
          data: {
            numero: leadId,
            etapa: destinoEtapa
          },
          success: function(response) {
            if (response.status === 'ok') {
              showSuccessMessage("Lead movido com sucesso!");
            } else {
              alert(response.message || "Erro ao mover o lead!");
            }
          },
          error: function() {
            alert("Erro na requisição AJAX");
          }
        });
      }
    });
  });

  function showSuccessMessage(message) {
    const toast = document.createElement('div');
    toast.className = 'toast-message bg-green-500 text-white p-4 rounded-md shadow-lg fixed bottom-4 right-4 mb-4';
    toast.innerText = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
  }
});
</script>


</body>
</html>
