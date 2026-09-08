<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';

// 1. รับ ID ของ Header จาก URL
$readiness_id = $_GET['id'] ?? null;
if (!$readiness_id) {
    header("Location: trans_readiness_check.php");
    exit;
}

$title = "รายละเอียดการตรวจจำนวนและความพร้อมของเจ้าหน้าที่";
ob_start();

// -----------------------------------------------------------------------------------------
// 2. ดึงข้อมูล Header (JOIN ครบทั้ง 3 ลายเซ็น)
// -----------------------------------------------------------------------------------------
try {
    $sqlHeader = "SELECT t1.*, 
                    -- ชื่อผู้ตรวจ (คนที่ 1)
                    CONCAT(IFNULL(r1.rank_name,''), ' ', p1.first_name, ' ', p1.last_name) AS checked_by_fullname,
                    pos1.position_name AS checked_by_position,
                    -- ชื่อหัวหน้าทีม (คนที่ 2)
                    CONCAT(IFNULL(r2.rank_name,''), ' ', p2.first_name, ' ', p2.last_name) AS leader_fullname,
                    pos2.position_name AS leader_position,

                    -- เจ้าหน้าที่ชุดที่ 3: เจ้าหน้าที่เฉพาะสังกัด (Hybrid & Historical Logic)
                    CASE 
                        -- 1. ถ้ามีชื่อที่ Snapshot ไว้ (ทั้งพิมพ์เองหรือดึงจากระบบตอนเซฟ) ให้ใช้ชื่อนั้นเลย
                        WHEN t1.unit_officer_name IS NOT NULL AND t1.unit_officer_name != '' THEN t1.unit_officer_name
                        -- 2. กรณีข้อมูลเก่าที่ยังไม่มี Snapshot ให้ Join เอาจากระบบ
                        WHEN t1.unit_officer_id IS NOT NULL THEN CONCAT(IFNULL(r3.rank_name,''), ' ', p3.first_name, ' ', p3.last_name)
                        ELSE '-' 
                    END AS unit_officer_fullname,

                    -- ตำแหน่ง (คนที่ 3)
                    CASE 
                        -- 1. ถ้ามีตำแหน่งที่ Snapshot ไว้ใน Header ให้ใช้ค่านี้ (ครอบคลุมทั้งพิมพ์เองและคนในระบบ)
                        WHEN t1.unit_officer_position IS NOT NULL AND t1.unit_officer_position != '' THEN t1.unit_officer_position
                        -- 2. กรณีข้อมูลเก่าที่ยังไม่มี Snapshot ให้ดึงจากตำแหน่งปัจจุบันในระบบ (Join)
                        WHEN t1.unit_officer_id IS NOT NULL THEN pos3.position_name
                        ELSE '-' 
                    END AS unit_officer_position

                  FROM trans_readiness_check_header t1
                  
                  -- Join ชุดที่ 1 (Checked By)
                  LEFT JOIN user_profile p1 ON t1.checked_by = p1.user_id
                  LEFT JOIN user_rank r1 ON p1.rank_id = r1.rank_id
                  LEFT JOIN user_position pos1 ON p1.position_id = pos1.position_id
                  
                  -- Join ชุดที่ 2 (Team Leader)
                  LEFT JOIN user_profile p2 ON t1.team_leader_id = p2.user_id
                  LEFT JOIN user_rank r2 ON p2.rank_id = r2.rank_id
                  LEFT JOIN user_position pos2 ON p2.position_id = pos2.position_id
                  
                  -- Join ชุดที่ 3 (Unit Officer)
                  LEFT JOIN user_profile p3 ON t1.unit_officer_id = p3.user_id
                  LEFT JOIN user_rank r3 ON p3.rank_id = r3.rank_id
                  LEFT JOIN user_position pos3 ON p3.position_id = pos3.position_id

                  WHERE t1.id = ? AND t1.delete_token = 0";
    $stmt = $pdo->prepare($sqlHeader);
    $stmt->execute([$readiness_id]);
    $header = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        echo "<div class='alert alert-danger m-3'>ไม่พบข้อมูลรายการตรวจสอบความพร้อม หรือข้อมูลถูกลบไปแล้ว</div>";
        exit;
    }

    // ตัวแปรเช็คสถานะเพื่อใช้คุมการแสดงผลทั้งหน้า
    $is_completed = ($header['status'] === 'COMPLETED');
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// -----------------------------------------------------------------------------------------
// 3. ตรวจสอบสถานะการบันทึก Checklist รายหมวดจากฐานข้อมูลจริง
// -----------------------------------------------------------------------------------------
$categories_status = [
    'VEHICLE' => false,
    'BAG'     => false,
    'TOOLS'   => false,
    'CAMERA'  => false
];

try {
    // ดึงข้อมูลจากตาราง results ว่าหมวดไหนตรวจไปแล้วบ้าง
    $sqlCheckProgress = "SELECT category, COALESCE(updated_at, created_at) as last_update
                         FROM trans_readiness_check_results 
                         WHERE header_id = ?";
    $stmtProgress = $pdo->prepare($sqlCheckProgress);
    $stmtProgress->execute([$readiness_id]); // ใช้ $readiness_id ที่รับมาจาก $_GET

    while ($row = $stmtProgress->fetch(PDO::FETCH_ASSOC)) {
        $categories_status[$row['category']] = $row['last_update'];
    }
} catch (PDOException $e) {
    // กรณี error ให้ปล่อยเป็น false ตามค่าเริ่มต้น
}

// เช็คจำนวนที่ตรวจเสร็จแล้วเพื่อใช้ในการเปิด/ปิด ส่วนลงนาม
$count_checked_categories = 0;
foreach ($categories_status as $status) {
    if ($status !== false) $count_checked_categories++;
}

