<?php
session_start();
// ปิด Error Reporting เมื่อใช้งานจริง หรือเปิดไว้ช่วง Dev
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// ตรวจสอบสิทธิ์การเข้าใช้งาน
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

    // -------------------------------------------------------------------------
    // 1. รับค่าจาก Frontend (Filter) - ปรับตาม name ใน HTML
    // -------------------------------------------------------------------------
    $dept_id        = $_POST['filter_department_id'] ?? '';
    $asset_no       = $_POST['filter_computer_asset_no'] ?? '';
    $brand_model    = $_POST['filter_computer_brand_model'] ?? ''; // ค้นหาทั้งยี่ห้อและรุ่น
    $serial_no      = $_POST['filter_computer_serial'] ?? '';
    $resp_id        = $_POST['filter_responsible_id'] ?? '';
    $receive_date   = $_POST['filter_receive_date'] ?? '';
    $start_use_date = $_POST['filter_start_use_date'] ?? '';

    // -------------------------------------------------------------------------
    // 2. สร้างเงื่อนไข Dynamic WHERE
    // -------------------------------------------------------------------------
    $where = " WHERE t1.status_delete = 0 ";
    $params = [];

    // กรองสิทธิ์ตามหน่วยงาน (Security & Department Filter)
    if ($user_role !== 'admin') {
        // User ทั่วไป ล็อกบังคับเห็นเฉพาะคอมพิวเตอร์ของหน่วยงานตัวเองเท่านั้น
        $where .= " AND t1.department_id = ? ";
        $params[] = $user_dept_id;
    } else {
        // Admin สามารถเลือกฟิลเตอร์กรองตามหน่วยงานที่ต้องการได้
        if (!empty($dept_id)) {
            $where .= " AND t1.department_id = ? ";
            $params[] = $dept_id;
        }
    }

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

    if (!empty($resp_id)) {
        $where .= " AND t1.responsible_id = ? ";
        $params[] = $resp_id;
    }

    if (!empty($receive_date)) {
        $where .= " AND t1.computer_receive_date = ? ";
        $params[] = $receive_date;
    }

    if (!empty($start_use_date)) {
        $where .= " AND t1.computer_start_use_date = ? ";
        $params[] = $start_use_date;
    }

    // -------------------------------------------------------------------------
    // 3. ระบบแบ่งหน้า (Pagination)
    // -------------------------------------------------------------------------
    $limit  = 15; // จำนวนรายการต่อหน้า
    $page   = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // -------------------------------------------------------------------------
    // 4. Query ข้อมูลหลัก
    // -------------------------------------------------------------------------
    $sql = "SELECT 
            t1.id, 
            t1.computer_asset_no,
            t1.computer_brand,
            t1.computer_model,
            t1.computer_serial,

            -- ดึงชื่อหน่วยงาน
            t_dept.department_name,
            
            -- จัดรูปแบบวันที่ให้สอดคล้องกับ renderTable (dd/mm/yyyy)
            DATE_FORMAT(t1.computer_receive_date, '%d/%m/%Y') AS receive_date_show,
            DATE_FORMAT(t1.computer_start_use_date, '%d/%m/%Y') AS start_use_date_show, 
            
            -- ดึงชื่อเต็มผู้ดูแล (Rank + First + Last)
            t1.responsible_id,
            CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS responsible_fullname,
            
            -- ข้อมูลประวัติการสร้าง
            DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS createdate_show

        FROM master_computer_list t1 
        
        -- JOIN ตารางหน่วยงาน
        LEFT JOIN master_departments t_dept ON t1.department_id = t_dept.id

        -- Join ข้อมูลผู้ดูแล
        LEFT JOIN users r_usr ON t1.responsible_id = r_usr.user_id
        LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
        LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
        
        $where 
        ORDER BY t1.id DESC
        LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // -------------------------------------------------------------------------
    // 5. Query นับจำนวนทั้งหมด (สำหรับ Pagination)
    // -------------------------------------------------------------------------
    $sqlCount = "SELECT COUNT(t1.id) as countdata FROM master_computer_list t1 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

    $totalRows  = (int)$countRes['countdata'];
    $totalPages = ceil($totalRows / $limit);

    // 6. ส่งผลลัพธ์กลับไปยัง Frontend
    echo json_encode([
        'status'      => 'success',
        'data'        => $data,         // ข้อมูลแถว
        'count'       => $totalRows,    // จำนวนรายการทั้งหมด
        'totalPages'  => $totalPages,   // จำนวนหน้าทั้งหมด
        'currentPage' => $page,         // หน้าปัจจุบัน
        'offset'      => $offset        // ลำดับเริ่มต้น
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage()
    ]);
}
