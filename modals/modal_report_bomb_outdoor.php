<?php
/**
 * Modal: ร่างรายงานคดีระเบิด (นอกอาคาร)
 * รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด (นอกอาคาร)
 */
?>

<div class="modal fade" id="modalReportBombOutdoor" aria-labelledby="modalReportBombOutdoorLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportBombOutdoorLabel">
                    รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด (นอกอาคาร)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body bg-light p-4">
                <form id="formReportBombOutdoor" novalidate>
                    <input type="hidden" id="rbo_incident_id" name="incident_id">

                                        <!-- Header Info -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="rbo_editInfo" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="rbo_editCount" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto me-3">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="rbo_doc_no_display"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="rbo_report_no_display"></span>
                            </div>
                        </div>
                    
                        <div class="bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2 flex-shrink-0">
                        <div class="form-check form-switch mb-0" style="padding-left: 0;">
                        <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchBombOutdoorReportToPdf"
                        style="width: 3rem; height: 1.5rem; cursor: pointer;"
                        onchange="if(this.checked){ this.checked=false; switchBombOutdoorReportToPdfForm(); }">
                        </div>
                        <label class="fw-bold text-danger mb-0" for="switchBombOutdoorReportToPdf" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                        <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                        </label>
                        </div>
                    </div>

                    <!-- ==================== ๑. การรับแจ้งเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">1. การรับแจ้งเหตุ</legend>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rbo_receive_date" id="rbo_receive_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">เวลา</label>
                                <input type="time" class="form-control form-control-sm" name="rbo_receive_time" id="rbo_receive_time">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">ตามประจำวันข้อที่</label>
                                <input type="text" class="form-control form-control-sm" name="rbo_daily_ref" id="rbo_daily_ref">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">หน่วยงาน</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rbo_agency_type" value="กสก.พฐก." id="rbo_agency_ksk">
                                        <label class="form-check-label" for="rbo_agency_ksk">กสก.พฐก.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rbo_agency_type" value="กสก.ศพฐ." id="rbo_agency_klk">
                                        <label class="form-check-label" for="rbo_agency_klk">กสก.ศพฐ.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rbo_agency_type" value="พฐ.จว." id="rbo_agency_ptjv">
                                        <label class="form-check-label" for="rbo_agency_ptjv">พฐ.จว.</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="rbo_agency_name" id="rbo_agency_name" placeholder="ระบุชื่อหน่วยงาน" style="width:200px;">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ได้รับแจ้งทาง</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rbo_notify_method[]" value="หนังสือ" id="rbo_notify_letter">
                                        <label class="form-check-label" for="rbo_notify_letter">ตามหนังสือ</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rbo_notify_method[]" value="โทรศัพท์" id="rbo_notify_phone">
                                        <label class="form-check-label" for="rbo_notify_phone">ทางโทรศัพท์</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rbo_notify_method[]" value="วิทยุสื่อสาร" id="rbo_notify_radio">
                                        <label class="form-check-label" for="rbo_notify_radio">วิทยุสื่อสาร</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">จาก สน./สภ.</label>
                                <input type="text" class="form-control form-control-sm" name="rbo_from_station" id="rbo_from_station">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">พนักงานสอบสวนเจ้าของคดี</label>
                                <input type="text" class="form-control form-control-sm" name="rbo_investigator" id="rbo_investigator">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ๒. สถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">2. สถานที่เกิดเหตุ</legend>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">สถานที่เกิดเหตุ</label>
                                <textarea class="form-control form-control-sm" name="rbo_crime_location" id="rbo_crime_location" rows="2"></textarea>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">ผู้เสียชีวิต/ผู้บาดเจ็บ/ผู้เสียหาย</label>
                                <input type="text" class="form-control form-control-sm" name="rbo_victim_name" id="rbo_victim_name">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">อายุประมาณ (ปี)</label>
                                <input type="text" class="form-control form-control-sm" name="rbo_victim_age" id="rbo_victim_age">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ๓. วันเวลาที่ทราบเหตุ/เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">3. วันเวลาที่ทราบเหตุ/เกิดเหตุ</legend>

                        <div class="row g-3">
                            <div class="col-12">
                                <span class="fw-semibold text-dark">ผู้เสียหายทราบเหตุ/เกิดเหตุ</span>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">วันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rbo_victim_know_date" id="rbo_victim_know_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rbo_victim_know_time" id="rbo_victim_know_time">
                            </div>

                            <div class="col-12 mt-3">
                                <span class="fw-semibold text-dark">พนักงานสอบสวนทราบเหตุ</span>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rbo_officer_know_date" id="rbo_officer_know_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rbo_officer_know_time" id="rbo_officer_know_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ๔. วันเวลาตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">4. วันเวลาตรวจสถานที่เกิดเหตุ</legend>

                        <div class="row g-3">
                            <div class="col-12">
                                <span class="fw-semibold text-dark">ตรวจสถานที่เกิดเหตุ</span>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rbo_inspect_date" id="rbo_inspect_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rbo_inspect_time" id="rbo_inspect_time">
                            </div>

                            <div class="col-12 mt-3">
                                <span class="fw-semibold text-dark">ตรวจสถานที่เกิดเหตุเพิ่มเติม</span>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rbo_inspect_add_date" id="rbo_inspect_add_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rbo_inspect_add_time" id="rbo_inspect_add_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ๕. ผู้ตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">5. ผู้ตรวจสถานที่เกิดเหตุ</legend>
                        <div id="rbo_inspector_container" class="rp-inspector-container-wrap">
                            <div class="row g-2 mb-2 align-items-center rp-inspector-row">
                                <div class="col-auto">
                                    <span class="fw-semibold rp-inspector-num" style="min-width:32px; display:inline-block;">5.1</span>
                                </div>
                                <div class="col">
                                    <select class="form-select form-select-sm rp-inspector-select" name="rbo_inspector_name[]">
                                        <option value="">-- เลือกผู้ตรวจ --</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <span class="fw-semibold">ตำแหน่ง</span>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control form-control-sm rp-inspector-position" name="rbo_inspector_position[]" placeholder="ตำแหน่ง" readonly>
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

                    <!-- ==================== ๖. ลักษณะของสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">6. ลักษณะของสถานที่เกิดเหตุ</legend>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ลักษณะของสถานที่เกิดเหตุ</label>
                                <textarea class="form-control form-control-sm" name="rbo_scene_description" id="rbo_scene_description" rows="5" placeholder="ระบุลักษณะของสถานที่เกิดเหตุ..."></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ๗. ผลการตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">7. ผลการตรวจสถานที่เกิดเหตุ</legend>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">พฤติการณ์ของคดีจากการสอบถามข้อมูลในเบื้องต้นจาก พงส. ได้ความว่า</label>
                                <textarea class="form-control form-control-sm" name="rbo_case_behavior" id="rbo_case_behavior" rows="4" placeholder="ระบุพฤติการณ์ของคดี..."></textarea>
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- 7.1 สภาพของสถานที่เกิดเหตุเมื่อไปถึง -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.1</span> สภาพของสถานที่เกิดเหตุเมื่อไปถึง</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rbo_scene_condition" id="rbo_scene_condition" rows="4" placeholder="ระบุสภาพสถานที่เกิดเหตุเมื่อไปถึง..."></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- 7.2 ลักษณะสภาพศพ -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.2</span> ลักษณะสภาพศพ</h6>

                        <div class="row g-3 ps-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">7.2.1 พบศพ/ไม่พบศพ</label>
                                <textarea class="form-control form-control-sm" name="rbo_body_found" id="rbo_body_found" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">7.2.2 ตำแหน่งที่พบศพ</label>
                                <textarea class="form-control form-control-sm" name="rbo_body_position" id="rbo_body_position" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">7.2.3 สภาพศพ</label>
                                <textarea class="form-control form-control-sm" name="rbo_body_condition" id="rbo_body_condition" rows="3"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">7.2.4 สภาพเครื่องแต่งกายและทรัพย์สิน</label>
                                <textarea class="form-control form-control-sm" name="rbo_body_clothing" id="rbo_body_clothing" rows="3"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">7.2.5 รอยบาดแผลที่ศพ</label>
                                <textarea class="form-control form-control-sm" name="rbo_body_wounds" id="rbo_body_wounds" rows="4"></textarea>
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- 7.3 สภาพความเสียหาย -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.3</span> สภาพความเสียหาย</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rbo_damage_detail" id="rbo_damage_detail" rows="4" placeholder="ระบุสภาพความเสียหาย..."></textarea>
                        </div>

                        <!-- 7.3.1 ตำแหน่งที่เกิดการระเบิด -->
                        <h6 class="fw-bold text-dark mb-3 ps-3"><span class="text-primary">7.3.1</span> ตำแหน่งที่เกิดการระเบิด</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rbo_explosion_position" id="rbo_explosion_position" rows="3" placeholder="ระบุตำแหน่งที่เกิดการระเบิด..."></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- 7.4 ร่องรอยและวัตถุพยาน -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.4</span> ร่องรอยและวัตถุพยานที่ตรวจพบในสถานที่เกิดเหตุ</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rbo_evidence_found" id="rbo_evidence_found" rows="4" placeholder="ระบุร่องรอยและวัตถุพยาน..."></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- 7.5 วัตถุพยานที่ตรวจเก็บ -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.5</span> วัตถุพยานที่ตรวจเก็บในสถานที่เกิดเหตุ</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rbo_evidence_collected" id="rbo_evidence_collected" rows="4" placeholder="ระบุวัตถุพยานที่ตรวจเก็บ..."></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- 7.6 การดำเนินการเกี่ยวกับวัตถุพยาน -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.6</span> การดำเนินการเกี่ยวกับวัตถุพยาน</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rbo_evidence_action" id="rbo_evidence_action" rows="4" placeholder="ระบุการดำเนินการเกี่ยวกับวัตถุพยาน..."></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- 7.7 การส่งมอบ -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.7</span> การส่งมอบสถานที่เกิดเหตุ</h6>
                        <div class="row g-3 ps-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">เจ้าหน้าที่ (กสก.พฐก./กสก.ศพฐ./พฐ.จว.)</label>
                                <input type="text" class="form-control form-control-sm" name="rbo_handover_officer" id="rbo_handover_officer">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ตรวจสถานที่เกิดเหตุเสร็จสิ้น พร้อมทั้งส่งมอบสถานที่เกิดเหตุคืนให้กับ</label>
                                <input type="text" class="form-control form-control-sm" name="rbo_handover_to" id="rbo_handover_to">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rbo_handover_date" id="rbo_handover_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rbo_handover_time" id="rbo_handover_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ลงชื่อ ==================== -->
                    <fieldset class="mb-2 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">ลงชื่อผู้รายงาน</legend>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ชื่อผู้รายงาน</label>
                                <input type="text" class="form-control form-control-sm" name="rbo_signer_name" id="rbo_signer_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ตำแหน่ง</label>
                                <input type="text" class="form-control form-control-sm" name="rbo_signer_position" id="rbo_signer_position">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">วันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rbo_sign_date" id="rbo_sign_date">
                            </div>
                        </div>
                    </fieldset>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-success" id="btn_save_report_bomb_outdoor">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
                </button>
            </div>

        </div>
    </div>
</div>
