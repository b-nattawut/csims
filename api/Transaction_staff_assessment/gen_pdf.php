<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../helpers/report_no.php';

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

// ไฟล์ Template 
$templatePath = $baseDir . '/templates/staff_assessment.docx';

// ไฟล์ Output
$outputDir    = $baseDir . '/output';
$tempDocxPath = $outputDir . '/temp_staff_' . uniqid() . '.docx';

// ไฟล์รูปภาพ Assets (Radio)
$assetsDir = $baseDir . '/assets/';
$radioEmpty   = $assetsDir . 'radio_empty.png';
$radioChecked = $assetsDir . 'radio_checked.png';
$checkboxEmpty   = $assetsDir . 'chk_empty.png';
$checkboxChecked = $assetsDir . 'chk_checked.png';

// Validation
if (!file_exists($templatePath)) die("Error: Template not found at $templatePath");
if (!file_exists($radioEmpty) || !file_exists($radioChecked))  die("Error: Radio icon not found.");
if (!file_exists($checkboxEmpty) || !file_exists($checkboxChecked))  die("Error: Checkbox icon not found.");


// ==========================================
// 2. HELPER FUNCTIONS
// ==========================================

// 2.1 แปลงวันที่เป็นภาษาไทย
function thaiDate($datetime)
{
    if (empty($datetime)) return '....................';
    $timestamp = strtotime($datetime);
    $months = [null, 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $year = date('Y', $timestamp) + 543;
    return date('j', $timestamp) . ' ' . $months[date('n', $timestamp)] . ' ' . $year;
}

// 2.2 จัดการรูปภาพ Base64 (ลายเซ็น)
function processBase64Image($base64String, $variableName, $templateProcessor, $width = 100)
{
    if (empty($base64String)) {
        $templateProcessor->setValue($variableName, '');
        return;
    }
    if (strpos($base64String, 'base64,') !== false) {
        $data = explode('base64,', $base64String);
        $base64String = end($data);
    }
    $imgData = base64_decode($base64String);
    if ($imgData === false) {
        $templateProcessor->setValue($variableName, '');
        return;
    }

    $tempFileName = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
    file_put_contents($tempFileName, $imgData);

    // คำนวณ Ratio
    list($origW, $origH) = getimagesize($tempFileName);
    $height = ($origH / $origW) * $width;

    try {
        $templateProcessor->setImageValue($variableName, [
            'path'   => $tempFileName,
            'width'  => $width,
            'height' => $height,
            'ratio'  => false
        ]);
    } catch (Exception $e) {
        $templateProcessor->setValue($variableName, '');
    }
}

// ==========================================
// 3. FETCH DATA
// ==========================================
$header_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($header_id <= 0) die("Error: Invalid ID");

try {
    // ---------------------------------------------------------
    // 3.1 ดึงข้อมูล Header + ผู้ประเมิน
    // ---------------------------------------------------------

    $sqlHeader = "SELECT t1.*, 
                    CONCAT(IFNULL(t3.rank_name,''), ' ', t2.first_name, ' ', t2.last_name) AS evaluator_fullname,
                    t4.position_name AS evaluator_position
                  FROM staff_assessment_header t1
                  LEFT JOIN user_profile t2 ON t1.evaluator_id = t2.user_id
                  LEFT JOIN user_rank t3 ON t2.rank_id = t3.rank_id
                  LEFT JOIN user_position t4 ON t2.position_id = t4.position_id
                  WHERE t1.id = ? AND t1.delete_token = 0";
    $stmt = $pdo->prepare($sqlHeader);
    $stmt->execute([$header_id]);
    $header = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header) die("Error: Data not found.");

    // ---------------------------------------------------------
    // 3.2 ดึงข้อมูล Results (คะแนนที่ประเมินแล้ว)
    // ---------------------------------------------------------

    // เอามา Map ใส่ Array โดยใช้ role_type เป็น Key
    $resultsMap = [];
    $sqlRes = "SELECT * FROM staff_assessment_results WHERE header_id = ?";
    $stmtRes = $pdo->prepare($sqlRes);
    $stmtRes->execute([$header_id]);
    while ($row = $stmtRes->fetch(PDO::FETCH_ASSOC)) {
        $resultsMap[$row['role_type']] = $row;
    }

    // ---------------------------------------------------------
    // 3.3 เตรียมข้อมูล Staff List (Leader, Photographer, etc.)
    // ---------------------------------------------------------

    // เราต้องดึงชื่อของแต่ละ Role จาก Header ID ที่เก็บไว้ (leader_id, photographer_id, ...)
    $rolesConfig = [
        ['key' => 'leader_id',       'type' => 'LEADER',       'label' => 'หัวหน้าทีม'],
        ['key' => 'photographer_id', 'type' => 'PHOTOGRAPHER', 'label' => 'ช่างภาพ'],
        ['key' => 'map_maker_id',    'type' => 'MAP_MAKER',    'label' => 'ผู้ทำแผนที่'],
        ['key' => 'searcher_id',     'type' => 'SEARCHER',     'label' => 'ผู้ค้นหาวัตถุพยาน'],
        ['key' => 'collector_id',    'type' => 'COLLECTOR',    'label' => 'ผู้ตรวจเก็บวัตถุพยาน']
    ];

    // ดึงชื่อ User ทั้งหมดที่เกี่ยวข้องทีเดียว
    $userIds = [];
    foreach ($rolesConfig as $cfg) {
        if (!empty($header[$cfg['key']])) $userIds[] = $header[$cfg['key']];
    }

    $staffMap = [];

    if (!empty($userIds)) {
        $inQuery = implode(',', array_fill(0, count($userIds), '?'));
        $sqlUser = "SELECT t1.user_id, 
                           CONCAT(IFNULL(t2.rank_name,''), ' ', t1.first_name, ' ', t1.last_name) AS fullname,
                           IFNULL(t3.position_name, '-') AS position_name
                    FROM user_profile t1 
                    LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                    LEFT JOIN user_position t3 ON t1.position_id = t3.position_id
                    WHERE t1.user_id IN ($inQuery)";

        $stmtUser = $pdo->prepare($sqlUser);
        $stmtUser->execute($userIds);

        while ($u = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
            // เก็บข้อมูลเป็น Array ย่อย
            $staffMap[$u['user_id']] = [
                'fullname' => $u['fullname'],
                'position' => $u['position_name']
            ];
        }
    }

    // ---------------------------------------------------------
    // 3.4 ดึงข้อมูลรายละเอียดคะแนน (Details) ของทุก Role ทีเดียว
    // ---------------------------------------------------------
    // ใช้การ JOIN ตาราง results เพื่อกรองเฉพาะ header_id นี้
    $sqlAllDetails = "SELECT t1.criteria_id, t1.score, t1.remark, t1.comment, t2.role_type
                      FROM staff_assessment_details t1
                      JOIN staff_assessment_results t2 ON t1.result_id = t2.id
                      WHERE t2.header_id = ?
                      ORDER BY t2.role_type, t1.criteria_id ASC";

    $stmtDet = $pdo->prepare($sqlAllDetails);
    $stmtDet->execute([$header_id]);

    // จัดกลุ่มข้อมูลใส่ Array: $detailsMap['LEADER'][1] = ['score'=>3, ...];
    $detailsMap = [];

    while ($row = $stmtDet->fetch(PDO::FETCH_ASSOC)) {
        $rType = $row['role_type'];
        $cId   = $row['criteria_id'];

        if (!isset($detailsMap[$rType])) {
            $detailsMap[$rType] = [];
        }

        $detailsMap[$rType][$cId] = [
            'score'   => intval($row['score']),
            'remark'  => $row['remark'],
            'comment' => $row['comment']
        ];
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// 4. PROCESS TEMPLATE
// ==========================================
try {
    $templateProcessor = new TemplateProcessor($templatePath);

    // [CONFIG] เตรียมรูปภาพที่ใช้ร่วมกัน 
    // ----------------------------------------------------
    $imgRadioSize = ['width' => 14, 'height' => 14, 'ratio' => false, 'marginTop' => 1];
    // ตั้งค่าสไตล์สำหรับ Unicode Checkmark
    $checkStyle = ['size' => 14, 'bold' => true, 'name' => 'Arial'];
    $checkmark  = '✓';

    // Helper สำหรับ Radio
    $getRadio = function ($cond) use ($radioChecked, $radioEmpty, $imgRadioSize) {
        return array_merge(['path' => ($cond ? $radioChecked : $radioEmpty)], $imgRadioSize);
    };

    // ----------------------------------------------------
    // ส่วนที่ 1: Header & ข้อมูลทั่วไป 
    // ----------------------------------------------------
    $templateProcessor->setValue('report_no',      convertReportNoToThai($header['report_no'] ?? ''));
    $templateProcessor->setValue('operation_date', thaiDate($header['operation_date']));
    $templateProcessor->setValue('location',       $header['location'] ?? '');

    // Radio ขอบข่าย (Scope)
    $scope = $header['case_scope'];
    $templateProcessor->setImageValue('chk_prop', $getRadio($scope == 'PROPERTY'));
    $templateProcessor->setImageValue('chk_life', $getRadio($scope == 'LIFE'));
    $templateProcessor->setImageValue('chk_bomb', $getRadio($scope == 'EXPLOSIVE'));

    $templateProcessor->setValue('evaluator_name', $header['evaluator_fullname']);
    $templateProcessor->setValue('evaluator_pos',  $header['evaluator_position']);

    // ----------------------------------------------------
    // ส่วนที่ 2: ตารางสรุปรายชื่อ & ผลการประเมิน
    // ----------------------------------------------------
    // 2.1 ใส่รายชื่อ - ตำแหน่งเจ้าหน้าที่ทั้ง 5 รายการในหน้าแรก
    foreach ($rolesConfig as $role) {
        $uid = $header[$role['key']];
        $name = isset($staffMap[$uid]) ? $staffMap[$uid]['fullname'] : '-';
        $position = isset($staffMap[$uid]) ? $staffMap[$uid]['position'] : '-';

        // 1. ใส่ชื่อ: ${name_LEADER}, ${name_PHOTOGRAPHER}, ...
        $templateProcessor->setValue('name_' . $role['type'], $name);
        // 2. ใส่ตำแหน่ง: ${pos_LEADER}, ${pos_PHOTOGRAPHER}, ...
        $templateProcessor->setValue('pos_' . $role['type'], $position);
    }

    // 2.2 ตารางสรุปคะแนน ทั้ง 5 ตำแหน่ง
    $activeRoles = [];
    foreach ($rolesConfig as $role) {
        if (!empty($header[$role['key']])) $activeRoles[] = $role;
    }

    if (count($activeRoles) > 0) {
        $templateProcessor->cloneRow('row_role', count($activeRoles));

        foreach ($activeRoles as $index => $role) {
            $rowIndex = $index + 1;
            $roleType = $role['type'];

            // ใส่ชื่อ
            $templateProcessor->setValue("row_role#$rowIndex", $role['label']);

            // คำนวณเกรด
            $resData = $resultsMap[$roleType] ?? null;
            $percentage = 0;
            if ($resData) {
                $total = floatval($resData['total_score']);
                $max   = intval($resData['max_score'] ?: 60);
                $percentage = ($total / $max) * 100;
            }

            // ตัดเกรด
            $isExc   = ($resData && $percentage >= 90);
            $isVGood = ($resData && $percentage >= 80 && $percentage < 90);
            $isGood  = ($resData && $percentage >= 70 && $percentage < 80);
            $isFair  = ($resData && $percentage >= 60 && $percentage < 70);
            $isImp   = ($resData && $percentage < 60);

            $grades = [
                'chk_exc'   => $isExc,
                'chk_vgood' => $isVGood,
                'chk_good'  => $isGood,
                'chk_fair'  => $isFair,
                'chk_imp'   => $isImp
            ];

            foreach ($grades as $tagBase => $condition) {
                $run = new TextRun();
                if ($condition) {
                    $run->addText($checkmark, $checkStyle);
                }
                // ใส่ชื่อ Tag เช่น chk_exc#1, chk_vgood#1
                $templateProcessor->setComplexValue("{$tagBase}#$rowIndex", $run);
            }
        }
    }

    // ----------------------------------------------------
    // ส่วนที่ 3: ลายเซ็นผู้ประเมิน
    // ----------------------------------------------------

    // วันที่ลงนาม (Format: dd/mm/yyyy ปี พ.ศ.)
    processBase64Image($header['evaluator_signature'], 'evaluator_sig', $templateProcessor, 85);

    $sDateStr = '....../....../......';
    if (!empty($header['signed_date'])) {
        $ts = strtotime($header['signed_date']);
        $sDateStr = date('d', $ts) . ' / ' . date('m', $ts) . ' / ' . (date('Y', $ts) + 543);
    }
    $templateProcessor->setValue('signed_date', $sDateStr);

    // ----------------------------------------------------
    // ส่วนที่ 4: แบบฟอร์มรายละเอียดรายบุคคล 
    // ----------------------------------------------------

    // เตรียมรูป Checkbox (ประกาศครั้งเดียว)
    $cbSize = ['width' => 14, 'height' => 14, 'ratio' => false, 'marginTop' => 1];
    $getCb  = function ($cond) use ($checkboxChecked, $checkboxEmpty, $cbSize) {
        return array_merge(['path' => ($cond ? $checkboxChecked : $checkboxEmpty)], $cbSize);
    };

    // 4.1 จัดการแสดง/ซ่อน Block ของแต่ละตำแหน่ง
    foreach ($rolesConfig as $roleCfg) {
        $roleType  = $roleCfg['type']; // ex: LEADER
        $blockName = 'BLOCK_' . $roleType; // ex: BLOCK_LEADER
        $uid       = $header[$roleCfg['key']]; // ex: leader_id

        if (empty($uid)) {
            // ถ้าไม่มีคนตำแหน่งนี้ -> ลบ Block ทิ้ง (หน้านั้นจะหายไป)
            $templateProcessor->deleteBlock($blockName);
        } else {
            $templateProcessor->setValue($blockName, '');       // ลบ Tag เปิด
            $templateProcessor->setValue('/' . $blockName, '');

            // ถ้ามีคน -> แทนค่าข้อมูลลงไป
            // A. ข้อมูลหัวกระดาษ
            $cName = $staffMap[$uid]['fullname'] ?? '-';
            $cPos  = $staffMap[$uid]['position'] ?? '-';

            // ตัวแปรใน Word: ${name_LEADER}, ${pos_LEADER}
            $templateProcessor->setValue('name_' . $roleType, $cName);
            $templateProcessor->setValue('pos_' . $roleType,  $cPos);


            // B. ส่วนสรุปผลการประเมินท้ายฟอร์ม (ย้ายมาทำในนี้)
            // ---------------------------------------------------------
            $resData    = $resultsMap[$roleType] ?? null;
            $totalScore = 0;
            $maxScore   = 60;
            $percent    = 0;

            if ($resData) {
                $totalScore = floatval($resData['total_score']);
                $maxScore   = intval($resData['max_score'] ?: 60);
                $percent    = ($totalScore / $maxScore) * 100;
            }

            // ตัดเกรด
            $isExc   = ($percent >= 90);
            $isVGood = ($percent >= 80 && $percent < 90);
            $isGood  = ($percent >= 70 && $percent < 80);
            $isFair  = ($percent >= 60 && $percent < 70);
            $isImp   = ($percent < 60);

            // แทนค่าตัวเลข (ต้องเติม _ROLETYPE ต่อท้าย เพื่อไม่ให้ซ้ำกัน)
            // ตัวแปรใน Word: ${score_max_LEADER}, ${score_total_LEADER}
            $templateProcessor->setValue('score_max_' . $roleType,     $maxScore);
            $templateProcessor->setValue('score_total_' . $roleType,   $totalScore);
            $templateProcessor->setValue('score_percent_' . $roleType, number_format($percent, 2));

            // แทนค่ารูป Checkbox
            // ตัวแปรใน Word: ${final_exc_LEADER}, ${final_vgood_LEADER}
            $templateProcessor->setImageValue('final_exc_' . $roleType,   $getCb($isExc));
            $templateProcessor->setImageValue('final_vgood_' . $roleType, $getCb($isVGood));
            $templateProcessor->setImageValue('final_good_' . $roleType,  $getCb($isGood));
            $templateProcessor->setImageValue('final_fair_' . $roleType,  $getCb($isFair));
            $templateProcessor->setImageValue('final_imp_' . $roleType,   $getCb($isImp));
        }
    }

    // 4.2 วนลูปหยอดคะแนน (Score Loop)
    // วนลูปข้อมูลคะแนนทั้งหมดที่เราดึงมาเตรียมไว้ใน $detailsMap
    // $detailsMap โครงสร้าง: ['LEADER' => [ 1 => ['score'=>3, ...], 2 => [...] ]]

    // 4.2 วนลูปหยอดคะแนนรายข้อ (Score Loop)
    foreach ($detailsMap as $roleType => $criteriaList) {
        foreach ($criteriaList as $cId => $det) {
            $score   = intval($det['score']);

            $templateProcessor->setValue("com_{$cId}_{$roleType}", htmlspecialchars($det['comment'] ?? ''));
            $templateProcessor->setValue("rem_{$cId}_{$roleType}", htmlspecialchars($det['remark'] ?? ''));

            // วนลูปคะแนน 0-3
            for ($s = 0; $s <= 3; $s++) {
                // ผลลัพธ์จะเป็น: c_1_0_LEADER, c_1_0_PHOTOGRAPHER
                $varName = "c_{$cId}_{$s}_{$roleType}";

                $run = new TextRun();
                if ($score === $s) {
                    // ถ้าคะแนนตรงกับช่อง ให้ใส่เครื่องหมายถูก
                    $run->addText($checkmark, $checkStyle);
                }
                // ใช้ setComplexValue แทน setImageValue เดิม
                $templateProcessor->setComplexValue($varName, $run);
            }
        }
    }

    // Save Temp File
    $templateProcessor->saveAs($tempDocxPath);
} catch (Exception $e) {
    die("Error at Process Template: " . $e->getMessage() . " <br>Trace: " . $e->getTraceAsString());
}

// ==========================================
// 5. CONVERT TO PDF & OUTPUT
// ==========================================
// LibreOffice Command (Linux) - ตรวจสอบ Path ด้วย (บางเครื่องอาจเป็น soffice)
$command = "libreoffice --headless --convert-to pdf --outdir " . escapeshellarg($outputDir) . " " . escapeshellarg($tempDocxPath);
$output = shell_exec($command . " 2>&1");

$pdfFileName = pathinfo($tempDocxPath, PATHINFO_FILENAME) . '.pdf';
$fullPdfPath = $outputDir . '/' . $pdfFileName;

if (file_exists($fullPdfPath)) {

    $safeReportNo = str_replace(['/', '\\', ' '], '-', $header['report_no']);
    $displayName = "F-CS-22_" . $safeReportNo . ".pdf";

    header("Cache-Control: no-cache, must-revalidate");
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $displayName . '"');
    header('Content-Length: ' . filesize($fullPdfPath));
    header("Title: " . $displayName);
    
    readfile($fullPdfPath);

    // Cleanup
    @unlink($tempDocxPath);
    @unlink($fullPdfPath);
} else {
    echo "<h1>Error Generating PDF</h1>";
    echo "<p>Command: $command</p>";
    echo "<pre>Output: $output</pre>";
}
