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

$log_id = $_POST['id'] ?? '';
$user_id = $_SESSION['user_id'];

if (empty($log_id)) {
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
        // เช็คว่าประวัติการซ่อมบำรุงนี้ ผูกอยู่กับเครื่องมือในหน่วยงานของตนเองหรือไม่
        $stmtCheck = $pdo->prepare("
            SELECT e.department_id 
            FROM trans_equipment_log l
            JOIN master_equipment_list e ON l.equipment_id = e.id
            WHERE l.id = ? AND l.delete_token = 0
        ");
        $stmtCheck->execute([$log_id]);
        $log_dept_id = $stmtCheck->fetchColumn();

        if ($log_dept_id != $user_dept_id) {
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่มีสิทธิ์ลบข้อมูล: ประวัตินี้เป็นของหน่วยงานอื่น'
            ]);
            exit;
        }
    }

    // ใช้ Soft Delete โดยการเปลี่ยน delete_token เป็น 1
    $sql = "UPDATE trans_equipment_log 
            SET delete_token = id, 
                update_by = ?, 
                update_date = NOW() 
            WHERE id = ? 
            AND delete_token = 0";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $log_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'ลบรายการสำเร็จ'
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'ไม่พบรายการนี้ หรือข้อมูลถูกลบไปแล้ว'
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