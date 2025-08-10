<?php
$etapas  = $etapas  ?? [];
$colunas = $colunas ?? [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>Kanban de Atendimento | CRM da Dra. Bruna Sathler</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Sortable (drag & drop) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
  <!-- jQuery (usado apenas no AJAX do mover lead; pode remover se quiser só fetch) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-50">
<div class="flex h-screen">

  <!-- Sidebar -->
  <?= view('sidebar') ?>

  <!-- Conteúdo principal -->
  <main class="flex-1 p-8 overflow-y-auto">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-3xl font-semibold text-gray-800">Kanban de Leads</h2>
      <button id="btnAbrirCriarTag" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
        + Criar Tag
      </button>
    </div>

    <!-- Colunas -->
    <div class="flex h-[calc(100vh-8rem)] overflow-x-auto px-4 space-x-4">
      <?php foreach ($etapas as $key => $titulo): ?>
        <div class="kanban-column flex flex-col bg-white rounded-lg shadow-md w-72 min-w-[18rem]">
          <div class="p-3 text-center font-bold text-gray-700 border-b"><?= esc($titulo) ?></div>

          <div class="cards-container flex-1 overflow-y-auto p-3 space-y-3 min-h-[50px]" data-etapa="<?= esc($key) ?>">
            <?php foreach ($colunas as $coluna): ?>
              <?php if ($coluna['etapa'] == $key): ?>
                <?php foreach ($coluna['clientes'] as $lead): ?>
                  <div class="kanban-card bg-gray-100 p-3 rounded shadow text-sm select-none cursor-pointer"
                       data-lead-id="<?= esc($lead['numero']) ?>" id="lead-<?= esc($lead['numero']) ?>">
                    <div class="flex items-center justify-between">
                      <div class="font-medium text-gray-800"><?= esc(mb_strimwidth($lead['numero'], 0, 12, '…', 'UTF-8')) ?></div>
                      <div class="flex gap-1" data-tags-holder></div>
                    </div>
                    <div class="text-gray-500 mt-1">
                      <?= esc(mb_strimwidth($lead['ultima_mensagem_usuario'] ?? '', 0, 40, '…', 'UTF-8')) ?>
                    </div>
                    <div class="text-gray-500">
                      <?= esc(mb_strimwidth($lead['ultima_resposta_ia'] ?? '', 0, 40, '…', 'UTF-8')) ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
</div>

<!-- Modal: Criar Tag -->
<div id="modalCriarTag" class="fixed inset-0 z-50 hidden items-center justify-center">
  <div class="absolute inset-0 bg-black/40" onclick="fecharModal('modalCriarTag')"></div>
  <div class="relative bg-white w-full max-w-md rounded-2xl shadow p-6">
    <h3 class="text-lg font-semibold mb-4">Criar nova tag</h3>
    <form id="formCriarTag" class="space-y-4" onsubmit="return false;">
      <?= csrf_field() ?>
      <div>
        <label class="block text-sm mb-1">Nome</label>
        <input type="text" id="tagNome" class="w-full border rounded-lg px-3 py-2" placeholder="ex.: VIP, Lead quente" required>
      </div>
      <div>
        <label class="block text-sm mb-1">Cor</label>
        <input type="color" id="tagCor" class="h-10 w-16 p-1 border rounded" value="#3b82f6">
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="px-4 py-2 rounded-lg bg-gray-100" onclick="fecharModal('modalCriarTag')">Cancelar</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white">Salvar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Detalhes do Lead (dados + tags + histórico + observações + agendamento) -->
<div id="modalLead" class="fixed inset-0 z-50 hidden items-center justify-center">
  <div class="absolute inset-0 bg-black/40" onclick="fecharModal('modalLead')"></div>
  <div class="relative bg-white w-full max-w-3xl rounded-2xl shadow p-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-lg font-semibold">Detalhes do cliente <span id="leadIdTitulo"></span></h3>
        <p class="text-xs text-gray-500" id="leadMeta"></p>
      </div>
      <button class="text-gray-500" onclick="fecharModal('modalLead')">✕</button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Coluna esquerda: dados + tags -->
      <div class="lg:col-span-1 space-y-4">
        <div class="border rounded-xl p-3">
          <h4 class="font-semibold mb-2">Dados</h4>
          <dl class="text-sm text-gray-700 space-y-1">
            <div><span class="font-medium">Nome:</span> <span id="detNome">-</span></div>
            <div><span class="font-medium">Telefone:</span> <span id="detTelefone">-</span></div>
            <div><span class="font-medium">Etapa:</span> <span id="detEtapa" class="px-2 py-0.5 rounded bg-gray-100 text-gray-700"></span></div>
            <div><span class="font-medium">Atualizado:</span> <span id="detAtualizado">-</span></div>
          </dl>
        </div>

        <div class="border rounded-xl p-3">
          <div class="flex items-center justify-between mb-2">
            <h4 class="font-semibold">Tags</h4>
            <button class="text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200" onclick="recarregarTagsDoLead()">Recarregar</button>
          </div>
          <div id="chipsTags" class="flex flex-wrap gap-1 mb-3"></div>
          <div id="listaTags" class="grid grid-cols-1 gap-2 max-h-48 overflow-auto">
            <!-- checkboxes via JS -->
          </div>
          <div class="text-right pt-2">
            <button id="btnSalvarLeadTags" class="px-4 py-2 rounded-lg bg-blue-600 text-white">Salvar tags</button>
          </div>
        </div>
      </div>

      <!-- Coluna direita: histórico + notas + agendamento -->
      <div class="lg:col-span-2 space-y-4">
        <div class="border rounded-xl p-3">
          <h4 class="font-semibold mb-2">Histórico recente</h4>
          <div id="historicoBox" class="space-y-2 max-h-56 overflow-auto text-sm">
            <!-- mensagens via JS -->
          </div>
        </div>

        <div class="border rounded-xl p-3">
          <h4 class="font-semibold mb-2">Observações</h4>
          <form id="formNota" class="flex items-start gap-2 mb-3" onsubmit="return false;">
            <?= csrf_field() ?>
            <textarea id="notaTexto" class="flex-1 border rounded-lg px-3 py-2" rows="2" placeholder="Adicionar observação..."></textarea>
            <button id="btnSalvarNota" class="px-4 py-2 bg-green-600 text-white rounded-lg">Salvar</button>
          </form>
          <ul id="listaNotas" class="space-y-2 text-sm">
            <!-- notas via JS -->
          </ul>
        </div>

        <!-- AGENDAMENTO -->
        <div class="border rounded-xl p-3">
          <h4 class="font-semibold mb-2">Agendar mensagem</h4>
          <form id="formAgendar" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-start" onsubmit="return false;">
            <?= csrf_field() ?>
            <div class="md:col-span-2">
              <label class="block text-sm mb-1">Mensagem</label>
              <textarea id="agMsg" class="w-full border rounded-lg px-3 py-2" rows="2" placeholder="Texto que será enviado..."></textarea>
            </div>
            <div>
              <label class="block text-sm mb-1">Data</label>
              <input type="date" id="agData" class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
              <label class="block text-sm mb-1">Hora</label>
              <input type="time" id="agHora" class="w-full border rounded-lg px-3 py-2" step="60">
            </div>
            <div class="md:col-span-4 text-right">
              <button id="btnAgendar" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Agendar</button>
            </div>
          </form>

          <h5 class="font-medium mt-4 mb-2">Agendamentos</h5>
          <div id="listaAgendamentos" class="space-y-2 text-sm">
            <!-- itens via JS -->
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
/* ================== Util / Estado ================== */
const csrfName = '<?= esc(csrf_token()) ?>';
const csrfHash = '<?= esc(csrf_hash()) ?>';

function abrirModal(id){ const m=document.getElementById(id); m.classList.remove('hidden'); m.classList.add('flex'); }
function fecharModal(id){ const m=document.getElementById(id); m.classList.add('hidden'); m.classList.remove('flex'); }
function toast(msg){
  const t=document.createElement('div'); t.className='fixed bottom-4 right-4 bg-gray-900 text-white px-4 py-2 rounded-lg shadow';
  t.textContent=msg; document.body.appendChild(t); setTimeout(()=>t.remove(),2500);
}
function esc(t){return (t||'').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function formatarData(iso){ if(!iso) return '-'; const d=new Date((iso+'').replace(' ','T')); if(isNaN(d)) return iso; return d.toLocaleString(); }

let leadAtualNumero = null;
let cardAtualEl = null;

/* ================== Criar Tag ================== */
document.getElementById('btnAbrirCriarTag').addEventListener('click', () => {
  document.getElementById('tagNome').value = '';
  document.getElementById('tagCor').value  = '#3b82f6';
  abrirModal('modalCriarTag');
});
document.getElementById('formCriarTag').addEventListener('submit', async (e) => {
  e.preventDefault();
  const nome = document.getElementById('tagNome').value.trim();
  const cor  = document.getElementById('tagCor').value;
  if (!nome) return;

  const fd = new FormData();
  fd.append(csrfName, csrfHash);
  fd.append('nome', nome);
  fd.append('cor', cor);

  const res = await fetch('<?= base_url("kanban/tags") ?>', { method: 'POST', body: fd });
  if (res.ok) {
    fecharModal('modalCriarTag');
    carregarTagsNosCards(); // repinta chips nos cards
    toast('Tag criada!');
  } else {
    toast('Erro ao criar tag');
  }
});

/* ================== Drag & Drop (mover lead) ================== */
document.addEventListener('DOMContentLoaded', function () {
  const containers = document.querySelectorAll('.cards-container');

  containers.forEach((container) => {
    new Sortable(container, {
      group: 'kanban',
      animation: 150,
      fallbackOnBody: true,
      swapThreshold: 0.65,
      forceFallback: true,
      onEnd(evt) {
        const leadElement = evt.item;
        const leadId = leadElement.getAttribute('data-lead-id');
        const destinoEtapa = evt.to.getAttribute('data-etapa');
        if (!leadId || !destinoEtapa) { alert("Erro ao mover o lead."); return; }

        $.ajax({
          url: '<?= base_url("kanban/atualizarEtapa") ?>',
          method: 'POST',
          data: { [csrfName]: csrfHash, numero: leadId, etapa: destinoEtapa },
          success: function(response) {
            if (response.status === 'ok') { toast("Lead movido!"); }
            else { alert(response.message || "Erro ao mover o lead!"); }
          },
          error: function() { alert("Erro na requisição AJAX"); }
        });
      }
    });
  });

  // Clique curto no card abre modal de detalhes (evita conflito com drag)
  document.querySelectorAll('.kanban-card').forEach(card => {
    let downX=0, downY=0, moved=false;
    card.addEventListener('mousedown', (e)=>{ downX=e.clientX; downY=e.clientY; moved=false; });
    card.addEventListener('mousemove', (e)=>{ if(Math.abs(e.clientX-downX)>5 || Math.abs(e.clientY-downY)>5) moved=true; });
    card.addEventListener('mouseup', async (e)=>{
      if (moved) return;
      const numero = card.getAttribute('data-lead-id');
      await abrirModalLead(numero, card);
    });
  });

  // Render chips iniciais nos cards
  carregarTagsNosCards();
});

/* ================== Modal Detalhes do Lead ================== */
async function abrirModalLead(numero, cardEl){
  leadAtualNumero = numero;
  cardAtualEl     = cardEl;

  const res = await fetch('<?= base_url("kanban/lead-detalhes") ?>/'+encodeURIComponent(numero));
  if (!res.ok) { toast('Erro ao carregar detalhes'); return; }
  const data = await res.json();

  // Header
  document.getElementById('leadIdTitulo').textContent = numero;
  document.getElementById('leadMeta').textContent =
    `Etapa: ${data.sessao?.etapa || '-'} • Última atualização: ${formatarData(data.sessao?.data_atualizacao || '')}`;

  // Dados
  document.getElementById('detNome').textContent      = data.paciente?.nome || 'Paciente';
  document.getElementById('detTelefone').textContent  = numero;
  document.getElementById('detEtapa').textContent     = data.sessao?.etapa || '-';
  document.getElementById('detAtualizado').textContent= formatarData(data.sessao?.data_atualizacao || '');

  // Tags
  montarListaTags(data.tags, data.doLead);
  renderChipsTags(data.tags, data.doLead);

  // Histórico
  renderHistorico(data.historico || []);

  // Notas
  renderNotas(data.notas || []);

  // Agendamentos
  await carregarAgendamentos(numero);

  // Bind botões salvar
  document.getElementById('btnSalvarLeadTags').onclick = ()=> salvarLeadTags(leadAtualNumero);
  document.getElementById('btnSalvarNota').onclick     = ()=> salvarNota(leadAtualNumero);
  document.getElementById('btnAgendar').onclick        = ()=> agendarMensagem(leadAtualNumero);

  abrirModal('modalLead');
}

function recarregarTagsDoLead(){
  if (!leadAtualNumero) return;
  abrirModalLead(leadAtualNumero, cardAtualEl);
}

/* ------ Tags ------ */
function montarListaTags(tags, doLead){
  const wrap = document.getElementById('listaTags');
  wrap.innerHTML = '';
  tags.forEach(tag => {
    const checked = doLead.includes(parseInt(tag.id)) ? 'checked' : '';
    wrap.insertAdjacentHTML('beforeend', `
      <label class="flex items-center gap-3 border rounded-lg p-2">
        <input type="checkbox" class="rounded" value="${tag.id}" ${checked}>
        <span class="inline-flex items-center gap-2">
          <span class="w-3 h-3 rounded-full" style="background:${tag.cor||'#3b82f6'}"></span>
          <span>${esc(tag.nome)}</span>
        </span>
      </label>
    `);
  });
}
function getTagsSelecionadas(){
  return Array.from(document.querySelectorAll('#listaTags input[type="checkbox"]:checked'))
              .map(i => i.value);
}
async function salvarLeadTags(numero){
  const ids = getTagsSelecionadas();
  const fd  = new FormData();
  fd.append(csrfName, csrfHash);
  ids.forEach(id => fd.append('tags[]', id));

  const res = await fetch('<?= base_url("kanban/lead-tags") ?>/'+encodeURIComponent(numero), { method: 'POST', body: fd });
  if (res.ok){
    toast('Tags salvas!');
    const fresh = await (await fetch('<?= base_url("kanban/lead-detalhes") ?>/'+encodeURIComponent(numero))).json();
    renderChipsTags(fresh.tags, fresh.doLead);
    await renderChipsNoCard(numero, cardAtualEl);
  } else {
    toast('Erro ao salvar tags');
  }
}
function renderChipsTags(tags, doLead){
  const holder = document.getElementById('chipsTags');
  holder.innerHTML = '';
  const mapa = {}; tags.forEach(t => mapa[t.id]=t);
  doLead.forEach(id => {
    const tag = mapa[id]; if (!tag) return;
    const chip = document.createElement('span');
    chip.className = 'px-2 py-0.5 rounded text-xs text-white';
    chip.style.background = tag.cor || '#3b82f6';
    chip.textContent = tag.nome;
    holder.appendChild(chip);
  });
}
async function renderChipsNoCard(numero, cardEl){
  const holder = cardEl.querySelector('[data-tags-holder]');
  if (!holder) return;
  holder.innerHTML = '';
  const res = await fetch('<?= base_url("kanban/lead-tags") ?>/'+encodeURIComponent(numero));
  if (!res.ok) return;
  const data = await res.json();
  const mapa = {}; data.tags.forEach(t => mapa[t.id]=t);
  data.doLead.forEach(id => {
    const tag = mapa[id]; if (!tag) return;
    const chip = document.createElement('span');
    chip.className = 'px-2 py-0.5 rounded text-[10px] text-white';
    chip.style.background = tag.cor || '#3b82f6';
    chip.textContent = tag.nome;
    holder.appendChild(chip);
  });
}
async function carregarTagsNosCards(){
  const cards = document.querySelectorAll('.kanban-card');
  for (const card of cards) {
    const numero = card.getAttribute('data-lead-id');
    await renderChipsNoCard(numero, card);
  }
}

/* ------ Histórico ------ */
function renderHistorico(hist){
  const box = document.getElementById('historicoBox');
  box.innerHTML = '';
  hist.forEach(m => {
    const isUser = m.role === 'user';
    const side   = isUser ? 'justify-start' : 'justify-end';
    const cls    = isUser ? 'bg-white border border-gray-200 text-gray-800' : 'bg-blue-600 text-white';
    box.insertAdjacentHTML('beforeend', `
      <div class="flex ${side}">
        <div class="max-w-[80%] rounded-2xl px-3 py-2 ${cls}">
          <div class="whitespace-pre-wrap break-words text-[13px]">${esc(m.content||'')}</div>
        </div>
      </div>
    `);
  });
  box.scrollTop = box.scrollHeight;
}

/* ------ Notas ------ */
function renderNotas(notas){
  const ul = document.getElementById('listaNotas');
  ul.innerHTML = '';
  if (!notas.length){
    ul.innerHTML = '<li class="text-gray-500">Sem observações.</li>';
    return;
  }
  notas.forEach(n => {
    const quando = formatarData(n.criado_em || '');
    ul.insertAdjacentHTML('beforeend', `
      <li class="border rounded-lg p-2">
        <div class="text-xs text-gray-500 mb-1">${quando} • ${esc(n.autor||'atendente')}</div>
        <div class="text-sm whitespace-pre-wrap break-words">${esc(n.texto||'')}</div>
      </li>
    `);
  });
}
async function salvarNota(numero){
  const txtEl = document.getElementById('notaTexto');
  const texto = (txtEl.value||'').trim();
  if (!texto) return;

  const fd = new FormData();
  fd.append(csrfName, csrfHash);
  fd.append('texto', texto);

  const res = await fetch('<?= base_url("kanban/lead-nota") ?>/'+encodeURIComponent(numero), { method: 'POST', body: fd });
  if (res.ok){
    txtEl.value = '';
    toast('Observação salva!');
    const fresh = await (await fetch('<?= base_url("kanban/lead-detalhes") ?>/'+encodeURIComponent(numero))).json();
    renderNotas(fresh.notas || []);
  } else {
    toast('Erro ao salvar observação');
  }
}

/* ------ Agendamentos ------ */
async function carregarAgendamentos(numero){
  const res = await fetch('<?= base_url("kanban/lead-schedules") ?>/'+encodeURIComponent(numero));
  if (!res.ok) return;
  const data = await res.json();
  renderAgendamentos(data.agendamentos || []);
}
function renderAgendamentos(list){
  const wrap = document.getElementById('listaAgendamentos');
  wrap.innerHTML = '';
  if (!list.length){
    wrap.innerHTML = '<div class="text-gray-500">Nenhum agendamento.</div>';
    return;
  }
  list.forEach(item => {
    const dt = formatarData(item.enviar_em);
    const pendente = item.status === 'pendente';
    wrap.insertAdjacentHTML('beforeend', `
      <div class="border rounded-lg p-2 flex items-center justify-between">
        <div class="pr-3">
          <div class="text-gray-800">${esc(item.mensagem || '')}</div>
          <div class="text-xs text-gray-500 mt-1">${dt} • ${item.status}</div>
        </div>
        <div>
          ${pendente ? `
            <button class="px-3 py-1 rounded bg-red-100 text-red-700"
                    onclick="cancelarAgendamento(${item.id})">Cancelar</button>
          ` : ``}
        </div>
      </div>
    `);
  });
}
async function agendarMensagem(numero){
  const msg  = document.getElementById('agMsg').value.trim();
  const data = document.getElementById('agData').value;
  const hora = document.getElementById('agHora').value;

  if (!msg || !data || !hora) { toast('Preencha mensagem, data e hora.'); return; }

  const fd = new FormData();
  fd.append(csrfName, csrfHash);
  fd.append('mensagem', msg);
  fd.append('data', data);
  fd.append('hora', hora);

  const res = await fetch('<?= base_url("kanban/lead-schedules") ?>/'+encodeURIComponent(numero), {
    method: 'POST', body: fd
  });
  if (res.ok){
    document.getElementById('agMsg').value = '';
    toast('Mensagem agendada!');
    await carregarAgendamentos(numero);
  } else {
    const j = await res.json().catch(()=>({msg:'Erro ao agendar'}));
    toast(j.msg || 'Erro ao agendar');
  }
}
async function cancelarAgendamento(id){
  const fd = new FormData();
  fd.append(csrfName, csrfHash);

  const res = await fetch('<?= base_url("kanban/lead-schedules/cancelar") ?>/'+id, {
    method: 'POST', body: fd
  });
  if (res.ok){
    toast('Agendamento cancelado.');
    await carregarAgendamentos(leadAtualNumero);
  } else {
    toast('Erro ao cancelar.');
  }
}
</script>
</body>
</html>
