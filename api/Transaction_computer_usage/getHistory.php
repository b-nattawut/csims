<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

/**
 * 1. ระบบรักษาความปลอดภัย (Security Check)
 */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน (Unauthorized)'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. รับค่า computer_id จาก GET (ส่งมาจาก JavaScript ตอนกดเปิด Modal ประวัติ)
$computer_id = $_GET['computer_id'] ?? '';
$month       = $_GET['month'] ?? ''; // (ถ้ามี)
$year        = $_GET['year'] ?? '';  // ปี ค.ศ. (ถ้ามี)

if (empty($computer_id)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบรหัสอ้างอิงเครื่องคอมพิวเตอร์'
    ]);
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
        // เช็คว่าเครื่องคอมพิวเตอร์ที่กำลังจะเปิดดูประวัติ เป็นของหน่วยงานตัวเองหรือไม่
        $stmtOwner = $pdo->prepare("SELECT department_id FROM master_computer_list WHERE id = ? AND status_delete = 0");
        $stmtOwner->execute([$computer_id]);
        $owner_dept_id = $stmtOwner->fetchColumn();

        if ($owner_dept_id != $user_dept_id) {
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่มีสิทธิ์เข้าถึงข้อมูล: เครื่องคอมพิวเตอร์นี้เป็นของหน่วยงานอื่น'
            ]);
            exit;
        }
    }

    /**
     * 3. Query ดึงข้อมูลประวัติการใช้งาน (Usage Logs)
     * ดึงข้อมูลจากตาราง trans_computer_usage และ Join หาชื่อเต็มผู้ใช้งาน
     */
    $sqlLogs = "SELECT 
                    t.id, 
                    t.computer_id, 
                    t.usage_date, 
                    t.report_no, 
                    t.condition_status, 
                    t.used_by, 
                    t.remark,
                    t.create_by,
                    
                    -- จัดรูปแบบวันที่เพื่อแสดงผลในตาราง (DD/MM/YYYY)
                    DATE_FORMAT(t.usage_date, '%d/%m/%Y') AS usage_date_show,
                    
                    -- ดึงชื่อผู้ใช้งานพร้อมยศ/ตำแหน่ง
                    CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS used_by_name

                FROM trans_computer_usage t 
                
                -- Join เพื่อดึงข้อมูลโปรไฟล์ของผู้ที่ลงชื่อใช้งานใน Log นั้นๆ
                LEFT JOIN users r_usr ON t.used_by = r_usr.user_id
                LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
                LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id

                -- เงื่อนไข: ระบุเครื่องให้ถูก และต้องไม่ถูกลบ (status_delete = 0)
                WHERE t.computer_id = ? AND t.status_delete = 0";

    // เริ่มต้นตัวแปร Parameters สำหรับ PDO
    $params = [$computer_id];

    // เงื่อนไขเพิ่มเติม: กรองตามเดือน (ถ้ามีการส่งมา)
    if (!empty($month)) {
        $sqlLogs .= " AND MONTH(t.usage_date) = ? ";
        $params[] = $month;
    }

    // เงื่อนไขเพิ่มเติม: กรองตามปี (ถ้ามีการส่งมา)
    if (!empty($year)) {
        $sqlLogs .= " AND YEAR(t.usage_date) = ? ";
        $params[] = $year;
    }

    // เรียงลำดับ: ถ้ากรองรายเดือนมักต้องการดูจากวันที่ 1 ไปสิ้นเดือน (ASC) 
    // แต่ถ้าดูทั้งหมดมักต้องการดูรายการล่าสุด (DESC) = ใช้ DESC ตามเดิม
    $sqlLogs .= " ORDER BY t.usage_date DESC, t.id DESC";

    $stmtLogs = $pdo->prepare($sqlLogs);
    $stmtLogs->execute($params);
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    // 4. ส่งข้อมูลกลับเป็น JSON
    // ใช้ JSON_UNESCAPED_UNICODE เพื่อให้ภาษาไทยใน used_by_name แสดงผลถูกต้อง
    echo json_encode([
        'status' => 'success',
        'data' => $logs
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
