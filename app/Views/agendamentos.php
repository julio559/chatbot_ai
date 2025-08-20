<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>Mensagens Agendadas | CRM Assistente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Utilitários extra sem quebrar Tailwind (opcional) */
    .container-safe { max-width: 1200px; }
    .ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fade-enter { opacity: 0; transform: translateY(6px); }
    .fade-enter-active { opacity: 1; transform: translateY(0); transition: all .18s ease; }
  </style>
</head>
<body class="bg-slate-50 text-slate-800">
<div class="min-h-screen">
  <!-- Sidebar mantém a mesma view para não quebrar -->
  <div class="flex min-h-screen">
    <?= view('sidebar') ?>

    <main class="flex-1">
      <!-- Topbar -->
      <header class="border-b border-slate-200 bg-white/70 backdrop-blur supports-[backdrop-filter]:bg-white/60">
        <div class="container-safe mx-auto px-6 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <nav class="text-xs text-slate-500 mb-1" aria-label="Breadcrumb">
              <ol class="flex items-center gap-1">
                <li>CRM</li>
                <li class="opacity-50">/</li>
                <li>Comunicação</li>
                <li class="opacity-50">/</li>
                <li class="font-medium text-slate-700">Mensagens Agendadas</li>
              </ol>
            </nav>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Mensagens Agendadas</h1>
            <p class="text-sm text-slate-500">Gerencie, edite e acompanhe o status dos envios futuros.</p>
          </div>
          <div class="flex items-center gap-2">
            <button id="btnNovo" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 text-white px-4 py-2 shadow hover:shadow-md active:scale-[.99]">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
              Novo agendamento
            </button>
          </div>
        </div>
      </header>

      <!-- Toolbar -->
      <section class="container-safe mx-auto px-6 pt-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div class="flex items-center gap-2 w-full md:w-auto">
            <div class="relative flex-1 md:w-80">
              <input id="q" type="text" placeholder="/ Buscar por nome, telefone ou texto..." class="w-full rounded-xl border border-slate-300 bg-white px-10 py-2.5 outline-none focus:ring-2 focus:ring-slate-900" />
              <span class="pointer-events-none absolute left-3 top-2.5 text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.9 14.32a8 8 0 111.414-1.414l4.387 4.386a1 1 0 01-1.414 1.415l-4.387-4.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd"/></svg>
              </span>
            </div>
            <select id="status" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 focus:ring-2 focus:ring-slate-900">
              <option value="">Todos</option>
              <option value="pendente">Pendentes</option>
              <option value="enviado">Enviados</option>
              <option value="cancelado">Cancelados</option>
            </select>
            <button id="btnFiltrar" class="px-4 py-2.5 rounded-xl bg-slate-900 text-white hover:shadow-md">Filtrar</button>
            <button id="btnLimpar" class="px-3 py-2.5 rounded-xl bg-slate-100 text-slate-700">Limpar</button>
          </div>

          <!-- Atalhos de status (sincronizados com o select #status) -->
          <div class="flex flex-wrap items-center gap-2">
            <button data-chip="" class="chip-status px-3 py-1.5 rounded-full border border-slate-200 bg-white text-xs text-slate-600">Todos</button>
            <button data-chip="pendente" class="chip-status px-3 py-1.5 rounded-full border border-yellow-200 bg-yellow-50 text-xs text-yellow-700">Pendentes</button>
            <button data-chip="enviado" class="chip-status px-3 py-1.5 rounded-full border border-emerald-200 bg-emerald-50 text-xs text-emerald-700">Enviados</button>
            <button data-chip="cancelado" class="chip-status px-3 py-1.5 rounded-full border border-slate-200 bg-slate-50 text-xs text-slate-700">Cancelados</button>
          </div>
        </div>
      </section>

      <!-- Card Tabela -->
      <section class="container-safe mx-auto px-6 py-6">
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200">
          <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
            <div class="text-sm text-slate-600">Total: <span id="count" class="font-medium">0</span></div>
            <div class="flex items-center gap-2">
              <button id="btnExport" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">Exportar CSV</button>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 z-10 bg-slate-50/80 backdrop-blur">
                <tr class="text-left text-slate-600">
                  <th class="px-4 py-3 font-medium">ID</th>
                  <th class="px-4 py-3 font-medium">Cliente</th>
                  <th class="px-4 py-3 font-medium">Telefone</th>
                  <th class="px-4 py-3 font-medium">Mensagem</th>
                  <th class="px-4 py-3 font-medium">Enviar em</th>
                  <th class="px-4 py-3 font-medium">Status</th>
                  <th class="px-4 py-3 font-medium text-right">Ações</th>
                </tr>
              </thead>
              <tbody id="tbody" class="divide-y divide-slate-100">
                <!-- linhas via JS -->
              </tbody>
            </table>
          </div>

          <!-- Empty State -->
          <div id="empty" class="hidden px-8 py-16 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="text-base font-medium text-slate-800">Nenhum agendamento encontrado</h3>
            <p class="mt-1 text-sm text-slate-500">Ajuste os filtros acima ou crie um novo agendamento.</p>
          </div>
        </div>
      </section>
    </main>
  </div>
</div>

<!-- Modal de Edição -->
<div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center">
  <div class="absolute inset-0 bg-slate-900/50" onclick="fecharModal()"></div>
  <div class="relative bg-white w-full max-w-xl rounded-2xl shadow-xl p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold">Editar agendamento <span id="mId" class="text-slate-500"></span></h3>
      <button class="text-slate-500 hover:text-slate-700" onclick="fecharModal()" aria-label="Fechar">✕</button>
    </div>

    <form id="form" onsubmit="return false;" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" id="mIdVal">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">Data</label>
          <input id="mData" type="date" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-slate-900">
        </div>
        <div>
          <label class="block text-sm mb-1">Hora</label>
          <input id="mHora" type="time" step="60" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-slate-900">
        </div>
      </div>

      <div>
        <label class="block text-sm mb-1">Mensagem</label>
        <textarea id="mMensagem" rows="3" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-slate-900" placeholder="Texto a ser enviado..."></textarea>
      </div>

      <div>
        <label class="block text-sm mb-1">Status</label>
        <select id="mStatus" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-slate-900">
          <option value="pendente">Pendente</option>
          <option value="enviado">Enviado</option>
          <option value="cancelado">Cancelado</option>
        </select>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2">
        <button type="button" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700" onclick="fecharModal()">Cancelar</button>
        <button id="btnSalvar" class="px-4 py-2 rounded-lg bg-slate-900 text-white">Salvar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal de Confirmação Excluir -->
<div id="confirmDelete" class="fixed inset-0 z-50 hidden items-center justify-center">
  <div class="absolute inset-0 bg-slate-900/50" onclick="fecharConfirm()"></div>
  <div class="relative bg-white w-full max-w-md rounded-2xl shadow-xl p-6">
    <div class="flex items-start gap-3">
      <div class="h-10 w-10 rounded-xl bg-red-50 flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-9 0h10"/></svg>
      </div>
      <div class="flex-1">
        <h4 class="text-base font-semibold">Excluir agendamento?</h4>
        <p class="mt-1 text-sm text-slate-500">Esta ação não pode ser desfeita.</p>
        <div class="mt-4 flex items-center justify-end gap-2">
          <button class="px-3 py-2 rounded-lg bg-slate-100 text-slate-700" onclick="fecharConfirm()">Cancelar</button>
          <button id="btnConfirmDelete" class="px-3 py-2 rounded-lg bg-red-600 text-white">Excluir</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toast container -->
<div id="toasts" class="fixed bottom-4 right-4 z-[60] space-y-2"></div>

<script>
/* ================== Helpers ================== */
const csrfName = '<?= esc(csrf_token()) ?>';
const csrfHash = '<?= esc(csrf_hash()) ?>';

function toast(msg, type='default'){
  const wrap = document.getElementById('toasts');
  const t = document.createElement('div');
  const base = 'px-4 py-2 rounded-xl shadow text-sm text-white flex items-center gap-2';
  const tone = type==='success' ? 'bg-emerald-600' : type==='error' ? 'bg-red-600' : 'bg-slate-900';
  t.className = base + ' ' + tone + ' fade-enter';
  t.innerHTML = `<span>${msg}</span>`;
  wrap.appendChild(t);
  requestAnimationFrame(()=> t.classList.add('fade-enter-active'));
  setTimeout(()=> t.remove(), 2600);
}

function esc(t){ return (t||'').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function dtBR(iso){
  if(!iso) return '-';
  const d = new Date((iso+'').replace(' ','T'));
  if (isNaN(d)) return iso;
  return d.toLocaleString();
}
function phoneFmt(num){
  const n = (num||'').replace(/\D+/g,'');
  if(n.length>=13){ return `+${n}`; }
  if(n.length===11){ return `(${n.slice(0,2)}) ${n.slice(2,7)}-${n.slice(7)}`; }
  if(n.length===10){ return `(${n.slice(0,2)}) ${n.slice(2,6)}-${n.slice(6)}`; }
  return num||'-';
}

const state = { items: [], edit: null, pendingDeleteId: null, loading: false };

/* ================== Carregar ================== */
async function carregar(){
  try{
    state.loading = true; drawSkeleton();
    const q = document.getElementById('q').value.trim();
    const status = document.getElementById('status').value;
    const url = new URL('<?= base_url("agendamentos/list") ?>', window.location.origin);
    if (q) url.searchParams.set('q', q);
    if (status) url.searchParams.set('status', status);

    const res = await fetch(url.toString());
    const data = await res.json();
    state.items = data.items || [];
    document.getElementById('count').textContent = state.items.length;
    renderTabela();
  }catch(e){
    console.error(e); toast('Erro ao carregar lista','error');
  }finally{ state.loading = false; }
}

function drawSkeleton(){
  const tb = document.getElementById('tbody');
  const empty = document.getElementById('empty');
  empty.classList.add('hidden');
  tb.innerHTML = '';
  for(let i=0;i<6;i++){
    tb.insertAdjacentHTML('beforeend', `
      <tr class="animate-pulse">
        <td class="px-4 py-3"><div class="h-3 w-10 rounded bg-slate-200"></div></td>
        <td class="px-4 py-3"><div class="h-3 w-32 rounded bg-slate-200"></div></td>
        <td class="px-4 py-3"><div class="h-3 w-28 rounded bg-slate-200"></div></td>
        <td class="px-4 py-3"><div class="h-3 w-64 rounded bg-slate-200"></div></td>
        <td class="px-4 py-3"><div class="h-3 w-40 rounded bg-slate-200"></div></td>
        <td class="px-4 py-3"><div class="h-6 w-20 rounded-full bg-slate-200"></div></td>
        <td class="px-4 py-3 text-right"><div class="h-8 w-24 rounded bg-slate-200 ml-auto"></div></td>
      </tr>`);
  }
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
    const msgShort = (item.mensagem || '').length > 90 ? esc(item.mensagem.slice(0,90)) + '…' : esc(item.mensagem || '');
    const badgeClass = badge(item.status);
    tb.insertAdjacentHTML('beforeend', `
      <tr class="hover:bg-slate-50">
        <td class="px-4 py-3 text-slate-600">${item.id}</td>
        <td class="px-4 py-3">
          <div class="flex items-center gap-3">
            <div class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-[11px] font-medium text-slate-600">${(item.paciente_nome||'P')[0]?.toUpperCase?.()||'P'}</div>
            <div class="min-w-0">
              <div class="text-sm font-medium text-slate-800 ellipsis">${esc(item.paciente_nome || 'Paciente')}</div>
              <div class="text-xs text-slate-500 ellipsis">${esc(item.email||'')}</div>
            </div>
          </div>
        </td>
        <td class="px-4 py-3">${phoneFmt(item.numero)}</td>
        <td class="px-4 py-3">
          <div class="max-w-[520px] ellipsis">${msgShort}</div>
        </td>
        <td class="px-4 py-3 text-slate-700">${dtBR(item.enviar_em)}</td>
        <td class="px-4 py-3">
          <span class="px-2 py-0.5 rounded-full text-xs ${badgeClass}">${item.status}</span>
        </td>
        <td class="px-4 py-3 text-right">
          <div class="inline-flex items-center gap-2">
            <button class="px-3 py-1.5 rounded-lg bg-amber-100 text-amber-800 hover:bg-amber-200" onclick='abrirEdicao(${JSON.stringify(item)})'>Editar</button>
            <button class="px-3 py-1.5 rounded-lg bg-red-100 text-red-700 hover:bg-red-200" onclick="excluir(${item.id})">Excluir</button>
          </div>
        </td>
      </tr>
    `);
  });
}

