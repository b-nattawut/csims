<?php
/**
 * API: Get Evidence Form Data
 * ดึงข้อมูล evidence_form จาก incident_checklist_transaction
 * สำหรับโหลดกลับมาแก้ไขในฟอร์มวัตถุพยาน
 * 
 * Created: 2026-05-19
 */
require_once '../../db_config.php';

header('Content-Type: application/json; charset=utf-8');

$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && !empty($result['incident_checklist_data'])) {
        $data = json_decode($result['incident_checklist_data'], true);

        // ตรวจสอบว่า json_decode สำเร็จหรือไม่
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log('[getEvidenceData] JSON decode failed for incident_id=' . $incident_id . ': ' . json_last_error_msg());
            echo json_encode([
                'success' => false, 
                'message' => 'ข้อมูล JSON เสียหาย: ' . json_last_error_msg()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ดึง evidence_form ถ้ามี
        $evidenceForm = $data['evidence_form'] ?? null;

        echo json_encode([
            'success' => true,
            'data' => $data,
            'evidence_form' => $evidenceForm
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // ไม่มีข้อมูล checklist - ส่ง empty data (ยังถือว่า success)
        echo json_encode([
            'success' => true,
            'data' => null,
            'evidence_form' => null
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('[getEvidenceData] Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
