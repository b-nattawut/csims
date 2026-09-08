<?php
session_start();
// เปิด Error Reporting ช่วงพัฒนา
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

$user_id = $_SESSION['user_id'];

// ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน 
$stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
$stmtRole->execute([$user_id]);
$userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

$user_role = strtolower($userInfo['role'] ?? '');
$user_dept_id = $userInfo['department_id'] ?? null;

// 2. ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสอ้างอิง (ID)']);
    exit;
}

$id = $_GET["id"];

/**
 * 3. Query ข้อมูลจากตาราง master_computer_list
 * เน้นการ JOIN เพื่อดึงชื่อเต็มพร้อมยศ/ตำแหน่งของผู้ดูแลและคนทำรายการ
 */
$sql = "SELECT 
            t1.id,
            t1.computer_asset_no,
            t1.computer_brand,
            t1.computer_model,
            t1.computer_serial,
            
            -- ข้อมูลหน่วยงาน
            t1.department_id,
            t_dept.department_name,

            -- ข้อมูลผู้รับผิดชอบ/ผู้ใช้งาน
            t1.responsible_id,
            CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS responsible_fullname,

            -- ข้อมูลวันที่ (Raw) สำหรับหยอดลง input type='date'
            t1.computer_receive_date, 
            t1.computer_start_use_date,
            
            -- ข้อมูลวันที่ (Show) สำหรับแสดงผลใน Modal Detail (DD/MM/YYYY)
            DATE_FORMAT(t1.computer_receive_date, '%d/%m/%Y') AS receive_date_show,
            DATE_FORMAT(t1.computer_start_use_date, '%d/%m/%Y') AS start_use_date_show,

            -- ข้อมูลประวัติการสร้าง
            CONCAT(t4.rank_name,' ',t3.first_name,' ',t3.last_name) AS fullname_create,
            DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS createdate_show,

            -- ข้อมูลประวัติการแก้ไขล่าสุด
            CONCAT(t4_up.rank_name,' ',t3_up.first_name,' ',t3_up.last_name) AS fullname_update,
            DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updatedate_show

        FROM master_computer_list t1 
        
        -- Join ตารางหน่วยงาน
        LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id

        -- Join ข้อมูลผู้รับผิดชอบ
        LEFT JOIN users r_usr ON t1.responsible_id = r_usr.user_id
        LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
        LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id

        -- Join ข้อมูลผู้สร้างรายการ
        LEFT JOIN users t2 ON t1.create_by = t2.user_id
        LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        
        -- Join ข้อมูลผู้แก้ไขรายการล่าสุด
        LEFT JOIN users t2_up ON t1.update_by = t2_up.user_id
        LEFT JOIN user_profile t3_up ON t2_up.user_id = t3_up.user_id 
        LEFT JOIN user_rank t4_up ON t3_up.rank_id = t4_up.rank_id

        WHERE t1.status_delete = 0 AND t1.id = ? ";

// ถ้าไม่ใช่ Admin ให้เติมเงื่อนไข AND ว่าต้องเป็นข้อมูลของหน่วยงานตัวเองเท่านั้น
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
        // ส่งข้อมูลกลับไปให้ JavaScript นำไป Map ลง Modal
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบข้อมูลเครื่องคอมพิวเตอร์ หรือคุณไม่มีสิทธิ์เข้าถึงรายการนี้'
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
