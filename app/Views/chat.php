<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>CRM Assistente • Conversas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Tailwind CSS via CDN (rápido para desenvolvimento) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    // opcional: configurações rápidas (se quiser customizar depois)
    tailwind.config = {
      theme: {
        extend: {
          borderRadius: { 'xl': '0.75rem', '2xl': '1rem' },
          boxShadow: { 'soft': '0 4px 16px rgba(0,0,0,0.06)' }
        }
      }
    }
  </script>
  <!-- Se usar ícones/emoji etc, Font preconnect ajuda -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
</head>
<body class="bg-gray-50 text-gray-800">
  <div class="min-h-screen flex">
    <?= view('sidebar') ?>

    <main class="flex-1 flex">
      <!-- Lista de contatos -->
      <aside class="w-80 bg-white border-r border-gray-200 flex flex-col">
        <div class="p-4 border-b">
          <h2 class="font-semibold text-lg">Conversas</h2>
          <input id="search" type="text" placeholder="Buscar..." class="mt-3 w-full rounded-xl border border-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <ul id="contactList" class="flex-1 overflow-y-auto divide-y"></ul>
      </aside>

      <!-- Janela de chat -->
      <section class="flex-1 flex flex-col">
        <div class="p-4 border-b bg-white flex items-center justify-between">
          <div>
            <h3 id="chatTitle" class="font-semibold text-lg">Selecione um contato</h3>
            <p id="chatSub" class="text-sm text-gray-500"></p>
          </div>
          <span id="etapaBadge" class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-600"></span>
        </div>

        <div id="messages" class="flex-1 p-4 overflow-y-auto space-y-3 bg-[url('https://transparenttextures.com/patterns/white-wall-3.png')]"></div>

        <form id="sendForm" class="p-4 bg-white border-t flex gap-3" onsubmit="return false;">
          <input id="msgInput" type="text" placeholder="Digite uma mensagem..." class="flex-1 rounded-xl border border-gray-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" disabled>
          <button id="sendBtn" class="px-5 py-2 rounded-xl bg-blue-600 text-white font-medium disabled:opacity-50" disabled>Enviar</button>
        </form>
      </section>
    </main>
  </div>

<script>
let contatos = [];
let numeroAtual = null;
let polling = null;
let historicoCache = ''; // para evitar re-render desnecessário

// Helpers de UI
function formatTelefone(t) {
  if (!t) return '';
  return `+${t}`;
}
function msgBubble(role, content) {
  const isUser = role === 'user';
  const align = isUser ? 'justify-start' : 'justify-end';
  const bubble = isUser
    ? 'bg-white border border-gray-200 text-gray-800'
    : 'bg-blue-600 text-white';

  return `
    <div class="flex ${align}">
      <div class="max-w-[70%] rounded-2xl px-4 py-2 ${bubble} shadow-sm whitespace-pre-wrap break-words">
        ${content.replace(/</g,'&lt;').replace(/>/g,'&gt;')}
      </div>
    </div>
  `;
}
function renderHistorico(h) {
  return h.map(m => msgBubble(m.role, m.content)).join('');
}

// Carregar contatos
async function loadContacts() {
  const res = await fetch('/chat/contacts');
  const data = await res.json();
  contatos = data;
  renderContacts(data);
}

function renderContacts(list) {
  const ul = document.getElementById('contactList');
  const q = document.getElementById('search').value?.toLowerCase() || '';
  const filtered = list.filter(c => (c.nome?.toLowerCase()||'').includes(q) || (c.telefone||'').includes(q));

  ul.innerHTML = filtered.map(c => `
    <li>
      <button class="w-full text-left p-4 hover:bg-blue-50 transition" onclick="openChat('${c.telefone}', '${(c.nome||'').replace(/'/g,"\\'")}')">
        <div class="font-medium">${c.nome||'Paciente'}</div>
        <div class="text-xs text-gray-500">${formatTelefone(c.telefone)}</div>
      </button>
    </li>
  `).join('');
}

document.getElementById('search').addEventListener('input', () => renderContacts(contatos));

// Abrir conversa
async function openChat(numero, nome) {
  numeroAtual = numero;
  document.getElementById('chatTitle').textContent = nome || 'Paciente';
  document.getElementById('chatSub').textContent   = formatTelefone(numero);
  document.getElementById('msgInput').disabled = false;
  document.getElementById('sendBtn').disabled = false;

  // Limpa polling anterior
  if (polling) clearInterval(polling);

  await refreshMessages(); // carrega já
  polling = setInterval(refreshMessages, 3000); // atualiza a cada 3s
}

async function refreshMessages() {
  if (!numeroAtual) return;
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
}

// Enviar
document.getElementById('sendBtn').addEventListener('click', sendMessage);
document.getElementById('msgInput').addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
});

async function sendMessage() {
  const input = document.getElementById('msgInput');
  const text = input.value.trim();
  if (!text || !numeroAtual) return;

  // Otimista: mostra na tela antes de confirmar
  const msgsEl = document.getElementById('messages');
  msgsEl.insertAdjacentHTML('beforeend', msgBubble('assistant', text));
  msgsEl.scrollTop = msgsEl.scrollHeight;
  input.value = '';

  const formData = new FormData();
  formData.append('numero', numeroAtual);
  formData.append('mensagem', text);

  const res = await fetch('/chat/send', { method: 'POST', body: formData });
  if (!res.ok) {
    console.error('Falha ao enviar');
  } else {
    setTimeout(refreshMessages, 1000);
  }
}

// inicia carregando contatos
loadContacts();
</script>
</body>
</html>
