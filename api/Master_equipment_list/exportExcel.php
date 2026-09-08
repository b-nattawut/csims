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
    $department_id  = $_GET['filter_department_id'] ?? '';
    $category_id    = $_GET['filter_equipment_category'] ?? '';
    $asset_no       = $_GET['filter_equipment_asset_no'] ?? '';
    $equipment_name = $_GET['filter_equipment_name'] ?? '';
    $brand_model    = $_GET['filter_equipment_brand_model'] ?? '';
    $serial_no      = $_GET['filter_equipment_serial'] ?? '';
    $install_date   = $_GET['filter_install_date'] ?? '';
    $start_use_date = $_GET['filter_start_use_date'] ?? '';
    $resp_id        = $_GET['filter_responsible_id'] ?? '';

    // 3. สร้าง Dynamic WHERE Query (Logic เดียวกับ searchData)
    $where = " WHERE t1.status_delete = 0 ";
    $params = [];

    // กรองสิทธิ์ตามหน่วยงาน (Security & Department Filter)
    if ($user_role !== 'admin') {
        // User ทั่วไป ล็อกบังคับดึงข้อมูลเฉพาะของหน่วยงานตัวเองเท่านั้น
        $where .= " AND t1.department_id = ? ";
        $params[] = $user_dept_id;
    } else {
        // Admin สามารถเลือกฟิลเตอร์กรองตามหน่วยงานที่ต้องการได้
        if (!empty($department_id)) {
            $where .= " AND t1.department_id = ? ";
            $params[] = $department_id;
        }
    }

    if (!empty($category_id)) {
        $where .= " AND t1.category_id = ? ";
        $params[] = $category_id;
    }
    if (!empty($asset_no)) {
        $where .= " AND t1.asset_no LIKE ? ";
        $params[] = "%$asset_no%";
    }
    if (!empty($equipment_name)) {
        $where .= " AND t1.tool_name LIKE ? ";
        $params[] = "%$equipment_name%";
    }
    if (!empty($brand_model)) {
        $words = array_filter(explode(' ', $brand_model));
        foreach ($words as $word) {
            $where .= " AND (t1.brand LIKE ? OR t1.model LIKE ?) ";
            $params[] = "%$word%";
            $params[] = "%$word%";
        }
    }
    if (!empty($serial_no)) {
        $where .= " AND t1.serial_no LIKE ?";
        $params[] = "%$serial_no%";
    }
    if (!empty($install_date)) {
        $where .= " AND t1.date_receive = ? ";
        $params[] = $install_date;
    }
    if (!empty($start_use_date)) {
        $where .= " AND t1.date_start_use = ? ";
        $params[] = $start_use_date;
    }
    if (!empty($resp_id)) {
        $where .= " AND t1.responsible_id = ? ";
        $params[] = $resp_id;
    }

    // 4. SQL Query (ดึงข้อมูลทั้งหมดที่ผ่าน Filter โดยไม่มี LIMIT)
    $sql = "SELECT 
            t_dept.department_name,
            t5.category_name,
            t1.asset_no,
            t1.tool_name,
            t1.brand,
            t1.model,
            t1.serial_no,
            t1.accessories,
            DATE_FORMAT(t1.date_receive, '%d/%m/%Y') AS date_receive,
            DATE_FORMAT(t1.date_start_use, '%d/%m/%Y') AS date_use, 
            CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS responsible_name,

            -- ข้อมูลผู้สร้าง
            CONCAT(c_rank.rank_name, ' ', c_prof.first_name, ' ', c_prof.last_name) AS creator_name,
            DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS created_at,
            -- ข้อมูลผู้แก้ไข
            CONCAT(u_rank.rank_name, ' ', u_prof.first_name, ' ', u_prof.last_name) AS updater_name,
            DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updated_at

        FROM master_equipment_list t1 
        LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id 
        LEFT JOIN master_equipment_categories t5 ON t1.category_id = t5.id 
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

    // 5. เริ่มต้นสร้าง Excel
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New')->setSize(11);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('รายการเครื่องมือ');

    // กำหนดหัวตาราง
    $headers = [
        'A1' => 'ลำดับ',
        'B1' => 'เลขครุภัณฑ์',
        'C1' => 'หน่วยงาน',
        'D1' => 'ประเภทเครื่องมือ',
        'E1' => 'ชื่อเครื่องมือ',
        'F1' => 'ยี่ห้อ',
        'G1' => 'รุ่น',
        'H1' => 'Serial No. (S/N)',
        'I1' => 'อุปกรณ์ส่วนควบ',
        'J1' => 'วันที่รับมา/ติดตั้ง',
        'K1' => 'วันที่เริ่มใช้งาน',
        'L1' => 'ผู้ดูแลเครื่องมือ',
        'M1' => 'ผู้สร้างรายการ',
        'N1' => 'วันที่สร้าง',
        'O1' => 'ผู้แก้ไขล่าสุด',
        'P1' => 'วันที่แก้ไขล่าสุด'
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
    $sheet->getStyle('A1:P1')->applyFromArray($headerStyle);

    // ปรับความกว้างคอลัมน์อัตโนมัติ
    foreach (range('A', 'P') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 6. ใส่ข้อมูลในตาราง
    $rowNumber = 2;
    foreach ($data as $index => $row) {
        $sheet->setCellValue('A' . $rowNumber, $index + 1);
        $sheet->setCellValue('B' . $rowNumber, $row['asset_no'] ?: '-');
        $sheet->setCellValue('C' . $rowNumber, $row['department_name'] ?: '-');
        $sheet->setCellValue('D' . $rowNumber, $row['category_name'] ?: '-');
        $sheet->setCellValue('E' . $rowNumber, $row['tool_name'] ?: '-');
        $sheet->setCellValue('F' . $rowNumber, $row['brand'] ?: '-');
        $sheet->setCellValue('G' . $rowNumber, $row['model'] ?: '-');
        $sheet->setCellValue('H' . $rowNumber, $row['serial_no'] ?: '-');
        $sheet->setCellValue('I' . $rowNumber, $row['accessories'] ?: '-');
        $sheet->setCellValue('J' . $rowNumber, $row['date_receive'] ?: '-');
        $sheet->setCellValue('K' . $rowNumber, $row['date_use'] ?: '-');
        $sheet->setCellValue('L' . $rowNumber, $row['responsible_name'] ?: '-');
        $sheet->setCellValue('M' . $rowNumber, $row['creator_name'] ?: '-');
        $sheet->setCellValue('N' . $rowNumber, $row['created_at'] ?: '-');
        $sheet->setCellValue('O' . $rowNumber, $row['updater_name'] ?: '-');
        $sheet->setCellValue('P' . $rowNumber, $row['updated_at'] ?: '-');

        // --- การจัดกึ่งกลาง (Alignment) และเส้นขอบ (Borders) ---

        // จัดการทีเดียวตั้งแต่คอลัมน์ A ถึง J ของแถวนั้นๆ
        $sheet->getStyle('A' . $rowNumber . ':P' . $rowNumber)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER) // กึ่งกลางแนวนอน
            ->setVertical(Alignment::VERTICAL_CENTER);   // กึ่งกลางแนวตั้ง

        // ใส่เส้นขอบให้แถวข้อมูล (แก้จุดที่คุณ comment ไว้ให้ทำงานได้จริง)
        $sheet->getStyle('A' . $rowNumber . ':P' . $rowNumber)
            ->getBorders()
            ->getAllBorders();
        // ->setBorderStyle(Border::BORDER_THIN)

        $rowNumber++;
    }

    // 7. สั่ง Download ไฟล์
    $filename = "Master_Equipment_List_" . date('Y-m-d_H.i') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
