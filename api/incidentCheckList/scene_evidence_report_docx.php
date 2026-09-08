<?php
/**
 * scene_evidence_report_docx.php — DOCX รายงานตรวจเก็บวัตถุพยานที่เกิดเหตุ (type 07)
 * โครงเดียวกับลายนิ้วมือแฝง: PhpWord API + ระยะแน่น
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/word_docx_dotted_rule.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

const SCENE_EV_DOCX_FONT = 'TH Sarabun New';
const SCENE_EV_DOCX_SIZE = 14;

function sceneEvLoadExport(int $incidentId): array
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
    include __DIR__ . '/gen_pdf_scene_evidence_report_html.php';
    ob_end_clean();
    ini_set('display_errors', $prev);

    $export = $GLOBALS['scene_evidence_report_export'] ?? null;
    if (!is_array($export) || empty($export['replacements'])) {
        throw new RuntimeException('ไม่สามารถโหลดข้อมูลรายงานวัตถุพยานที่เกิดเหตุได้');
    }
    return $export;
}

function sceneEvFs(array $extra = []): array
{
    return array_merge([
        'name' => SCENE_EV_DOCX_FONT,
        'size' => SCENE_EV_DOCX_SIZE,
        'color' => '000000',
    ], $extra);
}

function sceneEvPs(int $indentPt = 0, array $extra = []): array
{
    $style = ['spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 1.15];
    if ($indentPt > 0) {
        $style['indentation'] = ['left' => $indentPt * 20];
    }
    return array_merge($style, $extra);
}

function sceneEvText(string $htmlOrText): string
{
    $t = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $htmlOrText)), ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', trim($t));
    return $t ?? '';
}

function sceneEvR(array $r, string $key): string
{
    return sceneEvText($r[$key] ?? '');
}

function sceneEvCb(array $r, string $key): string
{
    $m = sceneEvText((string) ($r[$key] ?? ''));
    return ($m !== '' && $m !== '☐') ? '☑' : '☐';
}

function sceneEvDot(string $value, int $dots = 24): string
{
    $v = trim($value);
    return $v !== '' ? $v : str_repeat('.', $dots);
}

function sceneEvLine($section, string $text, int $indentPt = 0, array $fontExtra = [], array $paraExtra = []): void
{
    $t = trim($text);
    $isBlankish = ($t === '' || preg_match('/^\.+$/u', $t) || preg_match('/^\d+(?:\.\d+)*\.\s*$/u', $t));
    if ($isBlankish && wordDocxTryDottedBlankLine(
        $section,
        $text,
        ['name' => SCENE_EV_DOCX_FONT, 'size' => SCENE_EV_DOCX_SIZE, 'color' => '000000'],
        function (int $i, array $e = []) { return sceneEvPs($i, $e); },
        $indentPt,
        $fontExtra
    )) {
        return;
    }
    $section->addText($t === '' ? ' ' : $t, sceneEvFs($fontExtra), sceneEvPs($indentPt, $paraExtra));
}

function sceneEvAddDynamicLines($section, string $html): void
{
    if (trim($html) === '') {
        sceneEvLine($section, '', 20);
        return;
    }
    if (!preg_match_all('/<div([^>]*)>((?:(?!<div\b).)*)<\/div>/is', $html, $matches, PREG_SET_ORDER)) {
        $plain = sceneEvText($html);
        sceneEvLine($section, $plain !== '' ? $plain : '', 20);
        return;
    }
    foreach ($matches as $match) {
        $indent = 20;
        if (preg_match('/\bi3\b/', $match[1])) {
            $indent = 60;
        } elseif (preg_match('/\bi2\b/', $match[1])) {
            $indent = 40;
        }
        $text = sceneEvText($match[2]);
        sceneEvLine($section, $text, $indent);
    }
}

function sceneEvPreparePhoto($photo): ?array
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
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'scene_ev_photo_' . uniqid('', true) . $ext;
    if (file_put_contents($path, $bytes) === false) {
        return null;
    }
    return ['path' => $path];
}

/**
 * @return array{0: PhpWord, 1: string[]}
 */
