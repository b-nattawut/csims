<?php
/**
 * save_zpl_file.php
 * POST: รับ ZPL content จาก client → บันทึกเป็นไฟล์บน server → ส่ง URL กลับ
 * GET:  serve ไฟล์ ZPL พร้อม Content-Disposition header → iOS Safari เก็บลง Files app
 */

$dir = __DIR__ . '/../../uploads/zpl_temp';

// ===== GET: ดาวน์โหลดไฟล์ =====
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['file'])) {
    $requestedFile = basename($_GET['file']); // ป้องกัน path traversal
    $filePath = $dir . '/' . $requestedFile;

    if (!file_exists($filePath) || !preg_match('/\.zpl$/i', $requestedFile)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }

    // ชื่อไฟล์สำหรับ download (ตัด prefix timestamp ออก ให้เหลือชื่อสั้นๆ)
    $downloadName = preg_replace('/^\d{8}_\d{6}_[a-f0-9]+_/', '', $requestedFile);
    if (empty($downloadName)) $downloadName = $requestedFile;

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    readfile($filePath);
    exit;
}

// ===== POST: บันทึกไฟล์ =====
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$zplContent  = $input['zpl_content']  ?? '';
$filename    = $input['filename']     ?? 'evidence_label.zpl';

if (empty($zplContent)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีข้อมูล ZPL']);
    exit;
}

// sanitize filename
$filename = preg_replace('/[^a-zA-Z0-9\x{0E00}-\x{0E7F}_\-\.]/u', '_', $filename);
if (!preg_match('/\.zpl$/i', $filename)) {
    $filename .= '.zpl';
}

// สร้างโฟลเดอร์เก็บไฟล์ ZPL
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// ลบไฟล์เก่ากว่า 1 ชั่วโมง
foreach (glob($dir . '/*.zpl') as $old) {
    if (filemtime($old) < time() - 3600) {
        @unlink($old);
    }
}

// สร้างชื่อไฟล์ unique
$uniqueName = date('Ymd_His') . '_' . uniqid() . '_' . $filename;
$filePath   = $dir . '/' . $uniqueName;

if (file_put_contents($filePath, $zplContent) === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'บันทึกไฟล์ไม่สำเร็จ']);
    exit;
}

// สร้าง download URL ผ่าน PHP script (มี Content-Disposition header)
$downloadUrl = '/csims/api/incidentCheckList/save_zpl_file.php?file=' . urlencode($uniqueName);

echo json_encode([
    'status'       => 'success',
    'download_url' => $downloadUrl,
    'filename'     => $uniqueName,
]);
