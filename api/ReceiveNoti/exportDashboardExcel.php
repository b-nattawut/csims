<?php
ob_start();
session_start();
ini_set('display_errors', 0);
error_reporting(0);

require '../../db_config.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// ============================================
// ดึงข้อมูลจาก Database (query เดียวกับ getDashboardStats.php)
// ============================================
try {
    $sqlTable = "SELECT 
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '01') AS yala_wealth,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '02') AS yala_life,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '03') AS yala_bom,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '04') AS yala_fire,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '05') AS yala_traffic,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '06') AS yala_evidence,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '07') AS yala_crime_scene,
        SUM(province = 'ยะลา' AND statusDelete = 0 AND complaints_type = '08') AS yala_evidence_person,

        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '01') AS pattanee_wealth,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '02') AS pattanee_life,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '03') AS pattanee_bom,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '04') AS pattanee_fire,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '05') AS pattanee_traffic,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '06') AS pattanee_evidence,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '07') AS pattanee_crime_scene,
        SUM(province = 'ปัตตานี' AND statusDelete = 0 AND complaints_type = '08') AS pattanee_evidence_person,

        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '01') AS narateewat_wealth,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '02') AS narateewat_life,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '03') AS narateewat_bom,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '04') AS narateewat_fire,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '05') AS narateewat_traffic,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '06') AS narateewat_evidence,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '07') AS narateewat_crime_scene,
        SUM(province = 'นราธิวาส' AND statusDelete = 0 AND complaints_type = '08') AS narateewat_evidence_person
        FROM rn_ReceiveNoti";

    $stmt = $pdo->prepare($sqlTable);
    $stmt->execute();
    $raw = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    echo 'เกิดข้อผิดพลาดในการดึงข้อมูล';
    exit;
}

// ============================================
// สร้างข้อมูลตาราง
// ============================================
$provinces = [
    [
        'name' => 'ยะลา',
        'LC' => (int)($raw['yala_wealth'] ?? 0),
        'MD' => (int)($raw['yala_life'] ?? 0),
        'BM' => (int)($raw['yala_bom'] ?? 0),
        'FR' => (int)($raw['yala_fire'] ?? 0),
        'TF' => (int)($raw['yala_traffic'] ?? 0),
        'EV' => (int)($raw['yala_evidence'] ?? 0),
        'CM' => (int)($raw['yala_crime_scene'] ?? 0),
        'PS' => (int)($raw['yala_evidence_person'] ?? 0),
    ],
    [
        'name' => 'ปัตตานี',
        'LC' => (int)($raw['pattanee_wealth'] ?? 0),
        'MD' => (int)($raw['pattanee_life'] ?? 0),
        'BM' => (int)($raw['pattanee_bom'] ?? 0),
        'FR' => (int)($raw['pattanee_fire'] ?? 0),
        'TF' => (int)($raw['pattanee_traffic'] ?? 0),
        'EV' => (int)($raw['pattanee_evidence'] ?? 0),
        'CM' => (int)($raw['pattanee_crime_scene'] ?? 0),
        'PS' => (int)($raw['pattanee_evidence_person'] ?? 0),
    ],
    [
        'name' => 'นราธิวาส',
        'LC' => (int)($raw['narateewat_wealth'] ?? 0),
        'MD' => (int)($raw['narateewat_life'] ?? 0),
        'BM' => (int)($raw['narateewat_bom'] ?? 0),
        'FR' => (int)($raw['narateewat_fire'] ?? 0),
        'TF' => (int)($raw['narateewat_traffic'] ?? 0),
        'EV' => (int)($raw['narateewat_evidence'] ?? 0),
        'CM' => (int)($raw['narateewat_crime_scene'] ?? 0),
        'PS' => (int)($raw['narateewat_evidence_person'] ?? 0),
    ],
];

$colKeys = ['LC', 'MD', 'BM', 'FR', 'TF', 'EV', 'CM', 'PS'];

// ============================================
// สร้าง Spreadsheet
// ============================================
$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('TH Sarabun New')->setSize(14);

$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('สรุปคดีที่เกิดเหตุ');

// ============================================
// Title Row (merged)
// ============================================
$sheet->mergeCells('A1:J1');
$sheet->setCellValue('A1', 'สรุปคดีที่เกิดเหตุ (แยกประเภท)');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
]);
$sheet->getRowDimension(1)->setRowHeight(35);

// ============================================
// Header Row
// ============================================
$headers = ['จังหวัด', 'คดีทรัพย์', 'คดีชีวิต', 'คดีระเบิด', 'เพลิงไหม้', 'จราจร', 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)', 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ', 'ตรวจเก็บวัตถุพยานบุคคล', 'รวม'];
$cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

foreach ($headers as $i => $header) {
    $sheet->setCellValue($cols[$i] . '2', $header);
}

$headerStyle = [
    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '60A5FA']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
];
$sheet->getStyle('A2:J2')->applyFromArray($headerStyle);
$sheet->getRowDimension(2)->setRowHeight(40);

// ============================================
// Data Rows
// ============================================
$dataStyle = [
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0F2FE']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
    'font' => ['bold' => true, 'size' => 13],
];

