<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';

// รับ ID จาก URL
$assessment_id = $_GET['id'] ?? null;
if (!$assessment_id) {
    header("Location: trans_staff_assessment.php");
    exit;
}

$title = "รายละเอียดการประเมินความสามารถผู้ปฏิบัติหน้าที่";
ob_start();

// 1. ดึงข้อมูล Header
try {
    $sqlHeader = "SELECT t1.*, 
                    CONCAT(IFNULL(t3.rank_name,''), ' ', t2.first_name, ' ', t2.last_name) AS evaluator_fullname,
                    t4.position_name AS evaluator_position
                  FROM staff_assessment_header t1
                  LEFT JOIN user_profile t2 ON t1.evaluator_id = t2.user_id
                  LEFT JOIN user_rank t3 ON t2.rank_id = t3.rank_id
                  LEFT JOIN user_position t4 ON t2.position_id = t4.position_id
                  WHERE t1.id = ? AND t1.delete_token = 0";
    $stmt = $pdo->prepare($sqlHeader);
    $stmt->execute([$assessment_id]);
    $header = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        echo "<div class='alert alert-danger'>ไม่พบข้อมูลการประเมิน</div>";
        exit;
    }

    // ตัวแปรเช็คสถานะเพื่อใช้คุมการแสดงผลทั้งหน้า
    $is_completed = ($header['status'] === 'COMPLETED');

    // กำหนด Class สีของ Header ตามสถานะ
    // ถ้าเสร็จแล้ว: ใช้สีเขียว (bg-success-gradient) เพื่อบอกว่า SUCCESS
    // ถ้ายังไม่เสร็จ: ใช้สีปกติ (card-header-custom เดิมที่เป็นสีฟ้า)
    // $header_class = $is_completed ? 'card-header-custom bg-success-gradient' : 'card-header-custom';
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// -----------------------------------------------------------------------------------------
// ดึงข้อมูลผลการประเมินที่มีอยู่แล้ว เพื่อเอามา Map สถานะ
// -----------------------------------------------------------------------------------------
$assessment_map = [];
$sqlResults = "SELECT role_type, updated_at, created_at 
               FROM staff_assessment_results 
               WHERE header_id = ?";
$stmtResults = $pdo->prepare($sqlResults);
$stmtResults->execute([$assessment_id]);

while ($row = $stmtResults->fetch(PDO::FETCH_ASSOC)) {
    // ใช้ updated_at ถ้ามี ถ้าไม่มีใช้ created_at
    $timestamp = $row['updated_at'] ? $row['updated_at'] : $row['created_at'];
    $assessment_map[$row['role_type']] = $timestamp;
}

// --- นับจำนวนเพื่อแสดงสถานะความคืบหน้า ---

// 1. นับจำนวนคนที่มีชื่อระบุไว้ในทีม (จาก 5 ตำแหน่ง)
$role_fields = ['leader_id', 'photographer_id', 'map_maker_id', 'searcher_id', 'collector_id'];
$count_active_roles = 0; // จำนวนคนที่มีรายชื่อ
foreach ($role_fields as $field) {
    if (!empty($header[$field])) {
        $count_active_roles++;
    }
}

// 2. จำนวนคนที่ถูกประเมินแล้ว (นับจากข้อมูลใน assessment_map)
$count_assessed_roles = count($assessment_map);

$can_evaluate = ($_SESSION['user_id'] == $header['evaluator_id']);

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

<div class="d-flex align-items-center mb-4">
    <a href="./trans_staff_assessment.php" class="text-decoration-none text-secondary d-flex align-items-center fw-medium me-3 hover-text-primary">
        <i class="fa-solid fa-chevron-left me-2" style="margin-top: 2px;"></i> ย้อนกลับ
    </a>
    <div class="vr opacity-25 me-3" style="height: 25px;"></div>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="./trans_staff_assessment.php" class="text-decoration-none text-muted hover-text-primary">
                    <i class="fa-solid fa-folder-open me-1"></i> รายการประเมินความสามารถ
                </a>
            </li>
            <li class="breadcrumb-item active text-primary" aria-current="page">รายละเอียดการประเมิน</li>
        </ol>
    </nav>
</div>

<div class="page-header d-flex flex-wrap justify-content-between align-items-end pb-3 border-bottom mb-4">
    <div class="d-flex align-items-center">
        <div class="bg-primary rounded-pill me-3 shadow-sm align-self-stretch" style="width: 6px;"></div>
        <div>
            <h3 class="fw-bold mb-0 d-flex align-items-baseline">
                <span class="text-secondary fs-4 me-2">การประเมินความสามารถ:</span>
                <span class="text-primary"> <span class="d-none d-lg-inline">รายงานเลขที่</span> <?= htmlspecialchars($header['report_no']) ?></span>
            </h3>
            <div class="text-muted small mt-1">
                <i class="fa-regular fa-clock me-1"></i> วันที่ดำเนินการ: <?= date('d/m/Y', strtotime($header['operation_date'])) ?>
                <span class="mx-1">|</span>
                <i class="fa-solid fa-tag ms-2 me-1"></i> ขอบข่าย:
                <span>
                    <?php
                    $scopeText = [
                        'PROPERTY' => 'คดีทรัพย์',
                        'LIFE' => 'คดีชีวิต',
                        'EXPLOSIVE' => 'คดีระเบิด'
                    ];
                    echo $scopeText[$header['case_scope']] ?? $header['case_scope'];
                    ?>
                </span>
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
        <?php elseif ($count_assessed_roles >= $count_active_roles && $count_active_roles > 0): ?>
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
                    <span class="fw-bold">รอประเมิน (<?= $count_assessed_roles ?>/<?= $count_active_roles ?>)</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>


