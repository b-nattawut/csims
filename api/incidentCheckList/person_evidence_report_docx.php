<?php
/**
 * person_evidence_report_docx.php — DOCX รายงานตรวจเก็บวัตถุพยานที่บุคคล (type 08)
 * โครงเดียวกับวัตถุพยานที่เกิดเหตุ: PhpWord API + ระยะแน่น
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/word_docx_dotted_rule.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

const PERSON_EV_DOCX_FONT = 'TH Sarabun New';
const PERSON_EV_DOCX_SIZE = 14;

function personEvLoadExport(int $incidentId): array
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
    include __DIR__ . '/gen_pdf_person_evidence_report_html.php';
    ob_end_clean();
    ini_set('display_errors', $prev);

    $export = $GLOBALS['person_evidence_report_export'] ?? null;
    if (!is_array($export) || empty($export['replacements'])) {
        throw new RuntimeException('ไม่สามารถโหลดข้อมูลรายงานวัตถุพยานบุคคลได้');
    }
    return $export;
}

function personEvFs(array $extra = []): array
{
    return array_merge([
        'name' => PERSON_EV_DOCX_FONT,
        'size' => PERSON_EV_DOCX_SIZE,
        'color' => '000000',
    ], $extra);
}

function personEvPs(int $indentPt = 0, array $extra = []): array
{
    $style = ['spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 1.15];
    if ($indentPt > 0) {
        $style['indentation'] = ['left' => $indentPt * 20];
    }
    return array_merge($style, $extra);
}

function personEvText(string $htmlOrText): string
{
    $t = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $htmlOrText)), ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', trim($t));
    return $t ?? '';
}

function personEvR(array $r, string $key): string
{
    return personEvText($r[$key] ?? '');
}

function personEvCb(array $r, string $key): string
{
    $m = personEvText((string) ($r[$key] ?? ''));
    return ($m !== '' && $m !== '☐') ? '☑' : '☐';
}

function personEvDot(string $value, int $dots = 24): string
{
    $v = trim($value);
    return $v !== '' ? $v : str_repeat('.', $dots);
}

function personEvLine($section, string $text, int $indentPt = 0, array $fontExtra = [], array $paraExtra = []): void
{
    $t = trim($text);
    $isBlankish = ($t === '' || preg_match('/^\.+$/u', $t) || preg_match('/^\d+(?:\.\d+)*\.\s*$/u', $t));
    if ($isBlankish && wordDocxTryDottedBlankLine(
        $section,
        $text,
        ['name' => PERSON_EV_DOCX_FONT, 'size' => PERSON_EV_DOCX_SIZE, 'color' => '000000'],
        function (int $i, array $e = []) { return personEvPs($i, $e); },
        $indentPt,
        $fontExtra
    )) {
        return;
    }
    $section->addText($t === '' ? ' ' : $t, personEvFs($fontExtra), personEvPs($indentPt, $paraExtra));
}

function personEvAddDynamicLines($section, string $html): void
{
    if (trim($html) === '') {
        personEvLine($section, '', 20);
        return;
    }
    if (!preg_match_all('/<div([^>]*)>((?:(?!<div\b).)*)<\/div>/is', $html, $matches, PREG_SET_ORDER)) {
        $plain = personEvText($html);
        personEvLine($section, $plain !== '' ? $plain : '', 20);
        return;
    }
    foreach ($matches as $match) {
        $indent = 20;
        if (preg_match('/\bi3\b/', $match[1])) {
            $indent = 60;
        } elseif (preg_match('/\bi2\b/', $match[1])) {
            $indent = 40;
        }
        $text = personEvText($match[2]);
        personEvLine($section, $text, $indent);
    }
}

function personEvPreparePhoto($photo): ?array
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
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'person_ev_photo_' . uniqid('', true) . $ext;
    if (file_put_contents($path, $bytes) === false) {
        return null;
    }
    return ['path' => $path];
}

/**
 * @return array{0: PhpWord, 1: string[]}
 */
