<?php
/**
 * API: Get Fingerprint Report Data
 * ดึงข้อมูล incident_report_data จาก incident_checklist_transaction
 * สำหรับโหลดข้อมูลร่างรายงานตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)
 * 
 * ถ้า incident_report_data เป็น format ใหม่ (มี fingerprint key จาก saveFingerprintReport.php) → ส่งคืนตรงๆ
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
// Map fingerprint checklist JSON → report fields (rlf_*)
// ============================================
function mapChecklistToFingerprintReport($ck) {
    $gi  = $ck['general_info'] ?? [];
    $pkg = $ck['package_storage'] ?? [];
    $ho  = $ck['handover'] ?? [];

    // ---- Section 1: การรับแจ้ง ----
    $mapped = [
        'rlf_receive_date'             => $gi['receive_date'] ?? '',
        'rlf_receive_time'             => $gi['receive_time'] ?? '',
        'rlf_case_no'                  => $gi['case_no'] ?? '',
        'rlf_police_station'           => $gi['police_station'] ?? ($gi['source_station'] ?? ''),
        'rlf_letter_no'                => $gi['letter_no'] ?? '',
        'rlf_letter_date'              => $gi['letter_date'] ?? '',
        'rlf_evidence_sender'          => $gi['evidence_sender'] ?? '',
        'rlf_evidence_sender_position' => $gi['evidence_sender_position'] ?? '',
        'rlf_evidence_sender_phone'    => $gi['evidence_sender_phone'] ?? '',
        'rlf_evidence_letter_no'       => $gi['evidence_letter_no'] ?? '',
        'rlf_evidence_doc_no'          => $gi['evidence_doc_no'] ?? ($gi['document_no'] ?? ''),
        'rlf_evidence_doc_date'        => $gi['evidence_doc_date'] ?? '',
        'rlf_purpose[]'                => $gi['purposes'] ?? [],
        'rlf_purpose_other_text'       => $gi['purpose_other_text'] ?? '',

        // ---- Section 2: วันเวลาที่เกิดเหตุ / ทราบเหตุ ----
        'rlf_incident_date' => $gi['incident_date'] ?? '',
        'rlf_incident_time' => $gi['incident_time'] ?? '',
        'rlf_known_date'    => $gi['known_date'] ?? '',
        'rlf_known_time'    => $gi['known_time'] ?? '',

        // ---- Section 3: วันเวลาที่ตรวจเก็บ ----
        'rlf_collect_date' => $gi['collect_date'] ?? '',
        'rlf_collect_time' => $gi['collect_time'] ?? '',

        // ---- Section 5: ลักษณะการหีบห่อวัตถุพยาน ----
        'rlf_package_type[]'      => $pkg['package_types'] ?? [],
        'rlf_seal_condition[]'    => $pkg['seal_conditions'] ?? [],
        'rlf_collector_type'      => $pkg['collector_type'] ?? '',
        'rlf_collector_other_text'=> $pkg['collector_other_text'] ?? '',
        'rlf_storage_date'        => $pkg['storage_date'] ?? '',
        'rlf_storage_time'        => $pkg['storage_time'] ?? '',
        'rlf_duration_year'       => $pkg['duration_year'] ?? '',
        'rlf_duration_month'      => $pkg['duration_month'] ?? '',
        'rlf_duration_day'        => $pkg['duration_day'] ?? '',

        // ---- ลงชื่อผู้รายงาน ----
        'rlf_signer_name'     => $ho['sender_name'] ?? '',
        'rlf_signer_position' => $ho['sender_position'] ?? '',
        'rlf_sign_date'       => $gi['collect_date'] ?? '',
    ];

    // ---- Section 6: ลักษณะวัตถุพยาน ----
    $sets = $ck['section6_sets'] ?? [];
    $evDescriptions = [];
    $evWidths       = [];
    $evLengths      = [];
    $evHeights      = [];
    $evQuantities   = [];
    $evLabelNos     = [];
    $evLabUnits     = [];
    $evidenceTotal  = 0;

    // Flatten all sets into single list of evidence items
    foreach ($sets as $set) {
        $items = $set['evidence_items'] ?? [];
        foreach ($items as $item) {
            $desc = trim($item['description'] ?? ($item['detail'] ?? ''));
            if ($desc === '') continue;
            $evDescriptions[] = $desc;
            $evWidths[]       = $item['width'] ?? '';
            $evLengths[]      = $item['length'] ?? '';
            $evHeights[]      = $item['height'] ?? '';
            $evQuantities[]   = $item['quantity'] ?? '';
            $evLabelNos[]     = $item['label_no'] ?? '';
            $evLabUnits[]     = $item['lab_unit'] ?? 'fingerprint';
        }
        $evidenceTotal += intval($set['evidence_total'] ?? 0);
    }

    // Use normalized evidences as fallback
    if (empty($evDescriptions) && !empty($ck['evidences'])) {
        foreach ($ck['evidences'] as $ev) {
            $evDescriptions[] = $ev['detail'] ?? '';
            $evWidths[]       = '';
            $evLengths[]      = '';
            $evHeights[]      = '';
            $evQuantities[]   = '';
            $evLabelNos[]     = '';
            $evLabUnits[]     = $ev['lab_unit'] ?? 'fingerprint';
        }
    }

    if ($evidenceTotal === 0) $evidenceTotal = count($evDescriptions);

    $mapped['rlf_evidence_total[]'] = [$evidenceTotal];
    $mapped['rlf_ev_description[]'] = $evDescriptions;
    $mapped['rlf_ev_width[]']       = $evWidths;
    $mapped['rlf_ev_length[]']      = $evLengths;
    $mapped['rlf_ev_height[]']      = $evHeights;
    $mapped['rlf_ev_quantity[]']    = $evQuantities;
    $mapped['rlf_ev_label_no[]']    = $evLabelNos;
    $mapped['rlf_ev_lab_unit[]']    = $evLabUnits;

    // ---- Section 7: วิธีการดำเนินการ ----
    $methodNames   = [];
    $methodDetails = [];

    // Try global methods from first set
    if (!empty($sets)) {
        $firstSet = $sets[0];
        $globalMethods = $firstSet['global_methods'] ?? [];
        foreach ($globalMethods as $m) {
            if (!empty($m['name'])) {
                $methodNames[]   = $m['name'];
                $methodDetails[] = $m['detail'] ?? '';
            }
        }

        // Fallback: methods on individual evidence items
        if (empty($methodNames)) {
            foreach ($sets as $set) {
                $items = $set['evidence_items'] ?? [];
                foreach ($items as $item) {
                    $itemMethods = $item['methods'] ?? [];
                    foreach ($itemMethods as $m) {
                        if (!empty($m['name'])) {
                            $methodNames[]   = $m['name'];
                            $methodDetails[] = $m['detail'] ?? '';
                        }
                    }
                }
            }
        }
    }

    $mapped['rlf_method_name[]']   = $methodNames;
    $mapped['rlf_method_detail[]'] = $methodDetails;

    // ---- Section 8: การดำเนินการ ----
    $actionDest        = [];
    $actionOtherText   = '';
    $actionExhibit     = '';
    $actionReturnStn   = '';
    $forwardDepts      = [];
    $forwardOtherText  = '';

    // Collect from first set (which has global fallback)
    if (!empty($sets)) {
        $firstSet = $sets[0];
        $actionDest       = $firstSet['action_evidence_dest'] ?? [];
        $actionOtherText  = $firstSet['action_evidence_other_text'] ?? '';
        $actionExhibit    = $firstSet['action_exhibit'] ?? '';
        $actionReturnStn  = $firstSet['action_return_station'] ?? '';
        $forwardDepts     = $firstSet['action_forward_depts'] ?? [];
        $forwardOtherText = $firstSet['action_forward_other_text'] ?? '';
    }

    $mapped['rlf_action_evidence_dest']        = $actionDest;
    $mapped['rlf_action_evidence_other_text']   = $actionOtherText;
    $mapped['rlf_action_exhibit']               = $actionExhibit;
    $mapped['rlf_action_return_station']        = $actionReturnStn;
    $mapped['rlf_action_forward_dept[]']        = $forwardDepts;
    $mapped['rlf_action_forward_other_text']    = $forwardOtherText;

    return $mapped;
}

try {
    // ดึง agency info จาก rn_ReceiveNoti
    $stmtAgency = $pdo->prepare("SELECT receiveNoti_No, userReviewType, userReviewTypeVal FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
    $stmtAgency->execute([$incident_id]);
    $agencyRow = $stmtAgency->fetch(PDO::FETCH_ASSOC);

    $agencyName = '';
    $reportNo = '';
    if ($agencyRow) {
        $provinceMap = ['95' => 'ยะลา', '94' => 'ปัตตานี', '96' => 'นราธิวาส'];
        $rt = $agencyRow['userReviewType'] ?? '';
        $rv = $agencyRow['userReviewTypeVal'] ?? '';
        if ($rt === 'nvt') {
            $agencyName = 'นวท.(สบ ' . $rv . ') กสก.พฐก.';
        } elseif ($rt === 'spt') {
            $agencyName = 'ศพฐ ' . $rv;
        } elseif ($rt === 'ptjv') {
            $agencyName = 'พฐ.จว.' . ($provinceMap[strval($rv)] ?? $rv);
        }
        $reportNo = $agencyRow['receiveNoti_No'] ?? '';
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
        $checklistMapped = mapChecklistToFingerprintReport($checklistData);

        // Resolve inspectors
        $inspectors = $checklistData['inspectors'] ?? [];
        $inspectorFields = resolveInspectors($pdo, $inspectors, 'rlf');
        $checklistMapped = array_merge($checklistMapped, $inspectorFields);

            // Resolve sender position from user ID but keep the original select value intact.
        $senderVal = $checklistMapped['rlf_evidence_sender'] ?? '';
        if (!empty($senderVal) && is_numeric($senderVal)) {
            $sender = resolveUserId($pdo, $senderVal);
            if (empty($checklistMapped['rlf_evidence_sender_position'])) {
                $checklistMapped['rlf_evidence_sender_position'] = $sender['position'];
            }
        }

        $signerVal = $checklistMapped['rlf_signer_name'] ?? '';
        if (!empty($signerVal) && is_numeric($signerVal)) {
            $signer = resolveUserId($pdo, $signerVal);
            $checklistMapped['rlf_signer_name'] = $signer['fullname'];
            $checklistMapped['rlf_signer_position'] = $signer['position'];
        }

        // Agency + report no
        $checklistMapped['rlf_agency_name'] = $agencyName;
        $checklistMapped['rlf_report_no'] = $reportNo;
    }

    // ตรวจว่า report data มี fingerprint key หรือไม่
    $hasFingerprintReport = isset($reportData['fingerprint']) && is_array($reportData['fingerprint']);

    if ($hasFingerprintReport) {
        // มีข้อมูล report แล้ว → merge checklist เป็นค่าเริ่มต้น (เติมเฉพาะ field ที่ว่าง)
        if ($checklistMapped) {
            foreach ($checklistMapped as $k => $v) {
                if (!isset($reportData['fingerprint'][$k]) 
                    || $reportData['fingerprint'][$k] === '' 
                    || $reportData['fingerprint'][$k] === []) {
                    $reportData['fingerprint'][$k] = $v;
                }
            }
        }

        // Resolve sender position from user ID but keep the original select value intact.
        if (isset($reportData['fingerprint']['rlf_evidence_sender']) && is_numeric($reportData['fingerprint']['rlf_evidence_sender'])) {
            $resolvedSender = resolveUserId($pdo, $reportData['fingerprint']['rlf_evidence_sender']);
            if (empty($reportData['fingerprint']['rlf_evidence_sender_position'])) {
                $reportData['fingerprint']['rlf_evidence_sender_position'] = $resolvedSender['position'];
            }
        }

        // Resolve signer name fields because signer is a text input, not a select.
        if (isset($reportData['fingerprint']['rlf_signer_name']) && is_numeric($reportData['fingerprint']['rlf_signer_name'])) {
            $resolvedSigner = resolveUserId($pdo, $reportData['fingerprint']['rlf_signer_name']);
            $reportData['fingerprint']['rlf_signer_name'] = $resolvedSigner['fullname'];
            if (empty($reportData['fingerprint']['rlf_signer_position'])) {
                $reportData['fingerprint']['rlf_signer_position'] = $resolvedSigner['position'];
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $reportData['fingerprint'],
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'report_no' => $reportNo,
            'agency_name' => $agencyName,
            'format' => 'report'
        ], JSON_UNESCAPED_UNICODE);

    } elseif ($checklistMapped) {
        // ยังไม่เคยบันทึก report → ใช้ checklist mapped
        echo json_encode([
            'success' => true,
            'data' => $checklistMapped,
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'report_no' => $reportNo,
            'agency_name' => $agencyName,
            'format' => 'mapped_from_checklist'
        ], JSON_UNESCAPED_UNICODE);

    } else {
        echo json_encode([
            'success' => true,
            'data' => null,
            'count_report' => $countEdit,
            'edit_date' => $editDate,
            'report_no' => $reportNo,
            'agency_name' => $agencyName,
            'format' => 'empty'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
