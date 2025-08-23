<?php
/** @var array $etapas */
$etapas = $etapas ?? [];
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>CRM Assistente • Etapas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
          borderRadius: { 'xl': '0.75rem', '2xl': '1rem' },
          boxShadow: { 
            soft: '0 4px 16px rgba(0,0,0,0.06)',
            professional: '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)',
            'professional-lg': '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)'
          }
        }
      }
    }
  </script>
  <style>
    .fade-enter { opacity: 0; transform: translateY(6px); }
    .fade-enter-active { opacity: 1; transform: translateY(0); transition: all .18s ease; }
    .ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .header-gradient { background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); }
    .card-gradient { background: linear-gradient(135deg, #ffffff 0%, #fcfcfd 100%); }
    .hover-lift { transition: all 0.2s ease; }
    .hover-lift:hover { transform: translateY(-1px); box-shadow: 0 4px 12px -1px rgb(0 0 0 / 0.15); }
    .animate-fade-in { animation: fadeIn 0.5s ease-out; }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">
<div class="min-h-screen flex">
  <?= view('sidebar') ?>

  <main class="flex-1 overflow-y-auto">
    <!-- Header Profissional -->
    <header class="header-gradient border-b border-gray-200 shadow-sm">
      <div class="max-w-7xl mx-auto px-8 py-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <!-- Título e navegação -->
          <div class="space-y-3">
            <nav class="text-sm font-medium" aria-label="Breadcrumb">
              <ol class="flex items-center gap-2 text-gray-500">
                <li class="hover:text-gray-700 cursor-pointer transition-colors">CRM</li>
                <li class="text-gray-300">/</li>
                <li class="hover:text-gray-700 cursor-pointer transition-colors">Assistente</li>
                <li class="text-gray-300">/</li>
                <li class="text-gray-900 font-semibold">Gerenciar Etapas</li>
              </ol>
            </nav>
            
            <div class="flex items-center gap-4">
              <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-indigo-600 to-purple-600 flex items-center justify-center shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
              </div>
              <div>
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">Gerenciar Etapas</h1>
                <p class="text-gray-600 text-lg">Configure o comportamento da IA em cada fase do atendimento</p>
              </div>
            </div>
          </div>
          
          <!-- Ações do header -->
          <div class="flex items-center gap-3">
            <div class="text-sm text-gray-600 bg-gray-100 px-3 py-2 rounded-full">
              <?= count($etapas) ?> etapa<?= count($etapas) !== 1 ? 's' : '' ?> cadastrada<?= count($etapas) !== 1 ? 's' : '' ?>
            </div>
            <button id="btnNova" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-indigo-600 text-white font-medium hover:bg-indigo-700 shadow-professional hover:shadow-professional-lg hover-lift transition-all duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
              Nova Etapa
            </button>
          </div>
        </div>
      </div>
    </header>

    <div class="max-w-7xl mx-auto px-8 py-8">
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        <!-- Formulário -->
        <section class="xl:col-span-1">
          <div class="card-gradient rounded-2xl shadow-professional border border-gray-200 overflow-hidden">
            <!-- Header do formulário -->
            <div class="px-6 py-4 bg-indigo-50 border-b border-indigo-200">
              <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-lg bg-indigo-600 flex items-center justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </div>
                <div>
                  <h2 class="font-bold text-lg text-gray-900" id="formTitle">Nova Etapa</h2>
                  <p class="text-sm text-gray-600">Configure os parâmetros da etapa</p>
                </div>
              </div>
            </div>

            <!-- Conteúdo do formulário -->
            <div class="p-6">
              <form action="<?= base_url('/etapas/salvar') ?>" method="post" id="etapaForm" class="space-y-6">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="id">

                <!-- Nome da etapa -->
                <div class="space-y-2">
                  <label class="block text-sm font-medium text-gray-700">Nome da etapa <span class="text-red-500">*</span></label>
                  <input type="text" name="etapa_atual" id="etapa_atual"
                         class="w-full rounded-xl border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all hover-lift"
                         placeholder="ex.: entrada, agendamento, humano" required>
                  <p class="text-xs text-gray-500">Identificador único para esta etapa do fluxo</p>
                </div>

                <!-- Configurações básicas -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Tempo de resposta (segundos)</label>
                    <input type="number" name="tempo_resposta" id="tempo_resposta" min="0" max="60" value="5"
                           class="w-full rounded-xl border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all hover-lift" required>
                    <p class="text-xs text-gray-500">Delay antes da IA responder</p>
                  </div>

                  <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">IA pode responder?</label>
                    <label class="inline-flex items-center gap-3 p-3 border border-gray-300 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
                      <input type="checkbox" name="ia_pode_responder" id="ia_pode_responder" class="rounded text-indigo-600 focus:ring-indigo-500" checked>
                      <div class="flex-1">
                        <div class="font-medium text-gray-900 text-sm">Ativar IA</div>
                        <div class="text-xs text-gray-500">IA responde nesta etapa</div>
                      </div>
                    </label>
                  </div>
                </div>

                <!-- Prompt base -->
                <div class="space-y-2">
                  <div class="flex items-center justify-between">
                    <label class="block text-sm font-medium text-gray-700">Prompt base (opcional)</label>
                    <button type="button" id="btnPromptSugestao" class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                      </svg>
                      Inserir sugestão
                    </button>
                  </div>
                  <textarea name="prompt_base" id="prompt_base" rows="6"
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none transition-all hover-lift"
                            placeholder="Instruções específicas para a IA nesta etapa..."></textarea>
                  <p class="text-xs text-gray-500">Contexto e regras específicas para esta etapa</p>
                </div>

                <!-- Opções avançadas -->
                <div class="space-y-3">
                  <h3 class="text-sm font-medium text-gray-700 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Configurações Avançadas
                  </h3>
                  
                  <div class="grid grid-cols-1 gap-3">
                    <label class="inline-flex items-center gap-3 p-3 border border-gray-300 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
                      <input type="checkbox" name="modo_formal" id="modo_formal" class="rounded text-indigo-600 focus:ring-indigo-500">
                      <div class="flex-1">
                        <div class="font-medium text-gray-900 text-sm">Modo formal</div>
                        <div class="text-xs text-gray-500">Linguagem mais formal e profissional</div>
                      </div>
                    </label>
                    
                    <label class="inline-flex items-center gap-3 p-3 border border-gray-300 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
                      <input type="checkbox" name="permite_respostas_longas" id="permite_respostas_longas" class="rounded text-indigo-600 focus:ring-indigo-500">
                      <div class="flex-1">
                        <div class="font-medium text-gray-900 text-sm">Respostas longas</div>
                        <div class="text-xs text-gray-500">Permite respostas detalhadas</div>
                      </div>
                    </label>
                    
                    <label class="inline-flex items-center gap-3 p-3 border border-gray-300 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
                      <input type="checkbox" name="permite_redirecionamento" id="permite_redirecionamento" class="rounded text-indigo-600 focus:ring-indigo-500" checked>
                      <div class="flex-1">
                        <div class="font-medium text-gray-900 text-sm">Redirecionamento</div>
                        <div class="text-xs text-gray-500">Pode transferir para humano</div>
                      </div>
                    </label>
                  </div>
                </div>

                <!-- Observação -->
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                  <div class="flex items-start gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-sm text-amber-800">
                      <p class="font-medium">Importante:</p>
                      <p>Quando <strong>"IA pode responder"</strong> estiver desmarcado, a IA ficará em silêncio nesta etapa e apenas humanos poderão responder.</p>
                    </div>
                  </div>
                </div>

                <!-- Botões de ação -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                  <button type="button" id="btnCancelar" class="px-5 py-2.5 rounded-xl bg-white border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 hover-lift transition-all">
                    Cancelar
                  </button>
                  <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-medium hover:bg-indigo-700 shadow-professional hover:shadow-professional-lg hover-lift transition-all duration-200">
                    Salvar Etapa
                  </button>
                </div>
              </form>
            </div>
          </div>
        </section>

        <!-- Tabela -->
        <section class="xl:col-span-2">
          <div class="card-gradient rounded-2xl shadow-professional border border-gray-200 overflow-hidden">
            <!-- Header da tabela -->
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                  <h2 class="font-bold text-lg text-gray-900">Etapas Cadastradas</h2>
                  <p class="text-sm text-gray-600">Configure o comportamento da IA em cada etapa</p>
                </div>
                <div class="relative">
                  <input id="busca" type="text" placeholder="Buscar etapas..."
                         class="rounded-xl border border-gray-300 bg-white pl-10 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent hover-lift transition-all">
                  <span class="pointer-events-none absolute left-3 top-3 text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M12.9 14.32a8 8 0 111.414-1.414l4.387 4.386a1 1 0 01-1.414 1.415l-4.387-4.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd"/>
                    </svg>
                  </span>
                  <div class="absolute right-3 top-3">
                    <kbd class="inline-flex items-center rounded border border-gray-200 px-1 font-sans text-xs text-gray-400">/</kbd>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tabela -->
            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                  <tr class="text-left">
                    <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide w-12">#</th>
                    <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">Etapa</th>
                    <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">Tempo</th>
                    <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">IA</th>
                    <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">Configurações</th>
                    <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">Criado em</th>
                    <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide text-right">Ações</th>
                  </tr>
                </thead>
                <tbody id="tbodyEtapas" class="divide-y divide-gray-100 bg-white">
                  <?php if (!empty($etapas)): ?>
                    <?php foreach ($etapas as $i => $e): ?>
                      <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-gray-500 font-medium"><?= $i+1 ?></td>
                        <td class="px-6 py-4">
                          <div class="flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                              <span class="text-indigo-600 font-bold text-xs"><?= strtoupper(substr($e['etapa_atual'], 0, 2)) ?></span>
                            </div>
                            <div>
                              <div class="font-semibold text-gray-900"><?= esc($e['etapa_atual']) ?></div>
                              <?php if (!empty($e['prompt_base'])): ?>
                                <div class="text-xs text-gray-500">Com prompt personalizado</div>
                              <?php endif; ?>
                            </div>
                          </div>
                        </td>
                        <td class="px-6 py-4">
                          <span class="inline-flex items-center gap-1 text-sm text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <?= esc($e['tempo_resposta'] ?? '-') ?>s
                          </span>
                        </td>
                        <td class="px-6 py-4">
                          <?php if (!empty($e['ia_pode_responder'])): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-200">
                              <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                              </svg>
                              Ativa
                            </span>
                          <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                              <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"></path>
                              </svg>
                              Inativa
                            </span>
                          <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                          <div class="flex flex-wrap gap-1">
                            <?php if (!empty($e['modo_formal'])): ?>
                              <span class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">Formal</span>
                            <?php endif; ?>
                            <?php if (!empty($e['permite_respostas_longas'])): ?>
                              <span class="px-2 py-0.5 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">Longas</span>
                            <?php endif; ?>
                            <?php if (!empty($e['permite_redirecionamento'])): ?>
                              <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded-full text-xs font-medium">Redir.</span>
                            <?php endif; ?>
                          </div>
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                          <?= date('d/m/Y H:i', strtotime($e['criado_em'] ?? 'now')) ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                          <div class="inline-flex items-center gap-2">
                            <button class="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 font-medium transition-all hover-lift"
                                    onclick='editarEtapa(<?= json_encode($e, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'>
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                              </svg>
                              Editar
                            </button>

                            <form action="<?= base_url('/etapas/excluir') ?>" method="post" class="inline needs-confirm" data-nome="<?= esc($e['etapa_atual']) ?>">
                              <?= csrf_field() ?>
                              <input type="hidden" name="etapa_atual" value="<?= esc($e['etapa_atual']) ?>">
                              <button type="submit" class="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 font-medium transition-all hover-lift">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                                Excluir
                              </button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="7" class="px-8 py-20 text-center">
                        <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-100">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                          </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Nenhuma etapa cadastrada</h3>
                        <p class="text-gray-600 max-w-sm mx-auto mb-6">Comece criando sua primeira etapa para configurar o comportamento da IA.</p>
                        <button onclick="document.getElementById('btnNova').click()" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition-colors">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                          </svg>
                          Criar Primeira Etapa
                        </button>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>
  </main>
</div>

<!-- Modal de confirmação -->
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
          <h4 class="text-lg font-semibold text-gray-900 mb-2">Excluir etapa?</h4>
          <p id="confirmText" class="text-gray-600 mb-6">Esta ação não pode ser desfeita.</p>
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
<div id="toasts" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

<script>
// ====== Sistema de Toast Melhorado ======
function toast(msg, type='default'){
  const wrap = document.getElementById('toasts');
  const el = document.createElement('div');
  const base = 'px-6 py-4 rounded-xl shadow-lg text-sm text-white flex items-center gap-3 max-w-md';
  
  const colors = {
    success: 'bg-emerald-600',
    error: 'bg-red-600', 
    warning: 'bg-yellow-600',
    info: 'bg-blue-600',
    default: 'bg-gray-900'
  };
  
  const icons = {
    success: '<svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>',
    error: '<svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>',
    default: '<svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>'
  };
  
  el.className = `${base} ${colors[type] || colors.default} fade-enter`;
  el.innerHTML = `${icons[type] || icons.default}<span class="flex-1">${msg}</span>`;
  wrap.appendChild(el);
  requestAnimationFrame(() => el.classList.add('fade-enter-active'));
  setTimeout(() => el.remove(), 4000);
}

// ====== Filtro da tabela (debounce + atalho '/') ======
let buscaTimer=null; 
const buscaEl=document.getElementById('busca');

buscaEl.addEventListener('input', function(){ 
  clearTimeout(buscaTimer); 
  buscaTimer=setTimeout(()=>filtrar(this.value), 300); 
});

document.addEventListener('keydown', (e)=>{ 
  if(e.key==='/' && document.activeElement!==buscaEl){ 
    e.preventDefault(); 
    buscaEl.focus(); 
  } 
});

function filtrar(q){
  q = (q||'').toLowerCase();
  let visibleCount = 0;
  
  document.querySelectorAll('#tbodyEtapas tr').forEach(tr => {
    const text = tr.innerText.toLowerCase();
    const isVisible = text.includes(q);
    tr.style.display = isVisible ? '' : 'none';
    if (isVisible) visibleCount++;
  });
  
  // Feedback visual para busca vazia
  if (q && visibleCount === 0) {
    // Poderia adicionar uma mensagem "Nenhuma etapa encontrada" aqui
  }
}

// ====== Preencher formulário para edição ======
function editarEtapa(e) {
  document.getElementById('formTitle').textContent = 'Editar Etapa';
  document.getElementById('id').value = e.id || '';
  document.getElementById('etapa_atual').value = e.etapa_atual || '';
  document.getElementById('tempo_resposta').value = e.tempo_resposta ?? 5;
  document.getElementById('prompt_base').value = e.prompt_base || '';
  document.getElementById('modo_formal').checked = !!Number(e.modo_formal||0);
  document.getElementById('permite_respostas_longas').checked = !!Number(e.permite_respostas_longas||0);
  document.getElementById('permite_redirecionamento').checked = !!Number(e.permite_redirecionamento||0);
  document.getElementById('ia_pode_responder').checked = !!Number(e.ia_pode_responder ?? 1);
  
  // Scroll suave para o formulário
  document.getElementById('etapaForm').scrollIntoView({ 
    behavior: 'smooth', 
    block: 'start' 
  });
  
  // Focar no campo nome após um delay
  setTimeout(() => {
    document.getElementById('etapa_atual').focus();
  }, 500);
  
  toast('Dados carregados para edição', 'info');
}

// ====== Reset/Nova ======
const formEl = document.getElementById('etapaForm');

document.getElementById('btnCancelar').addEventListener('click', () => resetForm());
document.getElementById('btnNova').addEventListener('click', () => resetForm());

function resetForm(){
  document.getElementById('formTitle').textContent = 'Nova Etapa';
  formEl.reset();
  document.getElementById('tempo_resposta').value = 5;
  document.getElementById('permite_redirecionamento').checked = true;
  document.getElementById('ia_pode_responder').checked = true;
  
  // Scroll para o formulário
  document.getElementById('etapaForm').scrollIntoView({ 
    behavior: 'smooth', 
    block: 'start' 
  });
  
  // Focar no campo nome
  setTimeout(() => {
    document.getElementById('etapa_atual').focus();
  }, 300);
}

// ====== Confirmação de exclusão ======
let formToDelete = null;

function abrirConfirm(nome, form){
  formToDelete = form;
  document.getElementById('confirmText').textContent = `Excluir a etapa "${nome}"? Esta ação não pode ser desfeita e pode afetar o funcionamento da IA.`;
  const m=document.getElementById('confirmDelete'); 
  m.classList.remove('hidden'); 
  m.classList.add('flex');
  document.body.classList.add('overflow-hidden');
}

function fecharConfirm(){ 
  formToDelete=null; 
  const m=document.getElementById('confirmDelete'); 
  m.classList.add('hidden'); 
  m.classList.remove('flex');
  document.body.classList.remove('overflow-hidden');
}

document.getElementById('btnConfirmDelete').addEventListener('click', ()=>{ 
  if(formToDelete){ 
    // Loading state no botão
    const btn = document.getElementById('btnConfirmDelete');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = `
      <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      Excluindo...
    `;
    
    formToDelete.submit(); 
  } 
});

// Intercepta forms de exclusão com .needs-confirm
document.addEventListener('submit', (e)=>{
  const f = e.target.closest('form.needs-confirm');
  if(!f) return;
  e.preventDefault();
  abrirConfirm(f.dataset.nome || 'etapa', f);
});

// ====== Sugestão de prompt ======
const btnPromptSugestao = document.getElementById('btnPromptSugestao');
btnPromptSugestao?.addEventListener('click', ()=>{
  const etapaInput = document.getElementById('etapa_atual');
  const etapa = etapaInput.value.trim() || 'etapa';
  
  const sugestoes = {
    'entrada': 'Você é a recepcionista virtual da clínica. Seja acolhedora e profissional. Cumprimente o paciente, identifique a necessidade (agendamento, dúvidas, etc.) e colete informações básicas como nome e telefone. Não forneça preços sem autorização.',
    'agendamento': 'Você está auxiliando no agendamento. Colete: nome completo, telefone, procedimento desejado, e disponibilidade de horários. Explique que a confirmação será feita por um atendente. Seja clara sobre documentos necessários.',
    'humano': 'Você deve transferir esta conversa para um atendente humano. Informe educadamente: "Vou transferir você para um de nossos especialistas que poderá te ajudar melhor. Por favor, aguarde um momento." Não responda mais perguntas.',
    'duvidas': 'Você responde dúvidas gerais sobre a clínica: horários, localização, procedimentos básicos. Para questões específicas sobre preços, diagnósticos ou agendamentos, transfira para um humano. Seja informativa mas prudente.',
    'pos_consulta': 'Você auxilia pacientes após consultas. Tire dúvidas sobre receitas, orientações médicas gerais, agendamento de retorno. Para questões clínicas específicas, sempre oriente a entrar em contato com o médico.'
  };
  
  const base = sugestoes[etapa.toLowerCase()] || `Você é a assistente da clínica na etapa "${etapa}". Responda no WhatsApp de forma gentil e objetiva.\nContexto: etapa "${etapa}".\nRegras: não forneça preços nem marque consultas sem solicitação. Se perguntarem por valores/agendamento, oriente que um humano continuará o atendimento.`;
  
  const el = document.getElementById('prompt_base');
  el.value = base; 
  el.focus();
  
  // Animar o campo
  el.classList.add('ring-2', 'ring-indigo-500');
  setTimeout(() => {
    el.classList.remove('ring-2', 'ring-indigo-500');
  }, 1000);
  
  toast('Sugestão de prompt inserida!','success');
});

// ====== Validação do formulário ======
formEl.addEventListener('submit', function(e) {
  const etapa = document.getElementById('etapa_atual').value.trim();
  const tempo = document.getElementById('tempo_resposta').value;
  
  if (!etapa) {
    e.preventDefault();
    toast('Por favor, preencha o nome da etapa', 'error');
    document.getElementById('etapa_atual').focus();
    return;
  }
  
  if (tempo < 0 || tempo > 60) {
    e.preventDefault();
    toast('Tempo de resposta deve estar entre 0 e 60 segundos', 'error');
    document.getElementById('tempo_resposta').focus();
    return;
  }
  
  // Loading state no botão de envio
  const submitBtn = this.querySelector('button[type="submit"]');
  const originalText = submitBtn.textContent;
  submitBtn.disabled = true;
  submitBtn.innerHTML = `
    <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    Salvando...
  `;
});

// ====== Fechar modal com ESC ======
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    fecharConfirm();
  }
});

// ====== Auto-resize do textarea ======
const promptTextarea = document.getElementById('prompt_base');
promptTextarea.addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = (this.scrollHeight) + 'px';
});

// ====== Inicialização ======
document.addEventListener('DOMContentLoaded', () => {
  // Se há mensagens de sucesso/erro do backend, mostrá-las
  <?php if (session()->getFlashdata('success')): ?>
    toast('<?= esc(session()->getFlashdata('success')) ?>', 'success');
  <?php endif; ?>
  
  <?php if (session()->getFlashdata('error')): ?>
    toast('<?= esc(session()->getFlashdata('error')) ?>', 'error');
  <?php endif; ?>
});
</script>
</body>
</html>