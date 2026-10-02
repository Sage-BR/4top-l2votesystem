<?php
if (!file_exists(__DIR__ . '/.installed')) { http_response_code(404); exit; }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$csrf = isset($_GET['csrf']) && is_string($_GET['csrf']) ? $_GET['csrf'] : '';
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !verifyCsrf($csrf)) { http_response_code(403); exit; }
$id = filter_var($_GET['top_id'] ?? '', FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
if ($id === false) { http_response_code(400); exit; }
$stmt = getDB()->prepare("SELECT * FROM 4top_tops WHERE id = ? AND enabled = 1 AND top_btn = 'hopzoneu.php' LIMIT 1");
$stmt->execute(array($id));
$top = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$top) { http_response_code(404); exit; }
$login = currentLogin();
session_write_close();
try {
    $api = new HopzoneEuApi($top);
    $url = $api->prepareVote($login);
    header('Location: ' . $url, true, 302);
} catch (Throwable $e) {
    error_log('[Hopzone.eu] Falha ao preparar link de votação');
    http_response_code(502);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Não foi possível abrir a votação. Volte ao VoteSystem e tente novamente.';
}
