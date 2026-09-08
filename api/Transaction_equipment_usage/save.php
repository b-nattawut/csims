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

    // 1. รับค่าจากฟอร์ม (POST)
    // map ชื่อตัวแปรให้ตรงกับ name="..." ใน Modal ของ trans_equipment_usage.php
    $usage_id         = $_POST['id'] ?? '';
    $equipment_id     = $_POST['equipment_id'] ?? '';
    $usage_date       = $_POST['usage_date'] ?? '';
    $report_no        = trim($_POST['report_no'] ?? '');
    $condition_status = $_POST['condition_status'] ?? 'ปกติ'; // default ค่ากันเหนียว
    $used_by          = $_POST['used_by'] ?? '';
    $remark           = trim($_POST['remark'] ?? '');

    // 2. ตรวจสอบค่าว่าง (Validation) - บังคับกรอกช่องที่มี *
    if (empty($equipment_id) || empty($usage_date) || empty($report_no) || empty($condition_status) || empty($used_by)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลที่มีเครื่องหมาย * ให้ครบถ้วน']);
        exit;
    }

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Check)
    if ($user_role !== 'admin') {
        // เช็คว่าเครื่องมือ (equipment_id) ที่พยายามจะบันทึกประวัติ เป็นของหน่วยงานตัวเองหรือไม่
        $stmtOwner = $pdo->prepare("SELECT department_id FROM master_equipment_list WHERE id = ? AND status_delete = 0");
        $stmtOwner->execute([$equipment_id]);
        $owner_dept_id = $stmtOwner->fetchColumn();

        if ($owner_dept_id != $user_dept_id) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'ไม่มีสิทธิ์บันทึกข้อมูล: เครื่องมือนี้เป็นของหน่วยงานอื่น'
            ]);
            exit;
        }

        // กรณีเป็นการแก้ไข (UPDATE) ให้เช็คเพิ่มด้วยว่าประวัติเดิมเป็นของเครื่องมือในหน่วยงานตัวเองจริงไหม
        if (!empty($usage_id)) {
            $stmtCheckTrans = $pdo->prepare("
                SELECT m.department_id 
                FROM trans_equipment_usage t 
                JOIN master_equipment_list m ON t.equipment_id = m.id 
                WHERE t.id = ?
            ");
            $stmtCheckTrans->execute([$usage_id]);
            $trans_dept_id = $stmtCheckTrans->fetchColumn();

            if ($trans_dept_id != $user_dept_id) {
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'ไม่มีสิทธิ์แก้ไขรายการนี้: ข้อมูลดังกล่าวเป็นของหน่วยงานอื่น'
                ]);
                exit;
            }
        }
    }

    if (empty($usage_id)) {
        // --- โหมดเพิ่มข้อมูลใหม่ (INSERT) ---
        $sql = "INSERT INTO trans_equipment_usage 
                (equipment_id, usage_date, report_no, condition_status, used_by, remark, create_by, create_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $equipment_id,
            $usage_date,
            $report_no,
            $condition_status,
            $used_by,
            $remark,
            $user_id
        ]);
    } else {
        // --- โหมดแก้ไขข้อมูลเดิม (UPDATE) ---
        $sql = "UPDATE trans_equipment_usage SET 
                usage_date = ?, 
                report_no = ?, 
                condition_status = ?, 
                used_by = ?, 
                remark = ?, 
                update_by = ?, 
                update_date = NOW()
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $usage_date,
            $report_no,
            $condition_status,
            $used_by,
            $remark,
            $user_id,
            $usage_id
        ]);
    }

    // ส่งค่ากลับไปบอก Frontend ว่าบันทึกสำเร็จ
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);
} catch (PDOException $e) {
    // ดัก Error เผื่อเกิดข้อผิดพลาดระดับ Database
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
