<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$id = $_GET["id"] ?? null;

if (!$id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบ ID'
    ]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, tools_name, identification_number, administrator, date_use, usage_remark, status_condition, user_used, remark FROM master_tools_using WHERE statusDelete = 0 AND id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => 'success',
    'data' => $data
], JSON_UNESCAPED_UNICODE);
?>
