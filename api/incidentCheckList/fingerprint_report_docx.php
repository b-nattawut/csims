<?php
/**
 * fingerprint_report_docx.php — DOCX รายงานตรวจเก็บลายนิ้วมือแฝง (type 06)
 * โครงเดียวกับคดีจราจร: PhpWord API + ระยะแน่น
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/word_docx_dotted_rule.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

const FP_DOCX_FONT = 'TH Sarabun New';
const FP_DOCX_SIZE = 14;

function fpLoadExport(int $incidentId): array
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
    include __DIR__ . '/gen_pdf_fingerprint_report_html.php';
    ob_end_clean();
    ini_set('display_errors', $prev);

    $export = $GLOBALS['fingerprint_report_export'] ?? null;
    if (!is_array($export) || empty($export['replacements'])) {
        throw new RuntimeException('ไม่สามารถโหลดข้อมูลรายงานลายนิ้วมือแฝงได้');
    }
    return $export;
}

function fpFs(array $extra = []): array
{
    return array_merge([
        'name' => FP_DOCX_FONT,
        'size' => FP_DOCX_SIZE,
        'color' => '000000',
    ], $extra);
}

function fpPs(int $indentPt = 0, array $extra = []): array
{
    $style = ['spaceBefore' => 0, 'spaceAfter' => 0, 'lineHeight' => 1.15];
    if ($indentPt > 0) {
        $style['indentation'] = ['left' => $indentPt * 20];
    }
    return array_merge($style, $extra);
}

function fpText(string $htmlOrText): string
{
    $t = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $htmlOrText)), ENT_QUOTES, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', trim($t));
    return $t ?? '';
}

function fpR(array $r, string $key): string
{
    return fpText($r[$key] ?? '');
}

function fpCb(array $r, string $key): string
{
    $m = fpText((string) ($r[$key] ?? ''));
    return ($m !== '' && $m !== '☐') ? '☑' : '☐';
}

function fpDot(string $value, int $dots = 24): string
{
    $v = trim($value);
    return $v !== '' ? $v : str_repeat('.', $dots);
}

function fpLine($section, string $text, int $indentPt = 0, array $fontExtra = [], array $paraExtra = []): void
{
    $t = trim($text);
    $isBlankish = ($t === '' || preg_match('/^\.+$/u', $t) || preg_match('/^\d+(?:\.\d+)*\.\s*$/u', $t));
    if ($isBlankish && wordDocxTryDottedBlankLine(
        $section,
        $text,
        ['name' => FP_DOCX_FONT, 'size' => FP_DOCX_SIZE, 'color' => '000000'],
        function (int $i, array $e = []) { return fpPs($i, $e); },
        $indentPt,
        $fontExtra
    )) {
        return;
    }
    $section->addText($t === '' ? ' ' : $t, fpFs($fontExtra), fpPs($indentPt, $paraExtra));
}

function fpAddDynamicLines($section, string $html): void
{
    if (trim($html) === '') {
        fpLine($section, '', 20);
        return;
    }
    if (!preg_match_all('/<div([^>]*)>((?:(?!<div\b).)*)<\/div>/is', $html, $matches, PREG_SET_ORDER)) {
        $plain = fpText($html);
        fpLine($section, $plain !== '' ? $plain : '', 20);
        return;
    }
    foreach ($matches as $match) {
        $indent = 20;
        if (preg_match('/\bi3\b/', $match[1])) {
            $indent = 60;
        } elseif (preg_match('/\bi2\b/', $match[1])) {
            $indent = 40;
        }
        $text = fpText($match[2]);
        fpLine($section, $text, $indent);
    }
}

function fpPreparePhoto($photo): ?array
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
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fp_photo_' . uniqid('', true) . $ext;
    if (file_put_contents($path, $bytes) === false) {
        return null;
    }
    return ['path' => $path];
}

/**
 * @return array{0: PhpWord, 1: string[]}
 */
