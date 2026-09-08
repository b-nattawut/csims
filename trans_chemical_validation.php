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

$title = "การทดสอบสารเคมีในการตรวจสถานที่เกิดเหตุ";

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

    /* ปรับสไตล์รายการที่ถูก Disabled ใน Select2 */
    .select2-results__option[aria-disabled=true] {
        opacity: 0.5;
        background-color: #f8f9fa !important;
        color: #adb5bd !important;
        cursor: not-allowed;
    }


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
    .btn-close-lightbox-validate {
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
    .btn-close-lightbox-validate:hover {
        background-color: rgba(255, 255, 255, 0.15);
        transform: scale(1.1)
            /* พื้นหลังขาวอ่อนๆ */
    }


    .js-edit-val-btn,
    .js-delete-val-btn {
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
                            <button class="btn <?= $canManage ? 'btn-success' : 'btn-secondary' ?> btn-sm px-4 fw-bold shadow-sm text-nowrap w-100 <?= $canManage ? 'js-add-val-btn' : '' ?>"
                                style="height: 31px;"
                                type="button" <?= $canManage ? 'data-bs-toggle="modal" data-bs-target="#addEditValidationModal"' : 'disabled' ?>
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
                            <label for="filter_chemical_id" class="form-label text-muted small mb-1">ชื่อสารเคมี [ยี่ห้อ]</label>
                            <select id="filter_chemical_id" name="filter_chemical_id" class="form-select form-select-sm select2-master" data-placeholder="-- ทั้งหมด --">
                                <option value=""></option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_tester" class="form-label text-muted small mb-1">ผู้ทดสอบ</label>
                            <select id="filter_tester" name="filter_tester" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                <option value=""></option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-8 col-xl-8">
                            <label class="form-label text-muted small mb-1 fw-bold">ช่วงวันที่ทดสอบ</label>
                            <div class="input-group input-group-sm">
                                <input type="date" id="filter_val_start" name="filter_val_start" class="form-control">
                                <span class="input-group-text">ถึง</span>
                                <input type="date" id="filter_val_end" name="filter_val_end" class="form-control">
                            </div>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_res_acid_base" class="form-label text-muted small mb-1">ผลทดสอบ (กรด-เบส)</label>
                            <select id="filter_res_acid_base" name="filter_res_acid_base" class="form-select form-select-sm">
                                <option value="">-- ทั้งหมด --</option>
                                <option value="pass">ใช้ได้</option>
                                <option value="fail">ใช้ไม่ได้</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_res_blood" class="form-label text-muted small mb-1">ผลทดสอบ (โลหิต)</label>
                            <select id="filter_res_blood" name="filter_res_blood" class="form-select form-select-sm">
                                <option value="">-- ทั้งหมด --</option>
                                <option value="pass">ใช้ได้</option>
                                <option value="fail">ใช้ไม่ได้</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_is_verified" class="form-label text-muted small mb-1">สถานะทวนสอบ</label>
                            <select id="filter_is_verified" name="filter_is_verified" class="form-select form-select-sm">
                                <option value="">-- ทั้งหมด --</option>
                                <option value="1">ทวนสอบแล้ว</option>
                                <option value="0">รอการทวนสอบ</option>
                            </select>
                        </div>

                        <div class="col-12 d-flex justify-content-center align-items-center gap-2 mt-4 pt-3 border-top">
                            <button type="button" class="btn btn-warning btn-sm text-dark px-4 fw-bold" id="btn_clear_filter">
                                <i class="fas fa-undo me-1"></i> ล้างค่า
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" id="btn_search_filter" name="btn_search_filter">
                                <i class="fas fa-search me-1"></i> ค้นหา
                            </button>
                            <button type="button" class="btn btn-success btn-sm px-4 fw-bold" id="btn_export_excel" name="btn_export_excel">
                                <i class="fas fa-file-excel me-1"></i> Export Excel
                            </button>
                            <button type="button" class="btn btn-sm text-white px-4 fw-bold" style="background-color: #6f42c1;" id="btn_export_pdf">
                                <i class="fas fa-file-pdf me-1"></i> Export PDF
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
                    <th style="min-width: 88px;">Action</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 50px;">ลำดับ</th>
                    <th style="min-width: 100px;">วันที่ทดสอบ</th>
                    <th style="min-width: 220px;">รายการสารเคมีที่ทดสอบ</th>
                    <th class="d-none d-md-table-cell" style="min-width: 150px;">หน่วยงาน</th>
                    <th style="min-width: 100px;">ผลทดสอบ<br>(กรด-เบส)</th>
                    <th style="min-width: 100px;">ผลทดสอบ<br>(โลหิต)</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 150px;">ผู้ทดสอบ</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 150px;">ผู้ทวนสอบ</th>
                    <th style="min-width: 100px;">สถานะ</th>
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

<!-- Modal สำหรับเพิ่มรายการทดสอบสารเคมีใหม่ / แก้ไขรายการเดิม -->
<div class="modal fade" id="addEditValidationModal" aria-labelledby="addEditValidationModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addEditValidationModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> <span id="textModal">เพิ่มรายการ</span>
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" id="chemicalValidationForm" action="./api/Transaction_chemical_validation/save.php" novalidate>
                    <input type="hidden" id="val_edit_id" name="id">

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border mb-3">
                        <legend class="fieldset-header">
                            <i class="fas fa-search me-2"></i>เลือกสารเคมีที่ต้องการทดสอบ
                        </legend>
                        <div class="row g-3 justify-content-start">

                            <div class="col-12 col-xl-8">
                                <label for="prep_id" class="form-label fw-bold">ค้นหาสารที่เตรียมไว้ <span class="text-danger">*</span></label>
                                <select id="prep_id" name="prep_id" class="form-select select2-modal-val" required data-placeholder="-- ค้นหาชื่อสารที่เตรียม หรือเลข Lot --">
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาเลือกสารเคมีที่ต้องการทดสอบ</div>
                            </div>

                            <div class="col-12 col-xl-4">
                                <label for="val_amount_used" class="form-label fw-bold">ปริมาณที่ดึงมาทดสอบ <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text"
                                        id="val_amount_used"
                                        name="amount_used"
                                        class="form-control"
                                        placeholder="เช่น 5.00"
                                        inputmode="decimal"
                                        required
                                        oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');">
                                    <span class="input-group-text" id="val_unit_display">หน่วย</span>
                                </div>
                                <div id="val_stock_info" class="form-text text-primary ml-1"></div>
                            </div>

                            <div class="col-md-12 mt-3" id="val_chemical_detail_section" style="display: none;">
                                <div class="bg-light p-3 rounded-3 border border-dashed shadow-sm">

                                    <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                                        <i class="fas fa-flask me-1"></i> รายละเอียดสารเคมีที่เลือก
                                    </h6>

                                    <div class="row g-3">
                                        <div class="col-6 col-md-6 col-xl-4">
                                            <label class="text-muted small d-block mb-1">รายการสารเคมีตั้งต้น</label>
                                            <span id="auto_chemical_name" class="fw-bold">-</span>
                                        </div>
                                        <div class="col-6 col-md-6 col-xl-4">
                                            <label class="text-muted small d-block mb-1">เลข Lot สารตั้งต้น</label>
                                            <span id="auto_source_lot" class="fw-bold">-</span>
                                        </div>
                                        <div class="col-6 col-md-6 col-xl-4">
                                            <label class="text-muted small d-block mb-1">ยี่ห้อ / ผู้ผลิต</label>
                                            <span id="auto_brand_name" class="fw-bold">-</span>
                                        </div>
                                        <div class="col-6 col-md-6 col-xl-4">
                                            <label class="text-muted small d-block mb-1">หน่วยงานต้นสังกัด</label>
                                            <span id="auto_department_name" class="fw-bold">-</span>
                                        </div>
                                        <div class="col-6 col-md-6 col-xl-4">
                                            <label class="text-muted small d-block mb-1">วันที่เตรียม</label>
                                            <span id="auto_prep_date" class="fw-bold">-</span>
                                        </div>

                                        <div class="col-6 col-md-6 col-xl-4">
                                            <label class="text-muted small d-block mb-1">วันหมดอายุ</label>
                                            <span id="auto_exp_date" class="fw-bold">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border mb-3">
                        <legend class="fieldset-header">
                            <i class="fas fa-vial me-2"></i>ผลการทดสอบ
                        </legend>
                        <div class="row g-3 justify-content-start">

                            <div class="col-12 col-xl-6 mb-2">
                                <label class="form-label fw-bold d-block mb-2 pb-2 border-bottom">ผลการทดสอบ (กรด-เบส)</label>
                                <div class="bg-light p-3 rounded-3 border d-flex justify-content-center gap-3">
                                    <input type="radio" class="btn-check" name="res_acid_base" id="acid_pass" value="pass" required autocomplete="off">
                                    <label class="btn btn-outline-success flex-fill fw-bold shadow-sm py-2" for="acid_pass">
                                        <i class="fas fa-check-circle me-2"></i> ใช้ได้
                                    </label>

                                    <input type="radio" class="btn-check" name="res_acid_base" id="acid_fail" value="fail" autocomplete="off">
                                    <label class="btn btn-outline-danger flex-fill fw-bold shadow-sm py-2" for="acid_fail">
                                        <i class="fas fa-times-circle me-2"></i> ใช้ไม่ได้
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 col-xl-6 mb-2">
                                <label class="form-label fw-bold d-block mb-2 pb-2 border-bottom">ผลการทดสอบ (โลหิต)</label>
                                <div class="bg-light p-3 rounded-3 border d-flex justify-content-center gap-3">
                                    <input type="radio" class="btn-check" name="res_blood" id="blood_pass" value="pass" required autocomplete="off">
                                    <label class="btn btn-outline-success flex-fill fw-bold shadow-sm py-2" for="blood_pass">
                                        <i class="fas fa-check-circle me-2"></i> ใช้ได้
                                    </label>

                                    <input type="radio" class="btn-check" name="res_blood" id="blood_fail" value="fail" autocomplete="off">
                                    <label class="btn btn-outline-danger flex-fill fw-bold shadow-sm py-2" for="blood_fail">
                                        <i class="fas fa-times-circle me-2"></i> ใช้ไม่ได้
                                    </label>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border mb-3">
                        <legend class="fieldset-header">
                            <i class="fas fa-users me-2"></i>ข้อมูลผู้ดำเนินการ
                        </legend>
                        <div class="row g-3 justify-content-start">

                            <div class="col-12">
                                <label for="val_test_date" class="form-label fw-bold">วันที่ทำการทดสอบ <span class="text-danger">*</span></label>
                                <input type="date" id="val_test_date" name="test_date" class="form-control" required>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="tester_id" class="form-label fw-bold">ผู้ทดสอบ <span class="text-danger">*</span></label>
                                <select id="tester_id" name="tester_id" class="form-select select2-val-tester" required data-placeholder="-- ค้นหาชื่อเจ้าหน้าที่ผู้ทดสอบ --">
                                    <option value=""></option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="verifier_id" class="form-label fw-bold">ผู้ทวนสอบ <span class="text-danger">*</span></label>
                                <select id="verifier_id" name="verifier_id" class="form-select select2-val-verifier" required data-placeholder="-- ค้นหาชื่อเจ้าหน้าที่ผู้ทวนสอบ --">
                                    <option value=""></option>
                                </select>
                                <div id="verifier_error" class="text-danger small mt-1 d-none">ผู้ทวนสอบต้องไม่ใช่คนเดียวกับผู้ทดสอบ</div>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border mb-3">
                        <legend class="fieldset-header">
                            <i class="fas fa-images me-2"></i>แนบภาพถ่ายหลักฐานผลการทดสอบ
                        </legend>

                        <div class="row mb-3">
                            <div class="col-12 text-center">
                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="button" class="btn btn-primary px-4 shadow-sm" id="btn_choose_file_validate">
                                        <i class="fas fa-folder-open me-2"></i>เลือกไฟล์รูปภาพ
                                    </button>
                                    <button type="button" class="btn btn-success px-4 shadow-sm" id="btn_open_camera_validate">
                                        <i class="fas fa-camera me-2"></i>เปิดกล้องถ่ายภาพ
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <input type="file" id="validate_photos" name="validate_photos[]" accept="image/*" style="display: none;" multiple>
                                <input type="file" id="camera_input_validate" name="camera_photos_validate[]" accept="image/*" capture="environment" style="display: none;" multiple>

                                <div id="uploading_container_validate" class="mb-2"></div>

                                <div id="attachments_wrapper_validate" class="d-none mt-3">
                                    <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2">
                                        จำนวนภาพที่แนบ
                                        <span class="badge bg-secondary badge-circle ms-1" id="file_count_badge_validate">0</span>
                                    </h6>
                                    <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-3" id="attachments_grid_validate">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <div class="alert alert-secondary d-flex align-items-center mb-0" role="alert">
                        <i class="fas fa-info-circle me-2 fs-5"></i>
                        <div class="small">
                            เมื่อบันทึกข้อมูลเบื้องต้นแล้ว ระบบจะนำคุณไปยังหน้า <b>"รายละเอียดการทดสอบสารเคมี"</b> เพื่อดูรายละเอียดและลงนามทวนสอบในขั้นตอนถัดไป
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-end bg-white border-top shadow-sm">
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="chemicalValidationForm">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail สำหรับดูรายละเอียดทั้งหมด จะไม่มีสำหรับหน้านี้ จะไปใช้อีกหน้าแยกเลย เพราะต้องมีการลงลายเซ็นด้วย -->

<!-- LightBox แสดเป็นพื้นหลังสีดำเต็มหน้าจอ เวลากดดูรูปภาพเต็ม ๆ -->
<div id="customLightboxValidate" class="d-none" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: zoom-out;" onclick="closeLightboxOutsideValidate(event)">

    <div id="lightboxFileNameValidate" style="position: absolute; top: 20px; left: 20px; color: white; font-size: 1.2rem; font-weight: 300; background: rgba(0,0,0,0.5); padding: 5px 15px; border-radius: 4px; pointer-events: none;"></div>

    <button type="button"
        class="btn-close-lightbox-validate"
        onclick="closeLightboxValidate()"
        title="ปิดหน้าต่าง">
        &times;
    </button>

    <img id="lightboxImageValidate" src="" style="max-width: 95%; max-height: 85%; object-fit: contain; box-shadow: 0 0 30px rgba(0,0,0,0.5); cursor: default;" onclick="event.stopPropagation()">

</div>

<?php
$content = ob_get_clean();
ob_start();
?>

<script>
    window._sessionUserId = '<?= $_SESSION["user_id"] ?>';
    window._canManage = <?= $canManage ? 'true' : 'false' ?>;
    let attachmentStoreValidate = [];
    let deletedExistingPhotosValidate = [];

    $(document).ready(function() {

        if ($('#filter_department_id').length) {
            loadDepartments();
        }

        searchPage(1);

        // ส่วน filter รายการที่แสดงผล ด้วย chemical name - brand จาก master 
        // (ไปดึงเอาตัวที่ prepare ซึ่งชื่ออาจต่างกัน แต่ถ้าสารตั้งต้นเดียวกันคือตัวที่เลือกก็จะเอามาแสดงหมด)
        $('#filter_chemical_id').select2({
                theme: 'bootstrap-5',
                placeholder: '-- เลือกสารเคมี --',
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

        // Select2 รายการ user ในส่วนของ filter
        $('.select2-filter').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
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
            })
            .next('.select2-container')
            .addClass('select2-sm-custom');

        // Setup Select2 สำหรับ "หน่วยงาน" ในส่วน Filter
        $('.select2-dept').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            placeholder: '-- ทั้งหมด --'
        }).next('.select2-container').addClass('select2-sm-custom');

        // --- 1. ตั้งค่าผู้ทดสอบ (Tester) ---
        $('#tester_id').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#addEditValidationModal'),
            allowClear: true,
            ajax: {
                url: './api/get_users.php',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    q: params.term
                }),
                processResults: function(response) {
                    // ดึงค่าจากช่อง "ผู้ทวนสอบ" มาเทียบ
                    const verifierId = $('#verifier_id').val();

                    return {
                        results: $.map(response.data, function(item) {
                            return {
                                id: item.user_id,
                                text: item.fullname,
                                // --- ถ้า ID ตรงกับผู้ทวนสอบ ให้ disabled ทันที ---
                                disabled: (verifierId && item.user_id == verifierId)
                            };
                        })
                    };
                },
                cache: true
            }
        });

        // --- 2. Init ผู้ทวนสอบ (Verifier) ---
        $('#verifier_id').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#addEditValidationModal'),
            allowClear: true,
            ajax: {
                url: './api/get_users.php',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    q: params.term
                }),
                processResults: function(response) {
                    // ดึงค่าจากช่อง "ผู้ทดสอบ" มาเทียบ
                    const testerId = $('#tester_id').val();

                    return {
                        results: $.map(response.data, function(item) {
                            return {
                                id: item.user_id,
                                text: item.fullname,
                                // --- ถ้า ID ตรงกับผู้ทดสอบ ให้ disabled ทันที ---
                                disabled: (testerId && item.user_id == testerId)
                            };
                        })
                    };
                },
                cache: true
            }
        });

        // 2. Setup Select2 สำหรับ "เลือกสารเคมีจากคลัง" (ใน Modal)
        $('#prep_id').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#addEditValidationModal'),
            width: '100%',
            allowClear: true,
            ajax: {
                url: './api/Chemical_preparation/getPreparedSelect2.php',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term
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

        // เมื่อเลือกรายการสารที่เตรียมแล้วจาก Select2
        $('#prep_id').on('select2:select', function(e) {
            let data = e.params.data; // ข้อมูลที่เราส่งมาจาก PHP
            const $amountInput = $('#val_amount_used');

            // หยอดข้อมูลลงในส่วน Auto-fill (Read-only) --> ใน api จะไปดึงจาก master, inventory, prepare มาหยอด
            $('#auto_chemical_name').text(data.chemical_name || '-'); // ชื่อจาก Master
            $('#auto_brand_name').text(data.chemical_brand || '-'); // ยี่ห้อจาก Master
            $('#auto_source_lot').text(data.source_lot_number || '-'); // Lot จาก Inventory
            $('#auto_prep_date').text(data.prep_date_show || '-'); // วันที่เตรียม
            $('#auto_exp_date').text(data.exp_date_show || '-'); // วันหมดอายุของที่เตรียม
            $('#auto_department_name').text(data.department_name || '-');

            // แสดงหน่วยนับตั้งต้น
            const unitShow = data.unit_display || 'หน่วย';
            $('#val_unit_display').text(unitShow);

            // เก็บค่าไว้คำนวณ Validation
            $amountInput.data('current-stock', parseFloat(data.quantity_remaining || 0));
            $amountInput.data('original-used', 0);
            $amountInput.data('unit-display', unitShow);
            $amountInput.data('prep-name', data.prep_name);
            $amountInput.data('source-lot', data.source_lot_number);

            $('#val_chemical_detail_section').fadeIn(300);
            // ย้ายโฟกัสไปช่องปริมาณ
            $('#val_amount_used').focus();

            updateValidationStock($amountInput.val());
        });

        // --- เมื่อมีการล้างค่า (Clear) ---
        $('#prep_id').on('select2:unselect select2:clear', function() {
            resetValChemicalDetails(true);
        });

        // ดักจับการกรอกปริมาณที่ดึงมาทดสอบ
        $('#val_amount_used').on('input', function() {
            updateValidationStock($(this).val());
        });

        $('#val_test_date').on('change', function() {
            validateValidationDates();
        });

        // --- ดัก Event เมื่อมีการเลือก เพื่อให้ทั้งสองช่อง Update สถานะกันเอง ---
        $('#tester_id, #verifier_id').on('change', function() {
            validateUserSync(); // ฟังก์ชันเช็ค Error แดงๆ ที่คุณเขียนไว้
        });

        // Event Listeners สำหรับปุ่มเลือกไฟล์และกล้อง
        $('#btn_choose_file_validate').on('click', function() {
            $('#validate_photos').click();
        });

        $('#btn_open_camera_validate').on('click', function() {
            $('#camera_input_validate').click();
        });

        // จัดการเมื่อมีการเลือกไฟล์หรือถ่ายภาพ
        $('#validate_photos, #camera_input_validate').on('change', function(e) {
            const files = e.target.files;
            if (!files.length) return;

            const totalFiles = files.length;
            let processedCount = 0;

            const $uploadingBox = $('#uploading_container_validate');
            $uploadingBox.html(`
            <div class="d-flex align-items-center justify-content-center p-3 border rounded bg-light animate__animated animate__fadeIn">
                <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                <span class="small text-secondary">กำลังประมวลผลรูปภาพ <span id="cur_proc">0</span>/${totalFiles} ใบ...</span>
            </div>
        `);

            // วนลูปประมวลผลรูปภาพ
            Array.from(files).forEach(file => {
                // ตรวจสอบว่าเป็นไฟล์รูปภาพจริงหรือไม่
                if (!file.type.match('image.*')) {
                    processedCount++;
                    return;
                }

                const reader = new FileReader();

                // ดัก Error ของ FileReader
                reader.onerror = function() {
                    console.error("FileReader Error on file:", file.name);
                    processedCount++;
                };

                reader.onload = function(event) {
                    try {
                        // เพิ่มข้อมูลเข้า Store
                        attachmentStoreValidate.unshift({
                            filename: file.name,
                            base64: event.target.result,
                            size: formatFileSize(file.size),
                            date: new Date().toLocaleString('th-TH', {
                                day: '2-digit',
                                month: '2-digit',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            }),
                            isExisting: false,
                            file: file // เก็บไฟล์ต้นฉบับไว้ส่งตอน Save
                        });
                    } catch (err) {
                        console.error("Error pushing to store:", err);
                    } finally {
                        processedCount++;
                        $('#cur_proc').text(processedCount);

                        // เมื่อประมวลผลครบทุกไฟล์ (ไม่ว่าจะสำเร็จหรือ Error)
                        if (processedCount === totalFiles) {
                            setTimeout(() => {
                                $uploadingBox.empty();
                                renderAttachmentStoreValidate();
                            }, 300); // หน่วงเวลาเล็กน้อยให้ UI ทันอัปเดต
                        }
                    }
                };
                reader.readAsDataURL(file);
            });
            $(this).val(''); // Reset input
        });

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

    function resetValChemicalDetails(isSmooth = false) {
        const $section = $('#val_chemical_detail_section');

        if (isSmooth) {
            // กรณี User กดล้างค่าเองขณะ Modal เปิดอยู่ (ใช้ Fade Out)
            $section.fadeOut(300);
        } else {
            // กรณีล้างฟอร์มตอนเปิด Modal หรือปิด Modal (ซ่อนทันที ไม่ขยับ)
            $section.hide();
        }

        $('#auto_chemical_name, #auto_source_lot, #auto_brand_name, #auto_prep_date, #auto_exp_date, #auto_department_name').text('-');
        $('#val_unit_display').text('หน่วย');
        $('#val_stock_info').empty();
        $('#val_amount_used').val('').removeClass('is-invalid');
    }

    function validateUserSync() {
        const testerId = $('#tester_id').val();
        const verifierId = $('#verifier_id').val();
        const $verifierSelect = $('#verifier_id').next('.select2-container');
        const $errorLabel = $('#verifier_error');

        // ถ้ามีการเลือกทั้งสองช่อง และ ID ตรงกัน
        if (testerId && verifierId && testerId === verifierId) {
            $verifierSelect.addClass('border border-danger rounded');
            $errorLabel.removeClass('d-none');
        } else {
            $verifierSelect.removeClass('border border-danger');
            $errorLabel.addClass('d-none');
        }
    }

    //  ฟังก์ชันช่วยจัดรูปแบบขนาดไฟล์
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // ฟังก์ชันวาดรายการรูปภาพ (Preview Grid)
    function renderAttachmentStoreValidate() {
        const $grid = $('#attachments_grid_validate');
        const $wrapper = $('#attachments_wrapper_validate');
        const $badge = $('#file_count_badge_validate');

        if (!$grid.length) return; // กันพังถ้าหา Element ไม่เจอ

        $grid.empty();
        const totalCount = attachmentStoreValidate.length;
        $badge.text(totalCount);

        if (totalCount > 0) {
            $wrapper.removeClass('d-none');
            attachmentStoreValidate.forEach((file, index) => {
                const isExisting = file.isExisting;
                const statusBadge = isExisting ?
                    '<span class="badge bg-info text-white fw-normal" style="font-size: 0.65rem;">รูปเดิม</span>' :
                    '<span class="badge bg-success text-white fw-normal" style="font-size: 0.65rem;">รูปใหม่</span>';

                const imgSrc = file.src || file.base64 || '';
                const fileName = file.filename || 'Untitled';
                const fileSize = file.size || 'N/A';
                const fileDate = file.date || '-';

                const html = `
                <div class="col animate__animated animate__fadeIn">
                    <div class="attachment-card shadow-sm">
                        <div class="card-header-actions">
                            <button type="button" class="btn btn-outline-primary btn-action-sm" onclick="showImagePreviewValidate(${index})">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-action-sm" onclick="removeFileFromStoreValidate(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="img-box-middle" onclick="showImagePreviewValidate(${index})" style="cursor:zoom-in;">
                            <img src="${imgSrc}" style="width:100%; height:100%; object-fit:cover;" alt="preview">
                        </div>
                        <div class="card-body p-2 bg-white">
                            <div class="filename-text p-0 mb-2 fw-bold text-dark text-truncate" title="${fileName}" style="font-size: 0.75rem;">
                                ${fileName}
                            </div>
                            
                            <div class="metadata-group mt-1 flex-grow-1">
                                <div class="d-flex align-items-center text-secondary mb-1" style="font-size: 0.65rem;">
                                    <i class="fas fa-fw fa-hdd me-1 opacity-50"></i>
                                    <span>${fileSize}</span>
                                </div>
                                <div class="d-flex align-items-center text-secondary mb-2" style="font-size: 0.65rem;">
                                    <i class="fas fa-fw fa-calendar-alt me-1 opacity-50"></i>
                                    <span>${fileDate}</span>
                                </div>
                                <div class="d-flex justify-content-end">
                                    ${statusBadge}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
                $grid.append(html);
            });
        } else {
            $wrapper.addClass('d-none');
        }
    }

    // ฟังก์ชันลบรูปภาพ
    function removeFileFromStoreValidate(index) {
        const file = attachmentStoreValidate[index];
        if (file.isExisting && file.db_file_id) {
            deletedExistingPhotosValidate.push(file.db_file_id);
        }

        attachmentStoreValidate.splice(index, 1);
        renderAttachmentStoreValidate();
    }

    // ระบบ Lightbox (ดูรูปขยาย)
    function showImagePreviewValidate(index) {
        const file = attachmentStoreValidate[index];
        $('#lightboxImageValidate').attr('src', file.src || file.base64); // ใช้ ID Lightbox เดิมได้
        $('#lightboxFileNameValidate').text(file.filename);
        $('#customLightboxValidate').removeClass('d-none').hide().fadeIn(200);
        $('body').addClass('lightbox-open');
    }

    function closeLightboxValidate() {
        $('#customLightboxValidate').fadeOut(200, function() {
            $(this).addClass('d-none');
        });
        $('body').removeClass('lightbox-open');
    }

    function closeLightboxOutsideValidate(event) {
        // ตรวจสอบว่า ID ที่คลิกคือตัวพื้นหลัง จริงๆ ไม่ใช่ตัวรูปภาพ
        if (event.target.id === 'customLightboxValidate') {
            closeLightboxValidate();
        }
    }

    // ฟังก์ชันคำนวณยอดคงเหลือ Real-time สำหรับหน้า Validation
    function updateValidationStock(inputValue) {
        const $input = $('#val_amount_used');
        const $info = $('#val_stock_info');

        const currentStock = parseFloat($input.data('current-stock')) || 0;
        const originalUsed = parseFloat($input.data('original-used')) || 0;
        const prepName = $input.data('prep-name') || 'ไม่ระบุชื่อ'; // เก็บชื่อขวดที่เตรียมไว้
        const sourceLot = $input.data('source-lot') || 'ไม่ระบุล็อต';

        // ใช้ || 0 เพื่อให้เวลาเป็นค่าว่าง จะถูกมองว่าเป็นเลข 0 ทันที
        const currentInput = parseFloat(inputValue) || 0;
        const unitShow = $input.data('unit-display') || 'หน่วย';

        // ตรรกะ: (ยอดในขวดปัจจุบัน + ยอดที่เคยบันทึกไว้เดิม) - ยอดใหม่ที่กำลังกรอก
        const totalAvailable = currentStock + originalUsed;
        const realTimeRemain = totalAvailable - currentInput;

        const isOver = realTimeRemain < 0;
        const colorClass = isOver ? 'text-danger' : 'text-primary';
        const warningText = isOver ? ' <small class="fw-bold">(เกินยอดที่มี!)</small>' : '';

        // ปรับ Keyword ถ้าช่องว่างให้ใช้คำว่า "คงเหลือ" ปกติ ถ้ากรอกเลขให้ใช้ "คงเหลือหลังหักออก"
        const labelText = (inputValue === '' || currentInput === 0) ?
            'ปริมาณสารที่เตรียมไว้คงเหลือ:' :
            'คงเหลือหลังหักออก:';

        // เปลี่ยนสีของตัวอักษร "ทั้งแถว" โดยจัดการที่ตัว container (#val_stock_info)
        $info.removeClass('text-primary text-danger').addClass(colorClass);

        $info.html(`
            <div class="d-flex flex-column">
                <span class="mb-1">
                    <i class="fas fa-flask me-1"></i> สารที่เลือก: <b>${prepName} (Lot: ${sourceLot})</b>
                </span>
                <span>
                    <i class="fas fa-info-circle"></i> ${labelText}<b class="ms-1">${numberWithCommas(realTimeRemain)} ${unitShow}</b>${warningText}
                </span>
            </div>
        `);

        // 3. จัดการสถานะขอบแดงของ Input
        if (isOver) {
            $input.addClass('is-invalid');
        } else {
            $input.removeClass('is-invalid');
        }
    }

    // --- ฟังก์ชันตรวจสอบความถูกต้องของวันที่ทดสอบ (Validation) ---
    function validateValidationDates() {
        const testInput = document.getElementById('val_test_date');
        const $testInput = $(testInput);

        // ดึงวันที่เตรียมสารที่ถูกหยอดไว้ใน Auto-fill (หรือดึงจาก data ของ Select2)
        // หมายเหตุ: ควรดึงเป็น Format YYYY-MM-DD เพื่อความแม่นยำในการเทียบ
        const prepDateVal = $('#prep_id').select2('data')[0]?.prep_date_raw;
        const testDateVal = testInput.value;

        // 1. Reset State
        $testInput.removeClass('is-invalid');
        testInput.setCustomValidity('');

        // 2. ตรวจสอบเงื่อนไข: วันที่ทดสอบต้องไม่เกิดก่อนวันที่เตรียม
        if (testDateVal && prepDateVal) {
            const testDate = new Date(testDateVal);
            const prepDate = new Date(prepDateVal);

            // ตัดเวลาออกเพื่อให้เทียบเฉพาะวันที่
            testDate.setHours(0, 0, 0, 0);
            prepDate.setHours(0, 0, 0, 0);

            if (testDate < prepDate) {
                $testInput.addClass('is-invalid');
                testInput.setCustomValidity('วันที่ทดสอบห้ามก่อนวันที่เตรียมสาร');

                Swal.fire({
                    icon: 'error',
                    title: 'วันที่ไม่ถูกต้อง',
                    text: 'วันที่ทำการทดสอบ ห้ามก่อนวันที่เตรียมสารเคมี',
                    confirmButtonText: 'รับทราบ'
                });
            } else {
                // กรณีถูกต้อง: ล้างสถานะแดงออกเพื่อให้ฟอร์มส่งข้อมูลได้
                $testInput.removeClass('is-invalid');
                testInput.setCustomValidity('');
            }
        }
    }

    // --- ฟังก์ชันล้างสถานะฟอร์ม Validation ---
    function resetValidationFormState() {
        const $form = $('#chemicalValidationForm');

        // 1. Reset ฟอร์มพื้นฐาน
        $form[0].reset();
        $form.removeClass('was-validated');
        $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');

        // 2. ล้างค่า Select2 ทั้งหมด (สำคัญมาก)
        $('#prep_id, #tester_id, #verifier_id').val(null).trigger('change');

        // 3. ล้างสถานะวันที่
        document.getElementById('val_test_date').setCustomValidity('');

        // 4. ล้างส่วนข้อมูล Auto-fill (Read-only)
        $('#auto_chemical_name, #auto_brand_name, #auto_source_lot, #auto_prep_date, #auto_exp_date, #auto_department_name').text('-');

        // 5. ล้างสถานะ Stock Info
        $('#val_unit_display').text('หน่วย');
        $('#val_stock_info').removeClass('text-danger').addClass('text-primary').empty();

        // 6. ล้าง Error สำหรับ Verifier (ถ้ามี)
        $('#verifier_error').addClass('d-none');
        $('#verifier_id').next('.select2-container').removeClass('border border-danger');
        $('#verifier_error').addClass('d-none');
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
        if ($(e.target).closest('.js-edit-val-btn, .js-delete-val-btn, button, i').length) {
            return;
        }

        const id = $(this).data('id');
        if (id) {
            // เปลี่ยนหน้าไปยังหน้ารายละเอียด (Detail Page)
            window.location.href = `trans_chemical_validation_detail.php?id=${id}`;
        }
    });

    // 1. Logic สำหรับปุ่ม "เพิ่มรายการ" (Add)
    $(document).on('click', '.js-add-val-btn', function() {
        const $form = $('#chemicalValidationForm');

        // 1.1 ล้างข้อมูลและ Class ของ Bootstrap
        $form[0].reset();
        $form.removeClass('was-validated');
        // 1.2 ล้าง Manual Class (is-invalid, is-valid)
        $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');
        const inputsToClear = ['val_test_date', 'val_amount_used'];
        inputsToClear.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.setCustomValidity('');
        });

        // 1.3 รีเซ็ต ID (Hidden) และ Select2 ทั้งหมด
        $('#val_edit_id').val('');
        $('#prep_id, #tester_id, #verifier_id').val(null).trigger('change');
        resetValChemicalDetails(false);

        // 1.4 ล้างส่วนแสดงข้อมูลสารเคมีที่ดึงมา (Auto-fill Spans)
        $('#auto_chemical_name, #auto_brand_name, #auto_source_lot, #auto_prep_date, #auto_exp_date, #auto_department_name').text('-');
        $('#val_unit_display').text('หน่วย');
        $('#val_stock_info').empty();
        $('#verifier_error').addClass('d-none'); // ซ่อน Error ของผู้ทวนสอบ

        // 1.6 ล้างตัวแปรที่เก็บข้อมูลรูปภาพ (Store)
        if (typeof attachmentStoreValidate !== 'undefined') {
            attachmentStoreValidate = [];
        }

        // 1.7 ล้าง UI ส่วนแสดงตัวอย่างรูปภาพ
        $('#attachments_grid_validate').empty();
        $('#attachments_wrapper_validate').addClass('d-none');
        $('#file_count_badge_validate').text('0');
        $('#uploading_container_validate').empty();

        // 1.8 ล้างค่าใน Input File (เพื่อให้เลือกไฟล์เดิมซ้ำได้)
        $('#validate_photos, #camera_input_validate').val('');

        // 1.5 เปลี่ยนหัวข้อ Modal
        $('#textModal').text('เพิ่มรายการ');
        $('#addEditValidationModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายการ');

        // 1.6 ตั้งค่าเริ่มต้น: ผู้ทดสอบ = ผู้ใช้ปัจจุบัน, วันที่ทดสอบ = วันนี้
        let currentUserId = '<?php echo $current_user_id; ?>';
        let currentFullname = '<?php echo $current_user_fullname; ?>';

        if (currentUserId && currentFullname !== "-" && currentFullname !== "") {
            // สร้าง Option ใหม่สำหรับ Select2 ของผู้ทดสอบ
            let userOption = new Option(currentFullname, currentUserId, true, true);
            $('#tester_id').append(userOption).trigger('change');
        }

        let today = new Date().toISOString().split('T')[0];
        $('#val_test_date').val(today);

        // 1.7 ปลดล็อค Select2 (เผื่อกรณีที่เคยถูก Disabled ไว้ตอน Edit)
        $('#prep_id').prop('disabled', false);

        // เคลียร์ข้อมูลที่เก็บไว้ใน data attribute ของช่องกรอกปริมาณ
        $('#val_amount_used').data('current-stock', 0);
        $('#btn_save_all').prop('disabled', false).attr('title', '');
    });

    // 2. Logic สำหรับปุ่ม "แก้ไข" (Edit)
    $(document).on('click', '.js-edit-val-btn', function() {
        let dataId = $(this).data('id');

        $.ajax({
            url: './api/Transaction_chemical_validation/getDataByID.php',
            type: 'GET',
            data: {
                id: dataId
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let d = res.data;
                    const $form = $('#chemicalValidationForm');
                    const $amountInput = $('#val_amount_used');

                    // 2.1 เปลี่ยนหัวข้อ Modal
                    $('#textModal').text('แก้ไขข้อมูลการทดสอบสารเคมี');
                    $('#addEditValidationModalLabel').html('<i class="fas fa-edit fa-lg me-2"></i> แก้ไขข้อมูลการทดสอบสารเคมี');
                    $form.removeClass('was-validated');
                    $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');

                    // 2.2 Map ข้อมูลพื้นฐานลง Form
                    $('#val_edit_id').val(d.id);
                    $('#val_test_date').val(d.test_date_raw);
                    $amountInput.val(d.amount_used);

                    // 2.3 จัดการ Radio Buttons (ผลการทดสอบ)
                    if (d.res_acid_base) {
                        $(`input[name="res_acid_base"][value="${d.res_acid_base}"]`).prop('checked', true);
                    }
                    if (d.res_blood) {
                        $(`input[name="res_blood"][value="${d.res_blood}"]`).prop('checked', true);
                    }

                    // 2.4 หยอดข้อมูลในส่วน Auto-fill Spans (Read-only)
                    $('#auto_chemical_name').text(d.chemical_name || '-');
                    $('#auto_brand_name').text(d.chemical_brand || '-');
                    $('#auto_source_lot').text(d.source_lot_number || '-');
                    $('#auto_prep_date').text(d.prep_date_show || '-');
                    $('#auto_exp_date').text(d.exp_date_show || '-');
                    $('#auto_department_name').text(d.department_name || '-');

                    $('#val_chemical_detail_section').show();

                    // 2.5 จัดการหน่วยและยอดคงเหลือเพื่อคำนวณ Real-time
                    $('#val_unit_display').text(d.unit_display);

                    $amountInput.data('original-used', parseFloat(d.amount_used || 0));
                    $amountInput.data('current-stock', parseFloat(d.prep_remain_now || 0));
                    $amountInput.data('unit', d.unit_id);
                    $amountInput.data('prep-name', d.prep_name);
                    $amountInput.data('source-lot', d.source_lot_number);

                    // เรียกฟังก์ชันอัปเดตตัวเลขสต็อก
                    updateValidationStock(d.amount_used);

                    // 2.6 จัดการ Select2: สารที่เตรียมไว้ (Lock ไม่ให้เปลี่ยน)
                    if (d.prep_id) {
                        let prepOption = new Option(d.text, d.prep_id, true, true);
                        $(prepOption).data('prep_date_raw', d.prep_date_raw); // เก็บไว้ใช้เทียบวันที่ใน validateValidationDates
                        $('#prep_id').empty().append(prepOption).trigger('change').prop('disabled', true);
                    }

                    // 2.7 จัดการ Select2: ผู้ทดสอบ
                    if (d.tester_id) {
                        $('#tester_id').empty();
                        let testerOption = new Option(d.tester_fullname, d.tester_id, true, true);
                        $('#tester_id').append(testerOption).trigger('change');
                    }

                    // 2.8 จัดการ Select2: ผู้ทวนสอบ
                    if (d.verifier_id) {
                        $('#verifier_id').empty();
                        let verifierOption = new Option(d.verifier_fullname, d.verifier_id, true, true);
                        $('#verifier_id').append(verifierOption).trigger('change');
                    }

                    // 2.9 ตรวจสอบความถูกต้องของวันที่หลังหยอดค่า
                    validateValidationDates();

                    // --- ส่วนที่ 2: จัดการดึงรูปภาพเดิมมาแสดง ---

                    // 1. ล้างค่าใน Store รูปภาพเดิมก่อน
                    attachmentStoreValidate = [];
                    deletedExistingPhotosValidate = []; // ล้างคิวการลบรูปภาพ

                    // 2. ถ้ามีข้อมูลรูปภาพส่งกลับมา
                    if (d.photos && d.photos.length > 0) {
                        d.photos.forEach(p => {
                            attachmentStoreValidate.push({
                                db_file_id: p.id, // ID จริงจากตาราง attachment
                                filename: p.original_name, // ชื่อไฟล์เดิม
                                // เรียกผ่าน API get_attachment เพื่อพ่นรูป Binary
                                src: `./api/Transaction_chemical_validation/get_attachment.php?id=${p.id}`,
                                size: formatFileSize(p.file_size),
                                date: p.createdate_show,
                                isExisting: true // บอกระบบว่าเป็นรูปเก่าที่มีอยู่แล้ว
                            });
                        });
                    }

                    // 3. สั่งวาด Grid รูปภาพใหม่ทันที
                    renderAttachmentStoreValidate();

                    $('#btn_save_all').prop('disabled', false).attr('title', '');

                    // 2.6 แสดง Modal
                    $('#addEditValidationModal').modal('show');
                }
            },
            error: function() {
                Swal.fire('Error', 'ไม่สามารถดึงข้อมูลได้', 'error');
            }
        });
    });

    // ฟังก์ชันสำหรับลบรายการเตรียมสารเคมี
    $(document).on('click', '.js-delete-val-btn', function(e) {
        e.preventDefault();

        const dataId = $(this).data('id');
        const chemName = $(this).data('name');
        const testDate = $(this).data('test-date');
        const amountUsed = $(this).data('amount');
        const deptName = $(this).data('department') || 'ไม่ระบุหน่วยงาน';

        if (!dataId) {
            Swal.fire('Error', 'ไม่พบ ID ของรายการ', 'error');
            return;
        }

        Swal.fire({
            title: 'ยืนยันการลบ?',
            html: `
                <div class="text-center mb-3">คุณต้องการลบข้อมูลการทดสอบนี้ใช่หรือไม่?</div>
                <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">ชื่อสารที่ทดสอบ:</span><br>
                        <b class="ps-2 text-danger">${chemName}</b>
                    </div>
                    
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">หน่วยงานต้นสังกัด:</span><br>
                        <b class="ps-2 text-danger">${deptName}</b>
                    </div>

                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">วันที่ทดสอบ:</span><br>
                        <b class="ps-2 text-danger">${testDate}</b>
                    </div>
                    
                    <div>
                        <span class="text-secondary small">ปริมาณที่ใช้ทดสอบ (จะคืนยอดให้):</span><br>
                        <b class="ps-2 text-danger">${amountUsed}</b>
                    </div>
                </div>

                <div class="alert alert-warning small mt-3 mb-0 border-0 shadow-sm text-start">
                    <i class="fas fa-exclamation-triangle me-1"></i> 
                    <b>ระบบจะคืนยอดปริมาณสารที่ดึงมาทดสอบ</b> กลับเข้าสู่ "รายการสารที่เตรียมไว้" ให้โดยอัตโนมัติ
                </div>

                <div class="mt-3 text-center">
                    <small class="text-muted italic" style="font-size: 0.75rem;">
                        *หมายเหตุ: หากรายการนี้ทำการทวนสอบ (Verified) แล้ว จะไม่สามารถลบได้
                    </small>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> ลบและคืนยอดสาร',
            cancelButtonText: 'ยกเลิก',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    url: './api/Transaction_chemical_validation/delete.php',
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
                }).catch(error => {
                    // ดึงข้อความ Error จาก JSON ที่ Server ส่งกลับมา (ถ้ามี)
                    let errMsg = 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
                    if (error.responseJSON && error.responseJSON.message) {
                        errMsg = error.responseJSON.message;
                    } else if (error.message) {
                        errMsg = error.message;
                    }
                    Swal.showValidationMessage(`ไม่สามารถลบได้: ${errMsg}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'ลบสำเร็จ!',
                    text: 'ลบประวัติการทดสอบและคืนยอดสารเรียบร้อยแล้ว',
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
        window.location.href = './api/Transaction_chemical_validation/exportExcel.php?' + formData;
    });

    // ฟังก์ชันสำหรับออกรายงาน โดย filter เฉพาะรายการที่ได้ผ่านการค้นหาเท่านั้น / ถ้าไม่ได้ค้นหาโดยมี filter ก็จะ export ทุกรายการ
    $('#btn_export_pdf').on('click', function() {
        // 1. ดึงค่าจาก Filter
        const startDate = $('#filter_val_start').val();
        const endDate = $('#filter_val_end').val();
        const chemicalId = $('#filter_chemical_id').val();
        const chemicalName = $('#filter_chemical_id option:selected').text().trim();
        // เช็ค filter อื่นๆ (ผู้ทดสอบ, ผลทดสอบ, สถานะทวนสอบ)
        const hasOtherFilters = $('#filter_tester').val() || $('#filter_res_acid_base').val() || $('#filter_res_blood').val() || $('#filter_is_verified').val();

        let namePart = "";
        const fileNamePrefix = "F-CS-07";

        // 2. ตรรกะการตั้งชื่อไฟล์
        if (startDate && endDate) {
            namePart = `from_${startDate}_to_${endDate}`;
        } else if (startDate) {
            namePart = `from_${startDate}`;
        } else if (endDate) {
            namePart = `until_${endDate}`;
        } else {
            // กรณีไม่มีวันที่เลย: ใส่ Snapshot Date
            let subPart = "All-Records";
            if (chemicalId && chemicalName !== "-- ทั้งหมด --") {
                subPart = chemicalName.replace(/[/\\?%*:|"<>]/g, '-').replace(/\s+/g, '-');
            } else if (hasOtherFilters) {
                subPart = "Filtered";
            }

            const exportDate = new Date().toISOString().slice(0, 10);
            namePart = `${subPart}_${exportDate}`;
        }


        // ยุบขีดซ้ำ (ถ้ามี)
        namePart = namePart.replace(/-+/g, '-').replace(/[-_]$|(?<=_)-/g, '');
        const fileName = `${fileNamePrefix}_${namePart}.pdf`;

        // 2. ตรวจสอบจำนวนข้อมูลก่อน (Optional: กันการ export ข้อมูลว่าง)
        const currentCount = parseInt($('#count_display').text()) || 0;
        if (currentCount === 0) {
            Swal.fire('ไม่พบข้อมูล', 'ไม่มีรายการตามเงื่อนไขที่เลือก ไม่สามารถออกรายงานได้', 'warning');
            return;
        }

        // 3. ยืนยันการออกรายงาน
        Swal.fire({
            title: 'ยืนยันการออกรายงาน PDF?',
            text: `ระบบจะดึงข้อมูลทั้งหมด ${currentCount} รายการตามตัวกรองปัจจุบันมาสร้างรายงาน`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'ตกลง, ออกรายงาน',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                // 4. ส่งค่าไปยังไฟล์ PHP สำหรับสร้าง PDF โดยไปเปิดเป็นหน้าต่างใหม่
                const filterData = $('#searchFilterForm').serialize();
                const exportUrl = `./api/Transaction_chemical_validation/gen_pdf.php/${fileName}?${filterData}`;
                window.open(exportUrl, '_blank');
            }
        });
    });

    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    // ฟังก์ชันบันทึกข้อมูลการเตรียมสารเคมี
    $('#chemicalValidationForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        const $form = $(form);

        // 1. Custom Validation: ตรวจสอบตรรกะของวันที่ เรียกใช้ฟังก์ชันที่เราแยกไว้ เพื่อความชัวร์อีกครั้งก่อนส่ง

        // 1.1 ตรวจสอบวันที่ทดสอบ (เรียกใช้ฟังก์ชันเดิมที่เราทำไว้)
        validateValidationDates();
        if (form.querySelector('#val_test_date.is-invalid')) {
            $('#val_test_date').focus();
            return; // หยุดการทำงานถ้าวันที่ผิด (Swal แจ้งเตือนอยู่ในฟังก์ชัน validate แล้ว)
        }

        // 1.2 ตรวจสอบ: ผู้ทวนสอบ ห้ามเป็นคนเดียวกับ ผู้ทดสอบ
        const testerId = $('#tester_id').val();
        const verifierId = $('#verifier_id').val();

        if (testerId && verifierId && testerId === verifierId) {
            $('#verifier_id').next('.select2-container').addClass('border border-danger rounded');
            $('#verifier_error').removeClass('d-none'); // แสดงตัวหนังสือสีแดงเตือน

            Swal.fire({
                icon: 'error',
                title: 'ผู้ดำเนินการไม่ถูกต้อง',
                text: 'ผู้ทวนสอบและผู้ทดสอบต้องเป็นคนละคนกัน',
                confirmButtonColor: '#d33',
                returnFocus: false
            });
            return;
        } else {
            $('#verifier_id').next('.select2-container').removeClass('border border-danger');
            $('#verifier_error').addClass('d-none');
        }

        // 2. ตรวจสอบความถูกต้องเบื้องต้น (HTML5 Validation)
        if (!form.checkValidity()) {
            e.stopPropagation();
            $form.addClass('was-validated');

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

        // --- 3. เตรียมส่งข้อมูล (จัดการฟิลด์ที่ Disabled) ---
        const isEditMode = $('#val_edit_id').val() !== "";

        // ปลดล็อคฟิลด์ชั่วคราวเพื่อให้ FormData ดึงค่า ID สารที่เลือกไปได้ (กรณีโหมดแก้ไขที่โดนล็อคไว้)
        $('#prep_id').prop('disabled', false);

        const formData = new FormData(form);

        // ถ้าเป็นโหมดแก้ไข ให้สั่งกลับมาล็อคเหมือนเดิมทันทีหลังจากดึงค่าเสร็จ
        if (isEditMode) {
            $('#prep_id').prop('disabled', true);
        }

        // วนลูปเอาไฟล์รูปภาพที่เลือกไว้ใส่ลงใน FormData
        attachmentStoreValidate.forEach((item, index) => {
            if (item.file) { // ถ้าเป็นไฟล์ใหม่
                formData.append('validate_photos[]', item.file);
            }
        });

        // ส่งรายชื่อ ID รูปภาพเดิมที่ต้องการลบ (สำหรับโหมดแก้ไข)
        if (deletedExistingPhotosValidate.length > 0) {
            formData.append('deleted_photo_ids', JSON.stringify(deletedExistingPhotosValidate));
        }

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

                    let isNewRecord = !$('#val_edit_id').val();

                    // 1. กำหนดข้อความให้สอดคล้องกับสิ่งที่จะเกิดขึ้นจริง
                    let swalText = isNewRecord ?
                        'กำลังนำคุณไปหน้ารายละเอียดการทดสอบสารเคมี...' :
                        'ข้อมูลถูกปรับปรุงเรียบร้อยแล้ว';

                    let swalTimer = isNewRecord ?
                        2000 : 1500;

                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: swalText,
                        timer: swalTimer,
                        showConfirmButton: false
                    }).then(() => {
                        // ถ้าเป็นเพิ่มใหม่ (isNewRecord) และมี ID ส่งกลับมา
                        if (isNewRecord && response.insert_id) {
                            window.location.href = 'trans_chemical_validation_detail.php?id=' + response.insert_id;
                        } else {
                            // ถ้าเป็นแก้ไข แค่ปิด Modal และโหลดตารางใหม่
                            $('#addEditValidationModal').modal('hide');
                            const currentPage = parseInt($('#pagination-list .active a').text()) || 1;
                            if (typeof searchPage === "function") {
                                searchPage(isEditMode ? currentPage : 1);
                            }
                        }
                    });
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', response.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                }
            },
            error: function(xhr) {
                console.error("AJAX Error:", xhr.responseText);
                Swal.fire('Server Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ หรือเกิดข้อผิดพลาดภายใน', 'error');
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
            url: './api/Transaction_chemical_validation/searchData.php',
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

    // ฟังก์ชันสร้าง Badge สำหรับผลการทดสอบ (ใช้ได้ / ใช้ไม่ได้)
    function getResultBadge(result) {
        // 1. กรณีใช้ได้ (Pass)
        if (result === 'pass') {
            return `
            <span class="text-success fw-bold">
                ใช้ได้
            </span>`;
        }

        // 2. กรณีใช้ไม่ได้ (Fail)
        else if (result === 'fail') {
            return `
            <span class="text-danger fw-bold">
                ใช้ไม่ได้
            </span>`;
        }

        // 3. กรณีไม่มีข้อมูล / ยังไม่ได้ทดสอบ
        else {
            return `
            <span class="badge rounded-pill border border-secondary border-opacity-25 bg-light text-secondary px-3 py-2" style="font-size: 0.75rem;">
                <i class="fas fa-minus me-1"></i>ไม่ได้ทดสอบ
            </span>`;
        }
    }

    function renderTable(data, offset) {
        let html = '';
        let start_no = (isNaN(parseInt(offset))) ? 1 : parseInt(offset) + 1;

        if (data && data.length > 0) {
            data.forEach(function(row, i) {

                // --- 1. เตรียมข้อมูลพื้นฐาน ---
                let isVerified = parseInt(row.is_verified) === 1;
                let test_date = row.test_date_show || '-';
                let prep_name = row.prep_name || '-';
                let source_lot = row.source_lot_number || '-';
                let tester = row.tester_fullname || '-';
                let verifier = row.verifier_fullname || '-';
                let rawDepartmentName = row.department_name || '-';
                let departmentName = row.department_name || '<span class="text-muted small">ไม่ระบุ</span>';

                let statusBadge = isVerified ?
                    `<span class="badge rounded-pill bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-2" style="font-size: 0.8rem;">
                        ทวนสอบแล้ว
                    </span>` :
                    `<span class="badge rounded-pill bg-warning bg-opacity-25 text-dark border border-warning border-opacity-50 px-3 py-2" style="font-size: 0.8rem;">
                        รอการทวนสอบ
                    </span>`;

                // ปริมาณที่ดึงมาทดสอบ
                let amountUsed = numberWithCommas(row.amount_used);
                let unitText = row.unit_display || '';

                // --- จัดการปุ่มจัดการ (Lock ถ้า Verify แล้ว) ---
                let actionButtons = '';
                if (isVerified) {
                    // ถ้าทวนสอบแล้ว: แสดงไอคอน Lock แทน หรือทำให้ปุ่มจางและกดไม่ได้
                    actionButtons = `
                    <i class="fas fa-lock text-muted opacity-50" title="ทวนสอบแล้ว ไม่สามารถแก้ไขหรือลบได้"></i>
                `;
                } else {
                    // ถ้ายังไม่ทวนสอบ: แสดงปุ่มแก้ไข/ลบ ปกติ
                    actionButtons = `
                    <i class="fas fa-pen-to-square fa-lg text-warning ${window._canManage ? 'js-edit-val-btn cursor-pointer' : ''}" 
                        data-id="${row.id}" 
                        title="${window._canManage ? 'แก้ไข' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                    <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-val-btn cursor-pointer' : ''}" 
                        data-id="${row.id}" 
                        data-name="${prep_name}" 
                        data-test-date="${test_date}"
                        data-amount="${amountUsed} ${unitText}"
                        data-department="${rawDepartmentName}"
                        title="${window._canManage ? 'ลบ' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                `;
                }

                html += `
                    <tr class="clickable-row cursor-pointer" data-id="${row.id}" title="ดูข้อมูล">
                        <td class="text-center align-middle">
                            <div class="d-flex justify-content-center gap-2">
                                ${actionButtons}
                            </div>
                        </td>
                        <td class="text-center align-middle d-none d-xl-table-cell">${start_no + i}</td>
                        <td class="text-center align-middle">${test_date}</td>
                        
                        <td class="text-start align-middle">
                            <div class="chem-name-responsive text-dark">${prep_name}</div>
                            
                            <div class="d-xl-none text-muted mt-1" style="font-size: 0.75rem;">
                                ผู้ทดสอบ: ${tester}
                            </div>
                        </td>

                        <td class="text-start align-middle d-none d-md-table-cell">
                            ${departmentName}
                        </td>

                        <td class="text-center align-middle">
                            ${getResultBadge(row.res_acid_base)}
                        </td>

                        <td class="text-center align-middle">
                            ${getResultBadge(row.res_blood)}
                        </td>

                        <td class="text-start align-middle d-none d-xl-table-cell">
                            ${tester}
                        </td>

                        <td class="text-start align-middle d-none d-lg-table-cell">
                            ${verifier}
                        </td>

                        <td class="text-center align-middle">${statusBadge}</td>
                    </tr>
                    `;
            });
        } else {
            html = `
                <tr>
                    <td colspan="10" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-vial fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0 fw-bold">ไม่พบประวัติการทดสอบสารเคมีในระบบ</p>
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