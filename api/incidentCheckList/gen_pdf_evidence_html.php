<?php
/**
 * gen_pdf_evidence_html.php - Generate Evidence Form (วัตถุพยาน) from HTML Template
 *
 * รับ parameter: incident_id (GET)
 * ดึงข้อมูลจาก: incident_checklist_transaction + rn_ReceiveNoti
 */

header('Content-Type: text/html; charset=utf-8');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../helpers/report_no.php';

// ==========================================
// 1. HELPER FUNCTIONS
// ==========================================

function thaiDateShort($datetime)
{
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    $months = [null, 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $year = date('Y', $ts) + 543;
    $shortYear = substr($year, -2);
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . $shortYear;
}

function thaiDateFull($datetime)
{
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $d = date('j', $ts);
    $m = (int)date('n', $ts);
    $y = date('Y', $ts) + 543;
    return $d . ' ' . $months[$m] . ' ' . $y;
}

function thaiTime($datetime)
{
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    return date('H:i', $ts);
}

function getVal($arr, $key, $default = '')
{
    if (!is_array($arr)) return $default;
    return isset($arr[$key]) && $arr[$key] !== null ? $arr[$key] : $default;
}

function normalizeText($text)
{
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    return preg_replace('/\s+/u', ' ', $text);
}

function splitLocationLines($text, $firstLineLimit = 45)
{
    $text = normalizeText($text);
    if ($text === '') {
        return ['line1' => '', 'line2' => ''];
    }

    $sub = function ($value, $start, $len = null) {
        if (function_exists('mb_substr')) {
            return $len === null
                ? mb_substr($value, $start, null, 'UTF-8')
                : mb_substr($value, $start, $len, 'UTF-8');
        }
        return $len === null ? substr($value, $start) : substr($value, $start, $len);
    };
    
    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    
    // ถ้าข้อความสั้นพอ ให้แสดงบรรทัดเดียว
    if ($length <= $firstLineLimit) {
        return ['line1' => $text, 'line2' => ''];
    }
    
    // หาช่องว่างที่ใกล้ $firstLineLimit ที่สุด (ไม่เกิน) เพื่อตัดที่ขอบคำ
    $bestSpace = false;
    for ($i = min($firstLineLimit, $length - 1); $i >= 0; $i--) {
        if ($sub($text, $i, 1) === ' ') {
            $bestSpace = $i;
            break;
        }
    }
    
    // ถ้าไม่เจอช่องว่างก่อน limit → หาช่องว่างถัดไปหลัง limit
    if ($bestSpace === false) {
        for ($i = $firstLineLimit; $i < $length; $i++) {
            if ($sub($text, $i, 1) === ' ') {
                $bestSpace = $i;
                break;
            }
        }
    }
    
    if ($bestSpace !== false) {
        $line1 = $sub($text, 0, $bestSpace);
        $line2 = $sub($text, $bestSpace + 1);
    } else {
        // ไม่มีช่องว่างเลย → แสดงบรรทัดเดียว
        return ['line1' => $text, 'line2' => ''];
    }

    return [
        'line1' => trim((string)$line1),
        'line2' => trim((string)$line2),
    ];
}

function renderCheckbox($condition)
{
    return $condition ? '✓' : '';
}

// ==========================================
// 2. FETCH DATA FROM DATABASE
// ==========================================
$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) die("Error: กรุณาระบุ incident_id");

