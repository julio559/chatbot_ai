<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pacientes | CRM da Dra. Bruna Sathler</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
  <div class="flex h-screen">
    <!-- Sidebar -->
<?= view('sidebar') ?>


    <!-- Main Content -->
    <main class="flex-1 p-6 overflow-y-auto">
      <h2 class="text-2xl font-semibold text-gray-800 mb-4">Lista de Pacientes</h2>
      <div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
        <table class="min-w-full text-sm text-left text-gray-700">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="px-4 py-2 font-semibold">Nome</th>
              <th class="px-4 py-2 font-semibold">Telefone</th>
              <th class="px-4 py-2 font-semibold">Etapa</th>
              <th class="px-4 py-2 font-semibold">Último Contato</th>
              <th class="px-4 py-2 font-semibold">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pacientes as $p): ?>
              <tr class="hover:bg-gray-50 border-b">
                <td class="px-4 py-2"><?= esc($p['nome']) ?></td>
                <td class="px-4 py-2"><?= esc($p['telefone']) ?></td>
                <td class="px-4 py-2 capitalize"><?= esc($p['etapa'] ?? 'inicio') ?></td>
                <td class="px-4 py-2"><?= esc($p['ultimo_contato']) ?></td>
                <td class="px-4 py-2 space-x-2">
                  <button onclick="openModal('modal-<?= $p['id'] ?>')" class="text-blue-600 hover:underline">Editar</button>
                  <a href="/paciente/excluir/<?= $p['id'] ?>" class="text-red-600 hover:underline">Excluir</a>
                </td>
              </tr>

              <!-- Modal Lateral de Edição -->
<div id="modal-<?= $p['id'] ?>" class="fixed inset-0 z-50 hidden bg-black bg-opacity-40 flex justify-end">
  <div class="bg-white w-full max-w-md h-full flex flex-col shadow-xl">
    <!-- Cabeçalho -->
    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
      <h3 class="text-lg font-semibold text-gray-800">Editar Paciente</h3>
      <button onclick="closeModal('modal-<?= $p['id'] ?>')" class="text-gray-500 hover:text-red-600 text-xl font-bold">&times;</button>
    </div>

    <!-- Formulário -->
    <form action="/paciente/atualizar/<?= $p['id'] ?>" method="post" class="flex-1 flex flex-col justify-between">
      <div class="px-6 py-4 space-y-4 overflow-y-auto">
        <div>
          <label class="block text-sm font-medium text-gray-700">Nome</label>
          <input type="text" name="nome" value="<?= esc($p['nome']) ?>" class="w-full p-2 border rounded" required>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Telefone</label>
          <input type="text" name="telefone" value="<?= esc($p['telefone']) ?>" class="w-full p-2 border rounded" required>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Etapa</label>
          <select name="etapa" class="w-full p-2 border rounded">
  <?php foreach ($etapas as $key => $label): ?>
    <option value="<?= esc($key) ?>" <?= ($p['etapa'] ?? '') === $key ? 'selected' : '' ?>>
      <?= esc($label) ?>
    </option>
  <?php endforeach; ?>
</select>

        </div>
      </div>

      <!-- Rodapé com Botões -->
      <div class="px-6 py-4 border-t border-gray-200 bg-white flex justify-end space-x-2">
        <button type="button" onclick="closeModal('modal-<?= $p['id'] ?>')" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancelar</button>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Salvar</button>
      </div>
    </form>
  </div>
</div>

            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <script>
    function openModal(id) {
      document.getElementById(id).classList.remove('hidden');
    }

    function closeModal(id) {
      document.getElementById(id).classList.add('hidden');
    }
  </script>
</body>
</html>
