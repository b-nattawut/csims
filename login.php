<?php

// ตั้งค่า Session Timeout (แก้ค่าที่ includes/session_timeout.php)
require_once __DIR__ . '/includes/session_timeout.php';
@ini_set('session.gc_maxlifetime', CSIMS_SESSION_TIMEOUT * 2);
session_set_cookie_params([
    'lifetime' => CSIMS_SESSION_TIMEOUT,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
header('Content-Type: application/json');
require_once 'db_config.php'; // ตรวจสอบว่าไฟล์นี้มีการเชื่อมต่อ PDO $pdo
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

function getClientIpAddress() {
    $candidates = [
        $_SERVER['HTTP_CLIENT_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null
    ];

    foreach ($candidates as $value) {
        if (!$value) {
            continue;
        }
        $ip = trim(explode(',', $value)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return null;
}

/**
 * ใช้ IP address ไปหาพื้นที่โดยประมาณผ่าน ip-api.com (ฟรี ไม่ต้อง API key)
 */
function getLocationFromIp($ip) {
    if (!$ip || in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
        return 'เครือข่ายภายใน (Local)';
    }
    // Private IP → ไม่สามารถหาพื้นที่จาก internet ได้
    if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $ip)) {
        return 'เครือข่ายภายใน (Private IP: ' . $ip . ')';
    }
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $response = @file_get_contents("http://ip-api.com/json/{$ip}?lang=th&fields=status,country,regionName,city,isp", false, $ctx);
        if ($response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                $parts = array_filter([$data['city'] ?? '', $data['regionName'] ?? '', $data['country'] ?? '']);
                $location = implode(', ', $parts);
                if (!empty($data['isp'])) {
                    $location .= ' (' . $data['isp'] . ')';
                }
                return $location ?: 'Unknown';
            }
        }
    } catch (Throwable $e) {
        // ไม่ให้กระทบ flow login
    }
    return 'Unknown';
}

function writeLoginLog($pdo, $userId, $typeLogin, $ipAddress, $emailUser, $locationPredict = null) {
    $sql = "INSERT INTO log_user_login (user_id, date_login, type_login, Ip_Address, email_user, location_predict)
            VALUES (:user_id, NOW(), :type_login, :ip_address, :email_user, :location_predict)";

    $stmt = $pdo->prepare($sql);
    if ($userId !== null && $userId !== '') {
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':type_login', (int)$typeLogin, PDO::PARAM_INT);
    $stmt->bindValue(':ip_address', $ipAddress !== null ? $ipAddress : null, $ipAddress !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':email_user', $emailUser !== null ? $emailUser : null, $emailUser !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':location_predict', $locationPredict !== null ? $locationPredict : null, $locationPredict !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->execute();
}

/**
 * โหลด .env config
 */
function loadEnv() {
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            list($key, $val) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val);
        }
    }
}

/**
 * เช็ค login ไม่ผ่านติดกัน 3 ครั้ง → ส่ง email แจ้งเตือน
 */
