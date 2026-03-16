<?php
/**
 * VoteSystem 4Top Servers - Installer
 * Compatible: PHP 5.4 ~ 8.2
 */

$step  = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

$already_installed = file_exists(__DIR__ . '/config.php');
if ($already_installed && !isset($_GET['reinstall']) && !isset($_GET['create']) && $step < 3) {
    header('Location: index.php');
    exit;
}

// ── Step 2: testa DB e escreve config.php ─────────────────────────────────────
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';
    $db_name = trim($_POST['db_name'] ?? '');
    $project = trim($_POST['project'] ?? 'acis');

    $valid_projects = array('acis', 'l2jserver', 'l2jmobius');
    if (!in_array($project, $valid_projects)) $project = 'acis';

    try {
        $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8";
        $pdo = new PDO($dsn, $db_user, $db_pass, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ));

        $esc = function($s) { return str_replace("'", "\\'", $s); };
        $cfg  = "<?php\n";
        $cfg .= "// VoteSystem 4Top Servers — Config (gerado automaticamente)\n";
        $cfg .= "// NAO compartilhe este arquivo!\n\n";
        $cfg .= "define('DB_HOST',      '" . $esc($db_host) . "');\n";
        $cfg .= "define('DB_USER',      '" . $esc($db_user) . "');\n";
        $cfg .= "define('DB_PASS',      '" . $esc($db_pass) . "');\n";
        $cfg .= "define('DB_NAME',      '" . $esc($db_name) . "');\n";
        $cfg .= "define('GAME_PROJECT', '" . $esc($project)  . "');\n";
        $cfg .= "define('INSTALLED',    true);\n";

        if (file_put_contents(__DIR__ . '/config.php', $cfg) === false) {
            throw new RuntimeException('Não foi possível escrever config.php. Verifique as permissões da pasta.');
        }

        header('Location: install.php?step=3&project=' . urlencode($project));
        exit;

    } catch (PDOException $e) {
        $error = 'Falha na conexão com o banco: ' . $e->getMessage();
        $step  = 2;
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        $step  = 2;
    }
}

