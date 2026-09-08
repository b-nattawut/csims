<?php
require_once __DIR__ . '/includes/session_config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require_once './includes/check_permission.php';

$canCreate = hasPermission($pdo, 'incident.create');

$title = "รายการรับแจ้งเหตุ - ระบบบริหารจัดการฐานข้อมูลวัตถุพยาน";

// ดึงข้อมูล dropdown ที่ใช้ร่วมกัน (query ครั้งเดียว)
$qryPoliceStation = "SELECT station_name, province_id FROM master_police_station ORDER BY id DESC";
$stmtPS = $pdo->query($qryPoliceStation);
$policeStations = $stmtPS->fetchAll(PDO::FETCH_ASSOC);

$qryUsers = "SELECT t1.user_id, CONCAT(t2.short_rank,' ',t1.first_name,' ',t1.last_name) AS fullname,
                    t3.nvt_sub, t3.spt_sub, t3.ptjv_sub
             FROM user_profile t1
             LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
             LEFT JOIN users t3 ON t1.user_id = t3.user_id
             ORDER BY t1.user_id DESC";
$stmtUsers = $pdo->query($qryUsers);
$userList = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<?php
$extra_css = ob_get_clean();
ob_start();
?>

<!-- Card ของ Filter Form รวม input Field ที่ใช้สำหรับกรองข้อมูลรายการ incident ที่แสดงในตาราง -->
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
                        <div class="col-md-2 col-sm-2 mt-4">
                            <button class="mt-1 btn btn-success btn-sm px-3 fw-bold shadow-sm text-nowrap px-4 w-100"
                                type="button"
                                <?php if ($canCreate): ?>
                                data-bs-toggle="modal" data-bs-target="#addIncidentModal"
                                <?php else: ?>
                                disabled title="คุณไม่มีสิทธิ์เพิ่มรายการ"
                                <?php endif; ?>>
                                <i class="fas fa-plus fa-xl me-2"></i><span class="d-none d-md-inline">เพิ่ม<span class="d-none d-lg-inline">รายการ</span></span>
                            </button>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">เลขที่เอกสาร</label>
                            <input type="text" class="form-control form-control-sm" id="filter_doc_no" name="filter_doc_no">
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">รับแจ้ง</label>
                            <input type="date" class="form-control form-control-sm" id="filter_date_start" name="filter_date_start">
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">ถึงวันที่</label>
                            <input type="date" class="form-control form-control-sm" id="filter_date_end" name="filter_date_end">
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">จังหวัด</label>
                            <select class="form-select form-select-sm" id="filter_province" name="filter_province">
                                <option value="" selected disabled>กรุณาเลือก</option>
                                <option value="90">สงขลา</option>
                                <option value="95">ยะลา</option>
                                <option value="94">ปัตตานี</option>
                                <option value="96">นราธิวาส</option>
                            </select>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">สภ./สน.</label>
                            <select class="form-select form-select-sm" id="filter_station" name="filter_station">
                                <option value="" selected disabled>กรุณาเลือก</option>
                                <?php foreach ($policeStations as $ps): ?>
                                    <option value="<?= htmlspecialchars($ps['station_name']) ?>" data-province="<?= (int)($ps['province_id'] ?? '') ?>"><?= htmlspecialchars($ps['station_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">เหตุที่รับแจ้ง</label>
                            <select class="form-select form-select-sm" id="filter_incident_type" name="filter_incident_type">
                                <option value="" selected disabled>กรุณาเลือก</option>
                                <option value="01">ทรัพย์</option>
                                <option value="02">ชีวิต</option>
                                <option value="03">ระเบิด</option>
                                <option value="04">เพลิงไหม้</option>
                                <option value="05">จราจร</option>
                                <option value="06">ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)</option>
                                <option value="07">ตรวจเก็บวัตถุพยานที่เกิดเหตุ</option>
                                <option value="08">ตรวจเก็บวัตถุพยานบุคคล</option>
                            </select>
                        </div>

                        <div class="col-md-2 col-sm-3">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">ผู้แจ้ง</label>
                            <input type="text" class="form-control form-control-sm" id="filter_person" name="filter_person">
                        </div>

                        <div class="col-md-2 col-sm-3">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">พนักงานสอบสวน</label>
                            <input type="text" class="form-control form-control-sm" id="filter_inquiry_official" name="filter_inquiry_official">
                        </div>

                        <div class="col-md-12 d-flex justify-content-center align-items-center gap-2 mt-4">
                            <button type="button" class="btn btn-warning btn-sm text-dark fw-bold px-3" id="btn_clear_filter">
                                <i class="fas fa-undo me-1"></i> ล้างค่า
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" id="btn_search_filter" name="btn_search_filter">
                                <i class="fas fa-search me-1"></i> ค้นหา
                            </button>
                            <button type="button" class="btn btn-success btn-sm px-3 fw-bold" id="btn_export" name="btn_export">
                                <i class="fas fa-file-excel me-1"></i> Export
                            </button>
                            <!-- <button type="button" class="btn btn-primary btn-sm px-4 fw-bold" id="btn_checkNet" name="btn_checkNet">
                                <i class="fas fa-search me-1"></i> เช็ค Internet
                            </button> -->
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Card ของ Table ที่แสดงรายการ incident ทั้งหมด -->
<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-end align-items-center mb-3">
            <div class="text-muted small">
                จำนวนข้อมูลทั้งหมด : <span id="count_display">0</span> รายการ
            </div>
        </div>

        <div class="table-responsive">
            <div class="table-wrapper-focus">
                <table class="table table-bordered table-hover table-custom table-striped align-middle mb-0" style="table-layout: auto; width: 100%;">
                    <thead style="font-size: 14px;">
                        <tr class="text-nowrap text-center ">
                            <th style="width: 4%; white-space: nowrap;">ลำดับ</th>
                            <th style="width: 10%; white-space: nowrap;">เลขที่เอกสาร</th>
                            <th style="width: 14%; white-space: nowrap;">สภ./สน.</th>
                            <th style="width: 16%; white-space: nowrap;">พนักงานสอบสวน</th>
                            <th style="width: 12%; white-space: nowrap;">เหตุที่รับแจ้ง</th>
                            <th style="width: 8%; white-space: nowrap;">ช่องทาง</th>
                            <th style="width: 14%; white-space: nowrap;">ผู้แจ้ง</th>
                            <th style="width: 10%; white-space: nowrap;">วันที่รับแจ้ง</th>
                            <th style="width: 8%; white-space: nowrap;">ร่างรายงาน</th>
                        </tr>
                    </thead>
                    <tbody id="table_body" style="font-size: 14px;">
                        <tr>
                            <td colspan="9" class="text-center text-muted">กำลังโหลดข้อมูล...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="row col-md-12 d-flex justify-content-center mt-3">
            <nav aria-label="Page navigation mt-3">
                <ul id="pagination-list" class="pagination justify-content-center"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- Modal สำหรับเพิ่มรายการ incident ใหม่ -->
<div class="modal fade" id="addIncidentModal" aria-labelledby="addIncidentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addIncidentModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายการ
                </h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" id="incidentForm" action="./api/ReceiveNoti/save.php" novalidate>
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">1. ข้อมูลการรับแจ้งเหตุ</legend>

                        <div class="row justify-content-start">
                            <div class="col-md-2 mb-3">
                                <label for="daily_no" class="form-label">ประจำวันข้อที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="daily_no" name="daily_no" maxlength="50" placeholder="เช่น 12">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="daily_no" title="เขียนด้วยลายมือ">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-7 mb-3">
                                <label for="report_location" class="form-label">เขียนที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="report_location" name="report_location" maxlength="100">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="report_location" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <?php
                                $dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d\TH:i');
                                ?>
                                <label for="report_datetime" class="form-label">เมื่อวันที่/เวลา</label>
                                <input type="datetime-local" class="form-control" id="report_datetime" name="report_datetime" value="<?php echo $dateNow ?>">
                            </div>
                        </div>

                        <div class="row justify-content-start">

                            <!-- จังหวัด -->
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-muted">จังหวัด <span class="text-danger">*</span></label>
                                <select class="form-select" id="provineID" name="provineID" required>
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="90">สงขลา</option>
                                    <option value="95">ยะลา</option>
                                    <option value="94">ปัตตานี</option>
                                    <option value="96">นราธิวาส</option>
                                </select>
                                <div class="invalid-feedback">กรุณาเลือกจังหวัด</div>
                            </div>

                            <div class="col-md-5 mb-3">
                                <label class="form-label">รับแจ้งเหตุจาก <span class="text-danger">*</span></label>

                                <div class="row g-2">
                                    <div class="col-sm-12">
                                        <select class="form-select" id="source_station" name="source_station" required>
                                            <option value="" selected disabled>กรุณาเลือก</option>
                                            <?php foreach ($policeStations as $ps): ?>
                                                <option value="<?= htmlspecialchars($ps['station_name']) ?>" data-province="<?= (int)($ps['province_id'] ?? '') ?>"><?= htmlspecialchars($ps['station_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">กรุณาเลือกสถานีตำรวจ</div>
                                    </div>
                                </div>
                            </div>


                            <!-- ช่องทางที่รับแจ้ง -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ช่องทางที่รับแจ้ง</label>
                                <select class="form-select" id="report_channel" name="report_channel">
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="b">ทางหนังสือ</option>
                                    <option value="t">โทรศัพท์</option>
                                    <option value="r">วิทยุสื่อสาร</option>
                                    <option value="o">อื่น ๆ</option>
                                </select>
                            </div>

                            <div class="col-md-12 mb-3 d-none" id="otherChannel">
                                <label class="form-label">อื่นๆ โปรดระบุรายละเอียด</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="report_other_channel" name="report_other_channel" maxlength="100">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="report_other_channel" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุรายละเอียด</div>
                            </div>
                        </div>

                        <div class="row justify-content-start d-none" id="letterChannel">
                            <div class="col-md-9 mb-3">
                                <label for="up_to" class="form-label">ที่ <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="up_to" name="up_to" maxlength="100">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="up_to" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุเลขที่หนังสือ</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <?php
                                $dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
                                ?>
                                <label for="down_to" class="form-label">ลงวันที่ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="down_to" name="down_to" value="<?php echo $dateNow ?>">
                                <div class="invalid-feedback">กรุณาระบุวันที่ลงหนังสือ</div>
                            </div>
                        </div>
                    </fieldset>
                    <!-- เหตุที่รับแจ้ง -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">
                            2. รายละเอียดเหตุการณ์
                        </legend>
                        <div class="row justify-content-start">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    วันเวลาที่เกิดเหตุ
                                </label>

                                <input type="datetime-local" class="form-control" id="event_datetime" name="event_datetime" value="<?php echo $dateNow ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เหตุที่รับแจ้ง <span class="text-danger">*</span></label>

                                <select class="form-select" id="incident_type" name="incident_type" required>
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="01">ทรัพย์</option>
                                    <option value="02">ชีวิต</option>
                                    <option value="03">ระเบิด</option>
                                    <option value="04">เพลิงไหม้</option>
                                    <option value="05">จราจร</option>
                                    <option value="06">ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)</option>
                                    <option value="07">ตรวจเก็บวัตถุพยานที่เกิดเหตุ</option>
                                    <option value="08">ตรวจเก็บวัตถุพยานบุคคล</option>
                                </select>
                                <div class="invalid-feedback">กรุณาระบุเหตุที่รับแจ้ง</div>
                            </div>
                        </div>

                        <div class="row justify-content-start">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">สถานที่เกิดเหตุ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="incident_location" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" id="incident_location" name="incident_location" rows="3" maxlength="500"></textarea>
                            </div>
                        </div>

                        <div class="row justify-content-start">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">พฤติการณ์คดี <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="initial_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" id="initial_detail" name="initial_detail" rows="5" maxlength="2000"></textarea>
                            </div>
                        </div>
                    </fieldset>
                    <!-- บุคคลที่เกี่ยวข้อง -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">
                            3. บุคคลที่เกี่ยวข้อง
                        </legend>

                        <div class="border-bottom pb-2 mb-3">
                            <h6 class="fw-bold text-secondary m-0">
                                พนักงานสอบสวน
                            </h6>
                        </div>
                        <div class="row g-3 mb-5">

                            <div class="col-md-4">
                                <label for="inv_firstname" class="form-label small text-muted">ชื่อ</label>
                                <div class="input-group">
                                    <input type="text" class="form-control person-name" id="inv_firstname" name="inv_firstname">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="inv_firstname" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                                <div class="invalid-feedback">กรุณาระบุชื่อ</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small text-muted">นามสกุล</label>
                                <div class="input-group">
                                    <input type="text" class="form-control person-lastname" id="inv_lastname" name="inv_lastname">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="inv_lastname" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="inv_phone" class="form-label small text-muted">เบอร์โทรศัพท์</label>
                                <input type="tel" class="form-control person-phone" inputmode="numeric" id="inv_phone" name="inv_phone" pattern="[0-9\-]*" maxlength="12">
                            </div>

                        </div>

                        <div class="border-bottom pb-2 mb-3">
                            <h6 class="fw-bold text-secondary m-0">
                                ผู้เสียหาย
                            </h6>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="vic_firstname" class="form-label small text-muted">ชื่อ</label>
                                <div class="input-group">
                                    <input type="text" class="form-control person-name" id="vic_firstname" name="vic_firstname">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="vic_firstname" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="vic_lastname" class="form-label small text-muted">นามสกุล</label>
                                <div class="input-group">
                                    <input type="text" class="form-control person-lastname" id="vic_lastname" name="vic_lastname">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="vic_lastname" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="vic_phone" class="form-label small text-muted">เบอร์โทรศัพท์</label>
                                <input type="tel" class="form-control person-phone" inputmode="numeric" id="vic_phone" name="vic_phone" pattern="[0-9\-]*" maxlength="12">
                            </div>
                        </div>
                    </fieldset>
                    <!-- ผู้ทบทวนข้อมูล -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">
                            4. ผู้ทบทวนข้อมูล
                        </legend>

                        <div class="border-bottom pb-2 mb-3">
                            <h6 class="fw-bold text-secondary m-0">
                            </h6>
                        </div>
                        <div class="row g-3 mb-5">
                            <div class="col-md-2 mt-4">
                                <label class="form-label small text-muted">เรียน</label>
                                <select class="form-select" id="user_ReviewType" name="user_ReviewType">
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="nvt">นวท.(สบ)</option>
                                    <option value="spt">ศพฐ</option>
                                    <option value="ptjv">พฐ.จว</option>
                                </select>
                            </div>
                            <!-- นวท.สบ -->
                            <div class="col-md-2 mt-4 d-none nvt">
                                <label class="form-label small text-muted">ลำดับ</label>
                                <select class="form-select" id="nvt" name="nvt">
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                </select>
                            </div>

                            <!-- ศพฐ -->
                            <div class="col-md-2 mt-4 d-none spt">
                                <label class="form-label small text-muted">ลำดับ</label>
                                <select class="form-select" id="spt" name="spt">
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                    <option value="6">6</option>
                                    <option value="7">7</option>
                                    <option value="8">8</option>
                                    <option value="9">9</option>
                                    <option value="10">10</option>
                                </select>
                            </div>

                            <!-- พฐ.จว -->
                            <div class="col-md-2 mt-4 d-none ptjv">
                                <label class="form-label small text-muted">จังหวัด</label>
                                <select class="form-select" id="ptjv" name="ptjv">
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="90">สงขลา</option>
                                    <option value="95">ยะลา</option>
                                    <option value="94">ปัตตานี</option>
                                    <option value="96">นราธิวาส</option>
                                </select>
                            </div>

                            <!-- ชื่อผู้ทบทวนข้อมูล -->
                            <div class="col-md-4 mt-4">
                                <label class="form-label small text-muted">ผู้ทบทวนข้อมูล <span class="text-danger">*</span></label>
                                <select class="form-select" id="userReviewID" name="userReviewID" required>
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <?php foreach ($userList as $u): ?>
                                        <option value="<?= $u['user_id'] ?>" data-nvt="<?= $u['nvt_sub'] ?? '' ?>" data-spt="<?= $u['spt_sub'] ?? '' ?>" data-ptjv="<?= $u['ptjv_sub'] ?? '' ?>"><?= htmlspecialchars($u['fullname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="form-control" id="userReviewName" name="userReviewName" placeholder="กรอกชื่อผู้ทบทวนข้อมูล" style="display:none">
                                <div class="invalid-feedback">กรุณาเลือกผู้ทบทวนข้อมูล</div>
                            </div>

                            <div class="col-md-4 col-sm-3 mt-4">
                                <label class="form-label small text-muted">ตำแหน่ง</label>
                                <input type="text" class="form-control person-name" id="posUserReview" name="posUserReview">
                            </div>
                        </div>


                    </fieldset>
                    <!-- ผู้อนุมัติ -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">
                            5. ผู้อนุมัติข้อมูล
                        </legend>
                        <div class="border-bottom pb-2 mb-3">
                            <h6 class="fw-bold text-secondary m-0">
                            </h6>
                        </div>
                        <div class="row g-3 mb-5">
                            <div class="col-md-2 mt-4">
                                <label class="form-label small text-muted">เรียน</label>
                                <select class="form-select" id="user_ApproveType" name="user_ApproveType">
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="nvt">นวท.(สบ)</option>
                                    <option value="spt">ศพฐ</option>
                                    <option value="ptjv">พฐ.จว</option>
                                </select>
                            </div>
                            <!-- นวท.สบ -->
                            <div class="col-md-2 mt-4 d-none nvtApprove">
                                <label class="form-label small text-muted">ลำดับ</label>
                                <select class="form-select" id="nvtApprove" name="nvtApprove">
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                </select>
                            </div>

                            <!-- ศพฐ -->
                            <div class="col-md-2 mt-4 d-none sptApprove">
                                <label class="form-label small text-muted">ลำดับ</label>
                                <select class="form-select" id="sptApprove" name="sptApprove">
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                    <option value="6">6</option>
                                    <option value="7">7</option>
                                    <option value="8">8</option>
                                    <option value="9">9</option>
                                    <option value="10">10</option>
                                </select>
                            </div>

                            <!-- พฐ.จว -->
                            <div class="col-md-2 mt-4 d-none ptjvApprove">
                                <label class="form-label small text-muted">จังหวัด</label>
                                <select class="form-select" id="ptjvApprove" name="ptjvApprove">
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="90">สงขลา</option>
                                    <option value="95">ยะลา</option>
                                    <option value="94">ปัตตานี</option>
                                    <option value="96">นราธิวาส</option>
                                </select>
                            </div>

                            <!-- ชื่อผู้อนุมัติข้อมูล -->
                            <div class="col-md-4 mt-4">
                                <label class="form-label small text-muted">ผู้อนุมัติข้อมูล <span class="text-danger">*</span></label>
                                <select class="form-select" id="userApproveID" name="userApproveID" required>
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <?php foreach ($userList as $u): ?>
                                        <option value="<?= $u['user_id'] ?>" data-nvt="<?= $u['nvt_sub'] ?? '' ?>" data-spt="<?= $u['spt_sub'] ?? '' ?>" data-ptjv="<?= $u['ptjv_sub'] ?? '' ?>"><?= htmlspecialchars($u['fullname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="form-control" id="userApproveName" name="userApproveName" placeholder="กรอกชื่อผู้อนุมัติข้อมูล" style="display:none">
                                <div class="invalid-feedback">กรุณาเลือกผู้อนุมัติข้อมูล</div>
                            </div>

                            <div class="col-md-4 col-sm-3 mt-4">
                                <label class="form-label small text-muted">ตำแหน่ง</label>
                                <input type="text" class="form-control" id="posUserApprove" name="posUserApprove">
                            </div>
                        </div>

                    </fieldset>
                    <div class="modal-footer justify-content-end">
                        <button type="submit" class="btn btn-success" id="btn_save_all">
                            <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                        </button>
                        <button type="button" class="btn btn-danger js-close-modal" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> ยกเลิก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
ob_start();
?>
<script>
    window.addEventListener('online', () => {
        let attempts = 0;
        const maxAttempts = 30;

        const checkInterval = setInterval(async () => {
            attempts++;
            const isServerReady = await checkBackendHealth();

            if (isServerReady) {
                clearInterval(checkInterval);
                syncIncidentQueue();
                syncDeleteQueue();

                let currentPage = 1;
                try {
                    var pageData = $('#pagination-list').twbsPagination('getCurrentPage');
                    if (pageData) currentPage = pageData;
                } catch (e) {}

                searchPage(currentPage);
                if (typeof updateSyncUI === 'function') updateSyncUI();
            } else {
                if (attempts >= maxAttempts) {
                    clearInterval(checkInterval);
                }
            }
        }, 2000);
    });

    function setDefaultDate() {
        const now = new Date();

        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');

        const today = `${year}-${month}-${day}`;
        const firstDay = `${year}-${month}-01`;

        $('#filter_date_start').val(firstDay);
        $('#filter_date_end').val(today);
    }

    $(document).ready(function() {

        // เก็บ option ทั้งหมดไว้ก่อน init Select2
        var allStationOptions = $('#source_station option').clone();

        $('#source_station').select2({
            theme: 'bootstrap-5',
            placeholder: "กรุณาเลือก",
            allowClear: true,
            width: '100%',
            dropdownParent: $('#source_station').closest('.col-sm-12'),
            dropdownAutoWidth: true,
            dropdownPosition: 'below'
        });

        // เลือกจังหวัด → กรอง สภ./สน.
        $('#provineID').on('change', function() {
            var provId = $(this).val();
            // Destroy Select2 ก่อน เพื่อเปลี่ยน option
            $('#source_station').select2('destroy');
            $('#source_station').empty();
            allStationOptions.each(function() {
                var prov = $(this).data('province');
                // option "กรุณาเลือก" (ไม่มี data-province) → แสดงเสมอ
                if (!prov || String(prov) === String(provId)) {
                    $('#source_station').append($(this).clone());
                }
            });
            // Re-init Select2
            $('#source_station').select2({
                theme: 'bootstrap-5',
                placeholder: "กรุณาเลือก",
                allowClear: true,
                width: '100%',
                dropdownParent: $('#source_station').closest('.col-sm-12'),
                dropdownAutoWidth: true,
                dropdownPosition: 'below'
            });
        });

        $('#userReviewID').select2({
            theme: 'bootstrap-5',
            placeholder: "กรุณาเลือก",
            allowClear: true,
            width: '100%',
            // ระบุให้ช่อง Dropdown ไปเกิดใน Modal นี้
            dropdownParent: $('#addIncidentModal'),
            dropdownAutoWidth: true
        });

        $('#userApproveID').select2({
            theme: 'bootstrap-5',
            placeholder: "กรุณาเลือก",
            allowClear: true,
            width: '100%',
            // ระบุให้ช่อง Dropdown ไปเกิดใน Modal นี้
            dropdownParent: $('#addIncidentModal'),
            dropdownAutoWidth: true
        });

        // เพิ่มตัวช่วยดึง Focus (เพื่อความชัวร์ 100%)
        $(document).on('select2:open', '#userReviewID', function(e) {
            window.setTimeout(function() {
                document.querySelector('.select2-search__field').focus();
            }, 0);
        });

        $(document).on('select2:open', '#userApproveID', function(e) {
            window.setTimeout(function() {
                document.querySelector('.select2-search__field').focus();
            }, 0);
        });
        setDefaultDate();
        syncDeleteQueue();
        searchPage(1);

        // ★ เลือกจังหวัด (filter) → กรอง สภ./สน.
        $('#filter_province').on('change', function() {
            var provId = $(this).val();
            $('#filter_station option').each(function() {
                var prov = $(this).data('province');
                if (!prov) {
                    $(this).show();
                    return;
                }
                $(this).toggle(String(prov) === String(provId));
            });
            $('#filter_station').val('');
        });
    });

    $('.person-phone').mask('000-000-0000', {
        onKeyPress: function(val, e, field, options) {
            var mask = val.startsWith('02') ? '00-000-0000' : '000-000-0000';
            $('.person-phone').mask(mask, options);
        }
    });

    // $('#report_channel').on('change', function(e) {
    //     const otherInput = $('#report_other_channel');
    //     if ($(this).val() == 'o') {
    //         $('#otherChannel').removeClass('d-none');
    //         otherInput.prop('required', true);
    //     } else {
    //         $('#otherChannel').addClass('d-none');
    //         otherInput.prop('required', false);
    //         otherInput.val('');
    //     }
    // });

    $('#report_channel').on('change', function(e) {
        const selectedValue = $(this).val();
        const otherInput = $('#report_other_channel');
        const upToInput = $('#up_to');
        const downToInput = $('#down_to');

        // --- เคสที่ 1: ทางหนังสือ (b) ---
        if (selectedValue === 'b') {
            $('#letterChannel').removeClass('d-none');
            upToInput.prop('required', true);
            downToInput.prop('required', true);
        } else {
            $('#letterChannel').addClass('d-none');
            upToInput.prop('required', false);
            downToInput.prop('required', false);
            upToInput.val('');
            // คืนค่า down_to กลับไปเป็นวันปัจจุบันเมื่อซ่อนฟิลด์
            downToInput.val('<?php echo (new DateTime("now", new DateTimeZone("Asia/Bangkok")))->format("Y-m-d"); ?>');
        }

        // --- เคสที่ 2: อื่นๆ (o) ---
        if (selectedValue === 'o') {
            $('#otherChannel').removeClass('d-none');
            otherInput.prop('required', true);
        } else {
            $('#otherChannel').addClass('d-none');
            otherInput.prop('required', false);
            otherInput.val('');
        }
    });

    // ฟังก์ชันรวมสำหรับ toggle ประเภทผู้ทบทวน/ผู้อนุมัติ
    function handleTypeToggle(val, groups, selectors) {
        Object.keys(groups).forEach(function(key) {
            if (key === val) {
                $(groups[key]).removeClass('d-none');
            } else {
                $(groups[key]).addClass('d-none');
                $(selectors[key]).val('');
            }
        });
    }

    $('#user_ReviewType').on('change', function() {
        handleTypeToggle($(this).val(), {
            nvt: '.nvt',
            spt: '.spt',
            ptjv: '.ptjv'
        }, {
            nvt: '#nvt',
            spt: '#spt',
            ptjv: '#ptjv'
        });
        // reset user dropdown + text input เมื่อเปลี่ยนประเภท
        $('#userReviewID').show().prop('required', true).val('').trigger('change.select2');
        $('#userReviewID').next('.select2-container').show();
        $('#userReviewName').hide().prop('required', false).val('');
        $('#posUserReview').val('');
        filterUserDropdown('#userReviewID', '', '');
    });

    $('#user_ApproveType').on('change', function() {
        handleTypeToggle($(this).val(), {
            nvt: '.nvtApprove',
            spt: '.sptApprove',
            ptjv: '.ptjvApprove'
        }, {
            nvt: '#nvtApprove',
            spt: '#sptApprove',
            ptjv: '#ptjvApprove'
        });
        // reset user dropdown + text input เมื่อเปลี่ยนประเภท
        $('#userApproveID').show().prop('required', true).val('').trigger('change.select2');
        $('#userApproveID').next('.select2-container').show();
        $('#userApproveName').hide().prop('required', false).val('');
        $('#posUserApprove').val('');
        filterUserDropdown('#userApproveID', '', '');
    });

    // เก็บ option ทั้งหมดไว้สำหรับ restore (รองรับ Select2)
    var allReviewOptions = $('#userReviewID option:not(:first)').clone(true);
    var allApproveOptions = $('#userApproveID option:not(:first)').clone(true);

    // ฟังก์ชัน filter dropdown ชื่อคนตาม agency type + sub value (รองรับ Select2)
    function filterUserDropdown(selectId, agencyType, subVal) {
        var $sel = $(selectId);
        var allOpts = (selectId === '#userReviewID') ? allReviewOptions : allApproveOptions;

        // ลบ option เดิมทั้งหมด (เว้น placeholder)
        $sel.find('option:not(:first)').remove();

        if (!agencyType || !subVal) {
            // ไม่มี filter → คืน option ทั้งหมด
            $sel.append(allOpts.clone(true));
        } else {
            // filter เฉพาะ option ที่ตรงกับ agency type + sub value
            allOpts.each(function() {
                var dataVal = $(this).attr('data-' + agencyType);
                if (dataVal && dataVal == subVal) {
                    $sel.append($(this).clone(true));
                }
            });
        }

        $sel.val('').trigger('change.select2');
    }

    // สลับระหว่าง dropdown กับ text input (ศพฐ 1-9 = พิมพ์เอง, 10 = เลือกจาก dropdown)
    function toggleUserInput(selectId, textInputId, agencyType, subVal) {
        var $sel = $(selectId);
        var $s2 = $sel.next('.select2-container'); // Select2 container
        var $txt = $(textInputId);
        if (agencyType === 'spt' && subVal && parseInt(subVal) >= 1 && parseInt(subVal) <= 9) {
            // ศพฐ 1-9: ซ่อน dropdown + Select2 แสดง text input แทนที่
            $sel.hide().prop('required', false).val('');
            $s2.hide();
            $txt.show().prop('required', true);
        } else {
            // นวท, พฐจว, ศพฐ10: แสดง dropdown + Select2 ซ่อน text input
            $sel.show().prop('required', true);
            $s2.show();
            $txt.hide().prop('required', false).val('');
            if (agencyType && subVal) {
                filterUserDropdown(selectId, agencyType, subVal);
            }
        }
    }

    // ผู้ทบทวน: เมื่อเลือกลำดับ → filter/toggle ชื่อ
    $('#nvt, #spt, #ptjv').on('change', function() {
        var type = $('#user_ReviewType').val();
        var subVal = $(this).val();
        toggleUserInput('#userReviewID', '#userReviewName', type, subVal);
        $('#posUserReview').val('');
    });

    // ผู้อนุมัติ: เมื่อเลือกลำดับ → filter/toggle ชื่อ
    $('#nvtApprove, #sptApprove, #ptjvApprove').on('change', function() {
        var type = $('#user_ApproveType').val();
        var subVal = $(this).val();
        toggleUserInput('#userApproveID', '#userApproveName', type, subVal);
        $('#posUserApprove').val('');
    });

    // ฟังก์ชันรวมสำหรับดึงตำแหน่งผู้ใช้
    function fetchPosition(userId, targetSelector) {
        if (!userId) return;
        $.ajax({
            url: '/csims/api/ReceiveNoti/getPos.php',
            type: 'GET',
            data: {
                idEmp: userId
            },
            dataType: 'json',
            success: function(response) {
                if (response.message === 'success') {
                    $(targetSelector).val(response.data.position_name);
                }
            }
        });
    }

    $('#userReviewID').on('change', function() {
        fetchPosition($(this).val(), '#posUserReview');
    });

    $('#userApproveID').on('change', function() {
        fetchPosition($(this).val(), '#posUserApprove');
    });



    $('.js-close-modal').on('click', function() {
        const $form = $(this).closest('.modal').find('form');
        $form[0].reset();
        $form.removeClass('was-validated');
        $form.find('select').trigger('change');
    });

    // =========================================================
    // DOWNLOAD PDF FUNCTION
    // =========================================================
    function downloadPDF(id, complaintsType) {
        Swal.fire({
            title: 'ดาวน์โหลดร่างรายงาน',
            html: '<p style="color:#666;">คุณต้องการดาวน์โหลดไฟล์ PDF หรือไม่?</p>',
            iconHtml: '<i class="fas fa-file-pdf" style="color:#dc3545;font-size:1.6em;"></i>',
            customClass: {
                icon: 'border-0'
            },
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#dc3545',
            confirmButtonText: '<i class="fas fa-download me-1"></i> ดาวน์โหลด PDF',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                // complaints_type = 06 (ลายนิ้วมือแฝง) ใช้ฟอร์มเฉพาะ
                const pdfUrl = String(complaintsType) === '06' ?
                    '/csims/api/incident/gen_pdf_fingerprint_html.php?incident_id=' + id :
                    '/csims/api/incident/gen_pdf_html.php?incident_id=' + id;
                window.open(pdfUrl, '_blank');
            }
        });
    }

    // Event delegation for PDF download button
    $(document).on('click', '.btn-download-pdf', function(e) {
        e.stopPropagation();
        e.preventDefault();
        e.stopImmediatePropagation();

        const id = $(this).data('pdf-id');
        const complaintsType = $(this).data('pdf-type');
        downloadPDF(id, complaintsType);
        return false;
    });

    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    async function checkBackendHealth() {
        try {
            const res = await fetch('/csims/api/health_check.php', {
                method: 'GET',
                cache: 'no-store'
            });

            const data = await res.json();
            return data.status === 'ok';
        } catch (err) {
            return false;
        }
    }

    function handleOfflineSuccess(form, modalId) {
        saveToLocalQueue(form);

        $(modalId).modal('hide');

        Swal.fire({
            icon: 'info',
            title: 'บันทึกแบบออฟไลน์',
            text: 'ระบบบันทึกข้อมูลไว้แล้ว จะส่งให้อัตโนมัติเมื่อระบบพร้อม',
            showConfirmButton: false,
            timer: 2000
        });

        if (typeof updateSyncUI === 'function') updateSyncUI();
    }

    // saveData
    $('#incidentForm').on('submit', async function(e) {
        e.preventDefault();

        const form = this;
        const modalId = '#addIncidentModal';

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

        if (!navigator.onLine) {
            handleOfflineSuccess(form, modalId);
            return;
        }

        const backendOk = await checkBackendHealth();
        if (!backendOk) {
            handleOfflineSuccess(form, modalId);
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
                console.error("AJAX Error:", xhr.responseText, status, error);
                
                // เช็คว่าเป็น network error จริงๆ หรือเปล่า (ไม่ใช่ server error)
                if (status === 'timeout' || status === 'error' && xhr.status === 0) {
                    // Network error / Timeout → ถือว่า offline
                    handleOfflineSuccess(form, modalId);
                } else {
                    // Server error (400, 500, etc.) → แสดง error message
                    let errorMsg = 'ไม่สามารถบันทึกข้อมูลได้';
                    try {
                        const resp = JSON.parse(xhr.responseText);
                        errorMsg = resp.message || errorMsg;
                    } catch(e) {
                        if (xhr.responseText) errorMsg = xhr.responseText;
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: errorMsg,
                        confirmButtonText: 'ตกลง'
                    });
                }
            },
            complete: function() {
                $('#btn_save_all').prop('disabled', false).html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล');
            }
        });
    });

    function saveToLocalQueue(form) {
        const queue = JSON.parse(localStorage.getItem('incidentQueue')) || [];
        const formData = new FormData(form);
        const data = {};

        formData.forEach((value, key) => {
            if (data[key]) {
                Array.isArray(data[key]) ? data[key].push(value) : data[key] = [data[key], value];
            } else {
                data[key] = value;
            }
        });

        queue.push({
            url: form.action,
            method: 'POST',
            data: data,
            saved_at: new Date().toISOString()
        });

        localStorage.setItem('incidentQueue', JSON.stringify(queue));
    }

    async function syncIncidentQueue() {
        if (!navigator.onLine) return;

        const backendOk = await checkBackendHealth();
        if (!backendOk) return;

        let queue = JSON.parse(localStorage.getItem('incidentQueue')) || [];
        if (queue.length === 0) return;

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
        });

        Toast.fire({
            icon: 'info',
            title: 'กำลังส่งข้อมูลที่ค้างอยู่...'
        });

        let hasSynced = false;

        for (let i = 0; i < queue.length; i++) {
            try {
                const res = await $.ajax({
                    url: queue[i].url,
                    type: queue[i].method,
                    data: queue[i].data,
                    dataType: 'json'
                });

                if (res.status === 'success') {
                    queue.splice(i, 1);
                    i--;
                    hasSynced = true;
                } else {
                    break;
                }
            } catch (err) {
                break;
            }
        }

        localStorage.setItem('incidentQueue', JSON.stringify(queue));

        if (typeof updateSyncUI === 'function') updateSyncUI();

        if (hasSynced) {
            Toast.fire({
                icon: 'success',
                title: 'ส่งข้อมูลครบถ้วนแล้ว'
            }).then(() => {
                setTimeout(() => window.location.reload(), 1000);
            });
        }
    }

    $('#btn_export').on('click', function() {
        window.location.href = '/csims/api/ReceiveNoti/exportExcel.php?' + $('#searchFilterForm').serialize();
    });

    function searchPage(page) {
        const formData = $('#searchFilterForm').serializeArray();
        formData.push({
            name: 'page',
            value: page
        });

        $.ajax({
            url: '/csims/api/ReceiveNoti/searchData.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    renderTable(response.data, response.offset);
                    $('#count_display').text(response.count);
                    var total = parseInt(response.totalPages);
                    var current = parseInt(response.currentPage);
                    if (total > 0) {
                        setupPagination(total, current);
                    } else {
                        $('#pagination-list').twbsPagination('destroy');
                    }
                }
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function renderTable(data, offset) {
        var html = '';
        var index = (parseInt(offset) || 0) + 1;

        if (data && data.length > 0) {
            data.forEach(function(row) {
                var safeId = parseInt(row.id);
                html += '<tr data-id="' + safeId + '" style="cursor:pointer;">' +
                    '<td class="text-center" onclick="window.open(\'incidentDetail.php?id=' + safeId + '\',\'_self\')">' + index + '</td>' +
                    '<td class="text-center" onclick="window.open(\'incidentDetail.php?id=' + safeId + '\',\'_self\')">' + escapeHtml(row.receiveNoti_No_TH || (window.toThaiDocNo ? toThaiDocNo(row.receiveNoti_No) : row.receiveNoti_No) || '') + '</td>' +
                    '<td onclick="window.open(\'incidentDetail.php?id=' + safeId + '\',\'_self\')">' + escapeHtml(row.complaints_From) + '</td>' +
                    '<td onclick="window.open(\'incidentDetail.php?id=' + safeId + '\',\'_self\')">' + escapeHtml(row.inquiry_official_full_name) + '</td>' +
                    '<td onclick="window.open(\'incidentDetail.php?id=' + safeId + '\',\'_self\')">' + escapeHtml(row.complaintstype) + '</td>' +
                    '<td onclick="window.open(\'incidentDetail.php?id=' + safeId + '\',\'_self\')">' + escapeHtml(row.complaintsdevice) + '</td>' +
                    '<td onclick="window.open(\'incidentDetail.php?id=' + safeId + '\',\'_self\')">' + escapeHtml(row.fullname_create) + '</td>' +
                    '<td class="text-center" onclick="window.open(\'incidentDetail.php?id=' + safeId + '\',\'_self\')">' + escapeHtml(row.createdate) + '</td>' +
                    '<td class="text-center"><button type="button" class="btn btn-sm btn-download-pdf" style="background:#7c3aed; box-shadow:0 2px 6px rgba(124,58,237,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-pdf-id="' + safeId + '" data-pdf-type="' + escapeHtml(row.complaints_type) + '" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>แบบรับแจ้งเหตุ</button></td>' +
                    '</tr>';
                index++;
            });
        } else {
            html = '<tr><td colspan="9" class="text-center">ไม่พบข้อมูล</td></tr>';
        }
        $('#table_body').html(html);
        hidePendingDeletes();
    }

    function hidePendingDeletes() {
        const deleteQueue = JSON.parse(localStorage.getItem('incidentDeleteQueue')) || [];
        deleteQueue.forEach(item => {
            $(`tr[data-id="${item.id}"]`).remove();
        });
    }

    async function syncDeleteQueue() {
        if (!navigator.onLine) return;

        // เช็ค Backend ก่อน
        if (!(await checkBackendHealth())) return;

        let queue = JSON.parse(localStorage.getItem('incidentDeleteQueue')) || [];
        if (queue.length === 0) return;

        let hasDeleted = false;

        for (let i = 0; i < queue.length; i++) {
            try {
                await $.ajax({
                    url: '/csims/api/ReceiveNoti/delete.php',
                    type: 'POST',
                    data: {
                        id: queue[i].id
                    }
                });
                queue.splice(i, 1);
                i--;
                hasDeleted = true;
            } catch (e) {
                break;
            }
        }

        localStorage.setItem('incidentDeleteQueue', JSON.stringify(queue));

        if (hasDeleted) {
            var currentPage = 1;
            try {
                currentPage = $('#pagination-list').twbsPagination('getCurrentPage') || 1;
            } catch (e) {}
            searchPage(currentPage);

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'ซิงค์ข้อมูลลบเรียบร้อย',
                showConfirmButton: false,
                timer: 3000
            });
        }
    }

    // ตอนกด Submit ฟอร์มค้นหาครั้งแรก ให้เริ่มที่หน้า 1
    $('#searchFilterForm').on('submit', function(e) {
        e.preventDefault();
        searchPage(1);
    });

    $('#btn_clear_filter').on('click', function() {
        $('#searchFilterForm')[0].reset();
        $('#searchFilterForm select').val('').trigger('change');
        setDefaultDate();
        searchPage(1);
    });

    function setupPagination(totalPages, currentPage) {
        var total = parseInt(totalPages);
        var current = parseInt(currentPage);

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
                    if (page !== current) {
                        searchPage(page);
                    }
                }
            });
        }, 50);
    }
