<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>Mensagens Agendadas | CRM Assistente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800">
<div class="flex min-h-screen">

  <?= view('sidebar') ?>

  <main class="flex-1 p-6">
    <header class="mb-6 flex items-center justify-between gap-4 flex-wrap">
      <h1 class="text-2xl font-bold">Mensagens Agendadas</h1>

      <div class="flex items-center gap-2">
        <input id="q" type="text" placeholder="Buscar por nome, telefone ou texto..." class="w-72 max-w-full rounded-xl border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500">
        <select id="status" class="rounded-xl border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500">
          <option value="">Todos</option>
          <option value="pendente">Pendentes</option>
          <option value="enviado">Enviados</option>
          <option value="cancelado">Cancelados</option>
        </select>
        <button id="btnFiltrar" class="px-4 py-2 rounded-xl bg-blue-600 text-white">Filtrar</button>
        <button id="btnLimpar" class="px-3 py-2 rounded-xl bg-gray-100">Limpar</button>
      </div>
    </header>

    <section class="bg-white rounded-2xl shadow border border-gray-100">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-left bg-gray-50">
            <tr>
              <th class="px-4 py-3">ID</th>
              <th class="px-4 py-3">Cliente</th>
              <th class="px-4 py-3">Telefone</th>
              <th class="px-4 py-3">Mensagem</th>
              <th class="px-4 py-3">Enviar em</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3 text-right">Ações</th>
            </tr>
          </thead>
          <tbody id="tbody" class="divide-y">
            <!-- linhas vão via JS -->
          </tbody>
        </table>
      </div>

      <div id="empty" class="hidden p-8 text-center text-gray-500">Nenhum agendamento encontrado.</div>
    </section>
  </main>
</div>

<!-- Modal de Edição -->
<div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center">
  <div class="absolute inset-0 bg-black/40" onclick="fecharModal()"></div>
  <div class="relative bg-white w-full max-w-xl rounded-2xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold">Editar agendamento <span id="mId" class="text-gray-500"></span></h3>
      <button class="text-gray-500" onclick="fecharModal()">✕</button>
    </div>

    <form id="form" onsubmit="return false;" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" id="mIdVal">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">Data</label>
          <input id="mData" type="date" class="w-full border rounded-lg px-3 py-2">
        </div>
        <div>
          <label class="block text-sm mb-1">Hora</label>
          <input id="mHora" type="time" step="60" class="w-full border rounded-lg px-3 py-2">
        </div>
      </div>

      <div>
        <label class="block text-sm mb-1">Mensagem</label>
        <textarea id="mMensagem" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="Texto a ser enviado..."></textarea>
      </div>

      <div>
        <label class="block text-sm mb-1">Status</label>
        <select id="mStatus" class="w-full border rounded-lg px-3 py-2">
          <option value="pendente">Pendente</option>
          <option value="enviado">Enviado</option>
          <option value="cancelado">Cancelado</option>
        </select>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2">
        <button type="button" class="px-4 py-2 rounded-lg bg-gray-100" onclick="fecharModal()">Cancelar</button>
        <button id="btnSalvar" class="px-4 py-2 rounded-lg bg-blue-600 text-white">Salvar</button>
      </div>
    </form>
  </div>
</div>

<script>
const csrfName = '<?= esc(csrf_token()) ?>';
const csrfHash = '<?= esc(csrf_hash()) ?>';

