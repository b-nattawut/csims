<?php
/**
 * Script ติดตั้ง wkhtmltopdf library แบบ manual
 */

echo "========================================\n";
echo "Installing mikehaertl/phpwkhtmltopdf\n";
echo "========================================\n\n";

$composerPath = __DIR__ . '/composer.phar';
$vendorPath = __DIR__ . '/vendor';

// ตรวจสอบว่ามี composer.phar หรือไม่
if (!file_exists($composerPath)) {
    echo "❌ ไม่พบ composer.phar\n";
    echo "กรุณาดาวน์โหลดจาก: https://getcomposer.org/download/\n";
    echo "แล้ววางไว้ที่: " . __DIR__ . "/\n\n";

    echo "หรือรันคำสั่งนี้ใน Command Prompt:\n";
    echo "cd " . __DIR__ . "\n";
    echo "composer require mikehaertl/phpwkhtmltopdf\n";
    exit(1);
}

echo "✅ พบ composer.phar\n";
echo "กำลังติดตั้ง...\n\n";

// รัน composer require
$command = 'php ' . escapeshellarg($composerPath) . ' require mikehaertl/phpwkhtmltopdf 2>&1';
$output = [];
$returnCode = 0;

exec($command, $output, $returnCode);

echo implode("\n", $output) . "\n";

if ($returnCode === 0) {
    echo "\n========================================\n";
    echo "✅ ติดตั้งสำเร็จ!\n";
    echo "========================================\n";

    // ตรวจสอบว่ามีไฟล์จริงหรือไม่
    $libraryPath = $vendorPath . '/mikehaertl/phpwkhtmltopdf';
    if (is_dir($libraryPath)) {
        echo "✅ ยืนยัน: พบ library ที่ $libraryPath\n";
    }
} else {
    echo "\n========================================\n";
    echo "❌ เกิดข้อผิดพลาด!\n";
    echo "========================================\n";
    echo "กรุณารันคำสั่งนี้ใน Command Prompt แทน:\n";
    echo "cd " . __DIR__ . "\n";
    echo "composer require mikehaertl/phpwkhtmltopdf\n";
}
