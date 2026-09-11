<?php
/**
 * Modal: ตรวจเก็บวัตถุพยานบุคคล (Standard Form)
 * complaints_type = '08'
 * Prefix: ev8_
 * UI pattern: อิงตาม modal_scene_evidence.php (EV7) ทุกประการ
 */
date_default_timezone_set('Asia/Bangkok');

$inspectorOptionsEV8 = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
if (isset($pdo)) {
    $qryEV8 = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname,
               IFNULL(t3.position_name, '-') AS position_name
               FROM user_profile t1
               LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
               LEFT JOIN user_position t3 ON t1.position_id = t3.position_id
               ORDER BY t1.user_id DESC";
    $stmtEV8 = $pdo->query($qryEV8);
    while ($r = $stmtEV8->fetch(PDO::FETCH_ASSOC)) {
        $inspectorOptionsEV8 .= '<option value="' . $r['user_id'] . '" data-position="' . htmlspecialchars($r['position_name']) . '">' . htmlspecialchars($r['fullname']) . '</option>';
    }
}

$policeStationOptionsEV8 = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qryPS8 = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stmtPS8 = $pdo->query($qryPS8);
    while ($r = $stmtPS8->fetch(PDO::FETCH_ASSOC)) {
        $policeStationOptionsEV8 .= '<option value="' . htmlspecialchars($r['station_name']) . '">' . htmlspecialchars($r['station_name']) . '</option>';
    }
}

$todayDateEV8 = date('Y-m-d');
$todayTimeEV8 = date('H:i');
?>

