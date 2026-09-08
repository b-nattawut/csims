<?php
/**
 * word_docx_dotted_rule.php — เส้นไข่ปลาแบบจุดสั้น (แบบเดิม)
 */

/** จำนวนจุดมาตรฐานสำหรับช่องว่าง */
const WORD_DOCX_DOTS = 24;

/**
 * วาดบรรทัดไข่ปลาแบบจุดสั้น
 *
 * @param mixed $section PhpWord Section
 * @param array $fontStyle เช่น ['name'=>'TH Sarabun New','size'=>14]
 * @param callable $psBuilder fn(int $indentPt, array $extra=[]): array
 */
function wordDocxAddDottedRule($section, array $fontStyle, callable $psBuilder, int $indentPt = 0, string $prefix = ''): void
{
    $dots = str_repeat('.', WORD_DOCX_DOTS);
    $prefix = trim($prefix);
    $text = $prefix !== '' ? ($prefix . ' ' . $dots) : $dots;
    $section->addText($text, $fontStyle, $psBuilder($indentPt));
}

/**
 * ถ้าข้อความว่าง / เป็นแต่จุด / เป็นเลขหัวข้อว่าง → ใส่ไข่ปลาแบบเดิม
 * คืน true ถ้าวาดไปแล้ว
 */
function wordDocxTryDottedBlankLine(
    $section,
    string $text,
    array $fontStyle,
    callable $psBuilder,
    int $indentPt = 0,
    array $fontExtra = []
): bool {
    $t = trim($text);
    $fs = array_merge($fontStyle, $fontExtra);
    if ($t === '' || preg_match('/^\.+$/u', $t)) {
        wordDocxAddDottedRule($section, $fs, $psBuilder, $indentPt);
        return true;
    }
    if (preg_match('/^\d+(?:\.\d+)*\.\s*$/u', $t)) {
        wordDocxAddDottedRule($section, $fs, $psBuilder, $indentPt, rtrim($t));
        return true;
    }
    return false;
}
