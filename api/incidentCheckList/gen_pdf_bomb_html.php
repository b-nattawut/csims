<?php
// gen_pdf_bomb_html.php - Generate PDF for Bomb Case from HTML Template

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

// แปลงค่า user ที่อาจเป็น ID หรือชื่อ ให้ได้ข้อความชื่อที่พร้อมแสดงผล
function resolveUserDisplayName($rawValue, $userMap)
{
    if ($rawValue === null) return '';
    $raw = trim((string)$rawValue);
    if ($raw === '') return '';
    if (isset($userMap[$raw])) return $userMap[$raw];
    if (is_numeric($raw) && isset($userMap[(int)$raw])) return $userMap[(int)$raw];
    return $raw;
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

// --- กลุ่มข้อมูลหลัก (Top-level Arrays) ---
$gen        = $data['general_info'] ?? [];
$victims    = $data['victims'] ?? [];
$scene      = $data['scene_info'] ?? [];
$inspection = $data['inspection_results'] ?? [];
$inspectorIds = $data['inspectors'] ?? [];
$handover   = $data['handover'] ?? [];

// --- กลุ่มข้อมูลตารางและ Meta ต่างๆ ---
$evidences_found    = $data['evidences_found'] ?? [];
$ev_meta            = $data['evidence_found_meta'] ?? [];
$measurements       = $data['measurements'] ?? [];
$mm_meta            = $data['measurement_meta'] ?? [];
$sketch_meta        = $data['sketch_meta'] ?? [];
$body_diagram_meta  = $data['body_diagram_meta'] ?? [];
$photo_records      = $data['photo_records'] ?? [];

// --- กลุ่มข้อมูลย่อยภายใน Scene ---
$env     = $scene['environment'] ?? [];
$outdoor = $scene['outdoor'] ?? [];
$indoor  = $scene['indoor'] ?? [];

// --- กลุ่มข้อมูลย่อยภายใน Inspection ---
$bodies    = $inspection['bodies'] ?? [];
$bomb      = $inspection['bomb_evidence'] ?? [];
$blood     = $inspection['blood_evidence'] ?? [];
$collected = $inspection['collected_evidence'] ?? [];

// ---------------------------------------------------------
// 3.1 ข้อมูลพื้นฐานและการรับแจ้งเหตุ (General Info)
// ---------------------------------------------------------

// เลขรายงาน + ปี พ.ศ. สำหรับ header ทุกหน้า
// การจัดการเลขรายงาน (เช่น BM-0001/2569)
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
$case_date = thaiDate(getVal($gen, 'case_date'));
$case_time = thaiTime(getVal($gen, 'case_time'));

// ช่องทางการรับแจ้ง
$report_channel = getVal($gen, 'report_channel', []);
$chk_notify_phone  = renderCheckbox(in_array('ทางโทรศัพท์', $report_channel));
$chk_notify_radio  = renderCheckbox(in_array('ทางวิทยุสื่อสาร', $report_channel));
$chk_notify_letter = renderCheckbox(in_array('ทางหนังสือ', $report_channel));
$chk_notify_other  = renderCheckbox(in_array('อื่นๆ', $report_channel));
$notify_other_text = getVal($gen, 'report_channel_other');

// ข้อมูลสถานที่รับแจ้ง สภ./สน., ที่, ลง
$police_station = getVal($gen, 'source_station');
$location_at    = getVal($gen, 'document_no');
// record_date อาจเป็นข้อความตรงๆ หรือวันที่
$raw_record_date = getVal($gen, 'document_date');
$record_date = !empty($raw_record_date) ? (strtotime($raw_record_date) ? thaiDate($raw_record_date) : $raw_record_date) : '';

// ข้อมูลพนักงานสอบสวน
$investigator_name  = getVal($gen['investigator'] ?? [], 'name');
$investigator_phone = getVal($gen['investigator'] ?? [], 'phone');

// ---------------------------------------------------------
// 3.2 วันเวลาที่ทราบเหตุ/ตรวจเหตุ
// ---------------------------------------------------------
$victim_know_date  = thaiDate(getVal($gen, 'victim_know_date'));
$victim_know_time  = thaiTime(getVal($gen, 'victim_know_time'));
$officer_know_date = thaiDate(getVal($gen, 'officer_know_date'));
$officer_know_time = thaiTime(getVal($gen, 'officer_know_time'));

$inspect_date      = thaiDate(getVal($gen, 'inspect_date'));
$inspect_time      = thaiTime(getVal($gen, 'inspect_time'));
$inspect_add_date  = thaiDate(getVal($gen, 'inspect_additional_date'));
$inspect_add_time  = thaiTime(getVal($gen, 'inspect_additional_time'));

// ---------------------------------------------------------
// 3.3 ลักษณะสถานที่เกิดเหตุ (Environment)
// ---------------------------------------------------------
$crime_location = getVal($scene, 'crime_location');

// การรักษาสถานที่
$preservation = getVal($scene, 'preservation');
$chk_preserved_yes = renderCheckbox($preservation == 'มี');
$chk_preserved_no  = renderCheckbox($preservation == 'ไม่มี');
$preserved_no_text = getVal($scene, 'preservation_detail');

// แสงสว่าง
$chk_light_bright = renderCheckbox(in_array('สว่าง', $env['lighting'] ?? []));
$chk_light_dark    = renderCheckbox(in_array('มืด', $env['lighting'] ?? []));
$chk_light_pole  = renderCheckbox(in_array('เสาไฟส่องสว่าง', $env['lighting'] ?? []));
$chk_light_other  = renderCheckbox(in_array('อื่นๆ', $env['lighting'] ?? []));
$light_other_text = getVal($env, 'lighting_other');

//อุณหภูมิ
$chk_temp_hot   = renderCheckbox(in_array('ร้อน', $env['temperature'] ?? []));
$chk_temp_cold  = renderCheckbox(in_array('เย็น', $env['temperature'] ?? []));
$chk_temp_ac    = renderCheckbox(in_array('เครื่องปรับอากาศ', $env['temperature'] ?? []));
$chk_temp_other = renderCheckbox(in_array('อื่นๆ', $env['temperature'] ?? []));
$temp_other_text = getVal($env, 'temperature_other');

//กลิ่น
// ดึงค่าหลักมาก่อน
$smell_val = getVal($env, 'smell'); // 'มี' หรือ 'ไม่มี'
$smell_detail = getVal($env, 'smell_detail');

$chk_smell_yes = renderCheckbox($smell_val == 'มี');
$chk_smell_no  = renderCheckbox($smell_val == 'ไม่มี');

$smell_yes_text = ($smell_val == 'มี') ? $smell_detail : '';
$smell_no_text  = ($smell_val == 'ไม่มี') ? $smell_detail : '';

// ---------------------------------------------------------
// 3.4 ข้อมูลภายนอก/ภายในอาคาร (Outdoor/Indoor)
// ---------------------------------------------------------

// 3.4.1 ข้อมูลภายนอกอาคาร (Outdoor)
$chk_outdoor = renderCheckbox($outdoor['is_active'] ?? false);

// ลักษณะพื้นที่ภายนอก (Checkbox 5 ตัว)
$out_types = $outdoor['type'] ?? [];
$chk_out_road   = renderCheckbox(in_array('ถนน', $out_types));
$chk_out_lawn   = renderCheckbox(in_array('สนามหญ้า', $out_types));
$chk_out_garden = renderCheckbox(in_array('ในสวน', $out_types));
$chk_out_empty  = renderCheckbox(in_array('ที่ว่าง', $out_types));
$chk_out_other  = renderCheckbox(in_array('อื่นๆ', $out_types));
$out_type_other_text = getVal($outdoor, 'type_other');

// สภาพบริเวณโดยรอบ (ภายนอก)
// เมื่อหันหน้าเข้าสถานที่เกิดเหตุ
$out_entrance = getVal($outdoor, 'entrance');
// ด้านหน้า/ซ้าย/ขวา/หลังติด ( outdoor )
$out_adj      = $outdoor['adjacent'] ?? [];
$out_adj_front = getVal($out_adj, 'front');
$out_adj_left  = getVal($out_adj, 'left');
$out_adj_right = getVal($out_adj, 'right');
$out_adj_back  = getVal($out_adj, 'back');
// บริเวณที่เกิดเหตุ
$out_incident_area = getVal($outdoor, 'incident_area');

// 3.4.2 ข้อมูลภายในอาคาร (Indoor)
$chk_indoor = renderCheckbox($indoor['is_active'] ?? false);

// ลักษณะภายนอกอาคาร (ประเภทอาคารและจำนวนชั้น)
$bld_types_raw = $indoor['building_type'] ?? [];
$bld_floors = $indoor['building_floors'] ?? []; // อาร์เรย์เก็บเลขชั้น [value => floor_num]

// รองรับข้อมูลเก่าที่บันทึกค่าอาคารเป็นภาษาไทย
$buildingTypeMap = [
    'commercial' => 'commercial',
    'อาคารพาณิชย์' => 'commercial',
    'house' => 'house',
    'บ้านเดี่ยว' => 'house',
    'other' => 'other',
    'อื่นๆ' => 'other',
];

$bld_types = [];
foreach ((array)$bld_types_raw as $type) {
    $normalized = $buildingTypeMap[$type] ?? $type;
    if (!in_array($normalized, $bld_types, true)) {
        $bld_types[] = $normalized;
    }
}

$chk_bld_comm  = renderCheckbox(in_array('commercial', $bld_types));
$floor_bld_comm = $bld_floors['commercial'] ?? ($bld_floors['อาคารพาณิชย์'] ?? '');

$chk_bld_house = renderCheckbox(in_array('house', $bld_types));
$floor_bld_house = $bld_floors['house'] ?? ($bld_floors['บ้านเดี่ยว'] ?? '');

$chk_bld_other = renderCheckbox(in_array('other', $bld_types));
$bld_other_text = getVal($indoor, 'building_type_other');
$floor_bld_other = $bld_floors['other'] ?? ($bld_floors['อื่นๆ'] ?? '');

// ดึงค่าจำนวนชั้นมาแสดง (ดึงตัวแรกที่เจอที่มีค่า)
// --- ตรวจสอบและรวบรวมจำนวนชั้นทั้งหมด ---
$floors = [];
if (!empty($floor_bld_comm)) $floors[] = $floor_bld_comm;
if (!empty($floor_bld_house)) $floors[] = $floor_bld_house;
if (!empty($floor_bld_other)) $floors[] = $floor_bld_other;

// เชื่อมข้อมูลด้วย , (เช่น 1, 2, 5)
$floor_display_val = implode(', ', $floors);

// ติ๊กถูกอัตโนมัติถ้ามีข้อมูลชั้นใดชั้นหนึ่ง
$chk_floor_all = renderCheckbox(!empty($floors));

// สภาพบริเวณโดยรอบ (ภายใน) - มีรั้ว/ไม่มีรั้ว
$chk_fence_yes = renderCheckbox(getVal($indoor, 'fence') == 'มีรั้ว');
$chk_fence_no  = renderCheckbox(getVal($indoor, 'fence') == 'ไม่มีรั้ว');

// เมื่อหันหน้าเข้าสถานที่เกิดเหตุ
$in_entrance = getVal($indoor, 'entrance');
// ด้านหน้า/ซ้าย/ขวา/หลังติด ( indoor )
$in_adj      = $indoor['adjacent'] ?? [];
$in_adj_front = getVal($in_adj, 'front');
$in_adj_left  = getVal($in_adj, 'left');
$in_adj_right = getVal($in_adj, 'right');
$in_adj_back  = getVal($in_adj, 'back');
// ลักษณะภายใน
$in_interior_detail = getVal($indoor, 'interior');
// บริเวณที่เกิดเหตุ
$in_incident_area   = getVal($indoor, 'incident_area');

// โครงสร้างบริเวณที่เกิดเหตุ (Structure)
$st = $indoor['structure'] ?? [];
// มีขนาดกว้าง x ยาว ประมาณ 
$st_size = getVal($st, 'size');
$chk_st_size = renderCheckbox(!empty(trim($st_size)));
// ลักษณะโครงสร้าง
$st_type = getVal($st, 'type');
$chk_st_type = renderCheckbox(!empty(trim($st_type)));
// ผนัง
$st_wall = getVal($st, 'wall');
$chk_st_wall = renderCheckbox(!empty(trim($st_wall)));
// พื้น
$st_floor = getVal($st, 'floor');
$chk_st_floor = renderCheckbox(!empty(trim($st_floor)));
// หลังคา
$st_roof = getVal($st, 'roof');
$chk_st_roof = renderCheckbox(!empty(trim($st_roof)));
// การจัดวางสิ่งของ
$st_arrangement = getVal($st, 'arrangement');
$chk_st_arrangement = renderCheckbox(!empty(trim($st_arrangement)));

// ด้านหน้า/ซ้าย/ขวา/หลังติด ( structure )
$st_adj = $st['adjacent'] ?? [];
$st_adj_front = getVal($st_adj, 'front');
$st_adj_left  = getVal($st_adj, 'left');
$st_adj_right = getVal($st_adj, 'right');
$st_adj_back  = getVal($st_adj, 'back');

// ---------------------------------------------------------
// 3.5 ผลการตรวจสถานที่เกิดเหตุ (Inspection Results)
// ---------------------------------------------------------

// 3.5.1 ข้อมูลพฤติการณ์และความเสียหายเบื้องต้น
$case_behavior  = getVal($inspection, 'case_behavior'); // พฤติการณ์คดี
$damage_details = getVal($inspection, 'damage_details'); // ความเสียหาย
$expl_point     = getVal($inspection, 'explosion_point'); // ตำแหน่งที่เกิดการระเบิด

// 3.5.2 วัตถุพยานระเบิด (Bomb Evidence)
$chk_bomb_active = renderCheckbox($bomb['is_active'] ?? false);

// ภาชนะบรรจุ (Containers)
$cont = $bomb['containers'] ?? [];
$chk_cont_steel_box = renderCheckbox($cont['steel_box'] ?? false);
$chk_cont_gas_tank   = renderCheckbox($cont['gas_tank'] ?? false);
$chk_cont_fire_ext   = renderCheckbox($cont['fire_ext'] ?? false);
$chk_cont_steel_pipe = renderCheckbox($cont['steel_pipe'] ?? false);
$chk_cont_pvc_pipe   = renderCheckbox($cont['pvc_pipe'] ?? false);
$chk_cont_ac_tank    = renderCheckbox($cont['ac_tank'] ?? false);
$chk_cont_std_bomb   = renderCheckbox($cont['std_bomb'] ?? false);
$chk_cont_other      = renderCheckbox($cont['other'] ?? false);
$cont_other_text     = getVal($cont, 'other_text');

// วิธีการจุดระเบิด (Detonation)
$det = $bomb['detonation'] ?? [];
$chk_det_trap   = renderCheckbox($det['trap'] ?? false); // กับดัก/เหยียบ/สะดุด
$det_trap_text  = getVal($det, 'trap_detail'); // กับดัก/เหยียบ/สะดุด ( อื่น ๆ )
$chk_det_wire   = renderCheckbox($det['wire'] ?? false); // ลากสายไฟ
$det_wire_color = getVal($det, 'wire_color'); // ลากสายไฟ ( สี )
$det_wire_len   = getVal($det, 'wire_length'); // ลากสายไฟ ( ยาว )
$chk_det_radio  = renderCheckbox($det['radio'] ?? false); // วิทยุสื่อสาร
$det_radio_brand = getVal($det, 'radio_brand'); // วิทยุสื่อสาร - ยี่ห้อ
$det_radio_model = getVal($det, 'radio_model'); // วิทยุสื่อสาร - รุ่น
$det_radio_color = getVal($det, 'radio_color'); // วิทยุสื่อสาร - สี
$det_radio_sn    = getVal($det, 'radio_sn'); // วิทยุสื่อสาร - s/n
$chk_det_phone  = renderCheckbox($det['phone'] ?? false); // โทรศัพท์มือถือ
$det_phone_brand = getVal($det, 'phone_brand'); // โทรศัพท์มือถือ - ยี่ห้อ
$det_phone_model = getVal($det, 'phone_model'); // โทรศัพท์มือถือ - รุ่น
$det_phone_color = getVal($det, 'phone_color'); // โทรศัพท์มือถือ - สี
$det_phone_sn    = getVal($det, 'phone_sn'); // โทรศัพท์มือถือ - s/n
$chk_det_remote = renderCheckbox($det['remote'] ?? false);  // รีโมทคอนโทรล
$det_remote_text = getVal($det, 'remote_detail'); // รีโมทคอนโทรล - detail
$chk_det_timer  = renderCheckbox($det['timer'] ?? false); // ตั้งเวลา 
$det_timer_text  = getVal($det, 'timer_detail'); // ตั้งเวลา - detail 
$chk_det_other  = renderCheckbox($det['other'] ?? false); // อื่น ๆ 
$det_other_text  = getVal($det, 'other_detail'); // อื่น ๆ - detail 

// สะเก็ดระเบิด (Fragments)
$frag = $bomb['fragments'] ?? [];
$chk_frag_rebar = renderCheckbox($frag['rebar'] ?? false); // เหล็กเส้นตัดท่อน
$frag_rebar_size = getVal($frag, 'rebar_size'); // เหล็กเส้นตัดท่อน - ขนาด 
$frag_rebar_len  = getVal($frag, 'rebar_length'); // เหล็กเส้นตัดท่อน - ยาว
$chk_frag_nail  = renderCheckbox($frag['nail'] ?? false); // ตะปู
$frag_nail_size  = getVal($frag, 'nail_size'); // ตะปู - ขนาด
$chk_frag_other = renderCheckbox($frag['other'] ?? false); // อื่น ๆ
$frag_other_text = getVal($frag, 'other_detail'); // อื่น ๆ - detail 

// ส่วนประกอบระเบิด (Components)
$comp = $bomb['components'] ?? [];
$chk_comp_booster   = renderCheckbox($comp['booster'] ?? false); // หลอดดินขยาย
$comp_booster_text  = getVal($comp, 'booster_detail'); // หลอดดินขยาย - detail
$chk_comp_detonator = renderCheckbox($comp['detonator'] ?? false); // เชื้อปะทุไฟฟ้า
$comp_detonator_text = getVal($comp, 'detonator_detail'); // เชื้อปะทุไฟฟ้า - detail
$chk_comp_tape      = renderCheckbox($comp['tape'] ?? false); // เทปพันสายไฟ
$comp_tape_text     = getVal($comp, 'tape_detail'); // เทปพันสายไฟ - detail
$chk_comp_sim       = renderCheckbox($comp['sim'] ?? false); // ซิมการ์ด
$comp_sim_text      = getVal($comp, 'sim_detail'); // ซิมการ์ด - detail 
$chk_comp_circuit   = renderCheckbox($comp['circuit'] ?? false); // วงจรการจุดระเบิด
$comp_circuit_text  = getVal($comp, 'circuit_detail'); // วงจรการจุดระเบิด - detail
$chk_comp_battery   = renderCheckbox($comp['battery'] ?? false); // แบตเตอรี่
$comp_battery_text  = getVal($comp, 'battery_detail'); // แบตเตอรี่ - detail
$comp_battery_v     = getVal($comp, 'battery_v'); // แบตเตอรี่ - voltage
$chk_comp_dtmf      = renderCheckbox($comp['dtmf'] ?? false); // แผงวงจร DTMF
$comp_dtmf_text     = getVal($comp, 'dtmf_detail'); // แผงวงจร DTMF - detail
$chk_comp_pcb       = renderCheckbox($comp['pcb'] ?? false); // แผงวงจร
$comp_pcb_text      = getVal($comp, 'pcb_detail'); // แผงวงจร - detail
$chk_comp_wire      = renderCheckbox($comp['wire'] ?? false); // สายไฟวงจร 
$comp_wire_text     = getVal($comp, 'wire_detail'); // สายไฟวงจร - detail
$chk_comp_box       = renderCheckbox($comp['box'] ?? false); //  กล่องบรรจุวงจร
$comp_box_text      = getVal($comp, 'box_detail'); //  กล่องบรรจุวงจร - detail
$chk_comp_clock     = renderCheckbox($comp['clock'] ?? false); // นาฬิกา 
$comp_clock_text    = getVal($comp, 'clock_detail'); // นาฬิกา - detail
$chk_comp_lever     = renderCheckbox($comp['lever'] ?? false); // กระเดื่อง 
$comp_lever_text    = getVal($comp, 'lever_detail'); // กระเดื่อง - detail
$chk_comp_pin       = renderCheckbox($comp['pin'] ?? false); // สลักนิรภัย 
$comp_pin_text      = getVal($comp, 'pin_detail'); // สลักนิรภัย - detail
$chk_comp_other     = renderCheckbox($comp['other'] ?? false); //  อื่น ๆ 
$comp_other_text    = getVal($comp, 'other_detail'); //  อื่น ๆ - detail

// 3.5.3 คราบสีแดงคล้ายโลหิต (Blood Evidence)
$chk_blood_active = renderCheckbox($blood['is_active'] ?? false);
$blood_detail     = getVal($blood, 'detail'); // detail คราบสีแดงคล้ายโลหิต

// มีการทดสอบด้วยชุดทดสอบคราบโลหิตใดๆ หรือไม่ 
$has_any_blood_test = ($blood['test_hemastix'] ?? false) || ($blood['test_phenol'] ?? false);
$chk_test_blood_main = renderCheckbox($has_any_blood_test);

// ผลทดสอบ Hemastix 
$chk_test_hema    = renderCheckbox($blood['test_hemastix'] ?? false); // เก็บสถานะว่าเลือกทดสอบด้วย hemastix
$hema_res         = getVal($blood, 'hemastix_result'); // ผลจากการทดสอบด้วย hemastix (มีการเปลี่ยนแปลง / ไม่มีการเปลี่ยนแปลง)
$chk_hema_pos     = renderCheckbox($hema_res == 'มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน');
$chk_hema_neg     = renderCheckbox($hema_res == 'ไม่มีการเปลี่ยนแปลง');

// ผลทดสอบ Phenolphthalein
$chk_test_phenol  = renderCheckbox($blood['test_phenol'] ?? false); // เก็บสถานะว่าเลือกทดสอบด้วย phenolphthalein
$phenol_res       = getVal($blood, 'phenol_result'); // ผลจากการทดสอบด้วย phenolphthalein (มีการเปลี่ยนแปลง / ไม่มีการเปลี่ยนแปลง)
$chk_phenol_pos   = renderCheckbox($phenol_res == 'มีการเปลี่ยนแปลงเป็นสีชมพูในทันที');
$chk_phenol_neg   = renderCheckbox($phenol_res == 'ไม่มีการเปลี่ยนแปลง');

// 3.5.4 วัตถุพยานอื่นๆ และ วัตถุพยานที่ตรวจเก็บ (Collected)
$chk_other_ev_active = renderCheckbox($inspection['other_evidence']['is_active'] ?? false); // วัตถุพยานอื่นๆ
$other_ev_detail     = getVal($inspection['other_evidence'] ?? [], 'detail'); // วัตถุพยานอื่นๆ - detail

$chk_coll_dna     = renderCheckbox($collected['has_dna'] ?? false); // วัตถุพยานประเภทสารพันธุกรรม
$coll_dna_detail  = getVal($collected, 'dna_detail'); // วัตถุพยานประเภทสารพันธุกรรม - detail
$chk_coll_finger  = renderCheckbox($collected['has_fingerprint'] ?? false); // วัตถุพยานประเภทลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง
$coll_finger_detail = getVal($collected, 'fingerprint_detail'); // วัตถุพยานประเภทลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง - detail
$chk_coll_tool    = renderCheckbox($collected['has_toolmark'] ?? false); // วัตถุพยานประเภทร่องรอยการตัด (ToolMarks)
$coll_tool_detail = getVal($collected, 'toolmark_detail'); // วัตถุพยานประเภทร่องรอยการตัด (ToolMarks) - detail
$chk_coll_expl    = renderCheckbox($collected['has_explosive'] ?? false); // วัตถุพยานประเภทสารระเบิด
$coll_expl_detail = getVal($collected, 'explosive_detail'); // วัตถุพยานประเภทสารระเบิด - detail
$chk_coll_comp    = renderCheckbox($collected['has_comp'] ?? false); // วัตถุพยานประเภทส่วนประกอบของวัตถุระเบิด
$coll_comp_detail = getVal($collected, 'comp_detail'); // วัตถุพยานประเภทส่วนประกอบของวัตถุระเบิด - detail
$chk_coll_other   = renderCheckbox($collected['has_other'] ?? false); // วัตถุพยานประเภทอื่น ๆ
$coll_other_detail = getVal($collected, 'other_detail'); // วัตถุพยานประเภทอื่น ๆ - detail

// 3.5.5 การตรวจสอบครั้งสุดท้าย (Final Check)
$final = $inspection['final_check'] ?? [];
$chk_final_1 = renderCheckbox(in_array('การตรวจสอบครั้งสุดท้าย', $final));
$chk_final_2 = renderCheckbox(in_array('ตรวจเก็บวัตถุพยานครบถ้วน', $final));
$chk_final_3 = renderCheckbox(in_array('ถ่ายภาพสถานที่เกิดเหตุและดำเนินการส่งมอบสถานที่เกิดเหตุให้แก่พนักงานสอบสวน', $final));

// ---------------------------------------------------------
// 3.6 รายชื่อผู้ตรวจสถานที่เกิดเหตุ (Inspectors)
// ---------------------------------------------------------
$inspectorNames = [];

// แปลง ID เป็นชื่อเต็มโดยใช้ $userMap ที่เตรียมไว้
if (is_array($inspectorIds)) {
    foreach ($inspectorIds as $id) {
        $displayName = resolveUserDisplayName($id, $userMap);
        if ($displayName !== '') {
            $inspectorNames[] = $displayName;
        }
    }
}

// สร้าง HTML แถวผู้ตรวจแบบลำดับ 5.1, 5.2, ...
$inspector_rows_html = '';
if (!empty($inspectorNames)) {
    foreach ($inspectorNames as $idx => $name) {
        $no = $idx + 1;
        $inspector_rows_html .= '<div class="si"><span class="si-no">5.' . $no . '.</span><span class="si-dots">' . htmlspecialchars($name) . '</span></div>' . "\n";
    }
} else {
    // กรณีไม่มีข้อมูล ให้แสดงแถวว่าง 1 แถวเพื่อความสวยงามใน PDF
    $inspector_rows_html = '<div class="si"><span class="si-no">5.1.</span><span class="si-dots">......................................................................</span></div>';
}

// ---------------------------------------------------------
// 3.7 ข้อมูลส่วน Meta 
// ---------------------------------------------------------

// 3.7.1 ข้อมูลส่วน Meta ของวัตถุพยาน (Evidence Found Meta)
$ref_1 = getVal($ev_meta, 'ref_1'); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 1
$ref_2 = getVal($ev_meta, 'ref_2'); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 2
$ref_3 = getVal($ev_meta, 'ref_3'); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 3
$ref_4 = getVal($ev_meta, 'ref_4'); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 4

// ข้อมูลผู้จัดเก็บและวันเวลา
$ev_collector_raw = getVal($ev_meta, 'collector'); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - ผู้จัดเก็บ
$ev_collector = resolveUserDisplayName($ev_collector_raw, $userMap);
$ev_collect_datetime = thaiDateTime(getVal($ev_meta, 'collect_datetime')); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - วัน/เวลา

// 3.7.2 บันทึกการตรวจเก็บวัตถุพยาน (Measurement Meta - Fieldset 12)
$ec_inspect_date = thaiDate(getVal($mm_meta, 'inspection_date')); // วันที่ตรวจสอบที่เกิดเหตุ
$ec_inspect_time = thaiTime(getVal($mm_meta, 'inspection_date')); // เวลาประมาณ
if ($ec_inspect_date === '') $ec_inspect_date = $inspect_date;
if ($ec_inspect_time === '') $ec_inspect_time = $inspect_time;
$ec_recorder_raw = getVal($mm_meta, 'recorder'); // ผู้บันทึก
$ec_recorder = resolveUserDisplayName($ec_recorder_raw, $userMap);
$ec_datetime     = thaiDateTime(getVal($mm_meta, 'measurement_datetime')); // วัน/เวลา

// 3.7.3 แผนผังสังเขป (Sketch Meta - Fieldset 9)
$sketch_remark   = getVal($sketch_meta, 'remark');   // หมายเหตุ
$sketch_recorder_raw = getVal($sketch_meta, 'recorder'); // ผู้จดบันทึก
$sketch_recorder = resolveUserDisplayName($sketch_recorder_raw, $userMap);
$sketch_datetime = thaiDateTime(getVal($sketch_meta, 'datetime')); // วัน/เวลา

// 3.7.4 แผนผังตำแหน่งบาดแผล (Body Diagram Meta - Fieldset 11)
$bd_victim_name = getVal($body_diagram_meta, 'victim_name'); // ชื่อ-นามสกุล
$bd_victim_age  = getVal($body_diagram_meta, 'victim_age');  // อายุ (ปี)
$bd_doctor      = getVal($body_diagram_meta, 'doctor');      // แพทย์ผู้ชันสูตร
// $bd_remark      = getVal($body_diagram_meta, 'remark');      // หมายเหตุ

// 3.7.5 บันทึกการถ่ายภาพ (Photo Records - Fieldset 13)
$photo_id_start = getVal($photo_records, 'start');  // รหัสภาพถ่ายที่
$photo_id_end   = getVal($photo_records, 'end');    // ถึง
$photo_amount   = getVal($photo_records, 'amount'); // จำนวนภาพ
// รองรับทั้ง user_id และชื่อที่พิมพ์เอง
$photo_recorder_raw = getVal($photo_records, 'photographer_name'); // ผู้จดบันทึก
$photo_recorder = resolveUserDisplayName($photo_recorder_raw, $userMap);
$photo_datetime = thaiDateTime(getVal($photo_records, 'photographer_datetime')); // วัน/เวลา
if ($photo_recorder === '') $photo_recorder = $ec_recorder;
if ($photo_datetime === '') $photo_datetime = $ec_datetime;

// ---------------------------------------------------------
// 3.8 การส่งมอบสถานที่เกิดเหตุ (Handover - Fieldset 8)
// ---------------------------------------------------------
// ข้อมูลผู้รับมอบ
$rec_id = getVal($handover, 'receiver_id');
$receiver_name = resolveUserDisplayName($rec_id, $userMap);
$receiver_pos  = getVal($handover, 'receiver_pos');

// ข้อมูลผู้ส่งมอบ
$del_id = getVal($handover, 'deliverer_id');
$sender_name = resolveUserDisplayName($del_id, $userMap);
$sender_pos  = getVal($handover, 'deliverer_pos');

// วันเวลาที่ตรวจสถานที่เสร็จสิ้น (ใช้ฟังก์ชันที่สร้างใหม่)
$inspection_end_date  = thaiDate(getVal($handover, 'inspection_end_date'));
$inspection_end_time  = thaiTime(getVal($handover, 'inspection_end_time'));

// ---------------------------------------------------------
// 3.9 ตารางผู้ประสบเหตุ (Victims)
// ---------------------------------------------------------
$allVictims = $data['victims'] ?? [];
$victimRowsHtml = '';

// ตัวแปรสำหรับเก็บชื่อคนแรกไปแสดงใน Body Diagram (Fallback)
$first_victim_name = '';
$first_victim_age = '';

// จัดกลุ่มผู้ประสบเหตุตามประเภท (เพื่อให้แสดงเรียงกันตามฟอร์ม)
$victimsByType = [
    'ผู้เสียชีวิต' => [],
    'ผู้บาดเจ็บ' => [],
    'ผู้เสียหาย' => []
];

if (!empty($allVictims)) {
    foreach ($allVictims as $v) {
        $vType = $v['type'] ?? '';
        if (isset($victimsByType[$vType])) {
            $victimsByType[$vType][] = $v;

            // เก็บชื่อคนแรกที่เจอ (เน้นผู้เสียชีวิตหรือบาดเจ็บก่อน) เพื่อใช้ในหน้าแผนผังบาดแผล
            if (empty($first_victim_name) && ($vType == 'ผู้เสียชีวิต' || $vType == 'ผู้บาดเจ็บ')) {
                $first_victim_name = $v['name'] ?? '';
                $first_victim_age = $v['age'] ?? '';
            }
        }
    }
}

// สร้าง HTML แถวผู้ประสบเหตุ
$typeLabels = ['ผู้เสียชีวิต', 'ผู้บาดเจ็บ', 'ผู้เสียหาย'];
$isFirstRowOverall = true;

foreach ($typeLabels as $typeLabel) {
    $group = $victimsByType[$typeLabel];

    if (!empty($group)) {
        foreach ($group as $idx => $v) {
            $name = htmlspecialchars($v['name'] ?? '');
            $age  = htmlspecialchars($v['age'] ?? '');

            $marginStyle = $isFirstRowOverall ? ' style="margin-top:2px;"' : '';
            $victimRowsHtml .= '<div class="fr"' . $marginStyle . '>';

            if ($idx === 0) {
                // --- รายการแรกของประเภทนี้: แสดง Checkbox + ประเภท ---
                $chk = '✓';
                $victimRowsHtml .= '<label class="ck"><span class="cb">' . $chk . '</span>' . $typeLabel . '</label>';
            } else {
                // --- รายการที่ 2 เป็นต้นไป: ทำ Indent (เว้นช่องว่างให้ตรงกับด้านบน) ---
                // ใช้ Inline Style กำหนดความกว้างคงที่เพื่อให้คำว่า "ชื่อ" ตรงกันเป๊ะ
                $victimRowsHtml .= '<div style="min-width: 72px; display: inline-block;"></div>';
            }

            $victimRowsHtml .= '<span class="fl">ชื่อ</span>'
                . '<span class="fd">' . $name . '</span>'
                . '<span class="fl">อายุ</span>'
                . '<span class="fd-s">' . $age . '</span>'
                . '<span class="fl">ปี</span>'
                . '</div>';

            $isFirstRowOverall = false;
        }
    } else {
        // แสดงแถวว่างสำหรับประเภทที่ไม่มีคน (เพื่อให้ฟอร์มดูเต็ม)
        $marginStyle = $isFirstRowOverall ? ' style="margin-top:2px;"' : '';
        $victimRowsHtml .= '<div class="fr"' . $marginStyle . '>'
            . '<label class="ck"><span class="cb"></span>' . $typeLabel . '</label>'
            . '<span class="fl">ชื่อ</span>'
            . '<span class="fd"></span>'
            . '<span class="fl">อายุ</span>'
            . '<span class="fd-s"></span>'
            . '<span class="fl">ปี</span>'
            . '</div>';
        $isFirstRowOverall = false;
    }
}

// ส่งค่าไปยัง Body Diagram Meta (ถ้าใน JSON ไม่มีค่า ให้ใช้คนแรกจากตารางนี้)
if (empty($bd_victim_name)) $bd_victim_name = $first_victim_name;
if (empty($bd_victim_age))  $bd_victim_age  = $first_victim_age;

// ------------------------------------------------------------------
// 3.10 ข้อมูลศพ (Bodies) - แบ่งรายการแรกแสดงฝั่งซ้าย, รายการที่เหลือแสดงขวา
// ------------------------------------------------------------------

$bodies_left_html = '';
$bodies_right_html = '';
$min_bodies = 3; // กำหนดจำนวนรายการขั้นต่ำ ให้สอดคล้องตามฟอร์ม

// วนลูปตามจำนวนจริง หรืออย่างน้อย 3 รายการ
$total_bodies_to_render = max(count($bodies), $min_bodies);

if (!empty($bodies)) {
    for ($idx = 0; $idx < $total_bodies_to_render; $idx++) {
        $b = $bodies[$idx] ?? null;
        $no = $idx + 1;

        $body_name = $b ? getVal($b, 'name') : '';
        $status = $b ? getVal($b, 'status') : '';
        $chk_found = ($status == 'พบศพ') ? '✓' : '';
        $chk_not_found = ($status == 'ไม่พบศพ') ? '✓' : '';

        $detail = $b ? trim(getVal($b, 'condition_detail')) : '';
        $chk_condition = (!empty($detail)) ? '✓' : '';

        // ถ้าเป็นศพที่ 1 (ฝั่งซ้าย) หรือ ศพที่ 2 (ศพแรกของฝั่งขวา) ให้ margin เป็น 0
        $marginTop = ($idx === 1) ? '0px' : '10px';

        // 1. สร้างหัวข้อ (ศพที่ X)
        $current_body_html = '<div class="bh" style="margin-top:' . $marginTop . ';">'
            . '<span class="bk"></span>'
            . '<span>ศพที่ ' . $no . '</span>'
            . '<span class="fd" style="width:180px; flex:none; margin-left:5px;">' . htmlspecialchars($body_name) . '</span>'
            . '</div>';

        // 2. บรรทัด พบศพ
        $current_body_html .= '<div class="cg i1"><label class="ck"><span class="cb">' . $chk_found . '</span>พบศพ</label></div>';

        // 3. บรรทัด ไม่พบศพ + รายละเอียด
        $notfound_detail = $b ? htmlspecialchars(getVal($b, 'notfound_detail')) : '';
        $current_body_html .= '<div class="fr i1"><label class="ck" style="margin-right:2px;"><span class="cb">' . $chk_not_found . '</span>ไม่พบศพ</label>'
            . '<span class="fd">' . $notfound_detail . '</span></div>';

        // 4. บรรทัด สภาพศพ/การแต่งกาย
        $current_body_html .= '<div class="cg i1"><label class="ck"><span class="cb">' . $chk_condition . '</span>สภาพศพ, ลักษณะการแต่งกาย ทรัพย์สิน และอื่นๆ</label></div>';

        if (!empty($detail)) {
            // กรณีมีข้อมูล: แสดงข้อมูลและตามด้วยเส้นประว่าง 3 แถว
            $current_body_html .= '<div class="i2" style="line-height: 1.6; border-bottom: 1px dotted #888; margin-bottom: 2px;">'
                . nl2br(htmlspecialchars($detail)) . '</div>';
            for ($l = 0; $l < 3; $l++) {
                $current_body_html .= '<div class="fd-full i2"></div>';
            }
        } else {
            // กรณีไม่มีข้อมูล: ตีเส้นประว่าง 5 แถวเพื่อให้พื้นที่ดูเต็ม
            for ($l = 0; $l < 5; $l++) {
                $current_body_html .= '<div class="fd-full i2"></div>';
            }
        }

        // --- จุดสำคัญ: แยกเก็บตามลำดับ ---
        if ($idx === 0) {
            $bodies_left_html .= $current_body_html;
        } else {
            $bodies_right_html .= $current_body_html;
        }
    }
}

// ---------------------------------------------------------
// 3.11 ตารางวัตถุพยานที่ตรวจพบ (Evidences Found - ตารางหน้า 5)
// ---------------------------------------------------------
$evidence_found_rows = '';
$max_ev_rows = 15; // จำนวนแถวขั้นต่ำในตาราง
for ($i = 0; $i < max(count($evidences_found), $max_ev_rows); $i++) {
    $ev = $evidences_found[$i] ?? null;
    $no = $i + 1;

    if ($ev) {
        $item    = htmlspecialchars(getVal($ev, 'item'));
        $azimuth = htmlspecialchars(getVal($ev, 'azimuth'));
        $remark  = htmlspecialchars(getVal($ev, 'remark'));

        // ระยะห่างจากจุดอ้างอิง 1-4
        $ref1_dist = !empty($ev['ref1_dist']) ? htmlspecialchars($ev['ref1_dist']) : '';
        $ref2_dist = !empty($ev['ref2_dist']) ? htmlspecialchars($ev['ref2_dist']) : '';
        $ref3_dist = !empty($ev['ref3_dist']) ? htmlspecialchars($ev['ref3_dist']) : '';
        $ref4_dist = !empty($ev['ref4_dist']) ? htmlspecialchars($ev['ref4_dist']) : '';

        $evidence_found_rows .= "<tr>
            <td align='center' style='border: 1px solid #000; height: 25px;'>$no</td>
            <td style='border: 1px solid #000; padding-left: 5px;'>$item</td>
            <td align='center' style='border: 1px solid #000;'>$ref1_dist</td>
            <td align='center' style='border: 1px solid #000;'>$ref2_dist</td>
            <td align='center' style='border: 1px solid #000;'>$ref3_dist</td>
            <td align='center' style='border: 1px solid #000;'>$ref4_dist</td>
            <td style='border: 1px solid #000; padding-left: 5px;'>$azimuth</td>
            <td style='border: 1px solid #000; padding-left: 5px;'>$remark</td>
        </tr>";
    } else {
        // แถวว่าง
        $evidence_found_rows .= "<tr>
        <td align='center' style='border: 1px solid #000; height: 25px;'>$no</td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        </tr>";
    }
}

// ---------------------------------------------------------
// 3.12 ตารางรายการตรวจเก็บ (Measurements - ตารางหน้า 7)
// ---------------------------------------------------------
$measurement_rows = '';
$max_mm_rows = 12;
$icon = '✔';

for ($i = 0; $i < max(count($measurements), $max_mm_rows); $i++) {
    $m = $measurements[$i] ?? null;
    $no = $i + 1;

    if ($m) {
        $item = htmlspecialchars(getVal($m, 'item'));
        $qty  = htmlspecialchars(getVal($m, 'quantity'));
        $area = htmlspecialchars(getVal($m, 'area'));
        $lbl  = htmlspecialchars(getVal($m, 'label_no'));

        // --- การบรรจุหีบห่อ ---
        $pkg = $m['packaging'] ?? [];
        $p_plastic = !empty($pkg['plastic_text']) ? htmlspecialchars($pkg['plastic_text']) : (!empty($pkg['plastic']) ? $icon : '');
        $p_paper   = !empty($pkg['paper_text']) ? htmlspecialchars($pkg['paper_text']) : (!empty($pkg['paper']) ? $icon : '');
        $p_other   = !empty($pkg['other_text']) ? htmlspecialchars($pkg['other_text']) : (!empty($pkg['other']) ? $icon : '');

        // --- การดำเนินการ ---
        $act = $m['action'] ?? [];
        $a_return = !empty($act['return_text']) ? htmlspecialchars($act['return_text']) : (!empty($act['return']) ? $icon : '');
        $a_other  = !empty($act['other_text']) ? htmlspecialchars($act['other_text']) : (!empty($act['other']) ? $icon : '');

        $remark = htmlspecialchars(getVal($m, 'remark'));

        $measurement_rows .= "
            <tr>
                <td align='center' style='border:1px solid #000; height:30px;'>$no</td>
                <td style='border:1px solid #000; padding-left:3px;'>$item</td>
                <td align='center' style='border:1px solid #000;'>$qty</td>
                <td style='border:1px solid #000; padding-left:3px;'>$area</td>
                <td align='center' style='border:1px solid #000;'>$lbl</td>
                <td align='center' style='border:1px solid #000; font-size:10px;'>$p_plastic</td>
                <td align='center' style='border:1px solid #000; font-size:10px;'>$p_paper</td>
                <td align='center' style='border:1px solid #000; font-size:10px;'>$p_other</td>
                <td align='center' style='border:1px solid #000; font-size:10px;'>$a_return</td>
                <td align='center' style='border:1px solid #000; font-size:10px;'>$a_other</td>
                <td style='border:1px solid #000; padding-left:3px;'>$remark</td>
            </tr>";
    } else {
        $measurement_rows .= "<tr>
          <td align='center' style='border: 1px solid #000; height: 25px;'>$no</td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        <td style='border: 1px solid #000;'></td>
        </tr>";
    }
}

// ---------------------------------------------------------
// SIGNATURES (base64 → <img> tags)
// ---------------------------------------------------------
$signatures = $data['signatures'] ?? [];

// Helper: อ่านไฟล์จากดิสก์แปลงเป็น base64 data URI
function fileToBase64ForPdf($filePath)
{
    if (empty($filePath) || !file_exists($filePath)) return '';
    $content = file_get_contents($filePath);
    if ($content === false) return '';
    $mime = mime_content_type($filePath) ?: 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode($content);
}

// Helper: โหลด BLOB จาก incident_checklist_transaction_file โดย file_id → base64 data URI
function loadBlobAsBase64($pdo, $fileId)
{
    if (empty($fileId)) return '';
    try {
        $stmt = $pdo->prepare("SELECT file_name FROM incident_checklist_transaction_file WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['file_name'])) return '';
        $blobData = $row['file_name'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($blobData) ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($blobData);
    } catch (Exception $e) {
        return '';
    }
}

// Helper: normalize ค่ารูปให้เป็น data URI
function normalizeImageDataUri($raw)
{
    if (!is_string($raw)) return '';
    $raw = trim($raw);
    if ($raw === '') return '';

    if (stripos($raw, 'data:image') === 0) {
        return $raw;
    }

    if (preg_match('/^image\/[a-z0-9.+-]+;base64,/i', $raw)) {
        return 'data:' . $raw;
    }

    // รองรับ raw base64 ที่ไม่มี prefix data:image
    $compact = preg_replace('/\s+/', '', $raw);
    if ($compact !== '' && strlen($compact) > 80 && preg_match('/^[A-Za-z0-9+\/=]+$/', $compact)) {
        $decoded = base64_decode($compact, true);
        if ($decoded !== false) {
            return 'data:image/png;base64,' . $compact;
        }
    }

    return '';
}

// Helper: รองรับ input ได้ทั้ง array/string/file_id/getFile.php?id=.../filename
function resolveChecklistImageDataUri($pdo, $value, $fallbackDir = '')
{
    if (empty($value)) return '';

    if (is_array($value)) {
        $base64Keys = ['base64', 'data', 'src', 'url'];
        foreach ($base64Keys as $k) {
            if (!empty($value[$k])) {
                $uri = normalizeImageDataUri((string)$value[$k]);
                if ($uri !== '') return $uri;
            }
        }

        $idKeys = ['file_id', 'fileId', 'id'];
        foreach ($idKeys as $k) {
            if (!empty($value[$k])) {
                $uri = loadBlobAsBase64($pdo, $value[$k]);
                if ($uri !== '') return $uri;
            }
        }

        $nameKeys = ['filename', 'file_name', 'name', 'path'];
        foreach ($nameKeys as $k) {
            if (!empty($value[$k]) && $fallbackDir !== '') {
                $uri = fileToBase64ForPdf(rtrim($fallbackDir, '/\\') . DIRECTORY_SEPARATOR . basename((string)$value[$k]));
                if ($uri !== '') return $uri;
            }
        }

        return '';
    }

    if (is_string($value)) {
        $raw = trim($value);
        if ($raw === '') return '';

        $uri = normalizeImageDataUri($raw);
        if ($uri !== '') return $uri;

        if (ctype_digit($raw)) {
            $uri = loadBlobAsBase64($pdo, (int)$raw);
            if ($uri !== '') return $uri;
        }

        if (preg_match('/[?&]id=(\d+)/', $raw, $m)) {
            $uri = loadBlobAsBase64($pdo, (int)$m[1]);
            if ($uri !== '') return $uri;
        }

        if ($fallbackDir !== '') {
            $uri = fileToBase64ForPdf(rtrim($fallbackDir, '/\\') . DIRECTORY_SEPARATOR . basename($raw));
            if ($uri !== '') return $uri;
        }

        if (strpos($raw, 'uploads') !== false) {
            $possiblePath = realpath(__DIR__ . '/../../' . ltrim($raw, '/\\'));
            if ($possiblePath !== false) {
                $uri = fileToBase64ForPdf($possiblePath);
                if ($uri !== '') return $uri;
            }
        }
    }

    return '';
}

// Helper: สร้าง <img> tag จาก data ที่มาได้หลายรูปแบบ
function renderSignatureImg($pdo, $sigData, $fallbackDir = '')
{
    $imgUri = resolveChecklistImageDataUri($pdo, $sigData, $fallbackDir);
    if ($imgUri === '') return '';
    return '<img src="' . $imgUri . '" style="display:block; max-width:120px; max-height:38px; object-fit:contain; margin:0 auto;" alt="ลายเซ็น">';
}

$signaturesDir = __DIR__ . '/../../uploads_2/checklist_bomb_signatures/';

$receiver_signature_img = renderSignatureImg($pdo, $signatures['receiver_sig'] ?? '', $signaturesDir);
$sender_signature_img = renderSignatureImg($pdo, $signatures['sender_sig'] ?? '', $signaturesDir);
$scene_sketch_img_data = $signatures['scene_sketch'] ?? '';
$body_diagram_img_data = $signatures['body_diagram'] ?? '';

// สร้าง Scene Sketch <img>
$scene_sketch_img = '';
if (!empty($scene_sketch_img_data)) {
    $sketchBase64 = resolveChecklistImageDataUri($pdo, $scene_sketch_img_data, $signaturesDir);
    if (!empty($sketchBase64) && strpos($sketchBase64, 'data:image') !== false) {
        $scene_sketch_img = '<img src="' . $sketchBase64 . '" style="width:100%; height:100%; object-fit:contain; display:block;" alt="แผนผังสังเขป">';
    }
}

// สร้าง Body Diagram <img> — รูปรวม (พื้นหลัง + เส้นที่วาด) จาก JS
$body_diagram_img = '';
if (!empty($body_diagram_img_data)) {
    $bodyBase64 = resolveChecklistImageDataUri($pdo, $body_diagram_img_data, $signaturesDir);
    if (!empty($bodyBase64) && strpos($bodyBase64, 'data:image') !== false) {
        $body_diagram_img = '<img src="' . $bodyBase64 . '" style="max-width:100%; max-height:100%; object-fit:contain;" alt="แผนผังร่างกาย">';
    }
}
// Fallback: ถ้าไม่มีรูปจาก canvas ให้ใช้รูป static
if (empty($body_diagram_img)) {
    $body_diagram_img = '<img src="../../assets/images/body_diagram.png" style="max-width:100%; max-height:100%; object-fit:contain;" alt="แผนผังร่างกาย">';
}

// ---------------------------------------------------------
// PHOTO RECORDS
// ---------------------------------------------------------

// สร้าง Photo images HTML (grid 5 คอลัมน์ × 7 แถว = 35 รูป/หน้า)
$photos = $data['photos'] ?? [];
$photo_images = '';
$photo_extra_pages = '';
$PHOTOS_PER_PAGE = 35;

$photosDir = __DIR__ . '/../../uploads_2/checklist_bomb_photos/';
$validPhotos = [];
if (!empty($photos)) {
    foreach ($photos as $idx => $photo) {
        $pBase64 = resolveChecklistImageDataUri($pdo, $photo, $photosDir);
        $pName = '';
        if (is_array($photo)) {
            $pName = $photo['filename'] ?? $photo['name'] ?? $photo['file_name'] ?? '';
        }
        if (!empty($pBase64) && strpos($pBase64, 'data:image') !== false) {
            if (empty($pName)) $pName = 'photo_' . (count($validPhotos) + 1) . '.jpg';
            $validPhotos[] = ['base64' => $pBase64, 'name' => $pName];
        }
    }
}

// Override photo_id_start / photo_id_end / photo_amount with actual filenames for PDF
if (!empty($validPhotos)) {
    $totalPhotos = count($validPhotos);
    $firstPageEnd = min($PHOTOS_PER_PAGE, $totalPhotos) - 1;
    
    // Always use actual filenames in PDF (override user-entered values)
    $photo_id_start = $validPhotos[0]['name'];
    $photo_id_end = $validPhotos[$firstPageEnd]['name'];
    $photo_amount = (string)$totalPhotos;

    $pagesNeeded = (int)ceil($totalPhotos / $PHOTOS_PER_PAGE);

    // === หน้าแรก (Page 9): grid ===
    $photo_images = '<div class="photo-grid">';
    for ($i = 0; $i <= $firstPageEnd; $i++) {
        $safeName = htmlspecialchars($validPhotos[$i]['name']);
        $photo_images .= '<div class="photo-cell-wrapper">';
        $photo_images .= '<div class="photo-cell"><img src="' . $validPhotos[$i]['base64'] . '" alt="' . $safeName . '"></div>';
        $photo_images .= '<div class="photo-fname" title="' . $safeName . '">' . $safeName . '</div>';
        $photo_images .= '</div>';
    }
    $photo_images .= '</div>';

    // === หน้าถัดไป (Page 10, 11, ...) ===
    for ($p = 2; $p <= $pagesNeeded; $p++) {
        $startI = ($p - 1) * $PHOTOS_PER_PAGE;
        $endI = min($p * $PHOTOS_PER_PAGE, $totalPhotos) - 1;
        $pageStartName = htmlspecialchars($validPhotos[$startI]['name']);
        $pageEndName = htmlspecialchars($validPhotos[$endI]['name']);
        $pageNum = 9 + ($p - 1);

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
            <div class="title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด</div>
            <div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ (ต่อ)</div>
        </div>
        <div class="header-right">
            <div class="doc-box">
                <div class="doc-line">เลขรับที่/เลขรายงาน <span style="display:inline-block;min-width:60px;border-bottom:1px dotted #888;text-align:center;">' . htmlspecialchars($report_no) . '</span> / <span style="display:inline-block;min-width:40px;border-bottom:1px dotted #888;text-align:center;">' . htmlspecialchars($report_year) . '</span></div>
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
        <div style="display:flex; flex-direction:column; align-items:flex-end; margin-bottom:6px;">
            <div class="fr" style="width:auto;">
                <span class="fl">ผู้จดบันทึก</span>
                <span class="fd" style="width:250px;">' . htmlspecialchars($photo_recorder) . '</span>
            </div>
            <div class="fr" style="width:auto;">
                <span class="fl">วัน /เวลา</span>
                <span class="fd" style="width:250px;">' . htmlspecialchars($photo_datetime) . '</span>
            </div>
        </div>
        <div class="form-footer">
            <div class="form-footer-left">
                <strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ
            </div>
            <div class="form-footer-right">
                F-CS-10 แก้ไขครั้งที่ 2<br>
                แก้ไขวันที่ 2 ก.ย. 63<br>
                เริ่มใช้ 1 ต.ค. 63
            </div>
        </div>
    </div>
</div>';
    }
}

if (empty($photo_images)) {
    $photo_images = '<div style="flex:1; display:flex; align-items:center; justify-content:center;"><div style="color:#999; font-style:italic;">ไม่มีภาพถ่าย</div></div>';
}

// ==========================================
// 4. READ HTML TEMPLATE & REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_bomb_preview.html');

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
    '{{location_at}}'      => htmlspecialchars($location_at),
    '{{record_date}}'      => htmlspecialchars($record_date),
    '{{investigator_name}}' => htmlspecialchars($investigator_name),
    '{{investigator_phone}}' => htmlspecialchars($investigator_phone),

    // --- 2. สถานที่เกิดเหตุ ---
    '{{crime_location}}' => htmlspecialchars($crime_location),
    '{{victim_rows}}'    => $victimRowsHtml, // ใช้ HTML จากการวนลูปที่เตรียมไว้

    // --- 3. วันเวลาที่ทราบเหตุ / 4. ตรวจเหตุ ---
    '{{victim_know_date}}'  => htmlspecialchars($victim_know_date),
    '{{victim_know_time}}'  => htmlspecialchars($victim_know_time),
    '{{officer_know_date}}' => htmlspecialchars($officer_know_date),
    '{{officer_know_time}}' => htmlspecialchars($officer_know_time),
    '{{inspect_date}}'      => htmlspecialchars($inspect_date),
    '{{inspect_time}}'      => htmlspecialchars($inspect_time),
    '{{inspect_add_date}}'  => htmlspecialchars($inspect_add_date),
    '{{inspect_add_time}}'  => htmlspecialchars($inspect_add_time),

    // --- 5. รายชื่อผู้ตรวจสถานที่ ---
    '{{inspector_rows}}' => $inspector_rows_html,

    // --- 6. ลักษณะสถานที่เกิดเหตุ (Environment) ---
    '{{chk_preserved_yes}}' => $chk_preserved_yes,
    '{{chk_preserved_no}}'  => $chk_preserved_no,
    '{{preserved_no_text}}' => htmlspecialchars($preserved_no_text),
    '{{chk_light_bright}}'  => $chk_light_bright,
    '{{chk_light_dark}}'     => $chk_light_dark,
    '{{chk_light_pole}}'    => $chk_light_pole,
    '{{chk_light_other}}'   => $chk_light_other,
    '{{light_other_text}}'  => htmlspecialchars($light_other_text),
    '{{chk_temp_hot}}'      => $chk_temp_hot,
    '{{chk_temp_cold}}'     => $chk_temp_cold,
    '{{chk_temp_ac}}'       => $chk_temp_ac,
    '{{chk_temp_other}}'    => $chk_temp_other,
    '{{temp_other_text}}'   => htmlspecialchars($temp_other_text),
    '{{chk_smell_yes}}'     => $chk_smell_yes,
    '{{chk_smell_no}}'      => $chk_smell_no,
    '{{smell_yes_text}}'    => htmlspecialchars($smell_yes_text),
    '{{smell_no_text}}'     => htmlspecialchars($smell_no_text),

    // --- 6. Outdoor ---
    '{{chk_outdoor}}'       => $chk_outdoor,
    '{{chk_out_road}}'      => $chk_out_road,
    '{{chk_out_lawn}}'      => $chk_out_lawn,
    '{{chk_out_garden}}'    => $chk_out_garden,
    '{{chk_out_empty}}'     => $chk_out_empty,
    '{{chk_out_other}}'     => $chk_out_other,
    '{{out_type_other_text}}' => htmlspecialchars($out_type_other_text),
    '{{out_entrance}}'      => htmlspecialchars($out_entrance),
    '{{out_adj_front}}'     => htmlspecialchars($out_adj_front),
    '{{out_adj_left}}'      => htmlspecialchars($out_adj_left),
    '{{out_adj_right}}'     => htmlspecialchars($out_adj_right),
    '{{out_adj_back}}'      => htmlspecialchars($out_adj_back),
    '{{out_incident_area}}' => htmlspecialchars($out_incident_area),

    // --- 6. Indoor ---
    '{{chk_indoor}}'        => $chk_indoor,
    '{{chk_bld_comm}}'      => $chk_bld_comm,
    '{{floor_bld_comm}}'    => htmlspecialchars($floor_bld_comm),
    '{{chk_bld_house}}'     => $chk_bld_house,
    '{{floor_bld_house}}'   => htmlspecialchars($floor_bld_house),
    '{{chk_bld_other}}'     => $chk_bld_other,
    '{{bld_other_text}}'    => htmlspecialchars($bld_other_text),
    '{{floor_bld_other}}'   => htmlspecialchars($floor_bld_other),
    '{{chk_floor_all}}'     => $chk_floor_all,
    '{{floor_val}}'         => htmlspecialchars($floor_display_val),
    '{{chk_fence_yes}}'     => $chk_fence_yes,
    '{{chk_fence_no}}'      => $chk_fence_no,
    '{{in_entrance}}'       => htmlspecialchars($in_entrance),
    '{{in_adj_front}}'      => htmlspecialchars($in_adj_front),
    '{{in_adj_left}}'       => htmlspecialchars($in_adj_left),
    '{{in_adj_right}}'      => htmlspecialchars($in_adj_right),
    '{{in_adj_back}}'       => htmlspecialchars($in_adj_back),
    '{{in_interior_detail}}' => htmlspecialchars($in_interior_detail),
    '{{in_incident_area}}'  => htmlspecialchars($in_incident_area),

    /// --- 6. Indoor Structure ---
    '{{chk_st_size}}'    => $chk_st_size,
    '{{st_size}}'        => htmlspecialchars($st_size),
    '{{chk_st_type}}'    => $chk_st_type,
    '{{st_type}}'        => htmlspecialchars($st_type),
    '{{chk_st_wall}}'    => $chk_st_wall,
    '{{st_wall}}'        => htmlspecialchars($st_wall),
    '{{chk_st_floor}}'   => $chk_st_floor,
    '{{st_floor}}'       => htmlspecialchars($st_floor),
    '{{chk_st_roof}}'    => $chk_st_roof,
    '{{st_roof}}'        => htmlspecialchars($st_roof),
    '{{chk_st_arrangement}}' => $chk_st_arrangement,
    '{{st_arrangement}}' => htmlspecialchars($st_arrangement),
    '{{st_adj_front}}'   => htmlspecialchars($st_adj_front),
    '{{st_adj_left}}'    => htmlspecialchars($st_adj_left),
    '{{st_adj_right}}'   => htmlspecialchars($st_adj_right),
    '{{st_adj_back}}'    => htmlspecialchars($st_adj_back),

    // --- 7. ผลการตรวจสถานที่เกิดเหตุ (Behavior & Bomb) ---
    '{{case_behavior}}'  => htmlspecialchars($case_behavior),
    '{{damage_details}}' => htmlspecialchars($damage_details),
    '{{expl_point}}'     => htmlspecialchars($expl_point),
    '{{bodies_left_html}}' => $bodies_left_html, // รายการศพฝั่งซ้าย (รายการแรก)
    '{{bodies_right_html}}' => $bodies_right_html, // รายการศพฝั่งซ้าย (รายการที่ 2 เป็นต้นไป)

    // --- ระเบิด (Containers/Detonation/Fragments/Components) ---
    '{{chk_bomb_active}}'    => $chk_bomb_active,

    // --- ระเบิด (Containers) ---
    '{{chk_cont_steel_box}}'  => $chk_cont_steel_box,
    '{{chk_cont_gas_tank}}'   => $chk_cont_gas_tank,
    '{{chk_cont_fire_ext}}'   => $chk_cont_fire_ext,
    '{{chk_cont_steel_pipe}}' => $chk_cont_steel_pipe,
    '{{chk_cont_pvc_pipe}}'   => $chk_cont_pvc_pipe,
    '{{chk_cont_ac_tank}}'    => $chk_cont_ac_tank,
    '{{chk_cont_std_bomb}}'   => $chk_cont_std_bomb,
    '{{chk_cont_other}}'      => $chk_cont_other,
    '{{cont_other_text}}'     => htmlspecialchars($cont_other_text),

    // --- ระเบิด (Detonation) ---
    '{{chk_det_trap}}'      => $chk_det_trap,
    '{{det_trap_text}}'     => htmlspecialchars($det_trap_text),
    '{{chk_det_wire}}'      => $chk_det_wire,
    '{{det_wire_color}}'    => htmlspecialchars($det_wire_color),
    '{{det_wire_len}}'      => htmlspecialchars($det_wire_len),
    '{{chk_det_radio}}'     => $chk_det_radio,
    '{{det_radio_brand}}'   => htmlspecialchars($det_radio_brand),
    '{{det_radio_model}}'   => htmlspecialchars($det_radio_model),
    '{{det_radio_color}}'   => htmlspecialchars($det_radio_color),
    '{{det_radio_sn}}'      => htmlspecialchars($det_radio_sn),
    '{{chk_det_phone}}'     => $chk_det_phone,
    '{{det_phone_brand}}'   => htmlspecialchars($det_phone_brand),
    '{{det_phone_model}}'   => htmlspecialchars($det_phone_model),
    '{{det_phone_color}}'   => htmlspecialchars($det_phone_color),
    '{{det_phone_sn}}'      => htmlspecialchars($det_phone_sn),
    '{{chk_det_remote}}'    => $chk_det_remote,
    '{{det_remote_text}}'   => htmlspecialchars($det_remote_text),
    '{{chk_det_timer}}'     => $chk_det_timer,
    '{{det_timer_text}}'    => htmlspecialchars($det_timer_text),
    '{{chk_det_other}}'     => $chk_det_other,
    '{{det_other_text}}'    => htmlspecialchars($det_other_text),

    // --- ระเบิด (Fragments) ---
    '{{chk_frag_rebar}}'    => $chk_frag_rebar,
    '{{frag_rebar_size}}'   => htmlspecialchars($frag_rebar_size),
    '{{frag_rebar_len}}'    => htmlspecialchars($frag_rebar_len),
    '{{chk_frag_nail}}'     => $chk_frag_nail,
    '{{frag_nail_size}}'    => htmlspecialchars($frag_nail_size),
    '{{chk_frag_other}}'    => $chk_frag_other,
    '{{frag_other_text}}'   => htmlspecialchars($frag_other_text),

    // --- ระเบิด (Components) ---
    '{{chk_comp_booster}}'    => $chk_comp_booster,
    '{{comp_booster_text}}'   => htmlspecialchars($comp_booster_text),
    '{{chk_comp_detonator}}'  => $chk_comp_detonator,
    '{{comp_detonator_text}}' => htmlspecialchars($comp_detonator_text),
    '{{chk_comp_tape}}'       => $chk_comp_tape,
    '{{comp_tape_text}}'      => htmlspecialchars($comp_tape_text),
    '{{chk_comp_sim}}'        => $chk_comp_sim,
    '{{comp_sim_text}}'       => htmlspecialchars($comp_sim_text),
    '{{chk_comp_circuit}}'    => $chk_comp_circuit,
    '{{comp_circuit_text}}'   => htmlspecialchars($comp_circuit_text),
    '{{chk_comp_battery}}'    => $chk_comp_battery,
    '{{comp_battery_text}}'   => htmlspecialchars($comp_battery_text),
    '{{comp_battery_v}}'      => htmlspecialchars($comp_battery_v),
    '{{chk_comp_dtmf}}'       => $chk_comp_dtmf,
    '{{comp_dtmf_text}}'      => htmlspecialchars($comp_dtmf_text),
    '{{chk_comp_pcb}}'        => $chk_comp_pcb,
    '{{comp_pcb_text}}'       => htmlspecialchars($comp_pcb_text),
    '{{chk_comp_wire}}'       => $chk_comp_wire,
    '{{comp_wire_text}}'      => htmlspecialchars($comp_wire_text),
    '{{chk_comp_box}}'        => $chk_comp_box,
    '{{comp_box_text}}'       => htmlspecialchars($comp_box_text),
    '{{chk_comp_clock}}'      => $chk_comp_clock,
    '{{comp_clock_text}}'     => htmlspecialchars($comp_clock_text),
    '{{chk_comp_lever}}'      => $chk_comp_lever,
    '{{comp_lever_text}}'     => htmlspecialchars($comp_lever_text),
    '{{chk_comp_pin}}'        => $chk_comp_pin,
    '{{comp_pin_text}}'       => htmlspecialchars($comp_pin_text),
    '{{chk_comp_other}}'      => $chk_comp_other,
    '{{comp_other_text}}'     => htmlspecialchars($comp_other_text),

    // --- คราบเลือด ---
    '{{chk_blood_active}}' => $chk_blood_active,
    '{{blood_detail}}'     => htmlspecialchars($blood_detail),
    '{{chk_test_blood_main}}'    => $chk_test_blood_main,
    '{{chk_test_hema}}'    => $chk_test_hema,
    '{{chk_hema_pos}}'     => $chk_hema_pos,
    '{{chk_hema_neg}}'     => $chk_hema_neg,
    '{{chk_test_phenol}}'  => $chk_test_phenol,
    '{{chk_phenol_pos}}'   => $chk_phenol_pos,
    '{{chk_phenol_neg}}'   => $chk_phenol_neg,

    // --- วัตถุพยานที่ตรวจเก็บ (Collected) ---
    '{{chk_other_ev_active}}'     => $chk_other_ev_active,
    '{{other_ev_detail}}'         => htmlspecialchars($other_ev_detail),

    '{{chk_coll_dna}}'       => $chk_coll_dna,
    '{{coll_dna_detail}}'    => htmlspecialchars($coll_dna_detail),
    '{{chk_coll_finger}}'    => $chk_coll_finger,
    '{{coll_finger_detail}}' => htmlspecialchars($coll_finger_detail),
    '{{chk_coll_tool}}'      => $chk_coll_tool,
    '{{coll_tool_detail}}'   => htmlspecialchars($coll_tool_detail),
    '{{chk_coll_expl}}'      => $chk_coll_expl,
    '{{coll_expl_detail}}'   => htmlspecialchars($coll_expl_detail),
    '{{chk_coll_comp}}'      => $chk_coll_comp,
    '{{coll_comp_detail}}'   => htmlspecialchars($coll_comp_detail),
    '{{chk_coll_other}}'     => $chk_coll_other,
    '{{coll_other_detail}}'  => htmlspecialchars($coll_other_detail),

    // --- การตรวจสอบครั้งสุดท้าย (Final Check) ---
    '{{chk_final_1}}'     => $chk_final_1,
    '{{chk_final_2}}'     => $chk_final_2,
    '{{chk_final_3}}'     => $chk_final_3,

    // --- การส่งมอบสถานที่เกิดเหตุ (Handover) ---
    '{{receiver_signature_img}}' => $receiver_signature_img,
    '{{receiver_name}}'      => htmlspecialchars($receiver_name),
    '{{receiver_pos}}'       => htmlspecialchars($receiver_pos),

    '{{sender_signature_img}}'   => $sender_signature_img,
    '{{sender_name}}'        => htmlspecialchars($sender_name),
    '{{sender_pos}}'         => htmlspecialchars($sender_pos),

    '{{inspection_end_date}}' => htmlspecialchars($inspection_end_date),
    '{{inspection_end_time}}' => htmlspecialchars($inspection_end_time),

    // --- ตารางวัตถุพยานที่ตรวจพบ (Evidences Found + Meta) ---
    '{{evidence_found_rows}}' => $evidence_found_rows, // ตารางหน้า 5
    '{{ref_1}}' => htmlspecialchars($ref_1),
    '{{ref_2}}' => htmlspecialchars($ref_2),
    '{{ref_3}}' => htmlspecialchars($ref_3),
    '{{ref_4}}' => htmlspecialchars($ref_4),
    '{{ev_collector}}' => htmlspecialchars($ev_collector),
    '{{ev_collect_datetime}}' => htmlspecialchars($ev_collect_datetime),

    // --- ตารางรายการตรวจเก็บ (Measurements + Meta) ---
    '{{measurement_rows}}' => $measurement_rows, // ตารางหน้า 7
    '{{ec_inspect_date}}' => htmlspecialchars($ec_inspect_date),
    '{{ec_inspect_time}}' => htmlspecialchars($ec_inspect_time),
    '{{ec_recorder}}' => htmlspecialchars($ec_recorder),
    '{{ec_datetime}}' => htmlspecialchars($ec_datetime),

    // --- แผนผังสังเขป (Sketch Meta - Fieldset 9) ---
    '{{scene_sketch_img}}'       => $scene_sketch_img,
    '{{sketch_remark}}' => htmlspecialchars($sketch_remark),
    '{{sketch_recorder}}' => htmlspecialchars($sketch_recorder),
    '{{sketch_datetime}}' => htmlspecialchars($sketch_datetime),

    // --- แผนผังตำแหน่งบาดแผล (Body Diagram Meta - Fieldset 11) ---
    '{{body_diagram_img}}'       => $body_diagram_img,
    '{{bd_victim_name}}' => htmlspecialchars($bd_victim_name),
    '{{bd_victim_age}}' => htmlspecialchars($bd_victim_age),
    '{{bd_doctor}}' => htmlspecialchars($bd_doctor),
    // '{{bd_remark}}' => htmlspecialchars($bd_remark),

    // --- Photos ---
    '{{photo_images}}'           => $photo_images,
    '{{photo_id_start}}'         => htmlspecialchars($photo_id_start),
    '{{photo_id_end}}'           => htmlspecialchars($photo_id_end),
    '{{photo_amount}}'           => htmlspecialchars($photo_amount),
    '{{photo_recorder}}'         => htmlspecialchars($photo_recorder),
    '{{photo_datetime}}'         => htmlspecialchars($photo_datetime),
];

// แทนที่ค่าใน HTML
$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// เพิ่มหน้ารูปภาพเพิ่มเติม (รูปที่ 2+) ก่อน </body>
if (!empty($photo_extra_pages)) {
    $htmlContent = str_replace('</body>', $photo_extra_pages . "\n</body>", $htmlContent);
}

// คำนวณจำนวนหน้ารวม: 9 หน้าพื้นฐาน + หน้ารูปเพิ่ม (35 รูป/หน้า)
$photoPagesCount = !empty($validPhotos) ? (int)ceil(count($validPhotos) / $PHOTOS_PER_PAGE) : 1;
$extraPhotoPages = max(0, $photoPagesCount - 1);
$total_pages = 9 + $extraPhotoPages;
$htmlContent = str_replace('{{total_pages}}', $total_pages, $htmlContent);

// ==========================================
// 5. OUTPUT HTML (สำหรับทดสอบก่อน)
// ==========================================
header('Content-Type: text/html; charset=utf-8');
echo $htmlContent;
