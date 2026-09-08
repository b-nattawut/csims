<?php
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$chemical_name = $_POST['filter_chemical_name'] ?? '';
$prep_date = $_POST['filter_prep_date'] ?? '';
$exp_date = $_POST['filter_exp_date'] ?? '';
$brand = $_POST['filter_brand'] ?? '';

$where = " WHERE t1.statusDelete = 0 AND t2.is_active = 1 ";
$params = [];

// --- จัดการเงื่อนไข Filter ---
if (!empty($chemical_name)) {
    $where .= " AND t1.chemical_name LIKE ? ";
    $params[] = "%$chemical_name%";
}

if (!empty($prep_date)) {
    $where .= " AND t1.prep_date BETWEEN ? AND ? ";
    $params[] = $prep_date . " 00:00:00";
    $params[] = $prep_date . " 23:59:59";
}

if (!empty($exp_date)) {
    $where .= " AND t1.exp_date BETWEEN ? AND ? ";
    $params[] = $exp_date . " 00:00:00";
    $params[] = $exp_date . " 23:59:59";
}

if (!empty($brand)) {
    $where .= " AND t1.brand LIKE ? ";
    $params[] = "%$brand%";
}

$limit = 15; 
$page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// SQL ดึงข้อมูล
$sql = "SELECT t1.id, t1.chemical_name, CONCAT(t4.rank_name,' ',t3.first_name,' ',t3.last_name) AS fullname_create,
t1.brand,
CASE WHEN t1.acid_base_test = 0 THEN 'ใช้ได้'
WHEN t1.acid_base_test = 1 THEN 'ใช้ไม่ได้' END AS acid_base_test_status,
CASE WHEN t1.blood_test = 0 THEN 'ใช้ได้' 
WHEN t1.blood_test = 1 THEN 'ใช้ไม่ได้' END AS blood_test_status,
DATE_FORMAT(t1.date_testing, '%d/%m/%Y') AS cvdate_testing,
DATE_FORMAT(t1.prep_date, '%d/%m/%Y') AS cvprep_date,
DATE_FORMAT(t1.exp_date, '%d/%m/%Y') AS cvexp_date
FROM master_chemical_validation t1 
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
        FROM master_chemical_validation t1 
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