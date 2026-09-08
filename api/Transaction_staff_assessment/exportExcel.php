<?php
session_start();

// 1. ตั้งค่าการแสดง Error และความปลอดภัย
ini_set('display_errors', 0); // ปิดใน Production แต่เปิด 1 เพื่อ Debug
error_reporting(E_ALL);

require '../../db_config.php';
require '../../vendor/autoload.php';
require __DIR__ . '/../../helpers/report_no.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
// use PhpOffice\PhpSpreadsheet\Style\Border;

// เช็คว่า Login หรือยัง? (Security Check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน (Unauthorized)'
    ]);
    exit;
}

// 2. รับค่า Filter จาก Frontend 
$filter_report_no  = $_GET['filter_report_no'] ?? '';
$filter_location   = $_GET['filter_location'] ?? '';
$filter_case_scope = $_GET['filter_case_scope'] ?? '';
$filter_status     = $_GET['filter_status'] ?? '';
$filter_evaluator  = $_GET['filter_evaluator'] ?? '';
$filter_date_start = $_GET['filter_date_start'] ?? '';
$filter_date_end   = $_GET['filter_date_end'] ?? '';

// นิยาม SQL สำหรับคำนวณจำนวน (Logic เดียวกับ searchData)
$sql_active_roles = "(IF(t1.leader_id > 0, 1, 0) + IF(t1.photographer_id > 0, 1, 0) + IF(t1.map_maker_id > 0, 1, 0) + IF(t1.searcher_id > 0, 1, 0) + IF(t1.collector_id > 0, 1, 0))";
$sql_evaluated_count = "(SELECT COUNT(id) FROM staff_assessment_results WHERE header_id = t1.id)";

// 3. สร้าง Dynamic WHERE Query
$where = " WHERE t1.delete_token = 0 ";
$params = [];

if (!empty($filter_report_no)) {
    $where .= " AND t1.report_no LIKE ? ";
    $params[] = "%$filter_report_no%";
}
if (!empty($filter_location)) {
    $where .= " AND t1.location LIKE ? ";
    $params[] = "%$filter_location%";
}
if (!empty($filter_case_scope)) {
    $where .= " AND t1.case_scope = ? ";
    $params[] = $filter_case_scope;
}

// Logic การกรองสถานะแบบ Virtual Status
if (!empty($filter_status)) {
    if ($filter_status === 'COMPLETED') {
        $where .= " AND t1.status = 'COMPLETED' ";
    } elseif ($filter_status === 'WAITING_SIGN') {
        $where .= " AND t1.status = 'DRAFT' AND $sql_evaluated_count >= $sql_active_roles AND $sql_active_roles > 0 ";
    } elseif ($filter_status === 'IN_PROGRESS') {
        $where .= " AND t1.status = 'DRAFT' AND ($sql_evaluated_count < $sql_active_roles OR $sql_active_roles = 0) ";
    }
}

if (!empty($filter_evaluator)) {
    $where .= " AND t1.evaluator_id = ? ";
    $params[] = $filter_evaluator;
}
if (!empty($filter_date_start)) {
    $where .= " AND t1.operation_date >= ? ";
    $params[] = $filter_date_start;
}
if (!empty($filter_date_end)) {
    $where .= " AND t1.operation_date <= ? ";
    $params[] = $filter_date_end;
}

