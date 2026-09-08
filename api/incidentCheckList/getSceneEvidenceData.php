<?php
/**
 * API: Get Scene Evidence Checklist Data (ตรวจเก็บวัตถุพยานที่เกิดเหตุ)
 * complaints_type = '07'
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../db_config.php';

function jsonResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    $response = ['status' => $success ? 'success' : 'error', 'success' => $success, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
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
    // First, get the incident creator from rn_ReceiveNotiApprove (seqSignature=1 = creator)
    // This table stores fullName and positionName directly at creation time — guaranteed to have data
    $stApprove = $pdo->prepare("SELECT fullName, positionName FROM rn_ReceiveNotiApprove WHERE ComplaintsID = ? AND seqSignature = 1 LIMIT 1");
    $stApprove->execute([$incidentId]);
    $approveRow = $stApprove->fetch(PDO::FETCH_ASSOC);
    $incidentCreatorName = $approveRow ? trim($approveRow['fullName']) : '';
    $incidentCreatorPosition = $approveRow ? trim($approveRow['positionName']) : '';

    // Fallback: query from user_profile if rn_ReceiveNotiApprove has no data
    if (empty($incidentCreatorName)) {
        $stRn = $pdo->prepare("SELECT CONCAT(IFNULL(t4.rank_name,''),' ',IFNULL(t3.first_name,''),' ',IFNULL(t3.last_name,'')) AS creator_fullname,
                                      IFNULL(t5.position_name,'') AS creator_position
                               FROM rn_ReceiveNoti rn
                               LEFT JOIN users t2 ON rn.create_by = t2.user_id
                               LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
                               LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
                               LEFT JOIN user_position t5 ON t3.position_id = t5.position_id
                               WHERE rn.id = ?");
        $stRn->execute([$incidentId]);
        $rnRow = $stRn->fetch(PDO::FETCH_ASSOC);
        if ($rnRow) {
            $incidentCreatorName = trim($rnRow['creator_fullname']);
            $incidentCreatorPosition = trim($rnRow['creator_position']);
        }
    }

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
        // No checklist data yet — still return incident creator info
        jsonResponse(true, 'ยังไม่มีข้อมูล checklist', [
            'incident_id' => $incidentId,
            'checklist_data' => null,
            'incident_creator' => [
                'fullname' => $incidentCreatorName,
                'position' => $incidentCreatorPosition,
            ],
            'edit_info' => []
        ]);
    }

    $checklistData = null;
    if (!empty($row['incident_checklist_data'])) {
        $checklistData = json_decode($row['incident_checklist_data'], true);
        
        // ตัดข้อความซ้ำเฉพาะ incident_location (แก้ปัญหาข้อมูลถูกบันทึกซ้ำ)
        if (is_array($checklistData) && isset($checklistData['general_info']['incident_location'])) {
            $loc = $checklistData['general_info']['incident_location'];
            if (mb_strlen($loc, 'UTF-8') > 500) {
                $cut = mb_substr($loc, 0, 150, 'UTF-8');
                if (preg_match('/^(.+(?:จ\.|จังหวัด)[^\s]{0,15})/u', $cut, $m)) {
                    $checklistData['general_info']['incident_location'] = trim($m[1]);
                } else {
                    $checklistData['general_info']['incident_location'] = $cut;
                }
            }
        }
        // ตัด location_detail ด้วย (เฉพาะกรณีที่ยาวเกิน 500 ตัว)
        if (is_array($checklistData) && isset($checklistData['general_info']['location_detail'])) {
            $loc = $checklistData['general_info']['location_detail'];
            if (mb_strlen($loc, 'UTF-8') > 500) {
                $cut = mb_substr($loc, 0, 150, 'UTF-8');
                if (preg_match('/^(.+(?:จ\.|จังหวัด)[^\s]{0,15})/u', $cut, $m)) {
                    $checklistData['general_info']['location_detail'] = trim($m[1]);
                } else {
                    $checklistData['general_info']['location_detail'] = $cut;
                }
            }
        }
    }

    // BLOB signatures/photos: ส่ง file_id กลับไปตรงๆ ให้ frontend ใช้ getFile.php?id=N โหลดเอง (เร็วกว่าแปลง base64)

    // ดึงชื่อผู้บันทึก
    $creatorName = '';
    if ($row['create_by']) {
        $stUser = $pdo->prepare("SELECT CONCAT(IFNULL(t2.rank_name,''),' ',t1.first_name,' ',t1.last_name) AS fullname
                                FROM user_profile t1
                                LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                                WHERE t1.user_id = ?");
        $stUser->execute([$row['create_by']]);
        $uRow = $stUser->fetch(PDO::FETCH_ASSOC);
        if ($uRow) $creatorName = trim($uRow['fullname']);
    }

    $editorName = '';
    if ($row['edit_by']) {
        $stUser->execute([$row['edit_by']]);
        $eRow = $stUser->fetch(PDO::FETCH_ASSOC);
        if ($eRow) $editorName = trim($eRow['fullname']);
    }

    $result = [
        'record_id' => $row['id'],
        'incident_id' => $row['incident_id'],
        'receiveNoti_No' => $row['receiveNoti_No'],
        'complaints_type' => $row['complaints_type'],
        'checklist_data' => $checklistData,
        'incident_creator' => [
            'fullname' => $incidentCreatorName,
            'position' => $incidentCreatorPosition,
        ],
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
