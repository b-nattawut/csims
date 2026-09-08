<?php
session_start();
// เปิด Error Reporting ช่วง Dev
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. ตรวจสอบ Session (Security Check)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit;
}

// 2. รับค่าจาก POST
$id = $_POST['id'] ?? ''; // ถ้ามี ID คือการแก้ไข
$department_name = trim($_POST['department_name'] ?? ''); 
$user_id = $_SESSION['user_id'];
$dateNow = date('Y-m-d H:i:s');

// ตรวจสอบค่าว่างเบื้องต้น
if (empty($department_name)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุชื่อหน่วยงาน']);
    exit;
}

try {
    // -------------------------------------------------------------------------
    // 3. ตรวจสอบชื่อซ้ำ (Duplicate Check)
    // -------------------------------------------------------------------------
    // เช็คเฉพาะรายการที่ยังไม่ถูกลบ (status_delete = 0)
    $sqlCheck = "SELECT id FROM master_departments 
                WHERE department_name = ? AND status_delete = 0";
    $paramsCheck = [$department_name];

    // ถ้าเป็นการแก้ไข ให้ยกเว้น ID ตัวเอง
    if (!empty($id)) {
        $sqlCheck .= " AND id != ?";
        $paramsCheck[] = $id;
    }

    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute($paramsCheck);

    if ($stmtCheck->rowCount() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'ชื่อหน่วยงานนี้มีอยู่ในระบบแล้ว']);
        exit;
    }

    // -------------------------------------------------------------------------
    // 4. Logic บันทึกข้อมูล (Insert / Update)
    // -------------------------------------------------------------------------
    if (!empty($id)) {
        // --- กรณีแก้ไข (UPDATE) ---
        $sql = "UPDATE master_departments SET 
                    department_name = :name, 
                    updated_by = :user_id, 
                    updated_at = :now
                WHERE id = :id AND status_delete = 0";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'    => $department_name,
            ':user_id' => $user_id,
            ':now'     => $dateNow,
            ':id'      => $id
        ]);
        $message = "แก้ไขข้อมูลหน่วยงานเรียบร้อยแล้ว";
    } else {
        // --- กรณีเพิ่มใหม่ (INSERT) ---
        $sql = "INSERT INTO master_departments (
                    department_name, 
                    created_by, 
                    created_at,
                    status_delete
                ) VALUES (:name, :user_id, :now, 0)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'    => $department_name,
            ':user_id' => $user_id,
            ':now'     => $dateNow
        ]);
        $message = "เพิ่มหน่วยงานใหม่เรียบร้อยแล้ว";
    }

    echo json_encode([
        'status' => 'success',
        'message' => $message
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
?>