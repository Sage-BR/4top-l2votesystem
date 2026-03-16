<?php
/**
 * VoteSystem 4Top Servers — Voting Page
 * Compatible: PHP 5.4 ~ 8.2
 */

if (!file_exists(__DIR__ . '/config.php')) { header('Location: install.php'); exit; }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/bootstrap.php';

requireLogin();

$login = $_SESSION['vs_login'];
$ip    = clientIp();

// ── AJAX handlers ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // Etapa 1: verificar votos e listar personagens
    if ($_POST['action'] === 'check_votes') {
        echo json_encode(checkVotes($login, $ip));
        exit;
    }

    // Etapa 2: entregar recompensa no personagem escolhido
    if ($_POST['action'] === 'claim_reward') {
        $objId = (int)(isset($_POST['obj_id']) ? $_POST['obj_id'] : 0);
        if ($objId <= 0) {
            echo json_encode(array('status' => 'error', 'msg' => '❌ Selecione um personagem.'));
            exit;
        }
        echo json_encode(claimReward($login, $objId));
        exit;
    }
}

// ── Build tops status — DB primeiro, depois API individual ───────────────
$tops       = getTops();
$rewards    = getRewards();
$totalVotes = countVotes($login);

// Verifica se já coletou recompensa nas últimas 24h
$db     = getDB();
$stmt   = $db->prepare("SELECT claimed_at FROM icpvote_reward_claims WHERE login = ? AND claimed_at > DATE_SUB(NOW(), INTERVAL 12 HOUR) ORDER BY claimed_at DESC LIMIT 1");
$stmt->execute(array($login));
$lastClaim = $stmt->fetch();

$tops_status = array();
foreach ($tops as $top) {
    $cooldown_left = 0;
    $can_vote      = true;

    if ($lastClaim) {
        // Já coletou hoje — bloqueia todos os botões com o cooldown do claim
        $secs_since_claim = time() - strtotime($lastClaim['claimed_at']);
        $cooldown_left    = max(0, 43200 - $secs_since_claim);
        $can_vote         = false;
    } else {
        // Não coletou ainda — checa API individual
        if (!empty($top['top_btn'])) {
            $api = loadTopApi($top);
            if ($api) {
                $apiResult = $api->checkVote($ip, $login);
                if (!$apiResult->error && $apiResult->voted) {
                    $voteTime     = $apiResult->voteTime > 0 ? $apiResult->voteTime : time();
                    $secs_ago_api = time() - $voteTime;
                    if ($secs_ago_api < 43200) {
                        $cooldown_left = 43200 - $secs_ago_api;
                        $can_vote      = false;
                    }
                }
            }
        }
    }

    $top['can_vote']      = $can_vote;
    $top['cooldown_left'] = $cooldown_left;
    $tops_status[]        = $top;
}

renderHead('Votar');
renderNav();
?>

<main class="main-content">

  <!-- Hero -->
  <div class="page-hero">
    <div class="eyebrow">⚜ Vote &amp; Ganhe</div>
    <h1>Painel de Votação</h1>
    <p>Vote nos tops para apoiar o servidor e ganhar recompensas exclusivas!</p>
    <div class="divider"><span>✦</span></div>
  </div>

  <!-- Stats row -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-value"><?= $totalVotes ?></div>
      <div class="stat-label">Votos Totais</div>
    </div>
    <div class="stat-card">
      <?php $available = 0; foreach ($tops_status as $t) { if ($t['can_vote']) $available++; } ?>
      <div class="stat-value"><?= $available ?></div>
      <div class="stat-label">Tops Disponíveis</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= count($rewards) ?></div>
      <div class="stat-label">Itens de Reward</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= count($tops) ?></div>
      <div class="stat-label">Tops Ativos</div>
    </div>
  </div>

  <?php if (!has4Top()): ?>
  <div class="alert alert-warning" style="text-align:center;padding:1.5rem">
    ⚠ <strong>Votação indisponível.</strong><br>
    <span style="font-size:.85rem;color:var(--text-secondary)">
      O administrador ainda não configurou o 4TOP, que é obrigatório para a votação funcionar.
    </span>
  </div>
  <?php elseif (empty($tops)): ?>
  <div class="alert alert-warning">
    ⚠ Nenhum TOP configurado ainda.
    <?php if (isAdmin()): ?>
      <a href="admin.php" style="color:var(--gold)">Configure no painel de admin →</a>
    <?php endif; ?>
  </div>
  <?php else: ?>

  <!-- Tops grid -->
  <div style="margin-bottom:2rem">
    <div class="section-heading">🗳 Sites de Votação</div>
    <div class="tops-grid" id="topsGrid">

