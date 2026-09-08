<?php
/**
 * gen_pdf_life_report_indoor_html.php
 * Generate PDF F-CS-13 รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต (ในอาคาร)
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

function thaiDate($datetime)
{
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    $months = [null, 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $year = date('Y', $ts) + 543;
    return date('j', $ts) . ' ' . $months[date('n', $ts)] . ' ' . $year;
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
// PREPARE USER MAP (ID => Fullname)
// ==========================================
$userMap = [];
try {
    $sqlUser = "SELECT t1.user_id, 
                       CONCAT(IFNULL(t2.rank_name,''),' ',t1.first_name,' ',t1.last_name) AS fullname,
                       IFNULL(t2.rank_name,'') AS position
                FROM user_profile t1
                LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id";
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
// ถ้ามี reportData format ใหม่ (มี indoor key) → ใช้โดยตรง
// ถ้าไม่มี → map จาก checklist data
$indoor = [];

if ($reportData && isset($reportData['indoor']) && is_array($reportData['indoor'])) {
    $indoor = $reportData['indoor'];
} elseif ($checklistData) {
    // Map from checklist data
    $gi = $checklistData['general_info'] ?? [];
    $sc = $checklistData['scene_characteristics'] ?? [];
    $ir = $checklistData['inspection_result'] ?? [];
    $ho = $checklistData['handover'] ?? [];
    $st = $sc['structure'] ?? [];

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

    // Clothing text
    $clothingParts = [];
    if (!empty($clothing['shirt'])) $clothingParts[] = 'เสื้อ: ' . $clothing['shirt'];
    if (!empty($clothing['pants'])) $clothingParts[] = 'กางเกง: ' . $clothing['pants'];
    if (!empty($clothing['shoes'])) $clothingParts[] = 'รองเท้า/ถุงเท้า: ' . $clothing['shoes'];
    if (!empty($clothing['accessories'])) $clothingParts[] = 'เครื่องประดับ: ' . $clothing['accessories'];
    if (!empty($clothing['tattoo'])) $clothingParts[] = 'รอยสัก/แผลเป็น: ' . $clothing['tattoo'];
    if (!empty($clothing['other'])) $clothingParts[] = 'อื่นๆ: ' . $clothing['other'];
    $clothingText = implode(', ', $clothingParts);

    // Wound text
    $woundParts = [];
    if (!empty($wound['count'])) $woundParts[] = 'จำนวน ' . $wound['count'] . ' แห่ง';
    if (!empty($wound['description'])) $woundParts[] = $wound['description'];
    if (!empty($wound['detail'])) $woundParts[] = $wound['detail'];
    $woundText = implode(', ', $woundParts);

    // Building type
    $buildingTypes = $sc['building_type'] ?? [];
    $buildingOther = $sc['building_type_other'] ?? '';
    $buildingTypeMap = [
        'อาคารพาณิชย์' => 'อาคารพาณิชย์', 'บ้านเดี่ยว' => 'บ้านเดี่ยว',
        'ทาวน์เฮาส์' => 'ทาวน์เฮาส์', 'commercial' => 'อาคารพาณิชย์',
        'house' => 'บ้านเดี่ยว', 'townhouse' => 'ทาวน์เฮาส์',
    ];
    $buildingTextParts = [];
    if (is_array($buildingTypes)) {
        foreach ($buildingTypes as $bt) {
            $buildingTextParts[] = $buildingTypeMap[$bt] ?? $bt;
        }
    }
    if (!empty($buildingOther)) $buildingTextParts[] = $buildingOther;
    $buildingTypeText = implode(', ', $buildingTextParts);

    // Evidence collected summary
    $evidenceCollected = [];
    $collected = $ir['collected_evidence'] ?? [];
    if (!empty($collected['gun'])) $evidenceCollected[] = 'อาวุธปืน: ' . $collected['gun'];
    if (!empty($collected['dna'])) $evidenceCollected[] = 'DNA: ' . $collected['dna'];
    if (!empty($collected['fingerprint'])) $evidenceCollected[] = 'ลายนิ้วมือแฝง: ' . $collected['fingerprint'];
    if (!empty($collected['other_type'])) $evidenceCollected[] = $collected['other_type'];
    $evidenceCollectedText = implode(', ', $evidenceCollected);

    // Evidence found (traces / blood)
    $evidence = $ir['evidence'] ?? [];
    $evidenceFoundParts = [];
    if (!empty($evidence['blood_stain'])) $evidenceFoundParts[] = 'คราบโลหิต: ' . $evidence['blood_stain'];
    if (!empty($evidence['other'])) $evidenceFoundParts[] = $evidence['other'];
    $evidenceFoundText = implode(', ', $evidenceFoundParts);

    // Scene condition
    $sceneCondParts = [];
    if (!empty($sc['preservation'])) {
        $presText = ($sc['preservation'] === 'yes' || $sc['preservation'] === 'มี') ? 'มีการรักษาสถานที่' : 'ไม่มีการรักษาสถานที่';
        $sceneCondParts[] = $presText;
    }
    if (!empty($sc['preservation_detail'])) $sceneCondParts[] = $sc['preservation_detail'];
    $sceneCondText = implode(', ', $sceneCondParts);
    $indoor = [
        'rli_receive_date'   => $reportDt['date'],
        'rli_receive_time'   => $reportDt['time'],
        'rli_daily_ref'      => $gi['document_no'] ?? '',
        'rli_agency_type'    => $agencyType,
        'rli_agency_name'    => $agencyName,
        'rli_notify_method[]' => $gi['report_channel'] ?? [],
        'rli_from_station'   => $gi['source_station'] ?? '',
        'rli_investigator'   => trim(($investigator['firstname'] ?? '') . ' ' . ($investigator['lastname'] ?? '')),
        'rli_crime_location' => $gi['location_detail'] ?? '',
        'rli_victim_name'    => trim(($victim['firstname'] ?? '') . ' ' . ($victim['lastname'] ?? '')),
        'rli_victim_age'     => $victim['age'] ?? '',
        'rli_victim_know_date' => $victimDt['date'],
        'rli_victim_know_time' => $victimDt['time'],
        'rli_officer_know_date' => $officerDt['date'],
        'rli_officer_know_time' => $officerDt['time'],
        'rli_inspect_date'     => $inspectDt['date'],
        'rli_inspect_time'     => $inspectDt['time'],
        'rli_inspect_add_date' => $inspectAddDt['date'],
        'rli_inspect_add_time' => $inspectAddDt['time'],
        'rli_inspector_name[]' => [],
        'rli_inspector_position[]' => [],
        'rli_building_type_text' => $buildingTypeText,
        'rli_floor_count'      => '',
        'rli_mezzanine'        => '',
        'rli_rooftop'          => '',
        'rli_fence'            => ($sc['fence'] ?? '') === 'มีรั้ว' ? 'มี' : (($sc['fence'] ?? '') === 'ไม่มีรั้ว' ? 'ไม่มี' : ''),
        'rli_ext_front'        => $sc['front_adjacent'] ?? '',
        'rli_ext_left'         => $sc['left_adjacent'] ?? '',
        'rli_ext_right'        => $sc['right_adjacent'] ?? '',
        'rli_ext_back'         => $sc['back_adjacent'] ?? '',
        'rli_interior_detail'  => $sc['interior_detail'] ?? '',
        'rli_incident_area'    => $sc['incident_area_detail'] ?? '',
        'rli_area_size'        => $st['size'] ?? '',
        'rli_wall_front'       => $st['front'] ?? '',
        'rli_wall_left'        => $st['left'] ?? '',
        'rli_wall_right'       => $st['right'] ?? '',
        'rli_wall_back'        => $st['back'] ?? '',
        'rli_wall_front_window' => '', 'rli_wall_front_door' => '',
        'rli_wall_left_window'  => '', 'rli_wall_left_door'  => '',
        'rli_wall_right_window' => '', 'rli_wall_right_door' => '',
        'rli_wall_back_window'  => '', 'rli_wall_back_door'  => '',
        'rli_floor_material'   => $st['floor'] ?? '',
        'rli_ceiling'          => '',
        'rli_roof'             => $st['roof'] ?? '',
        'rli_arrange_front'    => $st['arrangement'] ?? '',
        'rli_arrange_left'     => '',
        'rli_arrange_right'    => '',
        'rli_arrange_back'     => '',
        'rli_arrange_other'    => '',
        'rli_case_behavior'    => $ir['case_behavior'] ?? '',
        'rli_scene_condition'  => $sceneCondText,
        'rli_body_found'       => $ir['body_found'] ?? '',
        'rli_body_position'    => $ir['body_location'] ?? '',
        'rli_body_condition'   => $ir['body_condition'] ?? '',
        'rli_body_clothing'    => $clothingText,
        'rli_body_wounds'      => $woundText,
        'rli_evidence_found'   => $evidenceFoundText,
        'rli_evidence_collected' => $evidenceCollectedText,
        'rli_evidence_action'  => '',
        'rli_handover_officer' => '',
        'rli_handover_to'      => '',
        'rli_handover_date'    => $inspectEndDt['date'],
        'rli_handover_time'    => $inspectEndDt['time'],
        'rli_signer_name'      => '',
        'rli_signer_position'  => '',
        'rli_sign_date'        => $inspectEndDt['date'],
    ];

    // Resolve inspector IDs → names
    $inspectorIds = $checklistData['inspectors'] ?? [];
    if (is_array($inspectorIds)) {
        foreach ($inspectorIds as $id) {
            if (isset($userMap[$id])) {
                $indoor['rli_inspector_name[]'][] = $userMap[$id]['fullname'];
                $indoor['rli_inspector_position[]'][] = $userMap[$id]['position'];
            }
        }
    }

    // Resolve handover user IDs
    $delivererId = $ho['deliverer_id'] ?? '';
    $receiverId = $ho['receiver_id'] ?? '';
    if (isset($userMap[$delivererId])) {
        $indoor['rli_handover_officer'] = $userMap[$delivererId]['fullname'];
        $indoor['rli_signer_name'] = $userMap[$delivererId]['fullname'];
        $indoor['rli_signer_position'] = $userMap[$delivererId]['position'];
    }
    if (isset($userMap[$receiverId])) {
        $indoor['rli_handover_to'] = $userMap[$receiverId]['fullname'];
    }
}

// Resolve user IDs that may still be numeric in indoor data
$resolveFields = ['rli_handover_officer', 'rli_handover_to', 'rli_signer_name'];
foreach ($resolveFields as $fk) {
    if (isset($indoor[$fk]) && is_numeric($indoor[$fk]) && isset($userMap[$indoor[$fk]])) {
        $indoor[$fk] = $userMap[$indoor[$fk]]['fullname'];
    }
}
if (isset($indoor['rli_signer_position']) && is_numeric($indoor['rli_signer_position']) && isset($userMap[$indoor['rli_signer_position']])) {
    $indoor['rli_signer_position'] = $userMap[$indoor['rli_signer_position']]['position'];
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
// แปลงเลขที่รายงาน/เลขที่เอกสารเป็นภาษาไทย
$displayOverride = getVal($indoor, 'rli_report_no_display_pdf');
if ($displayOverride === '') $displayOverride = getVal($indoor, 'rli_report_no_display_pdf_2');
if ($displayOverride === '') $displayOverride = getVal($indoor, 'rli_report_no_display_pdf_3');
if ($displayOverride === '') $displayOverride = getVal($indoor, 'rli_report_no_display_pdf_4');
if ($displayOverride !== '') $report_no = $displayOverride;
$report_no = smartThaiReportOrDoc($report_no);

// Notify method checkboxes
$notifyMethods = getVal($indoor, 'rli_notify_method[]', []);
if (!is_array($notifyMethods)) $notifyMethods = [$notifyMethods];

// ★ ข้อ 7: ให้ "วิธีการรับแจ้ง" ออกมาเป็นข้อความไทย ไม่ใช่ช่องติ๊กเปล่า
require_once __DIR__ . '/notify_method_helper.php';
$notify_method_text = notifyMethodToText(
    $notifyMethods,
    (string)getVal($indoor, 'rli_notify_method_other', '')
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

// Receive date/time
$receiveDate = getVal($indoor, 'rli_receive_date');
$receiveTime = getVal($indoor, 'rli_receive_time');
$receiveDateThai = !empty($receiveDate) ? thaiDateFull($receiveDate) : '';
$receiveTimeFormatted = $receiveTime;

// Victim / Know / Inspect dates
$victimKnowDate = thaiDateFull(getVal($indoor, 'rli_victim_know_date'));
$victimKnowTime = getVal($indoor, 'rli_victim_know_time');
$officerKnowDate = thaiDateFull(getVal($indoor, 'rli_officer_know_date'));
$officerKnowTime = getVal($indoor, 'rli_officer_know_time');
$inspectDate = thaiDateFull(getVal($indoor, 'rli_inspect_date'));
$inspectTime = getVal($indoor, 'rli_inspect_time');
$inspectAddDate = thaiDateFull(getVal($indoor, 'rli_inspect_add_date'));
$inspectAddTime = getVal($indoor, 'rli_inspect_add_time');

// Inspector rows HTML
$inspectorNames = getVal($indoor, 'rli_inspector_name[]', []);
$inspectorPositions = getVal($indoor, 'rli_inspector_position[]', []);
if (!is_array($inspectorNames)) $inspectorNames = [];
$inspector_rows_html = '';
foreach ($inspectorNames as $idx => $name) {
    $no = $idx + 1;
    $pos = $inspectorPositions[$idx] ?? '';
    $posText = !empty($pos) ? '  ตำแหน่ง ' . htmlspecialchars($pos) : '';
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">5.' . $no . '.</span><span class="fd" style="margin-left:6px;">' . htmlspecialchars($name) . $posText . '</span></div>' . "\n";
}
// Ensure at least 4 blank rows
for ($i = count($inspectorNames); $i < 4; $i++) {
    $no = $i + 1;
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">5.' . $no . '.</span><span class="fd" style="margin-left:6px;"></span><span class="fl" style="margin-left:20px;">ตำแหน่ง</span><span class="fd"></span></div>' . "\n";
}

// Victim rows
$victimStatus = '';
$allVictims = $gen['all_victims'] ?? [];
$victimRowsHtml = '';
if (!empty($allVictims)) {
    foreach ($allVictims as $v) {
        $vType = $v['type'] ?? '';
        if ($vType == 'deceased') $vType = 'ผู้เสียชีวิต';
        elseif ($vType == 'injured') $vType = 'ผู้บาดเจ็บ';
        elseif ($vType == 'missing') $vType = 'ผู้สูญหาย';
        $victimRowsHtml .= htmlspecialchars($vType) . '/' ;
    }
    $victimRowsHtml = rtrim($victimRowsHtml, '/');
} else {
    $victim = $gen['victim'] ?? [];
    $status = getVal($victim, 'status');
    if ($status == 'deceased') $victimRowsHtml = 'ผู้เสียชีวิต';
    elseif ($status == 'injured') $victimRowsHtml = 'ผู้บาดเจ็บ';
    elseif ($status == 'missing') $victimRowsHtml = 'ผู้สูญหาย';
    else $victimRowsHtml = htmlspecialchars($status);
}

// Fence
$fence = getVal($indoor, 'rli_fence');
$chk_fence_yes = renderCheckbox($fence === 'มี' || $fence === 'มีรั้ว');
$chk_fence_no = renderCheckbox($fence === 'ไม่มี' || $fence === 'ไม่มีรั้ว');
$chk_surround_fence_yes = $chk_fence_yes;
$chk_surround_fence_no = $chk_fence_no;

// Mezzanine
$mezzanine = getVal($indoor, 'rli_mezzanine');
$chk_mezzanine = renderCheckbox(!empty($mezzanine) && $mezzanine !== 'ไม่มี');
$chk_no_mezzanine = renderCheckbox(empty($mezzanine) || $mezzanine === 'ไม่มี');

// Body found
$bodyFound = getVal($indoor, 'rli_body_found');
$bodyFoundText = '';
if ($bodyFound === 'yes' || $bodyFound === 'พบศพ') $bodyFoundText = 'พบศพ';
elseif ($bodyFound === 'no' || $bodyFound === 'ไม่พบศพ') $bodyFoundText = 'ไม่พบศพ';
else $bodyFoundText = htmlspecialchars($bodyFound);

// Handover date
$handoverDate = thaiDateFull(getVal($indoor, 'rli_handover_date'));
$handoverTime = getVal($indoor, 'rli_handover_time');

// Sign date parts
$signDateParts = parseDateParts(getVal($indoor, 'rli_sign_date'));

// Building type text - ถ้ามี text สำเร็จรูปจาก report data ใช้เลย ถ้าไม่มีใช้จาก array
$buildingTypeText = getVal($indoor, 'rli_building_type_text');
if (empty($buildingTypeText)) {
    $bt = getVal($indoor, 'rli_building_type[]', []);
    if (is_array($bt)) {
        $buildingTypeText = implode(', ', $bt);
    }
    $bto = getVal($indoor, 'rli_building_type_other');
    if (!empty($bto)) $buildingTypeText .= (!empty($buildingTypeText) ? ', ' : '') . $bto;
}

// Agency short
$agencyShort = getVal($indoor, 'rlo_agency_name', $agencyName);

// Report no for filename (safe chars only)
$report_no_filename = preg_replace('/[^a-zA-Z0-9\-_]/', '', str_replace('/', '-', $rawReportNo));

// ==========================================
// 5. BUILD REPLACEMENTS
// ==========================================
$replacements = [
    '{{report_no}}' => htmlspecialchars($report_no),
    '{{report_year_short}}' => htmlspecialchars($report_year_short),
    '{{report_no_filename}}' => htmlspecialchars($report_no_filename),
    '{{total_pages}}' => '4',
    '{{agency_name}}' => htmlspecialchars($agencyShort),
    '{{agency_short}}' => htmlspecialchars($agencyShort),

    // Section 1
    '{{receive_date}}' => htmlspecialchars($receiveDateThai),
    '{{receive_time}}' => htmlspecialchars($receiveTimeFormatted),
    '{{daily_ref}}' => htmlspecialchars(getVal($indoor, 'rli_daily_ref')),
    '{{notify_method_text}}' => $notify_method_text,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_phone}}' => $chk_notify_phone,
    '{{chk_notify_radio}}' => $chk_notify_radio,
    '{{from_station}}' => htmlspecialchars(getVal($indoor, 'rli_from_station')),
    '{{investigator_name}}' => htmlspecialchars(getVal($indoor, 'rli_investigator')),

    // Section 2
    '{{victim_rows}}' => $victimRowsHtml,
    '{{victim_age}}' => htmlspecialchars(getVal($indoor, 'rli_victim_age')),

    // Section 3
    '{{victim_know_date}}' => htmlspecialchars($victimKnowDate),
    '{{victim_know_time}}' => htmlspecialchars($victimKnowTime),
    '{{officer_know_date}}' => htmlspecialchars($officerKnowDate),
    '{{officer_know_time}}' => htmlspecialchars($officerKnowTime),

    // Section 4
    '{{inspect_date}}' => htmlspecialchars($inspectDate),
    '{{inspect_time}}' => htmlspecialchars($inspectTime),
    '{{inspect_add_date}}' => htmlspecialchars($inspectAddDate),
    '{{inspect_add_time}}' => htmlspecialchars($inspectAddTime),

    // Section 5
    '{{inspector_rows}}' => $inspector_rows_html,

    // Section 6.1 ลักษณะภายนอก
    '{{building_type_text}}' => htmlspecialchars($buildingTypeText),
    '{{floor_count}}' => htmlspecialchars(getVal($indoor, 'rli_floor_count')),
    '{{chk_mezzanine}}' => $chk_mezzanine,
    '{{chk_no_mezzanine}}' => $chk_no_mezzanine,
    '{{chk_fence_yes}}' => $chk_fence_yes,
    '{{chk_fence_no}}' => $chk_fence_no,
    '{{chk_surround_fence_yes}}' => $chk_surround_fence_yes,
    '{{chk_surround_fence_no}}' => $chk_surround_fence_no,
    '{{ext_front}}' => htmlspecialchars(getVal($indoor, 'rli_ext_front')),
    '{{ext_left}}' => htmlspecialchars(getVal($indoor, 'rli_ext_left')),
    '{{ext_right}}' => htmlspecialchars(getVal($indoor, 'rli_ext_right')),
    '{{ext_back}}' => htmlspecialchars(getVal($indoor, 'rli_ext_back')),

    // Section 6.2 ลักษณะภายใน
    '{{interior_detail}}' => htmlspecialchars(getVal($indoor, 'rli_interior_detail')),

    // Section 6.3 บริเวณเกิดเหตุ
    '{{incident_area}}' => htmlspecialchars(getVal($indoor, 'rli_incident_area')),
    '{{area_size}}' => htmlspecialchars(getVal($indoor, 'rli_area_size')),

    // โครงสร้าง
    '{{wall_front}}' => htmlspecialchars(getVal($indoor, 'rli_wall_front')),
    '{{wall_front_window}}' => htmlspecialchars(getVal($indoor, 'rli_wall_front_window')),
    '{{wall_front_door}}' => htmlspecialchars(getVal($indoor, 'rli_wall_front_door')),
    '{{wall_left}}' => htmlspecialchars(getVal($indoor, 'rli_wall_left')),
    '{{wall_left_window}}' => htmlspecialchars(getVal($indoor, 'rli_wall_left_window')),
    '{{wall_left_door}}' => htmlspecialchars(getVal($indoor, 'rli_wall_left_door')),
    '{{wall_right}}' => htmlspecialchars(getVal($indoor, 'rli_wall_right')),
    '{{wall_right_window}}' => htmlspecialchars(getVal($indoor, 'rli_wall_right_window')),
    '{{wall_right_door}}' => htmlspecialchars(getVal($indoor, 'rli_wall_right_door')),
    '{{wall_back}}' => htmlspecialchars(getVal($indoor, 'rli_wall_back')),
    '{{wall_back_window}}' => htmlspecialchars(getVal($indoor, 'rli_wall_back_window')),
    '{{wall_back_door}}' => htmlspecialchars(getVal($indoor, 'rli_wall_back_door')),
    '{{floor_material}}' => htmlspecialchars(getVal($indoor, 'rli_floor_material')),
    '{{ceiling}}' => htmlspecialchars(getVal($indoor, 'rli_ceiling')),
    '{{roof}}' => htmlspecialchars(getVal($indoor, 'rli_roof')),

    // การจัดวางสิ่งของ
    '{{arrange_front}}' => htmlspecialchars(getVal($indoor, 'rli_arrange_front')),
    '{{arrange_left}}' => htmlspecialchars(getVal($indoor, 'rli_arrange_left')),
    '{{arrange_right}}' => htmlspecialchars(getVal($indoor, 'rli_arrange_right')),
    '{{arrange_back}}' => htmlspecialchars(getVal($indoor, 'rli_arrange_back')),
    '{{arrange_other}}' => htmlspecialchars(getVal($indoor, 'rli_arrange_other')),

    // Section 7
    '{{case_behavior}}' => htmlspecialchars(getVal($indoor, 'rli_case_behavior')),
    '{{scene_condition}}' => htmlspecialchars(getVal($indoor, 'rli_scene_condition')),
    '{{body_found_text}}' => $bodyFoundText,
    '{{body_position}}' => htmlspecialchars(getVal($indoor, 'rli_body_position')),
    '{{body_condition}}' => htmlspecialchars(getVal($indoor, 'rli_body_condition')),
    '{{body_clothing}}' => htmlspecialchars(getVal($indoor, 'rli_body_clothing')),
    '{{body_wounds}}' => htmlspecialchars(getVal($indoor, 'rli_body_wounds')),
    '{{evidence_found}}' => htmlspecialchars(getVal($indoor, 'rli_evidence_found')),
    '{{evidence_collected}}' => htmlspecialchars(getVal($indoor, 'rli_evidence_collected')),
    '{{evidence_action}}' => htmlspecialchars(getVal($indoor, 'rli_evidence_action')),

    // Section 7.6 การส่งมอบ
    '{{handover_officer}}' => htmlspecialchars(getVal($indoor, 'rli_handover_officer')),
    '{{handover_to}}' => htmlspecialchars(getVal($indoor, 'rli_handover_to')),
    '{{handover_date}}' => htmlspecialchars($handoverDate),
    '{{handover_time}}' => htmlspecialchars($handoverTime),

    // Signature
    '{{signer_signature}}' => '',
    '{{signer_name}}' => htmlspecialchars(getVal($indoor, 'rli_signer_name')),
    '{{signer_position}}' => htmlspecialchars(getVal($indoor, 'rli_signer_position')),
    '{{sign_date_day}}' => htmlspecialchars($signDateParts['day']),
    '{{sign_date_month}}' => htmlspecialchars($signDateParts['month']),
    '{{sign_date_year}}' => htmlspecialchars($signDateParts['year']),
];

// ==========================================
// 6. READ TEMPLATE AND REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_life_report_indoor_preview.html');
if ($htmlTemplate === false) {
    die("Error: ไม่สามารถอ่านไฟล์ template ได้");
}

$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// ==========================================
// EXPORT สำหรับ DOCX (ชีวิตในอาคาร)
// ==========================================
if (defined('WORD_DOCX_MODE') && WORD_DOCX_MODE) {
    $GLOBALS['life_indoor_report_export'] = [
        'replacements' => $replacements,
        'indoor' => $indoor ?? [],
    ];
    return;
}

// ==========================================
// 7. OUTPUT HTML
// ==========================================
if (!defined('WORD_REPORT_CAPTURE')) {
    header('Content-Type: text/html; charset=utf-8');
}
echo $htmlContent;