<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>CRM Assistente • Conversas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          borderRadius: { 'xl': '0.75rem', '2xl': '1rem' },
          boxShadow: { 'soft': '0 8px 24px rgba(2,6,23,0.06)' },
          colors: {
            brand: { DEFAULT: '#111827' },
            ocean: { 600: '#2563eb' },
            slatex: { 25:'#fcfcfd' }
          }
        }
      }
    }
  </script>
  <style>
    .scroll-smooth { scroll-behavior: smooth; }
    .ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fade-enter { opacity: 0; transform: translateY(6px); }
    .fade-enter-active { opacity: 1; transform: translateY(0); transition: all .18s ease; }
    .tail-l:after{content:"";position:absolute;left:-6px;bottom:8px;border-width:8px;border-style:solid;border-color:transparent #fff transparent transparent;filter:drop-shadow(0 1px 0 rgba(0,0,0,.05))}
    .tail-r-blue:after{content:"";position:absolute;right:-6px;bottom:8px;border-width:8px;border-style:solid;border-color:transparent transparent transparent #2563eb}
    .tail-r-gray:after{content:"";position:absolute;right:-6px;bottom:8px;border-width:8px;border-style:solid;border-color:transparent transparent transparent #111827}
    .modal-card{opacity:.0; transform:translateY(6px) scale(.985); transition:opacity .18s ease, transform .18s ease;}
    #fileModal.flex .modal-card{opacity:1; transform:none;}
  </style>
</head>
<body class="bg-slate-50 text-slate-800">
  <div class="min-h-screen flex">

    <?= view('sidebar') ?>

    <main class="flex-1 flex flex-col">
      <!-- Header -->
      <header class="p-4 bg-gradient-to-r from-slate-900 to-ocean-600 text-white">
        <div class="flex items-center justify-between gap-3">
          <div class="min-w-0">
            <h1 class="text-lg font-semibold ellipsis">Conversas</h1>
            <p class="text-white/70 text-xs">Atenda os pacientes em tempo real</p>
          </div>
          <div class="flex items-center gap-2"></div>
        </div>
      </header>

      <section class="flex-1 flex">
        <!-- Lista de contatos -->
        <aside class="w-80 bg-white border-r border-slate-200 flex flex-col">
          <div class="p-4 border-b border-slate-200">
            <h2 class="font-semibold text-lg">Contatos</h2>
            <div class="relative mt-3">
              <input id="search" type="text" placeholder="/ Buscar por contato..." class="w-full rounded-xl border border-slate-200 bg-white px-10 py-2 focus:outline-none focus:ring-2 focus:ring-ocean-600"/>
              <span class="pointer-events-none absolute left-3 top-2.5 text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.9 14.32a8 8 0 111.414-1.414l4.387 4.386a1 1 0 01-1.414 1.415l-4.387-4.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd"/></svg>
              </span>
            </div>
          </div>
          <ul id="contactList" class="flex-1 overflow-y-auto divide-y divide-slate-100"></ul>
        </aside>

        <!-- Janela de chat -->
        <section class="flex-1 flex flex-col">
          <div class="p-4 bg-white border-b flex items-center justify-between">
            <div class="min-w-0">
              <h3 id="chatTitle" class="font-semibold text-lg ellipsis">Selecione um contato</h3>
              <p id="chatSub" class="text-sm text-slate-500 ellipsis"></p>
            </div>
            <div class="flex items-center gap-2">
              <span id="etapaBadge" class="text-xs px-2 py-1 rounded bg-slate-100 text-slate-600"></span>
              <button id="toggleInfo" class="px-3 py-1 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-sm">Detalhes</button>
            </div>
          </div>

          <!-- Mensagens -->
          <div id="messages" class="flex-1 p-4 overflow-y-auto space-y-3 bg-slatex-25 scroll-smooth"></div>

          <!-- Barra de envio -->
          <form id="sendForm" class="p-4 bg-white border-t flex items-end gap-3" onsubmit="return false;">
            <div class="flex-1">
              <textarea id="msgInput" rows="1" placeholder="Digite uma mensagem..." class="w-full rounded-xl border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-ocean-600 resize-none disabled:opacity-50" disabled></textarea>
              <div id="typing" class="hidden mt-1 text-xs text-slate-400">Digitando…</div>
            </div>
            <button id="sendBtn" class="px-5 py-2 rounded-xl bg-ocean-600 text-white font-medium disabled:opacity-50" disabled>Enviar</button>
          </form>
        </section>

        <!-- Painel lateral: fechado por padrão -->
        <aside id="infoPanel" class="w-80 bg-white border-l border-slate-200 flex-col hidden">
          <div class="p-4 border-b">
            <div class="flex items-center justify-between">
              <h3 class="font-semibold">Detalhes</h3>
              <button id="closeInfo" class="px-2 py-1 rounded border text-xs">Fechar</button>
            </div>
            <p id="infoSub" class="text-xs text-slate-500 ellipsis mt-1"></p>
            <div class="mt-3 -mb-px flex gap-4 text-sm">
              <button class="pb-2 border-b-2 border-slate-900 text-slate-900" data-info-tab="obs">Observações</button>
              <button class="pb-2 border-b-2 border-transparent text-slate-500 hover:text-slate-700" data-info-tab="files">Arquivos</button>
            </div>
          </div>

          <!-- Observações -->
          <div id="panel-obs" class="flex-1 overflow-y-auto p-3 space-y-2">
            <ul id="obsList" class="space-y-2"></ul>
            <div id="obsEmpty" class="hidden p-3 text-sm text-slate-500">Nenhuma observação.</div>
          </div>

          <!-- Arquivos -->
          <div id="panel-files" class="hidden flex-1 overflow-y-auto p-3 space-y-2">
            <div id="filesEmpty" class="hidden p-3 text-sm text-slate-500">Nenhum arquivo enviado.</div>
            <ul id="fileList" class="space-y-2"></ul>
          </div>
        </aside>
      </section>
    </main>
  </div>

  <!-- Toasts -->
  <div id="toasts" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

  <!-- Modal de Preview de Arquivo -->
  <div id="fileModal" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="absolute inset-0 bg-slate-900/60" onclick="closeFileModal()"></div>
    <div class="relative modal-card bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 w-[min(92vw,1000px)] max-h-[92vh] overflow-hidden">
      <div class="flex items-center justify-between px-5 py-3 border-b">
        <div class="min-w-0">
          <h4 id="fileTitle" class="font-semibold ellipsis">Arquivo</h4>
          <p id="fileMeta" class="text-xs text-slate-500 ellipsis"></p>
        </div>
        <div class="flex items-center gap-2">
          <a id="fileDownload" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm" target="_blank" rel="noopener">Baixar</a>
          <button class="px-3 py-1.5 rounded-lg border hover:bg-slate-50 text-sm" onclick="closeFileModal()">Fechar ✕</button>
        </div>
      </div>
      <div id="fileViewer" class="p-4 overflow-auto" style="max-height: calc(92vh - 64px);"></div>
    </div>
  </div>

<script>
let contatos = [];
let numeroAtual = null;
let polling = null;
let historicoCache = '';
let isSending = false;

/* ========= Helpers de UI ========= */
function toast(msg, type='default'){
  const wrap = document.getElementById('toasts');
  const el = document.createElement('div');
  el.className = `fade-enter px-4 py-2 rounded-xl shadow text-sm text-white ${type==='error'?'bg-red-600':type==='success'?'bg-emerald-600':'bg-slate-900'}`;
  el.textContent = msg;
  wrap.appendChild(el);
  requestAnimationFrame(()=> el.classList.add('fade-enter-active'));
  setTimeout(()=> el.remove(), 2400);
}
function formatTelefone(t){ if(!t) return ''; return `+${t}`; }
function esc(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function isSameDay(a,b){ const A=new Date(a), B=new Date(b); return A.toDateString()===B.toDateString(); }
function dayLabel(d){ const dt=new Date(d); return dt.toLocaleDateString(undefined,{weekday:'short', day:'2-digit', month:'short'}); }
function fmtDateTime(iso){ if(!iso) return ''; const d=new Date(String(iso).replace(' ','T')); if(isNaN(d)) return iso; return d.toLocaleString(); }

function msgBubble(role, content, created_at){
  const isUser   = role === 'user';
  const isIA     = role === 'assistant';
  const isHumano = role === 'humano';

  let align  = 'justify-start';
  let bubble = 'bg-white border border-slate-200 text-slate-800 tail-l';
  if (isIA) {
    align  = 'justify-end';
    bubble = 'bg-ocean-600 text-white tail-r-blue';
  } else if (isHumano) {
    align  = 'justify-end';
    bubble = 'bg-slate-900 text-white tail-r-gray';
  }

  const time = created_at ? `<div class="mt-1 text-[10px] opacity-70">${new Date(created_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}</div>` : '';
  return `
    <div class="flex ${align}">
      <div class="relative max-w-[72%] rounded-2xl px-4 py-2 ${bubble} shadow-sm whitespace-pre-wrap break-words">
        ${esc(content)}
        ${time}
      </div>
    </div>
  `;
}
function dateSeparator(label){
  return `<div class="my-4 flex items-center gap-3"><div class="h-px flex-1 bg-slate-200"></div><span class="text-xs text-slate-500">${label}</span><div class="h-px flex-1 bg-slate-200"></div></div>`;
}
function renderHistorico(h){
  const parts = [];
  let lastDate = null;
  (h||[]).forEach(m => {
    const when = m.created_at || Date.now();
    if(!lastDate || !isSameDay(lastDate, when)){
      parts.push(dateSeparator(dayLabel(when)));
      lastDate = when;
    }
    parts.push(msgBubble(m.role, m.content, when));
  });
  return parts.join('');
}

/* ========= Contatos ========= */
async function loadContacts(){
  try{
    drawContactsSkeleton();
    const res = await fetch('/chat/contacts');
    const data = await res.json();
    contatos = data || [];
    renderContacts(contatos);
  }catch(e){ toast('Erro ao carregar contatos','error'); }
}
function drawContactsSkeleton(){
  const ul = document.getElementById('contactList');
  ul.innerHTML = '';
  for(let i=0;i<8;i++){
    ul.insertAdjacentHTML('beforeend', `
      <li class="p-4">
        <div class="animate-pulse flex items-center gap-3">
          <div class="h-10 w-10 rounded-full bg-slate-200"></div>
          <div class="flex-1">
            <div class="h-3 w-32 rounded bg-slate-200 mb-2"></div>
            <div class="h-3 w-24 rounded bg-slate-200"></div>
          </div>
        </div>
      </li>`);
  }
}
function renderContacts(list){
  const ul = document.getElementById('contactList');
  const q = document.getElementById('search').value?.toLowerCase() || '';
  const filtered = (list||[]).filter(c => (c.nome?.toLowerCase()||'').includes(q) || (c.telefone||'').includes(q));

  if(!filtered.length){
    ul.innerHTML = `<li class="p-6 text-center text-slate-500">Nenhum contato encontrado.</li>`;
    return;
  }
  ul.innerHTML = filtered.map(c => {
    const nome = esc(c.nome||'Paciente');
    const tel  = esc(c.telefone||'');
    const avatar = nome.charAt(0).toUpperCase();
    const unread = (c.unread_count||0)>0 ? `<span class="ml-auto inline-flex items-center justify-center h-5 min-w-[20px] rounded-full bg-ocean-600 text-white text-[10px] px-1">${c.unread_count}</span>` : '';
    const subt = c.ultimo_contato ? new Date(c.ultimo_contato).toLocaleString() : formatTelefone(tel);
    return `
      <li>
        <button class="w-full text-left px-4 py-3 hover:bg-blue-50 transition flex items-center gap-3" onclick="openChat('${tel}', '${nome.replace(/'/g,"\\'")}')">
          <div class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center text-[12px] font-medium text-slate-600">${avatar}</div>
          <div class="min-w-0">
            <div class="font-medium ellipsis">${nome}</div>
            <div class="text-xs text-slate-500 ellipsis">${subt}</div>
          </div>
          ${unread}
        </button>
      </li>`;
  }).join('');
}
// Debounce busca
let searchTimer=null;
document.getElementById('search').addEventListener('input', () => {
  clearTimeout(searchTimer); searchTimer=setTimeout(()=>renderContacts(contatos), 180);
});
// Atalho: '/' foca busca
const searchInput = document.getElementById('search');
document.addEventListener('keydown', (e)=>{ if(e.key==='/' && document.activeElement!==searchInput){ e.preventDefault(); searchInput.focus(); } });

/* ========= Abrir conversa ========= */
async function openChat(numero, nome){
  numeroAtual = numero;
  document.getElementById('chatTitle').textContent = nome || 'Paciente';
  document.getElementById('chatSub').textContent   = formatTelefone(numero);
  document.getElementById('infoSub').textContent   = `${nome||'Paciente'} • ${formatTelefone(numero)}`;
  document.getElementById('msgInput').disabled = false;
  document.getElementById('sendBtn').disabled = false;

  if (polling) clearInterval(polling);

  await refreshMessages();
  await Promise.all([loadNotes(numero), loadFiles(numero)]);
  polling = setInterval(refreshMessages, 3000);
}

/* ========= Mensagens ========= */
async function refreshMessages(){
  if (!numeroAtual) return;
  try{
    const res = await fetch(`/chat/messages/${numeroAtual}`);
    const data = await res.json();
    const msgsEl = document.getElementById('messages');
    const etapaBadge = document.getElementById('etapaBadge');

    etapaBadge.textContent = data.etapa ? `Etapa: ${data.etapa}` : '';

    const html = renderHistorico(data.historico || []);
    if (html !== historicoCache) {
      msgsEl.innerHTML = html;
      msgsEl.scrollTop = msgsEl.scrollHeight;
      historicoCache = html;
    }
  }catch(e){ console.error(e); }
}

// Auto-expand do textarea
const ta = document.getElementById('msgInput');
function autoGrow(){ this.style.height = 'auto'; this.style.height = (this.scrollHeight)+ 'px'; }
ta?.addEventListener('input', autoGrow);

document.getElementById('sendBtn').addEventListener('click', sendMessage);
ta?.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});
async function sendMessage(){
  const input = document.getElementById('msgInput');
  const text = input.value.trim();
  if (!text || !numeroAtual || isSending) return;
  isSending = true;

  // Otimista: HUMANO
  const msgsEl = document.getElementById('messages');
  msgsEl.insertAdjacentHTML('beforeend', msgBubble('humano', text, Date.now()));
  msgsEl.scrollTop = msgsEl.scrollHeight;
  input.value = '';
  autoGrow.call(input);

  const formData = new FormData();
  formData.append('numero', numeroAtual);
  formData.append('mensagem', text);

  try{
    const res = await fetch('/chat/send', { method: 'POST', body: formData });
    if (!res.ok) {
      toast('Falha ao enviar','error');
    } else {
      setTimeout(refreshMessages, 600);
    }
  }catch(e){ toast('Erro de rede','error'); }
  finally{ isSending = false; }
}

/* ========= Painel lateral: toggle (fechado por padrão) ========= */
const infoPanel = document.getElementById('infoPanel');
const toggleInfo = document.getElementById('toggleInfo');
const closeInfo  = document.getElementById('closeInfo');

toggleInfo?.addEventListener('click', ()=> showInfoPanel());
closeInfo?.addEventListener('click', ()=> showInfoPanel(false));

function showInfoPanel(forceOpen){
  if (forceOpen === true){ infoPanel.classList.remove('hidden'); infoPanel.classList.add('flex'); return; }
  if (forceOpen === false){ infoPanel.classList.add('hidden'); infoPanel.classList.remove('flex'); return; }
  const isHidden = infoPanel.classList.contains('hidden');
  if (isHidden){ infoPanel.classList.remove('hidden'); infoPanel.classList.add('flex'); }
  else { infoPanel.classList.add('hidden'); infoPanel.classList.remove('flex'); }
}

/* ========= Tabs do painel ========= */
const tabBtns   = document.querySelectorAll('[data-info-tab]');
const panelObs  = document.getElementById('panel-obs');
const panelFiles= document.getElementById('panel-files');
tabBtns.forEach(btn => btn.addEventListener('click', ()=>{
  tabBtns.forEach(b=>{ b.classList.remove('border-slate-900','text-slate-900'); b.classList.add('border-transparent','text-slate-500'); });
  btn.classList.add('border-slate-900','text-slate-900');
  const tab = btn.getAttribute('data-info-tab');
  panelObs.classList.toggle('hidden', tab!=='obs');
  panelFiles.classList.toggle('hidden', tab!=='files');
}));

/* ===== Observações (somente leitura aqui) ===== */
async function loadNotes(numero){
  try{
    const res = await fetch(`/kanban/lead-detalhes/${encodeURIComponent(numero)}`);
    if (!res.ok) throw new Error();
    const data = await res.json();
    renderNotes(data.notas || []);
  }catch(e){
    renderNotes([]);
  }
}
function renderNotes(notas){
  const ul = document.getElementById('obsList');
  const empty = document.getElementById('obsEmpty');
  ul.innerHTML = '';
  if (!notas.length){
    empty.classList.remove('hidden'); return;
  }
  empty.classList.add('hidden');
  notas.forEach(n=>{
    ul.insertAdjacentHTML('beforeend', `
      <li class="border rounded-lg p-2 bg-white">
        <div class="text-[11px] text-slate-500 mb-1">${fmtDateTime(n.criado_em||'')} • ${esc(n.autor||'atendente')}</div>
        <div class="text-sm whitespace-pre-wrap break-words">${esc(n.texto||'')}</div>
      </li>
    `);
  });
}

/* ===== Arquivos ===== */
async function loadFiles(numero){
  try{
    const res = await fetch(`/kanban/lead-files/${encodeURIComponent(numero)}`);
    if (!res.ok) throw new Error();
    const data = await res.json();
    renderFiles(data.arquivos || []);
  }catch(e){
    renderFiles([]);
  }
}
function renderFiles(list){
  const ul = document.getElementById('fileList');
  const empty = document.getElementById('filesEmpty');
  ul.innerHTML = '';
  if (!list.length){ empty.classList.remove('hidden'); return; }
  empty.classList.add('hidden');

  list.forEach(a=>{
    const dt = fmtDateTime(a.uploaded_at);
    const size = a.tamanho ? `${Math.round(a.tamanho/1024)} KB` : '';
    const proc = a.procedimento ? ` • Proc.: ${esc(a.procedimento)}` : '';
    const val  = (a.valor!==null && a.valor!==undefined && a.valor!=='') ? ` • Valor: R$ ${a.valor}` : '';
    ul.insertAdjacentHTML('beforeend', `
      <li>
        <button class="w-full text-left border rounded-lg p-2 hover:bg-slate-50 flex items-center gap-3"
                onclick='openFileModal(${JSON.stringify(a)})'>
          <div class="h-9 w-9 rounded bg-slate-100 flex items-center justify-center">
            ${iconForFile(a.nome_original || '')}
          </div>
          <div class="min-w-0">
            <div class="font-medium ellipsis">${esc(a.nome_original||'arquivo')}</div>
            <div class="text-[11px] text-slate-500 ellipsis">${dt}${size?` • ${size}`:''}${proc}${val}</div>
          </div>
        </button>
      </li>
    `);
  });
}
function iconForFile(name){
  const n = (name||'').toLowerCase();
  const img = n.match(/\.(png|jpe?g|gif|webp)$/);
  const pdf = n.endsWith('.pdf');
  const doc = n.match(/\.(docx?|xlsx?|pptx?)$/);
  if (img) return `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-500" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V9.414a2 2 0 00-.586-1.414l-4.414-4.414A2 2 0 0011.586 3H4z"/><path d="M8 13l2-2 2 2 2-2 2 2"/></svg>`;
  if (pdf) return `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-600" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2h7l5 5v13a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z"/><text x="9" y="18" font-size="7" fill="white">PDF</text></svg>`;
  if (doc) return `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2h7l5 5v13a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>`;
  return `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-500" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2V9l-6-7z"/></svg>`;
}

/* ===== Modal de arquivo (preview) ===== */
function openFileModal(file){
  const modal = document.getElementById('fileModal');
  const viewer = document.getElementById('fileViewer');
  const title = document.getElementById('fileTitle');
  const meta  = document.getElementById('fileMeta');
  const dl    = document.getElementById('fileDownload');

  title.textContent = file.nome_original || 'Arquivo';
  meta.textContent  = `${fmtDateTime(file.uploaded_at||'')} • ${file.tamanho? (Math.round(file.tamanho/1024)+' KB') : ''}`;
  dl.href = file.url_download || '#';

  const url = file.url_download || '';
  const name = (file.nome_original||'').toLowerCase();

  viewer.innerHTML = '';
  if (name.match(/\.(png|jpe?g|gif|webp)$/)){
    viewer.innerHTML = `<img src="${esc(url)}" alt="" class="max-w-[90vw] max-h-[75vh] object-contain rounded-lg">`;
  } else if (name.endsWith('.pdf')){
    viewer.innerHTML = `
      <iframe src="${esc(url)}" class="w-[90vw] max-w-[1000px] h-[75vh] rounded-lg border" referrerpolicy="no-referrer"></iframe>
      <div class="text-[11px] text-slate-500 mt-2">Se o preview não carregar, use o botão "Baixar".</div>`;
  } else {
    viewer.innerHTML = `
      <div class="p-4 text-sm text-slate-600">
        <p>Este tipo de arquivo não possui preview. Você pode <a class="text-blue-600 underline" href="${esc(url)}" target="_blank" rel="noopener">baixar aqui</a>.</p>
      </div>`;
  }

  modal.classList.remove('hidden'); modal.classList.add('flex');
}
function closeFileModal(){
  const modal = document.getElementById('fileModal');
  modal.classList.add('hidden'); modal.classList.remove('flex');
}

/* init */
loadContacts();
</script>
</body>
</html>