<!-- ========== Modal: ตรวจเก็บวัตถุพยานบุคคล (Standard) ========== -->
<div class="modal fade" id="addCheckListModalPersonEvidence" aria-labelledby="addCheckListModalPersonEvidenceLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addCheckListModalPersonEvidenceLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> ตรวจเก็บวัตถุพยานบุคคล
                </h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4" style="position:relative;">
                <!-- Loading Overlay -->
                <div id="ev8LoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track"><div class="csims-bar-fill"></div></div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>

                <form method="post" id="incidentCheckListFormPersonEvidence" novalidate>
                    <input type="hidden" id="receiveNoti_id_ev8" name="receiveNoti_id_ev8">
                    <input type="hidden" id="doc_no_ev8" name="doc_no_ev8">
                    <input type="hidden" id="report_no_ev8" name="report_no_ev8">

                    <!-- Header (เหมือน EV7) -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="editInfoEV8" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountEV8" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto me-3">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="receiveNoti_No_ev8"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="receiveNotiReportNo_ev8"></span>
                            </div>
                        </div>
                        <div class="bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2 flex-shrink-0">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToPdfFormEV8" style="width: 3rem; height: 1.5rem; cursor: pointer;" onchange="if(this.checked){ this.checked=false; switchToPersonEvidencePdfForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToPdfFormEV8" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

                    <!-- ==================== 1. สิ่งที่ได้รับจาก / การรับแจ้งเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">1. สิ่งที่ได้รับจาก / การรับแจ้งเหตุ</legend>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เมื่อวันที่ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="ev8_receive_date" name="ev8_receive_date" value="<?= $todayDateEV8 ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เวลา <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="ev8_receive_time" name="ev8_receive_time" value="<?= $todayTimeEV8 ?>" step="60">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">หน่วยงาน</label>
                                <select class="form-select" id="ev8_unit_type" name="ev8_unit_type">
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="กองพิสูจน์หลักฐานกลาง">กองพิสูจน์หลักฐานกลาง</option>
                                    <option value="ศูนย์พิสูจน์หลักฐาน">ศูนย์พิสูจน์หลักฐาน</option>
                                    <option value="พิสูจน์หลักฐานจังหวัด">พิสูจน์หลักฐานจังหวัด</option>
                                    <option value="กลุ่มงานการตรวจพิสูจน์">กลุ่มงานการตรวจพิสูจน์</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ชื่อหน่วยงาน</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="ev8_unit_name" name="ev8_unit_name" placeholder="ระบุชื่อหน่วยงาน">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev8_unit_name" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ช่องทางการรับแจ้ง <span class="text-danger">*</span></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row g-4">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ev8_notify_method[]" value="ตามหนังสือ" id="ev8_notify_letter">
                                            <label class="form-check-label" for="ev8_notify_letter">ตามหนังสือ</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ev8_notify_method[]" value="ทางโทรศัพท์" id="ev8_notify_phone">
                                            <label class="form-check-label" for="ev8_notify_phone">ทางโทรศัพท์</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ev8_notify_method[]" value="ทางวิทยุสื่อสาร" id="ev8_notify_radio">
                                            <label class="form-check-label" for="ev8_notify_radio">ทางวิทยุสื่อสาร</label>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" name="ev8_notify_method[]" value="อื่นๆ" id="ev8_notify_other">
                                            <label class="form-check-label" for="ev8_notify_other">อื่นๆ</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm" name="ev8_notify_method_other_text" id="ev8_notify_other_text" placeholder="ระบุ" style="width:200px; display:none;" disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" data-hw-targets="ev8_notify_other_text" id="ev8_notify_other_text_hw" title="เขียนด้วยลายมือ" style="padding:1px 6px; font-size:0.7rem; display:none;"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">จาก สน./สภ. <span class="text-danger">*</span></label>
                                <select id="ev8_police_station" name="ev8_police_station" class="form-select"><?= $policeStationOptionsEV8 ?></select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="ev8_document_no" name="ev8_document_no">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev8_document_no" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ลง</label>
                                <div class="input-group">
                                    <input type="date" class="form-control" id="ev8_document_date" name="ev8_document_date" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">ในคดี</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="ev8_case_no" name="ev8_case_no">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev8_case_no" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">สถานที่เกิดเหตุ <span class="text-danger">*</span> <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="ev8_incident_location" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" id="ev8_incident_location" name="ev8_incident_location" rows="2" placeholder="ระบุสถานที่เกิดเหตุ"></textarea>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เหตุเกิดเมื่อวันที่ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="ev8_incident_date" name="ev8_incident_date" value="<?= $todayDateEV8 ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" id="ev8_incident_time" name="ev8_incident_time" value="<?= $todayTimeEV8 ?>" step="60">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">พนักงานสอบสวน</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="ev8_investigator_name" name="ev8_investigator_name" placeholder="ชื่อพนักงานสอบสวน">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev8_investigator_name" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ขอส่ง</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="ev8_send_request" name="ev8_send_request">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev8_send_request" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>

                        <!-- รายการบุคคล (dynamic — เหมือนรายการของกลาง EV7) -->
                        <div class="mb-3 mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary border-bottom pb-2 mb-0 flex-grow-1">รายการบุคคล</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addPersonItemEV8()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                </button>
                            </div>
                            <div id="ev8_person_items_container">
                                <div class="ev8-person-item-row input-group mb-2">
                                    <span class="input-group-text fw-bold">๑.๑</span>
                                    <select class="form-select" name="ev8_person_prefix[]" style="max-width:140px;">
                                        <option value="" selected disabled>คำนำหน้า</option>
                                        <option value="นาย">นาย</option>
                                        <option value="นาง">นาง</option>
                                        <option value="นางสาว">นางสาว</option>
                                        <option value="อื่นๆ">อื่นๆ</option>
                                    </select>
                                    <input type="text" class="form-control" name="ev8_person_name[]" placeholder="ชื่อ-นามสกุล">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    <button type="button" class="btn btn-outline-danger" onclick="removeEV8Row(this)"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 2. วัตถุประสงค์ในการตรวจ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">2. วัตถุประสงค์ในการตรวจ</legend>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">เพื่อทำการตรวจเก็บ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="ev8_purpose_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" id="ev8_purpose_detail" name="ev8_purpose_detail" rows="2" placeholder="ระบุวัตถุประสงค์ เช่น เนื้อเยื่อบุกระพุ้งแก้ม เพื่อตรวจจากบุคคลดังกล่าว"></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 3. ผลการตรวจ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">3. ผลการตรวจ</legend>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ได้ทำการตรวจเก็บ ที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="ev8_inspect_location" name="ev8_inspect_location">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev8_inspect_location" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control" id="ev8_inspect_date" name="ev8_inspect_date" value="<?= $todayDateEV8 ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" id="ev8_inspect_time" name="ev8_inspect_time" value="<?= $todayTimeEV8 ?>" step="60">
                            </div>
                        </div>

                        <!-- 3.1 ข้อมูลส่วนบุคคล -->
                        <div class="mb-4 mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary border-bottom pb-2 mb-0 flex-grow-1">3.1 ข้อมูลส่วนบุคคล</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addPersonInfoEV8()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มบุคคล
                                </button>
                            </div>
                            <p class="text-muted small mb-2">(ให้ระบุ เช่น หมายเลขบัตรประจำตัวประชาชน / แบบหนังสือเดินทาง / ความสูง / คำหนี้รูปพรรณ เช่น แผลเป็น ไฝ สีผิว อายุ มือที่ถนัด เป็นต้น)</p>
                            <div id="ev8_person_info_container">
                                <div class="ev8-person-info-row card mb-3 border">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-bold text-secondary ev8-person-info-label">บุคคลที่ 1</span>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-2">
                                                <select class="form-select form-select-sm" name="ev8_info_prefix[]">
                                                    <option value="" selected disabled>คำนำหน้า</option>
                                                    <option value="นาย">นาย</option>
                                                    <option value="นาง">นาง</option>
                                                    <option value="นางสาว">นางสาว</option>
                                                    <option value="อื่นๆ">อื่นๆ</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" name="ev8_info_fullname[]" placeholder="ชื่อ-นามสกุล">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" name="ev8_info_id_card[]" placeholder="เลขบัตรประชาชน">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" name="ev8_info_passport[]" placeholder="เลขหนังสือเดินทาง">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" name="ev8_info_height[]" placeholder="สูง (ซม.)">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" name="ev8_info_age[]" placeholder="อายุ (ปี)">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" name="ev8_info_skin[]" placeholder="สีผิว">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <select class="form-select form-select-sm" name="ev8_info_hand[]">
                                                    <option value="" selected disabled>มือที่ถนัด</option>
                                                    <option value="ขวา">ขวา</option>
                                                    <option value="ซ้าย">ซ้าย</option>
                                                    <option value="ทั้งสองมือ">ทั้งสองมือ</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" name="ev8_info_feature[]" placeholder="คำหนี้รูปพรรณ (แผลเป็น, ไฝ ฯลฯ)">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3.2 รายละเอียดวัตถุพยานที่ทำการตรวจเก็บ -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary border-bottom pb-2 mb-0 flex-grow-1">3.2 รายละเอียดวัตถุพยานที่ทำการตรวจเก็บ</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addEvidenceDetailEV8()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                </button>
                            </div>
                            <div id="ev8_evidence_detail_container">
                                <div class="ev8-evidence-detail-row input-group mb-2">
                                    <span class="input-group-text fw-bold">๓.๒.๑</span>
                                    <input type="text" class="form-control" name="ev8_evidence_desc[]" placeholder="ตัวอย่าง เช่น เนื้อเยื่อบุกระพุ้งแก้ม/เข่ากับที่... จาก (นาย/นาง/นางสาว)...">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    <select class="form-select lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="max-width:220px;">
                                        <option value="">-- กลุ่มตรวจพิสูจน์ (เลือกได้หลายข้อ) --</option>
                                        <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                                        <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                        <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                        <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                        <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                        <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                        <option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                                    </select>
                                    <input type="hidden" class="lab-unit-value" name="ev8_lab_unit[]" value="">
                                    <input type="text" class="form-control" name="ev8_evidence_qty[]" placeholder="จำนวน" style="max-width:120px;">
                                    <button type="button" class="btn btn-outline-danger" onclick="removeEV8Row(this)"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>

                        <!-- 3.3 การดำเนินการเกี่ยวกับวัตถุพยาน -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">3.3 การดำเนินการเกี่ยวกับวัตถุพยาน</h6>
                            <div class="row g-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ได้ให้ (ชื่อผู้ลงลายมือชื่อ)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="ev8_witness_name" id="ev8_witness_name">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev8_witness_name" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ลงลายมือชื่อในแบบ</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="ev8_witness_form" id="ev8_witness_form">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev8_witness_form" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">การนำส่ง</label>
                                    <select class="form-select" name="ev8_handover_method" id="ev8_handover_method">
                                        <option value="" selected disabled>เลือก</option>
                                        <option value="นำส่ง">นำส่ง</option>
                                        <option value="ส่งมอบ">ส่งมอบ</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">วัตถุพยานตามชื่อ</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="ev8_handover_item_ref" id="ev8_handover_item_ref">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev8_handover_item_ref" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">ให้ (ผู้รับ)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="ev8_handover_to" id="ev8_handover_to">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev8_handover_to" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">เพื่อดำเนินการต่อไป (รายละเอียด) <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="ev8_handover_purpose" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                    <textarea class="form-control" name="ev8_handover_purpose" id="ev8_handover_purpose" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 4. ผู้ตรวจ (เหมือน EV7 section 4) ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">4. ผู้ตรวจ</legend>
                        <div id="ev8_inspector_container">
                            <div class="d-flex align-items-center mb-3 ev8-inspector-row">
                                <div class="text-end pe-3" style="width: 50px;">
                                    <span class="fw-bold text-secondary ev8-index-label">4.1</span>
                                </div>
                                <div class="flex-grow-1">
                                    <select class="form-select ev8-inspector-select" name="ev8_inspector_id[]"><?= $inspectorOptionsEV8 ?></select>
                                </div>
                                <div class="ms-2" style="width: 32px;"></div>
                            </div>
                        </div>
                        <div class="d-flex mt-2">
                            <div style="width: 50px;"></div>
                            <div class="flex-grow-1">
                                <button type="button" class="btn btn-outline-primary border-dashed w-100 py-2" id="btn_add_inspector_ev8">
                                    <i class="fas fa-plus-circle me-1"></i> เพิ่มผู้ตรวจ
                                </button>
                            </div>
                            <div class="ms-2" style="width: 32px;"></div>
                        </div>
                    </fieldset>

                    <!-- ==================== 5. ภาพถ่าย (เหมือน EV7 section 5) ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">5. บันทึกการถ่ายภาพ</legend>
                        <div class="row mb-4">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">รหัสภาพถ่ายที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="ev8_photo_id_start" id="ev8_photo_id_start">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev8_photo_id_start" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">ถึง</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="ev8_photo_id_end" id="ev8_photo_id_end">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev8_photo_id_end" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">จำนวน (ภาพ)</label>
                                <input type="text" class="form-control" name="ev8_photo_amount" id="ev8_photo_amount" inputmode="numeric">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <div id="ev8_photo_dropzone" class="d-flex flex-column align-items-center justify-content-center text-center"
                                    style="border:2px dashed #b0bec5; border-radius:12px; padding:24px; cursor:pointer; background:#f8f9fa; transition:all 0.2s;">
                                    <i class="fas fa-cloud-upload-alt mb-2" style="font-size:1.8rem; color:#90a4ae;"></i>
                                    <div class="text-muted">ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="button" class="btn btn-primary" id="btn_choose_file_ev8">
                                        <i class="fas fa-folder-open me-2"></i>เลือกไฟล์รูปภาพ
                                    </button>
                                    <button type="button" class="btn btn-success" id="btn_open_camera_ev8">
                                        <i class="fas fa-camera me-2"></i>เปิดกล้องถ่ายภาพ
                                    </button>
                                </div>
                            </div>
                        </div>
                        <input type="file" id="incident_photos_ev8" name="incident_photos_ev8[]" accept="image/*" style="display:none;" multiple>
                        <input type="file" id="camera_input_ev8" accept="image/*" capture="environment" style="display:none;" multiple>
                        <div id="attachments_wrapper_ev8" class="d-none mt-4">
                            <h6 class="fw-bold text-secondary mb-3">
                                Attachments <span class="badge bg-secondary rounded-pill ms-1" id="file_count_badge_ev8">0</span>
                            </h6>
                            <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-3" id="attachments_grid_ev8"></div>
                        </div>
                    </fieldset>

                    <!-- ==================== 6. ลงนาม (เหมือน EV7 section 6 — 2-column layout) ==================== -->
                    <fieldset class="p-4 bg-white rounded-4 shadow-sm border mb-4">
                        <legend class="fieldset-header">6. ลงนาม</legend>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small text-muted">วันที่</label>
                                <input type="date" class="form-control" id="ev8_sign_date" name="ev8_sign_date" value="<?= $todayDateEV8 ?>">
                            </div>
                        </div>
                        <div class="row g-4">
                            <!-- ผู้รับมอบ -->
                            <div class="col-lg-6">
                                <div class="p-3 bg-light rounded border h-100">
                                    <h6 class="fw-bold text-secondary border-bottom pb-2 mb-3">
                                        <i class="fas fa-user-check me-2"></i>ผู้รับมอบ
                                    </h6>
                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ลงชื่อ <span class="text-danger">*</span></label>
                                            <select class="form-select ev8-user-select" id="ev8_receiver_id" name="ev8_receiver_id" data-pos-target="#ev8_receiver_position"><?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้รับมอบ --', $inspectorOptionsEV8) ?></select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ตำแหน่ง</label>
                                            <input type="text" class="form-control bg-light" id="ev8_receiver_position" name="ev8_receiver_position" readonly>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small text-muted">ลายเซ็น</label>
                                        <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden w-100" style="height: 180px;">
                                            <canvas id="sig-canvas-ev8-receiver" class="signature-pad w-100 h-100"></canvas>
                                            <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none">
                                                <i class="fas fa-signature fa-2x mb-1 d-block text-center"></i> เซ็นชื่อที่นี่
                                            </div>
                                        </div>
                                        <div class="text-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearEV8ReceiverSignatures()"><i class="fas fa-eraser me-1"></i> ล้าง</button>
                                        </div>
                                        <input type="hidden" name="ev8_receiver_signature_data" id="ev8_receiver_signature_data">
                                    </div>
                                </div>
                            </div>
                            <!-- ผู้ส่งมอบ -->
                            <div class="col-lg-6">
                                <div class="p-3 bg-light rounded border h-100">
                                    <h6 class="fw-bold text-secondary border-bottom pb-2 mb-3">
                                        <i class="fas fa-user-edit me-2"></i>ผู้ส่งมอบ
                                    </h6>
                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ลงชื่อ <span class="text-danger">*</span></label>
                                            <select class="form-select ev8-user-select" id="ev8_sender_id" name="ev8_sender_id" data-pos-target="#ev8_sender_position"><?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งมอบ --', $inspectorOptionsEV8) ?></select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ตำแหน่ง</label>
                                            <input type="text" class="form-control bg-light" id="ev8_sender_position" name="ev8_sender_position" readonly>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small text-muted">ลายเซ็น</label>
                                        <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden w-100" style="height: 180px;">
                                            <canvas id="sig-canvas-ev8-sender" class="signature-pad w-100 h-100"></canvas>
                                            <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none">
                                                <i class="fas fa-signature fa-2x mb-1 d-block text-center"></i> เซ็นชื่อที่นี่
                                            </div>
                                        </div>
                                        <div class="text-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearEV8SenderSignatures()"><i class="fas fa-eraser me-1"></i> ล้าง</button>
                                        </div>
                                        <input type="hidden" name="ev8_sender_signature_data" id="ev8_sender_signature_data">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Save / Cancel (เหมือน EV7) -->
                    <div class="modal-footer justify-content-end">
                        <button type="button" class="btn btn-success" id="btn_save_ev8" onclick="prepareDataForSubmissionPersonEvidence()">
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

<script>
// Inspector options HTML for dynamic rows
var inspectorOptionsHTMLEV8 = <?= json_encode($inspectorOptionsEV8) ?>;

// ===================== Dynamic Row Functions (pattern เหมือน EV7) =====================
var ev8PersonIdx = 1;
var ev8PersonPrefixOptions = '<option value="" selected disabled>คำนำหน้า</option><option value="นาย">นาย</option><option value="นาง">นาง</option><option value="นางสาว">นางสาว</option><option value="อื่นๆ">อื่นๆ</option>';

function addPersonItemEV8() {
    ev8PersonIdx++;
    var html = '<div class="ev8-person-item-row input-group mb-2">' +
        '<span class="input-group-text fw-bold">๑.' + ev8PersonIdx + '</span>' +
        '<select class="form-select" name="ev8_person_prefix[]" style="max-width:140px;">' + ev8PersonPrefixOptions + '</select>' +
        '<input type="text" class="form-control" name="ev8_person_name[]" placeholder="ชื่อ-นามสกุล">' +
        '<button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>' +
        '<button type="button" class="btn btn-outline-danger" onclick="removeEV8Row(this)"><i class="fas fa-times"></i></button></div>';
    $('#ev8_person_items_container').append(html);
    renumberEV8Rows('#ev8_person_items_container', '๑.');
}

var ev8PersonInfoIdx = 1;
function addPersonInfoEV8() {
    ev8PersonInfoIdx++;
    var html = '<div class="ev8-person-info-row card mb-3 border">' +
        '<div class="card-body">' +
        '<div class="d-flex justify-content-between align-items-center mb-2">' +
            '<span class="fw-bold text-secondary ev8-person-info-label">บุคคลที่ ' + ev8PersonInfoIdx + '</span>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEV8PersonInfo(this)"><i class="fas fa-times me-1"></i>ลบ</button>' +
        '</div>' +
        '<div class="row g-2">' +
            '<div class="col-md-2"><select class="form-select form-select-sm" name="ev8_info_prefix[]">' + ev8PersonPrefixOptions + '</select></div>' +
            '<div class="col-md-4"><div class="input-group input-group-sm"><input type="text" class="form-control form-control-sm" name="ev8_info_fullname[]" placeholder="ชื่อ-นามสกุล"><button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>' +
            '<div class="col-md-3"><div class="input-group input-group-sm"><input type="text" class="form-control form-control-sm" name="ev8_info_id_card[]" placeholder="เลขบัตรประชาชน"><button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>' +
            '<div class="col-md-3"><div class="input-group input-group-sm"><input type="text" class="form-control form-control-sm" name="ev8_info_passport[]" placeholder="เลขหนังสือเดินทาง"><button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>' +
            '<div class="col-md-2"><div class="input-group input-group-sm"><input type="text" class="form-control form-control-sm" name="ev8_info_height[]" placeholder="ความสูง (ซม.)"><button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>' +
            '<div class="col-md-2"><div class="input-group input-group-sm"><input type="text" class="form-control form-control-sm" name="ev8_info_age[]" placeholder="อายุ (ปี)"><button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>' +
            '<div class="col-md-2"><div class="input-group input-group-sm"><input type="text" class="form-control form-control-sm" name="ev8_info_skin[]" placeholder="สีผิว"><button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>' +
            '<div class="col-md-3"><select class="form-select form-select-sm" name="ev8_info_hand[]"><option value="" selected disabled>มือที่ถนัด</option><option value="ขวา">ขวา</option><option value="ซ้าย">ซ้าย</option><option value="ทั้งสองมือ">ทั้งสองมือ</option></select></div>' +
            '<div class="col-md-3"><div class="input-group input-group-sm"><input type="text" class="form-control form-control-sm" name="ev8_info_feature[]" placeholder="คำหนี้รูปพรรณ (แผลเป็น, ไฝ ฯลฯ)"><button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>' +
        '</div></div></div>';
    $('#ev8_person_info_container').append(html);
}

function removeEV8PersonInfo(btn) {
    $(btn).closest('.ev8-person-info-row').remove();
    $('#ev8_person_info_container .ev8-person-info-label').each(function(i) {
        $(this).text('บุคคลที่ ' + (i + 1));
    });
    ev8PersonInfoIdx = $('#ev8_person_info_container .ev8-person-info-row').length;
}

var ev8EvidenceDetailIdx = 1;
var ev8LabUnitOptions = '<option value="">-- กลุ่มตรวจพิสูจน์ (เลือกได้หลายข้อ) --</option>' +
    '<option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>' +
    '<option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>' +
    '<option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>' +
    '<option value="drug">กลุ่มงานตรวจยาเสพติด</option>' +
    '<option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>' +
    '<option value="document">กลุ่มงานตรวจเอกสาร</option>' +
    '<option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>';
function addEvidenceDetailEV8() {
    ev8EvidenceDetailIdx++;
    var html = '<div class="ev8-evidence-detail-row input-group mb-2">' +
        '<span class="input-group-text fw-bold">๓.๒.' + ev8EvidenceDetailIdx + '</span>' +
        '<input type="text" class="form-control" name="ev8_evidence_desc[]" placeholder="ตัวอย่าง เช่น เนื้อเยื่อบุกระพุ้งแก้ม/เข่ากับที่... จาก (นาย/นาง/นางสาว)...">' +
        '<button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>' +
        '<select class="form-select lab-unit-multi" multiple size="3" title="\u0e40\u0e25\u0e37\u0e2d\u0e01\u0e44\u0e14\u0e49\u0e21\u0e32\u0e01\u0e01\u0e27\u0e48\u0e32 1 \u0e01\u0e25\u0e38\u0e48\u0e21\u0e07\u0e32\u0e19" style="max-width:220px;">' + ev8LabUnitOptions + '</select>' +
        '<input type="hidden" class="lab-unit-value" name="ev8_lab_unit[]" value="">' +
        '<input type="text" class="form-control" name="ev8_evidence_qty[]" placeholder="จำนวน" style="max-width:120px;">' +
        '<button type="button" class="btn btn-outline-danger" onclick="removeEV8Row(this)"><i class="fas fa-times"></i></button></div>';
    $('#ev8_evidence_detail_container').append(html);
    renumberEV8EvidenceDetails();
}

function removeEV8Row(btn) {
    var parent = $(btn).closest('.input-group');
    var container = parent.parent();
    parent.remove();
    if (container.attr('id') === 'ev8_person_items_container') renumberEV8Rows('#ev8_person_items_container', '๑.');
    if (container.attr('id') === 'ev8_evidence_detail_container') renumberEV8EvidenceDetails();
}

function renumberEV8Rows(containerSel, prefix) {
    $(containerSel).children().each(function(i) {
        $(this).find('.input-group-text').first().text(prefix + (i + 1));
    });
    ev8PersonIdx = $(containerSel).children().length;
}

function renumberEV8EvidenceDetails() {
    $('#ev8_evidence_detail_container').children().each(function(i) {
        $(this).find('.input-group-text').first().text('๓.๒.' + (i + 1));
    });
    ev8EvidenceDetailIdx = $('#ev8_evidence_detail_container').children().length;
}

// ===================== Inspector Dynamic Rows (เหมือน EV7) =====================
var ev8InspectorIdx = 1;
$(document).on('click', '#btn_add_inspector_ev8', function() {
    ev8InspectorIdx++;
    var html = '<div class="d-flex align-items-center mb-3 ev8-inspector-row">' +
        '<div class="text-end pe-3" style="width:50px;"><span class="fw-bold text-secondary ev8-index-label">4.' + ev8InspectorIdx + '</span></div>' +
        '<div class="flex-grow-1"><select class="form-select ev8-inspector-select" name="ev8_inspector_id[]">' + inspectorOptionsHTMLEV8 + '</select></div>' +
        '<div class="ms-2" style="width:32px;"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeInspectorEV8(this)"><i class="fas fa-times"></i></button></div></div>';
    $('#ev8_inspector_container').append(html);
});

function removeInspectorEV8(btn) {
    $(btn).closest('.ev8-inspector-row').remove();
    $('#ev8_inspector_container .ev8-index-label').each(function(i) {
        $(this).text('4.' + (i + 1));
    });
    ev8InspectorIdx = $('#ev8_inspector_container .ev8-inspector-row').length;
}

// ===================== Toggle "อื่นๆ" text field =====================
$('#ev8_notify_other').on('change', function() {
    if (this.checked) { $('#ev8_notify_other_text').show().prop('disabled', false); $('#ev8_notify_other_text_hw').show(); }
    else { $('#ev8_notify_other_text').hide().prop('disabled', true).val(''); $('#ev8_notify_other_text_hw').hide(); }
});

// ===================== Auto-fill position from select =====================
$(document).on('change', '.ev8-user-select', function() {
    var pos = $(this).find(':selected').data('position') || '';
    var target = $(this).data('pos-target');
    if (target) $(target).val(pos);
});

// ===================== Photo Handling (เหมือน EV7) =====================
var attachmentStoreEV8 = [];
var deletedExistingPhotosEV8 = [];

$('#btn_choose_file_ev8').on('click', function() { $('#incident_photos_ev8').trigger('click'); });
$('#btn_open_camera_ev8').on('click', function() { $('#camera_input_ev8').trigger('click'); });

// ★ Drag & Drop zone (ฟอร์มปกติวัตถุพยานบุคคล)
(function() {
    var ev8DZ = document.getElementById('ev8_photo_dropzone');
    if (!ev8DZ) return;
    ev8DZ.addEventListener('click', function() { $('#incident_photos_ev8').trigger('click'); });
    ['dragenter', 'dragover'].forEach(function(evt) {
        ev8DZ.addEventListener(evt, function(e) { e.preventDefault(); e.stopPropagation(); ev8DZ.style.borderColor = '#2196F3'; ev8DZ.style.background = '#e3f2fd'; });
    });
    ['dragleave', 'drop'].forEach(function(evt) {
        ev8DZ.addEventListener(evt, function(e) { e.preventDefault(); e.stopPropagation(); ev8DZ.style.borderColor = '#b0bec5'; ev8DZ.style.background = '#f8f9fa'; });
    });
    ev8DZ.addEventListener('drop', function(e) {
        var files = (e.dataTransfer && e.dataTransfer.files) ? e.dataTransfer.files : null;
        if (!files || !files.length) return;
        Array.from(files).forEach(function(file) {
            if (!file.type.startsWith('image/')) return;
            var id = 'ev8_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
            var reader = new FileReader();
            reader.onload = function(ev) {
                attachmentStoreEV8.push({ id: id, src: ev.target.result, name: file.name, file: file, existing: false });
                renderEV8AttachmentGrid();
                if (typeof window.pepfRenderPhotosFromStore === 'function') window.pepfRenderPhotosFromStore();
            };
            reader.readAsDataURL(file);
        });
    });
})();

$('#incident_photos_ev8, #camera_input_ev8').on('change', function(e) {
    var files = e.target.files;
    if (!files || files.length === 0) return;
    Array.from(files).forEach(function(file) {
        if (!file.type.startsWith('image/')) return;
        var id = 'ev8_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
        var reader = new FileReader();
        reader.onload = function(ev) {
            attachmentStoreEV8.push({ id: id, src: ev.target.result, name: file.name, file: file, existing: false });
            renderEV8AttachmentGrid();
            if (typeof window.pepfRenderPhotosFromStore === 'function') window.pepfRenderPhotosFromStore();
        };
        reader.readAsDataURL(file);
    });
    e.target.value = '';
});

function renderEV8AttachmentGrid() {
    var grid = $('#attachments_grid_ev8');
    grid.empty();
    var total = attachmentStoreEV8.length;
    if (total > 0) {
        $('#attachments_wrapper_ev8').removeClass('d-none');
        $('#file_count_badge_ev8').text(total);
        $('#ev8_photo_amount').val(total);
        // ★ รหัสภาพ = ชื่อไฟล์แรก/ล่าสุด (เฉพาะตอน user แนบ/ลบเอง ไม่ทับค่าที่โหลดจาก DB)
        if (!window.__ev8LoadingPhotos) {
            $('#ev8_photo_id_start').val(attachmentStoreEV8[0].name || 'photo');
            $('#ev8_photo_id_end').val(attachmentStoreEV8[total - 1].name || 'photo');
        }
        attachmentStoreEV8.forEach(function(item) {
            var displayName = (item.name || 'photo').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            var jsName = (item.name || 'photo').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
            var imgSrc = item.src || '';
            var badgeHtml = item.existing ?
                '<div class="pt-2"><span class="badge bg-info text-white fw-normal" style="font-size:0.65rem;">รูปเดิม</span></div>' :
                '<div class="pt-2"><span class="badge bg-success text-white fw-normal" style="font-size:0.65rem;">รูปใหม่</span></div>';
            var card = '<div class="col attachment-item" id="ev8_attach_' + item.id + '">' +
                '<div class="card attachment-card h-100">' +
                '<div class="card-actions-bar">' +
                '<button type="button" class="action-btn" title="ดูรูปภาพ" onclick="showImagePreview(\'' + imgSrc + '\', \'' + jsName + '\')"><i class="far fa-eye" style="font-size:0.8rem;"></i></button>' +
                '<button type="button" class="action-btn delete" title="ลบรูปนี้" onclick="removeEV8Attachment(\'' + item.id + '\')"><i class="fas fa-times" style="font-size:0.85rem;"></i></button>' +
                '</div>' +
                '<div class="img-thumbnail-box"><img src="' + imgSrc + '" alt="' + displayName + '"></div>' +
                '<div class="card-body d-flex flex-column">' +
                '<div class="filename-text mb-auto" title="' + displayName + '">' + displayName + '</div>' +
                badgeHtml +
                '</div></div></div>';
            grid.append(card);
        });
    } else {
        $('#attachments_wrapper_ev8').addClass('d-none');
        $('#file_count_badge_ev8').text(0);
        $('#ev8_photo_id_start').val('');
        $('#ev8_photo_id_end').val('');
        $('#ev8_photo_amount').val('');
    }
}

function removeEV8Attachment(id) {
    var item = attachmentStoreEV8.find(function(x) { return x.id === id; });
    if (item && item.existing) {
        deletedExistingPhotosEV8.push({ file_id: item.db_file_id, db_filename: item.db_filename });
    }
    attachmentStoreEV8 = attachmentStoreEV8.filter(function(x) { return x.id !== id; });
    renderEV8AttachmentGrid();
    if (typeof window.pepfRenderPhotosFromStore === 'function') window.pepfRenderPhotosFromStore();
}

// ===================== Reset Form (เหมือน EV7) =====================
function resetPersonEvidenceForm() {
    var form = document.getElementById('incidentCheckListFormPersonEvidence');
    if (form) form.reset();
    $('#editInfoEV8').addClass('d-none');
    attachmentStoreEV8 = [];
    deletedExistingPhotosEV8 = [];
    renderEV8AttachmentGrid();
    ev8PersonIdx = 1; ev8PersonInfoIdx = 1; ev8EvidenceDetailIdx = 1; ev8InspectorIdx = 1;
    $('#ev8_person_items_container').html($('#ev8_person_items_container').children().first().prop('outerHTML') || '');
    $('#ev8_person_info_container').html($('#ev8_person_info_container').children().first().prop('outerHTML') || '');
    $('#ev8_evidence_detail_container').html($('#ev8_evidence_detail_container').children().first().prop('outerHTML') || '');
    $('#ev8_inspector_container').html($('#ev8_inspector_container').children().first().prop('outerHTML') || '');
    if (typeof signaturePads !== 'undefined') {
        if (signaturePads['sig-canvas-ev8-receiver']) signaturePads['sig-canvas-ev8-receiver'].clear();
        if (signaturePads['sig-canvas-ev8-sender']) signaturePads['sig-canvas-ev8-sender'].clear();
    }
    // ★ FIX: reset PDF form ด้วย ป้องกันแถวค้างเมื่อเปิดครั้งที่ 2
    if (typeof window.resetPersonEvidencePdfForm === 'function') window.resetPersonEvidencePdfForm();
}

// ===================== Prefill / Load functions =====================
function ev8GetReceiveNotiId() {
    return ($('#receiveNoti_id_ev8').val() || $('#pepf_receiveNoti_id').val() || '').toString().trim();
}

function ev8SetValueIfBlank(selector, value) {
    var val = (value || '').toString().trim();
    if (!val) return;
    $(selector).each(function() {
        var current = ($(this).val() || '').toString().trim();
        if (!current) $(this).val(val);
    });
}

function ev8SetSelectValueWithOption($select, value) {
    var val = (value || '').toString().trim();
    if (!val || !$select || !$select.length) return;
    if ($select.find('option[value="' + val + '"]').length === 0) {
        $select.append(new Option(val, val, true, true));
    }
    $select.val(val).trigger('change');
}

function ev8ClearCanvasById(canvasId) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
}

