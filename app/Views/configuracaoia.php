<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Chat em Tempo Real • Teste IA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
    .ellipsis{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .fade-enter{opacity:0;transform:translateY(6px)}
    .fade-enter-active{opacity:1;transform:translateY(0);transition:all .18s ease}
    .tail-l:after{content:"";position:absolute;left:-6px;bottom:8px;border:8px solid transparent;border-right-color:#fff;filter:drop-shadow(0 1px 0 rgba(0,0,0,.05))}
    .tail-r:after{content:"";position:absolute;right:-6px;bottom:8px;border:8px solid transparent;border-left-color:#2563eb}
    .scroll-smooth{scroll-behavior:smooth}
  </style>
</head>
<body class="h-screen w-screen bg-slate-50 text-slate-800">
  <div class="h-full flex">
    <?= view('sidebar') ?>

    <div class="flex-1 flex flex-col h-full">
      <!-- Cabeçalho -->
      <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-3 flex items-center gap-3">
        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-blue-600 text-white font-semibold">IA</div>
        <div class="flex-1 min-w-0">
          <h1 class="text-base sm:text-lg font-semibold truncate">Chat de Teste (Tempo Real)</h1>
          <p id="statusLinha" class="text-xs text-slate-500">online</p>
        </div>

        <!-- Seletor de etapa -->
        <div class="hidden sm:flex items-center gap-2">
          <label for="selectEtapa" class="text-xs text-slate-500">Etapa:</label>
          <select id="selectEtapa" class="text-xs border rounded-lg px-2 py-1" disabled>
            <option>carregando…</option>
          </select>
          <button id="btnSalvarEtapa" class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200">Salvar</button>
        </div>

        <button id="btnLimpar" class="ml-2 text-xs sm:text-sm px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200">Limpar</button>
      </header>

      <!-- Mensagens -->
      <main id="areaMensagens" class="flex-1 overflow-y-auto bg-[url('https://transparenttextures.com/patterns/black-linen.png')] p-3 sm:p-4 space-y-2 scroll-smooth">
        <!-- skeleton inicial -->
        <div id="skeleton" class="space-y-2">
          <div class="flex justify-start"><div class="h-16 w-3/5 max-w-[72%] rounded-2xl bg-slate-200 animate-pulse"></div></div>
          <div class="flex justify-end"><div class="h-12 w-2/5 max-w-[60%] rounded-2xl bg-slate-200 animate-pulse"></div></div>
          <div class="flex justify-start"><div class="h-20 w-2/3 max-w-[72%] rounded-2xl bg-slate-200 animate-pulse"></div></div>
        </div>
      </main>

      <!-- “digitando” -->
      <div id="digitando" class="px-4 sm:px-6 py-2 text-xs text-slate-500 hidden">IA está digitando…</div>

      <!-- Input -->
      <footer class="bg-white border-t border-slate-200 px-3 sm:px-4 py-3">
        <form id="formEnvio" class="flex items-end gap-2" onsubmit="return false;">
          <?= csrf_field() ?>
          <textarea id="campoMensagem"
            class="flex-1 rounded-2xl border border-slate-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600 resize-none max-h-40 disabled:opacity-50"
            rows="1" placeholder="Digite uma mensagem… (Enter envia • Shift+Enter quebra linha)"></textarea>
          <button id="btnEnviar"
            class="px-5 py-2 rounded-2xl bg-blue-600 text-white font-medium disabled:opacity-50"
            type="button">Enviar</button>
        </form>
      </footer>
    </div>
  </div>

  <!-- Toasts -->
  <div id="toasts" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

<script>
const area = document.getElementById('areaMensagens');
const campo = document.getElementById('campoMensagem');
const btnEnviar = document.getElementById('btnEnviar');
const statusLinha = document.getElementById('statusLinha');
const digitando = document.getElementById('digitando');
const btnLimpar = document.getElementById('btnLimpar');
const selectEtapa = document.getElementById('selectEtapa');
const btnSalvarEtapa = document.getElementById('btnSalvarEtapa');
const skeleton = document.getElementById('skeleton');

let historico = [];
let etapasDisponiveis = []; // do backend
let sending = false;

/* ======= Helpers ======= */
function toast(msg, type='default'){
  const wrap = document.getElementById('toasts');
  const el = document.createElement('div');
  el.className = `fade-enter px-4 py-2 rounded-xl shadow text-sm text-white ${type==='error'?'bg-red-600':type==='success'?'bg-emerald-600':'bg-slate-900'}`;
  el.textContent = msg;
  wrap.appendChild(el);
  requestAnimationFrame(()=> el.classList.add('fade-enter-active'));
  setTimeout(()=> el.remove(), 2400);
}

function hhmm(date){const h=String(date.getHours()).padStart(2,'0');const m=String(date.getMinutes()).padStart(2,'0');return `${h}:${m}`;}
function esc(t){return (t||'').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function isSameDay(a,b){const A=new Date(a),B=new Date(b);return A.toDateString()===B.toDateString();}
function dayLabel(d){const dt=new Date(d);return dt.toLocaleDateString(undefined,{weekday:'short',day:'2-digit',month:'short'});} 

function dateSeparator(label){
  return `<div class="my-3 flex items-center gap-3"><div class="h-px flex-1 bg-slate-200"></div><span class="text-[11px] text-slate-500">${label}</span><div class="h-px flex-1 bg-slate-200"></div></div>`;
}

function bubble(role, content, ts){
  const isUser = role === 'user';
  const align = isUser ? 'justify-end' : 'justify-start';
  const cls = isUser ? 'bg-blue-600 text-white tail-r' : 'bg-white border border-slate-200 text-slate-800 tail-l';
  const tsNum = typeof ts === 'number' ? ts : Date.parse(ts || Date.now());
  const time = `<div class="text-[10px] mt-1 opacity-70 text-right">${hhmm(new Date(tsNum))}</div>`;
  return `
    <div class="flex ${align}">
      <div class="relative max-w-[78%] sm:max-w-[70%] rounded-2xl px-4 py-2 ${cls} shadow-sm whitespace-pre-wrap break-words">
        ${esc(content)}
        ${time}
      </div>
    </div>`;
}

function render(){
  if (skeleton) skeleton.remove();
  const parts = [];
  let lastDate = null;
  (historico||[]).forEach(m=>{
    const when = m.ts || Date.now();
    if(!lastDate || !isSameDay(lastDate, when)){
      parts.push(dateSeparator(dayLabel(when)));
      lastDate = when;
    }
    parts.push(bubble(m.role, m.content, when));
  });
  area.innerHTML = parts.join('');
  area.scrollTop = area.scrollHeight;
}

function pushLocal(role, content){ historico.push({ role, content, ts: Date.now() }); render(); }
function setDigitando(on){ digitando.classList.toggle('hidden', !on); statusLinha.textContent = on ? 'digitando…' : 'online'; }
function autoResize(el){ el.style.height='auto'; el.style.height=Math.min(el.scrollHeight,160)+'px'; }

/* popula o select dinamicamente com base no backend */
function popularEtapas(lista, atual){
  // aceita ['inicio', ...] ou [{etapa:'inicio'}, ...]
  const arr = Array.isArray(lista) ? lista.map(x => typeof x === 'string' ? x : (x?.etapa ?? '')).filter(Boolean) : [];
  etapasDisponiveis = Array.from(new Set(arr)).sort((a,b)=>a.localeCompare(b,'pt-BR'));

  selectEtapa.innerHTML = '';
  if (etapasDisponiveis.length === 0) {
    const opt = document.createElement('option');
    opt.textContent = '— sem etapas —';
    selectEtapa.appendChild(opt);
    selectEtapa.disabled = true;
    return;
  }
  for (const etapa of etapasDisponiveis){
    const opt = document.createElement('option');
    opt.value = etapa; opt.textContent = etapa; selectEtapa.appendChild(opt);
  }
  if (atual && etapasDisponiveis.includes(atual)) {
    selectEtapa.value = atual;
  }
  selectEtapa.disabled = false;
}

/* ======= Boot ======= */
async function fetchEtapas(){
  try{
    const r = await fetch('/configuracaoia/etapas');
    const lista = await r.json();
    if (Array.isArray(lista)) {
      popularEtapas(lista);
    }
  }catch(e){ console.warn('falha ao carregar etapas', e); }
}

async function carregarHistorico(){
  try {
    const r = await fetch('/configuracaoia/historicoTeste');
    const data = await r.json();

    if (Array.isArray(data?.historico)) { historico = data.historico; render(); }

    // se backend retornar etapas junto, usa; senão mantemos as já carregadas de /etapas
    if (Array.isArray(data?.etapasDisponiveis) && data.etapasDisponiveis.length) {
      popularEtapas(data.etapasDisponiveis, data?.etapa || 'inicio');
    } else if (data?.etapa) {
      // só atualiza seleção
      if (etapasDisponiveis.includes(data.etapa)) selectEtapa.value = data.etapa;
    }
  } catch(e) { console.warn('sem histórico inicial', e); }
}

/* ======= Etapas ======= */
async function salvarEtapa(){
  const etapaEscolhida = selectEtapa.value;
  if (etapasDisponiveis.length && !etapasDisponiveis.includes(etapaEscolhida)) { alert('Etapa inválida.'); return; }
  const form = new FormData();
  form.append('etapa', etapaEscolhida);
  try {
    const r = await fetch('/configuracaoia/atualizarEtapaTeste', { method: 'POST', body: form });
    const data = await r.json();
    if (!data?.ok) alert('Falha ao salvar etapa'); else toast('Etapa atualizada','success');
  } catch(e) { alert('Erro ao salvar etapa'); }
}

/* ======= Envio ======= */
async function enviar(){
  const texto = (campo.value||'').trim();
  if(!texto || sending) return;
  sending = true;
  pushLocal('user', texto);
  campo.value = ''; autoResize(campo); setDigitando(true);

  const form = new FormData(document.getElementById('formEnvio'));
  form.append('mensagem', texto);

  try{
    const resp = await fetch('/configuracaoia/testarchat', { method: 'POST', body: form });
    const data = await resp.json().catch(() => ({}));

    if (Array.isArray(data?.historico)){
      historico = data.historico; render();
    } else if (Array.isArray(data?.partes) && data.partes.length){
      for (const p of data.partes){ pushLocal('assistant', p); }
    } else {
      pushLocal('assistant', data?.resposta ?? 'Desculpe, não consegui responder agora.');
    }
    toast('Mensagem enviada','success');
  }catch(e){
    console.error(e);
    pushLocal('assistant', '⚠️ Erro ao contatar a IA. Tente novamente.');
    toast('Erro ao enviar','error');
  }finally{
    setDigitando(false);
    sending = false;
  }
}

/* ======= Limpar ======= */
btnLimpar.addEventListener('click', async () => {
  const formEl = document.getElementById('formEnvio');
  const form = new FormData(formEl);
  btnLimpar.disabled = true; const old = btnLimpar.textContent; btnLimpar.textContent = 'Limpando...';
  try {
    const r = await fetch('/configuracaoia/limparHistoricoTeste', { method: 'POST', body: form });
    const data = await r.json();
    if (data?.ok) { historico = []; render(); await carregarHistorico(); toast('Histórico limpo','success'); }
    else { alert('Não consegui limpar o histórico agora.'); }
  } catch (e) { console.error(e); alert('Erro ao limpar o histórico.'); }
  finally { btnLimpar.disabled = false; btnLimpar.textContent = old; }
});

/* ======= Listeners ======= */
btnEnviar.addEventListener('click', enviar);
campo.addEventListener('input', ()=>autoResize(campo));
campo.addEventListener('keydown', e => { if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); enviar(); }});
btnSalvarEtapa.addEventListener('click', salvarEtapa);

document.addEventListener('DOMContentLoaded', async () => {
  await fetchEtapas();       // carrega lista (GET /configuracaoia/etapas)
  await carregarHistorico(); // puxa histórico (pode atualizar etapa atual)
  setTimeout(()=>campo.focus(), 200);
});

// Acessibilidade: atalho Ctrl/Cmd+K para focar a mensagem
addEventListener('keydown', (e)=>{
  if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='k'){ e.preventDefault(); campo.focus(); }
});
</script>
</body>
</html>
