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

$title = "การประเมินความสามารถผู้ปฏิบัติหน้าที่";

ob_start();
?>

<style>
    /* 1. ปรับแต่งหัวข้อกลุ่ม  */
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

    /* 2. ปรับแต่งรายการย่อย */
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

    .js-delete-assessment-btn {
        padding: 30px;
        /* ขยายพื้นที่จิ้มออกไปรอบๆ */
        margin: -30px;
        /* ดึงขอบกลับมาเพื่อไม่ให้กระทบตำแหน่งในตาราง */
        cursor: pointer;
        touch-action: manipulation;
        /* ป้องกันการซูมเวลาเผลอ Double Tap */
    }

    /* ชื่อสารเคมี: หนาบน iPad (md) แต่ปกติบน PC (lg ขึ้นไป) */
    .report-no-responsive {
        font-weight: 700;
        /* Bold สำหรับจอเล็ก/iPad */
    }

    @media (min-width: 992px) {
        .report-no-responsive {
            font-weight: 500;
            /* กลับเป็นค่าปกติ (Medium) สำหรับจอใหญ่ */
        }
    }

    /* 1. ป้องกันไม่ให้ปุ่มที่ disabled ดูจาง (คงค่า Opacity เป็น 1) */
    .btn-check:disabled+.btn,
    .btn-check[disabled]+.btn {
        opacity: 0.8 !important;
        /* ทำให้สีไม่จาง */
        filter: none !important;
    }

    /* 2. กำหนดสีปุ่มที่ 'ถูกเลือก' (Checked) แม้จะถูก disabled อยู่ */
    /* เพื่อให้สีพื้น (Primary) แสดงชัดเจนเหมือนปุ่มปกติ */
    .btn-check:disabled:checked+.btn-outline-primary {
        background-color: var(--bs-primary) !important;
        color: #fff !important;
        border-color: var(--bs-primary) !important;
    }

    /* 3. กำหนดสีปุ่มที่ 'ไม่ได้ถูกเลือก' ให้ขอบยังชัด */
    .btn-check:disabled+.btn-outline-primary {
        color: var(--bs-primary);
        background-color: transparent;
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
                            <button class="btn <?= $canManage ? 'btn-success' : 'btn-secondary' ?> btn-sm px-4 fw-bold shadow-sm text-nowrap w-100 <?= $canManage ? 'js-add-assessment-btn' : '' ?>"
                                style="height: 31px;"
                                type="button" <?= $canManage ? 'data-bs-toggle="modal" data-bs-target="#addEditAssessmentModal"' : 'disabled' ?>
                                title="<?= $canManage ? 'เพิ่มรายการใหม่' : 'คุณไม่มีสิทธิ์จัดการข้อมูล' ?>">
                                <i class="fas fa-plus me-2"></i>เพิ่มรายการ
                            </button>
                        </div>

                        <div class="col-12 col-md-8 col-lg-6 col-xl-6">
                            <label class="form-label text-muted small mb-1">ช่วงวันที่ดำเนินการ</label>
                            <div class="input-group input-group-sm">
                                <input type="date" id="filter_date_start" name="filter_date_start" class="form-control">
                                <span class="input-group-text">ถึง</span>
                                <input type="date" id="filter_date_end" name="filter_date_end" class="form-control">
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3 col-xl-3">
                            <label for="filter_report_no" class="form-label text-muted small mb-1">เลขรายงาน</label>
                            <select id="filter_report_no" name="filter_report_no" class="form-select form-select-sm select2-report-filter">
                                <option value=""></option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_case_scope" class="form-label text-muted small mb-1">ขอบข่ายที่ตรวจ</label>
                            <select id="filter_case_scope" name="filter_case_scope" class="form-select form-select-sm">
                                <option value="">-- ทั้งหมด --</option>
                                <option value="PROPERTY">คดีทรัพย์</option>
                                <option value="LIFE">คดีชีวิต</option>
                                <option value="EXPLOSIVE">คดีระเบิด</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-12 col-lg-8 col-xl-8">
                            <label for="filter_location" class="form-label text-muted small mb-1">ที่ตั้ง / สถานที่ดำเนินการ</label>
                            <input type="text" id="filter_location" name="filter_location"
                                class="form-control form-control-sm" placeholder="ระบุสถานที่ หรือจุดเกิดเหตุ...">
                        </div>

                        <div class="col-12 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_status" class="form-label text-muted small mb-1">สถานะ</label>
                            <select id="filter_status" name="filter_status" class="form-select form-select-sm">
                                <option value="">-- ทั้งหมด --</option>
                                <option value="IN_PROGRESS">รอประเมิน</option>
                                <option value="WAITING_SIGN">รอลงนามรับรอง</option>
                                <option value="COMPLETED">เสร็จสมบูรณ์</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-8 col-xl-8">
                            <label for="filter_evaluator" class="form-label text-muted small mb-1">ผู้ประเมิน</label>
                            <select id="filter_evaluator" name="filter_evaluator" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
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
                    <th style="min-width: 110px;">วันที่ดำเนินการ</th>
                    <th style="min-width: 140px;">เลขรายงาน</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 200px;">ที่ตั้ง / สถานที่ดำเนินการ</th>
                    <th style="min-width: 80px;">ขอบข่าย</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 180px;">ผู้ประเมิน</th>
                    <th style="min-width: 120px;">สถานะ</th>
                    <th style="min-width: 100px;">ส่งออกรายงาน</th>
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

<!-- Modal สำหรับเพิ่มรายการประเมินเจ้าหน้าที่ -->
<div class="modal fade" id="addEditAssessmentModal" aria-labelledby="addEditAssessmentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addEditAssessmentModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> <span id="textModal">เพิ่มรายการ</span>
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" id="assessmentHeaderForm" action="./api/Transaction_staff_assessment/header/save.php" novalidate>
                    <input type="hidden" id="edit_id" name="id">

                    <fieldset class="bg-primary bg-opacity-10 p-3 rounded-3 border-0 mb-4">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 50px; height: 50px;">
                                <i class="fas fa-user-tie text-white fs-4"></i>
                            </div>
                            <div class="ms-3 flex-grow-1">
                                <label class="form-label small text-muted mb-0 d-block">เจ้าหน้าที่ผู้ประเมิน</label>
                                <span class="fw-bold text-primary"><?php echo $me['fullname'] ?? '-'; ?></span>
                                <input type="hidden" name="evaluator_id" value="<?php echo $current_user_id; ?>">

                                <span class="mx-2 text-muted opacity-50">|</span>
                                <span class="small text-secondary"><?php echo $me['position'] ?? '-'; ?></span>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border mb-3">
                        <legend class="fieldset-header"><i class="fas fa-info-circle me-1"></i> ข้อมูลทั่วไป</legend>
                        <div class="row g-3 mb-3 justify-content-start">

                            <div class="col-md-6">
                                <label for="report_no" class="form-label">เลขรายงาน <span class="text-danger">*</span></label>
                                <select id="report_no" name="report_no" class="form-select select2-report-modal" required>
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาเลือกเลขรายงาน</div>
                            </div>

                            <div class="col-md-6">
                                <label for="operation_date" class="form-label">วันที่ดำเนินการ <span class="text-danger">*</span></label>
                                <input type="date" id="operation_date" name="operation_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">กรุณาระบุวันที่</div>
                            </div>

                            <div class="col-md-12">
                                <label for="location" class="form-label">ที่ตั้ง / สถานที่ดำเนินการ <span class="text-danger">*</span></label>
                                <div class="input-group input-group-seamless">
                                    <textarea
                                        id="location"
                                        name="location"
                                        class="form-control"
                                        rows="2"
                                        placeholder="เช่น บ้านเลขที่ 1/1 ต.ศาลายา อ.พุทธมณฑล จ.นครปฐม"
                                        required></textarea>
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="location"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุสถานที่</div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold mb-2">ขอบข่ายที่ตรวจ
                                    <small class="text-muted fw-normal">(ระบุอัตโนมัติตามเลขรายงาน)</small>
                                </label>

                                <input type="hidden" name="case_scope" id="case_scope_hidden">

                                <div class="d-flex gap-2 p-1" style="cursor: not-allowed;">
                                    <input type="radio" class="btn-check" name="case_scope" id="scope_property" value="PROPERTY" disabled>
                                    <label class="btn btn-outline-primary flex-fill py-2 fw-bold shadow-sm" for="scope_property">คดีทรัพย์</label>

                                    <input type="radio" class="btn-check" name="case_scope" id="scope_life" value="LIFE" disabled>
                                    <label class="btn btn-outline-primary flex-fill py-2 fw-bold shadow-sm" for="scope_life">คดีชีวิต</label>

                                    <input type="radio" class="btn-check" name="case_scope" id="scope_explosive" value="EXPLOSIVE" disabled>
                                    <label class="btn btn-outline-primary flex-fill py-2 fw-bold shadow-sm" for="scope_explosive">คดีระเบิด</label>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border mb-3">
                        <legend class="fieldset-header"><i class="fas fa-users me-1"></i> รายชื่อผู้รับการประเมิน</legend>

                        <?php
                        $roles = [
                            ['id' => 'leader_id', 'label' => 'หัวหน้าทีม', 'pos_id' => 'leader_pos'],
                            ['id' => 'photographer_id', 'label' => 'ช่างภาพ', 'pos_id' => 'photographer_pos'],
                            ['id' => 'map_maker_id', 'label' => 'ผู้ทำแผนที่', 'pos_id' => 'map_maker_pos'],
                            ['id' => 'searcher_id', 'label' => 'ผู้ค้นหาวัตถุพยาน', 'pos_id' => 'searcher_pos'],
                            ['id' => 'collector_id', 'label' => 'ผู้ตรวจเก็บวัตถุพยาน', 'pos_id' => 'collector_pos']
                        ];

                        foreach ($roles as $role) {
                        ?>
                            <div class="row g-3 mb-3 align-items-end">
                                <div class="col-7">
                                    <label for="<?php echo $role['id']; ?>" class="form-label"><?php echo $role['label']; ?></label>
                                    <select id="<?php echo $role['id']; ?>" name="<?php echo $role['id']; ?>" class="form-select select2-modal user-select-action" data-target="#<?php echo $role['pos_id']; ?>" data-placeholder="-- ค้นหาชื่อเจ้าหน้าที่ --"
                                        required>
                                        <option value=""></option>
                                        <?php echo $userOptions; ?>
                                    </select>
                                    <!-- <div class="invalid-feedback">กรุณาเลือก<?php echo $role['label']; ?></div> -->
                                </div>
                                <div class="col-5">
                                    <label for="<?php echo $role['pos_id']; ?>" class="form-label">ตำแหน่ง</label>
                                    <input type="text" id="<?php echo $role['pos_id']; ?>" class="form-control bg-light" readonly tabindex="-1" >
                                </div>
                            </div>
                        <?php } ?>
                    </fieldset>

                    <div class="alert alert-secondary d-flex align-items-center mb-0" role="alert">
                        <i class="fas fa-info-circle me-2 fs-5"></i>
                        <div class="small">
                            เมื่อบันทึกข้อมูลเบื้องต้นแล้ว ระบบจะนำคุณไปยังหน้า <b>"รายละเอียดการประเมิน"</b> เพื่อประเมินความความสามารถของเจ้าหน้าที่ในขั้นตอนถัดไป
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer d-flex justify-content-end bg-white border-top shadow-sm">
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="assessmentHeaderForm">
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
<script src="js/report_no_th.js"></script>
<script>
    window._sessionUserId = '<?= $_SESSION["user_id"] ?>';
    window._canManage = <?= $canManage ? 'true' : 'false' ?>;
    const assessmentRoles = [
        'leader_id',
        'photographer_id',
        'map_maker_id',
        'searcher_id',
        'collector_id'
    ];

    function getExcludedUserIds(currentSelectId) {
        let excludedIds = [];
        assessmentRoles.forEach(id => {
            if (id !== currentSelectId) {
                const val = $('#' + id).val();
                if (val) excludedIds.push(val);
            }
        });
        return excludedIds;
    }

    $(document).ready(function() {
        searchPage(1);

        // =======================================================
        // Initialize Select2
        // =======================================================

        // รวบ Class ที่ต้องการ Initialize เข้าด้วยกัน
        $('.select2-report-filter, .select2-report-modal').each(function() {
            const $el = $(this);

            // ตั้งค่าพื้นฐานที่เหมือนกัน
            let select2Config = {
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '-- ค้นหาเลขรายงาน --',
                allowClear: true,
                ajax: {
                    url: './api/Transaction_staff_assessment/get_incident_reports.php',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        q: params.term
                    }),
                    processResults: data => ({
                        results: data.results
                    }),
                    cache: true
                }
            };

            // --- แยกเงื่อนไขเฉพาะจุด ---

            // 1. ถ้าเป็นตัวใน Modal ให้ใส่ dropdownParent (เพื่อป้องกันปัญหาเลื่อนไม่ได้หรือจิ้มไม่ติด)
            if ($el.hasClass('select2-report-modal')) {
                select2Config.dropdownParent = $('#addEditAssessmentModal');
            }

            // 2. สั่ง Initialize
            $el.select2(select2Config);

            // 3. ถ้าเป็นตัวใน Filter ให้ใส่คลาสแต่งขนาดเล็กเพิ่มเติม (หลังจาก Init เสร็จ)
            if ($el.hasClass('select2-report-filter')) {
                $el.next('.select2-container').addClass('select2-sm-custom');
            }
        });

        // สำหรับ Modal: เมื่อเลือกเลขรายงานแล้วให้ Auto-select ขอบข่ายคดี
        $('#report_no').on('select2:select', function(e) {
            const data = e.params.data;
            const type = data.complaints_type; // ค่า 01, 02, 03 จาก API

            // Mapping Table
            const mapping = {
                '01': 'PROPERTY',
                '02': 'LIFE',
                '03': 'EXPLOSIVE'
            };

            const targetScope = mapping[type];

            // --- ล้างสถานะเดิมทิ้งทั้งหมดก่อน ---
            // ปลด checked ของ radio ทั้งหมด
            $('input[name="case_scope"]').prop('checked', false);
            // คืนค่าสี Label ให้เป็นแบบ Outline ทั้งหมด
            $('input[name="case_scope"]').each(function() {
                let labelId = $(this).attr('id');
                $(`label[for="${labelId}"]`).removeClass('btn-primary').addClass('btn-outline-primary');
            });
            $('#case_scope_hidden').val('');

            if (targetScope) {
                // สั่ง Check ที่ Radio Button ตัวที่มี value ตรงกับที่ map ได้
                const $targetInput = $(`input[name="case_scope"][value="${targetScope}"]`);

                // สั่ง Check
                $targetInput.prop('checked', true);

                // เปลี่ยนสี Label ของตัวที่ถูกเลือก
                const targetId = $targetInput.attr('id');
                $(`label[for="${targetId}"]`).removeClass('btn-outline-primary').addClass('btn-primary');

                // หยอดค่าลง Hidden Input (เพื่อส่งฟอร์ม)
                $('#case_scope_hidden').val(targetScope);
            }
        });

        $('#report_no').on('select2:clear', function() {
            // 1. ยกเลิกการเลือก (Uncheck) Radio ทั้งหมด
            $('input[name="case_scope"]').prop('checked', false);

            // 2. คืนค่าสีปุ่ม Label ให้เป็นแบบ Outline (สีจาง) ทั้งหมด
            $('input[name="case_scope"]').each(function() {
                let id = $(this).attr('id');
                // ลบสีเข้ม (btn-primary) ออก และใส่สีเส้นขอบ (btn-outline-primary) กลับเข้าไป
                $(`label[for="${id}"]`).removeClass('btn-primary').addClass('btn-outline-primary');
            });

            $('#case_scope_hidden').val('');

        });

        // สำหรับ Filter: เมื่อเลือกเลขรายงานแล้วให้ Auto-select ขอบข่ายคดี
        $('#filter_report_no').on('select2:select', function(e) {
            const data = e.params.data;
            const type = data.complaints_type; // ค่า 01, 02, 03 จาก API

            const mapping = {
                '01': 'PROPERTY',
                '02': 'LIFE',
                '03': 'EXPLOSIVE'
            };

            const targetScope = mapping[type];

            if (targetScope) {
                // เปลี่ยนค่าใน Dropdown ขอบข่ายที่ตรวจ ของส่วน Filter
                $('#filter_case_scope').val(targetScope).trigger('change');
            }
        });

        // เมื่อล้างค่าเลขรายงานใน Filter
        $('#filter_report_no').on('select2:clear', function() {
            // ล้างค่าขอบข่ายกลับเป็น "-- ทั้งหมด --"
            $('#filter_case_scope').val('').trigger('change');
        });

        // วนลูป Initialize Select2 ทีละอัน
        assessmentRoles.forEach(id => {
            $('#' + id).select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $('#addEditAssessmentModal'),
                allowClear: true,
                placeholder: '-- กรุณาเลือก --',
                ajax: {
                    url: './api/get_users.php', // ปรับ Path ให้ตรงกับไฟล์จริงของคุณ
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        q: params.term
                    }),
                    processResults: function(response) {
                        // ดึง ID ของคนอื่นๆ ที่ถูกเลือกไปแล้ว
                        const excludedIds = getExcludedUserIds(id);

                        return {
                            results: $.map(response.data, function(item) {
                                return {
                                    id: item.user_id,
                                    text: item.fullname,
                                    // ดึงตำแหน่งมาเก็บไว้ในก้อนข้อมูล (สำหรับ Auto-fill)
                                    position: item.position_name,
                                    // --- ถ้าคนนี้ถูกเลือกในตำแหน่งอื่นไปแล้ว ให้ Disable ทันที ---
                                    disabled: excludedIds.includes(item.user_id.toString())
                                };
                            })
                        };
                    },
                    cache: true
                }
            });
        });

        // สำหรับ Select ตัวกรอง (Filter) ทั่วไป
        $('.select2-filter').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true,
        }).next('.select2-container').addClass('select2-sm-custom');

        // =======================================================
        // Logic: เลือกชื่อแล้ว Auto-fill ตำแหน่ง
        // =======================================================

        // 1. เมื่อมีการเลือกข้อมูล (Select)
        $('.user-select-action').on('select2:select', function(e) {
            // ดึงข้อมูลจาก event object ของ select2 โดยตรง (แม่นยำกว่า)
            let data = e.params.data;

            // ดึงค่าจาก attribute data-position ของ <option> ต้นทาง
            var position = data.position;

            // หา target input ที่จะเอาค่าไปใส่
            let targetId = $(this).data('target');

            if (targetId) {
                $(targetId).val(position || '-');
            }
        });

        // 2. เมื่อมีการลบข้อมูลออก (Clear / Unselect)
        $('.user-select-action').on('select2:clear select2:unselect', function(e) {
            let targetId = $(this).data('target');
            if (targetId) {
                $(targetId).val(''); // เคลียร์ค่าตำแหน่งออกด้วย
            }
        });

        // Event: เมื่อมีการเปลี่ยนค่าใน Radio Button (ขอบข่ายที่ตรวจ)
        $('input[name="case_scope"]').on('change', function() {
            // 1. ลบขอบแดงออกจากกรอบ d-flex
            $(this).closest('.d-flex').removeClass('border-danger');

            // 2. ซ่อนข้อความแจ้งเตือน (invalid-feedback)
            // ต้องหา parent col-md-12 ก่อน แล้วค่อยหา feedback เพราะ feedback อยู่นอก d-flex
            $(this).closest('.col-md-12').find('.invalid-feedback').hide(); // หรือ .fadeOut() นุ่มๆ ก็ได้
        });
    });

    // เพิ่ม Event เมื่อกดปุ่ม "เพิ่มรายการ"
    $(document).on('click', '.js-add-assessment-btn', function() {
        const $form = $('#assessmentHeaderForm');
        // --- ล้างค่า Input ทั่วไป ---
        $form[0].reset();
        $('#edit_id').val('');
        $form.removeClass('was-validated');

        // 2. รีเซ็ตค่าใน Select2 (เพราะ reset() ธรรมดาไม่เคลียร์ Select2)
        $('.select2-modal, .select2-report-modal').val(null).trigger('change');

        // --- ล้างสถานะปุ่ม Radio (ขอบข่ายคดี) และสี Label ---
        $('input[name="case_scope"]').prop('checked', false);
        $('input[name="case_scope"]').each(function() {
            let id = $(this).attr('id');
            $(`label[for="${id}"]`).removeClass('btn-primary').addClass('btn-outline-primary');
        });

        // --- ล้างค่าในช่อง Position (readonly) --- (ID ที่ลงท้ายด้วย _pos ตามโครงสร้างที่ทำไว้)
        $('input[id$="_pos"]').val('');

        // เปลี่ยนหัวข้อ Modal กลับเป็น "เพิ่มรายการ"
        $('#textModal').text('เพิ่มรายการ');
        $('#addEditAssessmentModalLabel i').removeClass('fa-edit').addClass('fa-plus');
    });

    // ========================================================================
    // 1. ปุ่มลบ (Delete) - ยังคงใช้ Modal ยืนยันเหมือนเดิม
    // ========================================================================

    $(document).on('click', '.js-delete-assessment-btn', function(e) {
        e.preventDefault();
        const dataId = $(this).data('id');
        const reportNo = $(this).data('report');
        const opDate = $(this).data('date');
        const location = $(this).data('location');
        const scope = $(this).data('scope');

        if (!dataId) {
            Swal.fire('Error', 'ไม่พบ ID ของรายการ', 'error');
            return;
        }

        Swal.fire({
            title: 'ยืนยันการลบ?',
            html: `
                <div class="text-center mb-3">คุณต้องการลบข้อมูลการประเมินนี้ใช่หรือไม่?</div>
                <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">เลขรายงาน:</span><br>
                        <b class="ps-2 text-danger">${window.toThaiReportNo ? toThaiReportNo(reportNo) : reportNo}</b>
                    </div>
                    
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">วันที่ดำเนินการ:</span><br>
                        <b class="ps-2 text-danger">${opDate}</b>
                    </div>

                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">ขอบข่าย:</span><br>
                        <b class="ps-2 text-danger">${scope}</b>
                    </div>

                    <div class="mb-2 pb-2">
                        <span class="text-secondary small">สถานที่ดำเนินการ:</span><br>
                        <div class="fw-bold ps-2 text-danger text-truncate" style="max-width: 250px;">${location}</div>
                    </div>
                </div>

                <div class="alert alert-warning small mt-3 mb-0 border-0 shadow-sm text-start">
                    <i class="fas fa-exclamation-triangle me-1"></i> 
                    <b>ข้อมูลการให้คะแนนทั้งหมด</b> ในรายการนี้จะถูกลบออกถาวรและไม่สามารถกู้คืนได้
                </div>

                <div class="mt-3 text-center">
                    <small class="text-muted italic" style="font-size: 0.75rem;">
                        *หมายเหตุ: หากสถานะเป็นเสร็จสมบูรณ์ (Completed) จะไม่สามารถลบได้
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
                    url: './api/Transaction_staff_assessment/header/delete.php', // เปลี่ยน path ให้ถูก
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
                    Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message || error.statusText}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading() // ป้องกันการกดปิดระหว่างโหลด
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'ลบข้อมูลสำเร็จ!',
                    text: 'รายการประเมินถูกลบเรียบร้อยแล้ว',
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
    // 2. ปุ่มแก้ไข/ประเมิน (Edit) -> พาไปหน้า Detail
    // ========================================================================

    $(document).on('click', '.clickable-row', function(e) {
        // ป้องกันการเปลี่ยนหน้าเมื่อคลิกโดนปุ่มแก้ไข, ลบ หรือ พิมพ์
        if ($(e.target).closest('.js-delete-assessment-btn, .js-print-btn').length) {
            return;
        }

        let dataId = $(this).data('id');
        // คลิกที่แถว พาไปหน้าทำแบบประเมินทันที (แทนปุ่ม View เดิม)
        window.location.href = `trans_staff_assessment_detail.php?id=${dataId}`;
    });

    // ========================================================================
    // 3. ปุ่มพิมพ์ (Print) -> เปิดหน้า PDF
    // ========================================================================

    $(document).on('click', '.js-print-btn', function() {
        let dataId = $(this).data('id');
        let reportNo = $(this).data('report') || 'Staff-Assessment'; // ดึงเลขรายงานมา

        // ทำความสะอาดชื่อไฟล์ (ลบอักขระพิเศษที่อาจทำให้ URL พัง)
        let safeReportNo = reportNo.toString().replace(/[/\\?%*:|"<>]/g, '-');
        let fileName = `F-CS-22_${safeReportNo}.pdf`;

        // เปิดหน้า PDF ใน Tab ใหม่
        window.open(`./api/Transaction_staff_assessment/gen_pdf.php/${fileName}?id=${dataId}`, '_blank');
    });

    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    $(document).on('click', '#btn_export_excel', function(e) {
        e.preventDefault(); // กันไว้ก่อนเผื่อปุ่มอยู่ใน Form submit

        // ดึงค่าจากฟอร์มทั้งหมดมาทำเป็น Query String
        const formData = $('#searchFilterForm').serialize();

        // ยิงไปที่ API
        window.location.href = './api/Transaction_staff_assessment/exportExcel.php?' + formData;
    });

    // saveData
    $('#assessmentHeaderForm').on('submit', function(e) {
        e.preventDefault();
        // Validate Form ก่อนส่งไปยัง backend 
        const form = this;

        if (!form.checkValidity()) {
            e.stopPropagation();
            $(form).addClass('was-validated');

            if ($('input[name="case_scope"]:checked').length === 0) {
                $('input[name="case_scope"]').closest('.d-flex').addClass('border-danger'); // ใส่ขอบแดง
                $('input[name="case_scope"]').closest('.col-md-12').find('.invalid-feedback').show();
            } else {
                $('input[name="case_scope"]').closest('.d-flex').removeClass('border-danger');
                $('input[name="case_scope"]').closest('.col-md-12').find('.invalid-feedback').hide();
            }

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

                    let isNewRecord = !$('#edit_id').val();

                    // 1. กำหนดข้อความให้สอดคล้องกับสิ่งที่จะเกิดขึ้นจริง
                    let swalText = isNewRecord ?
                        'กำลังนำคุณไปหน้ารายละเอียดการประเมินเจ้าหน้าที่...' :
                        'ข้อมูลถูกปรับปรุงเรียบร้อยแล้ว';

                    let swalTimer = isNewRecord ?
                        2000 : 1500;

                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกสำเร็จ',
                        text: swalText,
                        timer: swalTimer,
                        showConfirmButton: false
                    }).then(() => {
                        if (isNewRecord && response.insert_id) {
                            // กรณีเพิ่มใหม่: พาไปหน้า Detail เพื่อประเมินต่อทันที
                            window.location.href = 'trans_staff_assessment_detail.php?id=' + response.insert_id;
                        } else {
                            // กรณีแก้ไข: ปิด Modal และโหลดตารางหน้าเดิมใหม่
                            $('#addEditAssessmentModal').modal('hide');
                            const currentPage = parseInt($('#pagination-list .active a').text()) || 1;
                            if (typeof searchPage === "function") {
                                searchPage(isNewRecord ? currentPage : 1);
                            }
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

    function searchPage(page) {
        const formData = $('#searchFilterForm').serializeArray();
        formData.push({
            name: 'page',
            value: page
        });

        // รอเปลี่ยน API
        $.ajax({
            url: './api/Transaction_staff_assessment/header/searchData.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status == 'success') {
                    renderTable(response.data, response.offset);
                    $('#count_display').text(response.count);
                    const totalPages = parseInt(response.totalPages);
                    const currentPage = parseInt(response.currentPage);

                    $('#pagination-list').empty();

                    if (totalPages > 0) {
                        setupPagination(totalPages, currentPage);
                    } else {
                        // กรณีไม่พบข้อมูลเลย
                        $('#pagination-list').html('');
                    }
                } else {
                    $('#table_body').html(`<tr><td colspan="8" class="text-center text-danger py-5"><i class="fas fa-triangle-exclamation fa-2x mb-2"></i><br>${response.message || 'เกิดข้อผิดพลาดในการดึงข้อมูล'}</td></tr>`);
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
        let start_no = (isNaN(parseInt(offset))) ? 1 : parseInt(offset) + 1;

        if (data && data.length > 0) {
            data.forEach(function(row, i) {

                let isCompleted = row.status === 'COMPLETED';
                const totalStaff = parseInt(row.total_active_staff) || 0;
                const evaluated = parseInt(row.evaluated_count) || 0;

                // --- 1. จัดการ Badge สถานะ (Logic ใหม่) ---
                let statusBadge = '';

                if (isCompleted) {
                    // กรณี: ประเมินครบและลงนามจบงานแล้ว
                    statusBadge = `<span class="badge rounded-pill bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-3 py-2" style="font-size: 0.8rem;">เสร็จสมบูรณ์</span>`;
                } else if (evaluated >= totalStaff) {
                    // กรณี: ประเมินครบ 5 คนแล้ว แต่ยังไม่ได้กดปุ่มจบงาน/ลงนาม
                    statusBadge = `
                    <div class="d-flex flex-column align-items-center">
                        <span class="badge rounded-pill bg-info bg-opacity-25 text-info border border-info border-opacity-50 px-3 py-2" style="font-size: 0.8rem;">
                            รอลงนามรับรอง
                        </span>
                    </div>`;
                } else {
                    // กรณี: ยังประเมินไม่ครบ (แสดง Progress X/5)
                    statusBadge = `
                    <div class="d-flex flex-column align-items-center">
                        <span class="badge rounded-pill bg-warning bg-opacity-25 text-dark border border-warning border-opacity-50 px-3 py-2" style="font-size: 0.8rem;">
                            รอประเมิน (${evaluated}/${totalStaff})
                        </span>
                    </div>`;
                }

                // 2. แปลงขอบข่าย (Case Scope) เป็นภาษาไทย
                const scopeMap = {
                    'PROPERTY': '<span>คดีทรัพย์</span>',
                    'LIFE': '<span>คดีชีวิต</span>',
                    'EXPLOSIVE': '<span>คดีระเบิด</span>',
                };
                let scopeDisplay = scopeMap[row.case_scope] || row.case_scope;

                // 3. ปุ่ม Export PDF 
                let btnStyle = isCompleted ?
                    'background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;' :
                    'box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); font-size: 11px; white-space: nowrap;';

                let printBtnHtml = `
                    <button type="button" class="js-print-btn btn ${isCompleted ? '' : 'btn-secondary'} shadow-sm" 
                        style="${btnStyle}" data-id="${row.id}" data-report="${row.report_no}" ${isCompleted ? '' : 'disabled'} title="${isCompleted ? 'พิมพ์รายงาน F-CS-22' : 'ยังไม่เสร็จสมบูรณ์'}">
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
                    // 2. ถ้ายังไม่เสร็จ: แสดงปุ่ม "ลบ" ตามปกติ
                    actionButtons = `
                            <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-assessment-btn cursor-pointer' : ''}" 
                            data-id="${row.id}" 
                            data-report="${row.report_no}" 
                            data-date="${row.operation_date_show || '-'}"
                            data-location="${row.location || '-'}"
                            data-scope="${scopeDisplay || '-'}"
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
                    <td class="text-center align-middle">${row.operation_date_show || '-'}</td>
                    <td class="text-start align-middle">
                        <div class="report-no-responsive text-dark" style="font-size: 0.95rem;">${(window.toThaiReportNo ? toThaiReportNo(row.report_no) : row.report_no) || '-'}</div>
                        
                        <div class="d-xl-none text-muted mt-1" style="font-size: 0.75rem;">
                            ผู้ประเมิน: ${row.evaluator_fullname || '-'}
                        </div>
                    </td>

                    <td class="text-start align-middle d-none d-lg-table-cell text-truncate" style="max-width: 200px;">
                        ${row.location || '-'}
                    </td>
                    <td class="text-center align-middle">${scopeDisplay}</td>
                    <td class="text-start align-middle d-none d-xl-table-cell">
                        ${row.evaluator_fullname || '-'}
                    </td>
                    <td class="text-center align-middle">${statusBadge}</td>
                    <td class="text-center align-middle">
                        ${printBtnHtml}
                    </td>
                </tr>
            `;
            });
        } else {
            html = `
            <tr>
                <td colspan="9" class="text-center py-5">
                    <div class="text-muted">
                        <i class="fas fa-file-signature fa-3x mb-3 opacity-50"></i>
                        <p class="mb-0 fw-bold">ไม่พบรายการประเมินในระบบ</p>
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
        // setDefaultDate();
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