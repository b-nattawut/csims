<?php
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$filter_dpt_name = $_POST['filter_dpt_name'] ?? '';
$filter_thermometer = $_POST['filter_thermometer'] ?? '';
$filter_location = $_POST['filter_location'] ?? '';
$filter_month = $_POST['filter_month'] ?? '';

$where = " WHERE t1.statusDelete = 0 ";
$params = [];

// --- จัดการเงื่อนไข Filter ---
if (!empty($filter_dpt_name)) {
    $where .= " AND t1.dpt_name LIKE ? ";
    $params[] = "%$filter_dpt_name%";
}

if (!empty($filter_thermometer)) {
    $where .= " AND t1.thermometer_number LIKE ? ";
    $params[] = "%$filter_thermometer%";
}

if (!empty($filter_location)) {
    $where .= " AND t1.location_use LIKE ? ";
    $params[] = "%$filter_location%";
}

if (!empty($filter_month)) {
    $where .= " AND DATE_FORMAT(t1.menstruation, '%Y-%m') = ? ";
    $params[] = $filter_month;
}

$limit = 15; 
$page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// SQL ดึงข้อมูล
$sql = "SELECT 
    t1.id, 
    t1.dpt_name,
    t1.thermometer_number,
    t1.location_use,
    t1.menstruation,
    t1.measured_value,
    t1.status_remark,
    CONCAT(t4.rank_name, ' ', t3.first_name, ' ', t3.last_name) AS responsive_person_1_name,
    CONCAT(t6.rank_name, ' ', t5.first_name, ' ', t5.last_name) AS responsive_person_2_name,
    CONCAT(t8.rank_name, ' ', t7.first_name, ' ', t7.last_name) AS fullname_create,
    DATE_FORMAT(t1.menstruation, '%Y-%m') AS menstruation_format,
    TIME_FORMAT(t1.start_times, '%H:%i') AS start_times_format,
    TIME_FORMAT(t1.end_times, '%H:%i') AS end_times_format,
    DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i:%s') AS createdate
FROM master_control_temp t1 
LEFT JOIN users t2 ON t1.responsive_person_1 = t2.user_id
LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
LEFT JOIN users t2b ON t1.responsive_person_2 = t2b.user_id
LEFT JOIN user_profile t5 ON t2b.user_id = t5.user_id
LEFT JOIN user_rank t6 ON t5.rank_id = t6.rank_id
LEFT JOIN users t2c ON t1.create_by = t2c.user_id
LEFT JOIN user_profile t7 ON t2c.user_id = t7.user_id
LEFT JOIN user_rank t8 ON t7.rank_id = t8.rank_id
$where 
ORDER BY t1.id DESC
LIMIT $limit OFFSET $offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // นับจำนวน
    $sqlCount = "SELECT COUNT(*) as countdata 
        FROM master_control_temp t1 
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
