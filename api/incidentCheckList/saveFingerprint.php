<?php

/**
 * API: Save Fingerprint Evidence Checklist Data
 * ตาราง: incident_checklist_transaction / incident_checklist_transaction_file
 */

ini_set('memory_limit', '256M');
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_config.php';

// ============================================
// Helper Functions
// ============================================
function jsonResponse($success, $message, $data = null, $httpCode = 200)
{
    http_response_code($httpCode);
    $response = [
        'status' => $success ? 'success' : 'error',
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

function saveBlobToDb($pdo, $incidentId, $blobData)
{
    $stmt = $pdo->prepare("INSERT INTO incident_checklist_transaction_file (id_incident, file_name) VALUES (?, ?)");
    $stmt->execute([$incidentId, $blobData]);
    return (int) $pdo->lastInsertId();
}

function deleteBlobFromDb($pdo, $fileId)
{
    $stmt = $pdo->prepare("DELETE FROM incident_checklist_transaction_file WHERE id = ?");
    $stmt->execute([$fileId]);
}

function compressImage($blobData, $maxBytes = 2097152) {
    if (strlen($blobData) <= $maxBytes) return $blobData;
    $img = @imagecreatefromstring($blobData);
    if ($img === false) return $blobData;
    $quality = 85;
    $compressed = $blobData;
    while ($quality >= 10) {
        ob_start(); imagejpeg($img, null, $quality); $output = ob_get_clean();
        if (strlen($output) <= $maxBytes) { $compressed = $output; break; }
        $compressed = $output; $quality -= 10;
    }
    if (strlen($compressed) > $maxBytes) {
        $w = imagesx($img); $h = imagesy($img);
        $resized = imagecreatetruecolor((int)($w*0.5), (int)($h*0.5));
        imagecopyresampled($resized, $img, 0,0,0,0, (int)($w*0.5),(int)($h*0.5), $w,$h);
        $quality = 80;
        while ($quality >= 10) {
            ob_start(); imagejpeg($resized, null, $quality); $output = ob_get_clean();
            if (strlen($output) <= $maxBytes) { $compressed = $output; break; }
            $compressed = $output; $quality -= 10;
        }
        imagedestroy($resized);
    }
    imagedestroy($img);
    return $compressed;
}

// ============================================
// Main Logic
// ============================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed', null, 405);
}

$data = null;

if (isset($_POST['payload'])) {
    $data = json_decode($_POST['payload'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, 'Invalid JSON in payload: ' . json_last_error_msg(), null, 400);
    }
} else {
    $rawData = file_get_contents('php://input');
    if (!empty($rawData)) {
        $data = json_decode($rawData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $data = $_POST;
        }
    } else {
        $data = $_POST;
    }
}

// ตรวจสอบ Incident ID
$incidentId = null;
if (isset($data['receiveNoti_id']) && !empty($data['receiveNoti_id'])) {
    $incidentId = (int) $data['receiveNoti_id'];
} elseif (isset($data['incident_id']) && !empty($data['incident_id'])) {
    $incidentId = (int) $data['incident_id'];
} elseif (isset($data['doc_no']) && !empty($data['doc_no'])) {
    $findStmt = $pdo->prepare("SELECT id FROM rn_ReceiveNoti WHERE receiveNoti_No = :doc_no LIMIT 1");
    $findStmt->execute([':doc_no' => $data['doc_no']]);
    $row = $findStmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $incidentId = (int) $row['id'];
    }
}

if (!$incidentId) jsonResponse(false, 'ไม่พบรหัสใบรับแจ้งเหตุ', null, 400);

session_start();
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

