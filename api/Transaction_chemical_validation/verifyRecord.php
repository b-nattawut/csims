<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

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

// 1. ดึงข้อมูลรายการนี้ขึ้นมาก่อนเพื่อเช็ค verifier_id โดยจะให้เฉพาะ user login ที่ตรงกับ verified id ที่ระบุไว้เท่านั้นที่จะสามารถเซ็นได้
$checkSql = "SELECT verifier_id, is_verified FROM trans_chemical_validation WHERE id = ? AND status_delete = 0";
$checkStmt = $pdo->prepare($checkSql);
$checkStmt->execute([$id]);
$record = $checkStmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลรายการทดสอบนี้']);
    exit;
}

// 2. เช็คว่าคนที่กดบันทึก (Session) ตรงกับ Verifier ที่ระบุไว้ไหม
if ($_SESSION['user_id'] != $record['verifier_id']) {
    echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ทวนสอบรายการนี้ (ต้องเป็นผู้ทวนสอบที่ระบุเท่านั้น)']);
    exit;
}

// 3. เช็คว่าเซ็นไปแล้วหรือยัง (กันเซ็นซ้ำ)
if ($record['is_verified'] == 1) {
    echo json_encode(['status' => 'error', 'message' => 'รายการนี้ได้รับการทวนสอบไปแล้ว']);
    exit;
}

try {
    // 3. จัดการแปลง Base64 เป็นไฟล์รูปภาพ
    // ตัดส่วนหัว "data:image/png;base64," ออก
    $imgData = str_replace('data:image/png;base64,', '', $signatureBase64);
    $imgData = str_replace(' ', '+', $imgData);
    $data = base64_decode($imgData);

    if ($data === false) {
        throw new Exception('รูปแบบรูปภาพลายเซ็นไม่ถูกต้อง');
    }

    $uploadDir = '../../uploads/signatures/';

    // ตรวจสอบและสร้างโฟลเดอร์ให้อัตโนมัติ หากยังไม่มีโฟลเดอร์นี้ในระบบ
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // ตั้งชื่อไฟล์ (ระบุ ID และ Timestamp กันชื่อซ้ำ)
    $fileName = 'sig_val_' . $id . '_' . date('Ymd_His') . '.png';
    $filePath = $uploadDir . $fileName;

    // บันทึกไฟล์ลง Server
    if (!file_put_contents($filePath, $data)) {
        throw new Exception('ไม่สามารถบันทึกไฟล์รูปภาพได้');
    }

    // 4. อัปเดตฐานข้อมูล
    $sql = "UPDATE trans_chemical_validation 
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
        echo json_encode(['status' => 'success', 'message' => 'ทวนสอบและบันทึกลายเซ็นเรียบร้อยแล้ว']);
    } else {
        @unlink($filePath);
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถอัปเดตข้อมูลได้']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
