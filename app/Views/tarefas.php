<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>CRM • Tarefas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- SortableJS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: { 600:'#4f46e5', 700:'#4338ca' },
            accent: { 500:'#10b981' }
          },
          boxShadow: { soft: '0 10px 30px rgba(2,6,23,.06)' }
        }
      }
    };
  </script>
  <style>
    .fade-enter{opacity:0;transform:translateY(6px)}
    .fade-enter-active{opacity:1;transform:translateY(0);transition:all .18s ease}
    .ellipsis{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

    /* Drawer */
    .drawer{transform:translateX(100%); transition:transform .22s ease;}
    .drawer.open{transform:none;}
    .backdrop{opacity:0; transition:opacity .22s ease;}
    .backdrop.show{opacity:1;}
  </style>
</head>
<body class="bg-slate-50 text-slate-800">
<div class="min-h-screen flex">

  <?= view('sidebar') ?>

  <main class="flex-1 p-4 md:p-6 space-y-6">

    <!-- Hero -->
    <section class="rounded-2xl overflow-hidden shadow-soft relative">
      <div class="absolute inset-0 bg-gradient-to-r from-primary-600 to-accent-500 opacity-90"></div>
      <div class="relative p-6 md:p-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="text-white">
          <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">Minhas Tarefas</h1>
          <p class="text-white/80 text-sm mt-1">Crie, conclua, filtre e reordene por prioridade e data.</p>
        </div>
        <div class="flex items-center gap-2">
          <button id="btnNova" type="button"
                  class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white text-slate-900 hover:shadow">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nova tarefa
          </button>
        </div>
      </div>
    </section>

    <!-- Filtros + Stats -->
    <section class="grid grid-cols-1 xl:grid-cols-4 gap-4">
      <div class="xl:col-span-3">
        <div class="bg-white rounded-2xl shadow-soft ring-1 ring-slate-200 p-3 md:p-4">
          <div class="flex flex-col md:flex-row md:items-center gap-3">
            <div class="relative flex-1">
              <input id="busca" type="text" placeholder="/ Buscar por título, descrição, telefone..."
                     class="w-full rounded-xl border border-slate-300 pl-10 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-700">
              <span class="pointer-events-none absolute left-3 top-2.5 text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M12.9 14.32a8 8 0 111.414-1.414l4.387 4.386a1 1 0 01-1.414 1.415l-4.387-4.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd"/>
                </svg>
              </span>
            </div>

            <div class="flex items-center gap-2">
              <select id="fStatus" class="rounded-xl border border-slate-300 px-3 py-2">
                <option value="pendente">Pendentes</option>
                <option value="todas">Todas</option>
                <option value="concluida">Concluídas</option>
              </select>
              <input id="fDe" type="date" class="rounded-xl border border-slate-300 px-3 py-2">
              <input id="fAte" type="date" class="rounded-xl border border-slate-300 px-3 py-2">
              <button id="btnFiltrar" class="px-4 py-2 rounded-xl bg-slate-900 text-white hover:shadow">Aplicar</button>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl shadow-soft ring-1 ring-slate-200 p-3 text-center">
          <div class="text-xs text-slate-500">Total</div>
          <div id="statTotal" class="text-xl font-semibold">0</div>
        </div>
        <div class="bg-white rounded-2xl shadow-soft ring-1 ring-slate-200 p-3 text-center">
          <div class="text-xs text-slate-500">Pendentes</div>
          <div id="statPend" class="text-xl font-semibold">0</div>
        </div>
        <div class="bg-white rounded-2xl shadow-soft ring-1 ring-slate-200 p-3 text-center">
          <div class="text-xs text-slate-500">Concluídas</div>
          <div id="statConc" class="text-xl font-semibold">0</div>
        </div>
      </div>
    </section>

    <!-- Lista -->
    <section class="bg-white rounded-2xl shadow-soft ring-1 ring-slate-200">
      <div class="px-4 py-3 border-b flex items-center justify-between">
        <h3 class="font-semibold">Tarefas</h3>
        <div class="text-xs text-slate-500" id="countInfo">0 tarefa(s)</div>
      </div>

      <div id="lista" class="divide-y divide-slate-100"></div>
      <div id="vazio" class="hidden p-12 text-center text-slate-500">
        Nada por aqui. Clique em <span class="font-medium">Nova tarefa</span> para começar.
      </div>
    </section>
  </main>
</div>

<!-- FAB -->
<button id="fabNova" type="button"
        class="fixed bottom-6 right-6 z-40 rounded-full shadow-soft bg-primary-600 hover:bg-primary-700 text-white px-5 py-3">
  + Tarefa
</button>

<!-- DRAWER -->
<div id="drawerWrap" class="pointer-events-none fixed inset-0 z-50">
  <div id="drawerBackdrop" class="backdrop absolute inset-0 bg-slate-900/60"></div>
  <aside id="drawer" class="drawer pointer-events-auto absolute inset-y-0 right-0 w-full sm:max-w-md bg-white shadow-2xl ring-1 ring-black/5 flex flex-col">
    <header class="px-5 py-4 border-b flex items-center justify-between">
      <h3 id="drawerTitle" class="font-semibold">Nova Tarefa</h3>
      <button id="drawerBtnClose" type="button" class="px-3 py-1.5 rounded-lg border hover:bg-slate-50 text-sm">Fechar ✕</button>
    </header>

    <form id="taskForm" action="<?= base_url('/tarefas/salvar') ?>" method="post" class="p-5 space-y-4 overflow-y-auto">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="id">

      <div>
        <label class="block text-sm mb-1">Título</label>
        <input name="titulo" id="titulo" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-700" required>
      </div>

      <div>
        <label class="block text-sm mb-1">Descrição</label>
        <textarea name="descricao" id="descricao" rows="4" class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-700"></textarea>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm mb-1">Data</label>
          <input name="data" id="data" type="date" class="w-full rounded-xl border border-slate-300 px-3 py-2">
        </div>
        <div>
          <label class="block text-sm mb-1">Hora</label>
          <input name="hora" id="hora" type="time" step="60" class="w-full rounded-xl border border-slate-300 px-3 py-2">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm mb-1">Prioridade</label>
          <select name="prioridade" id="prioridade" class="w-full rounded-xl border border-slate-300 px-3 py-2">
            <option value="1">Alta</option>
            <option value="2" selected>Normal</option>
            <option value="3">Baixa</option>
          </select>
        </div>
        <div>
          <label class="block text-sm mb-1">Lembrete (min antes)</label>
          <input name="lembrete_minutos" id="lembrete_minutos" type="number" min="0" step="5" class="w-full rounded-xl border border-slate-300 px-3 py-2" placeholder="Ex.: 15">
        </div>
      </div>

      <div>
        <label class="block text-sm mb-1">Telefone do paciente (opcional)</label>
        <input name="lead_numero" id="lead_numero" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2" placeholder="Ex.: 5531999999999">
      </div>

      <div class="pt-1">
        <button type="submit" class="w-full px-5 py-2 rounded-xl bg-slate-900 text-white font-medium">Salvar</button>
      </div>
    </form>
  </aside>
</div>

<!-- Toasts -->
<div id="toasts" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

<!-- TODO: todo o JS isolado em módulo para não colidir nomes -->
<script type="module">
'use strict';

/* ===== Utils ===== */
const toast = (msg, type) => {
  const wrap = document.getElementById('toasts');
  const el = document.createElement('div');
  el.className = `fade-enter px-4 py-2 rounded-xl shadow text-sm text-white ${type==='error'?'bg-red-600':type==='success'?'bg-emerald-600':'bg-slate-900'}`;
  el.textContent = msg;
  wrap.appendChild(el);
  requestAnimationFrame(()=> el.classList.add('fade-enter-active'));
  setTimeout(()=> el.remove(), 2400);
};
const esc = s => (s||'').replace(/</g,'&lt;').replace(/>/g,'&gt;');
const toDateParts = iso => {
  if(!iso) return {d:'', t:''};
  const d = new Date(String(iso).replace(' ','T'));
  if (isNaN(d)) return {d:'', t:''};
  return { d: d.toISOString().slice(0,10), t: d.toTimeString().slice(0,5) };
};
const badgePrioridade = p => (+p===1
  ? '<span class="text-[11px] px-2 py-0.5 rounded bg-red-50 text-red-700">Alta</span>'
  : +p===3
  ? '<span class="text-[11px] px-2 py-0.5 rounded bg-slate-100 text-slate-700">Baixa</span>'
  : '<span class="text-[11px] px-2 py-0.5 rounded bg-amber-50 text-amber-700">Normal</span>');
const dotDue = dt => {
  if (!dt) return '';
  const now = new Date();
  const d   = new Date(String(dt).replace(' ','T'));
  const end = new Date(); end.setHours(23,59,59,999);
  if (d < new Date(now.toDateString())) return '<span class="inline-block w-2 h-2 rounded-full bg-red-500 mr-1 align-middle"></span>';
  if (d <= end) return '<span class="inline-block w-2 h-2 rounded-full bg-yellow-400 mr-1 align-middle"></span>';
  return '<span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1 align-middle"></span>';
};

/* ===== Estado ===== */
let tarefas = [];
let buscaTimer = null;
let sortableList = null;

/* ===== DOM refs ===== */
const buscaEl = document.getElementById('busca');
const fStatus = document.getElementById('fStatus');
const fDe     = document.getElementById('fDe');
const fAte    = document.getElementById('fAte');

const btnNovaTop = document.getElementById('btnNova');
const btnNovaFab = document.getElementById('fabNova');

const drawerWrap     = document.getElementById('drawerWrap');
const drawerEl       = document.getElementById('drawer');
const drawerBackdrop = document.getElementById('drawerBackdrop');
const drawerTitle    = document.getElementById('drawerTitle');
const drawerBtnClose = document.getElementById('drawerBtnClose');

function openDrawer(task=null){
  const form = document.getElementById('taskForm');
  form?.reset();

  document.getElementById('id').value = task?.id || '';
  drawerTitle.textContent = task ? 'Editar Tarefa' : 'Nova Tarefa';
  document.getElementById('titulo').value = task?.titulo || '';
  document.getElementById('descricao').value = task?.descricao || '';
  const parts = toDateParts(task?.data_hora || '');
  document.getElementById('data').value = parts.d;
  document.getElementById('hora').value = parts.t;
  document.getElementById('prioridade').value = task?.prioridade || '2';
  document.getElementById('lembrete_minutos').value = (task?.lembrete_minutos ?? '');
  document.getElementById('lead_numero').value = task?.lead_numero || '';

  drawerWrap.classList.remove('pointer-events-none');
  drawerEl.classList.add('open');
  drawerBackdrop.classList.add('show');

  setTimeout(()=> document.getElementById('titulo')?.focus(), 0);
}
function closeDrawer(){
  drawerEl.classList.remove('open');
  drawerBackdrop.classList.remove('show');
  setTimeout(()=> drawerWrap.classList.add('pointer-events-none'), 220);
}

/* Bind UI sem inline */
btnNovaTop?.addEventListener('click', ()=> openDrawer());
btnNovaFab?.addEventListener('click', ()=> openDrawer());
drawerBtnClose?.addEventListener('click', closeDrawer);
drawerBackdrop?.addEventListener('click', closeDrawer);
document.addEventListener('keydown', (e)=> {
  if (e.key==='Escape' && drawerEl.classList.contains('open')) closeDrawer();
  if (e.key.toLowerCase()==='n' && !drawerEl.classList.contains('open')) openDrawer();
});

/* ===== Filtros ===== */
document.getElementById('btnFiltrar')?.addEventListener('click', carregar);
buscaEl?.addEventListener('input', () => { clearTimeout(buscaTimer); buscaTimer=setTimeout(()=>render(),180); });
document.addEventListener('keydown', (e)=>{ if(e.key==='/' && document.activeElement!==buscaEl){ e.preventDefault(); buscaEl?.focus(); } });

/* ===== CRUD ===== */
async function carregar(){
  const params = new URLSearchParams({
    q: buscaEl?.value || '',
    status: fStatus?.value || 'pendente',
    data_de: fDe?.value || '',
    data_ate: fAte?.value || ''
  });
  const res = await fetch('<?= base_url('/tarefas/listar') ?>?'+params.toString());
  const data = await res.json().catch(()=> ({}));
  if (!res.ok || !data.ok) {
    // fallback para você conseguir testar o front mesmo sem backend
    tarefas = [];
    render();
    return;
  }
  tarefas = data.tarefas || [];
  render();
}

const linhaTarefa = (t) => {
  const dt = t.data_hora ? new Date(String(t.data_hora).replace(' ','T')) : null;
  const quando = dt ? dt.toLocaleString() : 'Sem data';
  const done = t.status === 'concluida';
  const lead = t.lead_numero ? ` • +${esc(t.lead_numero)}` : '';
  const lemb = (t.lembrete_minutos || t.lembrete_minutos === 0) ? ` • lembre ${t.lembrete_minutos}min antes` : '';
  const prio = badgePrioridade(parseInt(t.prioridade || 2));

  return `
  <div class="item group flex items-start gap-3 px-4 py-4" data-id="${t.id}">
    <div class="pt-1">
      <input type="checkbox" ${done?'checked':''} data-action="toggle" class="h-4 w-4 rounded border-slate-300">
    </div>
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2">
        <button type="button" data-action="edit" class="text-left font-medium ${done?'line-through text-slate-400':'text-slate-900'} ellipsis hover:underline">
          ${esc(t.titulo||'')}
        </button>
        ${prio}
      </div>
      <div class="text-sm ${done?'text-slate-400':'text-slate-600'} whitespace-pre-wrap mt-0.5">${esc(t.descricao||'')}</div>
      <div class="text-xs text-slate-500 mt-1">${dotDue(t.data_hora)} ${quando}${lead}${lemb}</div>
    </div>
    <div class="shrink-0 flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
      <button data-action="edit" class="px-2 py-1 rounded bg-amber-100 text-amber-800 hover:bg-amber-200">Editar</button>
      <button data-action="delete" class="px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200">Excluir</button>
      <span class="handle cursor-grab select-none px-2 text-slate-400" title="Arraste para reordenar">⋮⋮</span>
    </div>
  </div>`;
};

function atualizarStats(){
  const total = tarefas.length;
  const concl = tarefas.filter(t=> t.status==='concluida').length;
  const pend  = total - concl;
  document.getElementById('statTotal').textContent = total;
  document.getElementById('statPend').textContent  = pend;
  document.getElementById('statConc').textContent  = concl;
}

function render(){
  const q = (buscaEl?.value||'').toLowerCase();
  const list = document.getElementById('lista');
  const vazio = document.getElementById('vazio');

  const filtered = (tarefas||[]).filter(t => {
    const txt = `${t.titulo||''} ${t.descricao||''} ${t.lead_numero||''}`.toLowerCase();
    if (!txt.includes(q)) return false;
    if (fStatus?.value==='concluida' && t.status!=='concluida') return false;
    if (fStatus?.value==='pendente' && t.status==='concluida') return false;
    if (fDe?.value){ if (!t.data_hora || new Date(String(t.data_hora).replace(' ','T')) < new Date(fDe.value)) return false; }
    if (fAte?.value){ if (!t.data_hora || new Date(String(t.data_hora).replace(' ','T')) > new Date(fAte.value+'T23:59:59')) return false; }
    return true;
  });

  document.getElementById('countInfo').textContent = `${filtered.length} tarefa(s)`;
  atualizarStats();

  if (!filtered.length) {
    list.innerHTML = '';
    vazio.classList.remove('hidden');
    if (sortableList && typeof sortableList.destroy === 'function') { sortableList.destroy(); sortableList = null; }
    return;
  }

  vazio.classList.add('hidden');
  list.innerHTML = filtered.map(linhaTarefa).join('');

  // Delegação
  list.querySelectorAll('.item').forEach(row=>{
    row.addEventListener('click', async (e)=>{
      const id = +row.getAttribute('data-id');
      const t  = tarefas.find(x=> +x.id===id);
      if (!t) return;

      const actionBtn = e.target.closest('[data-action]');
      if (!actionBtn) return;
      const action = actionBtn.getAttribute('data-action');

      if (action==='edit') {
        openDrawer(t);
      } else if (action==='delete') {
        excluir(id);
      } else if (action==='toggle') {
        const checked = e.target.checked;
        toggleDone(id, checked);
      }
    });
  });

  // DnD
  if (sortableList && typeof sortableList.destroy === 'function') sortableList.destroy();
  sortableList = new Sortable(list, {
    handle: '.handle',
    animation: 150,
    onEnd: salvarOrdem
  });
}

async function salvarOrdem(){
  const ids = Array.from(document.querySelectorAll('#lista .item')).map(el => el.getAttribute('data-id'));
  const fd = new FormData();
  fd.append('<?= esc(csrf_token()) ?>', '<?= esc(csrf_hash()) ?>');
  ids.forEach(id => fd.append('ids[]', id));
  const res = await fetch('<?= base_url('/tarefas/ordenar') ?>', { method:'POST', body: fd });
  if (!res.ok) toast('Erro ao salvar ordem','error');
}

document.getElementById('taskForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const form = e.currentTarget;
  const fd = new FormData(form);
  const res = await fetch(form.action, { method:'POST', body: fd });
  const data = await res.json().catch(()=> ({}));
  if (!res.ok || !data.ok) {
    toast(data?.msg || 'Falha ao salvar','error');
    return;
  }
  toast('Tarefa salva!','success');
  closeDrawer();
  carregar();
});

async function toggleDone(id, checked){
  const fd = new FormData();
  fd.append('<?= esc(csrf_token()) ?>', '<?= esc(csrf_hash()) ?>');
  fd.append('id', id);
  fd.append('done', checked ? '1' : '0');
  const res = await fetch('<?= base_url('/tarefas/concluir') ?>', { method:'POST', body: fd });
  const data = await res.json().catch(()=> ({}));
  if (!res.ok || !data.ok) {
    toast(data?.msg || 'Erro ao atualizar status','error');
    carregar();
    return;
  }
  carregar();
}

async function excluir(id){
  if (!confirm('Excluir esta tarefa?')) return;
  const fd = new FormData();
  fd.append('<?= esc(csrf_token()) ?>', '<?= esc(csrf_hash()) ?>');
  fd.append('id', id);
  const res = await fetch('<?= base_url('/tarefas/excluir') ?>', { method:'POST', body: fd });
  const data = await res.json().catch(()=> ({}));
  if (!res.ok || !data.ok) { toast(data?.msg || 'Erro ao excluir','error'); return; }
  toast('Tarefa excluída.','success');
  carregar();
}

/* Boot */
carregar();
</script>
</body>
</html>
