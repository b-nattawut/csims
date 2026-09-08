<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบ Session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

// 2. ตรวจสอบ Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$current_user_id = $_SESSION['user_id'];

try {
    // 3. รับค่า
    $header_id = $_POST['header_id'] ?? null;
    $signature = $_POST['signature'] ?? null; // Base64 String

    if (!$header_id || !$signature) {
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน (Missing ID or Signature)']);
        exit;
    }

    // 4. ตรวจสอบว่าประเมินครบทุกคนหรือยัง? (Double Check ฝั่ง Server เพื่อความปลอดภัย)
    // นับจำนวนคนที่ต้องประเมิน (ที่มีชื่อระบุไว้)
    $sqlCountTargets = "SELECT 
                            (CASE WHEN leader_id IS NOT NULL THEN 1 ELSE 0 END) +
                            (CASE WHEN photographer_id IS NOT NULL THEN 1 ELSE 0 END) +
                            (CASE WHEN map_maker_id IS NOT NULL THEN 1 ELSE 0 END) +
                            (CASE WHEN searcher_id IS NOT NULL THEN 1 ELSE 0 END) +
                            (CASE WHEN collector_id IS NOT NULL THEN 1 ELSE 0 END) as target_count
                        FROM staff_assessment_header WHERE id = ?";
    $stmtTarget = $pdo->prepare($sqlCountTargets);
    $stmtTarget->execute([$header_id]);
    $target = $stmtTarget->fetch(PDO::FETCH_ASSOC);

    // นับจำนวนผลการประเมินที่มีจริง
    $sqlCountResults = "SELECT COUNT(*) as result_count FROM staff_assessment_results WHERE header_id = ?";
    $stmtResult = $pdo->prepare($sqlCountResults);
    $stmtResult->execute([$header_id]);
    $result = $stmtResult->fetch(PDO::FETCH_ASSOC);

    // ถ้าจำนวนไม่เท่ากัน แสดงว่ายังประเมินไม่ครบ
    if ($result['result_count'] < $target['target_count']) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถปิดงานได้ เนื่องจากยังประเมินเจ้าหน้าที่ไม่ครบทุกตำแหน่ง']);
        exit;
    }

    // 5. อัปเดตสถานะและบันทึกลายเซ็น
    $sqlUpdate = "UPDATE staff_assessment_header 
                  SET status = 'COMPLETED', 
                      evaluator_signature = :signature, 
                      signed_date = NOW(), 
                      updated_at = NOW(),
                      updated_by = :updated_by
                  WHERE id = :id";
    
    $stmt = $pdo->prepare($sqlUpdate);
    $stmt->execute([
        ':signature'  => $signature,
        ':updated_by' => $current_user_id,
        ':id'         => $header_id
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'บันทึกข้อมูลและปิดงานเรียบร้อยแล้ว',
        'signed_date' => date('d/m/Y H:i')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>