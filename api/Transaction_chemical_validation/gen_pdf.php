<?php
session_start();
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

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Element\TextRun;

// ==========================================
// 1. CONFIGURATION & PATHS
// ==========================================
$baseDir = __DIR__;
$templatePath = $baseDir . '/templates/chemical_validation_report.docx';
$outputDir    = $baseDir . '/output';
$tempDocxPath = $outputDir . '/temp_chem_val_' . uniqid() . '.docx';

if (!file_exists($templatePath)) die("Error: ไม่พบไฟล์ Template");

// ==========================================
// 2. HELPER FUNCTIONS
// ==========================================
// แปลงวันที่เป็น dd/mm/yy (พ.ศ. 2 หลัก) เช่น 19/03/69
function thaiDateShort($date)
{
    if (empty($date) || $date == '0000-00-00') return '-';
    $ts = strtotime($date);
    return date('d/m/', $ts) . ((date('Y', $ts) + 543) % 100);
}

// ==========================================
// 3. RECEIVE FILTERS & BUILD QUERY (ตรรกะเดียวกับ Search API)
// ==========================================
// เปลี่ยนจาก $_POST เป็น $_GET เพื่อรับค่าจาก window.open
$val_start    = $_GET['filter_val_start'] ?? '';
$val_end      = $_GET['filter_val_end'] ?? '';
$chem_id      = $_GET['filter_chemical_id'] ?? '';
$filter_dept_id     = $_GET['filter_department_id'] ?? '';
$tester_id    = $_GET['filter_tester'] ?? '';
$res_acid     = $_GET['filter_res_acid_base'] ?? '';
$res_blood    = $_GET['filter_res_blood'] ?? '';
$is_verified  = $_GET['filter_is_verified'] ?? '';

// ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน (เพิ่มเช็ค is_active = 1)
$stmtUser = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
$stmtUser->execute([$_SESSION['user_id']]);
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    http_response_code(403);
    die("Access Denied: ไม่พบข้อมูลผู้ใช้งาน หรือบัญชีถูกระงับ");
}

$user_role    = strtolower($userData['role'] ?? '');
$user_dept_id = $userData['department_id'] ?? null;

$where = " WHERE t1.status_delete = 0 ";
$params = [];

// ควบคุมสิทธิ์การเข้าถึงข้อมูลตามหน่วยงานอย่างรัดกุม (Department Access Control)
if ($user_role !== 'admin') {
    // ถ้า User ทั่วไปแอบส่ง filter_department_id ของหน่วยงานอื่นมา -> ปฏิเสธการสร้าง PDF ทันที
    if (!empty($filter_dept_id) && $filter_dept_id != $user_dept_id) {
        http_response_code(403);
        die("Access Denied: คุณไม่มีสิทธิ์ออกรายงาน PDF ข้อมูลของหน่วยงานอื่น");
    }
    // บังคับล็อกให้ดึงเฉพาะหน่วยงานของตนเองเท่านั้น
    $filter_dept_id = $user_dept_id;
}

if (!empty($filter_dept_id)) {
    $where .= " AND (
        SELECT mcl_sub.department_id 
        FROM preparation_ingredients pi_sub
        JOIN chemical_inventory inv_sub ON pi_sub.inventory_id = inv_sub.id
        JOIN master_chemical_list mcl_sub ON inv_sub.chemical_id = mcl_sub.id
        WHERE pi_sub.prep_id = t2.id AND pi_sub.status_delete = 0 
        LIMIT 1
    ) = ? ";
    $params[] = $filter_dept_id;
}

if (!empty($val_start)) {
    $where .= " AND t1.test_date >= ? ";
    $params[] = $val_start;
}
if (!empty($val_end)) {
    $where .= " AND t1.test_date <= ? ";
    $params[] = $val_end;
}
if (!empty($chem_id)) {
    $where .= " AND t2.id IN (
                    SELECT DISTINCT pi_f.prep_id 
                    FROM preparation_ingredients pi_f 
                    JOIN chemical_inventory inv_f ON pi_f.inventory_id = inv_f.id 
                    WHERE inv_f.chemical_id = ? AND pi_f.status_delete = 0
                ) ";
    $params[] = $chem_id;
}
if (!empty($tester_id)) {
    $where .= " AND t1.tester_id = ? ";
    $params[] = $tester_id;
}
if (!empty($res_acid)) {
    $where .= " AND t1.res_acid_base = ? ";
    $params[] = $res_acid;
}
if (!empty($res_blood)) {
    $where .= " AND t1.res_blood = ? ";
    $params[] = $res_blood;
}
if ($is_verified !== '') {
    $where .= " AND t1.is_verified = ? ";
    $params[] = $is_verified;
}

