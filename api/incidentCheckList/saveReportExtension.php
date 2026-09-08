<?php
/**
 * API: saveReportExtension.php
 * บันทึกข้อมูลขอขยายเวลาการออกรายงาน
 */
require '../../db_config.php';
session_start();
header("Content-Type: application/json; charset=UTF-8");

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['incident_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ไม่พบ incident_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

$incidentId = (int)$input['incident_id'];
$formData   = $input['form_data'] ?? [];

try {
    // ตรวจสอบว่ามี record อยู่แล้วหรือไม่
    $stmt = $pdo->prepare("SELECT id FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incidentId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล checklist transaction สำหรับคดีนี้'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $jsonData = json_encode($formData, JSON_UNESCAPED_UNICODE);

    $updateStmt = $pdo->prepare("UPDATE incident_checklist_transaction SET incident_extend_time_data = ?, edit_date = NOW() WHERE id = ?");
    $updateStmt->execute([$jsonData, $existing['id']]);

    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลสำเร็จ'], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'], JSON_UNESCAPED_UNICODE);
}
