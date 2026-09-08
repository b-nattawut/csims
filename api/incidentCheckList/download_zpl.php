<?php
// รับ ZPL content จาก POST → บันทึกเป็นไฟล์ชั่วคราว → redirect ไปที่ไฟล์
// iOS Safari จะเด้ง dialog "เปิดด้วย..." ให้เลือกแอป

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['zpl_content'])) {
    http_response_code(400);
    echo 'No ZPL content provided';
    exit;
}

$zplContent = $_POST['zpl_content'];
$filename = isset($_POST['filename']) ? $_POST['filename'] : 'evidence_label.zpl';
$filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
if (empty($filename)) $filename = 'evidence_label.zpl';

// บันทึกไฟล์ชั่วคราว
$tmpDir = __DIR__ . '/tmp_zpl';
if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);

// ลบไฟล์เก่า (เกิน 5 นาที)
foreach (glob($tmpDir . '/*.zpl') as $old) {
    if (filemtime($old) < time() - 300) @unlink($old);
}

$tmpFile = $tmpDir . '/' . uniqid('zpl_') . '.zpl';
file_put_contents($tmpFile, $zplContent);

// Redirect ไปที่ไฟล์ .zpl ตรงๆ → iOS จะเด้ง "เปิดด้วย..."
$baseUrl = dirname($_SERVER['REQUEST_URI']) . '/tmp_zpl/' . basename($tmpFile);
header('Location: ' . $baseUrl);
exit;
