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

$user_id = $_SESSION['user_id'];

try {

    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // รับค่าจากฟอร์ม (POST)
    $log_id             = $_POST['id'] ?? '';
    $equipment_id       = $_POST['equipment_id'] ?? '';
    $log_date           = $_POST['log_date'] ?? '';
    $maintenance_detail = trim($_POST['maintenance_detail'] ?? '');
    $company_name       = trim($_POST['company_name'] ?? '');
    $officer_name       = trim($_POST['officer_name'] ?? '');
    $remark             = trim($_POST['remark'] ?? '');

    // ตรวจสอบค่าว่าง (Validation)
    if (empty($equipment_id) || empty($log_date) || empty($maintenance_detail)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลที่มีเครื่องหมาย * ให้ครบถ้วน']);
        exit;
    }

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Control)
    if ($user_role !== 'admin') {
        if (empty($log_id)) {
            // กรณี INSERT: เช็คว่าเครื่องมือนี้เป็นของหน่วยงานตัวเองไหม
            $stmtCheck = $pdo->prepare("SELECT department_id FROM master_equipment_list WHERE id = ? AND status_delete = 0");
            $stmtCheck->execute([$equipment_id]);
            $equip_dept_id = $stmtCheck->fetchColumn();

            if ($equip_dept_id != $user_dept_id) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์บันทึกข้อมูล: เครื่องมือนี้อยู่คนละหน่วยงาน']);
                exit;
            }
        } else {
            // กรณี UPDATE: เช็คว่า Log เดิมนี้ เป็นของเครื่องมือในหน่วยงานตัวเองไหม
            $stmtCheck = $pdo->prepare("
                SELECT e.department_id 
                FROM trans_equipment_log l
                JOIN master_equipment_list e ON l.equipment_id = e.id
                WHERE l.id = ? AND l.delete_token = 0
            ");
            $stmtCheck->execute([$log_id]);
            $log_dept_id = $stmtCheck->fetchColumn();

            if ($log_dept_id != $user_dept_id) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์แก้ไขข้อมูล: ประวัตินี้เป็นของหน่วยงานอื่น']);
                exit;
            }
        }
    }

    if (empty($log_id)) {
        // --- โหมดเพิ่มข้อมูลใหม่ (INSERT) ---
        $sql = "INSERT INTO trans_equipment_log 
                (equipment_id, log_date, maintenance_detail, company_name, officer_name, remark, create_by, create_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $equipment_id,
            $log_date,
            $maintenance_detail,
            $company_name,
            $officer_name,
            $remark,
            $user_id
        ]);
    } else {
        // --- โหมดแก้ไขข้อมูลเดิม (UPDATE) ---
        $sql = "UPDATE trans_equipment_log SET 
                log_date = ?, 
                maintenance_detail = ?, 
                company_name = ?, 
                officer_name = ?, 
                remark = ?, 
                update_by = ?, 
                update_date = NOW()
                WHERE id = ? AND delete_token = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $log_date,
            $maintenance_detail,
            $company_name,
            $officer_name,
            $remark,
            $user_id,
            $log_id
        ]);
    }

    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
