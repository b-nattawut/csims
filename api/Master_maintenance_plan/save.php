<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

$dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s');

// รับข้อมูล JSON จาก request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// ถ้าไม่มีข้อมูล JSON ให้ลองรับจาก POST ปกติ
if (empty($data)) {
    $data = $_POST;
}

$annual_year = $data["annual_year"] ?? '';
$group_code = $data["group_code"] ?? '';
$department = $data["department"] ?? '';
$plans = $data["plans"] ?? [];

try {
    $pdo->beginTransaction();
    
    $insertedCount = 0;
    
    // Insert แต่ละ plan เป็น record แยก
    foreach ($plans as $plan) {
        $stmt = $pdo->prepare("
            INSERT INTO master_instrument_calibration (
                annual_year,
                group_code,
                dpt_id,
                list_tool_name,
                serialNo,
                brand,
                m_jan,
                m_feb,
                m_march,
                m_apr,
                m_may,
                m_jun,
                m_jul,
                m_aug,
                m_sep,
                m_oct,
                m_nov,
                m_dec,
                responsive_person,
                remark,
                create_by,
                create_date
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->execute([
            $annual_year,
            $group_code,
            $department,
            $plan['equipName'] ?? '',
            $plan['serialNo'] ?? '',
            $plan['brand'] ?? '',
            isset($plan['months']) && in_array(1, $plan['months']) ? 1 : 0,
            isset($plan['months']) && in_array(2, $plan['months']) ? 1 : 0,
            isset($plan['months']) && in_array(3, $plan['months']) ? 1 : 0,
            isset($plan['months']) && in_array(4, $plan['months']) ? 1 : 0,
            isset($plan['months']) && in_array(5, $plan['months']) ? 1 : 0,
            isset($plan['months']) && in_array(6, $plan['months']) ? 1 : 0,
            isset($plan['months']) && in_array(7, $plan['months']) ? 1 : 0,
            isset($plan['months']) && in_array(8, $plan['months']) ? 1 : 0,
            isset($plan['months']) && in_array(9, $plan['months']) ? 1 : 0,
            isset($plan['months']) && in_array(10, $plan['months']) ? 1 : 0,
            isset($plan['months']) && in_array(11, $plan['months']) ? 1 : 0,
            isset($plan['months']) && in_array(12, $plan['months']) ? 1 : 0,
            $plan['responsible'] ?? null,
            $plan['note'] ?? '',
            $_SESSION['user_id'],
            $dateNow
        ]);
        
        $insertedCount++;
    }
    
    $pdo->commit();
    
    echo json_encode([
        "status" => "success", 
        "message" => "บันทึกข้อมูลสำเร็จ",
        "item_count" => $insertedCount
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
