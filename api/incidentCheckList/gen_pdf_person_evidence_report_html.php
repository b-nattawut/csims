<?php
/**
 * gen_pdf_person_evidence_report_html.php
 * Generate PDF รายงานการตรวจเก็บวัตถุพยานที่บุคคล (ร่างรายงาน)
 *
 * รับ parameter: incident_id (GET)
 * ดึงข้อมูลจาก: incident_checklist_transaction.incident_report_data (JSON - top level)
 * ใช้ template: form_person_evidence_report_preview.html + str_replace pattern
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../helpers/report_no.php';
require_once __DIR__ . '/lab_unit_helper.php';

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

function thaiDate($dateStr) {
    if (empty($dateStr)) return '';
    $ts = strtotime($dateStr);
    if ($ts === false) return $dateStr;
    $months = [null,'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    return date('j',$ts).' '.$months[date('n',$ts)].' '.(date('Y',$ts)+543);
}

function thaiTime($datetime) {
    if (empty($datetime)) return '';
    if (strlen($datetime) <= 5) return $datetime;
    $ts = strtotime($datetime);
    return ($ts !== false) ? date('H:i', $ts) : $datetime;
}

function h($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

function getVal($arr, $key, $default = '') {
    if (!is_array($arr)) return $default;
    return isset($arr[$key]) && $arr[$key] !== null ? $arr[$key] : $default;
}

function renderCheckbox($condition) {
    return $condition ? '✓' : '';
}

function parseDateParts($dateStr) {
    if (empty($dateStr)) return ['day' => '', 'month' => '', 'year' => ''];
    $ts = strtotime($dateStr);
    if ($ts === false) return ['day' => '', 'month' => '', 'year' => ''];
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return [
        'day'   => date('j', $ts),
        'month' => $months[(int)date('n', $ts)] ?? '',
        'year'  => date('Y', $ts) + 543
    ];
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

// Use report data (person_evidence key) or fallback to checklist data
$data = [];
if ($reportData && isset($reportData['person_evidence']) && is_array($reportData['person_evidence'])) {
    $data = $reportData['person_evidence'];
} elseif ($reportData && is_array($reportData) && !isset($reportData['person_evidence'])) {
    // Legacy: top-level report data (before keyed approach)
    $data = $reportData;
} elseif ($checklistData && is_array($checklistData)) {
    $data = $checklistData;
}

// ==========================================
// MAP FLAT pe_* FIELDS → NESTED STRUCTURE
// (when data comes from the new report form with pe_ prefixed flat fields)
// ==========================================
if (isset($data['pe_receive_date']) || isset($data['pe_person_name[]'])) {
    $mapped = [];

    // general_info
    $mapped['general_info'] = [
        'receive_date'    => $data['pe_receive_date'] ?? '',
        'receive_time'    => $data['pe_receive_time'] ?? '',
        'report_date'     => $data['pe_receive_date'] ?? '',
        'report_time'     => $data['pe_receive_time'] ?? '',
        'document_no'     => $data['pe_document_no'] ?? '',
        'document_date'   => $data['pe_document_date'] ?? '',
        'notify_method'   => $data['pe_notify_method[]'] ?? [],
        'notify_method_other_text' => $data['pe_notify_method_other_text'] ?? '',
        'source_station'  => $data['pe_police_station'] ?? '',
        'police_station'  => $data['pe_police_station'] ?? '',
        'case_no'         => $data['pe_case_no'] ?? '',
        'incident_location' => $data['pe_incident_location'] ?? '',
        'location_detail' => $data['pe_incident_location'] ?? '',
        'incident_date'   => $data['pe_incident_date'] ?? '',
        'incident_time'   => $data['pe_incident_time'] ?? '',
        'investigator_name' => $data['pe_investigator_name'] ?? '',
        'unit_name'       => $data['pe_agency_name'] ?? '',
    ];

    // persons
    $mapped['persons'] = [];
    $pNames = $data['pe_person_name[]'] ?? [];
    $pPrefixes = $data['pe_person_prefix[]'] ?? [];
    if (!is_array($pNames)) $pNames = [$pNames];
    if (!is_array($pPrefixes)) $pPrefixes = [$pPrefixes];
    foreach ($pNames as $i => $name) {
        if (!empty($name)) {
            $mapped['persons'][] = [
                'prefix' => $pPrefixes[$i] ?? '',
                'name'   => $name,
            ];
        }
    }

    // purpose
    $mapped['purpose'] = [
        'detail' => $data['pe_purpose_detail'] ?? '',
    ];

    // inspection
    $mapped['inspection'] = [
        'location' => $data['pe_inspect_location'] ?? '',
        'date'     => $data['pe_inspect_date'] ?? '',
        'time'     => $data['pe_inspect_time'] ?? '',
    ];

    // person_info
    $mapped['person_info'] = [];
    $piFullnames = $data['pe_pi_fullname[]'] ?? [];
    $piPrefixes = $data['pe_pi_prefix[]'] ?? [];
    $piIdCards = $data['pe_pi_id_card[]'] ?? [];
    $piPassports = $data['pe_pi_passport[]'] ?? [];
    $piHeights = $data['pe_pi_height[]'] ?? [];
    $piAges = $data['pe_pi_age[]'] ?? [];
    $piSkins = $data['pe_pi_skin[]'] ?? [];
    $piHands = $data['pe_pi_hand[]'] ?? [];
    $piFeatures = $data['pe_pi_feature[]'] ?? [];
    if (!is_array($piFullnames)) $piFullnames = [$piFullnames];
    foreach ($piFullnames as $i => $fn) {
        if (!empty($fn)) {
            $mapped['person_info'][] = [
                'prefix'   => is_array($piPrefixes) ? ($piPrefixes[$i] ?? '') : $piPrefixes,
                'fullname' => $fn,
                'id_card'  => is_array($piIdCards) ? ($piIdCards[$i] ?? '') : $piIdCards,
                'passport' => is_array($piPassports) ? ($piPassports[$i] ?? '') : $piPassports,
                'height'   => is_array($piHeights) ? ($piHeights[$i] ?? '') : $piHeights,
                'age'      => is_array($piAges) ? ($piAges[$i] ?? '') : $piAges,
                'skin'     => is_array($piSkins) ? ($piSkins[$i] ?? '') : $piSkins,
                'hand'     => is_array($piHands) ? ($piHands[$i] ?? '') : $piHands,
                'feature'  => is_array($piFeatures) ? ($piFeatures[$i] ?? '') : $piFeatures,
            ];
        }
    }

    // evidences
    $mapped['evidences'] = [];
    $evDetails = $data['pe_ev_detail[]'] ?? [];
    $evQtys = $data['pe_ev_qty[]'] ?? [];
    $evLabs = $data['pe_ev_lab_unit[]'] ?? [];
    if (!is_array($evDetails)) $evDetails = [$evDetails];
    foreach ($evDetails as $i => $detail) {
        if (!empty($detail)) {
            $mapped['evidences'][] = [
                'detail'   => $detail,
                'qty'      => is_array($evQtys) ? ($evQtys[$i] ?? '') : $evQtys,
                'lab_unit' => is_array($evLabs) ? ($evLabs[$i] ?? '') : $evLabs,
            ];
        }
    }

    // evidence_handling
    $mapped['evidence_handling'] = [
        'witness_name'           => $data['pe_witness_name'] ?? '',
        'witness_form'           => $data['pe_witness_form'] ?? '',
        'witness_detail'         => $data['pe_witness_detail'] ?? '',
        'handover_method_checks' => $data['pe_handover_method_checks[]'] ?? [],
        'handover_method'        => '',
        'handover_item_ref'      => $data['pe_handover_item_ref'] ?? '',
        'handover_to'            => $data['pe_handover_to'] ?? '',
        'handover_purpose'       => $data['pe_handover_purpose'] ?? '',
    ];

    // signer
    $mapped['signer'] = [
        'name'     => $data['pe_signer_name'] ?? '',
        'fullname' => $data['pe_signer_name'] ?? '',
        'position' => $data['pe_signer_position'] ?? '',
    ];

    // handover (sign date)
    $mapped['handover'] = [
        'inspection_end_date' => $data['pe_sign_date'] ?? '',
    ];

    // inspectors
    $mapped['inspectors'] = [];
    $insNames = $data['pe_inspector_name[]'] ?? [];
    $insPositions = $data['pe_inspector_position[]'] ?? [];
    if (!is_array($insNames)) $insNames = [$insNames];
    if (!is_array($insPositions)) $insPositions = [$insPositions];
    foreach ($insNames as $i => $iname) {
        if (!empty($iname)) {
            $mapped['inspectors'][] = [
                'name'     => $iname,
                'position' => is_array($insPositions) ? ($insPositions[$i] ?? '') : $insPositions,
            ];
        }
    }

    // pdf_form overrides
    $mapped['pdf_form'] = [
        'report_ref'    => $data['pe_report_ref'] ?? '',
        'report_year'   => $data['pe_report_year'] ?? '',
        'center_name'   => $data['pe_center_name'] ?? '',
        'province_name' => $data['pe_province_name'] ?? '',
    ];

    $data = $mapped;
}

// ==========================================
// USER MAP
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
            $agencyName = 'นวท.(สบ ' . $rv . ') กสก.พฐก.';
        } elseif ($rt === 'spt') {
            $agencyName = 'ศพฐ ' . $rv;
            $agencyCenterName = $agencyName;
        } elseif ($rt === 'ptjv') {
            $agencyName = 'พฐ.จว.' . ($provinceMap[strval($rv)] ?? $rv);
            $agencyProvinceName = $agencyName;
        }
    }
} catch (Exception $e) { /* ignore */ }

