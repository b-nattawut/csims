<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบความปลอดภัย (Security Check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

$user_id = $_SESSION['user_id'];

// ตรวจสอบสถานะบัญชีผู้ใช้งาน (ต้อง active อยู่เท่านั้น)
$stmtUser = $pdo->prepare("SELECT is_active FROM users WHERE user_id = ?");
$stmtUser->execute([$user_id]);
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$userData || $userData['is_active'] != 1) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'บัญชีผู้ใช้งานของคุณถูกระงับ หรือไม่พบข้อมูล']);
    exit;
}

// 2. รับค่าจาก POST
$id = $_POST['id'] ?? null;
$signatureBase64 = $_POST['signature'] ?? null;

if (!$id || !$signatureBase64) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วนสำหรับการทวนสอบ']);
    exit;
}

try {
    // 3. ตรวจสอบสถานะและสิทธิ์ผู้ใช้งานก่อนทำการบันทึก
    // ค้นหา verifier_id เพื่อเช็คว่าคนที่เซ็นคือคนที่ได้รับมอบหมายจริงไหม
    $checkSql = "SELECT verifier_id, is_verified 
                 FROM trans_latent_chemical_test 
                 WHERE id = ? AND status_delete = 0";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$id]);
    $record = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new Exception('ไม่พบข้อมูลรายการที่ต้องการทวนสอบ');
    }

    // ตรวจสอบ: เฉพาะผู้ทวนสอบที่ระบุในรายการเท่านั้นที่มีสิทธิ์เซ็น
    if ($_SESSION['user_id'] != $record['verifier_id']) {
        throw new Exception('คุณไม่มีสิทธิ์ทวนสอบรายการนี้ (ต้องเป็นผู้ทวนสอบที่ระบุเท่านั้น)');
    }

    // ตรวจสอบ: กันการเซ็นซ้ำ (Double-sign protection)
    if ((int)$record['is_verified'] === 1) {
        throw new Exception('รายการนี้ได้รับการทวนสอบและลงนามเรียบร้อยแล้ว');
    }

    // 4. จัดการแปลง Base64 เป็นไฟล์รูปภาพ PNG
    // ตัดส่วนหัว "data:image/png;base64," และจัดการช่องว่าง
    $imgData = str_replace('data:image/png;base64,', '', $signatureBase64);
    $imgData = str_replace(' ', '+', $imgData);
    $binaryData = base64_decode($imgData);

    if ($binaryData === false) {
        throw new Exception('รูปแบบรูปภาพลายเซ็นไม่ถูกต้อง');
    }

    $folderPath = '../../uploads/signatures/';

    // ตรวจสอบและสร้างโฟลเดอร์ให้อัตโนมัติ หากยังไม่มีโฟลเดอร์ในระบบ
    if (!is_dir($folderPath)) {
        mkdir($folderPath, 0755, true);
    }

    // ตั้งชื่อไฟล์ (ระบุ Prefix เป็น latent เพื่อให้แยกจากระบบอื่นได้ชัดเจน)
    $fileName = 'sig_latent_' . $id . '_' . date('Ymd_His') . '.png';
    $filePath = $folderPath . $fileName;

    // บันทึกไฟล์ลง Server
    if (!file_put_contents($filePath, $binaryData)) {
        throw new Exception('ไม่สามารถบันทึกไฟล์รูปภาพลายเซ็นลงในเซิร์ฟเวอร์ได้');
    }

    // 5. อัปเดตฐานข้อมูล (Lock รายการ)
    $sql = "UPDATE trans_latent_chemical_test 
            SET is_verified = 1, 
                verifier_signature = :sig_file, 
                verifier_signature_date = NOW(),
                update_by = :user_id,
                update_date = NOW()
            WHERE id = :id AND status_delete = 0";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':sig_file' => $fileName,
        ':user_id'  => $_SESSION['user_id'],
        ':id'       => $id
    ]);

    if ($result) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'ทวนสอบความพร้อมและบันทึกลายเซ็นเรียบร้อยแล้ว'
        ]);
    } else {
        // หาก Update SQL พลั้งพลาด ให้ลบไฟล์รูปภาพที่เพิ่งสร้างทิ้งเพื่อไม่ให้รก Server
        @unlink($filePath);
        throw new Exception('ไม่สามารถอัปเดตสถานะในฐานข้อมูลได้');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}