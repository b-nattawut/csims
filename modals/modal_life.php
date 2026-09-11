<?php
// ตั้ง timezone เป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// ดึงข้อมูลผู้ตรวจสำหรับ dropdown
$inspectorOptionsLife = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
if (isset($pdo)) {
    $qryInspectorLife = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                     FROM user_profile t1 
                     LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                     ORDER BY t1.user_id DESC";
    $stmtLife = $pdo->query($qryInspectorLife);
    while ($rowLife = $stmtLife->fetch(PDO::FETCH_ASSOC)) {
        $inspectorOptionsLife .= '<option value="' . $rowLife['user_id'] . '">' . htmlspecialchars($rowLife['fullname']) . '</option>';
    }
}

// วันที่ปัจจุบัน (ไทย)
$todayDateLife = date('Y-m-d');
$todayTimeLife = date('H:i');
?>

<!-- Modal สำหรับเรื่อง "ชีวิต" (complaints_type = '02') - ตามฟอร์ม F-CS-09 -->
<div class="modal fade" id="addCheckListModalLife" aria-labelledby="addCheckListModalLifeLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addCheckListModalLifeLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" id="incidentCheckListFormLife" action="./api/incidentCheckList/saveLife.php" novalidate>
                    <input type="hidden" id="receiveNoti_id_life" name="receiveNoti_id">
                    <input type="hidden" id="doc_no_life" name="doc_no">
                    <input type="hidden" id="report_no_life" name="report_no">
                    <!-- PDF-only fields (hidden carriers for sync) -->
                    <input type="hidden" name="entrance_exit_detail">
                    <input type="hidden" name="fight_trace_detail">
                    <input type="hidden" name="body_location_2">
                    <input type="hidden" name="wound_description_2">
                    <input type="hidden" name="photographer_name_life" id="photographer_name_life">
                    <input type="hidden" name="photo_inspect_date_life" id="photo_inspect_date_life">
                    <input type="hidden" name="photo_inspect_time_life" id="photo_inspect_time_life">
                    <input type="hidden" name="photographer_datetime_life" id="photographer_datetime_life">
                    <input type="hidden" name="measurement_inspection_time_life" id="measurement_inspection_time_life">
                    <input type="hidden" name="evidence_label_life[]">

                    <!-- Header เลขที่เอกสาร -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="editInfoLife" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountLife" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto me-3">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="receiveNoti_No_life"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="receiveNotiReportNo_life"></span>
                            </div>
                        </div>
                        <div class="bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2 flex-shrink-0">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToPdfFormLife" style="width: 3rem; height: 1.5rem; cursor: pointer;" onchange="if(this.checked){ this.checked=false; switchToLifePdfForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToPdfFormLife" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
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
                                <input type="text" class="form-control bg-light" id="case_doc_no_life" name="case_doc_no" readonly>
                            </div>
                            <!-- วันที่ (ดึงจากข้อมูล แต่แก้ไขได้) -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">วันที่</label>
                                <input type="date" class="form-control" id="case_date_life" name="case_date" value="<?= $todayDateLife ?>">
                            </div>
                            <!-- เวลา (ให้เลือกเอง) -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" id="case_time_life" name="case_time" value="<?= $todayTimeLife ?>" step="60">
                            </div>
                        </div>

                       

                        <div class="mb-3 mt-2">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">การรับแจ้ง</h6>

                            <label class="form-label">
                                ช่องทางการรับแจ้ง <span class="text-danger">*</span> <small class="fw-normal text-muted">(เลือกได้หลายรายการ)</small>
                            </label>

                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row g-4">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="notify_method[]" value="ทางโทรศัพท์" id="life_notify_phone" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_notify_phone">ทางโทรศัพท์</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="notify_method[]" value="ทางวิทยุสื่อสาร" id="life_notify_radio" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_notify_radio">ทางวิทยุสื่อสาร</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="notify_method[]" value="ทางหนังสือ" id="life_notify_letter" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_notify_letter">ทางหนังสือ</label>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="notify_method[]" value="อื่นๆ" id="life_notify_other" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_notify_other">อื่นๆ</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm" name="notify_method_other_text" id="life_notify_other_text" placeholder="ระบุ" style="width: 200px; display: none;" disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" id="life_notify_other_hw" data-hw-targets="life_notify_other_text" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">สน/สภ. <span class="text-danger">*</span></label>
                                <select id="police_station_life" name="police_station" class="form-select">
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
                                    <input type="text" class="form-control" name="location_at" placeholder="...">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="location_at" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ลง</label>
                                <div class="input-group">
                                    <input type="date" class="form-control" name="record_date" value="<?= date('Y-m-d') ?>">
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
                                    <input type="text" class="form-control" name="investigator_name" placeholder="...">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="investigator_name" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
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
                                <label class="form-label">รายละเอียดสถานที่เกิดเหตุ <span class="text-danger">*</span> <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="crime_location" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" name="crime_location" rows="3" placeholder="..."></textarea>
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary border-bottom pb-2 mb-0 flex-grow-1">ข้อมูลผู้ประสบเหตุ</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addVictimCardLife()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มผู้ประสบเหตุ
                                </button>
                            </div>

                            <!-- Container สำหรับรายการผู้ประสบเหตุ -->
                            <div id="victim_container_life">
                                <!-- รายการที่ 1 (ค่าเริ่มต้น) -->
                                <div class="victim-card-life bg-light p-3 rounded-3 border mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="badge bg-primary">รายการที่ 1</span>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVictimCardLife(this)">
                                            <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                        </button>
                                    </div>

                                    <div class="bg-white p-3 rounded-3 shadow-sm">
                                        <!-- ประเภทผู้ประสบเหตุ -->
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">ประเภทผู้ประสบเหตุ <span class="text-danger">*</span></label>
                                            <select class="form-select" name="victim_type_life[]" onchange="toggleVictimFields(this)">
                                                <option value="" selected>-- เลือกประเภท --</option>
                                                <option value="ผู้เสียชีวิต">ผู้เสียชีวิต</option>
                                                <option value="ผู้บาดเจ็บ">ผู้บาดเจ็บ</option>
                                                <option value="ผู้สูญหาย">ผู้สูญหาย</option>
                                            </select>
                                        </div>

                                        <!-- ข้อมูลรายละเอียด -->
                                        <div class="row g-3">
                                            <div class="col-md-8">
                                                <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="victim_name_life[]" placeholder="ชื่อ-นามสกุล">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">อายุ (ปี)</label>
                                                <input type="text" class="form-control text-center" name="victim_age_life[]" inputmode="numeric" pattern="[0-9]*" maxlength="3" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 3);" placeholder="อายุ">
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
                                <input type="date" class="form-control" name="victim_know_date" value="<?= $todayDateLife ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="victim_know_time" value="<?= $todayTimeLife ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่พนักงานสอบสวนทราบเหตุ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="officer_know_date" value="<?= $todayDateLife ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="officer_know_time" value="<?= $todayTimeLife ?>">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 4. วันเวลาที่ตรวจเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">4. วันเวลาที่ตรวจเหตุ</legend>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ทำการตรวจสถานที่เกิดเหตุ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="inspect_date" value="<?= $todayDateLife ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="inspect_time" value="<?= $todayTimeLife ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ตรวจสถานที่เกิดเหตุเพิ่มเติม <small class="text-muted">(ถ้ามี)</small></label>
                                <input type="date" class="form-control" name="inspect_additional_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" name="inspect_additional_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 5. ผู้ตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">5. ผู้ตรวจสถานที่เกิดเหตุ</legend>

                        <div id="inspector_container_life">
                            <div class="d-flex align-items-center mb-3 inspector-row-life">
                                <div class="text-end pe-3" style="width: 50px;">
                                    <span class="fw-bold text-secondary index-label">5.1</span>
                                </div>
                                <div class="flex-grow-1">
                                    <select class="form-select inspector-select-life" name="inspector_id[]">
                                        <?= $inspectorOptionsLife ?>
                                    </select>
                                </div>
                                <div class="ms-2" style="width: 32px;"></div>
                            </div>
                        </div>

                        <div class="d-flex mt-2">
                            <div style="width: 50px;"></div>
                            <div class="flex-grow-1">
                                <button type="button" class="btn btn-outline-primary border-dashed w-100 py-2" id="btn_add_inspector_life">
                                    <i class="fas fa-plus-circle me-1"></i> เพิ่ม
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
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="scene_preserved" value="มี" id="life_scene_yes" data-group="scene_preserved" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_scene_yes">มีการรักษาสถานที่</label>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="scene_preserved" value="ไม่มี" id="life_scene_no" data-group="scene_preserved" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_scene_no">ไม่มีการรักษาสถานที่</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm" name="scene_preserved_no_text" id="life_scene_no_text" placeholder="ระบุรายละเอียด" style="width: 300px; display: none;" disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" id="life_scene_no_hw" data-hw-targets="life_scene_no_text" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">สภาพแวดล้อม</h6>

                            <!-- แสงสว่าง -->
                            <label class="form-label">แสงสว่าง <small class="text-muted fw-normal ms-1">(เลือกได้หลายรายการ)</small></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3 mt-2">
                                <div class="row g-4">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="lighting[]" value="สว่าง" id="life_light_bright" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_light_bright">สว่าง</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="lighting[]" value="มืด" id="life_light_dark" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_light_dark">มืด</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="lighting[]" value="สลัว" id="life_light_dim" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_light_dim">สลัว</label>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="lighting[]" value="อื่นๆ" id="life_light_other" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_light_other">อื่นๆ</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm" name="lighting_other_text" id="life_light_other_text" placeholder="ระบุ" style="width: 200px; display: none;" disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" id="life_light_other_hw" data-hw-targets="life_light_other_text" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>

                            <!-- อุณหภูมิ -->
                            <label class="form-label">อุณหภูมิ <small class="text-muted fw-normal ms-1">(เลือกได้หลายรายการ)</small></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3 mt-2">
                                <div class="row g-4">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="temperature[]" value="ร้อน" id="life_temp_hot" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_temp_hot">ร้อน</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="temperature[]" value="เย็น" id="life_temp_cold" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_temp_cold">เย็น</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="temperature[]" value="เครื่องปรับอากาศ" id="life_temp_ac" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_temp_ac">เครื่องปรับอากาศ</label>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="temperature[]" value="อื่นๆ" id="life_temp_other" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_temp_other">อื่นๆ</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm" name="temperature_other_text" id="life_temp_other_text" placeholder="ระบุ" style="width: 200px; display: none;" disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" id="life_temp_other_hw" data-hw-targets="life_temp_other_text" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>

                            <!-- กลิ่น -->
                            <label class="form-label">กลิ่น <span class="text-danger">*</span> <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border mt-2">
                                <div class="row">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="smell" value="มี" id="life_smell_yes" data-group="smell" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_smell_yes">มีกลิ่น</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="smell" value="ไม่มี" id="life_smell_no" data-group="smell" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_smell_no">ไม่มีกลิ่น</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">ลักษณะสถานที่เกิดเหตุ</h6>

                            <label class="form-label">ประเภทสถานที่ <span class="text-danger">*</span> <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small></label>

                            <!-- กรณีเกิดเหตุภายนอกอาคาร -->
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3 mt-2">
                                <div class="bg-white p-3 rounded-3 shadow-sm selection-card">
                                    <div class="form-check mb-3 pb-2 border-bottom">
                                        <input class="form-check-input js-master-outdoor-life cursor-pointer" type="checkbox"
                                            name="has_outdoor_incident_life" value="1" id="check_outdoor_main_life" style="transform: scale(1.2);">
                                        <label class="form-check-label fw-bold text-dark fs-6" for="check_outdoor_main_life">
                                            กรณีเกิดเหตุภายนอกอาคาร
                                        </label>
                                    </div>

                                    <div id="outdoor_fields_container_life">
                                    <label class="form-label small text-secondary">ลักษณะพื้นที่:</label>
                                    <div class="row g-4 ms-2">
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="outdoor_type[]" value="ถนน" id="life_outdoor_road" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_outdoor_road">ถนน</label>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="outdoor_type[]" value="สนามหญ้า" id="life_outdoor_lawn" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_outdoor_lawn">สนามหญ้า</label>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="outdoor_type[]" value="ในสวน" id="life_outdoor_garden" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_outdoor_garden">ในสวน</label>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="outdoor_type[]" value="ที่ว่าง" id="life_outdoor_empty" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_outdoor_empty">ที่ว่าง</label>
                                            </div>
                                        </div>
                                        <div class="col-auto d-flex align-items-center gap-2">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="outdoor_type[]" value="อื่นๆ" id="life_outdoor_other" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_outdoor_other">อื่นๆ</label>
                                            </div>
                                            <input type="text" class="form-control form-control-sm" name="outdoor_type_other_text" id="life_outdoor_other_text" placeholder="ระบุ" style="width: 200px; display: none;" disabled>
                                            <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" id="life_outdoor_other_hw" data-hw-targets="life_outdoor_other_text" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>

                                    <!-- สภาพบริเวณโดยรอบ (ภายนอก) -->
                                    <div class="mt-3 pt-3 border-top">
                                        <label class="form-label small text-secondary">เมื่อหันหน้าเข้า</label>
                                        <div class="input-group mb-3">
                                            <input type="text" class="form-control bg-white" name="outdoor_entrance_condition">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="outdoor_entrance_condition" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>

                                        <label class="form-label small text-secondary">บริเวณโดยรอบเมื่อหันหน้าเข้าสถานที่เกิดเหตุ</label>
                                        <div class="row g-3 mt-1">
                                            <div class="col-6 col-md-3">
                                                <label class="form-label small text-muted mb-1">ด้านหน้าติด</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control bg-white" name="outdoor_front_adjacent">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="outdoor_front_adjacent" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <label class="form-label small text-muted mb-1">ด้านซ้ายติด</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control bg-white" name="outdoor_left_adjacent">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="outdoor_left_adjacent" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <label class="form-label small text-muted mb-1">ด้านขวาติด</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control bg-white" name="outdoor_right_adjacent">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="outdoor_right_adjacent" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <label class="form-label small text-muted mb-1">ด้านหลังติด</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control bg-white" name="outdoor_back_adjacent">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="outdoor_back_adjacent" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- บริเวณที่เกิดเหตุ (ภายนอก) -->
                                    <div class="mt-3 pt-3 border-top">
                                        <label class="form-label small text-secondary">บริเวณที่เกิดเหตุ เกิดเหตุที่ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="outdoor_incident_area_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                        <textarea class="form-control bg-white" name="outdoor_incident_area_detail" rows="2" placeholder="..."></textarea>
                                    </div>
                                    </div><!-- /outdoor_fields_container_life -->
                                </div>
                            </div>

                            <!-- กรณีเกิดเหตุภายในอาคาร -->
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3">
                                <div class="bg-white p-3 rounded-3 shadow-sm selection-card">
                                    <div class="form-check mb-3 pb-2 border-bottom">
                                        <input class="form-check-input js-master-indoor-life cursor-pointer" type="checkbox"
                                            name="has_indoor_incident_life" value="1" id="check_indoor_main_life" style="transform: scale(1.2);">
                                        <label class="form-check-label fw-bold text-dark fs-6" for="check_indoor_main_life">
                                            กรณีเกิดเหตุภายในอาคาร
                                        </label>
                                    </div>

                                    <div id="indoor_fields_container_life">
                                    <label class="form-label small text-secondary mb-2">ลักษณะภายนอก:</label>
                                    <div class="row g-4 ms-2 mb-3">
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="building_type[]" value="อาคารพาณิชย์" id="life_bld_commercial" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_bld_commercial">อาคารพาณิชย์</label>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="building_type[]" value="บ้านเดี่ยว" id="life_bld_house" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_bld_house">บ้านเดี่ยว</label>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="building_type[]" value="ทาวน์เฮาส์" id="life_bld_townhouse" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_bld_townhouse">ทาวน์เฮาส์</label>
                                            </div>
                                        </div>
                                        <div class="col-auto d-flex align-items-center gap-2">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="building_type[]" value="อื่นๆ" id="life_bld_other" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_bld_other">อื่นๆ</label>
                                            </div>
                                            <input type="text" class="form-control form-control-sm" name="building_type_other_text" id="life_bld_other_text" placeholder="ระบุ" style="width: 200px; display: none;" disabled>
                                            <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" id="life_bld_other_hw" data-hw-targets="life_bld_other_text" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem; display:none;"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>

                                    <label class="form-label small text-secondary mb-2">สภาพบริเวณโดยรอบ:</label>
                                    <div class="row g-4 ms-2 mb-3">
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="indoor_surrounding" value="มีรั้ว" id="life_indoor_dark" data-group="indoor_surrounding" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_indoor_dark">มีรั้ว</label>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="indoor_surrounding" value="ไม่มีรั้ว" id="life_indoor_notdark" data-group="indoor_surrounding" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_indoor_notdark">ไม่มีรั้ว</label>
                                            </div>
                                        </div>
                                    </div>

                                    <label class="form-label small text-secondary mb-2">ลักษณะภายใน:</label>
                                    <div class="ms-2">
                                        <textarea class="form-control bg-white" name="indoor_interior_detail" rows="3" placeholder="ระบุลักษณะภายในอาคาร..."></textarea>
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open mt-1" data-hw-targets="indoor_interior_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                    </div>
                                    </div><!-- /indoor_fields_container_life -->
                                </div>
                            </div>
                        </div>

                        <div class="mb-4 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">สภาพแวดล้อมและบริเวณโดยรอบ</h6>

                            <div class="mb-3">
                                <label class="form-label">เมื่อหันหน้าเข้า</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="entrance_condition">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="entrance_condition" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>

                            <label class="form-label">บริเวณโดยรอบเมื่อหันหน้าเข้าสถานที่เกิดเหตุ</label>

                            <div class="bg-body-tertiary p-3 rounded-3 border mt-2">
                                <div class="row g-3">
                                    <div class="col-6 col-md-3">
                                        <label class="form-label small text-muted mb-1">ด้านหน้าติด</label>
                                        <div class="input-group">
                                        <input type="text" class="form-control bg-white" name="front_adjacent">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="front_adjacent" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label small text-muted mb-1">ด้านซ้ายติด</label>
                                        <div class="input-group">
                                        <input type="text" class="form-control bg-white" name="left_adjacent">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="left_adjacent" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label small text-muted mb-1">ด้านขวาติด</label>
                                        <div class="input-group">
                                        <input type="text" class="form-control bg-white" name="right_adjacent">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="right_adjacent" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label small text-muted mb-1">ด้านหลังติด</label>
                                        <div class="input-group">
                                        <input type="text" class="form-control bg-white" name="back_adjacent">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="back_adjacent" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- บริเวณที่เกิดเหตุ -->
                        <div class="mb-4 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">บริเวณที่เกิดเหตุ</h6>
                            <div class="mb-3">
                                <label class="form-label">บริเวณที่เกิดเหตุ เกิดเหตุที่ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="incident_area_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" name="incident_area_detail" rows="2" placeholder="..."></textarea>
                            </div>
                        </div>

                        <!-- โครงสร้างบริเวณที่เกิดเหตุ -->
                        <div class="mb-4 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">โครงสร้างบริเวณที่เกิดเหตุ</h6>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">มีขนาดกว้าง x ยาว ประมาณ</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="structure_size" placeholder="เช่น 4x5 เมตร">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_size" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ลักษณะโครงสร้าง</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="structure_type" placeholder="...">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_type" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">ผนัง</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="structure_wall" placeholder="...">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_wall" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">ด้านหน้า</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="structure_front">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_front" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">ด้านซ้าย</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="structure_left">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_left" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">ด้านขวา</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="structure_right">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_right" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">ด้านหลัง</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="structure_back">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_back" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">พื้น</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="structure_floor" placeholder="...">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_floor" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">หลังคา</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="structure_roof" placeholder="...">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_roof" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">การจัดวางสิ่งของ</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="structure_arrangement" placeholder="...">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="structure_arrangement" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 7. ผลการตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">7. ผลการตรวจสถานที่เกิดเหตุ</legend>

                        <!-- พฤติการณ์คดี -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">พฤติการณ์คดี <span class="text-danger">*</span> <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="case_behavior" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                            <textarea class="form-control" name="case_behavior" rows="4" placeholder="ระบุพฤติการณ์โดยสังเขป..."></textarea>
                        </div>

                        <!-- ทางเข้า - ทางออก -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">ทางเข้า - ทางออก <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="entrance_exit" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                            <textarea class="form-control" name="entrance_exit" rows="2" ></textarea>
                        </div>

                        <!-- ร่องรอยการต่อสู้ -->
                        <div class="mb-4">
                            <label class="form-label">ร่องรอยการต่อสู้ <span class="text-danger">*</span> <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="fight_trace" value="มี" id="life_fight_yes" data-group="fight_trace" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_fight_yes">มี</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="fight_trace" value="ไม่มี" id="life_fight_no" data-group="fight_trace" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_fight_no">ไม่มี</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ร่องรอยการรื้อค้น -->
                        <div class="mb-4">
                            <label class="form-label">ร่องรอยการรื้อค้น <span class="text-danger">*</span> <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="search_trace" value="มี" id="life_search_yes" data-group="search_trace" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_search_yes">มี</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="search_trace" value="ไม่มี" id="life_search_no" data-group="search_trace" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_search_no">ไม่มี</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ศพ -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">ข้อมูลศพ</h6>
                            
                            <label class="form-label">สถานะการพบศพ <span class="text-danger">*</span> <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3">
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="body_status" value="พบศพ" id="life_body_found" data-group="body_status" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_body_found">พบศพ</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="body_status" value="ไม่พบศพ" id="life_body_notfound" data-group="body_status" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_body_notfound">ไม่พบศพ</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">ตำแหน่งที่พบศพ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="body_location" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" name="body_location" rows="2" placeholder="..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">สภาพศพ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="body_condition" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" name="body_condition" rows="3" placeholder="..."></textarea>
                            </div>
                        </div>

                        <!-- การแต่งกายและทรัพย์สิน -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">การแต่งกายและทรัพย์สิน</h6>

                            <label class="form-label">รายการเครื่องแต่งกาย <small class="text-muted fw-normal ms-1">(เลือกได้หลายรายการ)</small></label>

                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="bg-white p-2 px-3 rounded-3 shadow-sm d-flex align-items-center gap-2">
                                            <div class="form-check mb-0" style="min-width: 100px;">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="clothing_items[]" value="เสื้อ" id="life_cloth_shirt" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer small fw-bold text-dark" for="life_cloth_shirt">เสื้อ</label>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" name="clothing_shirt" placeholder="รายละเอียด...">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="clothing_shirt" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="bg-white p-2 px-3 rounded-3 shadow-sm d-flex align-items-center gap-2">
                                            <div class="form-check mb-0" style="min-width: 100px;">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="clothing_items[]" value="กางเกง" id="life_cloth_pants" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer small fw-bold text-dark" for="life_cloth_pants">กางเกง</label>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" name="clothing_pants" placeholder="รายละเอียด...">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="clothing_pants" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="bg-white p-2 px-3 rounded-3 shadow-sm d-flex align-items-center gap-2">
                                            <div class="form-check mb-0" style="min-width: 100px;">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="clothing_items[]" value="รองเท้า/ถุงเท้า" id="life_cloth_shoes" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer small fw-bold text-dark" for="life_cloth_shoes">รองเท้า/ถุงเท้า</label>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" name="clothing_shoes" placeholder="รายละเอียด...">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="clothing_shoes" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="bg-white p-2 px-3 rounded-3 shadow-sm d-flex align-items-center gap-2">
                                            <div class="form-check mb-0" style="min-width: 100px;">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="clothing_items[]" value="เครื่องประดับ" id="life_cloth_accessories" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer small fw-bold text-dark" for="life_cloth_accessories">เครื่องประดับ</label>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" name="clothing_accessories" placeholder="รายละเอียด...">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="clothing_accessories" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="bg-white p-2 px-3 rounded-3 shadow-sm d-flex align-items-center gap-2">
                                            <div class="form-check mb-0" style="min-width: 100px;">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="clothing_items[]" value="รอยสักหรือรอยแผลเป็น" id="life_cloth_tattoo" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer small fw-bold text-dark" for="life_cloth_tattoo">รอยสัก/แผลเป็น</label>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" name="clothing_tattoo" placeholder="รายละเอียด...">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="clothing_tattoo" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="bg-white p-2 px-3 rounded-3 shadow-sm d-flex align-items-center gap-2">
                                            <div class="form-check mb-0" style="min-width: 100px;">
                                                <input class="form-check-input cursor-pointer" type="checkbox" name="clothing_items[]" value="อื่นๆ" id="life_cloth_other" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer small fw-bold text-dark" for="life_cloth_other">อื่นๆ</label>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" name="clothing_other" placeholder="รายละเอียด...">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="clothing_other" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- สภาพรอยบาดแผลเบื้องต้น -->
                        <div class="mb-2">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">สภาพรอยบาดแผลเบื้องต้น</h6>

                            <label class="form-label">สถานะรอยบาดแผล <span class="text-danger">*</span> <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small></label>

                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3">
                                <div class="row g-3 mb-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="wound_status" value="ไม่พบรอยบาดแผล" id="life_wound_none" data-group="wound_status" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_wound_none">ไม่พบรอยบาดแผล</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="wound_status" value="พบรอยบาดแผล" id="life_wound_found" data-group="wound_status" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_wound_found">พบรอยบาดแผล</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1">จำนวนรอย</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white text-center" name="wound_count" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                            <span class="input-group-text bg-light">รอย</span>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label small text-muted mb-1">ลักษณะบาดแผลคือ</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="wound_description" placeholder="...">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="wound_description" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">รายละเอียดบาดแผลเพิ่มเติม <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="wound_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" name="wound_detail" rows="3" placeholder="..."></textarea>
                            </div>
                        </div>

                        <!-- วัตถุพยานที่ตรวจพบ -->
                        <div class="mb-2">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">วัตถุพยานที่ตรวจพบ</h6>

                            <!-- คราบสีแดงคล้ายโลหิต -->
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3">
                                <div class="form-check mb-3">
                                    <input class="form-check-input cursor-pointer" type="checkbox" name="evidence_blood_stain" value="1" id="life_evidence_blood" style="transform: scale(1.1);">
                                    <label class="form-check-label cursor-pointer fw-bold text-dark" for="life_evidence_blood">คราบสีแดงคล้ายโลหิต</label>
                                </div>
                                <div class="ms-4">
                                    <label class="form-label small text-muted mb-1">รายละเอียด</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-white" name="blood_stain_detail" placeholder="...">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="blood_stain_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>

                            <!-- ทดสอบด้วยชุดทดสอบคราบโลหิตเบื้องต้น -->
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3">
                                <label class="form-label fw-bold text-dark mb-3">
                                    <i class="fas fa-vial me-1 text-primary"></i>ทดสอบด้วยชุดทดสอบคราบโลหิตเบื้องต้น
                                </label>

                                <!-- Hemastix -->
                                <div class="bg-white p-3 rounded-3 shadow-sm border selection-card mb-3">
                                    <div class="form-check mb-3 pb-2 border-bottom">
                                        <input class="form-check-input cursor-pointer" type="checkbox" name="test_hemastix" value="1" id="life_test_hemastix" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer fw-bold" for="life_test_hemastix">Hemastix</label>
                                    </div>
                                    <div class="bg-body-tertiary p-3 rounded-3 border border-light-subtle">
                                        <label class="form-label small text-secondary fw-bold mb-2">
                                            <i class="fas fa-search me-1 text-primary"></i>ผลการทดสอบ:
                                        </label>
                                        <div class="bg-white p-3 rounded-3 border">
                                            <div class="row g-3 align-items-center">
                                                <div class="col-auto">
                                                    <div class="form-check">
                                                        <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="hemastix_result" value="มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน" id="life_hemastix_positive" data-group="hemastix_result" style="transform: scale(1.1);">
                                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_hemastix_positive">มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน</label>
                                                    </div>
                                                </div>
                                                <div class="col-auto">
                                                    <div class="form-check">
                                                        <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="hemastix_result" value="ไม่มีการเปลี่ยนแปลง" id="life_hemastix_negative" data-group="hemastix_result" style="transform: scale(1.1);">
                                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_hemastix_negative">ไม่มีการเปลี่ยนแปลง</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Phenolphthalein -->
                                <div class="bg-white p-3 rounded-3 shadow-sm border selection-card">
                                    <div class="form-check mb-3 pb-2 border-bottom">
                                        <input class="form-check-input cursor-pointer" type="checkbox" name="test_phenolphthalein" value="1" id="life_test_phenol" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer fw-bold" for="life_test_phenol">Phenolphthalein</label>
                                    </div>
                                    <div class="bg-body-tertiary p-3 rounded-3 border border-light-subtle">
                                        <label class="form-label small text-secondary fw-bold mb-2">
                                            <i class="fas fa-search me-1 text-primary"></i>ผลการทดสอบ:
                                        </label>
                                        <div class="bg-white p-3 rounded-3 border">
                                            <div class="row g-3 align-items-center">
                                                <div class="col-auto">
                                                    <div class="form-check">
                                                        <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="phenol_result" value="มีการเปลี่ยนแปลงเป็นสีชมพูในทันที" id="life_phenol_positive" data-group="phenol_result" style="transform: scale(1.1);">
                                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_phenol_positive">มีการเปลี่ยนแปลงเป็นสีชมพูในทันที</label>
                                                    </div>
                                                </div>
                                                <div class="col-auto">
                                                    <div class="form-check">
                                                        <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" onclick="lifeRadioToggle(this)" name="phenol_result" value="ไม่มีการเปลี่ยนแปลง" id="life_phenol_negative" data-group="phenol_result" style="transform: scale(1.1);">
                                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="life_phenol_negative">ไม่มีการเปลี่ยนแปลง</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- วัตถุพยานอื่นๆ -->
                            <div class="mb-3">
                                <label class="form-label">วัตถุพยานอื่นๆ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="other_evidence" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" name="other_evidence" rows="2" placeholder="..."></textarea>
                            </div>
                        </div>

                        <!-- วัตถุพยานที่ตรวจเก็บ -->
                        <div class="mb-2">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">
                                <i class="fas fa-box-open me-2"></i>วัตถุพยานที่ตรวจเก็บ/การดำเนินการเกี่ยวกับวัตถุพยานเพื่อส่งตรวจพิสูจน์
                            </h6>

                            <!-- วัตถุพยานประเภทอาวุธปืนและเครื่องกระสุน -->
                            <div class="bg-white p-3 rounded-3 shadow-sm border selection-card mb-3">
                                <div class="form-check mb-3 pb-2 border-bottom">
                                    <input class="form-check-input cursor-pointer" type="checkbox" name="collected_evidence[]" value="วัตถุพยานประเภทอาวุธปืนและเครื่องกระสุน" id="life_collect_gun" style="transform: scale(1.1);">
                                    <label class="form-check-label cursor-pointer fw-bold" for="life_collect_gun">วัตถุพยานประเภทอาวุธปืนและเครื่องกระสุน</label>
                                </div>
                                <div>
                                    <label class="form-label small text-muted mb-1">รายละเอียด</label>
                                    <textarea class="form-control bg-light" name="collected_gun_detail" rows="2" placeholder="..."></textarea>
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open mt-1" data-hw-targets="collected_gun_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>

                            <!-- วัตถุพยานประเภทสารพันธุกรรม -->
                            <div class="bg-white p-3 rounded-3 shadow-sm border selection-card mb-3">
                                <div class="form-check mb-3 pb-2 border-bottom">
                                    <input class="form-check-input cursor-pointer" type="checkbox" name="collected_evidence[]" value="วัตถุพยานประเภทสารพันธุกรรม" id="life_collect_dna" style="transform: scale(1.1);">
                                    <label class="form-check-label cursor-pointer fw-bold" for="life_collect_dna">วัตถุพยานประเภทสารพันธุกรรม</label>
                                </div>
                                <div>
                                    <label class="form-label small text-muted mb-1">รายละเอียด</label>
                                    <textarea class="form-control bg-light" name="collected_dna_detail" rows="2" placeholder="..."></textarea>
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open mt-1" data-hw-targets="collected_dna_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>

                            <!-- วัตถุพยานประเภทลายนิ้วมือ -->
                            <div class="bg-white p-3 rounded-3 shadow-sm border selection-card mb-3">
                                <div class="form-check mb-3 pb-2 border-bottom">
                                    <input class="form-check-input cursor-pointer" type="checkbox" name="evidence_fingerprint" value="1" id="life_evidence_fingerprint" style="transform: scale(1.1);">
                                    <label class="form-check-label cursor-pointer fw-bold" for="life_evidence_fingerprint">วัตถุพยานประเภทลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง</label>
                                </div>
                                <div>
                                    <label class="form-label small text-muted mb-1">รายละเอียด</label>
                                    <textarea class="form-control bg-light" name="fingerprint_detail" rows="2" placeholder="..."></textarea>
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open mt-1" data-hw-targets="fingerprint_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>

                            <!-- วัตถุพยานประเภทอื่นๆ -->
                            <div class="bg-white p-3 rounded-3 shadow-sm border selection-card mb-3">
                                <div class="form-check mb-3 pb-2 border-bottom">
                                    <input class="form-check-input cursor-pointer" type="checkbox" name="evidence_other_type" value="1" id="life_evidence_other_type" style="transform: scale(1.1);">
                                    <label class="form-check-label cursor-pointer fw-bold" for="life_evidence_other_type">วัตถุพยานประเภทอื่นๆ</label>
                                </div>
                                <div>
                                    <label class="form-label small text-muted mb-1">รายละเอียด</label>
                                    <textarea class="form-control bg-light" name="other_evidence_type" rows="2" placeholder="..."></textarea>
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open mt-1" data-hw-targets="other_evidence_type" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>

                        </div>

                        <!-- การตรวจสอบครั้งสุดท้าย -->
                        <div class="mb-2">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">การตรวจสอบครั้งสุดท้าย</h6>

                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="form-check mb-2">
                                    <input class="form-check-input cursor-pointer" type="checkbox" name="final_check[]" value="การตรวจสอบครั้งสุดท้าย" id="life_final_check" style="transform: scale(1.1);">
                                    <label class="form-check-label cursor-pointer text-dark" for="life_final_check">การตรวจสอบครั้งสุดท้าย</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input cursor-pointer" type="checkbox" name="final_check[]" value="ตรวจเก็บวัตถุพยานครบถ้วน" id="life_evidence_complete" style="transform: scale(1.1);">
                                    <label class="form-check-label cursor-pointer text-dark" for="life_evidence_complete">ตรวจเก็บวัตถุพยานครบถ้วน</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input cursor-pointer" type="checkbox" name="final_check[]" value="ถ่ายภาพสถานที่เกิดเหตุและดำเนินการส่งมอบสถานที่เกิดเหตุให้แก่พนักงานสอบสวน" id="life_photo_handover" style="transform: scale(1.1);">
                                    <label class="form-check-label cursor-pointer text-dark" for="life_photo_handover">ถ่ายภาพสถานที่เกิดเหตุและดำเนินการส่งมอบสถานที่เกิดเหตุให้แก่พนักงานสอบสวน</label>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- 8. การส่งมอบคืนสถานที่ -->
                    <fieldset class="p-4 bg-white rounded-4 shadow-sm border mb-4">
                        <legend class="fieldset-header">8. การส่งมอบคืนสถานที่</legend>

                        <!-- วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น</label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small text-muted mb-1">วันที่</label>
                                    <input type="date" class="form-control" name="inspection_end_date" value="<?php echo $todayDateLife; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted mb-1">เวลา</label>
                                    <input type="time" class="form-control" name="inspection_end_time" value="<?php echo $todayTimeLife; ?>">
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
                                            <select class="form-select user-select-box-life" id="receiver_name_life" name="receiver_name" data-pos-target="#receiver_position_life">
                                                <?php echo str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้รับมอบ --', $inspectorOptionsLife); ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ตำแหน่ง</label>
                                            <input type="text" class="form-control bg-light" id="receiver_position_life" name="receiver_position" readonly>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small text-muted">ลายเซ็นผู้รับมอบ</label>
                                        <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden" style="height:60px;">
                                            <canvas id="sig-canvas-receiver-life" class="signature-pad w-100 h-100"></canvas>
                                        </div>
                                        <div class="text-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('sig-canvas-receiver-life')">
                                                <i class="fas fa-eraser me-1"></i> ล้าง
                                            </button>
                                        </div>
                                        <input type="hidden" name="receiver_signature_data_life" id="receiver_signature_data_life">
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
                                            <select class="form-select user-select-box-life" id="sender_name_life" name="sender_name" data-pos-target="#sender_position_life">
                                                <?php echo str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งมอบ --', $inspectorOptionsLife); ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ตำแหน่ง</label>
                                            <input type="text" class="form-control bg-light" id="sender_position_life" name="sender_position" readonly>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small text-muted">ลายเซ็นผู้ส่งมอบ</label>
                                        <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden" style="height:60px;">
                                            <canvas id="sig-canvas-sender-life" class="signature-pad w-100 h-100"></canvas>
                                        </div>
                                        <div class="text-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('sig-canvas-sender-life')">
                                                <i class="fas fa-eraser me-1"></i> ล้าง
                                            </button>
                                        </div>
                                        <input type="hidden" name="sender_signature_data_life" id="sender_signature_data_life">
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

                                <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden" style="height:500px;">
                                    <canvas id="scene_sketch_canvas_life" class="signature-pad w-100 h-100"></canvas>

                                    <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none user-select-none">
                                        <div class="text-center">
                                            <i class="fas fa-pencil-alt fa-3x mb-2"></i>
                                            <div>วาดแผนผังที่นี่</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <div></div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button type="button" class="btn btn-sm btn-outline-warning px-3" onclick="sketchUndo('scene_sketch_canvas_life')">
                                            <i class="fas fa-undo me-1"></i> ย้อนกลับ
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning px-3 btn-sketch-eraser" id="scene_sketch_canvas_life_eraser_btn" onclick="sketchToggleEraser('scene_sketch_canvas_life')">
                                            <i class="fas fa-eraser me-1"></i> ยางลบ
                                        </button>
                                        <div class="d-flex align-items-center gap-1">
                                            <i class="fas fa-pen" style="font-size:10px;color:#666;"></i>
                                            <input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize('scene_sketch_canvas_life',this.value);this.nextElementSibling.textContent=this.value+'px'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">
                                            <span style="font-size:11px;color:#666;min-width:30px;">2px</span>
                                        </div>
                                        <label class="btn btn-sm btn-outline-primary px-2 mb-0" title="เลือกสี" style="cursor:pointer;">
                                            <i class="fas fa-palette me-1"></i>
                                            <input type="color" value="#000000" onchange="sketchSetColor('scene_sketch_canvas_life',this.value)" style="width:0;height:0;padding:0;border:0;visibility:hidden;position:absolute;">
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('scene_sketch_canvas_life')">
                                            <i class="fas fa-trash-can me-1"></i> ล้างกระดาน
                                        </button>
                                    </div>
                                </div>

                                <input type="hidden" name="scene_sketch_data_life" id="scene_sketch_data_life">
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="sketch_remark_life" class="form-label">หมายเหตุ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="sketch_remark_life" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" id="sketch_remark_life" name="sketch_remark_life" rows="4"></textarea>
                            </div>
                        </div>

                        <!-- ผู้วัดบันทึก และ วัน/เวลา -->
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label for="sketch_recorder_life" class="form-label">ผู้จดบันทึก</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="sketch_recorder_life" name="sketch_recorder_life" placeholder="ชื่อผู้วัดบันทึก">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="sketch_recorder_life" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="sketch_datetime_life" class="form-label">วัน/เวลา</label>
                                <input type="datetime-local" class="form-control" id="sketch_datetime_life" name="sketch_datetime_life">
                            </div>
                        </div>
                    </fieldset>

                    <!-- fieldset 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">10. วัตถุพยานหรือร่องรอยที่ตรวจพบ</legend>

                        <!-- รายการวัตถุพยาน -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 text-secondary"><i class="fas fa-box me-2"></i>รายการวัตถุพยาน</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addEvidenceRowLife()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                </button>
                            </div>

                            <div id="evidence_container_life">
                                <!-- Card แรก -->
                                <div class="evidence-card-life card mb-3 shadow-sm">
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <!-- แถวที่ 1 -->
                                            <div class="col-12">
                                                <label class="form-label small text-muted">วัตถุพยาน</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="evidence_item_life[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>

                                            <!-- แถวที่ 2: ระยะห่าง (แก้เป็น input text แทน checkbox) -->
                                            <div class="col-12">
                                                <label class="form-label small text-muted">ระยะห่าง (m) จากจุดอ้างอิง</label>
                                                <div class="row g-2">
                                                    <div class="col-3">
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text">1</span>
                                                            <input type="text" class="form-control" name="evidence_level_1_life_0" placeholder="m">
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text">2</span>
                                                            <input type="text" class="form-control" name="evidence_level_2_life_0" placeholder="m">
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text">3</span>
                                                            <input type="text" class="form-control" name="evidence_level_3_life_0" placeholder="m">
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text">4</span>
                                                            <input type="text" class="form-control" name="evidence_level_4_life_0" placeholder="m">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- แถวที่ 3 -->
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">Azimuth (ทิศ/อ้าง/ระยะ)</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="evidence_azimuth_life[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">หมายเหตุ</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="evidence_remark_life[]">
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
                                                </select>
                                                <input type="hidden" class="lab-unit-value" name="evidence_lab_unit_life[]" value="">
                                            </div>

                                            <!-- ปุ่มลบ -->
                                            <div class="col-12">
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEvidenceCardLife(this)">
                                                    <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- จุดอ้างอิง -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="reference_point_1_life" class="form-label">จุดอ้างอิง 1</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="reference_point_1_life" name="reference_point_1_life">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="reference_point_1_life" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="reference_point_2_life" class="form-label">จุดอ้างอิง 2</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="reference_point_2_life" name="reference_point_2_life">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="reference_point_2_life" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="reference_point_3_life" class="form-label">จุดอ้างอิง 3</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="reference_point_3_life" name="reference_point_3_life">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="reference_point_3_life" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="reference_point_4_life" class="form-label">จุดอ้างอิง 4</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="reference_point_4_life" name="reference_point_4_life">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="reference_point_4_life" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>

                        <!-- ผู้จัดเก็บ และ วัน/เวลา -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="collector_name_life" class="form-label">ผู้จัดเก็บ</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="collector_name_life" name="collector_name_life">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="collector_name_life" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="collection_datetime_life" class="form-label">วัน/เวลา</label>
                                <input type="datetime-local" class="form-control" id="collection_datetime_life" name="collection_datetime_life">
                            </div>
                        </div>
                    </fieldset>

                    <!-- fieldset 11. แผนผังร่างกาย (Body Diagram) -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">11. แผนผังร่างกาย</legend>

                        <!-- ข้อมูลผู้เสียชีวิต/ผู้บาดเจ็บ -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-5">
                                <label for="victim_name_life_diagram" class="form-label">ชื่อ-สกุล (ผู้เสียชีวิต/ผู้บาดเจ็บ)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="victim_name_life_diagram" name="victim_name_life_diagram">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="victim_name_life_diagram" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label for="victim_age_life_diagram" class="form-label">อายุ</label>
                                <input type="text" class="form-control" id="victim_age_life_diagram" name="victim_age_life_diagram" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                            </div>
                            <div class="col-md-5">
                                <label for="autopsy_doctor_life" class="form-label">แพทย์ผู้ชันสูตร</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="autopsy_doctor_life" name="autopsy_doctor_life">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="autopsy_doctor_life" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden" style="height:600px;">
                                    <img src="/csims/assets/images/body_diagram.png" alt="Body Diagram" style="position: absolute; top: 0; left: 0; width:100%; height:100%; object-fit: contain; z-index: 1; pointer-events: none;">
                                    <canvas id="body_diagram_canvas_life" class="signature-pad w-100 h-100" style="position: absolute; top: 0; left: 0; z-index: 2; background: transparent;"></canvas>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <div></div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button type="button" class="btn btn-sm btn-outline-warning px-3" onclick="sketchUndo('body_diagram_canvas_life')">
                                            <i class="fas fa-undo me-1"></i> ย้อนกลับ
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning px-3 btn-sketch-eraser" id="body_diagram_canvas_life_eraser_btn" onclick="sketchToggleEraser('body_diagram_canvas_life')">
                                            <i class="fas fa-eraser me-1"></i> ยางลบ
                                        </button>
                                        <div class="d-flex align-items-center gap-1">
                                            <i class="fas fa-pen" style="font-size:10px;color:#666;"></i>
                                            <input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize('body_diagram_canvas_life',this.value);this.nextElementSibling.textContent=this.value+'px'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">
                                            <span style="font-size:11px;color:#666;min-width:30px;">2px</span>
                                        </div>
                                        <label class="btn btn-sm btn-outline-primary px-2 mb-0" title="เลือกสี" style="cursor:pointer;">
                                            <i class="fas fa-palette me-1"></i>
                                            <input type="color" value="#ff0000" onchange="sketchSetColor('body_diagram_canvas_life',this.value)" style="width:0;height:0;padding:0;border:0;visibility:hidden;position:absolute;">
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('body_diagram_canvas_life')">
                                            <i class="fas fa-trash-can me-1"></i> ล้างกระดาน
                                        </button>
                                    </div>
                                </div>

                                <input type="hidden" name="body_diagram_data_life" id="body_diagram_data_life">
                                <input type="hidden" name="body_diagram_strokes_life" id="body_diagram_strokes_life">
                            </div>

                            <div class="col-md-12">
                                <label for="body_diagram_remark_life" class="form-label">หมายเหตุ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="body_diagram_remark_life" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" id="body_diagram_remark_life" name="body_diagram_remark_life" rows="3"></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- fieldset 12. รายการวัตถุพยาน (บันทึกการวัดพบ) -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">12. บันทึกการวัดพบ</legend>

                        <!-- วันที่ตรวจสอบที่เกิดเหตุ -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="measurement_inspection_date_life" class="form-label">วันที่ตรวจสอบที่เกิดเหตุ</label>
                                <input type="datetime-local" class="form-control" id="measurement_inspection_date_life" name="measurement_inspection_date_life">
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 text-secondary"><i class="fas fa-clipboard-list me-2"></i>รายการวัตถุพยาน</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addMeasurementCardLife()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                </button>
                            </div>

                            <div id="measurement_container_life">
                                <!-- Card แรก -->
                                <div class="measurement-card-life card mb-3 shadow-sm">
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <!-- แถวที่ 1: รายการวัตถุพยาน, จำนวน -->
                                            <div class="col-md-8">
                                                <label class="form-label small text-muted">1. รายการวัตถุพยาน</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="measurement_item_life[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">2. จำนวน</label>
                                                <input type="text" class="form-control" name="measurement_quantity_life[]" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                            </div>

                                            <!-- แถวที่ 2: บริเวณที่ตรวจพบ, ป้ายหมายเลข -->
                                            <div class="col-md-8">
                                                <label class="form-label small text-muted">3. บริเวณที่ตรวจพบ</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="measurement_area_life[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">4. ป้ายหมายเลข</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="measurement_label_number_life[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>

                                            <!-- แถวที่ 3: การบรรจุหีบ -->
                                            <div class="col-12">
                                                <label class="form-label small text-muted">5. การบรรจุหีบ</label>
                                                <div class="row g-2">
                                                    <div class="col-md-4">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="measurement_package_plastic_check_0" value="1" onchange="togglePackageInput(this, 'plastic_0')">
                                                                <label class="form-check-label">พลาสติก</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm" name="measurement_package_plastic_text_0" id="package_plastic_0" disabled>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="measurement_package_paper_check_0" value="1" onchange="togglePackageInput(this, 'paper_0')">
                                                                <label class="form-check-label">กระดาษ</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm" name="measurement_package_paper_text_0" id="package_paper_0" disabled>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="measurement_package_other_check_0" value="1" onchange="togglePackageInput(this, 'other_0')">
                                                                <label class="form-check-label">อื่นๆ</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm" name="measurement_package_other_text_0" id="package_other_0" disabled>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- แถวที่ 4: การดำเนินการเกี่ยวกับวัตถุพยาน -->
                                            <div class="col-12">
                                                <label class="form-label small text-muted">6. การดำเนินการเกี่ยวกับวัตถุพยาน</label>
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                                                <input class="form-check-input" type="checkbox" name="measurement_action_return_check_0" value="1" onchange="toggleActionInput(this, 'return_0')">
                                                                <label class="form-check-label">ส่งคืนพงส.</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_return_text_0" id="action_return_0" disabled>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                                                <input class="form-check-input" type="checkbox" name="measurement_action_other_check_0" value="1" onchange="toggleActionInput(this, 'other_action_0')">
                                                                <label class="form-check-label">อื่นๆ</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_other_text_0" id="action_other_action_0" disabled>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- แถวที่ 5: หมายเหตุ -->
                                            <div class="col-md-12">
                                                <label class="form-label small text-muted">7. หมายเหตุ</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="measurement_remark_life[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>

                                            <!-- แถวที่ 6: การตรวจพิสูจน์ -->
                                            <div class="col-md-12">
                                                <label class="form-label small text-muted">8. การตรวจพิสูจน์</label>
                                                <select class="form-select lab-unit-multi" multiple size="4" title="เลือกได้มากกว่า 1 กลุ่มงาน">
                                                    <option value="">-- กรุณาเลือก (เลือกได้หลายข้อ) --</option>
                                                    <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                                    <option value="bio_dna">กลุ่มงานตรวจชีววิทยาและดีเอ็นเอ</option>
                                                    <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                                    <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                                    <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                                    <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                                    <option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                                                </select>
                                                <input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_life[]" value="">
                                            </div>

                                            <!-- ปุ่มลบ -->
                                            <div class="col-12">
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMeasurementCardLife(this)">
                                                    <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ผู้บันทึก และ วัน/เวลา -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="measurement_recorder_life" class="form-label">9. ผู้บันทึก</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="measurement_recorder_life" name="measurement_recorder_life">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="measurement_recorder_life" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="measurement_datetime_life" class="form-label">10. วัน/เวลา</label>
                                <input type="datetime-local" class="form-control" id="measurement_datetime_life" name="measurement_datetime_life">
                            </div>
                        </div>
                    </fieldset>

                    <!-- fieldset 13. บันทึกการถ่ายภาพ -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">13. บันทึกการถ่ายภาพ</legend>

                        <div class="row mb-4">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">รหัสภาพถ่ายที่</label>
                                <input type="text" class="form-control" name="photo_id_start_life" id="photo_id_start_life">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">ถึง</label>
                                <input type="text" class="form-control" name="photo_id_end_life" id="photo_id_end_life">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">จำนวน (ภาพ)</label>
                                <input type="text"
                                    class="form-control"
                                    name="photo_amount_life"
                                    id="photo_amount_life"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                            </div>
                        </div>

                        <!-- Hidden file inputs -->
                        <input type="file" id="incident_photos_life" name="incident_photos_life[]" accept="image/*" style="display: none;" multiple>
                        <input type="file" id="camera_input_life" name="camera_photos_life[]" accept="image/*" capture="environment" style="display: none;" multiple>

                        <!-- Drag & Drop Zone -->
                        <div id="dropzone_life" class="dropzone-life-area">
                            <div class="dropzone-life-content" id="dropzone_life_content">
                                <i class="fas fa-cloud-upload-alt dropzone-life-icon"></i>
                                <p class="dropzone-life-title">ลากไฟล์รูปภาพมาวางที่นี่</p>
                                <p class="dropzone-life-subtitle">หรือคลิกเพื่อเลือกไฟล์ (รองรับหลายไฟล์พร้อมกัน)</p>
                                <div class="d-flex gap-2 justify-content-center mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn_choose_file_life">
                                        <i class="fas fa-folder-open me-1"></i>เลือกไฟล์
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" id="btn_open_camera_life">
                                        <i class="fas fa-camera me-1"></i>เปิดกล้อง
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Progress -->
                        <div id="uploading_container_life" class="mb-3"></div>

                        <!-- Attachments Grid -->
                        <div id="attachments_wrapper_life" class="d-none mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold text-primary mb-0">
                                    <i class="fas fa-images me-1"></i>รูปภาพที่แนบ <span class="badge bg-secondary rounded-pill ms-1" id="file_count_badge_life">0</span>
                                </h6>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="btn_clear_all_photos_life" title="ลบรูปทั้งหมด">
                                    <i class="fas fa-trash-alt me-1"></i>ลบทั้งหมด
                                </button>
                            </div>
                            <div class="row g-2 row-cols-3 row-cols-md-4 row-cols-lg-5" id="attachments_grid_life">
                            </div>
                        </div>

                        <style>
                            .dropzone-life-area {
                                border: 2px dashed #b0bec5;
                                border-radius: 12px;
                                padding: 30px 20px;
                                text-align: center;
                                cursor: pointer;
                                transition: all 0.3s ease;
                                background: #f8f9fa;
                                margin-bottom: 10px;
                            }
                            .dropzone-life-area:hover,
                            .dropzone-life-area.dragover {
                                border-color: #2196F3;
                                background: #e3f2fd;
                            }
                            .dropzone-life-area.dragover {
                                transform: scale(1.01);
                                box-shadow: 0 0 20px rgba(33,150,243,0.2);
                            }
                            .dropzone-life-icon {
                                font-size: 2.5rem;
                                color: #b0bec5;
                                margin-bottom: 10px;
                                transition: color 0.3s ease;
                            }
                            .dropzone-life-area:hover .dropzone-life-icon,
                            .dropzone-life-area.dragover .dropzone-life-icon {
                                color: #2196F3;
                            }
                            .dropzone-life-title {
                                font-size: 0.95rem;
                                font-weight: 600;
                                color: #546e7a;
                                margin-bottom: 4px;
                            }
                            .dropzone-life-subtitle {
                                font-size: 0.8rem;
                                color: #90a4ae;
                                margin-bottom: 0;
                            }
                        </style>
                    </fieldset>

                    <script>
                    let evidenceIndexLife = 1;
                    let measurementIndexLife = 1;
                    let victimIndexLife = 1;

                    // ฟังก์ชันเพิ่มผู้ประสบเหตุ
                    function addVictimCardLife() {
                        victimIndexLife++;
                        const container = document.getElementById('victim_container_life');
                        const newCard = document.createElement('div');
                        newCard.className = 'victim-card-life bg-light p-3 rounded-3 border mb-3';
                        newCard.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-primary">รายการที่ ${victimIndexLife}</span>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVictimCardLife(this)">
                                    <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                </button>
                            </div>

                            <div class="bg-white p-3 rounded-3 shadow-sm">
                                <!-- ประเภทผู้ประสบเหตุ -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">ประเภทผู้ประสบเหตุ <span class="text-danger">*</span></label>
                                    <select class="form-select" name="victim_type_life[]" onchange="toggleVictimFields(this)">
                                        <option value="" selected>-- เลือกประเภท --</option>
                                        <option value="ผู้เสียชีวิต">ผู้เสียชีวิต</option>
                                        <option value="ผู้บาดเจ็บ">ผู้บาดเจ็บ</option>
                                        <option value="ผู้สูญหาย">ผู้สูญหาย</option>
                                    </select>
                                </div>

                                <!-- ข้อมูลรายละเอียด -->
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="victim_name_life[]" placeholder="ชื่อ-นามสกุล">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">อายุ (ปี)</label>
                                        <input type="text" class="form-control text-center" name="victim_age_life[]" inputmode="numeric" pattern="[0-9]*" maxlength="3" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 3);" placeholder="อายุ">
                                    </div>
                                </div>
                            </div>
                        `;
                        container.appendChild(newCard);
                    }

                    // ฟังก์ชันลบผู้ประสบเหตุ
                    function removeVictimCardLife(button) {
                        const container = document.getElementById('victim_container_life');
                        if (container.children.length > 1) {
                            button.closest('.victim-card-life').remove();
                        } else {
                            alert('ต้องมีอย่างน้อย 1 รายการ');
                        }
                    }

                    // ฟังก์ชัน toggle fields (ถ้าต้องการแสดง/ซ่อนฟิลด์ตามประเภท)
                    function toggleVictimFields(select) {
                        // ปัจจุบันไม่มี logic พิเศษ แต่เก็บไว้สำหรับอนาคต
                    }

                    function reIndexEvidenceCardsLife() {
                        const cards = document.querySelectorAll('#evidence_container_life .evidence-card-life');
                        cards.forEach(function(card, index) {
                            const lv1 = card.querySelector('input[name^="evidence_level_1_life_"]');
                            const lv2 = card.querySelector('input[name^="evidence_level_2_life_"]');
                            const lv3 = card.querySelector('input[name^="evidence_level_3_life_"]');
                            const lv4 = card.querySelector('input[name^="evidence_level_4_life_"]');
                            if (lv1) lv1.name = 'evidence_level_1_life_' + index;
                            if (lv2) lv2.name = 'evidence_level_2_life_' + index;
                            if (lv3) lv3.name = 'evidence_level_3_life_' + index;
                            if (lv4) lv4.name = 'evidence_level_4_life_' + index;
                        });
                        evidenceIndexLife = cards.length;
                    }

                    // ฟังก์ชันเพิ่ม card วัตถุพยาน
                    function addEvidenceRowLife() {
                        const container = document.getElementById('evidence_container_life');
                        const newCard = document.createElement('div');
                        newCard.className = 'evidence-card-life card mb-3 shadow-sm';
                        newCard.innerHTML = `
                            <div class="card-body">
                                <div class="row g-3">
                                    <!-- แถวที่ 1 -->
                                    <div class="col-12">
                                        <label class="form-label small text-muted">วัตถุพยาน</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="evidence_item_life[]">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>

                                    <!-- แถวที่ 2: ระยะห่าง -->
                                    <div class="col-12">
                                        <label class="form-label small text-muted">ระยะห่าง (m) จากจุดอ้างอิง</label>
                                        <div class="row g-2">
                                            <div class="col-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">1</span>
                                                    <input type="text" class="form-control" name="evidence_level_1_life_${evidenceIndexLife}" placeholder="m">
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">2</span>
                                                    <input type="text" class="form-control" name="evidence_level_2_life_${evidenceIndexLife}" placeholder="m">
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">3</span>
                                                    <input type="text" class="form-control" name="evidence_level_3_life_${evidenceIndexLife}" placeholder="m">
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">4</span>
                                                    <input type="text" class="form-control" name="evidence_level_4_life_${evidenceIndexLife}" placeholder="m">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- แถวที่ 3 -->
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">Azimuth (ทิศ/อ้าง/ระยะ)</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="evidence_azimuth_life[]">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">หมายเหตุ</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="evidence_remark_life[]">
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
                                        </select>
                                        <input type="hidden" class="lab-unit-value" name="evidence_lab_unit_life[]" value="">
                                    </div>

                                    <!-- ปุ่มลบ -->
                                    <div class="col-12">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEvidenceCardLife(this)">
                                            <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.appendChild(newCard);
                        reIndexEvidenceCardsLife();
                    }

                    // ฟังก์ชันลบ card วัตถุพยาน
                    function removeEvidenceCardLife(button) {
                        const container = document.getElementById('evidence_container_life');
                        if (container.children.length > 1) {
                            button.closest('.evidence-card-life').remove();
                            reIndexEvidenceCardsLife();
                        } else {
                            alert('ต้องมีอย่างน้อย 1 รายการ');
                        }
                    }

                    // ฟังก์ชันเปิด/ปิด textbox เมื่อติ๊ก checkbox การบรรจุหีบ
                    function togglePackageInput(checkbox, inputId) {
                        const input = document.getElementById('package_' + inputId);
                        if (checkbox.checked) {
                            input.disabled = false;
                            input.focus();
                        } else {
                            input.disabled = true;
                            input.value = '';
                        }
                    }

                    // ฟังก์ชันเปิด/ปิด textbox เมื่อติ๊ก checkbox การดำเนินการ
                    function toggleActionInput(checkbox, inputId) {
                        const input = document.getElementById('action_' + inputId);
                        if (checkbox.checked) {
                            input.disabled = false;
                            input.focus();
                        } else {
                            input.disabled = true;
                            input.value = '';
                        }
                    }

                    // ฟังก์ชันเพิ่ม card บันทึกการวัดพบ
                    function addMeasurementCardLife() {
                        const container = document.getElementById('measurement_container_life');
                        const newCard = document.createElement('div');
                        newCard.className = 'measurement-card-life card mb-3 shadow-sm';
                        newCard.innerHTML = `
                            <div class="card-body">
                                <div class="row g-3">
                                    <!-- แถวที่ 1: รายการวัตถุพยาน, จำนวน -->
                                    <div class="col-md-8">
                                        <label class="form-label small text-muted">1. รายการวัตถุพยาน</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="measurement_item_life[]">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">2. จำนวน</label>
                                        <input type="text" class="form-control" name="measurement_quantity_life[]" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                    </div>

                                    <!-- แถวที่ 2: บริเวณที่ตรวจพบ, ป้ายหมายเลข -->
                                    <div class="col-md-8">
                                        <label class="form-label small text-muted">3. บริเวณที่ตรวจพบ</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="measurement_area_life[]">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">4. ป้ายหมายเลข</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="measurement_label_number_life[]">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>

                                    <!-- แถวที่ 3: การบรรจุหีบ -->
                                    <div class="col-12">
                                        <label class="form-label small text-muted">5. การบรรจุหีบ</label>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="measurement_package_plastic_check_${measurementIndexLife}" value="1" onchange="togglePackageInput(this, 'plastic_${measurementIndexLife}')">
                                                        <label class="form-check-label">พลาสติก</label>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm" name="measurement_package_plastic_text_${measurementIndexLife}" id="package_plastic_${measurementIndexLife}" disabled>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="measurement_package_paper_check_${measurementIndexLife}" value="1" onchange="togglePackageInput(this, 'paper_${measurementIndexLife}')">
                                                        <label class="form-check-label">กระดาษ</label>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm" name="measurement_package_paper_text_${measurementIndexLife}" id="package_paper_${measurementIndexLife}" disabled>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="measurement_package_other_check_${measurementIndexLife}" value="1" onchange="togglePackageInput(this, 'other_${measurementIndexLife}')">
                                                        <label class="form-check-label">อื่นๆ</label>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm" name="measurement_package_other_text_${measurementIndexLife}" id="package_other_${measurementIndexLife}" disabled>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- แถวที่ 4: การดำเนินการเกี่ยวกับวัตถุพยาน -->
                                    <div class="col-12">
                                        <label class="form-label small text-muted">6. การดำเนินการเกี่ยวกับวัตถุพยาน</label>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                                        <input class="form-check-input" type="checkbox" name="measurement_action_return_check_${measurementIndexLife}" value="1" onchange="toggleActionInput(this, 'return_${measurementIndexLife}')">
                                                        <label class="form-check-label">ส่งคืนพงส.</label>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_return_text_${measurementIndexLife}" id="action_return_${measurementIndexLife}" disabled>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                                        <input class="form-check-input" type="checkbox" name="measurement_action_other_check_${measurementIndexLife}" value="1" onchange="toggleActionInput(this, 'other_action_${measurementIndexLife}')">
                                                        <label class="form-check-label">อื่นๆ</label>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_other_text_${measurementIndexLife}" id="action_other_action_${measurementIndexLife}" disabled>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- แถวที่ 5: หมายเหตุ -->
                                    <div class="col-md-12">
                                        <label class="form-label small text-muted">7. หมายเหตุ</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="measurement_remark_life[]">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>

                                    <!-- แถวที่ 6: การตรวจพิสูจน์ -->
                                    <div class="col-md-12">
                                        <label class="form-label small text-muted">8. การตรวจพิสูจน์</label>
                                        <select class="form-select lab-unit-multi" multiple size="4" title="เลือกได้มากกว่า 1 กลุ่มงาน">
                                            <option value="">-- กรุณาเลือก (เลือกได้หลายข้อ) --</option>
                                            <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                            <option value="bio_dna">กลุ่มงานตรวจชีววิทยาและดีเอ็นเอ</option>
                                            <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                            <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                            <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                            <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                            <option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                                        </select>
                                        <input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_life[]" value="">
                                    </div>

                                    <!-- ปุ่มลบ -->
                                    <div class="col-12">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMeasurementCardLife(this)">
                                            <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.appendChild(newCard);
                        measurementIndexLife++;
                    }

                    // ฟังก์ชันลบ card บันทึกการวัดพบ
                    function removeMeasurementCardLife(button) {
                        const container = document.getElementById('measurement_container_life');
                        if (container.children.length > 1) {
                            button.closest('.measurement-card-life').remove();
                        } else {
                            alert('ต้องมีอย่างน้อย 1 รายการ');
                        }
                    }

                    // Toggle แสดง/ซ่อนช่อง text สำหรับ "อื่นๆ"
                    document.addEventListener('DOMContentLoaded', function() {
                        // เมื่อเลขที่เอกสารถูกกรอก ให้คัดลอกไปแสดงในช่อง "คดี" ด้วย
                        const receiveNotiNoLife = document.getElementById('receiveNoti_No_life');
                        const caseDocNoLife = document.getElementById('case_doc_no_life');

                        // ใช้ MutationObserver เพื่อตรวจจับการเปลี่ยนแปลงของ receiveNoti_No_life
                        if (receiveNotiNoLife && caseDocNoLife) {
                            const observer = new MutationObserver(function(mutations) {
                                mutations.forEach(function(mutation) {
                                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                                        var raw = receiveNotiNoLife.textContent.trim();
                                        caseDocNoLife.value = (window.toThaiDocNo ? window.toThaiDocNo(raw) : raw);
                                    }
                                });
                            });

                            observer.observe(receiveNotiNoLife, {
                                childList: true,
                                characterData: true,
                                subtree: true
                            });

                            // กรณีที่มีค่าอยู่แล้วตั้งแต่เริ่มต้น
                            if (receiveNotiNoLife.textContent.trim()) {
                                var rawInit = receiveNotiNoLife.textContent.trim();
                                caseDocNoLife.value = (window.toThaiDocNo ? window.toThaiDocNo(rawInit) : rawInit);
                            }
                        }

                        // Toggle "ไม่มีการรักษาสถานที่" text field
                        const sceneNo = document.getElementById('life_scene_no');
                        const sceneNoText = document.getElementById('life_scene_no_text');
                        if (sceneNo && sceneNoText) {
                            sceneNo.addEventListener('change', function() {
                                if (this.checked) {
                                    sceneNoText.style.display = 'block';
                                    sceneNoText.disabled = false;
                                } else {
                                    sceneNoText.style.display = 'none';
                                    sceneNoText.disabled = true;
                                    sceneNoText.value = '';
                                }
                            });
                        }

                        // Toggle "อื่นๆ" text field สำหรับประเภทคดี
                        const caseOther = document.getElementById('life_case_other');
                        const caseOtherText = document.getElementById('life_case_other_text');
                        if (caseOther && caseOtherText) {
                            caseOther.addEventListener('change', function() {
                                caseOtherText.style.display = this.checked ? 'block' : 'none';
                                caseOtherText.disabled = !this.checked;
                                if (!this.checked) caseOtherText.value = '';
                            });
                        }

                        // Enable/Disable ฟิลด์ผู้ประสบเหตุ
                        const victimDead = document.getElementById('life_victim_dead');
                        const victimInjured = document.getElementById('life_victim_injured');
                        const victimMissing = document.getElementById('life_victim_missing');

                        if (victimDead) {
                            victimDead.addEventListener('change', function() {
                                const nameInput = document.querySelector('input[name="dead_name"]');
                                const ageInput = document.querySelector('input[name="dead_age"]');
                                if (this.checked) {
                                    nameInput.disabled = false;
                                    ageInput.disabled = false;
                                } else {
                                    nameInput.disabled = true;
                                    ageInput.disabled = true;
                                    nameInput.value = '';
                                    ageInput.value = '';
                                }
                            });
                        }

                        if (victimInjured) {
                            victimInjured.addEventListener('change', function() {
                                const nameInput = document.querySelector('input[name="injured_name"]');
                                const ageInput = document.querySelector('input[name="injured_age"]');
                                if (this.checked) {
                                    nameInput.disabled = false;
                                    ageInput.disabled = false;
                                } else {
                                    nameInput.disabled = true;
                                    ageInput.disabled = true;
                                    nameInput.value = '';
                                    ageInput.value = '';
                                }
                            });
                        }

                        if (victimMissing) {
                            victimMissing.addEventListener('change', function() {
                                const nameInput = document.querySelector('input[name="missing_name"]');
                                const ageInput = document.querySelector('input[name="missing_age"]');
                                if (this.checked) {
                                    nameInput.disabled = false;
                                    ageInput.disabled = false;
                                } else {
                                    nameInput.disabled = true;
                                    ageInput.disabled = true;
                                    nameInput.value = '';
                                    ageInput.value = '';
                                }
                            });
                        }

                        // ช่องทางการรับแจ้ง - อื่นๆ
                        const notifyOther = document.getElementById('life_notify_other');
                        const notifyOtherText = document.getElementById('life_notify_other_text');
                        if (notifyOther && notifyOtherText) {
                            notifyOther.addEventListener('change', function() {
                                if (this.checked) {
                                    notifyOtherText.style.display = 'block';
                                    notifyOtherText.disabled = false;
                                } else {
                                    notifyOtherText.style.display = 'none';
                                    notifyOtherText.disabled = true;
                                    notifyOtherText.value = '';
                                }
                            });
                        }

                        // แสงสว่าง - อื่นๆ
                        const lightOther = document.getElementById('life_light_other');
                        const lightOtherText = document.getElementById('life_light_other_text');
                        if (lightOther && lightOtherText) {
                            lightOther.addEventListener('change', function() {
                                if (this.checked) {
                                    lightOtherText.style.display = 'block';
                                    lightOtherText.disabled = false;
                                } else {
                                    lightOtherText.style.display = 'none';
                                    lightOtherText.disabled = true;
                                    lightOtherText.value = '';
                                }
                            });
                        }

                        // อุณหภูมิอากาศ - อื่นๆ
                        const tempOther = document.getElementById('life_temp_other');
                        const tempOtherText = document.getElementById('life_temp_other_text');
                        if (tempOther && tempOtherText) {
                            tempOther.addEventListener('change', function() {
                                if (this.checked) {
                                    tempOtherText.style.display = 'block';
                                    tempOtherText.disabled = false;
                                } else {
                                    tempOtherText.style.display = 'none';
                                    tempOtherText.disabled = true;
                                    tempOtherText.value = '';
                                }
                            });
                        }

                        // ประเภทกลางแจ้ง - อื่นๆ
                        const outdoorOther = document.getElementById('life_outdoor_other');
                        const outdoorOtherText = document.getElementById('life_outdoor_other_text');
                        if (outdoorOther && outdoorOtherText) {
                            outdoorOther.addEventListener('change', function() {
                                if (this.checked) {
                                    outdoorOtherText.style.display = 'block';
                                    outdoorOtherText.disabled = false;
                                } else {
                                    outdoorOtherText.style.display = 'none';
                                    outdoorOtherText.disabled = true;
                                    outdoorOtherText.value = '';
                                }
                            });
                        }

                        // ประเภทอาคาร - อื่นๆ
                        const bldOther = document.getElementById('life_bld_other');
                        const bldOtherText = document.getElementById('life_bld_other_text');
                        if (bldOther && bldOtherText) {
                            bldOther.addEventListener('change', function() {
                                if (this.checked) {
                                    bldOtherText.style.display = 'block';
                                    bldOtherText.disabled = false;
                                } else {
                                    bldOtherText.style.display = 'none';
                                    bldOtherText.disabled = true;
                                    bldOtherText.value = '';
                                }
                            });
                        }

                        // ================================================================
                        // จัดการ card ส่วนภายนอกอาคาร (Life) ให้เลือกเมนูใหญ่ก่อน เมนูด้านในถึง enable
                        // ================================================================
                        const $outdoorMasterLife = $('#check_outdoor_main_life');
                        const $outdoorContainerLife = $('#outdoor_fields_container_life');

                        function toggleOutdoorSectionLife() {
                            const isMasterChecked = $outdoorMasterLife.is(':checked');
                            $outdoorContainerLife.find('input, textarea').prop('disabled', !isMasterChecked);
                            if (!isMasterChecked) {
                                $outdoorContainerLife.find('input[type="text"], textarea').val('');
                                $outdoorContainerLife.find('input[type="checkbox"]').prop('checked', false);
                                $('#life_outdoor_other_text').hide();
                            } else {
                                // เช็คเงื่อนไขย่อยของ "อื่นๆ"
                                if ($('#life_outdoor_other').is(':checked')) {
                                    $('#life_outdoor_other_text').show().prop('disabled', false);
                                }
                            }
                        }

                        $outdoorMasterLife.on('change', function() {
                            toggleOutdoorSectionLife();
                        });
                        // สถานะเริ่มต้น: disable ทุก field ภายนอก
                        toggleOutdoorSectionLife();

                        // ================================================================
                        // จัดการ card ส่วนภายในอาคาร (Life) ให้เลือกเมนูใหญ่ก่อน เมนูด้านในถึง enable
                        // ================================================================
                        const $indoorMasterLife = $('#check_indoor_main_life');
                        const $indoorContainerLife = $('#indoor_fields_container_life');

                        function toggleIndoorSectionLife() {
                            const isMasterChecked = $indoorMasterLife.is(':checked');
                            $indoorContainerLife.find('input, textarea').prop('disabled', !isMasterChecked);
                            if (!isMasterChecked) {
                                $indoorContainerLife.find('input[type="text"], textarea').val('');
                                $indoorContainerLife.find('input[type="checkbox"]').prop('checked', false);
                                $('#life_bld_other_text').hide();
                            } else {
                                if ($('#life_bld_other').is(':checked')) {
                                    $('#life_bld_other_text').show().prop('disabled', false);
                                }
                            }
                        }

                        $indoorMasterLife.on('change', function() {
                            toggleIndoorSectionLife();
                        });
                        // สถานะเริ่มต้น: disable ทุก field ภายใน
                        toggleIndoorSectionLife();

                        // ================================================================
                        // จัดการ checkbox ผลทดสอบคราบโลหิต (Life)
                        // ให้เลือก Hemastix/Phenolphthalein ก่อน ผลทดสอบถึงจะ enable
                        // ================================================================
                        const lifeTestHemastix = document.getElementById('life_test_hemastix');
                        const lifeTestPhenol = document.getElementById('life_test_phenol');

                        window.toggleLifeBloodTests = function() {
                            // Hemastix results
                            const isHemaChecked = lifeTestHemastix && lifeTestHemastix.checked;
                            const hemaPos = document.getElementById('life_hemastix_positive');
                            const hemaNeg = document.getElementById('life_hemastix_negative');
                            if (hemaPos) hemaPos.disabled = !isHemaChecked;
                            if (hemaNeg) hemaNeg.disabled = !isHemaChecked;
                            if (!isHemaChecked) {
                                if (hemaPos) hemaPos.checked = false;
                                if (hemaNeg) hemaNeg.checked = false;
                            }

                            // Phenolphthalein results
                            const isPhenolChecked = lifeTestPhenol && lifeTestPhenol.checked;
                            const phenolPos = document.getElementById('life_phenol_positive');
                            const phenolNeg = document.getElementById('life_phenol_negative');
                            if (phenolPos) phenolPos.disabled = !isPhenolChecked;
                            if (phenolNeg) phenolNeg.disabled = !isPhenolChecked;
                            if (!isPhenolChecked) {
                                if (phenolPos) phenolPos.checked = false;
                                if (phenolNeg) phenolNeg.checked = false;
                            }
                        };

                        if (lifeTestHemastix) lifeTestHemastix.addEventListener('change', window.toggleLifeBloodTests);
                        if (lifeTestPhenol) lifeTestPhenol.addEventListener('change', window.toggleLifeBloodTests);

                        // สถานะเริ่มต้น: disable ผลทดสอบจนกว่าจะเลือก Hemastix/Phenolphthalein
                        window.toggleLifeBloodTests();

                        // Initialize canvas เพื่อให้พื้นหลังโปร่งใส
                        const canvasList = [
                            'body_diagram_canvas_life',
                            'scene_sketch_canvas_life',
                            'sig-canvas-receiver-life',
                            'sig-canvas-sender-life'
                        ];

                        canvasList.forEach(function(canvasId) {
                            const canvas = document.getElementById(canvasId);
                            if (canvas) {
                                const ctx = canvas.getContext('2d');
                                // ตั้งค่า canvas ให้มีขนาดเท่ากับ container
                                canvas.width = canvas.offsetWidth || canvas.parentElement.offsetWidth;
                                canvas.height = canvas.offsetHeight || canvas.parentElement.offsetHeight;
                                // Clear canvas และทำให้โปร่งใส
                                ctx.clearRect(0, 0, canvas.width, canvas.height);
                            }
                        });
                    });
                    </script>

                </form>
            </div>
            <!-- ★ Sticky Footer - เห็นตลอดเวลา -->
            <div class="modal-footer justify-content-end" style="position: sticky; bottom: 0; background: #fff; border-top: 1px solid #dee2e6; z-index: 10; padding: 12px 20px;">
                <button type="button" class="btn btn-success" id="btn_save_life" onclick="lpfSaveViaStandardForm()">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger js-close-modal" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>
