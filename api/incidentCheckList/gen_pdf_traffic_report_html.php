<?php
/**
 * gen_pdf_traffic_report_html.php
 * Generate PDF รายงานการตรวจพิสูจน์คดีจราจร
 *
 * รับ parameter: incident_id (GET)
 * ดึงข้อมูลจาก: incident_checklist_transaction.incident_report_data (JSON key: traffic)
 * ถ้ายังไม่มี report data จะ fallback ใช้ incident_checklist_data แล้ว map
 *
 * ใช้ template: form_traffic_report_preview.html + str_replace pattern
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../helpers/report_no.php';

// ==========================================
// 1. HELPER FUNCTIONS
// ==========================================

function thaiDateFull($datetime) {
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return date('j',$ts).' '.$months[(int)date('n',$ts)].' '.(date('Y',$ts)+543);
}

function thaiTime($datetime) {
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    return ($ts !== false) ? date('H:i', $ts) : '';
}

function parseDateParts($datetime) {
    if (empty($datetime)) return ['day' => '', 'month' => '', 'year' => ''];
    $ts = strtotime($datetime);
    if ($ts === false) return ['day' => '', 'month' => '', 'year' => ''];
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return ['day' => date('j', $ts), 'month' => $months[(int)date('n', $ts)] ?? '', 'year' => date('Y', $ts) + 543];
}

function h($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

function strLenUtf8($text) {
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function strSubUtf8($text, $start, $length = null) {
    if (function_exists('mb_substr')) {
        return $length === null
            ? mb_substr($text, $start, null, 'UTF-8')
            : mb_substr($text, $start, $length, 'UTF-8');
    }
    return $length === null ? substr($text, $start) : substr($text, $start, $length);
}

function strRposUtf8($text, $needle) {
    return function_exists('mb_strrpos') ? mb_strrpos($text, $needle, 0, 'UTF-8') : strrpos($text, $needle);
}

function wrapTextForPdfLine($line, $maxChars = 100) {
    $line = trim((string)$line);
    if ($line === '') return [''];

    $chunks = [];
    $remaining = $line;

    while (strLenUtf8($remaining) > $maxChars) {
        $candidate = strSubUtf8($remaining, 0, $maxChars);
        $splitAt = strRposUtf8($candidate, ' ');

        if ($splitAt !== false && $splitAt > (int)($maxChars * 0.45)) {
            $chunks[] = trim(strSubUtf8($remaining, 0, $splitAt));
            $remaining = trim(strSubUtf8($remaining, $splitAt + 1));
        } else {
            $chunks[] = trim($candidate);
            $remaining = trim(strSubUtf8($remaining, $maxChars));
        }
    }

    if ($remaining !== '') {
        $chunks[] = $remaining;
    }

    return $chunks ?: [''];
}

function hBlock($val) {
    $v = trim($val ?? '');
    $rawLines = $v === '' ? [''] : preg_split('/\r\n|\r|\n/', $v);
    $lines = [];

    foreach ($rawLines as $line) {
        foreach (wrapTextForPdfLine($line, 100) as $wrapped) {
            $lines[] = $wrapped;
        }
    }

    $lineCount = max(count($lines), 2);
    $html = '';

    for ($i = 0; $i < $lineCount; $i++) {
        $line = $lines[$i] ?? '';
        $safe = trim($line) === '' ? '&nbsp;' : htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
        $html .= '<div style="width:100%; min-height:22px; line-height:1.9; border-bottom:1px dotted #888;">' . $safe . '</div>';
    }

    return $html;
}

function getVal($arr, $key, $default = '') {
    if (!is_array($arr)) return $default;
    return isset($arr[$key]) && $arr[$key] !== null ? $arr[$key] : $default;
}

function renderCheckbox($condition) {
    return $condition ? '✓' : '';
}

// ==========================================
// 2. FETCH DATA FROM DATABASE
// ==========================================
$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;
if ($incident_id <= 0) die("Error: กรุณาระบุ incident_id");

try {
    $stmt = $pdo->prepare("SELECT incident_report_data, incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) die("Error: ไม่พบข้อมูลสำหรับ incident_id: " . $incident_id);

    $reportData = !empty($row['incident_report_data']) ? json_decode($row['incident_report_data'], true) : null;
    $checklistData = !empty($row['incident_checklist_data']) ? json_decode($row['incident_checklist_data'], true) : null;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// USER MAP (ID => Fullname + Position)
// ==========================================
$userMap = [];
try {
    $sqlUser = "SELECT t1.user_id,
                       CONCAT(IFNULL(t2.rank_name,''),' ',t1.first_name,' ',t1.last_name) AS fullname,
                       IFNULL(t3.position_name,'') AS position_name
                FROM user_profile t1
                LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                LEFT JOIN user_position t3 ON t1.position_id = t3.position_id";
    $stmtUser = $pdo->query($sqlUser);
    while ($u = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        $userMap[$u['user_id']] = $u;
    }
} catch (Exception $e) { /* ignore */ }

