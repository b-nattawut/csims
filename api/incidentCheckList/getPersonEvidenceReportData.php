<?php
/**
 * API: Get Person Evidence Report Data
 * ดึงข้อมูล incident_report_data จาก incident_checklist_transaction
 * สำหรับโหลดข้อมูลร่างรายงานตรวจเก็บวัตถุพยานบุคคล (complaints_type = '08')
 *
 * ถ้า incident_report_data มี person_evidence key → ส่งคืนตรงๆ
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
    if (empty($userId) || !is_numeric($userId)) return ['fullname' => (string)$userId, 'position' => ''];
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
        $prefix . '_inspector_name[]' => [],
        $prefix . '_inspector_position[]' => []
    ];
    if (empty($inspectors) || !is_array($inspectors)) return $result;

    $ids = [];
    foreach ($inspectors as $insp) {
        if (is_array($insp)) {
            $ids[] = $insp['id'] ?? '';
        } else {
            $ids[] = $insp;
        }
    }
    $validIds = array_filter($ids, function($id) { return !empty($id) && is_numeric($id); });
    if (empty($validIds)) {
        // If not numeric, treat as names already
        foreach ($ids as $id) {
            $result[$prefix . '_inspector_name[]'][] = (string)$id;
            $result[$prefix . '_inspector_position[]'][] = '';
        }
        return $result;
    }

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
            $result[$prefix . '_inspector_name[]'][] = $userMap[$id]['fullname'] ?? '';
            $result[$prefix . '_inspector_position[]'][] = $userMap[$id]['position'] ?? '';
        } elseif (!empty($id)) {
            $result[$prefix . '_inspector_name[]'][] = (string)$id;
            $result[$prefix . '_inspector_position[]'][] = '';
        }
    }

    return $result;
}

// ============================================
// Map notify_method checklist → report format
// ============================================
function mapNotifyMethods($methods) {
    if (!is_array($methods)) return [];
    $map = [
        'ทางโทรศัพท์' => 'โทรศัพท์',
        'ทางวิทยุสื่อสาร' => 'วิทยุสื่อสาร',
        'ทางหนังสือ' => 'หนังสือ',
        'ตามหนังสือ' => 'หนังสือ',
    ];
    $result = [];
    foreach ($methods as $m) {
        $result[] = $map[$m] ?? $m;
    }
    return $result;
}

// ============================================
// Map person evidence checklist JSON → report fields (pe_*)
// ============================================
function mapChecklistToPersonEvidenceReport($ck, $pdo) {
    $gen = $ck['general_info'] ?? [];
    $persons = $ck['persons'] ?? [];
    $personInfo = $ck['person_info'] ?? [];
    $evidences = $ck['evidences'] ?? [];
    $evidenceHandling = $ck['evidence_handling'] ?? [];
    $inspectors = $ck['inspectors'] ?? [];
    $handover = $ck['handover'] ?? [];
    $signer = $ck['signer'] ?? [];
    $purpose = $ck['purpose'] ?? [];
    $inspection = $ck['inspection'] ?? [];

    $mapped = [
        // Section 1: การรับแจ้งเหตุ
        'pe_receive_date'     => $gen['receive_date'] ?? ($gen['report_date'] ?? ''),
        'pe_receive_time'     => $gen['receive_time'] ?? ($gen['report_time'] ?? ''),
        'pe_agency_type'      => '',
        'pe_agency_name'      => $gen['unit_name'] ?? '',
        'pe_notify_method[]'  => mapNotifyMethods($gen['notify_method'] ?? []),
        'pe_notify_method_other_text' => $gen['notify_method_other_text'] ?? '',
        'pe_police_station'   => $gen['police_station'] ?? ($gen['source_station'] ?? ''),
        'pe_document_no'      => $gen['document_no'] ?? '',
        'pe_document_date'    => $gen['document_date'] ?? '',
        'pe_case_no'          => $gen['case_no'] ?? '',
        'pe_incident_location' => $gen['incident_location'] ?? ($gen['location_detail'] ?? ''),
        'pe_incident_date'    => $gen['incident_date'] ?? '',
        'pe_incident_time'    => $gen['incident_time'] ?? '',
        'pe_investigator_name' => $gen['investigator_name'] ?? ($gen['investigator']['name'] ?? ''),

        // Section 2: จุดประสงค์
        'pe_purpose_detail'   => is_array($purpose) ? ($purpose['detail'] ?? '') : (string)$purpose,

        // Section 3: ผลการตรวจ
        'pe_inspect_location' => $inspection['location'] ?? '',
        'pe_inspect_date'     => $inspection['date'] ?? '',
        'pe_inspect_time'     => $inspection['time'] ?? '',

        // 3.3 การดำเนินการเกี่ยวกับวัตถุพยาน
        'pe_witness_name'     => $evidenceHandling['witness_name'] ?? '',
        'pe_witness_form'     => $evidenceHandling['witness_form'] ?? '',
        'pe_witness_detail'   => $evidenceHandling['witness_detail'] ?? '',
        'pe_handover_method_checks[]' => $evidenceHandling['handover_method_checks'] ?? [],
        'pe_handover_item_ref' => $evidenceHandling['handover_item_ref'] ?? '',
        'pe_handover_to'      => $evidenceHandling['handover_to'] ?? '',
        'pe_handover_purpose' => $evidenceHandling['handover_purpose'] ?? ($evidenceHandling['purpose_full'] ?? ''),

        // ลงชื่อ
        'pe_signer_name'      => '',
        'pe_signer_position'  => '',
        'pe_sign_date'        => $handover['inspection_end_date'] ?? '',
    ];

    // Map persons (Section 1 list)
    $personPrefixes = [];
    $personNames = [];
    if (!empty($persons) && is_array($persons)) {
        foreach ($persons as $p) {
            $personPrefixes[] = $p['prefix'] ?? '';
            $personNames[] = $p['name'] ?? '';
        }
    }
    $mapped['pe_person_prefix[]'] = $personPrefixes;
    $mapped['pe_person_name[]'] = $personNames;

    // Map person info (Section 3.1)
    $piPrefixes = []; $piFullnames = []; $piIdCards = []; $piPassports = [];
    $piHeights = []; $piAges = []; $piSkins = []; $piHands = []; $piFeatures = [];
    if (!empty($personInfo) && is_array($personInfo)) {
        foreach ($personInfo as $pi) {
            $piPrefixes[] = $pi['prefix'] ?? '';
            $piFullnames[] = $pi['fullname'] ?? '';
            $piIdCards[] = $pi['id_card'] ?? '';
            $piPassports[] = $pi['passport'] ?? '';
            $piHeights[] = $pi['height'] ?? '';
            $piAges[] = $pi['age'] ?? '';
            $piSkins[] = $pi['skin'] ?? '';
            $piHands[] = $pi['hand'] ?? '';
            $piFeatures[] = $pi['feature'] ?? '';
        }
    }
    $mapped['pe_pi_prefix[]'] = $piPrefixes;
    $mapped['pe_pi_fullname[]'] = $piFullnames;
    $mapped['pe_pi_id_card[]'] = $piIdCards;
    $mapped['pe_pi_passport[]'] = $piPassports;
    $mapped['pe_pi_height[]'] = $piHeights;
    $mapped['pe_pi_age[]'] = $piAges;
    $mapped['pe_pi_skin[]'] = $piSkins;
    $mapped['pe_pi_hand[]'] = $piHands;
    $mapped['pe_pi_feature[]'] = $piFeatures;

    // Map evidences (Section 3.2)
    $evDetails = []; $evQtys = []; $evLabUnits = [];
    if (!empty($evidences) && is_array($evidences)) {
        foreach ($evidences as $ev) {
            $evDetails[] = $ev['detail'] ?? ($ev['item'] ?? '');
            $evQtys[] = $ev['qty'] ?? '';
            $evLabUnits[] = $ev['lab_unit'] ?? '';
        }
    }
    $mapped['pe_ev_detail[]'] = $evDetails;
    $mapped['pe_ev_qty[]'] = $evQtys;
    $mapped['pe_ev_lab_unit[]'] = $evLabUnits;

    // Resolve signer
    $signerId = $handover['receiver_id'] ?? ($signer['id'] ?? '');
    if (!empty($signerId)) {
        $resolved = resolveUserId($pdo, $signerId);
        $mapped['pe_signer_name'] = $resolved['fullname'];
        $mapped['pe_signer_position'] = $resolved['position'];
    }
    if (!empty($signer['fullname'])) $mapped['pe_signer_name'] = $signer['fullname'];
    if (!empty($signer['name'])) $mapped['pe_signer_name'] = $signer['name'];
    if (!empty($signer['position'])) $mapped['pe_signer_position'] = $signer['position'];

    return $mapped;
}

try {
    // ดึง agency info + report_no จาก rn_ReceiveNoti
    $stmtAgency = $pdo->prepare("SELECT userReviewType, userReviewTypeVal, receiveNotiReportNo FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
    $stmtAgency->execute([$incident_id]);
    $agencyRow = $stmtAgency->fetch(PDO::FETCH_ASSOC);
    $reportNo = $agencyRow['receiveNotiReportNo'] ?? '';

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
        $checklistMapped = mapChecklistToPersonEvidenceReport($checklistData, $pdo);

        // Resolve inspectors
        $inspectors = $checklistData['inspectors'] ?? [];
        $inspectorFields = resolveInspectors($pdo, $inspectors, 'pe');
        $checklistMapped = array_merge($checklistMapped, $inspectorFields);

        // Agency info
        $checklistMapped['pe_agency_type'] = $agencyType;
        $checklistMapped['pe_agency_name'] = $agencyName;
    }

    // ตรวจว่า report data มี person_evidence key หรือไม่
    $hasReport = isset($reportData['person_evidence']) && is_array($reportData['person_evidence']);

    if ($hasReport) {
        // มีข้อมูล report แล้ว → merge checklist เป็น fallback
        if ($checklistMapped) {
            foreach ($checklistMapped as $k => $v) {
                if (!isset($reportData['person_evidence'][$k]) 
                    || $reportData['person_evidence'][$k] === '' 
                    || $reportData['person_evidence'][$k] === []) {
                    $reportData['person_evidence'][$k] = $v;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $reportData['person_evidence'],
            'report_no' => $reportNo,
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'format' => 'report'
        ], JSON_UNESCAPED_UNICODE);

    } elseif ($checklistMapped) {
        echo json_encode([
            'success' => true,
            'data' => $checklistMapped,
            'report_no' => $reportNo,
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'format' => 'mapped_from_checklist'
        ], JSON_UNESCAPED_UNICODE);

    } else {
        echo json_encode([
            'success' => true,
            'data' => null,
            'report_no' => $reportNo,
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'format' => 'empty'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
