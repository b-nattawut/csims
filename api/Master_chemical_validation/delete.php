<?php

use Mpdf\Tag\P;

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$ID = $_POST['id'] ?? null;

if (!$ID) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบ ID'
    ]);
    exit;
}

$stmt = $pdo->prepare("UPDATE master_chemical_validation set statusDelete = 1 WHERE id = ?");
$stmt->execute([
    $ID
]);

echo json_encode([
    "message" => "success",
]);

?>