<?php
/** @var array $etapas */
/** @var array $instancias */
/** @var array $instanciasMap */
$etapas        = $etapas ?? [];
$instancias    = $instancias ?? [];
$instanciasMap = $instanciasMap ?? [];
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
          colors: {
            brand: { DEFAULT: '#111827' },
            ocean: { DEFAULT: '#2563eb', 50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',600:'#2563eb' },
            mint:  { DEFAULT: '#10b981' },
            rose:  { DEFAULT: '#ef4444' }
          },
          borderRadius: { 'xl': '0.75rem', '2xl': '1rem' },
          boxShadow: { soft: '0 8px 32px rgba(2,6,23,.06)', glass: '0 10px 30px rgba(2,6,23,.08)' },
          backgroundImage: {
            'mesh': 'radial-gradient(1200px 600px at 10% -10%, rgba(59,130,246,.12), transparent 60%), radial-gradient(1200px 600px at 110% 110%, rgba(16,185,129,.12), transparent 60%)'
          }
        }
      }
    }
  </script>
  <style>
    .fade-enter{opacity:.0;transform:translateY(6px)}
    .fade-enter-active{opacity:1;transform:translateY(0);transition:all .18s ease}
    .ellipsis{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .drag-hover{outline:2px dashed rgba(37,99,235,.5); outline-offset:-2px; border-radius:.75rem}
  </style>
</head>
<body class="bg-slate-50 text-slate-800">
<div class="min-h-screen flex bg-mesh">

  <?= view('sidebar') ?>

  <main class="flex-1 p-6">
    <!-- Cabeçalho -->
    <header class="mb-6">
      <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-ocean-600 to-mint shadow-glass">
        <div class="absolute inset-0 opacity-[.08] bg-[radial-gradient(30rem_12rem_at_60%_-10%,white,transparent)]"></div>
        <div class="p-6 md:p-8 flex items-center justify-between gap-6">
          <div class="min-w-0">
            <h1 class="text-white text-2xl md:text-3xl font-semibold tracking-tight">Gerenciar Etapas da Assistente</h1>
            <p class="text-white/80 mt-1 text-sm">Defina o comportamento da IA por etapa, ordem do funil e a instância WhatsApp preferida.</p>
          </div>
          <button id="btnNova" class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white text-slate-900 hover:shadow">
            <span>+ Nova etapa</span>
          </button>
        </div>
      </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Formulário -->
      <section class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-soft ring-1 ring-slate-200 overflow-hidden">
          <div class="px-5 pt-5">
            <h2 class="font-semibold" id="formTitle">Nova Etapa</h2>
            <p class="text-xs text-slate-500 mt-1">Desmarque “IA pode responder” para silenciar a IA nesta etapa.</p>
          </div>

          <form action="<?= base_url('/etapas/salvar') ?>" method="post" id="etapaForm" class="p-5 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="id">

            <div>
              <label class="block text-sm mb-1">Nome da etapa</label>
              <input type="text" name="etapa_atual" id="etapa_atual"
                     class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-900"
                     placeholder="ex.: entrada, em_contato, financeiro, humano" required>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm mb-1">Tempo de resposta (s)</label>
                <input type="number" name="tempo_resposta" id="tempo_resposta" min="0" max="60" value="5"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-900" required>
              </div>
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
                        placeholder="Regras/respostas específicas desta etapa..."></textarea>
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

            <!-- Instância preferida (select) -->
            <div class="grid grid-cols-1 gap-3">
              <div>
                <label class="block text-sm mb-1">
                  Instância preferida
                  <span class="text-[11px] text-slate-400">(opcional)</span>
                </label>
                <select id="instancia_select"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-slate-900">
                  <option value="">— Selecionar instância —</option>
                  <?php foreach ($instancias as $inst):
                      $label =  $inst['nome'] ?? $inst['label'] ?? ('Instância ' . substr((string)($inst['instance_id'] ?? ''), 0, 6));
                      $msisdn = $inst['linha_msisdn'] ?? '';
                      $token  = $inst['token'] ?? '';
                  ?>
                    <option value="<?= esc($token) ?>" data-msisdn="<?= esc($msisdn) ?>">
                      <?= esc($label) ?><?= $msisdn ? ' · +' . esc($msisdn) : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <!-- salvamos token/msisdn nos campos escondidos -->
                <input type="hidden" name="instancia_preferida_token"  id="instancia_preferida_token">
                <input type="hidden" name="instancia_preferida_msisdn" id="instancia_preferida_msisdn">
              </div>
            </div>

            <div class="pt-2 flex items-center gap-3">
              <button class="px-5 py-2 rounded-xl bg-slate-900 text-white font-medium">Salvar</button>
              <button type="button" id="btnCancelar" class="px-5 py-2 rounded-xl bg-slate-100 text-slate-700">Cancelar</button>
            </div>
          </form>
        </div>

        <!-- Dicas -->
      </section>

      <!-- Tabela -->
      <section class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-soft ring-1 ring-slate-200 p-5">
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
                  <th class="px-3 py-2 w-10">⇅</th>
                  <th class="px-3 py-2">Etapa</th>
                  <th class="px-3 py-2">Tempo (s)</th>
                  <th class="px-3 py-2">IA?</th>
                  <th class="px-3 py-2">Formal</th>
                  <th class="px-3 py-2">Longas</th>
                  <th class="px-3 py-2">Redir.</th>
                  <th class="px-3 py-2">Instância pref.</th>
                  <th class="px-3 py-2">Criado em</th>
                  <th class="px-3 py-2 text-right">Ações</th>
                </tr>
              </thead>
              <tbody id="tbodyEtapas" class="divide-y divide-slate-100">
                <?php if (!empty($etapas)): ?>
                  <?php foreach ($etapas as $i => $e): ?>
                    <tr class="hover:bg-slate-50" draggable="true" data-id="<?= (int)$e['id'] ?>">
                      <td class="px-3 py-2 text-slate-400 cursor-grab select-none" title="Arraste para reordenar">⋮⋮</td>
                      <td class="px-3 py-2 text-slate-800 ellipsis" title="<?= esc($e['etapa_atual']) ?>"><?= esc($e['etapa_atual']) ?></td>
                      <td class="px-3 py-2"><?= esc($e['tempo_resposta'] ?? '-') ?></td>
                      <td class="px-3 py-2">
                        <?php if (!empty($e['ia_pode_responder'])): ?>
                          <span class="inline-flex items-center px-2 py-0.5 rounded bg-emerald-50 text-emerald-700">Sim</span>
                        <?php else: ?>
                          <span class="inline-flex items-center px-2 py-0.5 rounded bg-rose-50 text-rose-700">Não</span>
                        <?php endif; ?>
                      </td>
                      <td class="px-3 py-2"><?= !empty($e['modo_formal']) ? 'Sim' : 'Não' ?></td>
                      <td class="px-3 py-2"><?= !empty($e['permite_respostas_longas']) ? 'Sim' : 'Não' ?></td>
                      <td class="px-3 py-2"><?= !empty($e['permite_redirecionamento']) ? 'Sim' : 'Não' ?></td>
                      <td class="px-3 py-2">
                        <?php
                          $tok = (string)($e['instancia_preferida_token'] ?? '');
                          if ($tok && isset($instanciasMap[$tok])) {
                              $lbl = $instanciasMap[$tok]['label'] ?? '';
                              $ms  = $instanciasMap[$tok]['msisdn'] ?? '';
                              echo esc($lbl) . ($ms ? ' · +' . esc($ms) : '');
                          } elseif (!empty($e['instancia_preferida_msisdn'])) {
                              echo '+' . esc($e['instancia_preferida_msisdn']);
                          } else {
                              echo '<span class="text-slate-400">—</span>';
                          }
                        ?>
                      </td>
                      <td class="px-3 py-2"><?= esc($e['criado_em'] ?? '-') ?></td>
                      <td class="px-3 py-2 text-right">
                        <div class="inline-flex items-center gap-1">
                          <button class="px-2 py-1 rounded bg-amber-100 text-amber-800 hover:bg-amber-200"
                                  onclick='editarEtapa(<?= json_encode($e, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'>Editar</button>

                          <form action="<?= base_url('/etapas/excluir') ?>" method="post" class="inline needs-confirm" data-nome="<?= esc($e['etapa_atual']) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="etapa_atual" value="<?= esc($e['etapa_atual']) ?>">
                            <button type="submit" class="px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200">Excluir</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="10" class="px-3 py-8 text-center text-slate-500">Nenhuma etapa cadastrada.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <p class="text-xs text-slate-500 mt-3">Dica: pressione “/” para focar a busca. Arraste as linhas para reordenar (salva automático).</p>
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
          <button id="btnConfirmDelete" class="px-3 py-2 rounded-lg bg-red-600 text-white" type="button">Excluir</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toasts -->
<div id="toasts" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

<script>
// ====== Toast ======
function toast(msg, type='default'){
  const wrap = document.getElementById('toasts');
  const el = document.createElement('div');
  el.className = `fade-enter px-4 py-2 rounded-xl shadow text-sm text-white ${type==='error'?'bg-red-600':type==='success'?'bg-emerald-600':'bg-slate-900'}`;
  el.textContent = msg;
  wrap.appendChild(el);
  requestAnimationFrame(()=> el.classList.add('fade-enter-active'));
  setTimeout(()=> el.remove(), 2400);
}

// ====== Busca (debounce + '/') ======
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

  // instância preferida (token/msisdn) -> select + hidden
  const sel = document.getElementById('instancia_select');
  const tok = e.instancia_preferida_token || '';
  const ms  = e.instancia_preferida_msisdn || '';
  sel.value = tok;
  document.getElementById('instancia_preferida_token').value  = tok;
  document.getElementById('instancia_preferida_msisdn').value = ms;

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
  // limpa select de instância + hiddens
  const sel = document.getElementById('instancia_select');
  sel.value = '';
  document.getElementById('instancia_preferida_token').value  = '';
  document.getElementById('instancia_preferida_msisdn').value = '';
}

