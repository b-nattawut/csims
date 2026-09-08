<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../db_config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน (Unauthorized)'
    ]);
    exit;
}

use PhpOffice\PhpWord\TemplateProcessor;

// ==========================================
// 1. CONFIGURATION & PATHS
// ==========================================
$baseDir = __DIR__;
$templatePath = $baseDir . '/templates/readiness_check.docx'; // ต้องสร้างไฟล์นี้
$outputDir    = $baseDir . '/output';
$tempDocxPath = $outputDir . '/temp_readiness_' . uniqid() . '.docx';

$assetsDir = $baseDir . '/assets/';
$checkboxChecked    = $assetsDir . 'chk_checked.png';
$checkboxEmpty    = $assetsDir . 'chk_empty.png';

if (!file_exists($templatePath)) die("Error: Template not found.");
if (!file_exists($checkboxChecked) || !file_exists($checkboxEmpty))  die("Error: Checkbox icon not found.");

// ==========================================
// 2. HELPER FUNCTIONS
// ==========================================
function thaiDate($date) {
    if (empty($date)) return '.../.../...';
    $ts = strtotime($date);
    $months = [null, 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return date('j', $ts) . ' ' . $months[date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}

function processSignature($base64, $varName, $tpl, $width = 100) {
    if (empty($base64)) { $tpl->setValue($varName, ''); return; }
    if (strpos($base64, 'base64,') !== false) {
        $parts = explode('base64,', $base64);
        $base64 = end($parts);
    }
    $imgData = base64_decode($base64);
    $tmp = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
    file_put_contents($tmp, $imgData);
    try {
        $tpl->setImageValue($varName, ['path' => $tmp, 'width' => $width, 'ratio' => true]);
    } catch (Exception $e) { $tpl->setValue($varName, ''); }
}

// ==========================================
// 3. FETCH DATA
// ==========================================
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    // 3.1 ดึงข้อมูล Header และชื่อเจ้าหน้าที่ทั้ง 3 ท่าน
    $sqlHeader = "SELECT t1.*, 
                    CONCAT(r1.rank_name, ' ', p1.first_name, ' ', p1.last_name) AS checked_name, pos1.position_name AS checked_pos,
                    CONCAT(r2.rank_name, ' ', p2.first_name, ' ', p2.last_name) AS leader_name, pos2.position_name AS leader_pos,

                    -- คนที่ 3: Hybrid Name
                    CASE 
                        WHEN t1.unit_officer_name IS NOT NULL AND t1.unit_officer_name != '' THEN t1.unit_officer_name
                        WHEN t1.unit_officer_id IS NOT NULL THEN CONCAT(IFNULL(r3.rank_name,''), ' ', p3.first_name, ' ', p3.last_name)
                        ELSE '-' 
                    END AS unit_name_hybrid,
                    
                    -- คนที่ 3: Hybrid Position (Snapshot First)
                    CASE 
                        WHEN t1.unit_officer_position IS NOT NULL AND t1.unit_officer_position != '' THEN t1.unit_officer_position
                        WHEN t1.unit_officer_id IS NOT NULL THEN pos3.position_name
                        ELSE '-' 
                    END AS unit_pos_hybrid,

                    -- ดึงข้อมูลสังกัดจากตาราง Users โดยตรง
                    u3.nvt_sub, u3.spt_sub, u3.ptjv_sub

                  FROM trans_readiness_check_header t1
                  LEFT JOIN user_profile p1 ON t1.checked_by = p1.user_id 
                  LEFT JOIN user_rank r1 ON p1.rank_id = r1.rank_id 
                  LEFT JOIN user_position pos1 ON p1.position_id = pos1.position_id

                  LEFT JOIN user_profile p2 ON t1.team_leader_id = p2.user_id 
                  LEFT JOIN user_rank r2 ON p2.rank_id = r2.rank_id 
                  LEFT JOIN user_position pos2 ON p2.position_id = pos2.position_id

                  LEFT JOIN user_profile p3 ON t1.unit_officer_id = p3.user_id 
                  LEFT JOIN user_rank r3 ON p3.rank_id = r3.rank_id 
                  LEFT JOIN user_position pos3 ON p3.position_id = pos3.position_id
                  LEFT JOIN users u3 ON t1.unit_officer_id = u3.user_id
                  WHERE t1.id = ? AND t1.delete_token = 0";
    $stmt = $pdo->prepare($sqlHeader);
    $stmt->execute([$id]);
    $header = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$header) die("Data not found.");

    // 3.2 ดึงผลการตรวจรายข้อของทุก Category
    $sqlDetails = "SELECT r.category, d.item_id, d.score, d.correction, d.remark 
                   FROM trans_readiness_check_results r
                   JOIN trans_readiness_check_details d ON r.id = d.result_id
                   WHERE r.header_id = ?";
    $stmtD = $pdo->prepare($sqlDetails);
    $stmtD->execute([$id]);
    $detailsMap = [];
    while ($row = $stmtD->fetch(PDO::FETCH_ASSOC)) {
        $detailsMap[$row['category']][$row['item_id']] = $row;
    }

} catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }

