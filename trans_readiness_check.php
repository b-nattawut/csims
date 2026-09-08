<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require_once './includes/check_permission.php';

$canManage = hasPermission($pdo, 'chemical.create');

// 1. เตรียม Option รายชื่อเจ้าหน้าที่
$userOptions = '';
try {
    $sqlUsers = "SELECT 
                    t1.user_id, 
                    CONCAT(IFNULL(t3.rank_name,''), ' ', t2.first_name, ' ', t2.last_name) AS fullname,
                    t4.position_name
                 FROM users t1
                 JOIN user_profile t2 ON t1.user_id = t2.user_id
                 LEFT JOIN user_rank t3 ON t2.rank_id = t3.rank_id
                 LEFT JOIN user_position t4 ON t2.position_id = t4.position_id 
                 WHERE t1.is_active = 1
                 ORDER BY t2.first_name ASC";

    $stmtUsers = $pdo->query($sqlUsers);
    while ($row = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
        // ป้องกันค่า NULL และใส่ Trim เพื่อตัดช่องว่าง
        $position = !empty($row['position_name']) ? trim($row['position_name']) : '-';

        // ใช้ Single Quote สลับ Double Quote ให้ชัดเจน
        $userOptions .= "<option value='{$row['user_id']}' data-position='{$position}'>{$row['fullname']}</option>";
    }

    // 2. ดึงข้อมูลผู้ประเมิน (Current User)
    $current_user_id = $_SESSION['user_id'];
    $sqlMe = "SELECT 
                CONCAT(IFNULL(t3.rank_name,''), ' ', t2.first_name, ' ', t2.last_name) AS fullname,
                t4.position_name AS position
              FROM user_profile t2
              LEFT JOIN user_rank t3 ON t2.rank_id = t3.rank_id
              LEFT JOIN user_position t4 ON t2.position_id = t4.position_id
              WHERE t2.user_id = ?";

    $stmtMe = $pdo->prepare($sqlMe);
    $stmtMe->execute([$current_user_id]);
    $me = $stmtMe->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // กรณี Query ผิดพลาดให้แสดง Error ใน Console แทนการพังหน้าเว็บ
    echo "<script>console.error('DB Error: " . addslashes($e->getMessage()) . "');</script>";
}