try {
    $pdo->beginTransaction();

    // --- ดึงข้อมูลเดิม ---
    $stmtOld = $pdo->prepare("SELECT id, incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? LIMIT 1");
    $stmtOld->execute([$incidentId]);
    $oldRow = $stmtOld->fetch(PDO::FETCH_ASSOC);

    $oldData = null;
    $existingPhotos = [];
    if ($oldRow && !empty($oldRow['incident_checklist_data'])) {
        $oldData = json_decode($oldRow['incident_checklist_data'], true);
        if ($oldData !== null) {
            $existingPhotos = $oldData['photos'] ?? [];
        }
    }

    // โครงสร้างข้อมูลสำหรับบันทึก
    $fpReportChannel = $data['purposes'] ?? [];
    if (empty($fpReportChannel) && (!empty($data['evidence_doc_no']) || !empty($data['evidence_letter_no']) || !empty($data['letter_no']))) {
        $fpReportChannel = ['document'];
    }

    $checklistData = [
        'general_info' => [
            'case_type' => 'fingerprint',
            'case_type_other' => '',
            'doc_no' => $data['doc_no'] ?? '',
            'report_no' => $data['report_no'] ?? '',
            'receive_date' => $data['receive_date'] ?? '',
            'receive_time' => $data['receive_time'] ?? '',
            'report_datetime' => !empty($data['receive_date'])
                ? ($data['receive_date'] . 'T' . ($data['receive_time'] ?? '00:00'))
                : '',
            'case_no' => $data['case_no'] ?? '',
            'police_station' => $data['police_station'] ?? '',
            'source_station' => $data['police_station'] ?? '',
            'letter_no' => $data['letter_no'] ?? '',
            'letter_date' => $data['letter_date'] ?? '',
            'document_no' => $data['evidence_doc_no'] ?? ($data['evidence_letter_no'] ?? ($data['letter_no'] ?? '')),
            'evidence_sender' => $data['evidence_sender'] ?? '',
            'evidence_sender_position' => $data['evidence_sender_position'] ?? '',
            'evidence_sender_phone' => $data['evidence_sender_phone'] ?? '',
            'evidence_letter_no' => $data['evidence_letter_no'] ?? '',
            'evidence_doc_no' => $data['evidence_doc_no'] ?? '',
            'evidence_doc_date' => $data['evidence_doc_date'] ?? '',
            'purposes' => $data['purposes'] ?? [],
            'purpose_other_text' => $data['purpose_other_text'] ?? '',
            'report_channel' => $fpReportChannel,
            'report_channel_other' => $data['purpose_other_text'] ?? '',
            'incident_date' => $data['incident_date'] ?? '',
            'incident_time' => $data['incident_time'] ?? '',
            'incident_datetime' => !empty($data['incident_date'])
                ? ($data['incident_date'] . 'T' . ($data['incident_time'] ?? '00:00'))
                : '',
            'known_date' => $data['known_date'] ?? '',
            'known_time' => $data['known_time'] ?? '',
            'collect_date' => $data['collect_date'] ?? '',
            'collect_time' => $data['collect_time'] ?? '',
            'inspection_datetime' => !empty($data['collect_date'])
                ? ($data['collect_date'] . 'T' . ($data['collect_time'] ?? '00:00'))
                : '',
            'location_detail' => $data['location_detail'] ?? ($data['crime_location'] ?? ($data['incident_location'] ?? '')),
            'investigator' => [
                'name' => $data['evidence_sender'] ?? '',
                'firstname' => $data['evidence_sender'] ?? '',
                'lastname' => '',
                'phone' => $data['evidence_sender_phone'] ?? ''
            ],
            'victim' => [
                'name' => '',
                'firstname' => '',
                'lastname' => '',
                'age' => ''
            ]
        ],
        'inspectors' => $data['inspectors'] ?? [],
        'package_storage' => [
            'package_types' => $data['package_types'] ?? [],
            'seal_conditions' => $data['seal_conditions'] ?? [],
            'collector_type' => $data['collector_type'] ?? '',
            'collector_other_text' => $data['collector_other_text'] ?? '',
            'storage_date' => $data['storage_date'] ?? '',
            'storage_time' => $data['storage_time'] ?? '',
            'duration_year' => $data['duration_year'] ?? '',
            'duration_month' => $data['duration_month'] ?? '',
            'duration_day' => $data['duration_day'] ?? '',
        ],
        'section6_sets' => $data['section6_sets'] ?? [],
        'section9' => $data['section9'] ?? [],
        'photo_records' => [
            'inspect_date' => $data['photo_inspect_date'] ?? '',
            'inspect_time' => $data['photo_inspect_time'] ?? '',
            'id_start' => $data['photo_id_start'] ?? '',
            'id_end' => $data['photo_id_end'] ?? '',
            'amount' => $data['photo_amount'] ?? '',
            'photographer_name' => $data['photographer_name'] ?? '',
            'photographer_datetime' => $data['photographer_datetime'] ?? '',
        ],
        'handover' => [
            'receiver_id' => '',
            'receiver_pos' => '',
            'deliverer_id' => '',
            'deliverer_pos' => $data['evidence_sender_position'] ?? '',
            'sender_name' => $data['evidence_sender'] ?? '',
            'sender_position' => $data['evidence_sender_position'] ?? ''
        ],
        'evidences' => [],
        'photos' => [],
    ];

    $sectionSets = is_array($data['section6_sets'] ?? null) ? $data['section6_sets'] : [];
    $normalizedEvidences = [];
    $evidenceNo = 1;
    $hasMultipleSets = count($sectionSets) > 1;
    foreach ($sectionSets as $setIndex => $set) {
        $setNumber = (int)($set['set_index'] ?? ($setIndex + 1));
        $setLabUnit = $set['lab_unit'] ?? 'fingerprint';

        $evidenceItems = is_array($set['evidence_items'] ?? null) ? $set['evidence_items'] : [];
        if (!empty($evidenceItems)) {
            foreach ($evidenceItems as $itemIndex => $item) {
                $detail = trim((string)(
                    $item['description'] ??
                    $item['detail'] ??
                    $item['item'] ??
                    ''
                ));

                if ($detail === '') {
                    continue;
                }

                if ($hasMultipleSets) {
                    $detail = 'ชุดที่ ' . $setNumber . ': ' . $detail;
                }

                $normalizedEvidences[] = [
                    'no' => $item['no'] ?? $evidenceNo++,
                    'detail' => $detail,
                    'lab_unit' => ($item['lab_unit'] ?? $setLabUnit)
                ];
            }
            continue;
        }

        $detail = trim((string)(
            $set['evidence_detail'] ??
            $set['detail'] ??
            $set['item'] ??
            $set['description'] ??
            ''
        ));

        if ($detail === '') {
            continue;
        }

        if ($hasMultipleSets) {
            $detail = 'ชุดที่ ' . $setNumber . ': ' . $detail;
        }

        $normalizedEvidences[] = [
            'no' => $set['no'] ?? $evidenceNo++,
            'detail' => $detail,
            'lab_unit' => $setLabUnit
        ];
    }
    $checklistData['evidences'] = $normalizedEvidences;

    // ===== รูปภาพ — บันทึกเป็น BLOB ใน incident_checklist_transaction_file =====
    $newPhotos = [];
    $imageFields = ['incident_photos_fp', 'camera_input_fp', 'incident_photos_fpn', 'camera_input_fpn'];
    foreach ($imageFields as $field) {
        if (isset($_FILES[$field])) {
            $files = $_FILES[$field];
            $fileCount = is_array($files['name']) ? count($files['name']) : 1;
            for ($i = 0; $i < $fileCount; $i++) {
                $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
                if ($error === UPLOAD_ERR_OK) {
                    $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                    $originalName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                    $blobData = file_get_contents($tmpName);
                    if ($blobData !== false && strlen($blobData) > 0) {
                        $blobData = compressImage($blobData, 2097152);
                        $newFileId = saveBlobToDb($pdo, $incidentId, $blobData);
                        $newPhotos[] = ['file_id' => $newFileId, 'filename' => $originalName];
                    }
                }
            }
        }
    }

    // รับรายการรูปที่ต้องการลบ (ส่งมาเป็น file_id)
    $deletedPhotoFileIds = [];
    if (isset($_POST['deleted_photo_file_ids'])) {
        $decoded = json_decode($_POST['deleted_photo_file_ids'], true);
        if (is_array($decoded)) $deletedPhotoFileIds = $decoded;
    } elseif (isset($data['deleted_photo_file_ids']) && is_array($data['deleted_photo_file_ids'])) {
        $deletedPhotoFileIds = $data['deleted_photo_file_ids'];
    }

    // กรองรูปเก่าที่ไม่ถูกลบ + ลบ BLOB จาก DB
    $keptPhotos = [];
    foreach ($existingPhotos as $photo) {
        $fid = $photo['file_id'] ?? 0;
        if (in_array($fid, $deletedPhotoFileIds)) {
            deleteBlobFromDb($pdo, $fid);
        } else {
            $keptPhotos[] = $photo;
        }
    }

    // รวมรูปเก่าที่เหลือ + รูปใหม่
    $checklistData['photos'] = array_merge($keptPhotos, $newPhotos);

    // Photo captions (if sent)
    if (isset($data['photo_captions']) && is_array($data['photo_captions'])) {
        $checklistData['photo_captions'] = $data['photo_captions'];
    }

    // บันทึก DB
    $json = json_encode($checklistData, JSON_UNESCAPED_UNICODE);

    $check = $pdo->prepare("SELECT id FROM incident_checklist_transaction WHERE incident_id = ? LIMIT 1");
    $check->execute([$incidentId]);
    $exists = $check->fetch();

    if ($exists) {
        $sql = "UPDATE incident_checklist_transaction 
                SET incident_checklist_data = ?, incident_report_data = ?, edit_by = ?, edit_date = NOW(),
                    count_edit = count_edit + 1
                WHERE incident_id = ?";
        $pdo->prepare($sql)->execute([$json, $json, $userId, $incidentId]);
    } else {
        $sql = "INSERT INTO incident_checklist_transaction 
                (incident_id, incident_checklist_data, incident_report_data, create_by, create_date) 
                VALUES (?, ?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute([$incidentId, $json, $json, $userId]);
    }

    $pdo->prepare("UPDATE rn_ReceiveNoti SET statusChecklist = 1 WHERE id = ?")->execute([$incidentId]);

    $pdo->commit();

    jsonResponse(true, 'บันทึกข้อมูลเรียบร้อยแล้ว', [
        'photos_count' => count($checklistData['photos'])
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Fingerprint Checklist Save Error: " . $e->getMessage());
    jsonResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage());
}
