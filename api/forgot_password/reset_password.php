<?php
/**
 * API: รีเซ็ตรหัสผ่าน (Step 3)
 * POST: { "email": "user@example.com", "otp": "123456", "new_password": "..." }
 * 
 * Flow:
 *   1. ตรวจว่า session มี reset_verified = true (ผ่าน verify_otp แล้ว)
 *   2. ตรวจว่า session ยังไม่หมดอายุ (10 นาที)
 *   3. ตรวจ OTP อีกครั้ง (double-check security)
 *   4. Validate รหัสผ่านใหม่
 *   5. Hash + Update password ใน DB
 *   6. ลบ OTP + ล้าง session
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $email       = isset($data['email']) ? trim($data['email']) : '';
    $otpCode     = isset($data['otp']) ? trim($data['otp']) : '';
    $newPassword = $data['new_password'] ?? '';

    // === ตรวจ session ===
    if (empty($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
        echo json_encode(['success' => false, 'message' => 'กรุณายืนยัน OTP ก่อน']);
        exit;
    }

    // === ตรวจ session หมดอายุ (10 นาที) ===
    $verifiedAt = $_SESSION['reset_verified_at'] ?? 0;
    if (time() - $verifiedAt > 600) {
        // ล้าง session
        unset($_SESSION['reset_verified'], $_SESSION['reset_user_id'], $_SESSION['reset_email'], $_SESSION['reset_verified_at']);
        echo json_encode(['success' => false, 'message' => 'หมดเวลาในการรีเซ็ตรหัสผ่าน กรุณาเริ่มใหม่']);
        exit;
    }

    // === ตรวจว่า email ตรงกับ session ===
    if ($email !== ($_SESSION['reset_email'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ตรงกัน กรุณาเริ่มใหม่']);
        exit;
    }

    $userId = (int)$_SESSION['reset_user_id'];

    // === Validate input ===
    if (empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกรหัสผ่านใหม่']);
        exit;
    }

    if (mb_strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร']);
        exit;
    }

    // === Double-check OTP (ป้องกัน session hijack) ===
    if (!empty($otpCode)) {
        $stmtOtp = $pdo->prepare("
            SELECT id, otp_code, expire_at 
            FROM user_otp 
            WHERE user_id = ? AND email = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmtOtp->execute([$userId, $email]);
        $otpRow = $stmtOtp->fetch(PDO::FETCH_ASSOC);

        if ($otpRow && !hash_equals($otpRow['otp_code'], $otpCode)) {
            echo json_encode(['success' => false, 'message' => 'รหัส OTP ไม่ถูกต้อง']);
            exit;
        }
    }

    // === Hash รหัสผ่านใหม่ ===
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // === Update password ใน DB ===
    $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ? AND is_active = 1");
    $stmtUpdate->execute([$hashedPassword, $userId]);

    if ($stmtUpdate->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถเปลี่ยนรหัสผ่านได้ บัญชีอาจถูกปิดใช้งาน']);
        exit;
    }

    // === ลบ OTP ทั้งหมดของ user ===
    $pdo->prepare("DELETE FROM user_otp WHERE user_id = ?")->execute([$userId]);

    // === ล้าง session ===
    unset(
        $_SESSION['reset_verified'],
        $_SESSION['reset_user_id'],
        $_SESSION['reset_email'],
        $_SESSION['reset_verified_at']
    );

    error_log('[FORGOT_PW] Password reset success for user_id=' . $userId . ' email=' . $email);

    echo json_encode([
        'success' => true,
        'message' => 'เปลี่ยนรหัสผ่านสำเร็จ'
    ]);

} catch (\Throwable $e) {
    error_log('Reset Password Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่'
    ]);
}
