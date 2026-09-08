<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../db_config.php';

// เช็คว่า Login หรือยัง? (Security Check)
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

// ==========================================
// 1. CONFIGURATION & PATHS
// ==========================================
$baseDir = __DIR__;

// ไฟล์ Template (ตรวจสอบ Path ให้ตรงกับโฟลเดอร์ในโปรเจกต์คุณ)
$templatePath = $baseDir . '/templates/equipment_log.docx';
$outputDir    = $baseDir . '/output';
$tempDocxPath = $outputDir . '/temp_log_' . uniqid() . '.docx';

// Validation
if (!file_exists($templatePath)) {
    die("Error: Template not found at $templatePath");
}

// ==========================================
// 2. HELPER FUNCTIONS
// ==========================================

// แปลงวันที่แบบย่อ dd/mm/yy (พ.ศ. 2 หลัก) เช่น 19/02/69
function thaiDateDDMMYY($datetime)
{
    if (empty($datetime) || $datetime == '0000-00-00') return '-';
    $timestamp = strtotime($datetime);

    $d = date('d', $timestamp); // วันที่ 2 หลัก (01-31)
    $m = date('m', $timestamp); // เดือน 2 หลัก (01-12)
    $y = (date('Y', $timestamp) + 543) % 100; // เอาเฉพาะ 2 หลักท้ายของ พ.ศ.

    // เติม 0 ด้านหน้ากรณีปีได้เลขตัวเดียว (เช่น ปี 01-09)
    $y = str_pad($y, 2, '0', STR_PAD_LEFT);

    return "$d/$m/$y";
}

// ==========================================
// 3. FETCH DATA
// ==========================================
$equipment_id = isset($_GET['equipment_id']) ? intval($_GET['equipment_id']) : 0;
$month = $_GET['month'] ?? ''; // รับค่าเดือนจาก Filter
$year  = $_GET['year'] ?? '';  // รับค่าปี (ค.ศ.) จาก Filter

if ($equipment_id <= 0) die("Error: Invalid Equipment ID");

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // 3.1 ดึงข้อมูล Master (ข้อมูลเครื่องมือ)
    $sqlMaster = "SELECT * FROM master_equipment_list WHERE id = ? AND status_delete = 0";
    $stmtMaster = $pdo->prepare($sqlMaster);
    $stmtMaster->execute([$equipment_id]);
    $master = $stmtMaster->fetch(PDO::FETCH_ASSOC);

    if (!$master) die("Error: Equipment data not found.");

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Check)
    if ($user_role !== 'admin') {
        if ($master['department_id'] != $user_dept_id) {
            http_response_code(403);
            die("Access Denied: คุณไม่มีสิทธิ์ออกรายงานประวัติเครื่องมือของหน่วยงานอื่น");
        }
    }

    // 3.2 ดึงข้อมูลประวัติ Log ทั้งหมดของเครื่องมือนี้ เรียงตามวันที่เก่าไปใหม่
    $sqlLogs = "SELECT * FROM trans_equipment_log 
                WHERE equipment_id = ? AND delete_token = 0";

    $params = [$equipment_id];

    // กรองเดือน (ถ้ามี)
    if (!empty($month)) {
        $sqlLogs .= " AND MONTH(log_date) = ? ";
        $params[] = $month;
    }

    // กรองปี (ถ้ามี)
    if (!empty($year)) {
        $sqlLogs .= " AND YEAR(log_date) = ? ";
        $params[] = $year;
    }

    // เรียงลำดับจากเก่าไปใหม่สำหรับรายงาน PDF
    $sqlLogs .= " ORDER BY log_date ASC, id ASC";

    $stmtLogs = $pdo->prepare($sqlLogs);
    $stmtLogs->execute($params);
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// 4. PROCESS TEMPLATE
// ==========================================
try {
    $templateProcessor = new TemplateProcessor($templatePath);

    // --- ส่วนที่ 1: แทนค่า Header (ข้อมูลเครื่องมือ) ---
    $templateProcessor->setValue('asset_no', $master['asset_no'] ?? '-');
    $templateProcessor->setValue('tool_name', $master['tool_name'] ?? '-');
    $templateProcessor->setValue('brand',     $master['brand'] ?? '-');
    $templateProcessor->setValue('model',     $master['model'] ?? '-');
    $templateProcessor->setValue('serial_no', $master['serial_no'] ?? '-');

    // จัดการอุปกรณ์ส่วนควบ (แยกจาก comma)
    $accList = [];
    if (!empty($master['accessories'])) {
        $accList = array_map('trim', explode(',', $master['accessories']));
    }
    $templateProcessor->setValue('acc_1', $accList[0] ?? '-');
    $templateProcessor->setValue('acc_2', $accList[1] ?? '-');
    $templateProcessor->setValue('acc_3', $accList[2] ?? '-');

    $templateProcessor->setValue('date_receive',   thaiDateDDMMYY($master['date_receive']));
    $templateProcessor->setValue('date_start_use', thaiDateDDMMYY($master['date_start_use']));

    // --- ส่วนที่ 2: วนลูปตาราง (ข้อมูลประวัติ) ---
    $rowCount = count($logs);

    if ($rowCount > 0) {
        // สั่งโคลนบรรทัดที่มี Tag ${log_date} ตามจำนวนรายการที่มี
        $templateProcessor->cloneRow('log_date', $rowCount);

        foreach ($logs as $index => $log) {
            $i = $index + 1; // ตัวแปรวนลูปสำหรับระบุแถว เช่น #1, #2

            $templateProcessor->setValue("log_date#$i", thaiDateDDMMYY($log['log_date']));
            $templateProcessor->setValue("detail#$i",   htmlspecialchars($log['maintenance_detail']));
            $templateProcessor->setValue("company#$i",  htmlspecialchars($log['company_name'] ?? '-'));
            $templateProcessor->setValue("officer#$i",  htmlspecialchars($log['officer_name'] ?? '-'));
            $templateProcessor->setValue("remark#$i",   htmlspecialchars($log['remark'] ?? '-'));
        }
    } else {
        // กรณีไม่มีประวัติ (กันพลาด ถ้า User แอบพิม URL เข้ามาเอง)
        $templateProcessor->cloneRow('log_date', 1);
        $templateProcessor->setValue("log_date#1", '-');
        $templateProcessor->setValue("detail#1",   'ไม่มีประวัติการซ่อมบำรุง/สอบเทียบ');
        $templateProcessor->setValue("company#1",  '-');
        $templateProcessor->setValue("officer#1",  '-');
        $templateProcessor->setValue("remark#1",   '-');
    }

    // Save Temp File
    $templateProcessor->saveAs($tempDocxPath);
} catch (Exception $e) {
    die("Error at Process Template: " . $e->getMessage());
}

