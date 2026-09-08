<?php
/**
 * gen_pdf_html.php - สร้างแบบการรับแจ้งเหตุ (F-CS-01) เป็น HTML Preview พร้อมปุ่มดาวน์โหลด PDF
 */

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../helpers/report_no.php';

// ==========================================
// 1. HELPER FUNCTIONS
// ==========================================

function renderCheckbox($condition) {
    return $condition ? '✓' : '';
}

function renderSignatureImg($sigFile, $sigPath) {
    if (!empty($sigFile) && file_exists($sigPath . $sigFile)) {
        $content = file_get_contents($sigPath . $sigFile);
        if ($content !== false) {
            $mime = mime_content_type($sigPath . $sigFile);
            $base64 = 'data:' . $mime . ';base64,' . base64_encode($content);
            return '<img src="' . $base64 . '" class="sig-img" alt="ลายเซ็น">';
        }
    }
    return '';
}

/**
 * สร้างบาร์โค้ด 1 มิติ (Code 39) เป็น inline SVG จากข้อความ
 * รองรับอักขระ 0-9, A-Z และ - . (space) $ / + %  (ตัวพิมพ์เล็กถูกแปลงเป็นพิมพ์ใหญ่)
 * ใช้สำหรับเข้ารหัส "เลขที่รับ/เลขรายงาน" ให้สแกนได้
 */
function renderCode39Barcode($data, $narrow = 2, $wide = 6, $height = 60)
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

// ==========================================
// 2. FETCH DATA FROM DATABASE
// ==========================================

$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) {
    die('ไม่พบรหัสรายการ');
}

// Query ดึงข้อมูลทั้งหมด
$sql = "SELECT 
    t1.*,
    t1.receiveNoti_No,
    t1.receiveNotiReportNo,
    t1.location_create,
    t1.create_date,
    t1.complaints_From,
    t1.complaints_From_Device,
    t1.complaints_From_Device_Other,
    t1.complaints_type,
    t1.complaints_type_other,
    t1.location_crime,
    t1.time_Occurrence,
    t1.basic_Info,
    t1.inquiry_official_full_name,
    t1.inquiry_official_phone,
    t1.suffer_full_name,
    t1.suffer_phone,
    t4.rank_name AS creator_rank,

    -- ดึงชื่อผู้สร้างเอกสาร (ผู้รับแจ้ง) แบบไม่มียศนำหน้า
    CONCAT(t3.first_name, ' ', t3.last_name) AS creator_pure_name,

    -- ดึงชื่อผู้ทบทวนแบบไม่มียศ
    CONCAT(prof_rev.first_name, ' ', prof_rev.last_name) AS review_pure_name,
    -- ดึงยศของผู้ทบทวนข้อมูลจาก Master Table
    COALESCE(rank_rev.rank_name, '') AS review_rank_name,
    
    -- ดึงชื่อผู้อนุมัติแบบไม่มียศ
    CONCAT(prof_app.first_name, ' ', prof_app.last_name) AS approve_pure_name,
    -- ดึงยศของผู้อนุมัติข้อมูลจาก Master Table
    COALESCE(rank_app.rank_name, '') AS approve_rank_name

FROM rn_ReceiveNoti t1
LEFT JOIN users t2 ON t1.create_by = t2.user_id
LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
-- JOIN ดึงโปรไฟล์ผู้ทบทวน
LEFT JOIN user_profile prof_rev ON t1.userReviewID = prof_rev.user_id
LEFT JOIN user_rank rank_rev ON prof_rev.rank_id = rank_rev.rank_id

-- JOIN ดึงโปรไฟล์ผู้อนุมัติ
LEFT JOIN user_profile prof_app ON t1.userApproveID = prof_app.user_id
LEFT JOIN user_rank rank_app ON prof_app.rank_id = rank_app.rank_id 
WHERE t1.id = ? AND t1.statusDelete = 0";

