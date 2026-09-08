<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. เช็คว่า Login หรือยัง? (Security Check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน (Unauthorized)'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. รับค่าจากฟอร์ม (POST)
$record_id   = $_POST['id'] ?? '';
$device_id   = $_POST['device_id'] ?? '';
$record_date = $_POST['record_date'] ?? '';
$record_time = $_POST['record_time'] ?? ''; 
$temp_value  = $_POST['temp_value'] ?? ''; // ใช้เป็น string ชั่วคราวเพื่อเช็คค่าว่าง
$status      = $_POST['status'] ?? '';
$time_period = $_POST['time_period'] ?? '';
$recorded_by = $_POST['recorded_by'] ?? '';

// 3. ตรวจสอบค่าว่าง (Validation)
// หมายเหตุ: เช็ค $temp_value === '' แทน empty() เพราะถ้าอุณหภูมิเป็น 0.0 ฟังก์ชัน empty() จะมองว่าเป็น true (ค่าว่าง) ซึ่งจะทำให้บันทึก 0 องศาไม่ได้
if (empty($device_id) || empty($record_date) || empty($record_time) || $temp_value === '' || empty($status) || empty($time_period) || empty($recorded_by)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลที่มีเครื่องหมาย * ให้ครบถ้วน']);
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
        if (empty($record_id)) {
            // กรณี INSERT: เช็คว่าเครื่องวัดอุณหภูมินี้เป็นของหน่วยงานตัวเองไหม
            $stmtCheckDev = $pdo->prepare("SELECT department_id FROM master_temperature_device WHERE id = ? AND status_delete = 0");
            $stmtCheckDev->execute([$device_id]);
            $dev_dept_id = $stmtCheckDev->fetchColumn();

            if ($dev_dept_id != $user_dept_id) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์บันทึกข้อมูล: เครื่องวัดอุณหภูมินี้อยู่คนละหน่วยงาน']);
                exit;
            }
        } else {
            // กรณี UPDATE: เช็คว่า Record เดิมนี้ เป็นของเครื่องวัดในหน่วยงานตัวเองไหม
            $stmtCheckRec = $pdo->prepare("
                SELECT d.department_id 
                FROM trans_temperature_record r
                JOIN master_temperature_device d ON r.device_id = d.id
                WHERE r.id = ? AND r.delete_token = 0
            ");
            $stmtCheckRec->execute([$record_id]);
            $rec_dept_id = $stmtCheckRec->fetchColumn();

            if ($rec_dept_id != $user_dept_id) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์แก้ไขข้อมูล: รายการบันทึกนี้เป็นของหน่วยงานอื่น']);
                exit;
            }
        }
    }

    // 4. เช็คข้อมูลซ้ำ (ป้องกันการจดอุณหภูมิตู้เดียวกัน วันเดียวกัน รอบเดียวกันซ้ำซ้อน)
    $sqlCheck = "SELECT id FROM trans_temperature_record 
                 WHERE device_id = ? 
                 AND record_date = ? 
                 AND time_period = ? 
                 AND delete_token = 0";
    
    $checkParams = [$device_id, $record_date, $time_period];

    // ถ้าเป็นการแก้ไข (มี $record_id) ต้องไม่นับ ID ของตัวเองที่กำลังแก้อยู่
    if (!empty($record_id)) {
        $sqlCheck .= " AND id != ?";
        $checkParams[] = $record_id;
    }

    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute($checkParams);

    if ($stmtCheck->fetch()) {
        // ถ้าคิวรี่แล้วเจอข้อมูล แสดงว่าซ้ำ! ดีด Error กลับไปให้ SweetAlert แสดงผล
        echo json_encode([
            'status' => 'error', 
            'message' => 'มีการบันทึกอุณหภูมิของเครื่องนี้ในวันที่และรอบเวลานี้ไปแล้ว กรุณาตรวจสอบอีกครั้ง!'
        ]);
        exit;
    }

    // 5. บันทึกข้อมูลลง Database
    if (empty($record_id)) {
        // --- โหมดเพิ่มข้อมูลใหม่ (INSERT) ---
        $sql = "INSERT INTO trans_temperature_record 
                (device_id, record_date, record_time, temp_value, status, time_period, recorded_by, create_by, create_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $device_id, $record_date, $record_time, $temp_value, $status, $time_period, $recorded_by, $user_id
        ]);
    } else {
        // --- โหมดแก้ไขข้อมูลเดิม (UPDATE) ---
        $sql = "UPDATE trans_temperature_record SET 
                record_date = ?, 
                record_time = ?,
                temp_value = ?, 
                status = ?, 
                time_period = ?, 
                recorded_by = ?, 
                update_by = ?, 
                update_date = NOW()
                WHERE id = ? AND delete_token = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $record_date, $record_time, $temp_value, $status, $time_period, $recorded_by, $user_id, $record_id
        ]);
    }

    // 6. ตอบกลับสถานะสำเร็จ
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>