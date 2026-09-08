<?php
/**
 * API: ส่ง OTP ไปยังอีเมลของ user
 * POST: { "user_id": 1, "email": "user@example.com" }
 * 
 * Flow:
 *   1. ตรวจสอบ input
 *   2. สร้างรหัส OTP 6 หลัก
 *   3. ลบ OTP เก่าของ user ออก
 *   4. บันทึก OTP ใหม่ลง user_otp (หมดอายุ 5 นาที)
 *   5. ส่ง OTP ไปทาง email ด้วย PHPMailer
 *   6. ส่ง masked email กลับไปแสดงที่ frontend
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = isset($data['user_id']) ? intval($data['user_id']) : 0;
    $email  = isset($data['email']) ? trim($data['email']) : '';

    // === Validate input ===
    if ($userId <= 0 || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง']);
        exit;
    }

    // === Rate Limit: ไม่ให้ส่งซ้ำภายใน 60 วินาที ===
    // ใช้ expire_at คำนวณย้อนกลับ (expire_at - 5 นาที = เวลาที่สร้าง)
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

    // === ลบ OTP เก่าของ user คนนี้ ===
    $stmtDel = $pdo->prepare("DELETE FROM user_otp WHERE user_id = ?");
    $stmtDel->execute([$userId]);

    // === บันทึก OTP ใหม่ (หมดอายุ 5 นาที) ===
    $expireMinutes = 5;
    $stmtIns = $pdo->prepare("
        INSERT INTO user_otp (user_id, email, otp_code, expire_at) 
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
    ");
    $stmtIns->execute([$userId, $email, $otpCode, $expireMinutes]);

    // ★★★ TEST MODE: true = ไม่ส่งอีเมล แสดง OTP บนจอ / false = ส่งอีเมลจริง ★★★
    define('TEST_MODE', false);

    // === Mask email สำหรับแสดงใน frontend ===
    $maskedEmail = maskEmail($email);

    $response = [
        'success' => true,
        'message' => TEST_MODE 
            ? 'TEST MODE: OTP ไม่ได้ส่งอีเมลจริง ดูรหัสด้านล่าง' 
            : 'ส่งรหัส OTP ไปยังอีเมลของคุณแล้ว',
        'masked_email' => $maskedEmail,
        'expire_minutes' => $expireMinutes
    ];

    // ★ Test Mode: แนบ OTP ใน response เพื่อทดสอบ (production ต้องลบ!)
    if (TEST_MODE) {
        $response['test_otp'] = $otpCode;
    }

    // ★ ปลด session lock ก่อน เพื่อไม่ให้ block request อื่น (เช่น verify_otp.php) ★
    session_write_close();

    // ★ ส่ง response ให้ client ทันที ก่อนส่ง SMTP ★
    $output = json_encode($response);
    header('Content-Length: ' . strlen($output));
    echo $output;
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) ob_end_flush();
        flush();
    }

    // === ส่งอีเมลใน background (client ไม่ต้องรอ) ===
    if (!TEST_MODE) {
        try {
            // === โหลด .env config ===
            $envFile = __DIR__ . '/../.env';
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

            // Headers ช่วยลดโอกาสเข้า Spam
            $mail->XMailer   = 'CSIMS Mailer';
            $mail->Priority  = 1;
            $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
            $mail->addCustomHeader('Precedence', 'bulk');

            $mail->isHTML(true);
            $mail->Subject = 'CSIMS - รหัสยืนยันตัวตน OTP';
            $mail->Body = getOtpEmailTemplate($otpCode, $expireMinutes);
            $mail->AltBody = "รหัสยืนยันตัวตน OTP ของคุณคือ: {$otpCode} (หมดอายุใน {$expireMinutes} นาที) - ห้ามแจ้งรหัสนี้ให้ผู้อื่น";

            $mail->send();
            error_log('[MAILJET] ส่ง OTP สำเร็จ → ' . $email);
        } catch (\Throwable $mailErr) {
            error_log('[MAILJET ERROR] ' . $mailErr->getMessage());
        }
    }

} catch (\Throwable $e) {
    error_log('OTP Send Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}

// === Helper Functions ===

/**
 * Mask email เช่น poomarin.1567@gmail.com → p****n@gmail.com
 */
function maskEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;

    $local = $parts[0];
    $domain = $parts[1];

    $len = mb_strlen($local);
    if ($len <= 2) {
        $masked = $local[0] . str_repeat('*', max($len - 1, 1));
    } else {
        $masked = $local[0] . str_repeat('*', $len - 2) . $local[$len - 1];
    }

    return $masked . '@' . $domain;
}

/**
 * สร้าง HTML Template สำหรับอีเมล OTP
 */
function getOtpEmailTemplate($otpCode, $expireMinutes) {
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
                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#0057ff,#00d4ff);padding:30px 40px;text-align:center;">
                            <div style="font-size:22px;font-weight:700;color:#fff;margin-bottom:4px;">CSIMS</div>
                            <div style="font-size:13px;color:rgba(255,255,255,0.8);">Crime Scene Investigation Management System</div>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding:36px 40px;">
                            <div style="font-size:18px;font-weight:700;color:#e8edf5;margin-bottom:12px;">ยืนยันตัวตนด้วยรหัส OTP</div>
                            <div style="font-size:14px;color:#6b7a99;line-height:1.7;margin-bottom:28px;">
                                กรุณานำรหัส OTP ด้านล่างไปกรอกในหน้ายืนยันตัวตน<br>
                                รหัสนี้จะหมดอายุใน <strong style="color:#00d4ff;">' . $expireMinutes . ' นาที</strong>
                            </div>
                            <!-- OTP Digits -->
                            <table cellpadding="0" cellspacing="6" style="margin:0 auto 28px auto;">
                                <tr>' . $digitBoxes . '</tr>
                            </table>
                            <div style="font-size:12px;color:#6b7a99;text-align:center;margin-bottom:24px;line-height:1.6;">
                                หากคุณไม่ได้เป็นผู้ร้องขอ กรุณาเพิกเฉยอีเมลนี้<br>
                                ห้ามแจ้งรหัสนี้ให้ผู้อื่นโดยเด็ดขาด
                            </div>
                            <div style="height:1px;background:rgba(0,212,255,0.1);margin-bottom:20px;"></div>
                            <div style="font-size:11px;color:#4a5568;text-align:center;">
                                © ' . date('Y') . ' CSIMS — Crime Scene Investigation Management System
                            </div>
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>';
}
