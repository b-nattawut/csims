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
    $dept_id      = $_GET['filter_department_id'] ?? '';
    $chem_name    = $_GET['filter_chem_name'] ?? '';
    $chem_brand   = $_GET['filter_chem_brand'] ?? '';
    $lot_number   = $_GET['filter_lot_number'] ?? '';
    $location     = $_GET['filter_location'] ?? '';
    $stock_status = $_GET['filter_stock_status'] ?? '';

    // 3. ตั้งค่าเงื่อนไขเริ่มต้น (ตาม searchData เป๊ะๆ)
    $where = " WHERE t2.status_delete = 0 ";
    $params = [];

    // ควบคุมสิทธิ์การมองเห็นข้อมูลตามหน่วยงาน (Security & Department Filter)
    if ($user_role !== 'admin') {
        // User ทั่วไป บังคับดึงเฉพาะข้อมูลของหน่วยงานตัวเองเท่านั้น (เพิกเฉยต่อ filter จากหน้าบ้าน)
        $where .= " AND t2.department_id = ? ";
        $params[] = $user_dept_id;
    } else {
        // Admin สามารถเลือกฟิลเตอร์กรองตามหน่วยงานที่ต้องการได้
        if (!empty($dept_id)) { 
            $where .= " AND t2.department_id = ? ";
            $params[] = $dept_id;
        }
    }

    if (!empty($chem_name)) {
        $where .= " AND (t2.chemical_name LIKE ?) ";
        $params[] = "%$chem_name%";
    }
    if (!empty($chem_brand)) {
        $where .= " AND t2.chemical_brand LIKE ? ";
        $params[] = "%$chem_brand%";
    }
    if (!empty($lot_number)) {
        $where .= " AND t2.id IN (SELECT chemical_id FROM chemical_inventory WHERE lot_number LIKE ? AND status_delete = 0) ";
        $params[] = "%$lot_number%";
    }
    if (!empty($location)) {
        $where .= " AND t2.id IN (SELECT chemical_id FROM chemical_inventory WHERE location_stored LIKE ? AND status_delete = 0) ";
        $params[] = "%$location%";
    }

    // 4. จัดการเรื่องสถานะสต็อก (HAVING)
    $having = "";
    if ($stock_status == 'in_stock') {
        $having = " HAVING total_remaining > 0 ";
    } elseif ($stock_status == 'out_of_stock') {
        $having = " HAVING total_remaining <= 0 OR total_remaining IS NULL ";
    } elseif ($stock_status == 'near_expiry') {
        $today = date('Y-m-d');
        $having = " HAVING nearest_expiry <= DATE_ADD('$today', INTERVAL 90 DAY) AND nearest_expiry >= '$today' ";
    }

    // 5. เตรียม SQL สำหรับคำนวณสต็อก
    $today = date('Y-m-d');
    $sql = "SELECT 
                t2.chemical_name, 
                t2.chemical_brand, 
                t_dept.department_name, 
                -- ข้อมูลหน่วยนับ
                t3.unit_name_th, t3.unit_name_en, t3.unit_symbol, t3.unit_group,
                GROUP_CONCAT(DISTINCT t1.location_stored SEPARATOR ', ') AS locations_summary,
                -- ยอดรวมทางกายภาพ
                SUM(CASE WHEN t1.status_delete = 0 THEN t1.quantity_remaining ELSE 0 END) AS total_physical,
                -- ยอดรวมที่ใช้งานได้จริง (ยังไม่หมดอายุ)
                SUM(CASE WHEN t1.status_delete = 0 AND t1.expiry_date >= '$today' THEN t1.quantity_remaining ELSE 0 END) AS total_remaining,
                -- วันหมดอายุที่ใกล้ที่สุด
                MIN(CASE WHEN t1.status_delete = 0 AND t1.quantity_remaining > 0 THEN t1.expiry_date ELSE NULL END) AS nearest_expiry,
                -- ปริมาณคงเหลือของล็อตที่ใกล้หมดอายุที่สุด
                (SELECT quantity_remaining 
                FROM chemical_inventory 
                WHERE chemical_id = t2.id 
                AND status_delete = 0 
                AND quantity_remaining > 0 
                ORDER BY expiry_date ASC, id ASC 
                LIMIT 1) AS nearest_expiry_qty,
                -- วันที่รับเข้าล่าสุด
                MAX(CASE WHEN t1.status_delete = 0 THEN t1.receive_date ELSE NULL END) AS latest_receive_date,
                -- ข้อมูลผู้สร้าง
                CONCAT(c_rank.rank_name, ' ', c_prof.first_name, ' ', c_prof.last_name) AS creator_name,
                DATE_FORMAT(t2.create_date, '%d/%m/%Y %H:%i') AS created_at,
                -- ข้อมูลผู้แก้ไขล่าสุด
                CONCAT(u_rank.rank_name, ' ', u_prof.first_name, ' ', u_prof.last_name) AS updater_name,
                DATE_FORMAT(t2.update_date, '%d/%m/%Y %H:%i') AS updated_at
            FROM master_chemical_list t2
            LEFT JOIN master_departments t_dept ON t2.department_id = t_dept.id 
            LEFT JOIN chemical_inventory t1 ON t2.id = t1.chemical_id AND t1.status_delete = 0
            -- JOIN ตารางหน่วยนับ
            LEFT JOIN master_chemical_units t3 ON t2.unit_id = t3.id 
            -- JOIN ผู้สร้าง
            LEFT JOIN user_profile c_prof ON t2.create_by = c_prof.user_id
            LEFT JOIN user_rank c_rank ON c_prof.rank_id = c_rank.rank_id
            -- JOIN ผู้แก้ไขล่าสุด
            LEFT JOIN user_profile u_prof ON t2.update_by = u_prof.user_id
            LEFT JOIN user_rank u_rank ON u_prof.rank_id = u_rank.rank_id
            $where 
            GROUP BY 
                t2.id, 
                t2.chemical_name, 
                t2.chemical_brand, 
                t_dept.department_name,
                c_rank.rank_name, 
                c_prof.first_name, 
                c_prof.last_name, 
                t2.create_date, 
                u_rank.rank_name, 
                u_prof.first_name, 
                u_prof.last_name, 
                t2.update_date
            $having
            ORDER BY t2.chemical_name ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New')->setSize(11);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('คลังและล็อตสารเคมี');

    // กำหนดหัวตาราง
    $headers = [
        'A1' => 'ลำดับ',
        'B1' => 'ชื่อสารเคมี',
        'C1' => 'หน่วยงาน',
        'D1' => 'ยี่ห้อ/ผู้ผลิต',
        'E1' => 'ยอดคงเหลือ (ใช้งานได้)',
        'F1' => 'ยอดคงเหลือ (รวม)',
        'G1' => 'วันหมดอายุ (ใกล้ที่สุด)',
        'H1' => 'สถานที่จัดเก็บ',
        'I1' => 'วันที่รับเข้าล่าสุด',
        'J1' => 'ผู้สร้างรายการ',
        'K1' => 'วันที่สร้าง',
        'L1' => 'ผู้แก้ไขล่าสุด',
        'M1' => 'วันที่แก้ไขล่าสุด'
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
    $sheet->getStyle('A1:M1')->applyFromArray($headerStyle);

    // 7. ใส่ข้อมูล
    $rowNumber = 2;
    foreach ($data as $index => $row) {

        $unitFull = '';
        if (!empty($row['unit_name_th'])) {
            $isPkg = (strpos($row['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($row['unit_group'], 'Packaging') !== false);
            $symbol = $isPkg ? $row['unit_name_en'] : $row['unit_symbol'];
            $unitFull = " " . $row['unit_name_th'] . " (" . $symbol . ")";
        }

        // เตรียมหน่วยนับแบบย่อสำหรับใส่ในวงเล็บวันหมดอายุ
        $shortUnit = $row['unit_symbol'] ?: '';
        if (!empty($row['unit_group']) && (strpos($row['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($row['unit_group'], 'Packaging') !== false)) {
            $shortUnit = $row['unit_name_en'] ?: $row['unit_symbol'];
        }

        // จัดรูปแบบวันหมดอายุ (วันที่ + ปริมาณในวงเล็บ)
        $expiryShow = $row['nearest_expiry'] ? date('d/m/Y', strtotime($row['nearest_expiry'])) : '-';
        if ($row['nearest_expiry'] && (float)$row['nearest_expiry_qty'] > 0) {
            $expiryShow .= " (" . number_format($row['nearest_expiry_qty'], 2) . " " . $shortUnit . ")";
        }
        // จัดรูปแบบวันหมดอายุ - วันที่รับเข้าล่าสุด
        $expiryDate = $row['nearest_expiry'] ? date('d/m/Y', strtotime($row['nearest_expiry'])) : '-';
        $receiveDate = $row['latest_receive_date'] ? date('d/m/Y', strtotime($row['latest_receive_date'])) : '-';

        $sheet->setCellValue('A' . $rowNumber, $index + 1);
        $sheet->setCellValue('B' . $rowNumber, $row['chemical_name'] ?: "-");
        $sheet->setCellValue('C' . $rowNumber, $row['department_name'] ?: "-");
        $sheet->setCellValue('D' . $rowNumber, $row['chemical_brand'] ?: "-");
        $valRemaining = number_format($row['total_remaining'] ?? 0, 2) . $unitFull;
        $sheet->setCellValue('E' . $rowNumber, $valRemaining);
        $valPhysical = number_format($row['total_physical'] ?? 0, 2) . $unitFull;
        $sheet->setCellValue('F' . $rowNumber, $valPhysical);
        $sheet->setCellValue('G' . $rowNumber, $expiryShow);
        $sheet->setCellValue('H' . $rowNumber, $row['locations_summary'] ?: '-');
        $sheet->setCellValue('I' . $rowNumber, $receiveDate);
        $sheet->setCellValue('J' . $rowNumber, $row['creator_name'] ?: '-');
        $sheet->setCellValue('K' . $rowNumber, $row['created_at'] ?: '-');
        $sheet->setCellValue('L' . $rowNumber, $row['updater_name'] ?: '-');
        $sheet->setCellValue('M' . $rowNumber, $row['updated_at'] ?: '-');

        // จัดกึ่งกลางและเส้นขอบ
        $range = 'A' . $rowNumber . ':M' . $rowNumber;
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($range)->getBorders()->getAllBorders();
        // ->setBorderStyle(Border::BORDER_THIN);

        $rowNumber++;
    }

    // 8. ปรับความกว้างคอลัมน์อัตโนมัติ
    foreach (range('A', 'M') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 9. ดาวน์โหลดไฟล์
    $filename = "Chemical_Inventory_Report_" . date('Y-m-d_H.i') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
