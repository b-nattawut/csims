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
// 1. CONFIGURATION
// ==========================================
$baseDir = __DIR__;
$templatePath = $baseDir . '/templates/temperature_record.docx';
$outputDir    = $baseDir . '/output';
$tempDocxPath = $outputDir . '/temp_record_' . uniqid() . '.docx';

// Validation
if (!file_exists($templatePath)) {
    die("Error: Template not found at $templatePath");
}

// รับค่าจาก URL
$device_id = isset($_GET['device_id']) ? intval($_GET['device_id']) : 0;
$month     = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year      = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$time_period = isset($_GET['time_period']) ? trim($_GET['time_period']) : '';

if ($device_id <= 0 || empty($time_period)) {
    die("Error: ข้อมูลเครื่องมือ หรือ รอบเวลาไม่ถูกต้อง");
}

// ==========================================
// 2. FETCH DATA FROM DATABASE
// ==========================================
try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role    = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // 2.1 ดึงข้อมูล Master ของตู้แช่ (ข้อมูลหัวกระดาษ)
    $sqlMaster = "SELECT t1.*, 
                         t_dept.department_name,
                         CONCAT(t4_r1.rank_name,' ',t3_r1.first_name,' ',t3_r1.last_name) AS resp1_name,
                         CONCAT(t4_r2.rank_name,' ',t3_r2.first_name,' ',t3_r2.last_name) AS resp2_name
                  FROM master_temperature_device t1
                  LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id 
                  LEFT JOIN users t2_r1 ON t1.responsible_person_1 = t2_r1.user_id
                  LEFT JOIN user_profile t3_r1 ON t2_r1.user_id = t3_r1.user_id
                  LEFT JOIN user_rank t4_r1 ON t3_r1.rank_id = t4_r1.rank_id
                  LEFT JOIN users t2_r2 ON t1.responsible_person_2 = t2_r2.user_id
                  LEFT JOIN user_profile t3_r2 ON t2_r2.user_id = t3_r2.user_id
                  LEFT JOIN user_rank t4_r2 ON t3_r2.rank_id = t4_r2.rank_id
                  WHERE t1.id = ? AND t1.status_delete = 0";

    $stmtMaster = $pdo->prepare($sqlMaster);
    $stmtMaster->execute([$device_id]);
    $master = $stmtMaster->fetch(PDO::FETCH_ASSOC);

    if (!$master) die("Error: ไม่พบข้อมูลเครื่องวัด");

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Check)
    if ($user_role !== 'admin') {
        if ($master['department_id'] != $user_dept_id) {
            http_response_code(403);
            die("Access Denied: คุณไม่มีสิทธิ์ออกรายงานบันทึกอุณหภูมิของหน่วยงานอื่น");
        }
    }

    // 2.2 ดึงข้อมูลการจดบันทึกอุณหภูมิ กรองตาม เดือน, ปี และ รอบเวลา
    $sqlLogs = "SELECT DAY(t1.record_date) AS log_day, t1.temp_value, t1.status, t1.time_period,
                       TIME_FORMAT(t1.record_time, '%H:%i') AS record_time_show,
                       CONCAT(t3.first_name, ' ', t3.last_name) AS recorded_by_name
                FROM trans_temperature_record t1
                LEFT JOIN users t2 ON t1.recorded_by = t2.user_id
                LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
                LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
                WHERE t1.device_id = ? 
                  AND MONTH(t1.record_date) = ? 
                  AND YEAR(t1.record_date) = ? 
                  AND t1.time_period = ? 
                  AND t1.delete_token = 0";
    $stmtLogs = $pdo->prepare($sqlLogs);
    $stmtLogs->execute([$device_id, $month, $year, $time_period]);
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// 3. PREPARE DATA FOR TABLE (จัดเตรียมข้อมูลลง Array 1-31)
// ==========================================

// หาจำนวนวันที่มีจริงๆ ในเดือน/ปี นั้น (เช่น ก.พ. 2026 จะได้ 28 วัน)
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

