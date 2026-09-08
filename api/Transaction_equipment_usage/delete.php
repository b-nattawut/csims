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

$usage_id = $_POST['id'] ?? '';
$user_id = $_SESSION['user_id'];

if (empty($usage_id)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสรายการ']);
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
        // เช็คว่ารายการประวัติการใช้งานที่กำลังจะลบ เป็นของเครื่องมือในหน่วยงานตัวเองหรือไม่
        $stmtOwner = $pdo->prepare("
            SELECT m.department_id 
            FROM trans_equipment_usage t 
            JOIN master_equipment_list m ON t.equipment_id = m.id 
            WHERE t.id = ? AND t.delete_token = 0
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

    // ใช้ Soft Delete โดยการเปลี่ยน delete_token ให้เท่ากับ id ของตัวมันเอง
    $sql = "UPDATE trans_equipment_usage 
            SET delete_token = id, 
                update_by = ?, 
                update_date = NOW() 
            WHERE id = ? AND delete_token = 0";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $usage_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'ลบรายการสำเร็จ']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถลบรายการได้ หรือรายการนี้อาจถูกลบไปแล้ว']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>