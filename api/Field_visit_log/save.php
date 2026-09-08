<?php
/**
 * API: Field_visit_log/save.php
 * บันทึก/แก้ไข บันทึกภาคสนาม (INSERT + UPDATE)
 */
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
require __DIR__ . '/../../helpers/report_no.php';
header("Content-Type: application/json; charset=UTF-8");

// ตรวจสอบ Session
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId  = $_SESSION['user_id'];
$dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');

// รับค่าจากฟอร์ม
$id                = $_POST['fvl_id'] ?? '';
$report_no         = trim($_POST['fvl_report_no'] ?? '');
$report_no_display = trim($_POST['fvl_report_no_display'] ?? '');
$visit_date        = $_POST['fvl_visit_date'] ?? '';
$station_id   = $_POST['fvl_station'] ?? null;
$province_id  = $_POST['fvl_province'] ?? null;
$case_type_id = $_POST['fvl_case_title'] ?? null;
$location     = trim($_POST['fvl_location'] ?? '');
$description  = trim($_POST['fvl_description'] ?? '');

// รับ JSON ของรูปภาพเดิมที่ยังคงอยู่ (กรณี edit)
$existing_photos      = $_POST['existing_photos'] ?? '[]';
$existing_attachments = $_POST['existing_attachments'] ?? '[]';
$deleted_photos       = json_decode($_POST['deleted_photos'] ?? '[]', true) ?: [];
$deleted_attachments  = json_decode($_POST['deleted_attachments'] ?? '[]', true) ?: [];

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($visit_date)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุวันที่บันทึก'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ถ้าไม่มีค่าอังกฤษ แต่มีช่องแสดงผล — ใช้ค่าจากช่องแสดงผลเป็นต้นทาง
if ($report_no === '' && $report_no_display !== '') {
    $report_no = $report_no_display;
}
$report_no_th = $report_no_display !== '' ? $report_no_display : smartThaiReportOrDoc($report_no);
if ($report_no === '') {
    $report_no = null;
    $report_no_th = null;
}

// เพิ่มคอลัมน์ report_no_TH ถ้ายังไม่มี
try {
    $pdo->query("SELECT report_no_TH FROM field_visit_logs LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE field_visit_logs ADD COLUMN report_no_TH VARCHAR(50) NULL AFTER report_no");
}

// Upload directories
$photoDir = '../../uploads_field_visit/photos/';
$fileDir  = '../../uploads_field_visit/files/';

if (!is_dir($photoDir)) mkdir($photoDir, 0755, true);
if (!is_dir($fileDir))  mkdir($fileDir, 0755, true);

try {
    $pdo->beginTransaction();

    // --- จัดการรูปภาพ ---
    $photoList = json_decode($existing_photos, true) ?: [];

    // ลบรูปที่ถูกลบ
    foreach ($deleted_photos as $delFile) {
        $delPath = $photoDir . basename($delFile);
        if (file_exists($delPath)) unlink($delPath);
        $photoList = array_values(array_filter($photoList, function($p) use ($delFile) {
            return ($p['filename'] ?? $p) !== $delFile;
        }));
    }

    // อัปโหลดรูปใหม่
    if (!empty($_FILES['fvl_photos'])) {
        $files = $_FILES['fvl_photos'];
        $captions = $_POST['fvl_photo_captions'] ?? [];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

            $ext      = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $uniqueId = substr(md5(uniqid(mt_rand(), true)), 0, 6);
            $newName  = 'fvl_photo_' . date('YmdHis') . '_' . $uniqueId . '.' . $ext;

            if (move_uploaded_file($files['tmp_name'][$i], $photoDir . $newName)) {
                $photoList[] = [
                    'filename' => $newName,
                    'caption'  => $captions[$i] ?? '',
                    'original' => $files['name'][$i]
                ];
            }
        }
    }

    // --- จัดการไฟล์แนบ ---
    $attachList = json_decode($existing_attachments, true) ?: [];

    // ลบไฟล์ที่ถูกลบ
    foreach ($deleted_attachments as $delFile) {
        $delPath = $fileDir . basename($delFile);
        if (file_exists($delPath)) unlink($delPath);
        $attachList = array_values(array_filter($attachList, function($a) use ($delFile) {
            return ($a['filename'] ?? $a) !== $delFile;
        }));
    }

    // อัปโหลดไฟล์ใหม่
    if (!empty($_FILES['fvl_files'])) {
        $files = $_FILES['fvl_files'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

            $ext      = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $uniqueId = substr(md5(uniqid(mt_rand(), true)), 0, 6);
            $newName  = 'fvl_file_' . date('YmdHis') . '_' . $uniqueId . '.' . $ext;

            if (move_uploaded_file($files['tmp_name'][$i], $fileDir . $newName)) {
                $attachList[] = [
                    'filename' => $newName,
                    'original' => $files['name'][$i],
                    'size'     => $files['size'][$i]
                ];
            }
        }
    }

    $photosJson = json_encode($photoList, JSON_UNESCAPED_UNICODE);
    $attachJson = json_encode($attachList, JSON_UNESCAPED_UNICODE);

    if (empty($id)) {
        // ===================== INSERT =====================
        $sql = "INSERT INTO field_visit_logs (
                    report_no, report_no_TH, visit_date, station_id, province_id, case_type_id,
                    location, description, photos, attachments,
                    create_by, create_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $pdo->prepare($sql)->execute([
            $report_no,
            $report_no_th,
            $visit_date,
            $station_id ?: null,
            $province_id ?: null,
            $case_type_id ?: null,
            $location ?: null,
            $description ?: null,
            $photosJson,
            $attachJson,
            $userId,
            $dateNow
        ]);

        $newId = $pdo->lastInsertId();
        $pdo->commit();

        echo json_encode([
            'status'  => 'success',
            'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว',
            'id'      => $newId
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // ===================== UPDATE =====================
        // ตรวจสอบว่ามีข้อมูลอยู่จริง
        $chk = $pdo->prepare("SELECT id FROM field_visit_logs WHERE id = ? AND status_delete = 0");
        $chk->execute([$id]);
        if (!$chk->fetch()) {
            throw new Exception('ไม่พบข้อมูลที่ต้องการแก้ไข');
        }

        $sql = "UPDATE field_visit_logs SET
                    report_no    = ?,
                    report_no_TH = ?,
                    visit_date   = ?,
                    station_id   = ?,
                    province_id  = ?,
                    case_type_id = ?,
                    location     = ?,
                    description  = ?,
                    photos       = ?,
                    attachments  = ?,
                    edit_by      = ?,
                    edit_date    = ?
                WHERE id = ? AND status_delete = 0";

        $pdo->prepare($sql)->execute([
            $report_no,
            $report_no_th,
            $visit_date,
            $station_id ?: null,
            $province_id ?: null,
            $case_type_id ?: null,
            $location ?: null,
            $description ?: null,
            $photosJson,
            $attachJson,
            $userId,
            $dateNow,
            $id
        ]);

        $pdo->commit();

        echo json_encode([
            'status'  => 'success',
            'message' => 'แก้ไขข้อมูลเรียบร้อยแล้ว',
            'id'      => $id
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
