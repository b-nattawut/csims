<?php
/**
 * generic_report_docx.php — สร้าง DOCX จาก HTML preview ของรายงานทุกประเภท
 * ใช้ PhpWord API + ระยะบรรทัดแน่น (ไม่ใช้ Html::addHtml)
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/word_report_helper.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

const REPORT_DOCX_FONT = 'TH Sarabun New';
const REPORT_DOCX_SIZE = 14;

function reportDocxFs(array $extra = []): array
{
    return array_merge([
        'name' => REPORT_DOCX_FONT,
        'size' => REPORT_DOCX_SIZE,
        'color' => '000000',
    ], $extra);
}

function reportDocxPs(int $indentPt = 0, array $extra = []): array
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

function reportDocxText(string $htmlOrText): string
{
    $t = html_entity_decode(strip_tags($htmlOrText), ENT_QUOTES, 'UTF-8');
    $t = str_replace("\xc2\xa0", ' ', $t);
    $t = preg_replace('/\s+/u', ' ', trim($t));
    return $t ?? '';
}

function reportDocxLine($section, string $text, int $indentPt = 0, array $fontExtra = [], array $paraExtra = []): void
{
    if ($text === '' || preg_match('/^\d+\.\s*$/u', $text)) {
        return;
    }
    $section->addText($text, reportDocxFs($fontExtra), reportDocxPs($indentPt, $paraExtra));
}

/**
 * โหลด HTML จาก generator ของแต่ละประเภท
 */
function reportCaptureGeneratorHtml(string $generatorFile, int $incidentId): string
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

    if (!defined('WORD_REPORT_CAPTURE')) {
        define('WORD_REPORT_CAPTURE', true);
    }

    $_GET['incident_id'] = $incidentId;
    $path = __DIR__ . '/' . $generatorFile;
    if (!is_readable($path)) {
        throw new RuntimeException('ไม่พบไฟล์รายงาน: ' . $generatorFile);
    }

    $prev = ini_get('display_errors');
    ini_set('display_errors', '0');
    ob_start();
    include $path;
    $html = ob_get_clean();
    ini_set('display_errors', $prev);

    if (trim($html) === '') {
        throw new RuntimeException('ไม่สามารถสร้างเนื้อหารายงานได้');
    }
    return $html;
}

/**
 * แยก .page จาก HTML ต้นฉบับ
 *
 * @return string[]
 */
function reportSplitHtmlPages(string $html): array
{
    $html = preg_replace('/<div class="no-print">.*?<\/div>/su', '', $html);
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);

    if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
        $body = $m[1];
    } else {
        $body = $html;
    }

    $pages = preg_split('/(?=<div[^>]*class="[^"]*\bpage\b[^"]*"[^>]*>)/i', $body);
    $pages = array_values(array_filter(array_map('trim', $pages), static function ($p) {
        return reportDocxText($p) !== '';
    }));

    if (empty($pages)) {
        $pages = preg_split('/<!--\s*=+\s*PAGE\s+\d+\s*=+\s*-->/i', $body);
        $pages = array_values(array_filter(array_map('trim', $pages), static function ($p) {
            return reportDocxText($p) !== '';
        }));
    }

    return !empty($pages) ? $pages : [trim($body)];
}

/**
 * ดึง header/footer ข้อความจากหน้าแรก
 *
 * @return array{header_left: string, agency: string, footer_left: string[], footer_right: string[]}
 */
function reportExtractChrome(string $pageHtml): array
{
    $chrome = [
        'header_left' => '',
        'agency' => '',
        'footer_left' => [],
        'footer_right' => [],
    ];

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    @$dom->loadHTML('<?xml encoding="UTF-8"><div id="root">' . $pageHtml . '</div>');
    $xpath = new DOMXPath($dom);

    $headerLeft = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " page-header-left ")]');
    if ($headerLeft && $headerLeft->length) {
        $chrome['header_left'] = reportDocxText($dom->saveHTML($headerLeft->item(0)));
    }

    // หน่วยงาน — แถว .fr แรกที่มีคำว่า หน่วยงาน
    foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " fr ")]') as $fr) {
        $t = reportDocxText($dom->saveHTML($fr));
        if (strpos($t, 'หน่วยงาน') === 0 || strpos($t, 'หน่วยงาน') !== false && strlen($t) < 120) {
            $chrome['agency'] = preg_replace('/^หน่วยงาน\s*/u', '', $t);
            break;
        }
    }

    $footerLeft = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " form-footer-left ") or contains(concat(" ", normalize-space(@class), " "), " form-footer-left-bottom ")]');
    if ($footerLeft) {
        foreach ($footerLeft as $node) {
            foreach (preg_split('/\n|<br\s*\/?>/i', $dom->saveHTML($node)) as $line) {
                $line = reportDocxText($line);
                if ($line !== '') {
                    $chrome['footer_left'][] = $line;
                }
            }
        }
    }

    $footerRight = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " form-footer-right ") or contains(concat(" ", normalize-space(@class), " "), " form-footer-right-bottom ")]');
    if ($footerRight) {
        foreach ($footerRight as $node) {
            $html = $dom->saveHTML($node);
            $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
            foreach (explode("\n", strip_tags($html)) as $line) {
                $line = reportDocxText($line);
                if ($line !== '') {
                    $chrome['footer_right'][] = $line;
                }
            }
        }
    }

    libxml_clear_errors();
    return $chrome;
}

