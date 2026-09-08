<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// เช็คว่า Login หรือยัง? (Security Check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน (Unauthorized)'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$equipment_id = $_GET['equipment_id'] ?? '';
$month        = $_GET['month'] ?? '';
$year         = $_GET['year'] ?? ''; // ปี ค.ศ.

if (empty($equipment_id)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสเครื่องมือ']);
    exit;
}

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Check)
    if ($user_role !== 'admin') {
        // เช็คว่าเครื่องมือที่กำลังจะเปิดดูประวัติ เป็นของหน่วยงานตัวเองหรือไม่
        $stmtOwner = $pdo->prepare("SELECT department_id FROM master_equipment_list WHERE id = ? AND status_delete = 0");
        $stmtOwner->execute([$equipment_id]);
        $owner_dept_id = $stmtOwner->fetchColumn();

        if ($owner_dept_id != $user_dept_id) {
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่มีสิทธิ์เข้าถึงข้อมูล: เครื่องมือนี้เป็นของหน่วยงานอื่น'
            ]);
            exit;
        }
    }

    // ไม่ต้อง Query Master ซ้ำ เพราะในหน้าตารางหลักรับค่า ชื่อเครื่องมือ, เลขชี้บ่ง, ยี่ห้อ, รุ่น, Serial No. และผู้ดูแล มาจากปุ่มกด (data-*) แล้วเอาไปแปะบนหัว Modal แบบเรียลไทม์เรียบร้อยแล้ว
    // ดึงข้อมูลประวัติการใช้งาน (Usage) ของเครื่องมือนี้ 
    // พร้อม Join หาชื่อผู้ใช้งานในแต่ละครั้ง
    $sqlLogs = "SELECT 
                    t.id, 
                    t.equipment_id, 
                    t.usage_date, 
                    t.report_no, 
                    t.condition_status, 
                    t.used_by, 
                    t.remark,
                    t.create_by,
                    DATE_FORMAT(t.usage_date, '%d/%m/%Y') AS usage_date_show,
                    
                    -- ดึงชื่อผู้ใช้งานพร้อมยศ
                    CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS used_by_name

                FROM trans_equipment_usage t 
                LEFT JOIN users r_usr ON t.used_by = r_usr.user_id
                LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
                LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id

                WHERE t.equipment_id = ? AND t.delete_token = 0";

    $params = [$equipment_id];

    // ถ้ามีการส่ง Month มา ให้เพิ่มเงื่อนไข
    if (!empty($month)) {
        $sqlLogs .= " AND MONTH(t.usage_date) = ? ";
        $params[] = $month;
    }

    // ถ้ามีการส่ง Year มา ให้เพิ่มเงื่อนไข
    if (!empty($year)) {
        $sqlLogs .= " AND YEAR(t.usage_date) = ? ";
        $params[] = $year;
    }

    $sqlLogs .= " ORDER BY t.usage_date DESC, t.id DESC";

    $stmtLogs = $pdo->prepare($sqlLogs);
    $stmtLogs->execute($params);
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    // ส่งข้อมูลกลับไปเป็น JSON (ใช้คีย์ 'data' เพื่อให้ตรงกับ JavaScript ฝั่งหน้าบ้าน)
    echo json_encode([
        'status' => 'success',
        'data' => $logs
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
