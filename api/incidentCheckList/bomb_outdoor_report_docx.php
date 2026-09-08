<?php
/**
 * bomb_outdoor_report_docx.php — DOCX รายงานคดีระเบิด นอกอาคาร (F-CS-16)
 * โครงเดียวกับระเบิดในอาคาร / คดีชีวิต: PhpWord API + ระยะแน่น
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/word_docx_dotted_rule.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

const BOMB_OUT_DOCX_FONT = 'TH Sarabun New';
const BOMB_OUT_DOCX_SIZE = 14;

function bombOutLoadExport(int $incidentId): array
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
    include __DIR__ . '/gen_pdf_bomb_report_outdoor_html.php';
    ob_end_clean();
    ini_set('display_errors', $prev);

    $export = $GLOBALS['bomb_outdoor_report_export'] ?? null;
    if (!is_array($export) || empty($export['replacements'])) {
        throw new RuntimeException('ไม่สามารถโหลดข้อมูลรายงานคดีระเบิด (นอกอาคาร) ได้');
    }
    return $export;
}

function bombOutFs(array $extra = []): array
{
    return array_merge([
        'name' => BOMB_OUT_DOCX_FONT,
        'size' => BOMB_OUT_DOCX_SIZE,
        'color' => '000000',
    ], $extra);
}

function bombOutPs(int $indentPt = 0, array $extra = []): array
{
    $style = ['spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 1.15];
    if ($indentPt > 0) {
        $style['indentation'] = ['left' => $indentPt * 20];
    }
    return array_merge($style, $extra);
}

function bombOutText(string $htmlOrText): string
{
    $t = html_entity_decode(strip_tags($htmlOrText), ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', trim($t));
    return $t ?? '';
}

function bombOutR(array $r, string $key): string
{
    return bombOutText($r[$key] ?? '');
}

function bombOutCb(array $r, string $key): string
{
    $m = bombOutText((string) ($r[$key] ?? ''));
    return ($m !== '' && $m !== '☐') ? '☑' : '☐';
}

function bombOutDot(string $value, int $dots = 24): string
{
    $v = trim($value);
    return $v !== '' ? $v : str_repeat('.', $dots);
}

function bombOutLine($section, string $text, int $indentPt = 0, array $fontExtra = [], array $paraExtra = []): void
{
    $t = trim($text);
    $isBlankish = ($t === '' || preg_match('/^\.+$/u', $t) || preg_match('/^\d+(?:\.\d+)*\.\s*$/u', $t));
    if ($isBlankish && wordDocxTryDottedBlankLine(
        $section,
        $text,
        ['name' => BOMB_OUT_DOCX_FONT, 'size' => BOMB_OUT_DOCX_SIZE, 'color' => '000000'],
        function (int $i, array $e = []) { return bombOutPs($i, $e); },
        $indentPt,
        $fontExtra
    )) {
        return;
    }
    $section->addText($t === '' ? ' ' : $t, bombOutFs($fontExtra), bombOutPs($indentPt, $paraExtra));
}

function bombOutAddDynamicLines($section, string $html): void
{
    if (trim($html) === '') {
        bombOutLine($section, '', 20);
        return;
    }
    if (!preg_match_all('/<div([^>]*)>((?:(?!<div\b).)*)<\/div>/is', $html, $matches, PREG_SET_ORDER)) {
        $plain = bombOutText($html);
        bombOutLine($section, $plain !== '' ? $plain : '', 20);
        return;
    }
    foreach ($matches as $match) {
        $indent = 20;
        if (preg_match('/\bi3\b/', $match[1])) {
            $indent = 60;
        } elseif (preg_match('/\bi2\b/', $match[1])) {
            $indent = 40;
        }
        $text = bombOutText($match[2]);
        bombOutLine($section, $text, $indent);
    }
}

function bombOutPreparePhoto($photo): ?array
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
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bomb_out_photo_' . uniqid('', true) . $ext;
    if (file_put_contents($path, $bytes) === false) {
        return null;
    }
    return ['path' => $path];
}

/**
 * @return array{0: PhpWord, 1: string[]}
 */