<?php
foreach ($tops_status as $idx => $top):
    $voted_class   = !$top['can_vote'] ? ' voted' : '';
    $remaining_fmt = $top['can_vote'] ? '' : formatCooldown($top['cooldown_left']);

    $btn_file = !empty($top['top_btn']) ? basename($top['top_btn'], '.php') : '';
    $base     = 'assets/buttons/' . $btn_file;

    $img_path = null;
    foreach (array('png','jpg','jpeg','gif') as $ext) {
        if (file_exists($base . '.' . $ext)) { $img_path = $base . '.' . $ext; break; }
    }
    if ($img_path === null) { $img_path = 'assets/buttons/default.png'; }

    $vote_url = e(getTopVoteUrl($top, $login));
?>
      <div class="top-card<?= $voted_class ?>" id="topCard_<?= $top['id'] ?>">

        <div style="text-align:center;margin-bottom:.6rem">
          <div class="top-name"><?= e($top['name']) ?></div>
          <div class="top-status <?= $top['can_vote'] ? 'ok' : 'pending' ?>" style="justify-content:center">
            <?= $top['can_vote'] ? '● Disponível' : '⏳ Em cooldown' ?>
          </div>
        </div>

        <?php if (!$top['can_vote']): ?>
        <div class="top-cooldown" id="timer_<?= $top['id'] ?>" style="text-align:center">
          ⏱ Próximo voto em: <strong class="countdown" data-secs="<?= $top['cooldown_left'] ?>"><?= $remaining_fmt ?></strong>
        </div>
        <?php endif; ?>

        <div style="display:flex;justify-content:center;margin-top:.75rem">
          <?php if ($top['can_vote']): ?>
          <a href="<?= $vote_url ?>" target="_blank" rel="noopener"
            class="vote-img-btn"
            title="Votar em <?= e($top['name']) ?>">
            <img src="<?= e($img_path) ?>" alt="<?= e($top['name']) ?>"
              onerror="this.style.display='none';this.nextElementSibling.style.display='inline'">
            <span style="display:none">⚔ Votar</span>
          </a>
          <?php else: ?>
          <div class="vote-img-btn voted-overlay" title="Já votado">
            <img src="<?= e($img_path) ?>" alt="<?= e($top['name']) ?>"
              style="opacity:.35;filter:grayscale(1)"
              onerror="this.style.display='none';this.nextElementSibling.style.display='inline'">
            <span style="display:none;opacity:.4">✓ Votado</span>
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
              font-size:.85rem;color:var(--gold);font-weight:600;text-shadow:0 0 6px rgba(0,0,0,.8)">
              ✓
            </div>
          </div>
          <?php endif; ?>
        </div>

      </div>
      <?php endforeach; ?>

    </div>
  </div>

  <!-- Seção de recompensa — duas etapas -->
  <div id="claimSection" style="text-align:center;margin:1.5rem 0;">
    <div id="claimBox" style="background:linear-gradient(135deg,rgba(201,168,76,.15),rgba(201,168,76,.05));border:1px solid rgba(201,168,76,.4);border-radius:10px;padding:1.5rem 2rem;display:inline-block;max-width:480px;width:100%">
      <div style="font-size:2rem;margin-bottom:.5rem">🎁</div>
      <div style="font-size:1.1rem;font-weight:700;color:var(--gold);margin-bottom:.4rem">Recompensa Diária</div>
      <div style="font-size:.82rem;color:var(--text-dim);margin-bottom:1rem">Vote em todos os tops e clique abaixo para verificar.</div>

      <!-- Etapa 1: Verificar Votos -->
      <div id="stepCheck">
        <button id="checkBtn" onclick="doCheckVotes(this)"
          style="background:linear-gradient(135deg,#c9a84c,#a07830);color:#0a0a0f;font-weight:700;font-size:1rem;
                 padding:.75rem 2.5rem;border:none;border-radius:6px;cursor:pointer;letter-spacing:.05em;
                 box-shadow:0 4px 20px rgba(201,168,76,.3);transition:all .2s">
          ⚔ Verificar Votos
        </button>
      </div>

      <!-- Etapa 2: Escolher personagem + Receber (oculto até verificação passar) -->
      <div id="stepClaim" style="display:none;margin-top:1rem">
        <div style="font-size:.8rem;color:var(--text-dim);margin-bottom:.5rem">Escolha o personagem que vai receber:</div>
        <select id="charSelect"
          style="width:100%;padding:.6rem .75rem;border-radius:6px;border:1px solid rgba(201,168,76,.4);
                 background:rgba(0,0,0,.3);color:var(--text-primary);font-size:.9rem;margin-bottom:.75rem;cursor:pointer">
        </select>
        <button id="claimBtn" onclick="doClaimReward(this)"
          style="background:linear-gradient(135deg,#4ade80,#16a34a);color:#0a0a0f;font-weight:700;font-size:1rem;
                 padding:.75rem 2.5rem;border:none;border-radius:6px;cursor:pointer;letter-spacing:.05em;
                 box-shadow:0 4px 20px rgba(74,222,128,.25);transition:all .2s">
          🎁 Receber Recompensa
        </button>
      </div>
    </div>
  </div>

  <?php endif; ?>

  <!-- How it works + Rewards side by side -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.25rem;margin-top:1.5rem;">

    <!-- Rewards -->
    <div class="card">
      <div class="card-title">🎁 Recompensas por Voto</div>
      <?php if (empty($rewards)): ?>
      <p style="font-size:.85rem;color:var(--text-dim)">Nenhum reward configurado.</p>
      <?php else: ?>
      <div class="rewards-list">
        <?php foreach ($rewards as $r): ?>
        <div class="reward-badge">
          <span>💎</span>
          <span>ID: <?= (int)$r['item_id'] ?></span>
          <span style="color:var(--text-dim)">×</span>
          <span><?= (int)$r['quantity'] ?></span>
          <?php if (!empty($r['description'])): ?>
          <span style="font-size:.7rem;color:var(--text-dim)">(<?= e($r['description']) ?>)</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <p style="font-size:.75rem;color:var(--text-dim);margin-top:.75rem;line-height:1.5">
        Os itens serão entregues ao seu personagem automaticamente após o voto ser confirmado.
      </p>
      <?php endif; ?>
    </div>

    <!-- Instructions -->
    <div class="card">
      <div class="card-title">📖 Como Votar</div>
      <ol style="list-style:none;display:flex;flex-direction:column;gap:.75rem;counter-reset:steps">
        <?php
        $steps = array(
            array('🌐', 'Clique na imagem do top para abrir o site de votação'),
            array('🗳', 'Vote de verdade no site que abrir na nova aba'),
            array('⏳', 'Repita para todos os tops disponíveis'),
            array('↩', 'Volte para esta página — o sistema detecta seu voto automaticamente'),
            array('🎁', 'Clique em <strong style="color:var(--gold)">Entregar Recompensa</strong> para receber os itens'),
            array('⏱', 'Você poderá votar novamente após <strong style="color:var(--gold)">12 horas</strong>'),
        );
        foreach ($steps as $i => $s): ?>
        <li style="display:flex;gap:.75rem;align-items:flex-start;font-size:.82rem;color:var(--text-secondary)">
          <span style="font-size:1.1rem;flex-shrink:0"><?= $s[0] ?></span>
          <span style="line-height:1.5"><?= $s[1] ?></span>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>

  </div>

  <!-- Toast — ANTES do renderFooter para ficar dentro do body -->
  <div id="toastEl" style="
    position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
    max-width:320px;padding:1rem 1.25rem;border-radius:6px;
    font-size:.85rem;font-family:'Raleway',sans-serif;
    transform:translateY(100px);opacity:0;
    transition:all .35s cubic-bezier(.34,1.56,.64,1);
    display:flex;align-items:flex-start;gap:.6rem;pointer-events:none;
  "></div>

