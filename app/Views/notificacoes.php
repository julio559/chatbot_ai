<?php $etapas = $etapas ?? []; ?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Números para Notificação | CRM Assistente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          borderRadius: { 'xl':'0.75rem', '2xl':'1rem' },
          boxShadow: { soft:'0 4px 16px rgba(0,0,0,.06)' }
        }
      }
    }
  </script>
  <style>
    .ellipsis{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .fade-enter{opacity:0;transform:translateY(6px)}
    .fade-enter-active{opacity:1;transform:translateY(0);transition:all .18s ease}
    .toggle{appearance:none;width:40px;height:22px;border-radius:999px;background:#e5e7eb;position:relative;cursor:pointer;transition:.15s}
    .toggle:checked{background:#16a34a}
    .toggle:after{content:"";width:18px;height:18px;position:absolute;top:2px;left:2px;border-radius:999px;background:#fff;transition:.15s}
    .toggle:checked:after{left:20px}
  </style>
</head>
<body class="bg-gray-50 text-gray-800">
<div class="flex min-h-screen">
  <?= view('sidebar') ?>

  <main class="flex-1 p-6">
    <header class="mb-6 flex items-center justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold">Números para Notificação</h1>
        <p class="text-sm text-gray-500">Gerencie os destinos que recebem alertas automáticos.</p>
      </div>
      <div class="flex items-center gap-2">
        <div class="relative">
          <input id="q" type="text" placeholder="/ Buscar por número ou descrição..." class="w-72 max-w-full rounded-xl border border-gray-300 pl-9 px-3 py-2 focus:ring-2 focus:ring-blue-500">
          <span class="pointer-events-none absolute left-3 top-2.5 text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.9 14.32a8 8 0 111.414-1.414l4.387 4.386a1 1 0 01-1.414 1.415l-4.387-4.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd"/></svg>
          </span>
        </div>
        <button id="btnBuscar" class="px-4 py-2 rounded-xl bg-blue-600 text-white">Buscar</button>
        <button id="btnNovo" class="px-4 py-2 rounded-xl bg-green-600 text-white">+ Novo</button>
        <button id="btnExport" class="px-4 py-2 rounded-xl bg-gray-100">Exportar CSV</button>
        <button id="btnImport" class="px-4 py-2 rounded-xl bg-gray-100">Importar</button>
      </div>
    </header>

    <section class="bg-white rounded-2xl shadow border border-gray-100">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-left bg-gray-50">
          <tr>
            <th class="px-4 py-3 w-10"><input id="chkAll" type="checkbox" class="rounded"/></th>
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

      <!-- Bulk actions -->
      <div class="p-3 border-t flex items-center justify-between gap-3">
        <div class="text-xs text-gray-500"><span id="selCount">0</span> selecionado(s)</div>
        <div class="flex items-center gap-2">
          <button id="btnBulkDelete" class="px-3 py-2 rounded-lg bg-red-50 text-red-700">Excluir Selecionados</button>
          <button id="btnBulkCopy" class="px-3 py-2 rounded-lg bg-gray-100">Copiar Números</button>
        </div>
      </div>
    </section>

    <!-- Regras por etapa -->
    <section class="bg-white rounded-2xl shadow border border-gray-100 mt-8">
      <div class="p-5 flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold">Regras por etapa (opcional)</h2>
          <p class="text-xs text-gray-500">Defina uma mensagem quando houver eventos em uma etapa específica.</p>
        </div>
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
        <div class="flex items-center gap-2">
          <input id="rAtivo" type="checkbox" class="toggle" checked>
          <span class="text-sm">Ativo</span>
        </div>
      </div>
      <div class="px-5 pb-5 text-xs text-gray-500">
        Dica: você pode usar <code>{numero}</code>, <code>{nome}</code> e <code>{etapa}</code> no template.
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
        <p id="mNumeroHint" class="text-[11px] text-gray-500 mt-1">Formato internacional: <b>55DDDNUMERO</b> (somente dígitos).</p>
      </div>
      <div>
        <label class="block text-sm mb-1">Descrição</label>
        <input id="mDescricao" type="text" placeholder="Ex.: Financeiro" class="w-full border rounded-lg px-3 py-2">
      </div>
      <label class="inline-flex items-center gap-2">
        <input id="mAtivo" type="checkbox" class="toggle" checked>
        <span>Ativo</span>
      </label>
      <div class="flex items-center justify-end gap-2 pt-2">
        <button type="button" class="px-4 py-2 rounded-lg bg-gray-100" onclick="fecharModal()">Cancelar</button>
        <button id="btnSalvar" class="px-4 py-2 rounded-lg bg-blue-600 text-white">Salvar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Import CSV -->
<div id="modalImport" class="fixed inset-0 z-50 hidden items-center justify-center">
  <div class="absolute inset-0 bg-black/40" onclick="fecharImport()"></div>
  <div class="relative bg-white w-full max-w-lg rounded-2xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold">Importar CSV</h3>
      <button class="text-gray-500" onclick="fecharImport()">✕</button>
    </div>
    <div class="space-y-3 text-sm">
      <p>Faça upload de um CSV com as colunas <b>numero</b>, <b>descricao</b>, <b>ativo</b>.</p>
      <input id="csvFile" type="file" accept=".csv" class="w-full border rounded-lg px-3 py-2" />
      <div class="flex items-center justify-end gap-2 pt-2">
        <button type="button" class="px-4 py-2 rounded-lg bg-gray-100" onclick="fecharImport()">Cancelar</button>
        <button id="btnProcessCSV" class="px-4 py-2 rounded-lg bg-blue-600 text-white">Processar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Confirm -->
<div id="modalConfirm" class="fixed inset-0 z-50 hidden items-center justify-center">
  <div class="absolute inset-0 bg-black/40" onclick="fecharConfirm()"></div>
  <div class="relative bg-white w-full max-w-md rounded-2xl shadow p-6">
    <h3 class="text-lg font-semibold mb-2">Confirmar ação</h3>
    <p id="confirmMsg" class="text-sm text-gray-600 mb-4">Tem certeza?</p>
    <div class="flex justify-end gap-2">
      <button class="px-4 py-2 rounded-lg bg-gray-100" onclick="fecharConfirm()">Cancelar</button>
      <button id="btnDoConfirm" class="px-4 py-2 rounded-lg bg-red-600 text-white">Confirmar</button>
    </div>
  </div>
</div>

<!-- Toasts -->
<div id="toasts" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

<script>
const csrfName = '<?= esc(csrf_token()) ?>';
const csrfHash = '<?= esc(csrf_hash()) ?>';

function toast(msg,type='default'){
  const t=document.createElement('div');
  t.className=`fade-enter px-4 py-2 rounded-xl shadow text-sm text-white ${type==='error'?'bg-red-600':type==='success'?'bg-emerald-600':'bg-gray-900'}`;
  t.textContent=msg; document.getElementById('toasts').appendChild(t);
  requestAnimationFrame(()=> t.classList.add('fade-enter-active'));
  setTimeout(()=>t.remove(),2200);
}
function abrirModal(){ const m=document.getElementById('modal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function fecharModal(){ const m=document.getElementById('modal'); m.classList.add('hidden'); m.classList.remove('flex'); resetForm(); }
function abrirImport(){ const m=document.getElementById('modalImport'); m.classList.remove('hidden'); m.classList.add('flex'); }
function fecharImport(){ const m=document.getElementById('modalImport'); m.classList.add('hidden'); m.classList.remove('flex'); document.getElementById('csvFile').value=''; }
function abrirConfirm(msg, onOk){ document.getElementById('confirmMsg').textContent = msg||'Tem certeza?'; const m=document.getElementById('modalConfirm'); m.classList.remove('hidden'); m.classList.add('flex'); const btn=document.getElementById('btnDoConfirm'); const clone=btn.cloneNode(true); btn.parentNode.replaceChild(clone, btn); clone.addEventListener('click', ()=>{ onOk?.(); fecharConfirm(); }); }
function fecharConfirm(){ const m=document.getElementById('modalConfirm'); m.classList.add('hidden'); m.classList.remove('flex'); }
function resetForm(){ document.getElementById('mId').value=''; document.getElementById('mNumero').value=''; document.getElementById('mDescricao').value=''; document.getElementById('mAtivo').checked=true; }
function isValidNumber(v){ return /^\d{12,15}$/.test(String(v||'').trim()); }

// keyboard: focus search with /
document.addEventListener('keydown', (e)=>{ if(e.key==='/' && document.activeElement.id!=='q'){ e.preventDefault(); document.getElementById('q').focus(); } });

// Buscar / debounce
let timer=null; document.getElementById('q').addEventListener('input', ()=>{ clearTimeout(timer); timer=setTimeout(carregar, 200); });

document.getElementById('btnNovo').addEventListener('click', ()=>{ document.getElementById('mTitle').textContent='Novo número'; abrirModal(); });
document.getElementById('btnBuscar').addEventListener('click', carregar);
document.getElementById('btnExport').addEventListener('click', exportCSV);
document.getElementById('btnImport').addEventListener('click', abrirImport);

// salvar
document.getElementById('btnSalvar').addEventListener('click', async ()=>{
  const numero = document.getElementById('mNumero').value;
  if (!isValidNumber(numero)) { toast('Número inválido. Use apenas dígitos (12–15).','error'); return; }
  const fd = new FormData();
  fd.append(csrfName, csrfHash);
  fd.append('id', document.getElementById('mId').value);
  fd.append('numero', numero);
  fd.append('descricao', document.getElementById('mDescricao').value);
  fd.append('ativo', document.getElementById('mAtivo').checked ? '1' : '0');

  const res = await fetch('<?= base_url("notificacoes/save") ?>',{ method:'POST', body:fd });
  if(res.ok){ toast('Salvo!','success'); fecharModal(); carregar(); } else { toast('Erro ao salvar','error'); }
});

async function excluir(id){
  abrirConfirm('Excluir este número?', async ()=>{
    const fd=new FormData(); fd.append(csrfName, csrfHash);
    const res=await fetch('<?= base_url("notificacoes/delete") ?>/'+id,{method:'POST', body:fd});
    if(res.ok){ toast('Excluído.','success'); carregar(); } else { toast('Erro ao excluir','error'); }
  });
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
  tb.innerHTML=''; document.getElementById('selCount').textContent='0'; document.getElementById('chkAll').checked=false;
  if(!items.length){ empty.classList.remove('hidden'); return; } empty.classList.add('hidden');
  items.forEach(it=>{
    tb.insertAdjacentHTML('beforeend', `
      <tr class="hover:bg-gray-50">
        <td class="px-4 py-3"><input type="checkbox" class="rowChk rounded" data-id="${it.id}" data-numero="${it.numero}"></td>
        <td class="px-4 py-3">${it.id}</td>
        <td class="px-4 py-3"><span class="font-medium">+${it.numero}</span></td>
        <td class="px-4 py-3 ellipsis" title="${(it.descricao||'')}">${(it.descricao||'')}</td>
        <td class="px-4 py-3">${it.ativo ? '<span class="px-2 py-0.5 rounded bg-green-100 text-green-700 text-xs">Sim</span>' : '<span class="px-2 py-0.5 rounded bg-gray-200 text-gray-700 text-xs">Não</span>'}</td>
        <td class="px-4 py-3 text-right">
          <button class="px-3 py-1 rounded-lg bg-amber-100 text-amber-800 mr-2" onclick='editar(${JSON.stringify(it)})'>Editar</button>
          <button class="px-3 py-1 rounded-lg bg-red-100 text-red-700" onclick="excluir(${it.id})">Excluir</button>
        </td>
      </tr>
    `);
  });
  bindRowChecks();
}

// seleção/bulk
function bindRowChecks(){
  const all=document.getElementById('chkAll'); const rows=[...document.querySelectorAll('.rowChk')];
  all.addEventListener('change',()=>{ rows.forEach(r=> r.checked=all.checked); updateSelCount(); });
  rows.forEach(r=> r.addEventListener('change', updateSelCount));
}
function updateSelCount(){ const rows=[...document.querySelectorAll('.rowChk:checked')]; document.getElementById('selCount').textContent=rows.length; }

// bulk delete
 document.getElementById('btnBulkDelete').addEventListener('click', ()=>{
   const ids=[...document.querySelectorAll('.rowChk:checked')].map(r=> r.dataset.id);
   if(!ids.length) return toast('Selecione ao menos um.');
   abrirConfirm(`Excluir ${ids.length} registro(s)?`, async ()=>{
     for (const id of ids){
       const fd=new FormData(); fd.append(csrfName, csrfHash);
       await fetch('<?= base_url("notificacoes/delete") ?>/'+id,{method:'POST', body:fd});
     }
     toast('Exclusão concluída.','success'); carregar();
   });
 });

// copiar números
 document.getElementById('btnBulkCopy').addEventListener('click', ()=>{
   const nums=[...document.querySelectorAll('.rowChk:checked')].map(r=> r.dataset.numero);
   if(!nums.length) return toast('Selecione ao menos um.');
   navigator.clipboard.writeText(nums.join('\n')).then(()=> toast('Copiado para a área de transferência.','success'));
 });

// export
function exportCSV(){
  const rows=[["id","numero","descricao","ativo"]];
  document.querySelectorAll('#tbody tr').forEach(tr=>{
    const tds=[...tr.querySelectorAll('td')]; if(!tds.length) return;
    const id=tds[1].innerText.trim(); const num=tds[2].innerText.trim().replace('+',''); const desc=tds[3].innerText.trim(); const ativo=/Sim/.test(tds[4].innerText.trim())?1:0;
    rows.push([id,num,desc,ativo]);
  });
  const csv=rows.map(r=> r.map(v=> '"'+String(v).replaceAll('"','""')+'"').join(',')).join('\n');
  const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'}); const url=URL.createObjectURL(blob);
  const a=document.createElement('a'); a.href=url; a.download='notificacoes.csv'; a.click(); URL.revokeObjectURL(url);
}

document.getElementById('btnProcessCSV').addEventListener('click', async ()=>{
  const f=document.getElementById('csvFile').files?.[0]; if(!f){ toast('Selecione um arquivo CSV.'); return; }
  const text=await f.text(); const lines=text.split(/\r?\n/).filter(Boolean); if(!lines.length){ toast('CSV vazio.'); return; }
  const header=lines.shift().toLowerCase(); const cols=header.split(',').map(s=> s.replace(/\"/g,'').replace(/"/g,'').trim());
  const idx={ numero:cols.indexOf('numero'), descricao:cols.indexOf('descricao'), ativo:cols.indexOf('ativo') };
  if(idx.numero===-1){ toast('CSV precisa da coluna numero','error'); return; }
  let ok=0, fail=0;
  for(const line of lines){
    const parts=line.match(/\s*(?:\"([^\"]*)\"|([^,]*))\s*(?:,|$)/g)?.map(s=> s.replace(/^,|,$/g,'').replace(/^\"|\"$/g,'')) || [];
    const numero = (parts[idx.numero]||'').replace(/\D/g,'');
    const descricao = idx.descricao>-1 ? (parts[idx.descricao]||'') : '';
    const ativo = idx.ativo>-1 ? (/^(1|true|sim)$/i.test(parts[idx.ativo]||'')?'1':'0') : '1';
    if(!isValidNumber(numero)){ fail++; continue; }
    const fd=new FormData(); fd.append(csrfName, csrfHash); fd.append('numero', numero); fd.append('descricao', descricao); fd.append('ativo', ativo);
    const res=await fetch('<?= base_url("notificacoes/save") ?>',{method:'POST', body:fd}); if(res.ok) ok++; else fail++;
  }
  toast(`Importação: ${ok} ok, ${fail} erro(s).`, fail? 'default':'success'); fecharImport(); carregar();
});

/* Regras (opcional) */
document.getElementById('btnNovaRegra').addEventListener('click', async ()=>{
  const etapa  = document.getElementById('rEtapa').value.trim();
  if (!etapa){ toast('Selecione uma etapa.','error'); return; }
  const fd=new FormData();
  fd.append(csrfName, csrfHash);
  fd.append('etapa', etapa);
  fd.append('mensagem_template', document.getElementById('rMsg').value.trim());
  fd.append('ativo', document.getElementById('rAtivo').checked ? '1' : '0');

  const res = await fetch('<?= base_url("notificacoes/regra/save") ?>', { method:'POST', body:fd });
  if(res.ok){ toast('Regra salva!','success'); } else { toast('Erro ao salvar regra','error'); }
});

// boot
carregar();
</script>
</body>
</html>