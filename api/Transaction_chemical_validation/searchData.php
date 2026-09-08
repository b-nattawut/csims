<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบ Security Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน (เพิ่มเช็ค is_active = 1)
    $stmtUser = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
    $stmtUser->execute([$user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลผู้ใช้งาน หรือบัญชีถูกระงับ']);
        exit;
    }

    $user_role    = strtolower($userData['role'] ?? '');
    $user_dept_id = $userData['department_id'] ?? null;

    // 2. รับค่าจาก Frontend (Filter)
    $val_start          = $_POST['filter_val_start'] ?? '';
    $val_end            = $_POST['filter_val_end'] ?? '';
    $filter_chemical_id = $_POST['filter_chemical_id'] ?? ''; // ID สารเคมีจาก Master
    $filter_dept_id     = $_POST['filter_department_id'] ?? ''; // ID หน่วยงานจาก Filter
    $tester_id          = $_POST['filter_tester'] ?? '';
    $res_acid_base      = $_POST['filter_res_acid_base'] ?? '';
    $res_blood          = $_POST['filter_res_blood'] ?? '';
    $is_verified        = $_POST['filter_is_verified'] ?? '';

    $today = date('Y-m-d');
    $near_expiry_date = date('Y-m-d', strtotime('+14 days'));

    // ควบคุมสิทธิ์การเข้าถึงข้อมูลตามหน่วยงานอย่างรัดกุม (Department Access Control)
    if ($user_role !== 'admin') {
        // ถ้า User ทั่วไปแอบส่ง filter_department_id ของหน่วยงานอื่นมา -> ปฏิเสธการเข้าถึง (403)
        if (!empty($filter_dept_id) && $filter_dept_id != $user_dept_id) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึงข้อมูลการทดสอบสารเคมีของหน่วยงานอื่น']);
            exit;
        }
        // บังคับล็อกให้ดึงเฉพาะหน่วยงานของตนเองเท่านั้น
        $filter_dept_id = $user_dept_id;
    }

    // 3. ตั้งค่าเงื่อนไขเริ่มต้น (Soft Delete)
    $where = " WHERE t1.status_delete = 0 ";
    $params = [];

    if (!empty($filter_dept_id)) {
        $where .= " AND (
            SELECT mcl_sub.department_id 
            FROM preparation_ingredients pi_sub
            JOIN chemical_inventory inv_sub ON pi_sub.inventory_id = inv_sub.id
            JOIN master_chemical_list mcl_sub ON inv_sub.chemical_id = mcl_sub.id
            WHERE pi_sub.prep_id = t2.id AND pi_sub.status_delete = 0 
            LIMIT 1
        ) = ? ";
        $params[] = $filter_dept_id;
    }

    // 4. สร้าง Dynamic WHERE Query
    // กรองช่วงวันที่ทดสอบ
    if (!empty($val_start)) {
        $where .= " AND t1.test_date >= ? ";
        $params[] = $val_start;
    }
    if (!empty($val_end)) {
        $where .= " AND t1.test_date <= ? ";
        $params[] = $val_end;
    }

    // กรองตามสารเคมีหลัก (Master) โดยใช้ Subquery เจาะหาผ่านตารางสูตรย่อย
    if (!empty($filter_chemical_id)) {
        $where .= " AND t2.id IN (
                    SELECT DISTINCT pi_f.prep_id 
                    FROM preparation_ingredients pi_f 
                    JOIN chemical_inventory inv_f ON pi_f.inventory_id = inv_f.id 
                    WHERE inv_f.chemical_id = ? AND pi_f.status_delete = 0
                ) ";
        $params[] = $filter_chemical_id;
    }

    // กรองตามผู้ทดสอบ
    if (!empty($tester_id)) {
        $where .= " AND t1.tester_id = ? ";
        $params[] = $tester_id;
    }

    // กรองตามผลการทดสอบ
    if (!empty($res_acid_base)) {
        $where .= " AND t1.res_acid_base = ? ";
        $params[] = $res_acid_base;
    }
    if (!empty($res_blood)) {
        $where .= " AND t1.res_blood = ? ";
        $params[] = $res_blood;
    }

    if ($is_verified !== '') {
        $where .= " AND t1.is_verified = ? ";
        $params[] = $is_verified;
    }

    // 5. Pagination Setup
    $limit = 15;
    $page  = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    // 6. Query ข้อมูลหลัก (JOIN หลายชั้นเพื่อให้ได้ข้อมูลครบถ้วน)
    $sql = "SELECT 
            t1.id,
            t1.is_verified,
            t1.amount_used,
            t1.res_acid_base,
            t1.res_blood,
            DATE_FORMAT(t1.test_date, '%d/%m/%Y') AS test_date_show,
            t1.test_date AS test_date_raw,
            
            -- ข้อมูลจากการเตรียม
            t2.prep_name,
            DATE_FORMAT(t2.prep_date, '%d/%m/%Y') AS prep_date_show,
            DATE_FORMAT(t2.expiry_date, '%d/%m/%Y') AS exp_date_show,

            -- ดึงชื่อหน่วยงานต้นสังกัด
                (
                    SELECT md_sub.department_name 
                    FROM preparation_ingredients pi_sub
                    JOIN chemical_inventory inv_sub ON pi_sub.inventory_id = inv_sub.id
                    JOIN master_chemical_list mcl_sub ON inv_sub.chemical_id = mcl_sub.id
                    JOIN master_departments md_sub ON mcl_sub.department_id = md_sub.id
                    WHERE pi_sub.prep_id = t2.id AND pi_sub.status_delete = 0 
                    LIMIT 1
                ) AS department_name,

            -- ใช้ GROUP_CONCAT มัดรวมรายการสารตั้งต้นเข้าด้วยกันกันแถวแตก
            GROUP_CONCAT(DISTINCT t3.lot_number SEPARATOR ', ') AS source_lot_number,
            GROUP_CONCAT(DISTINCT t4.chemical_name SEPARATOR ' + ') AS master_chem_name,
            GROUP_CONCAT(DISTINCT t4.chemical_brand SEPARATOR ', ') AS chemical_brand,

            -- ข้อมูลหน่วยนับจากตาราง Units
            t5.unit_name_th, t5.unit_name_en, t5.unit_symbol, t5.unit_group,
            
            -- ชื่อผู้ทดสอบ (Tester)
            CONCAT(IFNULL(r1.rank_name, ''), ' ', p1.first_name, ' ', p1.last_name) AS tester_fullname,
            
            -- ชื่อผู้ทวนสอบ (Verifier)
            CONCAT(IFNULL(r2.rank_name, ''), ' ', p2.first_name, ' ', p2.last_name) AS verifier_fullname

        FROM trans_chemical_validation t1 
        INNER JOIN chemical_preparation t2 ON t1.prep_id = t2.id

        -- เชื่อมโยงผ่านตารางสูตรย่อยเพื่อเข้าคลังสินค้า
        LEFT JOIN preparation_ingredients pi ON t2.id = pi.prep_id AND pi.status_delete = 0
        LEFT JOIN chemical_inventory t3 ON pi.inventory_id = t3.id
        LEFT JOIN master_chemical_list t4 ON t3.chemical_id = t4.id
        LEFT JOIN master_chemical_units t5 ON t2.unit_id = t5.id

        -- Join สำหรับผู้ทดสอบ
        LEFT JOIN user_profile p1 ON t1.tester_id = p1.user_id
        LEFT JOIN user_rank r1 ON p1.rank_id = r1.rank_id
        
        -- Join สำหรับผู้ทวนสอบ
        LEFT JOIN user_profile p2 ON t1.verifier_id = p2.user_id
        LEFT JOIN user_rank r2 ON p2.rank_id = r2.rank_id
        
        $where 
        GROUP BY t1.id,
                 t2.prep_name, t2.prep_date, t2.expiry_date,
                 t5.unit_name_th, t5.unit_name_en, t5.unit_symbol, t5.unit_group,
                 r1.rank_name, p1.first_name, p1.last_name,
                 r2.rank_name, p2.first_name, p2.last_name
        ORDER BY t1.test_date DESC, t1.id DESC 
        LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. วนลูปปั้นข้อมูลหน่วยนับ 
    $finalData = [];
    foreach ($rawData as $row) {
        $unitDisplay = '-';
        if (!empty($row['unit_name_th'])) {
            $isPkg = (strpos($row['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($row['unit_group'], 'Packaging') !== false);
            $symbol = $isPkg ? $row['unit_name_en'] : $row['unit_symbol'];
            $unitDisplay = $row['unit_name_th'] . " (" . $symbol . ")";
        }
        $row['unit_display'] = $unitDisplay;
        $finalData[] = $row;
    }

    // 7. Query นับจำนวนทั้งหมดเพื่อทำ Pagination
    $sqlCount = "SELECT COUNT(t1.id) as countdata 
                 FROM trans_chemical_validation t1 
                 INNER JOIN chemical_preparation t2 ON t1.prep_id = t2.id
                 $where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $countRes = $stmtCount->fetch(PDO::FETCH_ASSOC);

    $totalRows = (int)$countRes['countdata'];
    $totalPages = ceil($totalRows / $limit);

    // 8. ส่งข้อมูลกลับไปยัง Frontend
    echo json_encode([
        'status' => 'success',
        'data' => $finalData,
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
