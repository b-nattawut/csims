<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบ Session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// 2. ตรวจสอบ Method (ใช้ GET)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

try {
    // 3. รับค่า Parameter
    $header_id = $_GET['header_id'] ?? null;
    $user_id   = $_GET['user_id'] ?? null;
    $role_type = $_GET['role_type'] ?? null;

    if (!$header_id || !$user_id || !$role_type) {
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }

    // 4. ดึงข้อมูลส่วนหัวคะแนน (Results)
    $sqlResult = "SELECT id, total_score, max_score, percentage, grade, updated_at 
                  FROM staff_assessment_results 
                  WHERE header_id = ? AND user_id = ? AND role_type = ?";
    $stmtResult = $pdo->prepare($sqlResult);
    $stmtResult->execute([$header_id, $user_id, $role_type]);
    $result = $stmtResult->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        // ถ้ายังไม่มีข้อมูล (ยังไม่เคยประเมิน) ส่ง status: empty กลับไป
        echo json_encode(['status' => 'empty', 'message' => 'ยังไม่มีผลการประเมิน']);
        exit;
    }

    // 5. ดึงข้อมูลรายละเอียดรายข้อ (Details)
    // เลือกมาเฉพาะ criteria_id และค่าที่กรอก เพื่อเอาไป map กลับหน้าบ้านง่ายๆ
    $sqlDetail = "SELECT criteria_id, score, comment, remark 
                  FROM staff_assessment_details 
                  WHERE result_id = ?";
    $stmtDetail = $pdo->prepare($sqlDetail);
    $stmtDetail->execute([$result['id']]);
    $details = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

    // 6. จัดรูปแบบข้อมูล Detail ให้อยู่ในรูป Object เพื่อให้ JS ใช้งานง่าย
    // จากเดิม: [{criteria_id: 1, score: 3}, {criteria_id: 2, score: 2}]
    // เปลี่ยนเป็น: { "1": {score: 3, ...}, "2": {score: 2, ...} }
    $detailsMap = [];
    foreach ($details as $row) {
        $detailsMap[$row['criteria_id']] = [
            'score'   => $row['score'],
            'comment' => $row['comment'],
            'remark'  => $row['remark']
        ];
    }

    // 7. ส่งข้อมูลกลับ
    echo json_encode([
        'status' => 'success',
        'data' => [
            'result'  => $result,      // ข้อมูลคะแนนรวม
            'details' => $detailsMap   // ข้อมูลรายข้อ (Key เป็น criteria_id)
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>