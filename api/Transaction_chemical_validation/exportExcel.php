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

try {

    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน (เพิ่มเช็ค is_active = 1)
    $stmtUser = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
    $stmtUser->execute([$user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        http_response_code(403);
        die("Access Denied: ไม่พบข้อมูลผู้ใช้งาน หรือบัญชีถูกระงับ");
    }

    $user_role    = strtolower($userData['role'] ?? '');
    $user_dept_id = $userData['department_id'] ?? null;

    // 2. รับค่าจาก Frontend
    $val_start          = $_GET['filter_val_start'] ?? '';
    $val_end            = $_GET['filter_val_end'] ?? '';
    $filter_chemical_id = $_GET['filter_chemical_id'] ?? '';
    $filter_dept_id     = $_GET['filter_department_id'] ?? '';
    $tester_id          = $_GET['filter_tester'] ?? '';
    $res_acid_base      = $_GET['filter_res_acid_base'] ?? '';
    $res_blood          = $_GET['filter_res_blood'] ?? '';
    $is_verified        = $_GET['filter_is_verified'] ?? '';

    // ควบคุมสิทธิ์การเข้าถึงข้อมูลตามหน่วยงานอย่างรัดกุม (Department Access Control)
    if ($user_role !== 'admin') {
        // ถ้า User ทั่วไปแอบส่ง filter_department_id ของหน่วยงานอื่นมา -> ปฏิเสธการส่งออกรายงานทันที
        if (!empty($filter_dept_id) && $filter_dept_id != $user_dept_id) {
            http_response_code(403);
            die("Access Denied: คุณไม่มีสิทธิ์ส่งออกรายงานข้อมูลของหน่วยงานอื่น");
        }
        // บังคับล็อกให้ดึงเฉพาะหน่วยงานของตนเองเท่านั้น
        $filter_dept_id = $user_dept_id;
    }

    // 3. ตั้งค่าเงื่อนไข WHERE
    $where = " WHERE t1.status_delete = 0 ";
    $params = [];

    // รวมเงื่อนไขกรองสิทธิ์หน่วยงานให้กระชับ
    if (!empty($filter_dept_id)) {
        $where .= " AND (
            SELECT mcl_sub.department_id 
            FROM preparation_ingredients pi_sub
            JOIN chemical_inventory inv_sub ON pi_sub.inventory_id = inv_sub.id
            JOIN master_chemical_list mcl_sub ON inv_sub.chemical_id = mcl_sub.id
            WHERE pi_sub.prep_id = t2.id AND pi_sub.status_delete = 0 
            LIMIT 1
        ) = ? ";
        $params[] = $filter_dept_id;
    }

    if (!empty($val_start)) {
        $where .= " AND t1.test_date >= ? ";
        $params[] = $val_start;
    }
    if (!empty($val_end)) {
        $where .= " AND t1.test_date <= ? ";
        $params[] = $val_end;
    }
    if (!empty($filter_chemical_id)) {
        // ใช้ Subquery เจาะหาว่าใบเตรียมตัวไหนที่ใช้สารเคมี ID นี้เป็นสารตั้งต้น
        $where .= " AND t2.id IN (
                    SELECT DISTINCT pi_f.prep_id 
                    FROM preparation_ingredients pi_f 
                    JOIN chemical_inventory inv_f ON pi_f.inventory_id = inv_f.id 
                    WHERE inv_f.chemical_id = ? AND pi_f.status_delete = 0
                ) ";
        $params[] = $filter_chemical_id;
    }
    if (!empty($tester_id)) {
        $where .= " AND t1.tester_id = ? ";
        $params[] = $tester_id;
    }
    if (!empty($res_acid_base)) {
        $where .= " AND t1.res_acid_base = ? ";
        $params[] = $res_acid_base;
    }
    if (!empty($res_blood)) {
        $where .= " AND t1.res_blood = ? ";
        $params[] = $res_blood;
    }
    if ($is_verified !== '') {
        $where .= " AND t1.is_verified = ? ";
        $params[] = $is_verified;
    }

    // 4. Query ข้อมูลหลัก (ตัด LIMIT ออกเพื่อให้ Export ทั้งหมดตามที่กรอง)
