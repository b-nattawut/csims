<?php
/**
 * API: Save Life Incident Checklist Data
 * ตาราง: incident_checklist_transaction (ใช้ตารางเดียวกับทรัพย์)
 *
 * บันทึกรูปแบบ BLOB เหมือน saveProperty.php
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

/**
 * Normalize value: ถ้าเป็น array ให้แปลงเป็น string (ใช้ตัวแรก)
 * รองรับ PDF form ที่ส่ง checkbox เป็น array แทน single value
 */
function normalizeValue($val, $default = '') {
    if (is_array($val)) {
        return !empty($val) ? $val[0] : $default;
    }
    return $val !== null && $val !== '' ? $val : $default;
}

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

/**
 * บีบอัดรูปภาพให้ไม่เกิน $maxBytes (default 2MB)
 * คืนค่า binary data ที่บีบอัดแล้ว หรือ original data ถ้าบีบไม่ได้
 */
function compressImage($blobData, $maxBytes = 2097152) {
    // ถ้าขนาดไม่เกิน limit แล้ว ไม่ต้องบีบ
    if (strlen($blobData) <= $maxBytes) {
        return $blobData;
    }

    $img = @imagecreatefromstring($blobData);
    if ($img === false) {
        return $blobData; // ไม่ใช่รูปที่ GD รองรับ → คืนค่าเดิม
    }

    // ลด quality จาก 85 ลงมาเรื่อยๆ จนไม่เกิน maxBytes
    $quality = 85;
    $compressed = $blobData;
    while ($quality >= 10) {
        ob_start();
        imagejpeg($img, null, $quality);
        $output = ob_get_clean();
        if (strlen($output) <= $maxBytes) {
            $compressed = $output;
            break;
        }
        $compressed = $output;
        $quality -= 10;
    }

    // ถ้ายังเกินอยู่ → resize ลงครึ่งหนึ่งแล้วลองอีกครั้ง
    if (strlen($compressed) > $maxBytes) {
        $w = imagesx($img);
        $h = imagesy($img);
        $newW = (int)($w * 0.5);
        $newH = (int)($h * 0.5);
        $resized = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);

        $quality = 80;
        while ($quality >= 10) {
            ob_start();
            imagejpeg($resized, null, $quality);
            $output = ob_get_clean();
            if (strlen($output) <= $maxBytes) {
                $compressed = $output;
                break;
            }
            $compressed = $output;
            $quality -= 10;
        }
        imagedestroy($resized);
    }

    imagedestroy($img);
    return $compressed;
}

// ============================================
// Main Logic
// ============================================

// ตรวจสอบ method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'ไม่อนุญาตวิธีการนี้', null, 405);
}

// รับข้อมูล - รองรับทั้ง FormData และ JSON
$data = null;

