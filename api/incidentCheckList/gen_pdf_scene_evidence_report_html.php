<?php
/**
 * gen_pdf_scene_evidence_report_html.php
 * Generate PDF รายงานการตรวจเก็บวัตถุพยานที่เกิดเหตุ (ร่างรายงาน)
 *
 * รับ parameter: incident_id (GET)
 * ดึงข้อมูลจาก: incident_checklist_transaction.incident_report_data (JSON - top level)
 * ใช้ template: form_scene_evidence_report_preview.html + str_replace pattern
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
    return date('j',$ts).' '.$months[(int)date('n',$ts)].' '.(date('Y',$ts)+543);
}

function thaiDate($dateStr) {
    if (empty($dateStr)) return '';
    $ts = strtotime($dateStr);
    if ($ts === false) return $dateStr;
    $months = [null,'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    return date('j',$ts).' '.$months[date('n',$ts)].' '.(date('Y',$ts)+543);
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

// ฟังก์ชันตัดข้อความซ้ำออก (แก้ปัญหาข้อมูลถูกบันทึกซ้ำ)
function removeDuplicateText($text) {
    if (empty($text)) return '';
    $text = trim($text);
    $len = mb_strlen($text, 'UTF-8');
    if ($len < 50) return $text;
    
    // ถ้ายาวเกิน 500 ตัว น่าจะมีปัญหาซ้ำ — ตัดเอา 150 ตัวแรก
    if ($len > 500) {
        $cut = mb_substr($text, 0, 200, 'UTF-8');
        if (preg_match('/^(.+(?:จ\.|จังหวัด)[^\s]{0,20})/u', $cut, $m)) {
            return trim($m[1]);
        }
        return $cut;
    }
    
    return $text;
}

function renderCheckbox($condition) {
    return $condition ? '✓' : '';
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

// Use report data or fallback to checklist data (both have same structure for scene evidence)
$data = $reportData ?: ($checklistData ?: []);

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
$agencyCenterName = '';
$agencyProvinceName = '';
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
            $agencyCenterName = $agencyName;
        } elseif ($rt === 'ptjv') {
            $agencyName = 'พฐ.จว.' . ($provinceMap[strval($rv)] ?? $rv);
            $agencyProvinceName = $agencyName;
        }
    }
} catch (Exception $e) { /* ignore */ }

// ==========================================
// 3. EXTRACT DATA
// ==========================================
$gen = $data['general_info'] ?? [];
$purpose = $data['purpose'] ?? [];
$inspection = $data['inspection'] ?? [];
$evidenceItems = $data['evidence_items'] ?? [];
$exhibitDescs = $data['exhibit_descriptions'] ?? [];
$collectedEvidence = $data['collected_evidence'] ?? [];
$evidenceHandling = $data['evidence_handling'] ?? [];
$signer = $data['signer'] ?? [];
$pdfForm = $data['pdf_form'] ?? [];

// ==========================================
// REPORT NO & YEAR
// ==========================================
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
$report_year_short = '';
if (!empty($rawReportNo) && strpos($rawReportNo, '/') !== false) {
    $parts = explode('/', $rawReportNo, 2);
    $report_no = $parts[0];
    $report_year_short = substr(trim($parts[1] ?? ''), -2);
} else {
    $report_no = $rawReportNo;
}
// Override from pdf_form if present
if (!empty(getVal($pdfForm, 'report_ref'))) $report_no = getVal($pdfForm, 'report_ref');
if (!empty(getVal($pdfForm, 'report_year'))) $report_year_short = getVal($pdfForm, 'report_year');
if (!empty(getVal($pdfForm, 'report_ref_2'))) $report_no = getVal($pdfForm, 'report_ref_2');
// แปลงเป็นภาษาไทยเสมอ (รองรับทั้งเลขรายงานและเลขเอกสารเต็ม) — idempotent
$report_no = smartThaiReportOrDoc($report_no);

// ==========================================
// GENERAL INFO (Section 1)
// ==========================================
$receive_date = thaiDateFull(getVal($gen, 'receive_date'));
$receive_time = getVal($gen, 'receive_time');

// Notify method checkboxes
// ★ ข้อ 7: บางเคสข้อมูลถูกบันทึกไว้ใน report_channel (รหัสอังกฤษ) จึงต้อง fallback ด้วย
$channel = getVal($gen, 'notify_method');
if ($channel === '' || $channel === null || $channel === []) {
    $channel = $gen['report_channel'] ?? [];
}

