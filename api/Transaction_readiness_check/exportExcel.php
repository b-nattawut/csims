<?php
session_start();

// 1. ตั้งค่าการแสดง Error และความปลอดภัย
ini_set('display_errors', 0); // ปิดใน Production แต่เปิด 1 เพื่อ Debug
error_reporting(E_ALL);

require '../../db_config.php';
require '../../vendor/autoload.php';

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

// 2. รับค่า Filter จาก Frontend (ใช้ $_GET สำหรับ Export)
$filter_date_start = $_GET['filter_date_start'] ?? '';
$filter_date_end   = $_GET['filter_date_end'] ?? '';
$filter_team_no    = $_GET['filter_team_no'] ?? '';
$filter_readiness  = $_GET['filter_readiness'] ?? ''; 
$filter_status     = $_GET['filter_status'] ?? '';
$filter_checked_by = $_GET['filter_checked_by'] ?? '';

// 3. สร้าง Dynamic WHERE Query (เหมือนใน searchData)
$where = " WHERE t1.delete_token = 0 ";
$params = [];

if (!empty($filter_date_start)) {
    $where .= " AND t1.check_date >= ? ";
    $params[] = $filter_date_start;
}
if (!empty($filter_date_end)) {
    $where .= " AND t1.check_date <= ? ";
    $params[] = $filter_date_end;
}
if (!empty($filter_team_no)) {
    $where .= " AND t1.team_no = ? ";
    $params[] = $filter_team_no;
}
if (!empty($filter_readiness)) {
    $where .= " AND t1.team_readiness_status = ? ";
    $params[] = $filter_readiness;
}
if (!empty($filter_status)) {
    if ($filter_status === 'COMPLETED') {
        $where .= " AND t1.status = 'COMPLETED' ";
    } elseif ($filter_status === 'WAITING_SIGN') {
        $where .= " AND t1.status = 'DRAFT' 
                    AND (SELECT COUNT(DISTINCT category) FROM trans_readiness_check_results WHERE header_id = t1.id) = 4 ";
    } elseif ($filter_status === 'IN_PROGRESS') {
        $where .= " AND t1.status = 'DRAFT' 
                    AND (SELECT COUNT(DISTINCT category) FROM trans_readiness_check_results WHERE header_id = t1.id) < 4 ";
    }
}
if (!empty($filter_checked_by)) {
    $where .= " AND t1.checked_by = ? ";
    $params[] = $filter_checked_by;
}