<div class="card-incDetail">
    <div class="card-incDetail-header">
        <i class="fas fa-info-circle me-2"></i> ข้อมูลทั่วไปของรายงาน
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-lg-6 col-xl-2">
                <label class="small text-muted d-block">เลขรายงาน</label>
                <span class="fw-bold"><?php echo $header['report_no']; ?></span>
            </div>
            <div class="col-12 col-sm-6 col-lg-6 col-xl-2">
                <label class="small text-muted d-block">วันที่ดำเนินการ</label>
                <span class="fw-bold"><?php echo date('d/m/Y', strtotime($header['operation_date'])); ?></span>
            </div>
            <div class="col-12 col-sm-6 col-lg-6 col-xl-2">
                <label class="small text-muted d-block">ขอบข่ายที่ตรวจ</label>
                <?php
                $scopeMap = [
                    'PROPERTY' => '<span class="fw-bold">คดีทรัพย์</span>',
                    'LIFE' => '<span class="fw-bold">คดีชีวิต</span>',
                    'EXPLOSIVE' => '<span class="fw-bold">คดีระเบิด</span>'
                ];
                echo $scopeMap[$header['case_scope']] ?? $header['case_scope'];
                ?>
            </div>
            <div class="col-12 col-sm-6 col-lg-6 col-xl-3 border-xl-start ps-xl-4">
                <label class="small text-muted d-block">ที่ตั้ง / สถานที่ดำเนินการ</label>
                <span class="fw-bold"><?php echo $header['location']; ?></span>
            </div>
            <div class="col-12 col-sm-12 col-lg-12 col-xl-3 border-xl-start ps-xl-4">
                <label class="small text-muted d-block">ผู้ประเมิน</label>
                <span class="fw-bold"><?php echo $header['evaluator_fullname']; ?></span>
            </div>
        </div>
    </div>
</div>

