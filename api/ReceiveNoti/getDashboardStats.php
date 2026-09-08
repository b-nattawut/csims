<?php
/**
 * API: getDashboardStats.php
 * ดึงข้อมูลสถิติสำหรับ Dashboard Overview
 * 
 * Response:
 * - pieChart: สถิติคดีแยกตามจังหวัด (ยะลา, นราธิวาส, ปัตตานี)
 * - tableData: สถิติคดีแยกตามจังหวัดและประเภทคดี
 * - columnChart: สถิติคดีแยกตามประเภทคดี
 */

session_start();
ini_set('display_errors', 0);
error_reporting(0);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

try {
    // ============================================
    // Single Query - ดึงสถิติคดีแยกตามจังหวัดและประเภทคดี
    // ============================================
    $sqlTable = "SELECT 
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '01') AS yala_wealth,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '02') AS yala_life,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '03') AS yala_bom,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '04') AS yala_fire,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '05') AS yala_traffic,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '06') AS yala_evidence,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '07') AS yala_crime_scene,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '08') AS yala_evidence_person,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '09') AS yala_other,
        
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '01') AS narateewat_wealth,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '02') AS narateewat_life,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '03') AS narateewat_bom,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '04') AS narateewat_fire,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '05') AS narateewat_traffic,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '06') AS narateewat_evidence,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '07') AS narateewat_crime_scene,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '08') AS narateewat_evidence_person,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '09') AS narateewat_other,
        
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '01') AS pattanee_wealth,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '02') AS pattanee_life,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '03') AS pattanee_bom,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '04') AS pattanee_fire,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '05') AS pattanee_traffic,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '06') AS pattanee_evidence,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '07') AS pattanee_crime_scene,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '08') AS pattanee_evidence_person,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '09') AS pattanee_other
        FROM rn_ReceiveNoti";
    
    $stmtTable = $pdo->prepare($sqlTable);
    $stmtTable->execute();
    $tableRaw = $stmtTable->fetch(PDO::FETCH_ASSOC);
    
    // แปลงข้อมูลเป็น format ที่ใช้งานง่าย
    $colKeys = ['LC', 'MD', 'BM', 'FR', 'TF', 'EV', 'CM', 'PS', 'OT'];
    $colMap = ['LC' => 'wealth', 'MD' => 'life', 'BM' => 'bom', 'FR' => 'fire', 'TF' => 'traffic', 'EV' => 'evidence', 'CM' => 'crime_scene', 'PS' => 'evidence_person', 'OT' => 'other'];
    $provinceKeys = ['yala' => 'ยะลา', 'pattanee' => 'ปัตตานี', 'narateewat' => 'นราธิวาส'];

    $tableData = [];
    $totalData = array_fill_keys($colKeys, 0);

    foreach ($provinceKeys as $dbPrefix => $provinceName) {
        $row = ['province' => $provinceName];
        foreach ($colKeys as $code) {
            $val = (int)($tableRaw[$dbPrefix . '_' . $colMap[$code]] ?? 0);
            $row[$code] = $val;
            $totalData[$code] += $val;
        }
        $tableData[] = $row;
    }

    // Pie chart: คำนวณจาก tableData
    $pieChart = [];
    foreach ($tableData as $row) {
        $pieChart[] = ['province' => $row['province'], 'count' => array_sum(array_intersect_key($row, array_flip($colKeys)))];
    }
    
    // ============================================
    // Column Chart - สถิติคดีแยกตามประเภท
    // ============================================
    $colLabels = ['LC' => 'คดีทรัพย์', 'MD' => 'คดีชีวิต', 'BM' => 'คดีระเบิด', 'FR' => 'คดีเพลิงไหม้', 'TF' => 'คดีจราจร', 'EV' => 'ตรวจเก็บวัตถุพยาน', 'CM' => 'ตรวจเก็บที่เกิดเหตุ', 'PS' => 'ตรวจเก็บบุคคล', 'OT' => 'อื่นๆ'];
    $columnChart = [];
    foreach ($colKeys as $code) {
        $columnChart[] = ['type' => $colLabels[$code], 'count' => $totalData[$code], 'code' => $code];
    }
    
    // ============================================
    // Response
    // ============================================
    echo json_encode([
        'status' => 'success',
        'data' => [
            'pieChart' => $pieChart,
            'tableData' => $tableData,
            'totalData' => $totalData,
            'columnChart' => $columnChart
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดภายในระบบ'
    ], JSON_UNESCAPED_UNICODE);
}
?>