// ★ ข้อ 7: ให้ "วิธีการรับแจ้ง" ออกมาเป็นข้อความไทย ไม่ใช่ช่องติ๊กเปล่า
require_once __DIR__ . '/notify_method_helper.php';
$notify_method_text = notifyMethodToText(
    $channel,
    (string)getVal($gen, 'notify_method_other_text', '')
);

if (is_array($channel)) {
    $chk_notify_letter = renderCheckbox(in_array('ทางหนังสือ', $channel) || in_array('ตามหนังสือ', $channel) || in_array('letter', $channel) || in_array('document', $channel));
    $chk_notify_phone = renderCheckbox(in_array('ทางโทรศัพท์', $channel) || in_array('phone', $channel));
    $chk_notify_radio = renderCheckbox(in_array('ทางวิทยุสื่อสาร', $channel) || in_array('radio', $channel));
    $chk_notify_other = renderCheckbox(in_array('อื่นๆ', $channel) || in_array('other', $channel));
} else {
    $chk_notify_letter = renderCheckbox($channel == 'ทางหนังสือ' || $channel == 'ตามหนังสือ' || $channel == 'letter');
    $chk_notify_phone = renderCheckbox($channel == 'ทางโทรศัพท์' || $channel == 'phone');
    $chk_notify_radio = renderCheckbox($channel == 'ทางวิทยุสื่อสาร' || $channel == 'radio');
    $chk_notify_other = renderCheckbox($channel == 'อื่นๆ' || $channel == 'other');
}

$police_station = getVal($gen, 'police_station');
if (empty($police_station)) $police_station = getVal($gen, 'source_station');

$document_no = getVal($gen, 'document_no');
$raw_doc_date = getVal($gen, 'document_date');
$document_date = !empty($raw_doc_date) ? (strtotime($raw_doc_date) ? thaiDateFull($raw_doc_date) : $raw_doc_date) : '';

$incident_location = getVal($gen, 'incident_location');
if (empty($incident_location)) $incident_location = getVal($gen, 'location_detail');
// ตัดข้อความซ้ำออก (แก้ปัญหาข้อมูลถูกบันทึกซ้ำ)
$incident_location = removeDuplicateText($incident_location);

$incident_date = thaiDateFull(getVal($gen, 'incident_date'));
$incident_time = getVal($gen, 'incident_time');
$investigator_name = getVal($gen, 'investigator_name');

$center_name = getVal($pdfForm, 'center_name');
if (empty($center_name)) $center_name = $agencyCenterName;

$province_name = getVal($pdfForm, 'province_name');
if (empty($province_name)) $province_name = $agencyProvinceName;

// ==========================================
// EVIDENCE ITEM ROWS (Section 1 - รายการของกลาง)
// ==========================================
$evidence_item_rows = '';
if (!empty($evidenceItems) && is_array($evidenceItems)) {
    foreach ($evidenceItems as $idx => $item) {
        $no = $idx + 1;
        $desc = h(is_string($item) ? $item : getVal($item, 'description'));
        $evidence_item_rows .= '<div class="fr i2"><span class="fl">' . $no . '.</span><span class="fd" style="margin-left:4px;">' . $desc . '</span></div>' . "\n";
    }
}
if (empty($evidence_item_rows)) {
    for ($i = 1; $i <= 3; $i++) {
        $evidence_item_rows .= '<div class="fr i2"><span class="fl">' . $i . '.</span><span class="fd" style="margin-left:4px;">&nbsp;</span></div>' . "\n";
    }
}

// ==========================================
// PURPOSE (Section 2)
// ==========================================
$purposeTypes = $purpose['types'] ?? [];
if (is_string($purposeTypes)) $purposeTypes = [$purposeTypes];

$chk_purpose_fingerprint = renderCheckbox(is_array($purposeTypes) && (in_array('fingerprint', $purposeTypes) || in_array('รอยลายนิ้วมือแฝง', $purposeTypes)));
$chk_purpose_dna = renderCheckbox(is_array($purposeTypes) && (in_array('dna', $purposeTypes) || in_array('สารพันธุกรรม', $purposeTypes)));
$chk_purpose_other = renderCheckbox(is_array($purposeTypes) && (in_array('other', $purposeTypes) || in_array('วัตถุพยานอื่นๆ', $purposeTypes) || in_array('อื่นๆ', $purposeTypes)));
$purpose_detail = getVal($purpose, 'detail');

