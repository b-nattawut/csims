<?php
/**
 * API: Get Traffic Incident Checklist Data for Editing
 * ดึงข้อมูลก้อน JSON จาก incident_checklist_transaction เพื่อนำไปใส่ในฟอร์มแก้ไข
 */

require_once '../../db_config.php';

// รับค่า incident_id จากการเรียก (GET/POST)
$incident_id = isset($_REQUEST['incident_id']) ? intval($_REQUEST['incident_id']) : 0;

if ($incident_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    // ดึงข้อมูลล่าสุดจากตาราง Checklist Transaction
    $sql = "SELECT incident_checklist_data, count_edit, edit_date, edit_by
            FROM incident_checklist_transaction 
            WHERE incident_id = ? 
            ORDER BY create_date DESC 
            LIMIT 1";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$incident_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');

    if ($result && !empty($result['incident_checklist_data'])) {
        $checklistData = json_decode($result['incident_checklist_data'], true);
        
        $editInfo = [
            'count_edit' => (int)($result['count_edit'] ?? 0),
            'edit_date' => $result['edit_date'] ?? null,
            'edit_by' => $result['edit_by'] ?? null
        ];

        // ★ ดึง basic_Info จาก rn_ReceiveNoti เพื่อ auto-fill พฤติการณ์คดี
        $stmtBasic = $pdo->prepare("SELECT basic_Info FROM rn_ReceiveNoti WHERE id = ?");
        $stmtBasic->execute([$incident_id]);
        $basicRow = $stmtBasic->fetch(PDO::FETCH_ASSOC);
        $basicInfo = ($basicRow && !empty($basicRow['basic_Info'])) ? $basicRow['basic_Info'] : '';

        echo json_encode([
            'success' => true, 
            'data' => $checklistData,
            'edit_info' => $editInfo,
            'basic_info' => $basicInfo
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $stmtBasic = $pdo->prepare("SELECT basic_Info FROM rn_ReceiveNoti WHERE id = ?");
        $stmtBasic->execute([$incident_id]);
        $basicRow = $stmtBasic->fetch(PDO::FETCH_ASSOC);
        $basicInfo = ($basicRow && !empty($basicRow['basic_Info'])) ? $basicRow['basic_Info'] : '';

        echo json_encode([
            'success' => false, 
            'message' => 'ไม่พบข้อมูลเดิมในระบบ',
            'basic_info' => $basicInfo
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}