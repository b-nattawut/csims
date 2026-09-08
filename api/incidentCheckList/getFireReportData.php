<?php
/**
 * API: Get Fire Report Data
 * ดึงข้อมูล incident_report_data จาก incident_checklist_transaction
 * สำหรับโหลดข้อมูลร่างรายงานคดีเพลิงไหม้
 * 
 * ถ้า incident_report_data เป็น format ใหม่ (มี fire key จาก saveFireReport.php) → ส่งคืนตรงๆ
 * ถ้ายังไม่เคยบันทึก → map จาก checklist data เป็นค่าเริ่มต้น
 */
require_once '../../db_config.php';

header('Content-Type: application/json; charset=utf-8');

$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// Helper: Resolve single user ID → fullname + position
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
// Resolve inspector IDs → names + positions
// ============================================
function resolveInspectors($pdo, $inspectors, $prefix) {
    $result = [
        $prefix . '_inspector_id[]' => [],
        $prefix . '_inspector_name[]' => [],
        $prefix . '_inspector_position[]' => []
    ];
    if (empty($inspectors) || !is_array($inspectors)) return $result;

    // inspectors อาจเป็น array of {id, position} หรือ array of IDs
    $ids = [];
    foreach ($inspectors as $insp) {
        if (is_array($insp)) {
            $ids[] = $insp['id'] ?? '';
        } else {
            $ids[] = $insp;
        }
    }
    $validIds = array_filter($ids, function($id) { return !empty($id); });
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

    $userMap = [];
    foreach ($users as $u) {
        $userMap[$u['id']] = $u;
    }

    foreach ($ids as $id) {
        if (isset($userMap[$id])) {
            $result[$prefix . '_inspector_id[]'][] = $id;
            $result[$prefix . '_inspector_name[]'][] = $userMap[$id]['fullname'] ?? '';
            $result[$prefix . '_inspector_position[]'][] = $userMap[$id]['position'] ?? '';
        } elseif (!empty($id)) {
            $result[$prefix . '_inspector_id[]'][] = $id;
            $result[$prefix . '_inspector_name[]'][] = '';
            $result[$prefix . '_inspector_position[]'][] = '';
        }
    }

    return $result;
}

