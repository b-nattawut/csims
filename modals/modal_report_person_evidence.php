<?php
/**
 * Modal: ร่างรายงานตรวจเก็บวัตถุพยานบุคคล
 * รายงานการตรวจเก็บวัตถุพยานที่บุคคล
 * Prefix: pe_
 * Modal ID: modalReportPersonEvidence   Form ID: formReportPersonEvidence
 */
?>

<div class="modal fade" id="modalReportPersonEvidence" aria-labelledby="modalReportPersonEvidenceLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportPersonEvidenceLabel">
                    รายงานการตรวจเก็บวัตถุพยานที่บุคคล
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body bg-light p-4">
                <form id="formReportPersonEvidence" novalidate>
                    <input type="hidden" id="pe_incident_id" name="incident_id">

                    <!-- Switch to PDF form -->
                                        <!-- Header Info -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="pe_editInfo" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="pe_editCount" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto me-3">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="pe_doc_no_display"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="pe_report_no_display"></span>
                            </div>
                        </div>
                    
                        <div class="bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2 flex-shrink-0">
                        <div class="form-check form-switch mb-0" style="padding-left: 0;">
                        <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchPersonEvidenceToPdf"
                        style="width: 3rem; height: 1.5rem; cursor: pointer;"
                        onchange="if(this.checked){ this.checked=false; switchPersonEvidenceReportToPdfForm(); }">
                        </div>
                        <label class="fw-bold text-danger mb-0" for="switchPersonEvidenceToPdf" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                        <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                        </label>
                        </div>
                    </div>

                    <!-- ==================== 1. การรับแจ้งเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">1. การรับแจ้งเหตุ</legend>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="pe_receive_date" id="pe_receive_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">เวลา</label>
                                <input type="time" class="form-control form-control-sm" name="pe_receive_time" id="pe_receive_time">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">หน่วยงาน</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="pe_agency_type" value="กสก.พฐก." id="pe_agency_ksk">
                                        <label class="form-check-label" for="pe_agency_ksk">กสก.พฐก.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="pe_agency_type" value="กลก.ศพฐ." id="pe_agency_klk">
                                        <label class="form-check-label" for="pe_agency_klk">กลก.ศพฐ.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="pe_agency_type" value="พฐ.จว." id="pe_agency_ptjv">
                                        <label class="form-check-label" for="pe_agency_ptjv">พฐ.จว.</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="pe_agency_name" id="pe_agency_name" placeholder="ระบุชื่อหน่วยงาน" style="width:200px;">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ได้รับแจ้งทาง</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pe_notify_method[]" value="หนังสือ" id="pe_notify_letter">
                                        <label class="form-check-label" for="pe_notify_letter">ตามหนังสือ</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pe_notify_method[]" value="โทรศัพท์" id="pe_notify_phone">
                                        <label class="form-check-label" for="pe_notify_phone">ทางโทรศัพท์</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pe_notify_method[]" value="วิทยุสื่อสาร" id="pe_notify_radio">
                                        <label class="form-check-label" for="pe_notify_radio">วิทยุสื่อสาร</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pe_notify_method[]" value="อื่นๆ" id="pe_notify_other">
                                        <label class="form-check-label" for="pe_notify_other">อื่นๆ</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="pe_notify_method_other_text" id="pe_notify_method_other_text" placeholder="ระบุ..." style="width:150px;">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">จาก สน./สภ.</label>
                                <input type="text" class="form-control form-control-sm" name="pe_police_station" id="pe_police_station">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">เลขที่หนังสือ</label>
                                <input type="text" class="form-control form-control-sm" name="pe_document_no" id="pe_document_no">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">ลงวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="pe_document_date" id="pe_document_date">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">คดีอาญาที่</label>
                                <input type="text" class="form-control form-control-sm" name="pe_case_no" id="pe_case_no">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">สถานที่เกิดเหตุ</label>
                                <textarea class="form-control form-control-sm" name="pe_incident_location" id="pe_incident_location" rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">เมื่อวันที่เกิดเหตุ</label>
                                <input type="date" class="form-control form-control-sm" name="pe_incident_date" id="pe_incident_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">เวลาเกิดเหตุ</label>
                                <input type="time" class="form-control form-control-sm" name="pe_incident_time" id="pe_incident_time">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">พนักงานสอบสวนเจ้าของคดี</label>
                                <input type="text" class="form-control form-control-sm" name="pe_investigator_name" id="pe_investigator_name">
                            </div>
                        </div>

                        <!-- รายการบุคคล -->
                        <hr class="my-3">
                        <h6 class="fw-bold text-dark mb-3">รายการบุคคลที่ตรวจเก็บวัตถุพยาน</h6>
                        <div id="pe_persons_container">
                            <div class="row g-2 mb-2 align-items-center pe-person-row">
                                <div class="col-auto">
                                    <span class="fw-semibold pe-person-num" style="min-width:32px; display:inline-block;">1.1</span>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select form-select-sm" name="pe_person_prefix[]">
                                        <option value="">-- คำนำหน้า --</option>
                                        <option value="นาย">นาย</option>
                                        <option value="นาง">นาง</option>
                                        <option value="นางสาว">นางสาว</option>
                                        <option value="เด็กชาย">เด็กชาย</option>
                                        <option value="เด็กหญิง">เด็กหญิง</option>
                                    </select>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control form-control-sm" name="pe_person_name[]" placeholder="ชื่อ-นามสกุล">
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-sm btn-outline-danger pe-remove-person" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="pe_add_person_btn">
                            <i class="fas fa-plus me-1"></i> เพิ่มบุคคล
                        </button>
                    </fieldset>

                    <!-- ==================== 2. จุดประสงค์ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">2. จุดประสงค์ในการตรวจเก็บวัตถุพยาน</legend>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">รายละเอียดจุดประสงค์</label>
                                <textarea class="form-control form-control-sm" name="pe_purpose_detail" id="pe_purpose_detail" rows="3"></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 3. ผลการตรวจ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">3. ผลการตรวจ</legend>

                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">สถานที่ดำเนินการ</label>
                                <textarea class="form-control form-control-sm" name="pe_inspect_location" id="pe_inspect_location" rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="pe_inspect_date" id="pe_inspect_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">เวลา</label>
                                <input type="time" class="form-control form-control-sm" name="pe_inspect_time" id="pe_inspect_time">
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- 3.1 ข้อมูลส่วนบุคคล -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">3.1</span> ข้อมูลส่วนบุคคล</h6>
                        <div id="pe_person_info_container">
                            <div class="bg-light p-3 rounded mb-3 pe-person-info-row">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-primary pe-person-info-num">บุคคลที่ 1</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger pe-remove-person-info" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label">คำนำหน้า</label>
                                        <select class="form-select form-select-sm" name="pe_pi_prefix[]">
                                            <option value="">-- เลือก --</option>
                                            <option value="นาย">นาย</option>
                                            <option value="นาง">นาง</option>
                                            <option value="นางสาว">นางสาว</option>
                                            <option value="เด็กชาย">เด็กชาย</option>
                                            <option value="เด็กหญิง">เด็กหญิง</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">ชื่อ-นามสกุล</label>
                                        <input type="text" class="form-control form-control-sm" name="pe_pi_fullname[]">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">เลขบัตรประชาชน</label>
                                        <input type="text" class="form-control form-control-sm" name="pe_pi_id_card[]" maxlength="13">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">หนังสือเดินทาง</label>
                                        <input type="text" class="form-control form-control-sm" name="pe_pi_passport[]">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">ความสูง (ซม.)</label>
                                        <input type="text" class="form-control form-control-sm" name="pe_pi_height[]">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">อายุ (ปี)</label>
                                        <input type="text" class="form-control form-control-sm" name="pe_pi_age[]">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">สีผิว</label>
                                        <input type="text" class="form-control form-control-sm" name="pe_pi_skin[]">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">มือที่ถนัด</label>
                                        <select class="form-select form-select-sm" name="pe_pi_hand[]">
                                            <option value="">-- เลือก --</option>
                                            <option value="ขวา">ขวา</option>
                                            <option value="ซ้าย">ซ้าย</option>
                                            <option value="ทั้งสอง">ทั้งสอง</option>
                                        </select>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">ลักษณะพิเศษ</label>
                                        <textarea class="form-control form-control-sm" name="pe_pi_feature[]" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="pe_add_person_info_btn">
                            <i class="fas fa-plus me-1"></i> เพิ่มข้อมูลบุคคล
                        </button>

                        <hr class="my-3">

                        <!-- 3.2 รายละเอียดวัตถุพยาน -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">3.2</span> รายละเอียดวัตถุพยาน</h6>
                        <div id="pe_evidence_container">
                            <div class="row g-2 mb-2 align-items-end pe-evidence-row">
                                <div class="col-auto">
                                    <span class="fw-semibold pe-evidence-num" style="min-width:40px; display:inline-block;">3.2.1</span>
                                </div>
                                <div class="col">
                                    <label class="form-label">รายละเอียดตัวอย่าง</label>
                                    <input type="text" class="form-control form-control-sm" name="pe_ev_detail[]" placeholder="เช่น เนื้อเยื่อบุกระพุ้งแก้ม">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">จำนวน</label>
                                    <input type="text" class="form-control form-control-sm" name="pe_ev_qty[]">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">ส่งตรวจ</label>
                                    <select class="form-select form-select-sm" name="pe_ev_lab_unit[]">
                                        <option value="">-- เลือกกลุ่มงาน --</option>
                                        <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                                        <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                        <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                        <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                        <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                        <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                        <option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-sm btn-outline-danger pe-remove-evidence" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="pe_add_evidence_btn">
                            <i class="fas fa-plus me-1"></i> เพิ่มรายการวัตถุพยาน
                        </button>

                        <hr class="my-3">

                        <!-- 3.3 การดำเนินการเกี่ยวกับวัตถุพยาน -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">3.3</span> การดำเนินการเกี่ยวกับวัตถุพยาน</h6>
                        <div class="row g-3 ps-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ชื่อพยานในการตรวจเก็บ</label>
                                <input type="text" class="form-control form-control-sm" name="pe_witness_name" id="pe_witness_name">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">แบบฟอร์มที่ใช้</label>
                                <input type="text" class="form-control form-control-sm" name="pe_witness_form" id="pe_witness_form">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">รายละเอียดเพิ่มเติม</label>
                                <textarea class="form-control form-control-sm" name="pe_witness_detail" id="pe_witness_detail" rows="2"></textarea>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">วิธีจัดการวัตถุพยาน</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pe_handover_method_checks[]" value="นำส่ง" id="pe_handover_submit">
                                        <label class="form-check-label" for="pe_handover_submit">นำส่ง</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="pe_handover_method_checks[]" value="ส่งมอบ" id="pe_handover_transfer">
                                        <label class="form-check-label" for="pe_handover_transfer">ส่งมอบ</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ตามรายการวัตถุพยานที่</label>
                                <input type="text" class="form-control form-control-sm" name="pe_handover_item_ref" id="pe_handover_item_ref">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ส่งให้</label>
                                <input type="text" class="form-control form-control-sm" name="pe_handover_to" id="pe_handover_to">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">เพื่อ</label>
                                <textarea class="form-control form-control-sm" name="pe_handover_purpose" id="pe_handover_purpose" rows="2"></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ผู้ตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">ผู้ตรวจเก็บวัตถุพยาน</legend>
                        <div id="pe_inspector_container" class="rp-inspector-container-wrap">
                            <div class="row g-2 mb-2 align-items-center rp-inspector-row">
                                <div class="col-auto">
                                    <span class="fw-semibold rp-inspector-num" style="min-width:32px; display:inline-block;">1</span>
                                </div>
                                <div class="col">
                                    <select class="form-select form-select-sm rp-inspector-select" name="pe_inspector_name[]">
                                        <option value="">-- เลือกผู้ตรวจ --</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <span class="fw-semibold">ตำแหน่ง</span>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control form-control-sm rp-inspector-position" name="pe_inspector_position[]" placeholder="ตำแหน่ง" readonly>
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-sm btn-outline-danger rp-remove-inspector" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2 rp-add-inspector-btn">
                            <i class="fas fa-plus me-1"></i> เพิ่มผู้ตรวจ
                        </button>
                    </fieldset>

                    <!-- ==================== ลงชื่อผู้รายงาน ==================== -->
                    <fieldset class="mb-2 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">ลงชื่อผู้รายงาน</legend>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ชื่อผู้รายงาน</label>
                                <input type="text" class="form-control form-control-sm" name="pe_signer_name" id="pe_signer_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ตำแหน่ง</label>
                                <input type="text" class="form-control form-control-sm" name="pe_signer_position" id="pe_signer_position">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">วันที่</label>
                                <input type="date" class="form-control form-control-sm" name="pe_sign_date" id="pe_sign_date">
                            </div>
                        </div>
                    </fieldset>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-success" id="btn_save_report_person_evidence">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
                </button>
            </div>

        </div>
    </div>
</div>