// 4. เช็คสิทธิ์การเซ็นชื่อ (คนที่มีชื่อใน Header เท่านั้นที่กดเซ็นหมวดตัวเองได้)
$current_user_id = $_SESSION['user_id'];
$is_checked_by   = ($current_user_id == $header['checked_by']);
$is_leader       = ($current_user_id == $header['team_leader_id']);
if ($header['unit_officer_id'] !== null) {
    // กรณีมี ID ในระบบ: ต้องเป็นเจ้าของ ID เท่านั้นถึงจะเซ็นได้
    $is_unit_officer = ($current_user_id == $header['unit_officer_id']);
} else {
    // กรณีพิมพ์ชื่อเอง: อนุญาตให้ 1 ใน 2 คนแรก (หรือ Admin) เป็นคนกดเปิดช่องเซ็นให้
    $is_unit_officer = ($is_checked_by || $is_leader || $_SESSION['role'] === 'admin');
}

/**
 * สิทธิ์ในการจัดการข้อมูล Checklist (Save/Update ผลตรวจ)
 * - ต้องยังไม่จบงาน (DRAFT)
 * - และต้องเป็น 1 ใน 3 คนนี้ หรือเป็น Admin
 */
$can_manage_checklist = (!$is_completed && ($is_checked_by || $is_leader || $is_unit_officer));

/**
 * สิทธิ์การเซ็นชื่อ (จำกัดเฉพาะตัวบุคคล)
 * - ต้องยังไม่จบงาน (DRAFT)
 * - และ ID ต้องตรงกับช่องที่กำหนดเท่านั้น (Admin ก็เซ็นแทนไม่ได้)
 */
$can_sign_1 = (!$is_completed && $is_checked_by);   // เจ้าหน้าที่ผู้ตรวจ
$can_sign_2 = (!$is_completed && $is_leader);       // หัวหน้าทีม
$can_sign_3 = (!$is_completed && $is_unit_officer); // เจ้าหน้าที่เฉพาะสังกัด

// // --- Test = ปรับให้เช็คแค่ว่ายังไม่เสร็จพอ ไม่ต้องเช็คว่าใครเป็นผู้ประเมินจริง ๆ แล้วถึงจะเซ็นได้ ---
// $can_sign_1 = !$is_completed; // ใครก็ได้เซ็นช่องที่ 1 ได้ถ้างานยังไม่จบ
// $can_sign_2 = !$is_completed; // ใครก็ได้เซ็นช่องที่ 2 ได้
// $can_sign_3 = !$is_completed; // ใครก็ได้เซ็นช่องที่ 3 ได้

// // ส่วน can_manage_checklist ก็ปรับให้ทุกคนบันทึกผลตรวจได้ด้วย
// $can_manage_checklist = !$is_completed;
?>

<style>
    .js-eraser-btn.btn-outline-warning:not(.active):focus,
    .js-eraser-btn.btn-outline-warning:not(.active):hover,
    .js-eraser-btn.btn-outline-warning:not(.active):active {
        background-color: transparent !important;
        color: #ffc107 !important;
        box-shadow: none !important;
    }
</style>

<!-- breadcrumb สำหรับย้อนกลับหน้าหลัก -->
<div class="d-flex align-items-center mb-4">
    <a href="./trans_readiness_check.php" class="text-decoration-none text-secondary d-flex align-items-center fw-medium me-3 hover-text-primary">
        <i class="fa-solid fa-chevron-left me-2" style="margin-top: 2px;"></i> ย้อนกลับ
    </a>
    <div class="vr opacity-25 me-3" style="height: 25px;"></div>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="./trans_readiness_check.php" class="text-decoration-none text-muted hover-text-primary">
                    <i class="fa-solid fa-folder-open me-1"></i> รายการตรวจความพร้อม
                </a>
            </li>
            <li class="breadcrumb-item active text-primary" aria-current="page">รายละเอียดการตรวจความพร้อม</li>
        </ol>
    </nav>
</div>

<!-- header แสดงข้อมูลว่าเป็นบันทึกการตรวจความพร้อมของทีมไหน ประจำวันไหน + สถานะ -->
<div class="page-header d-flex flex-wrap justify-content-between align-items-end pb-3 border-bottom mb-4">
    <div class="d-flex align-items-center">
        <div class="bg-primary rounded-pill me-3 shadow-sm align-self-stretch" style="width: 6px;"></div>
        <div>
            <h3 class="fw-bold mb-0 d-flex align-items-baseline">
                <span class="text-secondary fs-4 me-2">บันทึกการตรวจความพร้อม:</span>
                <span class="text-primary"> ทีมตรวจที่ <?= htmlspecialchars($header['team_no']) ?></span>
            </h3>
            <div class="text-muted small mt-1">
                <i class="fa-regular fa-clock me-1"></i> วันที่ดำเนินการ: <?= date('d/m/Y', strtotime($header['check_date'])) ?>
                <?= date('H:i', strtotime($header['check_time'])) ?> น.
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center mt-3 mt-md-0">
        <?php if ($is_completed): ?>
            <div class="d-inline-flex align-items-center px-3 py-2 rounded-3 border border-success border-opacity-50 bg-success bg-opacity-25 text-success shadow-sm">
                <span class="small fw-bold me-lg-2 d-none d-lg-block border-end border-success border-opacity-50 pe-2" style="opacity: 0.8;">
                    สถานะ
                </span>
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle fa-lg me-2"></i>
                    <span class="fw-bold">เสร็จสมบูรณ์</span>
                </div>
            </div>
        <?php elseif ($count_checked_categories === 4): ?>
            <div class="d-inline-flex align-items-center px-3 py-2 rounded-3 border border-info border-opacity-50 bg-info bg-opacity-25 text-info shadow-sm">
                <span class="small fw-bold me-lg-2 d-none d-lg-block border-end border-info border-opacity-50 pe-2 text-info">
                    สถานะ
                </span>
                <div class="d-flex align-items-center">
                    <i class="fas fa-pen-nib fa-lg me-2"></i>
                    <span class="fw-bold">รอลงนามรับรอง</span>
                </div>
            </div>
        <?php else: ?>
            <div class="d-inline-flex align-items-center px-3 py-2 rounded-3 border border-warning border-opacity-50 bg-warning bg-opacity-25 text-dark shadow-sm">
                <span class="small fw-bold me-lg-2 d-none d-lg-block border-end border-warning border-opacity-50 pe-2 text-muted">
                    สถานะ
                </span>
                <div class="d-flex align-items-center">
                    <i class="fas fa-clock fa-lg me-2 text-warning"></i>
                    <span class="fw-bold">รอดำเนินการ (<?= $count_checked_categories ?>/4)</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ส่วนแสดงผลข้อมูลของรายการนั้น ๆ ที่ดึงมาจาก header ( modal add ในหน้าหลัก ) -->
