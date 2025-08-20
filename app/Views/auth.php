<?= csrf_field() ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Entrar / Criar conta</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Tailwind (CDN simples; em produção use build próprio) -->
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-slate-100">
  <div class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-3xl">
      <div class="mb-6 text-center">
        <h1 class="text-3xl font-semibold">Bem-vindo(a) 👋</h1>
        <p class="text-slate-300">Acesse sua conta ou crie uma nova para usar o painel.</p>
      </div>

      <div class="bg-slate-800/60 backdrop-blur rounded-2xl shadow-xl border border-slate-700">
        <!-- Tabs -->
        <div class="flex">
          <button id="tab-login" class="flex-1 py-3 text-center rounded-tl-2xl bg-slate-900/60 border-b-2 border-emerald-400 font-medium">Entrar</button>
          <button id="tab-register" class="flex-1 py-3 text-center rounded-tr-2xl bg-slate-800/60 border-b-2 border-transparent hover:border-slate-500 transition">Criar conta</button>
        </div>

        <!-- Conteúdo -->
        <div class="p-6 md:p-8">
          <!-- LOGIN -->
          <form id="form-login" class="space-y-4" action="<?= site_url('auth/login') ?>" method="post" autocomplete="on">
            <?= csrf_field() ?>

            <?php $errsLogin = session()->getFlashdata('errors_login'); ?>
            <?php if (!empty($errsLogin)): ?>
              <div class="rounded-lg border border-red-500/50 bg-red-500/10 p-3 text-red-200 text-sm">
                <?php foreach ($errsLogin as $msg): ?>
                  <div>• <?= esc($msg) ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div>
              <label class="block text-sm mb-1">E-mail</label>
              <input type="email" name="email" required
                     value="<?= esc(old('email')) ?>"
                     class="w-full rounded-lg bg-slate-900/60 border border-slate-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400">
            </div>

            <div>
              <label class="block text-sm mb-1">Senha</label>
              <input type="password" name="senha" required minlength="6"
                     class="w-full rounded-lg bg-slate-900/60 border border-slate-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400">
            </div>

            <button class="w-full py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold transition">
              Entrar
            </button>
          </form>

          <!-- REGISTER -->
          <form id="form-register" class="space-y-4 hidden" action="<?= site_url('auth/register') ?>" method="post" autocomplete="on">
            <?= csrf_field() ?>

            <?php $errsReg = session()->getFlashdata('errors_register'); ?>
            <?php if (!empty($errsReg)): ?>
              <div class="rounded-lg border border-red-500/50 bg-red-500/10 p-3 text-red-200 text-sm">
                <?php foreach ($errsReg as $msg): ?>
                  <div>• <?= esc($msg) ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="grid md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm mb-1">Nome</label>
                <input type="text" name="nome" required minlength="2"
                       value="<?= esc(old('nome')) ?>"
                       class="w-full rounded-lg bg-slate-900/60 border border-slate-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400">
              </div>
              <div>
                <label class="block text-sm mb-1">Telefone (WhatsApp)</label>
                <input type="text" name="telefone"
                       value="<?= esc(old('telefone')) ?>"
                       placeholder="(DDD) 99999-9999"
                       class="w-full rounded-lg bg-slate-900/60 border border-slate-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400">
              </div>
            </div>

            <div>
              <label class="block text-sm mb-1">E-mail</label>
              <input type="email" name="email" required
                     value="<?= esc(old('email')) ?>"
                     class="w-full rounded-lg bg-slate-900/60 border border-slate-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400">
            </div>

            <div class="grid md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm mb-1">Senha</label>
                <input type="password" name="senha" required minlength="6"
                       class="w-full rounded-lg bg-slate-900/60 border border-slate-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400">
              </div>
              <div>
                <label class="block text-sm mb-1">Confirmar senha</label>
                <input type="password" name="senha2" required minlength="6"
                       class="w-full rounded-lg bg-slate-900/60 border border-slate-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400">
              </div>
            </div>

            <button class="w-full py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold transition">
              Criar conta
            </button>
          </form>
        </div>
      </div>

      <p class="mt-6 text-center text-slate-400 text-sm">
        Dica: use um e-mail válido — você usará para acessar o painel.
      </p>
    </div>
  </div>

  <script>
    const tabLogin    = document.getElementById('tab-login');
    const tabRegister = document.getElementById('tab-register');
    const formLogin   = document.getElementById('form-login');
    const formReg     = document.getElementById('form-register');

    function activate(tab) {
      const active = tab === 'login';
      formLogin.classList.toggle('hidden', !active);
      formReg.classList.toggle('hidden', active);

      tabLogin.classList.toggle('bg-slate-900/60', active);
      tabLogin.classList.toggle('bg-slate-800/60', !active);
      tabLogin.classList.toggle('border-emerald-400', active);
      tabLogin.classList.toggle('border-transparent', !active);

      tabRegister.classList.toggle('bg-slate-900/60', !active);
      tabRegister.classList.toggle('bg-slate-800/60', active);
      tabRegister.classList.toggle('border-emerald-400', !active);
      tabRegister.classList.toggle('border-transparent', active);
    }

    tabLogin.addEventListener('click', () => activate('login'));
    tabRegister.addEventListener('click', () => activate('register'));

    // Se houve erro no cadastro, abre a aba de cadastro automaticamente
    <?php if (session()->getFlashdata('errors_register')): ?>
      activate('register');
    <?php endif; ?>
  </script>
</body>
</html>
