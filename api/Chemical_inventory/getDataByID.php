<?php
session_start();
// เปิด Error Reporting สำหรับการ Debug (ปิดเมื่อใช้งานจริง)
ini_set('display_errors', 0);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบ Security Check (Login หรือยัง?)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. ตรวจสอบ Parameter ID
$id = $_GET['id'] ?? '';

if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสรายการที่ต้องการดึงข้อมูล']);
    exit;
}

try {
    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    $user_role    = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // 3. Query ข้อมูลโดย Join กับตาราง Master เพื่อเอาชื่อและยี่ห้อมาโชว์ใน Reference Card
    $sql = "SELECT 
                t1.*, 
                t2.chemical_name, 
                t2.chemical_brand,
                t2.department_id,
                t3.unit_name_th,
                t3.unit_name_en,
                t3.unit_symbol,
                t3.unit_group,
                t_dept.department_name 
            FROM chemical_inventory t1
            LEFT JOIN master_chemical_list t2 ON t1.chemical_id = t2.id
            LEFT JOIN master_chemical_units t3 ON t2.unit_id = t3.id
            LEFT JOIN master_departments t_dept ON t2.department_id = t_dept.id 
            WHERE t1.id = ? AND t1.status_delete = 0";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Control)
        if ($user_role !== 'admin') {
            if ($data['department_id'] != $user_dept_id) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'ไม่มีสิทธิ์เข้าถึง: รายการนี้เป็นของหน่วยงานอื่น'
                ]);
                exit;
            }
        }
        // --- 4. Logic การจัดการหน่วยนับ ---
        $unitDisplay = '-';
        if (!empty($data['unit_name_th'])) {
            // เช็กกลุ่มเพื่อเลือกตัวย่อ (ml, kg) หรือ ชื่ออังกฤษ (Bottle, Kit)
            $isPackaging = (strpos($data['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($data['unit_group'], 'Packaging') !== false);
            $symbol = $isPackaging ? $data['unit_name_en'] : $data['unit_symbol'];
            $unitDisplay = $data['unit_name_th'] . " (" . $symbol . ")";
        }

        // เพิ่มฟิลด์ใหม่สำหรับแสดงผลโดยเฉพาะ
        $data['unit_display'] = $unitDisplay;

        // 5. ส่งข้อมูลกลับ
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบข้อมูลล็อตสารเคมีนี้ หรืออาจถูกลบไปแล้ว'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
