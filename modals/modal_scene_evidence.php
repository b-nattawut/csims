<?php
/**
 * Modal: ตรวจเก็บวัตถุพยานที่เกิดเหตุ (Standard Form)
 * complaints_type = '07'
 * Prefix: ev7_
 */
date_default_timezone_set('Asia/Bangkok');

$inspectorOptionsEV7 = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
if (isset($pdo)) {
    $qryEV7 = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname,
               IFNULL(t3.position_name, '-') AS position_name
               FROM user_profile t1
               LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
               LEFT JOIN user_position t3 ON t1.position_id = t3.position_id
               ORDER BY t1.user_id DESC";
    $stmtEV7 = $pdo->query($qryEV7);
    while ($r = $stmtEV7->fetch(PDO::FETCH_ASSOC)) {
        $inspectorOptionsEV7 .= '<option value="' . $r['user_id'] . '" data-position="' . htmlspecialchars($r['position_name']) . '">' . htmlspecialchars($r['fullname']) . '</option>';
    }
}

$policeStationOptionsEV7 = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qryPS7 = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stmtPS7 = $pdo->query($qryPS7);
    while ($r = $stmtPS7->fetch(PDO::FETCH_ASSOC)) {
        $policeStationOptionsEV7 .= '<option value="' . htmlspecialchars($r['station_name']) . '">' . htmlspecialchars($r['station_name']) . '</option>';
    }
}

$todayDateEV7 = date('Y-m-d');
$todayTimeEV7 = date('H:i');
?>