// ==========================================
// 4. PROCESS TEMPLATE
// ==========================================
$tpl = new TemplateProcessor($templatePath);

// ข้อมูลทั่วไป
$tpl->setValue('check_date', thaiDate($header['check_date']));
$tpl->setValue('check_time', date('H:i', strtotime($header['check_time'])));
$tpl->setValue('team_no', $header['team_no']);
$tpl->setImageValue('team_ok', ['path' => ($header['team_readiness_status'] === 'COMPLETE' ? $checkboxChecked : $checkboxEmpty), 'width' => 14, 'height' => 14]);
$tpl->setImageValue('team_ng', ['path' => ($header['team_readiness_status'] === 'INCOMPLETE' ? $checkboxChecked : $checkboxEmpty), 'width' => 14, 'height' => 14]);
$tpl->setValue('team_remark', $header['team_remark']);

// ข้อมูลอุปกรณ์
$tpl->setValue('car_license', $header['car_license']);
$tpl->setValue('car_mileage', $header['car_mileage'] ? number_format($header['car_mileage']) : '-');
$tpl->setValue('bag_set', $header['bag_set_no']);
$tpl->setValue('tool_set', $header['other_set_no'] ?: '-');
$tpl->setValue('camera_brand', $header['camera_brand']);
$tpl->setValue('camera_model', $header['camera_model']);
$tpl->setValue('camera_sn', $header['camera_sn'] ?: '-');

// ประมวลผล Checklist (Loop ตามหมวดหมู่)
$categories = ['VEHICLE', 'BAG', 'CAMERA', 'TOOLS'];
$checkImg = ['path' => $checkboxChecked, 'width' => 14, 'height' => 14];
$emptyImg = ['path' => $checkboxEmpty, 'width' => 14, 'height' => 14];

foreach ($categories as $cat) {
    $prefix = strtolower(substr($cat, 0, 1)); // v, b, c, t
    for ($i = 1; $i <= 15; $i++) { // เผื่อไว้ 15 ข้อ
        $item = $detailsMap[$cat][$i] ?? null;
        if ($item) {
            $tpl->setImageValue("{$prefix}_{$i}_ok", ($item['score'] == 1 ? $checkImg : $emptyImg));
            $tpl->setImageValue("{$prefix}_{$i}_ng", ($item['score'] == 0 ? $checkImg : $emptyImg));
            $tpl->setValue("{$prefix}_{$i}_cor", htmlspecialchars($item['correction']));
            $tpl->setValue("{$prefix}_{$i}_rem", htmlspecialchars($item['remark']));
        } else {
            // ล้าง Tag ที่ไม่ได้ใช้
            $tpl->setValue("{$prefix}_{$i}_ok", ""); $tpl->setValue("{$prefix}_{$i}_ng", "");
            $tpl->setValue("{$prefix}_{$i}_cor", ""); $tpl->setValue("{$prefix}_{$i}_rem", "");
        }
    }
}

