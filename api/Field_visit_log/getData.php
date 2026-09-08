<?php
/**
 * API: Field_visit_log/getData.php
 * ดึงรายการบันทึกภาคสนาม (สำหรับตาราง + filter)
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

// รับ filter
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to   = $_GET['date_to'] ?? '';
$filter_province  = $_GET['province'] ?? '';
$filter_station   = $_GET['station'] ?? '';
$filter_case      = $_GET['case_type'] ?? '';
$filter_recorder  = $_GET['recorder'] ?? '';

try {
    $where  = ["fvl.status_delete = 0"];
    $params = [];

    if (!empty($filter_date_from)) {
        $where[]  = "fvl.visit_date >= ?";
        $params[] = $filter_date_from;
    }
    if (!empty($filter_date_to)) {
        $where[]  = "fvl.visit_date <= ?";
        $params[] = $filter_date_to;
    }
    if (!empty($filter_province)) {
        $where[]  = "fvl.province_id = ?";
        $params[] = $filter_province;
    }
    if (!empty($filter_station)) {
        $where[]  = "fvl.station_id = ?";
        $params[] = $filter_station;
    }
    if (!empty($filter_case)) {
        $where[]  = "fvl.case_type_id = ?";
        $params[] = $filter_case;
    }
    if (!empty($filter_recorder)) {
        $where[]  = "CONCAT(IFNULL(ur.rank_name,''),' ',IFNULL(up.first_name,''),' ',IFNULL(up.last_name,'')) LIKE ?";
        $params[] = '%' . $filter_recorder . '%';
    }

    $whereStr = implode(' AND ', $where);

    // เพิ่มคอลumn report_no_TH ถ้ายังไม่มี
    try {
        $pdo->query("SELECT report_no_TH FROM field_visit_logs LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE field_visit_logs ADD COLUMN report_no_TH VARCHAR(50) NULL AFTER report_no");
        } catch (PDOException $e2) { /* ignore */ }
    }

    $sql = "SELECT 
                fvl.id,
                fvl.report_no,
                fvl.report_no_TH,
                fvl.visit_date,
                fvl.station_id,
                fvl.province_id,
                fvl.case_type_id,
                fvl.location,
                fvl.description,
                fvl.create_by,
                fvl.create_date,
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
            WHERE {$whereStr}
            ORDER BY fvl.create_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['report_no_display'] = !empty($row['report_no_TH'])
            ? $row['report_no_TH']
            : smartThaiReportOrDoc($row['report_no'] ?? '');
    }
    unset($row);

    echo json_encode([
        'status' => 'success',
        'data'   => $rows,
        'total'  => count($rows)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
