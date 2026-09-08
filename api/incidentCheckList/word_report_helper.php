<?php

/**
 * word_report_helper.php — แปลง HTML รายงาน (ออกแบบสำหรับ PDF) ให้ layout ตรง PDF ใน Word
 */

const WORD_FONT = 'TH SarabunIT๙';
const WORD_FONT_SIZE = '14pt';

function wordFontStack(): string
{
    return '"TH SarabunIT๙","TH Sarabun IT๙","TH SarabunIT9","Sarabun","TH SarabunPSK","TH Sarabun New",sans-serif';
}

/**
 * สร้าง mPDF instance — ใช้ฟอนต์ Sarabun เหมือน PDF preview
 */
function createReportMpdf(): \Mpdf\Mpdf
{
    $defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];
    $defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    return new \Mpdf\Mpdf([
        'fontDir' => array_merge($fontDirs, [
            __DIR__ . '/../../fonts/Sarabun',
        ]),
        'fontdata' => $fontData + [
            'sarabun' => [
                'R'  => 'THSarabun-Regular.ttf',
                'B'  => 'THSarabun-Bold.ttf',
                'I'  => 'THSarabun-Italic.ttf',
                'BI' => 'THSarabun-BoldItalic.ttf',
            ],
        ],
        'default_font' => 'sarabun',
        'default_font_size' => 14,
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 12,
        'margin_bottom' => 10,
    ]);
}

/**
 * สร้าง PDF จาก HTML รายงาน — layout/จำนวนหน้าตรง PDF preview
 */
function buildReportPdfBytes(string $html): string
{
    $html = preg_replace('/<div class="no-print">.*?<\/div>/su', '', $html);

    $mpdf = createReportMpdf();
    $mpdf->WriteHTML($html);

    return $mpdf->Output('', 'S');
}

/**
 * @return string[]
 */
function stripReportHtmlPages(string $html): array
{
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/@import\s+url\([^)]+\)\s*;/i', '', $html);
    $html = preg_replace('/<div class="no-print">.*?<\/div>/su', '', $html);

    if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
        $body = $m[1];
    } else {
        $body = $html;
    }

    // แยกหน้าก่อน แล้วค่อยลบ comment อื่น (ถ้าลบก่อน split จะเสีย marker PAGE 1/2/3...)
    $pages = preg_split('/<!--\s*=+\s*PAGE\s+\d+\s*=+\s*-->/i', $body);
    $pages = array_map(static function ($p) {
        return trim(preg_replace('/<!--[\s\S]*?-->/', '', $p));
    }, $pages);
    $pages = array_values(array_filter($pages, 'wordPageHasContent'));

    if (count($pages) <= 1) {
        $pages = preg_split('/(?=<div[^>]*class="[^"]*\bpage\b[^"]*"[^>]*>)/i', $body);
        $pages = array_values(array_filter(array_map('trim', $pages), 'wordPageHasContent'));
    }

    if (empty($pages)) {
        $pages = [trim($body)];
    }

    return array_map('transformPageLayoutForWord', $pages);
}

/** ตรวจว่าหน้ามีเนื้อหาจริง (ไม่ใช่ comment/whitespace อย่างเดียว) */
function wordPageHasContent(string $html): bool
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/u', '', $text);
    return $text !== '';
}

function wordHasClass(\DOMElement $el, string $class): bool
{
    if (!$el->hasAttribute('class')) {
        return false;
    }
    return in_array($class, preg_split('/\s+/', trim($el->getAttribute('class'))), true);
}

function wordIsInFooter(\DOMNode $node): bool
{
    while ($node instanceof \DOMElement) {
        if (wordHasClass($node, 'word-form-footer')) {
            return true;
        }
        $node = $node->parentNode;
    }
    return false;
}

function wordClassIndent(array $classes): string
{
    if (in_array('i3', $classes, true)) {
        return 'padding-left:60pt;';
    }
    if (in_array('i2', $classes, true)) {
        return 'padding-left:40pt;';
    }
    if (in_array('i1', $classes, true)) {
        return 'padding-left:20pt;';
    }
    return '';
}

function wordBaseStyle(): string
{
    return 'font-family:' . wordFontStack() . ';font-size:' . WORD_FONT_SIZE . ';color:#000;'
        . 'mso-bidi-font-family:"TH SarabunIT๙";mso-ascii-font-family:"TH SarabunIT๙";mso-hansi-font-family:"TH SarabunIT๙";'
        . 'mso-bidi-font-size:14.0pt;';
}

function wordParagraphStyle(array $extra = []): string
{
    $style = 'margin:0 0 1pt 0;padding:0;line-height:1.9;' . wordBaseStyle();
    foreach ($extra as $k => $v) {
        $style .= $k . ':' . $v . ';';
    }
    return $style;
}