// ====== Mudança do select de instância -> preenche hiddens
const instanciaSelect = document.getElementById('instancia_select');
instanciaSelect?.addEventListener('change', ()=>{
  const opt = instanciaSelect.selectedOptions[0];
  const token  = instanciaSelect.value || '';
  const msisdn = opt ? (opt.getAttribute('data-msisdn') || '') : '';
  document.getElementById('instancia_preferida_token').value  = token;
  document.getElementById('instancia_preferida_msisdn').value = msisdn;
});

// ====== AJAX helper (POST form sem sair da página) ======
async function postForm(form){
  const fd = new FormData(form);
  const res = await fetch(form.action, { method: form.method || 'POST', body: fd });
  let data = {};
  try { data = await res.json(); } catch(_){}
  if (!res.ok || (data && data.ok===false)) {
    throw new Error((data && (data.msg || data.error)) || 'Erro na operação');
  }
  return data;
}

// ====== Submit do formulário (SALVAR) via fetch ======
formEl.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const btn = formEl.querySelector('button[type="submit"], button:not([type])') || formEl.querySelector('button');
  if (btn) btn.disabled = true;
  try{
    await postForm(formEl);
    toast('Etapa salva!','success');
    setTimeout(()=> location.reload(), 600);
  }catch(err){
    toast(err?.message || 'Falha ao salvar','error');
  }finally{
    if (btn) btn.disabled = false;
  }
});

