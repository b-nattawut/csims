<?php
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$chemical_name = $_POST['filter_chemical_name'] ?? '';
$prep_date = $_POST['filter_prep_date'] ?? '';
$exp_date = $_POST['filter_exp_date'] ?? '';
$ready_for_use = $_POST['filter_ready_for_use'] ?? '';

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

if ($ready_for_use !== '') { 
    $where .= " AND t1.ready_for_use = ? ";
    $params[] = intval($ready_for_use);
}

// *** ต้องประกาศทิ้งไว้ ห้ามคอมเมนต์หายไป เพราะข้างล่างมีการเรียกใช้ ***
$having = "";
$paramsHaving = [];

$limit = 15; 
$page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// SQL ดึงข้อมูล
$sql = "SELECT t1.id, t1.chemical_name, CONCAT(t4.rank_name,' ',t3.first_name,' ',t3.last_name) AS fullname_create,
t1.remark, t1.description, t1.ready_for_use,
CASE WHEN t1.ready_for_use = 0 THEN 'พร้อมใช้งาน'
     WHEN t1.ready_for_use = 1 THEN 'ไม่พร้อมใช้งาน' END AS ready_use,
DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i:%s') AS createdate,
DATE_FORMAT(t1.prep_date, '%d/%m/%Y') AS prep_date,
DATE_FORMAT(t1.exp_date, '%d/%m/%Y') AS exp_date
FROM master_latent_fingerprint_chemical t1 
LEFT JOIN users t2 ON t1.create_by = t2.user_id
LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
$where 
$having
ORDER BY t1.id DESC
LIMIT $limit OFFSET $offset";

try {
    $stmt = $pdo->prepare($sql);
    // ทำงานได้เพราะประกาศ $paramsHaving ไว้แล้วด้านบน
    $stmt->execute(array_merge($params, $paramsHaving));
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. การนับจำนวน
    $sqlCount = "SELECT COUNT(*) as countdata FROM (
        SELECT CONCAT(t4.rank_name,' ',t3.first_name,' ',t3.last_name) AS fullname_create
        FROM master_latent_fingerprint_chemical t1 
        LEFT JOIN users t2 ON t1.create_by = t2.user_id
        LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id 
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        $where 
        $having
    ) as tmpTable";

    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute(array_merge($params, $paramsHaving));
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