function wordSpanStyleForElement(\DOMElement $el): string
{
    $style = wordBaseStyle();
    $classes = $el->hasAttribute('class')
        ? preg_split('/\s+/', trim($el->getAttribute('class')))
        : [];

    if (in_array('fl-b', $classes, true) || in_array('sec-heading', $classes, true)) {
        $style .= 'font-weight:bold;';
    }
    if (preg_match('/^fd(-|$)/', implode(' ', $classes)) || in_array('fd', $classes, true)
        || in_array('fd-s', $classes, true) || in_array('fd-m', $classes, true)
        || in_array('fd-l', $classes, true) || in_array('fd-full', $classes, true)
        || in_array('fd-block', $classes, true) || in_array('fd-block-i2', $classes, true)
        || in_array('fd-flow', $classes, true)) {
        $style .= 'border-bottom:1px dotted #888;';
    }
    if (in_array('fd-m', $classes, true) || in_array('fd-s', $classes, true)) {
        $style .= 'padding:0 3pt;';
    }
    if (in_array('cb', $classes, true)) {
        $style .= 'border:1px solid #000;display:inline-block;min-width:11pt;text-align:center;font-weight:bold;';
    }
    if (wordIsInFooter($el)) {
        $style = preg_replace('/color:#000;/', 'color:#808080;', $style);
        $style .= 'font-size:9pt;font-weight:400;';
    }

    if ($el->hasAttribute('style')) {
        $inline = $el->getAttribute('style');
        if (preg_match('/font-weight\s*:\s*bold/i', $inline)) {
            $style .= 'font-weight:bold;';
        }
        if (preg_match('/text-align\s*:\s*center/i', $inline)) {
            $style .= 'text-align:center;';
        }
        if (preg_match('/border-bottom\s*:\s*[^;]+/i', $inline)) {
            if (strpos($style, 'border-bottom') === false) {
                $style .= 'border-bottom:1px dotted #888;';
            }
        }
    }

    return $style;
}

function wordApplyInlineStyles(\DOMDocument $dom, \DOMXPath $xpath): void
{
    foreach ($xpath->query('//span|//label|//p|//td|//th|//u|//b|//strong') as $el) {
        if (!($el instanceof \DOMElement)) {
            continue;
        }
        if (in_array(strtolower($el->tagName), ['p', 'td', 'th'], true)) {
            $existing = $el->hasAttribute('style') ? $el->getAttribute('style') : '';
            if (strpos($existing, 'font-family') === false) {
                $el->setAttribute('style', wordBaseStyle() . $existing);
            }
            continue;
        }
        $el->setAttribute('style', wordSpanStyleForElement($el));
        $el->removeAttribute('class');
    }
}

function wordMoveChildren(\DOMNode $from, \DOMNode $to): void
{
    while ($from->firstChild) {
        $to->appendChild($from->firstChild);
    }
}

function wordFlattenInto(\DOMDocument $dom, \DOMNode $node, \DOMElement $target): void
{
    if ($node instanceof \DOMText) {
        $text = preg_replace('/\s+/u', ' ', $node->textContent ?? '');
        if ($text !== '' && $text !== ' ') {
            $target->appendChild($dom->createTextNode($text));
        }
        return;
    }
    if (!($node instanceof \DOMElement)) {
        return;
    }

    $tag = strtolower($node->tagName);
    if ($tag === 'label') {
        wordMoveChildren($node, $target);
        return;
    }
    if (in_array($tag, ['span', 'u', 'b', 'strong', 'i', 'em', 'a'], true)) {
        $clone = $node->cloneNode(true);
        if ($clone instanceof \DOMElement) {
            $clone->setAttribute('style', wordSpanStyleForElement($node));
            $clone->removeAttribute('class');
        }
        $target->appendChild($clone);
        return;
    }
    if ($tag === 'br') {
        $target->appendChild($dom->createElement('br'));
        return;
    }
    if ($tag === 'img') {
        $target->appendChild($node->cloneNode(true));
        return;
    }

    wordMoveChildren($node, $target);
}

function wordIsBlockContainer(\DOMElement $el): bool
{
    foreach ($el->childNodes as $child) {
        if ($child instanceof \DOMElement) {
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['div', 'p', 'table', 'ul', 'ol', 'h1', 'h2', 'h3'], true)) {
                return true;
            }
        }
    }
    return false;
}

function wordUnwrapPageDivs(\DOMDocument $dom): void
{
    $root = $dom->getElementById('word-root');
    if (!$root) {
        return;
    }
    $children = [];
    foreach ($root->childNodes as $child) {
        $children[] = $child;
    }
    foreach ($children as $child) {
        if ($child instanceof \DOMElement && strtolower($child->tagName) === 'div') {
            $fragment = $dom->createDocumentFragment();
            while ($child->firstChild) {
                $fragment->appendChild($child->firstChild);
            }
            $root->insertBefore($fragment, $child);
            $root->removeChild($child);
        }
    }
}

function wordCreateParagraph(\DOMDocument $dom, array $classes, array $extraStyle = []): \DOMElement
{
    $p = $dom->createElement('p');
    $style = wordParagraphStyle($extraStyle) . wordClassIndent($classes);
    $p->setAttribute('style', $style);
    return $p;
}

