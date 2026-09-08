<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. เช็ค Session ความปลอดภัย
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// 2. ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ที่ต้องการ']);
    exit;
}

$id = $_GET["id"];
$user_id = $_SESSION['user_id'];

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน (เพิ่มเช็ค is_active = 1)
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    if (!$userInfo) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลผู้ใช้งาน หรือบัญชีถูกระงับ']);
        exit;
    }

    $user_role    = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // 1. ดึงข้อมูลหลัก (Header)
    $sql = "SELECT t1.*, 
                   -- ผู้เตรียม
                   CONCAT(IFNULL(r_p.rank_name, ''), ' ', u_p.first_name, ' ', u_p.last_name) AS preparer_fullname,
                   -- ผู้สร้าง
                   CONCAT(IFNULL(r_c.rank_name, ''), ' ', u_c.first_name, ' ', u_c.last_name) AS fullname_create,
                   DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS createdate_show,
                   -- ผู้แก้ไข
                   CONCAT(IFNULL(r_up.rank_name, ''), ' ', u_up.first_name, ' ', u_up.last_name) AS fullname_update,
                   DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updatedate_show,
                   -- วันที่
                   t1.prep_date AS prep_date_raw,
                   t1.expiry_date AS exp_date_raw,
                   DATE_FORMAT(t1.prep_date, '%d/%m/%Y') AS prep_date_show,
                   DATE_FORMAT(t1.expiry_date, '%d/%m/%Y') AS exp_date_show,
                   -- หน่วยนับของสารที่เตรียม 
                   t4.unit_name_th, t4.unit_name_en, t4.unit_symbol, t4.unit_group,

                    -- ดึง department_id และ department_name จากสารตั้งต้น
                   (SELECT mcl.department_id 
                    FROM preparation_ingredients pi 
                    JOIN chemical_inventory inv ON pi.inventory_id = inv.id 
                    JOIN master_chemical_list mcl ON inv.chemical_id = mcl.id
                    WHERE pi.prep_id = t1.id AND pi.status_delete = 0 LIMIT 1) AS department_id,

                   -- ดึงชื่อหน่วยงาน (วิ่งผ่าน Inventory -> Master Chemical -> Department)
                   (SELECT md.department_name 
                    FROM preparation_ingredients pi 
                    JOIN chemical_inventory inv ON pi.inventory_id = inv.id 
                    JOIN master_chemical_list mcl ON inv.chemical_id = mcl.id
                    JOIN master_departments md ON mcl.department_id = md.id 
                    WHERE pi.prep_id = t1.id AND pi.status_delete = 0 LIMIT 1) AS department_name

            FROM chemical_preparation t1
            LEFT JOIN user_profile u_p ON t1.preparer_id = u_p.user_id
            LEFT JOIN user_rank r_p ON u_p.rank_id = r_p.rank_id

            LEFT JOIN user_profile u_c ON t1.create_by = u_c.user_id
            LEFT JOIN user_rank r_c ON u_c.rank_id = r_c.rank_id

            LEFT JOIN user_profile u_up ON t1.update_by = u_up.user_id
            LEFT JOIN user_rank r_up ON u_up.rank_id = r_up.rank_id

            LEFT JOIN master_chemical_units t4 ON t1.unit_id = t4.id

            WHERE t1.status_delete = 0 AND t1.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลการเตรียมสารเคมีนี้ หรืออาจถูกลบไปแล้ว']);
        exit;
    }

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Control)
    if ($user_role !== 'admin') {
        if ($data['department_id'] != $user_dept_id) {
            http_response_code(403);
            echo json_encode([
                'status'  => 'error',
                'message' => 'ไม่มีสิทธิ์เข้าถึง: รายการนี้เป็นของหน่วยงานอื่น'
            ]);
            exit;
        }
    }

    // 2. ดึงรายการสารตั้งต้นทั้งหมด (Ingredients)
    $sqlIng = "SELECT pi.*, 
                      inv.lot_number, 
                      inv.quantity_remaining,
                      m.id AS chemical_id,
                      m.chemical_name, 
                      m.chemical_brand,
                      u.unit_name_th, u.unit_symbol, u.unit_group, u.unit_name_en
               FROM preparation_ingredients pi
               LEFT JOIN chemical_inventory inv ON pi.inventory_id = inv.id
               LEFT JOIN master_chemical_list m ON inv.chemical_id = m.id
               LEFT JOIN master_chemical_units u ON pi.unit_id = u.id
               WHERE pi.prep_id = ? AND pi.status_delete = 0";

    $stmtIng = $pdo->prepare($sqlIng);
    $stmtIng->execute([$id]);
    $ingredients = $stmtIng->fetchAll(PDO::FETCH_ASSOC);

    // 3. จัด Format ข้อมูลหน่วยนับของแต่ละสารตั้งต้น
    foreach ($ingredients as &$ing) {
        $unitDisplay = '-';
        if (!empty($ing['unit_name_th'])) {
            $isPkg = (strpos($ing['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($ing['unit_group'], 'Packaging') !== false);
            $sym = $isPkg ? $ing['unit_name_en'] : $ing['unit_symbol'];
            $unitDisplay = $ing['unit_name_th'] . " (" . $sym . ")";
        }
        $ing['unit_display'] = $unitDisplay;
    }
    unset($ing); // เคลียร์ reference

    $unitDisplay = '-';
    if (!empty($data['unit_name_th'])) {
        $isPkg = (strpos($data['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($data['unit_group'], 'Packaging') !== false);
        $sym = $isPkg ? $data['unit_name_en'] : $data['unit_symbol'];
        $unitDisplay = $data['unit_name_th'] . " (" . $sym . ")";
    }
    $data['unit_display'] = $unitDisplay;

    // รวมข้อมูลทั้งหมดเข้าด้วยกัน
    $data['ingredients'] = $ingredients;

    echo json_encode([
        'status' => 'success',
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
