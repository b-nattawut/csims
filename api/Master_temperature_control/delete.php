<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$ID = $_POST['id'] ?? null;
$dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');

if (!$ID) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบ ID'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE master_control_temp SET 
            statusDelete = 1,
            edit_by = ?,
            edit_date = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $dateNow,
        $ID
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "ลบข้อมูลสำเร็จ"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "ไม่พบข้อมูลที่ต้องการลบ"
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
