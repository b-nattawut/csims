<?php
// ตั้ง timezone เป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// ดึงข้อมูลผู้ตรวจสำหรับ dropdown
$inspectorOptionsBomb = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
// ดึงข้อมูลผู้จดบันทึก (ใช้ fullname เป็น value)
$collectorOptionsBomb = '<option value="" selected disabled>-- เลือกผู้จดบันทึก --</option>';
if (isset($pdo)) {
    $qryInspectorBomb = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                     FROM user_profile t1 
                     LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                     ORDER BY t1.user_id DESC";
    $stmtBomb = $pdo->query($qryInspectorBomb);
    while ($rowBomb = $stmtBomb->fetch(PDO::FETCH_ASSOC)) {
        $inspectorOptionsBomb .= '<option value="' . $rowBomb['user_id'] . '">' . htmlspecialchars($rowBomb['fullname']) . '</option>';
        $collectorOptionsBomb .= '<option value="' . htmlspecialchars($rowBomb['fullname']) . '">' . htmlspecialchars($rowBomb['fullname']) . '</option>';
    }
}

// วันที่ปัจจุบัน (ไทย)
$todayDateBomb = date('Y-m-d');
$todayTimeBomb = date('H:i');
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
</style>
<!-- Modal สำหรับเรื่อง "ระเบิด" (complaints_type = '03') - ตามฟอร์ม F-CS-09 -->
<div class="modal fade" id="addCheckListModalBomb" aria-labelledby="addCheckListModalBombLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addCheckListModalBombLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>

            </div>
            <div class="modal-body bg-light p-4 position-relative">

                <form method="post" id="incidentCheckListFormBomb" action="./api/incidentCheckList/saveBomb.php" novalidate>
                    <input type="hidden" id="receiveNoti_id_bomb" name="receiveNoti_id">
                    <input type="hidden" id="doc_no_bomb" name="doc_no">
                    <input type="hidden" id="report_no_bomb" name="report_no">

                    <!-- Header เลขที่เอกสาร -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="editInfoBomb" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountBomb" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto me-3">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="receiveNoti_No_bomb"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="receiveNotiReportNo_bomb"></span>
                            </div>
                        </div>
                        <div class="bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2 flex-shrink-0">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToPdfFormBomb" style="width: 3rem; height: 1.5rem; cursor: pointer;" onchange="if(this.checked){ this.checked=false; switchToBombPdfForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToPdfFormBomb" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
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
                                <input type="text" class="form-control bg-light" id="case_doc_no_bomb" name="case_doc_bomb" readonly>
                            </div>
                            <!-- วันที่ (ดึงจากข้อมูล แต่แก้ไขได้) -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">วันที่</label>
                                <input type="date" class="form-control" id="case_date_bomb" name="case_date" value="<?= $todayDateBomb ?>">
                            </div>
                            <!-- เวลา (ให้เลือกเอง) -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" id="case_time_bomb" name="case_time" value="<?= $todayTimeBomb ?>" step="60">
                            </div>
                        </div>


                        <!-- ช่องทางการรับแจ้ง -->
                        <div class="mb-3 mt-2">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">การรับแจ้ง</h6>

                            <label class="form-label">
                                ช่องทางการรับแจ้ง <span class="text-danger">*</span> <small class="fw-normal text-muted">(เลือกได้หลายรายการ)</small>
                            </label>

                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="d-flex flex-wrap align-items-center gap-4">

                                    <div class="form-check mb-0">
                                        <input class="form-check-input cursor-pointer" type="checkbox" name="notify_method[]" value="ทางโทรศัพท์" id="bomb_notify_phone" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_notify_phone">ทางโทรศัพท์</label>
                                    </div>

                                    <div class="form-check mb-0">
                                        <input class="form-check-input cursor-pointer" type="checkbox" name="notify_method[]" value="ทางวิทยุสื่อสาร" id="bomb_notify_radio" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_notify_radio">ทางวิทยุสื่อสาร</label>
                                    </div>

                                    <div class="form-check mb-0">
                                        <input class="form-check-input cursor-pointer" type="checkbox" name="notify_method[]" value="ทางหนังสือ" id="bomb_notify_letter" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_notify_letter">ทางหนังสือ</label>
                                    </div>

                                    <div class="d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="notify_method[]" value="อื่นๆ" id="bomb_notify_other" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_notify_other">อื่นๆ</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm" name="notify_method_other_text" id="bomb_notify_other_text" placeholder="ระบุช่องทาง..." style="width: 200px; display: none;" disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" id="bomb_notify_other_hw" data-hw-targets="bomb_notify_other_text" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">สภ./สน. <span class="text-danger">*</span></label>
                                <select id="police_station_bomb" name="police_station" class="form-select">
                                    <?php
                                    $qryPoliceStation = "SELECT * FROM master_police_station ORDER BY id DESC";
                                    $stmt = $pdo->query($qryPoliceStation);
                                    ?>
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>
                                        <option value="<?php echo htmlspecialchars($row['station_name']); ?>">
                                            <?php echo htmlspecialchars($row['station_name']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="location_at" id="location_at_bomb" placeholder="...">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="location_at_bomb" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ลง</label>
                                <div class="input-group">
                                   <input type="date" class="form-control" name="record_date" id="record_date_bomb" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <h6 class="fw-bold text-primary border-bottom pb-2">ข้อมูลพนักงานสอบสวน</h6>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ชื่อพนักงานสอบสวน <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="investigator_name" id="investigator_name_bomb" placeholder="...">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="investigator_name_bomb" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
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
                                <label class="form-label">รายละเอียดสถานที่เกิดเหตุ <span class="text-danger">*</span> <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="crime_location_bomb" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" name="crime_location" id="crime_location_bomb" rows="3" placeholder="..."></textarea>
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary mb-0 flex-grow-1">ข้อมูลผู้ประสบเหตุ</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addVictimCardBomb()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มผู้ประสบเหตุ
                                </button>
                            </div>

                            <!-- Container สำหรับรายการผู้ประสบเหตุ -->
                            <div id="victim_container_bomb">
                                <!-- รายการที่ 1 (ค่าเริ่มต้น) -->
                                <div class="victim-card-bomb bg-light p-3 rounded-3 border mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="badge bg-primary">รายการที่ 1</span>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVictimCardBomb(this)">
                                            <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                        </button>
                                    </div>

                                    <div class="bg-white p-3 rounded-3 shadow-sm">
                                        <!-- ประเภทผู้ประสบเหตุ -->
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-muted">ประเภทผู้ประสบเหตุ <span class="text-danger">*</span></label>
                                            <select class="form-select" name="victim_type_bomb[]" onchange="toggleVictimFields(this)">
                                                <option value="" selected>-- เลือกประเภท --</option>
                                                <option value="ผู้เสียชีวิต">ผู้เสียชีวิต</option>
                                                <option value="ผู้บาดเจ็บ">ผู้บาดเจ็บ</option>
                                                <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                                            </select>
                                        </div>

                                        <!-- ข้อมูลรายละเอียด -->
                                        <div class="row g-3">
                                            <div class="col-md-10">
                                                <label class="form-label small text-muted">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="victim_name_bomb[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small text-muted">อายุ (ปี)</label>
                                                <input type="text" class="form-control text-center" name="victim_age_bomb[]" inputmode="numeric" pattern="[0-9]*" maxlength="3" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 3);">
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
                                <input type="date" class="form-control" name="victim_know_date" value="<?= $todayDateBomb ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="victim_know_time" value="<?= $todayTimeBomb ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่พนักงานสอบสวนทราบเหตุ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="officer_know_date" value="<?= $todayDateBomb ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="officer_know_time" value="<?= $todayTimeBomb ?>">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 4. วันเวลาที่ตรวจเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">4. วันเวลาที่ตรวจเหตุ</legend>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ทำการตรวจสถานที่เกิดเหตุ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="inspect_date1_bomb" name="inspect_date" value="<?= $todayDateBomb ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="inspect_time1_bomb" name="inspect_time" value="<?= $todayTimeBomb ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ตรวจสถานที่เกิดเหตุเพิ่มเติม <small class="text-muted">(ถ้ามี)</small></label>
                                <input type="date" class="form-control" id="inspect_date2_bomb" name="inspect_additional_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" id="inspect_time2_bomb" name="inspect_additional_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 5. ผู้ตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">5. ผู้ตรวจสถานที่เกิดเหตุ</legend>
                        <div id="inspector_container_bomb">
                            <div class="d-flex align-items-center mb-3 inspector-row-bomb">
                                <div class="text-end pe-3" style="width: 50px;">
                                    <span class="fw-bold text-secondary inspector-index-label-bomb">5.1</span>
                                </div>
                                <div class="flex-grow-1">
                                    <select class="form-select inspector-select-bomb" name="inspector_id[]">
                                        <?= $inspectorOptionsBomb ?>
                                    </select>
                                </div>
                                <div class="ms-2" style="width: 32px;">
                                    <button type="button" class="btn-remove-row remove-inspector-bomb d-none"
                                        onclick="removeInspectorRowBomb(this)" title="ลบรายการ">
                                        <i class="fas fa-times fs-5"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex mt-2">
                            <div style="width: 50px;"></div>
                            <div class="flex-grow-1">
                                <button type="button" class="btn btn-outline-primary border-dashed w-100 py-2" onclick="addInspectorRowBomb()">
                                    <i class="fas fa-plus-circle me-1"></i> เพิ่มผู้ตรวจสถานที่
                                </button>
                            </div>
                            <div class="ms-2" style="width: 32px;"></div>
                        </div>
                    </fieldset>

                    <!-- ==================== 6. ลักษณะสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">6. ลักษณะสถานที่เกิดเหตุ</legend>

                        <div class="mb-4 mt-2">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">สภาพสถานที่เกิดเหตุเมื่อไปถึง</h6>

                            <label class="form-label">
                                การรักษาสถานที่เกิดเหตุ <span class="text-danger">*</span>
                                <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small>
                            </label>

                            <div class="bg-body-tertiary p-3 rounded-3 border mt-2">
                                <div class="d-flex flex-wrap align-items-center gap-4">

                                    <div class="form-check mb-0">
                                        <input class="form-check-input bomb-radio-toggle cursor-pointer" type="checkbox" onclick="bombRadioToggle(this)" name="scene_preserved" value="มี" id="bomb_scene_yes" data-group="scene_preserved" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_scene_yes">มีการรักษาสถานที่</label>
                                    </div>

                                    <div class="d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input bomb-radio-toggle cursor-pointer" type="checkbox" onclick="bombRadioToggle(this)" name="scene_preserved" value="ไม่มี" id="bomb_scene_no" data-group="scene_preserved" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_scene_no">ไม่มีการรักษาสถานที่</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm" name="scene_preserved_no_text" id="bomb_scene_no_text" placeholder="ระบุรายละเอียด" style="width: 250px; display: none;" disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" id="bomb_scene_no_hw" data-hw-targets="bomb_scene_no_text" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="mb-4 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">สภาพแวดล้อม</h6>

                            <!-- แสงสว่าง (ที่สังเกตเห็น) -->
                            <label class="form-label">แสงสว่าง (ที่สังเกตเห็น) <small class="text-muted fw-normal ms-1">(เลือกได้หลายรายการ)</small></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3 mt-2">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input cursor-pointer" type="checkbox" name="lighting[]" value="สว่าง" id="bomb_light_bright" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_light_bright">สว่าง</label>
                                    </div>
                                    <div class="form-check mb-0">
                                        <input class="form-check-input cursor-pointer" type="checkbox" name="lighting[]" value="มืด" id="bomb_light_dark" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_light_dark">มืด</label>
                                    </div>
                                    <div class="form-check mb-0">
                                        <input class="form-check-input cursor-pointer" type="checkbox" name="lighting[]" value="เสาไฟส่องสว่าง" id="bomb_light_pole" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_light_pole">เสาไฟส่องสว่าง</label>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="lighting[]" value="อื่นๆ" id="bomb_light_other" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_light_other">อื่นๆ</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm" name="lighting_other_text" id="bomb_light_other_text" placeholder="ระบุ" style="width: 200px; display: none;" disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" id="bomb_light_other_hw" data-hw-targets="bomb_light_other_text" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>

                            <!-- อุณหภูมิ -->
                            <label class="form-label">อุณหภูมิ <small class="text-muted fw-normal ms-1">(เลือกได้หลายรายการ)</small></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3 mt-2">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input cursor-pointer" type="checkbox" name="temperature[]" value="ร้อน" id="bomb_temp_hot" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_temp_hot">ร้อน</label>
                                    </div>
                                    <div class="form-check mb-0">
                                        <input class="form-check-input cursor-pointer" type="checkbox" name="temperature[]" value="เย็น" id="bomb_temp_cold" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_temp_cold">เย็น</label>
                                    </div>
                                    <div class="form-check mb-0">
                                        <input class="form-check-input cursor-pointer" type="checkbox" name="temperature[]" value="เครื่องปรับอากาศ" id="bomb_temp_ac" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_temp_ac">เครื่องปรับอากาศ</label>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="temperature[]" value="อื่นๆ" id="bomb_temp_other" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_temp_other">อื่นๆ</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm" name="temperature_other_text" id="bomb_temp_other_text" placeholder="ระบุ" style="width: 200px; display: none;" disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" id="bomb_temp_other_hw" data-hw-targets="bomb_temp_other_text" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>

                            <!-- กลิ่น -->
                            <label class="form-label">กลิ่น <span class="text-danger">*</span> <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border mt-2">
                                <div class="d-flex flex-wrap align-items-center gap-4">

                                    <div class="d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input bomb-radio-toggle cursor-pointer" type="checkbox" onclick="bombRadioToggle(this)" name="smell" value="มี" id="bomb_smell_yes" data-group="smell" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_smell_yes">มีกลิ่น</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm" name="bomb_smell_yes_text" id="bomb_smell_yes_text" placeholder="ระบุรายละเอียด" style="width: 200px; display: none;" disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" id="bomb_smell_yes_hw" data-hw-targets="bomb_smell_yes_text" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                    </div>

                                    <div class="d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input bomb-radio-toggle cursor-pointer" type="checkbox" onclick="bombRadioToggle(this)" name="smell" value="ไม่มี" id="bomb_smell_no" data-group="smell" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_smell_no">ไม่มีกลิ่น</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm" name="bomb_smell_no_text" id="bomb_smell_no_text" placeholder="ระบุรายละเอียด" style="width: 200px; display: none;" disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" id="bomb_smell_no_hw" data-hw-targets="bomb_smell_no_text" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                    </div>

                                </div>
                            </div>

                        </div>

                        <!-- ลักษณะสถานที่เกิดเหตุ ( ภายใน / ภายนอกอาคาร ) -->
                        <div class="mb-4 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">ลักษณะสถานที่เกิดเหตุ</h6>

                            <label class="form-label">ประเภทสถานที่ <span class="text-danger">*</span> <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small></label>

                            <!-- กรณีเกิดเหตุภายนอกอาคาร -->

                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3 mt-2">
                                <div class="bg-white p-3 rounded-3 shadow-sm selection-card">
                                    <div class="form-check mb-3 pb-2 border-bottom">
                                        <input class="form-check-input js-master-outdoor cursor-pointer" type="checkbox"
                                            name="has_outdoor_incident" value="1" id="check_outdoor_main" style="transform: scale(1.2);">
                                        <label class="form-check-label fw-bold text-dark fs-6" for="check_outdoor_main">
                                            เกิดเหตุภายนอกอาคาร
                                        </label>
                                    </div>

                                    <div id="outdoor_fields_container" class="ms-2">

                                        <div class="ms-2">
                                            <label class="form-label small text-secondary">ลักษณะพื้นที่:</label>
                                            <div class="d-flex flex-wrap align-items-center gap-4 ms-2 mb-4">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input cursor-pointer" type="checkbox" name="outdoor_type[]" value="ถนน" id="bomb_outdoor_road">
                                                    <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_outdoor_road">ถนน</label>
                                                </div>
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input cursor-pointer" type="checkbox" name="outdoor_type[]" value="สนามหญ้า" id="bomb_outdoor_lawn">
                                                    <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_outdoor_lawn">สนามหญ้า</label>
                                                </div>
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input cursor-pointer" type="checkbox" name="outdoor_type[]" value="ในสวน" id="bomb_outdoor_garden">
                                                    <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_outdoor_garden">ในสวน</label>
                                                </div>
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input cursor-pointer" type="checkbox" name="outdoor_type[]" value="ที่ว่าง" id="bomb_outdoor_empty">
                                                    <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_outdoor_empty">ที่ว่าง</label>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check mb-0">
                                                        <input class="form-check-input cursor-pointer" type="checkbox" name="outdoor_type[]" value="อื่นๆ" id="bomb_outdoor_other">
                                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="bomb_outdoor_other">อื่นๆ</label>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm" name="outdoor_type_other_text" id="bomb_outdoor_other_text" placeholder="ระบุ" style="width: 180px; display: none;" disabled>
                                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" id="bomb_outdoor_other_hw" data-hw-targets="bomb_outdoor_other_text" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>

                                            <label class="form-label small text-secondary fw-bold border-top pt-3 w-100">
                                                สภาพแวดล้อมและบริเวณโดยรอบ (ภายนอก):
                                            </label>

                                            <div class="ms-2 mb-3">
                                                <label class="form-label small text-muted mb-1">เมื่อหันหน้าเข้าสถานที่เกิดเหตุ</label>
                                                <div class="input-group input-group-sm mb-3">
                                                <input type="text" class="form-control" name="entrance_condition_outdoor" id="entrance_condition_outdoor" placeholder="ระบุสภาพทางเข้า...">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="entrance_condition_outdoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>

                                                <div class="bg-light p-3 rounded-3 border border-light-subtle">
                                                    <div class="row g-3">
                                                        <div class="col-6 col-md-3">
                                                            <label class="form-label small text-muted mb-1">ด้านหน้าติด</label>
                                                            <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control bg-white" name="front_adjacent_outdoor" id="front_adjacent_outdoor">
                                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="front_adjacent_outdoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-3">
                                                            <label class="form-label small text-muted mb-1">ด้านซ้ายติด</label>
                                                            <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control bg-white" name="left_adjacent_outdoor" id="left_adjacent_outdoor">
                                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="left_adjacent_outdoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-3">
                                                            <label class="form-label small text-muted mb-1">ด้านขวาติด</label>
                                                            <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control bg-white" name="right_adjacent_outdoor" id="right_adjacent_outdoor">
                                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="right_adjacent_outdoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-3">
                                                            <label class="form-label small text-muted mb-1">ด้านหลังติด</label>
                                                            <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control bg-white" name="back_adjacent_outdoor" id="back_adjacent_outdoor">
                                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="back_adjacent_outdoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-4 pt-2 border-top">
                                            <label class="form-label small text-secondary fw-bold">บริเวณที่เกิดเหตุ (ภายนอกอาคาร):</label>
                                            <textarea class="form-control form-control-sm mt-1" name="incident_area_detail_outdoor" id="incident_area_detail_outdoor" rows="2" placeholder="ระบุรายละเอียดบริเวณที่เกิดเหตุ..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- กรณีเกิดเหตุภายในอาคาร -->
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3 mt-2">
                                <div class="bg-white p-3 rounded-3 shadow-sm selection-card">
                                    <div class="form-check mb-3 pb-2 border-bottom">
                                        <input class="form-check-input js-master-indoor cursor-pointer" type="checkbox"
                                            name="has_indoor_incident" value="1" id="check_indoor_main" style="transform: scale(1.2);">
                                        <label class="form-check-label fw-bold text-dark fs-6" for="check_indoor_main">
                                            เกิดเหตุภายในอาคาร
                                        </label>
                                    </div>

                                    <div id="indoor_fields_container" class="ms-2">
                                        <div class="ms-2">
                                            <div class="mb-4 mt-2">
                                                <label class="form-label small text-secondary fw-bold mb-2">
                                                    ลักษณะภายนอก <span class="text-danger">*</span> <small class="text-muted fw-normal ms-1">(เลือกได้หลายรายการ)</small>
                                                </label>

                                                <div class="bg-body-tertiary p-3 rounded-3 border">
                                                    <div class="d-flex flex-wrap align-items-center gap-3">
                                                        <?php
                                                        $buildingTypes = [
                                                            'commercial' => 'อาคารพาณิชย์',
                                                            'house'      => 'บ้านเดี่ยว',
                                                            'other'      => 'อื่น ๆ'
                                                        ];
                                                        foreach ($buildingTypes as $key => $label) {
                                                        ?>
                                                            <div class="form-check mb-0">
                                                                <input class="form-check-input js-bld-check-indoor cursor-pointer"
                                                                    type="checkbox"
                                                                    name="building_type_indoor[]"
                                                                    value="<?php echo $key; ?>"
                                                                    id="bld_<?php echo $key; ?>_indoor"
                                                                    style="transform: scale(1.1);">
                                                                <label class="form-check-label cursor-pointer small text-dark fw-bold" for="bld_<?php echo $key; ?>_indoor">
                                                                    <?php echo $label; ?>
                                                                </label>
                                                            </div>
                                                            <?php if ($key === 'other'): ?>
                                                                <input type="text" class="form-control form-control-sm"
                                                                    id="bld_other_text_indoor"
                                                                    name="building_type_other_text_indoor"
                                                                    placeholder="ระบุ..." disabled style="width:120px;">
                                                            <?php endif; ?>
                                                        <?php } ?>
                                                        <div class="d-flex align-items-center gap-1">
                                                            <span class="small text-muted">จำนวน</span>
                                                            <input type="number"
                                                                class="form-control form-control-sm text-center"
                                                                name="building_floor_count_indoor"
                                                                id="floor_count_indoor"
                                                                placeholder="-" min="1" style="width:60px;">
                                                            <span class="small text-muted">ชั้น</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <label class="form-label small text-secondary fw-bold border-top pt-3 w-100">สภาพบริเวณโดยรอบ:</label>
                                            <div class="ms-2 mb-4">
                                                <div class="d-flex gap-4 mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input bomb-radio-toggle cursor-pointer" type="checkbox" onclick="bombRadioToggle(this)" name="surrounding_fence_indoor" value="มีรั้ว" id="fence_yes_indoor" data-group="indoor_fence" style="transform: scale(1.1);">
                                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="fence_yes_indoor">มีรั้ว</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input bomb-radio-toggle cursor-pointer" type="checkbox" onclick="bombRadioToggle(this)" name="surrounding_fence_indoor" value="ไม่มีรั้ว" id="fence_no_indoor" data-group="indoor_fence" style="transform: scale(1.1);">
                                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="fence_no_indoor">ไม่มีรั้ว</label>
                                                    </div>
                                                </div>

                                                <label class="form-label small text-muted mb-1">เมื่อหันหน้าเข้าสถานที่เกิดเหตุ</label>
                                                <div class="input-group input-group-sm mb-3">
                                                <input type="text" class="form-control" name="entrance_condition_indoor" id="entrance_condition_indoor" placeholder="...">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="entrance_condition_indoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>

                                                <div class="bg-light p-3 rounded-3 border border-light-subtle">
                                                    <div class="row g-3">
                                                        <div class="col-6 col-md-3">
                                                            <label class="form-label small text-muted mb-1">ด้านหน้าติด</label>
                                                            <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control bg-white" name="front_adjacent_indoor" id="front_adjacent_indoor">
                                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="front_adjacent_indoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-3">
                                                            <label class="form-label small text-muted mb-1">ด้านซ้ายติด</label>
                                                            <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control bg-white" name="left_adjacent_indoor" id="left_adjacent_indoor">
                                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="left_adjacent_indoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-3">
                                                            <label class="form-label small text-muted mb-1">ด้านขวาติด</label>
                                                            <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control bg-white" name="right_adjacent_indoor" id="right_adjacent_indoor">
                                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="right_adjacent_indoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 col-md-3">
                                                            <label class="form-label small text-muted mb-1">ด้านหลังติด</label>
                                                            <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control bg-white" name="back_adjacent_indoor" id="back_adjacent_indoor">
                                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="back_adjacent_indoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <label class="form-label small text-secondary fw-bold border-top pt-3 w-100">ลักษณะภายใน:</label>
                                            <div class="ms-2 mb-4">
                                                <textarea class="form-control form-control-sm bg-white" name="interior_detail_indoor" id="interior_detail_indoor" rows="3" placeholder="ระบุลักษณะภายในอาคาร..."></textarea>
                                            </div>

                                            <label class="form-label small text-secondary fw-bold border-top pt-3 w-100">บริเวณที่เกิดเหตุ:</label>
                                            <div class="ms-2 mb-4">
                                                <textarea class="form-control form-control-sm bg-white" name="incident_area_detail_indoor" id="incident_area_detail_indoor" rows="2" placeholder="บริเวณที่เกิดเหตุ เกิดเหตุที่..."></textarea>
                                            </div>

                                            <label class="form-label small text-secondary fw-bold border-top pt-3 w-100">โครงสร้างบริเวณที่เกิดเหตุ:</label>
                                            <div class="ms-2">
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label small text-muted mb-1">มีขนาดกว้าง x ยาว ประมาณ</label>
                                                        <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control" name="structure_size_indoor" id="structure_size_indoor" placeholder="เช่น 4x5 เมตร">
                                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_size_indoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                    </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small text-muted mb-1">ลักษณะโครงสร้าง</label>
                                                        <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control" name="structure_type_indoor" id="structure_type_indoor">
                                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_type_indoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                    </div>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small text-muted mb-1">ผนัง</label>
                                                    <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control" name="structure_wall_indoor" id="structure_wall_indoor">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_wall_indoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                                </div>

                                                <div class="bg-light p-3 rounded-3 border border-light-subtle mb-3">
                                                    <div class="row g-3">
                                                        <div class="col-6 col-md-3"><label class="form-label small text-muted">ด้านหน้า</label><div class="input-group input-group-sm"><input type="text" class="form-control bg-white" name="structure_front_indoor" id="structure_front_indoor"><button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_front_indoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>
                                                        <div class="col-6 col-md-3"><label class="form-label small text-muted">ด้านซ้าย</label><div class="input-group input-group-sm"><input type="text" class="form-control bg-white" name="structure_left_indoor" id="structure_left_indoor"><button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_left_indoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>
                                                        <div class="col-6 col-md-3"><label class="form-label small text-muted">ด้านขวา</label><div class="input-group input-group-sm"><input type="text" class="form-control bg-white" name="structure_right_indoor" id="structure_right_indoor"><button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_right_indoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>
                                                        <div class="col-6 col-md-3"><label class="form-label small text-muted">ด้านหลัง</label><div class="input-group input-group-sm"><input type="text" class="form-control bg-white" name="structure_back_indoor" id="structure_back_indoor"><button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_back_indoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>
                                                    </div>
                                                </div>

                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label small text-muted mb-1">พื้นห้อง</label>
                                                        <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control" name="structure_floor_indoor" id="structure_floor_indoor">
                                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_floor_indoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                    </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small text-muted mb-1">หลังคา</label>
                                                        <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control" name="structure_roof_indoor" id="structure_roof_indoor">
                                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_roof_indoor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                    </div>
                                                    </div>
                                                </div>

                                                <div class="mb-2">
                                                    <label class="form-label small text-muted mb-1">การจัดวางสิ่งของ</label>
                                                    <textarea class="form-control form-control-sm bg-white" name="structure_arrangement_indoor" id="structure_arrangement_indoor" rows="3"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ==================== 7. ผลการตรวจสถานที่เกิดเหตุ ==================== -->
                            <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                                <legend class="fieldset-header">7. ผลการตรวจสถานที่เกิดเหตุ</legend>

                                <!-- พฤติการณ์คดี -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-secondary">พฤติการณ์คดี <span class="text-danger">*</span> <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="case_behavior_bomb" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                    <textarea class="form-control" name="case_behavior" id="case_behavior_bomb" rows="4" placeholder="ระบุพฤติการณ์โดยสังเขป..."></textarea>
                                </div>

                                <!-- ศพ -->
                                <div class="mb-3 mt-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="fw-bold text-primary mb-0 flex-grow-1">ข้อมูลศพ</h6>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="addBodyCardBomb()">
                                            <i class="fas fa-plus me-1"></i> เพิ่มรายการศพ
                                        </button>
                                    </div>

                                    <!-- Container สำหรับรายการศพ -->
                                    <div id="body_container_bomb">
                                        <div class="body-card-bomb bg-light p-3 rounded-3 border mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="badge bg-primary text-white js-body-index-badge">ศพที่ 1</span>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeBodyCardBomb(this)" style="display:none;">
                                                    <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                                </button>
                                            </div>

                                            <div class="bg-white p-3 rounded-3 shadow-sm">

                                                <label class="form-label small text-muted">สถานะการพบศพ <span class="text-danger">*</span></label>
                                                <div class="bg-body-tertiary p-3 rounded-3 border mb-3">
                                                    <div class="d-flex flex-wrap align-items-center gap-4">
                                                        <div class="form-check mb-0">
                                                            <input class="form-check-input cursor-pointer js-body-status" type="checkbox"
                                                                name="body_status_bomb[]" value="พบศพ" id="body_found_main"
                                                                onclick="toggleBodyCondition(this, 'found')" style="transform: scale(1.1);">
                                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="body_found_main">พบศพ</label>
                                                        </div>

                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check mb-0">
                                                                <input class="form-check-input cursor-pointer js-body-status" type="checkbox"
                                                                    name="body_status_bomb[]" value="ไม่พบศพ" id="body_notfound_main"
                                                                    onclick="toggleBodyCondition(this, 'notfound')" style="transform: scale(1.1);">
                                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="body_notfound_main">ไม่พบศพ</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm js-body-notfound-text"
                                                                name="body_notfound_detail_bomb[]" placeholder="ระบุเหตุผล"
                                                                style="width: 200px; display: none;" disabled>
                                                            <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic js-body-notfound-hw" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                                        </div>

                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small text-muted">ชื่อ - นามสกุล (ถ้าทราบ)</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control js-body-name"
                                                            name="body_name_bomb[]" placeholder="ระบุชื่อ-นามสกุล หรือสัญลักษณ์เรียกแทน" disabled>
                                                        <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small text-muted">สภาพศพ ลักษณะการแต่งกาย ทรัพย์สิน และอื่น ๆ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic ms-1" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                                    <textarea class="form-control js-body-condition-area" name="body_condition_bomb[]" rows="3" placeholder="..." disabled></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ความเสียหาย -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-secondary">ความเสียหาย (บ้านเรือน ยานพาหนะ ทรัพย์สิน) <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="damage_details_bomb" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                    <textarea class="form-control" name="damage_details" id="damage_details_bomb" rows="4"></textarea>
                                </div>

                                <!-- ตำแหน่งที่เกิดการระเบิด -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-secondary">ตำแหน่งที่เกิดการระเบิด <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="explosion_point_bomb" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                    <textarea class="form-control" name="explosion_point" id="explosion_point_bomb" rows="2"></textarea>
                                </div>


                                <!-- วัตถุพยานที่ตรวจพบ -->
                                <div class="mb-2">
                                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">วัตถุพยานที่ตรวจพบ</h6>

                                    <!-- ระเบิด ( เฉพาะของฟอร์มนี้ ) -->
                                    <div class="bg-white p-3 rounded-3 shadow-sm border selection-card mb-3">
                                        <div class="form-check mb-3 pb-2 border-bottom">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="has_bomb_evidence" value="1" id="bomb_evidence_main" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer fw-bold" for="bomb_evidence_main">ระเบิด</label>
                                        </div>

                                        <!-- ระเบิด ( ภาชนะตรวจบรรจุ ) -->
                                        <label class="form-label small text-secondary">ภาชนะบรรจุ:</label>
                                        <div class="bg-body-tertiary p-3 rounded-3 border mt-2">
                                            <div class="d-flex flex-wrap align-items-center gap-4">

                                                <div class="form-check mb-0">
                                                    <input class="form-check-input cursor-pointer" type="checkbox" name="bomb_containers[]" value="กล่องเหล็ก" id="cont_steel_box" style="transform: scale(1.1);">
                                                    <label class="form-check-label cursor-pointer ms-1 text-dark" for="cont_steel_box">กล่องเหล็ก</label>
                                                </div>

                                                <div class="form-check mb-0">
                                                    <input class="form-check-input cursor-pointer" type="checkbox" name="bomb_containers[]" value="ถังแก๊ส" id="cont_gas_tank" style="transform: scale(1.1);">
                                                    <label class="form-check-label cursor-pointer ms-1 text-dark" for="cont_gas_tank">ถังแก๊ส</label>
                                                </div>

                                                <div class="form-check mb-0">
                                                    <input class="form-check-input cursor-pointer" type="checkbox" name="bomb_containers[]" value="ถังดับเพลิง" id="cont_fire_ext" style="transform: scale(1.1);">
                                                    <label class="form-check-label cursor-pointer ms-1 text-dark" for="cont_fire_ext">ถังดับเพลิง</label>
                                                </div>

                                                <div class="form-check mb-0">
                                                    <input class="form-check-input cursor-pointer" type="checkbox" name="bomb_containers[]" value="ท่อเหล็ก" id="cont_steel_pipe" style="transform: scale(1.1);">
                                                    <label class="form-check-label cursor-pointer ms-1 text-dark" for="cont_steel_pipe">ท่อเหล็ก</label>
                                                </div>

                                                <div class="form-check mb-0">
                                                    <input class="form-check-input cursor-pointer" type="checkbox" name="bomb_containers[]" value="ท่อ PVC" id="cont_pvc_pipe" style="transform: scale(1.1);">
                                                    <label class="form-check-label cursor-pointer ms-1 text-dark" for="cont_pvc_pipe">ท่อ PVC</label>
                                                </div>

                                                <div class="form-check mb-0">
                                                    <input class="form-check-input cursor-pointer" type="checkbox" name="bomb_containers[]" value="ถังน้ำยาแอร์" id="cont_ac_tank" style="transform: scale(1.1);">
                                                    <label class="form-check-label cursor-pointer ms-1 text-dark" for="cont_ac_tank">ถังน้ำยาแอร์</label>
                                                </div>

                                                <div class="form-check mb-0">
                                                    <input class="form-check-input cursor-pointer" type="checkbox" name="bomb_containers[]" value="ระเบิดมาตรฐาน" id="cont_std_bomb" style="transform: scale(1.1);">
                                                    <label class="form-check-label cursor-pointer ms-1 text-dark" for="cont_std_bomb">ระเบิดมาตรฐาน</label>
                                                </div>

                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check mb-0">
                                                        <input class="form-check-input cursor-pointer" type="checkbox" name="bomb_containers[]" value="อื่นๆ" id="cont_other" style="transform: scale(1.1);">
                                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="cont_other">อื่นๆ</label>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm" name="bomb_container_other_text" id="bomb_cont_other_text" placeholder="ระบุภาชนะ..." style="width: 200px; display: none;" disabled>
                                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" id="bomb_cont_other_hw" data-hw-targets="bomb_cont_other_text" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                                </div>

                                            </div>
                                        </div>

                                        <!-- ระเบิด ( วิธีการจุดระเบิด ) -->
                                        <label class="form-label small text-secondary mt-3">วิธีการจุดระเบิด:</label>

                                        <div class="bg-body-tertiary p-3 rounded-3 border mb-3 mt-2">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 170px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="detonate_trap" value="1" id="bomb_detonate_trap">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_detonate_trap">
                                                                กับดัก / เหยียบ / สะดุด
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="detonate_trap_detail" name="detonate_trap_detail"
                                                                    placeholder="โปรดระบุรายละเอียด" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="detonate_trap_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 140px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="detonate_wire" value="1" id="bomb_detonate_wire">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_detonate_wire">
                                                                ลากสายไฟ
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1 d-flex gap-2">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light small">สี</span>
                                                                <input type="text" class="form-control"
                                                                    id="detonate_wire_color" name="detonate_wire_color"
                                                                    placeholder="ระบุสี" disabled>
                                                            </div>
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">ยาว (ซม.)</span>
                                                                <input type="text" class="form-control"
                                                                    id="detonate_wire_length" name="detonate_wire_length"
                                                                    inputmode="decimal" placeholder="0.00"
                                                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');"
                                                                    disabled>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-12">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 140px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="detonate_radio" value="1" id="bomb_detonate_radio">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_detonate_radio">
                                                                วิทยุสื่อสาร
                                                            </label>
                                                        </div>

                                                        <div class="flex-grow-1 d-flex gap-2">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light small">ยี่ห้อ</span>
                                                                <input type="text" class="form-control"
                                                                    id="detonate_radio_brand" name="detonate_radio_brand"
                                                                    placeholder="ระบุ" disabled>
                                                            </div>

                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light small">รุ่น</span>
                                                                <input type="text" class="form-control"
                                                                    id="detonate_radio_model" name="detonate_radio_model"
                                                                    placeholder="ระบุ" disabled>
                                                            </div>

                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light small">สี</span>
                                                                <input type="text" class="form-control"
                                                                    id="detonate_radio_color" name="detonate_radio_color"
                                                                    placeholder="ระบุ" disabled>
                                                            </div>

                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light small">s/n</span>
                                                                <input type="text" class="form-control"
                                                                    id="detonate_radio_sn" name="detonate_radio_sn"
                                                                    placeholder="ระบุ" disabled>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-12">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 140px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="detonate_phone" value="1" id="bomb_detonate_phone">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_detonate_phone">
                                                                โทรศัพท์มือถือ
                                                            </label>
                                                        </div>

                                                        <div class="flex-grow-1 d-flex gap-2">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light small">ยี่ห้อ</span>
                                                                <input type="text" class="form-control"
                                                                    id="detonate_phone_brand" name="detonate_phone_brand"
                                                                    placeholder="ระบุ" disabled>
                                                            </div>

                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light small">รุ่น</span>
                                                                <input type="text" class="form-control"
                                                                    id="detonate_phone_model" name="detonate_phone_model"
                                                                    placeholder="ระบุ" disabled>
                                                            </div>

                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light small">สี</span>
                                                                <input type="text" class="form-control"
                                                                    id="detonate_phone_color" name="detonate_phone_color"
                                                                    placeholder="ระบุ" disabled>
                                                            </div>

                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light small">s/n</span>
                                                                <input type="text" class="form-control"
                                                                    id="detonate_phone_sn" name="detonate_phone_sn"
                                                                    placeholder="ระบุ" disabled>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 140px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="detonate_remote" value="1" id="bomb_detonate_remote">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_detonate_remote">
                                                                รีโมทคอนโทรล
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="detonate_remote_detail" name="detonate_remote_detail"
                                                                    placeholder="โปรดระบุรายละเอียด" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="detonate_remote_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 140px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="detonate_timer" value="1" id="bomb_detonate_timer">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_detonate_timer">
                                                                ตั้งเวลา
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="detonate_timer_detail" name="detonate_timer_detail"
                                                                    placeholder="ระบุรายละเอียด (เช่น นาฬิกา, วงจรหน่วง)" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="detonate_timer_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 140px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="detonate_other" value="1" id="bomb_detonate_other">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_detonate_other">
                                                                อื่น ๆ
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="detonate_other_detail" name="detonate_other_detail"
                                                                    placeholder="โปรดระบุวิธีการจุดระเบิดอื่น ๆ" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="detonate_other_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>


                                        <!-- ระเบิด ( สะเก็ตระเบิด ) -->
                                        <label class="form-label small text-secondary mt-3">สะเก็ตระเบิด:</label>

                                        <div class="bg-body-tertiary p-3 rounded-3 border mb-3 mt-2">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="fragment_rebar" value="1" id="bomb_fragment_rebar">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_fragment_rebar">
                                                                เหล็กเส้นตัดท่อน
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1 d-flex gap-2">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light small">ขนาด (หุน)</span>
                                                                <input type="text" class="form-control"
                                                                    id="fragment_rebar_size" name="fragment_rebar_size"
                                                                    inputmode="decimal"
                                                                    placeholder="ระบุ"
                                                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');"
                                                                    disabled>
                                                            </div>

                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light small">ยาว (ซม.)</span>
                                                                <input type="text" class="form-control"
                                                                    id="fragment_rebar_length" name="fragment_rebar_length"
                                                                    inputmode="decimal"
                                                                    placeholder="ระบุ"
                                                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');"
                                                                    disabled>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="fragment_nail" value="1" id="bomb_fragment_nail">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_fragment_nail">
                                                                ตะปู
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light small">ขนาด (นิ้ว)</span>
                                                                <input type="text" class="form-control"
                                                                    id="fragment_nail_size" name="fragment_nail_size"
                                                                    inputmode="decimal"
                                                                    placeholder="ระบุ"
                                                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');"
                                                                    disabled>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="fragment_other" value="1" id="bomb_fragment_other">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_fragment_other">
                                                                อื่น ๆ
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="fragment_other_detail" name="fragment_other_detail"
                                                                    placeholder="โปรดระบุสะเก็ดระเบิดชนิดอื่น ๆ" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fragment_other_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ระเบิด ( ส่วนประกอบของวัตถุระเบิดอื่น ๆ ) -->
                                        <label class="form-label small text-secondary mt-3">ส่วนประกอบของวัตถุระเบิดอื่น ๆ:</label>

                                        <div class="bg-body-tertiary p-3 rounded-3 border mb-3 mt-2">
                                            <div class="row g-3">

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="comp_booster" value="1" id="bomb_comp_booster">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_comp_booster">
                                                                หลอดดินขยาย
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="comp_booster_detail" name="comp_booster_detail"
                                                                    placeholder="ระบุรายละเอียด (เช่น จำนวน, ขนาด)" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="comp_booster_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="comp_detonator" value="1" id="bomb_comp_detonator">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_comp_detonator">
                                                                เชื้อปะทุไฟฟ้า
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="comp_detonator_detail" name="comp_detonator_detail"
                                                                    placeholder="ระบุรายละเอียด (เช่น ยี่ห้อ, สีสายไฟ)" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="comp_detonator_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="comp_tape" value="1" id="bomb_comp_tape">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_comp_tape">
                                                                เทปพันสายไฟ
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="comp_tape_detail" name="comp_tape_detail"
                                                                    placeholder="ระบุรายละเอียด (เช่น สี, ยี่ห้อ)" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="comp_tape_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="comp_sim" value="1" id="bomb_comp_sim">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_comp_sim">
                                                                ซิมการ์ด
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="comp_sim_detail" name="comp_sim_detail"
                                                                    placeholder="ระบุรายละเอียด (เช่น ค่ายมือถือ, เลขซิม)" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="comp_sim_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="comp_circuit" value="1" id="bomb_comp_circuit">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_comp_circuit">
                                                                วงจรการจุดระเบิด
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="comp_circuit_detail" name="comp_circuit_detail"
                                                                    placeholder="ระบุประเภท (เช่น วงจรหน่วง, วงจรมาตรฐาน)" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="comp_circuit_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="comp_battery" value="1" id="bomb_comp_battery">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_comp_battery">
                                                                แบตเตอรี่
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1 d-flex gap-2">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="comp_battery_detail" name="comp_battery_detail"
                                                                    placeholder="ระบุรายละเอียด (เช่น ยี่ห้อ, ชนิด)" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="comp_battery_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>

                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light small">แรงดันไฟฟ้า (V)</span>
                                                                <input type="text" class="form-control"
                                                                    id="comp_battery_voltage" name="comp_battery_voltage"
                                                                    inputmode="decimal"
                                                                    placeholder="0.0"
                                                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');"
                                                                    disabled>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="comp_dtmf" value="1" id="bomb_comp_dtmf">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_comp_dtmf">
                                                                แผงวงจร DTMF
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="comp_dtmf_detail" name="comp_dtmf_detail"
                                                                    placeholder="ระบุรายละเอียด/ลักษณะ" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="comp_dtmf_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="comp_pcb" value="1" id="bomb_comp_pcb">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_comp_pcb">
                                                                แผงวงจร
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="comp_pcb_detail" name="comp_pcb_detail"
                                                                    placeholder="ระบุประเภท (เช่น วงจรหน่วง, วงจรมาตรฐาน)" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="comp_pcb_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="comp_wire" value="1" id="bomb_comp_wire">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_comp_wire">
                                                                สายไฟวงจร
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="comp_wire_detail" name="comp_wire_detail"
                                                                    placeholder="ระบุสี/ลักษณะสายไฟ" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="comp_wire_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="comp_box" value="1" id="bomb_comp_box">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_comp_box">
                                                                กล่องบรรจุวงจร
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="comp_box_detail" name="comp_box_detail"
                                                                    placeholder="ระบุลักษณะ/วัสดุกล่อง" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="comp_box_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="comp_clock" value="1" id="bomb_comp_clock">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_comp_clock">
                                                                นาฬิกา
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="comp_clock_detail" name="comp_clock_detail"
                                                                    placeholder="ระบุลักษณะ/ยี่ห้อ" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="comp_clock_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="comp_lever" value="1" id="bomb_comp_lever">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_comp_lever">
                                                                กระเดื่อง
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="comp_lever_detail" name="comp_lever_detail"
                                                                    placeholder="ระบุรายละเอียด" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="comp_lever_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="comp_pin" value="1" id="bomb_comp_pin">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_comp_pin">
                                                                สลักนิรภัย
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="comp_pin_detail" name="comp_pin_detail"
                                                                    placeholder="ระบุรายละเอียด" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="comp_pin_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="bg-white p-2 px-3 rounded-3 shadow-sm selection-card d-flex align-items-center gap-2 h-100">
                                                        <div class="form-check mb-0" style="min-width: 130px;">
                                                            <input class="form-check-input js-detonate-check cursor-pointer" type="checkbox"
                                                                name="comp_misc_other" value="1" id="bomb_comp_misc_other">
                                                            <label class="form-check-label cursor-pointer small fw-bold text-dark" for="bomb_comp_misc_other">
                                                                อื่น ๆ
                                                            </label>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="input-group input-group-sm">
                                                                <input type="text" class="form-control"
                                                                    id="comp_misc_other_detail" name="comp_misc_other_detail"
                                                                    placeholder="โปรดระบุส่วนประกอบอื่น ๆ" disabled>
                                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="comp_misc_other_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>


                                            </div>
                                        </div>

                                    </div>

                                    <!-- คราบสีแดงคล้ายโลหิต -->
                                    <div class="bg-white p-3 rounded-3 shadow-sm border selection-card mb-3">
                                        <div class="form-check mb-3 pb-2 border-bottom">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="evidence_blood_stain" value="1" id="bomb_evidence_blood" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer fw-bold text-dark" for="bomb_evidence_blood">
                                                คราบสีแดงคล้ายโลหิต
                                            </label>
                                        </div>

                                        <div class="ms-2">
                                            <div class="mb-3">
                                                <label class="form-label small text-secondary">รายละเอียด: <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="blood_stain_detail_bomb" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                                <textarea class="form-control form-control-sm bg-white" name="blood_stain_detail" id="blood_stain_detail_bomb" rows="3" placeholder="ระบุรายละเอียดคราบที่พบ..."></textarea>
                                            </div>

                                            <label class="form-label small text-secondary mt-2">
                                                ทดสอบด้วยชุดทดสอบคราบโลหิตเบื้องต้น:
                                            </label>

                                            <div class="bg-body-tertiary p-3 rounded-3 border mt-2">
                                                <div class="row g-3">

                                                    <div class="col-md-12">
                                                        <div class="bg-white p-2 px-3 rounded-3 shadow-sm border selection-card">
                                                            <div class="form-check mb-2 pb-2 border-bottom">
                                                                <input class="form-check-input cursor-pointer" type="checkbox" name="test_hemastix" value="1" id="bomb_test_hemastix">
                                                                <label class="form-check-label cursor-pointer small fw-bold" for="bomb_test_hemastix">Hemastix</label>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-3 ps-2">
                                                                <span class="small text-muted"><i class="fas fa-search me-1"></i>ผลการทดสอบ:</span>
                                                                <div class="form-check form-check-inline mb-0">
                                                                    <input class="form-check-input bomb-radio-toggle cursor-pointer" type="checkbox" onclick="bombRadioToggle(this)" name="hemastix_result" value="มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน" id="bomb_hemastix_positive" data-group="hemastix_result">
                                                                    <label class="form-check-label cursor-pointer small" for="bomb_hemastix_positive">เกิดการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน</label>
                                                                </div>
                                                                <div class="form-check form-check-inline mb-0">
                                                                    <input class="form-check-input bomb-radio-toggle cursor-pointer" type="checkbox" onclick="bombRadioToggle(this)" name="hemastix_result" value="ไม่มีการเปลี่ยนแปลง" id="bomb_hemastix_negative" data-group="hemastix_result">
                                                                    <label class="form-check-label cursor-pointer small" for="bomb_hemastix_negative">ไม่เกิดการเปลี่ยนแปลง</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-12">
                                                        <div class="bg-white p-2 px-3 rounded-3 shadow-sm border selection-card">
                                                            <div class="form-check mb-2 pb-2 border-bottom">
                                                                <input class="form-check-input cursor-pointer" type="checkbox" name="test_phenolphthalein" value="1" id="bomb_test_phenol">
                                                                <label class="form-check-label cursor-pointer small fw-bold" for="bomb_test_phenol">Phenolphthalein</label>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-3 ps-2">
                                                                <span class="small text-muted"><i class="fas fa-search me-1"></i>ผลการทดสอบ:</span>
                                                                <div class="form-check form-check-inline mb-0">
                                                                    <input class="form-check-input bomb-radio-toggle cursor-pointer" type="checkbox" onclick="bombRadioToggle(this)" name="phenol_result" value="มีการเปลี่ยนแปลงเป็นสีชมพูในทันที" id="bomb_phenol_positive" data-group="phenol_result">
                                                                    <label class="form-check-label cursor-pointer small" for="bomb_phenol_positive">เกิดการเปลี่ยนแปลงเป็นสีชมพูในทันที</label>
                                                                </div>
                                                                <div class="form-check form-check-inline mb-0">
                                                                    <input class="form-check-input bomb-radio-toggle cursor-pointer" type="checkbox" onclick="bombRadioToggle(this)" name="phenol_result" value="ไม่มีการเปลี่ยนแปลง" id="bomb_phenol_negative" data-group="phenol_result">
                                                                    <label class="form-check-label cursor-pointer small" for="bomb_phenol_negative">ไม่เกิดการเปลี่ยนแปลง</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- วัตถุพยานอื่นๆ -->
                                    <div class="bg-white p-3 rounded-3 shadow-sm border selection-card mb-3">

                                        <div class="form-check mb-3 pb-2 border-bottom">
                                            <input class="form-check-input cursor-pointer js-master-check" type="checkbox" name="has_evidence_other" value="1" id="evidence_other_main" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer fw-bold text-dark" for="evidence_other_main">
                                                วัตถุพยานอื่นๆ
                                            </label>
                                        </div>

                                        <div class="ms-2">
                                            <label class="form-label small text-secondary mb-1">รายละเอียด: <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="evidence_other_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                            <textarea
                                                class="form-control form-control-sm bg-white"
                                                id="evidence_other_detail"
                                                name="evidence_other_detail"
                                                rows="3"
                                                placeholder="ระบุรายละเอียดวัตถุพยานอื่นๆ ที่ตรวจพบในที่เกิดเหตุ..." disabled></textarea>
                                        </div>
                                    </div>

                                    <!-- วัตถุพยานที่ตรวจเก็บ -->
                                    <div class="mb-2">
                                        <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">
                                            <i class="fas fa-box-open me-2"></i>วัตถุพยานที่ตรวจเก็บ/การดำเนินการเกี่ยวกับวัตถุพยานเพื่อส่งตรวจพิสูจน์
                                        </h6>

                                        <!-- วัตถุพยานประเภทสารพันธุกรรม -->
                                        <div class="bg-white p-3 rounded-3 shadow-sm border selection-card mb-3">
                                            <div class="form-check mb-3 pb-2 border-bottom">
                                                <input class="form-check-input cursor-pointer js-master-check" type="checkbox" name="collected_evidence[]" value="สารพันธุกรรม" id="bomb_collect_dna" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer fw-bold" for="bomb_collect_dna">วัตถุพยานประเภทสารพันธุกรรม</label>
                                            </div>
                                            <div>
                                                <label class="form-label small text-muted mb-1">รายละเอียด</label>
                                                <textarea class="form-control bg-light" name="collected_dna_detail" id="collected_dna_detail_bomb" rows="2" placeholder="..."></textarea>
                                            </div>
                                        </div>

                                        <!-- วัตถุพยานประเภทลายนิ้วมือ -->
                                        <div class="bg-white p-3 rounded-3 shadow-sm border selection-card mb-3">
                                            <div class="form-check mb-3 pb-2 border-bottom">
                                                <input class="form-check-input cursor-pointer js-master-check" type="checkbox" name="collected_evidence[]" value="ลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง" id="bomb_evidence_fingerprint" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer fw-bold" for="bomb_evidence_fingerprint">วัตถุพยานประเภทลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง</label>
                                            </div>
                                            <div>
                                                <label class="form-label small text-muted mb-1">รายละเอียด</label>
                                                <textarea class="form-control bg-light" name="fingerprint_detail" id="fingerprint_detail_bomb" rows="2" placeholder="..."></textarea>
                                            </div>
                                        </div>

                                        <!-- วัตถุพยานประเภทร่องรอยการตัด (ToolMarks) -->
                                        <div class="bg-white p-3 rounded-3 shadow-sm border selection-card mb-3">
                                            <div class="form-check mb-3 pb-2 border-bottom">
                                                <input class="form-check-input cursor-pointer js-master-check" type="checkbox"
                                                    name="collected_evidence[]" value="ร่องรอยการตัด" id="bomb_collect_toolmark" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer fw-bold" for="bomb_collect_toolmark">
                                                    วัตถุพยานประเภทร่องรอยการตัด (ToolMarks)
                                                </label>
                                            </div>
                                            <div>
                                                <label class="form-label small text-muted mb-1">รายละเอียด</label>
                                                <textarea class="form-control bg-light" name="collected_toolmark_detail" id="collected_toolmark_detail_bomb" rows="2" placeholder="..."></textarea>
                                            </div>
                                        </div>

                                        <!-- วัตถุพยานประเภทสารระเบิด -->
                                        <div class="bg-white p-3 rounded-3 shadow-sm border selection-card mb-3">
                                            <div class="form-check mb-3 pb-2 border-bottom">
                                                <input class="form-check-input cursor-pointer js-master-check" type="checkbox"
                                                    name="collected_evidence[]" value="สารระเบิด" id="bomb_collect_explosive" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer fw-bold" for="bomb_collect_explosive">
                                                    วัตถุพยานประเภทสารระเบิด
                                                </label>
                                            </div>
                                            <div>
                                                <label class="form-label small text-muted mb-1">รายละเอียด</label>
                                                <textarea class="form-control bg-light" name="collected_explosive_detail" id="collected_explosive_detail_bomb" rows="2" placeholder="..."></textarea>
                                            </div>
                                        </div>

                                        <!-- วัตถุพยานประเภทส่วนประกอบของวัตถุระเบิด -->
                                        <div class="bg-white p-3 rounded-3 shadow-sm border selection-card mb-3">
                                            <div class="form-check mb-3 pb-2 border-bottom">
                                                <input class="form-check-input cursor-pointer js-master-check" type="checkbox"
                                                    name="collected_evidence[]" value="ส่วนประกอบของวัตถุระเบิด" id="bomb_collect_comp" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer fw-bold" for="bomb_collect_comp">
                                                    วัตถุพยานประเภทส่วนประกอบของวัตถุระเบิด
                                                </label>
                                            </div>
                                            <div>
                                                <label class="form-label small text-muted mb-1">รายละเอียด</label>
                                                <textarea class="form-control bg-light" name="collected_comp_detail" id="collected_comp_detail_bomb" rows="2" placeholder="ระบุชิ้นส่วน/ส่วนประกอบที่พบ..."></textarea>
                                            </div>
                                        </div>

                                        <!-- วัตถุพยานประเภทอื่นๆ -->
                                        <div class="bg-white p-3 rounded-3 shadow-sm border selection-card mb-3">
                                            <div class="form-check mb-3 pb-2 border-bottom">
                                                <input class="form-check-input cursor-pointer js-master-check" type="checkbox" name="collected_evidence[]" value="อื่นๆ" id="bomb_evidence_other_type" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer fw-bold" for="bomb_evidence_other_type">วัตถุพยานประเภทอื่นๆ</label>
                                            </div>
                                            <div>
                                                <label class="form-label small text-muted mb-1">รายละเอียด</label>
                                                <textarea class="form-control bg-light" name="other_evidence_type" id="other_evidence_type_bomb" rows="2" placeholder="..."></textarea>
                                            </div>
                                        </div>

                                    </div>

                                    <!-- การตรวจสอบครั้งสุดท้าย -->
                                    <div class="mb-2">
                                        <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">การตรวจสอบครั้งสุดท้าย</h6>

                                        <div class="bg-body-tertiary p-3 rounded-3 border">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="final_check[]" value="การตรวจสอบครั้งสุดท้าย" id="bomb_final_check" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer text-dark" for="bomb_final_check">การตรวจสอบครั้งสุดท้าย</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="final_check[]" value="ตรวจเก็บวัตถุพยานครบถ้วน" id="bomb_evidence_complete" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer text-dark" for="bomb_evidence_complete">ตรวจเก็บวัตถุพยานครบถ้วน</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="final_check[]" value="ถ่ายภาพสถานที่เกิดเหตุและดำเนินการส่งมอบสถานที่เกิดเหตุให้แก่พนักงานสอบสวน" id="bomb_photo_handover" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer text-dark" for="bomb_photo_handover">ถ่ายภาพสถานที่เกิดเหตุ และดำเนินการส่งมอบสถานที่เกิดเหตุให้แก่พนักงานสอบสวน</label>
                                            </div>
                                        </div>
                                    </div>
                            </fieldset>

                            <!-- 8. การส่งมอบคืนสถานที่ -->
                            <fieldset class="p-4 bg-white rounded-4 shadow-sm border mb-4">
                                <legend class="fieldset-header">8. การส่งมอบคืนสถานที่</legend>

                                <!-- วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น -->
                                <div class="mb-4">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">วันที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น</label>
                                            <input type="date" class="form-control" name="inspection_end_date" value="<?php echo $todayDateBomb; ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">เวลาประมาณ</label>
                                            <input type="time" class="form-control" name="inspection_end_time" value="<?php echo $todayTimeBomb; ?>">
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
                                                    <select class="form-select user-select-box-bomb" id="receiver_name_bomb" name="receiver_name" data-pos-target="#receiver_position_bomb">
                                                        <?php echo str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้รับมอบ --', $inspectorOptionsBomb); ?>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label small text-muted">ตำแหน่ง</label>
                                                    <input type="text" class="form-control bg-light" id="receiver_position_bomb" name="receiver_position" readonly>
                                                </div>
                                            </div>

                                            <div class="mb-2">
                                                <label class="form-label small text-muted">ลายเซ็นผู้รับมอบ</label>
                                                <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden w-100" style="height: 180px;">
                                                    <canvas id="sig-canvas-receiver-bomb" class="signature-pad w-100 h-100"></canvas>
                                                    <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none">
                                                        <i class="fas fa-signature fa-2x mb-1 d-block text-center"></i> เซ็นชื่อที่นี่
                                                    </div>
                                                </div>
                                                <div class="text-end mt-2">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('sig-canvas-receiver-bomb')">
                                                        <i class="fas fa-eraser me-1"></i> ล้าง
                                                    </button>
                                                </div>
                                                <input type="hidden" name="receiver_signature_data_bomb" id="receiver_signature_data_bomb">
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
                                                    <select class="form-select user-select-box-bomb" id="sender_name_bomb" name="sender_name" data-pos-target="#sender_position_bomb">
                                                        <?php echo str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งมอบ --', $inspectorOptionsBomb); ?>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label small text-muted">ตำแหน่ง</label>
                                                    <input type="text" class="form-control bg-light" id="sender_position_bomb" name="sender_position" readonly>
                                                </div>
                                            </div>

                                            <div class="mb-2">
                                                <label class="form-label small text-muted">ลายเซ็นผู้ส่งมอบ</label>
                                                <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden w-100" style="height: 180px;">
                                                    <canvas id="sig-canvas-sender-bomb" class="signature-pad w-100 h-100"></canvas>
                                                    <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none">
                                                        <i class="fas fa-signature fa-2x mb-1 d-block text-center"></i> เซ็นชื่อที่นี่
                                                    </div>
                                                </div>
                                                <div class="text-end mt-2">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('sig-canvas-sender-bomb')">
                                                        <i class="fas fa-eraser me-1"></i> ล้าง
                                                    </button>
                                                </div>
                                                <input type="hidden" name="sender_signature_data_bomb" id="sender_signature_data_bomb">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <!-- fieldset 9. แผนผังสังเขป -->
                            <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                                <legend class="fieldset-header">9. แผนผังสังเขป</legend>

                                <div class="row">
                                    <div class="col-md-12 mb-3">

                                        <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden" style="height: 700px;">
                                            <canvas id="scene_sketch_canvas_bomb" class="signature-pad w-100 h-100"></canvas>

                                            <div id="sketch_placeholder_bomb" class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none user-select-none">
                                                <div class="text-center">
                                                    <i class="fas fa-pencil-alt fa-3x mb-2"></i>
                                                    <div>วาดแผนผังที่นี่</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <div></div>
                                            <div class="d-flex gap-2 align-items-center">
                                                <button type="button" class="btn btn-sm btn-outline-warning px-3" onclick="sketchUndo('scene_sketch_canvas_bomb')">
                                                    <i class="fas fa-undo me-1"></i> ย้อนกลับ
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-warning px-3 btn-sketch-eraser" id="scene_sketch_canvas_bomb_eraser_btn" onclick="sketchToggleEraser('scene_sketch_canvas_bomb')">
                                                    <i class="fas fa-eraser me-1"></i> ยางลบ
                                                </button>
                                                <div class="d-flex align-items-center gap-1">
                                                    <i class="fas fa-pen" style="font-size:10px;color:#666;"></i>
                                                    <input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize('scene_sketch_canvas_bomb',this.value);this.nextElementSibling.textContent=this.value+'px'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">
                                                    <span style="font-size:11px;color:#666;min-width:30px;">2px</span>
                                                </div>
                                                <label class="btn btn-sm btn-outline-primary px-2 mb-0" title="เลือกสี" style="cursor:pointer;">
                                                    <i class="fas fa-palette me-1"></i>
                                                    <input type="color" value="#000000" onchange="sketchSetColor('scene_sketch_canvas_bomb',this.value)" style="width:0;height:0;padding:0;border:0;visibility:hidden;position:absolute;">
                                                </label>
                                                <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('scene_sketch_canvas_bomb')">
                                                    <i class="fas fa-trash-can me-1"></i> ล้างกระดาน
                                                </button>
                                            </div>
                                        </div>

                                        <input type="hidden" name="scene_sketch_data_bomb" id="scene_sketch_data_bomb">
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label for="sketch_remark_bomb" class="form-label">หมายเหตุ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="sketch_remark_bomb" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                        <textarea class="form-control" id="sketch_remark_bomb" name="sketch_remark_bomb" rows="4"></textarea>
                                    </div>
                                </div>

                                <!-- ผู้วัดบันทึก และ วัน/เวลา -->
                                <div class="row g-3 mt-2">
                                    <div class="col-md-6">
                                        <label for="sketch_recorder_bomb" class="form-label">ผู้จดบันทึก</label>
                                        <select class="form-control" id="sketch_recorder_bomb" name="sketch_recorder_bomb"><?php echo $collectorOptionsBomb; ?></select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="sketch_datetime_bomb" class="form-label">วัน/เวลา</label>
                                        <input type="datetime-local" class="form-control" id="sketch_datetime_bomb" name="sketch_datetime_bomb">
                                    </div>
                                </div>
                            </fieldset>

                            <!-- fieldset 10. วัตถุพยานและตำแหน่งที่ตรวจพบ -->
                            <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                                <legend class="fieldset-header">10. วัตถุพยานและตำแหน่งที่ตรวจพบ</legend>

                                <!-- รายการวัตถุพยาน -->
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="fw-bold text-primary mb-0 flex-grow-1">รายการวัตถุพยาน</h6>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="addEvidenceRowBomb()">
                                            <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                        </button>
                                    </div>

                                    <div id="evidence_container_bomb" style="overflow-x:auto;">
                                        <!-- Card แรก -->
                                        <div class="evidence-card-bomb bg-light p-3 rounded-3 border mb-3">

                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="badge bg-primary text-white js-evidence-index-badge">รายการวัตถุพยานที่ 1</span>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEvidenceCardBomb(this)" style="display:none;">
                                                    <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                                </button>
                                            </div>

                                            <div class="bg-white p-3 rounded-3 shadow-sm border">
                                                <div class="row g-3">
                                                    <!-- แถวที่ 1 -->
                                                    <div class="col-12">
                                                        <label class="form-label small text-muted">วัตถุพยาน</label>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control" name="evidence_item_bomb[]">
                                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                        </div>
                                                    </div>

                                                    <!-- แถวที่ 2 -->
                                                    <div class="col-12">
                                                        <label class="form-label small text-muted">ระยะห่าง (m) จากจุดอ้างอิง</label>
                                                        <div class="row g-2">
                                                            <div class="col-3">
                                                                <div class="input-group input-group-sm">
                                                                    <span class="input-group-text">จุดที่ 1</span>
                                                                    <input type="text" class="form-control" name="evidence_ref1_dist_bomb[]"
                                                                        inputmode="decimal" placeholder="0.00"
                                                                        oninput="this.value = this.value.replace(/[^0-9.]/g, '');">
                                                                </div>
                                                            </div>
                                                            <div class="col-3">
                                                                <div class="input-group input-group-sm">
                                                                    <span class="input-group-text">จุดที่ 2</span>
                                                                    <input type="text" class="form-control" name="evidence_ref2_dist_bomb[]"
                                                                        inputmode="decimal" placeholder="0.00"
                                                                        oninput="this.value = this.value.replace(/[^0-9.]/g, '');">
                                                                </div>
                                                            </div>
                                                            <div class="col-3">
                                                                <div class="input-group input-group-sm">
                                                                    <span class="input-group-text">จุดที่ 3</span>
                                                                    <input type="text" class="form-control" name="evidence_ref3_dist_bomb[]"
                                                                        inputmode="decimal" placeholder="0.00"
                                                                        oninput="this.value = this.value.replace(/[^0-9.]/g, '');">
                                                                </div>
                                                            </div>
                                                            <div class="col-3">
                                                                <div class="input-group input-group-sm">
                                                                    <span class="input-group-text">จุดที่ 4</span>
                                                                    <input type="text" class="form-control" name="evidence_ref4_dist_bomb[]"
                                                                        inputmode="decimal" placeholder="0.00"
                                                                        oninput="this.value = this.value.replace(/[^0-9.]/g, '');">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- แถวที่ 3 -->
                                                    <div class="col-md-4">
                                                        <label class="form-label small text-muted">Azimuth (พิกัด/องศา/ระยะ)</label>
                                                        <input type="text" class="form-control" name="evidence_azimuth_bomb[]">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small text-muted">หมายเหตุ</label>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control" name="evidence_remark_bomb[]">
                                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small text-muted">การตรวจพิสูจน์</label>
                                                        <select class="form-select lab-unit-multi" multiple size="4" title="เลือกได้มากกว่า 1 กลุ่มงาน">
                                                            <option value="">-- เลือก (เลือกได้หลายข้อ) --</option>
                                                            <option value="fingerprint">ลายนิ้วมือแฝง</option>
                                                            <option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option>
                                                            <option value="chemical">เคมีฟิสิกส์</option>
                                                            <option value="drug">ยาเสพติด</option>
                                                            <option value="gun">อาวุธปืน</option>
                                                            <option value="document">เอกสาร</option>
                                                            <option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option>
                                                            <option value="explosive">วัตถุระเบิด (กก.กตว.)</option>
                                                        </select>
                                                        <input type="hidden" class="lab-unit-value" name="evidence_lab_unit_bomb[]" value="">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- จุดอ้างอิง -->
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="reference_point_1_bomb" class="form-label">จุดอ้างอิงที่ 1</label>
                                        <div class="input-group">
                                        <input type="text" class="form-control" id="reference_point_1_bomb" name="reference_point_1_bomb">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="reference_point_1_bomb" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="reference_point_2_bomb" class="form-label">จุดอ้างอิงที่ 2</label>
                                        <div class="input-group">
                                        <input type="text" class="form-control" id="reference_point_2_bomb" name="reference_point_2_bomb">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="reference_point_2_bomb" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="reference_point_3_bomb" class="form-label">จุดอ้างอิงที่ 3</label>
                                        <div class="input-group">
                                        <input type="text" class="form-control" id="reference_point_3_bomb" name="reference_point_3_bomb">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="reference_point_3_bomb" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="reference_point_4_bomb" class="form-label">จุดอ้างอิงที่ 4</label>
                                        <div class="input-group">
                                        <input type="text" class="form-control" id="reference_point_4_bomb" name="reference_point_4_bomb">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="reference_point_4_bomb" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                    </div>
                                </div>

                                <!-- ผู้จดบันทึก และ วัน/เวลา -->
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="collector_name_bomb" class="form-label">ผู้จดบันทึก/วันที่เก็บวัตถุพยาน</label>
                                        <select class="form-control" id="collector_name_bomb" name="collector_name_bomb"><?php echo $collectorOptionsBomb; ?></select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="collection_datetime_bomb" class="form-label"></label>
                                        <input type="datetime-local" class="form-control" id="collection_datetime_bomb" name="collection_datetime_bomb">
                                    </div>
                                </div>
                            </fieldset>

                            <!-- fieldset 11. แผนผังภาพแสดงตำแหน่งบาดแผล -->
                            <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                                <legend class="fieldset-header">11. แผนผังภาพแสดงตำแหน่งบาดแผล</legend>

                                <!-- ข้อมูลผู้เสียชีวิต/ผู้บาดเจ็บ -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-5">
                                        <label for="victim_name_bomb_diagram" class="form-label">ชื่อ-สกุล (ผู้เสียชีวิต/ผู้บาดเจ็บ)</label>
                                        <div class="input-group">
                                        <input type="text" class="form-control" id="victim_name_bomb_diagram" name="victim_name_bomb_diagram">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="victim_name_bomb_diagram" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="victim_age_bomb_diagram" class="form-label">อายุ (ปี)</label>
                                        <input type="text" class="form-control" id="victim_age_bomb_diagram" name="victim_age_bomb_diagram" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                    </div>
                                    <div class="col-md-5">
                                        <label for="autopsy_doctor_bomb" class="form-label">แพทย์ผู้ชันสูตร</label>
                                        <div class="input-group">
                                        <input type="text" class="form-control" id="autopsy_doctor_bomb" name="autopsy_doctor_bomb">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="autopsy_doctor_bomb" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden" style="height: 800px;">
                                            <img src="/csims/assets/images/body_diagram.png" alt="Body Diagram" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; z-index: 1; pointer-events: none;">
                                            <canvas id="body_diagram_canvas_bomb" class="signature-pad w-100 h-100" style="position: absolute; top: 0; left: 0; z-index: 2; background: transparent;"></canvas>

                                            <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none user-select-none" style="z-index: 0;">
                                                <div class="text-center">
                                                    <i class="fas fa-pencil-alt fa-3x mb-2"></i>
                                                    <div>วาดบนแผนผังร่างกาย</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <div></div>
                                            <div class="d-flex gap-2 align-items-center">
                                                <button type="button" class="btn btn-sm btn-outline-warning px-3" onclick="sketchUndo('body_diagram_canvas_bomb')">
                                                    <i class="fas fa-undo me-1"></i> ย้อนกลับ
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-warning px-3 btn-sketch-eraser" id="body_diagram_canvas_bomb_eraser_btn" onclick="sketchToggleEraser('body_diagram_canvas_bomb')">
                                                    <i class="fas fa-eraser me-1"></i> ยางลบ
                                                </button>
                                                <div class="d-flex align-items-center gap-1">
                                                    <i class="fas fa-pen" style="font-size:10px;color:#666;"></i>
                                                    <input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize('body_diagram_canvas_bomb',this.value);this.nextElementSibling.textContent=this.value+'px'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">
                                                    <span style="font-size:11px;color:#666;min-width:30px;">2px</span>
                                                </div>
                                                <label class="btn btn-sm btn-outline-primary px-2 mb-0" title="เลือกสี" style="cursor:pointer;">
                                                    <i class="fas fa-palette me-1"></i>
                                                    <input type="color" value="#ff0000" onchange="sketchSetColor('body_diagram_canvas_bomb',this.value)" style="width:0;height:0;padding:0;border:0;visibility:hidden;position:absolute;">
                                                </label>
                                                <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('body_diagram_canvas_bomb')">
                                                    <i class="fas fa-trash-can me-1"></i> ล้างกระดาน
                                                </button>
                                            </div>
                                        </div>

                                        <input type="hidden" name="body_diagram_data_bomb" id="body_diagram_data_bomb">
                                    </div>

                                    <!-- <div class="col-md-12">
                                        <label for="body_diagram_remark_bomb" class="form-label">หมายเหตุ</label>
                                        <textarea class="form-control" id="body_diagram_remark_bomb" name="body_diagram_remark_bomb" rows="3"></textarea>
                                    </div> -->
                                </div>
                            </fieldset>

                            <!-- fieldset 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) -->
                            <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                                <legend class="fieldset-header">12. บันทึกการตรวจเก็บวัตถุพยาน</legend>

                                <!-- วันที่ตรวจสอบที่เกิดเหตุ -->
                                <!-- <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label for="measurement_inspection_date_bomb" class="form-label">วันที่ตรวจสอบที่เกิดเหตุ</label>
                                        <input type="datetime-local" class="form-control" id="measurement_inspection_date_bomb" name="measurement_inspection_date_bomb">
                                    </div>
                                </div> -->

                                <input type="hidden" id="measurement_inspection_date_bomb" name="measurement_inspection_date_bomb">

                                <div class="mb-4">
                                    <div class="d-flex justify-content-end align-items-center mb-3">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="addMeasurementCardBomb()">
                                            <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                        </button>
                                    </div>

                                    <div id="measurement_container_bomb" style="overflow-x:auto;">
                                        <!-- Card แรก -->
                                        <div class="measurement-card-bomb bg-light p-3 rounded-3 border mb-3">

                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="badge bg-primary text-white js-measurement-index-badge">รายการวัตถุพยานที่ 1</span>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMeasurementCardBomb(this)">
                                                    <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                                </button>
                                            </div>

                                            <div class="bg-white p-3 rounded-3 shadow-sm border">
                                                <div class="row g-3">
                                                    <!-- แถวที่ 1: รายการวัตถุพยาน, จำนวน -->
                                                    <div class="col-md-8">
                                                        <label class="form-label small text-muted">รายการวัตถุพยาน</label>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control" name="measurement_item_bomb[]">
                                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small text-muted">จำนวน</label>
                                                        <input type="text" class="form-control" name="measurement_quantity_bomb[]" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                                    </div>

                                                    <!-- แถวที่ 2: บริเวณที่ตรวจพบ, ป้ายหมายเลข -->
                                                    <div class="col-md-8">
                                                        <label class="form-label small text-muted">บริเวณที่ตรวจพบ</label>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control" name="measurement_area_bomb[]">
                                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small text-muted">ป้ายหมายเลข</label>
                                                        <input type="text"
                                                            class="form-control"
                                                            name="measurement_label_number_bomb[]"
                                                            inputmode="numeric"
                                                            pattern="[0-9]*"
                                                            oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                                    </div>

                                                    <!-- แถวที่ 3: การบรรจุหีบห่อ -->
                                                    <div class="col-12">
                                                        <label class="form-label small text-muted">การบรรจุหีบห่อ</label>
                                                        <div class="row g-2">

                                                            <div class="col-md-4">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="form-check mb-0">
                                                                        <input class="form-check-input js-master-check cursor-pointer" type="checkbox"
                                                                            name="measurement_package_plastic_check[0]" id="package_plastic_check_0" value="1">
                                                                        <label class="form-check-label small" for="package_plastic_check_0">พลาสติก</label>
                                                                    </div>
                                                                    <input type="text" class="form-control form-control-sm bg-light"
                                                                        name="measurement_package_plastic_text[0]" placeholder="..." disabled>
                                                                </div>
                                                            </div>

                                                            <div class="col-md-4">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="form-check mb-0">
                                                                        <input class="form-check-input js-master-check cursor-pointer" type="checkbox"
                                                                            name="measurement_package_paper_check[0]" id="package_paper_check_0" value="1">
                                                                        <label class="form-check-label small" for="package_paper_check_0">กระดาษ</label>
                                                                    </div>
                                                                    <input type="text" class="form-control form-control-sm bg-light"
                                                                        name="measurement_package_paper_text[0]" placeholder="..." disabled>
                                                                </div>
                                                            </div>

                                                            <div class="col-md-4">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="form-check mb-0">
                                                                        <input class="form-check-input js-master-check cursor-pointer" type="checkbox"
                                                                            name="measurement_package_other_check[0]" id="package_other_check_0" value="1">
                                                                        <label class="form-check-label small" for="package_other_check_0">อื่นๆ</label>
                                                                    </div>
                                                                    <input type="text" class="form-control form-control-sm bg-light"
                                                                        id="package_other_text_0"
                                                                        name="measurement_package_other_text[0]" placeholder="ระบุ..." disabled>
                                                                </div>
                                                            </div>

                                                        </div>
                                                    </div>

                                                    <!-- แถวที่ 4: การดำเนินการเกี่ยวกับวัตถุพยาน -->
                                                    <div class="col-12">
                                                        <label class="form-label small text-muted">การดำเนินการเกี่ยวกับวัตถุพยาน</label>
                                                        <div class="row g-3">

                                                            <div class="col-md-6">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="form-check mb-0" style="min-width: 100px; white-space: nowrap;">
                                                                        <input class="form-check-input js-master-check cursor-pointer" type="checkbox"
                                                                            name="measurement_action_return_check[0]" id="action_return_check_0" value="1">
                                                                        <label class="form-check-label small" for="action_return_check_0">ส่งคืนพงส.</label>
                                                                    </div>
                                                                    <input type="text" class="form-control form-control-sm flex-grow-1 bg-light"
                                                                        name="measurement_action_return_text[0]" placeholder="ระบุ..." disabled>
                                                                </div>
                                                            </div>

                                                            <div class="col-md-6">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="form-check mb-0" style="min-width: 100px; white-space: nowrap;">
                                                                        <input class="form-check-input js-master-check cursor-pointer" type="checkbox"
                                                                            name="measurement_action_other_check[0]" id="action_other_check_0" value="1">
                                                                        <label class="form-check-label small" for="action_other_check_0">อื่นๆ</label>
                                                                    </div>
                                                                    <input type="text" class="form-control form-control-sm flex-grow-1 bg-light"
                                                                        name="measurement_action_other_text[0]" placeholder="ระบุการดำเนินการอื่นๆ..." disabled>
                                                                </div>
                                                            </div>

                                                        </div>
                                                    </div>

                                                    <!-- แถวที่ 5: หมายเหตุ -->
                                                    <div class="col-12">
                                                        <label class="form-label small text-muted">หมายเหตุ</label>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control" name="measurement_remark_bomb[]">
                                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                        </div>
                                                    </div>

                                                    <!-- แถวที่ 6: การตรวจพิสูจน์ -->
                                                    <div class="col-12">
                                                        <label class="form-label small text-muted">การตรวจพิสูจน์</label>
                                                        <select class="form-select lab-unit-multi" multiple size="4" title="เลือกได้มากกว่า 1 กลุ่มงาน">
                                                            <option value="">-- กรุณาเลือก (เลือกได้หลายข้อ) --</option>
                                                            <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                                            <option value="bio_dna">กลุ่มงานตรวจชีววิทยาและดีเอ็นเอ</option>
                                                            <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                                            <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                                            <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                                            <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                                            <option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                                                            <option value="explosive">กองกำกับการเก็บกู้และตรวจสอบวัตถุระเบิด (กก.กตว.)</option>
                                                        </select>
                                                        <input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_bomb[]" value="">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ผู้จดบันทึก และ วัน/เวลา -->
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="measurement_recorder_bomb" class="form-label">ผู้จดบันทึก</label>
                                        <select class="form-control" id="measurement_recorder_bomb" name="measurement_recorder_bomb"><?php echo $collectorOptionsBomb; ?></select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="measurement_datetime_bomb" class="form-label">วัน/เวลา</label>
                                        <input type="datetime-local" class="form-control" id="measurement_datetime_bomb" name="measurement_datetime_bomb">
                                    </div>
                                </div>
                            </fieldset>

                            <!-- fieldset 13. บันทึกการถ่ายภาพ -->
                            <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                                <legend class="fieldset-header">13. บันทึกการถ่ายภาพ</legend>

                                <div class="row mb-4">
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">รหัสภาพถ่ายที่</label>
                                        <input type="text" class="form-control" name="photo_id_start_bomb" id="photo_id_start_bomb" readonly>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">ถึง</label>
                                        <input type="text" class="form-control" name="photo_id_end_bomb" id="photo_id_end_bomb" readonly>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label class="form-label">จำนวน (ภาพ)</label>
                                        <input type="text"
                                            class="form-control"
                                            name="photo_amount_bomb"
                                            id="photo_amount_bomb"
                                            readonly
                                            inputmode="numeric">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="photographer_name_bomb" class="form-label">ผู้จดบันทึก</label>
                                        <select class="form-control" id="photographer_name_bomb" name="photographer_name_bomb"><?php echo $collectorOptionsBomb; ?></select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="photographer_datetime_bomb" class="form-label">วัน/เวลา</label>
                                        <input type="datetime-local" class="form-control" id="photographer_datetime_bomb" name="photographer_datetime_bomb">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button type="button" class="btn btn-primary" id="btn_choose_file_bomb">
                                                <i class="fas fa-folder-open me-2"></i>เลือกไฟล์รูปภาพ
                                            </button>
                                            <button type="button" class="btn btn-success" id="btn_open_camera_bomb">
                                                <i class="fas fa-camera me-2"></i>เปิดกล้องถ่ายภาพ
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12">

                                        <!-- Input สำหรับเลือกไฟล์จาก Gallery -->
                                        <input type="file"
                                            id="incident_photos_bomb"
                                            name="incident_photos_bomb[]"
                                            accept="image/*"
                                            style="display: none;"
                                            multiple>

                                        <!-- Input สำหรับเปิดกล้องถ่ายภาพ -->
                                        <input type="file"
                                            id="camera_input_bomb"
                                            name="camera_photos_bomb[]"
                                            accept="image/*"
                                            capture="environment"
                                            style="display: none;"
                                            multiple>

                                        <div id="uploading_container_bomb" class="mb-4">
                                        </div>

                                        <div id="attachments_wrapper_bomb" class="d-none mt-4">
                                            <h6 class="fw-bold text-secondary mb-3">
                                                Attachments <span class="badge bg-secondary rounded-pill ms-1" id="file_count_badge_bomb">0</span>
                                            </h6>

                                            <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-3" id="attachments_grid_bomb">
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </fieldset>

                            <script>
                                const inspectorOptionsHTMLBomb = `<?php echo addslashes($inspectorOptionsBomb); ?>`;
                                // let signaturePads = {};

                                // ให้ store รูปพร้อมใช้งานเสมอ ป้องกัน ReferenceError ที่ทำให้ส่วนฟอร์มรูปพังทั้งหน้า
                                if (typeof attachmentStoreBomb === 'undefined' || !Array.isArray(window.attachmentStoreBomb)) {
                                    window.attachmentStoreBomb = [];
                                }
                                if (typeof deletedExistingPhotosBomb === 'undefined' || !Array.isArray(window.deletedExistingPhotosBomb)) {
                                    window.deletedExistingPhotosBomb = [];
                                }

                                function formatFileSize(bytes) {
                                    if (bytes === 0) return '0 Bytes';
                                    const k = 1024;
                                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                                }

                                // ฟังก์ชันส่วนกลางสำหรับตั้งค่า Select2 ให้กับ Element ที่ระบุ
                                function initSelect2Bomb($element) {
                                    const $modal = $('#addCheckListModalBomb');
                                    $element.select2({
                                        theme: 'bootstrap-5',
                                        width: '100%',
                                        placeholder: "กรุณาเลือก",
                                        allowClear: true,
                                        dropdownParent: $modal,
                                        dropdownAutoWidth: true
                                    });
                                }

                                // ฟังก์ชันช่วยสร้าง Toggle สำหรับ "อื่นๆ" (เพื่อลดความซ้ำซ้อน)
                                function setupOtherToggle(checkId, textId) {
                                    const chk = document.getElementById(checkId);
                                    const txt = document.getElementById(textId);
                                    if (chk && txt) {
                                        const hwBtn = txt.nextElementSibling;
                                        chk.addEventListener('change', function() {
                                            if (this.checked) {
                                                txt.style.display = 'block';
                                                txt.disabled = false;
                                                txt.focus();
                                                if (hwBtn && hwBtn.classList.contains('btn-hw-open')) hwBtn.style.display = '';
                                            } else {
                                                txt.style.display = 'none';
                                                txt.disabled = true;
                                                txt.value = '';
                                                if (hwBtn && hwBtn.classList.contains('btn-hw-open')) hwBtn.style.display = 'none';
                                            }
                                        });
                                    }
                                }

                                function setupPermanentOtherToggle(checkId, textId) {
                                    const chk = document.getElementById(checkId);
                                    const txt = document.getElementById(textId);

                                    if (chk && txt) {
                                        // บังคับให้แสดงผล (ลบ display: none)
                                        txt.style.display = 'block';

                                        // ตรวจสอบสถานะเริ่มต้น (กรณีโหลดหน้าครั้งแรก)
                                        txt.disabled = !chk.checked;

                                        chk.addEventListener('change', function() {
                                            if (this.checked) {
                                                txt.disabled = false;
                                                txt.focus();
                                            } else {
                                                txt.disabled = true;
                                                txt.value = ''; // ล้างข้อมูลเมื่อเอาติ๊กออก
                                            }
                                        });
                                    }
                                }

                                function initBombCanvases() {
                                    const canvasList = ['body_diagram_canvas_bomb', 'scene_sketch_canvas_bomb', 'sig-canvas-receiver-bomb', 'sig-canvas-sender-bomb'];
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

                                // --- หมวด: ภายนอกอาคาร ---
                                function toggleOutdoorSection() {
                                    const isChecked = $('#check_outdoor_main').is(':checked');
                                    const $container = $('#outdoor_fields_container');

                                    // 1. Enable/Disable ทุกอย่าง (รวม Checkbox ด้วยเพื่อให้ User ติ๊กได้)
                                    $container.find('input, textarea').prop('disabled', !isChecked);

                                    if (!isChecked) {
                                        // 2. ถ้าปิดตัวแม่: ล้างติ๊ก และ ล้างค่าช่อง Text พร้อมใส่สีเทา
                                        $container.find('input[type="checkbox"]').prop('checked', false);
                                        $container.find('input[type="text"], textarea').val('').addClass('bg-light');
                                        $('#bomb_outdoor_other_text').hide();
                                    } else {
                                        // 3. ถ้าเปิดตัวแม่: ปลดสีเทาช่องที่ควรพิมพ์ได้
                                        $container.find('input[type="text"], textarea').removeClass('bg-light');
                                        // ตรวจสอบช่อง "อื่นๆ" (Tier 2)
                                        const isOther = $('#bomb_outdoor_other').is(':checked');
                                        $('#bomb_outdoor_other_text').toggle(isOther).prop('disabled', !isOther);
                                    }
                                }

                                // --- หมวด: ภายในอาคาร ---
                                function toggleIndoorSection() {
                                    const isChecked = $('#check_indoor_main').is(':checked');
                                    const $container = $('#indoor_fields_container');

                                    // 1. Enable/Disable ทุกอย่างภายใต้ Container
                                    $container.find('input, textarea, select').prop('disabled', !isChecked);

                                    if (!isChecked) {
                                        $container.find('input[type="checkbox"]').prop('checked', false);
                                        $container.find('input[type="text"], input[type="number"], textarea').val('');
                                    } else {
                                        // 2. ถ้าเปิดตัวแม่: ให้ฟังก์ชันย่อยจัดการต่อว่าช่องไหนควรขาว/เทา
                                        syncIndoorSubFields();
                                    }
                                }

                                // --- ฟังก์ชันย่อยประเภทอาคาร ---
                                function syncIndoorSubFields() {
                                    const isMasterOn = $('#check_indoor_main').is(':checked');
                                    if (!isMasterOn) return;

                                    // จัดการเฉพาะช่อง "ระบุ..." ของ "อื่น ๆ" เท่านั้น
                                    // (ไม่ใช้ .closest('.selection-card') เพราะ building_type checkboxes ไม่ได้อยู่ใน
                                    //  card ของตัวเอง — ancestor .selection-card คือ outer card ของ indoor section ทั้งหมด
                                    //  ซึ่งทำให้ .find('input[type="text"]') ลบข้อมูลฟิลด์อื่นๆ เช่น entrance, adjacent ฯลฯ)
                                    const isOtherChecked = $('input[name="building_type_indoor[]"][value="other"]').is(':checked');
                                    const $otherText = $('#bld_other_text_indoor');
                                    $otherText.prop('disabled', !isOtherChecked);
                                    if (!isOtherChecked) $otherText.val('');
                                }

                                // --- หมวด: วัตถุพยานระเบิด ---
                                function toggleBombSection() {
                                    const $master = $('#bomb_evidence_main');
                                    const isChecked = $master.is(':checked');
                                    const $card = $master.closest('.selection-card');

                                    // จัดการ Checkbox ลูก
                                    $card.find('input[type="checkbox"]').not($master).prop('disabled', !isChecked);

                                    if (!isChecked) {
                                        $card.find('input[type="text"], input[type="number"], textarea').val('').prop('disabled', true).addClass('bg-light');
                                        $card.find('input[type="checkbox"]').not($master).prop('checked', false);
                                        $('#bomb_cont_other_text').hide();
                                        $('#bomb_cont_other_hw').hide();
                                    } else {
                                        $card.find('input[type="text"], input[type="number"], textarea').removeClass('bg-light');
                                        syncSubInputs(); // ตรวจสอบสถานะลูกๆ ต่อ
                                    }
                                }

                                // --- ฟังก์ชันควบคุมรายละเอียด (Tier 2): จัดการ Input ตาม Checkbox รายตัว ---
                                function syncSubInputs() {
                                    const isMasterOn = $('#bomb_evidence_main').is(':checked');

                                    // วนลูปเช็ค Checkbox ทุกตัวที่เป็นตัวคุมช่อง Input (วิธีการจุดระเบิด และ ภาชนะบรรจุ)
                                    $('.js-detonate-check, input[name="bomb_containers[]"]').each(function() {
                                        const isChecked = $(this).is(':checked');
                                        // ค้นหาช่อง Text/Number ที่อยู่ในกลุ่มเดียวกัน
                                        const $parent = $(this).closest('.selection-card, .col-auto, .d-flex, .col-lg-6');
                                        const $inputs = $parent.find('input[type="text"], input[type="number"]').not('#bomb_evidence_main');

                                        // เงื่อนไขการเปิดช่องกรอก: ตัวแม่ต้องเปิด AND ตัวมันเองต้องถูกติ๊ก
                                        const shouldEnable = isMasterOn && isChecked;
                                        $inputs.prop('disabled', !shouldEnable);

                                        if (!shouldEnable) {
                                            $inputs.val('');
                                        }

                                        // กรณีพิเศษ: ภาชนะบรรจุ "อื่นๆ" (จัดการ Show/Hide)
                                        if (this.id === 'cont_other') {
                                            if (shouldEnable) {
                                                $('#bomb_cont_other_text').show().prop('disabled', false);
                                                $('#bomb_cont_other_hw').show();
                                            } else {
                                                $('#bomb_cont_other_text').hide().prop('disabled', true).val('');
                                                $('#bomb_cont_other_hw').hide();
                                            }
                                        }
                                    });
                                }

                                // --- หมวด: คราบเลือด ---
                                function toggleBloodSection() {
                                    const $modal = $('#addCheckListModalBomb');
                                    const $master = $modal.find('#bomb_evidence_blood');
                                    const isChecked = $master.is(':checked');
                                    const $card = $master.closest('.selection-card');

                                    // 1. หาฟิลด์ลูกทั้งหมด (textarea และ checkbox ย่อย)
                                    const $fields = $card.find('textarea, input[type="checkbox"]').not($master);

                                    // 2. จัดการสถานะ Disabled
                                    $fields.prop('disabled', !isChecked);

                                    if (!isChecked) {
                                        // 3. ถ้าเอาติ๊กออก: ล้างค่า, ใส่สีเทา, และ Uncheck ตัวลูกทั้งหมด
                                        $card.find('textarea').val('');
                                        $fields.prop('checked', false);
                                        syncBloodSubTests(); // เพื่อให้ไปล้าง Radios ผลทดสอบด้วย
                                    } else {
                                        // 4. ถ้าติ๊กเลือก: Focus ที่ช่องรายละเอียด
                                        $card.find('textarea').focus();
                                        syncBloodSubTests();
                                    }
                                }

                                function syncBloodSubTests() {
                                    const $modal = $('#addCheckListModalBomb');
                                    const masterOn = $modal.find('#bomb_evidence_blood').is(':checked');

                                    // --- จัดการส่วน Hemastix ---
                                    const isHemaChecked = $modal.find('#bomb_test_hemastix').is(':checked') && masterOn;
                                    const $hemaRadios = $modal.find('input[name="hemastix_result"]');
                                    $hemaRadios.prop('disabled', !isHemaChecked);
                                    if (!isHemaChecked) $hemaRadios.prop('checked', false); // ล้างติ๊กผลทดสอบถ้าไม่ได้เลือกหัวข้อ

                                    // --- จัดการส่วน Phenolphthalein ---
                                    const isPhenolChecked = $modal.find('#bomb_test_phenol').is(':checked') && masterOn;
                                    const $phenolRadios = $modal.find('input[name="phenol_result"]');
                                    $phenolRadios.prop('disabled', !isPhenolChecked);
                                    if (!isPhenolChecked) $phenolRadios.prop('checked', false); // ล้างติ๊กผลทดสอบถ้าไม่ได้เลือกหัวข้อ
                                }

                                $(document).ready(function() {
                                    const $modal = $('#addCheckListModalBomb');

                                    // ================================================================
                                    // จัดการ card ของวัตถุพยาน (ระเบิด) ให้เลือกเมนูใหญ่ก่อน เมนูด้านในถึง enable
                                    // ================================================================
                                    // 1. ดักจับเมื่อติ๊ก "ตัวแม่" (ระเบิด)
                                    $('#bomb_evidence_main').on('change', toggleBombSection);

                                    // 2. ดักจับเมื่อติ๊ก "ตัวลูก" 
                                    $(document).on('change', '.js-detonate-check, input[name="bomb_containers[]"]', function() {
                                        syncSubInputs();

                                        // ถ้าติ๊กเลือก และตัวแม่เปิดอยู่ ให้ Focus ช่องแรก
                                        if (this.checked && $('#bomb_evidence_main').is(':checked')) {
                                            $(this).closest('.selection-card, .d-flex, .col-lg-6')
                                                .find('input[type="text"], input[type="number"]')
                                                .first().focus();
                                        }
                                    });

                                    // 3. รันสถานะเริ่มต้นตอนโหลดหน้า
                                    toggleBombSection();

                                    // // ================================================================
                                    // // จัดการ card ส่วนภายนอกอาคาร ให้เลือกเมนูใหญ่ก่อน เมนูด้านในถึง enable
                                    // // ================================================================

                                    $('#check_outdoor_main').on('change', function() {
                                        if (this.checked) $('#check_indoor_main').prop('checked', false);
                                        toggleIndoorSection();
                                        toggleOutdoorSection();
                                    });

                                    $('#bomb_outdoor_other').on('change', function() {
                                        toggleOutdoorSection();
                                        if (this.checked) $('#bomb_outdoor_other_text').focus();
                                    });

                                    // // ================================================================
                                    // // จัดการ card ส่วนภายในอาคาร ให้เลือกเมนูใหญ่ก่อน เมนูด้านในถึง enable
                                    // // ================================================================
                                    $('#check_indoor_main').on('change', function() {
                                        if (this.checked) $('#check_outdoor_main').prop('checked', false);
                                        toggleOutdoorSection();
                                        toggleIndoorSection();
                                    });

                                    $(document).on('change', '.js-bld-check-indoor', function() {
                                        syncIndoorSubFields();
                                        if (this.checked && $('#check_indoor_main').is(':checked')) {
                                            $(this).closest('.selection-card').find('input[type="number"], input[type="text"]').first().focus();
                                        }
                                    });

                                    // =======================================================================
                                    // จัดการ card ส่วนของวัตถุพยานที่เป็นคราบโลหิต ให้เลือกเมนูใหญ่ก่อน เมนูด้านในถึง enable
                                    // =======================================================================

                                    // เมื่อเปลี่ยนสถานะตัวแม่ (คราบเลือด)
                                    $('#bomb_evidence_blood').on('change', toggleBloodSection);

                                    // เมื่อเปลี่ยนสถานะตัวลูก (Hemastix / Phenol) เพื่อเปิด-ปิด Radios ผลทดสอบ
                                    $(document).on('change', '#bomb_test_hemastix, #bomb_test_phenol', syncBloodSubTests);

                                    // รันครั้งแรกเพื่อ Set UI
                                    toggleBloodSection();

                                    // 1. Auto Fill Position 
                                    $('.user-select-box-bomb').on('change', function() {
                                        var idEmp = $(this).val();
                                        var targetInput = $(this).data('pos-target');

                                        if (!idEmp) {
                                            $(targetInput).val('');
                                            return;
                                        }

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
                                            error: function(xhr, status, error) {
                                                console.error("Error fetching position:", error);
                                                $(targetInput).val('');
                                            }
                                        });
                                    });

                                    // เรียกใช้งาน Toggle สำหรับฟิลด์ต่างๆ
                                    setupOtherToggle('bomb_notify_other', 'bomb_notify_other_text'); // รับแจ้งอื่นๆ
                                    setupOtherToggle('bomb_light_other', 'bomb_light_other_text'); // แสงสว่างอื่นๆ
                                    setupOtherToggle('bomb_temp_other', 'bomb_temp_other_text'); // อุณหภูมิอื่นๆ
                                    setupOtherToggle('bomb_outdoor_other', 'bomb_outdoor_other_text'); // กลางแจ้งอื่นๆ
                                    setupPermanentOtherToggle('bld_other_indoor', 'bld_other_text_indoor') // อาคารอื่นๆ ( แสดงตลอดแค่ disabled ไว้ )

                                    // เมื่อเปิด Modal: แปลง Select ตัวแรกเป็น Select2 ทันที
                                    $modal.on('shown.bs.modal', function() {
                                        $('.inspector-select-bomb, .user-select-box-bomb, #police_station_bomb').each(function() {
                                            if (!$(this).hasClass("select2-hidden-accessible")) {
                                                initSelect2Bomb($(this));
                                            }
                                        });

                                        // --- ตั้งค่าวันเวลาปัจจุบัน ---
                                        const now = new Date();
                                        const year = now.getFullYear();
                                        const month = String(now.getMonth() + 1).padStart(2, '0');
                                        const day = String(now.getDate()).padStart(2, '0');
                                        const hours = String(now.getHours()).padStart(2, '0');
                                        const minutes = String(now.getMinutes()).padStart(2, '0');
                                        const currentDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;

                                        $('#collection_datetime_bomb, #measurement_inspection_date_bomb, #measurement_datetime_bomb').val(currentDateTime);

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
                                        const canvasesBomb = document.querySelectorAll('#addCheckListModalBomb .signature-pad');
                                        canvasesBomb.forEach((canvas) => {
                                            const canvasId = canvas.id;

                                            function resizeCanvas() {
                                                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                                                // if (canvas.offsetWidth === 0) return;
                                                // canvas.width = canvas.offsetWidth * ratio;
                                                // canvas.height = canvas.offsetHeight * ratio;
                                                // canvas.getContext('2d').scale(ratio, ratio);

                                                if (canvas.id === 'scene_sketch_canvas_bomb') {
                                                    canvas.width = 1200 * ratio; // ความละเอียดสูงคงที่
                                                    canvas.height = 800 * ratio;
                                                } else if (canvas.id === 'body_diagram_canvas_bomb') {
                                                    canvas.width = 900 * ratio;
                                                    canvas.height = 1200 * ratio;
                                                } else {
                                                    canvas.width = canvas.offsetWidth * ratio;
                                                    canvas.height = canvas.offsetHeight * ratio;
                                                }
                                                canvas.getContext('2d').scale(ratio, ratio);
                                            }

                                            // ถ้าเคยประกาศ global signaturePads ไว้ในหน้าหลักแล้ว
                                            if (typeof signaturePads !== 'undefined' && signaturePads[canvasId]) {
                                                resizeCanvas();
                                                return;
                                            }

                                            resizeCanvas();

                                            let penColor = (canvasId === 'body_diagram_canvas_bomb') ? 'rgb(255, 0, 0)' : 'rgb(0, 0, 0)';

                                            if (typeof SignaturePad !== 'undefined') {
                                                const pad = new SignaturePad(canvas, {
                                                    backgroundColor: 'rgba(255, 255, 255, 0)',
                                                    penColor: penColor,
                                                    minWidth: 1,
                                                    maxWidth: 3,
                                                });

                                                if (typeof signaturePads !== 'undefined') {
                                                    signaturePads[canvasId] = pad;
                                                    if (typeof _sketchOrigColor !== 'undefined') {
                                                        _sketchOrigColor[canvasId] = penColor;
                                                        _sketchColor[canvasId] = _sketchColor[canvasId] || penColor;
                                                    }
                                                }
                                            }

                                            window.addEventListener('resize', resizeCanvas);
                                        });

                                        reIndexInspectorsBomb();
                                        reIndexVictimCardsBomb();
                                        reIndexBodyCardsBomb();
                                        reIndexEvidenceCardsBomb();
                                        reIndexMeasurementCardsBomb();
                                        initBombCanvases();
                                    });

                                    // 6. ฟังก์ชัน Reset เมื่อปิด Modal ให้เคลียร์ค่า และจำนวน select ให้กลับไปค่าเริ่มต้นที่ 1 รายการ
                                    $modal.on('hidden.bs.modal', function() {

                                        // === ส่วนของ Victim (ผู้ประสบเหตุ) ===
                                        const $victimContainer = $('#victim_container_bomb');
                                        $victimContainer.find('.victim-card-bomb:not(:first)').remove(); // ลบ card ที่เกิน

                                        const $firstVictim = $('#victim_container_bomb .victim-card-bomb:first');
                                        $firstVictim.find('input').val('');
                                        $firstVictim.find('select').val('').trigger('change'); // เพิ่ม trigger('change') เพื่อรองรับ Select2
                                        $firstVictim.find('textarea').val(''); // ล้าง textarea ของ victim

                                        // === ส่วนของ Inspector (ผู้ตรวจสถานที่) ===
                                        $('.inspector-row-bomb:not(:first)').remove();
                                        const $firstSelect = $('.inspector-select-bomb:first');
                                        $firstSelect.val(null).trigger('change'); // ล้างค่า Select2

                                        // === ส่วนของ ข้อมูลศพ ===
                                        $('#body_container_bomb .body-card-bomb:not(:first)').remove();
                                        const $firstBody = $('#body_container_bomb .body-card-bomb:first');
                                        $firstBody.find('input, textarea').val('');
                                        $firstBody.find('input[type="checkbox"]').prop('checked', false);
                                        $firstBody.find('.js-body-notfound-text').hide(); // ซ่อนช่องระบุเหตุผลกลับไป
                                        $firstBody.find('.js-body-condition-area').prop('disabled', true); // ปิดช่อง textarea กลับไป

                                        // === ส่วนของ Input อื่นๆ ทั้งหมดใน Modal ===
                                        // ล้างค่าพวก checkbox, input text, number, textarea ทั่วไปที่ไม่ได้อยู่ในกลุ่ม dynamic
                                        $(this).find('input[type="text"], input[type="number"], textarea').val('');
                                        $(this).find('input[type="checkbox"]').prop('checked', false);
                                        $(this).find('input[id$="_text"]').hide().prop('disabled', true);

                                        // === ล้างช่องลายเซ็น ===

                                        if (typeof signaturePads !== 'undefined') {
                                            Object.values(signaturePads).forEach(pad => pad.clear());
                                        }

                                        // ★ รีเซ็ตการติดตาม explicit clear เมื่อปิด modal
                                        window._explicitlyClearedBombSigKeys = new Set();

                                        // === จัดเลขลำดับใหม่ ===
                                        reIndexVictimCardsBomb();
                                        reIndexInspectorsBomb();
                                        reIndexBodyCardsBomb();
                                        reIndexEvidenceCardsBomb();
                                        reIndexMeasurementCardsBomb();
                                    });

                                    // Logic คัดลอกเลขที่เอกสาร
                                    const receiveNotiNoBomb = document.getElementById('receiveNoti_No_bomb');
                                    const caseDocNoBomb = document.getElementById('case_doc_no_bomb');

                                    // ใช้ MutationObserver เพื่อตรวจจับการเปลี่ยนแปลงของ receiveNoti_No_Bomb
                                    if (receiveNotiNoBomb && caseDocNoBomb) {
                                        const observer = new MutationObserver(function(mutations) {
                                            mutations.forEach(function(mutation) {
                                        if (mutation.type === 'childList' || mutation.type === 'characterData') {
                                            var raw = receiveNotiNoBomb.textContent.trim();
                                            caseDocNoBomb.value = (window.toThaiDocNo ? window.toThaiDocNo(raw) : raw);
                                        }
                                            });
                                        });

                                        observer.observe(receiveNotiNoBomb, {
                                            childList: true,
                                            characterData: true,
                                            subtree: true
                                        });

                                        // กรณีที่มีค่าอยู่แล้วตั้งแต่เริ่มต้น
                                        if (receiveNotiNoBomb.textContent.trim()) {
                                            var rawInit = receiveNotiNoBomb.textContent.trim();
                                            caseDocNoBomb.value = (window.toThaiDocNo ? window.toThaiDocNo(rawInit) : rawInit);
                                        }
                                    }

                                    // ฟังก์ชันส่วนกลางสำหรับจัดการ Master-Detail Toggle ( ใช้กับส่วนของวัตถุพยานที่ check แล้วมีแค่ field ให้กรอกรายละเอียด )
                                    // ฟังก์ชันส่วนกลางสำหรับจัดการเปิด/ปิด Field คู่กับ Checkbox
                                    $(document).on('change', '.js-master-check', function() {
                                        const isChecked = $(this).is(':checked');

                                        // 1. หา Parent ที่ใกล้ที่สุด (เลือกเอา d-flex หรือ div ที่หุ้ม Checkbox กับ Input คู่นั้นไว้)
                                        const $parent = $(this).parent().parent(); // วิ่งขึ้นไปหา div ที่หุ้มกลุ่ม d-flex

                                        // หรือถ้าใช้โครงสร้าง d-flex ชัดเจน ใช้ตัวนี้จะแม่นยำกว่า:
                                        const $container = $(this).closest('.d-flex, .selection-card, div.ms-2');

                                        // 2. หา Input/Textarea ทั้งหมดในกลุ่มนั้น (ยกเว้นตัว Checkbox เอง)
                                        const $targets = $container.find('input[type="text"], input[type="number"], textarea, select').not(this);

                                        // 3. จัดการสถานะการใช้งาน
                                        $targets.prop('disabled', !isChecked);

                                        if (isChecked) {
                                            // ถ้าติ๊กเลือก: เปลี่ยนสีพื้นหลังเป็นขาว และ Focus
                                            $targets.removeClass('bg-light').addClass('bg-white');
                                            $targets.first().focus();
                                        } else {
                                            // ถ้าเอาติ๊กออก: ล้างค่า และเปลี่ยนสีเป็นเทา
                                            $targets.val('').prop('checked', false);
                                            $targets.removeClass('bg-white').addClass('bg-light');
                                        }

                                        // หมายเหตุ: ลบคำสั่ง .show() / .hide() ออกแล้ว เพื่อให้แสดงช่อง input ตลอดเวลา
                                    });

                                    // รันครั้งแรกเพื่อตรวจสอบสถานะตอนโหลดหน้า
                                    $('.js-master-check').each(function() {
                                        const isChecked = $(this).is(':checked');
                                        const $container = $(this).closest('.d-flex, .selection-card, div.ms-2');
                                        $container.find('input, textarea, select').not(this).prop('disabled', !isChecked);
                                    });

                                    $('#incident_photos_bomb, #camera_input_bomb').on('change', function(e) {
                                        const files = e.target.files;
                                        if (!files.length) return;

                                        // 1. แสดงตัวหมุนโหลด (Spinner) ในคอนเทนเนอร์ที่เตรียมไว้
                                        const $uploadingBox = $('#uploading_container_bomb');
                                        $uploadingBox.html(`
                                            <div class="d-flex align-items-center justify-content-center p-3 border rounded-3 bg-light animate__animated animate__fadeIn">
                                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                                <span class="small text-secondary">กำลังประมวลผลรูปภาพ <span id="upload_count_bomb">0</span>/${files.length} ใบ...</span>
                                            </div>
                                        `);

                                        let processedCount = 0;
                                        const totalFiles = files.length;

                                        // 2. ประมวลผลไฟล์ทั้งหมด
                                        Array.from(files).forEach(file => {
                                            const reader = new FileReader();
                                            const dateNow = new Date().toLocaleString('th-TH');

                                            reader.onload = function(event) {
                                                // แทรกรูปใหม่ไปหน้าสุด
                                                attachmentStoreBomb.unshift({
                                                    id: 'new_bomb_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5),
                                                    file: file,
                                                    filename: file.name,
                                                    name: file.name,
                                                    src: event.target.result,
                                                    base64: event.target.result,
                                                    size: formatFileSize(file.size),
                                                    date: dateNow,
                                                    isExisting: false
                                                });

                                                processedCount++;
                                                $('#upload_count_bomb').text(processedCount);

                                                // 3. เมื่ออ่านครบทุกไฟล์แล้ว ค่อยสั่ง Render Grid ครั้งเดียว
                                                if (processedCount === totalFiles) {
                                                    setTimeout(() => {
                                                        $uploadingBox.empty(); // เคลียร์ตัวโหลดออก
                                                        renderAttachmentStoreBomb(); // วาด Grid ทั้งหมดใหม่
                                                    }, 500); // ดีเลย์เล็กน้อยเพื่อให้เห็นสถานะโหลด
                                                }
                                            };
                                            reader.readAsDataURL(file);
                                        });

                                        $(this).val(''); // เคลียร์ค่า Input
                                    });

                                    $(document).on('change', '.inspector-select-bomb', function() {
                                        updateInspectorSelectionBomb();
                                    });

                                })

                                function bombRadioToggle(el) {
                                    // ดึงชื่อกลุ่มจาก data-group
                                    const group = el.getAttribute('data-group');
                                    // ค้นหา Checkbox ทั้งหมดในกลุ่มเดียวกัน
                                    const checkboxes = document.querySelectorAll(`[data-group="${group}"]`);

                                    checkboxes.forEach(cb => {
                                        // ถ้าไม่ใช่อันที่เพิ่งคลิก ให้เอาติ๊กออก
                                        if (cb !== el) {
                                            cb.checked = false;
                                        }
                                    });

                                    // เรียกฟังก์ชันจัดการเปิด/ปิดช่อง Text ที่สัมพันธ์กัน (ถ้ามี)
                                    handleRadioTextVisibility(el, group);
                                }

                                // ฟังก์ชันเสริมสำหรับเปิด/ปิดช่องระบุรายละเอียดที่สัมพันธ์กับ Radio-Checkbox
                                function handleRadioTextVisibility(el, group) {
                                    const isChecked = el.checked; // เช็กว่าตอนนี้ติ๊กอยู่ หรือเอาติ๊กออก

                                    // 1. จัดการกลุ่ม "กลิ่น" (smell)
                                    if (group === 'smell') {
                                        const $yesText = $('#bomb_smell_yes_text');
                                        const $noText = $('#bomb_smell_no_text');
                                        const $yesHw = $('#bomb_smell_yes_hw');
                                        const $noHw = $('#bomb_smell_no_hw');

                                        if (isChecked) {
                                            if (el.id === 'bomb_smell_yes') {
                                                $yesText.show().prop('disabled', false).focus();
                                                $yesHw.show();
                                                $noText.hide().prop('disabled', true).val('');
                                                $noHw.hide();
                                            } else if (el.id === 'bomb_smell_no') {
                                                $noText.show().prop('disabled', false).focus();
                                                $noHw.show();
                                                $yesText.hide().prop('disabled', true).val('');
                                                $yesHw.hide();
                                            }
                                        } else {
                                            // ถ้า Uncheck (ไม่เลือกเลยสักอัน) ให้ซ่อนและปิดทั้งคู่
                                            $yesText.hide().prop('disabled', true).val('');
                                            $yesHw.hide();
                                            $noText.hide().prop('disabled', true).val('');
                                            $noHw.hide();
                                        }
                                    }

                                    // 2. จัดการกลุ่ม "การรักษาสถานที่" (scene_preserved)
                                    if (group === 'scene_preserved') {
                                        const $noText = $('#bomb_scene_no_text');
                                        const $noHw = $('#bomb_scene_no_hw');

                                        // โชว์เฉพาะตอนติ๊ก "ไม่มี" (bomb_scene_no) และสถานะเป็น checked เท่านั้น
                                        if (isChecked && el.id === 'bomb_scene_no') {
                                            $noText.show().prop('disabled', false).focus();
                                            $noHw.show();
                                        } else {
                                            // ถ้าติ๊ก "มี" หรือ Uncheck ตัว "ไม่มี" ออก ให้ซ่อนช่องกรอก
                                            $noText.hide().prop('disabled', true).val('');
                                            $noHw.hide();
                                        }
                                    }
                                }

                                // =========================================================
                                // Victim LOGIC
                                // =========================================================

                                // --- ฟังก์ชันจัดลำดับ ---
                                function reIndexVictimCardsBomb() {
                                    const $container = $('#victim_container_bomb');
                                    const $cards = $container.find('.victim-card-bomb');

                                    $cards.each(function(index) {
                                        // ค้นหา badge ภายใน card ใบนั้นๆ แล้วใส่ข้อความ
                                        $(this).find('.js-victim-index-badge').first().text(`รายการที่ ${index + 1}`);

                                        // จัดการปุ่มลบ: ถ้าเป็นรายการแรกให้ซ่อนปุ่มลบ
                                        if ($cards.length === 1) {
                                            $(this).find('.btn-outline-danger').hide();
                                        } else {
                                            $(this).find('.btn-outline-danger').show();
                                        }
                                    });
                                }

                                // --- ฟังก์ชันเพิ่มรายการผู้ประสบเหตุ ---
                                function addVictimCardBomb() {
                                    const container = document.getElementById('victim_container_bomb');
                                    const newCard = document.createElement('div');
                                    newCard.className = 'victim-card-bomb bg-light p-3 rounded-3 border mb-3 animate__animated animate__fadeIn';

                                    newCard.innerHTML = `
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="badge bg-primary text-white js-victim-index-badge"></span>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVictimCardBomb(this)">
                                                <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                            </button>
                                        </div>

                                        <div class="bg-white p-3 rounded-3 shadow-sm">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold small text-muted">ประเภทผู้ประสบเหตุ <span class="text-danger">*</span></label>
                                                <select class="form-select" name="victim_type_bomb[]">
                                                    <option value="" selected>-- เลือกประเภท --</option>
                                                    <option value="ผู้เสียชีวิต">ผู้เสียชีวิต</option>
                                                    <option value="ผู้บาดเจ็บ">ผู้บาดเจ็บ</option>
                                                    <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                                                </select>
                                            </div>

                                            <div class="row g-3">
                                                <div class="col-md-10">
                                                    <label class="form-label small text-muted">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" name="victim_name_bomb[]" placeholder="ชื่อ-นามสกุล">
                                                        <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label small text-muted">อายุ (ปี)</label>
                                                    <input type="text" class="form-control text-center" name="victim_age_bomb[]" 
                                                        inputmode="numeric" pattern="[0-9]*" maxlength="3" 
                                                        oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 3);" placeholder="อายุ">
                                                </div>
                                            </div>
                                        </div>
                                    `;

                                    container.appendChild(newCard);
                                    reIndexVictimCardsBomb();
                                }

                                // --- ฟังก์ชันลบผู้ประสบเหตุ ---
                                function removeVictimCardBomb(button) {
                                    $(button).closest('.victim-card-bomb').remove();
                                    reIndexVictimCardsBomb(); // รันการจัดเลขใหม่ทันทีที่ลบ
                                }

                                // =========================================================
                                // Inspector LOGIC
                                // =========================================================

                                // --- ฟังก์ชันจัดลำดับและควบคุมปุ่มลบ (Inspector) ---
                                function reIndexInspectorsBomb() {
                                    const $rows = $('.inspector-row-bomb');
                                    $rows.each(function(index) {
                                        // 1. ปรับเลขลำดับ (5.1, 5.2, ...)
                                        $(this).find('.inspector-index-label-bomb').text(`5.${index + 1}`);

                                        // 2. ควบคุมปุ่มลบ: ถ้าเป็นแถวแรก (index 0) ให้ซ่อนเสมอ
                                        const $btn = $(this).find('.remove-inspector-bomb');
                                        if (index === 0) {
                                            $btn.addClass('d-none'); // ซ่อนปุ่มลบของรายการ 5.1
                                        } else {
                                            $btn.removeClass('d-none'); // แสดงปุ่มลบของรายการอื่นๆ
                                        }
                                    });
                                    // อัปเดตสถานะรายการที่ถูกเลือกทุกครั้งที่มีการ Re-index
                                    updateInspectorSelectionBomb();
                                }

                                // --- ฟังก์ชันเพิ่มแถวผู้ตรวจ ---
                                function addInspectorRowBomb() {
                                    const container = document.getElementById('inspector_container_bomb');
                                    const newRow = document.createElement('div');
                                    newRow.className = 'd-flex align-items-center mb-3 inspector-row-bomb animate__animated animate__fadeIn';

                                    newRow.innerHTML = `
                                        <div class="text-end pe-3" style="width: 50px;">
                                            <span class="fw-bold text-secondary inspector-index-label-bomb"></span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <select class="form-select inspector-select-bomb" name="inspector_id[]">
                                                ${inspectorOptionsHTMLBomb} 
                                            </select>
                                        </div>
                                        <div class="ms-2" style="width: 32px;">
                                            <button type="button" class="btn-remove-row remove-inspector-bomb" 
                                                    onclick="removeInspectorRowBomb(this)"
                                                    title="ลบรายการ"
                                                    >
                                                <i class="fas fa-times fs-5"></i>
                                            </button>
                                        </div>
                                    `;

                                    container.appendChild(newRow);

                                    // ตั้งค่า Select2 ให้ตัวที่เพิ่มใหม่
                                    const $lastSelect = $(newRow).find('.inspector-select-bomb');
                                    // เรียกใช้ฟังก์ชัน init ที่อยู่ใน ready (หรือประกาศไว้ข้างนอกก็ได้)
                                    if (typeof initSelect2Bomb === 'function') {
                                        initSelect2Bomb($lastSelect);
                                    }

                                    // ผูก Event ทันทีเพื่อให้เช็ค Disabled ทันทีที่เลือกคน
                                    $lastSelect.on('change', function() {
                                        updateInspectorSelectionBomb();
                                    });

                                    reIndexInspectorsBomb();
                                }

                                // --- ฟังก์ชันอัปเดตสถานะ Disabled ของตัวเลือกผู้ตรวจที่ซ้ำกัน ---
                                function updateInspectorSelectionBomb() {
                                    let selectedValues = [];

                                    // 1. เก็บค่า ID ที่ถูกเลือกทั้งหมดไว้ใน Array
                                    $('.inspector-select-bomb').each(function() {
                                        const val = $(this).val();
                                        if (val) selectedValues.push(val);
                                    });

                                    // 2. วนลูปตัวเลือก Select2 ทุกตัวเพื่อกำหนดสถานะ Disabled
                                    $('.inspector-select-bomb').each(function() {
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
                                function removeInspectorRowBomb(button) {
                                    const $rows = $('.inspector-row-bomb');
                                    // ป้องกันการลบถ้าเหลือแถวเดียว (เผื่อปุ่มลบไม่ซ่อน)
                                    if ($rows.length > 1) {
                                        $(button).closest('.inspector-row-bomb').remove();
                                        reIndexInspectorsBomb();
                                    }
                                }

                                // =========================================================
                                // BODY (ข้อมูลศพ) LOGIC
                                // =========================================================

                                // --- ฟังก์ชันจัดลำดับศพ ---
                                function reIndexBodyCardsBomb() {
                                    const $container = $('#body_container_bomb');
                                    const $cards = $container.find('.body-card-bomb');

                                    $cards.each(function(index) {
                                        const no = index + 1;
                                        // 1. อัปเดตตัวเลขใน Badge
                                        $(this).find('.js-body-index-badge').text(`ศพที่ ${no}`);

                                        // 2. อัปเดต name ของ input ทุกตัวให้รันตามลำดับ index
                                        $(this).find('.js-body-status').attr('name', `body_status_bomb[${index}]`);
                                        $(this).find('.js-body-notfound-text').attr('name', `body_notfound_detail_bomb[${index}]`);
                                        $(this).find('.js-body-name').attr('name', `body_name_bomb[${index}]`);
                                        $(this).find('.js-body-condition-area').attr('name', `body_condition_bomb[${index}]`);

                                        // 3. อัปเดต ID และ Label ของ Checkbox เพื่อให้กดเลือกได้ถูกต้อง
                                        const foundId = `status_found_bomb_${index}`;
                                        const notFoundId = `status_notfound_bomb_${index}`;

                                        const $foundCheck = $(this).find('input[value="พบศพ"]');
                                        const $notFoundCheck = $(this).find('input[value="ไม่พบศพ"]');

                                        $foundCheck.attr('id', foundId);
                                        $foundCheck.next('label').attr('for', foundId); // พบศพ label

                                        $notFoundCheck.attr('id', notFoundId);
                                        $notFoundCheck.next('label').attr('for', notFoundId); // ไม่พบศพ label

                                        // 4. จัดการปุ่มลบ (ซ่อนถ้าเหลือใบเดียว)
                                        const $delBtn = $(this).find('.btn-outline-danger');
                                        if ($cards.length === 1) {
                                            $delBtn.hide();
                                        } else {
                                            $delBtn.show();
                                        }
                                    });
                                }

                                // --- ฟังก์ชันเพิ่มรายการศพ ---
                                function addBodyCardBomb() {
                                    const container = document.getElementById('body_container_bomb');
                                    const index = $('.body-card-bomb').length;

                                    const newCard = document.createElement('div');
                                    newCard.className = 'body-card-bomb bg-light p-3 rounded-3 border mb-3 animate__animated animate__fadeIn';
                                    newCard.setAttribute('data-index', index);

                                    newCard.innerHTML = `
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="badge bg-primary text-white js-body-index-badge">ศพที่ ${index + 1}</span>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeBodyCardBomb(this)">
                                                <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                            </button>
                                        </div>

                                        <div class="bg-white p-3 rounded-3 shadow-sm">                                        
                                            <label class="form-label small text-muted">สถานะการพบศพ <span class="text-danger">*</span></label>
                                            
                                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3">
                                                <div class="d-flex flex-wrap align-items-center gap-4">
                                                    
                                                    <div class="form-check mb-0">
                                                        <label class="form-check-label cursor-pointer d-flex align-items-center gap-2 text-dark">
                                                            <input class="form-check-input js-body-status" type="checkbox" 
                                                                value="พบศพ" 
                                                                onclick="toggleBodyCondition(this, 'found')" style="transform: scale(1.1);">
                                                            <span>พบศพ</span>
                                                        </label>
                                                    </div>

                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="form-check mb-0">
                                                            <label class="form-check-label cursor-pointer d-flex align-items-center gap-2 text-dark">
                                                                <input class="form-check-input js-body-status" type="checkbox" 
                                                                    value="ไม่พบศพ" 
                                                                    onclick="toggleBodyCondition(this, 'notfound')" style="transform: scale(1.1);">
                                                                <span>ไม่พบศพ</span>
                                                            </label>
                                                        </div>
                                                        <input type="text" class="form-control form-control-sm js-body-notfound-text" 
                                                            placeholder="ระบุเหตุผล" 
                                                            style="width: 200px; display: none;" disabled>
                                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic js-body-notfound-hw" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                                    </div>

                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                    <label class="form-label small text-muted">ชื่อ - นามสกุล (ถ้าทราบ)</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control js-body-name"
                                                            placeholder="ระบุชื่อ-นามสกุล หรือสัญลักษณ์เรียกแทน" disabled>
                                                        <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                    </div>
                                            </div>

                                            <div class="mb-2">
                                                <label class="form-label small text-muted">สภาพศพ ลักษณะการแต่งกาย ทรัพย์สิน และอื่น ๆ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic ms-1" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                                <textarea class="form-control js-body-condition-area" 
                                                        rows="3" placeholder="..." disabled></textarea>
                                            </div>
                                        </div>
                                    `;

                                    container.appendChild(newCard);
                                    reIndexBodyCardsBomb();
                                }

                                // --- ฟังก์ชันลบรายการศพ ---
                                function removeBodyCardBomb(button) {
                                    if ($('.body-card-bomb').length > 1) {
                                        $(button).closest('.body-card-bomb').remove();
                                        reIndexBodyCardsBomb();
                                    } else {
                                        Swal.fire('แจ้งเตือน', 'ต้องมีรายการศพอย่างน้อย 1 รายการ', 'warning');
                                    }
                                }

                                // --- ฟังก์ชันควบคุมเงื่อนไข (พบศพ / ไม่พบศพ) ---
                                function toggleBodyCondition(el, status) {
                                    const $card = $(el).closest('.body-card-bomb');
                                    const $checkboxes = $card.find('.js-body-status');
                                    const $nameInput = $card.find('.js-body-name');
                                    const $conditionArea = $card.find('.js-body-condition-area');
                                    const $notFoundInput = $card.find('.js-body-notfound-text');
                                    const $notFoundHw = $card.find('.js-body-notfound-hw');

                                    // ทำให้ Checkbox ทำงานเหมือน Radio (เลือกได้อันเดียวในแต่ละ Card)
                                    $checkboxes.not(el).prop('checked', false);

                                    if (el.checked) {
                                        if (status === 'found') {
                                            // กรณีพบศพ: เปิด Textarea, ปิด/ซ่อน ช่องระบุเหตุผล, เปิดให้กรอกชื่อ
                                            $nameInput.prop('disabled', false).focus();
                                            $conditionArea.prop('disabled', false);
                                            $notFoundInput.hide().prop('disabled', true).val('');
                                            $notFoundHw.hide();
                                        } else {
                                            // กรณีไม่พบศพ: เปิดให้กรอกชื่อได้ (ถ้าทราบ), ปิด Textarea, เปิด/โชว์ ช่องระบุเหตุผล
                                            $nameInput.prop('disabled', false);
                                            $conditionArea.prop('disabled', true).val('');
                                            $notFoundInput.show().prop('disabled', false).focus();
                                            $notFoundHw.show();
                                        }
                                    } else {
                                        // ถ้าเอาติ๊กออก: ปิดทุกอย่าง
                                        $nameInput.prop('disabled', true).val('');
                                        $conditionArea.prop('disabled', true).val('');
                                        $notFoundInput.hide().prop('disabled', true).val('');
                                        $notFoundHw.hide();
                                    }
                                }

                                // =========================================================
                                // Evidence (วัตถุพยานและตำแหน่งที่ตรวจพบ) LOGIC
                                // =========================================================

                                // --- ฟังก์ชันจัดลำดับรายการวัตถุพยาน ---
                                function reIndexEvidenceCardsBomb() {
                                    const $container = $('#evidence_container_bomb');
                                    const $cards = $container.find('.evidence-card-bomb');

                                    $cards.each(function(index) {
                                        // 1. อัปเดตตัวเลขใน Badge
                                        $(this).find('.js-evidence-index-badge').text(`รายการวัตถุพยานที่ ${index + 1}`);

                                        // 2. คงชื่อ array (evidence_ref*_dist_bomb[]) ไว้ — saveBomb.php อ่านเป็น array
                                        $(this).find('input[name^="evidence_ref1_dist"]').attr('name', 'evidence_ref1_dist_bomb[]');
                                        $(this).find('input[name^="evidence_ref2_dist"]').attr('name', 'evidence_ref2_dist_bomb[]');
                                        $(this).find('input[name^="evidence_ref3_dist"]').attr('name', 'evidence_ref3_dist_bomb[]');
                                        $(this).find('input[name^="evidence_ref4_dist"]').attr('name', 'evidence_ref4_dist_bomb[]');

                                        // 3. จัดการปุ่มลบ (ถ้ามีรายการเดียวให้ซ่อน)
                                        const $delBtn = $(this).find('button[onclick*="removeEvidenceCardBomb"]');
                                        if ($cards.length === 1) {
                                            $delBtn.hide();
                                        } else {
                                            $delBtn.show();
                                        }
                                    });
                                }

                                // --- ฟังก์ชันเพิ่มรายการวัตถุพยาน ---
                                function addEvidenceRowBomb() {
                                    const container = document.getElementById('evidence_container_bomb');
                                    const index = $('.evidence-card-bomb').length;

                                    const newCard = document.createElement('div');
                                    newCard.className = 'evidence-card-bomb bg-light p-3 rounded-3 border mb-3 animate__animated animate__fadeIn';
                                    newCard.innerHTML = `
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="badge bg-primary text-white js-evidence-index-badge">รายการวัตถุพยานที่ ${index + 1}</span>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEvidenceCardBomb(this)">
                                                <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                            </button>
                                        </div>

                                        <div class="bg-white p-3 rounded-3 shadow-sm border">
                                            <div class="row g-3">
                                                <!-- แถวที่ 1 -->
                                                <div class="col-12">
                                                    <label class="form-label small text-muted">วัตถุพยาน</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" name="evidence_item_bomb[]">
                                                        <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                    </div>
                                                </div>

                                                <!-- แถวที่ 2 -->
                                                <div class="col-12">
                                                    <label class="form-label small text-muted">ระยะห่าง (m) จากจุดอ้างอิง</label>
                                                    <div class="row g-2">
                                                        <div class="col-3">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">จุดที่ 1</span>
                                                                <input type="text" class="form-control" name="evidence_ref1_dist_bomb[]" 
                                                                    inputmode="decimal" placeholder="0.00"
                                                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '');">
                                                            </div>
                                                        </div>
                                                        <div class="col-3">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">จุดที่ 2</span>
                                                                <input type="text" class="form-control" name="evidence_ref2_dist_bomb[]" 
                                                                    inputmode="decimal" placeholder="0.00"
                                                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '');">
                                                            </div>
                                                        </div>
                                                        <div class="col-3">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">จุดที่ 3</span>
                                                                <input type="text" class="form-control" name="evidence_ref3_dist_bomb[]" 
                                                                    inputmode="decimal" placeholder="0.00"
                                                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '');">
                                                            </div>
                                                        </div>
                                                        <div class="col-3">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">จุดที่ 4</span>
                                                                <input type="text" class="form-control" name="evidence_ref4_dist_bomb[]" 
                                                                    inputmode="decimal" placeholder="0.00"
                                                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '');">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- แถวที่ 3 -->
                                                <div class="col-md-4">
                                                    <label class="form-label small text-muted">Azimuth (พิกัด/องศา/ระยะ)</label>
                                                    <input type="text" class="form-control" name="evidence_azimuth_bomb[]">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small text-muted">หมายเหตุ</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" name="evidence_remark_bomb[]">
                                                        <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small text-muted">การตรวจพิสูจน์</label>
                                                    <select class="form-select lab-unit-multi" multiple size="4" title="เลือกได้มากกว่า 1 กลุ่มงาน">
                                                        <option value="">-- เลือก (เลือกได้หลายข้อ) --</option>
                                                        <option value="fingerprint">ลายนิ้วมือแฝง</option>
                                                        <option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option>
                                                        <option value="chemical">เคมีฟิสิกส์</option>
                                                        <option value="drug">ยาเสพติด</option>
                                                        <option value="gun">อาวุธปืน</option>
                                                        <option value="document">เอกสาร</option>
                                                        <option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option>
                                                        <option value="explosive">วัตถุระเบิด (กก.กตว.)</option>
                                                    </select>
                                                    <input type="hidden" class="lab-unit-value" name="evidence_lab_unit_bomb[]" value="">
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                    container.appendChild(newCard);
                                    reIndexEvidenceCardsBomb();
                                }

                                // ฟังก์ชันลบ card วัตถุพยาน
                                function removeEvidenceCardBomb(button) {
                                    const container = document.getElementById('evidence_container_bomb');
                                    if (container.children.length > 1) {
                                        button.closest('.evidence-card-bomb').remove();
                                        reIndexEvidenceCardsBomb();
                                    } else {
                                        Swal.fire('แจ้งเตือน', 'ต้องมีรายการวัตถุพยานอย่างน้อย 1 รายการ', 'warning');
                                    }
                                }

                                // =========================================================
                                // Evidence (บันทึกการตรวจเก็บวัตถุพยาน) LOGIC
                                // =========================================================

                                // --- 1. ฟังก์ชันจัดลำดับรายการตรวจเก็บใหม่ (reIndex) ---
                                function reIndexMeasurementCardsBomb() {
                                    const $container = $('#measurement_container_bomb');
                                    const $cards = $container.find('.measurement-card-bomb');

                                    $cards.each(function(index) {
                                        const no = index + 1;
                                        const $card = $(this);

                                        $card.find('.js-measurement-index-badge').text(`รายการวัตถุพยานที่ ${no}`);

                                        $card.find('input[name^="measurement_package_plastic_check"]').attr('name', `measurement_package_plastic_check[${index}]`).attr('id', `package_plastic_check_${index}`);
                                        $card.find('input[name^="measurement_package_plastic_text"]').attr('name', `measurement_package_plastic_text[${index}]`).attr('id', `package_plastic_[${index}]`);
                                        $card.find('label[for^="package_plastic_check_"]').attr('for', `package_plastic_check_${index}`);

                                        $card.find('input[name^="measurement_package_paper_check"]').attr('name', `measurement_package_paper_check[${index}]`).attr('id', `package_paper_check_${index}`);
                                        $card.find('input[name^="measurement_package_paper_text"]').attr('name', `measurement_package_paper_text[${index}]`).attr('id', `package_paper_[${index}]`);
                                        $card.find('label[for^="package_paper_check_"]').attr('for', `package_paper_check_${index}`);

                                        $card.find('input[name^="measurement_package_other_check"]').attr('name', `measurement_package_other_check[${index}]`).attr('id', `package_other_check_${index}`);
                                        $card.find('input[name^="measurement_package_other_text"]').attr('name', `measurement_package_other_text[${index}]`).attr('id', `package_other_[${index}]`);
                                        $card.find('label[for^="package_other_check_"]').attr('for', `package_other_check_${index}`);

                                        $card.find('input[name^="measurement_action_return_check"]').attr('name', `measurement_action_return_check[${index}]`).attr('id', `action_return_check_${index}`);
                                        $card.find('input[name^="measurement_action_return_text"]').attr('name', `measurement_action_return_text[${index}]`).attr('id', `action_return_[${index}]`);
                                        $card.find('label[for^="action_return_check_"]').attr('for', `action_return_check_${index}`);

                                        $card.find('input[name^="measurement_action_other_check"]').attr('name', `measurement_action_other_check[${index}]`).attr('id', `action_other_check_${index}`);
                                        $card.find('input[name^="measurement_action_other_text"]').attr('name', `measurement_action_other_text[${index}]`).attr('id', `action_other_action_[${index}]`);
                                        $card.find('label[for^="action_other_check_"]').attr('for', `action_other_check_${index}`);

                                        const $delBtn = $card.find('button[onclick*="removeMeasurementCardBomb"]');
                                        if ($cards.length === 1) {
                                            $delBtn.hide();
                                        } else {
                                            $delBtn.show();
                                        }
                                    });
                                }

                                // ฟังก์ชันเพิ่ม card บันทึกการตรวจเก็บ
                                function addMeasurementCardBomb() {
                                    const container = document.getElementById('measurement_container_bomb');
                                    const index = $('.measurement-card-bomb').length;
                                    const newCard = document.createElement('div');
                                    newCard.className = 'measurement-card-bomb bg-light p-3 rounded-3 border mb-3 animate__animated animate__fadeIn';
                                    newCard.innerHTML = `
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="badge bg-primary text-white js-measurement-index-badge">รายการวัตถุพยานที่ ${index + 1}</span>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMeasurementCardBomb(this)">
                                                <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                            </button>
                                        </div>
                                        <div class="bg-white p-3 rounded-3 shadow-sm border">
                                            <div class="row g-3">
                                                <!-- แถวที่ 1: รายการวัตถุพยาน, จำนวน -->
                                                <div class="col-md-8">
                                                    <label class="form-label small text-muted">รายการวัตถุพยาน</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" name="measurement_item_bomb[]">
                                                        <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small text-muted">จำนวน</label>
                                                    <input type="text" class="form-control" name="measurement_quantity_bomb[]" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                                </div>

                                                <!-- แถวที่ 2: บริเวณที่ตรวจพบ, ป้ายหมายเลข -->
                                                <div class="col-md-8">
                                                    <label class="form-label small text-muted">บริเวณที่ตรวจพบ</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" name="measurement_area_bomb[]">
                                                        <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small text-muted">ป้ายหมายเลข</label>
                                                    <input type="text" 
                                                        class="form-control" 
                                                        name="measurement_label_number_bomb[]"
                                                        inputmode="numeric" 
                                                        pattern="[0-9]*"
                                                        oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                                </div>

                                                <!-- แถวที่ 3: การบรรจุหีบห่อ -->
                                                <div class="col-12">
                                                    <label class="form-label small text-muted">การบรรจุหีบห่อ</label>
                                                    <div class="row g-2">
                                                        <div class="col-md-4">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="measurement_package_plastic_check[${index}]" value="1" onchange="togglePackageInput(this, 'plastic_[${index}]')">
                                                                    <label class="form-check-label">พลาสติก</label>
                                                                </div>
                                                                <input type="text" class="form-control form-control-sm" name="measurement_package_plastic_text[${index}]" id="package_plastic_[${index}]" disabled>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="measurement_package_paper_check[${index}]" value="1" onchange="togglePackageInput(this, 'paper_[${index}]')">
                                                                    <label class="form-check-label">กระดาษ</label>
                                                                </div>
                                                                <input type="text" class="form-control form-control-sm" name="measurement_package_paper_text[${index}]" id="package_paper_[${index}]" disabled>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="measurement_package_other_check[${index}]" value="1" onchange="togglePackageInput(this, 'other_[${index}]')">
                                                                    <label class="form-check-label">อื่นๆ</label>
                                                                </div>
                                                                <input type="text" class="form-control form-control-sm" name="measurement_package_other_text[${index}]" id="package_other_[${index}]" disabled>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- แถวที่ 4: การดำเนินการเกี่ยวกับวัตถุพยาน -->
                                                <div class="col-12">
                                                    <label class="form-label small text-muted">การดำเนินการเกี่ยวกับวัตถุพยาน</label>
                                                    <div class="row g-2">
                                                        <div class="col-md-6">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                                                    <input class="form-check-input" type="checkbox" name="measurement_action_return_check[${index}]" value="1" onchange="toggleActionInput(this, 'return_[${index}]')">
                                                                    <label class="form-check-label">ส่งคืนพงส.</label>
                                                                </div>
                                                                <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_return_text[${index}]" id="action_return_[${index}]" disabled>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                                                    <input class="form-check-input" type="checkbox" name="measurement_action_other_check[${index}]" value="1" onchange="toggleActionInput(this, 'other_action_[${index}]')">
                                                                    <label class="form-check-label">อื่นๆ</label>
                                                                </div>
                                                                <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_other_text[${index}]" id="action_other_action_[${index}]" disabled>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- แถวที่ 5: หมายเหตุ -->
                                                <div class="col-12">
                                                    <label class="form-label small text-muted">หมายเหตุ</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" name="measurement_remark_bomb[]">
                                                        <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                    </div>
                                                </div>

                                                <!-- แถวที่ 6: การตรวจพิสูจน์ -->
                                                <div class="col-12">
                                                    <label class="form-label small text-muted">การตรวจพิสูจน์</label>
                                                    <select class="form-select lab-unit-multi" multiple size="4" title="เลือกได้มากกว่า 1 กลุ่มงาน">
                                                        <option value="">-- กรุณาเลือก (เลือกได้หลายข้อ) --</option>
                                                        <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                                        <option value="bio_dna">กลุ่มงานตรวจชีววิทยาและดีเอ็นเอ</option>
                                                        <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                                        <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                                        <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                                        <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                                        <option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                                                        <option value="explosive">กองกำกับการเก็บกู้และตรวจสอบวัตถุระเบิด (กก.กตว.)</option>
                                                    </select>
                                                    <input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_bomb[]" value="">
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                    container.appendChild(newCard);
                                    reIndexMeasurementCardsBomb();
                                }

                                // ฟังก์ชันลบ card บันทึกการวัดพบ
                                function removeMeasurementCardBomb(button) {
                                    const container = document.getElementById('measurement_container_bomb');
                                    if (container.children.length > 1) {
                                        button.closest('.measurement-card-bomb').remove();
                                        reIndexMeasurementCardsBomb();
                                    } else {
                                        Swal.fire('แจ้งเตือน', 'ต้องมีรายการวัตถุพยานอย่างน้อย 1 รายการ', 'warning');
                                    }
                                }

                                // =========================================================
                                // Photos LOGIC
                                // =========================================================
                                
                                // ฟังก์ชันอัปเดตเลขรูปภาพอัตโนมัติ
                                function updatePhotoNumberingBomb() {
                                    const totalPhotos = attachmentStoreBomb.length;
                                    if (totalPhotos > 0) {
                                        const firstName = attachmentStoreBomb[0].filename || attachmentStoreBomb[0].name || '1';
                                        const lastName = attachmentStoreBomb[totalPhotos - 1].filename || attachmentStoreBomb[totalPhotos - 1].name || totalPhotos.toString();
                                        $('#photo_id_start_bomb').val(firstName);
                                        $('#photo_id_end_bomb').val(lastName);
                                        $('#photo_amount_bomb').val(totalPhotos.toString());
                                    } else {
                                        $('#photo_id_start_bomb').val('');
                                        $('#photo_id_end_bomb').val('');
                                        $('#photo_amount_bomb').val('');
                                    }
                                }
                                
                                function renderAttachmentStoreBomb() {
                                    const $grid = $('#attachments_grid_bomb');
                                    const $wrapper = $('#attachments_wrapper_bomb');
                                    const $badge = $('#file_count_badge_bomb');

                                    $grid.empty();

                                    const totalCount = attachmentStoreBomb.length;
                                    $badge.text(totalCount);

                                    if (totalCount > 0) {
                                        $wrapper.removeClass('d-none');

                                        attachmentStoreBomb.forEach((file, index) => {
                                            const isExisting = file.isExisting || file.existing;
                                            const statusBadge = isExisting ?
                                                '<span class="badge bg-info text-white fw-normal" style="font-size: 0.65rem;">รูปเดิม</span>' :
                                                '<span class="badge bg-success text-white fw-normal" style="font-size: 0.65rem;">รูปใหม่</span>';

                                            const fileSize = file.size ? file.size : 'N/A';
                                            const fileDate = file.date ? file.date : '-';
                                            const imgSrc = file.src || file.base64 || '';
                                            const fileName = file.filename || file.name || file.caption || 'photo';

                                            const html = `
                                            
                                                <div class="col animate__animated animate__fadeIn">
                                                    <div class="card attachment-card h-100 shadow-sm rounded-3 overflow-hidden">
                                                        
                                                        <div class="card-header-actions">
                                                            <button type="button" class="btn btn-outline-primary btn-action-sm" 
                                                                onclick="showImagePreviewBomb(${index})" title="ดูรูปภาพ">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger btn-action-sm" 
                                                                onclick="removeFileFromStoreBomb(${index})" title="ลบรูปภาพ">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </div>

                                                        <div class="img-box-middle" onclick="showImagePreviewBomb(${index})" style="cursor:zoom-in;">
                                                            <img src="${imgSrc}" alt="${fileName}">
                                                        </div>

                                                        <div class="card-body p-2 d-flex flex-column bg-white">
                                                            <div class="text-dark fw-bold text-truncate mb-2" style="font-size: 0.7rem; letter-spacing: 0.01rem;" title="${fileName}">
                                                                ${fileName}
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
                                    }
                                    
                                    // อัปเดตเลขรูปภาพอัตโนมัติ
                                    updatePhotoNumberingBomb();
                                }
                                // ★ expose ให้ PDF form / toggle เรียกใช้ได้ (เป็นระบบเดียวกับฟอร์มเสมือน)
                                window.renderBombAttachmentGrid = renderAttachmentStoreBomb;
                                window.showImagePreviewBomb = showImagePreviewBomb;
                                window.removeFileFromStoreBomb = removeFileFromStoreBomb;
                                // ฟังก์ชันสำหรับเปิดดูรูปขนาดเต็ม (Lightbox)
                                function showImagePreviewBomb(index) {
                                    const file = attachmentStoreBomb[index];
                                    if (!file) return;

                                    // 1. ตั้งค่ารูปและชื่อ (รองรับ BLOB src + legacy base64)
                                    $('#lightboxImageBomb').attr('src', file.src || file.base64 || '');
                                    $('#lightboxFileNameBomb').text(file.filename || file.name || file.caption || 'photo');

                                    // 2. แสดง Lightbox นุ่มๆ
                                    $('#customLightboxBomb').removeClass('d-none').hide().fadeIn(200);

                                    // 3. ล็อก Scrollbar
                                    $('body').addClass('lightbox-open');
                                }

                                // ฟังก์ชันปิดเมื่อคลิกพื้นที่ว่าง
                                function closeLightboxOutsideBomb(event) {
                                    // ตรวจสอบว่า ID ที่คลิกคือตัวพื้นหลัง (customLightboxBomb) จริงๆ ไม่ใช่ตัวรูปภาพ
                                    if (event.target.id === 'customLightboxBomb') {
                                        closeLightboxBomb();
                                    }
                                }

                                // ฟังก์ชันปิด Lightbox 
                                function closeLightboxBomb() {
                                    $('#customLightboxBomb').fadeOut(200, function() {
                                        $(this).addClass('d-none');
                                    });
                                    $('body').removeClass('lightbox-open');
                                }

                                function removeFileFromStoreBomb(index) {
                                    const file = attachmentStoreBomb[index];
                                    if (!file) return;

                                    if (file.isExisting || file.existing) {
                                        // กรณีเป็น "รูปเดิม" ให้คง Swal ไว้เพื่อป้องกันการเผลอลบไฟล์จริงใน Server
                                        Swal.fire({
                                            title: 'ยืนยันการลบรูปที่บันทึก?',
                                            text: "รูปภาพจะถูกลบออกจากระบบถาวรเมื่อกดบันทึก",
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonColor: '#d33',
                                            confirmButtonText: 'ลบ',
                                            cancelButtonText: 'ยกเลิก'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                // ★ track BLOB/filename สำหรับลบบน server เฉพาะเมื่อผู้ใช้ยืนยันลบเท่านั้น
                                                if (file.db_file_id) {
                                                    if (typeof deletedExistingPhotosBomb !== 'undefined') {
                                                        deletedExistingPhotosBomb.push({ file_id: file.db_file_id });
                                                    }
                                                } else if (file.filename || file.disk_filename) {
                                                    if (typeof deletedExistingPhotosBomb !== 'undefined') {
                                                        deletedExistingPhotosBomb.push({ filename: file.filename || file.disk_filename });
                                                    }
                                                }
                                                attachmentStoreBomb.splice(index, 1);
                                                renderAttachmentStoreBomb();
                                            }
                                        });
                                    } else {
                                        // กรณีเป็น "รูปใหม่" ลบออกจาก Array ได้ทันที ไม่ต้องถาม
                                        attachmentStoreBomb.splice(index, 1);
                                        renderAttachmentStoreBomb();
                                    }
                                }

                                // =========================================================
                                // --- ฟังก์ชัน Validation และรวบรวมข้อมูลสำหรับ Bomb Modal ---
                                // =========================================================
                                window.prepareDataForSubmissionBomb = function() {
                                    const form = document.getElementById('incidentCheckListFormBomb');
                                    let allErrors = [];
                                    const modalBody = document.querySelector('#addCheckListModalBomb .modal-body');

                                    // เพิ่มการตรวจสอบประเภทสถานที่ (บังคับเลือก 1 อย่าง)
                                    const isOutdoor = document.getElementById('check_outdoor_main').checked;
                                    const isIndoor = document.getElementById('check_indoor_main').checked;

                                    if (!isOutdoor && !isIndoor) {
                                        allErrors.push("กรุณาเลือกประเภทสถานที่ (ภายใน หรือ ภายนอกอาคาร)");
                                        const $targetSection = $('#check_outdoor_main').closest('.mb-4');
                                        if (allErrors.length === 1 && $targetSection.length) {
                                            const targetRect = $targetSection[0].getBoundingClientRect();
                                            const modalRect = modalBody.getBoundingClientRect();
                                            const relativeTop = targetRect.top - modalRect.top + modalBody.scrollTop;
                                            modalBody.scrollTo({ top: relativeTop - 50, behavior: 'smooth' });
                                            document.getElementById('check_outdoor_main').focus({ preventScroll: true });
                                        }
                                    }

                                    // 1. HTML5 Validation
                                    form.classList.add('was-validated');
                                    const invalidFields = [];
                                    const allInvalid = form.querySelectorAll(':invalid');
                                    allInvalid.forEach(field => {
                                        if (field.tagName === 'FIELDSET' || field.tagName === 'FORM') return;
                                        let fieldName = '';
                                        const label = field.closest('.mb-3, .col-md-6, .col-md-4, .col-12, .col-lg-6')?.querySelector('label');
                                        if (label) { fieldName = label.textContent.replace('*', '').trim(); }
                                        else { fieldName = field.getAttribute('placeholder') || field.getAttribute('name') || 'ฟิลด์ข้อมูล'; }
                                        invalidFields.push({ element: field, name: fieldName });
                                    });
                                    if (invalidFields.length > 0) {
                                        invalidFields.forEach(item => { allErrors.push(`กรุณากรอก/เลือก: ${item.name}`); });
                                    }

                                    if (allErrors.length > 0) {
                                        const firstInvalid = allInvalid[0];
                                        if (firstInvalid) {
                                            let targetElement = firstInvalid;
                                            if ($(firstInvalid).hasClass('select2-hidden-accessible')) {
                                                targetElement = $(firstInvalid).next('.select2-container')[0] || firstInvalid;
                                            }
                                            if (modalBody && targetElement) {
                                                const targetRect = targetElement.getBoundingClientRect();
                                                const modalRect = modalBody.getBoundingClientRect();
                                                const relativeTop = targetRect.top - modalRect.top + modalBody.scrollTop;
                                                modalBody.scrollTo({ top: relativeTop - 100, behavior: 'smooth' });
                                                targetElement.focus({ preventScroll: true });
                                            }
                                        }
                                        let errorHTML = '<div style="text-align: left; max-height: 400px; overflow-y: auto;">';
                                        allErrors.slice(0, 10).forEach((err, index) => { errorHTML += `<div style="margin-bottom: 5px;">${index + 1}. ${err}</div>`; });
                                        if (allErrors.length > 10) errorHTML += `<div class="mt-2 text-muted small">... และอีก ${allErrors.length - 10} รายการ</div>`;
                                        errorHTML += '</div>';
                                        Swal.fire({ icon: 'warning', title: `พบข้อผิดพลาด ${allErrors.length} รายการ`, html: errorHTML, confirmButtonText: 'ตกลง', confirmButtonColor: '#0d6efd', returnFocus: false });
                                        return;
                                    }

                                    // 2. ยืนยันก่อนบันทึก
                                    Swal.fire({
                                        title: 'ยืนยันการบันทึกข้อมูล',
                                        text: "กรุณาตรวจสอบความถูกต้องก่อนบันทึก",
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonColor: '#198754',
                                        cancelButtonColor: '#d33',
                                        confirmButtonText: 'ยืนยัน, บันทึกเลย!',
                                        cancelButtonText: 'ยกเลิก',
                                    }).then(async (result) => {
                                        if (result.isConfirmed) {
                                            Swal.fire({ title: 'กำลังบันทึกข้อมูล...', html: 'กรุณารอสักครู่', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

                                            // 3. Build FormData
                                            // ★ sync ช่อง "การตรวจพิสูจน์" (multi-select) ลง hidden input ก่อนเก็บ FormData
                                            if (window.LabUnitMulti) window.LabUnitMulti.syncAll(form);
                                            // ★ ปลด disabled ชั่วคราวเพื่อให้ FormData เก็บค่า checkbox ทั้งหมด
                                            // (กรณี save จาก PDF form: syncBombFormData('pdfToStd') ตั้งค่า .checked
                                            //  แต่ไม่ได้ trigger('change') จึงไม่ได้ re-enable ฟิลด์ในฟอร์มมาตรฐาน)
                                            const $disabledFields = $(form).find(':disabled');
                                            $disabledFields.prop('disabled', false);
                                            const formData = new FormData(form);
                                            $disabledFields.prop('disabled', true);

                                            // ★ เก็บ base64 ไว้เป็น fallback (ลบเฉพาะ key ที่ส่ง blob สำเร็จ ทีหลัง)
                                            // ลบเฉพาะ base64 จาก PDF form (ชื่อ bomb_*) ที่ไม่ใช่ fallback สำหรับ server
                                            formData.delete('bomb_receiver_sig_data');
                                            formData.delete('bomb_sender_sig_data');
                                            formData.delete('bomb_scene_sketch_data');
                                            formData.delete('bomb_body_diagram_data');
                                            // ★ เก็บ standard base64 fields ไว้เป็น fallback → ลบเฉพาะ key ที่ส่ง blob สำเร็จ
                                            var _base64KeysToDelete = [];
                                            formData.delete('bomb_receiver_sig_data');
                                            formData.delete('bomb_sender_sig_data');
                                            formData.delete('bomb_scene_sketch_data');
                                            formData.delete('bomb_body_diagram_data');

                                            // 4. ส่ง signatures / sketch เป็น Blob file
                                            // ★ ถ้า save จาก PDF form → ดู PDF canvas ก่อน, fallback เป็น standard canvas
                                            const preferPdf = !!window._savingFromBombPdfForm;
                                            window._savingFromBombPdfForm = false;

                                            // บางรอบอาจยังไม่มี global signaturePads ให้ fallback เป็น object ว่างเพื่อไม่ให้ save ล้ม
                                            const padStore = (typeof signaturePads !== 'undefined' && signaturePads) ? signaturePads : {};

                                            // ★ แผนผังของฟอร์ม PDF เป็นแบบหลายหน้า (bpf_sketch_page_N_canvas)
                                            //    bpfCollectSketchPagesData() จะรวมภาพ (พร้อมรูปพื้นหลัง)
                                            //    ไว้ใน hidden input #bpf_scene_sketch_data
                                            //    ของเดิมไปหา canvas ชื่อ bpf_scene_sketch_canvas ซึ่งไม่มีอยู่จริง
                                            //    จึงถือว่าแผนผังถูกล้าง แล้วลบรูปเดิมทิ้ง -> ไม่ขึ้นในไฟล์รายงาน
                                            let bombSketchHandled = false;
                                            if (preferPdf) {
                                                const bpfSketchHasContent = (window._bpfSketchPages || []).some(function(p) {
                                                    if (p._bgImgEl || p.bgImageData) return true;
                                                    const c = document.getElementById(p.canvasId);
                                                    if (!c || !c.width || !c.height) return false;
                                                    try {
                                                        const px = c.getContext('2d').getImageData(0, 0, c.width, c.height).data;
                                                        for (let pi = 3; pi < px.length; pi += 4) {
                                                            if (px[pi] > 0) return true;
                                                        }
                                                    } catch (e) { /* skip */ }
                                                    return false;
                                                });
                                                const bpfSketchInp = document.getElementById('bpf_scene_sketch_data');
                                                const bpfSketchVal = bpfSketchInp ? (bpfSketchInp.value || '') : '';
                                                if (bpfSketchHasContent && bpfSketchVal.indexOf('data:image') === 0) {
                                                    const bpfSketchBlob = await dataURLtoBlob(bpfSketchVal);
                                                    if (bpfSketchBlob) {
                                                        formData.append('sig_file_scene_sketch', bpfSketchBlob, 'scene_sketch.png');
                                                        bombSketchHandled = true;
                                                    }
                                                }
                                            }

                                            const sigCanvasMap = preferPdf ? {
                                                'receiver_signature': ['bpf_sig_receiver', 'sig-canvas-receiver-bomb'],
                                                'sender_signature': ['bpf_sig_sender', 'sig-canvas-sender-bomb'],
                                                'body_diagram': ['bpf_body_diagram_canvas', 'body_diagram_canvas_bomb']
                                            } : {
                                                'scene_sketch': ['scene_sketch_canvas_bomb'],
                                                'receiver_signature': ['sig-canvas-receiver-bomb'],
                                                'sender_signature': ['sig-canvas-sender-bomb'],
                                                'body_diagram': ['body_diagram_canvas_bomb']
                                            };

                                            const clearedSignatures = [];
                                            // แผนผังจาก PDF form จัดการแยกด้านบนแล้ว
                                            // ถ้าไม่มีเนื้อหาเลย ให้แจ้งลบรูปเดิม
                                            if (preferPdf && !bombSketchHandled) {
                                                clearedSignatures.push('scene_sketch');
                                            }
                                            for (const [key, canvasIds] of Object.entries(sigCanvasMap)) {
                                                let blobSent = false;
                                                for (const canvasId of canvasIds) {
                                                    if (blobSent) break;

                                                    // Body diagram ต้อง composite กับรูปพื้นหลัง
                                                    if (key === 'body_diagram') {
                                                        const pad = padStore[canvasId];
                                                        const cvs = document.getElementById(canvasId);

                                                        // ตรวจว่า canvas มีเนื้อหาจริง (รองรับทั้ง SignaturePad stroke + BLOB ที่โหลดด้วย drawImage)
                                                        let bodyHasContent = (pad && !pad.isEmpty());
                                                        if (!bodyHasContent && cvs && cvs.width > 0 && cvs.height > 0) {
                                                            try {
                                                                const ctx = cvs.getContext('2d');
                                                                const pixelData = ctx.getImageData(0, 0, cvs.width, cvs.height).data;
                                                                for (let pi = 3; pi < pixelData.length; pi += 4) {
                                                                    if (pixelData[pi] > 0) { bodyHasContent = true; break; }
                                                                }
                                                            } catch(e) { /* skip cross-origin */ }
                                                        }

                                                        if (bodyHasContent && cvs) {
                                                            const backgroundImg = cvs.parentElement.querySelector('img[src*="body_diagram"]');
                                                            let targetCanvas = cvs;
                                                            if (backgroundImg && backgroundImg.naturalWidth > 0) {
                                                                const tempCanvas = document.createElement('canvas');
                                                                tempCanvas.width = cvs.width;
                                                                tempCanvas.height = cvs.height;
                                                                const tempCtx = tempCanvas.getContext('2d');
                                                                tempCtx.fillStyle = '#FFFFFF';
                                                                tempCtx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
                                                                const imgW = backgroundImg.naturalWidth;
                                                                const imgH = backgroundImg.naturalHeight;
                                                                const canW = tempCanvas.width;
                                                                const canH = tempCanvas.height;
                                                                const scale = Math.min(canW / imgW, canH / imgH);
                                                                const drawW = imgW * scale;
                                                                const drawH = imgH * scale;
                                                                const drawX = (canW - drawW) / 2;
                                                                const drawY = (canH - drawH) / 2;
                                                                tempCtx.drawImage(backgroundImg, drawX, drawY, drawW, drawH);
                                                                tempCtx.drawImage(cvs, 0, 0);
                                                                targetCanvas = tempCanvas;
                                                            }
                                                            const blob = await canvasToBlob(targetCanvas, 'image/png');
                                                            if (blob) { formData.append('sig_file_' + key, blob, key + '.png'); blobSent = true; }
                                                        }
                                                        continue;
                                                    }

                                                    // Normal signature/sketch canvases
                                                    if (padStore[canvasId] && !padStore[canvasId].isEmpty()) {
                                                        const cvs = document.getElementById(canvasId);
                                                        if (cvs) {
                                                            const blob = await canvasToBlob(cvs, 'image/png');
                                                            if (blob) { formData.append('sig_file_' + key, blob, key + '.png'); blobSent = true; }
                                                        }
                                                    } else {
                                                        // Fallback: raw canvas (PDF form canvas อาจไม่มี SignaturePad)
                                                        const cvs = document.getElementById(canvasId);
                                                        if (cvs && cvs.width > 0 && cvs.height > 0) {
                                                            try {
                                                                const ctx = cvs.getContext('2d');
                                                                const pixelData = ctx.getImageData(0, 0, cvs.width, cvs.height).data;
                                                                let hasContent = false;
                                                                for (let pi = 3; pi < pixelData.length; pi += 4) { if (pixelData[pi] > 0) { hasContent = true; break; } }
                                                                if (hasContent) {
                                                                    const blob = await canvasToBlob(cvs, 'image/png');
                                                                    if (blob) { formData.append('sig_file_' + key, blob, key + '.png'); blobSent = true; }
                                                                }
                                                            } catch(e) { /* skip cross-origin or empty canvas */ }
                                                        }
                                                    }
                                                }
                                                if (!blobSent) {
                                                    // ★ เพิ่ม cleared เฉพาะเมื่อผู้ใช้กด "ล้างกระดาน" อย่างตั้งใจเท่านั้น
                                                    // ถ้าไม่มีการ clear อย่างตั้งใจ → ปล่อยให้ server preserve ไฟล์เดิม (Priority 3)
                                                    const _ec = window._explicitlyClearedBombSigKeys;
                                                    if (_ec && _ec.has(key)) { clearedSignatures.push(key); }
                                                } else {
                                                    // ★ Blob ส่งสำเร็จ → ลบ base64 เพื่อไม่ให้ server ประมวลผลซ้ำ
                                                    _base64KeysToDelete.push(key);
                                                }
                                            }
                                            // ★ ลบ base64 เฉพาะ key ที่ส่ง blob สำเร็จ (เก็บ fallback ไว้สำหรับ key ที่ยังไม่ได้ส่ง)
                                            var _sigBase64NameMap = {
                                                'scene_sketch': 'scene_sketch_data_bomb',
                                                'receiver_signature': 'receiver_signature_data_bomb',
                                                'sender_signature': 'sender_signature_data_bomb',
                                                'body_diagram': 'body_diagram_data_bomb'
                                            };
                                            _base64KeysToDelete.forEach(function(k) {
                                                if (_sigBase64NameMap[k]) formData.delete(_sigBase64NameMap[k]);
                                            });
                                            if (clearedSignatures.length > 0) {
                                                formData.append('cleared_signatures', JSON.stringify(clearedSignatures));
                                            }

                                            // 5. ★ Deleted photos (แยก BLOB file_id กับ filename legacy)
                                            formData.delete('deleted_photos');
                                            const deletedFileIds = [];
                                            const deletedFilenames = [];
                                            if (deletedExistingPhotosBomb.length > 0) {
                                                deletedExistingPhotosBomb.forEach(function(item) {
                                                    if (typeof item === 'object' && item.file_id) { deletedFileIds.push(item.file_id); }
                                                    else if (typeof item === 'object' && item.filename) { deletedFilenames.push(item.filename); }
                                                    else if (typeof item === 'number') { deletedFileIds.push(item); }
                                                    else if (typeof item === 'string') { deletedFilenames.push(item); }
                                                });
                                            }
                                            if (deletedFileIds.length > 0) { formData.append('deleted_photo_file_ids', JSON.stringify(deletedFileIds)); }
                                            if (deletedFilenames.length > 0) { formData.append('deleted_photos', JSON.stringify(deletedFilenames)); }

                                            // 6. Photos จาก attachmentStoreBomb
                                            formData.delete('incident_photos_bomb[]');
                                            formData.delete('camera_photos_bomb[]');
                                            if (typeof attachmentStoreBomb !== 'undefined' && attachmentStoreBomb.length > 0) {
                                                attachmentStoreBomb.forEach((item, index) => {
                                                    if (item.file) {
                                                        formData.append('incident_photos_bomb[]', item.file, item.file.name || `photo_${index + 1}.jpg`);
                                                    } else if (item.base64) {
                                                        const byteString = atob(item.base64.split(',')[1]);
                                                        const mimeString = item.base64.split(',')[0].split(':')[1].split(';')[0];
                                                        const ab = new ArrayBuffer(byteString.length);
                                                        const ia = new Uint8Array(ab);
                                                        for (let i = 0; i < byteString.length; i++) { ia[i] = byteString.charCodeAt(i); }
                                                        const blob = new Blob([ab], { type: mimeString });
                                                        formData.append('incident_photos_bomb[]', blob, item.filename || `photo_${index + 1}.jpg`);
                                                    }
                                                });
                                            }

                                            // 7. AJAX Submit (Offline-aware)
                                            const bombUrl = form.action;
                                            if (!navigator.onLine) {
                                                await saveChecklistOffline(formData, bombUrl, '#addCheckListModalBomb');
                                                return;
                                            }
                                            const backendOkBomb = await checkBackendHealth();
                                            if (!backendOkBomb) {
                                                await saveChecklistOffline(formData, bombUrl, '#addCheckListModalBomb');
                                                return;
                                            }
                                            $.ajax({
                                                url: bombUrl,
                                                type: 'POST',
                                                data: formData,
                                                processData: false,
                                                contentType: false,
                                                success: function(response) {
                                                    Swal.close();
                                                    if (response.success) {
                                                        Swal.fire({ icon: 'success', title: 'สำเร็จ', text: response.message || 'บันทึกข้อมูลเรียบร้อย', confirmButtonText: 'ตกลง' }).then(() => {
                                                            // ★ ปิด PDF modal ด้วย (ถ้า save จาก PDF form)
                                                            const pdfModalEl = document.getElementById('bombFormPdfModal');
                                                            if (pdfModalEl) {
                                                                const pdfMdl = bootstrap.Modal.getInstance(pdfModalEl);
                                                                if (pdfMdl) pdfMdl.hide();
                                                            }
                                                            const modalEl = document.getElementById('addCheckListModalBomb');
                                                            const modal = bootstrap.Modal.getInstance(modalEl);
                                                            if (modal) modal.hide();
                                                            if (typeof resetBombForm === 'function') resetBombForm();
                                                            if (typeof loadData === 'function') loadData();
                                                        });
                                                    } else {
                                                        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message || 'ไม่สามารถบันทึกข้อมูลได้', confirmButtonText: 'ตกลง' });
                                                    }
                                                },
                                                error: async function(xhr, status, error) {
                                                    Swal.close();
                                                    console.error('Error:', error);
                                                    await saveChecklistOffline(formData, bombUrl, '#addCheckListModalBomb');
                                                }
                                            });
                                        }
                                    });
                                };

                                // ============================================================================================
                                // --- ฟังก์ชัน สำหรับ load ข้อมูลมา fill สำหรับการ update data / แสดง modal เปล่า กรณีเป็นรายการใหม่ ---
                                // ============================================================================================
                                function loadBombDataToModal(incidentId, options) {
                                    options = options || {};
                                    const shouldShowStandardModal = options.showStandardModal !== false;
                                    const onLoaded = (typeof options.onLoaded === 'function') ? options.onLoaded : null;
                                    // ★ รีเซ็ตการติดตาม explicit clear ทุกครั้งที่โหลดข้อมูลใหม่
                                    window._explicitlyClearedBombSigKeys = new Set();

                                    const $form = $('#incidentCheckListFormBomb');
                                    // 1. Reset ฟอร์มและสถานะ Validation
                                    $form[0].reset();
                                    $form.removeClass('was-validated');
                                    $form.find('.select2-hidden-accessible').val(null).trigger('change');
                                    $('#victim_container_bomb, #inspector_container_bomb, #body_container_bomb, #evidence_container_bomb, #measurement_container_bomb').empty();

                                    // เตรียมวันที่/เวลาปัจจุบันไว้ใช้ (Fallback)
                                    const now = new Date();
                                    const dateLocal = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
                                    const timeLocal = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
                                    const dateTimeLocal = `${dateLocal}T${timeLocal}`;

                                    $.get('./api/incidentCheckList/getBombData.php', {
                                        incident_id: incidentId
                                    }, function(res) {
                                        // ★ เก็บ basic_info ไว้ใน global เพื่อ auto-fill พฤติการณ์คดี
                                        if (res.basic_info) window._basicInfoBomb = res.basic_info;

                                        if (res.success) {
                                            // ==========================================
                                            // CASE 1: มีข้อมูลเดิม (โหมดแก้ไข)
                                            // ==========================================

                                            console.log(res)

                                            // ==================== แสดงจำนวนการแก้ไข ====================
                                            if (res.edit_info) {
                                                const ei = res.edit_info;
                                                $('#editCountBomb').text(ei.count_edit || 0);
                                                $('#editCountBombPdf').text(ei.count_edit || 0);
                                                if (ei.edit_date) {
                                                    const ed = new Date(ei.edit_date);
                                                    const dd = String(ed.getDate()).padStart(2, '0');
                                                    const mm = String(ed.getMonth() + 1).padStart(2, '0');
                                                    const yyyy = ed.getFullYear() + 543;
                                                    const hh = String(ed.getHours()).padStart(2, '0');
                                                    const mi = String(ed.getMinutes()).padStart(2, '0');
                                                    $('#editDateBomb').text(dd + '/' + mm + '/' + yyyy + ' ' + hh + ':' + mi + ' น.');
                                                } else {
                                                    $('#editDateBomb').text('-');
                                                }
                                                $('#editInfoBomb').removeClass('d-none');
                                                $('#editInfoBombPdf').removeClass('d-none');
                                            }

                                            const d = res.data;
                                            const gen = d.general_info;
                                            // เก็บข้อมูลลายเซ็นไว้ในตัวแปรชั่วคราวก่อน
                                            const savedSignatures = d.signatures;

                                            // 2. Fill ข้อมูลพื้นฐาน (General Info)
                                            $('[name="case_doc_bomb"]').val(gen.case_doc_no ? (window.toThaiDocNo ? toThaiDocNo(gen.case_doc_no) : gen.case_doc_no) : (window.toThaiDocNo ? toThaiDocNo($('#doc_no_bomb').val() || '') : ($('#doc_no_bomb').val() || ''))); // 1. การรับแจ้งเหตุ - คดี
                                            $('[name="case_date"]').val(gen.case_date); // 1. การรับแจ้งเหตุ - วันที่
                                            $('[name="case_time"]').val(gen.case_time); // 1. การรับแจ้งเหตุ - เวลาประมาณ
                                            $('#police_station_bomb').val(gen.source_station).trigger('change'); // 1. การรับแจ้งเหตุ - สภ./สน.
                                            $('[name="location_at"]').val(gen.document_no); // 1. การรับแจ้งเหตุ - ที่
                                            $('[name="record_date"]').val(gen.document_date || new Date().toISOString().slice(0,10)); // 1. การรับแจ้งเหตุ - ลง
                                            $('[name="investigator_name"]').val(gen.investigator.name); // 1. การรับแจ้งเหตุ - ชื่อพนักงานสอบสวน
                                            $('[name="investigator_phone"]').val(gen.investigator.phone); // 1. การรับแจ้งเหตุ - หมายเลขโทรศัพท์

                                            // 3. ช่องทางการรับแจ้ง (Notify Method)
                                            if (gen.report_channel) {
                                                gen.report_channel.forEach(val => {
                                                    $(`input[name="notify_method[]"][value="${val}"]`).prop('checked', true).trigger('change'); // 1. การรับแจ้งเหตุ - ช่องทางการรับแจ้ง
                                                });
                                                if (gen.report_channel.includes('อื่นๆ')) {
                                                    $('#bomb_notify_other_text').show().prop('disabled', false).val(gen.report_channel_other);
                                                }
                                            }

                                            $('[name="victim_know_date"]').val(gen.victim_know_date); // 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ - วันที่ผู้เสียหายทราบเหตุ/เกิดเหตุ
                                            $('[name="victim_know_time"]').val(gen.victim_know_time); // 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ - เวลาประมาณ
                                            $('[name="officer_know_date"]').val(gen.officer_know_date); // 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ - วันที่พนักงานสอบสวนทราบเหตุ
                                            $('[name="officer_know_time"]').val(gen.officer_know_time); // 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ - เวลาประมาณ
                                            $('[name="inspect_date"]').val(gen.inspect_date); // 4. วันเวลาที่ตรวจเหตุ - วันที่ทำการตรวจสถานที่เกิดเหตุ
                                            $('[name="inspect_time"]').val(gen.inspect_time); // 4. วันเวลาที่ตรวจเหตุ - เวลาประมาณ
                                            $('[name="inspect_additional_date"]').val(gen.inspect_additional_date); // 4. วันเวลาที่ตรวจเหตุ - วันที่ตรวจสถานที่เกิดเหตุเพิ่มเติม
                                            $('[name="inspect_additional_time"]').val(gen.inspect_additional_time); // 4. วันเวลาที่ตรวจเหตุ - เวลาประมาณ

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
                                                    const buildingTypeMap = {
                                                        'อาคารพาณิชย์': 'commercial',
                                                        'บ้านเดี่ยว': 'house',
                                                        'อื่นๆ': 'other',
                                                        'อื่น ๆ': 'other'
                                                    };
                                                    ind.building_type.forEach(t => {
                                                        const mappedType = buildingTypeMap[t] || t;
                                                        $(`input[name="building_type_indoor[]"][value="${mappedType}"]`)
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

                                                    // รองรับฟอร์ม PDF ที่ใช้จำนวนชั้นช่องเดียว
                                                    var floorSingleVal = '';
                                                    if (ind.building_floors.total !== undefined && ind.building_floors.total !== null) {
                                                        floorSingleVal = ind.building_floors.total;
                                                    } else {
                                                        Object.keys(ind.building_floors).some(function(k) {
                                                            var v = ind.building_floors[k];
                                                            if (v !== undefined && v !== null && String(v).trim() !== '') {
                                                                floorSingleVal = v;
                                                                return true;
                                                            }
                                                            return false;
                                                        });
                                                    }
                                                    if (floorSingleVal !== '') {
                                                        $('[name="building_floor_count_indoor"]').val(floorSingleVal);
                                                    }
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
                                            const resData = d.inspection_results || {};
                                            var caseBehaviorVal = resData.case_behavior || res.basic_info || '';
                                            $('[name="case_behavior"]').val(caseBehaviorVal); // 7.ผลการตรวจสถานที่เกิดเหตุ - พฤติการณ์คดี
                                            $('[name="damage_details"]').val(resData.damage_details || ''); // 7.ผลการตรวจสถานที่เกิดเหตุ - ความเสียหาย
                                            $('[name="explosion_point"]').val(resData.explosion_point || ''); // 7.ผลการตรวจสถานที่เกิดเหตุ - ตำแหน่งที่เกิดการระเบิด

                                            // ข้อมูลศพ (Dynamic)
                                            if (resData.bodies && resData.bodies.length > 0) {
                                                resData.bodies.forEach((b, i) => {
                                                    addBodyCardBomb(); // สร้าง Card ใหม่ (จะได้ index ถัดไปเรื่อยๆ)

                                                    // เข้าถึง Card ใบที่ i ที่เพิ่งสร้าง
                                                    const $card = $('#body_container_bomb .body-card-bomb').eq(i);
                                                    const actualIndex = $card.attr('data-index'); // ดึงเลข index ที่ addBodyCardBomb เจนให้

                                                    // 1. ติ๊กสถานะ
                                                    const $targetCheckbox = $card.find(`.js-body-status[value="${b.status}"]`); // 7.ผลการตรวจสถานที่เกิดเหตุ - พบ/ไม่พบศพ
                                                    $targetCheckbox.prop('checked', true);

                                                    const statusKey = (b.status === 'พบศพ') ? 'found' : 'notfound';
                                                    toggleBodyCondition($targetCheckbox.get(0), statusKey);

                                                    // 2. ปลดล็อกและ Fill ค่าตามสถานะจริง (หลัง toggleBodyCondition เพราะ toggle จะ clear ค่า)
                                                    // ★ Fill ชื่อ-สกุลเสมอ ไม่ว่าจะเป็นสถานะใด (ต้องทำหลัง toggle เพราะ toggle clear ค่า)
                                                    $card.find(`.js-body-name`).prop('disabled', false).val(b.name); // 7.ผลการตรวจสถานที่เกิดเหตุ - ชื่อ - นามสกุล (ถ้าทราบ)

                                                    if (b.status === 'พบศพ') {
                                                        $card.find(`.js-body-condition-area`).prop('disabled', false).val(b.condition_detail); // 7.ผลการตรวจสถานที่เกิดเหตุ - สภาพศพ ลักษณะการแต่งกาย ทรัพย์สิน และอื่น ๆ
                                                        $card.find(`.js-body-notfound-text`).hide().prop('disabled', true).val('');
                                                    } else {
                                                        $card.find(`.js-body-notfound-text`).show().prop('disabled', false).val(b.notfound_detail); // 7.ผลการตรวจสถานที่เกิดเหตุ - ไม่พบศพ detail
                                                        // ★ Fill ลักษณะบาดแผลด้วย ถ้ามีข้อมูลอยู่ (กรณีไม่พบศพ แต่มีข้อมูลบาดแผล)
                                                        if (b.condition_detail) {
                                                            $card.find(`.js-body-condition-area`).prop('disabled', false).val(b.condition_detail);
                                                        } else {
                                                            $card.find(`.js-body-condition-area`).prop('disabled', true).val('');
                                                        }
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
                                                    if (contMap[key] === 'อื่นๆ') { $('#bomb_cont_other_text').show().prop('disabled', false).val(bomb.containers.other_text); $('#bomb_cont_other_hw').show(); }
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
                                            const blood = resData.blood_evidence || {};
                                            const $stdForm = $('#incidentCheckListFormBomb');
                                            const hemaResultValue = (blood.hemastix_result === 'ไม่เกิดการเปลี่ยนแปลง')
                                                ? 'ไม่มีการเปลี่ยนแปลง'
                                                : (blood.hemastix_result || '');
                                            const phenolResultValue = (blood.phenol_result === 'ไม่เกิดการเปลี่ยนแปลง')
                                                ? 'ไม่มีการเปลี่ยนแปลง'
                                                : (blood.phenol_result || '');

                                            $stdForm.find('#bomb_evidence_blood').prop('checked', !!blood.is_active).trigger('change'); // เลือก/ไม่เลือก คราบสีแดงคล้ายโลหิต
                                            $stdForm.find('[name="blood_stain_detail"]').val(blood.detail || ''); // detail คราบสีแดงคล้ายโลหิต
                                            $stdForm.find('#bomb_test_hemastix').prop('checked', !!blood.test_hemastix).trigger('change'); // เก็บสถานะว่าเลือกทดสอบด้วย hemastix
                                            $stdForm.find('input[name="hemastix_result"]').prop('checked', false);
                                            if (hemaResultValue) {
                                                $stdForm.find(`input[name="hemastix_result"][value="${hemaResultValue}"]`).prop('checked', true); // ผลจากการทดสอบด้วย hemastix
                                            }
                                            $stdForm.find('#bomb_test_phenol').prop('checked', !!(blood.test_phenol || blood.test_phenolphthalein)).trigger('change'); // เก็บสถานะว่าเลือกทดสอบด้วย phenolphthalein
                                            $stdForm.find('input[name="phenol_result"]').prop('checked', false);
                                            if (phenolResultValue) {
                                                $stdForm.find(`input[name="phenol_result"][value="${phenolResultValue}"]`).prop('checked', true); // ผลจากการทดสอบด้วย phenolphthalein
                                            }

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
                                                $('#measurement_container_bomb').empty();

                                                if (d.measurements && d.measurements.length > 0) {
                                                    d.measurements.forEach((m, i) => {
                                                        addMeasurementCardBomb();
                                                        const $row = $('#measurement_container_bomb .measurement-card-bomb').eq(i);

                                                    // ★ ใช้ name^= (starts-with) เพื่อรองรับทั้ง [] และ [i] หลัง reIndex
                                                    $row.find('input[name^="measurement_item_bomb"]').val(m.item);
                                                    $row.find('input[name^="measurement_quantity_bomb"]').val(m.quantity);
                                                    $row.find('input[name^="measurement_area_bomb"]').val(m.area);
                                                    $row.find('input[name^="measurement_label_number_bomb"]').val(m.label_no);
                                                    $row.find('input[name^="measurement_remark_bomb"]').val(m.remark);
                                                    window.setLabUnits($row.find('[name^="measurement_forensic_unit_bomb"]'), m.forensic_unit);

                                                    if (m.packaging.plastic) {
                                                        $row.find(`[name="measurement_package_plastic_check[${i}]"]`)
                                                            .prop('checked', true)
                                                            .trigger('change');
                                                        $row.find(`[name="measurement_package_plastic_text[${i}]"]`).val(m.packaging.plastic_text);
                                                    }

                                                    if (m.packaging.paper) {
                                                        $row.find(`[name="measurement_package_paper_check[${i}]"]`)
                                                            .prop('checked', true)
                                                            .trigger('change');
                                                        $row.find(`[name="measurement_package_paper_text[${i}]"]`).val(m.packaging.paper_text);
                                                    }

                                                    if (m.packaging.other) {
                                                        $row.find(`[name="measurement_package_other_check[${i}]"]`)
                                                            .prop('checked', true)
                                                            .trigger('change');
                                                        $row.find(`[name="measurement_package_other_text[${i}]"]`).val(m.packaging.other_text);
                                                    }

                                                    if (m.action.return) {
                                                        $row.find(`[name="measurement_action_return_check[${i}]"]`)
                                                            .prop('checked', true)
                                                            .trigger('change');
                                                        $row.find(`[name="measurement_action_return_text[${i}]"]`).val(m.action.return_text);
                                                    }

                                                    if (m.action.other) {
                                                        $row.find(`[name="measurement_action_other_check[${i}]"]`)
                                                            .prop('checked', true)
                                                            .trigger('change');
                                                        $row.find(`[name="measurement_action_other_text[${i}]"]`).val(m.action.other_text);
                                                    }
                                                });
                                            } else {
                                                addMeasurementCardBomb();
                                            }

                                            // 12. Meta ข้อมูลจดบันทึก/ส่งมอบ
                                            const evMeta = d.evidence_found_meta;
                                            $('#reference_point_1_bomb').val(evMeta.ref_1); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 1
                                            $('#reference_point_2_bomb').val(evMeta.ref_2); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 2
                                            $('#reference_point_3_bomb').val(evMeta.ref_3); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 3
                                            $('#reference_point_4_bomb').val(evMeta.ref_4); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - จุดอ้างอิง 4
                                            $('#collector_name_bomb').val(evMeta.collector); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - ผู้จดบันทึก
                                            $('#collection_datetime_bomb').val(evMeta.collect_datetime); // 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ - วัน/เวลา

                                            $('[name="measurement_inspection_date_bomb"]').val(d.measurement_meta.inspection_date || ''); // 12. รายการวัตถุพยาน (บันทึกการตรวจเก็บวัตถุพยาน) - วันที่ตรวจสอบที่เกิดเหตุ
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

                                            // ★ Backward compat: resolve old user_id → fullname for recorder selects
                                            (function() {
                                                var recFields = [
                                                    { name: 'sketch_recorder_bomb', val: (d.sketch_meta || {}).recorder },
                                                    { name: 'collector_name_bomb', val: (d.evidence_found_meta || {}).collector },
                                                    { name: 'measurement_recorder_bomb', val: (d.measurement_meta || {}).recorder },
                                                    { name: 'photographer_name_bomb', val: (d.photo_records || {}).photographer_name }
                                                ];
                                                recFields.forEach(function(f) {
                                                    if (!f.val || !/^\d+$/.test(f.val)) return; // skip if not a numeric user_id
                                                    var $sel = $('[name="' + f.name + '"]');
                                                    if ($sel.val()) return; // already matched
                                                    var $inspOpt = $('.inspector-select-bomb:first option[value="' + f.val + '"]');
                                                    if ($inspOpt.length) {
                                                        $sel.val($inspOpt.text().trim());
                                                    }
                                                });
                                            })();

                                            // ★ ฟังก์ชัน helper สำหรับ populate photo store (ใช้ได้ทั้งกรณี modal shown และไม่ shown)
                                            function _populateBombPhotoStore() {
                                                if (typeof window.attachmentStoreBomb === 'undefined') window.attachmentStoreBomb = [];
                                                window.attachmentStoreBomb.length = 0;
                                                if (typeof window.deletedExistingPhotosBomb === 'undefined') window.deletedExistingPhotosBomb = [];
                                                window.deletedExistingPhotosBomb.length = 0;
                                                if (d.photos && d.photos.length > 0) {
                                                    d.photos.forEach(function(p, idx) {
                                                        if (p.file_id) {
                                                            attachmentStoreBomb.push({
                                                                id: 'existing_' + p.file_id,
                                                                src: './api/incidentCheckList/getFile.php?id=' + p.file_id,
                                                                existing: true,
                                                                isExisting: true,
                                                                db_file_id: p.file_id,
                                                                filename: p.filename || p.name || ('photo_' + (idx + 1) + '.jpg'),
                                                                name: p.filename || p.name || ('photo_' + (idx + 1) + '.jpg'),
                                                                caption: p.caption || ''
                                                            });
                                                        } else if (p.base64) {
                                                            attachmentStoreBomb.push({
                                                                id: 'existing_legacy_' + idx,
                                                                src: p.base64,
                                                                existing: true,
                                                                isExisting: true,
                                                                filename: p.filename || 'photo',
                                                                disk_filename: p.filename || 'photo',
                                                                size: p.size || 'N/A',
                                                                date: p.date || '-'
                                                            });
                                                        }
                                                    });
                                                }
                                            }

                                            if (shouldShowStandardModal) {
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
                                                        const canvas = document.getElementById(item.id);
                                                        const sigData = savedSignatures[item.key];

                                                        if (!pad || !canvas) {
                                                            console.warn(`Missing pad or canvas for ${item.id}`);
                                                            return;
                                                        }

                                                        // ★ BLOB: ใช้ getFile.php?id=N โหลดเป็น Image
                                                        if (sigData && sigData.file_id) {
                                                            try {
                                                                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                                                                if (canvas.offsetWidth > 0) {
                                                                    canvas.width = canvas.offsetWidth * ratio;
                                                                    canvas.height = canvas.offsetHeight * ratio;
                                                                    canvas.getContext("2d").scale(ratio, ratio);
                                                                    pad.clear();
                                                                    const imgUrl = './api/incidentCheckList/getFile.php?id=' + sigData.file_id;
                                                                    const img = new Image();
                                                                    img.crossOrigin = 'anonymous';
                                                                    img.onload = function() {
                                                                        const ctx = canvas.getContext('2d');
                                                                        ctx.drawImage(img, 0, 0, canvas.offsetWidth, canvas.offsetHeight);
                                                                    };
                                                                    img.src = imgUrl;
                                                                }
                                                            } catch (err) {
                                                                console.error("Error loading BLOB signature for " + item.id, err);
                                                            }
                                                        }
                                                        // Legacy: base64 จากข้อมูลเดิม
                                                        else if (sigData && sigData.base64) {
                                                            try {
                                                                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                                                                if (canvas.offsetWidth > 0) {
                                                                    canvas.width = canvas.offsetWidth * ratio;
                                                                    canvas.height = canvas.offsetHeight * ratio;
                                                                    canvas.getContext("2d").scale(ratio, ratio);
                                                                    pad.clear();
                                                                    pad.fromDataURL(sigData.base64);
                                                                }
                                                            } catch (err) {
                                                                console.error("Error drawing signature for " + item.id, err);
                                                            }
                                                        }
                                                    });
                                                }

                                                // 2. โหลดรูปภาพที่แนบ
                                                _populateBombPhotoStore();
                                                renderAttachmentStoreBomb();
                                            }); // close shown.bs.modal
                                            } else {
                                                // ★ showStandardModal: false → โหลด photo store ทันที (ไม่ต้องรอ modal shown)
                                                // Signatures จะถูกโหลดโดย onLoaded callback ใน incidentChecklist.php → PDF canvas โดยตรง
                                                _populateBombPhotoStore();
                                            }
                                            } else {
                                                // ==========================================
                                                // CASE 2: ข้อมูลใหม่ (Add New)
                                                // ==========================================
                                                $form[0].reset();
                                                $form.removeClass('was-validated');

                                                // ล้างคอนเทนเนอร์และรีเซ็ตเลข Index ตัวแปร Global ให้กลับไปที่เริ่มต้น
                                                $('#victim_container_bomb, #inspector_container_bomb, #body_container_bomb, #evidence_container_bomb, #measurement_container_bomb').empty();
                                                
                                                // ★ สร้าง card แรกว่างๆ สำหรับ evidence และ measurement
                                                addEvidenceRowBomb();
                                                addMeasurementCardBomb();

                                                // 1. ดึงข้อมูลพื้นฐานจากหน้าหลักมาใส่ (ใช้ค่าอังกฤษจาก hidden input สำหรับสร้างเอกสาร)
                                                const docNo = $('#doc_no_bomb').val() || $('#receiveNoti_No_bomb').text().trim();
                                                $('[name="case_doc_bomb"]').val(window.toThaiDocNo ? toThaiDocNo(docNo) : docNo);

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
                                                $('[name="measurement_inspection_date_bomb"]').val(dateTimeLocal);
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
                                            if (onLoaded) {
                                                try { onLoaded(res.success ? res.data : null, res); } catch (e) { console.error('onLoaded callback error:', e); }
                                            }
                                            if (shouldShowStandardModal) {
                                                $('#addCheckListModalBomb').modal('show');
                                            }
                                        }).fail(function(xhr, textStatus, errorThrown) {
                                            if (onLoaded) {
                                                try {
                                                    onLoaded(null, {
                                                        success: false,
                                                        error: true,
                                                        textStatus: textStatus || '',
                                                        error: errorThrown || ''
                                                    });
                                                } catch (e) {
                                                    console.error('onLoaded callback error (fail):', e);
                                                }
                                            }
                                            Swal.fire('Error', 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้', 'error');
                                        });
                                    }
                                </script>

                        </div><!-- ปิด div.mb-4.mt-4 ลักษณะสถานที่เกิดเหตุ (section 6 unclosed) -->
                        </div><!-- ปิด div.mb-2 วัตถุพยานที่ตรวจพบ (section 7 unclosed) -->

                            <div class="modal-footer justify-content-end">
                                <button type="button" class="btn btn-success" id="btn_save_bomb" onclick="prepareDataForSubmissionBomb()">
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

<div id="customLightboxBomb" class="d-none" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: zoom-out;" onclick="closeLightboxOutsideBomb(event)">

    <div id="lightboxFileNameBomb" style="position: absolute; top: 20px; left: 20px; color: white; font-size: 1.2rem; font-weight: 300; background: rgba(0,0,0,0.5); padding: 5px 15px; border-radius: 4px; pointer-events: none;"></div>

    <button type="button"
        class="btn-close-lightbox-bomb"
        onclick="closeLightboxBomb()"
        title="ปิดหน้าต่าง">
        &times;
    </button>

    <img id="lightboxImageBomb" sr