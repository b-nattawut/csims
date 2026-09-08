<?php
/**
 * API: Get Person Evidence Checklist Data (ตรวจเก็บวัตถุพยานที่บุคคล)
 * complaints_type = '08'
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../db_config.php';

function jsonResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    $response = [
        'status' => $success ? 'success' : 'error',
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// BLOB: frontend จะใช้ getFile.php?id=N โหลดรูปเอง (ไม่ต้องแปลง base64 ที่ backend เพราะช้ามาก)

$incidentId = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $incidentId = isset($_GET['incident_id']) ? (int) $_GET['incident_id'] : null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $incidentId = isset($_POST['incident_id']) ? (int) $_POST['incident_id'] : null;
}

if (!$incidentId) {
    jsonResponse(false, 'Missing incident_id', null, 400);
}

try {
    $stmt = $pdo->prepare("SELECT ict.id, ict.incident_id, ict.incident_checklist_data,
                                  ict.create_by, ict.create_date, ict.edit_by, ict.edit_date, ict.count_edit,
                                  rn.receiveNoti_No, rn.complaints_type
                           FROM incident_checklist_transaction ict
                           INNER JOIN rn_ReceiveNoti rn ON ict.incident_id = rn.id
                           WHERE ict.incident_id = ?
                           LIMIT 1");
    $stmt->execute([$incidentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonResponse(false, 'ไม่พบข้อมูล checklist สำหรับเหตุการณ์นี้');
    }

    $checklistData = null;
    if (!empty($row['incident_checklist_data'])) {
        $checklistData = json_decode($row['incident_checklist_data'], true);
    }

    // BLOB signatures/photos: ส่ง file_id กลับไปตรงๆ ให้ frontend ใช้ getFile.php?id=N โหลดเอง (เร็วกว่าแปลง base64)

    $stUser = $pdo->prepare("SELECT CONCAT(IFNULL(t2.rank_name,''),' ',t1.first_name,' ',t1.last_name) AS fullname
                             FROM user_profile t1
                             LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                             WHERE t1.user_id = ?");

    $creatorName = '';
    if (!empty($row['create_by'])) {
        $stUser->execute([$row['create_by']]);
        $uRow = $stUser->fetch(PDO::FETCH_ASSOC);
        if ($uRow) {
            $creatorName = trim((string) $uRow['fullname']);
        }
    }

    $editorName = '';
    if (!empty($row['edit_by'])) {
        $stUser->execute([$row['edit_by']]);
        $eRow = $stUser->fetch(PDO::FETCH_ASSOC);
        if ($eRow) {
            $editorName = trim((string) $eRow['fullname']);
        }
    }

    $result = [
        'record_id' => $row['id'],
        'incident_id' => $row['incident_id'],
        'receiveNoti_No' => $row['receiveNoti_No'],
        'complaints_type' => $row['complaints_type'],
        'checklist_data' => $checklistData,
        'edit_info' => [
            'create_by' => $row['create_by'],
            'creator_name' => $creatorName,
            'create_date' => $row['create_date'],
            'edit_by' => $row['edit_by'],
            'editor_name' => $editorName,
            'edit_date' => $row['edit_date'],
            'count_edit' => $row['count_edit'],
        ]
    ];

    jsonResponse(true, 'ดึงข้อมูลสำเร็จ', $result);

} catch (PDOException $e) {
    jsonResponse(false, 'เกิดข้อผิดพลาดฐานข้อมูล: ' . $e->getMessage(), null, 500);
}