window.clearEV8ReceiverSignatures = function() {
    // Receiver exists in both standard and PDF forms; clear both to avoid stale save state.
    ev8ClearCanvasById('sig-canvas-ev8-receiver');
    ev8ClearCanvasById('pepf_sig_receiver');
};

window.clearEV8SenderSignatures = function() {
    // Sender exists in both standard and PDF forms; clear both to avoid stale save state.
    ev8ClearCanvasById('sig-canvas-ev8-sender');
    ev8ClearCanvasById('pepf_sig_sender');
};

function ev8MapReceiveNotiChannel(deviceCode) {
    var mapping = { 'd': 'ตามหนังสือ', 't': 'ทางโทรศัพท์', 'r': 'ทางวิทยุสื่อสาร', 'other': 'อื่นๆ' };
    return mapping[(deviceCode || '').toString().trim()] || 'อื่นๆ';
}

function ev8SplitReceiveNotiDateTime(value) {
    if (!value) return { date: '', time: '' };
    var dt = new Date(value);
    if (isNaN(dt.getTime())) return { date: '', time: '' };
    var date = dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0') + '-' + String(dt.getDate()).padStart(2, '0');
    var time = String(dt.getHours()).padStart(2, '0') + ':' + String(dt.getMinutes()).padStart(2, '0');
    return { date: date, time: time };
}

