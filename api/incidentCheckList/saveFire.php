<?php

/**
 * API: Save Fire Incident Checklist Data
 * ตาราง: incident_checklist_transaction
 */

ini_set('memory_limit', '256M');
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_config.php';
require_once __DIR__ . '/lab_unit_helper.php';

// ============================================
// Helper Functions
// ============================================
function jsonResponse($success, $message, $data = null, $httpCode = 200)
{
    http_response_code($httpCode);
    $response = [
        'status' => $success ? 'success' : 'error',
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

function saveBlobToDb($pdo, $incidentId, $blobData)
{
    $stmt = $pdo->prepare("INSERT INTO incident_checklist_transaction_file (id_incident, file_name) VALUES (?, ?)");
    $stmt->execute([$incidentId, $blobData]);
    return (int) $pdo->lastInsertId();
}

function deleteBlobFromDb($pdo, $fileId)
{
    $stmt = $pdo->prepare("DELETE FROM incident_checklist_transaction_file WHERE id = ?");
    $stmt->execute([$fileId]);
}

function compressImage($blobData, $maxBytes = 2097152) {
    if (strlen($blobData) <= $maxBytes) return $blobData;
    $img = @imagecreatefromstring($blobData);
    if ($img === false) return $blobData;
    $quality = 85;
    $compressed = $blobData;
    while ($quality >= 10) {
        ob_start(); imagejpeg($img, null, $quality); $output = ob_get_clean();
        if (strlen($output) <= $maxBytes) { $compressed = $output; break; }
        $compressed = $output; $quality -= 10;
    }
    if (strlen($compressed) > $maxBytes) {
        $w = imagesx($img); $h = imagesy($img);
        $resized = imagecreatetruecolor((int)($w*0.5), (int)($h*0.5));
        imagecopyresampled($resized, $img, 0,0,0,0, (int)($w*0.5),(int)($h*0.5), $w,$h);
        $quality = 80;
        while ($quality >= 10) {
            ob_start(); imagejpeg($resized, null, $quality); $output = ob_get_clean();
            if (strlen($output) <= $maxBytes) { $compressed = $output; break; }
            $compressed = $output; $quality -= 10;
        }
        imagedestroy($resized);
    }
    imagedestroy($img);
    return $compressed;
}

// ============================================
// Main Logic
// ============================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed', null, 405);
}

$data = null;

if (isset($_POST['payload'])) {
    $data = json_decode($_POST['payload'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, 'Invalid JSON in payload: ' . json_last_error_msg(), null, 400);
    }
} else {
    $rawData = file_get_contents('php://input');
    if (!empty($rawData)) {
        $data = json_decode($rawData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $data = $_POST;
        }
    } else {
        $data = $_POST;
    }
}

// ตรวจสอบ required fields
$incidentId = null;
if (isset($data['receiveNoti_id_fire']) && !empty($data['receiveNoti_id_fire'])) {
    $incidentId = (int) $data['receiveNoti_id_fire'];
} elseif (isset($data['receiveNoti_id']) && !empty($data['receiveNoti_id'])) {
    $incidentId = (int) $data['receiveNoti_id'];
} elseif (isset($data['incident_id']) && !empty($data['incident_id'])) {
    $incidentId = (int) $data['incident_id'];
} elseif (isset($data['doc_no_fire']) && !empty($data['doc_no_fire'])) {
    $docNo = $data['doc_no_fire'];
    $findStmt = $pdo->prepare("SELECT id FROM rn_ReceiveNoti WHERE receiveNoti_No = :doc_no LIMIT 1");
    $findStmt->execute([':doc_no' => $docNo]);
    $row = $findStmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $incidentId = (int) $row['id'];
    }
}

if (!$incidentId) jsonResponse(false, 'ไม่พบรหัสใบรับแจ้งเหตุ', null, 400);

session_start();
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

