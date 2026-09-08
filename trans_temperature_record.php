<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require_once './includes/check_permission.php';

$canManage = hasPermission($pdo, 'temperature.create');

// --- ดึงข้อมูลผู้ใช้งานปัจจุบัน ---
$current_user_id = $_SESSION['user_id'];
$current_user_fullname = "";
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
        $user_role = strtolower($user_row['role'] ?? '');
        $user_dept_id = $user_row['department_id'] ?? null;
    }
} catch (Exception $e) {
    $current_user_fullname = "เจ้าหน้าที่ผู้บันทึก";
}

$title = "การควบคุมอุณหภูมิ";
ob_start();
?>

<style>
    .js-add-record-btn {
        padding: 25px;
        /* ขยายพื้นที่จิ้มออกไปรอบๆ */
        margin: -25px;
        /* ดึงขอบกลับมาเพื่อไม่ให้กระทบตำแหน่งในตาราง */
        cursor: pointer;
        touch-action: manipulation;
        /* ป้องกันการซูมเวลาเผลอ Double Tap */
    }

    .js-edit-record-btn,
    .js-delete-record-btn {
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
                            <div class="col-12 col-md-6 col-lg-3 col-xl-3">
                                <label for="filter_department_id" class="form-label text-muted small mb-1">หน่วยงาน</label>
                                <select id="filter_department_id" name="filter_department_id" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                    <option value=""></option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="col-12 <?= ($user_role !== 'admin') ? 'col-md-4 col-lg-4 col-xl-4' : 'col-md-6 col-lg-3 col-xl-2' ?>">
                            <label for="filter_device_code" class="form-label text-muted small mb-1">หมายเลขเครื่องวัด</label>
                            <input type="text" id="filter_device_code" name="filter_device_code"
                                class="form-control form-control-sm" placeholder="ระบุหมายเลขเครื่อง...">
                        </div>

                        <div class="col-12 <?= ($user_role !== 'admin') ? 'col-md-4 col-lg-4 col-xl-4' : 'col-md-6 col-lg-3 col-xl-4' ?>">
                            <label for="filter_location_use" class="form-label text-muted small mb-1">สถานที่ใช้งาน</label>
                            <input type="text" id="filter_location_use" name="filter_location_use"
                                class="form-control form-control-sm shadow-none" placeholder="ระบุสถานที่...">
                        </div>

                        <div class="col-12 <?= ($user_role !== 'admin') ? 'col-md-4 col-lg-4 col-xl-4' : 'col-md-6 col-lg-3 col-xl-3' ?>">
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
                <tr class="text-nowrap text-center">
                    <th style="min-width: 120px;">Action</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 50px;">ลำดับ</th>
                    <th style="min-width: 130px;">หมายเลขเครื่องวัด</th>
                    <th class="d-none d-xxl-table-cell" style="min-width: 130px;">หน่วยงาน</th>
                    <th style="min-width: 160px;">สถานที่ใช้งาน</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 130px;">ช่วงการใช้งาน</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 180px;">ผู้รับผิดชอบ</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 150px;">ค่าความไม่แน่นอนของการวัด</th>
                    <th class="d-none d-md-table-cell" style="min-width: 120px;">วันที่บันทึกล่าสุด</th>
                    <th class="d-none d-md-table-cell" style="min-width: 120px;">อุณหภูมิล่าสุด</th>
                </tr>
            </thead>
            <tbody id="table_body" style="font-size: 14px;">
                <!-- ข้อมูลจะถูกโหลดจาก API เป็นอีก v. ที่ดึงทั้งข้อมูลจาก table Master ของ เครื่องวัด + JOIN กับ table Transaction ของเครื่องวัดด้วย-->
            </tbody>
        </table>
    </div>
</div>

<div class="row col-md-12 d-flex justify-content-center mt-3">
    <nav aria-label="Page navigation mt-3">
        <ul id="pagination-list" class="pagination justify-content-center"></ul>
    </nav>
</div>

<!-- Modal สำหรับเพิ่มรายการควบคุมอุณหภูมิใหม่ / แก้ไขรายการเดิม -->
<div class="modal fade" id="addRecordModal" aria-labelledby="addRecordModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addRecordModalLabel">
                    <i class="fas fa-thermometer-half fa-lg me-2"></i> <span id="textModal">บันทึกการควบคุมอุณหภูมิ</span>
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">

                <div class="card border-0 shadow-sm mb-4 border-start border-primary border-4 rounded-3">

                    <div class="card-body bg-white">
                        <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                            <i class="fas fa-info-circle fa-lg text-primary me-2"></i>
                            <h6 class="mb-0 fw-bold text-primary">ข้อมูลอ้างอิงเครื่องวัดอุณหภูมิ</h6>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">หน่วยงาน</span>
                                <span class="fw-bold" id="display_dept_name">-</span>
                            </div>
                            <div class="col-12 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">หมายเลขเครื่อง</span>
                                <span class="fw-bold" id="display_device_code">-</span>
                            </div>
                            <div class="col-12">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">สถานที่ใช้งาน</span>
                                <span class="fw-bold" id="display_location_use">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ช่วงการใช้งาน (°C)</span>
                                <span class="fw-bold" id="display_device_range">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ค่าความไม่แน่นอนของการวัด (°C)</span>
                                <span class="fw-bold" id="display_uncertainty">-</span>
                            </div>
                            <div class="col-12">
                                <h6 class="text-primary fw-bold border-bottom pb-2 mb-3 mt-3">
                                    <i class="fas fa-users fa-lg me-1"></i> รายชื่อผู้รับผิดชอบ
                                </h6>

                                <div class="row">
                                    <div class="col-6">
                                        <span class="d-block text-muted" style="font-size: 0.75rem;">ผู้รับผิดชอบคนที่ 1</span>
                                        <span class="fw-bold" id="display_responsible_1">-</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="d-block text-muted" style="font-size: 0.75rem;">ผู้รับผิดชอบคนที่ 2</span>
                                        <span class="fw-bold" id="display_responsible_2">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="post" id="temperatureRecordForm" action="./api/Transaction_temperature_record/save.php" novalidate>
                    <input type="hidden" id="device_id" name="device_id">
                    <input type="hidden" id="record_id" name="id">

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header"><i class="fas fa-edit me-1"></i> รายละเอียดการบันทึก</legend>
                        <div class="row g-3 justify-content-start">

                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="record_date" class="form-label">วันที่บันทึก <span class="text-danger">*</span></label>
                                <input type="date" id="record_date" name="record_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                                <div class="invalid-feedback">กรุณาระบุ วันที่บันทึก</div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="time_period" class="form-label">ช่วงเวลาที่บันทึก (รอบเวลา) <span class="text-danger">*</span></label>
                                <select id="time_period" name="time_period" class="form-select" required>
                                    <option value="" disabled>-- เลือกรอบเวลาที่บันทึก --</option>
                                    <option value="08:00-12:00">รอบเช้า (08.00 - 12.00 น.)</option>
                                    <option value="12:00-16:00">รอบบ่าย (12.00 - 16.00 น.)</option>
                                    <option value="16:00-20:00">รอบเย็น (16.00 - 20.00 น.)</option>
                                </select>
                                <div class="invalid-feedback">กรุณาเลือกรอบเวลาที่บันทึก</div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="record_time" class="form-label">เวลาที่บันทึกจริง <span class="text-danger">*</span></label>
                                <input type="time" id="record_time" name="record_time" class="form-control" required>
                                <div class="invalid-feedback" id="time_error">
                                    กรุณาระบุเวลาให้อยู่ในช่วงที่เลือก
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-6">
                                <label for="temp_value" class="form-label">ค่าที่อ่านได้ (°C) <span class="text-danger">*</span></label>
                                <input type="text"
                                    inputmode="decimal"
                                    id="temp_value"
                                    name="temp_value"
                                    class="form-control"
                                    placeholder="เช่น 25.5 หรือ -5.0"
                                    required
                                    oninput="this.value = this.value.replace(/[^0-9.-]/g, '').replace(/(\..*?)\..*/g, '$1').replace(/(?!^)-/g, '');">
                                <div class="invalid-feedback">กรุณาระบุ ค่าอุณหภูมิ</div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-6">
                                <label for="status" class="form-label">สถานะ <span class="text-danger">*</span></label>
                                <select id="status" name="status" class="form-select" required>
                                    <option value="" disabled selected>-- เลือกสถานะอุณหภูมิ --</option>
                                    <option value="ปกติ">ปกติ</option>
                                    <option value="ผิดปกติ">ผิดปกติ</option>
                                </select>
                                <div class="invalid-feedback">กรุณาเลือกสถานะ</div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-12">
                                <label for="recorded_by" class="form-label">ผู้บันทึก <span class="text-danger">*</span></label>
                                <select id="recorded_by" name="recorded_by" class="form-select select2-user" data-placeholder="-- ค้นหาชื่อผู้บันทึก --" required>
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาเลือกผู้บันทึก</div>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-end bg-white border-top shadow-sm">
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="temperatureRecordForm">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal สำหรับดู list รายการควบคุมอุณหภูมิทั้งหมดของเครื่องวัดนั้น ๆ โดยให้เลือกประจำเดือน / ปี มาแล้วจะแสดงตารางให้  -->
<div class="modal fade" id="viewHistoryModal" tabindex="-1" aria-labelledby="viewHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width: 90%;">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="viewHistoryModalLabel">
                    <i class="fas fa-folder-open fa-lg me-2"></i> แฟ้มประวัติการควบคุมอุณหภูมิรายเดือน
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-light">
                <div class="card border-0 shadow-sm mb-4 border-start border-primary border-4 rounded-3">
                    <div class="card-body bg-white">
                        <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                            <i class="fas fa-info-circle fa-lg text-primary me-2"></i>
                            <h6 class="mb-0 fw-bold text-primary">ข้อมูลอ้างอิงเครื่องวัดอุณหภูมิ</h6>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">หน่วยงาน</span>
                                <span class="fw-bold" id="hist_dept_name">-</span>
                            </div>
                            <div class="col-12 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">หมายเลขเครื่อง</span>
                                <span class="fw-bold" id="hist_device_code">-</span>
                            </div>
                            <div class="col-12">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">สถานที่ใช้งาน</span>
                                <span class="fw-bold" id="hist_location_use">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ช่วงการใช้งาน (°C)</span>
                                <span class="fw-bold" id="hist_device_range">-</span>
                            </div>
                            <div class="col-6 col-lg-6">
                                <span class="d-block text-muted" style="font-size: 0.75rem;">ค่าความไม่แน่นอนของการวัด (°C)</span>
                                <span class="fw-bold" id="hist_uncertainty">-</span>
                            </div>

                            <div class="col-12">
                                <h6 class="text-primary fw-bold border-bottom pb-2 mb-3 mt-3">
                                    <i class="fas fa-users fa-lg me-1"></i> รายชื่อผู้รับผิดชอบ
                                </h6>
                                <div class="row">
                                    <div class="col-6">
                                        <span class="d-block text-muted" style="font-size: 0.75rem;">ผู้รับผิดชอบคนที่ 1</span>
                                        <span class="fw-bold" id="hist_responsible_1">-</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="d-block text-muted" style="font-size: 0.75rem;">ผู้รับผิดชอบคนที่ 2</span>
                                        <span class="fw-bold" id="hist_responsible_2">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border mb-3 bg-light">
                    <div class="card-body p-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-4">
                                <label for="filter_history_year" class="form-label text-muted small mb-1 fw-bold"><i class="far fa-calendar me-1"></i> ปี (พ.ศ.)</label>
                                <select id="filter_history_year" class="form-select form-select-sm">
                                    <?php
                                    $currentYear = date('Y') + 543;
                                    for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                        echo "<option value=\"$y\">$y</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="filter_history_month" class="form-label text-muted small mb-1 fw-bold"><i class="far fa-calendar-alt me-1"></i> เดือน</label>
                                <select id="filter_history_month" class="form-select form-select-sm">
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

                            <div class="col-12 col-md-4">
                                <label for="filter_history_time" class="form-label text-muted small mb-1 fw-bold"><i class="far fa-clock me-1"></i> รอบเวลาที่บันทึก</label>
                                <select id="filter_history_time" class="form-select form-select-sm">
                                    <option value="08:00-12:00">เช้า (08.00 - 12.00 น.)</option>
                                    <option value="12:00-16:00">บ่าย (12.00 - 16.00 น.)</option>
                                    <option value="16:00-20:00">เย็น (16.00 - 20.00 น.)</option>
                                </select>
                            </div>

                            <div class="col-12 mt-4 pt-3 border-top d-flex flex-wrap gap-2 justify-content-center">
                                <button type="button" class="btn btn-primary btn-sm text-white fw-bold px-4 shadow-sm flex-fill flex-md-grow-0" id="btn_load_history">
                                    <i class="fas fa-search me-2"></i> ดูข้อมูล
                                </button>
                                <button type="button" class="btn btn-sm fw-bold px-4 shadow-sm flex-fill flex-md-grow-0 text-white" style="background-color: #6f42c1;" id="btn_print_history_pdf">
                                    <i class="fas fa-file-pdf me-2"></i> Export PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header py-3 px-4 ">
                        <h6 class="mb-1 fw-bold d-flex align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="fas fa-calendar-alt fa-lg me-2"></i> ตารางบันทึกประจำเดือน</h6>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 55vh;">
                            <table class="table table-bordered table-hover align-middle text-center mb-0" style="font-size: 0.9rem;">
                                <thead class="table-primary position-sticky top-0" style="z-index: 1;">
                                    <tr class="text-nowrap text-center">
                                        <th style="min-width: 100px;">Action</th>
                                        <th style="min-width: 100px;">วันที่</th>
                                        <th class="d-none d-sm-table-cell" style="min-width: 130px;">ช่วงเวลาที่บันทึก</th>
                                        <th style="min-width: 100px;">ค่าที่อ่านได้ (°C)</th>
                                        <th style="min-width: 90px;">สถานะ</th>
                                        <th class="d-none d-md-table-cell" style="min-width: 150px;">ผู้บันทึก</th>
                                    </tr>
                                </thead>
                                <tbody id="temperature_history_body">
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="fas fa-hand-pointer fa-2x mb-2 opacity-50"></i>
                                            <p class="mb-0">กรุณากดปุ่ม "ดูข้อมูล" เพื่อแสดงประวัติ</p>
                                        </td>
                                    </tr>
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
    // ตัวแปรเก็บ ID เครื่องวัดที่กำลังเปิดดูประวัติ
    let currentViewDeviceId = null;

    // ฟังก์ชันสำหรับตั้งค่าเวลาปัจจุบันและเลือกรอบให้อัตโนมัติ
    function setDefaultTimeAndPeriod() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const currentTime = `${hours}:${minutes}`;

        // 1. ตั้งค่าช่องเวลาเป็นเวลาปัจจุบัน
        $('#record_time').val(currentTime);

        // 2. วิเคราะห์ว่าตอนนี้อยู่รอบไหน
        let periodValue = "";
        const h = now.getHours();

        if (h >= 8 && h < 12) {
            periodValue = "08:00-12:00";
        } else if (h >= 12 && h < 16) {
            periodValue = "12:00-16:00";
        } else if (h >= 16 && h < 20) {
            periodValue = "16:00-20:00";
        }

        // 3. เลือกค่าใน Select และสั่งให้ทำงาน (trigger change) เพื่อเซ็ต min/max
        if (periodValue) {
            $('#time_period').val(periodValue).trigger('change');
        }
    }

    // ฟังก์ชันกลางสำหรับหาว่า ณ เวลานี้ คือรอบเวลาไหน
    function getCurrentPeriodValue() {
        const h = new Date().getHours();
        if (h >= 8 && h < 12) return "08:00-12:00";
        if (h >= 12 && h < 16) return "12:00-16:00";
        if (h >= 16 && h < 20) return "16:00-20:00";
        return "08:00-12:00"; // ค่า Default กรณีอยู่นอกช่วงเวลา (เช่น กลางคืน)
    }

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
                dropdownParent: $('#addRecordModal')
            });
        }

        $('#filter_history_year, #filter_history_month').on('change', function() {
            handleMonthFilter();
        });

        // เรียกครั้งแรก (Initial)
        handleMonthFilter();

        // ดักจับการเปลี่ยนรอบเวลาเพื่อ Update min/max
        $('#time_period').on('change', function() {
            const period = $(this).val();
            const timeInput = $('#record_time');

            if (period) {
                let [start, end] = period.split('-');
                // เนื่องจากเราปรับ value ใน HTML ให้เป็น HH:mm แล้ว จึงไม่ต้อง replace '.' อีก
                timeInput.attr('min', start);
                timeInput.attr('max', end);

                $('#time_error').text(`กรุณาระบุเวลาระหว่าง ${start} น. ถึง ${end} น.`);
            }
        });

        setDefaultTimeAndPeriod();
    });

    function handleMonthFilter() {
        const $yearSelect = $('#filter_history_year');
        const $monthSelect = $('#filter_history_month');

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
            // ดีดกลับมาที่เดือนปัจจุบันแทน
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

                    $('#recorded_by, #filter_responsible_id').html(options);

                    // เพิ่มบรรทัดนี้: สั่งให้ Select2 อัปเดต UI หลังจากยัด options เข้าไปแล้ว
                    $('#filter_responsible_id').trigger('change');
                    $('#recorded_by').trigger('change');
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

                    $('#filter_department_id').html(options);
                    // ให้ Select2 อัปเดต UI 
                    $('#filter_department_id').trigger('change');
                }
            }
        });
    }

    // ==========================================
    // 1. จัดการตารางหน้าหลัก (รายการเครื่องวัด + รายการบันทึกล่าสุด)
    // ==========================================
    function searchPage(page) {
        const formData = $('#searchFilterForm').serializeArray();
        formData.push({
            name: 'page',
            value: page
        });

        $.ajax({
            url: './api/Master_temperature_device/searchDataWithRecord.php',
            // ทำเหมือน equipment log คือใช้เป็น searchData ปกติที่ + JOIN เพิ่มส่วนของ transaction เพิ่มมา API ก็ไว้ folder Master temperature device เลย
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
                // จัดการค่าว่างและประกอบร่างข้อมูล
                let dept = row.department_name || '-';
                let code = row.device_code || '-';
                let range = (row.range_min && row.range_max) ? `${row.range_min} - ${row.range_max} °C` : '-';
                let loc = row.location_use || '-';
                let uncertainty = row.measurement_uncertainty ? `± ${row.measurement_uncertainty} °C` : '-';

                let responsible_display = '';
                if (row.resp1_name && row.resp2_name) {
                    responsible_display = `<div>1. ${row.resp1_name}</div><div>2. ${row.resp2_name}</div>`;
                } else if (row.resp1_name) {
                    responsible_display = row.resp1_name;
                } else {
                    responsible_display = '<span class="text-muted">-</span>';
                }

                let statusClass = '';
                if (row.last_status === 'ปกติ') {
                    statusClass = 'text-success';
                } else if (row.last_status === 'ผิดปกติ') {
                    statusClass = 'text-danger';
                } else {
                    statusClass = 'text-muted'; // กรณีอื่นๆ หรือค่าว่าง
                }

                // เช็คว่ามีประวัติล่าสุดไหม
                let lastDate = row.last_record_date ? row.last_record_date : '<span class="text-muted small">ยังไม่มีบันทึก</span>';
                let lastResult = row.last_temp_value ?
                    `<div class="fw-bold">${row.last_temp_value} °C</div>
                    <small class="${statusClass} fw-medium">(${row.last_status})</small>` :
                    '<span class="text-muted small">ไม่มีข้อมูล</span>';

                html += `
                        <tr class="clickable-row cursor-pointer" 
                            data-id="${row.id}" 
                            data-code="${code}" 
                            data-range="${range}" 
                            data-dept="${dept}" 
                            data-loc="${loc}" 
                            data-uncertainty="${uncertainty}"
                            data-resp1="${row.resp1_name || '-'}" 
                            data-resp2="${row.resp2_name || '-'}"
                            title="ดูข้อมูล">

                            <td class="text-center align-middle">
                                <div class="d-flex align-items-center justify-content-center" style="min-height: 100%;">
                                    <button type="button" 
                                            class="btn btn-sm ${window._canManage ? 'btn-success' : 'btn-secondary'} py-1 px-2 ${window._canManage ? 'js-add-record-btn' : ''}"
                                            data-id="${row.id}" 
                                            data-code="${code}" 
                                            data-range="${range}" 
                                            data-name="${row.device_name || '-'}" 
                                            data-loc="${loc}" 
                                            data-uncertainty="${uncertainty}"
                                            data-dept="${dept}"
                                            data-resp1="${row.resp1_name || '-'}" 
                                            data-resp2="${row.resp2_name || '-'}"
                                            ${window._canManage ? 'title="ลงบันทึกอุณหภูมิ"' : 'title="คุณไม่มีสิทธิ์จัดการข้อมูล" disabled'}>
                                        <i class="fas fa-circle-info me-1"></i> <span style="font-size: 0.8rem;">จัดการข้อมูล</span>
                                    </button>
                                </div>
                            </td>
                            <td class="text-center align-middle d-none d-xl-table-cell">${start_no + i}</td>
                            <td class="text-start align-middle">${code}</td>
                            <td class="text-start align-middle d-none d-xxl-table-cell">${dept}</td>
                            <td class="text-start align-middle">${loc}</td>
                            <td class="text-center align-middle d-none d-lg-table-cell">${range}</td>
                            <td class="text-start align-middle d-none d-lg-table-cell">
                                ${responsible_display}
                            </td>
                            <td class="text-center align-middle d-none d-xl-table-cell">${uncertainty}</td>
                            <td class="text-center align-middle d-none d-md-table-cell">${lastDate}</td>
                            <td class="text-center align-middle d-none d-md-table-cell">
                                ${lastResult}
                            </td>
                        </tr>
                    `;
            });
        } else {
            html = `
                <tr>
                    <td colspan="10" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-thermometer-empty fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0 fw-bold">ไม่พบรายการเครื่องวัดอุณหภูมิในระบบ</p>
                            <small class="opacity-75">ลองเปลี่ยนคำค้นหา  หรือกดปุ่ม <b class="text-dark">" เพิ่มรายการ "</b> ในหน้าเครื่องวัดอุณหภูมิ เพื่อสร้างข้อมูลใหม่</small>
                        </div>
                    </td>
                </tr>
            `;
        }
        $('#table_body').html(html);
    }

    // ตอนกด Submit ฟอร์มค้นหาครั้งแรก / โหลดหน้ามาแสดงครั้งแรก ให้เริ่มที่หน้า 1
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

    // ==========================================
    // 2. จัดการ Modal บันทึกอุณหภูมิ
    // ==========================================

    // A. เมื่อกดปุ่ม "บันทึก" (หน้ารวม)
    $(document).on('click', '.js-add-record-btn', function() {

        // 1. Reset ฟอร์มและเคลียร์สถานะ Validation
        const $form = $('#temperatureRecordForm');
        $form[0].reset();
        $form.removeClass('was-validated');
        $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');

        $('#record_id').val('');

        // เซ็ตวันที่ปัจจุบันกลับไปเป็นค่าเริ่มต้น
        document.getElementById('record_date').valueAsDate = new Date();

        // --- Auto Fill ผู้บันทึกที่ Login อยู่ ---
        const currentUserId = '<?php echo $current_user_id; ?>';
        const currentFullname = '<?php echo $current_user_fullname; ?>';

        if (currentUserId && currentFullname !== "") {
            // เช็คว่าใน Select2 มี Option นี้อยู่แล้วหรือไม่ ถ้าไม่มีให้ฉีด Option ใหม่เข้าไป
            if ($('#recorded_by').find("option[value='" + currentUserId + "']").length) {
                $('#recorded_by').val(currentUserId).trigger('change');
            } else {
                // สร้าง Option: new Option(text, value, defaultSelected, selected)
                let userOption = new Option(currentFullname, currentUserId, true, true);
                $('#recorded_by').append(userOption).trigger('change');
            }
        } else {
            $('#recorded_by').val('').trigger('change'); // ล้างค่าถ้าไม่มีข้อมูล
        }

        // รับค่าจาก data-* โยนลง Header ของ Modal
        $('#device_id').val($(this).data('id'));
        $('#display_device_code').text($(this).data('code'));
        $('#display_device_range').text($(this).data('range'));
        $('#display_dept_name').text($(this).data('dept'));
        $('#display_location_use').text($(this).data('loc'));
        $('#display_uncertainty').text($(this).data('uncertainty'));
        $('#display_responsible_1').text($(this).data('resp1'));
        $('#display_responsible_2').text($(this).data('resp2'));

        $('#textModal').text('บันทึกการควบคุมอุณหภูมิใหม่');
        $('#addRecordModalLabel').html('<i class="fas fa-thermometer-half fa-lg me-2"></i> <span id="textModal">บันทึกการควบคุมอุณหภูมิใหม่</span>');

        setDefaultTimeAndPeriod();

        $('#addRecordModal').modal('show');
    });

    $(document).on('click', '#btn_export_excel', function(e) {
        e.preventDefault(); // กันไว้ก่อนเผื่อปุ่มอยู่ใน Form submit

        // ดึงค่าจากฟอร์มทั้งหมดมาทำเป็น Query String
        const formData = $('#searchFilterForm').serialize();

        // ยิงไปที่ API
        window.location.href = './api/Transaction_temperature_record/exportExcel.php?' + formData;
    });

    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    // B. บันทึกข้อมูล (Submit Form)
    $('#temperatureRecordForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;

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
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(), // ใช้ serialize ธรรมดาก็พอสำหรับฟอร์มนี้
            dataType: 'json',
            beforeSend: function() {
                $('#btn_save_record').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> กำลังบันทึก...');
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
                    $('#addRecordModal').modal('hide');

                    // อัปเดตตาราง History ถ้าเปิดทิ้งไว้
                    if ($('#viewHistoryModal').is(':visible')) {
                        loadHistory(currentViewDeviceId);
                    }
                    searchPage(parseInt($('#pagination-list .active a').text()) || 1);
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                }
            },
            error: function(xhr) {
                Swal.fire('Server Error', 'เกิดข้อผิดพลาดที่ระบบ กรุณาติดต่อผู้ดูแล', 'error');
            },
            complete: function() {
                $('#btn_save_record').prop('disabled', false).html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล');
            }
        });
    });

    // ==========================================
    // 3. จัดการ Modal ดูประวัติรายเดือน
    // ==========================================

    $(document).on('click', '.clickable-row', function(e) {
        // ถ้าคลิกโดนปุ่ม "ลงบันทึก" ให้หยุดทำงาน ไม่ต้องเปิด Modal ประวัติ
        if ($(e.target).closest('.js-add-record-btn').length) {
            return;
        }

        // ดึงค่าจาก tr ที่คลิก
        const $row = $(this);
        currentViewDeviceId = $row.data('id');

        // ใส่ข้อมูล Header ใน Modal
        $('#hist_device_code').text($row.data('code'));
        $('#hist_device_range').text($row.data('range'));
        $('#hist_dept_name').text($row.data('dept'));
        $('#hist_location_use').text($row.data('loc'));
        $('#hist_uncertainty').text($row.data('uncertainty'));
        $('#hist_responsible_1').text($(this).data('resp1'));
        $('#hist_responsible_2').text($(this).data('resp2'));

        // เซ็ต Dropdown เป็นเดือนและปีปัจจุบัน
        let d = new Date();
        $('#filter_history_month').val(d.getMonth() + 1);
        $('#filter_history_year').val(d.getFullYear() + 543);

        const currentPeriod = getCurrentPeriodValue();
        $('#filter_history_time').val(currentPeriod);

        handleMonthFilter()

        // สั่งโหลดตารางทันที
        loadHistory(currentViewDeviceId);

        $('#viewHistoryModal').modal('show');
    });

    // D. เมื่อกดปุ่ม "ดูข้อมูล" (ใน Modal History)
    $('#btn_load_history').on('click', function() {
        if (currentViewDeviceId) {
            loadHistory(currentViewDeviceId);
        }
    });

    function loadHistory(deviceId) {
        let month = $('#filter_history_month').val();
        let year_th = $('#filter_history_year').val();
        let year_en = parseInt(year_th) - 543;
        let time_period = $('#filter_history_time').val(); // รับค่ารอบเวลา
        $('#temperature_history_body').html(`
            <tr>
                <td colspan="6" class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-info mb-2"></i>
                    <p>กำลังโหลดข้อมูล...</p>
                </td>
            </tr>
        `);

        $.ajax({
            url: './api/Transaction_temperature_record/getHistory.php',
            type: 'GET',
            data: {
                device_id: deviceId,
                month: month,
                year: year_en,
                time_period: time_period
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let html = '';
                    if (res.data && res.data.length > 0) {
                        res.data.forEach(log => {
                            // เปลี่ยนสีตามสถานะ
                            let statusBadge = log.status === 'ปกติ' ?
                                `<span class="badge bg-success bg-opacity-25 text-success border border-success">${log.status}</span>` :
                                `<span class="badge bg-danger bg-opacity-25 text-danger border border-danger">${log.status}</span>`;

                            html += `
                                <tr>
                                    <td class="text-center align-middle">
                                        <div class="d-flex justify-content-center gap-2">
                                            <i class="fas fa-pen-to-square fa-lg text-warning ${window._canManage ? 'js-edit-record-btn cursor-pointer' : ''}" 
                                            data-log='${JSON.stringify(log)}' 
                                            title="${window._canManage ? 'แก้ไข' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                                            
                                            <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-record-btn cursor-pointer' : ''}" 
                                            data-id="${log.id}" 
                                            data-date="${log.record_date_show}" 
                                            data-time="${log.time_period || '-'}"
                                            data-actual-time="${log.record_time_show || '-'}"
                                            data-temp="${log.temp_value}"
                                            title="${window._canManage ? 'ลบ' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">${log.record_date_show}</td>
                                    <td class="text-center align-middle d-none d-sm-table-cell">
                                        <span class="fw-bold">${log.record_time_show} น.</span><br>
                                        <small class="text-muted">(${log.time_period})</small>
                                    </td>
                                    <td class="text-center align-middle">${log.temp_value}</td>
                                    <td class="text-center align-middle">${statusBadge}</td>
                                    <td class="text-start align-middle d-none d-md-table-cell">
                                        ${log.recorded_by_name || '-'}
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        html = `<tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="fas fa-folder-open fa-3x mb-3 opacity-50"></i>
                                        <p class="mb-1 fw-bold">ไม่มีข้อมูลการบันทึกในเดือน / ปี / ช่วงเวลาที่เลือก</p>
                                        <small class="opacity-75">ลองเปลี่ยนเงื่อนไขในการค้นหาข้างต้น หรือไปเพิ่มรายการใหม่ได้ผ่านปุ่ม <b class="mx-2"><i class="fas fa-circle-info me-1"></i>จัดการข้อมูล</b> ในตารางหลัก</small>
                                    </td>
                                </tr>`;
                    }
                    $('#temperature_history_body').html(html);
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    }

    $('#btn_print_history_pdf').on('click', function() {
        if (!currentViewDeviceId) return;

        // ดึงค่าจาก Dropdown ปัจจุบันที่ผู้ใช้เลือกอยู่
        let month = $('#filter_history_month').val();
        let year_th = $('#filter_history_year').val();
        let year_en = parseInt(year_th) - 543;
        let time_period = $('#filter_history_time').val(); // สำคัญ! ต้องส่งไปลงหัวกระดาษ

        let deviceName = $('#hist_device_code').text().trim() || currentViewDeviceId;

        // 2. ทำความสะอาดชื่อไฟล์
        let safeTime = time_period.replace(/:/g, '.').replace(/\s+/g, '');
        let safeDevice = deviceName.replace(/[/\\?%*:|"<>]/g, '-');

        let fileName = `F-CS-19_${safeDevice}_${month}-${year_th}_(${safeTime}).pdf`;

        // สร้าง URL ส่งไปหา gen_pdf.php
        let url = `./api/Transaction_temperature_record/gen_pdf.php/${fileName}?device_id=${currentViewDeviceId}&month=${month}&year=${year_en}&time_period=${encodeURIComponent(time_period)}`;

        // เปิดแท็บใหม่เพื่อ Gen PDF
        window.open(url, '_blank');
    });

    // E. กดปุ่ม แก้ไขประวัติ (ในตารางรายเดือน)
    $(document).on('click', '.js-edit-record-btn', function() {
        let log = $(this).data('log');

        $('#temperatureRecordForm')[0].reset();
        $('#temperatureRecordForm').removeClass('was-validated');

        // โยนข้อมูลลง Form
        $('#device_id').val(log.device_id);
        $('#record_id').val(log.id);
        $('#record_date').val(log.record_date); // ต้องเป็น YYYY-MM-DD
        $('#temp_value').val(log.temp_value);
        $('#status').val(log.status);
        $('#time_period').val(log.time_period).trigger('change');
        $('#record_time').val(log.record_time);

        // อัปเดต Select2
        if ($('#recorded_by').find("option[value='" + log.recorded_by + "']").length) {
            $('#recorded_by').val(log.recorded_by).trigger('change');
        } else {
            // ถ้าใช้ AJAX Select2 อาจจะต้องสร้าง Option ก่อนค่อย trigger
            var newOption = new Option(log.recorded_by_name, log.recorded_by, true, true);
            $('#recorded_by').append(newOption).trigger('change');
        }

        // ดึง Header จากหน้า History มาใส่หน้า Add เพื่อความต่อเนื่อง
        $('#display_device_code').text($('#hist_device_code').text());
        $('#display_device_range').text($('#hist_device_range').text());
        $('#display_dept_name').text($('#hist_dept_name').text());
        $('#display_location_use').text($('#hist_location_use').text());
        $('#display_uncertainty').text($('#hist_uncertainty').text());
        $('#display_responsible_1').text($('#hist_responsible_1').text());
        $('#display_responsible_2').text($('#hist_responsible_2').text());

        $('#textModal').text('แก้ไขบันทึกการควบคุมอุณหภูมิ');
        $('#addRecordModalLabel').html('<i class="fas fa-edit fa-lg me-2"></i> <span id="textModal">แก้ไขบันทึกการควบคุมอุณหภูมิ</span>');

        $('#viewHistoryModal').modal('hide');
        setTimeout(() => $('#addRecordModal').modal('show'), 400);
    });

    // F. กดปุ่ม ลบประวัติ
    $(document).on('click', '.js-delete-record-btn', function(e) {
        e.preventDefault();
        const logId = $(this).data('id');
        const logDate = $(this).data('date');
        const logTime = $(this).data('time');
        const logActualTime = $(this).data('actual-time');
        const logTemp = $(this).data('temp');

        if (!logId) {
            Swal.fire('Error', 'ไม่พบ ID ของรายการ', 'error');
            return;
        }

        Swal.fire({
            title: 'ยืนยันการลบ?',
            html: `
                <div class="text-center mb-3">คุณต้องการลบข้อมูลการบันทึกนี้ใช่หรือไม่?</div>
                <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">วันที่บันทึก:</span><br>
                        <b class="ps-2 text-danger">${logDate}</b>
                    </div>
                    
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">รอบเวลาที่บันทึก:</span><br>
                        <b class="ps-2 text-danger">${logActualTime} น.</b>
                        <b class="ps-1 text-danger">( ${logTime} น. )</b> 
                    </div>
                    
                    <div>
                        <span class="text-secondary small">ค่าที่บันทึกได้:</span><br>
                        <b class="ps-2 text-danger">${logTemp} °C</b>
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
                    url: './api/Transaction_temperature_record/delete.php',
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
                Swal.fire({
                    title: 'ลบข้อมูลสำเร็จ!',
                    text: 'บันทึกการควบคุมอุณหภูมิถูกลบเรียบร้อยแล้ว',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    loadHistory(currentViewDeviceId); // โหลดตารางเดือนนั้นใหม่
                    const currentPage = parseInt($('#pagination-list .active a').text()) || 1;
                    searchPage(currentPage);
                });
            }
        });
    });
</script>
<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>