// 4. Query ข้อมูลหลัก (ดึงทั้งหมดที่ตรงเงื่อนไข - ตัด LIMIT ออก)
$sql = "SELECT 
        t1.report_no,
        t1.location,
        t1.case_scope,
        t1.status,
        DATE_FORMAT(t1.operation_date, '%d/%m/%Y') as operation_date_show,
        $sql_active_roles as total_active_staff,
        $sql_evaluated_count as evaluated_count,
        -- ผู้ประเมิน
        CONCAT(IFNULL(t4.rank_name,''), ' ', t3.first_name, ' ', t3.last_name) AS evaluator_fullname,
        -- ข้อมูลผู้สร้าง 
        CONCAT(IFNULL(c_rank.rank_name,''), ' ', c_prof.first_name, ' ', c_prof.last_name) AS creator_name,
        DATE_FORMAT(t1.created_at, '%d/%m/%Y %H:%i') AS created_at,
        -- ข้อมูลผู้แก้ไขล่าสุด
        CONCAT(IFNULL(u_rank.rank_name,''), ' ', u_prof.first_name, ' ', u_prof.last_name) AS updater_name,
        DATE_FORMAT(t1.updated_at, '%d/%m/%Y %H:%i') AS updated_at
    FROM staff_assessment_header t1 
    LEFT JOIN users t2 ON t1.evaluator_id = t2.user_id
    LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
    LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
    -- JOIN ผู้สร้าง 
    LEFT JOIN user_profile c_prof ON t1.created_by = c_prof.user_id
    LEFT JOIN user_rank c_rank ON c_prof.rank_id = c_rank.rank_id
    -- JOIN ผู้แก้ไข
    LEFT JOIN user_profile u_prof ON t1.updated_by = u_prof.user_id
    LEFT JOIN user_rank u_rank ON u_prof.rank_id = u_rank.rank_id
    $where 
    ORDER BY t1.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. เริ่มสร้าง Spreadsheet
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New')->setSize(11);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('รายการประเมินผู้ปฏิบัติหน้าที่');

    // กำหนดหัวตาราง
    $headers = [
        'A1' => 'ลำดับ',
        'B1' => 'วันที่ดำเนินการ',
        'C1' => 'เลขรายงาน',
        'D1' => 'ที่ตั้ง/สถานที่ดำเนินการ',
        'E1' => 'ขอบข่ายที่ตรวจ',
        'F1' => 'ผู้ประเมิน',
        'G1' => 'สถานะ',
        'H1' => 'ผู้สร้างรายการ',
        'I1' => 'วันที่สร้าง',
        'J1' => 'ผู้แก้ไขล่าสุด',
        'K1' => 'วันที่แก้ไขล่าสุด'
    ];

    foreach ($headers as $cell => $text) {
        $sheet->setCellValue($cell, $text);
    }

    // ตกแต่งหัวตาราง
    $headerStyle = [
        'font' => ['bold' => true],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D3D3D3'],
        ],
        // 'borders' => [
        //     'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        // ],
    ];
    $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

    // 6. ใส่ข้อมูลในตาราง
    $rowNumber = 2;

    $scopeMap = [
        'PROPERTY'  => 'คดีทรัพย์',
        'LIFE'      => 'คดีชีวิต',
        'EXPLOSIVE' => 'คดีระเบิด'
    ];

    foreach ($data as $index => $row) {
        $statusText = '';
        $totalStaff = (int)$row['total_active_staff'];
        $evaluated = (int)$row['evaluated_count'];

        if ($row['status'] === 'COMPLETED') {
            $statusText = 'เสร็จสมบูรณ์';
        } elseif ($evaluated >= $totalStaff && $totalStaff > 0) {
            $statusText = 'รอลงนามตรวจสอบ';
        } else {
            $statusText = "รอประเมิน ($evaluated/$totalStaff)";
        }

        $caseScopeText = $scopeMap[$row['case_scope']] ?? $row['case_scope'];

        $sheet->setCellValue('A' . $rowNumber, $index + 1);
        $sheet->setCellValue('B' . $rowNumber, $row['operation_date_show'] ?: "-");
        $sheet->setCellValue('C' . $rowNumber, !empty($row['report_no']) ? convertReportNoToThai($row['report_no']) : "-");
        $sheet->setCellValue('D' . $rowNumber, $row['location'] ?: "-");
        $sheet->setCellValue('E' . $rowNumber, $caseScopeText ?: "-");
        $sheet->setCellValue('F' . $rowNumber, $row['evaluator_fullname'] ?: "-");
        $sheet->setCellValue('G' . $rowNumber, $statusText ?: "-");
        $sheet->setCellValue('H' . $rowNumber, $row['creator_name'] ?: "-");
        $sheet->setCellValue('I' . $rowNumber, $row['created_at'] ?: "-");
        $sheet->setCellValue('J' . $rowNumber, $row['updater_name'] ?: "-");
        $sheet->setCellValue('K' . $rowNumber, $row['updated_at'] ?: "-");

        // จัดรูปแบบกึ่งกลางและเส้นขอบทั้งแถว (A ถึง K)
        $range = 'A' . $rowNumber . ':K' . $rowNumber;
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle($range)->getBorders()
            ->getAllBorders();
        // ->setBorderStyle(Border::BORDER_THIN);

        $rowNumber++;
    }

    // 7. ปรับความกว้างคอลัมน์
    foreach (range('A', 'K') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 8. สั่ง Download ไฟล์
    $filename = "Staff_Assessment_Report_" . date('Y-m-d_H.i') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
