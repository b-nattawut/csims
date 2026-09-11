<?php

/**
 * API: Save Bomb Incident Checklist Data (v6.0 BLOB-based)
 * ตาราง: incident_checklist_transaction
 *
 * บันทึกรูปแบบ BLOB เหมือน saveLife.php / saveProperty.php
 * - ลายเซ็น/แผนผัง → BLOB ใน incident_checklist_transaction_file
 * - รูปภาพ → BLOB ใน incident_checklist_transaction_file
 */

ini_set('memory_limit', '256M');
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_config.php';
require_once __DIR__ . '/lab_unit_helper.php';

// ============================================
// Helper Functions
// ============================================

function normalizeValue($val, $default = '') {
    if (is_array($val)) {
        foreach ($val as $item) {
            if (is_array($item)) {
                continue;
            }
            if ($item !== null && trim((string) $item) !== '') {
                return $item;
            }
        }
        return $default;
    }
    return $val !== null && $val !== '' ? $val : $default;
}

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

    // --- ดึงข้อมูลเดิม ---
    $stmtOld = $pdo->prepare("SELECT id, incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC, id DESC LIMIT 1");
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

    // 1. จัดเตรียมข้อมูลผู้ประสบเหตุ (Fieldset 2)
    $allVictims = [];
    $firstVictim = ['name' => '', 'age' => '', 'type' => ''];
    if (isset($data['victim_type_bomb']) && is_array($data['victim_type_bomb'])) {
        foreach ($data['victim_type_bomb'] as $i => $type) {
            if (!empty($type)) {
                $vObj = ['type' => $type, 'name' => $data['victim_name_bomb'][$i] ?? '', 'age' => $data['victim_age_bomb'][$i] ?? '']; // 2.สถานที่เกิดเหตุ - ประเภทผู้ประสบเหตุ, ชื่อ - นามสกุล, อายุ (ปี)
                $allVictims[] = $vObj;
                if (empty($firstVictim['name'])) $firstVictim = $vObj;
            }
        }
    }

    // 2. จัดการข้อมูลศพ (Fieldset 7)
    $bodies = [];
    if (isset($data['body_status_bomb']) && is_array($data['body_status_bomb'])) {
        foreach ($data['body_status_bomb'] as $index => $status) {
            $bodies[] = [
                'name' => $data['body_name_bomb'][$index] ?? '', // 7.ผลการตรวจสถานที่เกิดเหตุ - ชื่อ - นามสกุล (ถ้าทราบ)
                'status' => $status, // 7.ผลการตรวจสถานที่เกิดเหตุ - พบ/ไม่พบศพ
                'notfound_detail' => $data['body_notfound_detail_bomb'][$index] ?? '', // 7.ผลการตรวจสถานที่เกิดเหตุ - ไม่พบศพ detail
                'condition_detail' => $data['body_condition_bomb'][$index] ?? '' // 7.ผลการตรวจสถานที่เกิดเหตุ - สภาพศพ ลักษณะการแต่งกาย ทรัพย์สิน และอื่น ๆ
            ];
        }
    }

    // 3. จัดการวัตถุพยานที่ตรวจพบ (Fieldset 10)
    $evFound = [];
    // ★ ดึง distance arrays — รองรับทั้ง evidence_ref1_dist_bomb[] และ evidence_ref1_dist_bomb_0 (legacy)
    $ref1Arr = (isset($data['evidence_ref1_dist_bomb']) && is_array($data['evidence_ref1_dist_bomb']))
        ? $data['evidence_ref1_dist_bomb'] : [];
    $ref2Arr = (isset($data['evidence_ref2_dist_bomb']) && is_array($data['evidence_ref2_dist_bomb']))
        ? $data['evidence_ref2_dist_bomb'] : [];
    $ref3Arr = (isset($data['evidence_ref3_dist_bomb']) && is_array($data['evidence_ref3_dist_bomb']))
        ? $data['evidence_ref3_dist_bomb'] : [];
    $ref4Arr = (isset($data['evidence_ref4_dist_bomb']) && is_array($data['evidence_ref4_dist_bomb']))
        ? $data['evidence_ref4_dist_bomb'] : [];
    if (isset($data['evidence_item_bomb']) && is_array($data['evidence_item_bomb'])) {
        foreach ($data['evidence_item_bomb'] as $i => $_item) {
            if (!isset($ref1Arr[$i]) && isset($data["evidence_ref1_dist_bomb_{$i}"])) {
                $ref1Arr[$i] = $data["evidence_ref1_dist_bomb_{$i}"];
            }
            if (!isset($ref2Arr[$i]) && isset($data["evidence_ref2_dist_bomb_{$i}"])) {
                $ref2Arr[$i] = $data["evidence_ref2_dist_bomb_{$i}"];
            }
            if (!isset($ref3Arr[$i]) && isset($data["evidence_ref3_dist_bomb_{$i}"])) {
                $ref3Arr[$i] = $data["evidence_ref3_dist_bomb_{$i}"];
            }
            if (!isset($ref4Arr[$i]) && isset($data["evidence_ref4_dist_bomb_{$i}"])) {
                $ref4Arr[$i] = $data["evidence_ref4_dist_bomb_{$i}"];
            }
        }
    }
    if (isset($data['evidence_item_bomb'])) { // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - รายการวัตถุพยาน
        foreach ($data['evidence_item_bomb'] as $i => $item) {
            if (!empty(trim($item))) {
                $evFound[] = [
                    'item' => $item,
                    'ref1_dist' => $ref1Arr[$i] ?? '',
                    'ref2_dist' => $ref2Arr[$i] ?? '',
                    'ref3_dist' => $ref3Arr[$i] ?? '',
                    'ref4_dist' => $ref4Arr[$i] ?? '',
                    'azimuth' => $data['evidence_azimuth_bomb'][$i] ?? '', // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - Azimuth (ทิศ/อ้าง/ระยะ)
                    'remark' => $data['evidence_remark_bomb'][$i] ?? '', // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - หมายเหตุ
                    // ★ lab_unit ดึงจาก evidence_lab_unit_bomb โดยตรง (ไม่ cross-sync กับ measurement)
                    'lab_unit' => labUnitsNormalize($data['evidence_lab_unit_bomb'][$i] ?? ''),
                ];
            }
        }
    }

    // 4. จัดการบันทึกการตรวจเก็บ (Fieldset 12)
    $measurements = [];
    if (isset($data['measurement_item_bomb']) && is_array($data['measurement_item_bomb'])) { // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - รายการวัตถุพยาน
        foreach ($data['measurement_item_bomb'] as $idx => $item) {
            if (!empty(trim($item))) {
                $measurements[] = [
                    'item'     => $item, // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - รายการวัตถุพยาน
                    'quantity' => $data['measurement_quantity_bomb'][$idx] ?? '', // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - จำนวน
                    'area'     => $data['measurement_area_bomb'][$idx] ?? '', // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - บริเวณที่ตรวจพบ
                    'label_no' => $data['measurement_label_number_bomb'][$idx] ?? '', // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - ป้ายหมายเลข
                    'packaging' => [
                        'plastic'      => isset($data['measurement_package_plastic_check'][$idx]), // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การบรรจุหีบห่อ ( พลาสติก )
                        'plastic_text' => $data['measurement_package_plastic_text'][$idx] ?? '', // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การบรรจุหีบห่อ ( พลาสติก - detail )
                        'paper'        => isset($data['measurement_package_paper_check'][$idx]), // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การบรรจุหีบห่อ ( กระดาษ )
                        'paper_text'   => $data['measurement_package_paper_text'][$idx] ?? '', // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การบรรจุหีบห่อ ( กระดาษ - detail )
                        'other'        => isset($data['measurement_package_other_check'][$idx]), // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การบรรจุหีบห่อ ( อื่น ๆ )
                        'other_text'   => $data['measurement_package_other_text'][$idx] ?? '' // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การบรรจุหีบห่อ ( อื่น ๆ - detail )
                    ],
                    'action' => [
                        'return'      => isset($data['measurement_action_return_check'][$idx]), // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การดำเนินการ ( ส่งคืน พงส. )
                        'return_text' => $data['measurement_action_return_text'][$idx] ?? '', // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การดำเนินการ ( ส่งคืน พงส. - detail )
                        'other'       => isset($data['measurement_action_other_check'][$idx]), // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การดำเนินการ ( อื่น ๆ )
                        'other_text'  => $data['measurement_action_other_text'][$idx] ?? '' // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การดำเนินการ ( อื่น ๆ - detail )
                    ],
                    'remark' => $data['measurement_remark_bomb'][$idx] ?? '', // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - หมายเหตุ
                    'forensic_unit' => labUnitsNormalize($data['measurement_forensic_unit_bomb'][$idx] ?? '') // 12. การตรวจพิสูจน์
                ];
            }
        }
    }

    // 5. โครงสร้างข้อมูลสำหรับบันทึก
    $buildingFloors = $data['building_floor_indoor'] ?? [];
    if ((empty($buildingFloors) || !is_array($buildingFloors)) && !empty($data['building_floor_count_indoor'])) {
        $buildingFloors = ['total' => $data['building_floor_count_indoor']];
    }

    $measurementInspectionDate = normalizeValue(
        $data['measurement_inspection_date_bomb'] ?? ($_POST['measurement_inspection_date_bomb'] ?? ''),
        ''
    );
    if ($measurementInspectionDate === '') {
        $mDatePart = normalizeValue($data['measurement_inspection_date_bomb'] ?? ($_POST['measurement_inspection_date_bomb'] ?? ''), '');
        $mTimePart = normalizeValue($data['measurement_inspection_time_bomb'] ?? ($_POST['measurement_inspection_time_bomb'] ?? ''), '00:00');
        if ($mDatePart !== '') {
            $measurementInspectionDate = $mDatePart . 'T' . ($mTimePart !== '' ? $mTimePart : '00:00');
        }
    }

    $checklistData = [
        'general_info' => [
            'report_no' => $data['report_no'] ?? '',
            'doc_no' => $data['doc_no'] ?? '',
            'case_type' => 'bomb',
            'case_type_other' => '',
            'case_doc_no' => $data['case_doc_bomb'] ?? '', // 1.การรับแจ้งเหตุ - คดี
            'case_date' => $data['case_date'] ?? '', // 1.การรับแจ้งเหตุ - วันที่
            'case_time' => $data['case_time'] ?? '', // 1.การรับแจ้งเหตุ - เวลา
            'report_datetime' => !empty($data['case_date'])
                ? ($data['case_date'] . 'T' . ($data['case_time'] ?? '00:00'))
                : '',
            'report_channel' => $data['notify_method'] ?? [], // 1.การรับแจ้งเหตุ - ช่องทางการรับแจ้ง
            'report_channel_other' => $data['notify_method_other_text'] ?? '', // 1.การรับแจ้งเหตุ - ช่องทางการรับแจ้ง - ช่องอื่น ๆ
            'source_station' => $data['police_station'] ?? '', // 1.การรับแจ้งเหตุ - สภ./สน.
            'document_no' => $data['location_at'] ?? '', // 1.การรับแจ้งเหตุ - ที่
            'document_date' => $data['record_date'] ?? '', // 1.การรับแจ้งเหตุ - ลง
            'investigator' => [
                'name' => $data['investigator_name'] ?? '', // 1.การรับแจ้งเหตุ - ชื่อพนักงานสอบสวน
                'firstname' => $data['investigator_name'] ?? '',
                'lastname' => '',
                'phone' => $data['investigator_phone'] ?? '' // 1.การรับแจ้งเหตุ - หมายเลขโทรศัพท์พนักงานสอบสวน
            ],
            'location_detail' => $data['crime_location'] ?? '',
            'victim_know_date' => $data['victim_know_date'] ?? '', // 3.วันเวลาที่ทราบเหตุ/เกิดเหตุ - วันที่ผู้เสียหายทราบเหตุ/เกิดเหตุ
            'victim_know_time' => $data['victim_know_time'] ?? '', // 3.วันเวลาที่ทราบเหตุ/เกิดเหตุ - เวลาประมาณ
            'incident_datetime' => !empty($data['victim_know_date'])
                ? ($data['victim_know_date'] . 'T' . ($data['victim_know_time'] ?? '00:00'))
                : '',
            'officer_know_date' => $data['officer_know_date'] ?? '', // 3.วันเวลาที่ทราบเหตุ/เกิดเหตุ - วันที่พนักงานสอบสวนทราบเหตุ
            'officer_know_time' => $data['officer_know_time'] ?? '', // 3.วันเวลาที่ทราบเหตุ/เกิดเหตุ - เวลาประมาณ
            'inspect_date' => $data['inspect_date'] ?? '', // 4.วันเวลาที่ตรวจเหตุ - วันที่ทำการตรวจสถานที่เกิดเหตุ
            'inspect_time' => $data['inspect_time'] ?? '', // 4.วันเวลาที่ตรวจเหตุ - เวลาประมาณ
            'inspection_datetime' => !empty($data['inspect_date'])
                ? ($data['inspect_date'] . 'T' . ($data['inspect_time'] ?? '00:00'))
                : '',
            'inspect_additional_date' => $data['inspect_additional_date'] ?? '', // 4.วันเวลาที่ตรวจเหตุ - วันที่ตรวจสถานที่เกิดเหตุเพิ่มเติม
            'inspect_additional_time' => $data['inspect_additional_time'] ?? '', // 4.วันเวลาที่ตรวจเหตุ - เวลาประมาณ
            'victim' => [
                'name' => $firstVictim['name'] ?? '',
                'firstname' => $firstVictim['name'] ?? '',
                'lastname' => '',
                'age' => $firstVictim['age'] ?? ''
            ]
        ],
        'victims' => $allVictims, //------- ยังไม่มี ---------
        'scene_info' => [
            'crime_location' => $data['crime_location'] ?? '', // 2.สถานที่เกิดเหตุ - รายละเอียดสถานที่เกิดเหตุ
            'preservation' => $data['scene_preserved'] ?? '', // 6.ลักษณะสถานที่เกิดเหตุ - การรักษาสถานที่เกิดเหตุ (มี/ไม่มี)
            'preservation_detail' => $data['scene_preserved_no_text'] ?? '', // 6.ลักษณะสถานที่เกิดเหตุ - การรักษาสถานที่เกิดเหตุ (text field กรณีไม่มีการรักษาสถานที่เกิดเหตุ)
            'environment' => [
                'lighting' => $data['lighting'] ?? [], // 6.ลักษณะสถานที่เกิดเหตุ - สภาพแวดล้อม ( แสงสว่าง )
                'lighting_other' => $data['lighting_other_text'] ?? '', // 6.ลักษณะสถานที่เกิดเหตุ - สภาพแวดล้อม ( แสงสว่าง - อื่น ๆ )
                'temperature' => $data['temperature'] ?? [],  // 6.ลักษณะสถานที่เกิดเหตุ - สภาพแวดล้อม ( อุณหภูมิ )
                'temperature_other' => $data['temperature_other_text'] ?? '',  // 6.ลักษณะสถานที่เกิดเหตุ - สภาพแวดล้อม ( อุณหภูมิ - อื่น ๆ )
                'smell' => $data['smell'] ?? '', // 6.ลักษณะสถานที่เกิดเหตุ - สภาพแวดล้อม ( กลิ่น )
                'smell_detail' => ($data['smell'] == 'มี' ? ($data['bomb_smell_yes_text'] ?? '') : ($data['bomb_smell_no_text'] ?? '')) // 6.ลักษณะสถานที่เกิดเหตุ - สภาพแวดล้อม ( กลิ่น - input field ที่จะรับอิงตามตัวเลือกว่าเลือก มี/ไม่มีกลิ่น )
            ],
            'outdoor' => [
                'is_active' => !empty($data['has_outdoor_incident']), // 6.ลักษณะสถานที่เกิดเหตุ - ประเภทสถานที่ ( เลือก "เกิดเหตุภายนอกอาคาร" เป็น 1 ในตัวเลือก )
                'type' => $data['outdoor_type'] ?? [], // 6.ลักษณะสถานที่เกิดเหตุ - เกิดเหตุภายนอกอาคาร ( ลักษณะพืันที่ ) 
                'type_other' => $data['outdoor_type_other_text'] ?? '',
                'entrance' => $data['entrance_condition_outdoor'] ?? '',
                'adjacent' => ['front' => $data['front_adjacent_outdoor'] ?? '', 'left' => $data['left_adjacent_outdoor'] ?? '', 'right' => $data['right_adjacent_outdoor'] ?? '', 'back' => $data['back_adjacent_outdoor'] ?? ''],
                'incident_area' => $data['incident_area_detail_outdoor'] ?? ''
            ],
            'indoor' => [
                'is_active' => !empty($data['has_indoor_incident']), // 6.ลักษณะสถานที่เกิดเหตุ - ประเภทสถานที่ ( เลือก "เกิดเหตุภายในอาคาร" เป็น 1 ในตัวเลือก )
                'building_type' => $data['building_type_indoor'] ?? [], // 6.ลักษณะสถานที่เกิดเหตุ - เกิดเหตุภายในอาคาร ( ลักษณะภายนอก เลือก ประเภทอาคาร )
                'building_type_other' => $data['building_type_other_text_indoor'] ?? '',
                'building_floors' => $buildingFloors, // 6.ลักษณะสถานที่เกิดเหตุ - เกิดเหตุภายในอาคาร ( ลักษณะภายนอก เลือก ชั้นของแต่ละประเภทอาคาร )
                'fence' => $data['surrounding_fence_indoor'] ?? '', // 6.ลักษณะสถานที่เกิดเหตุ - ( มี/ไม่มีรั้ว )
                'entrance' => $data['entrance_condition_indoor'] ?? '', // 6.ลักษณะสถานที่เกิดเหตุ - ( เมื่อหันหน้าเข้าสถานที่เกิดเหตุ... )
                'adjacent' => ['front' => $data['front_adjacent_indoor'] ?? '', 'left' => $data['left_adjacent_indoor'] ?? '', 'right' => $data['right_adjacent_indoor'] ?? '', 'back' => $data['back_adjacent_indoor'] ?? ''], // 6.ลักษณะสถานที่เกิดเหตุ - ( ด้านหน้า/ซ้าย/ขวา/หลังติด )
                'interior' => $data['interior_detail_indoor'] ?? '', // 6.ลักษณะสถานที่เกิดเหตุ - ( ลักษณะภายใน )
                'incident_area' => $data['incident_area_detail_indoor'] ?? '', // 6.ลักษณะสถานที่เกิดเหตุ - ( บริเวณที่เกิดเหตุ )
                'structure' => [
                    'size' => $data['structure_size_indoor'] ?? '',  // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( มีขนาดกว้าง x ยาว ประมาณ )
                    'type' => $data['structure_type_indoor'] ?? '', // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( ลักษณะโครงสร้าง )
                    'wall' => $data['structure_wall_indoor'] ?? '', // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( ผนัง )
                    'floor' => $data['structure_floor_indoor'] ?? '',  // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( พื้น )
                    'roof' => $data['structure_roof_indoor'] ?? '',  // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( หลังคา )
                    'arrangement' => $data['structure_arrangement_indoor'] ?? '', // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( การจัดวางสิ่งของ )
                    'adjacent' => ['front' => $data['structure_front_indoor'] ?? '', 'left' => $data['structure_left_indoor'] ?? '', 'right' => $data['structure_right_indoor'] ?? '', 'back' => $data['structure_back_indoor'] ?? ''] // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( ด้านหน้า/ซ้าย/ขวา/หลังติด )
                ]
            ]
        ],
        'inspection_results' => [ // 7.ผลการตรวจสถานที่เกิดเหตุ
            'case_behavior' => $data['case_behavior'] ?? '', // 7.ผลการตรวจสถานที่เกิดเหตุ - พฤติการณ์คดี
            'damage_details' => $data['damage_details'] ?? '', // 7.ผลการตรวจสถานที่เกิดเหตุ - ความเสียหาย
            'explosion_point' => $data['explosion_point'] ?? '', // 7.ผลการตรวจสถานที่เกิดเหตุ - ตำแหน่งที่เกิดการระเบิด
            'bodies' => $bodies,  // 7.ผลการตรวจสถานที่เกิดเหตุ - ข้อมูลศพ ------- ยังไม่มี ---------
            // 7.ผลการตรวจสถานที่เกิดเหตุ - วัตถุพยานที่ตรวจพบ ( ระเบิด )
            'bomb_evidence' => [
                'is_active' => !empty($data['has_bomb_evidence']),
                'containers' => [
                    'steel_box' => in_array('กล่องเหล็ก', $data['bomb_containers'] ?? []),
                    'gas_tank' => in_array('ถังแก๊ส', $data['bomb_containers'] ?? []),
                    'fire_ext' => in_array('ถังดับเพลิง', $data['bomb_containers'] ?? []),
                    'steel_pipe' => in_array('ท่อเหล็ก', $data['bomb_containers'] ?? []),
                    'pvc_pipe' => in_array('ท่อ PVC', $data['bomb_containers'] ?? []),
                    'ac_tank' => in_array('ถังน้ำยาแอร์', $data['bomb_containers'] ?? []),
                    'std_bomb' => in_array('ระเบิดมาตรฐาน', $data['bomb_containers'] ?? []),
                    'other' => in_array('อื่นๆ', $data['bomb_containers'] ?? []),
                    'other_text' => $data['bomb_container_other_text'] ?? ''
                ],
                'detonation' => [ // วิธีการจุดระเบิด
                    'trap' => !empty($data['detonate_trap']), // กับดัก/เหยียบ/สะดุด
                    'trap_detail' => $data['detonate_trap_detail'] ?? '', // กับดัก/เหยียบ/สะดุด ( อื่น ๆ )
                    'wire' => !empty($data['detonate_wire']), // ลากสายไฟ
                    'wire_color' => $data['detonate_wire_color'] ?? '', // ลากสายไฟ ( สี )
                    'wire_length' => $data['detonate_wire_length'] ?? '', // ลากสายไฟ ( ยาว )
                    'radio' => !empty($data['detonate_radio']), // วิทยุสื่อสาร
                    'radio_brand' => $data['detonate_radio_brand'] ?? '', // วิทยุสื่อสาร - ยี่ห้อ
                    'radio_model' => $data['detonate_radio_model'] ?? '', // วิทยุสื่อสาร - รุ่น
                    'radio_color' => $data['detonate_radio_color'] ?? '', // วิทยุสื่อสาร - สี
                    'radio_sn' => $data['detonate_radio_sn'] ?? '', // วิทยุสื่อสาร - s/n
                    'phone' => !empty($data['detonate_phone']), // โทรศัพท์มือถือ
                    'phone_brand' => $data['detonate_phone_brand'] ?? '', // โทรศัพท์มือถือ - ยี่ห้อ
                    'phone_model' => $data['detonate_phone_model'] ?? '', // โทรศัพท์มือถือ - รุ่น
                    'phone_color' => $data['detonate_phone_color'] ?? '', // โทรศัพท์มือถือ - สี
                    'phone_sn' => $data['detonate_phone_sn'] ?? '', // โทรศัพท์มือถือ - s/n
                    'remote' => !empty($data['detonate_remote']), // รีโมทคอนโทรล
                    'remote_detail' => $data['detonate_remote_detail'] ?? '', // รีโมทคอนโทรล - detail
                    'timer' => !empty($data['detonate_timer']),  // ตั้งเวลา
                    'timer_detail' => $data['detonate_timer_detail'] ?? '', // ตั้งเวลา - detail 
                    'other' => !empty($data['detonate_other']), // อื่น ๆ
                    'other_detail' => $data['detonate_other_detail'] ?? '' // อื่น ๆ - detail 
                ],
                'fragments' => [ // สะเก็ตระเบิด
                    'rebar' => !empty($data['fragment_rebar']), // เหล็กเส้นตัดท่อน
                    'rebar_size' => $data['fragment_rebar_size'] ?? '', // เหล็กเส้นตัดท่อน - ขนาด 
                    'rebar_length' => $data['fragment_rebar_length'] ?? '', // เหล็กเส้นตัดท่อน - ยาว
                    'nail' => !empty($data['fragment_nail']), // ตะปู
                    'nail_size' => $data['fragment_nail_size'] ?? '', // ตะปู - ขนาด
                    'other' => !empty($data['fragment_other']), // อื่น ๆ
                    'other_detail' => $data['fragment_other_detail'] ?? '' // อื่น ๆ - detail 
                ],
                // ส่วนประกอบของวัตถุระเบิดอื่น ๆ 
                'components' => [
                    'booster' => !empty($data['comp_booster']), // หลอดดินขยาย
                    'booster_detail' => $data['comp_booster_detail'] ?? '', // หลอดดินขยาย - detail
                    'detonator' => !empty($data['comp_detonator']), // เชื้อปะทุไฟฟ้า
                    'detonator_detail' => $data['comp_detonator_detail'] ?? '', // เชื้อปะทุไฟฟ้า - detail
                    'tape' => !empty($data['comp_tape']), // เทปพันสายไฟ
                    'tape_detail' => $data['comp_tape_detail'] ?? '', // เทปพันสายไฟ - detail
                    'sim' => !empty($data['comp_sim']), // ซิมการ์ด
                    'sim_detail' => $data['comp_sim_detail'] ?? '', // ซิมการ์ด - detail
                    'circuit' => !empty($data['comp_circuit']), // วงจรการจุดระเบิด
                    'circuit_detail' => $data['comp_circuit_detail'] ?? '', // วงจรการจุดระเบิด - detail
                    'battery' => !empty($data['comp_battery']), // แบตเตอรี่
                    'battery_detail' => $data['comp_battery_detail'] ?? '', // แบตเตอรี่ - detail
                    'battery_v' => $data['comp_battery_voltage'] ?? '', // แบตเตอรี่ - voltage
                    'dtmf' => !empty($data['comp_dtmf']), // แผงวงจร DTMF
                    'dtmf_detail' => $data['comp_dtmf_detail'] ?? '', // แผงวงจร DTMF - detail
                    'pcb' => !empty($data['comp_pcb']), // แผงวงจร 
                    'pcb_detail' => $data['comp_pcb_detail'] ?? '', // แผงวงจร - detail
                    'wire' => !empty($data['comp_wire']), // สายไฟวงจร
                    'wire_detail' => $data['comp_wire_detail'] ?? '', // สายไฟวงจร - detail
                    'box' => !empty($data['comp_box']), //  กล่องบรรจุวงจร
                    'box_detail' => $data['comp_box_detail'] ?? '', //  กล่องบรรจุวงจร - detail
                    'clock' => !empty($data['comp_clock']), // นาฬิกา
                    'clock_detail' => $data['comp_clock_detail'] ?? '', // นาฬิกา - detail
                    'lever' => !empty($data['comp_lever']), // กระเดื่อง
                    'lever_detail' => $data['comp_lever_detail'] ?? '', // กระเดื่อง - detail
                    'pin' => !empty($data['comp_pin']), // สลักนิรภัย
                    'pin_detail' => $data['comp_pin_detail'] ?? '', // สลักนิรภัย - detail
                    'other' => !empty($data['comp_misc_other']), //  อื่น ๆ
                    'other_detail' => $data['comp_misc_other_detail'] ?? '' //  อื่น ๆ - detail
                ]
            ],
            // คราบสีแดงคล้ายโลหิต
            'blood_evidence' => [
                'is_active' => !empty($data['evidence_blood_stain']), // เลือก/ไม่เลือก คราบสีแดงคล้ายโลหิต
                'detail' => $data['blood_stain_detail'] ?? '', // detail คราบสีแดงคล้ายโลหิต
                'test_hemastix' => !empty($data['test_hemastix']), // เก็บสถานะว่าเลือกทดสอบด้วย hemastix
                'hemastix_result' => $data['hemastix_result'] ?? '', // ผลจากการทดสอบด้วย hemastix
                'test_phenol' => !empty($data['test_phenolphthalein']) || !empty($data['test_phenol']), // เก็บสถานะว่าเลือกทดสอบด้วย phenolphthalein
                'phenol_result' => $data['phenol_result'] ?? '' // ผลจากการทดสอบด้วย phenolphthalein
            ],
            // วัตถุพยานอื่นๆ
            'other_evidence' => ['is_active' => !empty($data['has_evidence_other']), 'detail' => $data['evidence_other_detail'] ?? ''],
            'collected_evidence' => [
                'has_dna' => (!empty($data['collected_evidence']) && in_array('สารพันธุกรรม', $data['collected_evidence'])),
                'dna_detail' => $data['collected_dna_detail'] ?? '',
                'has_fingerprint' => (!empty($data['collected_evidence']) && in_array('ลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง', $data['collected_evidence'])),
                'fingerprint_detail' => $data['fingerprint_detail'] ?? '',
                'has_toolmark' => (!empty($data['collected_evidence']) && in_array('ร่องรอยการตัด', $data['collected_evidence'])),
                'toolmark_detail' => $data['collected_toolmark_detail'] ?? '',
                'has_explosive' => (!empty($data['collected_evidence']) && in_array('สารระเบิด', $data['collected_evidence'])),
                'explosive_detail' => $data['collected_explosive_detail'] ?? '',
                'has_comp' => (!empty($data['collected_evidence']) && in_array('ส่วนประกอบของวัตถุระเบิด', $data['collected_evidence'])),
                'comp_detail' => $data['collected_comp_detail'] ?? '',
                'has_other' => (!empty($data['collected_evidence']) && in_array('อื่นๆ', $data['collected_evidence'])),
                'other_detail' => $data['other_evidence_type'] ?? ''
            ],
            'final_check' => $data['final_check'] ?? [] // การตรวจสอบครั้งสุดท้าย
        ],
        'inspectors' => $data['inspector_id'] ?? [], // 5.ผู้ตรวจสถานที่เกิดเหตุ
        'evidences' => array_map(function($ev, $i) {
            return [
                'no' => $i + 1,
                'detail' => $ev['item'] ?? '',
                'lab_unit' => labUnitsNormalize($ev['lab_unit'] ?? '')
            ];
        }, $evFound, array_keys($evFound)),
        'evidences_found' => $evFound, // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - รายการวัตถุพยาน 
        'evidence_found_meta' => [
            'ref_1' => $data['reference_point_1_bomb'] ?? '', // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 1
            'ref_2' => $data['reference_point_2_bomb'] ?? '', // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 2
            'ref_3' => $data['reference_point_3_bomb'] ?? '', // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 3
            'ref_4' => $data['reference_point_4_bomb'] ?? '', // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 4
            'collector' => normalizeValue($data['collector_name_bomb'] ?? ($_POST['collector_name_bomb'] ?? ($_POST['collector_name'] ?? '')), ''), // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - ผู้จัดเก็บ
            'collect_datetime' => normalizeValue($data['collection_datetime_bomb'] ?? ($_POST['collection_datetime_bomb'] ?? ($_POST['collection_datetime'] ?? '')), '') // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - วัน/เวลา
        ],
        'measurements' => $measurements,
        'measurement_meta' => [ // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน)
            'inspection_date' => $measurementInspectionDate, // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - วันที่ตรวจสอบที่เกิดเหตุ
            'recorder' => normalizeValue($data['measurement_recorder_bomb'] ?? ($_POST['measurement_recorder_bomb'] ?? ($_POST['ec_recorder'] ?? '')), ''), // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - ผู้บันทึก
            'measurement_datetime' => normalizeValue($data['measurement_datetime_bomb'] ?? ($_POST['measurement_datetime_bomb'] ?? ($_POST['ec_datetime'] ?? '')), '') // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - วัน/เวลา
        ],
        'sketch_meta' => [
            'remark' => $data['sketch_remark_bomb'] ?? '', // 9. แผนผังสังเขป - หมายเหตุ
            'recorder' => normalizeValue($data['sketch_recorder_bomb'] ?? ($_POST['sketch_recorder_bomb'] ?? ''), ''), // 9. แผนผังสังเขป - ผู้จดบันทึก
            'datetime' => normalizeValue($data['sketch_datetime_bomb'] ?? ($_POST['sketch_datetime_bomb'] ?? ''), '') // 9. แผนผังสังเขป - วัน/เวลา
        ],
        'body_diagram_meta' => [
            'victim_name' => $data['victim_name_bomb_diagram'] ?? '', // 11. แผนผังภาพแสดงตำแหน่งบาดแผล - ชื่อ-สกุล (ผู้เสียชีวิต/ผู้บาดเจ็บ)
            'victim_age' => $data['victim_age_bomb_diagram'] ?? '', // 11. แผนผังภาพแสดงตำแหน่งบาดแผล - อายุ (ปี)
            'doctor' => $data['autopsy_doctor_bomb'] ?? '', // 11. แผนผังภาพแสดงตำแหน่งบาดแผล - พทย์ผู้ชันสูตร
            // 'remark' => $data['body_diagram_remark_bomb'] ?? '' // 11. แผนผังภาพแสดงตำแหน่งบาดแผล - หมายเหตุ --> จริง ๆ ไม่มีในฟอร์ม
        ],
        'photo_records' => [
            'start' => $data['photo_id_start_bomb'] ?? '', // 13. บันทึกการถ่ายภาพ - รหัสภาพถ่ายที่
            'end' => $data['photo_id_end_bomb'] ?? '',  // 13. บันทึกการถ่ายภาพ - ถึง
            'amount' => $data['photo_amount_bomb'] ?? '', // 13. บันทึกการถ่ายภาพ - จำนวนภาพ
            'photographer_name'     => normalizeValue($data['photographer_name_bomb'] ?? ($_POST['photographer_name_bomb'] ?? ($_POST['photo_recorder'] ?? '')), ''), // 13. บันทึกการถ่ายภาพ - ผู้บันทึก
            'photographer_datetime' => normalizeValue($data['photographer_datetime_bomb'] ?? ($_POST['photographer_datetime_bomb'] ?? ($_POST['photo_datetime'] ?? '')), '') // 13. บันทึกการถ่ายภาพ - วัน/เวลา
        ],
        // 8. การส่งมอบสถานที่เกิดเหตุ
        'handover' => [
            'receiver_id' => $data['receiver_name'] ?? ($data['receiver_name_bomb'] ?? ''),
            'receiver_pos' => $data['receiver_position'] ?? ($data['receiver_position_bomb'] ?? ''),
            'deliverer_id' => $data['sender_name'] ?? ($data['sender_name_bomb'] ?? ''),
            'deliverer_pos' => $data['sender_position'] ?? ($data['sender_position_bomb'] ?? ''),
            'inspection_end_date' => $data['inspection_end_date'] ?? '',
            'inspection_end_time' => $data['inspection_end_time'] ?? ''
        ],
        'signatures' => [],
        'photos' => []
    ];

    // ============================================
    // 6. ลายเซ็น / แผนผัง — บันทึกเป็น BLOB ใน incident_checklist_transaction_file
    // ============================================
    $sigFileFields = [
        'scene_sketch'  => ['sig_file_scene_sketch', 'scene_sketch_file'],
        'receiver_sig'  => ['sig_file_receiver_signature', 'receiver_signature_file'],
        'sender_sig'    => ['sig_file_sender_signature', 'sender_signature_file'],
        'body_diagram'  => ['sig_file_body_diagram', 'body_diagram_file']
    ];

    $sigBase64Fields = [
        'scene_sketch'  => ['scene_sketch_data_bomb', 'bpf_scene_sketch_data'],
        'receiver_sig'  => ['receiver_signature_data_bomb', 'bpf_receiver_sig_data'],
        'sender_sig'    => ['sender_signature_data_bomb', 'bpf_sender_sig_data'],
        'body_diagram'  => ['body_diagram_data_bomb', 'bpf_body_diagram_data']
    ];

    // ★ รายการ signature ที่ถูกล้าง (ส่งมาจาก JS)
    $clearedSignatures = [];
    if (!empty($data['cleared_signatures'])) {
        $clearedSignatures = json_decode($data['cleared_signatures'], true) ?: [];
    }

    foreach ($sigFileFields as $sigKey => $fileFieldNames) {
        $saved = false;

        // ★ ถ้า signature นี้ถูกล้าง → ลบ BLOB เดิม + ไม่เก็บค่า
        $clearKeyMap = [
            'scene_sketch' => 'scene_sketch',
            'receiver_sig' => 'receiver_signature',
            'sender_sig' => 'sender_signature',
            'body_diagram' => 'body_diagram'
        ];
        $jsKey = $clearKeyMap[$sigKey] ?? $sigKey;
        if (in_array($jsKey, $clearedSignatures)) {
            if (!empty($existingSigFileIds[$sigKey])) {
                deleteBlobFromDb($pdo, $existingSigFileIds[$sigKey]);
            }
            $checklistData['signatures'][$sigKey] = [];
            continue;
        }

        // Priority 1: $_FILES (Blob upload จาก JS)
        foreach ($fileFieldNames as $fieldName) {
            if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                $blobData = file_get_contents($_FILES[$fieldName]['tmp_name']);
                if ($blobData !== false && strlen($blobData) > 0) {
                    if (!empty($existingSigFileIds[$sigKey])) {
                        deleteBlobFromDb($pdo, $existingSigFileIds[$sigKey]);
                    }
                    $newFileId = saveBlobToDb($pdo, $incidentId, $blobData);
                    $checklistData['signatures'][$sigKey] = ['file_id' => $newFileId];
                    $saved = true;
                    break;
                }
            }
        }

        // Priority 2: base64 ใน POST data (legacy)
        if (!$saved) {
            foreach ($sigBase64Fields[$sigKey] as $b64Field) {
                $base64 = $data[$b64Field] ?? '';
                if (!empty($base64) && strpos($base64, 'data:image') !== false) {
                    $parts = explode(',', $base64, 2);
                    $binaryData = base64_decode($parts[1] ?? $parts[0]);
                    if ($binaryData !== false && strlen($binaryData) > 0) {
                        if (!empty($existingSigFileIds[$sigKey])) {
                            deleteBlobFromDb($pdo, $existingSigFileIds[$sigKey]);
                        }
                        $newFileId = saveBlobToDb($pdo, $incidentId, $binaryData);
                        $checklistData['signatures'][$sigKey] = ['file_id' => $newFileId];
                        $saved = true;
                        break;
                    }
                }
            }
        }

        // Priority 3: preserve existing signature (ไม่ได้วาดใหม่)
        if (!$saved && $oldData && isset($oldData['signatures'][$sigKey])) {
            $checklistData['signatures'][$sigKey] = $oldData['signatures'][$sigKey];
        }
    }

    if (empty($checklistData['signatures']['deliverer_sig']) && !empty($checklistData['signatures']['sender_sig'])) {
        $checklistData['signatures']['deliverer_sig'] = $checklistData['signatures']['sender_sig'];
    }

    // ============================================
    // 7. รูปภาพ — บันทึกเป็น BLOB ใน incident_checklist_transaction_file
    // ============================================
    $newPhotos = [];
    $imageFields = ['incident_photos_bomb', 'camera_photos_bomb'];
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

    // รับรายการรูปเก่าที่เก็บเป็นไฟล์บนดิสก์ที่ต้องการลบ (migration จาก save เดิม)
    $deletedFilenames = [];
    if (isset($_POST['deleted_photos'])) {
        $deletedFilenames = json_decode($_POST['deleted_photos'], true) ?? [];
    }

    // กรองรูปเก่าที่ไม่ถูกลบ + ลบ BLOB / ไฟล์จาก DB/ดิสก์
    $keptPhotos = [];
    $uploadPathForDelete = __DIR__ . '/../../uploads_2/checklist_bomb_photos';
    foreach ($existingPhotos as $photo) {
        $fid = $photo['file_id'] ?? 0;
        $fname = $photo['filename'] ?? '';

        if ($fid && in_array($fid, $deletedPhotoFileIds)) {
            deleteBlobFromDb($pdo, $fid);
        } elseif ($fname && in_array($fname, $deletedFilenames)) {
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

    // ให้ระบบคำนวณ From/To/Amount อัตโนมัติจากจำนวนรูปที่บันทึกจริง
    $photoCount = count($checklistData['photos']);
    $postedStart = trim((string)($checklistData['photo_records']['start'] ?? ''));
    $postedEnd = trim((string)($checklistData['photo_records']['end'] ?? ''));
    $postedAmount = trim((string)($checklistData['photo_records']['amount'] ?? ''));

    if ($photoCount > 0) {
        // ถ้าไม่มีค่า/ค่าไม่เป็นตัวเลข ให้เติมค่าอัตโนมัติ
        if ($postedStart === '' || !ctype_digit($postedStart)) {
            $checklistData['photo_records']['start'] = '1';
        }
        if ($postedEnd === '' || !ctype_digit($postedEnd)) {
            $checklistData['photo_records']['end'] = (string)$photoCount;
        }
        // จำนวนภาพ ให้ตรงจำนวนจริงเสมอ
        $checklistData['photo_records']['amount'] = (string)$photoCount;
    } else {
        $checklistData['photo_records']['start'] = '';
        $checklistData['photo_records']['end'] = '';
        $checklistData['photo_records']['amount'] = '0';
    }

    // ★ รักษาข้อมูลจากรอบเก่า
    if ($oldData) {
        $preserveKeys = ['summary', 'opinion', 'remark'];
        foreach ($preserveKeys as $pKey) {
            if (!isset($checklistData[$pKey]) && isset($oldData[$pKey])) {
                $checklistData[$pKey] = $oldData[$pKey];
            }
        }
    }

    // ============================================
    // 8. บันทึก DB
    // ============================================
    $json = json_encode($checklistData, JSON_UNESCAPED_UNICODE);

    if ($oldRow) {
        $sql = "UPDATE incident_checklist_transaction 
                SET incident_checklist_data = ?, edit_by = ?, edit_date = NOW(),
                    count_edit = count_edit + 1
                WHERE id = ?";
        $pdo->prepare($sql)->execute([$json, $userId, (int)$oldRow['id']]);
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
    error_log("Bomb Checklist Save Error: " . $e->getMessage());
    jsonResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage());
}
