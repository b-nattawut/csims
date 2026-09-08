<?php
session_start();
// เปิด Error Reporting ช่วง Dev (ปิดเมื่อขึ้น Production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../../db_config.php'; 
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบ Session ผู้ใช้งาน
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

// 2. ตรวจสอบว่ามีการส่ง ID มาหรือไม่ 
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ที่ต้องการลบ']);
    exit;
}

$id = $_POST['id'];
$current_user_id = $_SESSION['user_id'];

try {
    // เริ่มต้น Transaction (เผื่ออนาคตต้องลบตารางลูกด้วย)
    $pdo->beginTransaction();

    /**
     * 3. เตรียมคำสั่ง SQL (Soft Delete แบบขั้นสูง)
     * - เปลี่ยน delete_token จาก 0 เป็นค่า ID ของตัวมันเอง เพื่อปลดล็อก Unique report_no
     * - บันทึกข้อมูลว่า ใครลบ และ ลบเมื่อไหร่
     */
    $sql = "UPDATE staff_assessment_header 
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

    // 5. ตรวจสอบว่ามีการแก้ไขแถวใน DB จริงหรือไม่
    if ($stmt->rowCount() > 0) {
        // หากลบสำเร็จ ให้ Commit Transaction
        $pdo->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => 'ลบรายการประเมินเรียบร้อยแล้ว'
        ]);
    } else {
        // หากไม่พบแถวที่ตรงเงื่อนไข (อาจถูกลบไปก่อนแล้ว)
        $pdo->rollBack();
        echo json_encode([
            'status' => 'error', 
            'message' => 'ไม่พบข้อมูลที่ต้องการลบ หรือข้อมูลนี้ถูกลบออกจากระบบไปแล้ว'
        ]);
    }

} catch (PDOException $e) {
    // กรณีเกิด Error ที่ระดับ Database ให้ Rollback ข้อมูล
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