<?php
// helper de URL do CI
helper('url');

// helpers de classes (preservados)
function navClasses(bool $active): string {
  return $active
    ? 'flex items-center px-4 py-3 rounded-xl bg-blue-100 text-blue-700 font-semibold transition-all duration-200 group'
    : 'flex items-center px-4 py-3 rounded-xl text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-all duration-200 group';
}
function iconClasses(bool $active): string {
  return $active
    ? 'w-5 h-5 mr-3 text-blue-600 shrink-0'
    : 'w-5 h-5 mr-3 text-blue-400 group-hover:text-blue-600 shrink-0';
}

// flags de rota
$isDashboard    = url_is('/') || url_is('dashboard') || url_is('dashboard/*');
$isPaciente     = url_is('paciente') || url_is('paciente/*');
$isAguardando   = url_is('painel/aguardando') || url_is('painel/aguardando/*');
$isKanban       = url_is('kanban') || url_is('kanban/*');
$isEtapas       = url_is('etapas') || url_is('etapas/*');
$isNotificacoes = url_is('notificacoes') || url_is('notificacoes/*');
$isAgendamentos = url_is('agendamentos') || url_is('agendamentos/*');
$isChats        = url_is('chat') || url_is('chat/*');
$isTestes       = url_is('configuracaoia') || url_is('configuracaoia/*');
$isWhatsapp     = url_is('whatsapp') || url_is('whatsapp/*');
$isAprendizagem = url_is('aprendizagem') || url_is('aprendizagem/*');
$isTarefas      = url_is('tarefas') || url_is('tarefas/*'); // <<< NOVO
?>

<style>
  /* Shell responsivo */
  aside.sidebar { width: 16rem; transition: width .18s ease; }
  aside.sidebar.is-collapsed { width: 5rem; }
  aside.sidebar .sb-label { transition: opacity .12s ease; }
  aside.sidebar.is-collapsed .sb-label { opacity: 0; pointer-events: none; position: absolute; }
  aside.sidebar .sb-chevron { transition: transform .18s ease; }
  aside.sidebar.is-collapsed .sb-chevron { transform: rotate(180deg); }

  /* Drawer mobile */
  @media (max-width: 1023px){
    aside.sidebar {
      position: fixed; inset: 0 auto 0 0; z-index: 40;
      width: 16rem; transform: translateX(-100%);
      box-shadow: 0 10px 30px rgba(0,0,0,.15);
    }
    aside.sidebar.show { transform: translateX(0); }
    .sb-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 30; display: none; }
    .sb-overlay.show { display: block; }
  }
</style>

<!-- Overlay mobile -->
<div id="sb-overlay" class="sb-overlay" aria-hidden="true"></div>