// ตรวจสอบว่าเป็น FormData หรือ JSON
if (isset($_POST['payload'])) {
    // กรณี FormData - payload อยู่ใน $_POST['payload'] เป็น JSON string
    $data = json_decode($_POST['payload'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, 'ข้อมูล JSON ไม่ถูกต้อง: ' . json_last_error_msg(), null, 400);
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

if (!$incidentId) {
    jsonResponse(false, 'ไม่พบรหัสใบรับแจ้งเหตุ', null, 400);
}

// ★ DEBUG: Log scene fields ที่ได้รับ
$debugSceneFields = [
    'scene_preserved' => $data['scene_preserved'] ?? 'NOT_SET',
    'lighting' => $data['lighting'] ?? 'NOT_SET',
    'temperature' => $data['temperature'] ?? 'NOT_SET',
    'smell' => $data['smell'] ?? 'NOT_SET',
    'has_outdoor_incident_life' => $data['has_outdoor_incident_life'] ?? 'NOT_SET',
    'outdoor_type' => $data['outdoor_type'] ?? 'NOT_SET',
    'outdoor_entrance_condition' => $data['outdoor_entrance_condition'] ?? 'NOT_SET',
    'outdoor_front_adjacent' => $data['outdoor_front_adjacent'] ?? 'NOT_SET',
];
error_log('[SAVE_LIFE DEBUG] incident_id=' . $incidentId . ' scene_fields=' . json_encode($debugSceneFields, JSON_UNESCAPED_UNICODE));

// ดึง user_id จาก session (ถ้ามี)
session_start();
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

try {
    $pdo->beginTransaction();

    // ============================================
    // 1. เตรียมข้อมูลสำหรับบันทึก
    // ============================================

    // รวมชื่อผู้ประสบเหตุ
    $victimName = '';
    $victimAge = '';
    if (!empty($data['dead_name'])) {
        $victimName = $data['dead_name'];
        $victimAge = $data['dead_age'] ?? '';
    } elseif (!empty($data['injured_name'])) {
        $victimName = $data['injured_name'];
        $victimAge = $data['injured_age'] ?? '';
    } elseif (!empty($data['missing_name'])) {
        $victimName = $data['missing_name'];
        $victimAge = $data['missing_age'] ?? '';
    }

    // ประมวลผลวัตถุพยาน/การตรวจวัด (Measurements to Evidences)
    $evidences = [];
    $measurements = [];

    // ==========================================
    // รับข้อมูลวัตถุพยานจาก payload.evidences (format ใหม่จาก frontend)
    // ==========================================
    if (isset($data['evidences']) && is_array($data['evidences']) && !empty($data['evidences'])) {
        foreach ($data['evidences'] as $ev) {
            $detail = trim($ev['detail'] ?? '');
            if (empty($detail) && empty($ev['type'])) continue;

            // ดึง ref_points (ระยะห่างจากจุดอ้างอิง 1-4)
            $refPoints = $ev['ref_points'] ?? [];
            $ref1Dist = $refPoints[0]['dist'] ?? '';
            $ref2Dist = $refPoints[1]['dist'] ?? '';
            $ref3Dist = $refPoints[2]['dist'] ?? '';
            $ref4Dist = $refPoints[3]['dist'] ?? '';
            $ref1Desc = $refPoints[0]['desc'] ?? '';
            $ref2Desc = $refPoints[1]['desc'] ?? '';
            $ref3Desc = $refPoints[2]['desc'] ?? '';
            $ref4Desc = $refPoints[3]['desc'] ?? '';

            // Quantity
            $qtyVal = '';
            $qtyUnit = '';
            if (isset($ev['quantity'])) {
                $qtyVal = $ev['quantity']['val'] ?? '';
                $qtyUnit = $ev['quantity']['unit'] ?? '';
            }

            // Packaging
            $packaging = $ev['packaging'] ?? [];

            // Action
            $action = $ev['action'] ?? [];

            // Blood test
            $bloodTest = $ev['blood_test'] ?? [];

            $evidences[] = [
                'type' => $ev['type'] ?? 'other',
                'no' => $ev['no'] ?? '',
                'detail' => $detail,
                'area_found' => $ev['area_found'] ?? '',
                'label_no' => $ev['label_no'] ?? '',
                'azimuth' => $ev['azimuth'] ?? '',
                'remark' => $ev['remark'] ?? '',
                'lab_unit' => labUnitsNormalize($ev['lab_unit'] ?? ''),
                'quantity_val' => $qtyVal,
                'quantity_unit' => $qtyUnit,
                // ระยะห่างจากจุดอ้างอิง (ค่าจริงเป็นเมตร)
                'ref1_dist' => $ref1Dist,
                'ref2_dist' => $ref2Dist,
                'ref3_dist' => $ref3Dist,
                'ref4_dist' => $ref4Dist,
                'ref1_desc' => $ref1Desc,
                'ref2_desc' => $ref2Desc,
                'ref3_desc' => $ref3Desc,
                'ref4_desc' => $ref4Desc,
                // สำหรับ backward compatibility
                'level_1' => $ref1Dist,
                'level_2' => $ref2Dist,
                'level_3' => $ref3Dist,
                'level_4' => $ref4Dist,
                // การบรรจุหีบห่อ
                'package_plastic' => !empty($packaging['plastic']),
                'package_paper' => !empty($packaging['paper']),
                'package_other' => !empty($packaging['other']),
                'package_other_text' => $packaging['other_text'] ?? '',
                // ผลทดสอบเบื้องต้น
                'blood_test' => $bloodTest,
                // การดำเนินการ
                'action_return' => !empty($action['return']),
                'action_other' => !empty($action['other']),
                'action_other_text' => $action['other_text'] ?? ''
            ];

            // เพิ่มเข้า measurements ด้วย (สำหรับตารางบันทึกการตรวจเก็บ)
            $measurements[] = [
                'item' => $detail,
                'quantity' => $qtyVal . (!empty($qtyUnit) ? ' ' . $qtyUnit : ''),
                'area' => $ev['area_found'] ?? '',
                'label_number' => $ev['label_no'] ?? '',
                'remark' => $ev['remark'] ?? '',
                'package_plastic' => !empty($packaging['plastic']),
                'package_plastic_text' => '',
                'package_paper' => !empty($packaging['paper']),
                'package_paper_text' => '',
                'package_other' => !empty($packaging['other']),
                'package_other_text' => $packaging['other_text'] ?? '',
                'action_return' => !empty($action['return']),
                'action_return_text' => '',
                'action_other' => !empty($action['other']),
                'action_other_text' => $action['other_text'] ?? '',
                'forensic_unit' => labUnitsNormalize($ev['lab_unit'] ?? '')
            ];
        }
    }

    // ==========================================
    // Fallback: รับข้อมูลวัตถุพยานจาก evidence_item_life (format เก่า)
    // ==========================================
    if (empty($evidences) && isset($data['evidence_item_life']) && is_array($data['evidence_item_life'])) {
        $itemCount = count($data['evidence_item_life']);
        for ($i = 0; $i < $itemCount; $i++) {
            $item = $data['evidence_item_life'][$i] ?? '';
            if (!empty(trim($item))) {
                $ref1Dist = trim((string)normalizeValue($data["evidence_level_1_life_{$i}"] ?? ''));
                $ref2Dist = trim((string)normalizeValue($data["evidence_level_2_life_{$i}"] ?? ''));
                $ref3Dist = trim((string)normalizeValue($data["evidence_level_3_life_{$i}"] ?? ''));
                $ref4Dist = trim((string)normalizeValue($data["evidence_level_4_life_{$i}"] ?? ''));

                $evidences[] = [
                    'type' => 'other',
                    'detail' => $item,
                    'azimuth' => $data['evidence_azimuth_life'][$i] ?? '',
                    'remark' => $data['evidence_remark_life'][$i] ?? '',
                    'lab_unit' => labUnitsNormalize($data['evidence_lab_unit_life'][$i] ?? ''),
                    'ref1_dist' => $ref1Dist,
                    'ref2_dist' => $ref2Dist,
                    'ref3_dist' => $ref3Dist,
                    'ref4_dist' => $ref4Dist,
                    'level_1' => $ref1Dist,
                    'level_2' => $ref2Dist,
                    'level_3' => $ref3Dist,
                    'level_4' => $ref4Dist
                ];
            }
        }
    }

    // ==========================================
    // Fallback: measurement_item_life (format เก่า)
    // ==========================================
    if (empty($measurements) && isset($data['measurement_item_life']) && is_array($data['measurement_item_life'])) {
        $itemCount = count($data['measurement_item_life']);
        for ($i = 0; $i < $itemCount; $i++) {
            $item = $data['measurement_item_life'][$i] ?? '';
            if (!empty(trim($item))) {
                $pkgPlastic = !empty($data["measurement_package_plastic_check_{$i}"]);
                $pkgPlasticText = $data["measurement_package_plastic_text_{$i}"] ?? '';
                $pkgPaper = !empty($data["measurement_package_paper_check_{$i}"]);
                $pkgPaperText = $data["measurement_package_paper_text_{$i}"] ?? '';
                $pkgOther = !empty($data["measurement_package_other_check_{$i}"]);
                $pkgOtherText = $data["measurement_package_other_text_{$i}"] ?? '';

                $actReturn = !empty($data["measurement_action_return_check_{$i}"]);
                $actReturnText = $data["measurement_action_return_text_{$i}"] ?? '';
                $actOther = !empty($data["measurement_action_other_check_{$i}"]);
                $actOtherText = $data["measurement_action_other_text_{$i}"] ?? '';

                $measurement = [
                    'item' => $item,
                    'quantity' => $data['measurement_quantity_life'][$i] ?? '',
                    'area' => $data['measurement_area_life'][$i] ?? '',
                    'label_number' => $data['measurement_label_number_life'][$i] ?? '',
                    'remark' => $data['measurement_remark_life'][$i] ?? '',
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
                    'forensic_unit' => labUnitsNormalize($data['measurement_forensic_unit_life'][$i] ?? '')
                ];
                $measurements[] = $measurement;
            }
        }
    }

    // ============================================
    // ประมวลผลข้อมูลผู้ประสบเหตุ (รองรับหลายคน)
    // ============================================
    $victimTypes = $data['victim_type_life'] ?? [];
    $victimNames = $data['victim_name_life'] ?? [];
    $victimAges = $data['victim_age_life'] ?? [];

    // หาสถานะผู้ประสบเหตุคนแรก (สำหรับ PDF)
    $firstVictimStatus = '';
    $firstVictimName = '';
    $firstVictimAge = '';
    if (!empty($victimTypes)) {
        $typeMap = [
            'ผู้เสียชีวิต' => 'deceased',
            'ผู้บาดเจ็บ' => 'injured',
            'ผู้สูญหาย' => 'missing'
        ];
        $firstVictimStatus = $typeMap[$victimTypes[0]] ?? $victimTypes[0];
        $firstVictimName = $victimNames[0] ?? '';
        $firstVictimAge = $victimAges[0] ?? '';
    }
    // Fallback: ถ้าไม่มีข้อมูลจากรูปแบบใหม่ ให้ใช้ victimName/victimAge จากด้านบน
    if (empty($firstVictimName) && !empty($victimName)) {
        $firstVictimName = $victimName;
        $firstVictimAge = $victimAge;
    }

    // รวบรวมผู้ประสบเหตุทั้งหมด
    $allVictims = [];
    $isPdfForm = ($data['form_mode'] ?? '') === 'pdf_form';

    if ($isPdfForm) {
        // PDF form: ผู้เสียชีวิตอยู่ใน victim_name_life[], ผู้บาดเจ็บ/สูญหาย แยกฟิลด์
        if (!empty($victimNames)) {
            for ($i = 0; $i < count($victimNames); $i++) {
                if (!empty(trim($victimNames[$i] ?? ''))) {
                    $allVictims[] = [
                        'type' => 'ผู้เสียชีวิต',
                        'name' => $victimNames[$i] ?? '',
                        'age' => $victimAges[$i] ?? ''
                    ];
                }
            }
        }
        $injuredNames = $data['victim_injured_name_life'] ?? [];
        $injuredAges = $data['victim_injured_age_life'] ?? [];
        for ($i = 0; $i < count($injuredNames); $i++) {
            if (!empty(trim($injuredNames[$i] ?? ''))) {
                $allVictims[] = [
                    'type' => 'ผู้บาดเจ็บ',
                    'name' => $injuredNames[$i] ?? '',
                    'age' => $injuredAges[$i] ?? ''
                ];
            }
        }
        $missingNames = $data['victim_missing_name_life'] ?? [];
        $missingAges = $data['victim_missing_age_life'] ?? [];
        for ($i = 0; $i < count($missingNames); $i++) {
            if (!empty(trim($missingNames[$i] ?? ''))) {
                $allVictims[] = [
                    'type' => 'ผู้สูญหาย',
                    'name' => $missingNames[$i] ?? '',
                    'age' => $missingAges[$i] ?? ''
                ];
            }
        }
        // อัพเดท firstVictim จาก allVictims
        if (!empty($allVictims) && empty($firstVictimName)) {
            $typeMap2 = ['ผู้เสียชีวิต' => 'deceased', 'ผู้บาดเจ็บ' => 'injured', 'ผู้สูญหาย' => 'missing'];
            $firstVictimStatus = $typeMap2[$allVictims[0]['type']] ?? $allVictims[0]['type'];
            $firstVictimName = $allVictims[0]['name'];
            $firstVictimAge = $allVictims[0]['age'];
        }
    } else {
        // Standard form: victim_type_life[], victim_name_life[], victim_age_life[] ตรงกัน index
        if (!empty($victimTypes)) {
            for ($i = 0; $i < count($victimTypes); $i++) {
                $allVictims[] = [
                    'type' => $victimTypes[$i] ?? '',
                    'name' => $victimNames[$i] ?? '',
                    'age' => $victimAges[$i] ?? ''
                ];
            }
        }
    }

    $checklistData = [
        // ==========================================
        // 1. ข้อมูลทั่วไป (general_info)
        // ==========================================
        'general_info' => [
            // วันเวลา
            'report_datetime' => ($data['case_date'] ?? '') . 'T' . ($data['case_time'] ?? ''),
            'incident_datetime' => ($data['victim_know_date'] ?? '') . 'T' . ($data['victim_know_time'] ?? ''),
            'investigator_known_datetime' => ($data['officer_know_date'] ?? '') . 'T' . ($data['officer_know_time'] ?? ''),
            'inspection_datetime' => ($data['inspect_date'] ?? '') . 'T' . ($data['inspect_time'] ?? ''),
            'inspection_additional_datetime' => ($data['inspect_additional_date'] ?? '') . 'T' . ($data['inspect_additional_time'] ?? ''),
            'inspection_end_datetime' => ($data['inspection_end_date'] ?? '') . 'T' . ($data['inspection_end_time'] ?? ''),

            // เอกสาร
            'report_no' => $data['report_no'] ?? '',
            'doc_no' => $data['doc_no'] ?? '',
            'record_seq' => $data['record_seq'] ?? '',
            'case_type' => 'life',
            'case_type_other' => '',

            // ช่องทางการรับแจ้ง
            'report_channel' => $data['notify_method'] ?? [],
            'report_channel_other' => $data['notify_method_other_text'] ?? '',

            // สน/สภ
            'source_station' => $data['police_station'] ?? '',
            'document_no' => $data['location_at'] ?? '',
            'document_date' => $data['record_date'] ?? '',

            // พนักงานสอบสวน
            'investigator' => [
                'firstname' => $data['investigator_name'] ?? '',
                'lastname' => '',
                'phone' => $data['investigator_phone'] ?? ''
            ],

            // สถานที่เกิดเหตุ
            'location_detail' => $data['crime_location'] ?? '',

            // ผู้ประสบเหตุ (คนแรก - สำหรับ PDF)
            'victim' => [
                'status' => $firstVictimStatus,
                'firstname' => $firstVictimName,
                'lastname' => '',
                'age' => $firstVictimAge
            ],

            // ผู้ประสบเหตุทั้งหมด (สำหรับใช้ในอนาคต)
            'all_victims' => $allVictims
        ],

        // ==========================================
        // 2. ลักษณะสถานที่เกิดเหตุ (scene_characteristics)
        // ==========================================
        'scene_characteristics' => [
            // การรักษาสถานที่
            'preservation' => normalizeValue($data['scene_preserved'] ?? ''),
            'preservation_detail' => $data['scene_preserved_no_text'] ?? '',

            // สภาพแวดล้อม
            'lighting' => $data['lighting'] ?? [],
            'lighting_other' => $data['lighting_other_text'] ?? '',
            'temperature' => $data['temperature'] ?? [],
            'temperature_other' => $data['temperature_other_text'] ?? '',
            'smell' => normalizeValue($data['smell'] ?? ''),

            // ภายนอกอาคาร (รองรับทั้ง standard form และ PDF form ที่มา scene_type[])
            'has_outdoor' => !empty($data['has_outdoor_incident_life']) || (isset($data['scene_type']) && is_array($data['scene_type']) && in_array('กรณีเกิดเหตุภายนอกอาคาร', $data['scene_type'])),
            'outdoor_type' => $data['outdoor_type'] ?? [],
            'outdoor_type_other' => $data['outdoor_type_other_text'] ?? '',
            'outdoor_entrance_condition' => $data['outdoor_entrance_condition'] ?? '',
            'outdoor_front_adjacent' => $data['outdoor_front_adjacent'] ?? '',
            'outdoor_left_adjacent' => $data['outdoor_left_adjacent'] ?? '',
            'outdoor_right_adjacent' => $data['outdoor_right_adjacent'] ?? '',
            'outdoor_back_adjacent' => $data['outdoor_back_adjacent'] ?? '',
            'outdoor_incident_area_detail' => $data['outdoor_incident_area_detail'] ?? '',

            // ภายในอาคาร (รองรับทั้ง standard form และ PDF form ที่มา incident_location_type[])
            'has_indoor' => !empty($data['has_indoor_incident_life']) || (isset($data['incident_location_type']) && is_array($data['incident_location_type']) && in_array('บริเวณภายในอาคาร', $data['incident_location_type'])),
            'building_type' => $data['building_type'] ?? [],
            'building_type_other' => $data['building_type_other_text'] ?? '',
            'fence' => normalizeValue($data['indoor_surrounding'] ?? ''),
            'interior_detail' => $data['indoor_interior_detail'] ?? '',

            // สภาพแวดล้อมและบริเวณโดยรอบ
            'entrance_condition' => $data['entrance_condition'] ?? '',
            'front_adjacent' => $data['front_adjacent'] ?? '',
            'left_adjacent' => $data['left_adjacent'] ?? '',
            'right_adjacent' => $data['right_adjacent'] ?? '',
            'back_adjacent' => $data['back_adjacent'] ?? '',

            // บริเวณที่เกิดเหตุ
            'incident_area_detail' => $data['incident_area_detail'] ?? '',

            // โครงสร้างบริเวณที่เกิดเหตุ
            'structure' => [
                'size' => $data['structure_size'] ?? '',
                'type' => is_array($data['structure_type'] ?? '') ? ($data['structure_type_text'] ?? '') : ($data['structure_type'] ?? ''),
                'wall' => normalizeValue($data['structure_wall'] ?? ''),
                'front' => $data['structure_front'] ?? '',
                'left' => $data['structure_left'] ?? '',
                'right' => $data['structure_right'] ?? '',
                'back' => $data['structure_back'] ?? '',
                'floor' => $data['structure_floor'] ?? '',
                'roof' => $data['structure_roof'] ?? '',
                'arrangement' => $data['structure_arrangement'] ?? ''
            ]
        ],

        // ==========================================
        // 3. ผลการตรวจสถานที่เกิดเหตุ (inspection_result)
        // ==========================================
        'inspection_result' => [
            // พฤติการณ์คดี
            'case_behavior' => $data['case_behavior'] ?? '',
            'entrance_exit' => $data['entrance_exit'] ?? '',
            'entrance_exit_detail' => $data['entrance_exit_detail'] ?? '',

            // ร่องรอย
            'fight_trace' => normalizeValue($data['fight_trace'] ?? ''),
            'fight_trace_detail' => $data['fight_trace_detail'] ?? '',
            'search_trace' => normalizeValue($data['search_trace'] ?? ''),

            // ข้อมูลศพ
            'body_found' => normalizeValue($data['body_status'] ?? ''),
            'body_location' => $data['body_location'] ?? '',
            'body_location_2' => $data['body_location_2'] ?? '',
            'body_condition' => $data['body_condition'] ?? '',

            // การแต่งกายและทรัพย์สิน
            'clothing' => [
                'shirt' => $data['clothing_shirt'] ?? '',
                'pants' => $data['clothing_pants'] ?? '',
                'shoes' => $data['clothing_shoes'] ?? '',
                'accessories' => $data['clothing_accessories'] ?? '',
                'tattoo' => $data['clothing_tattoo'] ?? '',
                'other' => $data['clothing_other'] ?? ''
            ],

            // สภาพรอยบาดแผล
            'wound' => [
                'status' => normalizeValue($data['wound_status'] ?? ''),
                'count' => $data['wound_count'] ?? '',
                'description' => $data['wound_description'] ?? '',
                'description_2' => $data['wound_description_2'] ?? '',
                'detail' => $data['wound_detail'] ?? ''
            ],

            // วัตถุพยานที่ตรวจพบ
            'evidence' => [
                'blood_stain' => $data['blood_stain_detail'] ?? '',
                'blood_test' => [
                    'hemastix_result' => normalizeValue($data['hemastix_result'] ?? ''),
                    'phenol_result' => normalizeValue($data['phenol_result'] ?? '')
                ],
                'other' => $data['other_evidence'] ?? ''
            ],

            // วัตถุพยานที่ตรวจเก็บ
            'collected_evidence' => [
                'gun' => $data['collected_gun_detail'] ?? '',
                'dna' => $data['collected_dna_detail'] ?? '',
                'fingerprint' => $data['fingerprint_detail'] ?? '',
                'other_type' => $data['other_evidence_type'] ?? ''
            ],

            // การตรวจสอบครั้งสุดท้าย
            'final_check' => $data['final_check'] ?? []
        ],

        // ==========================================
        // 4. ผู้ตรวจสถานที่เกิดเหตุ
        // ==========================================
        'inspectors' => $data['inspector_id'] ?? [],

        // ==========================================
        // 5. วัตถุพยาน (fieldset 10 & 12)
        // ==========================================
        'evidences' => $evidences,
        'evidence_meta' => [
            'reference_point_1' => $data['reference_point_1_life'] ?? '',
            'reference_point_2' => $data['reference_point_2_life'] ?? '',
            'reference_point_3' => $data['reference_point_3_life'] ?? '',
            'reference_point_4' => $data['reference_point_4_life'] ?? '',
            'collector_name' => $data['collector_name_life'] ?? '',
            'collection_datetime' => $data['collection_datetime_life'] ?? ''
        ],
        'measurements' => $measurements,
        'measurement_meta' => [
            'inspection_date' => $data['measurement_inspection_date_life'] ?? '',
            'inspection_time' => $data['measurement_inspection_time_life'] ?? '',
            'recorder' => $data['measurement_recorder_life'] ?? '',
            'datetime' => $data['measurement_datetime_life'] ?? ''
        ],

        // ==========================================
        // 6. การส่งมอบคืนสถานที่ (handover)
        // ==========================================
        'handover' => [
            'receiver_id' => $data['receiver_name'] ?? '',
            'receiver_pos' => $data['receiver_position'] ?? '',
            'deliverer_id' => $data['sender_name'] ?? '',
            'deliverer_pos' => $data['sender_position'] ?? '',
            'receiver_sig' => '',
            'deliverer_sig' => ''
        ],

        // ==========================================
        // 7. ข้อมูลเสริม (เก็บไว้สำหรับใช้ในอนาคต)
        // ==========================================
        'victim_info' => [
            'victim_types' => $data['victim_type_life'] ?? [],
            'victim_names' => $data['victim_name_life'] ?? [],
            'victim_ages' => $data['victim_age_life'] ?? []
        ],

        'body_info' => [
            'body_status' => normalizeValue($data['body_status'] ?? ''),
            'body_location' => $data['body_location'] ?? '',
            'body_condition' => $data['body_condition'] ?? '',
            'wound_status' => normalizeValue($data['wound_status'] ?? ''),
            'wound_count' => $data['wound_count'] ?? '',
            'wound_description' => $data['wound_description'] ?? '',
            'wound_detail' => $data['wound_detail'] ?? ''
        ],

        // ภาพถ่ายและลายเซ็น
        'photo_records' => [
            'photo_id_start' => $data['photo_id_start_life'] ?? '',
            'photo_id_end' => $data['photo_id_end_life'] ?? '',
            'photo_amount' => $data['photo_amount_life'] ?? '',
            'photographer_name' => $data['photographer_name_life'] ?? '',
            'photo_inspect_date' => $data['photo_inspect_date_life'] ?? '',
            'photo_inspect_time' => $data['photo_inspect_time_life'] ?? ''
        ],
        'signatures' => [],
        'photos' => [],

        // ==========================================
        // 8. ข้อมูล Fieldset 9, 11 (แผนผัง, ร่างกาย)
        // ==========================================
        'sketch_info' => [
            'remark' => $data['sketch_remark_life'] ?? '',
            'recorder' => $data['sketch_recorder_life'] ?? '',
            'datetime' => $data['sketch_datetime_life'] ?? ''
        ],
        'body_diagram_info' => [
            'victim_name' => $data['victim_name_life_diagram'] ?? normalizeValue($data['victim_name_life'] ?? ''),
            'victim_age' => $data['victim_age_life_diagram'] ?? normalizeValue($data['victim_age_life'] ?? ''),
            'autopsy_doctor' => $data['autopsy_doctor_life'] ?? '',
            'remark' => $data['body_diagram_remark_life'] ?? ''
        ],
        'body_diagram_strokes' => $data['body_diagram_strokes_life'] ?? ''
    ];

    // ============================================
    // 2. ตรวจสอบข้อมูลเดิม (ต้องทำก่อนเพื่อรู้ file_id เดิม)
    // ============================================

    $checkStmt = $pdo->prepare("SELECT id, incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = :incident_id LIMIT 1");
    $checkStmt->execute([':incident_id' => $incidentId]);
    $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

    $existingData = null;
    if ($existingRecord) {
        $existingData = json_decode($existingRecord['incident_checklist_data'], true);
        if ($existingData === null && !empty($existingRecord['incident_checklist_data'])) {
            error_log("WARNING: json_decode failed for incident_id={$incidentId} (Life). JSON may be truncated. json_last_error: " . json_last_error_msg());
        }
    }

    // ============================================
    // 3. บันทึกลายเซ็นต์ (Signatures → BLOB)
    // ============================================

    // Map: signature key → file field names [standard form, PDF form]
    $sigFileFields = [
        'scene_sketch'  => ['sig_file_scene_sketch', 'scene_sketch_file'],
        'receiver_sig'  => ['sig_file_receiver_signature', 'receiver_signature_file'],
        'sender_sig'    => ['sig_file_sender_signature', 'sender_signature_file'],
        'body_diagram'  => ['sig_file_body_diagram', 'body_diagram_file']
    ];

    // Map: signature key → base64 POST field names (fallback)
    $sigBase64Fields = [
        'scene_sketch'  => ['scene_sketch_data_life', 'scene_sketch_data'],
        'receiver_sig'  => ['receiver_signature_data_life', 'receiver_signature_data'],
        'sender_sig'    => ['sender_signature_data_life', 'sender_signature_data'],
        'body_diagram'  => ['body_diagram_data_life', 'body_diagram_data']
    ];

    // ดึง file_id เดิมจาก existing signatures
    $existingSigFileIds = [];
    if ($existingData && isset($existingData['signatures'])) {
        foreach ($existingData['signatures'] as $sigKey => $sigVal) {
            if (!empty($sigVal['file_id'])) {
                $existingSigFileIds[$sigKey] = (int) $sigVal['file_id'];
            }
        }
    }

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

        // Priority 1: $_FILES (Blob upload จาก standard หรือ PDF form)
        foreach ($fileFieldNames as $fieldName) {
            if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                $blobData = file_get_contents($_FILES[$fieldName]['tmp_name']);
                if ($blobData !== false && strlen($blobData) > 0) {
                    // ลบ BLOB เดิม (ถ้ามี)
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
        if (!$saved && $existingData && isset($existingData['signatures'][$sigKey])) {
            $checklistData['signatures'][$sigKey] = $existingData['signatures'][$sigKey];
        }
    }

    // Standardize key names for F-CS-11 generator.
    if (empty($checklistData['signatures']['deliverer_sig']) && !empty($checklistData['signatures']['sender_sig'])) {
        $checklistData['signatures']['deliverer_sig'] = $checklistData['signatures']['sender_sig'];
    }

    // ============================================
    // 4. บันทึกรูปภาพ (Photos → BLOB)
    // ============================================

    $existingPhotos = ($existingData) ? ($existingData['photos'] ?? []) : [];

    // 4.1 รับไฟล์รูปจาก $_FILES → BLOB
    $photoFieldNames = [];
    if (isset($_FILES['incident_photos_life']) && !empty($_FILES['incident_photos_life']['name'][0])) {
        $photoFieldNames[] = 'incident_photos_life';
    }
    if (isset($_FILES['incident_photos']) && !empty($_FILES['incident_photos']['name'][0])) {
        $photoFieldNames[] = 'incident_photos';
    }
    if (isset($_FILES['camera_photos_life']) && !empty($_FILES['camera_photos_life']['name'][0])) {
        $photoFieldNames[] = 'camera_photos_life';
    }

    $uploadErrors = [];

    foreach ($photoFieldNames as $photoFieldName) {
        $photos = $_FILES[$photoFieldName];
        $photoCount = count($photos['name']);

        for ($i = 0; $i < $photoCount; $i++) {
            if ($photos['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $photos['tmp_name'][$i];
                $originalName = $photos['name'][$i] ?? ('photo_' . ($i + 1) . '.jpg');
                $blobData = file_get_contents($tmpName);
                if ($blobData !== false && strlen($blobData) > 0) {
                    $blobData = compressImage($blobData, 2097152);
                    $newFileId = saveBlobToDb($pdo, $incidentId, $blobData);
                    $checklistData['photos'][] = ['file_id' => $newFileId, 'filename' => $originalName];
                }
            } else {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'ไฟล์ใหญ่เกินกว่า upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
                    UPLOAD_ERR_FORM_SIZE => 'ไฟล์ใหญ่เกินกว่า MAX_FILE_SIZE ในฟอร์ม',
                    UPLOAD_ERR_PARTIAL => 'ไฟล์อัปโหลดไม่ครบ',
                    UPLOAD_ERR_NO_FILE => 'ไม่มีไฟล์ที่อัปโหลด',
                    UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบ temp directory',
                    UPLOAD_ERR_CANT_WRITE => 'เขียนไฟล์ลงดิสก์ไม่ได้',
                    UPLOAD_ERR_EXTENSION => 'PHP extension หยุดการอัปโหลด',
                ];
                $errCode = $photos['error'][$i];
                $errMsg = $errorMessages[$errCode] ?? "Unknown error code: {$errCode}";
                $uploadErrors[] = "File '{$photos['name'][$i]}': {$errMsg}";
            }
        }
    }

    // 4.2 รับ base64 จาก JSON → BLOB (fallback)
    if (isset($data['photos']) && is_array($data['photos'])) {
        foreach ($data['photos'] as $photoIdx => $photo) {
            if (!empty($photo['base64'])) {
                $parts = explode(',', $photo['base64'], 2);
                $binaryData = base64_decode($parts[1] ?? $parts[0]);
                if ($binaryData !== false && strlen($binaryData) > 0) {
                    $binaryData = compressImage($binaryData, 2097152);
                    $originalName = $photo['filename'] ?? $photo['name'] ?? ('photo_' . ($photoIdx + 1) . '.jpg');
                    $newFileId = saveBlobToDb($pdo, $incidentId, $binaryData);
                    $checklistData['photos'][] = ['file_id' => $newFileId, 'filename' => $originalName];
                }
            }
        }
    }

    // Log upload errors (ถ้ามี)
    if (!empty($uploadErrors)) {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $logData = [
            'datetime' => date('Y-m-d H:i:s'),
            'incident_id' => $incidentId,
            'total_photos_saved' => count($checklistData['photos']),
            'errors' => $uploadErrors,
            'php_upload_max_filesize' => ini_get('upload_max_filesize'),
            'php_post_max_size' => ini_get('post_max_size'),
            'php_max_file_uploads' => ini_get('max_file_uploads'),
            'FILES_keys' => array_keys($_FILES)
        ];
        file_put_contents($logDir . '/saveLife_upload_errors_' . $incidentId . '.json', json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    // 4.3 ถ้า UPDATE: ลบรูปที่ user ต้องการลบ + รักษารูปเดิม
    if ($existingRecord && !empty($existingPhotos)) {
        // รับรายการ file_id ที่ต้องการลบ (BLOB)
        $deletedPhotoFileIds = [];
        if (isset($_POST['deleted_photo_file_ids'])) {
            $deletedPhotoFileIds = json_decode($_POST['deleted_photo_file_ids'], true) ?? [];
        }

        // Fallback: filename-based deletion (legacy)
        $deletedPhotoFilenames = [];
        if (isset($_POST['deleted_photos'])) {
            $deletedPhotoFilenames = json_decode($_POST['deleted_photos'], true) ?? [];
        }

        $keptPhotos = [];
        foreach ($existingPhotos as $photo) {
            $shouldDelete = false;

            if (!empty($photo['file_id']) && in_array((int) $photo['file_id'], array_map('intval', $deletedPhotoFileIds))) {
                // ลบ BLOB จาก DB
                deleteBlobFromDb($pdo, (int) $photo['file_id']);
                $shouldDelete = true;
            } elseif (!empty($photo['filename']) && in_array($photo['filename'], $deletedPhotoFilenames)) {
                // Legacy: ลบไฟล์จากดิสก์
                $legacyPath = __DIR__ . '/../../uploads/checklist_life_photos/' . $photo['filename'];
                if (file_exists($legacyPath)) {
                    @unlink($legacyPath);
                }
                $shouldDelete = true;
            }

            if (!$shouldDelete) {
                $keptPhotos[] = $photo;
            }
        }

        // รวมรูปเก่าที่เหลือ + รูปใหม่
        $checklistData['photos'] = array_merge($keptPhotos, $checklistData['photos']);
    }

    // ============================================
    // 5. แปลงเป็น JSON สำหรับบันทึกลง DB
    // ============================================

    $jsonChecklistData = json_encode($checklistData, JSON_UNESCAPED_UNICODE);

    if ($existingRecord) {
        // ============================================
        // UPDATE - อัปเดตข้อมูลที่มีอยู่
        // ============================================

        $sql = "UPDATE incident_checklist_transaction SET
                    incident_checklist_data = :checklist_data,
                    edit_by = :edit_by,
                    edit_date = NOW(),
                    count_edit = count_edit + 1
                WHERE incident_id = :incident_id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':checklist_data' => $jsonChecklistData,
            ':edit_by' => $userId,
            ':incident_id' => $incidentId
        ]);

        $recordId = $existingRecord['id'];
        $action = 'updated';

    } else {
        // ============================================
        // INSERT - สร้างข้อมูลใหม่
        // ============================================

        $sql = "INSERT INTO incident_checklist_transaction
                (incident_id, incident_checklist_data, incident_report_data, create_by, create_date)
                VALUES
                (:incident_id, :checklist_data, :report_data, :create_by, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':incident_id' => $incidentId,
            ':checklist_data' => $jsonChecklistData,
            ':report_data' => $jsonChecklistData,
            ':create_by' => $userId
        ]);

        $recordId = $pdo->lastInsertId();
        $action = 'created';
    }

    // ============================================
    // 6. อัปเดตสถานะ Checklist ใน rn_ReceiveNoti
    // ============================================

    $updateStatusSql = "UPDATE rn_ReceiveNoti SET statusChecklist = 1 WHERE id = :incident_id";
    $updateStatusStmt = $pdo->prepare($updateStatusSql);
    $updateStatusStmt->execute([':incident_id' => $incidentId]);

    $pdo->commit();

    // ============================================
    // Response
    // ============================================

    jsonResponse(true, 'บันทึกข้อมูลเรียบร้อยแล้ว', [
        'id' => $recordId,
        'incident_id' => $incidentId,
        'action' => $action,
        'signatures_saved' => count($checklistData['signatures']),
        'photos_saved' => count($checklistData['photos'])
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Life Checklist Save Error: " . $e->getMessage());
    jsonResponse(false, 'เกิดข้อผิดพลาดฐานข้อมูล: ' . $e->getMessage(), null, 500);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Life Checklist Save Error: " . $e->getMessage());
    jsonResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage(), null, 500);
}