function checkFailedLoginsAndAlert($pdo, $email, $ipAddress, $locationPredict) {
    if (!$email) return;

    // ดึง 3 records ล่าสุดของ email นี้
    $sql = "SELECT type_login FROM log_user_login 
            WHERE email_user = :email 
            ORDER BY date_login DESC LIMIT 3";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // ต้องมี 3 records และทั้งหมดเป็น type_login = 1 (ไม่ผ่าน)
    if (count($rows) < 3) return;
    foreach ($rows as $type) {
        if ((int)$type !== 1) return;
    }

    // เช็คว่าส่ง email แจ้งเตือนไปแล้วหรือยัง (ไม่ส่งซ้ำถ้ายังไม่มี login สำเร็จคั่น)
    $sqlCheck = "SELECT COUNT(*) FROM log_user_login 
                 WHERE email_user = :email AND type_login = 0 
                 ORDER BY date_login DESC LIMIT 1";
    // ใช้วิธีเช็คว่าครั้งที่ 3 พอดี (ไม่ใช่ครั้งที่ 4, 5, ...)
    $sqlCount = "SELECT COUNT(*) FROM log_user_login 
                 WHERE email_user = :email AND type_login = 1 
                 AND date_login > COALESCE(
                     (SELECT MAX(date_login) FROM log_user_login WHERE email_user = :email2 AND type_login = 0),
                     '1970-01-01'
                 )";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute([':email' => $email, ':email2' => $email]);
    $failCount = (int)$stmtCount->fetchColumn();

    // ส่งแจ้งเตือนเฉพาะครั้งที่ 3 พอดี (ทุกๆ 3 ครั้งก็ได้)
    if ($failCount % 3 !== 0) return;

    // === ส่ง email แจ้งเตือน ===
    try {
        loadEnv();
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'in-v3.mailjet.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAILJET_API_KEY'] ?? '';
        $mail->Password   = $_ENV['MAILJET_SECRET_KEY'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $fromEmail = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@ppunix.org';
        $fromName  = $_ENV['MAIL_FROM_NAME'] ?? 'CSIMS - ระบบจัดการข้อมูลสืบสวน';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addReplyTo($fromEmail, $fromName);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'แจ้งเตือน: มีการพยายามเข้าสู่ระบบ CSIMS ล้มเหลวหลายครั้ง';

        $dateNow = date('d/m/Y H:i:s');
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                    <h2 style='margin:0;'>⚠️ แจ้งเตือนความปลอดภัย</h2>
                    <p style='margin:5px 0 0;'>CSIMS - Crime Scene Investigation Management System</p>
                </div>
                <div style='background: #fff; border: 1px solid #ddd; padding: 25px; border-radius: 0 0 10px 10px;'>
                    <p>เรียนผู้ใช้งาน,</p>
                    <p>ระบบตรวจพบ <strong>การพยายามเข้าสู่ระบบล้มเหลว {$failCount} ครั้งติดต่อกัน</strong> สำหรับบัญชีของคุณ</p>
                    <table style='width:100%; border-collapse:collapse; margin: 15px 0;'>
                        <tr style='background:#f8f9fa;'><td style='padding:10px; border:1px solid #ddd;'><strong>วันที่/เวลา</strong></td><td style='padding:10px; border:1px solid #ddd;'>{$dateNow}</td></tr>
                        <tr><td style='padding:10px; border:1px solid #ddd;'><strong>IP Address</strong></td><td style='padding:10px; border:1px solid #ddd;'>{$ipAddress}</td></tr>
                        <tr style='background:#f8f9fa;'><td style='padding:10px; border:1px solid #ddd;'><strong>พื้นที่โดยประมาณ</strong></td><td style='padding:10px; border:1px solid #ddd;'>{$locationPredict}</td></tr>
                    </table>
                    <p style='color:#dc3545;'><strong>หากไม่ใช่คุณ</strong> กรุณาเปลี่ยนรหัสผ่านทันที หรือติดต่อผู้ดูแลระบบ</p>
                    <p style='color:#666; font-size:12px; margin-top:20px;'>อีเมลนี้ส่งโดยอัตโนมัติจากระบบ CSIMS กรุณาอย่าตอบกลับ</p>
                </div>
            </div>";
        $mail->AltBody = "แจ้งเตือน: มีการพยายามเข้าสู่ระบบล้มเหลว {$failCount} ครั้งติดต่อกัน จาก IP: {$ipAddress} พื้นที่: {$locationPredict} เวลา: {$dateNow}";

        $mail->send();
        error_log("[LOGIN ALERT] ส่ง email แจ้งเตือน failed login → {$email} (IP: {$ipAddress}, Location: {$locationPredict})");
    } catch (Throwable $e) {
        error_log('[LOGIN ALERT ERROR] ' . $e->getMessage());
    }
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = isset($data['email']) ? trim($data['email']) : null;
    $password = $data['password'] ?? null;
    $ipAddress = getClientIpAddress();
    $locationPredict = getLocationFromIp($ipAddress);

    if (!$email || !$password) {
        try {
            writeLoginLog($pdo, null, 1, $ipAddress, $email, $locationPredict);
        } catch (Throwable $ignore) {
            // ไม่ให้กระทบ flow login
        }
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกอีเมลและรหัสผ่าน']);
        exit;
    }

    // ******************************************************************************
    // ** คำสั่ง SQL ที่ปรับปรุง: ใช้ JOIN เพื่อดึงข้อมูลโปรไฟล์, ยศ, และตำแหน่ง **
    // ******************************************************************************
    $sql = "
        SELECT 
            u.user_id, 
            u.email, 
            u.password, 
            u.role, 
            up.first_name, 
            up.last_name,
            ur.rank_name,
            upos.position_name
        FROM 
            users u
        LEFT JOIN 
            user_profile up ON u.user_id = up.user_id
        LEFT JOIN 
            user_rank ur ON up.rank_id = ur.rank_id
        LEFT JOIN 
            user_position upos ON up.position_id = upos.position_id
        WHERE 
            u.email = ? AND u.is_active = 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // ตรวจสอบว่าบัญชีถูกปิดใช้งานหรือไม่
    if (!$user) {
        $checkSql = "SELECT user_id, is_active FROM users WHERE email = ? LIMIT 1";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$email]);
        $checkUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($checkUser && (int)$checkUser['is_active'] === 0) {
            try {
                writeLoginLog($pdo, $checkUser['user_id'], 1, $ipAddress, $email, $locationPredict);
                checkFailedLoginsAndAlert($pdo, $email, $ipAddress, $locationPredict);
            } catch (Throwable $ignore) {}
            echo json_encode(['success' => false, 'message' => 'บัญชีของคุณถูกปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ']);
            exit;
        }
    }

    if ($user && password_verify($password, $user['password'])) {
        // **********************************************************************
        // ** เก็บข้อมูลผู้ใช้เพิ่มเติมลงใน Session **
        // **********************************************************************
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        // ข้อมูลจาก user_profile, user_rank, user_position
        $_SESSION['first_name'] = $user['first_name'] ?? '';
        $_SESSION['last_name'] = $user['last_name'] ?? '';
        $_SESSION['rank_name'] = $user['rank_name'] ?? '';      // ยศ
        $_SESSION['position_name'] = $user['position_name'] ?? ''; // ตำแหน่ง

        try {
            writeLoginLog($pdo, $user['user_id'], 0, $ipAddress, $email, $locationPredict);
        } catch (Throwable $ignore) {
            // ไม่ให้กระทบ flow login
        }
		
        // TODO: Implement MFA/2FA logic
        echo json_encode(['success' => true]);
    } else {
        $failedUserId = $user['user_id'] ?? null;
        try {
            writeLoginLog($pdo, $failedUserId, 1, $ipAddress, $email, $locationPredict);
            checkFailedLoginsAndAlert($pdo, $email, $ipAddress, $locationPredict);
        } catch (Throwable $ignore) {
            // ไม่ให้กระทบ flow login
        }
        echo json_encode(['success' => false, 'message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง หรือบัญชีถูกระงับ']);
    }
} catch (Exception $e) {
    // ควร log $e->getMessage() สำหรับ Debug
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการล็อกอิน']);
}

?>