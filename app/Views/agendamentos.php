<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>Mensagens Agendadas | CRM Profissional</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { 
      theme: { 
        extend: {
          colors: { 
            brand: { DEFAULT: '#111827' },
            primary: {
              50: '#eff6ff',
              100: '#dbeafe', 
              500: '#3b82f6',
              600: '#2563eb',
              700: '#1d4ed8'
            }
          },
          borderRadius: { xl: '0.75rem', '2xl': '1rem' },
          boxShadow: { 
            professional: '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)',
            'professional-lg': '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
            'soft': '0 2px 8px 0 rgb(0 0 0 / 0.04)'
          }
        }
      }
    }
  </script>
  <style>
    .container-safe { max-width: 1200px; }
    .ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fade-enter { opacity: 0; transform: translateY(6px); }
    .fade-enter-active { opacity: 1; transform: translateY(0); transition: all .18s ease; }
    .header-gradient { background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); }
    .card-gradient { background: linear-gradient(135deg, #ffffff 0%, #fcfcfd 100%); }
    .hover-lift { transition: all 0.2s ease; }
    .hover-lift:hover { transform: translateY(-1px); box-shadow: 0 4px 12px -1px rgb(0 0 0 / 0.15); }
    .animate-fade-in { animation: fadeIn 0.5s ease-out; }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .loading-shimmer {
      background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
      background-size: 200% 100%;
      animation: shimmer 1.5s infinite;
    }
    @keyframes shimmer {
      0% { background-position: 200% 0; }
      100% { background-position: -200% 0; }
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">
<div class="min-h-screen">
  <div class="flex min-h-screen">
    <?= view('sidebar') ?>

    <main class="flex-1">
      <!-- Header Profissional -->
      <header class="header-gradient border-b border-gray-200 shadow-sm">
        <div class="container-safe mx-auto px-8 py-6">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-3">
              <!-- Breadcrumb -->
              <nav class="text-sm font-medium" aria-label="Breadcrumb">
                <ol class="flex items-center gap-2 text-gray-500">
                  <li class="hover:text-gray-700 cursor-pointer transition-colors">CRM</li>
                  <li class="text-gray-300">/</li>
                  <li class="hover:text-gray-700 cursor-pointer transition-colors">Comunicação</li>
                  <li class="text-gray-300">/</li>
                  <li class="text-gray-900 font-semibold">Mensagens Agendadas</li>
                </ol>
              </nav>
              
              <!-- Título e descrição -->
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center shadow-lg">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                  <h1 class="text-3xl font-bold tracking-tight text-gray-900">Mensagens Agendadas</h1>
                  <p class="text-gray-600 text-lg">Gerencie, edite e acompanhe o status dos envios futuros</p>
                </div>
              </div>
            </div>
            
            <div class="flex items-center gap-3">
              <button id="btnNovo" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 text-white px-6 py-3 font-medium shadow-professional hover:bg-blue-700 hover:shadow-professional-lg hover-lift transition-all duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Novo Agendamento
              </button>
            </div>
          </div>
        </div>
      </header>

      <!-- Toolbar de Filtros -->
      <section class="container-safe mx-auto px-8 pt-8">
        <div class="card-gradient rounded-2xl shadow-professional border border-gray-200 p-6">
          <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <!-- Filtros principais -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 flex-1">
              <div class="relative flex-1 max-w-md">
                <input id="q" type="text" placeholder="Buscar por nome, telefone ou mensagem..." 
                       class="w-full rounded-xl border border-gray-300 bg-white px-12 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent hover-lift transition-all" />
                <span class="pointer-events-none absolute left-4 top-3.5 text-gray-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M12.9 14.32a8 8 0 111.414-1.414l4.387 4.386a1 1 0 01-1.414 1.415l-4.387-4.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd"/>
                  </svg>
                </span>
                <div class="absolute right-3 top-3.5">
                  <kbd class="inline-flex items-center rounded border border-gray-200 px-1 font-sans text-xs text-gray-400">/</kbd>
                </div>
              </div>
              
              <div class="flex items-center gap-3">
                <select id="status" class="rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500 focus:border-transparent hover-lift transition-all">
                  <option value="">Todos os Status</option>
                  <option value="pendente">Pendentes</option>
                  <option value="enviado">Enviados</option>
                  <option value="cancelado">Cancelados</option>
                </select>
                
                <button id="btnFiltrar" class="px-6 py-3 rounded-xl bg-blue-600 text-white font-medium hover:bg-blue-700 shadow-professional hover:shadow-professional-lg hover-lift transition-all duration-200">
                  Filtrar
                </button>
                
                <button id="btnLimpar" class="px-4 py-3 rounded-xl bg-gray-100 text-gray-700 font-medium hover:bg-gray-200 hover-lift transition-all">
                  Limpar
                </button>
              </div>
            </div>

            <!-- Status Chips -->
            <div class="flex flex-wrap items-center gap-3">
              <span class="text-sm font-medium text-gray-600 hidden sm:block">Filtro rápido:</span>
              <button data-chip="" class="chip-status px-4 py-2 rounded-full border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 hover-lift transition-all">
                Todos
              </button>
              <button data-chip="pendente" class="chip-status px-4 py-2 rounded-full border border-yellow-200 bg-yellow-50 text-sm font-medium text-yellow-800 hover:bg-yellow-100 hover-lift transition-all">
                Pendentes
              </button>
              <button data-chip="enviado" class="chip-status px-4 py-2 rounded-full border border-emerald-200 bg-emerald-50 text-sm font-medium text-emerald-800 hover:bg-emerald-100 hover-lift transition-all">
                Enviados
              </button>
              <button data-chip="cancelado" class="chip-status px-4 py-2 rounded-full border border-gray-200 bg-gray-50 text-sm font-medium text-gray-700 hover:bg-gray-100 hover-lift transition-all">
                Cancelados
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Tabela Principal -->
      <section class="container-safe mx-auto px-8 py-8">
        <div class="card-gradient rounded-2xl shadow-professional border border-gray-200 overflow-hidden">
          <!-- Header da tabela -->
          <div class="flex items-center justify-between px-6 py-4 bg-gray-50 border-b border-gray-200">
            <div class="flex items-center gap-4">
              <div class="text-sm font-medium text-gray-700">
                Total de registros: <span id="count" class="font-semibold text-gray-900">0</span>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <button id="btnExport" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 font-medium hover-lift transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Exportar CSV
              </button>
            </div>
          </div>

          <!-- Tabela -->
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 border-b border-gray-200">
                <tr class="text-left">
                  <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">ID</th>
                  <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">Cliente</th>
                  <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">Telefone</th>
                  <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">Mensagem</th>
                  <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">Enviar em</th>
                  <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">Status</th>
                  <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide text-right">Ações</th>
                </tr>
              </thead>
              <tbody id="tbody" class="divide-y divide-gray-100 bg-white">
                <!-- linhas via JS -->
              </tbody>
            </table>
          </div>

          <!-- Empty State Elegante -->
          <div id="empty" class="hidden px-8 py-20 text-center bg-white">
            <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-100">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Nenhum agendamento encontrado</h3>
            <p class="text-gray-600 max-w-sm mx-auto mb-6">Ajuste os filtros acima ou crie um novo agendamento para começar.</p>
            <button onclick="document.getElementById('btnNovo').click()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              Criar Primeiro Agendamento
            </button>
          </div>
        </div>
      </section>
    </main>
  </div>
</div>

<!-- Modal de Edição Profissional -->
<div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
  <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm" onclick="fecharModal()"></div>
  <div class="relative bg-white w-full max-w-2xl rounded-2xl shadow-2xl animate-fade-in">
    <!-- Header do Modal -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-xl bg-blue-100 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
        </div>
        <div>
          <h3 class="text-xl font-semibold text-gray-900">Editar Agendamento</h3>
          <p id="mId" class="text-sm text-gray-500"></p>
        </div>
      </div>
      <button class="text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg p-2 transition-colors" onclick="fecharModal()" aria-label="Fechar">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Conteúdo do Modal -->
    <div class="px-6 py-6">
      <form id="form" onsubmit="return false;" class="space-y-6">
        <?= csrf_field() ?>
        <input type="hidden" id="mIdVal">

        <!-- Data e Hora -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Data do Envio</label>
            <input id="mData" type="date" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
          </div>
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Hora do Envio</label>
            <input id="mHora" type="time" step="60" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
          </div>
        </div>

        <!-- Mensagem -->
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Mensagem</label>
          <textarea id="mMensagem" rows="4" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none" placeholder="Digite o texto que será enviado..."></textarea>
          <div class="flex items-center justify-between text-xs text-gray-500">
            <span>Use variáveis como {nome}, {telefone} para personalizar</span>
            <span id="charCount">0/500 caracteres</span>
          </div>
        </div>

        <!-- Status -->
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Status do Agendamento</label>
          <select id="mStatus" class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
            <option value="pendente">Pendente</option>
            <option value="enviado">Enviado</option>
            <option value="cancelado">Cancelado</option>
          </select>
        </div>
      </form>
    </div>

    <!-- Footer do Modal -->
    <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-2xl">
      <button type="button" class="px-5 py-2.5 rounded-xl bg-white border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 hover-lift transition-all" onclick="fecharModal()">
        Cancelar
      </button>
      <button id="btnSalvar" class="px-5 py-2.5 rounded-xl bg-blue-600 text-white font-medium hover:bg-blue-700 shadow-professional hover:shadow-professional-lg hover-lift transition-all">
        Salvar Alterações
      </button>
    </div>
  </div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div id="confirmDelete" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
  <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm" onclick="fecharConfirm()"></div>
  <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl animate-fade-in">
    <div class="p-6">
      <div class="flex items-start gap-4">
        <div class="h-12 w-12 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-9 0h10"/>
          </svg>
        </div>
        <div class="flex-1">
          <h4 class="text-lg font-semibold text-gray-900 mb-2">Excluir agendamento?</h4>
          <p class="text-gray-600 mb-6">Esta ação não pode ser desfeita. O agendamento será removido permanentemente do sistema.</p>
          
          <div class="flex items-center justify-end gap-3">
            <button class="px-4 py-2.5 rounded-xl bg-white border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 hover-lift transition-all" onclick="fecharConfirm()">
              Cancelar
            </button>
            <button id="btnConfirmDelete" class="px-4 py-2.5 rounded-xl bg-red-600 text-white font-medium hover:bg-red-700 shadow-professional hover:shadow-professional-lg hover-lift transition-all">
              Sim, Excluir
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div id="toasts" class="fixed bottom-4 right-4 z-[60] space-y-2"></div>

<script>
/* ================== Helpers ================== */
const csrfName = '<?= esc(csrf_token()) ?>';
const csrfHash = '<?= esc(csrf_hash()) ?>';

function toast(msg, type='default'){
  const wrap = document.getElementById('toasts');
  const t = document.createElement('div');
  const base = 'px-6 py-4 rounded-xl shadow-lg text-sm text-white flex items-center gap-3 max-w-md';
  const tone = type==='success' ? 'bg-emerald-600' : type==='error' ? 'bg-red-600' : 'bg-gray-900';
  
  const icons = {
    success: '<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>',
    error: '<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>',
    default: '<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>'
  };
  
  t.className = base + ' ' + tone + ' fade-enter';
  t.innerHTML = `${icons[type] || icons.default}<span class="flex-1">${msg}</span>`;
  wrap.appendChild(t);
  requestAnimationFrame(()=> t.classList.add('fade-enter-active'));
  setTimeout(()=> t.remove(), 4000);
}

function esc(t){ return (t||'').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function dtBR(iso){
  if(!iso) return '-';
  const d = new Date((iso+'').replace(' ','T'));
  if (isNaN(d)) return iso;
  return d.toLocaleString('pt-BR');
}
function phoneFmt(num){
  const n = (num||'').replace(/\D+/g,'');
  if(n.length>=13){ return `+${n}`; }
  if(n.length===11){ return `(${n.slice(0,2)}) ${n.slice(2,7)}-${n.slice(7)}`; }
  if(n.length===10){ return `(${n.slice(0,2)}) ${n.slice(2,6)}-${n.slice(6)}`; }
  return num||'-';
}

const state = { items: [], edit: null, pendingDeleteId: null, loading: false };

/* ================== Carregar ================== */
async function carregar(){
  try{
    state.loading = true; 
    drawSkeleton();
    
    const q = document.getElementById('q').value.trim();
    const status = document.getElementById('status').value;
    const url = new URL('<?= base_url("agendamentos/list") ?>', window.location.origin);
    if (q) url.searchParams.set('q', q);
    if (status) url.searchParams.set('status', status);

    const res = await fetch(url.toString());
    const data = await res.json();
    state.items = data.items || [];
    document.getElementById('count').textContent = state.items.length;
    renderTabela();
  }catch(e){
    console.error(e); 
    toast('Erro ao carregar lista de agendamentos','error');
  }finally{ 
    state.loading = false; 
  }
}

function drawSkeleton(){
  const tb = document.getElementById('tbody');
  const empty = document.getElementById('empty');
  empty.classList.add('hidden');
  tb.innerHTML = '';
  
  for(let i=0;i<6;i++){
    tb.insertAdjacentHTML('beforeend', `
      <tr class="animate-pulse">
        <td class="px-6 py-4"><div class="h-4 w-12 rounded loading-shimmer"></div></td>
        <td class="px-6 py-4">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full loading-shimmer"></div>
            <div class="space-y-2">
              <div class="h-4 w-32 rounded loading-shimmer"></div>
              <div class="h-3 w-24 rounded loading-shimmer"></div>
            </div>
          </div>
        </td>
        <td class="px-6 py-4"><div class="h-4 w-28 rounded loading-shimmer"></div></td>
        <td class="px-6 py-4"><div class="h-4 w-64 rounded loading-shimmer"></div></td>
        <td class="px-6 py-4"><div class="h-4 w-40 rounded loading-shimmer"></div></td>
        <td class="px-6 py-4"><div class="h-6 w-20 rounded-full loading-shimmer"></div></td>
        <td class="px-6 py-4 text-right"><div class="h-8 w-24 rounded loading-shimmer ml-auto"></div></td>
      </tr>`);
  }
}

function renderTabela(){
  const tb = document.getElementById('tbody');
  const empty = document.getElementById('empty');
  tb.innerHTML = '';
  
  if (!state.items.length){
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');

  state.items.forEach(item => {
    const msgShort = (item.mensagem || '').length > 90 ? esc(item.mensagem.slice(0,90)) + '…' : esc(item.mensagem || '');
    const badgeClass = badge(item.status);
    const statusIcon = getStatusIcon(item.status);
    
    tb.insertAdjacentHTML('beforeend', `
      <tr class="hover:bg-gray-50 transition-colors">
        <td class="px-6 py-4">
          <span class="text-sm font-medium text-gray-900">#${item.id}</span>
        </td>
        <td class="px-6 py-4">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-semibold text-sm shadow-lg">
              ${(item.paciente_nome||'P')[0]?.toUpperCase?.()||'P'}
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-sm font-semibold text-gray-900 ellipsis">${esc(item.paciente_nome || 'Paciente')}</div>
              <div class="text-xs text-gray-500 ellipsis">${esc(item.email||'Sem email cadastrado')}</div>
            </div>
          </div>
        </td>
        <td class="px-6 py-4">
          <div class="flex items-center gap-2">
            <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
              <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
            </svg>
            <span class="text-sm text-gray-900 font-medium">${phoneFmt(item.numero)}</span>
          </div>
        </td>
        <td class="px-6 py-4">
          <div class="max-w-md">
            <p class="text-sm text-gray-900 ellipsis">${msgShort}</p>
            ${item.mensagem && item.mensagem.length > 90 ? '<span class="text-xs text-gray-500 cursor-pointer hover:text-blue-600">Ver mais...</span>' : ''}
          </div>
        </td>
        <td class="px-6 py-4">
          <div class="flex items-center gap-2">
            <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
            </svg>
            <span class="text-sm text-gray-900 font-medium">${dtBR(item.enviar_em)}</span>
          </div>
        </td>
        <td class="px-6 py-4">
          <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium ${badgeClass}">
            ${statusIcon}
            ${item.status}
          </span>
        </td>
        <td class="px-6 py-4 text-right">
          <div class="inline-flex items-center gap-2">
            <button class="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 font-medium transition-all hover-lift" onclick='abrirEdicao(${JSON.stringify(item)})'>
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
              </svg>
              Editar
            </button>
            <button class="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 font-medium transition-all hover-lift" onclick="excluir(${item.id})">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
              </svg>
              Excluir
            </button>
          </div>
        </td>
      </tr>
    `);
  });
}

function badge(st){
  if (st === 'pendente')  return 'bg-yellow-100 text-yellow-800 border border-yellow-200';
  if (st === 'enviado')   return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
  if (st === 'cancelado') return 'bg-gray-100 text-gray-700 border border-gray-200';
  return 'bg-gray-100 text-gray-700 border border-gray-200';
}

function getStatusIcon(status) {
  const icons = {
    pendente: '<svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg>',
    enviado: '<svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>',
    cancelado: '<svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>'
  };
  return icons[status] || icons.pendente;
}

/* ================== Filtros ================== */
function syncChipsFromSelect(){
  const s = document.getElementById('status').value;
  document.querySelectorAll('.chip-status').forEach(btn=>{
    const val = btn.getAttribute('data-chip');
    const active = (val===s) || (val==='' && s==='');
    btn.classList.toggle('ring-2', active);
    btn.classList.toggle('ring-blue-500', active);
    btn.classList.toggle('bg-blue-50', active && val !== '');
    btn.classList.toggle('text-blue-700', active && val !== '');
  });
}

document.getElementById('btnFiltrar').addEventListener('click', carregar);

document.getElementById('btnLimpar').addEventListener('click', () => {
  document.getElementById('q').value = '';
  document.getElementById('status').value = '';
  syncChipsFromSelect();
  carregar();
});

// chips -> select
Array.from(document.querySelectorAll('.chip-status')).forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const v = btn.getAttribute('data-chip') || '';
    document.getElementById('status').value = v;
    syncChipsFromSelect();
    carregar();
  });
});

// Busca ao pressionar Enter e atalho '/'
const searchInput = document.getElementById('q');
searchInput.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){carregar();} });
document.addEventListener('keydown', (e)=>{ if(e.key==='/' && document.activeElement!==searchInput){ e.preventDefault(); searchInput.focus(); } });

