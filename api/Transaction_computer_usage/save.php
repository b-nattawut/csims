<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

/**
 * 1. ระบบรักษาความปลอดภัย (Security Check)
 */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน (Unauthorized)'
    ]);
    exit;
}

$user_id = $_SESSION['user_id']; // ID ผู้บันทึก/แก้ไขรายการ

try {

    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    /**
     * 2. รับค่าจากฟอร์ม (POST)
     * แมปชื่อตัวแปรให้ตรงกับ name="..." ใน Modal ของหน้า trans_computer_usage
     */
    $usage_id         = $_POST['id'] ?? '';               // ถ้ามีค่า = แก้ไข (Update)
    $computer_id      = $_POST['computer_id'] ?? '';      // FK เชื่อมกับ Master Computer
    $usage_date       = $_POST['usage_date'] ?? '';       // วันที่ใช้งาน
    $report_no        = trim($_POST['report_no'] ?? '');  // วัตถุประสงค์ / งานที่ทำ
    $condition_status = $_POST['condition_status'] ?? 'ปกติ'; // สภาพเครื่อง
    $used_by          = $_POST['used_by'] ?? '';          // ID ผู้ใช้งานคอมพิวเตอร์
    $remark           = trim($_POST['remark'] ?? '');     // หมายเหตุ

    /**
     * 3. ตรวจสอบข้อมูลเบื้องต้น (Validation)
     */
    if (empty($computer_id) || empty($usage_date) || empty($report_no) || empty($condition_status) || empty($used_by)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'กรุณากรอกข้อมูลที่มีเครื่องหมาย * ให้ครบถ้วน'
        ]);
        exit;
    }

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Check)
    if ($user_role !== 'admin') {
        // เช็คว่าคอมพิวเตอร์ (computer_id) ที่พยายามจะบันทึกประวัติ เป็นของหน่วยงานตัวเองหรือไม่
        $stmtOwner = $pdo->prepare("SELECT department_id FROM master_computer_list WHERE id = ? AND status_delete = 0");
        $stmtOwner->execute([$computer_id]);
        $owner_dept_id = $stmtOwner->fetchColumn();

        if ($owner_dept_id != $user_dept_id) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'ไม่มีสิทธิ์บันทึกข้อมูล: เครื่องคอมพิวเตอร์นี้เป็นของหน่วยงานอื่น'
            ]);
            exit;
        }

        // กรณีเป็นการแก้ไข (UPDATE) ให้เช็คเพิ่มด้วยว่าประวัติเดิมเป็นของคอมพิวเตอร์ในหน่วยงานตัวเองจริงไหม
        if (!empty($usage_id)) {
            $stmtCheckTrans = $pdo->prepare("
                SELECT m.department_id 
                FROM trans_computer_usage t 
                JOIN master_computer_list m ON t.computer_id = m.id 
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
        /**
         * --- โหมดเพิ่มข้อมูลใหม่ (INSERT) ---
         * บันทึกข้อมูลลงตาราง trans_computer_usage
         */
        $sql = "INSERT INTO trans_computer_usage 
                (computer_id, usage_date, report_no, condition_status, used_by, remark, create_by, create_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $computer_id,
            $usage_date,
            $report_no,
            $condition_status,
            $used_by,
            $remark,
            $user_id
        ]);

        $message = 'เพิ่มประวัติการใช้งานเรียบร้อยแล้ว';
    } else {
        /**
         * --- โหมดแก้ไขข้อมูลเดิม (UPDATE) ---
         * แก้ไขข้อมูลโดยอ้างอิงจาก Primary Key (id)
         */
        $sql = "UPDATE trans_computer_usage SET 
                    usage_date = ?, 
                    report_no = ?, 
                    condition_status = ?, 
                    used_by = ?, 
                    remark = ?, 
                    update_by = ?, 
                    update_date = NOW()
                WHERE id = ? AND status_delete = 0";

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

        $message = 'แก้ไขประวัติการใช้งานเรียบร้อยแล้ว';
    }

    // ส่งค่ากลับไปบอก Frontend (JavaScript) เพื่อแสดงผลสำเร็จ
    echo json_encode([
        'status' => 'success',
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    // จัดการ Error กรณีเกิดปัญหากับ Database
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
