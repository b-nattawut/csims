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

// 1. รับค่าตัวแปรจาก GET (Device ID, Month, Year, Time Period)
$device_id = $_GET['device_id'] ?? '';
$month     = $_GET['month'] ?? '';
$year      = $_GET['year'] ?? ''; // ค่าปี ค.ศ. ที่แปลงมาจากฝั่ง JS แล้ว
$time_period = $_GET['time_period'] ?? ''; // รับค่าช่วงเวลา

if (empty($device_id) || empty($month) || empty($year) || empty($time_period)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลที่ส่งมาไม่ครบถ้วน (ต้องการ Device ID, Month, Year, Time Period)']);
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
        // เช็คว่าเครื่องวัดอุณหภูมินี้เป็นของหน่วยงานผู้ใช้งานหรือไม่
        $stmtCheckDev = $pdo->prepare("SELECT department_id FROM master_temperature_device WHERE id = ? AND status_delete = 0");
        $stmtCheckDev->execute([$device_id]);
        $device_dept_id = $stmtCheckDev->fetchColumn();

        if (!$device_dept_id || $device_dept_id != $user_dept_id) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง: เครื่องวัดอุณหภูมินี้อยู่คนละหน่วยงาน']);
            exit;
        }
    }

    // 2. Query ดึงประวัติการจดอุณหภูมิ
    // [เพิ่มใหม่] AND t1.time_period = ? เพื่อกรองเอาเฉพาะรอบเวลาที่เลือก
    // [ปรับปรุง] เอา t1.time_period ASC ออกจาก ORDER BY ได้เลย เพราะข้อมูลถูกกรองมาแค่รอบเดียวแล้ว
    $sqlHistory = "SELECT 
                    t1.id, 
                    t1.device_id, 
                    t1.record_date, 
                    t1.record_time, 
                    TIME_FORMAT(t1.record_time, '%H:%i') AS record_time_show, 
                    t1.temp_value, 
                    t1.status, 
                    t1.time_period, 
                    t1.recorded_by,
                    t1.create_by,
                    DATE_FORMAT(DATE_ADD(t1.record_date, INTERVAL 543 YEAR), '%d/%m/%Y') AS record_date_show,
                    
                    -- ดึงชื่อผู้บันทึกมาแสดง (ยึดตามโครงสร้างตาราง user profile ปกติ)
                    CONCAT(t4.rank_name, ' ', t3.first_name, ' ', t3.last_name) AS recorded_by_name
                    
                   FROM trans_temperature_record t1
                   LEFT JOIN users t2 ON t1.recorded_by = t2.user_id
                   LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
                   LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
                   WHERE t1.device_id = ? 
                     AND MONTH(t1.record_date) = ? 
                     AND YEAR(t1.record_date) = ? 
                     AND t1.time_period = ? 
                     AND t1.delete_token = 0 
                   ORDER BY t1.record_date ASC";
                   
    $stmt = $pdo->prepare($sqlHistory);
    $stmt->execute([$device_id, $month, $year, $time_period]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. ส่งข้อมูลกลับไปให้ JavaScript นำไปวาดตาราง
    echo json_encode([
        'status' => 'success',
        'data'   => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>