// Contador de caracteres no textarea
document.getElementById('mMensagem').addEventListener('input', (e) => {
  const length = e.target.value.length;
  document.getElementById('charCount').textContent = `${length}/500 caracteres`;
  if (length > 500) {
    document.getElementById('charCount').classList.add('text-red-600');
  } else {
    document.getElementById('charCount').classList.remove('text-red-600');
  }
});

/* ================== Edição ================== */
function abrirModal(){ 
  const m=document.getElementById('modal'); 
  m.classList.remove('hidden'); 
  m.classList.add('flex'); 
  document.body.classList.add('overflow-hidden');
}

function fecharModal(){ 
  const m=document.getElementById('modal'); 
  m.classList.add('hidden'); 
  m.classList.remove('flex'); 
  document.body.classList.remove('overflow-hidden');
  state.edit=null; 
}

function abrirEdicao(item){
  state.edit = item;
  document.getElementById('mId').textContent = `Agendamento #${item.id}`;
  document.getElementById('mIdVal').value = item.id;
  document.getElementById('mMensagem').value = item.mensagem || '';

  const d = new Date((item.enviar_em || '').replace(' ','T'));
  const yyyy = d.getFullYear().toString().padStart(4,'0');
  const mm   = (d.getMonth()+1).toString().padStart(2,'0');
  const dd   = d.getDate().toString().padStart(2,'0');
  const HH   = d.getHours().toString().padStart(2,'0');
  const II   = d.getMinutes().toString().padStart(2,'0');

  document.getElementById('mData').value = isNaN(d) ? '' : `${yyyy}-${mm}-${dd}`;
  document.getElementById('mHora').value = isNaN(d) ? '' : `${HH}:${II}`;
  document.getElementById('mStatus').value = item.status || 'pendente';

  // Atualizar contador de caracteres
  const length = (item.mensagem || '').length;
  document.getElementById('charCount').textContent = `${length}/500 caracteres`;

  abrirModal();
}

