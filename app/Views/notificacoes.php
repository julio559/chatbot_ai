<?php $etapas = $etapas ?? []; ?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Números para Notificação | CRM Assistente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800">
<div class="flex min-h-screen">
  <?= view('sidebar') ?>

  <main class="flex-1 p-6">
    <header class="mb-6 flex items-center justify-between gap-4 flex-wrap">
      <h1 class="text-2xl font-bold">Números para Notificação</h1>
      <div class="flex items-center gap-2">
        <input id="q" type="text" placeholder="Buscar por número ou descrição..." class="w-72 max-w-full rounded-xl border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500">
        <button id="btnBuscar" class="px-4 py-2 rounded-xl bg-blue-600 text-white">Buscar</button>
        <button id="btnNovo" class="px-4 py-2 rounded-xl bg-green-600 text-white">+ Novo</button>
      </div>
    </header>

    <section class="bg-white rounded-2xl shadow border border-gray-100">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-left bg-gray-50">
          <tr>
            <th class="px-4 py-3">ID</th>
            <th class="px-4 py-3">Número</th>
            <th class="px-4 py-3">Descrição</th>
            <th class="px-4 py-3">Ativo</th>
            <th class="px-4 py-3 text-right">Ações</th>
          </tr>
          </thead>
          <tbody id="tbody" class="divide-y"></tbody>
        </table>
      </div>
      <div id="empty" class="hidden p-8 text-center text-gray-500">Nenhum número cadastrado.</div>
    </section>

    <!-- (Opcional) Gestão de regras por etapa -->
    <section class="bg-white rounded-2xl shadow border border-gray-100 mt-8">
      <div class="p-5 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Regras por etapa (opcional)</h2>
        <button id="btnNovaRegra" class="px-4 py-2 rounded-xl bg-blue-600 text-white">Salvar regra</button>
      </div>
      <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
      <div>
  <label class="block text-sm mb-1">Etapa</label>
  <select id="rEtapa" class="w-full border rounded-lg px-3 py-2">
    <option value="">Selecione a etapa</option>
    <?php foreach ($etapas as $e): ?>
      <option value="<?= esc($e) ?>"><?= esc(ucfirst(str_replace('_',' ', $e))) ?></option>
    <?php endforeach; ?>
  </select>
</div>

        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Mensagem template (opcional)</label>
          <input id="rMsg" type="text" placeholder="ex.: Novo lead em {etapa}: +{numero}" class="w-full border rounded-lg px-3 py-2">
        </div>
        <label class="inline-flex items-center gap-2">
          <input id="rAtivo" type="checkbox" class="rounded" checked>
          <span>Ativo</span>
        </label>
      </div>
      <div class="p-5">
        <p class="text-xs text-gray-500">Dica: você pode usar <code>{numero}</code>, <code>{nome}</code> e <code>{etapa}</code> no template.</p>
      </div>
    </section>
  </main>
</div>

