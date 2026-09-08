<?php
/**
 * API: Field_visit_log/delete.php
 * ลบบันทึกภาคสนาม (soft delete)
 */
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? $_POST['id'] ?? $_GET['id'] ?? '';

if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ที่ต้องการลบ'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sql = "UPDATE field_visit_logs SET status_delete = 1, edit_by = ?, edit_date = NOW() WHERE id = ? AND status_delete = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id'], $id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลที่ต้องการลบ'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['status' => 'success', 'message' => 'ลบข้อมูลเรียบร้อยแล้ว'], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
