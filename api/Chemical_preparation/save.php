<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

// 1. รับค่าจาก POST
$id              = $_POST['id'] ?? '';
$prep_name       = trim($_POST['prep_name'] ?? '');
$prep_date       = $_POST['prep_date'] ?? '';
$exp_date        = $_POST['exp_date'] ?? '';
$prep_quantity   = (float)($_POST['prep_quantity'] ?? 0);
$unit_id         = (int)$_POST['unit_id'] ?? '';
$preparer_id     = (int)$_POST['preparer_id'] ?? '';
$location_stored = trim($_POST['location_stored'] ?? '');
$remark          = trim($_POST['remark'] ?? '');

// รับค่า Array ของ Ingredients จากหน้าฟอร์ม
$ingredients     = $_POST['ingredients'] ?? [];

$user_id = $_SESSION['user_id'];
$dateNow = date('Y-m-d H:i:s');

// ดึงข้อมูล Role และ Department ของผู้ใช้งาน (เพิ่ม is_active = 1 เพื่อความปลอดภัย)
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

// ตรวจสอบข้อมูลจำเป็นพื้นฐานของรายการหลัก
if (empty($prep_name) || empty($prep_date) || empty($exp_date) || $prep_quantity <= 0 || empty($unit_id) || empty($preparer_id)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุข้อมูลหลักของการเตรียมสารให้ครบถ้วน']);
    exit;
}

// ตรวจสอบว่ามีสารตั้งต้นส่งมาอย่างน้อย 1 รายการหรือไม่
if (empty($ingredients) || !is_array($ingredients)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุสารตั้งต้นที่นำมาผสมอย่างน้อย 1 รายการ']);
    exit;
}

// กฎข้อที่ 3: วันหมดอายุห้ามอยู่ก่อนวันที่เตรียม (เช็คเบื้องต้น)
if ($exp_date <= $prep_date) {
    echo json_encode(['status' => 'error', 'message' => 'วันหมดอายุต้องอยู่หลังจากวันที่เตรียมสาร']);
    exit;
}

