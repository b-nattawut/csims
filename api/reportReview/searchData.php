<?php
require '../../db_config.php';
require __DIR__ . '/../../helpers/report_no.php';
require __DIR__ . '/_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
rrRequireLogin();

$filter_doc_no = trim($_POST['filter_doc_no'] ?? '');
$filter_report_no = trim($_POST['filter_report_no'] ?? '');
$filter_province = trim($_POST['filter_province'] ?? '');
$filter_station = trim($_POST['filter_station'] ?? '');
$filter_incident_type = trim($_POST['filter_incident_type'] ?? '');

$where = " WHERE t1.statusDelete = 0 AND t2.is_active = 1 AND t1.statusChecklist = 1 ";
$params = [];

if ($filter_doc_no !== '') {
    $where .= " AND (t1.receiveNoti_No_TH LIKE ? OR t1.receiveNoti_No LIKE ?) ";
    $params[] = "%{$filter_doc_no}%";
    $params[] = "%{$filter_doc_no}%";
}
if ($filter_report_no !== '') {
    $where .= " AND (t1.receiveNotiReportNo_TH LIKE ? OR t1.receiveNotiReportNo LIKE ?) ";
    $params[] = "%{$filter_report_no}%";
    $params[] = "%{$filter_report_no}%";
}
if ($filter_province !== '') {
    $where .= " AND t1.provinceID = ? ";
    $params[] = $filter_province;
}
if ($filter_station !== '') {
    $where .= " AND t1.complaints_From = ? ";
    $params[] = $filter_station;
}
if ($filter_incident_type !== '') {
    $where .= " AND t1.complaints_type = ? ";
    $params[] = $filter_incident_type;
}

$limit = 15;
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;
$caseSql = rrComplaintCaseSql();

$sql = "SELECT t1.id, t1.create_by, t1.receiveNoti_No, t1.receiveNoti_No_TH, t1.receiveNotiReportNo, t1.receiveNotiReportNo_TH,
        t1.complaints_From, t1.province, t1.complaints_type,
        COALESCE(t1.seqStatusApprove, 1) AS seqStatusApprove,
        t1.dateStatusApprove,
        CASE WHEN t1.file_report_incident IS NOT NULL AND LENGTH(t1.file_report_incident) > 0 THEN 1 ELSE 0 END AS has_file,
        {$caseSql} AS complaintstype,
        (SELECT COUNT(*) FROM report_incident_approve ax WHERE ax.id_incident = t1.id AND ax.user_id IS NOT NULL AND ax.seq_no BETWEEN 1 AND 3) AS assigned_count,
        (SELECT COUNT(*) FROM report_incident_approve ax WHERE ax.id_incident = t1.id AND ax.dateApprove IS NOT NULL AND ax.seq_no BETWEEN 1 AND 3) AS signed_count
    FROM rn_ReceiveNoti t1
    LEFT JOIN users t2 ON t1.create_by = t2.user_id
    {$where}
    ORDER BY t1.id DESC
    LIMIT {$limit} OFFSET {$offset}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($data as &$row) {
    if (empty($row['receiveNotiReportNo_TH'])) {
        $row['receiveNotiReportNo_TH'] = convertReportNoToThai($row['receiveNotiReportNo'] ?? '');
    }
    if (empty($row['receiveNoti_No_TH'])) {
        $row['receiveNoti_No_TH'] = convertDocNoToThai($row['receiveNoti_No'] ?? '');
    }
    $signed = (int)($row['signed_count'] ?? 0);
    $assigned = (int)($row['assigned_count'] ?? 0);
    $seqStatus = (int)$row['seqStatusApprove'];
    $row['seq_label'] = rrSeqLabel($seqStatus);
    if ($assigned < 3) {
        $row['status_text'] = 'ยังไม่ได้กำหนดผู้ตรวจ';
        $row['status_class'] = 'secondary';
    } elseif ($signed >= 3 || $seqStatus >= 4) {
        $row['status_text'] = 'เซ็นครบ 3/3';
        $row['status_class'] = 'success';
    } else {
        $row['status_text'] = 'เซ็นแล้ว ' . $signed . '/3 (ยังไม่ครบ)';
        $row['status_class'] = 'primary';
    }
}
unset($row);

$sqlCount = "SELECT COUNT(*) AS countdata
    FROM rn_ReceiveNoti t1
    LEFT JOIN users t2 ON t1.create_by = t2.user_id
    {$where}";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();

rrJsonOk([
    'data' => $data,
    'count' => $totalRows,
    'totalPages' => (int)ceil($totalRows / $limit),
    'currentPage' => $page,
    'offset' => $offset,
]);
