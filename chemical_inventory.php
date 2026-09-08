<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require_once './includes/check_permission.php';

$canManage = hasPermission($pdo, 'chemical.create');

// ดึงข้อมูล Role และ Department ของผู้ใช้งานปัจจุบัน
$current_user_id = $_SESSION['user_id'];
$user_role = "";
$user_dept_id = null;

try {
    $stmtUser = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ? AND is_active = 1");
    $stmtUser->execute([$current_user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($userData) {
        $user_role = strtolower($userData['role'] ?? '');
        $user_dept_id = $userData['department_id'] ?? null;
    }
} catch (Exception $e) {
    // กรณี Error กำหนดเป็นค่าว่าง
}

$title = "คลังและล็อตสารเคมี";

ob_start();
?>

<style>
    .js-add-lot-btn {
        padding: 25px;
        /* ขยายพื้นที่จิ้มออกไปรอบๆ */
        margin: -25px;
        /* ดึงขอบกลับมาเพื่อไม่ให้กระทบตำแหน่งในตาราง */
        cursor: pointer;
        touch-action: manipulation;
        /* ป้องกันการซูมเวลาเผลอ Double Tap */
    }

    .js-edit-lot-btn,
    .js-delete-lot-btn {
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

                        <?php if ($user_role === 'admin'): ?>
                            <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                                <label for="filter_department_id" class="form-label text-muted small mb-1">หน่วยงาน</label>
                                <select id="filter_department_id" name="filter_department_id" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                    <option value=""></option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_chem_name" class="form-label text-muted small mb-1">ชื่อสารเคมี</label>
                            <input type="text" id="filter_chem_name" name="filter_chem_name" class="form-control form-control-sm" placeholder="ระบุชื่อสาร หรือรหัส...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_chem_brand" class="form-label text-muted small mb-1">ยี่ห้อ / ผู้ผลิต</label>
                            <input type="text" id="filter_chem_brand" name="filter_chem_brand" class="form-control form-control-sm" placeholder="เช่น Merck, Sigma...">
                        </div>

                        <!-- เบื้องหลังจะค้นหาว่าตัวไหนมี lot นี้อยู่ -->
                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_lot_number" class="form-label text-muted small mb-1">Lot Number</label>
                            <input type="text" id="filter_lot_number" name="filter_lot_number" class="form-control form-control-sm" placeholder="ระบุเลขล็อตเพื่อหาชื่อสาร...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_stock_status" class="form-label text-muted small mb-1">สถานะสต็อก</label>
                            <select id="filter_stock_status" name="filter_stock_status" class="form-select form-select-sm">
                                <option value="">-- ทั้งหมด --</option>
                                <option value="in_stock">มีของ</option>
                                <option value="out_of_stock">ของหมด</option>
                                <option value="near_expiry">มีล็อตใกล้หมดอายุ</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_location" class="form-label text-muted small mb-1">สถานที่เก็บ</label>
                            <input type="text" id="filter_location" name="filter_location" class="form-control form-control-sm" placeholder="ระบุสถานที่เก็บ...">
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
                    <th style="min-width: 120px;">Action</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 50px;">ลำดับ</th>
                    <th style="min-width: 170px;">ชื่อสารเคมี</th>
                    <th class="d-none d-md-table-cell" style="min-width: 150px;">หน่วยงาน</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 180px;">ยี่ห้อ / ผู้ผลิต</th>
                    <th style="min-width: 130px;">ยอดคงเหลือ (ใช้งานได้)</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 130px;">ยอดคงเหลือ (รวม)</th>
                    <th class="d-none d-md-table-cell" style="min-width: 110px;">วันหมดอายุ (ใกล้สุด)</th>
                    <th class="d-none d-md-table-cell" style="min-width: 120px;">สถานที่เก็บ</th>
                    <th class="d-none d-xxl-table-cell" style="min-width: 110px;">วันที่รับเข้าล่าสุด</th>
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
<div class="modal fade" id="addEditInventoryModal" aria-labelledby="addEditInventoryModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addEditInventoryModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> <span id="textModal">เพิ่มรายการ</span>
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div class="card border-0 shadow-sm mb-4 border-start border-primary border-4 rounded-3">
                    <div class="card-body bg-white">
                        <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                            <i class="fas fa-flask fa-lg text-primary me-2"></i>
                            <h6 class="mb-0 fw-bold text-primary">ข้อมูลอ้างอิงสารเคมี</h6>
                        </div>
                        <div class="row g-3">
                            <div class="col-12 col-lg-12">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">หน่วยงาน</span>
                                <span class="fw-bold" id="display_department">-</span>
                            </div>

                            <div class="col-12 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ชื่อสารเคมี</span>
                                <span class="fw-bold" id="display_chem_name">-</span>
                            </div>
                            <div class="col-6 col-md-6 col-lg-3">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ยี่ห้อ / ผู้ผลิต</span>
                                <span class="fw-bold" id="display_chem_brand">-</span>
                            </div>
                            <!-- ต้องปรับการแสดงผล หน่วยนับ -->
                            <div class="col-6 col-md-6 col-lg-3">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">หน่วยนับหลัก</span>
                                <span class="fw-bold" id="display_chem_unit">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="post" id="chemicalInventoryForm" action="./api/Chemical_inventory/save.php" novalidate>
                    <input type="hidden" id="chemical_id" name="chemical_id">
                    <input type="hidden" id="edit_id" name="id">

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header text-primary"><i class="fas fa-tag me-1"></i> รายละเอียดล็อตและปริมาณ</legend>
                        <div class="row g-3">

                            <div class="col-12 col-md-6">
                                <label for="lot_number" class="form-label fw-bold">Lot Number <span class="text-danger">*</span></label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="lot_number"
                                        name="lot_number"
                                        class="form-control"
                                        required
                                        placeholder="ระบุเลขล็อต (เช่น Lot No. 2026/04)"
                                        autocomplete="off">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="lot_number"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุเลขล็อต</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="quantity" class="form-label fw-bold">ปริมาณรับเข้า <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" id="quantity" name="quantity" class="form-control"
                                        inputmode="decimal" required placeholder="เช่น 500.00"
                                        oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');">
                                    <span class="input-group-text" id="display_unit">
                                        -
                                    </span>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุปริมาณ</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="receive_date" class="form-label fw-bold">วันที่รับเข้ามา <span class="text-danger">*</span></label>
                                <input type="date" id="receive_date" name="receive_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                                <div class="invalid-feedback">กรุณาระบุวันที่รับ</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="expiry_date" class="form-label fw-bold">วันหมดอายุ <span class="text-danger">*</span></label>
                                <input type="date" id="expiry_date" name="expiry_date" class="form-control" required>
                                <div class="invalid-feedback">กรุณาระบุวันหมดอายุ (ต้องหลังวันที่รับเข้า)</div>
                            </div>

                            <div class="col-12">
                                <label for="location_stored" class="form-label fw-bold">สถานที่เก็บ</label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="location_stored"
                                        name="location_stored"
                                        class="form-control"
                                        placeholder="เช่น ตู้เย็น 01, ชั้นวาง A2, ตู้เก็บสารไวไฟ">
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
                                        placeholder="ระบุข้อมูลเพิ่มเติม (ถ้ามี) เช่น สภาพขวดสมบูรณ์, ต้องเก็บให้พ้นแสง"></textarea>
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
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="chemicalInventoryForm">
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
<div class="modal fade" id="viewLotHistoryModal" tabindex="-1" aria-labelledby="viewLotHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="viewLotHistoryModalLabel">
                    <i class="fas fa-history me-2"></i> รายการล็อตของสารเคมี
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">

                <div class="card border-0 shadow-sm mb-4 border-start border-primary border-4 rounded-3">
                    <div class="card-body bg-white">
                        <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                            <i class="fas fa-flask fa-lg text-primary me-2"></i>
                            <h6 class="mb-0 fw-bold text-primary">ข้อมูลอ้างอิงสารเคมี</h6>
                        </div>
                        <div class="row g-3">
                            <div class="col-12 col-lg-12">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">หน่วยงาน</span>
                                <span class="fw-bold" id="hist_department">-</span>
                            </div>
                            <div class="col-12 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ชื่อสารเคมี</span>
                                <span class="fw-bold" id="hist_chem_name">-</span>
                            </div>
                            <div class="col-6 col-lg-3">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ยี่ห้อ / ผู้ผลิต</span>
                                <span class="fw-bold" id="hist_chem_brand">-</span>
                            </div>
                            <!-- ต้องปรับการแสดงผล หน่วยนับ -->
                            <div class="col-6 col-lg-3">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">หน่วยนับหลัก</span>
                                <span class="fw-bold" id="hist_unit_name">-</span>
                            </div>
                            <!-- ต้องปรับการแสดงผล หน่วยนับ -->
                            <div class="col-12">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ยอดคงเหลือที่ใช้งานได้</span>
                                <span class="fw-bold" id="hist_total_remaining">0.00</span>
                                <span class="fw-bold" id="hist_total_unit"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header py-3 px-4">
                        <h6 class="mb-0 fw-bold "><i class="fas fa-list fa-lg me-2"></i>รายการล็อตทั้งหมดในคลัง</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle text-center mb-0" style="font-size: 0.9rem;">
                                <thead class="table-primary">
                                    <tr class="text-nowrap">
                                        <th style="min-width: 80px;">Action</th>
                                        <th style="min-width: 150px;">Lot Number</th>
                                        <th style="min-width: 120px;">วันหมดอายุ</th>
                                        <th class="d-none d-lg-table-cell" style="min-width: 110px;">วันที่รับเข้า</th>
                                        <th style="min-width: 120px;">ปริมาณคงเหลือ / รับเข้า</th>
                                        <th class="d-none d-xl-table-cell" style="min-width: 150px;">สถานที่เก็บ</th>
                                        <th style="width: 100px; min-width: 100px;">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody id="lot_history_body">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top shadow-sm">
                <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">
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
    $(document).ready(function() {
        searchPage(1);

        if ($('#filter_department_id').length) {
            loadDepartments();
        }

        if ($('.select2-filter').length) {
            $('.select2-filter').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
            }).next('.select2-container').addClass('select2-sm-custom');
        }

        // --- การล้างข้อมูลเมื่อ Modal ปิด ---
        $('#addEditInventoryModal, #viewLotHistoryModal').on('hidden.bs.modal', function() {
            $('#lot_history_body').empty();
        });

        $('#receive_date, #expiry_date').on('change', function() {
            validateDates();
        });

    });

    function loadDepartments() {
        $.ajax({
            url: './api/get_departments.php',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let options = '<option value=""></option>'; // เว้นว่างไว้สำหรับค่า Default (ทั้งหมด)
                    res.data.forEach(item => {
                        options += `<option value="${item.id}">${item.department_name}</option>`;
                    });

                    // ใส่ option ลงใน Select2 และ update
                    $('#filter_department_id').html(options).trigger('change');
                }
            },
            error: function(err) {
                console.error('Error loading departments:', err);
            }
        });
    }

    /* ฟังก์ชันจัดการแสดงตัวเลข */
    function numberWithCommas(x) {
        if (x === null || x === undefined) return '0.00';
        return parseFloat(x).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // ฟังก์ชันตรวจสอบความถูกต้องของวันที่ (รับเข้า vs หมดอายุ)
    function validateDates() {
        const receiveInput = document.getElementById('receive_date');
        const expiryInput = document.getElementById('expiry_date');

        const receiveDateVal = $(receiveInput).val();
        const expiryDateVal = $(expiryInput).val();

        // 1. ล้างสถานะเดิม (Reset State) ก่อนทุกครั้งที่ตรวจสอบ
        $(receiveInput).removeClass('is-invalid');
        $(expiryInput).removeClass('is-invalid');
        receiveInput.setCustomValidity('');
        expiryInput.setCustomValidity('');

        // 2. ตรวจสอบเงื่อนไข (ถ้ามีการกรอกข้อมูลครบทั้ง 2 ช่อง)
        if (receiveDateVal && expiryDateVal) {
            // กฎ: วันหมดอายุ ต้องมากกว่า วันที่รับเข้า
            if (new Date(expiryDateVal) <= new Date(receiveDateVal)) {
                // ถ้าผิดกฎ: สั่งให้เป็น Invalid เพื่อโชว์ขอบแดงและข้อความ Feedback
                expiryInput.setCustomValidity('Invalid');
                $(expiryInput).addClass('is-invalid');
            } else {
                // ถ้าถูกต้อง: มั่นใจว่าล้างสถานะ Invalid ออกแล้ว
                expiryInput.setCustomValidity('');
                $(expiryInput).removeClass('is-invalid');
            }
        }
    }

    // --- 1. จัดการเมื่อคลิกปุ่ม "เพิ่มล็อตใหม่" ---
    $(document).on('click', '.js-add-lot-btn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const brand = $(this).data('brand');
        const unitShow = $(this).data('unit-display');
        let department = $(this).data('department');

        const $form = $('#chemicalInventoryForm');

        // รีเซ็ตฟอร์ม
        $form[0].reset();
        $form.removeClass('was-validated');
        $form.find('.is-valid, .is-invalid').removeClass('is-valid is-invalid');

        // หยอดข้อมูลลงใน Reference Card และ Hidden Field
        $('#edit_id').val(''); // ล้างค่ากรณีเคยเปิด Edit
        $('#display_department').text(department || '-');
        $('#chemical_id').val(id);
        $('#display_chem_name').text(name);
        $('#display_chem_brand').text(brand);

        $('#display_chem_unit').text(unitShow || '-');
        $('#display_unit').text(unitShow || '-');

        $('#textModal').text('เพิ่มรายการ');
        $('#addEditInventoryModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายการ');

        // ตั้งวันที่รับเป็นปัจจุบัน และล้างวันหมดอายุ
        let today = new Date().toISOString().split('T')[0];
        $('#receive_date').val(today);
        $('#expiry_date').val('');

        $('#btn_save_all').prop('disabled', false).attr('title', '').removeClass('btn-save-readonly').addClass('btn-success');
        $('#addEditInventoryModal').modal('show');
    });

    $(document).on('click', '.clickable-row', function(e) {
        // ป้องกันไม่ให้ทำงานเมื่อคลิกโดนปุ่ม จัดการ (Edit/Delete)
        if ($(e.target).closest('.js-add-lot-btn, button, a').length) {
            return;
        }

        const id = $(this).data('id');
        const name = $(this).data('name');
        const brand = $(this).data('brand');
        const unitShow = $(this).data('unit-display');
        const department = $(this).data('department');

        $('#hist_chem_name').text(name);
        $('#hist_department').text(department || '-');
        $('#hist_chem_brand').text(brand);
        $('#hist_unit_name').text(unitShow || '-');
        // เคลียร์ยอดรวมรอไว้ (เดี๋ยวจะไปคำนวณจริงใน loadLotHistory)
        $('#hist_total_remaining').text('0.00');

        if (id) {
            loadLotHistory(id, unitShow);
            $('#viewLotHistoryModal').modal('show');
        }
    });

    // ฟังก์ชันดึงข้อมูลและสร้างตารางรายการล็อต
    function loadLotHistory(chemical_id, unitName) {
        const $tbody = $('#lot_history_body');
        const $totalLabel = $('#hist_total_remaining');
        const $unitLabelTop = $('#hist_total_unit');

        // แสดงสถานะกำลังโหลด
        $tbody.html(`<tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                            <br>กำลังดึงข้อมูลประวัติล็อต...
                        </td>
                    </tr>`);

        $.ajax({
            url: './api/Chemical_inventory/getLotHistory.php',
            type: 'GET',
            data: {
                chemical_id: chemical_id
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let html = '';
                    let usableTotal = 0; // ยอดที่ใช้ได้จริง (ไม่หมดอายุ)
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    if (res.data && res.data.length > 0) {
                        res.data.forEach((item, index) => {

                            // คำนวณปริมาณและกำหนด Class สี
                            const qtyRemaining = parseFloat(item.quantity_remaining || 0);
                            const qtyReceive = parseFloat(item.quantity || 0);

                            // --- 1. Logic การคำนวณวัน (Threshold = 90 วัน) ---
                            let diffDays = null;
                            let isExpired = false;
                            let isNearExpiry = false;
                            const warningThreshold = 90; // เกณฑ์สำหรับ Inventory

                            if (item.expiry_date_raw) {
                                const expDate = new Date(item.expiry_date_raw);
                                expDate.setHours(0, 0, 0, 0);
                                const diffTime = expDate - today;
                                diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                                isExpired = (diffDays < 0);
                                isNearExpiry = (diffDays >= 0 && diffDays <= warningThreshold);
                            }

                            // สะสมยอดรวมเฉพาะที่ "ไม่หมดอายุ"
                            if (!isExpired) {
                                usableTotal += qtyRemaining;
                            }

                            // --- 2. กำหนดสีและไอคอนตามสถานะ ---
                            let rowStatusClass = ''; // สำหรับคอลัมน์คงเหลือ
                            let expiryTextClass = ''; // สำหรับคอลัมน์วันหมดอายุ
                            let expiryIcon = '';

                            if (qtyRemaining <= 0) {
                                rowStatusClass = 'text-secondary'; // ใช้หมดแล้ว
                                expiryTextClass = 'text-muted';
                            } else if (isExpired) {
                                rowStatusClass = 'text-danger'; // หมดอายุ
                                expiryTextClass = 'text-danger fw-bold';
                                expiryIcon = '<i class="fas fa-times-circle me-1"></i>';
                            } else if (isNearExpiry) {
                                rowStatusClass = 'text-warning'; // ใกล้หมดอายุ
                                expiryTextClass = 'text-warning fw-bold';
                                expiryIcon = '<i class="fas fa-exclamation-triangle me-1"></i>';
                            } else {
                                rowStatusClass = 'text-success'; // พร้อมใช้งาน
                                expiryTextClass = 'text-dark';
                            }

                            const unitLabel = item.unit_display || '';

                            // คำนวณสถานะวันหมดอายุสำหรับ Badge
                            const statusHtml = getStatusBadge(item.expiry_date_raw, item.quantity_remaining, false);

                            html += `
                            <tr>
                                <td class="text-center align-middle">
                                     <div class="d-flex justify-content-center gap-2">
                                        <i class="fas fa-pen-to-square fa-lg text-warning ${window._canManage ? 'js-edit-lot-btn cursor-pointer' : ''}" 
                                        data-id="${item.id}" title="${window._canManage ? 'แก้ไขล็อตนี้' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>

                                        <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-lot-btn cursor-pointer' : ''}" 
                                        data-id="${item.id}" 
                                        data-chem-id="${item.chemical_id}" 
                                        data-lot="${item.lot_number}"
                                        data-expiry="${item.expiry_date_show}"
                                        data-qty="${numberWithCommas(qtyRemaining)} ${unitLabel}"
                                        title="${window._canManage ? 'ลบล็อตนี้' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                                    </div>
                                </td>
                                <td class="align-middle text-start">
                                    <div class="chem-name-responsive text-dark">${item.lot_number}</div>
                                    <div class="d-xl-none text-muted" style="font-size: 0.75rem;">
                                        สถานที่เก็บ: <b>${item.location_stored || '-'}</b>
                                    </div>
                                </td>

                                <td class="align-middle text-center ${expiryTextClass}">
                                    ${expiryIcon}${item.expiry_date_show}
                                </td>

                                <td class="align-middle text-center d-none d-lg-table-cell">
                                    ${item.receive_date_show}
                                </td>
                                
                                <td class="align-middle text-center">
                                    <span class="fw-bold ${rowStatusClass}">${numberWithCommas(qtyRemaining)}</span>
                                    <span class="text-muted small">/ ${numberWithCommas(qtyReceive)} ${unitLabel}</span>
                                </td>

                                <td class="align-middle text-start d-none d-xl-table-cell">
                                    <span class="d-block text-truncate" style="max-width: 900px;" title="${item.location_stored}">
                                        ${item.location_stored || '-'}
                                    </span>
                                </td>

                                <td class="align-middle text-center">
                                    ${statusHtml}
                                </td>
                            </tr>
                        `;
                            $totalLabel.text(numberWithCommas(usableTotal));
                            $unitLabelTop.text(res.data[0].unit_display);

                            // เลือกสี: ถ้าใช้ได้จริงเป็น 0 ให้แดงทันทีเพื่อให้สะดุดตา
                            const totalClass = (usableTotal <= 0) ? 'text-danger' : 'text-primary';
                            $totalLabel.removeClass('text-primary text-danger').addClass(totalClass);
                            $unitLabelTop.removeClass('text-primary text-danger').addClass(totalClass);
                        });
                    } else {
                        html = `<tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-vials fa-3x mb-3 opacity-50"></i>
                                            <p class="mb-1 fw-bold">ยังไม่มีข้อมูลล็อตในระบบ</p>
                                            <small class="opacity-75">สารเคมีนี้ยังไม่มีรายการล็อตนำเข้าในระบบ<br>สามารถเพิ่มรายการใหม่ได้ผ่านปุ่ม <b class="mx-2"><i class="fas fa-circle-info me-1"></i>จัดการข้อมูล</b>  ในตารางหลัก</small>                                    </div>
                                    </td>
                                    </td>
                                </tr>`;
                        $totalLabel.text('0.00').removeClass('text-primary').addClass('text-danger');
                        $unitLabelTop.text(unitName || '-').removeClass('text-primary').addClass('text-danger');
                    }

                    $tbody.html(html);
                } else {
                    $tbody.html(`<tr><td colspan="8" class="text-center text-danger py-4">${res.message}</td></tr>`);
                }
            },
            error: function() {
                $tbody.html('<tr><td colspan="8" class="text-center text-danger py-4">เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์</td></tr>');
            }
        });
    }

    // ฟังก์ชันสำหรับฟอร์แมตวันที่เป็น DD/MM/YYYY (พ.ศ.)
    function formatDateThai(dateStr) {
        if (!dateStr || dateStr === '0000-00-00') return '-';
        const d = new Date(dateStr);
        return d.toLocaleDateString('th-TH', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
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

    // คลิกปุ่มแก้ไขจากในตารางประวัติล็อต
    $(document).on('click', '.js-edit-lot-btn', function() {
        let dataId = $(this).data('id'); // ID ของล็อตสารเคมี

        $.ajax({
            url: './api/Chemical_inventory/getDataByID.php',
            type: 'GET',
            data: {
                id: dataId
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let d = res.data;
                    const $form = $('#chemicalInventoryForm');

                    // --- ปิด Modal รายการล็อต (Detail) ก่อน ---
                    $('#viewLotHistoryModal').modal('hide');

                    // 1. จัดการ UI Modal
                    $('#textModal').text('แก้ไขข้อมูลล็อตสารเคมี');
                    $('#addEditInventoryModalLabel').html('<i class="fas fa-edit fa-lg me-2"></i> แก้ไขข้อมูลล็อตสารเคมี');
                    $form.removeClass('was-validated');
                    $form.find('.is-invalid').removeClass('is-invalid');
                    $form.find('.is-valid').removeClass('is-valid');

                    // 2. หยอดข้อมูลอ้างอิงสารเคมี (Reference Card)
                    $('#chemical_id').val(d.chemical_id);
                    $('#display_department').text(d.department_name || '-');
                    $('#display_chem_name').text(d.chemical_name);
                    $('#display_chem_brand').text(d.chemical_brand);

                    // --- แสดงหน่วยนับหลัก ---
                    $('#display_chem_unit, #display_unit').text(d.unit_display || '-');

                    // 3. หยอดข้อมูลรายละเอียดล็อตลงใน Input (Single Fields)
                    $('#edit_id').val(d.id);
                    $('#lot_number').val(d.lot_number);
                    $('#receive_date').val(d.receive_date);
                    $('#expiry_date').val(d.expiry_date);
                    $('#quantity').val(d.quantity);
                    $('#location_stored').val(d.location_stored);
                    $('#remark').val(d.remark);

                    // 4. แสดง Modal แก้ไข (ปิดอันเก่าก่อนแล้วเปิดอันนี้)
                    $('#addEditInventoryModal').modal('show');
                }
            },
            error: function() {
                Swal.fire('Error', 'ไม่สามารถดึงข้อมูลล็อตได้', 'error');
            }
        });
    });


    // คลิกปุ่มลบจากในตารางประวัติล็อต
    $(document).on('click', '.js-delete-lot-btn', function(e) {
        e.preventDefault();
        const logId = $(this).data('id');
        const chemId = $(this).data('chem-id');
        const lotNumber = $(this).data('lot');
        const expiryDate = $(this).data('expiry');
        const remainingQty = $(this).data('qty');

        if (!logId) {
            Swal.fire('Error', 'ไม่พบ ID ของรายการ', 'error');
            return;
        }

        Swal.fire({
            title: 'ยืนยันการลบ?',
            html: `
                <div class="text-center mb-3">คุณต้องการลบข้อมูลล็อตสารเคมีนี้ใช่หรือไม่?</div>
                <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">Lot Number:</span><br>
                        <b class="ps-2 text-danger">${lotNumber}</b>
                    </div>
                    
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">วันหมดอายุ:</span><br>
                        <b class="ps-2 text-danger">${expiryDate}</b>
                    </div>
                    
                    <div>
                        <span class="text-secondary small">ปริมาณคงเหลือปัจจุบัน:</span><br>
                        <b class="ps-2 text-danger">${remainingQty}</b>
                    </div>
                </div>
                <div class="mt-3 text-center">
                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i>หากมีการนำไปเตรียมสารแล้ว จะไม่สามารถลบได้</small>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> ลบข้อมูล',
            cancelButtonText: 'ยกเลิก',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    url: './api/Chemical_inventory/delete.php',
                    type: 'POST',
                    data: {
                        id: logId
                    },
                    dataType: 'json'
                }).then(response => {
                    if (response.status !== 'success') {
                        throw new Error(response.message || 'เกิดข้อผิดพลาดในการลบ');
                    }
                    return response;
                }).catch(err => {
                    let msg = 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์';

                    // กรณีที่ 1: เป็น Error ที่เรา throw มาจาก .then()
                    if (err instanceof Error) {
                        msg = err.message;
                    }
                    // กรณีที่ 2: เป็น Error จาก Server (jqXHR)
                    else if (err.responseJSON && err.responseJSON.message) {
                        msg = err.responseJSON.message;
                    } else if (err.statusText) {
                        msg = err.statusText;
                    }

                    Swal.showValidationMessage(`ไม่สามารถลบได้: ${msg}`);
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'ลบข้อมูลสำเร็จ!',
                    text: 'ล็อตสารเคมีถูกลบออกจากระบบแล้ว',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false,
                    returnFocus: false
                }).then(() => {
                    // 1. รีเฟรชตารางประวัติล็อตใน Modal
                    loadLotHistory(chemId);

                    // 2. รีเฟรชตารางสรุปหน้าหลัก
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
        window.location.href = './api/Chemical_inventory/exportExcel.php?' + formData;
    });

    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    // ฟังก์ชันบันทึกข้อมูลล็อตสารเคมี (Chemical Inventory)
    $('#chemicalInventoryForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;

        const receiveDate = $('#receive_date').val();
        const expiryDate = $('#expiry_date').val();

        // เช็ค Custom Logic ก่อน
        if (receiveDate && expiryDate && new Date(expiryDate) <= new Date(receiveDate)) {
            $(form).addClass('was-validated'); // บังคับให้โชว์สี
            $('#expiry_date').addClass('is-invalid').focus(); // บังคับแดงที่ช่องวันหมดอายุ

            Swal.fire({
                icon: 'error',
                title: 'วันที่ไม่ถูกต้อง',
                text: 'วันหมดอายุต้องอยู่หลังจากวันที่รับสารเคมีเข้าคลัง',
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

        // 3. ส่งข้อมูลผ่าน AJAX
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function() {
                $('#btn_save_all').prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin me-2"></i> กำลังบันทึกล็อต...');
            },
            success: function(response) {
                if (response.status == "success") {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: 'ข้อมูลล็อตสารเคมีถูกบันทึกลงคลังเรียบร้อยแล้ว',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // ปิด Modal และรีโหลดตาราง
                        $('#addEditInventoryModal').modal('hide');

                        // ดึง chemical_id ปัจจุบันมาเพื่อรีเฟรชตารางประวัติ
                        const currentChemId = $('#chemical_id').val();
                        if (currentChemId && $('#viewLotHistoryModal').is(':visible')) {
                            loadLotHistory(currentChemId);
                        }

                        // รีเฟรชตารางหน้าหลัก
                        const currentPage = parseInt($('#pagination-list .active a').text()) || 1;
                        if (typeof searchPage === "function") {
                            // ถ้าเป็นการแก้ไขให้ค้างหน้าที่เดิม ถ้าเพิ่มใหม่ให้ไปหน้า 1
                            searchPage($('#edit_id').val() ? currentPage : 1);
                        }
                    });
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', response.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                }
            },
            error: function(xhr) {
                console.error("AJAX Error:", xhr.responseText);
                Swal.fire('Server Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาลองใหม่อีกครั้ง', 'error');
            },
            complete: function() {
                // คืนค่าปุ่มบันทึก
                $('#btn_save_all').prop('disabled', false)
                    .html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล');
            }
        });
    });

    function searchPage(page) {
        // 1. ดึงค่าจาก Filter Form ทั้งหมด (ชื่อ, ยี่ห้อ, ล็อต, ช่วงวันที่, สถานะ, สถานที่)
        // .serializeArray() จะดึงค่าจากทุก input ที่มี name ในฟอร์มมาให้อัตโนมัติ
        const formData = $('#searchFilterForm').serializeArray();

        // เพิ่มพารามิเตอร์เลขหน้าเข้าไปในชุดข้อมูล
        formData.push({
            name: 'page',
            value: page
        });

        $.ajax({
            url: './api/Chemical_inventory/searchData.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status == 'success') {
                    // 1. วาดตารางข้อมูล (ส่ง data และ offset ไปเพื่อรันเลขลำดับ)
                    renderTable(response.data, response.offset);

                    // 2. แสดงจำนวนรายการทั้งหมดที่ค้นพบ
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
                    $('#table_body').html(`<tr><td colspan="9" class="text-center py-5 text-danger font-weight-bold">${response.message}</td></tr>`);
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

    function renderTable(data, offset) {
        let html = '';
        let start_no = (isNaN(parseInt(offset))) ? 1 : parseInt(offset) + 1;

        // ตั้งค่าวันที่ปัจจุบันเพื่อใช้เทียบสถานะ
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (data && data.length > 0) {
            data.forEach(function(row, i) {
                // 1. จัดการข้อมูลพื้นฐาน (ดึงจาก Master)
                let chemical_id = row.chemical_id;
                let chem_name = row.chemical_name || '-';
                let brand = row.chemical_brand || '-';
                let unitShow = row.unit_display || '-';
                let locations = row.locations_summary || '-';
                let deptName = row.department_name || '<span class="text-muted small">ไม่ระบุ</span>';
                let rawDeptName = row.department_name || 'ไม่ระบุ';

                // ใช้ Logic เดียวกับ Backend: ถ้าเป็นบรรจุภัณฑ์ใช้ EN Name ถ้าทั่วไปใช้ Symbol
                let shortUnit = row.unit_symbol || '';
                if (row.unit_group && (row.unit_group.includes('บรรจุภัณฑ์') || row.unit_group.includes('Packaging'))) {
                    shortUnit = row.unit_name_en || row.unit_symbol;
                }

                // 2. จัดการปริมาณ
                let totalUsable = parseFloat(row.total_remaining || 0);
                let totalPhysical = parseFloat(row.total_physical || 0);

                // สีของยอดที่ใช้ได้จริง (ถ้าหมด = แดง)
                let usableClass = (totalUsable <= 0) ? 'text-danger fw-bold' : 'text-primary fw-bold';

                let nearestExpiryShow = formatDateThai(row.nearest_expiry);
                let nearestQty = parseFloat(row.nearest_expiry_qty || 0);
                let expiryClass = ''; // คลาสสำหรับเน้นสีวันที่
                let expiryContent = '-';

                if (row.nearest_expiry && row.nearest_expiry !== '0000-00-00') {
                    const expDate = new Date(row.nearest_expiry);
                    const diffTime = expDate - today;
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                    if (diffDays < 0) {
                        expiryClass = 'text-danger fw-bold'; // หมดอายุแล้ว (ห้ามข้าม ต้องโชว์แดง)
                    } else if (diffDays <= 90) {
                        expiryClass = 'text-warning fw-bold'; // ใกล้หมดอายุ
                    } else {
                        expiryClass = 'text-dark';
                    }

                    let qtyWeight = (diffDays <= 90) ? 'fw-bold' : 'fw-normal';

                    let qtyPart = nearestQty > 0 ?
                        ` <span class="small ${qtyWeight}" style="font-size: 0.85rem;">(${numberWithCommas(nearestQty)} ${shortUnit})</span>` :
                        '';

                    expiryContent = `<span>${nearestExpiryShow}</span>${qtyPart}`;
                }

                let latestRecShow = formatDateThai(row.latest_receive_date);

                html += `
                <tr class="clickable-row cursor-pointer" 
                    data-id="${chemical_id}" 
                    data-name="${chem_name}" 
                    data-brand="${brand}"
                    data-unit-display="${unitShow}"
                    data-department="${rawDeptName}"
                    title="ดูข้อมูล"> 
            
                    <td class="text-center align-middle">
                        <div class="d-flex align-items-center justify-content-center" style="min-height: 100%;">
                            <button type="button" 
                                    class="btn btn-sm ${window._canManage ? 'btn-success js-add-lot-btn' : 'btn-secondary'} py-1 px-2"
                                    data-id="${chemical_id}" 
                                    data-name="${chem_name}" 
                                    data-brand="${brand}"
                                    data-unit-display="${unitShow}"
                                    data-department="${rawDeptName}"
                                    title="${window._canManage ? 'เพิ่มล็อตสารเคมีใหม่' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"
                                    ${window._canManage ? '' : 'disabled'}>
                                <i class="fas fa-circle-info me-1"></i> <span style="font-size: 0.8rem;">จัดการข้อมูล</span>
                            </button>
                        </div>
                    </td>
                    <td class="text-center align-middle d-none d-xl-table-cell">${start_no + i}</td>
                    <td class="text-start align-middle">
                        <div class="chem-name-responsive text-dark">${chem_name}</div>
                        <div class="d-lg-none text-muted small mt-1">
                            ยี่ห้อ/ผู้ผลิต: <b>${brand}</b>
                        </div>
                    </td>
                    <td class="text-start align-middle d-none d-md-table-cell">${deptName}</td>
                    
                    <td class="text-start align-middle d-none d-lg-table-cell">${brand}</td>

                     <td class="text-center align-middle ${usableClass}">
                        ${numberWithCommas(totalUsable)} <small>${unitShow}</small>
                    </td>

                    <td class="text-center align-middle d-none d-xl-table-cell text-dark">
                        ${numberWithCommas(totalPhysical)} <small>${unitShow}</small>
                    </td>
                    
                    <td class="text-center align-middle d-none d-md-table-cell">
                    <div class="${expiryClass} text-nowrap">
                        ${expiryContent}
                    </div>
                </td>

                    <td class="text-start align-middle d-none d-md-table-cell">
                        <span class="d-block text-truncate" style="max-width: 120px;" title="${locations}">
                            ${locations}
                        </span>
                    </td>
                    <td class="text-center align-middle d-none d-xxl-table-cell">
                        ${latestRecShow}
                    </td>
                </tr>
            `;
            });
        } else {
            html = `
            <tr>
                <td colspan="10" class="text-center py-5">
                    <div class="text-muted">
                        <i class="fas fa-flask fa-3x mb-3 opacity-50"></i>
                        <p class="mb-0 fw-bold">ไม่พบข้อมูลสารเคมีในระบบ</p>
                        <small class="opacity-75">ลองเปลี่ยนคำค้นหา  หรือกดปุ่ม <b class="text-dark">" เพิ่มรายการ "</b> ในหน้าสารเคมี เพื่อสร้างข้อมูลใหม่</small>
                    </div>
                </td>
            </tr>
        `;
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