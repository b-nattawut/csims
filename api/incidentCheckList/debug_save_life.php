<?php
/**
 * Debug: ดู POST data ที่ส่งมาจาก Life form
 * ใช้เพื่อตรวจสอบว่า FormData ส่งมาถูกต้องหรือไม่
 */
header('Content-Type: application/json; charset=utf-8');

$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'N/A',
    'post_count' => count($_POST),
    'files_count' => count($_FILES),
];

// Scene fields ที่ต้องการตรวจสอบ
$sceneFields = [
    'scene_preserved',
    'scene_preserved_no_text',
    'lighting',
    'lighting_other_text',
    'temperature',
    'temperature_other_text',
    'smell',
    'has_outdoor_incident_life',
    'outdoor_type',
    'outdoor_type_other_text',
    'outdoor_entrance_condition',
    'outdoor_front_adjacent',
    'outdoor_left_adjacent',
    'outdoor_right_adjacent',
    'outdoor_back_adjacent',
    'has_indoor_incident_life',
    'building_type',
    'entrance_condition',
    'front_adjacent',
    'receiveNoti_id',
    'doc_no',
    'report_no',
];

$result['scene_fields'] = [];
foreach ($sceneFields as $field) {
    $result['scene_fields'][$field] = $_POST[$field] ?? '❌ NOT_SET';
}

// All POST keys
$result['all_post_keys'] = array_keys($_POST);

// Raw input (first 500 chars)
$rawInput = file_get_contents('php://input');
$result['raw_input_length'] = strlen($rawInput);
$result['raw_input_preview'] = substr($rawInput, 0, 500);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