try {
    // ดึงข้อมูล Checklist Transaction
    $stmt = $pdo->prepare("SELECT incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) die("Error: ไม่พบข้อมูล Checklist สำหรับ incident_id: " . $incident_id);

    $data = json_decode($row['incident_checklist_data'], true);
    if (json_last_error() !== JSON_ERROR_NONE) $data = [];

    // ดึงข้อมูลรับแจ้งเหตุ
    $stmtRn = $pdo->prepare("SELECT receiveNoti_No, complaints_From, complaints_type FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
    $stmtRn->execute([$incident_id]);
    $rnRow = $stmtRn->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// 3. PREPARE USER MAP (ID => Fullname)
// ==========================================
$userMap = [];
$userPosMap = [];
try {
    $sqlUser = "SELECT t1.user_id, 
                       CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname,
                       t1.position_name
                FROM user_profile t1
                LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id";
    $stmtUser = $pdo->query($sqlUser);
    while ($u = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        $userMap[$u['user_id']] = $u['fullname'];
        $userPosMap[$u['user_id']] = $u['position_name'] ?? '';
    }
} catch (Exception $e) {
    // ignore
}

// ==========================================
// 4. EXTRACT DATA
// ==========================================
$gen = $data['general_info'] ?? [];
$handover = $data['handover'] ?? [];
$evidenceForm = $data['evidence_form'] ?? [];

// Station Name (evidence_form first, then ReceiveNoti, then general_info)
$stationName = getVal($evidenceForm, 'station_name');
if (empty($stationName)) {
    if (!empty($rnRow['complaints_From'])) {
        $stationName = $rnRow['complaints_From'];
    } else {
        $stationName = getVal($gen, 'source_station');
    }
}

// Case Number
$caseNo = '';
if (!empty($rnRow['receiveNoti_No'])) {
    $caseNo = $rnRow['receiveNoti_No'];
}
// ถ้ายังว่าง ลองดึงจาก document_no
if (empty($caseNo)) {
    $caseNo = getVal($gen, 'document_no');
}
$caseNo = convertDocNoToThai($caseNo);

// Location (evidence_form first, then general_info fallback)
$locationDetail = getVal($evidenceForm, 'location_detail');
if (empty($locationDetail)) {
    $locationDetail = getVal($gen, 'location_detail');
}
$locationDetail = normalizeText($locationDetail);
$locationLines = splitLocationLines($locationDetail, 45);
$locationLine1 = $locationLines['line1'] ?? '';
$locationLine2 = $locationLines['line2'] ?? '';
$locationLine2HiddenClass = $locationLine2 !== '' ? '' : 'is-hidden';

// Incident Date/Time (evidence_form first, then general_info fallback)
$evfIncidentDate = getVal($evidenceForm, 'incident_date');
$evfIncidentTime = getVal($evidenceForm, 'incident_time');
if (!empty($evfIncidentDate)) {
    $incidentDatetime = $evfIncidentDate . ' ' . ($evfIncidentTime ?: '00:00');
    $incidentDate = thaiDateFull($incidentDatetime);
    $incidentTime = thaiTime($incidentDatetime);
} else {
    $incidentDate = thaiDateFull(getVal($gen, 'incident_datetime'));
    $incidentTime = thaiTime(getVal($gen, 'incident_datetime'));
}

// Victim rows (ผู้ต้องหา / ผู้ต้องสงสัย / ผู้เสียหาย)
// evidence_form.victims first, then fallback to main checklist sources
$victimRows = [];
$evfVictims = is_array(getVal($evidenceForm, 'victims', [])) ? getVal($evidenceForm, 'victims', []) : [];
$allVictims = is_array(getVal($gen, 'all_victims', [])) ? getVal($gen, 'all_victims', []) : [];
$bombVictims = is_array(getVal($data, 'victims', [])) ? getVal($data, 'victims', []) : [];
$firePersons = [];
if (isset($data['scene_info']) && is_array($data['scene_info'])) {
    $firePersons = is_array(getVal($data['scene_info'], 'persons', [])) ? getVal($data['scene_info'], 'persons', []) : [];
}
$victimInfo = is_array(getVal($data, 'victim_info', [])) ? getVal($data, 'victim_info', []) : [];
$victimTypes = is_array(getVal($victimInfo, 'victim_types', [])) ? getVal($victimInfo, 'victim_types', []) : [];
$victimNames = is_array(getVal($victimInfo, 'victim_names', [])) ? getVal($victimInfo, 'victim_names', []) : [];
$victimAges = is_array(getVal($victimInfo, 'victim_ages', [])) ? getVal($victimInfo, 'victim_ages', []) : [];

$rawVictims = [];
if (!empty($evfVictims)) {
    $rawVictims = $evfVictims;
} elseif (!empty($allVictims)) {
    $rawVictims = $allVictims;
} elseif (!empty($bombVictims)) {
    $rawVictims = $bombVictims;
} elseif (!empty($firePersons)) {
    $rawVictims = $firePersons;
} elseif (!empty($victimTypes) || !empty($victimNames) || !empty($victimAges)) {
    $maxVictim = max(count($victimTypes), count($victimNames), count($victimAges));
    for ($i = 0; $i < $maxVictim; $i++) {
        $rawVictims[] = [
            'type' => $victimTypes[$i] ?? '',
            'name' => $victimNames[$i] ?? '',
            'age'  => $victimAges[$i] ?? '',
        ];
    }
}

foreach ($rawVictims as $rv) {
    $type = trim((string)(getVal($rv, 'type') ?: getVal($rv, 'victim_type') ?: getVal($rv, 'person_type') ?: getVal($rv, 'status')));
    $name = trim((string)(
        getVal($rv, 'name') ?: getVal($rv, 'victim_name') ?: getVal($rv, 'fullname') ?: trim(getVal($rv, 'firstname') . ' ' . getVal($rv, 'lastname'))
    ));
    $age = trim((string)(getVal($rv, 'age') ?: getVal($rv, 'victim_age')));

    if ($type === '' && $name === '' && $age === '') continue;
    $victimRows[] = ['type' => $type, 'name' => $name, 'age' => $age];
}

$victimRowsHtml = '';
foreach ($victimRows as $i => $vr) {
    $victimRowsHtml .= '<tr>';
    $victimRowsHtml .= '<td>' . ($i + 1) . '</td>';
    $victimRowsHtml .= '<td style="text-align:left;padding-left:4px;">' . htmlspecialchars($vr['type']) . '</td>';
    $victimRowsHtml .= '<td style="text-align:left;padding-left:4px;">' . htmlspecialchars($vr['name']) . '</td>';
    $victimRowsHtml .= '<td>' . htmlspecialchars($vr['age']) . '</td>';
    $victimRowsHtml .= '</tr>';
}

// ถ้าไม่มีข้อมูลผู้เกี่ยวข้องเลย ให้แสดง 1 แถวเป็น "-"
if ($victimRowsHtml === '') {
    $victimRowsHtml .= '<tr>';
    $victimRowsHtml .= '<td>1</td>';
    $victimRowsHtml .= '<td>-</td>';
    $victimRowsHtml .= '<td>-</td>';
    $victimRowsHtml .= '<td>-</td>';
    $victimRowsHtml .= '</tr>';
}

// Evidence Collection Date/Time (evidence_form first, then inspection_datetime)
$evfCollectDate = getVal($evidenceForm, 'collect_date');
$evfCollectTime = getVal($evidenceForm, 'collect_time');
if (!empty($evfCollectDate)) {
    $collectDatetime = $evfCollectDate . ' ' . ($evfCollectTime ?: '00:00');
    $collectDate = thaiDateFull($collectDatetime);
    $collectTime = thaiTime($collectDatetime);
} else {
    $collectDate = thaiDateFull(getVal($gen, 'inspection_datetime'));
    $collectTime = thaiTime(getVal($gen, 'inspection_datetime'));
}

// Collector Name (ผู้เก็บวัตถุพยาน) - evidence_form.collector_id first, then handover.receiver_id
$collectorName = '';
$collectorPos = '';
$recv_id = getVal($evidenceForm, 'collector_id');
if (empty($recv_id)) {
    $recv_id = getVal($handover, 'receiver_id');
}
if (!empty($recv_id) && isset($userMap[$recv_id])) {
    $collectorName = $userMap[$recv_id];
    $collectorPos = $userPosMap[$recv_id] ?? getVal($handover, 'receiver_pos');
} elseif (!empty($recv_id) && isset($pdo)) {
    try {
        $stmtRecv = $pdo->prepare("SELECT CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname, t1.position_name
                                   FROM user_profile t1
                                   LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                                   WHERE t1.user_id = ?");
        $stmtRecv->execute([$recv_id]);
        $rowRecv = $stmtRecv->fetch(PDO::FETCH_ASSOC);
        if ($rowRecv) {
            $collectorName = $rowRecv['fullname'];
            $collectorPos = $rowRecv['position_name'] ?? '';
        }
    } catch (Exception $e) {}
}
if (!empty($collectorPos)) {
    $collectorName .= '  ' . $collectorPos;
}

// ==========================================
// 5. EVIDENCE TABLE ROWS
// ==========================================
// evidence_form.evidence_details first, then top-level keys fallback
$evfEvidenceDetails = is_array(getVal($evidenceForm, 'evidence_details', [])) ? getVal($evidenceForm, 'evidence_details', []) : [];
$evidenceList = is_array(getVal($data, 'evidences', [])) ? getVal($data, 'evidences', []) : [];
$measurementList = is_array(getVal($data, 'measurements', [])) ? getVal($data, 'measurements', []) : [];
$evidencesFound = is_array(getVal($data, 'evidences_found', [])) ? getVal($data, 'evidences_found', []) : [];

// If evidence_form has its own evidence_details, use those as primary
if (!empty($evfEvidenceDetails)) {
    $evidenceList = $evfEvidenceDetails;
    $measurementList = [];
    $evidencesFound = [];
}

$evidenceRows = [];
$safeStr = function ($val) {
    if (is_array($val)) return implode(', ', array_filter(array_map('strval', $val)));
    return (string)($val ?? '');
};
$normalizeEvidence = function ($row) use ($safeStr) {
    return [
        'detail' => trim($safeStr(getVal($row, 'detail') ?: getVal($row, 'item') ?: getVal($row, 'description'))),
        'qty' => trim($safeStr(getVal($row, 'quantity_val') ?: getVal($row, 'qty') ?: getVal($row, 'quantity'))),
        'position' => trim($safeStr(getVal($row, 'area_found') ?: getVal($row, 'position') ?: getVal($row, 'area'))),
    ];
};

// สำหรับคดีทรัพย์/ระเบิด: ใช้ measurements เป็นแหล่งหลัก (ตาราง "บันทึกการตรวจเก็บวัตถุพยาน")
$evCaseType = getVal($gen, 'case_type');
$evPropertyBombTypes = ['theft', 'snatch', 'robbery', 'bomb'];
$evUsesMeasurements = in_array($evCaseType, $evPropertyBombTypes) && !empty($measurementList);

if ($evUsesMeasurements) {
    // คดีทรัพย์/ระเบิด: measurements เป็นหลัก, evidences เติมช่องว่าง
    foreach ($measurementList as $ms) {
        $evidenceRows[] = $normalizeEvidence($ms);
    }
    foreach ($evidenceList as $idx => $ev) {
        if (isset($ev['hidden']) && $ev['hidden'] === true) continue;
        $e = $normalizeEvidence($ev);
        if (isset($evidenceRows[$idx])) {
            if ($evidenceRows[$idx]['qty'] === '' && $e['qty'] !== '') $evidenceRows[$idx]['qty'] = $e['qty'];
            if ($evidenceRows[$idx]['position'] === '' && $e['position'] !== '') $evidenceRows[$idx]['position'] = $e['position'];
        }
    }
} else {
    // คดีอื่น ๆ: evidences เป็นหลัก, measurements/evidences_found เติมช่องว่าง
    foreach ($evidenceList as $ev) {
        if (isset($ev['hidden']) && $ev['hidden'] === true) continue;
        $evidenceRows[] = $normalizeEvidence($ev);
    }

    foreach ($measurementList as $idx => $ms) {
        $m = $normalizeEvidence($ms);
        if (isset($evidenceRows[$idx])) {
            if ($evidenceRows[$idx]['detail'] === '' && $m['detail'] !== '') $evidenceRows[$idx]['detail'] = $m['detail'];
            if ($evidenceRows[$idx]['qty'] === '' && $m['qty'] !== '') $evidenceRows[$idx]['qty'] = $m['qty'];
            if ($evidenceRows[$idx]['position'] === '' && $m['position'] !== '') $evidenceRows[$idx]['position'] = $m['position'];
        } else {
            $evidenceRows[] = $m;
        }
    }

    foreach ($evidencesFound as $idx => $ef) {
        $f = $normalizeEvidence($ef);
        if (isset($evidenceRows[$idx])) {
            if ($evidenceRows[$idx]['detail'] === '' && $f['detail'] !== '') $evidenceRows[$idx]['detail'] = $f['detail'];
            if ($evidenceRows[$idx]['qty'] === '' && $f['qty'] !== '') $evidenceRows[$idx]['qty'] = $f['qty'];
            if ($evidenceRows[$idx]['position'] === '' && $f['position'] !== '') $evidenceRows[$idx]['position'] = $f['position'];
        } else {
            $evidenceRows[] = $f;
        }
    }
}

$evidenceRowsHtml = '';
$evNo = 0;
$maxRowsPerPage = 10; // จำกัดจำนวนแถวต่อหน้า
$filteredEvidence = [];
foreach ($evidenceRows as $er) {
    if ($er['detail'] === '' && $er['qty'] === '' && $er['position'] === '') continue;
    $filteredEvidence[] = $er;
}

// แสดงเฉพาะ 10 แถวแรกในหน้าแรก
foreach ($filteredEvidence as $idx => $er) {
    if ($idx >= $maxRowsPerPage) break;
    $evNo++;
    $evidenceRowsHtml .= '<tr>';
    $evidenceRowsHtml .= '<td>' . $evNo . '</td>';
    $evidenceRowsHtml .= '<td style="text-align:left;padding-left:4px;">' . htmlspecialchars($er['detail']) . '</td>';
    $evidenceRowsHtml .= '<td>' . htmlspecialchars($er['qty']) . '</td>';
    $evidenceRowsHtml .= '<td style="text-align:left;padding-left:4px;">' . htmlspecialchars($er['position']) . '</td>';
    $evidenceRowsHtml .= '</tr>';
}

// เก็บแถวที่เหลือสำหรับหน้าถัดไป (ถ้ามี)
$remainingEvidence = array_slice($filteredEvidence, $maxRowsPerPage);
$hasMoreEvidence = count($remainingEvidence) > 0;

// ==========================================
// 6. CHAIN OF CUSTODY TABLE
// ==========================================
$custodySaved = is_array($evidenceForm['custody'] ?? null) ? $evidenceForm['custody'] : [];
$inspectors = $gen['inspectors'] ?? [];
$custodyRowsHtml = '';
$minCustodyRows = 5;
$custodyCount = 0;

// ใช้ข้อมูล custody ที่ user กรอกเอง (evidence_form.custody) เป็นหลัก
// ถ้ามีข้อมูลจริง (from_name หรือ to_name ไม่ว่าง) ให้ใช้ตัวนี้
$hasSavedCustody = false;
foreach ($custodySaved as $cs) {
    if (!empty(trim(getVal($cs, 'from_name', ''))) || !empty(trim(getVal($cs, 'to_name', '')))) {
        $hasSavedCustody = true;
        break;
    }
}

if ($hasSavedCustody) {
    // ใช้ข้อมูลที่ user กรอกเอง
    foreach ($custodySaved as $cs) {
        $fromName = trim(getVal($cs, 'from_name', ''));
        $toName   = trim(getVal($cs, 'to_name', ''));
        $custDate = getVal($cs, 'date', '');
        $remark   = trim(getVal($cs, 'remark', ''));

        // ข้ามแถวว่างทั้งหมด
        if ($fromName === '' && $toName === '' && $custDate === '' && $remark === '') continue;

        $custodyRowsHtml .= '<tr>';
        $custodyRowsHtml .= '<td>' . ($custodyCount + 1) . '</td>';
        $custodyRowsHtml .= '<td style="text-align:left;padding-left:4px;">' . htmlspecialchars($fromName) . '</td>';
        $custodyRowsHtml .= '<td style="text-align:left;padding-left:4px;">' . htmlspecialchars($toName) . '</td>';
        $custodyRowsHtml .= '<td>' . htmlspecialchars($custDate ? thaiDateShort($custDate) : '') . '</td>';
        $custodyRowsHtml .= '<td style="text-align:left;padding-left:4px;">' . htmlspecialchars($remark) . '</td>';
        $custodyRowsHtml .= '</tr>';
        $custodyCount++;
    }
} elseif (!empty($inspectors)) {
    // Fallback: auto-gen จาก inspectors (รองรับกรณียังไม่เคยบันทึก custody form)
    for ($i = 0; $i < count($inspectors); $i++) {
        $inspector = $inspectors[$i];
        $inspId = getVal($inspector, 'id', getVal($inspector, 'user_id'));
        $inspName = '';
        $inspPos = '';

        if (!empty($inspId) && isset($userMap[$inspId])) {
            $inspName = $userMap[$inspId];
            $inspPos = $userPosMap[$inspId] ?? '';
        } elseif (!empty($inspId)) {
            $inspName = getVal($inspector, 'name', '');
        }

        if (empty($inspPos)) {
            $inspPos = getVal($inspector, 'position', '');
        }

        $fromName = '';
        $toName = '';

        if ($i === 0 && !empty($collectorName)) {
            $fromName = $collectorName;
            $toName = $inspName;
        } elseif ($i > 0) {
            $prevInsp = $inspectors[$i - 1];
            $prevId = getVal($prevInsp, 'id', getVal($prevInsp, 'user_id'));
            $fromName = isset($userMap[$prevId]) ? $userMap[$prevId] : getVal($prevInsp, 'name', '');
            $toName = $inspName;
        }

        $custodyDate = thaiDateShort(getVal($inspector, 'date', getVal($gen, 'inspection_datetime')));
        $remark = getVal($inspector, 'remark', '');

        $custodyRowsHtml .= '<tr>';
        $custodyRowsHtml .= '<td>' . ($i + 1) . '</td>';
        $custodyRowsHtml .= '<td style="text-align:left;padding-left:4px;">' . htmlspecialchars($fromName) . '</td>';
        $custodyRowsHtml .= '<td style="text-align:left;padding-left:4px;">' . htmlspecialchars($toName) . '</td>';
        $custodyRowsHtml .= '<td>' . htmlspecialchars($custodyDate) . '</td>';
        $custodyRowsHtml .= '<td style="text-align:left;padding-left:4px;">' . htmlspecialchars($remark) . '</td>';
        $custodyRowsHtml .= '</tr>';
        $custodyCount++;
    }
}

// เติมแถวว่าง
for ($i = $custodyCount; $i < $minCustodyRows; $i++) {
    $custodyRowsHtml .= '<tr>';
    $custodyRowsHtml .= '<td>' . ($i + 1) . '</td>';
    $custodyRowsHtml .= '<td>&nbsp;</td>';
    $custodyRowsHtml .= '<td>&nbsp;</td>';
    $custodyRowsHtml .= '<td>&nbsp;</td>';
    $custodyRowsHtml .= '<td>&nbsp;</td>';
    $custodyRowsHtml .= '</tr>';
}

// ==========================================
// 7. CONDITION (สภาพของกลาง)
// ==========================================
// evidence_form.condition first, then evidence_condition fallback
$evfCondition = $evidenceForm['condition'] ?? [];
$condition = $data['evidence_condition'] ?? [];

if (!empty($evfCondition)) {
    $condGood = getVal($evfCondition, 'sealed', false);
    $condOther = getVal($evfCondition, 'other', false);
    $condOtherText = getVal($evfCondition, 'other_text', '');
} elseif (!empty($condition)) {
    $condGood = getVal($condition, 'sealed', false);
    $condOther = getVal($condition, 'other', false);
    $condOtherText = getVal($condition, 'other_text', '');
} else {
    // default
    $condGood = true;
    $condOther = false;
    $condOtherText = '';
}

$chkCondGood = renderCheckbox($condGood);
$chkCondOther = renderCheckbox($condOther);

// ==========================================
// 8. QR CODE DATA
// ==========================================
$rawCaseNo = '';
if (!empty($rnRow['receiveNoti_No'])) {
    $rawCaseNo = trim((string)$rnRow['receiveNoti_No']);
} elseif (!empty(getVal($gen, 'document_no'))) {
    $rawCaseNo = trim((string)getVal($gen, 'document_no'));
}

$qrData = '';
if ($incident_id > 0) {
    $qrCasePart = $rawCaseNo !== '' ? $rawCaseNo : 'NA';
    // qrcodejs ล้มเมื่อ payload มีตัวอักษรไทย — แปลงเป็นค่า ASCII สั้นๆ
    $thaiDigits = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
    $qrCasePart = str_replace($thaiDigits, ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $qrCasePart);
    $qrCasePart = preg_replace('/[^\x20-\x7E]/', '', $qrCasePart);
    $qrCasePart = substr(trim($qrCasePart), 0, 40);
    if ($qrCasePart === '') {
        $qrCasePart = 'NA';
    }
    $qrData = 'CSIMS-EV|' . $qrCasePart . '|ID:' . $incident_id;
    if (strlen($qrData) > 80) {
        $qrData = 'CSIMS-EV|ID:' . $incident_id;
    }
}

// ==========================================
// 8.5 LOGO BASE64 (embed ตรงเพื่อไม่ต้องโหลดจาก server)
// ==========================================
$logoBase64 = '';
$logoPath = $_SERVER['DOCUMENT_ROOT'] . '/csims/images/icon-forensic-police.png';
if (file_exists($logoPath)) {
    $logoData = file_get_contents($logoPath);
    if ($logoData !== false) {
        $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
    }
}

// ==========================================
// 9. READ HTML TEMPLATE & REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_evidence_preview.html');

if ($htmlTemplate === false) {
    die("Error: ไม่สามารถอ่านไฟล์ template ได้");
}

// สร้าง HTML สำหรับหน้าเพิ่มเติม (ถ้ามีแถวเกิน)
$extraPagesHtml = '';
if ($hasMoreEvidence) {
    $pageNum = 1;
    $chunks = array_chunk($remainingEvidence, $maxRowsPerPage);
    foreach ($chunks as $chunk) {
        $pageNum++;
        $extraPagesHtml .= '
<!-- ==================== EXTRA PAGE ' . $pageNum . ' ==================== -->
<div class="page">
    <div class="sidebar">
        <img src="' . $logoBase64 . '" alt="Logo" class="logo-img">
        <div class="text-group">
            <div class="vertical-text">พิสูจน์หลักฐานตำรวจ</div>
            <div class="vertical-text-en">Forensic Police</div>
        </div>
    </div>
    <div class="content">
        <div class="ev-title">วัตถุพยาน</div>
        <div class="ev-subtitle">EVIDENCE</div>
        <div class="section-label">ลักษณะ / จำนวน / ตำแหน่ง วัตถุพยานที่ตรวจพบ (ต่อ)</div>
        <table class="evidence-table">
            <thead>
                <tr>
                    <th style="width:8%;">ลำดับ</th>
                    <th style="width:50%;">รายละเอียดวัตถุพยาน</th>
                    <th style="width:15%;">จำนวน</th>
                    <th style="width:27%;">ตำแหน่งที่พบ</th>
                </tr>
            </thead>
            <tbody>';
        foreach ($chunk as $er) {
            $evNo++;
            $extraPagesHtml .= '<tr>';
            $extraPagesHtml .= '<td>' . $evNo . '</td>';
            $extraPagesHtml .= '<td style="text-align:left;padding-left:4px;">' . htmlspecialchars($er['detail']) . '</td>';
            $extraPagesHtml .= '<td>' . htmlspecialchars($er['qty']) . '</td>';
            $extraPagesHtml .= '<td style="text-align:left;padding-left:4px;">' . htmlspecialchars($er['position']) . '</td>';
            $extraPagesHtml .= '</tr>';
        }
        $extraPagesHtml .= '
            </tbody>
        </table>
        <div class="qr-section">
            <div id="qrcode_extra_' . $pageNum . '"></div>
        </div>
    </div>
</div>';
    }
}

$replacements = [
    '{{station_name}}'       => htmlspecialchars($stationName),
    '{{case_no}}'            => htmlspecialchars($caseNo),
    '{{location_detail_line1}}'    => htmlspecialchars($locationLine1),
    '{{location_detail_line2}}'    => htmlspecialchars($locationLine2),
    '{{location_line2_hidden}}'    => $locationLine2HiddenClass,
    '{{incident_date}}'      => htmlspecialchars($incidentDate),
    '{{incident_time}}'      => htmlspecialchars($incidentTime),
    '{{victim_rows}}'        => $victimRowsHtml,
    '{{collect_date}}'       => htmlspecialchars($collectDate),
    '{{collect_time}}'       => htmlspecialchars($collectTime),
    '{{collector_name}}'     => htmlspecialchars($collectorName),
    '{{evidence_rows}}'      => $evidenceRowsHtml,
    '{{custody_rows}}'       => $custodyRowsHtml,
    '{{chk_condition_good}}' => $chkCondGood,
    '{{chk_condition_other}}'=> $chkCondOther,
    '{{condition_other_text}}'=> htmlspecialchars($condOtherText),
    '{{qr_data}}'            => htmlspecialchars($qrData),
    '{{logo_base64}}'        => $logoBase64,
    '{{extra_evidence_pages}}' => $extraPagesHtml,
];

$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

echo $htmlContent;