// ==========================================
// AGENCY INFO
// ==========================================
$agencyType = '';
$agencyName = '';
$agencyCenterName = '';
$agencyProvinceName = '';
try {
    $stmtAgency = $pdo->prepare("SELECT userReviewType, userReviewTypeVal FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
    $stmtAgency->execute([$incident_id]);
    $agencyRow = $stmtAgency->fetch(PDO::FETCH_ASSOC);
    if ($agencyRow) {
        $provinceMap = ['95' => 'ยะลา', '94' => 'ปัตตานี', '96' => 'นราธิวาส'];
        $rt = $agencyRow['userReviewType'] ?? '';
        $rv = $agencyRow['userReviewTypeVal'] ?? '';
        if ($rt === 'nvt') {
            $agencyType = 'กสก.พฐก.';
            $agencyName = 'นวท.(สบ ' . $rv . ') กสก.พฐก.';
        } elseif ($rt === 'spt') {
            $agencyType = 'กลก.ศพฐ.';
            $agencyName = 'ศพฐ ' . $rv;
            $agencyCenterName = $agencyName;
        } elseif ($rt === 'ptjv') {
            $agencyType = 'พฐ.จว.';
            $agencyName = 'พฐ.จว.' . ($provinceMap[strval($rv)] ?? $rv);
            $agencyProvinceName = $agencyName;
        }
    }
} catch (Exception $e) { /* ignore */ }

// ==========================================
// 3. DETERMINE DATA SOURCE
// ==========================================
$data = [];

if ($reportData && isset($reportData['traffic']) && is_array($reportData['traffic'])) {
    $data = $reportData['traffic'];
} elseif ($checklistData && is_array($checklistData)) {
    // Fallback: map from checklistData
    $gi = $checklistData['general_info'] ?? [];
    $si = $checklistData['scene_info'] ?? [];
    $ip = $checklistData['inspection_purpose'] ?? [];
    $fr = $checklistData['forensic_results'] ?? [];
    $cr = $checklistData['comparison_results'] ?? [];
    $ho = $checklistData['handover'] ?? [];

    // Notify method mapping
    $notifyMethods = [];
    $reportChannel = $gi['report_channel'] ?? ($gi['notify_method'] ?? []);
    if (is_array($reportChannel)) {
        $methodMap = ['ทางโทรศัพท์'=>'โทรศัพท์','ทางวิทยุสื่อสาร'=>'วิทยุสื่อสาร','ทางหนังสือ'=>'หนังสือ'];
        foreach ($reportChannel as $m) {
            $notifyMethods[] = $methodMap[trim($m)] ?? trim($m);
        }
    }

    // Vehicles
    $vehicles = $si['vehicles_at_scene'] ?? ($si['vehicles'] ?? []);
    $vTypes = []; $vBrands = []; $vColors = []; $vPlates = []; $vQtys = [];
    if (is_array($vehicles)) {
        foreach ($vehicles as $v) {
            $vTypes[] = $v['detail'] ?? ($v['vehicle_detail'] ?? ($v['type'] ?? ''));
            $vBrands[] = $v['brand'] ?? ($v['vehicle_brand'] ?? '');
            $vColors[] = $v['color'] ?? ($v['vehicle_color'] ?? '');
            $vPlates[] = $v['plate_no'] ?? ($v['vehicle_plate_no'] ?? ($v['plate'] ?? ''));
            $vQtys[] = '1';
        }
    }

    // Inspectors
    $inspectors = $checklistData['inspectors'] ?? [];
    $inspNames = []; $inspPositions = [];
    if (is_array($inspectors)) {
        foreach ($inspectors as $insp) {
            $uid = is_array($insp) ? ($insp['id'] ?? '') : $insp;
            if (!empty($uid) && isset($userMap[$uid])) {
                $inspNames[] = $userMap[$uid]['fullname'] ?? '';
                $inspPositions[] = $userMap[$uid]['position_name'] ?? '';
            }
        }
    }

    // Analysis vehicles
    $analysisVehicles = $fr['vehicle_analysis'] ?? [];
    $aDescs = []; $aFront1 = []; $aFront2 = []; $aLeft = []; $aRear = []; $aRight = []; $aOther = [];
    if (is_array($analysisVehicles)) {
        foreach ($analysisVehicles as $av) {
            $aDescs[] = $av['condition'] ?? '';
            $traces = $av['traces'] ?? [];
            $frontTraces = $traces['front'] ?? [];
            $aFront1[] = trim($frontTraces[0]['detail'] ?? '');
            $aFront2[] = trim($frontTraces[1]['detail'] ?? '');
            $joinDetails = function($rows) {
                $out = [];
                foreach ((array)$rows as $r) {
                    $d = trim($r['detail'] ?? '');
                    if ($d !== '') $out[] = $d;
                }
                return implode("\n", $out);
            };
            $aLeft[] = $joinDetails($traces['left'] ?? []);
            $aRear[] = $joinDetails($traces['back'] ?? []);
            $aRight[] = $joinDetails($traces['right'] ?? []);
            $aOther[] = trim($av['mod_detail'] ?? '');
        }
    }

    // Signer
    $signName = '';
    $signerId = $ho['sender']['name_id'] ?? ($ho['deliverer_id'] ?? '');
    if (!empty($signerId) && isset($userMap[$signerId])) {
        $signName = $userMap[$signerId]['fullname'] ?? '';
    }

    $data = [
        'rt_receive_date' => $gi['case_date'] ?? ($gi['report_date'] ?? ''),
        'rt_receive_time' => $gi['case_time'] ?? ($gi['report_time'] ?? ''),
        'rt_daily_ref' => $gi['document_no'] ?? '',
        'rt_notify_method[]' => $notifyMethods,
        'rt_from_station' => $gi['source_station'] ?? ($gi['police_station'] ?? ''),
        'rt_officer_name' => $gi['investigator']['name'] ?? ($gi['investigator_name'] ?? ''),
        'rt_scene_location' => $si['crime_location'] ?? ($gi['location_detail'] ?? ''),
        'rt_purpose_qty_1' => $ip['qty_1'] ?? '',
        'rt_purpose_qty_2' => $ip['qty_2'] ?? '',
        'rt_purpose_other_text' => $ip['other_text'] ?? '',
        'rt_vehicle_type[]' => $vTypes,
        'rt_vehicle_brand[]' => $vBrands,
        'rt_vehicle_color[]' => $vColors,
        'rt_vehicle_plate[]' => $vPlates,
        'rt_vehicle_plate_qty[]' => $vQtys,
        'rt_inspector_name[]' => $inspNames,
        'rt_inspector_position[]' => $inspPositions,
        'rt_case_behavior' => $fr['case_behavior'] ?? '',
        'rt_analyze_date' => $fr['inspection_date'] ?? ($gi['inspect_date'] ?? ''),
        'rt_analyze_time' => $fr['inspection_time'] ?? ($gi['inspect_time'] ?? ''),
        'rt_analysis_vehicle_desc[]' => $aDescs,
        'rt_damage_front_detail_1[]' => $aFront1,
        'rt_damage_front_detail_2[]' => $aFront2,
        'rt_damage_left_detail[]' => $aLeft,
        'rt_damage_rear_detail[]' => $aRear,
        'rt_damage_right_detail[]' => $aRight,
        'rt_damage_other_detail[]' => $aOther,
        'rt_opinion' => $checklistData['opinion'] ?? '',
        'rt_sign_name' => $signName,
        'rt_sign_position' => $ho['sender']['position'] ?? ($ho['deliverer_pos'] ?? ''),
        'rt_sign_date' => $ho['inspection_end_date'] ?? '',
        'rt_agency_name' => $agencyName,
        'rt_agency_center_name' => $agencyCenterName,
        'rt_agency_province_name' => $agencyProvinceName,
    ];
}

// Helper to get field
function f($key, $default = '') {
    global $data;
    return $data[$key] ?? $default;
}
function fArr($key) {
    global $data;
    $v = $data[$key] ?? [];
    return is_array($v) ? $v : [];
}

// ==========================================
// Report No & Year
// ==========================================
$gen = $checklistData['general_info'] ?? [];
$rawReportNo = getVal($gen, 'report_no');

// Fallback: ถ้า checklist ไม่มี report_no → ดึงจาก rn_ReceiveNoti
if (empty($rawReportNo)) {
    try {
        $stmtRn = $pdo->prepare("SELECT receiveNotiReportNo FROM rn_ReceiveNoti WHERE id = ? AND statusDelete = 0 LIMIT 1");
        $stmtRn->execute([$incident_id]);
        $rnRow = $stmtRn->fetch(PDO::FETCH_ASSOC);
        if ($rnRow && !empty($rnRow['receiveNotiReportNo'])) {
            $rawReportNo = $rnRow['receiveNotiReportNo'];
        }
    } catch (Exception $e) {
        // ignore
    }
}

$report_no = '';
$report_year_short = '';
if (!empty($rawReportNo) && strpos($rawReportNo, '/') !== false) {
    $parts = explode('/', $rawReportNo, 2);
    $report_no = $parts[0];
    $report_year_short = substr(trim($parts[1] ?? ''), -2);
} else {
    $report_no = $rawReportNo;
}
// Override from saved report data if present
if (!empty(f('rt_report_ref'))) $report_no = f('rt_report_ref');
if (!empty(f('rt_report_year'))) $report_year_short = f('rt_report_year');
// แปลงเป็นภาษาไทยเสมอ (รองรับทั้งเลขรายงานและเลขเอกสารเต็ม) — idempotent
$report_no = smartThaiReportOrDoc($report_no);

// ==========================================
// 4. BUILD DYNAMIC HTML SECTIONS
// ==========================================

// --- Notify method checkboxes ---
$notifyMethods = fArr('rt_notify_method[]');
if (!is_array($notifyMethods)) $notifyMethods = [];

// ★ ข้อ 7: ให้ "วิธีการรับแจ้ง" ออกมาเป็นข้อความไทย ไม่ใช่ช่องติ๊กเปล่า
require_once __DIR__ . '/notify_method_helper.php';
$notify_method_text = notifyMethodToText($notifyMethods, (string)f('rt_notify_other_text'));

$chk_notify_letter = renderCheckbox(in_array('หนังสือ', $notifyMethods) || in_array('ทางหนังสือ', $notifyMethods));
$chk_notify_phone = renderCheckbox(in_array('โทรศัพท์', $notifyMethods) || in_array('ทางโทรศัพท์', $notifyMethods));
$chk_notify_radio = renderCheckbox(in_array('วิทยุสื่อสาร', $notifyMethods) || in_array('ทางวิทยุสื่อสาร', $notifyMethods));
$chk_notify_other = renderCheckbox(in_array('อื่นๆ', $notifyMethods));
$notify_other_text = f('rt_notify_other_text');

// --- Vehicle rows ---
$vTypes = fArr('rt_vehicle_type[]');
$vBrands = fArr('rt_vehicle_brand[]');
$vColors = fArr('rt_vehicle_color[]');
$vPlates = fArr('rt_vehicle_plate[]');
$vQtys = fArr('rt_vehicle_plate_qty[]');
$vCount = max(count($vTypes), 1);
$vehicle_rows_html = '';
for ($i = 0; $i < $vCount; $i++) {
    $no = $i + 1;
    $vehicle_rows_html .= '<div class="fr i1" style="margin-top:4px;">';
    $vehicle_rows_html .= '<span class="fl-b">1.' . $no . '</span>';
    $vehicle_rows_html .= '<span class="fl">&nbsp;รถของกลางรายการที่ ' . $no . ' เป็นรถ</span><span class="fd">' . h($vTypes[$i] ?? '') . '</span>';
    $vehicle_rows_html .= '<span class="fl">ยี่ห้อ</span><span class="fd-m">' . h($vBrands[$i] ?? '') . '</span>';
    $vehicle_rows_html .= '</div>';
    $vehicle_rows_html .= '<div class="fr i2">';
    $vehicle_rows_html .= '<span class="fl">สี</span><span class="fd-s">' . h($vColors[$i] ?? '') . '</span>';
    $vehicle_rows_html .= '<span class="fl">แผ่นป้ายทะเบียนหมายเลข</span><span class="fd-l">' . h($vPlates[$i] ?? '') . '</span>';
    $vehicle_rows_html .= '<span class="fl">จำนวน</span><span class="fd-s">' . h($vQtys[$i] ?? '1') . '</span><span class="fl">คัน</span>';
    $vehicle_rows_html .= '</div>';
}

// --- Inspector rows ---
$inspNames = fArr('rt_inspector_name[]');
$inspPositions = fArr('rt_inspector_position[]');
if (!is_array($inspNames)) $inspNames = [];
$inspector_rows_html = '';
$inspCount = max(count($inspNames), 1);
for ($i = 0; $i < $inspCount; $i++) {
    $no = $i + 1;
    $name = $inspNames[$i] ?? '';
    $pos = $inspPositions[$i] ?? '';
    $posText = !empty($pos) ? '  ตำแหน่ง ' . h($pos) : '';
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">3.' . $no . '.</span><span class="fd" style="margin-left:6px;">' . h($name) . $posText . '</span></div>' . "\n";
}

// --- Analysis vehicle rows ---
$aDescs = fArr('rt_analysis_vehicle_desc[]');
$aFront1 = fArr('rt_damage_front_detail_1[]');
$aFrontSign1 = fArr('rt_damage_front_sign_1[]');
$aFrontPhoto1 = fArr('rt_damage_front_photo_1[]');
$aFront2 = fArr('rt_damage_front_detail_2[]');
$aFrontSign2 = fArr('rt_damage_front_sign_2[]');
$aFrontPhoto2 = fArr('rt_damage_front_photo_2[]');
$aLeft = fArr('rt_damage_left_detail[]');
$aRear = fArr('rt_damage_rear_detail[]');
$aRight = fArr('rt_damage_right_detail[]');
$aOther = fArr('rt_damage_other_detail[]');
$aCount = max(count($aDescs), 1);
$analysis_vehicle_rows_html = '';
for ($i = 0; $i < $aCount; $i++) {
    $no = $i + 1;
    $sec = "4.{$no}";
    $analysis_vehicle_rows_html .= '<div style="margin-top:6px;">';
    $analysis_vehicle_rows_html .= '<div class="fl-b i1">' . $sec . ' รถของกลางรายการที่ ' . $no . '</div>';
    $analysis_vehicle_rows_html .= '<div class="fr i1"><span class="fl">เป็นรถ</span><span class="fd">' . h($aDescs[$i] ?? '') . '</span></div>';

    $analysis_vehicle_rows_html .= '<div class="fl-b i1" style="margin-top:6px;">' . $sec . '.1 ตรวจพบสภาพและความเสียหายด้านหน้ารถ ดังนี้</div>';
    $analysis_vehicle_rows_html .= '<div class="fr i2"><span class="fl">' . $sec . '.1.1</span><span class="fd">' . h($aFront1[$i] ?? '') . '</span></div>';
    $sign1 = trim($aFrontSign1[$i] ?? ''); $photo1 = trim($aFrontPhoto1[$i] ?? '');
    if ($sign1 !== '' || $photo1 !== '') {
        $analysis_vehicle_rows_html .= '<div class="fr i3"><span class="fl">(ป้ายหมายเลข</span><span class="fd-s">' . h($sign1) . '</span><span class="fl">ในภาพถ่ายที่</span><span class="fd-s">' . h($photo1) . '</span><span class="fl">)</span></div>';
    }
    $analysis_vehicle_rows_html .= '<div class="fr i2"><span class="fl">' . $sec . '.1.2</span><span class="fd">' . h($aFront2[$i] ?? '') . '</span></div>';
    $sign2 = trim($aFrontSign2[$i] ?? ''); $photo2 = trim($aFrontPhoto2[$i] ?? '');
    if ($sign2 !== '' || $photo2 !== '') {
        $analysis_vehicle_rows_html .= '<div class="fr i3"><span class="fl">(ป้ายหมายเลข</span><span class="fd-s">' . h($sign2) . '</span><span class="fl">ในภาพถ่ายที่</span><span class="fd-s">' . h($photo2) . '</span><span class="fl">)</span></div>';
    }

    $analysis_vehicle_rows_html .= '<div class="fl-b i1" style="margin-top:6px;">' . $sec . '.2 ตรวจพบสภาพและความเสียหายด้านซ้ายรถ ดังนี้</div>';
    $analysis_vehicle_rows_html .= '<div class="fd-block-i2">' . hBlock($aLeft[$i] ?? '') . '</div>';

    $analysis_vehicle_rows_html .= '<div class="fl-b i1" style="margin-top:6px;">' . $sec . '.3 ตรวจพบสภาพและความเสียหายด้านท้ายรถ ดังนี้</div>';
    $analysis_vehicle_rows_html .= '<div class="fd-block-i2">' . hBlock($aRear[$i] ?? '') . '</div>';

    $analysis_vehicle_rows_html .= '<div class="fl-b i1" style="margin-top:6px;">' . $sec . '.4 ตรวจพบสภาพและความเสียหายด้านขวารถ ดังนี้</div>';
    $analysis_vehicle_rows_html .= '<div class="fd-block-i2">' . hBlock($aRight[$i] ?? '') . '</div>';

    $analysis_vehicle_rows_html .= '<div class="fl-b i1" style="margin-top:6px;">' . $sec . '.5 ตรวจพบสภาพและความเสียหายบริเวณอื่นๆ ดังนี้</div>';
    $analysis_vehicle_rows_html .= '<div class="fd-block-i2">' . hBlock($aOther[$i] ?? '') . '</div>';
    $analysis_vehicle_rows_html .= '</div>';
}

// --- Analysis extra & scene detail (conditional sections) ---
$analysisExtra = f('rt_analysis_extra');
$analysisExtraHtml = '';
if (!empty($analysisExtra)) {
    $analysisExtraHtml = '<div class="i1" style="margin-top:4px;"><span class="fl-b">4.' . ($aCount + 1) . '</span> <span class="fl">(ถ้ามี)</span></div>';
    $analysisExtraHtml .= '<div class="fd-block">' . hBlock($analysisExtra) . '</div>';
}

$sceneDetail = f('rt_scene_detail');
$sceneDetailHtml = '';
if (!empty($sceneDetail)) {
    $nextNo = $aCount + (empty($analysisExtra) ? 1 : 2);
    $sceneDetailHtml = '<div class="i1" style="margin-top:4px;"><span class="fl-b">4.' . $nextNo . ' ลักษณะของสถานที่เกิดเหตุ</span></div>';
    $sceneDetailHtml .= '<div class="fd-block">' . hBlock($sceneDetail) . '</div>';
}

// --- Sign date parts ---
$signDateParts = parseDateParts(f('rt_sign_date'));

// ==========================================
// 5. BUILD REPLACEMENTS
// ==========================================
$replacements = [
    '{{report_no}}' => h($report_no),
    '{{report_year_short}}' => h($report_year_short),
    '{{total_pages}}' => '3',
    '{{agency_name}}' => h(!empty($agencyName) ? $agencyName : f('rt_agency_name')),

    // Section 1
    '{{receive_date}}' => h(thaiDateFull(f('rt_receive_date'))),
    '{{receive_time}}' => h(f('rt_receive_time')),
    '{{daily_ref}}' => h(f('rt_daily_ref')),
    '{{agency_center_name}}' => h(!empty($agencyCenterName) ? $agencyCenterName : f('rt_agency_center_name')),
    '{{agency_province_name}}' => h(!empty($agencyProvinceName) ? $agencyProvinceName : f('rt_agency_province_name')),
    '{{notify_method_text}}' => $notify_method_text,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_phone}}' => $chk_notify_phone,
    '{{chk_notify_radio}}' => $chk_notify_radio,
    '{{chk_notify_other}}' => $chk_notify_other,
    '{{notify_other_text}}' => h($notify_other_text),
    '{{from_station}}' => h(f('rt_from_station')),
    '{{officer_name}}' => h(f('rt_officer_name')),

    // Vehicles
    '{{vehicle_rows}}' => $vehicle_rows_html,
    '{{vehicle_next_no}}' => ($vCount + 1),
    '{{scene_location}}' => hBlock(f('rt_scene_location', f('rt_inspect_location'))),

    // Section 2 purposes
    '{{purpose_qty_1}}' => h(f('rt_purpose_qty_1')),
    '{{purpose_qty_2}}' => h(f('rt_purpose_qty_2')),
    '{{purpose_other_text}}' => h(f('rt_purpose_other_text')),

    // Section 3 inspectors
    '{{inspector_rows}}' => $inspector_rows_html,

    // Section 4 case behavior
    '{{case_behavior}}' => hBlock(f('rt_case_behavior')),

    // Page 2 - Analysis
    '{{inspect_location}}' => h(f('rt_inspect_location')),
    '{{inspect_place}}' => h(f('rt_inspect_place')),
    '{{analyze_date}}' => h(thaiDateFull(f('rt_analyze_date'))),
    '{{analyze_time}}' => h(f('rt_analyze_time')),
    '{{analysis_vehicle_rows}}' => $analysis_vehicle_rows_html,

    // Page 3
    '{{analysis_extra_section}}' => $analysisExtraHtml,
    '{{scene_detail_section}}' => $sceneDetailHtml,
    '{{compare_total_items}}' => h(f('rt_compare_total_items')),
    '{{compare_5_1}}' => hBlock(f('rt_compare_5_1')),
    '{{compare_5_1_sub}}' => hBlock(f('rt_compare_5_1_sub')),
    '{{compare_5_ref}}' => h(f('rt_compare_5_ref', '5.2')),
    '{{compare_5_2}}' => hBlock(f('rt_compare_5_2')),
    '{{compare_5_3}}' => hBlock(f('rt_compare_5_3')),
    '{{compare_5_4}}' => hBlock(f('rt_compare_5_4')),
    '{{opinion}}' => hBlock(f('rt_opinion')),

    // Signature
    '{{sign_name}}' => h(f('rt_sign_name')),
    '{{sign_position}}' => h(f('rt_sign_position')),
    '{{sign_date_day}}' => h($signDateParts['day']),
    '{{sign_date_month}}' => h($signDateParts['month']),
    '{{sign_date_year}}' => h($signDateParts['year']),
];

// ==========================================
// 6. READ TEMPLATE AND REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_traffic_report_preview.html');
if ($htmlTemplate === false) {
    die("Error: ไม่สามารถอ่านไฟล์ template ได้");
}

$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// ==========================================
// EXPORT สำหรับ DOCX (จราจร)
// ==========================================
if (defined('WORD_DOCX_MODE') && WORD_DOCX_MODE) {
    $GLOBALS['traffic_report_export'] = [
        'replacements' => $replacements,
        'traffic' => $data ?? [],
    ];
    return;
}

// ==========================================
// 7. OUTPUT HTML
// ==========================================
if (!defined('WORD_REPORT_CAPTURE')) {
    header('Content-Type: text/html; charset=utf-8');
}
echo $htmlContent;
