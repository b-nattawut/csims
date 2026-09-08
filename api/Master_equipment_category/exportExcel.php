<?php
session_start();

// 1. ตั้งค่าการแสดง Error และความปลอดภัย
ini_set('display_errors', 0); 
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

// 2. รับค่าจาก Frontend (Filter)
$category_name = $_GET['filter_category_name'] ?? '';
$created_by    = $_GET['filter_created_by'] ?? '';
$usage_status  = $_GET['filter_usage_status'] ?? ''; 

// 3. สร้าง Dynamic WHERE Query
$where = " WHERE t1.status_delete = 0 ";
$params = [];

if (!empty($category_name)) {
    $where .= " AND t1.category_name LIKE ? ";
    $params[] = "%$category_name%";
}

if (!empty($created_by)) {
    $where .= " AND t1.created_by = ? ";
    $params[] = $created_by;
}

if ($usage_status === 'active') {
    $where .= " AND (SELECT COUNT(id) FROM master_equipment_list WHERE category_id = t1.id AND status_delete = 0) > 0 ";
} elseif ($usage_status === 'empty') {
    $where .= " AND (SELECT COUNT(id) FROM master_equipment_list WHERE category_id = t1.id AND status_delete = 0) = 0 ";
}

// 4. SQL Query 
// ดึงข้อมูลหลัก + Subquery นับจำนวนเครื่องมือ + ข้อมูล Audit
$sql = "SELECT 
            t1.id, 
            t1.category_name,
            
            -- นับจำนวนเครื่องมือที่ใช้ Category นี้
            (SELECT COUNT(m.id) FROM master_equipment_list m 
             WHERE m.category_id = t1.id AND m.status_delete = 0) AS total_usage,

            -- ข้อมูลผู้บันทึก/ผู้สร้าง
            CONCAT(IFNULL(c_rank.rank_name,''), ' ', c_prof.first_name, ' ', c_prof.last_name) AS creator_name,
            DATE_FORMAT(t1.created_at, '%d/%m/%Y') AS created_date_only,
            DATE_FORMAT(t1.created_at, '%d/%m/%Y %H:%i') AS created_at_full,

            -- ข้อมูลผู้แก้ไขล่าสุด
            CONCAT(IFNULL(u_rank.rank_name,''), ' ', u_prof.first_name, ' ', u_prof.last_name) AS updater_name,
            DATE_FORMAT(t1.updated_at, '%d/%m/%Y %H:%i') AS updated_at_full

        FROM master_equipment_categories t1 
        LEFT JOIN user_profile c_prof ON t1.created_by = c_prof.user_id
        LEFT JOIN user_rank c_rank ON c_prof.rank_id = c_rank.rank_id
        LEFT JOIN user_profile u_prof ON t1.updated_by = u_prof.user_id
        LEFT JOIN user_rank u_rank ON u_prof.rank_id = u_rank.rank_id
        
        $where 
        ORDER BY t1.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. เริ่มต้นสร้าง Excel
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New')->setSize(11);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('ประเภทเครื่องมือ');

    // กำหนดหัวตาราง 
    $headers = [
        'A1' => 'ลำดับ',
        'B1' => 'ชื่อประเภทเครื่องมือ',
        'C1' => 'จำนวนเครื่องมือที่ใช้งาน',
        'D1' => 'ผู้บันทึก',
        'E1' => 'วันที่เพิ่มรายการ',
        'F1' => 'ผู้สร้างรายการ',
        'G1' => 'วันที่สร้าง',
        'H1' => 'ผู้แก้ไขล่าสุด',
        'I1' => 'วันที่แก้ไขล่าสุด'
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
            'startColor' => ['rgb' => 'E9ECEF'],
        ],
        // 'borders' => [
        //     'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        // ],
    ];
    $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

    // 6. ใส่ข้อมูลในตาราง
    $rowNumber = 2;
    foreach ($data as $index => $row) {
        $sheet->setCellValue('A' . $rowNumber, $index + 1);
        $sheet->setCellValue('B' . $rowNumber, $row['category_name']);
        $sheet->setCellValue('C' . $rowNumber, ($row['total_usage'] ?: 0));
        $sheet->setCellValue('D' . $rowNumber, $row['creator_name'] ?: '-');
        $sheet->setCellValue('E' . $rowNumber, $row['created_date_only'] ?: '-');
        
        // Audit Fields
        $sheet->setCellValue('F' . $rowNumber, $row['creator_name'] ?: '-');
        $sheet->setCellValue('G' . $rowNumber, $row['created_at_full'] ?: '-');
        $sheet->setCellValue('H' . $rowNumber, $row['updater_name'] ?: '-');
        $sheet->setCellValue('I' . $rowNumber, $row['updated_at_full'] ?: '-');

        // จัด Alignment
        $sheet->getStyle('A' . $rowNumber . ':I' . $rowNumber)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // ใส่เส้นขอบ
        $sheet->getStyle('A' . $rowNumber . ':I' . $rowNumber)
            ->getBorders()
            ->getAllBorders();
            // ->setBorderStyle(Border::BORDER_THIN);

        $rowNumber++;
    }

    // ปรับความกว้างคอลัมน์อัตโนมัติ
    foreach (range('A', 'I') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 7. สั่ง Download ไฟล์
    $filename = "Equipment_Categories_" . date('Y-m-d_H.i') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}