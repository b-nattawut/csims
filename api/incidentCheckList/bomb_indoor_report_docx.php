<?php
/**
 * bomb_indoor_report_docx.php — DOCX รายงานคดีระเบิด ในอาคาร (F-CS-15)
 * โครงเดียวกับคดีชีวิตในอาคาร: PhpWord API + ระยะแน่น
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/word_docx_dotted_rule.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

const BOMB_IN_DOCX_FONT = 'TH Sarabun New';
const BOMB_IN_DOCX_SIZE = 14;

function bombInLoadExport(int $incidentId): array
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
    include __DIR__ . '/gen_pdf_bomb_report_indoor_html.php';
    ob_end_clean();
    ini_set('display_errors', $prev);

    $export = $GLOBALS['bomb_indoor_report_export'] ?? null;
    if (!is_array($export) || empty($export['replacements'])) {
        throw new RuntimeException('ไม่สามารถโหลดข้อมูลรายงานคดีระเบิด (ในอาคาร) ได้');
    }
    return $export;
}

function bombInFs(array $extra = []): array
{
    return array_merge([
        'name' => BOMB_IN_DOCX_FONT,
        'size' => BOMB_IN_DOCX_SIZE,
        'color' => '000000',
    ], $extra);
}

function bombInPs(int $indentPt = 0, array $extra = []): array
{
    $style = ['spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 1.15];
    if ($indentPt > 0) {
        $style['indentation'] = ['left' => $indentPt * 20];
    }
    return array_merge($style, $extra);
}

function bombInText(string $htmlOrText): string
{
    $t = html_entity_decode(strip_tags($htmlOrText), ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', trim($t));
    return $t ?? '';
}

function bombInR(array $r, string $key): string
{
    return bombInText($r[$key] ?? '');
}

function bombInCb(array $r, string $key): string
{
    $m = bombInText((string) ($r[$key] ?? ''));
    return ($m !== '' && $m !== '☐') ? '☑' : '☐';
}

function bombInDot(string $value, int $dots = 24): string
{
    $v = trim($value);
    return $v !== '' ? $v : str_repeat('.', $dots);
}

function bombInLine($section, string $text, int $indentPt = 0, array $fontExtra = [], array $paraExtra = []): void
{
    $t = trim($text);
    $isBlankish = ($t === '' || preg_match('/^\.+$/u', $t) || preg_match('/^\d+(?:\.\d+)*\.\s*$/u', $t));
    if ($isBlankish && wordDocxTryDottedBlankLine(
        $section,
        $text,
        ['name' => BOMB_IN_DOCX_FONT, 'size' => BOMB_IN_DOCX_SIZE, 'color' => '000000'],
        function (int $i, array $e = []) { return bombInPs($i, $e); },
        $indentPt,
        $fontExtra
    )) {
        return;
    }
    $section->addText($t === '' ? ' ' : $t, bombInFs($fontExtra), bombInPs($indentPt, $paraExtra));
}

function bombInAddDynamicLines($section, string $html): void
{
    if (trim($html) === '') {
        bombInLine($section, '', 20);
        return;
    }
    if (!preg_match_all('/<div([^>]*)>((?:(?!<div\b).)*)<\/div>/is', $html, $matches, PREG_SET_ORDER)) {
        $plain = bombInText($html);
        bombInLine($section, $plain !== '' ? $plain : '', 20);
        return;
    }
    $prevWasEmptyDots = false;
    foreach ($matches as $match) {
        $indent = 20;
        if (preg_match('/\bi3\b/', $match[1])) {
            $indent = 60;
        } elseif (preg_match('/\bi2\b/', $match[1])) {
            $indent = 40;
        }
        $text = bombInText($match[2]);
        if ($text === '') {
            if ($prevWasEmptyDots) {
                continue;
            }
            $prevWasEmptyDots = true;
        } else {
            $prevWasEmptyDots = false;
        }
        bombInLine($section, $text, $indent);
    }
}

function bombInPreparePhoto($photo): ?array
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
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bomb_in_photo_' . uniqid('', true) . $ext;
    if (file_put_contents($path, $bytes) === false) {
        return null;
    }
    return ['path' => $path];
}

/**
 * @return array{0: PhpWord, 1: string[]}
 */
