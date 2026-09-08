<?php
session_start();
// เปิด Error Reporting ช่วง Dev (ปิดเมื่อขึ้น Production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../../db_config.php'; 
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบสสิทธิ์ผู้ใช้งาน (Security Check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

// 2. ตรวจสอบข้อมูลที่ส่งมา
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสรายการที่ต้องการลบ']);
    exit;
}

$id = $_POST['id'];
$current_user_id = $_SESSION['user_id'];

try {
    // เริ่มต้น Transaction (เผื่ออนาคตมีการเชื่อมโยงกับตารางรายละเอียด Checklist)
    $pdo->beginTransaction();

    /**
     * 3. เตรียมคำสั่ง SQL (Soft Delete แบบ delete_token)
     * - เปลี่ยน delete_token จาก 0 เป็นค่า id เพื่อให้ Unique Index หลุดออก (ถ้ามี)
     * - บันทึกประวัติการลบ (ใครลบ, ลบเมื่อไหร่)
     * - ตรวจสอบว่าต้องเป็นรายการที่ยังไม่ถูกลบ (delete_token = 0)
     */
    $sql = "UPDATE trans_readiness_check_header 
            SET delete_token = id, 
                deleted_by = :deleted_by, 
                deleted_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id AND delete_token = 0";

    $stmt = $pdo->prepare($sql);
    
    // 4. รันคำสั่ง
    $stmt->execute([
        ':deleted_by' => $current_user_id,
        ':updated_by' => $current_user_id,
        ':id'         => $id
    ]);

    // 5. ตรวจสอบผลลัพธ์
    if ($stmt->rowCount() > 0) {
        // หากลบสำเร็จ ให้ Commit ข้อมูล
        $pdo->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => 'ลบบันทึกความพร้อมเรียบร้อยแล้ว'
        ]);
    } else {
        // กรณีไม่พบ ID หรือรายการถูกลบไปก่อนหน้าแล้ว
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode([
            'status' => 'error', 
            'message' => 'ไม่พบข้อมูลที่ต้องการลบ หรือรายการนี้ถูกลบออกจากระบบแล้ว'
        ]);
    }

} catch (PDOException $e) {
    // กรณีเกิด Database Error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
?>