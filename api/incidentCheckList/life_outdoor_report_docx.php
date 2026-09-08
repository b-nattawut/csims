<?php
/**
 * life_outdoor_report_docx.php — DOCX รายงานคดีชีวิต นอกอาคาร (F-CS-14)
 * โครงเดียวกับคดีทรัพย์ / ชีวิตในอาคาร
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/word_docx_dotted_rule.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

const LIFE_OUT_DOCX_FONT = 'TH Sarabun New';
const LIFE_OUT_DOCX_SIZE = 14;

function lifeOutLoadExport(int $incidentId): array
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
    include __DIR__ . '/gen_pdf_life_report_outdoor_html.php';
    ob_end_clean();
    ini_set('display_errors', $prev);

    $export = $GLOBALS['life_outdoor_report_export'] ?? null;
    if (!is_array($export) || empty($export['replacements'])) {
        throw new RuntimeException('ไม่สามารถโหลดข้อมูลรายงานคดีชีวิต (นอกอาคาร) ได้');
    }
    return $export;
}

function lifeOutFs(array $extra = []): array
{
    return array_merge([
        'name' => LIFE_OUT_DOCX_FONT,
        'size' => LIFE_OUT_DOCX_SIZE,
        'color' => '000000',
    ], $extra);
}

function lifeOutPs(int $indentPt = 0, array $extra = []): array
{
    $style = ['spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 1.15];
    if ($indentPt > 0) {
        $style['indentation'] = ['left' => $indentPt * 20];
    }
    return array_merge($style, $extra);
}

function lifeOutText(string $htmlOrText): string
{
    $t = html_entity_decode(strip_tags($htmlOrText), ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', trim($t));
    return $t ?? '';
}

function lifeOutR(array $r, string $key): string
{
    return lifeOutText($r[$key] ?? '');
}

function lifeOutCb(array $r, string $key): string
{
    $m = lifeOutText((string) ($r[$key] ?? ''));
    return ($m !== '' && $m !== '☐') ? '☑' : '☐';
}

function lifeOutDot(string $value, int $dots = 24): string
{
    $v = trim($value);
    return $v !== '' ? $v : str_repeat('.', $dots);
}

function lifeOutLine($section, string $text, int $indentPt = 0, array $fontExtra = [], array $paraExtra = []): void
{
    $t = trim($text);
    $isBlankish = ($t === '' || preg_match('/^\.+$/u', $t) || preg_match('/^\d+(?:\.\d+)*\.\s*$/u', $t));
    if ($isBlankish && wordDocxTryDottedBlankLine(
        $section,
        $text,
        ['name' => LIFE_OUT_DOCX_FONT, 'size' => LIFE_OUT_DOCX_SIZE, 'color' => '000000'],
        function (int $i, array $e = []) { return lifeOutPs($i, $e); },
        $indentPt,
        $fontExtra
    )) {
        return;
    }
    $section->addText($t === '' ? ' ' : $t, lifeOutFs($fontExtra), lifeOutPs($indentPt, $paraExtra));
}

function lifeOutAddDynamicLines($section, string $html): void
{
    if (trim($html) === '') {
        lifeOutLine($section, '', 20);
        return;
    }
    if (!preg_match_all('/<div([^>]*)>((?:(?!<div\b).)*)<\/div>/is', $html, $matches, PREG_SET_ORDER)) {
        $plain = lifeOutText($html);
        lifeOutLine($section, $plain !== '' ? $plain : '', 20);
        return;
    }
    foreach ($matches as $match) {
        $indent = 20;
        if (preg_match('/\bi3\b/', $match[1])) {
            $indent = 60;
        } elseif (preg_match('/\bi2\b/', $match[1])) {
            $indent = 40;
        }
        $text = lifeOutText($match[2]);
        lifeOutLine($section, $text, $indent);
    }
}

function lifeOutPreparePhoto($photo): ?array
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
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'life_out_photo_' . uniqid('', true) . $ext;
    if (file_put_contents($path, $bytes) === false) {
        return null;
    }
    return ['path' => $path];
}

/**
 * @return array{0: PhpWord, 1: string[]}
 */
