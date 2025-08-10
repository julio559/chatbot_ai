<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>CRM Assistente • Etapas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800">
<div class="min-h-screen flex">
  <?= view('sidebar') ?>

  <main class="flex-1 p-6">
    <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold">Gerenciar Etapas</h1>

      <?php if (session()->getFlashdata('msg')): ?>
        <div class="px-4 py-2 rounded bg-green-100 text-green-700 text-sm shadow">
          <?= esc(session()->getFlashdata('msg')) ?>
        </div>
      <?php endif; ?>

      <?php if ($errs = session()->getFlashdata('errors')): ?>
        <div class="px-4 py-2 rounded bg-red-100 text-red-700 text-sm shadow">
          <?= esc(implode(' • ', $errs)) ?>
        </div>
      <?php endif; ?>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Formulário -->
      <section class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow p-5 border border-gray-100">
          <h2 class="font-semibold mb-4" id="formTitle">Nova Etapa</h2>

          <form action="/etapas/salvar" method="post" id="etapaForm" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="id">

            <div>
              <label class="block text-sm font-medium mb-1">Nome da etapa</label>
              <input type="text" name="etapa_atual" id="etapa_atual"
                     class="w-full rounded-xl border border-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                     placeholder="ex.: entrada, agendamento, humano" required>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Tempo de resposta (segundos)</label>
              <input type="number" name="tempo_resposta" id="tempo_resposta" min="0" max="60" value="5"
                     class="w-full rounded-xl border border-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Prompt base (opcional)</label>
              <textarea name="prompt_base" id="prompt_base" rows="5"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Texto do prompt específico desta etapa..."></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="modo_formal" id="modo_formal" class="rounded">
                <span class="text-sm">Modo formal</span>
              </label>
              <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="permite_respostas_longas" id="permite_respostas_longas" class="rounded">
                <span class="text-sm">Respostas longas</span>
              </label>
              <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="permite_redirecionamento" id="permite_redirecionamento" class="rounded" checked>
                <span class="text-sm">Redirecionamento</span>
              </label>
            </div>

            <div class="flex items-center gap-3 pt-2">
              <button class="px-5 py-2 rounded-xl bg-blue-600 text-white font-medium">Salvar</button>
              <button type="button" id="btnCancelar" class="px-5 py-2 rounded-xl bg-gray-100 text-gray-700">Cancelar</button>
            </div>
          </form>
        </div>
      </section>

      <!-- Tabela -->
      <section class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow p-5 border border-gray-100">
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold">Etapas cadastradas</h2>
            <input id="busca" type="text" placeholder="Buscar..."
                   class="rounded-xl border border-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="text-left bg-gray-50">
                <tr>
                  <th class="px-3 py-2 w-10">#</th>
                  <th class="px-3 py-2">Etapa</th>
                  <th class="px-3 py-2">Tempo (s)</th>
                  <th class="px-3 py-2">Formal</th>
                  <th class="px-3 py-2">Longas</th>
                  <th class="px-3 py-2">Redir.</th>
                  <th class="px-3 py-2">Criado em</th>
                  <th class="px-3 py-2 text-right">Ações</th>
                </tr>
              </thead>
              <tbody id="tbodyEtapas" class="divide-y">
                <?php foreach ($etapas as $e): ?>
                  <tr class="hover:bg-gray-50" draggable="true" data-id="<?= esc($e['id']) ?>">
                    <td class="px-3 py-2 cursor-grab select-none text-gray-400" title="Arraste para reordenar">⋮⋮</td>
                    <td class="px-3 py-2"><?= esc($e['etapa_atual']) ?></td>
                    <td class="px-3 py-2"><?= esc($e['tempo_resposta']) ?></td>
                    <td class="px-3 py-2"><?= $e['modo_formal'] ? 'Sim' : 'Não' ?></td>
                    <td class="px-3 py-2"><?= $e['permite_respostas_longas'] ? 'Sim' : 'Não' ?></td>
                    <td class="px-3 py-2"><?= $e['permite_redirecionamento'] ? 'Sim' : 'Não' ?></td>
                    <td class="px-3 py-2"><?= esc($e['criado_em'] ?? '-') ?></td>
                    <td class="px-3 py-2 text-right space-x-1">
                      <button class="px-2 py-1 rounded bg-amber-100 text-amber-800"
                              onclick='editarEtapa(<?= json_encode($e, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'>
                        Editar
                      </button>

                      <form action="/etapas/<?= esc($e['id']) ?>/up" method="post" class="inline">
                        <?= csrf_field() ?>
                        <button class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200" title="Mover para cima">↑</button>
                      </form>

                      <form action="/etapas/<?= esc($e['id']) ?>/down" method="post" class="inline">
                        <?= csrf_field() ?>
                        <button class="px-2 py-1 rounded bg-gray-100 hover:bg-gray-200" title="Mover para baixo">↓</button>
                      </form>

                      <form action="/etapas/<?= esc($e['id']) ?>/excluir" method="post" class="inline"
                            onsubmit="return confirm('Excluir a etapa &quot;<?= esc($e['etapa_atual']) ?>&quot;?');">
                        <?= csrf_field() ?>
                        <button class="px-2 py-1 rounded bg-red-100 text-red-700">Excluir</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>

                <?php if (empty($etapas)): ?>
                  <tr><td colspan="8" class="px-3 py-6 text-center text-gray-500">Nenhuma etapa cadastrada.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </main>
