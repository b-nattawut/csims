<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบ Security Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน']);
    exit;
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูล Role และ Department ของผู้ใช้งาน
$stmtUser = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
$stmtUser->execute([$user_id]);
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลผู้ใช้งาน หรือบัญชีถูกระงับ']);
    exit;
}

$user_role    = strtolower($userData['role'] ?? '');
$user_dept_id = $userData['department_id'] ?? null;


// 2. รับค่าจาก Frontend (Filter)
$filter_department_id = $_POST['filter_department_id'] ?? ''; 
$filter_chemical_id = $_POST['filter_chemical_id'] ?? '';
$prep_name  = $_POST['filter_prep_name'] ?? '';
$source_lot = $_POST['filter_source_lot'] ?? '';
$prep_start = $_POST['filter_prep_start'] ?? '';
$prep_end   = $_POST['filter_prep_end'] ?? '';
$status     = $_POST['filter_status'] ?? '';
$preparer   = $_POST['filter_preparer'] ?? '';

$today = date('Y-m-d');
$near_expiry_date = date('Y-m-d', strtotime('+14 days')); // เกณฑ์ 14 วัน (สำหรับสารเคมีที่เตรียมแล้ว)

// ควบคุมสิทธิ์การเข้าถึงข้อมูลตามหน่วยงานอย่างรัดกุม (Department Access Control)
if ($user_role !== 'admin') {
    // ถ้า User ทั่วไปพยายามส่ง department_id ของหน่วยงานอื่นมา -> ปฏิเสธการเข้าถึงทันที (403)
    if (!empty($filter_department_id) && $filter_department_id != $user_dept_id) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึงข้อมูลการเตรียมสารของหน่วยงานอื่น']);
        exit;
    }
    // บังคับล็อกให้ดึงเฉพาะหน่วยงานของตนเองเท่านั้น
    $filter_department_id = $user_dept_id;
}

// 3. ตั้งค่าเงื่อนไขเริ่มต้น
$where = " WHERE t1.status_delete = 0 ";
$params = [];

// 4. สร้าง Dynamic WHERE Query (ปรับปรุงให้ใช้ Subquery ค้นหาไปยังตารางย่อย)

if ($user_role !== 'admin') {
    $filter_department_id = $user_dept_id;
}

// 4. สร้าง Dynamic WHERE Query
// เงื่อนไขกรองด้วยหน่วยงาน (อ้างอิงผ่าน Inventory -> Master Chemical)
if (!empty($filter_department_id)) {
    $where .= " AND t1.id IN (
                    SELECT DISTINCT pi_dept.prep_id 
                    FROM preparation_ingredients pi_dept 
                    JOIN chemical_inventory inv_dept ON pi_dept.inventory_id = inv_dept.id 
                    JOIN master_chemical_list mcl_dept ON inv_dept.chemical_id = mcl_dept.id
                    WHERE mcl_dept.department_id = ? AND pi_dept.status_delete = 0
                ) ";
    $params[] = $filter_department_id;
}

if (!empty($filter_chemical_id)) {
    $where .= " AND t1.id IN (
                    SELECT DISTINCT pi.prep_id 
                    FROM preparation_ingredients pi 
                    JOIN chemical_inventory inv ON pi.inventory_id = inv.id 
                    WHERE inv.chemical_id = ? AND pi.status_delete = 0
                ) ";
    $params[] = $filter_chemical_id;
}

if (!empty($prep_name)) {
    $where .= " AND t1.prep_name LIKE ? ";
    $params[] = "%$prep_name%";
}

if (!empty($source_lot)) {
    $where .= " AND t1.id IN (
                    SELECT DISTINCT pi.prep_id 
                    FROM preparation_ingredients pi 
                    JOIN chemical_inventory inv ON pi.inventory_id = inv.id 
                    WHERE inv.lot_number LIKE ? AND pi.status_delete = 0
                ) ";
    $params[] = "%$source_lot%";
}

if (!empty($preparer)) {
    $where .= " AND t1.preparer_id = ? ";
    $params[] = $preparer;
}

// กรองช่วงวันที่เตรียม
if (!empty($prep_start)) {
    $where .= " AND t1.prep_date >= ? ";
    $params[] = $prep_start;
}
if (!empty($prep_end)) {
    $where .= " AND t1.prep_date <= ? ";
    $params[] = $prep_end;
}