<!-- ========== Modal: ตรวจเก็บวัตถุพยานที่เกิดเหตุ (Standard) ========== -->
<div class="modal fade" id="addCheckListModalSceneEvidence" aria-labelledby="addCheckListModalSceneEvidenceLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addCheckListModalSceneEvidenceLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4" style="position:relative;">
                <!-- Loading Overlay -->
                <div id="ev7LoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track"><div class="csims-bar-fill"></div></div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>

                <form method="post" id="incidentCheckListFormSceneEvidence" novalidate>
                    <input type="hidden" id="receiveNoti_id_ev7" name="receiveNoti_id_ev7">
                    <input type="hidden" id="doc_no_ev7" name="doc_no_ev7">
                    <input type="hidden" id="report_no_ev7" name="report_no_ev7">

                    <!-- Header -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="editInfoEV7" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountEV7" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto me-3">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="receiveNoti_No_ev7"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="receiveNotiReportNo_ev7"></span>
                            </div>
                        </div>
                        <div class="bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2 flex-shrink-0">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToPdfFormEV7" style="width: 3rem; height: 1.5rem; cursor: pointer;">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToPdfFormEV7" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
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
                                <input type="date" class="form-control" id="ev7_receive_date" name="ev7_receive_date" value="<?= $todayDateEV7 ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เวลา <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="ev7_receive_time" name="ev7_receive_time" value="<?= $todayTimeEV7 ?>" step="60">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">หน่วยงาน</label>
                                <select class="form-select" id="ev7_unit_type" name="ev7_unit_type">
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
                                    <input type="text" class="form-control" id="ev7_unit_name" name="ev7_unit_name" placeholder="ระบุชื่อหน่วยงาน">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev7_unit_name" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ช่องทางการรับแจ้ง <span class="text-danger">*</span></label>
                            <div class="bg-body-tertiary p-3 rounded-3 border">
                                <div class="row g-4">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ev7_notify_method[]" value="ตามหนังสือ" id="ev7_notify_letter">
                                            <label class="form-check-label" for="ev7_notify_letter">ตามหนังสือ</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ev7_notify_method[]" value="ทางโทรศัพท์" id="ev7_notify_phone">
                                            <label class="form-check-label" for="ev7_notify_phone">ทางโทรศัพท์</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ev7_notify_method[]" value="ทางวิทยุสื่อสาร" id="ev7_notify_radio">
                                            <label class="form-check-label" for="ev7_notify_radio">ทางวิทยุสื่อสาร</label>
                                        </div>
                                    </div>
                                    <div class="col-auto d-flex align-items-center gap-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" name="ev7_notify_method[]" value="อื่นๆ" id="ev7_notify_other">
                                            <label class="form-check-label" for="ev7_notify_other">อื่นๆ</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm" name="ev7_notify_method_other_text" id="ev7_notify_other_text" placeholder="ระบุ" style="width:200px; display:none;" disabled>
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" data-hw-targets="ev7_notify_other_text" title="เขียนด้วยลายมือ" style="padding:1px 6px; font-size:0.7rem; display:none;" id="ev7_notify_other_text_hw"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">จาก สน./สภ. <span class="text-danger">*</span></label>
                                <select id="ev7_police_station" name="ev7_police_station" class="form-select"><?= $policeStationOptionsEV7 ?></select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="ev7_document_no" name="ev7_document_no">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev7_document_no" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ลง</label>
                                <div class="input-group">
                                    <input type="date" class="form-control" id="ev7_document_date" name="ev7_document_date" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">ในคดี</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="ev7_case_no" name="ev7_case_no">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev7_case_no" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">สถานที่เกิดเหตุ <span class="text-danger">*</span> <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="ev7_incident_location" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" id="ev7_incident_location" name="ev7_incident_location" rows="2" placeholder="ระบุสถานที่เกิดเหตุ"></textarea>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เหตุเกิดเมื่อวันที่ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="ev7_incident_date" name="ev7_incident_date" value="<?= $todayDateEV7 ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" id="ev7_incident_time" name="ev7_incident_time" value="<?= $todayTimeEV7 ?>" step="60">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">พนักงานสอบสวน</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="ev7_investigator_name" name="ev7_investigator_name" placeholder="ชื่อพนักงานสอบสวน">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev7_investigator_name" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ขอส่ง</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="ev7_send_request" name="ev7_send_request" placeholder="...">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev7_send_request" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>

                        <!-- รายการของกลาง (dynamic) -->
                        <div class="mb-3 mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary border-bottom pb-2 mb-0 flex-grow-1">รายการของกลาง</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addEvidenceItemEV7()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                </button>
                            </div>
                            <div id="ev7_evidence_items_container">
                                <div class="ev7-evidence-item-row input-group mb-2">
                                    <span class="input-group-text fw-bold">1.1</span>
                                    <input type="text" class="form-control" name="ev7_evidence_item[]" placeholder="ระบุรายการของกลาง">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    <select class="form-select lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="max-width:220px;">
                                        <option value="">-- การตรวจพิสูจน์ (เลือกได้หลายข้อ) --</option>
                                        <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                                        <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                        <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                        <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                        <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                        <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                        <option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                                    </select>
                                    <input type="hidden" class="lab-unit-value" name="ev7_lab_unit[]" value="">
                                    <button type="button" class="btn btn-outline-danger" onclick="removeEV7Row(this)"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 2. จุดประสงค์ในการตรวจ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">2. จุดประสงค์ในการตรวจ</legend>

                        <p class="text-muted mb-3">เพื่อทำการตรวจเก็บ:</p>
                        <div class="bg-body-tertiary p-3 rounded-3 border mb-3">
                            <div class="row g-3">
                                <div class="col-auto">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="ev7_purpose[]" value="รอยลายนิ้วมือแฝง" id="ev7_purpose_fingerprint">
                                        <label class="form-check-label" for="ev7_purpose_fingerprint">รอยลายนิ้วมือแฝง</label>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="ev7_purpose[]" value="สารพันธุกรรม" id="ev7_purpose_dna">
                                        <label class="form-check-label" for="ev7_purpose_dna">สารพันธุกรรม</label>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="ev7_purpose[]" value="วัตถุพยานอื่นๆ" id="ev7_purpose_other_ev">
                                        <label class="form-check-label" for="ev7_purpose_other_ev">วัตถุพยานอื่นๆ</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">ที่...ของกลางดังกล่าว <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="ev7_purpose_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" id="ev7_purpose_detail" name="ev7_purpose_detail" rows="2" placeholder="บุคคลดังกล่าว / ระบุรายละเอียดเพิ่มเติม"></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 3. ผลการตรวจ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">3. ผลการตรวจ</legend>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ได้ทำการตรวจของกลางที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="ev7_inspect_location" name="ev7_inspect_location">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev7_inspect_location" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control" id="ev7_inspect_date" name="ev7_inspect_date" value="<?= $todayDateEV7 ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" id="ev7_inspect_time" name="ev7_inspect_time" value="<?= $todayTimeEV7 ?>" step="60">
                            </div>
                        </div>

                        <!-- 3.1 ลักษณะของกลาง -->
                        <div class="mb-4 mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary border-bottom pb-2 mb-0 flex-grow-1">3.1 ลักษณะของกลาง</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addExhibitDescEV7()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                </button>
                            </div>
                            <p class="text-muted small mb-2">(ให้ระบุสภาพทั่วไป เช่น รูปร่าง สี ขนาด ยี่ห้อ เป็นต้นและจำนวน)</p>
                            <div id="ev7_exhibit_desc_container">
                                <div class="ev7-exhibit-desc-row input-group mb-2">
                                    <span class="input-group-text fw-bold">3.1.1</span>
                                    <span class="input-group-text">ของกลางรายการที่ 1 เป็น</span>
                                    <input type="text" class="form-control" name="ev7_exhibit_desc[]" placeholder="ระบุลักษณะของกลาง">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    <button type="button" class="btn btn-outline-danger" onclick="removeEV7Row(this)"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>

                        <!-- 3.2 วัตถุพยานที่ตรวจแก็บ -->
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">3.2 วัตถุพยานที่ตรวจแก็บ</h6>

                            <!-- 3.2.1 ตรวจเก็บ -->
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3">
                                <p class="fw-bold mb-2">3.2.1 ตรวจเก็บ</p>
                                <div class="row g-3 mb-3">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ev7_collect_type[]" value="รอยลายนิ้วมือแฝง" id="ev7_collect_fingerprint">
                                            <label class="form-check-label" for="ev7_collect_fingerprint">รอยลายนิ้วมือแฝง</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ev7_collect_type[]" value="ฝ่ามือแฝง" id="ev7_collect_palm">
                                            <label class="form-check-label" for="ev7_collect_palm">ฝ่ามือแฝง</label>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ev7_collect_type[]" value="ฝ่าเท้าแฝง" id="ev7_collect_foot">
                                            <label class="form-check-label" for="ev7_collect_foot">ฝ่าเท้าแฝง</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">จำนวน (แผ่น)</label>
                                        <input type="text" class="form-control" name="ev7_collect_sheet_count" id="ev7_collect_sheet_count" inputmode="numeric">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label">ที่</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="ev7_collect_location" id="ev7_collect_location">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev7_collect_location" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                </div>

                                <!-- รายละเอียดย่อย 3.2.1.x -->
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold text-secondary">รายละเอียด</span>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addCollectDetailEV7()">
                                            <i class="fas fa-plus me-1"></i> เพิ่ม
                                        </button>
                                    </div>
                                    <div id="ev7_collect_detail_container">
                                        <div class="ev7-collect-detail-row input-group mb-2">
                                            <span class="input-group-text fw-bold">3.2.1.1</span>
                                            <input type="text" class="form-control" name="ev7_collect_detail[]" placeholder="ระบุรายละเอียด">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            <button type="button" class="btn btn-outline-danger" onclick="removeEV7Row(this)"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 3.2.2 วัตถุพยานประเภทอื่น -->
                            <div class="bg-body-tertiary p-3 rounded-3 border mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <p class="fw-bold mb-0">3.2.2 ระบุวัตถุพยานประเภทอื่น</p>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addOtherEvidenceEV7()">
                                        <i class="fas fa-plus me-1"></i> เพิ่ม
                                    </button>
                                </div>
                                <div id="ev7_other_evidence_container">
                                    <div class="ev7-other-evidence-row input-group mb-2">
                                        <input type="text" class="form-control" name="ev7_other_evidence[]" placeholder="ระบุวัตถุพยานประเภทอื่น">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        <button type="button" class="btn btn-outline-danger" onclick="removeEV7Row(this)"><i class="fas fa-times"></i></button>
                                    </div>
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
                                        <input type="text" class="form-control" name="ev7_witness_name" id="ev7_witness_name">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev7_witness_name" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ลงลายมือชื่อ/พิมพ์ลายนิ้วมือ ในแบบ</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="ev7_witness_form" id="ev7_witness_form">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev7_witness_form" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">รายละเอียดเพิ่มเติมของพยาน <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="ev7_witness_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                    <textarea class="form-control" name="ev7_witness_detail" id="ev7_witness_detail" rows="2"></textarea>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">การนำส่ง</label>
                                    <select class="form-select" name="ev7_handover_method" id="ev7_handover_method">
                                        <option value="" selected disabled>เลือก</option>
                                        <option value="นำส่ง">นำส่ง</option>
                                        <option value="ส่งมอบ">ส่งมอบ</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">วัตถุพยานตามข้อ</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="ev7_handover_item_ref" id="ev7_handover_item_ref" value="3.2" readonly>
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev7_handover_item_ref" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">ให้ (ผู้รับ)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="ev7_handover_to" id="ev7_handover_to">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev7_handover_to" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">รายละเอียดการนำส่ง/ส่งมอบ</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="ev7_handover_method_detail" id="ev7_handover_method_detail">
                                        <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev7_handover_method_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">เพื่อดำเนินการต่อไป (รายละเอียด) <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="ev7_handover_purpose" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                    <textarea class="form-control" name="ev7_handover_purpose" id="ev7_handover_purpose" rows="2"></textarea>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">การดำเนินการต่อไป <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="ev7_handover_next_action" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                    <textarea class="form-control" name="ev7_handover_next_action" id="ev7_handover_next_action" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 4. ผู้ตรวจ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">4. ผู้ตรวจสถานที่เกิดเหตุ</legend>
                        <div id="ev7_inspector_container">
                            <div class="d-flex align-items-center mb-3 ev7-inspector-row">
                                <div class="text-end pe-3" style="width: 50px;">
                                    <span class="fw-bold text-secondary ev7-index-label">4.1</span>
                                </div>
                                <div class="flex-grow-1">
                                    <select class="form-select ev7-inspector-select" name="ev7_inspector_id[]"><?= $inspectorOptionsEV7 ?></select>
                                </div>
                                <div class="ms-2" style="width: 32px;"></div>
                            </div>
                        </div>
                        <div class="d-flex mt-2">
                            <div style="width: 50px;"></div>
                            <div class="flex-grow-1">
                                <button type="button" class="btn btn-outline-primary border-dashed w-100 py-2" id="btn_add_inspector_ev7">
                                    <i class="fas fa-plus-circle me-1"></i> เพิ่มผู้ตรวจ
                                </button>
                            </div>
                            <div class="ms-2" style="width: 32px;"></div>
                        </div>
                    </fieldset>

                    <!-- ==================== 5. ภาพถ่าย ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">5. บันทึกการถ่ายภาพ</legend>
                        <div class="row mb-4">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">รหัสภาพถ่ายที่</label>
                                <input type="text" class="form-control" name="ev7_photo_id_start" id="ev7_photo_id_start">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">ถึง</label>
                                <input type="text" class="form-control" name="ev7_photo_id_end" id="ev7_photo_id_end">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">จำนวน (ภาพ)</label>
                                <input type="text" class="form-control" name="ev7_photo_amount" id="ev7_photo_amount" inputmode="numeric">
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">ผู้จดบันทึก</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="ev7_photographer_name" id="ev7_photographer_name" placeholder="ชื่อผู้จดบันทึก">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="ev7_photographer_name" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">วัน/เวลา</label>
                                <input type="datetime-local" class="form-control" name="ev7_photographer_datetime" id="ev7_photographer_datetime">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <div id="ev7_photo_dropzone" class="d-flex flex-column align-items-center justify-content-center text-center"
                                    style="border:2px dashed #b0bec5; border-radius:12px; padding:24px; cursor:pointer; background:#f8f9fa; transition:all 0.2s;">
                                    <i class="fas fa-cloud-upload-alt mb-2" style="font-size:1.8rem; color:#90a4ae;"></i>
                                    <div class="text-muted">ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="button" class="btn btn-primary" id="btn_choose_file_ev7">
                                        <i class="fas fa-folder-open me-2"></i>เลือกไฟล์รูปภาพ
                                    </button>
                                    <button type="button" class="btn btn-success" id="btn_open_camera_ev7">
                                        <i class="fas fa-camera me-2"></i>เปิดกล้องถ่ายภาพ
                                    </button>
                                </div>
                            </div>
                        </div>
                        <input type="file" id="incident_photos_ev7" name="incident_photos_ev7[]" accept="image/*" style="display:none;" multiple>
                        <input type="file" id="camera_input_ev7" accept="image/*" capture="environment" style="display:none;" multiple>
                        <div id="attachments_wrapper_ev7" class="d-none mt-4">
                            <h6 class="fw-bold text-secondary mb-3">
                                Attachments <span class="badge bg-secondary rounded-pill ms-1" id="file_count_badge_ev7">0</span>
                            </h6>
                            <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-3" id="attachments_grid_ev7"></div>
                        </div>
                    </fieldset>

                    <!-- ==================== 6. ลงนาม ==================== -->
                    <fieldset class="p-4 bg-white rounded-4 shadow-sm border mb-4">
                        <legend class="fieldset-header">6. ลงนาม</legend>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small text-muted">วันที่</label>
                                <input type="date" class="form-control" id="ev7_sign_date" name="ev7_sign_date" value="<?= $todayDateEV7 ?>">
                            </div>
                        </div>
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="p-3 bg-light rounded border h-100">
                                    <h6 class="fw-bold text-secondary border-bottom pb-2 mb-3">
                                        <i class="fas fa-user-check me-2"></i>ผู้รับมอบวัตถุพยาน
                                    </h6>
                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ลงชื่อ <span class="text-danger">*</span></label>
                                            <select class="form-select ev7-user-select" id="ev7_receiver_id" name="ev7_receiver_id" data-pos-target="#ev7_receiver_position"><?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้รับมอบ --', $inspectorOptionsEV7) ?></select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ตำแหน่ง</label>
                                            <input type="text" class="form-control bg-light" id="ev7_receiver_position" name="ev7_receiver_position" readonly>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small text-muted">ลายเซ็น</label>
                                        <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden w-100" style="height: 180px;">
                                            <canvas id="sig-canvas-ev7-receiver" class="signature-pad w-100 h-100"></canvas>
                                            <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none">
                                                <i class="fas fa-signature fa-2x mb-1 d-block text-center"></i> เซ็นชื่อที่นี่
                                            </div>
                                        </div>
                                        <div class="text-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('sig-canvas-ev7-receiver')"><i class="fas fa-eraser me-1"></i> ล้าง</button>
                                        </div>
                                        <input type="hidden" name="ev7_receiver_signature_data" id="ev7_receiver_signature_data">
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="p-3 bg-light rounded border h-100">
                                    <h6 class="fw-bold text-secondary border-bottom pb-2 mb-3">
                                        <i class="fas fa-user-check me-2"></i>ผู้ส่งมอบวัตถุพยาน
                                    </h6>
                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ลงชื่อ <span class="text-danger">*</span></label>
                                            <select class="form-select ev7-user-select" id="ev7_sender_id" name="ev7_sender_id" data-pos-target="#ev7_sender_position"><?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งมอบ --', $inspectorOptionsEV7) ?></select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ตำแหน่ง</label>
                                            <input type="text" class="form-control bg-light" id="ev7_sender_position" name="ev7_sender_position" readonly>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small text-muted">ลายเซ็น</label>
                                        <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden w-100" style="height: 180px;">
                                            <canvas id="sig-canvas-ev7-sender" class="signature-pad w-100 h-100"></canvas>
                                            <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none">
                                                <i class="fas fa-signature fa-2x mb-1 d-block text-center"></i> เซ็นชื่อที่นี่
                                            </div>
                                        </div>
                                        <div class="text-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('sig-canvas-ev7-sender')"><i class="fas fa-eraser me-1"></i> ล้าง</button>
                                        </div>
                                        <input type="hidden" name="ev7_sender_signature_data" id="ev7_sender_signature_data">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Save / Cancel -->
                    <div class="modal-footer justify-content-end">
                        <button type="button" class="btn btn-success" id="btn_save_ev7" onclick="prepareDataForSubmissionSceneEvidence()">
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
// ===================== Dynamic Row Functions =====================
var ev7EvidenceIdx = 1;
function addEvidenceItemEV7() {
    ev7EvidenceIdx++;
    var idx = '1.' + ev7EvidenceIdx;
    var html = '<div class="ev7-evidence-item-row input-group mb-2">' +
        '<span class="input-group-text fw-bold">' + idx + '</span>' +
        '<input type="text" class="form-control" name="ev7_evidence_item[]" placeholder="ระบุรายการของกลาง">' +
        '<button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>' +
        '<select class="form-select lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="max-width:220px;">' + ev7LabUnitOptions + '</select>' +
        '<input type="hidden" class="lab-unit-value" name="ev7_lab_unit[]" value="">' +
        '<button type="button" class="btn btn-outline-danger" onclick="removeEV7Row(this)"><i class="fas fa-times"></i></button></div>';
    $('#ev7_evidence_items_container').append(html);
    renumberEV7Rows('#ev7_evidence_items_container', '1.');
}

