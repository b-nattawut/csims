<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

/**
 * 1. ระบบรักษาความปลอดภัย (Security Check)
 * ตรวจสอบว่าผู้ใช้งานเข้าสู่ระบบอยู่หรือไม่
 */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน (Unauthorized)'
    ]);
    exit;
}

// 2. รับค่า ID ของรายการ Transaction ที่จะลบ
$usage_id = $_POST['id'] ?? '';
$user_id = $_SESSION['user_id']; // เก็บ ID คนที่สั่งลบไว้ใน update_by

if (empty($usage_id)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบรหัสรายการประวัติที่ต้องการลบ'
    ]);
    exit;
}

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Check)
    if ($user_role !== 'admin') {
        // เช็คว่ารายการประวัติการใช้งานที่กำลังจะลบ เป็นของเครื่องคอมพิวเตอร์ในหน่วยงานตัวเองหรือไม่
        $stmtOwner = $pdo->prepare("
            SELECT m.department_id 
            FROM trans_computer_usage t 
            JOIN master_computer_list m ON t.computer_id = m.id 
            WHERE t.id = ? AND t.status_delete = 0
        ");
        $stmtOwner->execute([$usage_id]);
        $owner_dept_id = $stmtOwner->fetchColumn();

        if ($owner_dept_id != $user_dept_id) {
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่มีสิทธิ์ลบข้อมูล: รายการใช้งานนี้เป็นของหน่วยงานอื่น'
            ]);
            exit;
        }
    }

    /**
     * 3. เตรียมคำสั่ง SQL (Soft Delete)
     * เราใช้แนวคิดเดียวกับตาราง Master คือเปลี่ยน status_delete จาก 0 เป็น id ของตัวเอง
     * เพื่อให้ข้อมูลยังคงอยู่ใน DB แต่ไม่ถูกดึงขึ้นมาแสดง (Audit Trail)
     */
    $sql = "UPDATE trans_computer_usage 
            SET status_delete = id, 
                update_by = :user_id, 
                update_date = NOW() 
            WHERE id = :id 
            AND status_delete = 0"; // ลบเฉพาะตัวที่ยัง Active อยู่เท่านั้น

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':user_id' => $user_id,
        ':id' => $usage_id
    ]);

    // ตรวจสอบว่ามีการอัปเดตแถวข้อมูลจริงหรือไม่
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'ลบประวัติการใช้งานเรียบร้อยแล้ว'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่สามารถลบรายการได้ หรือรายการนี้ถูกลบไปก่อนหน้าแล้ว'
        ]);
    }
} catch (PDOException $e) {
    // กรณีเกิดข้อผิดพลาดทาง Database
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดทางเทคนิค: ' . $e->getMessage()
    ]);
}