// กรองตามสถานะ
if ($status == 'expired') {
    $where .= " AND t1.expiry_date < ? AND t1.quantity_remaining > 0 ";
    $params[] = $today;
} elseif ($status == 'near_expiry') {
    // ใกล้หมดอายุ: ยังไม่หมดอายุ แต่เหลือไม่ถึง 90 วัน และยังมีของ
    $where .= " AND t1.expiry_date >= ? AND t1.expiry_date <= ? AND t1.quantity_remaining > 0 ";
    $params[] = $today;
    $params[] = $near_expiry_date;
} elseif ($status == 'active') {
    // พร้อมใช้งาน: ยังไม่หมดอายุ และเหลือมากกว่า 90 วัน
    $where .= " AND t1.expiry_date > ? AND t1.quantity_remaining > 0 ";
    $params[] = $near_expiry_date;
} elseif ($status == 'depleted') {
    $where .= " AND t1.quantity_remaining <= 0 ";
}


// 5. Pagination Setup
$limit = 15;
$page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 6. Query ข้อมูลหลัก
$sql = "SELECT 
            t1.*, 
            GROUP_CONCAT(DISTINCT t2.lot_number SEPARATOR ', ') AS source_lot_number,
            GROUP_CONCAT(DISTINCT t5.chemical_name SEPARATOR ' + ') AS source_chemical_name, 
            GROUP_CONCAT(DISTINCT t5.chemical_brand SEPARATOR ', ') AS source_chemical_brand,
            GROUP_CONCAT(DISTINCT t7.unit_symbol SEPARATOR ', ') AS source_unit_symbols,

            -- ข้อมูลหน่วยนับของสารที่เตรียม (Target)
            t6.unit_name_th AS target_unit_th, t6.unit_name_en AS target_unit_en, 
            t6.unit_symbol AS target_unit_sym, t6.unit_group AS target_unit_grp,

            CONCAT(IFNULL(t4.rank_name, ''),' ', t3.first_name, ' ', t3.last_name) AS preparer_fullname,
            -- จัดรูปแบบวันที่สำหรับตาราง
            DATE_FORMAT(t1.prep_date, '%d/%m/%Y') AS prep_date_show,
            DATE_FORMAT(t1.expiry_date, '%d/%m/%Y') AS exp_date_show,
            t1.expiry_date AS expiry_date_raw, -- เก็บไว้ให้ JS คำนวณ Badge
            md.department_name 
        FROM chemical_preparation t1 
        LEFT JOIN preparation_ingredients pi ON t1.id = pi.prep_id AND pi.status_delete = 0
        LEFT JOIN chemical_inventory t2 ON pi.inventory_id = t2.id
        LEFT JOIN master_chemical_list t5 ON t2.chemical_id = t5.id
        LEFT JOIN master_departments md ON t5.department_id = md.id 
        LEFT JOIN user_profile t3 ON t1.preparer_id = t3.user_id
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        LEFT JOIN master_chemical_units t6 ON t1.unit_id = t6.id
        LEFT JOIN master_chemical_units t7 ON t5.unit_id = t7.id
        $where 
        GROUP BY t1.id, 
                 t6.unit_name_th, t6.unit_name_en, t6.unit_symbol, t6.unit_group,
                 t4.rank_name, t3.first_name, t3.last_name,
                 md.department_name 
        ORDER BY t1.prep_date DESC, t1.id DESC 
        LIMIT $limit OFFSET $offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. จัด Format ข้อมูลหน่วยนับก่อนส่งกลับ
    $data = [];
    foreach ($rawData as $row) {
        // --- ปั้นหน่วยนับสารที่เตรียม (Target) ---
        $targetDisplay = '-';
        if (!empty($row['target_unit_th'])) {
            $isPkg = (strpos($row['target_unit_grp'], 'บรรจุภัณฑ์') !== false || strpos($row['target_unit_grp'], 'Packaging') !== false);
            $sym = $isPkg ? $row['target_unit_en'] : $row['target_unit_sym'];
            $targetDisplay = $row['target_unit_th'] . " (" . $sym . ")";
        }
        $row['unit_display'] = $targetDisplay;

        // --- ปั้นหน่วยนับสารตั้งต้น (Source) ---
        $row['source_unit_display'] = $row['source_unit_symbols'] ?: '-';

        $data[] = $row;
    }

    // 7. Query นับจำนวนทั้งหมด
    $sqlCount = "SELECT COUNT(t1.id) as countdata 
                 FROM chemical_preparation t1 
                 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

    $totalRows = (int)$countRes['countdata'];
    $totalPages = ceil($totalRows / $limit);

    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'count' => $totalRows,
        'totalPages' => $totalPages,
        'currentPage' => $page,
        'offset' => $offset
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