<div class="card-incDetail">
    <div class="card-incDetail-header">
        <i class="fas fa-users me-2"></i> รายชื่อผู้รับการประเมิน
    </div>

    <div class="card-body p-0">
        <div class="list-group list-group-flush" id="staff_assessment_list">
            <?php
            $roles = [
                ['key' => 'leader_id', 'label' => 'หัวหน้าทีม', 'type' => 'LEADER'],
                ['key' => 'photographer_id', 'label' => 'ช่างภาพ', 'type' => 'PHOTOGRAPHER'],
                ['key' => 'map_maker_id', 'label' => 'ผู้ทำแผนที่', 'type' => 'MAP_MAKER'],
                ['key' => 'searcher_id', 'label' => 'ผู้ค้นหาวัตถุพยาน', 'type' => 'SEARCHER'],
                ['key' => 'collector_id', 'label' => 'ผู้ตรวจเก็บวัตถุพยาน', 'type' => 'COLLECTOR']
            ];

            foreach ($roles as $index => $role) {
                $user_id = $header[$role['key']];
                if (!$user_id) continue;

                // นับจำนวนคนที่มีตัวตนจริง
                $count_active_roles++;

                // ตรวจสอบว่าคนนี้ประเมินไปหรือยัง?
                $is_assessed = isset($assessment_map[$role['type']]);
                if ($is_assessed) {
                    $count_assessed_roles++;
                }

                // เตรียมข้อมูล Display
                $display_time = $is_assessed ? date('d/m/Y H:i', strtotime($assessment_map[$role['type']])) : '';

                // Badge Class & Text
                $badge_class = $is_assessed ? 'bg-success text-white border-0' : 'bg-white text-muted border';
                $badge_icon = $is_assessed ? 'fa-check-circle' : 'fa-clock';
                $badge_text = $is_assessed ? 'ประเมินแล้ว' : 'รอการประเมิน';

                $btn_attr = '';

                // Button Class & Text
                if ($is_assessed) {
                    // --- กรณี: ประเมินเรียบร้อยแล้ว ---
                    if ($is_completed || !$can_evaluate) {
                        // ถ้างานจบแล้ว หรือ ไม่มีสิทธิ์ประเมิน -> ให้ "ดูผล" อย่างเดียว
                        $btn_class = 'btn-outline-secondary';
                        $btn_icon = 'fa-eye';
                        $btn_text = 'ดูผล<span class="d-none d-lg-inline">การ</span>ประเมิน';
                    } else {
                        // ถ้างานยังไม่จบ และ เป็นคนประเมิน -> ให้ "แก้ไขผล"
                        $btn_class = 'btn-outline-primary';
                        $btn_icon = 'fa-edit';
                        $btn_text = 'แก้ไขผล';
                    }
                } else {
                    // --- กรณี: ยังไม่ได้ประเมิน ---
                    if ($is_completed || !$can_evaluate) {
                        // งานจบไปแล้ว หรือ ไม่มีสิทธิ์ -> ล็อคปุ่ม "รอประเมิน"
                        $btn_class = 'btn-light text-muted border-0';
                        $btn_icon = 'fa-lock';
                        $btn_text = 'รอ<span class="d-none d-lg-inline">การ</span>ประเมิน';
                        $btn_attr = 'disabled style="cursor: not-allowed; opacity: 0.7;"';
                    } else {
                        // งานยังไม่จบ และ มีสิทธิ์ -> "เริ่มประเมิน"
                        $btn_class = 'btn-primary';
                        $btn_icon = 'fa-clipboard-check';
                        $btn_text = 'เริ่มประเมิน';
                    }
                }

                $icon_class = 'bg-light text-primary';

                $sqlUser = "SELECT CONCAT(IFNULL(t3.rank_name,''), ' ', t2.first_name, ' ', t2.last_name) AS fullname
                            FROM user_profile t2 
                            LEFT JOIN user_rank t3 ON t2.rank_id = t3.rank_id
                            WHERE t2.user_id = ?";
                $stU = $pdo->prepare($sqlUser);
                $stU->execute([$user_id]);
                $u = $stU->fetch();
            ?>
                <div class="list-group-item p-2 px-4 staff-item border-bottom" id="item_<?php echo $role['type']; ?>">
                    <div class="row align-items-center" style="min-height: 65px;">
                        <div class="col-md-5 d-flex align-items-center">
                            <div class="flex-shrink-0 <?php echo $icon_class; ?> p-2 rounded-circle me-3 border shadow-sm"
                                style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user-circle fa-2x"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.95rem;"><?php echo $u['fullname']; ?></h6>
                                <small class="text-secondary fw-bold text-opacity-75"><?php echo $role['label']; ?></small>
                            </div>
                        </div>

                        <div class="col-6 col-md-4 col-lg-4 text-md-center">
                            <div class="status-container d-flex flex-column align-items-center" id="status_<?php echo $role['type']; ?>">
                                <span class="badge <?php echo $badge_class; ?> px-4 py-2 rounded-pill shadow-sm" style="font-size: 0.85rem; min-width: 140px;">
                                    <i class="fas <?php echo $badge_icon; ?> me-1"></i> <?php echo $badge_text; ?>
                                </span>
                                <small class="text-muted mt-2 assessment-time" style="font-size: 0.75rem; display: <?php echo $is_assessed ? 'block' : 'none'; ?>;">
                                    เมื่อ: <?php echo $display_time; ?> </small>
                            </div>
                        </div>

                        <div class="col-6 col-md-3 col-lg-3 text-end">
                            <button class="btn <?= $btn_class ?> px-3 py-2 fw-bold js-assess-btn shadow-sm w-100 w-md-auto"
                                <?= $btn_attr ?>
                                data-user-id="<?= $user_id ?>"
                                data-role-type="<?= $role['type'] ?>"
                                data-fullname="<?= $u['fullname'] ?>"
                                data-role-label="<?= $role['label'] ?>">
                                <i class="fas <?= $btn_icon ?> me-2"></i><?= $btn_text ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<?php $show_final_section = ($count_active_roles > 0 && $count_active_roles === $count_assessed_roles); ?>
<div id="final_section" style="display: <?= $show_final_section ? 'block' : 'none'; ?>;">
    <div class="card-incDetail">
        <div class="card-incDetail-header">
            <i class="fas fa-file-signature me-2"></i> สรุปผลการประเมินและลงนามรับรอง
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-signature table-bordered align-middle mb-0" id="signatureTable">
                    <thead class="table-light text-secondary">
                        <tr class="text-center">
                            <th class="py-3 text-uppercase" style="min-width: 160px;">ผู้ประเมิน</th>
                            <th class="py-3 text-uppercase" style="min-width: 120px;">ตำแหน่ง</th>
                            <th class="py-3 text-uppercase" style="min-width: 300px;">ลายเซ็น</th>
                            <th class="py-3 text-uppercase" style="width: 160px;">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-active-row">
                            <td class="ps-3">
                                <div class="fw-bold text-dark"><?= $header['evaluator_fullname'] ?></div>
                            </td>

                            <td class="text-center">
                                <div class="text-muted">
                                    <?= $header['evaluator_position'] ?: '-' ?>
                                </div>
                            </td>

                            <td class="text-center py-4">
                                <div class="signature-container mx-auto" style="max-width: 500px;">
                                    <?php if (!$is_completed): ?>
                                        <?php if ($can_evaluate): ?>
                                            <div class="border rounded bg-white shadow-sm position-relative overflow-hidden" style="height: 140px;">
                                                <canvas id="sig-canvas" style="width: 100%; height: 100%; cursor: crosshair; touch-action: none;"></canvas>
                                                <div id="sig-placeholder" class="position-absolute top-50 start-50 translate-middle text-muted opacity-25 pointer-events-none text-center">
                                                    <i class="fas fa-pen-nib fa-2x mb-2 d-block"></i>
                                                    <span>เซ็นชื่อเพื่อรับรองผล</span>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-center d-flex justify-content-center gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-warning px-3 shadow-sm js-eraser-btn" id="eraser_sig_btn">
                                                    <i class="fas fa-eraser me-1"></i> ยางลบ
                                                </button>

                                                <button type="button" class="btn btn-sm btn-outline-secondary px-3 shadow-sm" id="clear_sig_btn">
                                                    <i class="fas fa-trash-alt me-1"></i> ล้างลายเซ็น
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-light border rounded p-4 text-center">
                                                <i class="fas fa-user-lock fa-2x mb-2 text-muted opacity-50"></i>
                                                <div class="text-muted small">รอการลงนามรับรองโดยผู้ประเมิน</div>
                                                <div class="fw-bold text-primary"><?= $header['evaluator_fullname'] ?></div>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="p-2 border rounded bg-white shadow-sm d-flex align-items-center justify-content-center" style="height: 160px;">
                                            <img src="<?= $header['evaluator_signature'] ?>" alt="Signature" class="img-fluid" style="max-height: 140px;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="text-center">
                                <?php if ($is_completed): ?>
                                    <div class="text-success fw-bold">
                                        Completed<br>
                                        <small class="text-success fw-normal">
                                            <?= date('d/m/Y H:i', strtotime($header['signed_date'])) ?> น.
                                        </small>
                                    </div>

                                <?php elseif ($can_evaluate): ?>
                                    <button class="btn btn-success w-100 py-2 fw-bold shadow-sm" id="btn_complete_all">
                                        <i class="fas fa-save me-2"></i>ยืนยัน<span class="d-none d-lg-inline">ลงนาม</span>
                                    </button>

                                <?php else: ?>
                                    <button class="btn btn-secondary w-100 py-2 shadow-sm disabled" style="opacity: 0.6; cursor: not-allowed;"
                                        title="เฉพาะผู้ประเมินที่ระบุเท่านั้นที่มีสิทธิ์ดำเนินการ">
                                        <i class="fas fa-lock me-2"></i> ยืนยัน<span class="d-none d-lg-inline">ลงนาม</span>
                                    </button>
                                    <div class="mt-2 text-muted italic" style="font-size: 0.7rem;">
                                        * เฉพาะผู้ประเมินเท่านั้น
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if (!$is_completed): ?>
                <div class="p-3 bg-light-info border-top">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        <b>หมายเหตุ:</b> เมื่อลงนามและบันทึกแล้ว ผลการประเมินพนักงานทั้ง <?= $count_active_roles ?> ท่านจะถูกจัดเก็บเป็นประวัติถาวรและไม่สามารถแก้ไขได้
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="assessModal" tabindex="-1" aria-labelledby="assessModalLabel" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="assessModalLabel">
                    <i class="fas fa-user-check fa-lg me-2"></i>ประเมินความสามารถผู้ปฏิบัติหน้าที่
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">

                <!-- ส่วนเกณฑ์การประเมิน -->
                <div class="sticky-criteria shadow py-3 px-3 border-bottom bg-white">
                    <div class="d-flex flex-column flex-lg-row">

                        <div class="d-flex align-items-start justify-content-cente mb-2 mb-lg-0 me-lg-4">
                            <div class="fw-bold text-primary border-end pe-3 me-3" style="font-size: 0.9rem; white-space: nowrap;">
                                <i class="fas fa-list-check me-1"></i> เกณฑ์การประเมิน
                            </div>
                            <div class="text-muted small fw-bold" style="white-space: nowrap;">
                                ระดับคะแนน:
                            </div>
                        </div>

                        <div class="row g-2 g-md-3 flex-grow-1 w-100 align-items-center justify-content-center">
                            <div class="col-6 col-md-3 text-center">
                                <div class="criteria-item d-inline-flex align-items-center">
                                    <span class="criteria-number bg-score-0">0</span> <small class="text-secondary">ไม่ปฏิบัติ</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 text-center">
                                <div class="criteria-item d-inline-flex align-items-center">
                                    <span class="criteria-number bg-score-1">1</span> <small class="text-secondary">ปฏิบัติไม่ถูกต้อง</small>
                                </div>

                            </div>
                            <div class="col-6 col-md-3 text-center">
                                <div class="criteria-item d-inline-flex align-items-center">
                                    <span class="criteria-number bg-score-2">2</span> <small class="text-secondary">ถูกต้องไม่ครบถ้วน</small>
                                </div>

                            </div>
                            <div class="col-6 col-md-3 text-center">
                                <div class="criteria-item d-inline-flex align-items-center">
                                    <span class="criteria-number bg-score-3">3</span> <small class="text-secondary">ถูกต้องครบถ้วน</small>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- ส่วนแสดงรายละเอียดผู้รับการประเมิน -->
                <div class="alert alert-secondary d-flex align-items-center border-0 shadow-sm mb-4">
                    <i class="fas fa-user-circle fa-2x me-3 text-secondary"></i>
                    <div>
                        <strong id="modal_staff_name" class="d-block" style="font-size: 1.1rem;"></strong>
                        <span id="modal_staff_role" class="text-muted fw-bold small"></span>
                    </div>
                </div>

                <!-- ส่วนตารางการประเมิน -->
                <form id="assessForm">
                    <input type="hidden" name="header_id" value="<?php echo $assessment_id; ?>">
                    <input type="hidden" name="user_id" id="modal_user_id">
                    <input type="hidden" name="role_type" id="modal_role_type">

                    <table class="table table-bordered align-middle assessment-table">
                        <thead class="table-light text-center">
                            <tr>
                                <th rowspan="2" class="align-middle" style="width: 45%;">กิจกรรมดำเนินการเฝ้าระวัง
                                    <br class="d-lg-none">
                                    <span class="d-none d-md-inline">(ขั้นตอน)</span>
                                </th>
                                <th colspan="4">ผลการประเมิน</th>
                                <th rowspan="2" class="align-middle" style="width: 15%;">ความเห็น</th>
                                <th rowspan="2" class="align-middle" style="width: 10%;">หมายเหตุ</th>
                            </tr>
                            <tr class="score-header">
                                <th style="width: 40px;">0<br />คะแนน</th>
                                <th style="width: 40px;">1<br />คะแนน</th>
                                <th style="width: 40px;">2<br />คะแนน</th>
                                <th style="width: 40px;">3<br />คะแนน</th>
                            </tr>
                        </thead>

                        <tbody id="criteria_body">
                            <!-- ใช้การดึงจาก template ที่ทำไว้มาแสดง แยกตาม role -->
                        </tbody>
                    </table>

                </form>

                <!-- ส่วนของการคำนวณ และแสดงผลคะแนนที่ได้ -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <table class="table table-sm table-bordered fw-bold shadow-sm">

                            <tr class="table-light">
                                <td style="width: 50%;" class="px-3">คะแนนเต็ม</td>
                                <td colspan="2" class="align-middle">
                                    <div class="position-relative">

                                        <div class="text-center">
                                            <span class="text-primary fw-bold m-0" id="max_score_display">60</span>
                                        </div>

                                        <span class="position-absolute top-50 end-0 translate-middle-y text-dark px-2">
                                            คะแนน
                                        </span>

                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td class="px-3">คะแนนที่ได้รับ</td>
                                <td colspan="2" class="align-middle">
                                    <div class="position-relative">

                                        <div class="text-center">
                                            <span class="text-success fw-bold m-0" id="total_score_display">0</span>
                                        </div>

                                        <span class="position-absolute top-50 end-0 translate-middle-y text-dark px-2">
                                            คะแนน
                                        </span>

                                    </div>
                                </td>
                            </tr>

                            <tr class="table-info text-dark">
                                <td class="px-3">คิดเป็นร้อยละ</td>
                                <td colspan="2" class="text-center">
                                    <span class="fw-bold m-0" id="percentage_display">0.00</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light shadow-sm">
                            <div class="card-body text-center d-flex flex-column justify-content-center">
                                <small class="text-muted d-block mb-0">สรุปผลการประเมิน:</small>
                                <h4 class="fw-bold mb-0" id="grade_display">รอการประเมิน...</h4>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer d-flex justify-content-end bg-light border-top shadow-sm">
                <?php if (!$is_completed): ?>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" form="assessForm">
                        <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                    </button>
                <?php endif; ?>

                <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal" id="btn_modal_close">
                    <i class="fas fa-times me-2"></i> <?php echo $is_completed ? 'ปิดหน้าต่าง' : 'ยกเลิก'; ?>
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
    let currentMaxScore = 60;

    let signaturePad = null
    let isEraserMode = false;

    const isCompleted = <?php echo json_encode($is_completed); ?>;
    const canEvaluate = <?php echo json_encode($can_evaluate); ?>;

    $(document).ready(function() {

        // 1. กดปุ่มเริ่มประเมิน
        $(document).on('click', '.js-assess-btn', function() {
            const d = $(this).data();
            const roleLabel = d.roleLabel; // "หัวหน้าทีม", "ช่างภาพ"
            const userId = d.userId;
            const headerId = $('input[name="header_id"]').val();

            // 1. แสดงสถานะกำลังโหลด (Loading State) ในตาราง
            $('#criteria_body').html(`
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                        <br><span class="text-muted">กำลังโหลดเกณฑ์การประเมิน...</span>
                    </td>
                </tr>
            `);

            // ใส่ข้อมูลเบื้องต้นของผู้รับการประเมินลงใน Modal รอไว้ก่อน
            $('#modal_staff_name').text(d.fullname);
            $('#modal_staff_role').text(d.roleLabel);
            $('#modal_user_id').val(d.userId);
            $('#modal_role_type').val(d.roleType);

            // เรียก Reset คะแนนให้ขึ้นว่า "รอการประเมิน..." ทันทีที่เปิด
            resetScoreDisplay();

            // 2. เรียกใช้ API เพื่อดึง HTML ตารางที่ปั้นจาก PHP
            $.ajax({
                url: 'api/Transaction_staff_assessment/detail/get_assessment_form.php',
                type: 'POST',
                data: {
                    role_label: roleLabel,
                    user_id: userId,
                    header_id: headerId
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        // --- A. จัดการเนื้อหาตาราง ---
                        $('#criteria_body').html(res.table_html);

                        currentMaxScore = res.max_score;
                        $('#max_score_display').text(currentMaxScore);

                        // --- B. ปรับแต่ง UI Modal (โหมด Add/Edit/View) ---
                        const ui = res.ui;
                        const mode = res.mode; // 'NEW', 'EDIT', 'COMPLETED'
                        const modalHeader = $('#assessModal .modal-header');
                        const modalTitle = $('#assessModalLabel');
                        const submitBtn = $('#assessModal button[type="submit"]');
                        const userInfoBox = $('.modal-body .alert');
                        const userIcon = userInfoBox.find('i');

                        // 1. ล้าง Class เก่าออกให้เกลี้ยงก่อนใส่ใหม่ (ป้องกันสีผสมกัน)
                        modalHeader.removeClass('bg-primary edit-mode completed-mode text-white');
                        userInfoBox.removeClass('alert-secondary alert-primary alert-warning alert-success');
                        userIcon.removeClass('text-secondary text-primary text-warning text-success text-dark');

                        // 2. ใส่ Class ตามที่ API กำหนดมาใน ui_config
                        modalHeader.addClass(ui.header_class);
                        if (ui.header_class === 'bg-primary') modalHeader.addClass('text-white');
                        modalTitle.html(`<i class="fas ${ui.icon} fa-lg me-2"></i> ${ui.title}`);

                        // จัดการปุ่มบันทึก
                        if (res.is_completed || !canEvaluate) {
                            // --- สถานะ เสร็จสมบูรณ์ (สีเขียว) ---
                            userInfoBox.addClass('alert-success');
                            userIcon.addClass('text-success');
                            submitBtn.hide();
                            $('#btn_modal_close').html('<i class="fas fa-times me-2"></i> ปิดหน้าต่าง');
                            $('#assessForm input, #assessForm textarea').prop('disabled', true);

                            calculateScores(); // โชว์คะแนนเดิมที่มีอยู่แล้ว
                        } else if (mode === 'EDIT') {
                            // --- สถานะ แก้ไข (สีเหลือง) ---
                            userInfoBox.addClass('alert-warning');
                            userIcon.addClass('text-dark');

                            submitBtn.show().html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล')
                                .removeClass('btn-primary').addClass('btn-warning text-dark');
                            $('#btn_modal_close').html('<i class="fas fa-times me-2"></i> ยกเลิก');
                            $('#assessForm input, #assessForm textarea').prop('disabled', false);

                            calculateScores(); // โชว์คะแนนเดิมที่มีอยู่แล้ว
                        } else {
                            // --- สถานะ สร้างใหม่ (สีน้ำเงิน) ---
                            userInfoBox.addClass('alert-primary');
                            userIcon.addClass('text-primary');

                            submitBtn.show().html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล')
                                .removeClass('btn-warning text-dark').addClass('btn-primary');
                            $('#btn_modal_close').html('<i class="fas fa-times me-2"></i> ยกเลิก');
                            $('#assessForm input, #assessForm textarea').prop('disabled', false);

                            resetScoreDisplay(); // ให้ขึ้นว่า "รอการประเมิน..."
                        }

                        // แสดง Modal
                        $('#assessModal').modal('show');

                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                }
            });
        });

        // ฟังก์ชันสำหรับหยอดข้อมูลกลับเข้าฟอร์ม
        function fillFormData(data) {
            const details = data.details;
            const result = data.result;

            // 1. วนลูปข้อมูลรายข้อ ใส่กลับเข้า Input
            // details คือ Object { "1": {score:.., comment:..}, "2": ... }
            for (const [criteriaId, val] of Object.entries(details)) {
                // ติ๊ก Radio Score
                $(`input[name="score[${criteriaId}]"][value="${val.score}"]`).prop('checked', true);

                // ใส่ Comment
                $(`textarea[name="comment[${criteriaId}]"]`).val(val.comment);

                // ใส่ Remark (ทั้ง textarea และ hidden input ถ้ามี)
                $(`textarea[name="remark[${criteriaId}]"]`).val(val.remark);
                $(`input[name="remark[${criteriaId}]"]`).val(val.remark); // กรณีเป็น hidden field
            }

            // 2. อัปเดตคะแนนรวมที่หน้าจอ
            const total = result.total_score;
            const percent = parseFloat(result.percentage);

            // เรียกฟังก์ชันนี้ มันจะไปคำนวณเกรดและใส่สีให้เองตาม Logic เดียวกับตอนกดเลือก
            updateScoreUI(total, percent);
        }

        function resetScoreDisplay() {
            $('#total_score_display').text('0');
            $('#percentage_display').text('0.00');
            $('#grade_display').text('รอการประเมิน...').removeClass().addClass('fw-bold mb-0 text-muted');
        }

        $(document).on('click', '.js-click-score', function() {
            // หา Radio ตัวลูกภายในช่องนั้น แล้วสั่ง Check
            const radio = $(this).find('input[type="radio"]');
            // เช็คก่อนว่า Radio ถูก Disable อยู่หรือไม่ ถ้าถูก disabled อยู่ คืออยู่ใน mode readonly ก็ต้องห้ามแก้ไข
            if (radio.prop('disabled')) {
                return; // ถ้า Disable อยู่ ให้จบฟังก์ชันทันที ไม่ทำอะไรต่อ
            }

            radio.prop('checked', true);
            radio.trigger('change');
        });

        // ฟังก์ชันคำนวณคะแนนอัตโนมัติ (Real-time Calculation)
        $(document).on('change', 'input[type="radio"][name^="score"]', function() {
            calculateScores();
        });

        function calculateScores() {
            let totalScore = 0;

            $('#assessForm input[type="radio"][name^="score"]:checked').each(function() {
                totalScore += parseInt($(this).val());
            });

            // คำนวณร้อยละ
            const percentage = (totalScore / currentMaxScore) * 100;
            updateScoreUI(totalScore, percentage);
        }

        function updateScoreUI(total, percent) {
            $('#total_score_display').text(total);
            $('#percentage_display').text(percent.toFixed(2));

            let grade = "ต้องปรับปรุง";
            let colorClass = "text-danger";

            if (percent >= 90) {
                grade = "ดีเยี่ยม";
                colorClass = "text-success";
            } else if (percent >= 80) {
                grade = "ดีมาก";
                colorClass = "text-primary";
            } else if (percent >= 70) {
                grade = "ดี";
                colorClass = "text-info";
            } else if (percent >= 60) {
                grade = "พอใช้";
                colorClass = "text-warning";
            }

            $('#grade_display').text(grade).removeClass().addClass('fw-bold mb-0 ' + colorClass);
        }

        // จัดการการส่งฟอร์ม (Submit Assessment)
        // 3. บันทึกข้อมูล (AJAX Submit)
        $('#assessForm').on('submit', function(e) {
            e.preventDefault();
            const form = this;

            // A. Validation: เช็คว่าประเมินครบทุกข้อไหม
            const totalQuestions = new Set($('input[type="radio"][name^="score"]').map(function() {
                return this.name;
            }).get()).size;
            const answeredQuestions = $('input[type="radio"][name^="score"]:checked').length;

            if (answeredQuestions < totalQuestions) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบถ้วน',
                    text: `คุณประเมินไปแล้ว ${answeredQuestions}/${totalQuestions} ข้อ กรุณาประเมินให้ครบทุกข้อ`,
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#f39c12' // สีส้ม
                });
                return;
            }

            // B. เตรียมข้อมูลส่ง API
            // เพิ่มข้อมูลสรุปคะแนนเข้าไปใน FormData ด้วย
            const formData = new FormData(form);
            formData.append('total_score', $('#total_score_display').text());
            formData.append('max_score', currentMaxScore);
            formData.append('percentage', $('#percentage_display').text());
            formData.append('grade', $('#grade_display').text());

            const submitBtn = $(form).find('button[type="submit"]');
            const originalBtnText = submitBtn.html();

            $.ajax({
                url: 'api/Transaction_staff_assessment/detail/save.php',
                type: 'POST',
                data: formData,
                processData: false, // สำคัญเมื่อใช้ FormData
                contentType: false, // สำคัญเมื่อใช้ FormData
                dataType: 'json',
                beforeSend: function() {
                    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> กำลังบันทึก...');
                },
                success: function(response) {
                    if (response.status === 'success') {
                        $('#assessModal').modal('hide');

                        Swal.fire({
                            icon: 'success',
                            title: 'บันทึกสำเร็จ',
                            text: 'ผลการประเมินถูกบันทึกเรียบร้อยแล้ว',
                            timer: 1500,
                            showConfirmButton: false
                        });

                        // C. อัปเดตหน้า UI (Badge สถานะ) โดยไม่ต้อง Reload หน้าเว็บ
                        updateStatusBadge(response.role_type, response.timestamp);

                        // (Optional) เช็คว่าประเมินครบทุกคนหรือยัง เพื่อโชว์ปุ่ม "สรุปผลทั้งหมด"
                        checkAllCompleted();

                    } else {
                        Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
                    }
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                    Swal.fire('Server Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html(originalBtnText);
                }
            });
        });

        // ฟังก์ชันอัปเดต Badge สถานะในหน้า List
        function updateStatusBadge(roleType, timestamp) {
            const container = $(`#status_${roleType}`);
            if (container.length) {
                // เปลี่ยน Badge เป็นสีเขียว
                container.find('.badge').removeClass('bg-white text-muted border')
                    .addClass('bg-success text-white border-0')
                    .html('<i class="fas fa-check-circle me-1"></i> ประเมินแล้ว');

                // แสดงเวลาที่ประเมิน
                container.find('.assessment-time').text('เมื่อ: ' + timestamp).show();

                // เปลี่ยนปุ่ม "เริ่มประเมิน" เป็น "แก้ไขผล"
                const btn = $(`#item_${roleType} .js-assess-btn`);
                btn.removeClass('btn-primary').addClass('btn-outline-primary')
                    .html('<i class="fas fa-edit me-2"></i>แก้ไขผล');
            }
        }

        // ฟังก์ชันเช็คว่าประเมินครบทุกคนหรือยัง
        function checkAllCompleted() {
            // นับจำนวน Badge ที่เป็นสีเขียว (bg-success)
            const totalStaff = $('.staff-item').length;
            const completedStaff = $('.status-container .badge.bg-success').length;

            if (totalStaff > 0 && totalStaff === completedStaff) {
                $('#final_section').fadeIn(function() {
                    // เรียก Init เมื่อ FadeIn เสร็จแล้วชัวร์ๆ
                    initSignaturePad();
                }); // แสดงส่วนเซ็นชื่อปิดงาน
            }
        }

        // =========================================================
        // ส่วนจัดการ Signature Pad (ลายเซ็นผู้ประเมิน)
        // =========================================================
        let signaturePad = null;
        const canvas = document.getElementById('sig-canvas');

        function initSignaturePad() {
            if (!canvas) return;

            // เช็คว่า Container มีความกว้างจริงไหม (ถ้า hidden อยู่ width จะเป็น 0)
            if (canvas.offsetWidth === 0) {
                console.warn('Canvas is hidden, cannot init.');
                return;
            }

            // ถ้าเคย Init ไปแล้วไม่ต้องทำซ้ำ (ป้องกันการทับซ้อน)
            if (signaturePad) return;

            const signatureBox = canvas.parentElement;

            // 1. ปรับขนาด Canvas ให้พอดีกับ Container (สำคัญมากสำหรับหน้าจอ Retina/Mobile)
            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = signatureBox.offsetWidth * ratio;
                canvas.height = signatureBox.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);
            }

            // เรียก Resize เมื่อมีการหมุนหน้าจอ หรือปรับขนาด Browser
            window.addEventListener("resize", resizeCanvas);

            // เรียก Resize ครั้งแรก
            resizeCanvas();

            // 2. Initialize SignaturePad
            signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)', // พื้นหลังใส
                penColor: 'rgb(0, 0, 0)', // สีปากกาดำ
                minWidth: 1, // เส้นบางสุด
                maxWidth: 2.5 // เส้นหนาสุด
            });

            signaturePad.addEventListener("beginStroke", () => {
                // สั่งซ่อนเฉพาะ ID ของ Placeholder เท่านั้น
                $('#sig-placeholder').fadeOut();
            });
        }

        // กรณี A: หน้าเว็บโหลดมาแล้ว Section นี้โชว์อยู่แล้ว (เพราะ PHP เช็คว่าครบแล้ว)
        if ($('#final_section').is(':visible')) {
            // รอเล็กน้อยเพื่อให้ Browser Render ขนาดเสร็จสมบูรณ์
            setTimeout(initSignaturePad, 100);
        }

        $('#eraser_sig_btn').on('click', function() {
            toggleEraser();
        });

        // ปุ่มล้างลายเซ็น
        $('#clear_sig_btn').on('click', function() {
            if (signaturePad) {
                signaturePad.clear();
                setEraserMode(false);
                // สั่งโชว์เฉพาะ ID ของ Placeholder กลับมา
                $('#sig-placeholder').fadeIn();
            }
        });

        // --- ฟังก์ชันจัดการโหมดยางลบ (พร้อม Fix iPad Sticky Button) ---

        function setEraserMode(on) {
            if (!signaturePad) return;

            isEraserMode = on;
            const $btn = $('#eraser_sig_btn');

            if (on) {
                // เปิดโหมดยางลบ
                signaturePad.penColor = '#ffffff';
                signaturePad.minWidth = 10;
                signaturePad.maxWidth = 15;
                $btn.removeClass('btn-outline-warning').addClass('btn-warning active');
            } else {
                // ปิดโหมดยางลบ (กลับเป็นปากกา)
                signaturePad.penColor = 'rgb(0, 0, 0)';
                signaturePad.minWidth = 1;
                signaturePad.maxWidth = 2.5;
                $btn.removeClass('btn-warning active').addClass('btn-outline-warning');
            }
        }

        function toggleEraser() {
            setEraserMode(!isEraserMode);
        }

        // =========================================================
        // ส่วนบันทึกข้อมูลทั้งหมด (Final Submit)
        // =========================================================
        $('#btn_complete_all').on('click', function() {
            // 1. ตรวจสอบว่าเซ็นชื่อหรือยัง
            if (!signaturePad || signaturePad.isEmpty()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาลงลายเซ็น',
                    text: 'คุณต้องลงลายเซ็นรับรองผลการประเมินก่อนบันทึก',
                    confirmButtonText: 'ตกลง'
                });
                // Scroll ไปหา Canvas
                $('html, body').animate({
                    scrollTop: $("#sig-canvas").offset().top - 200
                }, 500);
                return;
            }

            // 2. แปลงลายเซ็นเป็น Base64 String
            const signatureData = signaturePad.toDataURL('image/png');

            // 3. ส่งข้อมูลไป Save API (API จบงาน)
            Swal.fire({
                title: 'ยืนยันการบันทึก?',
                text: "เมื่อบันทึกแล้วจะไม่สามารถแก้ไขผลการประเมินได้อีก",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#198754', // สีเขียว
                cancelButtonColor: '#d33',
                confirmButtonText: 'ยืนยัน, บันทึกเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'api/Transaction_staff_assessment/header/complete.php', // ต้องสร้างไฟล์นี้
                        type: 'POST',
                        data: {
                            header_id: $('input[name="header_id"]').val(), // Header ID ปัจจุบัน
                            signature: signatureData
                        },
                        dataType: 'json',
                        beforeSend: function() {
                            $('#btn_complete_all').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...');
                        },
                        success: function(res) {
                            if (res.status === 'success') {
                                Swal.fire({
                                        title: 'สำเร็จ!',
                                        text: "บันทึกข้อมูลและจบงานเรียบร้อยแล้ว",
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false
                                    })
                                    .then(() => {
                                        window.location.href = 'trans_staff_assessment.php';
                                    });
                            } else {
                                Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                                $('#btn_complete_all').prop('disabled', false).html('<i class="fas fa-save"></i> บันทึกและเสร็จสิ้นรายการ');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'ไม่สามารถเชื่อมต่อ Server ได้', 'error');
                            $('#btn_complete_all').prop('disabled', false).html('<i class="fas fa-save"></i> บันทึกและเสร็จสิ้นรายการ');
                        }
                    });
                }
            });
        });
    });
</script>
<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>