// ============================================
// Helper: map notify_method ค่า checklist → report format
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
// Map fire checklist JSON → report fields (rf_*)
// ============================================
function mapChecklistToFireReport($ck) {
    $gi = $ck['general_info'] ?? [];
    $si = $ck['scene_info'] ?? [];
    $di = $ck['datetime_info'] ?? [];
    $sc = $ck['scene_characteristics'] ?? [];
    $cb = $ck['case_behavior'] ?? [];
    $dm = $ck['damage'] ?? [];
    $sm = $ck['summary'] ?? [];
    $op = $ck['opinion'] ?? [];
    $ho = $ck['handover'] ?? [];
    $ext = $sc['exterior'] ?? [];
    $int = $sc['interior'] ?? [];
    $ia = $sc['incident_area'] ?? [];
    $struct = $ia['structure'] ?? [];
    $objects = $ia['objects'] ?? [];
    $dmStruct = $dm['structure'] ?? [];
    $dmObj = $dm['objects'] ?? [];

    // Map fence value
    $fenceVal = '';
    $fence = $ext['fence'] ?? '';
    if ($fence === 'has_fence' || $fence === 'มีรั้ว' || $fence === 'มี') $fenceVal = 'มี';
    elseif ($fence === 'no_fence' || $fence === 'ไม่มีรั้ว' || $fence === 'ไม่มี') $fenceVal = 'ไม่มี';

    // Map insurance
    $insurance = '';
    $ins = $cb['insurance'] ?? '';
    if ($ins === 'has_insurance' || $ins === 'มี') $insurance = 'มี';
    elseif ($ins === 'no_insurance' || $ins === 'ไม่มี') $insurance = 'ไม่มี';

    // Map extinguish
    $extinguish = '';
    $ext_val = $cb['extinguish'] ?? '';
    if ($ext_val === 'yes' || $ext_val === 'ดับแล้ว') $extinguish = 'ดับแล้ว';
    elseif ($ext_val === 'no' || $ext_val === 'ยังไม่ดับ') $extinguish = 'ยังไม่ดับ';

    // Map adjacent damage
    $adjacentDamage = '';
    $adj = $dm['adjacent_damage'] ?? '';
    if ($adj === 'found' || $adj === 'พบ') $adjacentDamage = 'พบ';
    elseif ($adj === 'not_found' || $adj === 'ไม่พบ') $adjacentDamage = 'ไม่พบ';

    // Map cause type
    $causeType = '';
    $ct = $op['cause_type'] ?? '';
    if ($ct === 'believed' || $ct === 'เชื่อว่า') $causeType = 'เชื่อว่า';
    elseif ($ct === 'unknown' || $ct === 'ไม่ทราบสาเหตุ') $causeType = 'ไม่ทราบสาเหตุ';

    return [
        // Section 1: การรับแจ้งเหตุ
        'rf_receive_date' => $gi['report_date'] ?? '',
        'rf_receive_time' => $gi['report_time'] ?? '',
        'rf_daily_ref' => $gi['document_no'] ?? '',
        'rf_agency_type' => '',
        'rf_agency_name' => '',
        'rf_notify_method[]' => mapNotifyMethods($gi['notify_method'] ?? []),
        'rf_from_station' => $gi['source_station'] ?? '',
        'rf_investigator' => $gi['investigator']['name'] ?? '',

        // Section 2: สถานที่เกิดเหตุ
        'rf_crime_location' => $si['incident_location'] ?? '',

        // Section 3: วันเวลาที่ทราบเหตุ
        'rf_victim_know_date' => $di['victim_known_date'] ?? '',
        'rf_victim_know_time' => $di['victim_known_time'] ?? '',
        'rf_officer_know_date' => $di['investigator_known_date'] ?? '',
        'rf_officer_know_time' => $di['investigator_known_time'] ?? '',

        // Section 4: วันเวลาตรวจสถานที่
        'rf_inspect_date' => $di['inspection_date'] ?? '',
        'rf_inspect_time' => $di['inspection_time'] ?? '',
        'rf_inspect_add_date' => $di['inspection_additional_date'] ?? '',
        'rf_inspect_add_time' => $di['inspection_additional_time'] ?? '',

        // Section 6: ลักษณะสถานที่
        'rf_exterior_detail' => $ext['detail'] ?? '',
        'rf_floor_count' => $ext['floor_count'] ?? '',
        'rf_fence' => $fenceVal,
        'rf_ext_front' => $ext['front'] ?? '',
        'rf_ext_left' => $ext['left'] ?? '',
        'rf_ext_right' => $ext['right'] ?? '',
        'rf_ext_back' => $ext['back'] ?? '',
        'rf_interior_detail' => $int['detail'] ?? '',
        'rf_incident_area' => $ia['detail'] ?? '',
        'rf_area_size' => $ia['size'] ?? '',
        'rf_facing_direction' => $ia['facing_direction'] ?? '',
        'rf_wall_front' => $struct['wall_front'] ?? '',
        'rf_wall_left' => $struct['wall_left'] ?? '',
        'rf_wall_right' => $struct['wall_right'] ?? '',
        'rf_wall_back' => $struct['wall_back'] ?? '',
        'rf_floor_material' => $struct['floor'] ?? '',
        'rf_ceiling' => $struct['ceiling'] ?? '',
        'rf_roof' => $struct['roof'] ?? '',
        'rf_arrange_front' => $objects['wall_front'] ?? '',
        'rf_arrange_left' => $objects['wall_left'] ?? '',
        'rf_arrange_right' => $objects['wall_right'] ?? '',
        'rf_arrange_back' => $objects['wall_back'] ?? '',
        'rf_arrange_other' => $objects['other'] ?? '',

        // Section 7: พฤติการณ์คดี
        'rf_case_behavior' => $cb['detail'] ?? '',
        'rf_insurance' => $insurance,
        'rf_burn_time' => $cb['burn_time'] ?? '',
        'rf_extinguish' => $extinguish,
        'rf_extinguish_detail' => $cb['extinguish_detail'] ?? '',
        'rf_damage_condition' => $cb['damage_condition'] ?? '',
        'rf_spread_detail' => $cb['spread_detail'] ?? '',
        'rf_damage_wall_front' => $dmStruct['wall_front'] ?? '',
        'rf_damage_wall_left' => $dmStruct['wall_left'] ?? '',
        'rf_damage_wall_right' => $dmStruct['wall_right'] ?? '',
        'rf_damage_wall_back' => $dmStruct['wall_back'] ?? '',
        'rf_damage_floor' => $dmStruct['floor'] ?? '',
        'rf_damage_roof' => $dmStruct['roof'] ?? '',
        'rf_damage_ceiling' => $dmStruct['ceiling'] ?? '',
        'rf_damage_obj_front' => $dmObj['front'] ?? '',
        'rf_damage_obj_left' => $dmObj['left'] ?? '',
        'rf_damage_obj_right' => $dmObj['right'] ?? '',
        'rf_damage_obj_back' => $dmObj['back'] ?? '',
        'rf_damage_obj_floor' => $dmObj['floor'] ?? '',
        'rf_damage_obj_roof' => $dmObj['roof'] ?? '',
        'rf_damage_obj_ceiling' => $dmObj['ceiling'] ?? '',
        'rf_first_area' => $dm['first_area'] ?? '',
        'rf_switch_condition' => $dm['switch_condition'] ?? '',
        'rf_adjacent_damage' => $adjacentDamage,
        'rf_adjacent_damage_detail' => $dm['adjacent_damage_detail'] ?? '',
        'rf_evidence_found' => $dm['evidence_found'] ?? '',

        // Section 8: สรุปผลการตรวจ
        'rf_origin_area' => $sm['origin_area'] ?? '',
        'rf_fuel_source' => $sm['fuel_source'] ?? '',
        'rf_heat_source' => $sm['heat_source'] ?? '',
        'rf_summary_other' => $sm['other'] ?? '',

        // Section 9: ความเห็น
        'rf_opinion_first_area' => $op['first_area'] ?? '',
        'rf_cause_type' => $causeType,
        'rf_cause_believed_detail' => $op['cause_believed_detail'] ?? '',
        'rf_cause_unknown_detail' => $op['cause_unknown_detail'] ?? '',

        // การส่งมอบ
        'rf_handover_officer' => $ho['sender_name'] ?? '',
        'rf_handover_to' => $ho['receiver_name'] ?? '',
        'rf_handover_date' => $ho['inspection_end_date'] ?? '',
        'rf_handover_time' => $ho['inspection_end_time'] ?? '',

        // ลงชื่อผู้รายงาน
        'rf_signer_name' => $ho['sender_name'] ?? '',
        'rf_signer_position' => $ho['sender_position'] ?? '',
        'rf_sign_date' => $ho['inspection_end_date'] ?? '',
    ];
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

    // ดึง checklist data
    $checklistData = null;
    if (!empty($result['incident_checklist_data'])) {
        $checklistData = json_decode($result['incident_checklist_data'], true);
    }

    // ดึง report data
    $reportData = null;
    if (!empty($result['incident_report_data'])) {
        $reportData = json_decode($result['incident_report_data'], true);
    }

    // Map จาก checklist เป็นค่าเริ่มต้น
    $checklistMapped = null;
    if ($checklistData) {
        $checklistMapped = mapChecklistToFireReport($checklistData);

        // Resolve inspectors
        $inspectors = $checklistData['inspectors'] ?? [];
        $inspectorFields = resolveInspectors($pdo, $inspectors, 'rf');
        $checklistMapped = array_merge($checklistMapped, $inspectorFields);

        // Resolve user IDs → ชื่อจริง
        $ho = $checklistData['handover'] ?? [];
        $sender = resolveUserId($pdo, $ho['sender_name'] ?? '');
        $receiver = resolveUserId($pdo, $ho['receiver_name'] ?? '');
        $checklistMapped['rf_handover_officer'] = $sender['fullname'];
        $checklistMapped['rf_handover_to'] = $receiver['fullname'];
        $checklistMapped['rf_signer_name'] = $sender['fullname'];
        $checklistMapped['rf_signer_position'] = $sender['position'];

        // Agency info
        $checklistMapped['rf_agency_type'] = $agencyType;
        $checklistMapped['rf_agency_name'] = $agencyName;
    }

    // ตรวจว่า report data มี fire key หรือไม่
    $hasFireReport = isset($reportData['fire']) && is_array($reportData['fire']);

    if ($hasFireReport) {
        // มีข้อมูล report แล้ว → merge checklist เป็นค่าเริ่มต้น (เติมเฉพาะ field ที่ว่าง)
        if ($checklistMapped) {
            foreach ($checklistMapped as $k => $v) {
                if (!isset($reportData['fire'][$k]) 
                    || $reportData['fire'][$k] === '' 
                    || $reportData['fire'][$k] === []) {
                    $reportData['fire'][$k] = $v;
                }
            }
        }

        // Resolve name fields ถ้ายังเป็น numeric
        $nameFields = ['rf_handover_officer', 'rf_handover_to', 'rf_signer_name'];
        foreach ($nameFields as $fk) {
            if (isset($reportData['fire'][$fk]) && is_numeric($reportData['fire'][$fk])) {
                $resolved = resolveUserId($pdo, $reportData['fire'][$fk]);
                $reportData['fire'][$fk] = $resolved['fullname'];
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $reportData['fire'],
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'format' => 'report'
        ], JSON_UNESCAPED_UNICODE);

    } elseif ($checklistMapped) {
        // ยังไม่เคยบันทึก report → ใช้ checklist mapped
        echo json_encode([
            'success' => true,
            'data' => $checklistMapped,
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'format' => 'mapped_from_checklist'
        ], JSON_UNESCAPED_UNICODE);

    } else {
        echo json_encode([
            'success' => true,
            'data' => null,
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'format' => 'empty'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
