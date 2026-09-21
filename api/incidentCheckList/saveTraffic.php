<?php

/**
 * API: Save Traffic Incident Checklist Data (v5.0 Final)
 * ตาราง: incident_checklist_transaction
 */

ini_set('memory_limit', '256M');
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_config.php';
require_once __DIR__ . '/lab_unit_helper.php';

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

function saveBase64ToFile($base64Data, $folder, $filename)
{
    $uploadPath = __DIR__ . '/../../uploads_2/' . $folder;
    if (!is_dir($uploadPath)) mkdir($uploadPath, 0775, true);

    if (strpos($base64Data, ',') !== false) {
        list(, $base64Data) = explode(',', $base64Data, 2);
    }
    $imageData = base64_decode($base64Data);
    if ($imageData === false) return false;

    $filePath = $uploadPath . '/' . $filename;
    return (file_put_contents($filePath, $imageData) !== false) ? $filename : false;
}

function generateSignatureFilename($incidentId, $type)
{
    $timestamp = date('YmdHis');
    return "{$incidentId}_traffic_{$type}_{$timestamp}_" . substr(md5(uniqid()), 0, 4) . ".png";
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

// ============================================
// Main Logic
// ============================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed', null, 405);
}

$data = null;

if (isset($_POST['payload'])) {
    // กรณี FormData - payload อยู่ใน $_POST['payload'] เป็น JSON string
    $data = json_decode($_POST['payload'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, 'Invalid JSON in payload: ' . json_last_error_msg(), null, 400);
    }
} else {
    // กรณี JSON โดยตรง หรือ FormData ปกติ
    $rawData = file_get_contents('php://input');
    if (!empty($rawData)) {
        $data = json_decode($rawData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // ถ้า decode ไม่ได้ ให้ใช้ $_POST
            $data = $_POST;
        }
    } else {
        // ใช้ $_POST โดยตรง
        $data = $_POST;
    }
}

