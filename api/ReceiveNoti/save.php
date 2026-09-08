<?php

use Mpdf\Tag\P;

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
require __DIR__ . '/../../helpers/report_no.php';
header("Content-Type: application/json; charset=UTF-8");
// echo json_encode($_POST);
// die();
$dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
$dateObj = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
$yearTH = (int)$dateObj->format('Y') + 543;
$subYear = substr($yearTH, 2);

// เขียนที่
$location_create = $_POST["report_location"] ?? '';
// เมื่อวันที่
$raw_date_create = $_POST["report_datetime"] ?? '';
$createDate = '';
if (!empty($raw_date_create)) {
    $dateCreate = new DateTime($raw_date_create);
    $createDate = $dateCreate->format('Y-m-d H:i:s');
}

// ==========================================
// ส่วนที่เพิ่มเข้ามา: รับค่า "ที่" และ "ลงวันที่" จากหน้าบ้าน
// ==========================================
$up_to = $_POST["up_to"] ?? '';
$raw_down_to = $_POST["down_to"] ?? '';
$downToDate = null;
if (!empty($raw_down_to)) {
    $dateDown = new DateTime($raw_down_to);
    $downToDate = $dateDown->format('Y-m-d'); // บันทึกเป็น YYYY-MM-DD ตามโครงสร้าง Date ของ Database
}
$daily_no = $_POST["daily_no"] ?? '';

// เหตุที่รับแจ้ง
$incident_type = $_POST["incident_type"] ?? '';
$otherComplaintsType = $_POST["other_type_input"] ?? '';
if ($incident_type !== '09') {
    $otherComplaintsType = '';
}
// รับแจ้งเหตุจาก
$complaints_From = $_POST["source_station"] ?? '';
// ร้องเรียนจากอุปกรณ์
$complaintsFromDevice = $_POST["report_channel"] ?? '';
$complaintsFromDeviceOther = $_POST["report_other_channel"] ?? '';
// สถานที่เกิดเหตุ
$location_crime = $_POST["incident_location"] ?? '';
// เวลาที่เกิดเหตุ
$raw_date = $_POST["event_datetime"] ?? '';
$time_Occurrence = null;
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

// ผู้ทบทวนข้อมูล
$userReviewType = $_POST["user_ReviewType"] ?? '';
$numberReviewType = 0;
if (!empty($_POST["nvt"])) {
    $numberReviewType = intval($_POST["nvt"]);
} else if (!empty($_POST["ptjv"])) {
    $numberReviewType = intval($_POST["ptjv"]);
} else if (!empty($_POST["spt"])) {
    $numberReviewType = intval($_POST["spt"]);
}

$userReviewID = intval($_POST["userReviewID"] ?? 0);
$userReviewName = trim($_POST["userReviewName"] ?? ''); // ศพฐ 1-9: ชื่อที่พิมพ์เอง

// ผู้อนุมัติข้อมูล
$userApproveType = $_POST["user_ApproveType"] ?? '';
$numberApproveType = 0;
if (!empty($_POST["nvtApprove"])) {
    $numberApproveType = intval($_POST["nvtApprove"]);
} else if (!empty($_POST["ptjvApprove"])) {
    $numberApproveType = intval($_POST["ptjvApprove"]);
} else if (!empty($_POST["sptApprove"])) {
    $numberApproveType = intval($_POST["sptApprove"]);
}

$userApproveID = intval($_POST["userApproveID"] ?? 0);
$userApproveName = trim($_POST["userApproveName"] ?? ''); // ศพฐ 1-9: ชื่อที่พิมพ์เอง

// จังหวัด
$provineID = $_POST["provineID"] ?? '';
$provineName = '';
if ($provineID == '90') {
    $provineName .= 'สงขลา';
} else if ($provineID == '95') {
    $provineName .= 'ยะลา';
} else if ($provineID == '94') {
    $provineName .= 'ปัตตานี';
} else {
    $provineName .= 'นราธิวาส';
}
if ($incident_type == '01') {
    $DocNoPrefix = "ท";
    $ReportNo = "LC-";
} else if ($incident_type == '02') {
    $DocNoPrefix = "ช";
    $ReportNo = "MD-";
} else if ($incident_type == '03') {
    $DocNoPrefix = "ร";
    $ReportNo = "BM-";
} else if ($incident_type == '04') {
    $DocNoPrefix = "พ";
    $ReportNo = "FR-";
} else if ($incident_type == '05') {
    $DocNoPrefix = "จ";
    $ReportNo = "TF-";
} else if ($incident_type == '06') {
    $DocNoPrefix = "ว";
    $ReportNo = "EV-";
} else if ($incident_type == '07') {
    $DocNoPrefix = "อ";
    $ReportNo = "CM-";
} else if ($incident_type == '08') {
    $DocNoPrefix = "บ";
    $ReportNo = "PS-";
} else if ($incident_type == '09') {
    $DocNoPrefix = "อ";
    $ReportNo = "OT-";
} else {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'ประเภทเหตุที่รับแจ้งไม่ถูกต้อง'
    ]);
    exit;
}

