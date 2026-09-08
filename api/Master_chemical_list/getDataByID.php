<?php
session_start();
// เปิด Error Reporting ช่วง Dev
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. เช็ค Session ความปลอดภัย
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
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

    // 2. ตรวจสอบว่ามีการส่ง ID มาหรือไม่
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ที่ต้องการ']);
        exit;
    }

    $id = $_GET["id"];

    // --------------------------------------------------------
    // 3. Query ข้อมูลจากตาราง master_chemical_list
    // --------------------------------------------------------
    //<!-- เพิ่มการดึงข้อมูล หน่วยนับของสารเคมี ส่งมาให้ด้วย -->
    $sql = "SELECT 
            t1.id,
            t1.department_id,
            t_dept.department_name,
            t1.chemical_name,
            t1.chemical_brand,
            t1.unit_id,          
            t1.chemical_detail,
            
            t2.unit_name_th,
            t2.unit_name_en,
            t2.unit_symbol,
            t2.unit_group,

            -- นับจำนวนล็อตที่มีการบันทึกเข้าคลังไปแล้ว (ใช้สำหรับ Lock Field หน่วยนับ)
            (SELECT COUNT(*) FROM chemical_inventory 
             WHERE chemical_id = t1.id AND status_delete = 0) AS used_count,

            -- ข้อมูลคนสร้าง (Create)
            CONCAT(t4.rank_name,' ',t3.first_name,' ',t3.last_name) AS fullname_create,
            DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS createdate,

            -- ข้อมูลคนแก้ไขล่าสุด (Update)
            CONCAT(t4_up.rank_name,' ',t3_up.first_name,' ',t3_up.last_name) AS fullname_update,
            DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updatedate

        FROM master_chemical_list t1 
        -- Join หาข้อมูลหน่วยงาน
        LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id
        
        LEFT JOIN master_chemical_units t2 ON t1.unit_id = t2.id
        -- Join หาข้อมูลคนสร้าง
        LEFT JOIN user_profile t3 ON t1.create_by = t3.user_id
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        
        -- Join หาข้อมูลคนแก้ไข (Alias _up)
        LEFT JOIN user_profile t3_up ON t1.update_by = t3_up.user_id 
        LEFT JOIN user_rank t4_up ON t3_up.rank_id = t4_up.rank_id

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
        $data['used_count'] = (int)$data['used_count'];

        // ปั้นข้อความหน่วยนับให้ตาม Logic (Packaging ใช้ชื่ออังกฤษ / อื่นๆ ใช้ตัวย่อ)
        $isPackaging = (strpos($data['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($data['unit_group'], 'Packaging') !== false);
        $symbol = $isPackaging ? $data['unit_name_en'] : $data['unit_symbol'];

        // ส่งค่าที่จัด Format แล้วกลับไปในฟิลด์ใหม่ (เช่น unit_display)
        $data['unit_display'] = $data['unit_name_th'] . " (" . $symbol . ")";

        echo json_encode([
            'status' => 'success',
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบข้อมูลรายการสารเคมีนี้ หรือคุณไม่มีสิทธิ์เข้าถึงรายการนี้'
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