function buildLifeOutdoorReportPhpWord(array $replacements, array $photoDataUris = []): array
{
    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName(LIFE_OUT_DOCX_FONT);
    $phpWord->setDefaultFontSize(LIFE_OUT_DOCX_SIZE);
    $phpWord->setDefaultParagraphStyle(lifeOutPs(0));

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
    $reportNo = lifeOutR($r, '{{report_no}}');
    $yearShort = lifeOutR($r, '{{report_year_short}}');
    $agency = lifeOutR($r, '{{agency_name}}');
    $agencyShort = lifeOutR($r, '{{agency_name_short}}');
    if ($agencyShort === '') {
        $agencyShort = $agency;
    }

    $header = $section->addHeader();
    $ht = $header->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $ht->addRow();
    $c1 = $ht->addCell(7500);
    $run = $c1->addTextRun(lifeOutPs(0));
    $run->addText('รายงานการตรวจสถานที่เกิดเหตุที่', lifeOutFs(['underline' => 'single']));
    $run->addText('  ' . lifeOutDot($reportNo, 10) . ' /25 ' . lifeOutDot($yearShort, 4), lifeOutFs());
    $c2 = $ht->addCell(2000);
    $c2->addPreserveText('{PAGE}/{NUMPAGES}', lifeOutFs(), ['alignment' => Jc::END]);
    $header->addText('หน่วยงาน  ' . lifeOutDot($agency, 40), lifeOutFs(), lifeOutPs(0, ['spaceAfter' => 60]));

    $footer = $section->addFooter();
    $ft = $footer->addTable([
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT,
        'borderBottomSize' => 6,
        'borderBottomColor' => 'BFBFBF',
    ]);
    $ft->addRow();
    $fl = $ft->addCell(5000);
    $fl->addText('ปฏิบัติงานตาม', lifeOutFs(['size' => 10, 'color' => '808080']), lifeOutPs(0));
    $fl->addText('OPFS – CS – SP – 02', lifeOutFs(['size' => 10, 'color' => '808080']), lifeOutPs(0));
    $fr = $ft->addCell(5000);
    foreach (['F-CS-14 แก้ไขครั้งที่ 2', 'แก้ไขวันที่ 2 ก.ย. 63', 'เริ่มใช้ 1 ต.ค. 63'] as $fline) {
        $fr->addText($fline, lifeOutFs(['size' => 10, 'color' => '808080']), lifeOutPs(0, ['alignment' => Jc::END]));
    }

    // ===== หน้า 1 =====
    lifeOutLine($section, 'รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต (นอกอาคาร)', 0, [
        'bold' => true, 'size' => 15, 'underline' => 'single',
    ], ['alignment' => Jc::CENTER, 'spaceBefore' => 60, 'spaceAfter' => 60]);

    lifeOutLine($section, '1. การรับแจ้งเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    lifeOutLine(
        $section,
        'เมื่อวันที่ ' . lifeOutDot(lifeOutR($r, '{{receive_date}}'), 18)
        . ' เวลา ' . lifeOutDot(lifeOutR($r, '{{receive_time}}'), 8)
        . ' น. ตามประจำวันข้อที่ ' . lifeOutDot(lifeOutR($r, '{{daily_ref}}'), 10)
        . ' กสก.พฐก./ กลก.ศพฐ./',
        20
    );
    lifeOutLine(
        $section,
        // ★ ข้อ 7: ออกเป็นข้อความไทย แทนช่องติ๊ก
        'พฐ.จว. ' . lifeOutDot($agencyShort, 16)
        . ' ได้รับแจ้งตาม  ' . lifeOutDot(lifeOutR($r, '{{notify_method_text}}'), 30),
        20
    );
    lifeOutLine(
        $section,
        'จาก สน./สภ. ' . lifeOutDot(lifeOutR($r, '{{from_station}}'), 20)
        . ' ขอเจ้าหน้าที่ ร่วมตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต',
        20
    );
    lifeOutLine(
        $section,
        'โดยมี ' . lifeOutDot(lifeOutR($r, '{{investigator_name}}'), 30)
        . ' เป็นพนักงานสอบสวนเจ้าของคดี',
        20
    );

    lifeOutLine($section, '2. สถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    lifeOutLine(
        $section,
        'ผู้เสียชีวิต/ผู้บาดเจ็บ/ผู้เสียหาย ' . lifeOutDot(lifeOutR($r, '{{victim_name}}'), 24)
        . ' อายุประมาณ ' . lifeOutDot(lifeOutR($r, '{{victim_age}}'), 4) . ' ปี',
        20
    );

    lifeOutLine($section, '3. วันเวลาที่ทราบเหตุ/เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    lifeOutLine(
        $section,
        'ผู้เสียหายทราบเหตุ/เกิดเหตุ เมื่อวันที่ ' . lifeOutDot(lifeOutR($r, '{{victim_know_date}}'), 18)
        . ' เวลาประมาณ ' . lifeOutDot(lifeOutR($r, '{{victim_know_time}}'), 8) . ' น.',
        20
    );
    lifeOutLine(
        $section,
        'พนักงานสอบสวนทราบเหตุ เมื่อวันที่ ' . lifeOutDot(lifeOutR($r, '{{officer_know_date}}'), 18)
        . ' เวลาประมาณ ' . lifeOutDot(lifeOutR($r, '{{officer_know_time}}'), 8) . ' น.',
        20
    );

    lifeOutLine($section, '4. วันเวลาตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    lifeOutLine(
        $section,
        'ตรวจสถานที่เกิดเหตุ เมื่อวันที่ ' . lifeOutDot(lifeOutR($r, '{{inspect_date}}'), 18)
        . ' เวลาประมาณ ' . lifeOutDot(lifeOutR($r, '{{inspect_time}}'), 8) . ' น.',
        20
    );
    lifeOutLine(
        $section,
        'ตรวจสถานที่เกิดเหตุเพิ่มเติม เมื่อวันที่ ' . lifeOutDot(lifeOutR($r, '{{inspect_add_date}}'), 18)
        . ' เวลาประมาณ ' . lifeOutDot(lifeOutR($r, '{{inspect_add_time}}'), 8) . ' น.',
        20
    );

    lifeOutLine($section, '5. ผู้ตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    lifeOutAddDynamicLines($section, $r['{{inspector_rows}}'] ?? '');

    lifeOutLine($section, '6. ลักษณะของสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    lifeOutLine($section, lifeOutDot(lifeOutR($r, '{{scene_characteristics}}'), 60), 20);

    lifeOutLine($section, '7. ผลการตรวจสถานที่เกิดเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    lifeOutLine(
        $section,
        'พฤติการณ์ของคดีจากการสอบถามข้อมูลในเบื้องต้นจาก พงส. ได้ความว่า '
        . lifeOutDot(lifeOutR($r, '{{case_behavior}}'), 40),
        20
    );
    lifeOutLine($section, 'จากการตรวจสถานที่เกิดเหตุ', 20, [], ['spaceBefore' => 40]);
    lifeOutLine($section, '7.1 สภาพของสถานที่เกิดเหตุเมื่อไปถึง', 20);
    lifeOutLine($section, lifeOutDot(lifeOutR($r, '{{scene_condition}}'), 60), 40);

    // ===== หน้า 2 =====
    $section->addPageBreak();
    lifeOutLine($section, '7.2 ลักษณะสภาพศพ', 20, ['bold' => true]);
    lifeOutLine($section, '7.2.1 พบศพ/ไม่พบศพ ' . lifeOutDot(lifeOutR($r, '{{body_found}}'), 40), 40);
    lifeOutLine($section, '7.2.2 ตำแหน่งที่พบศพ ' . lifeOutDot(lifeOutR($r, '{{body_position}}'), 40), 40);
    lifeOutLine($section, '7.2.3 สภาพศพ ' . lifeOutDot(lifeOutR($r, '{{body_condition}}'), 40), 40);
    lifeOutLine($section, '7.2.4 สภาพเครื่องแต่งกายและทรัพย์สิน ' . lifeOutDot(lifeOutR($r, '{{body_clothing}}'), 30), 40);
    lifeOutLine($section, '7.2.5 รอยบาดแผลที่ศพ ' . lifeOutDot(lifeOutR($r, '{{body_wounds}}'), 40), 40);

    lifeOutLine($section, '7.3 ร่องรอยและวัตถุพยานที่ตรวจพบในสถานที่เกิดเหตุ', 20, ['bold' => true], ['spaceBefore' => 40]);
    lifeOutLine($section, lifeOutDot(lifeOutR($r, '{{evidence_found}}'), 60), 40);

    lifeOutLine($section, '7.4 วัตถุพยานที่ตรวจเก็บในสถานที่เกิดเหตุ', 20, ['bold' => true], ['spaceBefore' => 40]);
    lifeOutLine($section, lifeOutDot(lifeOutR($r, '{{evidence_collected}}'), 60), 40);

    lifeOutLine($section, '7.5 การดำเนินการเกี่ยวกับวัตถุพยาน', 20, ['bold' => true], ['spaceBefore' => 40]);
    lifeOutLine($section, lifeOutDot(lifeOutR($r, '{{evidence_action}}'), 60), 40);

    lifeOutLine(
        $section,
        '7.6 เจ้าหน้าที่ กสก.พฐก. / กสก.ศพฐ / พฐ.จว. '
        . lifeOutDot(lifeOutR($r, '{{handover_officer}}'), 24)
        . ' ตรวจสถานที่เกิดเหตุเสร็จสิ้น',
        20,
        ['bold' => true],
        ['spaceBefore' => 60]
    );
    lifeOutLine(
        $section,
        'พร้อมทั้งส่งมอบสถานที่เกิดเหตุคืนให้กับ ' . lifeOutDot(lifeOutR($r, '{{handover_to}}'), 30),
        20
    );
    lifeOutLine(
        $section,
        'เมื่อวันที่ ' . lifeOutDot(lifeOutR($r, '{{handover_date}}'), 18)
        . ' เวลาประมาณ ' . lifeOutDot(lifeOutR($r, '{{handover_time}}'), 8) . ' น.',
        20
    );

    $section->addTextBreak(1);
    $sig = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $sig->addRow();
    $sig->addCell(4500);
    $sc = $sig->addCell(5000);
    foreach ([
        '(ลงชื่อ) ............................................................',
        '( ' . lifeOutDot(lifeOutR($r, '{{signer_name}}'), 28) . ' )',
        '(ตำแหน่ง) ' . lifeOutDot(lifeOutR($r, '{{signer_position}}'), 28),
        '........../.............../.............',
    ] as $sigLine) {
        $sc->addText($sigLine, lifeOutFs(), lifeOutPs(0, ['alignment' => Jc::CENTER, 'lineHeight' => 1.8]));
    }

    $tempPhotoPaths = [];
    $photoIndex = 0;
    foreach (array_values($photoDataUris) as $photo) {
        $probe = lifeOutPreparePhoto($photo);
        if ($probe === null) {
            continue;
        }
        $section->addPageBreak();
        try {
            $section->addImage($probe['path'], ['width' => 450, 'alignment' => Jc::CENTER]);
            $photoIndex++;
            $section->addText(
                'ภาพที่ ' . $photoIndex,
                lifeOutFs(),
                lifeOutPs(0, ['alignment' => Jc::CENTER, 'spaceBefore' => 80])
            );
            $tempPhotoPaths[] = $probe['path'];
        } catch (Throwable $e) {
            @unlink($probe['path']);
        }
    }

    return [$phpWord, $tempPhotoPaths];
}

function downloadLifeOutdoorReportDocx(int $incidentId, string $baseName, array $photoDataUris = []): void
{
    $export = lifeOutLoadExport($incidentId);
    [$phpWord, $tempPhotos] = buildLifeOutdoorReportPhpWord($export['replacements'], $photoDataUris);

    $filenameUtf8 = $baseName . '.docx';
    $filenameAscii = preg_replace('/[^\x20-\x7E]/', '', $baseName);
    $filenameAscii = (trim($filenameAscii) !== '' ? $filenameAscii : ('life_outdoor_' . $incidentId)) . '.docx';

    $tmpFile = tempnam(sys_get_temp_dir(), 'life_out_docx_');
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
