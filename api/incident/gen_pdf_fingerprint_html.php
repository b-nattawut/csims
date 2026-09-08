<?php
/**
 * gen_pdf_fingerprint_html.php
 * แบบร่างรายงานสำหรับ complaints_type = 06 (ลายนิ้วมือแฝง)
 */

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../helpers/report_no.php';

date_default_timezone_set('Asia/Bangkok');

function h($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function renderCheckbox($condition) {
    return $condition ? '✓' : '';
}

$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;
if ($incident_id <= 0) {
    die('ไม่พบรหัสรายการ');
}

$sql = "SELECT
            t1.*,
            CONCAT(IFNULL(t4.rank_name,''), ' ', IFNULL(t3.first_name,''), ' ', IFNULL(t3.last_name,'')) AS creator_name,
            IFNULL(t4.rank_name, '') AS creator_rank
        FROM rn_ReceiveNoti t1
        LEFT JOIN users t2 ON t1.create_by = t2.user_id
        LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        WHERE t1.id = ? AND t1.statusDelete = 0
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$incident_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die('ไม่พบข้อมูลรายการนี้');
}

$sqlSign = "SELECT seqSignature, fullName, positionName
            FROM rn_ReceiveNotiApprove
            WHERE ComplaintsID = ?
            ORDER BY seqSignature ASC";
$stmtSign = $pdo->prepare($sqlSign);
$stmtSign->execute([$incident_id]);
$signatures = $stmtSign->fetchAll(PDO::FETCH_ASSOC);

$signData = [
    1 => ['fullName' => '', 'positionName' => ''],
    2 => ['fullName' => '', 'positionName' => ''],
    3 => ['fullName' => '', 'positionName' => ''],
];
foreach ($signatures as $sig) {
    $seq = intval($sig['seqSignature'] ?? 0);
    if (isset($signData[$seq])) {
        $signData[$seq]['fullName'] = $sig['fullName'] ?? '';
        $signData[$seq]['positionName'] = $sig['positionName'] ?? '';
    }
}

// =============================================
// ดึงข้อมูล Checklist จาก incident_checklist_transaction
// =============================================
$checklistData = null;
$sqlCL = "SELECT incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? LIMIT 1";
$stmtCL = $pdo->prepare($sqlCL);
$stmtCL->execute([$incident_id]);
$clRow = $stmtCL->fetch(PDO::FETCH_ASSOC);
if ($clRow && !empty($clRow['incident_checklist_data'])) {
    $checklistData = json_decode($clRow['incident_checklist_data'], true);
}

// --- Section 5: วัตถุพยานที่ส่งตรวจพิสูจน์ (evidence items → ev1..ev9 / qty1..qty9) ---
$evReplacements = [];
for ($i = 1; $i <= 9; $i++) {
    $evReplacements['ev' . $i] = '';
    $evReplacements['qty' . $i] = '';
}
if ($checklistData && !empty($checklistData['section6_sets'])) {
    $itemIndex = 0;
    foreach ($checklistData['section6_sets'] as $set) {
        if (!empty($set['evidence_items'])) {
            foreach ($set['evidence_items'] as $item) {
                $itemIndex++;
                if ($itemIndex > 9) break 2;
                $evReplacements['ev' . $itemIndex] = h($item['description'] ?? '');
                $evReplacements['qty' . $itemIndex] = h($item['quantity'] ?? '');
            }
        }
    }
}

