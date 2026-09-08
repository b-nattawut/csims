<?php
require '../../db_config.php';
require __DIR__ . '/../../helpers/report_no.php';
require __DIR__ . '/_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
rrRequireLogin();

$incidentId = (int)($_GET['incident_id'] ?? $_POST['incident_id'] ?? 0);
if ($incidentId <= 0) {
    rrJsonError('ไม่พบรหัสคดี');
}

$incident = rrGetIncident($pdo, $incidentId);
if (!$incident) {
    rrJsonError('ไม่พบข้อมูลคดี', 404);
}

if (empty($incident['receiveNotiReportNo_TH'])) {
    $incident['receiveNotiReportNo_TH'] = convertReportNoToThai($incident['receiveNotiReportNo'] ?? '');
}
if (empty($incident['receiveNoti_No_TH'])) {
    $incident['receiveNoti_No_TH'] = convertDocNoToThai($incident['receiveNoti_No'] ?? '');
}

$approvers = rrGetApprovers($pdo, $incidentId);
$sessionUserId = (int)$_SESSION['user_id'];
$seqStatus = (int)$incident['seqStatusApprove'];
$isCreator = ((int)$incident['create_by'] === $sessionUserId);
$isLocked = ($seqStatus >= 4);

$anyReviewerSigned = false;
foreach ([1, 2, 3] as $s) {
    if (!empty($approvers[$s]['dateApprove'])) {
        $anyReviewerSigned = true;
        break;
    }
}

$canAssign = $isCreator && !$isLocked && !$anyReviewerSigned;
$canUpload = $isCreator && !$isLocked;

$canApproveSeq = 0;
if (!$isLocked && $seqStatus >= 1 && $seqStatus <= 3 && isset($approvers[$seqStatus]) && $approvers[$seqStatus]) {
    if ((int)$approvers[$seqStatus]['user_id'] === $sessionUserId && empty($approvers[$seqStatus]['dateApprove'])) {
        $canApproveSeq = $seqStatus;
    }
}

$canRollback = false;
if (($seqStatus === 2 || $seqStatus === 3) && isset($approvers[$seqStatus]) && $approvers[$seqStatus]) {
    $canRollback = ((int)$approvers[$seqStatus]['user_id'] === $sessionUserId);
}

// signature preview (base64) รวมผู้ร่าง seq 0
foreach ([0, 1, 2, 3] as $seq) {
    if (!$approvers[$seq]) {
        continue;
    }
    $approvers[$seq]['signature_data'] = null;
    if ((int)$approvers[$seq]['has_signature'] === 1) {
        $stmtSig = $pdo->prepare("SELECT signature FROM report_incident_approve WHERE approve_id = ? LIMIT 1");
        $stmtSig->execute([(int)$approvers[$seq]['approve_id']]);
        $sig = $stmtSig->fetchColumn();
        if ($sig) {
            $approvers[$seq]['signature_data'] = 'data:image/png;base64,' . base64_encode($sig);
        }
    }
}

rrJsonOk([
    'incident' => $incident,
    'approvers' => $approvers,
    'seq_label' => rrSeqLabel($seqStatus),
    'session_user_id' => $sessionUserId,
    'is_creator' => $isCreator,
    'is_locked' => $isLocked,
    'can_assign' => $canAssign,
    'can_upload' => $canUpload,
    'can_approve_seq' => $canApproveSeq,
    'can_rollback' => $canRollback,
]);
