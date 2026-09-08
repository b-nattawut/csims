<?php
/**
 * gen_pdf_bomb_report_indoor_html.php
 * Generate PDF F-CS-15 รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด (ในอาคาร)
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
                       IFNULL(t3.position_name,'') AS position
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
            $agencyType = 'กสก.ศพฐ.';
            $agencyName = 'กสก.ศพฐ. ศพฐ ' . $rv;
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
$indoor = [];

if ($reportData && isset($reportData['indoor']) && is_array($reportData['indoor'])) {
    $indoor = $reportData['indoor'];
}
// Always override agency from DB
if (!empty($agencyType)) {
    $indoor['rbi_agency_type'] = $agencyType;
    $indoor['rbi_agency_name'] = $agencyName;
}
if (empty($indoor) && $checklistData) {
    // Map from bomb checklist data
    $gi = $checklistData['general_info'] ?? [];
    $si = $checklistData['scene_info'] ?? [];
    $ir = $checklistData['inspection_results'] ?? [];
    $ho = $checklistData['handover'] ?? [];
    $indoorScene = $si['indoor'] ?? [];
    $structure = $indoorScene['structure'] ?? [];
    $victims = $checklistData['victims'] ?? [];

    $firstVictim = !empty($victims) ? $victims[0] : [];

    // Notify methods
    $channels = $gi['report_channel'] ?? [];
    $notifyMap = ['ทางโทรศัพท์' => 'โทรศัพท์', 'ทางวิทยุสื่อสาร' => 'วิทยุสื่อสาร', 'ทางหนังสือ' => 'หนังสือ'];
    $notifyMethods = [];
    if (is_array($channels)) {
        foreach ($channels as $m) {
            $notifyMethods[] = $notifyMap[$m] ?? $m;
        }
    }

    // Building type
    $buildingTypes = $indoorScene['building_type'] ?? [];
    $buildingOther = $indoorScene['building_type_other'] ?? '';
    $buildingParts = [];
    if (is_array($buildingTypes)) {
        foreach ($buildingTypes as $bt) {
            $buildingParts[] = $bt;
        }
    }
    if (!empty($buildingOther)) $buildingParts[] = $buildingOther;
    $buildingTypeText = implode(', ', $buildingParts);

    // Investigator
    $investigator = $gi['investigator'] ?? [];
    $investigatorName = '';
    if (is_array($investigator)) {
        $investigatorName = $investigator['name'] ?? '';
    } elseif (is_string($investigator)) {
        $investigatorName = $investigator;
    }

    // Bodies
    $bodies = $ir['bodies'] ?? [];

    $indoor = [
        'rbi_receive_date' => $gi['case_date'] ?? '',
        'rbi_receive_time' => $gi['case_time'] ?? '',
        'rbi_daily_ref' => $gi['document_no'] ?? '',
        'rbi_agency_type' => $agencyType,
        'rbi_agency_name' => $agencyName,
        'rbi_notify_method[]' => $notifyMethods,
        'rbi_notify_method_other' => $gi['report_channel_other'] ?? '',
        'rbi_from_station' => $gi['source_station'] ?? '',
        'rbi_investigator' => $investigatorName,
        'rbi_crime_location' => $si['crime_location'] ?? '',
        'rbi_victim_name' => $firstVictim['name'] ?? '',
        'rbi_victim_age' => $firstVictim['age'] ?? '',
        'rbi_victim_know_date' => $gi['victim_know_date'] ?? '',
        'rbi_victim_know_time' => $gi['victim_know_time'] ?? '',
        'rbi_officer_know_date' => $gi['officer_know_date'] ?? '',
        'rbi_officer_know_time' => $gi['officer_know_time'] ?? '',
        'rbi_inspect_date' => $gi['inspect_date'] ?? '',
        'rbi_inspect_time' => $gi['inspect_time'] ?? '',
        'rbi_inspect_add_date' => $gi['inspect_additional_date'] ?? '',
        'rbi_inspect_add_time' => $gi['inspect_additional_time'] ?? '',
        'rbi_inspector_name[]' => [],
        'rbi_inspector_position[]' => [],
        'rbi_scene_preserved' => $si['preservation'] ?? '',
        'rbi_scene_preserved_detail' => $si['preservation_detail'] ?? '',
        'rbi_building_type_text' => $buildingTypeText,
        'rbi_floor_count' => '',
        'rbi_unit_count' => '',
        'rbi_mezzanine' => '',
        'rbi_rooftop' => '',
        'rbi_fence' => $indoorScene['fence'] ?? '',
        'rbi_ext_front' => $indoorScene['adjacent']['front'] ?? '',
        'rbi_ext_left' => $indoorScene['adjacent']['left'] ?? '',
        'rbi_ext_right' => $indoorScene['adjacent']['right'] ?? '',
        'rbi_ext_back' => $indoorScene['adjacent']['back'] ?? '',
        'rbi_interior_detail' => $indoorScene['interior'] ?? '',
        'rbi_incident_area' => $indoorScene['incident_area'] ?? '',
        'rbi_area_size' => $structure['size'] ?? '',
        'rbi_wall_front' => $structure['wall'] ?? '',
        'rbi_wall_front_window' => '', 'rbi_wall_front_door' => '',
        'rbi_wall_left' => '', 'rbi_wall_left_window' => '', 'rbi_wall_left_door' => '',
        'rbi_wall_right' => '', 'rbi_wall_right_window' => '', 'rbi_wall_right_door' => '',
        'rbi_wall_back' => '', 'rbi_wall_back_window' => '', 'rbi_wall_back_door' => '',
        'rbi_floor_material' => $structure['floor'] ?? '',
        'rbi_ceiling' => '',
        'rbi_roof' => $structure['roof'] ?? '',
        'rbi_arrange_front' => $structure['arrangement'] ?? '',
        'rbi_arrange_left' => '',
        'rbi_arrange_right' => '',
        'rbi_arrange_back' => '',
        'rbi_case_behavior' => $ir['case_behavior'] ?? '',
        'rbi_scene_condition' => '',
        'rbi_body_found' => !empty($bodies) ? ($bodies[0]['status'] ?? '') : '',
        'rbi_body_position' => '',
        'rbi_body_condition' => !empty($bodies) ? ($bodies[0]['condition_detail'] ?? '') : '',
        'rbi_body_clothing' => '',
        'rbi_body_wounds' => '',
        'rbi_damage_detail' => $ir['damage_details'] ?? '',
        'rbi_evidence_found' => '',
        'rbi_evidence_collected' => '',
        'rbi_evidence_action' => '',
        'rbi_other_detail' => '',
        'rbi_handover_officer' => '',
        'rbi_handover_to' => '',
        'rbi_handover_date' => $ho['inspection_end_date'] ?? '',
        'rbi_handover_time' => $ho['inspection_end_time'] ?? '',
        'rbi_signer_name' => '',
        'rbi_signer_position' => '',
    ];

    // Resolve inspector IDs → names
    $inspectorIds = $checklistData['inspectors'] ?? [];
    if (is_array($inspectorIds)) {
        foreach ($inspectorIds as $id) {
            if (isset($userMap[$id])) {
                $indoor['rbi_inspector_name[]'][] = $userMap[$id]['fullname'];
                $indoor['rbi_inspector_position[]'][] = $userMap[$id]['position'];
            }
        }
    }

    // Resolve handover user IDs
    $delivererId = $ho['deliverer_id'] ?? '';
    $receiverId = $ho['receiver_id'] ?? '';
    if (isset($userMap[$delivererId])) {
        $indoor['rbi_handover_officer'] = $userMap[$delivererId]['fullname'];
        $indoor['rbi_signer_name'] = $userMap[$delivererId]['fullname'];
        $indoor['rbi_signer_position'] = $userMap[$delivererId]['position'];
    }
    if (isset($userMap[$receiverId])) {
        $indoor['rbi_handover_to'] = $userMap[$receiverId]['fullname'];
    }
}

// Resolve user IDs that may still be numeric in indoor data
$resolveFields = ['rbi_handover_officer', 'rbi_handover_to', 'rbi_signer_name'];
foreach ($resolveFields as $fk) {
    if (isset($indoor[$fk]) && is_numeric($indoor[$fk]) && isset($userMap[$indoor[$fk]])) {
        $indoor[$fk] = $userMap[$indoor[$fk]]['fullname'];
    }
}
if (isset($indoor['rbi_signer_position']) && is_numeric($indoor['rbi_signer_position']) && isset($userMap[$indoor['rbi_signer_position']])) {
    $indoor['rbi_signer_position'] = $userMap[$indoor['rbi_signer_position']]['position'];
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
$displayOverride = getVal($indoor, 'rbi_report_no_display_pdf');
if ($displayOverride === '') $displayOverride = getVal($indoor, 'rbi_report_no_display_pdf_2');
if ($displayOverride === '') $displayOverride = getVal($indoor, 'rbi_report_no_display_pdf_3');
if ($displayOverride !== '') $report_no = $displayOverride;
$report_no = smartThaiReportOrDoc($report_no);

// Notify method checkboxes
$notifyMethods = getVal($indoor, 'rbi_notify_method[]', []);
if (!is_array($notifyMethods)) $notifyMethods = [$notifyMethods];

// ★ ข้อ 7: ให้ "วิธีการรับแจ้ง" ออกมาเป็นข้อความไทย ไม่ใช่ช่องติ๊กเปล่า
require_once __DIR__ . '/notify_method_helper.php';
$notify_method_text = notifyMethodToText(
    $notifyMethods,
    (string)getVal($indoor, 'rbi_notify_method_other', '')
);
$chk_notify_letter = renderCheckbox(
    in_array('หนังสือ', $notifyMethods) || in_array('ทางหนังสือ', $notifyMethods)
);
$chk_notify_phone = renderCheckbox(
    in_array('โทรศัพท์', $notifyMethods) || in_array('ทางโทรศัพท์', $notifyMethods)
);
$chk_notify_radio = renderCheckbox(
    in_array('วิทยุสื่อสาร', $notifyMethods) || in_array('ทางวิทยุสื่อสาร', $notifyMethods)
);

// Dates
$receiveDateThai = thaiDateFull(getVal($indoor, 'rbi_receive_date'));
$receiveTime = getVal($indoor, 'rbi_receive_time');
$victimKnowDate = thaiDateFull(getVal($indoor, 'rbi_victim_know_date'));
$victimKnowTime = getVal($indoor, 'rbi_victim_know_time');
$officerKnowDate = thaiDateFull(getVal($indoor, 'rbi_officer_know_date'));
$officerKnowTime = getVal($indoor, 'rbi_officer_know_time');
$inspectDate = thaiDateFull(getVal($indoor, 'rbi_inspect_date'));
$inspectTime = getVal($indoor, 'rbi_inspect_time');
$inspectAddDate = thaiDateFull(getVal($indoor, 'rbi_inspect_add_date'));
$inspectAddTime = getVal($indoor, 'rbi_inspect_add_time');
$handoverDate = thaiDateFull(getVal($indoor, 'rbi_handover_date'));
$handoverTime = getVal($indoor, 'rbi_handover_time');

// Inspector rows HTML
$inspectorNames = getVal($indoor, 'rbi_inspector_name[]', []);
$inspectorPositions = getVal($indoor, 'rbi_inspector_position[]', []);
if (!is_array($inspectorNames)) $inspectorNames = [];
$inspector_rows_html = '';
foreach ($inspectorNames as $idx => $name) {
    $no = $idx + 1;
    $pos = $inspectorPositions[$idx] ?? '';
    $posText = !empty($pos) ? '  ตำแหน่ง ' . htmlspecialchars($pos) : '';
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">5.' . $no . '.</span><span class="fd" style="margin-left:6px;">' . htmlspecialchars($name) . $posText . '</span></div>' . "\n";
}
if (empty($inspectorNames)) {
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">5.1.</span><span class="fd" style="margin-left:6px;"></span><span class="fl" style="margin-left:20px;">ตำแหน่ง</span><span class="fd"></span></div>' . "\n";
}

// Fence
$fence = getVal($indoor, 'rbi_fence');
$chk_fence_yes = renderCheckbox($fence === 'มี' || $fence === 'มีรั้ว');
$chk_fence_no = renderCheckbox($fence === 'ไม่มี' || $fence === 'ไม่มีรั้ว');

// Mezzanine
$mezzanine = getVal($indoor, 'rbi_mezzanine');
$chk_mezzanine_yes = renderCheckbox(!empty($mezzanine) && $mezzanine !== 'ไม่มี');
$chk_mezzanine_no = renderCheckbox(empty($mezzanine) || $mezzanine === 'ไม่มี');

// Rooftop
$rooftop = getVal($indoor, 'rbi_rooftop');
$chk_rooftop_yes = renderCheckbox(!empty($rooftop) && $rooftop !== 'ไม่มี');
$chk_rooftop_no = renderCheckbox(empty($rooftop) || $rooftop === 'ไม่มี');

// Building type text
$buildingTypeText = getVal($indoor, 'rbi_building_type_text');
if (empty($buildingTypeText)) {
    $bt = getVal($indoor, 'rbi_building_type[]', []);
    if (is_array($bt)) {
        $buildingTypeText = implode(', ', $bt);
    }
    $bto = getVal($indoor, 'rbi_building_type_other');
    if (!empty($bto)) $buildingTypeText .= (!empty($buildingTypeText) ? ', ' : '') . $bto;
}

// Agency short
$agencyShort = getVal($indoor, 'rbi_agency_name', $agencyName);

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
    '{{type_form}}' => '(ในอาคาร)',
    '{{agency_name}}' => htmlspecialchars($agencyShort),
    '{{agency_short}}' => htmlspecialchars($agencyShort),

    // Section 1
    '{{receive_date}}' => htmlspecialchars($receiveDateThai),
    '{{receive_time}}' => htmlspecialchars($receiveTime),
    '{{daily_ref}}' => htmlspecialchars(getVal($indoor, 'rbi_daily_ref')),
    '{{notify_method_text}}' => $notify_method_text,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_phone}}' => $chk_notify_phone,
    '{{chk_notify_radio}}' => $chk_notify_radio,
    '{{from_station}}' => htmlspecialchars(getVal($indoor, 'rbi_from_station')),
    '{{investigator_name}}' => htmlspecialchars(getVal($indoor, 'rbi_investigator')),

    // Section 2
    '{{crime_location}}' => htmlspecialchars(getVal($indoor, 'rbi_crime_location')),
    '{{victim_name}}' => htmlspecialchars(getVal($indoor, 'rbi_victim_name')),
    '{{victim_age}}' => htmlspecialchars(getVal($indoor, 'rbi_victim_age')),

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
    '{{floor_count}}' => htmlspecialchars(getVal($indoor, 'rbi_floor_count')),
    '{{unit_count}}' => htmlspecialchars(getVal($indoor, 'rbi_unit_count')),
    '{{chk_mezzanine_yes}}' => $chk_mezzanine_yes,
    '{{chk_mezzanine_no}}' => $chk_mezzanine_no,
    '{{chk_rooftop_yes}}' => $chk_rooftop_yes,
    '{{chk_rooftop_no}}' => $chk_rooftop_no,
    '{{chk_fence_yes}}' => $chk_fence_yes,
    '{{chk_fence_no}}' => $chk_fence_no,
    '{{ext_front}}' => htmlspecialchars(getVal($indoor, 'rbi_ext_front')),
    '{{ext_left}}' => htmlspecialchars(getVal($indoor, 'rbi_ext_left')),
    '{{ext_right}}' => htmlspecialchars(getVal($indoor, 'rbi_ext_right')),
    '{{ext_back}}' => htmlspecialchars(getVal($indoor, 'rbi_ext_back')),

    // Section 6.2 ลักษณะภายใน
    '{{interior_detail}}' => htmlspecialchars(getVal($indoor, 'rbi_interior_detail')),

    // Section 6.3 บริเวณเกิดเหตุ
    '{{incident_area}}' => htmlspecialchars(getVal($indoor, 'rbi_incident_area')),
    '{{area_size}}' => htmlspecialchars(getVal($indoor, 'rbi_area_size')),

    // โครงสร้าง
    '{{wall_front}}' => htmlspecialchars(getVal($indoor, 'rbi_wall_front')),
    '{{wall_front_window}}' => htmlspecialchars(getVal($indoor, 'rbi_wall_front_window')),
    '{{wall_front_door}}' => htmlspecialchars(getVal($indoor, 'rbi_wall_front_door')),
    '{{wall_left}}' => htmlspecialchars(getVal($indoor, 'rbi_wall_left')),
    '{{wall_left_window}}' => htmlspecialchars(getVal($indoor, 'rbi_wall_left_window')),
    '{{wall_left_door}}' => htmlspecialchars(getVal($indoor, 'rbi_wall_left_door')),
    '{{wall_right}}' => htmlspecialchars(getVal($indoor, 'rbi_wall_right')),
    '{{wall_right_window}}' => htmlspecialchars(getVal($indoor, 'rbi_wall_right_window')),
    '{{wall_right_door}}' => htmlspecialchars(getVal($indoor, 'rbi_wall_right_door')),
    '{{wall_back}}' => htmlspecialchars(getVal($indoor, 'rbi_wall_back')),
    '{{wall_back_window}}' => htmlspecialchars(getVal($indoor, 'rbi_wall_back_window')),
    '{{wall_back_door}}' => htmlspecialchars(getVal($indoor, 'rbi_wall_back_door')),
    '{{floor_material}}' => htmlspecialchars(getVal($indoor, 'rbi_floor_material')),
    '{{ceiling}}' => htmlspecialchars(getVal($indoor, 'rbi_ceiling')),
    '{{roof}}' => htmlspecialchars(getVal($indoor, 'rbi_roof')),

    // การจัดวางสิ่งของ
    '{{arrange_front}}' => htmlspecialchars(getVal($indoor, 'rbi_arrange_front')),
    '{{arrange_left}}' => htmlspecialchars(getVal($indoor, 'rbi_arrange_left')),
    '{{arrange_right}}' => htmlspecialchars(getVal($indoor, 'rbi_arrange_right')),
    '{{arrange_back}}' => htmlspecialchars(getVal($indoor, 'rbi_arrange_back')),

    // Section 7
    '{{case_behavior}}' => htmlspecialchars(getVal($indoor, 'rbi_case_behavior')),
    '{{scene_condition}}' => htmlspecialchars(getVal($indoor, 'rbi_scene_condition')),
    '{{body_found}}' => htmlspecialchars(getVal($indoor, 'rbi_body_found')),
    '{{body_position}}' => htmlspecialchars(getVal($indoor, 'rbi_body_position')),
    '{{body_condition}}' => htmlspecialchars(getVal($indoor, 'rbi_body_condition')),
    '{{body_clothing}}' => htmlspecialchars(getVal($indoor, 'rbi_body_clothing')),
    '{{body_wounds}}' => htmlspecialchars(getVal($indoor, 'rbi_body_wounds')),
    '{{damage_detail}}' => htmlspecialchars(getVal($indoor, 'rbi_damage_detail')),
    '{{evidence_found}}' => htmlspecialchars(getVal($indoor, 'rbi_evidence_found')),
    '{{evidence_collected}}' => htmlspecialchars(getVal($indoor, 'rbi_evidence_collected')),
    '{{evidence_action}}' => htmlspecialchars(getVal($indoor, 'rbi_evidence_action')),
    '{{other_detail}}' => htmlspecialchars(getVal($indoor, 'rbi_other_detail')),

    // Section 7.7 การส่งมอบ
    '{{handover_agency}}' => htmlspecialchars($agencyShort),
    '{{handover_to}}' => htmlspecialchars(getVal($indoor, 'rbi_handover_to')),
    '{{handover_date}}' => htmlspecialchars($handoverDate),
    '{{handover_time}}' => htmlspecialchars($handoverTime),

    // Signature
    '{{signer_name}}' => htmlspecialchars(getVal($indoor, 'rbi_signer_name')),
    '{{signer_position}}' => htmlspecialchars(getVal($indoor, 'rbi_signer_position')),
];

// ==========================================
// 6. READ TEMPLATE AND REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_bomb_report_indoor_preview.html');
if ($htmlTemplate === false) {
    die("Error: ไม่สามารถอ่านไฟล์ template ได้");
}

$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// ==========================================
// EXPORT สำหรับ DOCX (ระเบิดในอาคาร)
// ==========================================
if (defined('WORD_DOCX_MODE') && WORD_DOCX_MODE) {
    $GLOBALS['bomb_indoor_report_export'] = [
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
