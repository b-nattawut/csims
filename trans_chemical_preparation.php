<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require_once './includes/check_permission.php';

$canManage = hasPermission($pdo, 'chemical.create');

$current_user_id = $_SESSION['user_id'];
$current_user_fullname = "-";

$user_role = "";
$user_dept_id = null;

try {
    $sql_user = "SELECT 
                    t1.role,
                    t1.department_id,
                    CONCAT(IFNULL(t3.rank_name, ''), ' ', t2.first_name, ' ', t2.last_name) AS fullname
                FROM users t1
                INNER JOIN user_profile t2 ON t1.user_id = t2.user_id
                LEFT JOIN user_rank t3 ON t2.rank_id = t3.rank_id
                WHERE t1.user_id = ? AND t1.is_active = 1";

    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute([$current_user_id]);
    $user_row = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if ($user_row) {
        $current_user_fullname = trim($user_row['fullname']);
        $user_role    = strtolower($user_row['role'] ?? '');
        $user_dept_id = $user_row['department_id'] ?? null;
    }
} catch (Exception $e) {
    // กรณี Error ให้ใส่ชื่อกลางๆ ไว้ก่อน
    $current_user_fullname = "เจ้าหน้าที่ผู้รับผิดชอบ";
}

$title = "การเตรียมสารเคมี";

ob_start();
?>

<style>
    /* 1. ปรับแต่งหัวข้อกลุ่ม (Chemical Name) */
    .select2-results__group {
        display: block;
        background-color: #f8f9fa;
        /* สีพื้นหลังอ่อนๆ ให้ดูเป็นแถบหัวข้อ */
        color: #0056b3 !important;
        /* เปลี่ยนสีตัวอักษรหัวข้อ */
        font-weight: bold !important;
        font-size: 14px !important;
        padding: 8px 12px !important;
        /* เพิ่มระยะห่างจากกลุ่มก่อนหน้า */
        border-bottom: 1px solid #dee2e6;
        /* เส้นขีดคั่นด้านล่างหัวข้อ */
    }

    /* 2. ปรับแต่งรายการย่อย (Lot Number) */
    .select2-results__options--nested .select2-results__option {
        padding-left: 25px !important;
        /* เพิ่มการเยื้องเข้าไปด้านขวาเพื่อให้ดูเป็นลูก */
        border-bottom: 1px dotted #eeeeee;
        /* เส้นประจางๆ คั่นระหว่าง Lot */
        font-size: 13px;
    }

    /* สั่งลบเส้นประ (dotted) ออกจากรายการสุดท้ายในแต่ละกลุ่ม เพื่อไม่ให้ไปชนกับหมวดถัดไป */
    .select2-results__options--nested .select2-results__option:last-child {
        border-bottom: none !important;
    }

    /* 3. ปรับระยะห่างเมื่อนำเมาส์ไปวาง (Hover) */
    .select2-results__option--highlighted {
        background-color: #e9ecef !important;
        color: #333 !important;
    }

    .js-edit-prep-btn,
    .js-delete-prep-btn {
        padding: 12px;
        /* ขยายพื้นที่จิ้มออกไปรอบๆ */
        margin: -12px;
        /* ดึงขอบกลับมาเพื่อไม่ให้กระทบตำแหน่งในตาราง */
        cursor: pointer;
        touch-action: manipulation;
        /* ป้องกันการซูมเวลาเผลอ Double Tap */
    }

    /* ชื่อสารเคมี: หนาบน iPad (md) แต่ปกติบน PC (lg ขึ้นไป) */
    .chem-name-responsive {
        font-weight: 700;
        /* Bold สำหรับจอเล็ก/iPad */
    }

    @media (min-width: 992px) {
        .chem-name-responsive {
            font-weight: 500;
            /* กลับเป็นค่าปกติ (Medium) สำหรับจอใหญ่ */
        }
    }

    /* จัดการให้ Select2 ใน Input Group เชื่อมกับช่องด้านหน้าได้ */
    .input-group>.select2-container--bootstrap-5 {
        flex: 1 1 auto;
        width: 9% !important;
        /* ให้มันยืดหยุ่นตามพื้นที่ที่เหลือ */
    }

    .input-group>.select2-container--bootstrap-5 .select2-selection {
        /* ลบขอบมนฝั่งซ้ายออก เพื่อให้ต่อกับ Input ได้เนียน */
        border-top-left-radius: 0 !important;
        border-bottom-left-radius: 0 !important;
    }

    /* ถ้าต้องการล็อคความกว้างของหน่วยนับให้เท่ากันสวยๆ */
    .input-group .select2-container--bootstrap-5 {
        max-width: 200px;
    }
</style>

<!-- Card ของ Filter Form รวม input Field ที่ใช้สำหรับกรองข้อมูลรายการที่แสดงในตาราง -->
<div class="card mb-3 shadow-sm" style="font-size:13px;">
    <div class="card-body p-2">
        <div class="d-flex flex-wrap justify-content-end align-items-center mb-2">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterSection" aria-expanded="true">
                <i class="fas fa-filter me-1"></i> ตัวกรอง
            </button>
        </div>
        <div class="collapse show" id="filterSection">
            <div class="bg-light p-3 rounded-3 border mb-2 shadow-sm">

                <form id="searchFilterForm" name="searchFilterForm">
                    <div class="row g-2 justify-content-center align-items-end">

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <button class="btn <?= $canManage ? 'btn-success' : 'btn-secondary' ?> btn-sm px-4 fw-bold shadow-sm text-nowrap w-100 <?= $canManage ? 'js-add-prep-btn' : '' ?>"
                                style="height: 31px;"
                                type="button" <?= $canManage ? 'data-bs-toggle="modal" data-bs-target="#addEditPrepModal"' : 'disabled' ?>
                                title="<?= $canManage ? 'เพิ่มรายการใหม่' : 'คุณไม่มีสิทธิ์จัดการข้อมูล' ?>">
                                <i class="fas fa-plus me-2"></i>เพิ่มรายการ
                            </button>
                        </div>

                        <?php if ($user_role === 'admin'): ?>
                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_department_id" class="form-label text-muted small mb-1">หน่วยงาน</label>
                            <select id="filter_department_id" name="filter_department_id" class="form-select form-select-sm select2-dept" data-placeholder="-- ทั้งหมด --">
                                <option value=""></option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_prep_name" class="form-label text-muted small mb-1">ชื่อสารที่เตรียม</label>
                            <input type="text" id="filter_prep_name" name="filter_prep_name"
                                class="form-control form-control-sm" placeholder="เช่น Luminol Working...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_chemical_id" class="form-label text-muted small mb-1">สารตั้งต้น</label>
                            <select id="filter_chemical_id" name="filter_chemical_id" class="form-select form-select-sm select2-master" data-placeholder="-- ทั้งหมด --">
                                <option value=""></option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_source_lot" class="form-label text-muted small mb-1">เลข Lot สารตั้งต้น</label>
                            <input type="text" id="filter_source_lot" name="filter_source_lot"
                                class="form-control form-control-sm" placeholder="ระบุเลข Lot ต้นทาง...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_status" class="form-label text-muted small mb-1">สถานะสารเคมี</label>
                            <select id="filter_status" name="filter_status" class="form-select form-select-sm">
                                <option value="">ทั้งหมด</option>
                                <option value="active">พร้อมใช้งาน (ปกติ)</option>
                                <option value="near_expiry">ใกล้หมดอายุ</option>
                                <option value="expired">หมดอายุ</option>
                                <option value="depleted">ใช้หมดแล้ว</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_preparer" class="form-label text-muted small mb-1 fw-bold">ผู้เตรียม</label>
                            <select id="filter_preparer" name="filter_preparer" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                <option value=""></option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-8 col-xl-8 <?= ($user_role !== 'admin') ? 'col-md-12' : 'col-md-6' ?>">
                            <label class="form-label text-muted small mb-1">ช่วงวันที่เตรียม</label>
                            <div class="input-group input-group-sm">
                                <input type="date" id="filter_prep_start" name="filter_prep_start" class="form-control">
                                <span class="input-group-text">ถึง</span>
                                <input type="date" id="filter_prep_end" name="filter_prep_end" class="form-control">
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-center align-items-center gap-2 mt-4 pt-3 border-top">
                            <button type="button" class="btn btn-warning btn-sm text-dark fw-bold px-4" id="btn_clear_filter">
                                <i class="fas fa-undo me-1"></i> ล้างค่า
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" id="btn_search_filter" name="btn_search_filter">
                                <i class="fas fa-search me-1"></i> ค้นหา
                            </button>
                            <button type="button" class="btn btn-success btn-sm px-4 fw-bold" id="btn_export_excel" name="btn_export_excel">
                                <i class="fas fa-file-excel me-1"></i> Export Excel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end align-items-center mt-3 mb-2">
    <div class="text-muted small">
        จำนวนข้อมูลทั้งหมด : <span id="count_display" name="count_display"><i class="fas fa-spinner fa-spin"></i></span> รายการ
    </div>
</div>

