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
$id             = $_POST['id'] ?? '';
$prep_id        = (int)$_POST['prep_id'] ?? ''; // ID สารที่เตรียมไว้ (จากตาราง chemical_preparation)
$amount_used    = (float)($_POST['amount_used'] ?? 0); // ปริมาณที่ดึงมาทดสอบ
$test_date      = $_POST['test_date'] ?? '';
$res_acid_base  = $_POST['res_acid_base'] ?? ''; // pass / fail
$res_blood      = $_POST['res_blood'] ?? '';     // pass / fail
$tester_id      = (int)$_POST['tester_id'] ?? '';
$verifier_id    = (int)$_POST['verifier_id'] ?? '';
$deleted_photo_ids = $_POST['deleted_photo_ids'] ?? ''; // รับ JSON ID รูปที่จะลบ

$user_id = $_SESSION['user_id'];
$dateNow = date('Y-m-d H:i:s');

// 2. ตรวจสอบข้อมูลจำเป็นพื้นฐาน
if (empty($prep_id) || empty($test_date) || empty($res_acid_base) || empty($res_blood) || empty($tester_id) || empty($verifier_id)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุข้อมูลให้ครบถ้วน (รวมถึงผลการทดสอบทั้ง 2 รายการ)']);
    exit;
}

// ตรวจสอบปริมาณสาร (ต้องมากกว่า 0)
if ($amount_used <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุปริมาณสารที่ดึงมาทดสอบให้ถูกต้อง']);
    exit;
}

