<?php
// ตั้งค่าการแสดงผล Error (ปิดเมื่อใช้งานจริงบน Production)
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
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // 1. รับค่าจาก Frontend (Filter)
    $plan_year   = $_POST['filter_plan_year'] ?? '';
    $group_name  = $_POST['filter_group_name'] ?? '';
    $department_id = $_POST['filter_department_id'] ?? '';

    // 2. ตั้งค่าเงื่อนไขเริ่มต้น (ไม่เอาตัวที่ลบ)
    $where = " WHERE t1.delete_token = 0 ";
    $params = [];

    // ควบคุมสิทธิ์การมองเห็นข้อมูลตามหน่วยงาน (Security & Department Filter)
    if ($user_role !== 'admin') {
        // User ทั่วไป บังคับเห็นเฉพาะแผนงานของหน่วยงานตัวเองเท่านั้น (เพิกเฉยต่อ filter จากหน้าบ้าน)
        $where .= " AND t1.department_id = ? ";
        $params[] = $user_dept_id;
    } else {
        // Admin สามารถเลือกฟิลเตอร์กรองตามหน่วยงานที่ต้องการได้
        if (!empty($department_id)) {
            $where .= " AND t1.department_id = ? ";
            $params[] = $department_id;
        }
    }

    // 3. สร้าง Dynamic WHERE Query
    if (!empty($plan_year)) {
        $where .= " AND t1.plan_year = ? ";
        $params[] = $plan_year;
    }

    if (!empty($group_name)) {
        $where .= " AND t1.group_name LIKE ? ";
        $params[] = "%$group_name%";
    }
    
    // 4. Pagination
    $limit  = 15; // จำนวนที่แสดงต่อหน้า (ปรับได้ตามต้องการ)
    $page   = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // 5. Query ข้อมูลหลัก
    $sql = "SELECT 
            t1.id, 
            t1.plan_year,
            t1.group_name,
            md.department_name, 
            
            -- จัดรูปแบบวันที่ใน SQL เลย (นำมาจากตัวอย่างที่ยอดเยี่ยมของคุณ)
            DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS createdate,
            DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updatedate,
            
            -- ข้อมูลคนสร้าง
            CONCAT(IFNULL(t4.rank_name,''), ' ', t3.first_name, ' ', t3.last_name) AS fullname_create

        FROM trans_maintenance_plan_header t1 
        -- JOIN ตาราง Master Departments
        LEFT JOIN master_departments md ON t1.department_id = md.id
        LEFT JOIN user_profile t3 ON t1.create_by = t3.user_id
        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
        $where 
        ORDER BY t1.plan_year DESC, t1.id DESC
        LIMIT $limit OFFSET $offset";
    // Execute Main Query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Query นับจำนวนทั้งหมด (สำหรับ Pagination)
    // ไม่ต้อง JOIN ตาราง user เพื่อความรวดเร็วในการนับ
    $sqlCount = "SELECT COUNT(t1.id) as countdata 
                 FROM trans_maintenance_plan_header t1 
                 $where";

    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

    $totalRows = (int)$countRes['countdata'];
    $totalPages = ceil($totalRows / $limit);

    // 7. จัดการกรณีที่ยังไม่มี update_date (เพิ่งสร้าง) ให้แสดง create_date แทน
    foreach ($data as $key => $row) {
        if (empty($row['updatedate'])) {
            $data[$key]['updatedate'] = $row['createdate'];
        }
    }

    // 8. ส่งค่ากลับเป็น JSON ให้ Frontend
    echo json_encode([
        'status'      => 'success',
        'data'        => $data,
        'count'       => $totalRows,      // จำนวนรายการทั้งหมด
        'totalRows'   => $totalRows,
        'totalPages'  => $totalPages,     // จำนวนหน้าทั้งหมด
        'currentPage' => $page,
        'offset'      => $offset
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
