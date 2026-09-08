<?php
/**
 * Modal: ร่างรายงานคดีจราจร
 * รายงานการตรวจพิสูจน์คดีจราจร
 * Prefix: rt_
 * อ้างอิงจาก บัญชีแนบท้ายคำสั่ง สำนักงานพิสูจน์หลักฐานตำรวจ ที่/๔๔๔/๒๕๖๑
 */
?>

<div class="modal fade" id="modalReportTraffic" aria-labelledby="modalReportTrafficLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportTrafficLabel">
                    รายงานการตรวจพิสูจน์คดีจราจร
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body bg-light p-4">
                <form id="formReportTraffic" novalidate>
                    <input type="hidden" id="rt_incident_id" name="incident_id">

                    <!-- Switch to PDF form -->
                                        <!-- Header Info -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="rt_editInfo" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="rt_editCount" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto me-3">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="rt_doc_no_display"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="rt_report_no_display"></span>
                            </div>
                        </div>
                    
                        <div class="bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2 flex-shrink-0">
                        <div class="form-check form-switch mb-0" style="padding-left: 0;">
                        <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchTrafficReportToPdf"
                        style="width: 3rem; height: 1.5rem; cursor: pointer;"
                        onchange="if(this.checked){ this.checked=false; switchTrafficReportToPdfForm(); }">
                        </div>
                        <label class="fw-bold text-danger mb-0" for="switchTrafficReportToPdf" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
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
                                <input type="date" class="form-control form-control-sm" name="rt_receive_date" id="rt_receive_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">เวลา</label>
                                <input type="time" class="form-control form-control-sm" name="rt_receive_time" id="rt_receive_time">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">ตามประจำวันข้อที่</label>
                                <input type="text" class="form-control form-control-sm" name="rt_daily_ref" id="rt_daily_ref">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">หน่วยงาน</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rt_agency_type" value="กองพิสูจน์หลักฐานกลาง" id="rt_agency_center">
                                        <label class="form-check-label" for="rt_agency_center">กองพิสูจน์หลักฐานกลาง</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rt_agency_type" value="ศูนย์พิสูจน์หลักฐาน" id="rt_agency_sub">
                                        <label class="form-check-label" for="rt_agency_sub">ศูนย์พิสูจน์หลักฐาน</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="rt_agency_type" value="พิสูจน์หลักฐานจังหวัด" id="rt_agency_prov">
                                        <label class="form-check-label" for="rt_agency_prov">พิสูจน์หลักฐานจังหวัด</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="rt_agency_name" id="rt_agency_name" style="width:200px;">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-semibold">ได้รับแจ้งทาง</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rt_notify_method[]" value="หนังสือ" id="rt_notify_letter">
                                        <label class="form-check-label" for="rt_notify_letter">ตามหนังสือ</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rt_notify_method[]" value="โทรศัพท์" id="rt_notify_phone">
                                        <label class="form-check-label" for="rt_notify_phone">ทางโทรศัพท์</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rt_notify_method[]" value="วิทยุสื่อสาร" id="rt_notify_radio">
                                        <label class="form-check-label" for="rt_notify_radio">วิทยุสื่อสาร</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">จาก สน./สภ.</label>
                                <input type="text" class="form-control form-control-sm" name="rt_from_station" id="rt_from_station">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ของเจ้าหน้าที่ ร่วมตรวจพิสูจน์คดีอุบัติเหตุจราจร</label>
                                <input type="text" class="form-control form-control-sm" name="rt_officer_joint" id="rt_officer_joint">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">โดยมี</label>
                                <input type="text" class="form-control form-control-sm" name="rt_officer_name" id="rt_officer_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">เป็นพนักงานสอบสวน</label>
                                <select class="form-select form-select-sm" name="rt_officer_role" id="rt_officer_role">
                                    <option value=""></option>
                                    <option value="พนักงานสอบสวน">พนักงานสอบสวน</option>
                                    <option value="พนักงานสอบสวนเจ้าของคดี">พนักงานสอบสวนเจ้าของคดี</option>
                                    <option value="พนักงานสอบสวนเวร">พนักงานสอบสวนเวร</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== รายละเอียดรถของกลาง ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">รายละเอียดรถของกลาง</legend>

                        <div id="rt_vehicle_container">
                            <!-- รถของกลางรายการที่ 1 -->
                            <div class="rt-vehicle-block bg-light p-3 rounded-3 border mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge bg-primary rt-vehicle-num">รถของกลางรายการที่ 1</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger rt-remove-vehicle" onclick="rtRemoveVehicleBlock(this)">
                                        <i class="fas fa-trash me-1"></i> ลบ
                                    </button>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">เป็นรถ</label>
                                        <input type="text" class="form-control form-control-sm" name="rt_vehicle_type[]">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">ยี่ห้อ</label>
                                        <input type="text" class="form-control form-control-sm" name="rt_vehicle_brand[]">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">สี</label>
                                        <input type="text" class="form-control form-control-sm" name="rt_vehicle_color[]">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">แผ่นป้ายทะเบียน</label>
                                        <input type="text" class="form-control form-control-sm" name="rt_vehicle_plate[]">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">จำนวน</label>
                                        <input type="number" min="0" step="1" class="form-control form-control-sm" name="rt_vehicle_plate_qty[]">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="rtAddVehicleBlock()">
                            <i class="fas fa-plus me-1"></i> เพิ่มรถของกลาง
                        </button>
                    </fieldset>

                    <!-- ==================== 2. จุดประสงค์ในการตรวจพิสูจน์ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">2. จุดประสงค์ในการตรวจพิสูจน์</legend>

                        <p class="text-muted mb-3">(เลือกตามความเหมาะสม)</p>

                        <div class="form-check mb-3 d-flex align-items-start flex-wrap gap-2">
                            <input class="form-check-input mt-1" type="checkbox" name="rt_purpose[]" value="1" id="rt_purpose_1">
                            <label class="form-check-label" for="rt_purpose_1">
                                1. เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนระหว่างรถของกลาง
                            </label>
                            <input type="number" min="0" step="1" class="form-control form-control-sm" name="rt_purpose_qty_1" style="width:80px;">
                            <span>คัน หรือไม่ อย่างไร</span>
                        </div>

                        <div class="form-check mb-3 d-flex align-items-start flex-wrap gap-2">
                            <input class="form-check-input mt-1" type="checkbox" name="rt_purpose[]" value="2" id="rt_purpose_2">
                            <label class="form-check-label" for="rt_purpose_2">
                                2. เพื่อทราบว่ารถของกลาง
                            </label>
                            <input type="number" min="0" step="1" class="form-control form-control-sm" name="rt_purpose_qty_2" style="width:80px;">
                            <span>คัน มีการเฉี่ยวชนกันหรือไม่ อย่างไร</span>
                        </div>

                        <div class="form-check mb-3 d-flex align-items-start flex-wrap gap-2">
                            <input class="form-check-input mt-1" type="checkbox" name="rt_purpose[]" value="3" id="rt_purpose_3">
                            <label class="form-check-label" for="rt_purpose_3">
                                3. เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนที่รถของกลางทั้งหนึ่งหรือไม่และมีลักษณะการเฉี่ยวชนอย่างไร
                            </label>
                        </div>

                        <div class="form-check mb-3 d-flex align-items-start flex-wrap gap-2">
                            <input class="form-check-input mt-1" type="checkbox" name="rt_purpose[]" value="4" id="rt_purpose_4">
                            <label class="form-check-label" for="rt_purpose_4">
                                4. เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนระหว่างรถของกลางหรือไม่อย่างไร
                            </label>
                        </div>

                        <div class="form-check mb-3 d-flex align-items-start flex-wrap gap-2">
                            <input class="form-check-input mt-1" type="checkbox" name="rt_purpose[]" value="5" id="rt_purpose_5">
                            <label class="form-check-label" for="rt_purpose_5">
                                5. อื่นๆ
                            </label>
                            <input type="text" class="form-control form-control-sm flex-grow-1" name="rt_purpose_other_text" id="rt_purpose_other_text">
                        </div>
                    </fieldset>

                    <!-- ==================== 3. ผู้ตรวจพิสูจน์ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">3. ผู้ตรวจพิสูจน์</legend>
                        <div id="rt_inspector_container" class="rp-inspector-container-wrap">
                            <div class="row g-2 mb-2 align-items-center rp-inspector-row">
                                <div class="col-auto">
                                    <span class="fw-semibold rp-inspector-num" style="min-width:32px; display:inline-block;">3.1</span>
                                </div>
                                <div class="col">
                                    <select class="form-select form-select-sm rp-inspector-select" name="rt_inspector_name[]">
                                        <option value="">-- เลือกผู้ตรวจ --</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <span class="fw-semibold">ตำแหน่ง</span>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control form-control-sm rp-inspector-position" name="rt_inspector_position[]" readonly>
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

                    <!-- ==================== 4. ผลการตรวจพิสูจน์ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">4. ผลการตรวจพิสูจน์</legend>

                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">พฤติการณ์คดี</label>
                                <textarea class="form-control form-control-sm" name="rt_case_behavior" id="rt_case_behavior" rows="4"></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">สถานที่ตรวจพิสูจน์</label>
                                <input type="text" class="form-control form-control-sm" name="rt_inspect_location" id="rt_inspect_location">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">เมื่อวันที่</label>
                                <input type="date" class="form-control form-control-sm" name="rt_analyze_date" id="rt_analyze_date">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">เวลาประมาณ</label>
                                <input type="time" class="form-control form-control-sm" name="rt_analyze_time" id="rt_analyze_time">
                            </div>
                        </div>

                        <!-- รถของกลางแต่ละรายการ — ผลการตรวจ -->
                        <div id="rt_analysis_vehicle_container">
                            <!-- รายการที่ 1 -->
                            <div class="rt-analysis-block bg-light p-3 rounded-3 border mb-3">
                                <h6 class="fw-bold text-primary mb-3 rt-analysis-num">4.1 รถของกลางรายการที่ 1</h6>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label">เป็นรถ (อธิบายลักษณะสภาพรถว่ามีการต่อเติมหรือดัดแปลงหรือไม่อย่างไร หรือมิติรถ เป็นต้น)</label>
                                        <textarea class="form-control form-control-sm" name="rt_analysis_vehicle_desc[]" rows="2"></textarea>
                                    </div>
                                </div>

                                <!-- 4.x.1 ด้านหน้า -->
                                <h6 class="fw-semibold"><span class="text-primary">4.1.1</span> ตรวจพบสภาพและความเสียหายด้านหน้ารถ ดังนี้</h6>
                                <div class="ps-3 mb-3">
                                    <div class="mb-2">
                                        <label class="form-label">4.1.1.1 (บอกลักษณะขนาดรอย ระดับความสูงของรอย ทิศทางของรอย วัตถุพยานที่อยู่ในรอย)</label>
                                        <textarea class="form-control form-control-sm" name="rt_damage_front_detail_1[]" rows="2"></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">4.1.1.2</label>
                                        <textarea class="form-control form-control-sm" name="rt_damage_front_detail_2[]" rows="2"></textarea>
                                    </div>
                                    <p class="text-muted small">*** หากเจอวัตถุพยานอื่นๆ เช่น เลือด เส้นผม เป็นต้น ให้ระบุไว้อีกหัวข้อหนึ่งแล้วบอกด้วยว่า ดำเนินการต่ออย่างไร/ ได้รับผลการตรวจฯ ดังรายงาน...... ***</p>
                                </div>

                                <!-- 4.x.2 ด้านซ้าย -->
                                <h6 class="fw-semibold"><span class="text-primary">4.1.2</span> ตรวจพบสภาพและความเสียหายด้านซ้ายรถ ดังนี้</h6>
                                <div class="ps-3 mb-3">
                                    <textarea class="form-control form-control-sm" name="rt_damage_left_detail[]" rows="2"></textarea>
                                </div>

                                <!-- 4.x.3 ด้านท้าย -->
                                <h6 class="fw-semibold"><span class="text-primary">4.1.3</span> ตรวจพบสภาพและความเสียหายด้านท้ายรถ ดังนี้</h6>
                                <div class="ps-3 mb-3">
                                    <textarea class="form-control form-control-sm" name="rt_damage_rear_detail[]" rows="2"></textarea>
                                </div>

                                <!-- 4.x.4 ด้านขวา -->
                                <h6 class="fw-semibold"><span class="text-primary">4.1.4</span> ตรวจพบสภาพและความเสียหายด้านขวารถ ดังนี้</h6>
                                <div class="ps-3 mb-3">
                                    <textarea class="form-control form-control-sm" name="rt_damage_right_detail[]" rows="2"></textarea>
                                </div>

                                <!-- 4.x.5 บริเวณอื่นๆ -->
                                <h6 class="fw-semibold"><span class="text-primary">4.1.5</span> ตรวจพบสภาพและความเสียหายบริเวณอื่นๆ ดังนี้</h6>
                                <div class="ps-3 mb-3">
                                    <textarea class="form-control form-control-sm" name="rt_damage_other_detail[]" rows="2"></textarea>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="rtAddAnalysisVehicleBlock()">
                            <i class="fas fa-plus me-1"></i> เพิ่มรถของกลาง (ผลการตรวจ)
                        </button>

                        <!-- 4.x+1 ลักษณะของสถานที่เกิดเหตุ -->
                        <div class="mt-4">
                            <h6 class="fw-bold text-primary">ลักษณะของสถานที่เกิดเหตุ</h6>
                            <div class="mb-3">
                                <textarea class="form-control form-control-sm" name="rt_scene_detail" id="rt_scene_detail" rows="3"></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 5. ผลการตรวจเปรียบเทียบ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">5. ผลการตรวจเปรียบเทียบ</legend>

                        <p class="text-muted mb-3">จากการเปรียบเทียบสภาพร่องรอยความเสียหายและการแลกเปลี่ยนวัตถุพยานของรถของกลางทั้ง ... รายการ พบว่า</p>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">5.1 รอยครูดบริเวณ</label>
                                <textarea class="form-control form-control-sm" name="rt_compare_5_1" id="rt_compare_5_1" rows="3"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">มีลักษณะรอยและระดับความสูงเข้ากันได้กับรอยครูดบริเวณ (ตามผลการตรวจในข้อ 5.2)</label>
                                <textarea class="form-control form-control-sm" name="rt_compare_5_1_sub" id="rt_compare_5_1_sub" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ตามผลการตรวจในข้อ</label>
                                <input type="text" class="form-control form-control-sm" name="rt_compare_5_ref" id="rt_compare_5_ref" placeholder="เช่น 5.2">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">5.2</label>
                                <textarea class="form-control form-control-sm" name="rt_compare_5_2" id="rt_compare_5_2" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">5.3 (ลักษณะร่องรอยที่เข้ากัน)</label>
                                <textarea class="form-control form-control-sm" name="rt_compare_5_3" id="rt_compare_5_3" rows="2"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">5.4 (ร่องรอยความเสียหายของรถกับร่องรอยบริเวณจุดชนในสถานที่เกิดเหตุ)</label>
                                <textarea class="form-control form-control-sm" name="rt_compare_5_4" id="rt_compare_5_4" rows="2"></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 6. ความเห็น ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">6. ความเห็น</legend>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">จากผลการตรวจในข้อ 4 และ 5 ... จนได้รับความเสียหายดังที่ปรากฏ</label>
                                <textarea class="form-control form-control-sm" name="rt_opinion" id="rt_opinion" rows="4"></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== ลงชื่อ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">ลงชื่อผู้ตรวจพิสูจน์</legend>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ลงชื่อ</label>
                                <input type="text" class="form-control form-control-sm" name="rt_sign_name" id="rt_sign_name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">ตำแหน่ง</label>
                                <input type="text" class="form-control form-control-sm" name="rt_sign_position" id="rt_sign_position">
                            </div>
                        </div>
                    </fieldset>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer bg-white border-top py-3 px-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                <button type="button" class="btn btn-primary" id="btn_save_report_traffic">
                    <i class="fas fa-save me-1"></i> บันทึก
                </button>
            </div>

        </div>
    </div>