var ev7ExhibitIdx = 1;
function addExhibitDescEV7() {
    ev7ExhibitIdx++;
    var html = '<div class="ev7-exhibit-desc-row input-group mb-2">' +
        '<span class="input-group-text fw-bold">3.1.' + ev7ExhibitIdx + '</span>' +
        '<span class="input-group-text">ของกลางรายการที่ ' + ev7ExhibitIdx + ' เป็น</span>' +
        '<input type="text" class="form-control" name="ev7_exhibit_desc[]" placeholder="ระบุลักษณะของกลาง">' +
        '<button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>' +
        '<button type="button" class="btn btn-outline-danger" onclick="removeEV7Row(this)"><i class="fas fa-times"></i></button></div>';
    $('#ev7_exhibit_desc_container').append(html);
    renumberEV7ExhibitRows();
}

var ev7CollectDetailIdx = 1;
function addCollectDetailEV7() {
    ev7CollectDetailIdx++;
    var html = '<div class="ev7-collect-detail-row input-group mb-2">' +
        '<span class="input-group-text fw-bold">3.2.1.' + ev7CollectDetailIdx + '</span>' +
        '<input type="text" class="form-control" name="ev7_collect_detail[]" placeholder="ระบุรายละเอียด">' +
        '<button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>' +
        '<button type="button" class="btn btn-outline-danger" onclick="removeEV7Row(this)"><i class="fas fa-times"></i></button></div>';
    $('#ev7_collect_detail_container').append(html);
    renumberEV7CollectDetailRows();
}

