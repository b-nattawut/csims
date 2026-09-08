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
    // ดึงข้อมูลหน่วยนับสารเคมีทั้งหมด โดยเรียงตาม sort_order ที่เรากำหนดไว้
    $sql = "SELECT id, unit_name_th, unit_name_en, unit_symbol, unit_group
            FROM master_chemical_units 
            ORDER BY sort_order ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $units
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลหน่วยนับสารเคมี: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>