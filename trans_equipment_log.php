<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require_once './includes/check_permission.php';

$canManage = hasPermission($pdo, 'maintenance.create');
$current_user_id = $_SESSION['user_id'];

// ดึงข้อมูล Role และ Department ของ User ที่ Login
$stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
$stmtRole->execute([$current_user_id]);
$userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

$user_role = strtolower($userInfo['role'] ?? '');
$user_dept_id = $userInfo['department_id'] ?? null;

$title = "การซ่อมบำรุง/สอบเทียบเครื่องมือ";

ob_start();
?>

<style>
    .js-add-log-btn {
        padding: 25px;
        /* ขยายพื้นที่จิ้มออกไปรอบๆ */
        margin: -25px;
        /* ดึงขอบกลับมาเพื่อไม่ให้กระทบตำแหน่งในตาราง */
        cursor: pointer;
        touch-action: manipulation;
        /* ป้องกันการซูมเวลาเผลอ Double Tap */
    }

    .js-edit-log-btn,
    .js-delete-log-btn {
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

                        <?php if ($user_role === 'admin'): ?>
                            <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                                <label for="filter_department_id" class="form-label text-muted small mb-1">หน่วยงาน</label>
                                <select id="filter_department_id" name="filter_department_id" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                    <option value=""></option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_equipment_category" class="form-label text-muted small mb-1">ประเภทเครื่องมือ</label>
                            <select id="filter_equipment_category" name="filter_equipment_category"
                                class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                <option value=""></option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_equipment_asset_no" class="form-label text-muted small mb-1">เลขครุภัณฑ์</label>
                            <input type="text" id="filter_equipment_asset_no" name="filter_equipment_asset_no"
                                class="form-control form-control-sm" placeholder="ระบุเลขครุภัณฑ์...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_equipment_name" class="form-label text-muted small mb-1">ชื่อเครื่องมือ</label>
                            <input type="text" id="filter_equipment_name" name="filter_equipment_name"
                                class="form-control form-control-sm" placeholder="ระบุชื่อเครื่องมือ...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_equipment_brand_model" class="form-label text-muted small mb-1">ยี่ห้อ / รุ่น</label>
                            <input type="text" id="filter_equipment_brand_model" name="filter_equipment_brand_model"
                                class="form-control form-control-sm" placeholder="เช่น Nikon D7500, Alumina...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_equipment_serial" class="form-label text-muted small mb-1">Serial No. (S/N)</label>
                            <input type="text" id="filter_equipment_serial" name="filter_equipment_serial"
                                class="form-control form-control-sm"
                                oninput="this.value = this.value.replace(/\s/g, '')"
                                placeholder="ระบุเลข Serial...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_responsible_id" class="form-label text-muted small mb-1">ผู้ดูแล</label>
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

<div class="table-responsive">
    <div class="table-wrapper-focus">
        <table class="table table-bordered table-hover table-custom table-striped align-middle mb-0">
            <thead style="font-size: 14px;">
                <tr class="text-nowrap text-center">
                    <th style="min-width: 120px;">Action</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 50px;">ลำดับ</th>
                    <th style="min-width: 150px;">เลขครุภัณฑ์</th>
                    <th class="d-none d-md-table-cell" style="min-width: 150px;">หน่วยงาน</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 150px;">ประเภทเครื่องมือ</th>
                    <th style="min-width: 180px;">ชื่อเครื่องมือ</th>
                    <th class="d-none d-md-table-cell" style="min-width: 150px;">ยี่ห้อ / รุ่น</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 150px;">Serial No. (S/N)</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 180px;">ผู้ดูแล</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 110px;">วันที่ซ่อมล่าสุด</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 140px;">การซ่อมล่าสุด</th>
                    <th class="d-none d-md-table-cell" style="min-width: 100px;">ส่งออกรายงาน</th>
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

<!-- Modal สำหรับเพิ่มรายการสอบเทียบใหม่ / แก้ไขรายการเดิม -->
<div class="modal fade" id="addLogModal" aria-labelledby="addLogModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addLogModalLabel">
                    <i class="fas fa-tools fa-lg me-2"></i> <span id="textModal">บันทึกประวัติซ่อมบำรุง/สอบเทียบ</span>
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">

                <div class="card border-0 shadow-sm mb-4 border-start border-primary border-4 rounded-3">
                    <div class="card-body bg-white">
                        <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                            <i class="fas fa-toolbox fa-lg text-primary me-2"></i>
                            <h6 class="mb-0 fw-bold text-primary">ข้อมูลอ้างอิงเครื่องมือ</h6>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-lg-12">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">หน่วยงาน</span>
                                <span class="fw-bold" id="display_log_department">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">เลขครุภัณฑ์</span>
                                <span class="fw-bold" id="display_log_asset_no">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ประเภทเครื่องมือ</span>
                                <span class="fw-bold" id="display_log_category">-</span>
                            </div>
                            <div class="col-12">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ชื่อเครื่องมือ</span>
                                <span class="fw-bold" id="display_log_tool_name">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ยี่ห้อ (Brand)</span>
                                <span class="fw-bold" id="display_log_brand">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">รุ่น (Model)</span>
                                <span class="fw-bold" id="display_log_model">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">Serial No. (S/N)</span>
                                <span class="fw-bold" id="display_log_serial_no">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ผู้ดูแลเครื่องมือ</span>
                                <span class="fw-bold" id="display_log_responsible">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="post" id="equipmentLogForm" action="./api/Transaction_equipment_log/save.php" novalidate>
                    <input type="hidden" id="equipment_id" name="equipment_id">
                    <input type="hidden" id="log_id" name="id">

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header"><i class="fas fa-info-circle me-1"></i> รายละเอียดการซ่อมบำรุง / สอบเทียบ</legend>
                        <div class="row g-3 justify-content-start">

                            <div class="col-12 col-lg-3">
                                <label class="form-label">วัน เดือน ปี <span class="text-danger">*</span></label>
                                <input type="date" id="log_date" name="log_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">
                                    กรุณาระบุ วัน เดือน ปี
                                </div>
                            </div>
                            <div class="col-12 col-lg-9">
                                <label class="form-label">การซ่อมบำรุง / การสอบเทียบ <span class="text-danger">*</span></label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="maintenance_detail"
                                        name="maintenance_detail"
                                        class="form-control"
                                        placeholder="เช่น สอบเทียบประจำปี, เปลี่ยนแบตเตอรี่, ซ่อมแผงวงจร"
                                        required>
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="maintenance_detail"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุรายละเอียดการซ่อมบำรุง / การสอบเทียบ</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">บริษัทผู้รับผิดชอบ</label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="company_name"
                                        name="company_name"
                                        class="form-control"
                                        placeholder="เช่น บริษัท เอบีซี จำกัด, ศูนย์บริการ Nikon">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="company_name"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">เจ้าหน้าที่ผู้รับผิดชอบ</label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="officer_name"
                                        name="officer_name"
                                        class="form-control"
                                        placeholder="ระบุชื่อเจ้าหน้าที่ผู้ดำเนินการ">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="officer_name"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">หมายเหตุ</label>
                                <div class="input-group input-group-seamless">
                                    <textarea
                                        id="remark"
                                        name="remark"
                                        class="form-control"
                                        rows="2"
                                        placeholder="ระบุหมายเหตุเพิ่มเติม (ถ้ามี) เช่น ส่งซ่อมด่วน, รออะไหล่ 7 วัน"></textarea>
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
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="equipmentLogForm">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal สำหรับดู list รายการประวัติการซ่อมบำรุง / สอบเทียบทั้งหมดของเครื่องมือนั้น ๆ  -->
<div class="modal fade" id="viewHistoryModal" tabindex="-1" aria-labelledby="viewHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width: 90%;">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="viewHistoryModalLabel">
                    <i class="fas fa-history me-2"></i> ประวัติการซ่อมบำรุง / สอบเทียบเครื่องมือ
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="card border-0 shadow-sm mb-4 border-start border-primary border-4 rounded-3">
                    <div class="card-body bg-white">
                        <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                            <i class="fas fa-toolbox fa-lg text-primary me-2"></i>
                            <h6 class="mb-0 fw-bold text-primary">ข้อมูลอ้างอิงเครื่องมือ</h6>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-lg-12">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">หน่วยงาน</span>
                                <span class="fw-bold" id="hist_department">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">เลขครุภัณฑ์</span>
                                <span class="fw-bold" id="hist_asset_no">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ประเภทเครื่องมือ</span>
                                <span class="fw-bold" id="hist_category">-</span>
                            </div>
                            <div class="col-12">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ชื่อเครื่องมือ</span>
                                <span class="fw-bold" id="hist_tool_name">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ยี่ห้อ (Brand)</span>
                                <span class="fw-bold" id="hist_brand">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">รุ่น (Model)</span>
                                <span class="fw-bold" id="hist_model">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">Serial No. (S/N)</span>
                                <span class="fw-bold" id="hist_serial_no">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ผู้ดูแลเครื่องมือ</span>
                                <span class="fw-bold" id="hist_responsible">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border mb-3 bg-light">
                    <div class="card-body p-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-6">
                                <label for="filter_maint_year" class="form-label text-muted small mb-1 fw-bold"><i class="far fa-calendar me-1"></i> ปี (พ.ศ.)</label>
                                <select id="filter_maint_year" class="form-select form-select-sm">
                                    <option value="">-- แสดงทั้งหมด --</option>
                                    <?php
                                    $currentYear = date('Y') + 543;
                                    for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                        echo "<option value=\"$y\">$y</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="filter_maint_month" class="form-label text-muted small mb-1 fw-bold"><i class="far fa-calendar-alt me-1"></i> เดือน</label>
                                <select id="filter_maint_month" class="form-select form-select-sm">
                                    <option value="">-- แสดงทั้งหมด --</option>
                                    <option value="1">มกราคม</option>
                                    <option value="2">กุมภาพันธ์</option>
                                    <option value="3">มีนาคม</option>
                                    <option value="4">เมษายน</option>
                                    <option value="5">พฤษภาคม</option>
                                    <option value="6">มิถุนายน</option>
                                    <option value="7">กรกฎาคม</option>
                                    <option value="8">สิงหาคม</option>
                                    <option value="9">กันยายน</option>
                                    <option value="10">ตุลาคม</option>
                                    <option value="11">พฤศจิกายน</option>
                                    <option value="12">ธันวาคม</option>
                                </select>
                            </div>

                            <div class="col-12 mt-4 pt-3 border-top d-flex flex-wrap gap-2 justify-content-center">
                                <button type="button" class="btn btn-warning btn-sm text-dark fw-bold px-4 shadow-sm flex-fill flex-md-grow-0" id="btn_clear_maint_filter">
                                    <i class="fas fa-undo me-2"></i> ล้างค่า
                                </button>
                                <button type="button" class="btn btn-primary btn-sm text-white fw-bold px-4 shadow-sm flex-fill flex-md-grow-0" id="btn_search_maint">
                                    <i class="fas fa-search me-2"></i> ดูข้อมูล
                                </button>
                                <button type="button" class="btn btn-sm fw-bold px-4 shadow-sm flex-fill flex-md-grow-0 text-white" style="background-color: #6f42c1;" id="btn_export_maint_pdf">
                                    <i class="fas fa-file-pdf me-2"></i> Export PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header py-3 px-4">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-list fa-lg me-2"></i> รายการบันทึกการซ่อมบำรุง / สอบเทียบ</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle text-center mb-0" style="font-size: 0.95rem;">
                                <thead class="table-primary">
                                    <tr class="text-nowrap text-center">
                                        <th style="min-width: 100px;">Action</th>
                                        <th style="min-width: 120px;">วัน เดือน ปี</th>
                                        <th style="min-width: 250px;">การซ่อมบำรุง / การสอบเทียบ</th>
                                        <th class="d-none d-lg-table-cell" style="min-width: 150px;">บริษัทผู้รับผิดชอบ</th>
                                        <th class="d-none d-lg-table-cell" style="min-width: 150px;">เจ้าหน้าที่</th>
                                        <th class="d-none d-lg-table-cell" style="min-width: 150px;">หมายเหตุ</th>
                                    </tr>
                                </thead>
                                <tbody id="log_history_body">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top shadow-sm">
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
    let currentEquipId = null;

    $(document).ready(function() {
        searchPage(1);

        loadEquipmentCategories();
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

        $('#filter_maint_year, #filter_maint_month').on('change', function() {
            handleMonthFilter();
        });

        // เรียกครั้งแรก (Initial)
        handleMonthFilter();
    });

    function handleMonthFilter() {
        const $yearSelect = $('#filter_maint_year');
        const $monthSelect = $('#filter_maint_month');

        const selectedYear = parseInt($yearSelect.val());
        const selectedMonth = parseInt($monthSelect.val()) || 0;

        const now = new Date();
        const currentYearBE = now.getFullYear() + 543;
        const currentMonth = now.getMonth() + 1; // เดือนปัจจุบัน (1-12)

        $monthSelect.find('option').each(function() {
            const monthVal = parseInt($(this).val());
            if (!monthVal) return; // ข้ามตัวเลือก "-- แสดงทั้งหมด --"

            // ถ้าเลือกปีปัจจุบัน และเดือนในลิสต์ > เดือนปัจจุบัน
            const isFutureMonth = (selectedYear === currentYearBE && monthVal > currentMonth);

            if (isFutureMonth) {
                $(this).prop('disabled', true).hide();
            } else {
                $(this).prop('disabled', false).show();
            }
        });

        if (selectedYear === currentYearBE && selectedMonth > currentMonth) {
            // เคลียร์ค่าเดือนทิ้งทันที
            $monthSelect.val(currentMonth);
        }
    }


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
                    $('#filter_responsible_id').html(options).trigger('change');
                }
            }
        });
    }

    function loadEquipmentCategories() {
        $.ajax({
            url: './api/get_equipment_categories.php',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let options = '<option value=""></option>';
                    res.data.forEach(item => {
                        options += `<option value="${item.id}">${item.category_name}</option>`;
                    });

                    // ในหน้านี้มีเฉพาะช่อง Filter
                    $('#filter_equipment_category').html(options).trigger('change');
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

                    // นำ options ไปใส่ใน Select2 ของ Filter หน่วยงาน
                    $('#filter_department_id').html(options).trigger('change');
                }
            }
        });
    }

    function searchPage(page) {
        const formData = $('#searchFilterForm').serializeArray();
        formData.push({
            name: 'page',
            value: page
        });

        // รอเปลี่ยน API
        $.ajax({
            url: './api/Master_equipment_list/searchDataWithLog.php',
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
                }
            }
        });
    }

    function renderTable(data, offset) {
        let html = '';
        let start_no = (isNaN(parseInt(offset))) ? 1 : parseInt(offset) + 1;

        if (data && data.length > 0) {
            data.forEach(function(row, i) {

                // ตรวจสอบค่าว่างสำหรับฟิลด์ต่างๆ ก่อนแสดงผล
                let department = row.department_name || '<span class="text-muted small">ไม่ระบุ</span>';
                let tool_name = row.tool_name || '-';
                let asset_no = row.asset_no || '-';
                let brand = row.brand || '-';
                let model = row.model || '-';
                let serial_no = row.serial_no || '-';
                let category_name = row.category_name || '<span class="text-muted small">ไม่ระบุ</span>';
                let responsible = row.responsible_name ? row.responsible_name : '<span class="text-muted">ไม่ระบุ</span>';

                // เช็คว่ามีประวัติล่าสุดไหม ถ้าไม่มีให้ขึ้นว่า "ยังไม่มีประวัติ"
                let lastDate = row.last_log_date ? row.last_log_date : '<span class="text-muted small">ไม่มีประวัติ</span>';
                let lastDetail = row.last_log_detail ? row.last_log_detail : '<span>-</span>';

                // 1. เช็คสถานะข้อมูล
                let hasLog = !!row.last_log_date;

                // 2. กำหนด Style และ Class ตามเงื่อนไข (อิงตามหน้า Checklist)
                let btnStyle = hasLog ?
                    'background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 12px; white-space: nowrap;' :
                    'box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); font-size: 12px; white-space: nowrap;';

                let btnClass = hasLog ?
                    '' :
                    'btn-secondary';

                let printBtnHtml = `
                    <button type="button" 
                        class="btn ${btnClass} js-print-btn shadow-sm" 
                        style="${btnStyle}"
                        title="${hasLog ? 'พิมพ์ประวัติ F-CS-18' : 'ยังไม่มีประวัติให้พิมพ์'}" 
                        data-id="${row.id}" 
                        data-asset="${row.asset_no || '-'}" 
                        ${hasLog ? '' : 'disabled'}>
                        <i class="fas fa-file-pdf me-1"></i> Export PDF 
                    </button>`;

                html += `
                        <tr class="clickable-row cursor-pointer" data-id="${row.id}" data-category="${category_name}" data-department="${department}" data-resp="${row.responsible_name || 'ไม่ระบุ'}" title="ดูข้อมูล">
                            <td class="text-center align-middle">
                                <div class="d-flex align-items-center justify-content-center" style="min-height: 100%;">
                                    <button type="button" 
                                            class="btn btn-sm ${window._canManage ? 'btn-success' : 'btn-secondary'} py-1 px-2 ${window._canManage ? 'js-add-log-btn' : ''}"
                                            data-id="${row.id}" 
                                            data-asset="${asset_no}" 
                                            data-department="${department}"
                                            data-category="${category_name}"
                                            data-name="${tool_name}" 
                                            data-brand="${brand}" 
                                            data-model="${model}" 
                                            data-serial="${serial_no}"
                                            data-resp="${row.responsible_name || 'ไม่ระบุ'}"
                                            ${window._canManage ? 'title="ลงบันทึกการซ่อมบำรุง/สอบเทียบเครื่องมือ"' : 'title="คุณไม่มีสิทธิ์จัดการข้อมูล" disabled'}>
                                        <i class="fas fa-circle-info me-1"></i> <span style="font-size: 0.8rem; ">จัดการข้อมูล</span>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center align-middle d-none d-xl-table-cell">${start_no + i}</td>
                            <td class="align-middle">${asset_no}</td>
                            <td class="text-start align-middle d-none d-md-table-cell">${department}</td>
                            <td class="text-start align-middle d-none d-xl-table-cell">
                                ${category_name}
                            </td>
                            <td class="text-start align-middle">
                                <div class="fw-bold d-xl-none">${tool_name}</div>
                                <div class="d-none d-xl-block">${tool_name}</div>
                                <div class="d-xl-none text-muted small mt-1">
                                    ${category_name}
                                </div>
                            </td>
                            <td class="align-middle d-none d-md-table-cell">
                                <div class="fw-bold">${brand}</div>
                                <div class="text-muted small">${model}</div>
                            </td>
                            <td class="align-middle d-none d-lg-table-cell">${serial_no}</td>
                            <td class="align-middle d-none d-lg-table-cell">${responsible}</td>
                            <td class="text-center align-middle d-none d-xl-table-cell">${lastDate}</td>
                            <td class="align-middle d-none d-xl-table-cell">
                                <div class="text-truncate" style="max-width: 150px;" title="${lastDetail}">
                                    ${lastDetail}
                                </div>
                            </td>
                            <td class="text-center align-middle d-none d-md-table-cell">
                                ${printBtnHtml}
                            </td>
                        </tr>
                    `;
            });
        } else {
            html = `
                <tr>
                    <td colspan="12" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-tools fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0 fw-bold">ไม่พบรายการเครื่องมือในระบบ</p>
                            <small class="opacity-75">ลองเปลี่ยนคำค้นหา  หรือกดปุ่ม <b class="text-dark">" เพิ่มรายการ "</b> ในหน้าเครื่องมือ เพื่อสร้างข้อมูลใหม่</small>
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

    // ---------------------------------------------------------
    // ส่วนจัดการ Log (ซ่อมบำรุง / สอบเทียบ)
    // ---------------------------------------------------------

    // A. เมื่อกดปุ่ม "เพิ่มประวัติ" (หน้ารวม)
    $(document).on('click', '.js-add-log-btn', function() {
        $('#equipmentLogForm')[0].reset();
        $('#equipmentLogForm').removeClass('was-validated');
        $('#log_id').val('');

        // รับค่าจาก data-* attribute ของปุ่ม
        let equipId = $(this).data('id');
        let department = $(this).data('department');
        let assetNo = $(this).data('asset');
        let category = $(this).data('category');
        let toolName = $(this).data('name');
        let brand = $(this).data('brand');
        let model = $(this).data('model');
        let serial = $(this).data('serial');
        let respName = $(this).data('resp');

        // เอาไปใส่ใน Modal
        $('#equipment_id').val(equipId);
        $('#display_log_department').text(department || '-');
        $('#display_log_asset_no').text(assetNo);
        $('#display_log_category').html(category);
        $('#display_log_tool_name').text(toolName);
        $('#display_log_brand').text(brand);
        $('#display_log_model').text(model);
        $('#display_log_serial_no').text(serial);
        $('#display_log_responsible').text(respName);

        $('#textModal').text('บันทึกประวัติซ่อมบำรุง/สอบเทียบ');

        $('#addLogModal').modal('show');
    });

    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    // B. บันทึกข้อมูล Log
    $('#equipmentLogForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;

        // 1. ตรวจสอบความถูกต้องของ Form
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');

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
            url: $(this).attr('action'), // วิ่งไปหา ./api/Transaction_equipment_log/save.php ตามที่เขียนไว้ใน action ของ form
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function() {
                // เปลี่ยนสถานะปุ่มตอนกำลังโหลด
                $('#btn_save_all').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> กำลังบันทึก...');
            },
            success: function(res) {
                if (res.status == "success") {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: 'ข้อมูลถูกบันทึกเรียบร้อยแล้ว',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    $('#addLogModal').modal('hide');
                    // ถ้าเปิดหน้า History ทิ้งไว้อยู่ ให้โหลด History ใหม่ด้วย
                    if ($('#viewHistoryModal').is(':visible')) {
                        loadHistory($('#equipment_id').val());
                    }

                    searchPage(parseInt($('#pagination-list .active a').text()) || 1);
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', res.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", xhr.responseText);
                Swal.fire('Server Error', 'เกิดข้อผิดพลาดที่ระบบ กรุณาลองใหม่ หรือติดต่อผู้ดูแลระบบ', 'error');
            },
            complete: function() {
                // คืนค่าสถานะปุ่มกลับมาเหมือนเดิม
                $('#btn_save_all').prop('disabled', false).html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล');
            }
        });
    });

    // ดักจับการคลิกที่แถวเพื่อเปิดประวัติ (แทนปุ่มเดิม)
    $(document).on('click', '.clickable-row', function(e) {
        // ถ้าคลิกโดนปุ่ม "เพิ่มประวัติ" หรือ "พิมพ์" ให้หยุดทำงาน ไม่ต้องเปิด Modal ประวัติ
        if ($(e.target).closest('.js-add-log-btn, .js-print-btn').length) {
            return;
        }

        // --- เพิ่มการ Reset filter ---
        $('#filter_maint_month').val('');
        $('#filter_maint_year').val('');
        handleMonthFilter();

        // ดึงค่าจาก tr ที่คลิก
        let equipId = $(this).data('id');
        let department = $(this).data('department');
        let category = $(this).data('category');
        let respName = $(this).data('resp');

        // เซ็ตชื่อผู้ดูแลและโหลดประวัติ
        $('#hist_department').text(department || '-');
        $('#hist_responsible').text(respName);
        $('#hist_category').html(category);
        loadHistory(equipId);
    });

    // ฟังก์ชันโหลดข้อมูลประวัติมาแสดงในตาราง
    function loadHistory(equipId) {
        currentEquipId = equipId;

        // 1. ดึงค่าจาก Filter (เดือน/ปี)
        let month = $('#filter_maint_month').val();
        let year_th = $('#filter_maint_year').val();
        let year_en = year_th ? parseInt(year_th) - 543 : ''; // แปลงเป็น ค.ศ.

        $('#log_history_body').html('<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> กำลังโหลด...</td></tr>');

        $.ajax({
            url: './api/Transaction_equipment_log/getHistory.php', // <--- API ดึงประวัติ 
            type: 'GET',
            data: {
                equipment_id: equipId,
                month: month, // ส่งเดือนไปกรอง
                year: year_en // ส่งปี (ค.ศ.) ไปกรอง
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let d = res.equipment_info;
                    // เอาข้อมูล Master มาแสดงหัวตาราง
                    $('#hist_department').text(d.department_name || $('#hist_department').text());
                    $('#hist_tool_name').text(d.tool_name);
                    $('#hist_brand').text(d.brand || '-');
                    $('#hist_model').text(d.model || '-');
                    $('#hist_asset_no').text(d.asset_no || '-');
                    $('#hist_serial_no').text(d.serial_no || '-');

                    // วาดตาราง Log
                    let html = '';
                    if (res.logs && res.logs.length > 0) {
                        res.logs.forEach(log => {
                            html += `
                                    <tr>
                                        <td class="text-center align-middle">
                                            <div class="d-flex justify-content-center gap-2">
                                                <i class="fas fa-pen-to-square fa-lg text-warning ${window._canManage ? 'js-edit-log-btn cursor-pointer' : ''}" 
                                                data-log='${JSON.stringify(log)}' 
                                                title="${window._canManage ? 'แก้ไข' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>

                                                <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-log-btn cursor-pointer' : ''}" 
                                                data-id="${log.id}" 
                                                data-date="${log.log_date_show}" 
                                                data-detail="${log.maintenance_detail}" 
                                                data-company="${log.company_name || '-'}"
                                                title="${window._canManage ? 'ลบ' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">${log.log_date_show}</td>

                                        <td class="text-start align-middle">
                                            <div class="fw-bold d-lg-none">${log.maintenance_detail}</div>
                                            <div class="d-none d-lg-block">${log.maintenance_detail}</div>
                                            
                                            <div class="d-block d-lg-none mt-1 pt-1" style="border-top: 1px solid rgba(108, 117, 125, 0.2);">
                                                <div class="mb-1" style="font-size: 0.85rem;">
                                                    <span class="text-muted fw-medium">บริษัทผู้รับผิดชอบ:</span> 
                                                    <span class="text-dark">${log.company_name || '-'}</span>
                                                </div>
                                                <div class="mb-1" style="font-size: 0.85rem;">
                                                    <span class="text-muted fw-medium">เจ้าหน้าที่:</span> 
                                                    <span class="text-dark">${log.officer_name || '-'}</span>
                                                </div>
                                                ${log.remark ? `
                                                <div style="font-size: 0.8rem; line-height: 1.3;">
                                                    <span class="text-muted fw-medium">หมายเหตุ:</span>
                                                    <span class="text-secondary italic">${log.remark}</span>
                                                </div>` : ''}
                                            </div>
                                        </td>
                                        <td class="text-start align-middle d-none d-lg-table-cell">${log.company_name || '-'}</td>
                                        <td class="text-start align-middle d-none d-lg-table-cell">${log.officer_name || '-'}</td>
                                        <td class="text-start text-muted small d-none d-lg-table-cell">${log.remark || '-'}</td>
                                    </tr>
                                `;
                        });
                    } else {
                        if (month || year_th) {
                            // 1. มีการเลือก Filter แล้วไม่เจอ
                            html = `
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-calendar-minus fa-3x mb-3 opacity-50"></i>
                                            <p class="mb-1 fw-bold">ไม่พบประวัติการซ่อมบำรุงในช่วงเวลาที่เลือก</p>
                                            <div class="small opacity-75">
                                                เครื่องมือนี้ยังไม่มีบันทึกการซ่อมบำรุงหรือสอบเทียบใน <b class="text-dark">ปี / เดือน</b> ที่ท่านกำลังค้นหา <br>
                                                ลองปรับเปลี่ยนเงื่อนไขการค้นหา หรือเพิ่มรายการใหม่ได้ที่ปุ่ม
                                                <b class="mx-2"><i class="fas fa-circle-info me-1"></i>จัดการข้อมูล</b> ในตารางหลัก
                                            </div>
                                        </div>
                                    </td>
                                </tr>`;
                        } else {
                            // 2. ไม่มีประวัติเลยตั้งแต่ต้น
                            html = `
                                <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-tools fa-3x mb-3 opacity-50"></i>
                                        <p class="mb-1 fw-bold">ยังไม่มีประวัติการซ่อมบำรุง</p>
                                        <small class="opacity-75">เครื่องมือนี้ยังไม่มีข้อมูลการซ่อมบำรุงหรือสอบเทียบในระบบ<br>สามารถเพิ่มรายการใหม่ได้ผ่านปุ่ม <b class="mx-2"><i class="fas fa-circle-info me-1"></i>จัดการข้อมูล</b>  ในตารางหลัก</small>                                    </div>
                                </td>
                            </tr>`;
                        }
                    }
                    $('#log_history_body').html(html);
                    $('#viewHistoryModal').modal('show');
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    }

    // Event ปุ่มล้างค่าใน Modal ประวัติเครื่องมือ
    $('#btn_clear_maint_filter').on('click', function() {
        $('#filter_maint_month').val('');
        $('#filter_maint_year').val('');
        handleMonthFilter();
        if (currentEquipId) {
            loadHistory(currentEquipId); // เรียกแบบไม่มี params (ดึงทั้งหมด)
        }
    });

    // Event เมื่อกดปุ่ม "ดูข้อมูล"
    $('#btn_search_maint').on('click', function() {
        if (currentEquipId) {
            loadHistory(currentEquipId);
        }
    });

    // Event เมื่อกดปุ่ม "Export PDF"
    $('#btn_export_maint_pdf').on('click', function() {
        if (!currentEquipId) return;

        let month = $('#filter_maint_month').val();
        let year_th = $('#filter_maint_year').val();
        let year_en = year_th ? parseInt(year_th) - 543 : '';

        // ดึงชื่อเครื่องจาก Label ในหน้า Detail
        let equipName = $('#hist_asset_no').text().trim() || 'Equipment';
        let safeName = equipName.replace(/[/\\?%*:|"<>]/g, '-').replace(/\s+/g, '-');

        let namePart = "";
        if (month && year_th) {
            namePart = `${month}-${year_th}`;
        } else if (month) {
            namePart = `Monthly-${month}`;
        } else if (year_th) {
            namePart = `Annual-${year_th}`;
        } else {
            namePart = "Full-History";
        }

        let fileName = `F-CS-18_${safeName}_${namePart}.pdf`;

        // สร้าง URL สำหรับ Export
        let exportUrl = `./api/Transaction_equipment_log/gen_pdf.php/${fileName}?equipment_id=${currentEquipId}&month=${month}&year=${year_en}`;

        // เปิดหน้าใหม่เพื่อโหลด PDF
        window.open(exportUrl, '_blank');
    });


    // D. กดแก้ไข Log (จากปุ่มในหน้าตาราง History Modal)
    $(document).on('click', '.js-edit-log-btn', function() {
        let log = $(this).data('log');

        $('#equipmentLogForm')[0].reset();
        $('#equipmentLogForm').removeClass('was-validated');

        // โยนข้อมูล Log ลงฟอร์ม ...
        $('#equipment_id').val(log.equipment_id);
        $('#log_id').val(log.id);
        $('#log_date').val(log.log_date);
        $('#maintenance_detail').val(log.maintenance_detail);
        $('#company_name').val(log.company_name);
        $('#officer_name').val(log.officer_name);
        $('#remark').val(log.remark);

        // โยนข้อมูลเครื่องมือลง Header ของ Modal ฟอร์ม
        $('#display_log_department').text($('#hist_department').text());
        $('#display_log_asset_no').text($('#hist_asset_no').text());
        $('#display_log_category').html($('#hist_category').html());
        $('#display_log_tool_name').text($('#hist_tool_name').text());
        $('#display_log_brand').text($('#hist_brand').text());
        $('#display_log_model').text($('#hist_model').text());
        $('#display_log_serial_no').text($('#hist_serial_no').text());
        $('#display_log_responsible').text($('#hist_responsible').text());

        $('#textModal').text('แก้ไขประวัติซ่อมบำรุง/สอบเทียบ');
        $('#addLogModalLabel').html('<i class="fas fa-edit fa-lg me-2"></i> <span id="textModal">แก้ไขประวัติซ่อมบำรุง/สอบเทียบ</span>');

        $('#btn_save_all').prop('disabled', false).attr('title', '').removeClass('btn-save-readonly').addClass('btn-success');

        $('#viewHistoryModal').modal('hide');
        setTimeout(() => $('#addLogModal').modal('show'), 400);
    });

    // E. กดลบ Log (จากปุ่มในหน้าตาราง History Modal)
    $(document).on('click', '.js-delete-log-btn', function() {
        const logId = $(this).data('id');
        const logDate = $(this).data('date'); // จาก data-date
        const logDetail = $(this).data('detail'); // จาก data-detail
        const logCompany = $(this).data('company'); // จาก data-company

        if (!logId) {
            Swal.fire('Error', 'ไม่พบ ID ของรายการ', 'error');
            return;
        }

        Swal.fire({
            title: 'ยืนยันการลบ?',
            html: `
                <div class="text-center mb-3">คุณต้องการลบรายการประวัตินี้ใช่หรือไม่?</div>
                <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">วันที่ซ่อมบำรุง/สอบเทียบ:</span><br>
                        <b class="ps-2 text-danger">${logDate}</b>
                    </div>
                    
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">รายละเอียดการซ่อมบำรุง/สอบเทียบ:</span><br>
                        <b class="ps-2 text-danger">${logDetail}</b>
                    </div>
                    
                    <div>
                        <span class="text-secondary small">บริษัทผู้รับผิดชอบ:</span><br>
                        <b class="ps-2 text-danger">${logCompany}</b>
                    </div>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt me-2"></i> ลบประวัติ',
            cancelButtonText: '<i class="fas fa-times me-2"></i> ยกเลิก',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    url: './api/Transaction_equipment_log/delete.php',
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
                }).catch(error => {
                    Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message || error.statusText}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading() // ป้องกันการกดปิดระหว่างโหลด
        }).then((result) => {
            if (result.isConfirmed) {
                // เมื่อ AJAX สำเร็จ โชว์เด้งลบสำเร็จ
                Swal.fire({
                    title: 'ลบข้อมูลสำเร็จ!',
                    text: 'ประวัติถูกลบเรียบร้อยแล้ว',
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 1500,
                    returnFocus: false
                }).then(() => {
                    // โหลดตาราง History ใหม่
                    loadHistory(currentEquipId);
                    // โหลดตารางหน้าหลักใหม่
                    searchPage(typeof currentPage !== 'undefined' ? currentPage : 1);
                });
            }
        });
    });

    // F. กดปุ่มพิมพ์ประวัติ F-CS-18
    $(document).on('click', '.js-print-btn', function() {
        let equipId = $(this).data('id');
        let equipName = $(this).data('asset');

        // ทำความสะอาดชื่อเครื่องมือ
        let safeName = equipName.replace(/[/\\?%*:|"<>]/g, '-').replace(/\s+/g, '-');
        let fileName = `F-CS-18_${safeName}_Full-History.pdf`;

        // สั่งเปิดแท็บใหม่ (New Tab) และส่ง ID ไปที่ไฟล์ gen_pdf.php
        let url = `./api/Transaction_equipment_log/gen_pdf.php/${fileName}?equipment_id=${equipId}`;
        window.open(url, '_blank');
    });

    $(document).on('click', '#btn_export_excel', function(e) {
        e.preventDefault(); // กันไว้ก่อนเผื่อปุ่มอยู่ใน Form submit

        // ดึงค่าจากฟอร์มทั้งหมดมาทำเป็น Query String
        const formData = $('#searchFilterForm').serialize();

        // ยิงไปที่ API
        window.location.href = './api/Transaction_equipment_log/exportExcel.php?' + formData;
    });

    // บันทึกผลการทดสอบสารเคมี (Validation)
    $('#chemicalValidationForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;

        // 1. ตรวจสอบความถูกต้องของ Form (HTML5 Validation)
        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');

            // หา Input ตัวแรกที่ไม่ได้กรอกเพื่อให้หน้าจอ Scroll ไปหา
            const invalidElement = $(form).find(':invalid')[0];
            if (invalidElement) {
                invalidElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });

                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบถ้วน',
                    text: 'กรุณาระบุวันที่ทดสอบ และเลือกผลการทดสอบให้ครบถ้วน',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#3085d6',
                    returnFocus: false
                }).then(() => {
                    invalidElement.focus();
                });
            }
            return;
        }

        // 2. ส่งข้อมูลผ่าน AJAX
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if (res.status == "success") {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: 'ผลการทดสอบสารเคมีถูกบันทึกเรียบร้อยแล้ว',
                        timer: 1500,
                        showConfirmButton: false
                    });

                    // ปิด Modal บันทึกผล
                    $('#addValidationModal').modal('hide');

                    // เช็คว่าถ้าเปิดหน้า History Modal ทิ้งไว้อยู่ ให้โหลดตารางประวัติใหม่ด้วย
                    if ($('#viewValidationHistoryModal').is(':visible')) {
                        loadValidationHistory($('#inventory_id').val());
                    }

                    // รีโหลดตารางที่หน้าหลัก เพื่ออัปเดต Badge สถานะล่าสุด
                    const currentPage = parseInt($('#pagination-list .active a').text()) || 1;
                    searchPage(currentPage);

                } else {
                    Swal.fire('เกิดข้อผิดพลาด', res.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                }
            },
            error: function(xhr) {
                console.error("AJAX Error:", xhr.responseText);
                Swal.fire('Server Error', 'เกิดข้อผิดพลาดที่ระบบ กรุณาลองใหม่ หรือติดต่อผู้ดูแลระบบ', 'error');
            },
            complete: function() {
                // คืนค่าสถานะปุ่มกลับมาเหมือนเดิม
                $('#btn_save_all').prop('disabled', false)
                    .html('<i class="fas fa-save me-2"></i> บันทึกผลการทดสอบ');
            }
        });
    });
</script>
<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>