// --- Section 6: วิธีการดำเนินการ (methods → chk_m1..chk_m16) ---
// Mapping: method name → checkbox placeholder(s)
$methodToCheckbox = [
    'Super Glue'             => ['chk_m2'],
    'Rhodamine 6G'           => ['chk_m3'],
    'Basic Yellow 40'        => ['chk_m4'],
    'Indanedione-Zine'       => ['chk_m6'],
    'Ninhydrin'              => ['chk_m7'],
    'Amido Black'            => ['chk_m10'],
    'Acid Yellow 7'          => ['chk_m11'],
    'Powder'                 => ['chk_m13'],
    'Sticky Side'            => ['chk_m14'],
    'SPR'                    => ['chk_m15'],
];
// Forensic Light Sources → auto-check the FLS header of the column containing the selected chemical
$col1Methods = ['Super Glue', 'Rhodamine 6G', 'Basic Yellow 40'];
$col2Methods = ['Indanedione-Zine', 'Ninhydrin'];
$col3Methods = ['Amido Black', 'Acid Yellow 7'];
$methodChecks = [];
$methodDetails = [];
for ($i = 1; $i <= 16; $i++) {
    $methodChecks['chk_m' . $i] = '';
    $methodDetails['detail_m' . $i] = '';
}
if ($checklistData && !empty($checklistData['section6_sets'])) {
    // Collect all methods with their detail text
    $allMethods = []; // name => detail
    foreach ($checklistData['section6_sets'] as $set) {
        // Methods from evidence items
        if (!empty($set['evidence_items'])) {
            foreach ($set['evidence_items'] as $item) {
                if (!empty($item['methods'])) {
                    foreach ($item['methods'] as $m) {
                        if (!empty($m['name'])) {
                            $detail = trim($m['detail'] ?? '');
                            // Keep the longest detail if same method appears multiple times
                            if (!isset($allMethods[$m['name']]) || mb_strlen($detail) > mb_strlen($allMethods[$m['name']])) {
                                $allMethods[$m['name']] = $detail;
                            }
                        }
                    }
                }
            }
        }
        // Global methods (section 7)
        if (!empty($set['global_methods'])) {
            foreach ($set['global_methods'] as $m) {
                if (!empty($m['name'])) {
                    $detail = trim($m['detail'] ?? '');
                    if (!isset($allMethods[$m['name']]) || mb_strlen($detail) > mb_strlen($allMethods[$m['name']])) {
                        $allMethods[$m['name']] = $detail;
                    }
                }
            }
        }
    }
    $usedOther = false; // only 1 อื่นๆ checkbox (chk_m8)
    $hasFLS = false;
    $flsDetail = '';
    foreach ($allMethods as $methodName => $detail) {
        if (strcasecmp($methodName, 'Forensic Light Sources') === 0) {
            $hasFLS = true;
            $flsDetail = $detail;
            continue;
        }
        $found = false;
        foreach ($methodToCheckbox as $knownName => $keys) {
            if (strcasecmp($methodName, $knownName) === 0) {
                foreach ($keys as $k) {
                    $methodChecks[$k] = renderCheckbox(true);
                    $detailKey = str_replace('chk_m', 'detail_m', $k);
                    $methodDetails[$detailKey] = h($detail);
                }
                $found = true;
                break;
            }
        }
        if (!$found && mb_strtolower($methodName) !== 'อื่นๆ') {
            if (!$usedOther) {
                $otherText = $methodName . ($detail ? ' ' . $detail : '');
                $methodChecks['chk_m8'] = renderCheckbox(true);
                $methodDetails['detail_m8'] = h($otherText);
                $usedOther = true;
            }
        }
        if (mb_strtolower($methodName) === 'อื่นๆ') {
            if (!$usedOther) {
                $methodChecks['chk_m8'] = renderCheckbox(true);
                $methodDetails['detail_m8'] = h($detail);
                $usedOther = true;
            }
        }
    }
    // If "Forensic Light Sources" was selected, check the FLS header for columns that have chemicals selected
    if ($hasFLS) {
        $flsChecked = false;
        foreach ($col1Methods as $m) {
            if (isset($allMethods[$m])) { $methodChecks['chk_m1'] = renderCheckbox(true); $methodDetails['detail_m1'] = h($flsDetail); $flsChecked = true; break; }
        }
        foreach ($col2Methods as $m) {
            if (isset($allMethods[$m])) { $methodChecks['chk_m5'] = renderCheckbox(true); if (!$flsChecked) $methodDetails['detail_m5'] = h($flsDetail); $flsChecked = true; break; }
        }
        foreach ($col3Methods as $m) {
            if (isset($allMethods[$m])) { $methodChecks['chk_m9'] = renderCheckbox(true); if (!$flsChecked) $methodDetails['detail_m9'] = h($flsDetail); $flsChecked = true; break; }
        }
        // If FLS selected alone (no specific chemicals), just check the first column
        if (!$flsChecked) {
            $methodChecks['chk_m1'] = renderCheckbox(true);
            $methodDetails['detail_m1'] = h($flsDetail);
        }
    }
}