// ตรวจสอบ: ผู้ทดสอบและผู้ทวนสอบต้องไม่เป็นคนเดียวกัน
if ($tester_id == $verifier_id) {
    echo json_encode(['status' => 'error', 'message' => 'ผู้ทวนสอบและผู้ทดสอบต้องเป็นคนละคนกัน']);
    exit;
}

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งาน (เพิ่มเช็ค is_active = 1)
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

    // หาหน่วยงานของสารที่เตรียมไว้ (prep_id)
    $sqlPrepDept = "SELECT (
                    SELECT mcl_sub.department_id 
                    FROM preparation_ingredients pi_sub
                    JOIN chemical_inventory inv_sub ON pi_sub.inventory_id = inv_sub.id
                    JOIN master_chemical_list mcl_sub ON inv_sub.chemical_id = mcl_sub.id
                    WHERE pi_sub.prep_id = t1.id AND pi_sub.status_delete = 0 
                    LIMIT 1
                ) AS department_id
                FROM chemical_preparation t1
                WHERE t1.id = ?";
    $stmtPrepDept = $pdo->prepare($sqlPrepDept);
    $stmtPrepDept->execute([$prep_id]);
    $target_dept_id = $stmtPrepDept->fetchColumn();

    // ถ้าไม่ใช่ Admin และหน่วยงานของสารที่เลือกไม่ตรงกับหน่วยงานผู้ใช้ ห้ามทำรายการ (HTTP 403)
    if ($user_role !== 'admin' && $target_dept_id != $user_dept_id) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ทำรายการ: ไม่สามารถทดสอบสารเคมีของหน่วยงานอื่นได้']);
        exit;
    }

    if (!empty($id)) {
        // กรณีแก้ไข (UPDATE): ควรเช็คสิทธิ์ของรายการเดิม ($id) ด้วยว่าอยู่ในหน่วยงานเราไหม
        $sqlCheckOwner = "SELECT (
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
        $stmtOwner = $pdo->prepare($sqlCheckOwner);
        $stmtOwner->execute([$id]);
        $record_dept_id = $stmtOwner->fetchColumn();

        if ($user_role !== 'admin' && $record_dept_id != $user_dept_id) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ทำรายการ: ไม่สามารถแก้ไขข้อมูลของหน่วยงานอื่นได้']);
            exit;
        }
    }

    $pdo->beginTransaction();

    $insert_id = null;

    // --- ดึงข้อมูลสารที่เตรียมไว้เพื่อตรวจสอบสต็อกและวันที่ ---
    $sqlPrep = "SELECT t1.quantity_remaining, t1.prep_date, t2.unit_symbol, t2.unit_name_en, t2.unit_group
                FROM chemical_preparation t1 
                LEFT JOIN master_chemical_units t2 ON t1.unit_id = t2.id
                WHERE t1.id = ? AND t1.status_delete = 0 FOR UPDATE";
    $stmtPrep = $pdo->prepare($sqlPrep);
    $stmtPrep->execute([$prep_id]);
    $prepData = $stmtPrep->fetch(PDO::FETCH_ASSOC);

    if (!$prepData) {
        throw new Exception("ไม่พบข้อมูลสารเคมีที่เตรียมไว้ในระบบ");
    }

    // จัดการชื่อหน่วยสำหรับโชว์ใน Error
    $isPkg = (strpos($prepData['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($prepData['unit_group'], 'Packaging') !== false);
    $unitLabel = $isPkg ? $prepData['unit_name_en'] : $prepData['unit_symbol'];

    // [Validation] วันที่ทดสอบห้ามก่อนวันที่เตรียมสาร
    if ($test_date < $prepData['prep_date']) {
        $prep_fmt = date('d/m/Y', strtotime($prepData['prep_date']));
        throw new Exception("วันที่ทดสอบ ($test_date) ห้ามอยู่ก่อนวันที่เตรียมสาร ($prep_fmt)");
    }

    if (!empty($id)) {
        // -------------------------------------------------------------------------
        // กรณีแก้ไข (UPDATE)
        // -------------------------------------------------------------------------

        // ดึงข้อมูลเดิมมาเช็คสถานะการทวนสอบ
        $sqlOld = "SELECT amount_used, is_verified, prep_id FROM trans_chemical_validation WHERE id = ?";
        $stmtOld = $pdo->prepare($sqlOld);
        $stmtOld->execute([$id]);
        $oldValData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if (!$oldValData) throw new Exception("ไม่พบข้อมูลการทดสอบที่ต้องการแก้ไข");

        // ถ้าทวนสอบ (Verified) แล้ว ห้ามแก้ไขเด็ดขาด
        if ((int)$oldValData['is_verified'] === 1) {
            throw new Exception("ไม่สามารถแก้ไขได้ เนื่องจากรายการนี้ได้รับการทวนสอบและลงนามแล้ว");
        }

        // ตรวจสอบว่าห้ามเปลี่ยนขวดสารเคมี
        if ($oldValData['prep_id'] != $prep_id) {
            throw new Exception("ไม่สามารถเปลี่ยนรายการสารที่ทดสอบได้ หากต้องการเปลี่ยนกรุณาลบแล้วสร้างใหม่");
        }

        // --- จัดการส่วนต่างสต็อกของขวดสารที่เตรียมไว้ ---
        $diffAmount = $amount_used - (float)$oldValData['amount_used'];
        $currentRemain = (float)$prepData['quantity_remaining'];

        if ($diffAmount > $currentRemain) {
            throw new Exception("ปริมาณสารไม่เพียงพอ (ขาดอีก " . number_format($diffAmount - $currentRemain, 2) . " $unitLabel)");
        }

        // ปรับปรุงยอดคงเหลือในตาราง preparation
        $sqlUpdatePrep = "UPDATE chemical_preparation SET quantity_remaining = quantity_remaining - ? WHERE id = ?";
        $pdo->prepare($sqlUpdatePrep)->execute([$diffAmount, $prep_id]);

        $insert_id = $id; // ถ้าเป็นแก้ไข ให้ใช้ ID เดิม

        // อัปเดตข้อมูล Validation
        $sql = "UPDATE trans_chemical_validation SET
                    test_date = ?, amount_used = ?, 
                    res_acid_base = ?, res_blood = ?, 
                    tester_id = ?, verifier_id = ?, 
                    update_by = ?, update_date = ?
                WHERE id = ?";

        $pdo->prepare($sql)->execute([
            $test_date,
            $amount_used,
            $res_acid_base,
            $res_blood,
            $tester_id,
            $verifier_id,
            $user_id,
            $dateNow,
            $id
        ]);
    } else {
        // -------------------------------------------------------------------------
        // กรณีเพิ่มใหม่ (INSERT)
        // -------------------------------------------------------------------------

        // 1. ตรวจสอบสต็อกในขวดว่าพอให้ดึงมาทดสอบไหม
        if ((float)$prepData['quantity_remaining'] < $amount_used) {
            throw new Exception("ปริมาณสารไม่เพียงพอสำหรับการทดสอบ (คงเหลือ: " . number_format($prepData['quantity_remaining'], 2) . " $unitLabel)");
        }

        // 2. หักสต็อกออกจากขวดที่เตรียมไว้ทันที
        $sqlUpdateStock = "UPDATE chemical_preparation SET quantity_remaining = quantity_remaining - ? WHERE id = ?";
        $pdo->prepare($sqlUpdateStock)->execute([$amount_used, $prep_id]);

        // 3. บันทึกข้อมูลการทดสอบใหม่
        $sql = "INSERT INTO trans_chemical_validation (
                    prep_id, test_date, amount_used,
                    res_acid_base, res_blood,
                    tester_id, verifier_id,
                    create_by, create_date, is_verified
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";

        $pdo->prepare($sql)->execute([
            $prep_id,
            $test_date,
            $amount_used,
            $res_acid_base,
            $res_blood,
            $tester_id,
            $verifier_id,
            $user_id,
            $dateNow
        ]);

        $insert_id = $pdo->lastInsertId();
    }

    // =========================================================================
    // 3. จัดการรูปภาพ (Attachments)
    // =========================================================================

    // A. ลบรูปภาพที่ User สั่งลบ (Soft Delete)
    if (!empty($deleted_photo_ids)) {
        $delIds = json_decode($deleted_photo_ids, true);
        if (is_array($delIds)) {
            $sqlDel = "UPDATE trans_chemical_validation_attachment SET status_delete = id WHERE id = ? AND val_test_id = ?";
            $stmtDel = $pdo->prepare($sqlDel);
            foreach ($delIds as $delId) {
                $stmtDel->execute([$delId, $insert_id]);
            }
        }
    }

    // B. บันทึกรูปภาพใหม่ (Binary BLOB)
    if (isset($_FILES['validate_photos'])) {
        $photos = $_FILES['validate_photos'];
        $sqlInsAttach = "INSERT INTO trans_chemical_validation_attachment (
                            val_test_id, file_content, file_name, original_name, file_type, file_size, create_by
                         ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmtInsAttach = $pdo->prepare($sqlInsAttach);

        for ($i = 0; $i < count($photos['name']); $i++) {
            if ($photos['error'][$i] === 0 && $photos['size'][$i] > 0) {
                $file_ptr = fopen($photos['tmp_name'][$i], 'rb');
                $orig_name = $photos['name'][$i];
                $f_type = $photos['type'][$i];
                $f_size = $photos['size'][$i];

                $stmtInsAttach->bindParam(1, $insert_id);
                $stmtInsAttach->bindParam(2, $file_ptr, PDO::PARAM_LOB);
                $stmtInsAttach->bindParam(3, $orig_name); // ใช้ชื่อเดิมเป็น file_name ไปก่อน
                $stmtInsAttach->bindParam(4, $orig_name);
                $stmtInsAttach->bindParam(5, $f_type);
                $stmtInsAttach->bindParam(6, $f_size);
                $stmtInsAttach->bindParam(7, $user_id);

                $stmtInsAttach->execute();
                if (is_resource($file_ptr)) fclose($file_ptr);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกผลการทดสอบและปรับปรุงยอดสารเรียบร้อยแล้ว', 'insert_id' => $insert_id]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
