<?php
// garanta que o helper está disponível
helper('url');

// helperzinho para classes de ativo/inativo
function navClasses(bool $active): string {
  return $active
    ? 'flex items-center px-4 py-3 rounded-xl bg-blue-100 text-blue-700 font-semibold transition-all duration-200 group'
    : 'flex items-center px-4 py-3 rounded-xl text-gray-700 hover:bg-blue-100 hover:text-blue-700 transition-all duration-200 group';
}
function iconClasses(bool $active): string {
  return $active
    ? 'w-5 h-5 mr-3 text-blue-600'
    : 'w-5 h-5 mr-3 text-blue-400 group-hover:text-blue-600';
}
?>

<aside class="w-64 bg-white shadow-md border-r border-gray-200">
  <div class="px-6 py-4 border-b border-gray-200">
    <h1 class="text-xl font-bold text-gray-800">CRM Assistente</h1>
  </div>

  <nav class="mt-4 space-y-2">

    <?php
      $isDashboard   = url_is('/') || url_is('dashboard') || url_is('dashboard/*');
      $isPaciente    = url_is('paciente') || url_is('paciente/*');
      $isAguardando  = url_is('painel/aguardando') || url_is('painel/aguardando/*');
      $isKanban      = url_is('kanban') || url_is('kanban/*');
      $isEtapas      = url_is('etapas') || url_is('etapas/*');
      $isChats       = url_is('chat') || url_is('chat/*');
      $isTestes      = url_is('configuracaoia') || url_is('configuracaoia/*');
    ?>

    <!-- Visão Geral / Dashboard -->
    <a href="/" class="<?= navClasses($isDashboard) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isDashboard) ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h4l3 8 4-16 3 8h4" />
      </svg>
      <span class="font-medium">Visão Geral</span>
    </a>

    <!-- Pacientes -->
    <a href="/paciente" class="<?= navClasses($isPaciente) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isPaciente) ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A4 4 0 0116.95 7.05a4 4 0 00-5.656-5.657L5.12 7.05a4 4 0 010 5.657z" />
      </svg>
      <span class="font-medium">Pacientes</span>
    </a>

    <!-- Aguardando atendimento -->
    <a href="/painel/aguardando" class="<?= navClasses($isAguardando) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isAguardando) ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A4 4 0 0116.95 7.05a4 4 0 00-5.656-5.657L5.12 7.05a4 4 0 010 5.657z" />
      </svg>
      <span class="font-medium">Aguardando atendimento</span>
    </a>

    <!-- Leads (Kanban) -->
    <a href="/kanban" class="<?= navClasses($isKanban) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isKanban) ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A4 4 0 0116.95 7.05a4 4 0 00-5.656-5.657L5.12 7.05a4 4 0 010 5.657z" />
      </svg>
      <span class="font-medium">Leads</span>
    </a>

    <!-- Etapas -->
    <a href="/etapas" class="<?= navClasses($isEtapas) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isEtapas) ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 14v1m8-8h1M4 12H3m15.364-6.364l.707.707M6.343 17.657l-.707.707m12.728 0l.707-.707M6.343 6.343l-.707-.707" />
      </svg>
      <span class="font-medium">Etapas</span>
    </a>
 <a href="/notificacoes" class="<?= navClasses($isEtapas) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isEtapas) ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 14v1m8-8h1M4 12H3m15.364-6.364l.707.707M6.343 17.657l-.707.707m12.728 0l.707-.707M6.343 6.343l-.707-.707" />
      </svg>
      <span class="font-medium">Numero de notificações</span>
    </a>

 <a href="/agendamentos" class="<?= navClasses($isEtapas) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isEtapas) ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 14v1m8-8h1M4 12H3m15.364-6.364l.707.707M6.343 17.657l-.707.707m12.728 0l.707-.707M6.343 6.343l-.707-.707" />
      </svg>
      <span class="font-medium">Agendamentos</span>
    </a>

    <!-- Chats -->
    <a href="/chat" class="<?= navClasses($isChats) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isChats) ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 14v1m8-8h1M4 12H3m15.364-6.364l.707.707M6.343 17.657l-.707.707m12.728 0l.707-.707M6.343 6.343l-.707-.707" />
      </svg>
      <span class="font-medium">Chats</span>
    </a>

    <!-- Testes (Configuração IA) -->
    <a href="/configuracaoia" class="<?= navClasses($isTestes) ?>">
      <svg xmlns="http://www.w3.org/2000/svg" class="<?= iconClasses($isTestes) ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 14v1m8-8h1M4 12H3m15.364-6.364l.707.707M6.343 17.657l-.707.707m12.728 0l.707-.707M6.343 6.343l-.707-.707" />
      </svg>
      <span class="font-medium">TESTES</span>
    </a>

  </nav>
</aside>
