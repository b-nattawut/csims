<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');

$tools_name = $_POST["tools_name"] ?? '';
$identification_number = $_POST["identification_number"] ?? '';
$administrator = $_POST["administrator"] ?? null;
$date_use = $_POST["date_use"] ?? null;
$usage_remark = $_POST["usage_remark"] ?? '';
$status_condition = $_POST["status_condition"] ?? 0; // 0 = ปกติ, 1 = ไม่ปกติ
$user_used = $_POST["user_used"] ?? null;
$remark = $_POST["remark"] ?? '';

try {
    if (!empty($_POST['id'])) {
        // Update
        $stmt = $pdo->prepare("
        UPDATE master_tools_using SET
            tools_name = ?,
            identification_number = ?,
            administrator = ?,
            date_use = ?,
            usage_remark = ?,
            status_condition = ?,
            user_used = ?,
            remark = ?,
            edit_by = ?,
            edit_date = ?
        WHERE id = ?
        ");

        $stmt->execute([
            $tools_name,
            $identification_number,
            $administrator,
            $date_use,
            $usage_remark,
            $status_condition,
            $user_used,
            $remark,
            $_SESSION['user_id'],
            $dateNow,
            $_POST['id']
        ]);

        echo json_encode(["status" => "success", "message" => "แก้ไขข้อมูลสำเร็จ"]);
    } else {
        // Insert
        $stmt = $pdo->prepare("
        INSERT INTO master_tools_using (
            tools_name,
            identification_number,
            administrator,
            date_use,
            usage_remark,
            status_condition,
            user_used,
            remark,
            create_by,
            create_date
        ) VALUES (?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->execute([
            $tools_name,
            $identification_number,
            $administrator,
            $date_use,
            $usage_remark,
            $status_condition,
            $user_used,
            $remark,
            $_SESSION['user_id'],
            $dateNow
        ]);

        echo json_encode(["status" => "success", "message" => "บันทึกข้อมูลสำเร็จ"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
