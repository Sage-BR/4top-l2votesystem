<?php
/**
 * Database singleton — PDO
 * Compatible: PHP 5.4 ~ 8.2
 */
function getDB() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'",
            )
        );
    } catch (PDOException $e) {
        error_log('[VoteSystem] DB connection failed: ' . $e->getMessage());
        http_response_code(503);

        // Se for requisição AJAX ou API → JSON
        $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
               || (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'voteapi.php');

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('error' => true, 'message' => 'Service temporarily unavailable.'));
            exit;
        }

        // Requisição de navegador → HTML temático
        $errMsg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1.0">'
           . '<title>Indisponível — VoteSystem</title>'
           . '<link rel="stylesheet" href="assets/css/main.css">'
           . '</head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem">'
           . '<div class="card" style="max-width:480px;text-align:center">'
           . '<div style="font-size:2.5rem;margin-bottom:.75rem">&#9888;</div>'
           . '<h1 style="font-family:\'Cinzel Decorative\',serif;color:var(--crimson-light);margin-bottom:1rem">Banco de Dados Indisponível</h1>'
           . '<p style="color:var(--text-secondary);font-size:.85rem;line-height:1.7;margin-bottom:1.5rem">'
           . 'Não foi possível conectar ao banco de dados do servidor. '
           . 'Verifique se o MySQL está rodando e se as credenciais em <code>config.php</code> estão corretas.</p>'
           . '<div class="alert alert-error" style="font-size:.78rem;text-align:left;word-break:break-all">'
           . htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') . '</div>'
           . '<a href="javascript:location.reload()" class="btn btn-primary" style="margin-top:1rem">&#8635; Tentar novamente</a>'
           . '</div></body></html>';
        exit;
    }
    return $pdo;
}