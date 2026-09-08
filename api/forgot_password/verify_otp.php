<?php
/**
 * API: ตรวจสอบ OTP สำหรับลืมรหัสผ่าน
 * POST: { "email": "user@example.com", "otp": "123456" }
 * 
 * Flow:
 *   1. ตรวจสอบ input
 *   2. ดึง OTP จาก DB ตาม email
 *   3. ตรวจว่ายังไม่หมดอายุ
 *   4. เปรียบเทียบ OTP (timing-safe)
 *   5. ถ้าถูก → set session flag อนุญาตให้ reset password
 *   6. ถ้าผิด → นับครั้ง (ล็อกหลัง 5 ครั้ง)
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_config.php';

define('MAX_ATTEMPTS', 5);
define('LOCK_SECONDS', 300); // ล็อก 5 นาที

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $email   = isset($data['email']) ? trim($data['email']) : '';
    $otpCode = isset($data['otp']) ? trim($data['otp']) : '';

    // === Validate ===
    if (empty($email) || empty($otpCode)) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }

    if (!preg_match('/^\d{6}$/', $otpCode)) {
        echo json_encode(['success' => false, 'message' => 'รหัส OTP ต้องเป็นตัวเลข 6 หลัก']);
        exit;
    }

    // === ตรวจสอบ brute-force lock ===
    $lockKey = 'reset_locked_' . md5($email);
    $attemptKey = 'reset_attempts_' . md5($email);

    if (isset($_SESSION[$lockKey]) && time() < $_SESSION[$lockKey]) {
        $remaining = $_SESSION[$lockKey] - time();
        echo json_encode([
            'success' => false,
            'message' => "กรอก OTP ผิดเกินกำหนด กรุณารอ {$remaining} วินาที แล้วขอรหัสใหม่",
            'locked' => true
        ]);
        exit;
    }

    // === หา user_id จาก email ===
    $stmtUser = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmtUser->execute([$email]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบบัญชีผู้ใช้']);
        exit;
    }

    $userId = (int)$user['user_id'];

    // === ดึง OTP จาก DB ===
    $stmt = $pdo->prepare("
        SELECT id, otp_code, expire_at 
        FROM user_otp 
        WHERE user_id = ? AND email = ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$userId, $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบรหัส OTP กรุณาขอรหัสใหม่']);
        exit;
    }

    // === ตรวจหมดอายุ ===
    if (time() > strtotime($row['expire_at'])) {
        $pdo->prepare("DELETE FROM user_otp WHERE id = ?")->execute([$row['id']]);
        echo json_encode(['success' => false, 'message' => 'รหัส OTP หมดอายุแล้ว กรุณาขอรหัสใหม่', 'expired' => true]);
        exit;
    }

    // === เปรียบเทียบ OTP (timing-safe) ===
    if (!hash_equals($row['otp_code'], $otpCode)) {
        $_SESSION[$attemptKey] = ($_SESSION[$attemptKey] ?? 0) + 1;

        if ($_SESSION[$attemptKey] >= MAX_ATTEMPTS) {
            $_SESSION[$lockKey] = time() + LOCK_SECONDS;
            $_SESSION[$attemptKey] = 0;
            $pdo->prepare("DELETE FROM user_otp WHERE user_id = ?")->execute([$userId]);

            echo json_encode([
                'success' => false,
                'message' => 'กรอก OTP ผิดเกินกำหนด กรุณารอ 5 นาที แล้วขอรหัสใหม่',
                'locked' => true
            ]);
            exit;
        }

        $left = MAX_ATTEMPTS - $_SESSION[$attemptKey];
        echo json_encode([
            'success' => false,
            'message' => "รหัส OTP ไม่ถูกต้อง (เหลืออีก {$left} ครั้ง)"
        ]);
        exit;
    }

    // === OTP ถูกต้อง ===
    // ล้าง attempt counter
    unset($_SESSION[$attemptKey], $_SESSION[$lockKey]);

    // ตั้ง session flag อนุญาตให้ reset password (ใช้ได้ครั้งเดียว ภายใน 10 นาที)
    $_SESSION['reset_verified'] = true;
    $_SESSION['reset_user_id'] = $userId;
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_verified_at'] = time();

    // ไม่ลบ OTP ตรงนี้ — ให้ reset_password.php ลบหลัง reset สำเร็จ (double-check)

    echo json_encode([
        'success' => true,
        'message' => 'ยืนยัน OTP สำเร็จ'
    ]);

} catch (\Throwable $e) {
    error_log('Forgot Password Verify OTP Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่'
    ]);
}
