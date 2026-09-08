<?php
// ตั้ง timezone เป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// ดึงข้อมูลผู้ตรวจสำหรับ dropdown
$inspectorOptionsFPN = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
if (isset($pdo)) {
    $qryInspectorFPN = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname,
                     IFNULL(t3.position_name, '-') AS position_name
                     FROM user_profile t1 
                     LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                     LEFT JOIN user_position t3 ON t1.position_id = t3.position_id
                     ORDER BY t1.user_id DESC";
    $stmtFPN = $pdo->query($qryInspectorFPN);
    while ($rowFPN = $stmtFPN->fetch(PDO::FETCH_ASSOC)) {
        $inspectorOptionsFPN .= '<option value="' . $rowFPN['user_id'] . '" data-position="' . htmlspecialchars($rowFPN['position_name']) . '">' . htmlspecialchars($rowFPN['fullname']) . '</option>';
    }
}

// ดึงข้อมูลสถานีตำรวจ
$policeStationOptionsFPN = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qryPSFPN = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stmtPSFPN = $pdo->query($qryPSFPN);
    while ($rowPSFPN = $stmtPSFPN->fetch(PDO::FETCH_ASSOC)) {
        $policeStationOptionsFPN .= '<option value="' . htmlspecialchars($rowPSFPN['station_name']) . '">' . htmlspecialchars($rowPSFPN['station_name']) . '</option>';
    }
}

// วันที่ปัจจุบัน
$todayDateFPN = date('Y-m-d');
$todayTimeFPN = date('H:i');
?>

