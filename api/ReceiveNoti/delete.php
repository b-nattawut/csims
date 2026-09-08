<?php

use Mpdf\Tag\P;

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$ComplaintsID = $_POST['id'] ?? null;

if (!$ComplaintsID) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบ ID'
    ]);
    exit;
}

$stmt = $pdo->prepare("UPDATE rn_ReceiveNoti set statusDelete = 1 WHERE id = ?");
$stmt->execute([
    $ComplaintsID
]);

echo json_encode([
    "message" => "success",
]);

?>