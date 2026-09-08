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
    // 3. เช็คการใช้งานหน่วยงานก่อนลบ (Usage Check) จาก 4 ตาราง
    // -------------------------------------------------------------------------
    // ใช้ Subquery นับจำนวนรายการที่ผูกกับ department_id และยังไม่ถูกลบ
    $sqlUsage = "SELECT 
        (SELECT COUNT(id) FROM master_equipment_list WHERE department_id = :id1 AND status_delete = 0) AS equip_count,
        (SELECT COUNT(id) FROM master_computer_list WHERE department_id = :id2 AND status_delete = 0) AS comp_count,
        (SELECT COUNT(id) FROM master_temperature_device WHERE department_id = :id3 AND status_delete = 0) AS temp_count,
        (SELECT COUNT(id) FROM master_chemical_list WHERE department_id = :id4 AND status_delete = 0) AS chem_count
    ";
    
    $stmtUsage = $pdo->prepare($sqlUsage);
    $stmtUsage->execute([
        ':id1' => $id,
        ':id2' => $id,
        ':id3' => $id,
        ':id4' => $id
    ]);
    $usage = $stmtUsage->fetch(PDO::FETCH_ASSOC);

    // คำนวณยอดรวมทั้งหมด
    $totalUsage = $usage['chem_count'] + $usage['comp_count'] + $usage['equip_count'] + $usage['temp_count'];

    if ($totalUsage > 0) {
        // ถ้ามีการนำไปใช้งาน ห้ามลบเด็ดขาด พร้อมคืนค่าจำนวนเพื่อให้หน้าบ้านแจ้งเตือนได้ชัดเจน
        echo json_encode([
            'status' => 'error', 
            'message' => "ไม่สามารถลบได้ เนื่องจากหน่วยงานนี้ถูกอ้างอิงใช้งานอยู่ {$totalUsage} รายการ (สารเคมี: {$usage['chem_count']}, คอมพิวเตอร์: {$usage['comp_count']}, เครื่องมือ: {$usage['equip_count']}, เครื่องวัด: {$usage['temp_count']})"
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4. เตรียมคำสั่ง SQL (Soft Delete)
    // -------------------------------------------------------------------------
    // อัปเดตตาราง master_departments โดยให้ status_delete = id 
    $sql = "UPDATE master_departments 
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
            'message' => 'ลบหน่วยงานเรียบร้อยแล้ว'
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
?>