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

$title = "แผนการซ่อมบำรุง/สอบเทียบประจำปี";

ob_start();
?>

<style>
    .js-edit-plan-btn,
    .js-delete-plan-btn {
        padding: 12px;
        /* ขยายพื้นที่จิ้มออกไปรอบๆ */
        margin: -12px;
        /* ดึงขอบกลับมาเพื่อไม่ให้กระทบตำแหน่งในตาราง */
        cursor: pointer;
        touch-action: manipulation;
        /* ป้องกันการซูมเวลาเผลอ Double Tap */
    }

    /* ปรับแต่ง Checkbox ให้จิ้มง่ายบน iPad */
    .plan-check {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }

    /* ปรับแต่งเดือนให้แคบลงแต่ยังอ่านออก */
    .th-month {
        width: 45px;
        min-width: 45px;
        font-size: 0.75rem;
        padding: 8px 2px !important;
        white-space: nowrap;
    }

    /* --- 1. ส่วนเนื้อหา (Tbody TD) --- */
    .body-sticky-1,
    .body-sticky-2 {
        position: sticky !important;
        background-color: #fff !important;
        z-index: 1;
        top: auto !important;
        /* ต่ำสุด */
    }

    .body-sticky-1 {
        left: -0.5px !important;
        /* ขยับออกมาทับขอบซ้ายสุดเล็กน้อย */
    }

    .body-sticky-2 {
        left: 59.5px !important;
    }

    /* --- 3. ส่วนหัวที่เป็นจุดตัด (Corner Cells) --- */
    /* ช่อง ลำดับ และ รายการเครื่องมือ ในส่วนหัว */
    .header-sticky-all-1,
    .header-sticky-all-2 {
        position: sticky !important;
        top: 0 !important;
        /* ค้างแนวตั้ง */
        z-index: 20 !important;
        /* สูงที่สุด เพื่อทับทั้งแนวตั้งและแนวนอน */
        background-color: #cfe2ff !important;
    }

    .header-sticky-all-1 {
        left: -0.5px !important;
    }

    .header-sticky-all-2 {
        left: 59.5px !important;
    }

    .body-sticky-1,
    .header-sticky-all-1,
    .body-sticky-2,
    .header-sticky-all-2 {
        position: sticky !important;
        overflow: visible !important;
        /* สำคัญ: เพื่อให้เส้นที่วาดโผล่ออกมาเห็นชัด */
    }

    /* --- เส้นคั่นที่ 1: ระหว่าง 'ลำดับ' กับ 'รายการเครื่องมือ' --- */
    .body-sticky-1::after {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 0.5px;
        /* เส้นบางปกติ */
        height: 100%;
        background-color: #dee2e6;
        /* สีเทามาตรฐาน */
        z-index: 10;
    }

    /* --- เส้นคั่นที่ 1: ระหว่าง 'ลำดับ' กับ 'รายการเครื่องมือ' --- */
    .header-sticky-all-1::after {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 0.5px;
        /* ปกติ Bootstrap ใช้ 1px */
        height: 100%;

        /* ใช้สีนี้เพื่อให้เข้ากับทุก Theme ของ Bootstrap */
        background-color: rgba(0, 0, 0, 0.03) !important;
        z-index: 10;
    }

    /* --- เส้นคั่นที่ 2: หลัง 'รายการเครื่องมือ' (เส้นแบ่งโซนหลัก) --- */
    .body-sticky-2::after,
    .header-sticky-all-2::after {
        content: "";
        position: absolute;
        top: 0;
        right: -5px;
        /* ดันเงาออกไปทางขวาของปุ่ม */
        width: 5px;
        /* ความกว้างของรัศมีเงา */
        height: 100%;

        /* ใช้ Gradient ไล่สีเพื่อให้ดูเหมือนเงาฟุ้ง */
        background: linear-gradient(to right, rgba(0, 0, 0, 0.12), rgba(0, 0, 0, 0));

        pointer-events: none;
        /* เพื่อให้นิ้วยังจิ้มทะลุเงาไปกด Checkbox ได้ */
    }

    /* กรณีต้องการเงาจางๆ เสริมให้ดูมีมิติเวลาเลื่อน (Optional) */
    .body-sticky-2::before,
    .header-sticky-all-2::before {
        content: "";
        position: absolute;
        top: 0;
        right: -10px;
        width: 10px;
        height: 100%;
        background: linear-gradient(to right, rgba(0, 0, 0, 0.05), rgba(0, 0, 0, 0));
        pointer-events: none;
    }

    /* จัดการเส้นขอบล่างของช่อง "เดือน" (colspan 12) */
    .th-month-parent {
        position: sticky !important;
        top: 0 !important;
        z-index: 10 !important;
        background-color: #cfe2ff !important;
    }

    /* แถวแรก (Serial No, ยี่ห้อ ฯลฯ) */
    .header-sticky-row {
        position: sticky !important;
        top: 0 !important;
        z-index: 10 !important;
        background-color: #cfe2ff !important;
    }

    /* แถวที่ 2 (ม.ค. - ธ.ค.) */
    .header-sticky-row-2 {
        position: sticky !important;
        /* ขยับลงมาตามความสูงของแถวแรก ลองปรับตัวเลขนี้ให้พอดีกับหน้าจอคุณ (มักจะอยู่ระหว่าง 35-45px) */
        top: 30px !important;
        z-index: 10 !important;
        background-color: #cfe2ff !important;
        /* เส้นขอบล่าง */
    }

    /* แถวที่ 2 (ม.ค. - ธ.ค.) */
    .header-sticky-row-2-modal-detail {
        position: sticky !important;
        /* ขยับลงมาตามความสูงของแถวแรก ลองปรับตัวเลขนี้ให้พอดีกับหน้าจอคุณ (มักจะอยู่ระหว่าง 35-45px) */
        top: 38px !important;
        z-index: 10 !important;
        background-color: #cfe2ff !important;
        /* เส้นขอบล่าง */
    }

    /* ขยายขนาด Checkbox ให้ใหญ่ขึ้น */
    .custom-check-lg {
        width: 1.4em !important;
        height: 1.4em !important;
        cursor: pointer;
    }

    /* เพิ่ม Hitbox: ทำให้เมื่อเอาเมาส์วางในช่อง <td> แล้วดูเหมือนกดได้ทั้งช่อง */
    .clickable-cell:hover {
        background-color: rgba(0, 123, 255, 0.05);
        /* ไฮไลท์สีฟ้าอ่อนๆ เมื่อเอาเมาส์วาง */
    }

    /* ปรับแต่งสำหรับ iPad/Touch Device ให้กดง่ายขึ้น */
    @media (max-width: 1024px) {
        .custom-check-lg {
            width: 1.6em !important;
            /* บน iPad ปรับให้ใหญ่ขึ้นอีกนิด */
            height: 1.6em !important;
        }

        .clickable-cell div {
            padding-top: 15px !important;
            /* เพิ่มพื้นที่แนวตั้ง */
            padding-bottom: 15px !important;
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

                        <div class="col-12 col-sm-6 <?= ($user_role !== 'admin') ? 'col-md-4 col-lg-4 col-xl-4' : 'col-md-6 col-lg-2 col-xl-2 ' ?>">
                            <button class="btn <?= $canManage ? 'btn-success' : 'btn-secondary' ?> btn-sm px-4 fw-bold shadow-sm text-nowrap w-100 <?= $canManage ? 'js-add-plan-btn' : '' ?>"
                                style="height: 31px;"
                                type="button"
                                <?= $canManage ? 'data-bs-toggle="modal" data-bs-target="#addEditPlanModal"' : 'disabled title="คุณไม่มีสิทธิ์จัดการข้อมูล"' ?>>
                                <i class="fas fa-plus me-2"></i>เพิ่มรายการ
                            </button>
                        </div>

                        <div class="col-12 <?= ($user_role !== 'admin') ? 'col-md-4 col-lg-4 col-xl-4' : 'col-md-6 col-lg-2 col-xl-1 ' ?>">
                            <label for="filter_plan_year" class="form-label text-muted small mb-1">ปีแผนงาน</label>
                            <select id="filter_plan_year" name="filter_plan_year" class="form-select form-select-sm">
                                <option value="">-- ทั้งหมด --</option>
                                <?php
                                $currentYear = date('Y') + 543;
                                for ($i = $currentYear - 2; $i <= $currentYear + 2; $i++) {
                                    // แสดงตัวเลือกเป็นปีปัจจุบัน, หน้า-หลัง + 2 และ กำหนดให้ปีปัจจุบันถูกเลือกเป็น Default
                                    $selected = ($i == $currentYear) ? 'selected' : '';
                                    echo "<option value=\"$i\" $selected>$i</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 <?= ($user_role !== 'admin') ? 'col-md-4 col-lg-4 col-xl-4' : 'col-md-6 col-lg-4 col-xl-4' ?>">
                            <label for="filter_group_name" class="form-label text-muted small mb-1">กลุ่มงาน</label>
                            <input type="text" id="filter_group_name" name="filter_group_name"
                                class="form-control form-control-sm" placeholder="ระบุกลุ่มงาน...">
                        </div>

                        <?php if ($user_role === 'admin'): ?>
                            <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-5">
                                <label for="filter_department_id" class="form-label text-muted small mb-1">หน่วยงาน</label>
                                <select id="filter_department_id" name="filter_department_id" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                    <option value=""></option>
                                </select>
                            </div>
                        <?php endif; ?>

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
                    <th style="min-width: 80px;">ปีแผนงาน</th>
                    <th style="min-width: 180px;">กลุ่มงาน</th>
                    <th class="d-none d-md-table-cell" style="min-width: 180px;">หน่วยงาน</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 180px;">ผู้สร้าง</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 140px;">วันที่แก้ไขล่าสุด</th>
                    <th class="d-none d-md-table-cell" style="min-width: 100px;">ส่งออกรายงาน</th>
                </tr>
            </thead>
            <tbody id="table_body" style="font-size: 14px;">
                <!-- ข้อมูลจะดึงจาก API มาแสดงในส่วนของ renderTable -->
            </tbody>
        </table>
    </div>
</div>

<div class="row col-md-12 d-flex justify-content-center mt-3">
    <nav aria-label="Page navigation mt-3">
        <ul id="pagination-list" class="pagination justify-content-center"></ul>
    </nav>
</div>

<!-- Modal สำหรับเพิ่มรายการการซ่อมบำรุง / สอบเทียบเครื่องมือประจำปี + แก้ไข -->
<div class="modal fade" id="addEditPlanModal" aria-labelledby="addEditPlanModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addEditPlanModalLabel">
                    <i class="fas fa-calendar-alt fa-lg me-2"></i> <span id="textModal">สร้างแผนการซ่อมบำรุง/สอบเทียบ</span>
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" id="planForm" action="./api/Transaction_maintenance_plan/save.php" novalidate>
                    <input type="hidden" id="plan_header_id" name="id">

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header"><i class="fas fa-info-circle me-1"></i> ข้อมูลแผนประจำปี</legend>
                        <div class="row g-3 justify-content-start">

                            <div class="col-12 col-md-4 col-lg-3 <?= ($user_role !== 'admin') ? 'col-xl-4' : 'col-xl-2' ?>">
                                <label for="plan_year" class="form-label">ปีแผนงาน <span class="text-danger">*</span></label>
                                <select id="plan_year" name="plan_year" class="form-select" required>
                                    <option value="" disabled>-- เลือกปี --</option>
                                    <?php
                                    $currentYear = date('Y') + 543;
                                    // วนลูปปีถอยหลัง 2 ปี และล่วงหน้า 5 ปี (เพื่อให้วางแผนล่วงหน้าได้)
                                    for ($i = $currentYear - 2; $i <= $currentYear + 5; $i++) {
                                        // เช็คว่าถ้าเป็นปีปัจจุบัน ให้เป็นค่า Default (selected)
                                        $selected = ($i == $currentYear) ? 'selected' : '';
                                        echo "<option value=\"$i\" $selected>$i</option>";
                                    }
                                    ?>
                                </select>
                                <div class="invalid-feedback">กรุณาเลือกปีแผนงาน</div>
                            </div>

                            <div class="col-12 col-md-8 col-lg-9  <?= ($user_role !== 'admin') ? 'col-xl-8' : 'col-xl-5' ?>">
                                <label class="form-label">กลุ่มงาน <span class="text-danger">*</span></label>
                                <div class="input-group input-group-seamless">
                                    <input
                                        type="text"
                                        id="group_name"
                                        name="group_name"
                                        class="form-control"
                                        placeholder="เช่น กลุ่มงานพิสูจน์หลักฐาน, กลุ่มงานตรวจทางเคมีฟิสิกส์"
                                        required>
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="group_name"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุกลุ่มงาน</div>
                            </div>

                            <div class="col-12 col-md-12 col-lg-12 col-xl-5 <?= ($user_role !== 'admin') ? 'd-none' : '' ?>">
                                <label for="department_id" class="form-label">หน่วยงาน <span class="text-danger">*</span></label>
                                <select id="department_id" name="department_id" class="form-select select2-department" data-placeholder="-- ค้นหาหน่วยงาน --" <?= ($user_role === 'admin') ? 'required' : '' ?>>
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาระบุหน่วยงาน</div>
                            </div>

                        </div>
                    </fieldset>

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border mt-4">
                        <legend class="fieldset-header"><i class="fas fa-list me-1"></i> รายการเครื่องมือและแผนรายเดือน</legend>

                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 50vh;">
                                    <table class="table table-bordered table-hover table-maintenance-plan align-middle mb-0 text-center" style="font-size: 0.85rem;">
                                        <thead class="table-primary position-sticky top-0" style="z-index: 10;">
                                            <tr>
                                                <th rowspan="2" class="align-middle header-sticky-all-1" style="min-width: 60px;">ลำดับ</th>
                                                <th rowspan="2" class="align-middle header-sticky-all-2" style="min-width: 290px;">รายการเครื่องมือ / เลขครุภัณฑ์</th>
                                                <th rowspan="2" class="align-middle header-sticky-row" style="min-width: 150px;">Serial No.</th>
                                                <th rowspan="2" class="align-middle header-sticky-row" style="min-width: 150px;">ยี่ห้อ / รุ่น</th>
                                                <th colspan="12" class="th-month-parent">เดือน</th>
                                                <th rowspan="2" class="align-middle header-sticky-row" style="min-width: 220px;">ผู้รับผิดชอบ</th>
                                                <th rowspan="2" class="align-middle header-sticky-row" style="min-width: 150px;">หมายเหตุ</th>
                                            </tr>
                                            <tr>
                                                <th class="header-sticky-row-2" style="min-width:50px;">ม.ค.</th>
                                                <th class="header-sticky-row-2" style="min-width:50px;">ก.พ.</th>
                                                <th class="header-sticky-row-2" style="min-width:50px;">มี.ค.</th>
                                                <th class="header-sticky-row-2" style="min-width:50px;">เม.ย.</th>
                                                <th class="header-sticky-row-2" style="min-width:50px;">พ.ค.</th>
                                                <th class="header-sticky-row-2" style="min-width:50px;">มิ.ย.</th>
                                                <th class="header-sticky-row-2" style="min-width:50px;">ก.ค.</th>
                                                <th class="header-sticky-row-2" style="min-width:50px;">ส.ค.</th>
                                                <th class="header-sticky-row-2" style="min-width:50px;">ก.ย.</th>
                                                <th class="header-sticky-row-2" style="min-width:50px;">ต.ค.</th>
                                                <th class="header-sticky-row-2" style="min-width:50px;">พ.ย.</th>
                                                <th class="header-sticky-row-2" style="min-width:50px;">ธ.ค.</th>
                                            </tr>
                                        </thead>
                                        <tbody id="plan_detail_body">
                                            <tr>
                                                <td colspan="18" class="py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>กำลังโหลดรายการเครื่องมือ...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-end bg-white border-top shadow-sm">
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="planForm">
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
<div class="modal fade" id="viewPlanModal" tabindex="-1" aria-labelledby="viewPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="viewPlanModalLabel">
                    <i class="fas fa-eye fa-lg me-2"></i> รายละเอียดแผนการซ่อมบำรุงประจำปี
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body bg-light p-4">
                <div class="card border-0 shadow-sm border-start border-primary border-4 rounded-3 mb-4">
                    <div class="card-body">
                        <h6 class="text-primary fw-bold mb-3 border-bottom pb-2"><i class="fas fa-info-circle fa-lg me-1"></i> ข้อมูลแผนประจำปี</h6>
                        <div class="row g-3">
                            <div class="col-6 col-md-3 col-lg-2">
                                <label class="text-muted small d-block mb-1">ปีแผนงาน</label>
                                <span id="view_plan_year" class="fw-bold">-</span>
                            </div>
                            <div class="col-6 col-md-9 col-lg-5">
                                <label class="text-muted small d-block mb-1">กลุ่มงาน</label>
                                <span id="view_group_name" class="fw-bold">-</span>
                            </div>
                            <div class="col-12 col-md-12 col-lg-5">
                                <label class="text-muted small d-block mb-1">หน่วยงาน</label>
                                <span id="view_department_name" class="fw-bold">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header py-3 px-4">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-list fa-lg me-2"></i> รายการเครื่องมือและแผนรายเดือน</h6>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 50vh;">
                            <table class="table table-bordered table-hover align-middle mb-0 text-center" style="font-size: 0.85rem;">
                                <thead class="table-primary" style="z-index: 10;">
                                    <tr>
                                        <th rowspan="2" class="align-middle header-sticky-all-1" style="min-width: 60px;">ลำดับ</th>
                                        <th rowspan="2" class="align-middle header-sticky-all-2" style="min-width: 290px;">รายการเครื่องมือ / เลขครุภัณฑ์</th>
                                        <th rowspan="2" class="align-middle header-sticky-row" style="min-width: 150px;">Serial No.</th>
                                        <th rowspan="2" class="align-middle header-sticky-row" style="min-width: 150px;">ยี่ห้อ / รุ่น</th>
                                        <th colspan="12" class="th-month-parent">เดือน (แผนซ่อมบำรุง)</th>
                                        <th rowspan="2" class="align-middle header-sticky-row" style="min-width: 220px;">ผู้รับผิดชอบ</th>
                                        <th rowspan="2" class="align-middle header-sticky-row" style="min-width: 150px;">หมายเหตุ</th>
                                    </tr>
                                    <tr>
                                        <th class="header-sticky-row-2-modal-detail">ม.ค.</th>
                                        <th class="header-sticky-row-2-modal-detail">ก.พ.</th>
                                        <th class="header-sticky-row-2-modal-detail">มี.ค.</th>
                                        <th class="header-sticky-row-2-modal-detail">เม.ย.</th>
                                        <th class="header-sticky-row-2-modal-detail">พ.ค.</th>
                                        <th class="header-sticky-row-2-modal-detail">มิ.ย.</th>
                                        <th class="header-sticky-row-2-modal-detail">ก.ค.</th>
                                        <th class="header-sticky-row-2-modal-detail">ส.ค.</th>
                                        <th class="header-sticky-row-2-modal-detail">ก.ย.</th>
                                        <th class="header-sticky-row-2-modal-detail">ต.ค.</th>
                                        <th class="header-sticky-row-2-modal-detail">พ.ย.</th>
                                        <th class="header-sticky-row-2-modal-detail">ธ.ค.</th>
                                    </tr>
                                </thead>
                                <tbody id="view_plan_detail_body">
                                    <td colspan="18" class="py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>กำลังโหลดรายการเครื่องมือ...</td>
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
    $(document).ready(function() {
        if ($('.select2-filter').length) {
            $('.select2-filter').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true
            }).next('.select2-container').addClass('select2-sm-custom');
        }

        if ($('.select2-department').length) {
            $('.select2-department').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                dropdownParent: $('#addEditPlanModal')
            });
        }

        if ($('#filter_department_id').length) {
            loadDepartments();
        }

        searchPage(1);

        // Event ดักจับเมื่อ Admin เปลี่ยนหน่วยงานใน Modal สร้าง/แก้ไขแผน
        $('#department_id').on('change', function() {
            const selectedDeptId = $(this).val();
            // เรียกฟังก์ชันโหลดตารางเครื่องมือตามหน่วยงานที่เลือก (ส่งค่า deptId ไปด้วย)
            loadEquipmentForPlan(selectedDeptId);
        });
    });

    // ==========================================
    // 1. จัดการตารางหน้าหลัก (แผนประจำปี)
    // ==========================================
    function loadDepartments() {
        $.ajax({
            url: 'api/get_departments.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let options = '<option value=""></option>';
                    // สร้าง String HTML สำหรับ Option ทั้งหมด
                    response.data.forEach(function(dept) {
                        options += `<option value="${dept.id}">${dept.department_name}</option>`;
                    });

                    // นำ Option ไปเติมใน Select ที่ต้องการ (อ้างอิงตาม ID ที่ตั้งไว้)
                    // ยัดข้อมูลลงทั้งใน Filter และ Modal
                    $('#filter_department_id, #department_id').html(options);

                    // อัปเดต UI ของ Select2
                    $('#filter_department_id, #department_id').trigger('change');
                }
            },
            error: function() {
                console.error("ไม่สามารถโหลดข้อมูลหน่วยงานได้");
            }
        });
    }

    function searchPage(page) {
        const formData = $('#searchFilterForm').serializeArray();
        formData.push({
            name: 'page',
            value: page
        });

        $.ajax({
            url: './api/Transaction_maintenance_plan/searchData.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.status == 'success') {
                    renderTable(res.data, res.offset);
                    $('#count_display').text(res.count);
                    const total = parseInt(res.totalPages);
                    const current = parseInt(res.currentPage);
                    if (total > 0) setupPagination(total, current);
                }
            }
        });
    }

    function renderTable(data, offset) {
        let html = '';
        let start_no = (isNaN(parseInt(offset))) ? 1 : parseInt(offset) + 1;

        if (data && data.length > 0) {
            data.forEach(function(row, i) {

                let hasData = !!row.id;
                let updateDate = row.updatedate || row.createdate || '-';

                // 2. กำหนด Style และ Class สำหรับปุ่ม Export PDF
                let btnStyle = hasData ?
                    'background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;' :
                    'box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); font-size: 11px; white-space: nowrap;';

                let btnClass = hasData ? '' : 'btn-secondary';

                // 3. สร้างปุ่ม Print พร้อม Wrapper
                let printBtnHtml = `
                <button type="button" 
                        class="btn ${btnClass} js-print-btn shadow-sm" 
                        style="${btnStyle}"
                        title="${hasData ? 'พิมพ์แผนการซ่อมบำรุง F-CS-20' : 'ยังไม่มีข้อมูล'}" 
                        data-id="${row.id}" 
                        data-year="${row.plan_year}" 
                        data-group="${row.group_name || '-'}"
                        data-department="${row.department_name || '-'}" 
                        ${hasData ? '' : 'disabled'}>
                        <i class="fas fa-file-pdf me-1"></i> Export PDF 
                    </button>`;

                html += `
                    <tr class="clickable-row cursor-pointer" data-id="${row.id}" title="ดูข้อมูล">
                        <td class="align-middle">
                            <div class="d-flex justify-content-center gap-2">
                                <i class="fas fa-pen-to-square fa-lg text-warning ${window._canManage ? 'js-edit-plan-btn cursor-pointer' : ''}" 
                                   data-id="${row.id}" 
                                   title="${window._canManage ? 'แก้ไข' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
 
                                <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-plan-btn cursor-pointer' : ''}" 
                                    data-id="${row.id}" 
                                    data-year="${row.plan_year}" 
                                    data-group="${row.group_name || '-'}"
                                    data-department="${row.department_name || '-'}"
                                    title="${window._canManage ? 'ลบ' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                            </div>
                        </td>
                        <td class="text-center align-middle d-none d-xl-table-cell">${start_no + i}</td>
                        <td class="text-center align-middle">${row.plan_year}</td> 
                        <td class="text-start align-middle">${row.group_name || '-'}</td>
                        <td class="text-start align-middle d-none d-md-table-cell">${row.department_name || '-'}</td>
                        <td class="text-start align-middle d-none d-lg-table-cell">${row.fullname_create || '-'}</td>
                        <td class="text-center align-middle d-none d-xl-table-cell">${updateDate}</td>
                        <td class="text-center align-middle d-none d-md-table-cell">
                            ${printBtnHtml}
                        </td>
                    </tr>
                `;
            });
        } else {
            html = `
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted">
                                <i class="fas fa-folder-open fa-3x mb-3 opacity-50"></i>
                                <p class="mb-0 fw-bold">ไม่พบข้อมูลแผนการซ่อมบำรุง/สอบเทียบในระบบ</p>
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
    // 2. จัดการ Modal สร้าง/แก้ไขแผน
    // ==========================================

    // ฟังก์ชันโหลดรายการเครื่องมือตามหน่วยงานที่เลือก (สำหรับ Admin) หรือตามค่าเริ่มต้น
    function loadEquipmentForPlan(deptId = '') {
        $('#plan_detail_body').html('<tr><td colspan="18" class="py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 text-muted">กำลังโหลดรายการเครื่องมือตามหน่วยงาน...</p></td></tr>');

        // ดึงค่า Header ID ปัจจุบัน (กรณีที่เป็นโหมดแก้ไข เพื่อให้มันดึงข้อมูลเครื่องมือ + ค่าที่เคยติ๊กไว้มาด้วย)
        let headerId = $('#plan_header_id').val() || 0;
        let planYear = $('#plan_year').val() || '';
        let groupName = $('#group_name').val() || '';

        $.ajax({
            url: './api/Transaction_maintenance_plan/getEquipmentChecklist.php',
            type: 'GET',
            data: {
                header_id: headerId,
                department_id: deptId,
                plan_year: planYear,
                group_name: groupName
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let html = '';
                    res.data.forEach(function(item, index) {

                        let chk = (m) => parseInt(item['m' + m]) === 1 ? 'checked' : '';
                        let respName = item.responsible_name ? item.responsible_name : '<span class="text-muted small italic">ไม่ระบุ</span>';

                        html += `
                            <tr>
                                <td class="body-sticky-1">${index + 1}</td> 
                                <td class="text-start text-break body-sticky-2">
                                    <input type="hidden" name="details[${index}][equipment_id]" value="${item.id}">
                                    <strong>${item.tool_name}</strong><br>
                                    <small class="text-muted">${item.asset_no || '-'}</small>
                                </td>
                                <td class="text-start">${item.serial_no || '-'}</td>
                                <td class="text-start">
                                    <strong>${item.brand || '-'}</strong><br>
                                    <small class="text-muted">${item.model || '-'}</small>
                                </td>
                        `;

                        // วนลูปวาด Checkbox 12 เดือน
                        for (let m = 1; m <= 12; m++) {
                            html += `
                                <td class="p-0 align-middle">
                                    <label class="d-flex align-items-center justify-content-center w-100 h-100 m-0 cursor-pointer" 
                                        style="min-height: 50px; cursor: pointer;">
                                        <input class="form-check-input custom-check-lg m-0" 
                                            type="checkbox" 
                                            name="details[${index}][m${m}]" 
                                            value="1" 
                                            ${chk(m)}>
                                    </label>
                                </td>
                            `;
                        }

                        // ช่องกรอกผู้รับผิดชอบ และ หมายเหตุ
                        html += `
                                <td class="text-start">${respName}</td>
                                <td>
                                    <div class="input-group input-group-seamless">
                                        <input 
                                            type="text" 
                                            class="form-control form-control-sm" 
                                            name="details[${index}][remark]" 
                                            value="${item.remark || ''}">
                                        <button type="button"
                                            class="btn btn-hw-open btn-hw-dynamic"
                                            title="เขียนด้วยลายมือ">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });

                    if (res.data.length === 0) {
                        html = '<tr><td colspan="18" class="py-4 text-muted"><i class="fas fa-info-circle me-1"></i>ไม่พบข้อมูลเครื่องมือในหน่วยงานที่เลือก</td></tr>';
                    }
                    $('#plan_detail_body').html(html);
                } else {
                    $('#plan_detail_body').html(`<tr><td colspan="18" class="py-4 text-danger">${res.message || 'ไม่สามารถโหลดข้อมูลได้'}</td></tr>`);
                }
            },
            error: function() {
                $('#plan_detail_body').html('<tr><td colspan="18" class="py-4 text-danger">เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์</td></tr>');
            }
        });
    }

    // โหลดรายชื่อเครื่องมือมาวาดตาราง Checkbox 12 เดือน
    function loadEquipmentChecklist(headerId = 0) {
        $('#plan_detail_body').html('<tr><td colspan="18" class="py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></td></tr>');

        let currentDeptId = $('#department_id').val() || ''; 
        let planYear = $('#plan_year').val() || '';
        let groupName = $('#group_name').val() || '';

        // ส่ง Header ID ไปด้วย เพื่อดูว่าเคยติ๊กอะไรไว้แล้วบ้าง (กรณี Edit)
        $.ajax({
            url: './api/Transaction_maintenance_plan/getEquipmentChecklist.php',
            type: 'GET',
            data: {
                header_id: headerId,
                department_id: currentDeptId,
                plan_year: planYear,
                group_name: groupName
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let html = '';
                    res.data.forEach(function(item, index) {

                        let hasData = !!item.id;
                        let chk = (m) => parseInt(item['m' + m]) === 1 ? 'checked' : '';

                        let respName = item.responsible_name ? item.responsible_name : '<span class="text-muted small italic">ไม่ระบุ</span>';

                        html += `
                            <tr>
                                <td class="body-sticky-1">${index + 1}</td> 
                                <td class="text-start text-break body-sticky-2">
                                    <input type="hidden" name="details[${index}][equipment_id]" value="${item.id}">
                                    <strong>${item.tool_name}</strong><br>
                                    <small class="text-muted">${item.asset_no || '-'}</small>
                                </td>
                                <td class="text-start">${item.serial_no || '-'}</td>
                                <td class="text-start">
                                    <strong>${item.brand || '-'}</strong><br>
                                    <small class="text-muted">${item.model || '-'}</small>
                                </td>
                        `;

                        // วนลูปวาด Checkbox 12 เดือน

                        for (let m = 1; m <= 12; m++) {
                            html += `
                                <td class="p-0 align-middle">
                                    <label class="d-flex align-items-center justify-content-center w-100 h-100 m-0 cursor-pointer" 
                                        style="min-height: 50px; cursor: pointer;">
                                        <input class="form-check-input custom-check-lg m-0" 
                                            type="checkbox" 
                                            name="details[${index}][m${m}]" 
                                            value="1" 
                                            ${chk(m)}>
                                    </label>
                                </td>
                            `;
                        }

                        // ช่องกรอกผู้รับผิดชอบ และ หมายเหตุ
                        html += `
                            <td class="text-start">${respName}</td>
                            <td>
                                <div class="input-group input-group-seamless">
                                    <input 
                                    type="text" 
                                    class="form-control form-control-sm" 
                                    name="details[${index}][remark]" 
                                    value="${item.remark || ''}">
                                    <button type="button"
                                        class="btn btn-hw-open btn-hw-dynamic"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </td>
                            </tr>
                        `;
                    });
                    if (res.data.length === 0) {
                        html = '<tr><td colspan="18" class="py-4 text-muted">ไม่พบข้อมูลเครื่องมือในระบบ</td></tr>';
                    }
                    $('#plan_detail_body').html(html);
                }
            }
        });
    }

    // เคลียร์ค่าฟอร์มและ Select2 เมื่อปิด Modal (Add/Edit)
    $('#addEditPlanModal').on('hidden.bs.modal', function() {
        // ล้างค่า Select2 ให้กลับเป็นค่าว่าง (Placeholder)
        $('#department_id').val('').trigger('change.select2');
    });

    // เปิด Modal (เพิ่มใหม่)
    $(document).on('click', '.js-add-plan-btn', function() {
        $('#planForm')[0].reset();
        $('#planForm').removeClass('was-validated');
        $('#plan_header_id').val('');
        $('#textModal').text('สร้างแผนการซ่อมบำรุง/สอบเทียบ');
        $('#addEditPlanModalLabel').html('<i class="fas fa-calendar-alt fa-lg me-2"></i> <span id="textModal">สร้างแผนการซ่อมบำรุง/สอบเทียบ</span>');

        loadEquipmentChecklist(0); // โหลดตารางเครื่องมือเปล่าๆ
        $('#addEditPlanModal').modal('show');
    });

    // เปิด Modal (แก้ไข)
    $(document).on('click', '.js-edit-plan-btn', function() {
        let id = $(this).data('id');

        // ยิงไปดึงข้อมูล Header มาก่อน
        $.ajax({
            url: './api/Transaction_maintenance_plan/getDataByID.php',
            type: 'GET',
            data: {
                id: id
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let d = res.data;

                    // --- Map ข้อมูลลง Form Header ---
                    $('#plan_header_id').val(d.id);
                    $('#plan_year').val(d.plan_year);
                    $('#group_name').val(d.group_name);

                    if (d.department_id) {
                        $('#department_id').val(d.department_id).trigger('change.select2');
                    } else {
                        $('#department_id').val('').trigger('change.select2'); // ล้างค่ากรณีไม่มีหน่วยงาน
                    }

                    // --- เปลี่ยน Text และ Icon หัวข้อ Modal ---
                    $('#textModal').text('แก้ไขแผนการซ่อมบำรุงประจำปี ' + d.plan_year);

                    // เปลี่ยนไอคอนหัว Modal เป็นรูปดินสอ
                    $('#addEditPlanModalLabel').html('<i class="fas fa-edit fa-lg me-2"></i> <span id="textModal">แก้ไขแผนการซ่อมบำรุงประจำปี ' + d.plan_year + '</span>');

                    // โหลดตารางพร้อมข้อมูลที่เคยติ๊กไว้
                    loadEquipmentChecklist(d.id);
                    $('#addEditPlanModal').modal('show');
                } else {
                    // เผื่อกรณี API ตอบกลับสถานะ error
                    Swal.fire('เกิดข้อผิดพลาด', res.message || 'ไม่สามารถดึงข้อมูลได้', 'error');
                }
            },
            error: function() {
                // จัดการกรณี Network หรือ Server Error
                Swal.fire('Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์เพื่อดึงข้อมูลได้', 'error');
            }
        });
    });

    // ลบแผน
    $(document).on('click', '.js-delete-plan-btn', function() {
        const dataId = $(this).data('id');
        const planYear = $(this).data('year');
        const groupName = $(this).data('group') || '-';
        const deptName = $(this).data('department') || '-';

        Swal.fire({
            title: 'ยืนยันการลบ?',
            html: `
                    <div class="text-center mb-3">คุณต้องการลบแผนงานประจำปีนี้ใช่หรือไม่?</div>
                    <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                        <div class="mb-2 pb-2 border-bottom">
                            <span class="text-secondary small">ปีแผนงาน:</span><br>
                            <b class="ps-2 text-danger">${planYear}</b>
                        </div>
                        <div class="mb-2 pb-2 border-bottom">
                            <span class="text-secondary small">กลุ่มงาน:</span><br>
                            <b class="ps-2 text-danger">${groupName}</b>
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
                    url: './api/Transaction_maintenance_plan/delete.php',
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
                }).catch(error => {
                    // ส่ง error ไปแสดงที่ Swal
                    Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message || error.statusText}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading() // ป้องกันการกดปิดระหว่างโหลด
        }).then((result) => {
            if (result.isConfirmed) {
                // เมื่อ AJAX สำเร็จ
                Swal.fire({
                    title: 'ลบข้อมูลสำเร็จ!',
                    text: 'แผนและข้อมูลตารางถูกลบเรียบร้อยแล้ว',
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 1500,
                    returnFocus: false
                }).then(() => {
                    searchPage(1); // รีเฟรชตารางหน้าหลัก
                });
            }
        });
    });

    // ปุ่ม Print (เปิดแท็บใหม่)
    $(document).on('click', '.js-print-btn', function() {
        let id = $(this).data('id');
        let planYear = $(this).data('year');
        let groupName = $(this).data('group');
        let deptName = $(this).data('department') || '';

        let safeGroup = groupName.replace(/[/\\?%*:|"<>]/g, '-').replace(/\s+/g, '-');
        let safeDept = deptName.replace(/[/\\?%*:|"<>]/g, '-').replace(/\s+/g, '-');
        const fileNamePrefix = "F-CS-20";

        let fileName = `${fileNamePrefix}_${planYear}_${safeGroup}_${safeDept}.pdf`;

        window.open(`./api/Transaction_maintenance_plan/gen_pdf.php/${fileName}?id=${id}`, '_blank');
    });

    $(document).on('click', '#btn_export_excel', function(e) {
        e.preventDefault(); // กันไว้ก่อนเผื่อปุ่มอยู่ใน Form submit

        // ดึงค่าจากฟอร์มทั้งหมดมาทำเป็น Query String
        const formData = $('#searchFilterForm').serialize();

        // ยิงไปที่ API
        window.location.href = './api/Transaction_maintenance_plan/exportExcel.php?' + formData;
    });

    $(document).on('click', '.clickable-row', function(e) {
        // 1. เช็คว่าถ้าคลิกโดนปุ่มแก้ไขหรือลบ (หรือไอคอนข้างใน) ให้หยุดทำงาน ไม่ต้องเปิด Modal
        if ($(e.target).closest('.js-edit-plan-btn, .js-delete-plan-btn, .js-print-btn').length) {
            return;
        }

        // 2. ดึง ID จากแถวที่คลิก
        let id = $(this).data('id');

        viewMaintenancePlanDetail(id);
    });

    function viewMaintenancePlanDetail(id) {
        $.get('./api/Transaction_maintenance_plan/getDataByID.php', {
            id: id
        }, function(res) {
            if (res.status === 'success') {
                let d = res.data;
                $('#view_plan_year').text(d.plan_year);
                $('#view_group_name').text(d.group_name || '-');
                $('#view_department_name').text(d.department_name || '-');
                loadViewChecklist(d.id);
                $('#viewPlanModal').modal('show');
            }
        }, 'json');
    }

    // ฟังก์ชันสร้างตารางแบบ Read-only (ไม่มีช่อง Input/Checkbox ให้กด)
    function loadViewChecklist(headerId) {
        $('#view_plan_detail_body').html('<tr><td colspan="18" class="py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></td></tr>');

        $.ajax({
            url: './api/Transaction_maintenance_plan/getEquipmentChecklist.php',
            type: 'GET',
            data: {
                header_id: headerId
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let html = '';
                    res.data.forEach(function(item, index) {

                        // ฟังก์ชันสร้างเครื่องหมายถูกสีเขียว (เฉพาะเดือนที่มีแผน)
                        let getMark = (m) => parseInt(item['m' + m]) === 1 ? '<i class="fas fa-check text-success fs-5"></i>' : '';

                        let respName = item.responsible_name ? item.responsible_name : '<span class="text-muted small italic">ไม่ระบุ</span>';

                        html += `
                            <tr>
                                <td class="body-sticky-1">${index + 1}</td>
                                <td class="text-start text-break body-sticky-2">
                                    <strong>${item.tool_name}</strong><br>
                                    <small class="text-muted">${item.asset_no || '-'}</small>
                                </td>
                                <td class="text-start">${item.serial_no || '-'}</td>
                                <td class="text-start">
                                    <strong>${item.brand || '-'}</strong><br>
                                    <small class="text-muted">${item.model || '-'}</small>
                                </td>
                        `;

                        // วนลูปเดือน (1-12) เปลี่ยนจาก Checkbox เป็นไอคอน
                        for (let m = 1; m <= 12; m++) {
                            html += `<td>${getMark(m)}</td>`;
                        }

                        // ผู้รับผิดชอบ และ หมายเหตุ (แสดงเป็น Text ธรรมดา)
                        html += `
                                <td class="text-start">${item.responsible_name || '-'}</td>
                                <td class="text-start">${item.remark || '-'}</td>
                            </tr>
                        `;
                    });

                    if (res.data.length === 0) {
                        html = '<tr><td colspan="18" class="py-4 text-muted">ไม่พบข้อมูลเครื่องมือในระบบ</td></tr>';
                    }

                    $('#view_plan_detail_body').html(html);
                }
            }
        });
    }


    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    // ==========================================
    // 3. Submit บันทึกข้อมูล
    // ==========================================
    $('#planForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;

        // 1. ตรวจสอบความถูกต้องของ Form (ดึงมาจาก v.เก่า)
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

                // เลื่อนหน้าจอไปหาช่องที่ลืมกรอก
                if (elementToScroll) {
                    elementToScroll.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            }

            // แจ้งเตือนว่ากรอกข้อมูลไม่ครบ
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

        // 2. ถ้าข้อมูลครบถ้วน ให้ส่ง AJAX
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(), // ใช้ serialize เพราะต้องส่งข้อมูล Array ตารางเดือน
            dataType: 'json',
            beforeSend: function() {
                // เปลี่ยนปุ่มเป็นสถานะโหลด
                $('#btn_save_all').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> กำลังบันทึก...');
            },
            success: function(res) {
                if (res.status == "success") {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: 'ข้อมูลแผนถูกบันทึกเรียบร้อยแล้ว',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        $('#addEditPlanModal').modal('hide');
                        searchPage(1); // รีเฟรชตารางหน้าหลักแบบไม่ต้อง Reload หน้าเว็บ
                    });
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', res.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                }
            },
            error: function(xhr, status, error) {
                // ดักจับ Error กรณี Server หรือ API พัง (จาก v.เก่า)
                console.error("AJAX Error:", xhr.responseText);
                Swal.fire('Server Error', 'เกิดข้อผิดพลาดที่ระบบ กรุณาลองใหม่ หรือติดต่อผู้ดูแลระบบ', 'error');
            },
            complete: function() {
                // คืนค่าปุ่มกลับมาเหมือนเดิม
                $('#btn_save_all').prop('disabled', false).html('<i class="fas fa-save me-2"></i> บันทึกข้อมูลแผน');
            }
        });
    });
</script>
<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>