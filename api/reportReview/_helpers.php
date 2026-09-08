<?php

function rrRequireLogin(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function rrJsonOk(array $payload = []): void
{
    echo json_encode(array_merge(['status' => 'success'], $payload), JSON_UNESCAPED_UNICODE);
    exit;
}

function rrJsonError(string $message, int $httpCode = 200): void
{
    // business error ใช้ HTTP 200 เพื่อให้ frontend อ่าน message ได้ง่าย
    // ใช้ 401 เฉพาะกรณีไม่ได้ login
    if ($httpCode === 401) {
        http_response_code(401);
    }
    echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function rrComplaintCaseSql(): string
{
    return "CASE WHEN t1.complaints_type = '01' THEN 'ทรัพย์'
                 WHEN t1.complaints_type = '02' THEN 'ชีวิต'
                 WHEN t1.complaints_type = '03' THEN 'ระเบิด'
                 WHEN t1.complaints_type = '04' THEN 'เพลิงไหม้'
                 WHEN t1.complaints_type = '05' THEN 'จราจร'
                 WHEN t1.complaints_type = '06' THEN 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)'
                 WHEN t1.complaints_type = '07' THEN 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ'
                 WHEN t1.complaints_type = '08' THEN 'ตรวจเก็บวัตถุพยานบุคคล'
                 ELSE CONCAT('ไม่ทราบ (', t1.complaints_type, ')') END";
}

function rrGetIncident(PDO $pdo, int $incidentId): ?array
{
    $stmt = $pdo->prepare("SELECT id, create_by, receiveNoti_No, receiveNoti_No_TH, receiveNotiReportNo, receiveNotiReportNo_TH,
            complaints_From, province, complaints_type,
            COALESCE(seqStatusApprove, 1) AS seqStatusApprove,
            dateStatusApprove,
            CASE WHEN file_report_incident IS NOT NULL AND LENGTH(file_report_incident) > 0 THEN 1 ELSE 0 END AS has_file
        FROM rn_ReceiveNoti
        WHERE id = ? AND statusDelete = 0
        LIMIT 1");
    $stmt->execute([$incidentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function rrGetApprovers(PDO $pdo, int $incidentId): array
{
    $stmt = $pdo->prepare("SELECT a.approve_id, a.id_incident, a.user_id, a.position_name, a.remark, a.seq_no, a.dateApprove,
            CASE WHEN a.signature IS NOT NULL AND LENGTH(a.signature) > 0 THEN 1 ELSE 0 END AS has_signature,
            TRIM(CONCAT(COALESCE(r.short_rank, r.rank_name, ''), ' ', COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))) AS fullname
        FROM report_incident_approve a
        LEFT JOIN user_profile p ON p.user_id = a.user_id
        LEFT JOIN user_rank r ON p.rank_id = r.rank_id
        WHERE a.id_incident = ?
        ORDER BY a.seq_no ASC");
    $stmt->execute([$incidentId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // seq 0 = ผู้ร่างรายงาน, 1–3 = ผู้ตรวจ/ผู้อนุมัติ
    $map = [0 => null, 1 => null, 2 => null, 3 => null];
    foreach ($rows as $row) {
        $seq = (int)$row['seq_no'];
        if ($seq >= 0 && $seq <= 3) {
            $row['fullname'] = trim(preg_replace('/\s+/', ' ', (string)($row['fullname'] ?? '')));
            $map[$seq] = $row;
        }
    }
    return $map;
}

function rrGetUserMeta(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT t1.user_id,
            TRIM(CONCAT(COALESCE(t4.short_rank, t4.rank_name, ''), ' ', COALESCE(t2.first_name, ''), ' ', COALESCE(t2.last_name, ''))) AS fullname,
            t3.position_name
        FROM users t1
        LEFT JOIN user_profile t2 ON t1.user_id = t2.user_id
        LEFT JOIN user_position t3 ON t2.position_id = t3.position_id
        LEFT JOIN user_rank t4 ON t2.rank_id = t4.rank_id
        WHERE t1.user_id = ? AND t1.is_active = 1
        LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['fullname'] = trim(preg_replace('/\s+/', ' ', (string)($row['fullname'] ?? '')));
    return $row;
}

function rrDecodeDataUrl(string $dataUrl): string
{
    if (strpos($dataUrl, 'base64,') !== false) {
        $dataUrl = substr($dataUrl, strpos($dataUrl, 'base64,') + 7);
    }
    $dataUrl = str_replace(' ', '+', $dataUrl);
    $bin = base64_decode($dataUrl, true);
    if ($bin === false || $bin === '') {
        throw new RuntimeException('ลายเซ็นไม่ถูกต้อง');
    }
    return $bin;
}

function rrSeqLabel(int $seqStatus): string
{
    switch ($seqStatus) {
        case 1: return 'รอผู้ตรวจร่างรายงาน 1';
        case 2: return 'รอผู้ตรวจร่างรายงาน 2';
        case 3: return 'รอผู้อนุมัติรายงาน';
        case 4: return 'อนุมัติครบแล้ว';
        default: return 'รอดำเนินการ';
    }
}

function rrNowBangkok(): string
{
    return (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');
}