function addOtherEvidenceEV7() {
    var html = '<div class="ev7-other-evidence-row input-group mb-2">' +
        '<input type="text" class="form-control" name="ev7_other_evidence[]" placeholder="ระบุวัตถุพยานประเภทอื่น">' +
        '<button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>' +
        '<button type="button" class="btn btn-outline-danger" onclick="removeEV7Row(this)"><i class="fas fa-times"></i></button></div>';
    $('#ev7_other_evidence_container').append(html);
}

var ev7LabUnitOptions = '<option value="">-- การตรวจพิสูจน์ (เลือกได้หลายข้อ) --</option>' +
    '<option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>' +
    '<option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>' +
    '<option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>' +
    '<option value="drug">กลุ่มงานตรวจยาเสพติด</option>' +
    '<option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>' +
    '<option value="document">กลุ่มงานตรวจเอกสาร</option>' +
    '<option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>';

// addLabUnitEV7 kept as no-op for backward compatibility (lab_unit is now inline in evidence rows)
function addLabUnitEV7() { }

function removeEV7Row(btn) {
    var parent = $(btn).closest('.input-group');
    var container = parent.parent();
    parent.remove();
    // renumber
    if (container.attr('id') === 'ev7_evidence_items_container') renumberEV7Rows('#ev7_evidence_items_container', '1.');
    if (container.attr('id') === 'ev7_exhibit_desc_container') renumberEV7ExhibitRows();
    if (container.attr('id') === 'ev7_collect_detail_container') renumberEV7CollectDetailRows();
}

