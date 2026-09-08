<?php
/**
 * life_indoor_report_docx.php — DOCX รายงานคดีชีวิต ในอาคาร (F-CS-13)
 * โครงเดียวกับคดีทรัพย์: PhpWord API + ระยะแน่น
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/word_docx_dotted_rule.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

const LIFE_IN_DOCX_FONT = 'TH Sarabun New';
const LIFE_IN_DOCX_SIZE = 14;

function lifeInLoadExport(int $incidentId): array
{
    global $pdo;
    if (!($pdo instanceof PDO)) {
        require_once __DIR__ . '/../../db_config.php';
        if (!($pdo instanceof PDO) && isset($GLOBALS['pdo'])) {
            $pdo = $GLOBALS['pdo'];
        }
    }
    if (!($pdo instanceof PDO)) {
        throw new RuntimeException('ไม่สามารถเชื่อมต่อฐานข้อมูลได้');
    }

    if (!defined('WORD_DOCX_MODE')) {
        define('WORD_DOCX_MODE', true);
    }
    if (!defined('WORD_REPORT_CAPTURE')) {
        define('WORD_REPORT_CAPTURE', true);
    }

    $_GET['incident_id'] = $incidentId;

    $prev = ini_get('display_errors');
    ini_set('display_errors', '0');
    ob_start();
    include __DIR__ . '/gen_pdf_life_report_indoor_html.php';
    ob_end_clean();
    ini_set('display_errors', $prev);

    $export = $GLOBALS['life_indoor_report_export'] ?? null;
    if (!is_array($export) || empty($export['replacements'])) {
        throw new RuntimeException('ไม่สามารถโหลดข้อมูลรายงานคดีชีวิต (ในอาคาร) ได้');
    }
    return $export;
}

function lifeInFs(array $extra = []): array
{
    return array_merge([
        'name' => LIFE_IN_DOCX_FONT,
        'size' => LIFE_IN_DOCX_SIZE,
        'color' => '000000',
    ], $extra);
}

function lifeInPs(int $indentPt = 0, array $extra = []): array
{
    $style = ['spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 1.15];
    if ($indentPt > 0) {
        $style['indentation'] = ['left' => $indentPt * 20];
    }
    return array_merge($style, $extra);
}

function lifeInText(string $htmlOrText): string
{
    $t = html_entity_decode(strip_tags($htmlOrText), ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', trim($t));
    return $t ?? '';
}

function lifeInR(array $r, string $key): string
{
    return lifeInText($r[$key] ?? '');
}

function lifeInCb(array $r, string $key): string
{
    $m = lifeInText((string) ($r[$key] ?? ''));
    return ($m !== '' && $m !== '☐') ? '☑' : '☐';
}

function lifeInDot(string $value, int $dots = 24): string
{
    $v = trim($value);
    return $v !== '' ? $v : str_repeat('.', $dots);
}

function lifeInLine($section, string $text, int $indentPt = 0, array $fontExtra = [], array $paraExtra = []): void
{
    $t = trim($text);
    $isBlankish = ($t === '' || preg_match('/^\.+$/u', $t) || preg_match('/^\d+(?:\.\d+)*\.\s*$/u', $t));
    if ($isBlankish && wordDocxTryDottedBlankLine(
        $section,
        $text,
        ['name' => LIFE_IN_DOCX_FONT, 'size' => LIFE_IN_DOCX_SIZE, 'color' => '000000'],
        function (int $i, array $e = []) { return lifeInPs($i, $e); },
        $indentPt,
        $fontExtra
    )) {
        return;
    }
    $section->addText($t === '' ? ' ' : $t, lifeInFs($fontExtra), lifeInPs($indentPt, $paraExtra));
}

function lifeInAddDynamicLines($section, string $html): void
{
    if (trim($html) === '') {
        lifeInLine($section, '', 20);
        return;
    }
    if (!preg_match_all('/<div([^>]*)>((?:(?!<div\b).)*)<\/div>/is', $html, $matches, PREG_SET_ORDER)) {
        $plain = lifeInText($html);
        lifeInLine($section, $plain !== '' ? $plain : '', 20);
        return;
    }
    foreach ($matches as $match) {
        $indent = 20;
        if (preg_match('/\bi3\b/', $match[1])) {
            $indent = 60;
        } elseif (preg_match('/\bi2\b/', $match[1])) {
            $indent = 40;
        }
        $text = lifeInText($match[2]);
        lifeInLine($section, $text, $indent);
    }
}

function lifeInPreparePhoto($photo): ?array
{
    $uri = is_array($photo) ? ($photo['uri'] ?? '') : (string) $photo;
    if ($uri === '' || strpos($uri, 'data:image') !== 0) {
        return null;
    }
    if (!preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,(.+)$#', $uri, $m)) {
        return null;
    }
    $mime = strtolower($m[1]);
    $bytes = base64_decode($m[2], true);
    if ($bytes === false || $bytes === '') {
        return null;
    }
    $ext = '.jpg';
    if (strpos($mime, 'png') !== false) {
        $ext = '.png';
    } elseif (strpos($mime, 'gif') !== false) {
        $ext = '.gif';
    } elseif (strpos($mime, 'webp') !== false && function_exists('imagecreatefromstring')) {
        $im = @imagecreatefromstring($bytes);
        if ($im === false) {
            return null;
        }
        ob_start();
        imagepng($im);
        $bytes = ob_get_clean();
        imagedestroy($im);
        $ext = '.png';
    }
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'life_in_photo_' . uniqid('', true) . $ext;
    if (file_put_contents($path, $bytes) === false) {
        return null;
    }
    return ['path' => $path];
}

/**
 * @return array{0: PhpWord, 1: string[]}
 */
