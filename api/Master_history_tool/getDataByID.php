<?php

use Mpdf\Tag\P;

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$id = $_GET["id"];
$stmt = $pdo->prepare("SELECT id,tool_name,brand,model,serial_no,control_equipment_1,control_equipment_2,control_equipment_3,date_receive,date_use,maintenance,responsible_company,responsible_person,remark,date_transaction FROM master_history_tool WHERE statusDelete = 0 AND id = ? ");

$stmt->execute([
    $id
]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => 'success',
    'data' => $data
], JSON_UNESCAPED_UNICODE);

?>