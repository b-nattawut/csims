<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. เช็คสิทธิ์การเข้าใช้งาน
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

    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // 2. รับค่าจาก Frontend (Filter)
    $dept_id        = $_POST['filter_department_id'] ?? '';
    $category_id    = $_POST['filter_equipment_category'] ?? '';
    $equipment_name = $_POST['filter_equipment_name'] ?? '';
    $asset_no       = $_POST['filter_equipment_asset_no'] ?? '';
    $responsible_id = $_POST['filter_responsible_id'] ?? '';
    $brand_model = $_POST['filter_equipment_brand_model'] ?? '';
    $serial_no = $_POST['filter_equipment_serial'] ?? '';

    // 3. ตั้งค่าเงื่อนไขเริ่มต้น (ดึงเฉพาะเครื่องมือที่ยังไม่ถูกลบ)
    $where = " WHERE t1.status_delete = 0 ";
    $params = [];

    // ควบคุมสิทธิ์การมองเห็นข้อมูลตามหน่วยงาน (Security & Department Filter)
    if ($user_role !== 'admin') {
        // User ทั่วไป บังคับเห็นเฉพาะเครื่องมือของหน่วยงานตัวเองเท่านั้น (เพิกเฉยต่อ filter จากหน้าบ้าน)
        $where .= " AND t1.department_id = ? ";
        $params[] = $user_dept_id;
    } else {
        // Admin สามารถเลือกฟิลเตอร์กรองตามหน่วยงานที่ต้องการได้
        if (!empty($dept_id)) {
            $where .= " AND t1.department_id = ? ";
            $params[] = $dept_id;
        }
    }

    // 4. สร้าง Dynamic WHERE Query
    if (!empty($category_id)) {
        $where .= " AND t1.category_id = ? ";
        $params[] = $category_id;
    }

    if (!empty($equipment_name)) {
        $where .= " AND t1.tool_name LIKE ? ";
        $params[] = "%$equipment_name%";
    }

    if (!empty($asset_no)) {
        $where .= " AND t1.asset_no LIKE ? ";
        $params[] = "%$asset_no%";
    }

    if (!empty($responsible_id)) {
        $where .= " AND t1.responsible_id = ? ";
        $params[] = $responsible_id;
    }

    if (!empty($brand_model)) {
        $words = array_filter(explode(' ', $brand_model));
        foreach ($words as $word) {
            $where .= " AND (t1.brand LIKE ? OR t1.model LIKE ?) ";
            $params[] = "%$word%";
            $params[] = "%$word%";
        }
    }

    if (!empty($serial_no)) {
        $where .= " AND t1.serial_no LIKE ? ";
        $params[] = "%$serial_no%";
    }

    // 5. จัดการหน้า Pagination
    $limit = 15;
    $page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // 6. Query ข้อมูลหลัก + Subquery ดึงข้อมูลการใช้งานล่าสุด
    $sql = "SELECT 
            t1.id, 
            t_dept.department_name,
            t5.category_name, 
            t1.asset_no,
            t1.tool_name,
            t1.brand,
            t1.model,
            t1.serial_no,
            t1.create_by,
            
            -- ดึงข้อมูลชื่อผู้ดูแลพร้อมยศ
            CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS responsible_name,
            
            -- Subquery 1: ดึงวันที่ใช้งานล่าสุด (แปลง Format เป็น วัน/เดือน/ปี)
            (SELECT DATE_FORMAT(usage_date, '%d/%m/%Y') 
             FROM trans_equipment_usage t 
             WHERE t.equipment_id = t1.id AND t.delete_token = 0 
             ORDER BY usage_date DESC, id DESC LIMIT 1) AS last_usage_date,
             
            -- Subquery 2: ดึงการใช้งาน/เลขรายงานล่าสุด
            (SELECT report_no 
             FROM trans_equipment_usage t 
             WHERE t.equipment_id = t1.id AND t.delete_token = 0 
             ORDER BY usage_date DESC, id DESC LIMIT 1) AS last_report_no

        FROM master_equipment_list t1 
        
        -- JOIN ตารางหน่วยงาน
        LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id

        -- JOIN ตารางประเภทเครื่องมือ
        LEFT JOIN master_equipment_categories t5 ON t1.category_id = t5.id

        -- JOIN หาผู้ดูแลเครื่องมือ
        LEFT JOIN users r_usr ON t1.responsible_id = r_usr.user_id
        LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
        LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
        
        $where 
        ORDER BY t1.id DESC
        LIMIT $limit OFFSET $offset";
        
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Query นับจำนวนข้อมูลทั้งหมด (สำหรับสร้างปุ่ม Pagination)
    $sqlCount = "SELECT COUNT(t1.id) as countdata FROM master_equipment_list t1 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

    $totalRows = (int)$countRes['countdata'];
    $totalPages = ceil($totalRows / $limit);

    // 8. ส่งผลลัพธ์กลับไปให้ JavaScript
    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'count' => $totalRows,
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
