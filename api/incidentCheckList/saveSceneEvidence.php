<?php
/**
 * API: Save Scene Evidence Checklist Data (ตรวจเก็บวัตถุพยานที่เกิดเหตุ)
 * complaints_type = '07'
 * ตาราง: incident_checklist_transaction
 */

ini_set('memory_limit', '256M');
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_config.php';
require_once __DIR__ . '/lab_unit_helper.php';

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

function buildMergedLocation($data) {
    $ev7 = trim((string)($data['ev7_incident_location'] ?? ''));
    if ($ev7 !== '') return $ev7;
    $line1 = trim((string)($data['sevpf_incident_location'] ?? ''));
    $line2 = trim((string)($data['sevpf_incident_location_2'] ?? ''));
    return trim($line1 . ' ' . $line2);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed', null, 405);
}

function ev7AsList($value): array
{
    if ($value === null || $value === '') return [];
    if (!is_array($value)) return [$value];
    return array_values($value);
}

function ev7AsText($value): string
{
    if ($value === null || $value === false) return '';
    if (is_array($value)) {
        if (isset($value['description']) || isset($value['detail']) || isset($value['item'])) {
            return trim((string)($value['description'] ?? $value['detail'] ?? $value['item'] ?? ''));
        }
        $parts = [];
        foreach ($value as $item) {
            $t = ev7AsText($item);
            if ($t !== '') $parts[] = $t;
        }
        return implode(', ', $parts);
    }
    return trim((string)$value);
}

// รับข้อมูล — ถ้ามี payload_json ให้ใช้เป็นหลัก (array ของรายการของกลาง/กลุ่มงานไม่ถูกแบนเป็น string)
$data = $_POST;
if (!empty($_POST['payload_json'])) {
    $decodedPayload = json_decode($_POST['payload_json'], true);
    if (is_array($decodedPayload)) {
        $data = array_merge($data, $decodedPayload);
    }
}
$mergedIncidentLocation = buildMergedLocation($data);

