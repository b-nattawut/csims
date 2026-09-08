<?php
session_start();
require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// รับ ID สำหรับดึงข้อมูลรายการเดียว
$id = $_GET["id"] ?? null;
// หรือรับ annual_year และ group_code เพื่อดึงข้อมูลทั้ง group (เพื่อ backward compatibility)
$annual_year = $_GET["annual_year"] ?? null;
$group_code = $_GET["group_code"] ?? null;

try {
    // ถ้ามี id ให้ดึงข้อมูลรายการเดียว
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT 
                mc.id,
                mc.annual_year,
                mc.group_code,
                mc.dpt_id,
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
            WHERE mc.id = ?
            AND (mc.statusDelete = 0 OR mc.statusDelete IS NULL)
        ");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            echo json_encode([
                'status' => 'success',
                'data' => $item
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่พบข้อมูล'
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    // ถ้ามี annual_year และ group_code ให้ดึงข้อมูลทั้ง group
    else if ($annual_year && $group_code) {
        $stmt = $pdo->prepare("
            SELECT 
                mc.id,
                mc.annual_year,
                mc.group_code,
                mc.dpt_id,
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
            WHERE mc.annual_year = ?
            AND mc.group_code = ?
            AND (mc.statusDelete = 0 OR mc.statusDelete IS NULL)
            ORDER BY mc.id ASC
        ");
        $stmt->execute([$annual_year, $group_code]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($items) > 0) {
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'annual_year' => $annual_year,
                    'group_code' => $group_code,
                    'items' => $items
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่พบข้อมูล'
            ], JSON_UNESCAPED_UNICODE);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'กรุณาระบุ ID หรือ ปี/กลุ่มงาน'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
