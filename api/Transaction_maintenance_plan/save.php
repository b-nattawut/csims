<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// เช็คว่า Login หรือยัง? (Security Check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน (Unauthorized)'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {

    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // 2. รับค่าจาก Form
    $id              = $_POST['id'] ?? ''; // ถ้าว่าง = เพิ่มใหม่, ถ้ามีค่า = แก้ไข
    $plan_year       = $_POST['plan_year'] ?? '';
    $group_name      = trim($_POST['group_name'] ?? '');
    $department_id   = $_POST['department_id'] ?? '';
    $details         = $_POST['details'] ?? []; // Array ของตารางเครื่องมือ

    // ควบคุมสิทธิ์การกำหนดหน่วยงาน
    if ($user_role !== 'admin') {
        // ถ้าไม่ใช่ Admin บังคับใช้ค่าหน่วยงานของตัวเองเสมอ ป้องกันการส่งค่าข้ามหน่วยงาน
        $department_id = $user_dept_id;
    }

    // Validate เบื้องต้น
    if (empty($plan_year) || empty($group_name) || empty($department_id)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลส่วนหัวให้ครบถ้วน']);
        exit;
    }

    // เริ่มต้น Transaction (เพื่อการันตีว่า Header และ Detail ต้องบันทึกผ่านทั้งคู่)
    $pdo->beginTransaction();

    if (empty($id)) {
        // ==========================================
        // โหมด: สร้างใหม่ (INSERT)
        // ==========================================

        // กรองข้อมูลซ้ำ (UNIQUE)
        $stmtCheck = $pdo->prepare("SELECT id FROM trans_maintenance_plan_header WHERE plan_year = ? AND group_name = ? AND department_id = ? AND delete_token = 0");
        $stmtCheck->execute([$plan_year, $group_name, $department_id]);
        if ($stmtCheck->fetch()) {
            throw new Exception("แผนของปี $plan_year สำหรับกลุ่มงานและหน่วยงานนี้ มีอยู่ในระบบแล้ว กรุณาไปแก้ไขแผนเดิม");
        }

        // บันทึกส่วนหัว
        $sqlHeader = "INSERT INTO trans_maintenance_plan_header 
                      (plan_year, group_name, department_id, create_by, create_date) 
                      VALUES (?, ?, ?, ?, NOW())";
        $stmtHeader = $pdo->prepare($sqlHeader);
        $stmtHeader->execute([$plan_year, $group_name, $department_id, $user_id]);

        // ดึง ID ที่เพิ่งสร้างใหม่มาใช้
        $header_id = $pdo->lastInsertId();
    } else {
        // ==========================================
        // โหมด: แก้ไข (UPDATE)
        // ==========================================

        // ตรวจสอบสิทธิ์การแก้ไขข้อมูลเดิม (กรณี User ทั่วไป ห้ามแก้ไขแผนของหน่วยงานอื่น)
        if ($user_role !== 'admin') {
            $stmtCheckOwner = $pdo->prepare("SELECT department_id FROM trans_maintenance_plan_header WHERE id = ? AND delete_token = 0");
            $stmtCheckOwner->execute([$id]);
            $owner_dept_id = $stmtCheckOwner->fetchColumn();

            if ($owner_dept_id != $user_dept_id) {
                throw new Exception("ไม่มีสิทธิ์แก้ไขแผนงาน: รายการนี้เป็นของหน่วยงานอื่น");
            }
        }

        // กรองข้อมูลซ้ำ (ยกเว้นตัวเอง)
        $stmtCheck = $pdo->prepare("SELECT id FROM trans_maintenance_plan_header WHERE plan_year = ? AND group_name = ? AND department_id = ? AND delete_token = 0 AND id != ?");
        $stmtCheck->execute([$plan_year, $group_name, $department_id, $id]);
        if ($stmtCheck->fetch()) {
            throw new Exception("ไม่สามารถแก้ไขได้ เนื่องจากแผนปี $plan_year ของกลุ่มงานนี้ซ้ำกับรายการอื่น");
        }

        // อัปเดตส่วนหัว
        $sqlHeader = "UPDATE trans_maintenance_plan_header 
                      SET plan_year = ?, group_name = ?, department_id = ?, update_by = ?, update_date = NOW() 
                      WHERE id = ?";
        $stmtHeader = $pdo->prepare($sqlHeader);
        $stmtHeader->execute([$plan_year, $group_name, $department_id, $user_id, $id]);

        $header_id = $id;

        // **เคล็ดลับ (Wipe & Replace):** ลบ Detail เดิมของแผนนี้ทิ้งทั้งหมด เพื่อเตรียมรับของใหม่แบบคลีนๆ
        $pdo->prepare("DELETE FROM trans_maintenance_plan_detail WHERE header_id = ?")->execute([$header_id]);
    }

    // ==========================================
    // บันทึกตารางรายการเครื่องมือ (Detail)
    // ==========================================
    if (!empty($details) && is_array($details)) {

        $sqlDetail = "INSERT INTO trans_maintenance_plan_detail 
                      (header_id, equipment_id, m1, m2, m3, m4, m5, m6, m7, m8, m9, m10, m11, m12, responsible_name, remark) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtDetail = $pdo->prepare($sqlDetail);

        foreach ($details as $row) {
            $eq_id = $row['equipment_id'] ?? 0;
            if ($eq_id == 0) continue;

            // ตรวจสอบความปลอดภัยเพิ่มเติม (สำหรับ User ทั่วไป): เช็คว่าเครื่องมือที่ส่งมาเป็นของหน่วยงานตัวเองจริงไหม
            if ($user_role !== 'admin') {
                $stmtCheckEq = $pdo->prepare("SELECT department_id FROM master_equipment_list WHERE id = ? AND status_delete = 0");
                $stmtCheckEq->execute([$eq_id]);
                $eq_dept_id = $stmtCheckEq->fetchColumn();

                if ($eq_dept_id != $user_dept_id) {
                    continue; // ข้ามรายการเครื่องมือที่เป็นของหน่วยงานอื่นทันที
                }
            }

            // ตรวจสอบค่า Checkbox (ถ้าไม่ได้ติ๊ก ค่าจะไม่ถูกส่งมาใน POST ต้องใช้ isset เช็คเอา)
            $m1 = isset($row['m1']) ? 1 : 0;
            $m2 = isset($row['m2']) ? 1 : 0;
            $m3 = isset($row['m3']) ? 1 : 0;
            $m4 = isset($row['m4']) ? 1 : 0;
            $m5 = isset($row['m5']) ? 1 : 0;
            $m6 = isset($row['m6']) ? 1 : 0;
            $m7 = isset($row['m7']) ? 1 : 0;
            $m8 = isset($row['m8']) ? 1 : 0;
            $m9 = isset($row['m9']) ? 1 : 0;
            $m10 = isset($row['m10']) ? 1 : 0;
            $m11 = isset($row['m11']) ? 1 : 0;
            $m12 = isset($row['m12']) ? 1 : 0;

            $resp_name = trim($row['responsible_name'] ?? '');
            $remark = trim($row['remark'] ?? '');

            // **ประหยัด Database:** บันทึกเฉพาะบรรทัดที่มีการติ๊กแผน หรือ มีการพิมพ์ข้อความ เท่านั้น
            if ($m1 || $m2 || $m3 || $m4 || $m5 || $m6 || $m7 || $m8 || $m9 || $m10 || $m11 || $m12 || $resp_name !== '' || $remark !== '') {

                $stmtDetail->execute([
                    $header_id,
                    $eq_id,
                    $m1,
                    $m2,
                    $m3,
                    $m4,
                    $m5,
                    $m6,
                    $m7,
                    $m8,
                    $m9,
                    $m10,
                    $m11,
                    $m12,
                    $resp_name,
                    $remark
                ]);
            }
        }
    }

    // เมื่อคำสั่งทั้งหมดผ่านฉลุย ให้ทำการยื่นยันการเซฟ (Commit)
    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อย']);
} catch (Exception $e) {
    // ถ้ามีส่วนไหนพัง (แม้แต่จุดเดียว) ให้ Rollback ถอยหลังกลับไปเหมือนไม่เคยทำอะไรเลย
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