/**
 * @return string[]
 */
function wordExtractFooterLines(\DOMNode $node): array
{
    $lines = [];
    $buffer = '';

    $flush = static function () use (&$buffer, &$lines): void {
        $line = trim(str_replace("\xc2\xa0", ' ', $buffer));
        if ($line !== '') {
            $lines[] = $line;
        }
        $buffer = '';
    };

    $walk = static function (\DOMNode $n) use (&$walk, &$buffer, $flush): void {
        if ($n instanceof \DOMText) {
            $buffer .= $n->textContent;
            return;
        }
        if (!($n instanceof \DOMElement)) {
            return;
        }

        $tag = strtolower($n->tagName);
        if ($tag === 'br') {
            $flush();
            return;
        }
        if ($tag === 'div') {
            $flush();
            $line = trim(str_replace("\xc2\xa0", ' ', $n->textContent));
            if ($line !== '') {
                $lines[] = $line;
            }
            return;
        }

        foreach ($n->childNodes as $child) {
            $walk($child);
        }
    };

    foreach ($node->childNodes as $child) {
        $walk($child);
    }
    $flush();

    return $lines;
}

function wordPopulateFooterCell(\DOMDocument $dom, \DOMElement $td, \DOMElement $source, string $align): void
{
    foreach (wordExtractFooterLines($source) as $line) {
        $p = $dom->createElement('p');
        $p->setAttribute(
            'style',
            'margin:0 0 1pt 0;padding:0;line-height:1.45;color:#808080;font-size:9pt;font-weight:400;'
            . 'text-align:' . $align . ';' . wordBaseStyle()
        );
        $p->appendChild($dom->createTextNode($line));
        $td->appendChild($p);
    }
}

/**
 * แปลง HTML 1 หน้า — จัด layout ให้เหมือน PDF (แถว .fr อยู่บรรทัดเดียวกัน)
 */
