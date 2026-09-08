<?php
// ตั้งค่าการแสดงผล Error (ปิดเมื่อใช้งานจริง)
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

try {

    // 1. ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtUser = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtUser->execute([$user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $user_role = strtolower($userData['role'] ?? '');
    $user_dept_id = $userData['department_id'] ?? null;

    // 2. รับค่าจาก Frontend (Filter)
    $department_id  = $_POST['filter_department_id'] ?? '';
    $category_id    = $_POST['filter_equipment_category'] ?? '';
    $asset_no = $_POST['filter_equipment_asset_no'] ?? '';
    $equipment_name = $_POST['filter_equipment_name'] ?? '';
    $brand_model    = $_POST['filter_equipment_brand_model'] ?? '';
    $serial_no      = $_POST['filter_equipment_serial'] ?? '';
    $install_date   = $_POST['filter_install_date'] ?? '';
    $start_use_date = $_POST['filter_start_use_date'] ?? '';
    $resp_id        = $_POST['filter_responsible_id'] ?? '';

    // 3. ตั้งค่าเงื่อนไขเริ่มต้น (ไม่เอาตัวที่ลบ)
    $where_clauses = ["t1.status_delete = 0"];
    $params = [];

    // 4. สร้าง Dynamic WHERE Query พร้อมระบบกรองสิทธิ์ตามหน่วยงาน
    if ($user_role !== 'admin') {
        // User ทั่วไป ล็อกบังคับเห็นเฉพาะเครื่องมือของหน่วยงานตัวเองเท่านั้น (เพิกเฉยต่อ filter จากหน้าบ้าน)
        $where_clauses[] = "t1.department_id = ?";
        $params[] = $user_dept_id;
    } else {
        // Admin สามารถเลือกฟิลเตอร์กรองตามหน่วยงานที่ต้องการได้
        if (!empty($department_id)) {
            $where_clauses[] = "t1.department_id = ?";
            $params[] = $department_id;
        }
    }

    if (!empty($category_id)) {
        $where_clauses[] = "t1.category_id = ?";
        $params[] = $category_id;
    }

    if (!empty($asset_no)) {
        // (ตัด % ตัวหน้าออก) เพื่อให้ใช้ Index ได้ + status delete เช็คผ่าน Index ที่สร้างแทน
        $where_clauses[] = "t1.asset_no LIKE ?";
        $params[] = "$asset_no%";
    }

    // ฟิลด์ที่ไม่มี Index หรือค้นหาแบบหว่าน (ยังคงใช้ %...% ได้ตามความเหมาะสม)
    if (!empty($equipment_name)) {
        $where_clauses[] = "t1.tool_name LIKE ?";
        $params[] = "%$equipment_name%";
    }

    if (!empty($brand_model)) {
        $words = array_filter(explode(' ', $brand_model));
        foreach ($words as $word) {
            $where_clauses[] = "(t1.brand LIKE ? OR t1.model LIKE ?)";
            $params[] = "%$word%";
            $params[] = "%$word%";
        }
    }

    if (!empty($serial_no)) {
        // ใช้ "prefix%" แทน + status delete เช็คผ่าน Index ที่สร้างแทน
        $where_clauses[] = "t1.serial_no LIKE ?";
        $params[] = "$serial_no%";
    }

    if (!empty($install_date)) {
        $where_clauses[] = "t1.date_receive = ?";
        $params[] = $install_date;
    }

    if (!empty($start_use_date)) {
        $where_clauses[] = "t1.date_start_use = ?";
        $params[] = $start_use_date;
    }

    if (!empty($resp_id)) {
        $where_clauses[] = "t1.responsible_id = ?";
        $params[] = $resp_id;
    }

    $where_sql = " WHERE " . implode(" AND ", $where_clauses);

    // 4. Pagination
    $limit = 15;
    $page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // 5. Query ข้อมูล
    $sql = "SELECT 
            t1.id, 
            t1.asset_no,
            t1.tool_name,
            t1.brand,
            t1.model,
            t1.serial_no,

            t_dept.department_name,

            t5.category_name, 

            -- จัดรูปแบบวันที่สำหรับแสดงผล (dd/mm/yyyy)
            DATE_FORMAT(t1.date_receive, '%d/%m/%Y') AS date_receive,
            DATE_FORMAT(t1.date_start_use, '%d/%m/%Y') AS date_use, 
            
            -- ดึงข้อมูลผู้ดูแล
            CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS responsible_name

        FROM master_equipment_list t1 
        
        -- JOIN ตารางหน่วยงาน
        LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id

        -- JOIN ประเภทเครื่องมือ
        LEFT JOIN master_equipment_categories t5 ON t1.category_id = t5.id

        -- JOIN เพื่อหาชื่อนามสกุลของผู้ดูแล
        LEFT JOIN user_profile r_prof ON t1.responsible_id = r_prof.user_id
        LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
        
        $where_sql
        ORDER BY t1.id DESC
        LIMIT $limit OFFSET $offset";

    // Execute Main Query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Query นับจำนวนทั้งหมด (สำหรับ Pagination)
    // ใช้ WHERE เดียวกันแต่ตัด LIMIT ออก และไม่ต้อง JOIN เยอะเพื่อนับเร็วขึ้น
    $sqlCount = "SELECT COUNT(*) as countdata FROM master_equipment_list t1 $where_sql";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRows = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['countdata'];

    // ส่งค่ากลับเป็น JSON
    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'count' => $totalRows,      // จำนวนรายการทั้งหมด
        'totalRows' => $totalRows,
        'totalPages' => ceil($totalRows / $limit),
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
