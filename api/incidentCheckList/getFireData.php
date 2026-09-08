<?php
require_once '../../db_config.php';

header('Content-Type: application/json; charset=utf-8');

$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT incident_checklist_data, count_edit, edit_date, edit_by FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && !empty($result['incident_checklist_data'])) {
        $data = json_decode($result['incident_checklist_data'], true);

        // ข้อมูลการแก้ไข
        $editInfo = [
            'count_edit' => (int)($result['count_edit'] ?? 0),
            'edit_date' => $result['edit_date'] ?? null,
            'edit_by' => $result['edit_by'] ?? null
        ];

        // signatures และ photos เก็บ file_id อยู่แล้ว — frontend จะใช้ getFile.php?id=X เพื่อโหลดรูป

        // ★ ดึง basic_Info จาก rn_ReceiveNoti เพื่อ auto-fill พฤติการณ์คดี
        $stmtBasic = $pdo->prepare("SELECT basic_Info FROM rn_ReceiveNoti WHERE id = ?");
        $stmtBasic->execute([$incident_id]);
        $basicRow = $stmtBasic->fetch(PDO::FETCH_ASSOC);
        $basicInfo = ($basicRow && !empty($basicRow['basic_Info'])) ? $basicRow['basic_Info'] : '';

        echo json_encode(['success' => true, 'data' => $data, 'edit_info' => $editInfo, 'basic_info' => $basicInfo], JSON_UNESCAPED_UNICODE);
    } else {
        $stmtBasic = $pdo->prepare("SELECT basic_Info FROM rn_ReceiveNoti WHERE id = ?");
        $stmtBasic->execute([$incident_id]);
        $basicRow = $stmtBasic->fetch(PDO::FETCH_ASSOC);
        $basicInfo = ($basicRow && !empty($basicRow['basic_Info'])) ? $basicRow['basic_Info'] : '';

        echo json_encode(['success' => false, 'message' => 'No data found', 'basic_info' => $basicInfo], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
