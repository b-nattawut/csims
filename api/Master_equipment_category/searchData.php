<?php
session_start();
// เปิด Error Reporting ช่วง Dev
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// 2. รับค่า Filter จาก Frontend
$category_name = trim($_POST['filter_category_name'] ?? '');
$created_by    = $_POST['filter_created_by'] ?? '';
$usage_status = $_POST['filter_usage_status'] ?? '';

// 3. ตั้งค่าเงื่อนไขเริ่มต้น (Soft Delete Check)
$where = " WHERE t1.status_delete = 0 ";
$params = [];

if (!empty($category_name)) {
    $where .= " AND t1.category_name LIKE ? ";
    $params[] = "%$category_name%";
}

if (!empty($created_by)) {
    $where .= " AND t1.created_by = ? ";
    $params[] = $created_by;
}

if ($usage_status === 'active') {
    // ใช้ HAVING หรือ Subquery ใน WHERE
    $where .= " AND (SELECT COUNT(id) FROM master_equipment_list WHERE category_id = t1.id AND status_delete = 0) > 0 ";
} elseif ($usage_status === 'empty') {
    $where .= " AND (SELECT COUNT(id) FROM master_equipment_list WHERE category_id = t1.id AND status_delete = 0) = 0 ";
}


// 4. Pagination (แสดงหน้าละ 15 รายการ)
$limit = 15;
$page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 5. Query ข้อมูลหลัก 
$sql = "SELECT 
            t1.id, 
            t1.category_name,
            t1.created_at,
            DATE_FORMAT(t1.created_at, '%d/%m/%Y') AS created_at_show,
            
            -- ดึงชื่อผู้บันทึก
            CONCAT(IFNULL(r_rank.rank_name,''), ' ', r_prof.first_name, ' ', r_prof.last_name) AS creator_name,
            
            -- นับจำนวนเครื่องมือที่ใช้ Category ID นี้ (เฉพาะที่ยังไม่ถูกลบ)
            (SELECT COUNT(m.id) 
             FROM master_equipment_list m 
             WHERE m.category_id = t1.id AND m.status_delete = 0) AS total_usage

        FROM master_equipment_categories t1 
        LEFT JOIN user_profile r_prof ON t1.created_by = r_prof.user_id
        LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
        
        $where 
        ORDER BY t1.id DESC
        LIMIT $limit OFFSET $offset";

try {
    // ดึงข้อมูลรายการ
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Query นับจำนวนทั้งหมด (สำหรับ Pagination)
    $sqlCount = "SELECT COUNT(t1.id) as countdata FROM master_equipment_categories t1 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

    $totalRows = (int)$countRes['countdata'];
    $totalPages = ceil($totalRows / $limit);

    // ส่งค่ากลับเป็น JSON ให้ตรงกับที่ JS รอรับ
    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'count' => $totalRows,      // จำนวนรายการทั้งหมด
        'totalRows' => $totalRows,
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
