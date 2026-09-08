<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน']);
    exit;
}

// -------------------------------------------------------------------------
// 1. รับค่า Filter จาก Frontend
// -------------------------------------------------------------------------
$filter_report_no  = $_POST['filter_report_no'] ?? '';
$filter_location   = $_POST['filter_location'] ?? '';
$filter_case_scope = $_POST['filter_case_scope'] ?? '';
$filter_status     = $_POST['filter_status'] ?? '';
$filter_evaluator  = $_POST['filter_evaluator'] ?? '';
$filter_date_start = $_POST['filter_date_start'] ?? '';
$filter_date_end   = $_POST['filter_date_end'] ?? '';

// นิยาม SQL สำหรับคำนวณจำนวน (ใช้ซ้ำทั้งใน WHERE และ SELECT)
// นับจำนวนตำแหน่งที่มีรายชื่อระบุไว้จริงใน Header
$sql_active_roles = "(IF(t1.leader_id > 0, 1, 0) + IF(t1.photographer_id > 0, 1, 0) + IF(t1.map_maker_id > 0, 1, 0) + IF(t1.searcher_id > 0, 1, 0) + IF(t1.collector_id > 0, 1, 0))";
// นับจำนวนคนที่ประเมินแล้วจากตาราง results
$sql_evaluated_count = "(SELECT COUNT(id) FROM staff_assessment_results WHERE header_id = t1.id)";

// -------------------------------------------------------------------------
// 2. ตั้งค่าเงื่อนไขเริ่มต้น (ค้นหาเฉพาะรายการที่ยังไม่ถูกลบ)
// -------------------------------------------------------------------------
$where = " WHERE t1.delete_token = 0 ";
$params = [];

// 3. สร้าง Dynamic WHERE Query
if (!empty($filter_report_no)) {
    $where .= " AND t1.report_no LIKE ? ";
    $params[] = "%$filter_report_no%";
}

if (!empty($filter_location)) {
    $where .= " AND t1.location LIKE ? ";
    $params[] = "%$filter_location%";
}

if (!empty($filter_case_scope)) {
    $where .= " AND t1.case_scope = ? ";
    $params[] = $filter_case_scope;
}

// Logic การกรองสถานะแบบใหม่
if (!empty($filter_status)) {
    if ($filter_status === 'COMPLETED') {
        // เสร็จสมบูรณ์ (เซ็นชื่อจบงานแล้ว)
        $where .= " AND t1.status = 'COMPLETED' ";
    } 
    elseif ($filter_status === 'WAITING_SIGN') {
        // รอลงนาม (สถานะเป็น DRAFT และ ประเมินครบทุกคนที่มีชื่อแล้ว)
        $where .= " AND t1.status = 'DRAFT' AND $sql_evaluated_count >= $sql_active_roles AND $sql_active_roles > 0 ";
    } 
    elseif ($filter_status === 'IN_PROGRESS') {
        // รอประเมิน (สถานะเป็น DRAFT และ ยังประเมินไม่ครบตามรายชื่อ)
        $where .= " AND t1.status = 'DRAFT' AND ($sql_evaluated_count < $sql_active_roles OR $sql_active_roles = 0) ";
    }
}

if (!empty($filter_evaluator)) {
    $where .= " AND t1.evaluator_id = ? ";
    $params[] = $filter_evaluator;
}

// กรองช่วงวันที่ดำเนินการ
if (!empty($filter_date_start)) {
    $where .= " AND t1.operation_date >= ? ";
    $params[] = $filter_date_start;
}
if (!empty($filter_date_end)) {
    $where .= " AND t1.operation_date <= ? ";
    $params[] = $filter_date_end;
}

// 4. Pagination
$limit = 15;
$page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// -------------------------------------------------------------------------
// 5. Query ข้อมูลหลัก
// -------------------------------------------------------------------------
$sql = "SELECT 
            t1.id, 
            t1.report_no,
            t1.operation_date,
            DATE_FORMAT(t1.operation_date, '%d/%m/%Y') as operation_date_show,
            t1.location,
            t1.case_scope,
            t1.status,
            -- ส่งจำนวนไปให้ renderTable ใช้แสดงผล (X/Y)
            $sql_active_roles as total_active_staff,
            $sql_evaluated_count as evaluated_count,
            t1.created_by,
            -- Join ชื่อผู้ประเมิน
            CONCAT(IFNULL(t4.rank_name,''), ' ', t3.first_name, ' ', t3.last_name) AS evaluator_fullname
        FROM staff_assessment_header t1 
        LEFT JOIN users t2 ON t1.evaluator_id = t2.user_id
        LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        $where 
        ORDER BY t1.id DESC
        LIMIT $limit OFFSET $offset";

try {
    // ดึงข้อมูลหลัก
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Query นับจำนวนทั้งหมดสำหรับ Pagination
    $sqlCount = "SELECT COUNT(t1.id) as countdata FROM staff_assessment_header t1 $where ";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

    $totalRows = (int)$countRes['countdata'];
    $totalPages = ceil($totalRows / $limit);

    // 7. ส่งค่ากลับให้ Frontend
    echo json_encode([
        'status'      => 'success',
        'data'        => $data,
        'count'       => $totalRows,
        'totalRows'   => $totalRows,
        'totalPages'  => $totalPages,
        'currentPage' => $page,
        'offset'      => $offset
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}