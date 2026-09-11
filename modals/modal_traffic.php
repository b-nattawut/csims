<?php
// ตั้ง timezone เป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// ดึงข้อมูลผู้ตรวจสำหรับ dropdown
$inspectorOptionsTraffic = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
if (isset($pdo)) {
    $qryInspectorTraffic = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                     FROM user_profile t1 
                     LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                     ORDER BY t1.user_id DESC";
    $stmtTraffic = $pdo->query($qryInspectorTraffic);
    while ($rowTraffic = $stmtTraffic->fetch(PDO::FETCH_ASSOC)) {
        $inspectorOptionsTraffic .= '<option value="' . $rowTraffic['user_id'] . '">' . htmlspecialchars($rowTraffic['fullname']) . '</option>';
    }
}

// วันที่ปัจจุบัน (ไทย)
$todayDateTraffic = date('Y-m-d');
$todayTimeTraffic = date('H:i');
?>

<style>
    /* สไตล์พิเศษสำหรับปุ่มลบ */
    .btn-remove-row {
        width: 32px;
        height: 32px;
        color: #dc3545;
        border-radius: 50%;
        /* ทำให้เป็นวงกลมเวลา hover */
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        border: none;
        background: transparent;
    }

    .btn-remove-row:hover {
        background-color: #fff5f5;
        /* สีแดงอ่อนมาก */
    }

    .btn-remove-row:active {
        background-color: #fee2e2;
        /* สีแดงเข้มขึ้นเมื่อกด */
    }

    /* เปลี่ยน Cursor เป็น Not Allowed สำหรับฟิลด์ที่ปิดอยู่ */
    input:disabled,
    select:disabled,
    textarea:disabled,
    button:disabled,
    .form-check-input:disabled~.form-check-label {
        cursor: not-allowed !important;
    }

    /* 2. ปรับ Input Group Text (เช่น คำว่า สี, ยาว, ยี่ห้อ) ให้ดูซอฟต์ลง */
    .input-group:has(.form-control:disabled) .input-group-text {
        font-weight: 400 !important;
        color: #adb5bd !important;
        background-color: #f9f9f9 !important;
        border-color: #efefef !important;
        font-size: 0.8rem;
    }

    /* ปรับ Placeholder ให้จางลง เฉพาะตอนที่ช่องถูก disabled */
    .form-control:disabled::placeholder {
        color: #dee2e6 !important;
        font-weight: 300;
    }

    /* ปรับตัวช่อง Input เองเมื่อถูก disabled */
    .form-control:disabled {
        background-color: #f9f9f9 !important;
        color: #adb5bd !important;
        border-color: #efefef !important;
        cursor: not-allowed;
    }

    /* ===============================
    ====== preview photos style ======
    ==================================*/
    /* สไตล์สำหรับกล่องรูปภาพแนบ */
    .attachment-card {
        border: 1px solid #e0e6ed;
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
        background: #fff;
        position: relative;
    }

    .attachment-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    /* ส่วนที่ 1: Action Bar (ด้านบนสุด) */
    .card-header-actions {
        background-color: #f8f9fa;
        /* สีพื้นหลังอ่อนๆ ให้ดูแยกส่วน */
        padding: 8px 12px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        border-bottom: 1px solid #eee;
    }

    /* ส่วนที่ 2: Image Box (ตรงกลาง) */
    .img-box-middle {
        width: 100%;
        height: 150px;
        overflow: hidden;
        background-color: #fcfcfc;
    }

    .img-box-middle img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* ปรับแต่งปุ่มกดเล็กน้อย */
    .btn-action-sm {
        width: 28px;
        height: 28px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        font-size: 0.8rem;
    }

    .filename-text {
        font-size: 0.75rem;
        color: #6c757d;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        padding: 10px;
    }

    /* ปรับสไตล์รายการที่ถูก Disabled ใน Select2 */
    .select2-results__option[aria-disabled=true] {
        opacity: 0.5;
        /* จางลง 50% */
        background-color: #e9ecef !important;
        /* สีเทาเข้มขึ้น (Bootstrap Gray-200) */
        cursor: not-allowed;
        /* เปลี่ยนรูปเมาส์เป็นเครื่องหมายห้าม */
    }

    /* พื้นที่สำหรับจองไว้ใส่ปุ่มลบ เพื่อไม่ให้ Layout ขยับ */
    .delete-action-container {
        width: 40px;
        /* กำหนดความกว้างตายตัว */
        min-width: 40px;
        /* ป้องกันการถูกเบียด */
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* กรณีต้องการซ่อนปุ่ม แต่ยังคงรักษาพื้นที่ว่างไว้ (แทนการใช้ d-none) */
    .btn-invisible {
        visibility: hidden;
        /* ซ่อนตัวปุ่มแต่พื้นที่ยังอยู่เท่าเดิม */
        pointer-events: none;
        /* กดไม่ได้ */
    }
</style>

<!-- Modal สำหรับเรื่อง "จราจร" (complaints_type = '05') - ตามฟอร์ม -->
<div class="modal fade" id="addCheckListModalTraffic" aria-labelledby="addCheckListModalTrafficLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addCheckListModalTrafficLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>

            </div>
            <div class="modal-body bg-light p-4 position-relative">
                <!-- Loading Overlay -->
                <div id="trafficStdLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track">
                            <div class="csims-bar-fill"></div>
                        </div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>

                <form method="post" id="incidentCheckListFormTraffic" action="./api/incidentCheckList/saveTraffic.php" novalidate>
                    <input type="hidden" id="receiveNoti_id_traffic" name="receiveNoti_id">
                    <input type="hidden" id="doc_no_traffic" name="doc_no">
                    <input type="hidden" id="report_no_traffic" name="report_no">

                    <!-- Header เลขที่เอกสาร -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="editInfoTraffic" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountTraffic" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto me-3">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <!-- ใช้ script ดึงมาแสดงให้ อิงตาม id ที่กำหนดไว้ -->
                            <span class="fs-5 fw-bold text-primary" id="receiveNoti_No_Traffic"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="receiveNotiReportNo_traffic"></span>
                            </div>
                        </div>
                        <div class="bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2 flex-shrink-0">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToPdfFormTraffic" style="width: 3rem; height: 1.5rem; cursor: pointer;" onchange="if(this.checked){ this.checked=false; switchToTrafficPdfForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToPdfFormTraffic" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

                    <!-- ==================== 1. การรับแจ้งเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">1. การรับแจ้งเหตุ</legend>

                        <div class="row">
                            <!-- คดี (ดึงเลขที่เอกสารมาแสดง) -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">คดี</label>
                                <input type="text" class="form-control bg-light" id="case_doc_no_traffic" name="case_doc_traffic" readonly>
                            </div>
                            <!-- วันที่ -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">วันที่</label>
                                <input type="date" class="form-control" id="case_date_traffic" name="case_date" value="<?= $todayDateTraffic ?>">
                            </div>
                            <!-- เวลา -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" id="case_time_traffic" name="case_time" value="<?= $todayTimeTraffic ?>" step="60">
                            </div>
                        </div>

                        <!-- ช่องทางการรับแจ้ง -->
                        <div class="mb-3 mt-2">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">การรับแจ้ง</h6>

                            <div class="row g-3 mb-3">
                                <div class="col-md-7">
                                    <label class="form-label">
                                        ช่องทางการรับแจ้ง <small class="fw-normal text-muted">(เลือกได้หลายรายการ)</small>
                                    </label>
                                    <div class="bg-body-tertiary p-2 px-3 rounded-3 border" style="min-height: 48px; display: flex; align-items: center;">
                                        <div class="d-flex flex-wrap align-items-center gap-3">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="notify_method[]" value="ทางโทรศัพท์" id="traffic_notify_phone" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="traffic_notify_phone">ทางโทรศัพท์</label>
                                            </div>
                                            <div class="form-check mb-0">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="notify_method[]" value="ทางวิทยุสื่อสาร" id="traffic_notify_radio" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="traffic_notify_radio">ทางวิทยุสื่อสาร</label>
                                            </div>
                                            <div class="form-check mb-0">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="notify_method[]" value="ทางหนังสือ" id="traffic_notify_letter" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="traffic_notify_letter">ทางหนังสือ</label>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input cursor-pointer" type="checkbox" name="notify_method[]" value="อื่นๆ" id="traffic_notify_other" style="transform: scale(1.1);">
                                                    <label class="form-check-label cursor-pointer ms-1 text-dark" for="traffic_notify_other">อื่นๆ</label>
                                                </div>
                                                <input type="text" class="form-control form-control-sm" name="notify_method_other_text" id="traffic_notify_other_text" placeholder="ระบุ..." style="width: 200px; display: none;" disabled>
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-hw-open" data-hw-targets="traffic_notify_other_text" id="traffic_notify_other_hw" title="เขียนด้วยลายมือ" style="display:none;"><i class="fas fa-pen" style="font-size:0.75rem;"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-5 mb-3">
                                    <label class="form-label">สภ./สน.</label>
                                    <select id="police_station_traffic" name="police_station" class="form-select">
                                        <option value="" selected disabled>กรุณาเลือก</option>
                                        <?php
                                        $qryPoliceStation = "SELECT * FROM master_police_station ORDER BY id DESC";
                                        $stmt = $pdo->query($qryPoliceStation);
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        ?>
                                            <option value="<?= htmlspecialchars($row['station_name']) ?>">
                                                <?= htmlspecialchars($row['station_name']) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3 mt-3">
                                <h6 class="fw-bold text-primary border-bottom pb-2">ข้อมูลพนักงานสอบสวน</h6>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ชื่อพนักงานสอบสวน <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="investigator_name" id="traffic_investigator_name" placeholder="...">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="traffic_investigator_name" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">หมายเลขโทรศัพท์ <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control person-phone" name="investigator_phone" placeholder="08xxxxxxxx">
                                </div>
                            </div>
                    </fieldset>

                    <!-- ==================== 2. สถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">2. สถานที่เกิดเหตุ</legend>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">รายละเอียดสถานที่เกิดเหตุ <span class="text-danger">*</span> <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="traffic_crime_location" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" name="crime_location" id="traffic_crime_location" rows="3" placeholder="..."></textarea>
                            </div>
                        </div>

                        <!-- ข้อมูลรถของกลาง -->
                        <div class="mb-3 mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary mb-0 flex-grow-1">ข้อมูลรถของกลาง</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addVehicleCardTraffic()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรถของกลาง
                                </button>
                            </div>

                            <!-- Container สำหรับรายการรถของกลาง -->
                            <div id="vehicle_container_traffic">
                                <!-- รายการที่ 1 (ค่าเริ่มต้น) -->
                                <div class="vehicle-card-traffic bg-light p-3 rounded-3 border mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="badge bg-primary">รถของกลางที่ 1</span>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVehicleCardTraffic(this)">
                                            <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                        </button>
                                    </div>

                                    <div class="bg-white p-3 rounded-3 shadow-sm">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">รถ (ลักษณะรถ)</label>
                                                <input type="text" class="form-control" name="vehicle_detail[]" placeholder="เช่น รถจักรยานยนต์">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">ยี่ห้อ</label>
                                                <input type="text" class="form-control" name="vehicle_brand[]" placeholder="เช่น Toyota, Honda">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">รุ่น</label>
                                                <input type="text" class="form-control" name="vehicle_model[]" placeholder="เช่น Revo, Wave">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">สี</label>
                                                <input type="text" class="form-control" name="vehicle_color[]" placeholder="เช่น ขาว, ดำ-แดง">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">การติดแผ่นป้ายทะเบียน</label>
                                                <div class="d-flex gap-3 mt-1">
                                                    <div class="form-check">
                                                        <input class="form-check-input js-plate-status" type="checkbox" name="vehicle_plate_attach[]" value="ติด" checked>
                                                        <label class="form-check-label">ติด</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input js-plate-status" type="checkbox" name="vehicle_plate_none[]" value="ไม่ติด">
                                                        <label class="form-check-label">ไม่ติด</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label">เลขทะเบียน/จังหวัด</label>
                                                <input type="text" class="form-control js-plate-input" name="vehicle_plate_no[]" placeholder="เช่น กข 1234 กรุงเทพฯ">
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">3. วันเวลาที่ทราบเหตุ/เกิดเหตุ</legend>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ผู้เสียหายทราบเหตุ/เกิดเหตุ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="victim_know_date" value="<?= $todayDateTraffic ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="victim_know_time" value="<?= $todayTimeTraffic ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่พนักงานสอบสวนทราบเหตุ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="officer_know_date" value="<?= $todayDateTraffic ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="officer_know_time" value="<?= $todayTimeTraffic ?>">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 4. วันเวลาที่ตรวจเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">4. วันเวลาที่ตรวจเหตุ</legend>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ทำการตรวจสถานที่เกิดเหตุ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="inspect_date" value="<?= $todayDateTraffic ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="inspect_time" value="<?= $todayTimeTraffic ?>">
                            </div>
                        </div>

                        <!-- <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ตรวจสถานที่เกิดเหตุเพิ่มเติม <small class="text-muted">(ถ้ามี)</small></label>
                                <input type="date" class="form-control" name="inspect_additional_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" name="inspect_additional_time">
                            </div>
                        </div> -->
                    </fieldset>

                    <!-- ==================== 5. ผู้ตรวจสถานที่เกิดเหตุ ==================== -->

                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">5. ผู้ตรวจสถานที่เกิดเหตุ</legend>
                        <div id="inspector_container_traffic">
                            <div class="d-flex align-items-center mb-3 inspector-row-traffic">
                                <div class="text-end pe-3" style="width: 50px;">
                                    <span class="fw-bold text-secondary inspector-index-label-traffic">5.1</span>
                                </div>
                                <div class="flex-grow-1">
                                    <select class="form-select inspector-select-traffic" name="inspector_id[]">
                                        <?= $inspectorOptionsTraffic ?>
                                    </select>
                                </div>
                                <div class="ms-2" style="width: 32px;">
                                    <button type="button" class="btn-remove-row remove-inspector-traffic d-none"
                                        onclick="removeInspectorRowTraffic(this)" title="ลบรายการ">
                                        <i class="fas fa-times fs-5"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex mt-2">
                            <div style="width: 50px;"></div>
                            <div class="flex-grow-1">
                                <button type="button" class="btn btn-outline-primary border-dashed w-100 py-2" onclick="addInspectorRowTraffic()">
                                    <i class="fas fa-plus-circle me-1"></i> เพิ่มผู้ตรวจสถานที่
                                </button>
                            </div>
                            <div class="ms-2" style="width: 32px;"></div>
                        </div>
                    </fieldset>

                    <!-- ==================== 6. จุดประสงค์ในการตรวจ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">6. จุดประสงค์ในการตรวจ</legend>
                        <div class="mb-2">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"> เลือกจุดประสงค์ <small class="fw-normal text-muted">(เลือกได้หลายรายการ)</small></h6>

                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="form-check mb-3 d-flex align-items-center flex-wrap gap-2">
                                    <input class="form-check-input cursor-pointer js-purpose-check" type="checkbox" name="inspect_purpose[]" value="1" id="purpose_1" style="transform: scale(1.1);">
                                    <label class="form-check-label cursor-pointer text-dark" for="purpose_1">
                                        1. เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนระหว่างรถของกลาง
                                    </label>
                                    <input type="text" class="form-control form-control-sm js-purpose-input" name="purpose_qty_1" style="width: 80px;"
                                        inputmode="numeric" pattern="[0-9]*" placeholder="จำนวน" disabled>
                                    <span class="text-dark">คัน หรือไม่ อย่างไร</span>
                                </div>

                                <div class="form-check mb-3 d-flex align-items-center flex-wrap gap-2">
                                    <input class="form-check-input cursor-pointer js-purpose-check" type="checkbox" name="inspect_purpose[]" value="2" id="purpose_2" style="transform: scale(1.1);">
                                    <label class="form-check-label cursor-pointer text-dark" for="purpose_2">
                                        2. เพื่อทราบว่ารถของกลาง
                                    </label>
                                    <input type="text" class="form-control form-control-sm js-purpose-input" name="purpose_qty_2" style="width: 80px;"
                                        inputmode="numeric" pattern="[0-9]*" placeholder="จำนวน" disabled>
                                    <span class="text-dark">คัน มีการเฉี่ยวชนกันหรือไม่ อย่างไร</span>
                                </div>

                                <div class="form-check mb-3">
                                    <input class="form-check-input cursor-pointer" type="checkbox" name="inspect_purpose[]" value="3" id="purpose_3" style="transform: scale(1.1);">
                                    <label class="form-check-label cursor-pointer text-dark" for="purpose_3">
                                        3. เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนที่รถของกลางทั้งหมดนี้หรือไม่ และมีลักษณะการเฉี่ยวชนอย่างไร
                                    </label>
                                </div>

                                <div class="form-check d-flex align-items-center flex-wrap gap-2">
                                    <input class="form-check-input cursor-pointer js-purpose-check" type="checkbox" name="inspect_purpose[]" value="4" id="purpose_4" style="transform: scale(1.1);">
                                    <label class="form-check-label cursor-pointer text-dark" for="purpose_4">
                                        4. เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนระหว่างรถของกลางหรือไม่อย่างไร หรือ
                                    </label>
                                    <input type="text" class="form-control form-control-sm js-purpose-input" name="purpose_other_text" style="flex: 1; min-width: 200px;"
                                        placeholder="ระบุจุดประสงค์เพิ่มเติม..." disabled>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 7. ผู้ตรวจพิสูจน์ ==================== -->

                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">7. ผู้ตรวจพิสูจน์</legend>

                        <div id="forensic_container_traffic">
                            <div class="forensic-row-traffic d-flex align-items-center mb-3">
                                <div class="text-end pe-3" style="width: 50px;">
                                    <span class="fw-bold text-secondary forensic-index-label-traffic">7.1</span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <select class="form-select user-select-box-traffic forensic-select-traffic"
                                                name="forensic_id[]"
                                                data-pos-target="#forensic_pos_0">
                                                <?= $inspectorOptionsTraffic ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" class="form-control bg-light" id="forensic_pos_0"
                                                name="forensic_position[]" readonly placeholder="ตำแหน่ง (แสดงอัตโนมัติ)">
                                        </div>
                                    </div>
                                </div>
                                <div class="ms-2" style="width: 32px;">
                                    <button type="button" class="btn-remove-row remove-forensic-traffic d-none"
                                        onclick="removeForensicRowTraffic(this)" title="ลบรายการ">
                                        <i class="fas fa-times fs-5 text-danger"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex mt-2">
                            <div style="width: 50px;"></div>
                            <div class="flex-grow-1">
                                <button type="button" class="btn btn-outline-primary border-dashed w-100 py-2" onclick="addForensicRowTraffic()">
                                    <i class="fas fa-plus-circle me-1"></i> เพิ่มผู้ตรวจพิสูจน์
                                </button>
                            </div>
                            <div class="ms-2" style="width: 32px;"></div>
                        </div>
                    </fieldset>

                    <!-- ==================== 8. ผลการตรวจพิสูจน์ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">8. ผลการตรวจพิสูจน์</legend>
                        <div class="row">
                            <!-- พฤติการณ์คดี -->
                            <div class="col-md-12 mb-3">
                                <label class="form-label">พฤติการณ์คดี <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="traffic_case_behavior" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" name="case_behavior" id="traffic_case_behavior" rows="4"
                                    placeholder="ระบุพฤติการณ์โดยสังเขป..."></textarea>
                            </div>
                            <!-- สิ่งที่ทำการตรวจพิสูจน์ -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">สิ่งที่ทำการตรวจพิสูจน์</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="forensic_inspection_target" id="traffic_forensic_inspection_target"
                                        placeholder="เช่น รถของกลางรายการที่ 1 และ 2">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="traffic_forensic_inspection_target" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <!-- สถานที่ตรวจ -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">สถานที่ตรวจ</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="forensic_inspection_location" id="traffic_forensic_inspection_location"
                                        placeholder="ระบุสถานที่ที่ทำการตรวจพิสูจน์...">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="traffic_forensic_inspection_location" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">การตรวจพิสูจน์</label>
                                <select class="form-select lab-unit-multi" multiple size="4" title="เลือกได้มากกว่า 1 กลุ่มงาน">
                                    <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                                    <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                    <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                    <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                    <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                    <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                </select>
                                <input type="hidden" class="lab-unit-value" name="forensic_lab_unit" value="">
                            </div>
                            <!-- วัน / เวลาที่ทำการตรวจพิสูจน์ -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ทำการตรวจพิสูจน์</label>
                                <input type="date" class="form-control" name="forensic_inspect_date" value="<?= $todayDateTraffic ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" name="forensic_inspect_time" value="<?= $todayTimeTraffic ?>">
                            </div>
                        </div>

                        <!-- รายการรถของกลางเพื่อตรวจพิสูจน์ -->
                        <div class="mb-3 mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary mb-0 flex-grow-1">ข้อมูลรถของกลางเพื่อตรวจพิสูจน์</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addForensicVehicleCard()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรถของกลางเพื่อตรวจพิสูจน์
                                </button>
                            </div>

                            <div id="forensic_vehicle_container">
                                <div class="forensic-vehicle-card bg-light p-3 rounded-3 border mb-4 shadow-sm">
                                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-car me-2"></i><span class="js-v-index-label">รถของกลางที่ 1</span></h6>
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0 js-btn-remove-v" onclick="removeForensicVehicleCard(this)">
                                            <i class="fas fa-trash-alt me-1"></i> ลบรถคันนี้
                                        </button>
                                    </div>

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">สภาพรถ</label>
                                            <input type="text" class="form-control" name="forensic_v_condition[]" placeholder="ระบุสภาพทั่วไปของรถ เช่น สภาพปกติ, มีรอยเฉี่ยวชนรอบคัน">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">การต่อเติมหรือดัดแปลงสภาพ</label>
                                            <div class="px-3 py-2 border rounded bg-white">
                                                <div class="d-flex gap-4 align-items-center">
                                                    <div class="form-check flex-shrink-0 text-nowrap mb-0">
                                                        <input class="form-check-input js-mod-check cursor-pointer" type="checkbox" name="forensic_v_mod_status_0" value="ไม่มี" checked>
                                                        <label class="form-check-label cursor-pointer">ไม่มี</label>
                                                    </div>

                                                    <div class="form-check flex-shrink-0 text-nowrap mb-0">
                                                        <input class="form-check-input js-mod-check cursor-pointer" type="checkbox" name="forensic_v_mod_status_0" value="มี">
                                                        <label class="form-check-label cursor-pointer">มี (ระบุ)</label>
                                                    </div>

                                                    <input type="text" class="form-control flex-grow-1 js-mod-detail" name="forensic_v_mod_detail[]" placeholder="รายละเอียดการดัดแปลง..." disabled>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <label class="form-label fw-bold text-secondary mb-2">รายการร่องรอยความเสียหายแยกตามตำแหน่ง</label>
                                    <div class="forensic-trace-wrapper">
                                        <?php
                                        $v_sides = [
                                            'front' => 'ด้านหน้า',
                                            'left'  => 'ด้านซ้าย',
                                            'right' => 'ด้านขวา',
                                            'back'  => 'ด้านหลัง'
                                        ];
                                        $vIdx = 1;
                                        ?>

                                        <?php foreach ($v_sides as $side => $label): ?>
                                            <div class="bg-white p-3 rounded border mb-3 shadow-sm side-trace-box">
                                                <h6 class="small fw-bold text-primary border-bottom pb-2 mb-3">
                                                    <i class="fas fa-search me-1"></i> ร่องรอย<?= $label ?>
                                                </h6>

                                                <table class="table table-sm table-borderless align-middle mb-2">
                                                    <thead>
                                                        <tr class="small text-muted">
                                                            <th>ร่องรอย/บริเวณ/ตำแหน่ง</th>
                                                            <th style="width: 130px;">สูงจากพื้น (cm.)</th>
                                                            <th style="width: 30px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="js-trace-body">
                                                        <tr>
                                                            <td><input type="text" class="form-control form-control-sm" name="trace_<?= $side ?>_detail_<?= $vIdx ?>[]" placeholder="ระบุตำแหน่ง/ลักษณะรอย"></td>
                                                            <td><input type="number" class="form-control form-control-sm text-center" name="trace_<?= $side ?>_height_<?= $vIdx ?>[]" placeholder="0.00"></td>
                                                            <td style="width: 40px;"></td>
                                                        </tr>
                                                    </tbody>
                                                </table>

                                                <button type="button" class="btn btn-outline-primary btn-sm w-100 border-dashed js-add-trace-btn"
                                                    onclick="addTraceRow(this, 'trace_<?= $side ?>', <?= $vIdx ?>)">
                                                    <i class="fas fa-plus-circle me-1"></i> เพิ่มรายการร่องรอย<?= $label ?>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 9. ผลการตรวจเปรียบเทียบ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">9. ผลการตรวจเปรียบเทียบ</legend>

                        <div class="row align-items-center mb-4">
                            <div class="col-auto">
                                <span>จากการตรวจเปรียบเทียบสภาพร่องรอยความเสียหายและการแลกเปลี่ยนวัตถุพยานของรถของกลางทั้ง</span>
                            </div>
                            <div class="col-sm-1">
                                <input type="number" class="form-control form-control-sm text-center" name="forensic_compare_v_count" placeholder="...">
                            </div>
                            <div class="col-auto">
                                <span>รายการ พบว่า</span>
                            </div>
                        </div>

                        <div id="comparison_container">
                            <div class="comparison-row p-3 border rounded-3 mb-3 bg-light animate__animated animate__fadeIn">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="fw-bold text-primary js-compare-index">9.1</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0 js-btn-remove-compare" onclick="removeCompareRow(this)" style="display:none;">
                                        <i class="fas fa-times"></i> ลบ
                                    </button>
                                </div>

                                <div class="row g-2 align-items-center">
                                    <div class="col-md-4">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">รอย</span>
                                            <input type="text" class="form-control" name="compare_trace_type[]" placeholder="เช่น รอยบุบ">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">บริเวณ</span>
                                            <input type="text" class="form-control" name="compare_area_a[]" placeholder="เช่น กันชนหน้าขวา">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">ตามผลการตรวจในข้อ</span>
                                            <input type="text" class="form-control" name="compare_ref_a[]" placeholder="เช่น 8.1 ด้านหน้า">
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">มีลักษณะร่องรอยและระดับความสูงเข้ากันได้กับรอยครูดบริเวณ</span>
                                            <input type="text" class="form-control" name="compare_area_b[]" placeholder="เช่น ประตูหน้าซ้าย">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">ตามผลการตรวจในข้อ</span>
                                            <input type="text" class="form-control" name="compare_ref_b[]" placeholder="เช่น 8.2 ด้านซ้าย">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-outline-primary w-100 border-dashed py-2 mt-2" onclick="addCompareRow()">
                            <i class="fas fa-plus-circle me-1"></i> เพิ่มรายการเปรียบเทียบ
                        </button>
                    </fieldset>

                    <!-- ==================== 10. ความเห็น ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">10. ความเห็น</legend>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold text-secondary">
                                    บทสรุปและความเห็นจากผลการตรวจพิสูจน์
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="traffic_forensic_opinion" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">จากผลการตรวจในข้อ 8 และ 9</span>
                                    <textarea class="form-control" name="forensic_opinion" id="traffic_forensic_opinion" rows="5"
                                        placeholder="ระบุความเห็นของเจ้าหน้าที่โดยละเอียด..."></textarea>
                                    <span class="input-group-text bg-light">จนได้รับความเสียหายดังที่ปรากฏ</span>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 11. การส่งมอบคืนสถานที่ ==================== -->

                    <fieldset class="p-4 bg-white rounded-4 shadow-sm border mb-4">
                        <legend class="fieldset-header">11. การส่งมอบคืนสถานที่</legend>

                        <!-- วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น -->
                        <div class="mb-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">วันที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น</label>
                                    <input type="date" class="form-control" name="inspection_end_date" value="<?php echo $todayDateTraffic; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">เวลาประมาณ</label>
                                    <input type="time" class="form-control" name="inspection_end_time" value="<?php echo $todayTimeTraffic; ?>">
                                </div>
                            </div>
                        </div>

                        <!-- การส่งมอบสถานที่เกิดเหตุ -->
                        <h6 class="fw-bold text-primary border-bottom pb-2 mb-4">การส่งมอบสถานที่เกิดเหตุ</h6>

                        <div class="row g-4">
                            <!-- ผู้รับมอบสถานที่เกิดเหตุ -->
                            <div class="col-lg-6">
                                <div class="p-3 bg-light rounded border h-100">
                                    <h6 class="fw-bold text-secondary border-bottom pb-2 mb-3">
                                        <i class="fas fa-user-check me-2"></i>ผู้รับมอบสถานที่เกิดเหตุ
                                    </h6>

                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ลงชื่อ (ผู้รับมอบ) <span class="text-danger">*</span></label>
                                            <select class="form-select user-select-box-traffic" id="receiver_name_traffic" name="receiver_name" data-pos-target="#receiver_position_traffic">
                                                <?php echo str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้รับมอบ --', $inspectorOptionsTraffic); ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ตำแหน่ง</label>
                                            <input type="text" class="form-control bg-light" id="receiver_position_traffic" name="receiver_position" readonly>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small text-muted">ลายเซ็นผู้รับมอบ</label>
                                        <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden w-100" style="height: 180px;">
                                            <canvas id="sig-canvas-receiver-traffic" class="signature-pad w-100 h-100"></canvas>
                                            <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none">
                                                <i class="fas fa-signature fa-2x mb-1 d-block text-center"></i> เซ็นชื่อที่นี่
                                            </div>
                                        </div>
                                        <div class="text-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('sig-canvas-receiver-traffic')">
                                                <i class="fas fa-eraser me-1"></i> ล้าง
                                            </button>
                                        </div>
                                        <input type="hidden" name="receiver_signature_data_traffic" id="receiver_signature_data_traffic">
                                    </div>
                                </div>
                            </div>

                            <!-- ผู้ส่งมอบสถานที่เกิดเหตุ -->
                            <div class="col-lg-6">
                                <div class="p-3 bg-light rounded border h-100">
                                    <h6 class="fw-bold text-secondary border-bottom pb-2 mb-3">
                                        <i class="fas fa-user-edit me-2"></i>ผู้ส่งมอบสถานที่เกิดเหตุ
                                    </h6>

                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ลงชื่อ (ผู้ส่งมอบ) <span class="text-danger">*</span></label>
                                            <select class="form-select user-select-box-traffic" id="sender_name_traffic" name="sender_name" data-pos-target="#sender_position_traffic">
                                                <?php echo str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งมอบ --', $inspectorOptionsTraffic); ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ตำแหน่ง</label>
                                            <input type="text" class="form-control bg-light" id="sender_position_traffic" name="sender_position" readonly>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small text-muted">ลายเซ็นผู้ส่งมอบ</label>
                                        <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden w-100" style="height: 180px;">
                                            <canvas id="sig-canvas-sender-traffic" class="signature-pad w-100 h-100"></canvas>
                                            <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none">
                                                <i class="fas fa-signature fa-2x mb-1 d-block text-center"></i> เซ็นชื่อที่นี่
                                            </div>
                                        </div>
                                        <div class="text-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('sig-canvas-sender-traffic')">
                                                <i class="fas fa-eraser me-1"></i> ล้าง
                                            </button>
                                        </div>
                                        <input type="hidden" name="sender_signature_data_traffic" id="sender_signature_data_traffic">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 12. บันทึกการถ่ายภาพ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">12. บันทึกการถ่ายภาพ</legend>

                        <div class="row mb-4">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">รหัสภาพถ่ายที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="photo_id_start_traffic" id="photo_id_start_traffic">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="photo_id_start_traffic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">ถึง</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="photo_id_end_traffic" id="photo_id_end_traffic">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="photo_id_end_traffic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">จำนวน (ภาพ)</label>
                                <input type="text"
                                    class="form-control"
                                    name="photo_amount_traffic"
                                    id="photo_amount_traffic"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                            </div>
                            <div class="col-md-6">
                                <label for="photographer_name_traffic" class="form-label">ผู้จดบันทึก</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="photographer_name_traffic" name="photographer_name_traffic">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="photographer_name_traffic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="photographer_datetime_traffic" class="form-label">วัน/เวลา</label>
                                <input type="datetime-local" class="form-control" id="photographer_datetime_traffic" name="photographer_datetime_traffic">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="button" class="btn btn-primary" id="btn_choose_file_traffic">
                                        <i class="fas fa-folder-open me-2"></i>เลือกไฟล์รูปภาพ
                                    </button>
                                    <button type="button" class="btn btn-success" id="btn_open_camera_traffic">
                                        <i class="fas fa-camera me-2"></i>เปิดกล้องถ่ายภาพ
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- ★ Drag & Drop zone -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <div id="traffic_photo_dropzone"
                                    style="border:2px dashed #adb5bd; border-radius:12px; padding:24px; text-align:center; color:#6c757d; cursor:pointer; transition:all .15s ease; background:#f8f9fa;">
                                    <i class="fas fa-cloud-upload-alt fa-2x mb-2 d-block opacity-75"></i>
                                    <div style="font-size:.9rem;">ลากรูปภาพมาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</div>
                                    <div style="font-size:.75rem;" class="opacity-75">รองรับไฟล์รูปภาพหลายไฟล์</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">

                                <!-- Input สำหรับเลือกไฟล์จาก Gallery -->
                                <input type="file"
                                    id="incident_photos_traffic"
                                    name="incident_photos_traffic[]"
                                    accept="image/*"
                                    style="display: none;"
                                    multiple>

                                <!-- Input สำหรับเปิดกล้องถ่ายภาพ -->
                                <input type="file"
                                    id="camera_input_traffic"
                                    name="camera_photos_traffic[]"
                                    accept="image/*"
                                    capture="environment"
                                    style="display: none;"
                                    multiple>

                                <div id="uploading_container_traffic" class="mb-4">
                                </div>

                                <div id="attachments_wrapper_traffic" class="d-none mt-4">
                                    <h6 class="fw-bold text-secondary mb-3">
                                        Attachments <span class="badge bg-secondary rounded-pill ms-1" id="file_count_badge_traffic">0</span>
                                    </h6>

                                    <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-3" id="attachments_grid_traffic">
                                    </div>
                                </div>

                            </div>
                        </div>
                    </fieldset>

                    <!-- javascript  -->
                    <script>
                        const inspectorOptionsHTMLTraffic = `<?php echo addslashes($inspectorOptionsTraffic); ?>`;
                        let forensicOptionsHTML = '';
                        // ★ ระบบรูปรวม: store เดียวร่วมฟอร์มปกติ+เสมือน (modal_traffic_pdf_form.php ใช้ตัวเดียวกัน)
                        if (typeof window.attachmentStoreTraffic === 'undefined') window.attachmentStoreTraffic = [];
                        var attachmentStoreTraffic = window.attachmentStoreTraffic;
                        window.deletedExistingPhotosTraffic = window.deletedExistingPhotosTraffic || [];

                        function formatFileSize(bytes) {
                            if (bytes === 0) return '0 Bytes';
                            const k = 1024;
                            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                            const i = Math.floor(Math.log(bytes) / Math.log(k));
                            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                        }

                        function initSelect2Traffic($element) {
                            const $modal = $('#addCheckListModalTraffic');
                            $element.select2({
                                theme: 'bootstrap-5',
                                width: '100%',
                                placeholder: "กรุณาเลือก",
                                allowClear: true,
                                dropdownParent: $modal,
                                dropdownAutoWidth: true
                            });
                        }

                        function initTrafficCanvases() {
                            const canvasList = ['sig-canvas-receiver-traffic', 'sig-canvas-sender-traffic'];
                            canvasList.forEach(id => {
                                const canvas = document.getElementById(id);
                                if (canvas) {
                                    const ctx = canvas.getContext('2d');
                                    canvas.width = canvas.offsetWidth || canvas.parentElement.offsetWidth;
                                    canvas.height = canvas.offsetHeight || canvas.parentElement.offsetHeight;
                                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                                }
                            });
                        }

                        $(document).ready(function() {
                            const $modal = $('#addCheckListModalTraffic');
                            forensicOptionsHTML = $('.forensic-select-traffic').first().html();

                            // เมื่อเปิด Modal: แปลง Select ตัวแรกเป็น Select2 ทันที
                            $modal.on('shown.bs.modal', function() {
                                $('#police_station_traffic, .inspector-select-traffic, .forensic-select-traffic, .user-select-box-traffic').each(function() {
                                    if (!$(this).hasClass("select2-hidden-accessible")) {
                                        initSelect2Traffic($(this));
                                    }
                                });

                                // --- Initialize phone mask ---
                                if ($.isFunction($.fn.mask)) {
                                    $('input[name="investigator_phone"]').mask('000-000-0000', {
                                        onKeyPress: function(val, e, field, options) {
                                            var mask = val.startsWith('02') ? '00-000-0000' : '000-000-0000';
                                            $('input[name="investigator_phone"]').mask(mask, options);
                                        }
                                    });
                                }

                                // --- Initialize Signature Pads ---
                                const canvasesTraffic = document.querySelectorAll('#addCheckListModalTraffic .signature-pad');
                                canvasesTraffic.forEach((canvas) => {
                                    const canvasId = canvas.id;

                                    function resizeCanvas() {
                                        const ratio = Math.max(window.devicePixelRatio || 1, 1);
                                        if (canvas.offsetWidth === 0) return;
                                        canvas.width = canvas.offsetWidth * ratio;
                                        canvas.height = canvas.offsetHeight * ratio;
                                        canvas.getContext('2d').scale(ratio, ratio);
                                    }

                                    // ถ้าเคยประกาศ global signaturePads ไว้ในหน้าหลักแล้ว
                                    if (typeof signaturePads !== 'undefined' && signaturePads[canvasId]) {
                                        resizeCanvas();
                                        return;
                                    }

                                    resizeCanvas();

                                    if (typeof SignaturePad !== 'undefined') {
                                        const pad = new SignaturePad(canvas, {
                                            backgroundColor: 'rgba(255, 255, 255, 0)',
                                            minWidth: 1,
                                            maxWidth: 3,
                                        });

                                        if (typeof signaturePads !== 'undefined') {
                                            signaturePads[canvasId] = pad;
                                        }
                                    }

                                    window.addEventListener('resize', resizeCanvas);
                                });


                                // รีเซ็ตตัวเลขลำดับ
                                reIndexVehicleCardsTraffic();
                                reIndexInspectorsTraffic();
                                reIndexForensicRowsTraffic();
                                reIndexForensicVehicles();
                            });

                            // 6. ฟังก์ชัน Reset เมื่อปิด Modal ให้เคลียร์ค่า และจำนวน select ให้กลับไปค่าเริ่มต้นที่ 1 รายการ
                            $modal.on('hidden.bs.modal', function() {

                                // Reset ส่วน "อื่นๆ" ของการรับแจ้ง
                                $('#traffic_notify_other').prop('checked', false);
                                $('#traffic_notify_other_text').hide().prop('disabled', true).val('');

                                // === ส่วนของ รถของกลาง (Vehicle) ===
                                const $vehicleContainer = $('#vehicle_container_traffic');
                                // 1. ลบ Card ที่เกินมา (เหลือไว้แค่ใบแรก)
                                $vehicleContainer.find('.vehicle-card-traffic:not(:first)').remove();

                                // 2. ล้างค่าใน Card ใบแรก
                                const $firstVehicle = $vehicleContainer.find('.vehicle-card-traffic:first');
                                $firstVehicle.find('input').val('');
                                $firstVehicle.find('textarea').val('');

                                // 3. Reset สถานะแผ่นป้ายทะเบียน (ให้กลับมา "ติด" และ "Enabled")
                                $firstVehicle.find('input[value="ติด"]').prop('checked', true);
                                $firstVehicle.find('input[value="ไม่ติด"]').prop('checked', false);
                                $firstVehicle.find('.js-plate-input').prop('disabled', false).val('');

                                // === ส่วนของ ผู้ตรวจสถานที่ (Inspector) ===
                                $('.inspector-row-traffic:not(:first)').remove();
                                const $firstSelect = $('.inspector-select-traffic:first');
                                $firstSelect.val(null).trigger('change'); // ล้างค่า Select2

                                // === ส่วนของผู้ตรวจพิสูจน์ (Forensic): ลบแถวเกิน ล้าง Select2 และล้างตำแหน่ง ===
                                $('.forensic-row-traffic:not(:first)').remove();
                                const $firstForensic = $('.forensic-select-traffic:first');
                                $firstForensic.val(null).trigger('change');
                                // ล้างตำแหน่ง Readonly ของ 7.1
                                $($firstForensic.data('pos-target')).val('');

                                // === ส่วนของรถของกลางเพื่อตรวจพิสูจน์ (Forensic Vehicle): ลบแถวเกิน ล้างข้อมูล ===
                                const $container = $('#forensic_vehicle_container');
                                // ลบรายการที่เกินออกให้เหลือแค่ใบแรก
                                $container.find('.forensic-vehicle-card:not(:first)').remove();

                                // ล้างค่าในใบแรก
                                const $firstCard = $container.find('.forensic-vehicle-card:first');
                                $firstCard.find('input').val('');
                                $firstCard.find('input[value="ไม่มี"]').prop('checked', true);
                                $firstCard.find('input[value="มี"]').prop('checked', false);
                                $firstCard.find('.js-mod-detail').prop('disabled', true);

                                // ล้างแถวร่องรอยส่วนเกินในใบแรก (เหลือด้านละ 1 แถวว่างๆ)
                                $firstCard.find('.js-trace-body').each(function() {
                                    $(this).find('tr:not(:first)').remove();
                                });

                                // === ล้างช่องลายเซ็น ===
                                if (typeof signaturePads !== 'undefined') {
                                    Object.values(signaturePads).forEach(pad => pad.clear());
                                }

                                // 4. รีเซ็ตตัวเลขลำดับ
                                reIndexVehicleCardsTraffic();
                                reIndexInspectorsTraffic();
                                reIndexForensicRowsTraffic();
                                reIndexForensicVehicles();

                            });

                            // Logic คัดลอกเลขที่เอกสาร
                            const receiveNotiNoTraffic = document.getElementById('receiveNoti_No_Traffic');
                            const caseDocNoTraffic = document.getElementById('case_doc_no_traffic');

                            // ใช้ MutationObserver เพื่อตรวจจับการเปลี่ยนแปลงของ receiveNoti_No_Traffic
                            if (receiveNotiNoTraffic && caseDocNoTraffic) {
                                const observer = new MutationObserver(function(mutations) {
                                    mutations.forEach(function(mutation) {
                                        if (mutation.type === 'childList' || mutation.type === 'characterData') {
                                            var raw = receiveNotiNoTraffic.textContent.trim();
                                            caseDocNoTraffic.value = (window.toThaiDocNo ? window.toThaiDocNo(raw) : raw);
                                        }
                                    });
                                });

                                observer.observe(receiveNotiNoTraffic, {
                                    childList: true,
                                    characterData: true,
                                    subtree: true
                                });

                                // กรณีที่มีค่าอยู่แล้วตั้งแต่เริ่มต้น
                                if (receiveNotiNoTraffic.textContent.trim()) {
                                    var rawInit = receiveNotiNoTraffic.textContent.trim();
                                    caseDocNoTraffic.value = (window.toThaiDocNo ? window.toThaiDocNo(rawInit) : rawInit);
                                }
                            }

                            // --- จัดการส่วน "อื่นๆ" ของช่องทางการรับแจ้ง ---
                            $(document).on('change', '#traffic_notify_other', function() {
                                const $otherInput = $('#traffic_notify_other_text');

                                if (this.checked) {
                                    // ถ้าติ๊กเลือก: แสดงช่องกรอก, ปลดล็อก และ Focus
                                    $otherInput.show().prop('disabled', false).focus();
                                } else {
                                    // ถ้าเอาติ๊กออก: ซ่อนช่องกรอก, ล็อกช่อง และล้างค่าที่กรอกไว้
                                    $otherInput.hide().prop('disabled', true).val('');
                                }
                            });

                            // ★ ระบบรูปรวม: ใช้ tpfHandlePhotoFiles เป็นตัวจัดการเดียว (เก็บ File object + render ทั้ง 2 ฟอร์ม)
                            $('#incident_photos_traffic, #camera_input_traffic').on('change', function(e) {
                                const files = e.target.files;
                                if (!files.length) return;
                                if (typeof tpfHandlePhotoFiles === 'function') {
                                    tpfHandlePhotoFiles(files);
                                }
                                $(this).val(''); // เคลียร์ค่า Input เพื่อเลือกไฟล์เดิมซ้ำได้
                            });

                            // ★ Drag & Drop zone (ฟอร์มปกติ)
                            (function() {
                                var dz = document.getElementById('traffic_photo_dropzone');
                                if (!dz) return;
                                dz.addEventListener('click', function() {
                                    document.getElementById('incident_photos_traffic').click();
                                });
                                ['dragenter', 'dragover'].forEach(function(ev) {
                                    dz.addEventListener(ev, function(e) {
                                        e.preventDefault(); e.stopPropagation();
                                        dz.style.borderColor = '#0d6efd';
                                        dz.style.background = '#e7f1ff';
                                    });
                                });
                                ['dragleave', 'drop'].forEach(function(ev) {
                                    dz.addEventListener(ev, function(e) {
                                        e.preventDefault(); e.stopPropagation();
                                        dz.style.borderColor = '#adb5bd';
                                        dz.style.background = '#f8f9fa';
                                    });
                                });
                                dz.addEventListener('drop', function(e) {
                                    var files = e.dataTransfer && e.dataTransfer.files;
                                    if (files && files.length && typeof tpfHandlePhotoFiles === 'function') {
                                        tpfHandlePhotoFiles(files);
                                    }
                                });
                            })();

                            $(document).on('change', '.inspector-select-traffic', function() {
                                updateInspectorSelectionTraffic();
                            });

                            // ดักจับ Event ครั้งเดียว แต่ครอบคลุมทั้ง .forensic-select-traffic และ .user-select-box-traffic
                            $(document).on('change', '.forensic-select-traffic, .user-select-box-traffic', function(e) {
                                const $this = $(this);
                                const idEmp = $this.val();
                                const targetInput = $this.data('pos-target');

                                // 1. จัดการล้างค่าเมื่อไม่ได้เลือกชื่อ
                                if (!idEmp) {
                                    $(targetInput).val('');
                                } else {
                                    // 2. เรียก AJAX ดึงตำแหน่ง (แชร์ Logic ร่วมกัน)
                                    $.ajax({
                                        url: "/csims/api/ReceiveNoti/getPos.php",
                                        type: 'GET',
                                        data: {
                                            idEmp: idEmp
                                        },
                                        dataType: 'json',
                                        success: function(response) {
                                            if (response.message == "success" && response.data) {
                                                $(targetInput).val(response.data.position_name);
                                            } else {
                                                $(targetInput).val('');
                                            }
                                        },
                                        error: function() {
                                            $(targetInput).val('');
                                        }
                                    });
                                }

                                // 3. Logic พิเศษ: ป้องกันชื่อซ้ำ (เฉพาะส่วนที่เป็น Forensic)
                                // เช็คว่าตัวที่กำลังเปลี่ยนมี class 'forensic-select-traffic' หรือไม่
                                if ($this.hasClass('forensic-select-traffic')) {
                                    if (e.originalEvent !== undefined || e.isTrigger) {
                                        updateForensicSelectionTraffic();
                                    }
                                }
                            });

                            $(document).on('change', '.js-plate-status', function() {
                                const $card = $(this).closest('.vehicle-card-traffic');
                                const $allCheckboxes = $card.find('.js-plate-status');
                                const $plateInput = $card.find('.js-plate-input');

                                // 1. Exclusive Logic: ถ้ามีการ "ติ๊กถูก" ให้เอาติ๊กถูกของตัวอื่นออก
                                // แต่ถ้าเป็นการ "เอาติ๊กออก" ก็ปล่อยให้ว่างได้ตามปกติ
                                if (this.checked) {
                                    $allCheckboxes.not(this).prop('checked', false);
                                }

                                // 2. Disable/Enable Logic: ตรวจสอบสถานะเฉพาะตัวที่มีค่าเป็น "ติด"
                                const isAttached = $card.find('input[value="ติด"]').is(':checked');

                                if (isAttached) {
                                    // ถ้าเลือก "ติด": เปิดช่องกรอก, บังคับกรอก, และ Focus
                                    $plateInput.prop('disabled', false).prop('required', true).focus();
                                } else {
                                    // ถ้าเลือก "ไม่ติด" หรือ "ไม่ได้เลือกเลย": ปิดช่องกรอก, ยกเลิกบังคับกรอก, และล้างค่า
                                    $plateInput.prop('disabled', true).prop('required', false).val('');
                                }
                            });

                            // ดักจับการเปลี่ยนแปลงของ Checkbox ที่มีคลาส js-purpose-check
                            $(document).on('change', '.js-purpose-check', function() {
                                // ค้นหา Input ที่อยู่ในบรรทัดเดียวกัน (parent div)
                                const $parent = $(this).closest('.form-check');
                                const $input = $parent.find('.js-purpose-input');

                                if (this.checked) {
                                    // ถ้าเลือก: ปลดล็อก และ focus ไปที่ช่องกรอก
                                    $input.prop('disabled', false).focus();
                                } else {
                                    // ถ้าเลิกเลือก: ล็อกช่อง และล้างค่าที่เคยกรอกไว้
                                    $input.prop('disabled', true).val('');
                                }
                            });

                            // ป้องกันการพิมพ์ตัวอักษรในช่องที่เป็นตัวเลข (สำหรับ qty 1 และ 2)
                            $(document).on('input', 'input[inputmode="numeric"]', function() {
                                this.value = this.value.replace(/[^0-9]/g, '');
                            });

                            // --- การควบคุม Input การดัดแปลงสภาพ ---
                            $(document).on('change', '.js-mod-check', function() {
                                const $card = $(this).closest('.forensic-vehicle-card');
                                const $allModChecks = $card.find('.js-mod-check');
                                const $detailInput = $card.find('.js-mod-detail');

                                // 1. ทำให้ทำงานเหมือน Radio: ถ้าติ๊กอันนี้ ให้เอาติ๊กอันอื่นออก
                                if (this.checked) {
                                    $allModChecks.not(this).prop('checked', false);
                                }

                                // 2. ตรวจสอบสถานะ: เฉพาะกรณีที่ตัว "มี" ถูกติ๊กอยู่เท่านั้นถึงจะ Enable
                                // เราจะเจาะจงไปที่ตัวที่มี value="มี" ใน Card นั้นๆ
                                const isHasModChecked = $card.find('input[value="มี"]').is(':checked');

                                if (isHasModChecked) {
                                    $detailInput.prop('disabled', false).focus();
                                } else {
                                    // ถ้า "มี" ไม่ถูกติ๊ก (ไม่ว่าจะติ๊ก "ไม่มี" หรือไม่ติ๊กอะไรเลย) ให้ล็อกและล้างค่า
                                    $detailInput.prop('disabled', true).val('');
                                }
                            });
                        })

                        // =========================================================
                        // Vehicle LOGIC (รถของกลาง)
                        // =========================================================

                        // --- ฟังก์ชันจัดลำดับรายการรถ ---
                        function reIndexVehicleCardsTraffic() {
                            const $container = $('#vehicle_container_traffic');
                            const $cards = $container.find('.vehicle-card-traffic');

                            $cards.each(function(index) {
                                // ค้นหา badge แล้วเปลี่ยนข้อความเป็น "รถของกลางที่ X"
                                $(this).find('.js-vehicle-index-badge').first().text(`รถของกลางที่ ${index + 1}`);

                                // จัดการปุ่มลบ: ถ้าเหลือใบเดียวให้ซ่อน
                                if ($cards.length === 1) {
                                    $(this).find('.btn-outline-danger').hide();
                                } else {
                                    $(this).find('.btn-outline-danger').show();
                                }
                            });
                        }

                        // --- ฟังก์ชันเพิ่มรายการรถของกลาง ---
                        function addVehicleCardTraffic() {
                            const container = document.getElementById('vehicle_container_traffic');
                            const newCard = document.createElement('div');
                            newCard.className = 'vehicle-card-traffic bg-light p-3 rounded-3 border mb-3 animate__animated animate__fadeIn';

                            newCard.innerHTML = `
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge bg-primary text-white js-vehicle-index-badge"></span>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVehicleCardTraffic(this)">
                                        <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                    </button>
                                </div>

                                <div class="bg-white p-3 rounded-3 shadow-sm">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">รถ (ลักษณะรถ)</label>
                                            <input type="text" class="form-control" name="vehicle_detail[]" placeholder="เช่น รถยนต์นั่งส่วนบุคคล">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">ยี่ห้อ</label>
                                            <input type="text" class="form-control" name="vehicle_brand[]" placeholder="ยี่ห้อ">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">รุ่น</label>
                                            <input type="text" class="form-control" name="vehicle_model[]" placeholder="รุ่น">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">สี</label>
                                            <input type="text" class="form-control" name="vehicle_color[]" placeholder="สี">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">การติดแผ่นป้ายทะเบียน</label>
                                            <div class="d-flex gap-3 mt-1">
                                                <div class="form-check">
                                                    <input class="form-check-input js-plate-status" type="checkbox" name="vehicle_plate_attach[]" value="ติด" checked>
                                                    <label class="form-check-label">ติด</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input js-plate-status" type="checkbox" name="vehicle_plate_none[]" value="ไม่ติด">
                                                    <label class="form-check-label">ไม่ติด</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label">เลขทะเบียน/จังหวัด</label>
                                            <input type="text" class="form-control js-plate-input" name="vehicle_plate_no[]" placeholder="เช่น กข 1234 กรุงเทพฯ">
                                        </div>
                                    </div>
                                </div>
                            `;

                            container.appendChild(newCard);
                            reIndexVehicleCardsTraffic();
                        }

                        // --- ฟังก์ชันลบรายการรถของกลาง ---
                        function removeVehicleCardTraffic(button) {
                            $(button).closest('.vehicle-card-traffic').remove();
                            reIndexVehicleCardsTraffic();
                        }

                        // =========================================================
                        // Inspector LOGIC
                        // =========================================================

                        // --- ฟังก์ชันจัดลำดับและควบคุมปุ่มลบ (Inspector) ---
                        function reIndexInspectorsTraffic() {
                            const $rows = $('.inspector-row-traffic');
                            $rows.each(function(index) {
                                // 1. ปรับเลขลำดับ (5.1, 5.2, ...)
                                $(this).find('.inspector-index-label-traffic').text(`5.${index + 1}`);

                                // 2. ควบคุมปุ่มลบ: ถ้าเป็นแถวแรก (index 0) ให้ซ่อนเสมอ
                                const $btn = $(this).find('.remove-inspector-traffic');
                                if (index === 0) {
                                    $btn.addClass('d-none'); // ซ่อนปุ่มลบของรายการ 5.1
                                } else {
                                    $btn.removeClass('d-none'); // แสดงปุ่มลบของรายการอื่นๆ
                                }
                            });
                            // อัปเดตสถานะรายการที่ถูกเลือกทุกครั้งที่มีการ Re-index
                            updateInspectorSelectionTraffic();
                        }

                        // --- ฟังก์ชันเพิ่มแถวผู้ตรวจ ---
                        function addInspectorRowTraffic() {
                            const container = document.getElementById('inspector_container_traffic');
                            const newRow = document.createElement('div');
                            newRow.className = 'd-flex align-items-center mb-3 inspector-row-traffic animate__animated animate__fadeIn';

                            newRow.innerHTML = `
                                        <div class="text-end pe-3" style="width: 50px;">
                                            <span class="fw-bold text-secondary inspector-index-label-traffic"></span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <select class="form-select inspector-select-traffic" name="inspector_id[]">
                                                ${inspectorOptionsHTMLTraffic} 
                                            </select>
                                        </div>
                                        <div class="ms-2" style="width: 32px;">
                                            <button type="button" class="btn-remove-row remove-inspector-traffic" 
                                                    onclick="removeInspectorRowTraffic(this)"
                                                    title="ลบรายการ"
                                                    >
                                                <i class="fas fa-times fs-5"></i>
                                            </button>
                                        </div>
                                    `;

                            container.appendChild(newRow);

                            // ตั้งค่า Select2 ให้ตัวที่เพิ่มใหม่
                            const $lastSelect = $(newRow).find('.inspector-select-traffic');
                            // เรียกใช้ฟังก์ชัน init ที่อยู่ใน ready (หรือประกาศไว้ข้างนอกก็ได้)
                            if (typeof initSelect2Traffic === 'function') {
                                initSelect2Traffic($lastSelect);
                            }

                            // ผูก Event ทันทีเพื่อให้เช็ค Disabled ทันทีที่เลือกคน
                            $lastSelect.on('change', function() {
                                updateInspectorSelectionTraffic();
                            });

                            reIndexInspectorsTraffic();
                        }

                        // --- ฟังก์ชันอัปเดตสถานะ Disabled ของตัวเลือกผู้ตรวจที่ซ้ำกัน ---
                        function updateInspectorSelectionTraffic() {
                            let selectedValues = [];

                            // 1. เก็บค่า ID ที่ถูกเลือกทั้งหมดไว้ใน Array
                            $('.inspector-select-traffic').each(function() {
                                const val = $(this).val();
                                if (val) selectedValues.push(val);
                            });

                            // 2. วนลูปตัวเลือก Select2 ทุกตัวเพื่อกำหนดสถานะ Disabled
                            $('.inspector-select-traffic').each(function() {
                                const $currentSelect = $(this);
                                const currentVal = $currentSelect.val();

                                $currentSelect.find('option').each(function() {
                                    const optVal = $(this).val();
                                    if (!optVal) return; // ข้ามตัวเลือกว่าง (กรุณาเลือก)

                                    // เงื่อนไข: ถ้าค่านี้ถูกเลือกไปแล้วในช่องอื่น (ไม่ใช่ช่องตัวเอง) ให้ Disabled
                                    if (selectedValues.includes(optVal) && optVal !== currentVal) {
                                        $(this).prop('disabled', true);
                                    } else {
                                        $(this).prop('disabled', false);
                                    }
                                });

                                // 3. แจ้ง Select2 ให้รีเฟรชการแสดงผล (เฉพาะส่วน UI)
                                // ใช้ .trigger('change.select2') เพื่อไม่ให้เกิด Loop การเรียก Change Event
                                $currentSelect.trigger('change.select2');
                            });
                        }

                        // --- ฟังก์ชันลบแถวผู้ตรวจ ---
                        function removeInspectorRowTraffic(button) {
                            const $rows = $('.inspector-row-traffic');
                            // ป้องกันการลบถ้าเหลือแถวเดียว (เผื่อปุ่มลบไม่ซ่อน)
                            if ($rows.length > 1) {
                                $(button).closest('.inspector-row-traffic').remove();
                                reIndexInspectorsTraffic();
                            }
                        }

                        // =========================================================
                        // Forensic LOGIC
                        // =========================================================

                        // --- ฟังก์ชันจัดลำดับเลข 7.1, 7.2... ---
                        function reIndexForensicRowsTraffic() {
                            const rows = document.querySelectorAll('.forensic-row-traffic');
                            rows.forEach((row, index) => {
                                row.querySelector('.forensic-index-label-traffic').innerText = `7.${index + 1}`;
                                // แสดงปุ่มลบเฉพาะเมื่อมีมากกว่า 1 แถว
                                const removeBtn = row.querySelector('.remove-forensic-traffic');
                                if (rows.length > 1) {
                                    removeBtn.classList.remove('d-none');
                                } else {
                                    removeBtn.classList.add('d-none');
                                }
                            });
                        }

                        // --- ฟังก์ชันเพิ่มแถวผู้ตรวจพิสูจน์ ---
                        function addForensicRowTraffic() {
                            const container = document.getElementById('forensic_container_traffic');
                            // ใช้ Timestamp + Random เพื่อให้ ID ไม่ซ้ำกันแน่นอน
                            const uniqueId = Date.now() + Math.floor(Math.random() * 1000);
                            const uniquePosId = `forensic_pos_${uniqueId}`;
                            const div = document.createElement('div');
                            div.className = 'forensic-row-traffic d-flex align-items-center mb-3 animate__animated animate__fadeIn';

                            div.innerHTML = `
                                    <div class="text-end pe-3" style="width: 50px;">
                                        <span class="fw-bold text-secondary forensic-index-label-traffic">7.</span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <select class="form-select user-select-box-traffic forensic-select-traffic" 
                                                        name="forensic_id[]" data-pos-target="#${uniquePosId}">
                                                    ${forensicOptionsHTML}
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control bg-light" id="${uniquePosId}" 
                                                    name="forensic_position[]" readonly placeholder="ตำแหน่ง (แสดงอัตโนมัติ)">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ms-2" style="width: 32px;">
                                        <button type="button" class="btn-remove-row remove-forensic-traffic" onclick="removeForensicRowTraffic(this)" title="ลบรายการ">
                                            <i class="fas fa-times fs-5 text-danger"></i>
                                        </button>
                                    </div>
                                `;

                            container.appendChild(div);

                            // Init เฉพาะ Select2
                            const $newSelect = $(div).find('.forensic-select-traffic');
                            initSelect2Traffic($newSelect);

                            // จัดลำดับเลข + อัปเดตรายชื่อที่ถูกเลือกไปแล้วในแถวอื่นๆ ให้แถวใหม่
                            reIndexForensicRowsTraffic();
                            updateForensicSelectionTraffic();
                        }

                        // --- ฟังก์ชันป้องกันเลือกผู้ตรวจพิสูจน์ซ้ำ ---
                        function updateForensicSelectionTraffic() {
                            let selectedValues = [];
                            $('.forensic-select-traffic').each(function() {
                                const val = $(this).val();
                                if (val) selectedValues.push(val);
                            });

                            $('.forensic-select-traffic').each(function() {
                                const $currentSelect = $(this);
                                const currentVal = $currentSelect.val();

                                $currentSelect.find('option').each(function() {
                                    const optVal = $(this).val();
                                    if (!optVal) return;

                                    if (selectedValues.includes(optVal) && optVal !== currentVal) {
                                        $(this).prop('disabled', true);
                                    } else {
                                        $(this).prop('disabled', false);
                                    }
                                });
                                if ($currentSelect.data('select2')) {
                                    $currentSelect.trigger('change.select2');
                                }
                            });
                        }

                        // --- ฟังก์ชันลบแถว ---
                        function removeForensicRowTraffic(btn) {
                            btn.closest('.forensic-row-traffic').remove();
                            reIndexForensicRowsTraffic();
                            updateForensicSelectionTraffic();
                        }

                        // =========================================================
                        // Forensic Vehicle LOGIC
                        // =========================================================

                        // 1. กำหนดแม่พิมพ์ของร่องรอย 4 ด้าน เพื่อใช้สร้างใน Card ใหม่
                        const forensicSides = {
                            'front': 'ด้านหน้า',
                            'left': 'ด้านซ้าย',
                            'right': 'ด้านขวา',
                            'back': 'ด้านหลัง'
                        };

                        // --- ฟังก์ชันจัดลำดับรายการรถใหม่ทั้งหมด ---
                        function reIndexForensicVehicles() {
                            const $cards = $('.forensic-vehicle-card');
                            $cards.each(function(vIdxZero, card) {
                                const vIdx = vIdxZero + 1;
                                const $card = $(card);

                                // 1. อัปเดตหัวข้อ และซ่อนปุ่มลบถ้าเหลือใบเดียว
                                $card.find('.js-v-index-label').text(`รถของกลางที่ ${vIdx}`);
                                $card.find('.js-btn-remove-v').toggle($cards.length > 1); // เหลือใบเดียว ซ่อนปุ่มลบ

                                // 2. อัปเดต Radio/Checkbox การดัดแปลง
                                $card.find('.js-mod-check').attr('name', `forensic_v_mod_status_${vIdx}`);

                                // อัปเดตเฉพาะ Name และ Onclick ของร่องรอยทั้ง 4 ด้าน
                                Object.keys(forensicSides).forEach(side => {
                                    //  อัปเดต Name ของ Input ในตารางร่องรอย
                                    $card.find(`input[name^="trace_${side}_detail_"]`).attr('name', `trace_${side}_detail_${vIdx}[]`);
                                    $card.find(`input[name^="trace_${side}_height_"]`).attr('name', `trace_${side}_height_${vIdx}[]`);

                                    //  อัปเดตฟังก์ชันในปุ่มเพิ่มรอย
                                    $card.find(`.js-add-trace-btn`).filter(function() {
                                        return $(this).attr('onclick').includes(`'trace_${side}'`);
                                    }).attr('onclick', `addTraceRow(this, 'trace_${side}', ${vIdx})`);
                                });

                            });
                        }

                        // --- ฟังก์ชันเพิ่ม Card รถของกลาง ---
                        function addForensicVehicleCard() {
                            const $container = $('#forensic_vehicle_container');
                            const vIdx = $container.find('.forensic-vehicle-card').length + 1; // ลำดับเบื้องต้น

                            // สร้างกล่องร่องรอย 4 ด้านเรียงกัน
                            let tracesHtml = '';
                            Object.entries(forensicSides).forEach(([side, label]) => {
                                tracesHtml += `
                                    <div class="bg-white p-3 rounded border mb-3 shadow-sm side-trace-box">
                                        <h6 class="small fw-bold text-primary border-bottom pb-2 mb-3">
                                            <i class="fas fa-search me-1"></i> ร่องรอย${label}
                                        </h6>
                                        <table class="table table-sm table-borderless align-middle mb-2">
                                            <thead>
                                                <tr class="small text-muted">
                                                    <th>ร่องรอย/บริเวณ/ตำแหน่ง</th>
                                                    <th style="width: 130px;">สูงจากพื้น (cm.)</th>
                                                    <th style="width: 30px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody class="js-trace-body">
                                                <tr>
                                                    <td><input type="text" class="form-control form-control-sm" name="trace_${side}_detail_${vIdx}[]" placeholder="ระบุตำแหน่ง/ลักษณะรอย"></td>
                                                    <td><input type="number" class="form-control form-control-sm text-center" name="trace_${side}_height_${vIdx}[]" placeholder="0.00"></td>
                                                    <td style="width: 40px;"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <button type="button" class="btn btn-outline-primary btn-sm w-100 border-dashed js-add-trace-btn" 
                                                onclick="addTraceRow(this, 'trace_${side}', ${vIdx})">
                                            <i class="fas fa-plus-circle me-1"></i> เพิ่มรายการร่องรอย${label}
                                        </button>
                                    </div>`;
                            });

                            const cardHtml = `
                                <div class="forensic-vehicle-card bg-light p-3 rounded-3 border mb-4 shadow-sm animate__animated animate__fadeIn">
                                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-car me-2"></i><span class="js-v-index-label">รถของกลางที่ ${vIdx}</span></h6>
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0 js-btn-remove-v" onclick="removeForensicVehicleCard(this)">
                                            <i class="fas fa-trash-alt me-1"></i> ลบรถคันนี้
                                        </button>
                                    </div>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">สภาพรถ</label>
                                            <input type="text" class="form-control" name="forensic_v_condition[]" placeholder="ระบุสภาพทั่วไปของรถ...">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">การต่อเติมหรือดัดแปลงสภาพ</label>
                                            <div class="px-3 py-2 border rounded bg-white">
                                                <div class="d-flex gap-4 align-items-center">
                                                    <div class="form-check flex-shrink-0 text-nowrap mb-0">
                                                        <input class="form-check-input js-mod-check cursor-pointer" type="checkbox" name="forensic_v_mod_status_${vIdx}" value="ไม่มี" checked>
                                                        <label class="form-check-label cursor-pointer">ไม่มี</label>
                                                    </div>
                                                    <div class="form-check flex-shrink-0 text-nowrap mb-0">
                                                        <input class="form-check-input js-mod-check cursor-pointer" type="checkbox" name="forensic_v_mod_status_${vIdx}" value="มี">
                                                        <label class="form-check-label cursor-pointer">มี (ระบุ)</label>
                                                    </div>
                                                    <input type="text" class="form-control flex-grow-1 js-mod-detail" name="forensic_v_mod_detail[]" placeholder="รายละเอียดการดัดแปลง..." disabled>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <label class="form-label fw-bold text-secondary mb-2">รายการร่องรอยความเสียหายแยกตามตำแหน่ง</label>
                                        <div class="forensic-trace-wrapper">
                                            ${tracesHtml}
                                        </div>
                                </div>`;

                            $container.append(cardHtml);
                            reIndexForensicVehicles();
                        }

                        // --- ฟังก์ชันลบ Card รถ ---
                        function removeForensicVehicleCard(btn) {
                            $(btn).closest('.forensic-vehicle-card').remove();
                            reIndexForensicVehicles();
                        }

                        // --- ฟังก์ชันเพิ่มแถวร่องรอย (Row) ในตาราง ---
                        function addTraceRow(btn, sidePrefix, vIdx) {
                            // หา tbody ภายในกล่องขาว (side-trace-box) ที่ปุ่มนั้นอยู่
                            const $tbody = $(btn).closest('.side-trace-box').find('.js-trace-body');

                            const rowHtml = `
                                <tr class="animate__animated animate__fadeIn">
                                    <td><input type="text" class="form-control form-control-sm" name="${sidePrefix}_detail_${vIdx}[]" placeholder="ระบุตำแหน่ง/ลักษณะรอย"></td>
                                    <td><input type="number" class="form-control form-control-sm text-center" name="${sidePrefix}_height_${vIdx}[]" placeholder="0.00"></td>
                                    <td class="text-center">
                                        <button type="button" class="btn-remove-row mx-auto" 
                                                onclick="$(this).closest('tr').remove()" 
                                                title="ลบรอยนี้">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>`;
                            $tbody.append(rowHtml);
                        }

                        // =========================================================
                        // Comparision LOGIC
                        // =========================================================

                        // --- ฟังก์ชันจัดลำดับเลข 9.1, 9.2 ---
                        function reIndexCompareRows() {
                            const $rows = $('.comparison-row');
                            $rows.each(function(index) {
                                const idx = index + 1;
                                $(this).find('.js-compare-index').text(`9.${idx}`);
                                $(this).find('.js-btn-remove-compare').toggle($rows.length > 1);
                            });
                        }

                        // --- ฟังก์ชันเพิ่มแถวเปรียบเทียบ ---
                        function addCompareRow() {
                            const $container = $('#comparison_container');
                            const newIdx = $container.find('.comparison-row').length + 1;

                            // ดึง Template จากอันแรก (สะอาด)
                            const $newRow = $container.find('.comparison-row:first').clone();

                            // ล้างค่าข้อมูล
                            $newRow.find('input').val('');
                            $newRow.removeClass('animate__fadeIn').addClass('animate__fadeIn');

                            $container.append($newRow);
                            reIndexCompareRows();
                        }

                        // --- ฟังก์ชันลบแถวเปรียบเทียบ ---
                        function removeCompareRow(btn) {
                            $(btn).closest('.comparison-row').remove();
                            reIndexCompareRows();
                        }

                        // =========================================================
                        // Photos LOGIC
                        // =========================================================
                        function renderAttachmentStoreTraffic() {
                            const $grid = $('#attachments_grid_traffic');
                            const $wrapper = $('#attachments_wrapper_traffic');
                            const $badge = $('#file_count_badge_traffic');

                            $grid.empty();

                            const totalCount = attachmentStoreTraffic.length;
                            $badge.text(totalCount);

                            if (totalCount > 0) {
                                $wrapper.removeClass('d-none');

                                // รหัสภาพ: อัปเดตเฉพาะตอน user แนบ/ลบเอง ไม่ทับค่าที่โหลดจาก DB
                                $('#photo_amount_traffic').val(totalCount);
                                if (!window.__trafficLoadingPhotos) {
                                    $('#photo_id_start_traffic').val(attachmentStoreTraffic[0].filename || attachmentStoreTraffic[0].name || 'photo');
                                    $('#photo_id_end_traffic').val(attachmentStoreTraffic[totalCount - 1].filename || attachmentStoreTraffic[totalCount - 1].name || 'photo');
                                }

                                attachmentStoreTraffic.forEach((file, index) => {
                                    const isExist = (file.isExisting || file.existing);
                                    const statusBadge = isExist ?
                                        '<span class="badge bg-info text-white fw-normal" style="font-size: 0.65rem;">รูปเดิม</span>' :
                                        '<span class="badge bg-success text-white fw-normal" style="font-size: 0.65rem;">รูปใหม่</span>';

                                    const imgSrc = file.base64 || file.src || '';
                                    const dispName = file.filename || file.name || 'photo';
                                    const fileSize = file.size ? file.size : 'N/A';
                                    const fileDate = file.date ? file.date : '-';

                                    const html = `
                                            
                                                <div class="col animate__animated animate__fadeIn">
                                                    <div class="card attachment-card h-100 shadow-sm rounded-3 overflow-hidden">
                                                        
                                                        <div class="card-header-actions">
                                                            <button type="button" class="btn btn-outline-primary btn-action-sm" 
                                                                onclick="showImagePreviewTraffic(${index})" title="ดูรูปภาพ">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger btn-action-sm" 
                                                                onclick="removeTrafficPhoto('${file.id}')" title="ลบรูปภาพ">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </div>

                                                        <div class="img-box-middle" onclick="showImagePreviewTraffic(${index})" style="cursor:zoom-in;">
                                                            <img src="${imgSrc}" alt="${dispName}">
                                                        </div>

                                                        <div class="card-body p-2 d-flex flex-column bg-white">
                                                            <div class="text-dark fw-bold text-truncate mb-2" style="font-size: 0.7rem; letter-spacing: 0.01rem;" title="${dispName}">
                                                                ${dispName}
                                                            </div>
                                                            
                                                            <div class="metadata-group mt-1 flex-grow-1">
                                                                <div class="d-flex align-items-center text-secondary mb-1" style="font-size: 0.6rem;">
                                                                    <i class="fas fa-fw fa-hdd me-1 opacity-50"></i>
                                                                    <span>${fileSize}</span>
                                                                </div>
                                                                <div class="d-flex align-items-center text-secondary" style="font-size: 0.6rem;">
                                                                    <i class="fas fa-fw fa-calendar-alt me-1 opacity-50"></i>
                                                                    <span>${fileDate}</span>
                                                                </div>
                                                                <div class="d-flex justify-content-end">
                                                                ${statusBadge}
                                                            </div>
                                                            </div>

                                                            
                                                        </div>
                                                    </div>
                                                </div>`;
                                    $grid.append(html);
                                });
                            } else {
                                $wrapper.addClass('d-none');
                                $('#photo_amount_traffic').val('');
                                if (!window.__trafficLoadingPhotos) {
                                    $('#photo_id_start_traffic').val('');
                                    $('#photo_id_end_traffic').val('');
                                }
                            }
                        }

                        // ★ ระบบรูปรวม: render ทั้งฟอร์มปกติและเสมือนจาก store เดียว
                        window.trafficRenderAll = function() {
                            if (typeof renderAttachmentStoreTraffic === 'function') renderAttachmentStoreTraffic();
                            if (typeof tpfRenderPhotosFromStore === 'function') tpfRenderPhotosFromStore();
                        };

                        // ★ ล้างรูปทั้งหมด (ใช้ตอนเปิดเรคคอร์ดใหม่/prefill) — ไม่เรียกตอน toggle
                        window.resetTrafficPhotos = function() {
                            attachmentStoreTraffic = [];
                            window.attachmentStoreTraffic = attachmentStoreTraffic;
                            // ★ FIX: อัปเดต local ref ให้ตรงกับ window scope เสมอ
                            window.deletedExistingPhotosTraffic = [];
                            window.__trafficLoadingPhotos = false;
                            if (typeof window.trafficRenderAll === 'function') window.trafficRenderAll();
                        };

                        // ★ ลบรูปแบบ id เดียว ใช้ร่วมทั้ง 2 ฟอร์ม + track file_id ที่ลบเพื่อส่งให้ API
                        window.removeTrafficPhoto = function(id) {
                            var item = attachmentStoreTraffic.find(function(x) { return x.id === id; });
                            if (!item) return;
                            var doRemove = function() {
                                if ((item.isExisting || item.existing) && item.db_file_id) {
                                    window.deletedExistingPhotosTraffic = window.deletedExistingPhotosTraffic || [];
                                    window.deletedExistingPhotosTraffic.push(item.db_file_id);
                                }
                                attachmentStoreTraffic = attachmentStoreTraffic.filter(function(x) { return x.id !== id; });
                                window.attachmentStoreTraffic = attachmentStoreTraffic;
                                if (typeof window.trafficRenderAll === 'function') window.trafficRenderAll();
                            };
                            // ลบรูปทันทีแบบเดียวกับ Life modal โดยไม่ต้องมี Popup ยืนยัน
                            doRemove();
                        };

                        // ฟังก์ชันสำหรับเปิดดูรูปขนาดเต็ม (Lightbox)
                        function showImagePreviewTraffic(index) {
                            const file = attachmentStoreTraffic[index];
                            if (!file) return;

                            // 1. ตั้งค่ารูปและชื่อ
                            $('#lightboxImageTraffic').attr('src', file.base64 || file.src);
                            $('#lightboxFileNameTraffic').text(file.filename || file.name);

                            // 2. แสดง Lightbox นุ่มๆ
                            $('#customLightboxTraffic').removeClass('d-none').hide().fadeIn(200);

                            // 3. ล็อก Scrollbar
                            $('body').addClass('lightbox-open');
                        }

                        // ฟังก์ชันปิดเมื่อคลิกพื้นที่ว่าง
                        function closeLightboxOutsideTraffic(event) {
                            // ตรวจสอบว่า ID ที่คลิกคือตัวพื้นหลัง (customLightboxTraffic) จริงๆ ไม่ใช่ตัวรูปภาพ
                            if (event.target.id === 'customLightboxTraffic') {
                                closeLightboxTraffic();
                            }
                        }

                        // ฟังก์ชันปิด Lightbox 
                        function closeLightboxTraffic() {
                            $('#customLightboxTraffic').fadeOut(200, function() {
                                $(this).addClass('d-none');
                            });
                            $('body').removeClass('lightbox-open');
                        }

                        // legacy: เรียกต่อไปยังระบบรวม (เผื่อมีจุดที่ยังเรียกแบบ index)
                        function removeFileFromStoreTraffic(index) {
                            const file = attachmentStoreTraffic[index];
                            if (!file) return;
                            window.removeTrafficPhoto(file.id);
                        }

                        // ==============================================================================
                        // --- ฟังก์ชัน Validation และรวบรวมข้อมูลสำหรับ Traffic Modal เพื่อทำการบันทึกลง DB---
                        // ==============================================================================
                        window.prepareDataForSubmissionTraffic = async function() {
                            const form = document.getElementById('incidentCheckListFormTraffic');
                            let allErrors = [];
                            const $btnSave = $('#btn_save_traffic');
                            const modalBody = document.querySelector('#addCheckListModalTraffic .modal-body');

                            // 1. HTML5 Validation (required fields)
                            form.classList.add('was-validated');
                            const invalidFields = [];
                            // ค้นหาฟิลด์ที่ผิดเงื่อนไข (รวมถึงฟิลด์ที่ไม่ได้ถูก disabled)
                            const allInvalid = form.querySelectorAll(':invalid');

                            allInvalid.forEach(field => {
                                if (field.tagName === 'FIELDSET' || field.tagName === 'FORM') return;

                                // หาชื่อฟิลด์จาก label
                                let fieldName = '';
                                const label = field.closest('.mb-3, .col-md-6, .col-md-4, .col-12, .col-lg-6')?.querySelector('label');
                                if (label) {
                                    fieldName = label.textContent.replace('*', '').trim();
                                } else {
                                    fieldName = field.getAttribute('placeholder') || field.getAttribute('name') || 'ฟิลด์ข้อมูล';
                                }

                                invalidFields.push({
                                    element: field,
                                    name: fieldName
                                });
                            });

                            // รวบรวม Error Messages
                            if (invalidFields.length > 0) {
                                invalidFields.forEach(item => {
                                    allErrors.push(`กรุณากรอก/เลือก: ${item.name}`);
                                });
                            }

                            // ถ้ามีข้อผิดพลาด แสดง Alert
                            if (allErrors.length > 0) {
                                // 1. หาฟิลด์แรกที่ผิดพลาด (querySelectorAll คืนค่าตามลำดับใน DOM อยู่แล้ว)
                                const firstInvalid = allInvalid[0];

                                if (firstInvalid) {
                                    const modalBody = document.querySelector('#addCheckListModalTraffic .modal-body');
                                    let targetElement = firstInvalid;

                                    // รองรับกรณีเป็น Select2 (ตัวตนจริงถูกซ่อน ต้อง scroll ไปที่ container ของมัน)
                                    if ($(firstInvalid).hasClass('select2-hidden-accessible')) {
                                        targetElement = $(firstInvalid).next('.select2-container')[0] || firstInvalid;
                                    }

                                    if (modalBody && targetElement) {
                                        // คำนวณตำแหน่งที่ต้อง Scroll ไปภายใน Modal
                                        const targetRect = targetElement.getBoundingClientRect();
                                        const modalRect = modalBody.getBoundingClientRect();
                                        const relativeTop = targetRect.top - modalRect.top + modalBody.scrollTop;

                                        // สั่ง Scroll ทันที (smooth)
                                        modalBody.scrollTo({
                                            top: relativeTop - 100,
                                            behavior: 'smooth'
                                        });

                                        // เพิ่มลูกเล่น Highlight ฟิลด์ที่ผิด (ถ้าต้องการ)
                                        targetElement.focus({
                                            preventScroll: true
                                        });
                                    }
                                }

                                // 2. แสดง Swal ตามปกติ (จะแสดงทับหน้าจอที่กำลัง Scroll อยู่)
                                let errorHTML = '<div style="text-align: left; max-height: 400px; overflow-y: auto;">';
                                allErrors.slice(0, 10).forEach((err, index) => {
                                    errorHTML += `<div style="margin-bottom: 5px;">${index + 1}. ${err}</div>`;
                                });
                                if (allErrors.length > 10) errorHTML += `<div class="mt-2 text-muted small">... และอีก ${allErrors.length - 10} รายการ</div>`;
                                errorHTML += '</div>';

                                Swal.fire({
                                    icon: 'warning',
                                    title: `พบข้อผิดพลาด ${allErrors.length} รายการ`,
                                    html: errorHTML,
                                    confirmButtonText: 'ตกลง',
                                    confirmButtonColor: '#0d6efd',
                                    returnFocus: false
                                });

                                return; // หยุดการทำงาน
                            }

                            // 2. เตรียมข้อมูล Canvas (ลายเซ็น, แผนผัง, ไดอะแกรมร่างกาย)
                            if (typeof signaturePads !== 'undefined') {
                                const padsMapping = [{
                                        pad: 'sig-canvas-receiver-traffic',
                                        target: '#receiver_signature_data_traffic'
                                    },
                                    {
                                        pad: 'sig-canvas-sender-traffic',
                                        target: '#sender_signature_data_traffic'
                                    }
                                ];

                                padsMapping.forEach(item => {
                                    const padObj = signaturePads[item.pad];
                                    if (padObj && !padObj.isEmpty()) {
                                        $(item.target).val(padObj.toDataURL('image/png'));
                                    }
                                });
                            }

                            // 3. ส่งข้อมูลผ่าน AJAX (Offline-aware)
                            const formData = new FormData(form);

                            // ★ ระบบรูปรวม: ส่งเฉพาะรูปใหม่ (มี File object) รูปเดิมคงไว้ที่ DB เว้นแต่ถูกลบ
                            formData.delete('incident_photos_traffic[]');
                            if (typeof attachmentStoreTraffic !== 'undefined' && attachmentStoreTraffic.length > 0) {
                                attachmentStoreTraffic.forEach((item) => {
                                    if (item.file) {
                                        formData.append('incident_photos_traffic[]', item.file, item.filename || item.name || 'photo.jpg');
                                        formData.append('photo_captions_traffic[]', item.caption || '');
                                    }
                                });
                            }
                            // รายการรูปเดิมที่ถูกลบ -> ส่ง file_id ให้ API ลบ BLOB
                            if (window.deletedExistingPhotosTraffic && window.deletedExistingPhotosTraffic.length > 0) {
                                formData.append('deleted_photo_file_ids', JSON.stringify(window.deletedExistingPhotosTraffic));
                            }

                            // ยืนยันก่อนบันทึก
                            Swal.fire({
                                title: 'ยืนยันการบันทึกข้อมูล',
                                text: 'กรุณาตรวจสอบความถูกต้องก่อนบันทึก',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#198754',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'ยืนยัน, บันทึกเลย!',
                                cancelButtonText: 'ยกเลิก',
                            }).then(async (resultConfirm) => {
                            if (!resultConfirm.isConfirmed) return;

                            const trafficUrl = form.action;
                            if (!navigator.onLine) {
                                await saveChecklistOffline(formData, trafficUrl, '#addCheckListModalTraffic');
                                return;
                            }
                            const backendOkTraffic = await checkBackendHealth();
                            if (!backendOkTraffic) {
                                await saveChecklistOffline(formData, trafficUrl, '#addCheckListModalTraffic');
                                return;
                            }

                            Swal.fire({
                                title: 'กำลังบันทึกข้อมูล...',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });

                            $.ajax({
                                url: trafficUrl,
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                success: function(response) {
                                    Swal.close();
                                    if (response.success) {
                                        Swal.fire({
                                                icon: 'success',
                                                title: 'สำเร็จ',
                                                text: response.message || 'บันทึกข้อมูลเรียบร้อย',
                                                confirmButtonText: 'ตกลง'
                                            })
                                            .then(() => {
                                                ['addCheckListModalTraffic', 'trafficFormPdfModal'].forEach(function(id) {
                                                    var el = document.getElementById(id);
                                                    if (el) { var m = bootstrap.Modal.getInstance(el); if (m) m.hide(); }
                                                });
                                                if (typeof loadData === 'function') loadData();
                                            });
                                    } else {
                                        $btnSave.prop('disabled', false).html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล');
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'เกิดข้อผิดพลาด',
                                            text: response.message || 'ไม่สามารถบันทึกข้อมูลได้',
                                            confirmButtonText: 'ตกลง'
                                        });
                                    }
                                },
                                error: async function() {
                                    Swal.close();
                                    $btnSave.prop('disabled', false).html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล');
                                    await saveChecklistOffline(formData, trafficUrl, '#addCheckListModalTraffic');
                                }
                            });
                            }); // end confirmation .then()
                        };

                        // ============================================================================================
                        // --- ฟังก์ชัน สำหรับ load ข้อมูลมา fill สำหรับการ update data / แสดง modal เปล่า กรณีเป็นรายการใหม่ ---
                        // ============================================================================================
                        function loadTrafficDataToModal(incidentId) {
                            const $form = $('#incidentCheckListFormTraffic');
                            // 1. Reset ฟอร์มและสถานะ Validation
                            $form[0].reset();
                            $form.removeClass('was-validated');
                            $form.find('.select2-hidden-accessible').val(null).trigger('change');
                            // ล้างคอนเทนเนอร์และรีเซ็ตเลข Index ตัวแปร Global ให้กลับไปที่เริ่มต้น
                            $('#vehicle_container_traffic, #inspector_container_traffic, #forensic_container_traffic, #forensic_vehicle_container, #comparison_container').empty();

                            $.get('./api/incidentCheckList/getTrafficData.php', {
                                incident_id: incidentId
                            }, function(res) {
                                if (res.success) {
                                    // ==========================================
                                    // CASE 1: มีข้อมูลเดิม (โหมดแก้ไข)
                                    // ==========================================

                                    // log มาดูโครงสร้างข้อมูล
                                    console.log(res)

                                    const d = res.data;
                                    // เก็บข้อมูลลายเซ็นไว้ในตัวแปรชั่วคราวก่อน
                                    const savedSignatures = d.signatures;

                                    const gen = d.general_info;
                                    // 2. Fill ข้อมูลพื้นฐาน (General Info)
                                    $('[name="case_doc_traffic"]').val(gen.case_doc_no); // 1. การรับแจ้งเหตุ - คดี
                                    $('[name="case_date"]').val(gen.case_date); // 1. การรับแจ้งเหตุ - วันที่
                                    $('[name="case_time"]').val(gen.case_time); // 1. การรับแจ้งเหตุ - เวลาประมาณ
                                    $('[name="police_station"]').val(gen.source_station).trigger('change'); // 1. การรับแจ้งเหตุ - สภ./สน.
                                    $('[name="investigator_name"]').val(gen.investigator.name); // 1. การรับแจ้งเหตุ - ชื่อพนักงานสอบสวน
                                    $('[name="investigator_phone"]').val(gen.investigator.phone); // 1. การรับแจ้งเหตุ - หมายเลขโทรศัพท์

                                    // 3. ช่องทางการรับแจ้ง (Notify Method)
                                    if (gen.report_channel) {
                                        gen.report_channel.forEach(val => {
                                            $(`input[name="notify_method[]"][value="${val}"]`).prop('checked', true).trigger('change'); // 1. การรับแจ้งเหตุ - ช่องทางการรับแจ้ง
                                        });
                                        if (gen.report_channel.includes('อื่นๆ')) {
                                            $('[name="notify_method_other_text"]').show().prop('disabled', false).val(gen.report_channel_other);
                                        }
                                    }

                                    $('[name="victim_know_date"]').val(gen.victim_know_date); // 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ - วันที่ผู้เสียหายทราบเหตุ/เกิดเหตุ
                                    $('[name="victim_know_time"]').val(gen.victim_know_time); // 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ - เวลาประมาณ
                                    $('[name="officer_know_date"]').val(gen.officer_know_date); // 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ - วันที่พนักงานสอบสวนทราบเหตุ
                                    $('[name="officer_know_time"]').val(gen.officer_know_time); // 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ - เวลาประมาณ
                                    $('[name="inspect_date"]').val(gen.inspect_date); // 4. วันเวลาที่ตรวจเหตุ - วันที่ทำการตรวจสถานที่เกิดเหตุ
                                    $('[name="inspect_time"]').val(gen.inspect_time); // 4. วันเวลาที่ตรวจเหตุ - เวลาประมาณ

                                    // ============================================================================================================
                                    // =========================== ทำถึง inspect_time เดี๋ยวมาปรับต่อให้ข้อมูลตรงกับ saveTraffic ==========================
                                    // ============================================================================================================
                                    
                                    // 4. จัดการผู้ประสบเหตุ (Dynamic Rows)
                                    if (d.victims && d.victims.length > 0) {
                                        d.victims.forEach((v, i) => { // 2. สถานที่เกิดเหตุ - ข้อมูลผู้ประสบเหตุ
                                            addVictimCardBomb(); // สร้างแถว
                                            $('[name="victim_type_bomb[]"]').eq(i).val(v.type); // 2. สถานที่เกิดเหตุ - ประเภทผู้ประสบเหตุ
                                            $('[name="victim_name_bomb[]"]').eq(i).val(v.name); // 2. สถานที่เกิดเหตุ - ฃื่อ - นามสกุล
                                            $('[name="victim_age_bomb[]"]').eq(i).val(v.age); // 2. สถานที่เกิดเหตุ - อายุ
                                        });
                                    } else {
                                        addVictimCardBomb(); // สร้างรายการแรกว่างๆ ถ้าไม่มีข้อมูล
                                    }

                                    // --- ผู้ตรวจสถานที่ (Fieldset 5) ---
                                    if (d.inspectors && d.inspectors.length > 0) {
                                        d.inspectors.forEach((id, i) => {
                                            addInspectorRowBomb();
                                            $('.inspector-select-bomb').eq(i).val(id).trigger('change');
                                        });
                                    } else {
                                        addInspectorRowBomb(); // สร้างรายการแรกว่างๆ
                                    }

                                    // 5. ลักษณะสถานที่เกิดเหตุ (Environment)
                                    const scene = d.scene_info;
                                    $('[name="crime_location"]').val(scene.crime_location); // 2. สถานที่เกิดเหตุ - รายละเอียดสถานที่เกิดเหตุ

                                    // การรักษาสถานที่
                                    $(`input[name="scene_preserved"][value="${scene.preservation}"]`).prop('checked', true); // 6. ลักษณะสถานที่เกิดเหตุ - มี/ไม่มีการรักษาสถานที่
                                    if (scene.preservation === 'ไม่มี') {
                                        $('#bomb_scene_no_text').show().prop('disabled', false).val(scene.preservation_detail); // 6. ลักษณะสถานที่เกิดเหตุ - ไม่มีการรักษาสถานที่ (detail)
                                    }

                                    // สภาพแวดล้อม
                                    const env = scene.environment;
                                    // แสงสว่าง & อุณหภูมิ
                                    env.lighting.forEach(v => {
                                        $(`input[name="lighting[]"][value="${v}"]`).prop('checked', true); // 6. ลักษณะสถานที่เกิดเหตุ - แสงสว่าง
                                        if (v === 'อื่นๆ') $('#bomb_light_other_text').show().prop('disabled', false).val(env.lighting_other); // 6. ลักษณะสถานที่เกิดเหตุ - แสงสว่าง ( field text อื่น ๆ )
                                    });
                                    env.temperature.forEach(v => {
                                        $(`input[name="temperature[]"][value="${v}"]`).prop('checked', true); // 6. ลักษณะสถานที่เกิดเหตุ - อุณหภูมิ
                                        if (v === 'อื่นๆ') $('#bomb_temp_other_text').show().prop('disabled', false).val(env.temperature_other); // 6. ลักษณะสถานที่เกิดเหตุ - อุณหภูมิ ( field text อื่น ๆ )
                                    });

                                    // กลิ่น
                                    $(`input[name="smell"][value="${env.smell}"]`).prop('checked', true); // 6. ลักษณะสถานที่เกิดเหตุ - กลิ่น
                                    if (env.smell === 'มี') {
                                        $('#bomb_smell_yes_text').show().prop('disabled', false).val(env.smell_detail); // 6. ลักษณะสถานที่เกิดเหตุ - มีกลิ่น ( field text )
                                    } else if (env.smell === 'ไม่มี') {
                                        $('#bomb_smell_no_text').show().prop('disabled', false).val(env.smell_detail); // 6. ลักษณะสถานที่เกิดเหตุ - ไม่มีกลิ่น ( field text )
                                    }

                                    // 7. ภายใน / ภายนอก (Outdoor / Indoor)
                                    if (scene.outdoor.is_active) {
                                        $('#check_outdoor_main').prop('checked', true).trigger('change'); // 6. ลักษณะสถานที่เกิดเหตุ - เลือก เกิดเหตุภายนอกอาคาร
                                        const out = scene.outdoor;
                                        out.type.forEach(t => {
                                            $(`input[name="outdoor_type[]"][value="${t}"]`).prop('checked', true).trigger('change'); // 6. ลักษณะสถานที่เกิดเหตุ - เลือก เกิดเหตุภายนอกอาคาร ( ลักษณะพืันที่ ) 
                                        });
                                        $('[name="outdoor_type_other_text"]').val(out.type_other); // 6. ลักษณะสถานที่เกิดเหตุ - เลือก เกิดเหตุภายนอกอาคาร ( ลักษณะพืันที่ field text สำหรับอื่น ๆ ) 
                                        $('[name="entrance_condition_outdoor"]').val(out.entrance); // 6. ลักษณะสถานที่เกิดเหตุ - เมื่อหันหน้าเข้า
                                        $('[name="front_adjacent_outdoor"]').val(out.adjacent.front); // 6. ลักษณะสถานที่เกิดเหตุ - ด้านหน้าติด
                                        $('[name="left_adjacent_outdoor"]').val(out.adjacent.left); // 6. ลักษณะสถานที่เกิดเหตุ - ด้านซ้ายติด
                                        $('[name="right_adjacent_outdoor"]').val(out.adjacent.right); // 6. ลักษณะสถานที่เกิดเหตุ - ด้านขวาติด
                                        $('[name="back_adjacent_outdoor"]').val(out.adjacent.back); // 6. ลักษณะสถานที่เกิดเหตุ - ด้านหลังติด
                                        $('[name="incident_area_detail_outdoor"]').val(out.incident_area); // 6. ลักษณะสถานที่เกิดเหตุ - บริเวณที่เกิดเหตุ
                                    } else if (scene.indoor.is_active) {
                                        $('#check_indoor_main').prop('checked', true).trigger('change'); // 6. ลักษณะสถานที่เกิดเหตุ - เลือก เกิดเหตุภายในอาคาร
                                        const ind = scene.indoor;

                                        // 1. จัดการติ๊ก Checkbox ประเภทอาคาร (วนลูปเพื่อติ๊กถูกและเปิดช่อง "ชั้น" เท่านั้น)
                                        if (ind.building_type && Array.isArray(ind.building_type)) {
                                            ind.building_type.forEach(t => {
                                                $(`input[name="building_type_indoor[]"][value="${t}"]`)
                                                    .prop('checked', true)
                                                    .trigger('change'); // trigger เพื่อให้ setupPermanentOtherToggle ปลดล็อกช่อง Text
                                            });
                                        }

                                        // 2. หยอดค่าในช่อง "อื่น ๆ" (ทำครั้งเดียวด้านนอกลูป)
                                        // ค่าจาก building_type_other จะถูกใส่ลงไปทันที
                                        $('#bld_other_text_indoor').val(ind.building_type_other);

                                        // หยอดจำนวนชั้น
                                        if (ind.building_floors) {
                                            Object.keys(ind.building_floors).forEach(key => {
                                                $(`input[name="building_floor_indoor[${key}]"]`).val(ind.building_floors[key]); // 6. ลักษณะสถานที่เกิดเหตุ - จำนวนชั้น 
                                            });
                                        }

                                        $(`input[name="surrounding_fence_indoor"][value="${ind.fence}"]`).prop('checked', true).trigger('change'); // 6. ลักษณะสถานที่เกิดเหตุ - สภาพบริเวณโดยรอบ ( มีรั้ว / ไม่มีรั้ว )
                                        $('[name="entrance_condition_indoor"]').val(ind.entrance); // 6. ลักษณะสถานที่เกิดเหตุ - ( เมื่อหันหน้าเข้าสถานที่เกิดเหตุ... )
                                        $('[name="front_adjacent_indoor"]').val(ind.adjacent.front); // 6.ลักษณะสถานที่เกิดเหตุ - ( ด้านหน้า )
                                        $('[name="left_adjacent_indoor"]').val(ind.adjacent.left); // 6.ลักษณะสถานที่เกิดเหตุ - ( ด้านซ้าย )
                                        $('[name="right_adjacent_indoor"]').val(ind.adjacent.right); // 6.ลักษณะสถานที่เกิดเหตุ - ( ด้านขวา )
                                        $('[name="back_adjacent_indoor"]').val(ind.adjacent.back); // 6.ลักษณะสถานที่เกิดเหตุ - ( ด้านหลัง )
                                        $('[name="interior_detail_indoor"]').val(ind.interior); // 6.ลักษณะสถานที่เกิดเหตุ - ( ลักษณะภายใน )
                                        $('[name="incident_area_detail_indoor"]').val(ind.incident_area); // 6.ลักษณะสถานที่เกิดเหตุ - ( บริเวณที่เกิดเหตุ )
                                        // Structure
                                        const st = ind.structure;
                                        $('[name="structure_size_indoor"]').val(st.size); // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( มีขนาดกว้าง x ยาว ประมาณ )
                                        $('[name="structure_type_indoor"]').val(st.type); // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( ลักษณะโครงสร้าง )
                                        $('[name="structure_wall_indoor"]').val(st.wall); // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( ผนัง )
                                        $('[name="structure_front_indoor"]').val(st.adjacent.front); // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( ด้านหน้า )
                                        $('[name="structure_left_indoor"]').val(st.adjacent.left); // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( ด้านซ้าย )
                                        $('[name="structure_right_indoor"]').val(st.adjacent.right); // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( ด้านขวา )
                                        $('[name="structure_back_indoor"]').val(st.adjacent.back); // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( ด้านหลัง )
                                        $('[name="structure_floor_indoor"]').val(st.floor); // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( พื้น )
                                        $('[name="structure_roof_indoor"]').val(st.roof); // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( หลังคา )
                                        $('[name="structure_arrangement_indoor"]').val(st.arrangement); // 6.ลักษณะสถานที่เกิดเหตุ - โครงสร้างบริเวณที่เกิดเหตุ ( การจัดวางสิ่งของ )
                                    }

                                    // 8. ผลการตรวจเหตุ (Behavior & Damage)
                                    const resData = d.inspection_results;
                                    $('[name="case_behavior"]').val(resData.case_behavior); // 7.ผลการตรวจสถานที่เกิดเหตุ - พฤติการณ์คดี
                                    $('[name="damage_details"]').val(resData.damage_details); // 7.ผลการตรวจสถานที่เกิดเหตุ - ความเสียหาย
                                    $('[name="explosion_point"]').val(resData.explosion_point); // 7.ผลการตรวจสถานที่เกิดเหตุ - ตำแหน่งที่เกิดการระเบิด

                                    // ข้อมูลศพ (Dynamic)
                                    if (resData.bodies && resData.bodies.length > 0) {
                                        resData.bodies.forEach((b, i) => {
                                            addBodyCardBomb(); // สร้าง Card ใหม่ (จะได้ index ถัดไปเรื่อยๆ)

                                            // เข้าถึง Card ใบที่ i ที่เพิ่งสร้าง
                                            const $card = $('#body_container_bomb .body-card-bomb').eq(i);
                                            const actualIndex = $card.attr('data-index'); // ดึงเลข index ที่ addBodyCardBomb เจนให้

                                            $card.find(`.js-body-name`).val(b.name); // 7.ผลการตรวจสถานที่เกิดเหตุ - ชื่อ - นามสกุล (ถ้าทราบ)

                                            // 1. ติ๊กสถานะ
                                            const $targetCheckbox = $card.find(`.js-body-status[value="${b.status}"]`); // 7.ผลการตรวจสถานที่เกิดเหตุ - พบ/ไม่พบศพ
                                            $targetCheckbox.prop('checked', true);

                                            const statusKey = (b.status === 'พบศพ') ? 'found' : 'notfound';
                                            toggleBodyCondition($targetCheckbox.get(0), statusKey);

                                            // 2. ปลดล็อกและ Fill ค่าตามสถานะจริง
                                            if (b.status === 'พบศพ') {
                                                $card.find(`.js-body-condition-area`).prop('disabled', false).val(b.condition_detail); // 7.ผลการตรวจสถานที่เกิดเหตุ - สภาพศพ ลักษณะการแต่งกาย ทรัพย์สิน และอื่น ๆ
                                                $card.find(`.js-body-notfound-text`).hide().prop('disabled', true).val('');
                                            } else {
                                                $card.find(`.js-body-notfound-text`).show().prop('disabled', false).val(b.notfound_detail); // 7.ผลการตรวจสถานที่เกิดเหตุ - ไม่พบศพ detail
                                                $card.find(`.js-body-condition-area`).prop('disabled', true).val('');
                                            }
                                        });
                                    }

                                    // 9. วัตถุพยานระเบิด (Containers, Detonation, Fragments, Components)
                                    const bomb = d.inspection_results.bomb_evidence;
                                    if (bomb.is_active) $('#bomb_evidence_main').prop('checked', true).trigger('change'); // 7.ผลการตรวจสถานที่เกิดเหตุ - เลือก วัตถุพยานที่ตรวจพบ ( ระเบิด )

                                    // ภาชนะบรรจุ (Containers)
                                    const contMap = {
                                        steel_box: 'กล่องเหล็ก',
                                        gas_tank: 'ถังแก๊ส',
                                        fire_ext: 'ถังดับเพลิง',
                                        steel_pipe: 'ท่อเหล็ก',
                                        pvc_pipe: 'ท่อ PVC',
                                        ac_tank: 'ถังน้ำยาแอร์',
                                        std_bomb: 'ระเบิดมาตรฐาน',
                                        other: 'อื่นๆ'
                                    };
                                    Object.keys(contMap).forEach(key => {
                                        if (bomb.containers[key]) {
                                            // ลบ Prefix "cont_" ออกเพื่อให้ตรงกับค่า Value ใน HTML จริง
                                            $(`input[name="bomb_containers[]"][value="${contMap[key]}"]`).prop('checked', true);
                                            if (contMap[key] === 'อื่นๆ') $('#bomb_cont_other_text').show().prop('disabled', false).val(bomb.containers.other_text);
                                            // 7.ผลการตรวจสถานที่เกิดเหตุ - วัตถุพยานที่ตรวจพบ ( ระเบิด - ภาชนะบรรจุ - field test อื่น ๆ ) 
                                        }
                                    });

                                    // Detonation
                                    const det = bomb.detonation; // 7.ผลการตรวจสถานที่เกิดเหตุ - วัตถุพยานที่ตรวจพบ ( ระเบิด - วิธีการจุดระเบิด ) 
                                    if (det.trap) $('#bomb_detonate_trap').prop('checked', true).trigger('change'); // 7.ผลการตรวจสถานที่เกิดเหตุ - กับดัก
                                    $('[name="detonate_trap_detail"]').val(det.trap_detail); // 7.ผลการตรวจสถานที่เกิดเหตุ - กับดัก detail
                                    if (det.wire) $('#bomb_detonate_wire').prop('checked', true).trigger('change'); // 7.ผลการตรวจสถานที่เกิดเหตุ - ลากสายไฟ
                                    $('[name="detonate_wire_color"]').val(det.wire_color); // 7.ผลการตรวจสถานที่เกิดเหตุ - ลากสายไฟ (สี)
                                    $('[name="detonate_wire_length"]').val(det.wire_length); // 7.ผลการตรวจสถานที่เกิดเหตุ - ลากสายไฟ (ยาว)

                                    // Radio & Phone
                                    if (det.radio) $('#bomb_detonate_radio').prop('checked', true).trigger('change'); // 7.ผลการตรวจสถานที่เกิดเหตุ - วิทยุสื่อสาร
                                    $('[name="detonate_radio_brand"]').val(det.radio_brand); // 7.ผลการตรวจสถานที่เกิดเหตุ - วิทยุสื่อสาร (ยี่ห้อ)
                                    $('[name="detonate_radio_model"]').val(det.radio_model); // 7.ผลการตรวจสถานที่เกิดเหตุ - วิทยุสื่อสาร (รุ่น)
                                    $('[name="detonate_radio_color"]').val(det.radio_color); // 7.ผลการตรวจสถานที่เกิดเหตุ - วิทยุสื่อสาร (สี)
                                    $('[name="detonate_radio_sn"]').val(det.radio_sn); // 7.ผลการตรวจสถานที่เกิดเหตุ - วิทยุสื่อสาร (s/n)
                                    if (det.phone) $('#bomb_detonate_phone').prop('checked', true).trigger('change'); // 7.ผลการตรวจสถานที่เกิดเหตุ - โทรศัทพ์มือถือ
                                    $('[name="detonate_phone_brand"]').val(det.phone_brand); // 7.ผลการตรวจสถานที่เกิดเหตุ - โทรศัทพ์มือถือ (ยี่ห้อ)
                                    $('[name="detonate_phone_model"]').val(det.phone_model); // 7.ผลการตรวจสถานที่เกิดเหตุ - โทรศัทพ์มือถือ (รุ่น)
                                    $('[name="detonate_phone_color"]').val(det.phone_color); // 7.ผลการตรวจสถานที่เกิดเหตุ - โทรศัทพ์มือถือ (สี)
                                    $('[name="detonate_phone_sn"]').val(det.phone_sn); // 7.ผลการตรวจสถานที่เกิดเหตุ - โทรศัทพ์มือถือ (s/n)

                                    if (det.remote) $('#bomb_detonate_remote').prop('checked', true).trigger('change'); // 7.ผลการตรวจสถานที่เกิดเหตุ - รีโมทคอนโทรล  
                                    $('[name="detonate_remote_detail"]').val(det.remote_detail); // 7.ผลการตรวจสถานที่เกิดเหตุ - รีโมทคอนโทรล detail
                                    if (det.timer) $('#bomb_detonate_timer').prop('checked', true).trigger('change'); // 7.ผลการตรวจสถานที่เกิดเหตุ - ตั้งเวลา  
                                    $('[name="detonate_timer_detail"]').val(det.timer_detail); // 7.ผลการตรวจสถานที่เกิดเหตุ - ตั้งเวลา detail
                                    if (det.other) $('#bomb_detonate_other').prop('checked', true).trigger('change'); // 7.ผลการตรวจสถานที่เกิดเหตุ - อื่น ๆ   
                                    $('[name="detonate_other_detail"]').val(det.other_detail); // 7.ผลการตรวจสถานที่เกิดเหตุ - อื่น ๆ detail

                                    // สะเก็ดระเบิด (Fragments)
                                    const frag = bomb.fragments;
                                    if (frag.rebar) $('#bomb_fragment_rebar').prop('checked', true).trigger('change'); // เหล็กเส้นตัดท่อน
                                    $('[name="fragment_rebar_size"]').val(frag.rebar_size); // เหล็กเส้นตัดท่อน - ขนาด 
                                    $('[name="fragment_rebar_length"]').val(frag.rebar_length); // เหล็กเส้นตัดท่อน - ยาว
                                    if (frag.nail) $('#bomb_fragment_nail').prop('checked', true).trigger('change'); // ตะปู
                                    $('[name="fragment_nail_size"]').val(frag.nail_size); // ตะปู - ขนาด
                                    if (frag.other) $('#bomb_fragment_other').prop('checked', true).trigger('change'); // อื่น ๆ
                                    $('[name="fragment_other_detail"]').val(frag.other_detail); // อื่น ๆ - detail 

                                    // ส่วนประกอบ (Components)
                                    Object.keys(bomb.components).forEach(key => {
                                        // เช็ค checkbox หลัก
                                        if (bomb.components[key] === true) {
                                            // กรณีพิเศษสำหรับ checkbox ชื่อ "comp_misc_other"
                                            let selector = (key === 'other') ? 'input[name="comp_misc_other"]' : `input[name="comp_${key}"]`;
                                            $(selector).prop('checked', true).trigger('change');
                                        }
                                        // หยอด Text Detail
                                        if (key === 'battery') $('[name="comp_battery_detail"]').val(bomb.components.battery_detail);
                                        else if (key === 'other') $('[name="comp_misc_other_detail"]').val(bomb.components.other_detail);
                                        else $(`[name="comp_${key}_detail"]`).val(bomb.components[key + '_detail']);
                                    });
                                    $('[name="comp_battery_voltage"]').val(bomb.components.battery_v);

                                    // คราบเลือด
                                    const blood = resData.blood_evidence;
                                    if (blood.is_active) $('#bomb_evidence_blood').prop('checked', true).trigger('change'); // เลือก/ไม่เลือก คราบสีแดงคล้ายโลหิต
                                    $('[name="blood_stain_detail"]').val(blood.detail); // detail คราบสีแดงคล้ายโลหิต
                                    if (blood.test_hemastix) $('#bomb_test_hemastix').prop('checked', true).trigger('change'); // เก็บสถานะว่าเลือกทดสอบด้วย hemastix
                                    $(`input[name="hemastix_result"][value="${blood.hemastix_result}"]`).prop('checked', true); // ผลจากการทดสอบด้วย hemastix
                                    if (blood.test_phenol) $('#bomb_test_phenol').prop('checked', true).trigger('change'); // เก็บสถานะว่าเลือกทดสอบด้วย phenolphthalein
                                    $(`input[name="phenol_result"][value="${blood.phenol_result}"]`).prop('checked', true); // ผลจากการทดสอบด้วย phenolphthalein

                                    // วัตถุพยานอื่น ๆ
                                    if (resData.other_evidence.is_active) $('#evidence_other_main').prop('checked', true).trigger('change');
                                    $('[name="evidence_other_detail"]').val(resData.other_evidence.detail);

                                    // วัตถุพยานที่ตรวจเก็บ (Collected Evidence Group)
                                    const coll = resData.collected_evidence;
                                    const collMap = {
                                        has_dna: 'สารพันธุกรรม',
                                        has_fingerprint: 'ลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง',
                                        has_toolmark: 'ร่องรอยการตัด',
                                        has_explosive: 'สารระเบิด',
                                        has_comp: 'ส่วนประกอบของวัตถุระเบิด',
                                        has_other: 'อื่นๆ'
                                    };
                                    Object.keys(collMap).forEach(k => {
                                        if (coll[k]) $(`input[name="collected_evidence[]"][value="${collMap[k]}"]`).prop('checked', true).trigger('change');
                                    });
                                    $('[name="collected_dna_detail"]').val(coll.dna_detail);
                                    $('[name="fingerprint_detail"]').val(coll.fingerprint_detail);
                                    $('[name="collected_toolmark_detail"]').val(coll.toolmark_detail);
                                    $('[name="collected_explosive_detail"]').val(coll.explosive_detail);
                                    $('[name="collected_comp_detail"]').val(coll.comp_detail);
                                    $('[name="other_evidence_type"]').val(coll.other_detail);

                                    // การตรวจสอบครั้งสุดท้าย
                                    resData.final_check.forEach(v => $(`input[name="final_check[]"][value="${v}"]`).prop('checked', true));

                                    // 10. วัตถุพยานที่พบ (Table Fieldset 10)
                                    if (d.evidences_found && d.evidences_found.length > 0) {
                                        d.evidences_found.forEach((ev, i) => {
                                            addEvidenceRowBomb();
                                            const $card = $('#evidence_container_bomb .evidence-card-bomb').eq(i);
                                            $card.find('[name="evidence_item_bomb[]"]').val(ev.item || ev.detail || '');
                                            const evDist1 = ev.ref1_dist ?? (ev.level_1 === true || ev.level_1 === '1' ? '' : (ev.level_1 || ''));
                                            const evDist2 = ev.ref2_dist ?? (ev.level_2 === true || ev.level_2 === '1' ? '' : (ev.level_2 || ''));
                                            const evDist3 = ev.ref3_dist ?? (ev.level_3 === true || ev.level_3 === '1' ? '' : (ev.level_3 || ''));
                                            const evDist4 = ev.ref4_dist ?? (ev.level_4 === true || ev.level_4 === '1' ? '' : (ev.level_4 || ''));
                                            $card.find('input[name^="evidence_ref1_dist_bomb"]').val(evDist1);
                                            $card.find('input[name^="evidence_ref2_dist_bomb"]').val(evDist2);
                                            $card.find('input[name^="evidence_ref3_dist_bomb"]').val(evDist3);
                                            $card.find('input[name^="evidence_ref4_dist_bomb"]').val(evDist4);
                                            $card.find('[name="evidence_azimuth_bomb[]"]').val(ev.azimuth || '');
                                            $card.find('[name="evidence_remark_bomb[]"]').val(ev.remark || '');
                                            window.setLabUnits($card.find('[name="evidence_lab_unit_bomb[]"]'), ev.lab_unit);
                                        });
                                    } else {
                                        addEvidenceRowBomb(); // สร้างรายการแรกว่างๆ
                                    }

                                    // 11. ตารางการตรวจเก็บ (Table Fieldset 12)
                                    if (d.measurements && d.measurements.length > 0) {
                                        d.measurements.forEach((m, i) => {
                                            addMeasurementCardBomb(); // สร้าง card ลำดับที่ i
                                            const $row = $('#measurement_container_bomb .measurement-card-bomb').eq(i);

                                            // หยอดค่าโดยใช้ชื่อฟิลด์ที่ระบุ index [i]
                                            $row.find(`[name="measurement_item_bomb[${i}]"]`).val(m.item); // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - รายการวัตถุพยาน
                                            $row.find(`[name="measurement_quantity_bomb[${i}]"]`).val(m.quantity); // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - จำนวน
                                            $row.find(`[name="measurement_area_bomb[${i}]"]`).val(m.area); // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - บริเวณที่ตรวจพบ
                                            $row.find(`[name="measurement_label_number_bomb[${i}]"]`).val(m.label_no); // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - ป้ายหมายเลข
                                            $row.find(`[name="measurement_remark_bomb[${i}]"]`).val(m.remark); // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - หมายเหตุ

                                            // แก้บั๊กการ Enable: หยอดค่า checkbox และสั่ง trigger('change')
                                            if (m.packaging.plastic) {
                                                $row.find(`[name="measurement_package_plastic_check[${i}]"]`) // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การบรรจุหีบห่อ ( พลาสติก )
                                                    .prop('checked', true)
                                                    .trigger('change');
                                                $row.find(`[name="measurement_package_plastic_text[${i}]"]`).val(m.packaging.plastic_text); // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การบรรจุหีบห่อ ( พลาสติก - detail )
                                            }

                                            if (m.packaging.paper) {
                                                $row.find(`[name="measurement_package_paper_check[${i}]"]`) // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การบรรจุหีบห่อ ( กระดาษ )
                                                    .prop('checked', true)
                                                    .trigger('change');
                                                $row.find(`[name="measurement_package_paper_text[${i}]"]`).val(m.packaging.paper_text); // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การบรรจุหีบห่อ ( กระดาษ - detail )
                                            }

                                            if (m.packaging.other) {
                                                $row.find(`[name="measurement_package_other_check[${i}]"]`) // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การบรรจุหีบห่อ ( อื่น ๆ )
                                                    .prop('checked', true)
                                                    .trigger('change');
                                                $row.find(`[name="measurement_package_other_text[${i}]"]`).val(m.packaging.other_text); // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การบรรจุหีบห่อ ( อื่น ๆ - detail )
                                            }

                                            if (m.action.return) {
                                                $row.find(`[name="measurement_action_return_check[${i}]"]`) // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การดำเนินการเกี่ยวกับวัตถุพยาน - ส่งคืน พงส.
                                                    .prop('checked', true)
                                                    .trigger('change');
                                                $row.find(`[name="measurement_action_return_text[${i}]"]`).val(m.action.return_text); // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การดำเนินการเกี่ยวกับวัตถุพยาน ( ส่งคืน พงส. - detail )
                                            }

                                            if (m.action.other) {
                                                $row.find(`[name="measurement_action_other_check[${i}]"]`) // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การดำเนินการเกี่ยวกับวัตถุพยาน - อื่น ๆ
                                                    .prop('checked', true)
                                                    .trigger('change');
                                                $row.find(`[name="measurement_action_other_text[${i}]"]`).val(m.action.other_text); // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - การดำเนินการเกี่ยวกับวัตถุพยาน ( อื่น ๆ - detail )
                                            }
                                        });
                                    }

                                    // 12. Meta ข้อมูลจดบันทึก/ส่งมอบ
                                    const evMeta = d.evidence_found_meta;
                                    $('#reference_point_1_bomb').val(evMeta.ref_1); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 1
                                    $('#reference_point_2_bomb').val(evMeta.ref_2); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 2
                                    $('#reference_point_3_bomb').val(evMeta.ref_3); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 3
                                    $('#reference_point_4_bomb').val(evMeta.ref_4); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 4
                                    $('#collector_name_bomb').val(evMeta.collector); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - ผู้จดบันทึก
                                    $('#collection_datetime_bomb').val(evMeta.collect_datetime); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - วัน/เวลา

                                    // $('[name="measurement_inspection_date_bomb"]').val(d.measurement_meta.inspection_date); // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - วันที่ตรวจสอบที่เกิดเหตุ
                                    $('[name="measurement_recorder_bomb"]').val(d.measurement_meta.recorder); // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - ผู้จดบันทึก
                                    $('[name="measurement_datetime_bomb"]').val(d.measurement_meta.measurement_datetime); // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - วัน/เวลา

                                    $('[name="sketch_remark_bomb"]').val(d.sketch_meta.remark); // 9. แผนผังสังเขป - หมายเหตุ
                                    $('[name="sketch_recorder_bomb"]').val(d.sketch_meta.recorder); // 9. แผนผังสังเขป - ผู้จดบันทึก
                                    $('[name="sketch_datetime_bomb"]').val(d.sketch_meta.datetime); // 9. แผนผังสังเขป - วัน/เวลา

                                    $('[name="victim_name_bomb_diagram"]').val(d.body_diagram_meta.victim_name); // 11. แผนผังภาพแสดงตำแหน่งบาดแผล - ชื่อ-สกุล (ผู้เสียชีวิต/ผู้บาดเจ็บ)
                                    $('[name="victim_age_bomb_diagram"]').val(d.body_diagram_meta.victim_age); // 11. แผนผังภาพแสดงตำแหน่งบาดแผล - อายุ (ปี)
                                    $('[name="autopsy_doctor_bomb"]').val(d.body_diagram_meta.doctor); // 11. แผนผังภาพแสดงตำแหน่งบาดแผล - พทย์ผู้ชันสูตร
                                    // $('[name="body_diagram_remark_bomb"]').val(d.body_diagram_meta.remark); // 11. แผนผังภาพแสดงตำแหน่งบาดแผล - หมายเหตุ

                                    $('[name="photo_id_start_bomb"]').val(d.photo_records.start); // 13. บันทึกการถ่ายภาพ - รหัสภาพถ่ายที่
                                    $('[name="photo_id_end_bomb"]').val(d.photo_records.end); // 13. บันทึกการถ่ายภาพ - ถึง
                                    $('[name="photo_amount_bomb"]').val(d.photo_records.amount); // 13. บันทึกการถ่ายภาพ - จำนวนภาพ
                                    $('[name="photographer_name_bomb"]').val(d.photo_records.photographer_name); // 13. บันทึกการถ่ายภาพ - ผู้จดบันทึก
                                    $('[name="photographer_datetime_bomb"]').val(d.photo_records.photographer_datetime); // 13. บันทึกการถ่ายภาพ - วัน/เวลา

                                    $('#receiver_name_bomb').val(d.handover.receiver_id).trigger('change'); // 8. การส่งมอบสถานที่เกิดเหตุ - select - ชื่อผู้รับมอบ
                                    $('#sender_name_bomb').val(d.handover.deliverer_id).trigger('change'); // 8. การส่งมอบสถานที่เกิดเหตุ - select - ชื่อผู้ส่งมอบ
                                    $('[name="receiver_position"]').val(d.handover.receiver_pos); // 8. การส่งมอบสถานที่เกิดเหตุ - ตำแหน่งผู้รับมอบ
                                    $('[name="sender_position"]').val(d.handover.deliverer_pos); // 8. การส่งมอบสถานที่เกิดเหตุ - ตำแหน่งผู้ส่งมอบ
                                    $('[name="inspection_end_date"]').val(d.handover.inspection_end_date); // 8. การส่งมอบคืนสถานที่ - วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น ( วันที่ )
                                    $('[name="inspection_end_time"]').val(d.handover.inspection_end_time); // 8. การส่งมอบคืนสถานที่ - วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น ( เวลา )

                                    // เมื่อ Modal แสดงผลเสร็จสิ้น (กางสุดแล้ว)
                                    $('#addCheckListModalBomb').one('shown.bs.modal', function() {
                                        if (savedSignatures) {
                                            const padsToLoad = [{
                                                    key: 'scene_sketch',
                                                    id: 'scene_sketch_canvas_bomb'
                                                },
                                                {
                                                    key: 'body_diagram',
                                                    id: 'body_diagram_canvas_bomb'
                                                },
                                                {
                                                    key: 'receiver_sig',
                                                    id: 'sig-canvas-receiver-bomb'
                                                },
                                                {
                                                    key: 'sender_sig',
                                                    id: 'sig-canvas-sender-bomb'
                                                }
                                            ];

                                            padsToLoad.forEach(item => {
                                                const pad = signaturePads[item.id];
                                                const canvas = document.getElementById(item.id); // ดึง Element โดยตรง ชัวร์กว่า
                                                const base64Data = savedSignatures[item.key]?.base64;

                                                // ตรวจสอบว่ามีข้อมูลภาพ มีตัว Pad และมีตัว Canvas จริงๆ ในหน้าจอ
                                                if (base64Data && pad && canvas) {
                                                    try {
                                                        const ratio = Math.max(window.devicePixelRatio || 1, 1);

                                                        // ปรับขนาด Canvas ให้สัมพันธ์กับพื้นที่ปัจจุบัน
                                                        // ตรวจสอบ offsetWidth อีกครั้งเพื่อป้องกัน Error ถ้า Element ถูกซ่อนกะทันหัน
                                                        if (canvas.offsetWidth > 0) {
                                                            canvas.width = canvas.offsetWidth * ratio;
                                                            canvas.height = canvas.offsetHeight * ratio;
                                                            canvas.getContext("2d").scale(ratio, ratio);

                                                            pad.clear(); // ล้างขยะเดิมก่อนวาดใหม่
                                                            pad.fromDataURL(base64Data);
                                                        }
                                                    } catch (err) {
                                                        console.error("Error drawing signature for " + item.id, err);
                                                    }
                                                } else {
                                                    console.warn(`Missing data or pad for ${item.id}. Check if initialized.`);
                                                }
                                            });
                                        }
                                    });

                                    // 2. โหลดรูปภาพที่แนบ (Photos)
                                    if (d.photos && d.photos.length > 0) {
                                        // หยอดเข้า Store กลาง เพื่อให้ฟังก์ชัน Render นำไปแสดงผล
                                        attachmentStoreBomb = d.photos.map(p => ({
                                            filename: p.filename,
                                            base64: p.base64, // ใช้ base64 ที่เก็บใน JSON มาแสดงตัวอย่างได้เลย
                                            size: p.size || 'N/A',
                                            date: p.date || '-',
                                            isExisting: true // มาร์คไว้ว่าเป็นของเดิมจาก DB
                                        }));
                                        renderAttachmentStoreBomb();
                                    } else {
                                        attachmentStoreBomb = [];
                                    }
                                    renderAttachmentStoreBomb();
                                } else {
                                    // ==========================================
                                    // CASE 2: ข้อมูลใหม่ (Add New)
                                    // ==========================================
                                    $form[0].reset();
                                    $form.removeClass('was-validated');

                                    // ล้างคอนเทนเนอร์และรีเซ็ตเลข Index ตัวแปร Global ให้กลับไปที่เริ่มต้น
                                    $('#forensic_vehicle_container, #forensic_container_traffic, #inspector_container_traffic, #vehicle_container_traffic, #comparison_container').empty();

                                    // 1. ดึงข้อมูลพื้นฐานจากหน้าหลักมาใส่ (ใช้ค่าอังกฤษจาก hidden input สำหรับสร้างเอกสาร)
                                    const docNo = $('#doc_no_bomb').val() || $('#doc_no_traffic').val() || $('#receiveNoti_No_bomb').text().trim();
                                    $('[name="case_doc_bomb"]').val(docNo);

                                    const reportNo = $('#receiveNotiReportNo_bomb').text().trim();
                                    $('#receiveNotiReportNo_bomb').text(reportNo);

                                    // 2. จัดการเรื่อง วันที่ และ เวลาปัจจุบัน
                                    const now = new Date();
                                    const year = now.getFullYear();
                                    const month = String(now.getMonth() + 1).padStart(2, '0');
                                    const day = String(now.getDate()).padStart(2, '0');
                                    const hours = String(now.getHours()).padStart(2, '0');
                                    const minutes = String(now.getMinutes()).padStart(2, '0');

                                    const dateLocal = `${year}-${month}-${day}`; // รูปแบบ YYYY-MM-DD
                                    const timeLocal = `${hours}:${minutes}`; // รูปแบบ HH:mm
                                    const dateTimeLocal = `${dateLocal}T${timeLocal}`; // รูปแบบ YYYY-MM-DDTHH:mm

                                    // --- หยอดค่าวันที่ (Date) และ เวลา (Time) แยกฟิลด์ ---
                                    // 1. การรับแจ้งเหตุ
                                    $('[name="case_date"]').val(dateLocal);
                                    $('[name="case_time"]').val(timeLocal);

                                    // 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ
                                    $('[name="victim_know_date"]').val(dateLocal);
                                    $('[name="victim_know_time"]').val(timeLocal);
                                    $('[name="officer_know_date"]').val(dateLocal);
                                    $('[name="officer_know_time"]').val(timeLocal);

                                    // 4. วันเวลาที่ตรวจเหตุ
                                    $('[name="inspect_date"]').val(dateLocal);
                                    $('[name="inspect_time"]').val(timeLocal);

                                    // 8. วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น
                                    $('[name="inspection_end_date"]').val(dateLocal);
                                    $('[name="inspection_end_time"]').val(timeLocal);

                                    // --- หยอดค่าในฟิลด์ประเภท datetime-local ---
                                    // 9. แผนผังสังเขป - วัน/เวลา
                                    $('[name="sketch_datetime_bomb"]').val(dateTimeLocal);

                                    // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - วัน/เวลา
                                    $('[name="collection_datetime_bomb"]').val(dateTimeLocal);

                                    // 12. บันทึกการตรวจเก็บวัตถุพยาน - วันที่ตรวจสอบที่เกิดเหตุ และ วัน/เวลา บันทึก
                                    // $('[name="measurement_inspection_date_bomb"]').val(dateTimeLocal);
                                    $('[name="measurement_datetime_bomb"]').val(dateTimeLocal);

                                    // 12. บันทึกการตรวจเก็บวัตถุพยาน - วัน/เวลาที่บันทึก
                                    $('[name="photographer_datetime_bomb"]').val(dateTimeLocal);

                                    // 3. สร้างแถวเริ่มต้นให้ตารางที่ต้องมีอย่างน้อย 1 รายการ
                                    addVictimCardBomb();
                                    addInspectorRowBomb();
                                    addEvidenceRowBomb();
                                    addMeasurementCardBomb();
                                    addBodyCardBomb();

                                    // บังคับ Disable (ล็อก) ทุก Section โดยตรง 
                                    // ปลดติ๊ก Masters ทั้งหมดก่อน
                                    const masters = '#check_indoor_main, #check_outdoor_main, #bomb_evidence_main, #bomb_evidence_blood, #evidence_other_main';
                                    $(masters).prop('checked', false);

                                    // เรียกฟังก์ชัน Toggle "รายตัว" เพื่อสั่ง Disable ฟิลด์ข้างในแบบ Manual
                                    if (typeof toggleOutdoorSection === 'function') toggleOutdoorSection();
                                    if (typeof toggleIndoorSection === 'function') toggleIndoorSection();
                                    if (typeof toggleBombSection === 'function') toggleBombSection();
                                    if (typeof toggleBloodSection === 'function') toggleBloodSection();

                                    // สำหรับกลุ่ม .js-master-check (พวก DNA, ลายนิ้วมือ ฯลฯ)
                                    // ตัวนี้ไม่มี logic ดีดกลับ สามารถสั่ง trigger ได้เลยเพื่อให้ input รายละเอียดโดนล็อก
                                    $('.js-master-check').trigger('change');

                                    // ล้างค่าและล็อกวัตถุพยานอื่นๆ เพิ่มเติม
                                    $('[name="evidence_other_detail"]').prop('disabled', true).val('');

                                    // 5. ล้างรูปภาพใน Store
                                    attachmentStoreBomb = [];
                                    renderAttachmentStoreBomb();
                                }
                                $('#addCheckListModalBomb').modal('show');
                            }).fail(function() {
                                Swal.fire('Error', 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้', 'error');
                            });
                        }
                    </script>
            </div>
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-success" id="btn_save_traffic" onclick="prepareDataForSubmissionTraffic()">
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

<div id="customLightboxTraffic" class="d-none" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: zoom-out;" onclick="closeLightboxOutsideTraffic(event)">

    <div id="lightboxFileNameTraffic" style="position: absolute; top: 20px; left: 20px; color: white; font-size: 1.2rem; font-weight: 300; background: rgba(0,0,0,0.5); padding: 5px 15px; border-radius: 4px; pointer-events: none;"></div>

    <button type="button"
        class="btn-close-lightbox-traffic"
        onclick="closeLightboxTraffic()"
        title="ปิดหน้าต่าง">
        &times;
    </button>

    <img id="lightboxImageTraffic" src="" style="max-width: 95%; max-height: 85%; object-fit: contain; box-shadow: 0 0 30px rgba(0,0,0,0.5); cursor: default;" onclick="event.stopPropagation()">

</div>
<style>
    body.lightbox-open {
        overflow: hidden;
    }

    /* สไตล์ปุ่มปิด Lightbox */
    .btn-close-lightbox-traffic {
        position: absolute;
        top: 20px;
        right: 20px;
        background: none;
        border: none;
        color: white;
        font-size: 2.5rem;
        cursor: pointer;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        /* ทำให้พื้นหลังเป็นวงกลม */
        transition: all 0.2s ease;
        /* ให้การเปลี่ยนสีดูนุ่มนวล */
        line-height: 1;
        z-index: 10001;

        line-height: 0;
        padding-bottom: 16px;
    }

    /* เอฟเฟกต์ตอนเอาเมาส์ไปวาง (Hover) */
    .btn-close-lightbox-traffic:hover {
        background-color: rgba(255, 255, 255, 0.15);
        transform: scale(1.1)
            /* พื้นหลังขาวอ่อนๆ */
    }

    /* ล็อก Scroll หน้าหลัง */
</style>