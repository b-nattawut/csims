<?php
/**
 * ฟอร์ม HTML: บันทึกการตรวจร่างและรายงาน กลุ่มงานตรวจสถานที่เกิดเหตุ ศพฐ.10
 * ใช้เป็นต้นแบบ layout (และ preview) สำหรับแปลงเป็น .docx
 *
 * คาดหวังตัวแปรจาก downloadDraftReviewDocx.php:
 *   $F — array ค่าที่เติมในฟอร์ม
 */

if (!isset($F) || !is_array($F)) {
    http_response_code(400);
    echo 'Missing form data';
    exit;
}

$e = static function ($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

$dot = static function (string $v, int $min = 20): string {
    $v = trim($v);
    if ($v !== '') {
        return $v;
    }
    return str_repeat('.', $min);
};

$cb = static function (bool $on): string {
    return $on ? '☑' : '☐';
};
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>บันทึกการตรวจร่างและรายงาน</title>
<style>
@page { size: A4; margin: 12mm 14mm; }
* { box-sizing: border-box; }
body {
    font-family: "TH Sarabun New", "Sarabun", Tahoma, sans-serif;
    font-size: 16pt;
    color: #000;
    margin: 0;
    padding: 0;
    line-height: 1.25;
}
.page { width: 100%; max-width: 190mm; margin: 0 auto; }
.doc-no { text-align: right; margin-bottom: 4px; }
.title { text-align: center; font-weight: bold; text-decoration: underline; margin: 0 0 2px; line-height: 1.2; }
.info { margin-top: 8px; }
.info .row { margin: 1px 0; }
.dots { display: inline; border-bottom: 1px dotted #000; min-width: 80px; padding: 0 4px; }
.grid {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    table-layout: fixed;
}
.grid td {
    border: 1px solid #000;
    vertical-align: top;
    width: 50%;
    padding: 6px 8px 8px;
    height: 210px;
}
.box-h { font-weight: bold; margin-bottom: 4px; }
.sig-img { display: block; margin: 4px auto 0; max-height: 48px; max-width: 140px; }
.sig-block { text-align: center; margin-top: 8px; }
.sig-block .line { margin: 0; }
.remark-line {
    border-bottom: 1px dotted #000;
    min-height: 1.2em;
    margin: 3px 0;
    padding: 0 2px;
}
.footer-tbl {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    table-layout: fixed;
    font-size: 13pt;
}
.footer-tbl th, .footer-tbl td {
    border: 1px solid #000;
    text-align: center;
    padding: 4px 2px;
    vertical-align: middle;
}
.footer-tbl td { height: 48px; }
</style>
</head>
<body>
<div class="page">
    <div class="doc-no">ที่ <?= $e($dot($F['doc_no_right'], 12)) ?></div>

    <div class="title">บันทึกการตรวจร่างและรายงาน</div>
    <div class="title">กลุ่มงานตรวจสถานที่เกิดเหตุ ศพฐ.10</div>

    <div class="info">
        <div class="row">
            คดี <span class="dots"><?= $e($dot($F['case_name'], 28)) ?></span>
            สภ. <span class="dots"><?= $e($dot($F['station'], 28)) ?></span>
        </div>
        <div class="row">
            สถานที่เกิดเหตุ <span class="dots"><?= $e($dot($F['location'], 50)) ?></span>
        </div>
        <?php if (trim((string)($F['location2'] ?? '')) !== ''): ?>
        <div class="row"><span class="dots" style="display:block;"><?= $e($F['location2']) ?></span></div>
        <?php endif; ?>

        <div class="row">
            วันเวลาที่ทราบเหตุ&nbsp;&nbsp;วันที่ <?= $e($dot($F['know_date'], 12)) ?>
            &nbsp;เวลาประมาณ <?= $e($dot($F['know_time'], 8)) ?> น.
        </div>
        <div class="row">
            วันเวลาที่รับ&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;วันที่ <?= $e($dot($F['recv_date'], 12)) ?>
            &nbsp;เวลา <?= $e($dot($F['recv_time'], 8)) ?> น.
        </div>
        <div class="row">
            วันเวลาที่ตรวจฯ&nbsp;&nbsp;&nbsp;&nbsp;วันที่ <?= $e($dot($F['inspect_date'], 12)) ?>
            &nbsp;เวลาประมาณ <?= $e($dot($F['inspect_time'], 8)) ?> น.
        </div>
        <div class="row">ผู้ตรวจฯ <span class="dots"><?= $e(trim((string)$F['inspector']) !== '' ? $F['inspector'] : $dot('', 40)) ?></span></div>
        <div class="row">ผู้ร่างรายงาน <span class="dots"><?= $e($dot($F['drafter'], 40)) ?></span></div>
    </div>

    <table class="grid">
        <tr>
            <td>
                <div class="box-h">ผู้ร่างรายงาน</div>
                <div>เรียน นวท.(สบ <?= $e($F['drafter_nvt']) ?>) กสก.ศพฐ.10</div>
                <div>เพื่อโปรดพิจารณา ร่างรายงาน</div>
                <div class="sig-block">
                    <?php if ($F['drafter_sig_src'] !== ''): ?>
                        <img class="sig-img" src="<?= $e($F['drafter_sig_src']) ?>" alt="ลายเซ็น">
                    <?php endif; ?>
                    <div class="line">ลงชื่อ <?= $e($dot($F['drafter_sign_line'], 28)) ?></div>
                    <div class="line">(<?= $e($dot($F['drafter'], 28)) ?>)</div>
                    <div class="line">นวท. (สบ <?= $e($F['drafter_nvt']) ?>) <?= $e($F['drafter_position_extra']) ?></div>
                    <div class="line"><?= $e($dot($F['drafter_date'], 18)) ?></div>
                </div>
            </td>
            <td>
                <div class="box-h">ผู้ตรวจร่างรายงาน</div>
                <div>รายการแก้ไขดำเนินการ/ดำเนินการ(ครั้งที่ 1)</div>
                <div><?= $cb(!empty($F['r1_edit'])) ?> แก้ไข</div>
                <div><?= $cb(!empty($F['r1_print'])) ?> พิมพ์รายงาน</div>
                <div class="remark-line"><?= $e($F['r1_remark']) ?></div>
                <div class="remark-line">&nbsp;</div>
                <div class="sig-block">
                    <?php if ($F['r1_sig_src'] !== ''): ?>
                        <img class="sig-img" src="<?= $e($F['r1_sig_src']) ?>" alt="ลายเซ็น">
                    <?php endif; ?>
                    <div class="line">ลงชื่อ <?= $e($dot($F['r1_sign_line'], 28)) ?></div>
                    <div class="line">(<?= $e($dot($F['r1_name'], 28)) ?>)</div>
                    <div class="line">นวท. (สบ <?= $e($F['r1_nvt']) ?>) กสก.ศพฐ.10</div>
                    <div class="line"><?= $e($dot($F['r1_date'], 18)) ?></div>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="box-h">ผู้ตรวจร่างรายงาน</div>
                <div>รายการแก้ไขดำเนินการ/ดำเนินการ(ครั้งที่ 1)</div>
                <div><?= $cb(!empty($F['r2_edit'])) ?> แก้ไข</div>
                <div><?= $cb(!empty($F['r2_print'])) ?> พิมพ์รายงาน</div>
                <div class="remark-line"><?= $e($F['r2_remark']) ?></div>
                <div class="remark-line">&nbsp;</div>
                <div class="sig-block">
                    <?php if ($F['r2_sig_src'] !== ''): ?>
                        <img class="sig-img" src="<?= $e($F['r2_sig_src']) ?>" alt="ลายเซ็น">
                    <?php endif; ?>
                    <div class="line">ลงชื่อ <?= $e($dot($F['r2_sign_line'], 28)) ?></div>
                    <div class="line">(<?= $e($dot($F['r2_name'], 28)) ?>)</div>
                    <div class="line">นวท. (สบ <?= $e($F['r2_nvt']) ?>) กสก.ศพฐ.10</div>
                    <div class="line"><?= $e($dot($F['r2_date'], 18)) ?></div>
                </div>
            </td>
            <td>
                <div class="box-h">ผู้อนุมัติร่างรายงาน</div>
                <div class="remark-line"><?= $e($F['r3_remark']) ?></div>
                <div class="remark-line">&nbsp;</div>
                <div class="sig-block">
                    <?php if ($F['r3_sig_src'] !== ''): ?>
                        <img class="sig-img" src="<?= $e($F['r3_sig_src']) ?>" alt="ลายเซ็น">
                    <?php endif; ?>
                    <div class="line">ลงชื่อ <?= $e($dot($F['r3_sign_line'], 28)) ?></div>
                    <div class="line">(<?= $e($dot($F['r3_name'], 28)) ?>)</div>
                    <div class="line">นวท. (สบ <?= $e($F['r3_nvt'] !== '' ? $F['r3_nvt'] : '4') ?>) กสก.ศพฐ.10</div>
                    <div class="line"><?= $e($dot($F['r3_date'], 18)) ?></div>
                </div>
            </td>
        </tr>
    </table>

    <table class="footer-tbl">
        <tr>
            <th>บันทึกการรับแจ้งเหตุ</th>
            <th>แบบฟอร์ม (Check List)</th>
            <th>บันทึกส่งมอบวัตถุพยาน</th>
            <th>ผ่านการตรวจร่าง</th>
            <th>ผู้ตรวจสอบเอกสาร</th>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
    </table>
</div>
</body>
</html>