// ==========================================
// 3. EXTRACT DATA
// ==========================================
$gen = $data['general_info'] ?? [];
$purpose = $data['purpose'] ?? [];
$inspection = $data['inspection'] ?? [];
$persons = $data['persons'] ?? [];
$personInfo = $data['person_info'] ?? [];
$evidences = $data['evidences'] ?? [];
$evidenceHandling = $data['evidence_handling'] ?? [];
$signer = $data['signer'] ?? [];
$handover = $data['handover'] ?? [];
$pdfForm = $data['pdf_form'] ?? [];
$inspectorList = $data['inspectors'] ?? [];

// ==========================================
// REPORT NO & YEAR
// (Always from checklist general_info, like fire report)
// ==========================================
$ckGen = $checklistData['general_info'] ?? [];
$rawReportNo = getVal($ckGen, 'report_no', getVal($ckGen, 'document_no'));
// If still empty, try from current $gen (may come from report data)
if (empty($rawReportNo)) $rawReportNo = getVal($gen, 'report_no');
// If still empty, fallback to receiveNotiReportNo from DB
if (empty($rawReportNo)) {
    try {
        $stmtRpt = $pdo->prepare("SELECT receiveNotiReportNo FROM rn_ReceiveNoti WHERE id = ? LIMIT 1");
        $stmtRpt->execute([$incident_id]);
        $rptRow = $stmtRpt->fetch(PDO::FETCH_ASSOC);
        if ($rptRow && !empty($rptRow['receiveNotiReportNo'])) {
            $rawReportNo = $rptRow['receiveNotiReportNo'];
        }
    } catch (Exception $e) { /* ignore */ }
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
// Override from pdf_form if present
if (!empty(getVal($pdfForm, 'report_ref'))) $report_no = getVal($pdfForm, 'report_ref');
if (!empty(getVal($pdfForm, 'report_year'))) $report_year_short = getVal($pdfForm, 'report_year');
// แปลงเป็นภาษาไทยเสมอ (รองรับทั้งเลขรายงานและเลขเอกสารเต็ม) — idempotent
$report_no = smartThaiReportOrDoc($report_no);

// ==========================================
// GENERAL INFO (Section 1)
// ==========================================
$receive_date = thaiDateFull(getVal($gen, 'receive_date'));
$receive_time = getVal($gen, 'receive_time');

// Notify method checkboxes
$channel = getVal($gen, 'notify_method');
if ($channel === '' || $channel === null || $channel === []) {
    $channel = $gen['report_channel'] ?? [];
}
$notify_other_text = getVal($gen, 'notify_method_other_text');

// ★ ข้อ 7: ให้ "วิธีการรับแจ้ง" ออกมาเป็นข้อความไทย ไม่ใช่ช่องติ๊กเปล่า
require_once __DIR__ . '/notify_method_helper.php';
$notify_method_text = notifyMethodToText($channel, (string)$notify_other_text);

if (is_array($channel)) {
    $chk_notify_letter = renderCheckbox(in_array('ทางหนังสือ', $channel) || in_array('ตามหนังสือ', $channel) || in_array('หนังสือ', $channel) || in_array('letter', $channel) || in_array('document', $channel));
    $chk_notify_phone = renderCheckbox(in_array('ทางโทรศัพท์', $channel) || in_array('โทรศัพท์', $channel) || in_array('phone', $channel));
    $chk_notify_radio = renderCheckbox(in_array('ทางวิทยุสื่อสาร', $channel) || in_array('วิทยุสื่อสาร', $channel) || in_array('radio', $channel));
    $chk_notify_other = renderCheckbox(in_array('อื่นๆ', $channel) || in_array('other', $channel));
} else {
    $chk_notify_letter = renderCheckbox($channel == 'ทางหนังสือ' || $channel == 'ตามหนังสือ' || $channel == 'หนังสือ' || $channel == 'letter');
    $chk_notify_phone = renderCheckbox($channel == 'ทางโทรศัพท์' || $channel == 'โทรศัพท์' || $channel == 'phone');
    $chk_notify_radio = renderCheckbox($channel == 'ทางวิทยุสื่อสาร' || $channel == 'วิทยุสื่อสาร' || $channel == 'radio');
    $chk_notify_other = renderCheckbox($channel == 'อื่นๆ' || $channel == 'other');
}

$police_station = getVal($gen, 'police_station');
if (empty($police_station)) $police_station = getVal($gen, 'source_station');

$document_no = getVal($gen, 'document_no');
$raw_doc_date = getVal($gen, 'document_date');
$document_date = !empty($raw_doc_date) ? (strtotime($raw_doc_date) ? thaiDateFull($raw_doc_date) : $raw_doc_date) : '';

$case_no = convertDocNoToThai(getVal($gen, 'case_no'));
$incident_location = getVal($gen, 'incident_location');
if (empty($incident_location)) $incident_location = getVal($gen, 'location_detail');

$incident_date = thaiDateFull(getVal($gen, 'incident_date'));
$incident_time = getVal($gen, 'incident_time');
$investigator_name = getVal($gen, 'investigator_name');

$center_name = getVal($pdfForm, 'center_name');
if (empty($center_name)) $center_name = $agencyCenterName;

$province_name = getVal($pdfForm, 'province_name');
if (empty($province_name)) $province_name = $agencyProvinceName;

// ==========================================
// PERSON ITEM ROWS (Section 1 - รายการบุคคล)
// ==========================================
function toThaiNum($num) {
    return (string)$num;
}

$person_item_rows = '';
if (!empty($persons) && is_array($persons)) {
    foreach ($persons as $idx => $person) {
        $no = $idx + 1;
        $prefix = h(getVal($person, 'prefix'));
        $name = h(getVal($person, 'name'));
        $displayName = '';
        if (!empty($prefix) && !empty($name)) {
            $displayName = $prefix . ' ' . $name;
        } elseif (!empty($name)) {
            $displayName = $name;
        }
        $person_item_rows .= '<div class="fr i2"><span class="fl">1.' . $no . ' (' . (empty($prefix) ? 'นาย/นาง/นางสาว/อื่นๆ' : $prefix) . ')</span><span class="fd" style="margin-left:4px;">' . h($name) . '</span></div>' . "\n";
    }
}
if (empty($person_item_rows)) {
    $person_item_rows .= '<div class="fr i2"><span class="fl">1.1 (นาย/นาง/นางสาว/อื่นๆ)</span><span class="fd" style="margin-left:4px;">&nbsp;</span></div>' . "\n";
    $person_item_rows .= '<div class="fr i2"><span class="fl">1.2</span><span class="fd" style="margin-left:4px;">&nbsp;</span></div>' . "\n";
}

// ==========================================
// PURPOSE (Section 2)
// ==========================================
$purpose_detail = getVal($purpose, 'detail');

// ==========================================
// INSPECTION (Section 3)
// ==========================================
$inspect_location = getVal($inspection, 'location');
$inspect_date = thaiDateFull(getVal($inspection, 'date'));
$inspect_time = getVal($inspection, 'time');

if (empty($inspect_date)) {
    $raw = getVal($gen, 'inspection_datetime');
    if (!empty($raw)) {
        $parts = explode('T', $raw, 2);
        $inspect_date = thaiDateFull($parts[0] ?? '');
        $inspect_time = $parts[1] ?? '';
    }
}

// ==========================================
// PERSON INFO ROWS (Section 3.1)
// ==========================================
$person_info_rows = '';
if (!empty($personInfo) && is_array($personInfo)) {
    foreach ($personInfo as $idx => $pi) {
        $no = $idx + 1;
        $prefix = h(getVal($pi, 'prefix'));
        $fullname = h(getVal($pi, 'fullname'));
        $idCard = h(getVal($pi, 'id_card'));
        $passport = h(getVal($pi, 'passport'));
        $height = h(getVal($pi, 'height'));
        $age = h(getVal($pi, 'age'));
        $skin = h(getVal($pi, 'skin'));
        $hand = h(getVal($pi, 'hand'));
        $feature = h(getVal($pi, 'feature'));

        // Build description line
        $descParts = [];
        if (!empty($idCard)) $descParts[] = 'เลขบัตรประชาชน ' . $idCard;
        if (!empty($passport)) $descParts[] = 'หนังสือเดินทาง ' . $passport;
        if (!empty($height)) $descParts[] = 'ความสูง ' . $height . ' ซม.';
        if (!empty($age)) $descParts[] = 'อายุ ' . $age . ' ปี';
        if (!empty($skin)) $descParts[] = 'สีผิว ' . $skin;
        if (!empty($hand)) $descParts[] = 'มือที่ถนัด ' . $hand;
        if (!empty($feature)) $descParts[] = $feature;
        $descLine = implode(' / ', $descParts);

        $displayPrefix = !empty($prefix) ? $prefix : 'นาย/นาง/นางสาว/อื่นๆ';
        $person_info_rows .= '<div class="fr i2"><span class="fl">3.1.' . $no . ' บุคคลที่ ' . $no . ' (' . $displayPrefix . ')</span><span class="fd" style="margin-left:4px;">' . $fullname . '</span></div>' . "\n";
        if (!empty($descParts)) {
            // Split description into separate lines to avoid overlap
            $descGroups = array_chunk($descParts, 4);
            foreach ($descGroups as $group) {
                $person_info_rows .= '<div class="fr i2" style="padding-left:60px;"><span class="fd-full">' . implode(' / ', $group) . '</span></div>' . "\n";
            }
        }
    }
}
if (empty($person_info_rows)) {
    $person_info_rows .= '<div class="fr i2"><span class="fl">3.1.1 บุคคลที่ 1 (นาย/นาง/นางสาว/อื่นๆ)</span><span class="fd" style="margin-left:4px;">&nbsp;</span></div>' . "\n";
    $person_info_rows .= '<div class="fr i2" style="padding-left:60px;"><span class="fd-full">&nbsp;</span></div>' . "\n";
    $person_info_rows .= '<div class="fr i2"><span class="fl">3.1.2</span><span class="fd" style="margin-left:4px;">&nbsp;</span></div>' . "\n";
}

// ==========================================
// EVIDENCE DETAIL ROWS (Section 3.2)
// ==========================================
$labUnitMap = [
    'bio_dna'     => 'กลุ่มงานตรวจชีววิทยา',
    'chemical'    => 'กลุ่มงานตรวจทางเคมีฟิสิกส์',
    'fingerprint' => 'กลุ่มงานตรวจลายนิ้วมือแฝง',
    'drug'        => 'กลุ่มงานตรวจยาเสพติด',
    'gun'         => 'กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน',
    'document'    => 'กลุ่มงานตรวจเอกสาร',
    'digital'     => 'กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล',
    'computer'    => 'กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์',
];

$evidence_detail_rows = '';
$evidence_detail_rows_continued = '';
if (!empty($evidences) && is_array($evidences)) {
    foreach ($evidences as $idx => $ev) {
        $no = $idx + 1;
        $detail = h(getVal($ev, 'detail'));
        if (empty($detail)) $detail = h(getVal($ev, 'item'));
        $qty = h(getVal($ev, 'qty'));
        $labUnit = getVal($ev, 'lab_unit');
        $labUnitText = labUnitsToText($labUnit);

        $labLine = '';
        if (!empty($labUnitText)) {
            $labLine = ' ส่งตรวจ ' . h($labUnitText);
        }
        $qtyLabDisplay = '';
        if (!empty($qty) || !empty($labLine)) {
            $qtyLabDisplay = (!empty($qty) ? 'จำนวน ' . $qty : '') . $labLine;
        }

        $rowHtml = '<div class="fr i2"><span class="fl">3.2.' . $no . ' ตัวอย่าง</span><span class="fd" style="margin-left:4px;">' . $detail . '</span></div>' . "\n";
        if (!empty($qtyLabDisplay)) {
            $rowHtml .= '<div class="fr i2" style="padding-left:60px;"><span class="fd-full">' . $qtyLabDisplay . '</span></div>' . "\n";
        }

        // Put first 3 items on page 1, rest on page 2
        if ($idx < 3) {
            $evidence_detail_rows .= $rowHtml;
        } else {
            $evidence_detail_rows_continued .= $rowHtml;
        }
    }
}
if (empty($evidence_detail_rows)) {
    $evidence_detail_rows .= '<div class="fr i2"><span class="fl">3.2.1 ตัวอย่าง (เนื้อเยื่อบุกระพุ้งแก้ม /เข่ากับที่...(นาย/นาง/นางสาว/อื่นๆ)...)</span></div>' . "\n";
    $evidence_detail_rows .= '<div class="fr i2" style="padding-left:60px;"><span class="fl">จำนวน</span><span class="fd" style="margin-left:4px;">&nbsp;</span></div>' . "\n";
    $evidence_detail_rows .= '<div class="fr i2"><span class="fl">3.2.2</span><span class="fd" style="margin-left:4px;">&nbsp;</span></div>' . "\n";
}

// ==========================================
// EVIDENCE HANDLING (Section 3.3)
// ==========================================
$witness_name = getVal($evidenceHandling, 'witness_name');
$witness_form = getVal($evidenceHandling, 'witness_form');
$witness_detail = getVal($evidenceHandling, 'witness_detail');

$handover_method_checks = getVal($evidenceHandling, 'handover_method_checks', []);
if (is_string($handover_method_checks)) $handover_method_checks = [$handover_method_checks];
$handover_method = getVal($evidenceHandling, 'handover_method');
$chk_handover_submit = renderCheckbox(in_array('นำส่ง', $handover_method_checks) || $handover_method === 'นำส่ง');
$chk_handover_transfer = renderCheckbox(in_array('ส่งมอบ', $handover_method_checks) || $handover_method === 'ส่งมอบ');

$handover_item_ref = getVal($evidenceHandling, 'handover_item_ref');
$handover_to = getVal($evidenceHandling, 'handover_to');
$handover_purpose = getVal($evidenceHandling, 'handover_purpose');
// Multi-line purpose support
$purposeLines = getVal($evidenceHandling, 'purpose_lines', []);
$purposeFull = getVal($evidenceHandling, 'purpose_full');
if (!empty($purposeFull)) $handover_purpose = $purposeFull;

// ==========================================
// SIGNER
// ==========================================
// Try from handover (receiver/deliverer) or signer data
$signerId = getVal($handover, 'receiver_id');
if (empty($signerId)) $signerId = getVal($signer, 'id');

$signer_name = '';
$signer_position = '';

if (!empty($signerId) && isset($userMap[$signerId])) {
    $signer_name = $userMap[$signerId]['fullname'] ?? '';
    $signer_position = $userMap[$signerId]['position_name'] ?? '';
}

// Override from signer data if present
if (!empty(getVal($signer, 'fullname'))) $signer_name = getVal($signer, 'fullname');
if (!empty(getVal($signer, 'name'))) $signer_name = getVal($signer, 'name');
if (!empty(getVal($signer, 'position'))) $signer_position = getVal($signer, 'position');

// Fallback: try handover receiver position
if (empty($signer_position)) {
    $signer_position = getVal($handover, 'receiver_pos');
}

// Sign date
$signDate = getVal($handover, 'inspection_end_date');
if (empty($signDate)) $signDate = getVal($gen, 'receive_date');
$signDateParts = parseDateParts($signDate);

// ==========================================
// INSPECTOR ROWS
// ==========================================
$inspector_rows_html = '';
if (!empty($inspectorList) && is_array($inspectorList)) {
    foreach ($inspectorList as $idx => $insp) {
        $no = $idx + 1;
        $iName = '';
        $iPos = '';
        if (is_array($insp)) {
            $iName = h($insp['name'] ?? '');
            $iPos = h($insp['position'] ?? '');
        } else {
            $iName = h((string)$insp);
        }
        $posText = !empty($iPos) ? '  ตำแหน่ง ' . $iPos : '';
        $inspector_rows_html .= '<div class="fr i1"><span class="fl">' . toThaiNum($no) . '.</span><span class="fd" style="margin-left:6px;">' . $iName . $posText . '</span></div>' . "\n";
    }
}
// Fill empty rows up to at least 2
for ($i = count($inspectorList ?? []); $i < 2; $i++) {
    $no = $i + 1;
    $inspector_rows_html .= '<div class="fr i1"><span class="fl">' . toThaiNum($no) . '.</span><span class="fd" style="margin-left:6px;"></span><span class="fl" style="margin-left:20px;">ตำแหน่ง</span><span class="fd"></span></div>' . "\n";
}

// ==========================================
// 4. BUILD REPLACEMENTS
// ==========================================
$replacements = [
    '{{report_no}}'         => h($report_no),
    '{{report_year_short}}' => h($report_year_short),
    '{{total_pages}}'       => '2',
    '{{agency_name}}'       => h(!empty($agencyName) ? $agencyName : getVal($gen, 'unit_name')),

    // Section 1 - การรับแจ้งเหตุ
    '{{receive_date}}'      => h($receive_date),
    '{{receive_time}}'      => h($receive_time),
    '{{center_name}}'       => h($center_name),
    '{{province_name}}'     => h($province_name),
    '{{notify_method_text}}' => $notify_method_text,
    '{{chk_notify_letter}}' => $chk_notify_letter,
    '{{chk_notify_phone}}'  => $chk_notify_phone,
    '{{chk_notify_radio}}'  => $chk_notify_radio,
    '{{chk_notify_other}}'  => $chk_notify_other,
    '{{notify_other_text}}'  => h($notify_other_text),
    '{{police_station}}'    => h($police_station),
    '{{document_no}}'       => h($document_no),
    '{{document_date}}'     => h($document_date),
    '{{case_no}}'           => h($case_no),
    '{{incident_location}}' => h($incident_location),
    '{{incident_date}}'     => h($incident_date),
    '{{incident_time}}'     => h($incident_time),
    '{{investigator_name}}' => h($investigator_name),
    '{{person_item_rows}}'  => $person_item_rows,

    // Section 2 - จุดประสงค์
    '{{purpose_detail}}'    => h($purpose_detail),

    // Section 3 - ผลการตรวจ
    '{{inspect_location}}'  => h($inspect_location),
    '{{inspect_date}}'      => h($inspect_date),
    '{{inspect_time}}'      => h($inspect_time),

    // Section 3.1 - ข้อมูลส่วนบุคคล
    '{{person_info_rows}}'  => $person_info_rows,

    // Section 3.2 - รายละเอียดวัตถุพยาน
    '{{evidence_detail_rows}}'           => $evidence_detail_rows,
    '{{evidence_detail_rows_continued}}' => $evidence_detail_rows_continued,

    // Section 3.3 - การดำเนินการเกี่ยวกับวัตถุพยาน
    '{{witness_name}}'           => h($witness_name),
    '{{witness_form}}'           => h($witness_form),
    '{{witness_detail}}'         => h($witness_detail),
    '{{chk_handover_submit}}'    => $chk_handover_submit,
    '{{chk_handover_transfer}}'  => $chk_handover_transfer,
    '{{handover_item_ref}}'      => h($handover_item_ref),
    '{{handover_to}}'            => h($handover_to),
    '{{handover_purpose}}'       => nl2br(h($handover_purpose)),

    // Inspector
    '{{inspector_rows}}'  => $inspector_rows_html,

    // Signature
    '{{signer_name}}'     => h($signer_name),
    '{{signer_position}}' => h($signer_position),
    '{{sign_day}}'         => h($signDateParts['day']),
    '{{sign_month}}'       => h($signDateParts['month']),
    '{{sign_year}}'        => h($signDateParts['year']),
];

// ==========================================
// 5. READ TEMPLATE AND REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_person_evidence_report_preview.html');
if ($htmlTemplate === false) {
    die("Error: ไม่สามารถอ่านไฟล์ template ได้");
}

$htmlContent = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

// ==========================================
// EXPORT สำหรับ DOCX (วัตถุพยานบุคคล)
// ==========================================
if (defined('WORD_DOCX_MODE') && WORD_DOCX_MODE) {
    $GLOBALS['person_evidence_report_export'] = [
        'replacements' => $replacements,
    ];
    return;
}

// ==========================================
// 6. OUTPUT HTML
// ==========================================
if (!defined('WORD_REPORT_CAPTURE')) {
    header('Content-Type: text/html; charset=utf-8');
}
echo $htmlContent;