function transformPageLayoutForWord(string $html): string
{
    $html = preg_replace('/<div[^>]*class="[^"]*\bpage\b[^"]*"[^>]*>/i', '<div>', $html, 1);

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<?xml encoding="UTF-8"><div id="word-root">' . $html . '</div>';
    if (!@$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
        libxml_clear_errors();
        return htmlFragmentToXhtml($html);
    }

    $xpath = new DOMXPath($dom);
    wordUnwrapPageDivs($dom);

    // page-header → ตาราง 2 คอลัมน์
    foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " page-header ")]') as $header) {
        if (!($header instanceof \DOMElement)) {
            continue;
        }
        $table = $dom->createElement('table');
        $table->setAttribute('style', 'width:100%;border:none;border-collapse:collapse;margin:0 0 4pt 0;');
        $tr = $dom->createElement('tr');

        $tdLeft = $dom->createElement('td');
        $tdLeft->setAttribute('style', 'width:78%;border:none;vertical-align:top;' . wordBaseStyle());
        $left = null;
        $right = null;
        foreach ($header->childNodes as $child) {
            if ($child instanceof \DOMElement && wordHasClass($child, 'page-header-left')) {
                $left = $child;
            }
            if ($child instanceof \DOMElement && wordHasClass($child, 'page-header-right')) {
                $right = $child;
            }
        }
        if ($left) {
            wordMoveChildren($left, $tdLeft);
        }
        $tdRight = $dom->createElement('td');
        $tdRight->setAttribute('style', 'width:22%;border:none;text-align:right;vertical-align:top;' . wordBaseStyle());
        if ($right) {
            wordMoveChildren($right, $tdRight);
        }

        $tr->appendChild($tdLeft);
        $tr->appendChild($tdRight);
        $table->appendChild($tr);
        $header->parentNode->replaceChild($table, $header);
    }

    // form-title → ย่อหน้ากลาง
    foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " form-title ")]') as $node) {
        if (!($node instanceof \DOMElement)) {
            continue;
        }
        $p = wordCreateParagraph($dom, [], [
            'text-align' => 'center',
            'font-weight' => 'bold',
            'font-size' => '15pt',
            'text-decoration' => 'underline',
            'margin' => '6pt 0 4pt 0',
        ]);
        wordMoveChildren($node, $p);
        $node->parentNode->replaceChild($p, $node);
    }

    // sec-heading / sub-heading / header-note
    foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " sec-heading ") or contains(concat(" ", normalize-space(@class), " "), " sub-heading ") or contains(concat(" ", normalize-space(@class), " "), " header-note ")]') as $node) {
        if (!($node instanceof \DOMElement)) {
            continue;
        }
        $classes = preg_split('/\s+/', trim($node->getAttribute('class')));
        $extra = ['font-weight' => 'bold'];
        if (wordHasClass($node, 'sub-heading')) {
            $extra['margin-left'] = '20pt';
        }
        $p = wordCreateParagraph($dom, $classes, $extra);
        wordMoveChildren($node, $p);
        $node->parentNode->replaceChild($p, $node);
    }

    // fd-block / fd-multiline → ย่อหน้าเต็มบรรทัด
    foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " fd-block ") or contains(concat(" ", normalize-space(@class), " "), " fd-block-i2 ") or contains(concat(" ", normalize-space(@class), " "), " fd-multiline ")]') as $node) {
        if (!($node instanceof \DOMElement)) {
            continue;
        }
        $classes = preg_split('/\s+/', trim($node->getAttribute('class')));
        $p = wordCreateParagraph($dom, $classes, ['border-bottom' => '1px dotted #888']);
        wordMoveChildren($node, $p);
        $node->parentNode->replaceChild($p, $node);
    }

    // .fr → <p> เนื้อหาอยู่บรรทัดเดียวกัน (เหมือน PDF)
    $frNodes = [];
    foreach ($xpath->query('//*[@class]') as $node) {
        if ($node instanceof \DOMElement && wordHasClass($node, 'fr')) {
            $frNodes[] = $node;
        }
    }
    foreach ($frNodes as $node) {
        $classes = preg_split('/\s+/', trim($node->getAttribute('class')));
        $p = wordCreateParagraph($dom, $classes);
        foreach (iterator_to_array($node->childNodes) as $child) {
            wordFlattenInto($dom, $child, $p);
        }
        $node->parentNode->replaceChild($p, $node);
    }

    // .fr-flow → เหมือน .fr (ข้อความยาว + เส้นประ)
    $frFlowNodes = [];
    foreach ($xpath->query('//*[@class]') as $node) {
        if ($node instanceof \DOMElement && wordHasClass($node, 'fr-flow')) {
            $frFlowNodes[] = $node;
        }
    }
    foreach ($frFlowNodes as $node) {
        $classes = preg_split('/\s+/', trim($node->getAttribute('class')));
        $p = wordCreateParagraph($dom, $classes);
        foreach (iterator_to_array($node->childNodes) as $child) {
            wordFlattenInto($dom, $child, $p);
        }
        $node->parentNode->replaceChild($p, $node);
    }

    // signature-block → ตารางชิดขวา
    foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " signature-block ")]') as $node) {
        if (!($node instanceof \DOMElement)) {
            continue;
        }
        $table = $dom->createElement('table');
        $table->setAttribute('style', 'width:100%;border:none;border-collapse:collapse;margin-top:20pt;');
        $tr = $dom->createElement('tr');
        $tdSpacer = $dom->createElement('td');
        $tdSpacer->setAttribute('style', 'width:50%;border:none;');
        $tdBody = $dom->createElement('td');
        $tdBody->setAttribute('style', 'width:50%;border:none;text-align:center;line-height:2;' . wordBaseStyle());
        wordMoveChildren($node, $tdBody);
        $tr->appendChild($tdSpacer);
        $tr->appendChild($tdBody);
        $table->appendChild($tr);
        $node->parentNode->replaceChild($table, $node);
    }

    // form-footer → ตาราง 2 คอลัมน์ + เส้นขีดล่าง (แบบฟอร์ม)
    foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " form-footer ")]') as $footer) {
        if (!($footer instanceof \DOMElement)) {
            continue;
        }
        $table = $dom->createElement('table');
        $table->setAttribute('class', 'word-form-footer');
        $table->setAttribute('style', 'width:100%;border:none;border-collapse:collapse;margin-top:14pt;'
            . 'border-bottom:1px solid #BFBFBF;padding-bottom:5pt;');
        $tr = $dom->createElement('tr');

        $tdLeft = $dom->createElement('td');
        $tdLeft->setAttribute('style', 'width:50%;border:none;vertical-align:top;color:#808080;font-size:9pt;'
            . 'font-weight:400;line-height:1.45;' . wordBaseStyle());
        $tdRight = $dom->createElement('td');
        $tdRight->setAttribute('style', 'width:50%;border:none;text-align:right;vertical-align:top;color:#808080;'
            . 'font-size:9pt;font-weight:400;line-height:1.45;white-space:normal;' . wordBaseStyle());

        $left = null;
        $rightParts = [];
        foreach ($footer->childNodes as $child) {
            if (!($child instanceof \DOMElement)) {
                continue;
            }
            if (wordHasClass($child, 'form-footer-left') || wordHasClass($child, 'form-footer-left-bottom')) {
                $left = $child;
            } elseif (wordHasClass($child, 'form-footer-right') || wordHasClass($child, 'form-footer-right-bottom')) {
                $rightParts[] = $child;
            } elseif (trim(str_replace("\xc2\xa0", ' ', $child->textContent)) !== '') {
                $rightParts[] = $child;
            }
        }
        if ($left) {
            wordPopulateFooterCell($dom, $tdLeft, $left, 'left');
        }
        foreach ($rightParts as $part) {
            wordPopulateFooterCell($dom, $tdRight, $part, 'right');
        }

        $tr->appendChild($tdLeft);
        $tr->appendChild($tdRight);
        $table->appendChild($tr);
        $footer->parentNode->replaceChild($table, $footer);
    }

    // div ที่เหลือ (ไม่มี block ข้างใน) → แปลงเป็น p
    $divNodes = [];
    foreach ($xpath->query('//div') as $node) {
        if ($node instanceof \DOMElement && $node->getAttribute('id') !== 'word-root' && !wordIsBlockContainer($node)) {
            $divNodes[] = $node;
        }
    }
    foreach ($divNodes as $node) {
        $extra = wordIsInFooter($node)
            ? ['color' => '#808080', 'font-size' => '9pt', 'font-weight' => '400', 'line-height' => '1.45', 'margin' => '0 0 2pt 0']
            : [];
        $p = wordCreateParagraph($dom, [], $extra);
        wordMoveChildren($node, $p);
        $node->parentNode->replaceChild($p, $node);
    }

    wordApplyInlineStyles($dom, $xpath);

    $root = $dom->getElementById('word-root');
    if (!$root) {
        libxml_clear_errors();
        return htmlFragmentToXhtml($html);
    }

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    libxml_clear_errors();

    return htmlFragmentToXhtml($out);
}

