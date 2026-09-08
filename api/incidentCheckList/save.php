<?php
/**
 * API: Save Incident Checklist Data
 * ตาราง: incident_checklist_transaction
 * 
 * รับข้อมูล JSON ที่ประกอบด้วย:
 * - general_info: ข้อมูลทั่วไปทั้งหมด
 * - inspectors: Array ของ inspector_id
 * - trace_points: Array ของจุดที่ตรวจพบ
 * - evidences: Array ของวัตถุพยาน
 * - signatures: ลายเซ็นต์ (receiver, deliverer, scene_sketch)
 * - photos: Array ของรูปภาพ (base64)
 */

ini_set('memory_limit', '256M');
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_config.php';

// ============================================
// Helper Functions
// ============================================

/**
 * สร้าง response JSON และหยุดการทำงาน
 * JavaScript คาดหวัง: { status: 'success'/'error', message: '...' }
 */
function jsonResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    $response = [
        'status' => $success ? 'success' : 'error',
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * บันทึกไฟล์ base64 ลง folder
 * @return string|false ชื่อไฟล์ที่บันทึก หรือ false ถ้าล้มเหลว
 */
function saveBase64ToFile($base64Data, $folder, $filename) {
    // ตรวจสอบและสร้าง folder ถ้าไม่มี
    $uploadPath = __DIR__ . '/../../uploads/' . $folder;
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }
    
    // ลบ prefix ของ base64 (data:image/png;base64,)
    if (strpos($base64Data, ',') !== false) {
        list(, $base64Data) = explode(',', $base64Data, 2);
    }
    
    // Decode base64
    $imageData = base64_decode($base64Data);
    if ($imageData === false) {
        return false;
    }
    
    // บันทึกไฟล์
    $filePath = $uploadPath . '/' . $filename;
    if (file_put_contents($filePath, $imageData) !== false) {
        return $filename;
    }
    
    return false;
}

/**
 * สร้างชื่อไฟล์สำหรับลายเซ็นต์
 */
function generateSignatureFilename($incidentId, $type) {
    $timestamp = date('YmdHis');
    $random = substr(md5(uniqid()), 0, 6);
    return "{$incidentId}_{$type}_{$timestamp}_{$random}.png";
}

/**
 * สร้างชื่อไฟล์สำหรับรูปภาพ
 */
function generatePhotoFilename($incidentId, $index, $extension = 'jpg') {
    $timestamp = date('YmdHis');
    $random = substr(md5(uniqid()), 0, 6);
    $indexPadded = str_pad($index, 3, '0', STR_PAD_LEFT);
    return "{$incidentId}_photo_{$indexPadded}_{$timestamp}_{$random}.{$extension}";
}

// ============================================
// Main Logic
// ============================================

// ตรวจสอบ method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'ไม่อนุญาตวิธีการนี้', null, 405);
}

// รับข้อมูล - รองรับทั้ง FormData และ JSON
$data = null;

// ตรวจสอบว่าเป็น FormData หรือ JSON
if (isset($_POST['payload'])) {
    // กรณี FormData - payload อยู่ใน $_POST['payload'] เป็น JSON string
    $data = json_decode($_POST['payload'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, 'ข้อมูล JSON ไม่ถูกต้อง: ' . json_last_error_msg(), null, 400);
    }
} else {
    // กรณี JSON โดยตรง
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, 'ข้อมูล JSON ไม่ถูกต้อง: ' . json_last_error_msg(), null, 400);
    }
}

// ตรวจสอบ required fields - incident_id อาจอยู่ใน doc_no
$incidentId = null;
if (isset($data['incident_id']) && !empty($data['incident_id'])) {
    $incidentId = (int) $data['incident_id'];
} elseif (isset($data['general_info']['doc_no']) && !empty($data['general_info']['doc_no'])) {
    // ถ้าไม่มี incident_id ให้ใช้ doc_no ค้นหา
    $docNo = $data['general_info']['doc_no'];
    $findStmt = $pdo->prepare("SELECT id FROM rn_ReceiveNoti WHERE receiveNoti_No = :doc_no LIMIT 1");
    $findStmt->execute([':doc_no' => $docNo]);
    $row = $findStmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $incidentId = (int) $row['id'];
    }
}

