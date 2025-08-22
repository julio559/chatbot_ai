<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CRM</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme:{ extend:{
      colors:{ brand:{ DEFAULT:'#111827' } },
      borderRadius:{ xl:'0.75rem', '2xl':'1rem' },
      boxShadow:{ soft:'0 4px 16px rgba(0,0,0,.06)' }
    }}}
  </script>
  <style>
    .ellipsis{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  </style>
</head>
<body class="bg-slate-50 text-slate-800">
  <div class="flex min-h-screen">
    <?= view('sidebar') ?>

    <main class="flex-1 p-6 overflow-y-auto">
      <!-- Cabeçalho -->
      <section class="mb-6">
        <div class="bg-white p-5 rounded-2xl shadow-sm ring-1 ring-slate-200 flex items-center justify-between">
          <div class="min-w-0">
            <h2 class="text-2xl font-semibold text-slate-900">Dashboard Operacional</h2>
            <p class="text-sm text-slate-600">Métricas de performance em tempo real</p>
            <p id="lastUpdate" class="text-xs text-slate-400 mt-1">Atualizando…</p>
          </div>
          <div class="flex items-center gap-3">
            <select id="filtroPeriodo" class="border rounded-xl px-3 py-2 text-sm">
              <option value="today">Hoje</option>
              <option value="7d">Últimos 7 dias</option>
              <option value="30d" selected>Últimos 30 dias</option>
            </select>
            <button id="btnRefresh" class="px-3 py-2 rounded-xl bg-blue-600 text-white text-sm">Atualizar</button>
          </div>
        </div>
      </section>

      <!-- KPIs -->
      <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <!-- Taxa de resposta -->
        <article class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5">
          <div class="flex items-center justify-between">
            <h3 class="text-sm text-slate-500">Taxa de Resposta</h3>
            <span class="text-orange-500">💬</span>
          </div>
          <p id="kpiTaxaResposta" class="mt-2 text-3xl font-bold text-orange-600">--</p>
          <p id="kpiTaxaTrend" class="text-xs text-emerald-600 mt-1">—</p>
        </article>

        <!-- Tempo médio -->
        <article class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5">
          <div class="flex items-center justify-between">
            <h3 class="text-sm text-slate-500">Tempo Médio de Resposta</h3>
            <span class="text-emerald-600">⏱️</span>
          </div>
          <p id="kpiTempoMedio" class="mt-2 text-3xl font-bold text-emerald-700">--</p>
          <p id="kpiTempoTrend" class="text-xs text-emerald-600 mt-1">—</p>
        </article>

        <!-- Conversões hoje -->
        <article class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5">
          <div class="flex items-center justify-between">
            <h3 class="text-sm text-slate-500">Conversões Hoje</h3>
            <span class="text-rose-600">✔️</span>
          </div>
          <p id="kpiConversoesHoje" class="mt-2 text-3xl font-bold text-rose-600">--</p>
          <p class="text-xs text-slate-400 mt-1">mudanças para “fechado”</p>
        </article>

        <!-- Receita do mês -->
        <article class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5">
          <div class="flex items-center justify-between">
            <h3 class="text-sm text-slate-500">Receita do Mês</h3>
            <span class="text-amber-600">💲</span>
          </div>
          <p id="kpiReceitaMes" class="mt-2 text-3xl font-bold text-amber-600">--</p>
          <p id="kpiReceitaTrend" class="text-xs text-emerald-600 mt-1">—</p>
        </article>

        <!-- Conversas ativas -->
        <article class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5">
          <div class="flex items-center justify-between">
            <h3 class="text-sm text-slate-500">Conversas Ativas</h3>
            <span class="text-red-500">📈</span>
          </div>
          <p id="kpiConversasAtivas" class="mt-2 text-3xl font-bold text-red-500">--</p>
          <p class="text-xs text-slate-400 mt-1">últimas 24h com troca de mensagens</p>
        </article>
      </section>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Funil -->
        <section class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-6">
          <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">📈 Funil de Conversão</h3>
          <div id="funnel">
            <!-- linhas do funil -->
            <div class="mb-4">
              <div class="flex items-center justify-between text-sm mb-1">
                <span>Prospects</span><span id="fProspectsPct">0%</span>
              </div>
              <div class="w-full h-2 bg-slate-100 rounded"><div id="fProspects" class="h-2 bg-indigo-500 rounded" style="width:0%"></div></div>
            </div>
            <div class="mb-4">
              <div class="flex items-center justify-between text-sm mb-1">
                <span>Leads Qualificados</span><span id="fQualificadosPct">0%</span>
              </div>
              <div class="w-full h-2 bg-slate-100 rounded"><div id="fQualificados" class="h-2 bg-indigo-500 rounded" style="width:0%"></div></div>
            </div>
            <div class="mb-4">
              <div class="flex items-center justify-between text-sm mb-1">
                <span>Oportunidades</span><span id="fOportunidadesPct">0%</span>
              </div>
              <div class="w-full h-2 bg-slate-100 rounded"><div id="fOportunidades" class="h-2 bg-indigo-500 rounded" style="width:0%"></div></div>
            </div>
            <div>
              <div class="flex items-center justify-between text-sm mb-1">
                <span>Fechamentos</span><span id="fFechamentosPct">0%</span>
              </div>
              <div class="w-full h-2 bg-slate-100 rounded"><div id="fFechamentos" class="h-2 bg-indigo-500 rounded" style="width:0%"></div></div>
            </div>
          </div>
        </section>

        <!-- Performance dos Canais -->
        <section class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">👁️ Performance dos Canais</h3>
            <span id="totalConversoes" class="text-sm text-slate-500">0 conversões</span>
          </div>
          <ul id="listaCanais" class="space-y-3"></ul>
        </section>
      </div>
    </main>
  </div>

  <script>
    function fmtPct(n){ return (n ?? 0).toFixed(1).replace('.0','') + '%'; }
    function fmtMin(n){ return (n ?? 0).toFixed(1).replace('.0','') + 'min'; }
    function fmtMoeda(n){ return 'R$ ' + (Number(n||0)).toLocaleString('pt-BR',{minimumFractionDigits:0}); }

    async function carregar(){
      const periodo = document.getElementById('filtroPeriodo').value;
      const res = await fetch(`/home/metrics?periodo=${encodeURIComponent(periodo)}`);
      const d = await res.json();

      // KPIs
      document.getElementById('kpiTaxaResposta').textContent = fmtPct(d.kpis.taxa_resposta * 100);
      document.getElementById('kpiTaxaTrend').textContent   = d.kpis.taxa_trend ?? '';
      document.getElementById('kpiTempoMedio').textContent  = fmtMin(d.kpis.tempo_medio_min);
      document.getElementById('kpiTempoTrend').textContent  = d.kpis.tempo_trend ?? '';
      document.getElementById('kpiConversoesHoje').textContent = d.kpis.conversoes_hoje ?? 0;
      document.getElementById('kpiReceitaMes').textContent  = fmtMoeda(d.kpis.receita_mes);
      document.getElementById('kpiReceitaTrend').textContent= d.kpis.receita_trend ?? '';
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
      document.getElementById('fProspects').style.width = pcts.prospects+'%';
      document.getElementById('fQualificados').style.width = pcts.qualificados+'%';
      document.getElementById('fOportunidades').style.width = pcts.oportunidades+'%';
      document.getElementById('fFechamentos').style.width = pcts.fechamentos+'%';
      document.getElementById('fProspectsPct').textContent = fmtPct(pcts.prospects);
      document.getElementById('fQualificadosPct').textContent = fmtPct(pcts.qualificados);
      document.getElementById('fOportunidadesPct').textContent = fmtPct(pcts.oportunidades);
      document.getElementById('fFechamentosPct').textContent = fmtPct(pcts.fechamentos);

      // Canais
      const ul = document.getElementById('listaCanais'); ul.innerHTML='';
      let totalConv = 0;
      (d.canais||[]).forEach(c => {
        totalConv += c.conversoes||0;
        const critFlag = c.critico ? '<span class="ml-2 px-2 py-0.5 text-xs rounded bg-rose-100 text-rose-700">Critical</span>' : '';
        ul.insertAdjacentHTML('beforeend', `
          <li class="p-3 rounded-xl ring-1 ring-slate-200 flex items-center justify-between">
            <div class="min-w-0">
              <div class="font-medium text-slate-800 ellipsis">${c.nome}${critFlag}</div>
              <div class="text-xs text-slate-500">Taxa: ${fmtPct(c.taxa_resposta*100)} • Tempo médio: ${fmtMin(c.tempo_medio_min)} • Receita: ${fmtMoeda(c.receita||0)}</div>
            </div>
            <div class="text-sm font-semibold">${c.conversoes||0} conversões</div>
          </li>
        `);
      });
      document.getElementById('totalConversoes').textContent = `${totalConv} conversões`;

      // atualização
      const now = new Date();
      document.getElementById('lastUpdate').textContent = `Última atualização: ${now.toLocaleTimeString()}`;
    }

    document.getElementById('btnRefresh').addEventListener('click', carregar);
    document.getElementById('filtroPeriodo').addEventListener('change', carregar);
    carregar();
  </script>
</body>
</html>
