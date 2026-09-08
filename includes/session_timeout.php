<?php
/**
 * ค่า Session Timeout กลางของระบบ (วินาที)
 * แก้ที่ไฟล์นี้ที่เดียว แล้วมีผลทั้ง session_config.php และ api/session_status.php
 */
if (!defined('CSIMS_SESSION_TIMEOUT')) {
    define('CSIMS_SESSION_TIMEOUT', 28800); // 8 ชั่วโมง
}
