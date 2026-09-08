<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';

// 1. ตรวจสอบ ID
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("Location: trans_latent_chemical_test.php");
    exit;
}

$id = $_GET["id"];

// ดึงข้อมูล Role และ Department ID ของผู้ใช้งานปัจจุบัน
$current_user_id = $_SESSION['user_id'];
$user_role = "";
$user_dept_id = null;

try {
    $stmtUser = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
    $stmtUser->execute([$current_user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($userData) {
        $user_role    = strtolower($userData['role'] ?? '');
        $user_dept_id = $userData['department_id'] ?? null;
    }
} catch (Exception $e) {
    // กรณี Error
}

// 1. ดึงข้อมูลส่วน Header
$sql = "SELECT t1.*, 
            t2.prep_name, t2.prep_date, t2.expiry_date,
            DATE_FORMAT(t2.prep_date, '%d/%m/%Y') AS prep_date_show,
            DATE_FORMAT(t2.expiry_date, '%d/%m/%Y') AS exp_date_show,
            CONCAT(IFNULL(r_t.rank_name, ''), ' ', u_t.first_name, ' ', u_t.last_name) AS tester_fullname,
            CONCAT(IFNULL(r_v.rank_name, ''), ' ', u_v.first_name, ' ', u_v.last_name) AS verifier_fullname,
            pos_v.position_name AS verifier_position,
            DATE_FORMAT(t1.test_date, '%d/%m/%Y') AS test_date_show,
            DATE_FORMAT(t1.verifier_signature_date, '%d/%m/%Y %H:%i') AS verifier_date_show,
            -- ข้อมูลหน่วยนับของสารที่เตรียม (Target)
            t5.unit_name_th, t5.unit_name_en, t5.unit_symbol, t5.unit_group,

            -- ดึง department_id จากสารตั้งต้นเพื่อใช้เช็คสิทธิ์
            (SELECT mcl_sub.department_id 
             FROM preparation_ingredients pi_sub
             JOIN chemical_inventory inv_sub ON pi_sub.inventory_id = inv_sub.id
             JOIN master_chemical_list mcl_sub ON inv_sub.chemical_id = mcl_sub.id
             WHERE pi_sub.prep_id = t2.id AND pi_sub.status_delete = 0 
             LIMIT 1) AS department_id,

            -- ดึงชื่อหน่วยงานจากสารตั้งต้นตัวแรกของสูตรนี้ (ผ่าน Subquery)
            (SELECT md.department_name 
             FROM preparation_ingredients pi_sub
             JOIN chemical_inventory inv_sub ON pi_sub.inventory_id = inv_sub.id
             JOIN master_chemical_list mcl_sub ON inv_sub.chemical_id = mcl_sub.id
             JOIN master_departments md ON mcl_sub.department_id = md.id
             WHERE pi_sub.prep_id = t2.id AND pi_sub.status_delete = 0 
             LIMIT 1) AS department_name
             
        FROM trans_latent_chemical_test t1 
        INNER JOIN chemical_preparation t2 ON t1.prep_id = t2.id
        LEFT JOIN master_chemical_units t5 ON t2.unit_id = t5.id
        LEFT JOIN user_profile u_t ON t1.tester_id = u_t.user_id
        LEFT JOIN user_rank r_t ON u_t.rank_id = r_t.rank_id
        LEFT JOIN user_profile u_v ON t1.verifier_id = u_v.user_id
        LEFT JOIN user_rank r_v ON u_v.rank_id = r_v.rank_id
        LEFT JOIN user_position pos_v ON u_v.position_id = pos_v.position_id
        WHERE t1.status_delete = 0 AND t1.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    header("Location: trans_latent_chemical_test.php");
    exit;
}

// ป้องกันการเข้าถึงข้ามหน่วยงาน: ถ้าไม่ใช่ Admin และ ID ที่ส่งมาเป็นของหน่วยงานอื่น ให้ดึงกลับหน้าหลักทันที
if ($user_role !== 'admin') {
    if ($data['department_id'] != $user_dept_id) {
        header("Location: trans_latent_chemical_test.php");
        exit;
    }
}

// 2. ดึงรายการสารตั้งต้น (Ingredients) - แยกออกมาเป็น Query ใหม่
$sqlIng = "SELECT pi.amount_used, inv.lot_number, m.chemical_name, m.chemical_brand,
                  u.unit_name_th, u.unit_symbol, u.unit_group, u.unit_name_en
           FROM preparation_ingredients pi
           LEFT JOIN chemical_inventory inv ON pi.inventory_id = inv.id
           LEFT JOIN master_chemical_list m ON inv.chemical_id = m.id
           LEFT JOIN master_chemical_units u ON pi.unit_id = u.id
           WHERE pi.prep_id = ? AND pi.status_delete = 0";
$stmtIng = $pdo->prepare($sqlIng);
$stmtIng->execute([$data['prep_id']]);
$ingredients = $stmtIng->fetchAll(PDO::FETCH_ASSOC);

// จัด Format หน่วยนับ
foreach ($ingredients as &$ing) {
    $isPkg = (strpos($ing['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($ing['unit_group'], 'Packaging') !== false);
    $sym = $isPkg ? $ing['unit_name_en'] : $ing['unit_symbol'];
    $ing['unit_display'] = $ing['unit_name_th'] . " (" . $sym . ")";
}
unset($ing);

// แตกรายละเอียดสารเคมีตั้งต้น เตรียมตัวแปรสำหรับรวม String
$names = []; 
$lots = []; 
$brands = [];

foreach ($ingredients as $ing) {
    $names[] = htmlspecialchars($ing['chemical_name']);
    $lots[] = htmlspecialchars($ing['lot_number']);
    // ถ้าไม่มีแบรนด์ให้แสดงเป็น '-'
    $brands[] = !empty($ing['chemical_brand']) ? htmlspecialchars($ing['chemical_brand']) : '-';
}

// ใช้ array_unique เพื่อคัดค่าที่ซ้ำออก แล้วค่อย implode รวมเป็น String คั่นด้วยเครื่องหมายคอมมา
$display_names  = !empty($names)  ? implode(", ", array_unique($names))  : "-";
$display_lots   = !empty($lots)   ? implode(", ", array_unique($lots))   : "-";
$display_brands = !empty($brands) ? implode(", ", array_unique($brands)) : "-";

$unitDisplay = '-';
if (!empty($data['unit_name_th'])) {
    $isPkg = (strpos($data['unit_group'], 'บรรจุภัณฑ์') !== false || strpos($data['unit_group'], 'Packaging') !== false);
    $symbol = $isPkg ? $data['unit_name_en'] : $data['unit_symbol'];
    $unitDisplay = $data['unit_name_th'] . " (" . $symbol . ")";
}
// เก็บเข้าตัวแปร $data เพื่อความง่ายในการเรียกใช้ใน HTML
$data['unit_display'] = $unitDisplay;

// 3. ดึงรายการรูปภาพที่เกี่ยวข้อง
$sql_attach = "SELECT id, original_name, file_type, file_size,
               DATE_FORMAT(create_date, '%d/%m/%Y %H:%i') AS createdate_show
               FROM trans_latent_chemical_attachment 
               WHERE latent_test_id = ? AND status_delete = 0 
               ORDER BY id ASC";
$stmt_attach = $pdo->prepare($sql_attach);
$stmt_attach->execute([$id]);
$attachments = $stmt_attach->fetchAll(PDO::FETCH_ASSOC);

$title = "รายละเอียดการตรวจความพร้อมสารเคมีลายนิ้วมือแฝง";

ob_start();
?>

<style>
    /* สไตล์สำหรับกล่องรูปภาพแนบ */
    .attachment-card {
        border: 1px solid #e0e6ed;
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
        background: #fff;
        position: relative;
        height: 100%;
    }

    .attachment-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    /* ส่วน Action Bar (ปุ่มลบ/ดูภาพ) */
    .card-header-actions {
        background-color: #f8f9fa;
        padding: 8px 12px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        border-bottom: 1px solid #eee;
    }

    /* Image Box */
    .img-box-middle {
        width: 100%;
        height: 180px;
        /* เพิ่มความสูงเล็กน้อยให้เห็นชัด */
        overflow: hidden;
        background-color: #fcfcfc;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .img-box-middle img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .btn-action-sm {
        width: 30px;
        height: 30px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        font-size: 0.85rem;
    }

    .filename-text {
        font-size: 0.75rem;
        color: #6c757d;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding: 10px;
        background: #fff;
    }

    .badge-circle {
        width: 20px;
        /* ปรับขนาดตามต้องการ */
        height: 20px;
        /* ต้องเท่ากับ width */
        border-radius: 50%;
        /* ทำให้เป็นวงกลม */
        display: inline-flex;
        /* ใช้ flex เพื่อจัดกึ่งกลาง */
        align-items: center;
        /* กึ่งกลางแนวตั้ง */
        justify-content: center;
        /* กึ่งกลางแนวนอน */
        padding: 0;
        /* ล้างค่า padding เดิม */
        font-size: 0.7rem;
        /* ขนาดตัวเลข */
        padding-bottom: 2px;
        /* ดันตัวเลขขึ้นข้างบน */
    }

    body.lightbox-open {
        overflow: hidden;
    }

    /* สไตล์ปุ่มปิด Lightbox */
    .btn-close-lightbox-latent {
        position: absolute;
        top: 20px;
        right: 20px;
        background: none;
        border: none;
        color: white;
        font-size: 2.5rem;
        cursor: pointer;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        /* ทำให้พื้นหลังเป็นวงกลม */
        transition: all 0.2s ease;
        /* ให้การเปลี่ยนสีดูนุ่มนวล */
        line-height: 1;
        z-index: 10001;

        line-height: 0;
        padding-bottom: 16px;
    }

    /* เอฟเฟกต์ตอนเอาเมาส์ไปวาง (Hover) */
    .btn-close-lightbox-latent:hover {
        background-color: rgba(255, 255, 255, 0.15);
        transform: scale(1.1)
            /* พื้นหลังขาวอ่อนๆ */
    }

    .btn-eraser.btn-outline-warning:not(.active):focus,
    .btn-eraser.btn-outline-warning:not(.active):hover,
    .btn-eraser.btn-outline-warning:not(.active):active {
        background-color: transparent !important;
        color: #ffc107 !important;
        box-shadow: none !important;
    }

    /* ล็อก Scroll หน้าหลัง */
</style>

<div class="d-flex align-items-center mb-4">
    <a href="./trans_latent_chemical_test.php" class="text-decoration-none text-secondary d-flex align-items-center fw-medium me-3 hover-text-primary">
        <i class="fa-solid fa-chevron-left me-2" style="margin-top: 2px;"></i> ย้อนกลับ
    </a>
    <div class="vr opacity-25 me-3" style="height: 25px;"></div>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="./trans_latent_chemical_test.php" class="text-decoration-none text-muted hover-text-primary"> <i class="fa-solid fa-folder-open me-1"></i> รายการตรวจความพร้อม</a></li>
            <li class="breadcrumb-item active text-primary" aria-current="page">รายละเอียด</li>
        </ol>
    </nav>
</div>

<div class="page-header d-flex flex-wrap justify-content-between align-items-end pb-3 border-bottom mb-4">
    <div class="d-flex align-items-center">
        <div class="bg-primary rounded-pill me-3 shadow-sm align-self-stretch" style="width: 6px;"></div>
        <div>
            <h3 class="fw-bold mb-0 d-flex align-items-baseline">
                <span class="text-secondary fs-4 me-2">การตรวจความพร้อม:</span>
                <span class="text-primary"> <?= htmlspecialchars($data['prep_name']) ?></span>
            </h3>
            <div class="text-muted small mt-1">
                <i class="fa-regular fa-clock me-1"></i> วันที่ทดสอบ: <?= $data['test_date_show'] ?>
                <span class="mx-1">|</span><i class="fa-solid fa-tag ms-2 me-1"></i> Lot ต้นทาง: <?= $display_lots ?>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center">
        <?php if ($data['is_verified']): ?>
            <div class="d-inline-flex align-items-center px-3 py-2 rounded-3 border border-success border-opacity-50 bg-success bg-opacity-25 text-success shadow-sm">
                <span class="small fw-bold me-lg-2 d-none d-lg-block border-end border-success border-opacity-50 pe-2" style="opacity: 0.8;">สถานะ</span>
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle fa-lg me-2"></i>
                    <span class="fw-bold">ทวนสอบแล้ว</span>
                </div>
            </div>
        <?php else: ?>
            <div class="d-inline-flex align-items-center px-3 py-2 rounded-3 border border-warning border-opacity-50 bg-warning bg-opacity-25 text-dark shadow-sm">
                <span class="small fw-bold me-lg-2 d-none d-lg-block border-end border-warning border-opacity-50 pe-2 text-muted">สถานะ</span>
                <div class="d-flex align-items-center">
                    <i class="fas fa-clock fa-lg me-2 text-warning"></i>
                    <span class="fw-bold">รอการทวนสอบ</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-1">
    <div class="col-lg-12">
        <div class="card-incDetail">
            <div class="card-incDetail-header">
                <i class="fas fa-flask me-2"></i> ข้อมูลสารเคมีและผลการตรวจสอบความพร้อม
            </div>

            <div class="card-body">
                <div class="row">
                    <div class="col-12 col-md-6">
                        <div class="d-flex flex-column gap-3">
                            <div class="info-group">
                                <label class="text-muted small d-block mb-1">ชื่อสารที่เตรียม</label>
                                <span class="fw-bold text-primary"><?= htmlspecialchars($data['prep_name']) ?></span>
                            </div>
                            <div class="info-group">
                                <label class="text-muted small d-block mb-1">รายการสารเคมีตั้งต้น</label>
                                <span class="fw-bold"><?= $display_names ?></span>
                            </div>
                            <div class="info-group">
                                <label class="text-muted small d-block mb-1">เลข Lot สารตั้งต้น</label>
                                <span class="fw-bold"><?= $display_lots ?></span>
                            </div>
                            <div class="info-group">
                                <label class="text-muted small d-block mb-1">ยี่ห้อ/ผู้ผลิต</label>
                                <span class="fw-bold"><?= $display_brands ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 border-md-start">
                        <div class="d-flex flex-column gap-3 ps-md-3">
                            <div class="info-group">
                                <label class="text-muted small d-block mb-1">หน่วยงานต้นสังกัด</label>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($data['department_name'] ?? '-') ?></span>
                            </div>
                            <div class="info-group">
                                <label class="text-muted small d-block mb-1">วันที่เตรียมสาร</label>
                                <span class="fw-bold text-dark"><?= $data['prep_date_show'] ?></span>
                            </div>
                            <div class="info-group">
                                <label class="text-muted small d-block mb-1">วันหมดอายุของที่เตรียม</label>
                                <span class="fw-bold text-danger"><?= $data['exp_date_show'] ?></span>
                            </div>
                            <div class="info-group">
                                <label class="text-muted small d-block mb-1">ปริมาณที่ดึงมาตรวจสอบ</label>
                                <span class="fw-bold text-dark"><?= number_format($data['amount_used'], 2) ?> <?= $data['unit_display'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-4 pt-3 border-top">
                    <div class="info-group d-inline-flex align-items-center bg-light px-3 py-2 rounded-3 border border-secondary border-opacity-10">
                        <i class="fas fa-user-edit text-muted me-2 fs-5 opacity-75"></i>
                        <div>
                            <label class="text-muted small d-block mb-0" style="font-size: 0.75rem;">ผู้บันทึกการทดสอบ</label>
                            <span class="fw-bold text-dark"><?= htmlspecialchars($data['tester_fullname']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-top">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between p-3 rounded-3 shadow-sm border-start border-4 <?= $data['readiness_status'] == 'ready' ? 'bg-success-subtle border-success' : 'bg-danger-subtle border-danger' ?>">
                                <div class="mb-2 mb-md-0">
                                    <span class="fw-bold text-dark">สรุปความพร้อมในการใช้งาน</span>
                                </div>
                                <div style="min-width: 200px;">
                                    <span class="badge <?= $data['readiness_status'] == 'ready' ? 'bg-success' : 'bg-danger' ?> px-4 py-2 fs-6 shadow-sm w-100 rounded-pill">
                                        <i class="fas <?= $data['readiness_status'] == 'ready' ? 'fa-check-circle' : 'fa-times-circle' ?> me-2"></i>
                                        <?= $data['readiness_status'] == 'ready' ? 'พร้อมใช้งาน' : 'ไม่พร้อมใช้งาน' ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card border-0 bg-light rounded-3">
                                <div class="card-body p-0">
                                    <label class="text-muted small mb-1 d-block">
                                        หมายเหตุ / รายละเอียดการตรวจสอบเพิ่มเติม
                                    </label>
                                    <div class="bg-white p-3 rounded border" style="min-height: 80px; line-height: 1.6;">
                                        <?php if (!empty($data['remark'])): ?>
                                            <span class="text-dark fw-medium"><?= nl2br(htmlspecialchars($data['remark'])) ?></span>
                                        <?php else: ?>
                                            <div class="text-center py-2">
                                                <span class="text-muted italic small">- ไม่มีข้อมูลหมายเหตุเพิ่มเติม -</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
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
                <i class="fas fa-eyedropper me-2"></i> รายละเอียดผลการทดสอบทางเคมี (ลิตมัส)
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-12 col-md-6 border-md-end">
                        <div class="d-flex align-items-center justify-content-center py-3">
                            <div class="text-center">
                                <label class="text-muted small d-block mb-1">ค่า pH ที่วัดได้</label>
                                <span class="display-6 fw-bold text-primary">
                                    <?= $data['ph_value'] !== null ? number_format($data['ph_value'], 2) : '-' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="d-flex align-items-center justify-content-center py-3">
                            <div class="text-center w-100 px-4">
                                <label class="text-muted small d-block mb-2">การเปลี่ยนสีของกระดาษลิตมัส</label>
                                <?php if ($data['color_change_status'] == 'changed'): ?>
                                    <div class="alert alert-primary mb-0 py-2 shadow-sm border-0">
                                        <i class="fas fa-fill-drip me-2"></i> <b>เปลี่ยนสีทันที</b>
                                    </div>
                                <?php elseif ($data['color_change_status'] == 'unchanged'): ?>
                                    <div class="alert alert-secondary mb-0 py-2 shadow-sm border-0">
                                        <i class="fas fa-ban me-2"></i> <b>ไม่เปลี่ยนสี</b>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">- ไม่ระบุข้อมูล -</span>
                                <?php endif; ?>
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
                <i class="fas fa-images me-2"></i> ภาพถ่ายหลักฐานผลการทดสอบ
                <?php if (count($attachments) > 0): ?>
                    <span class="badge bg-white text-primary badge-circle ms-2" style="font-size: 12px; vertical-align: middle;">
                        <?= count($attachments) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (count($attachments) > 0): ?>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-3">
                        <?php foreach ($attachments as $index => $file): ?>
                            <div class="col">
                                <div class="attachment-card shadow-sm border">
                                    <!-- <div class="card-header-actions p-2">
                                        <button type="button" class="btn btn-outline-primary btn-action-sm"
                                            onclick="viewFullImage(<//?= $file['id'] ?>, '<//?= htmlspecialchars($file['original_name']) ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div> -->
                                    <div class="position-relative">
                                        <div class="img-box-middle" style="height: 200px; cursor: zoom-in;"
                                            onclick="viewFullImage(<?= $file['id'] ?>, '<?= htmlspecialchars($file['original_name']) ?>')">
                                            <img src="./api/Transaction_latent_chemical_test/get_attachment.php?id=<?= $file['id'] ?>"
                                                style="width:100%; height:100%; object-fit:cover;"
                                                class="transition-transform" alt="evidence">
                                        </div>
                                        <div class="position-absolute top-0 end-0 p-2">
                                            <button type="button" class="btn btn-white btn-sm shadow-sm rounded-circle border"
                                                onclick="viewFullImage(<?= $file['id'] ?>, '<?= htmlspecialchars($file['original_name']) ?>')"
                                                style="width: 34px; height: 34px;">
                                                <i class="fas fa-expand-alt text-white"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="card-body p-2 bg-white border-top">
                                        <div class="text-truncate small fw-bold text-dark mb-2" title="<?= htmlspecialchars($file['original_name']) ?>">
                                            <?= htmlspecialchars($file['original_name']) ?>
                                        </div>

                                        <div class="metadata-group mt-1">
                                            <div class="d-flex align-items-center text-secondary mb-1" style="font-size: 0.65rem;">
                                                <i class="fas fa-fw fa-hdd me-1 opacity-50"></i>
                                                <span><?= number_format($file['file_size'] / 1024, 2) ?> KB</span>
                                            </div>
                                            <div class="d-flex align-items-center text-secondary" style="font-size: 0.65rem;">
                                                <i class="fas fa-fw fa-calendar-alt me-1 opacity-50"></i>
                                                <span><?= $file['createdate_show'] ?> น.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-image fa-3x mb-3 opacity-50"></i>
                        <p class="mb-0 small">ไม่มีรูปภาพแนบสำหรับรายการนี้</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="col-12">
    <div class="card-incDetail">
        <div class="card-incDetail-header">
            <i class="fas fa-file-signature me-2"></i> การลงนามทวนสอบ
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-signature table-bordered align-middle mb-0">
                    <thead class="table-light text-secondary">
                        <tr class="text-center ">
                            <th class="py-3" style="min-width: 160px;">ผู้ทวนสอบ</th>
                            <th class="py-3" style="min-width: 120px;">ตำแหน่ง</th>
                            <th class="py-3" style="min-width: 310px;">ลายเซ็น</th>
                            <th class="py-3" style="min-width: 150px;">ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-active-row">
                            <td class="ps-3">
                                <div class="fw-bold text-dark"><?= $data['verifier_fullname'] ?></div>
                            </td>
                            <td class="text-center">
                                <div class="text-muted"><?= $data['verifier_position'] ?: '-' ?></div>
                            </td>

                            <?php $can_verify = ($_SESSION['user_id'] == $data['verifier_id']); ?>
                            <? //php $can_verify = true 
                            ?>

                            <td class="text-center py-4">
                                <div class="signature-container mx-auto" style="max-width: 500px;">
                                    <?php if ($data['is_verified'] == 0): ?>
                                        <?php if ($can_verify): ?>
                                            <div class="border rounded bg-white shadow-sm position-relative overflow-hidden" style="height: 150px;">
                                                <canvas id="sig-canvas" style="width: 100%; height: 100%; cursor: crosshair; touch-action: none;"></canvas>
                                                <div id="sig-placeholder" class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pointer-events-none text-center">
                                                    <i class="fas fa-signature fa-2x d-block mb-1"></i>
                                                    เซ็นชื่อทวนสอบความพร้อมที่นี่
                                                </div>
                                            </div>
                                            <div class="mt-2 text-center d-flex justify-content-center gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-warning px-3 shadow-sm btn-eraser" id="eraser_sig_btn">
                                                    <i class="fas fa-eraser me-1"></i> ยางลบ
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary px-3 shadow-sm" id="clear_sig_btn">
                                                    <i class="fas fa-trash-alt me-1"></i> ล้างลายเซ็น
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-secondary py-4 mb-0">
                                                <i class="fas fa-user-lock fa-2x mb-2 d-block opacity-50"></i>
                                                รอการลงนามโดย: <br>
                                                <b class="text-primary"><?= $data['verifier_fullname'] ?></b>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="p-2 border rounded bg-white shadow-sm d-flex align-items-center justify-content-center" style="height: 160px;">
                                            <img src="/csims/uploads/signatures/<?= $data['verifier_signature'] ?>"
                                                alt="Signature" class="img-fluid" style="max-height: 140px; width: auto; object-fit: contain;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="text-center">
                                <?php if ($data['is_verified'] == 1): ?>
                                    <div class="text-success fw-bold">
                                        Completed<br>
                                        <small class="text-success fw-normal"><?= $data['verifier_date_show'] ?> น.</small>
                                    </div>
                                <?php elseif ($can_verify): ?>
                                    <button class="btn btn-success px-4 shadow-sm fw-bold" id="btn_verify_submit">
                                        <i class="fas fa-save me-2"></i> บันทึก<span class="d-none d-lg-inline">การทวนสอบ</span>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary px-4 shadow-sm disabled" style="opacity: 0.6; cursor: not-allowed;"
                                        title="เฉพาะ <?= $data['verifier_fullname'] ?> เท่านั้นที่มีสิทธิ์บันทึก">
                                        <i class="fas fa-lock me-2"></i> บันทึก<span class="d-none d-lg-inline">การทวนสอบ</span>
                                    </button>
                                    <div class="mt-1">
                                        <small class="text-muted italic" style="font-size: 0.7rem;">
                                            * เฉพาะผู้ทวนสอบที่ระบุเท่านั้น
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="customLightboxLatent" class="d-none" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: zoom-out;" onclick="closeLightboxOutsideLatent(event)">

    <div id="lightboxFileNameLatent" style="position: absolute; top: 20px; left: 20px; color: white; font-size: 1.2rem; font-weight: 300; background: rgba(0,0,0,0.5); padding: 5px 15px; border-radius: 4px; pointer-events: none;"></div>

    <button type="button"
        class="btn-close-lightbox-latent"
        onclick="closeLightboxLatent()"
        title="ปิดหน้าต่าง">
        &times;
    </button>

    <img id="lightboxImageLatent" src="" style="max-width: 95%; max-height: 85%; object-fit: contain; box-shadow: 0 0 30px rgba(0,0,0,0.5); cursor: default;" onclick="event.stopPropagation()">

</div>

<?php
$content = ob_get_clean();
ob_start();
?>
<script>
    let verifierSignaturePad;
    let isEraserMode = false;

    $(document).ready(function() {
        // 1. Initialize Signature Pad
        const canvas = document.getElementById('sig-canvas');
        if (canvas) {
            const signatureBox = canvas.parentElement;

            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = signatureBox.offsetWidth * ratio;
                canvas.height = signatureBox.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);

                // เคลียร์ค่าที่ค้างอยู่ตอน Resize เพื่อป้องกันเส้นเพี้ยน
                if (verifierSignaturePad) verifierSignaturePad.clear();
            }

            window.addEventListener("resize", resizeCanvas);
            resizeCanvas();

            verifierSignaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)',
                penColor: 'rgb(0, 0, 0)',
                minWidth: 1,
                maxWidth: 2.5
            });

            // ซ่อน Placeholder เมื่อเริ่มเซ็น
            verifierSignaturePad.addEventListener("beginStroke", () => {
                $('#sig-placeholder').fadeOut();
            });
        }

        // 2. ผูก Event ให้ปุ่มต่างๆ (แทนการใช้ onclick ใน HTML)
        // ปุ่มยางลบ
        $('#eraser_sig_btn').on('click', function() {
            toggleEraser();
        });

        $('#clear_sig_btn').on('click', function() {
            clearSignature();
        });

        $('#btn_verify_submit').on('click', function() {
            saveVerification();
        });
    });

    // --- ฟังก์ชันจัดการลายเซ็น ---

    // ฟังก์ชันตั้งค่าโหมดยางลบ
    function setEraserMode(on) {
        if (!verifierSignaturePad) return;

        isEraserMode = on;
        const $btn = $('#eraser_sig_btn');

        if (on) {
            // เปิดโหมดยางลบ: สีขาว + เส้นใหญ่
            verifierSignaturePad.penColor = '#ffffff';
            verifierSignaturePad.minWidth = 10;
            verifierSignaturePad.maxWidth = 15;

            $btn.removeClass('btn-outline-warning').addClass('btn-warning active');
        } else {
            // ปิดโหมดยางลบ: สีดำปกติ
            verifierSignaturePad.penColor = 'rgb(0, 0, 0)';
            verifierSignaturePad.minWidth = 1;
            verifierSignaturePad.maxWidth = 2.5;

            $btn.removeClass('btn-warning active').addClass('btn-outline-warning');
        }
    }

    function toggleEraser() {
        setEraserMode(!isEraserMode);
    }

    function clearSignature() {
        if (verifierSignaturePad) {
            verifierSignaturePad.clear();
            setEraserMode(false);
            $('#sig-placeholder').fadeIn();
        }
    }

    async function saveVerification() {
        // ตรวจสอบว่ามีการเซ็นหรือยัง
        if (!verifierSignaturePad || verifierSignaturePad.isEmpty()) {
            Swal.fire({
                icon: 'warning',
                title: 'กรุณาเซ็นชื่อ',
                text: 'กรุณาลงลายเซ็นเพื่อรับรองความพร้อมสารเคมีก่อนบันทึก',
                confirmButtonColor: '#3085d6'
            });
            return;
        }

        const base64Data = verifierSignaturePad.toDataURL('image/png');
        const payload = {
            id: <?= $id ?>, // ID จาก PHP
            signature: base64Data
        };

        // แสดงยืนยันการบันทึก
        const result = await Swal.fire({
            title: 'ยืนยันการทวนสอบ?',
            text: "เมื่อบันทึกแล้ว ข้อมูลการตรวจความพร้อมนี้จะถูกล็อกและไม่สามารถแก้ไขได้อีก",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ยืนยันการลงนาม',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#0d6efd'
        });

        if (!result.isConfirmed) return;

        // --- ส่งข้อมูลไปยัง API ---
        $.ajax({
            url: './api/Transaction_latent_chemical_test/verifyRecord.php',
            type: 'POST',
            data: payload,
            dataType: 'json',
            beforeSend: function() {
                Swal.fire({
                    title: 'กำลังบันทึก...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            },
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'ทวนสอบสำเร็จ',
                        text: 'บันทึกการรับรองความพร้อมสารเคมีเรียบร้อยแล้ว',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload(); // รีโหลดหน้าเพื่อโชว์รูปภาพลายเซ็น
                    });
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
            }
        });
    }

    // ฟังก์ชันสำหรับเปิดดูรูปขนาดเต็มจาก Database ID
    function viewFullImage(id, filename) {
        const imageUrl = `./api/Transaction_latent_chemical_test/get_attachment.php?id=${id}`;

        $('#lightboxImageLatent').attr('src', imageUrl);
        $('#lightboxFileNameLatent').text(filename);
        $('#customLightboxLatent').removeClass('d-none').hide().fadeIn(200);
        $('body').addClass('lightbox-open');
    }

    // ปิด Lightbox เมื่อกด icon กากบาทมุมบนขวา
    function closeLightboxLatent() {
        $('#customLightboxLatent').fadeOut(200, function() {
            $(this).addClass('d-none');
        });
        $('body').removeClass('lightbox-open');
    }

    // ผูก Event ปิด Lightbox เมื่อกดพื้นที่นอกรูป 
    function closeLightboxOutsideLatent(event) {
        if (event.target.id === 'customLightboxLatent') {
            closeLightboxLatent();
        }
    }
</script>

<?php
$extra_scripts = ob_get_clean();
require './layout.php';
?>