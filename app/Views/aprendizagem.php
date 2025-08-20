<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <title>Aprendizagem | Base de Conhecimento da IA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>tailwind.config={theme:{extend:{colors:{brand:{DEFAULT:'#111827'}},borderRadius:{'2xl':'1rem'}}}}</script>
  <style>
    .fade-enter{opacity:0;transform:translateY(6px)}
    .fade-enter-active{opacity:1;transform:translateY(0);transition:all .18s ease}
    .modal-show{display:flex}
    .ellipsis{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  </style>
</head>
<body class="bg-slate-50 text-slate-800 h-screen w-screen">
  <div class="h-full w-full flex">
    <?= view('sidebar') ?>

    <!-- Painel principal -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Cabeçalho -->
      <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <nav class="text-xs text-slate-500 mb-1">
            <ol class="flex items-center gap-1">
              <li>CRM</li><li class="opacity-50">/</li><li>Assistente</li><li class="opacity-50">/</li><li class="font-medium text-slate-700">Aprendizagem</li>
            </ol>
          </nav>
          <h1 class="text-xl sm:text-2xl font-semibold tracking-tight">Aprendizagem (Base de Conhecimento)</h1>
        </div>
        <div class="flex items-center gap-2 w-full sm:w-auto">
          <div class="relative sm:w-72 w-full">
            <input id="busca" type="text" placeholder="/ Buscar título, conteúdo ou tags..."
                  class="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand"/>
            <span class="pointer-events-none absolute left-3 top-2.5 text-slate-400">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.9 14.32a8 8 011.414-1.414l4.387 4.386a1 1 0 01-1.414 1.415l-4.387-4.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd"/></svg>
            </span>
          </div>
          <a href="/aprendizagem/base" target="_blank" class="px-3 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50">GET base</a>
          <button onclick="openModal()" class="px-4 py-2 rounded-2xl bg-slate-900 text-white hover:shadow">Criar novo</button>
        </div>
      </header>

      <!-- Conteúdo -->
      <main class="flex-1 overflow-y-auto px-4 sm:px-6 py-6">
        <section class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200">
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50 text-left">
                <tr class="text-slate-600">
                  <th class="px-6 py-3">Título</th>
                  <th class="px-6 py-3">Tags</th>
                  <th class="px-6 py-3">Ativo</th>
                  <th class="px-6 py-3">Criado</th>
                  <th class="px-6 py-3 text-right">Ações</th>
                </tr>
              </thead>
              <tbody id="tbody" class="divide-y divide-slate-100">
                <?php foreach (($itens ?? []) as $it): ?>
                  <tr class="hover:bg-slate-50">
                    <td class="px-6 py-3">
                      <div class="font-medium text-slate-800 ellipsis" title="<?= esc($it['titulo']) ?>"><?= esc($it['titulo']) ?></div>
                      <div class="text-xs text-slate-500 max-w-xl ellipsis" title="<?= esc(mb_strimwidth($it['conteudo'],0,180,'…','UTF-8')) ?>">
                        <?= esc(mb_strimwidth($it['conteudo'],0,180,'…','UTF-8')) ?>
                      </div>
                    </td>
                    <td class="px-6 py-3 text-slate-700 ellipsis" title="<?= esc($it['tags']) ?>"><?= esc($it['tags']) ?></td>
                    <td class="px-6 py-3"><?= (int)($it['ativo']??0) ? '<span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">Sim</span>' : '<span class="text-slate-600 bg-slate-100 px-2 py-0.5 rounded">Não</span>' ?></td>
                    <td class="px-6 py-3 text-slate-600"><?= esc($it['criado_em'] ?? '') ?></td>
                    <td class="px-6 py-3 text-right">
                      <button class="px-2 py-1 rounded bg-amber-100 text-amber-800 hover:bg-amber-200" onclick="editar(<?= (int)$it['id'] ?>)">Editar</button>
                      <button class="ml-1 px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200" onclick="confirmExcluir(<?= (int)$it['id'] ?>)">Excluir</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($itens)): ?>
                  <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Nenhum conhecimento cadastrado ainda.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>
      </main>
    </div>
  </div>

  <!-- Modal Criar/Editar -->
  <div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="absolute inset-0 bg-slate-900/50" onclick="closeModal()"></div>
    <div class="relative bg-white w-full max-w-2xl rounded-2xl shadow-xl p-6">
      <h2 class="text-lg font-semibold mb-4" id="modalTitle">Novo conhecimento</h2>
      <form id="formModal" onsubmit="return false;" class="space-y-4">
        <input type="hidden" id="id" name="id" />
        <div>
          <label for="titulo" class="block text-sm mb-1">Título</label>
          <input id="titulo" name="titulo" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand" required />
        </div>
        <div>
          <label for="conteudo" class="block text-sm mb-1">Conteúdo</label>
          <textarea id="conteudo" name="conteudo" rows="10" class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-brand" placeholder="Cole aqui o texto que a IA deve considerar"></textarea>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label for="tags" class="block text-sm mb-1">Tags (separe por vírgula)</label>
            <input id="tags" name="tags" type="text" class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-brand" placeholder="ex.: procedimentos, agenda"/>
          </div>
          <label class="inline-flex items-center gap-2 mt-6">
            <input type="checkbox" id="ativo" name="ativo" class="rounded" checked />
            <span class="text-sm">Ativo</span>
          </label>
        </div>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button type="button" class="px-5 py-2 rounded-xl bg-slate-100" onclick="closeModal()">Cancelar</button>
          <button type="button" id="btnSalvar" class="px-5 py-2 rounded-xl bg-slate-900 text-white">Salvar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Confirm Delete -->
  <div id="confirmDelete" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="absolute inset-0 bg-slate-900/50" onclick="fecharConfirm()"></div>
    <div class="relative bg-white w-full max-w-md rounded-2xl shadow-xl p-6">
      <h3 class="text-base font-semibold">Excluir este conhecimento?</h3>
      <p class="text-sm text-slate-500 mt-1">Esta ação não pode ser desfeita.</p>
      <div class="mt-4 flex items-center justify-end gap-2">
        <button class="px-3 py-2 rounded-lg bg-slate-100 text-slate-700" onclick="fecharConfirm()">Cancelar</button>
        <button id="btnConfirmDelete" class="px-3 py-2 rounded-lg bg-red-600 text-white">Excluir</button>
      </div>
    </div>
  </div>

  <!-- Toasts -->
  <div id="toasts" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

  <script>
    function toast(msg, type='default'){
      const wrap = document.getElementById('toasts');
      const el = document.createElement('div');
      el.className = `fade-enter px-4 py-2 rounded-xl shadow text-sm text-white ${type==='error'?'bg-red-600':type==='success'?'bg-emerald-600':'bg-slate-900'}`;
      el.textContent = msg; wrap.appendChild(el);
      requestAnimationFrame(()=> el.classList.add('fade-enter-active'));
      setTimeout(()=> el.remove(), 2400);
    }

    // Busca com debounce + atalho '/'
    let buscaTimer=null; const buscaEl=document.getElementById('busca');
    buscaEl.addEventListener('input', ()=>{ clearTimeout(buscaTimer); buscaTimer=setTimeout(()=>filtrar(buscaEl.value), 180); });
    addEventListener('keydown', (e)=>{ if(e.key==='/' && document.activeElement!==buscaEl){ e.preventDefault(); buscaEl.focus(); } });
    function filtrar(q){
      q=(q||'').toLowerCase();
      document.querySelectorAll('#tbody tr').forEach(tr=>{
        const t=tr.innerText.toLowerCase(); tr.style.display = t.includes(q)?'':'none';
      });
    }

    // Modal helpers
    function openModal(){
      document.getElementById('formModal').reset();
      document.getElementById('id').value = '';
      document.getElementById('modalTitle').innerText='Novo conhecimento';
      const m=document.getElementById('modal'); m.classList.remove('hidden'); m.classList.add('modal-show');
    }
    function closeModal(){
      const m=document.getElementById('modal'); m.classList.add('hidden'); m.classList.remove('modal-show');
    }

    // Editar
    async function editar(id){
      try{
        const r = await fetch(`/aprendizagem/obter/${id}`);
        const j = await r.json();
        if(!j?.ok) throw new Error('Falha');
        const d = j.data;
        document.getElementById('id').value = d.id;
        document.getElementById('titulo').value = d.titulo || '';
        document.getElementById('conteudo').value = d.conteudo || '';
        document.getElementById('tags').value = d.tags || '';
        document.getElementById('ativo').checked = Number(d.ativo) === 1;
        document.getElementById('modalTitle').innerText='Editar conhecimento';
        const m=document.getElementById('modal'); m.classList.remove('hidden'); m.classList.add('modal-show');
      }catch(e){ toast('Não foi possível carregar o item','error'); }
    }

    // Salvar
    document.getElementById('btnSalvar').addEventListener('click', async ()=>{
      const form = new FormData(document.getElementById('formModal'));
      try{
        const r = await fetch('/aprendizagem/salvar', { method:'POST', body: form });
        const j = await r.json();
        if(!j?.ok) throw new Error('Falha');
        toast('Salvo com sucesso!','success');
        setTimeout(()=> location.reload(), 500);
      }catch(e){ toast('Erro ao salvar','error'); }
    });

    // Excluir
    let idExcluir = null;
    function confirmExcluir(id){
      idExcluir = id;
      const m=document.getElementById('confirmDelete'); m.classList.remove('hidden'); m.classList.add('modal-show');
    }
    function fecharConfirm(){
      idExcluir=null;
      const m=document.getElementById('confirmDelete'); m.classList.add('hidden'); m.classList.remove('modal-show');
    }
    document.getElementById('btnConfirmDelete').addEventListener('click', async ()=>{
      if(!idExcluir) return;
      const fd = new FormData(); fd.append('id', idExcluir);
      try{
        const r = await fetch('/aprendizagem/excluir', { method: 'POST', body: fd });
        const j = await r.json();
        if(!j?.ok) throw new Error('Falha');
        toast('Excluído!','success');
        setTimeout(()=> location.reload(), 400);
      }catch(e){ toast('Erro ao excluir','error'); }
      finally{ fecharConfirm(); }
    });
  </script>
</body>
</html>
