<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <title>Aprendizagem | Base de Conhecimento da IA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    .fade-enter { opacity: 0; transform: translateY(6px); }
    .fade-enter-active { opacity: 1; transform: translateY(0); transition: all .18s ease; }
    .modal-show { display: flex; }
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
<body class="bg-gray-50 text-gray-800 h-screen w-screen font-sans">
  <div class="h-full w-full flex">
    <?= view('sidebar') ?>

    <!-- Painel principal -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Header Profissional -->
      <header class="header-gradient border-b border-gray-200 shadow-sm">
        <div class="px-8 py-6">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <!-- Título e navegação -->
            <div class="space-y-3">
              <nav class="text-sm font-medium" aria-label="Breadcrumb">
                <ol class="flex items-center gap-2 text-gray-500">
                  <li class="hover:text-gray-700 cursor-pointer transition-colors">CRM</li>
                  <li class="text-gray-300">/</li>
                  <li class="hover:text-gray-700 cursor-pointer transition-colors">Assistente</li>
                  <li class="text-gray-300">/</li>
                  <li class="text-gray-900 font-semibold">Aprendizagem</li>
                </ol>
              </nav>
              
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center shadow-lg">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                  </svg>
                </div>
                <div>
                  <h1 class="text-3xl font-bold tracking-tight text-gray-900">Base de Conhecimento da IA</h1>
                  <p class="text-gray-600 text-lg">Gerencie o conhecimento e treinamento do assistente inteligente</p>
                </div>
              </div>
            </div>
            
            <!-- Ações do header -->
            <div class="flex items-center gap-3">
              <div class="relative">
                <input id="busca" type="text" placeholder="Buscar conhecimento..." 
                       class="w-full sm:w-80 rounded-xl border border-gray-300 bg-white pl-12 pr-4 py-3 text-sm outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent hover-lift transition-all"/>
                <span class="pointer-events-none absolute left-4 top-3.5 text-gray-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M12.9 14.32a8 8 0 111.414-1.414l4.387 4.386a1 1 0 01-1.414 1.415l-4.387-4.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd"/>
                  </svg>
                </span>
                <div class="absolute right-3 top-3.5">
                  <kbd class="inline-flex items-center rounded border border-gray-200 px-1 font-sans text-xs text-gray-400">/</kbd>
                </div>
              </div>
              
              <a href="/aprendizagem/base" target="_blank" 
                 class="inline-flex items-center gap-2 px-4 py-3 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 font-medium hover-lift transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                API Base
              </a>
              
              <button onclick="openModal()" 
                      class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-purple-600 text-white font-medium hover:bg-purple-700 shadow-professional hover:shadow-professional-lg hover-lift transition-all duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Criar Conhecimento
              </button>
            </div>
          </div>
        </div>
      </header>

      <!-- Conteúdo Principal -->
      <main class="flex-1 overflow-y-auto px-8 py-8">
        <div class="card-gradient rounded-2xl shadow-professional border border-gray-200 overflow-hidden">
          <!-- Header da tabela com estatísticas -->
          <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-6">
                <div class="text-sm font-medium text-gray-700">
                  <span class="text-2xl font-bold text-gray-900" id="totalItens">0</span>
                  <span class="text-gray-600 ml-1">itens de conhecimento</span>
                </div>
                <div class="flex items-center gap-4 text-sm">
                  <div class="flex items-center gap-2">
                    <div class="h-3 w-3 bg-emerald-500 rounded-full"></div>
                    <span class="text-gray-600"><span id="ativosCount">0</span> ativos</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <div class="h-3 w-3 bg-gray-400 rounded-full"></div>
                    <span class="text-gray-600"><span id="inativosCount">0</span> inativos</span>
                  </div>
                </div>
              </div>
              
              <div class="flex items-center gap-3">
                <button onclick="exportarBase()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 font-medium hover-lift transition-all">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  Exportar
                </button>
              </div>
            </div>
          </div>

          <!-- Tabela -->
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 border-b border-gray-200">
                <tr class="text-left">
                  <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">Conhecimento</th>
                  <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">Tags</th>
                  <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">Status</th>
                  <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide">Data</th>
                  <th class="px-6 py-4 font-semibold text-gray-700 text-xs uppercase tracking-wide text-right">Ações</th>
                </tr>
              </thead>
              <tbody id="tbody" class="divide-y divide-gray-100 bg-white">
                <?php foreach (($itens ?? []) as $it): ?>
                  <tr class="hover:bg-gray-50 transition-colors group">
                    <td class="px-6 py-4">
                      <div class="flex items-start gap-3">
                        <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-purple-500 to-blue-600 flex items-center justify-center text-white font-bold text-sm shadow-lg flex-shrink-0">
                          <?= strtoupper(substr($it['titulo'] ?? 'K', 0, 1)) ?>
                        </div>
                        <div class="min-w-0 flex-1">
                          <div class="font-semibold text-gray-900 text-base mb-1 ellipsis" title="<?= esc($it['titulo']) ?>">
                            <?= esc($it['titulo']) ?>
                          </div>
                          <div class="text-sm text-gray-600 leading-relaxed line-clamp-2" title="<?= esc(mb_strimwidth($it['conteudo'],0,200,'…','UTF-8')) ?>">
                            <?= esc(mb_strimwidth($it['conteudo'],0,200,'…','UTF-8')) ?>
                          </div>
                          <div class="mt-2 text-xs text-gray-500">
                            <?= strlen($it['conteudo']) ?> caracteres
                          </div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4">
                      <div class="flex flex-wrap gap-1">
                        <?php 
                        $tags = array_filter(array_map('trim', explode(',', $it['tags'] ?? '')));
                        foreach (array_slice($tags, 0, 3) as $tag): 
                        ?>
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            <?= esc($tag) ?>
                          </span>
                        <?php endforeach; ?>
                        <?php if (count($tags) > 3): ?>
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                            +<?= count($tags) - 3 ?>
                          </span>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td class="px-6 py-4">
                      <?php if ((int)($it['ativo']??0)): ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-200">
                          <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                          </svg>
                          Ativo
                        </span>
                      <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                          <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"></path>
                          </svg>
                          Inativo
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                      <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-sm text-gray-700 font-medium">
                          <?= date('d/m/Y H:i', strtotime($it['criado_em'] ?? 'now')) ?>
                        </span>
                      </div>
                    </td>
                    <td class="px-6 py-4 text-right">
                      <div class="inline-flex items-center gap-2">
                        <button class="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 font-medium transition-all hover-lift" onclick="editar(<?= (int)$it['id'] ?>)">
                          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                          </svg>
                          Editar
                        </button>
                        <button class="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 font-medium transition-all hover-lift" onclick="confirmExcluir(<?= (int)$it['id'] ?>)">
                          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                          </svg>
                          Excluir
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                
                <?php if (empty($itens)): ?>
                  <tr>
                    <td colspan="5" class="px-8 py-20 text-center">
                      <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-purple-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                      </div>
                      <h3 class="text-lg font-semibold text-gray-800 mb-2">Nenhum conhecimento encontrado</h3>
                      <p class="text-gray-600 max-w-sm mx-auto mb-6">Comece criando o primeiro item de conhecimento para treinar sua IA.</p>
                      <button onclick="openModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Criar Primeiro Conhecimento
                      </button>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Modal Criar/Editar Profissional -->
  <div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/75 backdrop-blur-sm" onclick="closeModal()"></div>
    <div class="relative bg-white w-full max-w-4xl rounded-2xl shadow-2xl animate-fade-in overflow-hidden">
      <!-- Header do Modal -->
      <div class="flex items-center justify-between px-6 py-4 bg-purple-50 border-b border-purple-200">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-purple-600 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>
          </div>
          <div>
            <h2 class="text-xl font-semibold text-gray-900" id="modalTitle">Novo conhecimento</h2>
            <p class="text-sm text-gray-600">Adicione informações que a IA deve conhecer</p>
          </div>
        </div>
        <button class="text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg p-2 transition-colors" onclick="closeModal()" aria-label="Fechar">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Conteúdo do Modal -->
      <div class="px-6 py-6 max-h-[80vh] overflow-y-auto">
        <form id="formModal" onsubmit="return false;" class="space-y-6">
          <input type="hidden" id="id" name="id" />
          
          <!-- Título -->
          <div class="space-y-2">
            <label for="titulo" class="block text-sm font-medium text-gray-700">
              Título do Conhecimento <span class="text-red-500">*</span>
            </label>
            <input id="titulo" name="titulo" type="text" 
                   class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" 
                   placeholder="Ex: Procedimentos de agendamento, Política de cancelamento..."
                   required />
            <p class="text-xs text-gray-500">Seja específico e descritivo para facilitar a busca</p>
          </div>
          
          <!-- Conteúdo -->
          <div class="space-y-2">
            <label for="conteudo" class="block text-sm font-medium text-gray-700">
              Conteúdo do Conhecimento <span class="text-red-500">*</span>
            </label>
            <textarea id="conteudo" name="conteudo" rows="12" 
                      class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none transition-all" 
                      placeholder="Cole ou digite aqui todas as informações que a IA deve conhecer sobre este tópico..."></textarea>
            <div class="flex items-center justify-between text-xs text-gray-500">
              <span>Forneça informações detalhadas e precisas para melhor performance da IA</span>
              <span id="charCount">0 caracteres</span>
            </div>
          </div>
          
          <!-- Tags e Status -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-2">
              <label for="tags" class="block text-sm font-medium text-gray-700">Tags</label>
              <input id="tags" name="tags" type="text" 
                     class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all" 
                     placeholder="procedimentos, agenda, cancelamento"/>
              <p class="text-xs text-gray-500">Separe múltiplas tags por vírgula</p>
            </div>
            
            <div class="space-y-2">
              <label class="block text-sm font-medium text-gray-700">Status</label>
              <label class="inline-flex items-center gap-3 p-4 border border-gray-300 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
                <input type="checkbox" id="ativo" name="ativo" class="rounded text-purple-600 focus:ring-purple-500" checked />
                <div class="flex-1">
                  <div class="font-medium text-gray-900">Ativo</div>
                  <div class="text-xs text-gray-500">A IA poderá usar este conhecimento</div>
                </div>
              </label>
            </div>
          </div>
        </form>
      </div>

      <!-- Footer do Modal -->
      <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 border-t border-gray-200">
        <button type="button" class="px-5 py-2.5 rounded-xl bg-white border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 hover-lift transition-all" onclick="closeModal()">
          Cancelar
        </button>
        <button type="button" id="btnSalvar" class="px-5 py-2.5 rounded-xl bg-purple-600 text-white font-medium hover:bg-purple-700 shadow-professional hover:shadow-professional-lg hover-lift transition-all">
          Salvar Conhecimento
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
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Excluir conhecimento?</h3>
            <p class="text-gray-600 mb-6">Esta ação não pode ser desfeita. O conhecimento será removido permanentemente da base da IA.</p>
            
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
    // Sistema de Toast Aprimorado
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
        warning: '<svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>',
        default: '<svg class="h-5 w-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>'
      };
      
      el.className = `${base} ${colors[type] || colors.default} fade-enter`;
      el.innerHTML = `${icons[type] || icons.default}<span class="flex-1">${msg}</span>`;
      wrap.appendChild(el);
      requestAnimationFrame(()=> el.classList.add('fade-enter-active'));
      setTimeout(()=> el.remove(), 4000);
    }

    // Contador de caracteres para textarea
    document.getElementById('conteudo').addEventListener('input', (e) => {
      const length = e.target.value.length;
      document.getElementById('charCount').textContent = `${length.toLocaleString()} caracteres`;
      if (length > 10000) {
        document.getElementById('charCount').classList.add('text-red-600');
      } else {
        document.getElementById('charCount').classList.remove('text-red-600');
      }
    });

    // Atualizar contadores na tabela
    function atualizarContadores() {
      const rows = document.querySelectorAll('#tbody tr[data-status]');
      const total = rows.length;
      const ativos = document.querySelectorAll('#tbody tr[data-status="ativo"]').length;
      const inativos = total - ativos;
      
      document.getElementById('totalItens').textContent = total;
      document.getElementById('ativosCount').textContent = ativos;
      document.getElementById('inativosCount').textContent = inativos;
    }

    // Exportar base de conhecimento
    function exportarBase() {
      const rows = [];
      rows.push(['Título', 'Conteúdo', 'Tags', 'Status', 'Data de Criação']);
      
      document.querySelectorAll('#tbody tr').forEach(tr => {
        const cells = tr.querySelectorAll('td');
        if (cells.length >= 4) {
          const titulo = cells[0].querySelector('.font-semibold')?.textContent.trim() || '';
          const conteudo = cells[0].querySelector('.text-sm.text-gray-600')?.textContent.trim() || '';
          const tags = Array.from(cells[1].querySelectorAll('.bg-blue-100')).map(tag => tag.textContent.trim()).join(', ');
          const status = cells[2].querySelector('.text-emerald-800') ? 'Ativo' : 'Inativo';
          const data = cells[3].querySelector('span')?.textContent.trim() || '';
          
          rows.push([titulo, conteudo, tags, status, data]);
        }
      });
      
      const csv = rows.map(r => r.map(v => `"${String(v).replaceAll('"','""')}"`).join(',')).join('\n');
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `base_conhecimento_${new Date().toISOString().split('T')[0]}.csv`;
      a.click();
      URL.revokeObjectURL(url);
      
      toast('Base de conhecimento exportada com sucesso!', 'success');
    }

    // Busca com debounce + atalho '/'
    let buscaTimer = null;
    const buscaEl = document.getElementById('busca');
    
    buscaEl.addEventListener('input', () => { 
      clearTimeout(buscaTimer); 
      buscaTimer = setTimeout(() => filtrar(buscaEl.value), 300); 
    });
    
    addEventListener('keydown', (e) => { 
      if(e.key === '/' && document.activeElement !== buscaEl) { 
        e.preventDefault(); 
        buscaEl.focus(); 
      } 
    });
    
    function filtrar(q) {
      q = (q || '').toLowerCase();
      let visibleCount = 0;
      
      document.querySelectorAll('#tbody tr').forEach(tr => {
        const t = tr.innerText.toLowerCase();
        const isVisible = t.includes(q);
        tr.style.display = isVisible ? '' : 'none';
        if (isVisible) visibleCount++;
      });
      
      // Mostrar/esconder empty state baseado nos resultados filtrados
      const emptyRow = document.querySelector('#tbody tr[data-empty]');
      if (emptyRow) {
        emptyRow.style.display = visibleCount === 0 ? '' : 'none';
      }
    }

    // Modal helpers
    function openModal() {
      document.getElementById('formModal').reset();
      document.getElementById('id').value = '';
      document.getElementById('modalTitle').innerText = 'Novo conhecimento';
      document.getElementById('charCount').textContent = '0 caracteres';
      document.getElementById('ativo').checked = true;
      
      const m = document.getElementById('modal');
      m.classList.remove('hidden');
      m.classList.add('modal-show');
      document.body.classList.add('overflow-hidden');
      
      // Focar no campo título
      setTimeout(() => {
        document.getElementById('titulo').focus();
      }, 100);
    }
    
    function closeModal() {
      const m = document.getElementById('modal');
      m.classList.add('hidden');
      m.classList.remove('modal-show');
      document.body.classList.remove('overflow-hidden');
    }

    // Editar
    async function editar(id) {
      try {
        // Loading state
        const loadingToast = toast('Carregando dados...', 'info');
        
        const r = await fetch(`/aprendizagem/obter/${id}`);
        const j = await r.json();
        
        if (!j?.ok) throw new Error('Falha ao carregar dados');
        
        const d = j.data;
        document.getElementById('id').value = d.id;
        document.getElementById('titulo').value = d.titulo || '';
        document.getElementById('conteudo').value = d.conteudo || '';
        document.getElementById('tags').value = d.tags || '';
        document.getElementById('ativo').checked = Number(d.ativo) === 1;
        document.getElementById('modalTitle').innerText = 'Editar conhecimento';
        
        // Atualizar contador de caracteres
        const length = (d.conteudo || '').length;
        document.getElementById('charCount').textContent = `${length.toLocaleString()} caracteres`;
        
        const m = document.getElementById('modal');
        m.classList.remove('hidden');
        m.classList.add('modal-show');
        document.body.classList.add('overflow-hidden');
        
        toast('Dados carregados com sucesso!', 'success');
      } catch(e) { 
        toast('Não foi possível carregar o item', 'error'); 
      }
    }

    // Salvar
    document.getElementById('btnSalvar').addEventListener('click', async () => {
      const titulo = document.getElementById('titulo').value.trim();
      const conteudo = document.getElementById('conteudo').value.trim();
      
      if (!titulo) {
        toast('Por favor, preencha o título', 'warning');
        document.getElementById('titulo').focus();
        return;
      }
      
      if (!conteudo) {
        toast('Por favor, preencha o conteúdo', 'warning');
        document.getElementById('conteudo').focus();
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
        const form = new FormData(document.getElementById('formModal'));
        const r = await fetch('/aprendizagem/salvar', { method: 'POST', body: form });
        const j = await r.json();
        
        if (!j?.ok) throw new Error(j?.message || 'Falha ao salvar');
        
        toast('Conhecimento salvo com sucesso!', 'success');
        setTimeout(() => location.reload(), 800);
      } catch(e) { 
        toast(e.message || 'Erro ao salvar', 'error'); 
      } finally {
        btnSalvar.disabled = false;
        btnSalvar.textContent = originalText;
      }
    });

    // Excluir
    let idExcluir = null;
    
    function confirmExcluir(id) {
      idExcluir = id;
      const m = document.getElementById('confirmDelete');
      m.classList.remove('hidden');
      m.classList.add('modal-show');
      document.body.classList.add('overflow-hidden');
    }
    
    function fecharConfirm() {
      idExcluir = null;
      const m = document.getElementById('confirmDelete');
      m.classList.add('hidden');
      m.classList.remove('modal-show');
      document.body.classList.remove('overflow-hidden');
    }
    
    document.getElementById('btnConfirmDelete').addEventListener('click', async () => {
      if (!idExcluir) return;
      
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
        fd.append('id', idExcluir);
        const r = await fetch('/aprendizagem/excluir', { method: 'POST', body: fd });
        const j = await r.json();
        
        if (!j?.ok) throw new Error(j?.message || 'Falha ao excluir');
        
        toast('Conhecimento excluído com sucesso!', 'success');
        setTimeout(() => location.reload(), 600);
      } catch(e) { 
        toast(e.message || 'Erro ao excluir', 'error'); 
      } finally {
        btnConfirm.disabled = false;
        btnConfirm.textContent = originalText;
        fecharConfirm();
      }
    });

    // Fechar modais com ESC
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeModal();
        fecharConfirm();
      }
    });

    // Inicialização
    document.addEventListener('DOMContentLoaded', () => {
      atualizarContadores();
      
      // Adicionar atributos data-status para facilitar contagem
      document.querySelectorAll('#tbody tr').forEach(tr => {
        const statusCell = tr.querySelector('td:nth-child(3)');
        if (statusCell) {
          const isActive = statusCell.querySelector('.text-emerald-800');
          tr.setAttribute('data-status', isActive ? 'ativo' : 'inativo');
        }
      });
    });
  </script>
</body>
</html>