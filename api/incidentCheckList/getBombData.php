<?php
/**
 * API: Get Bomb Checklist Data
 * ดึงข้อมูล incident_checklist_data จาก incident_checklist_transaction
 * สำหรับโหลดกลับมาแก้ไขในฟอร์มระเบิด
 * 
 * Updated: 2026-03-19
 * - รองรับ BLOB (file_id) + backward compat สำหรับ filename เดิม
 * - เพิ่ม edit_info เหมือน getLifeData.php
 */
require_once '../../db_config.php';

header('Content-Type: application/json; charset=utf-8');

$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * อ่านไฟล์จากดิสก์แล้วแปลงเป็น base64 data URI (legacy)
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
            foreach ($data['photos'] as &$photo) {
                if (!empty($photo['file_id'])) {
                    // BLOB: frontend จะใช้ getFile.php?id=N (ไม่ต้อง base64)
                    // ส่ง file_id กลับไปตรงๆ
                } elseif (empty($photo['base64']) && !empty($photo['filename'])) {
                    // Legacy: อ่านจากดิสก์
                    $photosDir = __DIR__ . '/../../uploads_2/checklist_bomb_photos/';
                    $photo['base64'] = fileToBase64($photosDir . $photo['filename']);
                }
            }
            unset($photo);
        }

        // === โหลดลายเซ็น ===
        if (isset($data['signatures']) && is_array($data['signatures'])) {
            foreach ($data['signatures'] as $type => &$sig) {
                if (!empty($sig['file_id'])) {
                    // BLOB: frontend จะใช้ getFile.php?id=N
                } elseif (empty($sig['base64']) && !empty($sig['filename'])) {
                    // Legacy: อ่านจากดิสก์
                    $sigDir = __DIR__ . '/../../uploads_2/checklist_bomb_signatures/';
                    $sig['base64'] = fileToBase64($sigDir . $sig['filename']);
                }
            }
            unset($sig);
        }

        // ★ ดึง basic_Info จาก rn_ReceiveNoti เพื่อ auto-fill พฤติการณ์คดี
        $stmtBasic = $pdo->prepare("SELECT basic_Info FROM rn_ReceiveNoti WHERE id = ?");
        $stmtBasic->execute([$incident_id]);
        $basicRow = $stmtBasic->fetch(PDO::FETCH_ASSOC);
        $basicInfo = ($basicRow && !empty($basicRow['basic_Info'])) ? $basicRow['basic_Info'] : '';

        echo json_encode(['success' => true, 'data' => $data, 'edit_info' => $editInfo, 'basic_info' => $basicInfo], JSON_UNESCAPED_UNICODE);
    } else {
        // ★ ถ้าไม่มี checklist data ก็ยังส่ง basic_info กลับไป
        $stmtBasic = $pdo->prepare("SELECT basic_Info FROM rn_ReceiveNoti WHERE id = ?");
        $stmtBasic->execute([$incident_id]);
        $basicRow = $stmtBasic->fetch(PDO::FETCH_ASSOC);
        $basicInfo = ($basicRow && !empty($basicRow['basic_Info'])) ? $basicRow['basic_Info'] : '';

        echo json_encode(['success' => false, 'message' => 'No data found', 'basic_info' => $basicInfo], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}