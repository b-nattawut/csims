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

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role    = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // 2. รับค่าจาก Frontend (Filter)
    $dept_id      = $_POST['filter_department_id'] ?? '';
    $chem_name   = $_POST['filter_chem_name'] ?? '';
    $chem_brand  = $_POST['filter_chem_brand'] ?? '';
    $lot_number  = $_POST['filter_lot_number'] ?? '';
    $location    = $_POST['filter_location'] ?? '';
    $stock_status = $_POST['filter_stock_status'] ?? '';

    // 3. ตั้งค่าเงื่อนไขเริ่มต้น
    $where = " WHERE t2.status_delete = 0 ";
    $params = [];

    // ควบคุมสิทธิ์การมองเห็นข้อมูลตามหน่วยงาน (Security & Department Filter)
    if ($user_role !== 'admin') {
        // User ทั่วไป บังคับดึงเฉพาะข้อมูลของหน่วยงานตัวเองเท่านั้น (เพิกเฉยต่อ filter จากหน้าบ้าน)
        $where .= " AND t2.department_id = ? ";
        $params[] = $user_dept_id;
    } else {
        // Admin สามารถเลือกฟิลเตอร์กรองตามหน่วยงานที่ต้องการได้
        if (!empty($dept_id)) { 
            $where .= " AND t2.department_id = ? ";
            $params[] = $dept_id;
        }
    }

    // 4. สร้าง Dynamic WHERE Query
    if (!empty($chem_name)) {
        $where .= " AND (t2.chemical_name LIKE ?) ";
        $params[] = "%$chem_name%";
    }
    if (!empty($chem_brand)) {
        $where .= " AND t2.chemical_brand LIKE ? ";
        $params[] = "%$chem_brand%";
    }

    // ค้นหาผ่าน Lot Number (ใช้ Subquery เพื่อหาว่าสารตัวไหนมีล็อตนี้อยู่)
    if (!empty($lot_number)) {
        $where .= " AND t2.id IN (SELECT chemical_id FROM chemical_inventory WHERE lot_number LIKE ? AND status_delete = 0) ";
        $params[] = "%$lot_number%";
    }

    if (!empty($location)) {
        $where .= " AND t2.id IN (SELECT chemical_id FROM chemical_inventory WHERE location_stored LIKE ? AND status_delete = 0) ";
        $params[] = "%$location%";
    }

    // Pagination Setup
    $limit = 15;
    $page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // 5. จัดการเรื่องสถานะสต็อก (HAVING)
    $having = "";
    if ($stock_status == 'in_stock') {
        $having = " HAVING total_remaining > 0 ";
    } elseif ($stock_status == 'out_of_stock') {
        $having = " HAVING total_remaining <= 0 OR total_remaining IS NULL ";
    } elseif ($stock_status == 'near_expiry') {
        $today = date('Y-m-d');
        $having = " HAVING nearest_expiry <= DATE_ADD('$today', INTERVAL 90 DAY) AND nearest_expiry >= '$today' ";
    }

    // 6. เตรียมฟิลด์คำนวณ (Aggregated Fields)
    // แยกส่วนนี้ออกมาเพื่อให้ใช้ได้ทั้ง SQL หลัก และ SQL Count
    $today = date('Y-m-d');
    $selectFields = "
    -- ยอดรวมทางกายภาพ (ทุกล็อตที่ยังมีของ)
    SUM(CASE WHEN t1.status_delete = 0 THEN t1.quantity_remaining ELSE 0 END) AS total_physical,

    -- ยอดรวมที่ใช้งานได้จริง (ไม่หมดอายุ และ ไม่ deleted)
    SUM(CASE WHEN t1.status_delete = 0 AND t1.expiry_date >= '$today' THEN t1.quantity_remaining ELSE 0 END) AS total_remaining,

    -- วันหมดอายุที่ใกล้ที่สุด (นับเฉพาะล็อตที่ยังมีของ)
    MIN(CASE WHEN t1.status_delete = 0 AND t1.quantity_remaining > 0 THEN t1.expiry_date ELSE NULL END) AS nearest_expiry,

    -- ปริมาณคงเหลือของ 'เฉพาะล็อต' ที่ใกล้หมดอายุที่สุดตัวนั้น
    (SELECT quantity_remaining 
     FROM chemical_inventory 
     WHERE chemical_id = t2.id 
       AND status_delete = 0 
       AND quantity_remaining > 0 
     ORDER BY expiry_date ASC, id ASC 
     LIMIT 1) AS nearest_expiry_qty
";

    // 7. สร้าง SQL
    $coreSql = " FROM master_chemical_list t2
             LEFT JOIN chemical_inventory t1 ON t2.id = t1.chemical_id AND t1.status_delete = 0
             LEFT JOIN master_chemical_units t3 ON t2.unit_id = t3.id
             LEFT JOIN master_departments t_dept ON t2.department_id = t_dept.id 
             $where 
             GROUP BY t2.id 
             $having ";

    // 8. QL หลัก: เพิ่มฟิลด์ที่เหลือที่ต้องการโชว์
    $sql = "SELECT t2.id AS chemical_id, t2.chemical_name, t2.chemical_brand, t2.create_by,
               t_dept.department_name, 
               t3.unit_name_th, t3.unit_name_en, t3.unit_symbol, t3.unit_group,
               MAX(CASE WHEN t1.status_delete = 0 THEN t1.receive_date ELSE NULL END) AS latest_receive_date,
               GROUP_CONCAT(DISTINCT t1.location_stored SEPARATOR ', ') AS locations_summary,
               $selectFields "
        . $coreSql .
        " ORDER BY t2.chemical_name ASC LIMIT $limit OFFSET $offset";

    // --- ทำรายการหลัก ---
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //  จัด Format ข้อมูลก่อนส่งออก (ปั้น unit_display)
    $data = [];
    foreach ($rawData as $row) {
        $unitDisplay = '-';
        if (!empty($row['unit_name_th'])) {
            $isPackaging = (strpos($row['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($row['unit_group'], 'Packaging') !== false);
            $symbol = $isPackaging ? $row['unit_name_en'] : $row['unit_symbol'];
            $unitDisplay = $row['unit_name_th'] . " (" . $symbol . ")";
        }
        $row['unit_display'] = $unitDisplay;
        $data[] = $row;
    }

    // --- ทำรายการนับจำนวน ---
    $sqlCount = "SELECT COUNT(*) as countdata FROM (
                    SELECT t2.id, $selectFields 
                    $coreSql
                 ) AS temp_table";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

    $totalRows = (int)($countRes['countdata'] ?? 0);
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
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
