<?php
/**
 * API: Field_visit_log/getDataByID.php
 * ดึงข้อมูลบันทึกภาคสนามตาม ID
 */
session_start();
require '../../db_config.php';
require __DIR__ . '/../../helpers/report_no.php';
header("Content-Type: application/json; charset=UTF-8");

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = $_GET['id'] ?? $_POST['id'] ?? '';
if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // เพิ่มคอลumn report_no_TH ถ้ายังไม่มี
    try {
        $pdo->query("SELECT report_no_TH FROM field_visit_logs LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE field_visit_logs ADD COLUMN report_no_TH VARCHAR(50) NULL AFTER report_no");
        } catch (PDOException $e2) { /* ignore */ }
    }

    $sql = "SELECT 
                fvl.*,
                mps.station_name,
                CASE fvl.province_id
                    WHEN 95 THEN 'ยะลา'
                    WHEN 94 THEN 'ปัตตานี'
                    WHEN 96 THEN 'นราธิวาส'
                    ELSE ''
                END AS province_name,
                CONCAT(IFNULL(ur.rank_name,''),' ',IFNULL(up.first_name,''),' ',IFNULL(up.last_name,'')) AS recorder_name
            FROM field_visit_logs fvl
            LEFT JOIN master_police_station mps ON fvl.station_id = mps.id
            LEFT JOIN user_profile up ON fvl.create_by = up.user_id
            LEFT JOIN user_rank ur ON up.rank_id = ur.rank_id
            WHERE fvl.id = ? AND fvl.status_delete = 0";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Decode BLOB → string
    $row['photos']      = $row['photos'] ? json_decode($row['photos'], true) : [];
    $row['attachments'] = $row['attachments'] ? json_decode($row['attachments'], true) : [];
    $row['report_no_display'] = !empty($row['report_no_TH'])
        ? $row['report_no_TH']
        : smartThaiReportOrDoc($row['report_no'] ?? '');

    echo json_encode(['status' => 'success', 'data' => $row], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