function badge(st){
  if (st === 'pendente')  return 'bg-yellow-100 text-yellow-800';
  if (st === 'enviado')   return 'bg-emerald-100 text-emerald-700';
  if (st === 'cancelado') return 'bg-slate-200 text-slate-700';
  return 'bg-slate-100 text-slate-700';
}

/* ================== Filtros ================== */
function syncChipsFromSelect(){
  const s = document.getElementById('status').value;
  document.querySelectorAll('.chip-status').forEach(btn=>{
    const val = btn.getAttribute('data-chip');
    const active = (val===s) || (val==='' && s==='');
    btn.classList.toggle('ring-2', active);
    btn.classList.toggle('ring-slate-900', active);
  });
}

document.getElementById('btnFiltrar').addEventListener('click', carregar);

document.getElementById('btnLimpar').addEventListener('click', () => {
  document.getElementById('q').value = '';
  document.getElementById('status').value = '';
  syncChipsFromSelect();
  carregar();
});

// chips -> select
Array.from(document.querySelectorAll('.chip-status')).forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const v = btn.getAttribute('data-chip') || '';
    document.getElementById('status').value = v;
    syncChipsFromSelect();
    carregar();
  });
});

// Busca ao pressionar Enter e atalho '/'
const searchInput = document.getElementById('q');
searchInput.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){carregar();} });
document.addEventListener('keydown', (e)=>{ if(e.key==='/' && document.activeElement!==searchInput){ e.preventDefault(); searchInput.focus(); } });

