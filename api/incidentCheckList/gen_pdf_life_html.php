<?php
// gen_pdf_life_html.php - Generate PDF for Life/Body Case from HTML Template

// เปิด error reporting เพื่อ debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../helpers/report_no.php';

// ==========================================
// 1. HELPER FUNCTIONS
// ==========================================

// แปลงวันที่เป็นภาษาไทย (12 ส.ค. 2567)
function thaiDate($datetime)
{
    if (empty($datetime)) return '';
    $timestamp = strtotime($datetime);
    $months = [null, 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $year = date('Y', $timestamp) + 543;
    return date('j', $timestamp) . ' ' . $months[date('n', $timestamp)] . ' ' . $year;
}

// แปลงเวลา (14:30)
function thaiTime($datetime)
{
    if (empty($datetime)) return '';
    return date('H:i', strtotime($datetime));
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
        'LC' => 'ท', 'MD' => 'ช', 'BM' => 'ร', 'FR' => 'พ',
        'TF' => 'จ', 'EV' => 'ว', 'CM' => 'อ', 'PS' => 'บ'
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
$gen = isset($data['general_info']) ? $data['general_info'] : [];
$scene = $data['scene_characteristics'] ?? [];
$inspection = $data['inspection_result'] ?? [];
$handover = $data['handover'] ?? [];
$inspectorIds = $data['inspectors'] ?? [];

// ข้อมูลเบื้องต้น
$case_doc_no = convertDocNoToThai(getVal($gen, 'case_doc_no') ?: getVal($gen, 'doc_no'));
$case_date = thaiDate(getVal($gen, 'report_datetime'));
$case_time = thaiTime(getVal($gen, 'report_datetime'));

// เลขรายงาน + ปี พ.ศ. สำหรับ header ทุกหน้า
// ค่าจาก DB เก็บรวมกัน เช่น "MD-0001/2569"
$raw_report_no = getIncidentReportNoTH($pdo, $incident_id, getVal($gen, 'report_no'));
$report_no = '';
$report_year = '';
if (!empty($raw_report_no) && strpos($raw_report_no, '/') !== false) {
    $parts = explode('/', $raw_report_no);
    $report_no = $parts[0]; // "MD-0001"
    $report_year = $parts[1]; // "2569" — ใช้เต็ม
} else {
    $report_no = $raw_report_no;
}
// แปลงตัวย่อเป็นภาษาไทย สำหรับ PDF เท่านั้น
$thaiPrefixMap = ['LC-' => 'ท-', 'MD-' => 'ช-', 'BM-' => 'ร-', 'FR-' => 'พ-', 'TF-' => 'จร-', 'EV-' => 'ต-', 'CM-' => 'ต-', 'PS-' => 'ต-'];
foreach ($thaiPrefixMap as $en => $th) {
    if (strpos($report_no, $en) === 0) {
        $report_no = $th . substr($report_no, strlen($en));
        break;
    }
}

// ช่องทางการรับแจ้ง
$channel = getVal($gen, 'report_channel');
$notify_other_text = getVal($gen, 'report_channel_other');

// รองรับทั้ง array และ string
if (is_array($channel)) {
    $chk_notify_phone = renderCheckbox(in_array('phone', $channel) || in_array('ทางโทรศัพท์', $channel));
    $chk_notify_radio = renderCheckbox(in_array('radio', $channel) || in_array('ทางวิทยุสื่อสาร', $channel));
    $chk_notify_letter = renderCheckbox(in_array('document', $channel) || in_array('letter', $channel) || in_array('ทางหนังสือ', $channel));
    // ถ้ามีข้อความในช่อง "อื่นๆ" = ติ๊กถูกอัตโนมัติ
    $chk_notify_other = renderCheckbox(in_array('other', $channel) || in_array('อื่นๆ', $channel) || !empty(trim($notify_other_text)));
} else {
    $chk_notify_phone = renderCheckbox($channel == 'phone' || $channel == 'ทางโทรศัพท์');
    $chk_notify_radio = renderCheckbox($channel == 'radio' || $channel == 'ทางวิทยุสื่อสาร');
    $chk_notify_letter = renderCheckbox($channel == 'document' || $channel == 'letter' || $channel == 'ทางหนังสือ');
    $chk_notify_other = renderCheckbox($channel == 'other' || $channel == 'อื่นๆ' || !empty(trim($notify_other_text)));
}

// ข้อมูลสถานที่รับแจ้ง
$police_station = getVal($gen, 'source_station');
$location_at = getVal($gen, 'document_no');
// record_date อาจเป็นข้อความตรงๆ หรือวันที่
$raw_record_date = getVal($gen, 'document_date');
$record_date = !empty($raw_record_date) ? (strtotime($raw_record_date) ? thaiDate($raw_record_date) : $raw_record_date) : '';

// ข้อมูลพนักงานสอบสวน
$investigator = isset($gen['investigator']) ? $gen['investigator'] : [];
$investigator_name = getVal($investigator, 'firstname') . ' ' . getVal($investigator, 'lastname');
$investigator_phone = getVal($investigator, 'phone');

// ข้อมูลสถานที่เกิดเหตุ
$crime_location = getVal($gen, 'location_detail');

// ข้อมูลผู้ประสบเหตุ
// ข้อมูลผู้ประสบเหตุ - แยกตามประเภท
$allVictims = $gen['all_victims'] ?? [];
// สร้างแถวผู้ประสบเหตุแบบ dynamic
$victimRowsHtml = '';
$dead_name = '';
$dead_age = '';
$injured_name = '';
$injured_age = '';
$missing_name = '';
$missing_age = '';

// จัดกลุ่มผู้ประสบเหตุตามประเภท
$victimsByType = [
    'ผู้เสียชีวิต' => [],
    'ผู้บาดเจ็บ' => [],
    'ผู้สูญหาย' => []
];

if (!empty($allVictims)) {
    foreach ($allVictims as $v) {
        $vType = $v['type'] ?? '';
        // map ชื่อภาษาอังกฤษเป็นไทย
        if ($vType == 'deceased') $vType = 'ผู้เสียชีวิต';
        elseif ($vType == 'injured') $vType = 'ผู้บาดเจ็บ';
        elseif ($vType == 'missing') $vType = 'ผู้สูญหาย';

        if (isset($victimsByType[$vType])) {
            $victimsByType[$vType][] = $v;
        }
        // เก็บค่าสำหรับ fallback ด้วย
        if ($vType == 'ผู้เสียชีวิต' && empty($dead_name)) {
            $dead_name = $v['name'] ?? ''; $dead_age = $v['age'] ?? '';
        } elseif ($vType == 'ผู้บาดเจ็บ' && empty($injured_name)) {
            $injured_name = $v['name'] ?? ''; $injured_age = $v['age'] ?? '';
        } elseif ($vType == 'ผู้สูญหาย' && empty($missing_name)) {
            $missing_name = $v['name'] ?? ''; $missing_age = $v['age'] ?? '';
        }
    }
}

// Fallback: ถ้าไม่มี all_victims ให้ใช้ข้อมูลเดิมจาก victim
if (empty($allVictims)) {
    $victim = isset($gen['victim']) ? $gen['victim'] : [];
    $vicStatus = getVal($victim, 'status');
    $vName = trim(getVal($victim, 'firstname') . ' ' . getVal($victim, 'lastname'));
    $vAge = getVal($victim, 'age');
    if ($vicStatus == 'deceased' || $vicStatus == 'ผู้เสียชีวิต') {
        $victimsByType['ผู้เสียชีวิต'][] = ['name' => $vName, 'age' => $vAge];
        $dead_name = $vName; $dead_age = $vAge;
    } elseif ($vicStatus == 'injured' || $vicStatus == 'ผู้บาดเจ็บ') {
        $victimsByType['ผู้บาดเจ็บ'][] = ['name' => $vName, 'age' => $vAge];
        $injured_name = $vName; $injured_age = $vAge;
    } elseif ($vicStatus == 'missing' || $vicStatus == 'ผู้สูญหาย') {
        $victimsByType['ผู้สูญหาย'][] = ['name' => $vName, 'age' => $vAge];
        $missing_name = $vName; $missing_age = $vAge;
    }
}

// สร้าง HTML แถวผู้ประสบเหตุ
$typeLabels = [
    'ผู้เสียชีวิต' => 'ผู้เสียชีวิต',
    'ผู้บาดเจ็บ' => 'ผู้บาดเจ็บ',
    'ผู้สูญหาย' => 'ผู้สูญหาย'
];
$isFirstRow = true;
foreach ($typeLabels as $typeKey => $typeLabel) {
    $victims = $victimsByType[$typeKey];
    if (!empty($victims)) {
        foreach ($victims as $idx => $v) {
            $chk = '✓';
            $name = htmlspecialchars($v['name'] ?? '');
            $age = htmlspecialchars($v['age'] ?? '');
            // แถวแรกของประเภทนี้ แสดง checkbox + label
            if ($idx === 0) {
                $marginStyle = $isFirstRow ? ' style="margin-top:2px;"' : '';
                $victimRowsHtml .= '<div class="fr"' . $marginStyle . '>'
                    . '<label class="ck"><span class="cb">' . $chk . '</span>' . $typeLabel . '</label>'
                    . '<span class="fl">ชื่อ</span>'
                    . '<span class="fd">' . $name . '</span>'
                    . '<span class="fl">อายุ</span>'
                    . '<span class="fd-s">' . $age . '</span>'
                    . '<span class="fl">ปี</span>'
                    . '</div>';
            } else {
                // แถวเพิ่มเติม — แสดง ✓ + ประเภทเหมือนกัน
                $victimRowsHtml .= '<div class="fr">'
                    . '<label class="ck"><span class="cb">✓</span>' . $typeLabel . '</label>'
                    . '<span class="fl">ชื่อ</span>'
                    . '<span class="fd">' . $name . '</span>'
                    . '<span class="fl">อายุ</span>'
                    . '<span class="fd-s">' . $age . '</span>'
                    . '<span class="fl">ปี</span>'
                    . '</div>';
            }
            $isFirstRow = false;
        }
    } else {
        // ไม่มีคนประเภทนี้ แสดงแถวเปล่า
        $marginStyle = $isFirstRow ? ' style="margin-top:2px;"' : '';
        $victimRowsHtml .= '<div class="fr"' . $marginStyle . '>'
            . '<label class="ck"><span class="cb"></span>' . $typeLabel . '</label>'
            . '<span class="fl">ชื่อ</span>'
            . '<span class="fd"></span>'
            . '<span class="fl">อายุ</span>'
            . '<span class="fd-s"></span>'
            . '<span class="fl">ปี</span>'
            . '</div>';
        $isFirstRow = false;
    }
}

// สำหรับใช้ในส่วนอื่น (body diagram fallback)
$victim_name = !empty($dead_name) ? $dead_name : (!empty($injured_name) ? $injured_name : $missing_name);
$victim_age = !empty($dead_age) ? $dead_age : (!empty($injured_age) ? $injured_age : $missing_age);

// วันเวลาที่ทราบเหตุ/เกิดเหตุ
$victim_know_date = thaiDate(getVal($gen, 'incident_datetime'));
$victim_know_time = thaiTime(getVal($gen, 'incident_datetime'));
$officer_know_date = thaiDate(getVal($gen, 'investigator_known_datetime'));
$officer_know_time = thaiTime(getVal($gen, 'investigator_known_datetime'));

// วันเวลาที่ตรวจเหตุ
$inspect_date = thaiDate(getVal($gen, 'inspection_datetime'));
$inspect_time = thaiTime(getVal($gen, 'inspection_datetime'));
$inspect_additional_date = thaiDate(getVal($gen, 'inspection_additional_datetime'));
$inspect_additional_time = thaiTime(getVal($gen, 'inspection_additional_datetime'));

// ผู้ตรวจสถานที่เกิดเหตุ
$inspectorNames = [];
if (is_array($inspectorIds)) {
    foreach ($inspectorIds as $id) {
        if (isset($userMap[$id])) {
            $inspectorNames[] = $userMap[$id];
        }
    }
}

// สร้าง HTML แถวผู้ตรวจแบบ dynamic
$inspector_rows_html = '';
foreach ($inspectorNames as $idx => $name) {
    $no = $idx + 1;
    $inspector_rows_html .= '<div class="si"><span class="si-no">5.' . $no . '.</span><span class="si-dots">' . htmlspecialchars($name) . '</span></div>' . "\n";
}

// ลักษณะสถานที่เกิดเหตุ
$preservation = $scene['preservation'] ?? '';
$scene_preserved_no_text = $scene['preservation_detail'] ?? '';

$chk_scene_preserved_yes = renderCheckbox($preservation == 'yes' || $preservation == 'มี');
// ถ้ามีข้อความในช่อง "ไม่มี" = ติ๊กถูกอัตโนมัติ
$chk_scene_preserved_no = renderCheckbox($preservation == 'no' || $preservation == 'ไม่มี' || !empty(trim($scene_preserved_no_text)));

// แสงสว่าง
$lighting = $scene['lighting'] ?? [];
$lighting_other_text = $scene['lighting_other'] ?? '';

$chk_lighting_bright = renderCheckbox(in_array('bright', $lighting) || in_array('สว่าง', $lighting));
$chk_lighting_dark = renderCheckbox(in_array('dark', $lighting) || in_array('มืด', $lighting));
$chk_lighting_dim = renderCheckbox(in_array('dim', $lighting) || in_array('สลัว', $lighting));
// ถ้ามีข้อความในช่อง "อื่นๆ" = ติ๊กถูกอัตโนมัติ
$chk_lighting_other = renderCheckbox(in_array('other', $lighting) || in_array('อื่นๆ', $lighting) || !empty(trim($lighting_other_text)));

// อุณหภูมิ
$temperature = $scene['temperature'] ?? [];
$temperature_other_text = $scene['temperature_other'] ?? '';

$chk_temperature_hot = renderCheckbox(in_array('hot', $temperature) || in_array('ร้อน', $temperature));
$chk_temperature_cold = renderCheckbox(in_array('cold', $temperature) || in_array('เย็น', $temperature));
$chk_temperature_ac = renderCheckbox(in_array('ac', $temperature) || in_array('เครื่องปรับอากาศ', $temperature));
// ถ้ามีข้อความในช่อง "อื่นๆ" = ติ๊กถูกอัตโนมัติ
$chk_temperature_other = renderCheckbox(in_array('other', $temperature) || in_array('อื่นๆ', $temperature) || !empty(trim($temperature_other_text)));

// กลิ่น
$smell = $scene['smell'] ?? '';
$chk_smell_yes = renderCheckbox($smell == 'yes' || $smell == 'มี');
$chk_smell_no = renderCheckbox($smell == 'no' || $smell == 'ไม่มี');

// กรณีเกิดเหตุภายนอกอาคาร
$has_outdoor = $scene['has_outdoor'] ?? false;
$outdoor = $scene['outdoor_type'] ?? [];
$outdoor_type_other_text = $scene['outdoor_type_other'] ?? '';
// auto-tick: ติ๊กถูกถ้ามี has_outdoor flag หรือมีข้อมูลนอกอาคารใดๆ ถูกเลือก
$chk_outdoor = renderCheckbox($has_outdoor || !empty($outdoor) || !empty(trim($outdoor_type_other_text)));

$chk_outdoor_road = renderCheckbox(in_array('road', $outdoor) || in_array('ถนน', $outdoor));
$chk_outdoor_lawn = renderCheckbox(in_array('lawn', $outdoor) || in_array('สนามหญ้า', $outdoor));
$chk_outdoor_garden = renderCheckbox(in_array('garden', $outdoor) || in_array('ในสวน', $outdoor));
$chk_outdoor_empty = renderCheckbox(in_array('empty', $outdoor) || in_array('ที่ว่าง', $outdoor));
// ถ้ามีข้อความในช่อง "อื่นๆ" = ติ๊กถูกอัตโนมัติ
$chk_outdoor_other = renderCheckbox(in_array('other', $outdoor) || in_array('อื่นๆ', $outdoor) || !empty(trim($outdoor_type_other_text)));

// สภาพบริเวณโดยรอบ (ภายนอก)
$outdoor_entrance_condition = $scene['outdoor_entrance_condition'] ?? '';
$outdoor_front_adjacent = $scene['outdoor_front_adjacent'] ?? '';
$outdoor_right_adjacent = $scene['outdoor_right_adjacent'] ?? '';
$outdoor_left_adjacent = $scene['outdoor_left_adjacent'] ?? '';
$outdoor_back_adjacent = $scene['outdoor_back_adjacent'] ?? '';
$outdoor_incident_area_detail = $scene['outdoor_incident_area_detail'] ?? '';

// สภาพบริเวณโดยรอบ (ภายใน)
$entrance_condition = $scene['entrance_condition'] ?? '';
$front_adjacent = $scene['front_adjacent'] ?? '';
$right_adjacent = $scene['right_adjacent'] ?? '';
$left_adjacent = $scene['left_adjacent'] ?? '';
$back_adjacent = $scene['back_adjacent'] ?? '';

// บริเวณที่เกิดเหตุ
$incident_area_detail = $scene['incident_area_detail'] ?? '';

// กรณีเกิดเหตุภายในอาคาร
$has_indoor = $scene['has_indoor'] ?? false;
$building = $scene['building_type'] ?? [];
$building_type_other_text = $scene['building_type_other'] ?? '';
// auto-tick: ติ๊กถูกถ้ามี has_indoor flag หรือมีข้อมูลภายในอาคารใดๆ ถูกเลือก
$chk_indoor = renderCheckbox($has_indoor || !empty($building) || !empty(trim($building_type_other_text)));

$chk_building_commercial = renderCheckbox(in_array('commercial', $building) || in_array('อาคารพาณิชย์', $building));
$chk_building_house = renderCheckbox(in_array('house', $building) || in_array('บ้านเดี่ยว', $building));
$chk_building_townhouse = renderCheckbox(in_array('townhouse', $building) || in_array('ทาวน์เฮาส์', $building));
// ถ้ามีข้อความในช่อง "อื่นๆ" = ติ๊กถูกอัตโนมัติ
$chk_building_other = renderCheckbox(in_array('other', $building) || in_array('อื่นๆ', $building) || !empty(trim($building_type_other_text)));

// สภาพบริเวณโดยรอบ (ภายใน)
$fence = $scene['fence'] ?? '';
$chk_indoor_fence_yes = renderCheckbox($fence == 'yes' || $fence == 'มีรั้ว');
$chk_indoor_fence_no = renderCheckbox($fence == 'no' || $fence == 'ไม่มีรั้ว');

// ลักษณะภายใน
$indoor_interior_detail = $scene['interior_detail'] ?? '';

// โครงสร้างบริเวณที่เกิดเหตุ
$structure = $scene['structure'] ?? [];
$structure_size = $structure['size'] ?? '';
$structure_type = $structure['type'] ?? '';
$structure_wall = $structure['wall'] ?? '';
$structure_front = $structure['front'] ?? '';
$structure_left = $structure['left'] ?? '';
$structure_right = $structure['right'] ?? '';
$structure_back = $structure['back'] ?? '';
$structure_floor = $structure['floor'] ?? '';
$structure_roof = $structure['roof'] ?? '';
$structure_arrangement = $structure['arrangement'] ?? '';

// auto-tick: ติ๊กถูกอัตโนมัติเมื่อมีข้อมูล text
$chk_structure_size = renderCheckbox(!empty(trim($structure_size)));
$chk_structure_type = renderCheckbox(!empty(trim($structure_type)));
$chk_structure_wall = renderCheckbox(!empty(trim($structure_wall)));
$chk_structure_floor = renderCheckbox(!empty(trim($structure_floor)));
$chk_structure_roof = renderCheckbox(!empty(trim($structure_roof)));
$chk_structure_arrangement = renderCheckbox(!empty(trim($structure_arrangement)));

// ผลการตรวจสถานที่เกิดเหตุ
$case_behavior = $inspection['case_behavior'] ?? '';
$entrance_exit = $inspection['entrance_exit'] ?? '';
$chk_entrance_exit = renderCheckbox(!empty(trim($entrance_exit)));

// ร่องรอย
$fight_trace = $inspection['fight_trace'] ?? '';
$chk_fight_trace_yes = renderCheckbox($fight_trace == 'yes' || $fight_trace == 'มี');
$chk_fight_trace_no = renderCheckbox($fight_trace == 'no' || $fight_trace == 'ไม่มี');

$search_trace = $inspection['search_trace'] ?? '';
$chk_search_trace_yes = renderCheckbox($search_trace == 'yes' || $search_trace == 'มี');
$chk_search_trace_no = renderCheckbox($search_trace == 'no' || $search_trace == 'ไม่มี');

// ศพ
$body_found = $inspection['body_found'] ?? '';
$chk_body_found = renderCheckbox($body_found == 'yes' || $body_found == 'พบศพ');
$chk_body_not_found = renderCheckbox($body_found == 'no' || $body_found == 'ไม่พบศพ');
$body_location = $inspection['body_location'] ?? '';
$chk_body_location = renderCheckbox(!empty(trim($body_location)));
$body_condition = $inspection['body_condition'] ?? '';

// การแต่งกายและทรัพย์สิน
$clothing = $inspection['clothing'] ?? [];
$clothing_shirt = $clothing['shirt'] ?? '';
$clothing_pants = $clothing['pants'] ?? '';
$clothing_shoes = $clothing['shoes'] ?? '';
$clothing_accessories = $clothing['accessories'] ?? '';
$clothing_tattoo = $clothing['tattoo'] ?? '';
$clothing_other = $clothing['other'] ?? '';

// ถ้ามีข้อความกรอก = ติ๊กถูกอัตโนมัติ
$chk_clothing_shirt = renderCheckbox(!empty(trim($clothing_shirt)));
$chk_clothing_pants = renderCheckbox(!empty(trim($clothing_pants)));
$chk_clothing_shoes = renderCheckbox(!empty(trim($clothing_shoes)));
$chk_clothing_accessories = renderCheckbox(!empty(trim($clothing_accessories)));
$chk_clothing_tattoo = renderCheckbox(!empty(trim($clothing_tattoo)));
$chk_clothing_other = renderCheckbox(!empty(trim($clothing_other)));

// สภาพรอยบาดแผล
$wound = $inspection['wound'] ?? [];
$woundStatus = $wound['status'] ?? '';
$chk_wound_none = renderCheckbox($woundStatus == 'none' || $woundStatus == 'ไม่พบรอยบาดแผล');
$chk_wound_found = renderCheckbox($woundStatus == 'found' || $woundStatus == 'พบรอยบาดแผล');
$wound_count = $wound['count'] ?? '';
$wound_description = $wound['description'] ?? '';
$wound_detail = $wound['detail'] ?? '';

// วัตถุพยานที่ตรวจพบ
$evidence = $inspection['evidence'] ?? [];
$blood_stain_detail = $evidence['blood_stain'] ?? '';
// ถ้ามีข้อความกรอก = ติ๊กถูกอัตโนมัติ
$chk_evidence_blood_stain = renderCheckbox(!empty(trim($blood_stain_detail)));

// ทดสอบคราบโลหิต
$bloodTest = $evidence['blood_test'] ?? [];
$hemastix_result = $bloodTest['hemastix_result'] ?? '';
$phenol_result = $bloodTest['phenol_result'] ?? '';

// ถ้ามีผลการทดสอบ = ติ๊กถูกอัตโนมัติ
$chk_test_hemastix = renderCheckbox(!empty($hemastix_result));
$chk_hemastix_positive = renderCheckbox($hemastix_result == 'positive' || stripos($hemastix_result, 'เขียว') !== false || stripos($hemastix_result, 'น้ำเงิน') !== false || stripos($hemastix_result, 'มีการเปลี่ยนแปลงเป็นสีเขียว') !== false);
$chk_hemastix_negative = renderCheckbox($hemastix_result == 'negative' || $hemastix_result == 'ไม่มีการเปลี่ยนแปลง');

$chk_test_phenolphthalein = renderCheckbox(!empty($phenol_result));
$chk_phenol_positive = renderCheckbox($phenol_result == 'positive' || stripos($phenol_result, 'ชมพู') !== false || stripos($phenol_result, 'มีการเปลี่ยนแปลงเป็นสีชมพู') !== false);
$chk_phenol_negative = renderCheckbox($phenol_result == 'negative' || $phenol_result == 'ไม่มีการเปลี่ยนแปลง');

// วัตถุพยานอื่นๆ
$other_evidence = $evidence['other'] ?? '';
$chk_other_evidence = renderCheckbox(!empty(trim($other_evidence)));

// วัตถุพยานที่ตรวจเก็บ
$collected = $inspection['collected_evidence'] ?? [];
$collected_gun_detail = $collected['gun'] ?? '';
$collected_dna_detail = $collected['dna'] ?? '';
$fingerprint_detail = $collected['fingerprint'] ?? '';

// ถ้ามีข้อความกรอก = ติ๊กถูกอัตโนมัติ
$chk_collected_gun = renderCheckbox(!empty(trim($collected_gun_detail)));
$chk_collected_dna = renderCheckbox(!empty(trim($collected_dna_detail)));
$chk_evidence_fingerprint = renderCheckbox(!empty(trim($fingerprint_detail)));

// วัตถุพยานประเภทอื่นๆ (ตรวจเก็บ)
$other_evidence_type = $collected['other_type'] ?? '';
$chk_evidence_other_type = renderCheckbox(!empty(trim($other_evidence_type)));

// การตรวจสอบครั้งสุดท้าย
$finalCheck = $inspection['final_check'] ?? [];
if (is_string($finalCheck)) $finalCheck = [$finalCheck];
$chk_final_check_1 = renderCheckbox(in_array('การตรวจสอบครั้งสุดท้าย', $finalCheck));
$chk_final_check_2 = renderCheckbox(in_array('ตรวจเก็บวัตถุพยานครบถ้วน', $finalCheck));
$chk_final_check_3 = renderCheckbox(in_array('ถ่ายภาพสถานที่เกิดเหตุและดำเนินการส่งมอบสถานที่เกิดเหตุให้แก่พนักงานสอบสวน', $finalCheck));

// การส่งมอบสถานที่เกิดเหตุ
$raw_end_dt = $gen['inspection_end_datetime'] ?? '';
$inspection_end_date = (!empty($raw_end_dt) && $raw_end_dt !== 'T') ? thaiDate($raw_end_dt) : '';
$inspection_end_time = (!empty($raw_end_dt) && $raw_end_dt !== 'T') ? thaiTime($raw_end_dt) : '';

// ผู้รับมอบสถานที่
$recId = $handover['receiver_id'] ?? '';
$receiver_name = isset($userMap[$recId]) ? $userMap[$recId] : '';
$receiver_position = $handover['receiver_pos'] ?? '';

// ผู้ส่งมอบสถานที่
$delId = $handover['deliverer_id'] ?? '';
$sender_name = isset($userMap[$delId]) ? $userMap[$delId] : '';
$sender_position = $handover['deliverer_pos'] ?? '';

// ==========================================
// SIGNATURES (base64 → <img> tags)
// ==========================================
$signatures = $data['signatures'] ?? [];

// Helper: อ่านไฟล์ลายเซ็นจากดิสก์แปลงเป็น base64 data URI
function sigFileToBase64($filePath) {
    if (!file_exists($filePath)) return '';
    $content = file_get_contents($filePath);
    if ($content === false) return '';
    $mime = mime_content_type($filePath);
    return 'data:' . $mime . ';base64,' . base64_encode($content);
}

// ถ้าไม่มี base64 แต่มี filename → อ่านจากไฟล์บนดิสก์
$sigDir = __DIR__ . '/../../uploads/checklist_life_signatures/';
$sigKeys = ['receiver_sig', 'sender_sig', 'scene_sketch', 'body_diagram'];
foreach ($sigKeys as $sKey) {
    if (isset($signatures[$sKey]) && is_array($signatures[$sKey])) {
        if (empty($signatures[$sKey]['base64']) && !empty($signatures[$sKey]['filename'])) {
            $signatures[$sKey]['base64'] = sigFileToBase64($sigDir . $signatures[$sKey]['filename']);
        }
        // ถ้ายังไม่มี base64 แต่มี file_id → ดึง BLOB จาก DB
        if (empty($signatures[$sKey]['base64']) && !empty($signatures[$sKey]['file_id'])) {
            $signatures[$sKey]['base64'] = loadBlobAsBase64($pdo, $signatures[$sKey]['file_id']);
        }
    }
}

// Helper: สร้าง <img> tag จาก base64
function renderSignatureImg($sigData) {
    if (empty($sigData)) return '';
    $base64 = '';
    if (is_array($sigData) && !empty($sigData['base64'])) {
        $base64 = $sigData['base64'];
    } elseif (is_string($sigData)) {
        $base64 = $sigData;
    }
    if (empty($base64) || strpos($base64, 'data:image') === false) return '';
    return '<img src="' . $base64 . '" style="display:block; max-width:120px; max-height:38px; object-fit:contain; margin:0 auto;" alt="ลายเซ็น">';
}

$receiver_signature_img = renderSignatureImg($signatures['receiver_sig'] ?? '');
$sender_signature_img = renderSignatureImg($signatures['sender_sig'] ?? '');
$scene_sketch_img_data = $signatures['scene_sketch'] ?? '';
$body_diagram_img_data = $signatures['body_diagram'] ?? '';

// สร้าง Scene Sketch <img>
$scene_sketch_img = '';
if (!empty($scene_sketch_img_data)) {
    $sketchBase64 = '';
    if (is_array($scene_sketch_img_data) && !empty($scene_sketch_img_data['base64'])) {
        $sketchBase64 = $scene_sketch_img_data['base64'];
    } elseif (is_string($scene_sketch_img_data)) {
        $sketchBase64 = $scene_sketch_img_data;
    }
    if (!empty($sketchBase64) && strpos($sketchBase64, 'data:image') !== false) {
        $scene_sketch_img = '<img src="' . $sketchBase64 . '" style="max-width:95%; max-height:95%; object-fit:contain;" alt="แผนผังสังเขป">';
    }
}

// สร้าง Body Diagram <img> — รูปรวม (พื้นหลัง + เส้นที่วาด) จาก JS
$body_diagram_img = '';
if (!empty($body_diagram_img_data)) {
    $bodyBase64 = '';
    if (is_array($body_diagram_img_data) && !empty($body_diagram_img_data['base64'])) {
        $bodyBase64 = $body_diagram_img_data['base64'];
    } elseif (is_string($body_diagram_img_data)) {
        $bodyBase64 = $body_diagram_img_data;
    }
    if (!empty($bodyBase64) && strpos($bodyBase64, 'data:image') !== false) {
        $body_diagram_img = '<img src="' . $bodyBase64 . '" style="height:100%; width:auto; max-width:100%; object-fit:contain;" alt="แผนผังร่างกาย">';
    }
}
// Fallback: ถ้าไม่มีรูปจาก canvas ให้ใช้รูป static
if (empty($body_diagram_img)) {
    $body_diagram_img = '<img src="../../assets/images/body_diagram.png" style="height:100%; width:auto; max-width:100%; object-fit:contain;" alt="แผนผังร่างกาย">';
}

// ==========================================
// SKETCH INFO (Page 4)
// ==========================================
$sketchInfo = $data['sketch_info'] ?? [];
$sketch_remark = $sketchInfo['remark'] ?? '';
$sketch_recorder = $sketchInfo['recorder'] ?? '';
$rawSketchDatetime = $sketchInfo['datetime'] ?? '';
if (!empty($rawSketchDatetime) && strtotime($rawSketchDatetime)) {
    $sketch_datetime = thaiDate($rawSketchDatetime) . ' ' . thaiTime($rawSketchDatetime);
} else {
    $sketch_datetime = $rawSketchDatetime;
}

// ==========================================
// EVIDENCE META (Page 5 - reference points, recorder)
// ==========================================
$evidenceMeta = $data['evidence_meta'] ?? [];
$reference_point_1 = $evidenceMeta['reference_point_1'] ?? '';
$reference_point_2 = $evidenceMeta['reference_point_2'] ?? '';
$reference_point_3 = $evidenceMeta['reference_point_3'] ?? '';
$reference_point_4 = $evidenceMeta['reference_point_4'] ?? '';
$ev_recorder = !empty($evidenceMeta['collector_name']) ? $evidenceMeta['collector_name'] : $sketch_recorder;
$rawEvDatetime = !empty($evidenceMeta['collection_datetime']) ? $evidenceMeta['collection_datetime'] : $sketch_datetime;
if (!empty($rawEvDatetime) && strtotime($rawEvDatetime)) {
    $ev_datetime = thaiDate($rawEvDatetime) . ' ' . thaiTime($rawEvDatetime);
} else {
    $ev_datetime = $rawEvDatetime;
}

// ==========================================
// BODY DIAGRAM INFO (Page 6)
// ==========================================
$bodyDiagramInfo = $data['body_diagram_info'] ?? [];
$body_diagram_victim_name = $bodyDiagramInfo['victim_name'] ?? '';
if (is_array($body_diagram_victim_name)) {
    $body_diagram_victim_name = implode(', ', $body_diagram_victim_name);
}
$body_diagram_victim_age = $bodyDiagramInfo['victim_age'] ?? '';
if (is_array($body_diagram_victim_age)) {
    $body_diagram_victim_age = implode(', ', $body_diagram_victim_age);
}
$body_diagram_doctor = $bodyDiagramInfo['autopsy_doctor'] ?? '';

// ==========================================
// EVIDENCE TABLE (Page 5) - สร้าง HTML rows
// ==========================================
$evidences = $data['evidences'] ?? [];
$totalEvidenceRows = 20; // จำนวนแถวสูงสุดในตาราง
$evidence_table_rows = '';

foreach ($evidences as $i => $ev) {
    if ($i >= $totalEvidenceRows) break;
    $num = $i + 1;
    $evDetail = htmlspecialchars($ev['detail'] ?? $ev['item'] ?? '');
    $evLabelNo = htmlspecialchars($ev['label_no'] ?? '');
    $evAzimuth = htmlspecialchars($ev['azimuth'] ?? '');
    $evRemark = htmlspecialchars($ev['remark'] ?? '');

    // ระยะห่างจากจุดอ้างอิง 1-4: แสดงค่าจริง (เมตร) ถ้ามี, ถ้าไม่มีแสดง ✓ จาก level (backward compatible)
    $evLv1 = !empty($ev['ref1_dist']) ? htmlspecialchars($ev['ref1_dist']) : (!empty($ev['level_1']) ? '✓' : '');
    $evLv2 = !empty($ev['ref2_dist']) ? htmlspecialchars($ev['ref2_dist']) : (!empty($ev['level_2']) ? '✓' : '');
    $evLv3 = !empty($ev['ref3_dist']) ? htmlspecialchars($ev['ref3_dist']) : (!empty($ev['level_3']) ? '✓' : '');
    $evLv4 = !empty($ev['ref4_dist']) ? htmlspecialchars($ev['ref4_dist']) : (!empty($ev['level_4']) ? '✓' : '');

    // ใช้ label_no สำหรับป้ายหมายเลข ถ้ามี, ถ้าไม่มีใช้ลำดับที่
    $labelDisplay = !empty($evLabelNo) ? $evLabelNo : $num;

    $evidence_table_rows .= '<tr>';
    $evidence_table_rows .= '<td style="border:1px solid #000; height:22px; text-align:center;">' . $labelDisplay . '</td>';
    $evidence_table_rows .= '<td style="border:1px solid #000;">' . $evDetail . '</td>';
    $evidence_table_rows .= '<td style="border:1px solid #000; text-align:center;">' . $evLv1 . '</td>';
    $evidence_table_rows .= '<td style="border:1px solid #000; text-align:center;">' . $evLv2 . '</td>';
    $evidence_table_rows .= '<td style="border:1px solid #000; text-align:center;">' . $evLv3 . '</td>';
    $evidence_table_rows .= '<td style="border:1px solid #000; text-align:center;">' . $evLv4 . '</td>';
    $evidence_table_rows .= '<td style="border:1px solid #000;">' . $evAzimuth . '</td>';
    $evidence_table_rows .= '<td style="border:1px solid #000;">' . $evRemark . '</td>';
    $evidence_table_rows .= '</tr>';
}

// เติมแถวว่างที่เหลือ
$filledRows = min(count($evidences), $totalEvidenceRows);
for ($i = $filledRows; $i < $totalEvidenceRows; $i++) {
    $evidence_table_rows .= '<tr><td style="border:1px solid #000; height:22px;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td></tr>';
}

// ==========================================
// EVIDENCE COLLECTION TABLE (Page 7)
// ==========================================
$measurementsData = $data['measurements'] ?? [];
$evidence_collection_rows = '';
$totalEcRows = 15;
$ecFilled = 0;
if (!empty($measurementsData)) {
    foreach ($measurementsData as $idx => $m) {
        if ($idx >= $totalEcRows) break;
        $ecNo = $idx + 1;
        $ecItem = htmlspecialchars($m['item'] ?? '');
        $ecQty = htmlspecialchars($m['quantity'] ?? '');
        $ecArea = htmlspecialchars($m['area'] ?? '');
        $ecLabel = htmlspecialchars($m['label_number'] ?? '');
        $ecRemark = htmlspecialchars($m['remark'] ?? '');

        // Package columns (3 sub-columns) - แสดงเฉพาะข้อความ ไม่ต้องมีเครื่องหมายถูก
        $pkgPlastic = '';
        if (!empty($m['package_plastic'])) {
            $pt = $m['package_plastic_text'] ?? '';
            $pkgPlastic = !empty($pt) ? htmlspecialchars($pt) : '✓';
        }
        $pkgPaper = '';
        if (!empty($m['package_paper'])) {
            $pt = $m['package_paper_text'] ?? '';
            $pkgPaper = !empty($pt) ? htmlspecialchars($pt) : '✓';
        }
        $pkgOther = '';
        if (!empty($m['package_other'])) {
            $pt = $m['package_other_text'] ?? '';
            $pkgOther = !empty($pt) ? htmlspecialchars($pt) : '✓';
        }

        // Action columns (2 sub-columns) - แสดงเฉพาะข้อความ ไม่ต้องมีเครื่องหมายถูก
        $actReturn = '';
        if (!empty($m['action_return'])) {
            $at = $m['action_return_text'] ?? '';
            $actReturn = !empty($at) ? htmlspecialchars($at) : '✓';
        }
        $actOther = '';
        if (!empty($m['action_other'])) {
            $at = $m['action_other_text'] ?? '';
            $actOther = !empty($at) ? htmlspecialchars($at) : '✓';
        }

        $evidence_collection_rows .= '<tr>'
            . '<td style="border:1px solid #000; height:24px; text-align:center;">' . $ecNo . '</td>'
            . '<td style="border:1px solid #000; padding:0 2px;">' . $ecItem . '</td>'
            . '<td style="border:1px solid #000; text-align:center;">' . $ecQty . '</td>'
            . '<td style="border:1px solid #000; padding:0 2px;">' . $ecArea . '</td>'
            . '<td style="border:1px solid #000; text-align:center;">' . $ecLabel . '</td>'
            . '<td style="border:1px solid #000; text-align:center; padding:0 2px; font-size:9px;">' . $pkgPlastic . '</td>'
            . '<td style="border:1px solid #000; text-align:center; padding:0 2px; font-size:9px;">' . $pkgPaper . '</td>'
            . '<td style="border:1px solid #000; text-align:center; padding:0 2px; font-size:9px;">' . $pkgOther . '</td>'
            . '<td style="border:1px solid #000; text-align:center; padding:0 2px; font-size:9px;">' . $actReturn . '</td>'
            . '<td style="border:1px solid #000; text-align:center; padding:0 2px; font-size:9px;">' . $actOther . '</td>'
            . '<td style="border:1px solid #000; padding:0 2px;">' . $ecRemark . '</td>'
            . '</tr>';
        $ecFilled++;
    }
}
for ($i = $ecFilled; $i < $totalEcRows; $i++) {
    $evidence_collection_rows .= '<tr><td style="border:1px solid #000; height:24px;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td></tr>';
}
// Page 7 date/time/recorder (ใช้ค่าจาก measurement_meta ถ้ามี, fallback เป็น inspection datetime)
$measurementMeta = $data['measurement_meta'] ?? [];
$mmInspDate = $measurementMeta['inspection_date'] ?? '';
$ec_inspect_date = !empty($mmInspDate) ? thaiDate($mmInspDate) : $inspect_date;
$ec_inspect_time = !empty($mmInspDate) ? thaiTime($mmInspDate) : $inspect_time;
$ec_recorder = !empty($measurementMeta['recorder']) ? $measurementMeta['recorder'] : $sketch_recorder;
$rawEcDatetime = !empty($measurementMeta['datetime']) ? $measurementMeta['datetime'] : $sketch_datetime;
// แปลง datetime เป็นรูปแบบวันเดือนปี พ.ศ.
if (!empty($rawEcDatetime) && strtotime($rawEcDatetime)) {
    $ec_datetime = thaiDate($rawEcDatetime) . ' ' . thaiTime($rawEcDatetime);
} else {
    $ec_datetime = $rawEcDatetime;
}

// ==========================================
// PHOTO RECORDS (Page 8)
// ==========================================
$photoRecords = $data['photo_records'] ?? [];
$photo_id_start = $photoRecords['photo_id_start'] ?? '';
$photo_id_end = $photoRecords['photo_id_end'] ?? '';
$photo_amount = $photoRecords['photo_amount'] ?? '';

// วันเวลาตรวจ (ใช้ค่าจาก inspection_datetime)
$photo_inspect_date = $inspect_date;
$photo_inspect_time = $inspect_time;

// สร้าง Photo images HTML (grid 5 คอลัมน์ × 7 แถว = 35 รูป/หน้า)
$photos = $data['photos'] ?? [];
$photo_images = '';
$photo_extra_pages = '';
$PHOTOS_PER_PAGE = 35;

// Helper: อ่านไฟล์จากดิสก์แปลงเป็น base64 data URI
function fileToBase64ForPdf($filePath) {
    if (!file_exists($filePath)) return '';
    $type = mime_content_type($filePath);
    if (strpos($type, 'image/') !== 0) return '';
    $data = file_get_contents($filePath);
    if ($data === false) return '';
    return 'data:' . $type . ';base64,' . base64_encode($data);
}

// Helper: โหลด BLOB จาก DB แปลงเป็น base64 data URI
function loadBlobAsBase64($pdo, $fileId) {
    try {
        $stmt = $pdo->prepare("SELECT file_name FROM incident_checklist_transaction_file WHERE id = ? LIMIT 1");
        $stmt->execute([$fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['file_name'])) {
            $blobData = $row['file_name'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($blobData);
            if (!$mime || $mime === 'application/octet-stream') {
                $mime = 'image/jpeg';
            }
            return 'data:' . $mime . ';base64,' . base64_encode($blobData);
        }
    } catch (Exception $e) {
        // silent fail
    }
    return '';
}

$validPhotos = [];
$photosDir = __DIR__ . '/../../uploads/checklist_life_photos/';

// DEBUG: Log photo data
error_log('[gen_pdf_life] Total photos in data: ' . count($photos ?? []));

if (!empty($photos)) {
    foreach ($photos as $idx => $photo) {
        $pBase64 = '';
        $pName = '';
        if (is_array($photo)) {
            error_log('[gen_pdf_life] Photo ' . $idx . ': file_id=' . ($photo['file_id'] ?? 'none') . ', filename=' . ($photo['filename'] ?? 'none'));
            
            if (!empty($photo['base64'])) {
                $pBase64 = $photo['base64'];
            }
            // ถ้าไม่มี base64 แต่มี filename → อ่านจากไฟล์บนดิสก์
            if (empty($pBase64) && !empty($photo['filename'])) {
                $pBase64 = fileToBase64ForPdf($photosDir . $photo['filename']);
            }
            // ถ้าไม่มี base64 และ filename แต่มี file_id → โหลด BLOB จาก DB
            if (empty($pBase64) && !empty($photo['file_id'])) {
                $pBase64 = loadBlobAsBase64($pdo, $photo['file_id']);
                error_log('[gen_pdf_life] Loaded BLOB for file_id=' . $photo['file_id'] . ', length=' . strlen($pBase64));
            }
            $pName = $photo['filename'] ?? $photo['name'] ?? $photo['file_name'] ?? '';
        }
        if (!empty($pBase64) && strpos($pBase64, 'data:image') !== false) {
            if (empty($pName)) $pName = 'photo_' . (count($validPhotos) + 1) . '.jpg';
            $validPhotos[] = ['base64' => $pBase64, 'name' => $pName];
        } else {
            error_log('[gen_pdf_life] Photo ' . $idx . ' SKIPPED - no valid base64');
        }
    }
}

error_log('[gen_pdf_life] Valid photos after processing: ' . count($validPhotos));

// Override photo_id_start / photo_id_end / photo_amount with actual filenames for PDF
if (!empty($validPhotos)) {
    $totalPhotos = count($validPhotos);
    $firstPageEnd = min($PHOTOS_PER_PAGE, $totalPhotos) - 1;
    
    // Always use actual filenames in PDF (override user-entered values)
    $photo_id_start = $validPhotos[0]['name'];
    $photo_id_end = $validPhotos[$firstPageEnd]['name'];
    $photo_amount = (string)$totalPhotos;

    $pagesNeeded = (int)ceil($totalPhotos / $PHOTOS_PER_PAGE);

    // === หน้าแรก (Page 8): grid ===
    $photo_images = '<div class="photo-grid">';
    for ($i = 0; $i <= $firstPageEnd; $i++) {
        $safeName = htmlspecialchars($validPhotos[$i]['name']);
        $photo_images .= '<div class="photo-cell-wrapper">';
        $photo_images .= '<div class="photo-cell"><img src="' . $validPhotos[$i]['base64'] . '" alt="' . $safeName . '"></div>';
        $photo_images .= '<div class="photo-fname" title="' . $safeName . '">' . $safeName . '</div>';
        $photo_images .= '</div>';
    }
    $photo_images .= '</div>';

    // === หน้าถัดไป (Page 9, 10, ...) ===
    for ($p = 2; $p <= $pagesNeeded; $p++) {
        $startI = ($p - 1) * $PHOTOS_PER_PAGE;
        $endI = min($p * $PHOTOS_PER_PAGE, $totalPhotos) - 1;
        $pageStartName = htmlspecialchars($validPhotos[$startI]['name']);
        $pageEndName = htmlspecialchars($validPhotos[$endI]['name']);
        $pageNum = 8 + ($p - 1);

        $gridHtml = '<div class="photo-grid">';
        for ($i = $startI; $i <= $endI; $i++) {
            $safeName = htmlspecialchars($validPhotos[$i]['name']);
            $gridHtml .= '<div class="photo-cell-wrapper">';
            $gridHtml .= '<div class="photo-cell"><img src="' . $validPhotos[$i]['base64'] . '" alt="' . $safeName . '"></div>';
            $gridHtml .= '<div class="photo-fname" title="' . $safeName . '">' . $safeName . '</div>';
            $gridHtml .= '</div>';
        }
        $gridHtml .= '</div>';

        $photo_extra_pages .= '
<div class="page" style="display:flex; flex-direction:column;">
    <div class="form-header">
        <div class="header-logo">
            <img src="../../images/office-of-police-forensic-icon.jpg" alt="ตราสำนักงานตำรวจแห่งชาติ">
        </div>
        <div class="header-center">
            <div class="title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต</div>
            <div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ (ต่อ)</div>
        </div>
        <div class="header-right">
            <div class="doc-box">
                <div class="doc-line">รายงานที่ <span style="display:inline-block;min-width:60px;border-bottom:1px dotted #888;text-align:center;">' . htmlspecialchars($report_no) . '</span> / 25<span style="display:inline-block;min-width:40px;border-bottom:1px dotted #888;text-align:center;">' . htmlspecialchars($report_year) . '</span></div>
                <div class="doc-line">หน้าที่ ' . $pageNum . ' / {{total_pages}}</div>
            </div>
        </div>
    </div>
    <div style="margin-bottom:4px;">
        <div class="fr" style="flex-wrap:nowrap;">
            <span class="fl">รหัสภาพถ่ายที่</span>
            <span class="fd" style="flex:1; min-width:40px;">' . $pageStartName . '</span>
            <span class="fl">ถึง</span>
            <span class="fd" style="flex:1; min-width:40px;">' . $pageEndName . '</span>
        </div>
        <div style="font-size:10.5px; font-style:italic; margin-top:1px;">(ตามภาพถ่ายรวมที่แนบ)</div>
    </div>
    ' . $gridHtml . '
    <div style="margin-top:auto;">
        <div class="form-footer">
            <div class="form-footer-left">
                <strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ
            </div>
            <div class="form-footer-right">
                F-CS-09 แก้ไขครั้งที่ 2<br>
                แก้ไขวันที่ 2 ก.ย. 63<br>
                เริ่มใช้ 1 ต.ค. 63
            </div>
        </div>
    </div>
</div>';
    }
}

if (empty($photo_images)) {
    $photo_images = '<div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center;"><div style="color:#999; font-style:italic;">ไม่มีภาพถ่าย</div></div>';
}

// ==========================================
// 4. READ HTML TEMPLATE & REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_life_preview.html');

// สร้าง array ของ placeholders และค่าที่จะแทนที่
$replacements = [
    '{{report_no}}' => htmlspecialchars($report_no),
    '{{report_year}}' => htmlspecialchars($report_year),
    '{{qr_data}}' => htmlspecialchars($qrData),
    '{{case_doc_no}}' => htmlspecialchars($case_doc_no),
    '{{case_date}}' => htmlspecialchars($case_date),
    '{{case_time}}' => htmlspecialchars($case_time),
    '{{chk_notify_phone}}' => $chk_notify_phone,
    '{{chk_notify_radio}}' => $chk_notify_radio,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_other}}' => $chk_notify_other,
    '{{police_station}}' => htmlspecialchars($police_station),
    '{{location_at}}' => htmlspecialchars($location_at),
    '{{record_date}}' => htmlspecialchars($record_date),
    '{{investigator_name}}' => htmlspecialchars($investigator_name),
    '{{investigator_phone}}' => htmlspecialchars($investigator_phone),
    '{{crime_location}}' => htmlspecialchars($crime_location),
    '{{victim_rows}}' => $victimRowsHtml,
    '{{victim_know_date}}' => htmlspecialchars($victim_know_date),
    '{{victim_know_time}}' => htmlspecialchars($victim_know_time),
    '{{officer_know_date}}' => htmlspecialchars($officer_know_date),
    '{{officer_know_time}}' => htmlspecialchars($officer_know_time),
    '{{inspect_date}}' => htmlspecialchars($inspect_date),
    '{{inspect_time}}' => htmlspecialchars($inspect_time),
    '{{inspect_additional_date}}' => htmlspecialchars($inspect_additional_date),
    '{{inspect_additional_time}}' => htmlspecialchars($inspect_additional_time),
    '{{inspector_rows}}' => $inspector_rows_html,
    '{{chk_scene_preserved_yes}}' => $chk_scene_preserved_yes,
    '{{chk_scene_preserved_no}}' => $chk_scene_preserved_no,
    '{{scene_preserved_no_text}}' => htmlspecialchars($scene_preserved_no_text),
    '{{chk_lighting_bright}}' => $chk_lighting_bright,
    '{{chk_lighting_dark}}' => $chk_lighting_dark,
    '{{chk_lighting_dim}}' => $chk_lighting_dim,
    '{{chk_lighting_other}}' => $chk_lighting_other,
    '{{lighting_other_text}}' => htmlspecialchars($lighting_other_text),
    '{{chk_temperature_hot}}' => $chk_temperature_hot,
    '{{chk_temperature_cold}}' => $chk_temperature_cold,
    '{{chk_temperature_ac}}' => $chk_temperature_ac,
    '{{chk_temperature_other}}' => $chk_temperature_other,
    '{{temperature_other_text}}' => htmlspecialchars($temperature_other_text),
    '{{chk_smell_yes}}' => $chk_smell_yes,
    '{{chk_smell_no}}' => $chk_smell_no,
    '{{chk_outdoor}}' => $chk_outdoor,
    '{{chk_outdoor_road}}' => $chk_outdoor_road,
    '{{chk_outdoor_lawn}}' => $chk_outdoor_lawn,
    '{{chk_outdoor_garden}}' => $chk_outdoor_garden,
    '{{chk_outdoor_empty}}' => $chk_outdoor_empty,
    '{{chk_outdoor_other}}' => $chk_outdoor_other,
    '{{outdoor_type_other_text}}' => htmlspecialchars($outdoor_type_other_text),
    '{{outdoor_entrance_condition}}' => htmlspecialchars($outdoor_entrance_condition),
    '{{outdoor_front_adjacent}}' => htmlspecialchars($outdoor_front_adjacent),
    '{{outdoor_right_adjacent}}' => htmlspecialchars($outdoor_right_adjacent),
    '{{outdoor_left_adjacent}}' => htmlspecialchars($outdoor_left_adjacent),
    '{{outdoor_back_adjacent}}' => htmlspecialchars($outdoor_back_adjacent),
    '{{outdoor_incident_area_detail}}' => htmlspecialchars($outdoor_incident_area_detail),
    '{{entrance_condition}}' => htmlspecialchars($entrance_condition),
    '{{front_adjacent}}' => htmlspecialchars($front_adjacent),
    '{{right_adjacent}}' => htmlspecialchars($right_adjacent),
    '{{left_adjacent}}' => htmlspecialchars($left_adjacent),
    '{{back_adjacent}}' => htmlspecialchars($back_adjacent),
    '{{incident_area_detail}}' => htmlspecialchars($incident_area_detail),
    '{{chk_indoor}}' => $chk_indoor,
    '{{chk_building_commercial}}' => $chk_building_commercial,
    '{{chk_building_house}}' => $chk_building_house,
    '{{chk_building_townhouse}}' => $chk_building_townhouse,
    '{{chk_building_other}}' => $chk_building_other,
    '{{building_type_other_text}}' => htmlspecialchars($building_type_other_text),
    '{{chk_indoor_fence_yes}}' => $chk_indoor_fence_yes,
    '{{chk_indoor_fence_no}}' => $chk_indoor_fence_no,
    '{{indoor_interior_detail}}' => htmlspecialchars($indoor_interior_detail),
    '{{chk_structure_size}}' => $chk_structure_size,
    '{{structure_size}}' => htmlspecialchars($structure_size),
    '{{chk_structure_type}}' => $chk_structure_type,
    '{{structure_type}}' => htmlspecialchars($structure_type),
    '{{chk_structure_wall}}' => $chk_structure_wall,
    '{{structure_wall}}' => htmlspecialchars($structure_wall),
    '{{structure_front}}' => htmlspecialchars($structure_front),
    '{{structure_left}}' => htmlspecialchars($structure_left),
    '{{structure_right}}' => htmlspecialchars($structure_right),
    '{{structure_back}}' => htmlspecialchars($structure_back),
    '{{chk_structure_floor}}' => $chk_structure_floor,
    '{{structure_floor}}' => htmlspecialchars($structure_floor),
    '{{chk_structure_roof}}' => $chk_structure_roof,
    '{{structure_roof}}' => htmlspecialchars($structure_roof),
    '{{chk_structure_arrangement}}' => $chk_structure_arrangement,
    '{{structure_arrangement}}' => htmlspecialchars($structure_arrangement),
    '{{case_behavior}}' => htmlspecialchars($case_behavior),
    '{{chk_entrance_exit}}' => $chk_entrance_exit,
    '{{entrance_exit}}' => htmlspecialchars($entrance_exit),
    '{{chk_fight_trace_yes}}' => $chk_fight_trace_yes,
    '{{chk_fight_trace_no}}' => $chk_fight_trace_no,
    '{{chk_search_trace_yes}}' => $chk_search_trace_yes,
    '{{chk_search_trace_no}}' => $chk_search_trace_no,
    '{{chk_body_found}}' => $chk_body_found,
    '{{chk_body_not_found}}' => $chk_body_not_found,
    '{{chk_body_location}}' => $chk_body_location,
    '{{body_location}}' => htmlspecialchars($body_location),
    '{{body_condition}}' => htmlspecialchars($body_condition),
    '{{chk_clothing_shirt}}' => $chk_clothing_shirt,
    '{{clothing_shirt}}' => htmlspecialchars($clothing_shirt),
    '{{chk_clothing_pants}}' => $chk_clothing_pants,
    '{{clothing_pants}}' => htmlspecialchars($clothing_pants),
    '{{chk_clothing_shoes}}' => $chk_clothing_shoes,
    '{{clothing_shoes}}' => htmlspecialchars($clothing_shoes),
    '{{chk_clothing_accessories}}' => $chk_clothing_accessories,
    '{{clothing_accessories}}' => htmlspecialchars($clothing_accessories),
    '{{chk_clothing_tattoo}}' => $chk_clothing_tattoo,
    '{{clothing_tattoo}}' => htmlspecialchars($clothing_tattoo),
    '{{chk_clothing_other}}' => $chk_clothing_other,
    '{{clothing_other}}' => htmlspecialchars($clothing_other),
    '{{chk_wound_none}}' => $chk_wound_none,
    '{{chk_wound_found}}' => $chk_wound_found,
    '{{wound_count}}' => htmlspecialchars($wound_count),
    '{{wound_description}}' => htmlspecialchars($wound_description),
    '{{wound_detail}}' => htmlspecialchars($wound_detail),
    '{{chk_evidence_blood_stain}}' => $chk_evidence_blood_stain,
    '{{blood_stain_detail}}' => htmlspecialchars($blood_stain_detail),
    '{{chk_test_hemastix}}' => $chk_test_hemastix,
    '{{chk_hemastix_positive}}' => $chk_hemastix_positive,
    '{{chk_hemastix_negative}}' => $chk_hemastix_negative,
    '{{chk_test_phenolphthalein}}' => $chk_test_phenolphthalein,
    '{{chk_phenol_positive}}' => $chk_phenol_positive,
    '{{chk_phenol_negative}}' => $chk_phenol_negative,
    '{{chk_other_evidence}}' => $chk_other_evidence,
    '{{other_evidence}}' => htmlspecialchars($other_evidence),
    '{{chk_collected_gun}}' => $chk_collected_gun,
    '{{collected_gun_detail}}' => htmlspecialchars($collected_gun_detail),
    '{{chk_collected_dna}}' => $chk_collected_dna,
    '{{collected_dna_detail}}' => htmlspecialchars($collected_dna_detail),
    '{{chk_evidence_fingerprint}}' => $chk_evidence_fingerprint,
    '{{fingerprint_detail}}' => htmlspecialchars($fingerprint_detail),
    '{{chk_evidence_other_type}}' => $chk_evidence_other_type,
    '{{other_evidence_type}}' => htmlspecialchars($other_evidence_type),
    '{{chk_final_check_1}}' => $chk_final_check_1,
    '{{chk_final_check_2}}' => $chk_final_check_2,
    '{{chk_final_check_3}}' => $chk_final_check_3,
    '{{inspection_end_date}}' => htmlspecialchars($inspection_end_date),
    '{{inspection_end_time}}' => htmlspecialchars($inspection_end_time),
    '{{receiver_name}}' => htmlspecialchars($receiver_name),
    '{{receiver_position}}' => htmlspecialchars($receiver_position),
    '{{sender_name}}' => htmlspecialchars($sender_name),
    '{{sender_position}}' => htmlspecialchars($sender_position),

    // Signatures
    '{{receiver_signature_img}}' => $receiver_signature_img,
    '{{sender_signature_img}}' => $sender_signature_img,

    // Scene Sketch (Page 4)
    '{{scene_sketch_img}}' => $scene_sketch_img,
    '{{sketch_remark}}' => htmlspecialchars($sketch_remark),
    '{{sketch_recorder}}' => htmlspecialchars($sketch_recorder),
    '{{sketch_datetime}}' => htmlspecialchars($sketch_datetime),

    // Body Diagram (Page 6)
    '{{body_diagram_victim_name}}' => htmlspecialchars($body_diagram_victim_name),
    '{{body_diagram_victim_age}}' => htmlspecialchars($body_diagram_victim_age),
    '{{body_diagram_doctor}}' => htmlspecialchars($body_diagram_doctor),
    '{{body_diagram_img}}' => $body_diagram_img,

    // Evidence Table (Page 5)
    '{{evidence_table_rows}}' => $evidence_table_rows,
    '{{reference_point_1}}' => htmlspecialchars($reference_point_1),
    '{{reference_point_2}}' => htmlspecialchars($reference_point_2),
    '{{reference_point_3}}' => htmlspecialchars($reference_point_3),
    '{{reference_point_4}}' => htmlspecialchars($reference_point_4),
    '{{ev_recorder}}' => htmlspecialchars($ev_recorder),
    '{{ev_datetime}}' => htmlspecialchars($ev_datetime),

    // Evidence Collection Table (Page 7)
    '{{evidence_collection_rows}}' => $evidence_collection_rows,
    '{{ec_inspect_date}}' => htmlspecialchars($ec_inspect_date),
    '{{ec_inspect_time}}' => htmlspecialchars($ec_inspect_time),
    '{{ec_recorder}}' => htmlspecialchars($ec_recorder),
    '{{ec_datetime}}' => htmlspecialchars($ec_datetime),

    // Photo Records (Page 8)
    '{{photo_inspect_date}}' => htmlspecialchars($photo_inspect_date),
    '{{photo_inspect_time}}' => htmlspecialchars($photo_inspect_time),
    '{{photo_id_start}}' => htmlspecialchars($photo_id_start),
    '{{photo_id_end}}' => htmlspecialchars($photo_id_end),
    '{{photo_amount}}' => htmlspecialchars($photo_amount),
    '{{photo_images}}' => $photo_images,
];

// แทนที่ค่าใน HTML
$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// เพิ่มหน้ารูปภาพเพิ่มเติม (รูปที่ 2+) ก่อน </body>
if (!empty($photo_extra_pages)) {
    $htmlContent = str_replace('</body>', $photo_extra_pages . "\n</body>", $htmlContent);
}

// คำนวณจำนวนหน้ารวม: 8 หน้าพื้นฐาน + หน้ารูปเพิ่ม (35 รูป/หน้า)
$photoPages = !empty($validPhotos) ? (int)ceil(count($validPhotos) / $PHOTOS_PER_PAGE) : 0;
$total_pages = 8 + $photoPages;
$htmlContent = str_replace('{{total_pages}}', $total_pages, $htmlContent);

// ==========================================
// 5. OUTPUT HTML (สำหรับทดสอบก่อน)
// ==========================================
header('Content-Type: text/html; charset=utf-8');
echo $htmlContent;

// ==========================================
// 6. TODO: CONVERT TO PDF (ใช้หลังจากทดสอบ HTML แล้ว)
// ==========================================
// require_once __DIR__ . '/../../vendor/autoload.php';
// use Mpdf\Mpdf;
//
// $mpdf = new Mpdf([
//     'mode' => 'utf-8',
//     'format' => 'A4',
//     'margin_left' => 12,
//     'margin_right' => 12,
//     'margin_top' => 10,
//     'margin_bottom' => 6,
// ]);
//
// $mpdf->WriteHTML($htmlContent);
// $mpdf->Output('life_checklist_' . $incident_id . '.pdf', 'I');