// สร้างเลขที่เอกสาร: 10-{provinceID}-{subYear}-{TypeCode}{Running4digit}
// เช่น: 10-95-69-MD0001, 10-96-69-LC0001
$typeCode = rtrim($ReportNo, '-'); // "MD", "LC", "BM", "FR", "TF", "EV", "CM", "PS"
$docNoNewPrefix = "10-" . $provineID . "-" . $subYear . "-" . $typeCode;

// ค้นหาเลขที่ล่าสุดของประเภท+จังหวัด+ปีนี้
$sql = "SELECT receiveNoti_No FROM rn_ReceiveNoti WHERE statusDelete = 0 AND receiveNoti_No LIKE :prefix ORDER BY receiveNoti_No DESC LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':prefix' => $docNoNewPrefix . '%']);

$lastDoc = $stmt->fetchColumn();
if ($lastDoc) {
    // ดึงเลข running number จากรูปแบบ 10-95-69-MD0003 → 0003
    $runningStr = substr($lastDoc, strlen($docNoNewPrefix));
    $lastNumber = (int)$runningStr;
    $nextDocNumber = $lastNumber + 1;
} else {
    $nextDocNumber = 1;
}

// ค้นหาเลขรายงานล่าสุดของประเภท+ปีนี้
$reportPrefix = $ReportNo; // "MD-", "LC-", etc.
$reportSuffix = "/" . $yearTH; // "/2569"
$sql2 = "SELECT receiveNotiReportNo FROM rn_ReceiveNoti WHERE statusDelete = 0 AND receiveNotiReportNo LIKE :prefix AND receiveNotiReportNo LIKE :suffix ORDER BY receiveNotiReportNo DESC LIMIT 1";
$stmt2 = $pdo->prepare($sql2);
$stmt2->execute([
    ':prefix' => $reportPrefix . '%',
    ':suffix' => '%' . $reportSuffix
]);

$lastReport = $stmt2->fetchColumn();
if ($lastReport) {
    preg_match('/(\d+)\//', $lastReport, $m);
    $lastReportNumber = isset($m[1]) ? (int)$m[1] : 0;
    $nextReportNumber = $lastReportNumber + 1;
} else {
    $nextReportNumber = 1;
}

$DocNoSave = $docNoNewPrefix . str_pad($nextDocNumber, 4, '0', STR_PAD_LEFT);
$DocNoSaveTH = convertDocNoToThai($DocNoSave);
$ReportNoSave = $ReportNo . str_pad($nextReportNumber, 4, '0', STR_PAD_LEFT) . "/" . $yearTH;
$ReportNoSaveTH = convertReportNoToThai($ReportNoSave);