$rowNum = 3;
foreach ($provinces as $prov) {
    $sheet->setCellValue('A' . $rowNum, $prov['name']);
    $colIdx = 1;
    $rowTotal = 0;
    foreach ($colKeys as $key) {
        $sheet->setCellValue($cols[$colIdx] . $rowNum, $prov[$key]);
        $rowTotal += $prov[$key];
        $colIdx++;
    }
    $sheet->setCellValue('J' . $rowNum, $rowTotal);
    $sheet->getStyle('A' . $rowNum . ':J' . $rowNum)->applyFromArray($dataStyle);
    $sheet->getRowDimension($rowNum)->setRowHeight(30);
    $rowNum++;
}

// ============================================
// Footer (รวม) Row
// ============================================
$sheet->setCellValue('A' . $rowNum, 'รวม');
$colIdx = 1;
$grandTotal = 0;
foreach ($colKeys as $key) {
    $colTotal = 0;
    foreach ($provinces as $prov) {
        $colTotal += $prov[$key];
    }
    $sheet->setCellValue($cols[$colIdx] . $rowNum, $colTotal);
    $grandTotal += $colTotal;
    $colIdx++;
}
$sheet->setCellValue('J' . $rowNum, $grandTotal);

$footerStyle = [
    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3B82F6']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
];
$sheet->getStyle('A' . $rowNum . ':J' . $rowNum)->applyFromArray($footerStyle);
$sheet->getRowDimension($rowNum)->setRowHeight(30);

// ============================================
// Column Widths
// ============================================
$sheet->getColumnDimension('A')->setWidth(14);
foreach (['B', 'C', 'D', 'E', 'F'] as $c) {
    $sheet->getColumnDimension($c)->setWidth(12);
}
$sheet->getColumnDimension('G')->setWidth(22);
$sheet->getColumnDimension('H')->setWidth(22);
$sheet->getColumnDimension('I')->setWidth(22);
$sheet->getColumnDimension('J')->setWidth(10);

// ============================================
// Sheet 2 — สถิติตามประเภทคดี (ตารางอย่างเดียว)
// ============================================
$provNames = [];
foreach ($provinces as $prov) {
    $provNames[] = $prov['name'];
}
$provCount = count($provNames);

$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('สถิติตามประเภทคดี');
$sheet2->getStyle('A1:F50')->getFont()->setName('TH Sarabun New')->setSize(14);

$caseTypesShort = ['คดีทรัพย์', 'คดีชีวิต', 'คดีระเบิด', 'เพลิงไหม้', 'จราจร', 'ลายนิ้วมือแฝง', 'วัตถุพยานเกิดเหตุ', 'วัตถุพยานบุคคล'];
$s2cols = ['A','B','C','D','E'];
$lastCol = $s2cols[$provCount + 1];

// Title
$sheet2->mergeCells('A1:' . $lastCol . '1');
$sheet2->setCellValue('A1', 'สถิติตามประเภทคดี (แยกจังหวัด)');
$sheet2->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
]);
$sheet2->getRowDimension(1)->setRowHeight(35);

// Header
$sheet2->setCellValue('A2', 'ประเภทคดี');
for ($i = 0; $i < $provCount; $i++) {
    $sheet2->setCellValue($s2cols[$i + 1] . '2', $provNames[$i]);
}
$sheet2->setCellValue($lastCol . '2', 'รวม');
$sheet2->getStyle('A2:' . $lastCol . '2')->applyFromArray([
    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '60A5FA']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D4E4FF']]],
]);
$sheet2->getRowDimension(2)->setRowHeight(32);

// Data rows
for ($t = 0; $t < count($caseTypesShort); $t++) {
    $r = $t + 3;
    $sheet2->setCellValue('A' . $r, $caseTypesShort[$t]);
    $rowSum = 0;
    for ($p = 0; $p < $provCount; $p++) {
        $val = $provinces[$p][$colKeys[$t]];
        $sheet2->setCellValue($s2cols[$p + 1] . $r, $val);
        $rowSum += $val;
    }
    $sheet2->setCellValue($lastCol . $r, $rowSum);

    $bgColor = ($t % 2 === 0) ? 'E8F4FD' : 'F8FBFF';
    $sheet2->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D4E4FF']]],
        'font' => ['size' => 13],
    ]);
    $sheet2->getStyle('A' . $r)->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        'font' => ['bold' => true, 'size' => 13],
    ]);
    $sheet2->getRowDimension($r)->setRowHeight(26);
}

// Footer row (รวม)
$footRow = 3 + count($caseTypesShort);
$sheet2->setCellValue('A' . $footRow, 'รวม');
$gTotal = 0;
for ($p = 0; $p < $provCount; $p++) {
    $cSum = 0;
    for ($t = 0; $t < count($colKeys); $t++) { $cSum += $provinces[$p][$colKeys[$t]]; }
    $sheet2->setCellValue($s2cols[$p + 1] . $footRow, $cSum);
    $gTotal += $cSum;
}
$sheet2->setCellValue($lastCol . $footRow, $gTotal);
$sheet2->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->applyFromArray([
    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3B82F6']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
]);
$sheet2->getRowDimension($footRow)->setRowHeight(30);

// Column widths
$sheet2->getColumnDimension('A')->setWidth(22);
for ($i = 1; $i <= $provCount + 1; $i++) {
    $sheet2->getColumnDimension($s2cols[$i])->setWidth(14);
}

// ============================================
// Output
// ============================================
ob_end_clean();
$filename = 'Dashboard_Summary_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
