<?php
/**
 * gen_word_report.php - ดาวน์โหลดรายงานเป็น Word (.docx ผ่าน PhpWord)
 *
 *   01 ทรัพย์              → property_report_docx.php
 *   02 ชีวิตในอาคาร        → life_indoor_report_docx.php
 *   02 ชีวิตนอกอาคาร       → life_outdoor_report_docx.php
 *   03 ระเบิดในอาคาร       → bomb_indoor_report_docx.php
 *   03 ระเบิดนอกอาคาร      → bomb_outdoor_report_docx.php
 *   04 เพลิงไหม้           → fire_report_docx.php
 *   05 จราจร               → traffic_report_docx.php
 *   06 ลายนิ้วมือแฝง       → fingerprint_report_docx.php
 *   07 วัตถุพยานที่เกิดเหตุ → scene_evidence_report_docx.php
 *   08 วัตถุพยานบุคคล      → person_evidence_report_docx.php
 *
 * รับ parameter (GET):
 *   incident_id, type (01–08), location, report_no
 *   photos = all  (ค่าเริ่มต้น) เนื้อหา + ภาพประกอบ เหมือนเดิมทุกประการ
 *          | none  เฉพาะเนื้อหา ไม่มีภาพประกอบ
 *          | only  เฉพาะภาพประกอบ แยกไฟล์ และเริ่มนับเลขหน้าที่ 1 ใหม่
 */

require_once __DIR__ . '/word_report_helper.php';
require_once __DIR__ . '/../../db_config.php';

$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;
$type        = isset($_GET['type']) ? preg_replace('/[^0-9]/', '', $_GET['type']) : '';
$location    = isset($_GET['location']) ? $_GET['location'] : '';
$report_no   = isset($_GET['report_no']) ? trim($_GET['report_no']) : '';

// โหมดภาพประกอบ — ไม่ส่งมา = 'all' (พฤติกรรมเดิม)
$photos_mode = isset($_GET['photos']) ? strtolower(trim($_GET['photos'])) : 'all';
if (!in_array($photos_mode, ['all', 'none', 'only'], true)) {
    $photos_mode = 'all';
}

if ($incident_id <= 0) {
    header('Content-Type: text/plain; charset=utf-8');
    die('Error: กรุณาระบุ incident_id');
}

$typeLabelMap = [
    '01' => 'รายงานตรวจสถานที่เกิดเหตุคดีทรัพย์',
    '02' => 'รายงานตรวจสถานที่เกิดเหตุคดีชีวิต',
    '03' => 'รายงานตรวจสถานที่เกิดเหตุคดีระเบิด',
    '04' => 'รายงานตรวจสถานที่เกิดเหตุเพลิงไหม้',
    '05' => 'รายงานตรวจสถานที่เกิดเหตุคดีจราจร',
    '06' => 'รายงานตรวจเก็บลายนิ้วมือแฝง',
    '07' => 'รายงานตรวจเก็บวัตถุพยานที่เกิดเหตุ',
    '08' => 'รายงานตรวจเก็บวัตถุพยานบุคคล',
];
$locLabel = $location === 'indoor' ? ' (ในอาคาร)' : ($location === 'outdoor' ? ' (นอกอาคาร)' : '');
$baseName = ($typeLabelMap[$type] ?? 'รายงาน') . $locLabel;
if ($report_no !== '') {
    $baseName .= ' ' . $report_no;
} else {
    $baseName .= ' ' . $incident_id;
}
$baseName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|', "\n", "\r"], '-', $baseName);

$photoDataUris = [];
if (isset($pdo)) {
    $photoDataUris = wordFetchIncidentPhotos($pdo, $incident_id);
}

// ----- แยกไฟล์เนื้อหา / ภาพประกอบ -----
if (!isset($typeLabelMap[$type])) {
    // เช็คชนิดรายงานก่อนเสมอ ไม่งั้น photos=only จะข้ามการตรวจนี้ไป
    header('Content-Type: text/plain; charset=utf-8');
    die('Error: ไม่พบรูปแบบรายงานสำหรับ type = ' . htmlspecialchars($type));
}