// ==========================================
// 5. CONVERT TO PDF & OUTPUT
// ==========================================
$command = "libreoffice --headless --convert-to pdf --outdir " . escapeshellarg($outputDir) . " " . escapeshellarg($tempDocxPath);
$output = shell_exec($command . " 2>&1");

$pdfFileName = pathinfo($tempDocxPath, PATHINFO_FILENAME) . '.pdf';
$fullPdfPath = $outputDir . '/' . $pdfFileName;

if (file_exists($fullPdfPath)) {

    // 1. รับค่าจาก Filter
    $month   = $_GET['month'] ?? '';
    $year_en = $_GET['year'] ?? '';
    $year_th = $year_en ? (int)$year_en + 543 : '';

    $fileNamePrefix = "F-CS-18";

    // 2. ระบุตัวตนเครื่องมือ (ใช้อันที่มีค่า: Asset No > Serial No > ID)
    $ident = trim($master['asset_no'] ?: ($master['serial_no'] ?: 'ID-' . $_GET['equipment_id']));

    // 3. กำหนดส่วนชื่อเงื่อนไข (Smart Naming)
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
    // ล้างอักขระพิเศษและช่องว่าง
    $safeName = str_replace([' ', '/', '\\', '[', ']', ':'], '-', $rawName);
    // ยุบขีดซ้ำ และตัดขีดหน้าหลังของแต่ละส่วนก่อนประกอบ (เพื่อป้องกัน -_)
    $safeName = preg_replace('/-+/', '-', $safeName);
    $safeName = str_replace(['-_', '_-'], '_', $safeName);

    $displayName = $fileNamePrefix . "_" . trim($safeName, '_-') . ".pdf";

    header("Cache-Control: no-cache, must-revalidate");
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $displayName . '"');
    header('Content-Length: ' . filesize($fullPdfPath));
    header("Title: " . $displayName);

    readfile($fullPdfPath);

    // ลบไฟล์ขยะ
    @unlink($tempDocxPath);
    @unlink($fullPdfPath);
} else {
    echo "<h1>Error Generating PDF</h1>";
    echo "<p>Command: $command</p>";
    echo "<pre>Output: $output</pre>";
}