try {
    $pdo->beginTransaction();

    // --- ดึงข้อมูลเดิม ---
    $stmtOld = $pdo->prepare("SELECT id, incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = ? LIMIT 1");
    $stmtOld->execute([$incidentId]);
    $oldRow = $stmtOld->fetch(PDO::FETCH_ASSOC);

    $oldData = null;
    $existingPhotos = [];
    if ($oldRow && !empty($oldRow['incident_checklist_data'])) {
        $oldData = json_decode($oldRow['incident_checklist_data'], true);
        if ($oldData !== null) {
            $existingPhotos = $oldData['photos'] ?? [];
        }
    }

    // ดึง existing file_ids ของ signatures จาก JSON เดิม
    $existingSigFileIds = [];
    if ($oldData && isset($oldData['signatures'])) {
        foreach ($oldData['signatures'] as $sKey => $sVal) {
            if (!empty($sVal['file_id'])) {
                $existingSigFileIds[$sKey] = (int) $sVal['file_id'];
            }
        }
    }

    // โครงสร้างข้อมูลสำหรับบันทึก
    $checklistData = [
        'general_info' => [
            'report_no' => $data['report_no_fire'] ?? '',
            'doc_no' => $data['doc_no_fire'] ?? '',
            'case_type' => 'fire',
            'case_type_other' => '',
            'case_doc_no' => $data['case_doc_no_fire'] ?? '',
            'report_date' => $data['fire_report_date'] ?? '',
            'report_time' => $data['fire_report_time'] ?? '',
            'report_datetime' => !empty($data['fire_report_date'])
                ? ($data['fire_report_date'] . 'T' . ($data['fire_report_time'] ?? '00:00'))
                : (!empty($data['case_date']) ? ($data['case_date'] . 'T' . ($data['case_time'] ?? '00:00')) : ''),
            'notify_method' => $data['fire_notify_method'] ?? [],
            'notify_method_other_text' => $data['fire_notify_method_other_text'] ?? '',
            'report_channel' => $data['fire_notify_method'] ?? [],
            'report_channel_other' => $data['fire_notify_method_other_text'] ?? '',
            'document_no' => $data['fire_document_no'] ?? ($data['case_doc_no_fire'] ?? ($data['doc_no_fire'] ?? '')),
            'source_station' => $data['police_station_fire'] ?? ($data['police_station'] ?? ''),
            'document_date' => $data['fire_document_date'] ?? '',
            'location_detail' => $data['fire_incident_location'] ?? ($data['crime_location'] ?? ''),
            'incident_datetime' => !empty($data['fire_victim_known_date'])
                ? ($data['fire_victim_known_date'] . 'T' . ($data['fire_victim_known_time'] ?? '00:00'))
                : (!empty($data['victim_know_date']) ? ($data['victim_know_date'] . 'T' . ($data['victim_know_time'] ?? '00:00')) : ''),
            'inspection_datetime' => !empty($data['fire_inspection_date'])
                ? ($data['fire_inspection_date'] . 'T' . ($data['fire_inspection_time'] ?? '00:00'))
                : (!empty($data['inspect_date']) ? ($data['inspect_date'] . 'T' . ($data['inspect_time'] ?? '00:00')) : ''),
            'investigator' => [
                'name' => $data['fire_investigator_name'] ?? ($data['investigator_name'] ?? ''),
                'firstname' => $data['fire_investigator_name'] ?? ($data['investigator_name'] ?? ''),
                'lastname' => '',
                'phone' => $data['fire_investigator_phone'] ?? ($data['investigator_phone'] ?? '')
            ],
            'victim' => [
                'name' => '',
                'firstname' => '',
                'lastname' => '',
                'age' => ''
            ]
        ],
        'scene_info' => [
            'incident_location' => $data['fire_incident_location'] ?? '',
            'persons' => []
        ],
        'datetime_info' => [
            'victim_known_date' => $data['fire_victim_known_date'] ?? '',
            'victim_known_time' => $data['fire_victim_known_time'] ?? '',
            'investigator_known_date' => $data['fire_investigator_known_date'] ?? '',
            'investigator_known_time' => $data['fire_investigator_known_time'] ?? '',
            'known_detail' => $data['fire_known_detail'] ?? '',
            'inspection_date' => $data['fire_inspection_date'] ?? '',
            'inspection_time' => $data['fire_inspection_time'] ?? '',
            'inspection_additional_date' => $data['fire_inspection_additional_date'] ?? '',
            'inspection_additional_time' => $data['fire_inspection_additional_time'] ?? ''
        ],
        'inspectors' => [],
        'scene_characteristics' => [
            'exterior' => [
                'detail' => $data['fire_exterior_detail'] ?? '',
                'floor_count' => $data['fire_floor_count'] ?? '',
                'fence' => $data['fire_fence'] ?? '',
                'front' => $data['fire_scene_front'] ?? '',
                'back' => $data['fire_scene_back'] ?? '',
                'left' => $data['fire_scene_left'] ?? '',
                'right' => $data['fire_scene_right'] ?? ''
            ],
            'interior' => [
                'detail' => $data['fire_interior_detail'] ?? ''
            ],
            'incident_area' => [
                'detail' => $data['fire_incident_area_detail'] ?? '',
                'size' => $data['fire_area_size'] ?? '',
                'facing_direction' => $data['fire_facing_direction'] ?? '',
                'structure' => [
                    'wall_front' => $data['fire_structure_wall_front'] ?? '',
                    'wall_left' => $data['fire_structure_wall_left'] ?? '',
                    'wall_right' => $data['fire_structure_wall_right'] ?? '',
                    'wall_back' => $data['fire_structure_wall_back'] ?? '',
                    'floor' => $data['fire_structure_floor'] ?? '',
                    'roof' => $data['fire_structure_roof'] ?? '',
                    'ceiling' => $data['fire_structure_ceiling'] ?? ''
                ],
                'objects' => [
                    'wall_front' => $data['fire_objects_wall_front'] ?? '',
                    'wall_left' => $data['fire_objects_wall_left'] ?? '',
                    'wall_right' => $data['fire_objects_wall_right'] ?? '',
                    'wall_back' => $data['fire_objects_wall_back'] ?? '',
                    'other' => $data['fire_objects_other'] ?? ''
                ]
            ]
        ],
        'case_behavior' => [
            'detail' => $data['fire_case_behavior'] ?? '',
            'insurance' => $data['fire_insurance'] ?? '',
            'burn_time' => $data['fire_burn_time'] ?? '',
            'extinguish' => $data['fire_extinguish'] ?? '',
            'extinguish_detail' => $data['fire_extinguish_detail'] ?? '',
            'damage_condition' => $data['fire_damage_condition'] ?? '',
            'spread_detail' => $data['fire_spread_detail'] ?? ''
        ],
        'damage' => [
            'structure' => [
                'wall_front' => $data['fire_damage_wall_front'] ?? '',
                'wall_left' => $data['fire_damage_wall_left'] ?? '',
                'wall_right' => $data['fire_damage_wall_right'] ?? '',
                'wall_back' => $data['fire_damage_wall_back'] ?? '',
                'floor' => $data['fire_damage_floor'] ?? '',
                'roof' => $data['fire_damage_roof'] ?? '',
                'ceiling' => $data['fire_damage_ceiling'] ?? ''
            ],
            'objects' => [
                'front' => $data['fire_damage_obj_front'] ?? '',
                'left' => $data['fire_damage_obj_left'] ?? '',
                'right' => $data['fire_damage_obj_right'] ?? '',
                'back' => $data['fire_damage_obj_back'] ?? '',
                'floor' => $data['fire_damage_obj_floor'] ?? '',
                'roof' => $data['fire_damage_obj_roof'] ?? '',
                'ceiling' => $data['fire_damage_obj_ceiling'] ?? ''
            ],
            'first_area' => $data['fire_first_area'] ?? '',
            'switch_condition' => $data['fire_switch_condition'] ?? '',
            'adjacent_damage' => $data['fire_adjacent_damage'] ?? '',
            'adjacent_damage_detail' => $data['fire_adjacent_damage_detail'] ?? '',
            'evidence_found' => $data['fire_evidence_found'] ?? ''
        ],
        'summary' => [
            'origin_area' => $data['fire_origin_area'] ?? '',
            'fuel_source' => $data['fire_fuel_source'] ?? '',
            'heat_source' => $data['fire_heat_source'] ?? '',
            'other' => $data['fire_summary_other'] ?? ''
        ],
        'opinion' => [
            'first_area' => $data['fire_opinion_first_area'] ?? '',
            'cause_type' => $data['fire_cause_type'] ?? '',
            'cause_believed_detail' => $data['fire_cause_believed_detail'] ?? '',
            'cause_unknown_detail' => $data['fire_cause_unknown_detail'] ?? ''
        ],
        'photo_records' => [
            'inspect_date' => $data['photo_inspect_date_fire'] ?? '',
            'inspect_time' => $data['photo_inspect_time_fire'] ?? '',
            'start' => $data['photo_id_start_fire'] ?? '',
            'end' => $data['photo_id_end_fire'] ?? '',
            'amount' => $data['photo_amount_fire'] ?? '',
            'recorder_name' => $data['recorder_name_fire'] ?? '',
            'recorder_datetime' => $data['recorder_datetime_fire'] ?? '',
            'photographer_name' => $data['photographer_name_fire'] ?? '',
            'photographer_datetime' => $data['photographer_datetime_fire'] ?? '',
            'investigator_select' => $data['fire_investigator_select'] ?? '',
            'investigator_phone_recorder' => $data['fire_investigator_phone_recorder'] ?? ''
        ],
        'handover' => [
            'inspection_end_date' => $data['fire_inspection_end_date'] ?? '',
            'inspection_end_time' => $data['fire_inspection_end_time'] ?? '',
            'receiver_name' => $data['receiver_name_fire'] ?? '',
            'receiver_position' => $data['receiver_position_fire'] ?? '',
            'sender_name' => $data['sender_name_fire'] ?? '',
            'sender_position' => $data['sender_position_fire'] ?? '',
            'receiver_id' => $data['receiver_name_fire'] ?? '',
            'receiver_pos' => $data['receiver_position_fire'] ?? '',
            'deliverer_id' => $data['sender_name_fire'] ?? '',
            'deliverer_pos' => $data['sender_position_fire'] ?? ''
        ],
        'remark' => $data['fire_remark'] ?? '',
        'sketch_remark' => $data['fire_sketch_remark'] ?? '',
        'sketch_recorder' => $data['fire_sketch_recorder'] ?? '',
        'sketch_datetime' => $data['fire_sketch_datetime'] ?? '',
        'signatures' => [],
        'photos' => [],
        'evidences' => [],
        'evidence_meta' => [
            'reference_point_1' => $data['reference_point_1_fire'] ?? '',
            'reference_point_2' => $data['reference_point_2_fire'] ?? '',
            'reference_point_3' => $data['reference_point_3_fire'] ?? '',
            'reference_point_4' => $data['reference_point_4_fire'] ?? ''
        ],
        'measurements' => [],
        'measurement_meta' => [
            'inspection_date' => $data['measurement_inspection_date_fire'] ?? ''
        ]
    ];

    // จัดการวัตถุพยาน (evidences)
    error_log("🔥 Evidence debug - evidence_item_fire isset: " . (isset($data['evidence_item_fire']) ? 'YES' : 'NO'));
    error_log("🔥 Evidence debug - evidence_item_fire is_array: " . (is_array($data['evidence_item_fire'] ?? null) ? 'YES' : 'NO'));
    error_log("🔥 Evidence debug - evidence_item_fire value: " . json_encode($data['evidence_item_fire'] ?? 'NOT SET'));
    error_log("🔥 Evidence debug - measurement_item_fire value: " . json_encode($data['measurement_item_fire'] ?? 'NOT SET'));
    if (isset($data['evidence_item_fire']) && is_array($data['evidence_item_fire'])) {
        foreach ($data['evidence_item_fire'] as $i => $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $level1 = $data['evidence_level_1_fire_' . $i] ?? '';
                $level2 = $data['evidence_level_2_fire_' . $i] ?? '';
                $level3 = $data['evidence_level_3_fire_' . $i] ?? '';
                $level4 = $data['evidence_level_4_fire_' . $i] ?? '';
                $checklistData['evidences'][] = [
                    'no' => $i + 1,
                    'detail' => $item,
                    'item' => $item,
                    'level_1' => $level1,
                    'level_2' => $level2,
                    'level_3' => $level3,
                    'level_4' => $level4,
                    'ref1_dist' => $level1,
                    'ref2_dist' => $level2,
                    'ref3_dist' => $level3,
                    'ref4_dist' => $level4,
                    'azimuth' => $data['evidence_azimuth_fire'][$i] ?? '',
                    'remark' => $data['evidence_remark_fire'][$i] ?? '',
                    'lab_unit' => labUnitsNormalize($data['evidence_lab_unit_fire'][$i] ?? '')
                ];
            }
        }
    }

    // Fallback: ถ้า evidences ว่าง แต่มี measurements ให้สร้างรายการสำหรับ F-CS-11
    if (empty($checklistData['evidences']) && isset($data['measurement_item_fire']) && is_array($data['measurement_item_fire'])) {
        foreach ($data['measurement_item_fire'] as $i => $item) {
            $item = trim((string)$item);
            if ($item === '') {
                continue;
            }
            $checklistData['evidences'][] = [
                'no' => $i + 1,
                'detail' => $item,
                'item' => $item,
                'lab_unit' => labUnitsNormalize($data['evidence_lab_unit_fire'][$i] ?? '')
            ];
        }
    }

    // จัดการบันทึกการตรวจเก็บวัตถุพยาน (measurements)
    if (isset($data['measurement_item_fire']) && is_array($data['measurement_item_fire'])) {
        foreach ($data['measurement_item_fire'] as $i => $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $checklistData['measurements'][] = [
                    'item' => $item,
                    'quantity' => $data['measurement_quantity_fire'][$i] ?? '',
                    'area' => $data['measurement_area_fire'][$i] ?? '',
                    'label_number' => $data['measurement_label_number_fire'][$i] ?? '',
                    'package_plastic' => !empty($data['measurement_package_plastic_check_fire_' . $i]),
                    'package_plastic_text' => $data['measurement_package_plastic_text_fire_' . $i] ?? '',
                    'package_paper' => !empty($data['measurement_package_paper_check_fire_' . $i]),
                    'package_paper_text' => $data['measurement_package_paper_text_fire_' . $i] ?? '',
                    'package_other' => !empty($data['measurement_package_other_check_fire_' . $i]),
                    'package_other_text' => $data['measurement_package_other_text_fire_' . $i] ?? '',
                    'action_return' => !empty($data['measurement_action_return_check_fire_' . $i]),
                    'action_return_text' => $data['measurement_action_return_text_fire_' . $i] ?? '',
                    'action_other' => !empty($data['measurement_action_other_check_fire_' . $i]),
                    'action_other_text' => $data['measurement_action_other_text_fire_' . $i] ?? '',
                    'remark' => $data['measurement_remark_fire'][$i] ?? '',
                    'forensic_unit' => labUnitsNormalize($data['measurement_forensic_unit_fire'][$i] ?? '')
                ];
            }
        }
    }

    // จัดการผู้ตรวจ
    if (isset($data['fire_inspector_id']) && is_array($data['fire_inspector_id'])) {
        foreach ($data['fire_inspector_id'] as $i => $inspId) {
            if (!empty($inspId)) {
                $checklistData['inspectors'][] = [
                    'id' => $inspId,
                    'position' => $data['fire_inspector_position'][$i] ?? ''
                ];
            }
        }
    }

    // จัดการบุคคล (ผู้เสียหาย / ผู้บาดเจ็บ / ผู้เสียชีวิต)
    if (isset($data['fire_person_type']) && is_array($data['fire_person_type'])) {
        foreach ($data['fire_person_type'] as $i => $type) {
            $name = $data['fire_person_name'][$i] ?? '';
            if (!empty($name) || !empty($type)) {
                $checklistData['scene_info']['persons'][] = [
                    'type' => $type,
                    'name' => $name,
                    'age' => $data['fire_person_age'][$i] ?? '',
                    'remark' => $data['fire_person_remark'][$i] ?? ''
                ];
            }
        }
    }

    if (!empty($checklistData['scene_info']['persons'])) {
        $firstPerson = $checklistData['scene_info']['persons'][0];
        $checklistData['general_info']['victim'] = [
            'name' => $firstPerson['name'] ?? '',
            'firstname' => $firstPerson['name'] ?? '',
            'lastname' => '',
            'age' => $firstPerson['age'] ?? ''
        ];
    } elseif (!empty($data['fire_victim_name'])) {
        $checklistData['general_info']['victim'] = [
            'name' => $data['fire_victim_name'] ?? '',
            'firstname' => $data['fire_victim_name'] ?? '',
            'lastname' => '',
            'age' => $data['fire_victim_age'] ?? ''
        ];
    }

    // ===== ลายเซ็น / แผนผัง — บันทึกเป็น BLOB ใน incident_checklist_transaction_file =====
    $sigFileFields = [
        'scene_sketch' => 'sig_file_scene_sketch',
        'receiver_signature' => 'sig_file_receiver_signature',
        'sender_signature' => 'sig_file_sender_signature'
    ];

    foreach ($sigFileFields as $key => $fieldName) {
        if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
            $blobData = file_get_contents($_FILES[$fieldName]['tmp_name']);
            if ($blobData !== false && strlen($blobData) > 0) {
                // ลบ BLOB เก่าจาก DB
                if (!empty($existingSigFileIds[$key])) {
                    deleteBlobFromDb($pdo, $existingSigFileIds[$key]);
                }
                // บันทึก BLOB ใหม่
                $newFileId = saveBlobToDb($pdo, $incidentId, $blobData);
                $checklistData['signatures'][$key] = ['file_id' => $newFileId];

                // Standardize key names for F-CS-11 generator.
                if ($key === 'receiver_signature') {
                    $checklistData['signatures']['receiver_sig'] = ['file_id' => $newFileId];
                } elseif ($key === 'sender_signature') {
                    $checklistData['signatures']['deliverer_sig'] = ['file_id' => $newFileId];
                }
            }
        }
    }

    // ★ รักษาลายเซ็นเดิมที่บันทึกใน DB ไว้แล้ว (ถ้าไม่ได้วาดใหม่)
    if ($oldData && isset($oldData['signatures'])) {
        foreach ($oldData['signatures'] as $sigType => $sigData) {
            if (empty($checklistData['signatures'][$sigType]) && !empty($sigData['file_id'])) {
                $checklistData['signatures'][$sigType] = $sigData;
            }
        }
    }

    if (empty($checklistData['signatures']['receiver_sig']) && !empty($checklistData['signatures']['receiver_signature'])) {
        $checklistData['signatures']['receiver_sig'] = $checklistData['signatures']['receiver_signature'];
    }
    if (empty($checklistData['signatures']['deliverer_sig']) && !empty($checklistData['signatures']['sender_signature'])) {
        $checklistData['signatures']['deliverer_sig'] = $checklistData['signatures']['sender_signature'];
    }

    // ===== รูปภาพ — บันทึกเป็น BLOB ใน incident_checklist_transaction_file =====
    $newPhotos = [];
    $imageFields = ['incident_photos_fire', 'camera_input_fire'];
    foreach ($imageFields as $field) {
        if (isset($_FILES[$field])) {
            $files = $_FILES[$field];
            $fileCount = is_array($files['name']) ? count($files['name']) : 1;
            for ($i = 0; $i < $fileCount; $i++) {
                $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
                if ($error === UPLOAD_ERR_OK) {
                    $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                    $originalName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                    $blobData = file_get_contents($tmpName);
                    if ($blobData !== false && strlen($blobData) > 0) {
                        $blobData = compressImage($blobData, 2097152);
                        $newFileId = saveBlobToDb($pdo, $incidentId, $blobData);
                        $newPhotos[] = ['file_id' => $newFileId, 'filename' => $originalName];
                    }
                }
            }
        }
    }

    // รับรายการรูปที่ต้องการลบ (ส่งมาเป็น file_id)
    $deletedPhotoFileIds = [];
    if (isset($_POST['deleted_photo_file_ids'])) {
        $deletedPhotoFileIds = json_decode($_POST['deleted_photo_file_ids'], true) ?? [];
    } elseif (isset($data['deleted_photo_file_ids']) && is_array($data['deleted_photo_file_ids'])) {
        $deletedPhotoFileIds = $data['deleted_photo_file_ids'];
    }

    // กรองรูปเก่าที่ไม่ถูกลบ + ลบ BLOB จาก DB
    $keptPhotos = [];
    foreach ($existingPhotos as $photo) {
        $fid = $photo['file_id'] ?? 0;
        if (in_array($fid, $deletedPhotoFileIds)) {
            deleteBlobFromDb($pdo, $fid);
        } else {
            $keptPhotos[] = $photo;
        }
    }

    // รวมรูปเก่าที่เหลือ + รูปใหม่
    $checklistData['photos'] = array_merge($keptPhotos, $newPhotos);

    // บันทึก DB
    $json = json_encode($checklistData, JSON_UNESCAPED_UNICODE);

    $check = $pdo->prepare("SELECT id FROM incident_checklist_transaction WHERE incident_id = ? LIMIT 1");
    $check->execute([$incidentId]);
    $exists = $check->fetch();

    if ($exists) {
        $sql = "UPDATE incident_checklist_transaction 
                SET incident_checklist_data = ?, incident_report_data = ?, edit_by = ?, edit_date = NOW(),
                    count_edit = count_edit + 1
                WHERE incident_id = ?";
        $pdo->prepare($sql)->execute([$json, $json, $userId, $incidentId]);
    } else {
        $sql = "INSERT INTO incident_checklist_transaction 
                (incident_id, incident_checklist_data, incident_report_data, create_by, create_date) 
                VALUES (?, ?, ?, ?, NOW())";
        $pdo->prepare($sql)->execute([$incidentId, $json, $json, $userId]);
    }

    $pdo->prepare("UPDATE rn_ReceiveNoti SET statusChecklist = 1 WHERE id = ?")->execute([$incidentId]);

    $pdo->commit();

    jsonResponse(true, 'บันทึกข้อมูลเรียบร้อยแล้ว', [
        'photos_count' => count($checklistData['photos'])
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Fire Checklist Save Error: " . $e->getMessage());
    jsonResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage());
}
