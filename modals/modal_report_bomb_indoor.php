<?php
/**
 * Modal: ร่างรายงานคดีระเบิด (ในอาคาร)
 * รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด (ในอาคาร)
 */
?>

<div class="modal fade" id="modalReportBombIndoor" aria-labelledby="modalReportBombIndoorLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportBombIndoorLabel">
                    รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด (ในอาคาร)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body bg-light p-4">
                <form id="formReportBombIndoor" novalidate>
                    <input type="hidden" id="rbi_incident_id" name="incident_id">

                                        <!-- Header Info -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="rbi_editInfo" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="rbi_editCount" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto me-3">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="rbi_doc_no_display"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="rbi_report_no_display"></span>
                            </div>
                        </div>
                    
                        <div class="bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2 flex-shrink-0">
                        <div class="form-check form-switch mb-0" style="padding-left: 0;">
                        <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchBombIndoorReportToPdf"
                        style="width: 3rem; height: 1.5rem; cursor: pointer;"
                        onchange="if(this.checked){ this.checked=false; switchBombIndoorReportToPdfForm(); }">
                        </div>
                        <label class="fw-bold text-danger mb-0" for="switchBombIndoorReportToPdf" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
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
                                <input type="date" class="form-control form-control-sm" name="rbi_receive_date" id="rbi_receive_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">เวลา</label>
                                <input type="time" class="form-control form-control-sm" name="rbi_receive_time" id="rbi_receive_time">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">ตามประจำวันข้อที่</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_daily_ref" id="rbi_daily_ref">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">หน่วยงาน</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rbi_agency_type" value="กสก.พฐก." id="rbi_agency_ksk">
                                        <label class="form-check-label" for="rbi_agency_ksk">กสก.พฐก.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rbi_agency_type" value="กสก.ศพฐ." id="rbi_agency_klk">
                                        <label class="form-check-label" for="rbi_agency_klk">กสก.ศพฐ.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rbi_agency_type" value="พฐ.จว." id="rbi_agency_ptjv">
                                        <label class="form-check-label" for="rbi_agency_ptjv">พฐ.จว.</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="rbi_agency_name" id="rbi_agency_name" placeholder="ระบุชื่อหน่วยงาน" style="width:200px;">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ได้รับแจ้งทาง</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rbi_notify_method[]" value="หนังสือ" id="rbi_notify_letter">
                                        <label class="form-check-label" for="rbi_notify_letter">ตามหนังสือ</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rbi_notify_method[]" value="โทรศัพท์" id="rbi_notify_phone">
                                        <label class="form-check-label" for="rbi_notify_phone">ทางโทรศัพท์</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rbi_notify_method[]" value="วิทยุสื่อสาร" id="rbi_notify_radio">
                                        <label class="form-check-label" for="rbi_notify_radio">วิทยุสื่อสาร</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">จาก สน./สภ.</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_from_station" id="rbi_from_station">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">พนักงานสอบสวนเจ้าของคดี</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_investigator" id="rbi_investigator">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ๒. สถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">2. สถานที่เกิดเหตุ</legend>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">สถานที่เกิดเหตุ</label>
                                <textarea class="form-control form-control-sm" name="rbi_crime_location" id="rbi_crime_location" rows="2"></textarea>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">ผู้เสียชีวิต/ผู้บาดเจ็บ/ผู้เสียหาย</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_victim_name" id="rbi_victim_name">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">อายุประมาณ (ปี)</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_victim_age" id="rbi_victim_age">
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
                                <input type="date" class="form-control form-control-sm" name="rbi_victim_know_date" id="rbi_victim_know_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rbi_victim_know_time" id="rbi_victim_know_time">
                            </div>

                            <div class="col-12 mt-3">
                                <span class="fw-semibold text-dark">พนักงานสอบสวนทราบเหตุ</span>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rbi_officer_know_date" id="rbi_officer_know_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rbi_officer_know_time" id="rbi_officer_know_time">
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
                                <input type="date" class="form-control form-control-sm" name="rbi_inspect_date" id="rbi_inspect_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rbi_inspect_time" id="rbi_inspect_time">
                            </div>

                            <div class="col-12 mt-3">
                                <span class="fw-semibold text-dark">ตรวจสถานที่เกิดเหตุเพิ่มเติม</span>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rbi_inspect_add_date" id="rbi_inspect_add_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rbi_inspect_add_time" id="rbi_inspect_add_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ๕. ผู้ตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">5. ผู้ตรวจสถานที่เกิดเหตุ</legend>
                        <div id="rbi_inspector_container" class="rp-inspector-container-wrap">
                            <div class="row g-2 mb-2 align-items-center rp-inspector-row">
                                <div class="col-auto">
                                    <span class="fw-semibold rp-inspector-num" style="min-width:32px; display:inline-block;">5.1</span>
                                </div>
                                <div class="col">
                                    <select class="form-select form-select-sm rp-inspector-select" name="rbi_inspector_name[]">
                                        <option value="">-- เลือกผู้ตรวจ --</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <span class="fw-semibold">ตำแหน่ง</span>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control form-control-sm rp-inspector-position" name="rbi_inspector_position[]" placeholder="ตำแหน่ง" readonly>
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

                        <!-- 6.1 ลักษณะภายนอก -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">6.1</span> ลักษณะภายนอก</h6>

                        <div class="row g-3 mb-3 ps-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ประเภทอาคาร</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rbi_building_type[]" value="บ้าน" id="rbi_bld_house">
                                        <label class="form-check-label" for="rbi_bld_house">บ้าน</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rbi_building_type[]" value="ตึกแถว" id="rbi_bld_shophouse">
                                        <label class="form-check-label" for="rbi_bld_shophouse">ตึกแถว</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rbi_building_type[]" value="อาคาร" id="rbi_bld_building">
                                        <label class="form-check-label" for="rbi_bld_building">อาคาร</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rbi_building_type[]" value="อื่นๆ" id="rbi_bld_other">
                                        <label class="form-check-label" for="rbi_bld_other">อื่นๆ</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="rbi_building_type_other" id="rbi_building_type_other" placeholder="ระบุ" style="width:180px;">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">จำนวนชั้น</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_floor_count" id="rbi_floor_count">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">จำนวนหลัง/คูหา</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_unit_count" id="rbi_unit_count">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">ชั้นลอย</label>
                                <div class="d-flex gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rbi_mezzanine" value="มี" id="rbi_mezzanine_yes">
                                        <label class="form-check-label" for="rbi_mezzanine_yes">มี</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rbi_mezzanine" value="ไม่มี" id="rbi_mezzanine_no">
                                        <label class="form-check-label" for="rbi_mezzanine_no">ไม่มี</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">ดาดฟ้า</label>
                                <div class="d-flex gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rbi_rooftop" value="มี" id="rbi_rooftop_yes">
                                        <label class="form-check-label" for="rbi_rooftop_yes">มี</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rbi_rooftop" value="ไม่มี" id="rbi_rooftop_no">
                                        <label class="form-check-label" for="rbi_rooftop_no">ไม่มี</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">รั้วล้อมรอบ</label>
                                <div class="d-flex gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rbi_fence" value="มี" id="rbi_fence_yes">
                                        <label class="form-check-label" for="rbi_fence_yes">มี</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rbi_fence" value="ไม่มี" id="rbi_fence_no">
                                        <label class="form-check-label" for="rbi_fence_no">ไม่มี</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">เมื่อหันหน้าเข้า</label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านหน้าติด</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_ext_front" id="rbi_ext_front">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านซ้ายติด</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_ext_left" id="rbi_ext_left">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านขวาติด</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_ext_right" id="rbi_ext_right">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ด้านหลังติด</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_ext_back" id="rbi_ext_back">
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- 6.2 ลักษณะภายใน -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">6.2</span> ลักษณะภายใน</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rbi_interior_detail" id="rbi_interior_detail" rows="4" placeholder="ระบุลักษณะภายใน..."></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- 6.3 บริเวณที่เกิดเหตุ -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">6.3</span> บริเวณที่เกิดเหตุ</h6>

                        <div class="row g-3 mb-3 ps-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">เกิดเหตุที่</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_incident_area" id="rbi_incident_area">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">ขนาด กว้าง x ยาว ประมาณ</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_area_size" id="rbi_area_size" placeholder="เช่น 4 x 5 เมตร">
                            </div>
                        </div>

                        <!-- ลักษณะโครงสร้าง -->
                        <h6 class="fw-semibold text-dark mb-2 ps-3">ลักษณะโครงสร้าง</h6>
                        <div class="ps-4">
                            <!-- ฝาผนังด้านหน้า -->
                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="form-label">ฝาผนังด้านหน้า</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_wall_front" id="rbi_wall_front">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">หน้าต่าง (บาน)</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_wall_front_window" id="rbi_wall_front_window">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">ประตู (บาน)</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_wall_front_door" id="rbi_wall_front_door">
                                </div>
                            </div>
                            <!-- ฝาผนังด้านซ้าย -->
                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="form-label">ฝาผนังด้านซ้าย</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_wall_left" id="rbi_wall_left">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">หน้าต่าง (บาน)</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_wall_left_window" id="rbi_wall_left_window">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">ประตู (บาน)</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_wall_left_door" id="rbi_wall_left_door">
                                </div>
                            </div>
                            <!-- ฝาผนังด้านขวา -->
                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="form-label">ฝาผนังด้านขวา</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_wall_right" id="rbi_wall_right">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">หน้าต่าง (บาน)</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_wall_right_window" id="rbi_wall_right_window">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">ประตู (บาน)</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_wall_right_door" id="rbi_wall_right_door">
                                </div>
                            </div>
                            <!-- ฝาผนังด้านหลัง -->
                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="form-label">ฝาผนังด้านหลัง</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_wall_back" id="rbi_wall_back">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">หน้าต่าง (บาน)</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_wall_back_window" id="rbi_wall_back_window">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">ประตู (บาน)</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_wall_back_door" id="rbi_wall_back_door">
                                </div>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="form-label">พื้นห้อง</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_floor_material" id="rbi_floor_material">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">เพดานห้อง</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_ceiling" id="rbi_ceiling">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">หลังคา</label>
                                    <input type="text" class="form-control form-control-sm" name="rbi_roof" id="rbi_roof">
                                </div>
                            </div>
                        </div>

                        <!-- ลักษณะการจัดวางสิ่งของ -->
                        <h6 class="fw-semibold text-dark mb-2 ps-3 mt-3">ลักษณะการจัดวางสิ่งของ</h6>
                        <div class="row g-3 ps-4">
                            <div class="col-md-12">
                                <label class="form-label">ชิดผนังด้านหน้าเรียงจากซ้ายไปขวา</label>
                                <textarea class="form-control form-control-sm" name="rbi_arrange_front" id="rbi_arrange_front" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">ชิดผนังด้านซ้ายเรียงจากหน้าไปหลัง</label>
                                <textarea class="form-control form-control-sm" name="rbi_arrange_left" id="rbi_arrange_left" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">ชิดผนังด้านขวาเรียงจากหน้าไปหลัง</label>
                                <textarea class="form-control form-control-sm" name="rbi_arrange_right" id="rbi_arrange_right" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">ชิดผนังด้านหลังเรียงจากซ้ายไปขวา</label>
                                <textarea class="form-control form-control-sm" name="rbi_arrange_back" id="rbi_arrange_back" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">รายละเอียดอื่นๆ</label>
                                <textarea class="form-control form-control-sm" name="rbi_arrange_other" id="rbi_arrange_other" rows="2"></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ๗. ผลการตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">7. ผลการตรวจสถานที่เกิดเหตุ</legend>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">พฤติการณ์ของคดีจากการสอบถามข้อมูลในเบื้องต้นจาก พงส. ได้ความว่า</label>
                                <textarea class="form-control form-control-sm" name="rbi_case_behavior" id="rbi_case_behavior" rows="4" placeholder="ระบุพฤติการณ์ของคดี..."></textarea>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">จากการตรวจสถานที่เกิดเหตุ</label>
                                <textarea class="form-control form-control-sm" name="rbi_inspection_detail" id="rbi_inspection_detail" rows="3"></textarea>
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- 7.1 สภาพของสถานที่เกิดเหตุเมื่อไปถึง -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.1</span> สภาพของสถานที่เกิดเหตุเมื่อไปถึง</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rbi_scene_condition" id="rbi_scene_condition" rows="4" placeholder="ระบุสภาพสถานที่เกิดเหตุเมื่อไปถึง..."></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- 7.2 ลักษณะสภาพศพ -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.2</span> ลักษณะสภาพศพ</h6>

                        <div class="row g-3 ps-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">7.2.1 พบศพ/ไม่พบศพ</label>
                                <textarea class="form-control form-control-sm" name="rbi_body_found" id="rbi_body_found" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">7.2.2 ตำแหน่งที่พบศพ</label>
                                <textarea class="form-control form-control-sm" name="rbi_body_position" id="rbi_body_position" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">7.2.3 สภาพศพ</label>
                                <textarea class="form-control form-control-sm" name="rbi_body_condition" id="rbi_body_condition" rows="3"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">7.2.4 สภาพเครื่องแต่งกายและทรัพย์สิน</label>
                                <textarea class="form-control form-control-sm" name="rbi_body_clothing" id="rbi_body_clothing" rows="3"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">7.2.5 รอยบาดแผลที่ศพ</label>
                                <textarea class="form-control form-control-sm" name="rbi_body_wounds" id="rbi_body_wounds" rows="4"></textarea>
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- 7.3 สภาพความเสียหาย -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.3</span> สภาพความเสียหาย</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rbi_damage_detail" id="rbi_damage_detail" rows="4" placeholder="ระบุสภาพความเสียหาย..."></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- 7.4 ร่องรอยและวัตถุพยาน -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.4</span> ร่องรอยและวัตถุพยานที่ตรวจพบในสถานที่เกิดเหตุ</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rbi_evidence_found" id="rbi_evidence_found" rows="4" placeholder="ระบุร่องรอยและวัตถุพยาน..."></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- 7.5 วัตถุพยานที่ตรวจเก็บ -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.5</span> วัตถุพยานที่ตรวจเก็บในสถานที่เกิดเหตุ</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rbi_evidence_collected" id="rbi_evidence_collected" rows="4" placeholder="ระบุวัตถุพยานที่ตรวจเก็บ..."></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- 7.6 การดำเนินการเกี่ยวกับวัตถุพยาน -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.6</span> การดำเนินการเกี่ยวกับวัตถุพยาน</h6>
                        <div class="ps-3 mb-3">
                            <textarea class="form-control form-control-sm" name="rbi_evidence_action" id="rbi_evidence_action" rows="4" placeholder="ระบุการดำเนินการเกี่ยวกับวัตถุพยาน..."></textarea>
                        </div>

                        <hr class="my-3">

                        <!-- 7.7 การส่งมอบ -->
                        <h6 class="fw-bold text-dark mb-3"><span class="text-primary">7.7</span> การส่งมอบสถานที่เกิดเหตุ</h6>
                        <div class="row g-3 ps-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">เจ้าหน้าที่ (กสก.พฐก./กสก.ศพฐ./พฐ.จว.)</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_handover_officer" id="rbi_handover_officer">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ตรวจสถานที่เกิดเหตุเสร็จสิ้น พร้อมทั้งส่งมอบสถานที่เกิดเหตุคืนให้กับ</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_handover_to" id="rbi_handover_to">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rbi_handover_date" id="rbi_handover_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rbi_handover_time" id="rbi_handover_time">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ลงชื่อ ==================== -->
                    <fieldset class="mb-2 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">ลงชื่อผู้รายงาน</legend>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ชื่อผู้รายงาน</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_signer_name" id="rbi_signer_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ตำแหน่ง</label>
                                <input type="text" class="form-control form-control-sm" name="rbi_signer_position" id="rbi_signer_position">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">วันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rbi_sign_date" id="rbi_sign_date">
                            </div>
                        </div>
                    </fieldset>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-success" id="btn_save_report_bomb_indoor">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> ยกเลิก
                </button>
            </div>

        </div>
    </div>
</div>
