<?php
/**
 * API: Get Traffic Report Data
 * ดึงข้อมูล incident_report_data จาก incident_checklist_transaction
 * สำหรับโหลดข้อมูลร่างรายงานคดีจราจร
 *
 * ถ้า incident_report_data มี traffic key → ส่งคืนตรงๆ
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

function splitDateTimeValue($value) {
    $value = trim((string)$value);
    if ($value === '') return ['', ''];

    if (strpos($value, 'T') !== false) {
        $parts = explode('T', $value, 2);
        $date = trim($parts[0]);
        $time = trim($parts[1]);
        if (preg_match('/^(\d{1,2}:\d{2})/', $time, $m)) {
            $time = $m[1];
        }
        return [$date, $time];
    }

    if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+(\d{1,2}:\d{2})/', $value, $m)) {
        return [$m[1], $m[2]];
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return [$value, ''];
    }

    if (preg_match('/^(\d{1,2}:\d{2})$/', $value, $m)) {
        return ['', $m[1]];
    }

    return ['', ''];
}

function mapNotifyMethods($methods) {
    if (!is_array($methods)) return [];
    $map = [
        'ทางโทรศัพท์' => 'โทรศัพท์',
        'ทางวิทยุสื่อสาร' => 'วิทยุสื่อสาร',
        'ทางหนังสือ' => 'หนังสือ',
        'โทรศัพท์' => 'โทรศัพท์',
        'วิทยุสื่อสาร' => 'วิทยุสื่อสาร',
        'หนังสือ' => 'หนังสือ'
    ];
    $result = [];
    foreach ($methods as $m) {
        $m = trim((string)$m);
        if ($m === '') continue;
        $result[] = $map[$m] ?? $m;
    }
    return array_values(array_unique($result));
}

// ============================================
// Map traffic checklist JSON → report fields (rt_*)
// ============================================
function mapChecklistToTrafficReport($ck) {
    $gi = $ck['general_info'] ?? [];
    $si = $ck['scene_info'] ?? [];
    $ip = $ck['inspection_purpose'] ?? [];
    $fr = $ck['forensic_results'] ?? [];
    $cr = $ck['comparison_results'] ?? [];
    $ho = $ck['handover'] ?? [];
    $pr = $ck['photo_records'] ?? [];

    $vehicles = $si['vehicles_at_scene'] ?? ($si['vehicles'] ?? []);
    $analysisVehicles = $fr['vehicle_analysis'] ?? [];
    $comparisons = $cr['comparisons'] ?? [];

    $notifyMethods = mapNotifyMethods($gi['report_channel'] ?? ($gi['notify_method'] ?? []));

    list($reportDateFromDt, $reportTimeFromDt) = splitDateTimeValue($gi['report_datetime'] ?? '');
    list($victimDateFromDt, $victimTimeFromDt) = splitDateTimeValue($gi['incident_datetime'] ?? '');
    list($inspectDateFromDt, $inspectTimeFromDt) = splitDateTimeValue($gi['inspection_datetime'] ?? '');

    $reportNo = trim((string)($gi['report_no'] ?? ''));
    $reportRef = '';
    $reportYear = '';
    if ($reportNo !== '') {
        $parts = explode('/', $reportNo);
        $reportRef = trim((string)($parts[0] ?? ''));
        $reportYearRaw = trim((string)($parts[1] ?? ''));
        $reportYearRaw = preg_replace('/[^0-9]/', '', $reportYearRaw);
        if ($reportYearRaw !== '') {
            $reportYear = strlen($reportYearRaw) > 2 ? substr($reportYearRaw, -2) : $reportYearRaw;
        }
    }

    $result = [
        // Section 1: การรับแจ้งเหตุ
        'rt_report_ref' => $reportRef,
        'rt_report_year' => $reportYear,
        'rt_receive_date' => $gi['case_date'] ?? ($gi['report_date'] ?? $reportDateFromDt),
        'rt_receive_time' => $gi['case_time'] ?? ($gi['report_time'] ?? $reportTimeFromDt),
        'rt_daily_ref' => $gi['document_no'] ?? '',
        'rt_notify_method[]' => $notifyMethods,
        'rt_from_station' => $gi['source_station'] ?? ($gi['police_station'] ?? ''),
        'rt_officer_joint' => '',
        'rt_officer_name' => $gi['investigator']['name'] ?? ($gi['investigator_name'] ?? ''),
        'rt_officer_role' => 'พนักงานสอบสวน',

        // Section 2 / scene
        'rt_scene_location' => $si['crime_location'] ?? ($gi['location_detail'] ?? ''),

        // Section 2: จุดประสงค์
        'rt_purpose[]' => isset($ip['selected_purposes']) && is_array($ip['selected_purposes']) ? array_values($ip['selected_purposes']) : [],
        'rt_purpose_qty_1' => $ip['qty_1'] ?? '',
        'rt_purpose_qty_2' => $ip['qty_2'] ?? '',
        'rt_purpose_other_text' => $ip['other_text'] ?? '',

        // Vehicle data
        'rt_vehicle_type[]' => [],
        'rt_vehicle_brand[]' => [],
        'rt_vehicle_color[]' => [],
        'rt_vehicle_plate[]' => [],
        'rt_vehicle_plate_qty[]' => [],

        // Section 3: วันเวลาที่ทราบเหตุ
        'rt_victim_know_date' => $gi['victim_know_date'] ?? $victimDateFromDt,
        'rt_victim_know_time' => $gi['victim_know_time'] ?? $victimTimeFromDt,
        'rt_officer_know_date' => $gi['officer_know_date'] ?? '',
        'rt_officer_know_time' => $gi['officer_know_time'] ?? '',

        // Section 4: วันเวลาตรวจสถานที่
        'rt_case_behavior' => $fr['case_behavior'] ?? '',
        'rt_inspect_location' => $fr['inspection_location'] ?? '',
        'rt_inspect_place' => $fr['inspection_target'] ?? '',
        'rt_analyze_date' => $fr['inspection_date'] ?? ($gi['inspect_date'] ?? $inspectDateFromDt),
        'rt_analyze_time' => $fr['inspection_time'] ?? ($gi['inspect_time'] ?? $inspectTimeFromDt),

        // รายการผลการตรวจรถ
        'rt_analysis_vehicle_desc[]' => [],
        'rt_damage_front_detail_1[]' => [],
        'rt_damage_front_detail_2[]' => [],
        'rt_damage_left_detail[]' => [],
        'rt_damage_rear_detail[]' => [],
        'rt_damage_right_detail[]' => [],
        'rt_damage_other_detail[]' => [],

        // รายละเอียดเพิ่มเติมและสรุป
        'rt_analysis_extra' => '',
        'rt_scene_detail' => $si['crime_location'] ?? ($gi['location_detail'] ?? ''),
        'rt_compare_total_items' => $cr['total_items'] ?? '',
        'rt_compare_5_1' => '',
        'rt_compare_5_1_sub' => '',
        'rt_compare_5_ref' => '',
        'rt_compare_5_2' => '',
        'rt_compare_5_3' => '',
        'rt_compare_5_4' => '',
        'rt_opinion' => $ck['opinion'] ?? '',

        // ลงชื่อ
        'rt_sign_name' => '',
        'rt_sign_position' => $ho['sender']['position'] ?? ($ho['deliverer_pos'] ?? ''),
        'rt_sign_date' => $ho['inspection_end_date'] ?? '',
    ];

    // Map vehicles
    if (!empty($vehicles)) {
        foreach ($vehicles as $v) {
            $result['rt_vehicle_type[]'][] = $v['detail'] ?? ($v['vehicle_detail'] ?? ($v['type'] ?? ''));
            $result['rt_vehicle_brand[]'][] = $v['brand'] ?? ($v['vehicle_brand'] ?? '');
            $result['rt_vehicle_color[]'][] = $v['color'] ?? ($v['vehicle_color'] ?? '');
            $result['rt_vehicle_plate[]'][] = $v['plate_no'] ?? ($v['vehicle_plate_no'] ?? ($v['plate'] ?? ''));
            $result['rt_vehicle_plate_qty[]'][] = '1';
        }
    }

    if (!empty($analysisVehicles) && is_array($analysisVehicles)) {
        foreach ($analysisVehicles as $av) {
            $traces = $av['traces'] ?? [];
            $frontTraces = isset($traces['front']) && is_array($traces['front']) ? $traces['front'] : [];
            $leftTraces = isset($traces['left']) && is_array($traces['left']) ? $traces['left'] : [];
            $rightTraces = isset($traces['right']) && is_array($traces['right']) ? $traces['right'] : [];
            $backTraces = isset($traces['back']) && is_array($traces['back']) ? $traces['back'] : [];

            $joinDetails = function($rows) {
                $out = [];
                foreach ($rows as $r) {
                    $d = trim((string)($r['detail'] ?? ''));
                    if ($d !== '') $out[] = $d;
                }
                return implode("\n", $out);
            };

            $result['rt_analysis_vehicle_desc[]'][] = $av['condition'] ?? '';
            $result['rt_damage_front_detail_1[]'][] = trim((string)($frontTraces[0]['detail'] ?? ''));
            $result['rt_damage_front_detail_2[]'][] = trim((string)($frontTraces[1]['detail'] ?? ''));
            $result['rt_damage_left_detail[]'][] = $joinDetails($leftTraces);
            $result['rt_damage_rear_detail[]'][] = $joinDetails($backTraces);
            $result['rt_damage_right_detail[]'][] = $joinDetails($rightTraces);
            $result['rt_damage_other_detail[]'][] = trim((string)($av['mod_detail'] ?? ''));
        }
    }

    if (empty($result['rt_compare_total_items']) && !empty($comparisons) && is_array($comparisons)) {
        $result['rt_compare_total_items'] = (string)count($comparisons);
    }

    if (!empty($comparisons) && is_array($comparisons)) {
        $first = $comparisons[0] ?? [];
        $second = $comparisons[1] ?? [];

        $result['rt_compare_5_1'] = trim(
            (string)($first['trace_type'] ?? '') . ' ' .
            (string)($first['area_a'] ?? '')
        );
        $result['rt_compare_5_1_sub'] = trim((string)($first['ref_a'] ?? ''));
        $result['rt_compare_5_ref'] = trim((string)($first['item_no'] ?? ''));
        $result['rt_compare_5_2'] = trim(
            (string)($first['area_b'] ?? '') .
            ((trim((string)($first['ref_b'] ?? '')) !== '') ? (' ' . trim((string)$first['ref_b'])) : '')
        );
        $result['rt_compare_5_3'] = trim(
            (string)($second['trace_type'] ?? '') . ' ' .
            (string)($second['area_a'] ?? '') . ' ' .
            (string)($second['area_b'] ?? '')
        );

        $lines = [];
        foreach ($comparisons as $cmp) {
            $line = trim(
                (string)($cmp['item_no'] ?? '') . ' ' .
                (string)($cmp['trace_type'] ?? '') . ' ' .
                (string)($cmp['area_a'] ?? '') . ' ' .
                (string)($cmp['area_b'] ?? '')
            );
            if ($line !== '') $lines[] = $line;
        }
        $result['rt_compare_5_4'] = implode("\n", $lines);
    }

    $analysisExtra = [];
    if (!empty($ck['evidences']) && is_array($ck['evidences'])) {
        foreach ($ck['evidences'] as $ev) {
            $d = trim((string)($ev['detail'] ?? ''));
            if ($d !== '') $analysisExtra[] = $d;
        }
    }
    if (!empty($pr['start']) || !empty($pr['end']) || !empty($pr['amount'])) {
        $analysisExtra[] = 'ภาพถ่าย: ' . trim((string)($pr['start'] ?? '')) . ' - ' . trim((string)($pr['end'] ?? '')) . ' จำนวน ' . trim((string)($pr['amount'] ?? '')) . ' ภาพ';
    }
    if (!empty($analysisExtra)) {
        $result['rt_analysis_extra'] = implode("\n", $analysisExtra);
    }

    if (!empty($ho['sender']['name_id'])) {
        $result['rt_sign_name'] = $ho['sender']['name_id'];
    } elseif (!empty($ho['deliverer_id'])) {
        $result['rt_sign_name'] = $ho['deliverer_id'];
    }

    return $result;
}

// ============================================
// Map rn_ReceiveNoti row -> report defaults (rt_*)
// ============================================
function mapReceiveNotiToTrafficReport($rn) {
    if (empty($rn) || !is_array($rn)) return [];

    $notifyMethods = [];
    $device = $rn['complaints_From_Device'] ?? '';
    if ($device === 't') {
        $notifyMethods[] = 'โทรศัพท์';
    } elseif ($device === 'r') {
        $notifyMethods[] = 'วิทยุสื่อสาร';
    }

    $reportNo = trim((string)($rn['receiveNotiReportNo'] ?? ''));
    $reportRef = $reportNo;
    $reportYear = '';
    if ($reportNo !== '') {
        $parts = explode('/', $reportNo);
        if (count($parts) >= 2) {
            $reportRef = trim($parts[0]);
            $reportYear = preg_replace('/[^0-9]/', '', trim($parts[1]));
            if (strlen($reportYear) > 2) {
                $reportYear = substr($reportYear, -2);
            }
        }
    }

    $createDate = (string)($rn['create_date'] ?? '');
    $receiveDate = '';
    if ($createDate !== '') {
        $ts = strtotime($createDate);
        if ($ts !== false) {
            $receiveDate = date('Y-m-d', $ts);
        }
    }

    $receiveTime = '';
    $timeOccurrence = trim((string)($rn['time_Occurrence'] ?? ''));
    if ($timeOccurrence !== '') {
        if (preg_match('/(\\d{1,2}:\\d{2})/', $timeOccurrence, $m)) {
            $receiveTime = $m[1];
        }
    }

    $officerName = trim(
        (string)($rn['inquiry_official_first_name'] ?? '') .
        ((empty($rn['inquiry_official_first_name']) || empty($rn['inquiry_official_last_name'])) ? '' : ' ') .
        (string)($rn['inquiry_official_last_name'] ?? '')
    );

    return [
        'rt_daily_ref' => $rn['receiveNoti_No'] ?? '',
        'rt_report_ref' => $reportRef,
        'rt_report_year' => $reportYear,
        'rt_receive_date' => $receiveDate,
        'rt_receive_time' => $receiveTime,
        'rt_from_station' => $rn['complaints_From'] ?? '',
        'rt_scene_location' => $rn['location_crime'] ?? '',
        'rt_scene_detail' => $rn['location_crime'] ?? '',
        'rt_notify_method[]' => $notifyMethods,
        'rt_officer_name' => $officerName,
        'rt_officer_role' => 'พนักงานสอบสวน'
    ];
}

try {
    // ดึงข้อมูลหลักจาก rn_ReceiveNoti เพื่อเติมค่าเริ่มต้นในฟอร์ม
    $stmtRn = $pdo->prepare("SELECT receiveNoti_No, receiveNotiReportNo, complaints_From, complaints_From_Device,
                                    location_crime, create_date, time_Occurrence,
                                    inquiry_official_first_name, inquiry_official_last_name,
                                    userReviewType, userReviewTypeVal
                             FROM rn_ReceiveNoti
                             WHERE id = ? LIMIT 1");
    $stmtRn->execute([$incident_id]);
    $rnRow = $stmtRn->fetch(PDO::FETCH_ASSOC) ?: [];

    // ดึง agency info จาก rn_ReceiveNoti
    $agencyRow = $rnRow;

    $agencyType = '';
    $agencyName = '';
    if ($agencyRow) {
        $provinceMap = ['95' => 'ยะลา', '94' => 'ปัตตานี', '96' => 'นราธิวาส'];
        $rt = $agencyRow['userReviewType'] ?? '';
        $rv = $agencyRow['userReviewTypeVal'] ?? '';
        if ($rt === 'nvt') {
            $agencyType = 'กองพิสูจน์หลักฐานกลาง';
            $agencyName = 'นวท.(สบ ' . $rv . ') กสก.พฐก.';
        } elseif ($rt === 'spt') {
            $agencyType = 'ศูนย์พิสูจน์หลักฐาน';
            $agencyName = 'ศพฐ ' . $rv;
        } elseif ($rt === 'ptjv') {
            $agencyType = 'พิสูจน์หลักฐานจังหวัด';
            $agencyName = 'พฐ.จว.' . ($provinceMap[strval($rv)] ?? $rv);
        }
    }

    $agencyCenterName = '';
    $agencyProvinceName = '';
    if ($agencyType === 'ศูนย์พิสูจน์หลักฐาน') {
        $agencyCenterName = $agencyName;
    } elseif ($agencyType === 'พิสูจน์หลักฐานจังหวัด') {
        $agencyProvinceName = $agencyName;
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
        $checklistMapped = mapChecklistToTrafficReport($checklistData);

        // Resolve inspectors
        $inspectors = $checklistData['inspectors'] ?? [];
        $inspectorFields = resolveInspectors($pdo, $inspectors, 'rt');
        $checklistMapped = array_merge($checklistMapped, $inspectorFields);

        // Agency info
        $checklistMapped['rt_agency_type'] = $agencyType;
        $checklistMapped['rt_agency_name'] = $agencyName;
        $checklistMapped['rt_agency_center_name'] = $agencyCenterName;
        $checklistMapped['rt_agency_province_name'] = $agencyProvinceName;

        // Resolve signer user id -> display name when source stores user id
        if (!empty($checklistMapped['rt_sign_name'])) {
            $resolvedSigner = resolveUserId($pdo, $checklistMapped['rt_sign_name']);
            if (!empty($resolvedSigner['fullname'])) {
                $checklistMapped['rt_sign_name'] = trim((string)$resolvedSigner['fullname']);
            }
            if (empty($checklistMapped['rt_sign_position']) && !empty($resolvedSigner['position'])) {
                $checklistMapped['rt_sign_position'] = trim((string)$resolvedSigner['position']);
            }
        }
    }

    // Map จาก rn_ReceiveNoti เป็นค่าเริ่มต้นเพิ่มเติม
    $rnMapped = mapReceiveNotiToTrafficReport($rnRow);
    $rnMapped['rt_agency_type'] = $agencyType;
    $rnMapped['rt_agency_name'] = $agencyName;
    $rnMapped['rt_agency_center_name'] = $agencyCenterName;
    $rnMapped['rt_agency_province_name'] = $agencyProvinceName;

    // ตรวจว่า report data มี traffic key หรือไม่
    $hasTrafficReport = isset($reportData['traffic']) && is_array($reportData['traffic']);

    if ($hasTrafficReport) {
        // มีข้อมูล report แล้ว → merge ค่า default (เติมเฉพาะ field ที่ว่าง)
        $baseDefaults = $checklistMapped ?: [];
        foreach ($rnMapped as $k => $v) {
            if (!isset($baseDefaults[$k]) || $baseDefaults[$k] === '' || $baseDefaults[$k] === []) {
                $baseDefaults[$k] = $v;
                continue;
            }
            if (is_array($baseDefaults[$k]) && is_array($v)) {
                $baseDefaults[$k] = array_values(array_unique(array_merge($baseDefaults[$k], $v)));
            }
        }

        foreach ($baseDefaults as $k => $v) {
                if (!isset($reportData['traffic'][$k])
                    || $reportData['traffic'][$k] === ''
                    || $reportData['traffic'][$k] === []) {
                    $reportData['traffic'][$k] = $v;
                }
        }

        echo json_encode([
            'success' => true,
            'data' => $reportData['traffic'],
            'count_report' => $countEdit,
            'edit_date' => $editDate
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // ยังไม่เคยบันทึก → รวม checklist + rn defaults แล้วส่งกลับ
        $initialData = $checklistMapped ?: [];
        foreach ($rnMapped as $k => $v) {
            if (!isset($initialData[$k]) || $initialData[$k] === '' || $initialData[$k] === []) {
                $initialData[$k] = $v;
                continue;
            }
            if (is_array($initialData[$k]) && is_array($v)) {
                $initialData[$k] = array_values(array_unique(array_merge($initialData[$k], $v)));
            }
        }

        echo json_encode([
            'success' => true,
            'data' => !empty($initialData) ? $initialData : new \stdClass(),
            'count_report' => $countEdit,
            'edit_date' => $editDate
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
