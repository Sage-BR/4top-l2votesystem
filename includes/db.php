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
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            )
        );
    } catch (PDOException $e) {
        error_log('[VoteSystem] DB connection failed: ' . $e->getMessage());
        http_response_code(503);
        // ── DEV: mostra o detalhe do erro ─────────────────────────────────────
        // Remova o "detail" antes de ir para produção
        echo json_encode(array(
            'error'   => true,
            'message' => 'Service temporarily unavailable.',
        ));
        exit;
    }
    return $pdo;
}