</script>

<!-- ========================================================================
     Handwriting Recognition Modal + Script
     ======================================================================== -->
<style>
    .btn-hw-open {
        transition: all 0.2s;
    }

    .btn-hw-open:hover {
        transform: scale(1.1);
    }

    #hwCanvasArea {
        position: relative;
        border: 2px solid #d1d5db;
        border-radius: 12px;
        background: #fff;
        overflow: hidden;
    }

    #hwCanvas {
        display: block;
        cursor: crosshair;
        touch-action: none;
    }

    .hw-canvas-placeholder {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #d1d5db;
        font-size: 1.1rem;
        pointer-events: none;
        transition: opacity .3s;
    }

    .hw-canvas-placeholder.hidden {
        opacity: 0;
    }

    .hw-result-badge {
        display: inline-block;
        padding: 6px 16px;
        margin: 4px;
        border-radius: 20px;
        background: #f3f4f6;
        border: 1.5px solid #e5e7eb;
        cursor: pointer;
        font-size: 1rem;
        transition: all .2s;
    }

    .hw-result-badge:hover {
        background: #6366f1;
        color: #fff;
        border-color: #6366f1;
        transform: scale(1.05);
    }

    .hw-result-badge.selected {
        background: #6366f1;
        color: #fff;
        border-color: #6366f1;
    }

    .hw-pen-size-group .btn.active {
        background: #6366f1 !important;
        border-color: #6366f1 !important;
        color: #fff !important;
    }
