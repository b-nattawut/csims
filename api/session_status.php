<?php
/**
 * Session Status / Keep-Alive Endpoint
 * - action=check     : คืนเวลาที่เหลือก่อนหมดอายุ (ไม่ต่ออายุ session)
 * - action=extend    : ต่ออายุ session (รีเซ็ต last_activity)
 * - action=heartbeat : ต่ออายุเมื่อผู้ใช้มีการใช้งานจริงในหน้าเว็บ
 *
 * หมายเหตุ: ใช้ session_start() โดยตรง (ไม่ผ่าน session_config.php)
 * เพื่อให้การ "เช็ก" ไม่ไปรีเซ็ตเวลาเอง
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// ใช้ค่า timeout ชุดเดียวกับ includes/session_config.php
require_once __DIR__ . '/../includes/session_timeout.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$timeout = CSIMS_SESSION_TIMEOUT;

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['active' => false, 'remaining' => 0]);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'check';
$now    = time();
$last   = isset($_SESSION['last_activity']) ? (int)$_SESSION['last_activity'] : $now;

if ($action === 'extend' || $action === 'heartbeat') {
    // ต่ออายุ session — ใช้เมื่อผู้ใช้กด "อยู่ต่อ" หรือมีการใช้งานจริงในหน้าเว็บ
    // แต่ถ้าหมดอายุไปแล้ว ห้ามต่อ (ต้อง login ใหม่)
    if (($now - $last) > $timeout) {
        echo json_encode(['active' => false, 'remaining' => 0]);
        exit;
    }
    $_SESSION['last_activity'] = $now;
    echo json_encode(['active' => true, 'remaining' => $timeout, 'extended' => true]);
    exit;
}

// action=check : คำนวณเวลาที่เหลือโดยไม่ต่ออายุ
$inactive  = $now - $last;
$remaining = $timeout - $inactive;

if ($remaining <= 0) {
    echo json_encode(['active' => false, 'remaining' => 0]);
    exit;
}

echo json_encode(['active' => true, 'remaining' => $remaining]);
exit;