$stmt = $pdo->prepare("
    INSERT INTO rn_ReceiveNoti (
        receiveNoti_No,
        receiveNoti_No_TH,
        receiveNotiReportNo,
        receiveNotiReportNo_TH,
        location_create,
        create_by,
        create_date,
        complaints_type,
        complaints_type_other,
        complaints_From,
        complaints_From_Device,
        complaints_From_Device_Other,
        location_crime,
        basic_info,
        time_Occurrence,
        inquiry_official_first_name,
        inquiry_official_last_name,
        inquiry_official_full_name,
        inquiry_official_phone,
        suffer_first_name,
        suffer_last_name,
        suffer_full_name,
        suffer_phone,
        create_dateTimeStamp,
        userReviewType,
        userReviewTypeVal,
        userReviewID,
        userApproveType,
        userApproveTypeVal,
        userApproveID,
        provinceID,
        province,
        up_to,
        down_to,
        daily_no
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");


$stmt->execute([
    $DocNoSave,
    $DocNoSaveTH,
    $ReportNoSave,
    $ReportNoSaveTH,
    $location_create,
    $_SESSION['user_id'],
    $createDate,
    $incident_type,
    $otherComplaintsType,
    $complaints_From,
    $complaintsFromDevice,
    $complaintsFromDeviceOther,
    $location_crime,
    $basic_info,
    $time_Occurrence,
    $invident_firstname,
    $invident_lastname,
    $invident_fullname,
    $invident_phone,
    $suffer_firstname,
    $suffer_lastname,
    $suffer_fullname,
    $suffer_phone,
    $dateNow,
    $userReviewType,
    $numberReviewType,
    $userReviewID,
    $userApproveType,
    $numberApproveType,
    $userApproveID,
    $provineID,
    $provineName,
    $up_to,
    $downToDate,
    $daily_no
]);

$insertId = $pdo->lastInsertId();
$userCreate = $pdo->prepare("SELECT t1.user_id , CONCAT(t4.rank_name,' ',t2.first_name,' ',t2.last_name) as fullname,t3.position_name FROM users t1 
left join user_profile t2 on t1.user_id = t2.user_id
left join user_position t3 on t2.position_id = t3.position_id 
LEFT JOIN user_rank t4 ON t2.rank_id = t4.rank_id 
where t1.user_id = ? AND t1.is_active = 1");

// Execute query ด้วย user_id จาก session
$userCreate->execute([$_SESSION['user_id']]);

// // ดึงข้อมูลออกมา
$userData = $userCreate->fetch(PDO::FETCH_ASSOC);
// // บันทึกข้อมูลลง Approve
$userCreateApprove = $pdo->prepare("INSERT INTO rn_ReceiveNotiApprove (ComplaintsID,userId,positionName,fullName,seqSignature) VALUES (?,?,?,?,?)");

$userCreateApprove->execute([
    $insertId,
    $userData['user_id'],
    $userData['position_name'],
    $userData['fullname'],
    1
]);

// Review
if (!empty($userReviewName)) {
    // ศพฐ 1-9: ชื่อที่พิมพ์เอง (ไม่มี user_id)
    $posReviewName = trim($_POST["posUserReview"] ?? '');
    $userReviewApprove = $pdo->prepare("INSERT INTO rn_ReceiveNotiApprove (ComplaintsID,userId,positionName,fullName,seqSignature) VALUES (?,?,?,?,?)");
    $userReviewApprove->execute([
        $insertId,
        null,
        $posReviewName,
        $userReviewName,
        2
    ]);
} else {
    $userReview = $pdo->prepare("SELECT t1.user_id , CONCAT(t4.rank_name,' ',t2.first_name,' ',t2.last_name) as fullname,t3.position_name FROM users t1 
    left join user_profile t2 on t1.user_id = t2.user_id
    left join user_position t3 on t2.position_id = t3.position_id 
    LEFT JOIN user_rank t4 ON t2.rank_id = t4.rank_id 
    where t1.user_id = ? AND t1.is_active = 1");
    $userReview->execute([$userReviewID]);
    $userDataReview = $userReview->fetch(PDO::FETCH_ASSOC);
    $userReviewApprove = $pdo->prepare("INSERT INTO rn_ReceiveNotiApprove (ComplaintsID,userId,positionName,fullName,seqSignature) VALUES (?,?,?,?,?)");
    $userReviewApprove->execute([
        $insertId,
        $userDataReview['user_id'],
        $userDataReview['position_name'],
        $userDataReview['fullname'],
        2
    ]);
}

// Approve
if (!empty($userApproveName)) {
    // ศพฐ 1-9: ชื่อที่พิมพ์เอง (ไม่มี user_id)
    $posApproveName = trim($_POST["posUserApprove"] ?? '');
    $userApproveInsert = $pdo->prepare("INSERT INTO rn_ReceiveNotiApprove (ComplaintsID,userId,positionName,fullName,seqSignature) VALUES (?,?,?,?,?)");
    $userApproveInsert->execute([
        $insertId,
        null,
        $posApproveName,
        $userApproveName,
        3
    ]);
} else {
    $userApprove = $pdo->prepare("SELECT t1.user_id , CONCAT(t4.rank_name,' ',t2.first_name,' ',t2.last_name) as fullname,t3.position_name FROM users t1 
    left join user_profile t2 on t1.user_id = t2.user_id
    left join user_position t3 on t2.position_id = t3.position_id 
    LEFT JOIN user_rank t4 ON t2.rank_id = t4.rank_id 
    where t1.user_id = ? AND t1.is_active = 1");
    $userApprove->execute([$userApproveID]);
    $userDataApprove = $userApprove->fetch(PDO::FETCH_ASSOC);
    $userApproveInsert = $pdo->prepare("INSERT INTO rn_ReceiveNotiApprove (ComplaintsID,userId,positionName,fullName,seqSignature) VALUES (?,?,?,?,?)");
    $userApproveInsert->execute([
        $insertId,
        $userDataApprove['user_id'],
        $userDataApprove['position_name'],
        $userDataApprove['fullname'],
        3
    ]);
}
// ส่งค่ากลับไปที่หน้าบ้านทันทีเพื่อตรวจสอบ และหยุดการทำงานตรงนี้
echo json_encode([
    "status" => "success"
]);
