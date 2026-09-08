<?php
session_start();
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

$user_id = $_SESSION['user_id'];

// ดึงข้อมูล Role และ Department ของผู้ใช้งาน (เพิ่มเช็ค is_active = 1)
$stmtUser = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
$stmtUser->execute([$user_id]);
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    http_response_code(403);
    die("Access Denied: ไม่พบข้อมูลผู้ใช้งาน หรือบัญชีถูกระงับ");
}

$user_role    = strtolower($userData['role'] ?? '');
$user_dept_id = $userData['department_id'] ?? null;

// 2. รับค่า Filter จาก Frontend 
$filter_department_id = $_GET['filter_department_id'] ?? '';
$filter_chemical_id = $_GET['filter_chemical_id'] ?? '';
$prep_name  = $_GET['filter_prep_name'] ?? '';
$source_lot = $_GET['filter_source_lot'] ?? '';
$prep_start = $_GET['filter_prep_start'] ?? '';
$prep_end   = $_GET['filter_prep_end'] ?? '';
$status     = $_GET['filter_status'] ?? '';
$preparer   = $_GET['filter_preparer'] ?? '';

$today = date('Y-m-d');
$near_expiry_date = date('Y-m-d', strtotime('+14 days'));

// ควบคุมสิทธิ์การเข้าถึงข้อมูลตามหน่วยงานอย่างรัดกุม (Department Access Control)
if ($user_role !== 'admin') {
    // ถ้า User ทั่วไปพยายามส่ง department_id ของหน่วยงานอื่นมาทาง URL -> บล็อกและแจ้งเตือนทันที
    if (!empty($filter_department_id) && $filter_department_id != $user_dept_id) {
        http_response_code(403);
        die("Access Denied: คุณไม่มีสิทธิ์ส่งออกรายงานข้อมูลของหน่วยงานอื่น");
    }
    // บังคับล็อกให้ดึงเฉพาะหน่วยงานของตนเองเท่านั้น
    $filter_department_id = $user_dept_id;
}

// 3. ตั้งค่าเงื่อนไขเริ่มต้น 
$where = " WHERE t1.status_delete = 0 ";
$params = [];

// เงื่อนไขกรองด้วยหน่วยงาน (อ้างอิงผ่านคลังสารเคมีตั้งต้น)
if (!empty($filter_department_id)) {
    $where .= " AND t1.id IN (
                    SELECT DISTINCT pi_dept.prep_id 
                    FROM preparation_ingredients pi_dept 
                    JOIN chemical_inventory inv_dept ON pi_dept.inventory_id = inv_dept.id 
                    JOIN master_chemical_list mcl_dept ON inv_dept.chemical_id = mcl_dept.id
                    WHERE mcl_dept.department_id = ? AND pi_dept.status_delete = 0
                ) ";
    $params[] = $filter_department_id;
}

if (!empty($filter_chemical_id)) {
    $where .= " AND t1.id IN (
                    SELECT DISTINCT pi.prep_id 
                    FROM preparation_ingredients pi 
                    JOIN chemical_inventory inv ON pi.inventory_id = inv.id 
                    WHERE inv.chemical_id = ? AND pi.status_delete = 0
                ) ";
    $params[] = $filter_chemical_id;
}

if (!empty($prep_name)) {
    $where .= " AND t1.prep_name LIKE ? ";
    $params[] = "%$prep_name%";
}
if (!empty($source_lot)) {
    $where .= " AND t1.id IN (
                    SELECT DISTINCT pi.prep_id 
                    FROM preparation_ingredients pi 
                    JOIN chemical_inventory inv ON pi.inventory_id = inv.id 
                    WHERE inv.lot_number LIKE ? AND pi.status_delete = 0
                ) ";
    $params[] = "%$source_lot%";
}

if (!empty($preparer)) {
    $where .= " AND t1.preparer_id = ? ";
    $params[] = $preparer;
}
if (!empty($prep_start)) {
    $where .= " AND t1.prep_date >= ? ";
    $params[] = $prep_start;
}
if (!empty($prep_end)) {
    $where .= " AND t1.prep_date <= ? ";
    $params[] = $prep_end;
}

// กรองตามสถานะ
if ($status == 'expired') {
    $where .= " AND t1.expiry_date < ? AND t1.quantity_remaining > 0 ";
    $params[] = $today;
} elseif ($status == 'near_expiry') {
    $where .= " AND t1.expiry_date >= ? AND t1.expiry_date <= ? AND t1.quantity_remaining > 0 ";
    $params[] = $today;
    $params[] = $near_expiry_date;
} elseif ($status == 'active') {
    $where .= " AND t1.expiry_date > ? AND t1.quantity_remaining > 0 ";
    $params[] = $near_expiry_date;
} elseif ($status == 'depleted') {
    $where .= " AND t1.quantity_remaining <= 0 ";
}