function buildBombOutdoorReportPhpWord(array $replacements, array $photoDataUris = []): array
{
    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName(BOMB_OUT_DOCX_FONT);
    $phpWord->setDefaultFontSize(BOMB_OUT_DOCX_SIZE);
    $phpWord->setDefaultParagraphStyle(bombOutPs(0));

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
    $reportNo = bombOutR($r, '{{report_no}}');
    $yearShort = bombOutR($r, '{{report_year_short}}');
    $agency = bombOutR($r, '{{agency_name}}');

    // Header
    $header = $section->addHeader();
    $ht = $header->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $ht->addRow();
    $c1 = $ht->addCell(7500);
    $run = $c1->addTextRun(bombOutPs(0));
    $run->addText('รายงานการตรวจสถานที่เกิดเหตุที่', bombOutFs(['underline' => 'single']));
    $run->addText('  ' . bombOutDot($reportNo, 10) . ' /25 ' . bombOutDot($yearShort, 4), bombOutFs());
    $c2 = $ht->addCell(2000);
    $c2->addPreserveText('{PAGE}/{NUMPAGES}', bombOutFs(), ['alignment' => Jc::END]);
    $header->addText('หน่วยงาน  ' . bombOutDot($agency, 40), bombOutFs(), bombOutPs(0, ['spaceAfter' => 60]));

    // Footer F-CS-16
    $footer = $section->addFooter();
    $ft = $footer->addTable([
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT,
        'borderBottomSize' => 6,
        'borderBottomColor' => 'BFBFBF',
    ]);
    $ft->addRow();
    $fl = $ft->addCell(5000);
    $fl->addText('ปฏิบัติงานตาม', bombOutFs(['size' => 10, 'color' => '808080']), bombOutPs(0));
    $fl->addText('OPFS – CS – SP – 03', bombOutFs(['size' => 10, 'color' => '808080']), bombOutPs(0));
    $fr = $ft->addCell(5000);
    foreach (['F-CS-16 แก้ไขครั้งที่ 2', 'แก้ไขวันที่ 2 ก.ค. 63', 'เริ่มใช้ 1 ก.ค. 63'] as $fline) {
        $fr->addText($fline, bombOutFs(['size' => 10, 'color' => '808080']), bombOutPs(0, ['alignment' => Jc::END]));
    }

    // ===== หน้า 1 =====
    bombOutLine($section, 'รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด (นอกอาคาร)', 0, [
        'bold' => true, 'size' => 15, 'underline' => 'single',
    ], ['alignment' => Jc::CENTER, 'spaceBefore' => 60, 'spaceAfter' => 60]);

    bombOutLine($section, '1. การรับแจ้งเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    bombOutLine(
        $section,
        'เมื่อวันที่ ' . bombOutDot(bombOutR($r, '{{receive_date}}'), 18)
        . ' เวลา ' . bombOutDot(bombOutR($r, '{{receive_time}}'), 8)
        . ' น. ตามประจำวันข้อที่ ' . bombOutDot(bombOutR($r, '{{daily_ref}}'), 10),
        20
    );
    bombOutLine(
        $section,
        'กสก.พฐก./ กสก.ศพฐ./พฐ.จว. ' . bombOutDot($agency, 40),
        20
    );
    bombOutLine(
        $section,
        // ★ ข้อ 7: ออกเป็นข้อความไทย แทนช่องติ๊ก
        'ได้รับแจ้งตาม  ' . bombOutDot(bombOutR($r, '{{notify_method_text}}'), 30),
        20
    );
    bombOutLine(
        $section,
        'จาก สน./สภ. ' . bombOutDot(bombOutR($r, '{{from_station}}'), 20)
        . ' ขอเจ้าหน้าที่ร่วมตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด',
        20
    );
    bombOutLine(
        $section,
        'โดยมี ' . bombOutDot(bombOutR($r, '{{investigator_name}}'), 30)
        . ' เป็นพนักงานสอบสวนเจ้าของคดี',
        20
    );

    bombOutLine($section, '2. สถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    bombOutAddDynamicLines($section, $r['{{crime_location_rows}}'] ?? '');
    bombOutLine(
        $section,
        'ผู้เสียหาย/ผู้ร้องทุกข์/ผู้ครอบครอง ' . bombOutDot(bombOutR($r, '{{victim_name}}'), 24)
        . ' อายุประมาณ ' . bombOutDot(bombOutR($r, '{{victim_age}}'), 4) . ' ปี',
        20
    );

    bombOutLine($section, '3. วันเวลาที่ทราบเหตุ/เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    bombOutLine(
        $section,
        'ผู้เสียหายทราบเหตุ/เกิดเหตุ เมื่อวันที่ ' . bombOutDot(bombOutR($r, '{{victim_know_date}}'), 18)
        . ' เวลาประมาณ ' . bombOutDot(bombOutR($r, '{{victim_know_time}}'), 8) . ' น.',
        20
    );
    bombOutLine(
        $section,
        'พนักงานสอบสวนทราบเหตุ เมื่อวันที่ ' . bombOutDot(bombOutR($r, '{{officer_know_date}}'), 18)
        . ' เวลาประมาณ ' . bombOutDot(bombOutR($r, '{{officer_know_time}}'), 8) . ' น.',
        20
    );

    bombOutLine($section, '4. วันเวลาตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    bombOutLine(
        $section,
        'ตรวจสถานที่เกิดเหตุ เมื่อวันที่ ' . bombOutDot(bombOutR($r, '{{inspect_date}}'), 18)
        . ' เวลาประมาณ ' . bombOutDot(bombOutR($r, '{{inspect_time}}'), 8) . ' น.',
        20
    );
    bombOutLine(
        $section,
        'ตรวจสถานที่เกิดเหตุเพิ่มเติม เมื่อวันที่ ' . bombOutDot(bombOutR($r, '{{inspect_add_date}}'), 18)
        . ' เวลาประมาณ ' . bombOutDot(bombOutR($r, '{{inspect_add_time}}'), 8) . ' น.',
        20
    );

    bombOutLine($section, '5. ผู้ตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    bombOutAddDynamicLines($section, $r['{{inspector_rows}}'] ?? '');

    bombOutLine($section, '6. ลักษณะของสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    bombOutAddDynamicLines($section, $r['{{scene_description_rows}}'] ?? '');

    // ===== หน้า 2 =====
    $section->addPageBreak();
    bombOutLine($section, 'ผลการตรวจสถานที่เกิดเหตุ', 20, [], ['spaceBefore' => 40]);
    $caseBehavior = bombOutR($r, '{{case_behavior}}');
    if ($caseBehavior !== '') {
        bombOutLine(
            $section,
            'พฤติการณ์ของคดีจากการสอบถามข้อมูลในเบื้องต้นจาก พงส. ได้ความว่า '
            . bombOutDot($caseBehavior, 40),
            20
        );
    }
    bombOutLine($section, '7.1 สภาพทั่วไปของสถานที่เกิดเหตุ', 20, ['bold' => true], ['spaceBefore' => 40]);
    bombOutAddDynamicLines($section, $r['{{scene_condition_rows}}'] ?? '');

    bombOutLine($section, '7.2 ผู้เสียชีวิต', 20, ['bold' => true], ['spaceBefore' => 40]);
    bombOutLine($section, '7.2.1 พบศพ/ไม่พบศพ ' . bombOutDot(bombOutR($r, '{{body_found}}'), 40), 40);
    bombOutAddDynamicLines($section, $r['{{body_position_rows}}'] ?? '');
    bombOutAddDynamicLines($section, $r['{{body_condition_rows}}'] ?? '');
    bombOutAddDynamicLines($section, $r['{{body_clothing_rows}}'] ?? '');
    bombOutAddDynamicLines($section, $r['{{body_wounds_rows}}'] ?? '');

    bombOutLine($section, '7.3 สภาพความเสียหาย', 20, ['bold' => true], ['spaceBefore' => 40]);
    bombOutAddDynamicLines($section, $r['{{damage_detail_rows}}'] ?? '');

    bombOutLine($section, '7.3.1 ตำแหน่งจุดศูนย์กลางระเบิด', 20, ['bold' => true], ['spaceBefore' => 40]);
    bombOutAddDynamicLines($section, $r['{{explosion_position_rows}}'] ?? '');

    bombOutLine($section, '7.4 ร่องรอยและวัตถุพยานที่ตรวจพบในสถานที่เกิดเหตุ', 20, ['bold' => true], ['spaceBefore' => 40]);
    bombOutAddDynamicLines($section, $r['{{evidence_found_rows}}'] ?? '');

    bombOutLine($section, '7.5 ตรวจเก็บวัตถุพยาน รอยลายนิ้วมือแฝง', 20, ['bold' => true], ['spaceBefore' => 40]);
    bombOutAddDynamicLines($section, $r['{{evidence_collected_rows}}'] ?? '');

    bombOutLine($section, '7.6 การดำเนินการเกี่ยวกับวัตถุพยาน', 20, ['bold' => true], ['spaceBefore' => 40]);
    bombOutAddDynamicLines($section, $r['{{evidence_action_rows}}'] ?? '');

    // ===== หน้า 3 =====
    $section->addPageBreak();
    bombOutLine(
        $section,
        '7.7 เจ้าหน้าที่ กสก.พฐก. / กสก.ศพฐ..... / พฐ.จว. '
        . bombOutDot(bombOutR($r, '{{handover_agency}}'), 24)
        . ' ตรวจสถานที่เกิดเหตุเสร็จสิ้น',
        20,
        ['bold' => true],
        ['spaceBefore' => 60]
    );
    bombOutLine(
        $section,
        'พร้อมทั้งส่งมอบสถานที่เกิดเหตุคืนให้กับ ' . bombOutDot(bombOutR($r, '{{handover_to}}'), 30),
        40
    );
    bombOutLine(
        $section,
        'เมื่อวันที่ ' . bombOutDot(bombOutR($r, '{{handover_date}}'), 18)
        . ' เวลาประมาณ ' . bombOutDot(bombOutR($r, '{{handover_time}}'), 8) . ' น.',
        40
    );

    $section->addTextBreak(1);
    $sig = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $sig->addRow();
    $sig->addCell(4500);
    $sc = $sig->addCell(5000);
    foreach ([
        '(ลงชื่อ) ............................................................',
        '( ' . bombOutDot(bombOutR($r, '{{signer_name}}'), 28) . ' )',
        '(ตำแหน่ง) ' . bombOutDot(bombOutR($r, '{{signer_position}}'), 28),
        '........../.............../.............',
    ] as $sigLine) {
        $sc->addText($sigLine, bombOutFs(), bombOutPs(0, ['alignment' => Jc::CENTER, 'lineHeight' => 1.8]));
    }

    $tempPhotoPaths = [];
    $photoIndex = 0;
    foreach (array_values($photoDataUris) as $photo) {
        $probe = bombOutPreparePhoto($photo);
        if ($probe === null) {
            continue;
        }
        $section->addPageBreak();
        try {
            $section->addImage($probe['path'], ['width' => 450, 'alignment' => Jc::CENTER]);
            $photoIndex++;
            $section->addText(
                'ภาพที่ ' . $photoIndex,
                bombOutFs(),
                bombOutPs(0, ['alignment' => Jc::CENTER, 'spaceBefore' => 80])
            );
            $tempPhotoPaths[] = $probe['path'];
        } catch (Throwable $e) {
            @unlink($probe['path']);
        }
    }

    return [$phpWord, $tempPhotoPaths];
}

function downloadBombOutdoorReportDocx(int $incidentId, string $baseName, array $photoDataUris = []): void
{
    $export = bombOutLoadExport($incidentId);
    [$phpWord, $tempPhotos] = buildBombOutdoorReportPhpWord($export['replacements'], $photoDataUris);

    $filenameUtf8 = $baseName . '.docx';
    $filenameAscii = preg_replace('/[^\x20-\x7E]/', '', $baseName);
    $filenameAscii = (trim($filenameAscii) !== '' ? $filenameAscii : ('bomb_outdoor_' . $incidentId)) . '.docx';

    $tmpFile = tempnam(sys_get_temp_dir(), 'bomb_out_docx_');
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
