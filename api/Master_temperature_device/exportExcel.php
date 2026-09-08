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

    // 2. รับค่า Filter จาก Frontend (ใช้ $_GET เพราะส่งมากับ window.location.href)
    $filter_dept     = $_GET['filter_department_id'] ?? '';
    $filter_code     = $_GET['filter_device_code'] ?? '';
    $filter_location = $_GET['filter_location_use'] ?? '';
    $filter_resp     = $_GET['filter_responsible_id'] ?? '';

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
        if (!empty($filter_dept)) {
            $where .= " AND t1.department_id = ? ";
            $params[] = $filter_dept;
        }
    }

    if (!empty($filter_code)) {
        $where .= " AND t1.device_code LIKE ? ";
        $params[] = "%$filter_code%";
    }

    if (!empty($filter_location)) {
        $where .= " AND t1.location_use LIKE ? ";
        $params[] = "%$filter_location%";
    }

    if (!empty($filter_resp)) {
        $where .= " AND (t1.responsible_person_1 = ? OR t1.responsible_person_2 = ?) ";
        $params[] = $filter_resp;
        $params[] = $filter_resp;
    }

    // 4. Query ข้อมูลหลัก
    $sql = "SELECT 
            t_dept.department_name,
            t1.device_code,
            t1.range_min,
            t1.range_max,
            t1.location_use,
            t1.measurement_uncertainty,
            CONCAT(t4_r1.rank_name,' ',t3_r1.first_name,' ',t3_r1.last_name) AS resp_name_1,
            CONCAT(t4_r2.rank_name,' ',t3_r2.first_name,' ',t3_r2.last_name) AS resp_name_2,
            -- ข้อมูลผู้สร้าง
            CONCAT(c_rank.rank_name, ' ', c_prof.first_name, ' ', c_prof.last_name) AS creator_name,
            DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS created_at,
            -- ข้อมูลผู้แก้ไข
            CONCAT(u_rank.rank_name, ' ', u_prof.first_name, ' ', u_prof.last_name) AS updater_name,
            DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updated_at
        FROM master_temperature_device t1 
        -- JOIN ตารางหน่วยงาน
        LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id
        -- JOIN ผู้รับผิดชอบ 1 & 2
        LEFT JOIN users t2_r1 ON t1.responsible_person_1 = t2_r1.user_id
        LEFT JOIN user_profile t3_r1 ON t2_r1.user_id = t3_r1.user_id
        LEFT JOIN user_rank t4_r1 ON t3_r1.rank_id = t4_r1.rank_id
        LEFT JOIN users t2_r2 ON t1.responsible_person_2 = t2_r2.user_id
        LEFT JOIN user_profile t3_r2 ON t2_r2.user_id = t3_r2.user_id
        LEFT JOIN user_rank t4_r2 ON t3_r2.rank_id = t4_r2.rank_id
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
    $sheet->setTitle('รายการเครื่องวัดอุณหภูมิ');

    // กำหนดหัวตาราง 
    $headers = [
        'A1' => 'ลำดับ',
        'B1' => 'หมายเลขเครื่องวัด',
        'C1' => 'หน่วยงาน',
        'D1' => 'ช่วงการใช้งาน (°C)',
        'E1' => 'สถานที่ใช้งาน',
        'F1' => 'ค่าความไม่แน่นอนของการวัด (°C)',
        'G1' => 'ผู้รับผิดชอบคนที่ 1',
        'H1' => 'ผู้รับผิดชอบคนที่ 2',
        'I1' => 'ผู้สร้างรายการ',
        'J1' => 'วันที่สร้าง',
        'K1' => 'ผู้แก้ไขล่าสุด',
        'L1' => 'วันที่แก้ไขล่าสุด'
    ];

    foreach ($headers as $cell => $text) {
        $sheet->setCellValue($cell, $text);
    }

    // ปรับ Style หัวตาราง (เปลี่ยนเป็น A1:L1)
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
    $sheet->getStyle('A1:L1')->applyFromArray($headerStyle);

    // ปรับความกว้างคอลัมน์อัตโนมัติ (A ถึง L)
    foreach (range('A', 'L') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 6. ใส่ข้อมูลในตาราง พร้อม Logic การแสดงผล
    $rowNumber = 2;
    foreach ($data as $index => $row) {
        // --- จัดการส่วน "ช่วงการใช้งาน" (Range) ---
        $rangeDisplay = ($row['range_min'] !== null && $row['range_max'] !== null) ?
            $row['range_min'] . " - " . $row['range_max'] . " °C" :
            "-";

        // --- จัดการส่วน "ค่าความไม่แน่นอน" (Uncertainty) ---
        $uncertaintyDisplay = ($row['measurement_uncertainty'] !== null) ?
            "± " . $row['measurement_uncertainty'] . " °C" :
            "-";

        $sheet->setCellValue('A' . $rowNumber, $index + 1);
        $sheet->setCellValue('B' . $rowNumber, $row['device_code'] ?: '-');
        $sheet->setCellValue('C' . $rowNumber, $row['department_name'] ?: '-');
        $sheet->setCellValue('D' . $rowNumber, $rangeDisplay); // ใส่ค่าที่รวมแล้ว
        $sheet->setCellValue('E' . $rowNumber, $row['location_use'] ?: '-');
        $sheet->setCellValue('F' . $rowNumber, $uncertaintyDisplay); // ใส่ ± และ °C
        $sheet->setCellValue('G' . $rowNumber, $row['resp_name_1'] ?: '-');
        $sheet->setCellValue('H' . $rowNumber, $row['resp_name_2'] ?: '-');
        $sheet->setCellValue('I' . $rowNumber, $row['creator_name'] ?: '-');
        $sheet->setCellValue('J' . $rowNumber, $row['created_at'] ?: '-');
        $sheet->setCellValue('K' . $rowNumber, $row['updater_name'] ?: '-');
        $sheet->setCellValue('L' . $rowNumber, $row['updated_at'] ?: '-');

        // จัดกึ่งกลาง และใส่เส้นขอบ (ช่วง A ถึง L)
        $range = 'A' . $rowNumber . ':L' . $rowNumber;
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle($range)->getBorders()
            ->getAllBorders();
        // ->setBorderStyle(Border::BORDER_THIN);

        $rowNumber++;
    }

    // 7. สั่ง Download ไฟล์
    $filename = "Master_Temperature_Device_" . date('Y-m-d_H.i') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
