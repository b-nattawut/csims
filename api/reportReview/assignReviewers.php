<?php
require '../../db_config.php';
require __DIR__ . '/_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
rrRequireLogin();

$incidentId = (int)($_POST['incident_id'] ?? 0);
$user1 = (int)($_POST['reviewer1_id'] ?? 0);
$user2 = (int)($_POST['reviewer2_id'] ?? 0);
$user3 = (int)($_POST['approver_id'] ?? 0);
$signatureRaw = $_POST['signature'] ?? '';

if ($incidentId <= 0 || $user1 <= 0 || $user2 <= 0 || $user3 <= 0) {
    rrJsonError('กรุณาเลือกผู้ตรวจร่างรายงานครบทั้ง 3 คน');
}
if (count(array_unique([$user1, $user2, $user3])) < 3) {
    rrJsonError('ผู้ตรวจแต่ละลำดับต้องเป็นคนละคน');
}
if ($signatureRaw === '') {
    rrJsonError('กรุณาลงลายเซ็นผู้ร่างรายงาน');
}

try {
    $incident = rrGetIncident($pdo, $incidentId);
} catch (Throwable $e) {
    rrJsonError('โหลดข้อมูลคดีไม่สำเร็จ: ' . $e->getMessage());
}
if (!$incident) {
    rrJsonError('ไม่พบข้อมูลคดี');
}
if ((int)$incident['create_by'] !== (int)$_SESSION['user_id']) {
    rrJsonError('เฉพาะคนสร้างร่างรายการนี้เท่านั้นที่กำหนดผู้ตรวจได้');
}
if ((int)$incident['seqStatusApprove'] >= 4) {
    rrJsonError('อนุมัติครบแล้ว ไม่สามารถแก้ไขได้');
}

// ห้ามเปลี่ยนคนถ้ามีคนเซ็นไปแล้ว (ลำดับ 1–3) — ต้องย้อนกลับก่อน
$approvers = rrGetApprovers($pdo, $incidentId);
foreach ([1, 2, 3] as $seq) {
    $row = $approvers[$seq] ?? null;
    if ($row && !empty($row['dateApprove'])) {
        rrJsonError('มีการเซ็นไปแล้ว กรุณาย้อนกลับก่อนจึงจะเปลี่ยนผู้ตรวจได้');
    }
}

$users = [
    1 => rrGetUserMeta($pdo, $user1),
    2 => rrGetUserMeta($pdo, $user2),
    3 => rrGetUserMeta($pdo, $user3),
];
foreach ($users as $seq => $u) {
    if (!$u) {
        rrJsonError("ไม่พบข้อมูลผู้ใช้ลำดับ {$seq}");
    }
}

$creatorMeta = rrGetUserMeta($pdo, (int)$_SESSION['user_id']);
if (!$creatorMeta) {
    rrJsonError('ไม่พบข้อมูลผู้ร่างรายงาน');
}

try {
    $sigBin = rrDecodeDataUrl($signatureRaw);
    $now = rrNowBangkok();

    $pdo->beginTransaction();

    $del = $pdo->prepare("DELETE FROM report_incident_approve WHERE id_incident = ?");
    $del->execute([$incidentId]);

    // seq 0 = ผู้ร่าง (เซ็นตอนบันทึก)
    $insDrafter = $pdo->prepare("INSERT INTO report_incident_approve
        (id_incident, user_id, position_name, remark, seq_no, dateApprove, signature)
        VALUES (?, ?, ?, NULL, 0, ?, ?)");
    $insDrafter->execute([
        $incidentId,
        (int)$creatorMeta['user_id'],
        $creatorMeta['position_name'] ?? '',
        $now,
        $sigBin,
    ]);

    $ins = $pdo->prepare("INSERT INTO report_incident_approve
        (id_incident, user_id, position_name, remark, seq_no, dateApprove, signature)
        VALUES (?, ?, ?, NULL, ?, NULL, NULL)");

    foreach ($users as $seq => $u) {
        $ins->execute([
            $incidentId,
            (int)$u['user_id'],
            $u['position_name'] ?? '',
            $seq,
        ]);
    }

    $upd = $pdo->prepare("UPDATE rn_ReceiveNoti SET seqStatusApprove = 1, dateStatusApprove = NULL WHERE id = ?");
    $upd->execute([$incidentId]);

    $pdo->commit();
    rrJsonOk(['message' => 'บันทึกผู้ตรวจร่างรายงานและลายเซ็นผู้ร่างเรียบร้อย']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    rrJsonError('บันทึกไม่สำเร็จ: ' . $e->getMessage(), 500);
}