// ค้นหา incident_id
$incidentId = null;
if (!empty($data['receiveNoti_id_ev7'])) {
    $incidentId = (int) $data['receiveNoti_id_ev7'];
}
if (!$incidentId && !empty($data['doc_no_ev7'])) {
    $stmt = $pdo->prepare("SELECT id FROM rn_ReceiveNoti WHERE receiveNoti_No = ? LIMIT 1");
    $stmt->execute([$data['doc_no_ev7']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $incidentId = (int) $row['id'];
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
        if ($oldData !== null) $existingPhotos = $oldData['photos'] ?? [];
    }

    // --- สร้างโครงสร้างข้อมูล ---
    $checklistData = [
        'general_info' => [
            'case_type' => 'scene_evidence',
            'doc_no' => $data['doc_no_ev7'] ?? '',
            'report_no' => $data['report_no_ev7'] ?? ($data['sevpf_report_no'] ?? ''),
            'receive_date' => $data['ev7_receive_date'] ?? ($data['sevpf_receive_date'] ?? ''),
            'receive_time' => $data['ev7_receive_time'] ?? ($data['sevpf_receive_time'] ?? ''),
            'unit_type' => $data['ev7_unit_type'] ?? '',
            'unit_name' => $data['ev7_unit_name'] ?? ($data['sevpf_unit_name'] ?? ''),
            'notify_method' => $data['ev7_notify_method'] ?? ($data['sevpf_notify_method'] ?? []),
            'notify_method_other_text' => $data['ev7_notify_method_other_text'] ?? '',
            'police_station' => $data['ev7_police_station'] ?? ($data['sevpf_police_station'] ?? ''),
            'document_no' => $data['ev7_document_no'] ?? ($data['sevpf_document_no'] ?? ''),
            'document_date' => $data['ev7_document_date'] ?? ($data['sevpf_document_date'] ?? ''),
            'case_no' => $data['ev7_case_no'] ?? ($data['sevpf_case_no'] ?? ''),
            'incident_location' => $mergedIncidentLocation,
            'incident_date' => $data['ev7_incident_date'] ?? ($data['sevpf_incident_date'] ?? ''),
            'incident_time' => $data['ev7_incident_time'] ?? ($data['sevpf_incident_time'] ?? ''),
            'investigator_name' => $data['ev7_investigator_name'] ?? ($data['sevpf_investigator_name'] ?? ''),
            'send_request' => $data['ev7_send_request'] ?? ($data['sevpf_send_request'] ?? ''),
            // สำหรับ gen_pdf_evidence_html.php
            'report_date' => $data['ev7_receive_date'] ?? ($data['sevpf_receive_date'] ?? ''),
            'report_time' => $data['ev7_receive_time'] ?? ($data['sevpf_receive_time'] ?? ''),
            'location_detail' => $mergedIncidentLocation,
            'incident_datetime' => (!empty($data['ev7_incident_date']) ? ($data['ev7_incident_date'] . 'T' . ($data['ev7_incident_time'] ?? '00:00')) : ''),
            'inspection_datetime' => (!empty($data['ev7_inspect_date']) ? ($data['ev7_inspect_date'] . 'T' . ($data['ev7_inspect_time'] ?? '00:00')) : ''),
            'source_station' => $data['ev7_police_station'] ?? ($data['sevpf_police_station'] ?? ''),
            'inspectors' => [],
        ],
        'evidence_items' => [],
        'purpose' => [
            'types' => $data['ev7_purpose'] ?? ($data['sevpf_purpose'] ?? []),
            'detail' => $data['ev7_purpose_detail'] ?? ($data['sevpf_purpose_detail'] ?? ''),
        ],
        'inspection' => [
            'location' => $data['ev7_inspect_location'] ?? ($data['sevpf_inspect_location'] ?? ''),
            'date' => $data['ev7_inspect_date'] ?? ($data['sevpf_inspect_date'] ?? ''),
            'time' => $data['ev7_inspect_time'] ?? ($data['sevpf_inspect_time'] ?? ''),
        ],
        'exhibit_descriptions' => [],
        'collected_evidence' => [
            'types' => $data['ev7_collect_type'] ?? ($data['sevpf_collect_type'] ?? []),
            'sheet_count' => $data['ev7_collect_sheet_count'] ?? ($data['sevpf_collect_sheet_count'] ?? ''),
            'location' => $data['ev7_collect_location'] ?? ($data['sevpf_collect_location'] ?? ''),
            'details' => [],
            'other_evidence' => [],
            'other_evidence_text' => $data['sevpf_other_evidence_text'] ?? '',
            'lab_units' => [],
        ],
        'evidence_handling' => [
            'witness_name' => $data['ev7_witness_name'] ?? ($data['sevpf_witness_name'] ?? ''),
            'witness_form' => $data['ev7_witness_form'] ?? ($data['sevpf_witness_form'] ?? ''),
            'witness_detail' => $data['sevpf_witness_detail'] ?? '',
            'handover_method' => $data['ev7_handover_method'] ?? '',
            'handover_method_checks' => $data['sevpf_handover_method_check'] ?? [],
            'handover_method_detail' => $data['sevpf_handover_method_detail'] ?? '',
            'handover_item_ref' => $data['ev7_handover_item_ref'] ?? ($data['sevpf_handover_item_ref'] ?? ''),
            'handover_to' => $data['ev7_handover_to'] ?? ($data['sevpf_handover_to'] ?? ''),
            'handover_purpose' => $data['ev7_handover_purpose'] ?? ($data['sevpf_handover_purpose'] ?? ''),
            'next_action' => $data['sevpf_handover_next_action'] ?? '',
            'next_action_full' => $data['sevpf_handover_next_action_full'] ?? '',
            'next_action_lines' => $data['sevpf_handover_more_lines'] ?? [],
        ],
        'handover' => [
            'receiver_id' => $data['sevpf_receiver_id'] ?? '',
            'receiver_name' => $data['sevpf_receiver_name'] ?? ($data['sevpf_receiver_id'] ?? ''),
            'receiver_pos' => $data['sevpf_receiver_position'] ?? '',
            'receiver_position' => $data['sevpf_receiver_position'] ?? '',
            'deliverer_id' => $data['sevpf_sender_id'] ?? '',
            'sender_name' => $data['sevpf_sender_name'] ?? ($data['sevpf_sender_id'] ?? ''),
            'deliverer_pos' => $data['sevpf_sender_position'] ?? '',
            'sender_position' => $data['sevpf_sender_position'] ?? '',
            'inspection_end_date' => $data['ev7_sign_date'] ?? '',
            'inspection_end_time' => $data['ev7_inspect_time'] ?? ($data['sevpf_receive_time'] ?? ''),
        ],
        'inspectors' => $data['ev7_inspector_id'] ?? [],
        'signer' => [
            'id' => $data['ev7_signer_id'] ?? '',
            'position' => $data['ev7_signer_position'] ?? ($data['sevpf_signer_position'] ?? ''),
            'name' => $data['sevpf_signer_name'] ?? '',
            'fullname' => $data['sevpf_signer_fullname'] ?? '',
            'date' => $data['ev7_sign_date'] ?? '',
        ],
        'photo_records' => [
            'start' => $data['ev7_photo_id_start'] ?? '',
            'end' => $data['ev7_photo_id_end'] ?? '',
            'amount' => $data['ev7_photo_amount'] ?? '',
        ],
        'pdf_form' => [
            'report_ref' => $data['sevpf_report_ref'] ?? '',
            'report_year' => $data['sevpf_report_year'] ?? '',
            'unit_type_checks' => $data['sevpf_unit_type_check'] ?? [],
            'center_name' => $data['sevpf_center_name'] ?? '',
            'province_name' => $data['sevpf_province_name'] ?? '',
            'sign_day' => $data['sevpf_sign_day'] ?? '',
            'sign_month' => $data['sevpf_sign_month'] ?? '',
            'sign_year' => $data['sevpf_sign_year'] ?? '',
        ],
        'signatures' => [],
        'photos' => [],
        'evidences' => [],
    ];

    // evidence_items — รับได้ทั้ง string และ array ต่อแถว (กลุ่มงานหลายค่า)
    $ev_items = ev7AsList($data['ev7_evidence_item'] ?? ($data['sevpf_evidence_item'] ?? []));
    $ev_lab_units_raw = ev7AsList($data['ev7_lab_unit'] ?? ($data['sevpf_lab_unit'] ?? []));
    $ev_rows = ev7AsList($data['ev7_evidence_rows'] ?? []);
    if ($ev_rows) {
        foreach ($ev_rows as $row) {
            if (!is_array($row)) {
                $detail = ev7AsText($row);
                $lu = [];
            } else {
                $detail = ev7AsText($row['description'] ?? $row['detail'] ?? $row['item'] ?? '');
                $lu = labUnitsNormalize($row['lab_unit'] ?? ($row['lab_units'] ?? []));
            }
            if ($detail === '') continue;
            $checklistData['evidence_items'][] = ['description' => $detail];
            $checklistData['evidences'][] = ['detail' => $detail, 'item' => $detail, 'lab_unit' => $lu];
            $checklistData['collected_evidence']['lab_units'][] = $lu;
        }
    } else {
        foreach ($ev_items as $idx => $item) {
            $detail = ev7AsText($item);
            if ($detail === '') continue;
            $lu = labUnitsNormalize($ev_lab_units_raw[$idx] ?? (is_array($item) ? ($item['lab_unit'] ?? []) : []));
            $checklistData['evidence_items'][] = ['description' => $detail];
            $checklistData['evidences'][] = ['detail' => $detail, 'item' => $detail, 'lab_unit' => $lu];
            $checklistData['collected_evidence']['lab_units'][] = $lu;
        }
    }

    // exhibit_descriptions
    $ex_descs = ev7AsList($data['ev7_exhibit_desc'] ?? ($data['sevpf_exhibit_desc'] ?? []));
    foreach ($ex_descs as $desc) {
        $text = ev7AsText($desc);
        if ($text !== '') $checklistData['exhibit_descriptions'][] = ['description' => $text];
    }

    // collect details
    $col_details = ev7AsList($data['ev7_collect_detail'] ?? ($data['sevpf_collect_detail'] ?? []));
    foreach ($col_details as $d) {
        $text = ev7AsText($d);
        if ($text !== '') $checklistData['collected_evidence']['details'][] = $text;
    }

    // other evidence
    $other_ev = ev7AsList($data['ev7_other_evidence'] ?? []);
    foreach ($other_ev as $oe) {
        $text = ev7AsText($oe);
        if ($text !== '') $checklistData['collected_evidence']['other_evidence'][] = $text;
    }

    // ถ้ายังไม่มี lab_units จากแถวของกลาง ให้เก็บจากฟิลด์รวม (รักษา index)
    if (empty($checklistData['collected_evidence']['lab_units']) && $ev_lab_units_raw) {
        foreach ($ev_lab_units_raw as $lu) {
            $checklistData['collected_evidence']['lab_units'][] = labUnitsNormalize($lu);
        }
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

    // --- ลายเซ็น ---
    $senderSignaturePresent = isset($data['ev7_sender_signature_present']) ? (string)$data['ev7_sender_signature_present'] : null;
    $receiverSignaturePresent = isset($data['ev7_receiver_signature_present']) ? (string)$data['ev7_receiver_signature_present'] : null;
    $senderSigCleared = ($senderSignaturePresent === '0') || (isset($data['sevpf_sender_sig_cleared']) && (string)$data['sevpf_sender_sig_cleared'] === '1');
    $receiverSigCleared = ($receiverSignaturePresent === '0') || (isset($data['sevpf_receiver_sig_cleared']) && (string)$data['sevpf_receiver_sig_cleared'] === '1');

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
        'signer_sig' => $data['ev7_signer_signature_data'] ?? '',
        'receiver_sig' => $data['sevpf_receiver_signature_data'] ?? '',
        'deliverer_sig' => $data['sevpf_sender_signature_data'] ?? '',
    ];

    foreach ($sigMap as $key => $sigBase64) {
        $cleared = false;
        if ($key === 'receiver_sig') $cleared = $receiverSigCleared;
        if ($key === 'deliverer_sig' || $key === 'signer_sig') $cleared = $senderSigCleared;

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

    // --- ภาพถ่าย (BLOB ใน DB) ---
    $newPhotos = [];
    if (isset($_FILES['incident_photos_ev7']) && !empty($_FILES['incident_photos_ev7']['name'][0])) {
        $photos = $_FILES['incident_photos_ev7'];
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
                    $newPhotos[] = ['file_id' => $newFileId, 'filename' => $fileName];
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
    if (isset($_POST['deleted_photos_ev7'])) {
        $deletedPhotoFilenames = json_decode($_POST['deleted_photos_ev7'], true) ?? [];
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
            $keptPhotos[] = $photo;
        }
    }
    $checklistData['photos'] = array_merge($keptPhotos, $newPhotos);

    // --- บันทึกลง DB ---
    $jsonData = json_encode($checklistData, JSON_UNESCAPED_UNICODE);

    if ($oldRow) {
        $sql = "UPDATE incident_checklist_transaction SET
                    incident_checklist_data = :data, incident_report_data = :report_data, edit_by = :edit_by, edit_date = NOW(), count_edit = count_edit + 1, count_report = GREATEST(COALESCE(count_report, 0), 1)
                WHERE incident_id = :incident_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':data' => $jsonData, ':report_data' => $jsonData, ':edit_by' => $userId, ':incident_id' => $incidentId]);
        $recordId = $oldRow['id'];
        $action = 'updated';
    } else {
        $sql = "INSERT INTO incident_checklist_transaction
                (incident_id, incident_checklist_data, incident_report_data, create_by, create_date, count_report)
                VALUES (:incident_id, :data, :report_data, :create_by, NOW(), 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':incident_id' => $incidentId, ':data' => $jsonData, ':report_data' => $jsonData, ':create_by' => $userId]);
        $recordId = $pdo->lastInsertId();
        $action = 'created';
    }

    // อัปเดตสถานะ
    $pdo->prepare("UPDATE rn_ReceiveNoti SET statusChecklist = 1 WHERE id = ?")->execute([$incidentId]);

    $pdo->commit();

    jsonResponse(true, 'บันทึกข้อมูลเรียบร้อยแล้ว', [
        'id' => $recordId, 'incident_id' => $incidentId, 'action' => $action,
        'photos_saved' => count($checklistData['photos'])
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    jsonResponse(false, 'เกิดข้อผิดพลาดฐานข้อมูล: ' . $e->getMessage(), null, 500);
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage(), null, 500);
}
