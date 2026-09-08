<?php
/**
 * gen_pdf_report_html.php - Generate PDF F-CS-11 (แบบการตรวจเก็บและส่งมอบวัตถุพยาน)
 * ใช้ HTML Template แทน mPDF - ใช้ได้กับทุกประเภทคดี (ทรัพย์/ชีวิต/ระเบิด)
 *
 * รับ parameter: incident_id (GET)
 * ดึงข้อมูลจาก: incident_checklist_transaction.incident_checklist_data (JSON)
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

function joinDateTime($date, $time)
{
    $date = trim((string)$date);
    $time = trim((string)$time);
    if ($date === '') return '';
    if ($time === '') $time = '00:00';
    return $date . 'T' . $time;
}

function normalizeText($text)
{
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    return preg_replace('/\s+/u', ' ', $text);
}

function splitIncidentLocationLines($text, $firstLineLimit = 40)
{
    $text = normalizeText($text);
    if ($text === '') {
        return ['line1' => '', 'line2' => ''];
    }

    $firstLineLimit = (int)$firstLineLimit;

    if ($firstLineLimit <= 0) {
        return ['line1' => $text, 'line2' => ''];
    }

    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($length <= $firstLineLimit) {
        return ['line1' => $text, 'line2' => ''];
    }

    $sub = function ($value, $start, $len = null) {
        if (function_exists('mb_substr')) {
            return $len === null
                ? mb_substr($value, $start, null, 'UTF-8')
                : mb_substr($value, $start, $len, 'UTF-8');
        }
        return $len === null ? substr($value, $start) : substr($value, $start, $len);
    };

    // === กลยุทธ์การตัดบรรทัด (ปรับปรุงสำหรับภาษาไทย) ===
    // 1. หาจุดตัดจากช่องว่าง (space) ที่ใกล้ $firstLineLimit ที่สุด
    // 2. ถ้าช่องว่างอยู่ก่อนคำนำหน้าที่อยู่ไทย (ต./อ./จ./ถ./ซ./แขวง/เขต) → ตัดก่อนคำนำหน้านั้น
    //    เพื่อไม่ให้ตัดแยก "ต.สะเตง" กับ "อ.เมือง" ออกจากกัน

    // รวบรวมตำแหน่ง space ทั้งหมดในข้อความ
    $spacePositions = [];
    for ($i = 0; $i < $length; $i++) {
        if ($sub($text, $i, 1) === ' ') {
            $spacePositions[] = $i;
        }
    }

    if (empty($spacePositions)) {
        // ไม่มีช่องว่างเลย → แสดงบรรทัดเดียว
        return ['line1' => $text, 'line2' => ''];
    }

    // หาตำแหน่ง space ที่ดีที่สุด: ใกล้ $firstLineLimit มากที่สุด (ไม่เกิน)
    $bestSpace = false;
    foreach ($spacePositions as $pos) {
        if ($pos <= $firstLineLimit) {
            $bestSpace = $pos;
        } else {
            break;
        }
    }

    // ถ้าไม่เจอ space ก่อน limit → ใช้ space แรกหลัง limit
    if ($bestSpace === false) {
        foreach ($spacePositions as $pos) {
            if ($pos > $firstLineLimit) {
                $bestSpace = $pos;
                break;
            }
        }
    }

    if ($bestSpace === false) {
        return ['line1' => $text, 'line2' => ''];
    }

    // === ปรับจุดตัดให้ไม่ตัดก่อนคำนำหน้าที่อยู่ไทย ===
    // ถ้าหลัง space มีคำเช่น อ. จ. ถ. ซ. แขวง เขต → ลองหาจุดตัดที่ดีกว่า
    // โดยตัดก่อนกลุ่มที่อยู่ทั้งหมด (ต.xxx อ.xxx จ.xxx) ให้อยู่ในบรรทัดเดียวกัน
    $thaiAddressPrefixes = ['ต.', 'อ.', 'จ.', 'ถ.', 'ซ.', 'แขวง', 'เขต'];
    $afterBest = trim($sub($text, $bestSpace + 1));
    $startsWithAddressPrefix = false;
    foreach ($thaiAddressPrefixes as $prefix) {
        if (function_exists('mb_strpos') ? mb_strpos($afterBest, $prefix, 0, 'UTF-8') === 0 : strpos($afterBest, $prefix) === 0) {
            $startsWithAddressPrefix = true;
            break;
        }
    }

    if ($startsWithAddressPrefix) {
        // หา space ก่อนหน้า bestSpace ที่ข้อความหลังมันไม่ใช่คำนำหน้าที่อยู่
        $betterSpace = false;
        $candidateSpaces = array_filter($spacePositions, function($p) use ($bestSpace) {
            return $p < $bestSpace;
        });
        // ลองจาก space ที่ใกล้ bestSpace ที่สุดลงไป
        $candidateSpaces = array_reverse($candidateSpaces);
        foreach ($candidateSpaces as $pos) {
            $afterThis = trim($sub($text, $pos + 1));
            $isAddress = false;
            foreach ($thaiAddressPrefixes as $prefix) {
                if (function_exists('mb_strpos') ? mb_strpos($afterThis, $prefix, 0, 'UTF-8') === 0 : strpos($afterThis, $prefix) === 0) {
                    $isAddress = true;
                    break;
                }
            }
            if (!$isAddress) {
                $betterSpace = $pos;
                break;
            }
        }
        if ($betterSpace !== false) {
            $bestSpace = $betterSpace;
        }
    }

    $line1 = $sub($text, 0, $bestSpace);
    $line2 = $sub($text, $bestSpace + 1);

    return ['line1' => trim((string)$line1), 'line2' => trim((string)$line2)];
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

function renderSignatureImg($sigData)
{
    // ถ้าไม่มีลายเซ็น ให้แสดงช่องว่างสำหรับเซ็น
    $emptyBox = '<span class="sig-empty-box"></span>';
    
    if (empty($sigData)) return $emptyBox;
    // Handle file_id (BLOB stored in DB) — used by fire/property cases
    if (is_array($sigData) && !empty($sigData['file_id'])) {
        $fileId = (int)$sigData['file_id'];
        return '<img src="/csims/api/incidentCheckList/getFile.php?id=' . $fileId . '" class="sig-img" alt="ลายเซ็น">';
    }
    // Handle filename (saved as file in uploads/checklist_signatures/) — used by scene_evidence
    if (is_array($sigData) && !empty($sigData['filename'])) {
        $filename = $sigData['filename'];
        return '<img src="/csims/uploads/checklist_signatures/' . htmlspecialchars($filename) . '" class="sig-img" alt="ลายเซ็น">';
    }
    $base64 = '';
    if (is_array($sigData) && !empty($sigData['base64'])) {
        $base64 = $sigData['base64'];
    } elseif (is_string($sigData)) {
        $base64 = $sigData;
    }
    if (empty($base64) || strpos($base64, 'data:image') === false) return $emptyBox;
    return '<img src="' . $base64 . '" class="sig-img" alt="ลายเซ็น">';
}

/**
 * สร้างบาร์โค้ด 1 มิติ (Code 39) เป็น inline SVG จากข้อความ
 * รองรับอักขระ 0-9, A-Z และ - . (space) $ / + %  (ตัวพิมพ์เล็กถูกแปลงเป็นพิมพ์ใหญ่)
 * ใช้สำหรับเข้ารหัส "เลขที่รายงาน" ให้สแกนได้
 */
