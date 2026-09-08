<?php
/**
 * Session Configuration
 * ตั้งค่า Session Timeout และเริ่ม Session
 * ให้ทุกไฟล์ include ไฟล์นี้แทน session_start() โดยตรง
 *
 * ★ สำคัญ: ต้อง include ไฟล์นี้ "ก่อน" เรียก session_start() ที่อื่น
 *   ไม่งั้น ini_set/session_set_cookie_params จะไม่มีผล และ PHP จะใช้ค่า
 *   default ของเซิร์ฟเวอร์ (บางเครื่องสั้นมาก ทำให้หลุดออกจากระบบเร็ว)
 */

// ระยะเวลาที่ยอมให้ "ไม่มีการใช้งานจริง" ก่อน logout (แก้ค่าที่ session_timeout.php)
require_once __DIR__ . '/session_timeout.php';

if (session_status() === PHP_SESSION_NONE) {
    // อายุไฟล์ session ฝั่งเซิร์ฟเวอร์ (เผื่อไว้ยาวกว่า timeout ของแอป)
    @ini_set('session.gc_maxlifetime', CSIMS_SESSION_TIMEOUT * 2);
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_strict_mode', '1');

    // cookie อยู่ยาวเท่า timeout ของแอป (0 = ปิดเบราว์เซอร์แล้วหาย)
    session_set_cookie_params([
        'lifetime' => CSIMS_SESSION_TIMEOUT,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// ตรวจสอบ Session Timeout (Activity-based)
if (isset($_SESSION['last_activity'])) {
    $inactive = time() - $_SESSION['last_activity'];
    if ($inactive > CSIMS_SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header("Location: /csims/login.html?timeout=1");
        exit;
    }
}
$_SESSION['last_activity'] = time();