<script>
// ── Toast ────────────────────────────────────────────────────────────────────
function showToast(msg, type) {
    var el = document.getElementById('toastEl');
    if (!el) return;
    var colors  = { ok:'rgba(26,100,50,.9)', error:'rgba(100,20,20,.9)', cooldown:'rgba(120,90,20,.9)', info:'rgba(20,50,100,.9)' };
    var borders = { ok:'1px solid rgba(45,138,90,.6)', error:'1px solid rgba(139,26,26,.6)', cooldown:'1px solid rgba(201,168,76,.4)', info:'1px solid rgba(60,100,180,.5)' };
    var icons   = { ok:'✓', error:'✗', cooldown:'⏳', info:'ℹ' };

    el.style.background     = colors[type]  || colors.info;
    el.style.border         = borders[type] || borders.info;
    el.style.color          = '#f0e8d8';
    el.style.backdropFilter = 'blur(10px)';
    el.innerHTML = '<span style="font-size:1rem;flex-shrink:0">' + (icons[type]||'ℹ') + '</span><span>' + msg + '</span>';

    setTimeout(function() { el.style.transform = 'translateY(0)';     el.style.opacity = '1'; }, 10);
    setTimeout(function() { el.style.transform = 'translateY(100px)'; el.style.opacity = '0'; }, 3500);
}