function renderCode39Barcode($data, $narrow = 2, $wide = 6, $height = 40)
{
    static $patterns = [
        '0' => 'NNNWWNWNN', '1' => 'WNNWNNNNW', '2' => 'NNWWNNNNW', '3' => 'WNWWNNNNN',
        '4' => 'NNNWWNNNW', '5' => 'WNNWWNNNN', '6' => 'NNWWWNNNN', '7' => 'NNNWNNWNW',
        '8' => 'WNNWNNWNN', '9' => 'NNWWNNWNN', 'A' => 'WNNNNWNNW', 'B' => 'NNWNNWNNW',
        'C' => 'WNWNNWNNN', 'D' => 'NNNNWWNNW', 'E' => 'WNNNWWNNN', 'F' => 'NNWNWWNNN',
        'G' => 'NNNNNWWNW', 'H' => 'WNNNNWWNN', 'I' => 'NNWNNWWNN', 'J' => 'NNNNWWWNN',
        'K' => 'WNNNNNNWW', 'L' => 'NNWNNNNWW', 'M' => 'WNWNNNNWN', 'N' => 'NNNNWNNWW',
        'O' => 'WNNNWNNWN', 'P' => 'NNWNWNNWN', 'Q' => 'NNNNNNWWW', 'R' => 'WNNNNNWWN',
        'S' => 'NNWNNNWWN', 'T' => 'NNNNWNWWN', 'U' => 'WWNNNNNNW', 'V' => 'NWWNNNNNW',
        'W' => 'WWWNNNNNN', 'X' => 'NWNNWNNNW', 'Y' => 'WWNNWNNNN', 'Z' => 'NWWNWNNNN',
        '-' => 'NWNNNNWNW', '.' => 'WWNNNNWNN', ' ' => 'NWWNNNNWN', '$' => 'NWNWNWNNN',
        '/' => 'NWNWNNNWN', '+' => 'NWNNNWNWN', '%' => 'NNNWNWNWN', '*' => 'NWNNWNWNN',
    ];
    $data = strtoupper((string)$data);
    $clean = '';
    $len = strlen($data);
    for ($i = 0; $i < $len; $i++) {
        $c = $data[$i];
        if ($c !== '*' && isset($patterns[$c])) {
            $clean .= $c;
        }
    }
    if ($clean === '') {
        return '';
    }
    $seq = '*' . $clean . '*';
    $x = 0;
    $bars = '';
    $slen = strlen($seq);
    for ($i = 0; $i < $slen; $i++) {
        $p = $patterns[$seq[$i]];
        for ($e = 0; $e < 9; $e++) {
            $w = ($p[$e] === 'W') ? $wide : $narrow;
            if (($e % 2) === 0) { // อิลิเมนต์คู่ = แท่งดำ
                $bars .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . $height . '"/>';
            }
            $x += $w;
        }
        $x += $narrow; // ช่องว่างคั่นระหว่างตัวอักษร
    }
    $totalW = $x;
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $totalW . ' ' . $height . '" preserveAspectRatio="none" shape-rendering="crispEdges">'
        . '<rect x="0" y="0" width="' . $totalW . '" height="' . $height . '" fill="#fff"/>'
        . '<g fill="#000">' . $bars . '</g></svg>';
}

