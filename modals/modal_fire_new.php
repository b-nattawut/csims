<?php
// ตั้ง timezone เป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// ดึงข้อมูลผู้ตรวจสำหรับ dropdown
$inspectorOptionsFire = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
if (isset($pdo)) {
    $qryInspectorFire = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                     FROM user_profile t1 
                     LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                     ORDER BY t1.user_id DESC";
    $stmtFire = $pdo->query($qryInspectorFire);
    while ($rowFire = $stmtFire->fetch(PDO::FETCH_ASSOC)) {
        $inspectorOptionsFire .= '<option value="' . $rowFire['user_id'] . '">' . htmlspecialchars($rowFire['fullname']) . '</option>';
    }
}

// วันที่ปัจจุบัน (ไทย)
$todayDateFire = date('Y-m-d');
$todayTimeFire = date('H:i');
?>

<!-- Modal สำหรับคดีเพลิงไหม้ (complaints_type = '04') -->
<div class="modal fade" id="addCheckListModalFire" aria-labelledby="addCheckListModalFireLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addCheckListModalFireLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4" style="position:relative;">
                <!-- Loading Overlay -->
                <div id="fireLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track">
                            <div class="csims-bar-fill"></div>
                        </div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>
                <form method="post" id="incidentCheckListFormFire" action="./api/incidentCheckList/saveFire.php" novalidate>
                    <input type="hidden" id="receiveNoti_id_fire" name="receiveNoti_id_fire">
                    <input type="hidden" id="doc_no_fire" name="doc_no_fire">
                    <input type="hidden" id="report_no_fire" name="report_no_fire">

                    <!-- Header เลขที่เอกสาร -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="editInfoFire" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountFire" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto me-3">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="receiveNoti_No_fire"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="receiveNotiReportNo_fire"></span>
                            </div>
                        </div>
                        <div class="bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2 flex-shrink-0">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToPdfFormFire" style="width: 3rem; height: 1.5rem; cursor: pointer;" onchange="if(this.checked){ this.checked=false; switchToFirePdfForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToPdfFormFire" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

                    <!-- ==================== 1. การรับแจ้งเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">1. การรับแจ้งเหตุ</legend>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">คดี</label>
                                <input type="text" class="form-control bg-light" id="case_doc_no_fire" name="case_doc_no_fire" readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">วันที่</label>
                                <input type="date" class="form-control" id="fire_report_date" name="fire_report_date" value="<?= $todayDateFire ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" id="fire_report_time" name="fire_report_time" value="<?= $todayTimeFire ?>" step="60">
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
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fire_notify_method[]" value="ทางโทรศัพท์" id="fire_notify_phone" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fire_notify_phone">ทางโทรศัพท์</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fire_notify_method[]" value="ทางวิทยุสื่อสาร" id="fire_notify_radio" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fire_notify_radio">ทางวิทยุสื่อสาร</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fire_notify_method[]" value="ทางหนังสือ" id="fire_notify_letter" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fire_notify_letter">ทางหนังสือ</label>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fire_notify_method[]" value="อื่นๆ" id="fire_notify_other" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fire_notify_other">อื่นๆ</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm" name="fire_notify_method_other_text" id="fire_notify_other_text" placeholder="ระบุ" style="width: 200px; display: none;" disabled>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-hw-open" data-hw-targets="fire_notify_other_text" id="fire_notify_other_hw" title="เขียนด้วยลายมือ" style="display:none;"><i class="fas fa-pen" style="font-size:0.75rem;"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">สน./สภ. <span class="text-danger">*</span></label>
                                <select id="police_station_fire" name="police_station_fire" class="form-select">
                                    <?php
                                    $qryPS_fire = "SELECT * FROM master_police_station ORDER BY id DESC";
                                    $stmtPS_fire = $pdo->query($qryPS_fire);
                                    ?>
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <?php while ($stationFire = $stmtPS_fire->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . htmlspecialchars($stationFire['station_name']) . '">' . htmlspecialchars($stationFire['station_name']) . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="fire_document_no" name="fire_document_no" placeholder="...">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fire_document_no" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ลง</label>
                                <div class="input-group">
                                    <input type="date" class="form-control" id="fire_document_date" name="fire_document_date" value="<?= date('Y-m-d') ?>">
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
                                    <input type="text" class="form-control" name="fire_investigator_name" id="fire_investigator_name" placeholder="...">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fire_investigator_name" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">หมายเลขโทรศัพท์ <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control person-phone" name="fire_investigator_phone" id="fire_investigator_phone" placeholder="08xxxxxxxx">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 2. สถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">2. สถานที่เกิดเหตุ</legend>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">รายละเอียดสถานที่เกิดเหตุ <span class="text-danger">*</span> <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_incident_location" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" name="fire_incident_location" id="fire_incident_location" rows="3" placeholder="..."></textarea>
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary border-bottom pb-2 mb-0 flex-grow-1">ผู้เสียหาย / ผู้บาดเจ็บ / ผู้เสียชีวิต</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addVictimCardFire()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                </button>
                            </div>

                            <div id="fire_person_container">
                                <!-- รายการที่ 1 (ค่าเริ่มต้น) -->
                                <div class="victim-card-fire bg-light p-3 rounded-3 border mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="badge bg-primary">รายการที่ 1</span>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVictimCardFire(this)">
                                            <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                        </button>
                                    </div>

                                    <div class="bg-white p-3 rounded-3 shadow-sm">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">ประเภท <span class="text-danger">*</span></label>
                                            <select class="form-select" name="fire_person_type[]">
                                                <option value="" selected>-- เลือกประเภท --</option>
                                                <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                                                <option value="ผู้บาดเจ็บ">ผู้บาดเจ็บ</option>
                                                <option value="ผู้เสียชีวิต">ผู้เสียชีวิต</option>
                                            </select>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="fire_person_name[]" placeholder="ชื่อ-นามสกุล">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">อายุ (ปี)</label>
                                                <input type="text" class="form-control text-center" name="fire_person_age[]" inputmode="numeric" pattern="[0-9]*" maxlength="3" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 3);" placeholder="อายุ">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">หมายเหตุ</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="fire_person_remark[]" placeholder="...">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
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
                                <input type="date" class="form-control" name="fire_victim_known_date" value="<?= $todayDateFire ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="fire_victim_known_time" value="<?= $todayTimeFire ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่พนักงานสอบสวนทราบเหตุ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="fire_investigator_known_date" value="<?= $todayDateFire ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="fire_investigator_known_time" value="<?= $todayTimeFire ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">ทราบเหตุ</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="fire_known_detail" id="fire_known_detail" placeholder="รายละเอียดเพิ่มเติม">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fire_known_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 4. วันเวลาที่ตรวจเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">4. วันเวลาตรวจสถานที่เกิดเหตุ</legend>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ทำการตรวจสถานที่เกิดเหตุ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="fire_inspection_date" value="<?= $todayDateFire ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="fire_inspection_time" value="<?= $todayTimeFire ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ตรวจสถานที่เกิดเหตุเพิ่มเติม <small class="text-muted">(ถ้ามี)</small></label>
                                <input type="date" class="form-control" name="fire_inspection_additional_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" name="fire_inspection_additional_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 5. ผู้ตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">5. ผู้ตรวจสถานที่เกิดเหตุ</legend>

                        <div id="inspector_container_fire">
                            <div class="d-flex align-items-center mb-3 inspector-row-fire">
                                <div class="text-end pe-3" style="width: 50px;">
                                    <span class="fw-bold text-secondary index-label">5.1</span>
                                </div>
                                <div class="flex-grow-1">
                                    <select class="form-select inspector-select-fire" name="fire_inspector_id[]">
                                        <?= $inspectorOptionsFire ?>
                                    </select>
                                </div>
                                <div class="ms-2" style="width: 32px;"></div>
                            </div>
                        </div>

                        <div class="d-flex mt-2">
                            <div style="width: 50px;"></div>
                            <div class="flex-grow-1">
                                <button type="button" class="btn btn-outline-primary border-dashed w-100 py-2" id="btn_add_inspector_fire">
                                    <i class="fas fa-plus-circle me-1"></i> เพิ่ม
                                </button>
                            </div>
                            <div class="ms-2" style="width: 32px;"></div>
                        </div>
                    </fieldset>

                    <!-- ==================== 6. ลักษณะของสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">6. ลักษณะของสถานที่เกิดเหตุ</legend>

                        <!-- 6.1 ลักษณะภายนอก -->
                        <div class="mb-4 mt-2">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">6.1 ลักษณะภายนอก</h6>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">รายละเอียดลักษณะภายนอก <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_exterior_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                    <textarea class="form-control" name="fire_exterior_detail" id="fire_exterior_detail" rows="3" placeholder="..."></textarea>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">จำนวนชั้น</label>
                                    <input type="text" class="form-control" name="fire_floor_count" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');" placeholder="ชั้น">
                                </div>
                            </div>

                            <!-- บริเวณโดยรอบ -->
                            <label class="form-label">บริเวณโดยรอบ <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3 mt-2">
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input fire-radio-toggle cursor-pointer" type="checkbox" onclick="fireRadioToggle(this)" name="fire_fence" value="has_fence" id="fire_fence_yes" data-group="fire_fence" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fire_fence_yes">มีรั้ว</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input fire-radio-toggle cursor-pointer" type="checkbox" onclick="fireRadioToggle(this)" name="fire_fence" value="no_fence" id="fire_fence_no" data-group="fire_fence" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fire_fence_no">ไม่มีรั้ว</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- เมื่อหันหน้าเข้า -->
                            <label class="form-label">เมื่อหันหน้าเข้า</label>
                            <div class="bg-body-tertiary p-3 rounded-3 border mt-2">
                                <div class="row g-3">
                                    <div class="col-6 col-md-3">
                                        <label class="form-label small text-muted mb-1">ด้านหน้าติด <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_scene_front" title="เขียนด้วยลายมือ" style="padding:0 4px; font-size:0.65rem;"><i class="fas fa-pen"></i></button></label>
                                        <textarea class="form-control bg-white" name="fire_scene_front" id="fire_scene_front" rows="2"></textarea>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label small text-muted mb-1">ด้านซ้ายติด <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_scene_left" title="เขียนด้วยลายมือ" style="padding:0 4px; font-size:0.65rem;"><i class="fas fa-pen"></i></button></label>
                                        <textarea class="form-control bg-white" name="fire_scene_left" id="fire_scene_left" rows="2"></textarea>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label small text-muted mb-1">ด้านขวาติด <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_scene_right" title="เขียนด้วยลายมือ" style="padding:0 4px; font-size:0.65rem;"><i class="fas fa-pen"></i></button></label>
                                        <textarea class="form-control bg-white" name="fire_scene_right" id="fire_scene_right" rows="2"></textarea>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label small text-muted mb-1">ด้านหลังติด <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_scene_back" title="เขียนด้วยลายมือ" style="padding:0 4px; font-size:0.65rem;"><i class="fas fa-pen"></i></button></label>
                                        <textarea class="form-control bg-white" name="fire_scene_back" id="fire_scene_back" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 6.2 ลักษณะภายใน -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">6.2 ลักษณะภายใน <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_interior_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></h6>
                            <textarea class="form-control" name="fire_interior_detail" id="fire_interior_detail" rows="4" placeholder="ระบุลักษณะภายใน..."></textarea>
                        </div>

                        <!-- 6.3 บริเวณที่เกิดเหตุ -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">6.3 บริเวณที่เกิดเหตุ</h6>

                            <div class="row g-3 mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">รายละเอียดบริเวณที่เกิดเหตุ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_incident_area_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                    <textarea class="form-control" name="fire_incident_area_detail" id="fire_incident_area_detail" rows="3" placeholder="..."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ขนาดกว้าง x ยาว (เมตร)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="fire_area_size" id="fire_area_size" placeholder="เช่น 4x5 เมตร">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fire_area_size" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">เมื่อหันหน้าเข้า...ที่เกิดเหตุ</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="fire_facing_direction" id="fire_facing_direction">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fire_facing_direction" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>

                            <!-- ลักษณะโครงสร้าง -->
                            <div class="mb-3 mt-4">
                                <h6 class="fw-bold text-secondary border-bottom pb-2 mb-3">ลักษณะโครงสร้าง</h6>
                                <div class="bg-body-tertiary p-3 rounded-3 border">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">ฝาผนังหน้า</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control bg-white" name="fire_structure_wall_front">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">ฝาผนังซ้าย</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control bg-white" name="fire_structure_wall_left">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">ฝาผนังขวา</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control bg-white" name="fire_structure_wall_right">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">ฝาผนังหลัง</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control bg-white" name="fire_structure_wall_back">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small text-muted mb-1">พื้นห้อง</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control bg-white" name="fire_structure_floor">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small text-muted mb-1">หลังคา</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control bg-white" name="fire_structure_roof">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small text-muted mb-1">เพดาน</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control bg-white" name="fire_structure_ceiling">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ลักษณะการจัดวางสิ่งของ -->
                            <div class="mb-3 mt-4">
                                <h6 class="fw-bold text-secondary border-bottom pb-2 mb-3">ลักษณะการจัดวางสิ่งของ</h6>
                                <div class="bg-body-tertiary p-3 rounded-3 border">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">สิ่งของชิดฝาผนังหน้า</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control bg-white" name="fire_objects_wall_front">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">สิ่งของชิดฝาผนังซ้าย</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control bg-white" name="fire_objects_wall_left">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">สิ่งของชิดฝาผนังขวา</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control bg-white" name="fire_objects_wall_right">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted mb-1">สิ่งของชิดฝาผนังหลัง</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control bg-white" name="fire_objects_wall_back">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label small text-muted mb-1">บริเวณอื่นๆ</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control bg-white" name="fire_objects_other">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 7. พฤติการณ์คดี & สภาพความเสียหาย ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">7. พฤติการณ์คดี & สภาพความเสียหาย</legend>

                        <!-- พฤติการณ์คดี -->
                        <div class="mb-4 mt-2">
                            <label class="form-label fw-bold text-secondary">พฤติการณ์คดี <span class="text-danger">*</span> <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_case_behavior" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                            <textarea class="form-control" name="fire_case_behavior" id="fire_case_behavior" rows="4" placeholder="ระบุพฤติการณ์โดยสังเขป..."></textarea>
                        </div>

                        <!-- ประกันภัย -->
                        <div class="mb-4">
                            <label class="form-label">ประกันภัย <span class="text-danger">*</span> <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input fire-radio-toggle cursor-pointer" type="checkbox" onclick="fireRadioToggle(this)" name="fire_insurance" value="has_insurance" id="fire_insurance_yes" data-group="fire_insurance" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fire_insurance_yes">มีประกัน</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input fire-radio-toggle cursor-pointer" type="checkbox" onclick="fireRadioToggle(this)" name="fire_insurance" value="no_insurance" id="fire_insurance_no" data-group="fire_insurance" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fire_insurance_no">ไม่มีประกัน</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- เวลาไฟลุกไหม้ -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">เวลาไฟลุกไหม้</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="fire_burn_time" id="fire_burn_time" placeholder="...">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fire_burn_time" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>

                        <!-- การดับไฟ -->
                        <div class="mb-4">
                            <label class="form-label">การดับไฟ <span class="text-danger">*</span> <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-2">
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input fire-radio-toggle cursor-pointer" type="checkbox" onclick="fireRadioToggle(this)" name="fire_extinguish" value="yes" id="fire_extinguish_yes" data-group="fire_extinguish" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fire_extinguish_yes">มี</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input fire-radio-toggle cursor-pointer" type="checkbox" onclick="fireRadioToggle(this)" name="fire_extinguish" value="no" id="fire_extinguish_no" data-group="fire_extinguish" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fire_extinguish_no">ไม่มี</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="input-group" id="fire_extinguish_detail_group" style="display:none;">
                                <input type="text" class="form-control" id="fire_extinguish_yes_detail" name="fire_extinguish_detail" placeholder="รายละเอียดการดับไฟ..." disabled>
                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fire_extinguish_yes_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                        </div>

                        <!-- สภาพความเสียหาย -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">สภาพความเสียหาย ของ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_damage_condition" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                            <textarea class="form-control" name="fire_damage_condition" id="fire_damage_condition" rows="3" placeholder="ระบุสภาพความเสียหาย..."></textarea>
                        </div>

                        <!-- เพลิงลุกลาม -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">เพลิงลุกลาม ไหม้ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_spread_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                            <textarea class="form-control" name="fire_spread_detail" id="fire_spread_detail" rows="3" placeholder="ระบุรายละเอียดเพลิงลุกลาม..."></textarea>
                        </div>

                        <!-- 7.1 สภาพความเสียหายโครงสร้าง -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">7.1 สภาพความเสียหายโครงสร้าง</h6>
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">ฝาผนังหน้า</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="fire_damage_wall_front">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">ฝาผนังซ้าย</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="fire_damage_wall_left">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">ฝาผนังขวา</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="fire_damage_wall_right">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">ฝาผนังหลัง</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="fire_damage_wall_back">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1">พื้นห้อง</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="fire_damage_floor">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1">หลังคา</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="fire_damage_roof">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1">เพดาน</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="fire_damage_ceiling">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 7.2 สภาพความเสียหายของสิ่งของ -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">7.2 สภาพความเสียหายของสิ่งของ</h6>
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">ชิดฝาผนังหน้า</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="fire_damage_obj_front">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">ชิดฝาผนังซ้าย</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="fire_damage_obj_left">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">ชิดฝาผนังขวา</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="fire_damage_obj_right">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">ชิดฝาผนังหลัง</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="fire_damage_obj_back">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1">พื้นห้อง</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="fire_damage_obj_floor">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1">หลังคา</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="fire_damage_obj_roof">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1">เพดาน</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bg-white" name="fire_damage_obj_ceiling">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 7.3 บริเวณที่เกิดเหตุขึ้นก่อน -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">7.3 บริเวณที่เกิดเหตุขึ้นก่อน <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_first_area" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></h6>
                            <textarea class="form-control" name="fire_first_area" id="fire_first_area" rows="3" placeholder="..."></textarea>
                        </div>

                        <!-- 7.4 สภาพแมนสวิตช์ควบคุมไฟฟ้า -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">7.4 สภาพแมนสวิตช์ควบคุมไฟฟ้า <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_switch_condition" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></h6>
                            <textarea class="form-control" name="fire_switch_condition" id="fire_switch_condition" rows="3" placeholder="..."></textarea>
                        </div>

                        <!-- 7.5 สภาพเสียหายบริเวณข้างเคียง -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">7.5 สภาพเสียหายบริเวณข้างเคียง</h6>

                            <label class="form-label">สถานะ <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3">
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input fire-radio-toggle cursor-pointer" type="checkbox" onclick="fireRadioToggle(this)" name="fire_adjacent_damage" value="found" id="fire_adj_found" data-group="fire_adjacent_damage" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fire_adj_found">พบ</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input fire-radio-toggle cursor-pointer" type="checkbox" onclick="fireRadioToggle(this)" name="fire_adjacent_damage" value="not_found" id="fire_adj_not_found" data-group="fire_adjacent_damage" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fire_adj_not_found">ไม่พบ</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <label class="form-label">รายละเอียด <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_adjacent_damage_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                            <textarea class="form-control" name="fire_adjacent_damage_detail" id="fire_adjacent_damage_detail" rows="3" placeholder="รายละเอียด..."></textarea>
                        </div>

                        <!-- 7.6 วัตถุพยานที่ตรวจพบ -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">7.6 วัตถุพยานที่ตรวจพบ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_evidence_found" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></h6>
                            <textarea class="form-control" name="fire_evidence_found" id="fire_evidence_found" rows="4" placeholder="ระบุวัตถุพยานที่ตรวจพบ..."></textarea>
                        </div>


                    </fieldset>

                    <!-- ==================== วัตถุพยานและตำแหน่งที่ตรวจพบ (ตาราง) ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">วัตถุพยานและตำแหน่งที่ตรวจพบ</legend>

                        <!-- รายการวัตถุพยาน -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 text-secondary"><i class="fas fa-box me-2"></i>รายการวัตถุพยาน</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addEvidenceRowFire()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                </button>
                            </div>

                            <div id="evidence_container_fire">
                                <!-- Card แรก -->
                                <div class="evidence-card-fire card mb-3 shadow-sm">
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label small text-muted">วัตถุพยาน</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="evidence_item_fire[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small text-muted">ระยะห่าง (m) จากจุดอ้างอิง</label>
                                                <div class="row g-2">
                                                    <div class="col-3">
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text">1</span>
                                                            <input type="text" class="form-control" name="evidence_level_1_fire_0" placeholder="m">
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text">2</span>
                                                            <input type="text" class="form-control" name="evidence_level_2_fire_0" placeholder="m">
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text">3</span>
                                                            <input type="text" class="form-control" name="evidence_level_3_fire_0" placeholder="m">
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text">4</span>
                                                            <input type="text" class="form-control" name="evidence_level_4_fire_0" placeholder="m">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">Azimuth (ทิศ/อ้าง/ระยะ)</label>
                                                <input type="text" class="form-control" name="evidence_azimuth_fire[]">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">หมายเหตุ</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="evidence_remark_fire[]">
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
                                                <input type="hidden" class="lab-unit-value" name="evidence_lab_unit_fire[]" value="">
                                            </div>
                                            <div class="col-12">
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEvidenceCardFire(this)">
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
                                <label for="reference_point_1_fire" class="form-label">จุดอ้างอิง 1</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="reference_point_1_fire" name="reference_point_1_fire">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="reference_point_1_fire" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="reference_point_2_fire" class="form-label">จุดอ้างอิง 2</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="reference_point_2_fire" name="reference_point_2_fire">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="reference_point_2_fire" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="reference_point_3_fire" class="form-label">จุดอ้างอิง 3</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="reference_point_3_fire" name="reference_point_3_fire">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="reference_point_3_fire" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="reference_point_4_fire" class="form-label">จุดอ้างอิง 4</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="reference_point_4_fire" name="reference_point_4_fire">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="reference_point_4_fire" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>


                    </fieldset>

                    <!-- ==================== บันทึกการตรวจเก็บวัตถุพยาน ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">บันทึกการตรวจเก็บวัตถุพยาน</legend>

                        <!-- วันที่ตรวจสถานที่เกิดเหตุ -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="measurement_inspection_date_fire" class="form-label">วันที่ตรวจสถานที่เกิดเหตุ</label>
                                <input type="datetime-local" class="form-control" id="measurement_inspection_date_fire" name="measurement_inspection_date_fire">
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 text-secondary"><i class="fas fa-clipboard-list me-2"></i>รายการวัตถุพยาน</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addMeasurementCardFire()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                </button>
                            </div>

                            <div id="measurement_container_fire">
                                <!-- Card แรก -->
                                <div class="measurement-card-fire card mb-3 shadow-sm">
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-8">
                                                <label class="form-label small text-muted">1. รายการวัตถุพยาน</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="measurement_item_fire[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">2. จำนวน</label>
                                                <input type="text" class="form-control" name="measurement_quantity_fire[]" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label small text-muted">3. บริเวณที่ตรวจพบ</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="measurement_area_fire[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">4. ป้ายหมายเลข</label>
                                                <input type="text" class="form-control" name="measurement_label_number_fire[]">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small text-muted">5. การบรรจุหีบ</label>
                                                <div class="row g-2">
                                                    <div class="col-md-4">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="measurement_package_plastic_check_fire_0" value="1" onchange="togglePackageInputFire(this, 'plastic_fire_0')">
                                                                <label class="form-check-label">พลาสติก</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm" name="measurement_package_plastic_text_fire_0" id="package_plastic_fire_0" disabled>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="measurement_package_paper_check_fire_0" value="1" onchange="togglePackageInputFire(this, 'paper_fire_0')">
                                                                <label class="form-check-label">กระดาษ</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm" name="measurement_package_paper_text_fire_0" id="package_paper_fire_0" disabled>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="measurement_package_other_check_fire_0" value="1" onchange="togglePackageInputFire(this, 'other_fire_0')">
                                                                <label class="form-check-label">อื่นๆ</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm" name="measurement_package_other_text_fire_0" id="package_other_fire_0" disabled>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small text-muted">6. การดำเนินการเกี่ยวกับวัตถุพยาน</label>
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                                                <input class="form-check-input" type="checkbox" name="measurement_action_return_check_fire_0" value="1" onchange="toggleActionInputFire(this, 'return_fire_0')">
                                                                <label class="form-check-label">ส่งคืนพงส.</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_return_text_fire_0" id="action_return_fire_0" disabled>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                                                <input class="form-check-input" type="checkbox" name="measurement_action_other_check_fire_0" value="1" onchange="toggleActionInputFire(this, 'other_action_fire_0')">
                                                                <label class="form-check-label">อื่นๆ</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_other_text_fire_0" id="action_other_action_fire_0" disabled>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small text-muted">7. หมายเหตุ</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="measurement_remark_fire[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-12">
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
                                                <input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_fire[]" value="">
                                            </div>
                                            <div class="col-12">
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMeasurementCardFire(this)">
                                                    <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                    </fieldset>

                    <!-- ==================== 8. สรุปผลการตรวจ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">8. สรุปผลการตรวจ</legend>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">8.1 บริเวณต้นเพลิงคือ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_origin_area" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                            <textarea class="form-control" name="fire_origin_area" id="fire_origin_area" rows="3" placeholder="ระบุบริเวณต้นเพลิง..."></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">8.2 เชื้อเพลิงที่ทำให้เกิดการลุกไหม้บริเวณต้นเพลิง <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_fuel_source" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                            <textarea class="form-control" name="fire_fuel_source" id="fire_fuel_source" rows="3" placeholder="ระบุเชื้อเพลิง..."></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">8.3 แหล่งความร้อนที่ทำให้เกิดเพลิงไหม้ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_heat_source" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                            <textarea class="form-control" name="fire_heat_source" id="fire_heat_source" rows="3" placeholder="ระบุแหล่งความร้อน..."></textarea>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold text-secondary">8.4 อื่นๆ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_summary_other" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                            <textarea class="form-control" name="fire_summary_other" id="fire_summary_other" rows="3" placeholder="..."></textarea>
                        </div>
                    </fieldset>

                    <!-- ==================== 9. ความเห็น ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">9. ความเห็น</legend>

                        <div class="mb-4">
                            <label class="form-label">จากการตรวจสถานที่เกิดเหตุเชื่อว่า เพลิงลุกไหม้ขึ้นก่อนบริเวณ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_opinion_first_area" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                            <textarea class="form-control" name="fire_opinion_first_area" id="fire_opinion_first_area" rows="3" placeholder="ระบุบริเวณ..."></textarea>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold text-secondary">สาเหตุของการเกิดเพลิงไหม้ครั้งนี้ <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small></label>

                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="mb-3">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input fire-radio-toggle cursor-pointer" type="checkbox" onclick="fireRadioToggle(this)" name="fire_cause_type" value="believed" id="fire_cause_believed" data-group="fire_cause_type" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="fire_cause_believed">น่าเชื่อว่าเกิดจาก</label>
                                    </div>
                                    <div class="d-flex align-items-center ms-4 gap-2" id="fire_cause_believed_group" style="width: calc(100% - 2rem); display: none;">
                                        <input type="text" class="form-control" name="fire_cause_believed_detail" id="fire_cause_believed_detail" placeholder="ระบุสาเหตุ..." disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" data-hw-targets="fire_cause_believed_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>

                                <div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input fire-radio-toggle cursor-pointer" type="checkbox" onclick="fireRadioToggle(this)" name="fire_cause_type" value="unknown" id="fire_cause_unknown" data-group="fire_cause_type" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer ms-1 text-dark" for="fire_cause_unknown">ไม่สามารถระบุให้ชัดได้ เนื่องจาก</label>
                                    </div>
                                    <div class="d-flex align-items-center ms-4 gap-2" id="fire_cause_unknown_group" style="width: calc(100% - 2rem); display: none;">
                                        <input type="text" class="form-control" name="fire_cause_unknown_detail" id="fire_cause_unknown_detail" placeholder="ระบุเหตุผล..." disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" data-hw-targets="fire_cause_unknown_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 10. แผนผังที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">10. แผนผังที่เกิดเหตุ</legend>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden" style="height: 700px;">
                                    <canvas id="scene_sketch_canvas_fire" class="signature-pad"></canvas>
                                    <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none user-select-none">
                                        <div class="text-center">
                                            <i class="fas fa-pencil-alt fa-3x mb-2"></i>
                                            <div>วาดแผนผังที่นี่</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="text-danger fw-bold small">* NOT TO SCALE</span>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button type="button" class="btn btn-sm btn-outline-warning px-3" onclick="sketchUndo('scene_sketch_canvas_fire')">
                                            <i class="fas fa-undo me-1"></i> ย้อนกลับ
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning px-3 btn-sketch-eraser" id="scene_sketch_canvas_fire_eraser_btn" data-canvas="scene_sketch_canvas_fire" onclick="sketchToggleEraser('scene_sketch_canvas_fire')">
                                            <i class="fas fa-eraser me-1"></i> ยางลบ
                                        </button>
                                        <div class="d-flex align-items-center gap-1">
                                            <i class="fas fa-pen" style="font-size:10px;color:#666;"></i>
                                            <input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize('scene_sketch_canvas_fire',this.value);this.nextElementSibling.textContent=this.value+'px'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">
                                            <span style="font-size:11px;color:#666;min-width:30px;">2px</span>
                                        </div>
                                        <label class="btn btn-sm btn-outline-primary px-2 mb-0" title="เลือกสี" style="cursor:pointer;">
                                            <i class="fas fa-palette me-1"></i>
                                            <input type="color" value="#000000" onchange="sketchSetColor('scene_sketch_canvas_fire',this.value)" style="width:0;height:0;padding:0;border:0;visibility:hidden;position:absolute;">
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('scene_sketch_canvas_fire')">
                                            <i class="fas fa-trash-can me-1"></i> ล้างกระดาน
                                        </button>
                                    </div>
                                </div>
                                <input type="hidden" name="scene_sketch_data_fire" id="scene_sketch_data_fire">
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="fire_sketch_remark" class="form-label">หมายเหตุ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_sketch_remark" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" id="fire_sketch_remark" name="fire_sketch_remark" rows="4"></textarea>
                            </div>
                        </div>

                        <!-- ผู้จดบันทึก และ วัน/เวลา -->
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label">ผู้จดบันทึก</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="fire_sketch_recorder" id="fire_sketch_recorder" placeholder="ชื่อผู้จดบันทึก">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fire_sketch_recorder" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">วัน/เวลา</label>
                                <input type="datetime-local" class="form-control" name="fire_sketch_datetime">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 11. บันทึกการถ่ายภาพ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">11. บันทึกการถ่ายภาพ</legend>

                        <div class="row mb-4">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">รหัสภาพถ่ายที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="photo_id_start_fire" id="photo_id_start_fire">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="photo_id_start_fire" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">ถึง</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="photo_id_end_fire" id="photo_id_end_fire">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="photo_id_end_fire" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">จำนวน (ภาพ)</label>
                                <input type="text" class="form-control" name="photo_amount_fire" id="photo_amount_fire"
                                    inputmode="numeric" pattern="[0-9]*"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <!-- Drag & Drop Zone -->
                                <div id="dropzone_fire" class="border border-2 border-dashed rounded-3 p-4 text-center mb-3" style="border-color: #b0bec5; cursor: pointer; background: #f8f9fa; transition: all 0.2s;">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                    <div class="text-muted small">ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือก</div>
                                </div>
                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="button" class="btn btn-primary" id="btn_choose_file_fire">
                                        <i class="fas fa-folder-open me-2"></i>เลือกไฟล์รูปภาพ
                                    </button>
                                    <button type="button" class="btn btn-success" id="btn_open_camera_fire">
                                        <i class="fas fa-camera me-2"></i>เปิดกล้องถ่ายภาพ
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">

                                <!-- Input สำหรับเลือกไฟล์จาก Gallery -->
                                <input type="file" id="incident_photos_fire" name="incident_photos_fire[]" accept="image/*" style="display: none;" multiple>

                                <!-- Input สำหรับเปิดกล้องถ่ายภาพ -->
                                <input type="file" id="camera_input_fire" name="camera_photos_fire[]" accept="image/*" capture="environment" style="display: none;" multiple>

                                <div id="uploading_container_fire" class="mb-4"></div>

                                <div id="attachments_wrapper_fire" class="d-none mt-4">
                                    <h6 class="fw-bold text-secondary mb-3">
                                        Attachments <span class="badge bg-secondary rounded-pill ms-1" id="file_count_badge_fire">0</span>
                                    </h6>
                                    <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-3" id="attachments_grid_fire"></div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 12. การส่งมอบคืนสถานที่ ==================== -->
                    <fieldset class="p-4 bg-white rounded-4 shadow-sm border mb-4">
                        <legend class="fieldset-header">12. การส่งมอบคืนสถานที่</legend>

                        <!-- วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น</label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small text-muted mb-1">วันที่</label>
                                    <input type="date" class="form-control" name="fire_inspection_end_date" id="fire_inspection_end_date" value="<?php echo $todayDateFire; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted mb-1">เวลา</label>
                                    <input type="time" class="form-control" name="fire_inspection_end_time" id="fire_inspection_end_time" value="<?php echo $todayTimeFire; ?>">
                                </div>
                            </div>
                        </div>

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
                                            <select class="form-select user-select-box-fire" id="receiver_name_fire" name="receiver_name_fire" data-pos-target="#receiver_position_fire">
                                                <?php echo str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้รับมอบ --', $inspectorOptionsFire); ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ตำแหน่ง</label>
                                            <input type="text" class="form-control bg-light" id="receiver_position_fire" name="receiver_position_fire" readonly>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small text-muted">ลายเซ็นผู้รับมอบ</label>
                                        <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden w-100" style="height: 180px;">
                                            <canvas id="sig-canvas-receiver-fire" class="signature-pad w-100 h-100"></canvas>
                                            <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none">
                                                <i class="fas fa-signature fa-2x mb-1 d-block text-center"></i> เซ็นชื่อที่นี่
                                            </div>
                                        </div>
                                        <div class="text-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('sig-canvas-receiver-fire')">
                                                <i class="fas fa-eraser me-1"></i> ล้าง
                                            </button>
                                        </div>
                                        <input type="hidden" name="receiver_signature_data_fire" id="receiver_signature_data_fire">
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
                                            <select class="form-select user-select-box-fire" id="sender_name_fire" name="sender_name_fire" data-pos-target="#sender_position_fire">
                                                <?php echo str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งมอบ --', $inspectorOptionsFire); ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ตำแหน่ง</label>
                                            <input type="text" class="form-control bg-light" id="sender_position_fire" name="sender_position_fire" readonly>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small text-muted">ลายเซ็นผู้ส่งมอบ</label>
                                        <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden w-100" style="height: 180px;">
                                            <canvas id="sig-canvas-sender-fire" class="signature-pad w-100 h-100"></canvas>
                                            <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none">
                                                <i class="fas fa-signature fa-2x mb-1 d-block text-center"></i> เซ็นชื่อที่นี่
                                            </div>
                                        </div>
                                        <div class="text-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('sig-canvas-sender-fire')">
                                                <i class="fas fa-eraser me-1"></i> ล้าง
                                            </button>
                                        </div>
                                        <input type="hidden" name="sender_signature_data_fire" id="sender_signature_data_fire">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== หมายเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">หมายเหตุ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fire_remark" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></legend>
                        <textarea class="form-control" name="fire_remark" id="fire_remark" rows="4" placeholder="หมายเหตุเพิ่มเติม..."></textarea>
                    </fieldset>

                    <!-- ==================== ข้อมูลผู้บันทึก ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">ข้อมูลผู้บันทึก</legend>
                        <p class="text-muted small mb-3">ข้อมูลนี้จะแสดงในส่วน "ผู้จดบันทึก" และ "วัน/เวลา" ของทุกหน้า</p>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ผู้บันทึก</label>
                                <select class="form-select user-select-box-fire" id="recorder_name_fire" name="recorder_name_fire">
                                    <?php echo str_replace('-- เลือกผู้ตรวจ --', '-- เลือก --', $inspectorOptionsFire); ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่บันทึก</label>
                                <input type="datetime-local" class="form-control" id="recorder_datetime_fire" name="recorder_datetime_fire">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ผู้ถ่ายภาพ</label>
                                <select class="form-select user-select-box-fire" id="photographer_name_fire" name="photographer_name_fire">
                                    <?php echo str_replace('-- เลือกผู้ตรวจ --', '-- เลือก --', $inspectorOptionsFire); ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ถ่ายภาพ</label>
                                <input type="datetime-local" class="form-control" id="photographer_datetime_fire" name="photographer_datetime_fire">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">พนักงานสอบสวน</label>
                                <select class="form-select user-select-box-fire" id="fire_investigator_select" name="fire_investigator_select">
                                    <?php echo str_replace('-- เลือกผู้ตรวจ --', '-- เลือก --', $inspectorOptionsFire); ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">หมายเลขโทรศัพท์</label>
                                <input type="tel" class="form-control" id="fire_investigator_phone_recorder" name="fire_investigator_phone_recorder" maxlength="12" placeholder="000-000-0000">
                            </div>
                        </div>
                    </fieldset>

                    <script>
                    let fireVictimIndex = 1;

                    // ฟังก์ชันเพิ่มผู้ประสบเหตุ (Fire)
                    var evidenceIndexFire = 1;
                    var measurementIndexFire = 1;

                    function reIndexEvidenceCardsFire() {
                        const cards = document.querySelectorAll('#evidence_container_fire .evidence-card-fire');
                        cards.forEach(function(card, index) {
                            const lv1 = card.querySelector('input[name^="evidence_level_1_fire_"]');
                            const lv2 = card.querySelector('input[name^="evidence_level_2_fire_"]');
                            const lv3 = card.querySelector('input[name^="evidence_level_3_fire_"]');
                            const lv4 = card.querySelector('input[name^="evidence_level_4_fire_"]');
                            if (lv1) lv1.name = 'evidence_level_1_fire_' + index;
                            if (lv2) lv2.name = 'evidence_level_2_fire_' + index;
                            if (lv3) lv3.name = 'evidence_level_3_fire_' + index;
                            if (lv4) lv4.name = 'evidence_level_4_fire_' + index;
                        });
                        evidenceIndexFire = cards.length;
                    }

                    // ฟังก์ชันเพิ่ม card วัตถุพยาน (Fire)
                    function addEvidenceRowFire() {
                        const container = document.getElementById('evidence_container_fire');
                        const newCard = document.createElement('div');
                        newCard.className = 'evidence-card-fire card mb-3 shadow-sm';
                        newCard.innerHTML = `
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label small text-muted">วัตถุพยาน</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="evidence_item_fire[]">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small text-muted">ระยะห่าง (m) จากจุดอ้างอิง</label>
                                        <div class="row g-2">
                                            <div class="col-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">1</span>
                                                    <input type="text" class="form-control" name="evidence_level_1_fire_${evidenceIndexFire}" placeholder="m">
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">2</span>
                                                    <input type="text" class="form-control" name="evidence_level_2_fire_${evidenceIndexFire}" placeholder="m">
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">3</span>
                                                    <input type="text" class="form-control" name="evidence_level_3_fire_${evidenceIndexFire}" placeholder="m">
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">4</span>
                                                    <input type="text" class="form-control" name="evidence_level_4_fire_${evidenceIndexFire}" placeholder="m">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">Azimuth (ทิศ/อ้าง/ระยะ)</label>
                                        <input type="text" class="form-control" name="evidence_azimuth_fire[]">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">หมายเหตุ</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="evidence_remark_fire[]">
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
                                        <input type="hidden" class="lab-unit-value" name="evidence_lab_unit_fire[]" value="">
                                    </div>
                                    <div class="col-12">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEvidenceCardFire(this)">
                                            <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.appendChild(newCard);
                        reIndexEvidenceCardsFire();
                    }

                    // ฟังก์ชันลบ card วัตถุพยาน (Fire)
                    function removeEvidenceCardFire(button) {
                        const container = document.getElementById('evidence_container_fire');
                        if (container.children.length > 1) {
                            button.closest('.evidence-card-fire').remove();
                            reIndexEvidenceCardsFire();
                        } else {
                            alert('ต้องมีอย่างน้อย 1 รายการ');
                        }
                    }

                    // ฟังก์ชันเปิด/ปิด textbox การบรรจุหีบ (Fire)
                    function togglePackageInputFire(checkbox, inputId) {
                        const input = document.getElementById('package_' + inputId);
                        if (checkbox.checked) {
                            input.disabled = false;
                            input.focus();
                        } else {
                            input.disabled = true;
                            input.value = '';
                        }
                    }

                    // ฟังก์ชันเปิด/ปิด textbox การดำเนินการ (Fire)
                    function toggleActionInputFire(checkbox, inputId) {
                        const input = document.getElementById('action_' + inputId);
                        if (checkbox.checked) {
                            input.disabled = false;
                            input.focus();
                        } else {
                            input.disabled = true;
                            input.value = '';
                        }
                    }

                    // ฟังก์ชันเพิ่ม card บันทึกการตรวจเก็บวัตถุพยาน (Fire)
                    function addMeasurementCardFire() {
                        const container = document.getElementById('measurement_container_fire');
                        const newCard = document.createElement('div');
                        newCard.className = 'measurement-card-fire card mb-3 shadow-sm';
                        newCard.innerHTML = `
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label small text-muted">1. รายการวัตถุพยาน</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="measurement_item_fire[]">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">2. จำนวน</label>
                                        <input type="text" class="form-control" name="measurement_quantity_fire[]" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label small text-muted">3. บริเวณที่ตรวจพบ</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="measurement_area_fire[]">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">4. ป้ายหมายเลข</label>
                                        <input type="text" class="form-control" name="measurement_label_number_fire[]">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small text-muted">5. การบรรจุหีบ</label>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="measurement_package_plastic_check_fire_${measurementIndexFire}" value="1" onchange="togglePackageInputFire(this, 'plastic_fire_${measurementIndexFire}')">
                                                        <label class="form-check-label">พลาสติก</label>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm" name="measurement_package_plastic_text_fire_${measurementIndexFire}" id="package_plastic_fire_${measurementIndexFire}" disabled>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="measurement_package_paper_check_fire_${measurementIndexFire}" value="1" onchange="togglePackageInputFire(this, 'paper_fire_${measurementIndexFire}')">
                                                        <label class="form-check-label">กระดาษ</label>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm" name="measurement_package_paper_text_fire_${measurementIndexFire}" id="package_paper_fire_${measurementIndexFire}" disabled>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="measurement_package_other_check_fire_${measurementIndexFire}" value="1" onchange="togglePackageInputFire(this, 'other_fire_${measurementIndexFire}')">
                                                        <label class="form-check-label">อื่นๆ</label>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm" name="measurement_package_other_text_fire_${measurementIndexFire}" id="package_other_fire_${measurementIndexFire}" disabled>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small text-muted">6. การดำเนินการเกี่ยวกับวัตถุพยาน</label>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                                        <input class="form-check-input" type="checkbox" name="measurement_action_return_check_fire_${measurementIndexFire}" value="1" onchange="toggleActionInputFire(this, 'return_fire_${measurementIndexFire}')">
                                                        <label class="form-check-label">ส่งคืนพงส.</label>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_return_text_fire_${measurementIndexFire}" id="action_return_fire_${measurementIndexFire}" disabled>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                                        <input class="form-check-input" type="checkbox" name="measurement_action_other_check_fire_${measurementIndexFire}" value="1" onchange="toggleActionInputFire(this, 'other_action_fire_${measurementIndexFire}')">
                                                        <label class="form-check-label">อื่นๆ</label>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_other_text_fire_${measurementIndexFire}" id="action_other_action_fire_${measurementIndexFire}" disabled>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small text-muted">7. หมายเหตุ</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="measurement_remark_fire[]">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-12">
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
                                        <input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_fire[]" value="">
                                    </div>
                                    <div class="col-12">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMeasurementCardFire(this)">
                                            <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.appendChild(newCard);
                        measurementIndexFire++;
                    }

                    // ฟังก์ชันลบ card บันทึกการตรวจเก็บวัตถุพยาน (Fire)
                    function removeMeasurementCardFire(button) {
                        const container = document.getElementById('measurement_container_fire');
                        if (container.children.length > 1) {
                            button.closest('.measurement-card-fire').remove();
                        } else {
                            alert('ต้องมีอย่างน้อย 1 รายการ');
                        }
                    }

                    function addVictimCardFire() {
                        fireVictimIndex++;
                        const container = document.getElementById('fire_person_container');
                        const newCard = document.createElement('div');
                        newCard.className = 'victim-card-fire bg-light p-3 rounded-3 border mb-3';
                        newCard.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-primary">รายการที่ ${fireVictimIndex}</span>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVictimCardFire(this)">
                                    <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                </button>
                            </div>

                            <div class="bg-white p-3 rounded-3 shadow-sm">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">ประเภท <span class="text-danger">*</span></label>
                                    <select class="form-select" name="fire_person_type[]">
                                        <option value="" selected>-- เลือกประเภท --</option>
                                        <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                                        <option value="ผู้บาดเจ็บ">ผู้บาดเจ็บ</option>
                                        <option value="ผู้เสียชีวิต">ผู้เสียชีวิต</option>
                                    </select>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="fire_person_name[]" placeholder="ชื่อ-นามสกุล">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">อายุ (ปี)</label>
                                        <input type="text" class="form-control text-center" name="fire_person_age[]" inputmode="numeric" pattern="[0-9]*" maxlength="3" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 3);" placeholder="อายุ">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">หมายเหตุ</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="fire_person_remark[]" placeholder="...">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.appendChild(newCard);
                    }

                    // ฟังก์ชันลบผู้ประสบเหตุ (Fire)
                    function removeVictimCardFire(button) {
                        const container = document.getElementById('fire_person_container');
                        if (container.children.length > 1) {
                            button.closest('.victim-card-fire').remove();
                        } else {
                            alert('ต้องมีอย่างน้อย 1 รายการ');
                        }
                    }

                    // ฟังก์ชันสลับ checkbox (Fire) - เลือกได้ 1 ใน group
                    function fireRadioToggle(el) {
                        const group = el.getAttribute('data-group');
                        if (!group) return;
                        const allInGroup = document.querySelectorAll(`input[data-group="${group}"]`);
                        allInGroup.forEach(function(cb) {
                            if (cb !== el) cb.checked = false;
                        });
                    }

                    document.addEventListener('DOMContentLoaded', function() {
                        // คัดลอกเลขที่เอกสาร -> คดี
                        const receiveNotiNoFire = document.getElementById('receiveNoti_No_fire');
                        const caseDocNoFire = document.getElementById('case_doc_no_fire');
                        if (receiveNotiNoFire && caseDocNoFire) {
                            const observer = new MutationObserver(function(mutations) {
                                mutations.forEach(function(mutation) {
                                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                                        var raw = receiveNotiNoFire.textContent.trim();
                                        caseDocNoFire.value = (window.toThaiDocNo ? window.toThaiDocNo(raw) : raw);
                                    }
                                });
                            });
                            observer.observe(receiveNotiNoFire, { childList: true, characterData: true, subtree: true });
                            if (receiveNotiNoFire.textContent.trim()) {
                                var rawInit = receiveNotiNoFire.textContent.trim();
                                caseDocNoFire.value = (window.toThaiDocNo ? window.toThaiDocNo(rawInit) : rawInit);
                            }
                        }

                        // ช่องทางการรับแจ้ง - อื่นๆ toggle
                        const notifyOther = document.getElementById('fire_notify_other');
                        const notifyOtherText = document.getElementById('fire_notify_other_text');
                        const notifyOtherHw = document.getElementById('fire_notify_other_hw');
                        if (notifyOther && notifyOtherText) {
                            notifyOther.addEventListener('change', function() {
                                if (this.checked) {
                                    notifyOtherText.style.display = 'block';
                                    notifyOtherText.disabled = false;
                                    if (notifyOtherHw) notifyOtherHw.style.display = '';
                                } else {
                                    notifyOtherText.style.display = 'none';
                                    notifyOtherText.disabled = true;
                                    notifyOtherText.value = '';
                                    if (notifyOtherHw) notifyOtherHw.style.display = 'none';
                                }
                            });
                        }

                        // การดับไฟ - toggle detail
                        const extYes = document.getElementById('fire_extinguish_yes');
                        const extDetail = document.getElementById('fire_extinguish_yes_detail');
                        const extDetailGroup = document.getElementById('fire_extinguish_detail_group');
                        if (extYes && extDetail) {
                            extYes.addEventListener('change', function() {
                                if (this.checked) {
                                    if (extDetailGroup) extDetailGroup.style.display = 'flex';
                                    extDetail.disabled = false;
                                } else {
                                    if (extDetailGroup) extDetailGroup.style.display = 'none';
                                    extDetail.disabled = true;
                                    extDetail.value = '';
                                }
                            });
                        }

                        // สาเหตุ - toggle detail fields
                        const causeBelieved = document.getElementById('fire_cause_believed');
                        const causeUnknown = document.getElementById('fire_cause_unknown');
                        const causeBelievedDetail = document.getElementById('fire_cause_believed_detail');
                        const causeUnknownDetail = document.getElementById('fire_cause_unknown_detail');

                        const causeBelievedGroup = document.getElementById('fire_cause_believed_group');
                        const causeUnknownGroup = document.getElementById('fire_cause_unknown_group');

                        if (causeBelieved && causeBelievedDetail) {
                            causeBelieved.addEventListener('change', function() {
                                if (this.checked) {
                                    if (causeBelievedGroup) causeBelievedGroup.style.display = 'flex';
                                    causeBelievedDetail.disabled = false;
                                } else {
                                    if (causeBelievedGroup) causeBelievedGroup.style.display = 'none';
                                    causeBelievedDetail.disabled = true;
                                    causeBelievedDetail.value = '';
                                }
                            });
                        }

                        if (causeUnknown && causeUnknownDetail) {
                            causeUnknown.addEventListener('change', function() {
                                if (this.checked) {
                                    if (causeUnknownGroup) causeUnknownGroup.style.display = 'flex';
                                    causeUnknownDetail.disabled = false;
                                } else {
                                    if (causeUnknownGroup) causeUnknownGroup.style.display = 'none';
                                    causeUnknownDetail.disabled = true;
                                    causeUnknownDetail.value = '';
                                }
                            });
                        }

                        // Init canvas ให้โปร่งใส
                        const canvasList = ['scene_sketch_canvas_fire'];
                        canvasList.forEach(function(canvasId) {
                            const canvas = document.getElementById(canvasId);
                            if (canvas) {
                                const ctx = canvas.getContext('2d');
                                canvas.width = canvas.offsetWidth || canvas.parentElement.offsetWidth;
                                canvas.height = canvas.offsetHeight || canvas.parentElement.offsetHeight;
                                ctx.clearRect(0, 0, canvas.width, canvas.height);
                            }
                        });
                    });
                    </script>

                    <!-- ==================== Modal Footer ==================== -->
                    <div class="modal-footer justify-content-end">
                        <button type="button" class="btn btn-success" id="btn_save_fire">
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
