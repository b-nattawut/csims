<?php
/**
 * API: Get Life Report Data
 * ดึงข้อมูล incident_report_data จาก incident_checklist_transaction
 * สำหรับโหลดข้อมูลร่างรายงานคดีชีวิต (ในอาคาร/นอกอาคาร)
 * 
 * ถ้า incident_report_data เป็น format เดิม (copy จาก checklist) จะ map ให้เป็น report field names
 * ถ้าเป็น format ใหม่ (มี indoor/outdoor key) จะส่งคืนตรงๆ
 */
require_once '../../db_config.php';

header('Content-Type: application/json; charset=utf-8');

$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// Helper: แยก datetime "2026-01-15T14:30" → ['date' => '2026-01-15', 'time' => '14:30']
// ============================================
function splitDatetime($dt) {
    if (empty($dt) || $dt === 'T') return ['date' => '', 'time' => ''];
    $parts = explode('T', $dt, 2);
    return [
        'date' => $parts[0] ?? '',
        'time' => $parts[1] ?? ''
    ];
}

// ============================================
// Helper: map notify_method ค่า checklist → report checkbox values
// Checklist ใช้ "ทางโทรศัพท์", "ทางวิทยุสื่อสาร", "ทางหนังสือ"
// Report indoor ใช้ "หนังสือ", "โทรศัพท์", "วิทยุสื่อสาร"
// ============================================
function mapNotifyMethods($methods, $isOutdoor = false) {
    if (!is_array($methods)) return [];
    $map = [
        'ทางโทรศัพท์' => 'โทรศัพท์',
        'ทางวิทยุสื่อสาร' => $isOutdoor ? 'วิทยุ' : 'วิทยุสื่อสาร',
        'ทางหนังสือ' => 'หนังสือ'
    ];
    $result = [];
    foreach ($methods as $m) {
        $result[] = $map[$m] ?? $m;
    }
    return $result;
}

// ============================================
// Helper: map building_type ค่า checklist → report checkbox values
// Checklist ใช้ "อาคารพาณิชย์", "บ้านเดี่ยว", "ทาวน์เฮาส์", "อื่นๆ"
// Report ใช้ "บ้าน", "ตึกแถว", "อาคาร", "อื่นๆ"
// ============================================
function mapBuildingTypes($checklistTypes, $checklistOther = '') {
    if (!is_array($checklistTypes)) return ['types' => [], 'other_text' => $checklistOther];
    
    $reportValues = ['บ้าน', 'ตึกแถว', 'อาคาร', 'อื่นๆ'];
    $map = [
        'บ้านเดี่ยว' => 'บ้าน',
        'อาคารพาณิชย์' => 'ตึกแถว',
        'ทาวน์เฮาส์' => 'บ้าน',
    ];
    
    $result = [];
    $otherParts = [];
    if (!empty($checklistOther)) $otherParts[] = $checklistOther;
    
    foreach ($checklistTypes as $t) {
        if (in_array($t, $reportValues)) {
            if (!in_array($t, $result)) $result[] = $t;
        } elseif (isset($map[$t])) {
            if (!in_array($map[$t], $result)) $result[] = $map[$t];
        } else {
            if (!in_array('อื่นๆ', $result)) $result[] = 'อื่นๆ';
            $otherParts[] = $t;
        }
    }
    
    return [
        'types' => $result,
        'other_text' => implode(', ', $otherParts)
    ];
}