// ====== Confirmação de exclusão + POST via fetch ======
let formToDelete = null;
function abrirConfirm(nome, form){
  formToDelete = form;
  document.getElementById('confirmText').textContent = `Excluir a etapa "${nome}"? Esta ação não pode ser desfeita.`;
  const m=document.getElementById('confirmDelete'); m.classList.remove('hidden'); m.classList.add('flex');
}
function fecharConfirm(){ formToDelete=null; const m=document.getElementById('confirmDelete'); m.classList.add('hidden'); m.classList.remove('flex'); }
addEventListener('submit', (e)=>{
  const f = e.target.closest('form.needs-confirm');
  if(!f) return;
  e.preventDefault();
  abrirConfirm(f.dataset.nome || 'etapa', f);
});
document.getElementById('btnConfirmDelete').addEventListener('click', async ()=>{
  if(!formToDelete) return;
  const form = formToDelete;
  fecharConfirm();
  try{
    await postForm(form);
    toast('Etapa excluída.','success');
    setTimeout(()=> location.reload(), 400);
  }catch(err){
    toast(err?.message || 'Erro ao excluir','error');
  }
});

// ====== Sugestão de prompt ======
const btnPromptSugestao = document.getElementById('btnPromptSugestao');
btnPromptSugestao?.addEventListener('click', ()=>{
  const etapa = (document.getElementById('etapa_atual').value||'').trim() || 'etapa';
  const base = `Você é a assistente da clínica. Responda no WhatsApp de forma gentil e objetiva.
Contexto: etapa "${etapa}".
Regras: não fale de preços nem marque consulta sem solicitação. Se perguntarem por valores/agendamento, oriente que um humano continuará o atendimento.`;
  const el = document.getElementById('prompt_base');
  el.value = base; el.focus(); toast('Sugestão inserida','success');
});

