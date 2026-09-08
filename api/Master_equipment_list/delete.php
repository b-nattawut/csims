<?php
session_start();
// เปิด Error Reporting ช่วง Dev (ปิดเมื่อขึ้น Production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบ Session ผู้ใช้งาน (เพื่อบันทึกว่าใครเป็นคนลบ)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

// 2. ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ที่ต้องการลบ']);
    exit;
}

$id = $_POST['id'];
$user_id = $_SESSION['user_id'];

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Check)
    if ($user_role !== 'admin') {
        // เช็คว่ารายการที่พยายามจะลบนั้น เป็นของหน่วยงานตัวเองหรือไม่
        $stmtOwner = $pdo->prepare("SELECT department_id FROM master_equipment_list WHERE id = ?");
        $stmtOwner->execute([$id]);
        $owner_dept_id = $stmtOwner->fetchColumn();

        if ($owner_dept_id != $user_dept_id) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'ไม่มีสิทธิ์ลบข้อมูล: เครื่องมือนี้เป็นของหน่วยงานอื่น'
            ]);
            exit;
        }
    }

    // 3. เตรียมคำสั่ง SQL (Soft Delete)
    // เปลี่ยน status_delete = id ให้ลบได้หลายครั้ง เผื่อสร้าง asset_no เดิมหลายครั้ง ลบหลายครั้ง และบันทึกคนแก้ไขล่าสุด
    $sql = "UPDATE master_equipment_list 
            SET status_delete = id, 
                update_by = :update_by, 
                update_date = NOW() 
            WHERE id = :id AND status_delete = 0"; // ป้องกันการกดลบซ้ำ

    $stmt = $pdo->prepare($sql);

    // 4. ผูกตัวแปรและรันคำสั่ง
    $result = $stmt->execute([
        ':update_by' => $user_id,
        ':id' => $id
    ]);

    if ($result) {
        echo json_encode([
            'status' => 'success',
            'message' => 'ลบรายการเรียบร้อยแล้ว'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่สามารถลบรายการได้ หรือรายการนี้อาจถูกลบไปแล้ว'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