// ==========================================
// INSPECTION (Section 3)
// ==========================================
$inspect_location = getVal($inspection, 'location');
$inspect_date = thaiDateFull(getVal($inspection, 'date'));
$inspect_time = getVal($inspection, 'time');

if (empty($inspect_date)) {
    $raw = getVal($gen, 'inspection_datetime');
    if (!empty($raw)) {
        $inspect_date = thaiDateFull($raw);
        $inspect_time = thaiTime($raw);
    }
}

// ==========================================
// EXHIBIT DESCRIPTIONS (Section 3.1)
// ==========================================
$exhibit_desc_rows = '';
if (!empty($exhibitDescs) && is_array($exhibitDescs)) {
    foreach ($exhibitDescs as $idx => $desc) {
        $no = $idx + 1;
        $text = h(is_string($desc) ? $desc : getVal($desc, 'description'));
        $exhibit_desc_rows .= '<div class="fr i2"><span class="fl">' . $no . '.</span><span class="fd" style="margin-left:4px;">' . $text . '</span></div>' . "\n";
    }
}
if (empty($exhibit_desc_rows)) {
    for ($i = 1; $i <= 3; $i++) {
        $exhibit_desc_rows .= '<div class="fr i2"><span class="fl">' . $i . '.</span><span class="fd" style="margin-left:4px;">&nbsp;</span></div>' . "\n";
    }
}

// ==========================================
// COLLECTED EVIDENCE (Section 3.2)
// ==========================================
$collectTypes = $collectedEvidence['types'] ?? [];
if (is_string($collectTypes)) $collectTypes = [$collectTypes];

$chk_collect_fingerprint = renderCheckbox(is_array($collectTypes) && (in_array('fingerprint', $collectTypes) || in_array('รอยลายนิ้วมือแฝง', $collectTypes)));
$chk_collect_palm = renderCheckbox(is_array($collectTypes) && (in_array('palm', $collectTypes) || in_array('ฝ่ามือแฝง', $collectTypes)));
$chk_collect_foot = renderCheckbox(is_array($collectTypes) && (in_array('foot', $collectTypes) || in_array('ฝ่าเท้าแฝง', $collectTypes)));
$collect_sheet_count = getVal($collectedEvidence, 'sheet_count');

$collect_detail_rows = '';
$collectDetails = $collectedEvidence['details'] ?? [];
if (!empty($collectDetails) && is_array($collectDetails)) {
    foreach ($collectDetails as $idx => $d) {
        $no = $idx + 1;
        $rawText = is_string($d) ? $d : getVal($d, 'description');
        $text = h(removeDuplicateText($rawText));
        $collect_detail_rows .= '<div class="fr i2" style="flex-wrap:wrap; align-items:flex-start;"><span class="fl">' . $no . '.</span><span class="fd-multiline" style="margin-left:4px; flex:1; font-size:14px !important;">' . $text . '</span></div>' . "\n";
    }
}
if (empty($collect_detail_rows)) {
    for ($i = 1; $i <= 2; $i++) {
        $collect_detail_rows .= '<div class="fr i2"><span class="fl">' . $i . '.</span><span class="fd" style="margin-left:4px;">&nbsp;</span></div>' . "\n";
    }
}

$other_evidence_text = getVal($collectedEvidence, 'other_evidence_text');
$other_evidence_text = removeDuplicateText($other_evidence_text);

// ==========================================
// EVIDENCE HANDLING (Section 3.3)
// ==========================================
$witness_name = getVal($evidenceHandling, 'witness_name');
$witness_name = removeDuplicateText($witness_name);
$witness_form = getVal($evidenceHandling, 'witness_form');
$witness_detail = getVal($evidenceHandling, 'witness_detail');

$handover_method_checks = getVal($evidenceHandling, 'handover_method_checks', []);
if (is_string($handover_method_checks)) $handover_method_checks = [$handover_method_checks];
$handover_method = getVal($evidenceHandling, 'handover_method');
$chk_handover_submit = renderCheckbox(in_array('นำส่ง', $handover_method_checks) || $handover_method === 'นำส่ง');
$chk_handover_transfer = renderCheckbox(in_array('ส่งมอบ', $handover_method_checks) || $handover_method === 'ส่งมอบ');

$handover_item_ref = getVal($evidenceHandling, 'handover_item_ref');
$handover_to = getVal($evidenceHandling, 'handover_to');
$handover_purpose = getVal($evidenceHandling, 'handover_purpose');