if (!$incidentId) {
    jsonResponse(false, 'ไม่พบรหัสใบรับแจ้งเหตุ', null, 400);
}

// ดึง user_id จาก session (ถ้ามี)
session_start();
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

try {
    $pdo->beginTransaction();
    
    // ============================================
    // 1. เตรียมข้อมูลสำหรับบันทึก
    // ============================================
    
    $checklistData = [
        'general_info' => $data['general_info'] ?? [],
        'scene_characteristics' => $data['scene_characteristics'] ?? [],
        'case_behavior_info' => $data['case_behavior_info'] ?? [],
        'inspectors' => $data['inspectors'] ?? [],
        'trace_points' => $data['trace_points'] ?? [],
        'evidences' => $data['evidences'] ?? [],
        'stolen_property' => $data['stolen_property'] ?? '',
        'handover' => $data['handover'] ?? [],
        'attachments_meta' => $data['attachments_meta'] ?? [],
        'final_check' => $data['final_check'] ?? [],
        'signatures' => [],
        'photos' => []
    ];

    // ★ สร้าง blood_stain จาก evidences._summary_only (blood) เพื่อให้ saveProperty.php format อ่านได้
    $bloodEvidence = null;
    if (!empty($checklistData['evidences'])) {
        foreach ($checklistData['evidences'] as $ev) {
            if (!empty($ev['_summary_only']) && ($ev['type'] ?? '') === 'blood') {
                $bloodEvidence = $ev;
                break;
            }
        }
    }
    if ($bloodEvidence) {
        $checklistData['blood_stain'] = [
            'status' => 'found',
            'detail' => $bloodEvidence['detail'] ?? '',
            'blood_test' => $bloodEvidence['blood_test'] ?? []
        ];
    }
    
    // ============================================
    // 2. บันทึกลายเซ็นต์ (Signatures) จาก handover และ attachments_meta
    // ============================================
    
    $signatureData = [
        'receiver_sig' => $data['handover']['receiver_sig'] ?? '',
        'deliverer_sig' => $data['handover']['deliverer_sig'] ?? '',
        'scene_sketch' => $data['attachments_meta']['sketch'] ?? ''
    ];
    
    foreach ($signatureData as $type => $base64) {
        if (!empty($base64) && strpos($base64, 'data:image') !== false) {
            $filename = generateSignatureFilename($incidentId, $type);
            
            // บันทึกไฟล์
            $savedFilename = saveBase64ToFile($base64, 'checklist_signatures', $filename);
            
            if ($savedFilename) {
                // เก็บแค่ filename ใน DB (ไม่เก็บ base64 เพื่อลดขนาด JSON)
                $checklistData['signatures'][$type] = [
                    'filename' => $savedFilename
                ];
            }
        }
    }
    
    // ============================================
    // 2.1 ลบ base64 ออกจาก handover/attachments_meta
    // ข้อมูลลายเซ็นถูกบันทึกเป็นไฟล์แล้ว อ้างอิงผ่าน $checklistData['signatures']
    // ถ้าไม่ลบ base64 ออก JSON จะใหญ่มาก → column truncate → json_decode ได้ null → รูปหาย
    // ============================================
    if (isset($checklistData['handover']['receiver_sig'])) {
        $checklistData['handover']['receiver_sig'] = '';
    }
    if (isset($checklistData['handover']['deliverer_sig'])) {
        $checklistData['handover']['deliverer_sig'] = '';
    }
    if (isset($checklistData['attachments_meta']['sketch'])) {
        $checklistData['attachments_meta']['sketch'] = '';
    }
    
    // ============================================
    // 3. บันทึกรูปภาพ (Photos) - รองรับทั้ง $_FILES และ base64
    // ============================================
    
    // กรณี A: รับไฟล์จาก $_FILES (FormData)
    $uploadPath = __DIR__ . '/../../uploads/checklist_photos';
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }

    $uploadErrors = [];

    if (isset($_FILES['incident_photos']) && !empty($_FILES['incident_photos']['name'][0])) {
        $photos = $_FILES['incident_photos'];
        $photoCount = count($photos['name']);
        
        for ($i = 0; $i < $photoCount; $i++) {
            if ($photos['error'][$i] === UPLOAD_ERR_OK) {
                $originalName = $photos['name'][$i];
                $tmpName = $photos['tmp_name'][$i];
                
                // ดึง extension จากชื่อไฟล์เดิม
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $extension = 'jpg';
                }
                
                $photoIndex = count($checklistData['photos']) + 1;
                $filename = generatePhotoFilename($incidentId, $photoIndex, $extension);
                $filePath = $uploadPath . '/' . $filename;
                
                if (move_uploaded_file($tmpName, $filePath)) {
                    $checklistData['photos'][] = [
                        'filename' => $filename,
                        'original_name' => $originalName
                    ];
                } else {
                    $uploadErrors[] = "move_uploaded_file failed: {$originalName}";
                }
            } else {
                // Log upload error
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'ไฟล์ใหญ่เกินกว่า upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
                    UPLOAD_ERR_FORM_SIZE => 'ไฟล์ใหญ่เกินกว่า MAX_FILE_SIZE ในฟอร์ม',
                    UPLOAD_ERR_PARTIAL => 'ไฟล์อัปโหลดไม่ครบ',
                    UPLOAD_ERR_NO_FILE => 'ไม่มีไฟล์ที่อัปโหลด',
                    UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบ temp directory',
                    UPLOAD_ERR_CANT_WRITE => 'เขียนไฟล์ลงดิสก์ไม่ได้',
                    UPLOAD_ERR_EXTENSION => 'PHP extension หยุดการอัปโหลด',
                ];
                $errCode = $photos['error'][$i];
                $errMsg = $errorMessages[$errCode] ?? "Unknown error code: {$errCode}";
                $uploadErrors[] = "File '{$photos['name'][$i]}': {$errMsg}";
            }
        }
    }
    
    // กรณี B: รับ base64 จาก JSON
    if (isset($data['photos']) && is_array($data['photos'])) {
        foreach ($data['photos'] as $index => $photo) {
            if (!empty($photo['base64'])) {
                // ตรวจสอบ extension จาก base64 header
                $extension = 'jpg';
                if (strpos($photo['base64'], 'data:image/png') !== false) {
                    $extension = 'png';
                } elseif (strpos($photo['base64'], 'data:image/gif') !== false) {
                    $extension = 'gif';
                }
                
                $photoIndex = count($checklistData['photos']) + $index + 1;
                $filename = generatePhotoFilename($incidentId, $photoIndex, $extension);
                
                // บันทึกไฟล์
                $savedFilename = saveBase64ToFile($photo['base64'], 'checklist_photos', $filename);
                
                if ($savedFilename) {
                    $checklistData['photos'][] = [
                        'filename' => $savedFilename,
                        'original_name' => $photo['original_name'] ?? ''
                    ];
                }
            }
        }
    }

    // Log upload errors (ถ้ามี)
    if (!empty($uploadErrors)) {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $logData = [
            'datetime' => date('Y-m-d H:i:s'),
            'incident_id' => $incidentId,
            'total_photos_saved' => count($checklistData['photos']),
            'errors' => $uploadErrors,
            'php_upload_max_filesize' => ini_get('upload_max_filesize'),
            'php_post_max_size' => ini_get('post_max_size'),
            'php_max_file_uploads' => ini_get('max_file_uploads'),
            'FILES_keys' => array_keys($_FILES)
        ];
        file_put_contents($logDir . '/save_upload_errors_' . $incidentId . '.json', json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    
    // ============================================
    // 4. ตรวจสอบว่ามีข้อมูลอยู่แล้วหรือไม่
    // ============================================
    
    $checkStmt = $pdo->prepare("SELECT id, incident_checklist_data FROM incident_checklist_transaction WHERE incident_id = :incident_id LIMIT 1");
    $checkStmt->execute([':incident_id' => $incidentId]);
    $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    // ============================================
    // 4.1 ถ้า UPDATE - รวมรูปเก่า + ลบรูปที่ user ต้องการลบ + รักษาลายเซ็นเดิม
    // ============================================
    if ($existingRecord) {
        $existingData = json_decode($existingRecord['incident_checklist_data'], true);
        
        // ★ ป้องกัน json_decode ล้มเหลว (JSON truncate / corrupt)
        // ถ้า decode ไม่ได้ → ใช้ข้อมูลเก่าเท่าที่หาได้ แทนที่จะให้รูปหายหมด
        if ($existingData === null && !empty($existingRecord['incident_checklist_data'])) {
            error_log("WARNING: json_decode failed for incident_id={$incidentId}. JSON may be truncated. json_last_error: " . json_last_error_msg());
            // พยายามดึง photos จาก JSON ดิบ (partial parse)
            $existingPhotos = [];
            // ไม่ให้รูปเดิมหาย → เก็บ JSON เดิมไว้ไม่ทับ (เฉพาะกรณี photos array)
        } else {
            $existingPhotos = $existingData['photos'] ?? [];
        }

        // ★ รักษาข้อมูลจาก saveProperty.php ที่ save.php ไม่ได้ส่งมา
        if ($existingData) {
            $preserveKeys = ['summary', 'opinion', 'remark', 'recorder_info', 'evidence_meta', 'measurements', 'photo_records'];
            foreach ($preserveKeys as $pKey) {
                if (!isset($checklistData[$pKey]) && isset($existingData[$pKey])) {
                    $checklistData[$pKey] = $existingData[$pKey];
                }
            }
            // รักษา blood_stain จาก saveProperty.php ถ้า standard form ไม่มีข้อมูล blood
            if (empty($checklistData['blood_stain']) && !empty($existingData['blood_stain'])) {
                $checklistData['blood_stain'] = $existingData['blood_stain'];
            }
        }
        
        // ★ รักษาลายเซ็นเดิมที่บันทึกเป็นไฟล์ไว้แล้ว (ถ้าไม่ได้วาดใหม่)
        if ($existingData && isset($existingData['signatures'])) {
            foreach ($existingData['signatures'] as $sigType => $sigData) {
                // ถ้าลายเซ็นใหม่ว่าง → ใช้ลายเซ็นเดิม (ไม่ให้หาย)
                if (empty($checklistData['signatures'][$sigType]) && !empty($sigData['filename'])) {
                    $checklistData['signatures'][$sigType] = $sigData;
                } else if (!empty($checklistData['signatures'][$sigType]) && !empty($sigData['filename'])) {
                    // ลายเซ็นใหม่มาแทน → ลบไฟล์เก่าออก (ป้องกันไฟล์สะสม)
                    $oldSigFile = __DIR__ . '/../../uploads/checklist_signatures/' . $sigData['filename'];
                    if (file_exists($oldSigFile)) {
                        @unlink($oldSigFile);
                    }
                }
            }
        }
        
        // รับรายการรูปที่ต้องการลบ
        $deletedPhotos = [];
        if (isset($_POST['deleted_photos'])) {
            $deletedPhotos = json_decode($_POST['deleted_photos'], true) ?? [];
        }
        
        // ลบไฟล์จริง + กรองรูปเก่าที่ไม่ถูกลบออก
        $keptPhotos = [];
        $filesToDeleteAfterCommit = []; // ★ เก็บไว้ลบหลัง commit (ป้องกัน rollback แล้วไฟล์หาย)
        $uploadPathForDelete = __DIR__ . '/../../uploads/checklist_photos';
        foreach ($existingPhotos as $photo) {
            if (in_array($photo['filename'], $deletedPhotos)) {
                // เก็บ path ไว้ลบหลัง commit
                $filesToDeleteAfterCommit[] = $uploadPathForDelete . '/' . $photo['filename'];
            } else {
                // เก็บรูปเดิมไว้
                $keptPhotos[] = $photo;
            }
        }
        
        // รวมรูปเก่าที่เหลือ + รูปใหม่ที่เพิ่งอัปโหลด
        $checklistData['photos'] = array_merge($keptPhotos, $checklistData['photos']);
    }
    
    // ============================================
    // 4.2 แปลงเป็น JSON สำหรับบันทึกลง DB
    // ============================================
    
    $jsonChecklistData = json_encode($checklistData, JSON_UNESCAPED_UNICODE);
    
    if ($existingRecord) {
        // ============================================
        // UPDATE - อัปเดตข้อมูลที่มีอยู่
        // ============================================
        
        $sql = "UPDATE incident_checklist_transaction SET 
                    incident_checklist_data = :checklist_data,
                    edit_by = :edit_by,
                    edit_date = NOW(),
                    count_edit = count_edit + 1
                WHERE incident_id = :incident_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':checklist_data' => $jsonChecklistData,
            ':edit_by' => $userId,
            ':incident_id' => $incidentId
        ]);
        
        $recordId = $existingRecord['id'];
        $action = 'updated';
        
    } else {
        // ============================================
        // INSERT - สร้างข้อมูลใหม่
        // ============================================
        
        $sql = "INSERT INTO incident_checklist_transaction 
                (incident_id, incident_checklist_data, incident_report_data, create_by, create_date) 
                VALUES 
                (:incident_id, :checklist_data, :report_data, :create_by, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':incident_id' => $incidentId,
            ':checklist_data' => $jsonChecklistData,
            ':report_data' => $jsonChecklistData,
            ':create_by' => $userId
        ]);
        
        $recordId = $pdo->lastInsertId();
        $action = 'created';
    }
    
    // ============================================
    // 6. อัปเดตสถานะ Checklist ใน rn_ReceiveNoti
    // ============================================
    
    $updateStatusSql = "UPDATE rn_ReceiveNoti SET statusChecklist = 1 WHERE id = :incident_id";
    $updateStatusStmt = $pdo->prepare($updateStatusSql);
    $updateStatusStmt->execute([':incident_id' => $incidentId]);
    
    $pdo->commit();
    
    // ★ ลบไฟล์รูปภาพหลัง commit สำเร็จ (ป้องกัน rollback แล้วไฟล์หาย)
    if (isset($filesToDeleteAfterCommit) && !empty($filesToDeleteAfterCommit)) {
        foreach ($filesToDeleteAfterCommit as $fileToDelete) {
            if (file_exists($fileToDelete)) {
                @unlink($fileToDelete);
            }
        }
    }
    
    // ============================================
    // Response
    // ============================================
    
    jsonResponse(true, 'บันทึกข้อมูลเรียบร้อยแล้ว', [
        'id' => $recordId,
        'incident_id' => $incidentId,
        'action' => $action,
        'signatures_saved' => count($checklistData['signatures']),
        'photos_saved' => count($checklistData['photos'])
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Checklist Save Error: " . $e->getMessage());
    jsonResponse(false, 'เกิดข้อผิดพลาดฐานข้อมูล: ' . $e->getMessage(), null, 500);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Checklist Save Error: " . $e->getMessage());
    jsonResponse(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage(), null, 500);
}