// ── Step 3: cria as tabelas ───────────────────────────────────────────────────
if ($step === 3 && isset($_GET['create'])) {
    if (!file_exists(__DIR__ . '/config.php')) {
        header('Location: install.php');
        exit;
    }
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/db.php';

    try {
        $pdo = getDB();

        // Tabelas base — todos os projetos
        $sqls = array(
            "CREATE TABLE IF NOT EXISTS `icpvote_tops` (
                `id`         INT(11)      NOT NULL AUTO_INCREMENT,
                `name`       VARCHAR(100) NOT NULL,
                `top_id`     VARCHAR(200) NOT NULL,
                `token`      VARCHAR(500) DEFAULT NULL,
                `url`        VARCHAR(500) DEFAULT NULL,
                `top_btn`    VARCHAR(50)  DEFAULT NULL,
                `api_url`    VARCHAR(500) DEFAULT NULL,
                `enabled`    TINYINT(1)   DEFAULT 1,
                `sort_order` INT(11)      DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

            "CREATE TABLE IF NOT EXISTS `icpvote_rewards` (
                `id`          INT(11) NOT NULL AUTO_INCREMENT,
                `item_id`     INT(11) NOT NULL,
                `quantity`    INT(11) NOT NULL DEFAULT 1,
                `description` VARCHAR(200) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

            "CREATE TABLE IF NOT EXISTS `icpvote_log` (
                `id`          INT(11)     NOT NULL AUTO_INCREMENT,
                `login`       VARCHAR(45) NOT NULL,
                `ip`          VARCHAR(45) NOT NULL,
                `top_id`      INT(11)     NOT NULL,
                `voted_at`    DATETIME    NOT NULL,
                `rewarded`    TINYINT(1)  DEFAULT 0,
                `rewarded_at` DATETIME    DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_login_top` (`login`, `top_id`),
                INDEX `idx_ip` (`ip`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

            "CREATE TABLE IF NOT EXISTS `icpvote_reward_claims` (
                `id`         INT(11)     NOT NULL AUTO_INCREMENT,
                `login`      VARCHAR(45) NOT NULL,
                `claimed_at` DATETIME    NOT NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_login` (`login`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8",
        );

        // Apenas L2JServer e L2JMobius precisam da fila de pendentes
        if (GAME_PROJECT !== 'acis') {
            $sqls[] = "CREATE TABLE IF NOT EXISTS `icpvote_pending_rewards` (
                `id`           INT(11)     NOT NULL AUTO_INCREMENT,
                `login`        VARCHAR(45) NOT NULL,
                `item_id`      INT(11)     NOT NULL,
                `quantity`     INT(11)     NOT NULL DEFAULT 1,
                `created_at`   DATETIME    NOT NULL,
                `delivered`    TINYINT(1)  DEFAULT 0,
                `delivered_at` DATETIME    DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_login` (`login`),
                INDEX `idx_delivered` (`delivered`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
        }

        foreach ($sqls as $sql) {
            $pdo->exec($sql);
        }

        $success = 'Tabelas criadas com sucesso!';
        $step    = 4;

    } catch (PDOException $e) {
        $error = 'Erro ao criar tabelas: ' . $e->getMessage();
    }
}

// ── Lê projeto do config.php ──────────────────────────────────────────────────
$installed_project = $_GET['project'] ?? 'acis';
if (file_exists(__DIR__ . '/config.php')) {
    $cfg_raw = file_get_contents(__DIR__ . '/config.php');
    if (preg_match("/define\('GAME_PROJECT',\s*'([^']+)'\)/", $cfg_raw, $m)) {
        $installed_project = $m[1];
    }
}

$project_info = array(
    'acis'      => array('name' => 'aCis',      'icon' => '⚔️',  'desc' => 'aCis 362+  •  reward direto no items',     'pass' => 'SHA1 hex'),
    'l2jserver' => array('name' => 'L2JServer', 'icon' => '🛡️', 'desc' => 'L2J4Team / L2JLisvus  •  fila de reward',  'pass' => 'SHA1 Base64'),
    'l2jmobius' => array('name' => 'L2JMobius', 'icon' => '🔮', 'desc' => 'L2JMobius (all Chronicle)  •  fila reward', 'pass' => 'SHA256 hex'),
);

// Tabelas por projeto (para exibir no Step 3)
$tables_base = array(
    'icpvote_tops'          => 'Sites de TOP configurados',
    'icpvote_rewards'       => 'Itens de recompensa por voto',
    'icpvote_log'           => 'Histórico de votos',
    'icpvote_reward_claims' => 'Registro de recompensas coletadas',
);
$tables_queue = array(
    'icpvote_pending_rewards' => 'Fila de rewards (L2JServer / L2JMobius)',
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VoteSystem 4Top — Instalação</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<div class="wizard-wrap">
  <div class="wizard-box animate-in">

    <div class="login-logo" style="text-align:center;margin-bottom:2rem;">
      <div class="logo-text">⚜ VoteSystem</div>
      <div class="logo-sub">4Top Servers — Assistente de Instalação</div>
    </div>

    <!-- Steps -->
    <div class="wizard-steps">
      <?php
      $steps_labels = array('Projeto', 'Banco', 'Tabelas', 'Pronto');
      foreach ($steps_labels as $i => $lbl):
          $n = $i + 1;
          $cls = '';
          if ($step > $n) $cls = 'done';
          elseif ($step === $n) $cls = 'active';
      ?>
      <div class="wizard-step <?= $cls ?>">
        <div class="step-num"><?= $step > $n ? '✓' : $n ?></div>
        <div class="step-label"><?= $lbl ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="card">

      <?php if ($error): ?>
      <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
      <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <!-- ── STEP 1 ── -->
      <?php if ($step === 1): ?>
      <div class="card-title">⚙ Selecione o Projeto L2J</div>
      <p style="font-size:.85rem;color:var(--text-secondary);margin-bottom:1.5rem;line-height:1.6;">
        Escolha o emulador do servidor. Isso define como as senhas são verificadas
        e como os rewards são entregues aos jogadores.
      </p>

      <form method="GET" action="install.php">
        <input type="hidden" name="step" value="2">
        <div class="project-grid">
          <?php foreach ($project_info as $key => $info): ?>
          <div class="project-option">
            <input type="radio" name="project" id="proj_<?= $key ?>" value="<?= $key ?>"
              <?= ($key === 'acis') ? 'checked' : '' ?>>
            <label for="proj_<?= $key ?>">
              <span class="proj-icon"><?= $info['icon'] ?></span>
              <span class="proj-name"><?= $info['name'] ?></span>
              <span style="font-size:.65rem;color:var(--text-dim)"><?= $info['desc'] ?></span>
              <span style="font-size:.6rem;color:var(--gold-dim);margin-top:.2rem">Hash: <?= $info['pass'] ?></span>
            </label>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="alert alert-info" style="font-size:.8rem;margin-bottom:1rem;">
          ℹ Tops e rewards são configurados depois, no painel de admin.
        </div>

        <button type="submit" class="btn btn-primary btn-full">
          Próximo — Configurar Banco de Dados ›
        </button>
      </form>
      <?php endif; ?>

      <!-- ── STEP 2 ── -->
      <?php if ($step === 2): ?>
      <?php
        $sel_project = $_POST['project'] ?? $_GET['project'] ?? 'acis';
        if (!isset($project_info[$sel_project])) $sel_project = 'acis';
      ?>
      <div class="card-title">🗄 Configuração MySQL</div>
      <div class="alert alert-warning" style="font-size:.8rem;margin-bottom:1.25rem;">
        ⚠ Use o banco de dados do servidor <strong><?= $project_info[$sel_project]['name'] ?></strong>
        onde ficam as contas dos jogadores.
      </div>

      <form method="POST" action="install.php?step=2">
        <input type="hidden" name="project" value="<?= htmlspecialchars($sel_project) ?>">

        <div class="form-group">
          <label class="form-label">Host</label>
          <input type="text" name="db_host" class="form-control"
            value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>"
            placeholder="localhost" required>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
          <div class="form-group">
            <label class="form-label">Usuário</label>
            <input type="text" name="db_user" class="form-control"
              value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>"
              placeholder="root" required>
          </div>
          <div class="form-group">
            <label class="form-label">Senha</label>
            <input type="password" name="db_pass" class="form-control"
              placeholder="••••••">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Nome do Banco (Database)</label>
          <input type="text" name="db_name" class="form-control"
            value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>"
            placeholder="l2jdb" required>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:1rem;">
          <a href="install.php" class="btn btn-ghost" style="flex:0 0 auto;">‹ Voltar</a>
          <button type="submit" class="btn btn-primary" style="flex:1;">
            Testar Conexão &amp; Continuar ›
          </button>
        </div>
      </form>
      <?php endif; ?>

      <!-- ── STEP 3 ── -->
      <?php if ($step === 3): ?>
      <div class="card-title">📋 Criar Tabelas</div>
      <div class="alert alert-success" style="margin-bottom:1.25rem;">
        ✓ Conexão com o banco de dados estabelecida com sucesso!
      </div>

      <p style="font-size:.85rem;color:var(--text-secondary);margin-bottom:1.25rem;line-height:1.6;">
        As tabelas abaixo serão criadas. Tabelas existentes <strong>não serão afetadas</strong>:
      </p>

      <?php
      $tables_show = $tables_base;
      if ($installed_project !== 'acis') {
          $tables_show = array_merge($tables_show, $tables_queue);
      }
      ?>
      <ul style="list-style:none;display:flex;flex-direction:column;gap:.4rem;margin-bottom:1.5rem;">
        <?php foreach ($tables_show as $t => $d): ?>
        <li style="display:flex;align-items:center;gap:.75rem;font-size:.82rem;padding:.5rem .75rem;background:rgba(0,0,0,.2);border-radius:4px;border:1px solid var(--border);">
          <span style="color:var(--gold)">📁</span>
          <code style="font-family:'Courier New',monospace;color:var(--gold-dim)"><?= $t ?></code>
          <span style="color:var(--text-dim);font-size:.7rem;margin-left:auto">— <?= $d ?></span>
        </li>
        <?php endforeach; ?>
      </ul>

      <?php if ($installed_project === 'acis'): ?>
      <div class="alert alert-info" style="font-size:.8rem;margin-bottom:1rem;">
        ⚔ <strong>aCis:</strong> rewards são inseridos diretamente na tabela
        <code>items</code> do jogo — sem mod Java ou cron necessário.
      </div>
      <?php else: ?>
      <div class="alert alert-info" style="font-size:.8rem;margin-bottom:1rem;">
        🛡 <strong><?= htmlspecialchars($project_info[$installed_project]['name'] ?? $installed_project) ?>:</strong>
        rewards ficam na fila <code>icpvote_pending_rewards</code> para entrega via cron ou mod Java.
      </div>
      <?php endif; ?>

      <a href="install.php?step=3&create=1&project=<?= urlencode($installed_project) ?>"
         class="btn btn-primary btn-full">
        ✓ Criar Tabelas e Finalizar ›
      </a>
      <?php endif; ?>

      <!-- ── STEP 4 ── -->
      <?php if ($step === 4): ?>
      <div style="text-align:center;padding:1rem 0;">
        <div style="font-size:3rem;margin-bottom:1rem">🎉</div>
        <h2 style="font-family:'Cinzel Decorative',serif;color:var(--gold);font-size:1.4rem;margin-bottom:.5rem">
          Instalação Concluída!
        </h2>
        <p style="color:var(--text-secondary);font-size:.9rem;margin-bottom:1.75rem;line-height:1.6;">
          O VoteSystem está pronto.<br>
          Faça login com uma conta que tenha <strong style="color:var(--gold)">access_level &ge; 1</strong>
          para configurar tops e rewards.
        </p>

        <?php if ($installed_project === 'acis'): ?>
        <div class="alert alert-info" style="text-align:left;margin-bottom:1.25rem;font-size:.8rem;line-height:1.6;">
          ⚔ <strong>aCis — Entrega de Reward:</strong><br>
          Os itens são inseridos diretamente em <code>items</code> no personagem mais recente da conta.<br>
          <span style="color:var(--text-dim)">Nenhum mod Java ou cron necessário.</span>
        </div>
        <?php else: ?>
        <div class="alert alert-info" style="text-align:left;margin-bottom:1.25rem;font-size:.8rem;line-height:1.6;">
          🛡 <strong>Entrega de Reward (<?= htmlspecialchars($project_info[$installed_project]['name'] ?? $installed_project) ?>):</strong><br>
          Os rewards ficam na fila <code>icpvote_pending_rewards</code>.
          Configure um cron job ou mod Java para processar a entrega.
        </div>
        <?php endif; ?>

        <div class="alert alert-warning" style="text-align:left;margin-bottom:1.5rem;font-size:.8rem;">
          🔒 <strong>Segurança:</strong> Exclua ou renomeie <code>install.php</code> após configurar o sistema!
        </div>

        <a href="index.php" class="btn btn-primary btn-full">
          ⚜ Ir para o VoteSystem
        </a>
      </div>
      <?php endif; ?>

    </div><!-- .card -->

    <div class="footer" style="border:none;margin-top:1.5rem;">
      VoteSystem 4Top Servers &mdash; by <span class="text-gold">4TeamBR</span>
    </div>

  </div>
</div>
</body>
</html>