$stmt = $pdo->prepare($sql);
$stmt->execute([$incident_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die('ไม่พบข้อมูลรายการนี้');
}

// ==========================================
// 3. FETCH SIGNATURES
// ==========================================

$sqlSign = "SELECT seqSignature, signature, fullName, positionName 
            FROM rn_ReceiveNotiApprove 
            WHERE ComplaintsID = ? 
            ORDER BY seqSignature ASC";
$stmtSign = $pdo->prepare($sqlSign);
$stmtSign->execute([$incident_id]);
$signatures = $stmtSign->fetchAll(PDO::FETCH_ASSOC);

$signData = [
    1 => ['signature' => '', 'fullName' => '', 'positionName' => ''],
    2 => ['signature' => '', 'fullName' => '', 'positionName' => ''],
    3 => ['signature' => '', 'fullName' => '', 'positionName' => '']
];

foreach ($signatures as $sig) {
    $seq = intval($sig['seqSignature']);
    if (isset($signData[$seq])) {
        $signData[$seq] = [
            'signature' => $sig['signature'],
            'fullName' => $sig['fullName'],
            'positionName' => $sig['positionName']
        ];
    }
}

$signaturePath = __DIR__ . '/../../uploads/signatures/';

// ==========================================
// 4. FORMAT DATA
// ==========================================

// วันที่สร้าง
$createDay = '';
$createMonth = '';
$createYear = '';
$createTime = '';

if (!empty($data['create_date'])) {
    $dt = new DateTime($data['create_date']);
    $createDay = $dt->format('d');
    $thaiMonths = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 
                   'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $createMonth = $thaiMonths[intval($dt->format('m'))];
    $createYear = $dt->format('Y') + 543;
    $createTime = $dt->format('H:i');
}

// วัน/เวลาเกิดเหตุ
$occurrenceDate = '';
$occurrenceTime = '';
if (!empty($data['time_Occurrence'])) {
    $dtOcc = new DateTime($data['time_Occurrence']);
    $occurrenceDate = $dtOcc->format('d') . '/' . $dtOcc->format('m') . '/' . ($dtOcc->format('Y') + 543);
    $occurrenceTime = $dtOcc->format('H:i');
}

$downToThaiText = '';
if (!empty($data['down_to']) && $data['down_to'] !== '0000-00-00') {
    $dtDown = new DateTime($data['down_to']);
    $downToThaiText = intval($dtDown->format('d')) . ' ' . $thaiMonths[intval($dtDown->format('m'))] . ' ' . ($dtDown->format('Y') + 543);
}

// ช่องทางรับแจ้ง
$chk_letter = renderCheckbox($data['complaints_From_Device'] == 'b');
$chk_phone = renderCheckbox($data['complaints_From_Device'] == 't');
$chk_radio = renderCheckbox($data['complaints_From_Device'] == 'r');
$chk_other_device = renderCheckbox($data['complaints_From_Device'] == 'o');
$device_other_text = $data['complaints_From_Device_Other'] ?? '';

$letterDetailsBlock = '';
if (($data['complaints_From_Device'] ?? '') === 'b') {
    // ถ้าส่งมาจากทางหนังสือ ให้ประกอบร่างบล็อก HTML เตรียมไว้
    $letterDetailsBlock = '
    <div class="fr fr-indent">
        <span class="fl">ที่</span>
        <span class="fd">' . htmlspecialchars($data['up_to'] ?? '') . '</span>
        <span class="fl">ลง</span>
        <span class="fd">' . htmlspecialchars($downToThaiText) . '</span>
    </div>';
}

// ประเภทเหตุ
$complaintType = $data['complaints_type'] ?? '';
$chk_type_01 = renderCheckbox($complaintType == '01');
$chk_type_02 = renderCheckbox($complaintType == '02');
$chk_type_03 = renderCheckbox($complaintType == '03');
$chk_type_04 = renderCheckbox($complaintType == '04');
$chk_type_05 = renderCheckbox($complaintType == '05');
$chk_type_06 = renderCheckbox($complaintType == '06');
$chk_type_07 = renderCheckbox($complaintType == '07');
$chk_type_08 = renderCheckbox($complaintType == '08');
$chk_type_09 = renderCheckbox($complaintType == '09');

// วันที่ PDF
$dtNow = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
$pdfDay = $dtNow->format('d');
$pdfMonth = $dtNow->format('m');
$pdfYear = ($dtNow->format('Y') + 543) % 100;

// ปี 2 หลักสุดท้าย จากวันที่สร้างเอกสาร สำหรับ เลขรายงาน/25xx
$reportYear2 = '';
if (!empty($createYear)) {
    $reportYear2 = substr($createYear, -2);
}

// ลายเซ็น 1 (ผู้รับแจ้ง) - fallback to creator name if no signature name
$sig1_name = !empty(trim($data['creator_pure_name'] ?? '')) ? $data['creator_pure_name'] : ($signData[1]['fullName'] ?? '');
$sig1_rank = $data['creator_rank'] ?? ''; 
$sig1_position = !empty($signData[1]['positionName']) ? $signData[1]['positionName'] : ($data['creator_rank'] ?? '');
$sig1_img = renderSignatureImg($signData[1]['signature'], $signaturePath);

// ลายเซ็น 2 (หัวหน้าทีม)
$sig2_name = !empty(trim($data['review_pure_name'] ?? '')) ? $data['review_pure_name'] : ($signData[2]['fullName'] ?? '');
$sig2_rank = $data['review_rank_name'] ?? ''; 
$sig2_position = $signData[2]['positionName'] ?? '';
$sig2_img = renderSignatureImg($signData[2]['signature'], $signaturePath);

// ลายเซ็น 3 (ผู้อนุมัติ)
$sig3_name = !empty(trim($data['approve_pure_name'] ?? '')) ? $data['approve_pure_name'] : ($signData[3]['fullName'] ?? '');
$sig3_rank = $data['approve_rank_name'] ?? '';
$sig3_position = $signData[3]['positionName'] ?? '';
$sig3_img = renderSignatureImg($signData[3]['signature'], $signaturePath);

// ==========================================
// สร้างข้อความ "เรียน" จาก userReviewType / userApproveType
// ==========================================
$provinceMap = ['95' => 'ยะลา', '94' => 'ปัตตานี', '96' => 'นราธิวาส'];

function buildTypeText($type, $val, $provinceMap) {
    if ($type === 'nvt') {
        return 'นวท.(สบ ' . $val . ') กสก.ศพฐ.../พฐ.จว....';
    } elseif ($type === 'spt') {
        return 'นวท.(สบ...) กสก.ศพฐ.' . $val . '/พฐ.จว....';
    } elseif ($type === 'ptjv') {
        $pName = $provinceMap[strval($val)] ?? '';
        return 'นวท.(สบ...) กสก.ศพฐ.../พฐ.จว.' . $pName; 
    }
    return '';
}

$reviewTypeText = buildTypeText($data['userReviewType'] ?? '', $data['userReviewTypeVal'] ?? '', $provinceMap);
$approveTypeText = buildTypeText($data['userApproveType'] ?? '', $data['userApproveTypeVal'] ?? '', $provinceMap);

// ==========================================
// 5. READ HTML TEMPLATE & REPLACE
// ==========================================

$htmlTemplate = file_get_contents(__DIR__ . '/form_incident_preview.html');

// Strip year suffix from receiveNotiReportNo (e.g. "LC-0006/2569" → "LC-0006")
$rptNo = $data['receiveNotiReportNo'] ?? '';
$slashPos = strpos($rptNo, '/');
if ($slashPos !== false) {
    $rptNo = substr($rptNo, 0, $slashPos);
}

// === Barcode 1D (Code 39) ของเลขที่รับ/เลขรายงาน — สแกนแล้วได้เลขที่รับ/เลขรายงาน ===
$barcodeValue = $data['receiveNoti_No'] ?? '';
$reportBarcodeSvg = renderCode39Barcode($barcodeValue);
$reportBarcodeHtml = $reportBarcodeSvg !== ''
    ? '<div class="report-barcode">' . $reportBarcodeSvg . '</div>'
    : '';

$replacements = [
    // Barcode 1D (มุมซ้ายบน)
    '{{report_barcode}}' => $reportBarcodeHtml,

    // Header
    '{{receiveNoti_No}}' => '',
    '{{receiveNotiReportNo}}' => htmlspecialchars(convertDocNoToThai($data['receiveNoti_No'] ?? '')),
    '{{reportYear2}}' => htmlspecialchars($reportYear2),

    // 1. เขียนที่
    '{{location_create}}' => htmlspecialchars($data['location_create'] ?? ''),
    '{{createDay}}' => htmlspecialchars($createDay),
    '{{createMonth}}' => htmlspecialchars($createMonth),
    '{{createYear}}' => htmlspecialchars($createYear),
    '{{createTime}}' => htmlspecialchars($createTime),

    '{{letter_details_block}}' => $letterDetailsBlock,
    '{{daily_no}}' => htmlspecialchars($data['daily_no'] ?? ''),

    // 2. รับแจ้งเหตุ
    '{{complaints_From}}' => htmlspecialchars($data['complaints_From'] ?? ''),
    '{{chk_letter}}' => $chk_letter,
    '{{chk_phone}}' => $chk_phone,
    '{{chk_radio}}' => $chk_radio,
    '{{chk_other_device}}' => $chk_other_device,
    '{{device_other_text}}' => htmlspecialchars($device_other_text),

    // 3. เหตุที่รับแจ้ง
    '{{chk_type_01}}' => $chk_type_01,
    '{{chk_type_02}}' => $chk_type_02,
    '{{chk_type_03}}' => $chk_type_03,
    '{{chk_type_04}}' => $chk_type_04,
    '{{chk_type_05}}' => $chk_type_05,
    '{{chk_type_06}}' => $chk_type_06,
    '{{chk_type_07}}' => $chk_type_07,
    '{{chk_type_08}}' => $chk_type_08,
    '{{chk_type_09}}' => $chk_type_09,
    '{{complaints_type_other}}' => htmlspecialchars($data['complaints_type_other'] ?? ''),

    // 4. สถานที่เกิดเหตุ
    '{{location_crime_1}}' => htmlspecialchars($data['location_crime'] ?? ''),

    // 5. วัน เวลา
    '{{occurrence_date}}' => htmlspecialchars($occurrenceDate),
    '{{occurrence_time}}' => htmlspecialchars($occurrenceTime),

    // 6. ข้อมูลเบื้องต้น
    '{{basic_info_1}}' => htmlspecialchars($data['basic_Info'] ?? ''),

    // 7. พนักงานสอบสวน
    '{{inquiry_official_full_name}}' => htmlspecialchars($data['inquiry_official_full_name'] ?? ''),
    '{{inquiry_official_phone}}' => htmlspecialchars($data['inquiry_official_phone'] ?? ''),

    // 8. ผู้เสียหาย
    '{{suffer_full_name}}' => htmlspecialchars($data['suffer_full_name'] ?? ''),
    '{{suffer_phone}}' => htmlspecialchars($data['suffer_phone'] ?? ''),

    // ลายเซ็น 1 (ผู้รับแจ้ง)
    '{{sig1_img}}' => $sig1_img,
    '{{sig1_name}}' => htmlspecialchars($sig1_name),
    '{{sig1_rank}}' => htmlspecialchars($sig1_rank),
    '{{sig1_position}}' => htmlspecialchars($sig1_position),

    // ลายเซ็น 2 (หัวหน้าทีม)
    '{{sig2_img}}' => $sig2_img,
    '{{sig2_rank}}' => htmlspecialchars($sig2_rank), 
    '{{sig2_name}}' => htmlspecialchars($sig2_name),
    '{{sig2_position}}' => htmlspecialchars($sig2_position),

    // ลายเซ็น 3 (ผู้อนุมัติ)
    '{{sig3_img}}' => $sig3_img,
    '{{sig3_name}}' => htmlspecialchars($sig3_name),
    '{{sig3_name_top}}' => htmlspecialchars($sig2_name),
    '{{sig3_rank}}' => htmlspecialchars($sig3_rank),
    '{{sig3_position}}' => htmlspecialchars($sig3_position),
    '{{sig3_position_top}}' => htmlspecialchars($sig3_position),

    // ข้อความ "เรียน" จาก type
    '{{review_type_text}}' => htmlspecialchars($reviewTypeText),
    '{{approve_type_text}}' => htmlspecialchars($approveTypeText),

    // วันที่ PDF
    '{{pdf_day}}' => htmlspecialchars($pdfDay),
    '{{pdf_month}}' => htmlspecialchars($pdfMonth),
    '{{pdf_year}}' => htmlspecialchars($pdfYear),
];

$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// ==========================================
// 6. OUTPUT HTML
// ==========================================
header('Content-Type: text/html; charset=utf-8');
echo $htmlContent;