<div class="row g-2">
    <div class="col-lg-12">
        <div class="card-incDetail">
            <div class="card-incDetail-header">
                <i class="fas fa-info-circle me-2"></i> ข้อมูลการปฏิบัติงานและความพร้อมของเจ้าหน้าที่
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 col-md-6">
                        <div class="d-flex flex-column gap-3">
                            <div class="info-group">
                                <label class="text-muted small d-block mb-1">ทีมตรวจสถานที่เกิดเหตุ</label>
                                <span class="fw-bold fs-5">ทีมตรวจที่ <?= htmlspecialchars($header['team_no']) ?></span>
                            </div>
                            <div class="info-group">
                                <label class="text-muted small d-block mb-1">วันที่และเวลาที่ตรวจ</label>
                                <span class="fw-bold"><?= date('d/m/Y', strtotime($header['check_date'])) ?></span>
                                <span class="text-secondary ms-1">(<?= date('H:i', strtotime($header['check_time'])) ?> น.)</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 border-md-start">
                        <div class="d-flex flex-column gap-3 ps-md-3">
                            <div class="info-group">
                                <label class="text-muted small d-block mb-1">สถานะความพร้อมของเจ้าหน้าที่ในทีม</label>
                                <?php if ($header['team_readiness_status'] == 'COMPLETE'): ?>
                                    <div class="badge bg-success-subtle text-success border border-success border-opacity-25 px-3 py-2 w-100 text-start">
                                        <i class="fas fa-check-circle me-2"></i> <b>เจ้าหน้าที่มาปฏิบัติหน้าที่ครบถ้วน</b>
                                    </div>
                                <?php else: ?>
                                    <div class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 px-3 py-2 w-100 text-start">
                                        <i class="fas fa-exclamation-triangle me-2"></i> <b>เจ้าหน้าที่มาปฏิบัติหน้าที่ไม่ครบ</b>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="info-group">
                                <label class="text-muted small d-block mb-1">หมายเหตุเกี่ยวกับเจ้าหน้าที่</label>
                                <div class="bg-light p-2 rounded border small text-dark" style="min-height: 40px;">
                                    <?= !empty($header['team_remark']) ? nl2br(htmlspecialchars($header['team_remark'])) : '<span class="text-muted italic">- ไม่มีหมายเหตุ -</span>' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="card-incDetail">
            <div class="card-incDetail-header">
                <i class="fas fa-user-shield me-2"></i> เจ้าหน้าที่ผู้รับผิดชอบ (ลงนามกำกับรายงาน)
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-12 col-lg-4">
                        <div class="p-3 rounded-3 bg-light border-start border-primary border-4 shadow-sm h-100">
                            <label class="small text-muted d-block mb-2 fw-bold text-uppercase">
                                เจ้าหน้าที่ผู้ตรวจ
                            </label>
                            <div class="fw-bold text-dark mb-1" style="font-size: 1.05rem;">
                                <?= $header['checked_by_fullname'] ?>
                            </div>
                            <div class="small text-secondary">
                                <?= $header['checked_by_position'] ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="p-3 rounded-3 bg-light border-start border-info border-4 shadow-sm h-100">
                            <label class="small text-muted d-block mb-2 fw-bold text-uppercase">
                                หัวหน้าทีมตรวจ
                            </label>
                            <div class="fw-bold text-dark mb-1" style="font-size: 1.05rem;">
                                <?= $header['leader_fullname'] ?>
                            </div>
                            <div class="small text-secondary">
                                <?= $header['leader_position'] ?>
                            </div>
                        </div>
                    </div>



                    <div class="col-12 col-lg-4">
                        <div class="p-3 rounded-3 bg-light border-start border-warning border-4 shadow-sm h-100">
                            <label class="small text-muted d-block mb-2 fw-bold text-uppercase">
                                <?php
                                // ตรวจสอบเงื่อนไขการแสดงผลสังกัด
                                if ($header['unit_category'] === 'นวท.(สบ....)') {
                                    // กรณี นวท. ให้จัดฟอร์แมตใหม่เป็น นวท.(สบ. {ลำดับ})
                                    $unit_info = "นวท.(สบ. " . $header['unit_detail'] . ")";
                                } else {
                                    // กรณีอื่นๆ (ศพฐ. หรือ พฐ.จว.) ให้ต่อกันตามปกติ
                                    $unit_info = $header['unit_category'] . " " . $header['unit_detail'];
                                }
                                ?>
                                เจ้าหน้าที่เฉพาะสังกัด (<?= $unit_info ?>)
                            </label>

                            <div class="fw-bold text-dark mb-1" style="font-size: 1.05rem;">
                                <?= $header['unit_officer_fullname'] ?>
                            </div>

                            <div class="small text-secondary mb-1">
                                <?= $header['unit_officer_position'] ?? '-' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="card-incDetail">
            <div class="card-incDetail-header">
                <i class="fas fa-tools me-2"></i> ข้อมูลยานพาหนะและชุดอุปกรณ์ที่นำมาตรวจสอบ
            </div>
            <div class="card-body p-4">
                <div class="row g-4">

                    <div class="col-12 col-sm-6 col-md-6 col-lg-3">
                        <div class="d-flex flex-column h-100">
                            <label class="small text-muted mb-2"><i class="fas fa-car me-1"></i> หมายเลขทะเบียน/โล่</label>
                            <span class="fw-bold fs-5"><?php echo htmlspecialchars($header['car_license']); ?></span>
                            <?php if ($header['car_mileage']): ?>
                                <div class="small text-muted pt-1 mt-auto">เลขกิโลเมตรเริ่มต้น: <span class="fw-bold text-dark"><?php echo number_format($header['car_mileage']); ?></span> กม.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-md-6 col-lg-3 border-start-lg ps-lg-4">
                        <div class="d-flex flex-column h-100">
                            <label class="small text-muted mb-1"><i class="fas fa-briefcase me-1"></i> กระเป๋าตรวจสถานที่เกิดเหตุทั่วไป</label>
                            <span class="fw-bold text-dark fs-5">ชุดที่ <?php echo htmlspecialchars($header['bag_set_no']); ?></span>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-md-6 col-lg-3 border-start-lg ps-lg-4">
                        <div class="d-flex flex-column h-100">
                            <label class="small text-muted mb-1"><i class="fas fa-camera me-1"></i> กล้องถ่ายภาพแบบดิจิทัล</label>
                            <div class="fw-bold text-dark fs-5">
                                <?php echo htmlspecialchars($header['camera_brand'] . ' ' . $header['camera_model']); ?>
                            </div>
                            <div class="small text-muted mt-auto pt-1">เลขหมายประจำเครื่อง: <span class="text-dark fw-bold"><?php echo htmlspecialchars($header['camera_sn']) ?: '-'; ?></span></div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-md-6 col-lg-3 border-start-lg ps-lg-4">
                        <div class="d-flex flex-column h-100">
                            <label class="small text-muted mb-1"><i class="fas fa-toolbox me-1"></i> เครื่องมือตรวจสถานที่เกิดเหตุ</label>
                            <span class="fw-bold text-dark fs-5">
                                <?php echo !empty($header['other_set_no']) ? 'ชุดที่ ' . htmlspecialchars($header['other_set_no']) : '<span class="text-muted fw-normal">- ไม่ระบุ -</span>'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ส่วนการตรวจความพร้อม ที่จะเป็นการเปิด modal ให้เริ่มทำฟอร์มแต่ละส่วน 
    ซึ่งจริง ๆ จะเก็บไว้ที่เดียวกัน แค่แยกเป็นเมนูย่อยเพื่อความสะดวก -->
