<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// ตรวจสอบ Session (ความปลอดภัย)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
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

    // ตรวจสอบ Parameter ID
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID']);
        exit;
    }

    $id = $_GET["id"];

    // --------------------------------------------------------
    // Query ข้อมูลจากตาราง master_temperature_device
    // --------------------------------------------------------
    $sql = "SELECT 
            t1.id,
            t1.department_id,
            t_dept.department_name,
            t1.device_code,
            t1.range_min,
            t1.range_max,
            t1.location_use,
            t1.measurement_uncertainty,
            
            -- ข้อมูลผู้รับผิดชอบ (ID) เพื่อนำไปใส่ใน Select Box ตอนแก้ไข
            t1.responsible_person_1 AS resp_id_1,
            t1.responsible_person_2 AS resp_id_2,

            -- ข้อมูลผู้รับผิดชอบ (ชื่อเต็ม) เพื่อนำไปแสดงผล (View Mode)
            CONCAT(t4_r1.rank_name,' ',t3_r1.first_name,' ',t3_r1.last_name) AS resp_fullname_1,
            CONCAT(t4_r2.rank_name,' ',t3_r2.first_name,' ',t3_r2.last_name) AS resp_fullname_2,

            -- ข้อมูลคนสร้าง (Create)
            CONCAT(t4.rank_name,' ',t3.first_name,' ',t3.last_name) AS fullname_create,
            DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS createdate,

            -- ข้อมูลคนแก้ไขล่าสุด (Update)
            CONCAT(t4_up.rank_name,' ',t3_up.first_name,' ',t3_up.last_name) AS fullname_update,
            DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updatedate

        FROM master_temperature_device t1 

        -- Join หน่วยงาน
        LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id
        
        -- Join คนสร้าง (Create By)
        LEFT JOIN users t2 ON t1.create_by = t2.user_id
        LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        
        -- Join คนแก้ไข (Update By)
        LEFT JOIN users t2_up ON t1.update_by = t2_up.user_id
        LEFT JOIN user_profile t3_up ON t2_up.user_id = t3_up.user_id 
        LEFT JOIN user_rank t4_up ON t3_up.rank_id = t4_up.rank_id

        -- Join ผู้รับผิดชอบคนที่ 1 (Responsible 1) -> ใช้ Alias _r1
        LEFT JOIN users t2_r1 ON t1.responsible_person_1 = t2_r1.user_id
        LEFT JOIN user_profile t3_r1 ON t2_r1.user_id = t3_r1.user_id 
        LEFT JOIN user_rank t4_r1 ON t3_r1.rank_id = t4_r1.rank_id

        -- Join ผู้รับผิดชอบคนที่ 2 (Responsible 2) -> ใช้ Alias _r2
        LEFT JOIN users t2_r2 ON t1.responsible_person_2 = t2_r2.user_id
        LEFT JOIN user_profile t3_r2 ON t2_r2.user_id = t3_r2.user_id 
        LEFT JOIN user_rank t4_r2 ON t3_r2.rank_id = t4_r2.rank_id

        WHERE t1.status_delete = 0 AND t1.id = ? ";
    // ดักเรื่องสิทธิ์: ถ้าไม่ใช่ Admin จะต้องเข้าถึงได้เฉพาะของหน่วยงานตัวเองเท่านั้น
    $params = [$id];
    if ($user_role !== 'admin') {
        $sql .= " AND t1.department_id = ? ";
        $params[] = $user_dept_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบข้อมูลเครื่องวัดอุณหภูมิ หรือคุณไม่มีสิทธิ์เข้าถึงรายการนี้'
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
