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

$seqStatus = (int)$incident['seqStatusApprove'];
if ($seqStatus >= 4) {
    rrJsonError('อนุมัติครบ 3 คนแล้ว ไม่สามารถย้อนกลับได้');
}
if ($seqStatus !== 2 && $seqStatus !== 3) {
    rrJsonError('ยังไม่มียอดอนุมัติให้ย้อนกลับ');
}

$approvers = rrGetApprovers($pdo, $incidentId);
$current = $approvers[$seqStatus] ?? null;
if (!$current || (int)$current['user_id'] !== (int)$_SESSION['user_id']) {
    rrJsonError('เฉพาะผู้ตรวจลำดับปัจจุบันเท่านั้นที่ย้อนกลับได้', 403);
}

try {
    $pdo->beginTransaction();

    // เคลียร์เฉพาะผู้ตรวจ 1–3 — คงลายเซ็นผู้ร่าง (seq 0) ไว้
    $clear = $pdo->prepare("UPDATE report_incident_approve
        SET remark = NULL, signature = NULL, dateApprove = NULL
        WHERE id_incident = ? AND seq_no BETWEEN 1 AND 3");
    $clear->execute([$incidentId]);

    $upd = $pdo->prepare("UPDATE rn_ReceiveNoti SET seqStatusApprove = 1, dateStatusApprove = NULL WHERE id = ?");
    $upd->execute([$incidentId]);

    $pdo->commit();
    rrJsonOk(['message' => 'ย้อนกลับและเคลียร์สถานะทั้งหมดเรียบร้อย', 'seqStatusApprove' => 1]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    rrJsonError('ย้อนกลับไม่สำเร็จ: ' . $e->getMessage(), 500);
}
