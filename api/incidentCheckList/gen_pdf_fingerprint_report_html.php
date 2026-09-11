<?php
/**
 * gen_pdf_fingerprint_report_html.php
 * Generate PDF รายงานการตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง) — ร่างรายงาน
 *
 * รับ parameter: incident_id (GET)
 * ดึงข้อมูลจาก: incident_checklist_transaction.incident_report_data (JSON key: fingerprint)
 * ใช้ template: form_fingerprint_report_preview.html + str_replace pattern
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/lab_unit_helper.php';
require_once __DIR__ . '/../../helpers/report_no.php';

// ==========================================
// 1. HELPER FUNCTIONS
// ==========================================

function thaiDateFull($datetime) {
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    // Handle MM/DD/YYYY format which strtotime may fail on
    if ($ts === false && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $datetime, $m)) {
        // Try as MM/DD/YYYY
        $ts = mktime(0, 0, 0, (int)$m[1], (int)$m[2], (int)$m[3]);
    }
    if ($ts === false) return '';
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}

function thaiTime($datetime) {
    if (empty($datetime)) return '';
    if (strlen($datetime) <= 5) return $datetime;
    $ts = strtotime($datetime);
    return ($ts !== false) ? date('H:i', $ts) : $datetime;
}

function h($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

function getVal($arr, $key, $default = '') {
    if (!is_array($arr)) return $default;
    return isset($arr[$key]) && $arr[$key] !== null ? $arr[$key] : $default;
}

function renderCheckbox($condition) {
    return $condition ? '✓' : '';
}

function parseDateParts($dateStr) {
    if (empty($dateStr)) return ['day' => '', 'month' => '', 'year' => ''];
    $ts = strtotime($dateStr);
    // Handle MM/DD/YYYY format which strtotime may fail on
    if ($ts === false && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateStr, $m)) {
        $ts = mktime(0, 0, 0, (int)$m[1], (int)$m[2], (int)$m[3]);
    }
    if ($ts === false) return ['day' => '', 'month' => '', 'year' => ''];
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return [
        'day'   => date('j', $ts),
        'month' => $months[(int)date('n', $ts)] ?? '',
        'year'  => date('Y', $ts) + 543
    ];
}

$thaiNumbers = ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'];
function toThaiNum($num) {
    global $thaiNumbers;
    $str = (string)$num;
    $result = '';
    for ($i = 0; $i < strlen($str); $i++) {
        $ch = $str[$i];
        $result .= (is_numeric($ch) && isset($thaiNumbers[(int)$ch])) ? $thaiNumbers[(int)$ch] : $ch;
    }
    return $result;
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

// Use report data (fingerprint key)
$data = [];
if ($reportData && isset($reportData['fingerprint']) && is_array($reportData['fingerprint'])) {
    $data = $reportData['fingerprint'];
} elseif ($checklistData && is_array($checklistData)) {
    $data = $checklistData;
}

// ==========================================
// USER MAP
// ==========================================
$userMap = [];
try {
    $sqlUser = "SELECT t1.user_id,
                       CONCAT(IFNULL(t2.rank_name,''),' ',t1.first_name,' ',t1.last_name) AS fullname,
                       IFNULL(t3.position_name,'') AS position_name
                FROM user_profile t1
                LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                LEFT JOIN user_position t3 ON t1.position_id = t3.position_id";
    $stmtUser = $pdo->query($sqlUser);
    while ($u = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        $userMap[$u['user_id']] = $u;
    }
} catch (Exception $e) { /* ignore */ }

// ==========================================
// AGENCY INFO
// ==========================================
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
            $agencyName = 'นวท.(สบ ' . $rv . ') กสก.พฐก.';
        } elseif ($rt === 'spt') {
            $agencyName = 'ศพฐ ' . $rv;
        } elseif ($rt === 'ptjv') {
            $agencyName = 'พฐ.จว.' . ($provinceMap[strval($rv)] ?? $rv);
        }
    }
} catch (Exception $e) { /* ignore */ }