function buildPersonEvidenceReportPhpWord(array $replacements, array $photoDataUris = []): array
{
    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName(PERSON_EV_DOCX_FONT);
    $phpWord->setDefaultFontSize(PERSON_EV_DOCX_SIZE);
    $phpWord->setDefaultParagraphStyle(personEvPs(0));

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
    $reportNo = personEvR($r, '{{report_no}}');
    $yearShort = personEvR($r, '{{report_year_short}}');
    $agency = personEvR($r, '{{agency_name}}');

    $header = $section->addHeader();
    $ht = $header->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $ht->addRow();
    $c1 = $ht->addCell(7500);
    $run = $c1->addTextRun(personEvPs(0));
    $run->addText('รายงานการตรวจเก็บวัตถุพยานที่', personEvFs(['underline' => 'single']));
    $run->addText('  ' . personEvDot($reportNo, 10) . ' /25 ' . personEvDot($yearShort, 4), personEvFs());
    $c2 = $ht->addCell(2000);
    $c2->addPreserveText('{PAGE}/{NUMPAGES}', personEvFs(), ['alignment' => Jc::END]);
    $header->addText('หน่วยงาน  ' . personEvDot($agency, 40), personEvFs(), personEvPs(0, ['spaceAfter' => 60]));

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
    foreach (['บัญชีแนบท้ายคำสั่ง สพฐ.ตร.', 'ที่ 848/2561'] as $fline) {
        $fr->addText($fline, personEvFs(['size' => 10, 'color' => '808080']), personEvPs(0, ['alignment' => Jc::END]));
    }

    // ===== หน้า 1 =====
    personEvLine($section, 'รายงานการตรวจเก็บวัตถุพยานที่บุคคล', 0, [
        'bold' => true, 'size' => 15, 'underline' => 'single',
    ], ['alignment' => Jc::CENTER, 'spaceBefore' => 60, 'spaceAfter' => 60]);

    personEvLine($section, '1. สิ่งที่ได้รับจาก /การรับแจ้งเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    personEvLine(
        $section,
        'เมื่อวันที่ ' . personEvDot(personEvR($r, '{{receive_date}}'), 18)
        . ' เวลา ' . personEvDot(personEvR($r, '{{receive_time}}'), 8)
        . ' น. กลุ่มงานตรวจที่เกิดเหตุ กองพิสูจน์หลักฐานกลาง/',
        20
    );
    personEvLine(
        $section,
        'ศูนย์พิสูจน์หลักฐาน ' . personEvDot(personEvR($r, '{{center_name}}'), 16)
        . ' /พิสูจน์หลักฐานจังหวัด ' . personEvDot(personEvR($r, '{{province_name}}'), 16),
        20
    );
    personEvLine(
        $section,
        // ★ ข้อ 7: ออกเป็นข้อความไทย แทนช่องติ๊ก
        'ได้รับแจ้ง ' . personEvDot(personEvR($r, '{{notify_method_text}}'), 30)
        . ' จากสถานีตำรวจนครบาล/สถานีตำรวจภูธร',
        20
    );
    personEvLine($section, 'จาก สน./สภ. ' . personEvDot(personEvR($r, '{{police_station}}'), 40), 20);
    personEvLine(
        $section,
        'ที่ ' . personEvDot(personEvR($r, '{{document_no}}'), 20)
        . ' ลงวันที่ ' . personEvDot(personEvR($r, '{{document_date}}'), 18),
        20
    );
    personEvLine(
        $section,
        'ในคดี ' . personEvDot(personEvR($r, '{{case_no}}'), 24) . ' สถานที่เกิดเหตุ',
        20
    );
    personEvLine($section, personEvDot(personEvR($r, '{{incident_location}}'), 60), 20);
    personEvLine(
        $section,
        'เหตุเกิดเมื่อวันที่ ' . personEvDot(personEvR($r, '{{incident_date}}'), 18)
        . ' เวลาประมาณ ' . personEvDot(personEvR($r, '{{incident_time}}'), 8) . ' น.',
        20
    );
    personEvLine(
        $section,
        'มี ' . personEvDot(personEvR($r, '{{investigator_name}}'), 30)
        . ' เป็นพนักงานสอบสวน ขอส่ง',
        20
    );
    personEvAddDynamicLines($section, $r['{{person_item_rows}}'] ?? '');

    personEvLine($section, '2. จุดประสงค์ในการตรวจ', 0, ['bold' => true], ['spaceBefore' => 40]);
    personEvLine(
        $section,
        'เพื่อทำการตรวจเก็บ ' . personEvDot(personEvR($r, '{{purpose_detail}}'), 40) . ' บุคคลดังกล่าว',
        20
    );

    personEvLine($section, '3. ผลการตรวจ', 0, ['bold' => true], ['spaceBefore' => 40]);
    personEvLine(
        $section,
        'ได้ทำการตรวจเก็บ ที่ ' . personEvDot(personEvR($r, '{{inspect_location}}'), 30),
        20
    );
    personEvLine(
        $section,
        'เมื่อวันที่ ' . personEvDot(personEvR($r, '{{inspect_date}}'), 18)
        . ' เวลาประมาณ ' . personEvDot(personEvR($r, '{{inspect_time}}'), 8) . ' น.',
        20
    );
    personEvLine(
        $section,
        '3.1 ข้อมูลส่วนบุคคล (ให้ระบุ เช่น หมายเลขบัตรประจำตัวประชาชน/ แบบหนังสือเดินทาง/ ความสูง /คำหนี้รูปพรรณ เช่น แผลเป็น ไฝ สีผิว อายุ มือที่ถนัด เป็นต้น)',
        20,
        ['bold' => true],
        ['spaceBefore' => 40]
    );
    personEvAddDynamicLines($section, $r['{{person_info_rows}}'] ?? '');

    personEvLine($section, '3.2 รายละเอียดวัตถุพยานที่ทำการตรวจเก็บ', 20, ['bold' => true], ['spaceBefore' => 40]);
    personEvAddDynamicLines($section, $r['{{evidence_detail_rows}}'] ?? '');

    // ===== หน้า 2 =====
    $section->addPageBreak();
    personEvAddDynamicLines($section, $r['{{evidence_detail_rows_continued}}'] ?? '');

    personEvLine($section, '3.3 การดำเนินการเกี่ยวกับวัตถุพยาน', 0, ['bold' => true], ['spaceBefore' => 40]);
    personEvLine(
        $section,
        'ได้ให้ ' . personEvDot(personEvR($r, '{{witness_name}}'), 24)
        . ' ลงลายมือชื่อในแบบ ' . personEvDot(personEvR($r, '{{witness_form}}'), 20),
        20
    );
    personEvLine(
        $section,
        personEvDot(personEvR($r, '{{witness_detail}}'), 30)
        . ' ให้เป็นหลักฐาน และได้( '
        . personEvCb($r, '{{chk_handover_submit}}') . ' นำส่ง / '
        . personEvCb($r, '{{chk_handover_transfer}}') . ' ส่งมอบ )',
        20
    );
    personEvLine(
        $section,
        'วัตถุพยานตามข้อ ' . personEvDot(personEvR($r, '{{handover_item_ref}}'), 10)
        . ' ให้ ' . personEvDot(personEvR($r, '{{handover_to}}'), 30),
        20
    );
    personEvLine(
        $section,
        personEvDot(personEvR($r, '{{handover_purpose}}'), 40) . ' เพื่อดำเนินการต่อไป',
        20
    );

    personEvLine($section, 'ผู้ตรวจเก็บวัตถุพยาน', 0, ['bold' => true, 'underline' => 'single'], ['spaceBefore' => 40]);
    personEvAddDynamicLines($section, $r['{{inspector_rows}}'] ?? '');

    $section->addTextBreak(1);
    $sig = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $sig->addRow();
    $sig->addCell(4500);
    $sc = $sig->addCell(5000);
    foreach ([
        '(ลงชื่อ) .......................................................',
        '( ' . personEvDot(personEvR($r, '{{signer_name}}'), 28) . ' )',
        '(ตำแหน่ง) ' . personEvDot(personEvR($r, '{{signer_position}}'), 28),
        personEvDot(personEvR($r, '{{sign_day}}'), 4)
            . ' / ' . personEvDot(personEvR($r, '{{sign_month}}'), 6)
            . ' / ' . personEvDot(personEvR($r, '{{sign_year}}'), 6),
    ] as $sigLine) {
        $sc->addText($sigLine, personEvFs(), personEvPs(0, ['alignment' => Jc::CENTER, 'lineHeight' => 1.8]));
    }

    $tempPhotoPaths = [];
    $photoIndex = 0;
    foreach (array_values($photoDataUris) as $photo) {
        $probe = personEvPreparePhoto($photo);
        if ($probe === null) {
            continue;
        }
        $section->addPageBreak();
        try {
            $section->addImage($probe['path'], ['width' => 450, 'alignment' => Jc::CENTER]);
            $photoIndex++;
            $section->addText(
                'ภาพที่ ' . $photoIndex,
                personEvFs(),
                personEvPs(0, ['alignment' => Jc::CENTER, 'spaceBefore' => 80])
            );
            $tempPhotoPaths[] = $probe['path'];
        } catch (Throwable $e) {
            @unlink($probe['path']);
        }
    }

    return [$phpWord, $tempPhotoPaths];
}

function downloadPersonEvidenceReportDocx(int $incidentId, string $baseName, array $photoDataUris = []): void
{
    $export = personEvLoadExport($incidentId);
    [$phpWord, $tempPhotos] = buildPersonEvidenceReportPhpWord($export['replacements'], $photoDataUris);

    $filenameUtf8 = $baseName . '.docx';
    $filenameAscii = preg_replace('/[^\x20-\x7E]/', '', $baseName);
    $filenameAscii = (trim($filenameAscii) !== '' ? $filenameAscii : ('person_evidence_' . $incidentId)) . '.docx';

    $tmpFile = tempnam(sys_get_temp_dir(), 'person_ev_docx_');
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
