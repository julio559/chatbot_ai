<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Chat em Tempo Real • Teste IA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-screen w-screen bg-gray-100 text-gray-800">
  <div class="h-full flex">
    <?= view('sidebar') ?>

    <div class="flex-1 flex flex-col h-full">
      <!-- Cabeçalho -->
      <header class="bg-white border-b border-gray-200 px-4 sm:px-6 py-3 flex items-center gap-3">
        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-blue-600 text-white font-semibold">IA</div>
        <div class="flex-1 min-w-0">
          <h1 class="text-base sm:text-lg font-semibold truncate">Chat de Teste (Tempo Real)</h1>
          <p id="statusLinha" class="text-xs text-gray-500">online</p>
        </div>

        <!-- Seletor de etapa (canto superior direito) -->
        <div class="flex items-center gap-2">
          <label for="selectEtapa" class="text-xs text-gray-500">Etapa:</label>
          <select id="selectEtapa" class="text-xs border rounded-lg px-2 py-1">
            <option value="inicio">inicio</option>
            <option value="em_contato">em_contato</option>
            <option value="financeiro">financeiro</option>
            <option value="agendamento">agendamento</option>
            <option value="humano">humano</option>
            <option value="perdido">perdido</option>
            <option value="finalizado">finalizado</option>
          </select>
          <button id="btnSalvarEtapa" class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200">Salvar</button>
        </div>

        <button id="btnLimpar" class="ml-2 text-xs sm:text-sm px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200">Limpar</button>
      </header>

      <!-- Mensagens -->
      <main id="areaMensagens" class="flex-1 overflow-y-auto bg-[url('https://transparenttextures.com/patterns/white-wall-3.png')] p-3 sm:p-4 space-y-2">
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
const area = document.getElementById('areaMensagens');
const campo = document.getElementById('campoMensagem');
const btnEnviar = document.getElementById('btnEnviar');
const statusLinha = document.getElementById('statusLinha');
const digitando = document.getElementById('digitando');
const btnLimpar = document.getElementById('btnLimpar');
const selectEtapa = document.getElementById('selectEtapa');
const btnSalvarEtapa = document.getElementById('btnSalvarEtapa');

let historico = [];
let etapasDisponiveis = []; // <<< manter em memória

