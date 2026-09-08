<?php
session_start();
// โหลด Composer Autoload สำหรับ PhpWord
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../db_config.php';

// 1. ระบบรักษาความปลอดภัย (Security Check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน (Unauthorized)'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Element\TextRun;

// ==========================================
// 2. CONFIGURATION & PATHS
// ==========================================
$baseDir = __DIR__;

// ไฟล์ Template
$templatePath = $baseDir . '/templates/computer_usage.docx';
$outputDir    = $baseDir . '/output';

// สร้างชื่อไฟล์ชั่วคราว
$tempDocxPath = $outputDir . '/temp_comp_' . uniqid() . '.docx';

// ตรวจสอบความพร้อมของไฟล์
if (!file_exists($templatePath)) die("Error: ไม่พบไฟล์ Template ที่ $templatePath");

// ==========================================
// 3. HELPER FUNCTIONS
// ==========================================
// แปลงวันที่เป็น dd/mm/yy (พ.ศ. 2 หลัก) เช่น 19/03/69
function thaiDateShort($datetime)
{
    if (empty($datetime) || $datetime == '0000-00-00') return '-';
    $timestamp = strtotime($datetime);
    $d = date('d', $timestamp);
    $m = date('m', $timestamp);
    $y = (date('Y', $timestamp) + 543) % 100;
    return "$d/$m/" . str_pad($y, 2, '0', STR_PAD_LEFT);
}

// ==========================================
// 4. FETCH DATA
// ==========================================
$computer_id = isset($_GET['computer_id']) ? intval($_GET['computer_id']) : 0;
$month = $_GET['month'] ?? ''; // รับค่าเดือน (1-12)
$year  = $_GET['year'] ?? '';  // รับค่าปี (ค.ศ.)

if ($computer_id <= 0) die("Error: ID คอมพิวเตอร์ไม่ถูกต้อง");

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // 4.1 ดึงข้อมูล Master Computer
    $sqlMaster = "SELECT 
                    t1.computer_asset_no, 
                    t1.computer_brand,
                    t1.computer_model,
                    t1.department_id,
                    CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS responsible_name
                  FROM master_computer_list t1
                  LEFT JOIN users r_usr ON t1.responsible_id = r_usr.user_id
                  LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
                  LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
                  WHERE t1.id = ? AND t1.status_delete = 0";

    $stmtMaster = $pdo->prepare($sqlMaster);
    $stmtMaster->execute([$computer_id]);
    $master = $stmtMaster->fetch(PDO::FETCH_ASSOC);

    if (!$master) die("Error: ไม่พบข้อมูลคอมพิวเตอร์ในระบบ");

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Check)
    if ($user_role !== 'admin') {
        if ($master['department_id'] != $user_dept_id) {
            http_response_code(403);
            die("Access Denied: คุณไม่มีสิทธิ์ออกรายงานของเครื่องคอมพิวเตอร์ในหน่วยงานอื่น");
        }
    }

    // 4.2 ดึงข้อมูลประวัติการใช้งาน (Logs) เรียงจากเก่าไปใหม่เพื่อลงตาราง
    $sqlLogs = "SELECT 
                    t.usage_date, 
                    t.report_no, 
                    t.condition_status, 
                    t.remark,
                    CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS used_by_name
                FROM trans_computer_usage t 
                LEFT JOIN users r_usr ON t.used_by = r_usr.user_id
                LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
                LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
                WHERE t.computer_id = ? AND t.status_delete = 0";

    // เริ่มต้น Parameters
    $params = [$computer_id];

    // เพิ่มเงื่อนไขกรองเดือน (ถ้ามี)
    if (!empty($month)) {
        $sqlLogs .= " AND MONTH(t.usage_date) = ? ";
        $params[] = $month;
    }

    // เพิ่มเงื่อนไขกรองปี (ถ้ามี)
    if (!empty($year)) {
        $sqlLogs .= " AND YEAR(t.usage_date) = ? ";
        $params[] = $year;
    }

    // เรียงลำดับจากเก่าไปใหม่ เพื่อให้ลงตาราง PDF ได้สวยงามตามลำดับเวลา
    $sqlLogs .= " ORDER BY t.usage_date ASC, t.id ASC";

    $stmtLogs = $pdo->prepare($sqlLogs);
    $stmtLogs->execute($params);
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// 5. PROCESS TEMPLATE
// ==========================================
try {
    $templateProcessor = new TemplateProcessor($templatePath);

    // --- ส่วนที่ 1: Header ---
    $brandModel = trim(($master['computer_brand'] ?? '') . ' ' . ($master['computer_model'] ?? ''));
    $templateProcessor->setValue('brand_model', htmlspecialchars($brandModel ?: '-'));
    $templateProcessor->setValue('asset_no', htmlspecialchars($master['computer_asset_no'] ?? '-'));
    $templateProcessor->setValue('responsible_name', htmlspecialchars($master['responsible_name'] ?? '-'));

    // --- ส่วนที่ 2: Table Rows ---
    $rowCount = count($logs);

    // ตั้งค่ามาตรฐานสำหรับ Unicode Checkmark
    $checkStyle = ['size' => 20, 'bold' => true, 'name' => 'Arial'];
    $checkmark  = '✓';

    if ($rowCount > 0) {
        $templateProcessor->cloneRow('usage_date', $rowCount);

        foreach ($logs as $index => $log) {
            $i = $index + 1;

            $templateProcessor->setValue("usage_date#$i", thaiDateShort($log['usage_date']));
            $templateProcessor->setValue("report_no#$i",  htmlspecialchars($log['report_no']));

            // Logic การใส่เครื่องหมายถูกในช่อง "ปกติ" หรือ "ไม่ปกติ"
            // (อิงตามค่า ENUM ใน DB: 'ปกติ', 'ไม่ปกติ / พบปัญหา')
            // เตรียม TextRun ใหม่ทุกรอบเพื่อป้องกันสไตล์ทับกัน
            $runNormal   = new TextRun();
            $runAbnormal = new TextRun();

            // Logic ตรวจสอบสถานะ (ENUM: 'ปกติ', 'ไม่ปกติ')
            $status = trim($log['condition_status'] ?? '');
            if ($status === 'ปกติ') {
                $runNormal->addText($checkmark, $checkStyle);
            } else if ($status === 'ไม่ปกติ' || $status === 'ไม่ปกติ / พบปัญหา') {
                $runAbnormal->addText($checkmark, $checkStyle);
            }

            // ใช้ setComplexValue แทน setImageValue เดิม
            $templateProcessor->setComplexValue("cond_n#$i", $runNormal);
            $templateProcessor->setComplexValue("cond_a#$i", $runAbnormal);

            $templateProcessor->setValue("used_by#$i", htmlspecialchars($log['used_by_name'] ?? '-'));
            $templateProcessor->setValue("remark#$i",  htmlspecialchars($log['remark'] ?? ''));
        }
    } else {
        // กรณีไม่มีประวัติเลย
        $templateProcessor->cloneRow('usage_date', 1);
        $templateProcessor->setValue("usage_date#1", "-");
        $templateProcessor->setValue("report_no#1",  "ไม่มีประวัติการใช้งาน");
        $templateProcessor->setComplexValue("cond_n#1", new TextRun());
        $templateProcessor->setComplexValue("cond_a#1", new TextRun());
        $templateProcessor->setValue("used_by#1", "-");
        $templateProcessor->setValue("remark#1", "");
    }

    $templateProcessor->saveAs($tempDocxPath);
} catch (Exception $e) {
    die("Template Error: " . $e->getMessage());
}