function buildFingerprintReportPhpWord(array $replacements, array $photoDataUris = []): array
{
    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName(FP_DOCX_FONT);
    $phpWord->setDefaultFontSize(FP_DOCX_SIZE);
    $phpWord->setDefaultParagraphStyle(fpPs(0));

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
    $reportNo = fpR($r, '{{report_no}}');
    $yearShort = fpR($r, '{{report_year_short}}');
    $agency = fpR($r, '{{agency_name}}');

    $header = $section->addHeader();
    $ht = $header->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $ht->addRow();
    $c1 = $ht->addCell(7500);
    $run = $c1->addTextRun(fpPs(0));
    $run->addText('รายงานการตรวจเก็บวัตถุพยานที่', fpFs(['underline' => 'single']));
    $run->addText('  ' . fpDot($reportNo, 10) . ' /25 ' . fpDot($yearShort, 4), fpFs());
    $c2 = $ht->addCell(2000);
    $c2->addPreserveText('{PAGE}/{NUMPAGES}', fpFs(), ['alignment' => Jc::END]);
    $header->addText('หน่วยงาน  ' . fpDot($agency, 40), fpFs(), fpPs(0, ['spaceAfter' => 60]));

    $footer = $section->addFooter();
    $ft = $footer->addTable([
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT,
        'borderBottomSize' => 6,
        'borderBottomColor' => 'BFBFBF',
    ]);
    $ft->addRow();
    $ft->addCell(3500);
    $fr = $ft->addCell(6500);
    $fr->addText(
        'รายงานการตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)',
        fpFs(['size' => 10, 'color' => '808080']),
        fpPs(0, ['alignment' => Jc::END])
    );

    // ===== หน้า 1 =====
    fpLine($section, 'รายงานการตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)', 0, [
        'bold' => true, 'size' => 15, 'underline' => 'single',
    ], ['alignment' => Jc::CENTER, 'spaceBefore' => 60, 'spaceAfter' => 60]);

    fpLine($section, '1. การรับแจ้ง', 0, ['bold' => true], ['spaceBefore' => 40]);
    fpLine(
        $section,
        'วันที่ ' . fpDot(fpR($r, '{{receive_date}}'), 18)
        . ' เวลา ' . fpDot(fpR($r, '{{receive_time}}'), 8)
        . ' น.  ปจว.ข้อที่ ' . fpDot(fpR($r, '{{case_no}}'), 16),
        20
    );
    fpLine(
        $section,
        'การรับแจ้งทางหนังสือ สน./สภ. ' . fpDot(fpR($r, '{{police_station}}'), 30),
        20
    );
    fpLine(
        $section,
        'ที่ ' . fpDot(fpR($r, '{{letter_no}}'), 24)
        . ' ลง ' . fpDot(fpR($r, '{{letter_date}}'), 18),
        20
    );
    fpLine($section, 'ผู้ส่งของกลาง ' . fpDot(fpR($r, '{{evidence_sender}}'), 40), 20);
    fpLine(
        $section,
        'ตำแหน่ง ' . fpDot(fpR($r, '{{evidence_sender_position}}'), 24)
        . ' โทร ' . fpDot(fpR($r, '{{evidence_sender_phone}}'), 16),
        20
    );
    fpLine(
        $section,
        'รับวัตถุพยานตามหนังสือ ' . fpDot(fpR($r, '{{evidence_letter_no}}'), 30),
        20
    );
    fpLine(
        $section,
        'ที่ ' . fpDot(fpR($r, '{{evidence_doc_no}}'), 24)
        . ' ลง ' . fpDot(fpR($r, '{{evidence_doc_date}}'), 18),
        20
    );
    fpLine(
        $section,
        'จุดประสงค์ในการตรวจพิสูจน์  '
        . fpCb($r, '{{chk_purpose_fingerprint}}') . ' เพื่อตรวจเก็บรอยลายนิ้วมือแฝง  '
        . fpCb($r, '{{chk_purpose_other}}') . ' อื่นๆ '
        . fpDot(fpR($r, '{{purpose_other_text}}'), 16),
        20
    );

    fpLine($section, '2. วันเวลาที่เกิดเหตุ/ทราบเหตุ', 0, ['bold' => true], ['spaceBefore' => 40]);
    fpLine(
        $section,
        'วันเวลาที่เกิดเหตุ วันที่ ' . fpDot(fpR($r, '{{incident_date}}'), 18)
        . ' เวลาประมาณ ' . fpDot(fpR($r, '{{incident_time}}'), 8) . ' น.',
        20
    );
    fpLine(
        $section,
        'วันเวลาที่ทราบเหตุ วันที่ ' . fpDot(fpR($r, '{{known_date}}'), 18)
        . ' เวลาประมาณ ' . fpDot(fpR($r, '{{known_time}}'), 8) . ' น.',
        20
    );

    fpLine($section, '3. วันเวลาที่ตรวจเก็บ', 0, ['bold' => true], ['spaceBefore' => 40]);
    fpLine(
        $section,
        'วันที่ ' . fpDot(fpR($r, '{{collect_date}}'), 18)
        . ' เวลาประมาณ ' . fpDot(fpR($r, '{{collect_time}}'), 8) . ' น.',
        20
    );

    fpLine($section, '4. ผู้ตรวจพิสูจน์', 0, ['bold' => true], ['spaceBefore' => 40]);
    fpAddDynamicLines($section, $r['{{inspector_rows}}'] ?? '');

    fpLine($section, '5. ลักษณะการหีบห่อวัตถุพยาน', 0, ['bold' => true], ['spaceBefore' => 40]);
    fpLine(
        $section,
        'ลักษณะการบรรจุ  '
        . fpCb($r, '{{chk_pkg_envelope}}') . ' บรรจุในซองวัตถุพยาน  '
        . fpCb($r, '{{chk_pkg_plastic}}') . ' พลาสติก',
        20
    );
    fpLine(
        $section,
        'สภาพการปิดผนึก  '
        . fpCb($r, '{{chk_seal_signed}}') . ' การปิดผนึกพร้อมลงลายมือชื่อกำกับ',
        20
    );
    fpLine(
        $section,
        fpCb($r, '{{chk_seal_detail}}') . ' เขียนรายละเอียดหน้าซองวัตถุพยาน',
        40
    );
    fpLine(
        $section,
        'ผู้เก็บรักษา  '
        . fpCb($r, '{{chk_collector_forensic}}') . ' เจ้าหน้าที่พิสูจน์หลักฐาน  '
        . fpCb($r, '{{chk_collector_investigator}}') . ' พนักงานสอบสวน  '
        . fpCb($r, '{{chk_collector_other}}') . ' อื่นๆ '
        . fpDot(fpR($r, '{{collector_other_text}}'), 12),
        20
    );
    fpLine(
        $section,
        'เก็บเมื่อ ' . fpDot(fpR($r, '{{storage_date}}'), 16)
        . ' เวลาประมาณ ' . fpDot(fpR($r, '{{storage_time}}'), 8)
        . ' น.  ระยะเวลา '
        . fpDot(fpR($r, '{{duration_year}}'), 2) . ' ปี '
        . fpDot(fpR($r, '{{duration_month}}'), 2) . ' เดือน '
        . fpDot(fpR($r, '{{duration_day}}'), 2) . ' วัน',
        20
    );

    // ===== หน้า 2 =====
    $section->addPageBreak();
    fpLine($section, '6. ลักษณะวัตถุพยาน', 0, ['bold' => true]);
    fpLine(
        $section,
        'จำนวนวัตถุพยานทั้งสิ้น ' . fpDot(fpR($r, '{{evidence_total}}'), 6) . ' รายการ',
        20
    );
    fpAddDynamicLines($section, $r['{{evidence_rows}}'] ?? '');

    fpLine($section, '7. วิธีการดำเนินการ', 0, ['bold' => true], ['spaceBefore' => 40]);
    fpAddDynamicLines($section, $r['{{method_rows}}'] ?? '');

    fpLine($section, '8. การดำเนินการ', 0, ['bold' => true], ['spaceBefore' => 40]);
    fpLine($section, 'วัตถุพยานแผ่นเก็บรอยลายนิ้วมือแฝง/ภาพถ่ายรอยลายนิ้วมือแฝง', 20, ['bold' => true]);
    fpLine(
        $section,
        fpCb($r, '{{chk_action_gnf}}') . ' กนฝ.  '
        . fpCb($r, '{{chk_action_evidence_other}}') . ' อื่นๆ '
        . fpDot(fpR($r, '{{action_evidence_other_text}}'), 20),
        20
    );
    fpLine(
        $section,
        'ของกลาง  '
        . fpCb($r, '{{chk_action_return_investigator}}') . ' ส่งคืนพนักงานสอบสวน  สภ. '
        . fpDot(fpR($r, '{{action_return_station}}'), 20),
        20
    );
    fpLine(
        $section,
        'ส่งต่อกลุ่มงาน  '
        . fpCb($r, '{{chk_fwd_gchw}}') . ' กชว.  '
        . fpCb($r, '{{chk_fwd_gop}}') . ' กอป.  '
        . fpCb($r, '{{chk_fwd_gos}}') . ' กอส.  '
        . fpCb($r, '{{chk_fwd_gkm}}') . ' กคม.  '
        . fpCb($r, '{{chk_fwd_gkp}}') . ' กคพ.  '
        . fpCb($r, '{{chk_fwd_other}}') . ' อื่นๆ '
        . fpDot(fpR($r, '{{action_forward_other_text}}'), 12),
        20
    );

    $section->addTextBreak(1);
    $sig = $section->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $sig->addRow();
    $sig->addCell(4500);
    $sc = $sig->addCell(5000);
    foreach ([
        'ลงชื่อ ........................................ ผู้รายงาน',
        '( ' . fpDot(fpR($r, '{{signer_name}}'), 28) . ' )',
        'ตำแหน่ง ' . fpDot(fpR($r, '{{signer_position}}'), 28),
        fpDot(fpR($r, '{{sign_day}}'), 4)
            . ' / ' . fpDot(fpR($r, '{{sign_month}}'), 10)
            . ' / ' . fpDot(fpR($r, '{{sign_year}}'), 6),
    ] as $sigLine) {
        $sc->addText($sigLine, fpFs(), fpPs(0, ['alignment' => Jc::CENTER, 'lineHeight' => 1.8]));
    }

    $tempPhotoPaths = [];
    $photoIndex = 0;
    foreach (array_values($photoDataUris) as $photo) {
        $probe = fpPreparePhoto($photo);
        if ($probe === null) {
            continue;
        }
        $section->addPageBreak();
        try {
            $section->addImage($probe['path'], ['width' => 450, 'alignment' => Jc::CENTER]);
            $photoIndex++;
            $section->addText(
                'ภาพที่ ' . $photoIndex,
                fpFs(),
                fpPs(0, ['alignment' => Jc::CENTER, 'spaceBefore' => 80])
            );
            $tempPhotoPaths[] = $probe['path'];
        } catch (Throwable $e) {
            @unlink($probe['path']);
        }
    }

    return [$phpWord, $tempPhotoPaths];
}

function downloadFingerprintReportDocx(int $incidentId, string $baseName, array $photoDataUris = []): void
{
    $export = fpLoadExport($incidentId);
    [$phpWord, $tempPhotos] = buildFingerprintReportPhpWord($export['replacements'], $photoDataUris);

    $filenameUtf8 = $baseName . '.docx';
    $filenameAscii = preg_replace('/[^\x20-\x7E]/', '', $baseName);
    $filenameAscii = (trim($filenameAscii) !== '' ? $filenameAscii : ('fingerprint_' . $incidentId)) . '.docx';

    $tmpFile = tempnam(sys_get_temp_dir(), 'fp_docx_');
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
