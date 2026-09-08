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
    // ดึงข้อมูล Role และ Department ของผู้ใช้งาน (เพิ่มเช็ค is_active = 1)
    $stmtUser = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
    $stmtUser->execute([$user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        exit(json_encode(['results' => []]));
    }

    $user_role    = strtolower($userData['role'] ?? '');
    $user_dept_id = $userData['department_id'] ?? null;

    // 2. รับค่าการค้นหาจาก Select2
    $searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';

    // รับค่า department_id ที่อาจถูกส่งมาจากหน้าบ้าน (เช่น เมื่อล็อกหน่วยงานจากตาราง)
    $locked_dept_id = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';

    // 3. Query ค้นหาข้อมูลจากสารที่เตรียมไว้
    // เงื่อนไข: ไม่ถูกลบ, มีของเหลือ, และยังไม่หมดอายุ
    $sql = "SELECT 
                t1.id, 
                t1.prep_name, 
                t1.quantity_remaining, 
                t1.unit_id,
                t1.prep_date,
                t1.expiry_date,
                -- ใช้ GROUP_CONCAT รวบยอดกรณีมีสารตั้งต้นหลายตัว
                GROUP_CONCAT(DISTINCT TRIM(t2.lot_number) SEPARATOR ', ') AS source_lot_number,
                GROUP_CONCAT(DISTINCT TRIM(t3.chemical_name) SEPARATOR ' + ') AS master_chem_name,
                GROUP_CONCAT(DISTINCT TRIM(t3.chemical_brand) SEPARATOR ', ') AS chemical_brand,
                -- ข้อมูลหน่วยนับของสารที่เตรียมเสร็จ
                t4.unit_name_th,
                t4.unit_name_en,
                t4.unit_symbol,
                t4.unit_group,
                DATE_FORMAT(t1.prep_date, '%d/%m/%Y') AS prep_date_show,
                DATE_FORMAT(t1.expiry_date, '%d/%m/%Y') AS exp_date_show,
                -- ดึงชื่อหน่วยงานจากตาราง Master
                MAX(t_dept.department_name) AS department_name
            FROM chemical_preparation t1
            -- เปลี่ยนมา JOIN ผ่านตารางประวัติสารตั้งต้น
            LEFT JOIN preparation_ingredients pi ON t1.id = pi.prep_id AND pi.status_delete = 0
            LEFT JOIN chemical_inventory t2 ON pi.inventory_id = t2.id
            LEFT JOIN master_chemical_list t3 ON t2.chemical_id = t3.id
            LEFT JOIN master_departments t_dept ON t3.department_id = t_dept.id 

            -- JOIN ตารางหน่วยนับ
            LEFT JOIN master_chemical_units t4 ON t1.unit_id = t4.id 
            WHERE t1.status_delete = 0 
              AND t1.quantity_remaining > 0 -- กรองเฉพาะตัวที่ปริมาณมากกว่า 0 ( ยังไม่หมด )
              AND t1.expiry_date >= CURDATE() -- กรองเฉพาะที่ยังไม่หมดอายุ
              ";

    $params = [];

    // ตรวจสอบสิทธิ์การเข้าถึงข้อมูลตามหน่วยงานอย่างรัดกุม (Department Access Control)
    if ($user_role !== 'admin') {
        // กรณี User ทั่วไป: ถ้าส่ง locked_dept_id ของหน่วยงานอื่นมา ให้ส่งผลลัพธ์ว่างทันที
        if (!empty($locked_dept_id) && $locked_dept_id != $user_dept_id) {
            exit(json_encode(['results' => []]));
        }
        // บังคับกรองเฉพาะหน่วยงานตนเองเสมอ
        $sql .= " AND t3.department_id = :dept_id ";
        $params[':dept_id'] = $user_dept_id;
    } else {
        // กรณี Admin: ถ้ามีการล็อกหน่วยงานมาจากหน้าบ้าน ให้กรองตามหน่วยงานนั้น
        if (!empty($locked_dept_id)) {
            $sql .= " AND t3.department_id = :dept_id ";
            $params[':dept_id'] = $locked_dept_id;
        }
    }

    $sql .= " AND (t1.prep_name LIKE :q1 
                   OR t2.lot_number LIKE :q2 
                   OR t3.chemical_name LIKE :q3)
            -- ต้องมัดกลุ่มด้วย id หลัก เพื่อไม่ให้แถวแตกออกมาตามจำนวนสารตั้งต้น
            GROUP BY t1.id
            ORDER BY t1.expiry_date ASC, t1.prep_name ASC
            LIMIT 30";

    $stmt = $pdo->prepare($sql);

    $searchWildcard = "%$searchTerm%";
    $params[':q1'] = $searchWildcard;
    $params[':q2'] = $searchWildcard;
    $params[':q3'] = $searchWildcard;
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedResults = [];

    foreach ($rows as $row) {
        // --- 4. ปั้นหน่วยนับให้ "กริบ" (ลบ unitMapping เดิมทิ้ง) ---
        $unitDisplay = $row['unit_symbol'] ?: '-';
        if (!empty($row['unit_name_th'])) {
            $isPkg = (strpos($row['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($row['unit_group'], 'Packaging') !== false);
            $sym = $isPkg ? $row['unit_name_en'] : $row['unit_symbol'];
            $unitDisplay = $row['unit_name_th'] . " (" . $sym . ")";
        }

        // หัวข้อกลุ่ม (ชื่อสารเคมีหลัก)
        $brandText = $row['chemical_brand'] ? " (" . $row['chemical_brand'] . ")" : "";
        $deptText  = ($user_role === 'admin') ? ($row['department_name'] ? " - [ " . $row['department_name'] . " ]" : " - [ ไม่ระบุ ]") : "";
        $groupHeader = $row['master_chem_name'] . $brandText . $deptText;

        if (!isset($groupedResults[$groupHeader])) {
            $groupedResults[$groupHeader] = [
                'text' => $groupHeader,
                'children' => []
            ];
        }

        $nbsp = "\u{00A0}";
        // รายการลูก
        $groupedResults[$groupHeader]['children'][] = [
            'id' => $row['id'],
            'text' => "สารที่เตรียม: " . $row['prep_name'] . $nbsp . "(Lot: " . $row['source_lot_number'] . ")" . $nbsp . "[เหลือ: " . number_format($row['quantity_remaining'], 2) . " " . $unitDisplay . "]",

            // --- ส่ง Data กลับไปให้ Frontend ---
            'prep_name' => $row['prep_name'],
            'chemical_name' => $row['master_chem_name'],
            'chemical_brand' => $row['chemical_brand'],
            'source_lot_number' => $row['source_lot_number'],
            'prep_date_show' => $row['prep_date_show'],
            'exp_date_show' => $row['exp_date_show'],
            'prep_date_raw' => $row['prep_date'],
            'quantity_remaining' => $row['quantity_remaining'],
            'unit_display' => $unitDisplay, // ส่งตัวหนังสือแบบเต็มไปแสดงผล
            'unit_id' => $row['unit_id'],    // ส่ง ID ไปใช้บันทึก
            'department_name' => $row['department_name']
        ];
    }

    echo json_encode(['results' => array_values($groupedResults)], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
