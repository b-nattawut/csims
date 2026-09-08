<?php
// ตั้งค่าการแสดงผล Error (ปิดเมื่อใช้งานจริง)
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบ Security Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน'
    ]);
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

    // 2. รับค่าจาก Frontend (Filter) ตามที่ตั้งไว้ใน searchFilterForm
    $filter_dept_id  = $_POST['filter_department_id'] ?? '';
    $chemical_name   = $_POST['filter_chemical_name'] ?? '';
    $chemical_brand  = $_POST['filter_chemical_brand'] ?? '';
    $chemical_detail = $_POST['filter_chemical_detail'] ?? '';

    // 3. ตั้งค่าเงื่อนไขเริ่มต้น (ไม่เอาตัวที่ลบ)
    $where = " WHERE t1.status_delete = 0 ";
    $params = [];

    // ควบคุมสิทธิ์การมองเห็นข้อมูลตามหน่วยงาน (Security & Department Filter)
    if ($user_role !== 'admin') {
        // User ทั่วไป ล็อกบังคับเห็นเฉพาะสารเคมีของหน่วยงานตัวเองเท่านั้น (เพิกเฉยต่อ filter จากหน้าบ้าน)
        $where .= " AND t1.department_id = ? ";
        $params[] = $user_dept_id;
    } else {
        // Admin สามารถเลือกฟิลเตอร์กรองตามหน่วยงานที่ต้องการได้
        if (!empty($filter_dept_id)) {
            $where .= " AND t1.department_id = ? ";
            $params[] = $filter_dept_id;
        }
    }

    // 4. สร้าง Dynamic WHERE Query
    if (!empty($chemical_name)) {
        $where .= " AND t1.chemical_name LIKE ? ";
        $params[] = "%$chemical_name%";
    }

    if (!empty($chemical_brand)) {
        $where .= " AND t1.chemical_brand LIKE ? ";
        $params[] = "%$chemical_brand%";
    }

    if (!empty($chemical_detail)) {
        $where .= " AND t1.chemical_detail LIKE ? ";
        $params[] = "%$chemical_detail%";
    }

    // 5. Pagination Setup
    $limit = 15;
    $page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // 6. Query ข้อมูลหลัก
    $sql = "SELECT 
            t1.id, 
            t_dept.department_name,
            t1.chemical_name,
            t1.chemical_brand,
            t1.unit_id,
            t1.chemical_detail,
            
            -- ดึงข้อมูลหน่วยนับ
            t2.unit_name_th,
            t2.unit_symbol,
            t2.unit_name_en,
            t2.unit_group,

            -- ข้อมูลคนสร้างรายการ
            CONCAT(t4.rank_name, ' ', t3.first_name, ' ', t3.last_name) AS fullname_create,
            DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i:%s') AS createdate,
            
            -- ข้อมูลคนแก้ไขล่าสุด
            CONCAT(t6.rank_name, ' ', t5.first_name, ' ', t5.last_name) AS fullname_update,
            DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i:%s') AS updatedate

        FROM master_chemical_list t1 

        -- JOIN หาชื่อหน่วยงาน
        LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id
        
        LEFT JOIN master_chemical_units t2 ON t1.unit_id = t2.id

        -- JOIN หาข้อมูลคนสร้างรายการ
        LEFT JOIN user_profile t3 ON t1.create_by = t3.user_id
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        
        -- JOIN หาข้อมูลคนแก้ไขล่าสุด
        LEFT JOIN user_profile t5 ON t1.update_by = t5.user_id
        LEFT JOIN user_rank t6 ON t5.rank_id = t6.rank_id
        
        $where 
        ORDER BY t1.id DESC
        LIMIT $limit OFFSET $offset";
    // Execute Main Query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedData = [];
    foreach ($rawData as $row) {
        $isPackaging = (strpos($row['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($row['unit_group'], 'Packaging') !== false);
        $symbol = $isPackaging ? $row['unit_name_en'] : $row['unit_symbol'];

        $row['unit_display'] = $row['unit_name_th'] . " (" . $symbol . ")";
        $formattedData[] = $row;
    }

    // 7. Query นับจำนวนทั้งหมดสำหรับ Pagination
    $sqlCount = "SELECT COUNT(t1.id) as countdata FROM master_chemical_list t1 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

    $totalRows = (int)$countRes['countdata'];
    $totalPages = ceil($totalRows / $limit);

    // 8. ส่งค่ากลับเป็น JSON
    echo json_encode([
        'status' => 'success',
        'data' => $formattedData,
        'count' => $totalRows,      // จำนวนรายการทั้งหมดที่ค้นหาเจอ
        'totalPages' => $totalPages,
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