try {
    $pdo->beginTransaction();

    // --- ตรวจสอบว่า ID หน่วยนับมีอยู่จริงหรือไม่ ---
    $stmtCheckUnit = $pdo->prepare("SELECT id FROM master_chemical_units WHERE id = ?");
    $stmtCheckUnit->execute([$unit_id]);
    if ($stmtCheckUnit->rowCount() === 0) {
        throw new Exception("รหัสหน่วยนับของสารที่เตรียมไม่ถูกต้อง");
    }

    // ตัวแปรสำหรับคอยจับสถิติของสารตั้งต้นทั้งหมดมาคำนวณกฎแล็บ
    $min_source_expiry = null;
    $max_source_receive = null;

    // ตัวแปรสำหรับจับคู่ว่าสารทุกตัวในสูตรมาจากหน่วยงานเดียวกันหรือไม่
    $target_department_id = null;

    // -------------------------------------------------------------------------
    // ขั้นตอนพิเศษ: หากเป็นโหมดแก้ไข (UPDATE) ให้ทำการ "คืนสต็อกเก่า" ก่อนคำนวณใหม่
    // -------------------------------------------------------------------------
    if (!empty($id)) {

        // ตรวจสอบสิทธิ์การแก้ไข: ป้องกันการแก้ไขรายการเตรียมสารของหน่วยงานอื่น
        if ($user_role !== 'admin') {
            $sqlCheckDept = "SELECT m.department_id 
                             FROM preparation_ingredients pi
                             JOIN chemical_inventory i ON pi.inventory_id = i.id
                             JOIN master_chemical_list m ON i.chemical_id = m.id
                             WHERE pi.prep_id = ? AND pi.status_delete = 0 LIMIT 1";
            $stmtCheckDept = $pdo->prepare($sqlCheckDept);
            $stmtCheckDept->execute([$id]);
            $prep_dept_id = $stmtCheckDept->fetchColumn();

            if ($prep_dept_id && $prep_dept_id != $user_dept_id) {
                throw new Exception("ไม่มีสิทธิ์แก้ไขข้อมูล: รายการเตรียมสารเคมีนี้เป็นของหน่วยงานอื่น");
            }
        }

        // ดึงรายการสารตั้งต้นชุดเดิมออกมาเพื่อคืนสต็อกกลับเข้าคลังชั่วคราว
        $sqlOldIng = "SELECT inventory_id, amount_used FROM preparation_ingredients WHERE prep_id = ? AND status_delete = 0";
        $stmtOldIng = $pdo->prepare($sqlOldIng);
        $stmtOldIng->execute([$id]);
        $oldIngredients = $stmtOldIng->fetchAll(PDO::FETCH_ASSOC);

        foreach ($oldIngredients as $oldIng) {
            $sqlRevert = "UPDATE chemical_inventory SET quantity_remaining = quantity_remaining + ? WHERE id = ?";
            $pdo->prepare($sqlRevert)->execute([$oldIng['amount_used'], $oldIng['inventory_id']]);
        }

        // สั่ง Soft Delete รายการย่อยเดิมทิ้งทั้งหมด เพื่อเตรียมลงชุดใหม่แทนที่
        $sqlPurge = "UPDATE preparation_ingredients SET status_delete = id WHERE prep_id = ?";
        $pdo->prepare($sqlPurge)->execute([$id]);
    }

    // -------------------------------------------------------------------------
    // ลูปที่ 1: ตรวจสอบสต็อกและ Validation กฎของแล็บทีละตัวสารตั้งต้น (FOR UPDATE)
    // -------------------------------------------------------------------------
    $validatedIngredients = [];
    foreach ($ingredients as $index => $item) {
        $inv_id = (int)($item['inventory_id'] ?? 0);
        $amt    = (float)($item['amount_used'] ?? 0);
        $u_id   = (int)($item['unit_id'] ?? 0); // หน่วยนับของสารตั้งต้นตัวนั้นๆ

        if ($inv_id <= 0 || $amt <= 0 || $u_id <= 0) {
            throw new Exception("ข้อมูลสารตั้งต้นรายการที่ " . ($index + 1) . " ระบุไม่ครบถ้วน หรือปริมาณไม่ถูกต้อง");
        }

        // Lock record คลังสินค้าของสารตั้งต้นตัวนั้นๆ ไว้คำนวณป้องกัน Race Condition
        $sqlStock = "SELECT inv.quantity_remaining, inv.expiry_date, inv.receive_date, inv.lot_number, mcl.department_id 
                     FROM chemical_inventory inv
                     JOIN master_chemical_list mcl ON inv.chemical_id = mcl.id
                     WHERE inv.id = ? AND inv.status_delete = 0 FOR UPDATE";
        $stmtStock = $pdo->prepare($sqlStock);
        $stmtStock->execute([$inv_id]);
        $stockData = $stmtStock->fetch(PDO::FETCH_ASSOC);

        if (!$stockData) {
            throw new Exception("ไม่พบข้อมูลล็อตสารเคมีต้นทางรายการที่ " . ($index + 1) . " ในคลังสินค้า");
        }

        // ห้าม User ธรรมดาเบิกสารข้ามหน่วยงาน (ใส่ให้ชัดเจนขึ้น)
        if ($user_role !== 'admin' && $stockData['department_id'] != $user_dept_id) {
            throw new Exception("ไม่มีสิทธิ์ใช้งานสารตั้งต้น (Lot: {$stockData['lot_number']}) เนื่องจากอยู่ต่างหน่วยงาน");
        }

        // สารตั้งต้นทุกรายการในสูตรนี้ "ต้องมาจากหน่วยงานเดียวกัน"
        if ($target_department_id === null) {
            $target_department_id = $stockData['department_id']; // กำหนดหน่วยงานอ้างอิงจากสารตัวแรก
        } elseif ($target_department_id != $stockData['department_id']) {
            throw new Exception("ไม่อนุญาตให้นำสารตั้งต้นจากต่างหน่วยงานมาผสมกันในสูตรเดียว (ตรวจสอบ Lot: {$stockData['lot_number']})");
        }

        $currentStock = (float)$stockData['quantity_remaining'];
        if ($currentStock < $amt) {
            throw new Exception("ยอดคงเหลือของ Lot '{$stockData['lot_number']}' ไม่เพียงพอ (คงเหลือ {$currentStock} แต่พยายามเบิก {$amt})");
        }

        // เก็บสถิติเพื่อไปเทียบกฎหลักภายนอกลูป
        if ($min_source_expiry === null || $stockData['expiry_date'] < $min_source_expiry) {
            $min_source_expiry = $stockData['expiry_date'];
        }
        if ($max_source_receive === null || $stockData['receive_date'] > $max_source_receive) {
            $max_source_receive = $stockData['receive_date'];
        }

        // พักข้อมูลที่ผ่านการตรวจสอบไว้สำหรับใช้ลูปบันทึกจริงถัดไป
        $validatedIngredients[] = [
            'inventory_id' => $inv_id,
            'amount_used' => $amt,
            'unit_id' => $u_id
        ];
    }

    // -------------------------------------------------------------------------
    // ตรวจสอบกฎเหล็กนิติวิทยาศาสตร์ (Business Rules Validation)
    // -------------------------------------------------------------------------
    // กฎข้อที่ 1: วันที่เตรียมห้ามอยู่ก่อนวันที่รับสารเข้าคลัง (เทียบกับตัวที่รับเข้ามาล่าสุดในกลุ่ม)
    if ($prep_date < $max_source_receive) {
        $receive_fmt = date('d/m/Y', strtotime($max_source_receive));
        throw new Exception("วันที่เตรียม ($prep_date) ห้ามอยู่ก่อนวันที่รับสารตั้งต้นเข้าคลังล่าสุด ($receive_fmt)");
    }

    // กฎข้อที่ 2: วันหมดอายุของน้ำยาห้ามเกินกว่าวันหมดอายุของสารตั้งต้น (เทียบกับตัวที่จะหมดอายุก่อนเพื่อนในกลุ่ม)
    if ($exp_date > $min_source_expiry) {
        $source_exp_fmt = date('d/m/Y', strtotime($min_source_expiry));
        throw new Exception("วันหมดอายุของน้ำยาผสม ห้ามเกินวันหมดอายุของสารตั้งต้นกลุ่มเสี่ยงที่จะหมดอายุก่อน ($source_exp_fmt)");
    }

    // -------------------------------------------------------------------------
    // บันทึกข้อมูลรายการหลัก (HEADER) ลงตาราง `chemical_preparation`
    // -------------------------------------------------------------------------
    $prep_id = null;

    if (!empty($id)) {
        // -------------------------------------------------------------------------
        // กรณีแก้ไข (UPDATE)
        // -------------------------------------------------------------------------

        // 1. [กฎเหล็กนิติวิทยาศาสตร์] ตรวจสอบก่อนว่าน้ำยาขวดนี้ถูกนำไปใช้งานในคดีหรือยัง
        // ตรวจสอบฝั่ง Chemical Validation
        $sqlCheckUsedVal = "SELECT id FROM trans_chemical_validation WHERE prep_id = ? AND status_delete = 0 LIMIT 1";
        $stmtCheckVal = $pdo->prepare($sqlCheckUsedVal);
        $stmtCheckVal->execute([$id]);

        // ตรวจสอบฝั่ง Latent Chemical Test
        $sqlCheckUsedLatent = "SELECT id FROM trans_latent_chemical_test WHERE prep_id = ? AND status_delete = 0 LIMIT 1";
        $stmtCheckLatent = $pdo->prepare($sqlCheckUsedLatent);
        $stmtCheckLatent->execute([$id]);

        // หากพบว่ามีการใช้งานในตารางใดตารางหนึ่ง ให้ดีดออกทันที
        if ($stmtCheckVal->rowCount() > 0 || $stmtCheckLatent->rowCount() > 0) {
            throw new Exception("ไม่สามารถแก้ไขรายการนี้ได้ เนื่องจากน้ำยาขวดนี้ถูกนำไปใช้งานในการทดสอบหรือตรวจความพร้อมแล้ว");
        }

        // 2. ดึงข้อมูลการเตรียมเดิมขึ้นมาตรวจสอบและคำนวณ Yield ต่อ
        $sqlOldPrep = "SELECT prep_quantity, quantity_remaining, unit_id FROM chemical_preparation WHERE id = ?";
        $stmtOldPrep = $pdo->prepare($sqlOldPrep);
        $stmtOldPrep->execute([$id]);
        $oldPrepData = $stmtOldPrep->fetch(PDO::FETCH_ASSOC);

        if (!$oldPrepData) throw new Exception("ไม่พบข้อมูลการเตรียมที่ต้องการแก้ไข");

        // กฎล็อคหน่วยนับ
        if ((int)$oldPrepData['unit_id'] !== $unit_id && (float)$oldPrepData['quantity_remaining'] < (float)$oldPrepData['prep_quantity']) {
            throw new Exception("ไม่สามารถเปลี่ยนหน่วยนับได้ เนื่องจากน้ำยานี้ถูกนำไปใช้งานต่อแล้ว");
        }

        // คำนวณหายอดคงเหลือหลังจากการปรับ Yield ใหม่
        $diffPrep = $prep_quantity - (float)$oldPrepData['prep_quantity'];
        $newPrepRemaining = (float)$oldPrepData['quantity_remaining'] + $diffPrep;

        if ($newPrepRemaining < 0) {
            throw new Exception("ปริมาณคงเหลือของน้ำยาผสมไม่เพียงพอต่อการปรับลด (น้ำยาขวดนี้อาจถูกนำไปแบ่งใช้ทดสอบแล้ว)");
        }

        $sqlParent = "UPDATE chemical_preparation SET
                        prep_name = ?, prep_date = ?, expiry_date = ?,
                        prep_quantity = ?, quantity_remaining = ?, 
                        unit_id = ?, preparer_id = ?, location_stored = ?, remark = ?, 
                        update_by = ?, update_date = ?
                      WHERE id = ?";
        $pdo->prepare($sqlParent)->execute([
            $prep_name,
            $prep_date,
            $exp_date,
            $prep_quantity,
            $newPrepRemaining,
            $unit_id,
            $preparer_id,
            $location_stored,
            $remark,
            $user_id,
            $dateNow,
            $id
        ]);
        $prep_id = $id;
    } else {
        // -------------------------------------------------------------------------
        // กรณีเพิ่มใหม่ (INSERT)
        // -------------------------------------------------------------------------

        // --- โหมดเพิ่มใหม่ (INSERT) ---
        $sqlParent = "INSERT INTO chemical_preparation (
                        prep_name, prep_date, expiry_date,
                        prep_quantity, quantity_remaining, 
                        unit_id, preparer_id, location_stored, remark, 
                        create_by, create_date
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sqlParent)->execute([
            $prep_name,
            $prep_date,
            $exp_date,
            $prep_quantity,
            $prep_quantity, // ยอดคงเหลือตั้งต้นเท่ากับยอดที่เตรียมได้
            $unit_id,
            $preparer_id,
            $location_stored,
            $remark,
            $user_id,
            $dateNow
        ]);
        $prep_id = $pdo->lastInsertId();
    }
    // -------------------------------------------------------------------------
    // ลูปที่ 2: ดำเนินการตัดสต็อกคลังจริง และ INSERT ลงตาราง `preparation_ingredients`
    // -------------------------------------------------------------------------
    $sqlInsIngredient = "INSERT INTO preparation_ingredients (prep_id, inventory_id, amount_used, unit_id) VALUES (?, ?, ?, ?)";
    $stmtInsIngredient = $pdo->prepare($sqlInsIngredient);

    $sqlDeductStock = "UPDATE chemical_inventory SET quantity_remaining = quantity_remaining - ? WHERE id = ?";
    $stmtDeductStock = $pdo->prepare($sqlDeductStock);

    foreach ($validatedIngredients as $ing) {
        // 1. ตัดสต็อกคลังหลักของสารเคมีตัวนั้นๆ
        $stmtDeductStock->execute([$ing['amount_used'], $ing['inventory_id']]);

        // 2. บันทึกประวัติสูตรสารตั้งต้นลงตารางย่อย
        $stmtInsIngredient->execute([$prep_id, $ing['inventory_id'], $ing['amount_used'], $ing['unit_id']]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลสูตรผสมสารเคมีและปรับปรุงยอดคลังเรียบร้อยแล้ว', 'insert_id' => $prep_id]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
