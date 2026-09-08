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

// 1. รับค่าจากฟอร์ม
$id              = $_POST['id'] ?? '';             // ID ของล็อต (ถ้ามี = UPDATE)
$chemical_id     = $_POST['chemical_id'] ?? '';    // ID สารเคมีหลัก
$lot_number      = trim($_POST['lot_number'] ?? '');
$receive_date    = $_POST['receive_date'] ?? '';
$expiry_date     = $_POST['expiry_date'] ?? '';
$quantity        = (float)($_POST['quantity'] ?? 0);
$location_stored = trim($_POST['location_stored'] ?? '');
$remark          = trim($_POST['remark'] ?? '');

// ข้อมูล System
$user_id = $_SESSION['user_id'];
$dateNow = date('Y-m-d H:i:s');

// 2. ตรวจสอบข้อมูลที่จำเป็น
if (empty($chemical_id) || empty($lot_number) || empty($receive_date) || empty($expiry_date) || $quantity <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลที่มีเครื่องหมาย * ให้ครบถ้วน']);
    exit;
}

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role    = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Control)
    if ($user_role !== 'admin') {
        if (empty($id)) {
            // กรณี INSERT: เช็คว่าสารเคมีหลัก (chemical_id) เป็นของหน่วยงานตนเองหรือไม่
            $stmtCheckChem = $pdo->prepare("SELECT department_id FROM master_chemical_list WHERE id = ? AND status_delete = 0");
            $stmtCheckChem->execute([$chemical_id]);
            $chem_dept_id = $stmtCheckChem->fetchColumn();

            if ($chem_dept_id != $user_dept_id) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์บันทึกข้อมูล: รายการสารเคมีนี้อยู่คนละหน่วยงาน']);
                exit;
            }
        } else {
            // กรณี UPDATE: เช็คว่าล็อตเดิมนี้ เป็นของสารเคมีในหน่วยงานตนเองหรือไม่
            $stmtCheckLot = $pdo->prepare("
                SELECT m.department_id 
                FROM chemical_inventory i
                JOIN master_chemical_list m ON i.chemical_id = m.id
                WHERE i.id = ? AND i.status_delete = 0
            ");
            $stmtCheckLot->execute([$id]);
            $lot_dept_id = $stmtCheckLot->fetchColumn();

            if ($lot_dept_id != $user_dept_id) {
                echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์แก้ไขข้อมูล: ล็อตสารเคมีนี้เป็นของหน่วยงานอื่น']);
                exit;
            }
        }
    }

    $pdo->beginTransaction();

    // -------------------------------------------------------------------------
    // ตรวจสอบ Unique Check (สารตัวเดิม ล็อตเดิม ห้ามซ้ำ)
    // -------------------------------------------------------------------------
    $sqlCheck = "SELECT id FROM chemical_inventory 
                 WHERE chemical_id = ? AND lot_number = ? AND status_delete = 0";
    $paramsCheck = [$chemical_id, $lot_number];
    if (!empty($id)) {
        $sqlCheck .= " AND id != ?";
        $paramsCheck[] = $id;
    }
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute($paramsCheck);
    if ($stmtCheck->rowCount() > 0) {
        throw new Exception("เลขล็อต '$lot_number' ของสารเคมีนี้ มีอยู่ในระบบแล้ว");
    }

    if (empty($id)) {
        // -------------------------------------------------------------------------
        // กรณีเพิ่มใหม่ (INSERT)
        // -------------------------------------------------------------------------
        $sql = "INSERT INTO chemical_inventory (
                    chemical_id, lot_number, receive_date, expiry_date,
                    quantity, quantity_remaining,
                    location_stored, remark, create_by, create_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $pdo->prepare($sql)->execute([
            $chemical_id,
            $lot_number,
            $receive_date,
            $expiry_date,
            $quantity,
            $quantity, // ตั้งต้น quantity_remaining ให้เท่ากับ quantity
            $location_stored,
            $remark,
            $user_id,
            $dateNow
        ]);
    } else {
        // -------------------------------------------------------------------------
        // กรณีแก้ไข (UPDATE)
        // -------------------------------------------------------------------------
        // ดึงยอดเดิมมาคำนวณส่วนต่าง (Delta) เพื่อปรับปรุงยอดคงเหลือให้ถูกต้อง
        $sqlOld = "SELECT quantity, quantity_remaining FROM chemical_inventory WHERE id = ?";
        $stmtOld = $pdo->prepare($sqlOld);
        $stmtOld->execute([$id]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if (!$oldData) throw new Exception("ไม่พบข้อมูลที่ต้องการแก้ไข");

        $diff = $quantity - (float)$oldData['quantity'];
        $newRemaining = (float)$oldData['quantity_remaining'] + $diff;

        if ($newRemaining < 0) throw new Exception("ยอดคงเหลือจะติดลบ ไม่สามารถปรับลดปริมาณลงได้เกินกว่ายอดที่มีอยู่จริง");

        $sql = "UPDATE chemical_inventory SET
                    lot_number = ?, receive_date = ?, expiry_date = ?,
                    quantity = ?, quantity_remaining = ?, 
                    location_stored = ?, remark = ?, update_by = ?, update_date = ?
                WHERE id = ? AND status_delete = 0";

        $pdo->prepare($sql)->execute([
            $lot_number,
            $receive_date,
            $expiry_date,
            $quantity,
            $newRemaining,
            $location_stored,
            $remark,
            $user_id,
            $dateNow,
            $id
        ]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
