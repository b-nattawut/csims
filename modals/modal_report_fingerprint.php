<?php
/**
 * Modal: ร่างรายงานการตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)
 * โครงสร้างเหมือน modal_report_fire.php | Input เหมือน checklist ลายนิ้วมือแฝง
 * Prefix: rlf_
 */

$inspectorOptionsRLF = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
if (isset($pdo)) {
    $qryInsRLF = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname,
                  IFNULL(t3.position_name, '-') AS position_name
                  FROM user_profile t1 
                  LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                  LEFT JOIN user_position t3 ON t1.position_id = t3.position_id
                  ORDER BY t1.user_id DESC";
    $stmtRLF = $pdo->query($qryInsRLF);
    while ($rowRLF = $stmtRLF->fetch(PDO::FETCH_ASSOC)) {
        $inspectorOptionsRLF .= '<option value="' . $rowRLF['user_id'] . '" data-position="' . htmlspecialchars($rowRLF['position_name']) . '">' . htmlspecialchars($rowRLF['fullname']) . '</option>';
    }
}

$policeStationOptionsRLF = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qryPSRLF = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stmtPSRLF = $pdo->query($qryPSRLF);
    while ($rowPSRLF = $stmtPSRLF->fetch(PDO::FETCH_ASSOC)) {
        $policeStationOptionsRLF .= '<option value="' . htmlspecialchars($rowPSRLF['station_name']) . '">' . htmlspecialchars($rowPSRLF['station_name']) . '</option>';
    }
}
?>

