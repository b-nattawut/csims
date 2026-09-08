<?php
/**
 * gen_pdf_fingerprint_checklist_html.php
 * Generate print-ready HTML for Fingerprint Checklist (same layout as PDF-style virtual form)
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../helpers/report_no.php';

// ==========================================
// 1. HELPER FUNCTIONS
// ==========================================

function renderCheckbox($condition) {
    return $condition ? '✓' : '';
}

function getVal($arr, $key, $default = '') {
    if (!is_array($arr)) return $default;
    return isset($arr[$key]) && $arr[$key] !== null ? $arr[$key] : $default;
}

function thaiDate($dateStr) {
    if (empty($dateStr)) return '';
    $ts = strtotime($dateStr);
    if ($ts === false) return '';
    $months = [null,'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $year = date('Y', $ts) + 543;
    return date('j', $ts) . ' ' . $months[date('n', $ts)] . ' ' . $year;
}

function thaiTime($dateStr) {
    if (empty($dateStr)) return '';
    $ts = strtotime($dateStr);
    if ($ts === false) return '';
    return date('H:i', $ts);
}

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

// ==========================================
// 2. FETCH DATA
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
    die("Database Error: " . htmlspecialchars($e->getMessage()));
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
// User map for ID → name lookup
// ==========================================
$userMap = [];
try {
    $sqlUser = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname,
                IFNULL(t3.position_name, '-') AS position_name
                FROM user_profile t1
                LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                LEFT JOIN user_position t3 ON t1.position_id = t3.position_id";
    $stmtUser = $pdo->query($sqlUser);
    while ($u = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        $userMap[$u['user_id']] = $u;
    }
} catch (Exception $e) {}

// ==========================================
// 3. EXTRACT DATA
// ==========================================
$gen = $data['general_info'] ?? [];
$pkg = $data['package_storage'] ?? [];
$section6Sets = $data['section6_sets'] ?? [];
$section9 = $data['section9'] ?? [];
$inspectorIds = $data['inspectors'] ?? [];
$photoRecords = $data['photo_records'] ?? [];
$photos = $data['photos'] ?? [];
$photoCaptions = $data['photo_captions'] ?? [];

// ==========================================
// Section 1: การรับแจ้ง
// ==========================================
$receiveDate = thaiDate(getVal($gen, 'receive_date'));
$receiveTime = thaiTime(getVal($gen, 'receive_date') . ' ' . getVal($gen, 'receive_time'));
if (empty($receiveTime) && !empty($gen['receive_time'])) {
    $receiveTime = $gen['receive_time'];
}

$docNo = convertDocNoToThai(getVal($gen, 'doc_no'));
$reportNo = getVal($gen, 'report_no');
$docYear = '';
if (!empty($docNo) && preg_match('/\d+-\d+-(\d{2})-/', $docNo, $ym)) {
    $docYear = $ym[1];
}
if (empty($docYear)) {
    $docYear = substr((date('Y') + 543), -2);
}

$purposes = getVal($gen, 'purposes', []);
if (!is_array($purposes)) $purposes = [];
$chk_purpose_fingerprint = renderCheckbox(in_array('เพื่อตรวจเก็บรอยลายนิ้วมือแฝง', $purposes));
$chk_purpose_other = renderCheckbox(in_array('อื่นๆ', $purposes));
$purposeOtherText = htmlspecialchars(getVal($gen, 'purpose_other_text'));

// Evidence sender lookup
$senderName = '';
$senderPosition = '';
$senderId = getVal($gen, 'evidence_sender');
if (is_numeric($senderId) && isset($userMap[$senderId])) {
    $senderName = $userMap[$senderId]['fullname'];
    $senderPosition = $userMap[$senderId]['position_name'];
} else {
    $senderName = htmlspecialchars($senderId);
    $senderPosition = htmlspecialchars(getVal($gen, 'evidence_sender_position'));
}

// ==========================================
// Section 2: วันเวลา
// ==========================================
$incidentDate = thaiDate(getVal($gen, 'incident_date'));
$incidentTime = getVal($gen, 'incident_time');
$knownDate = thaiDate(getVal($gen, 'known_date'));
$knownTime = getVal($gen, 'known_time');

// ==========================================
// Section 3: วันเวลาที่ตรวจเก็บ
// ==========================================
$collectDate = thaiDate(getVal($gen, 'collect_date'));
$collectTime = getVal($gen, 'collect_time');

// ==========================================
// Section 4: ผู้ตรวจพิสูจน์
// ==========================================
$inspectorsHtml = '';
if (!empty($inspectorIds) && is_array($inspectorIds)) {
    foreach ($inspectorIds as $idx => $inspId) {
        $inspName = '';
        if (is_numeric($inspId) && isset($userMap[$inspId])) {
            $inspName = htmlspecialchars($userMap[$inspId]['fullname']);
        } else {
            $inspName = htmlspecialchars($inspId);
        }
        $num = $idx + 1;
        $inspectorsHtml .= '<div class="fr"><span class="fl">4.' . $num . '</span><span class="fd">' . $inspName . '</span></div>';
    }
} else {
    $inspectorsHtml = '<div class="fr"><span class="fl">4.1</span><span class="fd"></span></div>';
}

// ==========================================
// Section 5: ลักษณะการหีบห่อ
// ==========================================
$pkgTypes = getVal($pkg, 'package_types', []);
if (!is_array($pkgTypes)) $pkgTypes = [];
$sealConditions = getVal($pkg, 'seal_conditions', []);
if (!is_array($sealConditions)) $sealConditions = [];
$collectorType = getVal($pkg, 'collector_type');

$chk_pkg_envelope = renderCheckbox(in_array('บรรจุในซองวัตถุพยาน', $pkgTypes));
$chk_pkg_plastic = renderCheckbox(in_array('พลาสติก', $pkgTypes));
$chk_seal_signed = renderCheckbox(in_array('มีการปิดผนึกพร้อมลงลายมือชื่อกำกับ', $sealConditions));
$chk_seal_detail = renderCheckbox(in_array('เขียนรายละเอียดหน้าซองครบถ้วน', $sealConditions));
$chk_collector_forensic = renderCheckbox($collectorType === 'เก็บโดยเจ้าหน้าที่พิสูจน์หลักฐาน');
$chk_collector_investigator = renderCheckbox($collectorType === 'เก็บโดยพนักงานสอบสวน');
$chk_collector_other = renderCheckbox($collectorType === 'เก็บโดย อื่นๆ');

$storageDate = thaiDate(getVal($pkg, 'storage_date'));
$storageTime = getVal($pkg, 'storage_time');

// ==========================================
// Section 6: ลักษณะวัตถุพยาน (dynamic sets)
// ==========================================
$section6Html = '';
if (empty($section6Sets)) {
    $section6Html = '<div class="fpf-sec-row" style="flex:1; border-bottom:none;">
        <div class="fpf-sec-label"><span class="fpf-sec-num">6.</span><span class="fpf-sec-txt">ลักษณะ<br>วัตถุ<br>พยาน</span></div>
        <div class="fpf-sec-body">
            <div class="fr"><span class="fl-b">จำนวนวัตถุพยานทั้งสิ้น</span><span class="fd-s"></span><span class="fl">รายการ</span></div>
        </div>
    </div>';
} else {
    foreach ($section6Sets as $setIdx => $set) {
        $setNum = $setIdx + 1;
        $isLast = ($setIdx === count($section6Sets) - 1);
        $borderStyle = $isLast ? 'style="flex:1; border-bottom:none;"' : '';
        
        $section6Html .= '<div class="fpf-sec-row" ' . $borderStyle . '>';
        if ($setIdx === 0) {
            $section6Html .= '<div class="fpf-sec-label"><span class="fpf-sec-num">6.</span><span class="fpf-sec-txt">ลักษณะ<br>วัตถุ<br>พยาน</span></div>';
        } else {
            $section6Html .= '<div class="fpf-sec-label"><span class="fpf-sec-num"></span></div>';
        }
        
        $section6Html .= '<div class="fpf-sec-body">';
        
        $evidenceTotal = getVal($set, 'evidence_total', '');
        $section6Html .= '<div class="fr"><span class="fl-b">จำนวนวัตถุพยานทั้งสิ้น</span><span class="fd-s">' . htmlspecialchars($evidenceTotal) . '</span><span class="fl">รายการ</span></div>';
        
        // Key is 'evidence_items' (as collected by JS)
        $items = getVal($set, 'evidence_items', []);
        if (!is_array($items)) $items = [];
        
        foreach ($items as $evIdx => $item) {
            $evNum = $evIdx + 1;
            $section6Html .= '<div class="ev-card">';
            $section6Html .= '<div class="fr"><span class="fl">รายการที่</span><span style="font-weight:600;">' . $evNum . '</span><span class="fl" style="margin-left:4px;">เป็น</span><span class="fd">' . htmlspecialchars(getVal($item, 'description')) . '</span></div>';
            $section6Html .= '<div class="fr"><span class="fl">ขนาด ก/ผคก.</span><span class="fd-s">' . htmlspecialchars(getVal($item, 'width')) . '</span><span class="fl">cm.</span><span class="fl">ย.</span><span class="fd-s">' . htmlspecialchars(getVal($item, 'length')) . '</span><span class="fl">cm.</span><span class="fl">ส.</span><span class="fd-s">' . htmlspecialchars(getVal($item, 'height')) . '</span><span class="fl">cm.</span></div>';
            $section6Html .= '<div class="fr"><span class="fl">จำนวน</span><span class="fd" style="max-width:80px;">' . htmlspecialchars(getVal($item, 'quantity')) . '</span><span class="fl">ป้ายหมายเลข</span><span class="fd">' . htmlspecialchars(getVal($item, 'label_no')) . '</span></div>';
            $section6Html .= '</div>';
        }
        
        $section6Html .= '</div></div>';
    }
}

// ==========================================
// Section 7: วิธีการดำเนินการ (global methods)
// ==========================================
// Methods are in section6_sets[0].global_methods (collected by JS)
$methods = [];
if (!empty($section6Sets) && isset($section6Sets[0]['global_methods'])) {
    $methods = $section6Sets[0]['global_methods'];
}

$methodsHtml = '';
if (!empty($methods) && is_array($methods)) {
    foreach ($methods as $method) {
        $mName = htmlspecialchars(is_array($method) ? getVal($method, 'name') : $method);
        $mDetail = htmlspecialchars(is_array($method) ? getVal($method, 'detail') : '');
        $methodsHtml .= '<div class="method-item"><span class="fl">• ' . $mName . '</span>';
        if (!empty($mDetail)) {
            $methodsHtml .= '<span class="fd">' . $mDetail . '</span>';
        }
        $methodsHtml .= '</div>';
    }
} else {
    $methodsHtml = '<div class="fr"><span class="fd"></span></div>';
}

// ==========================================
// Section 8: การดำเนินการ
// ==========================================
// Action data is stored in section6_sets[0] (the first set)
$actionSet = !empty($section6Sets) ? $section6Sets[0] : [];

// Evidence destination
$evidenceDest = getVal($actionSet, 'action_evidence_dest', []);
if (!is_array($evidenceDest)) $evidenceDest = is_string($evidenceDest) ? [$evidenceDest] : [];
$chk_action_gnf = renderCheckbox(in_array('กนฝ.', $evidenceDest));
$chk_action_evidence_other = renderCheckbox(in_array('อื่นๆ', $evidenceDest));
$action_evidence_other_text = htmlspecialchars(getVal($actionSet, 'action_evidence_other_text'));

// Exhibit return 
$exhibit = getVal($actionSet, 'action_exhibit', '');
$chk_action_return = renderCheckbox($exhibit === 'ส่งคืนพนักงานสอบสวน');
$action_return_station = htmlspecialchars(getVal($actionSet, 'action_return_station'));

// Forward
$forward = getVal($actionSet, 'action_forward', false);
$chk_action_forward = renderCheckbox(!empty($forward));
$forwardDepts = getVal($actionSet, 'action_forward_depts', []);
if (!is_array($forwardDepts)) $forwardDepts = [];
$chk_forward_gchw = renderCheckbox(in_array('กชว.', $forwardDepts));
$chk_forward_gop = renderCheckbox(in_array('กอป.', $forwardDepts));
$chk_forward_gos = renderCheckbox(in_array('กอส.', $forwardDepts));
$chk_forward_gkm = renderCheckbox(in_array('กคม.', $forwardDepts));
$chk_forward_gkp = renderCheckbox(in_array('กคพ.', $forwardDepts));
$chk_forward_other = renderCheckbox(in_array('อื่นๆ', $forwardDepts));
$action_forward_other_text = htmlspecialchars(getVal($actionSet, 'action_forward_other_text'));

// ==========================================
// Section 9: การถ่ายภาพวัตถุพยาน / ผลการตรวจเก็บ
// ==========================================
$photoTypes = getVal($section9, 'photo_types', []);
if (!is_array($photoTypes)) $photoTypes = [];
$photoSides = getVal($section9, 'photo_sides', []);
if (!is_array($photoSides)) $photoSides = [];

$chk_photo_package = renderCheckbox(in_array('ภาพการหีบห่อวัตถุพยาน', $photoTypes));
$chk_photo_front_scale = renderCheckbox(in_array('ภาพวัตถุพยานพร้อมหนังสือนำส่ง ด้านหน้า (วางสเกล)', $photoTypes));
$chk_photo_close_scale = renderCheckbox(in_array('ภาพวัตถุพยานระยะใกล้ (วางสเกล)', $photoTypes));
$chk_photo_definition = renderCheckbox(in_array('ภาพคำนิยามเฉพาะบนวัตถุพยาน', $photoTypes));
$chk_photo_lift_card = renderCheckbox(in_array('ภาพแผ่นเก็บรอยลายนิ้วมือแฝง พร้อมหนังสือนำส่ง', $photoTypes));
$chk_photo_color_adj = renderCheckbox(in_array('การปรับสี/แสง', $photoTypes));
$chk_photo_resize = renderCheckbox(in_array('การปรับขนาดภาพ', $photoTypes));

$chk_side_front = renderCheckbox(in_array('ด้านหน้า', $photoSides));
$chk_side_left = renderCheckbox(in_array('ด้านซ้าย', $photoSides));
$chk_side_right = renderCheckbox(in_array('ด้านขวา', $photoSides));
$chk_side_back = renderCheckbox(in_array('ด้านหลัง', $photoSides));

$colorAdjDetail = htmlspecialchars(getVal($section9, 'color_adj_detail'));
$resizeDetail = htmlspecialchars(getVal($section9, 'resize_detail'));

// Result type
$resultType = getVal($section9, 'result_type', '');
$chk_result_not_found = renderCheckbox($resultType === 'ไม่พบรอยลายนิ้วมือแฝง');
$chk_result_found = renderCheckbox($resultType === 'พบรอยลายนิ้วมือแฝง');
$notFoundReason = htmlspecialchars(getVal($section9, 'not_found_reason'));
$foundTotalSheets = htmlspecialchars(getVal($section9, 'found_total_sheets'));

// Found items
$foundItems = getVal($section9, 'found_items', []);
if (!is_array($foundItems)) $foundItems = [];
$foundItemsHtml = '';
if (!empty($foundItems)) {
    $foundItemsHtml .= '<div class="i1">';
    foreach ($foundItems as $fi) {
        $foundItemsHtml .= '<div class="fr">';
        $foundItemsHtml .= '<span class="fl">จากวัตถุพยานรายการที่</span>';
        $foundItemsHtml .= '<span class="fd" style="max-width:80px;">' . htmlspecialchars(getVal($fi, 'from_item')) . '</span>';
        $foundItemsHtml .= '<span class="fl">จำนวน</span>';
        $foundItemsHtml .= '<span class="fd-s">' . htmlspecialchars(getVal($fi, 'sheets')) . '</span>';
        $foundItemsHtml .= '<span class="fl">แผ่น</span>';
        $foundItemsHtml .= '</div>';
    }
    $foundItemsHtml .= '</div>';
}

// Photograph
$hasPhotograph = getVal($section9, 'has_photograph', '');
$chk_has_photograph = renderCheckbox(!empty($hasPhotograph));
// JS saves as 'photographs' not 'photograph_descs'
$photographDescs = getVal($section9, 'photographs', []);
if (!is_array($photographDescs)) $photographDescs = [];
$photographHtml = '';
if (!empty($photographDescs)) {
    $photographHtml .= '<div class="i1">';
    foreach ($photographDescs as $pIdx => $desc) {
        $pNum = $pIdx + 1;
        $photographHtml .= '<div class="fr"><span class="fl">' . $pNum . ')</span><span class="fd">' . htmlspecialchars($desc) . '</span></div>';
    }
    $photographHtml .= '</div>';
}

// ==========================================
// Photo Records (Page 2)
// ==========================================
$photoInspectDate = thaiDate(getVal($photoRecords, 'inspect_date'));
$photoInspectTime = getVal($photoRecords, 'inspect_time');
$photoTotal = !empty($photos) ? count($photos) : 0;
$photoIdStart = $photoTotal > 0 ? '1' : '';
$photoIdEnd = $photoTotal > 0 ? (string)$photoTotal : '';
$photoAmount = (string)$photoTotal;

// Photographer name (lookup from user_id)
$photographerName = '';
$photographerNameId = getVal($photoRecords, 'photographer_name');
if (is_numeric($photographerNameId) && isset($userMap[$photographerNameId])) {
    $photographerName = htmlspecialchars($userMap[$photographerNameId]['fullname']);
} else {
    $photographerName = htmlspecialchars($photographerNameId);
}

$photographerDatetime = '';
$rawPDT = getVal($photoRecords, 'photographer_datetime');
if (!empty($rawPDT)) {
    $photographerDatetime = thaiDate($rawPDT) . ' ' . thaiTime($rawPDT);
}

// ==========================================
// Photo Slots (3 per page)
// ==========================================
$photoSlotsHtml = '';
$extraPhotoPagesHtml = '';

if (!empty($photos)) {
    $photosPerPage = 3;
    $firstPagePhotos = array_slice($photos, 0, $photosPerPage);
    $remainingPhotos = array_slice($photos, $photosPerPage);
    
    // First page photo slots
    foreach ($firstPagePhotos as $pIdx => $photo) {
        $fileId = $photo['file_id'] ?? 0;
        $base64 = loadBlobAsBase64($pdo, $fileId);
        $caption = $photoCaptions[$pIdx] ?? '';
        
        $photoSlotsHtml .= '<div class="photo-slot">';
        if (!empty($base64)) {
            $photoSlotsHtml .= '<img src="' . $base64 . '" alt="ภาพที่ ' . ($pIdx + 1) . '">';
        } else {
            $photoSlotsHtml .= '<div style="color:#bbb; font-size:12px; text-align:center;">ภาพที่ ' . ($pIdx + 1) . '</div>';
        }
        if (!empty($caption)) {
            $photoSlotsHtml .= '<div class="photo-caption">' . htmlspecialchars($caption) . '</div>';
        }
        $photoSlotsHtml .= '</div>';
    }
    
    // Fill remaining slots on first page
    for ($i = count($firstPagePhotos); $i < $photosPerPage; $i++) {
        $photoSlotsHtml .= '<div class="photo-slot"><div style="color:#bbb; font-size:12px; text-align:center;">ภาพที่ ' . ($i + 1) . '</div></div>';
    }
    
    // Extra pages
    $chunks = array_chunk($remainingPhotos, $photosPerPage);
    foreach ($chunks as $chunkIdx => $chunk) {
        $pageNum = $chunkIdx + 3; // page 3, 4, 5, ...
        $extraPhotoPagesHtml .= '<div class="page page-photo">';
        $extraPhotoPagesHtml .= '<div class="fpf-header"><div class="fpf-header-inner">';
        $extraPhotoPagesHtml .= '<div class="fpf-header-logo"><img src="/csims/images/office-of-police-forensic-icon.jpg" alt=""></div>';
        $extraPhotoPagesHtml .= '<div class="fpf-header-center"><div class="fpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div><div class="fpf-title-sub">การตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)</div><div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ (ต่อ)</div></div>';
        $extraPhotoPagesHtml .= '<div class="fpf-header-qr"><div class="fpf-doc-box"><div class="fpf-doc-line">รายงานที่ ' . htmlspecialchars($docNo) . ' / 25' . htmlspecialchars($docYear) . '</div><div class="fpf-doc-line">หน้าที่ PAGENUM / TOTALPAGES</div></div></div>';
        $extraPhotoPagesHtml .= '<div class="fpf-header-qr-code" id="qr_page' . $pageNum . '"></div>';
        $extraPhotoPagesHtml .= '</div></div>';
        
        foreach ($chunk as $ci => $photo) {
            $globalIdx = $photosPerPage + ($chunkIdx * $photosPerPage) + $ci;
            $fileId = $photo['file_id'] ?? 0;
            $base64 = loadBlobAsBase64($pdo, $fileId);
            $caption = $photoCaptions[$globalIdx] ?? '';
            
            $extraPhotoPagesHtml .= '<div class="photo-slot">';
            if (!empty($base64)) {
                $extraPhotoPagesHtml .= '<img src="' . $base64 . '" alt="ภาพที่ ' . ($globalIdx + 1) . '">';
            }
            if (!empty($caption)) {
                $extraPhotoPagesHtml .= '<div class="photo-caption">' . htmlspecialchars($caption) . '</div>';
            }
            $extraPhotoPagesHtml .= '</div>';
        }
        
        $extraPhotoPagesHtml .= '<div class="fpf-footer"><div class="fpf-footer-inner"><div></div><div class="fpf-footer-right">F-CS-XX แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div></div></div>';
        $extraPhotoPagesHtml .= '</div>';
    }
} else {
    // Empty slots
    for ($i = 0; $i < 3; $i++) {
        $photoSlotsHtml .= '<div class="photo-slot"><div style="color:#bbb; font-size:12px; text-align:center;">ภาพที่ ' . ($i + 1) . '</div></div>';
    }
}

// ==========================================
// Calculate total pages
// ==========================================
$totalPages = 2; // page 1 (form) + page 2 (photo)
if (!empty($photos) && count($photos) > 3) {
    $totalPages += ceil((count($photos) - 3) / 3);
}

// Fix page numbers in extra pages
$extraPhotoPagesHtml = str_replace('TOTALPAGES', $totalPages, $extraPhotoPagesHtml);
// Assign actual page numbers
$pageCounter = 3;
while (strpos($extraPhotoPagesHtml, 'PAGENUM') !== false) {
    $extraPhotoPagesHtml = preg_replace('/PAGENUM/', $pageCounter, $extraPhotoPagesHtml, 1);
    $pageCounter++;
}

// ==========================================
// 5. LOAD TEMPLATE & REPLACE
// ==========================================
$htmlTemplate = file_get_contents(__DIR__ . '/form_fingerprint_preview.html');

$replacements = [
    '{{doc_no}}' => htmlspecialchars($docNo),
    '{{doc_year}}' => htmlspecialchars($docYear),
    '{{total_pages}}' => $totalPages,
    '{{qr_data}}' => htmlspecialchars($qrData),

    // Section 1
    '{{receive_date}}' => $receiveDate,
    '{{receive_time}}' => htmlspecialchars($receiveTime),
    '{{case_no}}' => htmlspecialchars(convertDocNoToThai(getVal($gen, 'case_no'))),
    '{{police_station}}' => htmlspecialchars(getVal($gen, 'police_station')),
    '{{letter_no}}' => htmlspecialchars(getVal($gen, 'letter_no')),
    '{{letter_date}}' => htmlspecialchars(getVal($gen, 'letter_date')),
    '{{evidence_sender}}' => $senderName,
    '{{evidence_sender_position}}' => $senderPosition,
    '{{evidence_sender_phone}}' => htmlspecialchars(getVal($gen, 'evidence_sender_phone')),
    '{{evidence_letter_no}}' => htmlspecialchars(getVal($gen, 'evidence_letter_no')),
    '{{evidence_doc_no}}' => htmlspecialchars(getVal($gen, 'evidence_doc_no')),
    '{{evidence_doc_date}}' => htmlspecialchars(getVal($gen, 'evidence_doc_date')),
    '{{chk_purpose_fingerprint}}' => $chk_purpose_fingerprint,
    '{{chk_purpose_other}}' => $chk_purpose_other,
    '{{purpose_other_text}}' => $purposeOtherText,

    // Section 2
    '{{incident_date}}' => $incidentDate,
    '{{incident_time}}' => htmlspecialchars($incidentTime),
    '{{known_date}}' => $knownDate,
    '{{known_time}}' => htmlspecialchars($knownTime),

    // Section 3
    '{{collect_date}}' => $collectDate,
    '{{collect_time}}' => htmlspecialchars($collectTime),

    // Section 4
    '{{inspectors_html}}' => $inspectorsHtml,

    // Section 5
    '{{chk_pkg_envelope}}' => $chk_pkg_envelope,
    '{{chk_pkg_plastic}}' => $chk_pkg_plastic,
    '{{chk_seal_signed}}' => $chk_seal_signed,
    '{{chk_seal_detail}}' => $chk_seal_detail,
    '{{chk_collector_forensic}}' => $chk_collector_forensic,
    '{{chk_collector_investigator}}' => $chk_collector_investigator,
    '{{chk_collector_other}}' => $chk_collector_other,
    '{{collector_other_text}}' => htmlspecialchars(getVal($pkg, 'collector_other_text')),
    '{{storage_date}}' => $storageDate,
    '{{storage_time}}' => htmlspecialchars($storageTime),
    '{{duration_year}}' => htmlspecialchars(getVal($pkg, 'duration_year')),
    '{{duration_month}}' => htmlspecialchars(getVal($pkg, 'duration_month')),
    '{{duration_day}}' => htmlspecialchars(getVal($pkg, 'duration_day')),

    // Section 6
    '{{section6_html}}' => $section6Html,

    // Section 7
    '{{methods_html}}' => $methodsHtml,

    // Section 8
    '{{chk_action_gnf}}' => $chk_action_gnf,
    '{{chk_action_evidence_other}}' => $chk_action_evidence_other,
    '{{action_evidence_other_text}}' => $action_evidence_other_text,
    '{{chk_action_return}}' => $chk_action_return,
    '{{action_return_station}}' => $action_return_station,
    '{{chk_action_forward}}' => $chk_action_forward,
    '{{chk_forward_gchw}}' => $chk_forward_gchw,
    '{{chk_forward_gop}}' => $chk_forward_gop,
    '{{chk_forward_gos}}' => $chk_forward_gos,
    '{{chk_forward_gkm}}' => $chk_forward_gkm,
    '{{chk_forward_gkp}}' => $chk_forward_gkp,
    '{{chk_forward_other}}' => $chk_forward_other,
    '{{action_forward_other_text}}' => $action_forward_other_text,

    // Section 9
    '{{chk_photo_package}}' => $chk_photo_package,
    '{{chk_photo_front_scale}}' => $chk_photo_front_scale,
    '{{chk_photo_close_scale}}' => $chk_photo_close_scale,
    '{{chk_side_front}}' => $chk_side_front,
    '{{chk_side_left}}' => $chk_side_left,
    '{{chk_side_right}}' => $chk_side_right,
    '{{chk_side_back}}' => $chk_side_back,
    '{{chk_photo_definition}}' => $chk_photo_definition,
    '{{chk_photo_lift_card}}' => $chk_photo_lift_card,
    '{{chk_photo_color_adj}}' => $chk_photo_color_adj,
    '{{color_adj_detail}}' => $colorAdjDetail,
    '{{chk_photo_resize}}' => $chk_photo_resize,
    '{{resize_detail}}' => $resizeDetail,
    '{{chk_result_not_found}}' => $chk_result_not_found,
    '{{not_found_reason}}' => $notFoundReason,
    '{{chk_result_found}}' => $chk_result_found,
    '{{found_total_sheets}}' => $foundTotalSheets,
    '{{found_items_html}}' => $foundItemsHtml,
    '{{chk_has_photograph}}' => $chk_has_photograph,
    '{{photograph_html}}' => $photographHtml,

    // Photo page
    '{{photo_inspect_date}}' => $photoInspectDate,
    '{{photo_inspect_time}}' => htmlspecialchars($photoInspectTime),
    '{{photo_id_start}}' => $photoIdStart,
    '{{photo_id_end}}' => $photoIdEnd,
    '{{photo_amount}}' => $photoAmount,
    '{{photographer_name}}' => $photographerName,
    '{{photographer_datetime}}' => $photographerDatetime,
    '{{photo_slots_html}}' => $photoSlotsHtml,
    '{{extra_photo_pages_html}}' => $extraPhotoPagesHtml,
];

$html = str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);

header('Content-Type: text/html; charset=UTF-8');
echo $html;