function renumberEV7Rows(containerSel, prefix) {
    $(containerSel).children().each(function(i) {
        $(this).find('.input-group-text').first().text(prefix + (i + 1));
    });
    ev7EvidenceIdx = $(containerSel).children().length;
}

function renumberEV7ExhibitRows() {
    $('#ev7_exhibit_desc_container').children().each(function(i) {
        var n = i + 1;
        $(this).find('.input-group-text').first().text('3.1.' + n);
        $(this).find('.input-group-text').eq(1).text('ของกลางรายการที่ ' + n + ' เป็น');
    });
    ev7ExhibitIdx = $('#ev7_exhibit_desc_container').children().length;
}

function renumberEV7CollectDetailRows() {
    $('#ev7_collect_detail_container').children().each(function(i) {
        $(this).find('.input-group-text').first().text('3.2.1.' + (i + 1));
    });
    ev7CollectDetailIdx = $('#ev7_collect_detail_container').children().length;
}

// Helper: Thai numeral (simplified)
function toThaiNumeral(str) { return str; }

// ===================== Inspector Dynamic Rows =====================
var ev7InspectorIdx = 1;
$(document).on('click', '#btn_add_inspector_ev7', function() {
    ev7InspectorIdx++;
    var html = '<div class="d-flex align-items-center mb-3 ev7-inspector-row">' +
        '<div class="text-end pe-3" style="width:50px;"><span class="fw-bold text-secondary ev7-index-label">4.' + ev7InspectorIdx + '</span></div>' +
        '<div class="flex-grow-1"><select class="form-select ev7-inspector-select" name="ev7_inspector_id[]">' + inspectorOptionsHTMLEV7 + '</select></div>' +
        '<div class="ms-2" style="width:32px;"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeInspectorEV7(this)"><i class="fas fa-times"></i></button></div></div>';
    $('#ev7_inspector_container').append(html);
});

