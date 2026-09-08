<?php
/**
 * gen_memo_report.php — บันทึกข้อความ "ส่งรายงานการตรวจสถานที่เกิดเหตุ"
 *
 * เขียนโครงสร้างเป็น HTML ก่อน แล้ว output เป็นไฟล์ Word (.doc)
 * ใช้ตราครุฑจากไฟล์ images/Picture1.jpg เป็นหัวกระดาษ
 *
 * รับ parameter (GET):
 *   incident_id : รหัสคดี (required)
 *   type        : ประเภทคดี (01–08)
 *   location    : indoor / outdoor
 *   report_no   : เลขที่รายงาน
 */

require_once __DIR__ . '/word_report_helper.php';
require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../helpers/report_no.php';

$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;
$type        = isset($_GET['type']) ? preg_replace('/[^0-9]/', '', $_GET['type']) : '';
$location    = isset($_GET['location']) ? $_GET['location'] : '';
$report_no   = isset($_GET['report_no']) ? trim($_GET['report_no']) : '';

if ($incident_id <= 0) {
    header('Content-Type: text/plain; charset=utf-8');
    die('Error: กรุณาระบุ incident_id');
}

// ==========================================
// HELPER
// ==========================================
function memoThaiDate($datetime)
{
    if (empty($datetime)) return '';
    $ts = strtotime(str_replace('T', ' ', $datetime));
    if ($ts === false) return '';
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}

function memoThaiTime($datetime)
{
    if (empty($datetime)) return '';
    $ts = strtotime(str_replace('T', ' ', $datetime));
    if ($ts === false) return '';
    return date('H.i', $ts);
}

/** เดือนย่อ + ปี พ.ศ. เช่น "พ.ย. 2562" */
function memoThaiMonthYear($datetime)
{
    if (empty($datetime)) return '';
    $ts = strtotime(str_replace('T', ' ', $datetime));
    if ($ts === false) return '';
    $months = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    return $months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}

/** ช่องเติมข้อมูล: ถ้ามีค่า → แสดงค่า, ถ้าไม่มี → เส้นจุดให้เขียนเอง */
function memoFill($value, $minWidth = '60pt')
{
    $value = trim((string)$value);
    if ($value === '') {
        return '<span style="display:inline-block;min-width:' . $minWidth . ';border-bottom:1px dotted #555;">&nbsp;</span>';
    }
    return '<span style="border-bottom:1px dotted #555;padding:0 3pt;">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</span>';
}

/** ช่องตัวเลข (จำนวน): ถ้ามีค่า → ตัวเลข, ถ้าไม่มี → เส้นจุดให้เขียนเอง */
function memoNum($value, $minWidth = '34pt')
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0') {
        return '<span style="display:inline-block;min-width:' . $minWidth . ';border-bottom:1px dotted #555;">&nbsp;</span>';
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * นับจำนวนแผ่นของรายงานจริง โดย capture HTML จาก generator เดียวกับ PDF/DOCX
 * แยกไว้ในฟังก์ชันเพื่อกันตัวแปร/ฟังก์ชันของ generator รั่วมาทับ scope หลัก
 */
function memoCountReportSheets($incidentId, $type, $location)
{
    global $pdo; // generator เรียกใช้ $pdo (db_config ถูก require_once ไปแล้ว จึงต้องส่งผ่าน global)

    $genMap = [
        '01' => 'gen_pdf_property_report_html.php',
        '02' => ($location === 'outdoor' ? 'gen_pdf_life_report_outdoor_html.php' : 'gen_pdf_life_report_indoor_html.php'),
        '03' => ($location === 'outdoor' ? 'gen_pdf_bomb_report_outdoor_html.php' : 'gen_pdf_bomb_report_indoor_html.php'),
        '04' => 'gen_pdf_fire_report_html.php',
        '05' => 'gen_pdf_traffic_report_html.php',
        '06' => 'gen_pdf_fingerprint_report_html.php',
        '07' => 'gen_pdf_scene_evidence_report_html.php',
        '08' => 'gen_pdf_person_evidence_report_html.php',
    ];
    $genFile = $genMap[$type] ?? '';
    if ($genFile === '' || !file_exists(__DIR__ . '/' . $genFile)) {
        return 0;
    }

    $_GET['incident_id'] = $incidentId;
    if (!defined('WORD_REPORT_CAPTURE')) {
        define('WORD_REPORT_CAPTURE', true);
    }

    $prevDisplay = ini_get('display_errors');
    ini_set('display_errors', '0');
    ob_start();
    try {
        include __DIR__ . '/' . $genFile;
    } catch (\Throwable $e) {
        // ignore
    }
    $reportHtml = ob_get_clean();
    ini_set('display_errors', $prevDisplay);

    if (trim((string)$reportHtml) === '') {
        return 0;
    }
    return count(stripReportHtmlPages($reportHtml));
}

