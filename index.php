<?php
/**
 * VoteSystem 4Top Servers — Login Page
 * Compatible: PHP 5.4 ~ 8.2
 */

if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/layout.php';

startSession();

if (isLoggedIn()) {
    header('Location: vote.php');
    exit;
}

$error = '';
$msg   = isset($_GET['msg']) ? $_GET['msg'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim(isset($_POST['login'])    ? $_POST['login']    : '');
    $password =      isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($login) || empty($password)) {
        $error = 'Preencha o login e a senha.';
    } else {
        $user = gameLogin($login, $password);
        if ($user) {
            sessionLogin($user);
            header('Location: vote.php');
            exit;
        } else {
            $error = 'Login ou senha incorretos.';
        }
    }
}

$projects     = array('acis' => 'aCis', 'l2jorion' => 'L2JOrion', 'l2jmobius' => 'L2JMobius', 'l2jsunrise' => 'L2JSunrise', 'l2mythras' => 'L2Mythras', 'l2jlisvus' => 'L2JLisvus');
$project_name = isset($projects[GAME_PROJECT]) ? $projects[GAME_PROJECT] : GAME_PROJECT;

// Layout config
$siteName   = defined('LAYOUT_SITE_NAME')   ? LAYOUT_SITE_NAME   : 'VoteSystem';
$siteSuffix = defined('LAYOUT_SITE_SUFFIX')  ? LAYOUT_SITE_SUFFIX  : '4Top Servers';
$favicon    = defined('LAYOUT_FAVICON')      ? LAYOUT_FAVICON      : '';
$extraCss   = defined('LAYOUT_EXTRA_CSS')    ? LAYOUT_EXTRA_CSS    : '';
$footer     = defined('LAYOUT_FOOTER')       ? LAYOUT_FOOTER       : 'VoteSystem <span class="text-gold">4Top Servers</span>';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($siteName) ?> — Login | <?= htmlspecialchars($siteSuffix) ?></title>
  <?php if ($favicon): $ext = strtolower(pathinfo($favicon, PATHINFO_EXTENSION)); $mime = ($ext==='png')?'image/png':(($ext==='svg')?'image/svg+xml':'image/x-icon'); ?>
  <link rel="icon" type="<?= $mime ?>" href="<?= htmlspecialchars($favicon) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="assets/css/main.css">
  <?php if (trim($extraCss)): ?><style><?= $extraCss ?></style><?php endif; ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/localforage/1.10.0/localforage.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="assets/js/i18n.js"></script>
  <style>
    .rune-bg { position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden; }
    .rune { position:absolute;font-size:1.5rem;opacity:0;color:rgba(201,168,76,.07);animation:floatRune 12s infinite; }
    @keyframes floatRune {
      0%   { opacity:0;transform:translateY(100vh) rotate(0deg); }
      10%  { opacity:1; }
      90%  { opacity:1; }
      100% { opacity:0;transform:translateY(-10vh) rotate(360deg); }
    }
  </style>
</head>
<body>

<!-- Seletor de idioma fixo (canto sup. direito) -->
<div class="lang-switcher" id="langSwitcher" title="Language / Idioma / Язык">
  <button class="lang-btn" data-lang="pt" title="Português (Brasil)" aria-label="Português (Brasil)"><img src="https://flagcdn.com/br.svg" width="24" height="18" alt="BR" loading="lazy"></button>
  <button class="lang-btn" data-lang="es" title="Español"            aria-label="Español"><img src="https://flagcdn.com/es.svg" width="24" height="18" alt="ES" loading="lazy"></button>
  <button class="lang-btn" data-lang="en" title="English (US)"       aria-label="English"><img src="https://flagcdn.com/us.svg" width="24" height="18" alt="EN" loading="lazy"></button>
  <button class="lang-btn" data-lang="ru" title="Русский"            aria-label="Русский"><img src="https://flagcdn.com/ru.svg" width="24" height="18" alt="RU" loading="lazy"></button>
</div>

<div class="rune-bg" aria-hidden="true">
<?php
$runes = array('ᚠ','ᚢ','ᚦ','ᚨ','ᚱ','ᚲ','ᚷ','ᚹ','ᚺ','ᚾ','ᛁ','ᛃ','ᛇ','ᛈ','ᛉ','ᛊ','ᛏ','ᛒ','ᛖ','ᛗ','ᛚ','ᛜ','ᛞ','ᛟ');
for ($i = 0; $i < 14; $i++) {
    echo '<span class="rune" style="left:'.rand(3,95).'%;animation-delay:'.rand(0,10).'s;animation-duration:'.rand(8,16).'s">'.$runes[array_rand($runes)].'</span>';
}
?>
</div>

<div style="position:relative;z-index:1;" class="login-wrap">
  <div class="login-box animate-in">

    <!-- Seletor de idioma fixo no topo direito via CSS global -->

    <div class="login-logo">
      <div style="margin-bottom:.75rem;line-height:1">
        <img src="https://i.imgur.com/MAuPJrp.png" alt="<?= htmlspecialchars($siteName) ?>" style="height:48px;width:auto">
      </div>
      <div class="logo-sub"><?= htmlspecialchars($siteSuffix) ?> &mdash; <?= e($project_name) ?></div>
    </div>

    <?php if ($msg === 'nologin'): ?>
    <div class="alert alert-warning" data-i18n="login_nologin_warn">⚠ Você precisa estar logado para acessar essa página.</div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error" id="loginErrorMsg">✗ <?= e($error) ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-title" data-i18n="login_card_title">🔐 Acesso do Jogador</div>

      <form method="POST" action="index.php" id="loginForm">
        <div class="form-group">
          <label class="form-label" for="login" data-i18n="login_account_label">Login da Conta</label>
          <input type="text" id="login" name="login" class="form-control"
            value="<?= e($_POST['login'] ?? '') ?>"
            data-i18n-placeholder="login_ph_login"
            placeholder="seu_login" autocomplete="username" autofocus required>
        </div>

        <div class="form-group">
          <label class="form-label" for="password" data-i18n="login_password_label">Senha</label>
          <div style="position:relative">
            <input type="password" id="password" name="password" class="form-control"
              placeholder="••••••••" autocomplete="current-password" required
              style="padding-right:2.5rem">
            <button type="button" id="togglePwd"
              style="position:absolute;right:.6rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-dim);cursor:pointer;font-size:1rem;padding:0;line-height:1"
              data-i18n-title="login_show_pwd"
              title="Mostrar senha">👁</button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:1rem" id="submitBtn"
          data-i18n="login_submit">
          ⚜ Entrar
        </button>
      </form>
    </div>

    <div class="card" style="margin-top:1rem;padding:1rem 1.5rem;">
      <div style="font-size:.78rem;color:var(--text-dim);text-align:center;line-height:1.7">
        <span data-i18n="login_hint_main">Use a mesma conta e senha do servidor de jogo.</span><br>
        <span style="color:var(--gold-dim)" data-i18n="login_hint_sub">Vote diariamente para ganhar recompensas!</span>
      </div>
    </div>

    <div class="footer" style="border:none;margin-top:1.5rem">
      <?= $footer ?>
    </div>
  </div>
</div>

<script>
document.getElementById('togglePwd').addEventListener('click', function() {
    var p = document.getElementById('password');
    p.type = p.type === 'password' ? 'text' : 'password';
});
document.getElementById('loginForm').addEventListener('submit', function() {
    var b = document.getElementById('submitBtn');
    b.classList.add('loading');
    b.innerHTML = (window.vsI18n) ? window.vsI18n.t('msg_entering') : 'Entrando...';
});
</script>
</body>
</html>