<?php
/**
 * API: ตรวจสอบ OTP ที่ user กรอกเข้ามา
 * POST: { "user_id": 1, "email": "user@example.com", "otp_code": "123456" }
 * 
 * Flow:
 *   1. ตรวจสอบ input
 *   2. ดึง OTP จาก DB ตาม user_id + email
 *   3. ตรวจว่ายังไม่หมดอายุ
 *   4. เปรียบเทียบรหัส OTP (timing-safe)
 *   5. ถ้าถูกต้อง → ลบ OTP, ตั้ง session, redirect ไป dashboard
 *   6. ถ้าผิด → นับจำนวนครั้งที่ลองผิด (ล็อกหลัง 5 ครั้ง)
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db_config.php';

// จำนวนครั้งสูงสุดที่กรอกผิดได้
define('MAX_OTP_ATTEMPTS', 5);

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId  = isset($data['user_id']) ? intval($data['user_id']) : 0;
    $email   = isset($data['email']) ? trim($data['email']) : '';
    $otpCode = isset($data['otp_code']) ? trim($data['otp_code']) : '';

    // === Validate input ===
    if ($userId <= 0 || empty($email) || empty($otpCode)) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }

    if (!preg_match('/^\d{6}$/', $otpCode)) {
        echo json_encode(['success' => false, 'message' => 'รหัส OTP ต้องเป็นตัวเลข 6 หลัก']);
        exit;
    }

    // === ตรวจสอบ brute-force (ใช้ session นับ) ===
    $attemptKey = 'otp_attempts_' . $userId;
    $lockKey    = 'otp_locked_until_' . $userId;

    if (isset($_SESSION[$lockKey]) && time() < $_SESSION[$lockKey]) {
        $remaining = $_SESSION[$lockKey] - time();
        echo json_encode([
            'success' => false,
            'message' => "กรอก OTP ผิดเกินกำหนด กรุณารอ {$remaining} วินาที",
            'locked' => true,
            'locked_seconds' => $remaining
        ]);
        exit;
    }

    // === ดึง OTP จาก DB ===
    $stmt = $pdo->prepare("
        SELECT id, otp_code, expire_at 
        FROM user_otp 
        WHERE user_id = ? AND email = ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$userId, $email]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบรหัส OTP กรุณาขอรหัสใหม่']);
        exit;
    }

    // === ตรวจสอบหมดอายุ ===
    $expireAt = strtotime($row['expire_at']);
    if (time() > $expireAt) {
        // ลบ OTP ที่หมดอายุ
        $pdo->prepare("DELETE FROM user_otp WHERE id = ?")->execute([$row['id']]);
        echo json_encode(['success' => false, 'message' => 'รหัส OTP หมดอายุแล้ว กรุณาขอรหัสใหม่', 'expired' => true]);
        exit;
    }

    // === เปรียบเทียบ OTP (timing-safe) ===
    if (!hash_equals($row['otp_code'], $otpCode)) {
        // นับจำนวนครั้งที่ลองผิด
        $_SESSION[$attemptKey] = ($_SESSION[$attemptKey] ?? 0) + 1;

        if ($_SESSION[$attemptKey] >= MAX_OTP_ATTEMPTS) {
            // ล็อก 5 นาที
            $_SESSION[$lockKey] = time() + 300;
            $_SESSION[$attemptKey] = 0;

            // ลบ OTP เพื่อบังคับให้ขอใหม่
            $pdo->prepare("DELETE FROM user_otp WHERE user_id = ?")->execute([$userId]);

            echo json_encode([
                'success' => false,
                'message' => 'กรอก OTP ผิดเกินกำหนด กรุณารอ 5 นาทีแล้วขอรหัสใหม่',
                'locked' => true,
                'locked_seconds' => 300
            ]);
            exit;
        }

        $attemptsLeft = MAX_OTP_ATTEMPTS - $_SESSION[$attemptKey];
        echo json_encode([
            'success' => false,
            'message' => "รหัส OTP ไม่ถูกต้อง (เหลืออีก {$attemptsLeft} ครั้ง)",
            'attempts_left' => $attemptsLeft
        ]);
        exit;
    }

    // === OTP ถูกต้อง ===
    
    // ลบ OTP ออกจาก DB (ใช้ได้ครั้งเดียว)
    $pdo->prepare("DELETE FROM user_otp WHERE user_id = ?")->execute([$userId]);

    // ล้าง attempt counter
    unset($_SESSION[$attemptKey], $_SESSION[$lockKey]);

    // ดึงข้อมูล user สำหรับ set session
    $stmtUser = $pdo->prepare("
        SELECT 
            u.user_id, u.email, u.role,
            up.first_name, up.last_name,
            ur.rank_name,
            upos.position_name
        FROM users u
        LEFT JOIN user_profile up ON u.user_id = up.user_id
        LEFT JOIN user_rank ur ON up.rank_id = ur.rank_id
        LEFT JOIN user_position upos ON up.position_id = upos.position_id
        WHERE u.user_id = ? AND u.is_active = 1
    ");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบบัญชีผู้ใช้หรือบัญชีถูกปิดใช้งาน']);
        exit;
    }

    // ตั้งค่า Session (เหมือน login.php)
    $_SESSION['user_id']       = $user['user_id'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['first_name']    = $user['first_name'] ?? '';
    $_SESSION['last_name']     = $user['last_name'] ?? '';
    $_SESSION['rank_name']     = $user['rank_name'] ?? '';
    $_SESSION['position_name'] = $user['position_name'] ?? '';
    $_SESSION['otp_verified']  = true;

    echo json_encode([
        'success' => true,
        'message' => 'ยืนยันตัวตนสำเร็จ',
        'redirect' => 'dashboard.php'
    ]);

} catch (Exception $e) {
    error_log('OTP Verify Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง'
    ]);
}
