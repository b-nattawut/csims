<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบความปลอดภัย (Session)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนดำเนินการ']);
    exit;
}


try {
    // ดึงเฉพาะข้อมูลกล้องที่สถานะปกติ (category_id = 2)
    $sql = "SELECT id, asset_no, tool_name, brand, model, serial_no 
            FROM master_equipment_list 
            WHERE category_id = 2 AND status_delete = 0 
            ORDER BY asset_no ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $cameras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $cameras
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>