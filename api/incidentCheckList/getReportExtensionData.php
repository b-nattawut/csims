<?php
/**
 * API: getReportExtensionData.php
 * ดึงข้อมูลขอขยายเวลาการออกรายงาน
 */
require '../../db_config.php';
session_start();
header("Content-Type: application/json; charset=UTF-8");

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$incidentId = isset($_GET['incident_id']) ? (int)$_GET['incident_id'] : 0;

if ($incidentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ไม่พบ incident_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT incident_extend_time_data, incident_report_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incidentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // ดึงข้อมูลจาก rn_ReceiveNoti (สถานที่เกิดเหตุ, ประเภทเหตุ)
    $stmtRn = $pdo->prepare("SELECT location_crime, complaints_type, receiveNotiReportNo_TH FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
    $stmtRn->execute([$incidentId]);
    $rnRow = $stmtRn->fetch(PDO::FETCH_ASSOC);

    $incidentInfo = null;
    if ($rnRow) {
        $typeMap = [
            '01' => 'ทรัพย์', '02' => 'ชีวิต', '03' => 'ระเบิด', '04' => 'เพลิงไหม้',
            '05' => 'จราจร', '06' => 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)',
            '07' => 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ', '08' => 'ตรวจเก็บวัตถุพยานบุคคล', '09' => 'อื่น ๆ'
        ];
        $incidentInfo = [
            'incident_location' => $rnRow['location_crime'] ?? '',
            'complaints_type' => $rnRow['complaints_type'] ?? '',
            'complaints_type_name' => $typeMap[$rnRow['complaints_type']] ?? '',
            'report_no' => $rnRow['receiveNotiReportNo_TH'] ?? ''
        ];
    }

    $reportData = null;
    // decode report_data เฉพาะเมื่อยังไม่มี extend_time_data (ใช้เป็น fallback เท่านั้น)
    if ($row && empty($row['incident_extend_time_data']) && !empty($row['incident_report_data'])) {
        $reportData = json_decode($row['incident_report_data'], true);
    }

    if ($row && !empty($row['incident_extend_time_data'])) {
        $data = json_decode($row['incident_extend_time_data'], true);
        echo json_encode(['success' => true, 'data' => $data, 'report_data' => $reportData, 'incident_info' => $incidentInfo], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => true, 'data' => null, 'report_data' => $reportData, 'incident_info' => $incidentInfo], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล'], JSON_UNESCAPED_UNICODE);
}