// ============================================
// Map checklist JSON → indoor report fields (rli_*)
// ============================================
function mapChecklistToIndoor($ck) {
    $gi = $ck['general_info'] ?? [];
    $sc = $ck['scene_characteristics'] ?? [];
    $ir = $ck['inspection_result'] ?? [];
    $st = $sc['structure'] ?? [];
    $ho = $ck['handover'] ?? [];

    $reportDt = splitDatetime($gi['report_datetime'] ?? '');
    $victimDt = splitDatetime($gi['incident_datetime'] ?? '');
    $officerDt = splitDatetime($gi['investigator_known_datetime'] ?? '');
    $inspectDt = splitDatetime($gi['inspection_datetime'] ?? '');
    $inspectAddDt = splitDatetime($gi['inspection_additional_datetime'] ?? '');
    $inspectEndDt = splitDatetime($gi['inspection_end_datetime'] ?? '');

    $victim = $gi['victim'] ?? [];
    $investigator = $gi['investigator'] ?? [];
    $wound = $ir['wound'] ?? [];
    $clothing = $ir['clothing'] ?? [];

    // สร้าง clothing summary text จาก clothing object
    $clothingText = '';
    $clothingParts = [];
    if (!empty($clothing['shirt'])) $clothingParts[] = 'เสื้อ: ' . $clothing['shirt'];
    if (!empty($clothing['pants'])) $clothingParts[] = 'กางเกง: ' . $clothing['pants'];
    if (!empty($clothing['shoes'])) $clothingParts[] = 'รองเท้า/ถุงเท้า: ' . $clothing['shoes'];
    if (!empty($clothing['accessories'])) $clothingParts[] = 'เครื่องประดับ: ' . $clothing['accessories'];
    if (!empty($clothing['tattoo'])) $clothingParts[] = 'รอยสัก/แผลเป็น: ' . $clothing['tattoo'];
    if (!empty($clothing['other'])) $clothingParts[] = 'อื่นๆ: ' . $clothing['other'];
    $clothingText = implode("\n", $clothingParts);

    // สร้าง wound summary
    $woundText = '';
    $woundParts = [];
    if (!empty($wound['status'])) $woundParts[] = $wound['status'];
    if (!empty($wound['count'])) $woundParts[] = 'จำนวน ' . $wound['count'] . ' แห่ง';
    if (!empty($wound['description'])) $woundParts[] = $wound['description'];
    if (!empty($wound['detail'])) $woundParts[] = $wound['detail'];
    $woundText = implode("\n", $woundParts);

    $buildingMapping = mapBuildingTypes($sc['building_type'] ?? [], $sc['building_type_other'] ?? '');

    return [
        // Section 1: การรับแจ้งเหตุ
        'rli_receive_date' => $reportDt['date'],
        'rli_receive_time' => $reportDt['time'],
        'rli_daily_ref' => $gi['document_no'] ?? '',
        'rli_agency_type' => '', // checklist ไม่มีฟิลด์นี้
        'rli_agency_name' => '',
        'rli_notify_method[]' => mapNotifyMethods($gi['report_channel'] ?? []),
        'rli_from_station' => $gi['source_station'] ?? '',
        'rli_investigator' => $investigator['firstname'] ?? '',

        // Section 2: สถานที่เกิดเหตุ
        'rli_crime_location' => $gi['location_detail'] ?? '',
        'rli_victim_name' => $victim['firstname'] ?? '',
        'rli_victim_age' => $victim['age'] ?? '',

        // Section 3: วันเวลาที่ทราบเหตุ
        'rli_victim_know_date' => $victimDt['date'],
        'rli_victim_know_time' => $victimDt['time'],
        'rli_officer_know_date' => $officerDt['date'],
        'rli_officer_know_time' => $officerDt['time'],

        // Section 4: วันเวลาตรวจสถานที่
        'rli_inspect_date' => $inspectDt['date'],
        'rli_inspect_time' => $inspectDt['time'],
        'rli_inspect_add_date' => $inspectAddDt['date'],
        'rli_inspect_add_time' => $inspectAddDt['time'],

        // Section 5: ผู้ตรวจ (checklist เก็บแค่ inspector_id => ทำ resolve ด้านล่าง)
        // จะถูกเพิ่มหลังจาก resolve inspector names

        // Section 6: ลักษณะสถานที่เกิดเหตุ (ในอาคาร)
        'rli_building_type[]' => $buildingMapping['types'],
        'rli_building_type_other' => $buildingMapping['other_text'],
        'rli_floor_count' => '',
        'rli_unit_count' => '',
        'rli_mezzanine' => '',
        'rli_rooftop' => '',
        'rli_fence' => ($sc['fence'] ?? '') === 'มีรั้ว' ? 'มี' : (($sc['fence'] ?? '') === 'ไม่มีรั้ว' ? 'ไม่มี' : ''),
        'rli_ext_front' => $sc['front_adjacent'] ?? '',
        'rli_ext_left' => $sc['left_adjacent'] ?? '',
        'rli_ext_right' => $sc['right_adjacent'] ?? '',
        'rli_ext_back' => $sc['back_adjacent'] ?? '',
        'rli_interior_detail' => $sc['interior_detail'] ?? '',
        'rli_incident_area' => $sc['incident_area_detail'] ?? '',
        'rli_area_size' => $st['size'] ?? '',
        'rli_wall_front' => $st['front'] ?? '',
        'rli_wall_left' => $st['left'] ?? '',
        'rli_wall_right' => $st['right'] ?? '',
        'rli_wall_back' => $st['back'] ?? '',
        'rli_wall_front_window' => '',
        'rli_wall_front_door' => '',
        'rli_wall_left_window' => '',
        'rli_wall_left_door' => '',
        'rli_wall_right_window' => '',
        'rli_wall_right_door' => '',
        'rli_wall_back_window' => '',
        'rli_wall_back_door' => '',
        'rli_floor_material' => $st['floor'] ?? '',
        'rli_ceiling' => '',
        'rli_roof' => $st['roof'] ?? '',
        'rli_arrange_front' => $st['arrangement'] ?? '',

        // Section 7: ผลการตรวจสถานที่เกิดเหตุ
        'rli_case_behavior' => $ir['case_behavior'] ?? '',
        'rli_inspection_detail' => '',
        'rli_scene_condition' => trim(implode("\n", array_filter([
            $sc['preservation'] ?? '',
            $sc['preservation_detail'] ?? ''
        ]))),
        'rli_body_found' => $ir['body_found'] ?? '',
        'rli_body_position' => $ir['body_location'] ?? '',
        'rli_body_condition' => $ir['body_condition'] ?? '',
        'rli_body_clothing' => $clothingText,
        'rli_body_wounds' => $woundText,
        'rli_evidence_found' => $ir['evidence']['other'] ?? '',
        'rli_evidence_collected' => '',
        'rli_evidence_action' => '',

        // Section 7.6: การส่งมอบ
        'rli_handover_officer' => $ho['deliverer_id'] ?? '',
        'rli_handover_to' => $ho['receiver_id'] ?? '',
        'rli_handover_date' => $inspectEndDt['date'],
        'rli_handover_time' => $inspectEndDt['time'],

        // ลงชื่อผู้รายงาน
        'rli_signer_name' => $ho['deliverer_id'] ?? '',
        'rli_signer_position' => $ho['deliverer_pos'] ?? '',
        'rli_sign_date' => $inspectEndDt['date'],
    ];
}

