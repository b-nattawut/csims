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

    // 2. รับค่า Filter จาก Frontend 
    $plan_year   = $_GET['filter_plan_year'] ?? '';
    $group_name  = $_GET['filter_group_name'] ?? '';
    $department_id = $_GET['filter_department_id'] ?? '';

    // 3. สร้าง Dynamic WHERE Query
    $where = " WHERE t1.delete_token = 0 ";
    $params = [];

    // ควบคุมสิทธิ์การมองเห็นข้อมูลตามหน่วยงาน (Security & Department Filter)
    if ($user_role !== 'admin') {
        // User ทั่วไป บังคับดึงเฉพาะข้อมูลของหน่วยงานตัวเองเท่านั้น (เพิกเฉยต่อ filter จากหน้าบ้าน)
        $where .= " AND t1.department_id = ? ";
        $params[] = $user_dept_id;
    } else {
        // Admin สามารถเลือกฟิลเตอร์กรองตามหน่วยงานที่ต้องการได้
        if (!empty($department_id)) {
            $where .= " AND t1.department_id = ? ";
            $params[] = $department_id;
        }
    }

    if (!empty($plan_year)) {
        $where .= " AND t1.plan_year = ? ";
        $params[] = $plan_year;
    }

    if (!empty($group_name)) {
        $where .= " AND t1.group_name LIKE ? ";
        $params[] = "%$group_name%";
    }

    // 4. Query ข้อมูลหลัก 
    $sql = "SELECT 
            t1.plan_year,
            t1.group_name,
            md.department_name, 
            -- ข้อมูลผู้สร้าง
            CONCAT(IFNULL(t4.rank_name,''), ' ', t3.first_name, ' ', t3.last_name) AS creator_name,
            DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS created_at,
            -- ข้อมูลผู้แก้ไขล่าสุด 
            CONCAT(IFNULL(u_rank.rank_name,''), ' ', u_prof.first_name, ' ', u_prof.last_name) AS updater_name,
            DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updated_at
        FROM trans_maintenance_plan_header t1 
        -- JOIN หาชื่อหน่วยงาน
        LEFT JOIN master_departments md ON t1.department_id = md.id
        -- JOIN ผู้สร้าง
        LEFT JOIN user_profile t3 ON t1.create_by = t3.user_id
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        -- JOIN ผู้แก้ไขล่าสุด
        LEFT JOIN user_profile u_prof ON t1.update_by = u_prof.user_id
        LEFT JOIN user_rank u_rank ON u_prof.rank_id = u_rank.rank_id
        $where 
        ORDER BY t1.plan_year DESC, t1.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. เริ่มต้นสร้าง Excel
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New')->setSize(11);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('แผนการซ่อมบำรุงสอบเทียบประจำปี');

    // กำหนดหัวตาราง
    $headers = [
        'A1' => 'ลำดับ',
        'B1' => 'ปีแผนงาน',
        'C1' => 'กลุ่มงาน',
        'D1' => 'หน่วยงาน',
        'E1' => 'ผู้สร้างรายการ',
        'F1' => 'วันที่สร้าง',
        'G1' => 'ผู้แก้ไขล่าสุด',
        'H1' => 'วันที่แก้ไขล่าสุด'
    ];

    foreach ($headers as $cell => $text) {
        $sheet->setCellValue($cell, $text);
    }

    // ตกแต่งหัวตาราง (หนา + พื้นหลังเทา + กึ่งกลาง)
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
    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

    // 6. ใส่ข้อมูลในตาราง
    $rowNumber = 2;
    foreach ($data as $index => $row) {

        $sheet->setCellValue('A' . $rowNumber, $index + 1);
        $sheet->setCellValue('B' . $rowNumber, $row['plan_year'] ?: '-');
        $sheet->setCellValue('C' . $rowNumber, $row['group_name'] ?: '-');
        $sheet->setCellValue('D' . $rowNumber, $row['department_name'] ?: '-');
        $sheet->setCellValue('E' . $rowNumber, $row['creator_name'] ?: '-');
        $sheet->setCellValue('F' . $rowNumber, $row['created_at'] ?: '-');
        $sheet->setCellValue('G' . $rowNumber, $row['updater_name'] ?: '-');
        $sheet->setCellValue('H' . $rowNumber, $row['updated_at'] ?: '-');

        // จัดกึ่งกลาง และใส่เส้นขอบทั้งแถว (A ถึง H)
        $range = 'A' . $rowNumber . ':H' . $rowNumber;
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle($range)->getBorders()
            ->getAllBorders();
        // ->setBorderStyle(Border::BORDER_THIN);

        $rowNumber++;
    }

    // 7. ปรับความกว้างคอลัมน์อัตโนมัติ
    foreach (range('A', 'H') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 8. สั่ง Download ไฟล์
    $filename = "Maintenance_Plan_Report_" . date('Y-m-d_H.i') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
