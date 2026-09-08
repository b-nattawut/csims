<?php
/**
 * Modal: ร่างรายงานคดีเพลิงไหม้
 * รายงานการตรวจสถานที่เกิดเหตุคดีเพลิงไหม้
 * Prefix: rf_
 */
?>

<div class="modal fade" id="modalReportFire" aria-labelledby="modalReportFireLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportFireLabel">
                    รายงานการตรวจสถานที่เกิดเหตุคดีเพลิงไหม้
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body bg-light p-4">
                <form id="formReportFire" novalidate>
                    <input type="hidden" id="rf_incident_id" name="incident_id">

                    <!-- Switch to PDF form -->
                                        <!-- Header Info -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="rf_editInfo" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="rf_editCount" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto me-3">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="rf_doc_no_display"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="rf_report_no_display"></span>
                            </div>
                        </div>
                    
                        <div class="bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2 flex-shrink-0">
                        <div class="form-check form-switch mb-0" style="padding-left: 0;">
                        <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchFireReportToPdf"
                        style="width: 3rem; height: 1.5rem; cursor: pointer;"
                        onchange="if(this.checked){ this.checked=false; switchFireReportToPdfForm(); }">
                        </div>
                        <label class="fw-bold text-danger mb-0" for="switchFireReportToPdf" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
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
                                <input type="date" class="form-control form-control-sm" name="rf_receive_date" id="rf_receive_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">เวลา</label>
                                <input type="time" class="form-control form-control-sm" name="rf_receive_time" id="rf_receive_time">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">ตามประจำวันข้อที่</label>
                                <input type="text" class="form-control form-control-sm" name="rf_daily_ref" id="rf_daily_ref">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">หน่วยงาน</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rf_agency_type" value="กสก.พฐก." id="rf_agency_ksk">
                                        <label class="form-check-label" for="rf_agency_ksk">กสก.พฐก.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rf_agency_type" value="กลก.ศพฐ." id="rf_agency_klk">
                                        <label class="form-check-label" for="rf_agency_klk">กลก.ศพฐ.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rf_agency_type" value="พฐ.จว." id="rf_agency_ptjv">
                                        <label class="form-check-label" for="rf_agency_ptjv">พฐ.จว.</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="rf_agency_name" id="rf_agency_name" placeholder="ระบุชื่อหน่วยงาน" style="width:200px;">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ได้รับแจ้งทาง</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rf_notify_method[]" value="หนังสือ" id="rf_notify_letter">
                                        <label class="form-check-label" for="rf_notify_letter">ตามหนังสือ</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rf_notify_method[]" value="โทรศัพท์" id="rf_notify_phone">
                                        <label class="form-check-label" for="rf_notify_phone">ทางโทรศัพท์</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rf_notify_method[]" value="วิทยุสื่อสาร" id="rf_notify_radio">
                                        <label class="form-check-label" for="rf_notify_radio">วิทยุสื่อสาร</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">จาก สน./สภ.</label>
                                <input type="text" class="form-control form-control-sm" name="rf_from_station" id="rf_from_station">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">พนักงานสอบสวนเจ้าของคดี</label>
                                <input type="text" class="form-control form-control-sm" name="rf_investigator" id="rf_investigator">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 2. สถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">2. สถานที่เกิดเหตุ</legend>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">สถานที่เกิดเหตุ</label>
                                <textarea class="form-control form-control-sm" name="rf_crime_location" id="rf_crime_location" rows="2"></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">3. วันเวลาที่ทราบเหตุ/เกิดเหตุ</legend>

                        <div class="row g-3">
                            <div class="col-12">
                                <span class="fw-semibold text-dark">ผู้เสียหายทราบเหตุ/เกิดเหตุ</span>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">วันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rf_victim_know_date" id="rf_victim_know_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rf_victim_know_time" id="rf_victim_know_time">
                            </div>

                            <div class="col-12 mt-3">
                                <span class="fw-semibold text-dark">พนักงานสอบสวนทราบเหตุ</span>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rf_officer_know_date" id="rf_officer_know_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rf_officer_know_time" id="rf_officer_know_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 4. วันเวลาตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">4. วันเวลาตรวจสถานที่เกิดเหตุ</legend>

                        <div class="row g-3">
                            <div class="col-12">
                                <span class="fw-semibold text-dark">ตรวจสถานที่เกิดเหตุ</span>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rf_inspect_date" id="rf_inspect_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rf_inspect_time" id="rf_inspect_time">
                            </div>

                            <div class="col-12 mt-3">
                                <span class="fw-semibold text-dark">ตรวจสถานที่เกิดเหตุเพิ่มเติม</span>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rf_inspect_add_date" id="rf_inspect_add_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rf_inspect_add_time" id="rf_inspect_add_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 5. ผู้ตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">5. ผู้ตรวจสถานที่เกิดเหตุ</legend>
                        <div id="rf_inspector_container" class="rp-inspector-container-wrap">
                            <div class="row g-2 mb-2 align-items-center rp-inspector-row">
                                <div class="col-auto">
                                    <span class="fw-semibold rp-inspector-num" style="min-width:32px; display:inline-block;">5.1</span>
                                </div>
                                <div class="col">
                                    <select class="form-select form-select-sm rp-inspector-select" name="rf_inspector_name[]">
                                        <option value="">-- เลือกผู้ตรวจ --</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <span class="fw-semibold">ตำแหน่ง</span>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control form-control-sm rp-inspector-position" name="rf_inspector_position[]" placeholder="ตำแหน่ง" readonly>
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

                    <!-- ==================== 6. ลักษณะของสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">6. ลักษณะของสถานที่เกิดเหตุ</legend>

                        <!-- 6.1 ลักษณะภายนอก -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">6.1</span> ลักษณะภายนอก</h6>
                        <div class="row g-3 mb-3 ps-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">รายละเอียดลักษณะภายนอก</label>
                                <textarea class="form-control form-control-sm" name="rf_exterior_detail" id="rf_exterior_detail" rows="3"></textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">จำนวนชั้น</label>
                                <input type="text" class="form-control form-control-sm" name="rf_floor_count" id="rf_floor_count">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">รั้วล้อมรอบ</label>
                                <div class="d-flex gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rf_fence" value="มี" id="rf_fence_yes">
                                        <label class="form-check-label" for="rf_fence_yes">มี</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rf_fence" value="ไม่มี" id="rf_fence_no">
                                        <label class="form-check-label" for="rf_fence_no">ไม่มี</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">เมื่อหันหน้าเข้า</label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านหน้าติด</label>
                                <input type="text" class="form-control form-control-sm" name="rf_ext_front" id="rf_ext_front">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านซ้ายติด</label>
                                <input type="text" class="form-control form-control-sm" name="rf_ext_left" id="rf_ext_left">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านขวาติด</label>
                                <input type="text" class="form-control form-control-sm" name="rf_ext_right" id="rf_ext_right">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านหลังติด</label>
                                <input type="text" class="form-control form-control-sm" name="rf_ext_back" id="rf_ext_back">
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- 6.2 ลักษณะภายใน -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">6.2</span> ลักษณะภายใน</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rf_interior_detail" id="rf_interior_detail" rows="4" placeholder="ระบุลักษณะภายใน..."></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- 6.3 บริเวณที่เกิดเหตุ -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">6.3</span> บริเวณที่เกิดเหตุ</h6>

                        <div class="row g-3 mb-3 ps-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">เกิดเหตุที่</label>
                                <input type="text" class="form-control form-control-sm" name="rf_incident_area" id="rf_incident_area">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">ขนาด</label>
                                <input type="text" class="form-control form-control-sm" name="rf_area_size" id="rf_area_size" placeholder="เช่น 4 x 5 เมตร">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">หันหน้าไปทาง</label>
                                <input type="text" class="form-control form-control-sm" name="rf_facing_direction" id="rf_facing_direction">
                            </div>
                        </div>

                        <!-- ลักษณะโครงสร้าง -->
                        <h6 class="fw-semibold text-dark mb-2 ps-3">ลักษณะโครงสร้าง</h6>
                        <div class="ps-4">
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label">ฝาผนังด้านหน้า</label>
                                    <input type="text" class="form-control form-control-sm" name="rf_wall_front" id="rf_wall_front">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ฝาผนังด้านซ้าย</label>
                                    <input type="text" class="form-control form-control-sm" name="rf_wall_left" id="rf_wall_left">
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label">ฝาผนังด้านขวา</label>
                                    <input type="text" class="form-control form-control-sm" name="rf_wall_right" id="rf_wall_right">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ฝาผนังด้านหลัง</label>
                                    <input type="text" class="form-control form-control-sm" name="rf_wall_back" id="rf_wall_back">
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="form-label">พื้นห้อง</label>
                                    <input type="text" class="form-control form-control-sm" name="rf_floor_material" id="rf_floor_material">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">เพดานห้อง</label>
                                    <input type="text" class="form-control form-control-sm" name="rf_ceiling" id="rf_ceiling">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">หลังคา</label>
                                    <input type="text" class="form-control form-control-sm" name="rf_roof" id="rf_roof">
                                </div>
                            </div>
                        </div>

                        <!-- ลักษณะการจัดวางสิ่งของ -->
                        <h6 class="fw-semibold text-dark mb-2 ps-3 mt-3">ลักษณะการจัดวางสิ่งของ</h6>
                        <div class="row g-3 ps-4">
                            <div class="col-md-12">
                                <label class="form-label">ชิดผนังด้านหน้าเรียงจากซ้ายไปขวา</label>
                                <textarea class="form-control form-control-sm" name="rf_arrange_front" id="rf_arrange_front" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">ชิดผนังด้านซ้ายเรียงจากหน้าไปหลัง</label>
                                <textarea class="form-control form-control-sm" name="rf_arrange_left" id="rf_arrange_left" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">ชิดผนังด้านขวาเรียงจากหน้าไปหลัง</label>
                                <textarea class="form-control form-control-sm" name="rf_arrange_right" id="rf_arrange_right" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">ชิดผนังด้านหลังเรียงจากซ้ายไปขวา</label>
                                <textarea class="form-control form-control-sm" name="rf_arrange_back" id="rf_arrange_back" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">บริเวณอื่นๆ</label>
                                <textarea class="form-control form-control-sm" name="rf_arrange_other" id="rf_arrange_other" rows="2"></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 7. พฤติการณ์คดี & สภาพความเสียหาย ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">7. พฤติการณ์คดี & สภาพความเสียหาย</legend>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">พฤติการณ์ของคดีจากการสอบถามข้อมูลในเบื้องต้นจาก พงส. ได้ความว่า</label>
                                <textarea class="form-control form-control-sm" name="rf_case_behavior" id="rf_case_behavior" rows="4"></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">การทำประกันภัย</label>
                                <div class="d-flex gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rf_insurance" value="มี" id="rf_insurance_yes">
                                        <label class="form-check-label" for="rf_insurance_yes">มี</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rf_insurance" value="ไม่มี" id="rf_insurance_no">
                                        <label class="form-check-label" for="rf_insurance_no">ไม่มี</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">เวลาในการเผาไหม้ (โดยประมาณ)</label>
                                <input type="text" class="form-control form-control-sm" name="rf_burn_time" id="rf_burn_time">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">การดับเพลิง</label>
                                <div class="d-flex gap-3 mt-1 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rf_extinguish" value="ดับแล้ว" id="rf_extinguish_yes">
                                        <label class="form-check-label" for="rf_extinguish_yes">ดับแล้ว</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rf_extinguish" value="ยังไม่ดับ" id="rf_extinguish_no">
                                        <label class="form-check-label" for="rf_extinguish_no">ยังไม่ดับ</label>
                                    </div>
                                </div>
                                <textarea class="form-control form-control-sm" name="rf_extinguish_detail" id="rf_extinguish_detail" rows="2" placeholder="รายละเอียดการดับเพลิง..."></textarea>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">สภาพความเสียหาย</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_condition" id="rf_damage_condition" rows="3"></textarea>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">การลุกลามของเพลิง</label>
                                <textarea class="form-control form-control-sm" name="rf_spread_detail" id="rf_spread_detail" rows="2"></textarea>
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- 7.1 ความเสียหายโครงสร้าง -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.1</span> ความเสียหายโครงสร้าง</h6>
                        <div class="row g-3 ps-3">
                            <div class="col-md-6">
                                <label class="form-label">ฝาผนังด้านหน้า</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_wall_front" id="rf_damage_wall_front" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ฝาผนังด้านซ้าย</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_wall_left" id="rf_damage_wall_left" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ฝาผนังด้านขวา</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_wall_right" id="rf_damage_wall_right" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ฝาผนังด้านหลัง</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_wall_back" id="rf_damage_wall_back" rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">พื้น</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_floor" id="rf_damage_floor" rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">หลังคา</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_roof" id="rf_damage_roof" rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เพดาน</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_ceiling" id="rf_damage_ceiling" rows="2"></textarea>
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- 7.2 ความเสียหายสิ่งของ -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.2</span> ความเสียหายสิ่งของ</h6>
                        <div class="row g-3 ps-3">
                            <div class="col-md-6">
                                <label class="form-label">ด้านหน้า</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_obj_front" id="rf_damage_obj_front" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านซ้าย</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_obj_left" id="rf_damage_obj_left" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านขวา</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_obj_right" id="rf_damage_obj_right" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านหลัง</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_obj_back" id="rf_damage_obj_back" rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">พื้น</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_obj_floor" id="rf_damage_obj_floor" rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">หลังคา</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_obj_roof" id="rf_damage_obj_roof" rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เพดาน</label>
                                <textarea class="form-control form-control-sm" name="rf_damage_obj_ceiling" id="rf_damage_obj_ceiling" rows="2"></textarea>
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- 7.3 จุดเริ่มต้นเพลิง -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.3</span> บริเวณจุดเริ่มต้นของเพลิง</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rf_first_area" id="rf_first_area" rows="3"></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- 7.4 สภาพสวิตช์ -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.4</span> สภาพสวิตช์ไฟฟ้า/อุปกรณ์ไฟฟ้า</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rf_switch_condition" id="rf_switch_condition" rows="3"></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- 7.5 ความเสียหายอาคารข้างเคียง -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.5</span> ความเสียหายอาคารข้างเคียง</h6>
                        <div class="row g-3 ps-3">
                            <div class="col-md-12">
                                <div class="d-flex gap-3 mt-1 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rf_adjacent_damage" value="พบ" id="rf_adjacent_found">
                                        <label class="form-check-label" for="rf_adjacent_found">พบ</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rf_adjacent_damage" value="ไม่พบ" id="rf_adjacent_notfound">
                                        <label class="form-check-label" for="rf_adjacent_notfound">ไม่พบ</label>
                                    </div>
                                </div>
                                <textarea class="form-control form-control-sm" name="rf_adjacent_damage_detail" id="rf_adjacent_damage_detail" rows="2" placeholder="รายละเอียด..."></textarea>
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- 7.6 ร่องรอยและวัตถุพยาน -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.6</span> ร่องรอยและวัตถุพยานที่ตรวจพบในสถานที่เกิดเหตุ</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rf_evidence_found" id="rf_evidence_found" rows="4"></textarea>
                        </div>
                    </fieldset>

                    <!-- ==================== 8. สรุปผลการตรวจ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">8. สรุปผลการตรวจ</legend>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">8.1 บริเวณจุดเริ่มต้นของเพลิง</label>
                                <textarea class="form-control form-control-sm" name="rf_origin_area" id="rf_origin_area" rows="3"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">8.2 แหล่งเชื้อเพลิง</label>
                                <textarea class="form-control form-control-sm" name="rf_fuel_source" id="rf_fuel_source" rows="3"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">8.3 แหล่งความร้อน</label>
                                <textarea class="form-control form-control-sm" name="rf_heat_source" id="rf_heat_source" rows="3"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">8.4 อื่นๆ</label>
                                <textarea class="form-control form-control-sm" name="rf_summary_other" id="rf_summary_other" rows="3"></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 9. ความเห็น ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">9. ความเห็น</legend>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">จุดเริ่มต้นของเพลิง</label>
                                <textarea class="form-control form-control-sm" name="rf_opinion_first_area" id="rf_opinion_first_area" rows="3"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">สาเหตุของเพลิง</label>
                                <div class="d-flex gap-3 mt-1 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rf_cause_type" value="เชื่อว่า" id="rf_cause_believed">
                                        <label class="form-check-label" for="rf_cause_believed">เชื่อว่า</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rf_cause_type" value="ไม่ทราบสาเหตุ" id="rf_cause_unknown">
                                        <label class="form-check-label" for="rf_cause_unknown">ไม่ทราบสาเหตุ</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">รายละเอียดที่เชื่อว่า</label>
                                <textarea class="form-control form-control-sm" name="rf_cause_believed_detail" id="rf_cause_believed_detail" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">รายละเอียดที่ไม่ทราบ</label>
                                <textarea class="form-control form-control-sm" name="rf_cause_unknown_detail" id="rf_cause_unknown_detail" rows="2"></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== การส่งมอบคืนสถานที่ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">การส่งมอบคืนสถานที่เกิดเหตุ</legend>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">เจ้าหน้าที่ (กสก.พฐก./กลก.ศพฐ./พฐ.จว.)</label>
                                <input type="text" class="form-control form-control-sm" name="rf_handover_officer" id="rf_handover_officer">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ตรวจสถานที่เกิดเหตุเสร็จสิ้น พร้อมทั้งส่งมอบสถานที่เกิดเหตุคืนให้กับ</label>
                                <input type="text" class="form-control form-control-sm" name="rf_handover_to" id="rf_handover_to">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rf_handover_date" id="rf_handover_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rf_handover_time" id="rf_handover_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ลงชื่อ ==================== -->
                    <fieldset class="mb-2 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">ลงชื่อผู้รายงาน</legend>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ชื่อผู้รายงาน</label>
                                <input type="text" class="form-control form-control-sm" name="rf_signer_name" id="rf_signer_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ตำแหน่ง</label>
                                <input type="text" class="form-control form-control-sm" name="rf_signer_position" id="rf_signer_position">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">วันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rf_sign_date" id="rf_sign_date">
                            </div>
                        </div>
                    </fieldset>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-success" id="btn_save_report_fire">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
                </button>
            </div>

        </div>
    </div>
</div>