/**
 * แปลงเนื้อหา 1 หน้า → รายการบรรทัด
 *
 * @return array<int, array{text: string, indent: int, bold: bool, center: bool, space_before: int}>
 */
function reportPageToLines(string $pageHtml): array
{
    $lines = [];

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    @$dom->loadHTML('<?xml encoding="UTF-8"><div id="root">' . $pageHtml . '</div>');
    $root = $dom->getElementById('root');
    if (!$root) {
        libxml_clear_errors();
        return $lines;
    }

    $walk = function (DOMNode $node, int $baseIndent = 0) use (&$walk, &$lines, $dom): void {
        if (!($node instanceof DOMElement)) {
            return;
        }

        $class = $node->hasAttribute('class') ? $node->getAttribute('class') : '';
        $classes = preg_split('/\s+/', trim($class)) ?: [];

        // ข้าม header/footer ของหน้า (ใส่ผ่าน PhpWord แล้ว)
        if (in_array('page-header', $classes, true)
            || in_array('form-footer', $classes, true)
            || in_array('no-print', $classes, true)) {
            return;
        }

        $indent = $baseIndent;
        if (in_array('i3', $classes, true)) {
            $indent = 60;
        } elseif (in_array('i2', $classes, true)) {
            $indent = 40;
        } elseif (in_array('i1', $classes, true)) {
            $indent = 20;
        }

        $isLeafBlock = false;
        $bold = false;
        $center = false;
        $spaceBefore = 0;

        if (in_array('form-title', $classes, true)) {
            $isLeafBlock = true;
            $bold = true;
            $center = true;
            $spaceBefore = 40;
            $indent = 0;
        } elseif (in_array('sec-heading', $classes, true)) {
            $isLeafBlock = true;
            $bold = true;
            $spaceBefore = 40;
            $indent = 0;
        } elseif (in_array('sub-heading', $classes, true)) {
            $isLeafBlock = true;
            $bold = true;
            $spaceBefore = 20;
            if ($indent < 20) {
                $indent = 20;
            }
        } elseif (in_array('fl-b', $classes, true)) {
            $isLeafBlock = true;
            $bold = true;
            if ($indent < 20) {
                $indent = 20;
            }
        } elseif (in_array('fr', $classes, true) || in_array('fr-flow', $classes, true)
            || in_array('wall-row', $classes, true) || in_array('text-block', $classes, true)
            || in_array('blank-line', $classes, true)) {
            $isLeafBlock = true;
        } elseif (in_array('signature-block', $classes, true)) {
            // flatten children as centered lines
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $t = reportDocxText($dom->saveHTML($child));
                    // checkbox ✓ → ☑
                    $t = str_replace('✓', '☑', $t);
                    if ($t !== '' && !preg_match('/^\d+\.\s*$/u', $t)) {
                        $lines[] = [
                            'text' => $t,
                            'indent' => 0,
                            'bold' => false,
                            'center' => true,
                            'space_before' => 40,
                        ];
                    }
                }
            }
            return;
        } elseif (strtolower($node->tagName) === 'br') {
            return;
        }

        if ($isLeafBlock) {
            $html = $dom->saveHTML($node);
            // checkbox empty box + check
            $html = preg_replace('/<span[^>]*class="[^"]*\bcb\b[^"]*"[^>]*>\s*✓\s*<\/span>/u', '☑', $html);
            $html = preg_replace('/<span[^>]*class="[^"]*\bcb\b[^"]*"[^>]*>\s*<\/span>/u', '☐', $html);
            $html = str_replace('✓', '☑', $html);
            $text = reportDocxText($html);
            if ($text !== '' && !preg_match('/^\d+\.\s*$/u', $text)) {
                // ข้ามแถว "หน่วยงาน xxx" เพราะอยู่ใน header แล้ว
                if (preg_match('/^หน่วยงาน\b/u', $text)) {
                    return;
                }
                $lines[] = [
                    'text' => $text,
                    'indent' => $indent,
                    'bold' => $bold || in_array('fl-b', $classes, true),
                    'center' => $center,
                    'space_before' => $spaceBefore,
                ];
            }
            return;
        }

        // container — walk children
        foreach ($node->childNodes as $child) {
            $walk($child, $indent);
        }
    };

    foreach ($root->childNodes as $child) {
        $walk($child, 0);
    }

    libxml_clear_errors();
    return $lines;
}

