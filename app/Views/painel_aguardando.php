<?php
  $pacientes = $pacientes ?? [];
  $etapas    = $etapas    ?? [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pacientes | CRM </title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          borderRadius: { 'xl':'0.75rem', '2xl':'1rem' },
          boxShadow:    { soft:'0 4px 16px rgba(0,0,0,.06)' }
        }
      }
    }
  </script>
  <style>
    .fade-enter{opacity:0;transform:translateY(6px)}
    .fade-enter-active{opacity:1;transform:translateY(0);transition:all .18s ease}
    .ellipsis{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .toggle{appearance:none;width:40px;height:22px;border-radius:999px;background:#e5e7eb;position:relative;cursor:pointer;transition:.15s}
    .toggle:checked{background:#16a34a}
    .toggle:after{content:"";width:18px;height:18px;position:absolute;top:2px;left:2px;border-radius:999px;background:#fff;transition:.15s}
    .toggle:checked:after{left:20px}
  </style>
</head>
<body class="bg-gray-100 text-gray-800">
  <div class="flex h-screen">
    <!-- Sidebar -->
    <?= view('sidebar') ?>

    <!-- Main Content -->
    <main class="flex-1 p-6 overflow-y-auto">
      <header class="mb-4 flex items-center justify-between gap-3 flex-wrap">
        <div class="min-w-0">
          <h2 class="text-2xl font-semibold text-gray-800">Lista de Pacientes</h2>
          <p class="text-sm text-gray-500">Gerencie dados, etapas e telefones. Clique em um paciente para editar.</p>
        </div>
        <div class="flex items-center gap-2 w-full md:w-auto">
          <div class="relative md:w-72 w-full">
            <input id="search" type="text" placeholder="/ Buscar por nome, telefone ou etapa" class="w-full rounded-xl border border-gray-300 pl-9 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"/>
            <span class="pointer-events-none absolute left-3 top-2.5 text-gray-400">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.9 14.32a8 8 0 111.414-1.414l4.387 4.386a1 1 0 01-1.414 1.415l-4.387-4.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd"/></svg>
            </span>
          </div>
          <select id="fEtapa" class="rounded-xl border border-gray-300 px-3 py-2">
            <option value="">Todas etapas</option>
            <?php foreach ($etapas as $key => $label): ?>
              <option value="<?= esc($key) ?>"><?= esc($label) ?></option>
            <?php endforeach; ?>
          </select>
          <button id="btnExport" class="px-4 py-2 rounded-xl bg-gray-100">Exportar CSV</button>
        </div>
      </header>

      <section class="bg-white rounded-xl shadow p-0 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm text-left text-gray-700">
            <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
              <tr>
                <th class="px-4 py-3 w-10"><input id="chkAll" type="checkbox" class="rounded"/></th>
                <th class="px-4 py-3 font-semibold">Nome</th>
                <th class="px-4 py-3 font-semibold">Telefone</th>
                <th class="px-4 py-3 font-semibold">Etapa</th>
                <th class="px-4 py-3 font-semibold">Último Contato</th>
                <th class="px-4 py-3 font-semibold text-right">Ações</th>
              </tr>
            </thead>
            <tbody id="tbody">
              <?php foreach ($pacientes as $p): ?>
                <tr class="hover:bg-gray-50 border-b group">
                  <td class="px-4 py-2"><input type="checkbox" class="rowChk rounded" data-id="<?= esc($p['id']) ?>" data-tel="<?= esc($p['telefone']) ?>" data-nome="<?= esc($p['nome']) ?>"></td>
                  <td class="px-4 py-2 ellipsis" title="<?= esc($p['nome']) ?>"><?= esc($p['nome']) ?></td>
                  <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                      <span class="font-medium"><?= esc($p['telefone']) ?></span>
                      <button type="button" class="text-xs px-2 py-0.5 rounded bg-gray-100 hover:bg-gray-200" onclick="copy('<?= esc($p['telefone']) ?>')">Copiar</button>
                      <a class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-700 hover:bg-green-200" target="_blank" rel="noopener" href="https://wa.me/<?= esc(preg_replace('/\D/','',$p['telefone'])) ?>">WhatsApp</a>
                    </div>
                  </td>
                  <td class="px-4 py-2 capitalize"><span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 text-xs"><?= esc($p['etapa'] ?? 'inicio') ?></span></td>
                  <td class="px-4 py-2"><?= esc($p['ultimo_contato']) ?></td>
                  <td class="px-4 py-2 text-right space-x-2">
                    <button onclick="openModal('modal-<?= $p['id'] ?>')" class="text-blue-600 hover:underline">Editar</button>
                    <a href="/paciente/excluir/<?= $p['id'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Excluir paciente \"<?= esc($p['nome']) ?>\"?')">Excluir</a>
                  </td>
                </tr>

                <!-- Modal Lateral de Edição -->
                <div id="modal-<?= $p['id'] ?>" class="fixed inset-0 z-50 hidden bg-black/40 backdrop-blur-[1px] flex justify-end">
                  <div class="bg-white w-full max-w-md h-full flex flex-col shadow-xl">
                    <!-- Cabeçalho -->
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                      <h3 class="text-lg font-semibold text-gray-800">Editar Paciente</h3>
                      <button onclick="closeModal('modal-<?= $p['id'] ?>')" class="text-gray-500 hover:text-red-600 text-xl font-bold">&times;</button>
                    </div>

                    <!-- Formulário -->
                    <form action="/paciente/atualizar/<?= $p['id'] ?>" method="post" class="flex-1 flex flex-col justify-between" onsubmit="return validarForm(this)">
                      <div class="px-6 py-4 space-y-4 overflow-y-auto">
                        <div>
                          <label class="block text-sm font-medium text-gray-700">Nome</label>
                          <input type="text" name="nome" value="<?= esc($p['nome']) ?>" class="w-full p-2 border rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700">Telefone</label>
                          <input type="text" name="telefone" value="<?= esc($p['telefone']) ?>" class="w-full p-2 border rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                          <p class="text-[11px] text-gray-500 mt-1">Formato recomendado: <b>55DDDNUMERO</b> (somente dígitos).</p>
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700">Etapa</label>
                          <select name="etapa" class="w-full p-2 border rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($etapas as $key => $label): ?>
                              <option value="<?= esc($key) ?>" <?= ($p['etapa'] ?? '') === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <!-- Rodapé com Botões -->
                      <div class="px-6 py-4 border-t border-gray-200 bg-white flex justify-end gap-2">
                        <button type="button" onclick="closeModal('modal-<?= $p['id'] ?>')" class="px-4 py-2 bg-gray-100 rounded-xl hover:bg-gray-200">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700">Salvar</button>
                      </div>
                    </form>
                  </div>
                </div>

              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Ações em massa -->
        <div class="p-3 border-t flex items-center justify-between gap-3">
          <div class="text-xs text-gray-500"><span id="selCount">0</span> selecionado(s)</div>
          <div class="flex items-center gap-2">
            <button id="btnCopyPhones" class="px-3 py-2 rounded-lg bg-gray-100">Copiar Telefones</button>
            <button id="btnExportSel" class="px-3 py-2 rounded-lg bg-gray-100">Exportar Seleção</button>
          </div>
        </div>
      </section>

      <!-- Empty state (client-side)
      <div id="empty" class="hidden p-10 text-center text-gray-500">Nenhum paciente encontrado.</div> -->
    </main>
  </div>

  <script>
    // Atalho para busca
    document.addEventListener('keydown', (e)=>{ const s=document.getElementById('search'); if(e.key==='/' && document.activeElement!==s){ e.preventDefault(); s.focus(); } });

    // Filtro client-side + por etapa
    const search = document.getElementById('search');
    const fEtapa = document.getElementById('fEtapa');
    const tbody  = document.getElementById('tbody');
    let timer=null;
    function applyFilters(){
      const q=(search.value||'').toLowerCase();
      const etapa=fEtapa.value;
      const rows=[...tbody.querySelectorAll('tr')];
      let count=0;
      rows.forEach(tr=>{
        const tds=[...tr.querySelectorAll('td')];
        const nome = (tds[1]?.innerText||'').toLowerCase();
        const fone = (tds[2]?.innerText||'').toLowerCase();
        const et   = (tds[3]?.innerText||'').toLowerCase();
        const ok = (!q || nome.includes(q) || fone.includes(q) || et.includes(q)) && (!etapa || et.includes(etapa.toLowerCase()));
        tr.style.display = ok ? '' : 'none';
        if(ok) count++;
      });
      document.getElementById('chkAll').checked=false; updateSelCount();
      // document.getElementById('empty').classList.toggle('hidden', count>0);
    }
    search.addEventListener('input', ()=>{ clearTimeout(timer); timer=setTimeout(applyFilters, 160); });
    fEtapa.addEventListener('change', applyFilters);

    // Seleção e ações em massa
    const chkAll = document.getElementById('chkAll');
    function bindRowChecks(){ const rows=[...document.querySelectorAll('.rowChk')]; rows.forEach(r=> r.addEventListener('change', updateSelCount)); }
    function updateSelCount(){ const rows=[...document.querySelectorAll('.rowChk:checked')]; document.getElementById('selCount').textContent=rows.length; }
    chkAll.addEventListener('change', ()=>{ document.querySelectorAll('.rowChk').forEach(r=> r.checked=chkAll.checked); updateSelCount(); });
    bindRowChecks();

    // Copiar telefones
    document.getElementById('btnCopyPhones').addEventListener('click', ()=>{
      const nums=[...document.querySelectorAll('.rowChk:checked')].map(r=> r.dataset.tel).filter(Boolean);
      if(!nums.length) return alert('Selecione ao menos um paciente.');
      navigator.clipboard.writeText(nums.join('\n')).then(()=> alert('Telefones copiados.'));
    });

    // Export CSV (todos)
    document.getElementById('btnExport').addEventListener('click', ()=> exportCSV(false));
    // Export CSV (seleção)
    document.getElementById('btnExportSel').addEventListener('click', ()=> exportCSV(true));

    function exportCSV(onlySelected){
      const rows=[["nome","telefone","etapa","ultimo_contato"]];
      const trList=[...tbody.querySelectorAll('tr')].filter(tr=> tr.style.display!=="none");
      trList.forEach(tr=>{
        const checked = tr.querySelector('.rowChk')?.checked;
        if(onlySelected && !checked) return;
        const tds=[...tr.querySelectorAll('td')];
        const nome=tds[1]?.innerText.trim()||'';
        const tel =(tds[2]?.innerText.trim()||'').replace('Copiar','').replace('WhatsApp','').trim();
        const etapa=tds[3]?.innerText.trim()||'';
        const last =tds[4]?.innerText.trim()||'';
        rows.push([nome,tel,etapa,last]);
      });
      const csv=rows.map(r=> r.map(v=> '"'+String(v).replaceAll('"','""')+'"').join(',')).join('\n');
      const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'}); const url=URL.createObjectURL(blob);
      const a=document.createElement('a'); a.href=url; a.download= onlySelected? 'pacientes_selecao.csv' : 'pacientes.csv'; a.click(); URL.revokeObjectURL(url);
    }

    // Modal helpers (mantêm seus IDs e funções)
    function openModal(id){ document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id){ document.getElementById(id).classList.add('hidden'); }
    window.openModal = openModal; window.closeModal = closeModal;

    // Validar telefone no submit
    function validarForm(form){ const tel=form.telefone?.value||''; if(!/^\d{12,15}$/.test(tel)){ alert('Telefone inválido. Use apenas dígitos (12–15).'); return false; } return true; }
    window.validarForm = validarForm;

    // Util
    function copy(text){ navigator.clipboard.writeText(text).then(()=>{ const t=document.createElement('div'); t.className='fade-enter fixed bottom-4 right-4 bg-gray-900 text-white px-4 py-2 rounded-xl shadow'; t.textContent='Copiado!'; document.body.appendChild(t); requestAnimationFrame(()=> t.classList.add('fade-enter-active')); setTimeout(()=>t.remove(),1600); }); }
    window.copy = copy;
  </script>
</body>
</html>