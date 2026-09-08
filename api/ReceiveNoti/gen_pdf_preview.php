<?php
/**
 * gen_pdf_preview.php - Preview PDF แบบการรับแจ้งเหตุ (F-CS-01) 
 * ดูฟอร์มเปล่าโดยไม่ต้องมีข้อมูล
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// ==========================================
// ตั้งค่า mPDF
// ==========================================

$defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new \Mpdf\Mpdf([
    'fontDir' => array_merge($fontDirs, [
        __DIR__ . '/../../fonts/Sarabun',
    ]),
    'fontdata' => $fontData + [
        'sarabun' => [
            'R'  => 'THSarabun-Regular.ttf',
            'B'  => 'THSarabun-Bold.ttf',
            'I'  => 'THSarabun-Italic.ttf',
            'BI' => 'THSarabun-BoldItalic.ttf',
        ]
    ],
    'default_font' => 'sarabun',
    'default_font_size' => 10,
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 10,
    'margin_bottom' => 10,
]);

// ==========================================
// Helper Functions
// ==========================================

function chk($val, $target) {
    $isChecked = ($val == $target);

    $size = "10";
    $style = 'style="vertical-align: -2px;"';

    $svgUnchecked = '
        <svg width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" ' . $style . '>
            <rect x="5" y="5" width="90" height="90" fill="none" stroke="black" stroke-width="8" />
        </svg>';

    $svgChecked = '
        <svg width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" ' . $style . '>
            <rect x="5" y="5" width="90" height="90" fill="none" stroke="black" stroke-width="8" />
            <path d="M20 50 L40 75 L80 20" fill="none" stroke="black" stroke-width="12" />
        </svg>';

    return $isChecked ? $svgChecked : $svgUnchecked;
}

function str_dots($val, $length = 30) {
    if (!empty($val) && $val != '-') {
        return '<span style="text-decoration: underline;">' . $val . '</span>';
    } else {
        return '<span style="text-decoration: underline;">' . str_repeat('&nbsp;', $length) . '</span>';
    }
}

// Helper function สำหรับเว้นวรรค
function sp($count) {
    return str_repeat('&nbsp;', $count);
}

// ==========================================
// สร้าง HTML (ตามฟอร์ม F-CS-01 แก้ไขครั้งที่ 2) - ตรง 100%
// ==========================================

$html = '
<style>
    body {
        font-family: sarabun;
        font-size: 16px;
        line-height: 1.5;
    }
    .header-title {
        text-align: center;
        font-size: 20px;
        font-weight: bold;
        text-decoration: underline;
        margin-bottom: 3px;
    }
    .border-box {
        border: 1px solid #000;
        padding: 2px 6px;
        font-size: 14px;
    }
    .main-box {
        border: 1px solid #000;
        padding: 8px 10px;
        margin-top: 5px;
    }
    .footer-text {
        font-size: 12px;
        text-align: right;
        margin-top: 8px;
    }
</style>

<!-- หัวกระดาษ -->
<div class="header-title">แบบการรับแจ้งเหตุ</div>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
    <tr>
        <td style="width: 45%;"></td>
        <td style="width: 55%; text-align: right;">
            <table style="border-collapse: collapse; float: right;">
                <tr>
                    <td class="border-box">ปจ.ซอ.' . str_dots('', 10) . '</td>
                    <td class="border-box">เลขที่รับ/เลขรายงาน' . str_dots('', 20) . '/25' . str_dots('', 8) . '</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- ข้อ 1 -->
<div style="margin-top: 5px; line-height: 1.8;">
    1. เขียนที่' . str_dots('', 77) . 'เมื่อวันที่' . str_dots('', 18) . 'เดือน' . str_dots('', 20) . 'พ.ศ.' . str_dots('', 15) . 'เวลา' . str_dots('', 15) . 'น.
</div>

<!-- ข้อ 2 -->
<div style="margin-top: 5px; line-height: 1.8;">
    2. รับแจ้งเหตุจาก สภ./สน.' . str_dots('', 42) . '&nbsp;&nbsp;&nbsp;' . chk('', 'phone') . '&nbsp;ทางโทรศัพท์ &nbsp;&nbsp;&nbsp;' . chk('', 'radio') . '&nbsp;วิทยุสื่อสาร &nbsp;&nbsp;&nbsp;' . chk('', 'other') . '&nbsp;อื่นๆ' . str_dots('', 50) . '
</div>

<!-- ข้อ 3 -->
<div style="margin-top: 5px; line-height: 1.8;">3. เหตุที่รับแจ้ง</div>
<div style="padding-left: 20px; margin-top: 3px; margin-bottom: 3px; line-height: 2.0;">
    ' . chk('', '01') . ' ทรัพย์ &nbsp;&nbsp;&nbsp;' . chk('', '02') . ' ชีวิต &nbsp;&nbsp;&nbsp;' . chk('', '03') . ' ระเบิด &nbsp;&nbsp;&nbsp;' . chk('', '04') . ' เพลิงไหม้ &nbsp;&nbsp;&nbsp;' . chk('', '05') . ' จราจร &nbsp;&nbsp;&nbsp;' . chk('', '06') . ' ตรวจเก็บวัตถุพยาน
</div>
<div style="padding-left: 20px; line-height: 1.8;">
    ' . chk('', '07') . ' อื่นๆ' . str_dots('', 170) . '
</div>

<!-- ข้อ 4 -->
<div style="margin-top: 8px; line-height: 1.0;">4. สถานที่เกิดเหตุ </div>
<div style="line-height: 1.8;">' . str_dots('', 185) . '</div>
<div style="line-height: 1.8;">' . str_dots('', 185) . '</div>

<!-- ข้อ 5 -->
<div style="margin-top: 8px; line-height: 1.8;">
    5. วัน เวลา ผู้เสียหายทราบเหตุ/เกิดเหตุ / พงส.ทราบเหตุ' . str_dots('', 68) . 'เวลาประมาณ' . str_dots('', 32) . 'น.
</div>

<!-- ข้อ 6 -->
<div style="margin-top: 8px; line-height: 1.0;">6. ข้อมูลเบื้องต้น </div>
<div style="line-height: 1.8;">' . str_dots('', 185) . '</div>
<div style="line-height: 1.8;">' . str_dots('', 185) . '</div>
<div style="line-height: 1.8;">' . str_dots('', 185) . '</div>

<!-- ข้อ 7 -->
<div style="margin-top: 10px; line-height: 1.8;">
    7. พนักงานสอบสวน' . str_dots('', 55) . 'เบอร์โทรศัพท์ติดต่อ' . str_dots('', 81) . '
</div>

<!-- ข้อ 8 -->
<div style="margin-top: 8px; line-height: 1.8;">
    8. ผู้เสียหาย' . str_dots('', 65) . 'เบอร์โทรศัพท์ติดต่อ' . str_dots('', 81) . '
</div>
<!-- กล่องส่วนเรียน หัวหน้าทีม -->
<div class="main-box">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 50%; vertical-align: top; border-right: 1px solid #000; padding-right: 10px;">
                <div style="margin-bottom: 3px;"><b>เรียน หัวหน้าทีมตรวจสถานที่เกิดเหตุ</b></div>
                <br>
                <div>ลงชื่อ' . str_dots('', 65) . 'ผู้รับแจ้ง</div>
                <br>
                <div>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(' . str_dots('', 65) . ')</div>
                <br>
                <div>ตำแหน่ง' . str_dots('', 65) . '</div>
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                <div><b>- ทราบ</b></div>
                <div style="line-height: 1.6;">- เรียน นวท.(สบ........) กสก.พฐก./ศพฐ........../พฐจ.</div>
                <div style="line-height: 1.6;">&nbsp;&nbsp;เพื่อโปรดทราบ และพิจารณาสั่งการ</div>
                <br>
                <div>ลงชื่อ' . str_dots('', 50) . 'หัวหน้าทีมตรวจสถานที่เกิดเหตุ</div>
                <br>
                <div>' . sp(7) . '(' . str_dots('', 50) . ')</div>
                <br>
                <div>ตำแหน่ง' . str_dots('', 50) . '</div>
            </td>
        </tr>
    </table>
</div>

<!-- ส่วนทราบ ดำเนินการ -->
<div class="main-box" style="margin-top: 0px; border-top: none;">
    <div style="line-height: 1.8; margin-bottom: 3px;">
        - ทราบ
    </div>
    <div style="line-height: 1.8;">
        ' . str_dots('', 50) . 'ดำเนินการ
    </div>
    <br>
    <div style="text-align: right; padding-right: 140px; line-height: 2.2;">
        ลงชื่อ' . str_dots('', 50) . '<br>
        (' . str_dots('', 50) . ')<br>
        ตำแหน่ง นวท.(สบ........) กสก.พฐก./ศพฐ........../พฐจ.
    </div>
    <div style="line-height: 3.0;">
        ' . sp(105) . str_dots('', 10) . '/' . str_dots('', 15) . '/' . str_dots('', 10) . '
    </div>
</div>

<!-- Footer -->
<div class="footer-text">
    F-CS-01 แก้ไขครั้งที่ 2<br>
    เริ่มใช้ 18 ส.ค. 64
</div>
';

// ==========================================
// Output PDF
// ==========================================

$mpdf->WriteHTML($html);
$mpdf->Output('ReceiveNotification_Preview.pdf', 'I');