// ==========================================
// FETCH DATA
// ==========================================
$rn = [];
try {
    $stmt = $pdo->prepare("SELECT id, receiveNoti_No, receiveNotiReportNo, complaints_From, province,
                                  complaints_type, complaints_From_Device, userReviewType, userReviewTypeVal
                           FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
    $stmt->execute([$incident_id]);
    $rn = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $rn = [];
}

$reportData = null;
$checklistData = null;
$countReport = 0;
try {
    $stmt = $pdo->prepare("SELECT incident_report_data, incident_checklist_data, COALESCE(count_report,0) AS count_report
                           FROM incident_checklist_transaction
                           WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tx) {
        $reportData = !empty($tx['incident_report_data']) ? json_decode($tx['incident_report_data'], true) : null;
        $checklistData = !empty($tx['incident_checklist_data']) ? json_decode($tx['incident_checklist_data'], true) : null;
        $countReport = (int)$tx['count_report'];
    }
} catch (Exception $e) {
    // ignore
}

$gi = is_array($checklistData) && isset($checklistData['general_info']) ? $checklistData['general_info'] : [];

// ประเภทคดี (คำสั้น)
$typeWordMap = [
    '01' => 'ทรัพย์',
    '02' => 'ชีวิต',
    '03' => 'ระเบิด',
    '04' => 'เพลิงไหม้',
    '05' => 'จราจร',
    '06' => 'ตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)',
    '07' => 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ',
    '08' => 'ตรวจเก็บวัตถุพยานบุคคล',
];
if ($type === '' && isset($rn['complaints_type'])) {
    $type = $rn['complaints_type'];
}
$typeWord = $typeWordMap[$type] ?? '';
$locLabel = $location === 'indoor' ? ' (ในอาคาร)' : ($location === 'outdoor' ? ' (นอกอาคาร)' : '');

// หน่วยงาน (ศพฐ.)
$agencyVal = $rn['userReviewTypeVal'] ?? '';
$agencyDept = 'กลุ่มงานตรวจสถานที่เกิดเหตุ ศพฐ.' . ($agencyVal !== '' ? $agencyVal : '10');
$agencyShort = 'ศพฐ.' . ($agencyVal !== '' ? $agencyVal : '10');

// สถานี / จังหวัด
$station = $rn['complaints_From'] ?? '';

// ช่องทางรับแจ้ง
$device = $rn['complaints_From_Device'] ?? '';
$channelText = $device === 'r' ? 'ทางวิทยุสื่อสาร' : ($device === 't' ? 'ทางโทรศัพท์' : 'ที่รับแจ้ง');

// วัน/เวลา รับแจ้ง
$reportDateRaw = $gi['report_datetime'] ?? ($gi['incident_datetime'] ?? '');
$reportDateThai = memoThaiDate($reportDateRaw);
$reportTimeThai = memoThaiTime($reportDateRaw);

// สถานที่เกิดเหตุ
$crimeLocation = $gi['location_detail'] ?? '';

// เลขที่รายงาน
$reportNoFinal = $report_no !== '' ? $report_no : ($rn['receiveNotiReportNo'] ?? ($gi['report_no'] ?? ''));
$reportNoFinal = convertReportNoToThai($reportNoFinal); // ให้เป็นภาษาไทยเสมอ (idempotent)
// วันที่ลงรายงาน (เดือน ปี) เช่น "พ.ย. 2562"
$reportMonthYear = memoThaiMonthYear($reportDateRaw);

// จำนวนภาพถ่าย
$photoCount = 0;
if (isset($pdo)) {
    $photos = wordFetchIncidentPhotos($pdo, $incident_id);
    $photoCount = count($photos);
}

// ==========================================
// นับจำนวนแผ่นรายงานจริง (จาก generator เดียวกับ PDF/DOCX)
// ==========================================
$reportSheets = memoCountReportSheets($incident_id, $type, $location);

$diagramSheets = 1;                 // แผงผังประกอบรายงาน
$reportSheetCount = $reportSheets > 0 ? $reportSheets : 0;
// รวมเอกสาร = แผ่นรายงาน + แผงผัง + ภาพถ่าย (1 ภาพ/แผ่น)
$totalSheets = $reportSheetCount + $diagramSheets + $photoCount;

// ==========================================
// BUILD HTML (โครงสร้างบันทึกข้อความ)
// ==========================================
$stack = wordFontStack();

// ตราครุฑ → ฝังเป็น base64 (ให้ Word แสดงผลได้แน่นอน)
$garudaPath = __DIR__ . '/../../images/Picture1.jpg';
$garudaImg = '';
if (is_readable($garudaPath)) {
    $bytes = file_get_contents($garudaPath);
    if ($bytes !== false) {
        $mime = 'image/jpeg';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $m = $finfo->buffer($bytes);
            if ($m) $mime = $m;
        }
        $uri = 'data:' . $mime . ';base64,' . base64_encode($bytes);
        $garudaImg = '<img src="' . $uri . '" width="52" height="55" style="width:52px;height:55px;" alt="ตราครุฑ"/>';
    }
}

