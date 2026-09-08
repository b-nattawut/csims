<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$id = $_GET["id"] ?? null;

if (!$id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบ ID'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            t1.*,
            CONCAT(t4.rank_name, ' ', t3.first_name, ' ', t3.last_name) AS responsive_person_1_name,
            CONCAT(t6.rank_name, ' ', t5.first_name, ' ', t5.last_name) AS responsive_person_2_name,
            DATE_FORMAT(t1.menstruation, '%Y-%m') AS menstruation_format,
            TIME_FORMAT(t1.start_times, '%H:%i') AS start_times_format,
            TIME_FORMAT(t1.end_times, '%H:%i') AS end_times_format
        FROM master_control_temp t1
        LEFT JOIN users t2 ON t1.responsive_person_1 = t2.user_id
        LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        LEFT JOIN users t2b ON t1.responsive_person_2 = t2b.user_id
        LEFT JOIN user_profile t5 ON t2b.user_id = t5.user_id
        LEFT JOIN user_rank t6 ON t5.rank_id = t6.rank_id
        WHERE t1.statusDelete = 0 AND t1.id = ?
    ");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบข้อมูล'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