// 4. Query ข้อมูลหลัก
$sql = "SELECT 
            t1.id, 
            DATE_FORMAT(t1.check_date, '%d/%m/%Y') as check_date_show,
            TIME_FORMAT(t1.check_time, '%H:%i') as check_time_show,
            t1.team_no,
            t1.status,
            t1.team_readiness_status,
            (SELECT COUNT(DISTINCT category) FROM trans_readiness_check_results WHERE header_id = t1.id) as checked_count,
            -- เจ้าหน้าที่ผู้ตรวจ
            CONCAT(IFNULL(r_rank.rank_name,''), ' ', r_prof.first_name, ' ', r_prof.last_name) AS inspector_fullname,
            -- ข้อมูลผู้สร้าง
            CONCAT(IFNULL(c_rank.rank_name,''), ' ', c_prof.first_name, ' ', c_prof.last_name) AS creator_name,
            DATE_FORMAT(t1.created_at, '%d/%m/%Y %H:%i') AS created_at,
            -- ข้อมูลผู้แก้ไข
            CONCAT(IFNULL(u_rank.rank_name,''), ' ', u_prof.first_name, ' ', u_prof.last_name) AS updater_name,
            DATE_FORMAT(t1.updated_at, '%d/%m/%Y %H:%i') AS updated_at
        FROM trans_readiness_check_header t1 
        LEFT JOIN user_profile r_prof ON t1.checked_by = r_prof.user_id
        LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
        -- JOIN ผู้สร้าง 
        LEFT JOIN user_profile c_prof ON t1.created_by = c_prof.user_id
        LEFT JOIN user_rank c_rank ON c_prof.rank_id = c_rank.rank_id
        -- JOIN ผู้แก้ไข 
        LEFT JOIN user_profile u_prof ON t1.updated_by = u_prof.user_id
        LEFT JOIN user_rank u_rank ON u_prof.rank_id = u_rank.rank_id
        $where 
        ORDER BY t1.check_date DESC, t1.check_time DESC, t1.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. เริ่มสร้าง Spreadsheet
    $spreadsheet = new Spreadsheet();
    // ตั้งค่า Font พื้นฐาน
    $spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New')->setSize(11);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('รายงานการตรวจความพร้อม');

    // กำหนดหัวตาราง (Headers)
    $headers = [
        'A1' => 'ลำดับ',
        'B1' => 'วันที่ตรวจ',
        'C1' => 'ทีมตรวจ',
        'D1' => 'ความพร้อมเจ้าหน้าที่',
        'E1' => 'เจ้าหน้าที่ผู้ตรวจ',
        'F1' => 'สถานะ',
        'G1' => 'ผู้สร้างรายการ',
        'H1' => 'วันที่สร้าง',
        'I1' => 'ผู้แก้ไขล่าสุด',
        'J1' => 'วันที่แก้ไขล่าสุด'
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
            'startColor' => ['rgb' => 'D3D3D3'], // สีน้ำเงินเข้ม
        ],
        // 'borders' => [
        //     'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        // ],
    ];
    $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

    // 6. ใส่ข้อมูลในตาราง
    $rowNumber = 2;
    foreach ($data as $index => $row) {

        $dateTimeShow = trim($row['check_date_show'] . ' ' . ($row['check_time_show'] ?: ''));
        if (empty($dateTimeShow)) $dateTimeShow = "-";

        // แปลงความพร้อมคน
        $personReadiness = ($row['team_readiness_status'] === 'COMPLETE') ? 'ครบ' : 'ไม่ครบ';

        // ปรับการแสดงผลสถานะใน Excel ให้ตรงตาม Logic หน้าเว็บ
        $statusText = '';
        if ($row['status'] === 'COMPLETED') {
            $statusText = 'เสร็จสมบูรณ์';
        } elseif ($row['checked_count'] == 4) {
            $statusText = 'รอลงนามรับรอง';
        } else {
            $statusText = 'รอดำเนินการ (' . $row['checked_count'] . '/4)';
        }

        $sheet->setCellValue('A' . $rowNumber, $index + 1);
        $sheet->setCellValue('B' . $rowNumber, $dateTimeShow);
        $sheet->setCellValue('C' . $rowNumber, "ทีมที่ " . $row['team_no'] ?: "-");
        $sheet->setCellValue('D' . $rowNumber, $personReadiness ?: "-");
        $sheet->setCellValue('E' . $rowNumber, $row['inspector_fullname'] ?: "-");
        $sheet->setCellValue('F' . $rowNumber, $statusText ?: "-");
        $sheet->setCellValue('G' . $rowNumber, $row['creator_name'] ?: "-");
        $sheet->setCellValue('H' . $rowNumber, $row['created_at'] ?: "-");
        $sheet->setCellValue('I' . $rowNumber, $row['updater_name'] ?: "-");
        $sheet->setCellValue('J' . $rowNumber, $row['updated_at'] ?: "-");

        // จัดรูปแบบแถวข้อมูล (เส้นขอบและจัดวาง)
        $range = 'A' . $rowNumber . ':J' . $rowNumber;
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($range)->getBorders()->getAllBorders();
        // ->setBorderStyle(Border::BORDER_THIN);

        $rowNumber++;
    }

    // 7. ปรับความกว้างคอลัมน์อัตโนมัติ
    foreach (range('A', 'J') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 8. สั่ง Download ไฟล์
    $filename = "Readiness_Check_Report_" . date('Y-m-d_H.i') . ".xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