// ==========================================
// 4. FETCH DATA
// ==========================================
try {
    $sql = "SELECT 
                t1.test_date, t1.res_acid_base, t1.res_blood,
                t2.prep_name, t2.prep_date, t2.expiry_date,

                -- มัดก้อนข้อมูลส่งออกไปให้ระบบประมวลผล Word
                GROUP_CONCAT(DISTINCT t3.lot_number SEPARATOR ', ') AS source_lot,
                GROUP_CONCAT(DISTINCT t4.chemical_name SEPARATOR ' + ') AS chemical_name, 
                GROUP_CONCAT(DISTINCT t4.chemical_brand SEPARATOR ', ') AS chemical_brand,
                CONCAT(IFNULL(r1.rank_name, ''), ' ', p1.first_name, ' ', p1.last_name) AS tester_fullname,
                CONCAT(IFNULL(r2.rank_name, ''), ' ', p2.first_name, ' ', p2.last_name) AS verifier_fullname,
                t1.verifier_signature -- สำหรับเช็คว่าเซ็นหรือยัง
            FROM trans_chemical_validation t1 
            INNER JOIN chemical_preparation t2 ON t1.prep_id = t2.id
            -- ดึงผ่านประวัติตารางกลางสูตรผสมย่อย
            LEFT JOIN preparation_ingredients pi ON t2.id = pi.prep_id AND pi.status_delete = 0
            LEFT JOIN chemical_inventory t3 ON pi.inventory_id = t3.id
            LEFT JOIN master_chemical_list t4 ON t3.chemical_id = t4.id
            LEFT JOIN user_profile p1 ON t1.tester_id = p1.user_id
            LEFT JOIN user_rank r1 ON p1.rank_id = r1.rank_id
            LEFT JOIN user_profile p2 ON t1.verifier_id = p2.user_id
            LEFT JOIN user_rank r2 ON p2.rank_id = r2.rank_id
            $where 
            GROUP BY 
            t1.id, 
            t1.test_date, t1.res_acid_base, t1.res_blood, t1.verifier_signature, 
            t1.tester_id, t1.verifier_id, t1.create_by, t1.update_by, t1.create_date, t1.update_date, t1.status_delete, t1.prep_id, t1.verifier_signature_date,
            t2.prep_name, t2.prep_date, t2.expiry_date,
            r1.rank_name, p1.first_name, p1.last_name,
            r2.rank_name, p2.first_name, p2.last_name
            ORDER BY t1.test_date ASC, t1.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// 5. PROCESS TEMPLATE
// ==========================================

try {
    $templateProcessor = new TemplateProcessor($templatePath);
    $count = count($rows);

    // ตั้งค่ามาตรฐานสำหรับ Unicode Checkmark
    $checkStyle = ['size' => 20, 'bold' => true, 'name' => 'Arial'];
    $checkmark  = '✓';

    if ($count > 0) {
        $templateProcessor->cloneRow('test_date', $count);
        $sigBaseDir = __DIR__ . '/../../uploads/signatures/';

        foreach ($rows as $index => $row) {
            $i = $index + 1;

            // ข้อมูลวันที่และรายละเอียดสาร
            $templateProcessor->setValue("test_date#$i", thaiDateShort($row['test_date']));
            $templateProcessor->setValue("chem_name#$i", $row['prep_name']);
            $templateProcessor->setValue("chem_brand#$i", ($row['chemical_brand'] ?: '-'));
            $templateProcessor->setValue("expiry_date#$i", thaiDateShort($row['expiry_date']));
            $templateProcessor->setValue("prep_date#$i", thaiDateShort($row['prep_date']));

            // ผู้ดำเนินการ
            $templateProcessor->setValue("tester#$i", $row['tester_fullname']);
            $sigFileName = $row['verifier_signature'];
            $sigPath = $sigBaseDir . $sigFileName;

            // ถ้ามีการเซ็นแล้ว และไฟล์มีอยู่จริง
            if (!empty($sigFileName) && file_exists($sigPath)) {
                // แทรกรูปภาพลายเซ็น (ปรับขนาดให้เล็กลงพอดีช่อง)
                $templateProcessor->setImageValue("verifier#$i", [
                    'path' => $sigPath,
                    'width' => 80,   // ปรับขนาดตามความเหมาะสมของตาราง
                    'height' => 40,
                    'ratio' => true
                ]);
            } else {
                // ถ้ายังไม่เซ็น ให้แสดงเป็นชื่อผู้ทวนสอบ (ตัวจางๆ) หรือเว้นว่าง
                $templateProcessor->setValue("verifier#$i", $row['verifier_fullname'] . " \n(รอลงนาม)");
            }

            // --- จัดการส่วน Unicode Checkbox (Pass/Fail) ---

            // เตรียม TextRun สำหรับ Acid/Base
            $runAcidP = new TextRun();
            $runAcidF = new TextRun();
            if ($row['res_acid_base'] == 'pass') {
                $runAcidP->addText($checkmark, $checkStyle);
            } elseif ($row['res_acid_base'] == 'fail') {
                $runAcidF->addText($checkmark, $checkStyle);
            }
            $templateProcessor->setComplexValue("acid_p#$i", $runAcidP);
            $templateProcessor->setComplexValue("acid_f#$i", $runAcidF);

            // เตรียม TextRun สำหรับ Blood
            $runBloodP = new TextRun();
            $runBloodF = new TextRun();
            if ($row['res_blood'] == 'pass') {
                $runBloodP->addText($checkmark, $checkStyle);
            } elseif ($row['res_blood'] == 'fail') {
                $runBloodF->addText($checkmark, $checkStyle);
            }
            $templateProcessor->setComplexValue("blood_p#$i", $runBloodP);
            $templateProcessor->setComplexValue("blood_f#$i", $runBloodF);
        }
    }

    $templateProcessor->saveAs($tempDocxPath);
} catch (Exception $e) {
    die("Template Error: " . $e->getMessage());
}

// ==========================================
// 6. CONVERT & OUTPUT
// ==========================================
$command = "libreoffice --headless --convert-to pdf --outdir " . escapeshellarg($outputDir) . " " . escapeshellarg($tempDocxPath);
$output = shell_exec($command . " 2>&1");

$pdfFileName = pathinfo($tempDocxPath, PATHINFO_FILENAME) . '.pdf';
$fullPdfPath = $outputDir . '/' . $pdfFileName;

if (file_exists($fullPdfPath)) {
    // 1. รับค่า Filter เพื่อใช้ตั้งชื่อไฟล์ตอน Save
    $val_start = $_GET['filter_val_start'] ?? '';
    $val_end   = $_GET['filter_val_end'] ?? '';
    $chem_id   = $_GET['filter_chemical_id'] ?? '';

    $fileNamePrefix = "F-CS-07";
    $namePart = "";

    if (!empty($val_start) && !empty($val_end)) {
        $namePart = "from_" . $val_start . "_to_" . $val_end;
    } elseif (!empty($val_start)) {
        $namePart = "from_" . $val_start;
    } elseif (!empty($val_end)) {
        $namePart = "until_" . $val_end;
    } else {
        $subPart = "All-Records";
        if (!empty($chem_id)) {
            // ดึงชื่อสารเคมีมาประกอบ
            $stmt = $pdo->prepare("SELECT chemical_name, chemical_brand FROM master_chemical_list WHERE id = ?");
            $stmt->execute([$chem_id]);
            $chem = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($chem) {
                $chemName = trim($chem['chemical_name']);
                $brandRaw = !empty($chem['chemical_brand']) ? trim($chem['chemical_brand']) : "";
                $chemBrand = $brandRaw ? " [$brandRaw]" : "";

                $fullName = $chemName . $chemBrand;

                // เปลี่ยนอักขระพิเศษเป็นขีด และยุบขีดซ้ำเฉพาะส่วนของชื่อสารเคมีก่อน
                $safeName = str_replace([' ', '/', '\\', '[', ']'], '-', $fullName);
                $safeName = preg_replace('/-+/', '-', $safeName);
                $subPart = trim($safeName, '-');
            } else {
                $subPart = "Chemical-ID-" . $chem_id;
            }
        } elseif (!empty($_GET['filter_tester']) || !empty($_GET['filter_res_acid_base']) || !empty($_GET['filter_res_blood']) || !empty($_GET['filter_is_verified']) ) {
            $subPart = "Filtered";
        }
        $namePart = $subPart . "_" . date('Y-m-d');
    }

    // Clean ชื่อไฟล์: ยุบขีดซ้ำ และตัดขีดหน้าหลัง
    $cleanNamePart = preg_replace('/-+/', '-', $namePart);
    $cleanNamePart = str_replace(['-_', '_-'], '_', $cleanNamePart);
    $displayName = $fileNamePrefix . "_" . trim($cleanNamePart, '_-') . ".pdf";

    header("Cache-Control: no-cache, must-revalidate");
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $displayName . '"');
    header('Content-Length: ' . filesize($fullPdfPath));
    header("Title: " . $displayName);

    readfile($fullPdfPath);

    @unlink($tempDocxPath);
    @unlink($fullPdfPath);
} else {
    http_response_code(500);
    echo "<h1>Error Generating PDF</h1>";
    echo "<pre>$output</pre>";
}
