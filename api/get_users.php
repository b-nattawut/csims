<?php
session_start();
require '../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// ป้องกันการเข้าถึงโดยไม่ได้ Login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$q = $_GET['q'] ?? '';
$category = $_GET['category'] ?? ''; // รับประเภทสังกัด
$detail = $_GET['detail'] ?? '';     // รับลำดับ/จังหวัด

// Mapping รหัสจังหวัดเพราะไม่ได้มี table เก็บ
$ptjv_map = [
    'ปัตตานี' => 94,
    'ยะลา' => 95,
    'นราธิวาส' => 96
];

try {
    $sql = "SELECT 
                t1.user_id, 
                CONCAT(IFNULL(t3.rank_name, ''), ' ', t2.first_name, ' ', t2.last_name) AS fullname,
                IFNULL(t4.position_name, '') AS position_name
            FROM users t1
            INNER JOIN user_profile t2 ON t1.user_id = t2.user_id
            LEFT JOIN user_rank t3 ON t2.rank_id = t3.rank_id
            LEFT JOIN user_position t4 ON t2.position_id = t4.position_id
            WHERE t1.is_active = 1";
    
    $params = [];

    // 3. ถ้ามีการส่งค่า q มา ให้เพิ่มเงื่อนไขการค้นหา (Dynamic WHERE)
    if (!empty($q)) {
        // ค้นหาจาก ชื่อ, นามสกุล หรือ ชื่อเต็มที่ต่อกันแล้ว
        $sql .= " AND (t2.first_name LIKE ? 
                       OR t2.last_name LIKE ? 
                       OR CONCAT(IFNULL(t3.rank_name, ''), t2.first_name, t2.last_name) LIKE ?)";
        $searchTerm = "%$q%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Logic กรองตามสังกัดเฉพาะ
    if (!empty($category) && !empty($detail)) {
        if ($category === 'นวท.(สบ....)') {
            $sql .= " AND t1.nvt_sub = ? ";
            $params[] = $detail;
        } elseif ($category === 'ศพฐ.') {
            $sql .= " AND t1.spt_sub = ? ";
            $params[] = $detail;
        } elseif ($category === 'พฐ.จว.') {
            // ใช้รหัสจังหวัด (94, 95, 96) จาก Map
            $ptjv_code = $ptjv_map[$detail] ?? 0;
            $sql .= " AND t1.ptjv_sub = ? ";
            $params[] = $ptjv_code;
        }
    }

    $sql .= " ORDER BY t2.first_name ASC"; 
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $users
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้งาน'
    ]);
}
?>
