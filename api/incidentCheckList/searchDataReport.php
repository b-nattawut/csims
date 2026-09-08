<?php
require '../../db_config.php';
require __DIR__ . '/../../helpers/report_no.php';
header("Content-Type: application/json; charset=UTF-8");

$doc_no = $_POST['filter_doc_no'] ?? '';
$report_no = $_POST['filter_report_no'] ?? '';
$province = $_POST['filter_province'] ?? '';
$station = $_POST['filter_station'] ?? '';
$incident_type = $_POST['filter_incident_type'] ?? '';

$where = " WHERE t1.statusDelete = 0 AND t2.is_active = 1 AND t1.statusChecklist = 1 ";
$params = [];

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

$limit = 15;
$page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;

$offset = ($page - 1) * $limit;

$sql = "SELECT t1.id, t1.create_by, t1.receiveNoti_No, t1.receiveNoti_No_TH, t1.receiveNotiReportNo, t1.receiveNotiReportNo_TH, t1.complaints_From, t1.province,
t1.complaints_type,
ict.incident_checklist_data,
COALESCE(ict.count_report, 0) as count_report,
CASE WHEN t1.complaints_type = '01' THEN 'ทรัพย์'
WHEN t1.complaints_type = '02' THEN 'ชีวิต'
WHEN t1.complaints_type = '03' THEN 'ระเบิด'
WHEN t1.complaints_type = '04' THEN 'เพลิงไหม้'
WHEN t1.complaints_type = '05' THEN 'จราจร'
WHEN t1.complaints_type = '06' THEN 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)'
WHEN t1.complaints_type = '07' THEN 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ'
WHEN t1.complaints_type = '08' THEN 'ตรวจเก็บวัตถุพยานบุคคล'
ELSE CONCAT('ไม่ทราบ (', t1.complaints_type, ')') END AS complaintstype,
CASE WHEN t1.complaints_From_Device = 'r' THEN 'วิทยุสื่อสาร'
WHEN t1.complaints_From_Device = 't' THEN 'ทางโทรศัพท์'
ELSE 'อื่นๆ' END AS complaintsdevice
FROM rn_ReceiveNoti t1
LEFT JOIN users t2 ON t1.create_by = t2.user_id
LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
LEFT JOIN incident_checklist_transaction ict ON ict.incident_id = t1.id
$where
ORDER BY t1.id DESC
LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process location_type for life cases
foreach ($data as &$row) {
    if (empty($row['receiveNotiReportNo_TH'])) {
        $row['receiveNotiReportNo_TH'] = convertReportNoToThai($row['receiveNotiReportNo'] ?? '');
    }
    if (empty($row['receiveNoti_No_TH'])) {
        $row['receiveNoti_No_TH'] = convertDocNoToThai($row['receiveNoti_No'] ?? '');
    }
    $row['location_type'] = '';
    if ($row['complaints_type'] == '02' && !empty($row['incident_checklist_data'])) {
        $ckData = json_decode($row['incident_checklist_data'], true);
        $sc = $ckData['scene_characteristics'] ?? [];
        if (!empty($sc['has_outdoor'])) {
            $row['location_type'] = 'outdoor';
            $row['complaintstype'] = 'ชีวิต (นอกอาคาร)';
        } elseif (!empty($sc['has_indoor'])) {
            $row['location_type'] = 'indoor';
            $row['complaintstype'] = 'ชีวิต (ในอาคาร)';
        }
    }else if($row['complaints_type'] == '03' && !empty($row['incident_checklist_data'])){
        $ckData = json_decode($row['incident_checklist_data'], true);
        $sc = $ckData['scene_info'] ?? [];
        $indoor = $sc['indoor']['is_active'] == 1 ? 'true' : 'false';
        if ($indoor == 'true') {
            $row['location_type'] = 'indoor';
            $row['complaintstype'] = 'ระเบิด (ในอาคาร)';
        } else {
            $row['location_type'] = 'outdoor';
            $row['complaintstype'] = 'ระเบิด (นอกอาคาร)';
        }
    }
    unset($row['incident_checklist_data']); // ไม่ต้องส่ง data ทั้งหมดกลับไป
}
unset($row);

$sqlCount = "SELECT COUNT(*) as countdata
FROM rn_ReceiveNoti t1
LEFT JOIN users t2 ON t1.create_by = t2.user_id
LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
$where";

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

$totalRows = (int)$countRes['countdata'];
$totalPages = ceil($totalRows / $limit);

echo json_encode([
    'status' => 'success',
    'data' => $data,
    'count' => $countRes['countdata'],
    'totalRows' => $totalRows,
    'totalPages' => $totalPages,
    'currentPage' => $page,
    'offset' => $offset
], JSON_UNESCAPED_UNICODE);
