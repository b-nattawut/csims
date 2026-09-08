<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. เช็ค Session (Security Check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// 2. ตรวจสอบว่ามีการส่ง ID มาหรือไม่
$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ที่ต้องการดึงข้อมูล']);
    exit;
}

// -------------------------------------------------------------------------
// 3. Query ข้อมูลจากตาราง master_equipment_categories
// -------------------------------------------------------------------------
$sql = "SELECT 
            t1.id,
            t1.category_name,
            
            -- นับจำนวนเครื่องมือที่ผูกอยู่กับประเภทนี้ (Usage Count)
            (SELECT COUNT(m.id) FROM master_equipment_list m 
             WHERE m.category_id = t1.id AND m.status_delete = 0) AS total_usage,

            -- ข้อมูลคนสร้าง (Created By)
            CONCAT(IFNULL(r_rank.rank_name,''), ' ', r_prof.first_name, ' ', r_prof.last_name) AS creator_name,
            DATE_FORMAT(t1.created_at, '%d/%m/%Y %H:%i น.') AS created_at_full,

            -- ข้อมูลคนแก้ไขล่าสุด (Updated By)
            CONCAT(IFNULL(u_rank.rank_name,''), ' ', u_prof.first_name, ' ', u_prof.last_name) AS updater_name,
            DATE_FORMAT(t1.updated_at, '%d/%m/%Y %H:%i น.') AS updated_at_full

        FROM master_equipment_categories t1 
        
        -- Join ข้อมูลคนสร้าง
        LEFT JOIN user_profile r_prof ON t1.created_by = r_prof.user_id
        LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id

        -- Join ข้อมูลคนแก้ไขล่าสุด
        LEFT JOIN user_profile u_prof ON t1.updated_by = u_prof.user_id
        LEFT JOIN user_rank u_rank ON u_prof.rank_id = u_rank.rank_id

        WHERE t1.id = ? AND t1.status_delete = 0";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        // จัดการ Logic เรื่อง "ยังไม่มีการแก้ไข" ให้กริบก่อนส่งไปหน้าบ้าน
        if (is_null($data['updater_name']) || empty(trim($data['updater_name']))) {
            $data['updater_name'] = '-';
            $data['updated_at_full'] = '';
        }

        echo json_encode([
            'status' => 'success',
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบข้อมูลประเภทเครื่องมือนี้'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}