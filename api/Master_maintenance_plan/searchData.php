<?php
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$filter_year = $_POST['filter_plan_year'] ?? '';
$filter_group = $_POST['filter_group_work'] ?? '';
$filter_tool_name = $_POST['filter_tool_name'] ?? '';

$where = " WHERE (mc.statusDelete = 0 OR mc.statusDelete IS NULL) ";
$params = [];

// --- จัดการเงื่อนไข Filter ---
if (!empty($filter_year)) {
    $where .= " AND mc.annual_year = ? ";
    $params[] = $filter_year;
}

if (!empty($filter_group)) {
    $where .= " AND mc.group_code LIKE ? ";
    $params[] = "%$filter_group%";
}

if (!empty($filter_tool_name)) {
    $where .= " AND mc.list_tool_name LIKE ? ";
    $params[] = "%$filter_tool_name%";
}

$limit = 15; 
$page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// SQL ดึงข้อมูลแต่ละรายการเครื่องมือ
$sql = "SELECT 
    mc.id,
    mc.annual_year,
    mc.group_code,
    mc.list_tool_name,
    mc.serialNo,
    mc.brand,
    mc.m_jan, mc.m_feb, mc.m_march, mc.m_apr, mc.m_may, mc.m_jun,
    mc.m_jul, mc.m_aug, mc.m_sep, mc.m_oct, mc.m_nov, mc.m_dec,
    mc.responsive_person,
    mc.remark,
    mc.create_by,
    mc.create_date,
    CONCAT(IFNULL(t3.rank_name, ''), ' ', IFNULL(t2.first_name, ''), ' ', IFNULL(t2.last_name, '')) as creator_name
FROM master_instrument_calibration mc
LEFT JOIN users t1 ON mc.create_by = t1.user_id
LEFT JOIN user_profile t2 ON t1.user_id = t2.user_id
LEFT JOIN user_rank t3 ON t2.rank_id = t3.rank_id
$where 
ORDER BY mc.annual_year DESC, mc.group_code ASC, mc.id ASC
LIMIT $limit OFFSET $offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // นับจำนวน
    $sqlCount = "SELECT COUNT(*) as countdata FROM master_instrument_calibration mc $where";

    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

    $totalRows = (int)$countRes['countdata'];
    $totalPages = ceil($totalRows / $limit);

    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'count' => $totalRows,
        'totalRows' => $totalRows,
        'totalPages' => $totalPages,
        'currentPage' => $page,
        'offset' => $offset
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
