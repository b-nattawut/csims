<?php
// logout.php
session_start();

// Record final activity before logout
if (isset($_SESSION['user_id'])) {
    require_once 'db_config.php';
    require_once 'includes/activity_logger.php';
    recordFinalActivity($pdo, $_SESSION['user_id']);
}

// ล้างเซสชันทั้งหมด
$_SESSION = [];
session_unset();
session_destroy();

// นำผู้ใช้กลับไปยังหน้า login
header('Location: login.html');
exit;