function ev8ApplyReceiveNotiData(data) {
    if (!data) return;
    var docNo = (data.receiveNoti_No || $('#doc_no_ev8').val() || '').toString().trim();
    var reportNo = (data.receiveNotiReportNo || $('#report_no_ev8').val() || '').toString().trim();
    if (docNo) {
        // hidden input เก็บค่าอังกฤษไว้สำหรับสร้างเอกสาร, ช่องแสดง "ในคดี" ใช้ภาษาไทย
        var docNoTH = (window.toThaiDocNo ? toThaiDocNo(docNo) : docNo);
        $('#doc_no_ev8, #pepf_doc_no').val(docNo);
        $('#receiveNoti_No_ev8').text(docNoTH);
        ev8SetValueIfBlank('#ev8_case_no', docNoTH);
        ev8SetValueIfBlank('#pepf_case_no, #pepf_case_no_display', docNoTH);
    }
    if (reportNo) {
        $('#report_no_ev8, #pepf_report_no').val(reportNo);
        $('#receiveNotiReportNo_ev8').text(reportNo);
    }
    var channel = ev8MapReceiveNotiChannel(data.complaints_From_Device);
    var channelPdf = channel === 'ตามหนังสือ' ? 'ทางหนังสือ' : channel;
    if ($('input[name="ev8_notify_method[]"]:checked').length === 0) {
        $('input[name="ev8_notify_method[]"][value="' + channel + '"]').prop('checked', true).trigger('change');
    }
    if ($('input[name="pepf_notify_method[]"]:checked').length === 0) {
        $('input[name="pepf_notify_method[]"][value="' + channelPdf + '"]').prop('checked', true).trigger('change');
    }
    var chOther = (data.complaints_From_Device_Other || '').toString().trim();
    if (channel === 'อื่นๆ' && chOther) {
        $('#ev8_notify_other').prop('checked', true).trigger('change');
        ev8SetValueIfBlank('#ev8_notify_other_text', chOther);
        $('#pepf_notify_other').prop('checked', true).trigger('change');
        ev8SetValueIfBlank('#pepf_notify_method_other_text', chOther);
    }
    if (typeof window.pepfToggleNotifyOther === 'function') {
        window.pepfToggleNotifyOther();
    }
    if (data.complaints_From) {
        ev8SetSelectValueWithOption($('#ev8_police_station'), data.complaints_From);
        ev8SetSelectValueWithOption($('#pepf_police_station'), data.complaints_From);
    }
    var receiveDateTime = ev8SplitReceiveNotiDateTime(data.create_date);
    ev8SetValueIfBlank('#ev8_receive_date, #pepf_receive_date', receiveDateTime.date);
    ev8SetValueIfBlank('#ev8_receive_time, #pepf_receive_time', receiveDateTime.time);
    var incidentLocation = (data.location_crime || data.place_Occurrence || data.location_create || '').toString().trim();
    ev8SetValueIfBlank('#ev8_incident_location, #pepf_incident_location', incidentLocation);
    var occurDateTime = ev8SplitReceiveNotiDateTime(data.time_Occurrence);
    ev8SetValueIfBlank('#ev8_incident_date, #pepf_incident_date', occurDateTime.date);
    ev8SetValueIfBlank('#ev8_incident_time, #pepf_incident_time', occurDateTime.time);
    var investigatorName = [data.inquiry_official_first_name, data.inquiry_official_last_name]
        .map(function(v) { return (v || '').toString().trim(); }).filter(Boolean).join(' ');
    ev8SetValueIfBlank('#ev8_investigator_name, #pepf_investigator_name', investigatorName);

    // --- Agency / Unit info from userReviewType ---
    var rt = (data.userReviewType || '').toString().trim();
    var rv = (data.userReviewTypeVal || '').toString().trim();
    var provinceMap = {'95': 'ยะลา', '94': 'ปัตตานี', '96': 'นราธิวาส'};
    if (rt) {
        var unitType = '', unitName = '', centerName = '', provinceName = '';
        if (rt === 'nvt') {
            unitType = 'กองพิสูจน์หลักฐานกลาง';
            unitName = 'นวท.(สบ ' + rv + ') กสก.พฐก.';
        } else if (rt === 'spt') {
            unitType = 'ศูนย์พิสูจน์หลักฐาน';
            unitName = 'ศพฐ ' + rv;
            centerName = rv;
        } else if (rt === 'ptjv') {
            unitType = 'พิสูจน์หลักฐานจังหวัด';
            unitName = 'พฐ.จว.' + (provinceMap[rv] || rv);
            provinceName = provinceMap[rv] || rv;
        }
        ev8SetValueIfBlank('#ev8_unit_type', unitType);
        ev8SetValueIfBlank('#ev8_unit_name, #pepf_unit_name', unitName);
        // PDF form unit type checkboxes
        if (unitType && $('input[name="pepf_unit_type_check[]"]:checked').length === 0) {
            $('input[name="pepf_unit_type_check[]"][value="' + unitType + '"]').prop('checked', true);
        }
        ev8SetValueIfBlank('#pepf_center_name, [name="pepf_center_name"]', centerName);
        ev8SetValueIfBlank('#pepf_province_name, [name="pepf_province_name"]', provinceName);
    }

    // --- Victim / Suffer → person list (1.1) ---
    var sufferFirst = (data.suffer_first_name || '').toString().trim();
    var sufferLast = (data.suffer_last_name || '').toString().trim();
    var sufferFullName = [sufferFirst, sufferLast].filter(Boolean).join(' ');
    if (sufferFullName) {
        // Fill first person row if still blank
        var $stdPersonName = $('[name="ev8_person_name[]"]').first();
        var $pdfPersonName = $('[name="pepf_person_name[]"]').first();
        if ($stdPersonName.length && !$stdPersonName.val()) $stdPersonName.val(sufferFullName);
        if ($pdfPersonName.length && !$pdfPersonName.val()) $pdfPersonName.val(sufferFullName);
    }

    // --- basic_Info → purpose detail (Section 2) ---
    var basicInfo = (data.basic_Info || '').toString().trim();
    if (basicInfo) {
        ev8SetValueIfBlank('#ev8_purpose_detail, #pepf_purpose_detail', basicInfo);
    }

    // --- inspection date/time fallback: use incident date if blank ---
    if (occurDateTime.date) {
        ev8SetValueIfBlank('#ev8_inspect_date, #pepf_inspect_date', occurDateTime.date);
    }
    if (occurDateTime.time) {
        ev8SetValueIfBlank('#ev8_inspect_time, #pepf_inspect_time', occurDateTime.time);
    }

    // --- sign date fallback: use today or receive date ---
    var signDateFallback = receiveDateTime.date || '';
    ev8SetValueIfBlank('#ev8_sign_date, #pepf_sign_date', signDateFallback);

    // --- Sync derived fields ---
    if (typeof window.pepfSyncReportNo === 'function') window.pepfSyncReportNo();
    if (typeof window.syncSignDatePartsFromDate === 'function') {
        setTimeout(function() { window.syncSignDatePartsFromDate(); }, 100);
    }
}