document.getElementById('btnSalvar').addEventListener('click', async () => {
  const id = document.getElementById('mIdVal').value;
  const msg = document.getElementById('mMensagem').value.trim();
  const data = document.getElementById('mData').value;
  const hora = document.getElementById('mHora').value;
  const st = document.getElementById('mStatus').value;

  if(!msg || !data || !hora){ 
    toast('Preencha todos os campos obrigatórios','error'); 
    return; 
  }

  if(msg.length > 500) {
    toast('A mensagem deve ter no máximo 500 caracteres','error');
    return;
  }

  // Loading state no botão
  const btnSalvar = document.getElementById('btnSalvar');
  const originalText = btnSalvar.textContent;
  btnSalvar.disabled = true;
  btnSalvar.innerHTML = `
    <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    Salvando...
  `;

  try {
    const fd = new FormData();
    fd.append('<?= esc(csrf_token()) ?>', '<?= esc(csrf_hash()) ?>');
    fd.append('mensagem', msg);
    fd.append('data', data);
    fd.append('hora', hora);
    fd.append('status', st);

    const res = await fetch('<?= base_url("agendamentos/update") ?>/'+id, { method:'POST', body: fd });
    
    if (res.ok) {
      toast('Agendamento atualizado com sucesso!','success');
      fecharModal();
      await carregar();
    } else {
      const j = await res.json().catch(()=>({msg:'Erro ao salvar'}));
      toast(j.msg || 'Erro ao salvar agendamento','error');
    }
  } catch (error) {
    toast('Erro de conexão. Tente novamente.','error');
  } finally {
    btnSalvar.disabled = false;
    btnSalvar.textContent = originalText;
  }
});