$tableData = [];
for ($i = 1; $i <= 31; $i++) {
    if ($i > $daysInMonth) {
        $tableData[$i] = ['temp' => '-', 'status' => '-', 'time' => '-', 'resp' => '-'];
    } else {
        $tableData[$i] = ['temp' => '', 'status' => '', 'time' => '', 'resp' => ''];
    }
}

// เอาข้อมูลจาก Database มายัดลง Array ตามวันที่
// ปล. ถ้ามีการจด 2 รอบ/วัน ในฐานข้อมูล โค้ดนี้จะดึงอันล่าสุด (รอบบ่าย) มาทับอันเก่า (รอบเช้า) 
// เพราะในฟอร์มกระดาษ 1 วันมีแค่ 1 บรรทัด
foreach ($logs as $row) {
    $d = intval($row['log_day']);
    if ($d >= 1 && $d <= 31) {
        $tableData[$d]['temp']   = $row['temp_value'];
        // $tableData[$d]['status_img'] = ($row['status'] == 'ปกติ') ? $iconCheckPath : $iconCrossPath;
        $tableData[$d]['status_val'] = ($row['status'] == 'ปกติ') ? 'pass' : 'fail';

        // เราสามารถเว้นว่าง 'time' ไว้ได้ เพราะมีบอกบนหัวกระดาษแล้ว หรือจะใส่ย้ำไปในตารางด้วยก็ได้
        $tableData[$d]['time']       = $row['record_time_show'];
        $tableData[$d]['resp']   = $row['recorded_by_name'];
    }
}

// ==========================================
// 4. PROCESS TEMPLATE (ตัวแปรที่จะใช้ค่าบน Word)
// ==========================================
try {
    $templateProcessor = new TemplateProcessor($templatePath);

    // 4.1 ข้อมูลส่วนหัวกระดาษ (Header)
    $thaiMonths = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $monthName = $thaiMonths[intval($month)] ?? '-';
    $yearThai  = $year + 543;

    // จัดรูปแบบ Range (Min - Max °C)
    $rangeText = '-';
    if (isset($master['range_min']) && isset($master['range_max'])) {
        $rangeText = $master['range_min'] . ' - ' . $master['range_max'] . ' °C';
    }

    // จัดรูปแบบ Uncertainty (± X °C)
    $uncertaintyText = '-';
    if (!empty($master['measurement_uncertainty'])) {
        // ใส่ ± เพื่อความเป็นมืออาชีพ หรือถ้าไม่ต้องการสามารถเอาออกได้ครับ
        $uncertaintyText = '± ' . $master['measurement_uncertainty'] . ' °C'; 
    }

    $templateProcessor->setValue('dept_name',   empty($master['department_name']) ? '-' : $master['department_name']);
    $templateProcessor->setValue('device_code', empty($master['device_code']) ? '-' : $master['device_code']);
    $templateProcessor->setValue('month',       $monthName);
    $templateProcessor->setValue('year',        $yearThai);
    $templateProcessor->setValue('uncertainty', $uncertaintyText);
    $templateProcessor->setValue('device_range', $rangeText);
    $templateProcessor->setValue('location',    empty($master['location_use']) ? '-' : $master['location_use']);

    // ผู้รับผิดชอบ (เผื่อกรณีดึงข้อมูลมาได้แต่เป็นค่าว่าง)
    $templateProcessor->setValue('resp1',       empty(trim($master['resp1_name'])) ? '-' : $master['resp1_name']);
    $templateProcessor->setValue('resp2',       empty(trim($master['resp2_name'])) ? '-' : $master['resp2_name']);

    $templateProcessor->setValue('time_head',   empty($time_period) ? '-' : $time_period);

    // 4.2 ยัดข้อมูลลงตาราง 31 วัน
    for ($i = 1; $i <= 31; $i++) {
        $dayData = $tableData[$i] ?? []; // ป้องกัน Index error

        $templateProcessor->setValue("d{$i}_temp",   $tableData[$i]['temp']);
        $templateProcessor->setValue("d{$i}_time",   $tableData[$i]['time']);
        $templateProcessor->setValue("d{$i}_resp",   $tableData[$i]['resp']);
        // Logic Checkbox (Unicode)
        $status_val = $dayData['status_val'] ?? '';
        // สร้าง TextRun เพื่อกำหนดสไตล์เฉพาะตัว
        $statusRun = new TextRun();

        if ($status_val === 'pass') {
            // ปรับขนาด Icon เครื่องหมายถูก 
            $statusRun->addText('ปกติ', ['size' => 9, 'bold' => false, 'name' => 'TH Sarabun New']);
        } elseif ($status_val === 'fail') {
            // ปรับขนาด Icon เครื่องหมายผิด
            $statusRun->addText('ผิดปกติ', ['size' => 9, 'bold' => false, 'name' => 'TH Sarabun New']);
        } else {
            // ปรับขนาดตัวขีด (-) ให้เล็กลงเป็นพิเศษเพื่อไม่ให้ดันตาราง
            $val = $dayData['status'] ?? '';
            $statusRun->addText($val, ['size' => 9, 'name' => 'TH Sarabun New']);
        }

        // ใช้ setComplexValue แทน setValue ปกติ
        $templateProcessor->setComplexValue("d{$i}_status", $statusRun);
    }

    $templateProcessor->saveAs($tempDocxPath);
} catch (Exception $e) {
    die("Error Processing Template: " . $e->getMessage());
}

