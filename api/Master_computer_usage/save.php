<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');

// รับข้อมูลจาก POST
$identification_number = $_POST["identification_number"] ?? '';
$administrator = $_POST["administrator"] ?? null;
$date_use = $_POST["date_use"] ?? '';
$usage_remark = $_POST["usage_remark"] ?? '';
$status_condition = isset($_POST["status_condition"]) ? (int)$_POST["status_condition"] : 0;
$user_used = $_POST["user_used"] ?? null;
$remark = $_POST["remark"] ?? '';

try {
    if (!empty($_POST['id'])) {
        // Update
        $stmt = $pdo->prepare("
            UPDATE master_computer_using SET
                identification_number = ?,
                administrator = ?,
                date_use = ?,
                usage_remark = ?,
                status_condition = ?,
                user_used = ?,
                remark = ?,
                edit_by = ?,
                edit_date = ?
            WHERE id = ? AND statusDelete = 0
        ");

        $stmt->execute([
            $identification_number,
            $administrator,
            $date_use,
            $usage_remark,
            $status_condition,
            $user_used,
            $remark,
            $_SESSION['user_id'] ?? null,
            $dateNow,
            $_POST['id']
        ]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => true, "status" => "success", "message" => "แก้ไขข้อมูลสำเร็จ"]);
        } else {
            echo json_encode(["success" => false, "status" => "error", "message" => "ไม่พบข้อมูลที่ต้องการแก้ไข"]);
        }
    } else {
        // Insert
        $stmt = $pdo->prepare("
            INSERT INTO master_computer_using (
                identification_number,
                administrator,
                date_use,
                usage_remark,
                status_condition,
                user_used,
                remark,
                create_by,
                create_date,
                statusDelete
            ) VALUES (?,?,?,?,?,?,?,?,?,0)
        ");

        $stmt->execute([
            $identification_number,
            $administrator,
            $date_use,
            $usage_remark,
            $status_condition,
            $user_used,
            $remark,
            $_SESSION['user_id'] ?? null,
            $dateNow
        ]);

        $lastId = $pdo->lastInsertId();
        echo json_encode([
            "success" => true,
            "status" => "success", 
            "message" => "บันทึกข้อมูลสำเร็จ",
            "id" => $lastId
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
