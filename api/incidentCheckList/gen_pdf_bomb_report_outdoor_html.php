<?php
/**
 * gen_pdf_bomb_report_outdoor_html.php
 * Generate PDF F-CS-16 รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด (นอกอาคาร)
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../helpers/report_no.php';

// ==========================================
// 1. HELPER FUNCTIONS
// ==========================================
function thaiDateFull($datetime) {
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}

function getVal($arr, $key, $default = '') {
    if (!is_array($arr)) return $default;
    return isset($arr[$key]) && $arr[$key] !== null ? $arr[$key] : $default;
}

function renderCheckbox($condition) { return $condition ? '✓' : ''; }

function wrapText($text, $maxChars = 75) {
    $lines = preg_split('/\r?\n/', $text);
    $result = [];
    foreach ($lines as $line) {
        if (mb_strlen($line, 'UTF-8') <= $maxChars) {
            $result[] = $line;
        } else {
            while (mb_strlen($line, 'UTF-8') > $maxChars) {
                $result[] = mb_substr($line, 0, $maxChars, 'UTF-8');
                $line = mb_substr($line, $maxChars, null, 'UTF-8');
            }
            if (mb_strlen($line, 'UTF-8') > 0) $result[] = $line;
        }
    }
    return $result;
}

function textToRows($text, $indent = 'i2', $minRows = 1) {
    $html = '';
    if (empty(trim($text))) {
        for ($i = 0; $i < $minRows; $i++) {
            $html .= '<div class="fr '.$indent.'"><span class="fd-full"></span></div>'."\n";
        }
        return $html;
    }
    $charsPerLine = ($indent === 'i1') ? 62 : 58;
    $lines = wrapText($text, $charsPerLine);
    foreach ($lines as $line) {
        $html .= '<div class="fr '.$indent.'"><span class="fd-full">'.htmlspecialchars($line).'</span></div>'."\n";
    }
    return $html;
}

function labeledTextToRows($label, $text, $indent = 'i2') {
    if (empty(trim($text))) {
        return '<div class="fr '.$indent.'"><span class="fl">'.$label.'</span><span class="fd"></span></div>'."\n";
    }
    $labelLen = mb_strlen(strip_tags($label), 'UTF-8');
    $firstLineMax = max(20, 58 - $labelLen);
    $lines = wrapText($text, $firstLineMax);
    // Re-wrap remaining lines at full width
    $allLines = [$lines[0]];
    $remaining = implode('', array_slice($lines, 1));
    if (mb_strlen($remaining, 'UTF-8') > 0) {
        $allLines = array_merge($allLines, wrapText($remaining, 58));
    }
    $html = '';
    $first = true;
    foreach ($allLines as $line) {
        if ($first) {
            $html .= '<div class="fr '.$indent.'"><span class="fl">'.$label.'</span><span class="fd">'.htmlspecialchars($line).'</span></div>'."\n";
            $first = false;
        } else {
            $html .= '<div class="fr '.$indent.'"><span class="fd-full">'.htmlspecialchars($line).'</span></div>'."\n";
        }
    }
    return $html;
}
    
// ==========================================
// 2. FETCH DATA FROM DATABASE
// ==========================================
$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;
if ($incident_id <= 0) die("Error: กรุณาระบุ incident_id");

try {
    $stmt = $pdo->prepare("SELECT incident_report_data, incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) die("Error: ไม่พบข้อมูลสำหรับ incident_id: " . $incident_id);
    $reportData = !empty($row['incident_report_data']) ? json_decode($row['incident_report_data'], true) : null;
    $checklistData = !empty($row['incident_checklist_data']) ? json_decode($row['incident_checklist_data'], true) : null;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// USER MAP
// ==========================================
$userMap = [];
try {
    $sqlUser = "SELECT t1.user_id, CONCAT(IFNULL(t2.rank_name,''),' ',t1.first_name,' ',t1.last_name) AS fullname, IFNULL(t3.position_name,'') AS position FROM user_profile t1 LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id LEFT JOIN user_position t3 ON t1.position_id = t3.position_id";
    $stmtUser = $pdo->query($sqlUser);
    while ($u = $stmtUser->fetch(PDO::FETCH_ASSOC)) { $userMap[$u['user_id']] = $u; }
} catch (Exception $e) {}

// ==========================================
// AGENCY INFO
// ==========================================
$agencyType = ''; $agencyName = '';
try {
    $stmtAgency = $pdo->prepare("SELECT userReviewType, userReviewTypeVal FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
    $stmtAgency->execute([$incident_id]);
    $agencyRow = $stmtAgency->fetch(PDO::FETCH_ASSOC);
    if ($agencyRow) {
        $provinceMap = ['95'=>'ยะลา','94'=>'ปัตตานี','96'=>'นราธิวาส'];
        $rt = $agencyRow['userReviewType'] ?? ''; $rv = $agencyRow['userReviewTypeVal'] ?? '';
        if ($rt === 'nvt') { $agencyType = 'กสก.พฐก.'; $agencyName = 'นวท.(สบ '.$rv.') กสก.พฐก.'; }
        elseif ($rt === 'spt') { $agencyType = 'กสก.ศพฐ.'; $agencyName = 'กสก.ศพฐ. ศพฐ '.$rv; }
        elseif ($rt === 'ptjv') { $agencyType = 'พฐ.จว.'; $agencyName = 'พฐ.จว.'.($provinceMap[strval($rv)] ?? $rv); }
    }
} catch (Exception $e) {}

// ==========================================
// 3. DETERMINE DATA SOURCE
// ==========================================
$outdoor = [];

if ($reportData && isset($reportData['outdoor']) && is_array($reportData['outdoor'])) {
    $outdoor = $reportData['outdoor'];
}
// Always override agency from DB
if (!empty($agencyType)) {
    $outdoor['rbo_agency_type'] = $agencyType;
    $outdoor['rbo_agency_name'] = $agencyName;
}
if (empty($outdoor) && $checklistData) {
    $gi = $checklistData['general_info'] ?? [];
    $si = $checklistData['scene_info'] ?? [];
    $ir = $checklistData['inspection_results'] ?? [];
    $ho = $checklistData['handover'] ?? [];
    $victims = $checklistData['victims'] ?? [];
    $firstVictim = !empty($victims) ? $victims[0] : [];

    $channels = $gi['report_channel'] ?? [];
    $notifyMethods = [];
    if (is_array($channels)) {
        $notifyMap = ['ทางโทรศัพท์'=>'โทรศัพท์','ทางวิทยุสื่อสาร'=>'วิทยุสื่อสาร','ทางหนังสือ'=>'หนังสือ'];
        foreach ($channels as $m) { $notifyMethods[] = $notifyMap[$m] ?? $m; }
    }

    $investigator = $gi['investigator'] ?? [];
    $investigatorName = is_array($investigator) ? ($investigator['name'] ?? '') : (string)$investigator;

    $bodies = $ir['bodies'] ?? [];

    $outdoor = [
        'rbo_receive_date' => $gi['case_date'] ?? '',
        'rbo_receive_time' => $gi['case_time'] ?? '',
        'rbo_daily_ref' => $gi['document_no'] ?? '',
        'rbo_agency_type' => $agencyType,
        'rbo_agency_name' => $agencyName,
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
        'rbo_inspector_name[]' => [],
        'rbo_inspector_position[]' => [],
        'rbo_scene_description' => $si['outdoor']['description'] ?? '',
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
        'rbo_handover_officer' => '',
        'rbo_handover_to' => '',
        'rbo_handover_date' => $ho['inspection_end_date'] ?? '',
        'rbo_handover_time' => $ho['inspection_end_time'] ?? '',
        'rbo_signer_name' => '',
        'rbo_signer_position' => '',
    ];

    $inspectorIds = $checklistData['inspectors'] ?? [];
    if (is_array($inspectorIds)) {
        foreach ($inspectorIds as $id) {
            if (isset($userMap[$id])) {
                $outdoor['rbo_inspector_name[]'][] = $userMap[$id]['fullname'];
                $outdoor['rbo_inspector_position[]'][] = $userMap[$id]['position'];
            }
        }
    }

    $delivererId = $ho['deliverer_id'] ?? '';
    $receiverId = $ho['receiver_id'] ?? '';
    if (isset($userMap[$delivererId])) {
        $outdoor['rbo_handover_officer'] = $userMap[$delivererId]['fullname'];
        $outdoor['rbo_signer_name'] = $userMap[$delivererId]['fullname'];
        $outdoor['rbo_signer_position'] = $userMap[$delivererId]['position'];
    }
    if (isset($userMap[$receiverId])) {
        $outdoor['rbo_handover_to'] = $userMap[$receiverId]['fullname'];
    }
}

// Resolve user IDs
$resolveFields = ['rbo_handover_officer', 'rbo_handover_to', 'rbo_signer_name'];
foreach ($resolveFields as $fk) {
    if (isset($outdoor[$fk]) && is_numeric($outdoor[$fk]) && isset($userMap[$outdoor[$fk]])) {
        $outdoor[$fk] = $userMap[$outdoor[$fk]]['fullname'];
    }
}

// ==========================================
// 4. PREPARE TEMPLATE VARIABLES
// ==========================================
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

$report_no = ''; $report_year_short = '';
if (!empty($rawReportNo) && strpos($rawReportNo, '/') !== false) {
    $parts = explode('/', $rawReportNo, 2);
    $report_no = $parts[0]; $report_year_short = substr($parts[1], -2);
} else { $report_no = $rawReportNo; }
$displayOverride = getVal($outdoor, 'rbo_report_no_display_pdf');
if ($displayOverride === '') $displayOverride = getVal($outdoor, 'rbo_report_no_display_pdf_2');
if ($displayOverride === '') $displayOverride = getVal($outdoor, 'rbo_report_no_display_pdf_3');
if ($displayOverride !== '') $report_no = $displayOverride;
$report_no = smartThaiReportOrDoc($report_no);

// Notify method checkboxes
$notifyMethods = getVal($outdoor, 'rbo_notify_method[]', []);
if (!is_array($notifyMethods)) $notifyMethods = [$notifyMethods];

// ★ ข้อ 7: ให้ "วิธีการรับแจ้ง" ออกมาเป็นข้อความไทย ไม่ใช่ช่องติ๊กเปล่า
require_once __DIR__ . '/notify_method_helper.php';
$notify_method_text = notifyMethodToText(
    $notifyMethods,
    (string)getVal($outdoor, 'rbo_notify_method_other', '')
);
$chk_notify_letter = renderCheckbox(in_array('หนังสือ', $notifyMethods) || in_array('ทางหนังสือ', $notifyMethods));
$chk_notify_phone = renderCheckbox(in_array('โทรศัพท์', $notifyMethods) || in_array('ทางโทรศัพท์', $notifyMethods));
$chk_notify_radio = renderCheckbox(in_array('วิทยุสื่อสาร', $notifyMethods) || in_array('ทางวิทยุสื่อสาร', $notifyMethods));

// Dates
$receiveDateThai = thaiDateFull(getVal($outdoor, 'rbo_receive_date'));
$receiveTime = getVal($outdoor, 'rbo_receive_time');
$victimKnowDate = thaiDateFull(getVal($outdoor, 'rbo_victim_know_date'));
$victimKnowTime = getVal($outdoor, 'rbo_victim_know_time');
$officerKnowDate = thaiDateFull(getVal($outdoor, 'rbo_officer_know_date'));
$officerKnowTime = getVal($outdoor, 'rbo_officer_know_time');
$inspectDate = thaiDateFull(getVal($outdoor, 'rbo_inspect_date'));
$inspectTime = getVal($outdoor, 'rbo_inspect_time');
$inspectAddDate = thaiDateFull(getVal($outdoor, 'rbo_inspect_add_date'));
$inspectAddTime = getVal($outdoor, 'rbo_inspect_add_time');
$handoverDate = thaiDateFull(getVal($outdoor, 'rbo_handover_date'));
$handoverTime = getVal($outdoor, 'rbo_handover_time');

// Inspector rows
$inspectorNames = getVal($outdoor, 'rbo_inspector_name[]', []);
$inspectorPositions = getVal($outdoor, 'rbo_inspector_position[]', []);
if (!is_array($inspectorNames)) $inspectorNames = [];
$inspector_rows_html = '';
foreach ($inspectorNames as $idx => $name) {
    $no = $idx + 1;
    $pos = $inspectorPositions[$idx] ?? '';
    $posText = !empty($pos) ? '  ตำแหน่ง ' . htmlspecialchars($pos) : '';
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">5.'.$no.'.</span><span class="fd" style="margin-left:6px;">'.htmlspecialchars($name).$posText.'</span></div>'."\n";
}
if (empty($inspectorNames)) {
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">5.1.</span><span class="fd" style="margin-left:6px;"></span><span class="fl" style="margin-left:20px;">ตำแหน่ง</span><span class="fd"></span></div>'."\n";
}

$agencyShort = getVal($outdoor, 'rbo_agency_name', $agencyName);
$report_no_filename = preg_replace('/[^a-zA-Z0-9\-_]/', '', str_replace('/', '-', $rawReportNo));

// ==========================================
// 5. BUILD REPLACEMENTS
// ==========================================
$replacements = [
    '{{report_no}}' => htmlspecialchars($report_no),
    '{{report_year_short}}' => htmlspecialchars($report_year_short),
    '{{report_no_filename}}' => htmlspecialchars($report_no_filename),
    '{{total_pages}}' => '3',
    '{{type_form}}' => '(นอกอาคาร)',
    '{{agency_name}}' => htmlspecialchars($agencyShort),
    '{{agency_short}}' => htmlspecialchars($agencyShort),

    '{{receive_date}}' => htmlspecialchars($receiveDateThai),
    '{{receive_time}}' => htmlspecialchars($receiveTime),
    '{{daily_ref}}' => htmlspecialchars(getVal($outdoor, 'rbo_daily_ref')),
    '{{notify_method_text}}' => $notify_method_text,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_phone}}' => $chk_notify_phone,
    '{{chk_notify_radio}}' => $chk_notify_radio,
    '{{from_station}}' => htmlspecialchars(getVal($outdoor, 'rbo_from_station')),
    '{{investigator_name}}' => htmlspecialchars(getVal($outdoor, 'rbo_investigator')),

    '{{crime_location_rows}}' => textToRows(getVal($outdoor, 'rbo_crime_location'), 'i1'),
    '{{victim_name}}' => htmlspecialchars(getVal($outdoor, 'rbo_victim_name')),
    '{{victim_age}}' => htmlspecialchars(getVal($outdoor, 'rbo_victim_age')),

    '{{victim_know_date}}' => htmlspecialchars($victimKnowDate),
    '{{victim_know_time}}' => htmlspecialchars($victimKnowTime),
    '{{officer_know_date}}' => htmlspecialchars($officerKnowDate),
    '{{officer_know_time}}' => htmlspecialchars($officerKnowTime),

    '{{inspect_date}}' => htmlspecialchars($inspectDate),
    '{{inspect_time}}' => htmlspecialchars($inspectTime),
    '{{inspect_add_date}}' => htmlspecialchars($inspectAddDate),
    '{{inspect_add_time}}' => htmlspecialchars($inspectAddTime),

    '{{inspector_rows}}' => $inspector_rows_html,

    '{{scene_description_rows}}' => textToRows(getVal($outdoor, 'rbo_scene_description'), 'i1'),

    '{{case_behavior}}' => htmlspecialchars(getVal($outdoor, 'rbo_case_behavior')),
    '{{scene_condition_rows}}' => textToRows(getVal($outdoor, 'rbo_scene_condition'), 'i2'),
    '{{body_found}}' => htmlspecialchars(getVal($outdoor, 'rbo_body_found')),
    '{{body_position_rows}}' => labeledTextToRows('7.2.2 ตำแหน่งที่พบศพ', getVal($outdoor, 'rbo_body_position')),
    '{{body_condition_rows}}' => labeledTextToRows('7.2.3 สภาพศพ', getVal($outdoor, 'rbo_body_condition')),
    '{{body_clothing_rows}}' => labeledTextToRows('7.2.4 สภาพเครื่องแต่งกายและทรัพย์สิน', getVal($outdoor, 'rbo_body_clothing')),
    '{{body_wounds_rows}}' => labeledTextToRows('7.2.5 รอยบาดแผลที่ศพ', getVal($outdoor, 'rbo_body_wounds')),
    '{{damage_detail_rows}}' => textToRows(getVal($outdoor, 'rbo_damage_detail'), 'i2'),
    '{{explosion_position_rows}}' => textToRows(getVal($outdoor, 'rbo_explosion_position'), 'i2'),
    '{{evidence_found_rows}}' => textToRows(getVal($outdoor, 'rbo_evidence_found'), 'i2'),
    '{{evidence_collected_rows}}' => textToRows(getVal($outdoor, 'rbo_evidence_collected'), 'i2'),
    '{{evidence_action_rows}}' => textToRows(getVal($outdoor, 'rbo_evidence_action'), 'i2'),

    '{{handover_agency}}' => htmlspecialchars($agencyShort),
    '{{handover_to}}' => htmlspecialchars(getVal($outdoor, 'rbo_handover_to')),
    '{{handover_date}}' => htmlspecialchars($handoverDate),
    '{{handover_time}}' => htmlspecialchars($handoverTime),

    '{{signer_name}}' => htmlspecialchars(getVal($outdoor, 'rbo_signer_name')),
    '{{signer_position}}' => htmlspecialchars(getVal($outdoor, 'rbo_signer_position')),
];

// ==========================================
// 6. READ TEMPLATE AND REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_bomb_report_outdoor_preview.html');
if ($htmlTemplate === false) die("Error: ไม่สามารถอ่านไฟล์ template ได้");

$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// ==========================================
// EXPORT สำหรับ DOCX (ระเบิดนอกอาคาร)
// ==========================================
if (defined('WORD_DOCX_MODE') && WORD_DOCX_MODE) {
    $GLOBALS['bomb_outdoor_report_export'] = [
        'replacements' => $replacements,
        'outdoor' => $outdoor ?? [],
    ];
    return;
}

if (!defined('WORD_REPORT_CAPTURE')) {
    header('Content-Type: text/html; charset=utf-8');
}
echo $htmlContent;
