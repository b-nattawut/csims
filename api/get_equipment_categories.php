<?php
session_start();
require '../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// ป้องกันการเข้าถึงโดยไม่ได้ Login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    // ดึงข้อมูลประเภทเครื่องมือเฉพาะรายการที่ยังใช้งานอยู่ 
    $sql = "SELECT id, category_name 
            FROM master_equipment_categories 
            WHERE status_delete = 0 
            ORDER BY category_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $categories
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลประเภทเครื่องมือ'
    ]);
}
?>