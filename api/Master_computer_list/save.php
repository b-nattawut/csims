<?php
session_start();
// เปิด Error Reporting ช่วง Dev (ปิดเมื่อขึ้น Production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// ตรวจสอบ Session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

// ข้อมูล System
$user_id = $_SESSION['user_id'];
$dateNow = date('Y-m-d H:i:s');

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // -------------------------------------------------------------------------
    // 1. รับค่าจาก POST (Mapping ให้ตรงกับ name ใน Form คอมพิวเตอร์)
    // -------------------------------------------------------------------------
    $id = $_POST['id'] ?? ''; // ถ้ามี ID แปลว่าเป็นการแก้ไข (Update)

    // ข้อมูลหลักคอมพิวเตอร์
    $department_id = !empty($_POST["department_id"]) ? $_POST["department_id"] : null;
    $asset_no   = trim($_POST["computer_asset_no"] ?? '');
    $brand      = trim($_POST["computer_brand"] ?? '');
    $model      = trim($_POST["computer_model"] ?? '');
    $serial_no  = trim($_POST["computer_serial"] ?? '');
    $serial_no = strtoupper($serial_no);
    $responsible_id = !empty($_POST["responsible_id"]) ? $_POST["responsible_id"] : null;

    // วันที่สำคัญ
    $receive_date   = !empty($_POST["computer_receive_date"]) ? $_POST["computer_receive_date"] : null;
    $start_use_date = !empty($_POST["computer_start_use_date"]) ? $_POST["computer_start_use_date"] : null;

    // ตรวจสอบข้อมูลเบื้องต้น (เช่น เลขครุภัณฑ์ห้ามว่าง)
    if (empty($asset_no)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุเลขครุภัณฑ์คอมพิวเตอร์']);
        exit;
    }

    // การจัดการสิทธิ์หน่วยงาน (Force Department ID)
    if ($user_role !== 'admin') {
        // ถ้าไม่ใช่ Admin บังคับให้ department_id เป็นของตัวเองเสมอ เพิกเฉยค่าที่ส่งมาจากฟอร์ม
        $department_id = $user_dept_id;

        // ถ้าเป็นการแก้ไข (Update) ต้องเช็คด้วยว่าของเดิมเป็นของหน่วยงานตัวเองไหม
        if (!empty($id)) {
            $stmtCheckOwner = $pdo->prepare("SELECT department_id FROM master_computer_list WHERE id = ?");
            $stmtCheckOwner->execute([$id]);
            $owner_dept_id = $stmtCheckOwner->fetchColumn();

            if ($owner_dept_id != $user_dept_id) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์แก้ไข: เครื่องคอมพิวเตอร์นี้เป็นของหน่วยงานอื่น']);
                exit;
            }
        }
    }

    // -------------------------------------------------------------------------
    // 2. ตรวจสอบข้อมูลซ้ำ (Duplicate Check)
    // -------------------------------------------------------------------------
    // เช็คเลขครุภัณฑ์ (computer_asset_no) ซ้ำในรายการที่ยังไม่ถูกลบ (status_delete = 0)
    $sqlCheck = "SELECT id FROM master_computer_list WHERE computer_asset_no = ? AND status_delete = 0";
    $paramsCheck = [$asset_no];

    if (!empty($id)) {
        $sqlCheck .= " AND id != ?";
        $paramsCheck[] = $id;
    }

    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute($paramsCheck);

    if ($stmtCheck->rowCount() > 0) {
        echo json_encode(['status' => 'error', 'message' => "เลขครุภัณฑ์ ($asset_no) นี้มีอยู่ในระบบแล้ว"]);
        exit;
    }

    // -------------------------------------------------------------------------
    // 3. Logic บันทึกข้อมูล (Insert / Update)
    // -------------------------------------------------------------------------
    if (!empty($id)) {
        // --- กรณีแก้ไข (UPDATE) ---
        $sql = "UPDATE master_computer_list SET
                    department_id = ?,
                    computer_asset_no = ?,
                    computer_brand = ?,
                    computer_model = ?,
                    computer_serial = ?,
                    responsible_id = ?, 
                    computer_receive_date = ?,
                    computer_start_use_date = ?,
                    update_by = ?,
                    update_date = ?
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $department_id,
            $asset_no,
            $brand,
            $model,
            $serial_no,
            $responsible_id,
            $receive_date,
            $start_use_date,
            $user_id,
            $dateNow,
            $id
        ]);
    } else {
        // --- กรณีเพิ่มใหม่ (INSERT) ---
        $sql = "INSERT INTO master_computer_list (
                    department_id,
                    computer_asset_no,
                    computer_brand,
                    computer_model,
                    computer_serial,
                    responsible_id, 
                    computer_receive_date,
                    computer_start_use_date,
                    create_by,
                    create_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $department_id,
            $asset_no,
            $brand,
            $model,
            $serial_no,
            $responsible_id,
            $receive_date,
            $start_use_date,
            $user_id,
            $dateNow
        ]);
    }

    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    // จัดการ Error กรณี Unique Key ซ้ำจาก Database โดยตรง (เผื่อเคสหลุดจากตัวเช็คด้านบน)
    if ($e->getCode() == '23000') {
        echo json_encode([
            'status' => 'error',
            'message' => 'เลขครุภัณฑ์คอมพิวเตอร์นี้ถูกใช้งานแล้วในรายการอื่น'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}
