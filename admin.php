<?php
/**
 * VoteSystem 4Top Servers — Admin Panel
 * Compatible: PHP 5.4 ~ 8.2
 */

if (!file_exists(__DIR__ . '/.installed')) { header('Location: install.php'); exit; }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/bootstrap.php';

requireAdmin();

$db      = getDB();
$success = '';
$error   = '';

// ── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_top') {
        $name    = trim($_POST['top_name'] ?? '');
        $top_id  = trim($_POST['top_id'] ?? '');
        $token   = trim($_POST['top_token'] ?? '');
        $top_btn = basename(trim($_POST['top_btn'] ?? ''));

        $url_templates = array(
            'l2jbrasil.php'   => 'https://top.l2jbrasil.com/index.php?a=in&s={SERVER_ID}',
            '4top.php'        => 'https://top.4teambr.com/index.php?a=in&s={SERVER_ID}',
            'hopzone.php'     => 'https://hopzone.net/lineage2/vote/{SERVER_ID}',
            'hopzoneu.php'    => 'https://hopzone.eu/server/{SERVER_ID}',
            'itopz.php'       => 'https://itopz.com/vote/{SERVER_ID}',
            'l2toporg.php'    => 'https://l2top.org/server/{SERVER_ID}/',
            'hotservers.php'  => 'https://www.hotservers.org/servers/{SERVER_ID}/vote',
            'l2rankzone.php'  => 'https://l2rankzone.com/lineage2-servers/{SERVER_ID}/vote',
            'l2votes.php'     => 'https://www.l2votes.com/server/{SERVER_ID}/',
        );

        if (empty($name) || empty($top_id) || empty($top_btn)) {
            $error = 'Nome, ID do Servidor e Top são obrigatórios.';
        } elseif ($top_btn !== '4top.php' && !has4Top()) {
            $error = '⚠ O 4TOP precisa ser adicionado primeiro antes de qualquer outro top.';
        } else {
            $url = isset($url_templates[$top_btn])
                ? str_replace('{SERVER_ID}', rawurlencode($top_id), $url_templates[$top_btn])
                : '';

            if ($top_btn === '4top.php') {
                $sort_order = 0;
                $db->exec("UPDATE 4top_tops SET sort_order = sort_order + 1 WHERE top_btn <> '4top.php'");
            } else {
                $row = $db->query("SELECT COALESCE(MAX(sort_order), 0) FROM 4top_tops")->fetchColumn();
                $sort_order = (int)$row + 1;
            }

            $stmt = $db->prepare(
                "INSERT INTO 4top_tops (name, top_id, token, url, api_url, top_btn, enabled, sort_order)
                 VALUES (?, ?, ?, ?, NULL, ?, 1, ?)"
            );
            $stmt->execute(array($name, $top_id, $token ?: null, $url ?: null, $top_btn, $sort_order));
            $success = 'Top "' . htmlspecialchars($name) . '" adicionado com sucesso!';
        }
    }

    elseif ($action === 'remove_top') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM 4top_tops WHERE id = ?");
            $stmt->execute(array($id));
            $success = 'Top removido.';
        }
    }

    elseif ($action === 'toggle_top') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $chk = $db->prepare("SELECT top_btn FROM 4top_tops WHERE id = ? LIMIT 1");
            $chk->execute(array($id));
            $row = $chk->fetch();
            if ($row && ($row['top_btn'] ?? '') === '4top.php') {
                $error = 'O 4TOP não pode ser desativado.';
            } else {
                $stmt = $db->prepare("UPDATE 4top_tops SET enabled = 1 - enabled WHERE id = ?");
                $stmt->execute(array($id));
                $success = 'Status do top atualizado.';
            }
        }
    }

    elseif ($action === 'add_reward') {
        $item_ids   = $_POST['item_id']     ?? array();
        $quantities = $_POST['quantity']    ?? array();
        $descs      = $_POST['description'] ?? array();

        if (!is_array($item_ids)) $item_ids = array($item_ids);

        $added = 0;
        $stmt  = $db->prepare(
            "INSERT INTO 4top_rewards (item_id, quantity, description) VALUES (?, ?, ?)"
        );
        for ($i = 0; $i < count($item_ids); $i++) {
            $iid = (int)($item_ids[$i] ?? 0);
            $qty = max(1, (int)($quantities[$i] ?? 1));
            $dsc = trim($descs[$i] ?? '');
            if ($iid > 0) {
                $stmt->execute(array($iid, $qty, $dsc ?: null));
                $added++;
            }
        }
        if ($added > 0) $success = $added . ' item(s) de reward adicionado(s)!';
        else $error = 'Preencha pelo menos um Item ID válido.';
    }

    elseif ($action === 'remove_reward') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM 4top_rewards WHERE id = ?");
            $stmt->execute(array($id));
            $success = 'Reward removido.';
        }
    }

    elseif ($action === 'clear_rewards') {
        $db->exec("DELETE FROM 4top_rewards");
        $success = 'Todos os rewards foram removidos.';
    }
}