// ==========================================
// 5. CONVERT TO PDF & DOWNLOAD
// ==========================================
$command = "libreoffice --headless --convert-to pdf --outdir " . escapeshellarg($outputDir) . " " . escapeshellarg($tempDocxPath);
$output = shell_exec($command . " 2>&1");

$pdfFileName = pathinfo($tempDocxPath, PATHINFO_FILENAME) . '.pdf';
$fullPdfPath = $outputDir . '/' . $pdfFileName;

if (file_exists($fullPdfPath)) {

    // 1. รับค่ามาเตรียมตั้งชื่อไฟล์ (สำหรับการกด Save)
    $month = $_GET['month'] ?? '';
    $year_en = $_GET['year'] ?? '';
    $year_th = (int)$year_en + 543;
    $time_p = $_GET['time_period'] ?? '';

    // ดึงชื่อเครื่องจากฐานข้อมูลมาใช้อีกครั้งเพื่อให้ชื่อไฟล์แม่นยำ
    $stmt = $pdo->prepare("SELECT device_code FROM master_temperature_device WHERE id = ?");
    $stmt->execute([$_GET['device_id']]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    $dCode = $device ? trim($device['device_code']) : 'Device';

    // 2. ประกอบร่างและทำความสะอาด
    $fileNamePrefix = "F-CS-19";
    $safeTime = str_replace([':', ' '], ['.', ''], $time_p);
    $rawName = "{$dCode}_{$month}-{$year_th}_({$safeTime})";

    // ยุบขีดซ้ำและล้างอักขระพิเศษ
    $cleanName = preg_replace('/-+/', '-', str_replace(['/', '\\'], '-', $rawName));
    $displayName = $fileNamePrefix . "_" . trim($cleanName, '-') . ".pdf";

    header("Cache-Control: no-cache, must-revalidate");
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $displayName . '"');
    header('Content-Length: ' . filesize($fullPdfPath));
    header("Title: " . $displayName);

    readfile($fullPdfPath);

    @unlink($tempDocxPath);
    @unlink($fullPdfPath);
} else {
    echo "<h1>Error Generating PDF</h1>";
    echo "<p>ไม่สามารถสร้างไฟล์ PDF ได้ โปรดตรวจสอบการตั้งค่า LibreOffice</p>";
}
