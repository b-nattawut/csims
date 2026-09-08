<?php
/**
 * API: Save Person Evidence Checklist Data (ตรวจเก็บวัตถุพยานที่บุคคล)
 * complaints_type = '08'
 * ตาราง: incident_checklist_transaction
 */

ini_set('memory_limit', '256M');
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_config.php';

function jsonResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    $response = ['status' => $success ? 'success' : 'error', 'success' => $success, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// === BLOB functions (เหมือน fingerprint) ===
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed', null, 405);
}

$data = $_POST;

$incidentId = null;
if (!empty($data['receiveNoti_id_ev8'])) {
    $incidentId = (int) $data['receiveNoti_id_ev8'];
}
if (!$incidentId && !empty($data['doc_no_ev8'])) {
    $stmt = $pdo->prepare("SELECT id FROM rn_ReceiveNoti WHERE receiveNoti_No = ? LIMIT 1");
    $stmt->execute([$data['doc_no_ev8']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $incidentId = (int) $row['id'];
}
if (!$incidentId) jsonResponse(false, 'ไม่พบรหัสใบรับแจ้งเหตุ', null, 400);

session_start();
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

try {
    $pdo->beginTransaction();

    $stmtOld = $pdo->prepare("SELECT id, incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? LIMIT 1");
    $stmtOld->execute([$incidentId]);
    $oldRow = $stmtOld->fetch(PDO::FETCH_ASSOC);
    $oldData = null;
    $existingPhotos = [];
    if ($oldRow && !empty($oldRow['incident_checklist_data'])) {
        $oldData = json_decode($oldRow['incident_checklist_data'], true);
        if ($oldData !== null) $existingPhotos = $oldData['photos'] ?? [];
    }

    $buildDateTime = function($date, $time) {
        $d = trim((string)($date ?? ''));
        if ($d === '') return '';
        $t = trim((string)($time ?? '00:00'));
        if ($t === '') $t = '00:00';
        return $d . 'T' . $t;
    };

    $personPrefixes = $data['ev8_person_prefix'] ?? [];
    $personNames = $data['ev8_person_name'] ?? [];

    $checklistData = [
        'general_info' => [
            'case_type' => 'person_evidence',
            'doc_no' => $data['doc_no_ev8'] ?? '',
            'report_no' => $data['report_no_ev8'] ?? '',
            'receive_date' => $data['ev8_receive_date'] ?? '',
            'receive_time' => $data['ev8_receive_time'] ?? '',
            'unit_type' => $data['ev8_unit_type'] ?? '',
            'unit_name' => $data['ev8_unit_name'] ?? '',
            'notify_method' => $data['ev8_notify_method'] ?? [],
            'notify_method_other_text' => $data['ev8_notify_method_other_text'] ?? '',
            'police_station' => $data['ev8_police_station'] ?? '',
            'document_no' => $data['ev8_document_no'] ?? '',
            'document_date' => $data['ev8_document_date'] ?? '',
            'case_no' => $data['ev8_case_no'] ?? '',
            'incident_location' => $data['ev8_incident_location'] ?? '',
            'incident_date' => $data['ev8_incident_date'] ?? '',
            'incident_time' => $data['ev8_incident_time'] ?? '',
            'investigator_name' => $data['ev8_investigator_name'] ?? '',
            'send_request' => $data['ev8_send_request'] ?? '',
            // generator compatibility
            'report_date' => $data['ev8_receive_date'] ?? '',
            'report_time' => $data['ev8_receive_time'] ?? '',
            'source_station' => $data['ev8_police_station'] ?? '',
            'location_detail' => $data['ev8_incident_location'] ?? '',
            'incident_datetime' => $buildDateTime($data['ev8_incident_date'] ?? '', $data['ev8_incident_time'] ?? ''),
            'inspection_datetime' => $buildDateTime($data['ev8_inspect_date'] ?? '', $data['ev8_inspect_time'] ?? ''),
            'inspectors' => [],
        ],
        'persons' => [],
        'person_info' => [],
        'purpose' => [
            'detail' => $data['ev8_purpose_detail'] ?? '',
        ],
        'inspection' => [
            'location' => $data['ev8_inspect_location'] ?? '',
            'date' => $data['ev8_inspect_date'] ?? '',
            'time' => $data['ev8_inspect_time'] ?? '',
        ],
        'evidence_handling' => [
            'witness_name' => $data['ev8_witness_name'] ?? '',
            'witness_form' => $data['ev8_witness_form'] ?? '',
            'witness_detail' => $data['ev8_witness_detail'] ?? '',
            'handover_method' => $data['ev8_handover_method'] ?? '',
            'handover_method_checks' => $data['pepf_handover_method_check'] ?? [],
            'handover_method_detail' => $data['ev8_handover_method_detail'] ?? '',
            'handover_item_ref' => $data['ev8_handover_item_ref'] ?? '',
            'handover_to' => $data['ev8_handover_to'] ?? '',
            'handover_purpose' => $data['ev8_handover_purpose'] ?? '',
            'purpose_lines' => $data['pepf_handover_more_lines'] ?? [],
            'purpose_full' => $data['pepf_handover_purpose_full'] ?? '',
        ],
        'handover' => [
            'receiver_id' => $data['ev8_receiver_id'] ?? '',
            'receiver_pos' => $data['ev8_receiver_position'] ?? '',
            'deliverer_id' => $data['ev8_sender_id'] ?? '',
            'deliverer_pos' => $data['ev8_sender_position'] ?? '',
            'inspection_end_date' => $data['ev8_sign_date'] ?? '',
            'inspection_end_time' => $data['ev8_inspect_time'] ?? '',
        ],
        'signer' => [
            'id' => $data['pepf_signer_id'] ?? '',
            'fullname' => $data['pepf_signer_fullname'] ?? '',
            'position' => $data['pepf_signer_position'] ?? '',
        ],
        'inspectors' => $data['ev8_inspector_id'] ?? [],
        'photo_records' => [
            'start' => $data['ev8_photo_id_start'] ?? '',
            'end' => $data['ev8_photo_id_end'] ?? '',
            'amount' => $data['ev8_photo_amount'] ?? '',
        ],
        'pdf_form' => [
            'report_ref' => $data['pepf_report_ref'] ?? '',
            'report_year' => $data['pepf_report_year'] ?? '',
            'unit_type_checks' => $data['pepf_unit_type_check'] ?? [],
            'center_name' => $data['pepf_center_name'] ?? '',
            'province_name' => $data['pepf_province_name'] ?? '',
        ],
        'signatures' => [],
        'photos' => [],
        'evidences' => [],
    ];

    // persons (ต้องมี name ถึงจะบันทึก — prefix อย่างเดียวไม่นับ)
    if (is_array($personNames)) {
        foreach ($personNames as $idx => $name) {
            $name = trim((string)$name);
            if ($name === '') continue;
            $prefix = isset($personPrefixes[$idx]) ? trim((string)$personPrefixes[$idx]) : '';
            $checklistData['persons'][] = ['prefix' => $prefix, 'name' => $name];
        }
    }

    // person info (3.1)
    $keys = ['ev8_info_prefix', 'ev8_info_fullname', 'ev8_info_id_card', 'ev8_info_passport', 'ev8_info_height', 'ev8_info_age', 'ev8_info_skin', 'ev8_info_hand', 'ev8_info_feature'];
    $infoArrays = [];
    foreach ($keys as $k) {
        $infoArrays[$k] = is_array($data[$k] ?? null) ? $data[$k] : [];
    }
    $maxInfo = 0;
    foreach ($infoArrays as $arr) $maxInfo = max($maxInfo, count($arr));
    for ($i = 0; $i < $maxInfo; $i++) {
        $row = [
            'prefix' => trim((string)($infoArrays['ev8_info_prefix'][$i] ?? '')),
            'fullname' => trim((string)($infoArrays['ev8_info_fullname'][$i] ?? '')),
            'id_card' => trim((string)($infoArrays['ev8_info_id_card'][$i] ?? '')),
            'passport' => trim((string)($infoArrays['ev8_info_passport'][$i] ?? '')),
            'height' => trim((string)($infoArrays['ev8_info_height'][$i] ?? '')),
            'age' => trim((string)($infoArrays['ev8_info_age'][$i] ?? '')),
            'skin' => trim((string)($infoArrays['ev8_info_skin'][$i] ?? '')),
            'hand' => trim((string)($infoArrays['ev8_info_hand'][$i] ?? '')),
            'feature' => trim((string)($infoArrays['ev8_info_feature'][$i] ?? '')),
        ];
        // ต้องมีชื่อหรือบัตร ถึงจะบันทึก (prefix/dropdown อย่างเดียวไม่นับ)
        $hasData = ($row['fullname'] !== '' || $row['id_card'] !== '' || $row['passport'] !== '');
        if ($hasData) $checklistData['person_info'][] = $row;
    }

    // evidences (3.2)
    $evDescs = is_array($data['ev8_evidence_desc'] ?? null) ? $data['ev8_evidence_desc'] : [];
    $evQtys = is_array($data['ev8_evidence_qty'] ?? null) ? $data['ev8_evidence_qty'] : [];
    $evLabs = is_array($data['ev8_lab_unit'] ?? null) ? $data['ev8_lab_unit'] : [];
    $maxEv = max(count($evDescs), count($evQtys), count($evLabs));
    for ($i = 0; $i < $maxEv; $i++) {
        $detail = trim((string)($evDescs[$i] ?? ''));
        $qty = trim((string)($evQtys[$i] ?? ''));
        $lab = trim((string)($evLabs[$i] ?? ''));
        // ต้องมีรายละเอียดหรือจำนวน ถึงจะบันทึก (lab_unit dropdown อย่างเดียวไม่นับ)
        if ($detail === '' && $qty === '') continue;
        $checklistData['evidences'][] = [
            'detail' => $detail,
            'item' => $detail,
            'qty' => $qty,
            'lab_unit' => $lab,
        ];
    }

    // Sync inspector structure for generator compatibility
    if (is_array($checklistData['inspectors'])) {
        $normalizedInspectors = [];
        foreach ($checklistData['inspectors'] as $inspId) {
            if ($inspId === '' || $inspId === null) continue;
            $normalizedInspectors[] = ['id' => $inspId, 'user_id' => $inspId];
        }
        $checklistData['inspectors'] = $normalizedInspectors;
        $checklistData['general_info']['inspectors'] = $normalizedInspectors;
    }

    // signatures
    $senderSignaturePresent = isset($data['ev8_sender_signature_present']) ? (string)$data['ev8_sender_signature_present'] : null;
    $receiverSignaturePresent = isset($data['ev8_receiver_signature_present']) ? (string)$data['ev8_receiver_signature_present'] : null;
    $senderCleared = ($senderSignaturePresent === '0');
    $receiverCleared = ($receiverSignaturePresent === '0');

    // ดึง existing file_ids ของ signatures จาก JSON เดิม
    $existingSigFileIds = [];
    if ($oldData && isset($oldData['signatures'])) {
        foreach ($oldData['signatures'] as $sKey => $sVal) {
            if (!empty($sVal['file_id'])) {
                $existingSigFileIds[$sKey] = (int) $sVal['file_id'];
            }
        }
    }

    $sigMap = [
        'signer_sig' => $data['ev8_signer_signature_data'] ?? '',
        'receiver_sig' => $data['pepf_receiver_signature_data'] ?? '',
        'deliverer_sig' => $data['pepf_sender_signature_data'] ?? '',
    ];

    foreach ($sigMap as $key => $sigBase64) {
        $cleared = false;
        if ($key === 'receiver_sig') $cleared = $receiverCleared;
        if ($key === 'deliverer_sig' || $key === 'signer_sig') $cleared = $senderCleared;

        if ($cleared) {
            if (!empty($existingSigFileIds[$key])) deleteBlobFromDb($pdo, $existingSigFileIds[$key]);
            unset($checklistData['signatures'][$key]);
        } elseif (!empty($sigBase64) && strpos($sigBase64, 'data:image') !== false) {
            if (strpos($sigBase64, ',') !== false) list(, $rawB64) = explode(',', $sigBase64, 2);
            else $rawB64 = $sigBase64;
            $blobData = base64_decode($rawB64);
            if ($blobData !== false && strlen($blobData) > 0) {
                if (!empty($existingSigFileIds[$key])) deleteBlobFromDb($pdo, $existingSigFileIds[$key]);
                $newFileId = saveBlobToDb($pdo, $incidentId, $blobData);
                $checklistData['signatures'][$key] = ['file_id' => $newFileId];
            }
        } elseif ($oldData && isset($oldData['signatures'][$key])) {
            $checklistData['signatures'][$key] = $oldData['signatures'][$key];
        }
    }

    // photos (BLOB ใน DB)
    $newPhotos = [];
    if (isset($_FILES['incident_photos_ev8']) && !empty($_FILES['incident_photos_ev8']['name'][0])) {
        $photos = $_FILES['incident_photos_ev8'];
        $fileCount = is_array($photos['name']) ? count($photos['name']) : 1;
        for ($i = 0; $i < $fileCount; $i++) {
            $error = is_array($photos['error']) ? $photos['error'][$i] : $photos['error'];
            if ($error === UPLOAD_ERR_OK) {
                $tmpName = is_array($photos['tmp_name']) ? $photos['tmp_name'][$i] : $photos['tmp_name'];
                $fileName = is_array($photos['name']) ? $photos['name'][$i] : $photos['name'];
                $blobData = file_get_contents($tmpName);
                if ($blobData !== false && strlen($blobData) > 0) {
                    $blobData = compressImage($blobData, 2097152);
                    $newFileId = saveBlobToDb($pdo, $incidentId, $blobData);
                    $newPhotos[] = [
                        'file_id' => $newFileId,
                        'filename' => $fileName,
                        'original_name' => $fileName
                    ];
                }
            }
        }
    }

    // รับรายการรูปที่ต้องการลบ
    $deletedPhotoFileIds = [];
    if (isset($_POST['deleted_photo_file_ids'])) {
        $deletedPhotoFileIds = json_decode($_POST['deleted_photo_file_ids'], true) ?? [];
    }
    $deletedPhotoFilenames = [];
    if (isset($_POST['deleted_photos_ev8'])) {
        $deletedPhotoFilenames = json_decode($_POST['deleted_photos_ev8'], true) ?? [];
    }

    // กรองรูปเก่า + ลบ BLOB จาก DB
    $keptPhotos = [];
    foreach ($existingPhotos as $photo) {
        $shouldDelete = false;

        if (!empty($photo['file_id']) && in_array((int) $photo['file_id'], array_map('intval', $deletedPhotoFileIds))) {
            deleteBlobFromDb($pdo, (int) $photo['file_id']);
            $shouldDelete = true;
        } elseif (!empty($photo['filename']) && in_array($photo['filename'], $deletedPhotoFilenames)) {
            $legacyPath = __DIR__ . '/../../uploads/checklist_photos/' . $photo['filename'];
            if (file_exists($legacyPath)) {
                @unlink($legacyPath);
            }
            $shouldDelete = true;
        }

        if (!$shouldDelete) {
            $keptPhotos[] = [
                'file_id' => $photo['file_id'] ?? null,
                'filename' => $photo['filename'] ?? null,
                'original_name' => $photo['original_name'] ?? null
            ];
        }
    }
    $checklistData['photos'] = array_merge($keptPhotos, $newPhotos);

    $jsonData = json_encode($checklistData, JSON_UNESCAPED_UNICODE);

    if ($oldRow) {
        $sql = "UPDATE incident_checklist_transaction SET
                    incident_checklist_data = :data, incident_report_data = :report_data, edit_by = :edit_by, edit_date = NOW(), count_edit = count_edit + 1
                WHERE incident_id = :incident_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':data' => $jsonData, ':report_data' => $jsonData, ':edit_by' => $userId, ':incident_id' => $incidentId]);
        $recordId = $oldRow['id'];
        $action = 'updated';
    } else {
        $sql = "INSERT INTO incident_checklist_transaction
                (incident_id, incident_checklist_data, incident_report_data, create_by, create_date)
                VALUES (:incident_id, :data, :report_data, :create_by, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':incident_id' => $incidentId, ':data' => $jsonData, ':report_data' => $jsonData, ':create_by' => $userId]);
        $recordId = $pdo->lastInsertId();
        $action = 'created';
    }

    $pdo->prepare("UPDATE rn_ReceiveNoti SET statusChecklist = 1 WHERE id = ?")->execute([$incidentId]);

    $pdo->commit();

    jsonResponse(true, 'บันทึกข้อมูลเรียบร้อยแล้ว', [
        'id' => $recordId,
        'incident_id' => $incidentId,
        'action' => $action,
        'photos_saved' => count($checklistData['photos'])
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    jsonResponse(false, 'เกิดข้อผิดพลาดฐานข้อมูล: ' . $e->getMessage(), null, 500);
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage(), null, 500);
}
