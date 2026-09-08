<?php
/**
 * traffic_report_docx.php — DOCX รายงานตรวจพิสูจน์คดีจราจร (type 05)
 * โครงเดียวกับคดีเพลิงไหม้: PhpWord API + ระยะแน่น
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/word_docx_dotted_rule.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

const TRAFFIC_DOCX_FONT = 'TH Sarabun New';
const TRAFFIC_DOCX_SIZE = 14;

function trafficLoadExport(int $incidentId): array
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
    include __DIR__ . '/gen_pdf_traffic_report_html.php';
    ob_end_clean();
    ini_set('display_errors', $prev);

    $export = $GLOBALS['traffic_report_export'] ?? null;
    if (!is_array($export) || empty($export['replacements'])) {
        throw new RuntimeException('ไม่สามารถโหลดข้อมูลรายงานคดีจราจรได้');
    }
    return $export;
}

function trafficFs(array $extra = []): array
{
    return array_merge([
        'name' => TRAFFIC_DOCX_FONT,
        'size' => TRAFFIC_DOCX_SIZE,
        'color' => '000000',
    ], $extra);
}

function trafficPs(int $indentPt = 0, array $extra = []): array
{
    $style = ['spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 1.15];
    if ($indentPt > 0) {
        $style['indentation'] = ['left' => $indentPt * 20];
    }
    return array_merge($style, $extra);
}

function trafficText(string $htmlOrText): string
{
    $t = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $htmlOrText)), ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', trim($t));
    return $t ?? '';
}

function trafficR(array $r, string $key): string
{
    return trafficText($r[$key] ?? '');
}

function trafficCb(array $r, string $key): string
{
    $m = trafficText((string) ($r[$key] ?? ''));
    return ($m !== '' && $m !== '☐') ? '☑' : '☐';
}

function trafficDot(string $value, int $dots = 24): string
{
    $v = trim($value);
    return $v !== '' ? $v : str_repeat('.', $dots);
}

function trafficLine($section, string $text, int $indentPt = 0, array $fontExtra = [], array $paraExtra = []): void
{
    $t = trim($text);
    $isBlankish = ($t === '' || preg_match('/^\.+$/u', $t) || preg_match('/^\d+(?:\.\d+)*\.\s*$/u', $t));
    if ($isBlankish && wordDocxTryDottedBlankLine(
        $section,
        $text,
        ['name' => TRAFFIC_DOCX_FONT, 'size' => TRAFFIC_DOCX_SIZE, 'color' => '000000'],
        function (int $i, array $e = []) { return trafficPs($i, $e); },
        $indentPt,
        $fontExtra
    )) {
        return;
    }
    $section->addText($t === '' ? ' ' : $t, trafficFs($fontExtra), trafficPs($indentPt, $paraExtra));
}

function trafficAddDynamicLines($section, string $html): void
{
    if (trim($html) === '') {
        trafficLine($section, '', 20);
        return;
    }
    if (!preg_match_all('/<div([^>]*)>((?:(?!<div\b).)*)<\/div>/is', $html, $matches, PREG_SET_ORDER)) {
        $plain = trafficText($html);
        trafficLine($section, $plain !== '' ? $plain : '', 20);
        return;
    }
    foreach ($matches as $match) {
        $indent = 20;
        if (preg_match('/\bi3\b/', $match[1])) {
            $indent = 60;
        } elseif (preg_match('/\bi2\b|fd-block-i2/', $match[1])) {
            $indent = 40;
        }
        $text = trafficText($match[2]);
        $bold = preg_match('/\bfl-b\b/', $match[1]) ? ['bold' => true] : [];
        trafficLine($section, $text, $indent, $bold);
    }
}

function trafficAddBlock($section, string $htmlOrText, int $indentPt = 40): void
{
    $plain = trafficText($htmlOrText);
    if ($plain === '') {
        trafficLine($section, '', $indentPt);
        return;
    }
    trafficLine($section, $plain, $indentPt);
}

function trafficPreparePhoto($photo): ?array
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
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'traffic_photo_' . uniqid('', true) . $ext;
    if (file_put_contents($path, $bytes) === false) {
        return null;
    }
    return ['path' => $path];
}

/**
 * @return array{0: PhpWord, 1: string[]}
 */
