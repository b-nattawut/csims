<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// รับค่า ID สำหรับลบข้อมูลรายการเดียว
$id = $_POST['id'] ?? null;
// หรือรับ annual_year และ group_code สำหรับลบทั้ง group (เพื่อ backward compatibility)
$annual_year = $_POST['annual_year'] ?? null;
$group_code = $_POST['group_code'] ?? null;
$dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');

try {
    // ถ้ามี id ให้ลบรายการเดียว
    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE master_instrument_calibration SET 
                statusDelete = 1,
                edit_by = ?,
                edit_date = ?
            WHERE id = ?
            AND (statusDelete = 0 OR statusDelete IS NULL)
        ");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $dateNow,
            $id
        ]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "status" => "success",
                "message" => "ลบข้อมูลสำเร็จ"
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "ไม่พบข้อมูลที่ต้องการลบ"
            ]);
        }
    }
    // ถ้ามี annual_year และ group_code ให้ลบทั้ง group
    else if ($annual_year && $group_code) {
        $stmt = $pdo->prepare("
            UPDATE master_instrument_calibration SET 
                statusDelete = 1,
                edit_by = ?,
                edit_date = ?
            WHERE annual_year = ? 
            AND group_code = ?
            AND (statusDelete = 0 OR statusDelete IS NULL)
        ");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $dateNow,
            $annual_year,
            $group_code
        ]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                "status" => "success",
                "message" => "ลบข้อมูลสำเร็จ (" . $stmt->rowCount() . " รายการ)"
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "ไม่พบข้อมูลที่ต้องการลบ"
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบข้อมูลที่ต้องการลบ (กรุณาระบุ ID หรือ ปี/กลุ่มงาน)'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
