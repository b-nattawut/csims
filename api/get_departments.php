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
    // ดึงข้อมูลหน่วยงานเฉพาะรายการที่ยังใช้งานอยู่ 
    $sql = "SELECT id, department_name 
            FROM master_departments 
            WHERE status_delete = 0 
            ORDER BY department_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $departments
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลหน่วยงาน'
    ]);
}
?>