<!-- Modal สำหรับตรวจเก็บวัตถุพยาน ลายนิ้วมือแฝง (รูปแบบ fire_new) -->
<div class="modal fade" id="addCheckListModalFingerprintNew" aria-labelledby="addCheckListModalFingerprintNewLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addCheckListModalFingerprintNewLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4" style="position:relative;">
                <div id="fpNewLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track">
                            <div class="csims-bar-fill"></div>
                        </div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>
                <form method="post" id="incidentCheckListFormFingerprintNew" novalidate>
                    <input type="hidden" id="receiveNoti_id_fpn" name="receiveNoti_id">
                    <input type="hidden" id="doc_no_fpn" name="doc_no">
                    <input type="hidden" id="report_no_fpn" name="report_no">

                    <!-- Header เลขที่เอกสาร -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="editInfoFPN" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountFPN" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto me-3">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="receiveNoti_No_fpn"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="receiveNotiReportNo_fpn"></span>
                            </div>
                        </div>
                        <div class="bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2 flex-shrink-0">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToPdfFormFP" style="width: 3rem; height: 1.5rem; cursor: pointer;" onchange="if(this.checked){ this.checked=false; switchToFingerprintPdfForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToPdfFormFP" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

                    <!-- ==================== 1. การรับแจ้ง ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">1. การรับแจ้ง</legend>

                        <div class="row">
                            <div class="col-lg-4 col-md-4 mb-3">
                                <label class="form-label">วันที่ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="fpn_receive_date" name="fpn_receive_date" value="<?= $todayDateFPN ?>">
                            </div>
                            <div class="col-lg-4 col-md-4 mb-3">
                                <label class="form-label">เวลา <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="fpn_receive_time" name="fpn_receive_time" value="<?= $todayTimeFPN ?>" step="60">
                            </div>
                            <div class="col-lg-4 col-md-4 mb-3">
                                <label class="form-label">ปจว.ข้อที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="fpn_case_no" name="fpn_case_no">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fpn_case_no" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-4 col-md-12 mb-3">
                                <label class="form-label">การรับแจ้งทางหนังสือ สน./สภ. <span class="text-danger">*</span></label>
                                <select class="form-select" id="fpn_police_station" name="fpn_police_station"><?= $policeStationOptionsFPN ?></select>
                            </div>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <label class="form-label">ที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="fpn_letter_no" name="fpn_letter_no">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fpn_letter_no" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <label class="form-label">ลง</label>
                                <div class="input-group">
                                    <input type="date" class="form-control" id="fpn_letter_date" name="fpn_letter_date" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-5 col-md-12 mb-3">
                                <label class="form-label">ผู้ส่งของกลาง</label>
                                <select class="form-select" id="fpn_evidence_sender" name="fpn_evidence_sender"><?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งของกลาง --', $inspectorOptionsFPN) ?></select>
                            </div>
                            <div class="col-lg-5 col-md-8 mb-3">
                                <label class="form-label">ตำแหน่ง</label>
                                <input type="text" class="form-control" id="fpn_evidence_sender_position" name="fpn_evidence_sender_position" readonly>
                            </div>
                            <div class="col-lg-2 col-md-4 mb-3">
                                <label class="form-label">โทร</label>
                                <input type="tel" class="form-control fpf-phone-format" id="fpn_evidence_sender_phone" name="fpn_evidence_sender_phone" maxlength="12">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-4 col-md-12 mb-3">
                                <label class="form-label">รับวัตถุพยานตามหนังสือ</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="fpn_evidence_letter_no" name="fpn_evidence_letter_no">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fpn_evidence_letter_no" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <label class="form-label">ที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="fpn_evidence_doc_no" name="fpn_evidence_doc_no">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fpn_evidence_doc_no" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <label class="form-label">ลง</label>
                                <div class="input-group">
                                    <input type="date" class="form-control" id="fpn_evidence_doc_date" name="fpn_evidence_doc_date" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">จุดประสงค์ในการตรวจพิสูจน์</h6>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="fpn_purpose[]" value="เพื่อตรวจเก็บรอยลายนิ้วมือแฝง" id="fpn_purpose_fingerprint">
                                    <label class="form-check-label" for="fpn_purpose_fingerprint">เพื่อตรวจเก็บรอยลายนิ้วมือแฝง</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="fpn_purpose[]" value="อื่นๆ" id="fpn_purpose_other">
                                    <label class="form-check-label" for="fpn_purpose_other">อื่นๆ</label>
                                    <input type="text" class="form-control form-control-sm d-inline-block ms-2" name="fpn_purpose_other_text" id="fpn_purpose_other_text" style="width:400px;">
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="fpn_purpose_other_text" title="เขียนด้วยลายมือ" style="padding:1px 6px; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 2. วันเวลาที่เกิดเหตุ/ที่ทราบเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">2. วันเวลาที่เกิดเหตุ/ที่ทราบเหตุ</legend>

                        <div class="mb-3">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">วันเวลาที่เกิดเหตุ</h6>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="fpn_incident_date" name="fpn_incident_date" value="<?= $todayDateFPN ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="fpn_incident_time" name="fpn_incident_time" value="<?= $todayTimeFPN ?>" step="60">
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">วันเวลาที่ทราบเหตุ</h6>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="fpn_known_date" name="fpn_known_date" value="<?= $todayDateFPN ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลา/ประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="fpn_known_time" name="fpn_known_time" value="<?= $todayTimeFPN ?>" step="60">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 3. วันเวลาที่ตรวจเก็บ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">3. วันเวลาที่ตรวจเก็บ</legend>

                        <div class="mb-3">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">วันเวลาที่ทำการตรวจเก็บ</h6>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="fpn_collect_date" name="fpn_collect_date" value="<?= $todayDateFPN ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="fpn_collect_time" name="fpn_collect_time" value="<?= $todayTimeFPN ?>" step="60">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 4. ผู้ตรวจพิสูจน์ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">4. ผู้ตรวจพิสูจน์</legend>

                        <div id="fpn_inspector_container">
                            <div class="d-flex align-items-center mb-3 fpn-inspector-row">
                                <div class="text-end pe-3" style="width: 50px;">
                                    <span class="fw-bold text-secondary fpn-index-label">4.1</span>
                                </div>
                                <div class="flex-grow-1">
                                    <select class="form-select fpn-inspector-select" name="fpn_inspector_id[]">
                                        <?= $inspectorOptionsFPN ?>
                                    </select>
                                </div>
                                <div class="ms-2" style="width: 32px;"></div>
                            </div>
                        </div>

                        <div class="d-flex mt-2">
                            <div style="width: 50px;"></div>
                            <div class="flex-grow-1">
                                <button type="button" class="btn btn-outline-primary border-dashed w-100 py-2" id="btn_add_inspector_fpn">
                                    <i class="fas fa-plus-circle me-1"></i> เพิ่มผู้ตรวจ
                                </button>
                            </div>
                            <div class="ms-2" style="width: 32px;"></div>
                        </div>
                    </fieldset>

                    <!-- ==================== 5. ลักษณะการหีบห่อวัตถุพยาน ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">5. ลักษณะการหีบห่อวัตถุพยาน</legend>

                        <!-- บรรจุ -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">การบรรจุ</label>
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_package_type[]" value="บรรจุในซองวัตถุพยาน" id="fpn_pkg_envelope" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fpn_pkg_envelope">บรรจุในซองวัตถุพยาน</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_package_type[]" value="พลาสติก" id="fpn_pkg_plastic" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fpn_pkg_plastic">พลาสติก</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- การปิดผนึก -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">การปิดผนึก</label>
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_seal_condition[]" value="มีการปิดผนึกพร้อมลงลายมือชื่อกำกับ" id="fpn_seal_signed" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fpn_seal_signed">การปิดผนึกพร้อมลงลายมือชื่อกำกับ</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_seal_condition[]" value="เขียนรายละเอียดหน้าซองครบถ้วน" id="fpn_seal_detail_complete" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fpn_seal_detail_complete">เขียนรายละเอียดหน้าซองวัตถุพยาน</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ผู้เก็บ -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">ผู้เก็บ <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input fpn-radio-collector cursor-pointer" type="checkbox" name="fpn_collector_type" value="เก็บโดยเจ้าหน้าที่พิสูจน์หลักฐาน" id="fpn_collector_forensic" data-group="fpn_collector_type" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fpn_collector_forensic">เก็บไว้เจ้าหน้าที่พิสูจน์หลักฐาน</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input fpn-radio-collector cursor-pointer" type="checkbox" name="fpn_collector_type" value="เก็บโดยพนักงานสอบสวน" id="fpn_collector_investigator" data-group="fpn_collector_type" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fpn_collector_investigator">เก็บโดยพนักงานสอบสวน</label>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input fpn-radio-collector cursor-pointer" type="checkbox" name="fpn_collector_type" value="เก็บโดย อื่นๆ" id="fpn_collector_other" data-group="fpn_collector_type" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1 text-dark" for="fpn_collector_other">เก็บโดย อื่นๆ</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm" name="fpn_collector_other_text" id="fpn_collector_other_text" placeholder="ระบุ..." style="width: 200px; display: none;" disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" data-hw-targets="fpn_collector_other_text" title="เขียนด้วยลายมือ" style="padding:1px 6px; font-size:0.7rem; display:none;" id="fpn_collector_other_text_hw"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- เก็บเมื่อ / ระยะเวลา -->
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เก็บเมื่อ</label>
                                <input type="date" class="form-control" id="fpn_storage_date" name="fpn_storage_date">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" id="fpn_storage_time" name="fpn_storage_time" step="60">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ระยะเวลา (ปี)</label>
                                <input type="number" class="form-control text-center" id="fpn_duration_year" name="fpn_duration_year" min="0" placeholder="ปี">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เดือน</label>
                                <input type="number" class="form-control text-center" id="fpn_duration_month" name="fpn_duration_month" min="0" max="12" placeholder="เดือน">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">วัน</label>
                                <input type="number" class="form-control text-center" id="fpn_duration_day" name="fpn_duration_day" min="0" max="31" placeholder="วัน">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 6. ลักษณะวัตถุพยาน ==================== -->
                    <div id="fpn_section6_wrapper">
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border fpn-section6-set" data-set-index="1">
                        <legend class="fieldset-header">
                            6. ลักษณะวัตถุพยาน
                            <span class="badge bg-primary ms-2 fpn-set-label">ชุดที่ 1</span>
                        </legend>

                        <!-- จำนวนวัตถุพยาน -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">จำนวนวัตถุพยานทั้งสิ้น</label>
                                <div class="input-group">
                                    <input type="number" class="form-control text-center fpn-evidence-total" name="fpn_evidence_total[]" min="0" placeholder="0">
                                    <span class="input-group-text">รายการ</span>
                                </div>
                            </div>
                        </div>

                        <!-- รายการวัตถุพยาน -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 text-secondary"><i class="fas fa-box me-2"></i>รายการวัตถุพยาน</h6>
                                <button type="button" class="btn btn-sm btn-primary fpn-btn-add-evidence">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                </button>
                            </div>

                            <div class="fpn-evidence-container">
                                <!-- รายการที่ 1 -->
                                <div class="fpn-evidence-card card mb-3 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="badge bg-primary fpn-evidence-badge">รายการที่ 1</span>
                                            <button type="button" class="btn btn-sm btn-outline-danger fpn-btn-del-evidence">
                                                <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                            </button>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-12">
                                                <label class="form-label">เป็น</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="fpn_ev_description[]" placeholder="รายละเอียดวัตถุพยาน...">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">กว้าง/เส้นผ่าศูนย์กลาง (cm)</label>
                                                <input type="text" class="form-control text-center" name="fpn_ev_width[]">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">ยาว (cm)</label>
                                                <input type="text" class="form-control text-center" name="fpn_ev_length[]">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">สูง (cm)</label>
                                                <input type="text" class="form-control text-center" name="fpn_ev_height[]">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">จำนวน</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="fpn_ev_quantity[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">ป้ายหมายเลข</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="fpn_ev_label_no[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label">การตรวจพิสูจน์</label>
                                                <select class="form-select" name="fpn_ev_lab_unit[]">
                                                    <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                                                    <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                                    <option value="fingerprint" selected>กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                                    <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                                    <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                                    <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                                </select>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                    </fieldset>
                    </div><!-- /fpn_section6_wrapper -->

                    <!-- ปุ่มเพิ่มชุดข้อมูลที่ 6 -->
                    <div class="text-center mb-4">
                        <button type="button" class="btn btn-outline-primary border-dashed px-4 py-2" id="btn_add_section6_fpn">
                            <i class="fas fa-plus-circle me-1"></i> เพิ่มข้อมูล
                        </button>
                    </div>

                    <!-- ==================== 7. วิธีการดำเนินการ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">7. วิธีการดำเนินการ</legend>
                        <div id="fpn_method_global_container">
                            <div class="fpn-method-row d-flex align-items-center gap-2 mb-2">
                                <select class="form-select fpn-method-select" name="fpn_ev_method_name[]" style="max-width: 250px;">
                                    <option value="" selected disabled>-- เลือกวิธีการ --</option>
                                    <option value="Forensic Light Sources">Forensic Light Sources</option>
                                    <option value="Powder">Powder</option>
                                    <option value="Super Glue">Super Glue</option>
                                    <option value="Indanedione-Zine">Indanedione-Zine</option>
                                    <option value="Amido Black">Amido Black</option>
                                    <option value="Sticky Side">Sticky Side</option>
                                    <option value="Rhodamine 6G">Rhodamine 6G</option>
                                    <option value="Ninhydrin">Ninhydrin</option>
                                    <option value="Acid Yellow 7">Acid Yellow 7</option>
                                    <option value="SPR">SPR</option>
                                    <option value="Basic Yellow 40">Basic Yellow 40</option>
                                    <option value="อื่นๆ">อื่นๆ</option>
                                </select>
                                <input type="text" class="form-control fpn-method-detail-input" name="fpn_ev_method_detail[]" placeholder="รายละเอียด...">
                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="fpn_btn_add_method_global">
                            <i class="fas fa-plus me-1"></i> เพิ่มวิธีการ
                        </button>
                    </fieldset>

                    <!-- ==================== 8. การดำเนินการ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">8. การดำเนินการ</legend>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">วัตถุพยานแผ่นเก็บรอยลายนิ้วมือแฝง/ภาพถ่ายรอยลายนิ้วมือแฝง</label>
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_action_evidence_dest[]" value="กนฝ." style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1">กนฝ.</label>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input cursor-pointer fpn-action-other-chk" type="checkbox" name="fpn_action_evidence_dest[]" value="อื่นๆ" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1">อื่นๆ</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm fpn-action-evidence-other" name="fpn_action_evidence_other_text" placeholder="ระบุ..." style="width: 200px;">
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:1px 6px; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">ของกลาง</label>
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row g-3 align-items-center">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_action_exhibit" value="ส่งคืนพนักงานสอบสวน" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1">ส่งคืนพนักงานสอบสวน</label>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center gap-1">
                                        <span class="small text-muted">สภ.</span>
                                        <select class="form-select form-select-sm fpn-return-station" name="fpn_action_return_station" style="width: 200px;"><?= $policeStationOptionsFPN ?></select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">ส่งต่อกลุ่มงาน</label>
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row g-3">
                                    <div class="col-auto"><div class="form-check"><input class="form-check-input cursor-pointer fpn-forward-group" type="checkbox" name="fpn_action_forward" value="ส่งต่อกลุ่มงาน" style="transform: scale(1.1);"><label class="form-check-label cursor-pointer ms-1">ส่งต่อกลุ่มงาน</label></div></div>
                                    <div class="col-auto"><div class="form-check"><input class="form-check-input cursor-pointer" type="checkbox" name="fpn_action_forward_dept[]" value="กชว." style="transform: scale(1.1);"><label class="form-check-label cursor-pointer ms-1">กชว.</label></div></div>
                                    <div class="col-auto"><div class="form-check"><input class="form-check-input cursor-pointer" type="checkbox" name="fpn_action_forward_dept[]" value="กอป." style="transform: scale(1.1);"><label class="form-check-label cursor-pointer ms-1">กอป.</label></div></div>
                                    <div class="col-auto"><div class="form-check"><input class="form-check-input cursor-pointer" type="checkbox" name="fpn_action_forward_dept[]" value="กอส." style="transform: scale(1.1);"><label class="form-check-label cursor-pointer ms-1">กอส.</label></div></div>
                                    <div class="col-auto"><div class="form-check"><input class="form-check-input cursor-pointer" type="checkbox" name="fpn_action_forward_dept[]" value="กคม." style="transform: scale(1.1);"><label class="form-check-label cursor-pointer ms-1">กคม.</label></div></div>
                                    <div class="col-auto"><div class="form-check"><input class="form-check-input cursor-pointer" type="checkbox" name="fpn_action_forward_dept[]" value="กคพ." style="transform: scale(1.1);"><label class="form-check-label cursor-pointer ms-1">กคพ.</label></div></div>
                                    <div class="col-auto d-flex align-items-center gap-2"><div class="form-check mb-0"><input class="form-check-input cursor-pointer" type="checkbox" name="fpn_action_forward_dept[]" value="อื่นๆ" style="transform: scale(1.1);"><label class="form-check-label cursor-pointer ms-1">อื่นๆ</label></div><input type="text" class="form-control form-control-sm fpn-forward-other" name="fpn_action_forward_other_text" placeholder="ระบุ..." style="width: 150px;"><button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:1px 6px; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 9. การถ่ายภาพวัตถุพยาน / ผลการตรวจเก็บ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border" id="fpn_section9_wrapper">
                        <legend class="fieldset-header">9. การถ่ายภาพวัตถุพยาน / ผลการตรวจเก็บ</legend>

                        <!-- การถ่ายภาพวัตถุพยาน -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">การถ่ายภาพวัตถุพยาน</h6>
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_photo_type[]" value="ภาพการหีบห่อวัตถุพยาน" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1">ภาพการหีบห่อวัตถุพยาน</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_photo_type[]" value="ภาพวัตถุพยานพร้อมหนังสือนำส่ง ด้านหน้า (วางสเกล)" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1">ภาพวัตถุพยานพร้อมหนังสือนำส่ง ด้านหน้า (วางสเกล)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_photo_type[]" value="ภาพวัตถุพยานระยะใกล้ (วางสเกล)" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1">ภาพวัตถุพยานระยะใกล้ (วางสเกล)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_photo_type[]" value="ภาพคำนิยามเฉพาะบนวัตถุพยาน" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1">ภาพค่าพิเศษบนวัตถุพยาน</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_photo_type[]" value="ภาพแผ่นเก็บรอยลายนิ้วมือแฝง พร้อมหนังสือนำส่ง" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1">ภาพแผ่นเก็บรอยลายนิ้วมือแฝง พร้อมหนังสือนำส่ง</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- ด้าน -->
                                <div class="mt-3 ms-3">
                                    <label class="form-label small text-muted">ด้านที่ถ่ายภาพ</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_photo_side[]" value="ด้านหน้า" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1">ด้านหน้า</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_photo_side[]" value="ด้านซ้าย" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1">ด้านซ้าย</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_photo_side[]" value="ด้านขวา" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1">ด้านขวา</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input cursor-pointer" type="checkbox" name="fpn_photo_side[]" value="ด้านหลัง" style="transform: scale(1.1);">
                                            <label class="form-check-label cursor-pointer ms-1">ด้านหลัง</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- การปรับสี/แสง & ขนาดภาพ -->
                                <div class="row g-3 mt-3">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input fpn-toggle-adj cursor-pointer" type="checkbox" name="fpn_photo_type[]" value="การปรับสี/แสง" id="fpn_photo_color_adj_chk" data-target="fpn_color_adj_detail" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1" for="fpn_photo_color_adj_chk">การปรับสี/แสง</label>
                                            </div>
                                            <input type="text" class="form-control form-control-sm" name="fpn_color_adj_detail" id="fpn_color_adj_detail" placeholder="ระบุ..." style="display:none;" disabled>
                                            <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" data-hw-targets="fpn_color_adj_detail" title="เขียนด้วยลายมือ" style="padding:1px 6px; font-size:0.7rem; display:none;" id="fpn_color_adj_detail_hw"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input fpn-toggle-adj cursor-pointer" type="checkbox" name="fpn_photo_type[]" value="การปรับขนาดภาพ" id="fpn_photo_resize_chk" data-target="fpn_resize_detail" style="transform: scale(1.1);">
                                                <label class="form-check-label cursor-pointer ms-1" for="fpn_photo_resize_chk">การปรับขนาดภาพ</label>
                                            </div>
                                            <input type="text" class="form-control form-control-sm" name="fpn_resize_detail" id="fpn_resize_detail" placeholder="ระบุ..." style="display:none;" disabled>
                                            <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" data-hw-targets="fpn_resize_detail" title="เขียนด้วยลายมือ" style="padding:1px 6px; font-size:0.7rem; display:none;" id="fpn_resize_detail_hw"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ผลการตรวจเก็บ -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">ผลการตรวจเก็บ</h6>

                            <!-- ไม่พบ -->
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3">
                                <div class="form-check mb-2">
                                    <input class="form-check-input fpn-radio-result cursor-pointer" type="checkbox" name="fpn_result_type" value="ไม่พบรอยลายนิ้วมือแฝง" id="fpn_result_not_found" data-group="fpn_result_type" style="transform: scale(1.1);">
                                    <label class="form-check-label cursor-pointer ms-1 fw-bold text-dark" for="fpn_result_not_found">ไม่พบรอยลายนิ้วมือแฝง</label>
                                </div>
                                <div class="fpn-not-found-wrapper ms-4" style="display:none;">
                                    <label class="form-label small text-muted">เพราะ</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control fpn-not-found-reason" name="fpn_not_found_reason" disabled placeholder="ระบุเหตุผล...">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>

                            <!-- พบ -->
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3">
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input fpn-radio-result cursor-pointer" type="checkbox" name="fpn_result_type" value="พบรอยลายนิ้วมือแฝง" id="fpn_result_found" data-group="fpn_result_type" style="transform: scale(1.1);">
                                        <label class="form-check-label cursor-pointer ms-1 fw-bold text-dark" for="fpn_result_found">พบรอยลายนิ้วมือแฝง</label>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="small text-muted">จำนวน</span>
                                        <input type="number" class="form-control form-control-sm fpn-found-total text-center" name="fpn_found_total_sheets" min="0" disabled style="width: 80px;">
                                        <span class="small text-muted">แผ่น</span>
                                    </div>
                                </div>
                                <div class="fpn-found-wrapper ms-4" style="display:none;">
                                    <div class="fpn-found-items-container" id="fpn_found_items_container_s9">
                                        <div class="fpn-found-item-row d-flex align-items-center gap-2 mb-2">
                                            <span class="small text-muted text-nowrap">จากวัตถุพยานรายการที่</span>
                                            <input type="text" class="form-control form-control-sm" name="fpn_found_from_item[]" disabled style="width: 80px;">
                                            <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:1px 6px; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                                            <span class="small text-muted text-nowrap">จำนวน</span>
                                            <input type="number" class="form-control form-control-sm text-center" name="fpn_found_item_sheets[]" min="0" disabled style="width: 80px;">
                                            <span class="small text-muted text-nowrap">แผ่น</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary fpn-btn-add-found-item mt-1" disabled>
                                        <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                    </button>
                                </div>
                            </div>

                            <!-- Photograph -->
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="form-check mb-2">
                                    <input class="form-check-input fpn-has-photograph cursor-pointer" type="checkbox" name="fpn_has_photograph" value="1" style="transform: scale(1.1);">
                                    <label class="form-check-label cursor-pointer ms-1 fw-bold text-dark">Photograph</label>
                                </div>
                                <div class="fpn-photograph-wrapper ms-4" style="display:none;">
                                    <div class="fpn-photograph-container" id="fpn_photograph_container_s9">
                                        <div class="fpn-photograph-row d-flex align-items-center gap-2 mb-2">
                                            <span class="fpn-photo-index small text-muted" style="min-width: 25px;">1)</span>
                                            <input type="text" class="form-control form-control-sm" name="fpn_photograph_desc[]" disabled placeholder="วัน/เดือน/ปี / รายละเอียด">
                                            <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:1px 6px; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary fpn-btn-add-photograph mt-1" disabled>
                                        <i class="fas fa-plus me-1"></i> เพิ่ม
                                    </button>
                                </div>
                            </div>
                        </div>

                    </fieldset>

                    <!-- ==================== 10. บันทึกการถ่ายภาพ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">10. บันทึกการถ่ายภาพ</legend>

                        <div class="row mb-4">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">รหัสภาพถ่ายที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="fpn_photo_id_start" id="fpn_photo_id_start">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fpn_photo_id_start" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">ถึง</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="fpn_photo_id_end" id="fpn_photo_id_end">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="fpn_photo_id_end" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">จำนวน (ภาพ)</label>
                                <input type="text" class="form-control" name="fpn_photo_amount" id="fpn_photo_amount"
                                    inputmode="numeric" pattern="[0-9]*"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="button" class="btn btn-primary" id="btn_choose_file_fpn">
                                        <i class="fas fa-folder-open me-2"></i>เลือกไฟล์รูปภาพ
                                    </button>
                                    <button type="button" class="btn btn-success" id="btn_open_camera_fpn">
                                        <i class="fas fa-camera me-2"></i>เปิดกล้องถ่ายภาพ
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <div id="fpn_photo_dropzone" class="fpn-photo-dropzone d-flex flex-column align-items-center justify-content-center text-center"
                                    style="border:2px dashed #b0bec5; border-radius:12px; padding:24px; cursor:pointer; background:#f8f9fa; transition:all 0.2s;">
                                    <i class="fas fa-cloud-upload-alt mb-2" style="font-size:1.8rem; color:#90a4ae;"></i>
                                    <div class="text-muted">ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <input type="file" id="incident_photos_fpn" name="incident_photos_fpn[]" accept="image/*" style="display: none;" multiple>
                                <input type="file" id="camera_input_fpn" name="camera_photos_fpn[]" accept="image/*" capture="environment" style="display: none;" multiple>

                                <div id="uploading_container_fpn" class="mb-4"></div>

                                <div id="attachments_wrapper_fpn" class="d-none mt-4">
                                    <h6 class="fw-bold text-secondary mb-3">
                                        Attachments <span class="badge bg-secondary rounded-pill ms-1" id="file_count_badge_fpn">0</span>
                                    </h6>
                                    <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-3" id="attachments_grid_fpn"></div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                </form>
            </div>
            <div class="modal-footer justify-content-end gap-2 py-2 px-3 bg-white border-top">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_fpn" onclick="prepareDataForSubmissionFingerprintNew()">
                    <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger btn-sm js-close-modal" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> ยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // =================================================================
    // Method dropdown options HTML (shared)
    // =================================================================
    const fpnMethodOptionsHTML = `
        <option value="" selected disabled>-- เลือกวิธีการ --</option>
        <option value="Forensic Light Sources">Forensic Light Sources</option>
        <option value="Powder">Powder</option>
        <option value="Super Glue">Super Glue</option>
        <option value="Indanedione-Zine">Indanedione-Zine</option>
        <option value="Amido Black">Amido Black</option>
        <option value="Sticky Side">Sticky Side</option>
        <option value="Rhodamine 6G">Rhodamine 6G</option>
        <option value="Ninhydrin">Ninhydrin</option>
        <option value="Acid Yellow 7">Acid Yellow 7</option>
        <option value="SPR">SPR</option>
        <option value="Basic Yellow 40">Basic Yellow 40</option>
        <option value="อื่นๆ">อื่นๆ</option>`;

    const fpnInspectorOptionsHTML = `<?= $inspectorOptionsFPN ?>`;

    // =================================================================
    // 4. ผู้ตรวจพิสูจน์ - Dynamic Rows
    // =================================================================
    let fpnInspectorIndex = 1;

    document.getElementById('btn_add_inspector_fpn').addEventListener('click', function() {
        fpnInspectorIndex++;
        const row = document.createElement('div');
        row.className = 'd-flex align-items-center mb-3 fpn-inspector-row';
        row.innerHTML = `
            <div class="text-end pe-3" style="width: 50px;">
                <span class="fw-bold text-secondary fpn-index-label">4.${fpnInspectorIndex}</span>
            </div>
            <div class="flex-grow-1">
                <select class="form-select fpn-inspector-select" name="fpn_inspector_id[]">${fpnInspectorOptionsHTML}</select>
            </div>
            <div class="ms-2" style="width: 32px;">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFPNInspectorRow(this)" title="ลบ">
                    <i class="fas fa-times"></i>
                </button>
            </div>`;
        document.getElementById('fpn_inspector_container').appendChild(row);
    });

    window.removeFPNInspectorRow = function(btn) {
        btn.closest('.fpn-inspector-row').remove();
        const rows = document.querySelectorAll('#fpn_inspector_container .fpn-inspector-row');
        rows.forEach((row, i) => {
            row.querySelector('.fpn-index-label').textContent = '4.' + (i + 1);
        });
        fpnInspectorIndex = rows.length;
    };

    // =================================================================
    // 5. Collector radio-like toggle
    // =================================================================
    document.querySelectorAll('.fpn-radio-collector').forEach(function(cb) {
        cb.addEventListener('change', function() {
            if (this.checked) {
                document.querySelectorAll('.fpn-radio-collector').forEach(function(other) {
                    if (other !== cb) other.checked = false;
                });
            }
            const otherTxt = document.getElementById('fpn_collector_other_text');
            const otherHw = document.getElementById('fpn_collector_other_text_hw');
            if (document.getElementById('fpn_collector_other').checked) {
                otherTxt.style.display = '';
                otherTxt.disabled = false;
                if (otherHw) otherHw.style.display = '';
            } else {
                otherTxt.style.display = 'none';
                otherTxt.disabled = true;
                otherTxt.value = '';
                if (otherHw) otherHw.style.display = 'none';
            }
        });
    });

    // =================================================================
    // Toggle adj inputs (color adj / resize)
    // =================================================================
    document.querySelectorAll('.fpn-toggle-adj').forEach(function(cb) {
        cb.addEventListener('change', function() {
            const targetId = this.dataset.target;
            const el = document.getElementById(targetId);
            const hwEl = document.getElementById(targetId + '_hw');
            if (el) {
                if (this.checked) { el.style.display = ''; el.disabled = false; if (hwEl) hwEl.style.display = ''; }
                else { el.style.display = 'none'; el.disabled = true; el.value = ''; if (hwEl) hwEl.style.display = 'none'; }
            }
        });
    });

    // =================================================================
    // Section 6 - Event Delegation for all sets
    // =================================================================
    let fpnSection6SetCount = 1;

    // Method row builder
    function buildMethodRowHTML(showDel) {
        let h = '<div class="fpn-method-row d-flex align-items-center gap-2 mb-2">';
        h += '<select class="form-select fpn-method-select" name="fpn_ev_method_name[]" style="max-width:250px;">' + fpnMethodOptionsHTML + '</select>';
        h += '<input type="text" class="form-control fpn-method-detail-input" name="fpn_ev_method_detail[]" placeholder="รายละเอียด...">';
        h += '<button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>';
        if (showDel) h += '<button type="button" class="btn btn-sm btn-outline-danger fpn-btn-del-method" title="ลบ"><i class="fas fa-times"></i></button>';
        h += '</div>';
        return h;
    }

    // Evidence card builder
    function buildEvidenceCardHTML(index) {
        return `
            <div class="fpn-evidence-card card mb-3 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-primary fpn-evidence-badge">รายการที่ ${index}</span>
                        <button type="button" class="btn btn-sm btn-outline-danger fpn-btn-del-evidence">
                            <i class="fas fa-trash me-1"></i> ลบรายการนี้
                        </button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">เป็น</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="fpn_ev_description[]" placeholder="รายละเอียดวัตถุพยาน...">
                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">กว้าง/เส้นผ่าศูนย์กลาง (cm)</label>
                            <input type="text" class="form-control text-center" name="fpn_ev_width[]">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">ยาว (cm)</label>
                            <input type="text" class="form-control text-center" name="fpn_ev_length[]">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">สูง (cm)</label>
                            <input type="text" class="form-control text-center" name="fpn_ev_height[]">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">จำนวน</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="fpn_ev_quantity[]">
                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ป้ายหมายเลข</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="fpn_ev_label_no[]">
                                <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">การตรวจพิสูจน์</label>
                            <select class="form-select" name="fpn_ev_lab_unit[]">
                                <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                                <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                <option value="fingerprint" selected>กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                <option value="document">กลุ่มงานตรวจเอกสาร</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>`;
    }

    // Full section 6 set builder
    function buildSection6SetHTML(setIndex) {
        return `
        <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border fpn-section6-set" data-set-index="${setIndex}">
            <legend class="fieldset-header">
                6. ลักษณะวัตถุพยาน
                <span class="badge bg-primary ms-2 fpn-set-label">ชุดที่ ${setIndex}</span>
                <button type="button" class="btn btn-sm btn-outline-danger ms-2 fpn-btn-remove-set" title="ลบชุดนี้">
                    <i class="fas fa-trash me-1"></i> ลบชุดนี้
                </button>
            </legend>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">จำนวนวัตถุพยานทั้งสิ้น</label>
                    <div class="input-group">
                        <input type="number" class="form-control text-center fpn-evidence-total" name="fpn_evidence_total[]" min="0" placeholder="0">
                        <span class="input-group-text">รายการ</span>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 text-secondary"><i class="fas fa-box me-2"></i>รายการวัตถุพยาน</h6>
                    <button type="button" class="btn btn-sm btn-primary fpn-btn-add-evidence">
                        <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                    </button>
                </div>
                <div class="fpn-evidence-container">
                    ${buildEvidenceCardHTML(1)}
                </div>
            </div>

        </fieldset>`;
    }

    // =================================================================
    // Add Section 6 Set
    // =================================================================
    document.getElementById('btn_add_section6_fpn').addEventListener('click', function() {
        fpnSection6SetCount++;
        const wrapper = document.getElementById('fpn_section6_wrapper');
        const tmp = document.createElement('div');
        tmp.innerHTML = buildSection6SetHTML(fpnSection6SetCount);
        const newSet = tmp.firstElementChild;
        wrapper.appendChild(newSet);
        // Skip scroll when loading data programmatically
        if (!window.fpnSkipScroll) {
            newSet.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    // =================================================================
    // Section 7 - Global method rows
    // =================================================================
    document.getElementById('fpn_btn_add_method_global').addEventListener('click', function() {
        const container = document.getElementById('fpn_method_global_container');
        const tmp = document.createElement('div');
        tmp.innerHTML = buildMethodRowHTML(true);
        container.appendChild(tmp.firstElementChild);
    });

    document.getElementById('fpn_method_global_container').addEventListener('click', function(e) {
        if (!e.target.closest('.fpn-btn-del-method')) return;
        const container = document.getElementById('fpn_method_global_container');
        if (container.querySelectorAll('.fpn-method-row').length <= 1) {
            Swal.fire({ icon: 'warning', title: 'ไม่สามารถลบได้', text: 'ต้องมีอย่างน้อย 1 วิธีการ', confirmButtonText: 'ตกลง' });
            return;
        }
        e.target.closest('.fpn-method-row').remove();
    });

    // =================================================================
    // Event Delegation for all Section 6 interactions
    // =================================================================
    document.addEventListener('click', function(e) {
        const set = e.target.closest('.fpn-section6-set');
        if (!set) return;

        // Remove set
        if (e.target.closest('.fpn-btn-remove-set')) {
            Swal.fire({
                icon: 'warning', title: 'ยืนยันการลบ',
                text: 'ต้องการลบชุดข้อมูลนี้ใช่หรือไม่?',
                showCancelButton: true, confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#dc3545'
            }).then(function(result) {
                if (result.isConfirmed) {
                    set.remove();
                    reindexFPNSets();
                }
            });
            return;
        }

        // Add evidence card
        if (e.target.closest('.fpn-btn-add-evidence')) {
            const container = set.querySelector('.fpn-evidence-container');
            const count = container.querySelectorAll('.fpn-evidence-card').length + 1;
            const tmp = document.createElement('div');
            tmp.innerHTML = buildEvidenceCardHTML(count);
            container.appendChild(tmp.firstElementChild);
            return;
        }

        // Delete evidence card
        if (e.target.closest('.fpn-btn-del-evidence')) {
            const container = set.querySelector('.fpn-evidence-container');
            if (container.querySelectorAll('.fpn-evidence-card').length <= 1) {
                Swal.fire({ icon: 'warning', title: 'ไม่สามารถลบได้', text: 'ต้องมีอย่างน้อย 1 รายการ', confirmButtonText: 'ตกลง' });
                return;
            }
            e.target.closest('.fpn-evidence-card').remove();
            container.querySelectorAll('.fpn-evidence-badge').forEach((b, i) => b.textContent = 'รายการที่ ' + (i + 1));
            return;
        }

        // Add method row
        if (e.target.closest('.fpn-btn-add-method')) {
            const card = e.target.closest('.fpn-evidence-card');
            if (!card) return;
            const container = card.querySelector('.fpn-method-container');
            const tmp = document.createElement('div');
            tmp.innerHTML = buildMethodRowHTML(true);
            container.appendChild(tmp.firstElementChild);
            return;
        }

        // Delete method row
        if (e.target.closest('.fpn-btn-del-method')) {
            const card = e.target.closest('.fpn-evidence-card');
            const container = card.querySelector('.fpn-method-container');
            if (container.querySelectorAll('.fpn-method-row').length <= 1) {
                Swal.fire({ icon: 'warning', title: 'ไม่สามารถลบได้', text: 'ต้องมีอย่างน้อย 1 วิธีการ', confirmButtonText: 'ตกลง' });
                return;
            }
            e.target.closest('.fpn-method-row').remove();
            return;
        }

        // Add found item
        if (e.target.closest('.fpn-btn-add-found-item')) {
            const si = set.dataset.setIndex;
            const container = set.querySelector('.fpn-found-items-container');
            const row = document.createElement('div');
            row.className = 'fpn-found-item-row d-flex align-items-center gap-2 mb-2';
            row.innerHTML = `
                <span class="small text-muted text-nowrap">จากวัตถุพยานรายการที่</span>
                <input type="text" class="form-control form-control-sm" name="fpn_found_from_item_s${si}[]" style="width:80px;">
                <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:1px 6px; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                <span class="small text-muted text-nowrap">จำนวน</span>
                <input type="number" class="form-control form-control-sm text-center" name="fpn_found_item_sheets_s${si}[]" min="0" style="width:80px;">
                <span class="small text-muted text-nowrap">แผ่น</span>
                <button type="button" class="btn btn-sm btn-outline-danger fpn-btn-del-found-item" title="ลบ"><i class="fas fa-times"></i></button>`;
            container.appendChild(row);
            return;
        }

        // Delete found item
        if (e.target.closest('.fpn-btn-del-found-item')) {
            const container = set.querySelector('.fpn-found-items-container');
            if (container.querySelectorAll('.fpn-found-item-row').length <= 1) {
                Swal.fire({ icon: 'warning', title: 'ไม่สามารถลบได้', text: 'ต้องมีอย่างน้อย 1 รายการ', confirmButtonText: 'ตกลง' });
                return;
            }
            e.target.closest('.fpn-found-item-row').remove();
            return;
        }

        // Add photograph
        if (e.target.closest('.fpn-btn-add-photograph')) {
            const si = set.dataset.setIndex;
            const container = set.querySelector('.fpn-photograph-container');
            const count = container.querySelectorAll('.fpn-photograph-row').length + 1;
            const row = document.createElement('div');
            row.className = 'fpn-photograph-row d-flex align-items-center gap-2 mb-2';
            row.innerHTML = `
                <span class="fpn-photo-index small text-muted" style="min-width:25px;">${count})</span>
                <input type="text" class="form-control form-control-sm" name="fpn_photograph_desc_s${si}[]" placeholder="วัน/เดือน/ปี / รายละเอียด">
                <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:1px 6px; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                <button type="button" class="btn btn-sm btn-outline-danger fpn-btn-del-photograph" title="ลบ"><i class="fas fa-times"></i></button>`;
            container.appendChild(row);
            return;
        }

        // Delete photograph
        if (e.target.closest('.fpn-btn-del-photograph')) {
            const container = set.querySelector('.fpn-photograph-container');
            if (container.querySelectorAll('.fpn-photograph-row').length <= 1) {
                Swal.fire({ icon: 'warning', title: 'ไม่สามารถลบได้', text: 'ต้องมีอย่างน้อย 1 รายการ', confirmButtonText: 'ตกลง' });
                return;
            }
            e.target.closest('.fpn-photograph-row').remove();
            container.querySelectorAll('.fpn-photo-index').forEach((el, i) => el.textContent = (i + 1) + ')');
            return;
        }
    });

    // =================================================================
    // Change events delegation
    // =================================================================
    document.addEventListener('change', function(e) {
        const set = e.target.closest('.fpn-section6-set');
        if (!set) return;

        // Toggle adj inputs (dynamic sets)
        if (e.target.classList.contains('fpn-toggle-adj-dynamic')) {
            const targetClass = e.target.dataset.targetClass;
            const inp = e.target.closest('.d-flex').querySelector('.' + targetClass);
            if (inp) {
                inp.style.display = e.target.checked ? '' : 'none';
                inp.disabled = !e.target.checked;
                if (!e.target.checked) inp.value = '';
            }
            return;
        }

        // Radio-like result
        if (e.target.classList.contains('fpn-radio-result')) {
            if (e.target.checked) {
                set.querySelectorAll('.fpn-radio-result').forEach(function(other) {
                    if (other !== e.target) other.checked = false;
                });
            }
            const notFoundCb = set.querySelector('.fpn-radio-result[value="ไม่พบรอยลายนิ้วมือแฝง"]');
            const foundCb = set.querySelector('.fpn-radio-result[value="พบรอยลายนิ้วมือแฝง"]');

            const nfWrapper = set.querySelector('.fpn-not-found-wrapper');
            const nfReason = set.querySelector('.fpn-not-found-reason');
            if (notFoundCb && notFoundCb.checked) {
                nfWrapper.style.display = ''; nfReason.disabled = false;
            } else {
                nfWrapper.style.display = 'none'; nfReason.disabled = true;
            }

            const fWrapper = set.querySelector('.fpn-found-wrapper');
            const fTotal = set.querySelector('.fpn-found-total');
            const fInputs = fWrapper.querySelectorAll('input, button');
            if (foundCb && foundCb.checked) {
                fWrapper.style.display = ''; fTotal.disabled = false;
                fInputs.forEach(el => el.disabled = false);
            } else {
                fWrapper.style.display = 'none'; fTotal.disabled = true;
                fInputs.forEach(el => el.disabled = true);
            }
            return;
        }

        // Photograph toggle
        if (e.target.classList.contains('fpn-has-photograph')) {
            const wrapper = set.querySelector('.fpn-photograph-wrapper');
            const inputs = wrapper.querySelectorAll('input, button');
            if (e.target.checked) {
                wrapper.style.display = '';
                inputs.forEach(el => el.disabled = false);
            } else {
                wrapper.style.display = 'none';
                inputs.forEach(el => el.disabled = true);
            }
            return;
        }
    });

    function reindexFPNSets() {
        const sets = document.querySelectorAll('#fpn_section6_wrapper .fpn-section6-set');
        sets.forEach((set, i) => {
            const idx = i + 1;
            set.dataset.setIndex = idx;
            const label = set.querySelector('.fpn-set-label');
            if (label) label.textContent = 'ชุดที่ ' + idx;
        });
        fpnSection6SetCount = sets.length;
    }

    // =================================================================
    // Collect data from a section 6 set
    // =================================================================
    function collectSection6SetDataFPN(set) {
        const setData = {
            set_index: set.dataset.setIndex,
            evidence_total: '',
            evidence_items: [],
            global_methods: [],
            action_evidence_dest: [],
            action_evidence_other_text: '',
            action_exhibit: '',
            action_return_station: '',
            action_forward: false,
            action_forward_depts: [],
            action_forward_other_text: ''
        };

        const totalEl = set.querySelector('.fpn-evidence-total');
        if (totalEl) setData.evidence_total = totalEl.value;

        set.querySelectorAll('.fpn-evidence-card').forEach(card => {
            const item = {
                description: (card.querySelector('[name="fpn_ev_description[]"]') || {}).value || '',
                width: (card.querySelector('[name="fpn_ev_width[]"]') || {}).value || '',
                length: (card.querySelector('[name="fpn_ev_length[]"]') || {}).value || '',
                height: (card.querySelector('[name="fpn_ev_height[]"]') || {}).value || '',
                quantity: (card.querySelector('[name="fpn_ev_quantity[]"]') || {}).value || '',
                label_no: (card.querySelector('[name="fpn_ev_label_no[]"]') || {}).value || '',
                lab_unit: (card.querySelector('[name="fpn_ev_lab_unit[]"]') || {}).value || 'fingerprint',
                methods: []
            };
            card.querySelectorAll('.fpn-method-row').forEach(row => {
                const sel = row.querySelector('.fpn-method-select');
                const det = row.querySelector('.fpn-method-detail-input');
                if (sel && sel.value) {
                    item.methods.push({ name: sel.value, detail: det ? det.value : '' });
                }
            });
            setData.evidence_items.push(item);
        });

        set.querySelectorAll('input[name*="fpn_action_evidence_dest"]:checked').forEach(cb => setData.action_evidence_dest.push(cb.value));
        const aeOther = set.querySelector('.fpn-action-evidence-other');
        if (aeOther) setData.action_evidence_other_text = (aeOther.value || '').trim();
        const exhibitCb = set.querySelector('input[name*="fpn_action_exhibit"]:checked');
        if (exhibitCb) setData.action_exhibit = exhibitCb.value;
        const returnStation = set.querySelector('.fpn-return-station');
        if (returnStation) setData.action_return_station = (returnStation.value || '').trim();
        const forwardCb = set.querySelector('.fpn-forward-group');
        setData.action_forward = forwardCb ? forwardCb.checked : false;
        set.querySelectorAll('input[name*="fpn_action_forward_dept"]:checked').forEach(cb => setData.action_forward_depts.push(cb.value));
        const fwdOther = set.querySelector('.fpn-forward-other');
        if (fwdOther) setData.action_forward_other_text = (fwdOther.value || '').trim();

        // Section 7 (global methods) + Section 8 (global actions) fallback for first set
        if (String(setData.set_index) === '1') {
            const globalMethodContainer = document.getElementById('fpn_method_global_container');
            if (globalMethodContainer) {
                globalMethodContainer.querySelectorAll('.fpn-method-row').forEach(row => {
                    const sel = row.querySelector('.fpn-method-select');
                    const det = row.querySelector('.fpn-method-detail-input');
                    if (sel && sel.value) setData.global_methods.push({ name: sel.value, detail: det ? det.value : '' });
                });
            }

            if (setData.action_evidence_dest.length === 0) {
                document.querySelectorAll('input[name="fpn_action_evidence_dest[]"]:checked').forEach(cb => setData.action_evidence_dest.push(cb.value));
            }
            if (!setData.action_exhibit) {
                const exhibitMain = document.querySelector('input[name="fpn_action_exhibit"]:checked');
                if (exhibitMain) setData.action_exhibit = exhibitMain.value;
            }
            if (!setData.action_return_station) {
                const returnMain = document.querySelector('select[name="fpn_action_return_station"]');
                if (returnMain) setData.action_return_station = (returnMain.value || '').trim();
            }
            if (setData.action_forward_depts.length === 0) {
                document.querySelectorAll('input[name="fpn_action_forward_dept[]"]:checked').forEach(cb => setData.action_forward_depts.push(cb.value));
            }
            if (!setData.action_forward) {
                const forwardMain = document.querySelector('input[name="fpn_action_forward"]');
                if (forwardMain) setData.action_forward = forwardMain.checked;
            }
            if (!setData.action_evidence_other_text) {
                const aeMain = document.querySelector('input[name="fpn_action_evidence_other_text"]');
                if (aeMain) setData.action_evidence_other_text = (aeMain.value || '').trim();
            }
            if (!setData.action_forward_other_text) {
                const fwMain = document.querySelector('input[name="fpn_action_forward_other_text"]');
                if (fwMain) setData.action_forward_other_text = (fwMain.value || '').trim();
            }
        }

        return setData;
    }

    // =================================================================
    // Collect data from section 9 (การถ่ายภาพวัตถุพยาน / ผลการตรวจเก็บ)
    // =================================================================
    function collectSection9DataFPN() {
        const wrapper = document.getElementById('fpn_section9_wrapper');
        if (!wrapper) return {};

        const s9 = {
            photo_types: [],
            photo_sides: [],
            color_adj_detail: '',
            resize_detail: '',
            result_type: '',
            not_found_reason: '',
            found_total_sheets: '',
            found_items: [],
            has_photograph: false,
            photographs: []
        };

        wrapper.querySelectorAll('input[type="checkbox"][name*="fpn_photo_type"]:checked').forEach(cb => s9.photo_types.push(cb.value));
        wrapper.querySelectorAll('input[type="checkbox"][name*="fpn_photo_side"]:checked').forEach(cb => s9.photo_sides.push(cb.value));

        const colorAdj = wrapper.querySelector('#fpn_color_adj_detail');
        if (colorAdj) s9.color_adj_detail = (colorAdj.value || '').trim();
        const resize = wrapper.querySelector('#fpn_resize_detail');
        if (resize) s9.resize_detail = (resize.value || '').trim();

        const checkedResult = wrapper.querySelector('.fpn-radio-result:checked');
        if (checkedResult) s9.result_type = checkedResult.value;
        if (s9.result_type === 'ไม่พบรอยลายนิ้วมือแฝง') {
            const reason = wrapper.querySelector('.fpn-not-found-reason');
            if (reason) s9.not_found_reason = reason.value;
        }
        if (s9.result_type === 'พบรอยลายนิ้วมือแฝง') {
            const totalSheets = wrapper.querySelector('.fpn-found-total');
            if (totalSheets) s9.found_total_sheets = totalSheets.value;
            wrapper.querySelectorAll('.fpn-found-item-row').forEach(row => {
                const fromItem = row.querySelector('input[name*="fpn_found_from_item"]');
                const sheets = row.querySelector('input[name*="fpn_found_item_sheets"]');
                s9.found_items.push({ from_item: fromItem ? fromItem.value : '', sheets: sheets ? sheets.value : '' });
            });
        }

        const photoChk = wrapper.querySelector('.fpn-has-photograph');
        s9.has_photograph = photoChk ? photoChk.checked : false;
        if (s9.has_photograph) {
            wrapper.querySelectorAll('input[name*="fpn_photograph_desc"]').forEach(input => {
                s9.photographs.push(input.value);
            });
        }

        return s9;
    }

    // =================================================================
    // Section 9 - Event Delegation (click)
    // =================================================================
    document.addEventListener('click', function(e) {
        const s9 = e.target.closest('#fpn_section9_wrapper');
        if (!s9) return;

        if (e.target.closest('.fpn-btn-add-found-item')) {
            const container = s9.querySelector('.fpn-found-items-container');
            const row = document.createElement('div');
            row.className = 'fpn-found-item-row d-flex align-items-center gap-2 mb-2';
            row.innerHTML = '<span class="small text-muted text-nowrap">จากวัตถุพยานรายการที่</span>' +
                '<input type="text" class="form-control form-control-sm" name="fpn_found_from_item[]" style="width:80px;">' +
                '<button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:1px 6px; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
                '<span class="small text-muted text-nowrap">จำนวน</span>' +
                '<input type="number" class="form-control form-control-sm text-center" name="fpn_found_item_sheets[]" min="0" style="width:80px;">' +
                '<span class="small text-muted text-nowrap">แผ่น</span>' +
                '<button type="button" class="btn btn-sm btn-outline-danger fpn-btn-del-found-item" title="ลบ"><i class="fas fa-times"></i></button>';
            container.appendChild(row);
            return;
        }

        if (e.target.closest('.fpn-btn-del-found-item')) {
            const container = s9.querySelector('.fpn-found-items-container');
            if (container.querySelectorAll('.fpn-found-item-row').length <= 1) {
                Swal.fire({ icon: 'warning', title: 'ไม่สามารถลบได้', text: 'ต้องมีอย่างน้อย 1 รายการ', confirmButtonText: 'ตกลง' });
                return;
            }
            e.target.closest('.fpn-found-item-row').remove();
            return;
        }

        if (e.target.closest('.fpn-btn-add-photograph')) {
            const container = s9.querySelector('.fpn-photograph-container');
            const count = container.querySelectorAll('.fpn-photograph-row').length + 1;
            const row = document.createElement('div');
            row.className = 'fpn-photograph-row d-flex align-items-center gap-2 mb-2';
            row.innerHTML = '<span class="fpn-photo-index small text-muted" style="min-width:25px;">' + count + ')</span>' +
                '<input type="text" class="form-control form-control-sm" name="fpn_photograph_desc[]" placeholder="วัน/เดือน/ปี / รายละเอียด">' +
                '<button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:1px 6px; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger fpn-btn-del-photograph" title="ลบ"><i class="fas fa-times"></i></button>';
            container.appendChild(row);
            return;
        }

        if (e.target.closest('.fpn-btn-del-photograph')) {
            const container = s9.querySelector('.fpn-photograph-container');
            if (container.querySelectorAll('.fpn-photograph-row').length <= 1) {
                Swal.fire({ icon: 'warning', title: 'ไม่สามารถลบได้', text: 'ต้องมีอย่างน้อย 1 รายการ', confirmButtonText: 'ตกลง' });
                return;
            }
            e.target.closest('.fpn-photograph-row').remove();
            container.querySelectorAll('.fpn-photo-index').forEach((el, i) => el.textContent = (i + 1) + ')');
            return;
        }
    });

    // =================================================================
    // Section 9 - Event Delegation (change)
    // =================================================================
    document.addEventListener('change', function(e) {
        const s9 = e.target.closest('#fpn_section9_wrapper');
        if (!s9) return;

        if (e.target.classList.contains('fpn-radio-result')) {
            if (e.target.checked) {
                s9.querySelectorAll('.fpn-radio-result').forEach(function(other) {
                    if (other !== e.target) other.checked = false;
                });
            }
            const notFoundCb = s9.querySelector('.fpn-radio-result[value="ไม่พบรอยลายนิ้วมือแฝง"]');
            const foundCb = s9.querySelector('.fpn-radio-result[value="พบรอยลายนิ้วมือแฝง"]');

            const nfWrapper = s9.querySelector('.fpn-not-found-wrapper');
            const nfReason = s9.querySelector('.fpn-not-found-reason');
            if (notFoundCb && notFoundCb.checked) {
                nfWrapper.style.display = ''; nfReason.disabled = false;
            } else {
                nfWrapper.style.display = 'none'; nfReason.disabled = true;
            }

            const fWrapper = s9.querySelector('.fpn-found-wrapper');
            const fTotal = s9.querySelector('.fpn-found-total');
            const fInputs = fWrapper.querySelectorAll('input, button');
            if (foundCb && foundCb.checked) {
                fWrapper.style.display = ''; fTotal.disabled = false;
                fInputs.forEach(el => el.disabled = false);
            } else {
                fWrapper.style.display = 'none'; fTotal.disabled = true;
                fInputs.forEach(el => el.disabled = true);
            }
            return;
        }

        if (e.target.classList.contains('fpn-has-photograph')) {
            const wrapper = s9.querySelector('.fpn-photograph-wrapper');
            const inputs = wrapper.querySelectorAll('input, button');
            if (e.target.checked) {
                wrapper.style.display = '';
                inputs.forEach(el => el.disabled = false);
            } else {
                wrapper.style.display = 'none';
                inputs.forEach(el => el.disabled = true);
            }
            return;
        }
    });

    // =================================================================
    // Submit / Collect Data
    // =================================================================
    window.prepareDataForSubmissionFingerprintNew = function() {
        const form = document.getElementById('incidentCheckListFormFingerprintNew');
        if (!form) return;

        const incidentIdEl = document.getElementById('receiveNoti_id_fpn');
        if (!incidentIdEl || !(incidentIdEl.value || '').trim()) {
            Swal.fire({ icon: 'warning', title: 'ไม่พบรหัสคดี', text: 'กรุณาเปิดฟอร์มจากรายการคดีก่อนบันทึก', confirmButtonText: 'ตกลง' });
            return;
        }

        const data = {
            receiveNoti_id: document.getElementById('receiveNoti_id_fpn').value,
            doc_no: document.getElementById('doc_no_fpn').value,
            report_no: document.getElementById('report_no_fpn').value,
            receive_date: document.getElementById('fpn_receive_date').value,
            receive_time: document.getElementById('fpn_receive_time').value,
            case_no: document.getElementById('fpn_case_no').value,
            police_station: document.getElementById('fpn_police_station').value,
            letter_no: document.getElementById('fpn_letter_no').value,
            letter_date: document.getElementById('fpn_letter_date').value,
            evidence_sender: (document.getElementById('fpn_evidence_sender') || {}).value || '',
            evidence_sender_position: (document.getElementById('fpn_evidence_sender_position') || {}).value || '',
            evidence_sender_phone: (document.getElementById('fpn_evidence_sender_phone') || {}).value || '',
            evidence_letter_no: (document.getElementById('fpn_evidence_letter_no') || {}).value || '',
            evidence_doc_no: (document.getElementById('fpn_evidence_doc_no') || {}).value || '',
            evidence_doc_date: (document.getElementById('fpn_evidence_doc_date') || {}).value || '',
            purposes: [],
            purpose_other_text: (document.getElementById('fpn_purpose_other_text') || {}).value || '',
            incident_date: document.getElementById('fpn_incident_date').value,
            incident_time: document.getElementById('fpn_incident_time').value,
            known_date: document.getElementById('fpn_known_date').value,
            known_time: document.getElementById('fpn_known_time').value,
            collect_date: document.getElementById('fpn_collect_date').value,
            collect_time: document.getElementById('fpn_collect_time').value,
            inspectors: [],
            package_types: [],
            seal_conditions: [],
            collector_type: '',
            collector_other_text: ((document.getElementById('fpn_collector_other_text') || {}).value || '').trim(),
            storage_date: document.getElementById('fpn_storage_date').value,
            storage_time: document.getElementById('fpn_storage_time').value,
            duration_year: document.getElementById('fpn_duration_year').value,
            duration_month: document.getElementById('fpn_duration_month').value,
            duration_day: document.getElementById('fpn_duration_day').value,
            section6_sets: [],
            section9: {},
            // Photo page fields
            photo_id_start: (document.getElementById('fpn_photo_id_start') || {}).value || '',
            photo_id_end: (document.getElementById('fpn_photo_id_end') || {}).value || '',
            photo_amount: (document.getElementById('fpn_photo_amount') || {}).value || ''
        };

        // Inspectors
        document.querySelectorAll('#fpn_inspector_container .fpn-inspector-select').forEach(sel => {
            if (sel.value) data.inspectors.push(sel.value);
        });

        // Package types
        document.querySelectorAll('input[name="fpn_package_type[]"]:checked').forEach(cb => {
            data.package_types.push(cb.value);
        });

        // Seal conditions
        document.querySelectorAll('input[name="fpn_seal_condition[]"]:checked').forEach(cb => {
            data.seal_conditions.push(cb.value);
        });

        // Purposes
        document.querySelectorAll('input[name="fpn_purpose[]"]:checked').forEach(cb => {
            data.purposes.push(cb.value);
        });

        // Collector type
        const checkedCollector = document.querySelector('.fpn-radio-collector:checked');
        if (checkedCollector) data.collector_type = checkedCollector.value;

        // Collect data from all section 6 sets
        document.querySelectorAll('#fpn_section6_wrapper .fpn-section6-set').forEach(set => {
            data.section6_sets.push(collectSection6SetDataFPN(set));
        });

        // Collect section 9 data
        data.section9 = collectSection9DataFPN();

        Swal.fire({
            title: 'ยืนยันการบันทึกข้อมูล',
            text: 'กรุณาตรวจสอบความถูกต้องก่อนบันทึก',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#d33',
            confirmButtonText: 'ยืนยัน, บันทึกเลย!',
            cancelButtonText: 'ยกเลิก',
        }).then(async function(result) {
            if (result.isConfirmed) {
                var submitData = new FormData();
                submitData.append('payload', JSON.stringify(data));

                // Append new photo files from shared store
                if (typeof attachmentStoreFP !== 'undefined') {
                    attachmentStoreFP.forEach(function(item) {
                        if (item.file && !item.existing) {
                            submitData.append('incident_photos_fpn[]', item.file);
                        }
                    });
                }

                // Append deleted photo file_ids
                if (typeof deletedExistingPhotosFP !== 'undefined' && deletedExistingPhotosFP.length > 0) {
                    submitData.append('deleted_photo_file_ids', JSON.stringify(deletedExistingPhotosFP));
                }

                var fpnUrl = './api/incidentCheckList/saveFingerprint.php';
                if (!navigator.onLine) {
                    await saveChecklistOffline(submitData, fpnUrl, '#addCheckListModalFingerprintNew');
                    return;
                }
                var backendOkFPN = await checkBackendHealth();
                if (!backendOkFPN) {
                    await saveChecklistOffline(submitData, fpnUrl, '#addCheckListModalFingerprintNew');
                    return;
                }

                $.ajax({
                    url: fpnUrl,
                    method: 'POST',
                    data: submitData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'สำเร็จ', text: response.message || 'บันทึกข้อมูลเรียบร้อย', confirmButtonText: 'ตกลง' }).then(function() {
                                const modal = bootstrap.Modal.getInstance(document.getElementById('addCheckListModalFingerprintNew'));
                                if (modal) modal.hide();
                                if (typeof resetFingerprintNewForm === 'function') resetFingerprintNewForm();
                                if (typeof loadData === 'function') loadData();
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message || 'ไม่สามารถบันทึกได้', confirmButtonText: 'ตกลง' });
                        }
                    },
                    error: async function(xhr) {
                        console.error('Save fingerprint error:', xhr);
                        await saveChecklistOffline(submitData, fpnUrl, '#addCheckListModalFingerprintNew');
                    }
                });
            }
        });
    };

    // =================================================================
    // Reset Form
    // =================================================================
    window.resetFingerprintNewForm = function() {
        const form = document.getElementById('incidentCheckListFormFingerprintNew');
        if (!form) return;
        form.reset();

        // Reset inspectors to 1
        const inspectorContainer = document.getElementById('fpn_inspector_container');
        const firstInspector = inspectorContainer.querySelector('.fpn-inspector-row');
        inspectorContainer.innerHTML = '';
        if (firstInspector) {
            firstInspector.querySelector('.fpn-index-label').textContent = '4.1';
            const delBtn = firstInspector.querySelector('.btn-outline-danger');
            if (delBtn) delBtn.closest('div').innerHTML = '';
            inspectorContainer.appendChild(firstInspector);
        }
        fpnInspectorIndex = 1;

        // Remove extra section 6 sets (keep only the first)
        const wrapper = document.getElementById('fpn_section6_wrapper');
        const allSets = wrapper.querySelectorAll('.fpn-section6-set');
        allSets.forEach((set, i) => {
            if (i > 0) set.remove();
        });
        fpnSection6SetCount = 1;

        // Hide conditional sections
        document.getElementById('fpn_collector_other_text').style.display = 'none';
        document.getElementById('fpn_collector_other_text').disabled = true;
        const colorAdj = document.getElementById('fpn_color_adj_detail');
        if (colorAdj) { colorAdj.style.display = 'none'; colorAdj.disabled = true; }
        const resize = document.getElementById('fpn_resize_detail');
        if (resize) { resize.style.display = 'none'; resize.disabled = true; }

        // Reset dates
        const today = '<?= $todayDateFPN ?>';
        const nowTime = '<?= $todayTimeFPN ?>';
        ['fpn_receive_date', 'fpn_incident_date', 'fpn_known_date', 'fpn_collect_date'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = today;
        });
        ['fpn_receive_time', 'fpn_incident_time', 'fpn_known_time', 'fpn_collect_time'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = nowTime;
        });
    };

    // =================================================================
    // ผู้ส่งของกลาง - Auto-fill ตำแหน่ง
    // =================================================================
    const senderElN = document.getElementById('fpn_evidence_sender');
    if (senderElN) {
        senderElN.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const pos = opt ? (opt.getAttribute('data-position') || '') : '';
            document.getElementById('fpn_evidence_sender_position').value = pos;
        });
    }

    // =================================================================
    // Phone format: xxx-xxx-xxxx
    // =================================================================
    document.querySelectorAll('#addCheckListModalFingerprintNew .fpf-phone-format').forEach(function(el) {
        el.addEventListener('input', function() {
            let v = this.value.replace(/\D/g, '').substring(0, 10);
            if (v.length > 6) v = v.slice(0,3) + '-' + v.slice(3,6) + '-' + v.slice(6);
            else if (v.length > 3) v = v.slice(0,3) + '-' + v.slice(3);
            this.value = v;
        });
    });

})();
</script>
