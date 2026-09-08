<?php
/**
 * gen_pdf.php - Generate PDF from incident_checklist_transaction
 * 
 * รับ parameter: incident_id (GET)
 * ดึงข้อมูลจาก: incident_checklist_transaction.incident_checklist_data (JSON)
 * 
 * ใช้ mPDF เพื่อ generate PDF 7 หน้า
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../db_config.php';

// ==========================================
// 1. รับ Parameter และดึงข้อมูลจาก Database
// ==========================================

$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) {
    die("Error: กรุณาระบุ incident_id");
}

// ดึงข้อมูลจาก database
try {
    $stmt = $pdo->prepare("SELECT incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        die("Error: ไม่พบข้อมูล Checklist สำหรับ incident_id: " . $incident_id);
    }

    // Decode JSON data - ตัวแปร $data จะถูกใช้ในไฟล์ template
    $data = json_decode($result['incident_checklist_data'], true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        die("Error: ไม่สามารถอ่านข้อมูล JSON ได้ - " . json_last_error_msg());
    }

    // Debug: บันทึกข้อมูลที่ดึงจาก DB
    @file_put_contents(__DIR__ . '/../../logs/gen_pdf_data_debug_' . $incident_id . '.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    @file_put_contents(__DIR__ . '/../../logs/gen_pdf_raw_debug_' . $incident_id . '.txt', $result['incident_checklist_data']);

    // Debug: แสดงข้อมูลสำคัญ
    $debugInfo = "=== DEBUG INFO ===\n";
    $debugInfo .= "report_no: " . ($data['general_info']['report_no'] ?? 'NOT SET') . "\n";
    $debugInfo .= "investigator name: " . ($data['general_info']['investigator']['firstname'] ?? 'NOT SET') . "\n";
    $debugInfo .= "location: " . ($data['general_info']['location_detail'] ?? 'NOT SET') . "\n";
    $debugInfo .= "victim name: " . ($data['general_info']['victim']['firstname'] ?? 'NOT SET') . "\n";
    $debugInfo .= "case_type: " . ($data['general_info']['case_type'] ?? 'NOT SET') . "\n";
    @file_put_contents(__DIR__ . '/../../logs/gen_pdf_keys_' . $incident_id . '.txt', $debugInfo);

    // ดึงข้อมูลผู้อนุมัติจากตาราง rn_ReceiveNotiApprove (seqSignature = 3)
    $stmtApprover = $pdo->prepare("SELECT fullName, positionName FROM rn_ReceiveNotiApprove WHERE ComplaintsID = ? AND seqSignature = 3");
    $stmtApprover->execute([$incident_id]);
    $approverData = $stmtApprover->fetch(PDO::FETCH_ASSOC);

    // เพิ่มข้อมูลผู้อนุมัติลงใน $data
    $data['approver_name'] = $approverData['fullName'] ?? '';
    $data['approver_position'] = $approverData['positionName'] ?? '';

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// 2. ตั้งค่า mPDF
// ==========================================

$defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new \Mpdf\Mpdf([
    'fontDir' => array_merge($fontDirs, [
        __DIR__ . '/../../fonts/Sarabun',
    ]),
    'fontdata' => $fontData + [
        'sarabun' => [
            'R'  => 'THSarabun-Regular.ttf',
            'B'  => 'THSarabun-Bold.ttf',
            'I'  => 'THSarabun-Italic.ttf',
            'BI' => 'THSarabun-BoldItalic.ttf',
        ]
    ],
    'default_font' => 'sarabun',
    'default_font_size' => 16,
    'format' => 'A4',
    'margin_left' => 5,
    'margin_right' => 5,
    'margin_top' => 10,
    'margin_bottom' => 5,
    'setAutoTopMargin' => 'stretch',
    'setAutoBottomMargin' => 'stretch',
]);

// ===== สำคัญมาก! ป้องกันการหดของฟอร์ม =====
$mpdf->shrink_tables_to_fit = 0;        // ห้ามหดตาราง
$mpdf->keep_table_proportions = true;   // คงสัดส่วนตาราง
$mpdf->use_kwt = false;                  // ปิด Keep-With-Table เพื่อให้แบ่งหน้าได้ตามปกติ
$mpdf->autoPageBreak = true;            // เปิด auto page break

// ==========================================
// 3. Include Template (ใช้ตัวแปร $data และ $mpdf)
// ==========================================

// Include ไฟล์ที่มี Helper Functions และ HTML Pages
// ไฟล์ template จะใช้ตัวแปร $data และ $mpdf ที่เตรียมไว้ข้างบน
include __DIR__ . '/gen_pdf_template.php';
