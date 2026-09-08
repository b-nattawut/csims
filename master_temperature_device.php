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

$title = "รายการเครื่องวัดอุณหภูมิ";

ob_start();
?>
<style>
    .js-edit-temp-device-btn,
    .js-delete-temp-device-btn {
        padding: 12px;
        /* ขยายพื้นที่จิ้มออกไปรอบๆ */
        margin: -12px;
        /* ดึงขอบกลับมาเพื่อไม่ให้กระทบตำแหน่งในตาราง */
        cursor: pointer;
        touch-action: manipulation;
        /* ป้องกันการซูมเวลาเผลอ Double Tap */
    }

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
        margin-top: 5px;
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
                        <div class="col-12 col-sm-6 col-md-6 <?= ($user_role !== 'admin') ? 'col-lg-3 col-xl-3' : 'col-lg-4 col-xl-4 ' ?>">
                            <button class="btn <?= $canManage ? 'btn-success' : 'btn-secondary' ?> btn-sm px-4 fw-bold shadow-sm text-nowrap w-100 <?= $canManage ? 'js-add-temp-device-btn' : '' ?>"
                                style="height: 31px;"
                                type="button" <?= $canManage ? 'data-bs-toggle="modal" data-bs-target="#addEditTemperatureDeviceModal"' : 'disabled' ?>
                                title="<?= $canManage ? 'เพิ่มรายการใหม่' : 'คุณไม่มีสิทธิ์จัดการข้อมูล' ?>">
                                <i class="fas fa-plus me-2"></i>เพิ่มรายการ
                            </button>
                        </div>

                        <?php if ($user_role === 'admin'): ?>
                            <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                                <label for="filter_department_id" class="form-label text-muted small mb-1">หน่วยงาน</label>
                                <select id="filter_department_id" name="filter_department_id" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                    <option value=""></option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="col-12 col-sm-6 col-md-6 <?= ($user_role !== 'admin') ? 'col-lg-3 col-xl-3' : 'col-lg-4 col-xl-4 ' ?>">
                            <label for="filter_device_code" class="form-label text-muted small mb-1">หมายเลขเครื่องวัด</label>
                            <input type="text" id="filter_device_code" name="filter_device_code" class="form-control form-control-sm" placeholder="ระบุหมายเลขเครื่อง...">
                        </div>


                        <div class="col-12 col-sm-6 col-md-6 <?= ($user_role !== 'admin') ? 'col-lg-3 col-xl-3' : 'col-lg-4 col-xl-4 ' ?>">
                            <label for="filter_location_use" class="form-label text-muted small mb-1">สถานที่ใช้งาน</label>
                            <input type="text" id="filter_location_use" name="filter_location_use" class="form-control form-control-sm" placeholder="ระบุสถานที่...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 <?= ($user_role !== 'admin') ? 'col-lg-3 col-xl-3' : 'col-lg-4 col-xl-4 ' ?>">
                            <label for="filter_responsible_id" class="form-label text-muted small mb-1">ผู้รับผิดชอบ</label>
                            <select id="filter_responsible_id" name="filter_responsible_id"
                                class="form-select form-select-sm select2-filter"
                                data-placeholder="-- ทั้งหมด --">
                                <option value=""></option>
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

<!-- Table แสดงรายการข้อมูลทั้งหมด -->
<div class="table-responsive">
    <div class="table-wrapper-focus">
        <table class="table table-bordered table-hover table-custom table-striped align-middle mb-0">
            <thead style="font-size: 14px;">
                <tr class="text-nowrap text-center ">
                    <th style="min-width: 100px;">Action</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 50px;">ลำดับ</th>
                    <th style="min-width: 150px;">หมายเลขเครื่องวัด</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 120px;">หน่วยงาน</th>
                    <th style="min-width: 150px;">สถานที่ใช้งาน</th>
                    <th class="d-none d-md-table-cell" style="min-width: 130px;">ช่วงการใช้งาน</th>
                    <th class="d-none d-md-table-cell" style="min-width: 180px;">ผู้รับผิดชอบ</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 150px;">ค่าความไม่แน่นอนของการวัด</th>
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

