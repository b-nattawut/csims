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

$user_id = $_SESSION['user_id'];

try {

    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // 2. รับค่าจาก Frontend (ผ่าน $_GET)
    $dept_id        = $_GET['filter_department_id'] ?? '';
    $asset_no       = $_GET['filter_computer_asset_no'] ?? '';
    $brand_model    = $_GET['filter_computer_brand_model'] ?? '';
    $serial_no      = $_GET['filter_computer_serial'] ?? '';
    $resp_id        = $_GET['filter_responsible_id'] ?? '';
    $receive_date   = $_GET['filter_receive_date'] ?? '';
    $start_use_date = $_GET['filter_start_use_date'] ?? '';

    // 3. สร้าง Dynamic WHERE (ตาม Logic searchData เป๊ะๆ)
    $where = " WHERE t1.status_delete = 0 ";
    $params = [];

    // กรองสิทธิ์ตามหน่วยงาน (Security & Department Filter)
    if ($user_role !== 'admin') {
        // User ทั่วไป ล็อกบังคับดึงข้อมูลเฉพาะคอมพิวเตอร์ของหน่วยงานตัวเองเท่านั้น
        $where .= " AND t1.department_id = ? ";
        $params[] = $user_dept_id;
    } else {
        // Admin สามารถเลือกฟิลเตอร์กรองตามหน่วยงานที่ต้องการได้
        if (!empty($dept_id)) {
            $where .= " AND t1.department_id = ? ";
            $params[] = $dept_id;
        }
    }

    if (!empty($asset_no)) {
        $where .= " AND t1.computer_asset_no LIKE ? ";
        $params[] = "%$asset_no%";
    }

    if (!empty($brand_model)) {
        $search_words = array_filter(explode(' ', $brand_model));
        foreach ($search_words as $word) {
            $where .= " AND (t1.computer_brand LIKE ? OR t1.computer_model LIKE ?) ";
            $params[] = "%$word%";
            $params[] = "%$word%";
        }
    }

    if (!empty($serial_no)) {
        $where .= " AND t1.computer_serial LIKE ? ";
        $params[] = "%$serial_no%";
    }

    if (!empty($resp_id)) {
        $where .= " AND t1.responsible_id = ? ";
        $params[] = $resp_id;
    }

    if (!empty($receive_date)) {
        $where .= " AND t1.computer_receive_date = ? ";
        $params[] = $receive_date;
    }

    if (!empty($start_use_date)) {
        $where .= " AND t1.computer_start_use_date = ? ";
        $params[] = $start_use_date;
    }

    // 4. Query ข้อมูล (ดึงทั้งหมดที่ตรง Filter - ไม่ใส่ LIMIT)
    $sql = "SELECT 
            t_dept.department_name,
            t1.computer_asset_no,
            t1.computer_brand,
            t1.computer_model,
            t1.computer_serial,
            DATE_FORMAT(t1.computer_receive_date, '%d/%m/%Y') AS receive_date_show,
            DATE_FORMAT(t1.computer_start_use_date, '%d/%m/%Y') AS start_use_date_show, 
            CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS responsible_fullname,
            -- ข้อมูลผู้สร้าง
            CONCAT(c_rank.rank_name, ' ', c_prof.first_name, ' ', c_prof.last_name) AS creator_name,
            DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS created_at,
            -- ข้อมูลผู้แก้ไข
            CONCAT(u_rank.rank_name, ' ', u_prof.first_name, ' ', u_prof.last_name) AS updater_name,
            DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updated_at
        FROM master_computer_list t1 
        LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id
        LEFT JOIN users r_usr ON t1.responsible_id = r_usr.user_id
        LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
        LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
        -- JOIN ผู้สร้าง
        LEFT JOIN user_profile c_prof ON t1.create_by = c_prof.user_id
        LEFT JOIN user_rank c_rank ON c_prof.rank_id = c_rank.rank_id
        -- JOIN ผู้แก้ไข
        LEFT JOIN user_profile u_prof ON t1.update_by = u_prof.user_id
        LEFT JOIN user_rank u_rank ON u_prof.rank_id = u_rank.rank_id
        $where 
        ORDER BY t1.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. สร้าง Spreadsheet
    $spreadsheet = new Spreadsheet();
    // ตั้งฟอนต์พื้นฐาน
    $spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New')->setSize(11);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('รายการเครื่องคอมพิวเตอร์');

    // กำหนดหัวตาราง
    $headers = [
        'A1' => 'ลำดับ',
        'B1' => 'เลขครุภัณฑ์',
        'C1' => 'หน่วยงาน',
        'D1' => 'ยี่ห้อ',
        'E1' => 'รุ่น',
        'F1' => 'Serial No. (S/N)',
        'G1' => 'วันที่รับมอบ/รับเข้า',
        'H1' => 'วันที่เริ่มใช้งาน',
        'I1' => 'ผู้ดูแลเครื่องคอมพิวเตอร์',
        'J1' => 'ผู้สร้างรายการ',
        'K1' => 'วันที่สร้าง',
        'L1' => 'ผู้แก้ไขล่าสุด',
        'M1' => 'วันที่แก้ไขล่าสุด'
    ];

    foreach ($headers as $cell => $text) {
        $sheet->setCellValue($cell, $text);
    }

    // สไตล์หัวตาราง (หนา + พื้นหลังเทา + กึ่งกลาง)
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
    $sheet->getStyle('A1:M1')->applyFromArray($headerStyle);

    // ปรับความกว้างคอลัมน์อัตโนมัติ
    foreach (range('A', 'M') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 6. ใส่ข้อมูล
    $rowNumber = 2;
    foreach ($data as $index => $row) {
        $sheet->setCellValue('A' . $rowNumber, $index + 1);
        $sheet->setCellValue('B' . $rowNumber, $row['computer_asset_no'] ?: '-');
        $sheet->setCellValue('C' . $rowNumber, $row['department_name'] ?: '-');
        $sheet->setCellValue('D' . $rowNumber, $row['computer_brand'] ?: '-');
        $sheet->setCellValue('E' . $rowNumber, $row['computer_model'] ?: '-');
        $sheet->setCellValue('F' . $rowNumber, $row['computer_serial'] ?: '-');
        $sheet->setCellValue('G' . $rowNumber, $row['receive_date_show'] ?: '-');
        $sheet->setCellValue('H' . $rowNumber, $row['start_use_date_show'] ?: '-');
        $sheet->setCellValue('I' . $rowNumber, $row['responsible_fullname'] ?: '-');
        $sheet->setCellValue('J' . $rowNumber, $row['creator_name'] ?: '-');
        $sheet->setCellValue('K' . $rowNumber, $row['created_at'] ?: '-');
        $sheet->setCellValue('L' . $rowNumber, $row['updater_name'] ?: '-');
        $sheet->setCellValue('M' . $rowNumber, $row['updated_at'] ?: '-');

        // จัดกึ่งกลางทั้งแถว และใส่เส้นขอบ
        $currentRange = 'A' . $rowNumber . ':M' . $rowNumber;
        $sheet->getStyle($currentRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle($currentRange)->getBorders()
            ->getAllBorders();
        // ->setBorderStyle(Border::BORDER_THIN);

        $rowNumber++;
    }
    // 7. Output ไฟล์
    $filename = "Master_Computer_List_" . date('Y-m-d_H.i') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