// --- Section 7: การดำเนินการ (actions → chk_send_gnf, chk_return_inquiry, etc.) ---
$actionChecks = [
    'chk_send_gnf'      => '',
    'chk_send_other'    => '',
    'send_other_text'   => '',
    'chk_return_inquiry' => '',
    'return_station'    => '',
    'chk_forward_group' => '',
    'chk_dept_kcw'      => '',
    'chk_dept_kop'      => '',
    'chk_dept_kos'      => '',
    'chk_dept_kkm'      => '',
    'chk_dept_kkp'      => '',
    'chk_dept_other'    => '',
    'forward_other_text' => '',
];
if ($checklistData && !empty($checklistData['section6_sets'])) {
    // Merge actions from all sets (primarily first set has the action data)
    foreach ($checklistData['section6_sets'] as $set) {
        if (!empty($set['action_evidence_dest'])) {
            foreach ($set['action_evidence_dest'] as $dest) {
                if ($dest === 'กนฝ.') $actionChecks['chk_send_gnf'] = renderCheckbox(true);
                if ($dest === 'อื่นๆ') $actionChecks['chk_send_other'] = renderCheckbox(true);
            }
        }
        if (!empty($set['action_evidence_other_text'])) {
            $actionChecks['send_other_text'] = h($set['action_evidence_other_text']);
        }
        if (!empty($set['action_exhibit'])) {
            $actionChecks['chk_return_inquiry'] = renderCheckbox(true);
        }
        if (!empty($set['action_return_station'])) {
            $actionChecks['return_station'] = h($set['action_return_station']);
        }
        if (!empty($set['action_forward'])) {
            $actionChecks['chk_forward_group'] = renderCheckbox(true);
        }
        if (!empty($set['action_forward_depts'])) {
            $deptMap = [
                'กชว.' => 'chk_dept_kcw',
                'กอป.' => 'chk_dept_kop',
                'กอส.' => 'chk_dept_kos',
                'กคม.' => 'chk_dept_kkm',
                'กคพ.' => 'chk_dept_kkp',
                'อื่นๆ' => 'chk_dept_other',
            ];
            foreach ($set['action_forward_depts'] as $dept) {
                if (isset($deptMap[$dept])) {
                    $actionChecks[$deptMap[$dept]] = renderCheckbox(true);
                }
            }
        }
        if (!empty($set['action_forward_other_text'])) {
            $actionChecks['forward_other_text'] = h($set['action_forward_other_text']);
        }
    }
}

$dtCreate = !empty($data['create_date']) ? new DateTime($data['create_date']) : new DateTime('now');
$thaiMonths = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$createDay = $dtCreate->format('d');
$createMonth = $thaiMonths[intval($dtCreate->format('m'))] ?? '';
$createYear = (int)$dtCreate->format('Y') + 543;
$createTime = $dtCreate->format('H:i');
$reportYear2 = substr((string)$createYear, -2);

$template = file_get_contents(__DIR__ . '/form_incident_fingerprint_preview.html');
if ($template === false) {
    die('ไม่พบไฟล์แม่แบบฟอร์ม');
}

