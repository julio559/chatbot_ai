<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Chat em Tempo Real • Teste IA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Tailwind CSS (CDN) -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-screen w-screen bg-gray-100 text-gray-800">
  <div class="h-full flex">
    <!-- Sidebar fixa à esquerda -->
    <?= view('sidebar') ?>

    <!-- Área do chat ocupa todo o restante -->
    <div class="flex-1 flex flex-col h-full">
      <!-- Cabeçalho -->
      <header class="bg-white border-b border-gray-200 px-4 sm:px-6 py-3 flex items-center gap-3">
        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-blue-600 text-white font-semibold">IA</div>
        <div class="flex-1 min-w-0">
          <h1 class="text-base sm:text-lg font-semibold truncate">Chat de Teste (Tempo Real)</h1>
          <p id="statusLinha" class="text-xs text-gray-500">online</p>
        </div>
        <button id="btnLimpar" class="text-xs sm:text-sm px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200">Limpar</button>
      </header>

      <!-- Mensagens -->
      <main id="areaMensagens" class="flex-1 overflow-y-auto bg-[url('https://transparenttextures.com/patterns/white-wall-3.png')] p-3 sm:p-4 space-y-2">
        <!-- mensagens renderizadas via JS -->
      </main>

      <!-- “digitando” -->
      <div id="digitando" class="px-4 sm:px-6 py-2 text-xs text-gray-500 hidden">IA está digitando…</div>

      <!-- Input -->
      <footer class="bg-white border-t border-gray-200 px-3 sm:px-4 py-3">
        <form id="formEnvio" class="flex items-end gap-2" onsubmit="return false;">
          <?= csrf_field() ?>
          <textarea id="campoMensagem"
            class="flex-1 rounded-2xl border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none max-h-40"
            rows="1" placeholder="Digite uma mensagem…"></textarea>
          <button id="btnEnviar"
            class="px-5 py-2 rounded-2xl bg-blue-600 text-white font-medium disabled:opacity-50"
            type="button">Enviar</button>
        </form>
      </footer>
    </div>
  </div>

<script>
/* ====== Estado ====== */
const area = document.getElementById('areaMensagens');
const campo = document.getElementById('campoMensagem');
const btnEnviar = document.getElementById('btnEnviar');
const statusLinha = document.getElementById('statusLinha');
const digitando = document.getElementById('digitando');
const btnLimpar = document.getElementById('btnLimpar');

let historico = []; // {role: 'user'|'assistant', content: string, ts: number}

/* ====== Helpers ====== */
function hhmm(date){const h=String(date.getHours()).padStart(2,'0');const m=String(date.getMinutes()).padStart(2,'0');return `${h}:${m}`;}
function esc(t){return (t||'').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function bubble(role, content, ts){
  const isUser = role === 'user';
  const align = isUser ? 'justify-end' : 'justify-start';
  const cls = isUser ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 text-gray-800';
  return `
    <div class="flex ${align}">
      <div class="max-w-[78%] sm:max-w-[70%] rounded-2xl px-4 py-2 ${cls} shadow-sm">
        <div class="whitespace-pre-wrap break-words">${esc(content)}</div>
        <div class="text-[10px] mt-1 opacity-70 text-right">${hhmm(new Date(ts))}</div>
      </div>
    </div>`;
}
function render(){
  area.innerHTML = historico.map(m => bubble(m.role, m.content, m.ts)).join('');
  area.scrollTop = area.scrollHeight;
}
function addMsg(role, content){
  historico.push({ role, content, ts: Date.now() });
  render();
}

/* ====== Envio ====== */
async function enviar(){
  const texto = (campo.value||'').trim();
  if(!texto) return;

  addMsg('user', texto);
  campo.value = ''; autoResize(campo); setDigitando(true);

  const form = new FormData(document.getElementById('formEnvio'));
  form.append('mensagem', texto);

  try{
    const resp = await fetch('/configuracaoia/testarchat', { method: 'POST', body: form });
    const data = await resp.json().catch(() => ({}));
    addMsg('assistant', data?.resposta ?? 'Desculpe, não consegui responder agora.');
  }catch(e){
    console.error(e);
    addMsg('assistant', '⚠️ Erro ao contatar a IA. Tente novamente.');
  }finally{
    setDigitando(false);
  }
}

/* ====== UI ====== */
function setDigitando(on){ digitando.classList.toggle('hidden', !on); statusLinha.textContent = on ? 'digitando…' : 'online'; }
function autoResize(el){ el.style.height='auto'; el.style.height=Math.min(el.scrollHeight,160)+'px'; }

/* ====== Eventos ====== */
btnEnviar.addEventListener('click', enviar);
campo.addEventListener('input', ()=>autoResize(campo));
campo.addEventListener('keydown', e => {
  if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); enviar(); }
});
btnLimpar.addEventListener('click', () => { historico=[]; render(); campo.value=''; autoResize(campo); });
setTimeout(()=>campo.focus(), 200);
</script>
</body>
</html>