function resolveUserDisplayById($pdo, $rawId)
{
    $rawId = trim((string)$rawId);
    if ($rawId === '' || !preg_match('/^\d+$/', $rawId)) {
        return ['name' => '', 'position' => ''];
    }

    try {
        $stmt = $pdo->prepare("SELECT CONCAT(IFNULL(r.rank_name,''),' ',p.first_name,' ',p.last_name) AS fullname,
                                      IFNULL(up.position_name,'') AS position_name
                               FROM users u
                               INNER JOIN user_profile p ON u.user_id = p.user_id
                               LEFT JOIN user_rank r ON p.rank_id = r.rank_id
                               LEFT JOIN user_position up ON p.position_id = up.position_id
                               WHERE u.user_id = ?");
        $stmt->execute([$rawId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return [
                'name' => trim((string)($row['fullname'] ?? '')),
                'position' => trim((string)($row['position_name'] ?? ''))
            ];
        }
    } catch (Exception $e) {
        // ignore
    }

    return ['name' => '', 'position' => ''];
}

// ==========================================
// 2. FETCH DATA FROM DATABASE
// ==========================================
$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) die("Error: กรุณาระบุ incident_id");

try {
    $stmt = $pdo->prepare("SELECT incident_checklist_data, f_cs_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) die("Error: ไม่พบข้อมูล Checklist สำหรับ incident_id: " . $incident_id);

    // ใช้ f_cs_data เป็นหลัก ถ้าไม่มีค่อยใช้ incident_checklist_data
    $fcsData = !empty($row['f_cs_data']) ? json_decode($row['f_cs_data'], true) : null;
    $checklistData = json_decode($row['incident_checklist_data'], true);
    
    if (json_last_error() !== JSON_ERROR_NONE) $checklistData = [];
    if (!is_array($fcsData)) $fcsData = null;
    
    // ถ้ามี f_cs_data ให้ใช้เป็นหลัก
    if (!empty($fcsData)) {
        $data = $fcsData;
        // เอา general_info จาก checklistData มาเสริมถ้า fcsData ไม่มี
        if (empty($data['general_info']) && !empty($checklistData['general_info'])) {
            $data['general_info'] = $checklistData['general_info'];
        }
    } else {
        $data = $checklistData;
    }
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
$gen = $data['general_info'] ?? [];
$handover = $data['handover'] ?? [];
$datetimeInfo = $data['datetime_info'] ?? [];
$sceneInfo = $data['scene_info'] ?? [];
$forensicResults = $data['forensic_results'] ?? [];

// === Report No ===
$reportNo = getVal($gen, 'report_no');
// $reportNoRaw = $reportNo; // ค่า ASCII ดิบสำหรับทำบาร์โค้ด (ก่อนแปลงตัวย่อเป็นภาษาไทย)
$docNo = getVal($gen, 'doc_no');
$reportNoRaw = $docNo; // ค่า ASCII ดิบสำหรับทำบาร์โค้ด (ก่อนแปลงตัวย่อเป็นภาษาไทย)
// แปลงตัวย่อเป็นภาษาไทย สำหรับ PDF เท่านั้น
$thaiPrefixMap = ['LC-' => 'ทพ ', 'MD-' => 'ชว ', 'BM-' => 'รบ '];
foreach ($thaiPrefixMap as $en => $th) {
    if (strpos($reportNo, $en) === 0) {
        $reportNo = $th . substr($reportNo, strlen($en));
        break;
    }
}

// === Barcode 1D (Code 39) ของเลขที่รายงาน — สแกนแล้วได้เลขที่รายงาน ===
$reportBarcodeSvg = renderCode39Barcode($reportNoRaw);
$reportBarcodeHtml = $reportBarcodeSvg !== ''
    ? '<div class="report-barcode">' . $reportBarcodeSvg . '<div class="bc-text">' . htmlspecialchars($reportNoRaw) . '</div></div>'
    : '';

// === Source Station ===
$sourceStation = getVal($gen, 'source_station');
if (empty($sourceStation)) {
    $sourceStation = getVal($gen, 'police_station');
}
$sourceStation = normalizeText($sourceStation);

// === Report Datetime ===
$reportDatetimeRaw = getVal($gen, 'report_datetime');
if (empty($reportDatetimeRaw)) {
    $reportDatetimeRaw = joinDateTime(getVal($gen, 'case_date'), getVal($gen, 'case_time'));
}
if (empty($reportDatetimeRaw)) {
    $reportDatetimeRaw = joinDateTime(getVal($gen, 'receive_date'), getVal($gen, 'receive_time'));
}
if (empty($reportDatetimeRaw)) {
    $reportDatetimeRaw = joinDateTime(getVal($gen, 'report_date'), getVal($gen, 'report_time'));
}
$reportDateParts = parseDateParts($reportDatetimeRaw);

// Override from fcs11_data
$fcs11 = $data['fcs11_data'] ?? [];
if (!empty($fcs11['report_day'])) $reportDateParts['day'] = $fcs11['report_day'];
if (!empty($fcs11['report_month'])) $reportDateParts['month'] = $fcs11['report_month'];
if (!empty($fcs11['report_year'])) $reportDateParts['year'] = $fcs11['report_year'];

// === Notification Channel ===
$channel = getVal($gen, 'report_channel');
$channel = !empty($channel) ? $channel : getVal($gen, 'notify_method');
$channelOtherText = getVal($gen, 'report_channel_other');
$channelOtherText = !empty($channelOtherText) ? $channelOtherText : getVal($gen, 'notify_method_other_text');
$channelOtherText = normalizeText($channelOtherText);

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
$docNo = getVal($gen, 'document_no');
if (empty($docNo)) {
    $docNo = getVal($gen, 'case_doc_no');
}
if (empty($docNo)) {
    $docNo = getVal($gen, 'doc_no');
}
$docNo = convertDocNoToThai(normalizeText($docNo));

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
// Override from fcs11_data if available
if (!empty($fcs11['case_type_text'])) $caseTypeText = $fcs11['case_type_text'];

// === Incident Location ===
$incidentLocation = getVal($gen, 'location_detail');
if (empty($incidentLocation)) {
    $incidentLocation = getVal($data['scene_info'] ?? [], 'crime_location');
}
if (empty($incidentLocation)) {
    $incidentLocation = getVal($sceneInfo, 'incident_location');
}
$incidentLocation = normalizeText($incidentLocation);
$incidentLocationLines = splitIncidentLocationLines($incidentLocation, 80);
$incidentLocationLine1 = $incidentLocationLines['line1'] ?? '';
$incidentLocationLine2 = $incidentLocationLines['line2'] ?? '';
$incidentLocationLine2HiddenClass = $incidentLocationLine2 !== '' ? '' : 'is-hidden';

// === Incident Datetime ===
$incidentDatetimeRaw = getVal($gen, 'incident_datetime');
if (empty($incidentDatetimeRaw)) {
    $incidentDatetimeRaw = joinDateTime(getVal($gen, 'victim_know_date'), getVal($gen, 'victim_know_time'));
}
if (empty($incidentDatetimeRaw)) {
    $incidentDatetimeRaw = joinDateTime(getVal($gen, 'incident_date'), getVal($gen, 'incident_time'));
}
if (empty($incidentDatetimeRaw)) {
    $incidentDatetimeRaw = joinDateTime(getVal($datetimeInfo, 'victim_known_date'), getVal($datetimeInfo, 'victim_known_time'));
}
$incidentDate = thaiDateFull($incidentDatetimeRaw);
$incidentTime = thaiTime($incidentDatetimeRaw);

// === Inspection Datetime ===
$inspectionDatetimeRaw = getVal($gen, 'inspection_datetime');
if (empty($inspectionDatetimeRaw)) {
    $inspectionDatetimeRaw = joinDateTime(getVal($gen, 'inspect_date'), getVal($gen, 'inspect_time'));
}
if (empty($inspectionDatetimeRaw)) {
    $inspectionDatetimeRaw = joinDateTime(getVal($gen, 'collect_date'), getVal($gen, 'collect_time'));
}
if (empty($inspectionDatetimeRaw)) {
    $inspectionDatetimeRaw = joinDateTime(getVal($datetimeInfo, 'inspection_date'), getVal($datetimeInfo, 'inspection_time'));
}
$inspectDate = thaiDateFull($inspectionDatetimeRaw);
$inspectTime = thaiTime($inspectionDatetimeRaw);

// === Victim ===
$victim = $gen['victim'] ?? [];
$victimName = '';
// ลองดึงจาก name ก่อน ถ้าไม่มีใช้ firstname + lastname
$victimName = getVal($victim, 'name');
if (empty($victimName)) {
    $victimName = trim(getVal($victim, 'firstname') . ' ' . getVal($victim, 'lastname'));
}
$victimAge = getVal($victim, 'age');
if (empty($victimName) && !empty($sceneInfo['persons']) && is_array($sceneInfo['persons'])) {
    $firstPerson = $sceneInfo['persons'][0] ?? [];
    $victimName = getVal($firstPerson, 'name');
    $victimAge = getVal($firstPerson, 'age');
}
$victimName = normalizeText($victimName);
$victimAge = normalizeText($victimAge);

// Override victim from fcs11_data if available
if (!empty($fcs11['victim_name'])) $victimName = normalizeText($fcs11['victim_name']);
if (!empty($fcs11['victim_age'])) $victimAge = normalizeText($fcs11['victim_age']);

// === Investigator (Officer) ===
$investigator = $gen['investigator'] ?? [];
$officerName = trim(getVal($investigator, 'firstname') . ' ' . getVal($investigator, 'lastname'));
if (empty($officerName)) {
    $officerName = getVal($investigator, 'name');
}
if (empty($officerName)) {
    $officerName = getVal($datetimeInfo, 'investigator_name');
}
$officerName = normalizeText($officerName);

// === Police Unit / Province ===
$policeUnit = '';
$province = '';
$provinceId = getVal($gen, 'province_id');
if (!empty($provinceId) && isset($pdo)) {
    try {
        $stmtProv = $pdo->prepare("SELECT cfs_name FROM CFS_province WHERE cfs_id = ?");
        $stmtProv->execute([$provinceId]);
        $rowProv = $stmtProv->fetch(PDO::FETCH_ASSOC);
        if ($rowProv) {
            $province = $rowProv['cfs_name'] ?? '';
        }
    } catch (PDOException $e) {
        $province = '';
    }
}

// Override police_unit and province from fcs11_data if available
if (!empty($fcs11['police_unit'])) $policeUnit = $fcs11['police_unit'];
if (!empty($fcs11['province'])) $province = $fcs11['province'];

$policeUnit = normalizeText($policeUnit);
$province = normalizeText($province);

// ==========================================
// 4. EVIDENCE TABLE ROWS
// ==========================================
// ทุกประเภทคดี: ใช้ measurements เป็นแหล่งหลัก (ตาราง "บันทึกการตรวจเก็บวัตถุพยาน" หน้า 7)
// ไม่ใช้ evidences (ตาราง "วัตถุพยานและตำแหน่งที่ตรวจพบ" หน้า 5)
$usesMeasurements = !empty($data['measurements']);


if ($usesMeasurements) {
    $evidenceList = [];
    foreach ($data['measurements'] as $i => $ev) {
        $detail = trim((string)($ev['item'] ?? ($ev['detail'] ?? '')));
        if ($detail === '') {
            continue;
        }
        $evidenceList[] = [
            'no' => $ev['no'] ?? ($i + 1),
            'detail' => $detail,
            'lab_unit' => $ev['forensic_unit'] ?? ($ev['lab_unit'] ?? '')
        ];
    }
} else {
    // Fallback 1: ลองดึงจาก measurements ก่อน (ตาราง "บันทึกการตรวจเก็บวัตถุพยาน" หน้า 7)
    $fallbackMeasure = $data['measurements'] ?? [];
    if (!empty($fallbackMeasure) && is_array($fallbackMeasure)) {
        $tmp = [];
        foreach ($fallbackMeasure as $i => $ev) {
            $detail = trim((string)($ev['item'] ?? ($ev['detail'] ?? '')));
            if ($detail === '') {
                continue;
            }
            $tmp[] = [
                'no' => $ev['no'] ?? ($i + 1),
                'detail' => $detail,
                'lab_unit' => $ev['forensic_unit'] ?? ($ev['lab_unit'] ?? '')
            ];
        }
        $evidenceList = $tmp;
    }
    
    // Fallback 2: evidences (สำหรับข้อมูลเก่าที่ไม่มี measurements)
    if (empty($evidenceList)) {
        $rawEvidences = $data['evidences'] ?? [];
        if (!empty($rawEvidences) && is_array($rawEvidences)) {
            $tmp = [];
            foreach ($rawEvidences as $i => $ev) {
                // ข้าม summary-only entries (เช่น blood summary)
                if (!empty($ev['_summary_only'])) {
                    continue;
                }
                $detail = trim((string)($ev['detail'] ?? ($ev['item'] ?? '')));
                if ($detail === '') {
                    continue;
                }
                $tmp[] = [
                    'no' => $ev['no'] ?? ($i + 1),
                    'detail' => $detail,
                    'lab_unit' => $ev['lab_unit'] ?? ($ev['forensic_unit'] ?? '')
                ];
            }
            $evidenceList = $tmp;
        }
    }
    
    // Fallback 3: evidences_found
    if (empty($evidenceList)) {
        $fallbackFound = $data['evidences_found'] ?? [];
        if (!empty($fallbackFound) && is_array($fallbackFound)) {
            $tmp = [];
            foreach ($fallbackFound as $i => $ev) {
                $detail = trim((string)($ev['item'] ?? ($ev['detail'] ?? '')));
                if ($detail === '') {
                    continue;
                }
                $tmp[] = [
                    'no' => $ev['no'] ?? ($i + 1),
                    'detail' => $detail,
                    'lab_unit' => $ev['lab_unit'] ?? ''
                ];
            }
            $evidenceList = $tmp;
        }
    }
}
if (empty($evidenceList)) {
    $sectionSets = $data['section6_sets'] ?? [];
    if (!empty($sectionSets) && is_array($sectionSets)) {
        $tmp = [];
        $hasMultipleSets = count($sectionSets) > 1;
        foreach ($sectionSets as $setIndex => $set) {
            $setNumber = (int)($set['set_index'] ?? ($setIndex + 1));
            $setLabUnit = getVal($set, 'lab_unit', 'fingerprint');
            $evidenceItems = $set['evidence_items'] ?? [];

            if (is_array($evidenceItems) && !empty($evidenceItems)) {
                foreach ($evidenceItems as $item) {
                    $detail = trim((string)(
                        getVal($item, 'description') ?: getVal($item, 'detail') ?: getVal($item, 'item')
                    ));
                    if ($detail === '') {
                        continue;
                    }

                    if ($hasMultipleSets) {
                        $detail = 'ชุดที่ ' . $setNumber . ': ' . $detail;
                    }

                    $tmp[] = [
                        'no' => count($tmp) + 1,
                        'detail' => $detail,
                        'lab_unit' => getVal($item, 'lab_unit', $setLabUnit)
                    ];
                }
                continue;
            }

            $detail = trim((string)(
                getVal($set, 'evidence_detail') ?: getVal($set, 'detail') ?: getVal($set, 'item') ?: getVal($set, 'description')
            ));
            if ($detail === '') {
                continue;
            }

            if ($hasMultipleSets) {
                $detail = 'ชุดที่ ' . $setNumber . ': ' . $detail;
            }

            $tmp[] = [
                'no' => count($tmp) + 1,
                'detail' => $detail,
                'lab_unit' => $setLabUnit
            ];
        }
        $evidenceList = $tmp;
    }
}

$sanitizedEvidenceList = [];
if (!empty($evidenceList) && is_array($evidenceList)) {
    $evidenceTypeLabelMap = [
        'blood' => 'คราบสีแดงคล้ายโลหิต',
        'fingerprint' => 'ลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง',
        'dna' => 'สารพันธุกรรม',
        'toolmark' => 'ร่องรอยการตัด (Toolmark)',
        'other' => 'อื่น ๆ'
    ];
    foreach ($evidenceList as $i => $evidence) {
        // Skip summary-only helper rows (e.g. blood summary section) - not an evidence handover item.
        if (!empty($evidence['_summary_only'])) {
            continue;
        }

        // Skip type-only entries (summary entries from property/bomb cases)
        $detail = trim((string)getVal($evidence, 'detail'));
        if ($detail === '') {
            $detail = trim((string)getVal($evidence, 'item'));
        }
        if ($detail === '') {
            continue;
        }

        $sanitizedEvidenceList[] = [
            'no' => getVal($evidence, 'no', count($sanitizedEvidenceList) + 1),
            'detail' => $detail,
            'lab_unit' => getVal($evidence, 'lab_unit')
        ];
    }
}

if (empty($sanitizedEvidenceList)) {
    $fallbackMeasure = $data['measurements'] ?? [];
    if (!empty($fallbackMeasure) && is_array($fallbackMeasure)) {
        foreach ($fallbackMeasure as $i => $ev) {
            $detail = trim((string)($ev['item'] ?? ($ev['detail'] ?? '')));
            if ($detail === '') {
                continue;
            }
            $sanitizedEvidenceList[] = [
                'no' => $ev['no'] ?? (count($sanitizedEvidenceList) + 1),
                'detail' => $detail,
                'lab_unit' => $ev['forensic_unit'] ?? ($ev['lab_unit'] ?? '')
            ];
        }
    }
}

$evidenceList = $sanitizedEvidenceList;

// Fallback: merge forensic_unit from measurements when individual items lack lab_unit
$measurementForensic = [];
if (!empty($data['measurements']) && is_array($data['measurements'])) {
    foreach ($data['measurements'] as $m) {
        $forensic = $m['forensic_unit'] ?? ($m['lab_unit'] ?? '');
        if ($forensic !== '') $measurementForensic[] = $forensic;
    }
}
if (!empty($measurementForensic)) {
    foreach ($evidenceList as $evIdx => &$evItem) {
        if (empty($evItem['lab_unit']) && isset($measurementForensic[$evIdx])) {
            $evItem['lab_unit'] = $measurementForensic[$evIdx];
        }
    }
    unset($evItem);
}

// Fallback: merge lab_units from collected_evidence when individual items lack lab_unit
$collectedLabUnits = $data['collected_evidence']['lab_units'] ?? [];
if (!empty($collectedLabUnits) && is_array($collectedLabUnits)) {
    foreach ($evidenceList as $evIdx => &$evItem) {
        if (empty($evItem['lab_unit']) && isset($collectedLabUnits[$evIdx])) {
            $evItem['lab_unit'] = $collectedLabUnits[$evIdx];
        }
    }
    unset($evItem);
}

$evidenceRowsHtml = '';
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
    'explosive'   => 'กลุ่มงานตรวจวัตถุระเบิด (กก.กตว.)',
];

if (!empty($evidenceList)) {
    $index = 1;
    foreach ($evidenceList as $evidence) {
        $evidenceNo = getVal($evidence, 'no', $index);
        $evidenceDetail = trim((string)getVal($evidence, 'detail'));
        $labUnit = getVal($evidence, 'lab_unit');
        if ($labUnit === 'traffic') {
            $labUnit = getVal($forensicResults, 'lab_unit', '');
        }

        if ($evidenceDetail === '' && (string)$evidenceNo === '') {
            continue;
        }

        $labUnitText = $labUnitMap[$labUnit] ?? $labUnit;
        if (empty($labUnitText)) {
            $labUnitText = '-';
        }

        $evidenceRowsHtml .= '<tr>
            <td>' . htmlspecialchars($evidenceNo) . '</td>
            <td class="td-left">' . htmlspecialchars($evidenceDetail) . '</td>
            <td>' . htmlspecialchars($labUnitText) . '</td>
        </tr>';
        $index++;
        $rowCount++;
    }
}

// === Evidence Nos (สำหรับบรรทัดส่งมอบ) ===
$evidenceNos = [];
if (!empty($evidenceList)) {
    foreach ($evidenceList as $ev) {
        if (!empty($ev['no'])) {
            $evidenceNos[] = $ev['no'];
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

// ลองดึงจาก fcsData โดยตรงก่อน
if (!empty($fcsData['handover']['receiver_name'])) {
    $recv_name = $fcsData['handover']['receiver_name'];
}
if (empty($recv_pos) && !empty($fcsData['handover']['receiver_position'])) {
    $recv_pos = $fcsData['handover']['receiver_position'];
}

if (empty($recv_name) && !empty($recv_id) && isset($userMap[$recv_id])) {
    $recv_name = $userMap[$recv_id];
} elseif (empty($recv_name) && !empty($recv_id) && isset($pdo)) {
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
if (empty($recv_name) && !empty($recv_id)) {
    $resolved = resolveUserDisplayById($pdo, $recv_id);
    if (!empty($resolved['name'])) {
        $recv_name = $resolved['name'];
    }
    if (empty($recv_pos) && !empty($resolved['position'])) {
        $recv_pos = $resolved['position'];
    }
}

// === Deliverer ===
$delv_id = getVal($handover, 'deliverer_id');
$delv_name = '';
$delv_pos  = getVal($handover, 'deliverer_pos');

// ลองดึงจาก fcsData โดยตรงก่อน
if (!empty($fcsData['handover']['deliverer_name'])) {
    $delv_name = $fcsData['handover']['deliverer_name'];
}
if (empty($delv_pos) && !empty($fcsData['handover']['deliverer_position'])) {
    $delv_pos = $fcsData['handover']['deliverer_position'];
}

if (empty($delv_name) && !empty($delv_id) && isset($userMap[$delv_id])) {
    $delv_name = $userMap[$delv_id];
} elseif (empty($delv_name) && !empty($delv_id) && isset($pdo)) {
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
if (empty($delv_name) && !empty($delv_id)) {
    $resolved = resolveUserDisplayById($pdo, $delv_id);
    if (!empty($resolved['name'])) {
        $delv_name = $resolved['name'];
    }
    if (empty($delv_pos) && !empty($resolved['position'])) {
        $delv_pos = $resolved['position'];
    }
}

// === Signatures ===
// ถ้ามี f_cs_data ให้ใช้ signature จาก f_cs_data เท่านั้น (ไม่ fallback)
$signatures = $data['signatures'] ?? [];
$attachmentsMeta = $data['attachments_meta'] ?? [];

if (!empty($fcsData)) {
    // ใช้ f_cs_data - ดึง signature จาก fcsData เท่านั้น
    $recv_sig = $fcsData['handover']['receiver_sig'] ?? ($fcsData['signatures']['receiver_sig'] ?? null);
    $delv_sig = $fcsData['handover']['deliverer_sig'] ?? ($fcsData['signatures']['deliverer_sig'] ?? null);
} else {
    // ใช้ incident_checklist_data - fallback ตามปกติ
    $recv_sig = getVal($handover, 'receiver_sig');
    $delv_sig = getVal($handover, 'deliverer_sig');
    
    if (empty($recv_sig) && isset($signatures['receiver_sig'])) {
        $recv_sig = $signatures['receiver_sig'];
    }
    if (empty($delv_sig) && isset($signatures['deliverer_sig'])) {
        $delv_sig = $signatures['deliverer_sig'];
    }
    
    // Also check attachments_meta as another fallback
    if (empty($recv_sig) && isset($attachmentsMeta['receiver_sig'])) {
        $recv_sig = $attachmentsMeta['receiver_sig'];
    }
    if (empty($delv_sig) && isset($attachmentsMeta['deliverer_sig'])) {
        $delv_sig = $attachmentsMeta['deliverer_sig'];
    }
    
    // Fallback for old key names (receiver_signature / sender_signature)
    if (empty($recv_sig) && isset($signatures['receiver_signature'])) {
        $recv_sig = $signatures['receiver_signature'];
    }
    if (empty($delv_sig) && isset($signatures['sender_signature'])) {
        $delv_sig = $signatures['sender_signature'];
    }
    if (empty($delv_sig) && isset($signatures['sender_sig'])) {
        $delv_sig = $signatures['sender_sig'];
    }
    if (empty($delv_sig) && isset($signatures['signer_sig'])) {
        $delv_sig = $signatures['signer_sig'];
    }
}

$receiver_sig_img  = renderSignatureImg($recv_sig);
$deliverer_sig_img = renderSignatureImg($delv_sig);

// Debug: แสดงว่ามี signature หรือไม่
// error_log("recv_sig: " . json_encode($recv_sig));
// error_log("delv_sig: " . json_encode($delv_sig));
// error_log("receiver_sig_img: " . $receiver_sig_img);
// error_log("deliverer_sig_img: " . $deliverer_sig_img);

// === Approver Signature ===
$approver_sig = $handover['approver_sig'] ?? $signatures['approver_sig'] ?? $attachmentsMeta['approver_sig'] ?? '';
$approver_sig_img = renderSignatureImg($approver_sig);

// === Handover Date/Time (ใช้วันที่ตรวจสถานที่เกิดเหตุ) ===
$handoverDate = thaiDateFull($inspectionDatetimeRaw);
$handoverTime = thaiTime($inspectionDatetimeRaw);
if (empty($handoverDate)) {
    $handoverDate = thaiDateFull(getVal($handover, 'inspection_end_date'));
}
if (empty($handoverTime)) {
    $handoverTime = thaiTime(getVal($handover, 'inspection_end_time'));
}

if (empty($recv_name)) {
    $recv_name = getVal($handover, 'receiver_name');
    if (empty($recv_name)) {
        $recv_name = getVal($handover['receiver'] ?? [], 'name_id');
    }
}
if (!empty($recv_name) && preg_match('/^\d+$/', trim((string)$recv_name))) {
    $resolved = resolveUserDisplayById($pdo, $recv_name);
    if (!empty($resolved['name'])) {
        $recv_name = $resolved['name'];
    }
    if (empty($recv_pos) && !empty($resolved['position'])) {
        $recv_pos = $resolved['position'];
    }
}
if (empty($recv_pos)) {
    $recv_pos = getVal($handover, 'receiver_position');
    if (empty($recv_pos)) {
        $recv_pos = getVal($handover['receiver'] ?? [], 'position');
    }
}
if (empty($delv_name)) {
    // ลองดึงจาก fcsData โดยตรงก่อน
    if (!empty($fcsData['handover']['deliverer_name'])) {
        $delv_name = $fcsData['handover']['deliverer_name'];
    } else {
        $delv_name = getVal($handover, 'sender_name');
        if (empty($delv_name)) {
            $delv_name = getVal($handover['sender'] ?? [], 'name_id');
        }
    }
}
if (!empty($delv_name) && preg_match('/^\d+$/', trim((string)$delv_name))) {
    $resolved = resolveUserDisplayById($pdo, $delv_name);
    if (!empty($resolved['name'])) {
        $delv_name = $resolved['name'];
    }
    if (empty($delv_pos) && !empty($resolved['position'])) {
        $delv_pos = $resolved['position'];
    }
}
if (empty($delv_pos)) {
    $delv_pos = getVal($handover, 'sender_position');
    if (empty($delv_pos)) {
        $delv_pos = getVal($handover['sender'] ?? [], 'position');
    }
}
// Fallback: use signer data (used by scene_evidence / EV7)
$signer = $data['signer'] ?? [];
if (empty($delv_name)) {
    $signerId = getVal($signer, 'id');
    if (!empty($signerId)) {
        $resolved = resolveUserDisplayById($pdo, $signerId);
        if (!empty($resolved['name'])) {
            $delv_name = $resolved['name'];
        }
        if (empty($delv_pos) && !empty($resolved['position'])) {
            $delv_pos = $resolved['position'];
        }
    }
}
if (empty($delv_pos) && !empty($signer['position'])) {
    $delv_pos = $signer['position'];
}

// ==========================================
// 6. READ HTML TEMPLATE & REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_report_preview.html');

$replacements = [
    // Header
    '{{report_no}}'       => htmlspecialchars($reportNo),
    '{{report_barcode}}'  => $reportBarcodeHtml,

    // เขียนที่ + วันที่
    '{{source_station}}'   => htmlspecialchars($sourceStation),
    '{{report_day}}'       => htmlspecialchars($reportDateParts['day']),
    '{{report_month}}'     => htmlspecialchars($reportDateParts['month']),
    '{{report_year}}'      => htmlspecialchars($reportDateParts['year']),

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
    '{{report_day_2}}'     => htmlspecialchars($reportDateParts['day']),
    '{{report_month_2}}'   => htmlspecialchars($reportDateParts['month']),
    '{{report_year_2}}'    => htmlspecialchars($reportDateParts['year']),

    // Case + Location
    '{{case_type_text}}'   => htmlspecialchars($caseTypeText),
    '{{incident_location_line1}}' => htmlspecialchars($incidentLocationLine1),
    '{{incident_location_line2}}' => htmlspecialchars($incidentLocationLine2),
    '{{incident_location_line2_hidden}}' => $incidentLocationLine2HiddenClass,

    // Date/Time
    '{{incident_date}}'    => htmlspecialchars($incidentDate),
    '{{incident_time}}'    => htmlspecialchars($incidentTime),
    '{{inspect_date}}'     => htmlspecialchars($inspectDate),
    '{{inspect_time}}'     => htmlspecialchars($inspectTime),

    // Victim
    '{{victim_name}}'      => htmlspecialchars($victimName),
    '{{victim_age}}'       => htmlspecialchars($victimAge),

    // Officer
    '{{officer_name}}'     => htmlspecialchars($officerName),

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

    // Approver
    '{{approver_sig_img}}'     => $approver_sig_img,
    '{{approver_name}}'        => htmlspecialchars($approverName),
    '{{approver_position}}'    => htmlspecialchars($approverPosition),
];

// ช่องที่ไม่มีข้อมูลให้แสดง "-" (ยกเว้น checkbox, รูป, HTML rows, CSS class, ชื่อ/ตำแหน่ง)
$skipDashKeys = [
    '{{chk_phone}}', '{{chk_document}}', '{{chk_radio}}', '{{chk_other}}',
    '{{receiver_sig_img}}', '{{deliverer_sig_img}}', '{{approver_sig_img}}',
    '{{evidence_rows}}', '{{incident_location_line2_hidden}}',
    '{{receiver_name}}', '{{receiver_position}}',
    '{{deliverer_name}}', '{{deliverer_position}}',
    '{{approver_name}}', '{{approver_position}}',
];
foreach ($replacements as $key => &$val) {
    if (in_array($key, $skipDashKeys)) continue;
    if (is_string($val) && trim($val) === '') {
        $val = '<span class="dash-center">-</span>';
    }
}
unset($val);

// แทนที่ค่าใน HTML
$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// ==========================================
// 7. OUTPUT HTML
// ==========================================
header('Content-Type: text/html; charset=utf-8');
echo $htmlContent;