function buildTrafficReportPhpWord(array $replacements, array $photoDataUris = []): array
{
    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName(TRAFFIC_DOCX_FONT);
    $phpWord->setDefaultFontSize(TRAFFIC_DOCX_SIZE);
    $phpWord->setDefaultParagraphStyle(trafficPs(0));

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
    $reportNo = trafficR($r, '{{report_no}}');
    $yearShort = trafficR($r, '{{report_year_short}}');
    $agency = trafficR($r, '{{agency_name}}');

    $header = $section->addHeader();
    $ht = $header->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $ht->addRow();
    $c1 = $ht->addCell(7500);
    $run = $c1->addTextRun(trafficPs(0));
    $run->addText('รายงานการตรวจพิสูจน์ที่', trafficFs(['underline' => 'single']));
    $run->addText('  ' . trafficDot($reportNo, 10) . ' /25 ' . trafficDot($yearShort, 4), trafficFs());
    $c2 = $ht->addCell(2000);
    $c2->addPreserveText('{PAGE}/{NUMPAGES}', trafficFs(), ['alignment' => Jc::END]);
    $header->addText('หน่วยงาน  ' . trafficDot($agency, 40), trafficFs(), trafficPs(0, ['spaceAfter' => 60]));

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
        'รายงานการตรวจพิสูจน์คดีจราจร',
        trafficFs(['size' => 10, 'color' => '808080']),
        trafficPs(0, ['alignment' => Jc::END])
    );

    // ===== หน้า 1 =====
    trafficLine($section, 'รายงานการตรวจพิสูจน์คดีจราจร', 0, [
        'bold' => true, 'size' => 15, 'underline' => 'single',
    ], ['alignment' => Jc::CENTER, 'spaceBefore' => 60, 'spaceAfter' => 60]);

    trafficLine($section, '1. การรับแจ้งเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    trafficLine(
        $section,
        'เมื่อวันที่ ' . trafficDot(trafficR($r, '{{receive_date}}'), 18)
        . ' เวลา ' . trafficDot(trafficR($r, '{{receive_time}}'), 8)
        . ' น. ตามประจำวันข้อที่ ' . trafficDot(trafficR($r, '{{daily_ref}}'), 10),
        20
    );
    trafficLine($section, 'กลุ่มงานตรวจสถานที่เกิดเหตุ', 20);
    trafficLine(
        $section,
        'กองพิสูจน์หลักฐานกลาง/ ศูนย์พิสูจน์หลักฐาน '
        . trafficDot(trafficR($r, '{{agency_center_name}}'), 16)
        . ' / พิสูจน์หลักฐานจังหวัด '
        . trafficDot(trafficR($r, '{{agency_province_name}}'), 16),
        20
    );
    trafficLine(
        $section,
        // ★ ข้อ 7: ออกเป็นข้อความไทย แทนช่องติ๊ก
        'ได้รับแจ้งตาม  ' . trafficDot(trafficR($r, '{{notify_method_text}}'), 30),
        20
    );
    trafficLine(
        $section,
        'จาก สน./สภ. ' . trafficDot(trafficR($r, '{{from_station}}'), 20)
        . ' ของเจ้าหน้าที่ ร่วมตรวจพิสูจน์คดีอุบัติเหตุจราจร',
        20
    );
    trafficLine(
        $section,
        'โดยมี ' . trafficDot(trafficR($r, '{{officer_name}}'), 30)
        . ' เป็นพนักงานสอบสวน',
        20
    );
    trafficLine($section, 'รายละเอียดปรากฏดังนี้', 20);

    trafficAddDynamicLines($section, $r['{{vehicle_rows}}'] ?? '');

    trafficLine(
        $section,
        '1.' . trafficDot(trafficR($r, '{{vehicle_next_no}}'), 2) . ' สถานที่เกิดเหตุ',
        20,
        ['bold' => true],
        ['spaceBefore' => 40]
    );
    trafficAddBlock($section, $r['{{scene_location}}'] ?? '', 40);

    trafficLine($section, '2. จุดประสงค์ในการตรวจพิสูจน์ (เลือกตามความเหมาะสม)', 0, ['bold' => true], ['spaceBefore' => 40]);
    trafficLine(
        $section,
        '1. เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนระหว่างรถของกลาง '
        . trafficDot(trafficR($r, '{{purpose_qty_1}}'), 6)
        . ' (ระบุจำนวนคัน) หรือไม่ อย่างไร',
        20
    );
    trafficLine(
        $section,
        'หรือ 2. เพื่อทราบว่ารถของกลาง '
        . trafficDot(trafficR($r, '{{purpose_qty_2}}'), 6)
        . ' (ระบุจำนวนคัน) มีการเฉี่ยวชนกันหรือไม่ อย่างไร',
        20
    );
    trafficLine($section, 'หรือ 3. เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนที่รถของกลางทั้งหนึ่งหรือไม่และมีลักษณะการเฉี่ยวชนอย่างไร', 20);
    trafficLine($section, 'หรือ 4. เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนระหว่างรถของกลางหรือไม่อย่างไร', 20);
    trafficLine($section, 'หรือ 5. ' . trafficDot(trafficR($r, '{{purpose_other_text}}'), 40), 20);

    trafficLine($section, '3. ผู้ตรวจพิสูจน์', 0, ['bold' => true], ['spaceBefore' => 40]);
    trafficAddDynamicLines($section, $r['{{inspector_rows}}'] ?? '');

    trafficLine($section, '4. ผลการตรวจพิสูจน์', 0, ['bold' => true], ['spaceBefore' => 40]);
    trafficLine($section, 'พฤติการณ์คดี', 20);
    trafficAddBlock($section, $r['{{case_behavior}}'] ?? '', 40);

    // ===== หน้า 2 =====
    $section->addPageBreak();
    trafficLine(
        $section,
        'ได้ทำการตรวจพิสูจน์ ' . trafficDot(trafficR($r, '{{inspect_location}}'), 40),
        20
    );
    trafficLine(
        $section,
        'ที่ ' . trafficDot(trafficR($r, '{{inspect_place}}'), 30) . ' (ระบุสถานที่ตรวจฯ)',
        20
    );
    trafficLine(
        $section,
        'เมื่อวันที่ ' . trafficDot(trafficR($r, '{{analyze_date}}'), 18)
        . ' เวลาประมาณ ' . trafficDot(trafficR($r, '{{analyze_time}}'), 8) . ' น.',
        20
    );
    trafficLine($section, 'ปรากฏรายละเอียดดังนี้', 20, [], ['spaceBefore' => 40]);
    trafficAddDynamicLines($section, $r['{{analysis_vehicle_rows}}'] ?? '');

    // ===== หน้า 3 =====
    $section->addPageBreak();
    trafficAddDynamicLines($section, $r['{{analysis_extra_section}}'] ?? '');
    trafficAddDynamicLines($section, $r['{{scene_detail_section}}'] ?? '');

    trafficLine($section, '5. ผลการตรวจเปรียบเทียบ', 0, ['bold' => true], ['spaceBefore' => 40]);
    trafficLine($section, 'จากการเปรียบเทียบสภาพร่องรอยความเสียหายและการแลกเปลี่ยนวัตถุพยานของรถของกลางทั้ง', 20);
    trafficLine(
        $section,
        trafficDot(trafficR($r, '{{compare_total_items}}'), 6) . ' รายการ พบว่า',
        40
    );

    trafficLine($section, '5.1 รอยครูดบริเวณ', 20, ['bold' => true], ['spaceBefore' => 40]);
    trafficAddBlock($section, $r['{{compare_5_1}}'] ?? '', 40);
    trafficLine($section, 'มีลักษณะรอยและระดับความสูงเข้ากันได้', 20);
    trafficLine($section, 'กับรอยครูดบริเวณ', 40);
    trafficAddBlock($section, $r['{{compare_5_1_sub}}'] ?? '', 40);
    trafficLine(
        $section,
        'ตามผลการตรวจในข้อ ' . trafficDot(trafficR($r, '{{compare_5_ref}}'), 6),
        40
    );

    trafficLine($section, '5.2', 20, ['bold' => true], ['spaceBefore' => 40]);
    trafficAddBlock($section, $r['{{compare_5_2}}'] ?? '', 40);

    trafficLine($section, '5.3 (ลักษณะร่องรอยที่เข้ากัน)', 20, ['bold' => true], ['spaceBefore' => 40]);
    trafficAddBlock($section, $r['{{compare_5_3}}'] ?? '', 40);

    trafficLine($section, '5.4 (ร่องรอยความเสียหายของรถกับร่องรอยบริเวณจุดชนในสถานที่เกิดเหตุ)', 20, ['bold' => true], ['spaceBefore' => 40]);
    trafficAddBlock($section, $r['{{compare_5_4}}'] ?? '', 40);

    trafficLine($section, '6. ความเห็น', 0, ['bold' => true], ['spaceBefore' => 40]);
    trafficLine($section, 'จากผลการตรวจในข้อ 4 และ 5', 20);
    trafficAddBlock($section, $r['{{opinion}}'] ?? '', 40);
    trafficLine($section, 'จนได้รับความเสียหายดังที่ปรากฏ', 20);

    $section->addTextBreak(1);
    $sig = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $sig->addRow();
    $sig->addCell(4500);
    $sc = $sig->addCell(5000);
    foreach ([
        '(ลงชื่อ) .............................................................. ผู้รายงาน',
        '( ' . trafficDot(trafficR($r, '{{sign_name}}'), 28) . ' )',
        'ตำแหน่ง ' . trafficDot(trafficR($r, '{{sign_position}}'), 28),
        'วันที่ ' . trafficDot(trafficR($r, '{{sign_date_day}}'), 4)
            . ' เดือน ' . trafficDot(trafficR($r, '{{sign_date_month}}'), 10)
            . ' พ.ศ. ' . trafficDot(trafficR($r, '{{sign_date_year}}'), 6),
    ] as $sigLine) {
        $sc->addText($sigLine, trafficFs(), trafficPs(0, ['alignment' => Jc::CENTER, 'lineHeight' => 1.8]));
    }

    $tempPhotoPaths = [];
    $photoIndex = 0;
    foreach (array_values($photoDataUris) as $photo) {
        $probe = trafficPreparePhoto($photo);
        if ($probe === null) {
            continue;
        }
        $section->addPageBreak();
        try {
            $section->addImage($probe['path'], ['width' => 450, 'alignment' => Jc::CENTER]);
            $photoIndex++;
            $section->addText(
                'ภาพที่ ' . $photoIndex,
                trafficFs(),
                trafficPs(0, ['alignment' => Jc::CENTER, 'spaceBefore' => 80])
            );
            $tempPhotoPaths[] = $probe['path'];
        } catch (Throwable $e) {
            @unlink($probe['path']);
        }
    }

    return [$phpWord, $tempPhotoPaths];
}

function downloadTrafficReportDocx(int $incidentId, string $baseName, array $photoDataUris = []): void
{
    $export = trafficLoadExport($incidentId);
    [$phpWord, $tempPhotos] = buildTrafficReportPhpWord($export['replacements'], $photoDataUris);

    $filenameUtf8 = $baseName . '.docx';
    $filenameAscii = preg_replace('/[^\x20-\x7E]/', '', $baseName);
    $filenameAscii = (trim($filenameAscii) !== '' ? $filenameAscii : ('traffic_' . $incidentId)) . '.docx';

    $tmpFile = tempnam(sys_get_temp_dir(), 'traffic_docx_');
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
