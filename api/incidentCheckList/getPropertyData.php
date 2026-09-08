<?php
/**
 * API: Get Property Checklist Data
 * ดึงข้อมูล incident_checklist_data จาก incident_checklist_transaction
 * สำหรับโหลดกลับมาแก้ไขในฟอร์มทรัพย์
 * 
 * Updated: 2026-03-04
 * - อ่านไฟล์รูปภาพและลายเซ็นจากดิสก์แทน base64 ใน DB
 */
require_once '../../db_config.php';

header('Content-Type: application/json; charset=utf-8');

$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * อ่านไฟล์จากดิสก์แล้วแปลงเป็น base64 data URI
 */
function fileToBase64($filePath) {
    if (!file_exists($filePath)) {
        return '';
    }
    $content = file_get_contents($filePath);
    if ($content === false) {
        return '';
    }
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];
    $mime = $mimeMap[$extension] ?? 'image/jpeg';
    return 'data:' . $mime . ';base64,' . base64_encode($content);
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

        // === โหลดรูปภาพ ===
        if (isset($data['photos']) && is_array($data['photos'])) {
            $photosDir = __DIR__ . '/../../uploads/checklist_photos/';
            foreach ($data['photos'] as &$photo) {
                // กรณี A: รูปเก็บเป็น BLOB ใน DB (ผ่าน file_id) — จาก saveProperty.php
                // ไม่ต้องแปลง base64 — frontend จะใช้ getFile.php?id= โดยตรง
                // กรณี B: รูปเก็บเป็นไฟล์บนดิสก์ (ผ่าน filename) — จาก save.php
                if (empty($photo['base64']) && !empty($photo['filename'])) {
                    $photo['base64'] = fileToBase64($photosDir . $photo['filename']);
                }
            }
            unset($photo);
        }

        // === โหลดลายเซ็นจากไฟล์ (กรณีไม่มี base64 ใน DB) ===
        if (isset($data['signatures']) && is_array($data['signatures'])) {
            $sigDir = __DIR__ . '/../../uploads/checklist_signatures/';
            foreach ($data['signatures'] as $type => &$sig) {
                if (empty($sig['base64']) && !empty($sig['filename'])) {
                    $sig['base64'] = fileToBase64($sigDir . $sig['filename']);
                }
            }
            unset($sig);

            // === โหลดลายเซ็นกลับเข้า handover/attachments_meta เพื่อ frontend ใช้งานได้ ===
            // กรณี A: base64 จากไฟล์ (save.php เดิม)
            if (!empty($data['signatures']['receiver_sig']['base64'])) {
                $data['handover']['receiver_sig'] = $data['signatures']['receiver_sig']['base64'];
            }
            if (!empty($data['signatures']['deliverer_sig']['base64'])) {
                $data['handover']['deliverer_sig'] = $data['signatures']['deliverer_sig']['base64'];
            }
            if (!empty($data['signatures']['scene_sketch']['base64'])) {
                $data['attachments_meta']['sketch'] = $data['signatures']['scene_sketch']['base64'];
            }

            // กรณี B: file_id จาก BLOB ใน DB (saveProperty.php)
            // ส่ง file_id กลับให้ frontend โหลดผ่าน getFile.php?id=
            $sigKeyMap = [
                'receiver_signature' => 'receiver_sig_file_id',
                'sender_signature'   => 'sender_sig_file_id',
                'scene_sketch'       => 'sketch_file_id'
            ];
            foreach ($sigKeyMap as $sigKey => $outputKey) {
                if (!empty($data['signatures'][$sigKey]['file_id'])) {
                    if (!isset($data['handover'])) $data['handover'] = [];
                    $data['handover'][$outputKey] = (int)$data['signatures'][$sigKey]['file_id'];
                }
            }
        }

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
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
