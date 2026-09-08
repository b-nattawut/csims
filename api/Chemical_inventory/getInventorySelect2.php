<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบความปลอดภัย
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่กำลังล็อกอินอยู่
    $stmtUser = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
    $stmtUser->execute([$user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลผู้ใช้งานในระบบ']);
        exit;
    }

    $user_role    = strtolower($userData['role'] ?? '');
    $user_dept_id = $userData['department_id'] ?? null;


    // 2. รับค่าการค้นหาจาก Select2
    $searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
    // รับค่ารหัสหน่วยงานที่ถูกล็อกมาจากแถวแรก (ถ้ามี)
    $locked_dept_id = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';

    // 3. Query ค้นหาข้อมูล (JOIN t1: Inventory + t2: Master)
    // เงื่อนไข: ไม่ถูกลบ และ ปริมาณคงเหลือต้องมากกว่า 0
    $sql = "SELECT 
                t1.id, 
                t2.id as chemical_id,  
                t2.chemical_name, 
                t2.chemical_brand, 
                t2.unit_id,
                t2.department_id,
                t1.lot_number, 
                t1.quantity_remaining, 
                t1.expiry_date,
                -- ข้อมูลหน่วยนับ
                t3.unit_name_th,
                t3.unit_name_en,
                t3.unit_symbol,
                t3.unit_group,
                t_dept.department_name 
            FROM chemical_inventory t1
            INNER JOIN master_chemical_list t2 ON t1.chemical_id = t2.id
            LEFT JOIN master_chemical_units t3 ON t2.unit_id = t3.id
            LEFT JOIN master_departments t_dept ON t2.department_id = t_dept.id 
            WHERE t1.status_delete = 0 
              AND t2.status_delete = 0
              AND t1.quantity_remaining > 0
              AND (
                  t1.expiry_date >= CURDATE() 
                  OR t1.expiry_date IS NULL 
                  OR YEAR(t1.expiry_date) = 0
              )";

    $params = [];

    // ตรวจสอบสิทธิ์การเข้าถึงข้อมูลตามหน่วยงาน (Enforce Access Control)
    if ($user_role !== 'admin') {
        // กรณี User ทั่วไป:
        // ถ้ามีการล็อกหน่วยงานจากแถวแรกมา และไม่ตรงกับหน่วยงานตัวเอง -> บล็อกการเข้าถึง
        if (!empty($locked_dept_id) && $locked_dept_id != $user_dept_id) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึงคลังสารเคมีของหน่วยงานอื่น']);
            exit;
        }

        // บังคับกรองเฉพาะหน่วยงานตนเองเสมอ
        $sql .= " AND t2.department_id = :dept_id ";
        $params[':dept_id'] = $user_dept_id;
    } else {
        // กรณี Admin: ถ้ามีการล็อกหน่วยงานส่งมาจากหน้าบ้าน ให้กรองตามหน่วยงานนั้น
        if (!empty($locked_dept_id)) {
            $sql .= " AND t2.department_id = :dept_id ";
            $params[':dept_id'] = $locked_dept_id;
        }
    }

    // เพิ่มเงื่อนไขการค้นหาข้อความ (q)
    $sql .= " AND (
                t2.chemical_name LIKE :q1 
                OR t2.chemical_brand LIKE :q2 
                OR t1.lot_number LIKE :q3
            )
            ORDER BY t1.expiry_date ASC, t2.chemical_name ASC
            LIMIT 30";

    $stmt = $pdo->prepare($sql);

    // กำหนดค่า Wildcard สำหรับการค้นหา
    $searchWildcard = "%$searchTerm%";
    $params[':q1'] = $searchWildcard;
    $params[':q2'] = $searchWildcard;
    $params[':q3'] = $searchWildcard;

    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedResults = [];

    foreach ($rows as $row) {
        // --- จัดรูปแบบหน่วยนับ (Logic เดียวกับส่วนอื่นๆ) ---
        $unitText = $row['unit_symbol'] ?: '-';
        if (!empty($row['unit_name_th'])) {
            $isPackaging = (strpos($row['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($row['unit_group'], 'Packaging') !== false);
            $symbol = $isPackaging ? $row['unit_name_en'] : $row['unit_symbol'];
            $unitText = $row['unit_name_th'] . " (" . $symbol . ")";
        }

        $deptSuffix = ($user_role === 'admin') ? " - [ " . ($row['department_name'] ?: 'ไม่ระบุ') . " ]" : "";

        // ชื่อหัวข้อกลุ่ม (ชื่อสารเคมี + ยี่ห้อ + หน่วยงาน)
        $chemGroupName = $row['chemical_name'] .
            ($row['chemical_brand'] ? " (" . $row['chemical_brand'] . ")" : "") .
            $deptSuffix;

        if (!isset($groupedResults[$chemGroupName])) {
            $groupedResults[$chemGroupName] = [
                'text' => $chemGroupName,
                'children' => []
            ];
        }

        $nbsp = "\u{00A0}";
        // รายการย่อย (ล็อต)
        $groupedResults[$chemGroupName]['children'][] = [
            'id' => $row['id'],
            'text' => $row['chemical_name'] . " | Lot: " . $row['lot_number'] . $nbsp . "[ คงเหลือ: " . number_format($row['quantity_remaining'], 2) . " " . $unitText . " ]",
            'lot_number' => $row['lot_number'],
            'remain' => $row['quantity_remaining'],
            'unit_display' => $unitText, // ส่งไปโชว์หน้าบ้าน
            'chem_name' => $row['chemical_name'],
            'unit_id' => (int)$row['unit_id'],
            'chemical_id' => $row['chemical_id'],
            'department_id' => $row['department_id'] 
        ];
    }

    echo json_encode(['results' => array_values($groupedResults)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
