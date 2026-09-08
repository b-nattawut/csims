<?php
session_start();
// เปิด Error Reporting สำหรับการ Debug (ปิดเมื่อใช้งานจริง)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบความปลอดภัย (Session)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนดำเนินการ']);
    exit;
}

// 2. ตรวจสอบ Parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสรายการที่ต้องการ (ID is required)']);
    exit;
}

$id = $_GET["id"];

/**
 * 3. Query ข้อมูลหลัก
 * - ทำการ JOIN 3 ชุด เพื่อดึงชื่อเจ้าหน้าที่ทั้ง 3 ตำแหน่ง
 * - แปลงรูปแบบวันที่และเวลาให้พร้อมใส่ใน HTML Input
 */
$sql = "SELECT 
            t1.*,
            t1.unit_officer_position,
            -- จัดรูปแบบวันที่/เวลา --
            t1.check_date,
            DATE_FORMAT(t1.check_date, '%d/%m/%Y') AS check_date_show,
            TIME_FORMAT(t1.check_time, '%H:%i') AS check_time_raw, -- สำหรับ Input type='time'
            
            -- เจ้าหน้าที่ชุดที่ 1: ผู้ตรวจ (Checked By) --
            CONCAT(IFNULL(r1.rank_name, ''), ' ', p1.first_name, ' ', p1.last_name) AS checked_by_fullname,
            
            -- เจ้าหน้าที่ชุดที่ 2: หัวหน้าทีมตรวจ (Team Leader) --
            CONCAT(IFNULL(r2.rank_name, ''), ' ', p2.first_name, ' ', p2.last_name) AS team_leader_fullname,

            -- เจ้าหน้าที่ชุดที่ 3: เจ้าหน้าที่เฉพาะสังกัด (Historical Support) --
            -- ถ้ามี unit_officer_name (Snapshot) ให้ใช้อันนั้นก่อน เพื่อความถูกต้องของประวัติ
            -- แต่ถ้าไม่มี (กรณีข้อมูลเก่าก่อนแก้ระบบ) ค่อยไปดึงจาก Profile ปัจจุบัน
            CASE 
                WHEN t1.unit_officer_name IS NOT NULL AND t1.unit_officer_name != '' THEN t1.unit_officer_name
                WHEN t1.unit_officer_id IS NOT NULL THEN CONCAT(IFNULL(r3.rank_name, ''), ' ', p3.first_name, ' ', p3.last_name)
                ELSE '-' 
            END AS unit_officer_fullname,

            -- ข้อมูลผู้ทำรายการระบบ (Metadata) --
            CONCAT(p_c.first_name, ' ', p_c.last_name) AS creator_name,
            DATE_FORMAT(t1.created_at, '%d/%m/%Y %H:%i') AS created_at_show

        FROM trans_readiness_check_header t1 
        
        -- Join ชุดที่ 1 (Checked By)
        LEFT JOIN user_profile p1 ON t1.checked_by = p1.user_id
        LEFT JOIN user_rank r1 ON p1.rank_id = r1.rank_id
        
        -- Join ชุดที่ 2 (Team Leader)
        LEFT JOIN user_profile p2 ON t1.team_leader_id = p2.user_id
        LEFT JOIN user_rank r2 ON p2.rank_id = r2.rank_id
        
        -- Join ชุดที่ 3 (Unit Officer)
        LEFT JOIN user_profile p3 ON t1.unit_officer_id = p3.user_id
        LEFT JOIN user_rank r3 ON p3.rank_id = r3.rank_id
        
        -- Join ข้อมูลผู้สร้างรายการ
        LEFT JOIN user_profile p_c ON t1.created_by = p_c.user_id

        WHERE t1.delete_token = 0 AND t1.id = ? ";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        // ส่งข้อมูลกลับไปในรูปแบบ JSON
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบข้อมูลที่ต้องการ หรือรายการนี้อาจถูกลบไปแล้ว'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}