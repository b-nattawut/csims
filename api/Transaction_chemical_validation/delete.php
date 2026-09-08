<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบ Session ผู้ใช้งาน
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

// 2. ตรวจสอบพารามิเตอร์ ID
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ของรายการที่ต้องการลบ']);
    exit;
}

$id = $_POST['id'];
$user_id = $_SESSION['user_id'];

try {

    // ดึงข้อมูล Role และ Department ของ User (เพิ่มเช็ค is_active = 1 เพื่อความปลอดภัย)
    $stmtUser = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
    $stmtUser->execute([$user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลผู้ใช้งาน หรือบัญชีถูกระงับ']);
        exit;
    }

    $user_role    = strtolower($userData['role'] ?? '');
    $user_dept_id = $userData['department_id'] ?? null;

    // หาหน่วยงานของรายการทดสอบนี้ (อ้างอิงจากสารตั้งต้นของ preparation ที่นำมาทดสอบ)
    $sqlDeptCheck = "SELECT (
                        SELECT mcl_sub.department_id 
                        FROM preparation_ingredients pi_sub
                        JOIN chemical_inventory inv_sub ON pi_sub.inventory_id = inv_sub.id
                        JOIN master_chemical_list mcl_sub ON inv_sub.chemical_id = mcl_sub.id
                        WHERE pi_sub.prep_id = t2.id AND pi_sub.status_delete = 0 
                        LIMIT 1
                    ) AS department_id
                    FROM trans_chemical_validation t1
                    JOIN chemical_preparation t2 ON t1.prep_id = t2.id
                    WHERE t1.id = ?";
    $stmtDeptCheck = $pdo->prepare($sqlDeptCheck);
    $stmtDeptCheck->execute([$id]);
    $record_dept_id = $stmtDeptCheck->fetchColumn();

    // ถ้าไม่ใช่ Admin และหน่วยงานไม่ตรงกัน ห้ามลบเด็ดขาด (HTTP 403)
    if ($user_role !== 'admin') {
        if (!$record_dept_id || $record_dept_id != $user_dept_id) {
            http_response_code(403);
            echo json_encode([
                'status'  => 'error', 
                'message' => 'ไม่มีสิทธิ์ทำรายการ: คุณไม่สามารถลบข้อมูลของหน่วยงานอื่นได้'
            ]);
            exit;
        }
    }
    
    // เริ่มต้น Transaction เพื่อป้องกันข้อมูลไม่สอดคล้องกัน (Data Inconsistency)
    $pdo->beginTransaction();

    // 3. ดึงข้อมูลรายการนี้ขึ้นมาเช็คสถานะและข้อมูลเพื่อคืนสต็อก
    // ต้อง JOIN หรือเช็คข้อมูลในตาราง trans_chemical_validation

    // ใส่ FOR UPDATE เพื่อล็อค Record ป้องกัน Race Condition
    $sqlCheck = "SELECT prep_id, amount_used, is_verified 
                 FROM trans_chemical_validation 
                 WHERE id = ? AND status_delete = 0 FOR UPDATE";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$id]);
    $valData = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$valData) {
        throw new Exception('ไม่พบข้อมูลการทดสอบที่ต้องการลบ หรือรายการนี้ถูกลบไปแล้ว');
    }

    // เงื่อนไขสำคัญ: ตรวจสอบสถานะการทวนสอบ (is_verified)
    if ((int)$valData['is_verified'] === 1) {
        throw new Exception('ไม่สามารถลบรายการนี้ได้ เนื่องจากได้รับการทวนสอบ (Verified) เรียบร้อยแล้ว');
    }

    $prep_id = $valData['prep_id'];
    $amount_to_restore = (float)$valData['amount_used'];

    // 4. ทำการ Soft Delete รายการ Validation
    $sqlDel = "UPDATE trans_chemical_validation 
                SET status_delete = id, 
                    update_by = :update_by,
                    update_date = NOW()
                WHERE id = :id AND status_delete = 0";
    $stmtDel = $pdo->prepare($sqlDel);
    $stmtDel->execute([
        ':update_by' => $user_id,
        ':id'        => $id
    ]);

    if ($stmtDel->rowCount() === 0) {
        throw new Exception('ไม่สามารถลบรายการนี้ได้ เนื่องจากรายการถูกลบไปก่อนหน้านี้แล้ว');
    }

    // 5. ลบรูปภาพที่ผูกกับรายการนี้ทั้งหมดด้วย (Soft Delete รูปภาพในตาราง attachment)
    $sqlDelAttach = "UPDATE trans_chemical_validation_attachment 
                 SET status_delete = id 
                 WHERE val_test_id = :id AND status_delete = 0";
    $stmtDelAttach = $pdo->prepare($sqlDelAttach);
    $stmtDelAttach->execute([':id' => $id]);

    // 6. คืนสต็อกกลับเข้าสู่ตารางการเตรียมสาร (chemical_preparation)
    // เพราะรายการทดสอบ (Validation) จะตัดยอดมาจากสารที่เตรียมไว้
    $sqlRestore = "UPDATE chemical_preparation 
                    SET quantity_remaining = quantity_remaining + :restore_amount 
                    WHERE id = :prep_id";
    $stmtRestore = $pdo->prepare($sqlRestore);
    $stmtRestore->execute([
        ':restore_amount' => $amount_to_restore,
        ':prep_id' => $prep_id
    ]);

    // 7. ยืนยันการทำรายการทั้งหมด
    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'ลบประวัติการทดสอบและคืนปริมาณสารเรียบร้อยแล้ว'
    ]);
} catch (Exception $e) {
    // หากเกิดข้อผิดพลาด ให้ยกเลิกคำสั่ง SQL ที่รันไปก่อนหน้าทั้งหมด
    if ($pdo->inTransaction()) $pdo->rollBack();

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