$sql = "SELECT 
        DATE_FORMAT(t1.test_date, '%d/%m/%Y') AS test_date_show,
        t1.amount_used,
        t1.res_acid_base,
        t1.res_blood,
        t1.is_verified,
        t2.prep_name,
        -- ดึงชื่อหน่วยงาน
            (
                SELECT md_sub.department_name 
                FROM preparation_ingredients pi_sub
                JOIN chemical_inventory inv_sub ON pi_sub.inventory_id = inv_sub.id
                JOIN master_chemical_list mcl_sub ON inv_sub.chemical_id = mcl_sub.id
                JOIN master_departments md_sub ON mcl_sub.department_id = md_sub.id
                WHERE pi_sub.prep_id = t2.id AND pi_sub.status_delete = 0 
                LIMIT 1
            ) AS department_name,
        -- ใช้ GROUP_CONCAT มัดรวมสารตั้งต้นทั้งหมดมาแสดงในช่องเดียว
        GROUP_CONCAT(DISTINCT t3.lot_number SEPARATOR ', ') AS source_lot_number,
        GROUP_CONCAT(DISTINCT t4.chemical_name SEPARATOR ' + ') AS master_chem_name,
        -- ข้อมูลหน่วยนับของน้ำยาที่เตรียม
        t5.unit_name_th, t5.unit_symbol, t5.unit_name_en, t5.unit_group,
        CONCAT(IFNULL(r1.rank_name, ''), ' ', p1.first_name, ' ', p1.last_name) AS tester_fullname,
        CONCAT(IFNULL(r2.rank_name, ''), ' ', p2.first_name, ' ', p2.last_name) AS verifier_fullname,
        -- ข้อมูลผู้สร้าง 
        CONCAT(IFNULL(c_rank.rank_name, ''), ' ', c_prof.first_name, ' ', c_prof.last_name) AS creator_name,
        DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS created_at,
        -- ข้อมูลผู้แก้ไขล่าสุด
        CONCAT(IFNULL(u_rank.rank_name, ''), ' ', u_prof.first_name, ' ', u_prof.last_name) AS updater_name,
        DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updated_at
    FROM trans_chemical_validation t1 
    INNER JOIN chemical_preparation t2 ON t1.prep_id = t2.id

    LEFT JOIN preparation_ingredients pi ON t2.id = pi.prep_id AND pi.status_delete = 0
    LEFT JOIN chemical_inventory t3 ON pi.inventory_id = t3.id
    LEFT JOIN master_chemical_list t4 ON t3.chemical_id = t4.id
    LEFT JOIN master_chemical_units t5 ON t2.unit_id = t5.id 
    LEFT JOIN user_profile p1 ON t1.tester_id = p1.user_id
    LEFT JOIN user_rank r1 ON p1.rank_id = r1.rank_id
    LEFT JOIN user_profile p2 ON t1.verifier_id = p2.user_id
    LEFT JOIN user_rank r2 ON p2.rank_id = r2.rank_id
    LEFT JOIN user_profile c_prof ON t1.create_by = c_prof.user_id
    LEFT JOIN user_rank c_rank ON c_prof.rank_id = c_rank.rank_id
    LEFT JOIN user_profile u_prof ON t1.update_by = u_prof.user_id
    LEFT JOIN user_rank u_rank ON u_prof.rank_id = u_rank.rank_id
    $where 
    GROUP BY t1.id, t2.prep_name, t5.unit_name_th, t5.unit_symbol, t5.unit_name_en, t5.unit_group,
             r1.rank_name, p1.first_name, p1.last_name,
             r2.rank_name, p2.first_name, p2.last_name,
             c_rank.rank_name, c_prof.first_name, c_prof.last_name,
             u_rank.rank_name, u_prof.first_name, u_prof.last_name
    ORDER BY t1.test_date DESC, t1.id DESC";


    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. เริ่มสร้าง Excel
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New')->setSize(11);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('รายการทดสอบสารเคมี');

    // กำหนดหัวตาราง (A ถึง I)
    $headers = [
        'A1' => 'ลำดับ',
        'B1' => 'วันที่ทดสอบ',
        'C1' => 'รายการสารที่ทดสอบ',
        'D1' => 'หน่วยงาน',
        'E1' => 'สารเคมีตั้งต้น',
        'F1' => 'Lot ตั้งต้น',
        'G1' => 'ปริมาณที่ใช้',
        'H1' => 'ผลทดสอบ (กรด-เบส)',
        'I1' => 'ผลทดสอบ (โลหิต)',
        'J1' => 'ผู้ทดสอบ',
        'K1' => 'ผู้ทวนสอบ',
        'L1' => 'สถานะการทวนสอบ',
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

    // 6. ใส่ข้อมูล
    $rowNumber = 2;
    foreach ($data as $index => $row) {
        // จัดรูปแบบสถานะ
        $statusText = ($row['is_verified'] == 1) ? "ทวนสอบแล้ว" : "รอการทวนสอบ";
        $resAcid = ($row['res_acid_base'] == "pass") ? "ใช้ได้" : "ใช้ไม่ได้";
        $resBlood = ($row['res_blood'] == "pass") ? "ใช้ได้" : "ใช้ไม่ได้";

        $unitShow = '';
        if (!empty($row['unit_name_th'])) {
            $isPkg = (strpos($row['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($row['unit_group'], 'Packaging') !== false);
            $symbol = $isPkg ? $row['unit_name_en'] : $row['unit_symbol'];
            $unitShow = " " . $row['unit_name_th'] . " (" . $symbol . ")";
        }

        $sheet->setCellValue('A' . $rowNumber, $index + 1);
        $sheet->setCellValue('B' . $rowNumber, $row['test_date_show'] ?: '-');
        $sheet->setCellValue('C' . $rowNumber, $row['prep_name'] ?: '-');
        $sheet->setCellValue('D' . $rowNumber, $row['department_name'] ?: '-'); 
        $sheet->setCellValue('E' . $rowNumber, $row['master_chem_name'] ?: '-');
        $sheet->setCellValue('F' . $rowNumber, $row['source_lot_number'] ?: '-');
        $sheet->setCellValue('G' . $rowNumber, number_format($row['amount_used'], 2) . $unitShow ?: '-');
        // แยกคอลัมน์ผลการทดสอบ
        $sheet->setCellValue('H' . $rowNumber, $resAcid ?: '-');
        $sheet->setCellValue('I' . $rowNumber, $resBlood ?: '-');
        $sheet->setCellValue('J' . $rowNumber, $row['tester_fullname'] ?: '-');
        $sheet->setCellValue('K' . $rowNumber, $row['verifier_fullname'] ?: '-');
        $sheet->setCellValue('L' . $rowNumber, $statusText ?: '-');
        $sheet->setCellValue('M' . $rowNumber, $row['creator_name'] ?: '-');
        $sheet->setCellValue('N' . $rowNumber, $row['created_at'] ?: '-');
        $sheet->setCellValue('O' . $rowNumber, $row['updater_name'] ?: '-');
        $sheet->setCellValue('P' . $rowNumber, $row['updated_at'] ?: '-');

        // จัดกึ่งกลางและเส้นขอบ
        $range = 'A' . $rowNumber . ':P' . $rowNumber;
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($range)->getBorders()->getAllBorders();
        // ->setBorderStyle(Border::BORDER_THIN);

        $rowNumber++;
    }

    // 7. ปรับความกว้างคอลัมน์
    foreach (range('A', 'P') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // 8. ส่งไฟล์ออก
    $filename = "Chemical_Validation_Report_" . date('Y-m-d_H.i') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