// ตรวจสอบ required fields - incident_id อาจอยู่ใน receiveNoti_id
$incidentId = null;
if (isset($data['receiveNoti_id']) && !empty($data['receiveNoti_id'])) {
    $incidentId = (int) $data['receiveNoti_id'];
} elseif (isset($data['incident_id']) && !empty($data['incident_id'])) {
    $incidentId = (int) $data['incident_id'];
} elseif (isset($data['doc_no']) && !empty($data['doc_no'])) {
    // ถ้าไม่มี incident_id ให้ใช้ doc_no ค้นหา
    $docNo = $data['doc_no'];
    $findStmt = $pdo->prepare("SELECT id FROM rn_ReceiveNoti WHERE receiveNoti_No = :doc_no LIMIT 1");
    $findStmt->execute([':doc_no' => $docNo]);
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

    // การตรวจพิสูจน์ระดับคดี — เลือกได้หลายกลุ่มงาน (เก็บเป็น array)
    $trafficLabUnit = labUnitsNormalize($data['forensic_lab_unit'] ?? '');

    // --- ดึงข้อมูลเดิมเพื่อเตรียมลบไฟล์เก่า ---
    $stmtOld = $pdo->prepare("SELECT incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? LIMIT 1");
    $stmtOld->execute([$incidentId]);
    $oldRow = $stmtOld->fetch(PDO::FETCH_ASSOC);

    $filesToDelete = [];
    $existingPhotos = [];
    if ($oldRow) {
        $oldData = json_decode($oldRow['incident_checklist_data'], true);

        // เก็บรายชื่อไฟล์ลายเซ็นเก่า
        if (!empty($oldData['signatures'])) {
            foreach ($oldData['signatures'] as $sig) {
                if (!empty($sig['filename'])) $filesToDelete[] = ['folder' => 'checklist_traffic_signatures', 'file' => $sig['filename']];
            }
        }

        // เก็บรูปภาพเก่า (รองรับทั้ง file_id และ filename)
        if (!empty($oldData['photos'])) {
            $existingPhotos = $oldData['photos'];
            foreach ($oldData['photos'] as $photo) {
                if (!empty($photo['filename'])) $filesToDelete[] = ['folder' => 'checklist_traffic_photos', 'file' => $photo['filename']];
            }
        }
    }

    // จัดการข้อมูลรถของกลาง (Fieldset 2)
    $allVehiclesAtScene = [];

    if (isset($data['vehicle_detail']) && is_array($data['vehicle_detail'])) {
        foreach ($data['vehicle_detail'] as $index => $detail) {
            // 1. จัดการสถานะแผ่นป้ายทะเบียน 
            // เช็คว่าในลำดับ (Index) นี้มีการส่งค่า "ติด" หรือ "ไม่ติด" มาหรือไม่
            $plateStatus = '';
            if (isset($data['vehicle_plate_attach'][$index]) && $data['vehicle_plate_attach'][$index] === 'ติด') {
                $plateStatus = 'ติด';
            } elseif (isset($data['vehicle_plate_none'][$index]) && $data['vehicle_plate_none'][$index] === 'ไม่ติด') {
                $plateStatus = 'ไม่ติด';
            } else {
                $plateStatus = 'ไม่ระบุ'; // กรณีไม่ได้เลือกเลย
            }

            // 2. รวบรวมข้อมูลเข้าโครงสร้าง Array
            $allVehiclesAtScene[] = [
                'v_index'      => $index + 1,
                'detail'       => $detail,
                'brand'        => $data['vehicle_brand'][$index] ?? '',
                'model'        => $data['vehicle_model'][$index] ?? '',
                'color'        => $data['vehicle_color'][$index] ?? '',
                'plate_status' => $plateStatus,
                'plate_no'     => $data['vehicle_plate_no'][$index] ?? ''
            ];
        }
    }

    // จัดการข้อมูลผู้ตรวจพิสูจน์ (Fieldset 7)
    $allForensicOfficers = [];

    if (isset($data['forensic_id']) && is_array($data['forensic_id'])) {
        foreach ($data['forensic_id'] as $index => $empId) {
            // บันทึกเฉพาะแถวที่มีการเลือกรายชื่อพนักงาน
            if (!empty($empId)) {
                $allForensicOfficers[] = [
                    'user_id'  => $empId,
                    'position' => $data['forensic_position'][$index] ?? '' // ดึงตำแหน่งตาม Index เดียวกัน
                ];
            }
        }
    }

    // จัดการข้อมูลรถของกลางเพื่อตรวจพิสูจน์ (Fieldset 8)
    $allVehicleAnalysis = [];
    $vSides = ['front', 'left', 'right', 'back']; // กำหนดคีย์ของแต่ละด้าน

    if (isset($data['forensic_v_condition']) && is_array($data['forensic_v_condition'])) {
        foreach ($data['forensic_v_condition'] as $index => $condition) {
            // vIdx คือลำดับรถที่ใช้ในชื่อฟิลด์ (1, 2, 3...) 
            $vIdx = $index + 1;

            // --- 2. วนลูปเก็บร่องรอยแยกตามด้าน (หน้า/ซ้าย/ขวา/หลัง) ---
            $tracesBySide = [];
            foreach ($vSides as $side) {
                $sideRows = [];
                $detailKey = "trace_{$side}_detail_{$vIdx}";
                $heightKey = "trace_{$side}_height_{$vIdx}";

                // ตรวจสอบว่ามีการส่งข้อมูลร่องรอยของด้านนั้นๆ มาหรือไม่
                if (isset($data[$detailKey]) && is_array($data[$detailKey])) {
                    foreach ($data[$detailKey] as $rowIdx => $detail) {
                        // เก็บเฉพาะแถวที่มีการกรอกรายละเอียด
                        if (trim($detail) !== '') {
                            $sideRows[] = [
                                'detail' => $detail,
                                'height' => $data[$heightKey][$rowIdx] ?? '0.00'
                            ];
                        }
                    }
                }
                $tracesBySide[$side] = $sideRows; // เก็บ Array ร่องรอยเข้าสู่ด้านนั้นๆ
            }

            // --- 3. รวบรวมข้อมูลรถ 1 คัน พร้อมร่องรอยทั้งหมด ---
            $allVehicleAnalysis[] = [
                'v_index'    => $vIdx,
                'condition'  => $condition,
                'mod_status' => $data["forensic_v_mod_status_$vIdx"] ?? 'ไม่มี',
                'mod_detail' => $data['forensic_v_mod_detail'][$index] ?? '',
                'traces'     => $tracesBySide // ก้อนร่องรอย 4 ด้าน
            ];
        }
    }

    // จัดการข้อมูลผลการตรวจเปรียบเทียบ (Fieldset 9)
    $allComparisons = [];

    if (isset($data['compare_trace_type']) && is_array($data['compare_trace_type'])) {
        foreach ($data['compare_trace_type'] as $index => $traceType) {
            // ตรวจสอบว่ามีการกรอกข้อมูลอย่างน้อย 1 ช่องในแถวนั้นหรือไม่ เพื่อป้องกันการเก็บแถวว่าง
            $hasData = !empty($traceType) ||
                !empty($data['compare_area_a'][$index]) ||
                !empty($data['compare_ref_a'][$index]);

            if ($hasData) {
                $allComparisons[] = [
                    'item_no'    => "9." . ($index + 1),        // ลำดับ 9.1, 9.2...
                    'trace_type' => $traceType,                 // รอย (เช่น รอยบุบ)
                    'area_a'     => $data['compare_area_a'][$index] ?? '', // บริเวณ (เช่น กันชนหน้าขวา)
                    'ref_a'      => $data['compare_ref_a'][$index] ?? '',  // ตามผลการตรวจในข้อ (เช่น 8.1 ด้านหน้า)
                    'area_b'     => $data['compare_area_b'][$index] ?? '', // เข้ากันได้กับรอยครูดบริเวณ (เช่น ประตูหน้าซ้าย)
                    'ref_b'      => $data['compare_ref_b'][$index] ?? ''   // ตามผลการตรวจในข้อ (เช่น 8.2 ด้านซ้าย)
                ];
            }
        }
    }

    // โครงสร้างข้อมูลสำหรับบันทึก
    $checklistData = [
        'general_info' => [
            'report_no' => $data['report_no'] ?? '',
            'doc_no' => $data['doc_no'] ?? '',
            'case_type' => 'traffic',
            'case_type_other' => '',
            'case_doc_no' => $data['case_doc_traffic'] ?? '', // 1.การรับแจ้งเหตุ - คดี
            'case_date' => $data['case_date'] ?? '', // 1.การรับแจ้งเหตุ - วันที่
            'case_time' => $data['case_time'] ?? '', // 1.การรับแจ้งเหตุ - เวลา
            'report_datetime' => !empty($data['case_date'])
                ? ($data['case_date'] . 'T' . ($data['case_time'] ?? '00:00'))
                : '',
            'report_channel' => $data['notify_method'] ?? [], // 1.การรับแจ้งเหตุ - ช่องทางการรับแจ้ง (Array)
            'report_channel_other' => $data['notify_method_other_text'] ?? '', // 1.การรับแจ้งเหตุ - ช่องทางอื่นๆ
            'source_station' => $data['police_station'] ?? '', // 1.การรับแจ้งเหตุ - สภ./สน.
            'document_no' => $data['case_doc_traffic'] ?? '',
            'location_detail' => $data['crime_location'] ?? '',
            'incident_datetime' => !empty($data['victim_know_date'])
                ? ($data['victim_know_date'] . 'T' . ($data['victim_know_time'] ?? '00:00'))
                : '',
            'inspection_datetime' => !empty($data['inspect_date'])
                ? ($data['inspect_date'] . 'T' . ($data['inspect_time'] ?? '00:00'))
                : '',
            'investigator' => [
                'name' => $data['investigator_name'] ?? '', // 1.การรับแจ้งเหตุ - ชื่อพนักงานสอบสวน
                'firstname' => $data['investigator_name'] ?? '',
                'lastname' => '',
                'phone' => $data['investigator_phone'] ?? '' // 1.การรับแจ้งเหตุ - หมายเลขโทรศัพท์พนักงานสอบสวน
            ],
            'victim' => [
                'name' => $data['victim_name'] ?? '',
                'firstname' => $data['victim_name'] ?? '',
                'lastname' => '',
                'age' => $data['victim_age'] ?? ''
            ],

            'victim_know_date' => $data['victim_know_date'] ?? '', // 3.วันเวลาที่ทราบเหตุ/เกิดเหตุ - วันที่ผู้เสียหายทราบเหตุ/เกิดเหตุ
            'victim_know_time' => $data['victim_know_time'] ?? '', // 3.วันเวลาที่ทราบเหตุ/เกิดเหตุ - เวลาประมาณ
            'officer_know_date' => $data['officer_know_date'] ?? '', // 3.วันเวลาที่ทราบเหตุ/เกิดเหตุ - วันที่พนักงานสอบสวนทราบเหตุ
            'officer_know_time' => $data['officer_know_time'] ?? '', // 3.วันเวลาที่ทราบเหตุ/เกิดเหตุ - เวลาประมาณ

            'inspect_date' => $data['inspect_date'] ?? '', // 4.วันเวลาที่ตรวจเหตุ - วันที่ทำการตรวจสถานที่เกิดเหตุ
            'inspect_time' => $data['inspect_time'] ?? '', // 4.วันเวลาที่ตรวจเหตุ - เวลาประมาณ
        ],
        'scene_info' => [
            'crime_location' => $data['crime_location'] ?? '', // 2.สถานที่เกิดเหตุ - รายละเอียดสถานที่เกิดเหตุ
            'vehicles_at_scene' => $allVehiclesAtScene // 2.สถานที่เกิดเหตุ - ข้อมูลรถของกลาง
        ],
        'inspectors' => $data['inspector_id'] ?? [], // 5.ผู้ตรวจสถานที่เกิดเหตุ
        'evidences' => array_values(array_filter(array_map(function($cmp, $idx) {
            $traceType = trim((string)($cmp['trace_type'] ?? ''));
            $areaA = trim((string)($cmp['area_a'] ?? ''));
            $areaB = trim((string)($cmp['area_b'] ?? ''));
            $detail = trim($traceType . ' ' . $areaA . ' ' . $areaB);
            if ($detail === '') {
                return null;
            }
            return [
                'no' => $idx + 1,
                'detail' => $detail,
                'lab_unit' => $trafficLabUnit
            ];
        }, $allComparisons, array_keys($allComparisons)))),

        'inspection_purpose' => [
            'selected_purposes' => $data['inspect_purpose'] ?? [], // 6. จุดประสงค์ในการตรวจ - ( checkboxes 4 รายการ )
            'qty_1' => $data['purpose_qty_1'] ?? '', // 6. จุดประสงค์ในการตรวจ - จำนวนคันใน input field ของ checkbox ข้อ 1
            'qty_2' => $data['purpose_qty_2'] ?? '', // 6. จุดประสงค์ในการตรวจ - จำนวนคันใน input field ของ checkbox ข้อ 2
            'other_text' => $data['purpose_other_text'] ?? '' // 6. จุดประสงค์ในการตรวจ - input field รายละเอียดของ checkbox 4
        ],

        'forensic_officers' => $allForensicOfficers, // 7.ผู้ตรวจพิสูจน์ (วนลูปเก็บ forensic_id[] และ forensic_position[]) 

        'forensic_results' => [ // 8.ผลการตรวจพิสูจน์
            'case_behavior' => $data['case_behavior'] ?? '', // 8.ผลการตรวจพิสูจน์ - พฤติการณ์คดี
            'inspection_target' => $data['forensic_inspection_target'] ?? '', // 8.ผลการตรวจพิสูจน์ - สิ่งที่ทำการตรวจพิสูจน์
            'inspection_location' => $data['forensic_inspection_location'] ?? '', // 8.ผลการตรวจพิสูจน์ - สถานที่ตรวจ
            'lab_unit' => $trafficLabUnit,
            'inspection_date' => $data['forensic_inspect_date'] ?? '', // 8.ผลการตรวจพิสูจน์ - วันที่ทำการตรวจพิสูจน์
            'inspection_time' => $data['forensic_inspect_time'] ?? '', // 8.ผลการตรวจพิสูจน์ - เวลาประมาณ
            'vehicle_analysis' => $allVehicleAnalysis // 8.ผลการตรวจพิสูจน์ - รายการรถของกลางเพื่อตรวจพิสูจน์ (วนลูปเก็บเป็น Array ซ้อน Array)
        ],

        'comparison_results' => [
            'total_items' => $data['forensic_compare_v_count'] ?? '', // 9.ผลการตรวจเปรียบเทียบ - จากการตรวจเปรียบเทียบสภาพร่องรอยความเสียหาย...รายการ
            'comparisons' => $allComparisons // 9.ผลการตรวจเปรียบเทียบ - รายการ 9.1, 9.2... (วนลูปเก็บค่า compare_trace_type[] ฯลฯ) 
        ],

        'opinion' => $data['forensic_opinion'] ?? '', // 10.ความเห็น

        'handover' => [
            'inspection_end_date' => $data['inspection_end_date'] ?? '', // 11.การส่งมอบคืนสถานที่ - วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น ( วันที่ )
            'inspection_end_time' => $data['inspection_end_time'] ?? '', // 11.การส่งมอบคืนสถานที่ - เวลาประมาณ
            'receiver' => [
                'name_id' => $data['receiver_name'] ?? '', // 8. การส่งมอบสถานที่เกิดเหตุ - select - ชื่อผู้รับมอบ
                'position' => $data['receiver_position'] ?? '' // 8. การส่งมอบสถานที่เกิดเหตุ - ตำแหน่งผู้รับมอบ
            ],
            'sender' => [
                'name_id' => $data['sender_name'] ?? '', // 8. การส่งมอบสถานที่เกิดเหตุ - select - ชื่อผู้ส่งมอบ
                'position' => $data['sender_position'] ?? '' // 8. การส่งมอบสถานที่เกิดเหตุ - ตำแหน่งผู้ส่งมอบ
            ],
            'receiver_id' => $data['receiver_name'] ?? '',
            'receiver_pos' => $data['receiver_position'] ?? '',
            'deliverer_id' => $data['sender_name'] ?? '',
            'deliverer_pos' => $data['sender_position'] ?? ''
        ],

        'photo_records' => [
            'start' => $data['photo_id_start_traffic'] ?? '', // 12.บันทึกการถ่ายภาพ - รหัสภาพถ่ายที่
            'end' => $data['photo_id_end_traffic'] ?? '', // 12.บันทึกการถ่ายภาพ - ถึง
            'amount' => $data['photo_amount_traffic'] ?? '', // 12. บันทึกการถ่ายภาพ - จำนวนภาพ
            'recorder_name' => $data['photographer_name_traffic'] ?? '', // 12. บันทึกการถ่ายภาพ - ผู้บันทึก
            'recorder_datetime' => $data['photographer_datetime_traffic'] ?? '' // 12. บันทึกการถ่ายภาพ - วัน/เวลา
        ],

        'signatures' => [], // สำหรับเก็บชื่อไฟล์ลายเซ็น (receiver, sender)
        'photos' => [] // สำหรับเก็บรายชื่อไฟล์รูปภาพที่แนบ
    ];

    // 6. ลายเซ็น
    $sigTypes = [
        'receiver_sig' => 'receiver_signature_data_traffic',
        'sender_sig'   => 'sender_signature_data_traffic',
    ];

    foreach ($sigTypes as $key => $fieldName) {
        if (!empty($data[$fieldName])) {
            $filename = generateSignatureFilename($incidentId, $key);
            $saved = saveBase64ToFile($data[$fieldName], 'checklist_traffic_signatures', $filename);
            if ($saved) {
                $checklistData['signatures'][$key] = [
                    'filename' => $saved
                ];

                if ($key === 'sender_sig') {
                    $checklistData['signatures']['deliverer_sig'] = $checklistData['signatures'][$key];
                }
            }
        }
    }

    if (empty($checklistData['signatures']['deliverer_sig']) && !empty($checklistData['signatures']['sender_sig'])) {
        $checklistData['signatures']['deliverer_sig'] = $checklistData['signatures']['sender_sig'];
    }

    // 7. รูปภาพ — บันทึกเป็น BLOB ใน DB (เหมือน fingerprint)
    $newPhotos = [];
    $imageFields = ['incident_photos_traffic', 'camera_photos_traffic'];
    $captions = isset($_POST['photo_captions_traffic']) ? $_POST['photo_captions_traffic'] : [];

    // 7.1 จัดการไฟล์จาก $_FILES (ไฟล์ใหม่ที่เลือกจากเครื่องหรือถ่ายจากกล้อง)
    foreach ($imageFields as $field) {
        if (isset($_FILES[$field])) {
            $files = $_FILES[$field];
            $fileCount = is_array($files['name']) ? count($files['name']) : 1;
            for ($i = 0; $i < $fileCount; $i++) {
                $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
                if ($error === UPLOAD_ERR_OK) {
                    $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                    $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                    $blobData = file_get_contents($tmpName);
                    if ($blobData !== false && strlen($blobData) > 0) {
                        $blobData = compressImage($blobData, 2097152);
                        $newFileId = saveBlobToDb($pdo, $incidentId, $blobData);
                        $newPhotos[] = [
                            'file_id' => $newFileId,
                            'filename' => $fileName,
                            'original_name' => $fileName,
                            'caption' => $captions[$i] ?? ''
                        ];
                    }
                }
            }
        }
    }

    // 7.2 จัดการรูปภาพจาก $data (Base64 จากระบบเดิม)
    foreach ($imageFields as $field) {
        if (isset($data[$field]) && is_array($data[$field])) {
            foreach ($data[$field] as $base64) {
                if (!empty($base64) && strpos($base64, 'data:image') === 0) {
                    if (strpos($base64, ',') !== false) {
                        list(, $rawB64) = explode(',', $base64, 2);
                    } else {
                        $rawB64 = $base64;
                    }
                    $blobData = base64_decode($rawB64);
                    if ($blobData !== false && strlen($blobData) > 0) {
                        $blobData = compressImage($blobData, 2097152);
                        $newFileId = saveBlobToDb($pdo, $incidentId, $blobData);
                        $newPhotos[] = ['file_id' => $newFileId];
                    }
                }
            }
        }
    }

    // รับรายการรูปที่ต้องการลบ
    $deletedPhotoFileIds = [];
    if (isset($_POST['deleted_photo_file_ids'])) {
        $decoded = json_decode($_POST['deleted_photo_file_ids'], true);
        if (is_array($decoded)) $deletedPhotoFileIds = $decoded;
    } elseif (isset($data['deleted_photo_file_ids']) && is_array($data['deleted_photo_file_ids'])) {
        $deletedPhotoFileIds = $data['deleted_photo_file_ids'];
    }

    // กรองรูปเก่า: เก็บเฉพาะที่ไม่ถูกลบ + ลบ BLOB จาก DB
    $keptPhotos = [];
    foreach ($existingPhotos as $photo) {
        $fid = $photo['file_id'] ?? 0;
        if ($fid && in_array($fid, $deletedPhotoFileIds)) {
            deleteBlobFromDb($pdo, $fid);
        } elseif ($fid) {
            $keptPhotos[] = [
                'file_id' => $fid,
                'filename' => $photo['filename'] ?? null,
                'original_name' => $photo['original_name'] ?? null,
                'caption' => $photo['caption'] ?? ''
            ];
        }
    }

    // รวมรูปเก่าที่เหลือ + รูปใหม่
    $checklistData['photos'] = array_merge($keptPhotos, $newPhotos);

    // 6. บันทึก DB
    $json = json_encode($checklistData, JSON_UNESCAPED_UNICODE);
    $check = $pdo->prepare("SELECT id FROM incident_checklist_transaction WHERE incident_id = ? LIMIT 1");
    $check->execute([$incidentId]);
    $exists = $check->fetch();

    if ($exists) {
        // อัปเดตทั้ง checklist_data และ report_data (ให้เป็นค่าเดียวกันตั้งต้น)
        $sql = "UPDATE incident_checklist_transaction 
                SET incident_checklist_data = ?, incident_report_data = ?, edit_by = ?, edit_date = NOW() 
                WHERE incident_id = ?";
        $pdo->prepare($sql)->execute([$json, $json, $userId, $incidentId]);
    } else {
        $sql = "INSERT INTO incident_checklist_transaction 
                (incident_id, incident_checklist_data, incident_report_data, create_by, create_date) 
                VALUES (?, ?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute([$incidentId, $json, $json, $userId]);
    }

    $pdo->prepare("UPDATE rn_ReceiveNoti SET statusChecklist = 1 WHERE id = ?")->execute([$incidentId]);

    foreach ($filesToDelete as $item) {
        $fullPath = __DIR__ . '/../../uploads_2/' . $item['folder'] . '/' . $item['file'];
        if (file_exists($fullPath)) {
            @unlink($fullPath); // ลบไฟล์เก่าทิ้ง
        }
    }

    $pdo->commit();
    jsonResponse(true, 'บันทึกข้อมูลเรียบร้อยแล้ว');
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Traffic Checklist Save Error: " . $e->getMessage());
    jsonResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage());
}
