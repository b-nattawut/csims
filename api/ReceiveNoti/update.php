<?php
use Mpdf\Tag\P;
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");
// echo json_encode($_POST);
// die();
$dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
// เขียนที่
$location_create = $_POST["report_location"] ?? '';
// เมื่อวันที่
$raw_date_create = $_POST["report_datetime"] ?? '';
$createDate = '';
if (!empty($raw_date_create)) {
    $dateCreate = new DateTime($raw_date_create);
    $createDate = $dateCreate->format('Y-m-d H:i:s');
}
// ID
$id = $_POST["id_complaintsEdit"];

$up_to = $_POST["up_to"] ?? '';
$raw_down_to = $_POST["down_to"] ?? '';
$down_to = null; // กำหนดค่าเริ่มต้นเป็น null เผื่อกรณีไม่ได้เลือกวันที่มา
if (!empty($raw_down_to)) {
    $dateDownTo = new DateTime($raw_down_to);
    $down_to = $dateDownTo->format('Y-m-d'); // format ตามชนิดข้อมูลประเภท DATE ของฐานข้อมูล
}

$daily_no = $_POST["daily_no"] ?? '';

// เหตุที่รับแจ้ง
$otherComplaintsType = $_POST["other_type_input"] ?? '';
// รับแจ้งเหตุจาก
$complaints_From = $_POST["source_station"] ?? '';
// ร้องเรียนจากอุปกรณ์
$complaintsFromDevice = $_POST["report_channel"] ?? '';
$complaintsFromDeviceOther = $_POST["report_other_channel"] ?? '';
// สถานที่เกิดเหตุ
$location_crime = $_POST["incident_location"] ?? '';
// เวลาที่เกิดเหตุ
$raw_date = $_POST["event_datetime"] ?? '';
$time_Occurrence = '';
if (!empty($raw_date)) {
    $date = new DateTime($raw_date);
    $time_Occurrence = $date->format('Y-m-d H:i:s');
}
// ข้อมูลเบื้องต้น
$basic_info = $_POST["initial_detail"] ?? '';

// ผู้สอบสวน
$invident_firstname = trim($_POST["inv_firstname"] ?? '');
$invident_lastname = trim($_POST["inv_lastname"] ?? '');
$invident_fullname = $invident_firstname . ' ' . $invident_lastname;
$invident_phone = $_POST["inv_phone"] ?? '';

// ผู้เสียหาย
$suffer_firstname = trim($_POST["vic_firstname"] ?? '');
$suffer_lastname = trim($_POST["vic_lastname"] ?? '');
$suffer_fullname = $suffer_firstname . ' ' . $suffer_lastname;
$suffer_phone = $_POST["vic_phone"] ?? '';
// ---------------------------------------------------------------------------------------------------
// ผู้ทบทวนข้อมูล
$userReviewType = $_POST["user_ReviewType"] ?? '';
$numberReviewType = 0;
if (!empty($_POST["nvt"])) { $numberReviewType = intval($_POST["nvt"]); }
else if(!empty($_POST["ptjv"])) { $numberReviewType = intval($_POST["ptjv"]); }
else if(!empty($_POST["spt"])) { $numberReviewType = intval($_POST["spt"]); }

$userReviewID = intval($_POST["userReviewID"] ?? 0);