function hhmm(date){const h=String(date.getHours()).padStart(2,'0');const m=String(date.getMinutes()).padStart(2,'0');return `${h}:${m}`;}
function esc(t){return (t||'').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function bubble(role, content, ts){
  const isUser = role === 'user';
  const align = isUser ? 'justify-end' : 'justify-start';
  const cls = isUser ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 text-gray-800';
  const tsNum = typeof ts === 'number' ? ts : Date.parse(ts || Date.now());
  return `
    <div class="flex ${align}">
      <div class="max-w-[78%] sm:max-w-[70%] rounded-2xl px-4 py-2 ${cls} shadow-sm">
        <div class="whitespace-pre-wrap break-words">${esc(content)}</div>
        <div class="text-[10px] mt-1 opacity-70 text-right">${hhmm(new Date(tsNum))}</div>
      </div>
    </div>`;
}
function render(){
  area.innerHTML = historico.map(m => bubble(m.role, m.content, m.ts)).join('');
  area.scrollTop = area.scrollHeight;
}
function pushLocal(role, content){
  historico.push({ role, content, ts: Date.now() });
  render();
}
function setDigitando(on){ digitando.classList.toggle('hidden', !on); statusLinha.textContent = on ? 'digitando…' : 'online'; }
function autoResize(el){ el.style.height='auto'; el.style.height=Math.min(el.scrollHeight,160)+'px'; }

function popularEtapas(lista, atual){
  etapasDisponiveis = Array.isArray(lista) ? lista : [];
  // limpa opções
  selectEtapa.innerHTML = '';
  // cria opções
  for (const etapa of etapasDisponiveis){
    const opt = document.createElement('option');
    opt.value = etapa;
    opt.textContent = etapa;
    selectEtapa.appendChild(opt);
  }
  // seleciona atual, se existir na lista; senão, primeira
  if (etapasDisponiveis.includes(atual)) {
    selectEtapa.value = atual;
  } else if (etapasDisponiveis.length) {
    selectEtapa.value = etapasDisponiveis[0];
  }
}

async function carregarHistorico(){
  try {
    const r = await fetch('/configuracaoia/historicoTeste');
    const data = await r.json();

    if (Array.isArray(data?.historico)) {
      historico = data.historico;
      render();
    }

    // popula etapas com o que veio do banco
    if (Array.isArray(data?.etapasDisponiveis)) {
      popularEtapas(data.etapasDisponiveis, data?.etapa || 'inicio');
    } else {
      // fallback: mantém opções existentes no HTML
    }
  } catch(e) { console.warn('sem histórico inicial', e); }
}

async function salvarEtapa(){
  const etapaEscolhida = selectEtapa.value;
  if (etapasDisponiveis.length && !etapasDisponiveis.includes(etapaEscolhida)) {
    alert('Etapa inválida.');
    return;
  }
  const form = new FormData();
  form.append('etapa', etapaEscolhida);
  try {
    const r = await fetch('/configuracaoia/atualizarEtapaTeste', { method: 'POST', body: form });
    const data = await r.json();
    if (!data?.ok) alert('Falha ao salvar etapa');
  } catch(e) {
    alert('Erro ao salvar etapa');
  }
}

async function enviar(){
  const texto = (campo.value||'').trim();
  if(!texto) return;

  pushLocal('user', texto);
  campo.value = ''; autoResize(campo); setDigitando(true);

  const form = new FormData(document.getElementById('formEnvio'));
  form.append('mensagem', texto);

  try{
    const resp = await fetch('/configuracaoia/testarchat', { method: 'POST', body: form });
    const data = await resp.json().catch(() => ({}));

    if (Array.isArray(data?.historico)){
      historico = data.historico;
      render();
    } else if (Array.isArray(data?.partes) && data.partes.length){
      for (const p of data.partes){ pushLocal('assistant', p); }
    } else {
      pushLocal('assistant', data?.resposta ?? 'Desculpe, não consegui responder agora.');
    }
  }catch(e){
    console.error(e);
    pushLocal('assistant', '⚠️ Erro ao contatar a IA. Tente novamente.');
  }finally{
    setDigitando(false);
  }
}

btnEnviar.addEventListener('click', enviar);
campo.addEventListener('input', ()=>autoResize(campo));
campo.addEventListener('keydown', e => { if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); enviar(); }});
btnLimpar.addEventListener('click', () => { historico=[]; render(); campo.value=''; autoResize(campo); });
btnSalvarEtapa.addEventListener('click', salvarEtapa);

document.addEventListener('DOMContentLoaded', () => {
  carregarHistorico();
  setTimeout(()=>campo.focus(), 200);
});
btnLimpar.addEventListener('click', async () => {
  // pega o CSRF do próprio form (se estiver habilitado)
  const formEl = document.getElementById('formEnvio');
  const form = new FormData(formEl);

  btnLimpar.disabled = true;
  btnLimpar.textContent = 'Limpando...';

  try {
    const r = await fetch('/configuracaoia/limparHistoricoTeste', {
      method: 'POST',
      body: form
    });
    const data = await r.json();

    if (data?.ok) {
      // zera o front e recarrega o histórico do servidor (vai vir vazio)
      historico = [];
      render();
      await carregarHistorico(); // garante UI consistente após limpar
    } else {
      alert('Não consegui limpar o histórico agora.');
    }
  } catch (e) {
    console.error(e);
    alert('Erro ao limpar o histórico.');
  } finally {
    btnLimpar.disabled = false;
    btnLimpar.textContent = 'Limpar';
  }
});

</script>

</body>
</html>