</style>

<div class="modal fade" id="hwModal" tabindex="-1" data-bs-backdrop="static" style="z-index:1070;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">
            <div class="modal-header" style="background:linear-gradient(135deg,#6366f1,#8b5cf6); border:none;">
                <h5 class="modal-title text-white"><i class="fas fa-pen-fancy me-2"></i>เขียนด้วยลายมือ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-muted mb-0">ภาษา:</label>
                        <select class="form-select form-select-sm" id="hwLang" style="width:130px;">
                            <option value="th" selected>ไทย</option>
                            <option value="en">English</option>
                            <option value="th,en">ไทย + English</option>
                        </select>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-muted mb-0">ขนาดปากกา:</label>
                        <div class="btn-group btn-group-sm hw-pen-size-group">
                            <button type="button" class="btn btn-outline-secondary" data-size="2">S</button>
                            <button type="button" class="btn btn-outline-secondary active" data-size="4">M</button>
                            <button type="button" class="btn btn-outline-secondary" data-size="7">L</button>
                        </div>
                    </div>
                </div>
                <div id="hwCanvasArea" class="mb-3">
                    <canvas id="hwCanvas" width="660" height="250"></canvas>
                    <div class="hw-canvas-placeholder" id="hwPlaceholder"><i class="fas fa-pen-alt me-2"></i>เขียนตัวอักษรที่นี่...</div>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-primary btn-sm" id="btnHwRecognize"><i class="fas fa-magic me-1"></i>แปลงเป็นข้อความ</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="btnHwUndo"><i class="fas fa-undo me-1"></i>Undo</button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnHwErase"><i class="fas fa-eraser me-1"></i>ลบทั้งหมด</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnHwSpace"><i class="fas fa-arrows-alt-h me-1"></i>เว้นวรรค</button>
                </div>
                <div class="small text-muted mb-2" id="hwStatus"></div>
                <div id="hwResults" class="mb-3">
                    <p class="text-muted small mb-0"><i class="fas fa-arrow-down me-1"></i>ผลลัพธ์จะแสดงที่นี่หลังกด "แปลงเป็นข้อความ"</p>
                </div>
                <hr>
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted mb-0 text-nowrap">ข้อความสะสม:</label>
                    <input type="text" class="form-control form-control-sm" id="hwAccumulated" readonly style="background:#f9fafb; font-size:1.1rem;">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnHwClearAccum" title="ล้างข้อความ"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-success" id="btnHwConfirm"><i class="fas fa-check me-1"></i>ยืนยัน ใส่ข้อความ</button>
            </div>
        </div>
    </div>
