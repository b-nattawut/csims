<?php
require_once __DIR__ . '/includes/session_config.php';
if(!empty($_SESSION["user_id"])) {
header("Location: dashboard.php");
} else {
header("Location: login.html");
}
?>
