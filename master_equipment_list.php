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

$title = "รายการเครื่องมือ";

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
                        <div class="col-12 col-md-6 col-lg-4 col-xl-4">
                            <button class="btn <?= $canManage ? 'btn-success' : 'btn-secondary' ?> btn-sm px-4 fw-bold shadow-sm text-nowrap w-100 <?= $canManage ? 'js-add-equipment-btn' : '' ?>"
                                style="height: 31px;"
                                type="button" <?= $canManage ? 'data-bs-toggle="modal" data-bs-target="#addEditEquipmentModal"' : 'disabled' ?>
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

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_equipment_category" class="form-label text-muted small mb-1">ประเภทเครื่องมือ</label>
                            <select id="filter_equipment_category" name="filter_equipment_category" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                <option value=""></option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_equipment_asset_no" class="form-label text-muted small mb-1">เลขครุภัณฑ์</label>
                            <input type="text" id="filter_equipment_asset_no" name="filter_equipment_asset_no" class="form-control form-control-sm" placeholder="ระบุเลขครุภัณฑ์...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_equipment_name" class="form-label text-muted small mb-1">ชื่อเครื่องมือ</label>
                            <input type="text" id="filter_equipment_name" name="filter_equipment_name" class="form-control form-control-sm" placeholder="ระบุชื่อเครื่องมือ...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_equipment_brand_model" class="form-label text-muted small mb-1">ยี่ห้อ / รุ่น</label>
                            <input type="text" id="filter_equipment_brand_model" name="filter_equipment_brand_model" class="form-control form-control-sm" placeholder="เช่น Nikon D7500, Alumina...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_equipment_serial" class="form-label text-muted small mb-1">Serial No. (S/N)</label>
                            <input type="text" id="filter_equipment_serial" name="filter_equipment_serial" class="form-control form-control-sm" placeholder="ระบุเลข Serial...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_responsible_id" class="form-label text-muted small mb-1">ผู้ดูแลเครื่องมือ</label>
                            <select id="filter_responsible_id" name="filter_responsible_id" class="form-select form-select-sm select2-filter" data-placeholder="-- ทั้งหมด --">
                                <option value=""></option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                            <label for="filter_install_date" class="form-label text-muted small mb-1">วันที่รับมา / ติดตั้ง</label>
                            <input type="date" id="filter_install_date" name="filter_install_date" class="form-control form-control-sm">
                        </div>

                        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
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
                    <th class="d-none d-lg-table-cell" style="min-width: 150px;">ประเภทเครื่องมือ</th>
                    <th style="min-width: 200px;">ชื่อเครื่องมือ</th>
                    <th class="d-none d-xxl-table-cell" style="min-width: 200px;">ยี่ห้อ / รุ่น</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 100px;">Serial No. (S/N)</th>
                    <th class="d-none d-xl-table-cell" style="min-width: 150px;">ผู้ดูแลเครื่องมือ</th>
                    <th class="d-none d-md-table-cell" style="min-width: 120px;">วันที่รับมา / ติดตั้ง</th>
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

