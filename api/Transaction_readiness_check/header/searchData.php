<?php
session_start();

require '../../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน']);
    exit;
}

// รับค่า Filter จาก Frontend (ตามชื่อ name ใน Form)
$filter_date_start = $_POST['filter_date_start'] ?? '';
$filter_date_end   = $_POST['filter_date_end'] ?? '';
$filter_team_no    = $_POST['filter_team_no'] ?? '';
$filter_readiness = $_POST['filter_readiness'] ?? ''; 
$filter_status     = $_POST['filter_status'] ?? '';
$filter_checked_by = $_POST['filter_checked_by'] ?? '';

// ตั้งค่าเงื่อนไขเริ่มต้น (Active Records Only)
$where = " WHERE t1.delete_token = 0 ";
$params = [];

// สร้าง Dynamic WHERE Query
if (!empty($filter_date_start)) {
    $where .= " AND t1.check_date >= ? ";
    $params[] = $filter_date_start;
}
if (!empty($filter_date_end)) {
    $where .= " AND t1.check_date <= ? ";
    $params[] = $filter_date_end;
}
if (!empty($filter_team_no)) {
    $where .= " AND t1.team_no = ? ";
    $params[] = $filter_team_no;
}

if (!empty($filter_readiness)) {
    $where .= " AND t1.team_readiness_status = ? ";
    $params[] = $filter_readiness;
}

// 3. สร้าง Dynamic WHERE Query
if (!empty($filter_status)) {
    if ($filter_status === 'COMPLETED') {
        // เสร็จสมบูรณ์: สถานะใน DB เป็น COMPLETED
        $where .= " AND t1.status = 'COMPLETED' ";
    } 
    elseif ($filter_status === 'WAITING_SIGN') {
        // รอลงนาม: เป็น DRAFT และตรวจอุปกรณ์ครบ 4 หมวดแล้ว
        $where .= " AND t1.status = 'DRAFT' 
                    AND (SELECT COUNT(DISTINCT category) FROM trans_readiness_check_results WHERE header_id = t1.id) = 4 ";
    } 
    elseif ($filter_status === 'IN_PROGRESS') {
        // อยู่ระหว่างตรวจสอบ: เป็น DRAFT และยังตรวจไม่ครบ 4 หมวด
        $where .= " AND t1.status = 'DRAFT' 
                    AND (SELECT COUNT(DISTINCT category) FROM trans_readiness_check_results WHERE header_id = t1.id) < 4 ";
    }
}

if (!empty($filter_checked_by)) {
    $where .= " AND t1.checked_by = ? ";
    $params[] = $filter_checked_by;
}

// จัดการ Pagination
$limit = 15;
$page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Query ข้อมูลหลัก (Join เพื่อเอาชื่อเจ้าหน้าที่)
$sql = "SELECT 
            t1.id, 
            t1.check_date,
            DATE_FORMAT(t1.check_date, '%d/%m/%Y') as check_date_show,
            TIME_FORMAT(t1.check_time, '%H:%i') as check_time_show,
            t1.team_no,
            t1.status,
            t1.team_readiness_status, 
            -- นับจำนวนหมวดที่ตรวจแล้วจากตาราง results (นับ DISTINCT category เพื่อความชัวร์)
            (SELECT COUNT(DISTINCT category) 
             FROM trans_readiness_check_results 
             WHERE header_id = t1.id) as checked_count,
            t1.created_by,
            -- ดึงชื่อเจ้าหน้าที่ผู้ตรวจ (Inspector)
            CONCAT(IFNULL(r_rank.rank_name,''), ' ', r_prof.first_name, ' ', r_prof.last_name) AS inspector_fullname
        FROM trans_readiness_check_header t1 
        LEFT JOIN users r_usr ON t1.checked_by = r_usr.user_id
        LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
        LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
        $where 
        ORDER BY t1.check_date DESC, t1.check_time DESC, t1.id DESC
        LIMIT $limit OFFSET $offset";

try {
    // ดึงข้อมูลรายการ
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Query นับจำนวนทั้งหมดสำหรับสร้าง Pagination UI
    $sqlCount = "SELECT COUNT(t1.id) as countdata FROM trans_readiness_check_header t1 $where ";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

    $totalRows = (int)$countRes['countdata'];
    $totalPages = ceil($totalRows / $limit);

    // 8. ส่งข้อมูลกลับ
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