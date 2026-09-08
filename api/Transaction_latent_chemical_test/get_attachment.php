<?php
session_start();

require '../../db_config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

// รับ ID ของไฟล์ที่ต้องการแสดง
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    exit("Invalid ID");
}

try {
    $user_id = $_SESSION['user_id'];

    // ดึงข้อมูล Role และ Department ของผู้ใช้งานปัจจุบัน (เพิ่มเช็ค is_active = 1 เพื่อความปลอดภัย)
    $stmtUser = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
    $stmtUser->execute([$user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        http_response_code(403);
        exit("Access Denied: ไม่พบข้อมูลผู้ใช้งาน หรือบัญชีถูกระงับ");
    }

    $user_role    = strtolower($userData['role'] ?? '');
    $user_dept_id = $userData['department_id'] ?? null;

    // 2. Query ดึงไฟล์พร้อมทั้งเช็คหน่วยงานต้นสังกัดของรายการตรวจความพร้อมนั้นๆ
    $sql = "SELECT a.file_content, a.file_type, a.original_name,
               (
                   SELECT mcl_sub.department_id 
                   FROM preparation_ingredients pi_sub
                   JOIN chemical_inventory inv_sub ON pi_sub.inventory_id = inv_sub.id
                   JOIN master_chemical_list mcl_sub ON inv_sub.chemical_id = mcl_sub.id
                   WHERE pi_sub.prep_id = t2.id AND pi_sub.status_delete = 0 
                   LIMIT 1
               ) AS record_dept_id
            FROM trans_latent_chemical_attachment a
            JOIN trans_latent_chemical_test t1 ON a.latent_test_id = t1.id
            JOIN chemical_preparation t2 ON t1.prep_id = t2.id
            WHERE a.id = ? AND a.status_delete = 0 AND t1.status_delete = 0";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file || empty($file['file_content'])) {
        http_response_code(404);
        exit("File not found");
    }

    // 3. ตรวจสอบสิทธิ์การเข้าถึง: ถ้าไม่ใช่ Admin และหน่วยงานไม่ตรงกัน ห้ามพ่นไฟล์ออกมาเด็ดขาด
    if ($user_role !== 'admin' && $file['record_dept_id'] != $user_dept_id) {
        http_response_code(403);
        exit("Access Denied: คุณไม่มีสิทธิ์ดูไฟล์ของหน่วยงานอื่น");
    }

    // 4. ตั้งค่า Header เพื่อบอก Browser ว่านี่คือไฟล์ชนิดไหน
    header("Content-Type: " . $file['file_type']);
    // เพิ่ม Content-Disposition inline เพื่อช่วยเปิดภาพบนหน้าเว็บได้สมบูรณ์ พร้อมส่งชื่อไฟล์เดิม
    header('Content-Disposition: inline; filename="' . rawurlencode($file['original_name']) . '"');
    header("Cache-Control: max-age=86400");
    
    // 5. พ่นข้อมูลไบนารีออกมา (รองรับทั้งกรณี PDO ส่งเป็น Stream Resource หรือ String)
    if (is_resource($file['file_content'])) {
        fpassthru($file['file_content']);
    } else {
        echo $file['file_content'];
    }
    exit;

} catch (PDOException $e) {
    // กรณี Database Error
    http_response_code(500);
    exit("Database Error");
}