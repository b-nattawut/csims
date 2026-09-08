<?php
ini_set('memory_limit', '512M');
ini_set('post_max_size', '50M');
ini_set('max_execution_time', '120');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['image'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

// Hardcode IP เครื่องพิมพ์ Zebra ZQ521
$printer_ip   = '192.168.1.50';
$printer_port = 9100;

try {
    $data = $_POST['image'];
    $data = str_replace('data:image/png;base64,', '', $data);
    $data = str_replace(' ', '+', $data);
    $img_blob = base64_decode($data);
    $source_img = imagecreatefromstring($img_blob);
    if (!$source_img) throw new Exception("ประมวลผลรูปไม่ได้");

    // --- 1. ปรับให้พอดีกระดาษ 100x150mm (203 DPI: 800x1200 dots) ---
    $paperWidth  = 800;
    $paperHeight = 1200;
    $srcW = imagesx($source_img);
    $srcH = imagesy($source_img);
    $ratioW = $paperWidth / $srcW;
    $ratioH = $paperHeight / $srcH;
    $ratio = min($ratioW, $ratioH);
    $targetWidth  = (int)($srcW * $ratio);
    $targetHeight = (int)($srcH * $ratio);
    // ปัดให้หาร 8 ลงตัว
    $targetWidth = (int)(ceil($targetWidth / 8) * 8);

    $img = imagecreatetruecolor($targetWidth, $targetHeight);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $white);

    // ย่อรูปด้วยโหมดคุณภาพสูงสุดเพื่อรักษาเส้นประและตัวอักษร
    imagecopyresampled($img, $source_img, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($source_img), imagesy($source_img));
    imagedestroy($source_img);

    // --- 2. แปลงเป็น Hex แบบ High-Threshold เพื่อเก็บรายละเอียดเส้น ---
    $bytesPerRow = ceil($targetWidth / 8);
    $totalBytes = $bytesPerRow * $targetHeight;
    $hexData = "";

    for ($y = 0; $y < $targetHeight; $y++) {
        $byte = 0;
        for ($x = 0; $x < $targetWidth; $x++) {
            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $gray = ($r * 0.299) + ($g * 0.587) + ($b * 0.114);

            // Threshold 170: เส้นประ #888 (gray136) = ดำ, ตัวอักษร sidebar #c0c0c0 (gray192) = ขาว
            if ($gray < 170) { 
                $byte |= (1 << (7 - ($x % 8)));
            }

            if (($x % 8 == 7) || ($x == $targetWidth - 1)) {
                $hexData .= sprintf('%02X', $byte);
                $byte = 0;
            }
        }
    }
    imagedestroy($img);

    // --- 3. สร้างคำสั่ง ZPL แบบชิดขอบซ้ายสุด (Margin 0) ---
    $zpl = "! U1 setvar \"device.languages\" \"zpl\"\r\n"; 
    $zpl .= "^XA";
    $zpl .= "^MD15";      // ความเข้มปานกลาง เพื่อให้เส้นคมไม่เบลอ
    $zpl .= "^LH0,0";     // เริ่มที่จุด 0,0
    $zpl .= "^PW" . $targetWidth;
    $zpl .= "^LL" . $targetHeight;
    $zpl .= "^LS0";
    // ใช้ FO-8 เพื่อบังคับให้ภาพขยับเข้าไปชิดขอบซ้ายสุดๆ
    $zpl .= "^FO0,0^GFA,$totalBytes,$totalBytes,$bytesPerRow," . $hexData . "^FS";
    $zpl .= "^XZ";

    $fp = @fsockopen($printer_ip, $printer_port, $errno, $errstr, 10);
    if ($fp) {
        // ส่งแบบ chunked เพื่อรองรับข้อมูลขนาดใหญ่
        $len = strlen($zpl);
        $offset = 0;
        $chunkSize = 8192;
        while ($offset < $len) {
            $written = fwrite($fp, substr($zpl, $offset, $chunkSize));
            if ($written === false) throw new Exception('เขียนข้อมูลไม่สำเร็จ');
            $offset += $written;
        }
        fflush($fp);
        fclose($fp);
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception("เชื่อมต่อไม่ได้ ($errstr)");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}