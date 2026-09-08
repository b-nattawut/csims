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
$id               = $_POST['id'] ?? ''; 
$prep_id          = (int)$_POST['prep_id'] ?? ''; // ID สารที่เตรียมไว้
$amount_used      = (float)($_POST['amount_used'] ?? 0); 
$test_date        = $_POST['test_date'] ?? '';
$ph_value         = $_POST['ph_value'] ?? null;
$color_change_status = $_POST['color_change_status'] ?? null;
$readiness_status = $_POST['readiness_status'] ?? ''; // ready / not_ready
$remark           = $_POST['remark'] ?? '';           // หมายเหตุเพิ่มเติม
$tester_id        = (int)$_POST['tester_id'] ?? '';
$verifier_id      = (int)$_POST['verifier_id'] ?? '';
$deleted_photo_ids = $_POST['deleted_photo_ids'] ?? ''; // รับ JSON ID รูปที่จะลบ

$user_id = $_SESSION['user_id'];
$dateNow = date('Y-m-d H:i:s');

// 2. ตรวจสอบข้อมูลจำเป็นพื้นฐาน
if (empty($prep_id) || empty($test_date) || empty($readiness_status) || empty($tester_id) || empty($verifier_id)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุข้อมูลให้ครบถ้วน']);
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

// ตรวจสอบช่วงค่า pH
if ($ph_value !== null && ($ph_value < 0 || $ph_value > 14)) {
    echo json_encode(['status' => 'error', 'message' => 'ค่า pH ต้องอยู่ระหว่าง 0 ถึง 14']);
    exit;
}

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งาน (เพิ่มเช็ค is_active = 1 เพื่อความปลอดภัย)
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
        echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ทำรายการ: ไม่สามารถตรวจสอบสารเคมีของหน่วยงานอื่นได้']);
        exit;
    }

    if (!empty($id)) {
        // กรณีแก้ไข (UPDATE): ตรวจสอบสิทธิ์ของรายการเดิม ($id) ด้วยว่าอยู่ในหน่วยงานเราไหม
        $sqlCheckOwner = "SELECT (
                            SELECT mcl_sub.department_id 
                            FROM preparation_ingredients pi_sub
                            JOIN chemical_inventory inv_sub ON pi_sub.inventory_id = inv_sub.id
                            JOIN master_chemical_list mcl_sub ON inv_sub.chemical_id = mcl_sub.id
                            WHERE pi_sub.prep_id = t2.id AND pi_sub.status_delete = 0 
                            LIMIT 1
                          ) AS department_id
                          FROM trans_latent_chemical_test t1
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

    // --- ดึงข้อมูลสารที่เตรียมไว้เพื่อตรวจสอบสต็อกและวันที่ (Lock สำหรับ Update) ---
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

    // ปั้นชื่อหน่วยสำหรับแสดงใน Error
    $isPkg = (strpos($prepData['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($prepData['unit_group'], 'Packaging') !== false);
    $unitLabel = $isPkg ? $prepData['unit_name_en'] : $prepData['unit_symbol'];

    // [Validation] วันที่ตรวจสอบห้ามก่อนวันที่เตรียมสาร
    if ($test_date < $prepData['prep_date']) {
        $prep_fmt = date('d/m/Y', strtotime($prepData['prep_date']));
        throw new Exception("วันที่ตรวจความพร้อม ($test_date) ห้ามอยู่ก่อนวันที่เตรียมสาร ($prep_fmt)");
    }

    $latent_test_id = null;

    if (!empty($id)) {
        // -------------------------------------------------------------------------
        // กรณีแก้ไข (UPDATE)
        // -------------------------------------------------------------------------
        
        // ดึงข้อมูลเดิมมาเช็คสถานะ
        $latent_test_id = $id;
        $sqlOld = "SELECT amount_used, is_verified, prep_id FROM trans_latent_chemical_test WHERE id = ?";
        $stmtOld = $pdo->prepare($sqlOld);
        $stmtOld->execute([$id]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        if (!$oldData) throw new Exception("ไม่พบข้อมูลที่ต้องการแก้ไข");

        // ถ้าทวนสอบ (Verified) แล้ว ห้ามแก้ไข
        if ((int)$oldData['is_verified'] === 1) {
            throw new Exception("ไม่สามารถแก้ไขได้ เนื่องจากรายการนี้ได้รับการทวนสอบและลงนามแล้ว");
        }

        // ตรวจสอบห้ามเปลี่ยนขวดสารเคมี
        if ($oldData['prep_id'] != $prep_id) {
            throw new Exception("ไม่สามารถเปลี่ยนรายการสารที่ตรวจสอบได้ หากต้องการเปลี่ยนกรุณาลบแล้วสร้างใหม่");
        }

        // --- จัดการส่วนต่างสต็อก ---
        $diffAmount = $amount_used - (float)$oldData['amount_used'];
        $currentRemain = (float)$prepData['quantity_remaining'];

        if ($diffAmount > $currentRemain) {
            // ปรับ Error Message ให้โชว์หน่วยนับ
            throw new Exception("ปริมาณสารไม่เพียงพอ (ต้องการเพิ่มอีก " . number_format($diffAmount, 2) . " $unitLabel แต่เหลือในขวด " . number_format($currentRemain, 2) . " $unitLabel)");
        }

        // ปรับปรุงยอดในขวดเตรียม
        $sqlUpdatePrep = "UPDATE chemical_preparation SET quantity_remaining = quantity_remaining - ? WHERE id = ?";
        $pdo->prepare($sqlUpdatePrep)->execute([$diffAmount, $prep_id]);

        // อัปเดตข้อมูลรายการตรวจ
        $sql = "UPDATE trans_latent_chemical_test SET
                    test_date = ?, amount_used = ?, 
                    ph_value = ?, color_change_status = ?,
                    readiness_status = ?, remark = ?, 
                    tester_id = ?, verifier_id = ?, 
                    update_by = ?, update_date = ?
                WHERE id = ?";
        
        $pdo->prepare($sql)->execute([
            $test_date, $amount_used,
            $ph_value, $color_change_status,
            $readiness_status, $remark,
            $tester_id, $verifier_id,
            $user_id, $dateNow, $id
        ]);

    } else {
        // -------------------------------------------------------------------------
        // กรณีเพิ่มใหม่ (INSERT)
        // -------------------------------------------------------------------------

        // 1. ตรวจสอบสต็อก
        if ((float)$prepData['quantity_remaining'] < $amount_used) {
            throw new Exception("ปริมาณสารในขวดไม่เพียงพอสำหรับการตรวจความพร้อม (คงเหลือ: " . number_format($prepData['quantity_remaining'], 2) . " $unitLabel)");
        }

        // 2. หักสต็อก
        $sqlUpdateStock = "UPDATE chemical_preparation SET quantity_remaining = quantity_remaining - ? WHERE id = ?";
        $pdo->prepare($sqlUpdateStock)->execute([$amount_used, $prep_id]);

        // 3. บันทึกข้อมูลใหม่
        $sql = "INSERT INTO trans_latent_chemical_test (
                    prep_id, test_date, amount_used,
                    ph_value, color_change_status,
                    readiness_status, remark,
                    tester_id, verifier_id,
                    create_by, create_date, is_verified
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
        
        $pdo->prepare($sql)->execute([
            $prep_id, $test_date, $amount_used,
            $ph_value, $color_change_status,
            $readiness_status, $remark,
            $tester_id, $verifier_id,
            $user_id, $dateNow
        ]);
        $latent_test_id = $pdo->lastInsertId();
    }

    // =========================================================================
    // 3. จัดการรูปภาพ (Attachments)
    // =========================================================================

    // A. ลบรูปภาพที่ User สั่งลบ (Soft Delete)
    if (!empty($deleted_photo_ids)) {
        $delIds = json_decode($deleted_photo_ids, true);
        if (is_array($delIds)) {
            $sqlDel = "UPDATE trans_latent_chemical_attachment SET status_delete = id WHERE id = ? AND latent_test_id = ?";
            $stmtDel = $pdo->prepare($sqlDel);
            foreach ($delIds as $delId) {
                $stmtDel->execute([$delId, $latent_test_id]);
            }
        }
    }

    // B. บันทึกรูปภาพใหม่ (Binary BLOB)
    if (isset($_FILES['latent_photos'])) {
        $photos = $_FILES['latent_photos'];
        $sqlInsAttach = "INSERT INTO trans_latent_chemical_attachment (
                            latent_test_id, file_content, file_name, original_name, file_type, file_size, create_by
                         ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmtInsAttach = $pdo->prepare($sqlInsAttach);

        for ($i = 0; $i < count($photos['name']); $i++) {
            if ($photos['error'][$i] === 0 && $photos['size'][$i] > 0) {
                $file_ptr = fopen($photos['tmp_name'][$i], 'rb');
                $orig_name = $photos['name'][$i];
                $f_type = $photos['type'][$i];
                $f_size = $photos['size'][$i];

                $stmtInsAttach->bindParam(1, $latent_test_id);
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
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลการตรวจความพร้อมเรียบร้อยแล้ว', 'insert_id' => $latent_test_id]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}