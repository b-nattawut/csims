<?php
/**
 * gen_pdf_report_bomb_html.php - Generate PDF F-CS-11 (แบบการตรวจเก็บและส่งมอบวัตถุพยาน) ใช้กับประเภทคดี (ระเบิด)
 *
 * รับ parameter: incident_id (GET)
 * ดึงข้อมูลจาก: incident_checklist_transaction.incident_checklist_data (JSON)
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/lab_unit_helper.php';

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

function renderSignatureImg($sigData, $pdo = null)
{
    // ★ ถ้าเป็น array ว่าง หรือ null → return ว่าง
    if ($sigData === null || $sigData === '' || (is_array($sigData) && count($sigData) === 0)) return '';
    $base64 = '';
    
    // ★ ถ้าเป็น file_id → ดึง BLOB จาก DB แล้วแปลงเป็น base64
    if (is_array($sigData) && !empty($sigData['file_id']) && $pdo) {
        try {
            // ★ column ชื่อ file_name (เก็บ BLOB data)
            $stmt = $pdo->prepare("SELECT file_name FROM incident_checklist_transaction_file WHERE id = ?");
            $stmt->execute([$sigData['file_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['file_name'])) {
                $base64 = 'data:image/png;base64,' . base64_encode($row['file_name']);
            }
        } catch (Exception $e) {
            // ignore
        }
    } elseif (is_array($sigData) && !empty($sigData['base64'])) {
        $base64 = $sigData['base64'];
    } elseif (is_string($sigData)) {
        $base64 = $sigData;
    }
    
    if (empty($base64) || strpos($base64, 'data:image') === false) return '';
    return '<img src="' . $base64 . '" class="sig-img" alt="ลายเซ็น">';
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
// PREPARE USER MAP (ID => Fullname)
// ==========================================
$userMap = [];
try {
    $sqlUser = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname
                FROM user_profile t1
                LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id";
    $stmtUser = $pdo->query($sqlUser);
    while ($u = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        $userMap[$u['user_id']] = $u['fullname'];
    }
} catch (Exception $e) {
    // ignore
}

// ดึงข้อมูลผู้อนุมัติจาก rn_ReceiveNotiApprove (seqSignature = 3)
$approverName = '';
$approverPosition = '';
try {
    $stmtApprover = $pdo->prepare("SELECT fullName, positionName FROM rn_ReceiveNotiApprove WHERE ComplaintsID = ? AND seqSignature = 3");
    $stmtApprover->execute([$incident_id]);
    $approverData = $stmtApprover->fetch(PDO::FETCH_ASSOC);
    if ($approverData) {
        $approverName = $approverData['fullName'] ?? '';
        $approverPosition = $approverData['positionName'] ?? '';
    }
} catch (Exception $e) {
    // ignore
}

// ==========================================
// 3. EXTRACT DATA
// ==========================================
$gen       = $data['general_info'] ?? [];
$scene     = $data['scene_info'] ?? [];
$inspect   = $data['inspection_results'] ?? [];
$handover  = $data['handover'] ?? [];

// === Report No ===
$reportNo = getVal($gen, 'report_no');
// แปลงตัวย่อเป็นภาษาไทย สำหรับ PDF เท่านั้น
$thaiPrefixMap = ['LC-' => 'ทพ ', 'MD-' => 'ชว ', 'BM-' => 'รบ '];
foreach ($thaiPrefixMap as $en => $th) {
    if (strpos($reportNo, $en) === 0) {
        $reportNo = $th . substr($reportNo, strlen($en));
        break;
    }
}

// === Source Station ===
$sourceStation = getVal($gen, 'source_station');

// === Report Datetime ===
$cDate = getVal($gen, 'case_date');
$cTime = getVal($gen, 'case_time');
// $reportDate = !empty($cDate) ? thaiDateFull($cDate) : '';
$reportDate = parseDateParts(getVal($gen, 'case_date'));
$reportTime = !empty($cTime) ? $cTime : '';

// === Notification Channel ===
$channel = getVal($gen, 'report_channel');
$channelOtherText = getVal($gen, 'report_channel_other');

if (is_array($channel)) {
    $chk_phone    = renderCheckbox(in_array('phone', $channel) || in_array('ทางโทรศัพท์', $channel));
    $chk_document = renderCheckbox(in_array('document', $channel) || in_array('letter', $channel) || in_array('หนังสือนำส่ง', $channel) || in_array('ทางหนังสือ', $channel));
    $chk_radio    = renderCheckbox(in_array('radio', $channel) || in_array('ทางวิทยุ', $channel) || in_array('ทางวิทยุสื่อสาร', $channel));
    $chk_other    = renderCheckbox(in_array('other', $channel) || in_array('อื่นๆ', $channel) || !empty(trim($channelOtherText)));
} else {
    $chk_phone    = renderCheckbox($channel == 'phone' || $channel == 'ทางโทรศัพท์');
    $chk_document = renderCheckbox($channel == 'document' || $channel == 'letter' || $channel == 'หนังสือนำส่ง' || $channel == 'ทางหนังสือ');
    $chk_radio    = renderCheckbox($channel == 'radio' || $channel == 'ทางวิทยุ' || $channel == 'ทางวิทยุสื่อสาร');
    $chk_other    = renderCheckbox($channel == 'other' || $channel == 'อื่นๆ' || !empty(trim($channelOtherText)));
}

// === Document No ===
$docNoRaw = getVal($gen, 'case_doc_no', getVal($gen, 'document_no'));
$docNo = docNoToThaiFormat($docNoRaw);

function docNoToThaiFormat($docNo) {
    $typeMap = ['LC'=>'ท','MD'=>'ช','BM'=>'ร','FR'=>'พ','TF'=>'จ','EV'=>'ว','CM'=>'อ','PS'=>'บ'];
    // ตรวจสอบฟอร์แมต เช่น 2026-0001-69-BM0001
    if (preg_match('/^\d+-\d+-(\d{2})-([A-Z]{2})(\d+)$/', $docNo, $m)) {
        $thaiPrefix = $typeMap[$m[2]] ?? $m[2];
        return $thaiPrefix . '-' . $m[3] . '/25' . $m[1];
    }
    return $docNo;
}

// === Case Type (support all incident types) ===
$caseType = getVal($gen, 'case_type');
$caseTypeText = '';
switch ($caseType) {
    case 'theft':   $caseTypeText = 'ลักทรัพย์'; break;
    case 'snatch':  $caseTypeText = 'ชิงทรัพย์'; break;
    case 'robbery': $caseTypeText = 'ปล้นทรัพย์'; break;
    case 'murder':  $caseTypeText = 'ฆาตกรรม'; break;
    case 'bomb':    $caseTypeText = 'ระเบิด'; break;
    case 'arson':   $caseTypeText = 'วางเพลิง'; break;
    case 'fire':    $caseTypeText = 'เพลิงไหม้'; break;
    case 'life':    $caseTypeText = 'คดีชีวิต'; break;
    case 'traffic': $caseTypeText = 'จราจร'; break;
    case 'fingerprint': $caseTypeText = 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)'; break;
    case 'scene_evidence': $caseTypeText = 'ตรวจเก็บและส่งมอบวัตถุพยาน'; break;
    case 'person_evidence':
    case 'person evidence': $caseTypeText = 'ตรวจเก็บวัตถุพยานบุคคล'; break;
    case 'other':   $caseTypeText = getVal($gen, 'case_type_other'); break;
    default:        $caseTypeText = $caseType;
}

// === Incident Location ===
$crime_location = getVal($scene, 'crime_location');

// === Incident Datetime ===
$incidentDate  = thaiDateFull(getVal($gen, 'victim_know_date'));
$incidentTime  = thaiTime(getVal($gen, 'victim_know_time'));

// === Inspection Datetime ===
$inspectDate      = thaiDateFull(getVal($gen, 'inspect_date'));
$inspectTime      = thaiTime(getVal($gen, 'inspect_time'));

// === Victims (Multiple) ===
// === Victims List HTML (Looping) ===
$victim_list_html = '';
$allVictims = $data['victims'] ?? [];

if (!empty($allVictims)) {
    foreach ($allVictims as $v) {
        $type = $v['type'] ?? 'ผู้เสียหาย';
        $name = $v['name'] ?? '........................................';
        $age  = $v['age'] ?? '.......';

        // Logic ขีดค่า (ใช้ <del> สำหรับประเภทที่ไม่ใช่)
        $label_damaged = ($type == 'ผู้เสียหาย') ? 'ผู้เสียหาย' : '<del>ผู้เสียหาย</del>';
        $label_death   = ($type == 'ผู้เสียชีวิต') ? 'ผู้เสียชีวิต' : '<del>ผู้เสียชีวิต</del>';
        $label_injured = ($type == 'ผู้บาดเจ็บ') ? 'ผู้บาดเจ็บ' : '<del>ผู้บาดเจ็บ</del>';

        // สร้าง HTML ต่อกัน
        $victim_list_html .= '<div class="fr" style="margin-bottom: 4px;">';
        $victim_list_html .= '    <span class="fl">' . $label_damaged . '/' . $label_death . '/' . $label_injured . '</span>';
        $victim_list_html .= '    <span class="fd" style="flex:1;">' . htmlspecialchars($name) . '</span>';
        $victim_list_html .= '    <span class="fl">อายุประมาณ</span>';
        $victim_list_html .= '    <span class="fd" style="width:50px; text-align:center;">' . htmlspecialchars($age) . '</span>';
        $victim_list_html .= '    <span class="fl">ปี</span>';
        $victim_list_html .= '</div>';
    }
} else {
    // กรณีไม่มีข้อมูล ให้แสดงบรรทัดว่างมาตรฐาน 1 บรรทัด
    $victim_list_html = '
        <div class="fr">
            <span class="fl">ผู้เสียหาย/ผู้เสียชีวิต/ผู้บาดเจ็บ</span>
            <span class="fd" style="flex:1;"></span>
            <span class="fl">อายุประมาณ</span>
            <span class="fd" style="width:50px;"></span>
            <span class="fl">ปี</span>
        </div>';
}


// === Investigator (Officer) ===
$investigator_name  = getVal($gen['investigator'] ?? [], 'name');

// === Police Unit / Province ===
$policeUnit = '';
$province = '';
try {
    $stmtRn = $pdo->prepare("SELECT complaints_From, province FROM rn_ReceiveNoti WHERE id = ?");
    $stmtRn->execute([$incident_id]);
    $rowRn = $stmtRn->fetch(PDO::FETCH_ASSOC);
    if ($rowRn) {
        $policeUnit = $rowRn['complaints_From'] ?? '';
        $province = $rowRn['province'] ?? '';
    }
} catch (PDOException $e) {
    // ignore
}

// ==========================================
// 4. EVIDENCE TABLE ROWS
// ==========================================
$evidenceList = $data['evidences_found'] ?? [];

// Fallback 1: ถ้า evidences_found ว่าง ให้ลอง evidences (normalized key)
if (empty($evidenceList) && !empty($data['evidences']) && is_array($data['evidences'])) {
    $tmp = [];
    foreach ($data['evidences'] as $i => $ev) {
        $detail = trim((string)($ev['detail'] ?? ($ev['item'] ?? '')));
        if ($detail === '' || !empty($ev['_summary_only'])) continue;
        $tmp[] = [
            'no' => $ev['no'] ?? ($i + 1),
            'item' => $detail,
            'lab_unit' => $ev['lab_unit'] ?? ''
        ];
    }
    if (!empty($tmp)) $evidenceList = $tmp;
}

// Fallback 2: ถ้ายังว่าง ให้ใช้ measurements
if (empty($evidenceList) && !empty($data['measurements']) && is_array($data['measurements'])) {
    $tmp = [];
    foreach ($data['measurements'] as $i => $m) {
        $detail = trim((string)($m['item'] ?? ($m['detail'] ?? '')));
        if ($detail === '') continue;
        $tmp[] = [
            'no' => $m['no'] ?? ($i + 1),
            'item' => $detail,
            'lab_unit' => $m['forensic_unit'] ?? ($m['lab_unit'] ?? '')
        ];
    }
    $evidenceList = $tmp;
}

// Merge forensic_unit จาก measurements เติมให้ evidences_found ที่ว่าง lab_unit
if (!empty($evidenceList) && !empty($data['measurements']) && is_array($data['measurements'])) {
    $forensicUnits = [];
    foreach ($data['measurements'] as $m) {
        $f = $m['forensic_unit'] ?? ($m['lab_unit'] ?? '');
        if ($f !== '') $forensicUnits[] = $f;
    }
    foreach ($evidenceList as $idx => &$ev) {
        if (empty($ev['lab_unit']) && isset($forensicUnits[$idx])) {
            $ev['lab_unit'] = $forensicUnits[$idx];
        }
    }
    unset($ev);
}
 
$evidenceRowsHtml = '';
$minRows = 6;
$rowCount = 0;

// Lab unit translation map
$labUnitMap = [
    'bio_dna'     => 'กลุ่มงานตรวจชีววิทยา',
    'chemical'    => 'กลุ่มงานตรวจทางเคมีฟิสิกส์',
    'fingerprint' => 'กลุ่มงานตรวจลายนิ้วมือแฝง',
    'drug'        => 'กลุ่มงานตรวจยาเสพติด',
    'gun'         => 'กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน',
    'document'    => 'กลุ่มงานตรวจเอกสาร',
    'digital'     => 'กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล',
    'computer'    => 'กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์',
    'explosive'   => 'วัตถุระเบิด (กก.กตว.)',
];

if (!empty($evidenceList)) {
    $index = 1;
    foreach ($evidenceList as $evidence) {
        $evidenceNo = getVal($evidence, 'no', $index);
        $evidenceDetail = getVal($evidence, 'item');
        $labUnit = getVal($evidence, 'lab_unit');

        // ข้ามรายการที่ไม่มีชื่อวัตถุพยาน
        if (empty(trim($evidenceDetail))) {
            $index++;
            continue;
        }

        $labUnitText = labUnitsToText($labUnit);

        $evidenceRowsHtml .= '<tr>
            <td><span class="dot-fill">' . htmlspecialchars($evidenceNo) . '</span></td>
            <td class="td-left"><span class="dot-fill">' . htmlspecialchars($evidenceDetail) . '</span></td>
            <td><span class="dot-fill">' . htmlspecialchars($labUnitText) . '</span></td>
        </tr>';
        $index++;
        $rowCount++;
    }
}

// เติมแถวว่างให้ครบ
for ($i = $rowCount; $i < $minRows; $i++) {
    $evidenceRowsHtml .= '<tr>
        <td><span class="dot-fill">&nbsp;</span></td>
        <td><span class="dot-fill">&nbsp;</span></td>
        <td><span class="dot-fill">&nbsp;</span></td>
    </tr>';
}

// === Evidence Nos (สำหรับบรรทัดส่งมอบ) ===
$evidenceNos = [];
if (!empty($evidenceList)) {
    foreach ($evidenceList as $i => $ev) {
        $detail = trim((string)(($ev['item'] ?? ($ev['detail'] ?? ''))));
        if ($detail !== '') {
            $evidenceNos[] = $ev['no'] ?? ($i + 1);
        }
    }
}
$evidenceNoStr = !empty($evidenceNos) ? implode(', ', $evidenceNos) : '';

// ==========================================
// 5. HANDOVER / SIGNATURES
// ==========================================

// === Receiver ===
$recv_id = getVal($handover, 'receiver_id');
$recv_name = '';
$recv_pos  = getVal($handover, 'receiver_pos');

if (!empty($recv_id) && isset($userMap[$recv_id])) {
    $recv_name = $userMap[$recv_id];
} elseif (!empty($recv_id) && isset($pdo)) {
    try {
        $stmtRecv = $pdo->prepare("SELECT CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                                   FROM user_profile t1 
                                   LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                                   WHERE t1.user_id = ?");
        $stmtRecv->execute([$recv_id]);
        $rowRecv = $stmtRecv->fetch(PDO::FETCH_ASSOC);
        if ($rowRecv) $recv_name = $rowRecv['fullname'];
    } catch (Exception $e) {}
}

// === Deliverer ===
$delv_id = getVal($handover, 'deliverer_id');
$delv_name = '';
$delv_pos  = getVal($handover, 'deliverer_pos');

if (!empty($delv_id) && isset($userMap[$delv_id])) {
    $delv_name = $userMap[$delv_id];
} elseif (!empty($delv_id) && isset($pdo)) {
    try {
        $stmtDelv = $pdo->prepare("SELECT CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                                   FROM user_profile t1 
                                   LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                                   WHERE t1.user_id = ?");
        $stmtDelv->execute([$delv_id]);
        $rowDelv = $stmtDelv->fetch(PDO::FETCH_ASSOC);
        if ($rowDelv) $delv_name = $rowDelv['fullname'];
    } catch (Exception $e) {}
}

// === Signatures (fallback chain: signatures → handover → attachments_meta) ===
// ★ Helper function to check if signature data is valid
function isValidSigData($sig) {
    if (empty($sig)) return false;
    if (is_array($sig)) {
        return !empty($sig['file_id']) || !empty($sig['base64']);
    }
    return is_string($sig) && strpos($sig, 'data:image') !== false;
}

$signatures = $data['signatures'] ?? [];
$recv_sig = $signatures['receiver_sig'] ?? null;
$delv_sig = $signatures['sender_sig'] ?? null;

// Fallback to handover
if (!isValidSigData($recv_sig)) {
    $recv_sig = getVal($handover, 'receiver_sig');
}
if (!isValidSigData($delv_sig)) {
    $delv_sig = getVal($handover, 'sender_sig');
}

// Also check attachments_meta as another fallback
$attachmentsMeta = $data['attachments_meta'] ?? [];
if (!isValidSigData($recv_sig) && isset($attachmentsMeta['receiver_sig'])) {
    $recv_sig = $attachmentsMeta['receiver_sig'];
}
if (!isValidSigData($delv_sig) && isset($attachmentsMeta['sender_sig'])) {
    $delv_sig = $attachmentsMeta['sender_sig'];
}

// DEBUG: ดูข้อมูล signatures (ปิดแล้ว)
// echo '<pre>signatures: ' . print_r($signatures, true) . '</pre>';
// echo '<pre>recv_sig: ' . print_r($recv_sig, true) . '</pre>';
// echo '<pre>delv_sig: ' . print_r($delv_sig, true) . '</pre>';

$receiver_sig_img  = renderSignatureImg($recv_sig, $pdo);
$deliverer_sig_img = renderSignatureImg($delv_sig, $pdo);


// === Handover Date/Time ===
$handoverDate = thaiDateFull(getVal($handover, 'inspection_end_date'));
$handoverTime = thaiTime(getVal($handover, 'inspection_end_time'));

// ==========================================
// 6. READ HTML TEMPLATE & REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_report_bomb_preview.html');

$replacements = [
    // Header
    '{{report_no}}'       => htmlspecialchars($reportNo),

    // เขียนที่ + วันที่
    '{{source_station}}'   => htmlspecialchars($sourceStation),
    '{{report_day}}'       => htmlspecialchars($reportDate['day']),
    '{{report_month}}'     => htmlspecialchars($reportDate['month']),
    '{{report_year}}'      => htmlspecialchars($reportDate['year']),

    // Content - Station repeat
    '{{source_station_2}}' => htmlspecialchars($sourceStation),

    // Checkboxes
    '{{chk_phone}}'        => $chk_phone,
    '{{chk_document}}'     => $chk_document,
    '{{chk_radio}}'        => $chk_radio,
    '{{chk_other}}'        => $chk_other,
    '{{channel_other_txt}}'=> htmlspecialchars($channelOtherText),

    // Document line
    '{{doc_no}}'           => htmlspecialchars($docNo),
    '{{report_day_2}}'     => htmlspecialchars($reportDate['day']),
    '{{report_month_2}}'   => htmlspecialchars($reportDate['month']),
    '{{report_year_2}}'    => htmlspecialchars($reportDate['year']),

    // Case + Location
    '{{case_type_text}}'   => htmlspecialchars($caseTypeText),
    '{{crime_location}}'=> htmlspecialchars($crime_location),

    // Date/Time
    '{{incident_date}}'    => htmlspecialchars($incidentDate),
    '{{incident_time}}'    => htmlspecialchars($incidentTime),
    '{{inspect_date}}'     => htmlspecialchars($inspectDate),
    '{{inspect_time}}'     => htmlspecialchars($inspectTime),

    // Victim
    '{{victim_list_html}}' => $victim_list_html,

    // Officer
    '{{officer_name}}'     => htmlspecialchars($investigator_name),

    // Police Unit / Province
    '{{police_unit}}'      => htmlspecialchars($policeUnit),
    '{{province}}'         => htmlspecialchars($province),

    // Evidence count (นับเฉพาะรายการที่มี lab_unit)
    '{{evidence_count}}'   => $rowCount > 0 ? $rowCount : '',

    // Evidence table
    '{{evidence_rows}}'    => $evidenceRowsHtml,

    // Evidence Nos (handover line)
    '{{evidence_nos}}'     => htmlspecialchars($evidenceNoStr),

    // Receiver
    '{{receiver_sig_img}}'     => $receiver_sig_img,
    '{{receiver_name}}'        => htmlspecialchars($recv_name),
    '{{receiver_position}}'    => htmlspecialchars($recv_pos),
    '{{handover_date}}'        => htmlspecialchars($handoverDate),
    '{{handover_time}}'        => htmlspecialchars($handoverTime),

    // Deliverer
    '{{deliverer_sig_img}}'    => $deliverer_sig_img,
    '{{deliverer_name}}'       => htmlspecialchars($delv_name),
    '{{deliverer_position}}'   => htmlspecialchars($delv_pos),
    '{{handover_date_2}}'      => htmlspecialchars($handoverDate),
    '{{handover_time_2}}'      => htmlspecialchars($handoverTime),
];

// แทนที่ค่าใน HTML
$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// ==========================================
// 7. OUTPUT HTML
// ==========================================
header('Content-Type: text/html; charset=utf-8');
echo $htmlContent;