<div class="card-incDetail">
    <div class="card-incDetail-header">
        <i class="fas fa-clipboard-check me-2"></i> รายการตรวจสอบความพร้อมอุปกรณ์และยานพาหนะ
    </div>

    <div class="card-body p-0">
        <div class="list-group list-group-flush" id="readiness_category_list">
            <?php
            // กำหนดหมวดหมู่การตรวจ 4 หมวด
            $check_categories = [
                [
                    'type'  => 'VEHICLE',
                    'label' => 'รถยนต์ตรวจสถานที่เกิดเหตุ',
                    'label_short' => 'รถยนต์ตรวจสถานที่เกิดเหตุ',
                    'desc'  => 'ระบบไฟฟ้า, ลมยาง, ของเหลว และสภาพตัวรถ',
                    'desc_short' => 'ระบบไฟ, ลมยาง, สภาพตัวรถ',
                    'icon'  => 'fa-car'
                ],
                [
                    'type'  => 'BAG',
                    'label' => 'กระเป๋าตรวจสถานที่เกิดเหตุทั่วไป',
                    'label_short' => 'กระเป๋าตรวจทั่วไป',
                    'desc'  => 'ชุดอุปกรณ์ตรวจเก็บและบรรจุวัตถุพยาน',
                    'desc_short' => 'อุปกรณ์ตรวจเก็บ/บรรจุวัตถุพยาน',
                    'icon'  => 'fa-briefcase'
                ],
                [
                    'type'  => 'CAMERA',
                    'label' => 'กล้องถ่ายภาพแบบดิจิทัล',
                    'label_short' => 'กล้องถ่ายภาพแบบดิจิทัล',
                    'desc'  => 'ตัวกล้อง, เลนส์, แฟลช และ Memory Card',
                    'desc_short' => 'กล้อง, เลนส์, แฟลช, SD Card',
                    'icon'  => 'fa-camera'
                ],
                [
                    'type'  => 'TOOLS',
                    'label' => 'เครื่องมือตรวจสถานที่เกิดเหตุ',
                    'label_short' => 'เครื่องมือตรวจสถานที่เกิดเหตุ',
                    'desc'  => 'เครื่องตรวจโลหะ, Poly Light, Laser วัดระยะ ฯลฯ',
                    'desc_short' => 'เครื่องตรวจโลหะ, Poly Light, Laser',
                    'icon'  => 'fa-toolbox'
                ]
            ];

            foreach ($check_categories as $cat) {
                // ตรวจสอบว่าหมวดนี้ตรวจไปหรือยัง (เช็คจาก $categories_status ที่ดึงไว้ตอนต้นไฟล์)
                $is_checked = isset($categories_status[$cat['type']]) && $categories_status[$cat['type']] !== false;
                $update_time = $is_checked ? date('d/m/Y H:i', strtotime($categories_status[$cat['type']])) : '';

                // สถานะ Badge
                $badge_class = $is_checked ? 'bg-success text-white border-0' : 'bg-light text-muted border';
                $badge_icon  = $is_checked ? 'fa-check-circle' : 'fa-clock';
                $badge_text  = $is_checked ? 'ตรวจสอบแล้ว' : 'ยังไม่ได้ตรวจสอบ';

                // จัดการปุ่มกดตามสิทธิ์และสถานะ
                $btn_attr = '';
                if ($is_checked) {
                    if ($is_completed || !$can_manage_checklist) {
                        $btn_class = 'btn-outline-secondary';
                        $btn_icon  = 'fa-eye';
                        $btn_text  = 'ดูผล<span class="d-lg-inline d-none">การ</span>ตรวจ';
                    } else {
                        $btn_class = 'btn-outline-primary';
                        $btn_icon  = 'fa-edit';
                        $btn_text  = 'แก้ไขผล';
                    }
                } else {
                    if ($is_completed || !$can_manage_checklist) {
                        $btn_class = 'btn-light text-muted border-0';
                        $btn_icon  = 'fa-lock';
                        $btn_text  = 'ปิดรับข้อมูล';
                        $btn_attr  = 'disabled style="cursor: not-allowed; opacity: 0.7;"';
                    } else {
                        $btn_class = 'btn-primary';
                        $btn_icon  = 'fa-clipboard-check';
                        $btn_text  = 'เริ่มตรวจ<span class="d-lg-inline d-none">สอบ</span>';
                    }
                }
            ?>
                <div class="list-group-item p-3 px-4 category-item border-bottom" id="cat_<?php echo $cat['type']; ?>">
                    <div class="row align-items-center">
                        <div class="col-md-5 d-flex align-items-center">
                            <div class="flex-shrink-0 bg-light text-primary p-2 rounded-3 me-3 border shadow-sm"
                                style="width: 52px; height: 52px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas <?php echo $cat['icon']; ?> fa-2x"></i>
                            </div>
                            <div class="text-truncate">
                                <h6 class="mb-0 mt-1 fw-bold text-dark">
                                    <span class="d-none d-lg-inline"><?php echo $cat['label']; ?></span>
                                    <span class="d-inline d-lg-none"><?php echo $cat['label_short']; ?></span>
                                </h6>
                                <small class="text-muted text-nowrap text-truncate d-block" style="max-width: 300px;">
                                    <span class="d-none d-lg-inline"><?php echo $cat['desc']; ?></span>
                                    <span class="d-inline d-lg-none"><?php echo $cat['desc_short']; ?></span>
                                </small>
                            </div>
                        </div>

                        <div class="col-6 col-md-4 text-md-center mt-3 mt-md-0">
                            <div class="status-container d-flex flex-column align-items-center">
                                <span class="badge <?php echo $badge_class; ?> px-4 py-2 rounded-pill shadow-sm" style="font-size: 0.85rem; min-width: 150px;">
                                    <i class="fas <?php echo $badge_icon; ?> me-1"></i> <?php echo $badge_text; ?>
                                </span>
                                <?php if ($is_checked): ?>
                                    <small class="text-muted mt-2" style="font-size: 0.75rem;">
                                        เมื่อ: <?php echo $update_time; ?> น.
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-6 col-md-3 text-end mt-3 mt-md-0">
                            <button class="btn <?= $btn_class ?> px-4 py-2 fw-bold js-start-check-btn shadow-sm w-100 w-md-auto"
                                <?= $btn_attr ?>
                                data-category="<?= $cat['type'] ?>"
                                data-label="<?= $cat['label'] ?>"
                                data-header-id="<?= $readiness_id ?>">
                                <i class="fas <?= $btn_icon ?> me-2"></i><?= $btn_text ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<?php
// ตรวจสอบว่าตรวจครบทั้ง 4 หมวดหรือยังก่อนโชว์ส่วนลงนาม
$all_categories_checked = ($count_checked_categories === 4);

// แสดงส่วนลงนามถ้าตรวจครบแล้ว หรือถ้าสถานะเป็น Completed แล้ว
$show_signature_section = ($all_categories_checked || $is_completed);
?>

<!-- ส่วนของการลงลายเซ็น โดยจะแสดงเมื่อผ่านเงื่อนไขว่าประเมินครบทั้ง 4 ส่วนแล้ว หรือ ลงเสร็จแล้วย้อนกลับมาดู -->
<div id="signature_section" style="display: <?= $show_signature_section ? 'block' : 'none'; ?>;">
    <div class="card-incDetail">
        <div class="card-incDetail-header">
            <i class="fas fa-file-signature me-2"></i> สรุปผลการตรวจสอบและลงนามรับรอง
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-signature table-bordered align-middle mb-0">
                    <thead class="table-light text-secondary text-center">
                        <tr>
                            <th class="py-3 text-uppercase" style="min-width: 160px;">ผู้ลงนาม</th>
                            <th class="py-3 text-uppercase" style="min-width: 120px;">ตำแหน่ง</th>
                            <th class="py-3 text-uppercase" style="min-width: 300px;">ลายเซ็น</th>
                            <th class="py-3 text-uppercase" style="min-width: 160px;">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // นิยามชุดข้อมูลผู้ลงนาม 3 ท่าน
                        $signers = [
                            [
                                'label'     => 'เจ้าหน้าที่ผู้ตรวจ',
                                'fullname'  => $header['checked_by_fullname'],
                                'position'  => $header['checked_by_position'],
                                'signature' => $header['checked_signature'],
                                'signed_at' => $header['checked_signed_at'],
                                'can_sign'  => $can_sign_1,
                                'canvas_id' => 'sig-canvas-1',
                                'clear_id'  => 'clear-1',
                                'save_btn'  => 'btn-save-sig-1',
                                'role_key'  => 'CHECKED'
                            ],
                            [
                                'label'     => 'หัวหน้าทีมตรวจ',
                                'fullname'  => $header['leader_fullname'],
                                'position'  => $header['leader_position'],
                                'signature' => $header['leader_signature'],
                                'signed_at' => $header['leader_signed_at'],
                                'can_sign'  => $can_sign_2,
                                'canvas_id' => 'sig-canvas-2',
                                'clear_id'  => 'clear-2',
                                'save_btn'  => 'btn-save-sig-2',
                                'role_key'  => 'LEADER'
                            ],
                            [
                                'label'     => 'เจ้าหน้าที่เฉพาะสังกัด',
                                'fullname'  => $header['unit_officer_fullname'],
                                'position'  => ($header['unit_category'] === 'นวท.(สบ....)')
                                    ? 'นวท.(สบ.' . $header['unit_detail'] . ')'  // ถ้าเป็น นวท. ให้เอาเลขใส่ในวงเล็บ
                                    : $header['unit_category'] . ' ' . $header['unit_detail'], // ถ้าเป็น ศพฐ./พฐ.จว. ให้เว้นวรรคปกติ
                                'signature' => $header['unit_officer_signature'],
                                'signed_at' => $header['unit_officer_signed_at'],
                                'can_sign'  => $can_sign_3,
                                'canvas_id' => 'sig-canvas-3',
                                'clear_id'  => 'clear-3',
                                'save_btn'  => 'btn-save-sig-3',
                                'role_key'  => 'UNIT'
                            ]
                        ];

                        foreach ($signers as $s):
                            $has_signed = !empty($s['signature']);
                        ?>
                            <tr class="<?= $s['can_sign'] && !$has_signed ? 'table-primary-light' : '' ?>">
                                <td class="ps-4">
                                    <div class="small text-primary fw-bold text-uppercase"><?= $s['label'] ?></div>
                                    <div class="fw-bold text-dark"><?= $s['fullname'] ?></div>
                                </td>
                                <td class="text-center text-muted">
                                    <?= $s['position'] ?>
                                </td>
                                <td class="py-4 text-center">
                                    <div class="signature-wrapper mx-auto" style="max-width: 500px;">
                                        <?php if ($has_signed): ?>
                                            <div class="p-2 border rounded bg-white shadow-sm d-flex align-items-center justify-content-center" style="height: 140px;">
                                                <img src="<?= $s['signature'] ?>" alt="Signature" class="img-fluid" style="max-height: 120px;">
                                            </div>
                                        <?php elseif ($s['can_sign'] && !$is_completed): ?>
                                            <div class="border rounded bg-white shadow-sm position-relative overflow-hidden" style="height: 150px;">
                                                <canvas id="<?= $s['canvas_id'] ?>" class="sig-canvas" style="width: 100%; height: 100%; cursor: crosshair; touch-action: none;"></canvas>
                                                <div class="position-absolute top-50 start-50 translate-middle text-muted opacity-25 pointer-events-none text-center sig-placeholder">
                                                    <i class="fas fa-pen-nib fa-2x mb-2"></i><br><span>เซ็นชื่อเพื่อรับรองผล</span>
                                                </div>
                                            </div>
                                            <div class="mt-2 d-flex justify-content-center gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-warning px-3 shadow-sm js-eraser-btn"
                                                    data-canvas="<?= $s['canvas_id'] ?>">
                                                    <i class="fas fa-eraser me-1"></i> ยางลบ
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary px-3 shadow-sm" id="<?= $s['clear_id'] ?>">
                                                    <i class="fas fa-trash-alt me-1"></i> ล้างลายเซ็น
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-light border rounded p-4 text-center text-muted" style="height: 140px; display: flex; flex-direction: column; justify-content: center;">
                                                <i class="fas fa-user-lock fa-2x mb-2 text-muted opacity-50"></i>
                                                <div class="text-muted small">รอการลงนามรับรองโดย</div>
                                                <div class="fw-bold text-primary"><?= $s['fullname'] ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($has_signed): ?>
                                        <div class="text-success fw-bold">
                                            Completed<br>
                                            <small class="text-success fw-normal">
                                                <?= date('d/m/Y H:i', strtotime($s['signed_at'])) ?> น.
                                            </small>
                                        </div>
                                    <?php elseif ($s['can_sign'] && !$is_completed): ?>
                                        <button type="button" class="btn btn-success w-100 py-2 shadow-sm fw-bold js-save-signature"
                                            data-role="<?= $s['role_key'] ?>"
                                            data-canvas="<?= $s['canvas_id'] ?>">
                                            <i class="fas fa-save me-2"></i>ยืนยัน<span class="d-none d-lg-inline">ลงนาม</span>
                                        </button>
                                    <?php elseif (!$is_completed): ?>
                                        <button class="btn btn-secondary w-100 py-2 shadow-sm disabled" style="opacity: 0.6; cursor: not-allowed;"
                                            title="เฉพาะ<?= $s['label'] ?>ที่ระบุเท่านั้นที่มีสิทธิ์ดำเนินการ">
                                            <i class="fas fa-lock me-2"></i> ยืนยัน<span class="d-none d-lg-inline">ลงนาม</span>
                                        </button>
                                        <div class="mt-2 text-muted italic" style="font-size: 0.7rem;">
                                            * เฉพาะ<?= $s['label'] ?>เท่านั้น
                                        </div>

                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border px-3 py-2">ไม่ได้ลงนาม</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!$is_completed): ?>
                <div class="p-3 bg-light-info border-top">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        <b>หมายเหตุ:</b> หลังจากที่ผู้เกี่ยวข้องทั้ง 3 ท่านลงนามครบถ้วนแล้ว สถานะของรายงานจะถูกเปลี่ยน และข้อมูลทุกอย่างจะไม่สามารถแก้ไขได้อีก
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- เพิ่มในส่วนของ modal การตรวจความพร้อม -->
<div class="modal fade" id="readinessModal" tabindex="-1" aria-labelledby="readinessModalLabel" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="readinessModalLabel">
                    <i class="fas fa-clipboard-list fa-lg me-2"></i>
                    <span id="dynamic_modal_title">รายการตรวจสอบ</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">

                <div id="modal_category_info">
                </div>

                <form id="readinessCheckForm">
                    <input type="hidden" name="header_id" value="<?= $readiness_id ?>">
                    <input type="hidden" id="modal_header_id" name="header_id" value="<?= $readiness_id ?>">
                    <input type="hidden" name="category" id="modal_category_type">

                    <div class="table-responsive" id="checklist_container">
                    </div>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-end bg-light border-top shadow-sm">
                <?php if (!$is_completed): ?>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" form="readinessCheckForm" id="btn_save_check">
                        <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                    </button>
                <?php endif; ?>

                <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal" id="btn_modal_close">
                    <i class="fas fa-times me-2"></i> ปิดหน้าต่าง
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
ob_start();
?>
<script>
    const currentReportId = "<?= $readiness_id ?>";
    const isCompleted = <?php echo json_encode($is_completed); ?>;

    $(document).ready(function() {

        $(document).on('click', '.js-click-score', function() {
            const radio = $(this).find('input[type="radio"]');
            if (!radio.prop('disabled')) {
                radio.prop('checked', true).trigger('change');
            }
        });

        // 1. กดปุ่มเริ่มตรวจสอบ
        $(document).on('click', '.js-start-check-btn', function() {
            const d = $(this).data();
            const category = d.category; // VEHICLE, BAG, TOOLS, CAMERA
            const label = d.label;
            const headerId = d.headerId;

            // เปลี่ยนหัวข้อ Modal และใส่ข้อมูลอ้างอิง
            $('#dynamic_modal_title').text(label);
            $('#modal_category_type').val(category);
            $('#modal_header_id').val(headerId);

            // ล้างข้อมูลเก่าและแสดงสถานะกำลังโหลด
            $('#modal_category_info').html('<div class="small text-muted"><i class="fas fa-circle-notch fa-spin me-2"></i>กำลังดึงข้อมูลรายละเอียด...</div>');
            $('#checklist_container').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><br>กำลังโหลดรายการ...</div>');

            // เรียก API ดึงข้อมูลรายการ Checklist
            $.ajax({
                url: './api/Transaction_readiness_check/detail/get_checklist_form.php',
                type: 'POST',
                data: {
                    category: category,
                    header_id: currentReportId
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        const ui = res.ui;
                        const mode = res.mode;

                        // 1. พ่นข้อมูลรายละเอียดอุปกรณ์ (เช่น ทะเบียนรถ, เลขชุดกระเป๋า)
                        $('#modal_category_info').html(res.info_html);

                        // 2. พ่น HTML ตารางรายการตรวจสอบ
                        $('#checklist_container').html(res.table_html);

                        // 2. ปรับแต่ง UI ของ Modal (สี Header)
                        const modalHeader = $('#readinessModal .modal-header');
                        const modalTitle = $('#readinessModal .modal-title');
                        const submitBtn = $('#btn_save_check');

                        // ล้าง Class เก่าทิ้งให้หมดก่อน แล้วกำหนด class ให้ตามที่ API แนบมา
                        modalHeader.removeClass('bg-primary edit-mode completed-mode text-white');

                        modalHeader.addClass(ui.header_class);
                        if (mode === 'NEW') modalHeader.addClass('text-white');

                        // อัปเดตหัวข้อและไอคอน
                        modalTitle.html(`<i class="fas ${ui.icon} fa-lg me-2"></i> ${ui.title}`);

                        if (isCompleted) {
                            submitBtn.hide();
                            $('#btn_modal_close').html('<i class="fas fa-times me-2"></i> ปิดหน้าต่าง');
                            $('#checklist_container').find('input, textarea').prop('disabled', true);
                        } else {
                            submitBtn.show();
                            submitBtn.html(ui.btn_text)
                                .removeClass('btn-primary btn-warning text-dark text-white')
                                .addClass(ui.btn_class);
                            $('#btn_modal_close').html('<i class="fas fa-times me-2"></i> ยกเลิก');
                        }
                        $('#readinessModal').modal('show');
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'ไม่สามารถเชื่อมต่อระบบได้', 'error');
                }
            });
        });

        // 2. Logic การบันทึก (Submit Form)
        $('#readinessCheckForm').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            // Validation: เช็คว่าติ๊กครบทุกข้อหรือยัง
            const totalRows = $('.check-item-row').length;
            const answeredRows = $('input[type="radio"]:checked').length;

            if (answeredRows < totalRows) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบถ้วน',
                    text: `กรุณาตรวจสอบให้ครบทุกรายการ (เหลืออีก ${totalRows - answeredRows} ข้อ)`,
                    confirmButtonColor: '#f39c12'
                });
                return;
            }

            $.ajax({
                url: './api/Transaction_readiness_check/detail/save.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                beforeSend: function() {
                    $('#btn_save_check').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> กำลังบันทึก...');
                },
                success: function(res) {
                    if (res.status === 'success') {
                        $('#readinessModal').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'บันทึกสำเร็จ',
                            text: 'ผลการตรวจสอบถูกบันทึกเรียบร้อยแล้ว',
                            timer: 1500,
                            showConfirmButton: false
                        });

                        // อัปเดต Badge ที่หน้า List (เหมือนหน้าประเมิน) 
                        updateCategoryBadge(res.category, res.update_time);

                        // เช็คว่าตรวจครบ 4 หมวดหรือยัง เพื่อเปิดส่วนเซ็นชื่อ 
                        checkAllCategoriesDone();
                    }
                },
                complete: function() {
                    $('#btn_save_check').prop('disabled', false).html('<i class="fas fa-save me-2"></i> บันทึกผลการตรวจ');
                }
            });
        });

        // ผูกเหตุการณ์ให้ปุ่มยางลบ
        $(document).on('click', '.js-eraser-btn', function() {
            const canvasId = $(this).data('canvas');
            const currentState = eraserState[canvasId] || false;
            setEraserMode(canvasId, !currentState);
        });

        // ปุ่มล้างลายเซ็น (แยกตามรายคน)
        $(document).on('click', '[id^="clear-"]', function() {
            const idNum = this.id.split('-')[1]; // ดึงเลข 1, 2, หรือ 3
            const canvasId = 'sig-canvas-' + idNum;
            if (signaturePads[canvasId]) {
                signaturePads[canvasId].clear();
                setEraserMode(canvasId, false);
                $(`#${canvasId}`).parent().find('.sig-placeholder').fadeIn();
            }
        });
    });

    /**
     * ฟังก์ชันอัปเดต Badge และปุ่มที่หน้ารายการหมวดหมู่ (แบบไม่ต้อง Refresh)
     */
    function updateCategoryBadge(category, timestamp) {
        const container = $(`#cat_${category}`);
        if (container.length) {
            // 1. เปลี่ยน Badge เป็นสีเขียว "ตรวจสอบแล้ว"
            container.find('.badge')
                .removeClass('bg-light text-muted border')
                .addClass('bg-success text-white border-0')
                .html('<i class="fas fa-check-circle me-1"></i> ตรวจสอบแล้ว');

            // 2. แสดงเวลาอัปเดต
            let statusDiv = container.find('.status-container');
            statusDiv.find('small').remove(); // ลบเวลาเก่าออกก่อน
            statusDiv.append(`<small class="text-muted mt-2" style="font-size: 0.75rem;">เมื่อ: ${timestamp} น.</small>`);

            // 3. เปลี่ยนชื่อปุ่มและไอคอนเป็น "แก้ไขผลตรวจ"
            const btn = container.find('.js-start-check-btn');
            btn.removeClass('btn-primary').addClass('btn-outline-primary')
                .html('<i class="fas fa-edit me-2"></i>แก้ไขผล');
        }
    }

    /**
     * ฟังก์ชันเช็คว่าตรวจครบ 4 หมวดหรือยัง เพื่อเปิดส่วนลงนาม
     */
    function checkAllCategoriesDone() {
        const totalCategories = 4;
        // นับจำนวน Badge ที่เป็นสีเขียว (bg-success)
        const completedCount = $('#readiness_category_list .badge.bg-success').length;

        if (completedCount === totalCategories) {
            // ถ้าครบแล้ว ให้แสดงส่วน Signature (ถ้าเดิมมันซ่อนอยู่)
            $('#signature_section').fadeIn();

            // ถ้ามี Signature Pad อยู่ ให้เรียก Init
            if (typeof initAllSignaturePads === "function") {
                initAllSignaturePads();
            }
        }
    }

    // เก็บออบเจ็กต์ SignaturePad แยกตาม ID ของ Canvas
    let signaturePads = {};

    // ฟังก์ชันเริ่มต้นใช้งาน Signature Pads ทั้งหมดที่มีในหน้าจอ
    function initAllSignaturePads() {
        const canvasIds = ['sig-canvas-1', 'sig-canvas-2', 'sig-canvas-3'];

        canvasIds.forEach(id => {
            const canvas = document.getElementById(id);
            if (!canvas || signaturePads[id]) return; // ถ้าไม่มี Canvas หรือ Init ไปแล้วให้ข้าม

            // 1. ตรวจสอบว่า Container แสดงผลอยู่จริง (ป้องกัน Error เรื่องขนาด 0px)
            if (canvas.offsetWidth === 0) return;

            const signatureBox = canvas.parentElement;

            // 2. ฟังก์ชันปรับขนาด Canvas ให้รองรับ Retina Display / Mobile
            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = signatureBox.offsetWidth * ratio;
                canvas.height = signatureBox.offsetHeight * ratio;

                // รีเซ็ต Transform และตั้งค่า Scale ใหม่
                const ctx = canvas.getContext('2d');
                ctx.setTransform(1, 0, 0, 1, 0, 0); // ล้างค่า Scale/Transform เดิมที่ค้างอยู่
                ctx.scale(ratio, ratio);

                if (signaturePads[id]) signaturePads[id].clear(); // ล้างลายเซ็นถ้ามีการหมุนจอ
            }

            window.addEventListener("resize", resizeCanvas);
            resizeCanvas();

            // 3. สร้าง Instance ของ SignaturePad และเก็บไว้ในตัวแปร Object
            signaturePads[id] = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)',
                penColor: 'rgb(0, 0, 0)',
                minWidth: 1,
                maxWidth: 2.5
            });

            // 4. ซ่อน Placeholder เมื่อเริ่มเซ็น
            signaturePads[id].addEventListener("beginStroke", () => {
                $(signatureBox).find('.sig-placeholder').fadeOut();
            });
        });
    }

    let eraserState = {}; // เก็บสถานะยางลบแยกตาม canvas_id

    /**
     * ฟังก์ชันตั้งค่าโหมดยางลบ
     */
    function setEraserMode(canvasId, on) {
        const pad = signaturePads[canvasId];
        if (!pad) return;

        eraserState[canvasId] = on;
        // หาปุ่มยางลบที่ตรงกับ canvas นี้
        const $btn = $(`.js-eraser-btn[data-canvas="${canvasId}"]`);

        if (on) {
            // เปิดโหมดยางลบ: สีขาว + เส้นใหญ่
            pad.penColor = '#ffffff';
            pad.minWidth = 10;
            pad.maxWidth = 15;
            $btn.removeClass('btn-outline-warning').addClass('btn-warning active');
        } else {
            // ปิดโหมดยางลบ: กลับเป็นสีดำปกติ
            pad.penColor = 'rgb(0, 0, 0)';
            pad.minWidth = 1;
            pad.maxWidth = 2.5;
            $btn.removeClass('btn-warning active').addClass('btn-outline-warning');
        }
    }

    // =========================================================
    // ส่วนบันทึกการลงนามรายบุคคล
    // =========================================================
    $(document).on('click', '.js-save-signature', function() {
        const btn = $(this);
        const roleKey = btn.data('role'); // CHECKED, LEADER, UNIT
        const canvasId = btn.data('canvas'); // sig-canvas-1, 2, 3
        const pad = signaturePads[canvasId];

        // 1. ตรวจสอบความพร้อม
        if (!pad || pad.isEmpty()) {
            Swal.fire({
                icon: 'warning',
                title: 'กรุณาลงลายเซ็น',
                text: 'กรุณาเซ็นชื่อในช่องให้เรียบร้อยก่อนยืนยัน'
            });
            return;
        }

        const signatureData = pad.toDataURL('image/png');

        // 2. ยืนยันก่อนบันทึก
        Swal.fire({
            title: 'ยืนยันการบันทึก?',
            text: "เมื่อบันทึกแล้วจะไม่สามารถแก้ไขผลการตรวจสอบได้อีก",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754', // สีเขียว
            cancelButtonColor: '#d33',
            confirmButtonText: 'ยืนยัน, บันทึกเลย!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/Transaction_readiness_check/detail/save_signature.php',
                    type: 'POST',
                    data: {
                        header_id: currentReportId,
                        role_key: roleKey,
                        signature: signatureData
                    },
                    dataType: 'json',
                    beforeSend: function() {
                        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                    },
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                    icon: 'success',
                                    title: 'ลงนามสำเร็จ',
                                    timer: 1500,
                                    showConfirmButton: false
                                })
                                .then(() => {
                                    location.reload();
                                }); // รีโหลดเพื่อล็อคฟอร์มและโชว์รูปภาพลายเซ็น
                        } else {
                            Swal.fire('Error', res.message, 'error');
                            btn.prop('disabled', false).html('<i class="fas fa-file-signature me-1"></i> ยืนยันลงนาม');
                        }
                    }
                });
            }
        });
    });

    // ตรวจสอบตอนโหลดหน้าเว็บ: ถ้าส่วนเซ็นชื่อโชว์อยู่ ให้ Init ทันที
    if ($('#signature_section').is(':visible')) {
        setTimeout(initAllSignaturePads, 300);
    }
</script>
<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>