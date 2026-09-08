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

$header_id = $_POST['header_id'] ?? null;
$role_key  = $_POST['role_key'] ?? null;
$signature = $_POST['signature'] ?? null;

if (!$header_id || !$role_key || !$signature) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']); exit;
}

// แมปบทบาทกับชื่อคอลัมน์ในตาราง
$roleMap = [
    'CHECKED' => ['sig' => 'checked_signature', 'date' => 'checked_signed_at'],
    'LEADER'  => ['sig' => 'leader_signature',  'date' => 'leader_signed_at'],
    'UNIT'    => ['sig' => 'unit_officer_signature', 'date' => 'unit_officer_signed_at']
];

$colSig  = $roleMap[$role_key]['sig'];
$colDate = $roleMap[$role_key]['date'];

try {
    $pdo->beginTransaction();

    // 1. อัปเดตลายเซ็นของบทบาทนั้นๆ
    $sql = "UPDATE trans_readiness_check_header SET $colSig = ?, $colDate = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$signature, $header_id]);

    // 2. ตรวจสอบว่าเซ็นครบทั้ง 3 คนหรือยัง?
    $stmtCheck = $pdo->prepare("SELECT checked_signature, leader_signature, unit_officer_signature FROM trans_readiness_check_header WHERE id = ?");
    $stmtCheck->execute([$header_id]);
    $h = $stmtCheck->fetch();

    if (!empty($h['checked_signature']) && !empty($h['leader_signature']) && !empty($h['unit_officer_signature'])) {
        // ถ้าครบ 3 คน ให้เปลี่ยนสถานะเป็น COMPLETED และบันทึกวันที่จบงานหลัก
        $sqlDone = "UPDATE trans_readiness_check_header SET status = 'COMPLETED' WHERE id = ?";
        $pdo->prepare($sqlDone)->execute([$header_id]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}