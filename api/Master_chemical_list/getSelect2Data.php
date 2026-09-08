<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['results' => []]));
}

$user_id = $_SESSION['user_id'];
$searchTerm = isset($_GET['q']) ? $_GET['q'] : '';
$dept_filter = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';

try {
    // 1. ตรวจสอบ role และ department_id ของ User ที่ล็อกอิน
    $stmtUser = $pdo->prepare("SELECT department_id, role FROM users WHERE user_id = ? AND is_active = 1");
    $stmtUser->execute([$user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        exit(json_encode(['results' => []]));
    }

    $user_dept_id = $userData['department_id'] ?? null;
    $user_role    = strtolower($userData['role'] ?? '');

    $sql = "SELECT t1.id, t1.chemical_name, t1.chemical_brand, t1.unit_id, t1.department_id,
               t2.unit_symbol, t2.unit_name_en, t2.unit_group, md.department_name
        FROM master_chemical_list t1
        LEFT JOIN master_chemical_units t2 ON t1.unit_id = t2.id
        LEFT JOIN master_departments md ON t1.department_id = md.id
        WHERE t1.status_delete = 0 
        AND (t1.chemical_name LIKE ? OR t1.chemical_brand LIKE ?)";

    $params = ["%$searchTerm%", "%$searchTerm%"];

    if ($user_role !== 'admin') {
        // กรณี User ทั่วไป: ถ้าส่ง department_id อื่นมาที่ไม่ใช่ของตัวเอง -> ให้ส่งค่าว่างกลับทันที
        if (!empty($dept_filter) && $dept_filter != $user_dept_id) {
            exit(json_encode(['results' => []]));
        }
        // บังคับกรองเฉพาะหน่วยงานตนเองเสมอ
        $sql .= " AND t1.department_id = ?";
        $params[] = $user_dept_id;
    } else {
        // กรณี Admin: ถ้ามีการเลือกล็อกหน่วยงานจากหน้าบ้าน ให้กรองตามหน่วยงานนั้น
        if (!empty($dept_filter)) {
            $sql .= " AND t1.department_id = ?";
            $params[] = $dept_filter;
        }
    }
    
    // เพิ่มเรียงลำดับชื่อ และ LIMIT 30 ไม่ให้ Select2 โหลดข้อมูลหนักเกินไป
    $sql .= " ORDER BY t1.chemical_name ASC LIMIT 30";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($data as $row) {
        // Logic เดียวกับหน้าเว็บ: Packaging ใช้ English Name / อื่นๆ ใช้ Symbol
        $isPackaging = (strpos($row['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($row['unit_group'], 'Packaging') !== false);
        $displayUnit = $isPackaging ? $row['unit_name_en'] : $row['unit_symbol'];

        $deptLabel = ($user_role === 'admin' && !empty($row['department_name'])) ? " [" . $row['department_name'] . "]" : "";

        $results[] = [
            'id'    => $row['id'],
            // ปรับ text ให้โชว์แบรนด์ หน่วยนับ และหน่วยงานต่อท้าย
            'text'  => $row['chemical_name'] .
                ($row['chemical_brand'] ? " [" . $row['chemical_brand'] . "]" : "") .
                " (" . $displayUnit . ")" . $deptLabel,
            'unit'  => $row['unit_id'],
            'brand' => $row['chemical_brand'],
            'department_id' => $row['department_id']
        ];
    }

    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['results' => []]);
}