// ── Etapa 1: Verificar Votos ─────────────────────────────────────────────────
function doCheckVotes(btn) {
    btn.disabled = true; btn.style.opacity = '.6'; btn.textContent = '⏳ Verificando votos...';

    var fd = new FormData();
    fd.append('action', 'check_votes');

    ajax('vote.php', fd, function(res) {
        if (res.status === 'ok') {
            // Preenche dropdown com os personagens
            var sel = document.getElementById('charSelect');
            sel.innerHTML = '';
            for (var i = 0; i < res.chars.length; i++) {
                var opt = document.createElement('option');
                opt.value = res.chars[i].obj_Id;
                opt.textContent = res.chars[i].char_name;
                sel.appendChild(opt);
            }
            // Mostra etapa 2, esconde etapa 1
            document.getElementById('stepCheck').style.display = 'none';
            document.getElementById('stepClaim').style.display = 'block';
            showToast(res.msg, 'ok');
        } else if (res.status === 'cooldown') {
            showToast(res.msg, 'cooldown');
            btn.disabled = false; btn.style.opacity = '1'; btn.textContent = '⚔ Verificar Votos';
        } else {
            showToast(res.msg || 'Erro desconhecido.', res.status === 'not_voted' ? 'info' : 'error');
            btn.disabled = false; btn.style.opacity = '1'; btn.textContent = '⚔ Verificar Votos';
        }
    }, function() {
        showToast('Erro ao conectar. Tente novamente.', 'error');
        btn.disabled = false; btn.style.opacity = '1'; btn.textContent = '⚔ Verificar Votos';
    });
}

// ── Etapa 2: Entregar Recompensa ──────────────────────────────────────────────
function doClaimReward(btn) {
    var objId = document.getElementById('charSelect').value;
    if (!objId) { showToast('Selecione um personagem.', 'error'); return; }

    btn.disabled = true; btn.style.opacity = '.6'; btn.textContent = '⏳ Entregando...';

    var fd = new FormData();
    fd.append('action', 'claim_reward');
    fd.append('obj_id', objId);

    ajax('vote.php', fd, function(res) {
        if (res.status === 'ok') {
            showToast(res.msg, 'ok');
            document.getElementById('claimSection').innerHTML =
                '<div style="background:linear-gradient(135deg,rgba(26,100,50,.2),rgba(26,100,50,.05));' +
                'border:1px solid rgba(45,138,90,.4);border-radius:10px;padding:1.5rem 2rem;' +
                'display:inline-block;max-width:480px;width:100%;text-align:center">' +
                '<div style="font-size:2rem;margin-bottom:.5rem">✅</div>' +
                '<div style="font-size:1rem;font-weight:700;color:#4ade80;margin-bottom:.3rem">Recompensa entregue!</div>' +
                '<div style="font-size:.8rem;color:var(--text-dim)">Volte em 12h para votar novamente.</div></div>';
        } else if (res.status === 'cooldown') {
            showToast(res.msg, 'cooldown');
            btn.disabled = false; btn.style.opacity = '1'; btn.textContent = '🎁 Receber Recompensa';
        } else {
            showToast(res.msg || 'Erro desconhecido.', 'error');
            btn.disabled = false; btn.style.opacity = '1'; btn.textContent = '🎁 Receber Recompensa';
        }
    }, function() {
        showToast('Erro ao conectar. Tente novamente.', 'error');
        btn.disabled = false; btn.style.opacity = '1'; btn.textContent = '🎁 Receber Recompensa';
    });
}

// ── Helper AJAX ───────────────────────────────────────────────────────────────
function ajax(url, formData, onSuccess, onError) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        try { var res = JSON.parse(xhr.responseText); onSuccess(res); }
        catch(e) { onError && onError(); }
    };
    xhr.send(formData);
}

// ── Countdown timers ──────────────────────────────────────────────────────────
function startCountdown(el) {
    if (!el) return;
    var secs = parseInt(el.getAttribute('data-secs'), 10);
    var interval = setInterval(function() {
        secs--;
        el.setAttribute('data-secs', secs);
        el.textContent = formatTime(secs);
        if (secs <= 0) { clearInterval(interval); el.textContent = '00:00:00'; }
    }, 1000);
}
function formatTime(secs) {
    if (secs <= 0) return '00:00:00';
    var h = Math.floor(secs / 3600), m = Math.floor((secs % 3600) / 60), s = secs % 60;
    return pad(h) + ':' + pad(m) + ':' + pad(s);
}
function pad(n) { return n < 10 ? '0' + n : '' + n; }

document.addEventListener('DOMContentLoaded', function() {
    var countdowns = document.querySelectorAll('.countdown[data-secs]');
    for (var i = 0; i < countdowns.length; i++) { startCountdown(countdowns[i]); }
});
</script>

</main>

<?php renderFooter(); ?>