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
use PhpOffice\PhpWord\Element\TextRun;

// ==========================================
// 1. CONFIGURATION & PATHS
// ==========================================
$baseDir = __DIR__;

// ไฟล์ Template 
$templatePath = $baseDir . '/templates/equipment_usage.docx';
$outputDir    = $baseDir . '/output';

$tempDocxPath = $outputDir . '/temp_usage_' . uniqid() . '.docx';

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

    $d = date('d', $timestamp);
    $m = date('m', $timestamp);
    $y = (date('Y', $timestamp) + 543) % 100;

    $y = str_pad($y, 2, '0', STR_PAD_LEFT);

    return "$d/$m/$y";
}

// ==========================================
// 3. FETCH DATA
// ==========================================
$equipment_id = isset($_GET['equipment_id']) ? intval($_GET['equipment_id']) : 0;
$month = isset($_GET['month']) ? $_GET['month'] : '';
$year  = isset($_GET['year']) ? $_GET['year'] : '';

if ($equipment_id <= 0) die("Error: Invalid Equipment ID");

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // 3.1 ดึงข้อมูล Master (ชื่อเครื่อง, เลขชี้บ่ง, ผู้ดูแล, หน่วยงาน) เพื่อตรวจสอบสิทธิ์
    $sqlMaster = "SELECT 
                    t1.tool_name, 
                    t1.asset_no,
                    t1.department_id,
                    CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS responsible_name
                  FROM master_equipment_list t1
                  LEFT JOIN users r_usr ON t1.responsible_id = r_usr.user_id
                  LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
                  LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
                  WHERE t1.id = ? AND t1.status_delete = 0";
    $stmtMaster = $pdo->prepare($sqlMaster);
    $stmtMaster->execute([$equipment_id]);
    $master = $stmtMaster->fetch(PDO::FETCH_ASSOC);

    if (!$master) die("Error: Equipment data not found.");

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Check)
    if ($user_role !== 'admin') {
        if ($master['department_id'] != $user_dept_id) {
            http_response_code(403);
            die("Access Denied: คุณไม่มีสิทธิ์ออกรายงานของเครื่องมือในหน่วยงานอื่น");
        }
    }

    // 3.2 ดึงข้อมูลประวัติการใช้งาน (Usage) ของเครื่องมือนี้ เรียงตามวันที่เก่าไปใหม่
    $sqlLogs = "SELECT 
                    t.usage_date, 
                    t.report_no, 
                    t.condition_status, 
                    t.remark,
                    CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS used_by_name
                FROM trans_equipment_usage t 
                LEFT JOIN users r_usr ON t.used_by = r_usr.user_id
                LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
                LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
                WHERE t.equipment_id = ? AND t.delete_token = 0 ";

    $params = [$equipment_id];

    // กรองตามเดือน (ถ้ามี)
    if (!empty($month)) {
        $sqlLogs .= " AND MONTH(t.usage_date) = ? ";
        $params[] = $month;
    }

    // กรองตามปี (ถ้ามี)
    if (!empty($year)) {
        $sqlLogs .= " AND YEAR(t.usage_date) = ? ";
        $params[] = $year;
    }

    $sqlLogs .= " ORDER BY t.usage_date ASC, t.id ASC"; // รายงานควรเรียงจากเก่าไปใหม่

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

    // --- ส่วนที่ 1: แทนค่า Header (ข้อมูลอ้างอิงด้านบนฟอร์ม) ---
    $templateProcessor->setValue('tool_name', htmlspecialchars($master['tool_name'] ?? '-'));
    $templateProcessor->setValue('asset_no', htmlspecialchars($master['asset_no'] ?? '-'));
    $templateProcessor->setValue('responsible_name', htmlspecialchars($master['responsible_name'] ?? '-'));

    // --- ส่วนที่ 2: วนลูปตาราง (ข้อมูลการใช้งาน) ---
    $rowCount = count($logs);

    // ตั้งค่ามาตรฐานไว้ก่อน (เปลี่ยนที่เดียว มีผลทั้งหมด)
    $checkStyle = ['size' => 20, 'bold' => true, 'name' => 'TH SarabunPSK'];
    $checkmark  = '✓';

    if ($rowCount > 0) {
        // สั่งโคลนบรรทัดที่มี Tag ${usage_date} ตามจำนวนรายการที่มี
        $templateProcessor->cloneRow('usage_date', $rowCount);

        foreach ($logs as $index => $log) {
            $i = $index + 1; // ลำดับแถว

            $templateProcessor->setValue("usage_date#$i", thaiDateDDMMYY($log['usage_date']));
            $templateProcessor->setValue("report_no#$i",  htmlspecialchars($log['report_no']));

            // เตรียม TextRun สำหรับช่อง "ปกติ" และ "ไม่ปกติ"
            $runNormal   = new TextRun();
            $runAbnormal = new TextRun();

            // จัดการ Logic เครื่องหมาย Unicode
            if ($log['condition_status'] === 'ปกติ') {
                $runNormal->addText($checkmark, $checkStyle);
            } else if ($log['condition_status'] === 'ไม่ปกติ') {
                $runAbnormal->addText($checkmark, $checkStyle);
            }

            // ใช้ setComplexValue เพื่อส่งสไตล์ Unicode เข้าไปในตารางที่โคลนมา
            $templateProcessor->setComplexValue("cond_n#$i", $runNormal);
            $templateProcessor->setComplexValue("cond_a#$i", $runAbnormal);

            $templateProcessor->setValue("used_by#$i",    htmlspecialchars($log['used_by_name'] ?? '-'));
            $templateProcessor->setValue("remark#$i",     htmlspecialchars($log['remark'] ?? ''));
        }
    } else {
        // กรณีเครื่องมือนี้ยังไม่มีประวัติการใช้งานเลย (สร้างแถวเปล่า 1 แถว)
        $templateProcessor->cloneRow('usage_date', 1);
        $templateProcessor->setValue("usage_date#1", '-');
        $templateProcessor->setValue("report_no#1",  'ไม่มีประวัติการใช้งาน');
        $templateProcessor->setComplexValue("cond_n#1", new TextRun());
        $templateProcessor->setComplexValue("cond_a#1", new TextRun());
        $templateProcessor->setValue("used_by#1",    '-');
        $templateProcessor->setValue("remark#1",     '');
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
    $month    = $_GET['month'] ?? '';
    $year_en  = $_GET['year'] ?? '';
    $year_th  = $year_en ? (int)$year_en + 543 : '';
    $equip_id = $_GET['equipment_id'] ?? 0;

    $fileNamePrefix = "F-CS-24";

    // 2. ระบุตัวตนเครื่องมือ (Asset No > Serial No > ID)
    $ident = trim($master['asset_no'] ?: ($master['serial_no'] ?: 'ID-' . $equip_id));

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

    // 4. Clean ชื่อไฟล์ (ยุบขีดซ้ำ และแก้ปัญหา -_ )
    $rawName = "{$ident}_{$namePart}";
    
    // ล้างอักขระพิเศษและช่องว่าง
    $safeName = str_replace([' ', '/', '\\', '[', ']', ':', '(', ')'], '-', $rawName);
    
    // ยุบขีดที่ซ้ำกัน และลบขีดที่อยู่หน้าหรือหลังขีดล่าง (_)
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
