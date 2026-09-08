<?php
require '../../db_config.php';
require __DIR__ . '/_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
rrRequireLogin();

$incidentId = (int)($_POST['incident_id'] ?? 0);
$seqNo = (int)($_POST['seq_no'] ?? 0);
$remark = trim($_POST['remark'] ?? '');
$signatureRaw = $_POST['signature'] ?? '';

if ($incidentId <= 0 || !in_array($seqNo, [1, 2, 3], true)) {
    rrJsonError('ข้อมูลไม่ครบ');
}
if ($signatureRaw === '') {
    rrJsonError('กรุณาลงลายเซ็น');
}

$incident = rrGetIncident($pdo, $incidentId);
if (!$incident) {
    rrJsonError('ไม่พบข้อมูลคดี', 404);
}

$seqStatus = (int)$incident['seqStatusApprove'];
if ($seqStatus >= 4) {
    rrJsonError('อนุมัติครบแล้ว ไม่สามารถแก้ไขได้');
}
if ($seqStatus !== $seqNo) {
    rrJsonError('ยังไม่ถึงลำดับการตรวจของคุณ (สถานะปัจจุบัน: ' . rrSeqLabel($seqStatus) . ')');
}

$approvers = rrGetApprovers($pdo, $incidentId);
$row = $approvers[$seqNo] ?? null;
if (!$row) {
    rrJsonError('ยังไม่ได้กำหนดผู้ตรวจลำดับนี้');
}
if ((int)$row['user_id'] !== (int)$_SESSION['user_id']) {
    rrJsonError('คุณไม่มีสิทธิ์เซ็นในลำดับนี้', 403);
}
if (!empty($row['dateApprove'])) {
    rrJsonError('ลำดับนี้เซ็นไปแล้ว');
}

try {
    $sigBin = rrDecodeDataUrl($signatureRaw);
    $now = rrNowBangkok();

    $pdo->beginTransaction();

    $upd = $pdo->prepare("UPDATE report_incident_approve
        SET remark = ?, signature = ?, dateApprove = ?
        WHERE approve_id = ? AND id_incident = ? AND seq_no = ? AND user_id = ?");
    $upd->execute([
        $remark,
        $sigBin,
        $now,
        (int)$row['approve_id'],
        $incidentId,
        $seqNo,
        (int)$_SESSION['user_id'],
    ]);

    $nextSeq = $seqNo + 1; // 2,3,4
    $updHead = $pdo->prepare("UPDATE rn_ReceiveNoti SET seqStatusApprove = ?, dateStatusApprove = ? WHERE id = ?");
    $updHead->execute([$nextSeq, $now, $incidentId]);

    $pdo->commit();
    rrJsonOk([
        'message' => 'ยืนยันผลการตรวจสอบเรียบร้อย',
        'seqStatusApprove' => $nextSeq,
        'seq_label' => rrSeqLabel($nextSeq),
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    rrJsonError('บันทึกไม่สำเร็จ: ' . $e->getMessage(), 500);
}
