<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized: กรุณาเข้าสู่ระบบก่อนใช้งาน'
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

    // 2. รับค่าจาก Frontend (Filter) - อิงตาม ID ในหน้า trans_computer_usage
    $dept_id        = $_POST['filter_department_id'] ?? '';
    $asset_no       = $_POST['filter_computer_asset_no'] ?? '';
    $brand_model    = $_POST['filter_computer_brand_model'] ?? ''; // ค้นหาทั้งยี่ห้อและรุ่น
    $serial_no      = $_POST['filter_computer_serial'] ?? '';
    $responsible_id = $_POST['filter_responsible_id'] ?? '';

    // 3. เงื่อนไขพื้นฐาน (เฉพาะเครื่องที่ยังไม่ถูกลบ)
    $where = " WHERE t1.status_delete = 0 ";
    $params = [];

    // ควบคุมสิทธิ์การมองเห็นข้อมูลตามหน่วยงาน (Security & Department Filter)
    if ($user_role !== 'admin') {
        // User ทั่วไป ล็อกบังคับเห็นเฉพาะคอมพิวเตอร์ของหน่วยงานตัวเองเท่านั้น (เพิกเฉยต่อ filter จากหน้าบ้าน)
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
    if (!empty($asset_no)) {
        $where .= " AND t1.computer_asset_no LIKE ? ";
        $params[] = "%$asset_no%";
    }

    if (!empty($brand_model)) {
        // 1. แยกคำที่ผู้ใช้กรอกด้วยช่องว่าง (เช่น "Lenovo Thinkpad" -> ["Lenovo", "Thinkpad"])
        // array_filter ช่วยตัดช่องว่างซ้ำๆ ออก
        $search_words = array_filter(explode(' ', $brand_model));

        foreach ($search_words as $word) {
            // 2. สำหรับทุกคำที่ค้นหา ต้องมีอยู่ใน "ยี่ห้อ" หรือ "รุ่น" อย่างใดอย่างหนึ่ง
            // ใช้คำเชื่อม AND ระหว่างชุดของคำ เพื่อบีบผลลัพธ์ให้ตรงที่สุด
            $where .= " AND (t1.computer_brand LIKE ? OR t1.computer_model LIKE ?) ";

            $params[] = "%$word%"; // สำหรับ computer_brand
            $params[] = "%$word%"; // สำหรับ computer_model
        }
    }

    if (!empty($serial_no)) {
        $where .= " AND t1.computer_serial LIKE ? ";
        $params[] = "%$serial_no%";
    }

    if (!empty($responsible_id)) {
        $where .= " AND t1.responsible_id = ? ";
        $params[] = $responsible_id;
    }

    // 5. ระบบแบ่งหน้า (Pagination)
    $limit = 15;
    $page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // 6. Query ข้อมูลหลักคอมพิวเตอร์ + Subquery ดึงประวัติล่าสุดจากตาราง Trans
    $sql = "SELECT 
            t1.id, 
            t_dept.department_name,
            t1.computer_asset_no,
            t1.computer_brand,
            t1.computer_model,
            t1.computer_serial,
            t1.create_by,
            
            -- ดึงชื่อผู้รับผิดชอบหลัก
            CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS responsible_fullname,
            
            -- Subquery 1: ดึงวันที่ใช้งานล่าสุดจากตาราง Trans (สถานะต้องไม่ถูกลบ)
            (SELECT DATE_FORMAT(usage_date, '%d/%m/%Y') 
             FROM trans_computer_usage t 
             WHERE t.computer_id = t1.id AND t.status_delete = 0 
             ORDER BY usage_date DESC, id DESC LIMIT 1) AS last_usage_date,
             
            -- Subquery 2: ดึงการใช้งาน / เลขรายงานที่ ล่าสุด
            (SELECT report_no 
             FROM trans_computer_usage t 
             WHERE t.computer_id = t1.id AND t.status_delete = 0 
             ORDER BY usage_date DESC, id DESC LIMIT 1) AS last_report_no

        FROM master_computer_list t1 
        -- JOIN ตารางหน่วยงาน
        LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id
        
        -- Join ข้อมูลผู้รับผิดชอบ
        LEFT JOIN users r_usr ON t1.responsible_id = r_usr.user_id
        LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
        LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
        
        $where 
        ORDER BY t1.id DESC
        LIMIT $limit OFFSET $offset";
        
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Query นับจำนวนทั้งหมด (สำหรับ Pagination)
    $sqlCount = "SELECT COUNT(t1.id) as countdata FROM master_computer_list t1 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

    $totalRows = (int)$countRes['countdata'];
    $totalPages = ceil($totalRows / $limit);

    // 8. ส่งข้อมูลกลับ
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