if ($photos_mode === 'only') {
    // ภาคผนวกภาพประกอบเป็นเอกสารใหม่ทั้งฉบับ → เลขหน้าเริ่มที่ 1 ไม่ต่อจากเนื้อหา
    require_once __DIR__ . '/word_photo_appendix.php';
    try {
        wordPhotoAppendixDownload(
            $photoDataUris,
            $baseName . ' (ภาพประกอบ)',
            ($typeLabelMap[$type] ?? 'รายงาน') . $locLabel . ' - ภาพประกอบ'
        );
    } catch (Throwable $e) {
        header('Content-Type: text/plain; charset=utf-8');
        die('Error: ' . $e->getMessage());
    }
    exit;
}

if ($photos_mode === 'none') {
    // ไม่ส่งรูปเข้าไป → ลูปแนบภาพในแต่ละ *_report_docx.php ไม่ทำงาน
    $photoDataUris = [];
    $baseName .= ' (เนื้อหา)';
}

// ----- DOCX ทีละฟอร์ม (แบบทรัพย์) -----
if ($type === '01') {
    require_once __DIR__ . '/property_report_docx.php';
    try {
        downloadPropertyReportDocx($incident_id, $baseName, $photoDataUris);
    } catch (Throwable $e) {
        header('Content-Type: text/plain; charset=utf-8');
        die('Error: ' . $e->getMessage());
    }
    exit;
}

if ($type === '02' && $location !== 'outdoor') {
    require_once __DIR__ . '/life_indoor_report_docx.php';
    try {
        downloadLifeIndoorReportDocx($incident_id, $baseName, $photoDataUris);
    } catch (Throwable $e) {
        header('Content-Type: text/plain; charset=utf-8');
        die('Error: ' . $e->getMessage());
    }
    exit;
}

if ($type === '02' && $location === 'outdoor') {
    require_once __DIR__ . '/life_outdoor_report_docx.php';
    try {
        downloadLifeOutdoorReportDocx($incident_id, $baseName, $photoDataUris);
    } catch (Throwable $e) {
        header('Content-Type: text/plain; charset=utf-8');
        die('Error: ' . $e->getMessage());
    }
    exit;
}

if ($type === '03' && $location !== 'outdoor') {
    require_once __DIR__ . '/bomb_indoor_report_docx.php';
    try {
        downloadBombIndoorReportDocx($incident_id, $baseName, $photoDataUris);
    } catch (Throwable $e) {
        header('Content-Type: text/plain; charset=utf-8');
        die('Error: ' . $e->getMessage());
    }
    exit;
}

if ($type === '03' && $location === 'outdoor') {
    require_once __DIR__ . '/bomb_outdoor_report_docx.php';
    try {
        downloadBombOutdoorReportDocx($incident_id, $baseName, $photoDataUris);
    } catch (Throwable $e) {
        header('Content-Type: text/plain; charset=utf-8');
        die('Error: ' . $e->getMessage());
    }
    exit;
}

if ($type === '04') {
    require_once __DIR__ . '/fire_report_docx.php';
    try {
        downloadFireReportDocx($incident_id, $baseName, $photoDataUris);
    } catch (Throwable $e) {
        header('Content-Type: text/plain; charset=utf-8');
        die('Error: ' . $e->getMessage());
    }
    exit;
}

if ($type === '05') {
    require_once __DIR__ . '/traffic_report_docx.php';
    try {
        downloadTrafficReportDocx($incident_id, $baseName, $photoDataUris);
    } catch (Throwable $e) {
        header('Content-Type: text/plain; charset=utf-8');
        die('Error: ' . $e->getMessage());
    }
    exit;
}

if ($type === '06') {
    require_once __DIR__ . '/fingerprint_report_docx.php';
    try {
        downloadFingerprintReportDocx($incident_id, $baseName, $photoDataUris);
    } catch (Throwable $e) {
        header('Content-Type: text/plain; charset=utf-8');
        die('Error: ' . $e->getMessage());
    }
    exit;
}

if ($type === '07') {
    require_once __DIR__ . '/scene_evidence_report_docx.php';
    try {
        downloadSceneEvidenceReportDocx($incident_id, $baseName, $photoDataUris);
    } catch (Throwable $e) {
        header('Content-Type: text/plain; charset=utf-8');
        die('Error: ' . $e->getMessage());
    }
    exit;
}

if ($type === '08') {
    require_once __DIR__ . '/person_evidence_report_docx.php';
    try {
        downloadPersonEvidenceReportDocx($incident_id, $baseName, $photoDataUris);
    } catch (Throwable $e) {
        header('Content-Type: text/plain; charset=utf-8');
        die('Error: ' . $e->getMessage());
    }
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
die('Error: ไม่พบรูปแบบรายงานสำหรับประเภทคดีนี้');