<aside class="sidebar bg-white shadow-md border-r border-gray-200 h-screen flex flex-col">
  <!-- Cabeçalho -->
  <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
    <div class="flex items-center gap-2 min-w-0">
      <div class="w-9 h-9 rounded-xl bg-blue-600 text-white grid place-items-center font-bold">CR</div>
      <div class="sb-label min-w-0">
        <h1 class="text-[15px] font-bold text-gray-800 leading-tight truncate">CRM Assistente</h1>
      </div>
    </div>

    <!-- Colapsar (desktop) / Fechar (mobile) -->
    <div class="flex items-center gap-1">
      <button id="sb-close" class="lg:hidden p-2 rounded-lg hover:bg-gray-100" title="Fechar menu" aria-label="Fechar menu">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
      <button id="sb-collapse" class="hidden lg:inline-flex p-2 rounded-lg hover:bg-gray-100" title="Colapsar/Expandir" aria-label="Colapsar/Expandir">
        <svg xmlns="http://www.w3.org/2000/svg" class="sb-chevron w-5 h-5 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- Navegação -->
  <nav class="px-3 py-3 space-y-2 flex-1 overflow-y-auto">
    <a href="/" class="<?= navClasses($isDashboard) ?>" title="Visão Geral">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isDashboard) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2 7-7 7 7 2 2v7a2 2 0 01-2 2h-5a2 2 0 01-2-2v-3H10v3a2 2 0 01-2 2H3a2 2 0 01-2-2v-7z"/>
      </svg>
      <span class="sb-label font-medium">Visão Geral</span>
    </a>

    <a href="/paciente" class="<?= navClasses($isPaciente) ?>" title="Pacientes">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isPaciente) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 14a4 4 0 10-8 0m12 6v-2a6 6 0 00-12 0v2M12 12a4 4 0 100-8 4 4 0 000 8z"/>
      </svg>
      <span class="sb-label font-medium">Pacientes</span>
    </a>

    <a href="/painel/aguardando" class="<?= navClasses($isAguardando) ?>" title="Aguardando atendimento">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isAguardando) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <span class="sb-label font-medium">Aguardando atendimento</span>
    </a>

    <a href="/kanban" class="<?= navClasses($isKanban) ?>" title="Leads (Kanban)">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isKanban) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h4v12H4zM10 6h4v12h-4zM16 6h4v12h-4z"/>
      </svg>
      <span class="sb-label font-medium">Leads</span>
    </a>

    <a href="/etapas" class="<?= navClasses($isEtapas) ?>" title="Etapas">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isEtapas) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h10M4 14h7M4 18h4"/>
      </svg>
      <span class="sb-label font-medium">Etapas</span>
    </a>

    <a href="/aprendizagem" class="<?= navClasses($isAprendizagem) ?>" title="Aprendizagem">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isAprendizagem) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 6l7 4-7 4-7-4 7-4zM5 14l7 4 7-4M5 10l7 4 7-4"/>
      </svg>
      <span class="sb-label font-medium">Aprendizagem</span>
    </a>

    <!-- NOVO: Tarefas -->
    <a href="/tarefas" class="<?= navClasses($isTarefas) ?>" title="Tarefas">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isTarefas) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <!-- clipboard + linhas -->
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12h6m-6 4h6M9 8h6M7 21h10a2 2 0 002-2V7l-5-4H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
      </svg>
      <span class="sb-label font-medium">Tarefas</span>
    </a>

    <a href="/notificacoes" class="<?= navClasses($isNotificacoes) ?>" title="Números de Notificação">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isNotificacoes) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1"/>
      </svg>
      <span class="sb-label font-medium">Notificações</span>
    </a>

    <a href="/agendamentos" class="<?= navClasses($isAgendamentos) ?>" title="Agendamentos">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isAgendamentos) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M3 11h18M5 20h14a2 2 0 002-2V7H3v11a2 2 0 002 2z"/>
      </svg>
      <span class="sb-label font-medium">Agendamentos</span>
    </a>

    <a href="/chat" class="<?= navClasses($isChats) ?>" title="Chats">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isChats) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8M8 14h5M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
      </svg>
      <span class="sb-label font-medium">Chats</span>
    </a>

    <a href="/whatsapp" class="<?= navClasses($isWhatsapp) ?>" title="Conectar WhatsApp">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isWhatsapp) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16.72 12.06a4.5 4.5 0 01-6.36 0l-1.07-1.07a1.5 1.5 0 010-2.12l.71-.71a1.5 1.5 0 012.12 0l.36.36a2.25 2.25 0 003.18 0l.36-.36a1.5 1.5 0 012.12 0l.71.71a1.5 1.5 0 010 2.12l-1.07 1.07z" />
      </svg>
      <span class="sb-label font-medium">Criar instancias</span>
    </a>

    <a href="/configuracaoia" class="<?= navClasses($isTestes) ?>" title="TESTES (Configuração IA)">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isTestes) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 3v2m0 2h12M18 5V3M6 5v2M4 9h16l-1 10H5L4 9z"/>
      </svg>
      <span class="sb-label font-medium">TESTES</span>
    </a>

    <a href="/logout" class="<?= navClasses(false) ?>" title="Sair">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses(false) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H7a2 2 0 01-2-2V7a2 2 0 012-2h4a2 2 0 012 2v1"/>
      </svg>
      <span class="sb-label font-medium">Sair</span>
    </a>
  </nav>

  <!-- Rodapé -->
  <div class="px-4 py-3 border-t border-gray-200">
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 rounded-full bg-gray-200 grid place-items-center">👩‍⚕️</div>
      <div class="sb-label min-w-0">
        <p class="text-[11px] text-gray-500 -mt-0.5 truncate">CRM Assistente</p>
      </div>
    </div>
  </div>
</aside>

<!-- Botão flutuante (mobile) -->
<button id="sb-open" class="lg:hidden fixed bottom-4 left-4 z-20 px-4 py-2 rounded-full bg-blue-600 text-white shadow" aria-label="Abrir menu">
  Menu
</button>

<script>
  const aside   = document.querySelector('aside.sidebar');
  const btnCol  = document.getElementById('sb-collapse');
  const btnOpen = document.getElementById('sb-open');
  const btnClose= document.getElementById('sb-close');
  const overlay = document.getElementById('sb-overlay');

  // Persistência do colapso (desktop)
  const LS_KEY = 'sb_collapsed_v1';
  const collapsed = localStorage.getItem(LS_KEY) === '1';
  if (collapsed) aside.classList.add('is-collapsed');

  btnCol?.addEventListener('click', () => {
    aside.classList.toggle('is-collapsed');
    localStorage.setItem(LS_KEY, aside.classList.contains('is-collapsed') ? '1' : '0');
  });

  // Drawer (mobile)
  function openDrawer(){ aside.classList.add('show'); overlay.classList.add('show'); document.body.style.overflow='hidden'; }
  function closeDrawer(){ aside.classList.remove('show'); overlay.classList.remove('show'); document.body.style.overflow=''; }

  btnOpen?.addEventListener('click', openDrawer);
  btnClose?.addEventListener('click', closeDrawer);
  overlay?.addEventListener('click', closeDrawer);

  // Acessibilidade: fechar com ESC no mobile
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape'){ closeDrawer(); } });
</script>