function removeInspectorEV7(btn) {
    $(btn).closest('.ev7-inspector-row').remove();
    $('#ev7_inspector_container .ev7-index-label').each(function(i) {
        $(this).text('4.' + (i + 1));
    });
    ev7InspectorIdx = $('#ev7_inspector_container .ev7-inspector-row').length;
}

// ===================== Toggle "อื่นๆ" text field =====================
$('#ev7_notify_other').on('change', function() {
    if (this.checked) { $('#ev7_notify_other_text').show().prop('disabled', false); $('#ev7_notify_other_text_hw').show(); }
    else { $('#ev7_notify_other_text').hide().prop('disabled', true).val(''); $('#ev7_notify_other_text_hw').hide(); }
});

// ===================== Auto-fill position from select =====================
$(document).on('change', '.ev7-user-select', function() {
    var pos = $(this).find(':selected').data('position') || '';
    var target = $(this).data('pos-target');
    if (target) $(target).val(pos);
});

// ===================== Photo Handling =====================
var attachmentStoreEV7 = [];
var deletedExistingPhotosEV7 = [];

$('#btn_choose_file_ev7').on('click', function() { $('#incident_photos_ev7').trigger('click'); });
$('#btn_open_camera_ev7').on('click', function() { $('#camera_input_ev7').trigger('click'); });

