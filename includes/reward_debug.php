<?php
// Debug da entrega: somente etapas e metadados técnicos, sem dados do jogador.
function rewardDebug($stage, $error = null) {
    static $requestId = null;
    if ($requestId === null) $requestId = bin2hex(random_bytes(8));
    $entry = array('utc' => gmdate('Y-m-d H:i:s'), 'request' => $requestId, 'stage' => $stage);
    if ($error !== null) {
        $entry['type'] = get_class($error);
        $entry['code'] = (string)$error->getCode();
        $entry['file'] = basename($error->getFile());
        $entry['line'] = $error->getLine();
        if ($error instanceof PDOException && !empty($error->errorInfo)) {
            $entry['sqlstate'] = $error->errorInfo[0];
            $entry['driver_code'] = isset($error->errorInfo[1]) ? $error->errorInfo[1] : null;
        }
        // Não grava mensagens SQL livres: elas podem conter valores pessoais.
        if (preg_match('/^(Call to undefined (?:function|method) [a-zA-Z0-9_\\\\:]+\(\)|Class "[a-zA-Z0-9_\\\\]+" not found)/', $error->getMessage(), $match)) {
            $entry['detail'] = $match[1];
        }
    }
    $path = dirname(__DIR__) . '/reward_delivery.log';
    if (@file_put_contents($path, json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        error_log('[VoteSystem] Não foi possível escrever reward_delivery.log');
    }
}
