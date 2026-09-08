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
$templatePath = $baseDir . '/templates/latent_chemical_test_report.docx';
$tempImgDir = realpath(__DIR__ . '/temp_images');
$outputDir    = $baseDir . '/output';
$tempDocxPath = $outputDir . '/temp_latent_val_' . uniqid() . '.docx';

$sigBaseDir    = __DIR__ . '/../../uploads/signatures/';

if (!file_exists($templatePath)) die("Error: ไม่พบไฟล์ Template สำหรับรายงาน F-CS-21");

// ==========================================
// 2. HELPER FUNCTIONS
// ==========================================
function thaiDateShort($date)
{
    if (empty($date) || $date == '0000-00-00') return '-';
    $ts = strtotime($date);
    return date('d/m/', $ts) . ((date('Y', $ts) + 543) % 100);
}

// ==========================================
// 3. RECEIVE FILTERS & BUILD QUERY
// ==========================================
$test_start         = $_GET['filter_test_start'] ?? '';
$test_end           = $_GET['filter_test_end'] ?? '';
$filter_chemical_id = $_GET['filter_chemical_id'] ?? '';
$filter_dept_id     = $_GET['filter_department_id'] ?? ''; 
$tester_id          = $_GET['filter_tester'] ?? '';
$filter_ph          = $_GET['filter_ph'] ?? '';
$filter_color       = $_GET['filter_color'] ?? '';
$readiness_status   = $_GET['filter_readiness'] ?? '';
$is_verified        = $_GET['filter_is_verified'] ?? '';

// ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน (เพิ่มเช็ค is_active = 1)
$user_id = $_SESSION['user_id'];
$stmtUser = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
$stmtUser->execute([$user_id]);
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

// รวมเงื่อนไขกรองสิทธิ์หน่วยงานให้กระชับ
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

