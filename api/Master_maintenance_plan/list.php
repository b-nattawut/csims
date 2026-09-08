<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

try {
    // ดึงข้อมูลแผนงานแต่ละรายการเครื่องมือ (ไม่ GROUP BY เพื่อแยกแต่ละรายการ)
    $stmt = $pdo->prepare("
        SELECT 
            mc.id,
            mc.annual_year,
            mc.group_code,
            mc.list_tool_name,
            mc.serialNo,
            mc.brand,
            mc.m_jan, mc.m_feb, mc.m_march, mc.m_apr, mc.m_may, mc.m_jun,
            mc.m_jul, mc.m_aug, mc.m_sep, mc.m_oct, mc.m_nov, mc.m_dec,
            mc.responsive_person,
            mc.remark,
            mc.create_by,
            mc.create_date,
            CONCAT(IFNULL(t3.rank_name, ''), ' ', IFNULL(t2.first_name, ''), ' ', IFNULL(t2.last_name, '')) as creator_name
        FROM master_instrument_calibration mc
        LEFT JOIN users t1 ON mc.create_by = t1.user_id
        LEFT JOIN user_profile t2 ON t1.user_id = t2.user_id
        LEFT JOIN user_rank t3 ON t2.rank_id = t3.rank_id
        WHERE (mc.statusDelete = 0 OR mc.statusDelete IS NULL)
        ORDER BY mc.annual_year DESC, mc.group_code ASC, mc.id ASC
    ");
    
    $stmt->execute();
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ถ้าไม่มีข้อมูล ส่ง array ว่างกลับ
    if (empty($plans)) {
        echo json_encode([
            "status" => "success",
            "data" => []
        ]);
        exit;
    }
    
    // สร้าง result array พร้อมข้อมูลแต่ละรายการ
    $result = [];
    foreach ($plans as $plan) {
        $result[] = [
            'id' => $plan['id'],
            'annual_year' => $plan['annual_year'] ?? '',
            'group_code' => $plan['group_code'] ?? '',
            'list_tool_name' => $plan['list_tool_name'] ?? '',
            'serialNo' => $plan['serialNo'] ?? '',
            'brand' => $plan['brand'] ?? '',
            'm_jan' => $plan['m_jan'] ?? 0,
            'm_feb' => $plan['m_feb'] ?? 0,
            'm_march' => $plan['m_march'] ?? 0,
            'm_apr' => $plan['m_apr'] ?? 0,
            'm_may' => $plan['m_may'] ?? 0,
            'm_jun' => $plan['m_jun'] ?? 0,
            'm_jul' => $plan['m_jul'] ?? 0,
            'm_aug' => $plan['m_aug'] ?? 0,
            'm_sep' => $plan['m_sep'] ?? 0,
            'm_oct' => $plan['m_oct'] ?? 0,
            'm_nov' => $plan['m_nov'] ?? 0,
            'm_dec' => $plan['m_dec'] ?? 0,
            'responsive_person' => $plan['responsive_person'] ?? '',
            'creator_name' => trim($plan['creator_name']) ?: '-',
            'create_date' => $plan['create_date']
        ];
    }
    
    echo json_encode([
        "status" => "success",
        "data" => $result
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage()
    ]);
}
