<?php
session_start();
// เปิด Error Reporting ช่วง Dev
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบ Session (Security Check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

// 2. ตรวจสอบว่ามีการส่ง ID มาหรือไม่
$id = $_POST['id'] ?? '';
if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ที่ต้องการลบ']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // -------------------------------------------------------------------------
    // 3. เช็คการใช้งานเครื่องมือก่อนลบ (Usage Check)
    // -------------------------------------------------------------------------
    // เราจะนับเฉพาะเครื่องมือที่ยัง "ไม่ถูกลบ" (status_delete = 0)
    $sqlUsage = "SELECT COUNT(id) AS usage_count 
                 FROM master_equipment_list 
                 WHERE category_id = ? AND status_delete = 0";
    
    $stmtUsage = $pdo->prepare($sqlUsage);
    $stmtUsage->execute([$id]);
    $usage = $stmtUsage->fetch(PDO::FETCH_ASSOC);

    if ($usage['usage_count'] > 0) {
        // ถ้ามีเครื่องมือใช้อยู่ ห้ามลบเด็ดขาด!
        echo json_encode([
            'status' => 'error', 
            'message' => 'ไม่สามารถลบได้ เนื่องจากมีเครื่องมือใช้งานประเภทนี้อยู่จำนวน ' . $usage['usage_count'] . ' รายการ'
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4. เตรียมคำสั่ง SQL (Soft Delete)
    // -------------------------------------------------------------------------
    // ใช้ตรรกะ status_delete = id ตามที่คุณต้องการ 
    // และบันทึกข้อมูล Audit Trail (ใครลบ/ลบตอนไหน) ลงในฟิลด์ update
    $sql = "UPDATE master_equipment_categories 
            SET status_delete = id, 
                updated_by = :user_id, 
                updated_at = NOW() 
            WHERE id = :id AND status_delete = 0";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':user_id' => $user_id,
        ':id' => $id
    ]);

    if ($result && $stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'ลบประเภทเครื่องมือเรียบร้อยแล้ว'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบรายการที่ต้องการลบ หรือรายการถูกลบไปแล้ว'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}