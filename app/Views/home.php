<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard CRM | Sistema Profissional</title>
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
            soft: '0 4px 16px rgba(0,0,0,.06)',
            professional: '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)',
            'professional-lg': '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)'
          }
        }
      }
    }
  </script>
  <style>
    .ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .header-gradient { background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); }
    .card-gradient { background: linear-gradient(135deg, #ffffff 0%, #fcfcfd 100%); }
    .hover-lift { transition: all 0.2s ease; }
    .hover-lift:hover { transform: translateY(-1px); box-shadow: 0 4px 12px -1px rgb(0 0 0 / 0.15); }
    .kpi-card { transition: all 0.3s ease; }
    .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -3px rgb(0 0 0 / 0.1); }
    .progress-bar { transition: width 1.5s ease-out; }
    .animate-fade-in { animation: fadeIn 0.5s ease-out; }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">
  <div class="flex min-h-screen">
    <?= view('sidebar') ?>

    <main class="flex-1 overflow-y-auto">
      <!-- Header Profissional -->
      <section class="header-gradient border-b border-gray-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-8 py-8">
          <div class="flex items-center justify-between">
            <div class="space-y-4">
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center shadow-lg">
                  <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                  </svg>
                </div>
                <div>
                  <h1 class="text-3xl font-bold text-gray-900 mb-1">Dashboard Operacional</h1>
                  <p class="text-gray-600 text-lg">Métricas de performance e insights em tempo real</p>
                </div>
              </div>
              <div class="flex items-center gap-3 text-sm">
                <div class="flex items-center gap-2 px-3 py-1.5 bg-green-50 rounded-full border border-green-200">
                  <div class="h-2 w-2 bg-green-500 rounded-full animate-pulse"></div>
                  <span class="text-green-700 font-medium">Sistema Online</span>
                </div>
                <span class="text-gray-300">•</span>
                <span id="lastUpdate" class="text-gray-500 font-medium">Atualizando...</span>
              </div>
            </div>
            <div class="flex items-center gap-4">
              <select id="filtroPeriodo" class="border border-gray-300 rounded-xl px-4 py-3 text-sm font-medium bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-professional hover-lift transition-all">
                <option value="today">Hoje</option>
                <option value="7d">Últimos 7 dias</option>
                <option value="30d" selected>Últimos 30 dias</option>
              </select>
              <button id="btnRefresh" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 shadow-professional hover:shadow-professional-lg hover-lift transition-all duration-200">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Atualizar
              </button>
            </div>
          </div>
        </div>
      </section>

      <div class="max-w-7xl mx-auto px-8 py-8 space-y-8">
        <!-- KPIs Grid -->
        <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 animate-fade-in">
          <!-- Taxa de Resposta -->
          <article class="kpi-card card-gradient rounded-2xl shadow-professional border border-gray-200 p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-orange-500/10 to-orange-600/10 rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
              <div class="flex items-center justify-between mb-4">
                <div class="h-12 w-12 rounded-xl bg-orange-100 flex items-center justify-center">
                  <svg class="h-6 w-6 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <div class="text-right">
                  <h3 class="text-sm font-semibold text-gray-700">Taxa de Resposta</h3>
                  <p class="text-xs text-gray-500">Engajamento médio</p>
                </div>
              </div>
              <div class="space-y-2">
                <p id="kpiTaxaResposta" class="text-3xl font-bold text-orange-600">--</p>
                <div id="kpiTaxaTrend" class="text-sm font-medium text-emerald-600">—</div>
              </div>
            </div>
          </article>

          <!-- Tempo Médio -->
          <article class="kpi-card card-gradient rounded-2xl shadow-professional border border-gray-200 p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-emerald-500/10 to-emerald-600/10 rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
              <div class="flex items-center justify-between mb-4">
                <div class="h-12 w-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                  <svg class="h-6 w-6 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <div class="text-right">
                  <h3 class="text-sm font-semibold text-gray-700">Tempo Médio</h3>
                  <p class="text-xs text-gray-500">Resposta rápida</p>
                </div>
              </div>
              <div class="space-y-2">
                <p id="kpiTempoMedio" class="text-3xl font-bold text-emerald-600">--</p>
                <div id="kpiTempoTrend" class="text-sm font-medium text-emerald-600">—</div>
              </div>
            </div>
          </article>

          <!-- Conversões Hoje -->
          <article class="kpi-card card-gradient rounded-2xl shadow-professional border border-gray-200 p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-rose-500/10 to-rose-600/10 rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
              <div class="flex items-center justify-between mb-4">
                <div class="h-12 w-12 rounded-xl bg-rose-100 flex items-center justify-center">
                  <svg class="h-6 w-6 text-rose-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <div class="text-right">
                  <h3 class="text-sm font-semibold text-gray-700">Conversões Hoje</h3>
                  <p class="text-xs text-gray-500">Fechamentos</p>
                </div>
              </div>
              <div class="space-y-2">
                <p id="kpiConversoesHoje" class="text-3xl font-bold text-rose-600">--</p>
                <div class="text-xs text-gray-500">mudanças para "fechado"</div>
              </div>
            </div>
          </article>

          <!-- Receita do Mês -->
          <article class="kpi-card card-gradient rounded-2xl shadow-professional border border-gray-200 p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-amber-500/10 to-amber-600/10 rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
              <div class="flex items-center justify-between mb-4">
                <div class="h-12 w-12 rounded-xl bg-amber-100 flex items-center justify-center">
                  <svg class="h-6 w-6 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"></path>
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <div class="text-right">
                  <h3 class="text-sm font-semibold text-gray-700">Receita do Mês</h3>
                  <p class="text-xs text-gray-500">Faturamento atual</p>
                </div>
              </div>
              <div class="space-y-2">
                <p id="kpiReceitaMes" class="text-3xl font-bold text-amber-600">--</p>
                <div id="kpiReceitaTrend" class="text-sm font-medium text-emerald-600">—</div>
              </div>
            </div>
          </article>

          <!-- Conversas Ativas -->
          <article class="kpi-card card-gradient rounded-2xl shadow-professional border border-gray-200 p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-red-500/10 to-red-600/10 rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
              <div class="flex items-center justify-between mb-4">
                <div class="h-12 w-12 rounded-xl bg-red-100 flex items-center justify-center">
                  <svg class="h-6 w-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5z"></path>
                    <path d="M15 7v2a4 4 0 01-4 4H9.828l-1.766 1.767c.28.149.599.233.938.233h2l3 3v-3h2a2 2 0 002-2V9a2 2 0 00-2-2h-1z"></path>
                  </svg>
                </div>
                <div class="text-right">
                  <h3 class="text-sm font-semibold text-gray-700">Conversas Ativas</h3>
                  <p class="text-xs text-gray-500">Últimas 24h</p>
                </div>
              </div>
              <div class="space-y-2">
                <p id="kpiConversasAtivas" class="text-3xl font-bold text-red-600">--</p>
                <div class="text-xs text-gray-500">com troca de mensagens</div>
              </div>
            </div>
          </article>
        </section>

        <!-- Gráficos e Análises -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Funil de Conversão -->
          <section class="card-gradient rounded-2xl shadow-professional border border-gray-200 p-8 hover-lift">
            <div class="flex items-center justify-between mb-8">
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-indigo-100 flex items-center justify-center">
                  <svg class="h-6 w-6 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"></path>
                  </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900">Funil de Conversão</h3>
              </div>
            </div>
            
            <div id="funnel" class="space-y-6">
              <!-- Prospects -->
              <div class="bg-white border border-gray-200 rounded-xl p-4 hover-lift">
                <div class="flex items-center justify-between mb-3">
                  <div class="flex items-center gap-3">
                    <div class="h-8 w-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                      <div class="h-3 w-3 bg-indigo-600 rounded-full"></div>
                    </div>
                    <span class="font-semibold text-gray-800">Prospects</span>
                  </div>
                  <span id="fProspectsPct" class="text-lg font-bold text-gray-900">0%</span>
                </div>
                <div class="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
                  <div id="fProspects" class="h-3 bg-indigo-500 rounded-full progress-bar" style="width:0%"></div>
                </div>
              </div>

              <!-- Leads Qualificados -->
              <div class="bg-white border border-gray-200 rounded-xl p-4 hover-lift">
                <div class="flex items-center justify-between mb-3">
                  <div class="flex items-center gap-3">
                    <div class="h-8 w-8 rounded-lg bg-blue-100 flex items-center justify-center">
                      <div class="h-3 w-3 bg-blue-600 rounded-full"></div>
                    </div>
                    <span class="font-semibold text-gray-800">Leads Qualificados</span>
                  </div>
                  <span id="fQualificadosPct" class="text-lg font-bold text-gray-900">0%</span>
                </div>
                <div class="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
                  <div id="fQualificados" class="h-3 bg-blue-500 rounded-full progress-bar" style="width:0%"></div>
                </div>
              </div>

              <!-- Oportunidades -->
              <div class="bg-white border border-gray-200 rounded-xl p-4 hover-lift">
                <div class="flex items-center justify-between mb-3">
                  <div class="flex items-center gap-3">
                    <div class="h-8 w-8 rounded-lg bg-purple-100 flex items-center justify-center">
                      <div class="h-3 w-3 bg-purple-600 rounded-full"></div>
                    </div>
                    <span class="font-semibold text-gray-800">Oportunidades</span>
                  </div>
                  <span id="fOportunidadesPct" class="text-lg font-bold text-gray-900">0%</span>
                </div>
                <div class="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
                  <div id="fOportunidades" class="h-3 bg-purple-500 rounded-full progress-bar" style="width:0%"></div>
                </div>
              </div>

              <!-- Fechamentos -->
              <div class="bg-white border border-gray-200 rounded-xl p-4 hover-lift">
                <div class="flex items-center justify-between mb-3">
                  <div class="flex items-center gap-3">
                    <div class="h-8 w-8 rounded-lg bg-emerald-100 flex items-center justify-center">
                      <div class="h-3 w-3 bg-emerald-600 rounded-full"></div>
                    </div>
                    <span class="font-semibold text-gray-800">Fechamentos</span>
                  </div>
                  <span id="fFechamentosPct" class="text-lg font-bold text-gray-900">0%</span>
                </div>
                <div class="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
                  <div id="fFechamentos" class="h-3 bg-emerald-500 rounded-full progress-bar" style="width:0%"></div>
                </div>
              </div>
            </div>
          </section>

          <!-- Performance dos Canais -->
          <section class="card-gradient rounded-2xl shadow-professional border border-gray-200 p-8 hover-lift">
            <div class="flex items-center justify-between mb-8">
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-purple-100 flex items-center justify-center">
                  <svg class="h-6 w-6 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900">Performance dos Canais</h3>
              </div>
              <span id="totalConversoes" class="text-sm font-semibold bg-purple-50 text-purple-700 px-3 py-1 rounded-full">0 conversões</span>
            </div>
            
            <ul id="listaCanais" class="space-y-4">
              <!-- Os canais serão inseridos aqui pelo JS -->
            </ul>
          </section>
        </div>
      </div>
    </main>
  </div>

  <script>
    function fmtPct(n){ return (n ?? 0).toFixed(1).replace('.0','') + '%'; }
    function fmtMin(n){ return (n ?? 0).toFixed(1).replace('.0','') + 'min'; }
    function fmtMoeda(n){ return 'R$ ' + (Number(n||0)).toLocaleString('pt-BR',{minimumFractionDigits:0}); }

    async function carregar(){
      try {
        const periodo = document.getElementById('filtroPeriodo').value;
        const res = await fetch(`/home/metrics?periodo=${encodeURIComponent(periodo)}`);
        const d = await res.json();

        // KPIs
        document.getElementById('kpiTaxaResposta').textContent = fmtPct(d.kpis.taxa_resposta * 100);
        document.getElementById('kpiTaxaTrend').textContent = d.kpis.taxa_trend ?? '';
        document.getElementById('kpiTempoMedio').textContent = fmtMin(d.kpis.tempo_medio_min);
        document.getElementById('kpiTempoTrend').textContent = d.kpis.tempo_trend ?? '';
        document.getElementById('kpiConversoesHoje').textContent = d.kpis.conversoes_hoje ?? 0;
        document.getElementById('kpiReceitaMes').textContent = fmtMoeda(d.kpis.receita_mes);
        document.getElementById('kpiReceitaTrend').textContent = d.kpis.receita_trend ?? '';
        document.getElementById('kpiConversasAtivas').textContent = d.kpis.conversas_ativas ?? 0;

        // Funil
        const f = d.funil || {prospects:0, qualificados:0, oportunidades:0, fechamentos:0};
        const base = Math.max(f.prospects, 1);
        const pcts = {
          prospects: 100,
          qualificados: (f.qualificados/base)*100,
          oportunidades: (f.oportunidades/base)*100,
          fechamentos: (f.fechamentos/base)*100
        };
        
        // Aplicar animações nas barras de progresso
        setTimeout(() => {
          document.getElementById('fProspects').style.width = pcts.prospects+'%';
          document.getElementById('fQualificados').style.width = pcts.qualificados+'%';
          document.getElementById('fOportunidades').style.width = pcts.oportunidades+'%';
          document.getElementById('fFechamentos').style.width = pcts.fechamentos+'%';
        }, 300);

        document.getElementById('fProspectsPct').textContent = fmtPct(pcts.prospects);
        document.getElementById('fQualificadosPct').textContent = fmtPct(pcts.qualificados);
        document.getElementById('fOportunidadesPct').textContent = fmtPct(pcts.oportunidades);
        document.getElementById('fFechamentosPct').textContent = fmtPct(pcts.fechamentos);

        // Canais
        const ul = document.getElementById('listaCanais'); 
        ul.innerHTML = '';
        let totalConv = 0;
        
        (d.canais||[]).forEach(c => {
          totalConv += c.conversoes||0;
          const critFlag = c.critico ? '<span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800"><svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>Crítico</span>' : '';
          
          ul.insertAdjacentHTML('beforeend', `
            <li class="bg-white border border-gray-200 rounded-xl p-5 hover-lift transition-all duration-200">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-4 min-w-0 flex-1">
                  <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg shadow-lg">
                    ${(c.nome || 'C')[0].toUpperCase()}
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 mb-1">
                      <h4 class="font-bold text-gray-900 ellipsis text-lg">${c.nome || 'Canal sem nome'}</h4>
                      ${critFlag}
                    </div>
                    <div class="text-sm text-gray-600">
                      <span class="font-semibold">${c.conversoes || 0} conversões</span>
                      <span class="mx-2 text-gray-400">•</span>
                      <span>Taxa: ${fmtPct(c.taxa_resposta*100)}</span>
                      <span class="mx-2 text-gray-400">•</span>
                      <span>Tempo médio: ${fmtMin(c.tempo_medio_min)}</span>
                    </div>
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-xl font-bold text-gray-900">${fmtMoeda(c.receita||0)}</div>
                  <div class="text-sm text-gray-500">receita gerada</div>
                </div>
              </div>
              
              <!-- Métricas detalhadas em cards menores -->
              <div class="grid grid-cols-3 gap-3">
                <div class="bg-emerald-50 rounded-lg p-3 text-center border border-emerald-200">
                  <div class="text-lg font-bold text-emerald-700">${fmtPct((c.taxa_resposta || 0) * 100)}</div>
                  <div class="text-xs text-emerald-600 font-medium">Taxa Resposta</div>
                </div>
                <div class="bg-blue-50 rounded-lg p-3 text-center border border-blue-200">
                  <div class="text-lg font-bold text-blue-700">${fmtMin(c.tempo_medio_min || 0)}</div>
                  <div class="text-xs text-blue-600 font-medium">Tempo Médio</div>
                </div>
                <div class="bg-purple-50 rounded-lg p-3 text-center border border-purple-200">
                  <div class="text-lg font-bold text-purple-700">${c.conversoes || 0}</div>
                  <div class="text-xs text-purple-600 font-medium">Conversões</div>
                </div>
              </div>
            </li>
          `);
        });
        
        document.getElementById('totalConversoes').textContent = `${totalConv} conversões`;

        // Atualização do timestamp
        const now = new Date();
        document.getElementById('lastUpdate').textContent = `Última atualização: ${now.toLocaleTimeString('pt-BR')}`;
        
        // Adicionar feedback visual de sucesso
        showSuccessFeedback();
        
      } catch (error) {
        console.error('Erro ao carregar dashboard:', error);
        showErrorFeedback();
      }
    }

    // Feedback visual para sucesso
    function showSuccessFeedback() {
      const updateElement = document.getElementById('lastUpdate');
      updateElement.classList.add('text-emerald-600');
      setTimeout(() => {
        updateElement.classList.remove('text-emerald-600');
      }, 2000);
    }

    // Feedback visual para erro
    function showErrorFeedback() {
      const updateElement = document.getElementById('lastUpdate');
      updateElement.textContent = 'Erro ao atualizar dados';
      updateElement.classList.add('text-red-600');
      setTimeout(() => {
        updateElement.classList.remove('text-red-600');
      }, 3000);
    }

    // Adicionar loading state no botão
    function setLoadingState(loading) {
      const btn = document.getElementById('btnRefresh');
      const icon = btn.querySelector('svg');
      
      if (loading) {
        btn.disabled = true;
        btn.classList.add('opacity-75');
        icon.classList.add('animate-spin');
      } else {
        btn.disabled = false;
        btn.classList.remove('opacity-75');
        icon.classList.remove('animate-spin');
      }
    }

    // Event listeners com loading state
    document.getElementById('btnRefresh').addEventListener('click', async () => {
      setLoadingState(true);
      await carregar();
      setLoadingState(false);
    });
    
    document.getElementById('filtroPeriodo').addEventListener('change', async () => {
      setLoadingState(true);
      await carregar();
      setLoadingState(false);
    });

    // Auto-refresh a cada 5 minutos
    setInterval(() => {
      carregar();
    }, 5 * 60 * 1000);

    // Carregamento inicial
    carregar();
  </script>
</body>
</html>