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
$brand = $_POST["brand"] ?? '';
$acid_base_test = intval($_POST["acid_base_test"]);
$blood_test = intval($_POST["blood_test"]);

if (!empty($_POST['id'])) {
    $stmt = $pdo->prepare("
    UPDATE master_chemical_validation SET
        date_testing = ?,
        chemical_name = ?,
        brand = ?,
        exp_date = ?,
        prep_date = ?,
        acid_base_test = ?,
        blood_test = ?,
        edit_by = ?,
        edit_date = ?
    WHERE id = ?
    ");

    $stmt->execute([
        $_POST['date_testing'],
        $chemical_name,
        $brand,
        $_POST['prep_date'],
        $_POST['exp_date'],
        $acid_base_test,
        $blood_test,
        $_SESSION['user_id'],
        $dateNow,
        $_POST['id']
    ]);

    echo json_encode([
        "status" => "success"
    ]);
} else {
    $stmt = $pdo->prepare("
    INSERT INTO master_chemical_validation (
        date_testing,
        chemical_name,
        brand,
        exp_date,
        prep_date,
        acid_base_test,
        blood_test,
        create_by,
        create_date
    ) VALUES (?,?,?,?,?,?,?,?,?)
    ");

    $stmt->execute([
        $_POST['date_testing'],
        $chemical_name,
        $brand,
        $_POST['prep_date'],
        $_POST['exp_date'],
        $acid_base_test,
        $blood_test,
        $_SESSION['user_id'],
        $dateNow
    ]);

    echo json_encode([
        "status" => "success"
    ]);
}




?>