function ev8FetchReceiveNotiData(incidentId, onSuccess, onError) {
    $.ajax({
        url: '/csims/api/ReceiveNoti/getDataByID.php',
        type: 'GET', dataType: 'json', data: { id: incidentId },
        success: function(response) {
            if (response && response.status === 'success' && response.data) {
                if (typeof onSuccess === 'function') onSuccess(response.data);
            } else { if (typeof onError === 'function') onError(response); }
        },
        error: function(xhr, status, error) {
            if (typeof onError === 'function') onError({ xhr: xhr, status: status, error: error });
        }
    });
}

window.prefillIncidentDataForPersonEvidence = function(incidentId) {
    var id = incidentId || ev8GetReceiveNotiId();
    if (!id) return;
    ev8FetchReceiveNotiData(id, function(data) { ev8ApplyReceiveNotiData(data); }, function(err) {
        console.warn('[EV8 Prefill] cannot load receive noti data', err);
    });
};

window.loadPersonEvidenceDataToModal = function(incidentId, onComplete) {
    $.ajax({
        url: './api/incidentCheckList/getPersonEvidenceData.php',
        method: 'GET',
        data: { incident_id: incidentId },
        dataType: 'json',
        success: function(res) {
            try {
                if (!(res && res.success && res.data && res.data.checklist_data)) {
                    if (typeof window.prefillIncidentDataForPersonEvidence === 'function') {
                        window.prefillIncidentDataForPersonEvidence(incidentId);
                    }
                    return;
                }

                var rd = res.data;
                var d = rd.checklist_data;

                // Keep hidden keys in sync for both forms.
                $('#receiveNoti_id_ev8, #pepf_receiveNoti_id').val(incidentId || rd.incident_id || '');

                // Edit info banner.
                if (rd.edit_info) {
                    var ei = rd.edit_info;
                    var editCount = ei.count_edit || 0;
                    $('#editCountEV8, #editCountEV8Pdf').text(editCount);

                    var editDateStr = '-';
                    if (ei.edit_date) {
                        var ed = new Date(ei.edit_date);
                        if (!isNaN(ed.getTime())) {
                            var dd = String(ed.getDate()).padStart(2, '0');
                            var mm = String(ed.getMonth() + 1).padStart(2, '0');
                            var yyyy = ed.getFullYear() + 543;
                            var hh = String(ed.getHours()).padStart(2, '0');
                            var mi = String(ed.getMinutes()).padStart(2, '0');
                            editDateStr = dd + '/' + mm + '/' + yyyy + ' ' + hh + ':' + mi + ' น.';
                        }
                    }
                    $('#editDateEV8, #editDateEV8Pdf').text(editDateStr);
                    $('#editInfoEV8, #editInfoEV8Pdf').removeClass('d-none');
                }

                // General info.
                if (d.general_info) {
                    var g = d.general_info;
                    if (g.doc_no) {
                        $('#doc_no_ev8, #pepf_doc_no').val(g.doc_no);
                        $('#receiveNoti_No_ev8').text(g.doc_no);
                    }
                    if (g.report_no) {
                        $('#report_no_ev8, #pepf_report_no').val(g.report_no);
                        $('#receiveNotiReportNo_ev8').text(g.report_no);
                    }
                    if (g.receive_date) $('#ev8_receive_date, #pepf_receive_date').val(g.receive_date);
                    if (g.receive_time) $('#ev8_receive_time, #pepf_receive_time').val(g.receive_time);
                    if (g.unit_type) {
                        $('#ev8_unit_type').val(g.unit_type);
                        // Also set PDF form checkbox for unit type
                        $('input[name="pepf_unit_type_check[]"][value="' + g.unit_type + '"]').prop('checked', true);
                    }
                    if (g.unit_name) $('#ev8_unit_name, #pepf_unit_name').val(g.unit_name);

                    if (g.notify_method) {
                        var nm = Array.isArray(g.notify_method) ? g.notify_method : [g.notify_method];
                        nm.forEach(function(v) {
                            $('input[name="ev8_notify_method[]"][value="' + v + '"]').prop('checked', true);
                            var pdfVal = (v === 'ตามหนังสือ') ? 'ทางหนังสือ' : v;
                            $('input[name="pepf_notify_method[]"][value="' + pdfVal + '"]').prop('checked', true);
                        });
                    }
                    if (g.notify_method_other_text) {
                        $('#ev8_notify_other_text').val(g.notify_method_other_text).show().prop('disabled', false);
                        $('#pepf_notify_method_other_text').val(g.notify_method_other_text);
                    }

                    if (g.police_station) {
                        ev8SetSelectValueWithOption($('#ev8_police_station'), g.police_station);
                        ev8SetSelectValueWithOption($('#pepf_police_station'), g.police_station);
                    }

                    if (g.document_no) $('#ev8_document_no, #pepf_document_no').val(g.document_no);
                    { var _dd8 = g.document_date || new Date().toISOString().slice(0,10); $('#ev8_document_date, #pepf_document_date').val(_dd8); }
                    if (g.case_no) $('#ev8_case_no, #pepf_case_no, #pepf_case_no_display').val(window.toThaiDocNo ? toThaiDocNo(g.case_no) : g.case_no);
                    if (g.incident_location) $('#ev8_incident_location, #pepf_incident_location').val(g.incident_location);
                    if (g.incident_date) $('#ev8_incident_date, #pepf_incident_date').val(g.incident_date);
                    if (g.incident_time) $('#ev8_incident_time, #pepf_incident_time').val(g.incident_time);
                    if (g.investigator_name) $('#ev8_investigator_name, #pepf_investigator_name').val(g.investigator_name);
                    if (g.send_request) $('#ev8_send_request, #pepf_send_request').val(g.send_request);
                    if (!g.case_no && g.doc_no) $('#pepf_case_no_display').val(window.toThaiDocNo ? toThaiDocNo(g.doc_no) : g.doc_no);
                }

                // Section 1 person list (ข้ามแถวว่างที่ไม่มีชื่อ).
                if (Array.isArray(d.persons) && d.persons.length > 0) {
                    var filteredPersons = d.persons.filter(function(p) {
                        var name = (p && typeof p === 'object') ? (p.name || '') : (p || '');
                        return $.trim(name) !== '';
                    });
                    filteredPersons.forEach(function(p, idx) {
                        if (idx > 0) {
                            if (typeof addPersonItemEV8 === 'function') addPersonItemEV8();
                            if (typeof window.pepfAddPersonItem === 'function') window.pepfAddPersonItem();
                        }
                        var prefix = (p && typeof p === 'object') ? (p.prefix || '') : '';
                        var name = (p && typeof p === 'object') ? (p.name || '') : (p || '');
                        var stdPrefixRows = $('[name="ev8_person_prefix[]"]');
                        var stdNameRows = $('[name="ev8_person_name[]"]');
                        var pdfPrefixRows = $('[name="pepf_person_prefix[]"]');
                        var pdfNameRows = $('[name="pepf_person_name[]"]');
                        if (stdPrefixRows.eq(idx).length) stdPrefixRows.eq(idx).val(prefix);
                        if (stdNameRows.eq(idx).length) stdNameRows.eq(idx).val(name);
                        if (pdfPrefixRows.eq(idx).length) pdfPrefixRows.eq(idx).val(prefix);
                        if (pdfNameRows.eq(idx).length) pdfNameRows.eq(idx).val(name);
                    });
                }

                // Section 3.1 person info (ข้ามแถวว่างที่ไม่มีชื่อ/บัตร).
                if (Array.isArray(d.person_info) && d.person_info.length > 0) {
                    d.person_info = d.person_info.filter(function(info) {
                        if (!info) return false;
                        return $.trim(info.fullname || '') !== '' || $.trim(info.id_card || '') !== '' || $.trim(info.passport || '') !== '';
                    });
                    d.person_info.forEach(function(info, idx) {
                        if (idx > 0) {
                            if (typeof addPersonInfoEV8 === 'function') addPersonInfoEV8();
                            if (typeof window.pepfAddPersonInfo === 'function') window.pepfAddPersonInfo();
                        }
                        var row = info || {};
                        var setRow = function(name, value) {
                            var stdRows = $('[name="ev8_' + name + '[]"]');
                            var pdfRows = $('[name="pepf_' + name + '[]"]');
                            if (stdRows.eq(idx).length) stdRows.eq(idx).val(value || '');
                            if (pdfRows.eq(idx).length) pdfRows.eq(idx).val(value || '');
                        };
                        setRow('info_prefix', row.prefix || '');
                        setRow('info_fullname', row.fullname || '');
                        setRow('info_id_card', row.id_card || '');
                        setRow('info_passport', row.passport || '');
                        setRow('info_height', row.height || '');
                        setRow('info_age', row.age || '');
                        setRow('info_skin', row.skin || '');
                        setRow('info_hand', row.hand || '');
                        setRow('info_feature', row.feature || '');
                    });
                }

                // Section 3.2 evidences.
                var evidenceRows = [];
                if (Array.isArray(d.evidences) && d.evidences.length > 0) {
                    evidenceRows = d.evidences;
                } else if (Array.isArray(d.evidence_items) && d.evidence_items.length > 0) {
                    evidenceRows = d.evidence_items;
                }
                // กรองแถวว่างที่ไม่มีรายละเอียด
                evidenceRows = evidenceRows.filter(function(ev) {
                    if (!ev) return false;
                    var detail = (typeof ev === 'object') ? (ev.detail || ev.item || ev.description || '') : (ev || '');
                    var qty = (typeof ev === 'object') ? (ev.qty || '') : '';
                    return $.trim(detail) !== '' || $.trim(qty) !== '';
                });
                if (evidenceRows.length > 0) {
                    evidenceRows.forEach(function(ev, idx) {
                        if (idx > 0) {
                            if (typeof addEvidenceDetailEV8 === 'function') addEvidenceDetailEV8();
                            if (typeof window.pepfAddEvidenceDetail === 'function') window.pepfAddEvidenceDetail();
                        }
                        var detail = (ev && typeof ev === 'object') ? (ev.detail || ev.item || ev.description || '') : (ev || '');
                        var qty = (ev && typeof ev === 'object') ? (ev.qty || '') : '';
                        var lab = (ev && typeof ev === 'object') ? window.labUnitsToString(ev.lab_unit) : '';

                        var stdDesc = $('[name="ev8_evidence_desc[]"]');
                        var stdQty = $('[name="ev8_evidence_qty[]"]');
                        var stdLab = $('[name="ev8_lab_unit[]"]');
                        var pdfDesc = $('[name="pepf_evidence_desc[]"]');
                        var pdfQty = $('[name="pepf_evidence_qty[]"]');
                        var pdfLab = $('[name="pepf_lab_unit[]"]');

                        if (stdDesc.eq(idx).length) stdDesc.eq(idx).val(detail);
                        if (stdQty.eq(idx).length) stdQty.eq(idx).val(qty);
                        if (stdLab.eq(idx).length) window.setLabUnits(stdLab.eq(idx), lab);
                        if (pdfDesc.eq(idx).length) pdfDesc.eq(idx).val(detail);
                        if (pdfQty.eq(idx).length) pdfQty.eq(idx).val(qty);
                        if (pdfLab.eq(idx).length) {
                            window.setLabUnits(pdfLab.eq(idx), lab);
                            var $pdfSel = pdfLab.eq(idx).closest('.pepf-ed-line2, .pepr-line-input').find('select.lab-unit-multi');
                            if ($pdfSel.length) $pdfSel.trigger('change');
                        }
                    });
                }

                // Section 2 + 3.
                if (d.purpose && d.purpose.detail) $('#ev8_purpose_detail, #pepf_purpose_detail').val(d.purpose.detail);
                if (d.inspection) {
                    if (d.inspection.location) $('#ev8_inspect_location, #pepf_inspect_location').val(d.inspection.location);
                    if (d.inspection.date) $('#ev8_inspect_date, #pepf_inspect_date').val(d.inspection.date);
                    if (d.inspection.time) $('#ev8_inspect_time, #pepf_inspect_time').val(d.inspection.time);
                }

                if (d.evidence_handling) {
                    var eh = d.evidence_handling;
                    if (eh.witness_name) $('#ev8_witness_name, #pepf_witness_name').val(eh.witness_name);
                    if (eh.witness_form) $('#ev8_witness_form, #pepf_witness_form').val(eh.witness_form);
                    if (eh.witness_detail) $('#pepf_witness_detail').val(eh.witness_detail);
                    if (eh.handover_method) $('#ev8_handover_method').val(eh.handover_method);
                    if (Array.isArray(eh.handover_method_checks)) {
                        eh.handover_method_checks.forEach(function(v) {
                            $('input[name="pepf_handover_method_check[]"][value="' + v + '"]').prop('checked', true);
                        });
                    }
                    if (eh.handover_method_detail) $('#pepf_handover_method_detail').val(eh.handover_method_detail);
                    if (eh.handover_item_ref) $('#ev8_handover_item_ref, #pepf_handover_item_ref').val(eh.handover_item_ref);
                    if (eh.handover_to) $('#ev8_handover_to, #pepf_handover_to').val(eh.handover_to);
                    if (eh.handover_purpose) $('#ev8_handover_purpose, #pepf_handover_purpose').val(eh.handover_purpose);

                    var lines = [];
                    if (Array.isArray(eh.purpose_lines) && eh.purpose_lines.length > 0) lines = eh.purpose_lines;
                    else if (eh.purpose_full) lines = (eh.purpose_full || '').split(/\r?\n/);
                    if (lines.length > 0) {
                        var lineFields = $('[name="pepf_handover_purpose"], [name^="pepf_handover_more_"]');
                        lineFields.each(function(i) { $(this).val(lines[i] || ''); });
                        $('#ev8_handover_purpose').val(lines.join('\n').replace(/\n+$/g, ''));
                    }
                }

                // Inspectors.
                if (Array.isArray(d.inspectors) && d.inspectors.length > 0) {
                    d.inspectors.forEach(function(insp, idx) {
                        var inspId = (insp && typeof insp === 'object') ? (insp.id || insp.user_id || insp.value || '') : insp;
                        if (!inspId) return;
                        if (idx > 0) {
                            $('#btn_add_inspector_ev8').trigger('click');
                            if (typeof window.pepfAddInspector === 'function') window.pepfAddInspector();
                        }
                        var stdRows = $('#ev8_inspector_container .ev8-inspector-select');
                        var pdfRows = $('#pepf_inspector_container select[name="pepf_inspector_id[]"]');
                        if (stdRows.eq(idx).length) stdRows.eq(idx).val(inspId).trigger('change');
                        if (pdfRows.eq(idx).length) pdfRows.eq(idx).val(inspId).trigger('change');
                    });

                    if ((!d.signer || (!d.signer.id && !d.signer.fullname && !d.signer.name)) && $('#pepf_signer_fullname').length) {
                        var firstInspectorId = (d.inspectors[0] && typeof d.inspectors[0] === 'object') ? (d.inspectors[0].id || d.inspectors[0].user_id || '') : d.inspectors[0];
                        if (firstInspectorId) {
                            $('#pepf_signer_id').val(firstInspectorId);
                            $('#pepf_signer_fullname').val(firstInspectorId).trigger('change');
                        }
                    }
                }

                // Receiver / sender.
                if (d.handover) {
                    var ho = d.handover;
                    if (ho.receiver_id) $('#ev8_receiver_id, #pepf_receiver_id').val(ho.receiver_id).trigger('change');
                    if (ho.receiver_pos) $('#ev8_receiver_position, #pepf_receiver_position').val(ho.receiver_pos);
                    if (ho.deliverer_id) $('#ev8_sender_id, #pepf_sender_id').val(ho.deliverer_id).trigger('change');
                    if (ho.deliverer_pos) $('#ev8_sender_position, #pepf_sender_position').val(ho.deliverer_pos);
                    if (ho.inspection_end_date) $('#ev8_sign_date, #pepf_sign_date').val(ho.inspection_end_date).trigger('change');
                }

                if (d.signer) {
                    var sg = d.signer;
                    if (sg.id) {
                        $('#pepf_signer_id').val(sg.id);
                        $('#pepf_signer_fullname').val(sg.id).trigger('change');
                    }
                    if (sg.fullname || sg.name) {
                        var signerValue = sg.id || '';
                        if (!signerValue && $('#pepf_signer_fullname option').filter(function() { return $(this).text() === (sg.fullname || sg.name); }).length) {
                            signerValue = $('#pepf_signer_fullname option').filter(function() { return $(this).text() === (sg.fullname || sg.name); }).first().val();
                            $('#pepf_signer_id').val(signerValue);
                        }
                        if (signerValue) $('#pepf_signer_fullname').val(signerValue).trigger('change');
                    }
                    if (sg.position) $('#pepf_signer_position').val(sg.position);
                }

                // PDF form specific.
                if (d.pdf_form) {
                    var pf = d.pdf_form;
                    if (pf.report_ref) $('[name="pepf_report_ref"]').val(pf.report_ref).trigger('input');
                    if (pf.report_year) $('[name="pepf_report_year"]').val(pf.report_year).trigger('input');
                    if (Array.isArray(pf.unit_type_checks)) {
                        pf.unit_type_checks.forEach(function(v) {
                            $('input[name="pepf_unit_type_check[]"][value="' + v + '"]').prop('checked', true);
                        });
                    }
                    if (pf.center_name) $('[name="pepf_center_name"]').val(pf.center_name);
                    if (pf.province_name) $('[name="pepf_province_name"]').val(pf.province_name);
                }

                // Photo id records.
                if (d.photo_records) {
                    if (d.photo_records.start) $('#ev8_photo_id_start, [name="photo_id_start_ev8"]').val(d.photo_records.start);
                    if (d.photo_records.end) $('#ev8_photo_id_end, [name="photo_id_end_ev8"]').val(d.photo_records.end);
                    if (d.photo_records.amount) $('#ev8_photo_amount, [name="photo_amount_ev8"]').val(d.photo_records.amount);
                }

                // Signatures (รองรับทั้ง BLOB file_id, base64, และ filename แบบเก่า)
                if (d.signatures) {
                    var drawSignatureToCanvas = function(canvasId, sigInfo) {
                        if (!sigInfo) return;
                        // ★ BLOB: ใช้ getFile.php?id=N (เหมือนฟอร์มระเบิด)
                        var sigUrl = '';
                        if (sigInfo.file_id) {
                            sigUrl = './api/incidentCheckList/getFile.php?id=' + sigInfo.file_id;
                        } else if (sigInfo.base64) {
                            sigUrl = sigInfo.base64;
                        } else if (sigInfo.filename) {
                            sigUrl = './uploads/checklist_signatures/' + sigInfo.filename;
                        }
                        if (!sigUrl) return;
                        setTimeout(function() {
                            var cvs = document.getElementById(canvasId);
                            if (!cvs) return;
                            var ctx = cvs.getContext('2d');
                            if (!ctx) return;
                            var img = new Image();
                            img.crossOrigin = 'anonymous';
                            img.onload = function() {
                                ctx.clearRect(0, 0, cvs.width, cvs.height);
                                ctx.drawImage(img, 0, 0, cvs.width, cvs.height);
                            };
                            img.src = sigUrl;
                        }, 180);
                    };

                    drawSignatureToCanvas('sig-canvas-ev8-sender', d.signatures.signer_sig || d.signatures.deliverer_sig);
                    drawSignatureToCanvas('sig-canvas-ev8-receiver', d.signatures.receiver_sig);
                    drawSignatureToCanvas('pepf_sig_sender', d.signatures.deliverer_sig || d.signatures.signer_sig);
                    drawSignatureToCanvas('pepf_sig_receiver', d.signatures.receiver_sig);
                }

                // Photos (รองรับทั้ง BLOB file_id, base64, และ filename แบบเก่า)
                if (Array.isArray(d.photos) && d.photos.length > 0) {
                    window.__ev8LoadingPhotos = true; // กันไม่ให้ render เขียนทับรหัสภาพที่โหลดมา
                    attachmentStoreEV8 = [];
                    d.photos.forEach(function(p) {
                        if (!p) return;
                        // ★ BLOB: ใช้ getFile.php?id=N (เหมือนฟอร์มระเบิด)
                        var photoSrc = '';
                        if (p.file_id) {
                            photoSrc = './api/incidentCheckList/getFile.php?id=' + p.file_id;
                        } else if (p.base64) {
                            photoSrc = p.base64;
                        } else if (p.filename) {
                            photoSrc = './uploads/checklist_photos/' + p.filename;
                        }
                        if (!photoSrc) return;
                        attachmentStoreEV8.push({
                            id: 'ev8_existing_' + (p.file_id || Date.now() + '_' + Math.random().toString(36).substr(2, 5)),
                            src: photoSrc,
                            name: p.original_name || p.filename || 'photo.png',
                            file: null,
                            existing: true,
                            db_file_id: p.file_id || null,
                            db_filename: p.filename || null
                        });
                    });
                    if (typeof renderEV8AttachmentGrid === 'function') renderEV8AttachmentGrid();
                    if (typeof window.pepfRenderPhotosFromStore === 'function') window.pepfRenderPhotosFromStore();
                    window.__ev8LoadingPhotos = false; // โหลดเสร็จ
                }

                if (typeof window.pepfToggleNotifyOther === 'function') window.pepfToggleNotifyOther();
                if (typeof window.pepfRefreshAutoWrapGroups === 'function') window.pepfRefreshAutoWrapGroups();
                if (typeof window.pepfUpdatePageNumbers === 'function') window.pepfUpdatePageNumbers();

                // Always try to fill still-blank fields from receive noti data
                if (typeof window.prefillIncidentDataForPersonEvidence === 'function') {
                    window.prefillIncidentDataForPersonEvidence(incidentId);
                }
                // Sync sign date parts (day/month/year) from hidden pepf_sign_date
                setTimeout(function() {
                    if (typeof window.syncSignDatePartsFromDate === 'function') window.syncSignDatePartsFromDate();
                    if (typeof window.pepfSyncReportNo === 'function') window.pepfSyncReportNo();
                }, 300);
            } finally {
                if (typeof onComplete === 'function') onComplete();
            }
        },
        error: function() {
            if (typeof window.prefillIncidentDataForPersonEvidence === 'function') {
                window.prefillIncidentDataForPersonEvidence(incidentId);
            }
            if (typeof onComplete === 'function') onComplete();
        }
    });
};