// ==========================================
// SIGNER
// ==========================================
$signerId = getVal($signer, 'id');
$signer_name = getVal($signer, 'fullname');
if (empty($signer_name)) $signer_name = getVal($signer, 'name');
if (empty($signer_name) && !empty($signerId) && isset($userMap[$signerId])) {
    $signer_name = $userMap[$signerId]['fullname'] ?? '';
}
$signer_position = getVal($signer, 'position');
if (empty($signer_position) && !empty($signerId) && isset($userMap[$signerId])) {
    $signer_position = $userMap[$signerId]['position_name'] ?? '';
}

// ==========================================
// 4. BUILD REPLACEMENTS
// ==========================================
$replacements = [
    '{{report_no}}'         => h($report_no),
    '{{report_year_short}}' => h($report_year_short),
    '{{total_pages}}'       => '2',
    '{{agency_name}}'       => h(!empty($agencyName) ? $agencyName : getVal($gen, 'unit_name')),

    // Section 1 - การรับแจ้งเหตุ
    '{{receive_date}}'      => h($receive_date),
    '{{receive_time}}'      => h($receive_time),
    '{{center_name}}'       => h($center_name),
    '{{province_name}}'     => h($province_name),
    '{{notify_method_text}}' => $notify_method_text,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_phone}}'  => $chk_notify_phone,
    '{{chk_notify_radio}}'  => $chk_notify_radio,
    '{{chk_notify_other}}'  => $chk_notify_other,
    '{{police_station}}'    => h($police_station),
    '{{document_no}}'       => h($document_no),
    '{{document_date}}'     => h($document_date),
    '{{incident_location}}' => h($incident_location),
    '{{incident_date}}'     => h($incident_date),
    '{{incident_time}}'     => h($incident_time),
    '{{investigator_name}}' => h($investigator_name),
    '{{evidence_item_rows}}'=> $evidence_item_rows,

    // Section 2 - จุดประสงค์
    '{{chk_purpose_fingerprint}}' => $chk_purpose_fingerprint,
    '{{chk_purpose_dna}}'         => $chk_purpose_dna,
    '{{chk_purpose_other}}'       => $chk_purpose_other,
    '{{purpose_detail}}'          => h($purpose_detail),

    // Section 3 - ผลการตรวจ
    '{{inspect_location}}'  => h($inspect_location),
    '{{inspect_date}}'      => h($inspect_date),
    '{{inspect_time}}'      => h($inspect_time),

    // Section 3.1 - ลักษณะของกลาง
    '{{exhibit_desc_rows}}' => $exhibit_desc_rows,

    // Section 3.2.1 - ตรวจเก็บ
    '{{chk_collect_fingerprint}}' => $chk_collect_fingerprint,
    '{{chk_collect_palm}}'        => $chk_collect_palm,
    '{{chk_collect_foot}}'        => $chk_collect_foot,
    '{{collect_sheet_count}}'     => h($collect_sheet_count),
    '{{collect_detail_rows}}'     => $collect_detail_rows,

    // Section 3.2.2 - วัตถุพยานอื่น
    '{{other_evidence_text}}'     => h($other_evidence_text),

    // Section 3.3 - การดำเนินการเกี่ยวกับวัตถุพยาน
    '{{witness_name}}'           => h($witness_name),
    '{{witness_form}}'           => h($witness_form),
    '{{witness_detail}}'         => h($witness_detail),
    '{{chk_handover_submit}}'    => $chk_handover_submit,
    '{{chk_handover_transfer}}'  => $chk_handover_transfer,
    '{{handover_item_ref}}'      => h($handover_item_ref),
    '{{handover_to}}'            => h($handover_to),
    '{{handover_purpose}}'       => h($handover_purpose),

    // Signature
    '{{signer_name}}'     => h($signer_name),
    '{{signer_position}}' => h($signer_position),
];

// ==========================================
// 5. READ TEMPLATE AND REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_scene_evidence_report_preview.html');
if ($htmlTemplate === false) {
    die("Error: ไม่สามารถอ่านไฟล์ template ได้");
}

$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// ==========================================
// EXPORT สำหรับ DOCX (วัตถุพยานที่เกิดเหตุ)
// ==========================================
if (defined('WORD_DOCX_MODE') && WORD_DOCX_MODE) {
    $GLOBALS['scene_evidence_report_export'] = [
        'replacements' => $replacements,
    ];
    return;
}

// ==========================================
// 6. OUTPUT HTML
// ==========================================
if (!defined('WORD_REPORT_CAPTURE')) {
    header('Content-Type: text/html; charset=utf-8');
}
echo $htmlContent;
