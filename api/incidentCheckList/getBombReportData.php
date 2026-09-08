<?php
/**
 * API: Get Bomb Report Data
 * ดึงข้อมูล incident_report_data จาก incident_checklist_transaction
 * สำหรับโหลดข้อมูลร่างรายงานคดีระเบิด (ในอาคาร)
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
function resolveInspectors($pdo, $inspectorIds, $prefix) {
    $result = [
        $prefix . '_inspector_id[]' => [],
        $prefix . '_inspector_name[]' => [],
        $prefix . '_inspector_position[]' => []
    ];
    if (empty($inspectorIds) || !is_array($inspectorIds)) return $result;

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

    $userMap = [];
    foreach ($users as $u) {
        $userMap[$u['id']] = $u;
    }

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

// ============================================
// Map bomb checklist JSON → outdoor report fields (rbo_*)
// ============================================
function mapBombChecklistToOutdoor($ck) {
    $gi = $ck['general_info'] ?? [];
    $si = $ck['scene_info'] ?? [];
    $ir = $ck['inspection_results'] ?? [];
    $ho = $ck['handover'] ?? [];
    $victims = $ck['victims'] ?? [];
    $firstVictim = !empty($victims) ? $victims[0] : [];
    $outdoor = $si['outdoor'] ?? [];
    $bodies = $ir['bodies'] ?? [];

    // Notify methods mapping
    $notifyMethods = [];
    $channels = $gi['report_channel'] ?? [];
    if (is_array($channels)) {
        $map = ['ทางโทรศัพท์'=>'โทรศัพท์','ทางวิทยุสื่อสาร'=>'วิทยุสื่อสาร','ทางหนังสือ'=>'หนังสือ'];
        foreach ($channels as $m) { $notifyMethods[] = $map[$m] ?? $m; }
    }

    // Investigator
    $investigator = $gi['investigator'] ?? [];
    $investigatorName = '';
    if (is_array($investigator)) { $investigatorName = $investigator['name'] ?? ($investigator['firstname'] ?? ''); }
    elseif (is_string($investigator)) { $investigatorName = $investigator; }

    return [
        'rbo_receive_date' => $gi['case_date'] ?? '',
        'rbo_receive_time' => $gi['case_time'] ?? '',
        'rbo_daily_ref' => $gi['document_no'] ?? '',
        'rbo_agency_type' => '',
        'rbo_agency_name' => '',
        'rbo_notify_method[]' => $notifyMethods,
        'rbo_from_station' => $gi['source_station'] ?? '',
        'rbo_investigator' => $investigatorName,
        'rbo_crime_location' => $si['crime_location'] ?? '',
        'rbo_victim_name' => $firstVictim['name'] ?? '',
        'rbo_victim_age' => $firstVictim['age'] ?? '',
        'rbo_victim_know_date' => $gi['victim_know_date'] ?? '',
        'rbo_victim_know_time' => $gi['victim_know_time'] ?? '',
        'rbo_officer_know_date' => $gi['officer_know_date'] ?? '',
        'rbo_officer_know_time' => $gi['officer_know_time'] ?? '',
        'rbo_inspect_date' => $gi['inspect_date'] ?? '',
        'rbo_inspect_time' => $gi['inspect_time'] ?? '',
        'rbo_inspect_add_date' => $gi['inspect_additional_date'] ?? '',
        'rbo_inspect_add_time' => $gi['inspect_additional_time'] ?? '',
        'rbo_scene_description' => $outdoor['description'] ?? '',
        'rbo_case_behavior' => $ir['case_behavior'] ?? '',
        'rbo_scene_condition' => '',
        'rbo_body_found' => !empty($bodies) ? ($bodies[0]['status'] ?? '') : '',
        'rbo_body_position' => '',
        'rbo_body_condition' => !empty($bodies) ? ($bodies[0]['condition_detail'] ?? '') : '',
        'rbo_body_clothing' => '',
        'rbo_body_wounds' => '',
        'rbo_damage_detail' => $ir['damage_details'] ?? '',
        'rbo_explosion_position' => $ir['explosion_position'] ?? '',
        'rbo_evidence_found' => '',
        'rbo_evidence_collected' => '',
        'rbo_evidence_action' => '',
        'rbo_handover_officer' => $ho['deliverer_id'] ?? '',
        'rbo_handover_to' => $ho['receiver_id'] ?? '',
        'rbo_handover_date' => $ho['inspection_end_date'] ?? '',
        'rbo_handover_time' => $ho['inspection_end_time'] ?? '',
        'rbo_signer_name' => $ho['deliverer_id'] ?? '',
        'rbo_signer_position' => $ho['deliverer_pos'] ?? '',
    ];
}

// ============================================
// Map bomb checklist JSON → indoor report fields (rbi_*)
// ============================================
function mapBombChecklistToIndoor($ck) {
    $gi = $ck['general_info'] ?? [];
    $si = $ck['scene_info'] ?? [];
    $ir = $ck['inspection_results'] ?? [];
    $ho = $ck['handover'] ?? [];
    $indoor = $si['indoor'] ?? [];
    $structure = $indoor['structure'] ?? [];
    $victims = $ck['victims'] ?? [];

    // First victim info
    $firstVictim = !empty($victims) ? $victims[0] : [];

    // Notify methods mapping
    $notifyMethods = [];
    $channels = $gi['report_channel'] ?? [];
    if (is_array($channels)) {
        $map = [
            'ทางโทรศัพท์' => 'โทรศัพท์',
            'ทางวิทยุสื่อสาร' => 'วิทยุสื่อสาร',
            'ทางหนังสือ' => 'หนังสือ'
        ];
        foreach ($channels as $m) {
            $notifyMethods[] = $map[$m] ?? $m;
        }
    }

    // Building type mapping
    $buildingTypes = $indoor['building_type'] ?? [];
    $reportBuildingTypes = [];
    $buildingOther = $indoor['building_type_other'] ?? '';
    if (is_array($buildingTypes)) {
        $reportValues = ['บ้าน', 'ตึกแถว', 'อาคาร', 'อื่นๆ'];
        $mapBT = ['บ้านเดี่ยว' => 'บ้าน', 'อาคารพาณิชย์' => 'ตึกแถว', 'ทาวน์เฮาส์' => 'บ้าน'];
        foreach ($buildingTypes as $t) {
            if (in_array($t, $reportValues)) {
                if (!in_array($t, $reportBuildingTypes)) $reportBuildingTypes[] = $t;
            } elseif (isset($mapBT[$t])) {
                if (!in_array($mapBT[$t], $reportBuildingTypes)) $reportBuildingTypes[] = $mapBT[$t];
            } else {
                if (!in_array('อื่นๆ', $reportBuildingTypes)) $reportBuildingTypes[] = 'อื่นๆ';
                $buildingOther .= ($buildingOther ? ', ' : '') . $t;
            }
        }
    }

    // Investigator
    $investigator = $gi['investigator'] ?? [];
    $investigatorName = '';
    if (is_array($investigator)) {
        $investigatorName = $investigator['name'] ?? ($investigator['firstname'] ?? '');
    } elseif (is_string($investigator)) {
        $investigatorName = $investigator;
    }

    // Bodies info for section 7.2
    $bodies = $ir['bodies'] ?? [];

    return [
        // Section 1: การรับแจ้งเหตุ
        'rbi_receive_date' => $gi['case_date'] ?? '',
        'rbi_receive_time' => $gi['case_time'] ?? '',
        'rbi_daily_ref' => $gi['document_no'] ?? '',
        'rbi_agency_type' => '',
        'rbi_agency_name' => '',
        'rbi_notify_method[]' => $notifyMethods,
        'rbi_notify_method_other' => $gi['report_channel_other'] ?? '',
        'rbi_from_station' => $gi['source_station'] ?? '',
        'rbi_investigator' => $investigatorName,

        // Section 2: สถานที่เกิดเหตุ
        'rbi_crime_location' => $si['crime_location'] ?? '',
        'rbi_victim_type' => $firstVictim['type'] ?? '',
        'rbi_victim_name' => $firstVictim['name'] ?? '',
        'rbi_victim_age' => $firstVictim['age'] ?? '',

        // Section 3: วันเวลาที่ทราบเหตุ
        'rbi_victim_know_date' => $gi['victim_know_date'] ?? '',
        'rbi_victim_know_time' => $gi['victim_know_time'] ?? '',
        'rbi_officer_know_date' => $gi['officer_know_date'] ?? '',
        'rbi_officer_know_time' => $gi['officer_know_time'] ?? '',

        // Section 4: วันเวลาตรวจสถานที่
        'rbi_inspect_date' => $gi['inspect_date'] ?? '',
        'rbi_inspect_time' => $gi['inspect_time'] ?? '',
        'rbi_inspect_add_date' => $gi['inspect_additional_date'] ?? '',
        'rbi_inspect_add_time' => $gi['inspect_additional_time'] ?? '',

        // Section 6: ลักษณะสถานที่เกิดเหตุ (ในอาคาร)
        'rbi_scene_preserved' => $si['preservation'] ?? '',
        'rbi_scene_preserved_detail' => $si['preservation_detail'] ?? '',
        'rbi_building_type[]' => $reportBuildingTypes,
        'rbi_building_type_other' => $buildingOther,
        'rbi_floor_count' => '',
        'rbi_mezzanine' => '',
        'rbi_rooftop' => '',
        'rbi_fence' => ($indoor['fence'] ?? '') === 'มีรั้ว' ? 'มี' : (($indoor['fence'] ?? '') === 'ไม่มีรั้ว' ? 'ไม่มี' : ($indoor['fence'] ?? '')),
        'rbi_ext_front' => $indoor['adjacent']['front'] ?? '',
        'rbi_ext_left' => $indoor['adjacent']['left'] ?? '',
        'rbi_ext_right' => $indoor['adjacent']['right'] ?? '',
        'rbi_ext_back' => $indoor['adjacent']['back'] ?? '',
        'rbi_interior_detail' => $indoor['interior'] ?? '',
        'rbi_incident_area' => $indoor['incident_area'] ?? '',
        'rbi_area_size' => $structure['size'] ?? '',
        'rbi_wall_front' => $structure['wall'] ?? '',
        'rbi_wall_front_window' => '',
        'rbi_wall_front_door' => '',
        'rbi_wall_left' => '',
        'rbi_wall_left_window' => '',
        'rbi_wall_left_door' => '',
        'rbi_wall_right' => '',
        'rbi_wall_right_window' => '',
        'rbi_wall_right_door' => '',
        'rbi_wall_back' => '',
        'rbi_wall_back_window' => '',
        'rbi_wall_back_door' => '',
        'rbi_floor_material' => $structure['floor'] ?? '',
        'rbi_ceiling' => '',
        'rbi_roof' => $structure['roof'] ?? '',
        'rbi_arrange_front' => $structure['arrangement'] ?? '',
        'rbi_arrange_left' => '',
        'rbi_arrange_right' => '',
        'rbi_arrange_back' => '',

        // Section 7: ผลการตรวจสถานที่เกิดเหตุ
        'rbi_case_behavior' => $ir['case_behavior'] ?? '',
        'rbi_scene_condition' => '',
        'rbi_body_condition' => !empty($bodies) ? ($bodies[0]['condition_detail'] ?? '') : '',
        'rbi_damage_detail' => $ir['damage_details'] ?? '',
        'rbi_evidence_found' => '',
        'rbi_evidence_collected' => '',
        'rbi_evidence_action' => '',
        'rbi_other_detail' => '',

        // Section 7.7: การส่งมอบ
        'rbi_handover_officer' => $ho['deliverer_id'] ?? '',
        'rbi_handover_to' => $ho['receiver_id'] ?? '',
        'rbi_handover_date' => $ho['inspection_end_date'] ?? '',
        'rbi_handover_time' => $ho['inspection_end_time'] ?? '',

        // ลงชื่อผู้รายงาน
        'rbi_signer_name' => $ho['deliverer_id'] ?? '',
        'rbi_signer_position' => $ho['deliverer_pos'] ?? '',
        'rbi_sign_date' => $ho['inspection_end_date'] ?? '',
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
            $agencyType = 'กสก.ศพฐ.';
            $agencyName = 'กสก.ศพฐ. ศพฐ ' . $rv;
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

    $isNewFormat = isset($reportData['report_type']) || isset($reportData['indoor']);
    $countEdit = (int)($result['count_report'] ?? 0);
    $editDate = $result['edit_date'] ?? '';

    // เตรียมข้อมูลจาก checklist เป็นค่าเริ่มต้น
    $checklistMapped = null;
    if ($checklistData) {
        $checklistMapped = ['report_type' => 'indoor'];

        // Indoor mapping
        $indoorFields = mapBombChecklistToIndoor($checklistData);
        $inspectors = $checklistData['inspectors'] ?? [];
        $inspectorFields = resolveInspectors($pdo, $inspectors, 'rbi');
        $checklistMapped['indoor'] = array_merge($indoorFields, $inspectorFields);

        // Resolve handover/signer user IDs → ชื่อจริง
        $ho = $checklistData['handover'] ?? [];
        $deliverer = resolveUserId($pdo, $ho['deliverer_id'] ?? '');
        $receiver = resolveUserId($pdo, $ho['receiver_id'] ?? '');
        $checklistMapped['indoor']['rbi_handover_officer'] = $deliverer['fullname'];
        $checklistMapped['indoor']['rbi_handover_to'] = $receiver['fullname'];
        $checklistMapped['indoor']['rbi_signer_name'] = $deliverer['fullname'];
        $checklistMapped['indoor']['rbi_signer_position'] = $deliverer['position'];

        // Agency info
        $checklistMapped['indoor']['rbi_agency_type'] = $agencyType;
        $checklistMapped['indoor']['rbi_agency_name'] = $agencyName;

        // Outdoor mapping
        $outdoorFields = mapBombChecklistToOutdoor($checklistData);
        $inspectorFieldsOutdoor = resolveInspectors($pdo, $inspectors, 'rbo');
        $checklistMapped['outdoor'] = array_merge($outdoorFields, $inspectorFieldsOutdoor);

        $checklistMapped['outdoor']['rbo_handover_officer'] = $deliverer['fullname'];
        $checklistMapped['outdoor']['rbo_handover_to'] = $receiver['fullname'];
        $checklistMapped['outdoor']['rbo_signer_name'] = $deliverer['fullname'];
        $checklistMapped['outdoor']['rbo_signer_position'] = $deliverer['position'];
        $checklistMapped['outdoor']['rbo_agency_type'] = $agencyType;
        $checklistMapped['outdoor']['rbo_agency_name'] = $agencyName;
    }

    if ($isNewFormat) {
        // format ใหม่ => merge ข้อมูล checklist เป็นค่าเริ่มต้น + ข้อมูล report ทับด้านบน
        if ($checklistMapped) {
            // Indoor merge
            if (!isset($reportData['indoor'])) {
                $reportData['indoor'] = $checklistMapped['indoor'] ?? [];
            } else {
                $baseFields = $checklistMapped['indoor'] ?? [];
                if (is_array($baseFields)) {
                    foreach ($baseFields as $k => $v) {
                        if (!isset($reportData['indoor'][$k]) 
                            || $reportData['indoor'][$k] === '' 
                            || $reportData['indoor'][$k] === []) {
                            $reportData['indoor'][$k] = $v;
                        }
                    }
                }
            }

            // Outdoor merge
            if (!isset($reportData['outdoor'])) {
                $reportData['outdoor'] = $checklistMapped['outdoor'] ?? [];
            } else {
                $baseFields = $checklistMapped['outdoor'] ?? [];
                if (is_array($baseFields)) {
                    foreach ($baseFields as $k => $v) {
                        if (!isset($reportData['outdoor'][$k]) 
                            || $reportData['outdoor'][$k] === '' 
                            || $reportData['outdoor'][$k] === []) {
                            $reportData['outdoor'][$k] = $v;
                        }
                    }
                }
            }
        }

        // Resolve user IDs in indoor report data
        if (isset($reportData['indoor']) && is_array($reportData['indoor'])) {
            $nameFields = ['rbi_handover_officer', 'rbi_handover_to', 'rbi_signer_name'];
            foreach ($nameFields as $fk) {
                if (isset($reportData['indoor'][$fk]) && is_numeric($reportData['indoor'][$fk])) {
                    $resolved = resolveUserId($pdo, $reportData['indoor'][$fk]);
                    $reportData['indoor'][$fk] = $resolved['fullname'];
                }
            }
            if (isset($reportData['indoor']['rbi_signer_position']) && is_numeric($reportData['indoor']['rbi_signer_position'])) {
                $resolved = resolveUserId($pdo, $reportData['indoor']['rbi_signer_position']);
                $reportData['indoor']['rbi_signer_position'] = $resolved['position'];
            }
            if (empty($reportData['indoor']['rbi_agency_type'])) $reportData['indoor']['rbi_agency_type'] = $agencyType;
            if (empty($reportData['indoor']['rbi_agency_name'])) $reportData['indoor']['rbi_agency_name'] = $agencyName;
        }

        // Resolve user IDs in outdoor report data
        if (isset($reportData['outdoor']) && is_array($reportData['outdoor'])) {
            $nameFields = ['rbo_handover_officer', 'rbo_handover_to', 'rbo_signer_name'];
            foreach ($nameFields as $fk) {
                if (isset($reportData['outdoor'][$fk]) && is_numeric($reportData['outdoor'][$fk])) {
                    $resolved = resolveUserId($pdo, $reportData['outdoor'][$fk]);
                    $reportData['outdoor'][$fk] = $resolved['fullname'];
                }
            }
            if (isset($reportData['outdoor']['rbo_signer_position']) && is_numeric($reportData['outdoor']['rbo_signer_position'])) {
                $resolved = resolveUserId($pdo, $reportData['outdoor']['rbo_signer_position']);
                $reportData['outdoor']['rbo_signer_position'] = $resolved['position'];
            }
            if (empty($reportData['outdoor']['rbo_agency_type'])) $reportData['outdoor']['rbo_agency_type'] = $agencyType;
            if (empty($reportData['outdoor']['rbo_agency_name'])) $reportData['outdoor']['rbo_agency_name'] = $agencyName;
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
        echo json_encode([
            'success' => true,
            'data' => $reportData,
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'format' => 'unknown'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
