<?php
/** Instruções compartilhadas pelo cadastro e pela edição, sem tokens reais. */
function renderIntegrationHelp($top, $dialogId) {
    $button = $top['top_btn'];
    $catalog = getAvailableTops();
    $info = isset($catalog[$button]) ? $catalog[$button] : null;
    if (!$info) return;
    ?>
    <dialog id="integration-<?= e($dialogId) ?>" class="integration-dialog" aria-labelledby="integration-title-<?= e($dialogId) ?>">
      <div class="integration-dialog-head">
        <h2 id="integration-title-<?= e($dialogId) ?>">Como integrar <?= e($info['name']) ?></h2>
        <button type="button" class="integration-close" aria-label="Fechar instruções" onclick="this.closest('dialog').close()" autofocus>×</button>
      </div>
      <div class="integration-dialog-content">
        <p>Cadastre seu servidor no <a href="<?= e($info['register_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($info['name']) ?></a>. No VoteSystem, informe o nome e o identificador do servidor no top.</p>
        <?php if ($button === '4top.php'): ?>
        <p>O 4TOP é obrigatório e aparece primeiro. Informe o identificador usado pelo seu servidor no ranking e a API Key do painel do 4TOP no campo Token / API Key.</p>
        <p>O link de votação e a consulta enviam a identificação da conta. A API deve confirmar um voto recente; abrir o link não comprova o voto. Não precisa configurar postback.</p>
        <?php elseif ($button === 'l2jbrasil.php'): ?>
        <p>Informe o username do servidor no ranking (parâmetro <code>u</code>), não o login do jogador. O link envia <code>player_id</code> gerado pelo MD5 do login; a checagem usa o mesmo identificador.</p>
        <p>Conclua o voto e o captcha no top. A confirmação exige status válido e voto nas últimas 12 horas. O código possui consulta secundária por IP quando o player_id não confirma.</p>
        <p>O campo Token não participa da consulta atual do L2JBrasil. Não precisa configurar postback.</p>
        <?php elseif ($button === 'l2toporg.php'): ?>
        <p>Informe o ID do servidor e o token da API do painel do L2Top.org. O link inclui o login; a API consulta esse mesmo login usando o token.</p>
        <p>É necessário um voto confirmado com horário dentro da janela de 12 horas. Não precisa configurar postback.</p>
        <?php elseif ($button === 'l2network.php'): ?>
        <p>Informe o username do servidor no ranking e sua API Key. O link envia o login em <code>id</code>; a consulta POST envia <code>apiKey</code>, <code>type=2</code> e <code>player=login</code>.</p>
        <p>A resposta é um inteiro: <code>-1</code> não confirma o voto, <code>0</code> não comprova voto recente e um timestamp positivo precisa estar dentro das últimas 12 horas. Não precisa configurar postback.</p>
        <?php elseif ($button === 'hopzoneu.php'): ?>
        <p>Informe o ID numérico do servidor e sua API Key. O VoteSystem gera pela API uma URL com <code>vote_id</code> vinculado ao login; use sempre o botão do painel para votar.</p>
        <p>A checagem exige <code>status=completed</code>, servidor e vote_id correspondentes, com voto recente. Um voto pendente não libera recompensa. O IP só é consultado quando não há vínculo salvo para a conta. Não precisa configurar postback.</p>
        <?php elseif (postbackTopSupported($button)): ?>
        <p>Informe o ID numérico do servidor. Copie o endereço abaixo e cole no campo <strong>Postback URL</strong> do painel do top. Clique no campo para selecionar o endereço completo:</p>
        <input type="text" class="form-control top-postback-url" readonly data-postback-suffix="<?= $button === 'top100arena.php' ? '?postback=' : '' ?>" aria-label="URL de postback" onclick="this.select()">
        <?php if ($button === 'mmtop200.php'): ?>
        <p>O campo Token recebe a API Key da página <strong>Vote Checker</strong> do MMTop200. Exemplo fictício (não válido): <code>0123456789abcdef0123456789abcdef</code>.</p>
        <p>Use o validador oficial sem senha adicional de postback: a integração valida a origem. O link envia o login da conta e o callback deve informar o usuário e o voto contabilizado.</p>
        <?php elseif ($button === 'gamingtop100.php'): ?>
        <p>Configure o callback na <a href="https://www.gamingtop100.net/edit" target="_blank" rel="noopener noreferrer">página de edição do GamingTop100</a>. Não precisa de token neste adaptador.</p>
        <p>O link envia uma referência numérica vinculada ao login; o callback devolve essa referência para identificar a conta.</p>
        <?php else: ?>
        <p>Mantenha <code>?postback=</code> no final do callback: o top acrescenta a referência do incentivo vinculada ao login. Não precisa de token neste adaptador.</p>
        <?php endif; ?>
        <p>Após salvar, conclua um voto permitido pelo botão do VoteSystem. A confirmação exige postback autenticado da conta; IP sozinho não libera recompensa. Se permanecer pendente, procure “POSTBACK RECEBIDO” e eventuais rejeições em <code>vote_api.log</code>.</p>
        <?php endif; ?>
        <p>URLs são geradas automaticamente. Conclua os votos em todos os tops ativos e volte ao VoteSystem para verificar e coletar. Cliques e falhas de consulta não liberam recompensa.</p>
      </div>
    </dialog>
    <?php
}
