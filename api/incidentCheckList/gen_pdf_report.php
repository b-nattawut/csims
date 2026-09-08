<?php
/**
 * gen_pdf_report.php - Generate PDF F-CS-11 (แบบการตรวจเก็บและส่งมอบวัตถุพยาน)
 * ใช้ mPDF สร้าง PDF จริง — ดึง logic ข้อมูลแบบเดียวกับ gen_pdf_report_html.php
 *
 * รับ parameter: incident_id (GET)
 * ดึงข้อมูลจาก: incident_checklist_transaction.incident_checklist_data (JSON)
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../db_config.php';

// ==========================================
// 1. รับ Parameter และดึงข้อมูลจาก Database
// ==========================================

$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) {
    die("Error: กรุณาระบุ incident_id");
}

try {
    $stmt = $pdo->prepare("SELECT incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        die("Error: ไม่พบข้อมูล Checklist สำหรับ incident_id: " . $incident_id);
    }

    $data = json_decode($result['incident_checklist_data'], true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        die("Error: ไม่สามารถอ่านข้อมูล JSON ได้ - " . json_last_error_msg());
    }

    $stmtApprover = $pdo->prepare("SELECT fullName, positionName FROM rn_ReceiveNotiApprove WHERE ComplaintsID = ? AND seqSignature = 3");
    $stmtApprover->execute([$incident_id]);
    $approverData = $stmtApprover->fetch(PDO::FETCH_ASSOC);

    $approverName = $approverData['fullName'] ?? '';
    $approverPosition = $approverData['positionName'] ?? '';

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
    'default_font_size' => 14,
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 10,
    'margin_bottom' => 10,
]);

// ==========================================
// 3. Helper Functions
// ==========================================

function chk($val, $target)
{
    $isChecked = false;
    if (is_array($val)) {
        $isChecked = in_array($target, $val);
    } else {
        $isChecked = ($val == $target);
    }

    $size = "14";
    $style = 'style="vertical-align: -2px;"';
    $svgUnchecked = '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" ' . $style . '><rect x="5" y="5" width="90" height="90" fill="none" stroke="black" stroke-width="8" /></svg>';
    $svgChecked = '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" ' . $style . '><rect x="5" y="5" width="90" height="90" fill="none" stroke="black" stroke-width="8" /><path d="M20 50 L40 75 L80 20" fill="none" stroke="black" stroke-width="12" /></svg>';

    return $isChecked ? $svgChecked : $svgUnchecked;
}

function dots($val, $length = 50)
{
    if (!empty($val) && $val !== '-') {
        return '&nbsp;<u style="padding: 0 3px;">' . htmlspecialchars($val) . '</u>&nbsp;';
    } else {
        return str_repeat('.', $length);
    }
}

function tab($n = 3) { return str_repeat('&nbsp;', $n); }

function thaiDateFull_pdf($datetime)
{
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}
function thaiTime_pdf($datetime)
{
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    return date('H:i', $ts);
}
function parseDateParts_pdf($datetime)
{
    if (empty($datetime)) return ['day'=>'','month'=>'','year'=>''];
    $ts = strtotime($datetime);
    if ($ts === false) return ['day'=>'','month'=>'','year'=>''];
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return ['day'=>date('j',$ts),'month'=>$months[(int)date('n',$ts)]??'','year'=>date('Y',$ts)+543];
}
function joinDT($date, $time)
{
    $date = trim((string)$date); $time = trim((string)$time);
    if ($date === '') return '';
    if ($time === '') $time = '00:00';
    return $date . 'T' . $time;
}
function getV($arr, $key, $default = '') { if (!is_array($arr)) return $default; return isset($arr[$key]) && $arr[$key] !== null ? $arr[$key] : $default; }

function resolveUser($pdo, $rawId)
{
    $rawId = trim((string)$rawId);
    if ($rawId === '' || !preg_match('/^\d+$/', $rawId)) return ['name'=>'','position'=>''];
    try {
        $stmt = $pdo->prepare("SELECT CONCAT(IFNULL(r.rank_name,''),' ',p.first_name,' ',p.last_name) AS fullname, IFNULL(up.position_name,'') AS position_name FROM users u INNER JOIN user_profile p ON u.user_id = p.user_id LEFT JOIN user_rank r ON p.rank_id = r.rank_id LEFT JOIN user_position up ON p.position_id = up.position_id WHERE u.user_id = ?");
        $stmt->execute([$rawId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return ['name'=>trim($row['fullname']??''),'position'=>trim($row['position_name']??'')];
    } catch (Exception $e) {}
    return ['name'=>'','position'=>''];
}

function renderSigImg_pdf($sigData)
{
    if (empty($sigData)) return '';
    if (is_array($sigData) && !empty($sigData['file_id'])) {
        return '<img src="/csims/api/incidentCheckList/getFile.php?id=' . (int)$sigData['file_id'] . '" style="width:120px;height:45px;vertical-align:bottom;margin:0 5px;">';
    }
    if (is_array($sigData) && !empty($sigData['filename'])) {
        $path = __DIR__ . '/../../uploads/checklist_signatures/' . $sigData['filename'];
        if (file_exists($path)) {
            return '<img src="' . $path . '" style="width:120px;height:45px;vertical-align:bottom;margin:0 5px;">';
        }
        return '';
    }
    $base64 = '';
    if (is_array($sigData) && !empty($sigData['base64'])) { $base64 = $sigData['base64']; }
    elseif (is_string($sigData)) { $base64 = $sigData; }
    if (empty($base64) || strpos($base64, 'data:image') === false) return '';
    return '<img src="' . $base64 . '" style="width:120px;height:45px;vertical-align:bottom;margin:0 5px;">';
}

// ==========================================
// 4. ดึงข้อมูล (ใช้ logic เดียวกับ gen_pdf_report_html.php)
// ==========================================

// User map
$userMap = [];
try {
    $sqlUser = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname FROM user_profile t1 LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id";
    $stmtUser = $pdo->query($sqlUser);
    while ($u = $stmtUser->fetch(PDO::FETCH_ASSOC)) { $userMap[$u['user_id']] = $u['fullname']; }
} catch (Exception $e) {}

$gen = $data['general_info'] ?? [];
$handover = $data['handover'] ?? [];
$datetimeInfo = $data['datetime_info'] ?? [];
$sceneInfo = $data['scene_info'] ?? [];
$forensicResults = $data['forensic_results'] ?? [];

// Report No
$reportNo = getV($gen, 'report_no');
$thaiPrefixMap = ['LC-'=>'ทพ ','MD-'=>'ชว ','BM-'=>'รบ '];
foreach ($thaiPrefixMap as $en => $th) { if (strpos($reportNo, $en) === 0) { $reportNo = $th . substr($reportNo, strlen($en)); break; } }

// Source Station
$sourceStation = getV($gen, 'source_station');
if (empty($sourceStation)) $sourceStation = getV($gen, 'police_station');

// Report Datetime
$reportDatetimeRaw = getV($gen, 'report_datetime');
if (empty($reportDatetimeRaw)) $reportDatetimeRaw = joinDT(getV($gen, 'case_date'), getV($gen, 'case_time'));
if (empty($reportDatetimeRaw)) $reportDatetimeRaw = joinDT(getV($gen, 'receive_date'), getV($gen, 'receive_time'));
if (empty($reportDatetimeRaw)) $reportDatetimeRaw = joinDT(getV($gen, 'report_date'), getV($gen, 'report_time'));
$reportDateParts = parseDateParts_pdf($reportDatetimeRaw);

// Notification Channel
$channel = getV($gen, 'report_channel');
if (empty($channel)) $channel = getV($gen, 'notify_method');
$channelOtherText = getV($gen, 'report_channel_other');
if (empty($channelOtherText)) $channelOtherText = getV($gen, 'notify_method_other_text');

// Document No
$docNo = getV($gen, 'document_no');
if (empty($docNo)) $docNo = getV($gen, 'case_doc_no');
if (empty($docNo)) $docNo = getV($gen, 'doc_no');

// Case Type
$caseType = getV($gen, 'case_type');
$caseTypeText = '';
switch ($caseType) {
    case 'theft':   $caseTypeText = 'ลักทรัพย์'; break;
    case 'snatch':  $caseTypeText = 'ชิงทรัพย์'; break;
    case 'robbery': $caseTypeText = 'ปล้นทรัพย์'; break;
    case 'murder':  $caseTypeText = 'ฆาตกรรม'; break;
    case 'bomb':    $caseTypeText = 'ระเบิด'; break;
    case 'arson':   $caseTypeText = 'วางเพลิง'; break;
    case 'fire':    $caseTypeText = 'เพลิงไหม้'; break;
    case 'life':    $caseTypeText = 'คดีชีวิต'; break;
    case 'traffic': $caseTypeText = 'จราจร'; break;
    case 'fingerprint': $caseTypeText = 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)'; break;
    case 'scene_evidence': $caseTypeText = 'ตรวจเก็บและส่งมอบวัตถุพยาน'; break;
    case 'person_evidence':
    case 'person evidence': $caseTypeText = 'ตรวจเก็บวัตถุพยานบุคคล'; break;
    case 'other':   $caseTypeText = getV($gen, 'case_type_other'); break;
    default:        $caseTypeText = $caseType;
}

// Incident Location
$incidentLocation = getV($gen, 'location_detail');
if (empty($incidentLocation)) $incidentLocation = getV($data['scene_info'] ?? [], 'crime_location');
if (empty($incidentLocation)) $incidentLocation = getV($sceneInfo, 'incident_location');

// Incident Datetime
$incidentDatetimeRaw = getV($gen, 'incident_datetime');
if (empty($incidentDatetimeRaw)) $incidentDatetimeRaw = joinDT(getV($gen, 'victim_know_date'), getV($gen, 'victim_know_time'));
if (empty($incidentDatetimeRaw)) $incidentDatetimeRaw = joinDT(getV($gen, 'incident_date'), getV($gen, 'incident_time'));
if (empty($incidentDatetimeRaw)) $incidentDatetimeRaw = joinDT(getV($datetimeInfo, 'victim_known_date'), getV($datetimeInfo, 'victim_known_time'));
$incidentDate = thaiDateFull_pdf($incidentDatetimeRaw);
$incidentTime = thaiTime_pdf($incidentDatetimeRaw);

// Inspection Datetime
$inspectionDatetimeRaw = getV($gen, 'inspection_datetime');
if (empty($inspectionDatetimeRaw)) $inspectionDatetimeRaw = joinDT(getV($gen, 'inspect_date'), getV($gen, 'inspect_time'));
if (empty($inspectionDatetimeRaw)) $inspectionDatetimeRaw = joinDT(getV($gen, 'collect_date'), getV($gen, 'collect_time'));
if (empty($inspectionDatetimeRaw)) $inspectionDatetimeRaw = joinDT(getV($datetimeInfo, 'inspection_date'), getV($datetimeInfo, 'inspection_time'));
$inspectDate = thaiDateFull_pdf($inspectionDatetimeRaw);
$inspectTime = thaiTime_pdf($inspectionDatetimeRaw);

// Victim
$victim = $gen['victim'] ?? [];
$victimName = getV($victim, 'name');
if (empty($victimName)) $victimName = trim(getV($victim, 'firstname') . ' ' . getV($victim, 'lastname'));
$victimAge = getV($victim, 'age');
if (empty($victimName) && !empty($sceneInfo['persons']) && is_array($sceneInfo['persons'])) {
    $firstPerson = $sceneInfo['persons'][0] ?? [];
    $victimName = getV($firstPerson, 'name');
    $victimAge = getV($firstPerson, 'age');
}

// Investigator
$investigator = $gen['investigator'] ?? [];
$officerName = trim(getV($investigator, 'firstname') . ' ' . getV($investigator, 'lastname'));
if (empty($officerName)) $officerName = getV($investigator, 'name');
if (empty($officerName)) $officerName = getV($datetimeInfo, 'investigator_name');

// Province
$policeUnit = '';
$province = '';
$provinceId = getV($gen, 'province_id');
if (!empty($provinceId)) {
    try {
        $stmtProv = $pdo->prepare("SELECT cfs_name FROM CFS_province WHERE cfs_id = ?");
        $stmtProv->execute([$provinceId]);
        $rowProv = $stmtProv->fetch(PDO::FETCH_ASSOC);
        if ($rowProv) $province = $rowProv['cfs_name'] ?? '';
    } catch (Exception $e) {}
}

// ==========================================
// 5. EVIDENCE TABLE ROWS (with lab_unit from collected_evidence)
// ==========================================
$evidenceList = $data['evidences'] ?? [];
if (empty($evidenceList)) {
    $fallback = $data['evidences_found'] ?? [];
    if (!empty($fallback) && is_array($fallback)) {
        $tmp = [];
        foreach ($fallback as $i => $ev) { $tmp[] = ['no'=>$ev['no']??($i+1),'detail'=>$ev['item']??($ev['detail']??''),'lab_unit'=>$ev['lab_unit']??'']; }
        $evidenceList = $tmp;
    }
}
if (empty($evidenceList)) {
    $fallback = $data['measurements'] ?? [];
    if (!empty($fallback) && is_array($fallback)) {
        $tmp = [];
        foreach ($fallback as $i => $ev) { $d=trim($ev['item']??($ev['detail']??'')); if($d!=='') $tmp[]=['no'=>$ev['no']??($i+1),'detail'=>$d,'lab_unit'=>$ev['lab_unit']??'']; }
        $evidenceList = $tmp;
    }
}

// Sanitize
$sanitized = [];
$evidenceTypeLabelMap = ['blood'=>'คราบสีแดงคล้ายโลหิต','fingerprint'=>'ลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง','dna'=>'สารพันธุกรรม','toolmark'=>'ร่องรอยการตัด (Toolmark)','other'=>'อื่น ๆ'];
if (!empty($evidenceList) && is_array($evidenceList)) {
    foreach ($evidenceList as $i => $evidence) {
        if (!empty($evidence['_summary_only'])) continue;
        $detail = trim((string)getV($evidence, 'detail'));
        if ($detail === '') $detail = trim((string)getV($evidence, 'item'));
        if ($detail === '') { $type = trim((string)getV($evidence, 'type')); if ($type !== '' && isset($evidenceTypeLabelMap[$type])) $detail = $evidenceTypeLabelMap[$type]; }
        if ($detail === '') continue;
        $sanitized[] = ['no'=>getV($evidence,'no',count($sanitized)+1),'detail'=>$detail,'lab_unit'=>getV($evidence,'lab_unit')];
    }
}
$evidenceList = $sanitized;

// Merge lab_units from collected_evidence
$collectedLabUnits = $data['collected_evidence']['lab_units'] ?? [];
if (!empty($collectedLabUnits) && is_array($collectedLabUnits)) {
    foreach ($evidenceList as $evIdx => &$evItem) {
        if (empty($evItem['lab_unit']) && isset($collectedLabUnits[$evIdx])) $evItem['lab_unit'] = $collectedLabUnits[$evIdx];
    }
    unset($evItem);
}

$labUnitMap = [
    'bio_dna'=>'กลุ่มงานตรวจชีววิทยา','chemical'=>'กลุ่มงานตรวจทางเคมีฟิสิกส์',
    'fingerprint'=>'กลุ่มงานตรวจลายนิ้วมือแฝง','drug'=>'กลุ่มงานตรวจยาเสพติด',
    'gun'=>'กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน','document'=>'กลุ่มงานตรวจเอกสาร',
    'digital'=>'กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล',
    'computer'=>'กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์',
];

$evidenceRowsHtml = '';
$rowCount = 0;
if (!empty($evidenceList)) {
    $index = 1;
    foreach ($evidenceList as $evidence) {
        $evidenceNo = getV($evidence, 'no', $index);
        $evidenceDetail = trim((string)getV($evidence, 'detail'));
        $labUnit = getV($evidence, 'lab_unit');
        if ($labUnit === 'traffic') $labUnit = getV($forensicResults, 'lab_unit', '');
        if ($evidenceDetail === '' && (string)$evidenceNo === '') continue;
        $labUnitText = $labUnitMap[$labUnit] ?? $labUnit;
        if (empty($labUnitText)) $labUnitText = '-';

        $evidenceRowsHtml .= '<tr>
            <td style="text-align:center;">' . htmlspecialchars($evidenceNo) . '</td>
            <td style="text-align:left;">' . htmlspecialchars($evidenceDetail) . '</td>
            <td style="text-align:center;">' . htmlspecialchars($labUnitText) . '</td>
        </tr>';
        $index++;
        $rowCount++;
    }
}
// Fill empty rows
for ($i = $rowCount; $i < 6; $i++) {
    $evidenceRowsHtml .= '<tr><td style="height:18px;">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
}

$evidenceNos = [];
if (!empty($evidenceList)) { foreach ($evidenceList as $ev) { if (!empty($ev['no'])) $evidenceNos[] = $ev['no']; } }
$evidenceNoStr = !empty($evidenceNos) ? implode(', ', $evidenceNos) : '';

// ==========================================
// 6. HANDOVER / SIGNATURES (same as HTML version)
// ==========================================
$recv_id = getV($handover, 'receiver_id'); $recv_name = ''; $recv_pos = getV($handover, 'receiver_pos');
if (!empty($recv_id) && isset($userMap[$recv_id])) { $recv_name = $userMap[$recv_id]; }
elseif (!empty($recv_id)) { $r = resolveUser($pdo, $recv_id); if(!empty($r['name'])) $recv_name=$r['name']; if(empty($recv_pos)&&!empty($r['position'])) $recv_pos=$r['position']; }

$delv_id = getV($handover, 'deliverer_id'); $delv_name = ''; $delv_pos = getV($handover, 'deliverer_pos');
if (!empty($delv_id) && isset($userMap[$delv_id])) { $delv_name = $userMap[$delv_id]; }
elseif (!empty($delv_id)) { $r = resolveUser($pdo, $delv_id); if(!empty($r['name'])) $delv_name=$r['name']; if(empty($delv_pos)&&!empty($r['position'])) $delv_pos=$r['position']; }

// Signatures
$recv_sig = getV($handover, 'receiver_sig');
$delv_sig = getV($handover, 'deliverer_sig');
$signatures = $data['signatures'] ?? [];
if (empty($recv_sig) && isset($signatures['receiver_sig'])) $recv_sig = $signatures['receiver_sig'];
if (empty($delv_sig) && isset($signatures['deliverer_sig'])) $delv_sig = $signatures['deliverer_sig'];
$attachmentsMeta = $data['attachments_meta'] ?? [];
if (empty($recv_sig) && isset($attachmentsMeta['receiver_sig'])) $recv_sig = $attachmentsMeta['receiver_sig'];
if (empty($delv_sig) && isset($attachmentsMeta['deliverer_sig'])) $delv_sig = $attachmentsMeta['deliverer_sig'];
if (empty($recv_sig) && isset($signatures['receiver_signature'])) $recv_sig = $signatures['receiver_signature'];
if (empty($delv_sig) && isset($signatures['sender_signature'])) $delv_sig = $signatures['sender_signature'];
if (empty($delv_sig) && isset($signatures['sender_sig'])) $delv_sig = $signatures['sender_sig'];
if (empty($delv_sig) && isset($signatures['signer_sig'])) $delv_sig = $signatures['signer_sig'];

$receiver_sig_img = renderSigImg_pdf($recv_sig);
$deliverer_sig_img = renderSigImg_pdf($delv_sig);

// Handover Date/Time
$handoverDate = thaiDateFull_pdf($inspectionDatetimeRaw);
$handoverTime = thaiTime_pdf($inspectionDatetimeRaw);
if (empty($handoverDate)) $handoverDate = thaiDateFull_pdf(getV($handover, 'inspection_end_date'));
if (empty($handoverTime)) $handoverTime = thaiTime_pdf(getV($handover, 'inspection_end_time'));

// Name fallbacks
if (empty($recv_name)) { $recv_name = getV($handover, 'receiver_name'); if (empty($recv_name)) $recv_name = getV($handover['receiver']??[], 'name_id'); }
if (!empty($recv_name) && preg_match('/^\d+$/', trim((string)$recv_name))) { $r=resolveUser($pdo,$recv_name); if(!empty($r['name'])) $recv_name=$r['name']; if(empty($recv_pos)&&!empty($r['position'])) $recv_pos=$r['position']; }
if (empty($recv_pos)) { $recv_pos = getV($handover, 'receiver_position'); if (empty($recv_pos)) $recv_pos = getV($handover['receiver']??[], 'position'); }

if (empty($delv_name)) { $delv_name = getV($handover, 'sender_name'); if (empty($delv_name)) $delv_name = getV($handover['sender']??[], 'name_id'); }
if (!empty($delv_name) && preg_match('/^\d+$/', trim((string)$delv_name))) { $r=resolveUser($pdo,$delv_name); if(!empty($r['name'])) $delv_name=$r['name']; if(empty($delv_pos)&&!empty($r['position'])) $delv_pos=$r['position']; }
if (empty($delv_pos)) { $delv_pos = getV($handover, 'sender_position'); if (empty($delv_pos)) $delv_pos = getV($handover['sender']??[], 'position'); }

// Signer fallback
$signer = $data['signer'] ?? [];
if (empty($delv_name)) {
    $signerId = getV($signer, 'id');
    if (!empty($signerId)) { $r=resolveUser($pdo,$signerId); if(!empty($r['name'])) $delv_name=$r['name']; if(empty($delv_pos)&&!empty($r['position'])) $delv_pos=$r['position']; }
}
if (empty($delv_pos) && !empty($signer['position'])) $delv_pos = $signer['position'];

// Channel checks
$chk_phone = chk($channel, 'phone');
$chk_document = chk($channel, 'document');
if (!is_array($channel) && $channel !== 'document') $chk_document = chk($channel, 'letter');
$chk_radio = chk($channel, 'radio');
$chk_other = chk($channel, 'other');
// Also check Thai values
if (is_array($channel)) {
    if (in_array('ทางโทรศัพท์', $channel)) $chk_phone = chk(['phone'], 'phone');
    if (in_array('หนังสือนำส่ง', $channel)||in_array('ทางหนังสือ', $channel)) $chk_document = chk(['document'], 'document');
    if (in_array('ทางวิทยุ', $channel)||in_array('ทางวิทยุสื่อสาร', $channel)) $chk_radio = chk(['radio'], 'radio');
    if (in_array('อื่นๆ', $channel)) $chk_other = chk(['other'], 'other');
}

// ==========================================
// 7. สร้าง HTML Content สำหรับ mPDF
// ==========================================

$logoPath = __DIR__ . '/../../images/office-of-police-forensic-icon.jpg';

$html = '
<style>
    body { font-family: sarabun; font-size: 13pt; line-height: 1.5; }
    .bold { font-weight: bold; }
    .center { text-align: center; }
    u { text-underline-offset: 3px; padding: 0 2px; }
    table { table-layout: fixed; width: 100%; }
    td { overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; }
    .evidence-table { width: 100%; border-collapse: collapse; margin-top: 3px; }
    .evidence-table th, .evidence-table td { border: 1px solid #000; padding: 2px 5px; font-size: 12pt; }
    .evidence-table th { background-color: #fff; font-weight: bold; }
</style>

<table width="100%" style="border:none;border-collapse:collapse;">
    <tr><td style="text-align:center;vertical-align:middle;"><img src="' . $logoPath . '" style="width:110px;height:auto;"></td></tr>
    <tr><td style="text-align:right;font-size:14pt;line-height:1.6;padding-top:5px;">เลขรับที่/เลขรายงาน' . dots($reportNo, 30) . '</td></tr>
</table>

<div style="text-align:center;font-size:15pt;font-weight:bold;margin-bottom:3px;">แบบการตรวจเก็บและส่งมอบวัตถุพยาน</div>

<div style="text-align:right;margin-bottom:3px;">เขียนที่' . dots($sourceStation, 50) . '</div>
<div style="text-align:right;margin-bottom:5px;">วันที่' . dots($reportDateParts['day'], 8) . 'เดือน' . dots($reportDateParts['month'], 20) . 'พ.ศ.' . dots($reportDateParts['year'], 8) . '</div>
<div style="line-height:1.6;">

    รับแจ้งเหตุ จาก ท้องที่ สน./สภ.' . dots($sourceStation, 45) . '<br>
    ' . $chk_phone . ' ทางโทรศัพท์ ' . tab(2) . $chk_document . ' หนังสือนำส่ง ' . tab(2) . $chk_radio . ' ทางวิทยุ ' . tab(2) . $chk_other . ' อื่น ๆ ' . dots($channelOtherText, 15) . '<br>
    ที่' . dots($docNo, 20) . 'เมื่อวันที่' . dots($reportDateParts['day'], 8) . 'เดือน' . dots($reportDateParts['month'], 18) . 'พ.ศ.' . dots($reportDateParts['year'], 8) . '<br>
    คดี' . dots($caseTypeText, 30) . 'สถานที่เกิดเหตุ' . dots($incidentLocation, 30) . '
</div>

<div style="line-height:1.6;margin-top:3px;">
    วัน เวลา ทราบเหตุ/เกิดเหตุ' . dots($incidentDate, 35) . 'เวลาประมาณ' . dots($incidentTime, 10) . 'น.<br>
    วัน เวลา ตรวจสถานที่เกิดเหตุ/ตรวจเก็บวัตถุพยาน' . dots($inspectDate, 22) . 'เวลาประมาณ' . dots($inspectTime, 8) . 'น.<br>
    ผู้เสียหาย/ผู้เสียชีวิต' . dots($victimName, 40) . 'อายุประมาณ' . dots($victimAge, 8) . 'ปี<br>
    ชื่อพนักงานสอบสวนเจ้าของคดี' . dots($officerName, 45) . '<br>
    กสก.' . dots($policeUnit, 12) . '/พธ.จว.' . dots($province, 12) . 'ได้ทำการตรวจเก็บวัตถุพยานในสถานที่เกิดเหตุ จำนวน' . dots($rowCount > 0 ? $rowCount : '', 5) . 'รายการ ดังนี้
</div>

<table class="evidence-table">
    <thead><tr>
        <th style="width:12%;">ลำดับ</th>
        <th style="width:55%;">รายการ</th>
        <th style="width:33%;">การตรวจพิสูจน์</th>
    </tr></thead>
    <tbody>' . $evidenceRowsHtml . '</tbody>
</table>

<div style="margin-top:8px;line-height:1.5;">
    วัตถุพยานลำดับที่ ' . dots($evidenceNoStr, 20) . ' ได้ส่งมอบให้กับพนักงานสอบสวนเจ้าของคดีเพื่อดำเนินการต่อไป
</div>

<div style="text-align:center;margin-top:15px;line-height:1.8;padding-left:340px;">
    (ลงชื่อ)' . (!empty($receiver_sig_img) ? $receiver_sig_img : dots('', 50)) . 'ผู้รับมอบ<br>
    (' . dots($recv_name, 45) . ')<br>
    ตำแหน่ง ' . dots($recv_pos, 60) . '<br>
    วันที่' . dots($handoverDate, 25) . ' เวลา' . dots($handoverTime, 12) . 'น.
</div>

<div style="text-align:center;margin-top:15px;line-height:1.8;padding-left:340px;">
    (ลงชื่อ)' . (!empty($deliverer_sig_img) ? $deliverer_sig_img : dots('', 50)) . 'ผู้มอบ<br>
    (' . dots($delv_name, 45) . ')<br>
    ตำแหน่ง ' . dots($delv_pos, 60) . '<br>
    วันที่' . dots($handoverDate, 25) . ' เวลา' . dots($handoverTime, 12) . 'น.
</div>

<div style="text-align:right;font-size:10pt;margin-top:10px;">
    F-CS-11 แก้ไขครั้งที่ 1<br>
    เริ่มใช้ 1 ส.ค. 60
</div>';

// ==========================================
// 8. Output PDF
// ==========================================

$mpdf->WriteHTML($html);
$mpdf->Output('report_F-CS-11_' . $incident_id . '.pdf', 'I');
