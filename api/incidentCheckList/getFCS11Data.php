<?php
/**
 * getFCS11Data.php - ดึงข้อมูลสำหรับ Modal F-CS-11 (แบบการตรวจเก็บและส่งมอบวัตถุพยาน)
 * รับ parameter: incident_id (GET)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_config.php';

// ==========================================
// HELPER FUNCTIONS
// ==========================================
function getVal($arr, $key, $default = '') {
    if (!is_array($arr)) return $default;
    return isset($arr[$key]) && $arr[$key] !== null ? $arr[$key] : $default;
}

function parseDateParts($datetime) {
    if (empty($datetime)) return ['day' => '', 'month' => '', 'year' => ''];
    $ts = strtotime($datetime);
    if ($ts === false) return ['day' => '', 'month' => '', 'year' => ''];

    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];

    return [
        'day'   => date('j', $ts),
        'month' => $months[(int)date('n', $ts)] ?? '',
        'year'  => date('Y', $ts) + 543
    ];
}

function joinDateTime($date, $time) {
    $date = trim((string)$date);
    $time = trim((string)$time);
    if ($date === '') return '';
    if ($time === '') $time = '00:00';
    return $date . 'T' . $time;
}

function normalizeText($text) {
    $text = trim((string)$text);
    if ($text === '') return '';
    return preg_replace('/\s+/u', ' ', $text);
}

// ==========================================
// MAIN
// ==========================================
$incident_id = isset($_GET['incident_id']) ? intval($_GET['incident_id']) : 0;

if ($incident_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุ incident_id']);
    exit;
}

try {
    // ดึงข้อมูลจาก incident_checklist_transaction (ใช้ f_cs_data เป็นหลัก)
    $stmt = $pdo->prepare("SELECT incident_checklist_data, f_cs_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Checklist']);
        exit;
    }

    // ใช้ f_cs_data เป็นหลัก ถ้าไม่มีค่อยใช้ incident_checklist_data
    $fcsDataRaw = $row['f_cs_data'] ?? null;
    $fcsData = !empty($fcsDataRaw) ? json_decode($fcsDataRaw, true) : null;
    $checklistData = json_decode($row['incident_checklist_data'], true);
    
    if (json_last_error() !== JSON_ERROR_NONE) $checklistData = [];
    if (!is_array($fcsData)) $fcsData = null;
    
    // Flag ว่าใช้ f_cs_data หรือไม่
    $usingFcsData = !empty($fcsData) && is_array($fcsData);
    
    // ถ้ามี f_cs_data ให้ใช้เป็นหลัก (ไม่ merge เพื่อให้การลบ signature ทำงานได้)
    if ($usingFcsData) {
        // ใช้ f_cs_data เป็นหลัก แต่เอา general data จาก checklistData มาเสริม
        $data = $fcsData;
        // เอา general_info จาก checklistData มาเสริมถ้า fcsData ไม่มี
        if (empty($data['general_info']) && !empty($checklistData['general_info'])) {
            $data['general_info'] = $checklistData['general_info'];
        }
    } else {
        $data = $checklistData;
        $fcsData = null;
    }

    // ==========================================
    // PREPARE USER MAP
    // ==========================================
    $userMap = [];
    $sqlUser = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname,
                       IFNULL(up.position_name,'') AS position_name
                FROM user_profile t1
                LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                LEFT JOIN user_position up ON t1.position_id = up.position_id";
    $stmtUser = $pdo->query($sqlUser);
    while ($u = $stmtUser->fetch(PDO::FETCH_ASSOC)) {
        $userMap[$u['user_id']] = [
            'fullname' => $u['fullname'],
            'position' => $u['position_name']
        ];
    }

    // ==========================================
    // EXTRACT DATA
    // ==========================================
    $gen = $data['general_info'] ?? [];
    $handover = $data['handover'] ?? [];
    $datetimeInfo = $data['datetime_info'] ?? [];
    $sceneInfo = $data['scene_info'] ?? [];

    // === Report No ===
    $reportNo = getVal($gen, 'report_no');

    // === Source Station ===
    $sourceStation = getVal($gen, 'source_station');
    if (empty($sourceStation)) {
        $sourceStation = getVal($gen, 'police_station');
    }

    // === Report Datetime ===
    $reportDatetimeRaw = getVal($gen, 'report_datetime');
    if (empty($reportDatetimeRaw)) {
        $reportDatetimeRaw = joinDateTime(getVal($gen, 'case_date'), getVal($gen, 'case_time'));
    }
    if (empty($reportDatetimeRaw)) {
        $reportDatetimeRaw = joinDateTime(getVal($gen, 'receive_date'), getVal($gen, 'receive_time'));
    }
    if (empty($reportDatetimeRaw)) {
        $reportDatetimeRaw = joinDateTime(getVal($gen, 'report_date'), getVal($gen, 'report_time'));
    }
    $reportDateParts = parseDateParts($reportDatetimeRaw);
    
    // Override report date parts from fcs11_data if available
    $fcs11 = $data['fcs11_data'] ?? [];
    if (!empty($fcs11['report_day'])) $reportDateParts['day'] = $fcs11['report_day'];
    if (!empty($fcs11['report_month'])) $reportDateParts['month'] = $fcs11['report_month'];
    if (!empty($fcs11['report_year'])) $reportDateParts['year'] = $fcs11['report_year'];

    // === Notification Channel ===
    $channel = getVal($gen, 'report_channel');
    $channel = !empty($channel) ? $channel : getVal($gen, 'notify_method');
    $channelOtherText = getVal($gen, 'report_channel_other');
    $channelOtherText = !empty($channelOtherText) ? $channelOtherText : getVal($gen, 'notify_method_other_text');

    $channels = [];
    if (is_array($channel)) {
        $channels = $channel;
    } elseif (!empty($channel)) {
        $channels = [$channel];
    }

    // === Document No ===
    $docNo = getVal($gen, 'document_no');
    if (empty($docNo)) $docNo = getVal($gen, 'case_doc_no');
    if (empty($docNo)) $docNo = getVal($gen, 'doc_no');

    // === Case Type ===
    $caseType = getVal($gen, 'case_type');
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
        case 'other':   $caseTypeText = getVal($gen, 'case_type_other'); break;
        default:        $caseTypeText = $caseType;
    }
    // Override from fcs11_data if available
    if (!empty($fcs11['case_type_text'])) $caseTypeText = $fcs11['case_type_text'];

    // === Incident Location ===
    $incidentLocation = getVal($gen, 'location_detail');
    if (empty($incidentLocation)) {
        $incidentLocation = getVal($sceneInfo, 'crime_location');
    }
    if (empty($incidentLocation)) {
        $incidentLocation = getVal($sceneInfo, 'incident_location');
    }

    // === Incident Datetime ===
    $incidentDatetimeRaw = getVal($gen, 'incident_datetime');
    if (empty($incidentDatetimeRaw)) {
        $incidentDatetimeRaw = joinDateTime(getVal($gen, 'victim_know_date'), getVal($gen, 'victim_know_time'));
    }
    if (empty($incidentDatetimeRaw)) {
        $incidentDatetimeRaw = joinDateTime(getVal($gen, 'incident_date'), getVal($gen, 'incident_time'));
    }

    // === Inspection Datetime ===
    $inspectionDatetimeRaw = getVal($gen, 'inspection_datetime');
    if (empty($inspectionDatetimeRaw)) {
        $inspectionDatetimeRaw = joinDateTime(getVal($gen, 'inspect_date'), getVal($gen, 'inspect_time'));
    }
    if (empty($inspectionDatetimeRaw)) {
        $inspectionDatetimeRaw = joinDateTime(getVal($gen, 'collect_date'), getVal($gen, 'collect_time'));
    }

    // === Victim ===
    $victim = $gen['victim'] ?? [];
    $victimName = getVal($victim, 'name');
    if (empty($victimName)) {
        $victimName = trim(getVal($victim, 'firstname') . ' ' . getVal($victim, 'lastname'));
    }
    $victimAge = getVal($victim, 'age');

    // === Investigator ===
    $investigator = $gen['investigator'] ?? [];
    $officerName = trim(getVal($investigator, 'firstname') . ' ' . getVal($investigator, 'lastname'));
    if (empty($officerName)) {
        $officerName = getVal($investigator, 'name');
    }
    if (empty($officerName)) {
        $officerName = getVal($datetimeInfo, 'investigator_name');
    }

    // === Province ===
    $province = '';
    $provinceId = getVal($gen, 'province_id');
    if (!empty($provinceId)) {
        $stmtProv = $pdo->prepare("SELECT cfs_name FROM CFS_province WHERE cfs_id = ?");
        $stmtProv->execute([$provinceId]);
        $rowProv = $stmtProv->fetch(PDO::FETCH_ASSOC);
        if ($rowProv) {
            $province = $rowProv['cfs_name'] ?? '';
        }
    }

    // ==========================================
    // EVIDENCE LIST (เหมือน gen_pdf_report_html.php)
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
        'explosive'   => 'กลุ่มงานตรวจวัตถุระเบิด (กก.กตว.)',
    ];
    
    $evidenceList = [];
    
    // ลองดึงจาก measurements ก่อน
    if (!empty($data['measurements']) && is_array($data['measurements'])) {
        foreach ($data['measurements'] as $i => $ev) {
            $detail = trim((string)($ev['item'] ?? ($ev['detail'] ?? '')));
            if ($detail === '') continue;
            $labUnit = $ev['forensic_unit'] ?? ($ev['lab_unit'] ?? '');
            $labUnitText = $labUnitMap[$labUnit] ?? $labUnit;
            $evidenceList[] = [
                'no' => $ev['no'] ?? ($i + 1),
                'item' => $detail,
                'test' => $labUnitText ?: '-'
            ];
        }
    }
    
    // Fallback: evidences
    if (empty($evidenceList) && !empty($data['evidences']) && is_array($data['evidences'])) {
        foreach ($data['evidences'] as $i => $ev) {
            if (!empty($ev['_summary_only'])) continue;
            $detail = trim((string)($ev['detail'] ?? ($ev['item'] ?? '')));
            if ($detail === '') continue;
            $labUnit = $ev['lab_unit'] ?? ($ev['forensic_unit'] ?? '');
            $labUnitText = $labUnitMap[$labUnit] ?? $labUnit;
            $evidenceList[] = [
                'no' => $ev['no'] ?? ($i + 1),
                'item' => $detail,
                'test' => $labUnitText ?: '-'
            ];
        }
    }

    // ==========================================
    // HANDOVER INFO (เหมือน gen_pdf_report_html.php)
    // ==========================================
    
    // Helper function to resolve user by ID
    function resolveUserById($pdo, $rawId) {
        $rawId = trim((string)$rawId);
        if ($rawId === '' || !preg_match('/^\d+$/', $rawId)) {
            return ['name' => '', 'position' => ''];
        }
        try {
            $stmt = $pdo->prepare("SELECT CONCAT(IFNULL(r.rank_name,''),' ',p.first_name,' ',p.last_name) AS fullname,
                                          IFNULL(up.position_name,'') AS position_name
                                   FROM users u
                                   INNER JOIN user_profile p ON u.user_id = p.user_id
                                   LEFT JOIN user_rank r ON p.rank_id = r.rank_id
                                   LEFT JOIN user_position up ON p.position_id = up.position_id
                                   WHERE u.user_id = ?");
            $stmt->execute([$rawId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'name' => trim((string)($row['fullname'] ?? '')),
                    'position' => trim((string)($row['position_name'] ?? ''))
                ];
            }
        } catch (Exception $e) {}
        return ['name' => '', 'position' => ''];
    }
    
    // === Receiver ===
    $recv_id = getVal($handover, 'receiver_id');
    $recv_name = '';
    $recv_pos = getVal($handover, 'receiver_pos');
    
    if (!empty($recv_id) && isset($userMap[$recv_id])) {
        $recv_name = $userMap[$recv_id]['fullname'];
        if (empty($recv_pos)) $recv_pos = $userMap[$recv_id]['position'];
    } elseif (!empty($recv_id)) {
        $resolved = resolveUserById($pdo, $recv_id);
        if (!empty($resolved['name'])) $recv_name = $resolved['name'];
        if (empty($recv_pos) && !empty($resolved['position'])) $recv_pos = $resolved['position'];
    }
    if (empty($recv_name)) {
        $recv_name = getVal($handover, 'receiver_name');
        if (empty($recv_name)) {
            $recv_name = getVal($handover['receiver'] ?? [], 'name_id');
        }
    }
    // ถ้า recv_name เป็น ID ให้ resolve
    if (!empty($recv_name) && preg_match('/^\d+$/', trim((string)$recv_name))) {
        $resolved = resolveUserById($pdo, $recv_name);
        if (!empty($resolved['name'])) $recv_name = $resolved['name'];
        if (empty($recv_pos) && !empty($resolved['position'])) $recv_pos = $resolved['position'];
    }
    if (empty($recv_pos)) {
        $recv_pos = getVal($handover, 'receiver_position');
        if (empty($recv_pos)) {
            $recv_pos = getVal($handover['receiver'] ?? [], 'position');
        }
    }

    // === Deliverer ===
    $delv_id = getVal($handover, 'deliverer_id');
    $delv_name = '';
    $delv_pos = getVal($handover, 'deliverer_pos');
    
    if (!empty($delv_id) && isset($userMap[$delv_id])) {
        $delv_name = $userMap[$delv_id]['fullname'];
        if (empty($delv_pos)) $delv_pos = $userMap[$delv_id]['position'];
    } elseif (!empty($delv_id)) {
        $resolved = resolveUserById($pdo, $delv_id);
        if (!empty($resolved['name'])) $delv_name = $resolved['name'];
        if (empty($delv_pos) && !empty($resolved['position'])) $delv_pos = $resolved['position'];
    }
    if (empty($delv_name)) {
        $delv_name = getVal($handover, 'deliverer_name');
        if (empty($delv_name)) {
            $delv_name = getVal($handover, 'sender_name');
        }
        if (empty($delv_name)) {
            $delv_name = getVal($handover['sender'] ?? [], 'name_id');
        }
    }
    // ถ้า delv_name เป็น ID ให้ resolve
    if (!empty($delv_name) && preg_match('/^\d+$/', trim((string)$delv_name))) {
        $resolved = resolveUserById($pdo, $delv_name);
        if (!empty($resolved['name'])) $delv_name = $resolved['name'];
        if (empty($delv_pos) && !empty($resolved['position'])) $delv_pos = $resolved['position'];
    }
    if (empty($delv_pos)) {
        $delv_pos = getVal($handover, 'deliverer_position');
        if (empty($delv_pos)) {
            $delv_pos = getVal($handover, 'sender_position');
        }
        if (empty($delv_pos)) {
            $delv_pos = getVal($handover['sender'] ?? [], 'position');
        }
    }
    
    // Fallback: use signer data (used by scene_evidence / EV7)
    $signer = $data['signer'] ?? [];
    if (empty($delv_name)) {
        $signerId = getVal($signer, 'id');
        if (!empty($signerId)) {
            $resolved = resolveUserById($pdo, $signerId);
            if (!empty($resolved['name'])) $delv_name = $resolved['name'];
            if (empty($delv_pos) && !empty($resolved['position'])) $delv_pos = $resolved['position'];
        }
    }
    if (empty($delv_pos) && !empty($signer['position'])) {
        $delv_pos = $signer['position'];
    }

    // === Signatures ===
    // ถ้ามี f_cs_data ให้ใช้ signature จาก f_cs_data เท่านั้น (ไม่ fallback)
    $signatures = $data['signatures'] ?? [];
    $attachmentsMeta = $data['attachments_meta'] ?? [];
    
    if ($usingFcsData) {
        // ใช้ f_cs_data - ดึง signature จาก fcsData เท่านั้น
        $recv_sig = $fcsData['handover']['receiver_sig'] ?? ($fcsData['signatures']['receiver_sig'] ?? null);
        $delv_sig = $fcsData['handover']['deliverer_sig'] ?? ($fcsData['signatures']['deliverer_sig'] ?? null);
    } else {
        // ใช้ incident_checklist_data - fallback ตามปกติ
        $recv_sig = getVal($handover, 'receiver_sig');
        if (empty($recv_sig)) $recv_sig = $signatures['receiver_sig'] ?? null;
        if (empty($recv_sig)) $recv_sig = $signatures['receiver_signature'] ?? null;
        if (empty($recv_sig)) $recv_sig = $attachmentsMeta['receiver_sig'] ?? null;
        
        $delv_sig = getVal($handover, 'deliverer_sig');
        if (empty($delv_sig)) $delv_sig = $signatures['deliverer_sig'] ?? null;
        if (empty($delv_sig)) $delv_sig = $signatures['sender_sig'] ?? null;
        if (empty($delv_sig)) $delv_sig = $signatures['sender_signature'] ?? null;
        if (empty($delv_sig)) $delv_sig = $signatures['signer_sig'] ?? null;
        if (empty($delv_sig)) $delv_sig = $attachmentsMeta['deliverer_sig'] ?? null;
    }

    // === Handover Date/Time (ใช้วันที่ตรวจสถานที่เกิดเหตุ) ===
    $handoverDate = '';
    $handoverTime = '';
    $handoverDateThai = '';
    $handoverTimeThai = '';
    if (!empty($inspectionDatetimeRaw)) {
        $ts = strtotime($inspectionDatetimeRaw);
        if ($ts !== false) {
            $handoverDate = date('Y-m-d', $ts);
            $handoverTime = date('H:i', $ts);
            // Thai format
            $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
            $handoverDateThai = date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
            $handoverTimeThai = date('H:i', $ts);
        }
    }
    if (empty($handoverDate)) {
        $handoverDate = getVal($handover, 'inspection_end_date');
        $handoverTime = getVal($handover, 'inspection_end_time');
    }

    // === Thai date format for incident/inspect ===
    $incidentDateThai = '';
    $incidentTimeThai = '';
    if (!empty($incidentDatetimeRaw)) {
        $ts = strtotime($incidentDatetimeRaw);
        if ($ts !== false) {
            $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
            $incidentDateThai = date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
            $incidentTimeThai = date('H:i', $ts);
        }
    }
    
    $inspectDateThai = '';
    $inspectTimeThai = '';
    if (!empty($inspectionDatetimeRaw)) {
        $ts = strtotime($inspectionDatetimeRaw);
        if ($ts !== false) {
            $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
            $inspectDateThai = date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
            $inspectTimeThai = date('H:i', $ts);
        }
    }

    // ==========================================
    // BUILD RESPONSE
    // ==========================================
    $response = [
        'status' => 'success',
        'data' => [
            'incident_id' => $incident_id,
            'report_no' => $reportNo,
            'source_station' => normalizeText($sourceStation),
            'report_day' => $reportDateParts['day'],
            'report_month' => $reportDateParts['month'],
            'report_year' => $reportDateParts['year'],
            'channels' => $channels,
            'channel_other_txt' => normalizeText($channelOtherText),
            'doc_no' => normalizeText($docNo),
            'case_type_text' => normalizeText($caseTypeText),
            'incident_location' => normalizeText($incidentLocation),
            'incident_date' => !empty($incidentDatetimeRaw) ? date('Y-m-d', strtotime($incidentDatetimeRaw)) : '',
            'incident_time' => !empty($incidentDatetimeRaw) ? date('H:i', strtotime($incidentDatetimeRaw)) : '',
            'incident_date_thai' => $incidentDateThai,
            'incident_time_thai' => $incidentTimeThai,
            'inspect_date' => !empty($inspectionDatetimeRaw) ? date('Y-m-d', strtotime($inspectionDatetimeRaw)) : '',
            'inspect_time' => !empty($inspectionDatetimeRaw) ? date('H:i', strtotime($inspectionDatetimeRaw)) : '',
            'inspect_date_thai' => $inspectDateThai,
            'inspect_time_thai' => $inspectTimeThai,
            'victim_name' => normalizeText(!empty($fcs11['victim_name']) ? $fcs11['victim_name'] : $victimName),
            'victim_age' => normalizeText(!empty($fcs11['victim_age']) ? $fcs11['victim_age'] : $victimAge),
            'officer_name' => normalizeText($officerName),
            'police_unit' => normalizeText(!empty($fcs11['police_unit']) ? $fcs11['police_unit'] : ''),
            'province' => normalizeText(!empty($fcs11['province']) ? $fcs11['province'] : $province),
            'evidences' => $evidenceList,
            'receiver_name' => normalizeText($recv_name),
            'receiver_position' => normalizeText($recv_pos),
            'receiver_sig' => $recv_sig,
            'deliverer_name' => normalizeText($delv_name),
            'deliverer_position' => normalizeText($delv_pos),
            'deliverer_sig' => $delv_sig,
            'handover_date' => $handoverDate,
            'handover_time' => $handoverTime,
            'handover_date_thai' => $handoverDateThai,
            'handover_time_thai' => $handoverTimeThai,
            '_debug' => [
                'using_fcs_data' => $usingFcsData,
                'fcs_data_exists' => !empty($fcsDataRaw),
                'has_recv_sig_in_fcs' => isset($fcsData['handover']['receiver_sig']) || isset($fcsData['signatures']['receiver_sig']),
                'has_delv_sig_in_fcs' => isset($fcsData['handover']['deliverer_sig']) || isset($fcsData['signatures']['deliverer_sig'])
            ]
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