function buildSceneEvidenceReportPhpWord(array $replacements, array $photoDataUris = []): array
{
    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName(SCENE_EV_DOCX_FONT);
    $phpWord->setDefaultFontSize(SCENE_EV_DOCX_SIZE);
    $phpWord->setDefaultParagraphStyle(sceneEvPs(0));

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
    $reportNo = sceneEvR($r, '{{report_no}}');
    $yearShort = sceneEvR($r, '{{report_year_short}}');
    $agency = sceneEvR($r, '{{agency_name}}');

    $header = $section->addHeader();
    $ht = $header->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $ht->addRow();
    $c1 = $ht->addCell(7500);
    $run = $c1->addTextRun(sceneEvPs(0));
    $run->addText('รายงานการตรวจเก็บวัตถุพยานที่', sceneEvFs(['underline' => 'single']));
    $run->addText('  ' . sceneEvDot($reportNo, 10) . ' /25 ' . sceneEvDot($yearShort, 4), sceneEvFs());
    $c2 = $ht->addCell(2000);
    $c2->addPreserveText('{PAGE}/{NUMPAGES}', sceneEvFs(), ['alignment' => Jc::END]);
    $header->addText('หน่วยงาน  ' . sceneEvDot($agency, 40), sceneEvFs(), sceneEvPs(0, ['spaceAfter' => 60]));

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
    foreach (['บัญชีแนบท้ายคำสั่ง สพฐ.ตร.', 'ที่ ๘๔๘/๒๕๖๑'] as $fline) {
        $fr->addText($fline, sceneEvFs(['size' => 10, 'color' => '808080']), sceneEvPs(0, ['alignment' => Jc::END]));
    }

    // ===== หน้า 1 =====
    sceneEvLine($section, 'รายงานการตรวจเก็บวัตถุพยานที่เกิดเหตุ', 0, [
        'bold' => true, 'size' => 15, 'underline' => 'single',
    ], ['alignment' => Jc::CENTER, 'spaceBefore' => 60, 'spaceAfter' => 60]);

    sceneEvLine($section, '1. สิ่งที่ได้รับจาก /การรับแจ้งเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    sceneEvLine(
        $section,
        'เมื่อวันที่ ' . sceneEvDot(sceneEvR($r, '{{receive_date}}'), 18)
        . ' เวลา ' . sceneEvDot(sceneEvR($r, '{{receive_time}}'), 8)
        . ' น. กลุ่มงานตรวจที่เกิดเหตุ กองพิสูจน์หลักฐานกลาง/',
        20
    );
    sceneEvLine(
        $section,
        'ศูนย์พิสูจน์หลักฐาน ' . sceneEvDot(sceneEvR($r, '{{center_name}}'), 16)
        . ' /พิสูจน์หลักฐานจังหวัด ' . sceneEvDot(sceneEvR($r, '{{province_name}}'), 16),
        20
    );
    sceneEvLine(
        $section,
        // ★ ข้อ 7: ออกเป็นข้อความไทย แทนช่องติ๊ก
        'ได้รับแจ้ง ' . sceneEvDot(sceneEvR($r, '{{notify_method_text}}'), 30)
        . ' จากสถานีตำรวจนครบาล/สถานีตำรวจภูธร',
        20
    );
    sceneEvLine($section, 'จาก สน./สภ. ' . sceneEvDot(sceneEvR($r, '{{police_station}}'), 40), 20);
    sceneEvLine(
        $section,
        'ที่ ' . sceneEvDot(sceneEvR($r, '{{document_no}}'), 20)
        . ' ลงวันที่ ' . sceneEvDot(sceneEvR($r, '{{document_date}}'), 18),
        20
    );
    sceneEvLine($section, 'สถานที่เกิดเหตุ ' . sceneEvDot(sceneEvR($r, '{{incident_location}}'), 40), 20);
    sceneEvLine(
        $section,
        'เหตุเกิดเมื่อวันที่ ' . sceneEvDot(sceneEvR($r, '{{incident_date}}'), 18)
        . ' เวลาประมาณ ' . sceneEvDot(sceneEvR($r, '{{incident_time}}'), 8) . ' น.',
        20
    );
    sceneEvLine(
        $section,
        'มี ' . sceneEvDot(sceneEvR($r, '{{investigator_name}}'), 30) . ' เป็นพนักงานสอบสวน',
        20
    );
    sceneEvLine($section, 'มีรายการของกลางดังนี้', 20);
    sceneEvAddDynamicLines($section, $r['{{evidence_item_rows}}'] ?? '');

    sceneEvLine($section, '2. จุดประสงค์ในการตรวจ', 0, ['bold' => true], ['spaceBefore' => 40]);
    sceneEvLine(
        $section,
        'เพื่อทำการตรวจเก็บ ( '
        . sceneEvCb($r, '{{chk_purpose_fingerprint}}') . ' รอยลายนิ้วมือแฝง / '
        . sceneEvCb($r, '{{chk_purpose_dna}}') . ' สารพันธุกรรม / '
        . sceneEvCb($r, '{{chk_purpose_other}}') . ' วัตถุพยานอื่นๆ ) ที่',
        20
    );
    sceneEvLine(
        $section,
        'ของกลางดังกล่าว ' . sceneEvDot(sceneEvR($r, '{{purpose_detail}}'), 40),
        20
    );

    sceneEvLine($section, '3. ผลการตรวจ', 0, ['bold' => true], ['spaceBefore' => 40]);
    sceneEvLine(
        $section,
        'ได้ทำการตรวจของกลางที่ ' . sceneEvDot(sceneEvR($r, '{{inspect_location}}'), 20)
        . ' เมื่อวันที่ ' . sceneEvDot(sceneEvR($r, '{{inspect_date}}'), 18),
        20
    );
    sceneEvLine(
        $section,
        'เวลาประมาณ ' . sceneEvDot(sceneEvR($r, '{{inspect_time}}'), 8) . ' น.',
        20
    );
    sceneEvLine(
        $section,
        '3.1 ลักษณะของกลาง (ให้ระบุสภาพทั่วไป เช่น รูปร่าง สี ขนาด ยี่ห้อ เป็นต้นและจำนวน)',
        20,
        ['bold' => true],
        ['spaceBefore' => 40]
    );
    sceneEvAddDynamicLines($section, $r['{{exhibit_desc_rows}}'] ?? '');

    // ===== หน้า 2 =====
    $section->addPageBreak();
    sceneEvLine($section, '3.2 วัตถุพยานที่ตรวจเก็บ', 0, ['bold' => true]);
    sceneEvLine(
        $section,
        '3.2.1 ตรวจเก็บ ( '
        . sceneEvCb($r, '{{chk_collect_fingerprint}}') . ' รอยลายนิ้วมือแฝง  '
        . sceneEvCb($r, '{{chk_collect_palm}}') . ' ฝ่ามือแฝง  '
        . sceneEvCb($r, '{{chk_collect_foot}}') . ' ฝ่าเท้าแฝง ) จำนวน '
        . sceneEvDot(sceneEvR($r, '{{collect_sheet_count}}'), 4) . ' แผ่น ที่',
        20,
        ['bold' => true],
        ['spaceBefore' => 40]
    );
    sceneEvAddDynamicLines($section, $r['{{collect_detail_rows}}'] ?? '');

    sceneEvLine($section, '3.2.2 (ระบุวัตถุพยานประเภทอื่น)', 20, ['bold' => true], ['spaceBefore' => 40]);
    sceneEvLine($section, sceneEvDot(sceneEvR($r, '{{other_evidence_text}}'), 60), 40);

    sceneEvLine($section, '3.3 การดำเนินการเกี่ยวกับวัตถุพยาน', 0, ['bold' => true], ['spaceBefore' => 40]);
    sceneEvLine(
        $section,
        'ได้ให้ ' . sceneEvDot(sceneEvR($r, '{{witness_name}}'), 24)
        . ' ลงลายมือชื่อ/พิมพ์ลายนิ้วมือในแบบ '
        . sceneEvDot(sceneEvR($r, '{{witness_form}}'), 20),
        20
    );
    sceneEvLine($section, sceneEvDot(sceneEvR($r, '{{witness_detail}}'), 60), 20);
    sceneEvLine(
        $section,
        'ให้เป็นหลักฐาน และได้ ( '
        . sceneEvCb($r, '{{chk_handover_submit}}') . ' นำส่ง / '
        . sceneEvCb($r, '{{chk_handover_transfer}}') . ' ส่งมอบ )',
        20
    );
    sceneEvLine(
        $section,
        'วัตถุพยานตามข้อ ' . sceneEvDot(sceneEvR($r, '{{handover_item_ref}}'), 10)
        . ' ให้ ' . sceneEvDot(sceneEvR($r, '{{handover_to}}'), 30),
        20
    );
    sceneEvLine(
        $section,
        sceneEvDot(sceneEvR($r, '{{handover_purpose}}'), 40) . ' เพื่อดำเนินการต่อไป',
        20
    );

    $section->addTextBreak(1);
    $sig = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $sig->addRow();
    $sig->addCell(4500);
    $sc = $sig->addCell(5000);
    foreach ([
        'ลงชื่อ ........................................ ผู้รายงาน',
        '(' . sceneEvDot(sceneEvR($r, '{{signer_name}}'), 28) . ')',
        'ตำแหน่ง ' . sceneEvDot(sceneEvR($r, '{{signer_position}}'), 28),
    ] as $sigLine) {
        $sc->addText($sigLine, sceneEvFs(), sceneEvPs(0, ['alignment' => Jc::CENTER, 'lineHeight' => 1.8]));
    }

    $tempPhotoPaths = [];
    $photoIndex = 0;
    foreach (array_values($photoDataUris) as $photo) {
        $probe = sceneEvPreparePhoto($photo);
        if ($probe === null) {
            continue;
        }
        $section->addPageBreak();
        try {
            $section->addImage($probe['path'], ['width' => 450, 'alignment' => Jc::CENTER]);
            $photoIndex++;
            $section->addText(
                'ภาพที่ ' . $photoIndex,
                sceneEvFs(),
                sceneEvPs(0, ['alignment' => Jc::CENTER, 'spaceBefore' => 80])
            );
            $tempPhotoPaths[] = $probe['path'];
        } catch (Throwable $e) {
            @unlink($probe['path']);
        }
    }

    return [$phpWord, $tempPhotoPaths];
}

function downloadSceneEvidenceReportDocx(int $incidentId, string $baseName, array $photoDataUris = []): void
{
    $export = sceneEvLoadExport($incidentId);
    [$phpWord, $tempPhotos] = buildSceneEvidenceReportPhpWord($export['replacements'], $photoDataUris);

    $filenameUtf8 = $baseName . '.docx';
    $filenameAscii = preg_replace('/[^\x20-\x7E]/', '', $baseName);
    $filenameAscii = (trim($filenameAscii) !== '' ? $filenameAscii : ('scene_evidence_' . $incidentId)) . '.docx';

    $tmpFile = tempnam(sys_get_temp_dir(), 'scene_ev_docx_');
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
