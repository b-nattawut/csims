<?php
session_start();
// เปิด Error Reporting ช่วง Dev
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบ Session
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
    // 2. รับค่าจาก POST (Mapping ให้ตรงกับ input name ในฟอร์ม)
    // -------------------------------------------------------------------------
    $id = $_POST['id'] ?? ''; // ถ้ามี ID แปลว่าแก้ไข

    $department_id         = !empty($_POST["department_id"]) ? $_POST["department_id"] : null;
    $device_code           = trim($_POST["device_code"] ?? '');
    $location_use          = trim($_POST["location_use"] ?? '');

    // ค่าตัวเลข (ช่วงการใช้งาน และ Uncertainty)
    $range_min              = $_POST["range_min"] !== '' ? $_POST["range_min"] : null;
    $range_max              = $_POST["range_max"] !== '' ? $_POST["range_max"] : null;
    $measurement_uncertainty = $_POST["measurement_uncertainty"] !== '' ? $_POST["measurement_uncertainty"] : null;

    // ผู้รับผิดชอบ (รับเป็น User ID จาก Select2 หรือ Dropdown)
    $responsible_person_1   = !empty($_POST["responsible_person_1"]) ? $_POST["responsible_person_1"] : null;
    $responsible_person_2   = !empty($_POST["responsible_person_2"]) ? $_POST["responsible_person_2"] : null;

    // การจัดการสิทธิ์หน่วยงาน (Force Department ID)
    if ($user_role !== 'admin') {
        // ถ้าไม่ใช่ Admin บังคับให้ department_id เป็นของตัวเองเสมอ เพิกเฉยค่าที่ส่งมาจากฟอร์ม
        $department_id = $user_dept_id;

        // ถ้าเป็นการแก้ไข (Update) ต้องเช็คด้วยว่าของเดิมเป็นของหน่วยงานตัวเองไหม
        if (!empty($id)) {
            $stmtCheckOwner = $pdo->prepare("SELECT department_id FROM master_temperature_device WHERE id = ?");
            $stmtCheckOwner->execute([$id]);
            $owner_dept_id = $stmtCheckOwner->fetchColumn();

            if ($owner_dept_id != $user_dept_id) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์แก้ไข: เครื่องวัดอุณหภูมินี้เป็นของหน่วยงานอื่น']);
                exit;
            }
        }
    }

    // -------------------------------------------------------------------------
    // 3. ตรวจสอบข้อมูลซ้ำ (Duplicate Check สำหรับ device_code)
    // -------------------------------------------------------------------------
    if (!empty($device_code)) {
        $sqlCheck = "SELECT id FROM master_temperature_device WHERE device_code = ? AND status_delete = 0";
        $paramsCheck = [$device_code];

        if (!empty($id)) {
            $sqlCheck .= " AND id != ?";
            $paramsCheck[] = $id;
        }

        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute($paramsCheck);

        if ($stmtCheck->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'หมายเลขเครื่องวัดนี้มีอยู่ในระบบแล้ว']);
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // 4. Logic บันทึกข้อมูล (Insert / Update)
    // -------------------------------------------------------------------------
    if (!empty($id)) {
        // --- กรณีแก้ไข (UPDATE) ---
        $sql = "UPDATE master_temperature_device SET
                    department_id = ?, 
                    device_code = ?,
                    range_min = ?,
                    range_max = ?,
                    location_use = ?,
                    measurement_uncertainty = ?,
                    responsible_person_1 = ?,
                    responsible_person_2 = ?,
                    update_by = ?,
                    update_date = ?
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $department_id,
            $device_code,
            $range_min,
            $range_max,
            $location_use,
            $measurement_uncertainty,
            $responsible_person_1,
            $responsible_person_2,
            $user_id,
            $dateNow,
            $id
        ]);
        $msg = "แก้ไขข้อมูลเรียบร้อยแล้ว";
    } else {
        // --- กรณีเพิ่มใหม่ (INSERT) ---
        $sql = "INSERT INTO master_temperature_device (
                    department_id,
                    device_code,
                    range_min,
                    range_max,
                    location_use,
                    measurement_uncertainty,
                    responsible_person_1,
                    responsible_person_2,
                    create_by,
                    create_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $department_id,
            $device_code,
            $range_min,
            $range_max,
            $location_use,
            $measurement_uncertainty,
            $responsible_person_1,
            $responsible_person_2,
            $user_id,
            $dateNow
        ]);
        $msg = "เพิ่มรายการเครื่องวัดใหม่เรียบร้อยแล้ว";
    }

    echo json_encode(['status' => 'success', 'message' => $msg]);
} catch (PDOException $e) {
    // ตรวจสอบว่าใช่ Error Code 23000 (Duplicate Entry) หรือไม่
    if ($e->getCode() == '23000') {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่สามารถบันทึกได้ เนื่องจากหมายเลขเครื่องวัดนี้เคยถูกใช้งานในระบบแล้ว (รวมถึงรายการที่ถูกลบ)'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database Error: ' . $e->getMessage()
        ]);
    }
}
