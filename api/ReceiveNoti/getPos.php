<?php

use Mpdf\Tag\P;

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
 
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");
// echo json_encode($_GET["idEmp"]); // แปลง array เป็น JSON

$stmt = $pdo->prepare("SELECT t2.position_name FROM user_profile t1 
LEFT JOIN user_position t2 ON t1.position_id = t2.position_id
WHERE t1.user_id = ?");

$stmt->execute([
    $_GET["idEmp"]
]);

$data = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    "message" => "success",
    "data" => $data
]);
// echo "test";
// die();
?>