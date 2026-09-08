<?php
/**
 * API: Save/Update Traffic Report Data
 * บันทึกข้อมูลร่างรายงานคดีจราจร
 * ลงคอลัมน์ incident_report_data ใน incident_checklist_transaction
 */
require_once '../../db_config.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

// รับข้อมูล JSON จาก POST body
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input'], JSON_UNESCAPED_UNICODE);
    exit;
}

$incidentId = intval($input['incident_id'] ?? 0);
$formData = $input['form_data'] ?? [];

if ($incidentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid incident_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo->beginTransaction();

    // ดึง record ปัจจุบัน
    $stmt = $pdo->prepare("SELECT id, incident_report_data, count_report 
                           FROM incident_checklist_transaction 
                           WHERE incident_id = ? 
                           ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incidentId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Record not found. Please save checklist first.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // เตรียมข้อมูล report
    $currentReportData = [];
    if (!empty($existing['incident_report_data'])) {
        $currentReportData = json_decode($existing['incident_report_data'], true) ?: [];
    }

    // เก็บข้อมูลใน key 'traffic'
    $currentReportData['traffic'] = $formData;
    $currentReportData['last_updated'] = date('Y-m-d H:i:s');
    $currentReportData['updated_by'] = $userId;

    $jsonReportData = json_encode($currentReportData, JSON_UNESCAPED_UNICODE);

    // UPDATE
    $countEdit = intval($existing['count_report'] ?? 0) + 1;
    $sql = "UPDATE incident_checklist_transaction 
            SET incident_report_data = :report_data, 
                edit_by = :edit_by, 
                edit_date = NOW(),
                count_report = :count_report
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':report_data' => $jsonReportData,
        ':edit_by' => $userId,
        ':count_report' => $countEdit,
        ':id' => $existing['id']
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'บันทึกร่างรายงานจราจรเรียบร้อยแล้ว'
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
