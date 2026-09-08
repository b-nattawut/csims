<?php
/**
 * Server-side ZPL generation for Evidence label
 * สำหรับ iPad/iOS ที่ไม่สามารถใช้ html2canvas ได้
 * 
 * วิธีทำงาน:
 * 1. ใช้ wkhtmltoimage render หน้า HTML form เป็นภาพ PNG (ถ้ามี)
 * 2. หรือใช้ GD library สร้างภาพ label โดยตรง (fallback)
 * 3. แปลง PNG เป็น ZPL GFA (graphic) format
 * 4. ส่งไฟล์ ZPL กลับให้ download
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../db_config.php';

$incidentId = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if (!$incidentId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing incident_id']);
    exit;
}

// หา wkhtmltoimage (local binary ก่อน → system path)
$renderTool = '';
$localBin = __DIR__ . '/bin/wkhtmltoimage';
if (file_exists($localBin)) {
    $renderTool = $localBin;
}
if (empty($renderTool)) {
    $renderTool = trim(shell_exec('which wkhtmltoimage 2>/dev/null') ?? '');
}
if (empty($renderTool)) {
    foreach (['/usr/bin/wkhtmltoimage', '/usr/local/bin/wkhtmltoimage'] as $p) {
        $ver = trim(shell_exec(escapeshellarg($p) . ' --version 2>/dev/null') ?? '');
        if (!empty($ver)) { $renderTool = $p; break; }
    }
}

// ★ บังคับใช้ GD library เสมอ เพราะ wkhtmltoimage ไม่รองรับ multi-page parameter
// และ GD mode รองรับการสร้างหลายหน้าได้ถูกต้อง
$useGD = true;

try {
    $uploadDir = __DIR__ . '/../../uploads/zpl_temp/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // ลบไฟล์เก่ากว่า 1 ชั่วโมง
    foreach (glob($uploadDir . '*') as $old) {
        if (filemtime($old) < time() - 3600) @unlink($old);
    }

    // Label size: 4.1 x 5.95 inches at 203 DPI = 832 x 1208 dots (แนวตั้ง)
    $labelWidthDots = 832;
    $labelHeightDots = 1208;

    $tmpPng = $uploadDir . 'tmp_' . uniqid() . '.png';
    $zplContent = '';

    if ($useGD) {
        // ===== ใช้ GD Library สร้างภาพ label โดยตรง (หลายหน้า) =====
        // คำนวณจำนวนหน้าที่ต้องการจาก evidence
        $totalPages = calculateTotalPages($pdo, $incidentId);
        $zplPages = [];
        for ($pageNum = 1; $pageNum <= $totalPages; $pageNum++) {
            $zplPages[] = generateZplWithGD($pdo, $incidentId, $labelWidthDots, $labelHeightDots, $pageNum, $totalPages);
        }
        $zplContent = implode("\n", $zplPages);
    } else {
        // ===== ใช้ wkhtmltoimage render HTML → PNG =====
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        $zplPages = [];
        
        // คำนวณจำนวนหน้าจริงจาก evidence (เหมือน GD mode)
        $totalPages = calculateTotalPages($pdo, $incidentId);
        for ($pageNum = 1; $pageNum <= $totalPages; $pageNum++) {
            $formUrl = "{$protocol}://{$host}/csims/api/incidentCheckList/gen_pdf_evidence_html.php?incident_id={$incidentId}&page={$pageNum}";
            $tmpPngPage = $uploadDir . 'tmp_' . uniqid() . '_p' . $pageNum . '.png';

            $cmd = escapeshellarg($renderTool)
                . ' --width 800'
                . ' --height 1200'
                . ' --quality 100'
                . ' --encoding utf-8'
                . ' ' . escapeshellarg($formUrl)
                . ' ' . escapeshellarg($tmpPngPage);

            $cmdOutput = shell_exec($cmd . ' 2>&1');

            if (!file_exists($tmpPngPage)) {
                // Fallback to GD if wkhtmltoimage fails
                $zplPages[] = generateZplWithGD($pdo, $incidentId, $labelWidthDots, $labelHeightDots, $pageNum);
            } else {
                // แปลง PNG → ZPL GFA
                $zplPages[] = pngToZpl($tmpPngPage, $labelWidthDots, $labelHeightDots);
                @unlink($tmpPngPage);
            }
        }
        
        $zplContent = implode("\n", $zplPages);
    }

    // ดึงเลขคดีสำหรับชื่อไฟล์
    $caseNo = '';
    try {
        $stmtCase = $pdo->prepare("SELECT receiveNoti_No FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
        $stmtCase->execute([$incidentId]);
        $caseRow = $stmtCase->fetch(PDO::FETCH_ASSOC);
        if ($caseRow) {
            $caseNo = preg_replace('/[\/\\\\:*?"<>|]/', '_', $caseRow['receiveNoti_No'] ?? '');
        }
    } catch (Exception $e) {}
    
    if (empty($caseNo)) $caseNo = $incidentId;

    // บันทึกไฟล์ ZPL
    $filename = 'evidence_' . $caseNo . '_' . date('YmdHis') . '.zpl';
    $filepath = $uploadDir . $filename;
    file_put_contents($filepath, $zplContent);

    $downloadUrl = '/csims/api/incidentCheckList/save_zpl_file.php?file=' . urlencode($filename);

    echo json_encode([
        'status' => 'success',
        'download_url' => $downloadUrl,
        'filename' => $filename,
        'size' => filesize($filepath)
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

/**
 * คำนวณจำนวนหน้าทั้งหมดที่ต้องการ
 */
