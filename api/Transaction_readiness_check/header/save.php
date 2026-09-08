<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

$current_user_id = $_SESSION['user_id'];

// ฟังก์ชันช่วยจัดการค่าว่างให้เป็น NULL
function getPostValue($key)
{
    $val = $_POST[$key] ?? '';
    return ($val === '') ? null : $val;
}

// --- รับข้อมูลจาก Form ---
$id = getPostValue('id');
$check_date = getPostValue('check_date');
$check_time = getPostValue('check_time');
$team_no    = getPostValue('team_no');
$team_readiness_status = getPostValue('team_readiness_status'); // COMPLETE / INCOMPLETE
$team_remark           = trim($_POST['team_remark'] ?? '');

// เจ้าหน้าที่ 3 คน
$checked_by      = getPostValue('checked_by');
$team_leader_id  = getPostValue('team_leader_id');
// รับค่าเจ้าหน้าที่เฉพาะสังกัด (มีทั้งแบบเลือกจากรายชื่อที่มี = ID และแบบพิมพ์เข้ามาเอง กรณีเป็นรายชื่อนอกเหนือจากขอบเขตที่มี = Name)
$unit_officer_id   = getPostValue('unit_officer_id');
$unit_officer_name = trim($_POST['unit_officer_name'] ?? '');
$unit_officer_position = trim($_POST['unit_officer_position'] ?? '');

// สังกัดเฉพาะ
$unit_category = getPostValue('unit_category');
$unit_detail   = getPostValue('unit_detail');

// ยานพาหนะและอุปกรณ์
$car_license  = trim($_POST['car_license'] ?? '');
$car_mileage  = getPostValue('car_mileage');
$bag_set_no   = trim($_POST['bag_set_no'] ?? '');
$other_set_no = trim($_POST['other_set_no'] ?? '');
$camera_brand = trim($_POST['camera_brand'] ?? '');
$camera_model = trim($_POST['camera_model'] ?? '');
$camera_sn = strtoupper(trim($_POST['camera_sn'] ?? ''));

// --- 2. Validation เบื้องต้น ---
// --- LOGIC: SYSTEM USER CHECK & SNAPSHOT ---
$isSystemUser = ($unit_category === 'ศพฐ.' && $unit_detail === '10') || 
                ($unit_category === 'พฐ.จว.' && in_array($unit_detail, ['ยะลา', 'ปัตตานี', 'นราธิวาส'])) ||
                ($unit_category === 'นวท.(สบ....)' && in_array($unit_detail, ['1', '2', '3', '4']));

if ($isSystemUser && !empty($unit_officer_id)) {
    // ดึงข้อมูล "ยศ ชื่อ นามสกุล" และ "ตำแหน่ง" ล่าสุดจาก DB มาทำ Snapshot
    $sqlSnap = "SELECT 
                    CONCAT(COALESCE(t3.rank_name,''), t2.first_name, ' ', t2.last_name) AS fullname,
                    t4.position_name
                FROM user_profile t2
                LEFT JOIN user_rank t3 ON t2.rank_id = t3.rank_id
                LEFT JOIN user_position t4 ON t2.position_id = t4.position_id
                WHERE t2.user_id = ?";
    $stmtSnap = $pdo->prepare($sqlSnap);
    $stmtSnap->execute([$unit_officer_id]);
    $snapData = $stmtSnap->fetch(PDO::FETCH_ASSOC);

    if ($snapData) {
        $unit_officer_name = $snapData['fullname'];
        $unit_officer_position = $snapData['position_name']; 
    }
}

// unit_officer: ต้องมีอย่างใดอย่างหนึ่ง (ID หรือ Name)
$has_officer = (!empty($unit_officer_id) || !empty($unit_officer_name));

if (
    empty($check_date) || empty($check_time) || empty($team_no) || empty($team_readiness_status) ||
    empty($checked_by) || empty($team_leader_id) || !$has_officer ||
    empty($car_license) || empty($bag_set_no)
) {

    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลสำคัญ (เครื่องหมาย *) ให้ครบถ้วน']);
    exit;
}

