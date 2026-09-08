<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// เช็คว่า Login หรือยัง? (Security Check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน (Unauthorized)']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // -------------------------------------------------------------------------
    // 1. รับค่า Filter จาก Frontend (กำหนด name สำหรับไปใช้ใน HTML)
    // -------------------------------------------------------------------------
    $filter_dept_id   = $_POST['filter_department_id'] ?? '';
    $filter_code      = $_POST['filter_device_code'] ?? '';
    $filter_location  = $_POST['filter_location_use'] ?? '';
    $filter_resp      = $_POST['filter_responsible_id'] ?? ''; // ค้นหาด้วย User ID (จาก Dropdown)

    // -------------------------------------------------------------------------
    // 2. ตั้งค่าเงื่อนไขเริ่มต้น
    // -------------------------------------------------------------------------
    $where = " WHERE t1.status_delete = 0 ";
    $params = [];

    // ควบคุมสิทธิ์การมองเห็นข้อมูลตามหน่วยงาน (Security & Department Filter)
    if ($user_role !== 'admin') {
        // User ทั่วไป บังคับเห็นเฉพาะเครื่องวัดอุณหภูมิของหน่วยงานตัวเองเท่านั้น (เพิกเฉยต่อ filter จากหน้าบ้าน)
        $where .= " AND t1.department_id = ? ";
        $params[] = $user_dept_id;
    } else {
        // Admin สามารถเลือกฟิลเตอร์กรองตามหน่วยงานที่ต้องการได้
        if (!empty($filter_dept_id)) {
            $where .= " AND t1.department_id = ? ";
            $params[] = $filter_dept_id;
        }
    }

    // 3. สร้าง Dynamic WHERE Query
    if (!empty($filter_code)) {
        $where .= " AND t1.device_code LIKE ? ";
        $params[] = "%$filter_code%";
    }

    if (!empty($filter_location)) {
        $where .= " AND t1.location_use LIKE ? ";
        $params[] = "%$filter_location%";
    }

    if (!empty($filter_resp)) {
        $where .= " AND (t1.responsible_person_1 = ? OR t1.responsible_person_2 = ?) ";
        $params[] = $filter_resp;
        $params[] = $filter_resp;
    }

    // 4. Pagination
    $limit = 15;
    $page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // -------------------------------------------------------------------------
    // 5. Query ข้อมูลหลัก
    // -------------------------------------------------------------------------
    $sql = "SELECT 
            t1.id, 
            t_dept.department_name,
            t1.device_code,
            t1.range_min,
            t1.range_max,
            t1.location_use,
            t1.measurement_uncertainty,
            
            -- ข้อมูลผู้รับผิดชอบ (Join ชื่อมาแสดงผล)
            CONCAT(t4_r1.rank_name,' ',t3_r1.first_name,' ',t3_r1.last_name) AS resp_name_1,
            CONCAT(t4_r2.rank_name,' ',t3_r2.first_name,' ',t3_r2.last_name) AS resp_name_2

        FROM master_temperature_device t1 

        -- Join ตารางหน่วยงาน
        LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id

        -- Join เพื่อเอาชื่อผู้รับผิดชอบคนที่ 1
        LEFT JOIN users t2_r1 ON t1.responsible_person_1 = t2_r1.user_id
        LEFT JOIN user_profile t3_r1 ON t2_r1.user_id = t3_r1.user_id
        LEFT JOIN user_rank t4_r1 ON t3_r1.rank_id = t4_r1.rank_id
        
        -- Join เพื่อเอาชื่อผู้รับผิดชอบคนที่ 2
        LEFT JOIN users t2_r2 ON t1.responsible_person_2 = t2_r2.user_id
        LEFT JOIN user_profile t3_r2 ON t2_r2.user_id = t3_r2.user_id
        LEFT JOIN user_rank t4_r2 ON t3_r2.rank_id = t4_r2.rank_id

        $where 
        ORDER BY t1.id DESC
        LIMIT $limit OFFSET $offset";

    // ดึงข้อมูลหลัก
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Query นับจำนวนทั้งหมด
    $sqlCount = "SELECT COUNT(t1.id) as countdata FROM master_temperature_device t1 $where ";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

    $totalRows = (int)$countRes['countdata'];
    $totalPages = ceil($totalRows / $limit);

    // 7. ส่งค่ากลับ
    echo json_encode([
        'status'      => 'success',
        'data'        => $data,
        'count' => $totalRows,
        'totalRows'   => $totalRows,
        'totalPages'  => $totalPages,
        'currentPage' => $page,
        'offset' => $offset
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
