<?php
header('Content-Type: application/json');

require '../db_config.php';

try {
    $pdo->query('SELECT 1');

    echo json_encode([
        'status' => 'ok',
        'db' => 'connected',
        'time' => date('Y-m-d H:i:s')
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'db' => 'down'
    ]);
}