<!-- Modal สำหรับเพิ่ม / แก้ไข รายการเครื่องวัดอุณหภูมิ-->
<div class="modal fade" id="addEditTemperatureDeviceModal" aria-labelledby="addEditTemperatureDeviceModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addEditTemperatureDeviceModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i><span id="textModal">เพิ่มรายการ</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" id="temperatureDeviceForm" name="temperatureDeviceForm" action="./api/Master_temperature_device/save.php" novalidate>
                    <input type="hidden" id="edit_id" name="id">

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header"><i class="fas fa-info-circle me-1"></i> ข้อมูลทั่วไปของเครื่องวัดอุณหภูมิ</legend>
                        <div class="row g-3 justify-content-start">

                            <div class="col-md-6 <?= ($user_role !== 'admin') ? 'd-none' : '' ?>">
                                <label for="department_id" class="form-label">หน่วยงาน <span class="text-danger">*</span></label>
                                <select id="department_id" name="department_id" class="form-select select2-dept" data-placeholder="-- ค้นหาหน่วยงาน --" <?= ($user_role === 'admin') ? 'required' : '' ?>>
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาระบุหน่วยงาน</div>
                            </div>

                            <div class="col-md-6">
                                <label for="device_code" class="form-label">เครื่องวัดอุณหภูมิ หมายเลข <span class="text-danger">*</span></label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="device_code"
                                        name="device_code"
                                        class="form-control"
                                        placeholder="ระบุหมายเลขเครื่องวัด (เช่น TEMP-001)"
                                        required>
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="device_code"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุหมายเลขเครื่องวัด</div>
                            </div>

                            <div class="<?= ($user_role !== 'admin') ? 'col-md-6' : 'col-md-12' ?>">
                                <label for="location_use" class="form-label">สถานที่ใช้งาน </label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="location_use"
                                        name="location_use"
                                        class="form-control"
                                        placeholder="เช่น ห้องเย็นชั้น 1, ตู้แช่ยาแผนกฉุกเฉิน">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="location_use"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">ช่วงการใช้งาน (°C)</label>
                                <div class="input-group">
                                    <input type="number" step="any" inputmode="decimal" id="range_min" name="range_min" class="form-control text-center" placeholder="ต่ำสุด (เช่น 2.0)">
                                    <span class="input-group-text bg-light">ถึง</span>
                                    <input type="number" step="any" inputmode="decimal" id="range_max" name="range_max" class="form-control text-center" placeholder="สูงสุด (เช่น 8.0)">
                                </div>
                                <div class="invalid-feedback d-block-manual" id="range_error" style="display:none;">
                                    ค่าต่ำสุดห้ามมากกว่าค่าสูงสุด
                                </div>
                                <div class="small text-muted opacity-50 mt-2">ระบุช่วงอุณหภูมิมาตรฐานของเครื่องมือนี้</div>
                            </div>

                            <div class="col-md-6">
                                <label for="measurement_uncertainty" class="form-label">ค่าความไม่แน่นอนของการวัด (°C)</label>
                                <div class="input-group">
                                    <span class="input-group-text">±</span>
                                    <input type="number" step="any" inputmode="decimal" id="measurement_uncertainty" name="measurement_uncertainty" class="form-control" placeholder="เช่น 0.05">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="responsible_person_1" class="form-label">ผู้รับผิดชอบคนที่ 1 <span class="text-danger">*</span></label>
                                <select id="responsible_person_1" name="responsible_person_1" class="form-select select2-modal" data-placeholder="-- ค้นหาชื่อผู้รับผิดชอบ --">
                                    <option value=""></option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="responsible_person_2" class="form-label">ผู้รับผิดชอบคนที่ 2 <small class="fw-normal">(ถ้ามี)</small></label>
                                <select id="responsible_person_2" name="responsible_person_2" class="form-select select2-modal" data-placeholder="-- ค้นหาชื่อผู้รับผิดชอบ --">
                                    <option value=""></option>
                                </select>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>

            <!-- ปุ่มด้านล่าง -->
            <div class="modal-footer d-flex justify-content-end bg-white border-top shadow-sm">
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="temperatureDeviceForm">
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
            <div class="modal-header bg-info text-white py-3">
                <h5 class="modal-title fw-bold" id="viewDetailModalLabel">
                    <i class="fas fa-search me-2"></i> รายละเอียดเครื่องวัดอุณหภูมิ
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-12">
                        <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                            <i class="fas fa-info-circle me-1"></i> ข้อมูลเครื่องวัดอุณหภูมิ
                        </h6>
                        <div class="row">
                            <div class="col-12 col-lg-6 mb-3">
                                <label class="text-muted small d-block">หน่วยงาน</label>
                                <span id="view_department_name" class="fw-bold">-</span>
                            </div>
                            <div class="col-12 col-lg-6 mb-3">
                                <label class="text-muted small d-block">หมายเลขเครื่องวัด</label>
                                <span id="view_device_code" class="fw-bold">-</span>
                            </div>
                            <div class="col-sm-12 mb-3">
                                <label class="text-muted small d-block">สถานที่ใช้งาน</label>
                                <span id="view_location_use" class="fw-bold">-</span>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="text-muted small d-block">ช่วงการใช้งาน (°C)</label>
                                <span id="view_temp_range" class="fw-bold">-</span>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="text-muted small d-block">ค่าความไม่แน่นอนของการวัด (°C)</label>
                                <span id="view_uncertainty" class="fw-bold">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                            <i class="fas fa-users me-1"></i> รายชื่อผู้รับผิดชอบ
                        </h6>
                        <div class="row">
                            <div class="col-sm-6 mb-2">
                                <label class="text-muted small d-block">ผู้รับผิดชอบคนที่ 1</label>
                                <span id="view_resp_1" class="fw-bold">-</span>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <label class="text-muted small d-block">ผู้รับผิดชอบคนที่ 2</label>
                                <span id="view_resp_2" class="fw-bold">-</span>
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
                                            <span id="view_create_by" class="text-dark d-block" style="font-size: 0.85rem;">-</span>
                                            <span id="view_create_date" class="italic" style="font-size: 0.75rem;">-</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-6 ps-4">
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

    $(document).ready(function() {
        // เริ่มต้นโหลดข้อมูลหน้าแรก
        searchPage(1);

        // โหลดรายชื่อผู้ใช้งานมาใส่ใน Select Box (ทำครั้งเดียวตอนโหลดหน้า)
        loadUserListForFilter();

        if ($('#filter_department_id').length) {
            loadDepartments();
        }

        // ตั้งค่า Select2 สำหรับผู้รับผิดชอบใน Modal
        initResponsibleSelect2('#responsible_person_1', '#responsible_person_2');
        initResponsibleSelect2('#responsible_person_2', '#responsible_person_1');

        $('.select2-filter').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
        }).next('.select2-container').addClass('select2-sm-custom');

        $('.select2-dept').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            dropdownParent: $('#addEditTemperatureDeviceModal')
        });

        // ดักจับการกรอกข้อมูลในช่อง Range
        $('#range_min, #range_max').on('input change', function() {
            const min = parseFloat($('#range_min').val());
            const max = parseFloat($('#range_max').val());

            if (!isNaN(min) && !isNaN(max) && min > max) {
                // ถ้าผิด: ใส่ขอบแดงทั้งคู่
                $('#range_min, #range_max').addClass('is-invalid');
                $('#range_error').show();
            } else {
                // ถ้าถูก: ล้างขอบแดง
                $('#range_min, #range_max').removeClass('is-invalid');
                $('#range_error').hide();
            }
        });

        // ดัก Event ตอนเปิด Modal (ทั้ง Add และ Edit)
        $('#addEditTemperatureDeviceModal').on('show.bs.modal', function() {
            // เช็คความถูกต้องของอุณหภูมิและผู้รับผิดชอบทันที (เผื่อกรณี Edit ข้อมูลเก่าที่มีปัญหา)
            setTimeout(() => {
                validateResponsibleSync(); // เช็คคนซ้ำ
                // เช็ค Range อุณหภูมิ
                const min = parseFloat($('#range_min').val());
                const max = parseFloat($('#range_max').val());
                if (!isNaN(min) && !isNaN(max) && min > max) {
                    $('#range_min, #range_max').addClass('is-invalid');
                }
            }, 150);
        });

        // 2. ดัก Event ตอนปิด Modal (ล้างไพ่ใหม่ทั้งหมด)
        $('#addEditTemperatureDeviceModal').on('hidden.bs.modal', function() {
            const $form = $('#temperatureDeviceForm');

            // ล้างข้อมูลใน Form (กรณีไม่ได้กดผ่านปุ่ม Add/Edit โดยตรง เช่น กด ESC)
            $form[0].reset();
            $form.find('select').val(null).trigger('change');

            // ล้าง Class Validation ของ Bootstrap
            $form.removeClass('was-validated');
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').hide();

            // ล้าง Style พิเศษที่เราทำไว้กับ Select2 และ Error Label
            $('.select2-container').removeClass('border border-danger rounded');
            $('#resp_2_error').addClass('d-none');
        });
    });

    // --- ฟังก์ชัน Init Select2 สำหรับผู้รับผิดชอบ (แบบฉลาด) ---
    function initResponsibleSelect2(currentId, otherId) {
        $(currentId).select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#addEditTemperatureDeviceModal'),
            allowClear: true,
            placeholder: "-- เลือกผู้รับผิดชอบ --",
            width: '100%',
            ajax: {
                url: './api/get_users.php',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    q: params.term
                }),
                processResults: function(response) {
                    const otherValue = $(otherId).val(); // เช็คว่าอีกช่องเลือกใครอยู่
                    return {
                        results: $.map(response.data, function(item) {
                            return {
                                id: item.user_id,
                                text: item.fullname,
                                // ถ้า ID ตรงกับอีกช่อง ให้ Disabled ทันที
                                disabled: (otherValue && item.user_id == otherValue)
                            };
                        })
                    };
                },
                cache: true
            }
        }).on('change', function() {
            validateResponsibleSync(); // เช็ค Error แดงๆ
        });
    }

    // --- 3. ดัก Event เมื่อมีการเลือก เพื่อให้ทั้งสองช่อง Update สถานะกันเอง ---
    $('#responsible_person_1, #responsible_person_2').on('change', function() {
        validateResponsibleSync();
    });

    function validateResponsibleSync() {
        const p1 = $('#responsible_person_1').val();
        const p2 = $('#responsible_person_2').val();
        const $p2Select = $('#responsible_person_2').next('.select2-container');
        const $errorLabel = $('#resp_2_error');

        if (p1 && p2 && p1 === p2) {
            $p2Select.addClass('border border-danger rounded');
            $errorLabel.removeClass('d-none');
        } else {
            $p2Select.removeClass('border border-danger');
            $errorLabel.addClass('d-none');
        }
    }

    // --- ฟังก์ชันสำหรับ Edit Mode (สำคัญมากสำหรับ AJAX Select2) ---
    function setSelect2Value(elementId, id, text) {
        const $select = $(elementId);
        if (id) {
            // สำหรับ AJAX Select2 เราต้องสร้าง Option ขึ้นมาเองก่อนแล้วค่อยสั่งเลือก
            const newOption = new Option(text, id, true, true);
            $select.append(newOption).trigger('change');
        } else {
            $select.val(null).trigger('change');
        }
    }

    // --- โหลด User เฉพาะช่อง Filter ---
    function loadUserListForFilter() {
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
                    $('#filter_responsible_id').html(options).trigger('change');
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
                    // อัปเดตทั้งใน Filter หน้าหลัก และ ฟอร์ม Modal
                    $('#filter_department_id, #department_id').html(options);
                    $('#filter_department_id').trigger('change');
                    $('#department_id').trigger('change');
                }
            }
        });
    }

    // 1. ฟังก์ชันสำหรับดึงข้อมูล Temperature Device และเปิด Modal
    function viewTemperatureDeviceDetail(id) {
        $.ajax({
            url: './api/Master_temperature_device/getDataByID.php',
            method: 'GET',
            data: {
                id: id
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let d = res.data;

                    // 1. จัดการส่วน "ช่วงการใช้งาน"
                    let range_display = (d.range_min !== null && d.range_max !== null) ?
                        `${parseFloat(d.range_min).toFixed(2)} - ${parseFloat(d.range_max).toFixed(2)} °C` :
                        '<span class="text-muted">-</span>';

                    // 2. จัดการส่วน "ค่าความไม่แน่นอน"
                    let uncertainty_display = (d.measurement_uncertainty !== null) ?
                        `<span class="fw-bold">± ${parseFloat(d.measurement_uncertainty).toFixed(3)} °C</span>` :
                        '<span class="text-muted">-</span>';

                    // Mapping ข้อมูลเฉพาะของ Temperature Device
                    $('#view_department_name').text(d.department_name || '-');
                    $('#view_device_code').text(d.device_code || '-');
                    $('#view_location_use').text(d.location_use || '-');
                    $('#view_temp_range').html(range_display);
                    $('#view_uncertainty').html(uncertainty_display);
                    $('#view_resp_1').text(d.resp_fullname_1 || '-');
                    $('#view_resp_2').text(d.resp_fullname_2 || '-');

                    // ข้อมูล Log การบันทึก
                    $('#view_create_by').text(d.fullname_create || '-');
                    $('#view_create_date').text(d.createdate || '-');
                    $('#view_update_by').text(d.fullname_update || '-');
                    $('#view_update_date').text(d.updatedate || '');

                    $('#viewDetailModal').modal('show');
                }
            }
        });
    }

    // 2. ดักจับการคลิกที่แถวตาราง (ยกเว้นไอคอนแก้ไข/ลบ)
    $(document).on('click', '.clickable-row', function(e) {
        // ป้องกันการทำงานซ้ำซ้อนถ้าคลิกโดนปุ่มจัดการ (ไอคอน <i> หรือปุ่มรอบข้าง)
        if ($(e.target).closest('.js-edit-temp-device-btn, .js-delete-temp-device-btn').length) {
            return;
        }

        const id = $(this).data('id');
        viewTemperatureDeviceDetail(id);
    });

    // เพิ่ม Event เมื่อกดปุ่ม "เพิ่มรายการ"
    $(document).on('click', '.js-add-temp-device-btn', function() {
        const $form = $('#temperatureDeviceForm');
        // 1. Reset Form
        $form[0].reset();

        // 2. เคลียร์ค่า Select2 ทั้งหมด (รวมหน่วยงาน และผู้รับผิดชอบ)
        $form.find('select').val(null).trigger('change');

        $('.select2-container').removeClass('border border-danger rounded');
        $('#resp_2_error').addClass('d-none');

        // 3. เคลียร์ ID (เพื่อให้รู้ว่าเป็น Add Mode)
        $('#edit_id').val('');

        // 4. เปลี่ยนหัวข้อกลับเป็น "เพิ่มรายการ"
        $('#textModal').text('เพิ่มรายการ');
        $('#addEditTemperatureDeviceModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายการ');

        // 5. ลบ Class Validated ออก
        $form.removeClass('was-validated');
        $form.find('.is-invalid').removeClass('is-invalid');

        $('#addEditTemperatureDeviceModal').modal('show');
    });

    // --- แก้ไขข้อมูล (Edit) ---
    $(document).on('click', '.js-edit-temp-device-btn', function() {
        let dataId = $(this).data('id');
        const $form = $('#temperatureDeviceForm');

        $.ajax({
            url: './api/Master_temperature_device/getDataByID.php',
            type: 'GET',
            data: {
                id: dataId
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let data = res.data;

                    $form.removeClass('was-validated');
                    $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');
                    $('#textModal').text('แก้ไขรายการ');
                    $('#addEditTemperatureDeviceModalLabel').html('<i class="fas fa-edit fa-lg me-2"></i> แก้ไขรายการ');


                    // Map ข้อมูลลง Form
                    $('#edit_id').val(data.id);
                    $('#department_id').val(data.department_id).trigger('change');
                    $('#device_code').val(data.device_code);
                    $('#location_use').val(data.location_use);
                    $('#range_min').val(data.range_min);
                    $('#range_max').val(data.range_max);
                    $('#measurement_uncertainty').val(data.measurement_uncertainty);

                    setSelect2Value('#responsible_person_1', data.resp_id_1, data.resp_fullname_1);
                    setSelect2Value('#responsible_person_2', data.resp_id_2, data.resp_fullname_2);
                    $('.select2-container').removeClass('border border-danger rounded');

                    $('#addEditTemperatureDeviceModal').modal('show');
                }
            }
        });
    });

    // --- ลบข้อมูล (Delete) ---
    $(document).on('click', '.js-delete-temp-device-btn', function(e) {
        let dataId = $(this).data('id');
        let deviceCode = $(this).data('name');
        let locationUse = $(this).data('location');

        // เช็คก่อนว่ามี ID ไหม 
        if (!dataId) {
            Swal.fire('Error', 'ไม่พบ ID ของรายการ', 'error');
            return;
        }

        Swal.fire({
            title: 'ยืนยันการลบ ?',
            html: `
                <div class="text-center mb-3">คุณต้องการลบรายการเครื่องวัดอุณหภูมินี้ใช่หรือไม่?</div>
                <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">หมายเลขเครื่องวัด:</span><br>
                        <b class="ps-2 text-danger">${deviceCode}</b>
                    </div>
                    <div>
                        <span class="text-secondary small">สถานที่ใช้งาน:</span><br>
                        <b class="ps-2 text-danger">${locationUse}</b>
                    </div>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> ลบข้อมูล',
            cancelButtonText: '<i class="fas fa-times me-2"></i> ยกเลิก',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                        url: './api/Master_temperature_device/delete.php',
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
                        Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message || error.statusText}`);
                    });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                // เมื่อ AJAX สำเร็จ
                Swal.fire({
                    title: 'ลบข้อมูลสำเร็จ!',
                    text: 'รายการถูกลบเรียบร้อยแล้ว',
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

    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    // --- บันทึกข้อมูล (Save) ---
    $('#temperatureDeviceForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;

        const p1 = $('#responsible_person_1').val();
        const p2 = $('#responsible_person_2').val();

        if (p1 && p2 && p1 === p2) {
            Swal.fire({
                icon: 'error',
                title: 'ข้อมูลไม่ถูกต้อง',
                text: 'ผู้รับผิดชอบคนที่ 1 และคนที่ 2 ต้องไม่เป็นคนละคนกัน',
                confirmButtonColor: '#3085d6',
                returnFocus: false
            });
            return false; // หยุดการทำงาน
        }

        // --- ตรวจสอบช่วงอุณหภูมิ  ---
        const min = parseFloat($('#range_min').val());
        const max = parseFloat($('#range_max').val());

        if (!isNaN(min) && !isNaN(max)) { // เช็คเฉพาะเมื่อมีการกรอกทั้งสองช่อง
            if (min > max) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ช่วงอุณหภูมิไม่ถูกต้อง',
                    text: 'ค่าอุณหภูมิต่ำสุด ห้ามมากกว่า ค่าอุณหภูมิสูงสุด',
                    confirmButtonColor: '#3085d6',
                    returnFocus: false
                }).then(() => {
                    $('#range_min').addClass('is-invalid');
                    $('#range_max').addClass('is-invalid').focus();
                });
                return false;
            }
        }

        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');

            // Scroll ไปจุดที่ผิด
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
            url: $(this).attr('action'),
            type: 'POST',
            data: new FormData(this),
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
                            text: 'ข้อมูลถูกบันทึกเรียบร้อยแล้ว',
                            timer: 1500,
                            showConfirmButton: false
                        })
                        .then(() => {
                            location.reload();
                        });
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', response.message, 'error');
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
        window.location.href = './api/Master_temperature_device/exportExcel.php?' + formData;
    });

    // --- ค้นหาข้อมูล (Search & Pagination) ---
    function searchPage(page) {
        const formData = $('#searchFilterForm').serializeArray();
        formData.push({
            name: 'page',
            value: page
        });

        $.ajax({
            url: './api/Master_temperature_device/searchData.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status == 'success') {
                    // 1. ส่งทั้ง data และ offset ไป render ตาราง (เพื่อให้ลำดับที่แสดงถูกต้อง)
                    renderTable(response.data, response.offset);

                    // 2. แสดงจำนวนรายการทั้งหมด (ถ้ามี element นี้ในหน้าจอ)
                    $('#count_display').text(response.count);

                    // 3. จัดการ Pagination
                    const total = parseInt(response.totalPages);
                    const current = parseInt(response.currentPage);

                    if (total > 0) {
                        setupPagination(total, current);
                    } else {
                        // กรณีค้นหาแล้วไม่เจอเลย ให้ทำลาย Pagination เดิมทิ้ง
                        $('#pagination-list').twbsPagination('destroy');
                    }
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'ไม่สามารถเชื่อมต่อกับ Server ได้', 'error');
            }
        });
    }

    function renderTable(data, offset) {
        let html = '';
        let start_no = (isNaN(parseInt(offset))) ? 1 : parseInt(offset) + 1;

        if (data && data.length > 0) {
            data.forEach(function(row, i) {

                // 1. จัดการข้อมูลเบื้องต้นและเช็คค่าว่าง
                let deptName = row.department_name || '-';
                let deviceCode = row.device_code || '-';
                let locationUse = row.location_use || '-';

                let rangeDisplay = (row.range_min !== null && row.range_max !== null) ?
                    `${row.range_min} - ${row.range_max} °C` :
                    '-';

                // จัดการส่วน "ค่าความไม่แน่นอน"
                let uncertaintyDisplay = (row.measurement_uncertainty !== null) ?
                    `± ${row.measurement_uncertainty} °C` :
                    '-';

                // จัดการส่วน "ผู้รับผิดชอบ" แบบ Dynamic
                let responsibleDisplay = '';

                if (row.resp_name_1 && row.resp_name_2) {
                    // กรณีมี 2 คน: ให้ใส่ลำดับ 1. และ 2.
                    responsibleDisplay = `
                        <div>1. ${row.resp_name_1}</div>
                        <div>2. ${row.resp_name_2}</div>
                    `;
                } else if (row.resp_name_1) {
                    // กรณีมีคนเดียว: โชว์ชื่อเต็มไปเลย ไม่ต้องมีเลขลำดับ
                    responsibleDisplay = row.resp_name_1;
                } else {
                    // กรณีไม่มีข้อมูล (กันเหนียว)
                    responsibleDisplay = '<span class="text-muted">-</span>';
                }

                html += `
                    <tr class="clickable-row cursor-pointer" data-id="${row.id}" title="ดูข้อมูล">
                        <td class="text-center align-middle">                            
                            <div class="d-flex justify-content-center gap-2">
                                <i class="fas fa-pen-to-square fa-lg text-warning ${window._canManage ? 'js-edit-temp-device-btn cursor-pointer' : ''}" 
                                    data-id="${row.id}" 
                                    title="${window._canManage ? 'แก้ไข' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>

                                <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-temp-device-btn cursor-pointer' : ''}" 
                                    data-id="${row.id}" 
                                    data-name="${deviceCode}" 
                                    data-location="${locationUse}"
                                    title="${window._canManage ? 'ลบ' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                            </div>
                        </td>
                        <td class="text-center align-middle d-none d-xl-table-cell">${start_no + i}</td>
                        <td class="align-middle">${deviceCode}</td>
                        <td class="align-middle d-none d-lg-table-cell">${deptName}</td>
                        <td class="align-middle">${locationUse}</td>
                        <td class="text-center align-middle d-none d-md-table-cell">${rangeDisplay}</td>
                        <td class="text-start align-middle d-none d-md-table-cell">
                            ${responsibleDisplay}
                        </td>
                        <td class="text-center align-middle d-none d-xl-table-cell">${uncertaintyDisplay}</td>

                    </tr>`;
            });
        } else {
            html = `
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-thermometer-empty fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0 fw-bold">ไม่พบรายการเครื่องวัดอุณหภูมิในระบบ</p>
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

    // ปุ่มล้างค่าตัวกรอง
    $('#btn_clear_filter').on('click', function() {
        $('#searchFilterForm')[0].reset();
        $('#searchFilterForm select').val('').trigger('change');
        searchPage(1);
    });

    // ฟังก์ชัน Pagination คงเดิมตาม equipment_list
    function setupPagination(totalPages, currentPage) {
        const total = parseInt(totalPages);
        const current = parseInt(currentPage);
        $('#pagination-list').twbsPagination('destroy');
        if (total <= 0) return;
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
                    if (page !== current) searchPage(page);
                }
            });
        }, 50);
    }
</script>
<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>