function reportPreparePhotoFile($photo): ?array
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

    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'report_photo_' . uniqid('', true) . $ext;
    if (file_put_contents($path, $bytes) === false) {
        return null;
    }
    return ['path' => $path];
}

/**
 * @return array{0: PhpWord, 1: string[]}
 */
function buildGenericReportPhpWord(string $html, array $photoDataUris = []): array
{
    $pages = reportSplitHtmlPages($html);
    if (empty($pages)) {
        throw new RuntimeException('ไม่พบหน้าในรายงาน');
    }

    $chrome = reportExtractChrome($pages[0]);

    $phpWord = new PhpWord();
    $phpWord->setDefaultFontName(REPORT_DOCX_FONT);
    $phpWord->setDefaultFontSize(REPORT_DOCX_SIZE);
    $phpWord->setDefaultParagraphStyle(reportDocxPs(0));

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

    // Header
    $header = $section->addHeader();
    $ht = $header->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT]);
    $ht->addRow();
    $c1 = $ht->addCell(7500);
    $headerLeft = $chrome['header_left'] !== ''
        ? $chrome['header_left']
        : 'รายงานการตรวจสถานที่เกิดเหตุที่';
    // แยกส่วนที่มีขีดเส้นใต้ชื่อฟอร์มถ้าเป็นไปได้
    if (preg_match('/^(รายงานการตรวจสถานที่เกิดเหตุที่)\s*(.*)$/u', $headerLeft, $hm)) {
        $run = $c1->addTextRun(reportDocxPs(0));
        $run->addText($hm[1], reportDocxFs(['underline' => 'single']));
        $run->addText('  ' . $hm[2], reportDocxFs());
    } else {
        $c1->addText($headerLeft, reportDocxFs(), reportDocxPs(0));
    }
    $c2 = $ht->addCell(2000);
    $c2->addPreserveText('{PAGE}/{NUMPAGES}', reportDocxFs(), ['alignment' => Jc::END]);

    if ($chrome['agency'] !== '') {
        $header->addText('หน่วยงาน  ' . $chrome['agency'], reportDocxFs(), reportDocxPs(0, ['spaceAfter' => 60]));
    }

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
    $fr = $ft->addCell(5000);
    $leftLines = !empty($chrome['footer_left']) ? $chrome['footer_left'] : [];
    $rightLines = !empty($chrome['footer_right']) ? $chrome['footer_right'] : [];
    if (empty($leftLines) && empty($rightLines)) {
        $rightLines = ['รายงานการตรวจสถานที่เกิดเหตุ'];
    }
    foreach ($leftLines as $line) {
        $fl->addText($line, reportDocxFs(['size' => 10, 'color' => '808080']), reportDocxPs(0));
    }
    foreach ($rightLines as $line) {
        $fr->addText($line, reportDocxFs(['size' => 10, 'color' => '808080']), reportDocxPs(0, ['alignment' => Jc::END]));
    }

    // Body pages
    foreach ($pages as $i => $pageHtml) {
        if ($i > 0) {
            $section->addPageBreak();
        }
        $pageLines = reportPageToLines($pageHtml);
        foreach ($pageLines as $line) {
            $para = [];
            if (!empty($line['center'])) {
                $para['alignment'] = Jc::CENTER;
            }
            if (!empty($line['space_before'])) {
                $para['spaceBefore'] = (int) $line['space_before'];
            }
            $font = !empty($line['bold']) ? ['bold' => true] : [];
            if (!empty($line['center']) && !empty($line['bold'])) {
                $font['underline'] = 'single';
                $font['size'] = 15;
            }
            reportDocxLine(
                $section,
                $line['text'],
                (int) ($line['indent'] ?? 0),
                $font,
                $para
            );
        }
    }

    // Photos
    $tempPhotoPaths = [];
    $photoIndex = 0;
    foreach (array_values($photoDataUris) as $photo) {
        $probe = reportPreparePhotoFile($photo);
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
                reportDocxFs(),
                reportDocxPs(0, ['alignment' => Jc::CENTER, 'spaceBefore' => 80])
            );
            $tempPhotoPaths[] = $probe['path'];
        } catch (Throwable $e) {
            @unlink($probe['path']);
        }
    }

    return [$phpWord, $tempPhotoPaths];
}

function downloadGenericReportDocx(string $generatorFile, int $incidentId, string $baseName, array $photoDataUris = []): void
{
    $html = reportCaptureGeneratorHtml($generatorFile, $incidentId);
    [$phpWord, $tempPhotos] = buildGenericReportPhpWord($html, $photoDataUris);

    $filenameUtf8 = $baseName . '.docx';
    $filenameAscii = preg_replace('/[^\x20-\x7E]/', '', $baseName);
    $filenameAscii = (trim($filenameAscii) !== '' ? $filenameAscii : ('report_' . $incidentId)) . '.docx';

    $tmpFile = tempnam(sys_get_temp_dir(), 'report_docx_');
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
