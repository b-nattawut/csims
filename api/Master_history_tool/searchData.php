<?php
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$equipment_name = $_POST['filter_equipment_name'] ?? '';
$brand = $_POST['filter_equipment_brand'] ?? '';
$model = $_POST['filter_equipment_model'] ?? '';
$serial_no = $_POST['filter_equipment_serial'] ?? '';
$install_date = $_POST['filter_install_date'] ?? '';
$start_use_date = $_POST['filter_start_use_date'] ?? '';

$where = " WHERE t1.statusDelete = 0 ";
$params = [];

// --- จัดการเงื่อนไข Filter ---
if (!empty($equipment_name)) {
    $where .= " AND t1.tool_name LIKE ? ";
    $params[] = "%$equipment_name%";
}

if (!empty($brand)) {
    $where .= " AND t1.brand LIKE ? ";
    $params[] = "%$brand%";
}

if (!empty($model)) {
    $where .= " AND t1.model LIKE ? ";
    $params[] = "%$model%";
}

if (!empty($serial_no)) {
    $where .= " AND t1.serial_no LIKE ? ";
    $params[] = "%$serial_no%";
}

if (!empty($install_date)) {
    $where .= " AND t1.date_receive = ? ";
    $params[] = $install_date;
}

if (!empty($start_use_date)) {
    $where .= " AND t1.date_use = ? ";
    $params[] = $start_use_date;
}

$limit = 15; 
$page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// SQL ดึงข้อมูล
$sql = "SELECT t1.id, 
t1.tool_name,
t1.brand,
t1.model,
t1.serial_no,
t1.control_equipment_1,
t1.control_equipment_2,
t1.control_equipment_3,
t1.maintenance,
t1.responsible_company,
t1.remark,
t1.responsible_person, 
CONCAT(t4.rank_name,' ',t3.first_name,' ',t3.last_name) AS fullname_create,
DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i:%s') AS createdate,
DATE_FORMAT(t1.date_receive, '%d/%m/%Y') AS date_receive,
DATE_FORMAT(t1.date_use, '%d/%m/%Y') AS date_use,
DATE_FORMAT(t1.date_transaction, '%d/%m/%Y') AS date_transaction
FROM master_history_tool t1 
LEFT JOIN users t2 ON t1.create_by = t2.user_id
LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
$where 
ORDER BY t1.id DESC
LIMIT $limit OFFSET $offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. การนับจำนวน
    $sqlCount = "SELECT COUNT(*) as countdata FROM (
        SELECT CONCAT(t4.rank_name,' ',t3.first_name,' ',t3.last_name) AS fullname_create
        FROM master_history_tool t1 
        LEFT JOIN users t2 ON t1.create_by = t2.user_id
        LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id 
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        $where 
    ) as tmpTable";

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