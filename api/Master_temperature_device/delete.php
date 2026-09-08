<?php
session_start();
// เปิด Error Reporting ช่วง Dev
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php'; 
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบ Session ผู้ใช้งาน
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. ตรวจสอบว่ามีการส่ง ID มาหรือไม่ 
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ที่ต้องการลบ']);
    exit;
}

$id = $_POST['id'];

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Check)
    if ($user_role !== 'admin') {
        $stmtOwner = $pdo->prepare("SELECT department_id FROM master_temperature_device WHERE id = ?");
        $stmtOwner->execute([$id]);
        $owner_dept_id = $stmtOwner->fetchColumn();

        if ($owner_dept_id != $user_dept_id) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'ไม่มีสิทธิ์ลบข้อมูล: เครื่องวัดอุณหภูมินี้เป็นของหน่วยงานอื่น'
            ]);
            exit;
        }
    }

    // 3. เตรียมคำสั่ง SQL (Soft Delete) 
    // อัปเดตตาราง master_temperature_device
    $sql = "UPDATE master_temperature_device 
            SET status_delete = id, 
                update_by = :update_by, 
                update_date = NOW() 
            WHERE id = :id AND status_delete = 0";

    $stmt = $pdo->prepare($sql);
    
    // 4. ผูกตัวแปรและรันคำสั่ง
    $result = $stmt->execute([
        ':update_by' => $user_id,
        ':id' => $id
    ]);

    // 5. ตรวจสอบว่ามีการแก้ไขแถวใน DB จริงหรือไม่
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'ลบรายการเครื่องวัดอุณหภูมิเรียบร้อยแล้ว'
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'ไม่พบข้อมูลที่ต้องการลบ หรือข้อมูลถูกลบไปแล้ว'
        ]);
    }

} catch (PDOException $e) {
    // กรณีเกิด Error ที่ระดับ Database
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
?>