/* ================== Excluir ================== */
function abrirConfirm(id){ 
  state.pendingDeleteId = id; 
  const m=document.getElementById('confirmDelete'); 
  m.classList.remove('hidden'); 
  m.classList.add('flex'); 
  document.body.classList.add('overflow-hidden');
}

function fecharConfirm(){ 
  state.pendingDeleteId=null; 
  const m=document.getElementById('confirmDelete'); 
  m.classList.add('hidden'); 
  m.classList.remove('flex'); 
  document.body.classList.remove('overflow-hidden');
}

async function excluir(id){
  abrirConfirm(id);
}

document.getElementById('btnConfirmDelete').addEventListener('click', async ()=>{
  const id = state.pendingDeleteId; 
  if(!id) return;

  // Loading state no botão
  const btnConfirm = document.getElementById('btnConfirmDelete');
  const originalText = btnConfirm.textContent;
  btnConfirm.disabled = true;
  btnConfirm.innerHTML = `
    <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    Excluindo...
  `;

  try {
    const fd = new FormData();
    fd.append('<?= esc(csrf_token()) ?>', '<?= esc(csrf_hash()) ?>');
    const res = await fetch('<?= base_url("agendamentos/delete") ?>/'+id, { method:'POST', body: fd });
    
    if (res.ok){
      toast('Agendamento excluído com sucesso','success');
      fecharConfirm();
      await carregar();
    } else {
      const j = await res.json().catch(()=>({msg:'Erro ao excluir'}));
      toast(j.msg || 'Erro ao excluir agendamento','error');
    }
  } catch (error) {
    toast('Erro de conexão. Tente novamente.','error');
  } finally {
    btnConfirm.disabled = false;
    btnConfirm.textContent = originalText;
  }
});