<!-- Modal formulário -->
<div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center">
  <div class="absolute inset-0 bg-black/40" onclick="fecharModal()"></div>
  <div class="relative bg-white w-full max-w-lg rounded-2xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold" id="mTitle">Novo número</h3>
      <button class="text-gray-500" onclick="fecharModal()">✕</button>
    </div>
    <form id="form" onsubmit="return false;" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" id="mId">
      <div>
        <label class="block text-sm mb-1">Número (WhatsApp)</label>
        <input id="mNumero" type="text" placeholder="Ex.: 5527999123456" class="w-full border rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-sm mb-1">Descrição</label>
        <input id="mDescricao" type="text" placeholder="Ex.: Financeiro" class="w-full border rounded-lg px-3 py-2">
      </div>
      <label class="inline-flex items-center gap-2">
        <input id="mAtivo" type="checkbox" class="rounded" checked>
        <span>Ativo</span>
      </label>
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
  const t=document.createElement('div');
  t.className='fixed bottom-4 right-4 bg-gray-900 text-white px-4 py-2 rounded-lg shadow';
  t.textContent=msg; document.body.appendChild(t);
  setTimeout(()=>t.remove(),2200);
}
function abrirModal(){ const m=document.getElementById('modal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function fecharModal(){ const m=document.getElementById('modal'); m.classList.add('hidden'); m.classList.remove('flex'); resetForm(); }
function resetForm(){ document.getElementById('mId').value=''; document.getElementById('mNumero').value=''; document.getElementById('mDescricao').value=''; document.getElementById('mAtivo').checked=true; }

document.getElementById('btnNovo').addEventListener('click', ()=>{ document.getElementById('mTitle').textContent='Novo número'; abrirModal(); });
document.getElementById('btnBuscar').addEventListener('click', carregar);

document.getElementById('btnSalvar').addEventListener('click', async ()=>{
  const fd = new FormData();
  fd.append(csrfName, csrfHash);
  fd.append('id', document.getElementById('mId').value);
  fd.append('numero', document.getElementById('mNumero').value);
  fd.append('descricao', document.getElementById('mDescricao').value);
  fd.append('ativo', document.getElementById('mAtivo').checked ? '1' : '0');

  const res = await fetch('<?= base_url("notificacoes/save") ?>',{ method:'POST', body:fd });
  if(res.ok){ toast('Salvo!'); fecharModal(); carregar(); } else { toast('Erro ao salvar'); }
});

async function excluir(id){
  if(!confirm('Excluir este número?')) return;
  const fd=new FormData(); fd.append(csrfName, csrfHash);
  const res=await fetch('<?= base_url("notificacoes/delete") ?>/'+id,{method:'POST', body:fd});
  if(res.ok){ toast('Excluído.'); carregar(); } else { toast('Erro ao excluir'); }
}

function editar(item){
  document.getElementById('mTitle').textContent='Editar número';
  document.getElementById('mId').value=item.id;
  document.getElementById('mNumero').value=item.numero;
  document.getElementById('mDescricao').value=item.descricao||'';
  document.getElementById('mAtivo').checked=!!Number(item.ativo);
  abrirModal();
}

async function carregar(){
  const url=new URL('<?= base_url("notificacoes/list") ?>', window.location.origin);
  const q=document.getElementById('q').value.trim(); if(q) url.searchParams.set('q', q);
  const res=await fetch(url.toString()); const data=await res.json(); render(data.items||[]);
}
function render(items){
  const tb=document.getElementById('tbody'); const empty=document.getElementById('empty');
  tb.innerHTML=''; if(!items.length){ empty.classList.remove('hidden'); return; } empty.classList.add('hidden');
  items.forEach(it=>{
    tb.insertAdjacentHTML('beforeend', `
      <tr class="hover:bg-gray-50">
        <td class="px-4 py-3">${it.id}</td>
        <td class="px-4 py-3">+${it.numero}</td>
        <td class="px-4 py-3">${(it.descricao||'')}</td>
        <td class="px-4 py-3">${it.ativo ? 'Sim' : 'Não'}</td>
        <td class="px-4 py-3 text-right">
          <button class="px-3 py-1 rounded-lg bg-amber-100 text-amber-800 mr-2" onclick='editar(${JSON.stringify(it)})'>Editar</button>
          <button class="px-3 py-1 rounded-lg bg-red-100 text-red-700" onclick="excluir(${it.id})">Excluir</button>
        </td>
      </tr>
    `);
  });
}
carregar();

/* Regras (opcional) */
document.getElementById('btnNovaRegra').addEventListener('click', async ()=>{
  const fd=new FormData();
  fd.append(csrfName, csrfHash);
  fd.append('etapa',  document.getElementById('rEtapa').value.trim());
  fd.append('mensagem_template', document.getElementById('rMsg').value.trim());
  fd.append('ativo', document.getElementById('rAtivo').checked ? '1' : '0');

  const res = await fetch('<?= base_url("notificacoes/regra/save") ?>', { method:'POST', body:fd });
  if(res.ok){ toast('Regra salva!'); } else { toast('Erro ao salvar regra'); }
});
</script>
</body>
</html>
