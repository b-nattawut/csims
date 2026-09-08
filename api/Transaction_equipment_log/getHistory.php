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
$month        = $_GET['month'] ?? ''; // รับค่าเดือน (ถ้ามี)
$year         = $_GET['year'] ?? '';  // รับค่าปี ค.ศ. (ถ้ามี)

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

    // 1. ดึงข้อมูล Master ของเครื่องมือ 
    $sqlMaster = "SELECT 
                    t1.id, 
                    t1.asset_no, 
                    t1.tool_name, 
                    t1.brand, 
                    t1.model, 
                    t1.serial_no,
                    t1.department_id,
                    t_dept.department_name,
                    t1.responsible_id,
                    CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS responsible_name
                  FROM master_equipment_list t1
                  LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id
                  LEFT JOIN users r_usr ON t1.responsible_id = r_usr.user_id
                  LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
                  LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
                  WHERE t1.id = ?";
                  
    $stmtMaster = $pdo->prepare($sqlMaster);
    $stmtMaster->execute([$equipment_id]);
    $equipment_info = $stmtMaster->fetch(PDO::FETCH_ASSOC);

    if (!$equipment_info) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลเครื่องมือในระบบ หรือเครื่องถูกลบไปแล้ว']);
        exit;
    }

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Control)
    if ($user_role !== 'admin') {
        if ($equipment_info['department_id'] != $user_dept_id) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง: เครื่องมือนี้อยู่คนละหน่วยงาน']);
            exit;
        }
    }

    // 2. ดึงข้อมูลประวัติ (Log) ของเครื่องมือนี้
    $sqlLogs = "SELECT id, equipment_id, log_date, maintenance_detail, company_name, officer_name, remark, create_by,
                DATE_FORMAT(log_date, '%d/%m/%Y') AS log_date_show
                FROM trans_equipment_log 
                WHERE equipment_id = ? AND delete_token = 0";
    
    // เตรียม Parameters สำหรับ PDO
    $params = [$equipment_id];

    // กรองเดือน (ถ้าส่งมา)
    if (!empty($month)) {
        $sqlLogs .= " AND MONTH(log_date) = ? ";
        $params[] = $month;
    }

    // กรองปี (ถ้าส่งมา)
    if (!empty($year)) {
        $sqlLogs .= " AND YEAR(log_date) = ? ";
        $params[] = $year;
    }

    // เรียงลำดับ (ถ้าดูประวัติการซ่อมบำรุงมักจะดูรายการล่าสุดก่อน)
    $sqlLogs .= " ORDER BY log_date DESC, id DESC";

    $stmtLogs = $pdo->prepare($sqlLogs);
    $stmtLogs->execute($params);
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    // 3. ส่งข้อมูลกลับไปเป็น JSON
    echo json_encode([
        'status' => 'success',
        'equipment_info' => $equipment_info,
        'logs' => $logs
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>