// ============================================
// Map checklist JSON → outdoor report fields (rlo_*)
// ============================================
function mapChecklistToOutdoor($ck) {
    $gi = $ck['general_info'] ?? [];
    $sc = $ck['scene_characteristics'] ?? [];
    $ir = $ck['inspection_result'] ?? [];
    $ho = $ck['handover'] ?? [];

    $reportDt = splitDatetime($gi['report_datetime'] ?? '');
    $victimDt = splitDatetime($gi['incident_datetime'] ?? '');
    $officerDt = splitDatetime($gi['investigator_known_datetime'] ?? '');
    $inspectDt = splitDatetime($gi['inspection_datetime'] ?? '');
    $inspectAddDt = splitDatetime($gi['inspection_additional_datetime'] ?? '');
    $inspectEndDt = splitDatetime($gi['inspection_end_datetime'] ?? '');

    $victim = $gi['victim'] ?? [];
    $investigator = $gi['investigator'] ?? [];
    $wound = $ir['wound'] ?? [];
    $clothing = $ir['clothing'] ?? [];

    // สร้าง clothing summary
    $clothingParts = [];
    if (!empty($clothing['shirt'])) $clothingParts[] = 'เสื้อ: ' . $clothing['shirt'];
    if (!empty($clothing['pants'])) $clothingParts[] = 'กางเกง: ' . $clothing['pants'];
    if (!empty($clothing['shoes'])) $clothingParts[] = 'รองเท้า/ถุงเท้า: ' . $clothing['shoes'];
    if (!empty($clothing['accessories'])) $clothingParts[] = 'เครื่องประดับ: ' . $clothing['accessories'];
    if (!empty($clothing['tattoo'])) $clothingParts[] = 'รอยสัก/แผลเป็น: ' . $clothing['tattoo'];
    if (!empty($clothing['other'])) $clothingParts[] = 'อื่นๆ: ' . $clothing['other'];
    $clothingText = implode("\n", $clothingParts);

    // สร้าง wound summary
    $woundParts = [];
    if (!empty($wound['status'])) $woundParts[] = $wound['status'];
    if (!empty($wound['count'])) $woundParts[] = 'จำนวน ' . $wound['count'] . ' แห่ง';
    if (!empty($wound['description'])) $woundParts[] = $wound['description'];
    if (!empty($wound['detail'])) $woundParts[] = $wound['detail'];
    $woundText = implode("\n", $woundParts);

    // สร้าง scene characteristics summary (outdoor ใช้ textarea เดียว)
    $sceneParts = [];
    if (!empty($sc['outdoor_type'])) {
        $sceneParts[] = 'ประเภท: ' . implode(', ', $sc['outdoor_type']);
    }
    if (!empty($sc['outdoor_entrance_condition'])) {
        $sceneParts[] = 'สภาพทางเข้า: ' . $sc['outdoor_entrance_condition'];
    }
    $adjParts = [];
    if (!empty($sc['outdoor_front_adjacent'])) $adjParts[] = 'ด้านหน้า: ' . $sc['outdoor_front_adjacent'];
    if (!empty($sc['outdoor_left_adjacent'])) $adjParts[] = 'ด้านซ้าย: ' . $sc['outdoor_left_adjacent'];
    if (!empty($sc['outdoor_right_adjacent'])) $adjParts[] = 'ด้านขวา: ' . $sc['outdoor_right_adjacent'];
    if (!empty($sc['outdoor_back_adjacent'])) $adjParts[] = 'ด้านหลัง: ' . $sc['outdoor_back_adjacent'];
    if (!empty($adjParts)) $sceneParts[] = "บริเวณโดยรอบ:\n" . implode("\n", $adjParts);
    if (!empty($sc['outdoor_incident_area_detail'])) {
        $sceneParts[] = 'บริเวณเกิดเหตุ: ' . $sc['outdoor_incident_area_detail'];
    }
    // lighting
    if (!empty($sc['lighting'])) $sceneParts[] = 'แสงสว่าง: ' . implode(', ', $sc['lighting']);
    if (!empty($sc['temperature'])) $sceneParts[] = 'อุณหภูมิ: ' . implode(', ', $sc['temperature']);
    if (!empty($sc['preservation'])) $sceneParts[] = 'การรักษาสถานที่: ' . $sc['preservation'];
    $sceneText = implode("\n", $sceneParts);

    return [
        // Section 1: การรับแจ้งเหตุ
        'rlo_receive_date' => $reportDt['date'],
        'rlo_receive_time' => $reportDt['time'],
        'rlo_daily_ref' => $gi['document_no'] ?? '',
        'rlo_agency_type' => '',
        'rlo_agency_name' => '',
        'rlo_notify_method[]' => mapNotifyMethods($gi['report_channel'] ?? [], true),
        'rlo_from_station' => $gi['source_station'] ?? '',
        'rlo_investigator' => $investigator['firstname'] ?? '',

        // Section 2: สถานที่เกิดเหตุ
        'rlo_crime_location' => $gi['location_detail'] ?? '',
        'rlo_victim_name' => $victim['firstname'] ?? '',
        'rlo_victim_age' => $victim['age'] ?? '',

        // Section 3: วันเวลาที่ทราบเหตุ
        'rlo_victim_know_date' => $victimDt['date'],
        'rlo_victim_know_time' => $victimDt['time'],
        'rlo_officer_know_date' => $officerDt['date'],
        'rlo_officer_know_time' => $officerDt['time'],

        // Section 4: วันเวลาตรวจสถานที่
        'rlo_inspect_date' => $inspectDt['date'],
        'rlo_inspect_time' => $inspectDt['time'],
        'rlo_inspect_add_date' => $inspectAddDt['date'],
        'rlo_inspect_add_time' => $inspectAddDt['time'],

        // Section 6: ลักษณะสถานที่เกิดเหตุ (textarea เดียว)
        'rlo_scene_characteristics' => $sceneText,

        // Section 7: ผลการตรวจสถานที่เกิดเหตุ
        'rlo_case_behavior' => $ir['case_behavior'] ?? '',
        'rlo_scene_condition' => trim(implode("\n", array_filter([
            $sc['preservation'] ?? '',
            $sc['preservation_detail'] ?? ''
        ]))),
        'rlo_body_found' => $ir['body_found'] ?? '',
        'rlo_body_position' => $ir['body_location'] ?? '',
        'rlo_body_condition' => $ir['body_condition'] ?? '',
        'rlo_body_clothing' => $clothingText,
        'rlo_body_wounds' => $woundText,
        'rlo_evidence_found' => $ir['evidence']['other'] ?? '',
        'rlo_evidence_collected' => '',
        'rlo_evidence_action' => '',

        // Section 7.6: การส่งมอบ
        'rlo_handover_officer' => $ho['deliverer_id'] ?? '',
        'rlo_handover_to' => $ho['receiver_id'] ?? '',
        'rlo_handover_date' => $inspectEndDt['date'],
        'rlo_handover_time' => $inspectEndDt['time'],

        // ลงชื่อผู้รายงาน
        'rlo_signer_name' => $ho['deliverer_id'] ?? '',
        'rlo_signer_position' => $ho['deliverer_pos'] ?? '',
        'rlo_sign_date' => $inspectEndDt['date'],
    ];
}

