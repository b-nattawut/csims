<?php
/**
 * Modal: ร่างรายงานคดีทรัพย์
 * รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์ (F-CS-05)
 */
?>

<div class="modal fade" id="modalReportProperty" aria-labelledby="modalReportPropertyLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportPropertyLabel">
                    รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body bg-light p-4">
                <form id="formReportProperty" novalidate>
                    <input type="hidden" id="rp_incident_id" name="incident_id">

                    <!-- Switch to PDF form -->
                                        <!-- Header Info -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="rp_editInfo" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="rp_editCount" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto me-3">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="rp_doc_no_display"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="rp_report_no_display"></span>
                            </div>
                        </div>
                    
                        <div class="bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2 flex-shrink-0">
                        <div class="form-check form-switch mb-0" style="padding-left: 0;">
                        <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchPropertyReportToPdf"
                        style="width: 3rem; height: 1.5rem; cursor: pointer;"
                        onchange="if(this.checked){ this.checked=false; switchPropertyReportToPdfForm(); }">
                        </div>
                        <label class="fw-bold text-danger mb-0" for="switchPropertyReportToPdf" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
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
                                <input type="date" class="form-control form-control-sm" name="rp_receive_date" id="rp_receive_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">เวลา</label>
                                <input type="time" class="form-control form-control-sm" name="rp_receive_time" id="rp_receive_time">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">ตามประจำวันข้อที่</label>
                                <input type="text" class="form-control form-control-sm" name="rp_daily_ref" id="rp_daily_ref">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">หน่วยงาน</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rp_agency_type" value="กสก.พฐก." id="rp_agency_ksk">
                                        <label class="form-check-label" for="rp_agency_ksk">กสก.พฐก.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rp_agency_type" value="กลก.ศพฐ." id="rp_agency_klk">
                                        <label class="form-check-label" for="rp_agency_klk">กลก.ศพฐ.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rp_agency_type" value="พฐ.จว." id="rp_agency_ptjv">
                                        <label class="form-check-label" for="rp_agency_ptjv">พฐ.จว.</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="rp_agency_name" id="rp_agency_name" placeholder="ระบุชื่อหน่วยงาน" style="width:200px;">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ได้รับแจ้งทาง</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rp_notify_method[]" value="หนังสือ" id="rp_notify_letter">
                                        <label class="form-check-label" for="rp_notify_letter">ตามหนังสือ</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rp_notify_method[]" value="โทรศัพท์" id="rp_notify_phone">
                                        <label class="form-check-label" for="rp_notify_phone">ทางโทรศัพท์</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rp_notify_method[]" value="วิทยุสื่อสาร" id="rp_notify_radio">
                                        <label class="form-check-label" for="rp_notify_radio">วิทยุสื่อสาร</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">จาก สน./สภ.</label>
                                <input type="text" class="form-control form-control-sm" name="rp_from_station" id="rp_from_station">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">โดยมี พนักงานสอบสวนเจ้าของคดี</label>
                                <input type="text" class="form-control form-control-sm" name="rp_investigator" id="rp_investigator">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ๒. สถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">2. สถานที่เกิดเหตุ</legend>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">สถานที่เกิดเหตุ</label>
                                <textarea class="form-control form-control-sm" name="rp_crime_location" id="rp_crime_location" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">เจ้าของบ้าน/ผู้เสียหาย/อื่นๆ</label>
                                <input type="text" class="form-control form-control-sm" name="rp_victim_name" id="rp_victim_name">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">อายุประมาณ (ปี)</label>
                                <input type="text" class="form-control form-control-sm" name="rp_victim_age" id="rp_victim_age">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ๓. วันเวลาที่ทราบเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">3. วันเวลาที่ทราบเหตุ/เกิดเหตุ</legend>
                        <div class="row g-3">
                            <div class="col-12"><span class="fw-semibold text-dark">ผู้เสียหายทราบเหตุ/เกิดเหตุ</span></div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rp_victim_know_date" id="rp_victim_know_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rp_victim_know_time" id="rp_victim_know_time">
                            </div>
                            <div class="col-12 mt-3"><span class="fw-semibold text-dark">พนักงานสอบสวนทราบเหตุ</span></div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rp_officer_know_date" id="rp_officer_know_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rp_officer_know_time" id="rp_officer_know_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ๔. วันเวลาตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">4. วันเวลาตรวจสถานที่เกิดเหตุ</legend>
                        <div class="row g-3">
                            <div class="col-12"><span class="fw-semibold text-dark">ตรวจสถานที่เกิดเหตุ</span></div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rp_inspect_date" id="rp_inspect_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rp_inspect_time" id="rp_inspect_time">
                            </div>
                            <div class="col-12 mt-3"><span class="fw-semibold text-dark">ตรวจสถานที่เกิดเหตุเพิ่มเติม</span></div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rp_inspect_add_date" id="rp_inspect_add_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rp_inspect_add_time" id="rp_inspect_add_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ๕. ผู้ตรวจสถานที่เกิดเหตุ (Dynamic + Dropdown) ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">5. ผู้ตรวจสถานที่เกิดเหตุ</legend>
                        <div id="rp_inspector_container">
                            <div class="row g-2 mb-2 align-items-center rp-inspector-row">
                                <div class="col-auto">
                                    <span class="fw-semibold rp-inspector-num" style="min-width:32px; display:inline-block;">5.1</span>
                                </div>
                                <div class="col">
                                    <select class="form-select form-select-sm rp-inspector-select" name="rp_inspector_name[]">
                                        <option value="">-- เลือกผู้ตรวจ --</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <span class="fw-semibold">ตำแหน่ง</span>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control form-control-sm rp-inspector-position" name="rp_inspector_position[]" placeholder="ตำแหน่ง" readonly>
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-sm btn-outline-danger rp-remove-inspector" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="rp_add_inspector">
                            <i class="fas fa-plus me-1"></i> เพิ่มผู้ตรวจ
                        </button>
                    </fieldset>

                    <!-- ==================== ๖. ลักษณะของสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">6. ลักษณะของสถานที่เกิดเหตุ</legend>

                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">6.1</span> ลักษณะภายนอก</h6>
                        <div class="row g-3 mb-3 ps-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ประเภทอาคาร</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rp_building_type[]" value="บ้าน" id="rp_bld_house">
                                        <label class="form-check-label" for="rp_bld_house">บ้าน</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rp_building_type[]" value="ตึกแถว" id="rp_bld_shophouse">
                                        <label class="form-check-label" for="rp_bld_shophouse">ตึกแถว</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rp_building_type[]" value="อาคาร" id="rp_bld_building">
                                        <label class="form-check-label" for="rp_bld_building">อาคาร</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rp_building_type[]" value="อื่นๆ" id="rp_bld_other">
                                        <label class="form-check-label" for="rp_bld_other">อื่นๆ</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="rp_building_type_other" id="rp_building_type_other" placeholder="ระบุ" style="width:180px;">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">จำนวนชั้น</label>
                                <input type="text" class="form-control form-control-sm" name="rp_floor_count" id="rp_floor_count">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">จำนวนหลัง/คูหา</label>
                                <input type="text" class="form-control form-control-sm" name="rp_unit_count" id="rp_unit_count">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">ชั้นลอย</label>
                                <div class="d-flex gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rp_mezzanine" value="มี" id="rp_mezzanine_yes">
                                        <label class="form-check-label" for="rp_mezzanine_yes">มี</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rp_mezzanine" value="ไม่มี" id="rp_mezzanine_no">
                                        <label class="form-check-label" for="rp_mezzanine_no">ไม่มี</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">ดาดฟ้า</label>
                                <div class="d-flex gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rp_rooftop" value="มี" id="rp_rooftop_yes">
                                        <label class="form-check-label" for="rp_rooftop_yes">มี</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rp_rooftop" value="ไม่มี" id="rp_rooftop_no">
                                        <label class="form-check-label" for="rp_rooftop_no">ไม่มี</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">ปลูกอยู่ภายในบริเวณ</label>
                                <div class="d-flex gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rp_in_compound" value="มี" id="rp_compound_yes">
                                        <label class="form-check-label" for="rp_compound_yes">มี</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rp_in_compound" value="ไม่มี" id="rp_compound_no">
                                        <label class="form-check-label" for="rp_compound_no">ไม่มี</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">รั้วล้อมรอบ</label>
                                <div class="d-flex gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rp_fence" value="มี" id="rp_fence_yes">
                                        <label class="form-check-label" for="rp_fence_yes">มี</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rp_fence" value="ไม่มี" id="rp_fence_no">
                                        <label class="form-check-label" for="rp_fence_no">ไม่มี</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12"><label class="form-label fw-semibold">เมื่อหันหน้าเข้า</label></div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านหน้าติด</label>
                                <input type="text" class="form-control form-control-sm" name="rp_ext_front" id="rp_ext_front">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านซ้ายติด</label>
                                <input type="text" class="form-control form-control-sm" name="rp_ext_left" id="rp_ext_left">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านขวาติด</label>
                                <input type="text" class="form-control form-control-sm" name="rp_ext_right" id="rp_ext_right">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านหลังติด</label>
                                <input type="text" class="form-control form-control-sm" name="rp_ext_back" id="rp_ext_back">
                            </div>
                        </div>

                        <hr class="my-3">
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">6.2</span> ลักษณะภายใน</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rp_interior_detail" id="rp_interior_detail" rows="4" placeholder="ระบุลักษณะภายใน..."></textarea>
                        </div>

                        <hr class="my-3">
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">6.3</span> บริเวณที่เกิดเหตุ</h6>
                        <div class="ps-3 mb-3">
                            <label class="form-label fw-semibold">เกิดเหตุที่</label>
                            <textarea class="form-control form-control-sm" name="rp_incident_area" id="rp_incident_area" rows="2"></textarea>
                        </div>
                    </fieldset>

                    <!-- ==================== ๗. ผลการตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">7. ผลการตรวจสถานที่เกิดเหตุ</legend>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">พฤติการณ์ของคดี</label>
                                <textarea class="form-control form-control-sm" name="rp_case_behavior" id="rp_case_behavior" rows="4" placeholder="ระบุพฤติการณ์ของคดี..."></textarea>
                            </div>
                        </div>

                        <hr class="my-3">
                        <label class="form-label fw-semibold">จากการตรวจสถานที่เกิดเหตุ</label>

                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.1</span> สภาพของสถานที่เกิดเหตุเมื่อไปถึง</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rp_scene_condition" id="rp_scene_condition" rows="4" placeholder="ระบุสภาพสถานที่เกิดเหตุเมื่อไปถึง..."></textarea>
                        </div>

                        <hr class="my-3">
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.2</span> ทางเข้าของคนร้าย</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rp_criminal_entry" id="rp_criminal_entry" rows="3" placeholder="ระบุทางเข้าของคนร้าย..."></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- ==================== 7.3 ห้อง (Dynamic Block) ==================== -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.3</span> คนร้ายได้เข้ามาในห้องต่าง ๆ ดังนี้</h6>
                        <div id="rp_room_container">
                            <div class="ps-3 mb-4 border-start border-3 border-primary ms-2 rp-room-block">
                                <div class="d-flex align-items-center mb-3 ps-2">
                                    <h6 class="fw-semibold text-primary mb-0"><span class="rp-room-num">7.3.1</span> ที่ห้อง</h6>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2 rp-remove-room" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                </div>
                                <div class="ps-3">
                                    <div class="mb-3">
                                        <input type="text" class="form-control form-control-sm" name="rp_room_name[]" placeholder="ชื่อห้อง">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label"><span class="rp-room-sub-num">7.3.1</span>.1 ทางเข้าของคนร้าย พบ/ไม่พบรอยจัดที่</label>
                                        <textarea class="form-control form-control-sm" name="rp_room_entry[]" rows="2"></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label"><span class="rp-room-sub-num">7.3.1</span>.2 พบรอยจัดที่</label>
                                        <textarea class="form-control form-control-sm" name="rp_room_marks[]" rows="2"></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label"><span class="rp-room-sub-num">7.3.1</span>.3 พบร่องรอยรื้อค้นที่</label>
                                        <textarea class="form-control form-control-sm" name="rp_room_search_marks[]" rows="2"></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label"><span class="rp-room-sub-num">7.3.1</span>.4 วัตถุพยานอื่นๆที่ตรวจพบ</label>
                                        <textarea class="form-control form-control-sm" name="rp_room_other_evidence[]" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="rp_add_room">
                            <i class="fas fa-plus me-1"></i> เพิ่มห้อง
                        </button>

                        <hr class="my-3">

                        <!-- ==================== 7.4 ทรัพย์สินที่ถูกโจรกรรม (Dynamic Block) ==================== -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.4</span> ในเบื้องต้น แจ้งว่า คนร้ายโจรกรรมทรัพย์สินไปดังนี้</h6>
                        <div id="rp_stolen_container">
                            <div class="ps-3 mb-4 border-start border-3 border-secondary ms-2 rp-stolen-block">
                                <div class="d-flex align-items-center mb-3 ps-2">
                                    <h6 class="fw-semibold text-dark mb-0"><span class="rp-stolen-block-num">7.4.1</span> ทรัพย์สินของผู้เสียหาย</h6>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2 rp-remove-stolen-block" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                </div>
                                <div class="ps-3">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">คำนำหน้า</label>
                                            <select class="form-select form-select-sm" name="rp_stolen_victim_prefix[]">
                                                <option value="">-- เลือก --</option>
                                                <option value="นาย">นาย</option>
                                                <option value="นาง">นาง</option>
                                                <option value="นางสาว">นางสาว</option>
                                                <option value="อื่นๆ">อื่นๆ</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold">ชื่อ-นามสกุล</label>
                                            <input type="text" class="form-control form-control-sm" name="rp_stolen_victim_name[]">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">สถานะ</label>
                                            <select class="form-select form-select-sm" name="rp_stolen_victim_role[]">
                                                <option value="">-- เลือก --</option>
                                                <option value="เจ้าของบ้าน">เจ้าของบ้าน</option>
                                                <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                                                <option value="อื่นๆ">อื่นๆ</option>
                                            </select>
                                        </div>
                                    </div>
                                    <label class="form-label fw-semibold">รายการทรัพย์สิน</label>
                                    <div class="rp-stolen-items mb-2">
                                        <div class="row g-2 mb-2 rp-stolen-item-row">
                                            <div class="col-auto d-flex align-items-center"><span class="rp-stolen-item-num text-muted" style="min-width:28px;">1.</span></div>
                                            <div class="col"><input type="text" class="form-control form-control-sm" name="rp_stolen_item[]" placeholder="รายการทรัพย์สิน"></div>
                                            <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger rp-remove-stolen-item" title="ลบ"><i class="fas fa-times"></i></button></div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rp-add-stolen-item">
                                        <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="rp_add_stolen_block">
                            <i class="fas fa-plus me-1"></i> เพิ่มผู้เสียหาย / ทรัพย์สิน
                        </button>

                        <hr class="my-3">

                        <!-- ==================== 7.5 วัตถุพยาน (Dynamic Block) ==================== -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.5</span> วัตถุพยานที่ตรวจเก็บในสถานที่เกิดเหตุ</h6>
                        <div id="rp_evidence_container">
                            <div class="ps-3 mb-4 border-start border-3 border-secondary ms-2 rp-evidence-block">
                                <div class="d-flex align-items-center gap-2 mb-3 ps-2">
                                    <h6 class="fw-semibold text-dark mb-0">
                                        <span class="rp-evidence-block-num">7.5.1</span> ตรวจเก็บวัตถุพยาน รอยลายนิ้วมือแฝง/ฝ่ามือแฝง/ฝ่าเท้าเเฝง
                                    </h6>
                                </div>
                                <div class="ps-3">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">จำนวน (แผ่น/ชิ้น)</label>
                                            <input type="text" class="form-control form-control-sm" name="rp_evidence_count[]">
                                        </div>
                                    </div>
                                    <label class="form-label fw-semibold">ที่ (รายละเอียดสถานที่ตรวจพบ)</label>
                                    <div class="rp-evidence-locs mb-2">
                                        <div class="row g-2 mb-2 rp-evidence-loc-row">
                                            <div class="col-auto d-flex align-items-center"><span class="rp-evidence-loc-num text-muted" style="min-width:28px;">1.</span></div>
                                            <div class="col"><input type="text" class="form-control form-control-sm" name="rp_evidence_loc[]"></div>
                                            <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger rp-remove-evidence-loc" title="ลบ"><i class="fas fa-times"></i></button></div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rp-add-evidence-loc">
                                        <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                    </button>
                                    <hr class="my-3">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label fw-semibold">และได้ให้ลงลายมือชื่อไว้เป็นหลักฐาน</label>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">คำนำหน้า</label>
                                            <select class="form-select form-select-sm" name="rp_evidence_signer_prefix[]">
                                                <option value="">-- เลือก --</option>
                                                <option value="นาย">นาย</option>
                                                <option value="นาง">นาง</option>
                                                <option value="นางสาว">นางสาว</option>
                                                <option value="อื่นๆ">อื่นๆ</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label">ชื่อ-นามสกุล</label>
                                            <input type="text" class="form-control form-control-sm" name="rp_evidence_signer_name[]">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">สถานะ</label>
                                            <select class="form-select form-select-sm" name="rp_evidence_signer_role[]">
                                                <option value="">-- เลือก --</option>
                                                <option value="เจ้าของบ้าน">เจ้าของบ้าน</option>
                                                <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                                                <option value="อื่นๆ">อื่นๆ</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="rp_add_evidence_block">
                            <i class="fas fa-plus me-1"></i> เพิ่มวัตถุพยาน
                        </button>

                        <hr class="my-3">

                        <!-- 7.6 การดำเนินการเกี่ยวกับวัตถุพยาน -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.6</span> การดำเนินการเกี่ยวกับวัตถุพยาน</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rp_evidence_action" id="rp_evidence_action" rows="4" placeholder="ระบุการดำเนินการเกี่ยวกับวัตถุพยาน..."></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- ==================== 7.7 การส่งมอบสถานที่เกิดเหตุ ==================== -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.7</span> การส่งมอบสถานที่เกิดเหตุ</h6>
                        <div class="row g-3 ps-3 mb-3">
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">เจ้าหน้าที่</label>
                                <select class="form-select form-select-sm" name="rp_handover_agency" id="rp_handover_agency">
                                    <option value="">-- เลือก --</option>
                                    <option value="กสก.พฐก.">กสก.พฐก.</option>
                                    <option value="กลก.ศพฐ.">กลก.ศพฐ.</option>
                                    <option value="พฐ.จว.">พฐ.จว.</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-semibold">ระบุรายละเอียด</label>
                                <input type="text" class="form-control form-control-sm" name="rp_handover_detail" id="rp_handover_detail">
                            </div>
                        </div>
                        <div class="row g-3 ps-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ตรวจสถานที่เกิดเหตุเสร็จสิ้น พร้อมทั้งส่งมอบสถานที่เกิดเหตุคืนให้กับ</label>
                                <input type="text" class="form-control form-control-sm" name="rp_handover_to" id="rp_handover_to">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rp_handover_date" id="rp_handover_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rp_handover_time" id="rp_handover_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ลงชื่อ ==================== -->
                    <fieldset class="mb-2 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">ลงชื่อผู้รายงาน</legend>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ชื่อผู้รายงาน</label>
                                <input type="text" class="form-control form-control-sm" name="rp_signer_name" id="rp_signer_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ตำแหน่ง</label>
                                <input type="text" class="form-control form-control-sm" name="rp_signer_position" id="rp_signer_position">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">วันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rp_sign_date" id="rp_sign_date">
                            </div>
                        </div>
                    </fieldset>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-success" id="btn_save_report_property">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
                </button>
            </div>

        </div>
    </div>
</div>
