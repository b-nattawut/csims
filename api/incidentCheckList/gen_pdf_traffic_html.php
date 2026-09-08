<?php
// gen_pdf_traffic_html.php - Generate PDF for Traffic Case from HTML Template

// เปิด error reporting เพื่อ debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../helpers/report_no.php';

// ==========================================
// 1. HELPER FUNCTIONS
// ==========================================

// แปลงวันที่เป็นภาษาไทย ( เช่น 12 ส.ค. 2567)
function thaiDate($datetime)
{
    if (empty($datetime)) return '';
    $timestamp = strtotime($datetime);
    $months = [null, 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $year = date('Y', $timestamp) + 543;
    return date('j', $timestamp) . ' ' . $months[date('n', $timestamp)] . ' ' . $year;
}

// แปลงเวลา ( เช่น 14:30 )
function thaiTime($datetime)
{
    if (empty($datetime)) return '';
    return date('H:i', strtotime($datetime));
}

// แปลงวันที่และเวลาเป็นภาษาไทย ( เช่น 12 ส.ค. 2567 14:30 )
function thaiDateTime($datetime)
{
    if (empty($datetime)) return '';
    // เรียกใช้ thaiDate และ thaiTime มาต่อกัน
    return thaiDate($datetime) . ' ' . thaiTime($datetime);
}

// ฟังก์ชัน Checkbox: ถ้า $condition เป็น true ให้แสดง ✓
function renderCheckbox($condition)
{
    return $condition ? '✓' : '';
}

// Helper: ดึงค่าจาก Array แบบปลอดภัย
function getVal($arr, $key, $default = '')
{
    return isset($arr[$key]) && $arr[$key] !== null ? $arr[$key] : $default;
}

// แปลงเลขที่เอกสารรูปแบบใหม่ → รูปแบบไทยสำหรับ PDF
// เช่น 10-95-69-MD0003 → ช-0003/2569
function docNoToThaiFormat($docNo)
{
    $typeMap = [
        'LC' => 'ท',
        'MD' => 'ช',
        'BM' => 'ร',
        'FR' => 'พ',
        'TF' => 'จ',
        'EV' => 'ว',
        'CM' => 'อ',
        'PS' => 'บ'
    ];
    if (preg_match('/^\d+-\d+-(\d{2})-([A-Z]{2})(\d+)$/', $docNo, $m)) {
        $subYear = $m[1];
        $typeCode = $m[2];
        $running = $m[3];
        $thaiPrefix = $typeMap[$typeCode] ?? $typeCode;
        $fullYear = '25' . $subYear;
        return $thaiPrefix . '-' . $running . '/' . $fullYear;
    }
    return $docNo;
}

// ==========================================
// 2. FETCH DATA FROM DATABASE
// ==========================================
$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) die("Error: Invalid Incident ID");

