<?php
// gen_pdf_property_html.php - Generate PDF for Property Case from HTML Template

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
    if ($timestamp === false) return '';
    $months = [null, 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $year = date('Y', $timestamp) + 543;
    return date('j', $timestamp) . ' ' . $months[date('n', $timestamp)] . ' ' . $year;
}

// แปลงเวลา (14:30)
function thaiTime($datetime)
{
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    return date('H:i', $ts);
}

// ฟังก์ชัน Checkbox: ถ้า $condition เป็น true ให้แสดง ✓
function renderCheckbox($condition)
{
    return $condition ? '✓' : '';
}

// Helper: ดึงค่าจาก Array แบบปลอดภัย
function getVal($arr, $key, $default = '')
{
    if (!is_array($arr)) return $default;
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

// Helper: อ่านไฟล์จากดิสก์แปลงเป็น base64 data URI
function fileToBase64ForPdf($filePath) {
    if (!file_exists($filePath)) return '';
    $content = file_get_contents($filePath);
    if ($content === false) return '';
    $mime = mime_content_type($filePath);
    return 'data:' . $mime . ';base64,' . base64_encode($content);
}

// Helper: โหลด BLOB จาก incident_checklist_transaction_file โดย file_id → base64 data URI
function loadBlobAsBase64($pdo, $fileId) {
    if (empty($fileId)) return '';
    try {
        $stmt = $pdo->prepare("SELECT file_name FROM incident_checklist_transaction_file WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['file_name'])) return '';
        $blobData = $row['file_name'];
        // ตรวจสอบ mime type จาก magic bytes
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($blobData);
        if (!$mime || strpos($mime, 'image') === false) $mime = 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($blobData);
    } catch (Exception $e) {
        return '';
    }
}

// Helper: สร้าง <img> tag จาก base64
function renderSignatureImg($sigData)
{
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
// QR CODE: ดึงเลขรับแจ้ง (เหมือน evidence form)
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
if (empty($qrData)) {
    $qrData = getVal($gen, 'document_no');
}

// ==========================================
// PREPARE USER MAP (ID => Fullname)
// ==========================================
$userMap = [];
try {
    $sqlUser = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname
                FROM user_profile t1
                LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id";
    $stmtUser = $pdo->query($sqlUser);
    while ($u = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        $userMap[$u['user_id']] = $u['fullname'];
    }
} catch (Exception $e) {
    // กรณี Query ไม่ผ่าน ให้ปล่อย $userMap เป็นว่างไว้
}

// ==========================================
// 3. EXTRACT DATA
// ==========================================
$gen = $data['general_info'] ?? [];
$scene = $data['scene_characteristics'] ?? [];
$behavior = $data['case_behavior_info'] ?? [];
$handover = $data['handover'] ?? [];
$inspectorIds = $data['inspectors'] ?? [];

// ==========================================
// SECTION 1: การรับแจ้งเหตุ
// ==========================================

// เลขรายงาน
$case_doc_no = convertDocNoToThai(getVal($gen, 'doc_no'));
$case_date = thaiDate(getVal($gen, 'report_datetime'));
$case_time = thaiTime(getVal($gen, 'report_datetime'));

$raw_report_no = getIncidentReportNoTH($pdo, $incident_id, getVal($gen, 'report_no'));
$report_no = '';
$report_year = '';
if (!empty($raw_report_no) && strpos($raw_report_no, '/') !== false) {
    $parts = explode('/', $raw_report_no);
    $report_no = $parts[0];
    $report_year = $parts[1];
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

// ประเภทคดี
$caseType = getVal($gen, 'case_type');
$chk_case_theft = renderCheckbox($caseType == 'theft');
$chk_case_robbery = renderCheckbox($caseType == 'snatch');       // ชิงทรัพย์ = snatch
$chk_case_burglary = renderCheckbox($caseType == 'robbery');     // ปล้นทรัพย์ = robbery
$caseOtherTxt = getVal($gen, 'case_type_other');
$chk_case_other = renderCheckbox($caseType == 'other' || !empty($caseOtherTxt));
if (empty($caseOtherTxt) && $caseType == 'other') $caseOtherTxt = '';

// ช่องทางการรับแจ้ง
$channel = getVal($gen, 'report_channel');
$notify_other_text = getVal($gen, 'report_channel_other');

if (is_array($channel)) {
    $chk_notify_phone = renderCheckbox(in_array('phone', $channel) || in_array('ทางโทรศัพท์', $channel));
    $chk_notify_radio = renderCheckbox(in_array('radio', $channel) || in_array('ทางวิทยุสื่อสาร', $channel));
    $chk_notify_letter = renderCheckbox(in_array('document', $channel) || in_array('letter', $channel) || in_array('ทางหนังสือ', $channel));
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
$raw_record_date = getVal($gen, 'document_date');
$record_date = !empty($raw_record_date) ? (strtotime($raw_record_date) ? thaiDate($raw_record_date) : $raw_record_date) : '';

// ข้อมูลพนักงานสอบสวน
$investigator = isset($gen['investigator']) ? $gen['investigator'] : [];
$investigator_name = trim(getVal($investigator, 'firstname') . ' ' . getVal($investigator, 'lastname'));
$investigator_phone = getVal($investigator, 'phone');

// ==========================================
// SECTION 2: สถานที่เกิดเหตุ + ผู้เสียหาย
// ==========================================
$crime_location = getVal($gen, 'location_detail');

// ผู้เสียหาย (คดีทรัพย์ = single victim with type)
$victim = isset($gen['victim']) ? $gen['victim'] : [];
$vicType = getVal($victim, 'type');
$vicName = getVal($victim, 'name');
if (empty($vicName)) {
    $vicName = trim(getVal($victim, 'firstname') . ' ' . getVal($victim, 'lastname'));
}
$vicDetail = getVal($victim, 'detail');
if (empty($vicDetail)) {
    $vicDetail = getVal($victim, 'owner_detail');
    if (empty($vicDetail)) $vicDetail = getVal($victim, 'victim_detail');
    if (empty($vicDetail)) $vicDetail = getVal($victim, 'other_detail');
    if (empty($vicDetail)) $vicDetail = getVal($victim, 'type_other');
}
$vicAge = getVal($victim, 'age');

$chk_vic_own = renderCheckbox($vicType == 'owner');
$chk_vic_vic = renderCheckbox($vicType == 'victim');
$chk_vic_oth = renderCheckbox($vicType == 'other');

// ==========================================
// SECTION 3: วันเวลาที่ทราบเหตุ/เกิดเหตุ
// ==========================================
$victim_know_date = thaiDate(getVal($gen, 'incident_datetime'));
$victim_know_time = thaiTime(getVal($gen, 'incident_datetime'));
$officer_know_date = thaiDate(getVal($gen, 'investigator_known_datetime'));
$officer_know_time = thaiTime(getVal($gen, 'investigator_known_datetime'));

// ==========================================
// SECTION 4: วันเวลาที่ตรวจเหตุ
// ==========================================
$inspect_date = thaiDate(getVal($gen, 'inspection_datetime'));
$inspect_time = thaiTime(getVal($gen, 'inspection_datetime'));
$inspect_additional_date = thaiDate(getVal($gen, 'inspection_additional_datetime'));
$inspect_additional_time = thaiTime(getVal($gen, 'inspection_additional_datetime'));

// ==========================================
// SECTION 5: ผู้ตรวจสถานที่เกิดเหตุ
// ==========================================
$inspectorNames = [];
if (is_array($inspectorIds)) {
    foreach ($inspectorIds as $id) {
        if (isset($userMap[$id])) {
            $inspectorNames[] = $userMap[$id];
        }
    }
}

$inspector_rows_html = '';
foreach ($inspectorNames as $idx => $name) {
    $no = $idx + 1;
    $inspector_rows_html .= '<div class="si"><span class="si-no">5.' . $no . '.</span><span class="si-dots">' . htmlspecialchars($name) . '</span></div>' . "\n";
}
// เติมแถวว่างให้ครบ 7 แถว
for ($i = count($inspectorNames); $i < 7; $i++) {
    $no = $i + 1;
    $inspector_rows_html .= '<div class="si"><span class="si-no">5.' . $no . '.</span><span class="si-dots"></span></div>' . "\n";
}

// ==========================================
// SECTION 6: ลักษณะสถานที่เกิดเหตุ
// ==========================================

// การรักษาสถานที่เกิดเหตุ
$preservation = getVal($scene, 'preservation');
$presDetail = getVal($scene, 'preservation_detail');
$chk_pres_y = renderCheckbox($preservation == 'yes');
$chk_pres_n = renderCheckbox($preservation == 'no');
$pres_detail_y = ($preservation == 'yes') ? $presDetail : '';
$pres_detail_n = ($preservation == 'no') ? $presDetail : '';

// สิ่งปลูกสร้าง (Building Types)
$bldgs = $scene['building_types'] ?? [];
if (!is_array($bldgs)) $bldgs = [];

$detailsMap = [
    'concrete' => '', 'wood' => '', 'half' => '', 'row_building' => '',
    'concrete_bldg' => '', 'commercial' => '', 'townhouse' => '', 'other' => ''
];
$floors = [];
$selectedTypes = [];

foreach ($bldgs as $b) {
    $type = $b['type'] ?? '';
    $detail = $b['detail'] ?? '';
    $floor = $b['floor'] ?? '';
    $selectedTypes[] = $type;
    if (isset($detailsMap[$type])) {
        $detailsMap[$type] = $detail;
    }
    if (!empty($floor)) {
        $floors[] = $floor;
    }
}

$chk_bd_conc = renderCheckbox(in_array('concrete', $selectedTypes));
$chk_bd_wood = renderCheckbox(in_array('wood', $selectedTypes));
$chk_bd_half = renderCheckbox(in_array('half', $selectedTypes));
$chk_bd_row = renderCheckbox(in_array('row_building', $selectedTypes));
$chk_bd_concbl = renderCheckbox(in_array('concrete_bldg', $selectedTypes));
$chk_bd_com = renderCheckbox(in_array('commercial', $selectedTypes));
$chk_bd_town = renderCheckbox(in_array('townhouse', $selectedTypes));
$chk_bd_other = renderCheckbox(in_array('other', $selectedTypes));

$hasAnyBuilding = count($bldgs) > 0;
$chk_floor = renderCheckbox($hasAnyBuilding);
$uniqueFloors = array_unique($floors);
$bd_floor = implode(', ', $uniqueFloors);

// รั้ว
$fence = getVal($scene, 'fence');
$chk_fence_y = renderCheckbox($fence == 'has_fence' || $fence == 'yes');
$chk_fence_n = renderCheckbox($fence == 'no_fence' || $fence == 'no');

// สภาพบริเวณโดยรอบ
$surround = $scene['surroundings'] ?? [];
$sur_front = getVal($surround, 'front');
$sur_left = getVal($surround, 'left');
$sur_right = getVal($surround, 'right');
$sur_back = getVal($surround, 'back');

// ลักษณะภายใน
$interior_detail = getVal($scene, 'interior_detail');

// บริเวณที่เกิดเหตุ
$point_detail = getVal($scene, 'point_detail');

// ==========================================
// SECTION 7: ผลการตรวจสถานที่เกิดเหตุ
// ==========================================

// พฤติการณ์คดี
$behavior_text = getVal($behavior, 'behavior_text');

// ลักษณะร่องรอยทางเข้า
$entryPoints = $behavior['entry_points'] ?? [];
if (!is_array($entryPoints)) $entryPoints = [];

$chk_trace_no = renderCheckbox(in_array('no_trace', $entryPoints));
$chk_trace_unlock = renderCheckbox(in_array('unlocked', $entryPoints));
$chk_trace_found = renderCheckbox(in_array('found_trace', $entryPoints));
$chk_trace_pry = renderCheckbox(in_array('pry', $entryPoints));
$chk_trace_cut = renderCheckbox(in_array('cut', $entryPoints));
$chk_trace_drill = renderCheckbox(in_array('drill', $entryPoints));
$isOtherTrace = in_array('other_trace', $entryPoints);
$chk_trace_other = renderCheckbox($isOtherTrace);
$traceOtherDetail = getVal($behavior, 'entry_other_detail');
$trace_other_txt = $isOtherTrace ? $traceOtherDetail : '';

// ทางเข้าคนร้าย
$entryLocs = $behavior['entry_locations_detail'] ?? [];
if (!is_array($entryLocs)) $entryLocs = [];

$entryMap = ['door' => '', 'window' => '', 'ceiling' => '', 'roof' => '', 'other_location' => ''];
$selectedEntries = [];

foreach ($entryLocs as $key => $detail) {
    if (!array_key_exists($key, $entryMap)) continue;
    $selectedEntries[] = $key;
    $entryMap[$key] = is_string($detail) ? $detail : '';
}

$chk_ent_door = renderCheckbox(in_array('door', $selectedEntries));
$chk_ent_win = renderCheckbox(in_array('window', $selectedEntries));
$chk_ent_ceil = renderCheckbox(in_array('ceiling', $selectedEntries));
$chk_ent_roof = renderCheckbox(in_array('roof', $selectedEntries));
$chk_ent_other = renderCheckbox(in_array('other_location', $selectedEntries));

// เครื่องมือ
$tools = $behavior['burglary_tools'] ?? [];
if (!is_array($tools)) $tools = [];

$chk_tool_screw = renderCheckbox(in_array('screwdriver', $tools));
$chk_tool_crow = renderCheckbox(in_array('crowbar', $tools));
$chk_tool_cut = renderCheckbox(in_array('bolt_cutter', $tools));
$chk_tool_other = renderCheckbox(in_array('other', $tools));
$tool_other_txt = getVal($behavior, 'tool_other_detail');

// ร่องรอยกว้าง / คนร้ายจำนวน
$trace_width = getVal($behavior, 'trace_width');
$perp_count = getVal($behavior, 'perpetrator_count');

// อาวุธ
$wpStatus = getVal($behavior, 'weapon_status');
$chk_wp_unused = renderCheckbox($wpStatus == 'unused' || $wpStatus == 'none');
$chk_wp_used = renderCheckbox($wpStatus == 'used');

$wpTypes = $behavior['weapon_types'] ?? [];
if (!is_array($wpTypes)) $wpTypes = [];

$chk_wp_knife = renderCheckbox(in_array('knife', $wpTypes));
$chk_wp_gun = renderCheckbox(in_array('gun', $wpTypes));
$chk_wp_rope = renderCheckbox(in_array('rope', $wpTypes));
$chk_wp_other = renderCheckbox(in_array('other', $wpTypes));
$wp_other_txt = getVal($behavior, 'weapon_other_detail');

// การพันธนาการ
$restraints = $behavior['restraint_methods'] ?? [];
if (!is_array($restraints)) $restraints = [];

$chk_res_conf = renderCheckbox(in_array('confinement', $restraints));
$chk_res_bind = renderCheckbox(in_array('binding', $restraints));
$binding_mat = getVal($behavior, 'binding_material');

// สภาพผู้เสียหาย
$vicStatus = $behavior['victim_status'] ?? [];
if (!is_array($vicStatus)) $vicStatus = [];

$chk_vs_inj = renderCheckbox(in_array('injured', $vicStatus));
$chk_vs_dead = renderCheckbox(in_array('deceased', $vicStatus));
$inj_detail = getVal($behavior, 'injury_detail');

// ==========================================
// จุดที่ตรวจพบร่องรอย (Trace Points) - Individual Variables (tp1-tp6)
// ==========================================
$tracePoints = $data['trace_points'] ?? [];
$tpVars = [];

for ($tpIdx = 0; $tpIdx < 6; $tpIdx++) {
    $tp = isset($tracePoints[$tpIdx]) ? $tracePoints[$tpIdx] : [];
    $tpNo = $tpIdx + 1;

    $areaDetail = getVal($tp, 'area_detail');

    // ทางเข้า
    $tpEnt = $tp['entry'] ?? [];
    $isEnt = !empty($tpEnt['checked']);
    $entText = $isEnt ? getVal($tpEnt, 'detail') : '';

    // รอยงัด
    $tpPry = $tp['pry'] ?? [];
    $isPry = !empty($tpPry['checked']);
    $pryText = $isPry ? getVal($tpPry, 'detail') : '';

    // รื้อค้น
    $tpRum = $tp['rummage'] ?? [];
    $isRum = !empty($tpRum['checked']);
    $rumText = $isRum ? getVal($tpRum, 'detail') : '';

    $tpVars["tp{$tpNo}_chk_area"] = renderCheckbox(!empty($areaDetail));
    $tpVars["tp{$tpNo}_area"] = htmlspecialchars($areaDetail);
    $tpVars["tp{$tpNo}_chk_entry"] = renderCheckbox($isEnt);
    $tpVars["tp{$tpNo}_entry"] = htmlspecialchars($entText);
    $tpVars["tp{$tpNo}_chk_pry"] = renderCheckbox($isPry);
    $tpVars["tp{$tpNo}_pry"] = htmlspecialchars($pryText);
    $tpVars["tp{$tpNo}_chk_rum"] = renderCheckbox($isRum);
    $tpVars["tp{$tpNo}_rum"] = htmlspecialchars($rumText);
}

// ==========================================
// วัตถุพยานที่ตรวจพบ (Evidence Summary)
// ==========================================
$evidences = $data['evidences'] ?? [];

$evSummary = [
    'blood' => ['found' => false, 'details' => []],
    'fingerprint' => ['found' => false, 'details' => []],
    'dna' => ['found' => false, 'details' => []],
    'toolmark' => ['found' => false, 'details' => []],
    'other' => ['found' => false, 'details' => []]
];

// ตัวแปรสำหรับผลทดสอบเลือด
$bloodTestInfo = [
    'has_test' => false,
    'hema_test' => false, 'hema_change' => false, 'hema_no' => false,
    'phenol_test' => false, 'phenol_change' => false, 'phenol_no' => false
];

foreach ($evidences as $ev) {
    $type = $ev['type'] ?? '';
    $detail = $ev['detail'] ?? '';

    if ($type == 'blood') {
        $evSummary['blood']['found'] = true;
        if ($detail) $evSummary['blood']['details'][] = $detail;

        // ผลทดสอบเลือด
        $bTest = $ev['blood_test'] ?? [];
        if (!empty($bTest)) {
            $bloodTestInfo['has_test'] = true;
            if (!empty($bTest['hemastix_tested']) && $bTest['hemastix_tested']) {
                $bloodTestInfo['hema_test'] = true;
                $res = $bTest['hemastix_result'] ?? '';
                if ($res == 'change') $bloodTestInfo['hema_change'] = true;
                if ($res == 'no_change') $bloodTestInfo['hema_no'] = true;
            }
            if (!empty($bTest['phenol_tested']) && $bTest['phenol_tested']) {
                $bloodTestInfo['phenol_test'] = true;
                $res = $bTest['phenol_result'] ?? '';
                if ($res == 'change') $bloodTestInfo['phenol_change'] = true;
                if ($res == 'no_change') $bloodTestInfo['phenol_no'] = true;
            }
        }
    } elseif ($type == 'fingerprint') {
        $evSummary['fingerprint']['found'] = true;
        if ($detail) $evSummary['fingerprint']['details'][] = $detail;
    } elseif ($type == 'dna') {
        $evSummary['dna']['found'] = true;
        if ($detail) $evSummary['dna']['details'][] = $detail;
    } elseif ($type == 'toolmark') {
        $evSummary['toolmark']['found'] = true;
        if ($detail) $evSummary['toolmark']['details'][] = $detail;
    } else {
        $evSummary['other']['found'] = true;
        $prefix = ($type != 'other' && $type != '') ? "($type) " : "";
        if ($detail) $evSummary['other']['details'][] = $prefix . $detail;
    }
}

$chk_ev_blood = renderCheckbox($evSummary['blood']['found']);
$det_ev_blood = implode(', ', $evSummary['blood']['details']);

$chk_hema = renderCheckbox($bloodTestInfo['hema_test']);
$chk_hema_y = renderCheckbox($bloodTestInfo['hema_change']);
$chk_hema_n = renderCheckbox($bloodTestInfo['hema_no']);
$chk_phen = renderCheckbox($bloodTestInfo['phenol_test']);
$chk_phen_y = renderCheckbox($bloodTestInfo['phenol_change']);
$chk_phen_n = renderCheckbox($bloodTestInfo['phenol_no']);

$chk_ev_fing = renderCheckbox($evSummary['fingerprint']['found']);
$det_ev_fing = implode(', ', $evSummary['fingerprint']['details']);

$chk_ev_dna = renderCheckbox($evSummary['dna']['found']);
$det_ev_dna = implode(', ', $evSummary['dna']['details']);

$chk_ev_tool = renderCheckbox($evSummary['toolmark']['found']);
$det_ev_tool = implode(', ', $evSummary['toolmark']['details']);

$chk_ev_other = renderCheckbox($evSummary['other']['found']);
$det_ev_other = implode(', ', $evSummary['other']['details']);

// ทรัพย์สินที่ถูกโจรกรรม
$stolen_property = getVal($data, 'stolen_property');

// การตรวจสอบครั้งสุดท้าย
$finalCheck = $data['final_check'] ?? [];
if (is_string($finalCheck)) $finalCheck = [$finalCheck];

$chk_final_ver = renderCheckbox(
    in_array('final_verified', $finalCheck) || 
    in_array('การตรวจสอบครั้งสุดท้าย', $finalCheck) ||
    in_array('collected_all', $finalCheck) ||
    in_array('photos_taken', $finalCheck) ||
    in_array('photo_taken', $finalCheck)
);
$chk_final_coll = renderCheckbox(in_array('collected_all', $finalCheck) || in_array('ตรวจเก็บวัตถุพยานครบถ้วน', $finalCheck));
$chk_final_photo = renderCheckbox(in_array('photos_taken', $finalCheck) || in_array('photo_taken', $finalCheck));

// ==========================================
// SECTION 8: การส่งมอบสถานที่เกิดเหตุ
// ==========================================
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
// SIGNATURES (base64 / BLOB → <img> tags)
// ==========================================
// รองรับทั้ง:
// - path เก่า: signatures.receiver_sig / signatures.deliverer_sig (base64 inline)
// - path ใหม่ (BLOB): signatures.receiver_signature / signatures.sender_signature (file_id)
// - handover: handover.receiver_sig / handover.deliverer_sig (base64 inline)
$signatures = $data['signatures'] ?? [];
$handoverSigs = $data['handover'] ?? [];

// === Receiver Signature ===
$receiverSigBase64 = '';
// ลอง key ใหม่ (receiver_signature) จาก BLOB path
if (!empty($signatures['receiver_signature']['file_id'])) {
    $receiverSigBase64 = loadBlobAsBase64($pdo, $signatures['receiver_signature']['file_id']);
}
// ลอง key เก่า (receiver_sig) จาก base64 path
if (empty($receiverSigBase64)) {
    $receiverSigBase64 = renderSignatureImg($signatures['receiver_sig'] ?? $handoverSigs['receiver_sig'] ?? '');
} else {
    $receiverSigBase64 = '<img src="' . $receiverSigBase64 . '" style="display:block; max-width:120px; max-height:38px; object-fit:contain; margin:0 auto;" alt="ลายเซ็น">';
}
$receiver_signature_img = $receiverSigBase64;

// === Sender/Deliverer Signature ===
$senderSigBase64 = '';
// ลอง key ใหม่ (sender_signature) จาก BLOB path
if (!empty($signatures['sender_signature']['file_id'])) {
    $senderSigBase64 = loadBlobAsBase64($pdo, $signatures['sender_signature']['file_id']);
}
// ลอง key เก่า (deliverer_sig) จาก base64 path
if (empty($senderSigBase64)) {
    $senderSigBase64 = renderSignatureImg($signatures['deliverer_sig'] ?? $handoverSigs['deliverer_sig'] ?? '');
} else {
    $senderSigBase64 = '<img src="' . $senderSigBase64 . '" style="display:block; max-width:120px; max-height:38px; object-fit:contain; margin:0 auto;" alt="ลายเซ็น">';
}
$sender_signature_img = $senderSigBase64;

// === Scene Sketch ===
$scene_sketch_img_data = $signatures['scene_sketch'] ?? $data['attachments_meta']['sketch'] ?? '';

// สร้าง Scene Sketch <img>
$scene_sketch_img = '';
// ลอง BLOB path ก่อน
if (is_array($scene_sketch_img_data) && !empty($scene_sketch_img_data['file_id'])) {
    $sketchBase64 = loadBlobAsBase64($pdo, $scene_sketch_img_data['file_id']);
    if (!empty($sketchBase64)) {
        $scene_sketch_img = '<img src="' . $sketchBase64 . '" style="width:100%; height:100%; object-fit:contain; display:block;" alt="แผนผังสังเขป">';
    }
}
// ถ้าไม่ได้จาก BLOB → ลอง base64 inline
if (empty($scene_sketch_img) && !empty($scene_sketch_img_data)) {
    $sketchBase64 = '';
    if (is_array($scene_sketch_img_data) && !empty($scene_sketch_img_data['base64'])) {
        $sketchBase64 = $scene_sketch_img_data['base64'];
    } elseif (is_string($scene_sketch_img_data)) {
        $sketchBase64 = $scene_sketch_img_data;
    }
    if (!empty($sketchBase64) && strpos($sketchBase64, 'data:image') !== false) {
        $scene_sketch_img = '<img src="' . $sketchBase64 . '" style="width:100%; height:100%; object-fit:contain; display:block;" alt="แผนผังสังเขป">';
    }
}

// ==========================================
// RECORDER INFO (ผู้จดบันทึก — ใช้ร่วมกันทุกหน้า)
// ==========================================
$recorderInfo = $data['recorder_info'] ?? [];
$recorderName = $recorderInfo['name'] ?? '';
$rawRecorderDatetime = $recorderInfo['datetime'] ?? '';
if (!empty($rawRecorderDatetime) && strtotime($rawRecorderDatetime)) {
    $recorderDatetime = thaiDate($rawRecorderDatetime) . ' ' . thaiTime($rawRecorderDatetime);
} else {
    $recorderDatetime = $rawRecorderDatetime;
}

// ==========================================
// SKETCH INFO (Page 4)
// ==========================================
$sketchInfo = $data['sketch_info'] ?? [];
$sketch_remark = $sketchInfo['remark'] ?? '';
if (empty($sketch_remark)) {
    $sketch_remark = $data['attachments_meta']['sketch_remark'] ?? '';
}
$sketch_recorder = $sketchInfo['recorder'] ?? '';
if (empty($sketch_recorder)) {
    $sketch_recorder = $recorderName;
}
$rawSketchDatetime = $sketchInfo['datetime'] ?? '';
if (!empty($rawSketchDatetime) && strtotime($rawSketchDatetime)) {
    $sketch_datetime = thaiDate($rawSketchDatetime) . ' ' . thaiTime($rawSketchDatetime);
} elseif (!empty($rawSketchDatetime)) {
    $sketch_datetime = $rawSketchDatetime;
} else {
    $sketch_datetime = $recorderDatetime;
}

// ==========================================
// EVIDENCE META (Page 5 - reference points, recorder)
// ==========================================
$evidenceMeta = $data['evidence_meta'] ?? [];

// Auto-generate reference points from evidences if evidence_meta is empty
if (empty($evidenceMeta) || (empty($evidenceMeta['reference_point_1']) && empty($evidenceMeta['reference_point_2']))) {
    $allEvs = $data['evidences'] ?? [];
    $refDescriptions = ['', '', '', ''];
    foreach ($allEvs as $ev) {
        $refPts = $ev['ref_points'] ?? [];
        foreach ($refPts as $rIdx => $rp) {
            if ($rIdx < 4 && !empty($rp['desc']) && empty($refDescriptions[$rIdx])) {
                $refDescriptions[$rIdx] = $rp['desc'];
            }
        }
    }
    for ($ri = 0; $ri < 4; $ri++) {
        $rpKey = 'reference_point_' . ($ri + 1);
        if (empty($evidenceMeta[$rpKey]) && !empty($refDescriptions[$ri])) {
            $evidenceMeta[$rpKey] = $refDescriptions[$ri];
        }
    }
}

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
// EVIDENCE TABLE (Page 5) - สร้าง HTML rows
// ==========================================
// กรองเอาเฉพาะ evidence ที่มีข้อมูลตารางจริงๆ มาแสดง
// ตัดออก: entry จาก static blood section (ทั้งข้อมูลใหม่ที่มี flag และข้อมูลเก่าที่ไม่มี)
$evidencesForTable = array_values(array_filter($data['evidences'] ?? [], function($ev) {
    if (!empty($ev['_summary_only'])) return false;
    // ข้อมูลเก่า: ถ้าไม่มี no, area_found, label_no, quantity = เป็น summary-only
    $hasNo = !empty($ev['no']);
    $hasArea = !empty($ev['area_found']);
    $hasLabel = !empty($ev['label_no']);
    $hasQty = isset($ev['quantity']) && (is_array($ev['quantity']) ? !empty($ev['quantity']['val']) : !empty($ev['quantity']));
    return ($hasNo || $hasArea || $hasLabel || $hasQty);
}));
$totalEvidenceRows = 20;
$evidence_table_rows = '';

foreach ($evidencesForTable as $i => $ev) {
    if ($i >= $totalEvidenceRows) break;
    $num = $i + 1;
    $evDetail = htmlspecialchars($ev['detail'] ?? $ev['item'] ?? '');
    $evLabelNo = htmlspecialchars($ev['label_no'] ?? '');
    $evAzimuth = htmlspecialchars($ev['azimuth'] ?? '');
    $evRemark = htmlspecialchars($ev['remark'] ?? '');

    // รองรับทั้ง flat keys (ref1_dist) และ array format (ref_points[0].dist)
    $refPts = $ev['ref_points'] ?? [];
    $evLv1 = !empty($ev['ref1_dist']) ? htmlspecialchars($ev['ref1_dist']) : (!empty($refPts[0]['dist']) ? htmlspecialchars($refPts[0]['dist']) : (!empty($ev['level_1']) ? '✓' : ''));
    $evLv2 = !empty($ev['ref2_dist']) ? htmlspecialchars($ev['ref2_dist']) : (!empty($refPts[1]['dist']) ? htmlspecialchars($refPts[1]['dist']) : (!empty($ev['level_2']) ? '✓' : ''));
    $evLv3 = !empty($ev['ref3_dist']) ? htmlspecialchars($ev['ref3_dist']) : (!empty($refPts[2]['dist']) ? htmlspecialchars($refPts[2]['dist']) : (!empty($ev['level_3']) ? '✓' : ''));
    $evLv4 = !empty($ev['ref4_dist']) ? htmlspecialchars($ev['ref4_dist']) : (!empty($refPts[3]['dist']) ? htmlspecialchars($refPts[3]['dist']) : (!empty($ev['level_4']) ? '✓' : ''));

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

$filledRows = min(count($evidencesForTable), $totalEvidenceRows);
for ($i = $filledRows; $i < $totalEvidenceRows; $i++) {
    $evidence_table_rows .= '<tr><td style="border:1px solid #000; height:22px;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td></tr>';
}

// ==========================================
// EVIDENCE COLLECTION TABLE (Page 6)
// ==========================================
// ใช้ measurements ถ้ามี, ถ้าไม่มีให้ auto-generate จาก evidences
$measurementsData = $data['measurements'] ?? [];
if (empty($measurementsData)) {
    // กรองเอาเฉพาะ evidence ที่มีข้อมูลตารางจริงๆ (ไม่รวม static blood summary)
    $evsForMeasure = array_values(array_filter($data['evidences'] ?? [], function($ev) {
        if (!empty($ev['_summary_only'])) return false;
        $hasNo = !empty($ev['no']);
        $hasArea = !empty($ev['area_found']);
        $hasLabel = !empty($ev['label_no']);
        $hasQty = isset($ev['quantity']) && (is_array($ev['quantity']) ? !empty($ev['quantity']['val']) : !empty($ev['quantity']));
        return ($hasNo || $hasArea || $hasLabel || $hasQty);
    }));
    foreach ($evsForMeasure as $ev) {
        $qtyVal = '';
        if (isset($ev['quantity'])) {
            if (is_array($ev['quantity'])) {
                $qtyVal = ($ev['quantity']['val'] ?? '') . ' ' . ($ev['quantity']['unit'] ?? '');
            } else {
                $qtyVal = $ev['quantity'];
            }
        }
        $pkg = $ev['packaging'] ?? [];
        $act = $ev['action'] ?? [];
        $measurementsData[] = [
            'item' => $ev['detail'] ?? '',
            'quantity' => trim($qtyVal),
            'area' => $ev['area_found'] ?? '',
            'label_number' => $ev['label_no'] ?? '',
            'package_plastic' => !empty($pkg['plastic']),
            'package_paper' => !empty($pkg['paper']),
            'package_other' => !empty($pkg['other']),
            'package_other_text' => $pkg['other_text'] ?? '',
            'action_return' => !empty($act['return']),
            'action_other' => !empty($act['other']),
            'action_other_text' => $act['other_text'] ?? '',
            'remark' => $ev['remark'] ?? '',
        ];
    }
}
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

// Page 6 date/time/recorder
$measurementMeta = $data['measurement_meta'] ?? [];
$mmInspDate = $measurementMeta['inspection_date'] ?? '';
$ec_inspect_date = !empty($mmInspDate) ? thaiDate($mmInspDate) : $inspect_date;
$ec_inspect_time = !empty($mmInspDate) ? thaiTime($mmInspDate) : $inspect_time;
$ec_recorder = !empty($measurementMeta['recorder']) ? $measurementMeta['recorder'] : $sketch_recorder;
$rawEcDatetime = !empty($measurementMeta['datetime']) ? $measurementMeta['datetime'] : $sketch_datetime;
if (!empty($rawEcDatetime) && strtotime($rawEcDatetime)) {
    $ec_datetime = thaiDate($rawEcDatetime) . ' ' . thaiTime($rawEcDatetime);
} else {
    $ec_datetime = $rawEcDatetime;
}

// ==========================================
// PHOTO RECORDS (Page 7)
// ==========================================
$photoRecords = $data['photo_records'] ?? $data['attachments_meta'] ?? [];
$photo_id_start = $photoRecords['photo_id_start'] ?? $photoRecords['photo_start'] ?? '';
$photo_id_end = $photoRecords['photo_id_end'] ?? $photoRecords['photo_end'] ?? '';
$photo_amount = $photoRecords['photo_amount'] ?? '';

$photo_inspect_date = $inspect_date;
$photo_inspect_time = $inspect_time;

// ผู้จดบันทึก / วัน เวลา สำหรับหน้า 7 (photo page)
$photo_recorder = $sketch_recorder; // fallback chain: sketch_recorder ← recorder_info.name
$photo_datetime = $sketch_datetime;  // fallback chain: sketch_datetime ← recorder_info.datetime

// สร้าง Photo images HTML (grid 5 คอลัมน์ × 7 แถว = 35 รูป/หน้า)
$photos = $data['photos'] ?? [];
$photo_images = '';
$photo_extra_pages = '';
$PHOTOS_PER_PAGE = 35;

$validPhotos = [];
$photosDir = __DIR__ . '/../../uploads/checklist_photos/';
if (!empty($photos)) {
    foreach ($photos as $photo) {
        $pBase64 = '';
        $pName = '';
        if (is_array($photo)) {
            $pName = $photo['filename'] ?? $photo['name'] ?? $photo['file_name'] ?? '';
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
            }
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

    // === หน้าแรก (Page 7): grid ===
    $photo_images = '<div class="photo-grid">';
    for ($i = 0; $i <= $firstPageEnd; $i++) {
        $safeName = htmlspecialchars($validPhotos[$i]['name']);
        $photo_images .= '<div class="photo-cell-wrapper">';
        $photo_images .= '<div class="photo-cell"><img src="' . $validPhotos[$i]['base64'] . '" alt="' . $safeName . '"></div>';
        $photo_images .= '<div class="photo-fname" title="' . $safeName . '">' . $safeName . '</div>';
        $photo_images .= '</div>';
    }
    $photo_images .= '</div>';

    // === หน้าถัดไป (Page 8, 9, ...) ===
    for ($p = 2; $p <= $pagesNeeded; $p++) {
        $startI = ($p - 1) * $PHOTOS_PER_PAGE;
        $endI = min($p * $PHOTOS_PER_PAGE, $totalPhotos) - 1;
        $pageStartName = htmlspecialchars($validPhotos[$startI]['name']);
        $pageEndName = htmlspecialchars($validPhotos[$endI]['name']);
        $pageNum = 7 + ($p - 1);

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
            <div class="title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์</div>
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
    <div class="form-footer" style="margin-top:auto;">
        <div class="form-footer-left">
            <strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ
        </div>
        <div class="form-footer-right">
            F-CS-08 แก้ไขครั้งที่ 2<br>
            แก้ไขวันที่ 2 ก.ย. 63<br>
            เริ่มใช้ 1 ต.ค. 63
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
$htmlTemplate = file_get_contents(__DIR__ . '/form_property_preview.html');

$replacements = [
    // Header (ทุกหน้า)
    '{{report_no}}' => htmlspecialchars($report_no),
    '{{report_year}}' => htmlspecialchars($report_year),

    // QR Code data (เหมือน evidence form — ใช้เลขรับแจ้ง)
    '{{qr_data}}' => htmlspecialchars($qrData),

    // Section 1: การรับแจ้งเหตุ
    '{{case_date}}' => htmlspecialchars($case_date),
    '{{case_time}}' => htmlspecialchars($case_time),
    '{{chk_case_theft}}' => $chk_case_theft,
    '{{chk_case_robbery}}' => $chk_case_robbery,
    '{{chk_case_burglary}}' => $chk_case_burglary,
    '{{chk_case_other}}' => $chk_case_other,
    '{{case_other_txt}}' => htmlspecialchars($caseOtherTxt),
    '{{chk_notify_phone}}' => $chk_notify_phone,
    '{{chk_notify_radio}}' => $chk_notify_radio,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_other}}' => $chk_notify_other,
    '{{notify_other_txt}}' => htmlspecialchars($notify_other_text),
    '{{police_station}}' => htmlspecialchars($police_station),
    '{{location_at}}' => htmlspecialchars($location_at),
    '{{record_date}}' => htmlspecialchars($record_date),
    '{{investigator_name}}' => htmlspecialchars($investigator_name),
    '{{investigator_phone}}' => htmlspecialchars($investigator_phone),

    // Section 2: สถานที่เกิดเหตุ + ผู้เสียหาย
    '{{crime_location}}' => htmlspecialchars($crime_location),
    '{{chk_vic_own}}' => $chk_vic_own,
    '{{chk_vic_vic}}' => $chk_vic_vic,
    '{{chk_vic_oth}}' => $chk_vic_oth,
    '{{vic_name}}' => htmlspecialchars($vicName),
    '{{vic_detail}}' => htmlspecialchars($vicDetail),
    '{{vic_age}}' => htmlspecialchars($vicAge),

    // Section 3: วันเวลา
    '{{victim_know_date}}' => htmlspecialchars($victim_know_date),
    '{{victim_know_time}}' => htmlspecialchars($victim_know_time),
    '{{officer_know_date}}' => htmlspecialchars($officer_know_date),
    '{{officer_know_time}}' => htmlspecialchars($officer_know_time),

    // Section 4: วันเวลาที่ตรวจ
    '{{inspect_date}}' => htmlspecialchars($inspect_date),
    '{{inspect_time}}' => htmlspecialchars($inspect_time),
    '{{inspect_additional_date}}' => htmlspecialchars($inspect_additional_date),
    '{{inspect_additional_time}}' => htmlspecialchars($inspect_additional_time),

    // Section 5: ผู้ตรวจ
    '{{inspector_rows}}' => $inspector_rows_html,

    // Section 6: ลักษณะสถานที่
    '{{chk_pres_y}}' => $chk_pres_y,
    '{{chk_pres_n}}' => $chk_pres_n,
    '{{pres_detail_y}}' => htmlspecialchars($pres_detail_y),

    '{{chk_bd_conc}}' => $chk_bd_conc,
    '{{chk_bd_wood}}' => $chk_bd_wood,
    '{{chk_bd_half}}' => $chk_bd_half,
    '{{chk_bd_row}}' => $chk_bd_row,
    '{{chk_bd_concbl}}' => $chk_bd_concbl,
    '{{chk_bd_com}}' => $chk_bd_com,
    '{{chk_bd_town}}' => $chk_bd_town,
    '{{chk_bd_other}}' => $chk_bd_other,

    '{{det_conc}}' => htmlspecialchars($detailsMap['concrete']),
    '{{det_wood}}' => htmlspecialchars($detailsMap['wood']),
    '{{det_half}}' => htmlspecialchars($detailsMap['half']),
    '{{det_row}}' => htmlspecialchars($detailsMap['row_building']),
    '{{det_concbl}}' => htmlspecialchars($detailsMap['concrete_bldg']),
    '{{det_com}}' => htmlspecialchars($detailsMap['commercial']),
    '{{det_town}}' => htmlspecialchars($detailsMap['townhouse']),
    '{{det_other}}' => htmlspecialchars($detailsMap['other']),

    '{{bd_floor}}' => htmlspecialchars($bd_floor),
    '{{chk_fence_y}}' => $chk_fence_y,
    '{{chk_fence_n}}' => $chk_fence_n,

    '{{sur_front}}' => htmlspecialchars($sur_front),
    '{{sur_left}}' => htmlspecialchars($sur_left),
    '{{sur_right}}' => htmlspecialchars($sur_right),
    '{{sur_back}}' => htmlspecialchars($sur_back),

    '{{interior_detail}}' => htmlspecialchars($interior_detail),
    '{{point_detail}}' => htmlspecialchars($point_detail),

    // Section 7: ผลการตรวจ
    '{{behavior_text}}' => htmlspecialchars($behavior_text),

    '{{chk_trace_no}}' => $chk_trace_no,
    '{{chk_trace_unlock}}' => $chk_trace_unlock,
    '{{chk_trace_found}}' => $chk_trace_found,
    '{{chk_trace_pry}}' => $chk_trace_pry,
    '{{chk_trace_cut}}' => $chk_trace_cut,
    '{{chk_trace_drill}}' => $chk_trace_drill,
    '{{chk_trace_other}}' => $chk_trace_other,
    '{{trace_other_txt}}' => htmlspecialchars($trace_other_txt),

    '{{chk_ent_door}}' => $chk_ent_door,
    '{{chk_ent_win}}' => $chk_ent_win,
    '{{chk_ent_ceil}}' => $chk_ent_ceil,
    '{{chk_ent_roof}}' => $chk_ent_roof,
    '{{chk_ent_other}}' => $chk_ent_other,
    '{{det_ent_door}}' => htmlspecialchars($entryMap['door']),
    '{{det_ent_win}}' => htmlspecialchars($entryMap['window']),
    '{{det_ent_ceil}}' => htmlspecialchars($entryMap['ceiling']),
    '{{det_ent_roof}}' => htmlspecialchars($entryMap['roof']),
    '{{det_ent_other}}' => htmlspecialchars($entryMap['other_location']),

    '{{chk_tool_screw}}' => $chk_tool_screw,
    '{{chk_tool_crow}}' => $chk_tool_crow,
    '{{chk_tool_cut}}' => $chk_tool_cut,
    '{{chk_tool_other}}' => $chk_tool_other,
    '{{tool_other_txt}}' => htmlspecialchars($tool_other_txt),

    '{{trace_width}}' => htmlspecialchars($trace_width),
    '{{perp_count}}' => htmlspecialchars($perp_count),

    '{{chk_wp_unused}}' => $chk_wp_unused,
    '{{chk_wp_used}}' => $chk_wp_used,
    '{{chk_wp_knife}}' => $chk_wp_knife,
    '{{chk_wp_gun}}' => $chk_wp_gun,
    '{{chk_wp_rope}}' => $chk_wp_rope,
    '{{chk_wp_other}}' => $chk_wp_other,
    '{{wp_other_txt}}' => htmlspecialchars($wp_other_txt),

    '{{chk_res_conf}}' => $chk_res_conf,
    '{{chk_res_bind}}' => $chk_res_bind,
    '{{binding_mat}}' => htmlspecialchars($binding_mat),

    '{{chk_vs_inj}}' => $chk_vs_inj,
    '{{chk_vs_dead}}' => $chk_vs_dead,
    '{{inj_detail}}' => htmlspecialchars($inj_detail),

    // Evidence Summary
    '{{chk_ev_blood}}' => $chk_ev_blood,
    '{{det_ev_blood}}' => htmlspecialchars($det_ev_blood),
    '{{chk_hema}}' => $chk_hema,
    '{{chk_hema_y}}' => $chk_hema_y,
    '{{chk_hema_n}}' => $chk_hema_n,
    '{{chk_phen}}' => $chk_phen,
    '{{chk_phen_y}}' => $chk_phen_y,
    '{{chk_phen_n}}' => $chk_phen_n,
    '{{chk_ev_fing}}' => $chk_ev_fing,
    '{{det_ev_fing}}' => htmlspecialchars($det_ev_fing),
    '{{chk_ev_dna}}' => $chk_ev_dna,
    '{{det_ev_dna}}' => htmlspecialchars($det_ev_dna),
    '{{chk_ev_tool}}' => $chk_ev_tool,
    '{{det_ev_tool}}' => htmlspecialchars($det_ev_tool),
    '{{chk_ev_other}}' => $chk_ev_other,
    '{{det_ev_other}}' => htmlspecialchars($det_ev_other),

    '{{stolen_property}}' => htmlspecialchars($stolen_property),

    '{{chk_final_ver}}' => $chk_final_ver,
    '{{chk_final_coll}}' => $chk_final_coll,
    '{{chk_final_photo}}' => $chk_final_photo,

    // Section 8: การส่งมอบ
    '{{inspection_end_date}}' => htmlspecialchars($inspection_end_date),
    '{{inspection_end_time}}' => htmlspecialchars($inspection_end_time),
    '{{receiver_signature_img}}' => $receiver_signature_img,
    '{{receiver_name}}' => htmlspecialchars($receiver_name),
    '{{receiver_position}}' => htmlspecialchars($receiver_position),
    '{{sender_signature_img}}' => $sender_signature_img,
    '{{sender_name}}' => htmlspecialchars($sender_name),
    '{{sender_position}}' => htmlspecialchars($sender_position),

    // Scene Sketch (Page 4)
    '{{scene_sketch_img}}' => $scene_sketch_img,
    '{{sketch_remark}}' => htmlspecialchars($sketch_remark),
    '{{sketch_recorder}}' => htmlspecialchars($sketch_recorder),
    '{{sketch_datetime}}' => htmlspecialchars($sketch_datetime),

    // Evidence Table (Page 5)
    '{{evidence_table_rows}}' => $evidence_table_rows,
    '{{reference_point_1}}' => htmlspecialchars($reference_point_1),
    '{{reference_point_2}}' => htmlspecialchars($reference_point_2),
    '{{reference_point_3}}' => htmlspecialchars($reference_point_3),
    '{{reference_point_4}}' => htmlspecialchars($reference_point_4),
    '{{ev_recorder}}' => htmlspecialchars($ev_recorder),
    '{{ev_datetime}}' => htmlspecialchars($ev_datetime),

    // Evidence Collection Table (Page 6)
    '{{evidence_collection_rows}}' => $evidence_collection_rows,
    '{{ec_inspect_date}}' => htmlspecialchars($ec_inspect_date),
    '{{ec_inspect_time}}' => htmlspecialchars($ec_inspect_time),
    '{{ec_recorder}}' => htmlspecialchars($ec_recorder),
    '{{ec_datetime}}' => htmlspecialchars($ec_datetime),

    // Photo Records (Page 7)
    '{{photo_inspect_date}}' => htmlspecialchars($photo_inspect_date),
    '{{photo_inspect_time}}' => htmlspecialchars($photo_inspect_time),
    '{{photo_id_start}}' => htmlspecialchars($photo_id_start),
    '{{photo_id_end}}' => htmlspecialchars($photo_id_end),
    '{{photo_amount}}' => htmlspecialchars($photo_amount),
    '{{photo_images}}' => $photo_images,

    // Photo Recorder (Page 7)
    '{{photo_recorder}}' => htmlspecialchars($photo_recorder),
    '{{photo_datetime}}' => htmlspecialchars($photo_datetime),
];

// Merge trace point variables (tp1–tp6)
foreach ($tpVars as $k => $v) {
    $replacements['{{' . $k . '}}'] = $v;
}

// แทนที่ค่าใน HTML
$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// เพิ่มหน้ารูปภาพเพิ่มเติม (รูปที่ 2+) ก่อน </body>
if (!empty($photo_extra_pages)) {
    $htmlContent = str_replace('</body>', $photo_extra_pages . "\n</body>", $htmlContent);
}

// คำนวณจำนวนหน้ารวม: 7 หน้าพื้นฐาน + หน้ารูปเพิ่ม (35 รูป/หน้า)
$photoPagesCount = !empty($validPhotos) ? (int)ceil(count($validPhotos) / $PHOTOS_PER_PAGE) : 1;
$extraPhotoPages = max(0, $photoPagesCount - 1);
$total_pages = 7 + $extraPhotoPages;
$htmlContent = str_replace('{{total_pages}}', $total_pages, $htmlContent);

// ==========================================
// 5. OUTPUT HTML
// ==========================================
header('Content-Type: text/html; charset=utf-8');
echo $htmlContent;
