<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require_once './includes/check_permission.php';

// ดึงข้อมูล Role และ Department ของ User ที่ Login
$user_id = $_SESSION['user_id'];
$stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
$stmtRole->execute([$user_id]);
$userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

$user_role = strtolower($userInfo['role'] ?? '');
$user_dept_id = $userInfo['department_id'] ?? null;

$canManage = hasPermission($pdo, 'master_data.create');

$title = "รายการเครื่องคอมพิวเตอร์";

ob_start();
?>
<style>
    .js-edit-computer-btn,
    .js-delete-computer-btn {
        padding: 12px;
        /* ขยายพื้นที่จิ้มออกไปรอบๆ */
        margin: -12px;
        /* ดึงขอบกลับมาเพื่อไม่ให้กระทบตำแหน่งในตาราง */
        cursor: pointer;
        touch-action: manipulation;
        /* ป้องกันการซูมเวลาเผลอ Double Tap */
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
                    <div class="row g-2 align-items-end <?= ($user_role !== 'admin') ? 'justify-content-start' : 'justify-content-center' ?>">

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4">
                            <button class="btn <?= $canManage ? 'btn-success' : 'btn-secondary' ?> btn-sm px-4 fw-bold shadow-sm text-nowrap w-100 <?= $canManage ? 'js-add-computer-btn' : '' ?>"
                                style="height: 31px;"
                                type="button" <?= $canManage ? 'data-bs-toggle="modal" data-bs-target="#addEditComputerModal"' : 'disabled' ?>
                                title="<?= $canManage ? 'เพิ่มรายการใหม่' : 'คุณไม่มีสิทธิ์จัดการข้อมูล' ?>">
                                <i class="fas fa-plus me-2"></i>เพิ่มรายการ
                            </button>
                        </div>

                        <?php if ($user_role === 'admin'): ?>
                            <div class="col-12 col-sm-6 col-md-6 col-lg-4">
                                <label for="filter_department_id" class="form-label text-muted small mb-1">หน่วยงาน</label>
                                <select id="filter_department_id" name="filter_department_id" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                    <option value=""></option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4">
                            <label for="filter_computer_asset_no" class="form-label text-muted small mb-1">เลขครุภัณฑ์</label>
                            <input type="text" id="filter_computer_asset_no" name="filter_computer_asset_no" class="form-control form-control-sm" placeholder="ระบุเลขครุภัณฑ์...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4">
                            <label for="filter_computer_brand_model" class="form-label text-muted small mb-1">ยี่ห้อ / รุ่น</label>
                            <input type="text" id="filter_computer_brand_model" name="filter_computer_brand_model" class="form-control form-control-sm" placeholder="เช่น Lenovo Thinkpad...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4">
                            <label for="filter_computer_serial" class="form-label text-muted small mb-1">Serial No. (S/N)</label>
                            <input type="text" id="filter_computer_serial" name="filter_computer_serial" class="form-control form-control-sm" placeholder="ระบุเลข Serial..." oninput="this.value = this.value.replace(/\s/g, '')">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4">
                            <label for="filter_responsible_id" class="form-label text-muted small mb-1">ผู้ดูแลเครื่องคอมพิวเตอร์</label>
                            <select id="filter_responsible_id" name="filter_responsible_id" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                <option value=""></option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4">
                            <label for="filter_receive_date" class="form-label text-muted small mb-1">วันที่รับมอบ / รับเข้า</label>
                            <input type="date" id="filter_receive_date" name="filter_receive_date" class="form-control form-control-sm">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4">
                            <label for="filter_start_use_date" class="form-label text-muted small mb-1">วันที่เริ่มใช้งาน</label>
                            <input type="date" id="filter_start_use_date" name="filter_start_use_date" class="form-control form-control-sm">
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
                <tr class="text-nowrap text-center ">
                    <th style="min-width: 100px;">Action</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 50px;">ลำดับ</th>
                    <th style="min-width: 150px;">เลขครุภัณฑ์</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 150px;">หน่วยงาน</th>
                    <th style="min-width: 200px;">ยี่ห้อ / รุ่น</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 150px;">Serial No. (S/N)</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 180px;">ผู้ดูแลเครื่องคอมพิวเตอร์</th>
                    <th class="d-none d-md-table-cell" style="min-width: 120px;">วันที่รับมอบ / รับเข้า</th>
                    <th class="d-none d-md-table-cell" style="min-width: 120px;">วันที่เริ่มใช้งาน</th>
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

<!-- Modal สำหรับเพิ่มรายการ computer ใหม่ / แก้ไขรายการเดิม -->
<div class="modal fade" id="addEditComputerModal" aria-labelledby="addEditComputerModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addEditComputerModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> <span id="textModal">เพิ่มรายการ</span>
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" id="computerForm" action="./api/Master_computer_list/save.php" novalidate>
                    <input type="hidden" id="edit_id" name="id">

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header"><i class="fas fa-info-circle me-1"></i> ข้อมูลทั่วไปของเครื่องคอมพิวเตอร์</legend>
                        <div class="row g-3 justify-content-start">

                            <div class="col-md-6 <?= ($user_role !== 'admin') ? 'd-none' : '' ?>">
                                <label for="department_id" class="form-label fw-bold">หน่วยงาน <span class="text-danger">*</span></label>
                                <select id="department_id" name="department_id" class="form-select select2-dept" data-placeholder="-- ค้นหาหน่วยงาน --" <?= ($user_role === 'admin') ? 'required' : '' ?>>
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาระบุหน่วยงาน</div>
                            </div>

                            <div class="col-md-6">
                                <label for="computer_asset_no" class="form-label fw-bold">เลขครุภัณฑ์ <span class="text-danger">*</span></label>
                                <div class="input-group input-group-seamless">
                                    <input type="text"
                                        id="computer_asset_no"
                                        name="computer_asset_no"
                                        class="form-control"
                                        placeholder="ระบุเลขครุภัณฑ์ (เช่น ค.456/69)"
                                        required
                                        maxlength="100"
                                        autocomplete="off">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="computer_asset_no"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุเลขครุภัณฑ์</div>
                            </div>

                            <div class="col-md-6">
                                <label for="computer_brand" class="form-label">ยี่ห้อ (Brand)</label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="computer_brand"
                                        name="computer_brand"
                                        class="form-control"
                                        placeholder="เช่น Dell, HP, Lenovo, Apple"
                                        autocomplete="off"
                                        maxlength="100">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="computer_brand"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="computer_model" class="form-label">รุ่น (Model)</label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="computer_model"
                                        name="computer_model"
                                        class="form-control"
                                        placeholder="ระบุชื่อรุ่น (เช่น Latitude 5420, MacBook Pro)"
                                        autocomplete="off"
                                        maxlength="100">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="computer_model"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="computer_serial" class="form-label fw-bold">Serial No. (S/N)</label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="computer_serial"
                                        name="computer_serial"
                                        class="form-control text-uppercase"
                                        placeholder="ระบุเลข Serial Number จากตัวเครื่อง"
                                        autocomplete="off"
                                        maxlength="100"
                                        oninput="this.value = this.value.replace(/\s/g, '').toUpperCase()">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="computer_serial"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="<?= ($user_role !== 'admin') ? 'col-md-12' : 'col-md-6' ?>">
                                <label for="responsible_id" class="form-label">ผู้ดูแลเครื่องคอมพิวเตอร์ <span class="text-danger">*</span></label>
                                <select id="responsible_id" name="responsible_id" class="form-select select2-user" data-placeholder="-- ค้นหาชื่อผู้ดูแลเครื่องคอมพิวเตอร์ --" required>
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาระบุผู้ดูแลเครื่องคอมพิวเตอร์</div>
                            </div>

                            <div class="col-md-6">
                                <label for="computer_receive_date" class="form-label">วันที่รับมอบ / รับเข้า</label>
                                <input type="date" id="computer_receive_date" name="computer_receive_date" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label for="computer_start_use_date" class="form-label">วันที่เริ่มใช้งาน</label>
                                <input type="date" id="computer_start_use_date" name="computer_start_use_date" class="form-control">
                                <div class="invalid-feedback" id="start_use_error">
                                    วันที่เริ่มใช้งานห้ามก่อนวันที่รับมอบ/รับเข้า
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-end bg-white border-top shadow-sm">
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="computerForm">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal สำหรับดูรายละเอียดทั้งหมด -->
<div class="modal fade" id="viewDetailModal" tabindex="-1" aria-labelledby="viewDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-width: 90%;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold" id="viewDetailModalLabel">
                    <i class="fas fa-search me-2"></i> รายละเอียดเครื่องคอมพิวเตอร์
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-12">
                        <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                            <i class="fas fa-info-circle me-1"></i> ข้อมูลเครื่องคอมพิวเตอร์
                        </h6>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="text-muted small d-block">หน่วยงาน</label>
                                <span id="view_department_name" class="fw-bold">-</span>
                            </div>

                            <div class="col-6 col-lg-4 mb-3">
                                <label class="text-muted small d-block">เลขครุภัณฑ์</label>
                                <span id="view_computer_asset_no" class="fw-bold">-</span>
                            </div>

                            <div class="col-6 col-lg-4 mb-3">
                                <label class="text-muted small d-block">ยี่ห้อ (Brand)</label>
                                <span id="view_computer_brand" class="fw-bold">-</span>
                            </div>
                            <div class="col-6 col-lg-4 mb-3">
                                <label class="text-muted small d-block">รุ่น (Model)</label>
                                <span id="view_computer_model" class="fw-bold">-</span>
                            </div>

                            <div class="col-6 col-lg-4 mb-3">
                                <label class="text-muted small d-block">Serial No. (S/N)</label>
                                <span id="view_computer_serial_no" class="fw-bold">-</span>
                            </div>

                            <div class="col-6 col-lg-8 mb-3">
                                <label class="text-muted small d-block">ผู้ดูแลเครื่องคอมพิวเตอร์</label>
                                <span id="view_responsible" class="fw-bold">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                            <i class="far fa-calendar-alt me-1"></i> วันที่สำคัญในระบบ
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <div class="d-flex justify-content-between align-items-center bg-light px-3 py-2 rounded">
                                    <span class="text-muted small">วันที่รับมอบ / รับเข้า:</span>
                                    <span id="view_computer_receive_date" class="fw-bold">-</span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="d-flex justify-content-between align-items-center bg-light px-3 py-2 rounded">
                                    <span class="text-muted small">วันที่เริ่มใช้งาน:</span>
                                    <span id="view_computer_start_use_date" class="fw-bold">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12 mt-2">
                        <div class="alert alert-secondary border-0 p-3 mb-0 shadow-sm">
                            <div class="row small text-muted align-items-center">

                                <div class="col-md-6 border-end border-white">
                                    <div class="d-flex align-items-center" style="min-height: 45px;"> <i class="fas fa-user-plus fa-lg text-secondary me-3"></i>
                                        <div>
                                            <label class="d-block mb-0 fw-bold" style="font-size: 0.7rem;">สร้างโดย:</label>
                                            <span id="view_create_by" class="text-dark d-block" style="font-size: 0.85rem;">-</span>
                                            <span id="view_create_date" class="italic" style="font-size: 0.75rem;">-</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 ps-4">
                                    <div class="d-flex align-items-center" style="min-height: 45px;"> <i class="fas fa-user-pen fa-lg text-secondary me-3"></i>
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
    window._canManage = <?= $canManage ? 'true' : 'false' ?>;

    // เก็บสถานะหน้าปัจจุบัน
    let currentPage = 1;

    $(document).ready(function() {
        searchPage(1);

        loadUserList();
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

        if ($('.select2-user').length) {
            $('.select2-user').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                dropdownParent: $('#addEditComputerModal')
            });
        }

        if ($('.select2-dept').length) {
            $('.select2-dept').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                dropdownParent: $('#addEditComputerModal')
            });
        }

        // 1. ดัก Event ตอนเปิด Modal (สำหรับ Computer)
        $('#addEditComputerModal').on('show.bs.modal', function() {
            setTimeout(() => {
                const receiveDate = $('#computer_receive_date').val();
                if (receiveDate) {
                    $('#computer_start_use_date').attr('min', receiveDate);
                }
            }, 100);
        });

        // 2. ดัก Event ตอนปิด Modal (Reset ค่า)
        $('#addEditComputerModal').on('hidden.bs.modal', function() {
            $('#department_id').val(null).trigger('change');
            $('#responsible_id').val(null).trigger('change');

            $('#computer_start_use_date').removeAttr('min');
            $(this).find('form').removeClass('was-validated');
            $(this).find('.is-invalid').removeClass('is-invalid');
        });

        // ควบคุมความสัมพันธ์วันที่แบบ Real-time 
        $('#computer_receive_date, #computer_start_use_date').on('change input', function() {
            const receiveDate = $('#computer_receive_date').val();
            const startUseDate = $('#computer_start_use_date').val();
            const $startInput = $('#computer_start_use_date');

            if (receiveDate && startUseDate) {
                if (new Date(startUseDate) < new Date(receiveDate)) {
                    $startInput.addClass('is-invalid');
                } else {
                    $startInput.removeClass('is-invalid');
                }
            }

            // อัปเดตค่า min ของ HTML5 ตลอดเวลา
            if (receiveDate) {
                $startInput.attr('min', receiveDate);
            }
        });
    });

    function loadUserList() {
        $.ajax({
            url: './api/get_users.php',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let options = '<option value=""></option>';
                    res.data.forEach(item => {
                        options += `<option value="${item.user_id}">${item.fullname}</option>`;
                    });

                    // อัปเดตทั้งใน Filter หน้าหลัก และ ฟอร์ม Modal
                    $('#filter_responsible_id, #responsible_id').html(options);
                    $('#filter_responsible_id').trigger('change');
                    $('#responsible_id').trigger('change');
                }
            }
        });
    }

    function loadDepartments() {
        $.ajax({
            url: './api/get_departments.php',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let options = '<option value=""></option>';
                    res.data.forEach(item => {
                        options += `<option value="${item.id}">${item.department_name}</option>`;
                    });
                    $('#filter_department_id, #department_id').html(options);
                    $('#filter_department_id, #department_id').trigger('change');
                }
            }
        });
    }

    // 1. ฟังก์ชันสำหรับดึงข้อมูลคอมพิวเตอร์และเปิด Modal Detail
    function viewComputerDetail(id) {
        $.ajax({
            url: './api/Master_computer_list/getDataByID.php',
            method: 'GET',
            data: {
                id: id
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let d = res.data;
                    // Mapping ข้อมูลลง Modal 
                    $('#view_department_name').text(d.department_name || '-');
                    $('#view_computer_asset_no').text(d.computer_asset_no || '-');
                    $('#view_computer_brand').text(d.computer_brand || '-');
                    $('#view_computer_model').text(d.computer_model || '-');
                    $('#view_computer_serial_no').text(d.computer_serial || '-');

                    // ข้อมูลผู้ดูแล
                    $('#view_responsible').html(d.responsible_fullname || '<span class="text-muted">ยังไม่ระบุ</span>');

                    // วันที่สำคัญ
                    $('#view_computer_receive_date').text(d.receive_date_show || '-');
                    $('#view_computer_start_use_date').text(d.start_use_date_show || '-');

                    // ข้อมูล Metadata (ประวัติการบันทึก)
                    $('#view_create_by').text(d.fullname_create || '-');
                    $('#view_create_date').text(d.createdate_show || '-');
                    $('#view_update_by').text(d.fullname_update || '-');
                    $('#view_update_date').text(d.updatedate_show || '');

                    // สั่งเปิด Modal
                    $('#viewDetailModal').modal('show');
                }
            },
            error: function() {
                Swal.fire('ผิดพลาด', 'ไม่สามารถดึงข้อมูลรายละเอียดได้', 'error');
            }
        });
    }

    // 2. ดักจับการคลิกที่แถว (โดยยกเว้นปุ่มจัดการอื่นๆ)
    $(document).on('click', '.clickable-row', function(e) {
        // ถ้าคลิกโดนปุ่มแก้ไขหรือลบ ให้หยุดทำงาน (ไม่เปิด Modal Detail)
        if ($(e.target).closest('.js-edit-computer-btn, .js-delete-computer-btn').length) {
            return;
        }

        const id = $(this).data('id');
        viewComputerDetail(id);
    });

    // เพิ่ม Event เมื่อกดปุ่ม "เพิ่มรายการ"
    $(document).on('click', '.js-add-computer-btn', function() {

        const $form = $('#computerForm');

        // Reset Form
        $form[0].reset();

        // เคลียร์ ID (เพื่อให้รู้ว่าเป็น Add Mode)
        $('#edit_id').val('');

        // เคลียร์ค่าใน Select2 (ผู้ดูแล/ผู้ใช้งาน)
        $('#department_id').val(null).trigger('change');
        $('#responsible_id').val('').trigger('change');

        // เปลี่ยนหัวข้อกลับเป็น "เพิ่มรายการ"
        $('#textModal').text('เพิ่มรายการ');
        $('#addEditComputerModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายการ');

        // ล้างสถานะการตรวจสอบ (Validation) ของ Bootstrap
        $form.removeClass('was-validated');

        // ล้างสีแดง/เขียว ที่ค้างอยู่ใน Input (ถ้ามี)
        $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');

        // ตั้งค่า Default บางอย่าง เช่น วันที่รับเข้า เป็นวันที่ปัจจุบัน
        $('#computer_receive_date').val(new Date().toISOString().split('T')[0]);
        $('#addEditComputerModal').modal('show');
    });

    $(document).on('click', '.js-edit-computer-btn', function() {
        let dataId = $(this).data('id');
        const $form = $('#computerForm');

        $.ajax({
            url: './api/Master_computer_list/getDataByID.php',
            type: 'GET',
            data: {
                id: dataId
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let d = res.data;

                    // เคลียร์สถานะ Validation เก่าออกก่อนเริ่มหยอดข้อมูล
                    $form.removeClass('was-validated');
                    $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');

                    // เปลี่ยน Text หัวข้อ Modal
                    $('#textModal').text('แก้ไขรายการ');
                    $('#addEditComputerModalLabel').html('<i class="fas fa-edit fa-lg me-2"></i> แก้ไขรายการ');

                    // --- Map ข้อมูลลงใน Form คอมพิวเตอร์ ---

                    // ข้อมูลหลัก 
                    $('#edit_id').val(d.id); // ใส่ ID ซ่อนไว้เพื่อใช้ระบุตอนบันทึก (UPDATE)
                    $('#department_id').val(d.department_id).trigger('change');
                    $('#computer_asset_no').val(d.computer_asset_no);
                    $('#computer_brand').val(d.computer_brand);
                    $('#computer_model').val(d.computer_model);
                    $('#computer_serial').val(d.computer_serial);

                    // ข้อมูลผู้รับผิดชอบ (Select2) ต้อง trigger('change') เพื่อให้ UI ของ Select2 อัปเดตตามค่าที่เลือก
                    $('#responsible_id').val(d.responsible_id).trigger('change');

                    // ข้อมูลวันที่
                    $('#computer_receive_date').val(d.computer_receive_date);
                    $('#computer_start_use_date').val(d.computer_start_use_date);

                    // สั่งเปิด Modal
                    $('#addEditComputerModal').modal('show');
                }
            },
            error: function() {
                Swal.fire('Error', 'ไม่สามารถดึงข้อมูลได้', 'error');
            }
        });
    });

    $(document).on('click', '.js-delete-computer-btn', function(e) {
        e.preventDefault();
        let dataId = $(this).data('id');

        let brand = $(this).data('brand') || '-';
        let model = $(this).data('model') || '-';
        let assetNo = $(this).data('asset') || '-';

        // เช็คก่อนว่ามี ID ไหม 
        if (!dataId) {
            Swal.fire('Error', 'ไม่พบ ID ของรายการที่ต้องการลบ', 'error');
            return;
        }

        Swal.fire({
            title: 'ยืนยันการลบ ?',
            html: `
            <div class="text-center mb-3">คุณต้องการลบรายการคอมพิวเตอร์นี้ใช่หรือไม่?</div>
            <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                <div class="mb-2 pb-2 border-bottom">
                    <span class="text-secondary small">ยี่ห้อ/รุ่น:</span><br>
                    <b class="ps-2 text-danger">${brand} ${model}</b>
                </div>
                <div>
                    <span class="text-secondary small">เลขครุภัณฑ์:</span><br>
                    <b class="ps-2 text-danger">${assetNo}</b>
                </div>
            </div>
        `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> ลบข้อมูล',
            cancelButtonText: '<i class="fas fa-times me-2"></i> ยกเลิก',
            showLoaderOnConfirm: true, // เปิดใช้งาน Loader
            preConfirm: () => {
                return $.ajax({
                        url: './api/Master_computer_list/delete.php',
                        type: 'POST',
                        data: {
                            id: dataId
                        },
                        dataType: 'json'
                    }).then(response => {
                        // เช็ค status ที่ PHP ส่งกลับมา
                        if (response.status !== 'success') {
                            throw new Error(response.message || 'เกิดข้อผิดพลาดในการลบ');
                        }
                        return response;
                    })
                    .catch(error => {
                        // ส่ง error ไปแสดงที่ Swal
                        Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message || 'ไม่สามารถติดต่อ Server ได้'}`);
                    });
            },
            allowOutsideClick: () => !Swal.isLoading() // ป้องกันการกดปิดระหว่างโหลด
        }).then((result) => {
            if (result.isConfirmed) {
                // เมื่อ AJAX สำเร็จ
                Swal.fire({
                    title: 'ลบข้อมูลสำเร็จ!',
                    text: 'รายการคอมพิวเตอร์ถูกลบเรียบร้อยแล้ว',
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 1500,
                    returnFocus: false
                }).then(() => {
                    searchPage(typeof currentPage !== 'undefined' ? currentPage : 1);
                });
            }
        });
    });

    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    // saveData
    $('#computerForm').on('submit', function(e) {
        e.preventDefault();
        // Validate Form ก่อนส่งไปยัง backend 
        const form = this;
        const $form = $(form);

        const receiveDate = $('#computer_receive_date').val();
        const startUseDate = $('#computer_start_use_date').val();
        const $startUseInput = $('#computer_start_use_date');

        if (receiveDate && startUseDate && new Date(startUseDate) < new Date(receiveDate)) {
            $startUseInput.addClass('is-invalid');
        } else {
            // ถ้าแก้แล้วให้เอาออก
            $startUseInput.removeClass('is-invalid');
        }

        if ($startUseInput.hasClass('is-invalid')) {
            Swal.fire({
                icon: 'warning',
                title: 'ข้อมูลไม่ถูกต้อง',
                text: 'วันที่เริ่มใช้งาน "ห้ามย้อนหลัง" ไปก่อนวันที่รับมอบ/รับเข้า',
                confirmButtonColor: '#3085d6',
                returnFocus: false
            }).then(() => {
                $startUseInput.focus();
            });
            return; // หยุดการทำงาน ไม่ส่ง AJAX
        }

        if (!form.checkValidity()) {
            e.stopPropagation();
            $form.addClass('was-validated');

            const invalidElement = findFirstInvalidInput(form);
            let $invalidElement = null;
            let isSelect2 = false;

            if (invalidElement) {
                $invalidElement = $(invalidElement);
                isSelect2 = $invalidElement.hasClass('select2-hidden-accessible');

                const elementToScroll = isSelect2 ?
                    $invalidElement.next('.select2-container')[0] :
                    invalidElement;

                if (elementToScroll) {
                    elementToScroll.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            }

            Swal.fire({
                icon: 'warning',
                title: 'ข้อมูลไม่ครบถ้วน',
                text: 'กรุณากรอกข้อมูลในช่องที่มีเครื่องหมาย * ให้ครบ',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#3085d6',
                returnFocus: false
            }).then((result) => {
                if (result.isConfirmed || result.isDismissed) {
                    if (invalidElement) {
                        if (isSelect2) {
                            $invalidElement.select2('open');
                        } else {
                            invalidElement.focus();
                        }
                    }
                }
            });
            return;
        }

        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: new FormData(form),
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function() {
                $('#btn_save_all').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...');
            },
            success: function(response) {
                if (response.status == "success") {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: 'ข้อมูลคอมพิวเตอร์ถูกบันทึกเรียบร้อยแล้ว',
                        timer: 1500,
                        showConfirmButton: false
                        // }).then(() => {
                        //     window.location.reload();
                        // });
                    }).then(() => {
                        // ปิด Modal
                        $('#addEditComputerModal').modal('hide');

                        // อัปเดตตารางข้อมูล (ไม่ต้อง Reload ทั้งหน้า)
                        if (typeof searchPage === "function") {
                            searchPage(1);
                        } else {
                            window.location.reload(); // กรณีไม่ได้ใช้ระบบ AJAX Table
                        }
                    });
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', response.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", xhr.responseText);
                Swal.fire('Server Error', 'เกิดข้อผิดพลาดที่ระบบ กรุณาลองใหม่ หรือติดต่อผู้ดูแลระบบ', 'error');
            },
            complete: function() {
                $('#btn_save_all').prop('disabled', false).html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล');
            }
        });
    });

    $(document).on('click', '#btn_export_excel', function(e) {
        e.preventDefault(); // กันไว้ก่อนเผื่อปุ่มอยู่ใน Form submit

        // ดึงค่าจากฟอร์มทั้งหมดมาทำเป็น Query String
        const formData = $('#searchFilterForm').serialize();

        // ยิงไปที่ API
        window.location.href = './api/Master_computer_list/exportExcel.php?' + formData;
    });

    function searchPage(page) {
        currentPage = page;

        const formData = $('#searchFilterForm').serializeArray();
        formData.push({
            name: 'page',
            value: page
        });

        // รอเปลี่ยน API
        $.ajax({
            url: './api/Master_computer_list/searchData.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status == 'success') {
                    renderTable(response.data, response.offset);
                    $('#count_display').text(response.count);
                    const total = parseInt(response.totalPages);
                    const current = parseInt(response.currentPage);

                    if (total > 0) {
                        setupPagination(total, current);
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

        if (data && data.length > 0) {
            data.forEach(function(row, i) {

                // 1. จัดการข้อมูลพื้นฐาน (ตรวจสอบค่าว่าง/Null)
                let asset_no = row.computer_asset_no || '-';
                let brand = row.computer_brand || '-';
                let model = row.computer_model || '-';
                let serial_no = row.computer_serial || '-';

                let department_name = row.department_name || '<span class="text-muted small">ไม่ระบุ</span>';

                // ผู้ดูแล (ถ้าไม่มีให้แสดงเป็นตัวจางๆ)
                let responsible = row.responsible_fullname ?
                    row.responsible_fullname :
                    '<span class="text-muted small italic">ยังไม่ระบุ</span>';

                // วันที่ (ใช้ค่าที่ Format มาจาก PHP )
                let receive_date = (row.receive_date_show && row.receive_date_show !== 'null') ? row.receive_date_show : '-';
                let start_use_date = (row.start_use_date_show && row.start_use_date_show !== 'null') ? row.start_use_date_show : '-';

                html += `
                        <tr class="clickable-row cursor-pointer" data-id="${row.id}" title="ดูข้อมูล">
                            <td class="text-center align-middle">
                                <div class="d-flex justify-content-center gap-2">
                                    <i class="fas fa-pen-to-square fa-lg text-warning ${window._canManage ? 'js-edit-computer-btn cursor-pointer' : ''}" 
                                        data-id="${row.id}" 
                                        title="${window._canManage ? 'แก้ไข' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>

                                    <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-computer-btn cursor-pointer' : ''}" 
                                        data-id="${row.id}" 
                                        data-brand="${brand}" 
                                        data-model="${model}" 
                                        data-asset="${asset_no}"
                                        title="${window._canManage ? 'ลบ' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                                </div>
                            </td>
                            <td class="text-center align-middle d-none d-xl-table-cell">${start_no + i}</td>
                            <td class="align-middle">${asset_no}</td> 
                            <td class="align-middle d-none d-lg-table-cell">${department_name}</td>
                            <td class="text-start align-middle">
                                <div class="fw-bold">${brand}</div>
                                <div class="text-muted small">${model}</div>
                            </td>
                            <td class="align-middle d-none d-lg-table-cell">${serial_no}</td>
                            <td class="align-middle d-none d-xl-table-cell">${responsible}</td>
                            <td class="text-center align-middle d-none d-md-table-cell">${receive_date}</td>
                            <td class="text-center align-middle d-none d-md-table-cell">${start_use_date}</td>
                        </tr>
                    `;
            });
        } else {
            html = `
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-laptop fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0 fw-bold">ไม่พบรายการเครื่องคอมพิวเตอร์ในระบบ</p>
                            <small class="opacity-75">ลองเปลี่ยนคำค้นหา  หรือกดปุ่ม <b class="text-dark">" เพิ่มรายการ "</b> เพื่อสร้างข้อมูลใหม่</small>
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

    // 2. ฟังก์ชันสร้างเลขหน้า (Pagination)
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