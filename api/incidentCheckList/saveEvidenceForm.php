<?php
/**
 * API: Save Evidence Form Data (วัตถุพยาน)
 * ตาราง: incident_checklist_transaction (ฟิลด์ evidence_form_data)
 * Created: 2026-04-02
 */

ini_set('memory_limit', '256M');
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_config.php';

function jsonResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    $response = ['status' => $success ? 'success' : 'error', 'success' => $success, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed', null, 405);
}

// รับข้อมูล JSON
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE || !$data) {
    jsonResponse(false, 'Invalid JSON data', null, 400);
}

$incidentId = isset($data['incident_id']) ? (int)$data['incident_id'] : 0;
if ($incidentId <= 0) {
    jsonResponse(false, 'Missing or invalid incident_id', null, 400);
}

$evidenceFormData = $data['evidence_form'] ?? [];

try {
    // ตรวจสอบว่ามี record อยู่แล้วหรือไม่
    $stmt = $pdo->prepare("SELECT id, incident_checklist_data, count_edit FROM incident_checklist_transaction WHERE incident_id = ? LIMIT 1");
    $stmt->execute([$incidentId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $userId = $_SESSION['user_id'] ?? null;

    if ($existing) {
        // อัปเดต: ดึง checklist_data เดิมมา merge ข้อมูล evidence_form เข้าไป
        $checklistData = [];
        if (!empty($existing['incident_checklist_data'])) {
            $checklistData = json_decode($existing['incident_checklist_data'], true) ?: [];
        }

        // เก็บ evidence_form แยกเป็น key ใน checklist_data
        $checklistData['evidence_form'] = $evidenceFormData;

        $countEdit = (int)($existing['count_edit'] ?? 0) + 1;

        $updateStmt = $pdo->prepare("UPDATE incident_checklist_transaction 
                                      SET incident_checklist_data = ?, 
                                          count_edit = ?, 
                                          edit_by = ?, 
                                          edit_date = NOW() 
                                      WHERE incident_id = ?");
        $updateStmt->execute([
            json_encode($checklistData, JSON_UNESCAPED_UNICODE),
            $countEdit,
            $userId,
            $incidentId
        ]);

        jsonResponse(true, 'บันทึกข้อมูลวัตถุพยานสำเร็จ (อัปเดต)');
    } else {
        // ยังไม่มี record → สร้างใหม่
        $checklistData = ['evidence_form' => $evidenceFormData];

        $insertStmt = $pdo->prepare("INSERT INTO incident_checklist_transaction 
                                      (incident_id, incident_checklist_data, create_by, create_date) 
                                      VALUES (?, ?, ?, NOW())");
        $insertStmt->execute([
            $incidentId,
            json_encode($checklistData, JSON_UNESCAPED_UNICODE),
            $userId
        ]);

        jsonResponse(true, 'บันทึกข้อมูลวัตถุพยานสำเร็จ (สร้างใหม่)');
    }

} catch (PDOException $e) {
    jsonResponse(false, 'เกิดข้อผิดพลาดฐานข้อมูล: ' . $e->getMessage(), null, 500);
}
