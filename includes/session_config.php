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

require_once __DIR__ . '/session_boot.php';

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
