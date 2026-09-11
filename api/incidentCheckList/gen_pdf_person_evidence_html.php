<?php
/**
 * gen_pdf_person_evidence_html.php - Generate PDF for Person Evidence Checklist
 * ตรวจเก็บวัตถุพยานที่บุคคล (complaints_type = '08')
 * ใช้ HTML Template: form_person_evidence_preview.html
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

function thaiDate($dateStr) {
    if (empty($dateStr)) return '';
    $timestamp = strtotime($dateStr);
    if ($timestamp === false) return $dateStr;
    $months = [null,'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $year = date('Y', $timestamp) + 543;
    return date('j', $timestamp) . ' ' . $months[date('n', $timestamp)] . ' ' . $year;
}

function thaiTime($timeStr) {
    if (empty($timeStr)) return '';
    if (strlen($timeStr) <= 5) return $timeStr;
    $ts = strtotime($timeStr);
    return $ts !== false ? date('H:i', $ts) : $timeStr;
}

function renderCheckbox($cond) { return $cond ? '✓' : ''; }

function getVal($arr, $key, $default = '') {
    if (!is_array($arr)) return $default;
    return isset($arr[$key]) && $arr[$key] !== null ? $arr[$key] : $default;
}

function h($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

function renderSignatureImg($sigData, $pdo = null) {
    if (empty($sigData)) return '';
    // 1) BLOB จาก DB (ข้อมูลใหม่)
    if (is_array($sigData) && !empty($sigData['file_id']) && $pdo) {
        $b64 = blobToBase64FromDb($pdo, $sigData['file_id']);
        if (!empty($b64)) return '<img src="'.$b64.'" style="display:block;max-width:120px;max-height:38px;object-fit:contain;margin:0 auto;" alt="sig">';
    }
    // 2) ไฟล์บนดิสก์ (ข้อมูลเก่า)
    if (is_array($sigData) && !empty($sigData['filename'])) {
        $path = __DIR__ . '/../../uploads/checklist_signatures/' . $sigData['filename'];
        if (file_exists($path)) {
            $bin = @file_get_contents($path);
            if ($bin !== false) {
                $mime = @mime_content_type($path) ?: 'image/png';
                $b64 = 'data:'.$mime.';base64,'.base64_encode($bin);
                return '<img src="'.$b64.'" style="display:block;max-width:120px;max-height:38px;object-fit:contain;margin:0 auto;" alt="sig">';
            }
        }
    }
    return '';
}

function blobToBase64FromDb($pdo, $fileId) {
    if (empty($fileId)) return '';
    try {
        $stmt = $pdo->prepare("SELECT file_name FROM incident_checklist_transaction_file WHERE id = ? LIMIT 1");
        $stmt->execute([$fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['file_name'])) return '';
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($row['file_name']);
        if (!$mime || $mime === 'application/octet-stream') $mime = 'image/png';
        return 'data:'.$mime.';base64,'.base64_encode($row['file_name']);
    } catch (Exception $e) { return ''; }
}

function photoToDataUri($photo, $pdo = null) {
    if (empty($photo)) return '';
    // 1) BLOB จาก DB (ข้อมูลใหม่)
    if (is_array($photo)) {
        $fileId = $photo['file_id'] ?? ($photo['id'] ?? '');
        if (!empty($fileId) && $pdo) return blobToBase64FromDb($pdo, $fileId);
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
} catch (PDOException $e) { die("Database Error: " . $e->getMessage()); }

// ==========================================
// QR CODE: ดึงเลขรับแจ้ง
// ==========================================
$qrData = '';
try {
    $stmtRn = $pdo->prepare("SELECT receiveNoti_No FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
    $stmtRn->execute([$incident_id]);
    $rnRow = $stmtRn->fetch(PDO::FETCH_ASSOC);
    if ($rnRow && !empty($rnRow['receiveNoti_No'])) $qrData = $rnRow['receiveNoti_No'];
} catch (Exception $e) {}

// ==========================================
// PREPARE USER MAP
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
} catch (Exception $e) {}

// ==========================================
// 3. EXTRACT DATA
// ==========================================
$gen = $data['general_info'] ?? [];
$purpose = $data['purpose'] ?? [];
$inspection = $data['inspection'] ?? [];
$persons = $data['persons'] ?? [];
$personInfo = $data['person_info'] ?? [];
$evidences = $data['evidences'] ?? [];
$evidenceHandling = $data['evidence_handling'] ?? [];
$handover = $data['handover'] ?? [];
$inspectorList = $data['inspectors'] ?? [];
$signatures = $data['signatures'] ?? [];
$photoRecords = $data['photo_records'] ?? [];
$pdfForm = $data['pdf_form'] ?? [];

// ==========================================
// GENERAL INFO (Section 1)
// ==========================================
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
$thaiPrefixMap = ['LC-'=>'ท-','MD-'=>'ช-','BM-'=>'ร-','FR-'=>'พ-','TF-'=>'จร-','EV-'=>'ต-','CM-'=>'ต-','PS-'=>'ต-'];
foreach ($thaiPrefixMap as $en => $th) {
    if (strpos($report_no, $en) === 0) { $report_no = $th . substr($report_no, strlen($en)); break; }
}

$receive_date = thaiDate(getVal($gen, 'receive_date'));
$receive_time = getVal($gen, 'receive_time');
$unit_name = getVal($gen, 'unit_name');

// Unit type checkboxes
$unitTypeChecks = $pdfForm['unit_type_checks'] ?? (getVal($gen, 'unit_type') ? [getVal($gen, 'unit_type')] : []);
if (is_string($unitTypeChecks)) $unitTypeChecks = [$unitTypeChecks];
$chk_unit_central = renderCheckbox(in_array('กองพิสูจน์หลักฐานกลาง', $unitTypeChecks));
$chk_unit_center = renderCheckbox(in_array('ศูนย์พิสูจน์หลักฐาน', $unitTypeChecks));
$chk_unit_province = renderCheckbox(in_array('พิสูจน์หลักฐานจังหวัด', $unitTypeChecks));

// Notify method
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
$incident_location = getVal($gen, 'incident_location');
if (empty($incident_location)) $incident_location = getVal($gen, 'location_detail');
$incident_date = thaiDate(getVal($gen, 'incident_date'));
$incident_time = getVal($gen, 'incident_time');
$investigator_name = getVal($gen, 'investigator_name');
$send_request = getVal($gen, 'send_request');

// ==========================================
// PERSON LIST (Section 1 - รายการบุคคล)
// ==========================================
$person_item_rows = '';
if (!empty($persons) && is_array($persons)) {
    foreach ($persons as $idx => $p) {
        $no = '1.' . ($idx + 1);
        $prefix = h(getVal($p, 'prefix'));
        $name = h(getVal($p, 'name'));
        $display = trim($prefix . ' ' . $name);
        $person_item_rows .= '<div class="si i1"><span class="si-no">'.$no.'</span><span class="si-dots">'.$display.'</span></div>'."\n";
    }
}
$filledPersons = is_array($persons) ? count($persons) : 0;
for ($i = $filledPersons; $i < 3; $i++) {
    $no = '1.' . ($i + 1);
    $person_item_rows .= '<div class="si i1"><span class="si-no">'.$no.'</span><span class="si-dots"></span></div>'."\n";
}

// ==========================================
// INSPECTION (Section 3)
// ==========================================
$inspect_location = getVal($inspection, 'location');
$inspect_date = thaiDate(getVal($inspection, 'date'));
$inspect_time = getVal($inspection, 'time');
if (empty($inspect_date)) {
    $raw = getVal($gen, 'inspection_datetime');
    if (!empty($raw)) { $inspect_date = thaiDate($raw); $inspect_time = thaiTime($raw); }
}

// ==========================================
// PURPOSE (Section 2)
// ==========================================
$purpose_detail = getVal($purpose, 'detail');

// ==========================================
// PERSON INFO (Section 3.1)
// ==========================================
$person_info_rows = '';
if (!empty($personInfo) && is_array($personInfo)) {
    foreach ($personInfo as $idx => $pi) {
        $no = $idx + 1;
        $prefix = h(getVal($pi, 'prefix'));
        $fullname = h(getVal($pi, 'fullname'));
        $display_name = trim($prefix . ' ' . $fullname);
        $id_card = h(getVal($pi, 'id_card'));
        $passport = h(getVal($pi, 'passport'));
        $height = h(getVal($pi, 'height'));
        $age = h(getVal($pi, 'age'));
        $skin = h(getVal($pi, 'skin'));
        $hand = h(getVal($pi, 'hand'));
        $feature = h(getVal($pi, 'feature'));

        $person_info_rows .= '<div class="person-card">'."\n";
        $person_info_rows .= '  <div class="pc-header">3.1.'.$no.' บุคคลที่ '.$no.'</div>'."\n";
        $person_info_rows .= '  <div class="pc-row"><span class="pc-fd">'.$display_name.'</span></div>'."\n";
        $person_info_rows .= '  <div class="pc-row"><span class="pc-fl">บัตรปชช.</span><span class="pc-fd" style="max-width:140px;">'.$id_card.'</span><span class="pc-fl">หนังสือเดินทาง</span><span class="pc-fd">'.$passport.'</span></div>'."\n";
        $person_info_rows .= '  <div class="pc-row"><span class="pc-fl">สูง</span><span class="pc-fd-s">'.$height.'</span><span class="pc-fl">ซม.</span><span class="pc-fl">อายุ</span><span class="pc-fd-s">'.$age.'</span><span class="pc-fl">ปี</span><span class="pc-fl">สีผิว</span><span class="pc-fd" style="max-width:60px;">'.$skin.'</span><span class="pc-fl">มือถนัด</span><span class="pc-fd-s">'.$hand.'</span></div>'."\n";
        $person_info_rows .= '  <div class="pc-row"><span class="pc-fl">คำหนี้รูปพรรณ</span><span class="pc-fd">'.$feature.'</span></div>'."\n";
        $person_info_rows .= '</div>'."\n";
    }
}
if (empty($person_info_rows)) {
    $person_info_rows = '<div class="person-card"><div class="pc-header">3.1.1 บุคคลที่ 1</div><div class="pc-row"><span class="pc-fd"></span></div><div class="pc-row"><span class="pc-fl">บัตรปชช.</span><span class="pc-fd" style="max-width:140px;"></span><span class="pc-fl">หนังสือเดินทาง</span><span class="pc-fd"></span></div><div class="pc-row"><span class="pc-fl">สูง</span><span class="pc-fd-s"></span><span class="pc-fl">ซม.</span><span class="pc-fl">อายุ</span><span class="pc-fd-s"></span><span class="pc-fl">ปี</span><span class="pc-fl">สีผิว</span><span class="pc-fd" style="max-width:60px;"></span><span class="pc-fl">มือถนัด</span><span class="pc-fd-s"></span></div><div class="pc-row"><span class="pc-fl">คำหนี้รูปพรรณ</span><span class="pc-fd"></span></div></div>'."\n";
}

// ==========================================
// EVIDENCE DETAILS (Section 3.2)
// ==========================================
$labUnitMap = [
    'bio_dna'=>'กลุ่มงานตรวจชีววิทยา','chemical'=>'กลุ่มงานตรวจทางเคมีฟิสิกส์',
    'fingerprint'=>'กลุ่มงานตรวจลายนิ้วมือแฝง','drug'=>'กลุ่มงานตรวจยาเสพติด',
    'gun'=>'กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน','document'=>'กลุ่มงานตรวจเอกสาร',
    'digital'=>'กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล',
    'computer'=>'กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์',
];

$evidence_detail_rows = '';
if (!empty($evidences) && is_array($evidences)) {
    foreach ($evidences as $idx => $ev) {
        $no = '3.2.' . ($idx + 1);
        $detail = h(getVal($ev, 'detail'));
        if (empty($detail)) $detail = h(getVal($ev, 'item'));
        $labUnit = getVal($ev, 'lab_unit');
        $labText = labUnitsToText($labUnit);
        $qty = h(getVal($ev, 'qty'));

        $evidence_detail_rows .= '<div class="ev-detail i1">'."\n";
        $evidence_detail_rows .= '  <div class="ev-line1"><span class="si-no">'.$no.'</span><span class="ev-fd">'.$detail.'</span></div>'."\n";
        $evidence_detail_rows .= '  <div class="ev-line2"><span class="fl" style="font-size:10px;">กลุ่มตรวจ</span><span class="ev-fd" style="font-size:10px;">'.h($labText).'</span><span class="fl" style="font-size:10px;">จำนวน</span><span class="ev-fd-s">'.$qty.'</span></div>'."\n";
        $evidence_detail_rows .= '</div>'."\n";
    }
}
$filledEv = is_array($evidences) ? count($evidences) : 0;
for ($i = $filledEv; $i < 3; $i++) {
    $no = '3.2.' . ($i + 1);
    $evidence_detail_rows .= '<div class="ev-detail i1"><div class="ev-line1"><span class="si-no">'.$no.'</span><span class="ev-fd"></span></div><div class="ev-line2"><span class="fl" style="font-size:10px;">กลุ่มตรวจ</span><span class="ev-fd" style="font-size:10px;"></span><span class="fl" style="font-size:10px;">จำนวน</span><span class="ev-fd-s"></span></div></div>'."\n";
}

// ==========================================
// EVIDENCE HANDLING (Section 3.3)
// ==========================================
$witness_name = getVal($evidenceHandling, 'witness_name');
$witness_form = getVal($evidenceHandling, 'witness_form');
$witness_detail = getVal($evidenceHandling, 'witness_detail');
$handover_method_checks = getVal($evidenceHandling, 'handover_method_checks', []);
if (is_string($handover_method_checks)) $handover_method_checks = [$handover_method_checks];
$chk_handover_submit = renderCheckbox(in_array('นำส่ง', $handover_method_checks));
$chk_handover_transfer = renderCheckbox(in_array('ส่งมอบ', $handover_method_checks));
$handover_method_detail = getVal($evidenceHandling, 'handover_method_detail');
$handover_item_ref = getVal($evidenceHandling, 'handover_item_ref');
$handover_to = getVal($evidenceHandling, 'handover_to');
$handover_purpose = getVal($evidenceHandling, 'handover_purpose');

// Build purpose lines array
$purpose_lines_raw = getVal($evidenceHandling, 'purpose_lines', []);
if (is_string($purpose_lines_raw)) $purpose_lines_raw = [$purpose_lines_raw];
$purposeFull = getVal($evidenceHandling, 'purpose_full');
if (!empty($purposeFull)) $handover_purpose = $purposeFull;

// Collect all non-empty text lines (handover_purpose อาจมีหลายบรรทัดจาก textarea)
$allPurposeTexts = [];
if (!empty($handover_purpose)) {
    foreach (preg_split('/\r?\n/', $handover_purpose) as $line) {
        $t = trim((string)$line);
        if ($t !== '') $allPurposeTexts[] = $t;
    }
}
if (!empty($purpose_lines_raw) && is_array($purpose_lines_raw)) {
    foreach ($purpose_lines_raw as $line) {
        $t = trim((string)$line);
        if (!empty($t)) $allPurposeTexts[] = $t;
    }
}

// Generate dotted-line rows: data rows first, then empty rows to fill at least 4 lines
$handover_purpose_lines = '';
$minLines = 4;
$totalLines = max($minLines, count($allPurposeTexts));
for ($i = 0; $i < $totalLines; $i++) {
    $text = isset($allPurposeTexts[$i]) ? h($allPurposeTexts[$i]) : '';
    $handover_purpose_lines .= '<div class="fr i1"><span class="fd-full">' . $text . '</span></div>' . "\n";
}

// ==========================================
// INSPECTORS (Section 4)
// ==========================================
$inspector_rows = '';
if (is_array($inspectorList)) {
    foreach ($inspectorList as $idx => $insp) {
        $no = $idx + 1;
        $inspId = is_array($insp) ? ($insp['id'] ?? ($insp['user_id'] ?? '')) : $insp;
        $inspName = isset($userMap[$inspId]) ? $userMap[$inspId] : (is_array($insp) ? getVal($insp, 'name') : '');
        $inspector_rows .= '<div class="si"><span class="si-no">4.'.$no.'.</span><span class="si-dots">'.h($inspName).'</span></div>'."\n";
    }
}
$filledInsp = is_array($inspectorList) ? count($inspectorList) : 0;
for ($i = $filledInsp; $i < 5; $i++) {
    $inspector_rows .= '<div class="si"><span class="si-no">4.'.($i+1).'.</span><span class="si-dots"></span></div>'."\n";
}

// ==========================================
// SIGNATURES
// ==========================================
$recv_sig = $signatures['receiver_sig'] ?? $signatures['receiver_signature'] ?? '';
$receiver_signature_img = renderSignatureImg($recv_sig, $pdo);
$send_sig = $signatures['deliverer_sig'] ?? $signatures['sender_signature'] ?? $signatures['sender_sig'] ?? '';
$sender_signature_img = renderSignatureImg($send_sig, $pdo);

// Receiver / Sender names
$recId = getVal($handover, 'receiver_id');
$receiver_name = isset($userMap[$recId]) ? $userMap[$recId] : $recId;
$receiver_position = getVal($handover, 'receiver_pos');
if (empty($receiver_position) && isset($userPositionMap[$recId])) $receiver_position = $userPositionMap[$recId];

$senId = getVal($handover, 'deliverer_id');
$sender_name = isset($userMap[$senId]) ? $userMap[$senId] : $senId;
$sender_position = getVal($handover, 'deliverer_pos');
if (empty($sender_position) && isset($userPositionMap[$senId])) $sender_position = $userPositionMap[$senId];

$sign_date = thaiDate(getVal($handover, 'inspection_end_date'));

// ==========================================
// PHOTOS (Page 3)
// ==========================================
$photo_id_start = getVal($photoRecords, 'start');
$photo_id_end = getVal($photoRecords, 'end');
$photo_amount = getVal($photoRecords, 'amount');

$photos = $data['photos'] ?? [];
$photo_images = '';
$photo_extra_pages = '';
$validPhotos = [];
if (!empty($photos)) {
    foreach ($photos as $photo) {
        $pSrc = photoToDataUri($photo, $pdo);
        if (!empty($pSrc) && strpos($pSrc, 'data:image') !== false) $validPhotos[] = $pSrc;
    }
}

if (!empty($validPhotos)) {
    foreach ($validPhotos as $pIdx => $pBase64) {
        $photoNum = $pIdx + 1;
        $photoHtml = '<div style="flex:1; display:flex; align-items:center; justify-content:center;">';
        $photoHtml .= '<div style="text-align:center;">';
        $photoHtml .= '<img src="'.$pBase64.'" style="max-width:100%; max-height:850px; object-fit:contain; border:1px solid #ccc;" alt="ภาพที่ '.$photoNum.'">';
        $photoHtml .= '<div style="font-size:11px; color:#333; margin-top:4px;">ภาพที่ '.$photoNum.' / '.count($validPhotos).'</div>';
        $photoHtml .= '</div></div>';

        if ($pIdx === 0) {
            $photo_images = $photoHtml;
        } else {
            $pageNum = 3 + $pIdx;
            $photo_extra_pages .= '
<div class="page" style="display:flex; flex-direction:column;">
    <div class="form-header">
        <div class="header-logo"><img src="../../images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="header-center">
            <div class="title-main">รายงานการตรวจเก็บวัตถุพยาน</div>
            <div class="title-sub">บันทึกการถ่ายภาพ (ต่อ)</div>
        </div>
        <div class="header-qr">
            <div class="doc-box">
                <div class="doc-line">รายงานที่ <span style="display:inline-block;min-width:60px;border-bottom:1px dotted #888;text-align:center;">'.h($report_no).'</span> / 25<span style="display:inline-block;min-width:40px;border-bottom:1px dotted #888;text-align:center;">'.h($report_year).'</span></div>
                <div class="doc-line">หน้าที่ '.$pageNum.' / {{total_pages}}</div>
            </div>
        </div>
        <div class="header-qr-code" id="qr_page'.$pageNum.'"></div>
    </div>
    '.$photoHtml.'
    <div class="form-footer" style="margin-top:auto;">
        <div class="form-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="form-footer-right">บัญชีแนบท้ายคำสั่ง สพฐ.ตร.<br>ที่ 848/2561</div>
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
$htmlTemplate = file_get_contents(__DIR__ . '/form_person_evidence_preview.html');

$replacements = [
    '{{report_no}}' => h($report_no),
    '{{report_year}}' => h($report_year),
    '{{qr_data}}' => h($qrData),

    // Section 1
    '{{receive_date}}' => h($receive_date),
    '{{receive_time}}' => h($receive_time),
    '{{unit_name}}' => h($unit_name),
    '{{chk_unit_central}}' => $chk_unit_central,
    '{{chk_unit_center}}' => $chk_unit_center,
    '{{chk_unit_province}}' => $chk_unit_province,
    '{{chk_notify_phone}}' => $chk_notify_phone,
    '{{chk_notify_radio}}' => $chk_notify_radio,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_other}}' => $chk_notify_other,
    '{{notify_other_text}}' => h($notify_other_text),
    '{{police_station}}' => h($police_station),
    '{{document_no}}' => h($document_no),
    '{{document_date}}' => h($document_date),
    '{{case_no}}' => h($case_no),
    '{{incident_location}}' => h($incident_location),
    '{{incident_date}}' => h($incident_date),
    '{{incident_time}}' => h($incident_time),
    '{{investigator_name}}' => h($investigator_name),
    '{{send_request}}' => h($send_request),
    '{{person_item_rows}}' => $person_item_rows,

    // Section 2
    '{{purpose_detail}}' => h($purpose_detail),

    // Section 3
    '{{inspect_location}}' => h($inspect_location),
    '{{inspect_date}}' => h($inspect_date),
    '{{inspect_time}}' => h($inspect_time),
    '{{person_info_rows}}' => $person_info_rows,
    '{{evidence_detail_rows}}' => $evidence_detail_rows,

    // Section 3.3
    '{{witness_name}}' => h($witness_name),
    '{{witness_form}}' => h($witness_form),
    '{{witness_detail}}' => h($witness_detail),
    '{{chk_handover_submit}}' => $chk_handover_submit,
    '{{chk_handover_transfer}}' => $chk_handover_transfer,
    '{{handover_method_detail}}' => h($handover_method_detail),
    '{{handover_item_ref}}' => h($handover_item_ref),
    '{{handover_to}}' => h($handover_to),
    '{{handover_purpose_lines}}' => $handover_purpose_lines,

    // Section 4
    '{{inspector_rows}}' => $inspector_rows,
    '{{receiver_signature_img}}' => $receiver_signature_img,
    '{{receiver_name}}' => h($receiver_name),
    '{{receiver_position}}' => h($receiver_position),
    '{{sender_signature_img}}' => $sender_signature_img,
    '{{sender_name}}' => h($sender_name),
    '{{sender_position}}' => h($sender_position),
    '{{sign_date}}' => h($sign_date),

    // Photos
    '{{photo_id_start}}' => h($photo_id_start),
    '{{photo_id_end}}' => h($photo_id_end),
    '{{photo_amount}}' => h($photo_amount),
    '{{photo_images}}' => $photo_images,
];

$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// เพิ่มหน้ารูปภาพเพิ่มเติม
if (!empty($photo_extra_pages)) {
    $htmlContent = str_replace('</body>', $photo_extra_pages . "\n</body>", $htmlContent);
}

// คำนวณจำนวนหน้ารวม
$extraPhotoPages = (count($validPhotos) > 1) ? count($validPhotos) - 1 : 0;
$total_pages = 3 + $extraPhotoPages;
$htmlContent = str_replace('{{total_pages}}', $total_pages, $htmlContent);

// ==========================================
// 5. OUTPUT HTML
// ==========================================
header('Content-Type: text/html; charset=utf-8');
echo $htmlContent;
