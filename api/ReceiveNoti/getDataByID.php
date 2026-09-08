<?php

session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$id = $_GET["id"];

$stmt = $pdo->prepare("SELECT t1.id
,t1.receiveNoti_No
,t1.receiveNotiReportNo
,t1.location_create
,t1.complaints_type
,t1.complaints_type_other
,t1.create_date
,t1.complaints_From
,t1.complaints_From_Device
,t1.complaints_From_Device_Other
,t1.location_crime
,t1.basic_Info
,t1.time_Occurrence
,t1.inquiry_official_first_name
,t1.inquiry_official_last_name
,t1.inquiry_official_phone
,t1.suffer_first_name
,t1.suffer_last_name
,t1.suffer_phone
,t1.provinceID 
,t1.userReviewType
,t1.userReviewTypeVal
,t1.userReviewID
,t1.userApproveType
,t1.userApproveTypeVal
,t1.userApproveID
,t1.up_to      
,t1.down_to
,t1.daily_no
FROM rn_ReceiveNoti t1
WHERE t1.statusDelete = 0 AND t1.id = ?");

$stmt->execute([
    $id
]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

// เอาไว้เช็ค ว่าผู้ Review เซ็นต์ไปยัง
$checkUserReview = $pdo->prepare("SELECT CASE WHEN signature IS NOT NULL AND signature != '' THEN TRUE ELSE FALSE
END AS has_signature
FROM rn_ReceiveNotiApprove
WHERE ComplaintsID = ? AND seqSignature = 2");

$checkUserReview->execute([
    $id
]);

$dataCheckUserReview = $checkUserReview->fetch(PDO::FETCH_ASSOC);

// เอาไว้เช็ค ว่าผู้ Approve เซ็นต์ไปยัง
$checkUserApprove = $pdo->prepare("SELECT CASE WHEN signature IS NOT NULL AND signature != '' THEN TRUE ELSE FALSE
END AS has_signature
FROM rn_ReceiveNotiApprove
WHERE ComplaintsID = ? AND seqSignature = 3");

$checkUserApprove->execute([
    $id
]);

$dataCheckUserApprove = $checkUserApprove->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => 'success',
    'data' => $data,
    'checkUserReview' => $dataCheckUserReview,
    'checkUserApprove' => $dataCheckUserApprove
], JSON_UNESCAPED_UNICODE);

?>