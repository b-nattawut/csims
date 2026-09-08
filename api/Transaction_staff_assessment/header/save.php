<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../../db_config.php'; 
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

$current_user_id = $_SESSION['user_id'];

// รับค่าและแปลง Empty String เป็น NULL สำหรับ ID และ Date
function getPostValue($key) {
    $val = $_POST[$key] ?? '';
    return ($val === '') ? null : $val;
}

$id = getPostValue('id'); 
$report_no = trim($_POST['report_no'] ?? '');
$operation_date = getPostValue('operation_date'); // สำคัญ: DATE ห้ามเป็น ''
$location = trim($_POST['location'] ?? '');
$case_scope = $_POST['case_scope'] ?? '';

// ทีมงาน (ใช้ฟังก์ชันเพื่อแปลง '' เป็น null)
$leader_id = getPostValue('leader_id');
$photographer_id = getPostValue('photographer_id');
$map_maker_id = getPostValue('map_maker_id');
$searcher_id = getPostValue('searcher_id');
$collector_id = getPostValue('collector_id');

$evaluator_id = getPostValue('evaluator_id');

// Validation เบื้องต้น
if (empty($report_no) || empty($operation_date) || empty($location)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูล เลขรายงาน, วันที่ และสถานที่ ให้ครบถ้วน']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. ตรวจสอบเลขรายงานซ้ำ
    $check_sql = "SELECT id FROM staff_assessment_header 
                  WHERE report_no = :report_no AND delete_token = 0 AND id != :id";
    // แปลง $id เป็น 0 ถ้าเป็น null เพื่อไม่ให้ error ใน sql (กรณี insert)
    $check_id = $id ? $id : 0; 
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':report_no' => $report_no, ':id' => $check_id]);

    if ($check_stmt->rowCount() > 0) {
        echo json_encode(['status' => 'error', 'message' => "เลขรายงาน '$report_no' นี้มีอยู่ในระบบแล้ว"]);
        exit;
    }

    if (empty($id)) {
        // ----------------------------------------------------
        // 2. INSERT (กรณีเพิ่มใหม่)
        // ----------------------------------------------------
        $sql = "INSERT INTO staff_assessment_header (
                    report_no, operation_date, location, case_scope,
                    leader_id, photographer_id, map_maker_id, searcher_id, collector_id,
                    evaluator_id, created_by, updated_by
                ) VALUES (
                    :report_no, :operation_date, :location, :case_scope,
                    :leader_id, :photographer_id, :map_maker_id, :searcher_id, :collector_id,
                    :evaluator_id, :created_by, :updated_by
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':report_no'      => $report_no,
            ':operation_date' => $operation_date,
            ':location'       => $location,
            ':case_scope'     => $case_scope,
            ':leader_id'      => $leader_id,
            ':photographer_id'=> $photographer_id,
            ':map_maker_id'   => $map_maker_id,
            ':searcher_id'    => $searcher_id,
            ':collector_id'   => $collector_id,
            ':evaluator_id'   => $evaluator_id,
            ':created_by'     => $current_user_id,
            ':updated_by'     => $current_user_id
        ]);
        
        $insert_id = $pdo->lastInsertId();
        $message = 'สร้างรายการประเมินใหม่เรียบร้อยแล้ว';
        
    } else {
        // ----------------------------------------------------
        // 3. UPDATE (กรณีแก้ไข) - จุดสำคัญ!
        // ----------------------------------------------------
        
        // 3.1 ดึงข้อมูลเก่ามาเทียบก่อนอัปเดต เพื่อดูว่าใครถูกเปลี่ยนตัวบ้าง
        $stmtOld = $pdo->prepare("SELECT * FROM staff_assessment_header WHERE id = ?");
        $stmtOld->execute([$id]);
        $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

        // Update ข้อมูลใหม่
        $sql = "UPDATE staff_assessment_header SET 
                    report_no = :report_no, 
                    operation_date = :operation_date, 
                    location = :location, 
                    case_scope = :case_scope,
                    leader_id = :leader_id, 
                    photographer_id = :photographer_id, 
                    map_maker_id = :map_maker_id, 
                    searcher_id = :searcher_id, 
                    collector_id = :collector_id,
                    evaluator_id = :evaluator_id,
                    updated_by = :updated_by
                WHERE id = :id AND delete_token = 0";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':report_no'      => $report_no,
            ':operation_date' => $operation_date,
            ':location'       => $location,
            ':case_scope'     => $case_scope,
            ':leader_id'      => $leader_id,
            ':photographer_id'=> $photographer_id,
            ':map_maker_id'   => $map_maker_id,
            ':searcher_id'    => $searcher_id,
            ':collector_id'   => $collector_id,
            ':evaluator_id'   => $evaluator_id,
            ':updated_by'     => $current_user_id,
            ':id'             => $id
        ]);

        // 3.2 Logic เคลียร์ผลประเมินเก่า (สำคัญมาก)
        // เช็คทีละตำแหน่ง ถ้าคนเก่าไม่เท่ากับคนใหม่ ให้ลบผลประเมินของคนเก่าทิ้ง
        $roles_map = [
            'leader_id'       => 'LEADER',
            'photographer_id' => 'PHOTOGRAPHER',
            'map_maker_id'    => 'MAP_MAKER',
            'searcher_id'     => 'SEARCHER',
            'collector_id'    => 'COLLECTOR'
        ];

        // เตรียมคำสั่งลบผลประเมิน (Delete from staff_assessment_results)
        // หมายเหตุ: Table detail จะถูกลบ auto เพราะ FK ON DELETE CASCADE
        $sqlDelResult = "DELETE FROM staff_assessment_results WHERE header_id = ? AND user_id = ? AND role_type = ?";
        $stmtDelResult = $pdo->prepare($sqlDelResult);

        // วนลูปเช็คทุกตำแหน่ง
        $params = [
            'leader_id' => $leader_id,
            'photographer_id' => $photographer_id,
            'map_maker_id' => $map_maker_id,
            'searcher_id' => $searcher_id,
            'collector_id' => $collector_id
        ];

        foreach ($roles_map as $field => $role_type) {
            $old_user = $oldData[$field];
            $new_user = $params[$field];

            // ถ้ามีคนเก่า และ คนเก่าไม่เท่ากับคนใหม่ (มีการเปลี่ยนตัว หรือ ลบออก)
            if (!empty($old_user) && $old_user != $new_user) {
                // ลบผลประเมินของคนเก่าทิ้ง เพื่อไม่ให้เป็นขยะในระบบ
                $stmtDelResult->execute([$id, $old_user, $role_type]);
            }
        }
        
        $insert_id = $id;
        $message = 'แก้ไขข้อมูลเรียบร้อยแล้ว';
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
?>