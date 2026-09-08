<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$id = $_GET["id"] ?? null;

if (!$id) {
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => 'ไม่พบ ID'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            t1.id,
            t1.identification_number AS computer_id,
            t1.administrator,
            t1.date_use,
            t1.usage_remark,
            t1.status_condition,
            t1.user_used,
            t1.remark,
            t1.create_by,
            t1.create_date,
            CONCAT(t4.rank_name, ' ', t3.first_name, ' ', t3.last_name) AS caretaker,
            CONCAT(t6.rank_name, ' ', t5.first_name, ' ', t5.last_name) AS user_used_name
        FROM master_computer_using t1
        LEFT JOIN users t2 ON t1.administrator = t2.user_id
        LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        LEFT JOIN users t2b ON t1.user_used = t2b.user_id
        LEFT JOIN user_profile t5 ON t2b.user_id = t5.user_id
        LEFT JOIN user_rank t6 ON t5.rank_id = t6.rank_id
        WHERE t1.statusDelete = 0 AND t1.id = ?
    ");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => 'ไม่พบข้อมูล'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