// ====== Drag & Drop para reordenar ======
const tbody = document.getElementById('tbodyEtapas');
let dragging;
tbody?.addEventListener('dragstart', (e) => {
  const tr = e.target.closest('tr[draggable="true"]');
  if (!tr) return;
  dragging = tr;
  tr.classList.add('opacity-60');
  e.dataTransfer.effectAllowed = 'move';
});
tbody?.addEventListener('dragend', (e) => {
  const tr = e.target.closest('tr[draggable="true"]');
  if (tr) tr.classList.remove('opacity-60');
  document.querySelectorAll('#tbodyEtapas tr').forEach(r=>r.classList.remove('drag-hover'));
  if (dragging) salvarOrdem();
  dragging = null;
});
tbody?.addEventListener('dragover', (e) => {
  e.preventDefault();
  const tr = e.target.closest('tr[draggable="true"]');
  if (!tr || tr === dragging) return;
  document.querySelectorAll('#tbodyEtapas tr').forEach(r=>r.classList.remove('drag-hover'));
  tr.classList.add('drag-hover');
  const rect = tr.getBoundingClientRect();
  const before = (e.clientY - rect.top) < (rect.height / 2);
  tbody.insertBefore(dragging, before ? tr : tr.nextSibling);
});

async function salvarOrdem() {
  const ids = Array.from(document.querySelectorAll('#tbodyEtapas tr[draggable="true"]'))
    .map(tr => tr.getAttribute('data-id'));
  const form = new FormData();
  const csrfInput = document.querySelector('#etapaForm input[name="<?= esc(csrf_token()) ?>"]');
  if (csrfInput) form.append('<?= esc(csrf_token()) ?>', csrfInput.value);
  ids.forEach(id => form.append('ids[]', id));
  try {
    const res = await fetch('<?= base_url('/etapas/ordenar') ?>', { method: 'POST', body: form });
    const data = await res.json().catch(()=> ({}));
    if (!res.ok) { toast(data?.msg || 'Erro ao salvar ordem', 'error'); }
    else { toast('Ordem atualizada','success'); }
  } catch (err) { toast('Erro ao salvar ordem','error'); }
}
</script>
</body>
</html>
