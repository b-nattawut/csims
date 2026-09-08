<?php
use Mpdf\Tag\P;
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$ComplaintsID = $_POST['id'] ?? null;
$ApproveID = $_POST['id'] ?? null;
$UserID = $_SESSION['user_id'];
$dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');

if (!$ComplaintsID) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบ ID'
    ]);
    exit;
}

$stmt = $pdo->prepare("UPDATE rn_ReceiveNotiApprove  SET signature = 1, signature_date = :dateNow
WHERE ApproveID = :approveID AND ComplaintsID = :complaintsID AND userId = :userId");
$stmt->execute([
    ':dateNow' => $dateNow,
    ':approveID' => $ApproveID,
    ':complaintsID' => $ComplaintsID,
    ':userId' => $UserID
]);

echo json_encode([
    "message" => "success",
]);

?>