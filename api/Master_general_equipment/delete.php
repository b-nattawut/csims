<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$ID = $_POST['id'] ?? null;

if (!$ID) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบ ID'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE master_tools_using SET statusDelete = 1 WHERE id = ?");
    $stmt->execute([$ID]);

    echo json_encode([
        "status" => "success",
        "message" => "ลบข้อมูลสำเร็จ"
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
