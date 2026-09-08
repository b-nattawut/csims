<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. เช็คสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. รับค่า ID ที่ต้องการดึงข้อมูล
$id = $_GET['id'] ?? '';

if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสแผน (Missing ID)']);
    exit;
}

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // 3. Query ดึงข้อมูล Header พร้อม JOIN หาชื่อหน่วยงาน
    $sql = "SELECT 
                t1.id, 
                t1.plan_year, 
                t1.group_name, 
                t1.department_id,          
                md.department_name,        
                t1.create_by 
            FROM trans_maintenance_plan_header t1
            LEFT JOIN master_departments md ON t1.department_id = md.id
            WHERE t1.id = ? AND t1.delete_token = 0";

    $params = [$id];

    // ควบคุมสิทธิ์การมองเห็นข้อมูลตามหน่วยงาน (Security & Department Filter)
    if ($user_role !== 'admin') {
        // หากไม่ใช่ Admin บังคับให้ดึงได้เฉพาะแผนงานของหน่วยงานตัวเองเท่านั้น
        $sql .= " AND t1.department_id = ?";
        $params[] = $user_dept_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // ดึงข้อมูลแค่แถวเดียว (fetch ไม่ใช่ fetchAll)
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        echo json_encode([
            'status' => 'success',
            'data'   => $data
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status'  => 'error',
            'message' => 'ไม่พบข้อมูล หรือคุณไม่มีสิทธิ์เข้าถึงแผนงานนี้'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
