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

    // 2. รับค่าจาก Frontend (ใช้ $_GET เพราะส่งมากับ window.location.href)
    $filter_dept_id  = $_GET['filter_department_id'] ?? '';
    $chemical_name   = $_GET['filter_chemical_name'] ?? '';
    $chemical_brand  = $_GET['filter_chemical_brand'] ?? '';
    $chemical_detail = $_GET['filter_chemical_detail'] ?? '';

    // 3. สร้าง Dynamic WHERE Query (ตาม searchData เป๊ะๆ)
    $where = " WHERE t1.status_delete = 0 ";
    $params = [];

    // ควบคุมสิทธิ์การมองเห็นข้อมูลตามหน่วยงาน (Security & Department Filter)
    if ($user_role !== 'admin') {
        // User ทั่วไป บังคับดึงเฉพาะข้อมูลของหน่วยงานตัวเองเท่านั้น (เพิกเฉยต่อ filter จากหน้าบ้าน)
        $where .= " AND t1.department_id = ? ";
        $params[] = $user_dept_id;
    } else {
        // Admin สามารถเลือกฟิลเตอร์กรองตามหน่วยงานที่ต้องการได้
        if (!empty($filter_dept_id)) {
            $where .= " AND t1.department_id = ? ";
            $params[] = $filter_dept_id;
        }
    }

    if (!empty($chemical_name)) {
        $where .= " AND t1.chemical_name LIKE ? ";
        $params[] = "%$chemical_name%";
    }

    if (!empty($chemical_brand)) {
        $where .= " AND t1.chemical_brand LIKE ? ";
        $params[] = "%$chemical_brand%";
    }

    if (!empty($chemical_detail)) {
        $where .= " AND t1.chemical_detail LIKE ? ";
        $params[] = "%$chemical_detail%";
    }

    // 4. Query ข้อมูลหลัก
    $sql = "SELECT 
            t_dept.department_name,
            t1.chemical_name,
            t1.chemical_brand,
            t1.chemical_detail,
            -- ข้อมูลหน่วยนับ
            t2.unit_name_th,
            t2.unit_name_en,
            t2.unit_symbol,
            t2.unit_group,
            -- ข้อมูลผู้สร้าง
            CONCAT(t4.rank_name, ' ', t3.first_name, ' ', t3.last_name) AS fullname_create,
            DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS createdate,
            -- ข้อมูลผู้แก้ไขล่าสุด
            CONCAT(t6.rank_name, ' ', t5.first_name, ' ', t5.last_name) AS fullname_update,
            DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updatedate
        FROM master_chemical_list t1 
        LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id
        LEFT JOIN master_chemical_units t2 ON t1.unit_id = t2.id
        LEFT JOIN user_profile t3 ON t1.create_by = t3.user_id
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        LEFT JOIN user_profile t5 ON t1.update_by = t5.user_id
        LEFT JOIN user_rank t6 ON t5.rank_id = t6.rank_id
        $where 
        ORDER BY t1.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. เริ่มต้นสร้าง Excel
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New')->setSize(11);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('รายการสารเคมี');

    // กำหนดหัวตาราง
    $headers = [
        'A1' => 'ลำดับ',
        'B1' => 'ชื่อสารเคมี',
        'C1' => 'หน่วยงาน',
        'D1' => 'ยี่ห้อ/ผู้ผลิต',
        'E1' => 'หน่วยนับหลัก',
        'F1' => 'รายละเอียด',
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
            'startColor' => ['rgb' => 'D3D3D3'],
        ],
        // 'borders' => [
        //     'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        // ],
    ];
    $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

    // ปรับความกว้างคอลัมน์อัตโนมัติ
    foreach (range('A', 'J') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 6. ใส่ข้อมูลในตาราง
    $rowNumber = 2;
    foreach ($data as $index => $row) {

        $unitDisplay = '-';
        if (!empty($row['unit_name_th'])) {
            // เช็กกลุ่มเพื่อเลือกตัวย่อ หรือ ชื่ออังกฤษ
            $isPackaging = (strpos($row['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($row['unit_group'], 'Packaging') !== false);
            $symbol = $isPackaging ? $row['unit_name_en'] : $row['unit_symbol'];
            $unitDisplay = $row['unit_name_th'] . " (" . $symbol . ")";
        }

        $sheet->setCellValue('A' . $rowNumber, $index + 1);
        $sheet->setCellValue('B' . $rowNumber, $row['chemical_name'] ?: '-');
        $sheet->setCellValue('C' . $rowNumber, $row['department_name'] ?: '-');
        $sheet->setCellValue('D' . $rowNumber, $row['chemical_brand'] ?: '-');
        $sheet->setCellValue('E' . $rowNumber, $unitDisplay);
        $sheet->setCellValue('F' . $rowNumber, $row['chemical_detail'] ?: '-');
        $sheet->setCellValue('G' . $rowNumber, $row['fullname_create'] ?: '-');
        $sheet->setCellValue('H' . $rowNumber, $row['createdate'] ?: '-');
        $sheet->setCellValue('I' . $rowNumber, $row['fullname_update'] ?: '-');
        $sheet->setCellValue('J' . $rowNumber, $row['updatedate'] ?: '-');

        // จัดกึ่งกลาง และใส่เส้นขอบทั้งแถว (A ถึง J)
        $range = 'A' . $rowNumber . ':J' . $rowNumber;
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle($range)->getBorders()
            ->getAllBorders();
        // ->setBorderStyle(Border::BORDER_THIN);

        $rowNumber++;
    }

    // 7. สั่ง Download ไฟล์
    $filename = "Master_Chemical_List_" . date('Y-m-d_H.i') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
