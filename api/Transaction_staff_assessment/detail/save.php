<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// ตรวจสอบ Session (เหมือนไฟล์ Header)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

// ตรวจสอบ Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$current_user_id = $_SESSION['user_id'];

try {
    // รับค่าและ Validate ข้อมูลเบื้องต้น
    $header_id = $_POST['header_id'] ?? null;
    $user_id   = $_POST['user_id'] ?? null;
    $role_type = $_POST['role_type'] ?? null;

    // ตรวจสอบข้อมูลจำเป็น
    if (!$header_id || !$user_id || !$role_type) {
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน (Missing Required Parameters)']);
        exit;
    }

    // รับข้อมูลคะแนน (ถ้าไม่ส่งมา ให้เป็น Array ว่าง)
    $total_score = $_POST['total_score'] ?? 0;
    $max_score   = $_POST['max_score'] ?? 0;
    $percentage  = $_POST['percentage'] ?? 0;
    $grade       = $_POST['grade'] ?? 'รอการประเมิน';

    $scores   = $_POST['score'] ?? [];
    $comments = $_POST['comment'] ?? [];
    $remarks  = $_POST['remark'] ?? []; // ค่าจาก hidden field หรือ textarea


    // 1. ดึงสถานะปัจจุบันของ Header มาเช็คก่อน
    $sqlStatus = "SELECT status, created_by FROM staff_assessment_header WHERE id = ?";
    $stmtStatus = $pdo->prepare($sqlStatus);
    $stmtStatus->execute([$header_id]);
    $headerInfo = $stmtStatus->fetch(PDO::FETCH_ASSOC);

    if (!$headerInfo) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลรายงาน']);
        exit;
    }

    // 2. ถ้าสถานะเป็น COMPLETED คือเซ็นยืนยันเรียบร้อยแล้ว ห้ามแก้ไขคะแนน
    if ($headerInfo['status'] === 'COMPLETED') {
        // ถ้ารายงานจบแล้ว ไม่ให้แก้คะแนนดิบ
        echo json_encode(['status' => 'error', 'message' => 'รายงานนี้เสร็จสมบูรณ์แล้ว ไม่สามารถแก้ไขคะแนนได้']);
        exit;
    }

    // เริ่ม Transaction
    $pdo->beginTransaction();

    // ---------------------------------------------------------
    // บันทึกผลสรุป (Insert or Update)
    // ---------------------------------------------------------

    // เช็คก่อนว่าเคยประเมินคนนี้ใน Role นี้ไปหรือยัง
    $sqlCheck = "SELECT id FROM staff_assessment_results 
                 WHERE header_id = ? AND user_id = ? AND role_type = ?";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$header_id, $user_id, $role_type]);
    $existingResult = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    $result_id = 0;

    if ($existingResult) {
        // กรณี Update
        $result_id = $existingResult['id'];
        $sqlUpdate = "UPDATE staff_assessment_results 
                      SET total_score = ?, max_score = ?, percentage = ?, grade = ?, updated_at = NOW()
                      WHERE id = ?";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([$total_score, $max_score, $percentage, $grade, $result_id]);

        // ลบ Detail เก่าทิ้งก่อนบันทึกใหม่
        $stmtDel = $pdo->prepare("DELETE FROM staff_assessment_details WHERE result_id = ?");
        $stmtDel->execute([$result_id]);
    } else {
        // กรณี Insert ใหม่
        $sqlInsert = "INSERT INTO staff_assessment_results 
                      (header_id, user_id, role_type, total_score, max_score, percentage, grade, created_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([$header_id, $user_id, $role_type, $total_score, $max_score, $percentage, $grade]);
        $result_id = $pdo->lastInsertId();
    }

    // ---------------------------------------------------------
    // 5. บันทึกรายละเอียดรายข้อ (Loop Insert)
    // ---------------------------------------------------------
    $sqlDetail = "INSERT INTO staff_assessment_details 
                  (result_id, criteria_id, score, comment, remark) 
                  VALUES (?, ?, ?, ?, ?)";
    $stmtDetail = $pdo->prepare($sqlDetail);

    foreach ($scores as $criteria_id => $score_val) {
        // ดึงค่า comment/remark ให้ตรงกับ criteria_id
        $comment_val = $comments[$criteria_id] ?? '';
        $remark_val  = $remarks[$criteria_id] ?? '';

        $stmtDetail->execute([
            $result_id,
            $criteria_id,
            $score_val,
            $comment_val,
            $remark_val
        ]);
    }

    $pdo->commit();

    // ส่ง Response กลับ
    echo json_encode([
        'status'    => 'success',
        'message'   => 'บันทึกการประเมินเรียบร้อยแล้ว',
        'role_type' => $role_type, // ส่งกลับไปอัปเดต UI หน้าบ้าน
        'timestamp' => date('d/m/Y H:i')
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()]);
}
