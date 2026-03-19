<?php
/**
 * VoteSystem 4Top Servers — install.php
 * =====================================================================
 * Segurança:
 *   1. Bloqueia acesso total se já instalado (config.php existe)
 *   2. Token de sessão CSRF — impede pular steps via URL
 *   3. Auto-deleta este arquivo ao concluir o step 4
 *   4. Nenhum parâmetro GET (?reinstall, ?create) bypassa a proteção
 * =====================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Bloqueio pós-instalação ───────────────────────────────────────────────────
// Se config.php existe, instalação concluída. Nada bypassa isso.
if (file_exists(__DIR__ . '/config.php')) {
    http_response_code(404);
    exit('Not found.');
}

// ── CSRF token do wizard ──────────────────────────────────────────────────────
if (empty($_SESSION['install_token'])) {
    $_SESSION['install_token'] = bin2hex(random_bytes(16));
}
$install_token = $_SESSION['install_token'];

function verifyToken() {
    $sent = $_POST['_token'] ?? $_GET['_token'] ?? '';
    if (!hash_equals($_SESSION['install_token'], $sent)) {
        http_response_code(403);
        exit('Sessão inválida. <a href="install.php">Reiniciar instalação</a>.');
    }
}

// ── Projetos suportados ───────────────────────────────────────────────────────
$valid_projects = array('acis','l2jorion','l2jmobius','l2jsunrise','l2mythras','l2jlisvus');

$project_info = array(
    'acis'       => array('name'=>'aCis',       'icon'=>'⚔️',  'desc'=>'aCis 362~408 (SHA1 Base64) e 409+ (BCrypt) — detectado automaticamente','pass'=>'SHA1 / BCrypt'),
    'l2jorion'   => array('name'=>'L2JOrion',   'icon'=>'🛡️', 'desc'=>'L2JOrion  •  reward direto no items',                                    'pass'=>'SHA1 Base64'),
    'l2jmobius'  => array('name'=>'L2JMobius',  'icon'=>'🔮', 'desc'=>'L2JMobius (all Chronicle)  •  reward direto no items',                   'pass'=>'SHA1 Base64'),
    'l2jsunrise' => array('name'=>'L2JSunrise', 'icon'=>'🌅', 'desc'=>'L2JSunrise  •  reward direto no items',                                  'pass'=>'SHA1 Base64'),
    'l2mythras'  => array('name'=>'L2Mythras',  'icon'=>'⚡', 'desc'=>'L2Mythras  •  reward direto no items',                                   'pass'=>'SHA1 Base64'),
    'l2jlisvus'  => array('name'=>'L2JLisvus',  'icon'=>'⚜️', 'desc'=>'L2JLisvus C4  •  reward direto no items',                               'pass'=>'SHA1 Base64'),
);

// ── Estado do wizard vem da sessão, não do GET ────────────────────────────────
$step  = (int)($_SESSION['install_step'] ?? 1);
$error = '';

// ── Step 2: testa DB e grava config.php ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_db') {
    verifyToken();

    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass']      ?? '';
    $db_name = trim($_POST['db_name'] ?? '');
    $project = trim($_POST['project'] ?? 'acis');
    if (!in_array($project, $valid_projects)) $project = 'acis';

    if (empty($db_user) || empty($db_name)) {
        $error = 'Usuário e nome do banco são obrigatórios.';
        $step  = 2;
    } else {
        try {
            $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ));
            unset($pdo);

            $esc = function($s) { return addslashes($s); };
            $cfg  = "<?php\n// VoteSystem 4Top Servers — Config\n// NAO compartilhe este arquivo!\n\n";
            $cfg .= "define('DB_HOST',      '" . $esc($db_host) . "');\n";
            $cfg .= "define('DB_USER',      '" . $esc($db_user) . "');\n";
            $cfg .= "define('DB_PASS',      '" . $esc($db_pass) . "');\n";
            $cfg .= "define('DB_NAME',      '" . $esc($db_name) . "');\n";
            $cfg .= "define('GAME_PROJECT', '" . $esc($project)  . "');\n";
            $cfg .= "define('INSTALLED',    true);\n";

            if (file_put_contents(__DIR__ . '/config.php', $cfg) === false) {
                throw new RuntimeException('Não foi possível escrever config.php. Verifique as permissões da pasta.');
            }

            $_SESSION['install_step']    = 3;
            $_SESSION['install_project'] = $project;
            $step = 3;

        } catch (PDOException $e) {
            $error = 'Falha na conexão: ' . $e->getMessage();
            $step  = 2;
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
            $step  = 2;
        }
    }

    // Repopula form sem senha
    $_SESSION['install_form'] = array('db_host'=>$db_host,'db_user'=>$db_user,'db_name'=>$db_name,'project'=>$project);
}

// ── Step 3: cria tabelas ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_tables') {
    verifyToken();

    if (($_SESSION['install_step'] ?? 0) !== 3) {
        http_response_code(403);
        exit('Estado inválido.');
    }

    if (!file_exists(__DIR__ . '/config.php')) {
        $error = 'config.php não encontrado. Volte ao passo anterior.';
        $step  = 2;
    } else {
        require_once __DIR__ . '/config.php';
        require_once __DIR__ . '/includes/db.php';
        try {
            $pdo = getDB();
            $sqls = array(
                "CREATE TABLE IF NOT EXISTS `4top_tops` (
                    `id` INT NOT NULL AUTO_INCREMENT, `name` VARCHAR(100) NOT NULL,
                    `top_id` VARCHAR(200) NOT NULL, `token` VARCHAR(500) DEFAULT NULL,
                    `url` VARCHAR(500) DEFAULT NULL, `top_btn` VARCHAR(50) DEFAULT NULL,
                    `api_url` VARCHAR(500) DEFAULT NULL, `enabled` TINYINT(1) DEFAULT 1,
                    `sort_order` INT DEFAULT 0, PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS `4top_rewards` (
                    `id` INT NOT NULL AUTO_INCREMENT, `item_id` INT NOT NULL,
                    `quantity` INT NOT NULL DEFAULT 1, `description` VARCHAR(200) DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS `4top_log` (
                    `id` INT NOT NULL AUTO_INCREMENT, `login` VARCHAR(45) NOT NULL,
                    `ip` VARCHAR(45) NOT NULL, `top_id` INT NOT NULL,
                    `voted_at` DATETIME NOT NULL, `rewarded` TINYINT(1) DEFAULT 0,
                    `rewarded_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`), INDEX `idx_login_top` (`login`,`top_id`), INDEX `idx_ip` (`ip`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS `4top_reward_claims` (
                    `id` INT NOT NULL AUTO_INCREMENT, `login` VARCHAR(45) NOT NULL,
                    `claimed_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`), INDEX `idx_login` (`login`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            );
            foreach ($sqls as $sql) $pdo->exec($sql);

            $_SESSION['install_step'] = 4;
            $step = 4;

            // ── AUTO-DELEÇÃO ──────────────────────────────────────────────
            // Deleta este arquivo. Se falhar, o bloqueio por config.php
            // ainda impede qualquer acesso futuro ao installer.
            @unlink(__FILE__);

        } catch (PDOException $e) {
            $error = 'Erro ao criar tabelas: ' . $e->getMessage();
            $step  = 3;
        }
    }
}

// ── Navegação GET (step 1 e step 2 com projeto) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $get_step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
    if ($get_step === 1) {
        $_SESSION['install_step'] = 1;
        $step = 1;
    } elseif ($get_step === 2 && isset($_GET['project'])) {
        verifyToken();
        $proj = in_array($_GET['project'], $valid_projects) ? $_GET['project'] : 'acis';
        $_SESSION['install_step']    = 2;
        $_SESSION['install_project'] = $proj;
        $step = 2;
    }
    // GET para steps 3/4 é ignorado — só aceita POST
}

$installed_project = $_SESSION['install_project'] ?? 'acis';
$form              = $_SESSION['install_form']    ?? array();

function t_install($key) {
    $lang      = isset($_COOKIE['vs_lang']) ? $_COOKIE['vs_lang'] : 'pt';
    $supported = array('pt','es','en','ru');
    if (!in_array($lang, $supported)) $lang = 'pt';
    $labels = array(
        'pt' => array('install_step1'=>'Projeto',  'install_step2'=>'Banco',    'install_step3'=>'Tabelas','install_step4'=>'Pronto'),
        'es' => array('install_step1'=>'Proyecto', 'install_step2'=>'BD',       'install_step3'=>'Tablas', 'install_step4'=>'Listo'),
        'en' => array('install_step1'=>'Project',  'install_step2'=>'Database', 'install_step3'=>'Tables', 'install_step4'=>'Done'),
        'ru' => array('install_step1'=>'Проект',   'install_step2'=>'БД',       'install_step3'=>'Таблицы','install_step4'=>'Готово'),
    );
    return isset($labels[$lang][$key]) ? $labels[$lang][$key] : $key;
}

$tables_base = array(
    '4top_tops'          => 'Sites de TOP configurados',
    '4top_rewards'       => 'Itens de recompensa por voto',
    '4top_log'           => 'Histórico de votos',
    '4top_reward_claims' => 'Registro de recompensas coletadas',
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="install_title">VoteSystem 4Top — Instalação</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/localforage/1.10.0/localforage.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="assets/js/i18n.js"></script>
</head>
<body>

<div id="langSwitcher" title="Language / Idioma / Язык"
     style="position:fixed;top:16px;right:18px;z-index:200;display:flex;align-items:center;gap:3px;
            background:rgba(4,5,8,0.82);border:1px solid rgba(201,168,76,0.28);border-radius:8px;
            padding:5px 7px;backdrop-filter:blur(14px);box-shadow:0 4px 20px rgba(0,0,0,0.5)">
    <button class="lang-btn" data-lang="pt" title="Português (Brasil)" aria-label="Português (Brasil)"><img src="https://flagcdn.com/br.svg" width="24" height="18" alt="BR" loading="lazy"></button>
    <button class="lang-btn" data-lang="es" title="Español"            aria-label="Español"><img src="https://flagcdn.com/es.svg" width="24" height="18" alt="ES" loading="lazy"></button>
    <button class="lang-btn" data-lang="en" title="English (US)"       aria-label="English"><img src="https://flagcdn.com/us.svg" width="24" height="18" alt="EN" loading="lazy"></button>
    <button class="lang-btn" data-lang="ru" title="Русский"            aria-label="Русский"><img src="https://flagcdn.com/ru.svg" width="24" height="18" alt="RU" loading="lazy"></button>
</div>

<div class="wizard-wrap">
  <div class="wizard-box animate-in">

    <div class="login-logo" style="text-align:center;margin-bottom:2rem;">
      <div class="logo-text">⚜ VoteSystem</div>
      <div class="logo-sub" data-i18n="install_subtitle">4Top Servers — Assistente de Instalação</div>
    </div>

    <div class="wizard-steps">
      <?php
      $steps_labels = array(t_install('install_step1'),t_install('install_step2'),t_install('install_step3'),t_install('install_step4'));
      foreach ($steps_labels as $i => $lbl):
          $n = $i + 1;
          $cls = $step > $n ? 'done' : ($step === $n ? 'active' : '');
      ?>
      <div class="wizard-step <?= $cls ?>">
        <div class="step-num"><?= $step > $n ? '✓' : $n ?></div>
        <div class="step-label"><?= htmlspecialchars($lbl) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="card">

      <?php if ($error): ?>
      <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- ══ STEP 1 ══ -->
      <?php if ($step === 1): ?>
      <div class="card-title" data-i18n="install_s1_title">⚙ Selecione o Projeto L2J</div>
      <p style="font-size:.85rem;color:var(--text-secondary);margin-bottom:1.5rem;line-height:1.6;" data-i18n="install_s1_desc">
        Escolha o emulador do servidor. Isso define como as senhas são verificadas e como os rewards são entregues.
      </p>
      <div class="project-grid" id="projectGrid">
        <?php foreach ($project_info as $key => $info): ?>
        <div class="project-option">
          <input type="radio" name="project" id="proj_<?= $key ?>" value="<?= $key ?>" <?= $key === 'acis' ? 'checked' : '' ?>>
          <label for="proj_<?= $key ?>">
            <span class="proj-icon"><?= $info['icon'] ?></span>
            <span class="proj-name"><?= $info['name'] ?></span>
            <span style="font-size:.65rem;color:var(--text-dim)"><?= htmlspecialchars($info['desc']) ?></span>
            <span style="font-size:.6rem;color:var(--gold-dim);margin-top:.2rem">Hash: <?= htmlspecialchars($info['pass']) ?></span>
          </label>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="alert alert-info" style="font-size:.8rem;margin-bottom:1rem;" data-i18n="install_s1_info">
        ℹ Tops e rewards são configurados depois, no painel de admin.
      </div>
      <button type="button" class="btn btn-primary btn-full" onclick="goStep2()" data-i18n="install_s1_btn">
        Próximo — Configurar Banco de Dados ›
      </button>
      <script>
      function goStep2() {
          var proj = document.querySelector('#projectGrid input[name="project"]:checked');
          if (!proj) { alert('Selecione um projeto.'); return; }
          window.location.href = 'install.php?step=2&project=' + encodeURIComponent(proj.value) + '&_token=<?= urlencode($install_token) ?>';
      }
      </script>
      <?php endif; ?>

      <!-- ══ STEP 2 ══ -->
      <?php if ($step === 2): ?>
      <div class="card-title" data-i18n="install_s2_title">🗄 Configuração MySQL</div>
      <div class="alert alert-warning" style="font-size:.8rem;margin-bottom:1.25rem;">
        ⚠ Use o banco de dados do servidor
        <strong><?= htmlspecialchars($project_info[$installed_project]['name'] ?? $installed_project) ?></strong>
        onde ficam as contas dos jogadores.
      </div>
      <form method="POST" action="install.php">
        <input type="hidden" name="action"  value="save_db">
        <input type="hidden" name="_token"  value="<?= htmlspecialchars($install_token) ?>">
        <input type="hidden" name="project" value="<?= htmlspecialchars($installed_project) ?>">
        <div class="form-group">
          <label class="form-label" data-i18n="install_s2_host">Host</label>
          <input type="text" name="db_host" class="form-control"
            value="<?= htmlspecialchars($form['db_host'] ?? 'localhost') ?>" placeholder="localhost" required autocomplete="off">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
          <div class="form-group">
            <label class="form-label" data-i18n="install_s2_user">Usuário</label>
            <input type="text" name="db_user" class="form-control"
              value="<?= htmlspecialchars($form['db_user'] ?? '') ?>" placeholder="root" required autocomplete="off">
          </div>
          <div class="form-group">
            <label class="form-label" data-i18n="install_s2_pass">Senha</label>
            <input type="password" name="db_pass" class="form-control" placeholder="••••••" autocomplete="new-password">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" data-i18n="install_s2_dbname">Nome do Banco (Database)</label>
          <input type="text" name="db_name" class="form-control"
            value="<?= htmlspecialchars($form['db_name'] ?? '') ?>" placeholder="l2jdb" required autocomplete="off">
        </div>
        <div style="display:flex;gap:.75rem;margin-top:1rem;">
          <a href="install.php?step=1" class="btn btn-ghost" style="flex:0 0 auto;" data-i18n="install_s2_back">‹ Voltar</a>
          <button type="submit" class="btn btn-primary" style="flex:1;" data-i18n="install_s2_btn">Testar Conexão &amp; Continuar ›</button>
        </div>
      </form>
      <?php endif; ?>

      <!-- ══ STEP 3 ══ -->
      <?php if ($step === 3): ?>
      <div class="card-title" data-i18n="install_s3_title">📋 Criar Tabelas</div>
      <div class="alert alert-success" style="margin-bottom:1.25rem;" data-i18n="install_s3_ok">
        ✓ Conexão com o banco de dados estabelecida com sucesso!
      </div>
      <p style="font-size:.85rem;color:var(--text-secondary);margin-bottom:1.25rem;line-height:1.6;" data-i18n="install_s3_desc">
        As tabelas abaixo serão criadas. Tabelas existentes <strong>não serão afetadas</strong>:
      </p>
      <ul style="list-style:none;display:flex;flex-direction:column;gap:.4rem;margin-bottom:1.5rem;">
        <?php foreach (array('4top_tops'=>'install_tbl_tops','4top_rewards'=>'install_tbl_rewards','4top_log'=>'install_tbl_log','4top_reward_claims'=>'install_tbl_claims') as $t => $key): ?>
        <li style="display:flex;align-items:center;gap:.75rem;font-size:.82rem;padding:.5rem .75rem;background:rgba(0,0,0,.2);border-radius:4px;border:1px solid var(--border);">
          <span style="color:var(--gold)">📁</span>
          <code style="font-family:'Courier New',monospace;color:var(--gold-dim)"><?= $t ?></code>
          <span style="color:var(--text-dim);font-size:.7rem;margin-left:auto" data-i18n="<?= $key ?>">— <?= htmlspecialchars($tables_base[$t]) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="alert alert-info" style="font-size:.8rem;margin-bottom:1rem;" data-i18n="install_s3_info">
        ✅ Rewards são inseridos diretamente na tabela <code>items</code> do jogo — sem mod Java ou cron necessário.
      </div>
      <form method="POST" action="install.php">
        <input type="hidden" name="action" value="create_tables">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($install_token) ?>">
        <button type="submit" class="btn btn-primary btn-full" data-i18n="install_s3_btn">✓ Criar Tabelas e Finalizar ›</button>
      </form>
      <?php endif; ?>

      <!-- ══ STEP 4 ══ -->
      <?php if ($step === 4): ?>
      <?php unset($_SESSION['install_token'],$_SESSION['install_step'],$_SESSION['install_project'],$_SESSION['install_form']); ?>
      <div style="text-align:center;padding:1rem 0;">
        <div style="font-size:3rem;margin-bottom:1rem">🎉</div>
        <h2 style="font-family:'Cinzel Decorative',serif;color:var(--gold);font-size:1.4rem;margin-bottom:.5rem" data-i18n="install_s4_title">
          Instalação Concluída!
        </h2>
        <p style="color:var(--text-secondary);font-size:.9rem;margin-bottom:1.75rem;line-height:1.6;" data-i18n="install_s4_desc">
          O VoteSystem está pronto.<br>
          Faça login com uma conta que tenha <strong style="color:var(--gold)">access_level &ge; 1</strong> para configurar tops e rewards.
        </p>
        <div class="alert alert-info" style="text-align:left;margin-bottom:1.25rem;font-size:.8rem;line-height:1.6;">
          <strong data-i18n="install_s4_reward_title">✅ Entrega de Reward:</strong><br>
          <span data-i18n="install_s4_reward_desc">Os itens são inseridos diretamente em <code>items</code> no personagem escolhido.</span><br>
          <span style="color:var(--text-dim)" data-i18n="install_s4_reward_sub">Nenhum mod Java ou cron necessário.</span>
        </div>
        <div class="alert alert-success" style="text-align:left;margin-bottom:1.5rem;font-size:.8rem;">
          <strong>🗑 install.php removido automaticamente.</strong><br>
          <span style="color:var(--text-dim)">O arquivo de instalação foi deletado com sucesso. Nenhuma ação adicional necessária.</span>
        </div>
        <a href="index.php" class="btn btn-primary btn-full" data-i18n="install_s4_btn">⚜ Ir para o VoteSystem</a>
      </div>
      <?php endif; ?>

    </div><!-- .card -->

    <div class="footer" style="border:none;margin-top:1.5rem;" data-i18n="install_footer">
      VoteSystem 4Top Servers &mdash; by <span class="text-gold">4TeamBR</span>
    </div>

  </div>
</div>
</body>
</html>