// ★ Drag & Drop zone (ฟอร์มปกติวัตถุพยานที่เกิดเหตุ)
(function() {
    var ev7DZ = document.getElementById('ev7_photo_dropzone');
    if (!ev7DZ) return;
    ev7DZ.addEventListener('click', function() { $('#incident_photos_ev7').trigger('click'); });
    ['dragenter', 'dragover'].forEach(function(evt) {
        ev7DZ.addEventListener(evt, function(e) { e.preventDefault(); e.stopPropagation(); ev7DZ.style.borderColor = '#2196F3'; ev7DZ.style.background = '#e3f2fd'; });
    });
    ['dragleave', 'drop'].forEach(function(evt) {
        ev7DZ.addEventListener(evt, function(e) { e.preventDefault(); e.stopPropagation(); ev7DZ.style.borderColor = '#b0bec5'; ev7DZ.style.background = '#f8f9fa'; });
    });
    ev7DZ.addEventListener('drop', function(e) {
        var files = (e.dataTransfer && e.dataTransfer.files) ? e.dataTransfer.files : null;
        if (!files || !files.length) return;
        Array.from(files).forEach(function(file) {
            if (!file.type.startsWith('image/')) return;
            var id = 'ev7_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
            var reader = new FileReader();
            reader.onload = function(ev) {
                attachmentStoreEV7.push({ id: id, src: ev.target.result, name: file.name, file: file, existing: false });
                renderEV7AttachmentGrid();
            };
            reader.readAsDataURL(file);
        });
    });
})();

$('#incident_photos_ev7, #camera_input_ev7').on('change', function(e) {
    var files = e.target.files;
    if (!files || files.length === 0) return;
    Array.from(files).forEach(function(file) {
        if (!file.type.startsWith('image/')) return;
        var id = 'ev7_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
        var reader = new FileReader();
        reader.onload = function(ev) {
            attachmentStoreEV7.push({ id: id, src: ev.target.result, name: file.name, file: file, existing: false });
            renderEV7AttachmentGrid();
        };
        reader.readAsDataURL(file);
    });
    e.target.value = '';
});