<div class="table-responsive">
    <div class="table-wrapper-focus">
        <table class="table table-bordered table-hover table-custom table-striped align-middle mb-0">
            <thead style="font-size: 14px;">
                <tr class="text-nowrap text-center">
                    <th style="min-width: 100px;">Action</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 50px;">ลำดับ</th>
                    <th style="min-width: 110px;">วันที่เตรียม</th>
                    <th style="min-width: 200px;">รายการสารที่เตรียม</th>
                    <th class="d-none d-md-table-cell" style="min-width: 180px;">หน่วยงาน</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 180px;">สารตั้งต้น [Lot]</th>
                    <th style="min-width: 130px;">ปริมาณคงเหลือ / ที่เตรียม</th>
                    <th class="d-none d-md-table-cell" style="min-width: 110px;">วันหมดอายุ</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 150px;">ผู้เตรียม</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 100px;">สถานะ</th>
                </tr>
            </thead>

            <tbody id="table_body" style="font-size: 14px;">
            </tbody>
        </table>
    </div>
</div>

<div class="row col-md-12 d-flex justify-content-center mt-3">
    <nav aria-label="Page navigation mt-3">
        <ul id="pagination-list" class="pagination justify-content-center"></ul>
    </nav>
</div>

<!-- Modal สำหรับเพิ่มรายการสารเคมีใหม่ / แก้ไขรายการเดิม -->
<div class="modal fade" id="addEditPrepModal" aria-labelledby="addEditPrepModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addEditPrepModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> <span id="textModal">เพิ่มรายการ</span>
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" id="chemicalPrepForm" action="./api/Chemical_preparation/save.php" novalidate>
                    <input type="hidden" id="edit_id" name="id">

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border mb-3">
                        <legend class="fieldset-header">
                            <i class="fas fa-box-open me-1"></i> สูตรผสมสารเคมีตั้งต้น
                        </legend>

                        <div id="ingredients_container" class="d-flex flex-column gap-3 mb-3">
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-primary fw-bold py-2 w-100 shadow-sm" id="btn_add_ingredient">
                                    <i class="fas fa-plus-circle me-1"></i> เพิ่มสารตั้งต้นในสูตรผสม
                                </button>
                                <div class="invalid-feedback text-center mt-2" id="ingredients_validation_msg">กรุณาระบุสารตั้งต้นอย่างน้อย 1 รายการ</div>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">
                            <i class="fas fa-vial me-1"></i> รายละเอียดสารเคมีที่เตรียมได้
                        </legend>
                        <div class="row g-3 justify-content-start">

                            <div class="col-12">
                                <label for="prep_name" class="form-label fw-bold">ชื่อสารเคมีที่เตรียม <span class="text-danger">*</span></label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="prep_name"
                                        name="prep_name"
                                        class="form-control"
                                        required
                                        placeholder="ระบุชื่อสารที่เตรียมเสร็จ เช่น Luminol Working Solution 1:1">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="prep_name"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุชื่อสารที่เตรียม</div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-6">
                                <label for="prep_date" class="form-label fw-bold">วันที่เตรียม <span class="text-danger">*</span></label>
                                <input type="date" id="prep_date" name="prep_date" class="form-control" required>
                                <div class="invalid-feedback">กรุณาระบุวันที่เตรียม</div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-6">
                                <label for="exp_date" class="form-label fw-bold">วันหมดอายุ <span class="text-danger">*</span></label>
                                <input type="date" id="exp_date" name="exp_date" class="form-control" required>
                                <div class="invalid-feedback">กรุณาระบุวันหมดอายุ (ต้องหลังวันที่เตรียม)</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="prep_quantity" class="form-label fw-bold">ปริมาณที่เตรียมได้ <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text"
                                        id="prep_quantity"
                                        name="prep_quantity"
                                        class="form-control"
                                        inputmode="decimal"
                                        required
                                        placeholder="เช่น 500.00"
                                        oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');">

                                    <select id="unit_id" name="unit_id" class="form-select" required>
                                        <option value=""></option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="preparer_id" class="form-label fw-bold">ผู้เตรียม <span class="text-danger">*</span></label>
                                <select id="preparer_id" name="preparer_id" class="form-select select2-modal-user" required data-placeholder="-- ค้นหาชื่อเจ้าหน้าที่ผู้เตรียม --">
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาระบุผู้เตรียม</div>
                            </div>

                            <div class="col-12">
                                <label for="location_stored" class="form-label fw-bold">สถานที่เก็บสารที่เตรียม</label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="location_stored"
                                        name="location_stored"
                                        class="form-control"
                                        placeholder="เช่น ตู้เย็น 01, ชั้นวาง B, ตู้เก็บสารเตรียมเสร็จ">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="location_stored"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="remark" class="form-label fw-bold">หมายเหตุ</label>
                                <div class="input-group input-group-seamless">
                                    <textarea
                                        id="remark"
                                        name="remark"
                                        class="form-control"
                                        rows="2"
                                        placeholder="ระบุรายละเอียดเพิ่มเติม (ถ้ามี) เช่น สภาวะการเก็บรักษา, ความเข้มข้นที่เตรียมได้"></textarea>
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="remark"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-end bg-white border-top shadow-sm">
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="chemicalPrepForm">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail สำหรับดูรายละเอียดทั้งหมด -->
<div class="modal fade" id="viewPrepDetailModal" tabindex="-1" aria-labelledby="viewPrepDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold" id="viewPrepDetailModalLabel">
                    <i class="fas fa-search me-2"></i> รายละเอียดการเตรียมสารเคมี
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">

                    <div class="col-12">
                        <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                            <i class="fas fa-box-open me-1"></i> สูตรผสมสารเคมี
                        </h6>

                        <div id="view_ingredients_container" class="d-flex flex-column gap-2 mb-2">
                        </div>
                    </div>

                    <div class="col-12">
                        <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                            <i class="fas fa-vial me-1"></i> รายละเอียดสารเคมีที่เตรียมได้
                        </h6>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="text-muted small d-block">ชื่อสารเคมีที่เตรียม</label>
                                <span id="view_prep_name" class="fw-bold">-</span>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="text-muted small d-block">หน่วยงาน</label>
                                <span id="view_department_name" class="fw-bold">-</span>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="text-muted small d-block">สถานที่จัดเก็บ</label>
                                <span id="view_location_stored" class="fw-bold">-</span>
                            </div>

                            <div class="col-6">
                                <label class="text-muted small d-block">ปริมาณที่เตรียมได้</label>
                                <span class="fw-bold">
                                    <span id="view_prep_quantity">-</span> <span class="view_prep_unit_label_1">-</span>
                                </span>
                            </div>
                            <div class="col-6">
                                <label class="text-muted small d-block">ปริมาณคงเหลือปัจจุบัน</label>
                                <span class="fw-bold">
                                    <span id="view_quantity_remaining">-</span> <span class="view_prep_unit_label_2">-</span><span id="view_status_badge" class="ms-2" style="font-size: 1em;"></span>
                                </span>
                            </div>
                            <div class="col-6">
                                <label class="text-muted small d-block">ผู้เตรียม</label>
                                <span id="view_preparer_name" class="fw-bold">-</span>
                            </div>

                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row g-4">
                                <div class="col-12 col-lg-7">
                                    <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                                        <i class="fas fa-comment-dots me-1"></i> หมายเหตุ
                                    </h6>
                                    <div class="px-3 py-2 bg-light rounded border border-dashed text-secondary" style="min-height: 90px;">
                                        <span id="view_remark" class="small" style="white-space: pre-wrap; line-height: 1.6;">-</span>
                                    </div>
                                </div>

                                <div class="col-12 col-lg-5">
                                    <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                                        <i class="far fa-calendar-alt me-1"></i> วันที่สำคัญ
                                    </h6>
                                    <div class="p-3 bg-white rounded border shadow-sm">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small">วันที่เตรียม:</span>
                                            <span id="view_prep_date" class="fw-bold text-dark">-</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted small">วันหมดอายุ:</span>
                                            <span id="view_exp_date">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-3">
                        <div class="alert alert-secondary border-0 p-3 mb-0 shadow-sm">
                            <div class="row small text-muted align-items-center">
                                <div class="col-sm-6 border-end border-white">
                                    <div class="d-flex align-items-center" style="min-height: 45px;">
                                        <i class="fas fa-user-plus fa-lg text-secondary me-3"></i>
                                        <div>
                                            <label class="d-block mb-0 fw-bold" style="font-size: 0.7rem;">สร้างโดย:</label>
                                            <span id="view_create_by" class="text-dark d-block" style="font-size: 0.85rem;">-</span>
                                            <span id="view_create_date" class="italic" style="font-size: 0.75rem;">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 ps-4">
                                    <div class="d-flex align-items-center" style="min-height: 45px;">
                                        <i class="fas fa-user-pen fa-lg text-secondary me-3"></i>
                                        <div>
                                            <label class="d-block mb-0 fw-bold" style="font-size: 0.7rem;">แก้ไขล่าสุดโดย:</label>
                                            <span id="view_update_by" class="text-dark d-block" style="font-size: 0.85rem;">-</span>
                                            <span id="view_update_date" class="italic" style="font-size: 0.75rem;">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-danger px-4 rounded shadow-sm" data-bs-dismiss="modal">
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
    window._sessionUserId = '<?= $_SESSION["user_id"] ?>';
    window._canManage = <?= $canManage ? 'true' : 'false' ?>;

    // ตัวแปร Global สำหรับคุมลำดับแถว และแคชหน่วยนับ
    let ingredientRowIndex = 0;
    let unitMapping = {};
    let globalUnitsData = [];

    $(document).ready(function() {
        if ($('#filter_department_id').length) {
            loadDepartments();
        }

        // โหลดหน่วยนับให้เสร็จก่อน แล้วค่อยเริ่มโหลดข้อมูลตาราง
        initChemicalUnits().then(() => {
            searchPage(1);
        });


        // ผูก Event Click ให้ปุ่ม "เพิ่มสารตั้งต้นในสูตรผสม"
        $('#btn_add_ingredient').on('click', function() {
            addIngredientRow();
        });

        $(document).on('click', '.btn-remove-ingredient', function() {
            $(this).closest('.ingredient-row').remove();
            reorderRowLabels(); // ให้รันรีรันจัดป้ายลำดับตัวเลขใหม่เรียง 1, 2, 3 ให้สวยงามพริ้วๆ
            validateIngredientsCount();

            // เมื่อลบแถวออก (โดยเฉพาะแถวแรกที่ถูกเลื่อนขึ้นมาแทนที่) ให้รีเฟรชสถานะตัวเลือก Select2 ทันที
        refreshAllIngredientSelect2();
        });

        $('#filter_chemical_id').select2({
                theme: 'bootstrap-5',
                placeholder: '-- เลือกสารต้นทาง --',
                allowClear: true,
                width: '100%',
                ajax: {
                    url: './api/Master_chemical_list/getSelect2Data.php',
                    dataType: 'json',
                    data: function(params) {
                        return {
                            q: params.term // ส่งค่าที่พิมพ์ไปที่ตัวแปร q
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.results
                        };
                    },
                    cache: true
                }
            })
            .next('.select2-container')
            .addClass('select2-sm-custom');

        $('.select2-filter').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                placeholder: '-- ทั้งหมด --',
                ajax: {
                    url: './api/get_users.php',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function(response) {
                        // response คือก้อน JSON ทั้งหมดจาก PHP
                        // ต้อง map ข้อมูลจาก user_id -> id และ fullname -> text
                        return {
                            results: $.map(response.data, function(item) {
                                return {
                                    id: item.user_id,
                                    text: item.fullname
                                }
                            })
                        };
                    },
                    cache: true
                }
            }).next('.select2-container')
            .addClass('select2-sm-custom');

        $('.select2-dept').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            placeholder: '-- ทั้งหมด --'
        }).next('.select2-container').addClass('select2-sm-custom');

        $('.select2-modal-user').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            placeholder: "-- เลือกเจ้าหน้าที่ --",
            dropdownParent: $('#addEditPrepModal'),
            ajax: {
                url: './api/get_users.php',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term
                    };
                },
                processResults: function(response) {
                    // response คือก้อน JSON ทั้งหมดจาก PHP
                    // ต้อง map ข้อมูลจาก user_id -> id และ fullname -> text
                    return {
                        results: $.map(response.data, function(item) {
                            return {
                                id: item.user_id,
                                text: item.fullname
                            }
                        })
                    };
                },
                cache: true
            }
        });

        // ดักจับเมื่อมีการเปลี่ยนวันที่ ทั้งช่องวันที่เตรียม และ วันหมดอายุ
        $('#prep_date, #exp_date').on('change', function() {
            validatePrepDates();
        });


        // เมื่อมีการเปลี่ยน "วันที่เตรียม"
        $(document).on('change', '#prep_date', function() {
            const newExpDate = calculateExpDate($(this).val());
            $('#exp_date').val(newExpDate);
        });

        function loadDepartments() {
            $.ajax({
                url: './api/get_departments.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        let options = '<option value=""></option>';
                        response.data.forEach(function(dept) {
                            options += `<option value="${dept.id}">${dept.department_name}</option>`;
                        });

                        // เติมข้อมูลลงใน Filter หน่วยงาน
                        $('#filter_department_id').html(options).trigger('change');
                    }
                },
                error: function() {
                    console.error("ไม่สามารถโหลดข้อมูลหน่วยงานได้");
                }
            });
        }

        // 3. ฟังก์ชันโหลดหน่วยนับจาก API (ฉบับสมบูรณ์)
        function initChemicalUnits() {
            return $.ajax({
                url: './api/get_chemical_units.php',
                method: 'GET',
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        let $select = $('#unit_id');
                        $select.empty().append('<option value="" selected disabled>-- เลือกหน่วย --</option>');

                        let groups = {};
                        unitMapping = {};

                        res.data.forEach(unit => {
                            if (!groups[unit.unit_group]) groups[unit.unit_group] = [];
                            groups[unit.unit_group].push(unit);

                            // ปั้นชื่อแสดงผล: Packaging = ชื่ออังกฤษ / อื่นๆ = ตัวย่อ
                            let isPkg = unit.unit_group.includes('บรรจุภัณฑ์') || unit.unit_group.includes('Packaging');
                            let symbol = isPkg ? unit.unit_name_en : unit.unit_symbol;
                            unitMapping[unit.id] = `${unit.unit_name_th} (${symbol})`;
                        });

                        for (let groupName in groups) {
                            let $optgroup = $('<optgroup>').attr('label', groupName);
                            groups[groupName].forEach(unit => {
                                let isPkg = unit.unit_group.includes('บรรจุภัณฑ์') || unit.unit_group.includes('Packaging');
                                let symbol = isPkg ? unit.unit_name_en : unit.unit_symbol;
                                $optgroup.append(`<option value="${unit.id}">${unit.unit_name_th} (${symbol})</option>`);
                            });
                            $select.append($optgroup);
                        }

                        // Initialize Select2 หลังจากวาด Option เสร็จ
                        $select.select2({
                            theme: 'bootstrap-5',
                            dropdownParent: $('#addEditPrepModal'),
                            allowClear: true,
                            placeholder: "-- เลือกหน่วย --",
                        });
                    }
                }
            });
        }
    });

    // ฟังก์ชันเช็คการล็อกหน่วยงาน (ส่ง Current Element มาด้วย เพื่อไม่ให้ช่องที่เลือกแล้วถูกบีบตัวเลือกของตัวเอง)
    function getLockedDepartmentId(currentElement = null) {
        let lockedDeptId = '';

        $('#ingredients_container .ingredient-row').each(function() {
            let $select = $(this).find('.ingredient-inventory');
            
            // ข้ามช่องปัจจุบันที่กำลังกดค้นหาอยู่ (ถ้ามี)
            if (currentElement && $select[0] === $(currentElement)[0]) {
                return true; // continue loop
            }

            let selectData = $select.select2('data')[0];
            if (selectData && selectData.department_id) {
                lockedDeptId = selectData.department_id;
                return false; // เจอตัวอื่นที่เลือกไว้แล้ว เอาอันนี้มาล็อกเลยแล้วเบรก
            }
        });

        return lockedDeptId;
    }

    // ฟังก์ชันสั่งรีเฟรชตัวเลือกใน Select2 ทุกแถวให้สอดคล้องกับการล็อคหน่วยงานปัจจุบัน
    function refreshAllIngredientSelect2() {
        $('#ingredients_container .ingredient-inventory').each(function() {
            // ถ้าแถวไหนยังไม่ได้เลือกค่า ให้ทำการ trigger หรอเคลียร์เพื่อให้มันโหลดตัวเลือกใหม่ตามเงื่อนไขปัจจุบัน
            if (!$(this).val()) {
                $(this).val(null).trigger('change');
            }
        });
    }

    // ฟังก์ชันไดนามิกสร้างบล็อกแถวสารตั้งต้น (ฉบับล็อคหน่วยนับอัตโนมัติ + กล่องข้อความสไตล์เดิม)
    function addIngredientRow(data = null) {
        let idx = ingredientRowIndex++;

        // คำนวณเลขลำดับแสดงผลหน้ากล่อง (เช่น 1., 2., 3.)
        let displayNo = $('#ingredients_container .ingredient-row').length + 1;

        let blockHtml = `
            <div class="ingredient-row bg-white p-3 rounded-3 border shadow-sm mb-3" data-index="${idx}">
                
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <h6 class="text-primary fw-bold mb-0">
                        <i class="fas fa-flask me-2"></i>รายการที่ <span class="row-number-label">${displayNo}</span>
                    </h6>
                    <button type="button" class="btn btn-outline-danger btn-sm border-0 btn-remove-ingredient">
                        <i class="fas fa-times me-1"></i> ลบรายการนี้
                    </button>
                </div>

                <div class="row g-3 align-items-start">
                    
                    <div class="col-12 col-md-7">
                        <label class="form-label text-muted small mb-1 fw-bold">เลือกรายการสารเคมี และ Lot Number <span class="text-danger">*</span></label>
                        <select name="ingredients[${idx}][inventory_id]" class="form-select ingredient-inventory" required data-placeholder="-- ค้นหาชื่อสารเคมี หรือเลข Lot จากคลัง --">
                            <option value=""></option>
                        </select>
                        <div class="stock-feedback-box form-text text-primary mt-2 small" style="display:none; line-height: 1.5;"></div>
                        <div class="invalid-feedback">กรุณาเลือกสารเคมีจากคลัง</div>
                    </div>

                    <div class="col-12 col-md-5">
                        <label class="form-label text-muted small mb-1 fw-bold">ปริมาณที่เบิกมาใช้ <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="ingredients[${idx}][amount_used]" class="form-control ingredient-amount" inputmode="decimal" required placeholder="เช่น 100.00">
                            <span class="input-group-text ingredient-unit-span bg-light">-</span>
                        </div>
                        <div class="invalid-feedback">กรุณาระบุปริมาณการใช้งาน</div>
                        <input type="hidden" name="ingredients[${idx}][unit_id]" class="ingredient-unit-id">
                    </div>
                </div>
            </div>
        `;

        $('#ingredients_container').append(blockHtml);
        let $row = $(`.ingredient-row[data-index="${idx}"]`);

        // เรียกใช้งาน Select2 คลังสินค้าในแถวนั้นๆ
        $row.find('.ingredient-inventory').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#addEditPrepModal'),
            width: '100%',
            allowClear: true,
            ajax: {
                url: './api/Chemical_inventory/getInventorySelect2.php',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term,
                        department_id: getLockedDepartmentId(this) // ส่งตัวมันเองเข้าไปเช็คด้วย
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.results
                    };
                },
                cache: true
            }
        });

        // ดักจับเหตุการณ์ตอน User เลือกสารเคมีต้นทางรายบรรทัด
        $row.find('.ingredient-inventory').on('select2:select', function(e) {
            let item = e.params.data;
            let selectedChemId = item.chemical_id; // ใช้ ID ของสารเคมี (ไม่ใช่ ID ของ Lot)
            let isDuplicate = false;

            // ตรวจสอบการเลือกรายการซ้ำซ้อนข้ามแถว
            $('#ingredients_container .ingredient-inventory').not(this).each(function() {
                let otherItem = $(this).select2('data')[0];
                if (otherItem && otherItem.chemical_id == selectedChemId) {
                    isDuplicate = true;
                    return false; // จบลูป
                }
            });

            if (isDuplicate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'มีสารเคมีนี้ในรายการแล้ว',
                    text: 'คุณได้เลือกสารเคมีตัวนี้ไว้แล้วในแถวอื่น หากต้องการเปลี่ยน Lot ให้แก้ไขแถวเดิมแทนการเพิ่มแถวใหม่ครับ',
                    confirmButtonColor: '#3085d6'
                });
                $(this).val(null).trigger('change');
                $row.find('.stock-feedback-box').hide();
                $row.find('.ingredient-unit-span').text('-');
                $row.find('.ingredient-unit-id').val('');

                // ถ้าล้างตัวแรกออกแล้วไม่มีแถวอื่นคุม ให้รีเฟรชค่าการล็อก
                refreshAllIngredientSelect2();
                return;
            }

            // บังคับแนบ department_id ลงไปใน Element ของ Select2 เพื่อให้ฟังก์ชันกลางเรียกใช้งานได้
            let $element = $(this).find(':selected');
            // อัปเดตข้อมูล Data ของ Option ปัจจุบันให้มี department_id ชัดเจน
            let currentData = $(this).select2('data')[0];
            if (currentData) {
                currentData.department_id = item.department_id;
            }

            // เมื่อมีการเลือกแถวแรกสำเร็จ ให้สั่งรีเฟรชตัวเลือกช่องอื่น ๆ ให้กรองตามหน่วยงานนี้ทันที
            refreshAllIngredientSelect2();

            let $amtInput = $row.find('.ingredient-amount');

            // แยกปั้นข้อความหน่วยนับดึงจากออบเจกต์หลังบ้าน
            let unitShow = item.unit_display || 'หน่วย';
            let cleanLot = item.lot_number || item.text.split('[')[0].replace('Lot: ', '').trim();

            // หยอดหน่วยนับลงแผ่นป้าย Span และผูก ID ลง Hidden Input ทันทีโดย User ไม่ต้องเลือกเอง
            $row.find('.ingredient-unit-span').text(unitShow);
            $row.find('.ingredient-unit-id').val(item.unit_id || '');

            // พักสถิติไว้ที่ข้อมูลตัวแปร Data Attribute รายแถว
            $amtInput.data('current-stock', parseFloat(item.remain || 0));
            $amtInput.data('original-used', 0);
            $amtInput.data('unit', unitShow);
            $amtInput.data('source-info', `${item.chem_name} (Lot: ${cleanLot})`);

            updateRowStockFeedback($row);
            $amtInput.focus();
        });

        // ดักจับ Event ตอนกดล้างข้อมูลใน select2 (Clear)
        $row.find('.ingredient-inventory').on('select2:unselect select2:clear', function() {
            let $amtInput = $row.find('.ingredient-amount');
            $row.find('.ingredient-unit-span').text('-');
            $row.find('.ingredient-unit-id').val('');
            $amtInput.val('').data('source-info', '').removeClass('is-invalid');
            $row.find('.stock-feedback-box').hide();

            // เมื่อเคลียร์ค่าออก ให้รีเฟรชเพื่อให้กลับมาแสดงรายการทั้งหมดตามเดิม
            refreshAllIngredientSelect2();
        });

        $row.find('.ingredient-amount').on('input', function() {
            let val = $(this).val();
            // ดักเอาเฉพาะตัวเลขและทศนิยมจุดเดียวตัวเดิมของพี่
            val = val.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');
            $(this).val(val);
            updateRowStockFeedback($row);
        });

        // Mapping ข้อมูลเก่าขึ้นฟอร์ม (กรณีโหมดแก้ไข)
        if (data) {
            // 1. ปั้นข้อความโชว์ให้เหมือน Select2 ต้นฉบับ
            let sourceText = `${data.chemical_name} | Lot: ${data.lot_number} [ คงเหลือ: ${numberWithCommas(data.quantity_remaining)} ${data.unit_display} ]`;
            let invOpt = new Option(sourceText, data.inventory_id, true, true);

            // 2. ยัด Data แฝง (รวมถึง chemical_id) เข้าไปใน Option ด้วย เพื่อให้ฟังก์ชันดักซ้ำทำงานได้สมบูรณ์
            $(invOpt).data('data', {
                id: data.inventory_id,
                text: sourceText,
                chemical_id: data.chemical_id,
                chem_name: data.chemical_name,
                lot_number: data.lot_number,
                remain: data.quantity_remaining,
                unit_display: data.unit_display,
                department_id: data.department_id 
            });

            $row.find('.ingredient-inventory').append(invOpt).trigger('change');

            // บรรจุข้อความหน่วยนับแฝง
            $row.find('.ingredient-unit-span').text(data.unit_display || 'หน่วย');
            $row.find('.ingredient-unit-id').val(data.unit_id || '');

            let $amtInput = $row.find('.ingredient-amount');
            $amtInput.val(data.amount_used);

            // 3. ดึงยอดสต็อกจาก data.quantity_remaining (ไม่ใช่ source_remain ตัวเก่า)
            $amtInput.data('original-used', parseFloat(data.amount_used || 0));
            $amtInput.data('current-stock', parseFloat(data.quantity_remaining || 0));

            $amtInput.data('unit', data.unit_display);

            // source-info เอาไว้โชว์ใต้ช่องกรอกปริมาณ (เอาแค่ชื่อกับล็อตพอ)
            $amtInput.data('source-info', `${data.chemical_name} (Lot: ${data.lot_number})`);

            updateRowStockFeedback($row);
        }
        validateIngredientsCount();
    }

    // ฟังก์ชันคำนวณยอดสต็อกรายบล็อก พร้อมถอดรหัสพ่นโครงสร้าง HTML แบบตัวเดิมเป๊ะๆ
    function updateRowStockFeedback($row) {
        let $amtInput = $row.find('.ingredient-amount');
        let $feedback = $row.find('.stock-feedback-box');

        let originalUsed = parseFloat($amtInput.data('original-used')) || 0;
        let currentStock = parseFloat($amtInput.data('current-stock')) || 0;
        let currentInput = parseFloat($amtInput.val()) || 0;
        let unitLabel = $amtInput.data('unit') || '';

        if (!$amtInput.data('source-info')) {
            $feedback.hide();
            return;
        }

        let totalAvailable = currentStock + originalUsed;
        let realTimeRemain = totalAvailable - currentInput;
        let isOver = realTimeRemain < 0;

        // เปลี่ยนสลับคลาสสีแดง/น้ำเงิน ตามเกณฑ์การเบิกสต็อกเกินสิทธิ์
        $feedback.removeClass('text-primary text-danger').addClass(isOver ? 'text-danger' : 'text-primary');

        let labelText = (currentInput === 0) ? 'ปริมาณคงเหลือในคลังหลัก:' : 'คงเหลือในคลังหลังหักออก:';
        let warningText = isOver ? ' <small class="fw-bold">(เกินยอดที่มีในคลัง!)</small>' : '';

        // พ่นโครงสร้าง flex-column แบบพิมพ์นิยมตัวเดิมของระบบกลับมาใช้งาน
        $feedback.html(`
            <div class="d-flex flex-column">
                <span class="mb-1">
                    <i class="fas fa-box me-1"></i> ล็อตต้นทาง: <b>${$amtInput.data('source-info')}</b>
                </span>
                <span>
                    <i class="fas fa-info-circle"></i> ${labelText}
                    <b class="ms-1">${numberWithCommas(realTimeRemain)} ${unitLabel}</b>${warningText}
                </span>
            </div>
        `).show();

        if (isOver) {
            $amtInput.addClass('is-invalid');
            $amtInput[0].setCustomValidity('Invalid');
        } else {
            $amtInput.removeClass('is-invalid');
            $amtInput[0].setCustomValidity('');
        }
    }

    // ฟังก์ชันรันจัดเรียงเลขลำดับป้ายกล่องใหม่ทุกครั้งที่มีการลบแถว (เพื่อความสวยงามเรียงแถว 1,2,3 เสมอ)
    function reorderRowLabels() {
        $('#ingredients_container .ingredient-row').each(function(index) {
            $(this).find('.row-number-label').text(index + 1);
        });
    }

    // ตรวจสอบจำนวนรายการสารเคมีในสูตรผสม
    function validateIngredientsCount() {
        let count = $('.ingredient-row').length;
        if (count === 0) {
            $('#ingredients_validation_msg').show();
            $('#btn_add_ingredient').addClass('btn-outline-danger').removeClass('btn-outline-primary');
            return false;
        } else {
            $('#ingredients_validation_msg').hide();
            $('#btn_add_ingredient').addClass('btn-outline-primary').removeClass('btn-outline-danger');
            return true;
        }
    }

    // อัปเดตลอจิกภายในฟังก์ชัน viewPrepDetail(id) ตรงจุดที่ได้รับ Response Success
    function viewPrepDetail(id) {
        $.ajax({
            url: './api/Chemical_preparation/getDataByID.php',
            method: 'GET',
            data: {
                id: id
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let d = res.data;
                    // --- 1. รายละเอียดสารเคมีปลายทางที่เตรียมได้ (Result) ---
                    $('#view_prep_name').text(d.prep_name || '-');
                    $('#view_department_name').text(d.department_name || '-');
                    $('#view_preparer_name').text(d.preparer_fullname || '-');
                    $('#view_prep_quantity').text(numberWithCommas(d.prep_quantity));
                    $('#view_location_stored').text(d.location_stored || '-');
                    $('#view_remark').text(d.remark || '-');
                    $('.view_prep_unit_label_1').text(d.unit_display || '-');
                    $('.view_prep_unit_label_2').text(d.unit_display || '-');

                    $('#view_quantity_remaining').text(numberWithCommas(d.quantity_remaining));
                    $('#view_prep_date').text(d.prep_date_show || '-');

                    // เริ่มต้น Wrapper กล่องหลัก
                    let ingredientsHtml = `
                        <div class="bg-light p-3 rounded-3 border border-dashed shadow-sm">
                            <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                                <i class="fas fa-list-ul me-1"></i> รายการสารเคมีตั้งต้น
                            </h6>`;

                    if (d.ingredients && d.ingredients.length > 0) {
                        d.ingredients.forEach(function(ing, index) {
                            // เพิ่มเงื่อนไขใส่เส้น border-bottom ยกเว้นตัวสุดท้าย
                            let borderClass = (index < d.ingredients.length - 1) ? 'border-bottom border-secondary-subtle pb-2 mb-2' : '';

                            ingredientsHtml += `
                                <div class="row align-items-center ${borderClass}">
                                    <div class="col-1 text-center">
                                        <span class="badge bg-secondary text-white rounded-circle" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                                            ${index + 1}
                                        </span>
                                    </div>
                                    
                                    <div class="col-5">
                                        <div class="fw-bold text-dark">${ing.chemical_name}</div>
                                        <small class="text-muted">${ing.chemical_brand || '-'}</small>
                                    </div>
                                    
                                    <div class="col-3">
                                        <small class="text-muted d-block small" style="font-size: 10px;">Lot Number</small>
                                        <span class="fw-bold text-dark">${ing.lot_number}</span>
                                    </div>
                                    
                                    <div class="col-3 text-end">
                                        <small class="text-muted d-block small" style="font-size: 10px;">ปริมาณ</small>
                                        <span class="fw-bold text-primary">${numberWithCommas(ing.amount_used)} ${ing.unit_display}</span>
                                    </div>
                                </div>`;
                        });
                    } else {
                        ingredientsHtml += `
                        <div class="text-center py-2 text-muted small">
                            ไม่พบข้อมูลสารเคมีตั้งต้น
                        </div>`;
                    }

                    // ปิด Wrapper
                    ingredientsHtml += `</div>`;

                    // สั่งพ่น HTML ชุดการ์ดเข้าสู่คอนเทนเนอร์หลักหน้าจอ
                    $('#view_ingredients_container').html(ingredientsHtml);

                    // --- 3. ตรรกะตรวจสอบการแสดงสีวันหมดอายุคลังและ Metadata ด้านล่าง (คงเดิม) ---
                    const qtyRemain = parseFloat(d.quantity_remaining || 0);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    let diffDays = null;
                    let isExpired = false;
                    let isNearExpiry = false;

                    if (d.exp_date_raw) {
                        const expDate = new Date(d.exp_date_raw);
                        expDate.setHours(0, 0, 0, 0);
                        diffDays = Math.ceil((expDate - today) / (1000 * 60 * 60 * 24));
                        isExpired = (diffDays < 0);
                        isNearExpiry = (diffDays >= 0 && diffDays <= 14);
                    }

                    const $remainVal = $('#view_quantity_remaining');
                    const $remainUnit = $('.view_prep_unit_label_2'); // ตัวที่ 2 (คงเหลือ)
                    const $expDisplay = $('#view_exp_date');
                    const $statusBadge = $('#view_status_badge'); // เรียกใช้งาน span ที่เราสร้างใหม่
                    const allColorClasses = 'text-primary text-danger text-warning text-success text-secondary text-dark fw-bold';
                    $remainVal.removeClass(allColorClasses);
                    $remainUnit.removeClass('text-primary text-danger text-warning text-success text-secondary');
                    $statusBadge.empty().removeClass(allColorClasses); // เคลียร์ข้อความสถานะเดิม
                    $expDisplay.removeClass(allColorClasses + ' text-dark').empty();

                    if (qtyRemain <= 0) {
                        $remainVal.addClass('text-secondary fw-bold');
                        $remainUnit.addClass('text-secondary');
                        $expDisplay.addClass('text-secondary fw-bold').html(`<i class="fas  fa-archive me-1"></i> ${d.exp_date_show}`);
                        $statusBadge.addClass('text-secondary').text('(ใช้หมดแล้ว)')
                    } else if (isExpired) {
                        $remainVal.addClass('text-danger fw-bold');
                        $remainUnit.addClass('text-danger');
                        $expDisplay.addClass('text-danger fw-bold').html(`<i class="fas fa-times-circle me-1"></i> ${d.exp_date_show}`);
                        $statusBadge.addClass('text-danger').text('(หมดอายุ)');
                    } else if (isNearExpiry) {
                        $remainVal.addClass('text-warning fw-bold');
                        $remainUnit.addClass('text-warning');
                        let dText = diffDays === 0 ? 'วันนี้' : `${diffDays} วัน`;
                        $expDisplay.addClass('text-warning fw-bold').html(`<i class="fas fa-exclamation-triangle me-1"></i> ${d.exp_date_show} <small>(${dText})</small>`);
                        $statusBadge.addClass('text-warning').text('(ใกล้หมดอายุ)');
                    } else {
                        $remainVal.addClass('text-success fw-bold');
                        $remainUnit.addClass('text-success');
                        $expDisplay.addClass('text-dark fw-bold').text(d.exp_date_show);
                    }

                    $('#view_create_by').text(d.fullname_create || '-');
                    $('#view_create_date').text(d.createdate_show || '-');
                    $('#view_update_by').text(d.fullname_update || '-');
                    $('#view_update_date').text(d.updatedate_show || '');

                    // สั่งเปิดฉายหน้าต่างดีเทลตัวงาน
                    $('#viewPrepDetailModal').modal('show');
                }
            }
        });
    }


    // ฟังก์ชันช่วยคำนวณวันที่ (Prep Date + 3 Days)
    function calculateExpDate(prepDateVal) {
        if (!prepDateVal) return '';

        let result = new Date(prepDateVal);
        result.setDate(result.getDate() + 3); // 

        // แปลงกลับเป็น Format YYYY-MM-DD สำหรับ input[type="date"]
        let year = result.getFullYear();
        let month = String(result.getMonth() + 1).padStart(2, '0');
        let day = String(result.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    // --- ฟังก์ชันตรวจสอบความถูกต้องของวันที่ (แยกออกมาภายนอก) ---
    function validatePrepDates() {
        const prepInput = document.getElementById('prep_date');
        const expInput = document.getElementById('exp_date');

        const prepDateVal = prepInput.value;
        const expDateVal = expInput.value;

        // 1. Reset State
        $(prepInput).removeClass('is-invalid');
        $(expInput).removeClass('is-invalid');
        prepInput.setCustomValidity('');
        expInput.setCustomValidity('');

        // 2. ตรวจสอบเงื่อนไข: วันหมดอายุ ต้องมากกว่า วันที่เตรียม
        if (prepDateVal && expDateVal) {
            if (new Date(expDateVal) <= new Date(prepDateVal)) {
                // ถ้าผิดกฎ: วันหมดอายุต้องไม่อยู่ก่อนหรือเท่ากับวันที่เตรียม
                expInput.setCustomValidity('Invalid');
                $(expInput).addClass('is-invalid');
            } else {
                // ถ้าถูกต้องแล้ว: มั่นใจว่าล้างสถานะแดงออกแน่นอน
                expInput.setCustomValidity('');
                $(expInput).removeClass('is-invalid');
            }
        }
    }

    // --- ฟังก์ชันล้างสถานะฟอร์มเบื้องต้น ---
    function resetPrepFormState() {
        const $form = $('#chemicalPrepForm');
        $form[0].reset();
        $form.removeClass('was-validated');
        $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');

        // ล้าง Custom Validity ที่ค้างอยู่บน Input วันที่
        document.getElementById('prep_date').setCustomValidity('');
        document.getElementById('exp_date').setCustomValidity('');

        // ล้าง UI ที่เป็น Text/Span
        $('#source_unit_display').text('หน่วย');
        $('#stock_remain_info').empty();
    }

    /* ฟังก์ชันจัดการแสดงตัวเลข */
    function numberWithCommas(x) {
        if (x === null || x === undefined) return '0.00';
        return parseFloat(x).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Event Listener เมื่อคลิกที่แถวของตาราง
    $(document).on('click', '.clickable-row', function(e) {
        // ป้องกันไม่ให้ทำงานเมื่อคลิกโดนปุ่มจัดการ (Edit/Delete)
        if ($(e.target).closest('.js-edit-prep-btn, .js-delete-prep-btn, button').length) {
            return;
        }

        const id = $(this).data('id');
        if (id) {
            viewPrepDetail(id);
        }
    });

    // 1. Logic สำหรับปุ่ม "เพิ่มรายการ" (Add)
    $(document).on('click', '.js-add-prep-btn', function() {
        const $form = $('#chemicalPrepForm');

        // 1.1 ล้างข้อมูลและ Class ของ Bootstrap
        $form[0].reset();
        $form.removeClass('was-validated');
        // 1.2 ล้าง Manual Class (is-invalid, is-valid)
        $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');
        const inputsToClear = ['prep_date', 'exp_date', 'amount_used', 'prep_quantity'];
        inputsToClear.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.setCustomValidity('');
        });

        $('#ingredients_container').empty(); // เคลียร์ของเก่า
        ingredientRowIndex = 0;

        // 1.3 รีเซ็ต ID และ Select2 ทั้งหมด
        $('#edit_id').val('');
        $('#inventory_id, #preparer_id').val(null).trigger('change');
        $('#unit_id').val('').trigger('change');

        // 1.4 ล้างส่วนแสดงข้อมูลสต็อกต้นทาง
        $('#source_unit_display').text('หน่วย');
        $('#stock_remain_info').empty();

        // 1.5 เปลี่ยนหัวข้อ Modal
        $('#textModal').text('เพิ่มรายการ');
        $('#addEditPrepModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายการ');

        // 1.6 ตั้งผู้เตรียมเป็นชื่อผู้ใช้ที่ Login อยู่ + ตั้งวันที่เตรียมเป็น "วันนี้"
        let currentUserId = '<?php echo $current_user_id; ?>';
        let currentFullname = '<?php echo $current_user_fullname; ?>';

        if (currentUserId && currentFullname !== "-" && currentFullname !== "") {
            // สร้าง Option ใหม่และเลือกให้ทันที
            let userOption = new Option(currentFullname, currentUserId, true, true);
            $('#preparer_id').append(userOption).trigger('change');
        }

        let today = new Date().toISOString().split('T')[0];
        $('#prep_date').val(today);

        const defaultExpDate = calculateExpDate(today);
        $('#exp_date').val(defaultExpDate);

        $('#inventory_id').prop('disabled', false);
        $('#btn_save_all').prop('disabled', false).attr('title', '');

        // แอดแถวแรกเสมอ
        addIngredientRow();
    });

    // 2. Logic สำหรับปุ่ม "แก้ไข" (Edit)
    $(document).on('click', '.js-edit-prep-btn', function() {
        let dataId = $(this).data('id');

        $.ajax({
            url: './api/Chemical_preparation/getDataByID.php',
            type: 'GET',
            data: {
                id: dataId
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let d = res.data;
                    const $form = $('#chemicalPrepForm');
                    // const $amountInput = $('#amount_used');

                    // 2.1 เปลี่ยนหัวข้อ Modal
                    $('#textModal').text('แก้ไขข้อมูลการเตรียมสารเคมี');
                    $('#addEditPrepModalLabel').html('<i class="fas fa-edit fa-lg me-2"></i> แก้ไขข้อมูลการเตรียมสารเคมี');
                    $form.removeClass('was-validated');
                    $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');

                    // 2.2 Map ข้อมูลลง Form (ส่วนของผลลัพธ์)
                    $('#edit_id').val(d.id);
                    $('#prep_name').val(d.prep_name);
                    $('#prep_date').val(d.prep_date_raw);
                    $('#exp_date').val(d.exp_date_raw);
                    $('#prep_quantity').val(d.prep_quantity);
                    $('#unit_id').val(d.unit_id).trigger('change');
                    $('#location_stored').val(d.location_stored);
                    $('#remark').val(d.remark);
                    // $amountInput.val(d.amount_used);

                    // 2.3 จัดการ Select2: ผู้เตรียม
                    if (d.preparer_id) {
                        $('#preparer_id').empty(); // ล้างค่าเก่า
                        let newOption = new Option(d.preparer_fullname, d.preparer_id, true, true);
                        $('#preparer_id').append(newOption).trigger('change');
                    }

                    // วาดแถว ingredients ที่ดึงมา
                    $('#ingredients_container').empty();
                    ingredientRowIndex = 0;
                    if (d.ingredients && d.ingredients.length > 0) {
                        d.ingredients.forEach(ing => addIngredientRow(ing));
                    } else {
                        addIngredientRow();
                    }

                    // 1. เช็คว่าสารเคมีนี้ถูกนำไปใช้งานแล้วหรือยัง
                    let prepQty = parseFloat(d.prep_quantity || 0);
                    let remainQty = parseFloat(d.quantity_remaining || 0);
                    let isUsed = remainQty < prepQty; // ถ้าน้อยกว่า แสดงว่าโดนเบิกไปใช้ทดสอบแล้ว

                    if (isUsed) {
                        // 2. ล็อคไม่ให้แก้สูตรผสม
                        $('.ingredient-inventory').prop('disabled', true); // ล็อค Select2
                        $('.ingredient-amount').prop('disabled', true) // ล็อคช่องปริมาณ

                        // 3. ซ่อนปุ่มลบรายการ และปุ่มเพิ่มรายการใหม่
                        $('.btn-remove-ingredient').hide();
                        $('#btn_add_ingredient').hide();

                        // 4. แสดงข้อความเตือนให้ User ทราบ
                        if ($('#lock_warning_msg').length === 0) {
                            $('#ingredients_container').prepend(`
                                <div id="lock_warning_msg" class="alert alert-warning small border-0 shadow-sm">
                                    <i class="fas fa-lock me-1"></i> สารเคมีนี้ถูกนำไปใช้งานแล้ว ไม่สามารถแก้ไขสูตรผสมได้
                                </div>
                            `);
                        }
                    } else {
                        // ถ้ายังไม่ถูกใช้ เคลียร์สถานะล็อค (เผื่อไว้)
                        $('#lock_warning_msg').remove();
                        $('#btn_add_ingredient').show();
                    }

                    // 2.5 ตรวจสอบความถูกต้องของวันที่หลังหยอดค่า
                    validatePrepDates();

                    $('#btn_save_all').prop('disabled', false).attr('title', '');

                    // 2.6 แสดง Modal
                    $('#addEditPrepModal').modal('show');
                }
            },
            error: function() {
                Swal.fire('Error', 'ไม่สามารถดึงข้อมูลได้', 'error');
            }
        });
    });

    // ฟังก์ชันสำหรับลบรายการเตรียมสารเคมี
    $(document).on('click', '.js-delete-prep-btn', function(e) {
        e.preventDefault();

        const dataId = $(this).data('id');
        const prepName = $(this).data('name');
        const prepDate = $(this).data('date');
        const remainQty = $(this).data('remain');
        const deptName = $(this).data('department');

        if (!dataId) {
            Swal.fire('Error', 'ไม่พบ ID ของรายการ', 'error');
            return;
        }

        Swal.fire({
            title: 'ยืนยันการลบ?',
            html: `
                <div class="text-center mb-3">คุณต้องการลบข้อมูลการเตรียมสารนี้ใช่หรือไม่?</div>
                <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">ชื่อสารที่เตรียม:</span><br>
                        <b class="ps-2 text-danger">${prepName}</b>
                    </div>
                    
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">หน่วยงาน:</span><br>
                        <b class="ps-2 text-danger">${deptName}</b>
                    </div>

                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">วันที่เตรียม:</span><br>
                        <b class="ps-2 text-danger">${prepDate}</b>
                    </div>
                    
                    <div>
                        <span class="text-secondary small">ปริมาณคงเหลือปัจจุบัน:</span><br>
                        <b class="ps-2 text-danger">${remainQty}</b>
                    </div>
                    
                </div>

                <div class="alert alert-warning small mt-3 mb-0 border-0 shadow-sm">
                    <i class="fas fa-exclamation-triangle me-1"></i> 
                    <b>ระบบจะคืนยอดสต็อกสารตั้งต้น</b> กลับเข้าสู่คลังหลักให้โดยอัตโนมัติ
                </div>

                <div class="mt-3 text-center">
                    <small class="text-muted italic" style="font-size: 0.75rem;">
                        *หมายเหตุ: หากน้ำยานี้ถูกนำไปใช้งาน (ตัดสต็อก) แล้ว จะไม่สามารถลบได้
                    </small>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> ลบและคืนยอดสต็อก',
            cancelButtonText: 'ยกเลิก',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    url: './api/Chemical_preparation/delete.php',
                    type: 'POST',
                    data: {
                        id: dataId
                    },
                    dataType: 'json'
                }).then(response => {
                    if (response.status !== 'success') {
                        throw new Error(response.message || 'เกิดข้อผิดพลาดในการลบ');
                    }
                    return response;
                }).catch(xhr => {
                    let errMsg = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errMsg = xhr.responseJSON.message;
                    }
                    Swal.showValidationMessage(`${errMsg}`);
                    return false;
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'ลบสำเร็จ!',
                    text: 'รายการเตรียมสารเคมีถูกลบและคืนสต็อกเรียบร้อยแล้ว',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false,
                    returnFocus: false
                }).then(() => {
                    // รีโหลดหน้าเดิม (รักษา Page ปัจจุบันไว้)
                    const currentPage = parseInt($('#pagination-list .active a').text()) || 1;
                    searchPage(currentPage);
                });
            }
        });
    });

    $(document).on('click', '#btn_export_excel', function(e) {
        e.preventDefault(); // กันไว้ก่อนเผื่อปุ่มอยู่ใน Form submit

        // ดึงค่าจากฟอร์มทั้งหมดมาทำเป็น Query String
        const formData = $('#searchFilterForm').serialize();

        // ยิงไปที่ API
        window.location.href = './api/Chemical_preparation/exportExcel.php?' + formData;
    });

    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    // ฟังก์ชันบันทึกข้อมูลการเตรียมสารเคมี
    $('#chemicalPrepForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        const $form = $(form);

        // 1. Custom Validation: ตรวจสอบตรรกะของวันที่ เรียกใช้ฟังก์ชันที่เราแยกไว้ เพื่อความชัวร์อีกครั้งก่อนส่ง
        validatePrepDates();

        if (!validateIngredientsCount()) {
            Swal.fire({
                icon: 'warning',
                title: 'สูตรผสมไม่ถูกต้อง',
                text: 'กรุณาระบุสารเคมีตั้งต้นในสูตรผสมอย่างน้อย 1 รายการครับ',
                confirmButtonColor: '#3085d6',
                returnFocus: false
            });
            return; // สั่งหยุดทำงาน ไม่ให้ยิง AJAX
        }

        const prepDate = $('#prep_date').val();
        const expDate = $('#exp_date').val();

        if (prepDate && expDate && new Date(expDate) <= new Date(prepDate)) {
            $(form).addClass('was-validated');
            $('#exp_date').addClass('is-invalid').focus();

            Swal.fire({
                icon: 'error',
                title: 'วันที่ไม่ถูกต้อง',
                text: 'วันหมดอายุของสารที่เตรียม ต้องอยู่หลังจากวันที่เตรียมสาร',
                confirmButtonColor: '#d33',
                returnFocus: false
            });
            return;
        }

        // 2. ตรวจสอบความถูกต้องเบื้องต้น (HTML5 Validation)
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');

            const invalidElement = findFirstInvalidInput(form);
            if (invalidElement) {
                let $invalidElement = $(invalidElement);
                let isSelect2 = $invalidElement.hasClass('select2-hidden-accessible');

                // เลื่อนไปหาจุดที่ลืมกรอก
                const elementToScroll = isSelect2 ? $invalidElement.next('.select2-container')[0] : invalidElement;
                if (elementToScroll) {
                    elementToScroll.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }

                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบถ้วน',
                    text: 'กรุณากรอกข้อมูลในช่องที่จำเป็น (เครื่องหมาย *) ให้ครบถ้วน',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#3085d6',
                    returnFocus: false
                }).then(() => {
                    if (isSelect2) {
                        $invalidElement.select2('open');
                    } else {
                        invalidElement.focus();
                    }
                });
            }
            return;
        }

        // 1. เช็คว่าเป็นโหมดสร้างใหม่หรืออัปเดต (เพื่อใช้จัดเลขหน้าตอนจบ)
        const editId = $('#edit_id').val();
        const isUpdate = editId !== "";

        // 2. ปลดล็อคฟิลด์ที่โดน Disabled ชั่วคราวเพื่อให้ FormData ดูดค่าได้ครบ
        const $disabledFields = $form.find(':disabled');
        $disabledFields.prop('disabled', false);

        const formData = new FormData(form);

        // ล็อคกลับคืนทันทีเพื่อป้องกัน User กดซ้ำ
        $disabledFields.prop('disabled', true);

        // 4. ส่งข้อมูลผ่าน AJAX
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function() {
                $('#btn_save_all').prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin me-2"></i> กำลังบันทึก...');
            },
            success: function(response) {
                if (response.status == "success") {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: 'ข้อมูลการเตรียมสารเคมีถูกบันทึกเรียบร้อยแล้ว',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // ปิด Modal และรีโหลดตาราง
                        $('#addEditPrepModal').modal('hide');

                        // กลับไปหน้าปัจจุบันที่กำลังดูอยู่ หรือหน้า 1 ถ้าเป็นรายการใหม่
                        const currentPage = parseInt($('#pagination-list .active a').text()) || 1;
                        if (typeof searchPage === "function") {
                            searchPage(isUpdate ? currentPage : 1);
                        }
                    });
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', response.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                }
            },
            error: function(xhr) {
                console.error("AJAX Error:", xhr.responseText);

                // ตั้งค่า Default ไว้ก่อน
                let errorMessage = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ หรือเกิดข้อผิดพลาดภายใน';

                // ลองเช็คว่า Server ส่ง JSON กลับมาพร้อม message หรือไม่
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else {
                    // เผื่อกรณี Server ส่ง Text ธรรมดาที่ Parse ไม่เป็น JSON
                    try {
                        let response = JSON.parse(xhr.responseText);
                        if (response.message) errorMessage = response.message;
                    } catch (e) {
                        // ปล่อยให้ใช้ errorMessage เดิม
                    }
                }

                // แสดงข้อความจริงจาก Server
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: errorMessage
                });
            },
            complete: function() {
                // คืนค่าปุ่มบันทึก
                $('#btn_save_all').prop('disabled', false)
                    .html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล');
            }
        });
    });

    function searchPage(page) {
        // 1. ดึงค่าจาก Filter Form (ชื่อสารที่เตรียม, Lot ต้นทาง, ช่วงวันที่, สถานะ, ผู้เตรียม)
        const formData = $('#searchFilterForm').serializeArray();

        // เพิ่มพารามิเตอร์เลขหน้า
        formData.push({
            name: 'page',
            value: page
        });

        $.ajax({
            url: './api/Chemical_preparation/searchData.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status == 'success') {
                    // 1. วาดตารางข้อมูล
                    renderTable(response.data, response.offset);

                    // 2. แสดงจำนวนรายการทั้งหมด
                    $('#count_display').text(response.count);

                    // 3. จัดการระบบ Pagination
                    const total = parseInt(response.totalPages);
                    const current = parseInt(response.currentPage);

                    if (total > 0) {
                        setupPagination(total, current);
                    } else {
                        $('#pagination-list').empty();
                    }
                } else {
                    $('#table_body').html(`<tr><td colspan="9" class="text-center py-5 text-danger fw-bold">${response.message}</td></tr>`);
                    $('#count_display').text('0');
                }
            },
            error: function(xhr) {
                console.error("Search Error:", xhr.responseText);
                $('#table_body').html('<tr><td colspan="9" class="text-center py-5 text-danger"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์</td></tr>');
                $('#count_display').text('Error');
            }
        });
    }

    // ฟังก์ชันกลางสำหรับสร้าง Badge สถานะ (ใช้ได้ทั้ง Inventory และ Preparation)
    function getStatusBadge(expiryDateStr, qty, isPrep = false) {
        const qtyRemaining = parseFloat(qty || 0);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // 1. ถ้าของหมด (Priority 1)
        if (qtyRemaining <= 0) {
            return `<span class="badge rounded-pill border border-secondary border-opacity-25 bg-secondary bg-opacity-25 text-secondary px-3 py-2" style="font-size: 0.75rem;">
                <i class="fas fa-archive me-1"></i>ใช้หมดแล้ว</span>`;
        }

        if (!expiryDateStr || expiryDateStr === '0000-00-00') {
            return `<span class="badge rounded-pill border border-secondary bg-secondary bg-opacity-25 text-secondary px-3 py-2" style="font-size: 0.75rem;">ไม่ระบุ</span>`;
        }

        const expDate = new Date(expiryDateStr);
        expDate.setHours(0, 0, 0, 0);
        const diffTime = expDate - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        // 2. ถ้าหมดอายุแล้ว (Priority 2)
        if (diffDays < 0) {
            return `<span class="badge rounded-pill border border-danger border-opacity-50 bg-danger bg-opacity-25 text-danger px-3 py-2" style="font-size: 0.75rem;">
                <i class="fas fa-times-circle me-1"></i>หมดอายุ</span>`;
        }

        // --- 3. ถ้าใกล้หมดอายุ (Priority 3) - ใช้ isPrep กำหนดเกณฑ์การเตือน 
        // ถ้าเป็นสารเตรียมเอง (Prep) เตือนที่ 14 วัน, ถ้าเป็นของคลัง (Stock) เตือนที่ 90 วัน
        const warningThreshold = isPrep ? 14 : 90;

        if (diffDays <= warningThreshold) {
            let dayText = diffDays === 0 ? 'วันนี้' : `${diffDays} วัน`;
            // ถ้าเหลือไม่ถึง 7 วัน ให้ใช้สีแดงกระพริบหรือเน้นเป็นพิเศษ (ถ้าต้องการ)
            let urgencyClass = diffDays <= 7 ? 'text-danger fw-bold' : 'text-warning';

            return `<span class="badge rounded-pill border border-warning border-opacity-50 bg-warning bg-opacity-10 ${urgencyClass} px-3 py-2" style="font-size: 0.75rem;">
                <i class="fas fa-exclamation-triangle me-1"></i>ใกล้หมดอายุ (${dayText})</span>`;
        }

        // 4. สถานะปกติ / พร้อมใช้งาน (Priority 4)
        else {
            return `<span class="badge rounded-pill border border-success border-opacity-50 bg-success bg-opacity-25 text-success px-3 py-2" style="font-size: 0.75rem;">
                <i class="fas fa-check-circle me-1"></i>พร้อมใช้งาน</span>`;
        }
    }

    function renderTable(data, offset) {
        let html = '';
        let start_no = (isNaN(parseInt(offset))) ? 1 : parseInt(offset) + 1;

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (data && data.length > 0) {
            data.forEach(function(row, i) {

                // --- 1. เตรียมข้อมูลพื้นฐาน ---
                let prep_name = row.prep_name || '-';
                let prep_date = row.prep_date_show || '-';
                let exp_date = row.exp_date_show || '-';
                let source_chem = row.source_chemical_name || '-';
                let source_lot = row.source_lot_number || '-';
                let preparer = row.preparer_fullname || '-';
                let unitText = row.unit_display || '';
                let rawDepartmentName = row.department_name || '-';
                let departmentName = row.department_name || '<span class="text-muted small">ไม่ระบุ</span>';

                let prepQty = parseFloat(row.prep_quantity || 0);
                let qtyRemain = parseFloat(row.quantity_remaining || 0);

                // 1. ตั้งค่าเริ่มต้น
                let diffDays = null;
                let isExpired = false;
                let isNearExpiry = false;
                const warningThreshold = 14; // เกณฑ์ 14 วันสำหรับสารเตรียมเอง

                if (row.expiry_date_raw) {
                    const expDate = new Date(row.expiry_date_raw);
                    expDate.setHours(0, 0, 0, 0);
                    diffDays = Math.ceil((expDate - today) / (1000 * 60 * 60 * 24));
                    isExpired = (diffDays < 0);
                    isNearExpiry = (diffDays >= 0 && diffDays <= warningThreshold);
                }

                // --- กำหนดสี Class ---
                let remainClass = (qtyRemain <= 0) ? 'text-secondary' : (isExpired ? 'text-danger' : (isNearExpiry ? 'text-warning' : 'text-success'));
                let expiryClass = isExpired ? 'text-danger fw-bold' : (isNearExpiry ? 'text-warning fw-bold' : 'text-dark');

                // --- Logic สำหรับ Badge "สถานะ" แบบจัดลำดับความสำคัญ ---
                let statusHtml = getStatusBadge(row.expiry_date_raw, row.quantity_remaining, true);

                // --- 5. ปุ่มจัดการ (แก้ไข / ลบ) ---
                let actionButtons = `
                    <i class="fas fa-pen-to-square fa-lg text-warning ${window._canManage ? 'js-edit-prep-btn cursor-pointer' : ''}"
                        data-id="${row.id}" title="${window._canManage ? 'แก้ไข' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                    <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-prep-btn cursor-pointer' : ''}"
                        data-id="${row.id}"
                        data-name="${prep_name}"
                        data-date="${prep_date}"
                        data-remain="${numberWithCommas(qtyRemain)} ${unitText}"
                        data-department="${rawDepartmentName}"
                        title="${window._canManage ? 'ลบ' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                `;

                html += `
                    <tr class="clickable-row cursor-pointer" data-id="${row.id}" title="ดูข้อมูล">
                        <td class="text-center align-middle">
                            <div class="d-flex justify-content-center gap-2">
                                ${actionButtons}
                            </div>
                        </td>
                        <td class="text-center align-middle d-none d-xl-table-cell">${start_no + i}</td>
                        <td class="text-center align-middle">${prep_date}</td>
                        <td class="text-start align-middle">
                            <div class="chem-name-responsive text-dark">${prep_name}</div>
                            <div class="d-lg-none mt-1">
                                <small class="text-muted d-block">จาก: ${source_chem}</small>
                                <span class="badge bg-light text-primary border border-primary border-opacity-25 fw-normal" style="font-size: 0.7rem;">
                                    Lot: ${source_lot}
                                </span>
                            </div>
                        </td>

                        <td class="text-start align-middle d-none d-md-table-cell">${departmentName}</td>

                        <td class="text-start align-middle d-none d-lg-table-cell">
                            <div class="mb-1">${source_chem}</div>
                            <div class="badge bg-blue-light text-primary border border-primary border-opacity-25 fw-normal" style="font-size: 0.7rem;">
                                <i class="fas fa-tag me-1"></i>Lot: ${source_lot}
                            </div>
                        </td>

                        <td class="text-center align-middle">
                            <span class="fw-bold ${remainClass}">${numberWithCommas(qtyRemain)}</span>
                            <span class="text-muted small">/ ${numberWithCommas(prepQty)} ${unitText}</span>
                            
                        </td>

                        <td class="text-center align-middle d-none d-md-table-cell ${expiryClass}">
                            ${exp_date}
                        </td>

                        <td class="text-start align-middle d-none d-xl-table-cell">
                            ${preparer}
                        </td>

                        <td class="text-center align-middle d-none d-lg-table-cell">
                            ${statusHtml}
                        </td>
                    </tr>
                    `;
            });
        } else {
            html = `
                <tr>
                    <td colspan="10" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-vials fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0 fw-bold">ไม่พบประวัติการเตรียมสารเคมีในระบบ</p>
                            <small class="opacity-75">ลองเปลี่ยนเงื่อนไขการค้นหา หรือกดปุ่ม <b class="text-dark">" เพิ่มรายการ "</b> เพื่อสร้างข้อมูลใหม่</small>
                        </div>
                    </td>
                </tr>`;
        }
        $('#table_body').html(html);
    }

    // ตอนกด Submit ฟอร์มค้นหาครั้งแรก ให้เริ่มที่หน้า 1
    $('#searchFilterForm').on('submit', function(e) {
        e.preventDefault();
        searchPage(1);
    });

    $('#btn_clear_filter').on('click', function() {
        $('#searchFilterForm')[0].reset();
        $('#searchFilterForm select').val('').trigger('change');
        searchPage(1);
    });

    // ฟังก์ชันสร้างเลขหน้า (Pagination)
    function setupPagination(totalPages, currentPage) {
        // 1. แปลงค่าให้เป็นตัวเลขที่แน่นอน ป้องกันปัญหาจาก Type String
        const total = parseInt(totalPages);
        const current = parseInt(currentPage);

        console.log("SetupPagination:", total, current);

        // 2. ทำลายอันเก่า
        $('#pagination-list').twbsPagination('destroy');

        // 3. ถ้าไม่มีหน้า หรือหน้าเดียว (บางคนไม่อยากโชว์ปุ่มถ้ามีหน้าเดียว) 
        // แต่ถ้าอยากโชว์หน้าเดียวด้วยให้เอา current < 1 ออก
        if (total <= 0) return;

        // 4. ใช้ setTimeout เพื่อให้แน่ใจว่าการวาดใหม่จะไม่ตีกับอันที่เพิ่ง destroy
        setTimeout(function() {
            $('#pagination-list').twbsPagination({
                totalPages: total,
                startPage: current,
                visiblePages: 5,
                first: '<i class="fas fa-angle-double-left"></i>',
                prev: '<i class="fas fa-angle-left"></i>',
                next: '<i class="fas fa-angle-right"></i>',
                last: '<i class="fas fa-angle-double-right"></i>',
                initiateStartPageClick: false,
                onPageClick: function(event, page) {
                    // เช็คให้แน่ใจว่าหน้าที่คลิก ไม่ใช่หน้าเดิม
                    if (page !== current) {
                        searchPage(page);
                    }
                }
            });
        }, 50);
    }
</script>
<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>