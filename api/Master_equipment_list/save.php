<?php
session_start();
// เปิด Error Reporting ช่วง Dev
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// ตรวจสอบ Session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
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
    // 1. รับค่าจาก POST (Mapping ให้ตรงกับ name="..." ใน Form หน้าบ้าน)
    // -------------------------------------------------------------------------
    $id = $_POST['id'] ?? ''; // ถ้ามี ID แปลว่าแก้ไข

    // ข้อมูลหลัก
    $department_id = !empty($_POST["department_id"]) ? $_POST["department_id"] : null;
    $category_id = !empty($_POST["equipment_category"]) ? $_POST["equipment_category"] : null;
    $tool_name  = trim($_POST["equipment_name"] ?? '');
    $brand      = trim($_POST["equipment_brand"] ?? '');
    $model      = trim($_POST["equipment_model"] ?? '');
    $serial_no  = trim($_POST["equipment_serial"] ?? '');
    $serial_no = strtoupper($serial_no);
    $asset_no   = trim($_POST["asset_no"] ?? '');
    $responsible_id = !empty($_POST["responsible_id"]) ? $_POST["responsible_id"] : null;

    // วันที่
    $date_receive   = !empty($_POST["install_date"]) ? $_POST["install_date"] : null;
    $date_start_use = !empty($_POST["start_use_date"]) ? $_POST["start_use_date"] : null;

    // รวม Accessories (1, 2, 3) เป็น String เดียวคั่นด้วย Comma
    $acc_1 = trim($_POST["accessories_1"] ?? '');
    $acc_2 = trim($_POST["accessories_2"] ?? '');
    $acc_3 = trim($_POST["accessories_3"] ?? '');

    $acc_list = array_filter([$acc_1, $acc_2, $acc_3]); // ตัดค่าว่างออก
    $accessories = implode(', ', $acc_list); // รวมเป็น "สายไฟ, เมาส์, คีย์บอร์ด"

    // การจัดการสิทธิ์หน่วยงาน (Force Department ID)
    if ($user_role !== 'admin') {
        // ถ้าไม่ใช่ Admin บังคับให้ department_id เป็นของตัวเองเสมอ เพิกเฉยค่าที่ส่งมาจากฟอร์ม
        $department_id = $user_dept_id;

        // ถ้าเป็นการแก้ไข (Update) ต้องเช็คด้วยว่าของเดิมเป็นของหน่วยงานตัวเองไหม
        if (!empty($id)) {
            $stmtCheckOwner = $pdo->prepare("SELECT department_id FROM master_equipment_list WHERE id = ?");
            $stmtCheckOwner->execute([$id]);
            $owner_dept_id = $stmtCheckOwner->fetchColumn();

            if ($owner_dept_id != $user_dept_id) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์แก้ไข: เครื่องมือนี้เป็นของหน่วยงานอื่น']);
                exit;
            }
        }
    }

    // -------------------------------------------------------------------------
    // 2. ตรวจสอบข้อมูลซ้ำ (Duplicate Check สำหรับเลขครุภัณฑ์)
    // -------------------------------------------------------------------------

    if (!empty($asset_no)) {

        // เช็คเลขครุภัณฑ์ (asset_no) ซ้ำ (ยกเว้นตัวเองกรณีแก้ไข และต้องไม่ถูกลบ)
        $sqlCheck = "SELECT id FROM master_equipment_list WHERE asset_no = ? AND status_delete = 0";
        $paramsCheck = [$asset_no];

        // ถ้าเป็นการแก้ไข (มี ID ส่งมา) ให้เพิ่มเงื่อนไข "ไม่นับตัวเอง"
        if (!empty($id)) {
            $sqlCheck .= " AND id != ?";
            $paramsCheck[] = $id;
        }

        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute($paramsCheck);

        if ($stmtCheck->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'เลขครุภัณฑ์นี้ (' . $asset_no . ') มีอยู่ในระบบแล้ว']);
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // 3. Logic บันทึกข้อมูล (Insert / Update)
    // -------------------------------------------------------------------------
    if (!empty($id)) {
        // --- กรณีแก้ไข (UPDATE) ---
        $sql = "UPDATE master_equipment_list SET
                    department_id = ?,
                    category_id = ?,
                    asset_no = ?,
                    tool_name = ?,
                    brand = ?,
                    model = ?,
                    serial_no = ?,
                    accessories = ?,
                    date_receive = ?,
                    date_start_use = ?,
                    responsible_id = ?, 
                    update_by = ?,
                    update_date = ?
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $department_id,
            $category_id,
            $asset_no,
            $tool_name,
            $brand,
            $model,
            $serial_no,
            $accessories,
            $date_receive,
            $date_start_use,
            $responsible_id,
            $user_id,
            $dateNow,
            $id
        ]);
    } else {
        // --- กรณีเพิ่มใหม่ (INSERT) ---
        // ไม่ต้องใส่ update_date เพราะ Database จะจัดการเอง
        // ไม่ต้องใส่ status_delete เพราะ Default เป็น 0 อยู่แล้ว
        $sql = "INSERT INTO master_equipment_list (
                    department_id,
                    category_id,
                    asset_no,
                    tool_name,
                    brand,
                    model,
                    serial_no,
                    accessories,
                    date_receive,
                    date_start_use,
                    responsible_id, 
                    create_by,
                    create_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $department_id,
            $category_id,
            $asset_no,
            $tool_name,
            $brand,
            $model,
            $serial_no,
            $accessories,
            $date_receive,
            $date_start_use,
            $responsible_id,
            $user_id,
            $dateNow
        ]);
    }

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    if ($e->getCode() == '23000') { // Error Code สำหรับ Duplicate Entry
        echo json_encode([
            'status' => 'error',
            'message' => 'เลขครุภัณฑ์นี้ถูกใช้งานอยู่ในระบบแล้ว ไม่สามารถเพิ่มซ้ำได้'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database Error: ' . $e->getMessage()
        ]);
    }
}
