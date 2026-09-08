<?php
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$filter_computer_id = $_POST['filter_computer_id'] ?? $_POST['filter_identification'] ?? '';
$filter_caretaker = $_POST['filter_caretaker'] ?? '';
$filter_date_start = $_POST['filter_date_start'] ?? $_POST['filter_date_from'] ?? '';
$filter_date_end = $_POST['filter_date_end'] ?? $_POST['filter_date_to'] ?? '';
$filter_status = $_POST['filter_status'] ?? '';

$where = " WHERE t1.statusDelete = 0 ";
$params = [];

// --- จัดการเงื่อนไข Filter ---
if (!empty($filter_computer_id)) {
    $where .= " AND t1.identification_number LIKE ? ";
    $params[] = "%$filter_computer_id%";
}

if (!empty($filter_caretaker)) {
    $where .= " AND (t3.first_name LIKE ? OR t3.last_name LIKE ? OR CONCAT(t4.rank_name, ' ', t3.first_name, ' ', t3.last_name) LIKE ?) ";
    $params[] = "%$filter_caretaker%";
    $params[] = "%$filter_caretaker%";
    $params[] = "%$filter_caretaker%";
}

if (!empty($filter_date_start)) {
    $where .= " AND DATE(t1.date_use) >= ? ";
    $params[] = $filter_date_start;
}

if (!empty($filter_date_end)) {
    $where .= " AND DATE(t1.date_use) <= ? ";
    $params[] = $filter_date_end;
}

if ($filter_status !== '') {
    $where .= " AND t1.status_condition = ? ";
    $params[] = (int)$filter_status;
}

$limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 15;
if ($limit < 1) $limit = 15;
$page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// SQL ดึงข้อมูล
$sql = "SELECT 
    t1.id, 
    t1.identification_number AS computer_id,
    t1.date_use,
    t1.usage_remark,
    t1.status_condition,
    t1.remark,
    t1.create_date,
    t1.edit_date,
    COALESCE(t1.edit_date, t1.create_date) AS updated_at,
    CONCAT(t4.rank_name, ' ', t3.first_name, ' ', t3.last_name) AS caretaker,
    CONCAT(t6.rank_name, ' ', t5.first_name, ' ', t5.last_name) AS user_used_name,
    CONCAT(t8.rank_name, ' ', t7.first_name, ' ', t7.last_name) AS recorder,
    (SELECT COUNT(*) FROM master_computer_using sub WHERE sub.identification_number = t1.identification_number AND sub.statusDelete = 0) AS log_count
FROM master_computer_using t1 
LEFT JOIN users t2 ON t1.administrator = t2.user_id
LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
LEFT JOIN users t2b ON t1.user_used = t2b.user_id
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
        FROM master_computer_using t1 
        LEFT JOIN users t2 ON t1.administrator = t2.user_id
        LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        $where";

    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

    $totalRows = (int)$countRes['countdata'];
    $totalPages = ceil($totalRows / $limit);

    echo json_encode([
        'success' => true,
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
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
