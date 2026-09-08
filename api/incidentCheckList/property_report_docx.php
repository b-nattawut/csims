<?php
/**
 * property_report_docx.php — สร้าง DOCX รายงานคดีทรัพย์ (F-CS-12)
 * จากข้อมูลชุดเดียวกับ PDF + โครงตาม form_property_report_word.html
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/word_docx_dotted_rule.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

const PROPERTY_DOCX_FONT = 'TH Sarabun New';
const PROPERTY_DOCX_SIZE = 14;

/**
 * โหลด replacements + HTML จาก generator PDF/ข้อมูลคดีทรัพย์
 *
 * @return array{replacements: array, html: string, prop: array}
 */
function propertyLoadReportExport(int $incidentId): array
{
    // include generator จากในฟังก์ชัน → ต้องดึง $pdo เข้า local scope
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
    include __DIR__ . '/gen_pdf_property_report_html.php';
    ob_end_clean();
    ini_set('display_errors', $prev);

    $export = $GLOBALS['property_report_export'] ?? null;
    if (!is_array($export) || empty($export['replacements'])) {
        throw new RuntimeException('ไม่สามารถโหลดข้อมูลรายงานคดีทรัพย์ได้');
    }

    return $export;
}

function propertyDocxText(string $htmlOrText): string
{
    $t = html_entity_decode(strip_tags($htmlOrText), ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', trim($t));
    return $t ?? '';
}

function propertyDocxCb($mark): string
{
    $m = propertyDocxText((string) $mark);
    return ($m !== '' && $m !== '☐') ? '☑' : '☐';
}

function propertyDocxDot(string $value, int $minDots = 24): string
{
    $v = trim($value);
    if ($v === '') {
        return str_repeat('.', max($minDots, 18));
    }
    return $v;
}

function propertyDocxFs(array $extra = []): array
{
    return array_merge([
        'name' => PROPERTY_DOCX_FONT,
        'size' => PROPERTY_DOCX_SIZE,
        'color' => '000000',
    ], $extra);
}

/** ย่อหน้าแน่นแบบ PDF — ไม่เว้นระยะหลังบรรทัด */
function propertyPs(int $indentPt = 0, array $extra = []): array
{
    $style = [
        'spaceBefore' => 0,
        'spaceAfter'  => 0,
        'lineHeight'  => 1.15,
    ];
    if ($indentPt > 0) {
        $style['indentation'] = ['left' => $indentPt * 20];
    }
    return array_merge($style, $extra);
}

function propertyR(array $r, string $key): string
{
    return propertyDocxText($r[$key] ?? '');
}

function propertyCbMark(array $r, string $key): string
{
    return propertyDocxCb($r[$key] ?? '');
}

function propertyLine($section, string $text, int $indentPt = 0, array $fontExtra = [], array $paraExtra = []): void
{
    $t = trim($text);
    $isBlankish = ($t === '' || preg_match('/^\.+$/u', $t) || preg_match('/^\d+(?:\.\d+)*\.\s*$/u', $t));
    if ($isBlankish && wordDocxTryDottedBlankLine(
        $section,
        $text,
        ['name' => PROPERTY_DOCX_FONT, 'size' => PROPERTY_DOCX_SIZE, 'color' => '000000'],
        function (int $i, array $e = []) { return propertyPs($i, $e); },
        $indentPt,
        $fontExtra
    )) {
        return;
    }
    $section->addText($t === '' ? ' ' : $t, propertyDocxFs($fontExtra), propertyPs($indentPt, $paraExtra));
}

function propertyBlankDot(string $value, int $dots = 24): string
{
    $v = trim($value);
    return $v !== '' ? $v : str_repeat('.', $dots);
}

/** ใส่บรรทัดจาก HTML แถว dynamic ที่แปลงแล้ว */
function propertyAddDynamicLines($section, string $html): void
{
    if (trim($html) === '') {
        propertyLine($section, '', 20);
        return;
    }
    if (!preg_match_all('/<p([^>]*)>(.*?)<\/p>/is', $html, $matches, PREG_SET_ORDER)) {
        $plain = propertyDocxText($html);
        propertyLine($section, $plain !== '' ? $plain : '', 20);
        return;
    }
    foreach ($matches as $match) {
        $indent = 20;
        if (preg_match('/margin-left:\s*(\d+)\s*pt/i', $match[1], $im)) {
            $indent = (int) $im[1];
        }
        $bold = (bool) preg_match('/font-weight\s*:\s*bold/i', $match[1]);
        $text = propertyDocxText($match[2]);
        propertyLine($section, $text, $indent, $bold ? ['bold' => true] : []);
    }
}

/**
 * สร้างเนื้อหารายงานด้วย PhpWord API โดยตรง (ระยะแน่นเหมือน PDF)
 *
 * @return array{0: PhpWord, 1: string[]}
 */
function buildPropertyReportPhpWord(string $filledHtml, array $replacements, array $photoDataUris = []): array
{
    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName(PROPERTY_DOCX_FONT);
    $phpWord->setDefaultFontSize(PROPERTY_DOCX_SIZE);
    $phpWord->setDefaultParagraphStyle(propertyPs(0));

    $settings = $phpWord->getSettings();
    $settings->setHideSpellingErrors(true);
    $settings->setHideGrammaticalErrors(true);
    if (class_exists('\\PhpOffice\\PhpWord\\Style\\Language') && method_exists($settings, 'setThemeFontLang')) {
        try {
            $settings->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('th-TH', 'th-TH'));
        } catch (Throwable $e) {
            // ignore
        }
    }

    $section = $phpWord->addSection([
        'marginTop'    => 680,
        'marginBottom' => 680,
        'marginLeft'   => 850,
        'marginRight'  => 850,
    ]);

    $r = $replacements;
    $reportNo = propertyR($r, '{{report_no}}');
    $yearShort = propertyR($r, '{{report_year_short}}');
    $agency = propertyR($r, '{{agency_name}}');

    // Header
    $header = $section->addHeader();
    $ht = $header->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $ht->addRow();
    $c1 = $ht->addCell(7500);
    $run = $c1->addTextRun(propertyPs(0));
    $run->addText('รายงานการตรวจสถานที่เกิดเหตุที่', propertyDocxFs(['underline' => 'single']));
    $run->addText(
        '  ' . propertyBlankDot($reportNo, 10) . ' /25 ' . propertyBlankDot($yearShort, 4),
        propertyDocxFs()
    );
    $c2 = $ht->addCell(2000);
    $c2->addPreserveText('{PAGE}/{NUMPAGES}', propertyDocxFs(), ['alignment' => Jc::END]);
    $header->addText('หน่วยงาน  ' . propertyBlankDot($agency, 40), propertyDocxFs(), propertyPs(0, ['spaceAfter' => 60]));

    // Footer
    $footer = $section->addFooter();
    $ft = $footer->addTable([
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT,
        'borderBottomSize' => 6,
        'borderBottomColor' => 'BFBFBF',
    ]);
    $ft->addRow();
    $fl = $ft->addCell(5000);
    $fl->addText('ปฏิบัติงานตาม', propertyDocxFs(['size' => 10, 'color' => '808080']), propertyPs(0));
    $fl->addText('OPFS – CS – SP – 01', propertyDocxFs(['size' => 10, 'color' => '808080']), propertyPs(0));
    $fr = $ft->addCell(5000);
    foreach (['F-CS-12 แก้ไขครั้งที่ 2', 'แก้ไขวันที่ 2 ก.ค. 63', 'เริ่มใช้ 1 ก.ค. 63'] as $fline) {
        $fr->addText($fline, propertyDocxFs(['size' => 10, 'color' => '808080']), propertyPs(0, ['alignment' => Jc::END]));
    }

    // ===== หน้า 1 =====
    propertyLine($section, 'รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์', 0, [
        'bold' => true,
        'size' => 15,
        'underline' => 'single',
    ], ['alignment' => Jc::CENTER, 'spaceBefore' => 60, 'spaceAfter' => 60]);

    propertyLine($section, '1. การรับแจ้งเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    propertyLine(
        $section,
        'เมื่อวันที่ ' . propertyBlankDot(propertyR($r, '{{receive_date}}'), 18)
        . ' เวลา ' . propertyBlankDot(propertyR($r, '{{receive_time}}'), 8)
        . ' น. ตามประจำวันข้อที่ ' . propertyBlankDot(propertyR($r, '{{daily_ref}}'), 10),
        20
    );
    propertyLine(
        $section,
        // ★ ข้อ 7: ออกเป็นข้อความไทย แทนช่องติ๊ก
        'ได้รับแจ้งตาม  ' . propertyBlankDot(propertyR($r, '{{notify_method_text}}'), 30),
        20
    );
    propertyLine(
        $section,
        'จาก สน./สภ. ' . propertyBlankDot(propertyR($r, '{{from_station}}'), 20)
        . ' ขอเจ้าหน้าที่ร่วมตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์',
        20
    );
    propertyLine(
        $section,
        'โดยมี ' . propertyBlankDot(propertyR($r, '{{investigator_name}}'), 30)
        . ' เป็นพนักงานสอบสวนเจ้าของคดี',
        20
    );

    propertyLine($section, '2. สถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    propertyLine($section, propertyBlankDot(propertyR($r, '{{crime_location}}'), 50), 20);
    propertyLine(
        $section,
        'ผู้เสียหาย/ผู้ร้องทุกข์/เจ้าของ ' . propertyBlankDot(propertyR($r, '{{victim_name}}'), 24)
        . ' อายุประมาณ ' . propertyBlankDot(propertyR($r, '{{victim_age}}'), 4) . ' ปี',
        20
    );

    propertyLine($section, '3. วันเวลาที่ทราบเหตุ/เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    propertyLine(
        $section,
        'ผู้เสียหายทราบเหตุ/เกิดเหตุ เมื่อวันที่ ' . propertyBlankDot(propertyR($r, '{{victim_know_date}}'), 18)
        . ' เวลาประมาณ ' . propertyBlankDot(propertyR($r, '{{victim_know_time}}'), 8) . ' น.',
        20
    );
    propertyLine(
        $section,
        'พนักงานสอบสวนทราบเหตุ เมื่อวันที่ ' . propertyBlankDot(propertyR($r, '{{officer_know_date}}'), 18)
        . ' เวลาประมาณ ' . propertyBlankDot(propertyR($r, '{{officer_know_time}}'), 8) . ' น.',
        20
    );

    propertyLine($section, '4. วันเวลาตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    propertyLine(
        $section,
        'ตรวจสถานที่เกิดเหตุ เมื่อวันที่ ' . propertyBlankDot(propertyR($r, '{{inspect_date}}'), 18)
        . ' เวลาประมาณ ' . propertyBlankDot(propertyR($r, '{{inspect_time}}'), 8) . ' น.',
        20
    );
    propertyLine(
        $section,
        'ตรวจสถานที่เกิดเหตุเพิ่มเติม เมื่อวันที่ ' . propertyBlankDot(propertyR($r, '{{inspect_add_date}}'), 18)
        . ' เวลาประมาณ ' . propertyBlankDot(propertyR($r, '{{inspect_add_time}}'), 8) . ' น.',
        20
    );

    propertyLine($section, '5. ผู้ตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    propertyAddDynamicLines($section, $r['{{inspector_rows}}'] ?? '');

    propertyLine($section, '6. ลักษณะของสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    propertyLine($section, '6.1 ลักษณะภายนอก', 20, ['bold' => true]);
    propertyLine(
        $section,
        'เป็น บ้าน/ตึกแถว/อาคาร/อื่นๆ ' . propertyBlankDot(propertyR($r, '{{building_type_text}}'), 20)
        . ' จำนวน ชั้น ' . propertyBlankDot(propertyR($r, '{{floor_count}}'), 4)
        . ' หลัง/คูหา ' . propertyBlankDot(propertyR($r, '{{unit_count}}'), 4)
        . '  ' . propertyCbMark($r, '{{chk_mezzanine_yes}}') . 'มี / '
        . propertyCbMark($r, '{{chk_mezzanine_no}}') . ' ไม่มีชั้นลอย',
        40
    );
    propertyLine(
        $section,
        propertyCbMark($r, '{{chk_rooftop_yes}}') . 'มี / '
        . propertyCbMark($r, '{{chk_rooftop_no}}') . 'ไม่มีดาดฟ้า ปลูกอยู่ภายในบริเวณ '
        . propertyCbMark($r, '{{chk_fence_yes}}') . 'มี / '
        . propertyCbMark($r, '{{chk_fence_no}}') . ' ไม่มี รั้วล้อมรอบ เมื่อหันหน้าเข้า',
        40
    );
    propertyLine($section, 'ด้านหน้าติด ' . propertyBlankDot(propertyR($r, '{{ext_front}}'), 40), 40);
    propertyLine($section, 'ด้านซ้ายติด ' . propertyBlankDot(propertyR($r, '{{ext_left}}'), 40), 40);
    propertyLine($section, 'ด้านขวาติด ' . propertyBlankDot(propertyR($r, '{{ext_right}}'), 40), 40);
    propertyLine($section, 'ด้านหลังติด ' . propertyBlankDot(propertyR($r, '{{ext_back}}'), 40), 40);

    propertyLine($section, '6.2 ลักษณะภายใน', 20, ['bold' => true], ['spaceBefore' => 40]);
    propertyLine($section, propertyBlankDot(propertyR($r, '{{interior_detail}}'), 60), 20);

    // ===== หน้า 2 =====
    $section->addPageBreak();
    propertyLine($section, '6.3 บริเวณที่เกิดเหตุ สถานที่เกิดเหตุ', 20, ['bold' => true]);
    propertyLine($section, propertyBlankDot(propertyR($r, '{{incident_area}}'), 60), 40);

    propertyLine($section, '7. ผลการตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    propertyLine($section, 'พฤติการณ์คดี', 20);
    propertyLine($section, propertyBlankDot(propertyR($r, '{{case_behavior}}'), 60), 20);

    propertyLine($section, 'ผลการตรวจสถานที่เกิดเหตุ', 20, [], ['spaceBefore' => 40]);
    propertyLine($section, '7.1 สภาพทั่วไปของสถานที่เกิดเหตุ', 20, ['bold' => true]);
    propertyLine($section, propertyBlankDot(propertyR($r, '{{scene_condition}}'), 60), 40);

    propertyLine($section, '7.2 ทางเข้าของคนร้าย', 20, ['bold' => true], ['spaceBefore' => 40]);
    propertyLine($section, propertyBlankDot(propertyR($r, '{{criminal_entry}}'), 60), 40);

    propertyLine($section, '7.3 ร่องรอยที่ตรวจพบในแต่ละห้อง', 20, ['bold' => true], ['spaceBefore' => 40]);
    propertyAddDynamicLines($section, $r['{{room_rows}}'] ?? '');

    // ===== หน้า 3 =====
    $section->addPageBreak();
    propertyLine($section, '7.4 ทรัพย์สินที่ถูกโจรกรรม นาย/นาง/นางสาว/อื่นๆ', 20, ['bold' => true]);
    propertyAddDynamicLines($section, $r['{{stolen_rows}}'] ?? '');

    propertyLine($section, '7.5 ตรวจเก็บวัตถุพยาน รอยลายนิ้วมือแฝง', 20, ['bold' => true], ['spaceBefore' => 40]);
    propertyAddDynamicLines($section, $r['{{evidence_rows}}'] ?? '');

    propertyLine($section, '7.6 การดำเนินการเกี่ยวกับวัตถุพยาน', 20, ['bold' => true], ['spaceBefore' => 40]);
    propertyLine($section, propertyBlankDot(propertyR($r, '{{evidence_action}}'), 60), 40);

    // ===== หน้า 4 =====
    $section->addPageBreak();
    propertyLine(
        $section,
        '7.7 เจ้าหน้าที่ '
        . propertyBlankDot(trim(propertyR($r, '{{handover_agency}}') . ' ' . propertyR($r, '{{handover_detail}}')), 30)
        . ' ตรวจสถานที่เกิดเหตุเสร็จสิ้น',
        20,
        ['bold' => true]
    );
    propertyLine(
        $section,
        'พร้อมทั้งส่งมอบสถานที่เกิดเหตุคืนให้กับ ' . propertyBlankDot(propertyR($r, '{{handover_to}}'), 30),
        40
    );
    propertyLine(
        $section,
        'เมื่อวันที่ ' . propertyBlankDot(propertyR($r, '{{handover_date}}'), 18)
        . ' เวลาประมาณ ' . propertyBlankDot(propertyR($r, '{{handover_time}}'), 8) . ' น.',
        40
    );

    $section->addTextBreak(1);
    $sig = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $sig->addRow();
    $sig->addCell(4500);
    $sc = $sig->addCell(5000);
    foreach ([
        '(ลงชื่อ) ............................................................',
        '( ' . propertyBlankDot(propertyR($r, '{{signer_name}}'), 28) . ' )',
        '(ตำแหน่ง) ' . propertyBlankDot(propertyR($r, '{{signer_position}}'), 28),
        '........../.............../.............',
    ] as $sigLine) {
        $sc->addText($sigLine, propertyDocxFs(), propertyPs(0, ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'lineHeight' => 1.8]));
    }

    // รูปประกอบ — ขึ้นหน้าใหม่เฉพาะเมื่อฝังรูปสำเร็จเท่านั้น (กันหน้าว่างเพี้ยน)
    $tempPhotoPaths = [];
    $photoIndex = 0;
    foreach (array_values($photoDataUris) as $photo) {
        $probe = propertyPreparePhotoFile($photo);
        if ($probe === null) {
            continue;
        }
        $section->addPageBreak();
        try {
            $section->addImage($probe['path'], [
                'width' => 450,
                'alignment' => Jc::CENTER,
            ]);
            $photoIndex++;
            $section->addText(
                'ภาพที่ ' . $photoIndex,
                propertyDocxFs(),
                propertyPs(0, ['alignment' => Jc::CENTER, 'spaceBefore' => 80])
            );
            $tempPhotoPaths[] = $probe['path'];
        } catch (Throwable $e) {
            @unlink($probe['path']);
        }
    }

    return [$phpWord, $tempPhotoPaths];
}

/**
 * เขียน data-uri เป็นไฟล์ชั่วคราว — หรือ null ถ้าใช้ไม่ได้
 *
 * @return array{path: string}|null
 */
function propertyPreparePhotoFile($photo): ?array
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

    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'prop_photo_' . uniqid('', true) . $ext;
    if (file_put_contents($path, $bytes) === false) {
        return null;
    }
    return ['path' => $path];
}

function propertyWrapHtmlFragment(string $html): string
{
    // PhpWord ต้องการ HTML ที่ค่อนข้างสะอาด
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
    if (!preg_match('/^\s*<html/i', $html)) {
        $html = '<body>' . $html . '</body>';
    }
    return $html;
}

/**
 * แปลงแถว dynamic จาก PDF HTML → <p> ทีละบรรทัด (จับเฉพาะ leaf div)
 */
function propertyConvertDynamicRows(array $replacements): array
{
    $keys = ['{{inspector_rows}}', '{{room_rows}}', '{{stolen_rows}}', '{{evidence_rows}}'];
    foreach ($keys as $key) {
        if (empty($replacements[$key])) {
            continue;
        }
        $html = $replacements[$key];
        $lines = [];
        if (preg_match_all('/<div([^>]*)>((?:(?!<div\b).)*)<\/div>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attrs = $match[1];
                $text = propertyDocxText($match[2]);
                if ($text === '' || preg_match('/^\d+(?:\.\d+)*\.\s*$/u', $text)) {
                    $text = ($text === '' ? '' : rtrim($text) . ' ') . str_repeat('.', 48);
                }
                $ml = 20;
                if (preg_match('/\bi3\b/', $attrs)) {
                    $ml = 60;
                } elseif (preg_match('/\bi2\b/', $attrs)) {
                    $ml = 40;
                } elseif (preg_match('/\bi1\b/', $attrs)) {
                    $ml = 20;
                }
                $bold = (bool) preg_match('/\bfl-b\b/', $attrs);
                $style = 'margin-left:' . $ml . 'pt;';
                if ($bold) {
                    $style .= 'font-weight:bold;';
                }
                // ค่าในช่อง fd ให้ขีดเส้นใต้ท้ายข้อความถ้ามีค่า
                $lines[] = '<p style="' . $style . '">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }
        if (empty($lines)) {
            $plain = propertyDocxText($html);
            $lines[] = '<p style="margin-left:20pt;">' . htmlspecialchars($plain !== '' ? $plain : str_repeat('.', 48), ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $replacements[$key] = implode("\n", $lines);
    }
    return $replacements;
}

/**
 * ปรับ HTML ให้ PhpWord อ่านได้ดีขึ้น
 */
function propertyPrepareHtmlForPhpWord(string $html): string
{
    if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
        $html = $m[1];
    }

    // ☐✓ จาก template → ☑
    $html = str_replace(['☐✓', '☐☑'], '☑', $html);

    // ค่าในช่องฟอร์ม: ไม่ใส่ <u> ทั้งก้อน (ทำให้ดูเหมือนเส้นแดงผิดพลาด)
    // ถ้าว่าง → จุดไข่ปลา, ถ้ามีค่า → ข้อความธรรมดา
    $html = preg_replace_callback(
        '/<span[^>]*class="[^"]*\bdot\b[^"]*"[^>]*>(.*?)<\/span>/is',
        static function ($m) {
            $inner = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
            if ($inner === '') {
                return '....................';
            }
            return htmlspecialchars($inner, ENT_QUOTES, 'UTF-8');
        },
        $html
    );

    // เหลือเฉพาะขีดเส้นใต้หัวข้อรายงาน
    $html = preg_replace(
        '/<p([^>]*)\sclass="[^"]*\btitle\b[^"]*"([^>]*)>/i',
        '<p$1$2 style="text-align:center;font-weight:bold;font-size:15pt;text-decoration:underline;">',
        $html
    );
    $html = preg_replace(
        '/<p([^>]*)\sclass="[^"]*\bsec\b[^"]*"([^>]*)>/i',
        '<p$1$2 style="font-weight:bold;">',
        $html
    );
    $html = preg_replace(
        '/<p([^>]*)\sclass="[^"]*\bsub\b[^"]*"([^>]*)>/i',
        '<p$1$2 style="font-weight:bold;margin-left:20pt;">',
        $html
    );
    $html = preg_replace(
        '/<p([^>]*)\sclass="[^"]*\brow\b[^"]*\bi3\b[^"]*"([^>]*)>/i',
        '<p$1$2 style="margin-left:60pt;">',
        $html
    );
    $html = preg_replace(
        '/<p([^>]*)\sclass="[^"]*\brow\b[^"]*\bi2\b[^"]*"([^>]*)>/i',
        '<p$1$2 style="margin-left:40pt;">',
        $html
    );
    $html = preg_replace(
        '/<p([^>]*)\sclass="[^"]*\brow\b[^"]*\bi1\b[^"]*"([^>]*)>/i',
        '<p$1$2 style="margin-left:20pt;">',
        $html
    );
    $html = preg_replace('/\sclass="[^"]*"/', '', $html);

    return $html;
}

/**
 * แนบรูป 1 หน้า — คืน path ไฟล์ชั่วคราว (ต้องลบหลัง save DOCX เท่านั้น)
 */
function propertyAddPhotoPage($section, $photo, int $index): ?string
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
    } elseif (strpos($mime, 'webp') !== false) {
        // PhpWord มักไม่รองรับ webp → แปลงเป็น png ถ้ามี GD
        if (function_exists('imagecreatefromstring')) {
            $im = @imagecreatefromstring($bytes);
            if ($im !== false) {
                ob_start();
                imagepng($im);
                $bytes = ob_get_clean();
                imagedestroy($im);
                $ext = '.png';
            }
        }
    }

    $dir = sys_get_temp_dir();
    $path = $dir . DIRECTORY_SEPARATOR . 'prop_photo_' . uniqid('', true) . $ext;
    if (file_put_contents($path, $bytes) === false) {
        return null;
    }

    try {
        $section->addImage($path, [
            'width' => 450,
            'alignment' => Jc::CENTER,
        ]);
        $section->addText(
            'ภาพที่ ' . $index,
            propertyDocxFs(),
            ['alignment' => Jc::CENTER, 'spaceBefore' => 120]
        );
        return $path;
    } catch (Throwable $e) {
        @unlink($path);
        $section->addText(
            '[ไม่สามารถแทรกรูปภาพที่ ' . $index . ' ได้]',
            propertyDocxFs(['color' => 'CC0000']),
            ['alignment' => Jc::CENTER]
        );
        return null;
    }
}

/**
 * สร้างและส่งไฟล์ .docx ให้เบราว์เซอร์ดาวน์โหลด
 */
function downloadPropertyReportDocx(int $incidentId, string $baseName, array $photoDataUris = []): void
{
    $export = propertyLoadReportExport($incidentId);
    $replacements = propertyConvertDynamicRows($export['replacements']);

    [$phpWord, $tempPhotos] = buildPropertyReportPhpWord('', $replacements, $photoDataUris);

    $filenameUtf8 = $baseName . '.docx';
    $filenameAscii = preg_replace('/[^\x20-\x7E]/', '', $baseName);
    $filenameAscii = (trim($filenameAscii) !== '' ? $filenameAscii : ('property_report_' . $incidentId)) . '.docx';

    $tmpFile = tempnam(sys_get_temp_dir(), 'prop_docx_');
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
