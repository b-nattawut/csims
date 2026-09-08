<?php
// db_config.php
// ตั้งค่า Timezone ให้เป็นประเทศไทย (ICT UTC+7)
date_default_timezone_set('Asia/Bangkok');

try {
    $dsn = "mysql:host=localhost;dbname=csims;charset=utf8mb4";
    $username = "csims";
    $password = "csimsSQL2025@";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}