function htmlFragmentToXhtml(string $html): string
{
    $html = preg_replace('/<br\s*>/i', '<br/>', $html);
    $html = preg_replace('/<hr([^>]*)(?<!\/)>/i', '<hr$1/>', $html);
    $html = preg_replace('/<img([^>]*)(?<!\/)>/i', '<img$1/>', $html);
    return $html;
}

/**
 * สร้างไฟล์ .docx ด้วย PhpWord
 */
function buildReportDocx(array $pageHtmls): \PhpOffice\PhpWord\PhpWord
{
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $phpWord->setDefaultFontName('Sarabun');
    $phpWord->setDefaultFontSize(14);

    $sectionStyle = [
        'marginTop'    => 720,
        'marginBottom' => 540,
        'marginLeft'   => 850,
        'marginRight'  => 850,
    ];

    $section = $phpWord->addSection($sectionStyle);
    $fontStyle = ['name' => 'Sarabun', 'size' => 14];

    foreach ($pageHtmls as $i => $pageHtml) {
        if ($i > 0) {
            $section->addPageBreak();
        }
        try {
            \PhpOffice\PhpWord\Shared\Html::addHtml($section, $pageHtml, false, false);
        } catch (\Throwable $e) {
            $plain = html_entity_decode(strip_tags($pageHtml), ENT_QUOTES, 'UTF-8');
            $plain = preg_replace('/\s+/u', ' ', trim($plain));
            if ($plain !== '') {
                $section->addText($plain, $fontStyle);
            }
        }
    }

    return $phpWord;
}

/**
 * แยก footer ออกจากเนื้อหาหน้า (หลัง transform จะเป็น table.word-form-footer)
 *
 * @return array{body: string, footer: string}
 */
function wordExtractPageFooter(string $html): array
{
    $footer = '';
    if (preg_match('/<table\b[^>]*\bword-form-footer\b[^>]*>[\s\S]*?<\/table>/i', $html, $m)) {
        $footer = trim($m[0]);
        $html = trim(str_replace($m[0], '', $html));
    } elseif (preg_match('/<table\b[^>]*>[\s\S]*?<\/table>\s*$/i', $html, $m)
        && stripos($m[0], '#808080') !== false) {
        // fallback: ตาราง footer ที่ transform แล้ว (สีเทา) ท้ายหน้า
        $footer = trim($m[0]);
        $html = trim(substr($html, 0, -strlen($m[0])));
    }

    return ['body' => $html, 'footer' => $footer];
}

/** เตรียม HTML footer สำหรับ Word Footer zone (mso-element:footer) */
function wordPrepareFooterForMsoZone(string $footerHtml): string
{
    if (trim($footerHtml) === '') {
        return '';
    }

    $footerHtml = preg_replace('/margin-top:\s*[^;]+;?/i', '', $footerHtml);
    $footerHtml = preg_replace('/\sclass="word-form-footer"/i', ' class="word-form-footer word-mso-footer"', $footerHtml);

    return $footerHtml;
}

function wordBuildMsoFooterBlock(string $footerInnerHtml): string
{
    $inner = wordPrepareFooterForMsoZone($footerInnerHtml);
    if ($inner === '') {
        return '';
    }

    return '<div style="mso-element:footer" id="WordReportFooter">' . $inner . '</div>';
}

/** Word field — เลขหน้า / จำนวนหน้าทั้งหมด */
function wordPageNumberFieldHtml(): string
{
    return '<!--[if supportFields]><span style=\'mso-element:field-begin\'></span> PAGE '
        . '<span style=\'mso-element:field-separator\'></span><span style=\'mso-element:field-end\'></span>'
        . '<![endif]-->/<!--[if supportFields]><span style=\'mso-element:field-begin\'></span> NUMPAGES '
        . '<span style=\'mso-element:field-separator\'></span><span style=\'mso-element:field-end\'></span><![endif]-->';
}

