<?php
use Mpdf\Tag\P;
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");
$dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');

$tool_name = $_POST["equipment_name"] ?? '';
$brand = $_POST["equipment_brand"] ?? '';
$model = $_POST["equipment_model"] ?? '';
$serial_no = $_POST["equipment_serial"] ?? '';
$control_equipment_1 = $_POST["accessories_1"] ?? '';
$control_equipment_2 = $_POST["accessories_2"] ?? '';
$control_equipment_3 = $_POST["accessories_3"] ?? '';
$maintenance = $_POST["log_action"] ?? '';
$responsible_company = trim($_POST["responsible_company"] ?? '');
$remark = $_POST["log_note"] ?? '';
// $responsible_person = intval($_POST["log_officer"]);
$responsible_person = $_POST["log_officer"] ?? '';
$date_receive = $_POST["install_date"] ?? null;
$date_use = $_POST["start_use_date"] ?? null;
$date_transaction = $_POST["log_date"] ?? null;
if (!empty($_POST['id'])) {
    $stmt = $pdo->prepare("
    UPDATE master_history_tool SET
        tool_name = ?,
        brand = ?,
        model = ?,
        serial_no = ?,
        control_equipment_1 = ?,
        control_equipment_2 = ?,
        control_equipment_3 = ?,
        maintenance = ?,
        responsible_company = ?,
        remark = ?,
        responsible_person = ?,
        date_receive = ?,  
        date_use = ?,
        edit_by = ?,
        edit_date = ?
        date_transaction = ?
    WHERE id = ?
    ");

    $stmt->execute([
        $tool_name,
        $brand,
        $model,
        $serial_no,
        $control_equipment_1,
        $control_equipment_2,
        $control_equipment_3,
        $maintenance,
        $responsible_company,
        $remark,
        $responsible_person,
        $date_receive,
        $date_use,
        $_SESSION['user_id'],
        $dateNow,
        $date_transaction,
        $_POST['id']
    ]);

    echo json_encode([
        "status" => "success"
    ]);
} else {
    $stmt = $pdo->prepare("
    INSERT INTO master_history_tool (
        tool_name,
        brand,
        model,
        serial_no,
        control_equipment_1,
        control_equipment_2,
        control_equipment_3,
        maintenance,
        responsible_company,
        remark,
        responsible_person,
        date_receive,
        date_use,
        create_by,
        create_date,
        date_transaction
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->execute([
        $tool_name,
        $brand,
        $model,
        $serial_no,
        $control_equipment_1,
        $control_equipment_2,
        $control_equipment_3,
        $maintenance,
        $responsible_company,
        $remark,
        $responsible_person,
        $date_receive,
        $date_use,
        $_SESSION['user_id'],
        $dateNow,
        $date_transaction
    ]);

    echo json_encode([
        "status" => "success"
    ]);
}




?>