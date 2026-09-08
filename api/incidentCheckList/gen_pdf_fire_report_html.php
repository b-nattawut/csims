<?php
/**
 * gen_pdf_fire_report_html.php
 * Generate PDF รายงานการตรวจสถานที่เกิดเหตุคดีเพลิงไหม้
 *
 * รับ parameter: incident_id (GET)
 * ดึงข้อมูลจาก: incident_checklist_transaction.incident_report_data (JSON key: fire)
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
// PREPARE USER MAP (ID => Fullname + Position)
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
$fire = [];

if ($reportData && isset($reportData['fire']) && is_array($reportData['fire'])) {
    $fire = $reportData['fire'];
} elseif ($checklistData) {
    // Map from fire checklist data
    $gi = $checklistData['general_info'] ?? [];
    $si = $checklistData['scene_info'] ?? [];
    $di = $checklistData['datetime_info'] ?? [];
    $sc = $checklistData['scene_characteristics'] ?? [];
    $cbData = $checklistData['case_behavior'] ?? [];
    $dm = $checklistData['damage'] ?? [];
    $sm = $checklistData['summary'] ?? [];
    $op = $checklistData['opinion'] ?? [];
    $ho = $checklistData['handover'] ?? [];
    $ext = $sc['exterior'] ?? [];
    $int = $sc['interior'] ?? [];
    $ia = $sc['incident_area'] ?? [];
    $struct = $ia['structure'] ?? [];
    $objects = $ia['objects'] ?? [];
    $dmStruct = $dm['structure'] ?? [];
    $dmObj = $dm['objects'] ?? [];

    // Fence
    $fenceVal = '';
    $fence = $ext['fence'] ?? '';
    if ($fence === 'has_fence' || $fence === 'มีรั้ว' || $fence === 'มี') $fenceVal = 'มี';
    elseif ($fence === 'no_fence' || $fence === 'ไม่มีรั้ว' || $fence === 'ไม่มี') $fenceVal = 'ไม่มี';

    // Insurance
    $insurance = '';
    $ins = $cbData['insurance'] ?? '';
    if ($ins === 'has_insurance' || $ins === 'มี') $insurance = 'มี';
    elseif ($ins === 'no_insurance' || $ins === 'ไม่มี') $insurance = 'ไม่มี';

    // Extinguish
    $extinguish = '';
    $extVal = $cbData['extinguish'] ?? '';
    if ($extVal === 'yes' || $extVal === 'ดับแล้ว') $extinguish = 'ดับแล้ว';
    elseif ($extVal === 'no' || $extVal === 'ยังไม่ดับ') $extinguish = 'ยังไม่ดับ';

    // Adjacent damage
    $adjacentDamage = '';
    $adj = $dm['adjacent_damage'] ?? '';
    if ($adj === 'found' || $adj === 'พบ') $adjacentDamage = 'พบ';
    elseif ($adj === 'not_found' || $adj === 'ไม่พบ') $adjacentDamage = 'ไม่พบ';

    // Cause type
    $causeType = '';
    $ct = $op['cause_type'] ?? '';
    if ($ct === 'believed' || $ct === 'เชื่อว่า') $causeType = 'เชื่อว่า';
    elseif ($ct === 'unknown' || $ct === 'ไม่ทราบสาเหตุ') $causeType = 'ไม่ทราบสาเหตุ';

    // Notify methods
    $notifyMethods = $gi['notify_method'] ?? [];
    if (!is_array($notifyMethods)) $notifyMethods = [];
    $methodMap = ['ทางโทรศัพท์' => 'โทรศัพท์', 'ทางวิทยุสื่อสาร' => 'วิทยุสื่อสาร', 'ทางหนังสือ' => 'หนังสือ'];
    $mappedMethods = [];
    foreach ($notifyMethods as $m) {
        $mappedMethods[] = $methodMap[$m] ?? $m;
    }

    $fire = [
        'rf_receive_date' => $gi['report_date'] ?? '',
        'rf_receive_time' => $gi['report_time'] ?? '',
        'rf_daily_ref' => $gi['document_no'] ?? '',
        'rf_agency_type' => $agencyType,
        'rf_agency_name' => $agencyName,
        'rf_notify_method[]' => $mappedMethods,
        'rf_from_station' => $gi['source_station'] ?? '',
        'rf_investigator' => $gi['investigator']['name'] ?? '',
        'rf_crime_location' => $si['incident_location'] ?? '',
        'rf_victim_know_date' => $di['victim_known_date'] ?? '',
        'rf_victim_know_time' => $di['victim_known_time'] ?? '',
        'rf_officer_know_date' => $di['investigator_known_date'] ?? '',
        'rf_officer_know_time' => $di['investigator_known_time'] ?? '',
        'rf_inspect_date' => $di['inspection_date'] ?? '',
        'rf_inspect_time' => $di['inspection_time'] ?? '',
        'rf_inspect_add_date' => $di['inspection_additional_date'] ?? '',
        'rf_inspect_add_time' => $di['inspection_additional_time'] ?? '',
        'rf_inspector_name[]' => [],
        'rf_inspector_position[]' => [],
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
        'rf_case_behavior' => $cbData['detail'] ?? '',
        'rf_insurance' => $insurance,
        'rf_burn_time' => $cbData['burn_time'] ?? '',
        'rf_extinguish' => $extinguish,
        'rf_extinguish_detail' => $cbData['extinguish_detail'] ?? '',
        'rf_damage_condition' => $cbData['damage_condition'] ?? '',
        'rf_spread_detail' => $cbData['spread_detail'] ?? '',
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
        'rf_origin_area' => $sm['origin_area'] ?? '',
        'rf_fuel_source' => $sm['fuel_source'] ?? '',
        'rf_heat_source' => $sm['heat_source'] ?? '',
        'rf_summary_other' => $sm['other'] ?? '',
        'rf_opinion_first_area' => $op['first_area'] ?? '',
        'rf_cause_type' => $causeType,
        'rf_cause_believed_detail' => $op['cause_believed_detail'] ?? '',
        'rf_cause_unknown_detail' => $op['cause_unknown_detail'] ?? '',
        'rf_handover_officer' => '',
        'rf_handover_to' => '',
        'rf_handover_date' => $ho['inspection_end_date'] ?? '',
        'rf_handover_time' => $ho['inspection_end_time'] ?? '',
        'rf_signer_name' => '',
        'rf_signer_position' => '',
        'rf_sign_date' => $ho['inspection_end_date'] ?? '',
    ];

    // Resolve inspector IDs → names
    $inspectorIds = $checklistData['inspectors'] ?? [];
    if (is_array($inspectorIds)) {
        foreach ($inspectorIds as $insp) {
            $id = is_array($insp) ? ($insp['id'] ?? '') : $insp;
            if (isset($userMap[$id])) {
                $fire['rf_inspector_name[]'][] = $userMap[$id]['fullname'];
                $fire['rf_inspector_position[]'][] = $userMap[$id]['position'];
            }
        }
    }

    // Resolve handover user IDs
    $senderId = $ho['sender_name'] ?? '';
    $receiverId = $ho['receiver_name'] ?? '';
    if (isset($userMap[$senderId])) {
        $fire['rf_handover_officer'] = $userMap[$senderId]['fullname'];
        $fire['rf_signer_name'] = $userMap[$senderId]['fullname'];
        $fire['rf_signer_position'] = $userMap[$senderId]['position'];
    }
    if (isset($userMap[$receiverId])) {
        $fire['rf_handover_to'] = $userMap[$receiverId]['fullname'];
    }
}

// Resolve user IDs that may still be numeric in fire data
$resolveFields = ['rf_handover_officer', 'rf_handover_to', 'rf_signer_name'];
foreach ($resolveFields as $fk) {
    if (isset($fire[$fk]) && is_numeric($fire[$fk]) && isset($userMap[$fire[$fk]])) {
        $fire[$fk] = $userMap[$fire[$fk]]['fullname'];
    }
}
if (isset($fire['rf_signer_position']) && is_numeric($fire['rf_signer_position']) && isset($userMap[$fire['rf_signer_position']])) {
    $fire['rf_signer_position'] = $userMap[$fire['rf_signer_position']]['position'];
}

// ==========================================
// 4. PREPARE TEMPLATE VARIABLES
// ==========================================

// Report No & Year
$gen = $checklistData['general_info'] ?? [];
$rawReportNo = getVal($gen, 'report_no', getVal($gen, 'document_no'));

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
$displayOverride = getVal($fire, 'rf_report_no_display_pdf');
if ($displayOverride === '') $displayOverride = getVal($fire, 'rf_report_no_display_pdf_2');
if ($displayOverride === '') $displayOverride = getVal($fire, 'rf_report_no_display_pdf_3');
if ($displayOverride !== '') $report_no = $displayOverride;
$report_no = smartThaiReportOrDoc($report_no);

// Notify method checkboxes
$notifyMethods = getVal($fire, 'rf_notify_method[]', []);
if (!is_array($notifyMethods)) $notifyMethods = [$notifyMethods];

// ★ ข้อ 7: ให้ "วิธีการรับแจ้ง" ออกมาเป็นข้อความไทย ไม่ใช่ช่องติ๊กเปล่า
require_once __DIR__ . '/notify_method_helper.php';
$notify_method_text = notifyMethodToText(
    $notifyMethods,
    (string)getVal($fire, 'rf_notify_method_other', '')
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
$receiveDateThai = thaiDateFull(getVal($fire, 'rf_receive_date'));
$receiveTime = getVal($fire, 'rf_receive_time');
$victimKnowDate = thaiDateFull(getVal($fire, 'rf_victim_know_date'));
$victimKnowTime = getVal($fire, 'rf_victim_know_time');
$officerKnowDate = thaiDateFull(getVal($fire, 'rf_officer_know_date'));
$officerKnowTime = getVal($fire, 'rf_officer_know_time');
$inspectDate = thaiDateFull(getVal($fire, 'rf_inspect_date'));
$inspectTime = getVal($fire, 'rf_inspect_time');
$inspectAddDate = thaiDateFull(getVal($fire, 'rf_inspect_add_date'));
$inspectAddTime = getVal($fire, 'rf_inspect_add_time');
$handoverDate = thaiDateFull(getVal($fire, 'rf_handover_date'));
$handoverTime = getVal($fire, 'rf_handover_time');
$signDateParts = parseDateParts(getVal($fire, 'rf_sign_date'));
$signDateFull = thaiDateFull(getVal($fire, 'rf_sign_date'));

// Inspector rows HTML
$inspectorNames = getVal($fire, 'rf_inspector_name[]', []);
$inspectorPositions = getVal($fire, 'rf_inspector_position[]', []);
if (!is_array($inspectorNames)) $inspectorNames = [];
$inspector_rows_html = '';
foreach ($inspectorNames as $idx => $name) {
    $no = $idx + 1;
    $pos = $inspectorPositions[$idx] ?? '';
    $posText = !empty($pos) ? '  ตำแหน่ง ' . htmlspecialchars($pos) : '';
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">5.' . $no . '.</span><span class="fd" style="margin-left:6px;">' . htmlspecialchars($name) . $posText . '</span></div>' . "\n";
}
for ($i = count($inspectorNames); $i < 4; $i++) {
    $no = $i + 1;
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">5.' . $no . '.</span><span class="fd" style="margin-left:6px;"></span><span class="fl" style="margin-left:20px;">ตำแหน่ง</span><span class="fd"></span></div>' . "\n";
}

// Fence checkbox
$fence = getVal($fire, 'rf_fence');
$chk_fence_yes = renderCheckbox($fence === 'มี' || $fence === 'มีรั้ว');
$chk_fence_no = renderCheckbox($fence === 'ไม่มี' || $fence === 'ไม่มีรั้ว');

// Insurance checkbox
$insurance = getVal($fire, 'rf_insurance');
$chk_insurance_yes = renderCheckbox($insurance === 'มี');
$chk_insurance_no = renderCheckbox($insurance === 'ไม่มี');

// Extinguish checkbox
$extinguish = getVal($fire, 'rf_extinguish');
$chk_extinguish_yes = renderCheckbox($extinguish === 'ดับแล้ว');
$chk_extinguish_no = renderCheckbox($extinguish === 'ยังไม่ดับ');

// Adjacent damage checkbox
$adjacentDamage = getVal($fire, 'rf_adjacent_damage');
$chk_adjacent_found = renderCheckbox($adjacentDamage === 'พบ');
$chk_adjacent_notfound = renderCheckbox($adjacentDamage === 'ไม่พบ');

// Cause type checkbox
$causeType = getVal($fire, 'rf_cause_type');
$chk_cause_believed = renderCheckbox($causeType === 'เชื่อว่า');
$chk_cause_unknown = renderCheckbox($causeType === 'ไม่ทราบสาเหตุ');

// Agency
$agencyShort = getVal($fire, 'rf_agency_name', $agencyName);

// Report no for filename
$report_no_filename = preg_replace('/[^a-zA-Z0-9\-_]/', '', str_replace('/', '-', $rawReportNo));

// ==========================================
// 5. BUILD REPLACEMENTS
// ==========================================
$replacements = [
    '{{report_no}}' => htmlspecialchars($report_no),
    '{{report_year_short}}' => htmlspecialchars($report_year_short),
    '{{report_no_filename}}' => htmlspecialchars($report_no_filename),
    '{{total_pages}}' => '3',
    '{{agency_name}}' => htmlspecialchars($agencyShort),

    // Section 1
    '{{receive_date}}' => htmlspecialchars($receiveDateThai),
    '{{receive_time}}' => htmlspecialchars($receiveTime),
    '{{daily_ref}}' => htmlspecialchars(getVal($fire, 'rf_daily_ref')),
    '{{notify_method_text}}' => $notify_method_text,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_phone}}' => $chk_notify_phone,
    '{{chk_notify_radio}}' => $chk_notify_radio,
    '{{from_station}}' => htmlspecialchars(getVal($fire, 'rf_from_station')),
    '{{investigator_name}}' => htmlspecialchars(getVal($fire, 'rf_investigator')),

    // Section 2
    '{{crime_location}}' => htmlspecialchars(getVal($fire, 'rf_crime_location')),

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

    // Section 6.1 ภายนอก
    '{{exterior_detail}}' => htmlspecialchars(getVal($fire, 'rf_exterior_detail')),
    '{{floor_count}}' => htmlspecialchars(getVal($fire, 'rf_floor_count')),
    '{{chk_fence_yes}}' => $chk_fence_yes,
    '{{chk_fence_no}}' => $chk_fence_no,
    '{{ext_front}}' => htmlspecialchars(getVal($fire, 'rf_ext_front')),
    '{{ext_left}}' => htmlspecialchars(getVal($fire, 'rf_ext_left')),
    '{{ext_right}}' => htmlspecialchars(getVal($fire, 'rf_ext_right')),
    '{{ext_back}}' => htmlspecialchars(getVal($fire, 'rf_ext_back')),

    // Section 6.2 ภายใน
    '{{interior_detail}}' => htmlspecialchars(getVal($fire, 'rf_interior_detail')),

    // Section 6.3 บริเวณที่เกิดเหตุ
    '{{incident_area}}' => htmlspecialchars(getVal($fire, 'rf_incident_area')),
    '{{area_size}}' => htmlspecialchars(getVal($fire, 'rf_area_size')),
    '{{facing_direction}}' => htmlspecialchars(getVal($fire, 'rf_facing_direction')),

    // โครงสร้าง
    '{{wall_front}}' => htmlspecialchars(getVal($fire, 'rf_wall_front')),
    '{{wall_left}}' => htmlspecialchars(getVal($fire, 'rf_wall_left')),
    '{{wall_right}}' => htmlspecialchars(getVal($fire, 'rf_wall_right')),
    '{{wall_back}}' => htmlspecialchars(getVal($fire, 'rf_wall_back')),
    '{{floor_material}}' => htmlspecialchars(getVal($fire, 'rf_floor_material')),
    '{{ceiling}}' => htmlspecialchars(getVal($fire, 'rf_ceiling')),
    '{{roof}}' => htmlspecialchars(getVal($fire, 'rf_roof')),

    // การจัดวางสิ่งของ
    '{{arrange_front}}' => htmlspecialchars(getVal($fire, 'rf_arrange_front')),
    '{{arrange_left}}' => htmlspecialchars(getVal($fire, 'rf_arrange_left')),
    '{{arrange_right}}' => htmlspecialchars(getVal($fire, 'rf_arrange_right')),
    '{{arrange_back}}' => htmlspecialchars(getVal($fire, 'rf_arrange_back')),
    '{{arrange_other}}' => htmlspecialchars(getVal($fire, 'rf_arrange_other')),

    // Section 7 พฤติการณ์คดี
    '{{case_behavior}}' => htmlspecialchars(getVal($fire, 'rf_case_behavior')),
    '{{chk_insurance_yes}}' => $chk_insurance_yes,
    '{{chk_insurance_no}}' => $chk_insurance_no,
    '{{burn_time}}' => htmlspecialchars(getVal($fire, 'rf_burn_time')),
    '{{chk_extinguish_yes}}' => $chk_extinguish_yes,
    '{{chk_extinguish_no}}' => $chk_extinguish_no,
    '{{extinguish_detail}}' => htmlspecialchars(getVal($fire, 'rf_extinguish_detail')),
    '{{damage_condition}}' => htmlspecialchars(getVal($fire, 'rf_damage_condition')),
    '{{spread_detail}}' => htmlspecialchars(getVal($fire, 'rf_spread_detail')),

    // 7.1 ความเสียหายโครงสร้าง
    '{{damage_wall_front}}' => htmlspecialchars(getVal($fire, 'rf_damage_wall_front')),
    '{{damage_wall_left}}' => htmlspecialchars(getVal($fire, 'rf_damage_wall_left')),
    '{{damage_wall_right}}' => htmlspecialchars(getVal($fire, 'rf_damage_wall_right')),
    '{{damage_wall_back}}' => htmlspecialchars(getVal($fire, 'rf_damage_wall_back')),
    '{{damage_floor}}' => htmlspecialchars(getVal($fire, 'rf_damage_floor')),
    '{{damage_roof}}' => htmlspecialchars(getVal($fire, 'rf_damage_roof')),
    '{{damage_ceiling}}' => htmlspecialchars(getVal($fire, 'rf_damage_ceiling')),

    // 7.2 ความเสียหายสิ่งของ
    '{{damage_obj_front}}' => htmlspecialchars(getVal($fire, 'rf_damage_obj_front')),
    '{{damage_obj_left}}' => htmlspecialchars(getVal($fire, 'rf_damage_obj_left')),
    '{{damage_obj_right}}' => htmlspecialchars(getVal($fire, 'rf_damage_obj_right')),
    '{{damage_obj_back}}' => htmlspecialchars(getVal($fire, 'rf_damage_obj_back')),
    '{{damage_obj_floor}}' => htmlspecialchars(getVal($fire, 'rf_damage_obj_floor')),
    '{{damage_obj_roof}}' => htmlspecialchars(getVal($fire, 'rf_damage_obj_roof')),
    '{{damage_obj_ceiling}}' => htmlspecialchars(getVal($fire, 'rf_damage_obj_ceiling')),

    // 7.3 - 7.6
    '{{first_area}}' => htmlspecialchars(getVal($fire, 'rf_first_area')),
    '{{switch_condition}}' => htmlspecialchars(getVal($fire, 'rf_switch_condition')),
    '{{chk_adjacent_found}}' => $chk_adjacent_found,
    '{{chk_adjacent_notfound}}' => $chk_adjacent_notfound,
    '{{adjacent_damage_detail}}' => htmlspecialchars(getVal($fire, 'rf_adjacent_damage_detail')),
    '{{evidence_found}}' => htmlspecialchars(getVal($fire, 'rf_evidence_found')),

    // Section 8 สรุปผล
    '{{origin_area}}' => htmlspecialchars(getVal($fire, 'rf_origin_area')),
    '{{fuel_source}}' => htmlspecialchars(getVal($fire, 'rf_fuel_source')),
    '{{heat_source}}' => htmlspecialchars(getVal($fire, 'rf_heat_source')),
    '{{summary_other}}' => htmlspecialchars(getVal($fire, 'rf_summary_other')),

    // Section 9 ความเห็น
    '{{opinion_first_area}}' => htmlspecialchars(getVal($fire, 'rf_opinion_first_area')),
    '{{chk_cause_believed}}' => $chk_cause_believed,
    '{{cause_believed_detail}}' => htmlspecialchars(getVal($fire, 'rf_cause_believed_detail')),
    '{{chk_cause_unknown}}' => $chk_cause_unknown,
    '{{cause_unknown_detail}}' => htmlspecialchars(getVal($fire, 'rf_cause_unknown_detail')),

    // การส่งมอบ
    '{{handover_officer}}' => htmlspecialchars(getVal($fire, 'rf_handover_officer')),
    '{{handover_to}}' => htmlspecialchars(getVal($fire, 'rf_handover_to')),
    '{{handover_date}}' => htmlspecialchars($handoverDate),
    '{{handover_time}}' => htmlspecialchars($handoverTime),

    // ลงชื่อ
    '{{signer_name}}' => htmlspecialchars(getVal($fire, 'rf_signer_name')),
    '{{signer_position}}' => htmlspecialchars(getVal($fire, 'rf_signer_position')),
    '{{sign_date}}' => htmlspecialchars($signDateFull),
];

// ==========================================
// 6. READ TEMPLATE AND REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_fire_report_preview.html');
if ($htmlTemplate === false) {
    die("Error: ไม่สามารถอ่านไฟล์ template ได้");
}

$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// ==========================================
// EXPORT สำหรับ DOCX (เพลิงไหม้)
// ==========================================
if (defined('WORD_DOCX_MODE') && WORD_DOCX_MODE) {
    $GLOBALS['fire_report_export'] = [
        'replacements' => $replacements,
        'fire' => $fire ?? [],
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
