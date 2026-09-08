<?php
session_start();
// เปิด Error Reporting สำหรับการ Debug (ปิดเมื่อใช้งานจริง)
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

    // 2. รับค่าจาก POST
    $id              = $_POST['id'] ?? ''; // ถ้ามี ID แปลว่าเป็นการแก้ไข
    $department_id   = $_POST['department_id'] ?? '';
    $chemical_name   = trim($_POST["chemical_name"] ?? '');
    $chemical_brand  = trim($_POST["chemical_brand"] ?? '');
    $unit_id         = trim($_POST["unit_id"] ?? ''); // รับค่าหน่วยนับหลัก
    $chemical_detail = trim($_POST["chemical_detail"] ?? '');

    // การจัดการสิทธิ์หน่วยงาน (Force Department ID)
    if ($user_role !== 'admin') {
        // ถ้าไม่ใช่ Admin บังคับให้ department_id เป็นของตัวเองเสมอ เพิกเฉยค่าที่ส่งมาจากฟอร์ม
        $department_id = $user_dept_id;

        // ถ้าเป็นการแก้ไข (Update) ต้องเช็คด้วยว่าของเดิมเป็นของหน่วยงานตัวเองไหม
        if (!empty($id)) {
            $stmtCheckOwner = $pdo->prepare("SELECT department_id FROM master_chemical_list WHERE id = ?");
            $stmtCheckOwner->execute([$id]);
            $owner_dept_id = $stmtCheckOwner->fetchColumn();

            if ($owner_dept_id != $user_dept_id) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์แก้ไข: สารเคมีนี้เป็นของหน่วยงานอื่น']);
                exit;
            }
        }
    }

    // ตรวจสอบข้อมูลจำเป็น
    if (empty($department_id)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุหน่วยงาน']);
        exit;
    }

    if (empty($chemical_name)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุชื่อสารเคมี']);
        exit;
    }

    if (empty($unit_id)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุหน่วยนับหลัก']);
        exit;
    }
    // -------------------------------------------------------------------------
    // // 3. ตรวจสอบข้อมูลซ้ำ (Unique Check: Name + Brand + Dept + status_delete=0)
    // -------------------------------------------------------------------------
    $sqlCheck = "SELECT id FROM master_chemical_list 
             WHERE chemical_name = ? AND chemical_brand = ? AND department_id = ? AND status_delete = 0";
    $paramsCheck = [$chemical_name, $chemical_brand, $department_id];

    if (!empty($id)) {
        $sqlCheck .= " AND id != ?";
        $paramsCheck[] = $id;
    }

    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute($paramsCheck);

    if ($stmtCheck->rowCount() > 0) {
        $msg = "สารเคมีชื่อ '$chemical_name'";
        if (!empty($chemical_brand)) $msg .= " ยี่ห้อ '$chemical_brand'";
        $msg .= " มีอยู่ในระบบของหน่วยงานที่ท่านเลือกแล้ว";

        echo json_encode(['status' => 'error', 'message' => $msg]);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4. บันทึกข้อมูล (Insert / Update)
    // -------------------------------------------------------------------------
    if (!empty($id)) {
        // --- กรณีแก้ไข (UPDATE) ---

        // ** Security Check: ตรวจสอบว่ามีการใช้งานหน่วยนับนี้ในคลังหรือยัง **
        // ถ้าหน่วยนับที่ส่งมาใหม่ไม่ตรงกับของเดิม และมีการบันทึกล็อตในคลังแล้ว จะไม่อนุญาตให้แก้
        $sqlCheckUsage = "SELECT unit_id, 
                         (SELECT COUNT(id) FROM chemical_inventory WHERE chemical_id = ? AND status_delete = 0) as used_count 
                         FROM master_chemical_list WHERE id = ?";
        $stmtUsage = $pdo->prepare($sqlCheckUsage);
        $stmtUsage->execute([$id, $id]);
        $usageData = $stmtUsage->fetch(PDO::FETCH_ASSOC);

        if ($usageData && (int)$usageData['used_count'] > 0 && (int)$usageData['unit_id'] !== (int)$unit_id) {
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่สามารถเปลี่ยนหน่วยนับได้ เนื่องจากมีการบันทึกรายการในคลังสารเคมีไปแล้ว'
            ]);
            exit;
        }

        $sql = "UPDATE master_chemical_list SET
                    department_id = ?,
                    chemical_name = ?,
                    chemical_brand = ?,
                    unit_id = ?,
                    chemical_detail = ?,
                    update_by = ?,
                    update_date = ?
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $department_id,
            $chemical_name,
            $chemical_brand,
            $unit_id,
            $chemical_detail,
            $user_id,
            $dateNow,
            $id
        ]);
    } else {
        // --- กรณีเพิ่มใหม่ (INSERT) ---
        $sql = "INSERT INTO master_chemical_list (
                    department_id,
                    chemical_name,
                    chemical_brand,
                    unit_id,
                    chemical_detail,
                    create_by,
                    create_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $department_id,
            $chemical_name,
            $chemical_brand,
            $unit_id,
            $chemical_detail,
            $user_id,
            $dateNow
        ]);
    }

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    // ดัก Error Duplicate Entry จาก Database (Unique Key)
    if ($e->getCode() == '23000') {
        echo json_encode([
            'status' => 'error',
            'message' => 'ข้อมูลซ้ำ: มีรายการสารเคมีและยี่ห้อนี้ในระบบแล้ว'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database Error: ' . $e->getMessage()
        ]);
    }
}