function renderEV7AttachmentGrid() {
    var grid = $('#attachments_grid_ev7');
    grid.empty();
    var total = attachmentStoreEV7.length;
    if (total > 0) {
        $('#attachments_wrapper_ev7').removeClass('d-none');
        $('#file_count_badge_ev7').text(total);
        $('#ev7_photo_amount').val(total);
        // ★ รหัสภาพ = ชื่อไฟล์แรก/ล่าสุด (เฉพาะตอน user แนบ/ลบเอง ไม่ทับค่าที่โหลดจาก DB)
        if (!window.__ev7LoadingPhotos) {
            $('#ev7_photo_id_start').val(attachmentStoreEV7[0].name || 'photo');
            $('#ev7_photo_id_end').val(attachmentStoreEV7[total - 1].name || 'photo');
        }
        attachmentStoreEV7.forEach(function(item) {
            var displayName = (item.name || 'photo').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            var jsName = (item.name || 'photo').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
            var imgSrc = item.src || '';
            var badgeHtml = item.existing ?
                '<div class="pt-2"><span class="badge bg-info text-white fw-normal" style="font-size:0.65rem;">รูปเดิม</span></div>' :
                '<div class="pt-2"><span class="badge bg-success text-white fw-normal" style="font-size:0.65rem;">รูปใหม่</span></div>';
            var card = '<div class="col attachment-item" id="ev7_attach_' + item.id + '">' +
                '<div class="card attachment-card h-100">' +
                '<div class="card-actions-bar">' +
                '<button type="button" class="action-btn" title="ดูรูปภาพ" onclick="showImagePreview(\'' + imgSrc + '\', \'' + jsName + '\')"><i class="far fa-eye" style="font-size:0.8rem;"></i></button>' +
                '<button type="button" class="action-btn delete" title="ลบรูปนี้" onclick="removeEV7Attachment(\'' + item.id + '\')"><i class="fas fa-times" style="font-size:0.85rem;"></i></button>' +
                '</div>' +
                '<div class="img-thumbnail-box"><img src="' + imgSrc + '" alt="' + displayName + '"></div>' +
                '<div class="card-body d-flex flex-column">' +
                '<div class="filename-text mb-auto" title="' + displayName + '">' + displayName + '</div>' +
                badgeHtml +
                '</div></div></div>';
            grid.append(card);
        });
    } else {
        $('#attachments_wrapper_ev7').addClass('d-none');
        $('#file_count_badge_ev7').text(0);
        $('#ev7_photo_id_start').val('');
        $('#ev7_photo_id_end').val('');
        $('#ev7_photo_amount').val('');
    }
}

function removeEV7Attachment(id) {
    var item = attachmentStoreEV7.find(function(x) { return x.id === id; });
    if (item && item.existing) {
        deletedExistingPhotosEV7.push({ file_id: item.db_file_id, db_filename: item.db_filename });
    }
    attachmentStoreEV7 = attachmentStoreEV7.filter(function(x) { return x.id !== id; });
    renderEV7AttachmentGrid();
}

// ===================== Reset Form =====================
function resetSceneEvidenceForm() {
    var form = document.getElementById('incidentCheckListFormSceneEvidence');
    if (form) form.reset();
    if (typeof window.resetSceneEvidencePdfForm === 'function') window.resetSceneEvidencePdfForm();
    $('#editInfoEV7').addClass('d-none');
    attachmentStoreEV7 = [];
    deletedExistingPhotosEV7 = [];
    renderEV7AttachmentGrid();
    ev7EvidenceIdx = 1; ev7ExhibitIdx = 1; ev7CollectDetailIdx = 1; ev7InspectorIdx = 1;
    // Reset dynamic containers to 1 row
    $('#ev7_evidence_items_container').html($('#ev7_evidence_items_container').children().first().prop('outerHTML') || '');
    $('#ev7_exhibit_desc_container').html($('#ev7_exhibit_desc_container').children().first().prop('outerHTML') || '');
    $('#ev7_collect_detail_container').html($('#ev7_collect_detail_container').children().first().prop('outerHTML') || '');
    $('#ev7_other_evidence_container').html($('#ev7_other_evidence_container').children().first().prop('outerHTML') || '');
    $('#ev7_inspector_container').html($('#ev7_inspector_container').children().first().prop('outerHTML') || '');
    // Clear signature
    ['sig-canvas-ev7-receiver', 'sig-canvas-ev7-sender', 'sig-canvas-ev7-signer'].forEach(function(id) {
        if (signaturePads[id]) signaturePads[id].clear();
    });
}
</script>
