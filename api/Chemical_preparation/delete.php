<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบ Session ผู้ใช้งาน
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

// 2. ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ของรายการที่ต้องการลบ']);
    exit;
}

$id = $_POST['id'];
$user_id = $_SESSION['user_id'];

try {
    // เริ่มต้น Transaction
    $pdo->beginTransaction();

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

    // หาหน่วยงานของรายการเตรียมสารเคมีนี้ (ดึงอ้างอิงจากสารตั้งต้นตัวแรกในสูตรผ่าน Master Chemical)
    $sqlPrepDept = "SELECT mcl.department_id 
                    FROM preparation_ingredients pi
                    JOIN chemical_inventory i ON pi.inventory_id = i.id
                    JOIN master_chemical_list mcl ON i.chemical_id = mcl.id
                    WHERE pi.prep_id = ? AND pi.status_delete = 0 LIMIT 1";
    $stmtPrepDept = $pdo->prepare($sqlPrepDept);
    $stmtPrepDept->execute([$id]);
    $prep_dept_id = $stmtPrepDept->fetchColumn();

    // ตรวจสอบสิทธิ์หน่วยงาน (ถ้าไม่ใช่ Admin และหน่วยงานไม่ตรงกัน ห้ามลบเด็ดขาด)
    if ($user_role !== 'admin') {
        if (!$prep_dept_id || $prep_dept_id != $user_dept_id) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(403);
            echo json_encode([
                'status'  => 'error', 
                'message' => 'ไม่มีสิทธิ์ทำรายการ: คุณไม่สามารถลบข้อมูลของหน่วยงานอื่นได้'
            ]);
            exit;
        }
    }

    // 3. ตรวจสอบว่ามีการนำสารที่เตรียมนี้ไป "ใช้งานจริงหรือยัง" 
    // เช็คทั้ง 2 ตาราง: trans_chemical_validation และ trans_latent_chemical_test
    
    // เช็คฝั่ง Validation
    $sqlCheckVal = "SELECT id FROM trans_chemical_validation 
                    WHERE prep_id = ? AND status_delete = 0 LIMIT 1";
    $stmtVal = $pdo->prepare($sqlCheckVal);
    $stmtVal->execute([$id]);

    // เช็คฝั่ง Latent
    $sqlCheckLatent = "SELECT id FROM trans_latent_chemical_test 
                       WHERE prep_id = ? AND status_delete = 0 LIMIT 1";
    $stmtLatent = $pdo->prepare($sqlCheckLatent);
    $stmtLatent->execute([$id]);

    if ($stmtVal->rowCount() > 0 || $stmtLatent->rowCount() > 0) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        
        http_response_code(400);
        echo json_encode([
            'status' => 'error', 
            'message' => 'ไม่สามารถลบรายการนี้ได้ เนื่องจากมีประวัติการนำสารเคมีนี้ไปใช้งานในโมดูลทดสอบหรือตรวจความพร้อมแล้ว'
        ]);
        exit;
    }

    // 4. ดึงรายการสารตั้งต้นทั้งหมด (Ingredients) ของ prep_id นี้ออกมาเพื่อคืนสต็อก
    $sqlGetIngredients = "SELECT inventory_id, amount_used FROM preparation_ingredients WHERE prep_id = ? AND status_delete = 0";
    $stmtGet = $pdo->prepare($sqlGetIngredients);
    $stmtGet->execute([$id]);
    $ingredients = $stmtGet->fetchAll(PDO::FETCH_ASSOC);

    // 5. วนลูปคืนสต็อกให้สารตั้งต้นทุกตัวในคลังหลัก
    foreach ($ingredients as $ing) {
        $sqlRestore = "UPDATE chemical_inventory SET quantity_remaining = quantity_remaining + ? WHERE id = ?";
        $pdo->prepare($sqlRestore)->execute([$ing['amount_used'], $ing['inventory_id']]);
    }

    // 6. ลบรายการสารตั้งต้นในตาราง preparation_ingredients (Soft Delete)
    $sqlDelIng = "UPDATE preparation_ingredients SET status_delete = id WHERE prep_id = ?";
    $pdo->prepare($sqlDelIng)->execute([$id]);

    // ลบรายการหลัก chemical_preparation (Soft Delete + เช็ค status_delete = 0 เพื่อป้องกันการลบซ้ำ)
    $sqlDel = "UPDATE chemical_preparation SET status_delete = id, update_by = ? WHERE id = ? AND status_delete = 0";
    $stmtDel = $pdo->prepare($sqlDel);
    $stmtDel->execute([$user_id, $id]);

    if ($stmtDel->rowCount() === 0) {
        throw new Exception("ไม่พบรายการที่ต้องการลบ หรือรายการนี้ถูกลบไปแล้ว");
    }
    
    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'ลบรายการเตรียมสารเคมีและคืนยอดสต็อกเรียบร้อยแล้ว']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
