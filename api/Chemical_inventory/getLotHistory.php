<?php
session_start();
ini_set('display_errors', 0); // ปิด Error ใน Production
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. เช็ค Session ความปลอดภัย
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. รับค่า chemical_id (รหัสสารเคมีหลักจากตาราง Master)
$chemical_id = $_GET['chemical_id'] ?? '';

if (empty($chemical_id)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสสารเคมี']);
    exit;
}

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role    = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Control)
    if ($user_role !== 'admin') {
        $stmtCheckChem = $pdo->prepare("SELECT department_id FROM master_chemical_list WHERE id = ? AND status_delete = 0");
        $stmtCheckChem->execute([$chemical_id]);
        $chem_dept_id = $stmtCheckChem->fetchColumn();

        if (!$chem_dept_id || $chem_dept_id != $user_dept_id) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์เข้าถึง: รายการสารเคมีนี้อยู่คนละหน่วยงาน']);
            exit;
        }
    }

    // 3. Query ดึงรายการล็อตทั้งหมดของสารเคมีนี้
    // Join หาข้อมูลผู้บันทึก (Create By) เพื่อแสดงในตารางประวัติแบบละเอียด
    $sql = "SELECT 
                t.id, 
                t.chemical_id, 
                t.lot_number, 
                t.receive_date, 
                t.expiry_date, 
                t.quantity, 
                t.quantity_remaining, 
                t.location_stored,
                t.remark,
                DATE_FORMAT(t.receive_date, '%d/%m/%Y') AS receive_date_show,
                DATE_FORMAT(t.expiry_date, '%d/%m/%Y') AS expiry_date_show,
                t.expiry_date AS expiry_date_raw, -- สำหรับให้ JS คำนวณสี Badge
                t.create_by,

                -- ข้อมูลหน่วยนับ
                t2.unit_name_th,
                t2.unit_name_en,
                t2.unit_symbol,
                t2.unit_group,

                -- ดึงชื่อผู้บันทึกล็อตนี้
                CONCAT(r_rank.rank_name, r_prof.first_name, ' ', r_prof.last_name) AS creator_name

            FROM chemical_inventory t 
            LEFT JOIN master_chemical_list m ON t.chemical_id = m.id
            LEFT JOIN master_chemical_units t2 ON m.unit_id = t2.id 
            LEFT JOIN user_profile r_prof ON t.create_by = r_prof.user_id
            LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id

            WHERE t.chemical_id = ? AND t.status_delete = 0 
            ORDER BY t.expiry_date ASC, t.receive_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$chemical_id]);
    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. วนลูปเพื่อปั้น unit_display ให้ Frontend ใช้งานได้ทันที
    $lots = [];
    foreach ($rawData as $row) {
        $unitDisplay = '-';
        if (!empty($row['unit_name_th'])) {
            // Logic เดียวกัน: Packaging ใช้ชื่ออังกฤษ / อื่นๆ ใช้ตัวย่อ
            $isPackaging = (strpos($row['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($row['unit_group'], 'Packaging') !== false);
            $symbol = $isPackaging ? $row['unit_name_en'] : $row['unit_symbol'];
            $unitDisplay = $row['unit_name_th'] . " (" . $symbol . ")";
        }
        $row['unit_display'] = $unitDisplay;
        $lots[] = $row;
    }

    // 4. ส่งข้อมูลกลับ (ใช้คีย์ 'data' เพื่อให้ JS วนลูปสร้าง <tr> ได้เลย)
    echo json_encode([
        'status' => 'success',
        'data' => $lots
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
