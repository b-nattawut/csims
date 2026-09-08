<?php
require '../../db_config.php';
require __DIR__ . '/../../helpers/report_no.php';
header("Content-Type: application/json; charset=UTF-8");

$doc_no = $_POST['filter_doc_no'] ?? '';
$report_no = $_POST['filter_Report_no'] ?? '';
$province = $_POST['filter_province'] ?? '';
$station = $_POST['filter_station'] ?? '';
$person = $_POST['filter_person'] ?? '';
$incident_type = $_POST['filter_incident_type'] ?? '';

$where = " WHERE t1.statusDelete = 0 AND t2.is_active = 1 ";
$params = [];

// --- จัดการเงื่อนไข Filter ---
if (!empty($doc_no)) {
    $where .= " AND (t1.receiveNoti_No LIKE ? OR t1.receiveNoti_No_TH LIKE ?) ";
    $params[] = "%$doc_no%";
    $params[] = "%$doc_no%";
}

if (!empty($report_no)) {
    $where .= " AND (t1.receiveNotiReportNo LIKE ? OR t1.receiveNotiReportNo_TH LIKE ?) ";
    $params[] = "%$report_no%";
    $params[] = "%$report_no%";
}

if (!empty($province)) {
    $where .= " AND t1.provinceID = ? ";
    $params[] = $province;
}

if (!empty($station)) {
    $where .= " AND t1.complaints_From LIKE ? ";
    $params[] = "%$station%";
}

if (!empty($incident_type)) {
    $where .= " AND t1.complaints_type = ? ";
    $params[] = $incident_type;
}

// 2. สร้างเงื่อนไข HAVING (แยกออกมาสำหรับ Alias)
$having = "";
$paramsHaving = [];
if (!empty($person)) {
    $having = " HAVING fullname_create LIKE ? ";
    $paramsHaving[] = "%$person%";
}

$limit = 15; // จำนวนรายการต่อหน้า
$page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;

$offset = ($page - 1) * $limit; // คำนวณจุดเริ่มต้น

$sql = "SELECT t1.id,t1.receiveNoti_No,t1.receiveNoti_No_TH,t1.receiveNotiReportNo,t1.receiveNotiReportNo_TH,t1.complaints_From,t1.statusChecklist,CONCAT(t4.rank_name,' ',t3.first_name,' ',t3.last_name) AS fullname_create,
t1.complaints_type,
case when t1.complaints_type = '01' then 'ทรัพย์'
when t1.complaints_type = '02' then 'ชีวิต'
when t1.complaints_type = '03' then 'ระเบิด'
when t1.complaints_type = '04' then 'เพลิงไหม้'
when t1.complaints_type = '05' then 'จราจร'
when t1.complaints_type = '06' then 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)'
when t1.complaints_type = '07' then 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ'
when t1.complaints_type = '08' then 'ตรวจเก็บวัตถุพยานบุคคล'
ELSE CONCAT('ไม่ทราบ (', t1.complaints_type, ')') END AS complaintstype,
case when t1.complaints_From_Device = 'r' then 'วิทยุสื่อสาร'
when t1.complaints_From_Device = 't' then 'ทางโทรศัพท์'
ELSE 'อื่นๆ' END AS complaintsdevice,
t1.province
FROM rn_ReceiveNoti t1
LEFT JOIN users t2 ON t1.create_by = t2.user_id
LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
$where
$having
ORDER BY t1.id DESC
LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
// รวม params ทั้งหมดเข้าด้วยกัน
$stmt->execute(array_merge($params, $paramsHaving));
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($data as &$rowTH) {
    if (empty($rowTH['receiveNotiReportNo_TH'])) {
        $rowTH['receiveNotiReportNo_TH'] = convertReportNoToThai($rowTH['receiveNotiReportNo'] ?? '');
    }
    if (empty($rowTH['receiveNoti_No_TH'])) {
        $rowTH['receiveNoti_No_TH'] = convertDocNoToThai($rowTH['receiveNoti_No'] ?? '');
    }
}
unset($rowTH);

// 4. การนับจำนวน (ใช้ Subquery ครอบ เพื่อให้รองรับ HAVING)
$sqlCount = "SELECT COUNT(*) as countdata FROM (
SELECT CONCAT(t4.rank_name,' ',t3.first_name,' ',t3.last_name) AS fullname_create, t1.complaints_From
FROM rn_ReceiveNoti t1 
LEFT JOIN users t2 ON t1.create_by = t2.user_id
LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id 
LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
$where 
$having
) as tmpTable";

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute(array_merge($params, $paramsHaving));
$countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);
// คำนวณจำนวนหน้าทั้งหมดก่อนส่งออกไป
$totalRows = (int)$countRes['countdata'];
$totalPages = ceil($totalRows / $limit);

echo json_encode([
    'status' => 'success',
    'data' => $data,
    'count' => $countRes['countdata'],
    'totalRows' => $totalRows,   // จำนวนรายการทั้งหมด
    'totalPages' => $totalPages, // จำนวนหน้าทั้งหมด (สำคัญมาก)
    'currentPage' => $page,      // หน้าปัจจุบัน
    'offset' => $offset          // ส่ง offset กลับไปแก้ปัญหาเลขลำดับเป็น NaN
], JSON_UNESCAPED_UNICODE);