// หมายเหตุ: ไม่ใส่ font-family ใน inline style เพราะชื่อฟอนต์มีเครื่องหมาย " ครอบ
// ซึ่งจะทำให้ attribute style="..." ขาดกลางคันใน Word — กำหนด font-family ไว้ใน <style> ส่วนหัวแทน
$base = 'font-size:16pt;color:#000;line-height:1.7;';
$indent = 'text-indent:48pt;';

ob_start();
?>
<div class="memo-doc" style="<?= $base ?>">

    <table style="width:100%;border:none;border-collapse:collapse;margin:0 0 8pt 0;">
        <tr>
            <td style="width:70px;border:none;vertical-align:top;text-align:left;padding:0;"><?= $garudaImg ?></td>
            <td style="border:none;vertical-align:middle;text-align:center;<?= $base ?>font-size:22pt;font-weight:bold;">บันทึกข้อความ</td>
            <td style="width:70px;border:none;padding:0;"></td>
        </tr>
    </table>

    <table style="width:100%;border:none;border-collapse:collapse;">
        <tr>
            <td style="width:63%;border:none;padding:0 0 2pt 0;"><b>ส่วนราชการ</b> <?= memoFill($agencyDept, '150pt') ?></td>
            <td style="width:37%;border:none;padding:0 0 2pt 0;"><b>โทร</b> <?= memoFill('0-2529-2233', '78pt') ?></td>
        </tr>
        <tr>
            <td style="border:none;padding:0 0 2pt 0;"><b>ที่</b> <?= memoFill('0032.42/', '110pt') ?></td>
            <td style="border:none;padding:0 0 2pt 0;"><b>วันที่</b> <?= memoFill($reportDateThai, '105pt') ?></td>
        </tr>
    </table>

    <p style="margin:0 0 2pt 0;<?= $base ?>">
        <b>เรื่อง</b>&nbsp;&nbsp;ส่งรายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับ<?= memoFill($typeWord . $locLabel, '70pt') ?>
    </p>
    <p style="margin:0 0 8pt 0;<?= $base ?>">
        <b>เรียน</b>&nbsp;&nbsp;ผกก.<?= memoFill($station, '120pt') ?>
    </p>

    <p style="margin:0 0 4pt 0;<?= $indent . $base ?>">
        ตามรับแจ้ง<?= memoFill($channelText, '80pt') ?>จาก <?= memoFill($station, '120pt') ?>
        เมื่อวันที่ <?= memoFill($reportDateThai, '110pt') ?> เวลาประมาณ <?= memoFill($reportTimeThai, '50pt') ?> น.
        เพื่อขอเจ้าหน้าที่<?= htmlspecialchars($agencyDept, ENT_QUOTES, 'UTF-8') ?>
        ตรวจสถานที่เกิดเหตุคดีเกี่ยวกับ<?= memoFill($typeWord . $locLabel, '70pt') ?>
        เหตุเกิดที่ <?= memoFill($crimeLocation, '260pt') ?>
        เพื่อนำเป็นหลักฐานประกอบทางคดีความ ละเอียดแจ้งแล้วนั้น
    </p>

    <p style="margin:0 0 4pt 0;<?= $indent . $base ?>">
        บัดนี้ได้ทำรายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับ<?= htmlspecialchars($typeWord, ENT_QUOTES, 'UTF-8') ?>
        ดังกล่าวเสร็จเรียบร้อยแล้ว จึงขอส่ง
    </p>

    <table style="border:none;border-collapse:collapse;margin:0 0 6pt 56pt;">
        <tr>
            <td style="border:none;padding:0 0 3pt 0;width:300pt;">๑. รายงานที่ <?= memoFill(convertReportNoToThai($reportNoFinal), '95pt') ?> ลง <?= memoFill($reportMonthYear, '80pt') ?></td>
            <td style="border:none;padding:0 0 3pt 0;white-space:nowrap;">จำนวน</td>
            <td style="border:none;padding:0 0 3pt 8pt;text-align:center;"><?= memoNum($reportSheetCount) ?></td>
            <td style="border:none;padding:0 0 3pt 6pt;white-space:nowrap;">แผ่น</td>
        </tr>
        <tr>
            <td style="border:none;padding:0 0 3pt 0;">๒. แผงผังประกอบรายงาน</td>
            <td style="border:none;padding:0 0 3pt 0;white-space:nowrap;">จำนวน</td>
            <td style="border:none;padding:0 0 3pt 8pt;text-align:center;"><?= memoNum($diagramSheets) ?></td>
            <td style="border:none;padding:0 0 3pt 6pt;white-space:nowrap;">แผ่น</td>
        </tr>
        <tr>
            <td style="border:none;padding:0;">๓. ภาพถ่ายประกอบรายงาน</td>
            <td style="border:none;padding:0;white-space:nowrap;">จำนวน</td>
            <td style="border:none;padding:0 0 0 8pt;text-align:center;"><?= memoNum($photoCount) ?></td>
            <td style="border:none;padding:0 0 0 6pt;white-space:nowrap;">ภาพ</td>
        </tr>
    </table>

    <p style="margin:0 0 8pt 0;<?= $base ?>">
        รวมเอกสารจำนวน <?= memoNum($reportSheetCount > 0 ? $totalSheets : '', '46pt') ?> แผ่น มาพร้อมนี้ด้วยแล้ว
    </p>

    <p style="margin:0 0 18pt 0;<?= $indent . $base ?>">จึงเรียนมาเพื่อทราบ</p>

    <table style="width:100%;border:none;border-collapse:collapse;margin-top:8pt;">
        <tr>
            <td style="width:55%;border:none;"></td>
            <td style="width:45%;border:none;text-align:center;line-height:2;<?= $base ?>">
                <div style="font-weight:bold;">พ.ต.อ.</div>
                <div>(................................)</div>
                <div style="font-weight:bold;">นวท. (สบ 4) กสภ.ฯ ปรท. รอง ผบก.<?= htmlspecialchars($agencyShort, ENT_QUOTES, 'UTF-8') ?></div>
            </td>
        </tr>
    </table>

