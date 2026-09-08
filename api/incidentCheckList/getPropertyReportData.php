<?php
/**
 * API: Get Property Report Data
 * ดึงข้อมูล incident_report_data จาก incident_checklist_transaction
 * สำหรับโหลดข้อมูลร่างรายงานคดีทรัพย์ (F-CS-05)
 * 
 * ถ้า incident_report_data มี key "property" จะส่งคืนตรงๆ
 * ถ้ายังไม่เคยบันทึก จะ map จาก checklist data เป็นค่าเริ่มต้น
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
// ============================================
function mapNotifyMethods($methods) {
    if (!is_array($methods)) return [];
    $map = [
        'ทางโทรศัพท์' => 'โทรศัพท์',
        'ทางวิทยุสื่อสาร' => 'วิทยุสื่อสาร',
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
// Map checklist JSON → property report fields (rp_*)
// ============================================
function mapChecklistToProperty($ck) {
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

    $buildingMapping = mapBuildingTypes($sc['building_type'] ?? [], $sc['building_type_other'] ?? '');

    return [
        // Section 1: การรับแจ้งเหตุ
        'rp_receive_date' => $reportDt['date'],
        'rp_receive_time' => $reportDt['time'],
        'rp_daily_ref' => $gi['document_no'] ?? '',
        'rp_agency_type' => '',
        'rp_agency_name' => '',
        'rp_notify_method[]' => mapNotifyMethods($gi['report_channel'] ?? []),
        'rp_from_station' => $gi['source_station'] ?? '',
        'rp_joint_officer' => '',
        'rp_investigator' => $investigator['firstname'] ?? '',

        // Section 2: สถานที่เกิดเหตุ
        'rp_crime_location' => $gi['location_detail'] ?? '',
        'rp_victim_name' => $victim['firstname'] ?? '',
        'rp_victim_age' => $victim['age'] ?? '',

        // Section 3: วันเวลาที่ทราบเหตุ
        'rp_victim_know_date' => $victimDt['date'],
        'rp_victim_know_time' => $victimDt['time'],
        'rp_officer_know_date' => $officerDt['date'],
        'rp_officer_know_time' => $officerDt['time'],

        // Section 4: วันเวลาตรวจสถานที่
        'rp_inspect_date' => $inspectDt['date'],
        'rp_inspect_time' => $inspectDt['time'],
        'rp_inspect_add_date' => $inspectAddDt['date'],
        'rp_inspect_add_time' => $inspectAddDt['time'],

        // Section 6: ลักษณะสถานที่เกิดเหตุ
        'rp_building_type[]' => $buildingMapping['types'],
        'rp_building_type_other' => $buildingMapping['other_text'],
        'rp_floor_count' => '',
        'rp_unit_count' => '',
        'rp_mezzanine' => '',
        'rp_rooftop' => '',
        'rp_in_compound' => '',
        'rp_fence' => ($sc['fence'] ?? '') === 'มีรั้ว' ? 'มี' : (($sc['fence'] ?? '') === 'ไม่มีรั้ว' ? 'ไม่มี' : ''),
        'rp_ext_front' => $sc['front_adjacent'] ?? '',
        'rp_ext_left' => $sc['left_adjacent'] ?? '',
        'rp_ext_right' => $sc['right_adjacent'] ?? '',
        'rp_ext_back' => $sc['back_adjacent'] ?? '',
        'rp_interior_detail' => $sc['interior_detail'] ?? '',
        'rp_incident_area' => $sc['incident_area_detail'] ?? '',

        // Section 7: ผลการตรวจสถานที่เกิดเหตุ
        'rp_case_behavior' => $ir['case_behavior'] ?? '',
        'rp_inspection_detail' => '',
        'rp_scene_condition' => trim(implode("\n", array_filter([
            $sc['preservation'] ?? '',
            $sc['preservation_detail'] ?? ''
        ]))),
        'rp_criminal_entry' => '',

        // 7.3 ห้องต่างๆ (ไม่มีใน checklist → เว้นว่าง)
        'rp_room_name[]' => [],
        'rp_room_entry[]' => [],
        'rp_room_marks[]' => [],
        'rp_room_search_marks[]' => [],
        'rp_room_other_evidence[]' => [],

        // 7.4 ทรัพย์สินที่ถูกโจรกรรม
        'rp_stolen_victim_prefix' => '',
        'rp_stolen_victim_name' => $victim['firstname'] ?? '',
        'rp_stolen_victim_role' => '',
        'rp_stolen_item[]' => [],

        // 7.5 วัตถุพยาน
        'rp_fingerprint_count' => '',
        'rp_fingerprint_location' => '',
        'rp_fingerprint_detail[]' => [],
        'rp_fp_signer_prefix' => '',
        'rp_fp_signer_name' => '',
        'rp_fp_signer_role' => '',
        'rp_evidence_other[]' => [],

        // 7.6 การดำเนินการ
        'rp_evidence_action' => '',

        // 7.7 การส่งมอบ
        'rp_handover_officer' => $ho['deliverer_id'] ?? '',
        'rp_handover_to' => $ho['receiver_id'] ?? '',
        'rp_handover_date' => $inspectEndDt['date'],
        'rp_handover_time' => $inspectEndDt['time'],

        // ลงชื่อผู้รายงาน
        'rp_signer_name' => $ho['deliverer_id'] ?? '',
        'rp_signer_position' => $ho['deliverer_pos'] ?? '',
        'rp_sign_date' => $inspectEndDt['date'],
    ];
}

// ============================================
// Resolve single user ID → fullname + position
// ============================================
function resolveUserId($pdo, $userId) {
    if (empty($userId)) return ['fullname' => '', 'position' => ''];
    $stmt = $pdo->prepare("SELECT CONCAT(IFNULL(t3.rank_name, ''), ' ', t2.first_name, ' ', t2.last_name) as fullname,
                                  IFNULL(t3.rank_name, '') as position
                           FROM users t1
                           INNER JOIN user_profile t2 ON t1.user_id = t2.user_id
                           LEFT JOIN user_rank t3 ON t2.rank_id = t3.rank_id
                           WHERE t1.user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row : ['fullname' => '', 'position' => ''];
}

// ============================================
// Resolve user IDs → names in final property data
// ============================================
function resolveUserIdsInPropertyData($pdo, &$data) {
    if (!isset($data['property']) || !is_array($data['property'])) return;

    $nameFields = ['rp_handover_officer', 'rp_handover_to', 'rp_signer_name'];
    foreach ($nameFields as $fk) {
        if (isset($data['property'][$fk]) && is_numeric($data['property'][$fk])) {
            $resolved = resolveUserId($pdo, $data['property'][$fk]);
            $data['property'][$fk] = $resolved['fullname'];
        }
    }
    // signer_position
    if (isset($data['property']['rp_signer_position']) && is_numeric($data['property']['rp_signer_position'])) {
        $resolved = resolveUserId($pdo, $data['property']['rp_signer_position']);
        $data['property']['rp_signer_position'] = $resolved['position'];
    }
}

// ============================================
// Resolve inspector IDs → names + positions
// ============================================
function resolveInspectors($pdo, $inspectorIds) {
    $result = [
        'rp_inspector_name[]' => [],
        'rp_inspector_position[]' => []
    ];
    if (empty($inspectorIds) || !is_array($inspectorIds)) return $result;

    $validIds = array_filter($inspectorIds, function($id) { return !empty($id); });
    if (empty($validIds)) return $result;

    $placeholders = implode(',', array_fill(0, count($validIds), '?'));
    $stmt = $pdo->prepare("SELECT t1.user_id as id, 
                                  CONCAT(IFNULL(t3.rank_name, ''), ' ', t2.first_name, ' ', t2.last_name) as fullname, 
                                  IFNULL(t3.rank_name, '') as position 
                           FROM users t1
                           INNER JOIN user_profile t2 ON t1.user_id = t2.user_id
                           LEFT JOIN user_rank t3 ON t2.rank_id = t3.rank_id
                           WHERE t1.user_id IN ($placeholders)");
    $stmt->execute(array_values($validIds));
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $userMap = [];
    foreach ($users as $u) {
        $userMap[$u['id']] = $u;
    }

    foreach ($inspectorIds as $id) {
        if (isset($userMap[$id])) {
            $result['rp_inspector_name[]'][] = $userMap[$id]['fullname'] ?? '';
            $result['rp_inspector_position[]'][] = $userMap[$id]['position'] ?? '';
        } else {
            $result['rp_inspector_name[]'][] = '';
            $result['rp_inspector_position[]'][] = '';
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

    $countEdit = (int)($result['count_report'] ?? 0);
    $editDate = $result['edit_date'] ?? '';

    // ดึง report data
    $reportData = null;
    if (!empty($result['incident_report_data'])) {
        $reportData = json_decode($result['incident_report_data'], true);
    }

    // ดึง checklist data เพื่อ map เป็นค่าเริ่มต้น
    $checklistData = null;
    if (!empty($result['incident_checklist_data'])) {
        $checklistData = json_decode($result['incident_checklist_data'], true);
    }

    // เตรียมข้อมูล mapped จาก checklist (ใช้เป็นค่าเริ่มต้น)
    $checklistMapped = null;
    if ($checklistData) {
        $propertyFields = mapChecklistToProperty($checklistData);

        // Resolve inspector IDs → names
        $inspectors = $checklistData['inspectors'] ?? [];
        $inspectorFields = resolveInspectors($pdo, $inspectors);
        $propertyFields = array_merge($propertyFields, $inspectorFields);

        // Resolve handover/signer user IDs → ชื่อจริง
        $ho = $checklistData['handover'] ?? [];
        $deliverer = resolveUserId($pdo, $ho['deliverer_id'] ?? '');
        $receiver = resolveUserId($pdo, $ho['receiver_id'] ?? '');
        $propertyFields['rp_handover_officer'] = $deliverer['fullname'];
        $propertyFields['rp_handover_to'] = $receiver['fullname'];
        $propertyFields['rp_signer_name'] = $deliverer['fullname'];
        $propertyFields['rp_signer_position'] = $deliverer['position'];

        // Agency info จาก rn_ReceiveNoti
        $propertyFields['rp_agency_type'] = $agencyType;
        $propertyFields['rp_agency_name'] = $agencyName;

        $checklistMapped = [
            'report_type' => 'property',
            'property' => $propertyFields
        ];
    }

    // ตรวจว่ามี property data ที่เคยบันทึกแล้วหรือไม่
    $hasPropertyReport = isset($reportData['property']) && is_array($reportData['property']);

    if ($hasPropertyReport) {
        // มีข้อมูล report แล้ว → เติม checklist เป็นค่าเริ่มต้นถ้ายังว่าง
        if ($checklistMapped) {
            $baseFields = $checklistMapped['property'] ?? [];
            foreach ($baseFields as $k => $v) {
                if (!isset($reportData['property'][$k]) 
                    || $reportData['property'][$k] === '' 
                    || $reportData['property'][$k] === []) {
                    $reportData['property'][$k] = $v;
                }
            }
        }

        // Resolve user IDs → ชื่อจริง
        resolveUserIdsInPropertyData($pdo, $reportData);

        // Agency fallback
        if (empty($reportData['property']['rp_agency_type'])) {
            $reportData['property']['rp_agency_type'] = $agencyType;
        }
        if (empty($reportData['property']['rp_agency_name'])) {
            $reportData['property']['rp_agency_name'] = $agencyName;
        }

        echo json_encode([
            'success' => true,
            'data' => $reportData,
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'format' => 'report'
        ], JSON_UNESCAPED_UNICODE);

    } elseif ($checklistMapped) {
        // ยังไม่เคยบันทึก report → ใช้ข้อมูลจาก checklist เป็นค่าเริ่มต้น
        echo json_encode([
            'success' => true,
            'data' => $checklistMapped,
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'format' => 'mapped_from_checklist'
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // ไม่มีข้อมูลเลย
        echo json_encode([
            'success' => true,
            'data' => ['report_type' => 'property', 'property' => []],
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'format' => 'empty'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
