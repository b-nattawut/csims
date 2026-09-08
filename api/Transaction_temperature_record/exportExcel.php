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

    $user_role    = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // 2. รับค่า Filter จาก Frontend
    $filter_dept_id  = $_GET['filter_department_id'] ?? '';
    $filter_code     = $_GET['filter_device_code'] ?? '';
    $filter_location = $_GET['filter_location_use'] ?? '';
    $filter_resp     = $_GET['filter_responsible_id'] ?? '';

    // 3. สร้าง Dynamic WHERE
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

    // 4. SQL Query (ดึงข้อมูลทั้งหมด + บันทึกล่าสุด - ตัด LIMIT ออก)
    $sql = "SELECT 
        t_dept.department_name,
        t1.device_code,
        t1.range_min,
        t1.range_max,
        t1.location_use,
        t1.measurement_uncertainty,
        CONCAT(t4_r1.rank_name,' ',t3_r1.first_name,' ',t3_r1.last_name) AS resp1_name,
        CONCAT(t4_r2.rank_name,' ',t3_r2.first_name,' ',t3_r2.last_name) AS resp2_name,
        DATE_FORMAT(DATE_ADD(last_rec.record_date, INTERVAL 543 YEAR), '%d/%m/%Y') AS last_record_date,
        last_rec.temp_value AS last_temp_value,
        last_rec.status AS last_status,
        -- ข้อมูลผู้สร้าง
        CONCAT(c_rank.rank_name, ' ', c_prof.first_name, ' ', c_prof.last_name) AS creator_name,
        DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS created_at,
        -- ข้อมูลผู้แก้ไข
        CONCAT(u_rank.rank_name, ' ', u_prof.first_name, ' ', u_prof.last_name) AS updater_name,
        DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updated_at
    FROM master_temperature_device t1 
    -- JOIN ข้อมูลหน่วยงาน
    LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id
    
    LEFT JOIN users t2_r1 ON t1.responsible_person_1 = t2_r1.user_id
    LEFT JOIN user_profile t3_r1 ON t2_r1.user_id = t3_r1.user_id
    LEFT JOIN user_rank t4_r1 ON t3_r1.rank_id = t4_r1.rank_id
    LEFT JOIN users t2_r2 ON t1.responsible_person_2 = t2_r2.user_id
    LEFT JOIN user_profile t3_r2 ON t2_r2.user_id = t3_r2.user_id
    LEFT JOIN user_rank t4_r2 ON t3_r2.rank_id = t4_r2.rank_id
    -- JOIN ข้อมูลบันทึกล่าสุด
    LEFT JOIN (
        SELECT tr1.device_id, tr1.record_date, tr1.temp_value, tr1.status
        FROM trans_temperature_record tr1
        INNER JOIN (
            SELECT device_id, MAX(id) AS max_id
            FROM trans_temperature_record
            WHERE delete_token = 0
            GROUP BY device_id
        ) tr2 ON tr1.id = tr2.max_id
    ) last_rec ON t1.id = last_rec.device_id
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

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New')->setSize(11);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('รายการบันทึกการควบคุมอุณหภูมิ');

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
        'I1' => 'วันที่บันทึกล่าสุด',
        'J1' => 'อุณหภูมิล่าสุด (°C)',
        'K1' => 'ผู้สร้างรายการ',
        'L1' => 'วันที่สร้าง',
        'M1' => 'ผู้แก้ไขล่าสุด',
        'N1' => 'วันที่แก้ไขล่าสุด'
    ];

    foreach ($headers as $cell => $text) {
        $sheet->setCellValue($cell, $text);
    }

    // สไตล์หัวตาราง
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
    $sheet->getStyle('A1:N1')->applyFromArray($headerStyle);

    // 6. ใส่ข้อมูล
    $rowNumber = 2;
    foreach ($data as $index => $row) {
        // จัดการช่วงการใช้งาน
        $rangeDisplay = ($row['range_min'] !== null && $row['range_max'] !== null) ?
            $row['range_min'] . " - " . $row['range_max'] . " °C" : "-";

        // จัดการค่าความไม่แน่นอน
        $uncertaintyDisplay = ($row['measurement_uncertainty'] !== null) ?
            "± " . $row['measurement_uncertainty'] . " °C" : "-";

        $sheet->setCellValue('A' . $rowNumber, $index + 1);
        $sheet->setCellValue('B' . $rowNumber, $row['device_code'] ?: '-');
        $sheet->setCellValue('C' . $rowNumber, $row['department_name'] ?: '-');
        $sheet->setCellValue('D' . $rowNumber, $rangeDisplay);
        $sheet->setCellValue('E' . $rowNumber, $row['location_use'] ?: '-');
        $sheet->setCellValue('F' . $rowNumber, $uncertaintyDisplay);
        $sheet->setCellValue('G' . $rowNumber, $row['resp1_name'] ?: '-');
        $sheet->setCellValue('H' . $rowNumber, $row['resp2_name'] ?: '-');
        $sheet->setCellValue('I' . $rowNumber, $row['last_record_date'] ?: 'ไม่มีข้อมูล');
        $sheet->setCellValue('J' . $rowNumber, $row['last_temp_value'] !== null ? $row['last_temp_value'] . " °C" : "-");
        $sheet->setCellValue('K' . $rowNumber, $row['creator_name'] ?: '-');
        $sheet->setCellValue('L' . $rowNumber, $row['created_at'] ?: '-');
        $sheet->setCellValue('M' . $rowNumber, $row['updater_name'] ?: '-');
        $sheet->setCellValue('N' . $rowNumber, $row['updated_at'] ?: '-');

        // จัดกึ่งกลางและเส้นขอบ (A ถึง N)
        $range = 'A' . $rowNumber . ':N' . $rowNumber;
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($range)->getBorders()->getAllBorders();
        // ->setBorderStyle(Border::BORDER_THIN);

        $rowNumber++;
    }

    // 7. AutoSize คอลัมน์
    foreach (range('A', 'N') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 8. ดาวน์โหลดไฟล์
    $filename = "Temperature_Record_Report_" . date('Y-m-d_H.i') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
