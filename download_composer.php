<?php
/**
 * ดาวน์โหลด composer.phar อัตโนมัติ
 */

echo "กำลังดาวน์โหลด Composer...\n";

$composerUrl = 'https://getcomposer.org/download/latest-stable/composer.phar';
$composerPath = __DIR__ . '/composer.phar';

// ดาวน์โหลดไฟล์
$composerContent = @file_get_contents($composerUrl);

if ($composerContent === false) {
    die("❌ ไม่สามารถดาวน์โหลด Composer ได้\nกรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต\n");
}

// บันทึกไฟล์
file_put_contents($composerPath, $composerContent);
chmod($composerPath, 0755);

echo "✅ ดาวน์โหลด composer.phar สำเร็จ!\n";
echo "กำลังติดตั้ง library...\n\n";

// ตั้งค่า environment variables ที่ composer ต้องการ
putenv('HOME=' . __DIR__);
putenv('COMPOSER_HOME=' . __DIR__ . '/.composer');

// สร้างโฟลเดอร์ .composer ถ้ายังไม่มี
$composerHomeDir = __DIR__ . '/.composer';
if (!is_dir($composerHomeDir)) {
    mkdir($composerHomeDir, 0755, true);
}

// ติดตั้ง library
$command = 'php ' . escapeshellarg($composerPath) . ' require mikehaertl/phpwkhtmltopdf --no-interaction 2>&1';
$output = [];
exec($command, $output, $returnCode);

echo implode("\n", $output) . "\n";

if ($returnCode === 0) {
    echo "\n✅ ติดตั้งสำเร็จ!\n";
} else {
    echo "\n❌ เกิดข้อผิดพลาด\n";
}
