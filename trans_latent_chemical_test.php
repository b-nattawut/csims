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
                    CONCAT(IFNULL(r.rank_name, ''), ' ', t2.first_name, ' ', t2.last_name) AS fullname
                FROM users t1
                INNER JOIN user_profile t2 ON t1.user_id = t2.user_id
                LEFT JOIN user_rank r ON t2.rank_id = r.rank_id
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

$title = "การตรวจความพร้อมสารเคมีลายนิ้วมือแฝง";
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

    /* ล็อก Scroll หน้าหลัง */

    .js-edit-latent-btn,
    .js-delete-latent-btn {
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
                    <div class="row g-2 justify-content-start align-items-end">

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <button class="btn <?= $canManage ? 'btn-success' : 'btn-secondary' ?> btn-sm px-4 fw-bold shadow-sm text-nowrap w-100 <?= $canManage ? 'js-add-latent-btn' : '' ?>"
                                style="height: 31px;" type="button" <?= $canManage ? 'data-bs-toggle="modal" data-bs-target="#addEditLatentModal"' : 'disabled' ?>
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
                                <input type="date" id="filter_test_start" name="filter_test_start" class="form-control form-control-sm">
                                <span class="input-group-text">ถึง</span>
                                <input type="date" id="filter_test_end" name="filter_test_end" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_ph" class="form-label text-muted small mb-1">ค่า pH</label>
                            <input type="number" id="filter_ph" name="filter_ph" step="0.1" class="form-control form-control-sm" placeholder="เช่น 7.0">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_color" class="form-label text-muted small mb-1">ผลลิตมัส</label>
                            <select id="filter_color" name="filter_color" class="form-select form-select-sm">
                                <option value="">-- ทั้งหมด --</option>
                                <option value="changed">เปลี่ยนสี</option>
                                <option value="unchanged">ไม่เปลี่ยนสี</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_readiness" class="form-label text-muted small mb-1">ความพร้อมการใช้งาน</label>
                            <select id="filter_readiness" name="filter_readiness" class="form-select form-select-sm">
                                <option value="">-- ทั้งหมด --</option>
                                <option value="ready">พร้อมใช้งาน</option>
                                <option value="not_ready">ไม่พร้อมใช้งาน</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_is_verified" class="form-label text-muted small mb-1">สถานะการทวนสอบ</label>
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
                    <th style="min-width: 80px;">Action</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 50px;">ลำดับ</th>
                    <th style="min-width: 110px;">วันที่ทดสอบ</th>
                    <th style="min-width: 220px;">รายการสารเคมีที่ทดสอบ</th>
                    <th class="d-none d-md-table-cell" style="min-width: 150px;">หน่วยงาน</th>

                    <th class="d-none d-lg-table-cell" style="min-width: 80px;">pH</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 120px;">ผลลิตมัส</th>

                    <th style="min-width: 140px;">ความพร้อมการใช้งาน</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 150px;">ผู้ทดสอบ</th>
                    <th class="d-none d-xxl-table-cell" style="min-width: 150px;">ผู้ทวนสอบ</th>
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

<!-- Modal สำหรับเพิ่มรายการตรวจความพร้อมสารเคมีใหม่ / แก้ไขรายการเดิม -->
<div class="modal fade" id="addEditLatentModal" aria-labelledby="addEditLatentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addEditLatentModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> <span id="textModal">เพิ่มรายการ</span>
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" id="latentChemicalTestForm" action="./api/Transaction_latent_chemical_test/save.php" novalidate>
                    <input type="hidden" id="latent_edit_id" name="id">

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border mb-3">
                        <legend class="fieldset-header">
                            <i class="fas fa-search me-2"></i>เลือกสารเคมีที่ต้องการตรวจสอบ
                        </legend>
                        <div class="row g-3 justify-content-start">

                            <div class="col-12 col-xl-8">
                                <label for="prep_id" class="form-label fw-bold">ค้นหาสารที่เตรียมไว้ <span class="text-danger">*</span></label>
                                <select id="prep_id" name="prep_id" class="form-select select2-modal-val" required data-placeholder="-- ค้นหาชื่อสารที่เตรียม หรือเลข Lot --">
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาเลือกสารเคมีที่ต้องการตรวจสอบ</div>
                            </div>

                            <div class="col-12 col-xl-4">
                                <label for="latent_amount_used" class="form-label fw-bold">ปริมาณที่ดึงมาทดสอบ <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text"
                                        id="latent_amount_used"
                                        name="amount_used"
                                        class="form-control"
                                        placeholder="เช่น 10.00"
                                        inputmode="decimal"
                                        required
                                        oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');">
                                    <span class="input-group-text" id="latent_unit_display">หน่วย</span>
                                </div>
                                <div id="latent_stock_info" class="form-text text-primary ml-1"></div>
                            </div>

                            <div class="col-md-12 mt-3" id="latent_chemical_detail_section" style="display: none;">
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
                            <i class="fas fa-vial me-2"></i>ผลการตรวจสอบความพร้อม
                        </legend>
                        <div class="row g-3 justify-content-start">

                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold d-block mb-2 border-bottom">ความพร้อมในการใช้งาน <span class="text-danger">*</span></label>
                                <div class="bg-light p-3 rounded-3 border d-flex justify-content-center gap-3">
                                    <input type="radio" class="btn-check" name="readiness_status" id="status_ready" value="ready" required autocomplete="off">
                                    <label class="btn btn-outline-success flex-fill fw-bold shadow-sm py-2" for="status_ready">
                                        <i class="fas fa-check-circle me-2"></i> พร้อม
                                    </label>

                                    <input type="radio" class="btn-check" name="readiness_status" id="status_not_ready" value="not_ready" autocomplete="off">
                                    <label class="btn btn-outline-danger flex-fill fw-bold shadow-sm py-2" for="status_not_ready">
                                        <i class="fas fa-times-circle me-2"></i> ไม่พร้อม
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 mb-3">
                                <label for="latent_remark" class="form-label">หมายเหตุ</label>
                                <div class="input-group input-group-seamless">
                                    <textarea
                                        id="latent_remark"
                                        name="remark"
                                        class="form-control"
                                        rows="3"
                                        placeholder="ระบุรายละเอียดเพิ่มเติม (ถ้ามี) เช่น พบตะกอนเล็กน้อย, สีของสารเริ่มจางลง"></textarea>
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="latent_remark"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border mb-3">
                        <legend class="fieldset-header">
                            <i class="fas fa-eyedropper me-2"></i>ผลการทดสอบด้วยกระดาษลิตมัส
                        </legend>
                        <div class="row g-3 justify-content-start">
                            <div class="col-12 mb-3">
                                <label for="litmus_ph_value" class="form-label fw-bold">ค่า pH <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text text-secondary fw-bold">pH</span>
                                    <input type="number"
                                        id="litmus_ph_value"
                                        name="ph_value"
                                        class="form-control"
                                        placeholder="เช่น 7.0 (ระบุได้ระหว่าง 0.0 - 14.0)"
                                        min="0"
                                        max="14"
                                        step="0.1"
                                        required
                                        oninput="if(value > 14) value = 14; if(value < 0) value = 0;">
                                </div>
                                <div class="invalid-feedback">กรุณาระบุค่า pH (0-14)</div>
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold d-block mb-2 border-bottom">การเปลี่ยนสีของกระดาษ <span class="text-danger">*</span></label>
                                <div class="bg-light p-3 rounded-3 border d-flex gap-2">
                                    <input type="radio" class="btn-check" name="color_change_status" id="color_changed" value="changed" required autocomplete="off">
                                    <label class="btn btn-outline-primary flex-fill fw-bold shadow-sm py-2" for="color_changed">
                                        <i class="fas fa-fill-drip me-2"></i> เปลี่ยนสีทันที
                                    </label>

                                    <input type="radio" class="btn-check" name="color_change_status" id="color_unchanged" value="unchanged" autocomplete="off">
                                    <label class="btn btn-outline-warning flex-fill fw-bold shadow-sm py-2" for="color_unchanged">
                                        <i class="fas fa-ban me-2"></i> ไม่เปลี่ยนสี
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

                            <div class="col-12 g-3">
                                <label for="latent_test_date" class="form-label fw-bold">วันที่ทำการตรวจความพร้อม <span class="text-danger">*</span></label>
                                <input type="date" id="latent_test_date" name="test_date" class="form-control" required>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label for="tester_id" class="form-label fw-bold">ผู้ทดสอบ <span class="text-danger">*</span></label>
                                <select id="tester_id" name="tester_id" class="form-select select2-val-tester" required data-placeholder="-- ค้นหาชื่อเจ้าหน้าที่ผู้ทดสอบ --">
                                    <option value=""></option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
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
                                    <button type="button" class="btn btn-primary px-4 shadow-sm" id="btn_choose_file_latent">
                                        <i class="fas fa-folder-open me-2"></i>เลือกไฟล์รูปภาพ
                                    </button>
                                    <button type="button" class="btn btn-success px-4 shadow-sm" id="btn_open_camera_latent">
                                        <i class="fas fa-camera me-2"></i>เปิดกล้องถ่ายภาพ
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <input type="file" id="latent_photos" name="latent_photos[]" accept="image/*" style="display: none;" multiple>
                                <input type="file" id="camera_input_latent" name="camera_photos_latent[]" accept="image/*" capture="environment" style="display: none;" multiple>

                                <div id="uploading_container_latent" class="mb-2"></div>

                                <div id="attachments_wrapper_latent" class="d-none mt-3">
                                    <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2">
                                        จำนวนภาพที่แนบ
                                        <span class="badge bg-secondary badge-circle ms-1" id="file_count_badge_latent">0</span>
                                    </h6>
                                    <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-3" id="attachments_grid_latent">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <div class="alert alert-secondary d-flex align-items-center mb-0" role="alert">
                        <i class="fas fa-info-circle me-2 fs-5"></i>
                        <div class="small">
                            เมื่อบันทึกข้อมูลเบื้องต้นแล้ว ระบบจะนำคุณไปยังหน้า <b>"รายละเอียดการตรวจความพร้อมสารเคมี"</b> เพื่อดูรายละเอียดและลงนามทวนสอบในขั้นตอนถัดไป
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer d-flex justify-content-end bg-white border-top shadow-sm">
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="latentChemicalTestForm">
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
    window._sessionUserId = '<?= $_SESSION["user_id"] ?>';
    window._canManage = <?= $canManage ? 'true' : 'false' ?>;
    let attachmentStoreLatent = [];
    let deletedExistingPhotosLatent = [];

    $(document).ready(function() {

        loadDepartments();

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
            dropdownParent: $('#addEditLatentModal'),
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
            dropdownParent: $('#addEditLatentModal'),
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
            dropdownParent: $('#addEditLatentModal'),
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
            const $amountInput = $('#latent_amount_used');

            // หยอดข้อมูลลงในส่วน Auto-fill (Read-only) --> ใน api จะไปดึงจาก master, inventory, prepare มาหยอด
            $('#auto_chemical_name').text(data.chemical_name || '-'); // ชื่อจาก Master
            $('#auto_brand_name').text(data.chemical_brand || '-'); // ยี่ห้อจาก Master
            $('#auto_source_lot').text(data.source_lot_number || '-'); // Lot จาก Inventory
            $('#auto_prep_date').text(data.prep_date_show || '-'); // วันที่เตรียม
            $('#auto_exp_date').text(data.exp_date_show || '-'); // วันหมดอายุของที่เตรียม
            $('#auto_department_name').text(data.department_name || '-');


            // แสดงหน่วยนับตั้งต้น
            const unitShow = data.unit_display || 'หน่วย';
            $('#latent_unit_display').text(unitShow);

            // เก็บค่าไว้คำนวณ Validation
            $amountInput.data('current-stock', parseFloat(data.quantity_remaining || 0));
            $amountInput.data('original-used', 0);
            $amountInput.data('unit-display', unitShow);
            $amountInput.data('prep-name', data.prep_name);
            $amountInput.data('source-lot', data.source_lot_number);

            $('#latent_chemical_detail_section').fadeIn(300);
            // ย้ายโฟกัสไปช่องปริมาณ
            $('#latent_amount_used').focus();

            updateLatentStockValidation($amountInput.val());
        });

        // --- เมื่อมีการล้างค่า (Clear) ---
        $('#prep_id').on('select2:unselect select2:clear', function() {
            resetLatentChemicalDetails(true);
        });

        // ดักจับการกรอกปริมาณที่ดึงมาทดสอบ
        $('#latent_amount_used').on('input', function() {
            updateLatentStockValidation($(this).val());
        });

        $('#latent_test_date').on('change', function() {
            validateLatentDates();
        });

        // --- ดัก Event เมื่อมีการเลือก เพื่อให้ทั้งสองช่อง Update สถานะกันเอง ---
        $('#tester_id, #verifier_id').on('change', function() {
            validateUserSync(); // ฟังก์ชันเช็ค Error แดงๆ ที่คุณเขียนไว้
        });

        // Event Listeners สำหรับปุ่มเลือกไฟล์และกล้อง
        $('#btn_choose_file_latent').on('click', function() {
            $('#latent_photos').click();
        });

        $('#btn_open_camera_latent').on('click', function() {
            $('#camera_input_latent').click();
        });

        // จัดการเมื่อมีการเลือกไฟล์หรือถ่ายภาพ
        $('#latent_photos, #camera_input_latent').on('change', function(e) {
            const files = e.target.files;
            if (!files.length) return;

            const totalFiles = files.length;
            let processedCount = 0;

            const $uploadingBox = $('#uploading_container_latent');
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
                        attachmentStoreLatent.unshift({
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
                                renderAttachmentStoreLatent();
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

    function resetLatentChemicalDetails(isSmooth = false) {
        const $section = $('#latent_chemical_detail_section');

        if (isSmooth) {
            // กรณี User กดล้างค่าเองขณะ Modal เปิดอยู่ (ใช้ Fade Out)
            $section.fadeOut(300);
        } else {
            // กรณีล้างฟอร์มตอนเปิด Modal หรือปิด Modal (ซ่อนทันที ไม่ขยับ)
            $section.hide();
        }

        // ล้างข้อมูลอื่นๆ (Immediate)
        $('#auto_chemical_name, #auto_source_lot, #auto_brand_name, #auto_prep_date, #auto_exp_date, #auto_department_name').text('-');
        $('#latent_unit_display').text('หน่วย');
        $('#latent_stock_info').empty();
        $('#latent_amount_used').val('').removeClass('is-invalid');
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
    function renderAttachmentStoreLatent() {
        const $grid = $('#attachments_grid_latent');
        const $wrapper = $('#attachments_wrapper_latent');
        const $badge = $('#file_count_badge_latent');

        if (!$grid.length) return; // กันพังถ้าหา Element ไม่เจอ

        $grid.empty();
        const totalCount = attachmentStoreLatent.length;
        $badge.text(totalCount);

        if (totalCount > 0) {
            $wrapper.removeClass('d-none');
            attachmentStoreLatent.forEach((file, index) => {
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
                            <button type="button" class="btn btn-outline-primary btn-action-sm" onclick="showImagePreviewLatent(${index})">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-action-sm" onclick="removeFileFromStoreLatent(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="img-box-middle" onclick="showImagePreviewLatent(${index})" style="cursor:zoom-in;">
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
    function removeFileFromStoreLatent(index) {
        const file = attachmentStoreLatent[index];
        if (file.isExisting && file.db_file_id) {
            deletedExistingPhotosLatent.push(file.db_file_id);
        }

        attachmentStoreLatent.splice(index, 1);
        renderAttachmentStoreLatent();
    }

    // ระบบ Lightbox (ดูรูปขยาย)
    function showImagePreviewLatent(index) {
        const file = attachmentStoreLatent[index];
        $('#lightboxImageLatent').attr('src', file.src || file.base64); // ใช้ ID Lightbox เดิมได้
        $('#lightboxFileNameLatent').text(file.filename);
        $('#customLightboxLatent').removeClass('d-none').hide().fadeIn(200);
        $('body').addClass('lightbox-open');
    }

    function closeLightboxLatent() {
        $('#customLightboxLatent').fadeOut(200, function() {
            $(this).addClass('d-none');
        });
        $('body').removeClass('lightbox-open');
    }

    function closeLightboxOutsideLatent(event) {
        // ตรวจสอบว่า ID ที่คลิกคือตัวพื้นหลัง จริงๆ ไม่ใช่ตัวรูปภาพ
        if (event.target.id === 'customLightboxLatent') {
            closeLightboxLatent();
        }
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

    // ฟังก์ชันคำนวณยอดคงเหลือ Real-time สำหรับหน้า latent
    function updateLatentStockValidation(inputValue) {
        const $input = $('#latent_amount_used');
        const $info = $('#latent_stock_info');

        const currentStock = parseFloat($input.data('current-stock')) || 0;
        const originalUsed = parseFloat($input.data('original-used')) || 0;
        const prepName = $input.data('prep-name') || 'ไม่ระบุชื่อ';
        const sourceLot = $input.data('source-lot') || 'ไม่ระบุล็อต';

        const currentInput = parseFloat(inputValue) || 0;
        const unitShow = $input.data('unit-display') || 'หน่วย';

        const totalAvailable = currentStock + originalUsed;
        const realTimeRemain = totalAvailable - currentInput;

        const isOver = realTimeRemain < 0;
        const colorClass = isOver ? 'text-danger' : 'text-primary';
        const warningText = isOver ? ' <small class="fw-bold">(เกินยอดที่มี!)</small>' : '';
        const labelText = (inputValue === '' || currentInput === 0) ? 'ปริมาณสารที่เตรียมไว้คงเหลือ:' : 'คงเหลือหลังหักออก:';

        $info.removeClass('text-primary text-danger').addClass(colorClass);
        $info.html(`
            <div class="d-flex flex-column">
                <span class="mb-1"><i class="fas fa-flask me-1"></i> สารที่เลือก: <b>${prepName} (Lot: ${sourceLot})</b></span>
                <span><i class="fas fa-info-circle"></i> ${labelText} <b>${numberWithCommas(realTimeRemain)} ${unitShow}</b>${warningText}</span>
            </div>
        `);

        isOver ? $input.addClass('is-invalid') : $input.removeClass('is-invalid');
    }

    // --- ฟังก์ชันตรวจสอบความถูกต้องของวันที่ทดสอบ (Validation) ---
    function validateLatentDates() {
        const testInput = document.getElementById('latent_test_date');
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
        if ($(e.target).closest('.js-edit-latent-btn, .js-delete-latent-btn, button, i').length) {
            return;
        }

        const id = $(this).data('id');
        if (id) {
            // เปลี่ยนหน้าไปยังหน้ารายละเอียด (Detail Page)
            window.location.href = `trans_latent_chemical_test_detail.php?id=${id}`;
        }
    });

    // 1. Logic สำหรับปุ่ม "เพิ่มรายการ" (Add)
    $(document).on('click', '.js-add-latent-btn', function() {
        const $form = $('#latentChemicalTestForm');

        // 1.1 ล้างข้อมูลและ Class ของ Bootstrap
        $form[0].reset();
        $form.removeClass('was-validated');
        // 1.2 ล้าง Manual Class (is-invalid, is-valid)
        $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');
        const inputsToClear = ['latent_test_date', 'latent_amount_used'];
        inputsToClear.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.setCustomValidity('');
        });

        // 1.3 รีเซ็ต ID (Hidden) และ Select2 ทั้งหมด
        $('#latent_edit_id').val('');
        $('#prep_id, #tester_id, #verifier_id').val(null).trigger('change');
        resetLatentChemicalDetails(false);

        // 1.4 ล้างส่วนแสดงข้อมูลสารเคมีที่ดึงมา (Auto-fill Spans)
        $('#auto_chemical_name, #auto_brand_name, #auto_source_lot, #auto_prep_date, #auto_exp_date, #auto_department_name').text('-');
        $('#latent_unit_display').text('หน่วย');
        $('#latent_stock_info').empty();
        $('#latent_remark').val('');
        $('#verifier_error').addClass('d-none'); // ซ่อน Error ของผู้ทวนสอบ

        // 1.6 ล้างตัวแปรที่เก็บข้อมูลรูปภาพ (Store)
        if (typeof attachmentStoreLatent !== 'undefined') {
            attachmentStoreLatent = [];
        }

        // 1.7 ล้าง UI ส่วนแสดงตัวอย่างรูปภาพ
        $('#attachments_grid_latent').empty();
        $('#attachments_wrapper_latent').addClass('d-none');
        $('#file_count_badge_latent').text('0');
        $('#uploading_container_latent').empty();

        // 1.8 ล้างค่าใน Input File (เพื่อให้เลือกไฟล์เดิมซ้ำได้)
        $('#latent_photos, #camera_input_latent').val('');

        // 1.9 เปลี่ยนหัวข้อ Modal
        $('#textModal').text('เพิ่มรายการ');
        $('#addEditLatentModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายการ');

        // 1.10 ตั้งค่าเริ่มต้น: ผู้ทดสอบ = ผู้ใช้ปัจจุบัน, วันที่ทดสอบ = วันนี้
        let currentUserId = '<?php echo $current_user_id; ?>';
        let currentFullname = '<?php echo $current_user_fullname; ?>';

        if (currentUserId && currentFullname !== "-" && currentFullname !== "") {
            // สร้าง Option ใหม่สำหรับ Select2 ของผู้ทดสอบ
            let userOption = new Option(currentFullname, currentUserId, true, true);
            $('#tester_id').append(userOption).trigger('change');
        }

        let today = new Date().toISOString().split('T')[0];
        $('#latent_test_date').val(today);

        // 1.11 ปลดล็อค Select2 (เผื่อกรณีที่เคยถูก Disabled ไว้ตอน Edit)
        $('#prep_id').prop('disabled', false);

        // เคลียร์ข้อมูลที่เก็บไว้ใน data attribute ของช่องกรอกปริมาณ
        $('#latent_amount_used').data('current-stock', 0);

        // ล้างค่า pH
        $('#litmus_ph_value').val('');

        $('input[name="color_change_status"]').prop('checked', false);

        $('#btn_save_all').prop('disabled', false).attr('title', '');
    });

    // 2. Logic สำหรับปุ่ม "แก้ไข" (Edit)
    $(document).on('click', '.js-edit-latent-btn', function() {
        let dataId = $(this).data('id');

        $.ajax({
            url: './api/Transaction_latent_chemical_test/getDataByID.php',
            type: 'GET',
            data: {
                id: dataId
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let d = res.data;
                    const $form = $('#latentChemicalTestForm');
                    const $amountInput = $('#latent_amount_used');

                    // 2.1 เปลี่ยนหัวข้อ Modal
                    $('#textModal').text('แก้ไขข้อมูลการตรวจความพร้อม');
                    $('#addEditLatentModalLabel').html('<i class="fas fa-edit fa-lg me-2"></i> แก้ไขข้อมูลการตรวจความพร้อม');
                    $form.removeClass('was-validated');
                    $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');

                    // 2.2 Map ข้อมูลพื้นฐานลง Form
                    $('#latent_edit_id').val(d.id);
                    $('#latent_test_date').val(d.test_date_raw);
                    $('#latent_remark').val(d.remark || '');
                    $amountInput.val(d.amount_used);

                    // 2.3 จัดการ Radio Buttons (ผลการตรวจความพร้อม)
                    if (d.readiness_status) {
                        $(`input[name="readiness_status"][value="${d.readiness_status}"]`).prop('checked', true);
                    }

                    // 2.3.1 หยอดค่า pH
                    $('#litmus_ph_value').val(d.ph_value || '');

                    // 2.3.2 หยอดค่าผลลิตมัส และ Update สีปุ่ม
                    if (d.color_change_status) {
                        $(`input[name="color_change_status"][value="${d.color_change_status}"]`).prop('checked', true);
                    }

                    // 2.4 หยอดข้อมูลในส่วน Auto-fill Spans (Read-only)
                    $('#auto_chemical_name').text(d.chemical_name || '-'); // ชื่อจาก Master
                    $('#auto_brand_name').text(d.chemical_brand || '-'); // ยี่ห้อจาก Master
                    $('#auto_source_lot').text(d.source_lot_number || '-'); // Lot จาก Inventory
                    $('#auto_prep_date').text(d.prep_date_show || '-'); // วันที่เตรียม
                    $('#auto_exp_date').text(d.exp_date_show || '-'); // วันหมดอายุของที่เตรียม
                    $('#auto_department_name').text(d.department_name || '-');

                    $('#latent_chemical_detail_section').show();
                    $('#latent_unit_display').text(d.unit_display);

                    $amountInput.data('original-used', parseFloat(d.amount_used || 0));
                    $amountInput.data('current-stock', parseFloat(d.prep_remain_now || 0));
                    $amountInput.data('unit-display', d.unit_display);
                    $amountInput.data('prep-name', d.prep_name);
                    $amountInput.data('source-lot', d.source_lot_number);

                    // เรียกฟังก์ชันอัปเดตตัวเลขสต็อก
                    updateLatentStockValidation(d.amount_used);

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
                    validateLatentDates();

                    // --- ส่วนที่ 2: จัดการดึงรูปภาพเดิมมาแสดง ---

                    // 1. ล้างค่าใน Store รูปภาพเดิมก่อน
                    attachmentStoreLatent = [];
                    deletedExistingPhotosLatent = []; // ล้างคิวการลบรูปภาพ

                    // 2. ถ้ามีข้อมูลรูปภาพส่งกลับมา
                    if (d.photos && d.photos.length > 0) {
                        d.photos.forEach(p => {
                            attachmentStoreLatent.push({
                                db_file_id: p.id, // ID จริงจากตาราง attachment
                                filename: p.original_name, // ชื่อไฟล์เดิม
                                // เรียกผ่าน API get_attachment เพื่อพ่นรูป Binary
                                src: `./api/Transaction_latent_chemical_test/get_attachment.php?id=${p.id}`,
                                size: formatFileSize(p.file_size),
                                date: p.createdate_show,
                                isExisting: true // บอกระบบว่าเป็นรูปเก่าที่มีอยู่แล้ว
                            });
                        });
                    }

                    // 3. สั่งวาด Grid รูปภาพใหม่ทันที
                    renderAttachmentStoreLatent();

                    $('#btn_save_all').prop('disabled', false).attr('title', '');

                    // 2.6 แสดง Modal
                    $('#addEditLatentModal').modal('show');
                }
            },
            error: function() {
                Swal.fire('Error', 'ไม่สามารถดึงข้อมูลได้', 'error');
            }
        });
    });

    // ฟังก์ชันสำหรับลบรายการเตรียมสารเคมี
    $(document).on('click', '.js-delete-latent-btn', function(e) {
        e.preventDefault();
        const dataId = $(this).data('id');
        const latentName = $(this).data('name');
        const testDate = $(this).data('test-date');
        const amountUsed = $(this).data('amount');
        const deptName = $(this).data('department') || 'ไม่ระบุหน่วยงาน';

        Swal.fire({
            title: 'ยืนยันการลบ?',
            html: `
                <div class="text-center mb-3">คุณต้องการลบข้อมูลการตรวจความพร้อมนี้ใช่หรือไม่?</div>
                <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">ชื่อสารที่ตรวจสอบ:</span><br>
                        <b class="ps-2 text-danger">${latentName}</b>
                    </div>
                    
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">หน่วยงานต้นสังกัด:</span><br>
                        <b class="ps-2 text-danger">${deptName}</b>
                    </div>

                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">วันที่ตรวจความพร้อม:</span><br>
                        <b class="ps-2 text-danger">${testDate}</b>
                    </div>
                    
                    <div>
                        <span class="text-secondary small">ปริมาณที่ดึงมาตรวจสอบ (จะคืนยอดให้):</span><br>
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
                    url: './api/Transaction_latent_chemical_test/delete.php',
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
                    text: 'คืนยอดสารเข้าขวดเตรียมเรียบร้อยแล้ว',
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
        window.location.href = './api/Transaction_latent_chemical_test/exportExcel.php?' + formData;
    });

    // ฟังก์ชันสำหรับออกรายงาน โดย filter เฉพาะรายการที่ได้ผ่านการค้นหาเท่านั้น / ถ้าไม่ได้ค้นหาโดยมี filter ก็จะ export ทุกรายการ
    $('#btn_export_pdf').on('click', function() {
        const startDate = $('#filter_test_start').val();
        const endDate = $('#filter_test_end').val();
        const chemicalId = $('#filter_chemical_id').val();
        const chemicalName = $('#filter_chemical_id option:selected').text().trim(); // ดึงชื่อสารเคมีแทน ID
        const hasOtherFilters = $('#filter_ph').val() || $('#filter_color').val() || $('#filter_readiness').val() || $('#filter_tester').val() || $('#filter_is_verified').val();

        let namePart = "";
        const fileNamePrefix = "F-CS-21";

        // 2. ตรรกะการลำดับความสำคัญของชื่อ (Match กับ PHP)
        if (startDate && endDate) {
            namePart = `from_${startDate}_to_${endDate}`;
        } else if (startDate) {
            namePart = `from_${startDate}`;
        } else if (endDate) {
            namePart = `until_${endDate}`;
        } else {
            // กรณีอื่นๆ: ใส่ชื่อเงื่อนไข + วันที่ปัจจุบัน (Snapshot Date)
            let subPart = "All-Records";

            if (chemicalId && chemicalName !== "-- ทั้งหมด --") {
                subPart = chemicalName.replace(/[/\\?%*:|"<>]/g, '-').replace(/\s+/g, '-');
            } else if (hasOtherFilters) {
                subPart = "Filtered";
            }

            const exportDate = new Date().toISOString().slice(0, 10); // ได้ YYYY-MM-DD
            namePart = `${subPart}_${exportDate}`;
        }

        namePart = namePart.replace(/-+/g, '-');
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
                const exportUrl = `./api/Transaction_latent_chemical_test/gen_pdf.php/${fileName}?${filterData}`;
                window.open(exportUrl, '_blank');
            }
        });
    });

    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    // ฟังก์ชันบันทึกข้อมูลการเตรียมสารเคมี
    $('#latentChemicalTestForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        const $form = $(form);

        // 1. Custom Validation: ตรวจสอบตรรกะของวันที่ เรียกใช้ฟังก์ชันที่เราแยกไว้ เพื่อความชัวร์อีกครั้งก่อนส่ง

        // 1.1 ตรวจสอบวันที่ทดสอบ (เรียกใช้ฟังก์ชันเดิมที่เราทำไว้)
        validateLatentDates();
        if (form.querySelector('#latent_test_date.is-invalid')) {
            $('#latent_test_date').focus();
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
        const isEditMode = $('#latent_edit_id').val() !== "";

        // ปลดล็อคฟิลด์ชั่วคราวเพื่อให้ FormData ดึงค่า ID สารที่เลือกไปได้ (กรณีโหมดแก้ไขที่โดนล็อคไว้)
        $('#prep_id').prop('disabled', false);

        const formData = new FormData(form);

        // ถ้าเป็นโหมดแก้ไข ให้สั่งกลับมาล็อคเหมือนเดิมทันทีหลังจากดึงค่าเสร็จ
        if (isEditMode) {
            $('#prep_id').prop('disabled', true);
        }

        // วนลูปเอาไฟล์รูปภาพที่เลือกไว้ใส่ลงใน FormData
        attachmentStoreLatent.forEach((item, index) => {
            if (item.file) { // ถ้าเป็นไฟล์ใหม่
                formData.append('latent_photos[]', item.file);
            }
        });

        // ส่งรายชื่อ ID รูปภาพเดิมที่ต้องการลบ (สำหรับโหมดแก้ไข)
        if (deletedExistingPhotosLatent.length > 0) {
            formData.append('deleted_photo_ids', JSON.stringify(deletedExistingPhotosLatent));
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

                    let isNewRecord = !$('#latent_edit_id').val();

                    // 1. กำหนดข้อความให้สอดคล้องกับสิ่งที่จะเกิดขึ้นจริง
                    let swalText = isNewRecord ?
                        'กำลังนำคุณไปหน้ารายละเอียดการตรวจความพร้อมสารเคมี...' :
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
                            window.location.href = 'trans_latent_chemical_test_detail.php?id=' + response.insert_id;
                        } else {
                            // ถ้าเป็นแก้ไข แค่ปิด Modal และโหลดตารางใหม่
                            $('#addEditLatentModal').modal('hide');
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
            url: './api/Transaction_latent_chemical_test/searchData.php',
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
                    $('#table_body').html(`<tr><td colspan="10" class="text-center py-5 text-danger fw-bold">${response.message}</td></tr>`);
                    $('#count_display').text('0');
                }
            },
            error: function(xhr) {
                console.error("Search Error:", xhr.responseText);
                $('#table_body').html('<tr><td colspan="10" class="text-center py-5 text-danger"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์</td></tr>');
                $('#count_display').text('Error');
            }
        });
    }

    // ฟังก์ชันสร้าง Badge สำหรับผลความพร้อมการใช้งาน (Latent Chemical Test)
    function getReadinessBadge(result) {
        // 1. กรณีพร้อมใช้งาน (Ready)
        if (result === 'ready') {
            return `
                <span class="text-success fw-bold">
                    พร้อมใช้งาน
                </span>`;
        }
        // 2. กรณีไม่พร้อมใช้งาน (Not Ready)
        else if (result === 'not_ready') {
            return `
                <span class="text-danger fw-bold">
                    ไม่พร้อมใช้งาน
                </span>`;
        }
        // 3. กรณีไม่มีข้อมูล
        else {
            return `<span class="text-muted small">-</span>`;
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
                let remark = row.remark || '<span class="text-muted opacity-50">-</span>';
                let rawDepartmentName = row.department_name || '-';
                let departmentName = row.department_name || '<span class="text-muted small">ไม่ระบุ</span>';

                let statusBadge = isVerified ?
                    `<span class="badge rounded-pill bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-2" style="font-size: 0.8rem;">
                        ทวนสอบแล้ว
                    </span>` :
                    `<span class="badge rounded-pill bg-warning bg-opacity-25 text-dark border border-warning border-opacity-50 px-3 py-2" style="font-size: 0.8rem;">
                        รอการทวนสอบ
                    </span>`;

                // --- เตรียมข้อมูล pH และ ลิตมัส ---
                let phValue = row.ph_value !== null ? parseFloat(row.ph_value).toFixed(1) : '-';
                let colorStatus = row.color_change_status === 'changed' ?
                    '<span class="text-primary">เปลี่ยนสี</span>' :
                    '<span class="text-muted">ไม่เปลี่ยนสี</span>';

                // Badge สำหรับแสดงผลในจอ iPad (ซ่อนบน Desktop)
                let responsiveSubInfo = `
                <div class="d-xl-none mt-1 d-flex gap-2" style="font-size: 0.75rem;">
                    <span class="badge bg-light text-dark border">pH: ${phValue}</span>
                    <span class="badge bg-light text-dark border">${colorStatus}</span>
                </div>
            `;

                // ปริมาณที่ดึงมาทดสอบ
                let amountUsed = numberWithCommas(row.amount_used);
                let unitText = row.unit_display || '';

                // --- 3. จัดการปุ่มจัดการ (Lock ถ้า Verify แล้ว) ---
                let actionButtons = '';
                if (isVerified) {
                    actionButtons = `
                    <i class="fas fa-lock text-muted opacity-50" title="ทวนสอบแล้ว ไม่สามารถแก้ไขหรือลบได้"></i>
                `;
                } else {
                    // ถ้ายังไม่ทวนสอบ: แสดงปุ่มแก้ไข/ลบ ปกติ
                    actionButtons = `
                    <i class="fas fa-pen-to-square fa-lg text-warning ${window._canManage ? 'js-edit-latent-btn cursor-pointer' : ''}" 
                        data-id="${row.id}" 
                        title="${window._canManage ? 'แก้ไข' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                    <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-latent-btn cursor-pointer' : ''}" 
                        data-id="${row.id}" 
                        data-name="${prep_name}" 
                        data-test-date="${row.test_date_show}" 
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
                            <div class="chem-name-responsive text-dark" style="font-size: 0.95rem;">${prep_name}</div>
                            
                            <div class="d-xl-none text-muted mt-1" style="font-size: 0.75rem;">
                                ผู้ทดสอบ: ${tester}
                            </div>
                        </td>

                        <td class="text-start align-middle d-none d-md-table-cell">
                            ${departmentName}
                        </td>

                        <td class="text-center align-middle d-none d-lg-table-cell">
                            ${phValue}
                        </td>

                        <td class="text-center align-middle d-none d-lg-table-cell">
                            ${colorStatus}
                        </td>

                        <td class="text-center align-middle">
                            ${getReadinessBadge(row.readiness_status)}
                        </td>

                        <td class="text-start align-middle d-none d-xl-table-cell">
                            ${tester}
                        </td>

                        <td class="text-start align-middle d-none d-xxl-table-cell">
                            ${verifier}
                        </td>

                        <td class="text-center align-middle">
                            ${statusBadge}
                        </td>
                    </tr>
                    `;
            });
        } else {
            html = `
                <tr>
                    <td colspan="11" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-vial fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0 fw-bold">ไม่พบประวัติการตรวจความพร้อมสารเคมีในระบบ</p>
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