<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../db_config.php';

// 1. เช็คสิทธิ์การเข้าใช้งาน
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

$templatePath = $baseDir . '/templates/maintenance_plan.docx';
$outputDir    = $baseDir . '/output';
$tempDocxPath = $outputDir . '/temp_plan_' . uniqid() . '.docx';

if (!file_exists($templatePath)) {
    die("Error: ไม่พบไฟล์ Template ที่ $templatePath");
}

// ==========================================
// 2. FETCH DATA
// ==========================================
$header_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($header_id <= 0) die("Error: ไม่พบรหัสแผน");

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // 2.1 ดึงข้อมูล Header
    $sqlHeader = "SELECT h.*, md.department_name
                  FROM trans_maintenance_plan_header h
                  LEFT JOIN master_departments md ON h.department_id = md.id
                  WHERE h.id = ? AND h.delete_token = 0";
    $stmtH = $pdo->prepare($sqlHeader);
    $stmtH->execute([$header_id]);
    $header = $stmtH->fetch(PDO::FETCH_ASSOC);

    if (!$header) die("Error: ไม่พบข้อมูลแผน หรือแผนอาจถูกลบไปแล้ว");

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Check)
    if ($user_role !== 'admin') {
        if ($header['department_id'] != $user_dept_id) {
            http_response_code(403);
            die("Access Denied: คุณไม่มีสิทธิ์ออกรายงานแผนงานของหน่วยงานอื่น");
        }
    }

    // 2.2 ดึงข้อมูล Detail (รายการเครื่องมือ)
    // คล้ายกับ getEquipmentChecklist แต่เราดึงเฉพาะตัวที่ "ถูกติ๊กแผน" หรือ "มีเขียนหมายเหตุ/ผู้รับผิดชอบ"
    // เพื่อไม่ให้ตารางใน PDF ยาวเกินไปโดยไม่จำเป็น
    $sqlDetail = "SELECT 
                    e.tool_name, 
                    e.asset_no, 
                    e.serial_no, 
                    e.brand,
                    d.m1, d.m2, d.m3, d.m4, d.m5, d.m6, 
                    d.m7, d.m8, d.m9, d.m10, d.m11, d.m12,
                    
                    -- สร้างชื่อผู้ดูแลจาก Master
                    CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS responsible_name,
                    
                    d.remark
                  FROM trans_maintenance_plan_detail d
                  JOIN master_equipment_list e ON d.equipment_id = e.id
                  LEFT JOIN users r_usr ON e.responsible_id = r_usr.user_id
                  LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
                  LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
                  WHERE d.header_id = ? 
                  ORDER BY d.id ASC";

    $stmtD = $pdo->prepare($sqlDetail);
    $stmtD->execute([$header_id]);
    $details = $stmtD->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// 3. PROCESS TEMPLATE (PHPWord)
