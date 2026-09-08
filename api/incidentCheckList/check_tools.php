<?php
header('Content-Type: text/plain; charset=utf-8');
echo "=== Auto Setup wkhtmltoimage ===\n\n";

$localBin = __DIR__ . '/bin/wkhtmltoimage';
$binDir = __DIR__ . '/bin';

// เช็คว่ามี local binary อยู่แล้วไหม
if (file_exists($localBin) && is_executable($localBin)) {
    $ver = trim(shell_exec(escapeshellarg($localBin) . ' --version 2>&1') ?? '');
    echo "OK: wkhtmltoimage พร้อมใช้งาน ($ver)\n";
    echo "Path: $localBin\n";
    exit;
}

// เช็คว่ามีใน system ไหม
$sysTool = trim(shell_exec('which wkhtmltoimage 2>/dev/null') ?? '');
if ($sysTool) {
    echo "OK: wkhtmltoimage อยู่ใน system แล้ว: $sysTool\n";
    exit;
}

echo "wkhtmltoimage ไม่พบ - กำลังดาวน์โหลด...\n\n";

// สร้าง bin directory
if (!is_dir($binDir)) mkdir($binDir, 0755, true);

// ดาวน์โหลด .deb package
$arch = trim(shell_exec('dpkg --print-architecture 2>/dev/null') ?? '');
if (empty($arch)) $arch = 'amd64';
echo "Architecture: $arch\n";

// ใช้ version ที่เสถียร
$debUrl = "https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.jammy_{$arch}.deb";
$tmpDeb = sys_get_temp_dir() . '/wkhtmltox.deb';
$tmpExtract = sys_get_temp_dir() . '/wkhtmltox_extract_' . time();

echo "Downloading: $debUrl\n";

// ดาวน์โหลดด้วย wget หรือ curl
$dlResult = shell_exec("wget -q '$debUrl' -O '$tmpDeb' 2>&1 || curl -sL '$debUrl' -o '$tmpDeb' 2>&1");
echo "Download result: $dlResult\n";

if (!file_exists($tmpDeb) || filesize($tmpDeb) < 1000) {
    // ลอง Debian Bullseye
    $debUrl = "https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bullseye_{$arch}.deb";
    echo "Trying: $debUrl\n";
    shell_exec("wget -q '$debUrl' -O '$tmpDeb' 2>&1 || curl -sL '$debUrl' -o '$tmpDeb' 2>&1");
}

if (!file_exists($tmpDeb) || filesize($tmpDeb) < 1000) {
    // ลอง Focal
    $debUrl = "https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6-1/wkhtmltox_0.12.6-1.focal_{$arch}.deb";
    echo "Trying: $debUrl\n";
    shell_exec("wget -q '$debUrl' -O '$tmpDeb' 2>&1 || curl -sL '$debUrl' -o '$tmpDeb' 2>&1");
}

if (!file_exists($tmpDeb) || filesize($tmpDeb) < 1000) {
    echo "\nERROR: ดาวน์โหลดไม่สำเร็จ\n";
    echo "กรุณา SSH เข้า server แล้วรัน:\n";
    echo "  sudo apt-get update && sudo apt-get install -y wkhtmltopdf\n";
    exit;
}

echo "Downloaded: " . filesize($tmpDeb) . " bytes\n";

// แตก .deb (ar archive → data.tar.xz → extract binary)
if (!is_dir($tmpExtract)) mkdir($tmpExtract, 0755, true);

echo "\nExtracting...\n";
$extractCmd = "cd '$tmpExtract' && ar x '$tmpDeb' && tar xf data.tar* 2>/dev/null";
$extractOut = shell_exec($extractCmd . ' 2>&1');
echo "Extract: $extractOut\n";

// หา wkhtmltoimage binary
$foundBin = trim(shell_exec("find '$tmpExtract' -name 'wkhtmltoimage' -type f 2>/dev/null | head -1") ?? '');
echo "Found binary: $foundBin\n";

if ($foundBin && file_exists($foundBin)) {
    copy($foundBin, $localBin);
    chmod($localBin, 0755);
    
    // ลบ temp files
    shell_exec("rm -rf '$tmpExtract' '$tmpDeb'");
    
    // ทดสอบ
    $ver = trim(shell_exec(escapeshellarg($localBin) . ' --version 2>&1') ?? '');
    echo "\n=== RESULT ===\n";
    echo "wkhtmltoimage installed at: $localBin\n";
    echo "Version: $ver\n";
    
    if (!empty($ver) && strpos($ver, 'wkhtmltoimage') !== false) {
        echo "STATUS: SUCCESS!\n";
    } else {
        echo "STATUS: Binary copied but may need shared libraries.\n";
        $ldd = shell_exec("ldd '$localBin' 2>&1");
        echo "Dependencies:\n$ldd\n";
    }
} else {
    echo "\nERROR: ไม่พบ binary ใน .deb package\n";
    shell_exec("rm -rf '$tmpExtract' '$tmpDeb'");
}
