<?php
require '../../db_config.php';
require __DIR__ . '/_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
rrRequireLogin();

$incidentId = (int)($_POST['incident_id'] ?? 0);
if ($incidentId <= 0) {
    rrJsonError('ไม่พบรหัสคดี');
}

$incident = rrGetIncident($pdo, $incidentId);
if (!$incident) {
    rrJsonError('ไม่พบข้อมูลคดี', 404);
}
if ((int)$incident['create_by'] !== (int)$_SESSION['user_id']) {
    rrJsonError('เฉพาะคนสร้างร่างเท่านั้นที่อัปโหลดไฟล์ได้', 403);
}
if ((int)$incident['seqStatusApprove'] >= 4) {
    rrJsonError('อนุมัติครบแล้ว ไม่สามารถแก้ไขไฟล์ได้');
}
if (empty($_FILES['report_file']['tmp_name'])) {
    rrJsonError('กรุณาเลือกไฟล์รายงาน');
}

$file = $_FILES['report_file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    rrJsonError('อัปโหลดไฟล์ไม่สำเร็จ');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']) ?: '';
$allowed = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
if (!in_array($mime, $allowed, true)) {
    rrJsonError('รองรับเฉพาะไฟล์ PDF หรือ DOC/DOCX');
}

$binary = file_get_contents($file['tmp_name']);
if ($binary === false || $binary === '') {
    rrJsonError('อ่านไฟล์ไม่สำเร็จ');
}

$stmt = $pdo->prepare("UPDATE rn_ReceiveNoti SET file_report_incident = ? WHERE id = ?");
$stmt->execute([$binary, $incidentId]);

rrJsonOk(['message' => 'อัปโหลดไฟล์รายงานเรียบร้อย', 'has_file' => 1]);
