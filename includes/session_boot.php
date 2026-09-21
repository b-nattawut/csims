<?php
/**
 * เริ่ม PHP session พร้อม cookie อายุเท่า timeout ของแอป
 * ไม่เช็ก idle และไม่รีเซ็ต last_activity
 *
 * มี cookie สำรอง csims_keep เพราะบางเซิร์ฟเวอร์ยังออก PHPSESSID แบบ Session
 * (ปิดแท็บ/ปิดเบราว์เซอร์แล้วหาย) แม้ตั้ง cookie_lifetime แล้ว
 */
require_once __DIR__ . '/session_timeout.php';

if (!defined('CSIMS_KEEP_COOKIE')) {
    define('CSIMS_KEEP_COOKIE', 'csims_keep');
}

if (!function_exists('csims_session_cookie_secure')) {
    function csims_session_cookie_secure() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    }
}

if (!function_exists('csims_session_cookie_path')) {
    function csims_session_cookie_path() {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $apiPos = strpos($script, '/api/');
        if ($apiPos !== false) {
            $base = substr($script, 0, $apiPos);
            return $base === '' ? '/' : $base;
        }
        $dir = rtrim(dirname($script), '/');
        return ($dir === '' || $dir === '\\') ? '/' : $dir;
    }
}

if (!function_exists('csims_emit_cookie')) {
    function csims_emit_cookie($name, $value, $expiresAt) {
        if (headers_sent()) return;
        $path = csims_session_cookie_path();
        $secure = csims_session_cookie_secure();
        $maxAge = max(0, $expiresAt - time());
        $cookie = $name . '=' . $value
            . '; Max-Age=' . $maxAge
            . '; Expires=' . gmdate('D, d M Y H:i:s', $expiresAt) . ' GMT'
            . '; Path=' . $path
            . '; HttpOnly; SameSite=Lax';
        if ($secure) {
            $cookie .= '; Secure';
        }
        header('Set-Cookie: ' . $cookie, false);
    }
}

if (!function_exists('csims_persist_session_cookie')) {
    function csims_persist_session_cookie() {
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        if (headers_sent()) return;

        $id = session_id();
        if ($id === '') return;

        $expiresAt = time() + (int) CSIMS_SESSION_TIMEOUT;
        csims_emit_cookie(session_name(), $id, $expiresAt);
        csims_emit_cookie(CSIMS_KEEP_COOKIE, $id, $expiresAt);
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $cookiePath = csims_session_cookie_path();
    $secure = csims_session_cookie_secure();

    if (empty($_COOKIE[session_name()]) && !empty($_COOKIE[CSIMS_KEEP_COOKIE])) {
        session_id($_COOKIE[CSIMS_KEEP_COOKIE]);
        $strictMode = '0';
    } else {
        $strictMode = '1';
    }

    @ini_set('session.gc_maxlifetime', (string) (CSIMS_SESSION_TIMEOUT * 2));
    @ini_set('session.cookie_lifetime', (string) CSIMS_SESSION_TIMEOUT);
    @ini_set('session.cookie_path', $cookiePath);
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_strict_mode', $strictMode);
    @ini_set('session.use_only_cookies', '1');

    session_start([
        'cookie_lifetime' => CSIMS_SESSION_TIMEOUT,
        'cookie_path'     => $cookiePath,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => $secure,
        'gc_maxlifetime'  => CSIMS_SESSION_TIMEOUT * 2,
        'use_strict_mode' => $strictMode,
        'use_only_cookies'=> '1',
    ]);
}

csims_persist_session_cookie();
