<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// เช็คว่า Login หรือยัง? (Security Check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน (Unauthorized)'
    ]);
    exit;
}

$record_id = $_POST['id'] ?? '';
$user_id = $_SESSION['user_id'];

if (empty($record_id)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสรายการที่ต้องการลบ']);
    exit;
}

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role    = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Control)
    if ($user_role !== 'admin') {
        // เช็คว่ารายการบันทึกอุณหภูมินี้ ผูกอยู่กับเครื่องวัดในหน่วยงานของตนเองหรือไม่
        $stmtCheck = $pdo->prepare("
            SELECT d.department_id 
            FROM trans_temperature_record r
            JOIN master_temperature_device d ON r.device_id = d.id
            WHERE r.id = ? AND r.delete_token = 0
        ");
        $stmtCheck->execute([$record_id]);
        $rec_dept_id = $stmtCheck->fetchColumn();

        if ($rec_dept_id != $user_dept_id) {
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่มีสิทธิ์ลบข้อมูล: รายการบันทึกนี้เป็นของหน่วยงานอื่น'
            ]);
            exit;
        }
    }

    // ใช้ Soft Delete โดยการเปลี่ยน delete_token เป็นค่าของ id ตัวมันเอง (เพื่อป้องกันปัญหา Unique Key ซ้ำ)
    $sql = "UPDATE trans_temperature_record 
            SET delete_token = id, 
                update_by = ?, 
                update_date = NOW() 
            WHERE id = ?
            AND delete_token = 0";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $record_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'ลบรายการบันทึกอุณหภูมิสำเร็จ'
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'ไม่พบรายการนี้ หรือข้อมูลถูกลบไปแล้ว'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