if (!empty($test_start)) {
    $where .= " AND t1.test_date >= ? ";
    $params[] = $test_start;
}
if (!empty($test_end)) {
    $where .= " AND t1.test_date <= ? ";
    $params[] = $test_end;
}
if (!empty($filter_chemical_id)) {
    $where .= " AND t2.id IN (
                    SELECT DISTINCT pi_f.prep_id 
                    FROM preparation_ingredients pi_f 
                    JOIN chemical_inventory inv_f ON pi_f.inventory_id = inv_f.id 
                    WHERE inv_f.chemical_id = ? AND pi_f.status_delete = 0
                ) ";
    $params[] = $filter_chemical_id;
}
if (!empty($tester_id)) {
    $where .= " AND t1.tester_id = ? ";
    $params[] = $tester_id;
}
if ($filter_ph !== '') {
    $where .= " AND t1.ph_value = ? ";
    $params[] = $filter_ph;
}
if (!empty($filter_color)) {
    $where .= " AND t1.color_change_status = ? ";
    $params[] = $filter_color;
}
if (!empty($readiness_status)) {
    $where .= " AND t1.readiness_status = ? ";
    $params[] = $readiness_status;
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
                t1.id, t1.test_date, t1.readiness_status, t1.remark, t1.amount_used,
                t1.ph_value, t1.color_change_status,
                t2.prep_name, t2.prep_date, t2.expiry_date,

                -- รวมสูตรผสมออกมาคั่นสายอักขระให้พร้อมหยอด Word
                GROUP_CONCAT(DISTINCT t3.lot_number SEPARATOR ', ') AS source_lot,
                GROUP_CONCAT(DISTINCT t4.chemical_name SEPARATOR ' + ') AS chemical_name, 
                GROUP_CONCAT(DISTINCT t4.chemical_brand SEPARATOR ', ') AS chemical_brand,

                CONCAT(IFNULL(r1.rank_name, ''), ' ', p1.first_name, ' ', p1.last_name) AS tester_fullname,
                CONCAT(IFNULL(r2.rank_name, ''), ' ', p2.first_name, ' ', p2.last_name) AS verifier_fullname,
                t1.verifier_signature
            FROM trans_latent_chemical_test t1 
            INNER JOIN chemical_preparation t2 ON t1.prep_id = t2.id
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
            t1.test_date, 
            t1.readiness_status, 
            t1.remark, 
            t1.amount_used, 
            t1.ph_value, 
            t1.color_change_status, 
            t1.verifier_signature,
            t2.prep_name, 
            t2.prep_date, 
            t2.expiry_date,
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

    // ประกาศตัวแปรเก็บไฟล์รูปภาพชั่วคราวเพื่อป้องกัน PHP Undefined Variable Notice
    $tempFilesToDelete = [];

    if ($count > 0) {
        $templateProcessor->cloneRow('test_date', $count);

        $templateProcessor->cloneBlock('block_test', $count, true, true);

        foreach ($rows as $index => $row) {
            $i = $index + 1;
            $current_latent_id = $row['id'];

            // ------- หน้าตารางหลัก (แนวนอน) ----------
            // 5.1 ข้อมูลพื้นฐาน
            $templateProcessor->setValue("test_date#$i", thaiDateShort($row['test_date']));
            $templateProcessor->setValue("chem_name#$i", $row['prep_name']);

            $details = "ยี่ห้อ/ผู้ผลิต: " . ($row['chemical_brand'] ?: '-');
            $templateProcessor->setValue("details#$i", $details);

            $templateProcessor->setValue("expiry_date#$i", thaiDateShort($row['expiry_date']));
            $templateProcessor->setValue("prep_date#$i", thaiDateShort($row['prep_date']));

            $templateProcessor->setValue("remark#$i", htmlspecialchars($row['remark'] ?: '-'));
            $templateProcessor->setValue("tester#$i", $row['tester_fullname']);

            // 5.2 จัดการลายเซ็น (Verifier)
            $sigFileName = $row['verifier_signature'];
            $sigPath = $sigBaseDir . $sigFileName;

            if (!empty($sigFileName) && file_exists($sigPath)) {
                $templateProcessor->setImageValue("verifier#$i", [
                    'path' => $sigPath,
                    'width' => 100,
                    'height' => 40,
                    'ratio' => true
                ]);
            } else {
                $templateProcessor->setValue("verifier#$i", $row['verifier_fullname'] . "\n(รอลงนาม)");
            }

            // 5.3 Logic Checkbox ความพร้อม
            $runReady    = new TextRun();
            $runNotReady = new TextRun();

            if ($row['readiness_status'] === 'ready') {
                $runReady->addText($checkmark, $checkStyle);
            } elseif ($row['readiness_status'] === 'not_ready') {
                $runNotReady->addText($checkmark, $checkStyle);
            }

            // ใช้ setComplexValue แทน setImageValue เดิม
            $templateProcessor->setComplexValue("ready_chk#$i", $runReady);
            $templateProcessor->setComplexValue("notready_chk#$i", $runNotReady);


            // ------- หน้าตารางผลการทดสอบ (แนวตั้ง) ----------
            $templateProcessor->setValue("test_date_block#$i", thaiDateShort($row['test_date']));
            $templateProcessor->setValue("chem_name_block#$i", $row['prep_name']);
            $templateProcessor->setValue("tester_block#$i", $row['tester_fullname']);

            $resultsRun = new TextRun();

            $sqlAllAttach = "SELECT id, file_content, file_type FROM trans_latent_chemical_attachment 
                 WHERE latent_test_id = ? AND status_delete = 0 LIMIT 10";

            $stmtAll = $pdo->prepare($sqlAllAttach);
            $stmtAll->execute([$current_latent_id]);
            $attachments = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

            // 2. จัดการรูปภาพ (แบบ setImageValue)
            for ($j = 1; $j <= 10; $j++) {
                $placeholderName = "img$j#$i"; // เช่น img1#1, img2#1...
                $spacerName = "s$j#$i";

                if (count($attachments) > 0) {
                    if (isset($attachments[$j - 1])) {
                        $imgRow = $attachments[$j - 1];
                        $ext = (strpos($imgRow['file_type'], 'jpeg') !== false) ? 'jpg' : 'png';
                        $tempImgPath = $tempImgDir . DIRECTORY_SEPARATOR . "img_{$current_latent_id}_{$imgRow['id']}.{$ext}";

                        file_put_contents($tempImgPath, $imgRow['file_content']);

                        if (file_exists($tempImgPath) && filesize($tempImgPath) > 0) {
                            $templateProcessor->setImageValue($placeholderName, [
                                'path'   => $tempImgPath,
                                'width'  => 550,   // ปรับขนาดตามต้องการ
                                'height' => 250,
                                'ratio'  => true
                            ]);
                            $tempFilesToDelete[] = $tempImgPath;
                        } else {
                            $templateProcessor->setValue($placeholderName, ""); // ถ้าไฟล์พังให้ว่างไว้
                        }

                        // จัดการตัวคั่น (Spacer) เฉพาะตอนที่มีรูปปัจจุบัน
                        if (isset($attachments[$j])) {
                            // ถ้ามีรูปถัดไป ให้ใส่บรรทัดว่าง
                            $spacerRun = new TextRun();
                            $spacerRun->addTextBreak(2);
                            $templateProcessor->setComplexValue($spacerName, $spacerRun);
                        } else {
                            // ถ้าไม่มีรูปถัดไป ลบตัวคั่นทิ้ง
                            $templateProcessor->setValue($spacerName, "");
                        }
                    } else {
                        // ลบ Placeholder ที่เหลือทิ้งในกรณีที่มีรูปแค่บางส่วน (เช่น มี 2 รูป จาก 10)
                        $templateProcessor->setValue($placeholderName, "");
                        $templateProcessor->setValue($spacerName, "");
                    }
                } else {
                    // --- กรณี "ไม่มีรูปเลย" --- 
                    if ($j === 1) {
                        // ใส่ข้อความแทนที่ในช่องแรก
                        $noImgRun = new TextRun();
                        $noImgRun->addText("(ไม่มีภาพถ่ายหลักฐาน)", ['italic' => true, 'color' => '999999']);
                        $templateProcessor->setComplexValue($placeholderName, $noImgRun);
                    } else {
                        $templateProcessor->setValue($placeholderName, "");
                    }
                    // ลบตัวคั่น s ทั้งหมดทิ้ง
                    $templateProcessor->setValue($spacerName, "");
                }
            }

            // 3. จัดการข้อความ (pH และ ผลลิตมัส) แบบ setValue ปกติ
            $phVal = ($row['ph_value'] !== null) ? number_format($row['ph_value'], 2) : '-';
            $templateProcessor->setValue("res_ph#$i", $phVal);

            // --- 5.6 Logic สำหรับเครื่องหมายเช็ค (เฉพาะเครื่องหมาย ✓ หรือ ช่องว่าง) ---
            $isChanged = ($row['color_change_status'] === 'changed');

            $styleWithUnderline = [
                'name' => 'Courier New',
                'size' => 18,
                'color' => '000000',
                'underline' => 'single'
            ];

            // --- สำหรับช่องที่ 1 (เปลี่ยนสีทันที) ---
            $c1Run = new TextRun();
            if ($isChanged) {
                // ถ้าเลือก: ใส่ช่องว่าง + ✓ + ช่องว่าง (เพื่อให้เส้นใต้ยาวออกมาทั้งสองข้าง)
                $c1Run->addText(" ✓ ", $styleWithUnderline);
            } else {
                $c1Run->addText("\u{00A0}\u{00A0}\u{00A0}", $styleWithUnderline);
            }
            $templateProcessor->setComplexValue("c1#$i", $c1Run);

            $c2Run = new \PhpOffice\PhpWord\Element\TextRun();
            if (!$isChanged) {
                // ถ้าเลือก: ใส่ช่องว่าง + ✓ + ช่องว่าง
                $c2Run->addText(" ✓ ", $styleWithUnderline);
            } else {
                // ถ้าไม่เลือก: ใส่เฉพาะช่องว่างพิเศษ
                $c2Run->addText("\u{00A0}\u{00A0}\u{00A0}", $styleWithUnderline);
            }
            $templateProcessor->setComplexValue("c2#$i", $c2Run);
        }
    }

    $templateProcessor->saveAs($tempDocxPath);

    // --- ส่วนการลบไฟล์รูปชั่วคราวหลัง Gen เสร็จ ---
    if (!empty($tempFilesToDelete)) {
        foreach ($tempFilesToDelete as $f) {
            if (file_exists($f)) @unlink($f);
        }
    }
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

    // 1. รับค่า Filter
    $test_start = $_GET['filter_test_start'] ?? '';
    $test_end   = $_GET['filter_test_end'] ?? '';
    $chem_id    = $_GET['filter_chemical_id'] ?? '';

    // สร้างชื่อส่วนกลาง
    $fileNamePrefix = "F-CS-21";
    $namePart = "";

    if (!empty($test_start) && !empty($test_end)) {
        $namePart = "from_" . $test_start . "_to_" . $test_end;
    } elseif (!empty($test_start)) {
        $namePart = "from_" . $test_start;
    } elseif (!empty($test_end)) {
        $namePart = "until_" . $test_end;
    } else {
        // กรณีไม่มีวันที่: ให้พ่วง Snapshot Date
        $subPart = "All-Records";
        if (!empty($chem_id)) {
            $stmt = $pdo->prepare("SELECT chemical_name, chemical_brand FROM master_chemical_list WHERE id = ?");
            $stmt->execute([$chem_id]);
            $chem = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($chem) {
                $chemName = trim($chem['chemical_name']);
                $brandRaw = !empty($chem['chemical_brand']) ? trim($chem['chemical_brand']) : "";
                $chemBrand = $brandRaw ? " [$brandRaw]" : "";
                $fullName = $chemName . $chemBrand;

                $safeName = str_replace([' ', '/', '\\', '[', ']'], '-', $fullName);
                $safeName = preg_replace('/-+/', '-', $safeName);
                $subPart = trim($safeName, '-');
            } else {
                $subPart = "Chemical-ID-" . $chem_id;
            }
        } elseif (!empty($_GET['filter_tester']) || !empty($_GET['filter_ph']) || !empty($_GET['filter_color']) || !empty($_GET['filter_readiness']) || !empty($_GET['filter_is_verified'])) {
            $subPart = "Filtered";
        }
        $namePart = $subPart . "_" . date('Y-m-d');
    }

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
    echo "<p>ไม่สามารถสร้างไฟล์ PDF ได้ โปรดตรวจสอบการติดตั้ง LibreOffice</p>";
    echo "<p><b>Command:</b> $command</p>";
    echo "<pre><b>Output:</b> $output</pre>";
}
