<?php
session_start();
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
    // 1. ดึง department_id ของ User ที่ล็อกอินอยู่
    $stmtUser = $pdo->prepare("SELECT department_id, role FROM users WHERE user_id = ?");
    $stmtUser->execute([$user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData || empty($userData['department_id'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบข้อมูลหน่วยงานของคุณในระบบ'
        ]);
        exit;
    }

    $user_dept_id = $userData['department_id'];
    $user_role    = strtolower($userData['role'] ?? '');

    // รับค่าจาก GET
    $header_id       = isset($_GET['header_id']) ? (int)$_GET['header_id'] : 0;
    $request_dept_id = $_GET['department_id'] ?? ''; 
    $plan_year       = $_GET['plan_year'] ?? '';     
    $group_name      = trim($_GET['group_name'] ?? '');

    // กำหนดตัวแปรสำหรับกรองหน่วยงาน
    $filter_dept_id = null;
    $active_header_id = $header_id;

   if ($user_role !== 'admin') {
        // กรณี User ทั่วไป: บังคับใช้หน่วยงานของตัวเองเสมอ
        $filter_dept_id = $user_dept_id;
    } else {
        // กรณี Admin:
        if (!empty($request_dept_id)) {
            // ถ้ามีการเลือกหน่วยงานผ่าน Dropdown ใน Modal ให้ใช้ค่านั้น
            $filter_dept_id = $request_dept_id;
        } else if ($header_id > 0) {
            // ถ้าเป็นการ Edit แต่ยังไม่ได้เปลี่ยนหน่วยงาน ให้ดึงหน่วยงานเดิมของ Header นั้นมาใช้
            $stmtH = $pdo->prepare("SELECT department_id FROM trans_maintenance_plan_header WHERE id = ?");
            $stmtH->execute([$header_id]);
            $filter_dept_id = $stmtH->fetchColumn() ?: null;
        } else {
            // ถ้าเป็นเพิ่มแผนใหม่ และยังไม่เลือกหน่วยงาน -> ให้เป็น null เพื่อแสดง "เครื่องมือทั้งหมด"
            $filter_dept_id = null;
        }

        // [Core Logic] กรณี Admin สลับหน่วยงานระหว่าง Edit:
        // เช็คว่าหน่วยงานใหม่ที่เลือก มี Header ของปี ($plan_year) และกลุ่มงาน ($group_name) นี้อยู่แล้วหรือยัง?
        if ($header_id > 0 && !empty($plan_year) && !empty($group_name) && !empty($filter_dept_id)) {
            $stmtFindOther = $pdo->prepare("SELECT id FROM trans_maintenance_plan_header 
                                          WHERE plan_year = ? AND group_name = ? AND department_id = ? AND delete_token = 0");
            $stmtFindOther->execute([$plan_year, $group_name, $filter_dept_id]);
            $foundOtherHeaderId = $stmtFindOther->fetchColumn();

            if ($foundOtherHeaderId) {
                // ถ้าพบแผนของหน่วยงานนั้นอยู่แล้ว ให้ดึงข้อมูล Checkbox ของอันนั้นมาแสดงแทน
                $active_header_id = $foundOtherHeaderId;
            } else {
                // ถ้ายังไม่มีแผนในปีนี้ของหน่วยงานนั้น ให้เคลียร์เป็น 0 (แสดงเครื่องมือเปล่ายังไม่ติ๊ก)
                $active_header_id = 0;
            }
        }
    }

    if ($header_id == 0 && $active_header_id == 0) {
        // --- กรณี: กดปุ่ม "เพิ่มแผนใหม่" (ไม่มี Header ID) ---
        // ดึงเครื่องมือทั้งหมดที่ยังไม่ถูกลบ มาวาดตารางเปล่าๆ 
        // ทำการ LEFT JOIN ดึงชื่อผู้ดูแลมาด้วย
        $sql = "SELECT 
                    t1.id, 
                    t1.tool_name, 
                    t1.asset_no, 
                    t1.serial_no, 
                    t1.brand,
                    t1.model, 
                    
                    -- สร้างคอลัมน์จำลอง m1-m12 ให้เป็น 0 (ยังไม่ถูกติ๊ก)
                    0 as m1, 0 as m2, 0 as m3, 0 as m4, 0 as m5, 0 as m6, 
                    0 as m7, 0 as m8, 0 as m9, 0 as m10, 0 as m11, 0 as m12,
                    
                    -- [เพิ่มใหม่] ดึงชื่อผู้ดูแลจากตาราง Master
                    CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS responsible_name,
                    
                    '' as remark 
                FROM master_equipment_list t1
                LEFT JOIN users r_usr ON t1.responsible_id = r_usr.user_id
                LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
                LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
                WHERE t1.status_delete = 0";

        $params = [];

        // ถ้าระบุหน่วยงาน ($filter_dept_id ไม่เป็น null) ถึงจะเติมเงื่อนไข WHERE กรองตามหน่วยงาน
        // แต่ถ้าเป็น Admin แล้วยังไม่เลือกหน่วยงาน ($filter_dept_id เป็น null) SQL จะไม่กรอง ปล่อยแสดงทั้งหมด
        if ($filter_dept_id !== null) {
            $sql .= " AND t1.department_id = ?";
            $params[] = $filter_dept_id;
        }

        $sql .= " ORDER BY t1.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // --- กรณี: กดปุ่ม "แก้ไข" หรือ "ดูรายละเอียด" (มี Header ID ส่งมา) ---
        // ใช้ LEFT JOIN ระหว่าง master_equipment_list กับ trans_maintenance_plan_detail
        // เพื่อให้ได้เครื่องมือครบทุกตัว และเอาค่าแผน (m1-m12) กับหมายเหตุที่เคยบันทึกไว้มาแปะ
        $sql = "SELECT 
                    e.id, 
                    e.tool_name, 
                    e.asset_no, 
                    e.serial_no, 
                    e.brand,
                    e.model,
                    
                    -- ถ้ามีข้อมูลใน detail ให้เอาค่านั้นมาใช้ ถ้าไม่มีให้เป็น 0
                    COALESCE(d.m1, 0) as m1, 
                    COALESCE(d.m2, 0) as m2, 
                    COALESCE(d.m3, 0) as m3, 
                    COALESCE(d.m4, 0) as m4, 
                    COALESCE(d.m5, 0) as m5, 
                    COALESCE(d.m6, 0) as m6, 
                    COALESCE(d.m7, 0) as m7, 
                    COALESCE(d.m8, 0) as m8, 
                    COALESCE(d.m9, 0) as m9, 
                    COALESCE(d.m10, 0) as m10, 
                    COALESCE(d.m11, 0) as m11, 
                    COALESCE(d.m12, 0) as m12,
                    
                    -- ดึงชื่อผู้ดูแลอัปเดตล่าสุดจาก Master แทนการดึงจาก Detail
                    CONCAT(r_rank.rank_name, ' ', r_prof.first_name, ' ', r_prof.last_name) AS responsible_name,
                    
                    -- ดึงหมายเหตุที่เคยกรอกไว้
                    COALESCE(d.remark, '') as remark 
                    
                FROM master_equipment_list e
                
                -- JOIN ดึงชื่อผู้ดูแลปัจจุบัน
                LEFT JOIN users r_usr ON e.responsible_id = r_usr.user_id
                LEFT JOIN user_profile r_prof ON r_usr.user_id = r_prof.user_id
                LEFT JOIN user_rank r_rank ON r_prof.rank_id = r_rank.rank_id
                
                -- JOIN ดึงข้อมูลการติ๊กแผน จากแผนของปีนั้นๆ
                LEFT JOIN trans_maintenance_plan_detail d ON e.id = d.equipment_id AND d.header_id = ?
                
                WHERE e.status_delete = 0";
        
        $params = [$active_header_id];

        if ($filter_dept_id !== null) {
            $sql .= " AND e.department_id = ?";
            $params[] = $filter_dept_id;
        }

        $sql .= " ORDER BY e.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'status' => 'success',
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
