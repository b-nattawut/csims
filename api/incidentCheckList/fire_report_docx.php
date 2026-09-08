<?php
/**
 * fire_report_docx.php — DOCX รายงานคดีเพลิงไหม้ (type 04)
 * โครงเดียวกับคดีทรัพย์ / ระเบิด: PhpWord API + ระยะแน่น
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/word_docx_dotted_rule.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

const FIRE_DOCX_FONT = 'TH Sarabun New';
const FIRE_DOCX_SIZE = 14;

function fireLoadExport(int $incidentId): array
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
    include __DIR__ . '/gen_pdf_fire_report_html.php';
    ob_end_clean();
    ini_set('display_errors', $prev);

    $export = $GLOBALS['fire_report_export'] ?? null;
    if (!is_array($export) || empty($export['replacements'])) {
        throw new RuntimeException('ไม่สามารถโหลดข้อมูลรายงานคดีเพลิงไหม้ได้');
    }
    return $export;
}

function fireFs(array $extra = []): array
{
    return array_merge([
        'name' => FIRE_DOCX_FONT,
        'size' => FIRE_DOCX_SIZE,
        'color' => '000000',
    ], $extra);
}

function firePs(int $indentPt = 0, array $extra = []): array
{
    $style = ['spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 1.15];
    if ($indentPt > 0) {
        $style['indentation'] = ['left' => $indentPt * 20];
    }
    return array_merge($style, $extra);
}

function fireText(string $htmlOrText): string
{
    $t = html_entity_decode(strip_tags($htmlOrText), ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', trim($t));
    return $t ?? '';
}

function fireR(array $r, string $key): string
{
    return fireText($r[$key] ?? '');
}

function fireCb(array $r, string $key): string
{
    $m = fireText((string) ($r[$key] ?? ''));
    return ($m !== '' && $m !== '☐') ? '☑' : '☐';
}

function fireDot(string $value, int $dots = 24): string
{
    $v = trim($value);
    return $v !== '' ? $v : str_repeat('.', $dots);
}

function fireLine($section, string $text, int $indentPt = 0, array $fontExtra = [], array $paraExtra = []): void
{
    $t = trim($text);
    $isBlankish = ($t === '' || preg_match('/^\.+$/u', $t) || preg_match('/^\d+(?:\.\d+)*\.\s*$/u', $t));
    if ($isBlankish && wordDocxTryDottedBlankLine(
        $section,
        $text,
        ['name' => FIRE_DOCX_FONT, 'size' => FIRE_DOCX_SIZE, 'color' => '000000'],
        function (int $i, array $e = []) { return firePs($i, $e); },
        $indentPt,
        $fontExtra
    )) {
        return;
    }
    $section->addText($t === '' ? ' ' : $t, fireFs($fontExtra), firePs($indentPt, $paraExtra));
}

function fireAddDynamicLines($section, string $html): void
{
    if (trim($html) === '') {
        fireLine($section, '', 20);
        return;
    }
    if (!preg_match_all('/<div([^>]*)>((?:(?!<div\b).)*)<\/div>/is', $html, $matches, PREG_SET_ORDER)) {
        $plain = fireText($html);
        fireLine($section, $plain !== '' ? $plain : '', 20);
        return;
    }
    $prevWasEmpty = false;
    foreach ($matches as $match) {
        $indent = 20;
        if (preg_match('/\bi3\b/', $match[1])) {
            $indent = 60;
        } elseif (preg_match('/\bi2\b/', $match[1])) {
            $indent = 40;
        }
        $text = fireText($match[2]);
        if ($text === '' || preg_match('/^\.+$/u', $text)) {
            if ($prevWasEmpty) {
                continue;
            }
            $prevWasEmpty = true;
            fireLine($section, '', $indent);
            continue;
        }
        $prevWasEmpty = false;
        fireLine($section, $text, $indent);
    }
}

function firePreparePhoto($photo): ?array
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
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fire_photo_' . uniqid('', true) . $ext;
    if (file_put_contents($path, $bytes) === false) {
        return null;
    }
    return ['path' => $path];
}

/**
 * @return array{0: PhpWord, 1: string[]}
 */
