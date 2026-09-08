<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. เช็คสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$id = $_POST['id'] ?? '';

// 2. เช็คว่ามี ID ส่งมาหรือไม่
if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลที่ต้องการลบ (Missing ID)']);
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
        // เช็คว่าแผนงานที่กำลังจะลบ เป็นของหน่วยงานตัวเองหรือไม่
        $stmtOwner = $pdo->prepare("SELECT department_id FROM trans_maintenance_plan_header WHERE id = ? AND delete_token = 0");
        $stmtOwner->execute([$id]);
        $owner_dept_id = $stmtOwner->fetchColumn();

        if ($owner_dept_id != $user_dept_id) {
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่มีสิทธิ์ลบข้อมูล: แผนงานนี้เป็นของหน่วยงานอื่น'
            ]);
            exit;
        }
    }

    // 3. ใช้เทคนิค Soft Delete (อัปเดต delete_token = id)
    // เพื่อให้หลุดจากเงื่อนไข = 0 และคืนพื้นที่ให้กับ UNIQUE KEY (plan_year + group_name)
    $sql = "UPDATE trans_maintenance_plan_header 
            SET delete_token = id, 
                update_by = ?, 
                update_date = NOW() 
            WHERE id = ? AND delete_token = 0";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $id]);

    // เช็คว่ามีบรรทัดไหนถูกอัปเดตจริงไหม (เผื่อส่ง ID มั่วมา)
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'ลบข้อมูลสำเร็จ'
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'ไม่สามารถลบรายการได้ หรือรายการนี้ถูกลบไปแล้ว'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
?>