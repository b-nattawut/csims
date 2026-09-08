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

$title = "รายการสารเคมี";

ob_start();
?>

<style>
    .js-edit-chemical-btn,
    .js-delete-chemical-btn {
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
                            <button class="btn <?= $canManage ? 'btn-success' : 'btn-secondary' ?> btn-sm px-4 fw-bold shadow-sm text-nowrap w-100 <?= $canManage ? 'js-add-chemical-btn' : '' ?>"
                                style="height: 31px;"
                                type="button" <?= $canManage ? 'data-bs-toggle="modal" data-bs-target="#addEditChemicalModal"' : 'disabled' ?>
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
                            <label for="filter_chemical_name" class="form-label text-muted small mb-1">ชื่อสารเคมี</label>
                            <input type="text" id="filter_chemical_name" name="filter_chemical_name" class="form-control form-control-sm" placeholder="ระบุชื่อสารเคมี...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 <?= ($user_role !== 'admin') ? 'col-lg-3 col-xl-3' : 'col-lg-4 col-xl-4 ' ?>">
                            <label for="filter_chemical_brand" class="form-label text-muted small mb-1">ยี่ห้อ/ผู้ผลิต</label>
                            <input type="text" id="filter_chemical_brand" name="filter_chemical_brand" class="form-control form-control-sm" placeholder="ระบุยี่ห้อหรือผู้ผลิต...">
                        </div>


                        <div class="col-12 col-sm-6 col-md-6 <?= ($user_role !== 'admin') ? 'col-lg-3 col-xl-3' : 'col-lg-4 col-xl-4 ' ?>">
                            <label for="filter_chemical_detail" class="form-label text-muted small mb-1">รายละเอียด</label>
                            <input type="text" id="filter_chemical_detail" name="filter_chemical_detail" class="form-control form-control-sm" placeholder="ระบุรายละเอียดสารเคมี...">
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
                    <th style="min-width: 180px;">ชื่อสารเคมี</th>
                    <th class="d-none d-md-table-cell" style="min-width: 150px;">หน่วยงาน</th>
                    <th style="min-width: 150px;">ยี่ห้อ/ผู้ผลิต</th>
                    <th class="d-none d-md-table-cell" style="min-width: 100px;">หน่วยนับหลัก</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 220px;">รายละเอียด</th>
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
<div class="modal fade" id="addEditChemicalModal" aria-labelledby="addEditChemicalModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addEditChemicalModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> <span id="textModal">เพิ่มรายการ</span>
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" id="chemicalMasterForm" action="./api/Master_chemical_list/save.php" novalidate>
                    <input type="hidden" id="edit_id" name="id">

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header"><i class="fas fa-info-circle me-1"></i> ข้อมูลทั่วไปของสารเคมี</legend>
                        <div class="row g-3 justify-content-start">

                            <div class="col-md-6 <?= ($user_role !== 'admin') ? 'd-none' : '' ?>">
                                <label for="department_id" class="form-label fw-bold">หน่วยงาน <span class="text-danger">*</span></label>
                                <select id="department_id" name="department_id" class="form-select select2-dept" <?= ($user_role === 'admin') ? 'required' : '' ?> data-placeholder="-- ค้นหาหน่วยงาน --">
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาเลือกหน่วยงาน</div>
                            </div>

                            <div class="col-md-6">
                                <label for="chemical_name" class="form-label fw-bold">ชื่อสารเคมี <span class="text-danger">*</span></label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="chemical_name"
                                        name="chemical_name"
                                        class="form-control"
                                        required
                                        autocomplete="off"
                                        maxlength="255"
                                        placeholder="เช่น Ninhydrin, Cyanoacrylate, Silver Nitrate">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="chemical_name"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุชื่อสารเคมี</div>
                            </div>

                            <div class="<?= ($user_role !== 'admin') ? 'col-md-6' : 'col-md-12' ?>">
                                <label for="chemical_brand" class="form-label fw-bold">ยี่ห้อ / ผู้ผลิต</label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="chemical_brand"
                                        name="chemical_brand"
                                        class="form-control"
                                        autocomplete="off"
                                        maxlength="255"
                                        placeholder="เช่น Merck, Sigma-Aldrich, Fisher Scientific">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="chemical_brand"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label for="unit_id" class="form-label fw-bold">หน่วยนับหลัก <span class="text-danger">*</span></label>
                                <select id="unit_id" name="unit_id" class="form-select select2-units" required
                                    data-placeholder="-- ค้นหาหน่วยนับ --">
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาเลือกหน่วยนับหลัก</div>
                                <div class="form-text mt-2">
                                    <div class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <b>คำแนะนำ:</b> โปรดเลือกหน่วยที่เล็กที่สุดในการเบิกจ่าย (เช่น ml แทน L) เพื่อความแม่นยำในการคำนวณสต็อก
                                    </div>
                                    <div class="text-danger fw-bold mt-1">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        ข้อควรระวัง: หากมีการเพิ่มข้อมูล Lot หรือเริ่มใช้งานในคลังแล้ว ระบบจะไม่อนุญาตให้แก้ไขหน่วยนับนี้ เพื่อป้องกันความผิดพลาดของยอดคงเหลือ
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label for="chemical_detail" class="form-label fw-bold">รายละเอียด</label>
                                <div class="input-group input-group-seamless">
                                    <textarea
                                        id="chemical_detail"
                                        name="chemical_detail"
                                        class="form-control"
                                        rows="4"
                                        placeholder="ระบุรายละเอียดเพิ่มเติม เช่น ความเข้มข้น, เกรดสารเคมี (AR/CP Grade)"></textarea>
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="chemical_detail"
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
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="chemicalMasterForm">
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
<div class="modal fade" id="viewChemicalDetailModal" tabindex="-1" aria-labelledby="viewChemicalDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-width: 90%;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold" id="viewChemicalDetailModalLabel">
                    <i class="fas fa-search me-2"></i> รายละเอียดสารเคมี
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-12">
                        <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                            <i class="fas fa-info-circle me-1"></i> ข้อมูลสารเคมี
                        </h6>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="text-muted small d-block">หน่วยงาน</label>
                                <span id="view_department_name" class="fw-bold">-</span>
                            </div>

                            <div class="col-12 col-lg-6 mb-3">
                                <label class="text-muted small d-block">ชื่อสารเคมี</label>
                                <span id="view_chemical_name" class="fw-bold">-</span>
                            </div>
                            <div class="col-6 col-md-6 col-lg-3 mb-3">
                                <label class="text-muted small d-block">ยี่ห้อ / ผู้ผลิต</label>
                                <span id="view_chemical_brand" class="fw-bold">-</span>
                            </div>

                            <div class="col-6 col-md-6 col-lg-3 mb-3">
                                <label class="text-muted small d-block">หน่วยนับหลัก</label>
                                <span id="view_unit_text" class="fw-bold">-</span>
                            </div>

                            <div class="col-12 mb-3">
                                <label class="text-muted small d-block mb-1">รายละเอียด</label>
                                <div class="px-3 py-1 bg-light rounded border mt-1" style="white-space: pre-wrap; display: block;">
                                    <span id="view_chemical_detail" class="text-secondary" style="display: block; line-height: 1.4;">
                                        -
                                    </span>
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

    let unitMapping = {};

    $(document).ready(function() {

        if ($('.select2-filter').length) {
            $('.select2-filter').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true
            }).next('.select2-container').addClass('select2-sm-custom');
        }

        if ($('.select2-dept').length) {
            $('.select2-dept').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                dropdownParent: $('#addEditChemicalModal')
            });
        }

        if ($('#filter_department_id').length) {
            loadDepartments();
        }

        initChemicalUnits().then(() => {
            searchPage(1);
        });
    });

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

                    // ยัดข้อมูลลงทั้งใน Filter และ Modal
                    $('#filter_department_id, #department_id').html(options);

                    // อัปเดต UI ของ Select2
                    $('#filter_department_id, #department_id').trigger('change');
                }
            },
            error: function() {
                console.error("ไม่สามารถดึงข้อมูลหน่วยงานได้");
            }
        });
    }

    // 3. ฟังก์ชันโหลดหน่วยนับจาก API
    function initChemicalUnits() {
        return $.ajax({
            url: './api/get_chemical_units.php',
            method: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let $select = $('#unit_id');
                    $select.empty().append('<option value=""></option>');

                    let groups = {};
                    unitMapping = {}; // เคลียร์ mapping เก่า

                    res.data.forEach(unit => {
                        // 1. จัดกลุ่มหน่วย
                        if (!groups[unit.unit_group]) groups[unit.unit_group] = [];
                        groups[unit.unit_group].push(unit);

                        // 2. ตัดสินใจว่าจะโชว์อะไรในวงเล็บ
                        // ถ้าเป็นกลุ่มบรรจุภัณฑ์/อื่นๆ ให้ใช้ชื่ออังกฤษ (Bottle, Kit)
                        // ถ้าเป็นกลุ่มของแข็ง/ของเหลว ให้ใช้ตัวย่อ (ml, kg)
                        let displaySymbol = unit.unit_symbol;
                        if (unit.unit_group.includes('บรรจุภัณฑ์') || unit.unit_group.includes('Packaging')) {
                            displaySymbol = unit.unit_name_en; // เช่น Bottle, Kit, Vial
                        }

                        // 3. สร้าง Mapping และเก็บค่าไว้แสดงผล
                        unitMapping[unit.id] = `${unit.unit_name_th} (${displaySymbol})`;
                    });

                    // วาด optgroup และ option
                    for (let groupName in groups) {
                        let $optgroup = $('<optgroup>').attr('label', groupName);
                        groups[groupName].forEach(unit => {
                            let displaySymbol = unit.unit_symbol;
                            if (unit.unit_group.includes('บรรจุภัณฑ์') || unit.unit_group.includes('Packaging')) {
                                displaySymbol = unit.unit_name_en;
                            }
                            $optgroup.append(`<option value="${unit.id}">${unit.unit_name_th} (${displaySymbol})</option>`);
                        });
                        $select.append($optgroup);
                    }

                    // 4. หลังจากวาดเสร็จ ค่อย Initialize Select2
                    $('.select2-units').select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $('#addEditChemicalModal'),
                        width: '100%',
                        allowClear: true
                    });
                }
            }
        });
    }

    // 1. ฟังก์ชันสำหรับดึงข้อมูลและเปิด Modal ดูรายละเอียดสารเคมี
    function viewChemicalDetail(id) {
        $.ajax({
            url: './api/Master_chemical_list/getDataByID.php',
            method: 'GET',
            data: {
                id: id
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let d = res.data;

                    // Mapping ข้อมูลลง Modal ดูรายละเอียด (View Modal)
                    $('#view_department_name').text(d.department_name || '-');
                    $('#view_chemical_name').text(d.chemical_name || '-');
                    $('#view_chemical_brand').text(d.chemical_brand || '-');
                    $('#view_unit_text').text(d.unit_display || '-');
                    $('#view_chemical_detail').text(d.chemical_detail || 'ไม่มีรายละเอียดเพิ่มเติม');

                    // Mapping ข้อมูล Metadata (Audit Log)
                    $('#view_create_by').text(d.fullname_create || '-');
                    $('#view_create_date').text(d.createdate || '-');
                    $('#view_update_by').text(d.fullname_update || '-');
                    $('#view_update_date').text(d.updatedate || '');

                    // เปิด Modal
                    $('#viewChemicalDetailModal').modal('show');
                } else {
                    Swal.fire('ผิดพลาด', 'ไม่สามารถดึงข้อมูลได้', 'error');
                }
            },
            error: function() {
                Swal.fire('ผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
            }
        });
    }

    // 2. ดักจับการคลิกที่แถวของตาราง (Clickable Row)
    $(document).on('click', '.clickable-row', function(e) {
        // ป้องกันไม่ให้ทำงานเมื่อคลิกโดนปุ่ม จัดการ (Edit/Delete) ในแถวนั้น
        if ($(e.target).closest('.js-edit-chemical-btn, .js-delete-chemical-btn, button').length) {
            return;
        }

        const id = $(this).data('id');
        if (id) {
            viewChemicalDetail(id);
        }
    });

    // เพิ่ม Event เมื่อกดปุ่ม "เพิ่มรายการ"
    $(document).on('click', '.js-add-chemical-btn', function() {

        // 1. Reset Form
        $('#chemicalMasterForm')[0].reset();

        $('#unit_id').val('').trigger('change');
        $('#department_id').val('').trigger('change');

        // 2. เคลียร์ ID (เพื่อให้รู้ว่าเป็น Add Mode)
        $('#edit_id').val('');

        // 3. ปลดล็อกฟิลด์หน่วยนับ (เผื่อโดน disabled มาจากโหมดแก้ไข)
        $('#unit_id').prop('disabled', false);

        // 4. เปลี่ยนหัวข้อกลับเป็น "เพิ่มรายการ"
        $('#textModal').text('เพิ่มรายการ');
        $('#addEditChemicalModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายการ');

        // 5. ลบ Class Validated ออก
        $('#chemicalMasterForm').removeClass('was-validated');
    });

    $(document).on('click', '.js-edit-chemical-btn', function() {
        let dataId = $(this).data('id');
        $.ajax({
            url: './api/Master_chemical_list/getDataByID.php',
            type: 'GET',
            data: {
                id: dataId
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let data = res.data;

                    // 1. เปลี่ยน Text หัวข้อ Modal
                    $('#textModal').text('แก้ไขรายการสารเคมี');
                    $('#addEditChemicalModalLabel').html('<i class="fas fa-edit fa-lg me-2"></i> แก้ไขรายการสารเคมี');

                    // 2. Map ข้อมูลลง Form (ตาม 3 ฟิลด์หลัก)
                    $('#edit_id').val(data.id);
                    $('#department_id').val(data.department_id).trigger('change');
                    $('#chemical_name').val(data.chemical_name);
                    $('#chemical_brand').val(data.chemical_brand);
                    $('#unit_id').val(data.unit_id).trigger('change');
                    $('#chemical_detail').val(data.chemical_detail);

                    // 2. ตรวจสอบว่ามีการใช้งานหรือยัง
                    if (data.used_count > 0) {
                        // ถ้าถูกใช้แล้ว: ล็อคไม่ให้เปลี่ยนหน่วยนับ และอาจจะเปลี่ยนสีเพื่อให้ User รู้
                        $('#unit_id').prop('disabled', true).trigger('change');
                        $('#unit_id').attr('title', 'ไม่สามารถแก้ไขได้เนื่องจากมีการใช้งานในคลังแล้ว');
                    } else {
                        // ถ้ายังไม่ถูกใช้: เปิดให้แก้ไขได้ปกติ
                        $('#unit_id').prop('disabled', false).trigger('change');
                        $('#unit_id').removeAttr('title');
                    }

                    // 3. แสดง Modal
                    $('#addEditChemicalModal').modal('show');
                }
            },
            error: function() {
                Swal.fire('Error', 'ไม่สามารถดึงข้อมูลได้', 'error');
            }
        });
    });


    $(document).on('click', '.js-delete-chemical-btn', function(e) {
        e.preventDefault();
        let dataId = $(this).data('id');
        let chemicalName = $(this).data('name');
        let chemicalBrand = $(this).data('brand');
        let deptName = $(this).data('dept');

        if (!dataId) {
            Swal.fire('Error', 'ไม่พบ ID ของรายการ', 'error');
            return;
        }

        Swal.fire({
            title: 'ยืนยันการลบ ?',
            html: `
                <div class="text-center mb-3">คุณต้องการลบรายการสารเคมีนี้ใช่หรือไม่?</div>
                <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">ชื่อสารเคมี:</span><br>
                        <b class="ps-2 text-danger">${chemicalName}</b>
                    </div>
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">ยี่ห้อ/ผู้ผลิต:</span><br>
                        <b class="ps-2 text-danger">${chemicalBrand}</b>
                    </div>
                    <div>
                        <span class="text-secondary small">หน่วยงาน:</span><br>
                        <b class="ps-2 text-danger">${deptName}</b> 
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
                    url: './api/Master_chemical_list/delete.php',
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
                    Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'ลบข้อมูลสำเร็จ!',
                    text: 'รายการสารเคมีถูกลบเรียบร้อยแล้ว',
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 1500,
                    returnFocus: false
                }).then(() => {
                    searchPage(1); // โหลดตารางใหม่
                });
            }
        });
    });

    // ฟังก์ชันค้นหา Input ตัวแรกที่ Invalid (สำหรับ Scroll และ Focus)
    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    // ฟังก์ชันบันทึกข้อมูล Master สารเคมี
    $('#chemicalMasterForm').on('submit', function(e) {
        e.preventDefault();

        $('#unit_id').prop('disabled', false); // ปลดล็อคแป๊บเดียวเพื่อให้ค่าถูกส่งไป Backend

        const form = this;

        // 1. Validate Form เบื้องต้น (Client-side)
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');

            const invalidElement = findFirstInvalidInput(form);
            if (invalidElement) {
                let $invalidElement = $(invalidElement);
                let isSelect2 = $invalidElement.hasClass('select2-hidden-accessible');

                // เลื่อนหน้าจอไปหาจุดที่กรอกผิด
                const elementToScroll = isSelect2 ?
                    $invalidElement.next('.select2-container')[0] :
                    invalidElement;

                if (elementToScroll) {
                    elementToScroll.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }

                // แจ้งเตือนด้วย Swal
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบถ้วน',
                    text: 'กรุณากรอกข้อมูลในช่องที่จำเป็น (เครื่องหมาย *) ให้ครบถ้วน',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#3085d6',
                    returnFocus: false
                }).then((result) => {
                    if (isSelect2) {
                        $invalidElement.select2('open');
                    } else {
                        invalidElement.focus();
                    }
                });
            }
            return;
        }

        // 2. ส่งข้อมูลไปยัง Backend ผ่าน AJAX
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function() {
                $('#btn_save_all').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> กำลังบันทึก...');
            },
            success: function(response) {
                if (response.status == "success") {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: 'ข้อมูลสารเคมีถูกบันทึกเรียบร้อยแล้ว',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // ปิด Modal
                        $('#addEditChemicalModal').modal('hide');
                        if (typeof searchPage === "function") {
                            searchPage(1);
                        } else {
                            window.location.reload(); // fallback กรณีไม่มีฟังก์ชัน refresh เฉพาะจุด
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
                // คืนค่าปุ่มบันทึก
                $('#btn_save_all').prop('disabled', false).html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล');
            }
        });
    });

    $(document).on('click', '#btn_export_excel', function(e) {
        e.preventDefault(); // กันไว้ก่อนเผื่อปุ่มอยู่ใน Form submit

        // ดึงค่าจากฟอร์มทั้งหมดมาทำเป็น Query String
        const formData = $('#searchFilterForm').serialize();

        // ยิงไปที่ API
        window.location.href = './api/Master_chemical_list/exportExcel.php?' + formData;
    });

    function searchPage(page) {
        // ดึงค่าจาก Filter Form (ชื่อสารเคมี, ยี่ห้อ, รายละเอียด)
        const formData = $('#searchFilterForm').serializeArray();
        formData.push({
            name: 'page',
            value: page
        });

        $.ajax({
            url: './api/Master_chemical_list/searchData.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status == 'success') {
                    // 1. วาดตารางข้อมูล (ส่ง data และ offset ไปเพื่อรันเลขลำดับ)
                    renderTable(response.data, response.offset);

                    // 2. แสดงจำนวนรายการทั้งหมด
                    $('#count_display').text(response.count);

                    // 3. จัดการระบบ Pagination
                    const total = parseInt(response.totalPages);
                    const current = parseInt(response.currentPage);

                    if (total > 0) {
                        setupPagination(total, current);
                    } else {
                        $('#pagination-list').empty(); // เคลียร์ปุ่มหน้ากรณีไม่มีข้อมูล
                    }
                }
            },
            error: function(xhr) {
                console.error("Search Error:", xhr.responseText);
                $('#table_body').html('<tr><td colspan="6" class="text-center text-danger">เกิดข้อผิดพลาดในการดึงข้อมูล</td></tr>');
            }
        });
    }

    function renderTable(data, offset) {
        let html = '';
        let start_no = (isNaN(parseInt(offset))) ? 1 : parseInt(offset) + 1;

        if (data && data.length > 0) {
            data.forEach(function(row, i) {

                // ตรวจสอบค่าว่างสำหรับฟิลด์สารเคมีก่อนแสดงผล
                let department_name = row.department_name || '-';
                let chemical_name = row.chemical_name || '-';
                let chemical_brand = row.chemical_brand || '-';
                let chemical_detail = row.chemical_detail || '-';

                let unitShow = row.unit_display || '-';

                html += `
                <tr class="clickable-row cursor-pointer" data-id="${row.id}" title="ดูข้อมูล">
                    <td class="text-center align-middle">
                        <div class="d-flex justify-content-center gap-2">
                            <i class="fas fa-pen-to-square fa-lg text-warning ${window._canManage ? 'js-edit-chemical-btn cursor-pointer' : ''}" 
                                data-id="${row.id}" 
                                title="${window._canManage ? 'แก้ไข' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>

                            <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-chemical-btn cursor-pointer' : ''}" 
                                data-id="${row.id}" 
                                data-name="${chemical_name}" 
                                data-brand="${chemical_brand}"
                                data-dept="${department_name || 'ไม่ระบุ'}" 
                                title="${window._canManage ? 'ลบ' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                        </div>
                    </td>
                    <td class="text-center align-middle d-none d-xl-table-cell">${start_no + i}</td>
                    <td class="align-middle">${chemical_name}</td>
                    <td class="align-middle d-none d-md-table-cell text-start">${department_name}</td>
                    <td class="align-middle">${chemical_brand}</td>
                    <td class="text-center align-middle d-none d-md-table-cell">${unitShow}</td>
                    <td class="text-start align-middle d-none d-lg-table-cell">
                        <div class="text-truncate" style="max-width: 250px;" title="${chemical_detail}">
                            ${chemical_detail}
                        </div>
                    </td>
                </tr>
            `;
            });
        } else {
            html = `
            <tr>
                <td colspan="7" class="text-center py-5">
                    <div class="text-muted">
                        <i class="fas fa-flask fa-3x mb-3 opacity-50"></i>
                        <p class="mb-0 fw-bold">ไม่พบรายการสารเคมีในระบบ</p>
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