try {
    $stmt = $pdo->prepare("SELECT incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) die("Error: Data not found.");

    $data = json_decode($row['incident_checklist_data'], true);
    if (json_last_error() !== JSON_ERROR_NONE) $data = [];
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// QR CODE: ดึงเลขรับแจ้ง
// ==========================================
$qrData = '';
try {
    $stmtRn = $pdo->prepare("SELECT receiveNoti_No FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
    $stmtRn->execute([$incident_id]);
    $rnRow = $stmtRn->fetch(PDO::FETCH_ASSOC);
    if ($rnRow && !empty($rnRow['receiveNoti_No'])) {
        $qrData = $rnRow['receiveNoti_No'];
    }
} catch (Exception $e) {}

// ==========================================
// PREPARE USER MAP (ID => Fullname)
// ==========================================
$userMap = [];
try {
    $sqlUser = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname
                FROM user_profile t1
                LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id";
    $stmtUser = $pdo->query($sqlUser);

    while ($row = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        $userMap[$row['user_id']] = $row['fullname'];
    }
} catch (Exception $e) {
    // กรณี Query ไม่ผ่าน ให้ปล่อย $userMap เป็นว่างไว้
}

// ==========================================
// 3. EXTRACT DATA
// ==========================================

// --- กลุ่มข้อมูลหลัก ---
$gen        = $data['general_info'] ?? [];
$scene      = $data['scene_info'] ?? [];
$inspectorsId = $data['inspectors'] ?? [];
$purposes   = $data['inspection_purpose'] ?? [];
$forensics  = $data['forensic_officers'] ?? [];
$f_results  = $data['forensic_results'] ?? [];
$compare    = $data['comparison_results'] ?? [];
$handover   = $data['handover'] ?? [];
$photo_recs = $data['photo_records'] ?? [];

// ---------------------------------------------------------
// 3.1 ข้อมูลพื้นฐาน (Header & General Info - Fieldset 1)
// ---------------------------------------------------------

// เลขรายงาน + ปี พ.ศ. สำหรับ header ทุกหน้า
// การจัดการเลขรายงาน (เช่น TF-0001/2569)
// ดึงเลขที่รายงานภาษาไทยจากฐานข้อมูล (receiveNotiReportNo_TH) เป็นหลัก
$raw_report_no = getIncidentReportNoTH($pdo, $incident_id, getVal($gen, 'report_no'));
$report_no = '';
$report_year = '';
if (!empty($raw_report_no) && strpos($raw_report_no, '/') !== false) {
    list($report_no, $report_year) = explode('/', $raw_report_no, 2);
} else {
    $report_no = $raw_report_no;
}

$case_doc_no = convertDocNoToThai(getVal($gen, 'case_doc_no') ?: getVal($gen, 'doc_no'));
$case_date   = thaiDate(getVal($gen, 'case_date'));
$case_time   = thaiTime(getVal($gen, 'case_time'));

// ช่องทางการรับแจ้ง
$notify_methods   = getVal($gen, 'report_channel', []);
$chk_notify_phone  = renderCheckbox(in_array('ทางโทรศัพท์', $notify_methods));
$chk_notify_radio  = renderCheckbox(in_array('ทางวิทยุสื่อสาร', $notify_methods));
$chk_notify_letter = renderCheckbox(in_array('ทางหนังสือ', $notify_methods));
$chk_notify_other  = renderCheckbox(in_array('อื่นๆ', $notify_methods));
$notify_other_text = getVal($gen, 'report_channel_other');

// ข้อมูลสถานที่รับแจ้ง สภ./สน.
$police_station = getVal($gen, 'source_station');
// ข้อมูลพนักงานสอบสวน
$investigator_name  = getVal($gen['investigator'] ?? [], 'name');
$investigator_phone = getVal($gen['investigator'] ?? [], 'phone');

// ---------------------------------------------------------
// 3.2 รายละเอียดสถานที่เกิดเหตุ - รายการรถของกลางในที่เกิดเหตุ (Fieldset 2)
// ---------------------------------------------------------
$crime_location = getVal($scene, 'crime_location');

$vehiclesAtScene = $scene['vehicles_at_scene'] ?? [];


$vehicle_scene_html = '';
if (!empty($vehiclesAtScene)) {
    foreach ($vehiclesAtScene as $idx => $v) {
        $no = $idx + 1;
        $p_status = ($v['plate_status'] == 'ติด') ? 'ติด' : 'ไม่ติด';

        $vehicle_scene_html .= '<div style="margin-bottom: 8px;">';

        // บรรทัดที่ 1: หัวข้อรถ, ชนิดรถ, ยี่ห้อ
        $vehicle_scene_html .= '    <div class="fr">';
        $vehicle_scene_html .= '        <span class="fl-b" style="text-decoration:underline; min-width:85px;">รถของกลางที่ ' . $no . '</span>';
        $vehicle_scene_html .= '        <span class="fl">รถ</span><span class="fd" style="flex:2;">' . htmlspecialchars($v['detail']) . '</span>';
        $vehicle_scene_html .= '        <span class="fl">ยี่ห้อ</span><span class="fd" style="flex:2;">' . htmlspecialchars($v['brand']) . '</span>';
        $vehicle_scene_html .= '    </div>';

        // บรรทัดที่ 2: รุ่น, สี
        $vehicle_scene_html .= '    <div class="fr">';
        $vehicle_scene_html .= '        <span style="min-width:90px;"></span>'; // เว้นว่างหน้า
        $vehicle_scene_html .= '        <span class="fl">รุ่น</span><span class="fd">' . htmlspecialchars($v['model']) . '</span>';
        $vehicle_scene_html .= '        <span class="fl">สี</span><span class="fd">' . htmlspecialchars($v['color']) . '</span>';
        $vehicle_scene_html .= '    </div>';

        // บรรทัดที่ 3: สถานะป้ายทะเบียน และ เลขทะเบียน
        $vehicle_scene_html .= '    <div class="fr">';
        $vehicle_scene_html .= '        <span style="min-width:90px;"></span>'; // เว้นว่างหน้า
        $vehicle_scene_html .= '        <span class="fl">' . $p_status . ' แผ่นป้ายทะเบียน</span><span class="fd">' . htmlspecialchars($v['plate_no']) . '</span>';
        $vehicle_scene_html .= '    </div>';

        $vehicle_scene_html .= '</div>';
    }
}

// ---------------------------------------------------------
// 3.3 + 3.4 วันเวลาที่ทราบเหตุ/ตรวจเหตุ (Fieldset 3-4)
// ---------------------------------------------------------
$victim_know_date  = thaiDate(getVal($gen, 'victim_know_date'));
$victim_know_time  = thaiTime(getVal($gen, 'victim_know_time'));
$officer_know_date = thaiDate(getVal($gen, 'officer_know_date'));
$officer_know_time = thaiTime(getVal($gen, 'officer_know_time'));

$inspect_date      = thaiDate(getVal($gen, 'inspect_date'));
$inspect_time      = thaiTime(getVal($gen, 'inspect_time'));

// ---------------------------------------------------------
// 3.5 ผู้ตรวจสถานที่เกิดเหตุ (Fieldset 5)
// ---------------------------------------------------------

$inspector_rows_html = '';
if (!empty($inspectorsId)) {
    foreach ($inspectorsId as $idx => $id) {
        $name = $userMap[$id] ?? '.......................................';
        $inspector_rows_html .= '<div class="fr"><span class="fl i1">5.' . ($idx + 1) . '.</span><span class="fd">' . htmlspecialchars($name) . '</span></div>';
    }
} else {
    $inspector_rows_html = '<div class="fr"><span class="fl i1">5.1.</span><span class="fd">......................................................................</span></div>';
}

// ---------------------------------------------------------
// 3.6 จุดประสงค์ในการตรวจ (Fieldset 6)
// ---------------------------------------------------------
$sel_purposes = $purposes['selected_purposes'] ?? [];
$chk_purpose_1 = renderCheckbox(in_array('1', $sel_purposes));
$chk_purpose_2 = renderCheckbox(in_array('2', $sel_purposes));
$chk_purpose_3 = renderCheckbox(in_array('3', $sel_purposes));
$chk_purpose_4 = renderCheckbox(in_array('4', $sel_purposes));
$purpose_qty_1 = getVal($purposes, 'qty_1');
$purpose_qty_2 = getVal($purposes, 'qty_2');
$purpose_other = getVal($purposes, 'other_text');

// ---------------------------------------------------------
// 3.7 ผู้ตรวจพิสูจน์ (Fieldset 7)
// ---------------------------------------------------------

$forensic_rows_html = '';
$min_rows = 5; // กำหนดจำนวนแถวขั้นต่ำที่ต้องการแสดง (7.1 - 7.5)
$total_rows = max(count($forensics), $min_rows);

for ($i = 0; $i < $total_rows; $i++) {
    $no = $i + 1;
    $f = $forensics[$i] ?? null; // ดึงข้อมูลตาม index ถ้าไม่มีจะได้ค่า null

    // ถ้ามีข้อมูลให้ดึงชื่อ/ตำแหน่ง ถ้าไม่มีให้ใช้จุดไข่ปลา
    $name = ($f && isset($userMap[$f['user_id']])) ? $userMap[$f['user_id']] : '';
    $pos  = ($f && !empty($f['position'])) ? $f['position'] : '';

    $forensic_rows_html .= '<div style="margin-bottom: 8px;">';

    // บรรทัดที่ 1: ลำดับ 7.x และ ชื่อ (ใส่ class i1 และคุมความกว้างลำดับ)
    $forensic_rows_html .= '    <div class="fr">';
    $forensic_rows_html .= '        <span class="fl i1" style="margin-right:5px;">7.' . $no . '.</span>';
    $forensic_rows_html .= '        <span class="fd">' . htmlspecialchars($name) . '</span>';
    $forensic_rows_html .= '    </div>';

    // บรรทัดที่ 2: ตำแหน่ง (เว้นว่างหน้า 40px)
    $forensic_rows_html .= '    <div class="fr">';
    $forensic_rows_html .= '        <span style="min-width:42px;"></span>'; // เว้นว่างหน้าให้ตรงกับ i1
    $forensic_rows_html .= '        <span class="fl" style="margin-right:0px;">ตำแหน่ง</span>'; // ขยับคำว่าตำแหน่งให้พ้นเลขลำดับ
    $forensic_rows_html .= '        <span class="fd">' . htmlspecialchars($pos) . '</span>';
    $forensic_rows_html .= '    </div>';

    $forensic_rows_html .= '</div>';
}

// ---------------------------------------------------------
// 3.8 ผลการตรวจพิสูจน์ร่องรอยละเอียด (Fieldset 8)
// ---------------------------------------------------------
$case_behavior      = getVal($f_results, 'case_behavior');
$inspection_target  = getVal($f_results, 'inspection_target');
$inspection_location = getVal($f_results, 'inspection_location');
$inspect_f_date     = thaiDate(getVal($f_results, 'inspection_date'));
$inspect_f_time     = thaiTime(getVal($f_results, 'inspection_time'));

// วนลูปสร้างตารางร่องรอยแยกตามรถและด้าน 
$vehicle_analysis_html = '';
$analysis_data = $f_results['vehicle_analysis'] ?? [];

foreach ($analysis_data as $v) {
    $v_no = $v['v_index'];

    // บล็อกข้อมูลรถแต่ละคัน
    $vehicle_analysis_html .= '<div style="margin-bottom: 10px; page-break-inside: avoid;">';
    $vehicle_analysis_html .= '    <div class="fl-b" style="text-decoration:underline;">รถของกลางที่ ' . $v_no . '</div>';
    $vehicle_analysis_html .= '    <div class="fr" style="margin-top:5px;"><span class="fl">สภาพ</span><span class="fd">' . htmlspecialchars($v['condition']) . '</span></div>';

    $chk_mod_no = renderCheckbox($v['mod_status'] == 'ไม่มี');
    $chk_mod_yes = renderCheckbox($v['mod_status'] == 'มี');
    $vehicle_analysis_html .= '    <div class="fr">';
    $vehicle_analysis_html .= '        <label class="ck"><span class="cb">' . $chk_mod_no . '</span>ไม่มี</label>';
    $vehicle_analysis_html .= '        <label class="ck" style="margin-right: 0px;"><span class="cb">' . $chk_mod_yes . '</span>มี (การต่อเติมหรือดัดแปลงสภาพ)</label>';
    $vehicle_analysis_html .= '       <span class="fd">' . htmlspecialchars($v['mod_detail']) . '</span>';
    $vehicle_analysis_html .= '    </div>';

    // --- ส่วนหัวตาราง (ปรับให้มีช่องว่างรองรับเลขลำดับเพื่อให้เส้นประตรงกัน) ---
    $vehicle_analysis_html .= '    <div class="fr" style="border-top:1.5px solid #000; border-bottom:1.5px solid #000; background:#f9f9f9; margin-top:5px; font-weight:bold; font-size:10px; align-items: stretch;">';
    $vehicle_analysis_html .= '        <div style="flex:1; text-align:center; border-right:1px dashed #000; padding: 2px 0;">ร่องรอย/ความเสียหาย...บริเวณ/ตำแหน่ง</div>';
    $vehicle_analysis_html .= '        <div style="width:80px; text-align:center; padding: 2px 0;">สูงจากพื้น (cm.)</div>';
    $vehicle_analysis_html .= '    </div>';

    // รายการร่องรอย 4 ด้าน (หน้า/ซ้าย/ขวา/หลัง)
    $sides = ['front' => 'ด้านหน้า', 'left' => 'ด้านซ้าย', 'right' => 'ด้านขวา', 'back' => 'ด้านหลัง'];
    // --- วนลูปด้านรถ ---
    foreach ($sides as $key => $label) {
        $vehicle_analysis_html .= '    <div style="font-weight:bold; font-size:11px; margin-top:5px; padding-left:5px;">' . $label . '</div>';

        $traces = $v['traces'][$key] ?? [];
        for ($i = 0; $i < max(count($traces), 4); $i++) {
            $t = $traces[$i] ?? null;
            $detail = $t ? htmlspecialchars($t['detail']) : '';
            $height = $t ? htmlspecialchars($t['height']) : '';

            // --- ส่วนเนื้อหา (ปรับโครงสร้าง Flex ให้ตรงกับหัว) ---
            $vehicle_analysis_html .= '    <div class="fr" style="margin-bottom:0; align-items: stretch;">';

            // ช่องซ้าย: รวมเลขลำดับและรายละเอียดไว้ใน flex:1 ก้อนเดียวกัน แล้วขีดเส้นประขวา
            $vehicle_analysis_html .= '        <div style="flex:1; display:flex; border-right:1px dashed #000; align-items:center; padding: 2px 0;">';
            $vehicle_analysis_html .= '            <span style="width:10px;"></span>';
            $vehicle_analysis_html .= '            <span style="width:15px; font-size:10px; padding-left:5px; color:#666;">' . ($i + 1) . '.</span>';
            $vehicle_analysis_html .= '            <div class="fd" style="flex:1; margin-right:5px; border-bottom:1px dotted #ccc;">' . $detail . '</div>';
            $vehicle_analysis_html .= '        </div>';

            // ช่องขวา: ความกว้างคงที่ 80px เท่ากับหัวตาราง
            $vehicle_analysis_html .= '        <div style="width:80px; display:flex; align-items:center; justify-content:center;">';
            $vehicle_analysis_html .= '            <div class="fd-s" style="width:60px; border-bottom:1px dotted #ccc;">' . $height . '</div>';
            $vehicle_analysis_html .= '        </div>';

            $vehicle_analysis_html .= '    </div>';
        }
    }
    $vehicle_analysis_html .= '</div>';
}

// ---------------------------------------------------------
// 3.9 ผลการตรวจเปรียบเทียบ (Fieldset 9)
// ---------------------------------------------------------
$compare_total = getVal($compare, 'total_items');
$compare_list_html = '';
$comparisons = $compare['comparisons'] ?? [];

$min_compare_rows = 3;
$total_compare_rows = max(count($comparisons), $min_compare_rows);

for ($i = 0; $i < $total_compare_rows; $i++) {
    $c = $comparisons[$i] ?? null;
    $item_no = $c ? $c['item_no'] : "9." . ($i + 1);

    $compare_list_html .= '<div style="margin-bottom:15px; line-height: 1.8;">';
    // --- บรรทัดที่ 1: เลขลำดับ + รอย + บริเวณ ---
    $compare_list_html .= '  <div class="fr i1">';
    $compare_list_html .= '    <span class="fl-b">' . $item_no . '</span>';
    $compare_list_html .= '    <span class="fl" style="margin-right:1px">รอย</span>';
    $compare_list_html .= '    <span class="fd" style="min-width:50px;">' . ($c ? htmlspecialchars($c['trace_type']) : '') . '</span>';
    $compare_list_html .= '    <span class="fl" style="margin-right:1px">บริเวณ</span>';
    $compare_list_html .= '    <span class="fd" style="min-width:50px;">' . ($c ? htmlspecialchars($c['area_a']) : '') . '</span>';
    $compare_list_html .= '  </div>';

    // --- บรรทัดที่ 2: ตามผลการตรวจในข้อ (ใส่ i1 เพื่อย่อหน้า) ---
    $compare_list_html .= '  <div class="fr i1">';
    $compare_list_html .= '    <span class="fl" style="margin-right:0px">ตามผลการตรวจในข้อ</span>';
    $compare_list_html .= '    <span class="fd" style="min-width:50px; text-align:center;">' . ($c ? htmlspecialchars($c['ref_a']) : '') . '</span>';
    $compare_list_html .= '  </div>';

    // --- บรรทัดที่ 3: มีลักษณะร่องรอย... (ใส่ i1 เพื่อย่อหน้า) ---
    $compare_list_html .= '  <div class="fr i1">';
    $compare_list_html .= '    <span class="fl" >มีลักษณะร่องรอยและระดับความสูงเข้ากันได้กับรอยครูด</span>';
    $compare_list_html .=     'บริเวณ' . '<span class="fd" style="margin-left:2px; min-width:60px;">' .  ($c ? htmlspecialchars($c['area_b']) : '') . '</span>';
    $compare_list_html .= '  </div>';

    // --- บรรทัดที่ 4: ตามผลการตรวจในข้อ (ใส่ i1 เพื่อย่อหน้า) ---
    $compare_list_html .= '  <div class="fr i1">';
    $compare_list_html .= '    <span class="fl" style="margin-right:0px">ตามผลการตรวจในข้อ</span>';
    $compare_list_html .= '    <span class="fd" style="min-width:60px; text-align:center;">' . ($c ? htmlspecialchars($c['ref_b']) : '') . '</span>';
    $compare_list_html .= '  </div>';

    $compare_list_html .= '</div>';
}

// ---------------------------------------------------------
// 3.10 ความเห็น (Fieldset 10)
// ---------------------------------------------------------
$forensic_opinion = getVal($data, 'opinion');

// ---------------------------------------------------------
// 3.11 ส่งมอบ (Fieldset 11)
// ---------------------------------------------------------
$inspect_end_date = thaiDate(getVal($handover, 'inspection_end_date'));
$inspect_end_time = thaiTime(getVal($handover, 'inspection_end_time'));

$receiver_name = $userMap[getVal($handover['receiver'] ?? [], 'name_id')] ?? '';
$receiver_pos  = getVal($handover['receiver'] ?? [], 'position');
$sender_name   = $userMap[getVal($handover['sender'] ?? [], 'name_id')] ?? '';
$sender_pos    = getVal($handover['sender'] ?? [], 'position');

// ---------------------------------------------------------
// 3.12 บันทึกการถ่ายภาพ (Photo Records - Fieldset 13)
// ---------------------------------------------------------

$photo_id_start = getVal($photo_recs, 'start');  // รหัสภาพถ่ายที่
$photo_id_end   = getVal($photo_recs, 'end');    // ถึง
$photo_amount   = getVal($photo_recs, 'amount'); // จำนวนภาพ
$photo_recorder = getVal($photo_recs, 'recorder_name'); // ผู้จดบันทึก
$photo_datetime = thaiDateTime(getVal($photo_recs, 'recorder_datetime')); // วัน/เวลา

// ---------------------------------------------------------
// SIGNATURES (base64 → <img> tags)
// ---------------------------------------------------------
$signatures = $data['signatures'] ?? [];

// Helper: อ่าน BLOB จาก DB แปลงเป็น base64 (เหมือน fingerprint)
function loadBlobAsBase64($pdo, $fileId) {
    if (empty($fileId)) return '';
    try {
        $stmt = $pdo->prepare("SELECT file_name FROM incident_checklist_transaction_file WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['file_name'])) return '';
        $blobData = $row['file_name'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($blobData);
        if (!$mime || strpos($mime, 'image') === false) $mime = 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($blobData);
    } catch (Exception $e) {
        return '';
    }
}

// Helper: สร้าง <img> tag จาก signature
function renderSignatureImg($sigData, $pdo = null)
{
    if (empty($sigData)) return '';
    // 1) BLOB จาก DB (ข้อมูลใหม่)
    if (is_array($sigData) && !empty($sigData['file_id']) && $pdo) {
        $base64 = loadBlobAsBase64($pdo, $sigData['file_id']);
        if (!empty($base64)) {
            return '<img src="' . $base64 . '" style="display:block; max-width:120px; max-height:38px; object-fit:contain; margin:0 auto;" alt="ลายเซ็น">';
        }
    }
    // 2) ไฟล์บนดิสก์ (ข้อมูลเก่า)
    if (is_array($sigData) && !empty($sigData['filename'])) {
        $path = __DIR__ . '/../../uploads_2/checklist_traffic_signatures/' . $sigData['filename'];
        if (file_exists($path)) {
            $bin = @file_get_contents($path);
            if ($bin !== false) {
                $mime = @mime_content_type($path) ?: 'image/png';
                $base64 = 'data:'.$mime.';base64,'.base64_encode($bin);
                return '<img src="' . $base64 . '" style="display:block; max-width:120px; max-height:38px; object-fit:contain; margin:0 auto;" alt="ลายเซ็น">';
            }
        }
    }
    // 3) base64 เดิมใน JSON (ข้อมูลเก่ามาก)
    $base64 = '';
    if (is_array($sigData) && !empty($sigData['base64'])) {
        $base64 = $sigData['base64'];
    } elseif (is_string($sigData)) {
        $base64 = $sigData;
    }
    if (empty($base64) || strpos($base64, 'data:image') === false) return '';
    return '<img src="' . $base64 . '" style="display:block; max-width:120px; max-height:38px; object-fit:contain; margin:0 auto;" alt="ลายเซ็น">';
}

$receiver_signature_img = renderSignatureImg($signatures['receiver_sig'] ?? '', $pdo);
$sender_signature_img = renderSignatureImg($signatures['sender_sig'] ?? '', $pdo);


// ---------------------------------------------------------
// PHOTO RECORDS
// ---------------------------------------------------------

// สร้าง Photo images HTML — แต่ละรูปอยู่คนละหน้า
$photos = $data['photos'] ?? [];
$photo_images = ''; // รูปแรกใส่ในหน้า Page 8 เดิม (ของจราจรยังไม่รู้ว่ามีกี่ page)
$photo_extra_pages = ''; // รูปที่ 2+ สร้างเป็นหน้าใหม่

// โหลดรูปภาพ: BLOB ก่อน (ข้อมูลใหม่), ดิสก์/base64 สำรอง (ข้อมูลเก่า)
$photosDir = __DIR__ . '/../../uploads_2/checklist_traffic_photos/';
$validPhotos = [];
if (!empty($photos)) {
    foreach ($photos as $idx => $photo) {
        $pBase64 = '';
        // 1) BLOB จาก DB
        if (is_array($photo) && !empty($photo['file_id'])) {
            $pBase64 = loadBlobAsBase64($pdo, $photo['file_id']);
        }
        // 2) base64 เดิมใน JSON (ข้อมูลเก่า)
        if (empty($pBase64) && is_array($photo) && !empty($photo['base64'])) {
            $pBase64 = $photo['base64'];
        }
        // 3) ไฟล์บนดิสก์ (ข้อมูลเก่า)
        if (empty($pBase64) && is_array($photo) && !empty($photo['filename'])) {
            $fp = $photosDir . $photo['filename'];
            if (file_exists($fp)) {
                $bin = @file_get_contents($fp);
                if ($bin !== false) {
                    $mime = @mime_content_type($fp) ?: 'image/jpeg';
                    $pBase64 = 'data:'.$mime.';base64,'.base64_encode($bin);
                }
            }
        }
        if (!empty($pBase64) && strpos($pBase64, 'data:image') !== false) {
            $validPhotos[] = $pBase64;
        }
    }
}

if (!empty($validPhotos)) {
    foreach ($validPhotos as $pIdx => $pBase64) {
        $photoNum = $pIdx + 1;
        $photoHtml = '<div style="flex:1; display:flex; align-items:center; justify-content:center;">';
        $photoHtml .= '<div style="text-align:center;">';
        $photoHtml .= '<img src="' . $pBase64 . '" style="max-width:100%; max-height:850px; object-fit:contain; border:1px solid #ccc;" alt="ภาพที่ ' . $photoNum . '">';
        $photoHtml .= '<div style="font-size:11px; color:#333; margin-top:4px;">ภาพที่ ' . $photoNum . ' / ' . count($validPhotos) . '</div>';
        $photoHtml .= '</div>';
        $photoHtml .= '</div>';

        if ($pIdx === 0) {
            // รูปแรก → ใส่ในหน้า Page 3 เดิม 
            $photo_images = $photoHtml;
        } else {
            // รูปที่ 2+ → สร้างหน้าใหม่ ( checklist ระเบิดมี 9 หน้า เลยจะเริ่มตั้งแต่หน้า 10 ไปสำหรับรูปที่ 2+) 
            // (ของจราจรยังไม่รู้ว่ามีกี่ page ต้องมาปรับอีกที + ปรับส่วนของ footer ให้ถูก)
            $pageNum = 3 + $pIdx;
            $photo_extra_pages .= '
                <div class="page" style="display:flex; flex-direction:column;">
                    <div class="form-header">
                        <div class="header-logo">
                            <img src="../../images/office-of-police-forensic-icon.jpg" alt="ตราสำนักงานตำรวจแห่งชาติ">
                        </div>
                        <div class="header-center">
                            <div class="title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
                            <div class="title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับจราจร</div>
                            <div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ (ต่อ)</div>
                        </div>
                        <div class="header-right">
                            <div class="doc-box">
                                <div class="doc-line">เลขรับที่/เลขรายงาน <span style="display:inline-block;min-width:60px;border-bottom:1px dotted #888;text-align:center;">' . htmlspecialchars($report_no) . '</span> / <span style="display:inline-block;min-width:40px;border-bottom:1px dotted #888;text-align:center;">' . htmlspecialchars($report_year) . '</span></div>
                                <div class="doc-line">หน้าที่ ' . $pageNum . ' / {{total_pages}}</div>
                            </div>
                        </div>
                    </div>
                    ' . $photoHtml . '

                    <div style="margin-top:12px; display:flex; flex-direction:column; align-items:flex-end;">
                        <div class="fr" style="width:auto;">
                            <span class="fl">ผู้จดบันทึก</span>
                            <span class="fd" style="width:250px;">' . htmlspecialchars($photo_recorder) . '</span>
                        </div>
                        <div class="fr" style="width:auto;">
                            <span class="fl">วัน /เวลา</span>
                            <span class="fd" style="width:250px;">' . htmlspecialchars($photo_datetime) . '</span>
                        </div>
                    </div>

                    <div class="form-footer" style="margin-top:auto;">
                        <div class="form-footer-left">
                            <strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ
                        </div>
                        <div class="form-footer-right">
                            F-CS-... แก้ไขครั้งที่...<br>
                            แก้ไขวันที่...<br>
                            เริ่มใช้...
                        </div>
                    </div>
                </div>';
        }
    }
}

if (empty($photo_images)) {
    $photo_images = '<div style="flex:1; display:flex; align-items:center; justify-content:center;"><div style="color:#999; font-style:italic;">ไม่มีภาพถ่าย</div></div>';
}

// ==========================================
// 4. READ HTML TEMPLATE & REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_traffic_preview.html');

// สร้าง array ของ placeholders และค่าที่จะแทนที่
$replacements = [
    // --- Header ---
    '{{report_no}}'   => htmlspecialchars($report_no),
    '{{report_year}}' => htmlspecialchars($report_year),
    '{{qr_data}}' => htmlspecialchars($qrData),

    // --- 1. การรับแจ้งเหตุ ---
    '{{case_doc_no}}'      => htmlspecialchars($case_doc_no),
    '{{case_date}}'        => htmlspecialchars($case_date),
    '{{case_time}}'        => htmlspecialchars($case_time),
    '{{chk_notify_phone}}' => $chk_notify_phone,
    '{{chk_notify_radio}}' => $chk_notify_radio,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_other}}' => $chk_notify_other,
    '{{notify_other_text}}' => htmlspecialchars($notify_other_text),
    '{{police_station}}'   => htmlspecialchars($police_station),
    '{{investigator_name}}' => htmlspecialchars($investigator_name),
    '{{investigator_phone}}' => htmlspecialchars($investigator_phone),

    // --- 2. สถานที่เกิดเหตุ ---
    '{{crime_location}}' => htmlspecialchars($crime_location),
    '{{vehicle_scene_html}}' => $vehicle_scene_html,

    // --- 3. วันเวลาที่ทราบเหตุ / 4. ตรวจเหตุ ---
    '{{victim_know_date}}'  => htmlspecialchars($victim_know_date),
    '{{victim_know_time}}'  => htmlspecialchars($victim_know_time),
    '{{officer_know_date}}' => htmlspecialchars($officer_know_date),
    '{{officer_know_time}}' => htmlspecialchars($officer_know_time),
    '{{inspect_date}}'      => htmlspecialchars($inspect_date),
    '{{inspect_time}}'      => htmlspecialchars($inspect_time),

    // --- 5. รายชื่อผู้ตรวจสถานที่ ---
    '{{inspector_rows}}' => $inspector_rows_html,

    // --- 6. จุดประสงค์ในการตรวจ ---
    '{{chk_purpose_1}}' => $chk_purpose_1,
    '{{chk_purpose_2}}' => $chk_purpose_2,
    '{{chk_purpose_3}}' => $chk_purpose_3,
    '{{chk_purpose_4}}' => $chk_purpose_4,
    '{{purpose_qty_1}}' => htmlspecialchars($purpose_qty_1),
    '{{purpose_qty_2}}' => htmlspecialchars($purpose_qty_2),
    '{{purpose_other}}' => htmlspecialchars($purpose_other),

    // --- 7. ผู้ตรวจพิสูจน์ ---
    '{{forensic_rows}}' => $forensic_rows_html,

    // --- 8. ผลการตรวจพิสูจน์ ---
    '{{case_behavior}}'       => htmlspecialchars($case_behavior),
    '{{inspection_target}}'   => htmlspecialchars($inspection_target),
    '{{inspection_location}}' => htmlspecialchars($inspection_location),
    '{{inspect_f_date}}'      => htmlspecialchars($inspect_f_date),
    '{{inspect_f_time}}'      => htmlspecialchars($inspect_f_time),
    '{{vehicle_analysis_html}}' => $vehicle_analysis_html,

    // --- 9. ผลการตรวจเปรียบเทียบ ---
    '{{compare_total}}'     => htmlspecialchars($compare_total),
    '{{compare_list_html}}' => $compare_list_html,

    // --- 10. ความเห็น ---
    '{{forensic_opinion}}' => htmlspecialchars($forensic_opinion),

    // --- 11. การส่งมอบสถานที่เกิดเหตุ ---
    '{{inspect_end_date}}' => htmlspecialchars($inspect_end_date),
    '{{inspect_end_time}}' => htmlspecialchars($inspect_end_time),

    '{{receiver_signature_img}}' => $receiver_signature_img,
    '{{receiver_name}}'      => htmlspecialchars($receiver_name),
    '{{receiver_pos}}'       => htmlspecialchars($receiver_pos),
    '{{sender_signature_img}}'   => $sender_signature_img,
    '{{sender_name}}'        => htmlspecialchars($sender_name),
    '{{sender_pos}}'         => htmlspecialchars($sender_pos),

    // --- 12. บันทึกการถ่ายภาพ ---
    '{{photo_images}}'    => $photo_images,
    '{{photo_id_start}}'  => htmlspecialchars($photo_id_start),
    '{{photo_id_end}}'    => htmlspecialchars($photo_id_end),
    '{{photo_amount}}'    => htmlspecialchars($photo_amount),
    '{{photo_recorder}}'  => htmlspecialchars($photo_recorder),
    '{{photo_datetime}}'  => htmlspecialchars($photo_datetime),

];

// แทนที่ค่าใน HTML
$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// เพิ่มหน้ารูปภาพเพิ่มเติม (รูปที่ 2+) ก่อน </body>
if (!empty($photo_extra_pages)) {
    $htmlContent = str_replace('</body>', $photo_extra_pages . "\n</body>", $htmlContent);
}

// คำนวณจำนวนหน้ารวม: 3 หน้าพื้นฐาน + หน้ารูปเพิ่ม (รูปที่ 2 เป็นต้นไป) (ต้องมาแก้เลขหน้าอีกที ดูก่อนว่ามีกี่หน้า)
$basePages = 3;
$extraPhotoPages = (count($validPhotos) > 1) ? count($validPhotos) - 1 : 0;
$total_pages = $basePages + $extraPhotoPages;
$htmlContent = str_replace('{{total_pages}}', $total_pages, $htmlContent);

// ==========================================
// 5. OUTPUT HTML 
// ==========================================
header('Content-Type: text/html; charset=utf-8');
echo $htmlContent;
