<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require_once './includes/check_permission.php';

$canManage = hasPermission($pdo, 'computer_usage.create');

$current_user_id = $_SESSION['user_id'];
$current_user_fullname = ""; // เตรียมตัวแปรไว้

try {
    // Query ชื่อเต็มของผู้ใช้ที่ Login อยู่
    $sql_user = "SELECT 
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
    }
} catch (Exception $e) {
    $current_user_fullname = "เจ้าหน้าที่ผู้ทำรายการ"; // กันเหนียวกรณี Error
}

// ดึงข้อมูล Role และ Department ของ User ที่ Login
$stmtRole = $pdo->prepare("SELECT role, department_id FROM users WHERE user_id = ?");
$stmtRole->execute([$current_user_id]);
$userInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);

$user_role = strtolower($userInfo['role'] ?? '');
$user_dept_id = $userInfo['department_id'] ?? null;

$title = "การใช้งานเครื่องคอมพิวเตอร์";

ob_start();
?>

<style>
    .js-add-usage-btn {
        padding: 25px;
        /* ขยายพื้นที่จิ้มออกไปรอบๆ */
        margin: -25px;
        /* ดึงขอบกลับมาเพื่อไม่ให้กระทบตำแหน่งในตาราง */
        cursor: pointer;
        touch-action: manipulation;
        /* ป้องกันการซูมเวลาเผลอ Double Tap */
    }

    .js-edit-usage-btn,
    .js-delete-usage-btn {
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
                    <div class="row g-2 justify-content-center align-items-end">

                        <?php if ($user_role === 'admin'): ?>
                            <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                                <label for="filter_department_id" class="form-label text-muted small mb-1">หน่วยงาน</label>
                                <select id="filter_department_id" name="filter_department_id" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                    <option value=""></option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="col-12 col-sm-6 col-md-6 <?= ($user_role !== 'admin') ? 'col-lg-3 col-xl-3' : 'col-lg-4 col-xl-4 ' ?>">
                            <label for="filter_computer_asset_no" class="form-label text-muted small mb-1">เลขครุภัณฑ์</label>
                            <input type="text" id="filter_computer_asset_no" name="filter_computer_asset_no"
                                class="form-control form-control-sm" placeholder="ระบุเลขครุภัณฑ์...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 <?= ($user_role !== 'admin') ? 'col-lg-3 col-xl-3' : 'col-lg-4 col-xl-4 ' ?>">
                            <label for="filter_computer_brand_model" class="form-label text-muted small mb-1">ยี่ห้อ / รุ่น</label>
                            <input type="text" id="filter_computer_brand_model" name="filter_computer_brand_model"
                                class="form-control form-control-sm" placeholder="เช่น Lenovo Thinkpad...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 <?= ($user_role !== 'admin') ? 'col-lg-3 col-xl-3' : 'col-lg-4 col-xl-4 ' ?>">
                            <label for="filter_computer_serial" class="form-label text-muted small mb-1">Serial No. (S/N)</label>
                            <input type="text" id="filter_computer_serial" name="filter_computer_serial"
                                class="form-control form-control-sm" placeholder="ระบุเลข Serial...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 <?= ($user_role !== 'admin') ? 'col-lg-3 col-xl-3' : 'col-lg-4 col-xl-4 ' ?>">
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
                    <th style="min-width: 180px;">ยี่ห้อ / รุ่น</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 150px;">Serial No. (S/N)</th>
                    <th class="d-none d-md-table-cell" style="min-width: 180px;">ผู้ดูแล</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 110px;">วันที่ใช้งานล่าสุด</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 140px;">กิจกรรมล่าสุด</th>
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

<!-- Modal สำหรับเพิ่มรายการใช้งานใหม่ / แก้ไขรายการเดิม -->
<div class="modal fade" id="addUsageModal" aria-labelledby="addUsageModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addUsageModalLabel">
                    <i class="fas fa-clipboard-check fa-lg me-2"></i> <span id="textModal">บันทึกประวัติการใช้งานเครื่องคอมพิวเตอร์</span>
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">

                <div class="card border-0 shadow-sm mb-4 border-start border-primary border-4 rounded-3">
                    <div class="card-body bg-white">
                        <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                            <i class="fas fa-laptop fa-lg text-primary me-2"></i>
                            <h6 class="mb-0 fw-bold text-primary">ข้อมูลอ้างอิงเครื่องคอมพิวเตอร์</h6>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-lg-12">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">หน่วยงาน</span>
                                <span class="fw-bold" id="display_usage_department">-</span>
                            </div>
                            <div class="col-6 col-lg-4">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">เลขครุภัณฑ์</span>
                                <span class="fw-bold" id="display_usage_asset_no">-</span>
                            </div>
                            <div class="col-6 col-lg-4">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ยี่ห้อ (Brand)</span>
                                <span class="fw-bold" id="display_usage_brand">-</span>
                            </div>
                            <div class="col-6 col-lg-4">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">รุ่น (Model)</span>
                                <span class="fw-bold" id="display_usage_model">-</span>
                            </div>
                            <div class="col-6 col-lg-4">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">Serial No. (S/N)</span>
                                <span class="fw-bold" id="display_usage_serial_no">-</span>
                            </div>
                            <div class="col-12 col-lg-8">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ผู้ดูแลเครื่องคอมพิวเตอร์</span>
                                <span class="fw-bold" id="display_usage_responsible">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="post" id="computerUsageForm" action="./api/Transaction_computer_usage/save.php" novalidate>
                    <input type="hidden" id="computer_id" name="computer_id">
                    <input type="hidden" id="usage_id" name="id">

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header"><i class="fas fa-info-circle me-1"></i> รายละเอียดการใช้งาน</legend>
                        <div class="row g-3 justify-content-start">

                            <div class="col-md-4">
                                <label for="usage_date" class="form-label">วันที่ใช้งาน <span class="text-danger">*</span></label>
                                <input type="date" id="usage_date" name="usage_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">กรุณาระบุวันที่ใช้งาน</div>
                            </div>

                            <div class="col-md-8">
                                <label for="report_no" class="form-label">การใช้งาน / เลขรายงานที่ <span class="text-danger">*</span></label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="report_no"
                                        name="report_no"
                                        class="form-control"
                                        placeholder="ระบุรายละเอียดหรือเลขรายงาน (เช่น รายงานเลขที่ 1234/2569)"
                                        required>
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="report_no"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุการใช้งานหรือเลขรายงาน</div>
                            </div>

                            <div class="col-md-4">
                                <label for="condition_status" class="form-label">สภาพเครื่องคอมพิวเตอร์ <span class="text-danger">*</span></label>
                                <select id="condition_status" name="condition_status" class="form-select" required>
                                    <option value="" disabled selected>-- เลือกสภาพเครื่องคอมพิวเตอร์ --</option>
                                    <option value="ปกติ">ปกติ</option>
                                    <option value="ไม่ปกติ">ไม่ปกติ</option>
                                </select>
                                <div class="invalid-feedback">กรุณาระบุสภาพเครื่องหลังใช้งาน</div>
                            </div>

                            <div class="col-md-8">
                                <label for="used_by" class="form-label">ผู้ใช้งาน <span class="text-danger">*</span></label>
                                <select id="used_by" name="used_by" class="form-select select2-user" data-placeholder="-- ค้นหาชื่อผู้ใช้งาน --" required>
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาระบุรายชื่อผู้ใช้งาน</div>
                            </div>

                            <div class="col-md-12">
                                <label for="remark" class="form-label">หมายเหตุ</label>
                                <div class="input-group input-group-seamless">
                                    <textarea
                                        id="remark"
                                        name="remark"
                                        class="form-control"
                                        rows="2"
                                        placeholder="ระบุหมายเหตุเพิ่มเติม (ถ้ามี) เช่น พบอาการเครื่องค้าง, จอภาพผิดปกติ"></textarea>
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
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="computerUsageForm">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal สำหรับดู list รายการใช้งานทั้งหมดของเครื่องคอมพิวเตอร์ เครื่องนั้น ๆ  -->
<div class="modal fade" id="viewHistoryModal" tabindex="-1" aria-labelledby="viewHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width: 90%;">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="viewHistoryModalLabel">
                    <i class="fas fa-history me-2"></i> ประวัติการใช้งานเครื่องคอมพิวเตอร์
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="card border-0 shadow-sm mb-4 border-start border-primary border-4 rounded-3">
                    <div class="card-body bg-white">
                        <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                            <i class="fas fa-laptop fa-lg text-primary me-2"></i>
                            <h6 class="mb-0 fw-bold text-primary">ข้อมูลอ้างอิงเครื่องคอมพิวเตอร์</h6>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-lg-12">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">หน่วยงาน</span>
                                <span class="fw-bold" id="hist_department">-</span>
                            </div>
                            <div class="col-6 col-lg-4">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">เลขครุภัณฑ์</span>
                                <span class="fw-bold fs-6" id="hist_asset_no">-</span>
                            </div>
                            <div class="col-6 col-lg-4">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ยี่ห้อ (Brand)</span>
                                <span class="fw-bold" id="hist_brand">-</span>
                            </div>
                            <div class="col-6 col-lg-4">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">รุ่น (Model)</span>
                                <span class="fw-bold" id="hist_model">-</span>
                            </div>
                            <div class="col-6 col-lg-4">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">Serial No. (S/N)</span>
                                <span class="fw-bold" id="hist_serial_no">-</span>
                            </div>
                            <div class="col-6 col-lg-8">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ผู้ดูแลเครื่องคอมพิวเตอร์</span>
                                <span class="fw-bold" id="hist_responsible">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border mb-3 bg-light">
                    <div class="card-body p-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-6">
                                <label for="filter_computer_year" class="form-label text-muted small mb-1 fw-bold"><i class="far fa-calendar me-1"></i> ปี (พ.ศ.)</label>
                                <select id="filter_computer_year" class="form-select form-select-sm">
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
                                <label for="filter_computer_month" class="form-label text-muted small mb-1 fw-bold"><i class="far fa-calendar-alt me-1"></i> เดือน</label>
                                <select id="filter_computer_month" class="form-select form-select-sm">
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
                                <button type="button" class="btn btn-warning btn-sm text-dark fw-bold px-4 shadow-sm flex-fill flex-md-grow-0" id="btn_clear_computer_filter">
                                    <i class="fas fa-undo me-2"></i> ล้างค่า
                                </button>
                                <button type="button" class="btn btn-primary btn-sm text-white fw-bold px-4 shadow-sm flex-fill flex-md-grow-0" id="btn_search_computer">
                                    <i class="fas fa-search me-2"></i> ดูข้อมูล
                                </button>
                                <button type="button" class="btn btn-sm fw-bold px-4 shadow-sm flex-fill flex-md-grow-0 text-white" style="background-color: #6f42c1;" id="btn_export_computer_pdf">
                                    <i class="fas fa-file-pdf me-2"></i> Export PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header py-3 px-4">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-list fa-lg me-2"></i> รายการบันทึกการใช้งาน</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle text-center mb-0" style="font-size: 0.95rem;">
                                <thead class="table-primary">
                                    <tr class="text-nowrap text-center">
                                        <th style="min-width: 100px;">Action</th>
                                        <th style="min-width: 120px;">วัน เดือน ปี</th>
                                        <th style="min-width: 250px;">การใช้งาน / เลขรายงานที่</th>
                                        <th style="min-width: 100px;">สภาพ</th>
                                        <th class="d-none d-lg-table-cell" style="min-width: 150px;">ผู้ใช้งาน</th>
                                        <th class="d-none d-lg-table-cell" style="min-width: 150px;">หมายเหตุ</th>
                                    </tr>
                                </thead>
                                <tbody id="usage_history_body">
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
    // ตัวแปรเก็บ ID เครื่องคอมพิวเตอร์ปัจจุบัน เพื่อใช้ตอนโหลด/รีเฟรช Modal ประวัติ
    let currentComputerId = null;
    let currentPage = 1;

    $(document).ready(function() {
        searchPage(1);

        loadUserList();

        if ($('#filter_department_id').length) {
            loadDepartments();
        }

        // เริ่มต้นใช้งาน Select2 
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
                dropdownParent: $('#addUsageModal')
            });
        }

        $('#filter_computer_year, #filter_computer_month').on('change', function() {
            handleMonthFilter();
        });

        // เรียกครั้งแรก (Initial)
        handleMonthFilter();
    });

    function handleMonthFilter() {
        const $yearSelect = $('#filter_computer_year');
        const $monthSelect = $('#filter_computer_month');

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
                    // ให้ค่าเริ่มต้นเป็นว่างเปล่าเพื่อรองรับ Placeholder ของ Select2
                    let options = '<option value=""></option>';

                    res.data.forEach(item => {
                        options += `<option value="${item.user_id}">${item.fullname}</option>`;
                    });

                    $('#used_by, #filter_responsible_id').html(options);

                    // สั่งให้ Select2 อัปเดต UI หลังจากยัด options เข้าไปแล้ว
                    $('#filter_responsible_id').trigger('change');
                    $('#used_by').trigger('change');
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
                    $('#filter_department_id').html(options).trigger('change');
                }
            }
        });
    }

    // ==========================================
    // ส่วนจัดการประวัติการใช้งาน (Transaction Usage)
    // ==========================================

    // กดปุ่ม "ลงบันทึก" จากหน้าตารางหลัก
    $(document).on('click', '.js-add-usage-btn', function() {

        // ล้างค่าในฟอร์มและสถานะ Validation
        const $form = $('#computerUsageForm');
        $form[0].reset();
        $form.removeClass('was-validated');
        $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');

        // เคลียร์ค่า Select2 (ผู้ใช้งาน) และ ID Transaction
        $('#used_by').val('').trigger('change');
        $('#usage_id').val('');

        // หยอดข้อมูลอ้างอิงจาก Master (ดึงจาก data-attributes ของปุ่มที่คลิก)
        $('#computer_id').val($(this).data('id')); // ID หลักของเครื่องคอมพิวเตอร์
        $('#display_usage_department').text($(this).data('department') || '-');
        $('#display_usage_asset_no').text($(this).data('asset') || '-');
        $('#display_usage_brand').text($(this).data('brand') || '-');
        $('#display_usage_model').text($(this).data('model') || '-');
        $('#display_usage_serial_no').text($(this).data('serial') || '-');
        $('#display_usage_responsible').text($(this).data('resp') || '-');

        // --- Auto Fill User ที่ Login อยู่ ---
        let currentUserId = '<?php echo $current_user_id; ?>';
        let currentFullname = '<?php echo $current_user_fullname; ?>';

        if (currentUserId && currentFullname !== "") {
            // ตรวจสอบว่าใน Select2 มี Option นี้หรือยัง ถ้าไม่มีให้สร้างใหม่แล้วเลือกทันที
            if ($('#used_by').find("option[value='" + currentUserId + "']").length) {
                $('#used_by').val(currentUserId).trigger('change');
            } else {
                // สร้าง Option ใหม่ (text, value, defaultSelected, selected)
                let newUserOption = new Option(currentFullname, currentUserId, true, true);
                $('#used_by').append(newUserOption).trigger('change');
            }
        }

        // ตั้งค่าวันที่ใช้งานเริ่มต้นเป็น "วันที่ปัจจุบัน"
        document.getElementById('usage_date').valueAsDate = new Date();

        // ปรับเปลี่ยนหัวข้อ Modal (เปลี่ยนกลับจากตอน edit)
        $('#textModal').text('บันทึกประวัติการใช้งานเครื่องคอมพิวเตอร์');
        $('#addUsageModalLabel').html('<i class="fas fa-clipboard-check fa-lg me-2"></i> <span id="textModal">บันทึกประวัติการใช้งานเครื่องคอมพิวเตอร์</span>');

        // แสดง Modal
        $('#btn_save_all').prop('disabled', false).attr('title', '').removeClass('btn-save-readonly').addClass('btn-success');
        $('#addUsageModal').modal('show');
    });

    $(document).on('click', '.clickable-row', function(e) {
        // ป้องกันถ้าคลิกโดนปุ่มลงบันทึก หรือ ปุ่มพิมพ์
        if ($(e.target).closest('.js-add-usage-btn, .js-print-btn').length) {
            return;
        }

        // --- เพิ่มการ Reset filter ---
        $('#filter_computer_month').val('');
        $('#filter_computer_year').val('');
        handleMonthFilter();

        const $row = $(this);
        currentComputerId = $row.data('id');

        // เซ็ตข้อมูล Header ใน Modal History
        $('#hist_department').text($row.data('department') || '-');
        $('#hist_asset_no').text($row.data('asset') || '-');
        $('#hist_brand').text($row.data('brand') || '-');
        $('#hist_model').text($row.data('model') || '-');
        $('#hist_serial_no').text($row.data('serial') || '-');
        $('#hist_responsible').text($row.data('resp') || '-');

        // เรียกฟังก์ชันโหลดประวัติ เพื่อแสดง modal พร้อมตารางแสดงบันทึกการใช้งานเครื่องคอมพิวเตอร์
        loadHistory(currentComputerId);
    });

    // ฟังก์ชันโหลดข้อมูลประวัติการใช้เครื่องคอมพิวเตอร์มาแสดง
    function loadHistory(computerId) {
        currentComputerId = computerId;
        let month = $('#filter_computer_month').val();
        let year_th = $('#filter_computer_year').val();
        let year_en = year_th ? parseInt(year_th) - 543 : '';

        $('#usage_history_body').html('<tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-info mb-2"></i><p>กำลังโหลด...</p></td></tr>');
        $.ajax({
            url: './api/Transaction_computer_usage/getHistory.php',
            type: 'GET',
            data: {
                computer_id: computerId,
                month: month,
                year: year_en
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let html = '';
                    if (res.data && res.data.length > 0) {
                        res.data.forEach(log => {
                            let statusBadge = (log.condition_status === 'ปกติ') ?
                                '<span class="badge bg-success rounded-pill px-3">ปกติ</span>' :
                                '<span class="badge bg-danger rounded-pill px-3">ไม่ปกติ</span>';

                            html += `
                                <tr>
                                    <td class="text-center align-middle">
                                        <div class="d-flex justify-content-center gap-2">
                                            <i class="fas fa-pen-to-square fa-lg text-warning ${window._canManage ? 'js-edit-usage-btn cursor-pointer' : ''}" 
                                            data-log='${JSON.stringify(log)}' title="${window._canManage ? 'แก้ไข' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>

                                            <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-usage-btn cursor-pointer' : ''}" 
                                                data-id="${log.id}" 
                                                data-date="${log.usage_date_show}" 
                                                data-report="${log.report_no}" 
                                                data-user="${log.used_by_name || '-'}" 
                                                title="${window._canManage ? 'ลบ' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">${log.usage_date_show}</td>
                                    <td class="text-start align-middle">
                                        <div class="fw-bold d-lg-none">${log.report_no}</div>
                                        <div class="d-none d-lg-block">${log.report_no}</div>
                                        
                                        <div class="d-block d-lg-none mt-1 pt-1" style="border-top: 1px solid rgba(108, 117, 125, 0.2);">
                                            <div class="mb-1" style="font-size: 0.85rem;">
                                                <span class="text-muted fw-medium">ผู้ใช้งาน:</span> 
                                                <span class="text-dark">${log.used_by_name || '-'}</span>
                                            </div>
                                            ${log.remark ? `
                                            <div style="font-size: 0.8rem; line-height: 1.2;">
                                                <span class="text-muted fw-medium">หมายเหตุ:</span>
                                                <span class="text-secondary italic">${log.remark}</span>
                                            </div>` : ''}
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">${statusBadge}</td>
                                    <td class="text-start align-middle d-none d-lg-table-cell">${log.used_by_name || '-'}</td>
                                    <td class="text-start text-muted small d-none d-lg-table-cell">${log.remark || '-'}</td>
                                </tr>
                            `;
                        });
                    } else {
                        if (month || year_th) {
                            html = `
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-calendar-minus fa-3x mb-3 opacity-50"></i>
                                        <p class="mb-1 fw-bold">ไม่พบประวัติการใช้งานในช่วงเวลาที่เลือก</p>
                                        <div class="small opacity-75">
                                            คอมพิวเตอร์เครื่องนี้ยังไม่มีบันทึกการใช้งานใน <b class="text-dark">ปี / เดือน</b> ที่ท่านกำลังค้นหา <br>
                                            ลองปรับเปลี่ยนเงื่อนไขการค้นหา หรือเพิ่มรายการใหม่ได้ที่ปุ่ม 
                                            <b class="mx-2"><i class="fas fa-circle-info me-1"></i>จัดการข้อมูล</b> ในตารางหลัก
                                        </div>
                                    </div>
                                </td>
                            </tr>`;
                        } else {
                            // กรณีที่ไม่มีประวัติเลย (Default state)
                            html = `
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-history fa-3x mb-3 opacity-50"></i>
                                        <p class="mb-1 fw-bold">ยังไม่มีประวัติการใช้งาน</p>
                                        <small class="opacity-75">คอมพิวเตอร์เครื่องนี้ยังไม่มีข้อมูลการใช้งานในระบบ<br>สามารถเพิ่มรายการใหม่ได้ผ่านปุ่ม <b class="mx-2"><i class="fas fa-circle-info me-1"></i>จัดการข้อมูล</b> ในตารางหลัก</small>
                                    </div>
                                </td>
                            </tr>`;
                        }
                    }
                    $('#usage_history_body').html(html);
                    $('#viewHistoryModal').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'ไม่สามารถโหลดประวัติได้', 'error');
                }
            },
            error: function(xhr) {
                console.error(xhr.responseText);
                Swal.fire('Error', 'ไม่สามารถเชื่อมต่อ Server ได้', 'error');
            }
        });
    }

    // Event ปุ่มล้างค่าใน Modal ประวัติเครื่องมือ
    $('#btn_clear_computer_filter').on('click', function() {
        $('#filter_computer_month').val('');
        $('#filter_computer_year').val('');
        handleMonthFilter();
        if (currentComputerId) {
            loadHistory(currentComputerId); // เรียกแบบไม่มี params (ดึงทั้งหมด)
        }
    });

    // Event เมื่อกดปุ่ม "ดูข้อมูล"
    $('#btn_search_computer').on('click', function() {
        if (currentComputerId) {
            loadHistory(currentComputerId);
        }
    });

    // Event เมื่อกดปุ่ม "Export PDF"
    $('#btn_export_computer_pdf').on('click', function() {
        if (!currentComputerId) return;

        let month = $('#filter_computer_month').val();
        let year_th = $('#filter_computer_year').val();
        let year_en = year_th ? parseInt(year_th) - 543 : '';

        // ดึงชื่อเครื่องจาก Label ในหน้า Detail
        let assetNo = $('#hist_asset_no').text().trim() || 'Computer';
        let safeName = assetNo.replace(/[/\\?%*:|"<>]/g, '-').replace(/\s+/g, '-');

        // ตรรกะชื่อไฟล์รองรับการเลือกแบบโดดๆ (เหมือน F-CS-18)
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

        // สร้าง URL สำหรับ Export
        let fileName = `F-CS-23_${safeName}_${namePart}.pdf`;
        let exportUrl = `./api/Transaction_computer_usage/gen_pdf.php/${fileName}?computer_id=${currentComputerId}&month=${month}&year=${year_en}`;

        // เปิดหน้าใหม่เพื่อโหลด PDF
        window.open(exportUrl, '_blank');
    });

    // กดแก้ไขประวัติ (จากปุ่มในหน้าตาราง History Modal)
    $(document).on('click', '.js-edit-usage-btn', function() {
        // ดึงก้อนข้อมูล Log ที่ฝังไว้ใน data-log (ตอนวาดตารางประวัติ)
        let log = $(this).data('log');

        // รีเซ็ตฟอร์มบันทึกการใช้งานคอมพิวเตอร์
        const $form = $('#computerUsageForm');
        $form[0].reset();
        $form.removeClass('was-validated');
        $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');

        // --- ยัดข้อมูลลงใน Form ---
        $('#computer_id').val(log.computer_id); // ID หลักของคอมพิวเตอร์
        $('#usage_id').val(log.id); // ID ของรายการ Transaction ที่จะแก้
        $('#usage_date').val(log.usage_date);
        $('#report_no').val(log.report_no);
        $('#condition_status').val(log.condition_status);
        $('#remark').val(log.remark);

        // จัดการ Select2 (ผู้ใช้งาน)
        $('#used_by').val(log.used_by).trigger('change');

        // --- ยัดข้อมูล Header ใน Modal (ดึงจาก Header ของ History Modal มาใช้ได้เลย) ---
        $('#display_usage_department').text($('#hist_department').text());
        $('#display_usage_asset_no').text($('#hist_asset_no').text());
        $('#display_usage_brand').text($('#hist_brand').text());
        $('#display_usage_model').text($('#hist_model').text());
        $('#display_usage_serial_no').text($('#hist_serial_no').text());
        $('#display_usage_responsible').text($('#hist_responsible').text());

        // ปรับเปลี่ยนหัวข้อ Modal ให้เป็นโหมดแก้ไข
        $('#textModal').text('แก้ไขประวัติการใช้งาน');
        $('#addUsageModalLabel').html('<i class="fas fa-edit fa-lg me-2"></i> <span id="textModal">แก้ไขประวัติการใช้งานเครื่องคอมพิวเตอร์</span>');

        $('#btn_save_all').prop('disabled', false).attr('title', '').removeClass('btn-save-readonly').addClass('btn-success');

        // สลับ Modal (ปิดประวัติ แล้วเปิดฟอร์มแก้ไข)
        $('#viewHistoryModal').modal('hide');
        setTimeout(() => $('#addUsageModal').modal('show'), 400);
    });

    // กดลบประวัติ 
    $(document).on('click', '.js-delete-usage-btn', function() {
        const dataId = $(this).data('id');
        const logDate = $(this).data('date');
        const logReport = $(this).data('report');
        const logUser = $(this).data('user');

        if (!dataId) {
            Swal.fire('Error', 'ไม่พบ ID ของรายการ', 'error');
            return;
        }

        Swal.fire({
            title: 'ยืนยันการลบ?',
            html: `
                <div class="text-center mb-3">คุณต้องการลบรายการบันทึกการใช้งานนี้ใช่หรือไม่?</div>
                <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">วันที่ใช้งาน:</span><br>
                        <b class="ps-2 text-danger">${logDate}</b>
                    </div>
                    
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">การใช้งาน / เลขรายงานที่:</span><br>
                        <b class="ps-2 text-danger">${logReport}</b>
                    </div>
                    
                    <div>
                        <span class="text-secondary small">ผู้ใช้งาน:</span><br>
                        <b class="ps-2 text-danger">${logUser}</b>
                    </div>
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
                    url: './api/Transaction_computer_usage/delete.php',
                    type: 'POST',
                    data: {
                        id: dataId
                    },
                    dataType: 'json'
                }).then(response => {
                    if (response.status !== 'success') throw new Error(response.message || 'เกิดข้อผิดพลาดในการลบ');
                    return response;
                }).catch(error => {
                    Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message || 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้'}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'ลบข้อมูลสำเร็จ!',
                    text: 'ประวัติการใช้งานเครื่องคอมพิวเตอร์ถูกลบเรียบร้อยแล้ว',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false,
                    returnFocus: false
                }).then(() => {
                    // โหลดตารางประวัติใน Modal ใหม่ --> จะเห็นเฉพาะรายการที่ยังไม่ถูกลบ (status_delete = 0)
                    loadHistory(currentComputerId);
                    // อัปเดตตารางหน้าหลัก (เพื่อรีเฟรชข้อมูล "การใช้งานล่าสุด" ในหน้า Transaction หลัก) ใช้ตัวแปร currentPage ที่เราเก็บสถานะหน้าปัจจุบันไว้
                    searchPage(typeof currentPage !== 'undefined' ? currentPage : 1);
                });
            }
        });
    });

    // กดปุ่มพิมพ์ประวัติจากหน้าตารางหลัก
    $(document).on('click', '.js-print-btn', function() {
        let computerId = $(this).data('id');
        let assetNo = $(this).data('asset');

        let safeName = assetNo.replace(/[/\\?%*:|"<>]/g, '-').replace(/\s+/g, '-');
        let fileName = `F-CS-23_${safeName}_Full-History.pdf`;

        let url = `./api/Transaction_computer_usage/gen_pdf.php/${fileName}?computer_id=${computerId}`;
        window.open(url, '_blank');
    });

    $(document).on('click', '#btn_export_excel', function(e) {
        e.preventDefault(); // กันไว้ก่อนเผื่อปุ่มอยู่ใน Form submit

        // ดึงค่าจากฟอร์มทั้งหมดมาทำเป็น Query String
        const formData = $('#searchFilterForm').serialize();

        // ยิงไปที่ API
        window.location.href = './api/Transaction_computer_usage/exportExcel.php?' + formData;
    });

    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    // กดปุ่ม "บันทึกข้อมูล" ใน Modal Form
    $('#computerUsageForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        const $form = $(form);

        // --- ตรวจสอบความถูกต้องของข้อมูล (Validation) ---        
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

        // --- ส่งข้อมูลไปยัง API ด้วย AJAX ---
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: new FormData(form),
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function() {
                $('#btn_save_all').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> กำลังบันทึก...');
            },
            success: function(res) {
                if (res.status == "success") {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: 'ข้อมูลการใช้งานถูกบันทึกเรียบร้อยแล้ว',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    $('#addUsageModal').modal('hide');

                    // หากเปิดหน้าต่าง "ประวัติการใช้งาน" ค้างไว้อยู่ ให้โหลดประวัติใหม่ทันที
                    if ($('#viewHistoryModal').is(':visible') && typeof currentComputerId !== 'undefined' && currentComputerId) {
                        loadHistory(currentComputerId);
                    }
                    // รีเฟรชตารางหน้าหลัก (เพื่ออัปเดต "การใช้งานล่าสุด")
                    searchPage(typeof currentPage !== 'undefined' ? currentPage : 1);
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', res.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                }
            },
            error: function(xhr) {
                console.error("AJAX Error:", xhr.responseText);
                Swal.fire('Server Error', 'เกิดข้อผิดพลาดที่ระบบ กรุณาลองใหม่ หรือติดต่อผู้ดูแลระบบ', 'error');
            },
            complete: function() {
                $('#btn_save_all').prop('disabled', false).html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล');
            }
        });
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
            url: './api/Master_computer_list/searchDataWithUsage.php',
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
                    } else {
                        $('#pagination-list').empty(); // เคลียร์ปุ่มถ้าไม่เจอข้อมูล
                    }
                } else {
                    $('#table_body').html(`<tr><td colspan="9" class="text-center py-5 text-danger">${response.message}</td></tr>`);
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

                // จัดเตรียมข้อมูลพื้นฐาน 
                let dept = row.department_name || '-';
                let asset_no = row.computer_asset_no || '-';
                let brand = row.computer_brand || '-';
                let model = row.computer_model || '-';
                let serial_no = row.computer_serial || '-';
                let responsible = row.responsible_fullname || '-';

                // ข้อมูลการใช้งานล่าสุด (Transaction Data)
                let lastDate = row.last_usage_date ? row.last_usage_date : '<span class="text-muted small">ไม่มีประวัติ</span>';
                let lastDetailText = row.last_report_no ? row.last_report_no : '-';
                let lastDetailHtml = row.last_report_no ? row.last_report_no : '<span class="text-muted">-</span>';

                // Logic สำหรับปุ่ม Export PDF (สีม่วง)
                // จะเปิดให้กดได้ (Enabled) ก็ต่อเมื่อมีประวัติการใช้งาน (last_usage_date ไม่เป็น null)
                let hasLog = !!row.last_usage_date;
                let btnStyle = hasLog ?
                    'background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;' :
                    'box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); font-size: 11px; white-space: nowrap;';
                let btnClass = hasLog ? '' : 'btn-secondary';

                let printBtnHtml = `
                <button type="button" class="btn js-print-btn ${btnClass} shadow-sm" 
                        style="${btnStyle}" 
                        data-id="${row.id}" 
                        data-asset="${asset_no}" 
                        ${hasLog ? '' : 'disabled'} 
                        title="${hasLog ? 'พิมพ์ประวัติการใช้งาน PDF' : 'ยังไม่มีประวัติให้พิมพ์'}">
                    <i class="fas fa-file-pdf me-1"></i> Export PDF
                </button>`;

                html += `
                    <tr class="clickable-row cursor-pointer" 
                        data-id="${row.id}" 
                        data-department="${dept}"
                        data-asset="${asset_no}" 
                        data-brand="${brand}" 
                        data-model="${model}" 
                        data-serial="${serial_no}" 
                        data-resp="${responsible}"
                        title="ดูข้อมูล">
                        <td class="text-center align-middle">
                            <div class="d-flex align-items-center justify-content-center" style="min-height: 100%;">
                                <button type="button" 
                                        class="btn btn-sm ${window._canManage ? 'btn-success' : 'btn-secondary'} py-1 px-2 ${window._canManage ? 'js-add-usage-btn' : ''}"
                                        data-id="${row.id}" 
                                        data-department="${dept}"
                                        data-asset="${asset_no}" 
                                        data-brand="${brand}" 
                                        data-model="${model}" 
                                        data-serial="${serial_no}" 
                                        data-resp="${responsible}"
                                        ${window._canManage ? 'title="ลงบันทึกการใช้งานเครื่องคอมพิวเตอร์"' : 'title="คุณไม่มีสิทธิ์จัดการข้อมูล" disabled'}>
                                    <i class="fas fa-circle-info me-1"></i> <span style="font-size: 0.8rem;">จัดการข้อมูล</span>
                                </button>
                            </div>
                        </td>
                        <td class="text-center align-middle d-none d-xl-table-cell">${start_no + i}</td>
                        <td class="text-start align-middle">${asset_no}</td>
                        <td class="text-start align-middle d-none d-md-table-cell">${dept}</td>
                        <td class="text-start align-middle">
                            <div class="fw-bold">${brand}</div>
                            <div class="text-muted small">${model}</div>
                        </td>
                        <td class="text-start align-middle d-none d-lg-table-cell">${serial_no}</td>
                        <td class="text-start align-middle d-none d-md-table-cell">${responsible}</td>
                        <td class="text-center align-middle d-none d-xl-table-cell">${lastDate}</td>
                        <td class="align-middle d-none d-xl-table-cell text-truncate" style="max-width: 200px;" title="${lastDetailText}">
                            ${lastDetailHtml}
                        </td>
                        <td class="text-center align-middle d-none d-md-table-cell">${printBtnHtml}</td>
                    </tr>
                `;
            });
        } else {
            html = `
                <tr>
                    <td colspan="10" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-laptop fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0 fw-bold">ไม่พบรายการเครื่องคอมพิวเตอร์ในระบบ</p>
                            <small class="opacity-75">ลองเปลี่ยนคำค้นหา  หรือกดปุ่ม <b class="text-dark">" เพิ่มรายการ "</b> ในหน้าเครื่องคอมพิวเตอร์ เพื่อสร้างข้อมูลใหม่</small>
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
</script>
<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>