// --- Also pull general info from checklist for fields not in rn_ReceiveNoti ---
$clCaseNo = '';
$clLetterNo = '';
$clLetterDate = '';
$clSenderName = '';
$clSenderPosition = '';
$clSenderPhone = '';
$clEvidenceLetterNo = '';
$clEvidenceDocNo = '';
$clEvidenceDocDate = '';
$clPurposes = [];
$clPurposeOtherText = '';
if ($checklistData) {
    $gi = $checklistData['general_info'] ?? [];
    $clCaseNo = h(convertDocNoToThai($gi['case_no'] ?? ''));
    $clLetterNo = h($gi['letter_no'] ?? '');
    $clLetterDate = h($gi['letter_date'] ?? '');
    // evidence_sender stores user_id, need to look up the name
    $clSenderPosition = h($gi['evidence_sender_position'] ?? '');
    $clSenderId = $gi['evidence_sender'] ?? '';
    if ($clSenderId !== '') {
        $sqlSender = "SELECT CONCAT(IFNULL(t2.rank_name,''), ' ', IFNULL(t1.first_name,''), ' ', IFNULL(t1.last_name,'')) AS fullname,
                             IFNULL(t3.position_name, '') AS position_name
                      FROM user_profile t1
                      LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                      LEFT JOIN user_position t3 ON t1.position_id = t3.position_id
                      WHERE t1.user_id = ?
                      LIMIT 1";
        $stmtSender = $pdo->prepare($sqlSender);
        $stmtSender->execute([$clSenderId]);
        $senderRow = $stmtSender->fetch(PDO::FETCH_ASSOC);
        if ($senderRow) {
            $clSenderName = h(trim($senderRow['fullname']));
            if (empty($clSenderPosition)) {
                $clSenderPosition = h($senderRow['position_name']);
            }
        }
    }
    $clSenderPhone = h($gi['evidence_sender_phone'] ?? '');
    $clEvidenceLetterNo = h($gi['evidence_letter_no'] ?? '');
    $clEvidenceDocNo = h($gi['evidence_doc_no'] ?? '');
    $clEvidenceDocDate = h($gi['evidence_doc_date'] ?? '');
    if (!empty($gi['purposes']) && is_array($gi['purposes'])) {
        $clPurposes = $gi['purposes'];
    }
    $clPurposeOtherText = h($gi['purpose_other_text'] ?? '');
}

// Strip year suffix from receiveNoti_No (e.g. "LC-0006/2569" → "LC-0006")
$rNo = $data['receiveNoti_No'] ?? '';
$slashPos = strpos($rNo, '/');
if ($slashPos !== false) {
    $rNo = substr($rNo, 0, $slashPos);
}

