<?php

use Mpdf\Tag\P;

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$id = $_GET["id"];
$stmt = $pdo->prepare("SELECT id,chemical_name,acid_base_test,blood_test,date_testing,prep_date,exp_date,brand FROM master_chemical_validation WHERE statusDelete = 0 AND id = ? ");

$stmt->execute([
    $id
]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => 'success',
    'data' => $data
], JSON_UNESCAPED_UNICODE);

?>