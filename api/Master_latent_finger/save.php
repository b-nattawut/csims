<?php
use Mpdf\Tag\P;
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");
$dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');

$chemical_name = $_POST["chemical_name"] ?? '';
$description = $_POST["description"] ?? '';
$remark = $_POST["chemical_name"] ?? '';
$ready_to_use = intval($_POST["ready_to_use"]);

if (!empty($_POST['id'])) {
    $stmt = $pdo->prepare("
    UPDATE master_latent_fingerprint_chemical SET
        chemical_name = ?,
        description = ?,
        remark = ?,
        prep_date = ?,
        exp_date = ?,
        ready_for_use = ?,
        edit_by = ?,
        edit_date = ?
    WHERE id = ?
    ");

    $stmt->execute([
        $chemical_name,
        $description,
        $remark,
        $_POST['prep_date'],
        $_POST['exp_date'],
        $ready_to_use,
        $_SESSION['user_id'],
        $dateNow,
        $_POST['id']
    ]);

    echo json_encode([
        "status" => "success"
    ]);
} else {
    $stmt = $pdo->prepare("
    INSERT INTO master_latent_fingerprint_chemical (
        chemical_name,
        description,
        remark,
        prep_date,
        exp_date,
        ready_for_use,
        create_by,
        create_date
    ) VALUES (?,?,?,?,?,?,?,?)
    ");

    $stmt->execute([
        $chemical_name,
        $description,
        $remark,
        $_POST['prep_date'],
        $_POST['exp_date'],
        $ready_to_use,
        $_SESSION['user_id'],
        $dateNow
    ]);

    echo json_encode([
        "status" => "success"
    ]);
}




?>