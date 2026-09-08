<?php
session_start();
// เปิด Error Reporting ช่วง Dev (ปิดเมื่อขึ้น Production)
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

// 2. ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_POST['id']) || empty($_POST['id'])) {
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
        $stmtOwner = $pdo->prepare("SELECT department_id FROM master_chemical_list WHERE id = ?");
        $stmtOwner->execute([$id]);
        $owner_dept_id = $stmtOwner->fetchColumn();

        if ($owner_dept_id != $user_dept_id) {
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่มีสิทธิ์ลบข้อมูล: สารเคมีนี้เป็นของหน่วยงานอื่น'
            ]);
            exit;
        }
    }

    // --- 1. ตรวจสอบการใช้งาน (Safety Check) ---
    // เช็คว่าสารเคมีตัวนี้มี Lot ใน Inventory ที่ยังไม่ได้ลบหรือไม่
    $sqlCheck = "SELECT id FROM chemical_inventory 
                 WHERE chemical_id = ? AND status_delete = 0 LIMIT 1";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$id]);

    if ($stmtCheck->rowCount() > 0) {
        // ถ้ายังมีของอยู่ในคลัง ห้ามลบ Master นี้
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่สามารถลบรายการนี้ได้ เนื่องจากยังมีข้อมูลล็อตสารเคมีค้างอยู่ในคลัง กรุณาลบข้อมูลในคลังสารเคมีให้หมดก่อน'
        ]);
        exit;
    }

    // 3. เตรียมคำสั่ง SQL (Soft Delete)
    // ใช้ status_delete = id เพื่อปลดล็อค Unique Key (Name + Brand + status_delete) 
    // ทำให้สามารถเพิ่มสารเคมีชื่อเดิม/ยี่ห้อเดิมเข้าไปใหม่ได้หลังจากการลบ
    $sql = "UPDATE master_chemical_list 
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

    if ($result) {
        echo json_encode([
            'status' => 'success',
            'message' => 'ลบรายการสารเคมีเรียบร้อยแล้ว'
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