$tops    = getAllTops();
$rewards = getRewards();
$has4top = has4Top();

$stmt = $db->query("SELECT COUNT(*) FROM 4top_log");
$total_votes = (int)$stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM 4top_log WHERE voted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$votes_today = (int)$stmt->fetchColumn();

$recent_log = getVoteLog(15);

renderHead('Admin');
renderNav();
?>

<main class="main-content">

  <div class="page-hero">
    <div class="eyebrow" data-i18n="admin_eyebrow">⚙ Painel Administrativo</div>
    <h1 data-i18n="admin_hero_title">Gerenciar VoteSystem</h1>
    <p data-i18n="admin_hero_subtitle">Configure tops de votação, rewards e monitore a atividade dos jogadores.</p>
    <div class="divider"><span>✦</span></div>
  </div>

  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-value"><?= $total_votes ?></div>
      <div class="stat-label" data-i18n="admin_stat_total">Votos Total</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= $votes_today ?></div>
      <div class="stat-label" data-i18n="admin_stat_today">Votos Hoje</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= count($tops) ?></div>
      <div class="stat-label" data-i18n="admin_stat_tops">Tops Cadastrados</div>
    </div>
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success">✓ <?= e($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-error">✗ <?= e($error) ?></div>
  <?php endif; ?>

  <div class="admin-grid">

    <!-- ── Add Top ── -->
    <div class="card">
      <div class="card-title" data-i18n="admin_add_top_title">➕ Adicionar Site de TOP</div>

      <?php $availableTops = getAvailableTops(); ?>

      <?php if (!$has4top): ?>
      <div class="alert alert-warning" style="font-size:.82rem;margin-bottom:1rem;line-height:1.5">
        <span data-i18n-html="admin_4top_req_warn">⚠ <strong>O 4TOP é obrigatório.</strong> Adicione o 4TOP antes de qualquer outro site de votação.</span>
      </div>
      <?php endif; ?>

      <form method="POST" action="admin.php" id="addTopForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="add_top">

        <div class="form-group">
          <label class="form-label" data-i18n="admin_top_sel_label">Site de Votação</label>
          <?php if (empty($availableTops)): ?>
          <div class="alert alert-warning" style="font-size:.8rem">
            ⚠ Nenhum arquivo encontrado em <code>tops/</code>.
          </div>
          <?php else: ?>
          <?php
            $addedBtns = array();
            foreach ($tops as $t) {
                if (!empty($t['top_btn'])) $addedBtns[] = $t['top_btn'];
            }
            $showOnly4top = !$has4top;
          ?>
          <select name="top_btn" id="topBtnSelect" class="form-control" required
            onchange="onTopChange(this)">
            <option value="" data-i18n="admin_top_sel_ph">— Selecione o site —</option>
            <?php foreach ($availableTops as $file => $info):
              if ($showOnly4top && $file !== '4top.php') continue;
              if (in_array($file, $addedBtns)) continue;
            ?>
            <option value="<?= e($file) ?>"
              data-token="<?= $info['token'] ? '1' : '0' ?>"
              data-site="<?= e($info['site']) ?>"
              data-name="<?= e($info['name']) ?>">
              <?= e($info['name']) ?>
              <?php if ($info['site']): ?> — <?= e($info['site']) ?><?php endif; ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" data-i18n="admin_top_name_label">Nome do Top</label>
          <input type="text" name="top_name" id="topNameInput" class="form-control"
            data-i18n-placeholder="admin_top_name_ph"
            placeholder="ex: L2JBrasil" required>
        </div>

        <div class="form-group">
          <label class="form-label" data-i18n="admin_top_id_label">ID do Servidor no Top</label>
          <input type="text" name="top_id" id="topIdInput" class="form-control"
            data-i18n-placeholder="admin_top_id_ph"
            placeholder="ex: 12345 (veja no painel do site de votação)" required>
          <div id="topSiteHint" style="font-size:.7rem;color:var(--text-dim);margin-top:.3rem;display:none">
            <span data-i18n="admin_site_hint">ℹ Encontre seu ID em:</span> <a id="topSiteHintUrl" href="#" target="_blank" rel="noopener" style="color:var(--gold-dim)"></a>
          </div>
        </div>

        <div class="form-group" id="tokenGroup" style="display:none">
          <label class="form-label">
            <span data-i18n="admin_token_label">Token / API Key</span>
            <span style="color:var(--text-dim);font-size:.7em" data-i18n="admin_token_req">(obrigatório para este top)</span>
          </label>
          <input type="text" name="top_token" id="topTokenInput" class="form-control"
            data-i18n-placeholder="admin_token_ph"
            placeholder="Cole o token gerado no painel do site de votação">
        </div>

        <div class="alert alert-info" style="font-size:.75rem;margin-bottom:1rem" data-i18n="admin_url_auto_info">
          ℹ As URLs de votação são geradas automaticamente. A ordem é definida automaticamente (4TOP sempre em 1º).
        </div>

        <button type="submit" class="btn btn-primary btn-full" data-i18n="admin_btn_add_top">✓ Adicionar Top</button>
      </form>
    </div>

    <!-- ── Tops List ── -->
    <div class="card">
      <div class="card-title">🏆 <span data-i18n="admin_tops_list_title">Tops Cadastrados</span> (<?= count($tops) ?>)</div>

      <?php if (empty($tops)): ?>
      <p style="font-size:.85rem;color:var(--text-dim)" data-i18n="admin_no_tops">Nenhum top cadastrado ainda.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th data-i18n="col_name">Nome</th>
              <th data-i18n="col_id">ID</th>
              <th data-i18n="col_status">Status</th>
              <th style="text-align:right" data-i18n="col_actions">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tops as $top): ?>
            <tr>
              <td style="color:var(--text-primary)">
                <div style="font-weight:500"><?= e($top['name']) ?></div>
                <?php if (!empty($top['top_btn'])): ?>
                <div style="font-size:.7rem;color:var(--gold-dim);margin-top:.15rem">
                  📂 <?= e($top['top_btn']) ?>
                </div>
                <?php elseif (!empty($top['url'])): ?>
                <div style="font-size:.7rem;color:var(--text-dim);margin-top:.15rem"><?= e(substr($top['url'], 0, 40)) ?>…</div>
                <?php endif; ?>
              </td>
              <td><code style="font-size:.78rem;color:var(--gold-dim)"><?= e($top['top_id']) ?></code></td>
              <td>
                <span class="badge <?= $top['enabled'] ? 'badge-success' : 'badge-danger' ?>"
                  data-i18n="<?= $top['enabled'] ? 'badge_active' : 'badge_inactive' ?>">
                  <?= $top['enabled'] ? 'Ativo' : 'Inativo' ?>
                </span>
              </td>
              <td style="text-align:right">
                <div style="display:flex;gap:.3rem;justify-content:flex-end">
                  <?php if (($top['top_btn'] ?? '') === '4top.php'): ?>
                  <button class="btn btn-ghost btn-sm" disabled
                    data-i18n-title="title_4top_no_disable"
                    title="O 4TOP não pode ser desativado"
                    style="opacity:.35;cursor:not-allowed">⏸</button>
                  <?php else: ?>
                  <form method="POST" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="toggle_top">
                    <input type="hidden" name="id" value="<?= (int)$top['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"
                      data-i18n-title="<?= $top['enabled'] ? 'title_disable' : 'title_enable' ?>"
                      title="<?= $top['enabled'] ? 'Desativar' : 'Ativar' ?>">
                      <?= $top['enabled'] ? '⏸' : '▶' ?>
                    </button>
                  </form>
                  <?php endif; ?>
                  <form method="POST" style="display:inline"
                    onsubmit="return confirm(window.vsI18n ? window.vsI18n.t('confirm_remove_top') : 'Remover este top?')">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="remove_top">
                    <input type="hidden" name="id" value="<?= (int)$top['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit" title="Remover">🗑</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Add Rewards ── -->
    <div class="card">
      <div class="card-title" data-i18n="admin_reward_cfg_title">🎁 Configurar Rewards por Voto</div>

      <p style="font-size:.82rem;color:var(--text-secondary);margin-bottom:1rem;line-height:1.5" data-i18n="admin_reward_cfg_desc">
        Adicione os itens que serão entregues ao jogador a cada voto.
        Você pode adicionar múltiplos itens de uma vez.
      </p>

      <form method="POST" action="admin.php" id="rewardForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="add_reward">

        <div id="rewards-container">
          <div class="reward-row">
            <div>
              <label class="form-label" data-i18n="admin_reward_item_id">Item ID</label>
              <input type="number" name="item_id[]" class="form-control" placeholder="ex: 57" min="1" required>
            </div>
            <div>
              <label class="form-label" data-i18n="admin_reward_qty">Quantidade</label>
              <input type="number" name="quantity[]" class="form-control" placeholder="1" min="1" value="1" required>
            </div>
            <div style="display:flex;align-items:flex-end">
              <button type="button" class="btn-icon" onclick="addRewardRow()"
                data-i18n-title="admin_btn_add_more" title="Adicionar mais">＋</button>
            </div>
          </div>
        </div>

        <div class="form-group" style="margin-top:.75rem">
          <label class="form-label" data-i18n="admin_reward_name_lbl">Nome (Ex: "Adena")</label>
          <input type="text" name="description[]" id="firstDesc" class="form-control"
            data-i18n-placeholder="admin_reward_name_ph"
            placeholder="Nome do item para exibição">
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:1rem" data-i18n="admin_btn_save_rewards">
          ✓ Salvar Rewards
        </button>
      </form>
    </div>

    <!-- ── Rewards List ── -->
    <div class="card">
      <div class="card-title">📦 <span data-i18n="admin_rewards_list_title">Rewards Configurados</span> (<?= count($rewards) ?>)</div>

      <?php if (empty($rewards)): ?>
      <p style="font-size:.85rem;color:var(--text-dim)" data-i18n="admin_no_rewards">Nenhum reward configurado.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th data-i18n="admin_reward_item_id">Item ID</th>
              <th data-i18n="col_qty">Qtd</th>
              <th data-i18n="col_item_name">Nome</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rewards as $r): ?>
            <tr>
              <td><code style="color:var(--gold)"><?= (int)$r['item_id'] ?></code></td>
              <td style="color:var(--text-primary)">×<?= (int)$r['quantity'] ?></td>
              <td style="color:var(--text-dim)"><?= $r['description'] ? e($r['description']) : '—' ?></td>
              <td>
                <form method="POST"
                  onsubmit="return confirm(window.vsI18n ? window.vsI18n.t('confirm_remove_reward') : 'Remover este reward?')">
                  <input type="hidden" name="action" value="remove_reward">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit">🗑</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
