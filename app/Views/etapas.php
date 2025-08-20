<?php
/** @var array $etapas */
$etapas = $etapas ?? [];
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>CRM Assistente • Etapas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { brand: { DEFAULT: '#111827' } },
          borderRadius: { 'xl': '0.75rem', '2xl': '1rem' },
          boxShadow: { soft: '0 4px 16px rgba(0,0,0,0.06)' }
        }
      }
    }
  </script>
  <style>
    .fade-enter{opacity:0;transform:translateY(6px)}
    .fade-enter-active{opacity:1;transform:translateY(0);transition:all .18s ease}
    .ellipsis{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  </style>
</head>
<body class="bg-slate-50 text-slate-800">
<div class="min-h-screen flex">
  <?= view('sidebar') ?>

  <main class="flex-1 p-6">
    <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <nav class="text-xs text-slate-500 mb-1" aria-label="Breadcrumb">
          <ol class="flex items-center gap-1">
            <li>CRM</li><li class="opacity-50">/</li><li>Assistente</li><li class="opacity-50">/</li>
            <li class="font-medium text-slate-700">Gerenciar Etapas</li>
          </ol>
        </nav>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Gerenciar Etapas</h1>
      </div>

      <div class="flex items-center gap-3">
        <button id="btnNova" class="px-3 py-2 rounded-lg bg-slate-900 text-white hover:shadow">Nova etapa</button>
      </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Formulário -->
      <section class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5">
          <h2 class="font-semibold mb-4" id="formTitle">Nova Etapa</h2>

          <form action="<?= base_url('/etapas/salvar') ?>" method="post" id="etapaForm" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="id">

            <div>
              <label class="block text-sm mb-1">Nome da etapa</label>
              <input type="text" name="etapa_atual" id="etapa_atual"
                     class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-900"
                     placeholder="ex.: entrada, agendamento, humano" required>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm mb-1">Tempo de resposta (s)</label>
                <input type="number" name="tempo_resposta" id="tempo_resposta" min="0" max="60" value="5"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-900" required>
              </div>

              <!-- IA pode responder -->
              <div>
                <label class="block text-sm mb-1">IA pode responder?</label>
                <label class="inline-flex items-center gap-2 select-none">
                  <input type="checkbox" name="ia_pode_responder" id="ia_pode_responder" class="rounded" checked>
                  <span class="text-sm">Sim, responder nesta etapa</span>
                </label>
              </div>
            </div>

            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block text-sm">Prompt base (opcional)</label>
                <button type="button" id="btnPromptSugestao" class="text-xs text-slate-500 hover:text-slate-700">Inserir sugestão</button>
              </div>
              <textarea name="prompt_base" id="prompt_base" rows="6"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-900"
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

            <div class="text-xs text-slate-500 -mt-2">
              <p>• Quando <b>IA pode responder</b> estiver desmarcado, a IA ficará em silêncio nesta etapa.</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
              <button class="px-5 py-2 rounded-xl bg-slate-900 text-white font-medium">Salvar</button>
              <button type="button" id="btnCancelar" class="px-5 py-2 rounded-xl bg-slate-100 text-slate-700">Cancelar</button>
            </div>
          </form>
        </div>
      </section>

      <!-- Tabela -->
      <section class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <h2 class="font-semibold">Etapas cadastradas</h2>
            <div class="relative">
              <input id="busca" type="text" placeholder="/ Buscar..."
                     class="rounded-xl border border-slate-300 bg-white pl-9 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-900">
              <span class="pointer-events-none absolute left-3 top-2.5 text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.9 14.32a8 8 0 111.414-1.414l4.387 4.386a1 1 0 01-1.414 1.415l-4.387-4.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd"/></svg>
              </span>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="text-left bg-slate-50">
                <tr class="text-slate-600">
                  <th class="px-3 py-2 w-10">#</th>
                  <th class="px-3 py-2">Etapa</th>
                  <th class="px-3 py-2">Tempo (s)</th>
                  <th class="px-3 py-2">IA?</th>
                  <th class="px-3 py-2">Formal</th>
                  <th class="px-3 py-2">Longas</th>
                  <th class="px-3 py-2">Redir.</th>
                  <th class="px-3 py-2">Criado em</th>
                  <th class="px-3 py-2 text-right">Ações</th>
                </tr>
              </thead>
              <tbody id="tbodyEtapas" class="divide-y divide-slate-100">
                <?php if (!empty($etapas)): ?>
                  <?php foreach ($etapas as $i => $e): ?>
                    <tr>
                      <td class="px-3 py-2 text-slate-400"><?= $i+1 ?></td>
                      <td class="px-3 py-2 text-slate-800 ellipsis" title="<?= esc($e['etapa_atual']) ?>"><?= esc($e['etapa_atual']) ?></td>
                      <td class="px-3 py-2"><?= esc($e['tempo_resposta'] ?? '-') ?></td>
                      <td class="px-3 py-2"><?= !empty($e['ia_pode_responder']) ? 'Sim' : 'Não' ?></td>
                      <td class="px-3 py-2"><?= !empty($e['modo_formal']) ? 'Sim' : 'Não' ?></td>
                      <td class="px-3 py-2"><?= !empty($e['permite_respostas_longas']) ? 'Sim' : 'Não' ?></td>
                      <td class="px-3 py-2"><?= !empty($e['permite_redirecionamento']) ? 'Sim' : 'Não' ?></td>
                      <td class="px-3 py-2"><?= esc($e['criado_em'] ?? '-') ?></td>
                      <td class="px-3 py-2 text-right">
                        <div class="inline-flex items-center gap-1">
                          <button class="px-2 py-1 rounded bg-amber-100 text-amber-800 hover:bg-amber-200"
                                  onclick='editarEtapa(<?= json_encode($e, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'>Editar</button>

                          <!-- Excluir envia etapa_atual para /etapas/excluir -->
                          <form action="<?= base_url('/etapas/excluir') ?>" method="post" class="inline needs-confirm" data-nome="<?= esc($e['etapa_atual']) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="etapa_atual" value="<?= esc($e['etapa_atual']) ?>">
                            <button class="px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200">Excluir</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="9" class="px-3 py-8 text-center text-slate-500">Nenhuma etapa cadastrada.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </main>
</div>

<!-- Modal de confirmação -->
<div id="confirmDelete" class="fixed inset-0 z-50 hidden items-center justify-center">
  <div class="absolute inset-0 bg-slate-900/50" onclick="fecharConfirm()"></div>
  <div class="relative bg-white w-full max-w-md rounded-2xl shadow-xl p-6">
    <div class="flex items-start gap-3">
      <div class="h-10 w-10 rounded-xl bg-red-50 flex items-center justify-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-9 0h10"/></svg>
      </div>
      <div class="flex-1">
        <h4 class="text-base font-semibold">Excluir etapa?</h4>
        <p id="confirmText" class="mt-1 text-sm text-slate-500">Esta ação não pode ser desfeita.</p>
        <div class="mt-4 flex items-center justify-end gap-2">
          <button class="px-3 py-2 rounded-lg bg-slate-100 text-slate-700" onclick="fecharConfirm()">Cancelar</button>
          <button id="btnConfirmDelete" class="px-3 py-2 rounded-lg bg-red-600 text-white">Excluir</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toasts -->
<div id="toasts" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

<script>
// ====== Toast util ======
function toast(msg, type='default'){
  const wrap = document.getElementById('toasts');
  const el = document.createElement('div');
  el.className = `fade-enter px-4 py-2 rounded-xl shadow text-sm text-white ${type==='error'?'bg-red-600':type==='success'?'bg-emerald-600':'bg-slate-900'}`;
  el.textContent = msg;
  wrap.appendChild(el);
  requestAnimationFrame(()=> el.classList.add('fade-enter-active'));
  setTimeout(()=> el.remove(), 2400);
}

// ====== Filtro da tabela (debounce + atalho '/') ======
let buscaTimer=null; const buscaEl=document.getElementById('busca');
buscaEl.addEventListener('input', function(){ clearTimeout(buscaTimer); buscaTimer=setTimeout(()=>filtrar(this.value), 180); });
document.addEventListener('keydown', (e)=>{ if(e.key==='/' && document.activeElement!==buscaEl){ e.preventDefault(); buscaEl.focus(); } });
function filtrar(q){
  q = (q||'').toLowerCase();
  document.querySelectorAll('#tbodyEtapas tr').forEach(tr => {
    const text = tr.innerText.toLowerCase();
    tr.style.display = text.includes(q) ? '' : 'none';
  });
}

// ====== Preencher formulário para edição ======
function editarEtapa(e) {
  document.getElementById('formTitle').textContent = 'Editar Etapa';
  document.getElementById('id').value = e.id || '';
  document.getElementById('etapa_atual').value = e.etapa_atual || '';
  document.getElementById('tempo_resposta').value = e.tempo_resposta ?? 5;
  document.getElementById('prompt_base').value = e.prompt_base || '';
  document.getElementById('modo_formal').checked = !!Number(e.modo_formal||0);
  document.getElementById('permite_respostas_longas').checked = !!Number(e.permite_respostas_longas||0);
  document.getElementById('permite_redirecionamento').checked = !!Number(e.permite_redirecionamento||0);
  document.getElementById('ia_pode_responder').checked = !!Number(e.ia_pode_responder ?? 1);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ====== Reset/Nova ======
const formEl = document.getElementById('etapaForm');
document.getElementById('btnCancelar').addEventListener('click', () => resetForm());
document.getElementById('btnNova').addEventListener('click', () => resetForm());
function resetForm(){
  document.getElementById('formTitle').textContent = 'Nova Etapa';
  formEl.reset();
  document.getElementById('tempo_resposta').value = 5;
  document.getElementById('permite_redirecionamento').checked = true;
  document.getElementById('ia_pode_responder').checked = true;
}

// ====== Confirmação de exclusão ======
let formToDelete = null;
function abrirConfirm(nome, form){
  formToDelete = form;
  document.getElementById('confirmText').textContent = `Excluir a etapa "${nome}"? Esta ação não pode ser desfeita.`;
  const m=document.getElementById('confirmDelete'); m.classList.remove('hidden'); m.classList.add('flex');
}
function fecharConfirm(){ formToDelete=null; const m=document.getElementById('confirmDelete'); m.classList.add('hidden'); m.classList.remove('flex'); }
document.getElementById('btnConfirmDelete').addEventListener('click', ()=>{ if(formToDelete){ formToDelete.submit(); } });

// Intercepta forms de exclusão com .needs-confirm
addEventListener('submit', (e)=>{
  const f = e.target.closest('form.needs-confirm');
  if(!f) return;
  e.preventDefault();
  abrirConfirm(f.dataset.nome || 'etapa', f);
});

// ====== Sugestão de prompt ======
const btnPromptSugestao = document.getElementById('btnPromptSugestao');
btnPromptSugestao?.addEventListener('click', ()=>{
  const etapa = (document.getElementById('etapa_atual').value||'').trim() || 'etapa';
  const base = `Você é a assistente da clínica. Responda no WhatsApp de forma gentil e objetiva.\nContexto: etapa "${etapa}".\nRegras: não fale de preços nem marque consulta sem solicitação. Se perguntarem por valores/agendamento, oriente que um humano continuará o atendimento.`;
  const el = document.getElementById('prompt_base');
  el.value = base; el.focus(); toast('Sugestão inserida','success');
});
</script>
</body>
</html>
