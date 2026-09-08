<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require '../../db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// 1. เช็ค Session ความปลอดภัย
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// 2. ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ที่ต้องการ']);
    exit;
}

$id = $_GET["id"];
$user_id = $_SESSION['user_id'];

try {

    // ดึงข้อมูล Role และ Department ของผู้ใช้งานที่ล็อกอิน (เพิ่มเช็ค is_active = 1)
    $stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
    $stmtRole->execute([$user_id]);
    $userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

    if (!$userInfo) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลผู้ใช้งาน หรือบัญชีถูกระงับ']);
        exit;
    }

    $user_role    = strtolower($userInfo['role'] ?? '');
    $user_dept_id = $userInfo['department_id'] ?? null;

    // -------------------------------------------------------------------------
    // 3. Query ข้อมูลแบบ JOIN ครบวงจร 
    // -------------------------------------------------------------------------
    $sql = "SELECT 
            t1.*,
            
            -- ข้อมูลการตรวจความพร้อม (Format วันที่)
            t1.test_date AS test_date_raw,
            DATE_FORMAT(t1.test_date, '%d/%m/%Y') AS test_date_show,

            -- ข้อมูลสารเคมีที่เตรียมไว้ (Preparation)
            t2.prep_name,
            t2.prep_date AS prep_date_raw,
            t2.expiry_date AS exp_date_raw,
            t2.quantity_remaining AS prep_remain_now, -- ยอดคงเหลือจริงในขวดปัจจุบัน
            DATE_FORMAT(t2.prep_date, '%d/%m/%Y') AS prep_date_show,
            DATE_FORMAT(t2.expiry_date, '%d/%m/%Y') AS exp_date_show,

            -- ดึง department_id จากสารตั้งต้นเพื่อใช้เช็คสิทธิ์
                (
                    SELECT mcl_sub.department_id 
                    FROM preparation_ingredients pi_sub
                    JOIN chemical_inventory inv_sub ON pi_sub.inventory_id = inv_sub.id
                    JOIN master_chemical_list mcl_sub ON inv_sub.chemical_id = mcl_sub.id
                    WHERE pi_sub.prep_id = t2.id AND pi_sub.status_delete = 0 
                    LIMIT 1
                ) AS department_id,

            -- ดึงชื่อหน่วยงานต้นสังกัด
            (
                SELECT md_sub.department_name 
                FROM preparation_ingredients pi_sub
                JOIN chemical_inventory inv_sub ON pi_sub.inventory_id = inv_sub.id
                JOIN master_chemical_list mcl_sub ON inv_sub.chemical_id = mcl_sub.id
                JOIN master_departments md_sub ON mcl_sub.department_id = md_sub.id
                WHERE pi_sub.prep_id = t2.id AND pi_sub.status_delete = 0 
                LIMIT 1
            ) AS department_name,

            -- ดึงสารตั้งต้นและล็อตแบบกลุ่มรวบยอด
            GROUP_CONCAT(DISTINCT t3.lot_number SEPARATOR ', ') AS source_lot_number,
            GROUP_CONCAT(DISTINCT t4.chemical_name SEPARATOR ' + ') AS chemical_name,
            GROUP_CONCAT(DISTINCT t4.chemical_brand SEPARATOR ', ') AS chemical_brand,

            -- ข้อมูลหน่วยนับ 
            t5.unit_name_th,
            t5.unit_name_en,
            t5.unit_symbol,
            t5.unit_group,

            -- ข้อมูลผู้ทดสอบ (Tester)
            CONCAT(IFNULL(r_t.rank_name, ''), ' ', u_t.first_name, ' ', u_t.last_name) AS tester_fullname,

            -- ข้อมูลผู้ทวนสอบ (Verifier)
            CONCAT(IFNULL(r_v.rank_name, ''), ' ', u_v.first_name, ' ', u_v.last_name) AS verifier_fullname,
            DATE_FORMAT(t1.verifier_signature_date, '%d/%m/%Y %H:%i') AS verifier_date_show,

            -- ข้อมูล Metadata (Create/Update)
            CONCAT(IFNULL(r_c.rank_name, ''), ' ', u_c.first_name, ' ', u_c.last_name) AS fullname_create,
            DATE_FORMAT(t1.create_date, '%d/%m/%Y %H:%i') AS createdate_show,
            CONCAT(IFNULL(r_up.rank_name, ''), ' ', u_up.first_name, ' ', u_up.last_name) AS fullname_update,
            DATE_FORMAT(t1.update_date, '%d/%m/%Y %H:%i') AS updatedate_show

        FROM trans_latent_chemical_test t1 
        
        -- Join ไปหาข้อมูลการเตรียม
        INNER JOIN chemical_preparation t2 ON t1.prep_id = t2.id
        
        -- เปลี่ยนจุดเชื่อมโยงให้สอดคล้องกันผ่านตารางกลางย่อย
        LEFT JOIN preparation_ingredients pi ON t2.id = pi.prep_id AND pi.status_delete = 0
        LEFT JOIN chemical_inventory t3 ON pi.inventory_id = t3.id
        LEFT JOIN master_chemical_list t4 ON t3.chemical_id = t4.id
        LEFT JOIN master_chemical_units t5 ON t2.unit_id = t5.id
        
        -- Join หาข้อมูลผู้ทดสอบ
        LEFT JOIN user_profile u_t ON t1.tester_id = u_t.user_id
        LEFT JOIN user_rank r_t ON u_t.rank_id = r_t.rank_id
        
        -- Join หาข้อมูลผู้ทวนสอบ
        LEFT JOIN user_profile u_v ON t1.verifier_id = u_v.user_id
        LEFT JOIN user_rank r_v ON u_v.rank_id = r_v.rank_id
        
        -- Join ข้อมูลคนสร้าง/คนแก้
        LEFT JOIN user_profile u_c ON t1.create_by = u_c.user_id
        LEFT JOIN user_rank r_c ON u_c.rank_id = r_c.rank_id
        LEFT JOIN user_profile u_up ON t1.update_by = u_up.user_id 
        LEFT JOIN user_rank r_up ON u_up.rank_id = r_up.rank_id

        WHERE t1.status_delete = 0 AND t1.id = ? 
        GROUP BY 
            t1.id, 
            t2.prep_name, t2.prep_date, t2.expiry_date, t2.quantity_remaining,
            t5.unit_name_th, t5.unit_name_en, t5.unit_symbol, t5.unit_group,
            r_t.rank_name, u_t.first_name, u_t.last_name,
            r_v.rank_name, u_v.first_name, u_v.last_name,
            r_c.rank_name, u_c.first_name, u_c.last_name,
            r_up.rank_name, u_up.first_name, u_up.last_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        // ตรวจสอบสิทธิ์หน่วยงาน (Department Access Control)
        if ($user_role !== 'admin') {
            if ($data['department_id'] != $user_dept_id) {
                http_response_code(403);
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'ไม่มีสิทธิ์เข้าถึง: รายการนี้เป็นของหน่วยงานอื่น'
                ]);
                exit;
            }
        }

        // --- Logic การจัดการหน่วยนับ ---
        $unitDisplay = '-';
        if (!empty($data['unit_name_th'])) {
            $isPkg = (strpos($data['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($data['unit_group'], 'Packaging') !== false);
            $symbol = $isPkg ? $data['unit_name_en'] : $data['unit_symbol'];
            $unitDisplay = $data['unit_name_th'] . " (" . $symbol . ")";
        }
        $data['unit_display'] = $unitDisplay;

        // ปั้นค่า text เพื่อเอาไปยัดใส่ Select2 ให้เหมือนกับรูปแบบใน getPreparedSelect2
        $nbsp = "\u{00A0}";
        $data['text'] = "สารที่เตรียม: " . $data['prep_name'] . $nbsp . "(Lot: " . $data['source_lot_number'] . ")" . $nbsp . "[เหลือ: " . number_format($data['prep_remain_now'], 2) . " " . $unitDisplay . "]";

        // ---------------------------------------------------------------------------
        // เพิ่ม Query ดึงรายการรูปภาพที่เกี่ยวข้อง (ดึงเฉพาะ Metadata ส่วนรูปจริง ๆ ค่อยไปจัดการอีกที)
        // ---------------------------------------------------------------------------
        $sqlPhotos = "SELECT id, original_name, file_size, file_type,
                      DATE_FORMAT(create_date, '%d/%m/%Y %H:%i') AS createdate_show
                      FROM trans_latent_chemical_attachment 
                      WHERE latent_test_id = ? AND status_delete = 0 
                      ORDER BY id ASC";
        $stmtPhotos = $pdo->prepare($sqlPhotos);
        $stmtPhotos->execute([$id]);

        // ใส่ข้อมูลรูปภาพลงในตัวแปร data
        $data['photos'] = $stmtPhotos->fetchAll(PDO::FETCH_ASSOC);

        // ส่งข้อมูลกลับไปในรูปแบบ JSON
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบข้อมูลการตรวจความพร้อมสารเคมีนี้ หรืออาจถูกลบไปแล้ว'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