<form method="POST"
                    onsubmit="return confirm(window.vsI18n ? window.vsI18n.t('confirm_clear_rewards') : 'Remover TODOS os rewards?')"
                    style="margin-top:1rem">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="clear_rewards">
        <button type="submit" class="btn btn-danger btn-sm" data-i18n="admin_btn_clear_rewards">🗑 Limpar Todos</button>
      </form>
      <?php endif; ?>
    </div>

  </div><!-- .admin-grid -->

  <!-- Vote Log -->
  <div class="card" style="margin-top:1.5rem">
    <div class="flex-between" style="margin-bottom:1rem">
      <div class="card-title" style="margin:0" data-i18n="admin_log_title">📊 Log de Votos Recentes</div>
      <span style="font-size:.75rem;color:var(--text-dim)" data-i18n="admin_log_subtitle">Últimas 15 sessões</span>
    </div>

    <?php if (empty($recent_log)): ?>
    <p style="font-size:.85rem;color:var(--text-dim)" data-i18n="admin_no_log">Nenhum voto registrado ainda.</p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th data-i18n="col_login">Login</th>
            <th data-i18n="col_tops_voted">Tops Votados</th>
            <th data-i18n="col_ip">IP</th>
            <th data-i18n="col_datetime">Data/Hora</th>
            <th data-i18n="col_reward">Reward</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent_log as $log): ?>
          <tr>
            <td style="color:var(--gold-light)"><?= e($log['login']) ?></td>
            <td>
              <?php foreach (explode(', ', $log['tops_voted'] ?? '') as $t): ?>
              <span style="display:inline-block;background:rgba(201,168,76,.15);border:1px solid rgba(201,168,76,.3);
                border-radius:4px;padding:1px 6px;font-size:.72rem;margin:1px 2px;color:var(--gold-light)">
                <?= e(trim($t)) ?>
              </span>
              <?php endforeach; ?>
              <span style="font-size:.7rem;color:var(--text-dim);margin-left:2px">(<?= (int)$log['total_tops'] ?>)</span>
            </td>
            <td><code style="font-size:.75rem;color:var(--text-dim)"><?= e($log['ip']) ?></code></td>
            <td style="font-size:.78rem;color:var(--text-secondary)"><?= e($log['voted_at']) ?></td>
            <td>
              <span class="badge <?= $log['rewarded'] ? 'badge-success' : 'badge-gold' ?>"
                data-i18n="<?= $log['rewarded'] ? 'badge_delivered' : 'badge_pending' ?>">
                <?= $log['rewarded'] ? 'Entregue' : 'Pendente' ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</main>