$title = "การตรวจจำนวนและความพร้อมเจ้าหน้าที่";

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

    .js-edit-readiness-btn,
    .js-delete-readiness-btn {
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

                        <div class="col-12 col-md-4 col-lg-3 col-xl-3">
                            <button class="btn <?= $canManage ? 'btn-success' : 'btn-secondary' ?> btn-sm px-4 fw-bold shadow-sm text-nowrap w-100 <?= $canManage ? 'js-add-readiness-btn' : '' ?>"
                                style="height: 31px;"
                                type="button" <?= $canManage ? 'data-bs-toggle="modal" data-bs-target="#addReadinessModal"' : 'disabled' ?>
                                title="<?= $canManage ? 'เพิ่มรายการใหม่' : 'คุณไม่มีสิทธิ์จัดการข้อมูล' ?>">
                                <i class="fas fa-plus me-2"></i>เพิ่มรายการ
                            </button>
                        </div>

                        <div class="col-12 col-md-8 col-lg-6 col-xl-6">
                            <label class="form-label text-muted small mb-1">ช่วงวันที่ปฏิบัติงาน</label>
                            <div class="input-group input-group-sm">
                                <input type="date" id="filter_date_start" name="filter_date_start" class="form-control">
                                <span class="input-group-text">ถึง</span>
                                <input type="date" id="filter_date_end" name="filter_date_end" class="form-control">
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3 col-xl-3">
                            <label for="filter_team_no" class="form-label text-muted small mb-1">ทีมตรวจที่</label>
                            <select id="filter_team_no" name="filter_team_no" class="form-select form-select-sm">
                                <option value="">-- ทั้งหมด --</option>
                                <?php for ($i = 1; $i <= 10; $i++) echo "<option value='$i'>ทีมที่ $i</option>"; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3 col-xl-3">
                            <label for="filter_readiness" class="form-label text-muted small mb-1">ความพร้อมเจ้าหน้าที่</label>
                            <select id="filter_readiness" name="filter_readiness" class="form-select form-select-sm">
                                <option value="">-- ทั้งหมด --</option>
                                <option value="COMPLETE">ครบ</option>
                                <option value="INCOMPLETE">ไม่ครบ</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3 col-xl-3">
                            <label for="filter_status" class="form-label text-muted small mb-1">สถานะ</label>
                            <select id="filter_status" name="filter_status" class="form-select form-select-sm">
                                <option value="">-- ทั้งหมด --</option>
                                <option value="IN_PROGRESS">รอดำเนินการ</option>
                                <option value="WAITING_SIGN">รอลงนามรับรอง</option>
                                <option value="COMPLETED">เสร็จสมบูรณ์</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-6 col-xl-6">
                            <label for="filter_checked_by" class="form-label text-muted small mb-1">เจ้าหน้าที่ผู้ตรวจ</label>
                            <select id="filter_checked_by" name="filter_checked_by" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                <option value=""></option>
                                <?php echo $userOptions; ?>
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
                    <th style="min-width: 80px;">Action</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 50px;">ลำดับ</th>
                    <th style="min-width: 120px;">วันที่ตรวจ</th>
                    <th style="min-width: 100px;">ทีมตรวจ</th>
                    <th class="d-none d-md-table-cell" style="min-width: 120px;">ความพร้อมเจ้าหน้าที่</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 180px;">เจ้าหน้าที่ผู้ตรวจ</th>
                    <th style="min-width: 130px;">สถานะ</th>
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

<!-- Modal สำหรับเพิ่มรายการตรวจจำนวนและความพร้อมเจ้าหน้าที่ -->
<div class="modal fade" id="addReadinessModal" aria-labelledby="addReadinessModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addReadinessModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> <span id="textModal">เพิ่มรายการ</span>
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" id="readinessHeaderForm" action="./api/Transaction_readiness_check/header/save.php" novalidate>
                    <input type="hidden" id="edit_id" name="id">

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border mb-3">
                        <legend class="fieldset-header"><i class="fas fa-info-circle me-1"></i> ข้อมูลพื้นฐานการปฏิบัติงาน</legend>
                        <div class="row g-3 mb-3 justify-content-start">

                            <div class="col-md-6">
                                <label for="check_date" class="form-label fw-bold">วันที่ตรวจ <span class="text-danger">*</span></label>
                                <input type="date" id="check_date" name="check_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">กรุณาระบุวันที่ตรวจ</div>
                            </div>
                            <div class="col-md-6">
                                <label for="check_time" class="form-label fw-bold">เวลา <span class="text-danger">*</span></label>
                                <input type="time" id="check_time" name="check_time" class="form-control" value="<?php echo date('H:i'); ?>" required>
                                <div class="invalid-feedback">กรุณาระบุเวลาที่ตรวจ</div>
                            </div>

                            <!-- ถ้ามีเพิ่มข้อมูลใน user table ว่าอยู่ทีมไหม ก็เอามา default ตาม user login ได้ -->
                            <div class="col-md-6">
                                <label for="team_no" class="form-label fw-bold">ทีมตรวจสถานที่เกิดเหตุ <span class="text-danger">*</span></label>
                                <select id="team_no" name="team_no" class="form-select select2-readiness" data-placeholder="-- เลือกทีมที่ปฏิบัติหน้าที่ --" required>
                                    <option value="" selected disabled>--- เลือกทีมตรวจ ---</option>
                                    <?php for ($i = 1; $i <= 10; $i++) echo "<option value='$i'>ทีมตรวจที่ $i</option>"; ?>
                                </select>
                                <div class="invalid-feedback">กรุณาเลือกทีมตรวจ</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">ความพร้อมของเจ้าหน้าที่ <span class="text-danger">*</span></label>
                                <div class="bg-light p-2 rounded-3 border d-flex justify-content-center gap-2" style="height: 50px; align-items: center;">
                                    <input type="radio" class="btn-check" name="team_readiness_status" id="status_complete" value="COMPLETE" required autocomplete="off">
                                    <label class="btn btn-outline-success flex-fill fw-bold py-1 shadow-sm" for="status_complete">
                                        <i class="fas fa-users me-2"></i> ครบ
                                    </label>

                                    <input type="radio" class="btn-check" name="team_readiness_status" id="status_incomplete" value="INCOMPLETE" autocomplete="off">
                                    <label class="btn btn-outline-danger flex-fill fw-bold py-1 shadow-sm" for="status_incomplete">
                                        <i class="fas fa-user-minus me-2"></i> ไม่ครบ
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label for="team_remark" class="form-label fw-bold">หมายเหตุ</label>
                                <div class="input-group input-group-seamless">
                                    <textarea
                                        id="team_remark"
                                        name="team_remark"
                                        class="form-control"
                                        rows="1"
                                        placeholder="ระบุเหตุผลกรณีเจ้าหน้าที่ไม่ครบ หรือข้อมูลเพิ่มเติม (ถ้ามี) เช่น ลาป่วย 1 นาย"></textarea>
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="team_remark"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>
                    </fieldset>

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border mb-3">
                        <legend class="fieldset-header"><i class="fas fa-user-edit me-1"></i> เจ้าหน้าที่ผู้รับผิดชอบ</legend>
                        <div class="row g-3 mb-3 justify-content-start">

                            <div class="col-md-6">
                                <label for="checked_by" class="form-label">เจ้าหน้าที่ผู้ตรวจ <span class="text-danger">*</span></label>
                                <select id="checked_by" name="checked_by" class="form-select select2-modal" data-placeholder="-- ค้นหาชื่อเจ้าหน้าที่ผู้ตรวจ --" required>
                                    <option value=""></option>
                                    <?php echo $userOptions; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="team_leader_id" class="form-label">หัวหน้าทีมตรวจ <span class="text-danger">*</span></label>
                                <select id="team_leader_id" name="team_leader_id" class="form-select select2-modal" data-placeholder="-- ค้นหาชื่อหัวหน้าทีมตรวจ --" required>
                                    <option value=""></option>
                                    <?php echo $userOptions; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <h6 class="form-label border-bottom pb-2 text-primary">เจ้าหน้าที่เฉพาะสังกัด (ลงนามกำกับหน่วยงาน) <span class="text-danger">*</span></h6>
                                <div class="row g-2 mt-2">

                                    <div class="col-12 col-md-6 col-lg-3">
                                        <label class="small form-label mb-1">ประเภทสังกัด</label>
                                        <select id="unit_category" name="unit_category" class="form-select" required>
                                            <option value="" selected disabled>-- เลือกสังกัด --</option>
                                            <option value="นวท.(สบ....)">นวท.(สบ....)</option>
                                            <option value="ศพฐ.">ศพฐ.</option>
                                            <option value="พฐ.จว.">พฐ.จว.</option>
                                        </select>
                                    </div>

                                    <div class="col-12 col-md-6 col-lg-3" id="unit_detail_container" style="display: none;">
                                        <label class="small form-label mb-1">ลำดับ</label>
                                        <select id="unit_detail" name="unit_detail" class="form-select" required>
                                        </select>
                                    </div>

                                    <div class="col-12 col-md-6 col-lg-6" id="unit_user_container" style="display: none;">
                                        <label class="small form-label mb-1">ชื่อเจ้าหน้าที่</label>

                                        <div id="wrapper_officer_select">
                                            <select id="unit_officer_id" name="unit_officer_id" class="form-select select2-modal" data-placeholder="-- ค้นหาชื่อเจ้าหน้าที่ลงนาม --">
                                                <option value=""></option>
                                                <?php echo $userOptions; ?>
                                            </select>
                                        </div>

                                        <div id="wrapper_officer_text" style="display: none;">
                                            <div class="input-group input-group-seamless">
                                                <input type="text" id="unit_officer_name" name="unit_officer_name" class="form-control" placeholder="ระบุชื่อ-นามสกุล เจ้าหน้าที่">
                                                <button type="button" class="btn btn-hw-open" data-hw-targets="unit_officer_name" title="เขียนด้วยลายมือ">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6 col-lg-6" id="unit_position_container" style="display: none;">
                                        <label class="small form-label mb-1">ตำแหน่ง</label>
                                        <div class="input-group input-group-seamless">
                                            <input type="text" id="unit_officer_position" name="unit_officer_position" class="form-control" placeholder="ระบุตำแหน่ง">
                                            <button type="button" class="btn btn-hw-open" id="btn_hw_position" data-hw-targets="unit_officer_position" title="เขียนด้วยลายมือ">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </fieldset>


                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border mb-3">
                        <legend class="fieldset-header"><i class="fas fa-tools me-1"></i> ข้อมูลยานพาหนะและชุดอุปกรณ์</legend>
                        <div class="row g-3 mb-3 justify-content-start">

                            <div class="col-md-12 mt-3 mb-3">
                                <h6 class="fw-bold mb-3 border-bottom pb-2 text-primary">
                                    ข้อมูลยานพาหนะ</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="car_license" class="form-label small">หมายเลขทะเบียน/โล่ <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-seamless">
                                            <input
                                                type="text"
                                                id="car_license"
                                                name="car_license"
                                                class="form-control"
                                                placeholder="ระบุเลขทะเบียน/โล่ (เช่น 1กข-1234 หรือ โล่ 00000)"
                                                required>
                                            <button type="button"
                                                class="btn btn-hw-open"
                                                data-hw-targets="car_license"
                                                title="เขียนด้วยลายมือ">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">กรุณาระบุหมายเลขทะเบียน/โล่</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="car_mileage" class="form-label small">เลขกิโลเมตรเริ่มต้น</label>
                                        <input type="number" id="car_mileage" name="car_mileage" class="form-control" placeholder="เช่น 45000">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <h6 class="fw-bold mb-3 border-bottom pb-2 text-primary">
                                    ข้อมูลชุดอุปกรณ์ประกอบการตรวจ
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="bag_set_no" class="form-label small fw-bold">กระเป๋าตรวจสถานที่เกิดเหตุทั่วไป (ชุดที่) <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            id="bag_set_no"
                                            name="bag_set_no"
                                            class="form-control"
                                            placeholder="ระบุเลขชุดกระเป๋า (เช่น 1 หรือ 10)"
                                            inputmode="decimal"
                                            required
                                            oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');">
                                        <div class="invalid-feedback">กรุณาระบุเลขชุดกระเป๋า</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="other_set_no" class="form-label small fw-bold">ชุดเครื่องมือตรวจสถานที่เกิดเหตุ (ชุดที่)</label>
                                        <input
                                            type="text"
                                            id="other_set_no"
                                            name="other_set_no"
                                            class="form-control"
                                            placeholder="ระบุหมายเลขชุดเครื่องมือ (เช่น 5)"
                                            inputmode="decimal"
                                            oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');">
                                        <small class="text-muted" style="font-size: 0.7rem;">*ครอบคลุมเครื่องตรวจโลหะ, Poly Light, Laser ฯลฯ</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12 mt-3">
                                <h6 class="fw-bold mb-3 border-bottom pb-2 text-primary">
                                    ข้อมูลกล้องถ่ายภาพดิจิทัล</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="select_camera" class="form-label small">เลือกกล้องจากระบบ</label>
                                        <select id="select_camera" name="equipment_id" class="form-select select2-camera" data-placeholder="-- ค้นหาเลขครุภัณฑ์หรือชื่อกล้องถ่ายภาพ --">
                                            <option value=""></option>
                                        </select>
                                    </div>
                                </div>

                                <div id="camera_detail_section" class="mt-3" style="display: none;">
                                    <div class="bg-light p-3 rounded-3 border border-dashed shadow-sm">
                                        <h6 class="text-primary fw-bold border-bottom pb-2 mb-3" style="font-size: 0.9rem;">
                                            <i class="fas fa-info-circle me-1"></i> รายละเอียดกล้องที่เลือก
                                        </h6>
                                        <div class="row g-3">
                                            <div class="col-12 col-md-4 col-xl-4">
                                                <label class="text-muted small d-block mb-1">เลขครุภัณฑ์</label>
                                                <span id="auto_camera_asset_no" class="fw-bold">-</span>
                                            </div>
                                            <div class="col-12 col-md-8 col-xl-8">
                                                <label class="text-muted small d-block mb-1">ชื่อกล้อง</label>
                                                <span id="auto_camera_name" class="fw-bold">-</span>
                                            </div>
                                            <div class="col-6 col-md-4 col-xl-4">
                                                <label class="text-muted small d-block mb-1">ยี่ห้อ</label>
                                                <span id="auto_camera_brand" class="fw-bold">-</span>
                                            </div>
                                            <div class="col-6 col-md-4 col-xl-4">
                                                <label class="text-muted small d-block mb-1">รุ่น</label>
                                                <span id="auto_camera_model" class="fw-bold">-</span>
                                            </div>
                                            <div class="col-6 col-md-4 col-xl-4">
                                                <label class="text-muted small d-block mb-1">Serial No. (S/N)</label>
                                                <span id="auto_camera_sn" class="fw-bold">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" id="camera_brand" name="camera_brand">
                                <input type="hidden" id="camera_model" name="camera_model">
                                <input type="hidden" id="camera_sn" name="camera_sn">

                            </div>
                        </div>
                    </fieldset>


                    <div class="alert alert-secondary d-flex align-items-center mb-0" role="alert">
                        <i class="fas fa-info-circle me-2 fs-5"></i>
                        <div class="small">
                            เมื่อบันทึกข้อมูลเบื้องต้นแล้ว ระบบจะนำคุณไปยังหน้า <b>"รายละเอียดการตรวจความพร้อม"</b> เพื่อประเมินความพร้อมของเจ้าหน้าที่และอุปกรณ์ในขั้นตอนถัดไป
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer d-flex justify-content-end bg-white border-top shadow-sm">
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="readinessHeaderForm">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger px-4 js-close-modal" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
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
    // Dynamic Unit Logic
    const unitMap = {
        'นวท.(สบ....)': ['1', '2', '3', '4', '5'],
        'ศพฐ.': ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
        'พฐ.จว.': ['สงขลา', 'ยะลา', 'ปัตตานี', 'นราธิวาส']
    };

    let isEditLoading = false; // Flag สำหรับควบคุมสถานะการโหลดข้อมูลแก้ไข

    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    function initSignerSelect2(currentId, otherIds) {
        $(currentId).select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#addReadinessModal'),
            allowClear: true,
            placeholder: "-- เลือกเจ้าหน้าที่ --",
            width: '100%',
            ajax: {
                url: './api/get_users.php',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    q: params.term,
                    category: $('#unit_category').val(),
                    detail: $('#unit_detail').val()
                }),
                processResults: function(response) {
                    // ดึงค่าปัจจุบันของช่องอื่นๆ มาเก็บใน Array
                    const selectedValues = otherIds.map(id => $(id).val());

                    return {
                        results: $.map(response.data, function(item) {
                            return {
                                id: item.user_id,
                                text: item.fullname,
                                // ถ้า user_id นี้ถูกเลือกอยู่ในช่องอื่นช่องใดช่องหนึ่ง ให้ Disable
                                disabled: selectedValues.includes(item.user_id.toString())
                            };
                        })
                    };
                },
                cache: true
            }
        }).on('change', function() {
            validateUniqueSigners(); // เช็คความถูกต้องภาพรวม (เส้นขอบแดง)
        });
    }

    function validateUniqueSigners() {
        const v1 = $('#checked_by').val();
        const v2 = $('#team_leader_id').val();
        const v3 = $('#unit_officer_id').val();

        // ล้างสถานะ Error เดิมก่อน
        $('.signer-error-msg').addClass('d-none');
        $('#checked_by, #team_leader_id, #unit_officer_id').next('.select2-container').removeClass('border border-danger rounded');

        // ตรวจสอบคู่ที่ซ้ำกัน
        if (v1 && v2 && v1 === v2) markAsError('#checked_by, #team_leader_id');
        if (v1 && v3 && v1 === v3) markAsError('#checked_by, #unit_officer_id');
        if (v2 && v3 && v2 === v3) markAsError('#team_leader_id, #unit_officer_id');
    }

    function markAsError(selector) {
        $(selector).next('.select2-container').addClass('border border-danger rounded');
        // แสดงข้อความแจ้งเตือน (ถ้าคุณเพิ่ม Tag ไว้ใน HTML)
        $('.signer-error-msg').removeClass('d-none');
    }

    function loadCameraList() {
        $.ajax({
            url: './api/Transaction_readiness_check/get_cameras.php',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let options = '<option value=""></option>';
                    res.data.forEach(item => {
                        // เก็บข้อมูลไว้ใน data attribute ของ option เพื่อเรียกใช้ง่ายๆ
                        options += `<option value="${item.id}" 
                            data-asset="${item.asset_no}" 
                            data-name="${item.tool_name}" 
                            data-brand="${item.brand}" 
                            data-model="${item.model}" 
                            data-sn="${item.serial_no}">
                            [${item.asset_no}] ${item.tool_name}
                        </option>`;
                    });
                    $('#select_camera').html(options);
                }
            }
        });
    }

    $(document).ready(function() {
        // โหลดข้อมูลครั้งแรก
        searchPage(1);

        $('.select2-camera').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#addReadinessModal'),
            allowClear: true,
            width: '100%',
        });

        // 2. โหลดข้อมูลกล้องจาก API
        loadCameraList();

        // 3. เมื่อเลือกกล้อง ให้แสดงรายละเอียด
        $('#select_camera').on('change', function() {

            $('#auto_camera_asset_no, #auto_camera_name, #auto_camera_brand, #auto_camera_model, #auto_camera_sn').text('-');
            $('#camera_brand, #camera_model, #camera_sn').val('');

            const selected = $(this).find(':selected');
            const val = $(this).val();

            if ($(this).val()) {
                const asset = selected.data('asset') || '-';
                const name = selected.data('name') || '-';
                const brand = selected.data('brand') || '-';
                const model = selected.data('model') || '-';
                const sn = selected.data('sn') || '-';

                // แสดงผลในหน้าจอ
                $('#auto_camera_asset_no').text(asset);
                $('#auto_camera_name').text(name);
                $('#auto_camera_brand').text(brand);
                $('#auto_camera_model').text(model);
                $('#auto_camera_sn').text(sn);

                // ใส่ค่าลง Hidden Input
                $('#camera_brand').val(brand);
                $('#camera_model').val(model);
                $('#camera_sn').val(sn);

                $('#camera_detail_section').fadeIn();
            } else {
                $('#camera_detail_section').hide();
            }
        });

        // =======================================================
        //  Initialize Select2
        // =======================================================

        // --- 1. ตั้งค่า IDs ของทั้ง 3 ช่องที่ต้องการตรวจสอบการซ้ำ ---
        const signerIds = ['#checked_by', '#team_leader_id', '#unit_officer_id'];

        // --- 2. Initialize Select2 สำหรับทั้ง 3 ช่อง ---
        initSignerSelect2('#checked_by', ['#team_leader_id', '#unit_officer_id']);
        initSignerSelect2('#team_leader_id', ['#checked_by', '#unit_officer_id']);
        initSignerSelect2('#unit_officer_id', ['#checked_by', '#team_leader_id']);

        $('.select2-filter').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
            placeholder: '-- ทั้งหมด --'
        }).next('.select2-container').addClass('select2-sm-custom');

        // =======================================================
        // Logic: เจ้าหน้าที่คนที่ 3 - ให้เลือก Unit ก่อน
        // =======================================================


        $('#unit_category').on('change', function() {
            const category = $(this).val();
            const $detailSelect = $('#unit_detail');

            $detailSelect.empty().append('<option value="" selected disabled>-- ระบุ --</option>');

            // ถ้าไม่ใช่โหมดโหลดข้อมูลแก้ไข ให้ล้างค่าทิ้งปกติ
            if (!isEditLoading) {
                $('#unit_user_container, #unit_position_container').hide();
                $('#unit_officer_id').val(null).trigger('change');
                $('#unit_officer_position').val('');
                $('#unit_officer_name').val('');
            }

            if (category && unitMap[category]) {
                // วาดใหม่ทุกครั้ง ไม่ต้องเช็ค length เพื่อให้จำนวนตัวเลือกถูกต้องตามประเภท
                unitMap[category].forEach(val => {
                    $detailSelect.append(`<option value="${val}">${val}</option>`);
                });
                $('#unit_detail_container').fadeIn(200);
            } else {
                $('#unit_detail_container').hide();
            }
            checkOfficerInputMode();
        });

        $('#unit_detail').on('change', function() {
            const detail = $(this).val();

            // ถ้าไม่ใช่โหมดโหลดข้อมูลแก้ไข และมีการเปลี่ยนค่าลำดับ
            if (!isEditLoading) {
                // เคลียร์ค่าเจ้าหน้าที่ทั้งหมดทันที
                $('#unit_officer_id').val(null).trigger('change'); // ล้าง Select2
                $('#unit_officer_name').val(''); // ล้าง Text ชื่อ
                $('#unit_officer_position').val(''); // ล้าง Text ตำแหน่ง
            }

            if (detail) {
                // ค่อยแสดง Container และเช็คโหมด (Readonly หรือ Editable)
                $('#unit_user_container, #unit_position_container').fadeIn(200);
                checkOfficerInputMode();
            }
        });
    });

    // ดึงตำแหน่งอัตโนมัติเมื่อเลือกเจ้าหน้าที่ 
    $('#unit_officer_id').on('change', function() {
        // ถ้ากำลังโหลดข้อมูลแก้ไขอยู่ ให้หยุดทำงานทันที ไม่ต้องไปดึงตำแหน่งใหม่
        if (isEditLoading) return;

        const userId = $(this).val();
        const $posInput = $('#unit_officer_position');

        // ทำงานเฉพาะเมื่อมีการเลือกคน และช่องตำแหน่งถูกตั้งเป็น readonly
        if (userId && $posInput.prop('readonly')) {
            // แสดงสถานะว่ากำลังโหลด (Optional)
            $posInput.val('กำลังดึงข้อมูลตำแหน่ง...');

            $.ajax({
                url: './api/get_user_position.php',
                type: 'GET',
                data: {
                    user_id: userId
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        $posInput.val(res.position_name || '-');
                    } else {
                        $posInput.val('-');
                    }
                },
                error: function() {
                    $posInput.val('ไม่พบข้อมูลตำแหน่ง');
                }
            });
        } else if (!userId && $posInput.prop('readonly')) {
            // ถ้าล้างค่าชื่อ (Clear) ให้ล้างตำแหน่งด้วย
            $posInput.val('');
        }
    });

    // ฟังก์ชันสำหรับสลับโหมดการกรอกชื่อเจ้าหน้าที่
    function checkOfficerInputMode() {
        const category = $('#unit_category').val();
        const detail = $('#unit_detail').val();
        const $selectWrapper = $('#wrapper_officer_select');
        const $textWrapper = $('#wrapper_officer_text');
        const $posInput = $('#unit_officer_position'); // ช่อง input ตำแหน่ง
        const $posHwBtn = $('#btn_hw_position'); // ปุ่มเขียนของตำแหน่ง

        // เงื่อนไข: ศพฐ. ลำดับ 10, พฐ.จว. ทั้ง 3 และ นวท.(สบ....) 1-4
        const isSystemUser = (category === 'ศพฐ.' && detail === '10') ||
            (category === 'พฐ.จว.' && ['สงขลา', 'ยะลา', 'ปัตตานี', 'นราธิวาส'].includes(detail)) ||
            (category === 'นวท.(สบ....)' && ['1', '2', '3', '4', '5'].includes(detail));

        if (isSystemUser) {
            $selectWrapper.show();
            $textWrapper.hide();
            $posInput.prop('readonly', true).addClass('bg-light'); // ดึงข้อมูลตำแหน่งของ user ที่มีในระบบมาแสดงได้เลย
            $posHwBtn.hide(); // ซ่อนปุ่มเขียน เพราะข้อมูลดึงจากระบบ

            // จัดการ Required (สำคัญมากเพื่อให้ Form Validate ผ่าน)
            $('#unit_officer_id').prop('required', true);
            $('#unit_officer_name').prop('required', false);
            $posInput.prop('required', true);

            // --- 2. จัดการข้อมูล (ทำเฉพาะตอน User เปลี่ยนค่าเอง ไม่ใช่ตอนโหลด Edit) ---
            if (!isEditLoading) {
                $('#unit_officer_name').val('');
                $('#unit_officer_id').val(null).trigger('change');
                $posInput.val(''); // ล้างตำแหน่งด้วยถ้าต้องการให้เลือกชื่อใหม่ก่อน
            }

        } else {
            $selectWrapper.hide();
            $textWrapper.show();
            $posInput.prop('readonly', false).removeClass('bg-light'); // ให้ user กรอกตำแหน่งเอง ถ้าไม่ได้เป็น user ในระบบ
            $posHwBtn.show(); // แสดงปุ่มเขียน เพื่อให้ใช้งานปากกาได้

            // จัดการ Required
            $('#unit_officer_id').prop('required', false);
            $('#unit_officer_name').prop('required', true);
            $posInput.prop('required', true);

            // --- 2. จัดการข้อมูล ---
            if (!isEditLoading) {
                $('#unit_officer_id').val(null).trigger('change');
                $('#unit_officer_name').val(''); // ล้างชื่อเดิม
                $posInput.val(''); // ล้างตำแหน่งเดิม
            }
        }
    }

    // =======================================================
    // ปุ่มเพิ่มรายการ (Add Button)
    // =======================================================

    $(document).on('click', '.js-add-readiness-btn', function() {
        const $form = $('#readinessHeaderForm');
        $form[0].reset();
        $form.removeClass('was-validated');
        $('#edit_id').val('');

        // Default วันที่/เวลาปัจจุบัน
        const now = new Date();
        $('#check_date').val(now.toISOString().split('T')[0]);
        $('#check_time').val(now.toTimeString().split(' ')[0].substring(0, 5));

        // Default ผู้ตรวจเป็นคน Login
        $('#checked_by').val('<?php echo $current_user_id; ?>').trigger('change');

        // ล้างสถานะ Dynamic Dropdown
        $('#unit_officer_id').val(null).trigger('change');
        $('#unit_officer_name').val('');
        $('#unit_officer_position').val('');

        $('#wrapper_officer_select').show();
        $('#wrapper_officer_text').hide();
        $('#unit_detail_container, #unit_user_container, #unit_position_container').hide();
        $('.select2-modal').not('#checked_by').val(null).trigger('change');

        $('input[name="team_readiness_status"]').prop('checked', false); // ล้างการเลือกปุ่ม ครบ/ไม่ครบ

        $('#select_camera').val(null).trigger('change');
        // ซ่อนส่วนรายละเอียดและล้างข้อความข้างใน
        $('#camera_detail_section').hide();
        $('#auto_camera_asset_no, #auto_camera_name, #auto_camera_brand, #auto_camera_model, #auto_camera_sn').text('-');

        // ล้าง Hidden Input (ป้องกันค่าค้างจากตอน Edit)
        $('#camera_brand, #camera_model, #camera_sn').val('');

        $('#textModal').text('เพิ่มรายการ');
        $('#btn_save_all').prop('disabled', false).attr('title', '');
        $('#addReadinessModal').modal('show');
    });

    // =======================================================
    // ปุ่มแก้ไขรายการ
    // =======================================================
    $(document).on('click', '.js-edit-readiness-btn', function() {
        let dataId = $(this).data('id');
        const $form = $('#readinessHeaderForm');

        isEditLoading = true; // เริ่มโหมดโหลดข้อมูล

        $.ajax({
            url: './api/Transaction_readiness_check/header/getDataByID.php',
            type: 'GET',
            data: {
                id: dataId
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let d = res.data;

                    // 2.1 เปลี่ยนหัวข้อและสถานะ Modal
                    $('#textModal').text('แก้ไขข้อมูลบันทึกความพร้อม');
                    $('#addReadinessModalLabel').html('<i class="fas fa-edit fa-lg me-2"></i> แก้ไขข้อมูลบันทึกความพร้อม');
                    $form.removeClass('was-validated');
                    $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');

                    // 2.2 Map ข้อมูลพื้นฐาน
                    $('#edit_id').val(d.id);
                    $('#check_date').val(d.check_date);
                    $('#check_time').val(d.check_time_raw); // ส่งแบบ HH:mm:ss มาจาก PHP
                    $('#team_no').val(d.team_no).trigger('change');

                    if (d.team_readiness_status) {
                        $(`input[name="team_readiness_status"][value="${d.team_readiness_status}"]`).prop('checked', true);
                    }
                    $('#team_remark').val(d.team_remark || '');

                    // 2.3 จัดการเจ้าหน้าที่ 3 ส่วน (Select2 AJAX)
                    // ใช้ฟังก์ชันช่วยสร้าง Option ใหม่เพื่อให้ Select2 แสดงชื่อได้ถูกต้อง
                    setSelect2Value('#checked_by', d.checked_by, d.checked_by_fullname);
                    setSelect2Value('#team_leader_id', d.team_leader_id, d.team_leader_fullname);

                    // 2.4 จัดการสังกัด (Dynamic Dropdown)
                    if (d.unit_category) {
                        $('#unit_category').val(d.unit_category).trigger('change');

                        // ใช้ setTimeout เล็กน้อยเพื่อให้ Dropdown ชั้นที่ 2 (unit_detail) วาดเสร็จก่อนหยอดค่า
                        setTimeout(function() {
                            $('#unit_detail').val(d.unit_detail).trigger('change');

                            checkOfficerInputMode();

                            if ($('#wrapper_officer_select').is(':visible')) {
                                // ถ้าเป็นโหมด Select 
                                setSelect2Value('#unit_officer_id', d.unit_officer_id, d.unit_officer_fullname);
                            } else {
                                // ถ้าเป็นโหมดระบุชื่อเอง
                                $('#unit_officer_name').val(d.unit_officer_name || d.unit_officer_fullname);
                            }
                            $('#unit_officer_position').val(d.unit_officer_position || '');

                            $('#unit_detail_container, #unit_user_container, #unit_position_container').show();
                            // ปล่อยล็อกหลังจากทุกอย่างถูก Render เสร็จแล้ว
                            setTimeout(() => {
                                isEditLoading = false;
                            }, 200);
                        }, 300);
                    }

                    // 2.5 ข้อมูลยานพาหนะและอุปกรณ์
                    $('input[name="car_license"]').val(d.car_license);
                    $('input[name="car_mileage"]').val(d.car_mileage);
                    $('input[name="bag_set_no"]').val(d.bag_set_no);
                    $('input[name="other_set_no"]').val(d.other_set_no);
                    $('#camera_brand').val(d.camera_brand);
                    $('#camera_model').val(d.camera_model);
                    $('#camera_sn').val(d.camera_sn);

                    // 2.6 จัดการ Auto-Select กล้องใน Select2 
                    let foundCameraId = "";

                    // วนลูปหา Option ที่มี ยี่ห้อ, รุ่น, และ SN ตรงกับในฐานข้อมูล Log
                    $('#select_camera option').each(function() {
                        const optBrand = $(this).data('brand');
                        const optModel = $(this).data('model');
                        const optSN = $(this).data('sn');

                        if (optBrand === d.camera_brand && optModel === d.camera_model && optSN === d.camera_sn) {
                            foundCameraId = $(this).val();
                            return false; // เจอแล้วหยุดลูป
                        }
                    });

                    if (foundCameraId) {
                        // กรณี 1: เจอเครื่องมือที่ตรงกันใน Master List ปัจจุบัน
                        $('#select_camera').val(foundCameraId).trigger('change');
                    } else if (d.camera_sn) {
                        // กรณี 2: ไม่เจอใน Master (อาจถูกลบหรือแก้ไข) แต่ใน Log มีข้อมูลอยู่
                        // ให้สร้าง Option "ชั่วคราว" ขึ้นมาเพื่อให้ User เห็นข้อมูลเดิมที่เคยบันทึกไว้
                        const tempText = `[ข้อมูลเดิม] ${d.camera_brand} ${d.camera_model} (S/N: ${d.camera_sn})`;
                        const tempOption = new Option(tempText, "TEMP_OLD_DATA", true, true);

                        // ใส่ข้อมูล snapshot กลับเข้าไปใน data-attr ด้วย เผื่อ User กดเลือกใหม่แล้วเปลี่ยนใจกลับมาอันเดิม
                        $(tempOption).data('brand', d.camera_brand).data('model', d.camera_model).data('sn', d.camera_sn);

                        $('#select_camera').append(tempOption).trigger('change');
                    } else {
                        // กรณี 3: ไม่มีข้อมูลกล้องใน Log เดิม
                        $('#select_camera').val('').trigger('change');
                    }

                    $('#btn_save_all').prop('disabled', false).attr('title', '');

                    // 2.7 แสดง Modal
                    $('#addReadinessModal').modal('show');
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์เพื่อดึงข้อมูลได้', 'error');
            }
        });
    });

    /**
     * ฟังก์ชันช่วยในการ Set ค่าให้กับ Select2 ที่เป็น AJAX
     * ปรับปรุงให้รองรับทั้งการยัดค่าใหม่ และการเลือกจาก List เดิม
     */
    function setSelect2Value(elementId, id, text) {
        const $select = $(elementId);

        if (id && text) {
            // ตรวจสอบว่ามี Option นี้อยู่แล้วหรือไม่ ถ้าไม่มีให้สร้างใหม่
            if ($select.find("option[value='" + id + "']").length === 0) {
                const newOption = new Option(text, id, true, true);
                $select.append(newOption).trigger('change');
            } else {
                $select.val(id).trigger('change');
            }
        } else if (id) {
            // กรณีมีแค่ ID 
            $select.val(id).trigger('change');
        } else {
            // ล้างค่า
            $select.val(null).trigger('change');
        }
    }

    // =======================================================
    // ปุ่มลบรายการ
    // =======================================================

    // เดี๋ยวมาปรับเป็น layout เดียวกับหน้าอื่น ๆ 
    $(document).on('click', '.js-delete-readiness-btn', function(e) {
        e.preventDefault();
        const dataId = $(this).data('id');
        const checkDate = $(this).data('date');
        const checkTime = $(this).data('time');
        const teamNo = $(this).data('team');
        const inspector = $(this).data('inspector');

        if (!dataId) {
            Swal.fire('Error', 'ไม่พบ ID ของรายการ', 'error');
            return;
        }

        Swal.fire({
            title: 'ยืนยันการลบ?',
            html: `
                <div class="text-center mb-3">คุณต้องการลบข้อมูลบันทึกการตรวจความพร้อมนี้ใช่หรือไม่?</div>
                <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">วันที่/เวลาที่ตรวจ:</span><br>
                        <b class="ps-2 text-danger">${checkDate} (${checkTime} น.)</b>
                    </div>
                    
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">ทีมตรวจปฏิบัติหน้าที่:</span><br>
                        <b class="ps-2 text-danger">ทีมที่ ${teamNo}</b>
                    </div>

                    <div class="mb-2">
                        <span class="text-secondary small">เจ้าหน้าที่ผู้ตรวจ:</span><br>
                        <b class="ps-2 text-danger">${inspector}</b>
                    </div>

                </div>

                <div class="alert alert-warning small mt-3 mb-0 border-0 shadow-sm text-start">
                    <i class="fas fa-exclamation-triangle me-1"></i> 
                    <b>รายการตรวจสอบ (Checklist) ทั้งหมด</b> ในชุดนี้จะถูกลบออกถาวรและไม่สามารถกู้คืนได้
                </div>

                <div class="mt-3 text-center">
                    <small class="text-muted italic" style="font-size: 0.75rem;">
                        *หมายเหตุ: หากลงนามครบถ้วน (Completed) แล้ว จะไม่สามารถลบได้
                    </small>
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
                    url: './api/Transaction_readiness_check/header/delete.php',
                    type: 'POST',
                    data: {
                        id: dataId
                    },
                    dataType: 'json'
                }).then(response => {
                    if (response.status !== 'success') {
                        throw new Error(response.message || 'ลบข้อมูลไม่สำเร็จ');
                    }
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
                    text: 'บันทึกความพร้อมถูกลบเรียบร้อยแล้ว',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false,
                    returnFocus: false
                }).then(() => {
                    searchPage(1); // โหลดตารางใหม่
                });
            }
        });
    });

    // ========================================================================
    // ปุ่มพาไปหน้า Detail
    // ========================================================================

    $(document).on('click', '.clickable-row', function(e) {
        // ป้องกันการเปลี่ยนหน้าเมื่อคลิกโดนปุ่มแก้ไข, ลบ หรือ พิมพ์
        if ($(e.target).closest('.js-edit-readiness-btn, .js-delete-readiness-btn, .js-print-btn').length) return;

        // คลิกที่แถวเพื่อพาไปหน้า detail
        let dataId = $(this).data('id');
        window.location.href = `trans_readiness_check_detail.php?id=${dataId}`;
    });

    // ========================================================================
    // ปุ่ม Export PDF
    // ========================================================================

    $(document).on('click', '.js-print-btn', function() {
        let dataId = $(this).data('id');
        let team = $(this).data('team');
        let date = $(this).data('date').replace(/\//g, '-');

        let fileName = `F-CS-02_Team-${team}_${date}.pdf`;

        // เปิดหน้า PDF ใน Tab ใหม่
        window.open(`./api/Transaction_readiness_check/gen_pdf.php/${fileName}?id=${dataId}`, '_blank');
    });

    // ========================================================================
    // ปุ่ม Export Excel
    // ========================================================================
    $(document).on('click', '#btn_export_excel', function(e) {
        e.preventDefault(); // กันไว้ก่อนเผื่อปุ่มอยู่ใน Form submit

        // ดึงค่าจากฟอร์มทั้งหมดมาทำเป็น Query String
        const formData = $('#searchFilterForm').serialize();

        // ยิงไปที่ API
        window.location.href = './api/Transaction_readiness_check/exportExcel.php?' + formData;
    });

    // =======================================================
    // บันทึกข้อมูล (Submit Form)
    // =======================================================

    $('#readinessHeaderForm').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        const $form = $(form);

        // 1. Custom Validation: ตรวจสอบรายชื่อเจ้าหน้าที่ห้ามซ้ำกัน (3 คน)
        const v1 = $('#checked_by').val();
        const v2 = $('#team_leader_id').val();
        const v3 = $('#unit_officer_id').val();

        if ((v1 && v2 && v1 === v2) || (v1 && v3 && v1 === v3) || (v2 && v3 && v2 === v3)) {
            Swal.fire({
                icon: 'error',
                title: 'รายชื่อเจ้าหน้าที่ซ้ำกัน',
                text: 'กรุณาเลือกเจ้าหน้าที่ผู้รับผิดชอบให้ครบทั้ง 3 ตำแหน่ง โดยห้ามเป็นบุคคลเดียวกัน',
                confirmButtonColor: '#d33'
            });
            validateUniqueSigners(); // เรียกใช้ฟังก์ชันเดิมเพื่อแสดงขอบแดง
            return;
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

        // 3. เตรียมส่งข้อมูล
        const formData = new FormData(form);

        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function() {
                $('#btn_save_all').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...');
            },
            success: function(res) {
                if (res.status === "success") {

                    let isEditMode = !$('#edit_id').val();

                    // 1. กำหนดข้อความให้สอดคล้องกับสิ่งที่จะเกิดขึ้นจริง
                    let swalText = isEditMode ?
                        'กำลังนำคุณไปหน้าการตรวจจำนวนและความพร้อมเจ้าหน้าที่...' :
                        'ข้อมูลถูกปรับปรุงเรียบร้อยแล้ว';

                    let swalTimer = isEditMode ?
                        2000 : 1500;

                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: swalText,
                        timer: swalTimer,
                        showConfirmButton: false
                    }).then(() => {
                        if (isEditMode && res.insert_id) {
                            // Redirect ไปหน้า Detail เพื่อทำ Checklist ต่อ
                            window.location.href = `trans_readiness_check_detail.php?id=${res.insert_id}`;
                        } else {
                            // กรณีแก้ไข: ปิด Modal และโหลดตารางหน้าเดิมใหม่
                            $('#addReadinessModal').modal('hide');
                            const currentPage = parseInt($('#pagination-list .active a').text()) || 1;
                            if (typeof searchPage === "function") {
                                searchPage(isEditMode ? currentPage : 1);
                            }
                        }
                    });
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', res.message || 'ไม่สามารถบันทึกได้', 'error');
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

    // =======================================================
    // การดึงข้อมูลและแสดงผลตาราง (Search & Render)
    // =======================================================

    function searchPage(page) {
        const formData = $('#searchFilterForm').serializeArray();
        formData.push({
            name: 'page',
            value: page
        });

        $.ajax({
            url: './api/Transaction_readiness_check/header/searchData.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    renderTable(res.data, res.offset);
                    $('#count_display').text(res.count);
                    const totalPages = parseInt(res.totalPages);
                    const currentPage = parseInt(res.currentPage);

                    $('#pagination-list').empty();

                    if (totalPages > 0) {
                        setupPagination(totalPages, currentPage);
                    } else {
                        // กรณีไม่พบข้อมูลเลย
                        $('#pagination-list').html('');
                    }
                } else {
                    $('#table_body').html(`<tr><td colspan="8" class="text-center text-danger py-5"><i class="fas fa-triangle-exclamation fa-2x mb-2"></i><br>${res.message || 'เกิดข้อผิดพลาดในการดึงข้อมูล'}</td></tr>`);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                // แสดง Error บนหน้าจอเมื่อเชื่อมต่อไม่ได้
                $('#table_body').html('<tr><td colspan="8" class="text-center text-danger py-5"><i class="fas fa-triangle-exclamation fa-2x mb-2"></i><br>ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้</td></tr>');
            }
        });
    }

    function renderTable(data, offset) {
        let html = '';
        let start_no = parseInt(offset) + 1;

        if (data && data.length > 0) {
            data.forEach((row, i) => {
                const isCompleted = row.status === 'COMPLETED';

                // 1. Badge ความพร้อมคน (ครบ/ไม่ครบ)
                const personReadiness = row.team_readiness_status === 'COMPLETE' ?
                    `<span class="text-success fw-bold">ครบ</span>` :
                    `<span class="text-danger fw-bold">ไม่ครบ</span>`;

                // 2. Badge สถานะอุปกรณ์ + Progress (X/4)
                let statusBadge = '';
                if (isCompleted) {
                    statusBadge = `<span class="badge rounded-pill bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-2" style="font-size: 0.8rem;">เสร็จสมบูรณ์</span>`;
                } else if (row.checked_count == 4) {
                    // ตรวจอุปกรณ์ครบ 4 หมวดแล้ว แต่ยังเซ็นไม่ครบ (รอลงนาม)
                    statusBadge = `
                    <div class="d-flex flex-column align-items-center">
                        <span class="badge rounded-pill bg-info bg-opacity-25 text-info border border-info border-opacity-50 px-3 py-2" style="font-size: 0.8rem;">
                            รอลงนามรับรอง
                        </span>
                    </div>`;
                } else {
                    // แสดงจำนวนหมวดที่ตรวจแล้ว เช่น รอดำเนินการ (2/4)
                    statusBadge = `
                    <div class="d-flex flex-column align-items-center">
                        <span class="badge rounded-pill bg-warning bg-opacity-25 text-dark border border-warning border-opacity-50 px-3 py-2" style="font-size: 0.8rem;">
                            รอดำเนินการ (${row.checked_count}/4)
                        </span>
                    </div>`;
                }

                // 3. ปุ่ม Export PDF 
                let btnStyle = isCompleted ?
                    'background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;' :
                    'box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); font-size: 11px; white-space: nowrap;';

                let printBtnHtml = `
                    <button type="button" class="js-print-btn btn ${isCompleted ? '' : 'btn-secondary'} shadow-sm" 
                        style="${btnStyle}" data-id="${row.id}" data-team="${row.team_no}" 
                        data-date="${row.check_date_show}" ${isCompleted ? '' : 'disabled'} title="${isCompleted ? 'พิมพ์รายงาน F-CS-02' : 'ยังไม่เสร็จสมบูรณ์'}">
                        <i class="fas fa-file-pdf me-1"></i> Export PDF
                    </button>`;

                let actionButtons = '';

                if (isCompleted) {
                    // 1. ถ้าประเมินเสร็จสมบูรณ์แล้ว: แสดงแค่ "แม่กุญแจ" ตัวเดียวพอ
                    actionButtons = `
                        <i class="fas fa-lock text-muted opacity-50 fa-lg" 
                        title="เสร็จสมบูรณ์แล้ว ไม่สามารถแก้ไขหรือลบได้" 
                        style="cursor: default;"></i>
                    `;
                } else {
                    // 2. ถ้ายังไม่เสร็จ: 
                    actionButtons = `

                        <i class="fas fa-pen-to-square fa-lg text-warning ${window._canManage ? 'js-edit-readiness-btn cursor-pointer' : ''}" 
                        data-id="${row.id}" 
                        title="${window._canManage ? 'แก้ไข' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                        <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-readiness-btn cursor-pointer' : ''}" 
                        data-id="${row.id}" 
                        data-date="${row.check_date_show}"
                        data-time="${row.check_time_show}"
                        data-team="${row.team_no}"
                        data-inspector="${row.inspector_fullname || '-'}"
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
                        <td class="text-center align-middle">${row.check_date_show} ${row.check_time_show || ''}</td>
                        <td class="fw-bold text-center align-middle">ทีมที่ ${row.team_no}</td>
                        <td class="text-center align-middle d-none d-md-table-cell">${personReadiness}</td>
                        <td class="text-start align-middle d-none d-lg-table-cell text-truncate" style="max-width: 180px;">
                            ${row.inspector_fullname || '-'}
                        </td>
                        <td class="text-center align-middle">${statusBadge}</td>
                        <td class="text-center align-middle d-none d-md-table-cell">${printBtnHtml}</td>
                    </tr>`;
            });
        } else {
            html = `
            <tr>
                <td colspan="8" class="text-center py-5">
                    <div class="text-muted">
                        <i class="fas fa-file-signature fa-3x mb-3 opacity-50"></i>
                        <p class="mb-0 fw-bold">ไม่พบรายการตรวจความพร้อมเจ้าหน้าที่ในระบบ</p>
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
        $('.select2-filter').val(null).trigger('change');
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