</div>

<script src="./js/handwriting.canvas.js"></script>
<script src="./js/report_no_th.js"></script>
<script>
    (function() {
        var hwCanvasInstance = null;
        var hwTargetIds = [];
        var hwAccumulatedText = '';
        var hwHasDrawn = false;
        var hwStrokeCount = 0;
        var hwModalInitialized = false;

        $(document).off('click.hwOpen').on('click.hwOpen', '.btn-hw-open', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var targets = ($(this).data('hw-targets') || '').split(',').map(function(s) {
                return s.trim();
            }).filter(Boolean);
            hwTargetIds = targets;
            hwAccumulatedText = '';
            hwHasDrawn = false;
            hwStrokeCount = 0;
            $('#hwAccumulated').val('');
            $('#hwResults').html('<p class="text-muted small mb-0"><i class="fas fa-arrow-down me-1"></i>ผลลัพธ์จะแสดงที่นี่หลังกด "แปลงเป็นข้อความ"</p>');
            $('#hwStatus').text('');
            $('#hwPlaceholder').removeClass('hidden');

            var modalEl = document.getElementById('hwModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (!modal) modal = new bootstrap.Modal(modalEl);
            modal.show();
        });

        if (!hwModalInitialized) {
            hwModalInitialized = true;
            var $modal = $('#hwModal');

            $modal.off('hidden.bs.modal.hw').on('hidden.bs.modal.hw', function() {
                if (hwCanvasInstance) {
                    var cvs = hwCanvasInstance.canvas;
                    var newCanvas = cvs.cloneNode(true);
                    cvs.parentNode.replaceChild(newCanvas, cvs);
                    hwCanvasInstance = null;
                }
                hwStrokeCount = 0;
                hwHasDrawn = false;
            });

            $modal.off('shown.bs.modal.hw').on('shown.bs.modal.hw', function() {
                if (hwCanvasInstance) {
                    var oldCvs = hwCanvasInstance.canvas;
                    var newCvs = oldCvs.cloneNode(true);
                    oldCvs.parentNode.replaceChild(newCvs, oldCvs);
                }
                var canvas = document.getElementById('hwCanvas');
                var area = document.getElementById('hwCanvasArea');
                canvas.width = area.clientWidth || 660;
                canvas.height = 250;

                hwCanvasInstance = new handwriting.Canvas(canvas, 4);
                hwCanvasInstance.set_Undo_Redo(true, true);
                hwCanvasInstance.setOptions({
                    language: $('#hwLang').val(),
                    numOfReturn: 5
                });
                hwCanvasInstance.setCallBack(function(results, err) {
                    $('#hwStatus').text('');
                    if (err) {
                        $('#hwResults').html('<span class="text-danger small"><i class="fas fa-exclamation-triangle me-1"></i>' + err.message + '</span>');
                        return;
                    }
                    if (!results || results.length === 0) {
                        $('#hwResults').html('<span class="text-warning small"><i class="fas fa-question-circle me-1"></i>ไม่พบผลลัพธ์ ลองเขียนใหม่</span>');
                        return;
                    }
                    var html = '<span class="small text-muted me-2">เลือกผลลัพธ์:</span>';
                    results.forEach(function(text, idx) {
                        html += '<span class="hw-result-badge' + (idx === 0 ? ' selected' : '') + '" data-text="' + $('<span>').text(text).html().replace(/"/g, '&quot;') + '">' + $('<span>').text(text).html() + '</span>';
                    });
                    $('#hwResults').html(html);
                    hwAppendText(results[0]);
                    hwStrokeCount = 0;
                    if (hwCanvasInstance) {
                        hwCanvasInstance.erase();
                        hwHasDrawn = false;
                        $('#hwPlaceholder').removeClass('hidden');
                    }
                });
            });

            $modal.off('mousedown.hw touchstart.hw').on('mousedown.hw touchstart.hw', '#hwCanvas', function() {
                $('#hwPlaceholder').addClass('hidden');
            });
            $modal.off('mousemove.hw touchmove.hw').on('mousemove.hw touchmove.hw', '#hwCanvas', function() {
                hwStrokeCount++;
                if (hwStrokeCount > 2) hwHasDrawn = true;
            });

            $('#btnHwRecognize').off('click.hw').on('click.hw', function() {
                if (!hwCanvasInstance || !hwHasDrawn || hwStrokeCount < 3) {
                    $('#hwStatus').html('<span class="text-warning"><i class="fas fa-info-circle me-1"></i>กรุณาเขียนก่อนกดแปลง</span>');
                    return;
                }
                hwCanvasInstance.setOptions({
                    language: $('#hwLang').val(),
                    numOfReturn: 5
                });
                $('#hwStatus').html('<i class="fas fa-spinner fa-spin me-1"></i>กำลังแปลง...');
                hwCanvasInstance.recognize();
            });

            $('#btnHwUndo').off('click.hw').on('click.hw', function() {
                if (hwCanvasInstance) {
                    hwCanvasInstance.undo();
                    if (hwCanvasInstance.step.length === 0) {
                        hwHasDrawn = false;
                        hwStrokeCount = 0;
                        $('#hwPlaceholder').removeClass('hidden');
                    }
                }
            });

            $('#btnHwErase').off('click.hw').on('click.hw', function() {
                if (hwCanvasInstance) {
                    hwCanvasInstance.erase();
                    hwHasDrawn = false;
                    hwStrokeCount = 0;
                    $('#hwPlaceholder').removeClass('hidden');
                }
            });

            $('#btnHwSpace').off('click.hw').on('click.hw', function() {
                hwAccumulatedText += ' ';
                $('#hwAccumulated').val(hwAccumulatedText);
            });

            $('#btnHwClearAccum').off('click.hw').on('click.hw', function() {
                hwAccumulatedText = '';
                $('#hwAccumulated').val('');
            });

            $(document).off('click.hwBadge').on('click.hwBadge', '#hwResults .hw-result-badge', function() {
                var prev = $('#hwResults .hw-result-badge.selected');
                if (prev.length) {
                    var prevText = prev.attr('data-text');
                    if (hwAccumulatedText.endsWith(prevText)) {
                        hwAccumulatedText = hwAccumulatedText.slice(0, -prevText.length);
                    }
                }
                $('#hwResults .hw-result-badge').removeClass('selected');
                $(this).addClass('selected');
                hwAppendText($(this).attr('data-text'));
            });

            $(document).off('click.hwPenSize').on('click.hwPenSize', '.hw-pen-size-group .btn', function() {
                $('.hw-pen-size-group .btn').removeClass('active');
                $(this).addClass('active');
                if (hwCanvasInstance) hwCanvasInstance.setLineWidth(parseInt($(this).data('size')));
            });

            $('#btnHwConfirm').off('click.hw').on('click.hw', function() {
                if (hwAccumulatedText.trim() && hwTargetIds.length > 0) {
                    hwTargetIds.forEach(function(id) {
                        var el = document.getElementById(id);
                        if (el) {
                            var existing = (el.value || '').trim();
                            el.value = existing ? existing + hwAccumulatedText : hwAccumulatedText;
                            $(el).trigger('change').trigger('input');
                        }
                    });
                }
                var modalEl = document.getElementById('hwModal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            });
        }

        function hwAppendText(text) {
            hwAccumulatedText += text;
            $('#hwAccumulated').val(hwAccumulatedText);
        }
    })();
</script>

<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>