<?php renderFooter(); ?>

<script>
var rewardRowCount = 1;

function addRewardRow() {
    rewardRowCount++;
    var container = document.getElementById('rewards-container');
    var row = document.createElement('div');
    row.className = 'reward-row';
    var t = window.vsI18n ? window.vsI18n.t : function(k){ return k; };
    row.innerHTML =
        '<div>' +
            '<label class="form-label" data-i18n="admin_reward_item_id">' + t('admin_reward_item_id') + '</label>' +
            '<input type="number" name="item_id[]" class="form-control" placeholder="ex: 57" min="1" required>' +
        '</div>' +
        '<div>' +
            '<label class="form-label" data-i18n="admin_reward_qty">' + t('admin_reward_qty') + '</label>' +
            '<input type="number" name="quantity[]" class="form-control" placeholder="1" min="1" value="1" required>' +
        '</div>' +
        '<div style="display:flex;flex-direction:column;justify-content:flex-end;gap:.3rem">' +
            '<label class="form-label" style="visibility:hidden">.</label>' +
            '<button type="button" class="btn-icon" onclick="removeRewardRow(this)" title="' + t('admin_btn_remove_row') + '">✕</button>' +
        '</div>';
    container.appendChild(row);
}

function removeRewardRow(btn) {
    var row = btn.closest('.reward-row');
    if (row) row.remove();
}