/* ================== Export ================== */
document.getElementById('btnExport').addEventListener('click', ()=>{
  if(!state.items.length){ 
    toast('Nenhum dado disponível para exportar','error'); 
    return; 
  }
  
  const rows = [ ['ID','Cliente','Telefone','Mensagem','Enviar em','Status'] ];
  state.items.forEach(it=> rows.push([
    it.id, 
    (it.paciente_nome||'Paciente').replaceAll(',', ' '), 
    phoneFmt(it.numero), 
    (it.mensagem||'').replaceAll('\n',' ').replaceAll(',', ' '), 
    dtBR(it.enviar_em), 
    it.status
  ]));
  
  const csv = rows.map(r=> r.map(v=> `"${String(v).replaceAll('"','""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type:'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; 
  a.download = `agendamentos_${new Date().toISOString().split('T')[0]}.csv`; 
  a.click();
  URL.revokeObjectURL(url);
  
  toast('Arquivo CSV exportado com sucesso!','success');
});

/* ================== Novo Agendamento ================== */
document.getElementById('btnNovo').addEventListener('click', ()=>{
  // Para criar novo, definir um ID especial que será tratado no backend
  abrirEdicao({ 
    id: 'novo', 
    mensagem: '', 
    enviar_em: '', 
    status: 'pendente',
    paciente_nome: 'Novo Agendamento',
    numero: '',
    email: ''
  });
});

// Fechar modais com ESC
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    fecharModal();
    fecharConfirm();
  }
});

/* ================== Inicialização ================== */
syncChipsFromSelect();
carregar();

// Auto-refresh a cada 30 segundos para manter dados atualizados
setInterval(() => {
  if (!state.loading && !document.getElementById('modal').classList.contains('flex')) {
    carregar();
  }
}, 30000);
</script>
</body>
</html>