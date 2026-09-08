<?php
session_start();
ini_set('display_errors', 0);
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
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ของล็อตสารเคมีที่ต้องการลบ']);
    exit;
}

$id = $_POST['id'];
$user_id = $_SESSION['user_id'];

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role    = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Control)
    if ($user_role !== 'admin') {
        // เช็คว่าล็อตสารเคมีนี้ ผูกอยู่กับสารเคมีในหน่วยงานของตนเองหรือไม่
        $stmtCheck = $pdo->prepare("
            SELECT m.department_id 
            FROM chemical_inventory i
            JOIN master_chemical_list m ON i.chemical_id = m.id
            WHERE i.id = ? AND i.status_delete = 0
        ");
        $stmtCheck->execute([$id]);
        $lot_dept_id = $stmtCheck->fetchColumn();

        if (!$lot_dept_id || $lot_dept_id != $user_dept_id) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่มีสิทธิ์ลบข้อมูล: ล็อตสารเคมีนี้เป็นของหน่วยงานอื่น'
            ]);
            exit;
        }
    }

    $pdo->beginTransaction();

    // 3. เตรียมคำสั่ง SQL (Soft Delete)
    // การใช้ status_delete = id จะทำให้ Unique Key (chemical_id + lot_number + status_delete) 
    // ของรายการที่ถูกลบไปแล้วไม่มาชนกับรายการใหม่ที่อาจจะใช้เลขล็อตเดิมในอนาคต

    // ตรวจสอบว่าล็อตนี้ถูกนำไปใช้เตรียมสาร (Prepare) หรือยัง
    $sqlCheckUsage = "SELECT id FROM preparation_ingredients
                      WHERE inventory_id = ? AND status_delete = 0 LIMIT 1";
    $stmtUsage = $pdo->prepare($sqlCheckUsage);
    $stmtUsage->execute([$id]);

    if ($stmtUsage->rowCount() > 0) {
        if ($pdo->inTransaction()) $pdo->rollBack();

        // หากมีการนำไปใช้แล้ว ไม่อนุญาตให้ลบ (หรือต้องให้ไปลบประวัติการเตรียมสารก่อน)
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่สามารถลบล็อตนี้ได้ เนื่องจากมีประวัติการนำสารในล็อตนี้ไปเตรียมใช้งานแล้ว'
        ]);
        exit;
    }

    // เนื่องจากตั้งค่า update_date เป็น ON UPDATE CURRENT_TIMESTAMP ใน DB แล้ว
    // ใน SQL นี้จึงไม่จำเป็นต้อง set update_date เอง
    $sql = "UPDATE chemical_inventory 
            SET status_delete = id, 
                update_by = :update_by 
            WHERE id = :id 
              AND status_delete = 0";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':update_by' => $user_id,
        ':id' => $id
    ]);

    // 5. ตรวจสอบผลการลบ
    if ($result && $stmt->rowCount() > 0) {
        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'ลบข้อมูลล็อตสารเคมีเรียบร้อยแล้ว'
        ]);
    } else {
        throw new Exception('ไม่พบข้อมูลที่ต้องการลบ หรือข้อมูลถูกลบไปแล้ว');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