</div>
<?php
$memoHtml = ob_get_clean();

// ==========================================
// WORD HTML DOCUMENT WRAPPER
// ==========================================
$pageRule = '@page WordSection1{size:21cm 29.7cm;margin:2.0cm 2.0cm 1.5cm 2.5cm;}';

$wordHtml = '<html xmlns:o="urn:schemas-microsoft-com:office:office"'
    . ' xmlns:w="urn:schemas-microsoft-com:office:word"'
    . ' xmlns="http://www.w3.org/TR/REC-html40">'
    . '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>'
    . '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom>'
    . '<w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->'
    . '<style>'
    . '@font-face{font-family:"Sarabun";font-weight:400;font-style:normal;'
    . 'src:url("/csims/fonts/Sarabun/Sarabun-Regular.ttf") format("truetype");}'
    . '@font-face{font-family:"Sarabun";font-weight:700;font-style:normal;'
    . 'src:url("/csims/fonts/Sarabun/Sarabun-Bold.ttf") format("truetype");}'
    . $pageRule
    . 'div.WordSection1{page:WordSection1;}'
    . 'body,p,span,td,div,b,strong{font-family:' . $stack . ';font-size:16pt;'
    . 'mso-fareast-font-family:"TH SarabunIT๙";mso-bidi-font-family:"TH SarabunIT๙";'
    . 'mso-ascii-font-family:"TH SarabunIT๙";mso-hansi-font-family:"TH SarabunIT๙";}'
    . 'body{margin:0;padding:0;}'
    . 'p{margin:0;padding:0;}'
    . 'td{vertical-align:top;}'
    . 'table{border-collapse:collapse;}'
    . '</style></head><body><div class="WordSection1">' . $memoHtml . '</div></body></html>';

// ==========================================
// FILENAME + OUTPUT
// ==========================================
$baseName = 'บันทึกข้อความ ' . ($typeWord !== '' ? $typeWord . $locLabel : '');
$baseName .= ' ' . ($reportNoFinal !== '' ? $reportNoFinal : $incident_id);
$baseName = trim(str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|', "\n", "\r"], '-', $baseName));

$filenameAscii = preg_replace('/[^\x20-\x7E]/', '', $baseName);
$filenameAscii = trim($filenameAscii) !== '' ? $filenameAscii : ('memo_' . $incident_id);
$filenameAscii .= '.doc';
$filenameUtf8 = $baseName . '.doc';

header('Cache-Control: max-age=0');
header('Content-Type: application/vnd.ms-word; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$filenameAscii\"; filename*=UTF-8''" . rawurlencode($filenameUtf8));
echo $wordHtml;