// ==========================================
try {
    $templateProcessor = new TemplateProcessor($templatePath);

    // --- แทนค่าส่วนหัวกระดาษ ---
    $templateProcessor->setValue('plan_year',       $header['plan_year']);
    $templateProcessor->setValue('group_name',      $header['group_name'] ?: '-');
    $templateProcessor->setValue('department_name', $header['department_name'] ?: '-');

    // --- วนลูปสร้างตาราง ---
    $rowCount = count($details);

    // ตั้งค่ามาตรฐานสำหรับ Unicode Checkmark
    $checkStyle = ['size' => 18, 'bold' => true, 'name' => 'Arial'];
    $checkmark  = '✓';

    if ($rowCount > 0) {
        $templateProcessor->cloneRow('row_num', $rowCount);

        foreach ($details as $index => $row) {
            $i = $index + 1; // ลำดับแถวใน Word (เริ่มที่ 1)

            $toolText = $row['tool_name'];
            if (!empty($row['asset_no'])) {
                $toolText .= " (" . $row['asset_no'] . ")"; // ใส่ \n ขึ้นบรรทัดใหม่
            }

            $templateProcessor->setValue("row_num#$i",   $i);
            $templateProcessor->setValue("tool_name#$i", htmlspecialchars($toolText));
            $templateProcessor->setValue("serial_no#$i", htmlspecialchars($row['serial_no'] ?: '-'));
            $templateProcessor->setValue("brand#$i",     htmlspecialchars($row['brand'] ?: '-'));

            // วนลูปเดือน 1-12
            for ($m = 1; $m <= 12; $m++) {
                $monthRun = new TextRun();

                if ($row["m{$m}"] == 1) {
                    // ถ้ามีแผนในเดือนนั้น ให้ใส่เครื่องหมาย ✓
                    $monthRun->addText($checkmark, $checkStyle);
                }

                // ใช้ setComplexValue แทน setImageValue เดิม
                $templateProcessor->setComplexValue("m{$m}#$i", $monthRun);
            }
            $resp_name = trim($row['responsible_name']) ? trim($row['responsible_name']) : '-';
            $templateProcessor->setValue("responsible#$i", htmlspecialchars($resp_name));
            $templateProcessor->setValue("remark#$i",      htmlspecialchars($row['remark'] ?: '-'));
        }
    } else {
        // กรณีไม่มีรายการเครื่องมือในแผนเลย
        $templateProcessor->cloneRow('row_num', 1);
        $templateProcessor->setValue("row_num#1",   '-');
        $templateProcessor->setValue("tool_name#1", 'ไม่มีรายการเครื่องมือ');
        $templateProcessor->setValue("serial_no#1", '-');
        $templateProcessor->setValue("brand#1",     '-');
        for ($m = 1; $m <= 12; $m++) {
            $templateProcessor->setComplexValue("m{$m}#1", new TextRun());
        }
        $templateProcessor->setValue("responsible#1", '-');
        $templateProcessor->setValue("remark#1",      '-');
    }

    // บันทึกไฟล์ Temp เป็น .docx
    $templateProcessor->saveAs($tempDocxPath);
} catch (Exception $e) {
    die("Error at Process Template: " . $e->getMessage());
}

// ==========================================
// 4. CONVERT TO PDF & OUTPUT (LibreOffice)
// ==========================================
// คำสั่ง LibreOffice สำหรับแปลงไฟล์ (ตรวจสอบ Path บนเซิร์ฟเวอร์ของคุณด้วย)
$command = "libreoffice --headless --convert-to pdf --outdir " . escapeshellarg($outputDir) . " " . escapeshellarg($tempDocxPath);
$output = shell_exec($command . " 2>&1");

$pdfFileName = pathinfo($tempDocxPath, PATHINFO_FILENAME) . '.pdf';
$fullPdfPath = $outputDir . '/' . $pdfFileName;

if (file_exists($fullPdfPath)) {
    // 1. ดึงข้อมูลประกอบชื่อไฟล์ (จากตัวแปร $header ที่คุณมีอยู่แล้ว)
    $planYear = $header['plan_year'];
    $groupName = trim($header['group_name']);
    $deptName = trim($header['department_name']); 

    $fileNamePrefix = "F-CS-20";

    $safeGroup = str_replace([' ', '/', '\\', '[', ']', '(', ')'], '-', $groupName);
    $safeDept  = str_replace([' ', '/', '\\', '[', ']', '(', ')'], '-', $deptName);
    $rawName = "{$planYear}_{$safeGroup}_{$safeDept}";

    // ยุบขีดซ้ำ และล้างขีดหน้า Underscore (ใช้สูตรเดิมที่คุยกัน)
    $cleanName = preg_replace('/-+/', '-', $rawName);
    $cleanName = str_replace(['-_', '_-'], '_', $cleanName);

    $displayName = $fileNamePrefix . "_" . trim($cleanName, '_-') . ".pdf";

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
