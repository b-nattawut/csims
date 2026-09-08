<?php
/**
 * gen_pdf_life_report_outdoor_html.php
 * Generate PDF F-CS-14 รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต (นอกอาคาร)
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
                       CONCAT(IFNULL(t3.rank_name,''),' ',t1.first_name,' ',t1.last_name) AS fullname,
                       IFNULL(t4.position_name,'') AS position
                FROM user_profile t1
                LEFT JOIN user_rank t3 ON t1.rank_id = t3.rank_id
                LEFT JOIN user_position t4 ON t1.position_id = t4.position_id";
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
$outdoor = [];

if ($reportData && isset($reportData['outdoor']) && is_array($reportData['outdoor'])) {
    $outdoor = $reportData['outdoor'];
} elseif ($checklistData) {
    // Map from checklist data
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

    // Scene characteristics - outdoor
    $sceneCharParts = [];
    $outdoorTypes = $sc['outdoor_type'] ?? [];
    if (is_array($outdoorTypes) && !empty($outdoorTypes)) {
        $sceneCharParts[] = 'ประเภท: ' . implode(', ', $outdoorTypes);
    }
    if (!empty($sc['outdoor_type_other'])) $sceneCharParts[] = $sc['outdoor_type_other'];
    if (!empty($sc['outdoor_entrance_condition'])) $sceneCharParts[] = 'สภาพทางเข้า: ' . $sc['outdoor_entrance_condition'];
    if (!empty($sc['outdoor_front_adjacent'])) $sceneCharParts[] = 'ด้านหน้าติด: ' . $sc['outdoor_front_adjacent'];
    if (!empty($sc['outdoor_left_adjacent'])) $sceneCharParts[] = 'ด้านซ้ายติด: ' . $sc['outdoor_left_adjacent'];
    if (!empty($sc['outdoor_right_adjacent'])) $sceneCharParts[] = 'ด้านขวาติด: ' . $sc['outdoor_right_adjacent'];
    if (!empty($sc['outdoor_back_adjacent'])) $sceneCharParts[] = 'ด้านหลังติด: ' . $sc['outdoor_back_adjacent'];
    if (!empty($sc['outdoor_incident_area_detail'])) $sceneCharParts[] = $sc['outdoor_incident_area_detail'];

    // Preservation
    if (!empty($sc['preservation'])) {
        $presText = ($sc['preservation'] === 'yes' || $sc['preservation'] === 'มี') ? 'มีการรักษาสถานที่' : 'ไม่มีการรักษาสถานที่';
        $sceneCharParts[] = $presText;
    }
    if (!empty($sc['preservation_detail'])) $sceneCharParts[] = $sc['preservation_detail'];

    // Lighting
    $lighting = $sc['lighting'] ?? [];
    if (is_array($lighting) && !empty($lighting)) $sceneCharParts[] = 'แสงสว่าง: ' . implode(', ', $lighting);

    // Temperature
    $temperature = $sc['temperature'] ?? [];
    if (is_array($temperature) && !empty($temperature)) $sceneCharParts[] = 'อุณหภูมิ: ' . implode(', ', $temperature);

    // Smell
    if (!empty($sc['smell'])) {
        $smellText = ($sc['smell'] === 'yes' || $sc['smell'] === 'มี') ? 'มีกลิ่น' : 'ไม่มีกลิ่น';
        $sceneCharParts[] = $smellText;
    }

    $sceneCharText = implode(', ', $sceneCharParts);

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
        $presText2 = ($sc['preservation'] === 'yes' || $sc['preservation'] === 'มี') ? 'มีการรักษาสถานที่' : 'ไม่มีการรักษาสถานที่';
        $sceneCondParts[] = $presText2;
    }
    if (!empty($sc['preservation_detail'])) $sceneCondParts[] = $sc['preservation_detail'];
    $sceneCondText = implode(', ', $sceneCondParts);

    // Victim name
    $victimName = trim(($victim['firstname'] ?? '') . ' ' . ($victim['lastname'] ?? ''));

    $outdoor = [
        'rlo_receive_date'   => $reportDt['date'],
        'rlo_receive_time'   => $reportDt['time'],
        'rlo_daily_ref'      => $gi['document_no'] ?? '',
        'rlo_agency_type'    => $agencyType,
        'rlo_agency_name'    => $agencyName,
        'rlo_notify_method[]' => $gi['report_channel'] ?? [],
        'rlo_from_station'   => $gi['source_station'] ?? '',
        'rlo_investigator'   => trim(($investigator['firstname'] ?? '') . ' ' . ($investigator['lastname'] ?? '')),
        'rlo_crime_location' => $gi['location_detail'] ?? '',
        'rlo_victim_name'    => $victimName,
        'rlo_victim_age'     => $victim['age'] ?? '',
        'rlo_victim_know_date' => $victimDt['date'],
        'rlo_victim_know_time' => $victimDt['time'],
        'rlo_officer_know_date' => $officerDt['date'],
        'rlo_officer_know_time' => $officerDt['time'],
        'rlo_inspect_date'     => $inspectDt['date'],
        'rlo_inspect_time'     => $inspectDt['time'],
        'rlo_inspect_add_date' => $inspectAddDt['date'],
        'rlo_inspect_add_time' => $inspectAddDt['time'],
        'rlo_inspector_name[]' => [],
        'rlo_inspector_position[]' => [],
        'rlo_scene_characteristics' => $sceneCharText,
        'rlo_case_behavior'    => $ir['case_behavior'] ?? '',
        'rlo_scene_condition'  => $sceneCondText,
        'rlo_body_found'       => $ir['body_found'] ?? '',
        'rlo_body_position'    => $ir['body_location'] ?? '',
        'rlo_body_condition'   => $ir['body_condition'] ?? '',
        'rlo_body_clothing'    => $clothingText,
        'rlo_body_wounds'      => $woundText,
        'rlo_evidence_found'   => $evidenceFoundText,
        'rlo_evidence_collected' => $evidenceCollectedText,
        'rlo_evidence_action'  => '',
        'rlo_handover_officer' => '',
        'rlo_handover_to'      => '',
        'rlo_handover_date'    => $inspectEndDt['date'],
        'rlo_handover_time'    => $inspectEndDt['time'],
        'rlo_signer_name'      => '',
        'rlo_signer_position'  => '',
        'rlo_sign_date'        => $inspectEndDt['date'],
    ];

    // Resolve inspector IDs → names
    $inspectorIds = $checklistData['inspectors'] ?? [];
    if (is_array($inspectorIds)) {
        foreach ($inspectorIds as $id) {
            if (isset($userMap[$id])) {
                $outdoor['rlo_inspector_name[]'][] = $userMap[$id]['fullname'];
                $outdoor['rlo_inspector_position[]'][] = $userMap[$id]['position'];
            }
        }
    }

    // Resolve handover user IDs
    $delivererId = $ho['deliverer_id'] ?? '';
    $receiverId = $ho['receiver_id'] ?? '';
    if (isset($userMap[$delivererId])) {
        $outdoor['rlo_handover_officer'] = $userMap[$delivererId]['fullname'];
        $outdoor['rlo_signer_name'] = $userMap[$delivererId]['fullname'];
        $outdoor['rlo_signer_position'] = $userMap[$delivererId]['position'];
    }
    if (isset($userMap[$receiverId])) {
        $outdoor['rlo_handover_to'] = $userMap[$receiverId]['fullname'];
    }
}

// Resolve user IDs that may still be numeric in outdoor data
$resolveFields = ['rlo_handover_officer', 'rlo_handover_to', 'rlo_signer_name'];
foreach ($resolveFields as $fk) {
    if (isset($outdoor[$fk]) && is_numeric($outdoor[$fk]) && isset($userMap[$outdoor[$fk]])) {
        $outdoor[$fk] = $userMap[$outdoor[$fk]]['fullname'];
    }
}
if (isset($outdoor['rlo_signer_position']) && is_numeric($outdoor['rlo_signer_position']) && isset($userMap[$outdoor['rlo_signer_position']])) {
    $outdoor['rlo_signer_position'] = $userMap[$outdoor['rlo_signer_position']]['position'];
}

// Resolve inspector IDs → names if names are missing but IDs exist
$inspectorIdsFromReport = $outdoor['rlo_inspector_id[]'] ?? [];
$inspectorNamesFromReport = $outdoor['rlo_inspector_name[]'] ?? [];
if (is_array($inspectorIdsFromReport) && !empty($inspectorIdsFromReport) && empty($inspectorNamesFromReport)) {
    $outdoor['rlo_inspector_name[]'] = [];
    $outdoor['rlo_inspector_position[]'] = [];
    foreach ($inspectorIdsFromReport as $uid) {
        if (isset($userMap[$uid])) {
            $outdoor['rlo_inspector_name[]'][] = $userMap[$uid]['fullname'];
            $outdoor['rlo_inspector_position[]'][] = $userMap[$uid]['position'];
        } else {
            $outdoor['rlo_inspector_name[]'][] = '';
            $outdoor['rlo_inspector_position[]'][] = '';
        }
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
// แปลงเลขที่รายงาน/เลขที่เอกสารเป็นภาษาไทย
$displayOverride = getVal($outdoor, 'rlo_report_no_display_pdf');
if ($displayOverride === '') $displayOverride = getVal($outdoor, 'rlo_report_no_display_pdf_2');
if ($displayOverride !== '') $report_no = $displayOverride;
$report_no = smartThaiReportOrDoc($report_no);

// Notify method checkboxes
$notifyMethods = getVal($outdoor, 'rlo_notify_method[]', []);
if (!is_array($notifyMethods)) $notifyMethods = [$notifyMethods];

// ★ ข้อ 7: ให้ "วิธีการรับแจ้ง" ออกมาเป็นข้อความไทย ไม่ใช่ช่องติ๊กเปล่า
require_once __DIR__ . '/notify_method_helper.php';
$notify_method_text = notifyMethodToText(
    $notifyMethods,
    (string)getVal($outdoor, 'rlo_notify_method_other', '')
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
    in_array('วิทยุ', $notifyMethods) || in_array('วิทยุสื่อสาร', $notifyMethods) ||
    in_array('ทางวิทยุสื่อสาร', $notifyMethods) || in_array('radio', $notifyMethods)
);

// Receive date/time
$receiveDate = getVal($outdoor, 'rlo_receive_date');
$receiveTime = getVal($outdoor, 'rlo_receive_time');
$receiveDateThai = !empty($receiveDate) ? thaiDateFull($receiveDate) : '';
$receiveTimeFormatted = $receiveTime;

// Victim / Know / Inspect dates
$victimKnowDate = thaiDateFull(getVal($outdoor, 'rlo_victim_know_date'));
$victimKnowTime = getVal($outdoor, 'rlo_victim_know_time');
$officerKnowDate = thaiDateFull(getVal($outdoor, 'rlo_officer_know_date'));
$officerKnowTime = getVal($outdoor, 'rlo_officer_know_time');
$inspectDate = thaiDateFull(getVal($outdoor, 'rlo_inspect_date'));
$inspectTime = getVal($outdoor, 'rlo_inspect_time');
$inspectAddDate = thaiDateFull(getVal($outdoor, 'rlo_inspect_add_date'));
$inspectAddTime = getVal($outdoor, 'rlo_inspect_add_time');

// Inspector rows HTML
$inspectorNames = getVal($outdoor, 'rlo_inspector_name[]', []);
$inspectorPositions = getVal($outdoor, 'rlo_inspector_position[]', []);
if (!is_array($inspectorNames)) $inspectorNames = [];
$inspector_rows_html = '';
$thaiNums = ['๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙', '๑๐'];
foreach ($inspectorNames as $idx => $name) {
    $no = $thaiNums[$idx] ?? ($idx + 1);
    $pos = $inspectorPositions[$idx] ?? '';
    $posText = !empty($pos) ? '  ตำแหน่ง ' . htmlspecialchars($pos) : '';
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">๕.' . $no . '</span><span class="fd" style="margin-left:6px;">' . htmlspecialchars($name) . $posText . '</span></div>' . "\n";
}
// Ensure at least 4 blank rows
for ($i = count($inspectorNames); $i < 4; $i++) {
    $no = $thaiNums[$i] ?? ($i + 1);
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">๕.' . $no . '</span><span class="fd" style="margin-left:6px;"></span><span class="fl" style="margin-left:20px;">ตำแหน่ง</span><span class="fd"></span></div>' . "\n";
}

// Victim name
$victimName = getVal($outdoor, 'rlo_victim_name');
if (empty($victimName)) {
    // Try from checklist all_victims
    $allVictims = $gen['all_victims'] ?? [];
    if (!empty($allVictims)) {
        $vParts = [];
        foreach ($allVictims as $v) {
            $vType = $v['type'] ?? '';
            if ($vType == 'deceased') $vType = 'ผู้เสียชีวิต';
            elseif ($vType == 'injured') $vType = 'ผู้บาดเจ็บ';
            elseif ($vType == 'missing') $vType = 'ผู้สูญหาย';
            $vParts[] = $vType . ' ' . ($v['name'] ?? '');
        }
        $victimName = implode(', ', $vParts);
    }
}

// Body found text
$bodyFound = getVal($outdoor, 'rlo_body_found');
$bodyFoundText = '';
if ($bodyFound === 'yes' || $bodyFound === 'พบศพ') $bodyFoundText = 'พบศพ';
elseif ($bodyFound === 'no' || $bodyFound === 'ไม่พบศพ') $bodyFoundText = 'ไม่พบศพ';
else $bodyFoundText = htmlspecialchars($bodyFound);

// Handover date
$handoverDate = thaiDateFull(getVal($outdoor, 'rlo_handover_date'));
$handoverTime = getVal($outdoor, 'rlo_handover_time');

// Sign date parts
$signDateParts = parseDateParts(getVal($outdoor, 'rlo_sign_date'));

// Agency short
$agencyShort = getVal($outdoor, 'rli_agency_name', $agencyName);

// Report no for filename
$report_no_filename = preg_replace('/[^a-zA-Z0-9\-_]/', '', str_replace('/', '-', $rawReportNo));

// ==========================================
// 5. BUILD REPLACEMENTS
// ==========================================
$replacements = [
    '{{report_no}}' => htmlspecialchars($report_no),
    '{{report_year_short}}' => htmlspecialchars($report_year_short),
    '{{report_no_filename}}' => htmlspecialchars($report_no_filename),
    '{{agency_name}}' => htmlspecialchars($agencyShort),
    '{{agency_name_short}}' => htmlspecialchars($agencyShort),

    // Section 1
    '{{receive_date}}' => htmlspecialchars($receiveDateThai),
    '{{receive_time}}' => htmlspecialchars($receiveTimeFormatted),
    '{{daily_ref}}' => htmlspecialchars(getVal($outdoor, 'rlo_daily_ref')),
    '{{notify_method_text}}' => $notify_method_text,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_phone}}' => $chk_notify_phone,
    '{{chk_notify_radio}}' => $chk_notify_radio,
    '{{from_station}}' => htmlspecialchars(getVal($outdoor, 'rlo_from_station')),
    '{{investigator_name}}' => htmlspecialchars(getVal($outdoor, 'rlo_investigator')),

    // Section 2
    '{{victim_name}}' => htmlspecialchars($victimName),
    '{{victim_age}}' => htmlspecialchars(getVal($outdoor, 'rlo_victim_age')),

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

    // Section 6
    '{{scene_characteristics}}' => htmlspecialchars(getVal($outdoor, 'rlo_scene_characteristics')),

    // Section 7
    '{{case_behavior}}' => htmlspecialchars(getVal($outdoor, 'rlo_case_behavior')),
    '{{scene_condition}}' => htmlspecialchars(getVal($outdoor, 'rlo_scene_condition')),
    '{{body_found}}' => $bodyFoundText,
    '{{body_position}}' => htmlspecialchars(getVal($outdoor, 'rlo_body_position')),
    '{{body_condition}}' => htmlspecialchars(getVal($outdoor, 'rlo_body_condition')),
    '{{body_clothing}}' => htmlspecialchars(getVal($outdoor, 'rlo_body_clothing')),
    '{{body_wounds}}' => htmlspecialchars(getVal($outdoor, 'rlo_body_wounds')),
    '{{evidence_found}}' => htmlspecialchars(getVal($outdoor, 'rlo_evidence_found')),
    '{{evidence_collected}}' => htmlspecialchars(getVal($outdoor, 'rlo_evidence_collected')),
    '{{evidence_action}}' => htmlspecialchars(getVal($outdoor, 'rlo_evidence_action')),

    // Section 7.6
    '{{handover_officer}}' => htmlspecialchars(getVal($outdoor, 'rlo_handover_officer')),
    '{{handover_to}}' => htmlspecialchars(getVal($outdoor, 'rlo_handover_to')),
    '{{handover_date}}' => htmlspecialchars($handoverDate),
    '{{handover_time}}' => htmlspecialchars($handoverTime),

    // Signature
    '{{signer_name}}' => htmlspecialchars(getVal($outdoor, 'rlo_signer_name')),
    '{{signer_position}}' => htmlspecialchars(getVal($outdoor, 'rlo_signer_position')),
    '{{sign_date_day}}' => htmlspecialchars($signDateParts['day']),
    '{{sign_date_month}}' => htmlspecialchars($signDateParts['month']),
    '{{sign_date_year}}' => htmlspecialchars($signDateParts['year']),
];

// ==========================================
// 6. READ TEMPLATE AND REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_life_report_outdoor_preview.html');
if ($htmlTemplate === false) {
    die("Error: ไม่สามารถอ่านไฟล์ template ได้");
}

$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// ==========================================
// EXPORT สำหรับ DOCX (ชีวิตนอกอาคาร)
// ==========================================
if (defined('WORD_DOCX_MODE') && WORD_DOCX_MODE) {
    $GLOBALS['life_outdoor_report_export'] = [
        'replacements' => $replacements,
        'outdoor' => $outdoor ?? [],
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