// 4. SQL Query (ดึงข้อมูลทั้งหมดที่ผ่าน Filter โดยไม่มี LIMIT)
$sql = "SELECT 
        t1.*,
        -- รวมสารตั้งต้น
        GROUP_CONCAT(CONCAT(m.chemical_name, ' (Lot: ', inv.lot_number, ') - ', pi.amount_used, ' ', u.unit_symbol) SEPARATOR '; ') AS ingredients_summary,
        -- ข้อมูลผู้เตรียม/ผู้สร้าง/ผู้แก้ไข
        CONCAT(IFNULL(t4.rank_name, ''),' ', t3.first_name, ' ', t3.last_name) AS preparer_fullname,
        CONCAT(IFNULL(c_rank.rank_name, ''),' ', c_prof.first_name, ' ', c_prof.last_name) AS creator_name,
        CONCAT(IFNULL(u_rank.rank_name, ''),' ', u_prof.first_name, ' ', u_prof.last_name) AS updater_name,
        DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS created_at,
        DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updated_at,
        DATE_FORMAT(t1.prep_date, '%d/%m/%Y') AS prep_date_show,
        DATE_FORMAT(t1.expiry_date, '%d/%m/%Y') AS exp_date_show,
        u_target.unit_name_th, u_target.unit_symbol, u_target.unit_name_en, u_target.unit_group,
        md.department_name 
    FROM chemical_preparation t1 
    LEFT JOIN preparation_ingredients pi ON t1.id = pi.prep_id AND pi.status_delete = 0
    LEFT JOIN chemical_inventory inv ON pi.inventory_id = inv.id
    LEFT JOIN master_chemical_list m ON inv.chemical_id = m.id
    LEFT JOIN master_departments md ON m.department_id = md.id 
    LEFT JOIN master_chemical_units u ON pi.unit_id = u.id 
    LEFT JOIN master_chemical_units u_target ON t1.unit_id = u_target.id
    LEFT JOIN user_profile t3 ON t1.preparer_id = t3.user_id
    LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
    LEFT JOIN user_profile c_prof ON t1.create_by = c_prof.user_id
    LEFT JOIN user_rank c_rank ON c_prof.rank_id = c_rank.rank_id
    LEFT JOIN user_profile u_prof ON t1.update_by = u_prof.user_id
    LEFT JOIN user_rank u_rank ON u_prof.rank_id = u_rank.rank_id
    $where 
    GROUP BY 
        t1.id, 
        t1.prep_date, t1.expiry_date, t1.create_date, t1.update_date,
        t3.first_name, t3.last_name, t4.rank_name,
        c_prof.first_name, c_prof.last_name, c_rank.rank_name,
        u_prof.first_name, u_prof.last_name, u_rank.rank_name,
        u_target.unit_name_th, u_target.unit_symbol, u_target.unit_name_en, u_target.unit_group,
        md.department_name 
    ORDER BY t1.prep_date DESC, t1.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. เริ่มสร้าง Excel
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New')->setSize(11);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('รายการเตรียมสารเคมี');

    // กำหนดหัวตาราง (A ถึง I)
    $headers = [
        'A1' => 'ลำดับ',
        'B1' => 'ชื่อสารเคมีที่เตรียม',
        'C1' => 'หน่วยงาน',
        'D1' => 'สารตั้งต้นที่ใช้ (ชื่อ/Lot/ปริมาณ)', // รวมเป็นคอลัมน์เดียว
        'E1' => 'ปริมาณคงเหลือ',
        'F1' => 'ปริมาณที่เตรียม',
        'G1' => 'วันที่เตรียม',
        'H1' => 'วันหมดอายุ',
        'I1' => 'ผู้เตรียม',
        'J1' => 'ผู้สร้างรายการ',
        'K1' => 'วันที่สร้าง',
        'L1' => 'ผู้แก้ไขล่าสุด',
        'M1' => 'วันที่แก้ไขล่าสุด'
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
    $sheet->getStyle('A1:M1')->applyFromArray($headerStyle);

    // 6. ใส่ข้อมูล
    $rowNumber = 2;
    foreach ($data as $index => $row) {

        // --- Logic การปั้นหน่วยนับ (Target Unit) ---
        $unitShow = '';
        if (!empty($row['unit_name_th'])) {
            $isPkg = (strpos($row['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($row['unit_group'], 'Packaging') !== false);
            $sym = $isPkg ? $row['unit_name_en'] : $row['unit_symbol'];
            $unitShow = " " . $row['unit_name_th'] . " (" . $sym . ")";
        }

        $sheet->setCellValue('A' . $rowNumber, $index + 1);
        $sheet->setCellValue('B' . $rowNumber, $row['prep_name'] ?: "-");
        $sheet->setCellValue('C' . $rowNumber, $row['department_name'] ?: "-"); 
        $sheet->setCellValue('D' . $rowNumber, $row['ingredients_summary'] ?: "-");
        $sheet->setCellValue('E' . $rowNumber, number_format($row['quantity_remaining'], 2) . $unitShow ?: "-");
        $sheet->setCellValue('F' . $rowNumber, number_format($row['prep_quantity'], 2) . $unitShow ?: "-");
        $sheet->setCellValue('G' . $rowNumber, $row['prep_date_show'] ?: "-");
        $sheet->setCellValue('H' . $rowNumber, $row['exp_date_show'] ?: "-");
        $sheet->setCellValue('I' . $rowNumber, $row['preparer_fullname'] ?: "-");
        $sheet->setCellValue('J' . $rowNumber, $row['creator_name'] ?: "-");
        $sheet->setCellValue('K' . $rowNumber, $row['created_at'] ?: "-");
        $sheet->setCellValue('L' . $rowNumber, $row['updater_name'] ?: "-");
        $sheet->setCellValue('M' . $rowNumber, $row['updated_at'] ?: "-");

        // จัดรูปแบบและเส้นขอบ
        $range = 'A' . $rowNumber . ':M' . $rowNumber;
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($range)->getBorders()->getAllBorders();
        // ->setBorderStyle(Border::BORDER_THIN);

        $rowNumber++;
    }

    // 7. จัดการความกว้างคอลัมน์
    foreach (range('A', 'M') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 8. ส่งไฟล์ออก
    $filename = "Chemical_Preparation_Report_" . date('Y-m-d_H.i') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
