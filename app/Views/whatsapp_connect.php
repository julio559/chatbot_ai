<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>Instâncias WhatsApp | CRM Assistente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: {
      borderRadius: { 'xl':'0.75rem','2xl':'1rem' },
      boxShadow: { soft:'0 6px 18px rgba(0,0,0,.06)' }
    }}}
  </script>
  <style>
    .hidden{display:none}
  </style>
</head>
<body class="bg-slate-50 text-slate-800">
  <div class="flex min-h-screen">
    <?= view('sidebar') ?>

    <main class="flex-1 p-6 overflow-y-auto">
      <header class="mb-6">
        <div class="bg-gradient-to-r from-emerald-50 via-blue-50 to-blue-100 p-6 rounded-2xl shadow-sm ring-1 ring-slate-200/60">
          <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
              <h1 class="text-2xl font-semibold text-slate-900">Instâncias do WhatsApp</h1>
              <p class="text-sm text-slate-600">Gerencie suas instâncias UltraMSG (múltiplas por usuário).</p>
            </div>
            <button id="btnNovo" class="px-4 py-2 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700">
              + Nova instância
            </button>
          </div>
        </div>
      </header>

      <section class="bg-white rounded-2xl shadow-soft ring-1 ring-slate-200">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 border-b">
              <tr>
                <th class="px-4 py-3 text-left">Nome</th>
                <th class="px-4 py-3 text-left">Linha</th>
                <th class="px-4 py-3 text-left">Instance ID</th>
                <th class="px-4 py-3 text-left">Webhook</th>
                <th class="px-4 py-3 text-left">Status</th>
                <th class="px-4 py-3 text-right">Ações</th>
              </tr>
            </thead>
            <tbody class="divide-y" id="tbody">
              <?php foreach (($instancias ?? []) as $i): ?>
              <?php $connected = ($i['conn_status'] ?? '') === 'authenticated'; ?>
              <tr class="hover:bg-slate-50">
                <td class="px-4 py-3"><?= esc($i['nome']) ?></td>
                <td class="px-4 py-3"><?= esc($i['linha_msisdn'] ?? '-') ?></td>
                <td class="px-4 py-3"><?= esc($i['instance_id']) ?></td>
                <td class="px-4 py-3"><?= esc($i['webhook_url'] ?: '-') ?></td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full <?= $connected ? 'bg-emerald-500' : 'bg-slate-300' ?>"></span>
                    <?= esc($i['conn_status'] ?? '—') ?>
                    <?php if (!empty($i['conn_substatus'])): ?>
                      <span class="text-slate-400">/ <?= esc($i['conn_substatus']) ?></span>
                    <?php endif; ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <button class="px-3 py-1 rounded-lg bg-amber-100 text-amber-800 mr-2"
                          onclick='abrirEditar(<?= json_encode($i, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>Editar</button>
                  <button class="px-3 py-1 rounded-lg bg-blue-100 text-blue-800 mr-2"
                          onclick="abrirPainelQR(<?= (int)$i['id'] ?>,'<?= esc($i['nome']) ?>')">QR/Status</button>
                  <button class="px-3 py-1 rounded-lg bg-red-100 text-red-700"
                          onclick="apagarInstancia(<?= (int)$i['id'] ?>)">Excluir</button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($instancias)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Nenhuma instância cadastrada.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <!-- Modal Criar/Editar -->
  <div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="absolute inset-0 bg-black/40" onclick="fecharModal()"></div>
    <div class="relative bg-white w-full max-w-lg rounded-2xl shadow-xl p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 id="mTitle" class="text-lg font-semibold">Nova instância</h3>
        <button onclick="fecharModal()" class="text-gray-500 hover:text-red-600 text-xl font-bold">&times;</button>
      </div>

      <form id="form" onsubmit="return false;" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" id="mId">
        <div>
          <label class="text-sm text-slate-600">Nome</label>
          <input id="mNome" class="mt-1 w-full border rounded-xl px-3 py-2" placeholder="ex.: Recepção" required>
        </div>
        <div>
          <label class="text-sm text-slate-600">Número da linha (DDI+DDD+NÚMERO)</label>
          <input id="mLinha" class="mt-1 w-full border rounded-xl px-3 py-2" placeholder="ex.: 5531999999999" required>
          <p class="text-xs text-slate-500 mt-1">Somente dígitos. Ex.: 55 + DDD + número.</p>
        </div>
        <div>
          <label class="text-sm text-slate-600">Instance ID</label>
          <input id="mInstance" class="mt-1 w-full border rounded-xl px-3 py-2" placeholder="ex.: instance123456" required>
        </div>
        <div>
          <label class="text-sm text-slate-600">Token</label>
          <input id="mToken" class="mt-1 w-full border rounded-xl px-3 py-2" placeholder="seu-token-ultramsg">
          <p class="text-xs text-slate-500 mt-1">Ao editar, deixe em branco para manter o token atual.</p>
        </div>
        <div>
          <label class="text-sm text-slate-600">Webhook (padrão do sistema)</label>
          <input id="mHook" class="mt-1 w-full border rounded-xl px-3 py-2" value="<?= esc($webhookBase) ?>">
          <p class="text-xs text-slate-500 mt-1">Este endpoint receberá os eventos desta instância.</p>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="px-4 py-2 rounded-xl bg-gray-100" onclick="fecharModal()">Cancelar</button>
          <button id="btnSalvar" class="px-4 py-2 rounded-xl bg-blue-600 text-white">Salvar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- SlideOver QR/Status -->
  <div id="qrPanel" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="fecharQR()"></div>
    <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-xl p-6 overflow-y-auto">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">Conectar WhatsApp: <span id="qrTitle"></span></h3>
        <button onclick="fecharQR()" class="text-gray-500 hover:text-red-600 text-xl font-bold">&times;</button>
      </div>

      <!-- QR + overlay de check -->
      <div class="relative aspect-square w-full max-w-xs mx-auto rounded-xl border grid place-items-center bg-slate-50">
        <img id="qrImg" class="max-w-full max-h-full p-3" alt="QR Code">
        <div id="qrOk" class="hidden absolute inset-0 grid place-items-center">
          <div class="rounded-full bg-emerald-500 text-white w-24 h-24 grid place-items-center shadow-xl">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-14 h-14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 6L9 17l-5-5"/>
            </svg>
          </div>
        </div>
      </div>

      <div class="mt-4 grid grid-cols-2 gap-2">
        <button id="btnRefresh" class="px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200">Atualizar QR</button>
        <button id="btnForce" class="px-3 py-2 rounded-xl bg-amber-100 text-amber-800 hover:bg-amber-200">Forçar novo QR</button>
      </div>

      <div class="mt-3 p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200 text-sm">
        Status: <b id="qrStatus">—</b>
        <div class="text-xs text-slate-500 mt-1">Espere até ficar <b>authenticated</b>.</div>
      </div>

      <div class="mt-6">
        <label class="text-sm text-slate-600">Webhook</label>
        <div class="flex gap-2 mt-1">
          <input id="qrHook" class="flex-1 border rounded-xl px-3 py-2" value="<?= esc($webhookBase) ?>">
          <button id="btnSaveHook" class="px-3 py-2 rounded-xl bg-blue-600 text-white">Salvar webhook</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    const csrfName = '<?= esc(csrf_token()) ?>';
    const csrfHash = '<?= esc(csrf_hash()) ?>';

    function abrirModal(){ const m=document.getElementById('modal'); m.classList.remove('hidden'); m.classList.add('flex'); }
    function fecharModal(){ const m=document.getElementById('modal'); m.classList.add('hidden'); m.classList.remove('flex'); resetForm(); }
    const mId=document.getElementById('mId'),
          mNome=document.getElementById('mNome'),
          mLinha=document.getElementById('mLinha'),
          mInstance=document.getElementById('mInstance'),
          mToken=document.getElementById('mToken'),
          mHook=document.getElementById('mHook');

    function resetForm(){
      mId.value=''; mNome.value=''; mLinha.value=''; mInstance.value=''; mToken.value=''; mHook.value='<?= esc($webhookBase) ?>';
    }

    document.getElementById('btnNovo').addEventListener('click', ()=>{
      document.getElementById('mTitle').textContent='Nova instância';
      abrirModal();
    });

    function abrirEditar(item){
      document.getElementById('mTitle').textContent='Editar instância';
      mId.value=item.id;
      mNome.value=item.nome || 'Instância';
      mLinha.value=item.linha_msisdn || '';
      mInstance.value=item.instance_id || '';
      mToken.value=''; // em branco = manter
      mHook.value=item.webhook_url || '<?= esc($webhookBase) ?>';
      abrirModal();
    }

    // salvar (bind) + webhook
    document.getElementById('btnSalvar').addEventListener('click', async ()=>{
      const fd=new FormData();
      fd.append(csrfName, csrfHash);
      fd.append('id', mId.value || 0);
      fd.append('nome', mNome.value);
      fd.append('linha_msisdn', (mLinha.value || '').replace(/\D+/g,''));
      fd.append('instance_id', mInstance.value);
      fd.append('token', mToken.value || ''); // vazio no editar = manter token

      const r  = await fetch('/whatsapp/bind', { method:'POST', body:fd });
      const j1 = await r.json();

      if (!r.ok || !j1.ok) {
        alert('Erro ao salvar: ' + (j1.msg || r.status));
        return;
      }
      const newId = j1.id;

      const hook = (mHook.value || '').trim();
      if (hook) {
        const fd2 = new FormData();
        fd2.append(csrfName, csrfHash);
        fd2.append('webhook_url', hook);
        const r2 = await fetch('/whatsapp/webhook/'+newId, { method:'POST', body:fd2 });
        if (!r2.ok) alert('Instância salva, mas falhou ao salvar webhook.');
      }

      location.reload();
    });

    // excluir
    async function apagarInstancia(id) {
      if (!confirm('Tem certeza que deseja excluir esta instância?')) return;

      const fd = new FormData();
      fd.append('_method','DELETE');
      fd.append(csrfName, csrfHash);

      const resp = await fetch(`/whatsapp/delete/${id}`, { method: 'POST', body: fd });
      const j = await resp.json();

      if (resp.ok && j.status === 'success') location.reload();
      else alert('Erro: ' + (j.message || 'Falha ao excluir'));
    }

    // === QR/STATUS ===
    let pollTimer = null, closeTimer = null;

    function setQrOk(on) {
      const okEl = document.getElementById('qrOk');
      if (!okEl) return;
      okEl.classList.toggle('hidden', !on);
    }

    function extractStatus(j) {
      let st = j && j.status ? j.status : null;
      let sub = null;
      const raw = j && j.raw ? j.raw : null;
      if (!st && raw && raw.status && raw.status.accountStatus && raw.status.accountStatus.status) {
        st = raw.status.accountStatus.status;
      }
      if (raw && raw.status && raw.status.accountStatus && raw.status.accountStatus.substatus) {
        sub = raw.status.accountStatus.substatus;
      }
      return { st, sub };
    }

    function abrirPainelQR(id, nome){
      document.getElementById('qrTitle').textContent = nome;
      document.getElementById('qrImg').src = '/whatsapp/qr/'+id+'?ts='+Date.now();
      document.getElementById('qrPanel').classList.remove('hidden');
      setQrOk(false);
      document.getElementById('qrStatus').textContent = '—';

      const poll = async ()=>{
        try{
          const r = await fetch('/whatsapp/status/'+id);
          const j = await r.json();
          const { st, sub } = extractStatus(j);
          const txt = st ? (sub ? `${st} / ${sub}` : st) : (j.status || 'unknown');
          document.getElementById('qrStatus').textContent = txt;

          if (st === 'authenticated') {
            setQrOk(true);
            if (pollTimer) clearTimeout(pollTimer);
            closeTimer = setTimeout(()=>{
              fecharQR();
              location.reload();
            }, 900);
            return;
          }
          pollTimer = setTimeout(poll, 2000);
        } catch(e){
          document.getElementById('qrStatus').textContent = 'erro';
          pollTimer = setTimeout(poll, 2500);
        }
      };

      document.getElementById('btnRefresh').onclick = ()=>{
        setQrOk(false);
        document.getElementById('qrImg').src = '/whatsapp/qr/'+id+'?ts='+Date.now();
      };
      document.getElementById('btnForce').onclick = async ()=>{
        const fd=new FormData(); fd.append(csrfName, csrfHash);
        await fetch('/whatsapp/logout/'+id, { method:'POST', body:fd });
        setQrOk(false);
        document.getElementById('qrImg').src = '/whatsapp/qr/'+id+'?ts='+Date.now();
      };
      document.getElementById('btnSaveHook').onclick = async ()=>{
        const fd=new FormData(); fd.append(csrfName, csrfHash);
        fd.append('webhook_url', document.getElementById('qrHook').value);
        const r = await fetch('/whatsapp/webhook/'+id, { method:'POST', body:fd });
        alert(r.ok ? 'Webhook salvo' : 'Falha ao salvar webhook');
      };

      poll();
    }

    function fecharQR(){
      const p = document.getElementById('qrPanel');
      p.classList.add('hidden');
      if (pollTimer) clearTimeout(pollTimer);
      if (closeTimer) clearTimeout(closeTimer);
      pollTimer = null; closeTimer = null;
      setQrOk(false);
    }
  </script>
</body>
</html>
