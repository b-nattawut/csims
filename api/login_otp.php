<?php
/**
 * API: Login สำหรับ OTP flow (ใช้ทดสอบ)
 * POST: { "email": "...", "password": "..." }
 * 
 * ตรวจ email+password เหมือน login.php ปกติ
 * แต่ไม่ set session → คืน user_id + email ให้ frontend ไป verify OTP ต่อ
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;


function isDatabaseOffline(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SELECT @@global.read_only AS ro");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row['ro']);
    } catch (Throwable $e) {
        return false;
    }
}

function getClientIpAddress() {
    $candidates = [
        $_SERVER['HTTP_CLIENT_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null
    ];
    foreach ($candidates as $value) {
        if (!$value) continue;
        $ip = trim(explode(',', $value)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return null;
}

function getLocationFromIp($ip) {
    if (!$ip || in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
        return 'เครือข่ายภายใน (Local)';
    }
    if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $ip)) {
        return 'เครือข่ายภายใน (Private IP: ' . $ip . ')';
    }
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 1]]);
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
    } catch (Throwable $e) {}
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
    $stmt->bindValue(':ip_address', $ipAddress, $ipAddress !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':email_user', $emailUser, $emailUser !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':location_predict', $locationPredict, $locationPredict !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->execute();
}

function loadEnv() {
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
}

function checkFailedLoginsAndAlert($pdo, $email, $ipAddress, $locationPredict) {
	
	
    if (!$email) return;

    $sqlCount = "SELECT COUNT(*) FROM log_user_login 
                 WHERE email_user = :email AND type_login = 1 
                 AND date_login > COALESCE(
                     (SELECT MAX(date_login) FROM log_user_login WHERE email_user = :email2 AND type_login = 0),
                     '1970-01-01'
                 )";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute([':email' => $email, ':email2' => $email]);
    $failCount = (int)$stmtCount->fetchColumn();

    if ($failCount < 3 || $failCount % 3 !== 0) return;
	
	

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
        $fromName  = $_ENV['MAIL_FROM_NAME'] ?? 'CSIMS';
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
    } catch (Throwable $e) {
        error_log('[LOGIN ALERT ERROR] ' . $e->getMessage());
    }
}




// === Helper: ส่ง JSON response ให้ client ทันที แล้วทำงานต่อ background ===
function flushResponse($jsonData) {
    // ปลด session lock ก่อน เพื่อไม่ให้ block request อื่น
    session_write_close();

    $output = json_encode($jsonData);
    header('Content-Length: ' . strlen($output));
    echo $output;

    // ส่ง response ให้ client ทันที
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) ob_end_flush();
        flush();
    }
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $email    = isset($data['email']) ? trim($data['email']) : '';
    $password = $data['password'] ?? '';

    $ipAddress = getClientIpAddress();
	
	// ดักก่อน login
	if (isDatabaseOffline($pdo)) {
		http_response_code(503);
		echo json_encode([
			'success' => false,
			'maintenance' => true,
			'message' => 'ระบบอยู่ระหว่าง Maintenance / Restore กรุณาลองใหม่ภายหลัง'
		]);
		exit;
	}

    if (empty($email) || empty($password)) {
        flushResponse(['success' => false, 'message' => 'กรุณากรอกอีเมลและรหัสผ่าน']);
        // background: log
        try {
            $locationPredict = getLocationFromIp($ipAddress);
            writeLoginLog($pdo, null, 1, $ipAddress, $email, $locationPredict);
        } catch (Throwable $ignore) {}
        exit;
    }

    // ดึง user จาก DB
    $stmt = $pdo->prepare("
        SELECT user_id, email, password, is_active
        FROM users 
        WHERE email = ? 
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // บัญชีถูกปิด
    if ($user && (int)$user['is_active'] === 0) {
        flushResponse(['success' => false, 'message' => 'บัญชีของคุณถูกปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ']);
        // background: log + alert
        try {
            $locationPredict = getLocationFromIp($ipAddress);
            writeLoginLog($pdo, $user['user_id'], 1, $ipAddress, $email, $locationPredict);
            checkFailedLoginsAndAlert($pdo, $email, $ipAddress, $locationPredict);
        } catch (Throwable $ignore) {}
        exit;
    }

    // ตรวจรหัสผ่าน
    if ($user && password_verify($password, $user['password'])) {
        // ★ ส่ง response ให้ client ทันที ★
        flushResponse([
            'success' => true,
            'user_id' => (int)$user['user_id'],
            'email'   => $user['email']
        ]);
        // background: log (ไม่ต้องรอ location)
        try {
            $locationPredict = getLocationFromIp($ipAddress);
            writeLoginLog($pdo, $user['user_id'], 0, $ipAddress, $email, $locationPredict);
        } catch (Throwable $ignore) {}
    } else {
        $failedUserId = $user['user_id'] ?? null;
        flushResponse(['success' => false, 'message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง']);
        // background: log + alert
        try {
            $locationPredict = getLocationFromIp($ipAddress);
            writeLoginLog($pdo, $failedUserId, 1, $ipAddress, $email, $locationPredict);
            checkFailedLoginsAndAlert($pdo, $email, $ipAddress, $locationPredict);
        } catch (Throwable $ignore) {}
    }

} catch (Exception $e) {
    error_log('Login OTP Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการล็อกอิน']);
}