<!-- Modal สำหรับเพิ่มรายการ equipment ใหม่ / แก้ไขรายการเดิม -->
<div class="modal fade" id="addEditEquipmentModal" aria-labelledby="addEditEquipmentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addEditEquipmentModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> <span id="textModal">เพิ่มรายการ</span>
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" id="equipmentHistoryForm" action="./api/Master_equipment_list/save.php" novalidate>
                    <input type="hidden" id="edit_id" name="id">

                    <fieldset class="bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header"><i class="fas fa-info-circle me-1"></i> ข้อมูลทั่วไปของเครื่องมือ</legend>
                        <div class="row g-3 justify-content-start">

                            <div class="col-12 col-md-6 <?= ($user_role !== 'admin') ? 'd-none' : '' ?>">
                                <label for="department_id" class="form-label fw-bold">หน่วยงาน <span class="text-danger">*</span></label>
                                <select id="department_id" name="department_id" class="form-select select2-dept" data-placeholder="-- ค้นหาหน่วยงาน --" <?= ($user_role === 'admin') ? 'required' : '' ?>>
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาระบุหน่วยงาน</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="equipment_category" class="form-label fw-bold">ประเภทเครื่องมือ <span class="text-danger">*</span></label>
                                <select id="equipment_category" name="equipment_category" class="form-select select2-user" data-placeholder="-- ค้นหาประเภทเครื่องมือ --" required>
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาระบุประเภทเครื่องมือ</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="equipment_name" class="form-label">ชื่อเครื่องมือ <span class="text-danger">*</span></label>
                                <div class="input-group input-group-seamless">
                                    <input type="text" id="equipment_name" name="equipment_name" class="form-control" required autocomplete="off" maxlength="255" placeholder="เช่น กล้องถ่ายภาพดิจิทัล, เครื่องตรวจโลหะ">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="equipment_name"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุชื่อเครื่องมือ</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="asset_no" class="form-label">เลขครุภัณฑ์ <span class="text-danger">*</span></label>
                                <div class="input-group input-group-seamless">
                                    <input type="text"
                                        id="asset_no"
                                        name="asset_no"
                                        class="form-control"
                                        autocomplete="off"
                                        maxlength="100"
                                        required
                                        placeholder="ระบุเลขครุภัณฑ์ (เช่น ค.123/67)">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="asset_no"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุเลขครุภัณฑ์</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="equipment_brand" class="form-label">ยี่ห้อ (Brand)</label>
                                <div class="input-group input-group-seamless">
                                    <input type="text"
                                        id="equipment_brand"
                                        name="equipment_brand"
                                        class="form-control"
                                        autocomplete="off"
                                        maxlength="100"
                                        placeholder="เช่น Nikon, Bosch, Garmin">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="equipment_brand"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="equipment_model" class="form-label">รุ่น (Model)</label>
                                <div class="input-group input-group-seamless">
                                    <input type="text"
                                        id="equipment_model"
                                        name="equipment_model"
                                        class="form-control"
                                        autocomplete="off"
                                        maxlength="100"
                                        placeholder="ระบุชื่อรุ่นอุปกรณ์">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="equipment_model"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="equipment_serial" class="form-label">Serial No. (S/N)</label>
                                <div class="input-group input-group-seamless">
                                    <input type="text"
                                        id="equipment_serial"
                                        name="equipment_serial"
                                        class="form-control text-uppercase"
                                        autocomplete="off"
                                        maxlength="100"
                                        placeholder="ระบุเลข S/N จากตัวเครื่อง"
                                        oninput="this.value = this.value.replace(/\s/g, '').toUpperCase()">
                                    <button type="button"
                                        class="btn btn-hw-open"
                                        data-hw-targets="equipment_serial"
                                        title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-12 <?= ($user_role !== 'admin') ? 'col-lg-12' : 'col-lg-6' ?>">
                                <label for="responsible_id" class="form-label">ผู้ดูแลเครื่องมือ <span class="text-danger">*</span></label>
                                <select id="responsible_id" name="responsible_id" class="form-select select2-user" data-placeholder="-- ค้นหาชื่อผู้ดูแลเครื่องมือ --" required>
                                    <option value=""></option>
                                </select>
                                <div class="invalid-feedback">กรุณาระบุผู้ดูแลเครื่องมือ</div>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">อุปกรณ์ส่วนควบ</label>
                                <div class="row g-2">
                                    <div class="col-12 col-lg-4">
                                        <div class="input-group input-group-seamless">
                                            <span class="input-group-text">1.</span>
                                            <input type="text" class="form-control" name="accessories_1" id="accessories_1"
                                                placeholder="เช่น กระเป๋าใส่เครื่อง, สายชาร์จ, แบตเตอรี่สำรอง">
                                            <button type="button"
                                                class="btn btn-hw-open"
                                                data-hw-targets="accessories_1"
                                                title="เขียนด้วยลายมือ">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-4">
                                        <div class="input-group input-group-seamless">
                                            <span class="input-group-text">2.</span>
                                            <input type="text" class="form-control" name="accessories_2" id="accessories_2"
                                                placeholder="ระบุอุปกรณ์เพิ่มเติม (ถ้ามี)">
                                            <button type="button"
                                                class="btn btn-hw-open"
                                                data-hw-targets="accessories_2"
                                                title="เขียนด้วยลายมือ">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-4">
                                        <div class="input-group input-group-seamless">
                                            <span class="input-group-text">3.</span>
                                            <input type="text" class="form-control" name="accessories_3" id="accessories_3"
                                                placeholder="ระบุอุปกรณ์เพิ่มเติม (ถ้ามี)">
                                            <button type="button"
                                                class="btn btn-hw-open"
                                                data-hw-targets="accessories_3"
                                                title="เขียนด้วยลายมือ">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="install_date" class="form-label">วันที่รับมา / ติดตั้ง</label>
                                <input type="date" id="install_date" name="install_date" class="form-control">
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="start_use_date" class="form-label">วันที่เริ่มใช้งาน</label>
                                <input type="date" id="start_use_date" name="start_use_date" class="form-control">
                                <div class="invalid-feedback" id="start_use_error">
                                    วันที่เริ่มใช้งานห้ามก่อนวันที่รับมา/ติดตั้ง
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-end bg-white border-top shadow-sm">
                <button type="submit" class="btn btn-success px-4" id="btn_save_all" form="equipmentHistoryForm">
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
                    <i class="fas fa-search me-2"></i> รายละเอียดเครื่องมือ
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-12">
                        <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                            <i class="fas fa-info-circle me-1"></i> ข้อมูลเครื่องมือ
                        </h6>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="text-muted small d-block">หน่วยงาน</label>
                                <span id="view_department_name" class="fw-bold">-</span>
                            </div>
                            <div class="col-6 col-md-6 mb-3">
                                <label class="text-muted small d-block">เลขครุภัณฑ์</label>
                                <span id="view_asset_no" class="fw-bold">-</span>
                            </div>
                            <div class="col-6 col-md-6 mb-3">
                                <label class="text-muted small d-block">ประเภทเครื่องมือ</label>
                                <span id="view_category" class="fw-bold">-</span>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="text-muted small d-block">ชื่อเครื่องมือ</label>
                                <span id="view_tool_name" class="fw-bold">-</span>
                            </div>
                            <div class="col-6 col-md-6 mb-3">
                                <label class="text-muted small d-block">ยี่ห้อ (Brand)</label>
                                <span id="view_brand" class="fw-bold">-</span>
                            </div>
                            <div class="col-6 col-md-6 mb-3">
                                <label class="text-muted small d-block">รุ่น (Model)</label>
                                <span id="view_model" class="fw-bold">-</span>
                            </div>
                            <div class="col-6 col-md-6 mb-3">
                                <label class="text-muted small d-block">Serial No. (S/N)</label>
                                <span id="view_serial_no" class="fw-bold">-</span>
                            </div>
                            <div class="col-6 col-md-6 mb-3">
                                <label class="text-muted small d-block">ผู้ดูแลเครื่องมือ</label>
                                <span id="view_responsible" class="fw-bold">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="row">
                            <div class="col-md-7 mb-3">
                                <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                                    <i class="fas fa-tools me-1"></i> อุปกรณ์ส่วนควบ
                                </h6>
                                <div class="p-2 bg-light rounded border border-dashed text-secondary" style="min-height: 50px;">
                                    <span id="view_accessories" class="small">-</span>
                                </div>
                            </div>
                            <div class="col-md-5 mb-3">
                                <h6 class="text-primary fw-bold border-bottom pb-2 mb-3">
                                    <i class="far fa-calendar-alt me-1"></i> วันที่สำคัญในระบบ
                                </h6>
                                <div class="row small g-2">
                                    <div class="col-12">
                                        <span class="text-muted">วันที่รับมา / ติดตั้ง:</span>
                                        <span id="view_date_receive" class="fw-bold float-end">-</span>
                                    </div>
                                    <div class="col-12">
                                        <span class="text-muted">วันที่เริ่มใช้งาน:</span>
                                        <span id="view_date_start_use" class="fw-bold float-end">-</span>
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
        searchPage(1);

        if ($('#filter_department_id').length) {
            loadDepartments();
        }
        loadEquipmentCategories();
        loadUserList();

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
                dropdownParent: $('#addEditEquipmentModal')
            });
        }

        if ($('.select2-dept').length) {
            $('.select2-dept').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                dropdownParent: $('#addEditEquipmentModal')
            });
        }

        // 1. ดัก Event ตอนเปิด Modal (ทั้ง Add และ Edit)
        $('#addEditEquipmentModal').on('show.bs.modal', function() {
            // รอให้ข้อมูลใน Input ถูกเติมก่อน (กรณี Edit) แล้วค่อยตั้งค่า min
            setTimeout(() => {
                const installDate = $('#install_date').val();
                if (installDate) {
                    $('#start_use_date').attr('min', installDate);
                }
            }, 100); // delay นิดนึงเพื่อให้ข้อมูลจากปุ่ม Edit เติมลง input ทัน
        });

        // 2. ดัก Event ตอนปิด Modal (เพื่อ Reset ค่า)
        $('#addEditEquipmentModal').on('hidden.bs.modal', function() {
            // ล้างค่า min ออก
            $('#start_use_date').removeAttr('min');
            // ล้าง Class validation ต่างๆ (ถ้ามี)
            $(this).find('form').removeClass('was-validated');
            $(this).find('.is-invalid').removeClass('is-invalid');
        });

        // 3. Event Change ทำงาน Real-time ตอนผู้ใช้เลือกวันที่ใหม่
        $('#install_date, #start_use_date').on('change input', function() {
            checkDateValidation();
        });
    });

    function checkDateValidation() {
        const installDate = $('#install_date').val();
        const startUseDate = $('#start_use_date').val();
        const $startInput = $('#start_use_date');

        if (installDate && startUseDate) {
            if (new Date(startUseDate) < new Date(installDate)) {
                $startInput.addClass('is-invalid');
            } else {
                $startInput.removeClass('is-invalid');
            }
        } else {
            $startInput.removeClass('is-invalid');
        }

        // อัปเดตค่า min
        if (installDate) {
            $('#start_use_date').attr('min', installDate);
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

                    // อัปเดตทั้งใน Filter หน้าหลัก และ ฟอร์ม Modal
                    $('#filter_responsible_id, #responsible_id').html(options);
                    $('#filter_responsible_id').trigger('change');
                    $('#responsible_id').trigger('change');
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

                    // หยอดใส่ทั้งช่อง Filter และช่องใน Modal
                    $('#filter_equipment_category, #equipment_category').html(options);

                    // Trigger เพื่อให้ Select2 อัปเดตการแสดงผล
                    $('#filter_equipment_category').trigger('change');
                    $('#equipment_category').trigger('change');
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

                    // หยอดใส่ทั้งช่อง Filter หน้าหลัก และช่องใน Modal
                    $('#filter_department_id, #department_id').html(options);

                    // Trigger เพื่อให้ Select2 อัปเดตการแสดงผล
                    $('#filter_department_id').trigger('change');
                    $('#department_id').trigger('change');
                }
            }
        });
    }

    // 1. สร้างฟังก์ชันสำหรับดึงข้อมูลและเปิด Modal
    function viewEquipmentDetail(id) {
        $.ajax({
            url: './api/Master_equipment_list/getDataByID.php',
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
                    $('#view_asset_no').text(d.asset_no || '-');
                    $('#view_category').text(d.category_name || '-');
                    $('#view_tool_name').text(d.tool_name);
                    $('#view_brand').text(d.brand);
                    $('#view_model').text(d.model);
                    $('#view_serial_no').text(d.serial_no);
                    $('#view_accessories').text(d.accessories || 'ไม่มีอุปกรณ์ส่วนควบ');
                    $('#view_date_receive').text(d.date_receive_show);
                    $('#view_date_start_use').text(d.date_start_use_show);
                    $('#view_responsible').html(d.responsible_name || '<span class="text-muted">ยังไม่ระบุ</span>');
                    $('#view_create_by').text(d.fullname_create || '-');
                    $('#view_create_date').text(d.createdate || '-');
                    $('#view_update_by').text(d.fullname_update || '-');
                    $('#view_update_date').text(d.updatedate || '');

                    $('#viewDetailModal').modal('show');
                }
            }
        });
    }

    // 2. ดักจับการคลิกที่แถว (โดยยกเว้นปุ่มจัดการอื่นๆ)
    $(document).on('click', '.clickable-row', function(e) {
        // ถ้าคลิกโดนปุ่มแก้ไขหรือลบ ให้หยุดทำงาน (ไม่เปิด Modal Detail)
        if ($(e.target).closest('.js-edit-btn, .js-delete-btn').length) {
            return;
        }

        const id = $(this).data('id');
        viewEquipmentDetail(id);
    });

    // เพิ่ม Event เมื่อกดปุ่ม "เพิ่มรายการ"
    $(document).on('click', '.js-add-equipment-btn', function() {
        const $form = $('#equipmentHistoryForm');

        // 1. Reset Form (ค่าพื้นฐานใน Input)
        $form[0].reset();

        // 2. เคลียร์ ID และสถานะ Validation
        $('#edit_id').val('');
        $form.removeClass('was-validated');
        $form.find('.is-invalid, .is-valid').removeClass('is-invalid is-valid');
        $('.input-group-seamless').removeClass('hw-active');

        // 3. เคลียร์ Select2 

        // เคลียร์ "หน่วยงาน"
        $('#department_id').val(null).trigger('change');

        // เคลียร์ "ประเภทเครื่องมือ"
        $('#equipment_category').val(null).trigger('change');

        // เคลียร์ "ผู้รับผิดชอบ"
        $('#responsible_id').val(null).trigger('change');

        // 4. เปลี่ยนหัวข้อกลับเป็น "เพิ่มรายการ"
        $('#textModal').text('เพิ่มรายการ');
        $('#addEditEquipmentModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายการ');

        // Default วันที่ติดตั้ง (เป็นวันนี้)
        $('#install_date').val(new Date().toISOString().split('T')[0]);

        if (typeof checkDateValidation === 'function') {
            checkDateValidation();
        }

        $('#addEditEquipmentModal').modal('show');
    });

    $(document).on('click', '.js-edit-btn', function() {
        let dataId = $(this).data('id');
        $.ajax({
            url: './api/Master_equipment_list/getDataByID.php',
            type: 'GET',
            data: {
                id: dataId
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    let data = res.data;

                    // เปลี่ยน Text หัวข้อ Modal
                    $('#textModal').text('แก้ไขรายการ');
                    $('#addEditEquipmentModalLabel').html('<i class="fas fa-edit fa-lg me-2"></i> แก้ไขรายการ');

                    // --- Map ข้อมูลให้ตรงกับ ID ใน Form เครื่องมือ ---

                    // 1. ข้อมูลเครื่องมือ

                    $('#department_id').val(data.department_id).trigger('change');
                    $('#equipment_name').val(data.tool_name);
                    $('#equipment_category').val(data.category_id).trigger('change');
                    $('#asset_no').val(data.asset_no);
                    $('#equipment_brand').val(data.brand);
                    $('#equipment_model').val(data.model);
                    $('#equipment_serial').val(data.serial_no);

                    $('#edit_id').val(data.id); // ใส่ ID เพื่อบอกว่านี่คือการ Edit
                    $('#responsible_id').val(data.responsible_id).trigger('change');
                    // 2. อุปกรณ์ส่วนควบ
                    if (data.accessories) {
                        let acc = data.accessories.split(','); // แยกด้วย comma
                        $('#accessories_1').val(acc[0] ? acc[0].trim() : '');
                        $('#accessories_2').val(acc[1] ? acc[1].trim() : '');
                        $('#accessories_3').val(acc[2] ? acc[2].trim() : '');
                    } else {
                        $('#accessories_1, #accessories_2, #accessories_3').val('');
                    }

                    // 3. วันที่
                    $('#install_date').val(data.date_receive);
                    $('#start_use_date').val(data.date_start_use);

                    checkDateValidation();

                    $('#addEditEquipmentModal').modal('show');
                }
            },
            error: function() {
                Swal.fire('Error', 'ไม่สามารถดึงข้อมูลได้', 'error');
            }
        });
    });

    $(document).on('click', '.js-delete-btn', function(e) {
        e.preventDefault();
        let dataId = $(this).data('id');
        let toolName = $(this).data('name');
        let assetNo = $(this).data('asset') || '-';

        // เช็คก่อนว่ามี ID ไหม 
        if (!dataId) {
            Swal.fire('Error', 'ไม่พบ ID ของรายการ', 'error');
            return;
        }

        Swal.fire({
            title: 'ยืนยันการลบ ?',
            html: `
                <div class="text-center mb-3">คุณต้องการลบรายการเครื่องมือนี้ใช่หรือไม่?</div>
                <div class="mt-2 small text-start p-3 bg-light rounded border shadow-sm">
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="text-secondary small">ชื่อเครื่องมือ:</span><br>
                        <b class="ps-2 text-danger">${toolName}</b>
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
                        url: './api/Master_equipment_list/delete.php',
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
            allowOutsideClick: () => !Swal.isLoading() // ป้องกันการกดปิดระหว่างโหลด
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

    // saveData
    $('#equipmentHistoryForm').on('submit', function(e) {
        e.preventDefault();
        // Validate Form ก่อนส่งไปยัง backend 
        const form = this;

        const installDate = $('#install_date').val();
        const startUseDate = $('#start_use_date').val();
        const $startUseInput = $('#start_use_date');

        if (installDate && startUseDate && new Date(startUseDate) < new Date(installDate)) {
            $startUseInput.addClass('is-invalid');
        } else {
            // ถ้าแก้แล้วให้เอาออก
            $startUseInput.removeClass('is-invalid');
        }

        if ($startUseInput.hasClass('is-invalid')) {
            Swal.fire({
                icon: 'warning',
                title: 'ข้อมูลไม่ถูกต้อง',
                text: 'วันที่เริ่มใช้งาน "ห้ามย้อนหลัง" ไปก่อนวันที่รับมา/ติดตั้ง',
                confirmButtonColor: '#3085d6',
                returnFocus: false
            }).then(() => {
                $startUseInput.focus();
            });
            return; // หยุดการทำงาน ไม่ส่ง AJAX
        }

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
                    }).then(() => {
                        window.location.reload();
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
        window.location.href = './api/Master_equipment_list/exportExcel.php?' + formData;
    });

    function searchPage(page) {
        const formData = $('#searchFilterForm').serializeArray();
        formData.push({
            name: 'page',
            value: page
        });

        // รอเปลี่ยน API
        $.ajax({
            url: './api/Master_equipment_list/searchData.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status == 'success') {
                    renderTable(response.data, response.offset);
                    $('#count_display').text(response.count);
                    const total = parseInt(response.totalPages);
                    const current = parseInt(response.currentPage); // <--- เปลี่ยนตรงนี้ให้ตรงกับ JSON

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
        let start_no = (isNaN(parseInt(offset))) ? 1 : parseInt(offset) + 1;

        if (data && data.length > 0) {
            data.forEach(function(row, i) {

                // ตรวจสอบค่าว่างสำหรับฟิลด์ต่างๆ ก่อนแสดงผล
                let tool_name = row.tool_name || '-';
                let asset_no = row.asset_no || '-';
                let brand = row.brand || '-';
                let model = row.model || '-';
                let serial_no = row.serial_no || '-';

                let department_name = row.department_name || '<span class="text-muted small">ไม่ระบุ</span>';
                let category_name = row.category_name || '<span class="text-muted small">ไม่ระบุ</span>';
                let responsible = row.responsible_name ? row.responsible_name : '<span class="text-muted small italic">ยังไม่ระบุ</span>';
                // วันที่รับมา และ วันที่เริ่มใช้ (ถ้า API ส่งมาเป็น "null" String หรือค่าว่าง)
                let date_receive = (row.date_receive && row.date_receive !== 'null') ? row.date_receive : '-';
                let date_use = (row.date_use && row.date_use !== 'null') ? row.date_use : '-';


                html += `
                        <tr class="clickable-row cursor-pointer" data-id="${row.id}" title="ดูข้อมูล">
                            <td class="text-center align-middle">
                                <div class="d-flex justify-content-center gap-2">
                                    <i class="fas fa-pen-to-square fa-lg text-warning ${window._canManage ? 'js-edit-btn cursor-pointer' : ''}" 
                                        data-id="${row.id}" 
                                        title="${window._canManage ? 'แก้ไข' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>

                                    <i class="fas fa-trash-alt fa-lg text-danger ${window._canManage ? 'js-delete-btn cursor-pointer' : ''}" 
                                        data-id="${row.id}" 
                                        data-name="${row.tool_name}" 
                                        data-asset="${row.asset_no}" 
                                        title="${window._canManage ? 'ลบ' : 'คุณไม่มีสิทธิ์จัดการข้อมูล'}"></i>
                                </div>
                            </td>
                            <td class="text-center d-none d-xl-table-cell align-middle">${start_no + i}</td>
                            <td class="align-middle">${asset_no}</td>
                            <td class="align-middle d-none d-lg-table-cell">
                                ${department_name}
                            </td>
                            <td class="align-middle d-none d-lg-table-cell">
                                ${category_name}
                            </td>
                            <td class="align-middle">${tool_name}</td>
                            <td class="text-start align-middle d-none d-xxl-table-cell">
                                <div class="fw-bold">${brand}</div>
                                <div class="text-muted small">${model}</div>
                            </td>
                            <td class="d-none d-lg-table-cell align-middle">${serial_no}</td>
                            <td class="d-none d-xl-table-cell align-middle">${responsible}</td>
                            <td class="text-center d-none d-md-table-cell align-middle">${date_receive}</td>
                            <td class="text-center d-none d-md-table-cell align-middle">${date_use}</td>
                        </tr>
                    `;
            });
        } else {
            html = `
                <tr>
                    <td colspan="11" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-tools fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0 fw-bold">ไม่พบรายการเครื่องมือในระบบ</p>
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