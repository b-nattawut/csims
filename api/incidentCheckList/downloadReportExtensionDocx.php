<?php
/**
 * API: downloadReportExtensionDocx.php
 */
require '../../db_config.php';
session_start();
require '../../vendor/autoload.php';
require __DIR__ . '/../../helpers/report_no.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

$incidentId = isset($_GET['incident_id']) ? (int)$_GET['incident_id'] : 0;
if ($incidentId <= 0) {
    http_response_code(400);
    echo 'Missing incident_id';
    exit;
}

$stmt = $pdo->prepare("SELECT ict.incident_extend_time_data, rn.receiveNotiReportNo_TH
    FROM incident_checklist_transaction ict
    LEFT JOIN rn_ReceiveNoti rn ON rn.id = ict.incident_id
    WHERE ict.incident_id = ? ORDER BY ict.create_date DESC LIMIT 1");
$stmt->execute([$incidentId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$data = [];
if ($row && !empty($row['incident_extend_time_data'])) {
    $data = json_decode($row['incident_extend_time_data'], true) ?: [];
}
$reportNo = $row['receiveNotiReportNo_TH'] ?? '';

function toThaiDate($dateStr) {
    if (empty($dateStr)) return '....................................';
    $d = new DateTime($dateStr);
    $thaiMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    $day = $d->format('j');
    $month = $thaiMonths[(int)$d->format('n') - 1];
    $year = (string)((int)$d->format('Y') + 543);
    return $day . ' ' . $month . ' ' . $year;
}

function val($data, $key, $default = '') {
    return isset($data[$key]) && $data[$key] !== '' ? $data[$key] : $default;
}

$dotLine = '..................................................';
$shortDot = '....................';

$phpWord = new PhpWord();
$phpWord->setDefaultFontName('TH Sarabun New');
$phpWord->setDefaultFontSize(14);

$section = $phpWord->addSection([
    'marginTop'    => 800,
    'marginBottom' => 500,
    'marginLeft'   => 1100,
    'marginRight'  => 900,
]);

$fs = ['size' => 14, 'name' => 'TH Sarabun New'];
$fsBold = ['size' => 14, 'name' => 'TH Sarabun New', 'bold' => true];
$fsUD = ['size' => 14, 'name' => 'TH Sarabun New', 'underline' => 'dotted'];
$fsUL = ['size' => 14, 'name' => 'TH Sarabun New', 'underline' => 'single'];

// --- Header ---
$birdPath = __DIR__ . '/../../images/bird.png';
$ht = $section->addTable();
$ht->addRow();
$hc1 = $ht->addCell(1500);
if (file_exists($birdPath)) {
    $hc1->addImage($birdPath, ['width' => 40, 'height' => 40]);
}
$hc2 = $ht->addCell(7500);
$hc2->addText('บันทึกข้อความ', ['bold' => true, 'size' => 20, 'name' => 'TH Sarabun New'], ['alignment' => Jc::CENTER]);

// --- Info row ---
$it = $section->addTable();
$it->addRow();
$r = $it->addCell(3000)->addTextRun();
$r->addText('ส่วนราชการ', $fsBold);
$r->addText('  กสก.ศพฐ.1', $fs);
$r = $it->addCell(3000)->addTextRun();
$r->addText('โทร. ', $fsBold);
$r->addText('0-2529-2233', $fs);
$r = $it->addCell(3000)->addTextRun();
$r->addText('เลขรายงาน ', $fsBold);
$drn =  $reportNo ?? ''; 
$r->addText($drn ?: $shortDot, $fs);

// --- ที่ / วันที่ ---
$it2 = $section->addTable();
$it2->addRow();
$r = $it2->addCell(5500)->addTextRun();
$r->addText('ที่ 0032.42/-', $fsBold);
$r = $it2->addCell(3500)->addTextRun();
$r->addText('วันที่ ', $fsBold);
$r->addText(toThaiDate(val($data, 'rex_date', '')), $fs);

// --- เรื่อง / เรียน ---
$r = $section->addTextRun();
$r->addText('เรื่อง', $fsBold);
$r->addText('  ขอขยายเวลาการออกรายงานตรวจสถานที่เกิดเหตุ', $fs);

$r = $section->addTextRun();
$r->addText('เรียน', $fsBold);
$r->addText('  นวท.(สบ 4)กสก.ศพฐ.1', $fs);

$section->addTextBreak(0);

// --- เนื้อหาย่อหน้าแรก ---
$ind = ['indentation' => ['firstLine' => 700]];
$ct = val($data, 'rex_complaints_type', $dotLine);
$ca = val($data, 'rex_case_about', $dotLine);
$od = toThaiDate(val($data, 'rex_order_date', ''));
$dd = toThaiDate(val($data, 'rex_due_date', ''));
$cd = toThaiDate(val($data, 'rex_command_date', ''));

$p = $section->addTextRun($ind);
$p->addText('ด้วยข้าฯ ', $fs);
$p->addText(val($data, 'rex_requester_name', $dotLine), $fsUD);
$p->addText(' นวท.(สบ 2) กสก.ศพฐ.1', $fs);
$p->addText(' ผู้ตรวจสถานที่เกิดเหตุ คดีเกี่ยวกับ', $fs);
$p->addText($ct, $fsUL);
$p->addText(' เหตุเกิดที่', $fs);
$p->addText($ca, $fsUD);

$p = $section->addTextRun();
$p->addText('รับแจ้งเหตุและเดินทางไปตรวจสถานที่เกิดเหตุเมื่อวันที่ ', $fs);
$p->addText($od, $fsUD);
$p->addText(' และจะครบกำหนดออกรายงานวันที่ ', $fs);
$p->addText($dd, $fsUD);
$p->addText(' แต่เนื่องจากข้าฯไม่สามารถจัดทำรายงานเสร็จภายในกำหนด ตามคำสั่ง ศพฐ. ที่ 480/2563', $fs);
$p->addText(' ลงวันที่ ', $fs);
$p->addText($cd, $fsUD);
$p->addText(' โดยรายงานดังกล่าว', $fs);

// --- Checkboxes ---
$reasons = $data['rex_reasons[]'] ?? $data['rex_reasons'] ?? [];
if (!is_array($reasons)) $reasons = [];

$reasonTexts = [
    'อยู่ระหว่างรอผลการตรวจพิสูจน์วัตถุพยานของกลุ่มงานตรวจทางเคมี ฟิสิกส์ ศูนย์พิสูจน์หลักฐาน',
    'ของกลางที่ทำการตรวจพิสูจน์มีจำนวนมาก และ/หรือ ต้องใช้เทคนิคและเวลาในการตรวจพิสูจน์',
    'มีเรื่อง(คดี) ที่ถูกอกรรรจ์ สะเทือนขวัญ ที่ผู้บังคับบัญชาสนใจ ซึ่งต้องทำการจัดทำรายงานผลการตรวจก่อน',
    'อื่นๆ',
];

foreach ($reasonTexts as $rText) {
    $checked = in_array($rText, $reasons);
    $cb = $checked ? '☑' : '☐';
    $rr = $section->addTextRun(['indentation' => ['left' => 500]]);
    $rr->addText($cb . ' ', $fs);
    $rr->addText($rText, $fs);
    if ($rText === 'อื่นๆ') {
        $ot = val($data, 'rex_reason_other_text', '');
        if (!empty($ot)) {
            $rr->addText('  ' . $ot, $fsUD);
        }
    }
}

// --- จึงขอขยายเวลา ---
$ec = val($data, 'rex_extension_count', $shortDot);
$p = $section->addTextRun($ind);
$p->addText('จึงขอขยายเวลาจัดทำรายงานการตรวจสถานที่เกิดเหตุ ในคดีดังกล่าวเป็นครั้งที่ ', $fs);
$p->addText($ec, $fsUD);

$p = $section->addTextRun($ind);
$p->addText('จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติขยายเวลาการออกรายงานตรวจสถานที่เกิดเหตุ', $fs);

$section->addTextBreak(0);

// --- ลงชื่อผู้ขอ (ชิดขวา) ---
$st = $section->addTable(['alignment' => Jc::END]);
$st->addRow();
$sc = $st->addCell(4500);
$rn = val($data, 'rex_requester_name', $shortDot);
$sc->addText($rn, $fs, ['alignment' => Jc::CENTER]);
$sc->addText('(                                        )', $fs, ['alignment' => Jc::CENTER]);
$sc->addText('นวท.(สบ 2)กสก.ศพฐ.1', $fs, ['alignment' => Jc::CENTER]);

// --- ส่วนอนุมัติ ---
$ad = val($data, 'rex_approve_days', $shortDot);
$as = toThaiDate(val($data, 'rex_approve_start_date', ''));
$ae = toThaiDate(val($data, 'rex_approve_end_date', ''));

$p = $section->addTextRun();
$p->addText('- อนุมัติขยายเวลาครั้งที่ 1', $fs);
$p->addText(' จำนวน ', $fs);
$p->addText($ad, $fsUD);
$p->addText(' วัน', $fs);

$p = $section->addTextRun();
$p->addText('(ตั้งแต่วันที่ ', $fs);
$p->addText($as, $fsUD);
$p->addText(' – ', $fs);
$p->addText($ae, $fsUD);
$p->addText(')', $fs);

$section->addTextBreak(0);

// --- ลงชื่อผู้อนุมัติ (ชิดซ้าย) ---
$at = $section->addTable(['alignment' => Jc::START]);
$at->addRow();
$ac = $at->addCell(4500);
$an = val($data, 'rex_approver_name', $shortDot);
$ac->addText($an, $fs, ['alignment' => Jc::CENTER]);
$ac->addText('(                                        )', $fs, ['alignment' => Jc::CENTER]);
$ac->addText('นวท.(สบ 4)กสก.ศพฐ.1', $fs, ['alignment' => Jc::CENTER]);
$apd = toThaiDate(val($data, 'rex_approve_date', ''));
$ac->addText($apd, $fs, ['alignment' => Jc::CENTER]);

// --- Output ---
$filename = 'report_extension_' . $incidentId . '.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit;
