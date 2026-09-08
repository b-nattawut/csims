<?php
session_start();
require '../db_config.php'; 
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// 2. รับค่า user_id จาก GET
$user_id = $_GET['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสผู้ใช้งาน']);
    exit;
}

try {
    // 3. Query ดึงเฉพาะชื่อตำแหน่ง
    $sql = "SELECT 
                t2.position_name
            FROM user_profile t1
            LEFT JOIN user_position t2 ON t1.position_id = t2.position_id
            WHERE t1.user_id = ?
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'status' => 'success',
            'position_name' => $result['position_name'] ?? '-'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'ไม่พบข้อมูลตำแหน่งของผู้ใช้งานนี้'
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
?>