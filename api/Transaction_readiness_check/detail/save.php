<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบสิทธิ์ (Security Check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

$current_user_id = $_SESSION['user_id'];

try {
    // 2. รับค่าพื้นฐาน
    $header_id = $_POST['header_id'] ?? null;
    $category  = $_POST['category'] ?? null; // VEHICLE, BAG, TOOLS, CAMERA

    if (!$header_id || !$category) {
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน (Missing Required Parameters)']);
        exit;
    }

    // 3. รับข้อมูลผลตรวจรายข้อ (เป็น Array โดยมี Key คือ item_id)
    $results     = $_POST['result'] ?? [];     // [item_id => score]
    $corrections = $_POST['correction'] ?? []; // [item_id => text]
    $remarks     = $_POST['remark'] ?? [];     // [item_id => text]

    // 4. ตรวจสอบสถานะ Header (ห้ามแก้ไขถ้าจบงานแล้ว)
    $stmtStatus = $pdo->prepare("SELECT status FROM trans_readiness_check_header WHERE id = ?");
    $stmtStatus->execute([$header_id]);
    $header = $stmtStatus->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลรายงาน']);
        exit;
    }

    if ($header['status'] === 'COMPLETED') {
        echo json_encode(['status' => 'error', 'message' => 'รายงานนี้ตรวจสอบเสร็จสิ้นแล้ว ไม่สามารถแก้ไขได้']);
        exit;
    }

    // 5. เริ่มกระบวนการบันทึกข้อมูล (Transaction)
    $pdo->beginTransaction();

    // --- ส่วนที่ 1: บันทึกลงตารางสรุป (trans_readiness_check_results) ---
    // เช็คก่อนว่าหมวดนี้เคยตรวจไปหรือยังใน Header นี้
    $stmtCheck = $pdo->prepare("SELECT id FROM trans_readiness_check_results WHERE header_id = ? AND category = ?");
    $stmtCheck->execute([$header_id, $category]);
    $existingResult = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existingResult) {
        $result_id = $existingResult['id'];
        // อัปเดตเวลาการแก้ไขล่าสุด
        $stmtUpdateRes = $pdo->prepare("UPDATE trans_readiness_check_results SET updated_at = NOW() WHERE id = ?");
        $stmtUpdateRes->execute([$result_id]);

        // ล้างข้อมูล Detail เก่าออกเพื่อเตรียม Insert ใหม่ (Clean & Insert Strategy)
        $stmtDel = $pdo->prepare("DELETE FROM trans_readiness_check_details WHERE result_id = ?");
        $stmtDel->execute([$result_id]);
    } else {
        // Insert ใหม่
        $stmtInsertRes = $pdo->prepare("INSERT INTO trans_readiness_check_results (header_id, category, created_by, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $stmtInsertRes->execute([$header_id, $category, $current_user_id]);
        $result_id = $pdo->lastInsertId();
    }

    // --- ส่วนที่ 2: บันทึกรายละเอียดรายข้อ (trans_readiness_check_details) ---
    $sqlDetail = "INSERT INTO trans_readiness_check_details (result_id, item_id, score, correction, remark) VALUES (?, ?, ?, ?, ?)";
    $stmtDetail = $pdo->prepare($sqlDetail);

    foreach ($results as $item_id => $score_val) {
        $correction_val = $corrections[$item_id] ?? '';
        $remark_val     = $remarks[$item_id] ?? '';

        $stmtDetail->execute([
            $result_id,
            $item_id,
            $score_val,
            $correction_val,
            $remark_val
        ]);
    }

    $pdo->commit();

    // 6. ส่งผลลัพธ์กลับเพื่ออัปเดตหน้า UI
    echo json_encode([
        'status'      => 'success',
        'message'     => 'บันทึกรายการตรวจสอบเรียบร้อยแล้ว',
        'category'    => $category,
        'update_time' => date('d/m/Y H:i')
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()]);
}