try {
    $pdo->beginTransaction();

    if (empty($id)) {
        // ----------------------------------------------------
        // 3. INSERT (สร้างรายการใหม่)
        // ----------------------------------------------------
        $sql = "INSERT INTO trans_readiness_check_header (
                    check_date, check_time, team_no, 
                    team_readiness_status, team_remark,
                    checked_by, team_leader_id, unit_category, unit_detail, 
                    unit_officer_id, unit_officer_name, unit_officer_position,
                    car_license, car_mileage, bag_set_no, other_set_no,
                    camera_brand, camera_model, camera_sn,
                    status, created_by, updated_by
                ) VALUES (
                    :check_date, :check_time, :team_no,
                    :team_readiness_status, :team_remark,
                    :checked_by, :team_leader_id, :unit_category, :unit_detail, 
                    :unit_officer_id, :unit_officer_name, :unit_officer_position,
                    :car_license, :car_mileage, :bag_set_no, :other_set_no,
                    :camera_brand, :camera_model, :camera_sn,
                    'DRAFT', :created_by, :updated_by
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':check_date'      => $check_date,
            ':check_time'      => $check_time,
            ':team_no'         => $team_no,
            ':team_readiness_status' => $team_readiness_status,
            ':team_remark'           => $team_remark,
            ':checked_by'      => $checked_by,
            ':team_leader_id'  => $team_leader_id,
            ':unit_category'   => $unit_category,
            ':unit_detail'     => $unit_detail,
            ':unit_officer_id' => $unit_officer_id,
            ':unit_officer_name'     => (!empty($unit_officer_name) ? $unit_officer_name : null),
            ':unit_officer_position' => (!empty($unit_officer_position) ? $unit_officer_position : null),
            ':car_license'     => $car_license,
            ':car_mileage'     => $car_mileage,
            ':bag_set_no'      => $bag_set_no,
            ':other_set_no'    => $other_set_no,
            ':camera_brand'    => $camera_brand,
            ':camera_model'    => $camera_model,
            ':camera_sn'       => $camera_sn,
            ':created_by'      => $current_user_id,
            ':updated_by'      => $current_user_id
        ]);

        $insert_id = $pdo->lastInsertId();
        $message = 'เริ่มบันทึกรายการความพร้อมเรียบร้อยแล้ว';
    } else {
        // ----------------------------------------------------
        // 4. UPDATE (แก้ไขข้อมูลเดิม)
        // ----------------------------------------------------

        // 4.1 ตรวจสอบข้อมูลเก่าเพื่อจัดการลายเซ็น
        $stmtOld = $pdo->prepare("SELECT checked_by, team_leader_id, unit_officer_id, unit_officer_name, unit_officer_position FROM trans_readiness_check_header WHERE id = ?");
        $stmtOld->execute([$id]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        // Logic: หากมีการเปลี่ยนตัวเจ้าหน้าที่ ให้ล้างลายเซ็นของตำแหน่งนั้นทิ้งทันที
        $clear_checked_sig = ($oldData['checked_by'] != $checked_by) ? ", checked_signature = NULL, checked_signed_at = NULL " : "";
        $clear_leader_sig  = ($oldData['team_leader_id'] != $team_leader_id) ? ", leader_signature = NULL, leader_signed_at = NULL " : "";
        // ล้างลายเซ็นหาก ชื่อ หรือ ตำแหน่ง
        $officer_changed = (
            $oldData['unit_officer_id'] != $unit_officer_id || 
            $oldData['unit_officer_name'] != $unit_officer_name || 
            $oldData['unit_officer_position'] != $unit_officer_position
        );
        $clear_unit_sig  = ($officer_changed) ? ", unit_officer_signature = NULL, unit_officer_signed_at = NULL " : "";

        $sql = "UPDATE trans_readiness_check_header SET 
                    check_date = :check_date, 
                    check_time = :check_time, 
                    team_no = :team_no,
                    team_readiness_status = :team_readiness_status,
                    team_remark = :team_remark,
                    checked_by = :checked_by, 
                    team_leader_id = :team_leader_id, 
                    unit_category = :unit_category, 
                    unit_detail = :unit_detail, 
                    unit_officer_id = :unit_officer_id,
                    unit_officer_name = :unit_officer_name,
                    unit_officer_position = :unit_officer_position,
                    car_license = :car_license, 
                    car_mileage = :car_mileage, 
                    bag_set_no = :bag_set_no, 
                    other_set_no = :other_set_no,
                    camera_brand = :camera_brand, 
                    camera_model = :camera_model, 
                    camera_sn = :camera_sn,
                    updated_by = :updated_by
                    $clear_checked_sig
                    $clear_leader_sig
                    $clear_unit_sig
                WHERE id = :id AND delete_token = 0";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':check_date'      => $check_date,
            ':check_time'      => $check_time,
            ':team_no'         => $team_no,
            ':team_readiness_status' => $team_readiness_status,
            ':team_remark'           => $team_remark,
            ':checked_by'      => $checked_by,
            ':team_leader_id'  => $team_leader_id,
            ':unit_category'   => $unit_category,
            ':unit_detail'     => $unit_detail,
            ':unit_officer_id' => $unit_officer_id,
            ':unit_officer_name'     => (!empty($unit_officer_name) ? $unit_officer_name : null),
            ':unit_officer_position' => (!empty($unit_officer_position) ? $unit_officer_position : null),
            ':car_license'     => $car_license,
            ':car_mileage'     => $car_mileage,
            ':bag_set_no'      => $bag_set_no,
            ':other_set_no'    => $other_set_no,
            ':camera_brand'    => $camera_brand,
            ':camera_model'    => $camera_model,
            ':camera_sn'       => $camera_sn,
            ':updated_by'      => $current_user_id,
            ':id'              => $id
        ]);

        $insert_id = $id;
        $message = 'แก้ไขข้อมูลเบื้องต้นเรียบร้อยแล้ว';
    }

    $pdo->commit();
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'insert_id' => $insert_id
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
}