function buildBombIndoorReportPhpWord(array $replacements, array $photoDataUris = []): array
{
    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName(BOMB_IN_DOCX_FONT);
    $phpWord->setDefaultFontSize(BOMB_IN_DOCX_SIZE);
    $phpWord->setDefaultParagraphStyle(bombInPs(0));

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
    $reportNo = bombInR($r, '{{report_no}}');
    $yearShort = bombInR($r, '{{report_year_short}}');
    $agency = bombInR($r, '{{agency_name}}');

    // Header
    $header = $section->addHeader();
    $ht = $header->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $ht->addRow();
    $c1 = $ht->addCell(7500);
    $run = $c1->addTextRun(bombInPs(0));
    $run->addText('รายงานการตรวจสถานที่เกิดเหตุที่', bombInFs(['underline' => 'single']));
    $run->addText('  ' . bombInDot($reportNo, 10) . ' /25 ' . bombInDot($yearShort, 4), bombInFs());
    $c2 = $ht->addCell(2000);
    $c2->addPreserveText('{PAGE}/{NUMPAGES}', bombInFs(), ['alignment' => Jc::END]);
    $header->addText('หน่วยงาน  ' . bombInDot($agency, 40), bombInFs(), bombInPs(0, ['spaceAfter' => 60]));

    // Footer F-CS-15
    $footer = $section->addFooter();
    $ft = $footer->addTable([
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT,
        'borderBottomSize' => 6,
        'borderBottomColor' => 'BFBFBF',
    ]);
    $ft->addRow();
    $fl = $ft->addCell(5000);
    $fl->addText('ปฏิบัติงานตาม', bombInFs(['size' => 10, 'color' => '808080']), bombInPs(0));
    $fl->addText('OPFS – CS – SP – 03', bombInFs(['size' => 10, 'color' => '808080']), bombInPs(0));
    $fr = $ft->addCell(5000);
    foreach (['F-CS-15 แก้ไขครั้งที่ 2', 'แก้ไขวันที่ 2 ก.ค. 63', 'เริ่มใช้ 1 ก.ค. 63'] as $fline) {
        $fr->addText($fline, bombInFs(['size' => 10, 'color' => '808080']), bombInPs(0, ['alignment' => Jc::END]));
    }

    // ===== หน้า 1 =====
    bombInLine($section, 'รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด (ในอาคาร)', 0, [
        'bold' => true, 'size' => 15, 'underline' => 'single',
    ], ['alignment' => Jc::CENTER, 'spaceBefore' => 60, 'spaceAfter' => 60]);

    bombInLine($section, '1. การรับแจ้งเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    bombInLine(
        $section,
        'เมื่อวันที่ ' . bombInDot(bombInR($r, '{{receive_date}}'), 18)
        . ' เวลา ' . bombInDot(bombInR($r, '{{receive_time}}'), 8)
        . ' น. ตามประจำวันข้อที่ ' . bombInDot(bombInR($r, '{{daily_ref}}'), 10)
        . ' กสก.พฐก./ กสก.ศพฐ./',
        20
    );
    bombInLine(
        $section,
        'พฐ.จว. ' . bombInDot(bombInR($r, '{{agency_name}}'), 40),
        20
    );
    bombInLine(
        $section,
        // ★ ข้อ 7: ออกเป็นข้อความไทย แทนช่องติ๊ก
        'ได้รับแจ้งตาม  ' . bombInDot(bombInR($r, '{{notify_method_text}}'), 30),
        20
    );
    bombInLine(
        $section,
        'จาก สน./สภ. ' . bombInDot(bombInR($r, '{{from_station}}'), 20)
        . ' ขอเจ้าหน้าที่ร่วมตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด',
        20
    );
    bombInLine(
        $section,
        'โดยมี ' . bombInDot(bombInR($r, '{{investigator_name}}'), 30)
        . ' เป็นพนักงานสอบสวนเจ้าของคดี',
        20
    );

    bombInLine($section, '2. สถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    bombInLine($section, bombInDot(bombInR($r, '{{crime_location}}'), 50), 20);
    bombInLine(
        $section,
        'ผู้เสียหาย/ผู้ร้องทุกข์/ผู้ครอบครอง ' . bombInDot(bombInR($r, '{{victim_name}}'), 24)
        . ' อายุประมาณ ' . bombInDot(bombInR($r, '{{victim_age}}'), 4) . ' ปี',
        20
    );

    bombInLine($section, '3. วันเวลาที่ทราบเหตุ/เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    bombInLine(
        $section,
        'ผู้เสียหายทราบเหตุ/เกิดเหตุ เมื่อวันที่ ' . bombInDot(bombInR($r, '{{victim_know_date}}'), 18)
        . ' เวลาประมาณ ' . bombInDot(bombInR($r, '{{victim_know_time}}'), 8) . ' น.',
        20
    );
    bombInLine(
        $section,
        'พนักงานสอบสวนทราบเหตุ เมื่อวันที่ ' . bombInDot(bombInR($r, '{{officer_know_date}}'), 18)
        . ' เวลาประมาณ ' . bombInDot(bombInR($r, '{{officer_know_time}}'), 8) . ' น.',
        20
    );

    bombInLine($section, '4. วันเวลาตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    bombInLine(
        $section,
        'ตรวจสถานที่เกิดเหตุ เมื่อวันที่ ' . bombInDot(bombInR($r, '{{inspect_date}}'), 18)
        . ' เวลาประมาณ ' . bombInDot(bombInR($r, '{{inspect_time}}'), 8) . ' น.',
        20
    );
    bombInLine(
        $section,
        'ตรวจสถานที่เกิดเหตุเพิ่มเติม เมื่อวันที่ ' . bombInDot(bombInR($r, '{{inspect_add_date}}'), 18)
        . ' เวลาประมาณ ' . bombInDot(bombInR($r, '{{inspect_add_time}}'), 8) . ' น.',
        20
    );

    bombInLine($section, '5. ผู้ตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    bombInAddDynamicLines($section, $r['{{inspector_rows}}'] ?? '');

    bombInLine($section, '6. ลักษณะของสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    bombInLine($section, '6.1 ลักษณะภายนอก', 20, ['bold' => true]);
    bombInLine(
        $section,
        'เป็น บ้าน/ตึกแถว/อาคาร/อื่นๆ ' . bombInDot(bombInR($r, '{{building_type_text}}'), 16),
        40
    );
    bombInLine(
        $section,
        'จำนวน ชั้น ' . bombInDot(bombInR($r, '{{floor_count}}'), 4)
        . ' หลัง/คูหา ' . bombInDot(bombInR($r, '{{unit_count}}'), 4)
        . '  '
        . bombInCb($r, '{{chk_mezzanine_yes}}') . 'มี / '
        . bombInCb($r, '{{chk_mezzanine_no}}') . 'ไม่มีชั้นลอย',
        40
    );
    bombInLine(
        $section,
        bombInCb($r, '{{chk_rooftop_yes}}') . 'มี / '
        . bombInCb($r, '{{chk_rooftop_no}}') . 'ไม่มีดาดฟ้า  ปลูกอยู่ภายในบริเวณ '
        . bombInCb($r, '{{chk_fence_yes}}') . 'มี / '
        . bombInCb($r, '{{chk_fence_no}}') . 'ไม่มี รั้วล้อมรอบ เมื่อหันหน้าเข้า',
        40
    );
    bombInLine($section, 'ด้านหน้าติด ' . bombInDot(bombInR($r, '{{ext_front}}'), 40), 40);
    bombInLine($section, 'ด้านซ้ายติด ' . bombInDot(bombInR($r, '{{ext_left}}'), 40), 40);
    bombInLine($section, 'ด้านขวาติด ' . bombInDot(bombInR($r, '{{ext_right}}'), 40), 40);
    bombInLine($section, 'ด้านหลังติด ' . bombInDot(bombInR($r, '{{ext_back}}'), 40), 40);

    bombInLine($section, '6.2 ลักษณะภายใน', 20, ['bold' => true], ['spaceBefore' => 40]);
    bombInLine($section, bombInDot(bombInR($r, '{{interior_detail}}'), 60), 40);

    // 6.3 ต่อเนื่อง (ไม่ hard-break — กันหน้าว่างจาก pageBreak ทับเนื้อที่ล้น)
    bombInLine($section, '6.3 บริเวณที่เกิดเหตุ', 20, ['bold' => true], ['spaceBefore' => 40]);
    bombInLine($section, 'จุดเกิดเหตุ ' . bombInDot(bombInR($r, '{{incident_area}}'), 40), 40);
    bombInLine($section, 'มีขนาดห้อง กว้าง x ยาว ประมาณ ' . bombInDot(bombInR($r, '{{area_size}}'), 30), 40);

    bombInLine($section, 'ลักษณะโครงสร้าง', 40, ['bold' => true, 'underline' => 'single'], [
        'alignment' => Jc::CENTER, 'spaceBefore' => 60, 'spaceAfter' => 40,
    ]);
    bombInLine(
        $section,
        'ฝาผนังด้านหน้า ' . bombInDot(bombInR($r, '{{wall_front}}'), 16)
        . ' หน้าต่าง ' . bombInDot(bombInR($r, '{{wall_front_window}}'), 4) . ' บาน'
        . ' ประตู ' . bombInDot(bombInR($r, '{{wall_front_door}}'), 4) . ' บาน',
        40
    );
    bombInLine(
        $section,
        'ฝาผนังด้านซ้าย ' . bombInDot(bombInR($r, '{{wall_left}}'), 16)
        . ' หน้าต่าง ' . bombInDot(bombInR($r, '{{wall_left_window}}'), 4) . ' บาน'
        . ' ประตู ' . bombInDot(bombInR($r, '{{wall_left_door}}'), 4) . ' บาน',
        40
    );
    bombInLine(
        $section,
        'ฝาผนังด้านขวา ' . bombInDot(bombInR($r, '{{wall_right}}'), 16)
        . ' หน้าต่าง ' . bombInDot(bombInR($r, '{{wall_right_window}}'), 4) . ' บาน'
        . ' ประตู ' . bombInDot(bombInR($r, '{{wall_right_door}}'), 4) . ' บาน',
        40
    );
    bombInLine(
        $section,
        'ฝาผนังด้านหลัง ' . bombInDot(bombInR($r, '{{wall_back}}'), 16)
        . ' หน้าต่าง ' . bombInDot(bombInR($r, '{{wall_back_window}}'), 4) . ' บาน'
        . ' ประตู ' . bombInDot(bombInR($r, '{{wall_back_door}}'), 4) . ' บาน',
        40
    );
    bombInLine($section, 'พื้นห้อง ' . bombInDot(bombInR($r, '{{floor_material}}'), 40), 40);
    bombInLine($section, 'เพดานห้อง ' . bombInDot(bombInR($r, '{{ceiling}}'), 40), 40);
    bombInLine($section, 'หลังคา ' . bombInDot(bombInR($r, '{{roof}}'), 40), 40);

    bombInLine($section, 'ลักษณะการจัดวางสิ่งของ', 40, ['bold' => true, 'underline' => 'single'], ['spaceBefore' => 60]);
    bombInLine($section, 'ด้านหน้า (ซ้ายไปขวา) ' . bombInDot(bombInR($r, '{{arrange_front}}'), 30), 40);
    bombInLine($section, 'ด้านซ้าย (หน้าไปหลัง) ' . bombInDot(bombInR($r, '{{arrange_left}}'), 30), 40);
    bombInLine($section, 'ด้านขวา (หน้าไปหลัง) ' . bombInDot(bombInR($r, '{{arrange_right}}'), 30), 40);
    bombInLine($section, 'ด้านหลัง (ซ้ายไปขวา) ' . bombInDot(bombInR($r, '{{arrange_back}}'), 30), 40);
    bombInLine($section, 'รายละเอียดอื่นๆ ' . bombInDot(bombInR($r, '{{other_detail}}'), 40), 20);

    bombInLine($section, '7. ผลการตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    bombInLine(
        $section,
        'พฤติการณ์ของคดีจากการสอบถามข้อมูลในเบื้องต้นจาก พงส. ได้ความว่า '
        . bombInDot(bombInR($r, '{{case_behavior}}'), 40),
        20
    );
    bombInLine($section, 'ผลการตรวจสถานที่เกิดเหตุ', 20, [], ['spaceBefore' => 40]);
    bombInLine($section, '7.1 สภาพทั่วไปของสถานที่เกิดเหตุ', 20, ['bold' => true]);
    bombInLine($section, bombInDot(bombInR($r, '{{scene_condition}}'), 60), 40);

    bombInLine($section, '7.2 ผู้เสียชีวิต', 20, ['bold' => true], ['spaceBefore' => 40]);
    bombInLine($section, '7.2.1 พบศพ/ไม่พบศพ ' . bombInDot(bombInR($r, '{{body_found}}'), 40), 40);
    bombInLine($section, '7.2.2 ตำแหน่งที่พบศพ ' . bombInDot(bombInR($r, '{{body_position}}'), 40), 40);
    bombInLine($section, '7.2.3 สภาพศพ ' . bombInDot(bombInR($r, '{{body_condition}}'), 40), 40);
    bombInLine($section, '7.2.4 เครื่องแต่งกายเสื้อผ้าที่สวมใส่ ' . bombInDot(bombInR($r, '{{body_clothing}}'), 30), 40);
    bombInLine($section, '7.2.5 บาดแผลที่ปรากฏ ' . bombInDot(bombInR($r, '{{body_wounds}}'), 40), 40);

    // 7.3+ ต่อเนื่อง (ไม่ hard-break — กันหน้าว่าง)
    bombInLine($section, '7.3 สภาพความเสียหาย', 20, ['bold' => true], ['spaceBefore' => 40]);
    bombInLine($section, bombInDot(bombInR($r, '{{damage_detail}}'), 60), 40);

    bombInLine($section, '7.4 ร่องรอยและวัตถุพยานที่ตรวจพบในสถานที่เกิดเหตุ', 20, ['bold' => true], ['spaceBefore' => 40]);
    bombInLine($section, bombInDot(bombInR($r, '{{evidence_found}}'), 60), 40);

    bombInLine($section, '7.5 ตรวจเก็บวัตถุพยาน รอยลายนิ้วมือแฝง', 20, ['bold' => true], ['spaceBefore' => 40]);
    bombInLine($section, bombInDot(bombInR($r, '{{evidence_collected}}'), 60), 40);

    bombInLine($section, '7.6 การดำเนินการเกี่ยวกับวัตถุพยาน', 20, ['bold' => true], ['spaceBefore' => 40]);
    bombInLine($section, bombInDot(bombInR($r, '{{evidence_action}}'), 60), 40);

    bombInLine(
        $section,
        '7.7 เจ้าหน้าที่ กสก.พฐก. / กสก.ศพฐ..... / พฐ.จว. '
        . bombInDot(bombInR($r, '{{handover_agency}}'), 24)
        . ' ตรวจสถานที่เกิดเหตุเสร็จสิ้น',
        20,
        ['bold' => true],
        ['spaceBefore' => 60]
    );
    bombInLine(
        $section,
        'พร้อมทั้งส่งมอบสถานที่เกิดเหตุคืนให้กับ ' . bombInDot(bombInR($r, '{{handover_to}}'), 30),
        40
    );
    bombInLine(
        $section,
        'เมื่อวันที่ ' . bombInDot(bombInR($r, '{{handover_date}}'), 18)
        . ' เวลาประมาณ ' . bombInDot(bombInR($r, '{{handover_time}}'), 8) . ' น.',
        40
    );

    $section->addTextBreak(1);
    $sig = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $sig->addRow();
    $sig->addCell(4500);
    $sc = $sig->addCell(5000);
    foreach ([
        '(ลงชื่อ) ............................................................',
        '( ' . bombInDot(bombInR($r, '{{signer_name}}'), 28) . ' )',
        '(ตำแหน่ง) ' . bombInDot(bombInR($r, '{{signer_position}}'), 28),
        '........../.............../.............',
    ] as $sigLine) {
        $sc->addText($sigLine, bombInFs(), bombInPs(0, ['alignment' => Jc::CENTER, 'lineHeight' => 1.8]));
    }

    $tempPhotoPaths = [];
    $photoIndex = 0;
    foreach (array_values($photoDataUris) as $photo) {
        $probe = bombInPreparePhoto($photo);
        if ($probe === null) {
            continue;
        }
        $section->addPageBreak();
        try {
            $section->addImage($probe['path'], ['width' => 450, 'alignment' => Jc::CENTER]);
            $photoIndex++;
            $section->addText(
                'ภาพที่ ' . $photoIndex,
                bombInFs(),
                bombInPs(0, ['alignment' => Jc::CENTER, 'spaceBefore' => 80])
            );
            $tempPhotoPaths[] = $probe['path'];
        } catch (Throwable $e) {
            @unlink($probe['path']);
        }
    }

    return [$phpWord, $tempPhotoPaths];
}

function downloadBombIndoorReportDocx(int $incidentId, string $baseName, array $photoDataUris = []): void
{
    $export = bombInLoadExport($incidentId);
    [$phpWord, $tempPhotos] = buildBombIndoorReportPhpWord($export['replacements'], $photoDataUris);

    $filenameUtf8 = $baseName . '.docx';
    $filenameAscii = preg_replace('/[^\x20-\x7E]/', '', $baseName);
    $filenameAscii = (trim($filenameAscii) !== '' ? $filenameAscii : ('bomb_indoor_' . $incidentId)) . '.docx';

    $tmpFile = tempnam(sys_get_temp_dir(), 'bomb_in_docx_');
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
