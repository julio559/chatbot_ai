<?php
$etapas  = $etapas  ?? [];
$colunas = $colunas ?? [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>Kanban de Atendimento | CRM</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Sortable (drag & drop) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
  <!-- jQuery (só para um POST) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    .ellipsis{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .fade-enter{opacity:0;transform:translateY(6px)}
    .fade-enter-active{opacity:1;transform:translateY(0);transition:all .18s ease}
    /* animação suave ao abrir */
    .modal-card{opacity:.0; transform:translateY(6px) scale(.98); transition:opacity .18s ease, transform .18s ease;}
    #modalLead.flex .modal-card{opacity:1; transform:none;}
    #modalConfigColuna.flex .modal-card{opacity:1; transform:none;}
  </style>
</head>
<body class="bg-slate-50 text-slate-800">
<div class="flex h-screen">

  <!-- Sidebar -->
  <?= view('sidebar') ?>

  <!-- Main -->
  <main class="flex-1 p-6 overflow-hidden">
    <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <div class="min-w-0">
        <h2 class="text-2xl font-semibold tracking-tight">Kanban de Leads</h2>
        <p class="text-xs text-slate-500">Arraste e solte para mover entre etapas. Clique no card para abrir o lead.</p>
      </div>
      <div class="flex items-center gap-2 w-full md:w-auto">
        <div class="relative md:w-72 w-full">
          <input id="filtroGlobal" type="text" placeholder="/ Buscar por telefone, tag ou texto..." class="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-900"/>
          <span class="pointer-events-none absolute left-3 top-2.5 text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.9 14.32a8 8 0 111.414-1.414l4.387 4.386a1 1 0 01-1.414 1.415l-4.387-4.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd"/></svg>
          </span>
        </div>
        <button id="btnAbrirCriarTag" class="px-4 py-2 rounded-xl bg-slate-900 text-white hover:shadow">+ Criar Tag</button>
      </div>
    </header>

    <!-- Board -->
    <section class="h-[calc(100vh-9.5rem)] overflow-x-auto overflow-y-hidden px-1">
      <div class="flex h-full gap-4 pr-3">
        <?php foreach ($etapas as $key => $titulo): ?>
          <div class="kanban-column flex flex-col bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 w-80 min-w-[20rem]" data-etapa-key="<?= esc($key) ?>">
            <div class="sticky top-0 z-10 px-4 py-3 border-b bg-white/90 backdrop-blur rounded-t-2xl">
              <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                  <div class="font-semibold text-slate-800 ellipsis" title="<?= esc($titulo) ?>"><?= esc($titulo) ?></div>
                  <div class="text-[11px] text-slate-500"><span data-col-count>0</span> leads</div>
                </div>
                <button class="px-2 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-50"
                        title="Configurar coluna"
                        onclick="abrirConfigColuna('<?= esc($key) ?>','<?= esc($titulo) ?>')">⚙️</button>
              </div>
            </div>

            <div class="cards-container flex-1 overflow-y-auto p-3 space-y-3 min-h-[64px]" data-etapa="<?= esc($key) ?>">
              <?php foreach ($colunas as $coluna): ?>
                <?php if ($coluna['etapa'] == $key): ?>
                  <?php foreach ($coluna['clientes'] as $lead): ?>
                    <div class="kanban-card bg-slate-50 hover:bg-slate-100 border border-slate-200 p-3 rounded-xl shadow-sm text-sm select-none cursor-grab"
                         data-lead-id="<?= esc($lead['numero']) ?>"
                         data-updated-at="<?= esc($lead['ultimo_contato'] ?? '') ?>"
                         id="lead-<?= esc($lead['numero']) ?>">
                      <div class="flex items-center justify-between gap-2">
                        <div class="font-medium text-slate-800 ellipsis" title="<?= esc($lead['numero']) ?>"><?= esc(mb_strimwidth($lead['numero'], 0, 16, '…', 'UTF-8')) ?></div>
                        <div class="flex flex-wrap gap-1" data-tags-holder></div>
                      </div>
                      <div class="mt-2 space-y-1 text-[13px]">
                        <div class="text-slate-600 ellipsis" title="<?= esc($lead['ultima_mensagem_usuario'] ?? '') ?>"><?= esc(mb_strimwidth($lead['ultima_mensagem_usuario'] ?? '', 0, 60, '…', 'UTF-8')) ?></div>
                        <div class="text-slate-500 ellipsis" title="<?= esc($lead['ultima_resposta_ia'] ?? '') ?>"><?= esc(mb_strimwidth($lead['ultima_resposta_ia'] ?? '', 0, 60, '…', 'UTF-8')) ?></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</div>

<!-- Modal: Criar Tag -->
<div id="modalCriarTag" class="fixed inset-0 z-50 hidden items-center justify-center">
  <div class="absolute inset-0 bg-slate-900/50" onclick="fecharModal('modalCriarTag')"></div>
  <div class="relative bg-white w-full max-w-md rounded-2xl shadow-xl p-6">
    <h3 class="text-lg font-semibold mb-4">Criar nova tag</h3>
    <form id="formCriarTag" class="space-y-4" onsubmit="return false;">
      <?= csrf_field() ?>
      <div>
        <label class="block text-sm mb-1">Nome</label>
        <input type="text" id="tagNome" class="w-full border rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-900" placeholder="ex.: VIP, Lead quente" required>
      </div>
      <div>
        <label class="block text-sm mb-1">Cor</label>
        <input type="color" id="tagCor" class="h-10 w-16 p-1 border rounded" value="#3b82f6">
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="px-4 py-2 rounded-lg bg-slate-100" onclick="fecharModal('modalCriarTag')">Cancelar</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 text-white">Salvar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Detalhes do Lead (NOVA VERSÃO BONITA) -->
<div id="modalLead" class="fixed inset-0 z-50 hidden items-center justify-center">
  <!-- Overlay -->
  <div class="absolute inset-0 bg-slate-900/60" onclick="fecharModal('modalLead')"></div>

  <!-- Card -->
  <div class="relative w-[min(92vw,980px)] max-h-[92vh] overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 modal-card"
       onclick="event.stopPropagation()">

    <!-- Header com gradiente -->
    <div class="relative bg-gradient-to-r from-indigo-600 via-fuchsia-600 to-rose-500 text-white px-6 py-5">
      <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-4 min-w-0">
          <div class="h-12 w-12 rounded-xl bg-white/15 backdrop-blur text-white flex items-center justify-center text-xl font-semibold ring-1 ring-white/25"
               id="leadAvatar">
            <span id="leadAvatarText">?</span>
          </div>
          <div class="min-w-0">
            <h3 class="text-lg font-semibold leading-tight">
              <span>Detalhes do cliente</span>
              <span id="leadIdTitulo" class="opacity-90 ml-2 font-normal"></span>
            </h3>
            <p id="leadMeta" class="text-[12px] opacity-90 truncate"></p>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button onclick="copyPhone()" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 text-sm ring-1 ring-white/20">Copiar telefone</button>
          <button class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 text-sm ring-1 ring-white/20" onclick="fecharModal('modalLead')">Fechar ✕</button>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="px-6 pt-4 border-b">
      <nav id="leadTabs" class="-mb-px flex gap-6 overflow-x-auto">
        <button type="button" data-tab="overview"
                class="border-b-2 border-slate-900 text-slate-900 pb-2 font-medium">Visão geral</button>
        <button type="button" data-tab="conversas"
                class="border-b-2 border-transparent text-slate-500 hover:text-slate-700 pb-2">Conversas & Notas</button>
        <button type="button" data-tab="arquivos"
                class="border-b-2 border-transparent text-slate-500 hover:text-slate-700 pb-2">Arquivos & Agenda</button>
      </nav>
    </div>

    <!-- Conteúdo -->
    <div class="p-6 overflow-y-auto" style="max-height: calc(92vh - 160px)">
      <!-- ===== Painel: Visão geral ===== -->
      <section data-lead-panel="overview">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
          <!-- Dados -->
          <div class="lg:col-span-1 space-y-4">
            <div class="border rounded-xl p-4 shadow-sm">
              <h4 class="font-semibold mb-2">Dados</h4>
              <dl class="text-sm text-slate-700 space-y-1">
                <div><span class="font-medium">Nome:</span> <span id="detNome">-</span></div>
                <div><span class="font-medium">Telefone:</span> <span id="detTelefone">-</span></div>
                <div class="flex items-center gap-2">
                  <span class="font-medium">Etapa:</span>
                  <span id="detEtapa" class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-xs"></span>
                </div>
                <div><span class="font-medium">Atualizado:</span> <span id="detAtualizado">-</span></div>
              </dl>
            </div>

            <!-- Tags -->
            <div class="border rounded-xl p-4 shadow-sm">
              <div class="flex items-center justify-between mb-2">
                <h4 class="font-semibold">Tags</h4>
                <button class="text-xs px-2 py-1 rounded bg-slate-100 hover:bg-slate-200"
                        onclick="recarregarTagsDoLead()">Recarregar</button>
              </div>
              <div id="chipsTags" class="flex flex-wrap gap-1 mb-3"></div>
              <div id="listaTags" class="grid grid-cols-1 gap-2 max-h-48 overflow-auto"></div>
              <div class="text-right pt-2">
                <button id="btnSalvarLeadTags" class="px-4 py-2 rounded-lg bg-slate-900 text-white">Salvar tags</button>
              </div>
            </div>
          </div>

          <!-- Histórico compacto -->
          <div class="lg:col-span-2 space-y-4">
            <div class="border rounded-xl p-4 shadow-sm">
              <div class="flex items-center justify-between mb-2">
                <h4 class="font-semibold">Histórico recente</h4>
                <span class="text-[11px] text-slate-500">últimas 10 mensagens</span>
              </div>
              <div id="historicoBox" class="space-y-2 max-h-72 overflow-auto text-sm"></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== Painel: Conversas & Notas ===== -->
      <section data-lead-panel="conversas" class="hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div class="border rounded-xl p-4 shadow-sm">
            <h4 class="font-semibold mb-2">Linha do tempo</h4>
            <div id="historicoBox" class="space-y-2 max-h-[60vh] overflow-auto text-sm"></div>
          </div>

          <div class="border rounded-xl p-4 shadow-sm">
            <h4 class="font-semibold mb-2">Observações</h4>
            <form id="formNota" class="flex items-start gap-2 mb-3" onsubmit="return false;">
              <?= csrf_field() ?>
              <textarea id="notaTexto" class="flex-1 border rounded-lg px-3 py-2" rows="3" placeholder="Adicionar observação..."></textarea>
              <button id="btnSalvarNota" class="px-4 py-2 bg-emerald-600 text-white rounded-lg">Salvar</button>
            </form>
            <ul id="listaNotas" class="space-y-2 text-sm"></ul>
          </div>
        </div>
      </section>

      <!-- ===== Painel: Arquivos & Agenda ===== -->
      <section data-lead-panel="arquivos" class="hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <!-- Arquivos -->
          <div class="border rounded-xl p-4 shadow-sm">
            <h4 class="font-semibold mb-2">Arquivos do paciente</h4>

            <form id="formUploadArq" class="grid grid-cols-1 sm:grid-cols-2 gap-3" onsubmit="return false;">
              <?= csrf_field() ?>
              <div class="sm:col-span-2">
                <label class="block text-sm mb-1">Arquivo</label>
                <input type="file" id="arqFile" class="w-full border rounded-lg px-3 py-2 bg-white" />
                <p class="text-[11px] text-slate-500 mt-1">PDF, imagens, DOCX, XLSX, TXT (até 15MB).</p>
              </div>
              <div>
                <label class="block text-sm mb-1">Procedimento</label>
                <input type="text" id="arqProc" class="w-full border rounded-lg px-3 py-2" placeholder="Ex.: Facetas, Clareamento">
              </div>
              <div>
                <label class="block text-sm mb-1">Valor (R$)</label>
                <input type="text" id="arqValor" class="w-full border rounded-lg px-3 py-2" placeholder="Ex.: 1500,00">
              </div>
              <div class="sm:col-span-2">
                <label class="block text-sm mb-1">Observação</label>
                <input type="text" id="arqObs" class="w-full border rounded-lg px-3 py-2" placeholder="Opcional">
              </div>
              <div class="sm:col-span-2 text-right">
                <button id="btnUploadArq" class="px-4 py-2 bg-slate-900 text-white rounded-lg">Enviar arquivo</button>
              </div>
            </form>

            <h5 class="font-medium mt-4 mb-2">Anexos</h5>
            <div id="listaArquivos" class="space-y-2 text-sm"></div>
          </div>

          <!-- Agenda -->
          <div class="border rounded-xl p-4 shadow-sm">
            <h4 class="font-semibold mb-2">Agendar mensagem</h4>
            <form id="formAgendar" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-start" onsubmit="return false;">
              <?= csrf_field() ?>
              <div class="md:col-span-2">
                <label class="block text-sm mb-1">Mensagem</label>
                <textarea id="agMsg" class="w-full border rounded-lg px-3 py-2" rows="3" placeholder="Texto que será enviado..."></textarea>
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
                <button id="btnAgendar" class="px-4 py-2 bg-slate-900 text-white rounded-lg">Agendar</button>
              </div>
            </form>

            <h5 class="font-medium mt-4 mb-2">Agendamentos</h5>
            <div id="listaAgendamentos" class="space-y-2 text-sm"></div>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>

<!-- Modal: Configurar Coluna -->
<div id="modalConfigColuna" class="fixed inset-0 z-50 hidden items-center justify-center">
  <div class="absolute inset-0 bg-slate-900/50" onclick="fecharModal('modalConfigColuna')"></div>

  <div class="relative w-[min(92vw,720px)] max-h-[92vh] overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 modal-card"
       onclick="event.stopPropagation()">
    <!-- Header -->
    <div class="relative bg-gradient-to-r from-sky-600 via-indigo-600 to-fuchsia-600 text-white px-6 py-5">
      <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
          <h3 class="text-lg font-semibold leading-tight">
            Configurar coluna <span id="cfgEtapaKey" class="opacity-90 font-normal"></span>
          </h3>
          <p class="text-[12px] opacity-90 truncate">Ajuste nome, limites e aparência dessa etapa.</p>
        </div>
        <button class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 text-sm ring-1 ring-white/20"
                onclick="fecharModal('modalConfigColuna')">Fechar ✕</button>
      </div>
    </div>

    <!-- Conteúdo -->
    <div class="p-6 overflow-y-auto" style="max-height: calc(92vh - 140px)">
      <form id="formConfigColuna" class="grid grid-cols-1 md:grid-cols-2 gap-4" onsubmit="return false;">
        <?= csrf_field() ?>

        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Título visível da coluna</label>
          <input id="cfgTitulo" type="text" class="w-full border rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-900" placeholder="ex.: Em contato">
          <p class="text-[11px] text-slate-500 mt-1">Se vazio, usamos o título padrão da etapa.</p>
        </div>

        <div>
          <label class="block text-sm mb-1">WIP (limite de cards)</label>
          <input id="cfgWip" type="number" min="0" class="w-full border rounded-xl px-3 py-2" placeholder="0 = sem limite">
        </div>

        <div>
          <label class="block text-sm mb-1">Destacar após (horas)</label>
          <input id="cfgSLA" type="number" min="0" class="w-full border rounded-xl px-3 py-2" placeholder="ex.: 24">
          <p class="text-[11px] text-slate-500 mt-1">Cards sem atualização acima disso ficam “alerta”.</p>
        </div>

        <div>
          <label class="block text-sm mb-1">Cor da borda da coluna</label>
          <input id="cfgCor" type="color" class="h-10 w-16 p-1 border rounded" value="#0ea5e9">
        </div>

        <div>
          <label class="block text-sm mb-1">Bloquear resposta da IA nesta etapa</label>
          <select id="cfgBloqIA" class="w-full border rounded-xl px-3 py-2">
            <option value="0">Não</option>
            <option value="1">Sim</option>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="block text-sm mb-1">Template de mensagem automática ao mover para esta etapa</label>
          <textarea id="cfgTemplate" rows="4" class="w-full border rounded-xl px-3 py-2" placeholder="Texto opcional. Variáveis disponíveis: {{nome}}, {{telefone}}, {{etapa}}"></textarea>
        </div>

        <div class="md:col-span-2 flex items-center justify-end gap-2 pt-2">
          <button type="button" class="px-4 py-2 rounded-lg bg-slate-100" onclick="fecharModal('modalConfigColuna')">Cancelar</button>
          <button type="button" id="btnTestarConfig" class="px-4 py-2 rounded-lg bg-amber-600 text-white">Testar envio</button>
          <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 text-white">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Toasts -->
<div id="toasts" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

<script>
// ===== Utils (ES5) =====
var csrfName = '<?= esc(csrf_token()) ?>';
var csrfHash = '<?= esc(csrf_hash()) ?>';

// ===== Arquivos (upload/listar/baixar/excluir) =====
function carregarArquivos(numero){
  return fetch('<?= base_url("kanban/lead-files") ?>/'+encodeURIComponent(numero))
    .then(function(r){ if(!r.ok) throw new Error(); return r.json(); })
    .then(function(data){ renderArquivos(data.arquivos||[]); })
    .catch(function(){ renderArquivos([]); });
}

function renderArquivos(list){
  var wrap=document.getElementById('listaArquivos'); wrap.innerHTML='';
  if(!list.length){
    wrap.innerHTML = '<div class="text-gray-500">Nenhum arquivo enviado.</div>';
    return;
  }
  for(var i=0;i<list.length;i++){
    var a=list[i];
    var dt = a.uploaded_at ? new Date(String(a.uploaded_at).replace(' ','T')).toLocaleString() : '-';
    var tam = a.tamanho ? (Math.round(a.tamanho/1024)+' KB') : '';
    var linha =
      '<div class="border rounded-lg p-2 flex items-center justify-between">'+
        '<div class="pr-3">'+
          '<div class="font-medium">'+esc(a.nome_original||'arquivo')+'</div>'+
          '<div class="text-xs text-slate-500">'+
            (a.procedimento?('Proc.: '+esc(a.procedimento)+' • '):'')+
            (typeof a.valor!=='undefined' && a.valor!==null?('Valor: R$ '+String(a.valor)):'')+
          '</div>'+
          '<div class="text-[11px] text-slate-400 mt-0.5">'+dt+(tam?(' • '+tam):'')+'</div>'+
        '</div>'+
        '<div class="flex items-center gap-2">'+
          '<a class="px-3 py-1 rounded bg-slate-100 hover:bg-slate-200" href="'+esc(a.url_download)+'" target="_blank" rel="noopener">Baixar</a>'+
          '<button class="px-3 py-1 rounded bg-red-100 text-red-700" onclick="excluirArquivo('+a.id+')">Excluir</button>'+
        '</div>'+
      '</div>';
    wrap.insertAdjacentHTML('beforeend', linha);
  }
}

function uploadArquivo(numero){
  var f = document.getElementById('arqFile').files[0];
  if(!f){ toast('Selecione um arquivo.','error'); return; }
  var fd = new FormData();
  fd.append('arquivo', f);
  fd.append('procedimento', (document.getElementById('arqProc').value||'').trim());
  fd.append('valor', (document.getElementById('arqValor').value||'').trim());
  fd.append('observacao', (document.getElementById('arqObs').value||'').trim());
  fd.append('<?= esc(csrf_token()) ?>', '<?= esc(csrf_hash()) ?>');

  fetch('<?= base_url("kanban/lead-files") ?>/'+encodeURIComponent(numero), { method:'POST', body: fd })
    .then(function(r){ if(!r.ok) return r.json().then(function(j){ throw new Error(j.msg||'Erro'); }); return r.json(); })
    .then(function(){ 
      toast('Arquivo enviado!','success');
      document.getElementById('formUploadArq').reset();
      carregarArquivos(numero);
    })
    .catch(function(e){ toast(e.message||'Falha no upload','error'); });
}

function excluirArquivo(id){
  var fd=new FormData(); fd.append('<?= esc(csrf_token()) ?>','<?= esc(csrf_hash()) ?>');
  fetch('<?= base_url("kanban/lead-files/delete") ?>/'+id, { method:'POST', body: fd })
    .then(function(r){ if(!r.ok) throw new Error(); return r.json(); })
    .then(function(){ toast('Arquivo excluído.','success'); carregarArquivos(leadAtualNumero); })
    .catch(function(){ toast('Erro ao excluir.','error'); });
}

function abrirModal(id){ var m=document.getElementById(id); if(!m) return; m.classList.remove('hidden'); m.classList.add('flex'); }
function fecharModal(id){ var m=document.getElementById(id); if(!m) return; m.classList.add('hidden'); m.classList.remove('flex'); }
function toast(msg,type){ if(!type) type='default'; var t=document.createElement('div'); t.className='fade-enter px-4 py-2 rounded-xl shadow text-sm text-white '+(type==='error'?'bg-red-600':type==='success'?'bg-emerald-600':'bg-slate-900'); t.textContent=msg; var w=document.getElementById('toasts'); w.appendChild(t); setTimeout(function(){ t.classList.add('fade-enter-active'); },0); setTimeout(function(){ t.remove(); },2400); }
function esc(t){ return (t||'').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function formatarData(iso){ if(!iso) return '-'; var d=new Date(String(iso).replace(' ','T')); if(isNaN(d)) return iso; return d.toLocaleString(); }
function contrastOn(hex){ try{ var c=parseInt(hex.replace('#',''),16); var r=(c>>16)&255,g=(c>>8)&255,b=c&255; var yiq=((r*299)+(g*587)+(b*114))/1000; return yiq>=128?'#111827':'#fff'; }catch(e){ return '#fff'; } }

// ===== Busca global =====
var filtro=document.getElementById('filtroGlobal'); var buscaTimer=null;
if (filtro) {
  filtro.addEventListener('input', function(){ clearTimeout(buscaTimer); buscaTimer=setTimeout(function(){ filtrarCards(filtro.value); },180); });
  document.addEventListener('keydown', function(e){ if(e.key==='/'){ if(document.activeElement!==filtro){ e.preventDefault(); filtro.focus(); } } });
}
function filtrarCards(q){ q=(q||'').toLowerCase(); var cards=document.querySelectorAll('.kanban-card'); for(var i=0;i<cards.length;i++){ var card=cards[i]; var text=card.innerText.toLowerCase(); card.style.display=text.indexOf(q)>-1?'':'none'; } atualizarCounters(); }

// ===== WIP counter =====
function atualizarCounters(){
  var cols=document.querySelectorAll('.kanban-column');
  for(var i=0;i<cols.length;i++){
    var col=cols[i];
    var body=col.querySelector('.cards-container');
    var badge=col.querySelector('[data-col-count]');
    var count = body ? body.querySelectorAll('.kanban-card:not([style*="display: none"])').length : 0;
    if (badge) badge.textContent = count;

    // Ajuste visual de WIP no header, se houver config
    var key = col.getAttribute('data-etapa-key');
    var cfg = (window.cfgCache && window.cfgCache[key]) || {};
    var wip = (typeof cfg.wip === 'number' ? cfg.wip : 0);
    var header = col.querySelector('.sticky');
    if (header) header.style.background = (wip > 0 && count > wip) ? 'rgba(250,204,21,0.15)' : 'rgba(255,255,255,0.9)';
  }
}

// ===== Drag & Drop + clique para abrir modal do lead =====
document.addEventListener('DOMContentLoaded', function(){
  // Sortable
  var containers=document.querySelectorAll('.cards-container');
  for(var i=0;i<containers.length;i++){
    new Sortable(containers[i], {
      group:'kanban',
      animation:150,
      fallbackOnBody:true,
      swapThreshold:0.65,
      forceFallback:true,
      onEnd:function(evt){
        var leadElement=evt.item;
        var leadId=leadElement.getAttribute('data-lead-id');
        var destinoEtapa=evt.to.getAttribute('data-etapa');
        if(!leadId||!destinoEtapa){ alert('Erro ao mover o lead.'); return; }
        $.ajax({
          url:'<?= base_url("kanban/atualizarEtapa") ?>',
          method:'POST',
          data: (function(){ var o={}; o[csrfName]=csrfHash; o.numero=leadId; o.etapa=destinoEtapa; return o; })(),
          success:function(r){
            if(r && r.status==='ok'){
              toast('Lead movido!','success');
              atualizarCounters();
              // reaplica visuais na coluna destino e SLA geral
              var col = evt.to.closest('.kanban-column');
              if (col) {
                var colKey = col.getAttribute('data-etapa-key');
                if (colKey && window.cfgCache && window.cfgCache[colKey]) {
                  aplicarConfigVisual(colKey, window.cfgCache[colKey]);
                }
              }
              aplicarSLAEmCards();
            } else { alert((r&&r.message)||'Erro ao mover o lead!'); }
          },
          error:function(){ alert('Erro na requisição AJAX'); }
        });
      }
    });
  }

  // Clique curto no card abre modal (protege contra arraste)
  var cards=document.querySelectorAll('.kanban-card');
  for(var j=0;j<cards.length;j++){
    (function(card){
      var downX=0, downY=0, moved=false;
      card.addEventListener('mousedown', function(e){ downX=e.clientX; downY=e.clientY; moved=false; });
      card.addEventListener('mousemove', function(e){ if(Math.abs(e.clientX-downX)>5 || Math.abs(e.clientY-downY)>5) moved=true; });
      card.addEventListener('mouseup', function(e){
        if(moved) return;
        var numero=card.getAttribute('data-lead-id');
        abrirModalLead(numero, card);
      });
    })(cards[j]);
  }

  carregarTagsNosCards();
  atualizarCounters();

  // Criar Tag
  var btnCriar = document.getElementById('btnAbrirCriarTag');
  if (btnCriar) {
    btnCriar.addEventListener('click', function(){
      document.getElementById('tagNome').value='';
      document.getElementById('tagCor').value='#3b82f6';
      abrirModal('modalCriarTag');
    });
  }
  var formTag = document.getElementById('formCriarTag');
  if (formTag) {
    formTag.addEventListener('submit', function(e){
      e.preventDefault();
      var nome=document.getElementById('tagNome').value.trim();
      var cor=document.getElementById('tagCor').value;
      if(!nome) return;
      var fd=new FormData();
      fd.append(csrfName, csrfHash);
      fd.append('nome', nome);
      fd.append('cor', cor);
      fetch('<?= base_url("kanban/tags") ?>', { method:'POST', body: fd })
        .then(function(r){ if(!r.ok) throw new Error(); return r.json().catch(function(){return {ok:true};}); })
        .then(function(){ fecharModal('modalCriarTag'); carregarTagsNosCards(); toast('Tag criada!','success'); })
        .catch(function(){ toast('Erro ao criar tag','error'); });
    });
  }

  // === Bind do modal de Configurar Coluna
  var formCfg = document.getElementById('formConfigColuna');
  if (formCfg) {
    formCfg.addEventListener('submit', function(e){ e.preventDefault(); salvarConfigColuna(); });
  }
  var btnTest = document.getElementById('btnTestarConfig');
  if (btnTest) {
    btnTest.addEventListener('click', function(){ testarConfigColuna(); });
  }

  // Carrega configurações existentes
  carregarConfigsEtapas();
});

// ===== Tabs do Lead =====
function initLeadTabs(){
  var tabs = document.querySelectorAll('#leadTabs [data-tab]');
  var panels = document.querySelectorAll('[data-lead-panel]');
  for (var i=0;i<tabs.length;i++){
    (function(btn){
      btn.addEventListener('click', function(){
        var tab = btn.getAttribute('data-tab');
        for (var j=0;j<tabs.length;j++){
          tabs[j].classList.remove('border-slate-900','text-slate-900');
          tabs[j].classList.add('border-transparent','text-slate-500');
        }
        btn.classList.add('border-slate-900','text-slate-900');
        for (var k=0;k<panels.length;k++){
          var p = panels[k];
          p.classList.toggle('hidden', p.getAttribute('data-lead-panel') !== tab);
        }
      });
    })(tabs[i]);
  }
}

// Avatar com inicial
function setLeadAvatar(nameOrPhone){
  var txt = (nameOrPhone||'').trim();
  var ch = txt ? txt.charAt(0).toUpperCase() : '?';
  var el = document.getElementById('leadAvatarText');
  if (el) el.textContent = ch;
}

// Copiar telefone
function copyPhone(){
  try{
    var t = (document.getElementById('detTelefone').textContent||'').trim();
    if (!t) return;
    if (navigator.clipboard && navigator.clipboard.writeText){
      navigator.clipboard.writeText(t).then(function(){ toast('Telefone copiado!','success'); });
    } else {
      var ta=document.createElement('textarea'); ta.value=t; document.body.appendChild(ta);
      ta.select(); document.execCommand('copy'); ta.remove(); toast('Telefone copiado!','success');
    }
  }catch(e){}
}

// Ao abrir o modal: reseta para a aba "Visão geral"
function resetLeadTabsToOverview(){
  var firstTab = document.querySelector('#leadTabs [data-tab="overview"]');
  if (firstTab) firstTab.click();
}

// Hook no seu abrirModalLead
(function(){
  var _abrirModalLead = abrirModalLead;
  abrirModalLead = function(numero, cardEl){
    _abrirModalLead(numero, cardEl);
    setTimeout(function(){
      initLeadTabs();
      resetLeadTabsToOverview();
    }, 0);
  };
})();

// ===== Modal Lead =====
var leadAtualNumero=null; var cardAtualEl=null;

function abrirModalLead(numero, cardEl){
  leadAtualNumero = numero; cardAtualEl = cardEl;
  fetch('<?= base_url("kanban/lead-detalhes") ?>/'+encodeURIComponent(numero))
    .then(function(r){ if(!r.ok) throw new Error(); return r.json(); })
    .then(function(data){
      document.getElementById('leadIdTitulo').textContent = numero;
      document.getElementById('leadMeta').textContent =
        'Etapa: '+(data.sessao && data.sessao.etapa ? data.sessao.etapa : '-')+
        ' • Última atualização: '+formatarData(data.sessao && data.sessao.data_atualizacao ? data.sessao.data_atualizacao : '');

      document.getElementById('detNome').textContent      = (data.paciente && data.paciente.nome) ? data.paciente.nome : 'Paciente';
      document.getElementById('detTelefone').textContent  = numero;
      document.getElementById('detEtapa').textContent     = (data.sessao && data.sessao.etapa) ? data.sessao.etapa : '-';
      document.getElementById('detAtualizado').textContent= formatarData(data.sessao && data.sessao.data_atualizacao ? data.sessao.data_atualizacao : '');

      montarListaTags(data.tags||[], data.doLead||[]);
      renderChipsTags(data.tags||[], data.doLead||[]);
      renderHistorico(data.historico||[]);
      renderNotas(data.notas||[]);
      carregarAgendamentos(numero);

      document.getElementById('btnSalvarLeadTags').onclick = function(){ salvarLeadTags(leadAtualNumero); };
      document.getElementById('btnSalvarNota').onclick     = function(){ salvarNota(leadAtualNumero); };
      document.getElementById('btnAgendar').onclick        = function(){ agendarMensagem(leadAtualNumero); };
      carregarArquivos(numero);
      var upBtn = document.getElementById('btnUploadArq');
      if (upBtn) upBtn.onclick = function(){ uploadArquivo(leadAtualNumero); };

      abrirModal('modalLead');
      setLeadAvatar(document.getElementById('detNome').textContent || numero);
    })
    .catch(function(){ toast('Erro ao carregar detalhes','error'); });
}

function recarregarTagsDoLead(){ if(!leadAtualNumero) return; abrirModalLead(leadAtualNumero, cardAtualEl); }

// Tags (modal)
function montarListaTags(tags, doLead){
  var wrap=document.getElementById('listaTags'); wrap.innerHTML='';
  var i; for(i=0;i<tags.length;i++){
    var tag=tags[i]; var checked = doLead.indexOf(parseInt(tag.id,10))>-1 ? 'checked' : '';
    wrap.insertAdjacentHTML('beforeend',
      '<label class="flex items-center gap-3 border rounded-lg p-2">'+
        '<input type="checkbox" class="rounded" value="'+tag.id+'" '+checked+'>'+
        '<span class="inline-flex items-center gap-2">'+
          '<span class="w-3 h-3 rounded-full" style="background:'+(tag.cor||'#3b82f6')+'"></span>'+
          '<span>'+esc(tag.nome)+'</span>'+
        '</span>'+
      '</label>'
    );
  }
}
function getTagsSelecionadas(){
  var els=document.querySelectorAll('#listaTags input[type="checkbox"]:checked');
  var out=[]; for(var i=0;i<els.length;i++){ out.push(els[i].value); } return out;
}
function salvarLeadTags(numero){
  var ids=getTagsSelecionadas();
  var fd=new FormData(); fd.append(csrfName, csrfHash); for(var i=0;i<ids.length;i++){ fd.append('tags[]', ids[i]); }
  fetch('<?= base_url("kanban/lead-tags") ?>/'+encodeURIComponent(numero), { method:'POST', body: fd })
    .then(function(r){ if(!r.ok) throw new Error(); return r.json().catch(function(){return {ok:true};}); })
    .then(function(){ toast('Tags salvas!','success'); return fetch('<?= base_url("kanban/lead-detalhes") ?>/'+encodeURIComponent(numero)); })
    .then(function(r){ return r.json(); })
    .then(function(fresh){ renderChipsTags(fresh.tags||[], fresh.doLead||[]); return renderChipsNoCard(numero, cardAtualEl); })
    .catch(function(){ toast('Erro ao salvar tags','error'); });
}
function renderChipsTags(tags, doLead){
  var holder=document.getElementById('chipsTags'); holder.innerHTML='';
  var mapa={}, i; for(i=0;i<tags.length;i++){ mapa[tags[i].id]=tags[i]; }
  for(i=0;i<doLead.length;i++){
    var id=doLead[i]; var tag=mapa[id]; if(!tag) continue;
    var chip=document.createElement('span');
    chip.className='px-2 py-0.5 rounded text-xs';
    chip.style.background = tag.cor || '#3b82f6';
    chip.style.color = contrastOn(tag.cor || '#3b82f6');
    chip.textContent=tag.nome;
    holder.appendChild(chip);
  }
}
function renderChipsNoCard(numero, cardEl){
  if(!cardEl) return Promise.resolve();
  var holder=cardEl.querySelector('[data-tags-holder]'); if(!holder) return Promise.resolve();
  holder.innerHTML='';
  return fetch('<?= base_url("kanban/lead-tags") ?>/'+encodeURIComponent(numero))
    .then(function(r){ if(!r.ok) throw new Error(); return r.json(); })
    .then(function(data){
      var mapa={}, i; for(i=0;i<(data.tags||[]).length;i++){ mapa[data.tags[i].id]=data.tags[i]; }
      for(i=0;i<(data.doLead||[]).length;i++){
        var id=data.doLead[i]; var tag=mapa[id]; if(!tag) continue;
        var chip=document.createElement('span');
        chip.className='px-2 py-0.5 rounded text-[10px]';
        chip.style.background = tag.cor || '#3b82f6';
        chip.style.color = contrastOn(tag.cor || '#3b82f6');
        chip.textContent=tag.nome;
        holder.appendChild(chip);
      }
    })["catch"](function(){});
}
function carregarTagsNosCards(){
  var cards=document.querySelectorAll('.kanban-card');
  var chain = Promise.resolve();
  for(var i=0;i<cards.length;i++){
    (function(card){
      chain = chain.then(function(){
        var numero=card.getAttribute('data-lead-id');
        return renderChipsNoCard(numero, card);
      });
    })(cards[i]);
  }
  return chain;
}

// Histórico
function renderHistorico(hist){
  var box=document.getElementById('historicoBox'); box.innerHTML='';
  for(var i=0;i<hist.length;i++){
    var m=hist[i]; var isUser = m.role==='user';
    var side   = isUser ? 'justify-start' : 'justify-end';
    var cls    = isUser ? 'bg-white border border-gray-200 text-gray-800' : 'bg-slate-900 text-white';
    box.insertAdjacentHTML('beforeend',
      '<div class="flex '+side+'">'+
        '<div class="max-w-[80%] rounded-2xl px-3 py-2 '+cls+'">'+
          '<div class="whitespace-pre-wrap break-words text-[13px]">'+esc(m.content||'')+'</div>'+
        '</div>'+
      '</div>'
    );
  }
  box.scrollTop = box.scrollHeight;
}

// Notas
function renderNotas(notas){
  var ul=document.getElementById('listaNotas'); ul.innerHTML='';
  if(!notas || !notas.length){ ul.innerHTML='<li class="text-gray-500">Sem observações.</li>'; return; }
  for(var i=0;i<notas.length;i++){
    var n=notas[i]; var quando = formatarData(n.criado_em || '');
    ul.insertAdjacentHTML('beforeend',
      '<li class="border rounded-lg p-2">'+
        '<div class="text-xs text-gray-500 mb-1">'+quando+' • '+esc(n.autor||'atendente')+'</div>'+
        '<div class="text-sm whitespace-pre-wrap break-words">'+esc(n.texto||'')+'</div>'+
      '</li>'
    );
  }
}
function salvarNota(numero){
  var txtEl=document.getElementById('notaTexto'); var texto=(txtEl.value||'').trim(); if(!texto) return;
  var fd=new FormData(); fd.append(csrfName, csrfHash); fd.append('texto', texto);
  fetch('<?= base_url("kanban/lead-nota") ?>/'+encodeURIComponent(numero), { method:'POST', body: fd })
    .then(function(r){ if(!r.ok) throw new Error(); return r.json().catch(function(){return {ok:true};}); })
    .then(function(){ txtEl.value=''; toast('Observação salva!','success'); return fetch('<?= base_url("kanban/lead-detalhes") ?>/'+encodeURIComponent(numero)); })
    .then(function(r){ return r.json(); })
    .then(function(fresh){ renderNotas(fresh.notas||[]); })
    .catch(function(){ toast('Erro ao salvar observação','error'); });
}

// Agendamentos
function carregarAgendamentos(numero){
  fetch('<?= base_url("kanban/lead-schedules") ?>/'+encodeURIComponent(numero))
    .then(function(r){ if(!r.ok) throw new Error(); return r.json(); })
    .then(function(data){ renderAgendamentos(data.agendamentos||[]); })
    .catch(function(){});
}
function renderAgendamentos(list){
  var wrap=document.getElementById('listaAgendamentos'); wrap.innerHTML='';
  if(!list.length){ wrap.innerHTML='<div class="text-gray-500">Nenhum agendamento.</div>'; return; }
  for(var i=0;i<list.length;i++){
    var item=list[i]; var dt=formatarData(item.enviar_em); var pendente=item.status==='pendente';
    wrap.insertAdjacentHTML('beforeend',
      '<div class="border rounded-lg p-2 flex items-center justify-between">'+
        '<div class="pr-3">'+
          '<div class="text-gray-800">'+esc(item.mensagem||'')+'</div>'+
          '<div class="text-xs text-gray-500 mt-1">'+dt+' • '+item.status+'</div>'+
        '</div>'+
        '<div>'+(pendente?('<button class="px-3 py-1 rounded bg-red-100 text-red-700" onclick="cancelarAgendamento('+item.id+')">Cancelar</button>'):'')+'</div>'+
      '</div>'
    );
  }
}
function agendarMensagem(numero){
  var msg=document.getElementById('agMsg').value.trim();
  var data=document.getElementById('agData').value;
  var hora=document.getElementById('agHora').value;
  if(!msg||!data||!hora){ toast('Preencha mensagem, data e hora.','error'); return; }
  var fd=new FormData(); fd.append(csrfName, csrfHash); fd.append('mensagem', msg); fd.append('data', data); fd.append('hora', hora);
  fetch('<?= base_url("kanban/lead-schedules") ?>/'+encodeURIComponent(numero), { method:'POST', body: fd })
    .then(function(r){ if(!r.ok) return r.json().then(function(j){ throw new Error(j.msg||'Erro'); }); return r.json(); })
    .then(function(){ document.getElementById('agMsg').value=''; toast('Mensagem agendada!','success'); carregarAgendamentos(numero); })
    .catch(function(e){ toast(e.message||'Erro ao agendar','error'); });
}
function cancelarAgendamento(id){
  var fd=new FormData(); fd.append(csrfName, csrfHash);
  fetch('<?= base_url("kanban/lead-schedules/cancelar") ?>/'+id, { method:'POST', body: fd })
    .then(function(r){ if(!r.ok) throw new Error(); return r.json(); })
    .then(function(){ toast('Agendamento cancelado.','success'); carregarAgendamentos(leadAtualNumero); })
    .catch(function(){ toast('Erro ao cancelar.','error'); });
}

/* ===================== CONFIGURAR COLUNA (MODAL + BACKEND) ===================== */
var cfgCache = {};          // { etapaKey: cfg }
var etapaEmEdicao = null;

function abrirConfigColuna(key, title){
  etapaEmEdicao = key;
  document.getElementById('cfgEtapaKey').textContent = '('+ key +')';

  // limpa form
  preencherFormConfig({});

  // busca config do backend
  fetch('<?= base_url("kanban/etapa-config") ?>/' + encodeURIComponent(key))
    .then(function(r){ if(!r.ok) throw new Error(); return r.json(); })
    .then(function(cfg){
      cfgCache[key] = cfg || {};
      preencherFormConfig(cfgCache[key]);
      abrirModal('modalConfigColuna');
    })
    .catch(function(){
      abrirModal('modalConfigColuna');
    });
}

function preencherFormConfig(cfg){
  cfg = cfg || {};
  document.getElementById('cfgTitulo').value   = cfg.titulo || '';
  document.getElementById('cfgWip').value      = (typeof cfg.wip === 'number' ? String(cfg.wip) : '');
  document.getElementById('cfgSLA').value      = (typeof cfg.destacar_horas === 'number' ? String(cfg.destacar_horas) : '');
  document.getElementById('cfgCor').value      = cfg.cor_borda || '#0ea5e9';
  document.getElementById('cfgBloqIA').value   = (cfg.bloquear_ia ? '1' : '0');
  document.getElementById('cfgTemplate').value = cfg.template || '';
}
function coletarFormConfig(){
  var wip = parseInt(document.getElementById('cfgWip').value,10);
  var sla = parseInt(document.getElementById('cfgSLA').value,10);
  return {
    titulo: (document.getElementById('cfgTitulo').value||'').trim(),
    wip: isNaN(wip)?0:wip,
    destacar_horas: isNaN(sla)?0:sla,
    cor_borda: document.getElementById('cfgCor').value || '#0ea5e9',
    bloquear_ia: document.getElementById('cfgBloqIA').value === '1',
    template: (document.getElementById('cfgTemplate').value||'').trim()
  };
}
function salvarConfigColuna(){
  if (!etapaEmEdicao) return;
  var cfg = coletarFormConfig();
  var fd = new FormData();
  fd.append('json', JSON.stringify(cfg));
  fd.append(csrfName, csrfHash);

  fetch('<?= base_url("kanban/etapa-config") ?>/' + encodeURIComponent(etapaEmEdicao), { method:'POST', body: fd })
    .then(function(r){ if(!r.ok) throw new Error(); return r.json(); })
    .then(function(){
      cfgCache[etapaEmEdicao] = cfg;
      aplicarConfigVisual(etapaEmEdicao, cfg);
      aplicarSLAEmCards(); // reavalia SLA
      toast('Configuração salva!','success');
      fecharModal('modalConfigColuna');
    })
    .catch(function(){ toast('Erro ao salvar configuração','error'); });
}
function testarConfigColuna(){
  if (!etapaEmEdicao) return;
  var cfg = coletarFormConfig();
  var fd = new FormData();
  fd.append('json', JSON.stringify(cfg));
  fd.append(csrfName, csrfHash);

  fetch('<?= base_url("kanban/etapa-config/teste") ?>/' + encodeURIComponent(etapaEmEdicao), { method:'POST', body: fd })
    .then(function(r){ if(!r.ok) throw new Error(); return r.json(); })
    .then(function(resp){ toast((resp && resp.msg) ? resp.msg : 'Template recebido para teste.','success'); })
    .catch(function(){ toast('Falha no teste da configuração','error'); });
}

function aplicarConfigVisual(etapaKey, cfg){
  var col = document.querySelector('.kanban-column[data-etapa-key="'+ etapaKey +'"]');
  if (!col) return;

  // Título
  var titleEl = col.querySelector('.sticky .font-semibold');
  if (titleEl) {
    var fallback = titleEl.getAttribute('title') || titleEl.textContent || '';
    var nome = (cfg.titulo && cfg.titulo.trim()) ? cfg.titulo.trim() : fallback;
    titleEl.textContent = nome;
    titleEl.setAttribute('title', nome);
  }

  // Borda/anel
  var color = cfg.cor_borda || '';
  if (color) {
    col.style.boxShadow = 'inset 0 0 0 2px ' + color + '22';
    var colBody = col.querySelector('.cards-container');
    if (colBody) colBody.style.borderColor = color + '55';
  }

  // WIP header highlight
  var wip = (typeof cfg.wip === 'number' ? cfg.wip : 0);
  var body = col.querySelector('.cards-container');
  var header = col.querySelector('.sticky');
  if (body && header) {
    var count = body.querySelectorAll('.kanban-card:not([style*="display: none"])').length;
    header.style.background = (wip > 0 && count > wip) ? 'rgba(250,204,21,0.15)' : 'rgba(255,255,255,0.9)';
  }
}

function aplicarSLAEmCards(){
  var cols = document.querySelectorAll('.kanban-column[data-etapa-key]');
  for (var i=0;i<cols.length;i++){
    var key  = cols[i].getAttribute('data-etapa-key');
    var cfg  = cfgCache[key] || {};
    var slaH = parseInt(cfg.destacar_horas || 0, 10);
    var wrap = cols[i].querySelector('.cards-container');
    if (!wrap || !slaH) continue;
    var cards = wrap.querySelectorAll('.kanban-card');
    for (var j=0;j<cards.length;j++){
      var card = cards[j];
      var updatedAt = card.getAttribute('data-updated-at') || '';
      if (!updatedAt) { card.style.outline=''; card.style.background=''; continue; }
      var ts = Date.parse(String(updatedAt).replace(' ','T'));
      if (!isNaN(ts)) {
        var horas = (Date.now() - ts)/(1000*60*60);
        if (horas >= slaH) {
          card.style.outline = '2px solid #f59e0b'; // âmbar
          card.style.background = '#fff7ed';
        } else {
          card.style.outline = '';
          card.style.background = '';
        }
      }
    }
  }
}

function carregarConfigsEtapas(){
  var cols = document.querySelectorAll('.kanban-column[data-etapa-key]');
  for (var i=0;i<cols.length;i++){
    (function(col){
      var key = col.getAttribute('data-etapa-key');
      fetch('<?= base_url("kanban/etapa-config") ?>/' + encodeURIComponent(key))
        .then(function(r){ if(!r.ok) throw new Error(); return r.json(); })
        .then(function(cfg){
          cfgCache[key] = cfg || {};
          aplicarConfigVisual(key, cfgCache[key]);
        })
        .catch(function(){ /* sem config ainda */ });
    })(cols[i]);
  }
  // aplica SLA após breve atraso (garante DOM pronto)
  setTimeout(aplicarSLAEmCards, 120);
}
</script>
</body>
</html>
