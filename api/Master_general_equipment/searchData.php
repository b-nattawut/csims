<?php
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$filter_tools_name = $_POST['tools_name'] ?? '';
$filter_identification_number = $_POST['identification_number'] ?? '';
$filter_caretaker = $_POST['administrator'] ?? '';
$filter_status = $_POST['status_condition'] ?? '';

$where = " WHERE t1.statusDelete = 0 ";
$params = [];

// --- จัดการเงื่อนไข Filter ---
if (!empty($filter_tools_name)) {
    $where .= " AND t1.tools_name LIKE ? ";
    $params[] = "%$filter_tools_name%";
}

if (!empty($filter_identification_number)) {
    $where .= " AND t1.identification_number LIKE ? ";
    $params[] = "%$filter_identification_number%";
}

if (!empty($filter_caretaker)) {
    $where .= " AND (t3.first_name LIKE ? OR t3.last_name LIKE ? OR t4.rank_name LIKE ?) ";
    $params[] = "%$filter_caretaker%";
    $params[] = "%$filter_caretaker%";
    $params[] = "%$filter_caretaker%";
}

if ($filter_status !== '') {
    $where .= " AND t1.status_condition = ? ";
    $params[] = $filter_status;
}

$limit = 15; 
$page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// SQL ดึงข้อมูล
$sql = "SELECT t1.id, t1.tools_name, t1.identification_number, 
CONCAT(t4.rank_name,' ',t3.first_name,' ',t3.last_name) AS administrator_name,
DATE_FORMAT(t1.date_use, '%d/%m/%Y') AS date_use_formatted,
t1.usage_remark,
t1.status_condition,
CONCAT(t6.rank_name,' ',t5.first_name,' ',t5.last_name) AS user_used_name,
t1.remark,
CONCAT(t8.rank_name,' ',t7.first_name,' ',t7.last_name) AS fullname_create,
DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i:%s') AS createdate
FROM master_tools_using t1 
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
    $sqlCount = "SELECT COUNT(*) as countdata FROM master_tools_using t1 
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
