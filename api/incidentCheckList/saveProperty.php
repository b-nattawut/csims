<?php
/**
 * API: Save Property Incident Checklist Data
 * ตาราง: incident_checklist_transaction
 * 
 * บันทึกรูปแบบ BLOB เหมือน saveFire.php
 * - ลายเซ็น/แผนผัง → BLOB ใน incident_checklist_transaction_file
 * - รูปภาพ → BLOB ใน incident_checklist_transaction_file
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

// ตรวจสอบ required fields
$incidentId = null;
if (isset($data['incident_id']) && !empty($data['incident_id'])) {
    $incidentId = (int) $data['incident_id'];
} elseif (isset($data['general_info']['doc_no']) && !empty($data['general_info']['doc_no'])) {
    $docNo = $data['general_info']['doc_no'];
    $findStmt = $pdo->prepare("SELECT id FROM rn_ReceiveNoti WHERE receiveNoti_No = :doc_no LIMIT 1");
    $findStmt->execute([':doc_no' => $docNo]);
    $row = $findStmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $incidentId = (int) $row['id'];
    }
}

if (!$incidentId) {
    jsonResponse(false, 'Missing required field: incident_id', null, 400);
}

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

    // ดึง existing file_ids ของ signatures จาก JSON เดิม
    $existingSigFileIds = [];
    if ($oldData && isset($oldData['signatures'])) {
        foreach ($oldData['signatures'] as $sKey => $sVal) {
            if (!empty($sVal['file_id'])) {
                $existingSigFileIds[$sKey] = (int) $sVal['file_id'];
            }
        }
    }

    // ============================================
    // 1. เตรียมข้อมูลสำหรับบันทึก (ครบทุก field)
    // ============================================

    $checklistData = [
        'general_info'          => $data['general_info'] ?? [],
        'scene_characteristics' => $data['scene_characteristics'] ?? [],
        'case_behavior_info'    => $data['case_behavior_info'] ?? [],
        'inspectors'            => $data['inspectors'] ?? [],
        'trace_points'          => $data['trace_points'] ?? [],
        'evidences'             => $data['evidences'] ?? [],
        'stolen_property'       => $data['stolen_property'] ?? '',
        'handover'              => $data['handover'] ?? [],
        'attachments_meta'      => $data['attachments_meta'] ?? [],
        'recorder_info'         => $data['recorder_info'] ?? [],
        'final_check'           => $data['final_check'] ?? [],
        'signatures'            => [],
        'photos'                => []
    ];

    // ============================================
    // ★ Section 10: บันทึกการตรวจเก็บวัตถุพยาน (measurements)
    // ============================================
    $measurementsProp = [];

    // Format ใหม่ — ส่งเป็น array ใน payload (จาก JS: payload.measurements)
    if (isset($data['measurements']) && is_array($data['measurements']) && !empty($data['measurements'])) {
        foreach ($data['measurements'] as $m) {
            $item = trim($m['item'] ?? '');
            // อนุญาตให้ว่างได้ ถ้ามีฟิลด์อื่นกรอก
            $hasOther = !empty($m['quantity']) || !empty($m['area']) || !empty($m['label_number']) || !empty($m['remark']);
            if (empty($item) && !$hasOther) continue;

            $measurementsProp[] = [
                'item' => $item,
                'quantity' => $m['quantity'] ?? '',
                'area' => $m['area'] ?? '',
                'label_number' => $m['label_number'] ?? '',
                'remark' => $m['remark'] ?? '',
                'package_plastic' => !empty($m['package_plastic']),
                'package_plastic_text' => $m['package_plastic_text'] ?? '',
                'package_paper' => !empty($m['package_paper']),
                'package_paper_text' => $m['package_paper_text'] ?? '',
                'package_other' => !empty($m['package_other']),
                'package_other_text' => $m['package_other_text'] ?? '',
                'action_return' => !empty($m['action_return']),
                'action_return_text' => $m['action_return_text'] ?? '',
                'action_other' => !empty($m['action_other']),
                'action_other_text' => $m['action_other_text'] ?? '',
                'forensic_unit' => $m['forensic_unit'] ?? ''
            ];
        }
    }

    // Fallback: รับจาก measurement_item_property[] (POST-style format)
    if (empty($measurementsProp) && isset($data['measurement_item_property']) && is_array($data['measurement_item_property'])) {
        $itemCount = count($data['measurement_item_property']);
        for ($i = 0; $i < $itemCount; $i++) {
            $item = $data['measurement_item_property'][$i] ?? '';
            if (empty(trim($item))) continue;

            $pkgPlastic = !empty($data["measurement_package_plastic_check_prop_{$i}"]);
            $pkgPlasticText = $data["measurement_package_plastic_text_prop_{$i}"] ?? '';
            $pkgPaper = !empty($data["measurement_package_paper_check_prop_{$i}"]);
            $pkgPaperText = $data["measurement_package_paper_text_prop_{$i}"] ?? '';
            $pkgOther = !empty($data["measurement_package_other_check_prop_{$i}"]);
            $pkgOtherText = $data["measurement_package_other_text_prop_{$i}"] ?? '';

            $actReturn = !empty($data["measurement_action_return_check_prop_{$i}"]);
            $actReturnText = $data["measurement_action_return_text_prop_{$i}"] ?? '';
            $actOther = !empty($data["measurement_action_other_check_prop_{$i}"]);
            $actOtherText = $data["measurement_action_other_text_prop_{$i}"] ?? '';

            $measurementsProp[] = [
                'item' => $item,
                'quantity' => $data['measurement_quantity_property'][$i] ?? '',
                'area' => $data['measurement_area_property'][$i] ?? '',
                'label_number' => $data['measurement_label_number_property'][$i] ?? '',
                'remark' => $data['measurement_remark_property'][$i] ?? '',
                'package_plastic' => $pkgPlastic,
                'package_plastic_text' => $pkgPlasticText,
                'package_paper' => $pkgPaper,
                'package_paper_text' => $pkgPaperText,
                'package_other' => $pkgOther,
                'package_other_text' => $pkgOtherText,
                'action_return' => $actReturn,
                'action_return_text' => $actReturnText,
                'action_other' => $actOther,
                'action_other_text' => $actOtherText,
                'forensic_unit' => $data['measurement_forensic_unit_property'][$i] ?? ''
            ];
        }
    }

    // เก็บลง checklistData (รักษา structure แบบเดียวกับ saveLife)
    $checklistData['measurements'] = $measurementsProp;
    $checklistData['measurement_meta'] = [
        'inspection_date' => $data['measurement_inspection_date_property'] ?? ($data['measurement_meta']['inspection_date'] ?? ''),
        'recorder' => $data['measurement_recorder_property'] ?? ($data['measurement_meta']['recorder'] ?? ''),
        'datetime' => $data['measurement_datetime_property'] ?? ($data['measurement_meta']['datetime'] ?? '')
    ];

    // ★ สร้าง blood_stain จาก evidences._summary_only (blood)
    $bloodEvidence = null;
    if (!empty($checklistData['evidences'])) {
        foreach ($checklistData['evidences'] as $ev) {
            if (!empty($ev['_summary_only']) && ($ev['type'] ?? '') === 'blood') {
                $bloodEvidence = $ev;
                break;
            }
        }
    }
    if ($bloodEvidence) {
        $checklistData['blood_stain'] = [
            'status' => 'found',
            'detail' => $bloodEvidence['detail'] ?? '',
            'blood_test' => $bloodEvidence['blood_test'] ?? []
        ];
    }

    // ============================================
    // 2. ลบ base64 ขนาดใหญ่ออกจาก JSON (เพราะเก็บเป็น BLOB แทน)
    // ============================================
    if (isset($checklistData['handover']['receiver_sig'])) {
        $checklistData['handover']['receiver_sig'] = '';
    }
    if (isset($checklistData['handover']['deliverer_sig'])) {
        $checklistData['handover']['deliverer_sig'] = '';
    }
    if (isset($checklistData['attachments_meta']['sketch'])) {
        $checklistData['attachments_meta']['sketch'] = '';
    }

    // ============================================
    // 3. ลายเซ็น / แผนผัง — บันทึกเป็น BLOB ใน incident_checklist_transaction_file
    // ============================================
    $sigFileFields = [
        'scene_sketch'       => 'sig_file_scene_sketch',
        'receiver_signature' => 'sig_file_receiver_signature',
        'sender_signature'   => 'sig_file_sender_signature'
    ];

    foreach ($sigFileFields as $key => $fieldName) {
        if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
            $blobData = file_get_contents($_FILES[$fieldName]['tmp_name']);
            if ($blobData !== false && strlen($blobData) > 0) {
                // ลบ BLOB เก่าจาก DB
                if (!empty($existingSigFileIds[$key])) {
                    deleteBlobFromDb($pdo, $existingSigFileIds[$key]);
                }
                // บันทึก BLOB ใหม่
                $newFileId = saveBlobToDb($pdo, $incidentId, $blobData);
                $checklistData['signatures'][$key] = ['file_id' => $newFileId];
            }
        }
    }

    // ★ รักษาลายเซ็นเดิมที่บันทึกใน DB ไว้แล้ว (ถ้าไม่ได้วาดใหม่)
    if ($oldData && isset($oldData['signatures'])) {
        foreach ($oldData['signatures'] as $sigType => $sigData) {
            if (empty($checklistData['signatures'][$sigType]) && !empty($sigData['file_id'])) {
                $checklistData['signatures'][$sigType] = $sigData;

                // Normalize to standard F-CS-11 keys (receiver_sig / deliverer_sig)
                if (!empty($checklistData['signatures']['receiver_signature'])) {
                    $checklistData['signatures']['receiver_sig'] = $checklistData['signatures']['receiver_signature'];
                }
                if (!empty($checklistData['signatures']['sender_signature'])) {
                    $checklistData['signatures']['deliverer_sig'] = $checklistData['signatures']['sender_signature'];
                }
            }
        }
    }

    // ============================================
    // 4. รูปภาพ — บันทึกเป็น BLOB ใน incident_checklist_transaction_file
    // ============================================
    $newPhotos = [];
    $imageFields = ['incident_photos', 'camera_input'];
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
        $deletedPhotoFileIds = json_decode($_POST['deleted_photo_file_ids'], true) ?? [];
    } elseif (isset($data['deleted_photo_file_ids']) && is_array($data['deleted_photo_file_ids'])) {
        $deletedPhotoFileIds = $data['deleted_photo_file_ids'];
    }

    // รับรายการรูปเก่าที่เก็บเป็นไฟล์บนดิสก์ที่ต้องการลบ (migration จาก save.php เดิม)
    $deletedFilenames = [];
    if (isset($_POST['deleted_photos'])) {
        $deletedFilenames = json_decode($_POST['deleted_photos'], true) ?? [];
    }

    // กรองรูปเก่าที่ไม่ถูกลบ + ลบ BLOB / ไฟล์จาก DB/ดิสก์
    $keptPhotos = [];
    $uploadPathForDelete = __DIR__ . '/../../uploads/checklist_photos';
    foreach ($existingPhotos as $photo) {
        $fid = $photo['file_id'] ?? 0;
        $fname = $photo['filename'] ?? '';

        if ($fid && in_array($fid, $deletedPhotoFileIds)) {
            // BLOB ที่ต้องลบ
            deleteBlobFromDb($pdo, $fid);
        } elseif ($fname && in_array($fname, $deletedFilenames)) {
            // ไฟล์บนดิสก์ที่ต้องลบ (จาก save.php เดิม)
            $filePath = $uploadPathForDelete . '/' . $fname;
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        } else {
            $keptPhotos[] = $photo;
        }
    }

    // รวมรูปเก่าที่เหลือ + รูปใหม่ (BLOB)
    $checklistData['photos'] = array_merge($keptPhotos, $newPhotos);

    // ★ รักษาข้อมูลจากรอบเก่าที่ save.php ไม่ได้ส่งมา
    if ($oldData) {
        // หมายเหตุ: 'measurements' และ 'measurement_meta' ถูกประมวลผลใหม่ใน block ด้านบนแล้ว
        // ดังนั้นไม่ต้องรักษาจากรอบเก่า (overwrite ด้วยค่าใหม่ได้เลย แม้ว่าจะ empty)
        // แต่ถ้า save round นี้ไม่ส่ง measurements มาเลย (เช่น save จาก endpoint เดิมที่ยังไม่อัพเดท) → keep old
        if (empty($checklistData['measurements']) && !empty($oldData['measurements'])) {
            // ตรวจสอบว่า round นี้มีฟิลด์ measurement หรือไม่ (ถ้าไม่มีให้ keep)
            $hasNewMeasurementInput = isset($data['measurements']) || isset($data['measurement_item_property']);
            if (!$hasNewMeasurementInput) {
                $checklistData['measurements'] = $oldData['measurements'];
            }
        }
        if (empty(array_filter($checklistData['measurement_meta'])) && !empty($oldData['measurement_meta'])) {
            $hasNewMeta = !empty($data['measurement_inspection_date_property'])
                || !empty($data['measurement_recorder_property'])
                || !empty($data['measurement_datetime_property'])
                || !empty($data['measurement_meta']);
            if (!$hasNewMeta) {
                $checklistData['measurement_meta'] = $oldData['measurement_meta'];
            }
        }

        $preserveKeys = ['summary', 'opinion', 'remark', 'evidence_meta', 'photo_records', 'blood_stain'];
        foreach ($preserveKeys as $pKey) {
            if (!isset($checklistData[$pKey]) && isset($oldData[$pKey])) {
                $checklistData[$pKey] = $oldData[$pKey];
            }
        }
        // รักษา blood_stain จากรอบเก่าถ้าไม่มีข้อมูล blood ใหม่
        if (empty($checklistData['blood_stain']) && !empty($oldData['blood_stain'])) {
            $checklistData['blood_stain'] = $oldData['blood_stain'];
        }
    }

    // ============================================
    // 5. บันทึก DB
    // ============================================
    $json = json_encode($checklistData, JSON_UNESCAPED_UNICODE);

    if ($oldRow) {
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

    // อัปเดตสถานะ Checklist
    $pdo->prepare("UPDATE rn_ReceiveNoti SET statusChecklist = 1 WHERE id = ?")->execute([$incidentId]);

    $pdo->commit();

    jsonResponse(true, 'บันทึกข้อมูลเรียบร้อยแล้ว', [
        'incident_id' => $incidentId,
        'photos_count' => count($checklistData['photos']),
        'signatures_count' => count($checklistData['signatures'])
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Property Checklist Save Error: " . $e->getMessage());
    jsonResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage());
}
