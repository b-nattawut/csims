<?php
// ตั้ง timezone เป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// ดึงข้อมูลผู้ตรวจสำหรับ dropdown
$inspectorOptionsLife = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
if (isset($pdo)) {
    $qryInspectorLife = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                     FROM user_profile t1 
                     LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                     ORDER BY t1.user_id DESC";
    $stmtLife = $pdo->query($qryInspectorLife);
    while ($rowLife = $stmtLife->fetch(PDO::FETCH_ASSOC)) {
        $inspectorOptionsLife .= '<option value="' . $rowLife['user_id'] . '">' . htmlspecialchars($rowLife['fullname']) . '</option>';
    }
}

// วันที่ปัจจุบัน (ไทย)
$todayDateLife = date('Y-m-d');
$todayTimeLife = date('H:i');
?>

<!-- Modal QrCode -->
<div class="modal fade" id="addQrcodeModal" aria-labelledby="addQrcodeModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addQrcodeModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <form method="post" id="incidentCheckListFormEvidence" action="./api/incidentCheckList/saveLife.php" novalidate>
                    <input type="hidden" id="receiveNoti_id_evidence" name="receiveNoti_id">
                    <input type="hidden" id="doc_no_evidence" name="doc_no">
                    <input type="hidden" id="report_no_evidence" name="report_no">

                    <!-- Header เลขที่เอกสาร -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="editInfoEvidence" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountEvidence" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="receiveNoti_No_evidence"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="receiveNotiReportNo_evidence"></span>
                            </div>
                        </div>
                    </div>

                    <!-- ==================== 1. การรับแจ้งเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">1. การรับแจ้งเหตุ</legend>

                        <div class="row">
                            <!-- คดี (ดึงเลขที่เอกสารมาแสดง) -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">คดี</label>
                                <input type="text" class="form-control bg-light" id="case_doc_no_evidence" name="case_doc_no" readonly>
                            </div>
                            <!-- วันที่ (ดึงจากข้อมูล แต่แก้ไขได้) -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">วันที่</label>
                                <input type="date" class="form-control" id="case_date_evidence" name="case_date" value="<?= $todayDateLife ?>">
                            </div>
                            <!-- เวลา (ให้เลือกเอง) -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">เวลาประมาณ</label>
                                <input type="time" class="form-control" id="case_time_evidence" name="case_time" value="<?= $todayTimeLife ?>" step="60">
                            </div>
                        </div>

                       

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">สถานีตำรวจ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="police_station" name="police_station" readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ประเภทคดี <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="incident_type" name="incident_type" readonly>
                            </div>
                        </div>

                       

                        
                    </fieldset>

                    <!-- ==================== 2. สถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">2. สถานที่เกิดเหตุ</legend>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">รายละเอียดสถานที่เกิดเหตุ <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="crime_location" rows="3" placeholder="..."></textarea>
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <!-- Container สำหรับรายการผู้ประสบเหตุ -->
                            <div id="victim_container_evidence">
                                <!-- รายการที่ 1 (ค่าเริ่มต้น) -->
                                <div class="victim-card-life bg-light p-3 rounded-3 border mb-3">
                                    <div class="bg-white p-3 rounded-3 shadow-sm">
                                        <!-- ประเภทผู้ประสบเหตุ -->
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">ประเภทผู้ประสบเหตุ <span class="text-danger">*</span></label>
                                            <select class="form-select" name="victim_type_life[]" onchange="toggleVictimFields(this)">
                                                <option value="" selected>-- เลือกประเภท --</option>
                                                <option value="ผู้ต้องหา">ผู้ต้องหา</option>
                                                <option value="ผู้ต้องสงสัย">ผู้ต้องสงสัย</option>
                                                <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                                            </select>
                                        </div>

                                        <!-- ข้อมูลรายละเอียด -->
                                        <div class="row g-3">
                                            <div class="col-md-8">
                                                <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="victim_name_life[]" placeholder="ชื่อ-นามสกุล">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">อายุ (ปี)</label>
                                                <input type="text" class="form-control text-center" name="victim_age_life[]" inputmode="numeric" pattern="[0-9]*" maxlength="3" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 3);" placeholder="อายุ">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">3. วันเวลาที่เกิดเหตุ</legend>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่ผู้เสียหายทราบเหตุ/เกิดเหตุ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="victim_know_date" value="<?= $todayDateLife ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="victim_know_time" value="<?= $todayTimeLife ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">วันที่พนักงานสอบสวนทราบเหตุ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="officer_know_date" value="<?= $todayDateLife ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">เวลาประมาณ <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="officer_know_time" value="<?= $todayTimeLife ?>">
                            </div>
                        </div>
                    </fieldset>
                   

                    <!-- 8. การส่งมอบคืนสถานที่ -->
                    <fieldset class="p-4 bg-white rounded-4 shadow-sm border mb-4">
                        <legend class="fieldset-header">ข้อมูลวัตถุพยาน</legend>

                        <!-- วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">วันเวลาที่ทำการเก็บวัตถุพยาน</label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small text-muted mb-1">วันที่</label>
                                    <input type="date" class="form-control" name="inspection_end_date" value="<?php echo $todayDateLife; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted mb-1">เวลา</label>
                                    <input type="time" class="form-control" name="inspection_end_time" value="<?php echo $todayTimeLife; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="p-3 bg-light rounded border h-100">
                                    <h6 class="fw-bold text-secondary border-bottom pb-2 mb-3">
                                        <i class="fas fa-user-check me-2"></i>ผู้เก็บวัตถุพยาน
                                    </h6>

                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ชื่อ <span class="text-danger">*</span></label>
                                            <select class="form-select user-select-box-evidence" id="receiver_name_evidence" name="receiver_name" data-pos-target="#receiver_position_evidence">
                                                <?php echo str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้รับมอบ --', $inspectorOptionsLife); ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ตำแหน่ง</label>
                                            <input type="text" class="form-control bg-light" id="receiver_position_evidence" name="receiver_position" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">รายละเอียดบาดแผลเพิ่มเติม</label>
                            <textarea class="form-control" name="wound_detail" rows="3" placeholder="..."></textarea>
                        </div>
                            
                    </fieldset>

                    <!-- ==================== ลำดับการครอบครองวัตถุพยาน ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">ลำดับการครอบครองวัตถุพยาน</legend>

                        <div id="inspector_container_evidence">
                            <div class="d-flex align-items-center mb-3 inspector-row-life">
                                <div class="text-end pe-3" style="width: 50px;">
                                    <span class="fw-bold text-secondary index-label">5.1</span>
                                </div>
                                <div class="flex-grow-1">
                                    <select class="form-select inspector-select-evidence" name="inspector_id[]">
                                        <?= $inspectorOptionsLife ?>
                                    </select>
                                </div>
                                <div class="ms-2" style="width: 32px;"></div>
                            </div>
                        </div>

                        <div class="d-flex mt-2">
                            <div style="width: 50px;"></div>
                            <div class="flex-grow-1">
                                <button type="button" class="btn btn-outline-primary border-dashed w-100 py-2" id="btn_add_inspector_evidence">
                                    <i class="fas fa-plus-circle me-1"></i> เพิ่ม
                                </button>
                            </div>
                            <div class="ms-2" style="width: 32px;"></div>
                        </div>
                    </fieldset>

                    <script>
                    // evidenceIndexLife, measurementIndexLife, victimIndexLife declared in modal_life.php

                    

                   
                    // ฟังก์ชัน toggle fields (ถ้าต้องการแสดง/ซ่อนฟิลด์ตามประเภท)
                    function toggleVictimFields(select) {
                        // ปัจจุบันไม่มี logic พิเศษ แต่เก็บไว้สำหรับอนาคต
                    }

                   
                    


                   

                    

                    // Toggle แสดง/ซ่อนช่อง text สำหรับ "อื่นๆ"
                    // document.addEventListener('DOMContentLoaded', function() {
                    //     // เมื่อเลขที่เอกสารถูกกรอก ให้คัดลอกไปแสดงในช่อง "คดี" ด้วย
                    //     const receiveNotiNoLife = document.getElementById('receiveNoti_No_life');
                    //     const caseDocNoLife = document.getElementById('case_doc_no_life');

                    //     // ใช้ MutationObserver เพื่อตรวจจับการเปลี่ยนแปลงของ receiveNoti_No_life
                    //     if (receiveNotiNoLife && caseDocNoLife) {
                    //         const observer = new MutationObserver(function(mutations) {
                    //             mutations.forEach(function(mutation) {
                    //                 if (mutation.type === 'childList' || mutation.type === 'characterData') {
                    //                     caseDocNoLife.value = receiveNotiNoLife.textContent.trim();
                    //                 }
                    //             });
                    //         });

                    //         observer.observe(receiveNotiNoLife, {
                    //             childList: true,
                    //             characterData: true,
                    //             subtree: true
                    //         });

                    //         // กรณีที่มีค่าอยู่แล้วตั้งแต่เริ่มต้น
                    //         if (receiveNotiNoLife.textContent.trim()) {
                    //             caseDocNoLife.value = receiveNotiNoLife.textContent.trim();
                    //         }
                    //     }
                    // )}
                    </script>

                    <div class="modal-footer justify-content-end">
                        <button type="button" class="btn btn-success" id="btn_save_evidence" onclick="prepareDataForSubmissionEvidence()">
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
