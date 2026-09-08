<?php
session_start();
// เปิด Error Reporting สำหรับช่วงพัฒนา (ปิดเมื่อขึ้น Production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

/**
 * 1. ตรวจสอบสิทธิ์การเข้าถึง
 * ตรวจสอบ Session เพื่อยืนยันตัวตนและบันทึกว่าใครเป็นผู้ทำรายการลบ
 */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'เซสชันหมดอายุหรือยังไม่ได้เข้าสู่ระบบ กรุณาล็อกอินใหม่อีกครั้ง'
    ]);
    exit;
}

/**
 * 2. ตรวจสอบข้อมูลที่ส่งมา
 */
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบ ID รายการที่ต้องการลบ'
    ]);
    exit;
}

$id = $_POST['id'];
$user_id = $_SESSION['user_id']; // ID ผู้ใช้งานปัจจุบัน

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
        $stmtOwner = $pdo->prepare("SELECT department_id FROM master_computer_list WHERE id = ?");
        $stmtOwner->execute([$id]);
        $owner_dept_id = $stmtOwner->fetchColumn();

        if ($owner_dept_id != $user_dept_id) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'ไม่มีสิทธิ์ลบข้อมูล: เครื่องคอมพิวเตอร์นี้เป็นของหน่วยงานอื่น'
            ]);
            exit;
        }
    }

    /**
     * 3. เตรียมคำสั่ง SQL (Soft Delete Logic)
     * เราจะเซ็ต status_delete = id เพื่อปลดล็อก Unique Key ของ asset_no
     * และบันทึกว่าใครเป็นคนลบผ่าน update_by
     */
    $sql = "UPDATE master_computer_list 
            SET status_delete = id, 
                update_by = :update_by, 
                update_date = NOW() 
            WHERE id = :id 
            AND status_delete = 0"; // ป้องกันการลบซ้ำรายการที่ลบไปแล้ว

    $stmt = $pdo->prepare($sql);

    // 4. ผูกตัวแปรและรันคำสั่ง
    $result = $stmt->execute([
        ':update_by' => $user_id,
        ':id' => $id
    ]);

    // ตรวจสอบว่ามีแถวที่ถูก Update หรือไม่ (row count)
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'ลบข้อมูลเครื่องคอมพิวเตอร์เรียบร้อยแล้ว'
        ]);
    } else {
        // กรณี rowCount เป็น 0 อาจเพราะ ID ไม่ถูกต้อง หรือรายการถูกลบไปก่อนหน้าแล้ว
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่สามารถลบรายการได้ หรือรายการนี้อาจถูกลบไปแล้ว'
        ]);
    }
} catch (PDOException $e) {
    // กรณีเกิดข้อผิดพลาดที่ Database
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดทางเทคนิค: ' . $e->getMessage()
    ]);
}
