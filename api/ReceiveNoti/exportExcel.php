<?php
session_start();

// 1. ตั้งค่าการแสดง Error (เปิดไว้ตอนทดสอบ ถ้าใช้งานจริงค่อยปิดครับ)
ini_set('display_errors', 1);
error_reporting(E_ALL);
$filename = "Report_Incident" . date('Ymd_His') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// 2. Import ไฟล์ที่จำเป็น
require '../../db_config.php'; 
require '../../vendor/autoload.php'; // เรียกใช้ Library ผ่าน Vendor ที่มีอยู่แล้ว
require __DIR__ . '/../../helpers/report_no.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;


// 3. รับค่าจากฟอร์ม (ผ่าน $.serialize() ในหน้าบ้าน)
$filter_doc_no = $_GET['filter_doc_no'] ?? '';
$filter_province = $_GET['filter_province'] ?? '';
// ... รับตัวแปรอื่นๆ ตาม name ของ input ...

// 4. สร้างออบเจกต์ Spreadsheet
$spreadsheet = new Spreadsheet();
// ตั้งฟอนต์ Sarabun เป็นฟอนต์เริ่มต้นของไฟล์
$spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New');
$spreadsheet->getDefaultStyle()->getFont()->setSize(11);

$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('รายการรับแจ้งเหตุ');

// 5. กำหนดหัวตาราง (Header)
$headers = [
    'A1' => 'ลำดับ',
    'B1' => 'เลขที่เอกสาร',
    'C1' => 'เลขที่รายงาน',
    'D1' => 'วันที่รับแจ้ง',
    'E1' => 'จังหวัด',
    'F1' => 'สภ./สน.',
    'G1' => 'เหตุที่รับแจ้ง',
    'H1' => 'ช่องทาง',
    'I1' => 'ผู้แจ้ง',
    'J1' => 'พนักงานสอบสวน',
    'K1' => 'เบอร์โทรศัพท์',
    'L1' => 'ผู้เสียหาย',
    'M1' => 'เบอร์โทรศัพท์',
];

foreach ($headers as $cell => $text) {
    $sheet->setCellValue($cell, $text);
}

// 6. ตกแต่งหัวตาราง (ทำให้ตัวหนา และใส่สีพื้นหลัง)
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => '000000'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'D3D3D3'], // สีเทาอ่อนเหมือนในรูป
    ],
];
$sheet->getStyle('A1:M1')->applyFromArray($headerStyle);

// ปรับความกว้างคอลัมน์อัตโนมัติ
foreach (range('A', 'M') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}


$doc_no     = $_POST['filter_doc_no'] ?? '';
$date_start = $_POST['filter_date_start'] ?? '';
$date_end   = $_POST['filter_date_end'] ?? '';
$province   = $_POST['filter_province'] ?? '';
$station    = $_POST['filter_station'] ?? '';
$inc_type   = $_POST['filter_incident_type'] ?? '';
$person     = $_POST['filter_person'] ?? '';
$inquiry_official = $_POST['filter_inquiry_official'] ?? '';

$where = " WHERE t1.statusDelete = 0 AND t2.is_active = 1 ";
$params = [];

// --- จัดการเงื่อนไข Filter ---
if (!empty($doc_no)) {
    $where .= " AND (t1.receiveNoti_No LIKE ? OR t1.receiveNoti_No_TH LIKE ?) ";
    $params[] = "%$doc_no%";
    $params[] = "%$doc_no%";
}

if (!empty($date_start) && !empty($date_end)) {
    $where .= " AND t1.create_date BETWEEN ? AND ? ";
    $params[] = $date_start . " 00:00:00";
    $params[] = $date_end . " 23:59:59";
}

if (!empty($province)) {
    $where .= " AND t1.provinceID = ? ";
    $params[] = $province;
}

if (!empty($station)) {
    $where .= " AND t1.complaints_From LIKE ? ";
    $params[] = "%$station%";
}

if (!empty($inquiry_official)) {
    $where .= " AND t1.inquiry_official_full_name LIKE ? ";
    $params[] = "%$inquiry_official%";
}

if (!empty($inc_type)) {
    $where .= " AND t1.complaints_type = ? ";
    $params[] = $inc_type;
}

// 2. สร้างเงื่อนไข HAVING (แยกออกมาสำหรับ Alias)
$having = "";
$paramsHaving = [];
if (!empty($person)) {
    $having = " HAVING fullname_create LIKE ? ";
    $paramsHaving[] = "%$person%";
}

$sql = "SELECT t1.receiveNoti_No,t1.receiveNotiReportNo,t1.complaints_From,t1.inquiry_official_full_name,t1.inquiry_official_phone,t1.suffer_full_name,t1.suffer_phone,CONCAT(t4.rank_name,' ',t3.first_name,' ',t3.last_name) AS fullname_create,
case when t1.complaints_type = 01 then 'คดีเกี่ยวกับทรัพย์'
when t1.complaints_type = 02 then 'คดีเกี่ยวกับชีวิต'
when t1.complaints_type = 03 then 'คดีเกี่ยวกับระเบิด'
when t1.complaints_type = 04 then 'คดีเพลิงไหม้'
when t1.complaints_type = 05 then 'คดีจราจร'
when t1.complaints_type = 06 then 'ตรวจเก็บวัตถุพยาน'
when t1.complaints_type = 07 then 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ'
when t1.complaints_type = 08 then 'ตรวจเก็บวัตถุพยานบุคคล'
ELSE 'อื่นๆ' END AS complaintstype,
case when t1.complaints_From_Device = 'r' then 'วิทยุสื่อสาร'
when t1.complaints_From_Device = 't' then 'ทางโทรศัพท์'
ELSE 'อื่นๆ' END AS complaintsdevice,t1.complaints_type,
DATE_FORMAT(t1.create_dateTimeStamp, '%d/%m/%Y %H:%i:%s') AS createdate,t1.province
FROM rn_ReceiveNoti t1 
LEFT JOIN users t2 ON t1.create_by = t2.user_id
LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
$where "; 
$stmt = $pdo->query($sql);
$rowNumber = 2;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sheet->setCellValue('A' . $rowNumber, $rowNumber - 1);
    $sheet->setCellValue('B' . $rowNumber, !empty($row['receiveNoti_No']) ? convertDocNoToThai($row['receiveNoti_No']) : '-');
    $sheet->setCellValue('C' . $rowNumber, !empty($row['receiveNotiReportNo']) ? convertReportNoToThai($row['receiveNotiReportNo']) : '-');
    $sheet->setCellValue('D' . $rowNumber, $row['createdate'] ?? '-');
    $sheet->setCellValue('E' . $rowNumber, $row['province'] ?? '-');
    $sheet->setCellValue('F' . $rowNumber, $row['complaints_From'] ?? '-');
    $sheet->setCellValue('G' . $rowNumber, $row['complaintstype'] ?? '-');
    $sheet->setCellValue('H' . $rowNumber, $row['complaintsdevice'] ?? '-');
    $sheet->setCellValue('I' . $rowNumber, $row['fullname_create'] ?? '-');
    $sheet->setCellValue('J' . $rowNumber, $row['inquiry_official_full_name'] ?? '-');
    $sheet->setCellValue('K' . $rowNumber, $row['inquiry_official_phone'] ?? '-');
    $sheet->setCellValue('L' . $rowNumber, $row['suffer_full_name'] ?? '-');
    $sheet->setCellValue('M' . $rowNumber, $row['suffer_phone'] ?? '-');
    
    foreach (range('A', 'M') as $columnID) {
        $sheet->getStyle($columnID . $rowNumber)
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    }
    $rowNumber++;
}

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;