function buildLifeIndoorReportPhpWord(array $replacements, array $photoDataUris = []): array
{
    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName(LIFE_IN_DOCX_FONT);
    $phpWord->setDefaultFontSize(LIFE_IN_DOCX_SIZE);
    $phpWord->setDefaultParagraphStyle(lifeInPs(0));

    $settings = $phpWord->getSettings();
    $settings->setHideSpellingErrors(true);
    $settings->setHideGrammaticalErrors(true);
    if (class_exists('\\PhpOffice\\PhpWord\\Style\\Language') && method_exists($settings, 'setThemeFontLang')) {
        try {
            $settings->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('th-TH', 'th-TH'));
        } catch (Throwable $e) {
        }
    }

    $section = $phpWord->addSection([
        'marginTop' => 680,
        'marginBottom' => 680,
        'marginLeft' => 850,
        'marginRight' => 850,
    ]);

    $r = $replacements;
    $reportNo = lifeInR($r, '{{report_no}}');
    $yearShort = lifeInR($r, '{{report_year_short}}');
    $agency = lifeInR($r, '{{agency_name}}');

    // Header
    $header = $section->addHeader();
    $ht = $header->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $ht->addRow();
    $c1 = $ht->addCell(7500);
    $run = $c1->addTextRun(lifeInPs(0));
    $run->addText('รายงานการตรวจสถานที่เกิดเหตุที่', lifeInFs(['underline' => 'single']));
    $run->addText('  ' . lifeInDot($reportNo, 10) . ' /25 ' . lifeInDot($yearShort, 4), lifeInFs());
    $c2 = $ht->addCell(2000);
    $c2->addPreserveText('{PAGE}/{NUMPAGES}', lifeInFs(), ['alignment' => Jc::END]);
    $header->addText('หน่วยงาน  ' . lifeInDot($agency, 40), lifeInFs(), lifeInPs(0, ['spaceAfter' => 60]));

    // Footer F-CS-13
    $footer = $section->addFooter();
    $ft = $footer->addTable([
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT,
        'borderBottomSize' => 6,
        'borderBottomColor' => 'BFBFBF',
    ]);
    $ft->addRow();
    $fl = $ft->addCell(5000);
    $fl->addText('ปฏิบัติงานตาม', lifeInFs(['size' => 10, 'color' => '808080']), lifeInPs(0));
    $fl->addText('OPFS – CS – SP – 02', lifeInFs(['size' => 10, 'color' => '808080']), lifeInPs(0));
    $fr = $ft->addCell(5000);
    foreach (['F-CS-13 แก้ไขครั้งที่ 2', 'แก้ไขวันที่ 2 ก.ย. 63', 'เริ่มใช้ 1 ต.ค. 63'] as $fline) {
        $fr->addText($fline, lifeInFs(['size' => 10, 'color' => '808080']), lifeInPs(0, ['alignment' => Jc::END]));
    }

    // ===== หน้า 1 =====
    lifeInLine($section, 'รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต (ในอาคาร)', 0, [
        'bold' => true, 'size' => 15, 'underline' => 'single',
    ], ['alignment' => Jc::CENTER, 'spaceBefore' => 60, 'spaceAfter' => 60]);

    lifeInLine($section, '1. การรับแจ้งเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    lifeInLine(
        $section,
        'เมื่อวันที่ ' . lifeInDot(lifeInR($r, '{{receive_date}}'), 18)
        . ' เวลา ' . lifeInDot(lifeInR($r, '{{receive_time}}'), 8)
        . ' น. ตามประจำวันข้อที่ ' . lifeInDot(lifeInR($r, '{{daily_ref}}'), 10),
        20
    );
    lifeInLine(
        $section,
        // ★ ข้อ 7: ออกเป็นข้อความไทย แทนช่องติ๊ก
        'ได้รับแจ้งตาม  ' . lifeInDot(lifeInR($r, '{{notify_method_text}}'), 30),
        20
    );
    lifeInLine(
        $section,
        'จาก สน./สภ. ' . lifeInDot(lifeInR($r, '{{from_station}}'), 20)
        . ' ขอเจ้าหน้าที่ ร่วมตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต',
        20
    );
    lifeInLine(
        $section,
        'โดยมี ' . lifeInDot(lifeInR($r, '{{investigator_name}}'), 30)
        . ' เป็นพนักงานสอบสวนเจ้าของคดี',
        20
    );

    lifeInLine($section, '2. สถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    lifeInLine(
        $section,
        lifeInDot(lifeInR($r, '{{victim_rows}}'), 30)
        . ' อายุประมาณ ' . lifeInDot(lifeInR($r, '{{victim_age}}'), 4) . ' ปี',
        20
    );

    lifeInLine($section, '3. วันเวลาที่ทราบเหตุ/เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    lifeInLine(
        $section,
        'ผู้เสียหายทราบเหตุ/เกิดเหตุ เมื่อวันที่ ' . lifeInDot(lifeInR($r, '{{victim_know_date}}'), 18)
        . ' เวลาประมาณ ' . lifeInDot(lifeInR($r, '{{victim_know_time}}'), 8) . ' น.',
        20
    );
    lifeInLine(
        $section,
        'พนักงานสอบสวนทราบเหตุ เมื่อวันที่ ' . lifeInDot(lifeInR($r, '{{officer_know_date}}'), 18)
        . ' เวลาประมาณ ' . lifeInDot(lifeInR($r, '{{officer_know_time}}'), 8) . ' น.',
        20
    );

    lifeInLine($section, '4. วันเวลาตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    lifeInLine(
        $section,
        'ตรวจสถานที่เกิดเหตุ เมื่อวันที่ ' . lifeInDot(lifeInR($r, '{{inspect_date}}'), 18)
        . ' เวลาประมาณ ' . lifeInDot(lifeInR($r, '{{inspect_time}}'), 8) . ' น.',
        20
    );
    lifeInLine(
        $section,
        'ตรวจสถานที่เกิดเหตุเพิ่มเติม เมื่อวันที่ ' . lifeInDot(lifeInR($r, '{{inspect_add_date}}'), 18)
        . ' เวลาประมาณ ' . lifeInDot(lifeInR($r, '{{inspect_add_time}}'), 8) . ' น.',
        20
    );

    lifeInLine($section, '5. ผู้ตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    lifeInAddDynamicLines($section, $r['{{inspector_rows}}'] ?? '');

    lifeInLine($section, '6. ลักษณะของสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    lifeInLine($section, '6.1 ลักษณะภายนอก', 20, ['bold' => true]);
    lifeInLine(
        $section,
        'เป็น บ้าน/ตึกแถว/อาคาร/อื่น ๆ ' . lifeInDot(lifeInR($r, '{{building_type_text}}'), 16)
        . ' ชั้น จำนวน ' . lifeInDot(lifeInR($r, '{{floor_count}}'), 4)
        . ' หลัง/คูหา  '
        . lifeInCb($r, '{{chk_mezzanine}}') . 'มี / '
        . lifeInCb($r, '{{chk_no_mezzanine}}') . 'ไม่มี ชั้นลอย',
        40
    );
    lifeInLine(
        $section,
        lifeInCb($r, '{{chk_fence_yes}}') . 'มี / '
        . lifeInCb($r, '{{chk_fence_no}}') . 'ไม่มี ดาดฟ้า  ปลูกอยู่ภายในบริเวณ '
        . lifeInCb($r, '{{chk_surround_fence_yes}}') . 'มี / '
        . lifeInCb($r, '{{chk_surround_fence_no}}') . 'ไม่มี รั้วล้อมรอบ เมื่อหันหน้าเข้า',
        40
    );
    lifeInLine($section, 'ด้านหน้าติด ' . lifeInDot(lifeInR($r, '{{ext_front}}'), 40), 40);
    lifeInLine($section, 'ด้านซ้ายติด ' . lifeInDot(lifeInR($r, '{{ext_left}}'), 40), 40);
    lifeInLine($section, 'ด้านขวาติด ' . lifeInDot(lifeInR($r, '{{ext_right}}'), 40), 40);
    lifeInLine($section, 'ด้านหลังติด ' . lifeInDot(lifeInR($r, '{{ext_back}}'), 40), 40);

    // ===== หน้า 2 =====
    $section->addPageBreak();
    lifeInLine($section, '6.2 ลักษณะภายใน', 20, ['bold' => true]);
    lifeInLine($section, lifeInDot(lifeInR($r, '{{interior_detail}}'), 60), 40);

    lifeInLine($section, '6.3 บริเวณที่เกิดเหตุ', 20, ['bold' => true], ['spaceBefore' => 40]);
    lifeInLine($section, 'เกิดเหตุที่ ' . lifeInDot(lifeInR($r, '{{incident_area}}'), 40), 40);
    lifeInLine($section, 'ซึ่งมีขนาด กว้าง x ยาว ประมาณ ' . lifeInDot(lifeInR($r, '{{area_size}}'), 30), 40);

    lifeInLine($section, 'ลักษณะโครงสร้าง', 40, ['bold' => true, 'underline' => 'single'], [
        'alignment' => Jc::CENTER, 'spaceBefore' => 60, 'spaceAfter' => 40,
    ]);
    lifeInLine(
        $section,
        'ฝาผนังด้านหน้า ' . lifeInDot(lifeInR($r, '{{wall_front}}'), 16)
        . ' หน้าต่าง ' . lifeInDot(lifeInR($r, '{{wall_front_window}}'), 4) . ' บาน'
        . ' ประตู ' . lifeInDot(lifeInR($r, '{{wall_front_door}}'), 4) . ' บาน',
        40
    );
    lifeInLine(
        $section,
        'ฝาผนังด้านซ้าย ' . lifeInDot(lifeInR($r, '{{wall_left}}'), 16)
        . ' หน้าต่าง ' . lifeInDot(lifeInR($r, '{{wall_left_window}}'), 4) . ' บาน'
        . ' ประตู ' . lifeInDot(lifeInR($r, '{{wall_left_door}}'), 4) . ' บาน',
        40
    );
    lifeInLine(
        $section,
        'ฝาผนังด้านขวา ' . lifeInDot(lifeInR($r, '{{wall_right}}'), 16)
        . ' หน้าต่าง ' . lifeInDot(lifeInR($r, '{{wall_right_window}}'), 4) . ' บาน'
        . ' ประตู ' . lifeInDot(lifeInR($r, '{{wall_right_door}}'), 4) . ' บาน',
        40
    );
    lifeInLine(
        $section,
        'ฝาผนังด้านหลัง ' . lifeInDot(lifeInR($r, '{{wall_back}}'), 16)
        . ' หน้าต่าง ' . lifeInDot(lifeInR($r, '{{wall_back_window}}'), 4) . ' บาน'
        . ' ประตู ' . lifeInDot(lifeInR($r, '{{wall_back_door}}'), 4) . ' บาน',
        40
    );
    lifeInLine(
        $section,
        'พื้นห้อง ' . lifeInDot(lifeInR($r, '{{floor_material}}'), 12)
        . ' เพดาน/ฝ้า ' . lifeInDot(lifeInR($r, '{{ceiling}}'), 16)
        . ' หลังคา ' . lifeInDot(lifeInR($r, '{{roof}}'), 12),
        40
    );

    lifeInLine($section, 'ลักษณะการจัดวางสิ่งของ', 40, ['bold' => true, 'underline' => 'single'], ['spaceBefore' => 60]);
    lifeInLine($section, 'ติดฝาผนังด้านหน้าเรียงจากซ้ายไปขวา ' . lifeInDot(lifeInR($r, '{{arrange_front}}'), 30), 40);
    lifeInLine($section, 'ติดฝาผนังด้านซ้ายเรียงจากหน้าไปหลัง ' . lifeInDot(lifeInR($r, '{{arrange_left}}'), 30), 40);
    lifeInLine($section, 'ติดฝาผนังด้านขวาเรียงจากหน้าไปหลัง ' . lifeInDot(lifeInR($r, '{{arrange_right}}'), 30), 40);
    lifeInLine($section, 'ติดฝาผนังด้านหลังเรียงจากซ้ายไปขวา ' . lifeInDot(lifeInR($r, '{{arrange_back}}'), 30), 40);
    lifeInLine($section, 'บริเวณอื่นๆ ' . lifeInDot(lifeInR($r, '{{arrange_other}}'), 40), 40);

    lifeInLine($section, '7. ผลการตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    lifeInLine(
        $section,
        'พฤติการณ์ของคดีจากการสอบถามข้อมูลในเบื้องต้นจาก พงส. ได้ความว่า '
        . lifeInDot(lifeInR($r, '{{case_behavior}}'), 40),
        20
    );
    lifeInLine($section, 'จากการตรวจสถานที่เกิดเหตุ', 20, [], ['spaceBefore' => 40]);
    lifeInLine(
        $section,
        '7.1 สภาพของสถานที่เกิดเหตุเมื่อไปถึง ' . lifeInDot(lifeInR($r, '{{scene_condition}}'), 40),
        20
    );

    // ===== หน้า 3 =====
    $section->addPageBreak();
    lifeInLine($section, '7.2 ลักษณะสภาพศพ', 20, ['bold' => true]);
    lifeInLine($section, '7.2.1 พบศพ/ไม่พบศพ ' . lifeInDot(lifeInR($r, '{{body_found_text}}'), 40), 40);
    lifeInLine($section, '7.2.2 ตำแหน่งที่พบศพ ' . lifeInDot(lifeInR($r, '{{body_position}}'), 40), 40);
    lifeInLine($section, '7.2.3 สภาพศพ ' . lifeInDot(lifeInR($r, '{{body_condition}}'), 40), 40);
    lifeInLine($section, '7.2.4 สภาพเครื่องแต่งกายและทรัพย์สิน ' . lifeInDot(lifeInR($r, '{{body_clothing}}'), 30), 40);
    lifeInLine($section, '7.2.5 รอยบาดแผลที่ศพ ' . lifeInDot(lifeInR($r, '{{body_wounds}}'), 40), 40);

    lifeInLine($section, '7.3 ร่องรอยและวัตถุพยานที่ตรวจพบในสถานที่เกิดเหตุ', 20, ['bold' => true], ['spaceBefore' => 40]);
    lifeInLine($section, lifeInDot(lifeInR($r, '{{evidence_found}}'), 60), 40);

    lifeInLine($section, '7.4 วัตถุพยานที่ตรวจเก็บในสถานที่เกิดเหตุ', 20, ['bold' => true], ['spaceBefore' => 40]);
    lifeInLine($section, lifeInDot(lifeInR($r, '{{evidence_collected}}'), 60), 40);

    // ===== หน้า 4 =====
    $section->addPageBreak();
    lifeInLine($section, '7.5 การดำเนินการเกี่ยวกับวัตถุพยาน', 20, ['bold' => true]);
    lifeInLine($section, lifeInDot(lifeInR($r, '{{evidence_action}}'), 60), 40);

    lifeInLine(
        $section,
        '7.6 เจ้าหน้าที่ กสก.พฐก. / กสก.ศพฐ / พฐ.จว. '
        . lifeInDot(lifeInR($r, '{{handover_officer}}'), 24)
        . ' ตรวจสถานที่เกิดเหตุเสร็จสิ้น',
        20,
        ['bold' => true],
        ['spaceBefore' => 60]
    );
    lifeInLine(
        $section,
        'พร้อมทั้งส่งมอบสถานที่เกิดเหตุคืนให้กับ ' . lifeInDot(lifeInR($r, '{{handover_to}}'), 30),
        20
    );
    lifeInLine(
        $section,
        'เมื่อวันที่ ' . lifeInDot(lifeInR($r, '{{handover_date}}'), 18)
        . ' เวลาประมาณ ' . lifeInDot(lifeInR($r, '{{handover_time}}'), 8) . ' น.',
        20
    );

    $section->addTextBreak(1);
    $sig = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $sig->addRow();
    $sig->addCell(4500);
    $sc = $sig->addCell(5000);
    foreach ([
        '(ลงชื่อ) ............................................................',
        '( ' . lifeInDot(lifeInR($r, '{{signer_name}}'), 28) . ' )',
        '(ตำแหน่ง) ' . lifeInDot(lifeInR($r, '{{signer_position}}'), 28),
        '........../.............../.............',
    ] as $sigLine) {
        $sc->addText($sigLine, lifeInFs(), lifeInPs(0, ['alignment' => Jc::CENTER, 'lineHeight' => 1.8]));
    }

    $tempPhotoPaths = [];
    $photoIndex = 0;
    foreach (array_values($photoDataUris) as $photo) {
        $probe = lifeInPreparePhoto($photo);
        if ($probe === null) {
            continue;
        }
        $section->addPageBreak();
        try {
            $section->addImage($probe['path'], ['width' => 450, 'alignment' => Jc::CENTER]);
            $photoIndex++;
            $section->addText(
                'ภาพที่ ' . $photoIndex,
                lifeInFs(),
                lifeInPs(0, ['alignment' => Jc::CENTER, 'spaceBefore' => 80])
            );
            $tempPhotoPaths[] = $probe['path'];
        } catch (Throwable $e) {
            @unlink($probe['path']);
        }
    }

    return [$phpWord, $tempPhotoPaths];
}

function downloadLifeIndoorReportDocx(int $incidentId, string $baseName, array $photoDataUris = []): void
{
    $export = lifeInLoadExport($incidentId);
    [$phpWord, $tempPhotos] = buildLifeIndoorReportPhpWord($export['replacements'], $photoDataUris);

    $filenameUtf8 = $baseName . '.docx';
    $filenameAscii = preg_replace('/[^\x20-\x7E]/', '', $baseName);
    $filenameAscii = (trim($filenameAscii) !== '' ? $filenameAscii : ('life_indoor_' . $incidentId)) . '.docx';

    $tmpFile = tempnam(sys_get_temp_dir(), 'life_in_docx_');
    if ($tmpFile === false) {
        throw new RuntimeException('ไม่สามารถสร้างไฟล์ชั่วคราวได้');
    }
    $docxPath = $tmpFile . '.docx';
    @unlink($tmpFile);

    $writer = IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save($docxPath);

    foreach ($tempPhotos as $p) {
        if (is_string($p) && $p !== '' && is_file($p)) {
            @unlink($p);
        }
    }

    header('Cache-Control: max-age=0');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header(
        'Content-Disposition: attachment; filename="' . $filenameAscii . '"; filename*=UTF-8\'\'' . rawurlencode($filenameUtf8)
    );
    header('Content-Length: ' . filesize($docxPath));
    readfile($docxPath);
    @unlink($docxPath);
}