// ============================================
// Resolve single user ID → fullname + position
// ============================================
function resolveUserId($pdo, $userId) {
    if (empty($userId)) return ['fullname' => '', 'position' => ''];
    $stmt = $pdo->prepare("SELECT CONCAT(IFNULL(t3.rank_name, ''), ' ', t2.first_name, ' ', t2.last_name) as fullname,
                                  IFNULL(t4.position_name, '') as position
                           FROM users t1
                           INNER JOIN user_profile t2 ON t1.user_id = t2.user_id
                           LEFT JOIN user_rank t3 ON t2.rank_id = t3.rank_id
                           LEFT JOIN user_position t4 ON t2.position_id = t4.position_id
                           WHERE t1.user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : ['fullname' => '', 'position' => ''];
}

// ============================================
// Resolve user IDs → names in final output data
// แปลงค่าตัวเลข user ID เป็นชื่อจริง (ยศ + ชื่อ + นามสกุล)
// ============================================
function resolveUserIdsInData($pdo, &$data) {
    foreach (['indoor' => 'rli', 'outdoor' => 'rlo'] as $t => $p) {
        if (!isset($data[$t]) || !is_array($data[$t])) continue;
        // Fields ที่ต้อง resolve เป็น fullname
        $nameFields = [$p.'_handover_officer', $p.'_handover_to', $p.'_signer_name'];
        foreach ($nameFields as $fk) {
            if (isset($data[$t][$fk]) && is_numeric($data[$t][$fk])) {
                $resolved = resolveUserId($pdo, $data[$t][$fk]);
                $data[$t][$fk] = $resolved['fullname'];
            }
        }
        // signer_position
        $posField = $p.'_signer_position';
        if (isset($data[$t][$posField]) && is_numeric($data[$t][$posField])) {
            $resolved = resolveUserId($pdo, $data[$t][$posField]);
            $data[$t][$posField] = $resolved['position'];
        }
    }
}

// ============================================
// Resolve inspector IDs → names + positions
// ============================================
function resolveInspectors($pdo, $inspectorIds, $prefix) {
    $result = [
        $prefix . '_inspector_id[]' => [],
        $prefix . '_inspector_name[]' => [],
        $prefix . '_inspector_position[]' => []
    ];
    if (empty($inspectorIds) || !is_array($inspectorIds)) return $result;

    // กรองเฉพาะ IDs ที่มีค่า
    $validIds = array_filter($inspectorIds, function($id) { return !empty($id); });
    if (empty($validIds)) return $result;

    $placeholders = implode(',', array_fill(0, count($validIds), '?'));
    $stmt = $pdo->prepare("SELECT t1.user_id as id, 
                                  CONCAT(IFNULL(t3.rank_name, ''), ' ', t2.first_name, ' ', t2.last_name) as fullname, 
                                  IFNULL(t4.position_name, '') as position 
                           FROM users t1
                           INNER JOIN user_profile t2 ON t1.user_id = t2.user_id
                           LEFT JOIN user_rank t3 ON t2.rank_id = t3.rank_id
                           LEFT JOIN user_position t4 ON t2.position_id = t4.position_id
                           WHERE t1.user_id IN ($placeholders)");
    $stmt->execute(array_values($validIds));
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // สร้าง lookup map
    $userMap = [];
    foreach ($users as $u) {
        $userMap[$u['id']] = $u;
    }

    // ใส่ตาม order เดิม
    foreach ($inspectorIds as $id) {
        if (isset($userMap[$id])) {
            $result[$prefix . '_inspector_id[]'][] = $id;
            $result[$prefix . '_inspector_name[]'][] = $userMap[$id]['fullname'] ?? '';
            $result[$prefix . '_inspector_position[]'][] = $userMap[$id]['position'] ?? '';
        } else {
            $result[$prefix . '_inspector_id[]'][] = $id;
            $result[$prefix . '_inspector_name[]'][] = '';
            $result[$prefix . '_inspector_position[]'][] = '';
        }
    }

    return $result;
}

try {
    // ดึง agency info จาก rn_ReceiveNoti
    $stmtAgency = $pdo->prepare("SELECT userReviewType, userReviewTypeVal FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
    $stmtAgency->execute([$incident_id]);
    $agencyRow = $stmtAgency->fetch(PDO::FETCH_ASSOC);
    
    $agencyType = '';
    $agencyName = '';
    if ($agencyRow) {
        $provinceMap = ['95' => 'ยะลา', '94' => 'ปัตตานี', '96' => 'นราธิวาส'];
        $rt = $agencyRow['userReviewType'] ?? '';
        $rv = $agencyRow['userReviewTypeVal'] ?? '';
        if ($rt === 'nvt') {
            $agencyType = 'กสก.พฐก.';
            $agencyName = 'นวท.(สบ ' . $rv . ') กสก.พฐก.';
        } elseif ($rt === 'spt') {
            $agencyType = 'กลก.ศพฐ.';
            $agencyName = 'ศพฐ ' . $rv;
        } elseif ($rt === 'ptjv') {
            $agencyType = 'พฐ.จว.';
            $agencyName = 'พฐ.จว.' . ($provinceMap[strval($rv)] ?? $rv);
        }
    }

    $stmt = $pdo->prepare("SELECT incident_report_data, incident_checklist_data, count_report, edit_date 
                           FROM incident_checklist_transaction 
                           WHERE incident_id = ? 
                           ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'No data found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ดึง location_type จาก checklist data
    $locationType = 'unknown';
    $checklistData = null;
    if (!empty($result['incident_checklist_data'])) {
        $checklistData = json_decode($result['incident_checklist_data'], true);
        if (isset($checklistData['scene_characteristics'])) {
            $sc = $checklistData['scene_characteristics'];
            if (!empty($sc['has_outdoor'])) {
                $locationType = 'outdoor';
            } elseif (!empty($sc['has_indoor'])) {
                $locationType = 'indoor';
            } else {
                if (!empty($sc['outdoor_type'])) {
                    $locationType = 'outdoor';
                } elseif (!empty($sc['building_type'])) {
                    $locationType = 'indoor';
                }
            }
        }
    }

    // ดึง report data
    $reportData = null;
    if (!empty($result['incident_report_data'])) {
        $reportData = json_decode($result['incident_report_data'], true);
    }

    // ตรวจว่า reportData เป็น format ใหม่ (มี 'indoor' หรือ 'outdoor' key จาก saveLifeReport.php)
    // หรือ format เดิม (copy จาก checklist: มี 'general_info' key)
    $isNewFormat = isset($reportData['report_type']) || isset($reportData['indoor']) || isset($reportData['outdoor']);
    $isChecklistFormat = isset($reportData['general_info']);

    $countEdit = (int)($result['count_report'] ?? 0);
    $editDate = $result['edit_date'] ?? '';

    // ====================================================================
    // เตรียมข้อมูลจาก checklist เป็นค่าเริ่มต้น (ใช้ได้ทุกกรณี)
    // ====================================================================
    $checklistMapped = null;
    if ($checklistData) {
        $checklistMapped = ['report_type' => $locationType];

        $indoorFields = mapChecklistToIndoor($checklistData);
        $inspectors = $checklistData['inspectors'] ?? [];
        $inspectorFields = resolveInspectors($pdo, $inspectors, 'rli');
        $checklistMapped['indoor'] = array_merge($indoorFields, $inspectorFields);

        $outdoorFields = mapChecklistToOutdoor($checklistData);
        $inspectorFieldsOutdoor = resolveInspectors($pdo, $inspectors, 'rlo');
        $checklistMapped['outdoor'] = array_merge($outdoorFields, $inspectorFieldsOutdoor);

        // Resolve handover/signer user IDs → ชื่อจริง
        $ho = $checklistData['handover'] ?? [];
        $deliverer = resolveUserId($pdo, $ho['deliverer_id'] ?? '');
        $receiver = resolveUserId($pdo, $ho['receiver_id'] ?? '');
        foreach (['indoor' => 'rli', 'outdoor' => 'rlo'] as $t => $p) {
            $checklistMapped[$t][$p . '_handover_officer'] = $deliverer['fullname'];
            $checklistMapped[$t][$p . '_handover_to'] = $receiver['fullname'];
            $checklistMapped[$t][$p . '_signer_name'] = $deliverer['fullname'];
            $checklistMapped[$t][$p . '_signer_position'] = $deliverer['position'];
            // Agency info จาก rn_ReceiveNoti
            $checklistMapped[$t][$p . '_agency_type'] = $agencyType;
            $checklistMapped[$t][$p . '_agency_name'] = $agencyName;
        }
    }

    if ($isNewFormat) {
        // format ใหม่ => merge ข้อมูล checklist เป็นค่าเริ่มต้น + ข้อมูล report ทับด้านบน
        if ($checklistMapped) {
            foreach (['indoor', 'outdoor'] as $type) {
                if (!isset($reportData[$type])) {
                    // ยังไม่เคยบันทึก type นี้ → ใช้ checklist ทั้งหมด
                    $reportData[$type] = $checklistMapped[$type] ?? [];
                } else {
                    // มีข้อมูล report แล้ว → เติมเฉพาะ field ที่ว่าง (ไม่ทับข้อมูลที่ user กรอกไว้)
                    $baseFields = $checklistMapped[$type] ?? [];
                    if (is_array($baseFields)) {
                        foreach ($baseFields as $k => $v) {
                            if (!isset($reportData[$type][$k]) 
                                || $reportData[$type][$k] === '' 
                                || $reportData[$type][$k] === []) {
                                $reportData[$type][$k] = $v;
                            }
                        }
                    }
                }
            }
        }

        // Resolve user IDs → ชื่อจริง
        resolveUserIdsInData($pdo, $reportData);

        // Agency fallback: ถ้ายังว่างอยู่ ใส่จาก rn_ReceiveNoti
        foreach (['indoor' => 'rli', 'outdoor' => 'rlo'] as $type => $p) {
            if (isset($reportData[$type]) && is_array($reportData[$type])) {
                if (empty($reportData[$type][$p . '_agency_type'])) {
                    $reportData[$type][$p . '_agency_type'] = $agencyType;
                }
                if (empty($reportData[$type][$p . '_agency_name'])) {
                    $reportData[$type][$p . '_agency_name'] = $agencyName;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $reportData,
            'location_type' => $locationType,
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'format' => 'report'
        ], JSON_UNESCAPED_UNICODE);
    } elseif ($isChecklistFormat) {
        // format เดิม (copy จาก checklist) => map เป็น report field names
        $mapped = [
            'report_type' => $locationType
        ];

        $indoorFields = mapChecklistToIndoor($reportData);
        $inspectors = $reportData['inspectors'] ?? [];
        $inspectorFields = resolveInspectors($pdo, $inspectors, 'rli');
        $mapped['indoor'] = array_merge($indoorFields, $inspectorFields);

        $outdoorFields = mapChecklistToOutdoor($reportData);
        $inspectorFieldsOutdoor = resolveInspectors($pdo, $inspectors, 'rlo');
        $mapped['outdoor'] = array_merge($outdoorFields, $inspectorFieldsOutdoor);

        // Agency info จาก rn_ReceiveNoti
        $mapped['indoor']['rli_agency_type'] = $agencyType;
        $mapped['indoor']['rli_agency_name'] = $agencyName;
        $mapped['outdoor']['rlo_agency_type'] = $agencyType;
        $mapped['outdoor']['rlo_agency_name'] = $agencyName;

        // Resolve user IDs → ชื่อจริง
        resolveUserIdsInData($pdo, $mapped);

        echo json_encode([
            'success' => true,
            'data' => $mapped,
            'location_type' => $locationType,
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'format' => 'mapped_from_checklist'
        ], JSON_UNESCAPED_UNICODE);
    } elseif ($checklistMapped) {
        // ยังไม่เคยบันทึก report → ใช้ข้อมูลจาก checklist เป็นค่าเริ่มต้น
        resolveUserIdsInData($pdo, $checklistMapped);
        echo json_encode([
            'success' => true,
            'data' => $checklistMapped,
            'location_type' => $locationType,
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'format' => 'mapped_from_checklist'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // ไม่มีข้อมูลเลย
        echo json_encode([
            'success' => true,
            'data' => $reportData,
            'location_type' => $locationType,
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'format' => 'unknown'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
