<?php
/**
 * API: ส่ง OTP สำหรับลืมรหัสผ่าน
 * POST: { "email": "user@example.com" }
 * 
 * Flow:
 *   1. ตรวจสอบว่า email มีอยู่ในระบบ
 *   2. Rate limit: ไม่ให้ส่งซ้ำภายใน 60 วินาที
 *   3. สร้าง OTP 6 หลัก
 *   4. ลบ OTP เก่า, บันทึก OTP ใหม่ (หมดอายุ 5 นาที)
 *   5. ส่ง OTP ทางอีเมลด้วย PHPMailer (Mailjet SMTP)
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = isset($data['email']) ? trim($data['email']) : '';

    // === Validate ===
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกอีเมล']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง']);
        exit;
    }

    // === ตรวจว่า email มีในระบบหรือไม่ ===
    $stmtUser = $pdo->prepare("SELECT user_id, email, is_active FROM users WHERE email = ? LIMIT 1");
    $stmtUser->execute([$email]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // ไม่บอกชัดว่าไม่มี email เพื่อป้องกัน email enumeration
        echo json_encode(['success' => false, 'message' => 'หากอีเมลนี้มีอยู่ในระบบ คุณจะได้รับรหัส OTP ทางอีเมล']);
        exit;
    }

    if ((int)$user['is_active'] === 0) {
        echo json_encode(['success' => false, 'message' => 'บัญชีนี้ถูกปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ']);
        exit;
    }

    $userId = (int)$user['user_id'];

    // === Rate Limit: ไม่ให้ส่งซ้ำภายใน 60 วินาที ===
    $stmtRate = $pdo->prepare("
        SELECT expire_at FROM user_otp 
        WHERE user_id = ? AND email = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $stmtRate->execute([$userId, $email]);
    $lastOtp = $stmtRate->fetch();

    if ($lastOtp) {
        $createdApprox = strtotime($lastOtp['expire_at']) - (5 * 60);
        $now = time();
        if (($now - $createdApprox) < 60) {
            $wait = 60 - ($now - $createdApprox);
            echo json_encode([
                'success' => false,
                'message' => "กรุณารอ {$wait} วินาที ก่อนส่งรหัสอีกครั้ง"
            ]);
            exit;
        }
    }

    // === Generate OTP 6 digits ===
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // === ลบ OTP เก่า ===
    $stmtDel = $pdo->prepare("DELETE FROM user_otp WHERE user_id = ?");
    $stmtDel->execute([$userId]);

    // === บันทึก OTP ใหม่ (หมดอายุ 5 นาที) ===
    $expireMinutes = 5;
    $stmtIns = $pdo->prepare("
        INSERT INTO user_otp (user_id, email, otp_code, expire_at) 
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
    ");
    $stmtIns->execute([$userId, $email, $otpCode, $expireMinutes]);

    // === โหลด .env config ===
    $envFile = __DIR__ . '/../../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            list($key, $val) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val);
        }
    }

    // === ส่งอีเมลด้วย PHPMailer ผ่าน Mailjet SMTP ===
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->SMTPDebug  = 0;
    $mail->Host       = 'in-v3.mailjet.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['MAILJET_API_KEY'] ?? '';
    $mail->Password   = $_ENV['MAILJET_SECRET_KEY'] ?? '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $fromEmail = $_ENV['MAIL_FROM_ADDRESS'] ?? 'csims.rtp@gmail.com';
    $fromName  = $_ENV['MAIL_FROM_NAME'] ?? 'CSIMS - ระบบจัดการข้อมูลสืบสวน';
    $mail->setFrom($fromEmail, $fromName);
    $mail->addReplyTo($fromEmail, $fromName);
    $mail->addAddress($email);

    $mail->XMailer   = 'CSIMS Mailer';
    $mail->Priority  = 1;
    $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');

    $mail->isHTML(true);
    $mail->Subject = 'CSIMS - รหัสยืนยันการรีเซ็ตรหัสผ่าน';
    $mail->Body = getResetOtpEmailTemplate($otpCode, $expireMinutes);
    $mail->AltBody = "รหัส OTP สำหรับรีเซ็ตรหัสผ่าน: {$otpCode} (หมดอายุใน {$expireMinutes} นาที) - ห้ามแจ้งรหัสนี้ให้ผู้อื่น";

    $mail->send();
    error_log('[FORGOT_PW] OTP sent → ' . $email);

    // === เก็บ user_id ใน session สำหรับ step ถัดไป ===
    $_SESSION['reset_user_id'] = $userId;
    $_SESSION['reset_email'] = $email;

    echo json_encode([
        'success' => true,
        'message' => 'ส่งรหัส OTP ไปยังอีเมลของคุณแล้ว'
    ]);

} catch (\Throwable $e) {
    error_log('Forgot Password Send OTP Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการส่ง OTP กรุณาลองใหม่'
    ]);
}

// === Email Template ===
function getResetOtpEmailTemplate($otpCode, $expireMinutes) {
    $digits = str_split($otpCode);
    $digitBoxes = '';
    foreach ($digits as $d) {
        $digitBoxes .= '<td style="width:42px;height:50px;background:#0e1420;border:2px solid #00d4ff;border-radius:10px;text-align:center;font-size:26px;font-weight:700;color:#00d4ff;font-family:monospace;letter-spacing:2px;">' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '</td>';
    }

    return '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#080c14;font-family:Arial,Helvetica,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#080c14;padding:40px 0;">
            <tr><td align="center">
                <table width="480" cellpadding="0" cellspacing="0" style="background:#0e1420;border:1px solid rgba(0,212,255,0.15);border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0057ff,#00d4ff);padding:30px 40px;text-align:center;">
                            <div style="font-size:22px;font-weight:700;color:#fff;margin-bottom:4px;">CSIMS</div>
                            <div style="font-size:13px;color:rgba(255,255,255,0.8);">Crime Scene Investigation Management System</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 40px;">
                            <div style="font-size:18px;font-weight:700;color:#e8edf5;margin-bottom:12px;">รีเซ็ตรหัสผ่าน</div>
                            <div style="font-size:14px;color:#6b7a99;line-height:1.7;margin-bottom:28px;">
                                คุณได้ร้องขอการรีเซ็ตรหัสผ่าน กรุณานำรหัส OTP ด้านล่างไปกรอกในหน้ายืนยัน<br>
                                รหัสนี้จะหมดอายุใน <strong style="color:#00d4ff;">' . $expireMinutes . ' นาที</strong>
                            </div>
                            <table cellpadding="0" cellspacing="6" style="margin:0 auto 28px auto;">
                                <tr>' . $digitBoxes . '</tr>
                            </table>
                            <div style="font-size:12px;color:#6b7a99;text-align:center;margin-bottom:24px;line-height:1.6;">
                                หากคุณไม่ได้ร้องขอการรีเซ็ตรหัสผ่าน กรุณาเพิกเฉยอีเมลนี้<br>
                                ห้ามแจ้งรหัสนี้ให้ผู้อื่นโดยเด็ดขาด
                            </div>
                            <div style="height:1px;background:rgba(0,212,255,0.1);margin-bottom:20px;"></div>
                            <div style="font-size:11px;color:#4a5568;text-align:center;">
                                &copy; ' . date('Y') . ' CSIMS — Crime Scene Investigation Management System
                            </div>
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>';
}
