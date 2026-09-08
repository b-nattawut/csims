<?php
require '../../db_config.php';
require __DIR__ . '/_helpers.php';

rrRequireLogin();

$incidentId = (int)($_GET['incident_id'] ?? 0);
if ($incidentId <= 0) {
    http_response_code(400);
    echo 'ไม่พบรหัสคดี';
    exit;
}

$stmt = $pdo->prepare("SELECT receiveNotiReportNo, file_report_incident
    FROM rn_ReceiveNoti
    WHERE id = ? AND statusDelete = 0
    LIMIT 1");
$stmt->execute([$incidentId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['file_report_incident'])) {
    http_response_code(404);
    echo 'ไม่พบไฟล์รายงาน';
    exit;
}

$blob = $row['file_report_incident'];
$mime = 'application/pdf';
if (strncmp($blob, '%PDF', 4) === 0) {
    $mime = 'application/pdf';
    $ext = 'pdf';
} elseif (strncmp($blob, 'PK', 2) === 0) {
    $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    $ext = 'docx';
} else {
    $mime = 'application/msword';
    $ext = 'doc';
}

$reportNo = preg_replace('/[^A-Za-z0-9_\-ก-๙]/u', '_', (string)($row['receiveNotiReportNo'] ?? 'report'));
$filename = 'report_' . $reportNo . '.' . $ext;

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($blob));
echo $blob;
exit;