var _topNames = [];

function onTopChange(sel) {
    var opt        = sel.options[sel.selectedIndex];
    var needsToken = opt.getAttribute('data-token') === '1';
    var site       = opt.getAttribute('data-site')  || '';
    var name       = opt.getAttribute('data-name')  || '';

    document.getElementById('tokenGroup').style.display = needsToken ? '' : 'none';
    if (!needsToken) document.getElementById('topTokenInput').value = '';

    var nameInput    = document.getElementById('topNameInput');
    var currentValue = nameInput.value.trim();
    var isAutoValue  = !currentValue || _topNames.indexOf(currentValue) !== -1;
    if (name && isAutoValue) nameInput.value = name;

    var hint    = document.getElementById('topSiteHint');
    var hintUrl = document.getElementById('topSiteHintUrl');
    if (site) {
        var fullUrl = site.startsWith('http') ? site : 'https://' + site;
        hintUrl.textContent = site;
        hintUrl.href        = fullUrl;
        hint.style.display  = '';
    } else {
        hint.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var opts = document.querySelectorAll('#topBtnSelect option[data-name]');
    for (var i = 0; i < opts.length; i++) {
        var n = opts[i].getAttribute('data-name');
        if (n) _topNames.push(n);
    }
});
</script>
</body>
</html>