//เช็คผู้ทบทวนข้อมูล
$userReviewCheck = $pdo->prepare("SELECT userId FROM rn_ReceiveNotiApprove 
where ComplaintsID = ? AND seqSignature = 2");
//Execute query ด้วย user_id จาก session
$userReviewCheck->execute([$id]);
$userDataReviewCheck = $userReviewCheck->fetch(PDO::FETCH_ASSOC);

if($userReviewID != intval($userDataReviewCheck['userId'])){
    // อัพเดท Table Main
    $userReviewUpt = $pdo->prepare("
    update rn_ReceiveNoti set userReviewID = ?
    where id = ? AND statusDelete = 0 ");

    $userReviewUpt->execute([
        $userReviewID,
        $id
    ]);

    // อัพเดท Table Approve
    $userReviewUpdate = $pdo->prepare("SELECT t1.user_id , CONCAT(t4.rank_name,' ',t2.first_name,' ',t2.last_name) as fullname,t3.position_name FROM users t1 
    left join user_profile t2 on t1.user_id = t2.user_id
    left join user_position t3 on t2.position_id = t3.position_id 
    LEFT JOIN user_rank t4 ON t2.rank_id = t4.rank_id 
    where t1.user_id = ? AND t1.is_active = 1");
    $userReviewUpdate->execute([$userReviewID]);
    $userDataReviewUpt = $userReviewUpdate->fetch(PDO::FETCH_ASSOC);

    $userReviewApproveUpt = $pdo->prepare("
    update rn_ReceiveNotiApprove set 
        userId = ?,
        positionName = ?,
        fullName = ?
        where ComplaintsID = ? AND seqSignature = 2
    ");

    $userReviewApproveUpt->execute([
        $userDataReviewUpt['user_id'],
        $userDataReviewUpt['position_name'],
        $userDataReviewUpt['fullname'],
        $id
    ]);

}
// ผู้อนุมัติข้อมูล
$userApproveType = $_POST["user_ApproveType"] ?? '';
$numberApproveType = 0;
if (!empty($_POST["nvtApprove"])) { $numberApproveType = intval($_POST["nvtApprove"]); }
else if(!empty($_POST["ptjvApprove"])) { $numberApproveType = intval($_POST["ptjvApprove"]); }
else if(!empty($_POST["sptApprove"])) { $numberApproveType = intval($_POST["sptApprove"]); }

$userApproveID = intval($_POST["userApproveID"] ?? 0);

//เช็คผู้อนุมัติข้อมูล
$userApproveCheck = $pdo->prepare("SELECT userId FROM rn_ReceiveNotiApprove 
where ComplaintsID = ? AND seqSignature = 3");
//Execute query ด้วย user_id จาก session
$userApproveCheck->execute([$id]);
$userDataApproveCheck = $userApproveCheck->fetch(PDO::FETCH_ASSOC);

if($userApproveID != intval($userDataApproveCheck['userId'])){
    
    // อัพเดท Table Main
    $userApproveUpt = $pdo->prepare("
    update rn_ReceiveNoti set userApproveID = ?
    where id = ? AND statusDelete = 0 ");

    $userApproveUpt->execute([
        $userApproveID,
        $id
    ]);

    // อัพเดท Table Approve
    $userApproveUpdate = $pdo->prepare("SELECT t1.user_id , CONCAT(t4.rank_name,' ',t2.first_name,' ',t2.last_name) as fullname,t3.position_name FROM users t1 
    left join user_profile t2 on t1.user_id = t2.user_id
    left join user_position t3 on t2.position_id = t3.position_id 
    LEFT JOIN user_rank t4 ON t2.rank_id = t4.rank_id 
    where t1.user_id = ? AND t1.is_active = 1");
    $userApproveUpdate->execute([$userApproveID]);
    $userDataApproveUpt = $userApproveUpdate->fetch(PDO::FETCH_ASSOC);


    $uptUserApprove = $pdo->prepare("
    update rn_ReceiveNotiApprove set 
        userId = ?,
        positionName = ?,
        fullName = ?
        where ComplaintsID = ? AND seqSignature = 3
    ");

    $uptUserApprove->execute([
        $userDataApproveUpt['user_id'],
        $userDataApproveUpt['position_name'],
        $userDataApproveUpt['fullname'],
        $id
    ]);

}


$stmt = $pdo->prepare("
    update rn_ReceiveNoti set 
        location_create = ?,
        create_date = ?,
        complaints_type_other = ?,
        complaints_From = ?,
        complaints_From_Device = ?,
        complaints_From_Device_Other = ?,
        location_crime = ?,
        basic_info = ?,
        time_Occurrence = ?,
        inquiry_official_first_name = ?,
        inquiry_official_last_name = ?,
        inquiry_official_full_name = ?,
        inquiry_official_phone = ?,
        suffer_first_name = ?,
        suffer_last_name = ?,
        suffer_full_name = ?,
        suffer_phone = ?,
        userReviewType = ?,
        userReviewTypeVal = ?,
        userApproveType = ?,
        userApproveTypeVal = ?,
        up_to = ?,             
        down_to = ?,
        daily_no = ?,
        edit_by = ?,
        edit_date = ?
        where statusDelete = 0 and id = ?
");

$stmt->execute([
    $location_create, // เขียนที่
    $createDate, // เมื่อวันที่
    $otherComplaintsType, // เหตุที่รับแจ้งอื่นๆ ในกรณีที่ type เป้นอื่นๆ
    $complaints_From, // ร้องเรียนจาก สภ./สน
    $complaintsFromDevice, // ร้องเรียนทางอุปกรณ์อะไร
    $complaintsFromDeviceOther, // อุปกรณ์อื่นๆ
    $location_crime, // สถานที่เกิดเหตุ
    $basic_info, // ข้อมูลเบื้องต้น
    $time_Occurrence, // เวลาที่เกิดเหตุ
    $invident_firstname, // ชื่อจริงพนักงานสอบสวน
    $invident_lastname, // นามสกุลพนักงานสอบสวน
    $invident_fullname, // ชื่อเต็มพนักงานสอบสวน
    $invident_phone, // เบอร์โทรศัพท์พนักงานสอบสวน
    $suffer_firstname, // ชื่อจริงผู้เสียหาย
    $suffer_lastname, // นามสกุลผู้เสียหาย
    $suffer_fullname, // ชื่อเต็มผู้เสียหาย
    $suffer_phone, // เบอร์โทรศัพท์ผู้เสียหาย
    $userReviewType, // เรียน type Review
    $numberReviewType, // number type
    $userApproveType, // เรียน type Approve
    $numberApproveType, // number type
    $up_to, // ที่ 
    $down_to, // ลงวันที่ 
    $daily_no, // ประจำวันข้อที่
    $_SESSION['user_id'],
    $dateNow,
    $id
]);

// ส่งค่ากลับไปที่หน้าบ้านทันทีเพื่อตรวจสอบ และหยุดการทำงานตรงนี้
echo json_encode([
    "status" => "success"
]);

?>