// Override agency from saved form data
if (!empty(getVal($data, 'rlf_agency_name'))) {
    $agencyName = getVal($data, 'rlf_agency_name');
}

// ==========================================
// REPORT NO & YEAR
// ==========================================
$ckGen = $checklistData['general_info'] ?? [];
$rawReportNo = getVal($ckGen, 'report_no', getVal($ckGen, 'document_no'));
if (empty($rawReportNo)) {
    try {
        $stmtRpt = $pdo->prepare("SELECT receiveNotiReportNo FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
        $stmtRpt->execute([$incident_id]);
        $rptRow = $stmtRpt->fetch(PDO::FETCH_ASSOC);
        if ($rptRow && !empty($rptRow['receiveNotiReportNo'])) {
            $rawReportNo = $rptRow['receiveNotiReportNo'];
        }
    } catch (Exception $e) { /* ignore */ }
}
$report_no = '';
$report_year_short = '';
if (!empty($rawReportNo) && strpos($rawReportNo, '/') !== false) {
    $parts = explode('/', $rawReportNo, 2);
    $report_no = $parts[0];
    $report_year_short = substr(trim($parts[1] ?? ''), -2);
} else {
    $report_no = $rawReportNo;
}
// Override from saved data
if (!empty(getVal($data, 'rlf_report_no_display_pdf'))) $report_no = getVal($data, 'rlf_report_no_display_pdf');
if (!empty(getVal($data, 'rlf_report_year_pdf'))) $report_year_short = getVal($data, 'rlf_report_year_pdf');
$report_no = smartThaiReportOrDoc($report_no);

// Fallback: ถ้ายังไม่มีปี ให้ใช้ปี พ.ศ. ปัจจุบัน 2 หลักท้าย
if (empty($report_year_short)) {
    $report_year_short = substr((string)(date('Y') + 543), -2);
}

// ==========================================
// SECTION 1 — การรับแจ้ง
// ==========================================
$receive_date = thaiDateFull(getVal($data, 'rlf_receive_date'));
$receive_time = getVal($data, 'rlf_receive_time');
$case_no = convertDocNoToThai(getVal($data, 'rlf_case_no'));
$police_station = getVal($data, 'rlf_police_station');
$letter_no = getVal($data, 'rlf_letter_no');
$letter_date = thaiDateFull(getVal($data, 'rlf_letter_date'));

// Evidence sender — resolve from user_id
$evidence_sender = '';
$evidence_sender_position = getVal($data, 'rlf_evidence_sender_position');
$senderId = getVal($data, 'rlf_evidence_sender');
if (!empty($senderId) && isset($userMap[$senderId])) {
    $evidence_sender = trim($userMap[$senderId]['fullname'] ?? '');
    if (empty($evidence_sender_position)) {
        $evidence_sender_position = $userMap[$senderId]['position_name'] ?? '';
    }
} elseif (!empty($senderId)) {
    $evidence_sender = $senderId;
}
$evidence_sender_phone = getVal($data, 'rlf_evidence_sender_phone');

$evidence_letter_no = getVal($data, 'rlf_evidence_letter_no');
$evidence_doc_no = getVal($data, 'rlf_evidence_doc_no');
$evidence_doc_date = thaiDateFull(getVal($data, 'rlf_evidence_doc_date'));

// Purpose checkboxes
$purposes = getVal($data, 'rlf_purpose[]', []);
if (is_string($purposes)) $purposes = [$purposes];
$chk_purpose_fingerprint = renderCheckbox(in_array('เพื่อตรวจเก็บรอยลายนิ้วมือแฝง', $purposes));
$chk_purpose_other = renderCheckbox(in_array('อื่นๆ', $purposes));
$purpose_other_text = getVal($data, 'rlf_purpose_other_text');

// ==========================================
// SECTION 2 — วันเวลาที่เกิดเหตุ/ทราบเหตุ
// ==========================================
$incident_date = thaiDateFull(getVal($data, 'rlf_incident_date'));
$incident_time = getVal($data, 'rlf_incident_time');
$known_date = thaiDateFull(getVal($data, 'rlf_known_date'));
$known_time = getVal($data, 'rlf_known_time');

// ==========================================
// SECTION 3 — วันเวลาที่ตรวจเก็บ
// ==========================================
$collect_date = thaiDateFull(getVal($data, 'rlf_collect_date'));
$collect_time = getVal($data, 'rlf_collect_time');

// ==========================================
// SECTION 4 — ผู้ตรวจพิสูจน์ (Inspector rows)
// ==========================================
$insNames = getVal($data, 'rlf_inspector_name[]', []);
$insPositions = getVal($data, 'rlf_inspector_position[]', []);
if (is_string($insNames)) $insNames = [$insNames];
if (is_string($insPositions)) $insPositions = [$insPositions];

$inspector_rows_html = '';
foreach ($insNames as $idx => $iname) {
    if (empty($iname)) continue;
    $no = $idx + 1;
    $iPos = is_array($insPositions) ? ($insPositions[$idx] ?? '') : $insPositions;
    $posText = !empty($iPos) ? '  ตำแหน่ง ' . h($iPos) : '';
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">4.' . $no . '.</span><span class="fd" style="margin-left:6px;">' . h($iname) . $posText . '</span></div>' . "\n";
}
// Fill empty rows
for ($i = count(array_filter($insNames)); $i < 2; $i++) {
    $no = $i + 1;
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">4.' . $no . '.</span><span class="fd" style="margin-left:6px;"></span><span class="fl" style="margin-left:20px;">ตำแหน่ง</span><span class="fd"></span></div>' . "\n";
}

// ==========================================
// SECTION 5 — ลักษณะการหีบห่อวัตถุพยาน
// ==========================================
$packageTypes = getVal($data, 'rlf_package_type[]', []);
if (is_string($packageTypes)) $packageTypes = [$packageTypes];
$chk_pkg_envelope = renderCheckbox(in_array('บรรจุในซองวัตถุพยาน', $packageTypes));
$chk_pkg_plastic = renderCheckbox(in_array('พลาสติก', $packageTypes));

$sealConditions = getVal($data, 'rlf_seal_condition[]', []);
if (is_string($sealConditions)) $sealConditions = [$sealConditions];
$chk_seal_signed = renderCheckbox(in_array('มีการปิดผนึกพร้อมลงลายมือชื่อกำกับ', $sealConditions));
$chk_seal_detail = renderCheckbox(in_array('เขียนรายละเอียดหน้าซองครบถ้วน', $sealConditions));

$collector_type = getVal($data, 'rlf_collector_type');
$chk_collector_forensic = renderCheckbox($collector_type === 'เก็บโดยเจ้าหน้าที่พิสูจน์หลักฐาน');
$chk_collector_investigator = renderCheckbox($collector_type === 'เก็บโดยพนักงานสอบสวน');
$chk_collector_other = renderCheckbox($collector_type === 'เก็บโดย อื่นๆ');
$collector_other_text = getVal($data, 'rlf_collector_other_text');

$storage_date = thaiDateFull(getVal($data, 'rlf_storage_date'));
$storage_time = getVal($data, 'rlf_storage_time');
$duration_year = getVal($data, 'rlf_duration_year');
$duration_month = getVal($data, 'rlf_duration_month');
$duration_day = getVal($data, 'rlf_duration_day');

// ==========================================
// SECTION 6 — ลักษณะวัตถุพยาน (Evidence rows)
// ==========================================
$labUnitMap = [
    'bio_dna'     => 'กลุ่มงานตรวจชีววิทยา',
    'chemical'    => 'กลุ่มงานตรวจทางเคมีฟิสิกส์',
    'fingerprint' => 'กลุ่มงานตรวจลายนิ้วมือแฝง',
    'drug'        => 'กลุ่มงานตรวจยาเสพติด',
    'gun'         => 'กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน',
    'document'    => 'กลุ่มงานตรวจเอกสาร',
    'digital'     => 'กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล',
    'computer'    => 'กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์',
];

$evTotal = getVal($data, 'rlf_evidence_total[]', []);
$evidenceTotalDisplay = '';
if (is_array($evTotal)) {
    $evidenceTotalDisplay = $evTotal[0] ?? '';
} else {
    $evidenceTotalDisplay = $evTotal;
}

$evDescriptions = getVal($data, 'rlf_ev_description[]', []);
$evWidths = getVal($data, 'rlf_ev_width[]', []);
$evLengths = getVal($data, 'rlf_ev_length[]', []);
$evHeights = getVal($data, 'rlf_ev_height[]', []);
$evQuantities = getVal($data, 'rlf_ev_quantity[]', []);
$evLabelNos = getVal($data, 'rlf_ev_label_no[]', []);
$evLabUnits = getVal($data, 'rlf_ev_lab_unit[]', []);

if (is_string($evDescriptions)) $evDescriptions = [$evDescriptions];
if (is_string($evWidths)) $evWidths = [$evWidths];
if (is_string($evLengths)) $evLengths = [$evLengths];
if (is_string($evHeights)) $evHeights = [$evHeights];
if (is_string($evQuantities)) $evQuantities = [$evQuantities];
if (is_string($evLabelNos)) $evLabelNos = [$evLabelNos];
if (is_string($evLabUnits)) $evLabUnits = [$evLabUnits];

$evidence_rows_html = '';
foreach ($evDescriptions as $idx => $desc) {
    if (empty($desc)) continue;
    $no = $idx + 1;
    $w = h($evWidths[$idx] ?? '');
    $l = h($evLengths[$idx] ?? '');
    $hg = h($evHeights[$idx] ?? '');
    $qty = h($evQuantities[$idx] ?? '');
    $label = h($evLabelNos[$idx] ?? '');
    $labKey = $evLabUnits[$idx] ?? '';
    $labText = labUnitsToText($labKey);

    $evidence_rows_html .= '<div class="fr i1"><span class="fl">รายการที่ <b>' . $no . '</b></span><span class="fl" style="margin-left:4px;">เป็น</span><span class="fd">' . h($desc) . '</span></div>' . "\n";

    $sizeParts = [];
    if (!empty($w)) $sizeParts[] = 'ก/ผคก. ' . $w . ' cm.';
    if (!empty($l)) $sizeParts[] = 'ย. ' . $l . ' cm.';
    if (!empty($hg)) $sizeParts[] = 'ส. ' . $hg . ' cm.';
    $sizeStr = implode(' ', $sizeParts);

    $detailParts = [];
    if (!empty($sizeStr)) $detailParts[] = 'ขนาด ' . $sizeStr;
    if (!empty($qty)) $detailParts[] = 'จำนวน ' . $qty;
    if (!empty($label)) $detailParts[] = 'ป้ายหมายเลข ' . $label;
    if (!empty($labText)) $detailParts[] = 'การตรวจพิสูจน์ ' . h($labText);

    if (!empty($detailParts)) {
        $evidence_rows_html .= '<div class="fr i2"><span class="fd-full">' . implode(' / ', $detailParts) . '</span></div>' . "\n";
    }
}
if (empty($evidence_rows_html)) {
    $evidence_rows_html .= '<div class="fr i1"><span class="fl">รายการที่ <b>1</b></span><span class="fl" style="margin-left:4px;">เป็น</span><span class="fd">&nbsp;</span></div>' . "\n";
    $evidence_rows_html .= '<div class="fr i2"><span class="fd-full">&nbsp;</span></div>' . "\n";
}

// ==========================================
// SECTION 7 — วิธีการดำเนินการ (Method rows)
// ==========================================
$methodNames = getVal($data, 'rlf_method_name[]', []);
$methodDetails = getVal($data, 'rlf_method_detail[]', []);
if (is_string($methodNames)) $methodNames = [$methodNames];
if (is_string($methodDetails)) $methodDetails = [$methodDetails];

$method_rows_html = '';
foreach ($methodNames as $idx => $mName) {
    if (empty($mName)) continue;
    $no = $idx + 1;
    $detail = h($methodDetails[$idx] ?? '');
    $detailStr = !empty($detail) ? ' — ' . $detail : '';
    $method_rows_html .= '<div class="fr i1"><span class="fl">7.' . $no . '.</span><span class="fd" style="margin-left:6px;">' . h($mName) . $detailStr . '</span></div>' . "\n";
}
if (empty($method_rows_html)) {
    $method_rows_html .= '<div class="fr i1"><span class="fl">7.1.</span><span class="fd" style="margin-left:6px;">&nbsp;</span></div>' . "\n";
}

// ==========================================
// SECTION 8 — การดำเนินการ
// ==========================================
$action_evidence_dest = getVal($data, 'rlf_action_evidence_dest');
if (is_string($action_evidence_dest)) $action_evidence_dest = [$action_evidence_dest];
if (!is_array($action_evidence_dest)) $action_evidence_dest = [];
$chk_action_gnf = renderCheckbox(in_array('กนฝ.', $action_evidence_dest));
$chk_action_evidence_other = renderCheckbox(in_array('อื่นๆ', $action_evidence_dest));
$action_evidence_other_text = getVal($data, 'rlf_action_evidence_other_text');

$action_exhibit = getVal($data, 'rlf_action_exhibit');
if (is_string($action_exhibit)) $action_exhibit = [$action_exhibit];
if (!is_array($action_exhibit)) $action_exhibit = [];
$chk_action_return_investigator = renderCheckbox(in_array('ส่งคืนพนักงานสอบสวน', $action_exhibit));
$action_return_station = getVal($data, 'rlf_action_return_station');

$forwardDepts = getVal($data, 'rlf_action_forward_dept[]', []);
if (is_string($forwardDepts)) $forwardDepts = [$forwardDepts];
$chk_fwd_gchw = renderCheckbox(in_array('กชว.', $forwardDepts));
$chk_fwd_gop = renderCheckbox(in_array('กอป.', $forwardDepts));
$chk_fwd_gos = renderCheckbox(in_array('กอส.', $forwardDepts));
$chk_fwd_gkm = renderCheckbox(in_array('กคม.', $forwardDepts));
$chk_fwd_gkp = renderCheckbox(in_array('กคพ.', $forwardDepts));
$chk_fwd_other = renderCheckbox(in_array('อื่นๆ', $forwardDepts));
$action_forward_other_text = getVal($data, 'rlf_action_forward_other_text');

// ==========================================
// SIGNER
// ==========================================
$signer_name = getVal($data, 'rlf_signer_name');
$signer_position = getVal($data, 'rlf_signer_position');

$signDate = getVal($data, 'rlf_sign_date');
$signDateParts = parseDateParts($signDate);

// ==========================================
// BUILD REPLACEMENTS
// ==========================================
$replacements = [
    '{{report_no}}'         => h($report_no),
    '{{report_year_short}}' => h($report_year_short),
    '{{agency_name}}'       => h(!empty($agencyName) ? $agencyName : ''),

    // Section 1
    '{{receive_date}}'              => h($receive_date),
    '{{receive_time}}'              => h($receive_time),
    '{{case_no}}'                   => h($case_no),
    '{{police_station}}'            => h($police_station),
    '{{letter_no}}'                 => h($letter_no),
    '{{letter_date}}'               => h($letter_date),
    '{{evidence_sender}}'           => h($evidence_sender),
    '{{evidence_sender_position}}'  => h($evidence_sender_position),
    '{{evidence_sender_phone}}'     => h($evidence_sender_phone),
    '{{evidence_letter_no}}'        => h($evidence_letter_no),
    '{{evidence_doc_no}}'           => h($evidence_doc_no),
    '{{evidence_doc_date}}'         => h($evidence_doc_date),
    '{{chk_purpose_fingerprint}}'   => $chk_purpose_fingerprint,
    '{{chk_purpose_other}}'         => $chk_purpose_other,
    '{{purpose_other_text}}'        => h($purpose_other_text),

    // Section 2
    '{{incident_date}}'  => h($incident_date),
    '{{incident_time}}'  => h($incident_time),
    '{{known_date}}'     => h($known_date),
    '{{known_time}}'     => h($known_time),

    // Section 3
    '{{collect_date}}'   => h($collect_date),
    '{{collect_time}}'   => h($collect_time),

    // Section 4
    '{{inspector_rows}}' => $inspector_rows_html,

    // Section 5
    '{{chk_pkg_envelope}}'          => $chk_pkg_envelope,
    '{{chk_pkg_plastic}}'           => $chk_pkg_plastic,
    '{{chk_seal_signed}}'           => $chk_seal_signed,
    '{{chk_seal_detail}}'           => $chk_seal_detail,
    '{{chk_collector_forensic}}'    => $chk_collector_forensic,
    '{{chk_collector_investigator}}'=> $chk_collector_investigator,
    '{{chk_collector_other}}'       => $chk_collector_other,
    '{{collector_other_text}}'      => h($collector_other_text),
    '{{storage_date}}'              => h($storage_date),
    '{{storage_time}}'              => h($storage_time),
    '{{duration_year}}'             => h($duration_year),
    '{{duration_month}}'            => h($duration_month),
    '{{duration_day}}'              => h($duration_day),

    // Section 6
    '{{evidence_total}}'  => h($evidenceTotalDisplay),
    '{{evidence_rows}}'   => $evidence_rows_html,

    // Section 7
    '{{method_rows}}'     => $method_rows_html,

    // Section 8
    '{{chk_action_gnf}}'               => $chk_action_gnf,
    '{{chk_action_evidence_other}}'    => $chk_action_evidence_other,
    '{{action_evidence_other_text}}'   => h($action_evidence_other_text),
    '{{chk_action_return_investigator}}'=> $chk_action_return_investigator,
    '{{action_return_station}}'        => h($action_return_station),
    '{{chk_fwd_gchw}}'    => $chk_fwd_gchw,
    '{{chk_fwd_gop}}'     => $chk_fwd_gop,
    '{{chk_fwd_gos}}'     => $chk_fwd_gos,
    '{{chk_fwd_gkm}}'     => $chk_fwd_gkm,
    '{{chk_fwd_gkp}}'     => $chk_fwd_gkp,
    '{{chk_fwd_other}}'   => $chk_fwd_other,
    '{{action_forward_other_text}}' => h($action_forward_other_text),

    // Signature
    '{{signer_name}}'     => h($signer_name),
    '{{signer_position}}' => h($signer_position),
    '{{sign_day}}'        => h($signDateParts['day']),
    '{{sign_month}}'      => h($signDateParts['month']),
    '{{sign_year}}'       => h($signDateParts['year']),
];

// ==========================================
// READ TEMPLATE AND REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_fingerprint_report_preview.html');
if ($htmlTemplate === false) {
    die("Error: ไม่สามารถอ่านไฟล์ template ได้");
}

$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// ==========================================
// EXPORT สำหรับ DOCX (ลายนิ้วมือแฝง)
// ==========================================
if (defined('WORD_DOCX_MODE') && WORD_DOCX_MODE) {
    $GLOBALS['fingerprint_report_export'] = [
        'replacements' => $replacements,
        'fingerprint' => $data ?? [],
    ];
    return;
}

// ==========================================
// OUTPUT HTML
// ==========================================
if (!defined('WORD_REPORT_CAPTURE')) {
    header('Content-Type: text/html; charset=utf-8');
}
echo $htmlContent;