$replacements = [
    '{{receiveNoti_No}}' => h(convertDocNoToThai($rNo)),
    '{{reportYear2}}' => h($reportYear2),

    '{{create_day}}' => h($createDay),
    '{{create_month}}' => h($createMonth),
    '{{create_year}}' => h($createYear),
    '{{create_time}}' => h($createTime),

    '{{case_no}}' => $clCaseNo,
    '{{complaints_from}}' => h($data['complaints_From'] ?? ''),
    '{{letter_no}}' => $clLetterNo,
    '{{letter_date}}' => $clLetterDate,

    '{{suffer_full_name}}' => $clSenderName ?: h($data['suffer_full_name'] ?? ''),
    '{{suffer_phone}}' => $clSenderPhone ?: h($data['suffer_phone'] ?? ''),
    '{{sender_position}}' => $clSenderPosition,

    '{{evidence_letter_no}}' => $clEvidenceLetterNo,
    '{{evidence_doc_no}}' => $clEvidenceDocNo,
    '{{evidence_doc_date}}' => $clEvidenceDocDate,

    '{{inquiry_official_full_name}}' => h($data['inquiry_official_full_name'] ?? ''),
    '{{inquiry_official_phone}}' => h($data['inquiry_official_phone'] ?? ''),
    '{{inquiry_position}}' => '',

    '{{chk_purpose_fp}}' => renderCheckbox(!empty($clPurposes) ? in_array('เพื่อตรวจเก็บรอยลายนิ้วมือแฝง', $clPurposes) : true),
    '{{chk_purpose_other}}' => renderCheckbox(!empty($clPurposes) && in_array('อื่นๆ', $clPurposes)),
    '{{purpose_other_text}}' => $clPurposeOtherText,

    '{{ev1}}' => $evReplacements['ev1'], '{{ev2}}' => $evReplacements['ev2'], '{{ev3}}' => $evReplacements['ev3'], '{{ev4}}' => $evReplacements['ev4'], '{{ev5}}' => $evReplacements['ev5'],
    '{{ev6}}' => $evReplacements['ev6'], '{{ev7}}' => $evReplacements['ev7'], '{{ev8}}' => $evReplacements['ev8'], '{{ev9}}' => $evReplacements['ev9'],
    '{{qty1}}' => $evReplacements['qty1'], '{{qty2}}' => $evReplacements['qty2'], '{{qty3}}' => $evReplacements['qty3'], '{{qty4}}' => $evReplacements['qty4'], '{{qty5}}' => $evReplacements['qty5'],
    '{{qty6}}' => $evReplacements['qty6'], '{{qty7}}' => $evReplacements['qty7'], '{{qty8}}' => $evReplacements['qty8'], '{{qty9}}' => $evReplacements['qty9'],

    '{{chk_m1}}' => $methodChecks['chk_m1'], '{{chk_m2}}' => $methodChecks['chk_m2'], '{{chk_m3}}' => $methodChecks['chk_m3'], '{{chk_m4}}' => $methodChecks['chk_m4'],
    '{{chk_m5}}' => $methodChecks['chk_m5'], '{{chk_m6}}' => $methodChecks['chk_m6'], '{{chk_m7}}' => $methodChecks['chk_m7'], '{{chk_m8}}' => $methodChecks['chk_m8'],
    '{{chk_m9}}' => $methodChecks['chk_m9'], '{{chk_m10}}' => $methodChecks['chk_m10'], '{{chk_m11}}' => $methodChecks['chk_m11'], '{{chk_m12}}' => $methodChecks['chk_m12'],
    '{{chk_m13}}' => $methodChecks['chk_m13'], '{{chk_m14}}' => $methodChecks['chk_m14'], '{{chk_m15}}' => $methodChecks['chk_m15'], '{{chk_m16}}' => $methodChecks['chk_m16'],
    '{{detail_m1}}' => $methodDetails['detail_m1'], '{{detail_m2}}' => $methodDetails['detail_m2'], '{{detail_m3}}' => $methodDetails['detail_m3'], '{{detail_m4}}' => $methodDetails['detail_m4'],
    '{{detail_m5}}' => $methodDetails['detail_m5'], '{{detail_m6}}' => $methodDetails['detail_m6'], '{{detail_m7}}' => $methodDetails['detail_m7'], '{{detail_m8}}' => $methodDetails['detail_m8'],
    '{{detail_m9}}' => $methodDetails['detail_m9'], '{{detail_m10}}' => $methodDetails['detail_m10'], '{{detail_m11}}' => $methodDetails['detail_m11'], '{{detail_m12}}' => $methodDetails['detail_m12'],
    '{{detail_m13}}' => $methodDetails['detail_m13'], '{{detail_m14}}' => $methodDetails['detail_m14'], '{{detail_m15}}' => $methodDetails['detail_m15'], '{{detail_m16}}' => $methodDetails['detail_m16'],
    '{{chk_send_gnf}}' => $actionChecks['chk_send_gnf'],
    '{{chk_send_other}}' => $actionChecks['chk_send_other'],
    '{{send_other_text}}' => $actionChecks['send_other_text'],
    '{{chk_return_inquiry}}' => $actionChecks['chk_return_inquiry'],
    '{{return_station}}' => $actionChecks['return_station'],
    '{{chk_forward_group}}' => $actionChecks['chk_forward_group'],
    '{{chk_dept_kcw}}' => $actionChecks['chk_dept_kcw'],
    '{{chk_dept_kop}}' => $actionChecks['chk_dept_kop'],
    '{{chk_dept_kos}}' => $actionChecks['chk_dept_kos'],
    '{{chk_dept_kkm}}' => $actionChecks['chk_dept_kkm'],
    '{{chk_dept_kkp}}' => $actionChecks['chk_dept_kkp'],
    '{{chk_dept_other}}' => $actionChecks['chk_dept_other'],
    '{{forward_other_text}}' => $actionChecks['forward_other_text'],

    '{{sig1_name}}' => h(!empty($signData[1]['fullName']) ? $signData[1]['fullName'] : trim(($data['creator_name'] ?? ''))),
    '{{sig1_position}}' => h(!empty($signData[1]['positionName']) ? $signData[1]['positionName'] : ($data['creator_rank'] ?? '')),
    '{{sig2_name}}' => h($signData[2]['fullName'] ?? ''),
    '{{sig2_position}}' => h($signData[2]['positionName'] ?? ''),
    '{{sig3_name}}' => h($signData[3]['fullName'] ?? ''),
    '{{sig3_position}}' => h($signData[3]['positionName'] ?? ''),
];

$html = str_replace(array_keys($replacements), array_values($replacements), $template);

header('Content-Type: text/html; charset=utf-8');
echo $html;
