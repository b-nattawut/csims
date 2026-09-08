<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require_once './includes/check_permission.php';

$canManage = hasPermission($pdo, 'master_data.create');

$title = "รายชื่อหน่วยงาน";

ob_start();
?>

<style>
    .js-edit-btn,
    .js-delete-btn {
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
                    <div class="row g-2 justify-content-start align-items-end">
                        <div class="col-12 col-md-6 col-lg-3 col-xl-3">
                            <button class="btn <?= $canManage ? 'btn-success' : 'btn-secondary' ?> btn-sm px-4 fw-bold shadow-sm text-nowrap w-100 <?= $canManage ? 'js-add-department-btn' : '' ?>"
                                style="height: 31px;"
                                type="button" <?= $canManage ? 'data-bs-toggle="modal" data-bs-target="#addEditDepartmentModal"' : 'disabled' ?>
                                title="<?= $canManage ? 'เพิ่มรายการใหม่' : 'คุณไม่มีสิทธิ์จัดการข้อมูล' ?>">
                                <i class="fas fa-plus me-2"></i>เพิ่มรายการ
                            </button>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3 col-xl-3">
                            <label for="filter_department_name" class="form-label text-muted small mb-1">ชื่อหน่วยงาน</label>
                            <input type="text" id="filter_department_name" name="filter_department_name" class="form-control form-control-sm" placeholder="ค้นหาชื่อหน่วยงาน...">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3 col-xl-3">
                            <label for="filter_created_by" class="form-label text-muted small mb-1">ผู้บันทึก</label>
                            <select id="filter_created_by" name="filter_created_by" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                <option value=""></option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3 col-xl-3">
                            <label for="filter_usage_status" class="form-label text-muted small mb-1">สถานะการใช้งาน</label>
                            <select id="filter_usage_status" name="filter_usage_status" class="form-select form-select-sm">
                                <option value="" selected>-- ทั้งหมด --</option>
                                <option value="active">มีการใช้งาน</option>
                                <option value="empty">ยังไม่มีการใช้งาน</option>
                            </select>
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
                    <th style="min-width: 160px;">ชื่อหน่วยงาน</th>
                    <th style="min-width: 100px;">จำนวนรายการ (รวม)</th>
                    <th style="min-width: 200px;">ผู้บันทึก</th>
                    <th style="min-width: 120px;">วันที่เพิ่มรายการ</th>
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

<!-- Modal สำหรับเพิ่มรายการหน่วยงานใหม่ / แก้ไขรายการเดิม -->
<div class="modal fade" id="addEditDepartmentModal" aria-labelledby="addEditDepartmentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addEditDepartmentModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> <span id="textModal">เพิ่มรายการ</span>
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" id="departmentForm" action="./api/Master_department/save.php" novalidate>
                    <input type="hidden" id="department_id" name="id">

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border">
                        <div class="row g-3 justify-content-start">
                            <div class="col-12">
                                <label for="department_name" class="form-label fw-bold">ชื่อหน่วยงาน <span class="text-danger">*</span></label>
                                <div class="input-group input-group-seamless">
                                    <input type="text" id="department_name" name="department_name" class="form-control"
                                        required autocomplete="off" maxlength="255"
                                        placeholder="เช่น กลุ่มงานตรวจสถานที่เกิดเหตุ, พิสูจน์หลักฐาน จังหวัด...">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="department_name"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุชื่อหน่ยงาน</div>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-end bg-white border-top shadow-sm">
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="departmentForm">
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
<div class="modal fade" id="viewDepartmentDetailModal" tabindex="-1" aria-labelledby="viewDepartmentDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" style="max-width: 90%;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold" id="viewDepartmentDetailModalLabel">
                    <i class="fas fa-search me-2"></i> รายละเอียดหน่วยงาน
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">

                    <div class="col-md-12">
                        <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                            <i class="fas fa-info-circle me-1"></i> ข้อมูลทั่วไป
                        </h6>
                        <div class="row">
                            <div class="col-12 mb-4 d-flex justify-content-between align-items-center border-bottom pb-3">
                                <div>
                                    <label class="text-muted small d-block mb-1">ชื่อหน่วยงาน</label>
                                    <span id="view_dept_name" class="fw-bold">-</span>
                                </div>
                                <div class="text-end text-md-center bg-light p-2 rounded border px-3">
                                    <label class="text-muted small d-block mb-1">รวมรายการที่เกี่ยวข้องทั้งหมด</label>
                                    <span class="fw-bold fs-5 text-dark" id="view_dept_usage_total">0</span>
                                    <span class="text-muted small">รายการ</span>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="text-muted small d-block mb-2">จำนวนรายการที่เกี่ยวข้อง (แยกตามประเภท)</label>
                                <div class="row g-3">

                                    <!-- 1. เครื่องมือ -->
                                    <div class="col-12 col-sm-6 col-xl-3">
                                        <div class="p-3 bg-primary bg-opacity-10 rounded-3 border border-primary border-opacity-25">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-tools"></i>
                                                </div>
                                                <div class="ms-3">
                                                    <label class="text-muted small d-block mb-0">เครื่องมือ</label>
                                                    <span id="view_dept_usage_equipment" class="fw-bold text-primary">0</span> <span class="small text-muted">รายการ</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 2. คอมพิวเตอร์ -->
                                    <div class="col-12 col-sm-6 col-xl-3">
                                        <div class="p-3 bg-info bg-opacity-10 rounded-3 border border-info border-opacity-25">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 bg-info text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-laptop"></i>
                                                </div>
                                                <div class="ms-3">
                                                    <label class="text-muted small d-block mb-0">คอมพิวเตอร์</label>
                                                    <span id="view_dept_usage_computer" class="fw-bold text-info">0</span> <span class="small text-muted">รายการ</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 3. เครื่องวัดอุณหภูมิ -->
                                    <div class="col-12 col-sm-6 col-xl-3">
                                        <div class="p-3 bg-danger bg-opacity-10 rounded-3 border border-danger border-opacity-25">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 bg-danger text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-temperature-half"></i>
                                                </div>
                                                <div class="ms-3">
                                                    <label class="text-muted small d-block mb-0">เครื่องวัดอุณหภูมิ</label>
                                                    <span id="view_dept_usage_thermometer" class="fw-bold text-danger">0</span> <span class="small text-muted">รายการ</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 4. สารเคมี -->
                                    <div class="col-12 col-sm-6 col-xl-3">
                                        <div class="p-3 bg-success bg-opacity-10 rounded-3 border border-success border-opacity-25">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-flask"></i>
                                                </div>
                                                <div class="ms-3">
                                                    <label class="text-muted small d-block mb-0">สารเคมี</label>
                                                    <span id="view_dept_usage_chemical" class="fw-bold text-success">0</span> <span class="small text-muted">รายการ</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12 mt-2">
                        <div class="alert alert-secondary border-0 p-3 mb-0 shadow-sm">
                            <div class="row small text-muted align-items-center">

                                <div class="col-sm-6 border-end border-white">
                                    <div class="d-flex align-items-center" style="min-height: 45px;"> <i class="fas fa-user-plus fa-lg text-secondary me-3"></i>
                                        <div>
                                            <label class="d-block mb-0 fw-bold" style="font-size: 0.7rem;">สร้างโดย:</label>
                                            <span id="view_dept_create_by" class="text-dark d-block" style="font-size: 0.85rem;">-</span>
                                            <span id="view_dept_create_date" class="italic" style="font-size: 0.75rem;">-</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-6 ps-4">
                                    <div class="d-flex align-items-center" style="min-height: 45px;">
                                        <i class="fas fa-user-pen fa-lg text-secondary me-3"></i>
                                        <div>
                                            <label class="d-block mb-0 fw-bold" style="font-size: 0.7rem;">แก้ไขล่าสุดโดย:</label>
                                            <span id="view_dept_update_by" class="text-dark d-block" style="font-size: 0.85rem;">-</span>
                                            <span id="view_dept_update_date" class="italic" style="font-size: 0.75rem;">-</span>
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

    $(document).ready(function() {
        // เริ่มต้นโหลดข้อมูล
        searchPage(1);
        loadUserList(); // โหลดรายชื่อสำหรับ Filter ผู้บันทึกข้อมูล

        // Initialize Select2 สำหรับ Filter
        if ($('.select2-filter').length) {
            $('.select2-filter').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                placeholder: $(this).data('placeholder')
            }).next('.select2-container').addClass('select2-sm-custom');
        }
    });

    // --- 1. การดึงข้อมูลและแสดงผลตาราง ---

    // ฟังก์ชันค้นหาและแบ่งหน้า
    function searchPage(page) {
        const formData = $('#searchFilterForm').serializeArray();
        formData.push({
            name: 'page',
            value: page
        });

        $.ajax({
            url: './api/Master_department/searchData.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status == 'success') {
                    renderTable(response.data, response.offset);
                    $('#count_display').text(response.count);
                    const total = parseInt(response.totalPages);
                    const current = parseInt(response.currentPage);

                    console.log("กำลังส่งค่าไป SetupPagination:", total, current);

                    if (total > 0) {
                        setupPagination(total, current);
                    }
                }
            }
        });
    }

    function renderTable(data, offset) {
        let html = '';
        let start_no = parseInt(offset) + 1;

        if (data && data.length > 0) {
            data.forEach(function(row, i) {
                // ถ้าถูกใช้งานอยู่ (usage > 0) จะแสดงสีเน้น และอาจคุมปุ่มลบ
                let usageText = '';
                if (row.total_usage > 0) {
                    // กรณีมีการใช้งาน: ใช้ตัวหนา และสี Primary (น้ำเงิน) เพื่อให้ดูมีความสำคัญ
                    usageText = `<b class="text-primary">${row.total_usage}</b>`;
                } else {
                    // กรณีไม่มีการใช้งาน: ใช้สีจาง (Muted) เพื่อสื่อว่าว่างอยู่ และสามารถลบได้
                    usageText = `<span class="text-muted opacity-50">0</span>`;
                }

                html += `
                    <tr class="clickable-row cursor-pointer" data-id="${row.id}" title="ดูรายละเอียด">
                        <td class="text-center align-middle">
                            <div class="d-flex justify-content-center gap-2">
                                <i class="fas fa-pen-to-square fa-lg text-warning ${window._canManage ? 'js-edit-btn cursor-pointer' : ''}" 
                                   data-id="${row.id}" title="${window._canManage ? 'แก้ไข' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                                
                                <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-btn cursor-pointer' : ''}" 
                                   data-id="${row.id}" 
                                   data-name="${row.department_name}"
                                   data-usage="${row.total_usage}"
                                   data-creator="${row.creator_name}"
                                   title="${window._canManage ? 'ลบ' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                            </div>
                        </td>
                        <td class="text-center d-none d-xl-table-cell align-middle">${start_no + i}</td>
                        <td class="text-start align-middle ">${row.department_name}</td>
                        <td class="text-center align-middle">${usageText}</td>
                        <td class="text-start align-middle">${row.creator_name}</td>
                        <td class="text-center align-middle">${row.created_at_show}</td>
                    </tr>
                `;
            });
        } else {
            html = `
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-building fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0 fw-bold">ไม่พบรายการหน่วยงานในระบบ</p>
                            <small class="opacity-75">ลองเปลี่ยนคำค้นหา  หรือกดปุ่ม <b class="text-dark">" เพิ่มรายการ "</b> เพื่อสร้างข้อมูลใหม่</small>
                        </div>
                    </td>
                </tr>
            `;
        }
        $('#table_body').html(html);
    }

    // --- 2. การจัดการ Modal (Add / Edit / View) ---

    // ดูรายละเอียด
    $(document).on('click', '.clickable-row', function(e) {
        if ($(e.target).closest('.js-edit-btn, .js-delete-btn').length) return;

        const id = $(this).data('id');
        $.get('./api/Master_department/getDataByID.php', {
            id: id
        }, function(res) {
            if (res.status === 'success') {
                const d = res.data;
                $('#view_dept_name').text(d.department_name);

                // ยอดรวมทั้งหมด
                $('#view_dept_usage_total').text(d.total_usage || 0);

                // ยอดแยกประเภท
                $('#view_dept_usage_equipment').text(d.usage_equipment || 0);
                $('#view_dept_usage_computer').text(d.usage_computer || 0);
                $('#view_dept_usage_thermometer').text(d.usage_thermometer || 0);
                $('#view_dept_usage_chemical').text(d.usage_chemical || 0);

                $('#view_dept_create_by').text(d.creator_name || '-');
                $('#view_dept_create_date').text(d.created_at_full || '-');
                $('#view_dept_update_by').text(d.updater_name || '-');
                $('#view_dept_update_date').text(d.updated_at_full || '');
                $('#viewDepartmentDetailModal').modal('show');
            }
        }, 'json');
    });

    // เปิด Modal เพิ่มข้อมูล
    $(document).on('click', '.js-add-department-btn', function() {
        $('#departmentForm')[0].reset();
        $('#department_id').val('');
        $('#addEditDepartmentModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายการ');
        $('#departmentForm').removeClass('was-validated');
        $('#addEditDepartmentModal').modal('show');
    });

    // เปิด Modal แก้ไขข้อมูล
    $(document).on('click', '.js-edit-btn', function() {
        const id = $(this).data('id');
        $.get('./api/Master_department/getDataByID.php', {
            id: id
        }, function(res) {
            if (res.status === 'success') {
                $('#department_id').val(res.data.id);
                $('#department_name').val(res.data.department_name);
                $('#addEditDepartmentModalLabel').html('<i class="fas fa-edit fa-lg me-2"></i> แก้ไขชื่อหน่วยงาน');
                $('#addEditDepartmentModal').modal('show');
            }
        }, 'json');
    });

    // --- 3. บันทึกข้อมูล (Save) ---
    $('#departmentForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        if (!form.checkValidity()) {
            $(form).addClass('was-validated');
            return;
        }

        $.ajax({
            url: './api/Master_department/save.php',
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function() {
                $('#btn_save_all').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            },
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false,
                        returnFocus: false
                    });
                    $('#addEditDepartmentModal').modal('hide');
                    searchPage(1);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            complete: function() {
                $('#btn_save_all').prop('disabled', false).html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล');
            }
        });
    });

    // --- 4. การลบข้อมูล (Delete) ---
    $(document).on('click', '.js-delete-btn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const usage = $(this).data('usage');
        const creator = $(this).data('creator');

        // ถ้ามีข้อมูลผูกอยู่ ลบไม่ได้!
        if (usage > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'ไม่สามารถลบรายการได้',
                html: `
                <div class="text-center mb-3">หน่วยงาน <b>"${name}"</b> ยังมีการใช้งานอยู่</div>
                <div class="p-3 bg-light rounded border border-warning shadow-sm">
                    มีรายการในระบบที่ผูกกับหน่วยงานนี้อยู่ <b class="text-danger"> ${usage} รายการ</b><br>
                    <small class="text-muted text-start d-block mt-2">* กรุณาย้ายรายการเหล่านั้นไปหน่วยงานอื่น หรือลบข้อมูลออกก่อน จึงจะสามารถลบหน่วยงานนี้ได้</small>
                </div>
            `,
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'ตกลง',
                returnFocus: false
            });
            return;
        }

        Swal.fire({
            title: 'ยืนยันการลบ?',
            html: `
                <div class="text-center mb-3">คุณต้องการลบหน่วยงานนี้ใช่หรือไม่?</div>
                <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">ชื่อหน่วยงาน:</span><br>
                        <b class="ps-2 text-danger">${name}</b>
                    </div>
                    <div>
                        <span class="text-secondary small">ผู้บันทึกข้อมูล:</span><br>
                        <b class="ps-2 text-danger">${creator || '-'}</b>
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
            returnFocus: false,
            preConfirm: () => {
                return $.ajax({
                    url: './api/Master_department/delete.php',
                    type: 'POST',
                    data: {
                        id: id
                    },
                    dataType: 'json'
                }).then(response => {
                    if (response.status !== 'success') {
                        throw new Error(response.message || 'เกิดข้อผิดพลาดในการลบ');
                    }
                    return response;
                }).catch(error => {
                    Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'ลบข้อมูลสำเร็จ!',
                    text: 'หน่วยงานถูกลบเรียบร้อยแล้ว',
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 1500,
                    returnFocus: false
                }).then(() => {
                    searchPage(1);
                });
            }
        });
    });

    $(document).on('click', '#btn_export_excel', function(e) {
        e.preventDefault(); // กันไว้ก่อนเผื่อปุ่มอยู่ใน Form submit

        // ดึงค่าจากฟอร์มทั้งหมดมาทำเป็น Query String
        const formData = $('#searchFilterForm').serialize();

        // ยิงไปที่ API
        window.location.href = './api/Master_department/exportExcel.php?' + formData;
    });

    // --- 5. Helper Functions ---
    function loadUserList() {
        $.get('./api/get_users.php', function(res) {
            if (res.status === 'success') {
                let options = '<option value=""></option>';
                res.data.forEach(item => {
                    options += `<option value="${item.user_id}">${item.fullname}</option>`;
                });
                $('#filter_created_by').html(options).trigger('change');
            }
        }, 'json');
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