/* ================== Edição ================== */
function abrirModal(){ const m=document.getElementById('modal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function fecharModal(){ const m=document.getElementById('modal'); m.classList.add('hidden'); m.classList.remove('flex'); state.edit=null; }

function abrirEdicao(item){
  state.edit = item;
  document.getElementById('mId').textContent    = '#' + item.id;
  document.getElementById('mIdVal').value       = item.id;
  document.getElementById('mMensagem').value    = item.mensagem || '';

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

  if(!msg || !data || !hora){ toast('Preencha mensagem, data e hora.','error'); return; }

  const fd = new FormData();
  fd.append('<?= esc(csrf_token()) ?>', '<?= esc(csrf_hash()) ?>');
  fd.append('mensagem', msg);
  fd.append('data', data);
  fd.append('hora', hora);
  fd.append('status', st);

  const res = await fetch('<?= base_url("agendamentos/update") ?>/'+id, { method:'POST', body: fd });
  if (res.ok) {
    toast('Agendamento atualizado!','success');
    fecharModal();
    await carregar();
  } else {
    const j = await res.json().catch(()=>({msg:'Erro ao salvar'}));
    toast(j.msg || 'Erro ao salvar','error');
  }
});

/* ================== Excluir ================== */
function abrirConfirm(id){ state.pendingDeleteId = id; const m=document.getElementById('confirmDelete'); m.classList.remove('hidden'); m.classList.add('flex'); }
function fecharConfirm(){ state.pendingDeleteId=null; const m=document.getElementById('confirmDelete'); m.classList.add('hidden'); m.classList.remove('flex'); }

async function excluir(id){
  // usa popup elegante; mantém fallback se necessário
  abrirConfirm(id);
}

document.getElementById('btnConfirmDelete').addEventListener('click', async ()=>{
  const id = state.pendingDeleteId; if(!id) return;
  const fd = new FormData();
  fd.append('<?= esc(csrf_token()) ?>', '<?= esc(csrf_hash()) ?>');
  const res = await fetch('<?= base_url("agendamentos/delete") ?>/'+id, { method:'POST', body: fd });
  if (res.ok){
    toast('Agendamento excluído.','success');
    fecharConfirm();
    await carregar();
  } else {
    const j = await res.json().catch(()=>({msg:'Erro ao excluir'}));
    toast(j.msg || 'Erro ao excluir','error');
  }
});

/* ================== Export ================== */
document.getElementById('btnExport').addEventListener('click', ()=>{
  if(!state.items.length){ toast('Nada para exportar'); return; }
  const rows = [ ['ID','Cliente','Telefone','Mensagem','Enviar em','Status'] ];
  state.items.forEach(it=> rows.push([
    it.id, (it.paciente_nome||'Paciente').replaceAll(',', ' '), phoneFmt(it.numero), (it.mensagem||'').replaceAll('\n',' ').replaceAll(',', ' '), dtBR(it.enviar_em), it.status
  ]));
  const csv = rows.map(r=> r.map(v=> `"${String(v).replaceAll('"','\"')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type:'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = 'agendamentos.csv'; a.click();
  URL.revokeObjectURL(url);
});

/* ================== Novo (atalho) ================== */
document.getElementById('btnNovo').addEventListener('click', ()=>{
  abrirEdicao({ id: 'novo', mensagem: '', enviar_em: '', status: 'pendente' });
});

/* boot */
syncChipsFromSelect();
carregar();
</script>
</body>
</html>