function buildFireReportPhpWord(array $replacements, array $photoDataUris = []): array
{
    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName(FIRE_DOCX_FONT);
    $phpWord->setDefaultFontSize(FIRE_DOCX_SIZE);
    $phpWord->setDefaultParagraphStyle(firePs(0));

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
    $reportNo = fireR($r, '{{report_no}}');
    $yearShort = fireR($r, '{{report_year_short}}');
    $agency = fireR($r, '{{agency_name}}');

    $header = $section->addHeader();
    $ht = $header->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $ht->addRow();
    $c1 = $ht->addCell(7500);
    $run = $c1->addTextRun(firePs(0));
    $run->addText('รายงานการตรวจสถานที่เกิดเหตุที่', fireFs(['underline' => 'single']));
    $run->addText('  ' . fireDot($reportNo, 10) . ' /25 ' . fireDot($yearShort, 4), fireFs());
    $c2 = $ht->addCell(2000);
    $c2->addPreserveText('{PAGE}/{NUMPAGES}', fireFs(), ['alignment' => Jc::END]);
    $header->addText('หน่วยงาน  ' . fireDot($agency, 40), fireFs(), firePs(0, ['spaceAfter' => 60]));

    $footer = $section->addFooter();
    $ft = $footer->addTable([
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT,
        'borderBottomSize' => 6,
        'borderBottomColor' => 'BFBFBF',
    ]);
    $ft->addRow();
    $ft->addCell(4000);
    $fr = $ft->addCell(6000);
    $fr->addText(
        'รายงานการตรวจสถานที่เกิดเหตุคดีเพลิงไหม้',
        fireFs(['size' => 10, 'color' => '808080']),
        firePs(0, ['alignment' => Jc::END])
    );

    // ===== หน้า 1 =====
    fireLine($section, 'รายงานการตรวจสถานที่เกิดเหตุคดีเพลิงไหม้', 0, [
        'bold' => true, 'size' => 15, 'underline' => 'single',
    ], ['alignment' => Jc::CENTER, 'spaceBefore' => 60, 'spaceAfter' => 60]);

    fireLine($section, '1. การรับแจ้งเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    fireLine(
        $section,
        'เมื่อวันที่ ' . fireDot(fireR($r, '{{receive_date}}'), 18)
        . ' เวลา ' . fireDot(fireR($r, '{{receive_time}}'), 8)
        . ' น. ตามประจำวันข้อที่ ' . fireDot(fireR($r, '{{daily_ref}}'), 10),
        20
    );
    fireLine(
        $section,
        // ★ ข้อ 7: ออกเป็นข้อความไทย แทนช่องติ๊ก
        'ได้รับแจ้งตาม  ' . fireDot(fireR($r, '{{notify_method_text}}'), 30),
        20
    );
    fireLine(
        $section,
        'จาก สน./สภ. ' . fireDot(fireR($r, '{{from_station}}'), 20)
        . ' ขอเจ้าหน้าที่ร่วมตรวจสถานที่เกิดเหตุคดีเพลิงไหม้',
        20
    );
    fireLine(
        $section,
        'โดยมี ' . fireDot(fireR($r, '{{investigator_name}}'), 30)
        . ' เป็นพนักงานสอบสวนเจ้าของคดี',
        20
    );

    fireLine($section, '2. สถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    fireLine($section, 'เหตุเกิดที่ ' . fireDot(fireR($r, '{{crime_location}}'), 50), 20);

    fireLine($section, '3. วันเวลาที่ทราบเหตุ/เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    fireLine(
        $section,
        'ผู้เสียหายทราบเหตุ/เกิดเหตุ เมื่อวันที่ ' . fireDot(fireR($r, '{{victim_know_date}}'), 18)
        . ' เวลาประมาณ ' . fireDot(fireR($r, '{{victim_know_time}}'), 8) . ' น.',
        20
    );
    fireLine(
        $section,
        'พนักงานสอบสวนทราบเหตุ เมื่อวันที่ ' . fireDot(fireR($r, '{{officer_know_date}}'), 18)
        . ' เวลาประมาณ ' . fireDot(fireR($r, '{{officer_know_time}}'), 8) . ' น.',
        20
    );

    fireLine($section, '4. วันเวลาตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    fireLine(
        $section,
        'ตรวจสถานที่เกิดเหตุ เมื่อวันที่ ' . fireDot(fireR($r, '{{inspect_date}}'), 18)
        . ' เวลาประมาณ ' . fireDot(fireR($r, '{{inspect_time}}'), 8) . ' น.',
        20
    );
    fireLine(
        $section,
        'ตรวจเพิ่มเติม เมื่อวันที่ ' . fireDot(fireR($r, '{{inspect_add_date}}'), 18)
        . ' เวลาประมาณ ' . fireDot(fireR($r, '{{inspect_add_time}}'), 8) . ' น.',
        20
    );

    fireLine($section, '5. ผู้ตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    fireAddDynamicLines($section, $r['{{inspector_rows}}'] ?? '');

    fireLine($section, '6. ลักษณะของสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    fireLine($section, '6.1 ลักษณะภายนอก', 20, ['bold' => true]);
    fireLine($section, fireR($r, '{{exterior_detail}}'), 40);
    fireLine(
        $section,
        'จำนวนชั้น ' . fireDot(fireR($r, '{{floor_count}}'), 4)
        . '  รั้วล้อมรอบ '
        . fireCb($r, '{{chk_fence_yes}}') . ' มี  '
        . fireCb($r, '{{chk_fence_no}}') . ' ไม่มี',
        40
    );
    fireLine(
        $section,
        'ด้านหน้าติด ' . fireDot(fireR($r, '{{ext_front}}'), 20)
        . '  ด้านซ้ายติด ' . fireDot(fireR($r, '{{ext_left}}'), 20),
        40
    );
    fireLine(
        $section,
        'ด้านขวาติด ' . fireDot(fireR($r, '{{ext_right}}'), 20)
        . '  ด้านหลังติด ' . fireDot(fireR($r, '{{ext_back}}'), 20),
        40
    );

    fireLine($section, '6.2 ลักษณะภายใน', 20, ['bold' => true], ['spaceBefore' => 40]);
    fireLine($section, fireR($r, '{{interior_detail}}'), 40);

    fireLine($section, '6.3 บริเวณที่เกิดเหตุ', 20, ['bold' => true], ['spaceBefore' => 40]);
    fireLine(
        $section,
        'เกิดเหตุที่ ' . fireDot(fireR($r, '{{incident_area}}'), 20)
        . ' ขนาด ' . fireDot(fireR($r, '{{area_size}}'), 12)
        . ' หันหน้าไปทาง ' . fireDot(fireR($r, '{{facing_direction}}'), 12),
        40
    );

    // โครงสร้างต่อเนื่อง (ไม่ hard-break — กันหน้าว่าง)
    fireLine($section, 'ลักษณะโครงสร้าง', 20, ['bold' => true, 'underline' => 'single'], [
        'alignment' => Jc::CENTER, 'spaceBefore' => 40, 'spaceAfter' => 40,
    ]);
    fireLine($section, 'ฝาผนังด้านหน้า ' . fireDot(fireR($r, '{{wall_front}}'), 40), 40);
    fireLine($section, 'ฝาผนังด้านซ้าย ' . fireDot(fireR($r, '{{wall_left}}'), 40), 40);
    fireLine($section, 'ฝาผนังด้านขวา ' . fireDot(fireR($r, '{{wall_right}}'), 40), 40);
    fireLine($section, 'ฝาผนังด้านหลัง ' . fireDot(fireR($r, '{{wall_back}}'), 40), 40);
    fireLine(
        $section,
        'พื้นห้อง ' . fireDot(fireR($r, '{{floor_material}}'), 16)
        . ' เพดาน ' . fireDot(fireR($r, '{{ceiling}}'), 16)
        . ' หลังคา ' . fireDot(fireR($r, '{{roof}}'), 16),
        40
    );

    fireLine($section, 'ลักษณะการจัดวางสิ่งของ', 20, ['bold' => true, 'underline' => 'single'], ['spaceBefore' => 60]);
    fireLine($section, 'ด้านหน้า (ซ้ายไปขวา) ' . fireDot(fireR($r, '{{arrange_front}}'), 30), 40);
    fireLine($section, 'ด้านซ้าย (หน้าไปหลัง) ' . fireDot(fireR($r, '{{arrange_left}}'), 30), 40);
    fireLine($section, 'ด้านขวา (หน้าไปหลัง) ' . fireDot(fireR($r, '{{arrange_right}}'), 30), 40);
    fireLine($section, 'ด้านหลัง (ซ้ายไปขวา) ' . fireDot(fireR($r, '{{arrange_back}}'), 30), 40);
    fireLine($section, 'บริเวณอื่นๆ ' . fireDot(fireR($r, '{{arrange_other}}'), 40), 40);

    fireLine($section, '7. พฤติการณ์คดี และ สภาพความเสียหาย', 0, ['bold' => true], ['spaceBefore' => 40]);
    fireLine($section, 'พฤติการณ์ของคดีจากการสอบถามข้อมูลในเบื้องต้นจาก พงส. ได้ความว่า', 20);
    fireLine($section, fireR($r, '{{case_behavior}}'), 40);
    fireLine(
        $section,
        'การทำประกันภัย '
        . fireCb($r, '{{chk_insurance_yes}}') . ' มี  '
        . fireCb($r, '{{chk_insurance_no}}') . ' ไม่มี'
        . '    เวลาในการเผาไหม้ ' . fireDot(fireR($r, '{{burn_time}}'), 16),
        20
    );
    fireLine(
        $section,
        'การดับเพลิง '
        . fireCb($r, '{{chk_extinguish_yes}}') . ' ดับแล้ว  '
        . fireCb($r, '{{chk_extinguish_no}}') . ' ยังไม่ดับ  '
        . fireDot(fireR($r, '{{extinguish_detail}}'), 24),
        20
    );
    fireLine($section, 'สภาพความเสียหาย', 20);
    fireLine($section, fireR($r, '{{damage_condition}}'), 40);
    fireLine($section, 'การลุกลามของเพลิง', 20);
    fireLine($section, fireR($r, '{{spread_detail}}'), 40);

    fireLine($section, '7.1 ความเสียหายโครงสร้าง', 20, ['bold' => true], ['spaceBefore' => 40]);
    fireLine($section, 'ฝาผนังด้านหน้า ' . fireDot(fireR($r, '{{damage_wall_front}}'), 40), 40);
    fireLine($section, 'ฝาผนังด้านซ้าย ' . fireDot(fireR($r, '{{damage_wall_left}}'), 40), 40);
    fireLine($section, 'ฝาผนังด้านขวา ' . fireDot(fireR($r, '{{damage_wall_right}}'), 40), 40);
    fireLine($section, 'ฝาผนังด้านหลัง ' . fireDot(fireR($r, '{{damage_wall_back}}'), 40), 40);
    fireLine(
        $section,
        'พื้น ' . fireDot(fireR($r, '{{damage_floor}}'), 16)
        . ' หลังคา ' . fireDot(fireR($r, '{{damage_roof}}'), 16)
        . ' เพดาน ' . fireDot(fireR($r, '{{damage_ceiling}}'), 16),
        40
    );

    // 7.2+ ต่อเนื่อง (ไม่ hard-break — กันหน้าว่าง)
    fireLine($section, '7.2 ความเสียหายสิ่งของ', 20, ['bold' => true], ['spaceBefore' => 40]);
    fireLine($section, 'ด้านหน้า ' . fireDot(fireR($r, '{{damage_obj_front}}'), 40), 40);
    fireLine($section, 'ด้านซ้าย ' . fireDot(fireR($r, '{{damage_obj_left}}'), 40), 40);
    fireLine($section, 'ด้านขวา ' . fireDot(fireR($r, '{{damage_obj_right}}'), 40), 40);
    fireLine($section, 'ด้านหลัง ' . fireDot(fireR($r, '{{damage_obj_back}}'), 40), 40);
    fireLine(
        $section,
        'พื้น ' . fireDot(fireR($r, '{{damage_obj_floor}}'), 16)
        . ' หลังคา ' . fireDot(fireR($r, '{{damage_obj_roof}}'), 16)
        . ' เพดาน ' . fireDot(fireR($r, '{{damage_obj_ceiling}}'), 16),
        40
    );

    fireLine($section, '7.3 บริเวณจุดเริ่มต้นของเพลิง', 20, ['bold' => true], ['spaceBefore' => 40]);
    fireLine($section, fireR($r, '{{first_area}}'), 40);

    fireLine($section, '7.4 สภาพสวิตช์ไฟฟ้า/อุปกรณ์ไฟฟ้า', 20, ['bold' => true], ['spaceBefore' => 40]);
    fireLine($section, fireR($r, '{{switch_condition}}'), 40);

    fireLine($section, '7.5 ความเสียหายอาคารข้างเคียง', 20, ['bold' => true], ['spaceBefore' => 40]);
    fireLine(
        $section,
        fireCb($r, '{{chk_adjacent_found}}') . ' พบ  '
        . fireCb($r, '{{chk_adjacent_notfound}}') . ' ไม่พบ  '
        . fireDot(fireR($r, '{{adjacent_damage_detail}}'), 30),
        40
    );

    fireLine($section, '7.6 ร่องรอยและวัตถุพยานที่ตรวจพบในสถานที่เกิดเหตุ', 20, ['bold' => true], ['spaceBefore' => 40]);
    fireLine($section, fireR($r, '{{evidence_found}}'), 40);

    fireLine($section, '8. สรุปผลการตรวจ', 0, ['bold' => true], ['spaceBefore' => 40]);
    fireLine($section, '8.1 บริเวณจุดเริ่มต้นของเพลิง', 20, ['bold' => true]);
    fireLine($section, fireR($r, '{{origin_area}}'), 40);
    fireLine($section, '8.2 แหล่งเชื้อเพลิง', 20, ['bold' => true]);
    fireLine($section, fireR($r, '{{fuel_source}}'), 40);
    fireLine($section, '8.3 แหล่งความร้อน', 20, ['bold' => true]);
    fireLine($section, fireR($r, '{{heat_source}}'), 40);
    fireLine($section, '8.4 อื่นๆ', 20, ['bold' => true]);
    fireLine($section, fireR($r, '{{summary_other}}'), 40);

    fireLine($section, '9. ความเห็น', 0, ['bold' => true], ['spaceBefore' => 40]);
    fireLine($section, 'จุดเริ่มต้นของเพลิง', 20);
    fireLine($section, fireR($r, '{{opinion_first_area}}'), 40);
    fireLine(
        $section,
        'สาเหตุของเพลิง  '
        . fireCb($r, '{{chk_cause_believed}}') . ' เชื่อว่า '
        . fireDot(fireR($r, '{{cause_believed_detail}}'), 30),
        20
    );
    fireLine(
        $section,
        fireCb($r, '{{chk_cause_unknown}}') . ' ไม่ทราบสาเหตุ '
        . fireDot(fireR($r, '{{cause_unknown_detail}}'), 30),
        40
    );

    fireLine($section, 'เจ้าหน้าที่ ' . fireDot(fireR($r, '{{handover_officer}}'), 40), 20, [], ['spaceBefore' => 60]);
    fireLine(
        $section,
        'ตรวจสถานที่เกิดเหตุเสร็จสิ้น ส่งมอบสถานที่เกิดเหตุคืนให้กับ '
        . fireDot(fireR($r, '{{handover_to}}'), 30),
        20
    );
    fireLine(
        $section,
        'เมื่อวันที่ ' . fireDot(fireR($r, '{{handover_date}}'), 18)
        . ' เวลาประมาณ ' . fireDot(fireR($r, '{{handover_time}}'), 8) . ' น.',
        20
    );

    $section->addTextBreak(1);
    $sig = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $sig->addRow();
    $sig->addCell(4500);
    $sc = $sig->addCell(5000);
    foreach ([
        'ลงชื่อ ........................................ ผู้รายงาน',
        '(' . fireDot(fireR($r, '{{signer_name}}'), 28) . ')',
        'ตำแหน่ง ' . fireDot(fireR($r, '{{signer_position}}'), 28),
        'วันที่ ' . fireDot(fireR($r, '{{sign_date}}'), 20),
    ] as $sigLine) {
        $sc->addText($sigLine, fireFs(), firePs(0, ['alignment' => Jc::CENTER, 'lineHeight' => 1.8]));
    }

    $tempPhotoPaths = [];
    $photoIndex = 0;
    foreach (array_values($photoDataUris) as $photo) {
        $probe = firePreparePhoto($photo);
        if ($probe === null) {
            continue;
        }
        $section->addPageBreak();
        try {
            $section->addImage($probe['path'], ['width' => 450, 'alignment' => Jc::CENTER]);
            $photoIndex++;
            $section->addText(
                'ภาพที่ ' . $photoIndex,
                fireFs(),
                firePs(0, ['alignment' => Jc::CENTER, 'spaceBefore' => 80])
            );
            $tempPhotoPaths[] = $probe['path'];
        } catch (Throwable $e) {
            @unlink($probe['path']);
        }
    }

    return [$phpWord, $tempPhotoPaths];
}

function downloadFireReportDocx(int $incidentId, string $baseName, array $photoDataUris = []): void
{
    $export = fireLoadExport($incidentId);
    [$phpWord, $tempPhotos] = buildFireReportPhpWord($export['replacements'], $photoDataUris);

    $filenameUtf8 = $baseName . '.docx';
    $filenameAscii = preg_replace('/[^\x20-\x7E]/', '', $baseName);
    $filenameAscii = (trim($filenameAscii) !== '' ? $filenameAscii : ('fire_' . $incidentId)) . '.docx';

    $tmpFile = tempnam(sys_get_temp_dir(), 'fire_docx_');
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