// =========================================================
// Person Evidence (type 08): Submit Data (EV7 flow style)
// =========================================================
async function prepareDataForSubmissionPersonEvidence() {
    const getCheckboxValues = (name) => {
        const values = [];
        $('input[name="' + name + '"]:checked').each(function() { values.push($(this).val()); });
        return values;
    };

    const collectValues = (selector) => {
        var values = [];
        $(selector).each(function() {
            var value = $.trim($(this).val() || '');
            if (value) values.push(value);
        });
        return values;
    };

    const chooseBestValues = (primarySelector, secondarySelector) => {
        var primaryValues = collectValues(primarySelector);
        var secondaryValues = secondarySelector ? collectValues(secondarySelector) : [];
        if (secondaryValues.length > primaryValues.length) return secondaryValues;
        if (secondaryValues.length < primaryValues.length) return primaryValues;
        if (secondaryValues.join('\n').length > primaryValues.join('\n').length) return secondaryValues;
        return primaryValues;
    };

    const mapPdfNotifyToStd = (values) => {
        return (values || []).map(function(v) {
            if (v === 'ทางหนังสือ') return 'ตามหนังสือ';
            return v;
        });
    };

    const stdNotify = getCheckboxValues('ev8_notify_method[]');
    const pdfNotify = mapPdfNotifyToStd(getCheckboxValues('pepf_notify_method[]'));
    // ★ FIX: ใช้ค่าจาก modal ที่ user กำลังใช้งานจริง (PDF modal เปิดอยู่ → ใช้ PDF checkbox)
    var preferPdfNotify = $('#personEvidenceFormPdfModal').hasClass('show');
    var mergedNotify = preferPdfNotify
        ? (pdfNotify.length > 0 ? pdfNotify : stdNotify)
        : (stdNotify.length > 0 ? stdNotify : pdfNotify);

    const payload = {
        receiveNoti_id_ev8: $('#receiveNoti_id_ev8').val() || $('#pepf_receiveNoti_id').val(),
        doc_no_ev8: $('#doc_no_ev8').val() || $('#pepf_doc_no').val(),
        report_no_ev8: $('#report_no_ev8').val() || $('#pepf_report_no').val(),

        // Section 1: การรับแจ้ง
        ev8_receive_date: $('[name="ev8_receive_date"]').val() || $('[name="pepf_receive_date"]').val(),
        ev8_receive_time: $('[name="ev8_receive_time"]').val() || $('[name="pepf_receive_time"]').val(),
        ev8_unit_type: $('[name="ev8_unit_type"]').val() || '',
        ev8_unit_name: $('[name="ev8_unit_name"]').val() || $('[name="pepf_unit_name"]').val(),
        'ev8_notify_method': mergedNotify,
        ev8_notify_method_other_text: $('[name="ev8_notify_method_other_text"]').val() || $('[name="pepf_notify_method_other_text"]').val() || '',
        ev8_police_station: $('#ev8_police_station').val() || $('[name="pepf_police_station"]').val(),
        ev8_document_no: $('[name="ev8_document_no"]').val() || $('[name="pepf_document_no"]').val(),
        ev8_document_date: $('[name="ev8_document_date"]').val() || $('[name="pepf_document_date"]').val(),
        ev8_case_no: $('[name="ev8_case_no"]').val() || $('[name="pepf_case_no"]').val(),
        ev8_incident_location: $('[name="ev8_incident_location"]').val() || $('[name="pepf_incident_location"]').val(),
        ev8_incident_date: $('[name="ev8_incident_date"]').val() || $('[name="pepf_incident_date"]').val(),
        ev8_incident_time: $('[name="ev8_incident_time"]').val() || $('[name="pepf_incident_time"]').val(),
        ev8_investigator_name: $('[name="ev8_investigator_name"]').val() || $('[name="pepf_investigator_name"]').val(),
        ev8_send_request: $('[name="ev8_send_request"]').val() || $('[name="pepf_send_request"]').val(),

        // Dynamic arrays
        'ev8_person_prefix': [],
        'ev8_person_name': [],
        'ev8_info_prefix': [],
        'ev8_info_fullname': [],
        'ev8_info_id_card': [],
        'ev8_info_passport': [],
        'ev8_info_height': [],
        'ev8_info_age': [],
        'ev8_info_skin': [],
        'ev8_info_hand': [],
        'ev8_info_feature': [],
        'ev8_evidence_desc': [],
        'ev8_evidence_qty': [],
        'ev8_lab_unit': [],

        // Section 2 / 3
        ev8_purpose_detail: $('[name="ev8_purpose_detail"]').val() || $('[name="pepf_purpose_detail"]').val(),
        ev8_inspect_location: $('[name="ev8_inspect_location"]').val() || $('[name="pepf_inspect_location"]').val(),
        ev8_inspect_date: $('[name="ev8_inspect_date"]').val() || $('[name="pepf_inspect_date"]').val(),
        ev8_inspect_time: $('[name="ev8_inspect_time"]').val() || $('[name="pepf_inspect_time"]').val(),

        // 3.3 handling
        ev8_witness_name: $('[name="ev8_witness_name"]').val() || $('[name="pepf_witness_name"]').val(),
        ev8_witness_form: $('[name="ev8_witness_form"]').val() || $('[name="pepf_witness_form"]').val(),
        ev8_witness_detail: $('[name="pepf_witness_detail"]').val() || '',
        ev8_handover_method: $('[name="ev8_handover_method"]').val() || '',
        'pepf_handover_method_check': getCheckboxValues('pepf_handover_method_check[]'),
        ev8_handover_method_detail: $('[name="pepf_handover_method_detail"]').val() || '',
        ev8_handover_item_ref: $('[name="ev8_handover_item_ref"]').val() || $('[name="pepf_handover_item_ref"]').val(),
        ev8_handover_to: $('[name="ev8_handover_to"]').val() || $('[name="pepf_handover_to"]').val(),
        ev8_handover_purpose: $('[name="ev8_handover_purpose"]').val() || $('[name="pepf_handover_purpose"]').val(),
        'pepf_handover_more_lines': [],
        pepf_handover_purpose_full: '',

        // Inspector / sign
        'ev8_inspector_id': [],
        ev8_sign_date: $('[name="ev8_sign_date"]').val() || $('[name="pepf_sign_date"]').val() || '',
        ev8_receiver_id: $('[name="ev8_receiver_id"]').val() || $('[name="pepf_receiver_id"]').val() || '',
        ev8_receiver_position: $('[name="ev8_receiver_position"]').val() || $('[name="pepf_receiver_position"]').val() || '',
        ev8_sender_id: $('[name="ev8_sender_id"]').val() || $('[name="pepf_sender_id"]').val() || '',
        ev8_sender_position: $('[name="ev8_sender_position"]').val() || $('[name="pepf_sender_position"]').val() || '',
        pepf_signer_id: $('[name="pepf_signer_id"]').val() || $('[name="pepf_signer_fullname"]').val() || '',
        pepf_signer_fullname: ($('#pepf_signer_fullname').val() ? ($('#pepf_signer_fullname option:selected').data('fullname') || $('#pepf_signer_fullname option:selected').text() || '') : ''),
        pepf_signer_position: $('[name="pepf_signer_position"]').val() || '',

        // PDF specific
        pepf_report_ref: $('[name="pepf_report_ref"]').val() || '',
        pepf_report_year: $('[name="pepf_report_year"]').val() || '',
        'pepf_unit_type_check': getCheckboxValues('pepf_unit_type_check[]'),
        pepf_center_name: $('[name="pepf_center_name"]').val() || '',
        pepf_province_name: $('[name="pepf_province_name"]').val() || '',

        // Photo records
        ev8_photo_id_start: $('[name="ev8_photo_id_start"]').val() || $('[name="photo_id_start_ev8"]').val() || '',
        ev8_photo_id_end: $('[name="ev8_photo_id_end"]').val() || $('[name="photo_id_end_ev8"]').val() || '',
        ev8_photo_amount: $('[name="ev8_photo_amount"]').val() || $('[name="photo_amount_ev8"]').val() || '',
    };

    // choose fuller source between standard/PDF
    payload['ev8_person_prefix'] = chooseBestValues('[name="ev8_person_prefix[]"]', '[name="pepf_person_prefix[]"]');
    payload['ev8_person_name'] = chooseBestValues('[name="ev8_person_name[]"]', '[name="pepf_person_name[]"]');
    payload['ev8_info_prefix'] = chooseBestValues('[name="ev8_info_prefix[]"]', '[name="pepf_info_prefix[]"]');
    payload['ev8_info_fullname'] = chooseBestValues('[name="ev8_info_fullname[]"]', '[name="pepf_info_fullname[]"]');
    payload['ev8_info_id_card'] = chooseBestValues('[name="ev8_info_id_card[]"]', '[name="pepf_info_id_card[]"]');
    payload['ev8_info_passport'] = chooseBestValues('[name="ev8_info_passport[]"]', '[name="pepf_info_passport[]"]');
    payload['ev8_info_height'] = chooseBestValues('[name="ev8_info_height[]"]', '[name="pepf_info_height[]"]');
    payload['ev8_info_age'] = chooseBestValues('[name="ev8_info_age[]"]', '[name="pepf_info_age[]"]');
    payload['ev8_info_skin'] = chooseBestValues('[name="ev8_info_skin[]"]', '[name="pepf_info_skin[]"]');
    payload['ev8_info_hand'] = chooseBestValues('[name="ev8_info_hand[]"]', '[name="pepf_info_hand[]"]');
    payload['ev8_info_feature'] = chooseBestValues('[name="ev8_info_feature[]"]', '[name="pepf_info_feature[]"]');
    payload['ev8_evidence_desc'] = chooseBestValues('[name="ev8_evidence_desc[]"]', '[name="pepf_evidence_desc[]"]');
    payload['ev8_evidence_qty'] = chooseBestValues('[name="ev8_evidence_qty[]"]', '[name="pepf_evidence_qty[]"]');

    payload['ev8_lab_unit'] = (function() {
        var stdVals = []; var pdfVals = [];
        $('[name="ev8_lab_unit[]"]').each(function() { stdVals.push(window.getLabUnitsString(this)); });
        $('[name="pepf_lab_unit[]"]').each(function() { pdfVals.push(window.getLabUnitsString(this)); });
        var stdFilled = stdVals.filter(Boolean).length;
        var pdfFilled = pdfVals.filter(Boolean).length;
        return pdfFilled > stdFilled ? pdfVals : stdVals;
    })();

    payload['ev8_inspector_id'] = chooseBestValues(
        '#ev8_inspector_container .ev8-inspector-select',
        '#pepf_inspector_container select[name="pepf_inspector_id[]"]'
    );

    var handoverLines = [];
    $('[name="pepf_handover_purpose"], [name^="pepf_handover_more_"]').each(function() {
        handoverLines.push($(this).val() || '');
    });
    var stdPurpose = ($('[name="ev8_handover_purpose"]').val() || '').trim();
    if (handoverLines.join('').trim() === '' && stdPurpose) handoverLines = stdPurpose.split(/\r?\n/);
    payload['pepf_handover_more_lines'] = handoverLines;
    payload['pepf_handover_purpose_full'] = handoverLines.join('\n').replace(/\n+$/g, '');

    const submitData = new FormData();
    submitData.append('payload_json', JSON.stringify(payload));
    for (const [key, val] of Object.entries(payload)) {
        if (Array.isArray(val)) {
            val.forEach(function(v) { submitData.append(key + '[]', v); });
        } else {
            submitData.append(key, val || '');
        }
    }

    if (typeof attachmentStoreEV8 !== 'undefined' && attachmentStoreEV8.length > 0) {
        payload.ev8_photo_amount = payload.ev8_photo_amount || String(attachmentStoreEV8.length);
        if (!payload.ev8_photo_id_start) payload.ev8_photo_id_start = '1';
        if (!payload.ev8_photo_id_end) payload.ev8_photo_id_end = String(attachmentStoreEV8.length);
    }

    var canvasHasDrawing = function(canvas) {
        if (!canvas) return false;
        var ctx = canvas.getContext('2d');
        if (!ctx) return false;
        var pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        for (var i = 3; i < pixels.length; i += 4) {
            if (pixels[i] !== 0) return true;
        }
        return false;
    };

    var senderCanvasStd = document.getElementById('sig-canvas-ev8-sender');
    var receiverCanvasStd = document.getElementById('sig-canvas-ev8-receiver');
    var receiverCanvasPdf = document.getElementById('pepf_sig_receiver');
    var senderCanvasPdf = document.getElementById('pepf_sig_sender');

    var hasSenderStd = !!(senderCanvasStd && canvasHasDrawing(senderCanvasStd));
    var hasReceiverStd = !!(receiverCanvasStd && canvasHasDrawing(receiverCanvasStd));
    var hasReceiverPdf = !!(receiverCanvasPdf && canvasHasDrawing(receiverCanvasPdf));
    var hasSenderPdf = !!(senderCanvasPdf && canvasHasDrawing(senderCanvasPdf));

    // Explicitly tell backend if signature is present after user edits (including clear action).
    submitData.append('ev8_sender_signature_present', (hasSenderStd || hasSenderPdf) ? '1' : '0');
    submitData.append('ev8_receiver_signature_present', (hasReceiverStd || hasReceiverPdf) ? '1' : '0');

    // ★ FIX: เลือก canvas ตาม modal ที่เปิดอยู่ (PDF modal เปิด → ใช้ PDF canvas ก่อน)
    var pickCanvasDataUrl = function(canvasIds) {
        for (var ci = 0; ci < canvasIds.length; ci++) {
            var c = document.getElementById(canvasIds[ci]);
            if (!c || !canvasHasDrawing(c)) continue;
            try {
                var dUrl = c.toDataURL('image/png');
                if (dUrl && dUrl !== 'data:,') return dUrl;
            } catch (ex) { console.warn('[EV8] cannot export canvas:', canvasIds[ci], ex); }
        }
        return '';
    };

    var preferPdf = $('#personEvidenceFormPdfModal').hasClass('show');
    var senderSigDataUrl = preferPdf
        ? pickCanvasDataUrl(['pepf_sig_sender', 'sig-canvas-ev8-sender'])
        : pickCanvasDataUrl(['sig-canvas-ev8-sender', 'pepf_sig_sender']);
    var receiverSigDataUrl = preferPdf
        ? pickCanvasDataUrl(['pepf_sig_receiver', 'sig-canvas-ev8-receiver'])
        : pickCanvasDataUrl(['sig-canvas-ev8-receiver', 'pepf_sig_receiver']);

    if (senderSigDataUrl) {
        submitData.append('ev8_signer_signature_data', senderSigDataUrl);
        submitData.append('pepf_sender_signature_data', senderSigDataUrl);
    }
    if (receiverSigDataUrl) {
        submitData.append('pepf_receiver_signature_data', receiverSigDataUrl);
    }

    submitData.set('payload_json', JSON.stringify(payload));
    submitData.set('ev8_photo_id_start', payload.ev8_photo_id_start || '');
    submitData.set('ev8_photo_id_end', payload.ev8_photo_id_end || '');
    submitData.set('ev8_photo_amount', payload.ev8_photo_amount || '');

    if (typeof attachmentStoreEV8 !== 'undefined' && attachmentStoreEV8.length > 0) {
        attachmentStoreEV8.forEach(function(item) {
            if (item.file) submitData.append('incident_photos_ev8[]', item.file);
        });
    }

    if (typeof deletedExistingPhotosEV8 !== 'undefined' && deletedExistingPhotosEV8.length > 0) {
        // ส่ง file_id สำหรับลบ BLOB ใน DB (ข้อมูลใหม่)
        var deletedFileIds = deletedExistingPhotosEV8.map(function(x) {
            return x.db_file_id || x.file_id || null;
        }).filter(function(id) { return id !== null; });
        if (deletedFileIds.length > 0) {
            submitData.append('deleted_photo_file_ids', JSON.stringify(deletedFileIds));
        }
        // ส่ง filename สำหรับข้อมูลเก่า (backward compatibility)
        var deletedFilenames = deletedExistingPhotosEV8.map(function(x) {
            return x.db_filename || '';
        }).filter(function(fn) { return fn !== ''; });
        if (deletedFilenames.length > 0) {
            submitData.append('deleted_photos_ev8', JSON.stringify(deletedFilenames));
        }
    }

    Swal.fire({
        title: 'ยืนยันการบันทึกข้อมูล',
        text: 'กรุณาตรวจสอบความถูกต้องก่อนบันทึก',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#d33',
        confirmButtonText: 'ยืนยัน, บันทึกเลย!',
        cancelButtonText: 'ยกเลิก',
    }).then(async (result) => {
        if (!result.isConfirmed) return;

        const btnSave = $('#btn_save_ev8');
        const btnSavePdf = $('#btn_save_ev8_pdf');
        const btnText = btnSave.html();
        const btnTextPdf = btnSavePdf.html();
        btnSave.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> กำลังบันทึก...');
        btnSavePdf.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> กำลังบันทึก...');

        const ev8Url = './api/incidentCheckList/savePersonEvidence.php';

        if (!navigator.onLine) {
            btnSave.prop('disabled', false).html(btnText);
            btnSavePdf.prop('disabled', false).html(btnTextPdf);
            if (typeof saveChecklistOffline === 'function') await saveChecklistOffline(submitData, ev8Url, '#addCheckListModalPersonEvidence');
            return;
        }

        const backendOk = await checkBackendHealth();
        if (!backendOk) {
            btnSave.prop('disabled', false).html(btnText);
            btnSavePdf.prop('disabled', false).html(btnTextPdf);
            if (typeof saveChecklistOffline === 'function') await saveChecklistOffline(submitData, ev8Url, '#addCheckListModalPersonEvidence');
            return;
        }

        $.ajax({
            url: ev8Url,
            method: 'POST',
            data: submitData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                btnSave.prop('disabled', false).html(btnText);
                btnSavePdf.prop('disabled', false).html(btnTextPdf);
                if (response.success) {
                    var ev8Refreshed = false;
                    var refreshOnce = function() {
                        if (ev8Refreshed) return;
                        ev8Refreshed = true;
                        window.location.reload();
                    };

                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: response.message || 'บันทึกข้อมูลเรียบร้อย',
                        confirmButtonText: 'ตกลง'
                    }).then(() => {
                        ['addCheckListModalPersonEvidence', 'personEvidenceFormPdfModal'].forEach(function(id) {
                            const el = document.getElementById(id);
                            if (el) { const m = bootstrap.Modal.getInstance(el); if (m) m.hide(); }
                        });
                        refreshOnce();
                    });

                    // Fallback: ensure the page refreshes once even if the dialog flow is interrupted.
                    setTimeout(refreshOnce, 1500);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: response.message || 'ไม่สามารถบันทึกข้อมูลได้',
                        confirmButtonText: 'ตกลง'
                    });
                }
            },
            error: async function(xhr) {
                btnSave.prop('disabled', false).html(btnText);
                btnSavePdf.prop('disabled', false).html(btnTextPdf);
                console.error('[EV8] AJAX Error:', xhr.responseText);
                if (typeof saveChecklistOffline === 'function') await saveChecklistOffline(submitData, ev8Url, '#addCheckListModalPersonEvidence');
            }
        });
    });
}
</script>
