<?php
/**
 * gen_pdf_property_report_html.php
 * Generate PDF F-CS-12 รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์
 *
 * รับ parameter: incident_id (GET)
 * ดึงข้อมูลจาก: incident_checklist_transaction.incident_report_data (JSON)
 * ถ้ายังไม่มี report data จะ fallback ใช้ incident_checklist_data แล้ว map
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../helpers/report_no.php';

// ==========================================
// 1. HELPER FUNCTIONS
// ==========================================

function thaiDateFull($datetime)
{
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $d = date('j', $ts);
    $m = (int) date('n', $ts);
    $y = date('Y', $ts) + 543;
    return $d . ' ' . $months[$m] . ' ' . $y;
}

function thaiTime($datetime)
{
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    return date('H:i', $ts);
}

function parseDateParts($datetime)
{
    if (empty($datetime)) return ['day' => '', 'month' => '', 'year' => ''];
    $ts = strtotime($datetime);
    if ($ts === false) return ['day' => '', 'month' => '', 'year' => ''];
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    return [
        'day'   => date('j', $ts),
        'month' => $months[(int)date('n', $ts)] ?? '',
        'year'  => date('Y', $ts) + 543
    ];
}

function getVal($arr, $key, $default = '')
{
    if (!is_array($arr)) return $default;
    return isset($arr[$key]) && $arr[$key] !== null ? $arr[$key] : $default;
}

function renderCheckbox($condition)
{
    return $condition ? '✓' : '';
}

function splitDatetime($dt)
{
    if (empty($dt) || $dt === 'T') return ['date' => '', 'time' => ''];
    $parts = explode('T', $dt, 2);
    return [
        'date' => $parts[0] ?? '',
        'time' => $parts[1] ?? ''
    ];
}

function h($val)
{
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

// ==========================================
// 2. FETCH DATA FROM DATABASE
// ==========================================
$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) die("Error: กรุณาระบุ incident_id");

try {
    $stmt = $pdo->prepare("SELECT incident_report_data, incident_checklist_data 
                           FROM incident_checklist_transaction 
                           WHERE incident_id = ? 
                           ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) die("Error: ไม่พบข้อมูลสำหรับ incident_id: " . $incident_id);

    $reportData = !empty($row['incident_report_data']) ? json_decode($row['incident_report_data'], true) : null;
    $checklistData = !empty($row['incident_checklist_data']) ? json_decode($row['incident_checklist_data'], true) : null;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// PREPARE USER MAP (ID => Fullname + Position)
// ==========================================
$userMap = [];
try {
    $sqlUser = "SELECT t1.user_id, 
                       CONCAT(IFNULL(t2.rank_name,''),' ',t1.first_name,' ',t1.last_name) AS fullname,
                       IFNULL(t2.rank_name,'') AS position,
                       IFNULL(t3.position_name,'') AS position_name
                FROM user_profile t1
                LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                LEFT JOIN user_position t3 ON t1.position_id = t3.position_id";
    $stmtUser = $pdo->query($sqlUser);
    while ($u = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        $userMap[$u['user_id']] = $u;
    }
} catch (Exception $e) {
    // ignore
}

// ==========================================
// AGENCY INFO
// ==========================================
$agencyType = '';
$agencyName = '';
try {
    $stmtAgency = $pdo->prepare("SELECT userReviewType, userReviewTypeVal FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
    $stmtAgency->execute([$incident_id]);
    $agencyRow = $stmtAgency->fetch(PDO::FETCH_ASSOC);
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
} catch (Exception $e) {
    // ignore
}

// ==========================================
// 3. DETERMINE DATA SOURCE
// ==========================================
$prop = [];

if ($reportData && isset($reportData['property']) && is_array($reportData['property'])) {
    $prop = $reportData['property'];
} elseif ($checklistData) {
    $gi = $checklistData['general_info'] ?? [];
    $sc = $checklistData['scene_characteristics'] ?? [];
    $ir = $checklistData['inspection_result'] ?? [];
    $ho = $checklistData['handover'] ?? [];

    $reportDt = splitDatetime($gi['report_datetime'] ?? '');
    $victimDt = splitDatetime($gi['incident_datetime'] ?? '');
    $officerDt = splitDatetime($gi['investigator_known_datetime'] ?? '');
    $inspectDt = splitDatetime($gi['inspection_datetime'] ?? '');
    $inspectAddDt = splitDatetime($gi['inspection_additional_datetime'] ?? '');
    $inspectEndDt = splitDatetime($gi['inspection_end_datetime'] ?? '');

    $victim = $gi['victim'] ?? [];
    $investigator = $gi['investigator'] ?? [];

    $prop = [
        'rp_receive_date'   => $reportDt['date'],
        'rp_receive_time'   => $reportDt['time'],
        'rp_daily_ref'      => $gi['document_no'] ?? '',
        'rp_agency_type'    => $agencyType,
        'rp_agency_name'    => $agencyName,
        'rp_notify_method[]' => $gi['report_channel'] ?? [],
        'rp_from_station'   => $gi['source_station'] ?? '',
        'rp_investigator'   => trim(($investigator['firstname'] ?? '') . ' ' . ($investigator['lastname'] ?? '')),
        'rp_crime_location' => $gi['location_detail'] ?? '',
        'rp_victim_name'    => trim(($victim['firstname'] ?? '') . ' ' . ($victim['lastname'] ?? '')),
        'rp_victim_age'     => $victim['age'] ?? '',
        'rp_victim_know_date' => $victimDt['date'],
        'rp_victim_know_time' => $victimDt['time'],
        'rp_officer_know_date' => $officerDt['date'],
        'rp_officer_know_time' => $officerDt['time'],
        'rp_inspect_date'     => $inspectDt['date'],
        'rp_inspect_time'     => $inspectDt['time'],
        'rp_inspect_add_date' => $inspectAddDt['date'],
        'rp_inspect_add_time' => $inspectAddDt['time'],
        'rp_inspector_name[]' => [],
        'rp_inspector_position[]' => [],
        'rp_building_type[]' => [],
        'rp_building_type_other' => '',
        'rp_floor_count'    => '',
        'rp_unit_count'     => '',
        'rp_mezzanine'      => '',
        'rp_rooftop'        => '',
        'rp_in_compound'    => '',
        'rp_fence'          => ($sc['fence'] ?? '') === 'มีรั้ว' ? 'มี' : (($sc['fence'] ?? '') === 'ไม่มีรั้ว' ? 'ไม่มี' : ''),
        'rp_ext_front'      => $sc['front_adjacent'] ?? '',
        'rp_ext_left'       => $sc['left_adjacent'] ?? '',
        'rp_ext_right'      => $sc['right_adjacent'] ?? '',
        'rp_ext_back'       => $sc['back_adjacent'] ?? '',
        'rp_interior_detail' => $sc['interior_detail'] ?? '',
        'rp_incident_area'  => $sc['incident_area_detail'] ?? '',
        'rp_case_behavior'  => $ir['case_behavior'] ?? '',
        'rp_scene_condition' => '',
        'rp_criminal_entry' => '',
        'rp_room_name[]'    => [],
        'rp_room_entry[]'   => [],
        'rp_room_marks[]'   => [],
        'rp_room_search_marks[]' => [],
        'rp_room_other_evidence[]' => [],
        'rp_stolen_victim_prefix[]' => [],
        'rp_stolen_victim_name[]'   => [],
        'rp_stolen_victim_role[]'   => [],
        'rp_stolen_item[]'  => [],
        'rp_evidence_count[]' => [],
        'rp_evidence_loc[]'  => [],
        'rp_evidence_signer_prefix[]' => [],
        'rp_evidence_signer_name[]'   => [],
        'rp_evidence_signer_role[]'   => [],
        'rp_evidence_action' => '',
        'rp_handover_agency' => $agencyType,
        'rp_handover_detail' => '',
        'rp_handover_to'    => '',
        'rp_handover_date'  => $inspectEndDt['date'],
        'rp_handover_time'  => $inspectEndDt['time'],
        'rp_signer_name'    => '',
        'rp_signer_position' => '',
        'rp_sign_date'      => $inspectEndDt['date'],
    ];

    // Resolve inspector IDs
    $inspectorIds = $checklistData['inspectors'] ?? [];
    if (is_array($inspectorIds)) {
        foreach ($inspectorIds as $id) {
            if (isset($userMap[$id])) {
                $prop['rp_inspector_name[]'][] = $userMap[$id]['fullname'];
                $prop['rp_inspector_position[]'][] = $userMap[$id]['position_name'] ?: $userMap[$id]['position'];
            }
        }
    }

    // Resolve handover user IDs
    $delivererId = $ho['deliverer_id'] ?? '';
    $receiverId = $ho['receiver_id'] ?? '';
    if (isset($userMap[$delivererId])) {
        $prop['rp_handover_detail'] = $userMap[$delivererId]['fullname'];
        $prop['rp_signer_name'] = $userMap[$delivererId]['fullname'];
        $prop['rp_signer_position'] = $userMap[$delivererId]['position_name'] ?: $userMap[$delivererId]['position'];
    }
    if (isset($userMap[$receiverId])) {
        $prop['rp_handover_to'] = $userMap[$receiverId]['fullname'];
    }
}

// Resolve user IDs that may still be numeric
$resolveFields = ['rp_handover_to', 'rp_signer_name'];
foreach ($resolveFields as $fk) {
    if (isset($prop[$fk]) && is_numeric($prop[$fk]) && isset($userMap[$prop[$fk]])) {
        $prop[$fk] = $userMap[$prop[$fk]]['fullname'];
    }
}

// ==========================================
// 4. PREPARE TEMPLATE VARIABLES
// ==========================================

// Report No & Year
$gen = $checklistData['general_info'] ?? [];
$rawReportNo = getVal($gen, 'report_no');

// Fallback: ถ้า checklist ไม่มี report_no → ดึงจาก rn_ReceiveNoti
if (empty($rawReportNo)) {
    try {
        $stmtRn = $pdo->prepare("SELECT receiveNotiReportNo FROM rn_ReceiveNoti WHERE id = ? AND statusDelete = 0 LIMIT 1");
        $stmtRn->execute([$incident_id]);
        $rnRow = $stmtRn->fetch(PDO::FETCH_ASSOC);
        if ($rnRow && !empty($rnRow['receiveNotiReportNo'])) {
            $rawReportNo = $rnRow['receiveNotiReportNo'];
        }
    } catch (Exception $e) {
        // ignore
    }
}

$report_no = '';
$report_year = '';
$report_year_short = '';
if (!empty($rawReportNo) && strpos($rawReportNo, '/') !== false) {
    $parts = explode('/', $rawReportNo, 2);
    $report_no = $parts[0];
    $report_year = $parts[1];
    $report_year_short = substr($report_year, -2);
} else {
    $report_no = $rawReportNo;
}
$report_no = smartThaiReportOrDoc($report_no);

// Notify method checkboxes
$notifyMethods = getVal($prop, 'rp_notify_method[]', []);
if (!is_array($notifyMethods)) $notifyMethods = [$notifyMethods];

// ★ ข้อ 7: ให้ "วิธีการรับแจ้ง" ออกมาเป็นข้อความไทย ไม่ใช่ช่องติ๊กเปล่า
require_once __DIR__ . '/notify_method_helper.php';
$notify_method_text = notifyMethodToText(
    $notifyMethods,
    (string)getVal($prop, 'rp_notify_method_other', '')
);
$chk_notify_letter = renderCheckbox(
    in_array('หนังสือ', $notifyMethods) || in_array('ทางหนังสือ', $notifyMethods) ||
    in_array('document', $notifyMethods) || in_array('letter', $notifyMethods)
);
$chk_notify_phone = renderCheckbox(
    in_array('โทรศัพท์', $notifyMethods) || in_array('ทางโทรศัพท์', $notifyMethods) ||
    in_array('phone', $notifyMethods)
);
$chk_notify_radio = renderCheckbox(
    in_array('วิทยุสื่อสาร', $notifyMethods) || in_array('ทางวิทยุสื่อสาร', $notifyMethods) ||
    in_array('radio', $notifyMethods)
);

// Dates
$receiveDateThai = thaiDateFull(getVal($prop, 'rp_receive_date'));
$receiveTime = getVal($prop, 'rp_receive_time');
$victimKnowDate = thaiDateFull(getVal($prop, 'rp_victim_know_date'));
$victimKnowTime = getVal($prop, 'rp_victim_know_time');
$officerKnowDate = thaiDateFull(getVal($prop, 'rp_officer_know_date'));
$officerKnowTime = getVal($prop, 'rp_officer_know_time');
$inspectDate = thaiDateFull(getVal($prop, 'rp_inspect_date'));
$inspectTime = getVal($prop, 'rp_inspect_time');
$inspectAddDate = thaiDateFull(getVal($prop, 'rp_inspect_add_date'));
$inspectAddTime = getVal($prop, 'rp_inspect_add_time');
$handoverDate = thaiDateFull(getVal($prop, 'rp_handover_date'));
$handoverTime = getVal($prop, 'rp_handover_time');

// Inspector rows HTML
$inspectorNames = getVal($prop, 'rp_inspector_name[]', []);
$inspectorPositions = getVal($prop, 'rp_inspector_position[]', []);
if (!is_array($inspectorNames)) $inspectorNames = [];
$inspector_rows_html = '';
foreach ($inspectorNames as $idx => $name) {
    $no = $idx + 1;
    $pos = $inspectorPositions[$idx] ?? '';
    $posText = !empty($pos) ? '  ตำแหน่ง ' . h($pos) : '';
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">5.' . $no . '.</span><span class="fd" style="margin-left:6px;">' . h($name) . $posText . '</span></div>' . "\n";
}
// for ($i = count($inspectorNames); $i < 4; $i++) {
//     $no = $i + 1;
//     $inspector_rows_html .= '<div class="fr i1"><span class="fl">5.' . $no . '.</span><span class="fd" style="margin-left:6px;"></span><span class="fl" style="margin-left:20px;">ตำแหน่ง</span><span class="fd"></span></div>' . "\n";
// }

// Building type text
$buildingTypes = getVal($prop, 'rp_building_type[]', []);
if (!is_array($buildingTypes)) $buildingTypes = [];
$buildingTypeOther = getVal($prop, 'rp_building_type_other');
$buildingTypeText = getVal($prop, 'rp_building_type_text', '');
if (empty($buildingTypeText)) {
    $btParts = [];
    foreach ($buildingTypes as $bt) { $btParts[] = $bt; }
    if (!empty($buildingTypeOther)) $btParts[] = $buildingTypeOther;
    $buildingTypeText = implode(', ', $btParts);
}

// Mezzanine / Rooftop / Compound / Fence checkboxes
$mezzanine = getVal($prop, 'rp_mezzanine');
$chk_mezzanine_yes = renderCheckbox($mezzanine === 'มี');
$chk_mezzanine_no = renderCheckbox($mezzanine === 'ไม่มี' || empty($mezzanine));
$rooftop = getVal($prop, 'rp_rooftop');
$chk_rooftop_yes = renderCheckbox($rooftop === 'มี');
$chk_rooftop_no = renderCheckbox($rooftop === 'ไม่มี' || empty($rooftop));
$compound = getVal($prop, 'rp_in_compound');
$chk_compound_yes = renderCheckbox($compound === 'มี');
$chk_compound_no = renderCheckbox($compound === 'ไม่มี' || empty($compound));
$fence = getVal($prop, 'rp_fence');
$chk_fence_yes = renderCheckbox($fence === 'มี' || $fence === 'มีรั้ว');
$chk_fence_no = renderCheckbox($fence === 'ไม่มี' || $fence === 'ไม่มีรั้ว');

// 7.3 Room rows HTML
$roomNames = getVal($prop, 'rp_room_name[]', []);
$roomEntries = getVal($prop, 'rp_room_entry[]', []);
$roomMarks = getVal($prop, 'rp_room_marks[]', []);
$roomSearchMarks = getVal($prop, 'rp_room_search_marks[]', []);
$roomOtherEvidence = getVal($prop, 'rp_room_other_evidence[]', []);
if (!is_array($roomNames)) $roomNames = [];
$room_rows_html = '';
if (count($roomNames) > 0) {
    foreach ($roomNames as $idx => $rname) {
        $no = $idx + 1;
        $subNum = "7.3.$no";
        $room_rows_html .= '<div class="i2" style="margin-top:6px;">';
        $room_rows_html .= '<div class="fl-b">' . $subNum . ' ที่ห้อง <span style="font-weight:normal;">' . h($rname) . '</span></div>';
        $room_rows_html .= '<div class="fr i3"><span class="fl">' . $subNum . '.1 ทางเข้าของคนร้าย พบ/ไม่พบรอยจัดที่</span><span class="fd">' . h($roomEntries[$idx] ?? '') . '</span></div>';
        $room_rows_html .= '<div class="fr i3"><span class="fl">' . $subNum . '.2 พบรอยจัดที่</span><span class="fd">' . h($roomMarks[$idx] ?? '') . '</span></div>';
        $room_rows_html .= '<div class="fr i3"><span class="fl">' . $subNum . '.3 พบร่องรอยรื้อค้นที่</span><span class="fd">' . h($roomSearchMarks[$idx] ?? '') . '</span></div>';
        $room_rows_html .= '<div class="fr i3"><span class="fl">' . $subNum . '.4 วัตถุพยานอื่นๆที่ตรวจพบ</span><span class="fd">' . h($roomOtherEvidence[$idx] ?? '') . '</span></div>';
        $room_rows_html .= '</div>';
    }
} else {
    $room_rows_html .= '<div class="i2" style="margin-top:6px;">';
    $room_rows_html .= '<div class="fl-b">7.3.1 ที่ห้อง</div>';
    $room_rows_html .= '<div class="fr i3"><span class="fl">7.3.1.1 ทางเข้าของคนร้าย พบ/ไม่พบรอยจัดที่</span><span class="fd"></span></div>';
    $room_rows_html .= '<div class="fr i3"><span class="fl">7.3.1.2 พบรอยจัดที่</span><span class="fd"></span></div>';
    $room_rows_html .= '<div class="fr i3"><span class="fl">7.3.1.3 พบร่องรอยรื้อค้นที่</span><span class="fd"></span></div>';
    $room_rows_html .= '<div class="fr i3"><span class="fl">7.3.1.4 วัตถุพยานอื่นๆที่ตรวจพบ</span><span class="fd"></span></div>';
    $room_rows_html .= '</div>';
}

// 7.4 Stolen property rows HTML
$stolenPrefixes = getVal($prop, 'rp_stolen_victim_prefix[]', []);
$stolenNames = getVal($prop, 'rp_stolen_victim_name[]', []);
$stolenRoles = getVal($prop, 'rp_stolen_victim_role[]', []);
$stolenItems = getVal($prop, 'rp_stolen_item[]', []);
if (!is_array($stolenPrefixes)) $stolenPrefixes = [];
if (!is_array($stolenNames)) $stolenNames = [];
if (!is_array($stolenRoles)) $stolenRoles = [];
if (!is_array($stolenItems)) $stolenItems = [];
$stolen_rows_html = '';
if (count($stolenNames) > 0) {
    foreach ($stolenNames as $idx => $sname) {
        $no = $idx + 1;
        $prefix = $stolenPrefixes[$idx] ?? '';
        $role = $stolenRoles[$idx] ?? '';
        $stolen_rows_html .= '<div class="i2" style="margin-top:4px;">';
        $stolen_rows_html .= '<div class="fr"><span class="fl-b">7.4.' . $no . '</span>';
        $stolen_rows_html .= '<span class="fl" style="margin-left:6px;">' . h($prefix) . '</span>';
        $stolen_rows_html .= '<span class="fd">' . h($sname) . '</span>';
        $stolen_rows_html .= '<span class="fl">' . h($role) . '</span>';
        $stolen_rows_html .= '<span class="fl" style="margin-left:6px;">แจ้งว่า คนร้ายโจรกรรมทรัพย์สินไปดังนี้</span></div>';
        $stolen_rows_html .= '</div>';
    }
    // Flatten comma-separated stolen items into individual rows
    $flatStolenItems = [];
    foreach ($stolenItems as $item) {
        $subItems = array_map('trim', explode(',', $item));
        foreach ($subItems as $sub) {
            if ($sub !== '') $flatStolenItems[] = $sub;
        }
    }
    if (count($flatStolenItems) > 0) {
        foreach ($flatStolenItems as $si => $item) {
            $stolen_rows_html .= '<div class="fr i3"><span class="fl">' . ($si + 1) . '.</span><span class="fd">' . h($item) . '</span></div>';
        }
    } else {
        $stolen_rows_html .= '<div class="fr i3"><span class="fl">1.</span><span class="fd"></span></div>';
    }
} else {
    $stolen_rows_html .= '<div class="i2" style="margin-top:4px;">';
    $stolen_rows_html .= '<div class="fr"><span class="fl-b">7.4.1</span>';
    $stolen_rows_html .= '<span class="fl" style="margin-left:6px;">นาย/นาง/นางสาว/อื่นๆ</span>';
    $stolen_rows_html .= '<span class="fd"></span>';
    $stolen_rows_html .= '<span class="fl">แจ้งว่าทรัพย์สินที่ถูกโจรกรรมดังนี้</span></div>';
    $stolen_rows_html .= '<div class="fr i3"><span class="fl">1.</span><span class="fd"></span></div>';
    $stolen_rows_html .= '</div>';
}

// 7.5 Evidence rows HTML
$evidenceCounts = getVal($prop, 'rp_evidence_count[]', []);
$evidenceLocs = getVal($prop, 'rp_evidence_loc[]', []);
$evidenceSignerPrefixes = getVal($prop, 'rp_evidence_signer_prefix[]', []);
$evidenceSignerNames = getVal($prop, 'rp_evidence_signer_name[]', []);
$evidenceSignerRoles = getVal($prop, 'rp_evidence_signer_role[]', []);
if (!is_array($evidenceCounts)) $evidenceCounts = [];
if (!is_array($evidenceLocs)) $evidenceLocs = [];
if (!is_array($evidenceSignerPrefixes)) $evidenceSignerPrefixes = [];
if (!is_array($evidenceSignerNames)) $evidenceSignerNames = [];
if (!is_array($evidenceSignerRoles)) $evidenceSignerRoles = [];
$evidence_rows_html = '';
$evidenceBlockCount = max(count($evidenceCounts), 1);
for ($b = 0; $b < $evidenceBlockCount; $b++) {
    $no = $b + 1;
    $cnt = $evidenceCounts[$b] ?? '';
    $sigPrefix = $evidenceSignerPrefixes[$b] ?? '';
    $sigName = $evidenceSignerNames[$b] ?? '';
    $sigRole = $evidenceSignerRoles[$b] ?? '';
    $evidence_rows_html .= '<div class="i2" style="margin-top:4px;">';
    $evidence_rows_html .= '<div class="fl-b">7.5.' . $no . ' ตรวจเก็บวัตถุพยาน รอยลายนิ้วมือแฝง/ฝ่ามือแฝง/ฝ่าเท้าแฝง<span class="fl">&nbsp;จำนวน</span><span class="fd-l">' . h($cnt) . '</span><span class="fl">แผ่น&nbsp;ที่</span></div>';
    // Flatten comma-separated evidence locations into individual rows
    $flatEvidenceLocs = [];
    foreach ($evidenceLocs as $loc) {
        $subLocs = array_map('trim', explode(',', $loc));
        foreach ($subLocs as $sub) {
            if ($sub !== '') $flatEvidenceLocs[] = $sub;
        }
    }
    if (count($flatEvidenceLocs) > 0) {
        foreach ($flatEvidenceLocs as $li => $loc) {
            $evidence_rows_html .= '<div class="fr i3"><span class="fl">' . ($li + 1) . '.</span><span class="fd">' . h($loc) . '</span></div>';
        }
    } else {
        $evidence_rows_html .= '<div class="fr i3"><span class="fl">1.</span><span class="fd"></span></div>';
    }
    $signerFullText = trim(h($sigPrefix) . ' ' . h($sigName));
    $signerRoleText = !empty($sigRole) ? h($sigRole) : '';
    $evidence_rows_html .= '<div class="fr i3" style="flex-wrap:wrap;"><span class="fl" style="white-space:normal;">และได้ให้ นาย/นาง/นางสาว/อื่นๆ </span><span class="fd">' . $signerFullText . '</span><span class="fl" style="white-space:normal;">' . $signerRoleText . ' /ผู้เสียหาย/อื่นๆ</span></div>';
    $evidence_rows_html .= '<div class="fr i3"><span class="fl" style="white-space:normal;">ลงลายมือชื่อในแบบเก็บรอยลายนิ้วมือแฝงไว้เป็นหลักฐาน</span></div>';
    $evidence_rows_html .= '</div>';
}

// Agency short
$agencyShort = getVal($prop, 'rp_agency_name', $agencyName);

// Report no for filename
$report_no_filename = preg_replace('/[^a-zA-Z0-9\-_]/', '', str_replace('/', '-', $rawReportNo));

// Sign date parts
$signDateParts = parseDateParts(getVal($prop, 'rp_sign_date'));

// ==========================================
// 5. BUILD REPLACEMENTS
// ==========================================
$replacements = [
    '{{report_no}}' => h($report_no),
    '{{report_year_short}}' => h($report_year_short),
    '{{report_no_filename}}' => h($report_no_filename),
    '{{total_pages}}' => '4',
    '{{agency_name}}' => h($agencyShort),
    '{{agency_short}}' => h($agencyShort),

    // Section 1
    '{{receive_date}}' => h($receiveDateThai),
    '{{receive_time}}' => h($receiveTime),
    '{{daily_ref}}' => h(getVal($prop, 'rp_daily_ref')),
    '{{notify_method_text}}' => $notify_method_text,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_phone}}' => $chk_notify_phone,
    '{{chk_notify_radio}}' => $chk_notify_radio,
    '{{from_station}}' => h(getVal($prop, 'rp_from_station')),
    '{{investigator_name}}' => h(getVal($prop, 'rp_investigator')),

    // Section 2
    '{{crime_location}}' => h(getVal($prop, 'rp_crime_location')),
    '{{victim_name}}' => h(getVal($prop, 'rp_victim_name')),
    '{{victim_age}}' => h(getVal($prop, 'rp_victim_age')),

    // Section 3
    '{{victim_know_date}}' => h($victimKnowDate),
    '{{victim_know_time}}' => h($victimKnowTime),
    '{{officer_know_date}}' => h($officerKnowDate),
    '{{officer_know_time}}' => h($officerKnowTime),

    // Section 4
    '{{inspect_date}}' => h($inspectDate),
    '{{inspect_time}}' => h($inspectTime),
    '{{inspect_add_date}}' => h($inspectAddDate),
    '{{inspect_add_time}}' => h($inspectAddTime),

    // Section 5
    '{{inspector_rows}}' => $inspector_rows_html,

    // Section 6
    '{{building_type_text}}' => h($buildingTypeText),
    '{{floor_count}}' => h(getVal($prop, 'rp_floor_count')),
    '{{unit_count}}' => h(getVal($prop, 'rp_unit_count')),
    '{{chk_mezzanine_yes}}' => $chk_mezzanine_yes,
    '{{chk_mezzanine_no}}' => $chk_mezzanine_no,
    '{{chk_rooftop_yes}}' => $chk_rooftop_yes,
    '{{chk_rooftop_no}}' => $chk_rooftop_no,
    '{{chk_compound_yes}}' => $chk_compound_yes,
    '{{chk_compound_no}}' => $chk_compound_no,
    '{{chk_fence_yes}}' => $chk_fence_yes,
    '{{chk_fence_no}}' => $chk_fence_no,
    '{{ext_front}}' => h(getVal($prop, 'rp_ext_front')),
    '{{ext_left}}' => h(getVal($prop, 'rp_ext_left')),
    '{{ext_right}}' => h(getVal($prop, 'rp_ext_right')),
    '{{ext_back}}' => h(getVal($prop, 'rp_ext_back')),
    '{{interior_detail}}' => h(getVal($prop, 'rp_interior_detail')),
    '{{incident_area}}' => h(getVal($prop, 'rp_incident_area')),

    // Section 7
    '{{case_behavior}}' => h(getVal($prop, 'rp_case_behavior')),
    '{{scene_condition}}' => h(getVal($prop, 'rp_scene_condition')),
    '{{criminal_entry}}' => h(getVal($prop, 'rp_criminal_entry')),

    // 7.3 rooms
    '{{room_rows}}' => $room_rows_html,

    // 7.4 stolen
    '{{stolen_rows}}' => $stolen_rows_html,

    // 7.5 evidence
    '{{evidence_rows}}' => $evidence_rows_html,

    // 7.6
    '{{evidence_action}}' => h(getVal($prop, 'rp_evidence_action')),

    // 7.7 handover
    '{{handover_agency}}' => h(getVal($prop, 'rp_handover_agency')),
    '{{handover_detail}}' => h(getVal($prop, 'rp_handover_detail')),
    '{{handover_to}}' => h(getVal($prop, 'rp_handover_to')),
    '{{handover_date}}' => h($handoverDate),
    '{{handover_time}}' => h($handoverTime),

    // Signature
    '{{signer_name}}' => h(getVal($prop, 'rp_signer_name')),
    '{{signer_position}}' => h(getVal($prop, 'rp_signer_position')),
    '{{sign_date_day}}' => h($signDateParts['day']),
    '{{sign_date_month}}' => h($signDateParts['month']),
    '{{sign_date_year}}' => h($signDateParts['year']),
];

// ==========================================
// 6. READ TEMPLATE AND REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_property_report_preview.html');
if ($htmlTemplate === false) {
    die("Error: ไม่สามารถอ่านไฟล์ template ได้");
}

$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// ==========================================
// 7. OUTPUT HTML
// ==========================================
header('Content-Type: text/html; charset=utf-8');
echo $htmlContent;