</div>

<script>
/* ========== Traffic Report: Dynamic Vehicles ========== */
function rtAddVehicleBlock() {
    var container = document.getElementById('rt_vehicle_container');
    var count = container.querySelectorAll('.rt-vehicle-block').length + 1;
    var html = `
    <div class="rt-vehicle-block bg-light p-3 rounded-3 border mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="badge bg-primary rt-vehicle-num">รถของกลางรายการที่ ${count}</span>
            <button type="button" class="btn btn-sm btn-outline-danger rt-remove-vehicle" onclick="rtRemoveVehicleBlock(this)">
                <i class="fas fa-trash me-1"></i> ลบ
            </button>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">เป็นรถ</label>
                <input type="text" class="form-control form-control-sm" name="rt_vehicle_type[]">
            </div>
            <div class="col-md-4">
                <label class="form-label">ยี่ห้อ</label>
                <input type="text" class="form-control form-control-sm" name="rt_vehicle_brand[]">
            </div>
            <div class="col-md-4">
                <label class="form-label">สี</label>
                <input type="text" class="form-control form-control-sm" name="rt_vehicle_color[]">
            </div>
            <div class="col-md-4">
                <label class="form-label">แผ่นป้ายทะเบียน</label>
                <input type="text" class="form-control form-control-sm" name="rt_vehicle_plate[]">
            </div>
            <div class="col-md-4">
                <label class="form-label">จำนวน</label>
                <input type="number" min="0" step="1" class="form-control form-control-sm" name="rt_vehicle_plate_qty[]">
            </div>
        </div>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

function rtRemoveVehicleBlock(btn) {
    var block = btn.closest('.rt-vehicle-block');
    block.remove();
    rtRenumberVehicles();
}

function rtRenumberVehicles() {
    var blocks = document.querySelectorAll('#rt_vehicle_container .rt-vehicle-block');
    blocks.forEach(function(b, i) {
        b.querySelector('.rt-vehicle-num').textContent = 'รถของกลางรายการที่ ' + (i + 1);
    });
}

/* ========== Traffic Report: Dynamic Analysis Vehicle Blocks ========== */
function rtAddAnalysisVehicleBlock() {
    var container = document.getElementById('rt_analysis_vehicle_container');
    var count = container.querySelectorAll('.rt-analysis-block').length + 1;
    var prefix = '4.' + count;
    var html = `
    <div class="rt-analysis-block bg-light p-3 rounded-3 border mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold text-primary mb-0 rt-analysis-num">${prefix} รถของกลางรายการที่ ${count}</h6>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="rtRemoveAnalysisBlock(this)"><i class="fas fa-trash me-1"></i> ลบ</button>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-12">
                <label class="form-label">เป็นรถ (อธิบายลักษณะสภาพรถ)</label>
                <textarea class="form-control form-control-sm" name="rt_analysis_vehicle_desc[]" rows="2"></textarea>
            </div>
        </div>
        <h6 class="fw-semibold"><span class="text-primary">${prefix}.1</span> ตรวจพบสภาพและความเสียหายด้านหน้ารถ ดังนี้</h6>
        <div class="ps-3 mb-3">
            <div class="mb-2">
                <label class="form-label">${prefix}.1.1</label>
                <textarea class="form-control form-control-sm" name="rt_damage_front_detail_1[]" rows="2"></textarea>
            </div>
            <div class="mb-2">
                <label class="form-label">${prefix}.1.2</label>
                <textarea class="form-control form-control-sm" name="rt_damage_front_detail_2[]" rows="2"></textarea>
            </div>
        </div>
        <h6 class="fw-semibold"><span class="text-primary">${prefix}.2</span> ตรวจพบสภาพและความเสียหายด้านซ้ายรถ ดังนี้</h6>
        <div class="ps-3 mb-3"><textarea class="form-control form-control-sm" name="rt_damage_left_detail[]" rows="2"></textarea></div>
        <h6 class="fw-semibold"><span class="text-primary">${prefix}.3</span> ตรวจพบสภาพและความเสียหายด้านท้ายรถ ดังนี้</h6>
        <div class="ps-3 mb-3"><textarea class="form-control form-control-sm" name="rt_damage_rear_detail[]" rows="2"></textarea></div>
        <h6 class="fw-semibold"><span class="text-primary">${prefix}.4</span> ตรวจพบสภาพและความเสียหายด้านขวารถ ดังนี้</h6>
        <div class="ps-3 mb-3"><textarea class="form-control form-control-sm" name="rt_damage_right_detail[]" rows="2"></textarea></div>
        <h6 class="fw-semibold"><span class="text-primary">${prefix}.5</span> ตรวจพบสภาพและความเสียหายบริเวณอื่นๆ ดังนี้</h6>
        <div class="ps-3 mb-3"><textarea class="form-control form-control-sm" name="rt_damage_other_detail[]" rows="2"></textarea></div>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

function rtRemoveAnalysisBlock(btn) {
    var block = btn.closest('.rt-analysis-block');
    block.remove();
    rtRenumberAnalysisBlocks();
}

function rtRenumberAnalysisBlocks() {
    var blocks = document.querySelectorAll('#rt_analysis_vehicle_container .rt-analysis-block');
    blocks.forEach(function(b, i) {
        var num = i + 1;
        var prefix = '4.' + num;
        b.querySelector('.rt-analysis-num').textContent = prefix + ' รถของกลางรายการที่ ' + num;
        // Renumber sub-headings
        var headings = b.querySelectorAll('h6.fw-semibold .text-primary');
        var subLabels = ['.1', '.2', '.3', '.4', '.5'];
        headings.forEach(function(h, j) {
            if (j < subLabels.length) h.textContent = prefix + subLabels[j];
        });
    });
}
</script>