</div>

<script>
// ====== Filtro da tabela ======
document.getElementById('busca').addEventListener('input', function () {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#tbodyEtapas tr').forEach(tr => {
    const text = tr.innerText.toLowerCase();
    tr.style.display = text.includes(q) ? '' : 'none';
  });
});

// ====== Preencher formulário para edição ======
function editarEtapa(e) {
  document.getElementById('formTitle').textContent = 'Editar Etapa';
  document.getElementById('id').value = e.id;
  document.getElementById('etapa_atual').value = e.etapa_atual || '';
  document.getElementById('tempo_resposta').value = e.tempo_resposta || 5;
  document.getElementById('prompt_base').value = e.prompt_base || '';
  document.getElementById('modo_formal').checked = !!Number(e.modo_formal);
  document.getElementById('permite_respostas_longas').checked = !!Number(e.permite_respostas_longas);
  document.getElementById('permite_redirecionamento').checked = !!Number(e.permite_redirecionamento);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ====== Reset do formulário ======
document.getElementById('btnCancelar').addEventListener('click', () => {
  document.getElementById('formTitle').textContent = 'Nova Etapa';
  document.getElementById('id').value = '';
  document.getElementById('etapa_atual').value = '';
  document.getElementById('tempo_resposta').value = 5;
  document.getElementById('prompt_base').value = '';
  document.getElementById('modo_formal').checked = false;
  document.getElementById('permite_respostas_longas').checked = false;
  document.getElementById('permite_redirecionamento').checked = true;
});

// ====== Drag & Drop ======
const tbody = document.getElementById('tbodyEtapas');
let dragging;

tbody.addEventListener('dragstart', (e) => {
  const tr = e.target.closest('tr[draggable="true"]');
  if (!tr) return;
  dragging = tr;
  tr.classList.add('opacity-60');
  e.dataTransfer.effectAllowed = 'move';
});
tbody.addEventListener('dragend', (e) => {
  const tr = e.target.closest('tr[draggable="true"]');
  if (tr) tr.classList.remove('opacity-60');
  dragging = null;
  salvarOrdem(); // persiste quando solta
});
tbody.addEventListener('dragover', (e) => {
  e.preventDefault();
  const tr = e.target.closest('tr[draggable="true"]');
  if (!tr || tr === dragging) return;
  const rect = tr.getBoundingClientRect();
  const before = (e.clientY - rect.top) < (rect.height / 2);
  tbody.insertBefore(dragging, before ? tr : tr.nextSibling);
});

async function salvarOrdem() {
  const ids = Array.from(tbody.querySelectorAll('tr[draggable="true"]'))
    .map(tr => tr.getAttribute('data-id'));

  const form = new FormData();
  // tenta reaproveitar o CSRF do primeiro form da página
  const csrfInput = document.querySelector('#etapaForm input[name="<?= esc(csrf_token()) ?>"]');
  if (csrfInput) form.append('<?= esc(csrf_token()) ?>', csrfInput.value);
  ids.forEach(id => form.append('ids[]', id));

  try {
    const res = await fetch('/etapas/ordenar', { method: 'POST', body: form });
    if (!res.ok) console.error('Falha ao salvar ordem');
  } catch (err) {
    console.error(err);
  }
}
</script>
</body>
</html>