function calculateTotalPages($pdo, $incidentId) {
    $maxEvidencePerPage = 10;
    
    // ดึงข้อมูล evidence
    $stmt = $pdo->prepare("SELECT incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ?");
    $stmt->execute([$incidentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $evidenceCount = 0;
    if ($row && !empty($row['incident_checklist_data'])) {
        $data = json_decode($row['incident_checklist_data'], true);
        $evidenceForm = $data['evidence_form'] ?? [];
        $evidences = $evidenceForm['evidence_details'] ?? $data['evidences'] ?? [];
        $evidenceCount = count($evidences);
    }
    
    // หน้าแรก: evidence (สูงสุด 10 รายการ)
    // หน้าเพิ่มเติม: evidence ที่เหลือ (ทุก 10 รายการ)
    // หน้าสุดท้าย: custody chain
    
    $evidencePages = max(1, ceil($evidenceCount / $maxEvidencePerPage));
    $totalPages = $evidencePages + 1; // +1 สำหรับหน้า custody
    
    return $totalPages;
}

/**
 * แปลง PNG เป็น ZPL GFA format (เหมือน client-side canvasToZplGfa)
 */
function pngToZpl($pngPath, $targetW, $targetH) {
    $img = imagecreatefrompng($pngPath);
    if (!$img) {
        throw new Exception('Cannot read PNG file');
    }

    // Resize to target label size
    $resized = imagecreatetruecolor($targetW, $targetH);
    $white = imagecolorallocate($resized, 255, 255, 255);
    imagefill($resized, 0, 0, $white);
    imagecopyresampled($resized, $img, 0, 0, 0, 0, $targetW, $targetH, imagesx($img), imagesy($img));
    imagedestroy($img);

    // Convert to B/W and generate ZPL hex
    $bytesPerRow = (int)ceil($targetW / 8);
    $totalBytes = $bytesPerRow * $targetH;
    $hexStr = '';

    for ($y = 0; $y < $targetH; $y++) {
        for ($bx = 0; $bx < $bytesPerRow; $bx++) {
            $byteVal = 0;
            for ($bit = 0; $bit < 8; $bit++) {
                $px = $bx * 8 + $bit;
                if ($px < $targetW) {
                    $rgb = imagecolorat($resized, $px, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    $gray = $r * 0.299 + $g * 0.587 + $b * 0.114;
                    if ($gray < 170) {
                        $byteVal |= (1 << (7 - $bit));
                    }
                }
            }
            $hexStr .= strtoupper(str_pad(dechex($byteVal), 2, '0', STR_PAD_LEFT));
        }
    }
    
    imagedestroy($resized);

    // สร้าง ZPL output
    $zpl = "^XA\n";
    $zpl .= "^MD15\n";
    $zpl .= "^LH0,0\n";
    $zpl .= "^PW{$targetW}\n";
    $zpl .= "^LL{$targetH}\n";
    $zpl .= "^LS0\n";
    $zpl .= "^FO0,0\n";
    $zpl .= "^GFA,{$totalBytes},{$totalBytes},{$bytesPerRow},{$hexStr}\n";
    $zpl .= "^FS\n";
    $zpl .= "^XZ\n";

    return $zpl;
}

/**
 * สร้าง ZPL โดยใช้ GD Library (fallback เมื่อไม่มี wkhtmltoimage)
 * วาดตามโครงสร้างฟอร์มจริงจาก form_evidence_preview.html
 * @param int $pageNum หมายเลขหน้า (1, 2, 3, ...)
 * @param int $totalPages จำนวนหน้าทั้งหมด
 * หน้า 1 ถึง N-1 = evidence (10 รายการต่อหน้า)
 * หน้า N (สุดท้าย) = custody chain
 */
function generateZplWithGD($pdo, $incidentId, $targetW, $targetH, $pageNum = 1, $totalPages = 2) {
    // ดึงข้อมูลจาก database (เหมือน gen_pdf_evidence_html.php)
    $stmt = $pdo->prepare("SELECT incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incidentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $data = $row ? json_decode($row['incident_checklist_data'], true) : [];
    
    $stmtRn = $pdo->prepare("SELECT receiveNoti_No, complaints_From FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
    $stmtRn->execute([$incidentId]);
    $rnRow = $stmtRn->fetch(PDO::FETCH_ASSOC);
    
    $gen = $data['general_info'] ?? [];
    $evidenceForm = $data['evidence_form'] ?? [];
    
    // ===== ดึงข้อมูลเหมือน gen_pdf_evidence_html.php =====
    // Station Name
    $stationName = $evidenceForm['station_name'] ?? '';
    if (empty($stationName)) $stationName = $rnRow['complaints_From'] ?? '';
    if (empty($stationName)) $stationName = $gen['source_station'] ?? '';
    
    // Case Number
    $caseNo = $rnRow['receiveNoti_No'] ?? '';
    if (empty($caseNo)) $caseNo = $gen['document_no'] ?? '';
    
    // Location
    $locationDetail = $evidenceForm['location_detail'] ?? '';
    if (empty($locationDetail)) $locationDetail = $gen['location_detail'] ?? '';
    
    // Incident Date/Time
    $incidentDate = '';
    $incidentTime = '';
    if (!empty($evidenceForm['incident_date'])) {
        $incidentDate = thaiDateFullGD($evidenceForm['incident_date']);
        $incidentTime = $evidenceForm['incident_time'] ?? '';
    } elseif (!empty($gen['incident_datetime'])) {
        $incidentDate = thaiDateFullGD($gen['incident_datetime']);
        $ts = strtotime($gen['incident_datetime']);
        if ($ts) $incidentTime = date('H:i', $ts);
    }
    
    // Collect Date/Time
    $collectDate = '';
    $collectTime = '';
    if (!empty($evidenceForm['collect_date'])) {
        $collectDate = thaiDateFullGD($evidenceForm['collect_date']);
        $collectTime = $evidenceForm['collect_time'] ?? '';
    } elseif (!empty($gen['inspection_datetime'])) {
        $collectDate = thaiDateFullGD($gen['inspection_datetime']);
        $ts = strtotime($gen['inspection_datetime']);
        if ($ts) $collectTime = date('H:i', $ts);
    }
    
    // Victims
    $victims = [];
    $evfVictims = $evidenceForm['victims'] ?? [];
    $allVictims = $gen['all_victims'] ?? [];
    $rawVictims = !empty($evfVictims) ? $evfVictims : $allVictims;
    foreach ($rawVictims as $rv) {
        $type = trim($rv['type'] ?? $rv['victim_type'] ?? $rv['person_type'] ?? '');
        $name = trim($rv['name'] ?? $rv['victim_name'] ?? $rv['fullname'] ?? '');
        $age = trim($rv['age'] ?? $rv['victim_age'] ?? '');
        if ($type !== '' || $name !== '' || $age !== '') {
            $victims[] = ['type' => $type, 'name' => $name, 'age' => $age];
        }
    }
    
    // Evidence Details (ไม่รวมรายการที่ถูกซ่อน hidden: true)
    $evidences = [];
    $evfEvidences = $evidenceForm['evidence_details'] ?? [];
    $dataEvidences = $data['evidences'] ?? [];
    $rawEvidences = !empty($evfEvidences) ? $evfEvidences : $dataEvidences;
    foreach ($rawEvidences as $ev) {
        // ข้ามรายการที่ถูกซ่อน
        if (isset($ev['hidden']) && $ev['hidden'] === true) {
            continue;
        }
        $detail = trim($ev['detail'] ?? $ev['item'] ?? $ev['description'] ?? '');
        $qty = trim($ev['qty'] ?? $ev['quantity'] ?? $ev['quantity_val'] ?? '');
        $position = trim($ev['position'] ?? $ev['area_found'] ?? $ev['area'] ?? '');
        if ($detail !== '' || $qty !== '' || $position !== '') {
            $evidences[] = ['detail' => $detail, 'qty' => $qty, 'position' => $position];
        }
    }
    
    // Custody Chain
    $custodyChain = $evidenceForm['custody'] ?? [];
    
    // Condition
    $evfCondition = $evidenceForm['condition'] ?? [];
    $condGood = $evfCondition['sealed'] ?? true;
    $condOther = $evfCondition['other'] ?? false;
    $condOtherText = $evfCondition['other_text'] ?? '';
    
    // ===== สร้างภาพ (4.1 x 5.95 inches @ 203 DPI = 832 x 1208 dots) =====
    $img = imagecreatetruecolor($targetW, $targetH);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    $gray = imagecolorallocate($img, 85, 85, 85);
    $headerBg = imagecolorallocate($img, 240, 230, 210); // #f0e6d2
    
    // พื้นขาว
    imagefill($img, 0, 0, $white);
    
    // Sidebar ดำ (ประมาณ 18% ของความกว้าง = ~150 dots) - ดำทั้งแถบตลอดความสูง
    $sidebarW = (int)($targetW * 0.18); // ~150 dots
    imagefilledrectangle($img, 0, 0, $sidebarW - 1, $targetH - 1, $black);
    
    // หา font file
    $fontBold = $_SERVER['DOCUMENT_ROOT'] . '/csims/fonts/Sarabun/Sarabun-Bold.ttf';
    $fontRegular = $_SERVER['DOCUMENT_ROOT'] . '/csims/fonts/Sarabun/Sarabun-Regular.ttf';
    
    if (!file_exists($fontBold)) {
        $fontBold = 5;
        $fontRegular = 5;
    }
    
    // ===== วาด Logo ใน Sidebar (ด้านบน - โคตรชัดที่สุด!) =====
    $logoPath = $_SERVER['DOCUMENT_ROOT'] . '/csims/images/icon-forensic-police.png';
    if (file_exists($logoPath) && is_string($fontBold)) {
        $logoImg = imagecreatefrompng($logoPath);
        if ($logoImg) {
            $logoSize = (int)($sidebarW * 0.92); // 92% ของความกว้าง sidebar
            $logoX = (int)(($sidebarW - $logoSize) / 2); // กลาง sidebar
            $logoY = 25; // ห่างจากขอบบนมากขึ้น
            
            $logoW = imagesx($logoImg);
            $logoH = imagesy($logoImg);
            
            // สร้าง canvas ขนาด 3x สำหรับความชัดสูงสุด
            $scale = 3;
            $scaledW = $logoW * $scale;
            $scaledH = $logoH * $scale;
            $whiteLogo = imagecreatetruecolor($scaledW, $scaledH);
            $blackBg = imagecolorallocate($whiteLogo, 0, 0, 0);
            imagefill($whiteLogo, 0, 0, $blackBg);
            
            $whiteColor = imagecolorallocate($whiteLogo, 255, 255, 255);
            
            // วาด logo ขยาย 3x - จับทุก pixel ที่มีสี
            for ($px = 0; $px < $logoW; $px++) {
                for ($py = 0; $py < $logoH; $py++) {
                    $rgba = imagecolorat($logoImg, $px, $py);
                    $alpha = ($rgba >> 24) & 0x7F;
                    $r = ($rgba >> 16) & 0xFF;
                    $g = ($rgba >> 8) & 0xFF;
                    $b = $rgba & 0xFF;
                    
                    // จับทุก pixel ที่ไม่โปร่งใส (alpha < 127) และมีสีอะไรก็ได้
                    if ($alpha < 120) {
                        // วาด 3x3 block เพื่อให้หนามากๆ
                        for ($dx = 0; $dx < $scale; $dx++) {
                            for ($dy = 0; $dy < $scale; $dy++) {
                                imagesetpixel($whiteLogo, $px * $scale + $dx, $py * $scale + $dy, $whiteColor);
                            }
                        }
                    }
                }
            }
            
            // ทำให้เส้นหนาขึ้นอีกด้วย dilation (ขยายขอบ)
            $dilated = imagecreatetruecolor($scaledW, $scaledH);
            imagefill($dilated, 0, 0, $blackBg);
            $whiteD = imagecolorallocate($dilated, 255, 255, 255);
            
            for ($x = 1; $x < $scaledW - 1; $x++) {
                for ($y = 1; $y < $scaledH - 1; $y++) {
                    // ถ้า pixel นี้หรือ pixel รอบๆ เป็นสีขาว
                    $hasWhite = false;
                    for ($dx = -1; $dx <= 1; $dx++) {
                        for ($dy = -1; $dy <= 1; $dy++) {
                            $c = imagecolorat($whiteLogo, $x + $dx, $y + $dy);
                            if (($c & 0xFF) > 128) {
                                $hasWhite = true;
                                break 2;
                            }
                        }
                    }
                    if ($hasWhite) {
                        imagesetpixel($dilated, $x, $y, $whiteD);
                    }
                }
            }
            
            imagecopyresampled($img, $dilated, $logoX, $logoY, 0, 0, $logoSize, $logoSize, $scaledW, $scaledH);
            imagedestroy($logoImg);
            imagedestroy($whiteLogo);
            imagedestroy($dilated);
        }
    }
    
    // ===== ข้อความใน Sidebar (หมุน -90 องศา = อ่านจากบนลงล่าง) =====
    // ข้อความอยู่ตรงกลาง sidebar ทั้งแนวนอนและแนวตั้ง
    // Forensic Police อยู่ตรงกลางระหว่าง พิสูจน์หลักฐานตำรวจ
    if (is_string($fontBold)) {
        $sidebarTextTh = "พิสูจน์หลักฐานตำรวจ";
        $sidebarTextEn = "Forensic Police";
        
        // ตำแหน่งกลาง sidebar - กลางกระดาษพอดี
        $centerX = (int)($sidebarW / 2); // กลาง X
        $textCenterY = (int)($targetH * 0.38); // กลาง Y ของกระดาษ (เลื่อนขึ้นอีก)
        
        // วาดข้อความไทย (หมุน -90 องศา) - ตัวใหญ่ขึ้น 32pt
        imagettftext($img, 32, -90, $centerX + 22, $textCenterY, $white, $fontBold, $sidebarTextTh);
        
        // วาดข้อความอังกฤษ (หมุน -90 องศา) - ตัวใหญ่ขึ้น 18pt
        $enTextY = $textCenterY + 90; // เลื่อนลงมาให้อยู่กลางๆ ของข้อความไทย
        imagettftext($img, 18, -90, $centerX - 18, $enTextY, $white, $fontBold, $sidebarTextEn);
    }
    
    // ===== Content Area =====
    $contentX = $sidebarW + 20;
    $contentW = $targetW - $sidebarW - 35;
    $y = 30; // เพิ่มระยะห่างจากหัวกระดาษ
    
    if (is_string($fontBold)) {
        // ===== หัวข้อ (ตรงกลาง content area) =====
        $titleX = $contentX + ($contentW / 2) - 100;
        imagettftext($img, 32, 0, $titleX, $y + 30, $black, $fontBold, "วัตถุพยาน");
        $y += 70; // เพิ่มระยะห่างเยอะๆ
        imagettftext($img, 12, 0, $titleX + 50, $y, $gray, $fontRegular, "EVIDENCE");
        $y += 40; // เพิ่มระยะห่างระหว่าง EVIDENCE กับบรรทัดถัดไป
        
        // ===== สถานีตำรวจ + คดี (แถวเดียว) =====
        imagettftext($img, 12, 0, $contentX, $y, $black, $fontBold, "สถานีตำรวจ");
        imagettftext($img, 12, 0, $contentX + 80, $y, $black, $fontRegular, $stationName);
        imagettftext($img, 12, 0, $contentX + 380, $y, $black, $fontBold, "คดี");
        imagettftext($img, 12, 0, $contentX + 410, $y, $black, $fontRegular, $caseNo);
        $y += 28;
        
        // ===== สถานที่เกิดเหตุ (1 หรือ 2 บรรทัด) =====
        imagettftext($img, 12, 0, $contentX, $y, $black, $fontBold, "สถานที่เกิดเหตุ");
        $locLen = mb_strlen($locationDetail, 'UTF-8');
        $maxCharsPerLine = 38;
        if ($locLen <= $maxCharsPerLine) {
            // บรรทัดเดียวพอ
            imagettftext($img, 11, 0, $contentX + 105, $y, $black, $fontRegular, $locationDetail);
            $y += 28;
        } else {
            // 2 บรรทัด - ตัดที่ช่องว่างใกล้กลาง
            $halfLen = (int)ceil($locLen / 2);
            $bestSpace = false;
            for ($i = $halfLen; $i >= max(0, $halfLen - 15); $i--) {
                if (mb_substr($locationDetail, $i, 1, 'UTF-8') === ' ') {
                    $bestSpace = $i;
                    break;
                }
            }
            if ($bestSpace === false) {
                for ($i = $halfLen; $i <= min($locLen, $halfLen + 15); $i++) {
                    if (mb_substr($locationDetail, $i, 1, 'UTF-8') === ' ') {
                        $bestSpace = $i;
                        break;
                    }
                }
            }
            if ($bestSpace !== false) {
                $locLine1 = mb_substr($locationDetail, 0, $bestSpace, 'UTF-8');
                $locLine2 = mb_substr($locationDetail, $bestSpace + 1, null, 'UTF-8');
            } else {
                $locLine1 = mb_substr($locationDetail, 0, $maxCharsPerLine, 'UTF-8');
                $locLine2 = mb_substr($locationDetail, $maxCharsPerLine, null, 'UTF-8');
            }
            imagettftext($img, 11, 0, $contentX + 105, $y, $black, $fontRegular, $locLine1);
            $y += 22;
            imagettftext($img, 11, 0, $contentX, $y, $black, $fontRegular, $locLine2);
            $y += 22;
        }
        
        // ===== วันที่เกิดเหตุ + เวลา =====
        imagettftext($img, 12, 0, $contentX, $y, $black, $fontBold, "วันที่เกิดเหตุ");
        imagettftext($img, 12, 0, $contentX + 95, $y, $black, $fontRegular, $incidentDate);
        imagettftext($img, 12, 0, $contentX + 310, $y, $black, $fontBold, "เวลาประมาณ");
        imagettftext($img, 12, 0, $contentX + 410, $y, $black, $fontRegular, $incidentTime . " น.");
        $y += 30;
        
        // ===== แยกเนื้อหาตามหน้า =====
        // หน้าสุดท้าย = Custody Chain, หน้าอื่นๆ = Evidence
        $maxEvidencePerPage = 10;
        $isCustodyPage = ($pageNum == $totalPages);
        
        if ($isCustodyPage) {
            // ========== หน้าสุดท้าย: Custody Chain ==========
            
            // ลำดับการครอบครองวัตถุพยาน
            imagettftext($img, 12, 0, $contentX, $y, $black, $fontBold, "ลำดับการครอบครองวัตถุพยาน (CHAIN OF CUSTODY)");
            $y += 28;
            
            // ตาราง custody (ปรับให้เหมาะกับ 832 dots width)
            $tableX = $contentX;
            $tableW = $contentW;
            $colW = [45, 120, 120, 90, 145]; // ลำดับ, จากใคร, ถึงใคร, วันที่, หมายเหตุ
            $rowH = 36;
            
            // Header
            imagefilledrectangle($img, $tableX, $y, $tableX + $tableW, $y + $rowH, $headerBg);
            imagerectangle($img, $tableX, $y, $tableX + $tableW, $y + $rowH, $black);
            
            $hx = $tableX;
            imagettftext($img, 10, 0, $hx + 5, $y + 15, $black, $fontBold, "ลำดับ");
            imagettftext($img, 10, 0, $hx + 12, $y + 28, $black, $fontBold, "ที่");
            imageline($img, $hx + $colW[0], $y, $hx + $colW[0], $y + $rowH, $black);
            $hx += $colW[0];
            imagettftext($img, 10, 0, $hx + 8, $y + 22, $black, $fontBold, "จากใคร");
            imageline($img, $hx + $colW[1], $y, $hx + $colW[1], $y + $rowH, $black);
            $hx += $colW[1];
            imagettftext($img, 10, 0, $hx + 8, $y + 22, $black, $fontBold, "ถึงใคร");
            imageline($img, $hx + $colW[2], $y, $hx + $colW[2], $y + $rowH, $black);
            $hx += $colW[2];
            imagettftext($img, 10, 0, $hx + 8, $y + 22, $black, $fontBold, "วันที่");
            imageline($img, $hx + $colW[3], $y, $hx + $colW[3], $y + $rowH, $black);
            $hx += $colW[3];
            imagettftext($img, 10, 0, $hx + 8, $y + 22, $black, $fontBold, "หมายเหตุ");
            $y += $rowH;
            
            // Data rows
            $custodyCount = 0;
            foreach ($custodyChain as $cust) {
                $fromName = trim($cust['from_name'] ?? '');
                $toName = trim($cust['to_name'] ?? '');
                $custDate = $cust['date'] ?? '';
                $remark = trim($cust['remark'] ?? '');
                if ($fromName === '' && $toName === '' && $custDate === '' && $remark === '') continue;
                
                $custodyCount++;
                imagerectangle($img, $tableX, $y, $tableX + $tableW, $y + $rowH, $black);
                $hx = $tableX;
                imagettftext($img, 10, 0, $hx + 15, $y + 22, $black, $fontRegular, (string)$custodyCount);
                imageline($img, $hx + $colW[0], $y, $hx + $colW[0], $y + $rowH, $black);
                $hx += $colW[0];
                imagettftext($img, 9, 0, $hx + 5, $y + 22, $black, $fontRegular, mb_substr($fromName, 0, 12, 'UTF-8'));
                imageline($img, $hx + $colW[1], $y, $hx + $colW[1], $y + $rowH, $black);
                $hx += $colW[1];
                imagettftext($img, 9, 0, $hx + 5, $y + 22, $black, $fontRegular, mb_substr($toName, 0, 12, 'UTF-8'));
                imageline($img, $hx + $colW[2], $y, $hx + $colW[2], $y + $rowH, $black);
                $hx += $colW[2];
                imagettftext($img, 9, 0, $hx + 5, $y + 22, $black, $fontRegular, thaiDateShortGD($custDate));
                imageline($img, $hx + $colW[3], $y, $hx + $colW[3], $y + $rowH, $black);
                $hx += $colW[3];
                imagettftext($img, 9, 0, $hx + 5, $y + 22, $black, $fontRegular, mb_substr($remark, 0, 16, 'UTF-8'));
                $y += $rowH;
            }
            
            // เติมแถวว่าง
            for ($i = $custodyCount; $i < 5; $i++) {
                imagerectangle($img, $tableX, $y, $tableX + $tableW, $y + $rowH, $black);
                $hx = $tableX;
                imagettftext($img, 10, 0, $hx + 15, $y + 22, $black, $fontRegular, (string)($i + 1));
                imageline($img, $hx + $colW[0], $y, $hx + $colW[0], $y + $rowH, $black);
                $hx += $colW[0];
                imageline($img, $hx + $colW[1], $y, $hx + $colW[1], $y + $rowH, $black);
                $hx += $colW[1];
                imageline($img, $hx + $colW[2], $y, $hx + $colW[2], $y + $rowH, $black);
                $hx += $colW[2];
                imageline($img, $hx + $colW[3], $y, $hx + $colW[3], $y + $rowH, $black);
                $y += $rowH;
            }
            
            $y += 30;
            
            // ลักษณะของของกลาง
            imagettftext($img, 12, 0, $contentX, $y, $black, $fontBold, "ลักษณะของของกลางขณะมาถึงผู้ตรวจพิสูจน์หรือ");
            $y += 24;
            imagettftext($img, 12, 0, $contentX, $y, $black, $fontBold, "ผู้รับผิดชอบ");
            $y += 32;
            
            // Checkbox - วาดกรอบและเครื่องหมายติ๊ก
            $chkSize = 18;
            // Checkbox 1: อยู่ในสภาพปิดผนึกเรียบร้อย
            imagerectangle($img, $contentX, $y, $contentX + $chkSize, $y + $chkSize, $black);
            if ($condGood) {
                // วาดเครื่องหมายติ๊กด้วยเส้นหนา
                imagesetthickness($img, 2);
                imageline($img, $contentX + 3, $y + 10, $contentX + 7, $y + 14, $black);
                imageline($img, $contentX + 7, $y + 14, $contentX + 15, $y + 4, $black);
                imagesetthickness($img, 1);
            }
            imagettftext($img, 11, 0, $contentX + 25, $y + 14, $black, $fontRegular, "อยู่ในสภาพปิดผนึกเรียบร้อย");
            $y += 28;
            
            // Checkbox 2: อื่น ๆ
            imagerectangle($img, $contentX, $y, $contentX + $chkSize, $y + $chkSize, $black);
            if ($condOther) {
                imagesetthickness($img, 2);
                imageline($img, $contentX + 3, $y + 10, $contentX + 7, $y + 14, $black);
                imageline($img, $contentX + 7, $y + 14, $contentX + 15, $y + 4, $black);
                imagesetthickness($img, 1);
            }
            imagettftext($img, 11, 0, $contentX + 25, $y + 14, $black, $fontRegular, "อื่น ๆ");
            imagettftext($img, 11, 0, $contentX + 75, $y + 14, $black, $fontRegular, $condOtherText);
            
            // ===== QR Code สำหรับหน้า 2 (ใช้ ZPL native) =====
            $GLOBALS['qrCodeData'] = $caseNo;
            $GLOBALS['qrCodeX'] = (int)($contentX + ($contentW / 2) - 60);
            $GLOBALS['qrCodeY'] = (int)($targetH - 150);
            
            goto convertToZpl;
        }
        
        // ========== หน้า Evidence (หน้า 1 ถึง N-1) ==========
        // คำนวณ evidence ที่จะแสดงในหน้านี้
        $startIdx = ($pageNum - 1) * $maxEvidencePerPage;
        $pageEvidences = array_slice($evidences, $startIdx, $maxEvidencePerPage);
        
        // หน้าแรกแสดง victim table และข้อมูลเพิ่มเติม
        if ($pageNum == 1) {
            // ผู้ต้องหา / ผู้ต้องสงสัย / ผู้เสียหาย
            imagettftext($img, 12, 0, $contentX, $y, $black, $fontBold, "ผู้ต้องหา / ผู้ต้องสงสัย / ผู้เสียหาย");
            $y += 26;
            
            // ตาราง victim (ปรับให้เหมาะกับ 832 dots width)
            $tableX = $contentX;
            $tableW = $contentW;
            $colW = [50, 115, 300, 55]; // ลำดับ, ประเภท, ชื่อ-นามสกุล, อายุ
            $rowH = 32;
            
            // Header
            imagefilledrectangle($img, $tableX, $y, $tableX + $tableW, $y + $rowH, $headerBg);
            imagerectangle($img, $tableX, $y, $tableX + $tableW, $y + $rowH, $black);
            
            $hx = $tableX;
            imagettftext($img, 11, 0, $hx + 8, $y + 20, $black, $fontBold, "ลำดับ");
            imageline($img, $hx + $colW[0], $y, $hx + $colW[0], $y + $rowH, $black);
            $hx += $colW[0];
            imagettftext($img, 11, 0, $hx + 10, $y + 20, $black, $fontBold, "ประเภท");
            imageline($img, $hx + $colW[1], $y, $hx + $colW[1], $y + $rowH, $black);
            $hx += $colW[1];
            imagettftext($img, 11, 0, $hx + 10, $y + 20, $black, $fontBold, "ชื่อ-นามสกุล");
            imageline($img, $hx + $colW[2], $y, $hx + $colW[2], $y + $rowH, $black);
            $hx += $colW[2];
            imagettftext($img, 11, 0, $hx + 10, $y + 20, $black, $fontBold, "อายุ");
            $y += $rowH;
            
            // Data rows (1 แถว)
            if (empty($victims)) {
                $victims = [['type' => '-', 'name' => '-', 'age' => '-']];
            }
            foreach ($victims as $i => $v) {
                if ($i >= 1) break; // จำกัด 1 แถว
                imagerectangle($img, $tableX, $y, $tableX + $tableW, $y + $rowH, $black);
                $hx = $tableX;
                imagettftext($img, 11, 0, $hx + 18, $y + 20, $black, $fontRegular, (string)($i + 1));
                imageline($img, $hx + $colW[0], $y, $hx + $colW[0], $y + $rowH, $black);
                $hx += $colW[0];
                imagettftext($img, 10, 0, $hx + 8, $y + 20, $black, $fontRegular, mb_substr($v['type'], 0, 12, 'UTF-8'));
                imageline($img, $hx + $colW[1], $y, $hx + $colW[1], $y + $rowH, $black);
                $hx += $colW[1];
                imagettftext($img, 10, 0, $hx + 8, $y + 20, $black, $fontRegular, mb_substr($v['name'], 0, 35, 'UTF-8'));
                imageline($img, $hx + $colW[2], $y, $hx + $colW[2], $y + $rowH, $black);
                $hx += $colW[2];
                imagettftext($img, 11, 0, $hx + 15, $y + 20, $black, $fontRegular, $v['age']);
                $y += $rowH;
            }
            
            $y += 20; // เพิ่มระยะห่างก่อนวันที่เก็บวัตถุพยาน
            
            // วันที่เก็บวัตถุพยาน + เวลา (บรรทัดเดียว เพิ่มระยะห่าง)
            imagettftext($img, 12, 0, $contentX, $y, $black, $fontBold, "วันที่เก็บวัตถุพยาน");
            imagettftext($img, 12, 0, $contentX + 125, $y, $black, $fontRegular, $collectDate);
            imagettftext($img, 12, 0, $contentX + 320, $y, $black, $fontBold, "เวลาประมาณ");
            imagettftext($img, 12, 0, $contentX + 420, $y, $black, $fontRegular, $collectTime . " น.");
            $y += 28;
            
            // ชื่อผู้เก็บวัตถุพยาน
            imagettftext($img, 12, 0, $contentX, $y, $black, $fontBold, "ชื่อผู้เก็บวัตถุพยาน");
            $y += 28;
        }
        
        // ลักษณะ / จำนวน / ตำแหน่ง
        $labelText = ($pageNum == 1) ? "ลักษณะ / จำนวน / ตำแหน่ง วัตถุพยานที่ตรวจพบ" : "ลักษณะ / จำนวน / ตำแหน่ง วัตถุพยานที่ตรวจพบ (ต่อ)";
        imagettftext($img, 12, 0, $contentX, $y, $black, $fontBold, $labelText);
        $y += 28;
        
        $tableX = $contentX;
        $tableW = $contentW;
        
        // ตาราง evidence (ปรับให้เหมาะกับ 832 dots width)
        $colW = [45, 300, 65, 110]; // ลำดับ, รายละเอียด, จำนวน, ตำแหน่ง
        $rowH = 42;
        $headerH = 30;
        
        // Header
        imagefilledrectangle($img, $tableX, $y, $tableX + $tableW, $y + $headerH, $headerBg);
        imagerectangle($img, $tableX, $y, $tableX + $tableW, $y + $headerH, $black);
        
        $hx = $tableX;
        imagettftext($img, 11, 0, $hx + 5, $y + 20, $black, $fontBold, "ลำดับ");
        imageline($img, $hx + $colW[0], $y, $hx + $colW[0], $y + $headerH, $black);
        $hx += $colW[0];
        imagettftext($img, 11, 0, $hx + 10, $y + 20, $black, $fontBold, "รายละเอียดวัตถุพยาน");
        imageline($img, $hx + $colW[1], $y, $hx + $colW[1], $y + $headerH, $black);
        $hx += $colW[1];
        imagettftext($img, 11, 0, $hx + 5, $y + 20, $black, $fontBold, "จำนวน");
        imageline($img, $hx + $colW[2], $y, $hx + $colW[2], $y + $headerH, $black);
        $hx += $colW[2];
        imagettftext($img, 11, 0, $hx + 5, $y + 20, $black, $fontBold, "ตำแหน่งที่พบ");
        $y += $headerH;
        
        // Data rows (แสดง evidence ของหน้านี้)
        foreach ($pageEvidences as $i => $ev) {
            $rowNum = $startIdx + $i + 1; // ลำดับต่อเนื่องจากหน้าก่อน
            imagerectangle($img, $tableX, $y, $tableX + $tableW, $y + $rowH, $black);
            $hx = $tableX;
            imagettftext($img, 11, 0, $hx + 15, $y + 25, $black, $fontRegular, (string)$rowNum);
            imageline($img, $hx + $colW[0], $y, $hx + $colW[0], $y + $rowH, $black);
            $hx += $colW[0];
            // รายละเอียด (ตัด 2 บรรทัด)
            $detailText = $ev['detail'];
            $line1 = mb_substr($detailText, 0, 35, 'UTF-8');
            $line2 = mb_substr($detailText, 35, 35, 'UTF-8');
            imagettftext($img, 10, 0, $hx + 5, $y + 16, $black, $fontRegular, $line1);
            if (!empty($line2)) {
                imagettftext($img, 10, 0, $hx + 5, $y + 32, $black, $fontRegular, $line2);
            }
            imageline($img, $hx + $colW[1], $y, $hx + $colW[1], $y + $rowH, $black);
            $hx += $colW[1];
            imagettftext($img, 11, 0, $hx + 15, $y + 25, $black, $fontRegular, $ev['qty']);
            imageline($img, $hx + $colW[2], $y, $hx + $colW[2], $y + $rowH, $black);
            $hx += $colW[2];
            imagettftext($img, 10, 0, $hx + 5, $y + 25, $black, $fontRegular, mb_substr($ev['position'], 0, 12, 'UTF-8'));
            $y += $rowH;
        }
        
        // ===== QR Code จะถูกเพิ่มใน ZPL โดยตรง (ไม่วาดใน GD) =====
        // เก็บข้อมูล QR ไว้ใช้ตอนสร้าง ZPL
        $GLOBALS['qrCodeData'] = $caseNo;
        $GLOBALS['qrCodeX'] = (int)($contentX + ($contentW / 2) - 60);
        $GLOBALS['qrCodeY'] = (int)($targetH - 150);
        
    } else {
        // Fallback: ใช้ built-in font
        imagestring($img, 5, $contentX + 180, 30, "EVIDENCE", $black);
        imagestring($img, 4, $contentX, 70, "Station: " . substr($stationName, 0, 30), $black);
        imagestring($img, 4, $contentX, 100, "Case: " . $caseNo, $black);
    }
    
    convertToZpl:
    // แปลงเป็น ZPL
    $bytesPerRow = (int)ceil($targetW / 8);
    $totalBytes = $bytesPerRow * $targetH;
    $hexStr = '';

    for ($y = 0; $y < $targetH; $y++) {
        for ($bx = 0; $bx < $bytesPerRow; $bx++) {
            $byteVal = 0;
            for ($bit = 0; $bit < 8; $bit++) {
                $px = $bx * 8 + $bit;
                if ($px < $targetW) {
                    $rgb = imagecolorat($img, $px, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    $grayVal = $r * 0.299 + $g * 0.587 + $b * 0.114;
                    if ($grayVal < 170) {
                        $byteVal |= (1 << (7 - $bit));
                    }
                }
            }
            $hexStr .= strtoupper(str_pad(dechex($byteVal), 2, '0', STR_PAD_LEFT));
        }
    }
    
    imagedestroy($img);

    // สร้าง ZPL output
    $zpl = "^XA\n";
    $zpl .= "^MD15\n";
    $zpl .= "^LH0,0\n";
    $zpl .= "^PW{$targetW}\n";
    $zpl .= "^LL{$targetH}\n";
    $zpl .= "^LS0\n";
    $zpl .= "^FO0,0\n";
    $zpl .= "^GFA,{$totalBytes},{$totalBytes},{$bytesPerRow},{$hexStr}\n";
    $zpl .= "^FS\n";
    
    // เพิ่ม QR Code ด้วย ZPL native command
    if (!empty($GLOBALS['qrCodeData'])) {
        $qrX = $GLOBALS['qrCodeX'] ?? 300;
        $qrY = $GLOBALS['qrCodeY'] ?? 1050;
        $qrData = $GLOBALS['qrCodeData'];
        $zpl .= "^FO{$qrX},{$qrY}\n";
        $zpl .= "^BQN,2,5\n"; // QR Code, Model 2, Magnification 5
        $zpl .= "^FDMM,A{$qrData}^FS\n"; // MM = Mixed mode, A = Automatic
    }
    
    $zpl .= "^XZ\n";

    return $zpl;
}

/**
 * แปลงวันที่เป็นภาษาไทยแบบเต็ม (สำหรับ GD)
 */
function thaiDateFullGD($datetime) {
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $d = date('j', $ts);
    $m = (int)date('n', $ts);
    $y = date('Y', $ts) + 543;
    return $d . ' ' . $months[$m] . ' ' . $y;
}

/**
 * แปลงวันที่เป็นภาษาไทยแบบย่อ (สำหรับ GD)
 */
function thaiDateShortGD($datetime) {
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    $months = [null, 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $year = date('Y', $ts) + 543;
    $shortYear = substr($year, -2);
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . $shortYear;
}