/**
 * แยก header (เลขที่รายงาน + หน่วยงาน) ออกจากเนื้อหาหน้า
 *
 * @return array{header: string, body: string}
 */
function wordExtractPageHeader(string $html): array
{
    $header = '';
    $body = trim($html);

    if (preg_match('/^\s*(<table\b(?![^>]*\bword-form-footer\b)[^>]*>[\s\S]*?<\/table>)/i', $body, $m)) {
        $header .= $m[1];
        $body = trim(substr($body, strlen($m[0])));
    }

    if (preg_match('/^\s*(<p\b[^>]*>[\s\S]*?หน่วยงาน[\s\S]*?<\/p>)/iu', $body, $m)) {
        $header .= $m[1];
        $body = trim(substr($body, strlen($m[0])));
    }

    return ['header' => trim($header), 'body' => $body];
}

/** ใส่เลขหน้าแบบ Word field ใน header (แทน 1/4, 2/4 ฯลฯ) */
function wordHeaderWithPageFields(string $headerHtml): string
{
    if (trim($headerHtml) === '') {
        return '';
    }

    $replaced = preg_replace(
        '/(<td[^>]*text-align:\s*right[^>]*>)([\s\S]*?)(<\/td>)/i',
        '$1' . wordPageNumberFieldHtml() . '$3',
        $headerHtml,
        1
    );

    return is_string($replaced) ? $replaced : $headerHtml;
}

function wordBuildMsoHeaderBlock(string $headerInnerHtml): string
{
    $inner = wordHeaderWithPageFields($headerInnerHtml);
    if ($inner === '') {
        return '';
    }

    return '<div style="mso-element:header" id="WordReportHeader">' . $inner . '</div>';
}

/** Page break ระหว่างหน้า — บังคับขึ้นหน้าใหม่ใน Word */
function wordPageBreakHtml(): string
{
    return '<p style="margin:0;padding:0;line-height:0;font-size:0;mso-line-height-rule:exactly;">'
        . '<br clear="all" style="page-break-before:always;mso-page-break-before:always;"/>'
        . '</p>';
}

/** ประกอบ 1 หน้า — header + body + footer ครบ (เหมือน PDF ทุกหน้า) */
function wordAssemblePageSheet(string $html, bool $isLastPage = false): string
{
    if (trim($html) === '') {
        return '';
    }

    $parts = wordExtractPageFooter($html);
    $headerParts = wordExtractPageHeader($parts['body']);

    $header = $headerParts['header'];
    $body = $headerParts['body'];
    $footer = $parts['footer'];

    $breakStyle = $isLastPage ? '' : 'page-break-after:always;mso-page-break-after:always;';

    return '<table class="word-page-sheet" align="center" cellpadding="0" cellspacing="0" border="0" '
        . 'style="width:180mm;max-width:100%;border:none;border-collapse:collapse;'
        . 'mso-table-lspace:0pt;mso-table-rspace:0pt;' . $breakStyle . '">'
        . '<tr><td style="border:none;padding:0;vertical-align:top;text-align:left;">' . $header . '</td></tr>'
        . '<tr><td style="border:none;padding:0;vertical-align:top;text-align:left;">' . $body . '</td></tr>'
        . '<tr><td style="border:none;padding:0;vertical-align:bottom;text-align:left;">' . $footer . '</td></tr>'
        . '</table>';
}

