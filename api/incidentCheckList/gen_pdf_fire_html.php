<?php
// gen_pdf_fire_html.php - Generate PDF for Fire Case from HTML Template

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../helpers/report_no.php';

// ==========================================
// 1. HELPER FUNCTIONS
// ==========================================

function thaiDate($dateStr)
{
    if (empty($dateStr)) return '';
    $timestamp = strtotime($dateStr);
    if ($timestamp === false) return $dateStr;
    $months = [null, 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $year = date('Y', $timestamp) + 543;
    return date('j', $timestamp) . ' ' . $months[date('n', $timestamp)] . ' ' . $year;
}

function thaiTime($timeStr)
{
    if (empty($timeStr)) return '';
    // รองรับทั้ง HH:MM และ datetime string
    if (strlen($timeStr) <= 5) return $timeStr; // already HH:MM
    $ts = strtotime($timeStr);
    return $ts !== false ? date('H:i', $ts) : $timeStr;
}

function renderCheckbox($condition)
{
    return $condition ? '✓' : '';
}

function getVal($arr, $key, $default = '')
{
    return isset($arr[$key]) && $arr[$key] !== null ? $arr[$key] : $default;
}

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
$userPositionMap = [];
try {
    $sqlUser = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname, t3.position_name
                FROM user_profile t1
                LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                LEFT JOIN user_position t3 ON t1.position_id = t3.position_id";
    $stmtUser = $pdo->query($sqlUser);

    while ($row = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        $userMap[$row['user_id']] = $row['fullname'];
        $userPositionMap[$row['user_id']] = $row['position_name'] ?? '';
    }
} catch (Exception $e) {
    // ปล่อย $userMap เป็นว่างไว้
}

// ==========================================
// 3. EXTRACT DATA
// ==========================================
$gen = $data['general_info'] ?? [];
$sceneInfo = $data['scene_info'] ?? [];
$dtInfo = $data['datetime_info'] ?? [];
$scene = $data['scene_characteristics'] ?? [];
$caseBeh = $data['case_behavior'] ?? [];
$damage = $data['damage'] ?? [];
$summary = $data['summary'] ?? [];
$opinion = $data['opinion'] ?? [];
$handover = $data['handover'] ?? [];
$inspectorList = $data['inspectors'] ?? [];
$photoRecords = $data['photo_records'] ?? [];

// ==========================================
// GENERAL INFO (Section 1)
// ==========================================
$case_doc_no = convertDocNoToThai(getVal($gen, 'case_doc_no') ?: getVal($gen, 'doc_no'));
$case_date = thaiDate(getVal($gen, 'report_date'));
$case_time = getVal($gen, 'report_time');

// เลขรายงาน + ปี พ.ศ.
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
// แปลงตัวย่อเป็นภาษาไทย
$thaiPrefixMap = ['LC-' => 'ท-', 'MD-' => 'ช-', 'BM-' => 'ร-', 'FR-' => 'พ-', 'TF-' => 'จร-', 'EV-' => 'ต-', 'CM-' => 'ต-', 'PS-' => 'ต-'];
foreach ($thaiPrefixMap as $en => $th) {
    if (strpos($report_no, $en) === 0) {
        $report_no = $th . substr($report_no, strlen($en));
        break;
    }
}

// ช่องทางการรับแจ้ง
$channel = getVal($gen, 'notify_method');
$notify_other_text = getVal($gen, 'notify_method_other_text');

if (is_array($channel)) {
    $chk_notify_phone = renderCheckbox(in_array('ทางโทรศัพท์', $channel));
    $chk_notify_radio = renderCheckbox(in_array('ทางวิทยุสื่อสาร', $channel));
    $chk_notify_letter = renderCheckbox(in_array('ทางหนังสือ', $channel));
    $chk_notify_other = renderCheckbox(in_array('อื่นๆ', $channel) || !empty(trim($notify_other_text)));
} else {
    $chk_notify_phone = renderCheckbox($channel == 'ทางโทรศัพท์');
    $chk_notify_radio = renderCheckbox($channel == 'ทางวิทยุสื่อสาร');
    $chk_notify_letter = renderCheckbox($channel == 'ทางหนังสือ');
    $chk_notify_other = renderCheckbox($channel == 'อื่นๆ' || !empty(trim($notify_other_text)));
}

$police_station = getVal($gen, 'source_station');
$document_no = getVal($gen, 'document_no');
$raw_document_date = getVal($gen, 'document_date');
$document_date = !empty($raw_document_date) ? (strtotime($raw_document_date) ? thaiDate($raw_document_date) : $raw_document_date) : '';

$investigator = $gen['investigator'] ?? [];
$investigator_name = getVal($investigator, 'name');
$investigator_phone = getVal($investigator, 'phone');

// ==========================================
// SCENE INFO (Section 2)
// ==========================================
$crime_location = getVal($sceneInfo, 'incident_location');

// ผู้ประสบเหตุ (ผู้เสียหาย / ผู้บาดเจ็บ / ผู้เสียชีวิต)
$persons = $sceneInfo['persons'] ?? [];
$typeLabels = ['ผู้เสียหาย', 'ผู้บาดเจ็บ', 'ผู้เสียชีวิต'];
$personsByType = [];
foreach ($typeLabels as $t) {
    $personsByType[$t] = [];
}
if (!empty($persons)) {
    foreach ($persons as $p) {
        $pType = $p['type'] ?? '';
        if (isset($personsByType[$pType])) {
            $personsByType[$pType][] = $p;
        }
    }
}

$victimRowsHtml = '';
$isFirstRow = true;
foreach ($typeLabels as $typeLabel) {
    $list = $personsByType[$typeLabel];
    if (!empty($list)) {
        foreach ($list as $idx => $p) {
            $name = htmlspecialchars($p['name'] ?? '');
            $age = htmlspecialchars($p['age'] ?? '');
            $remarkP = htmlspecialchars($p['remark'] ?? '');
            $marginStyle = $isFirstRow ? ' style="margin-top:2px;"' : '';
            $victimRowsHtml .= '<div class="fr"' . $marginStyle . '>'
                . '<label class="ck"><span class="cb">✓</span>' . $typeLabel . '</label>'
                . '<span class="fl">ชื่อ</span>'
                . '<span class="fd">' . $name . '</span>'
                . '<span class="fl">อายุ</span>'
                . '<span class="fd-s">' . $age . '</span>'
                . '<span class="fl">ปี</span>'
                . '</div>';
            $isFirstRow = false;
        }
    } else {
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

// ==========================================
// DATETIME INFO (Section 3-4)
// ==========================================
$victim_know_date = thaiDate(getVal($dtInfo, 'victim_known_date'));
$victim_know_time = getVal($dtInfo, 'victim_known_time');
$officer_know_date = thaiDate(getVal($dtInfo, 'investigator_known_date'));
$officer_know_time = getVal($dtInfo, 'investigator_known_time');
$known_detail = getVal($dtInfo, 'known_detail');

$inspect_date = thaiDate(getVal($dtInfo, 'inspection_date'));
$inspect_time = getVal($dtInfo, 'inspection_time');
$inspect_additional_date = thaiDate(getVal($dtInfo, 'inspection_additional_date'));
$inspect_additional_time = getVal($dtInfo, 'inspection_additional_time');

// ==========================================
// INSPECTORS (Section 5)
// ==========================================
$inspector_rows_html = '';
if (is_array($inspectorList)) {
    foreach ($inspectorList as $idx => $insp) {
        $no = $idx + 1;
        $inspId = $insp['id'] ?? '';
        $inspName = isset($userMap[$inspId]) ? $userMap[$inspId] : '';
        $inspector_rows_html .= '<div class="si"><span class="si-no">5.' . $no . '.</span><span class="si-dots">' . htmlspecialchars($inspName) . '</span></div>' . "\n";
    }
}

// ==========================================
// SCENE CHARACTERISTICS (Section 6)
// ==========================================
$exterior = $scene['exterior'] ?? [];
$exterior_detail = getVal($exterior, 'detail');
$floor_count = getVal($exterior, 'floor_count');

$fence = getVal($exterior, 'fence');
$chk_fence_yes = renderCheckbox($fence == 'has_fence' || $fence == 'มีรั้ว');
$chk_fence_no = renderCheckbox($fence == 'no_fence' || $fence == 'ไม่มีรั้ว');

$scene_front = getVal($exterior, 'front');
$scene_left = getVal($exterior, 'left');
$scene_right = getVal($exterior, 'right');
$scene_back = getVal($exterior, 'back');

$interior = $scene['interior'] ?? [];
$interior_detail = getVal($interior, 'detail');

$incidentArea = $scene['incident_area'] ?? [];
$incident_area_detail = getVal($incidentArea, 'detail');
$area_size = getVal($incidentArea, 'size');
$facing_direction = getVal($incidentArea, 'facing_direction');

// โครงสร้าง
$structure = $incidentArea['structure'] ?? [];
$structure_wall_front = getVal($structure, 'wall_front');
$structure_wall_left = getVal($structure, 'wall_left');
$structure_wall_right = getVal($structure, 'wall_right');
$structure_wall_back = getVal($structure, 'wall_back');
$structure_floor = getVal($structure, 'floor');
$structure_roof = getVal($structure, 'roof');
$structure_ceiling = getVal($structure, 'ceiling');

// สิ่งของ
$objects = $incidentArea['objects'] ?? [];
$objects_wall_front = getVal($objects, 'wall_front');
$objects_wall_left = getVal($objects, 'wall_left');
$objects_wall_right = getVal($objects, 'wall_right');
$objects_wall_back = getVal($objects, 'wall_back');
$objects_other = getVal($objects, 'other');

// ==========================================
// CASE BEHAVIOR (Section 7)
// ==========================================
$case_behavior = getVal($caseBeh, 'detail');

$insurance = getVal($caseBeh, 'insurance');
$chk_insurance_yes = renderCheckbox($insurance == 'has_insurance' || $insurance == 'มีประกัน');
$chk_insurance_no = renderCheckbox($insurance == 'no_insurance' || $insurance == 'ไม่มีประกัน');

$burn_time = getVal($caseBeh, 'burn_time');

$extinguish = getVal($caseBeh, 'extinguish');
$chk_extinguish_yes = renderCheckbox($extinguish == 'yes' || $extinguish == 'มี');
$chk_extinguish_no = renderCheckbox($extinguish == 'no' || $extinguish == 'ไม่มี');
$extinguish_detail = getVal($caseBeh, 'extinguish_detail');

$damage_condition = getVal($caseBeh, 'damage_condition');
$spread_detail = getVal($caseBeh, 'spread_detail');

// ==========================================
// DAMAGE (Section 7 cont)
// ==========================================
$dmgStructure = $damage['structure'] ?? [];
$damage_wall_front = getVal($dmgStructure, 'wall_front');
$damage_wall_left = getVal($dmgStructure, 'wall_left');
$damage_wall_right = getVal($dmgStructure, 'wall_right');
$damage_wall_back = getVal($dmgStructure, 'wall_back');
$damage_floor = getVal($dmgStructure, 'floor');
$damage_roof = getVal($dmgStructure, 'roof');
$damage_ceiling = getVal($dmgStructure, 'ceiling');

$dmgObjects = $damage['objects'] ?? [];
$damage_obj_front = getVal($dmgObjects, 'front');
$damage_obj_left = getVal($dmgObjects, 'left');
$damage_obj_right = getVal($dmgObjects, 'right');
$damage_obj_back = getVal($dmgObjects, 'back');
$damage_obj_floor = getVal($dmgObjects, 'floor');
$damage_obj_roof = getVal($dmgObjects, 'roof');
$damage_obj_ceiling = getVal($dmgObjects, 'ceiling');

$first_area = getVal($damage, 'first_area');
$switch_condition = getVal($damage, 'switch_condition');

$adjacent_damage = getVal($damage, 'adjacent_damage');
$chk_adjacent_found = renderCheckbox($adjacent_damage == 'found' || $adjacent_damage == 'พบ');
$chk_adjacent_not_found = renderCheckbox($adjacent_damage == 'not_found' || $adjacent_damage == 'ไม่พบ');
$adjacent_damage_detail = getVal($damage, 'adjacent_damage_detail');

$evidence_found = getVal($damage, 'evidence_found');

// ==========================================
// SUMMARY (Section 8)
// ==========================================
$origin_area = getVal($summary, 'origin_area');
$fuel_source = getVal($summary, 'fuel_source');
$heat_source = getVal($summary, 'heat_source');
$summary_other = getVal($summary, 'other');

// ==========================================
// OPINION (Section 9)
// ==========================================
$opinion_first_area = getVal($opinion, 'first_area');
$cause_type = getVal($opinion, 'cause_type');
$chk_cause_believed = renderCheckbox($cause_type == 'believed');
$chk_cause_unknown = renderCheckbox($cause_type == 'unknown');
$cause_believed_detail = getVal($opinion, 'cause_believed_detail');
$cause_unknown_detail = getVal($opinion, 'cause_unknown_detail');

// ==========================================
// REMARK
// ==========================================
$remark = $data['remark'] ?? '';

// ==========================================
// HANDOVER (Section 10)
// ==========================================
$inspection_end_date = thaiDate(getVal($handover, 'inspection_end_date'));
$inspection_end_time = getVal($handover, 'inspection_end_time');

// ผู้รับมอบ — อาจเป็น user_id หรือข้อความ
$recId = getVal($handover, 'receiver_name');
$receiver_name = isset($userMap[$recId]) ? $userMap[$recId] : $recId;
$receiver_position = getVal($handover, 'receiver_position');
if (empty($receiver_position) && isset($userPositionMap[$recId])) {
    $receiver_position = $userPositionMap[$recId];
}

// ผู้ส่งมอบ
$senId = getVal($handover, 'sender_name');
$sender_name = isset($userMap[$senId]) ? $userMap[$senId] : $senId;
$sender_position = getVal($handover, 'sender_position');
if (empty($sender_position) && isset($userPositionMap[$senId])) {
    $sender_position = $userPositionMap[$senId];
}

// ==========================================
// SIGNATURES (จาก BLOB ใน DB)
// ==========================================
$signatures = $data['signatures'] ?? [];

function blobToBase64FromDb($pdo, $fileId) {
    if (empty($fileId)) return '';
    $stmt = $pdo->prepare("SELECT file_name FROM incident_checklist_transaction_file WHERE id = ? LIMIT 1");
    $stmt->execute([$fileId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['file_name'])) return '';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($row['file_name']);
    if (!$mime || $mime === 'application/octet-stream') $mime = 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode($row['file_name']);
}

$sigKeys = ['receiver_signature', 'sender_signature', 'scene_sketch'];
foreach ($sigKeys as $sKey) {
    if (isset($signatures[$sKey]) && is_array($signatures[$sKey]) && !empty($signatures[$sKey]['file_id'])) {
        $signatures[$sKey]['base64'] = blobToBase64FromDb($pdo, $signatures[$sKey]['file_id']);
    }
}

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

$receiver_signature_img = renderSignatureImg($signatures['receiver_signature'] ?? '');
$sender_signature_img = renderSignatureImg($signatures['sender_signature'] ?? '');

// Scene Sketch
$scene_sketch_img_data = $signatures['scene_sketch'] ?? '';
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

// ==========================================
// SKETCH INFO (Page 6)
// ==========================================
$sketch_remark = $data['sketch_remark'] ?? '';
$sketch_recorder = $data['sketch_recorder'] ?? '';
$rawSketchDatetime = $data['sketch_datetime'] ?? '';
if (!empty($rawSketchDatetime) && strtotime($rawSketchDatetime)) {
    $sketch_datetime = thaiDate($rawSketchDatetime) . ' ' . thaiTime($rawSketchDatetime);
} else {
    $sketch_datetime = $rawSketchDatetime;
}

// ==========================================
// EVIDENCE META (Page 4)
// ==========================================
$evidenceMeta = $data['evidence_meta'] ?? [];
$reference_point_1 = $evidenceMeta['reference_point_1'] ?? '';
$reference_point_2 = $evidenceMeta['reference_point_2'] ?? '';
$reference_point_3 = $evidenceMeta['reference_point_3'] ?? '';
$reference_point_4 = $evidenceMeta['reference_point_4'] ?? '';
$ev_recorder = $sketch_recorder;
$ev_datetime = $sketch_datetime;

// ==========================================
// EVIDENCE TABLE (Page 4) - สร้าง HTML rows
// ==========================================
$evidences = $data['evidences'] ?? [];
$totalEvidenceRows = 20;
$evidence_table_rows = '';

foreach ($evidences as $i => $ev) {
    if ($i >= $totalEvidenceRows) break;
    $num = $i + 1;
    $evDetail = htmlspecialchars($ev['item'] ?? '');
    $evAzimuth = htmlspecialchars($ev['azimuth'] ?? '');
    $evRemark = htmlspecialchars($ev['remark'] ?? '');

    $evLv1 = !empty($ev['level_1']) ? '✓' : '';
    $evLv2 = !empty($ev['level_2']) ? '✓' : '';
    $evLv3 = !empty($ev['level_3']) ? '✓' : '';
    $evLv4 = !empty($ev['level_4']) ? '✓' : '';

    $evidence_table_rows .= '<tr>';
    $evidence_table_rows .= '<td style="border:1px solid #000; height:22px; text-align:center;">' . $num . '</td>';
    $evidence_table_rows .= '<td style="border:1px solid #000;">' . $evDetail . '</td>';
    $evidence_table_rows .= '<td style="border:1px solid #000; text-align:center;">' . $evLv1 . '</td>';
    $evidence_table_rows .= '<td style="border:1px solid #000; text-align:center;">' . $evLv2 . '</td>';
    $evidence_table_rows .= '<td style="border:1px solid #000; text-align:center;">' . $evLv3 . '</td>';
    $evidence_table_rows .= '<td style="border:1px solid #000; text-align:center;">' . $evLv4 . '</td>';
    $evidence_table_rows .= '<td style="border:1px solid #000;">' . $evAzimuth . '</td>';
    $evidence_table_rows .= '<td style="border:1px solid #000;">' . $evRemark . '</td>';
    $evidence_table_rows .= '</tr>';
}

$filledRows = min(count($evidences), $totalEvidenceRows);
for ($i = $filledRows; $i < $totalEvidenceRows; $i++) {
    $evidence_table_rows .= '<tr><td style="border:1px solid #000; height:22px;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td></tr>';
}

// ==========================================
// EVIDENCE COLLECTION TABLE (Page 5)
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

$measurementMeta = $data['measurement_meta'] ?? [];
$mmInspDate = $measurementMeta['inspection_date'] ?? '';
$ec_inspect_date = !empty($mmInspDate) ? thaiDate($mmInspDate) : $inspect_date;
$ec_inspect_time = !empty($mmInspDate) ? thaiTime($mmInspDate) : $inspect_time;
$ec_recorder = $sketch_recorder;
$ec_datetime = $sketch_datetime;

// ==========================================
// PHOTO RECORDS (Page 5)
// ==========================================
$photo_id_start = getVal($photoRecords, 'start');
$photo_id_end = getVal($photoRecords, 'end');
$photo_amount = getVal($photoRecords, 'amount');

$photo_inspect_date = getVal($photoRecords, 'inspect_date') ?: $inspect_date;
$photo_inspect_time = getVal($photoRecords, 'inspect_time') ?: $inspect_time;

// สร้าง Photo images HTML (grid 5 คอลัมน์ × 7 แถว = 35 รูป/หน้า)
$photos = $data['photos'] ?? [];
$photo_images = '';
$photo_extra_pages = '';
$PHOTOS_PER_PAGE = 35;

$validPhotos = [];
if (!empty($photos)) {
    foreach ($photos as $idx => $photo) {
        $pBase64 = '';
        $pName = '';
        if (is_array($photo)) {
            $pName = $photo['filename'] ?? $photo['name'] ?? $photo['file_name'] ?? '';
            // โหลดจาก BLOB ใน DB ผ่าน file_id
            if (!empty($photo['file_id'])) {
                $pBase64 = blobToBase64FromDb($pdo, $photo['file_id']);
            }
            // fallback: base64 เดิมใน JSON
            if (empty($pBase64) && !empty($photo['base64'])) {
                $pBase64 = $photo['base64'];
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
            <div class="title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับเพลิงไหม้</div>
            <div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ (ต่อ)</div>
        </div>
        <div class="header-qr">
            <div class="doc-box">
                <div class="doc-line">เลขรับที่/เลขรายงาน <span style="display:inline-block;min-width:60px;border-bottom:1px dotted #888;text-align:center;">' . htmlspecialchars($report_no) . '</span> / <span style="display:inline-block;min-width:40px;border-bottom:1px dotted #888;text-align:center;">' . htmlspecialchars($report_year) . '</span></div>
                <div class="doc-line">หน้าที่ ' . $pageNum . ' / {{total_pages}}</div>
            </div>
        </div>
        <div class="header-qr-code" id="qr_page' . $pageNum . '"></div>
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
                <span class="fd" style="width:250px;">' . htmlspecialchars($ec_recorder) . '</span>
            </div>
            <div class="fr" style="width:auto;">
                <span class="fl">วัน /เวลา</span>
                <span class="fd" style="width:250px;">' . htmlspecialchars($ec_datetime) . '</span>
            </div>
        </div>
        <div class="form-footer">
            <div class="form-footer-left">
                <strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ
            </div>
            <div class="form-footer-right">
                F-CS-12 แก้ไขครั้งที่ 1<br>
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
$htmlTemplate = file_get_contents(__DIR__ . '/form_fire_preview.html');

$replacements = [
    '{{report_no}}' => htmlspecialchars($report_no),
    '{{report_year}}' => htmlspecialchars($report_year),
    '{{qr_data}}' => htmlspecialchars($qrData),

    // Section 1
    '{{case_doc_no}}' => htmlspecialchars($case_doc_no),
    '{{case_date}}' => htmlspecialchars($case_date),
    '{{case_time}}' => htmlspecialchars($case_time),
    '{{chk_notify_phone}}' => $chk_notify_phone,
    '{{chk_notify_radio}}' => $chk_notify_radio,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_other}}' => $chk_notify_other,
    '{{notify_other_text}}' => htmlspecialchars($notify_other_text),
    '{{police_station}}' => htmlspecialchars($police_station),
    '{{document_no}}' => htmlspecialchars($document_no),
    '{{document_date}}' => htmlspecialchars($document_date),
    '{{investigator_name}}' => htmlspecialchars($investigator_name),
    '{{investigator_phone}}' => htmlspecialchars($investigator_phone),

    // Section 2
    '{{crime_location}}' => htmlspecialchars($crime_location),
    '{{victim_rows}}' => $victimRowsHtml,

    // Section 3
    '{{victim_know_date}}' => htmlspecialchars($victim_know_date),
    '{{victim_know_time}}' => htmlspecialchars($victim_know_time),
    '{{officer_know_date}}' => htmlspecialchars($officer_know_date),
    '{{officer_know_time}}' => htmlspecialchars($officer_know_time),
    '{{known_detail}}' => htmlspecialchars($known_detail),

    // Section 4
    '{{inspect_date}}' => htmlspecialchars($inspect_date),
    '{{inspect_time}}' => htmlspecialchars($inspect_time),
    '{{inspect_additional_date}}' => htmlspecialchars($inspect_additional_date),
    '{{inspect_additional_time}}' => htmlspecialchars($inspect_additional_time),

    // Section 5
    '{{inspector_rows}}' => $inspector_rows_html,

    // Section 6
    '{{exterior_detail}}' => htmlspecialchars($exterior_detail),
    '{{floor_count}}' => htmlspecialchars($floor_count),
    '{{chk_fence_yes}}' => $chk_fence_yes,
    '{{chk_fence_no}}' => $chk_fence_no,
    '{{scene_front}}' => htmlspecialchars($scene_front),
    '{{scene_left}}' => htmlspecialchars($scene_left),
    '{{scene_right}}' => htmlspecialchars($scene_right),
    '{{scene_back}}' => htmlspecialchars($scene_back),
    '{{interior_detail}}' => htmlspecialchars($interior_detail),
    '{{incident_area_detail}}' => htmlspecialchars($incident_area_detail),
    '{{area_size}}' => htmlspecialchars($area_size),
    '{{facing_direction}}' => htmlspecialchars($facing_direction),

    // Section 6 cont (structure)
    '{{structure_wall_front}}' => htmlspecialchars($structure_wall_front),
    '{{structure_wall_left}}' => htmlspecialchars($structure_wall_left),
    '{{structure_wall_right}}' => htmlspecialchars($structure_wall_right),
    '{{structure_wall_back}}' => htmlspecialchars($structure_wall_back),
    '{{structure_floor}}' => htmlspecialchars($structure_floor),
    '{{structure_roof}}' => htmlspecialchars($structure_roof),
    '{{structure_ceiling}}' => htmlspecialchars($structure_ceiling),

    // Section 6 cont (objects)
    '{{objects_wall_front}}' => htmlspecialchars($objects_wall_front),
    '{{objects_wall_left}}' => htmlspecialchars($objects_wall_left),
    '{{objects_wall_right}}' => htmlspecialchars($objects_wall_right),
    '{{objects_wall_back}}' => htmlspecialchars($objects_wall_back),
    '{{objects_other}}' => htmlspecialchars($objects_other),

    // Section 7
    '{{case_behavior}}' => htmlspecialchars($case_behavior),
    '{{chk_insurance_yes}}' => $chk_insurance_yes,
    '{{chk_insurance_no}}' => $chk_insurance_no,
    '{{burn_time}}' => htmlspecialchars($burn_time),
    '{{chk_extinguish_yes}}' => $chk_extinguish_yes,
    '{{chk_extinguish_no}}' => $chk_extinguish_no,
    '{{extinguish_detail}}' => htmlspecialchars($extinguish_detail),
    '{{damage_condition}}' => htmlspecialchars($damage_condition),
    '{{spread_detail}}' => htmlspecialchars($spread_detail),

    // Section 7 cont (damage)
    '{{damage_wall_front}}' => htmlspecialchars($damage_wall_front),
    '{{damage_wall_left}}' => htmlspecialchars($damage_wall_left),
    '{{damage_wall_right}}' => htmlspecialchars($damage_wall_right),
    '{{damage_wall_back}}' => htmlspecialchars($damage_wall_back),
    '{{damage_floor}}' => htmlspecialchars($damage_floor),
    '{{damage_roof}}' => htmlspecialchars($damage_roof),
    '{{damage_ceiling}}' => htmlspecialchars($damage_ceiling),
    '{{damage_obj_front}}' => htmlspecialchars($damage_obj_front),
    '{{damage_obj_left}}' => htmlspecialchars($damage_obj_left),
    '{{damage_obj_right}}' => htmlspecialchars($damage_obj_right),
    '{{damage_obj_back}}' => htmlspecialchars($damage_obj_back),
    '{{damage_obj_floor}}' => htmlspecialchars($damage_obj_floor),
    '{{damage_obj_roof}}' => htmlspecialchars($damage_obj_roof),
    '{{damage_obj_ceiling}}' => htmlspecialchars($damage_obj_ceiling),
    '{{first_area}}' => htmlspecialchars($first_area),
    '{{switch_condition}}' => htmlspecialchars($switch_condition),
    '{{chk_adjacent_found}}' => $chk_adjacent_found,
    '{{chk_adjacent_not_found}}' => $chk_adjacent_not_found,
    '{{adjacent_damage_detail}}' => htmlspecialchars($adjacent_damage_detail),
    '{{evidence_found}}' => htmlspecialchars($evidence_found),

    // Section 8
    '{{origin_area}}' => htmlspecialchars($origin_area),
    '{{fuel_source}}' => htmlspecialchars($fuel_source),
    '{{heat_source}}' => htmlspecialchars($heat_source),
    '{{summary_other}}' => htmlspecialchars($summary_other),

    // Section 9
    '{{opinion_first_area}}' => htmlspecialchars($opinion_first_area),
    '{{chk_cause_believed}}' => $chk_cause_believed,
    '{{chk_cause_unknown}}' => $chk_cause_unknown,
    '{{cause_believed_detail}}' => htmlspecialchars($cause_believed_detail),
    '{{cause_unknown_detail}}' => htmlspecialchars($cause_unknown_detail),

    // Remark
    '{{remark}}' => htmlspecialchars($remark),

    // Section 10 (Handover)
    '{{inspection_end_date}}' => htmlspecialchars($inspection_end_date),
    '{{inspection_end_time}}' => htmlspecialchars($inspection_end_time),
    '{{receiver_name}}' => htmlspecialchars($receiver_name),
    '{{receiver_position}}' => htmlspecialchars($receiver_position),
    '{{sender_name}}' => htmlspecialchars($sender_name),
    '{{sender_position}}' => htmlspecialchars($sender_position),
    '{{receiver_signature_img}}' => $receiver_signature_img,
    '{{sender_signature_img}}' => $sender_signature_img,

    // Sketch (Page 6)
    '{{scene_sketch_img}}' => $scene_sketch_img,
    '{{sketch_remark}}' => htmlspecialchars($sketch_remark),
    '{{sketch_recorder}}' => htmlspecialchars($sketch_recorder),
    '{{sketch_datetime}}' => htmlspecialchars($sketch_datetime),

    // Evidence Table (Page 4)
    '{{evidence_table_rows}}' => $evidence_table_rows,
    '{{reference_point_1}}' => htmlspecialchars($reference_point_1),
    '{{reference_point_2}}' => htmlspecialchars($reference_point_2),
    '{{reference_point_3}}' => htmlspecialchars($reference_point_3),
    '{{reference_point_4}}' => htmlspecialchars($reference_point_4),
    '{{ev_recorder}}' => htmlspecialchars($ev_recorder),
    '{{ev_datetime}}' => htmlspecialchars($ev_datetime),

    // Evidence Collection Table (Page 5)
    '{{evidence_collection_rows}}' => $evidence_collection_rows,
    '{{ec_inspect_date}}' => htmlspecialchars($ec_inspect_date),
    '{{ec_inspect_time}}' => htmlspecialchars($ec_inspect_time),
    '{{ec_recorder}}' => htmlspecialchars($ec_recorder),
    '{{ec_datetime}}' => htmlspecialchars($ec_datetime),

    // Photos (Page 7)
    '{{photo_inspect_date}}' => htmlspecialchars($photo_inspect_date),
    '{{photo_inspect_time}}' => htmlspecialchars($photo_inspect_time),
    '{{photo_id_start}}' => htmlspecialchars($photo_id_start),
    '{{photo_id_end}}' => htmlspecialchars($photo_id_end),
    '{{photo_amount}}' => htmlspecialchars($photo_amount),
    '{{photo_images}}' => $photo_images,
];

// แทนที่ค่าใน HTML
$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// เพิ่มหน้ารูปภาพเพิ่มเติม
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