// ลายเซ็นเจ้าหน้าที่ 3 ท่าน
$tpl->setValue('name_1', $header['checked_name']);   
$tpl->setValue('pos_1', $header['checked_pos']);
$tpl->setValue('name_2', $header['leader_name']);    
$tpl->setValue('pos_2', $header['leader_pos']);
$tpl->setValue('name_3', $header['unit_name_hybrid']);      
$tpl->setValue('pos_3', $header['unit_pos_hybrid']);

// --- ส่วนจัดการ Unit Category & Detail ---
$uCat = $header['unit_category'];
$uDet = $header['unit_detail'];
$uID  = $header['unit_officer_id'];

// Mapping จังหวัด 
$ptjv_reverse_map = [
    94 => 'ปัตตานี',
    95 => 'ยะลา',
    96 => 'นราธิวาส'
];

$val_sabu = '........';
$val_fsc  = '........';
$val_pjw  = '........';

// ดึงข้อมูลจากระบบ User (ถ้ามีคนในระบบ) - ดึงมาโชว์ให้ครบทุกหมวกที่เขาสวม
if (!empty($uID)) {
    if (!empty($header['nvt_sub'])) $val_sabu = $header['nvt_sub'];
    if (!empty($header['spt_sub'])) $val_fsc  = $header['spt_sub'];
    if (!empty($header['ptjv_sub'])) {
        $val_pjw = $ptjv_reverse_map[$header['ptjv_sub']] ?? '........';
    }
}

// ใช้ค่าจาก Header (unit_detail) ทับลงไปในหมวดที่เลือกบันทึกมา 
// เพื่อรองรับทั้งกรณี "คนนอกระบบ (พิมพ์มือ)" และการ "Snapshot ข้อมูล ณ วันที่ตรวจ"
if ($uCat === 'นวท.(สบ....)') {
    $val_sabu = $uDet;
} elseif ($uCat === 'ศพฐ.') {
    $val_fsc = $uDet;
} elseif ($uCat === 'พฐ.จว.') {
    $val_pjw = $uDet;
}

$tpl->setValue('u_sabu', $val_sabu);
$tpl->setValue('u_fsc',  $val_fsc);
$tpl->setValue('u_pjw',  $val_pjw);

processSignature($header['checked_signature'], 'sig_1', $tpl);
processSignature($header['leader_signature'],  'sig_2', $tpl);
processSignature($header['unit_officer_signature'], 'sig_3', $tpl);

// บันทึกไฟล์ชั่วคราว
$tpl->saveAs($tempDocxPath);

// ==========================================
// 5. CONVERT TO PDF & OUTPUT
// ==========================================
$command = "libreoffice --headless --convert-to pdf --outdir " . escapeshellarg($outputDir) . " " . escapeshellarg($tempDocxPath);
shell_exec($command);

$pdfPath = $outputDir . '/' . pathinfo($tempDocxPath, PATHINFO_FILENAME) . '.pdf';

if (file_exists($pdfPath)) {
    // เตรียมชื่อไฟล์สำหรับการดาวน์โหลด (ล้างค่าที่อาจมีผลกับ Header)
    $cleanTeam = str_replace(['/', '\\', ' '], '_', $header['team_no']);
    $cleanDate = date('d-m-Y', strtotime($header['check_date']));

    // ตั้งชื่อให้สอดคล้องกับเลขฟอร์ม (ถ้ามี) เช่น F-CS-02
    $displayName = "F-CS-02_Team-{$cleanTeam}_{$cleanDate}.pdf";

    header('Content-Type: application/pdf');
    header("Cache-Control: no-cache, must-revalidate");
    header('Content-Disposition: inline; filename="' . $displayName . '"');
    header('Content-Length: ' . filesize($pdfPath));
    header("Title: " . $displayName); 

    readfile($pdfPath);
    
    @unlink($tempDocxPath); 
    @unlink($pdfPath);
} else {
    echo "PDF Generation Failed.";
}