/** แปลง BLOB รูปใน DB → ['uri'=>, 'w'=>mm, 'h'=>mm] หรือ null เมื่อไม่พบ */
function wordBlobToPhoto($pdo, $fileId)
{
    if (empty($fileId) || !$pdo) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT file_name FROM incident_checklist_transaction_file WHERE id = ? LIMIT 1");
        $stmt->execute([$fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['file_name'])) {
            return null;
        }
        $bytes = $row['file_name'];
        $mime = 'image/png';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $m = $finfo->buffer($bytes);
            if ($m && $m !== 'application/octet-stream') {
                $mime = $m;
            }
        }

        // กรอบเป้าหมาย (กว้าง x สูง) เป็น mm — ปรับค่าตรงนี้เพื่อเปลี่ยนขนาดรูปใน Word
        $maxW = 160.0; // mm (กว้างเกือบเต็มหน้า เหลือขอบนิดเดียว)
        $maxH = 200.0; // mm (เพดานความสูง กันรูปแนวตั้งล้นหน้า)
        $mmToPx = 3.7795;

        $w = null;
        $h = null;
        $info = @getimagesizefromstring($bytes);
        if ($info && !empty($info[0]) && !empty($info[1])) {
            $natW = (int) $info[0];
            $natH = (int) $info[1];
            // สเกลให้พอดีกรอบ (ย่อเท่านั้น ไม่ขยายเกินต้นฉบับ)
            $scale = min(($maxW * $mmToPx) / $natW, ($maxH * $mmToPx) / $natH, 1.0);
            $tgtW = max(1, (int) round($natW * $scale));
            $tgtH = max(1, (int) round($natH * $scale));

            // ย่อไฟล์รูปจริงด้วย GD → ฝังรูปที่เล็กลงจริง Word จะแสดงตามขนาดนี้เป๊ะ ไม่ขยายล้น
            if ($scale < 1.0 && function_exists('imagecreatefromstring')) {
                $src = @imagecreatefromstring($bytes);
                if ($src !== false) {
                    $dst = imagecreatetruecolor($tgtW, $tgtH);
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $tgtW, $tgtH, $natW, $natH);
                    ob_start();
                    imagepng($dst);
                    $resized = ob_get_clean();
                    imagedestroy($src);
                    imagedestroy($dst);
                    if (!empty($resized)) {
                        $bytes = $resized;
                        $mime = 'image/png';
                    }
                }
            }

            $w = round($tgtW / $mmToPx, 1);
            $h = round($tgtH / $mmToPx, 1);
        }

        $uri = 'data:' . $mime . ';base64,' . base64_encode($bytes);

        return ['uri' => $uri, 'w' => $w, 'h' => $h];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * ดึงรูปภาพที่บันทึกไว้ตอน checklist (เก็บเป็น BLOB) → array ของ ['uri','w','h']
 * รูปอยู่ที่ key 'photos' ของ incident_checklist_data ทุกชนิดคดี
 */
