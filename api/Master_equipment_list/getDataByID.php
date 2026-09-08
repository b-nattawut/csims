<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// เช็ค Session ก่อนเสมอ
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
$stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
$stmtRole->execute([$user_id]);
$userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

$user_role = strtolower($userInfo['role'] ?? '');
$user_dept_id = $userInfo['department_id'] ?? null;

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID']);
    exit;
}

$id = $_GET["id"];

// --------------------------------------------------------
// Query ข้อมูลจากตาราง master_equipment_list
// --------------------------------------------------------
$sql = "SELECT 
            t1.id,
            t1.department_id,
            t_dept.department_name,
            t1.category_id,          
            t5.category_name,
            t1.asset_no,
            t1.tool_name,
            t1.brand,
            t1.model,
            t1.serial_no,
            t1.accessories,    
            
            -- ดึงข้อมูลผู้ดูแล (ID และชื่อเต็ม)
            t1.responsible_id,
            CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS responsible_name,

            -- จัดรูปแบบวันที่สำหรับ input type='date' (YYYY-MM-DD)
            t1.date_receive, 
            t1.date_start_use,
            
            -- จัดรูปแบบวันที่สำหรับแสดงผล text (DD/MM/YYYY)
            DATE_FORMAT(t1.date_receive, '%d/%m/%Y') AS date_receive_show,
            DATE_FORMAT(t1.date_start_use, '%d/%m/%Y') AS date_start_use_show,

            -- ข้อมูลคนสร้าง (Create)
            CONCAT(t4.rank_name,' ',t3.first_name,' ',t3.last_name) AS fullname_create,
            DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS createdate,

            -- ข้อมูลคนแก้ไขล่าสุด (Update)
            CONCAT(t4_up.rank_name,' ',t3_up.first_name,' ',t3_up.last_name) AS fullname_update,
            DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updatedate

        FROM master_equipment_list t1 

        -- JOIN ตารางหน่วยงาน 
        LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id

        -- JOIN ตารางประเภทเครื่องมือ
        LEFT JOIN master_equipment_categories t5 ON t1.category_id = t5.id
        
        -- Join หาผู้ดูแลเครื่องมือ
        LEFT JOIN users r_usr ON t1.responsible_id = r_usr.user_id
        LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
        LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id

        -- Join คนสร้าง
        LEFT JOIN users t2 ON t1.create_by = t2.user_id
        LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        
        -- Join คนแก้ไข (ใช้ Alias _up เพื่อไม่ให้ชื่อซ้ำ)
        LEFT JOIN users t2_up ON t1.update_by = t2_up.user_id
        LEFT JOIN user_profile t3_up ON t2_up.user_id = t3_up.user_id 
        LEFT JOIN user_rank t4_up ON t3_up.rank_id = t4_up.rank_id

        WHERE t1.status_delete = 0 AND t1.id = ? ";
// ดักเรื่องสิทธิ์: ถ้าไม่ใช่ Admin จะต้องเข้าถึงได้เฉพาะของหน่วยงานตัวเอง
$params = [$id];
if ($user_role !== 'admin') {
    $sql .= " AND t1.department_id = ? ";
    $params[] = $user_dept_id;
}

try {
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
            'message' => 'ไม่พบข้อมูลเครื่องมือ หรือคุณไม่มีสิทธิ์เข้าถึงรายการนี้'
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
