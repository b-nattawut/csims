<?php
/**
 * gen_pdf_scene_evidence_html.php - Generate PDF for Scene Evidence Checklist
 * ตรวจเก็บวัตถุพยานที่เกิดเหตุ (complaints_type = '07')
 * ใช้ HTML Template เหมือนฟอร์มอื่นๆ (เพลิงไหม้/ทรัพย์/ชีวิต)
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../helpers/report_no.php';
require_once __DIR__ . '/lab_unit_helper.php';

// ==========================================
// 1. HELPER FUNCTIONS
// ==========================================

function thaiDate($dateStr)
{
    if (empty($dateStr)) return '';
    $timestamp = strtotime($dateStr);
    if ($timestamp === false) return $dateStr;
    $months = [null, 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $year = date('Y', $timestamp) + 543;
    return date('j', $timestamp) . ' ' . $months[date('n', $timestamp)] . ' ' . $year;
}

function thaiTime($timeStr)
{
    if (empty($timeStr)) return '';
    if (strlen($timeStr) <= 5) return $timeStr;
    $ts = strtotime($timeStr);
    return $ts !== false ? date('H:i', $ts) : $timeStr;
}

function renderCheckbox($condition)
{
    return $condition ? '✓' : '';
}

function getVal($arr, $key, $default = '')
{
    if (!is_array($arr)) return $default;
    return isset($arr[$key]) && $arr[$key] !== null ? $arr[$key] : $default;
}

// ฟังก์ชันตัดข้อความซ้ำออก (แก้ปัญหาข้อมูลถูกบันทึกซ้ำ)
function removeDuplicateText($text)
{
    if (empty($text)) return '';
    $text = trim($text);
    $len = mb_strlen($text, 'UTF-8');
    if ($len < 50) return $text;
    
    // ถ้ายาวเกิน 500 ตัว น่าจะมีปัญหาซ้ำ — ตัดเอา 150 ตัวแรก
    if ($len > 500) {
        // หาจุดที่เหมาะสมในการตัด (หลังคำว่า จ. หรือ อ. หรือ ต.)
        $cut = mb_substr($text, 0, 200, 'UTF-8');
        // หาตำแหน่งสุดท้ายของ จ.xxx หรือ จังหวัด
        if (preg_match('/^(.+(?:จ\.|จังหวัด)[^\s]{0,20})/u', $cut, $m)) {
            return trim($m[1]);
        }
        return $cut;
    }
    
    return $text;
}

function renderSignatureImg($sigData, $pdo = null)
{
    if (empty($sigData)) return '';
    // 1) BLOB จาก DB (ข้อมูลใหม่)
    if (is_array($sigData) && !empty($sigData['file_id']) && $pdo) {
        $base64 = blobToBase64FromDb($pdo, $sigData['file_id']);
        if (!empty($base64)) {
            return '<img src="' . $base64 . '" style="display:block; max-width:120px; max-height:38px; object-fit:contain; margin:0 auto;" alt="ลายเซ็น">';
        }
    }
    // 2) ไฟล์บนดิสก์ (ข้อมูลเก่า)
    if (is_array($sigData) && !empty($sigData['filename'])) {
        $path = __DIR__ . '/../../uploads/checklist_signatures/' . $sigData['filename'];
        if (file_exists($path)) {
            $bin = @file_get_contents($path);
            if ($bin !== false) {
                $mime = @mime_content_type($path) ?: 'image/png';
                $base64 = 'data:'.$mime.';base64,'.base64_encode($bin);
                return '<img src="' . $base64 . '" style="display:block; max-width:120px; max-height:38px; object-fit:contain; margin:0 auto;" alt="ลายเซ็น">';
            }
        }
    }
    return '';
}

function blobToBase64FromDb($pdo, $fileId)
{
    if (empty($fileId)) return '';
    try {
        $stmt = $pdo->prepare("SELECT file_name FROM incident_checklist_transaction_file WHERE id = ? LIMIT 1");
        $stmt->execute([$fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['file_name'])) return '';
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($row['file_name']);
        if (!$mime || $mime === 'application/octet-stream') $mime = 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($row['file_name']);
    } catch (Exception $e) {
        return '';
    }
}

function photoToDataUri($photo, $pdo = null)
{
    if (empty($photo)) return '';

    // 1) BLOB จาก DB (ข้อมูลใหม่)
    if (is_array($photo)) {
        $fileId = $photo['file_id'] ?? ($photo['id'] ?? '');
        if (!empty($fileId) && $pdo) {
            return blobToBase64FromDb($pdo, $fileId);
        }
    }

    // 2) ไฟล์บนดิสก์ (ข้อมูลเก่า)
    if (is_array($photo)) {
        $filename = $photo['filename'] ?? ($photo['file_name'] ?? '');
        if (!empty($filename)) {
            $candidates = [
                __DIR__ . '/../../uploads/checklist_photos/' . $filename,
                __DIR__ . '/../../uploads_2/checklist_photos/' . $filename,
            ];
            foreach ($candidates as $fp) {
                if (file_exists($fp) && is_file($fp)) {
                    $mime = @mime_content_type($fp) ?: 'image/jpeg';
                    $bin = @file_get_contents($fp);
                    if ($bin !== false) return 'data:'.$mime.';base64,'.base64_encode($bin);
                }
            }
        }
    }

    return '';
}

// ==========================================
// 2. FETCH DATA FROM DATABASE
// ==========================================
$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) die("Error: กรุณาระบุ incident_id");

try {
    $stmt = $pdo->prepare("SELECT incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) die("Error: ไม่พบข้อมูล Checklist สำหรับ incident_id: " . $incident_id);

    $data = json_decode($row['incident_checklist_data'], true);
    if (json_last_error() !== JSON_ERROR_NONE) $data = [];
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// QR CODE: ดึงเลขรับแจ้ง
// ==========================================
$qrData = '';
try {
    $stmtRn = $pdo->prepare("SELECT receiveNoti_No FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
    $stmtRn->execute([$incident_id]);
    $rnRow = $stmtRn->fetch(PDO::FETCH_ASSOC);
    if ($rnRow && !empty($rnRow['receiveNoti_No'])) {
        $qrData = $rnRow['receiveNoti_No'];
    }
} catch (Exception $e) {}

// ==========================================
// PREPARE USER MAP (ID => Fullname)
// ==========================================
$userMap = [];
$userPositionMap = [];
try {
    $sqlUser = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname, t3.position_name
                FROM user_profile t1
                LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                LEFT JOIN user_position t3 ON t1.position_id = t3.position_id";
    $stmtUser = $pdo->query($sqlUser);
    while ($u = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        $userMap[$u['user_id']] = $u['fullname'];
        $userPositionMap[$u['user_id']] = $u['position_name'] ?? '';
    }
} catch (Exception $e) {
    // ignore
}

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
$handover = $data['handover'] ?? [];
$inspectorList = $data['inspectors'] ?? [];
$signer = $data['signer'] ?? [];
$signatures = $data['signatures'] ?? [];
$photoRecords = $data['photo_records'] ?? [];
$evidences = $data['evidences'] ?? [];
$pdfForm = $data['pdf_form'] ?? [];

// ==========================================
// GENERAL INFO (Section 1)
// ==========================================
$doc_no = getVal($gen, 'doc_no');
if (empty($doc_no)) $doc_no = getVal($gen, 'case_doc_no');
$doc_no = convertDocNoToThai($doc_no);

$raw_report_no = getIncidentReportNoTH($pdo, $incident_id, getVal($gen, 'report_no'));
$report_no = '';
$report_year = '';
if (!empty($raw_report_no) && strpos($raw_report_no, '/') !== false) {
    $parts = explode('/', $raw_report_no);
    $report_no = $parts[0];
    $report_year = $parts[1] ?? '';
} else {
    $report_no = $raw_report_no;
    $report_year = getVal($pdfForm, 'report_year');
}
// แปลงตัวย่อเป็นภาษาไทย
$thaiPrefixMap = ['LC-' => 'ท-', 'MD-' => 'ช-', 'BM-' => 'ร-', 'FR-' => 'พ-', 'TF-' => 'จร-', 'EV-' => 'ต-', 'CM-' => 'ต-', 'PS-' => 'ต-'];
foreach ($thaiPrefixMap as $en => $th) {
    if (strpos($report_no, $en) === 0) {
        $report_no = $th . substr($report_no, strlen($en));
        break;
    }
}

$receive_date = thaiDate(getVal($gen, 'receive_date'));
$receive_time = getVal($gen, 'receive_time');

// ช่องทางการรับแจ้ง
$channel = getVal($gen, 'notify_method');
$notify_other_text = getVal($gen, 'notify_method_other_text');

if (is_array($channel)) {
    $chk_notify_phone = renderCheckbox(in_array('ทางโทรศัพท์', $channel) || in_array('phone', $channel));
    $chk_notify_radio = renderCheckbox(in_array('ทางวิทยุสื่อสาร', $channel) || in_array('radio', $channel));
    $chk_notify_letter = renderCheckbox(in_array('ทางหนังสือ', $channel) || in_array('letter', $channel) || in_array('document', $channel));
    $chk_notify_other = renderCheckbox(in_array('อื่นๆ', $channel) || in_array('other', $channel) || !empty(trim((string)$notify_other_text)));
} else {
    $chk_notify_phone = renderCheckbox($channel == 'ทางโทรศัพท์' || $channel == 'phone');
    $chk_notify_radio = renderCheckbox($channel == 'ทางวิทยุสื่อสาร' || $channel == 'radio');
    $chk_notify_letter = renderCheckbox($channel == 'ทางหนังสือ' || $channel == 'letter' || $channel == 'document');
    $chk_notify_other = renderCheckbox($channel == 'อื่นๆ' || $channel == 'other' || !empty(trim((string)$notify_other_text)));
}

$police_station = getVal($gen, 'police_station');
if (empty($police_station)) $police_station = getVal($gen, 'source_station');

$document_no = getVal($gen, 'document_no');
$raw_document_date = getVal($gen, 'document_date');
$document_date = !empty($raw_document_date) ? (strtotime($raw_document_date) ? thaiDate($raw_document_date) : $raw_document_date) : '';

$case_no = convertDocNoToThai(getVal($gen, 'case_no'));
$investigator_name = getVal($gen, 'investigator_name');

// ==========================================
// SCENE INFO (Section 2)
// ==========================================
$incident_location = getVal($gen, 'incident_location');
if (empty($incident_location)) $incident_location = getVal($gen, 'location_detail');
// ตัดข้อความซ้ำออก (แก้ปัญหาข้อมูลถูกบันทึกซ้ำ)
$incident_location = removeDuplicateText($incident_location);

$incident_date = thaiDate(getVal($gen, 'incident_date'));
$incident_time = getVal($gen, 'incident_time');

// ==========================================
// INSPECTION (Section 3)
// ==========================================
$inspect_location = getVal($inspection, 'location');
$inspect_date = thaiDate(getVal($inspection, 'date'));
$inspect_time = getVal($inspection, 'time');

// Fallback: try general_info
if (empty($inspect_date)) {
    $raw = getVal($gen, 'inspection_datetime');
    if (!empty($raw)) {
        $inspect_date = thaiDate($raw);
        $inspect_time = thaiTime($raw);
    }
}

// ==========================================
// INSPECTORS (Section 4)
// ==========================================
$inspector_rows_html = '';
if (is_array($inspectorList)) {
    foreach ($inspectorList as $idx => $insp) {
        $no = $idx + 1;
        $inspId = '';
        if (is_array($insp)) {
            $inspId = $insp['id'] ?? ($insp['user_id'] ?? '');
        } else {
            $inspId = $insp;
        }
        $inspName = isset($userMap[$inspId]) ? $userMap[$inspId] : (is_array($insp) ? getVal($insp, 'name') : '');
        $inspector_rows_html .= '<div class="si"><span class="si-no">4.' . $no . '.</span><span class="si-dots">' . htmlspecialchars($inspName) . '</span></div>' . "\n";
    }
}
// เติมแถวว่างให้ครบ 5
$filledInspectors = is_array($inspectorList) ? count($inspectorList) : 0;
for ($i = $filledInspectors; $i < 5; $i++) {
    $no = $i + 1;
    $inspector_rows_html .= '<div class="si"><span class="si-no">4.' . $no . '.</span><span class="si-dots"></span></div>' . "\n";
}

// ==========================================
// PURPOSE (Section 5)
// ==========================================
$purposeTypes = $purpose['types'] ?? [];
if (is_string($purposeTypes)) $purposeTypes = [$purposeTypes];
$purpose_detail = getVal($purpose, 'detail');

$chk_purpose_fingerprint = renderCheckbox(is_array($purposeTypes) && (in_array('fingerprint', $purposeTypes) || in_array('ลายนิ้วมือแฝง', $purposeTypes)));
$chk_purpose_dna = renderCheckbox(is_array($purposeTypes) && (in_array('dna', $purposeTypes) || in_array('สารพันธุกรรม', $purposeTypes)));
$chk_purpose_other = renderCheckbox(
    (is_array($purposeTypes) && (
        in_array('other', $purposeTypes) ||
        in_array('อื่นๆ', $purposeTypes) ||
        in_array('วัตถุพยานอื่นๆ', $purposeTypes)
    )) ||
    !empty(trim((string)$purpose_detail))
);

// ==========================================
// EVIDENCE ITEMS (Section 6)
// ==========================================
$evidence_item_rows = '';
if (!empty($evidenceItems) && is_array($evidenceItems)) {
    foreach ($evidenceItems as $idx => $item) {
        $no = $idx + 1;
        $desc = htmlspecialchars(getVal($item, 'description'));
        $evidence_item_rows .= '<div class="si"><span class="si-no">' . $no . '.</span><span class="si-dots">' . $desc . '</span></div>' . "\n";
    }
}
// เติมแถวว่าง
$filledEvidenceItems = is_array($evidenceItems) ? count($evidenceItems) : 0;
for ($i = $filledEvidenceItems; $i < 8; $i++) {
    $no = $i + 1;
    $evidence_item_rows .= '<div class="si"><span class="si-no">' . $no . '.</span><span class="si-dots"></span></div>' . "\n";
}

// ==========================================
// EXHIBIT DESCRIPTIONS (Section 7)
// ==========================================
$exhibit_desc_rows = '';
if (!empty($exhibitDescs) && is_array($exhibitDescs)) {
    foreach ($exhibitDescs as $idx => $desc) {
        $no = $idx + 1;
        $text = htmlspecialchars(getVal($desc, 'description'));
        $exhibit_desc_rows .= '<div class="si"><span class="si-no">' . $no . '.</span><span class="si-dots">' . $text . '</span></div>' . "\n";
    }
}
$filledExhibits = is_array($exhibitDescs) ? count($exhibitDescs) : 0;
for ($i = $filledExhibits; $i < 5; $i++) {
    $no = $i + 1;
    $exhibit_desc_rows .= '<div class="si"><span class="si-no">' . $no . '.</span><span class="si-dots"></span></div>' . "\n";
}

// ==========================================
// COLLECTED EVIDENCE (Section 8)
// ==========================================
$collectTypes = $collectedEvidence['types'] ?? [];
if (is_string($collectTypes)) $collectTypes = [$collectTypes];

$chk_collect_fingerprint = renderCheckbox(is_array($collectTypes) && (in_array('fingerprint', $collectTypes) || in_array('รอยลายนิ้วมือแฝง', $collectTypes)));
$chk_collect_palm = renderCheckbox(is_array($collectTypes) && (in_array('palm', $collectTypes) || in_array('ฝ่ามือแฝง', $collectTypes)));
$chk_collect_foot = renderCheckbox(is_array($collectTypes) && (in_array('foot', $collectTypes) || in_array('ฝ่าเท้าแฝง', $collectTypes)));

$collect_sheet_count = getVal($collectedEvidence, 'sheet_count');
$collect_location = getVal($collectedEvidence, 'location');

$collect_detail_rows = '';
$collectDetails = $collectedEvidence['details'] ?? [];
if (!empty($collectDetails) && is_array($collectDetails)) {
    foreach ($collectDetails as $idx => $d) {
        $no = $idx + 1;
        $text = htmlspecialchars(is_string($d) ? $d : getVal($d, 'description'));
        $collect_detail_rows .= '<div class="si"><span class="si-no">' . $no . '.</span><span class="si-dots">' . $text . '</span></div>' . "\n";
    }
}
$filledCollectDetails = is_array($collectDetails) ? count($collectDetails) : 0;
for ($i = $filledCollectDetails; $i < 3; $i++) {
    $no = $i + 1;
    $collect_detail_rows .= '<div class="si"><span class="si-no">' . $no . '.</span><span class="si-dots"></span></div>' . "\n";
}

// ==========================================
// EVIDENCE HANDLING (Section 9)
// ==========================================
$witness_name = getVal($evidenceHandling, 'witness_name');
$handover_method = getVal($evidenceHandling, 'handover_method');
$handover_method_checks = getVal($evidenceHandling, 'handover_method_checks', []);
if (is_string($handover_method_checks)) $handover_method_checks = [$handover_method_checks];
$chk_handover_submit = renderCheckbox(in_array('นำส่ง', $handover_method_checks) || $handover_method === 'นำส่ง');
$chk_handover_transfer = renderCheckbox(in_array('ส่งมอบ', $handover_method_checks) || $handover_method === 'ส่งมอบ');
$handover_method_detail = getVal($evidenceHandling, 'handover_method_detail');
if (empty($handover_method_detail)) $handover_method_detail = $handover_method;
$handover_item_ref = getVal($evidenceHandling, 'handover_item_ref');
$handover_to = getVal($evidenceHandling, 'handover_to');
$handover_purpose = getVal($evidenceHandling, 'handover_purpose');

// ==========================================
// EVIDENCE TABLE (Section 10)
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

$evidence_table_rows = '';
$totalTableRows = 15;
$filledRows = 0;

if (!empty($evidences) && is_array($evidences)) {
    $collectedLabUnits = $collectedEvidence['lab_units'] ?? [];
    foreach ($evidences as $i => $ev) {
        $num = $i + 1;
        $evDetail = htmlspecialchars(getVal($ev, 'detail'));
        if (empty($evDetail)) $evDetail = htmlspecialchars(getVal($ev, 'item'));
        $labUnit = getVal($ev, 'lab_unit');
        if (empty($labUnit) && isset($collectedLabUnits[$i])) {
            $labUnit = $collectedLabUnits[$i];
        }
        $labUnitText = labUnitsToText($labUnit);
        if ($labUnitText === '') $labUnitText = '';

        $evidence_table_rows .= '<tr>';
        $evidence_table_rows .= '<td>' . $num . '</td>';
        $evidence_table_rows .= '<td class="td-left">' . $evDetail . '</td>';
        $evidence_table_rows .= '<td>' . htmlspecialchars($labUnitText) . '</td>';
        $evidence_table_rows .= '</tr>';
        $filledRows++;
    }
}
for ($i = $filledRows; $i < $totalTableRows; $i++) {
    $evidence_table_rows .= '<tr><td style="height:22px;"></td><td></td><td></td></tr>';
}

// ==========================================
// HANDOVER / SIGNATURES (Section 11)
// ==========================================
$inspection_end_date = thaiDate(getVal($handover, 'inspection_end_date'));
$inspection_end_time = getVal($handover, 'inspection_end_time');

// ผู้รับมอบ
$recId = getVal($handover, 'receiver_name');
if (empty($recId)) $recId = getVal($handover, 'receiver_id');
$receiver_name = isset($userMap[$recId]) ? $userMap[$recId] : $recId;
$receiver_position = getVal($handover, 'receiver_position');
if (empty($receiver_position)) $receiver_position = getVal($handover, 'receiver_pos');
if (empty($receiver_position) && isset($userPositionMap[$recId])) {
    $receiver_position = $userPositionMap[$recId];
}

// ผู้ส่งมอบ
$senId = getVal($handover, 'sender_name');
if (empty($senId)) $senId = getVal($handover, 'deliverer_id');
$sender_name = isset($userMap[$senId]) ? $userMap[$senId] : $senId;
$sender_position = getVal($handover, 'sender_position');
if (empty($sender_position)) $sender_position = getVal($handover, 'deliverer_pos');
if (empty($sender_position) && isset($userPositionMap[$senId])) {
    $sender_position = $userPositionMap[$senId];
}

// ==========================================
// SIGNATURES
// ==========================================
// Receiver signature
$recv_sig = $signatures['receiver_sig'] ?? $signatures['receiver_signature'] ?? '';
$receiver_signature_img = renderSignatureImg($recv_sig, $pdo);

// Sender/Deliverer signature
$send_sig = $signatures['deliverer_sig'] ?? $signatures['sender_signature'] ?? $signatures['sender_sig'] ?? '';
$sender_signature_img = renderSignatureImg($send_sig, $pdo);

// ==========================================
// PHOTOS (Page 3)
// ==========================================
$photo_id_start = getVal($photoRecords, 'start');
$photo_id_end = getVal($photoRecords, 'end');
$photo_amount = getVal($photoRecords, 'amount');

$photo_inspect_date = $inspect_date;
$photo_inspect_time = $inspect_time;

// สร้าง Photo images HTML
$photos = $data['photos'] ?? [];
$photo_images = '';
$photo_extra_pages = '';

$validPhotos = [];
if (!empty($photos)) {
    foreach ($photos as $photo) {
        $pSrc = photoToDataUri($photo, $pdo);
        if (!empty($pSrc) && strpos($pSrc, 'data:image') !== false) {
            $validPhotos[] = $pSrc;
        }
    }
}

if (!empty($validPhotos)) {
    foreach ($validPhotos as $pIdx => $pBase64) {
        $photoNum = $pIdx + 1;
        $photoHtml = '<div style="flex:1; display:flex; align-items:center; justify-content:center;">';
        $photoHtml .= '<div style="text-align:center;">';
        $photoHtml .= '<img src="' . $pBase64 . '" style="max-width:100%; max-height:850px; object-fit:contain; border:1px solid #ccc;" alt="ภาพที่ ' . $photoNum . '">';
        $photoHtml .= '<div style="font-size:11px; color:#333; margin-top:4px;">ภาพที่ ' . $photoNum . ' / ' . count($validPhotos) . '</div>';
        $photoHtml .= '</div>';
        $photoHtml .= '</div>';

        if ($pIdx === 0) {
            $photo_images = $photoHtml;
        } else {
            $pageNum = 3 + $pIdx;
            $photo_extra_pages .= '
<div class="page" style="display:flex; flex-direction:column;">
    <div class="form-header">
        <div class="header-logo">
            <img src="../../images/office-of-police-forensic-icon.jpg" alt="ตราสำนักงานตำรวจแห่งชาติ">
        </div>
        <div class="header-center">
            <div class="title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="title-sub">การตรวจเก็บวัตถุพยานที่เกิดเหตุ</div>
            <div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ (ต่อ)</div>
        </div>
        <div class="header-qr">
            <div class="doc-box">
                <div class="doc-line">เลขรับที่/เลขรายงาน <span style="display:inline-block;min-width:60px;border-bottom:1px dotted #888;text-align:center;">' . htmlspecialchars($report_no) . '</span> / <span style="display:inline-block;min-width:40px;border-bottom:1px dotted #888;text-align:center;">' . htmlspecialchars($report_year) . '</span></div>
                <div class="doc-line">หน้าที่ ' . $pageNum . ' / {{total_pages}}</div>
            </div>
        </div>
        <div class="header-qr-code" id="qr_page' . $pageNum . '"></div>
    </div>
    ' . $photoHtml . '
    <div class="form-footer" style="margin-top:auto;">
        <div class="form-footer-left">
            <strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ
        </div>
        <div class="form-footer-right">
            F-CS-11 แก้ไขครั้งที่ 1<br>
            เริ่มใช้ 1 ส.ค. 60
        </div>
    </div>
</div>';
        }
    }
}

if (empty($photo_images)) {
    $photo_images = '<div style="flex:1; display:flex; align-items:center; justify-content:center;"><div style="color:#999; font-style:italic;">ไม่มีภาพถ่าย</div></div>';
}

// ==========================================
// 4. READ HTML TEMPLATE & REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_scene_evidence_preview.html');

$replacements = [
    '{{report_no}}' => htmlspecialchars($report_no),
    '{{report_year}}' => htmlspecialchars($report_year),
    '{{qr_data}}' => htmlspecialchars($qrData),

    // Section 1 - การรับแจ้งเหตุ
    '{{doc_no}}' => htmlspecialchars($doc_no),
    '{{receive_date}}' => htmlspecialchars($receive_date),
    '{{receive_time}}' => htmlspecialchars($receive_time),
    '{{chk_notify_phone}}' => $chk_notify_phone,
    '{{chk_notify_radio}}' => $chk_notify_radio,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_other}}' => $chk_notify_other,
    '{{notify_other_text}}' => htmlspecialchars($notify_other_text),
    '{{police_station}}' => htmlspecialchars($police_station),
    '{{document_no}}' => htmlspecialchars($document_no),
    '{{document_date}}' => htmlspecialchars($document_date),
    '{{case_no}}' => htmlspecialchars($case_no),
    '{{investigator_name}}' => htmlspecialchars($investigator_name),

    // Section 2 - สถานที่เกิดเหตุ
    '{{incident_location}}' => htmlspecialchars($incident_location),
    '{{incident_date}}' => htmlspecialchars($incident_date),
    '{{incident_time}}' => htmlspecialchars($incident_time),

    // Section 3 - วันเวลาที่ตรวจ
    '{{inspect_location}}' => htmlspecialchars($inspect_location),
    '{{inspect_date}}' => htmlspecialchars($inspect_date),
    '{{inspect_time}}' => htmlspecialchars($inspect_time),

    // Section 4 - ผู้ตรวจ
    '{{inspector_rows}}' => $inspector_rows_html,

    // Section 5 - วัตถุประสงค์
    '{{chk_purpose_fingerprint}}' => $chk_purpose_fingerprint,
    '{{chk_purpose_dna}}' => $chk_purpose_dna,
    '{{chk_purpose_other}}' => $chk_purpose_other,
    '{{purpose_detail}}' => htmlspecialchars($purpose_detail),

    // Section 6 - วัตถุพยานที่ตรวจพบ
    '{{evidence_item_rows}}' => $evidence_item_rows,

    // Section 7 - รายละเอียดของกลาง
    '{{exhibit_desc_rows}}' => $exhibit_desc_rows,

    // Section 8 - การตรวจเก็บวัตถุพยาน
    '{{chk_collect_fingerprint}}' => $chk_collect_fingerprint,
    '{{chk_collect_palm}}' => $chk_collect_palm,
    '{{chk_collect_foot}}' => $chk_collect_foot,
    '{{collect_sheet_count}}' => htmlspecialchars($collect_sheet_count),
    '{{collect_location}}' => htmlspecialchars($collect_location),
    '{{collect_detail_rows}}' => $collect_detail_rows,

    // Section 9 - การส่งมอบวัตถุพยาน
    '{{witness_name}}' => htmlspecialchars($witness_name),
    '{{chk_handover_submit}}' => $chk_handover_submit,
    '{{chk_handover_transfer}}' => $chk_handover_transfer,
    '{{handover_method_detail}}' => htmlspecialchars($handover_method_detail),
    '{{handover_item_ref}}' => htmlspecialchars($handover_item_ref),
    '{{handover_to}}' => htmlspecialchars($handover_to),
    '{{handover_purpose}}' => htmlspecialchars($handover_purpose),

    // Section 10 - ตารางวัตถุพยาน
    '{{evidence_table_rows}}' => $evidence_table_rows,

    // Section 11 - การส่งมอบคืนสถานที่เกิดเหตุ
    '{{inspection_end_date}}' => htmlspecialchars($inspection_end_date),
    '{{inspection_end_time}}' => htmlspecialchars($inspection_end_time),
    '{{receiver_name}}' => htmlspecialchars($receiver_name),
    '{{receiver_position}}' => htmlspecialchars($receiver_position),
    '{{sender_name}}' => htmlspecialchars($sender_name),
    '{{sender_position}}' => htmlspecialchars($sender_position),
    '{{receiver_signature_img}}' => $receiver_signature_img,
    '{{sender_signature_img}}' => $sender_signature_img,

    // Photos (Page 3)
    '{{photo_inspect_date}}' => htmlspecialchars($photo_inspect_date),
    '{{photo_inspect_time}}' => htmlspecialchars($photo_inspect_time),
    '{{photo_id_start}}' => htmlspecialchars($photo_id_start),
    '{{photo_id_end}}' => htmlspecialchars($photo_id_end),
    '{{photo_amount}}' => htmlspecialchars($photo_amount),
    '{{photo_images}}' => $photo_images,
];

// แทนที่ค่าใน HTML
$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// เพิ่มหน้ารูปภาพเพิ่มเติม
if (!empty($photo_extra_pages)) {
    $htmlContent = str_replace('</body>', $photo_extra_pages . "\n</body>", $htmlContent);
}

// คำนวณจำนวนหน้ารวม: 3 หน้าพื้นฐาน + หน้ารูปเพิ่ม
$extraPhotoPages = (count($validPhotos) > 1) ? count($validPhotos) - 1 : 0;
$total_pages = 3 + $extraPhotoPages;
$htmlContent = str_replace('{{total_pages}}', $total_pages, $htmlContent);

// ==========================================
// 5. OUTPUT HTML
// ==========================================
header('Content-Type: text/html; charset=utf-8');
echo $htmlContent;