function wordFetchIncidentPhotos($pdo, int $incidentId): array
{
    $result = [];
    if (!$pdo || $incidentId <= 0) {
        return $result;
    }
    try {
        $stmt = $pdo->prepare("SELECT incident_checklist_data, incident_report_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
        $stmt->execute([$incidentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $result;
        }

        $photos = [];
        foreach (['incident_checklist_data', 'incident_report_data'] as $col) {
            if (!empty($row[$col])) {
                $d = json_decode($row[$col], true);
                if (is_array($d) && !empty($d['photos']) && is_array($d['photos'])) {
                    $photos = $d['photos'];
                    break;
                }
            }
        }

        foreach ($photos as $photo) {
            $fileId = '';
            if (is_array($photo)) {
                $fileId = $photo['file_id'] ?? ($photo['id'] ?? '');
            } elseif (is_numeric($photo)) {
                $fileId = $photo;
            }
            if (empty($fileId)) {
                continue;
            }
            $p = wordBlobToPhoto($pdo, $fileId);
            if (is_array($p) && !empty($p['uri']) && strpos($p['uri'], 'data:image') === 0) {
                $result[] = $p;
            }
        }
    } catch (Exception $e) {
        /* ignore */
    }
    return $result;
}

/**
 * สร้างหน้ารูปภาพประกอบ 1 รูป/หน้า แบบ 50:50
 * รูปอยู่ครึ่งบนของหน้า (ขนาด mm ตายตัวกัน Word ขยายล้นหน้า) ครึ่งล่างเว้นว่างไว้เขียนคำบรรยาย
 */
function wordBuildPhotoPageSheet($photo, int $index): string
{
    $stack = wordFontStack();
    $caption = 'ภาพที่ ' . $index;

    $uri = is_array($photo) ? ($photo['uri'] ?? '') : $photo;
    $w = is_array($photo) ? ($photo['w'] ?? null) : null;
    $h = is_array($photo) ? ($photo['h'] ?? null) : null;

    // ใส่ความกว้างเป็น pixel attribute ด้วย เพราะ Word เคารพ width="px" แน่นอน (mm บางเครื่องไม่ทำงาน)
    $mmToPx = 3.7795;
    if ($w && $h) {
        $wpx = (int) round($w * $mmToPx);
        $hpx = (int) round($h * $mmToPx);
        $imgAttr = ' width="' . $wpx . '" height="' . $hpx . '"';
        $imgSize = 'width:' . $w . 'mm;height:' . $h . 'mm;';
    } else {
        $wpx = (int) round(160 * $mmToPx);
        $imgAttr = ' width="' . $wpx . '"';
        $imgSize = 'width:160mm;max-width:160mm;height:auto;';
    }

    return '<table class="word-photo-sheet" align="center" cellpadding="0" cellspacing="0" border="0" '
        . 'style="width:180mm;max-width:100%;border:none;border-collapse:collapse;'
        . 'mso-table-lspace:0pt;mso-table-rspace:0pt;">'
        . '<tr><td style="border:none;padding:0;vertical-align:top;text-align:center;">'
        . '<img src="' . $uri . '"' . $imgAttr . ' style="' . $imgSize . 'border:1px solid #ccc;" alt="' . $caption . '"/>'
        . '<p style="margin:6pt 0 0 0;text-align:center;font-family:' . $stack . ';font-size:' . WORD_FONT_SIZE . ';">' . $caption . '</p>'
        . '</td></tr>'
        . '</table>';
}

/**
 * สร้าง Word HTML (.doc) — layout ตรง PDF ดีที่สุดใน Word
 * $photoDataUris : รูปประกอบจาก checklist ต่อท้ายเอกสาร (1 รูป/หน้า)
 */
function buildWordHtmlDocument(array $pageHtmls, array $photoDataUris = []): string
{
    $body = '';

    $total = count($pageHtmls);
    $hasPhotos = !empty($photoDataUris);
    foreach ($pageHtmls as $i => $page) {
        $page = wordSanitizePageHtml($page);
        if ($i > 0) {
            $body .= wordPageBreakHtml();
        }
        // ถ้ามีหน้ารูปต่อท้าย หน้ารายงานสุดท้ายต้องไม่ใช่หน้าสุดท้ายจริง
        $isLast = ($i === $total - 1) && !$hasPhotos;
        $body .= wordAssemblePageSheet($page, $isLast);
    }

    if ($hasPhotos) {
        $reportPageCount = $total;
        foreach (array_values($photoDataUris) as $idx => $photo) {
            if ($reportPageCount > 0 || $idx > 0) {
                $body .= wordPageBreakHtml();
            }
            $body .= wordBuildPhotoPageSheet($photo, $idx + 1);
        }
    }

    $stack = wordFontStack();

    $pageRule = '@page WordSection1{size:21cm 29.7cm;margin:1.2cm 1.5cm 1.2cm 1.5cm;}';

    $pageSheetCss = 'table.word-page-sheet{width:180mm;max-width:100%;margin-left:auto;margin-right:auto;}'
        . 'table.word-page-sheet td{border:none;vertical-align:top;}'
        . 'table.word-photo-sheet{width:180mm;max-width:100%;margin-left:auto;margin-right:auto;}'
        . 'table.word-photo-sheet td{border:none;}'
        . 'table.word-form-footer{width:100%;border:none;border-collapse:collapse;margin-top:10pt;'
        . 'border-bottom:1px solid #BFBFBF;padding-bottom:5pt;}'
        . 'table.word-form-footer td{color:#808080;font-size:9pt;font-weight:400;}';

    return '<html xmlns:o="urn:schemas-microsoft-com:office:office"'
        . ' xmlns:w="urn:schemas-microsoft-com:office:word"'
        . ' xmlns="http://www.w3.org/TR/REC-html40">'
        . '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>'
        . '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom>'
        . '<w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->'
        . '<style>'
        . '@font-face{font-family:"Sarabun";font-weight:400;font-style:normal;'
        . 'src:url("/csims/fonts/Sarabun/Sarabun-Regular.ttf") format("truetype");}'
        . '@font-face{font-family:"Sarabun";font-weight:700;font-style:normal;'
        . 'src:url("/csims/fonts/Sarabun/Sarabun-Bold.ttf") format("truetype");}'
        . (is_readable(__DIR__ . '/form_footer.css') ? file_get_contents(__DIR__ . '/form_footer.css') : '')
        . $pageRule
        . 'div.WordSection1{page:WordSection1;}'
        . $pageSheetCss
        . '/* Style Definitions */'
        . 'p.MsoNormal,li.MsoNormal,div.MsoNormal,p,li,div{mso-style-parent:"";'
        . 'font-family:' . $stack . ';mso-fareast-font-family:"TH SarabunIT๙";'
        . 'mso-bidi-font-family:"TH SarabunIT๙";mso-ascii-font-family:"TH SarabunIT๙";mso-hansi-font-family:"TH SarabunIT๙";}'
        . 'body,p,span,td,th,u,b,strong,label{font-family:' . $stack . ';font-size:' . WORD_FONT_SIZE . ';'
        . 'mso-fareast-font-family:"TH SarabunIT๙";'
        . 'mso-bidi-font-family:"TH SarabunIT๙";mso-ascii-font-family:"TH SarabunIT๙";mso-hansi-font-family:"TH SarabunIT๙";}'
        . 'body{margin:0;padding:0;}'
        . 'p{margin:0;padding:0;line-height:1.6;}'
        . 'table{border-collapse:collapse;}'
        . 'td,th{vertical-align:top;}'
        . 'table.data-table td,table.data-table th{border:1px solid #000;padding:2pt 4pt;}'
        . '</style></head><body><div class="WordSection1">' . $body . '</div></body></html>';
}

/** ลบ style ที่ทำให้ Word แตกหน้า */
function wordSanitizePageHtml(string $html): string
{
    $html = preg_replace('/page-break-[^:;]+:\s*[^;]+;?/i', '', $html);
    $html = preg_replace('/(?:min-)?height\s*:\s*\d+(?:\.\d+)?(?:mm|px|pt)[^;]*;?/i', '', $html);
    $html = preg_replace('/<p[^>]*>\s*(&nbsp;|\s)*<\/p>/i', '', $html);
    return $html;
}