function toast(msg){
  const t = document.createElement('div');
  t.className = 'fixed bottom-4 right-4 bg-gray-900 text-white px-4 py-2 rounded-lg shadow';
  t.textContent = msg; document.body.appendChild(t);
  setTimeout(()=>t.remove(), 2400);
}
function esc(t){return (t||'').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function dtBR(iso){
  if(!iso) return '-';
  const d = new Date(iso.replace(' ','T'));
  if (isNaN(d)) return iso;
  return d.toLocaleString();
}

const state = {
  items: [],
  edit: null
};

async function carregar(){
  const q = document.getElementById('q').value.trim();
  const status = document.getElementById('status').value;
  const url = new URL('<?= base_url("agendamentos/list") ?>', window.location.origin);
  if (q) url.searchParams.set('q', q);
  if (status) url.searchParams.set('status', status);

  const res = await fetch(url.toString());
  const data = await res.json();
  state.items = data.items || [];
  renderTabela();
}

function renderTabela(){
  const tb = document.getElementById('tbody');
  const empty = document.getElementById('empty');
  tb.innerHTML = '';
  if (!state.items.length){
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');

  state.items.forEach(item => {
    const msgShort = (item.mensagem || '').length > 80 ? esc(item.mensagem.slice(0,80)) + '…' : esc(item.mensagem || '');
    tb.insertAdjacentHTML('beforeend', `
      <tr class="hover:bg-gray-50">
        <td class="px-4 py-3">${item.id}</td>
        <td class="px-4 py-3">${esc(item.paciente_nome || 'Paciente')}</td>
        <td class="px-4 py-3">+${esc(item.numero)}</td>
        <td class="px-4 py-3">${msgShort}</td>
        <td class="px-4 py-3">${dtBR(item.enviar_em)}</td>
        <td class="px-4 py-3">
          <span class="px-2 py-0.5 rounded text-xs ${badge(item.status)}">${item.status}</span>
        </td>
        <td class="px-4 py-3 text-right">
          <button class="px-3 py-1 rounded-lg bg-amber-100 text-amber-800 mr-2" onclick='abrirEdicao(${JSON.stringify(item)})'>Editar</button>
          <button class="px-3 py-1 rounded-lg bg-red-100 text-red-700" onclick="excluir(${item.id})">Excluir</button>
        </td>
      </tr>
    `);
  });
}

function badge(st){
  if (st === 'pendente')  return 'bg-yellow-100 text-yellow-800';
  if (st === 'enviado')   return 'bg-green-100 text-green-700';
  if (st === 'cancelado') return 'bg-gray-200 text-gray-700';
  return 'bg-gray-100 text-gray-700';
}

/* ====== filtros ====== */
document.getElementById('btnFiltrar').addEventListener('click', carregar);
document.getElementById('btnLimpar').addEventListener('click', () => {
  document.getElementById('q').value = '';
  document.getElementById('status').value = '';
  carregar();
});
document.getElementById('q').addEventListener('keydown', (e)=>{ if(e.key==='Enter'){carregar();} });

/* ====== edição ====== */
function abrirModal(){ const m=document.getElementById('modal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function fecharModal(){ const m=document.getElementById('modal'); m.classList.add('hidden'); m.classList.remove('flex'); state.edit=null; }

function abrirEdicao(item){
  state.edit = item;
  document.getElementById('mId').textContent    = '#' + item.id;
  document.getElementById('mIdVal').value       = item.id;
  document.getElementById('mMensagem').value    = item.mensagem || '';

  // quebra enviar_em em data/hora
  const d = new Date((item.enviar_em || '').replace(' ','T'));
  const yyyy = d.getFullYear().toString().padStart(4,'0');
  const mm   = (d.getMonth()+1).toString().padStart(2,'0');
  const dd   = d.getDate().toString().padStart(2,'0');
  const HH   = d.getHours().toString().padStart(2,'0');
  const II   = d.getMinutes().toString().padStart(2,'0');

  document.getElementById('mData').value    = isNaN(d) ? '' : `${yyyy}-${mm}-${dd}`;
  document.getElementById('mHora').value    = isNaN(d) ? '' : `${HH}:${II}`;
  document.getElementById('mStatus').value  = item.status || 'pendente';

  abrirModal();
}

document.getElementById('btnSalvar').addEventListener('click', async () => {
  const id   = document.getElementById('mIdVal').value;
  const msg  = document.getElementById('mMensagem').value.trim();
  const data = document.getElementById('mData').value;
  const hora = document.getElementById('mHora').value;
  const st   = document.getElementById('mStatus').value;

  if(!msg || !data || !hora){ toast('Preencha mensagem, data e hora.'); return; }

  const fd = new FormData();
  fd.append('<?= esc(csrf_token()) ?>', '<?= esc(csrf_hash()) ?>');
  fd.append('mensagem', msg);
  fd.append('data', data);
  fd.append('hora', hora);
  fd.append('status', st);

  const res = await fetch('<?= base_url("agendamentos/update") ?>/'+id, { method:'POST', body: fd });
  if (res.ok) {
    toast('Agendamento atualizado!');
    fecharModal();
    await carregar();
  } else {
    const j = await res.json().catch(()=>({msg:'Erro ao salvar'}));
    toast(j.msg || 'Erro ao salvar');
  }
});

/* ====== excluir ====== */
async function excluir(id){
  if(!confirm('Deseja realmente excluir este agendamento?')) return;

  const fd = new FormData();
  fd.append('<?= esc(csrf_token()) ?>', '<?= esc(csrf_hash()) ?>');

  const res = await fetch('<?= base_url("agendamentos/delete") ?>/'+id, { method:'POST', body: fd });
  if (res.ok){
    toast('Agendamento excluído.');
    await carregar();
  } else {
    const j = await res.json().catch(()=>({msg:'Erro ao excluir'}));
    toast(j.msg || 'Erro ao excluir');
  }
}

/* boot */
carregar();
</script>
</body>
</html>
