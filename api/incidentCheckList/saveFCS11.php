<?php
/**
 * saveFCS11.php - บันทึกข้อมูล F-CS-11 (แบบการตรวจเก็บและส่งมอบวัตถุพยาน)
 * รับ JSON POST data
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/lab_unit_helper.php';

// ==========================================
// MAIN
// ==========================================
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
    exit;
}

$incident_id = isset($data['incident_id']) ? intval($data['incident_id']) : 0;

if ($incident_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุ incident_id']);
    exit;
}

try {
    // ดึงข้อมูลเดิมจาก incident_checklist_transaction
    $stmt = $pdo->prepare("SELECT id, f_cs_data FROM incident_checklist_transaction WHERE incident_id = ? ORDER BY create_date DESC LIMIT 1");
    $stmt->execute([$incident_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Checklist สำหรับ incident_id: ' . $incident_id]);
        exit;
    }

    $existingData = json_decode($row['f_cs_data'], true);
    if (json_last_error() !== JSON_ERROR_NONE) $existingData = [];

    // ==========================================
    // UPDATE DATA
    // ==========================================
    
    // Update general_info
    if (!isset($existingData['general_info'])) $existingData['general_info'] = [];
    
    if (!empty($data['report_no'])) {
        $existingData['general_info']['report_no'] = $data['report_no'];
    }
    if (!empty($data['source_station'])) {
        $existingData['general_info']['source_station'] = $data['source_station'];
        $existingData['general_info']['police_station'] = $data['source_station'];
    }
    if (!empty($data['doc_no'])) {
        $existingData['general_info']['document_no'] = $data['doc_no'];
        $existingData['general_info']['doc_no'] = $data['doc_no'];
    }
    if (!empty($data['case_type_text'])) {
        $existingData['general_info']['case_type_text'] = $data['case_type_text'];
    }
    if (!empty($data['incident_location'])) {
        $existingData['general_info']['location_detail'] = $data['incident_location'];
    }
    
    // Channels
    if (!empty($data['channels']) && is_array($data['channels'])) {
        $existingData['general_info']['report_channel'] = $data['channels'];
        $existingData['general_info']['notify_method'] = $data['channels'];
    }
    if (isset($data['channel_other_txt'])) {
        $existingData['general_info']['report_channel_other'] = $data['channel_other_txt'];
        $existingData['general_info']['notify_method_other_text'] = $data['channel_other_txt'];
    }
    
    // Datetime
    if (!empty($data['incident_date'])) {
        $incidentDatetime = $data['incident_date'];
        if (!empty($data['incident_time'])) {
            $incidentDatetime .= 'T' . $data['incident_time'];
        }
        $existingData['general_info']['incident_datetime'] = $incidentDatetime;
    }
    
    if (!empty($data['inspect_date'])) {
        $inspectDatetime = $data['inspect_date'];
        if (!empty($data['inspect_time'])) {
            $inspectDatetime .= 'T' . $data['inspect_time'];
        }
        $existingData['general_info']['inspection_datetime'] = $inspectDatetime;
    }
    
    // Victim
    if (!isset($existingData['general_info']['victim'])) $existingData['general_info']['victim'] = [];
    if (!empty($data['victim_name'])) {
        $existingData['general_info']['victim']['name'] = $data['victim_name'];
    }
    if (!empty($data['victim_age'])) {
        $existingData['general_info']['victim']['age'] = $data['victim_age'];
    }
    
    // Investigator
    if (!isset($existingData['general_info']['investigator'])) $existingData['general_info']['investigator'] = [];
    if (!empty($data['officer_name'])) {
        $existingData['general_info']['investigator']['name'] = $data['officer_name'];
    }
    
    // Update handover
    if (!isset($existingData['handover'])) $existingData['handover'] = [];
    
    if (!empty($data['receiver_name'])) {
        $existingData['handover']['receiver_name'] = $data['receiver_name'];
    }
    if (!empty($data['receiver_position'])) {
        $existingData['handover']['receiver_pos'] = $data['receiver_position'];
        $existingData['handover']['receiver_position'] = $data['receiver_position'];
    }
    if (!empty($data['deliverer_name'])) {
        $existingData['handover']['deliverer_name'] = $data['deliverer_name'];
    }
    if (!empty($data['deliverer_position'])) {
        $existingData['handover']['deliverer_pos'] = $data['deliverer_position'];
        $existingData['handover']['deliverer_position'] = $data['deliverer_position'];
    }
    
    // Signatures
    if (!isset($existingData['signatures'])) $existingData['signatures'] = [];
    if (!isset($existingData['handover'])) $existingData['handover'] = [];
    
    // Receiver signature
    if (isset($data['receiver_sig'])) {
        if ($data['receiver_sig'] === 'CLEARED') {
            // User cleared the signature
            unset($existingData['handover']['receiver_sig']);
            unset($existingData['signatures']['receiver_sig']);
        } elseif (!empty($data['receiver_sig'])) {
            $existingData['handover']['receiver_sig'] = ['base64' => $data['receiver_sig']];
            $existingData['signatures']['receiver_sig'] = ['base64' => $data['receiver_sig']];
        }
    }
    
    // Deliverer signature
    if (isset($data['deliverer_sig'])) {
        if ($data['deliverer_sig'] === 'CLEARED') {
            // User cleared the signature
            unset($existingData['handover']['deliverer_sig']);
            unset($existingData['signatures']['deliverer_sig']);
        } elseif (!empty($data['deliverer_sig'])) {
            $existingData['handover']['deliverer_sig'] = ['base64' => $data['deliverer_sig']];
            $existingData['signatures']['deliverer_sig'] = ['base64' => $data['deliverer_sig']];
        }
    }
    
    // Update measurements (evidences)
    if (!empty($data['evidences']) && is_array($data['evidences'])) {
        $measurements = [];
        foreach ($data['evidences'] as $ev) {
            $measurements[] = [
                'no' => $ev['no'] ?? (count($measurements) + 1),
                'item' => $ev['item'] ?? '',
                'detail' => $ev['item'] ?? '',
                'forensic_unit' => labUnitsNormalize($ev['test'] ?? ''),
                'lab_unit' => labUnitsNormalize($ev['test'] ?? '')
            ];
        }
        $existingData['measurements'] = $measurements;
    }
    
    // Add FCS11 specific data
    $existingData['fcs11_data'] = [
        'report_day' => $data['report_day'] ?? '',
        'report_month' => $data['report_month'] ?? '',
        'report_year' => $data['report_year'] ?? '',
        'police_unit' => $data['police_unit'] ?? '',
        'province' => $data['province'] ?? '',
        'victim_name' => $data['victim_name'] ?? '',
        'victim_age' => $data['victim_age'] ?? '',
        'case_type_text' => $data['case_type_text'] ?? '',
        'handover_date' => $data['handover_date'] ?? '',
        'handover_time' => $data['handover_time'] ?? '',
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Also update general_info with these values
    $existingData['general_info']['report_day'] = $data['report_day'] ?? '';
    $existingData['general_info']['report_month'] = $data['report_month'] ?? '';
    $existingData['general_info']['report_year'] = $data['report_year'] ?? '';
    $existingData['general_info']['police_unit'] = $data['police_unit'] ?? '';
    $existingData['general_info']['case_type_text'] = $data['case_type_text'] ?? '';
    $existingData['general_info']['province'] = $data['province'] ?? '';
    if (!empty($data['victim_name'])) {
        $existingData['general_info']['victim']['name'] = $data['victim_name'];
    }
    if (!empty($data['victim_age'])) {
        $existingData['general_info']['victim']['age'] = $data['victim_age'];
    }

    // ==========================================
    // SAVE TO DATABASE (f_cs_data column)
    // ==========================================
    $jsonData = json_encode($existingData, JSON_UNESCAPED_UNICODE);
    
    $updateStmt = $pdo->prepare("UPDATE incident_checklist_transaction SET f_cs_data = ?, edit_date = NOW() WHERE id = ?");
    $result = $updateStmt->execute([$jsonData, $row['id']]);
    $rowsAffected = $updateStmt->rowCount();

    echo json_encode([
        'status' => 'success',
        'message' => 'บันทึกข้อมูล F-CS-11 สำเร็จ',
        'incident_id' => $incident_id,
        'rows_affected' => $rowsAffected,
        'record_id' => $row['id'],
        'has_receiver_sig' => isset($existingData['handover']['receiver_sig']),
        'has_deliverer_sig' => isset($existingData['handover']['deliverer_sig']),
        'saved_report_day' => $existingData['fcs11_data']['report_day'] ?? '',
        'saved_report_month' => $existingData['fcs11_data']['report_month'] ?? '',
        'saved_victim_name' => $existingData['fcs11_data']['victim_name'] ?? '',
        'saved_police_unit' => $existingData['fcs11_data']['police_unit'] ?? '',
        'json_length' => strlen($jsonData)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