// ==========================================
// 6. CONVERT TO PDF & OUTPUT
// ==========================================
$command = "libreoffice --headless --convert-to pdf --outdir " . escapeshellarg($outputDir) . " " . escapeshellarg($tempDocxPath);
shell_exec($command . " 2>&1");

$pdfFileName = pathinfo($tempDocxPath, PATHINFO_FILENAME) . '.pdf';
$fullPdfPath = $outputDir . '/' . $pdfFileName;

if (file_exists($fullPdfPath)) {
    // 1. รับค่าจาก Filter
    $month    = $_GET['month'] ?? '';
    $year_en  = $_GET['year'] ?? '';
    $year_th  = $year_en ? (int)$year_en + 543 : '';
    $computer_id = $_GET['computer_id'] ?? 0;

    $fileNamePrefix = "F-CS-23";

    // 2. ระบุตัวตน (Asset No > Serial No > ID)
    $ident = trim($master['computer_asset_no'] ?: ($master['computer_serial_no'] ?: 'ID-' . $computer_id));

    // 3. กำหนดส่วนชื่อเงื่อนไข (Smart Naming รองรับเลือกโดดๆ)
    if (!empty($month) && !empty($year_th)) {
        $namePart = "{$month}-{$year_th}";
    } elseif (!empty($year_th)) {
        $namePart = "Annual-{$year_th}";
    } elseif (!empty($month)) {
        $namePart = "Monthly-{$month}";
    } else {
        $namePart = "Full-History";
    }

    // 4. Clean ชื่อไฟล์ (จัดการขีดซ้ำและขีดส่วนเกิน)
    $rawName = "{$ident}_{$namePart}";

    // ล้างอักขระพิเศษ
    $safeName = str_replace([' ', '/', '\\', '[', ']', ':'], '-', $rawName);

    // ยุบขีดซ้ำ และตัดขีดหน้าหลังของแต่ละส่วนก่อนประกอบ (สูตรแก้ -_ )
    $safeName = preg_replace('/-+/', '-', $safeName);
    $safeName = str_replace(['-_', '_-'], '_', $safeName);

    $displayName = $fileNamePrefix . "_" . trim($safeName, '_-') . ".pdf";

    header("Cache-Control: no-cache, must-revalidate");
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $displayName . '"');
    header('Content-Length: ' . filesize($fullPdfPath));
    header("Title: " . $displayName);

    readfile($fullPdfPath);

    // ล้างไฟล์ชั่วคราว
    @unlink($tempDocxPath);
    @unlink($fullPdfPath);
} else {
    http_response_code(500);
    echo "<h1>Error Generating PDF</h1>";
    echo "<p><b>Command:</b> $command</p>";
    echo "<pre><b>Output:</b> $output</pre>";
}