<div class="modal fade" id="modalReportFingerprint" aria-labelledby="modalReportFingerprintLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportFingerprintLabel">
                    รายงานการตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body bg-light p-4">
                <form id="formReportFingerprint" novalidate>
                    <input type="hidden" id="rlf_incident_id" name="incident_id">
                    <input type="hidden" id="rlf_agency_name" name="rlf_agency_name">
                    <input type="hidden" id="rlf_report_no_display_pdf" name="rlf_report_no_display_pdf">
                    <input type="hidden" id="rlf_report_year_pdf" name="rlf_report_year_pdf">

                    <!-- Switch to PDF form -->
                                        <!-- Header Info -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="rlf_editInfo" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="rlf_editCount" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto me-3">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="rlf_doc_no_display"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="rlf_report_no_display"></span>
                            </div>
                        </div>
                    
                        <div class="bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2 flex-shrink-0">
                        <div class="form-check form-switch mb-0" style="padding-left: 0;">
                        <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchFingerprintReportToPdf"
                        style="width: 3rem; height: 1.5rem; cursor: pointer;">
                        </div>
                        <label class="fw-bold text-danger mb-0" for="switchFingerprintReportToPdf" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                        <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                        </label>
                        </div>
                    </div>

                    <!-- ==================== 1. การรับแจ้ง ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">1. การรับแจ้ง</legend>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">วันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rlf_receive_date" id="rlf_receive_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">เวลา</label>
                                <input type="time" class="form-control form-control-sm" name="rlf_receive_time" id="rlf_receive_time">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">ปจว.ข้อที่</label>
                                <input type="text" class="form-control form-control-sm" name="rlf_case_no" id="rlf_case_no">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">สน./สภ.</label>
                                <select class="form-select form-select-sm" name="rlf_police_station" id="rlf_police_station">
                                    <?= $policeStationOptionsRLF ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">ที่</label>
                                <input type="text" class="form-control form-control-sm" name="rlf_letter_no" id="rlf_letter_no">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">ลง</label>
                                <input type="date" class="form-control form-control-sm" name="rlf_letter_date" id="rlf_letter_date" value="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ผู้ส่งของกลาง</label>
                                <select class="form-select form-select-sm" name="rlf_evidence_sender" id="rlf_evidence_sender">
                                    <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งของกลาง --', $inspectorOptionsRLF) ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">ตำแหน่ง</label>
                                <input type="text" class="form-control form-control-sm" name="rlf_evidence_sender_position" id="rlf_evidence_sender_position" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">โทร</label>
                                <input type="tel" class="form-control form-control-sm" name="rlf_evidence_sender_phone" id="rlf_evidence_sender_phone" maxlength="12">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">รับวัตถุพยานตามหนังสือ</label>
                                <input type="text" class="form-control form-control-sm" name="rlf_evidence_letter_no" id="rlf_evidence_letter_no">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">ที่</label>
                                <input type="text" class="form-control form-control-sm" name="rlf_evidence_doc_no" id="rlf_evidence_doc_no">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">ลง</label>
                                <input type="date" class="form-control form-control-sm" name="rlf_evidence_doc_date" id="rlf_evidence_doc_date" value="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">จุดประสงค์ในการตรวจพิสูจน์</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_purpose[]" value="เพื่อตรวจเก็บรอยลายนิ้วมือแฝง" id="rlf_purpose_fingerprint">
                                        <label class="form-check-label" for="rlf_purpose_fingerprint">เพื่อตรวจเก็บรอยลายนิ้วมือแฝง</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_purpose[]" value="อื่นๆ" id="rlf_purpose_other">
                                        <label class="form-check-label" for="rlf_purpose_other">อื่นๆ</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="rlf_purpose_other_text" id="rlf_purpose_other_text" placeholder="ระบุ..." style="width:200px;">
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 2. วันเวลาที่เกิดเหตุ/ที่ทราบเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">2. วันเวลาที่เกิดเหตุ/ที่ทราบเหตุ</legend>

                        <div class="row g-3">
                            <div class="col-12">
                                <span class="fw-semibold text-dark">วันเวลาที่เกิดเหตุ</span>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">วันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rlf_incident_date" id="rlf_incident_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rlf_incident_time" id="rlf_incident_time">
                            </div>

                            <div class="col-12 mt-3">
                                <span class="fw-semibold text-dark">วันเวลาที่ทราบเหตุ</span>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">วันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rlf_known_date" id="rlf_known_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rlf_known_time" id="rlf_known_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 3. วันเวลาที่ตรวจเก็บ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">3. วันเวลาที่ตรวจเก็บ</legend>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">วันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rlf_collect_date" id="rlf_collect_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rlf_collect_time" id="rlf_collect_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 4. ผู้ตรวจพิสูจน์ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">4. ผู้ตรวจพิสูจน์</legend>
                        <div id="rlf_inspector_container" class="rp-inspector-container-wrap">
                            <div class="row g-2 mb-2 align-items-center rp-inspector-row">
                                <div class="col-auto">
                                    <span class="fw-semibold rp-inspector-num" style="min-width:32px; display:inline-block;">4.1</span>
                                </div>
                                <div class="col">
                                    <select class="form-select form-select-sm rp-inspector-select" name="rlf_inspector_name[]">
                                        <?= $inspectorOptionsRLF ?>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <span class="fw-semibold">ตำแหน่ง</span>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control form-control-sm rp-inspector-position" name="rlf_inspector_position[]" placeholder="ตำแหน่ง" readonly>
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-sm btn-outline-danger rp-remove-inspector" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2 rp-add-inspector-btn" id="rlf_add_inspector_btn">
                            <i class="fas fa-plus me-1"></i> เพิ่มผู้ตรวจ
                        </button>
                    </fieldset>

                    <!-- ==================== 5. ลักษณะการหีบห่อวัตถุพยาน ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">5. ลักษณะการหีบห่อวัตถุพยาน</legend>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ลักษณะการบรรจุ</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_package_type[]" value="บรรจุในซองวัตถุพยาน" id="rlf_pkg_envelope">
                                        <label class="form-check-label" for="rlf_pkg_envelope">บรรจุในซองวัตถุพยาน</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_package_type[]" value="พลาสติก" id="rlf_pkg_plastic">
                                        <label class="form-check-label" for="rlf_pkg_plastic">พลาสติก</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">สภาพการปิดผนึก</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_seal_condition[]" value="มีการปิดผนึกพร้อมลงลายมือชื่อกำกับ" id="rlf_seal_signed">
                                        <label class="form-check-label" for="rlf_seal_signed">การปิดผนึกพร้อมลงลายมือชื่อกำกับ</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_seal_condition[]" value="เขียนรายละเอียดหน้าซองครบถ้วน" id="rlf_seal_detail_complete">
                                        <label class="form-check-label" for="rlf_seal_detail_complete">เขียนรายละเอียดหน้าซองวัตถุพยาน</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ผู้เก็บรักษา</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rlf_collector_type" value="เก็บโดยเจ้าหน้าที่พิสูจน์หลักฐาน" id="rlf_collector_forensic">
                                        <label class="form-check-label" for="rlf_collector_forensic">เจ้าหน้าที่พิสูจน์หลักฐาน</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rlf_collector_type" value="เก็บโดยพนักงานสอบสวน" id="rlf_collector_investigator">
                                        <label class="form-check-label" for="rlf_collector_investigator">พนักงานสอบสวน</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rlf_collector_type" value="เก็บโดย อื่นๆ" id="rlf_collector_other">
                                        <label class="form-check-label" for="rlf_collector_other">อื่นๆ</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="rlf_collector_other_text" id="rlf_collector_other_text" placeholder="ระบุ..." style="width:200px;">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">เก็บเมื่อ</label>
                                <input type="date" class="form-control form-control-sm" name="rlf_storage_date" id="rlf_storage_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rlf_storage_time" id="rlf_storage_time">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ระยะเวลา</label>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <input type="number" class="form-control form-control-sm" name="rlf_duration_year" id="rlf_duration_year" min="0" style="width:80px;" placeholder="0">
                                    <span>ปี</span>
                                    <input type="number" class="form-control form-control-sm" name="rlf_duration_month" id="rlf_duration_month" min="0" max="12" style="width:80px;" placeholder="0">
                                    <span>เดือน</span>
                                    <input type="number" class="form-control form-control-sm" name="rlf_duration_day" id="rlf_duration_day" min="0" max="31" style="width:80px;" placeholder="0">
                                    <span>วัน</span>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 6. ลักษณะวัตถุพยาน ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">6. ลักษณะวัตถุพยาน</legend>

                        <div id="rlf_evidence_sets_container">
                            <!-- ชุดที่ 1 -->
                            <div class="rlf-evidence-set border rounded p-3 mb-3" data-set-index="1">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold text-primary mb-0">ชุดที่ 1</h6>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">จำนวนวัตถุพยานทั้งสิ้น</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control" name="rlf_evidence_total[]" min="0" placeholder="0">
                                            <span class="input-group-text">รายการ</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="rlf-evidence-items">
                                    <!-- รายการที่ 1 -->
                                    <div class="rlf-evidence-card border rounded p-3 mb-2 bg-light">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-primary rlf-ev-badge">รายการที่ 1</span>
                                            <button type="button" class="btn btn-sm btn-outline-danger rlf-remove-evidence" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-12">
                                                <label class="form-label">เป็น</label>
                                                <input type="text" class="form-control form-control-sm" name="rlf_ev_description[]">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">ก/ผคก. (cm)</label>
                                                <input type="text" class="form-control form-control-sm" name="rlf_ev_width[]">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">ย. (cm)</label>
                                                <input type="text" class="form-control form-control-sm" name="rlf_ev_length[]">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">ส. (cm)</label>
                                                <input type="text" class="form-control form-control-sm" name="rlf_ev_height[]">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">จำนวน</label>
                                                <input type="text" class="form-control form-control-sm" name="rlf_ev_quantity[]">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">ป้ายหมายเลข</label>
                                                <input type="text" class="form-control form-control-sm" name="rlf_ev_label_no[]">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">การตรวจพิสูจน์</label>
                                                <select class="form-select form-select-sm" name="rlf_ev_lab_unit[]">
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
                                <button type="button" class="btn btn-sm btn-outline-secondary rlf-add-evidence-btn">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="rlf_add_evidence_set_btn">
                            <i class="fas fa-plus me-1"></i> เพิ่มชุดวัตถุพยาน
                        </button>
                    </fieldset>

                    <!-- ==================== 7. วิธีการดำเนินการ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">7. วิธีการดำเนินการ</legend>

                        <div id="rlf_method_container">
                            <div class="row g-2 mb-2 align-items-center rlf-method-row">
                                <div class="col-md-4">
                                    <select class="form-select form-select-sm" name="rlf_method_name[]">
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
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control form-control-sm" name="rlf_method_detail[]" placeholder="รายละเอียด...">
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-sm btn-outline-danger rlf-remove-method" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="rlf_add_method_btn">
                            <i class="fas fa-plus me-1"></i> เพิ่มวิธีการ
                        </button>
                    </fieldset>

                    <!-- ==================== 8. การดำเนินการ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">8. การดำเนินการ</legend>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">วัตถุพยานแผ่นเก็บรอยลายนิ้วมือแฝง/ภาพถ่ายรอยลายนิ้วมือแฝง</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_action_evidence_dest" value="กนฝ." id="rlf_action_gnf">
                                        <label class="form-check-label" for="rlf_action_gnf">กนฝ.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_action_evidence_dest" value="อื่นๆ" id="rlf_action_evidence_other_chk">
                                        <label class="form-check-label" for="rlf_action_evidence_other_chk">อื่นๆ</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="rlf_action_evidence_other_text" id="rlf_action_evidence_other_text" placeholder="ระบุ..." style="width:200px;">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ของกลาง</label>
                                <div class="d-flex flex-wrap gap-3 align-items-center mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_action_exhibit" value="ส่งคืนพนักงานสอบสวน" id="rlf_action_return_investigator">
                                        <label class="form-check-label" for="rlf_action_return_investigator">ส่งคืนพนักงานสอบสวน</label>
                                    </div>
                                    <div>
                                        <span class="me-1">สภ.</span>
                                        <select class="form-select form-select-sm d-inline-block" name="rlf_action_return_station" id="rlf_action_return_station" style="width:200px;">
                                            <?= $policeStationOptionsRLF ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ส่งต่อกลุ่มงาน</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_action_forward_dept[]" value="กชว." id="rlf_fwd_gchw">
                                        <label class="form-check-label" for="rlf_fwd_gchw">กชว.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_action_forward_dept[]" value="กอป." id="rlf_fwd_gop">
                                        <label class="form-check-label" for="rlf_fwd_gop">กอป.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_action_forward_dept[]" value="กอส." id="rlf_fwd_gos">
                                        <label class="form-check-label" for="rlf_fwd_gos">กอส.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_action_forward_dept[]" value="กคม." id="rlf_fwd_gkm">
                                        <label class="form-check-label" for="rlf_fwd_gkm">กคม.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_action_forward_dept[]" value="กคพ." id="rlf_fwd_gkp">
                                        <label class="form-check-label" for="rlf_fwd_gkp">กคพ.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rlf_action_forward_dept[]" value="อื่นๆ" id="rlf_fwd_other">
                                        <label class="form-check-label" for="rlf_fwd_other">อื่นๆ</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="rlf_action_forward_other_text" id="rlf_action_forward_other_text" placeholder="ระบุ..." style="width:150px;">
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ลงชื่อผู้รายงาน ==================== -->
                    <fieldset class="mb-2 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">ลงชื่อผู้รายงาน</legend>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ชื่อผู้รายงาน</label>
                                <input type="text" class="form-control form-control-sm" name="rlf_signer_name" id="rlf_signer_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ตำแหน่ง</label>
                                <input type="text" class="form-control form-control-sm" name="rlf_signer_position" id="rlf_signer_position">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">วันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rlf_sign_date" id="rlf_sign_date">
                            </div>
                        </div>
                    </fieldset>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-success" id="btn_save_report_fingerprint">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
                </button>
            </div>

        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const rlfInspectorOptionsHTML = `<?= $inspectorOptionsRLF ?>`;
    const rlfPoliceStationOptionsHTML = `<?= $policeStationOptionsRLF ?>`;

    // ===== 4. ผู้ตรวจพิสูจน์ - Dynamic Rows =====
    let rlfInspectorIdx = 1;
    document.getElementById('rlf_add_inspector_btn').addEventListener('click', function() {
        rlfInspectorIdx++;
        const container = document.getElementById('rlf_inspector_container');
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-center rp-inspector-row';
        row.innerHTML = `
            <div class="col-auto"><span class="fw-semibold rp-inspector-num" style="min-width:32px; display:inline-block;">4.${rlfInspectorIdx}</span></div>
            <div class="col"><select class="form-select form-select-sm rp-inspector-select" name="rlf_inspector_name[]">${rlfInspectorOptionsHTML}</select></div>
            <div class="col-auto"><span class="fw-semibold">ตำแหน่ง</span></div>
            <div class="col"><input type="text" class="form-control form-control-sm rp-inspector-position" name="rlf_inspector_position[]" placeholder="ตำแหน่ง" readonly></div>
            <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger rp-remove-inspector" title="ลบ"><i class="fas fa-trash-alt"></i></button></div>`;
        container.appendChild(row);
    });

    document.getElementById('rlf_inspector_container').addEventListener('click', function(e) {
        const btn = e.target.closest('.rp-remove-inspector');
        if (!btn) return;
        const rows = this.querySelectorAll('.rp-inspector-row');
        if (rows.length <= 1) return;
        btn.closest('.rp-inspector-row').remove();
        rlfReindexInspectors();
    });

    document.getElementById('rlf_inspector_container').addEventListener('change', function(e) {
        const sel = e.target.closest('.rp-inspector-select');
        if (!sel) return;
        const opt = sel.selectedOptions[0];
        const posInput = sel.closest('.rp-inspector-row').querySelector('.rp-inspector-position');
        if (posInput && opt) posInput.value = opt.dataset.position || '';
    });

    function rlfReindexInspectors() {
        const rows = document.querySelectorAll('#rlf_inspector_container .rp-inspector-row');
        rows.forEach((row, i) => { row.querySelector('.rp-inspector-num').textContent = '4.' + (i + 1); });
        rlfInspectorIdx = rows.length;
    }

    // ===== ผู้ส่งของกลาง - auto-fill position =====
    const senderSel = document.getElementById('rlf_evidence_sender');
    if (senderSel) {
        senderSel.addEventListener('change', function() {
            const opt = this.selectedOptions[0];
            const posInput = document.getElementById('rlf_evidence_sender_position');
            if (posInput && opt) posInput.value = opt.dataset.position || '';
        });
    }

    // ===== 6. ลักษณะวัตถุพยาน - Dynamic =====
    let rlfSetIdx = 1;
    let rlfEvidenceIdx = 1;

    const labUnitOptions = `
        <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
        <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
        <option value="fingerprint" selected>กลุ่มงานตรวจลายนิ้วมือแฝง</option>
        <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
        <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
        <option value="document">กลุ่มงานตรวจเอกสาร</option>`;

    function buildEvidenceCardHTML(num) {
        return `<div class="rlf-evidence-card border rounded p-3 mb-2 bg-light">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-primary rlf-ev-badge">รายการที่ ${num}</span>
                <button type="button" class="btn btn-sm btn-outline-danger rlf-remove-evidence" title="ลบ"><i class="fas fa-trash-alt"></i></button>
            </div>
            <div class="row g-2">
                <div class="col-md-12"><label class="form-label">เป็น</label><input type="text" class="form-control form-control-sm" name="rlf_ev_description[]"></div>
                <div class="col-md-3"><label class="form-label">ก/ผคก. (cm)</label><input type="text" class="form-control form-control-sm" name="rlf_ev_width[]"></div>
                <div class="col-md-3"><label class="form-label">ย. (cm)</label><input type="text" class="form-control form-control-sm" name="rlf_ev_length[]"></div>
                <div class="col-md-3"><label class="form-label">ส. (cm)</label><input type="text" class="form-control form-control-sm" name="rlf_ev_height[]"></div>
                <div class="col-md-3"><label class="form-label">จำนวน</label><input type="text" class="form-control form-control-sm" name="rlf_ev_quantity[]"></div>
                <div class="col-md-6"><label class="form-label">ป้ายหมายเลข</label><input type="text" class="form-control form-control-sm" name="rlf_ev_label_no[]"></div>
                <div class="col-md-6"><label class="form-label">การตรวจพิสูจน์</label><select class="form-select form-select-sm" name="rlf_ev_lab_unit[]">${labUnitOptions}</select></div>
            </div>
        </div>`;
    }

    document.getElementById('rlf_evidence_sets_container').addEventListener('click', function(e) {
        // Add evidence item
        const addBtn = e.target.closest('.rlf-add-evidence-btn');
        if (addBtn) {
            const setDiv = addBtn.closest('.rlf-evidence-set');
            const items = setDiv.querySelectorAll('.rlf-evidence-card');
            const container = setDiv.querySelector('.rlf-evidence-items');
            const tmp = document.createElement('div');
            tmp.innerHTML = buildEvidenceCardHTML(items.length + 1);
            container.appendChild(tmp.firstElementChild);
            return;
        }
        // Remove evidence item
        const removeBtn = e.target.closest('.rlf-remove-evidence');
        if (removeBtn) {
            const setDiv = removeBtn.closest('.rlf-evidence-set');
            const items = setDiv.querySelectorAll('.rlf-evidence-card');
            if (items.length <= 1) return;
            removeBtn.closest('.rlf-evidence-card').remove();
            setDiv.querySelectorAll('.rlf-ev-badge').forEach((b, i) => { b.textContent = 'รายการที่ ' + (i + 1); });
        }
    });

    document.getElementById('rlf_add_evidence_set_btn').addEventListener('click', function() {
        rlfSetIdx++;
        const container = document.getElementById('rlf_evidence_sets_container');
        const setDiv = document.createElement('div');
        setDiv.className = 'rlf-evidence-set border rounded p-3 mb-3';
        setDiv.dataset.setIndex = rlfSetIdx;
        setDiv.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-primary mb-0">ชุดที่ ${rlfSetIdx}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger rlf-remove-set" title="ลบชุด"><i class="fas fa-trash-alt me-1"></i>ลบชุด</button>
            </div>
            <div class="row g-3 mb-3"><div class="col-md-4"><label class="form-label fw-semibold">จำนวนวัตถุพยานทั้งสิ้น</label><div class="input-group input-group-sm"><input type="number" class="form-control" name="rlf_evidence_total[]" min="0" placeholder="0"><span class="input-group-text">รายการ</span></div></div></div>
            <div class="rlf-evidence-items">${buildEvidenceCardHTML(1)}</div>
            <button type="button" class="btn btn-sm btn-outline-secondary rlf-add-evidence-btn"><i class="fas fa-plus me-1"></i> เพิ่มรายการ</button>`;
        container.appendChild(setDiv);
    });

    document.getElementById('rlf_evidence_sets_container').addEventListener('click', function(e) {
        const removeSetBtn = e.target.closest('.rlf-remove-set');
        if (removeSetBtn) {
            const sets = this.querySelectorAll('.rlf-evidence-set');
            if (sets.length <= 1) return;
            removeSetBtn.closest('.rlf-evidence-set').remove();
        }
    });

    // ===== 7. วิธีการ - Dynamic =====
    const methodOptions = `
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

    document.getElementById('rlf_add_method_btn').addEventListener('click', function() {
        const container = document.getElementById('rlf_method_container');
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-center rlf-method-row';
        row.innerHTML = `
            <div class="col-md-4"><select class="form-select form-select-sm" name="rlf_method_name[]">${methodOptions}</select></div>
            <div class="col"><input type="text" class="form-control form-control-sm" name="rlf_method_detail[]" placeholder="รายละเอียด..."></div>
            <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger rlf-remove-method" title="ลบ"><i class="fas fa-trash-alt"></i></button></div>`;
        container.appendChild(row);
    });

    document.getElementById('rlf_method_container').addEventListener('click', function(e) {
        const btn = e.target.closest('.rlf-remove-method');
        if (!btn) return;
        if (this.querySelectorAll('.rlf-method-row').length <= 1) return;
        btn.closest('.rlf-method-row').remove();
    });

})();
</script>
