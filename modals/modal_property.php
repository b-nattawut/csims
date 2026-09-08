<?php
// ตั้ง timezone เป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// วันที่ปัจจุบัน
$dateNow = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d\TH:i');

// ดึงข้อมูลผู้ตรวจสำหรับ dropdown
$inspectorOptionsProp = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
if (isset($pdo)) {
    $qryInspectorProp = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                     FROM user_profile t1 
                     LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                     ORDER BY t1.user_id DESC";
    $stmtProp = $pdo->query($qryInspectorProp);
    while ($rowProp = $stmtProp->fetch(PDO::FETCH_ASSOC)) {
        $inspectorOptionsProp .= '<option value="' . $rowProp['user_id'] . '">' . htmlspecialchars($rowProp['fullname']) . '</option>';
    }
}
?>

<!-- Modal สำหรับคดีทรัพย์ (complaints_type = '01') -->
<div class="modal fade" id="addCheckListModal" aria-labelledby="addCheckListModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="addCheckListModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light p-4" style="position:relative;">
                <!-- Loading Overlay -->
                <style>
                    @keyframes csims-bar-fill {
                        0%   { width: 0%; }
                        100% { width: 100%; }
                    }
                    .csims-loading-overlay {
                        position:absolute;top:0;left:0;right:0;bottom:0;
                        background:rgba(255,255,255,0.88);z-index:1050;
                        display:flex;align-items:center;justify-content:center;
                    }
                    .csims-bar-track {
                        width: 220px; height: 32px;
                        background: #fff;
                        border: 2.5px solid #3b5998;
                        border-radius: 999px;
                        overflow: hidden;
                        padding: 3px;
                    }
                    .csims-bar-fill {
                        height: 100%;
                        background: #3b5998;
                        border-radius: 999px;
                        animation: csims-bar-fill 2s ease-in-out infinite;
                    }
                </style>
                <div id="propertyLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track">
                            <div class="csims-bar-fill"></div>
                        </div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>
                <form method="post" id="incidentCheckListForm" action="./api/incidentCheckList/save.php" novalidate>
                    <input type="hidden" id="receiveNoti_id" name="receiveNoti_id">
                    <input type="hidden" id="doc_no" name="doc_no">
                    <input type="hidden" id="report_no" name="report_no">

                    <!-- Header เลขที่เอกสาร -->
                    <div class="d-flex align-items-center mb-4 pb-2 border-bottom">
                        <div id="editInfoProperty" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountProperty" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="text-end ms-auto">
                            <span class="d-block text-muted lh-sm">เลขที่เอกสาร</span>
                            <span class="fs-5 fw-bold text-primary" id="receiveNoti_No"></span>
                            <div class="badge bg-white text-secondary border d-flex align-items-center px-2 py-1 mt-1">
                                <span class="fw-normal text-muted me-2" style="font-size: 0.75rem;">เลขรายงาน:</span>
                                <span class="fw-bold text-secondary" style="font-size: 0.85rem;" id="receiveNotiReportNo"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Switch กรอกตามฟอร์มเสมือนจริง -->
                    <div class="mb-3 d-flex align-items-center gap-3 flex-wrap">
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToPdfFormProperty" style="width: 3rem; height: 1.5rem; cursor: pointer;" onchange="if(this.checked){ this.checked=false; switchToPropertyPdfForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToPdfFormProperty" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

                    <!-- ==================== 1. ข้อมูลการรับแจ้งเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">1. ข้อมูลการรับแจ้งเหตุ</legend>

                        <div class="row">
                            <!-- ประเภทคดี -->
                            <div class="col-md-6 mb-3">
                                <label for="case_type" class="form-label">ประเภทคดี <span class="text-danger">*</span></label>
                                <select class="form-select" id="case_type" name="case_type">
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="theft">ลักทรัพย์</option>
                                    <option value="snatch">ชิงทรัพย์</option>
                                    <option value="robbery">ปล้นทรัพย์</option>
                                    <option value="other">อื่น ๆ</option>
                                </select>
                            </div>
                            <!-- วัน-เวลา ที่รับแจ้งเหตุ -->
                            <div class="col-md-6 mb-3">
                                <label for="report_datetime" class="form-label">วันที่รับแจ้งเหตุ <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="report_datetime" name="report_datetime" value="<?php echo $dateNow; ?>">
                            </div>

                            <!-- ช่องอื่น ๆ ของ ประเภทคดี -->
                            <div id="case_type_other_div" class="col-md-12 mb-3 mt-2 d-none">
                                <label for="case_type_other" class="form-label">อื่นๆ โปรดระบุรายละเอียด <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="case_type_other" name="case_type_other" placeholder="โปรดระบุประเภทคดี" maxlength="100">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="case_type_other" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>

                            <!-- สภ./สน. -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">รับแจ้งเหตุจาก <span class="text-danger">*</span></label>
                                <div class="row g-2">
                                    <div class="col-sm-12">
                                        <select id="source_station" name="source_station" class="form-select">
                                            <?php
                                            $qryPoliceStation = "SELECT * FROM master_police_station ORDER BY id DESC";
                                            $stmt = $pdo->query($qryPoliceStation);
                                            ?>
                                            <option value="" selected disabled>กรุณาเลือก</option>
                                            <?php
                                            while ($station = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                echo '<option value="' . htmlspecialchars($station['station_name']) . '">' . htmlspecialchars($station['station_name']) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- จังหวัด -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label small text-muted">จังหวัด</label>
                                <select class="form-select" id="provinceID" name="provinceID">
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="95">ยะลา</option>
                                    <option value="94">ปัตตานี</option>
                                    <option value="96">นราธิวาส</option>
                                </select>
                            </div>

                            <!-- ช่องทางที่รับแจ้ง -->
                            <div class="col-md-4 mb-3">
                                <label for="report_channel" class="form-label">ช่องทางที่รับแจ้ง <span class="text-danger">*</span></label>
                                <select class="form-select" id="report_channel" name="report_channel">
                                    <option value="" selected disabled>กรุณาเลือก</option>
                                    <option value="phone">ทางโทรศัพท์</option>
                                    <option value="radio">ทางวิทยุสื่อสาร</option>
                                    <option value="document">ทางหนังสือ</option>
                                    <option value="other">อื่น ๆ</option>
                                </select>
                            </div>
                            <!-- ที่ -->
                            <div class="col-md-4 mb-3">
                                <label for="document_no" class="form-label">ที่</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="document_no" name="document_no" placeholder="">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="document_no" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <!-- ลง -->
                            <div class="col-md-4 mb-3">
                                <label for="document_date" class="form-label">ลง</label>
                                <div class="input-group">
                                    <input type="date" class="form-control" id="document_date" name="document_date" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>

                            <!-- ช่องอื่น ๆ ของ ช่องทางที่รับแจ้ง -->
                            <div id="report_channel_other_div" class="col-md-12 mb-3 mt-2 d-none">
                                <label class="form-label">อื่นๆ โปรดระบุรายละเอียด <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="report_channel_other" name="report_channel_other" placeholder="โปรดระบุช่องทาง" maxlength="100">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="report_channel_other" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>

                            <div class="col-md-12 mt-3 mb-3">
                                <h6 class="fw-bold text-primary border-bottom pb-2">ข้อมูลพนักงานสอบสวน</h6>
                            </div>

                            <!-- ชื่อพนักงานสอบสวน -->
                            <div class="col-md-4 mb-3">
                                <label for="investigator_firstname" class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="investigator_firstname" name="investigator_firstname">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="investigator_firstname" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <!-- นามสกุลพนักงานสอบสวน -->
                            <div class="col-md-4 mb-3">
                                <label for="investigator_lastname" class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="investigator_lastname" name="investigator_lastname">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="investigator_lastname" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <!-- เบอร์โทรศัพท์พนักงานสอบสวน -->
                            <div class="col-md-4 mb-3">
                                <label for="investigator_phone" class="form-label">หมายเลขโทรศัพท์ <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control person-phone" id="investigator_phone" name="investigator_phone" maxlength="10" placeholder="08xxxxxxxx">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 2. สถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">2. สถานที่เกิดเหตุ</legend>

                        <div class="row">
                            <!-- สถานที่เกิดเหตุ -->
                            <div class="col-md-12 mb-3">
                                <label for="incident_location" class="form-label">รายละเอียดสถานที่ <span class="text-danger">*</span> <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="incident_location" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" id="incident_location" name="incident_location" rows="3"></textarea>
                            </div>

                            <!-- ผู้เสียหาย / ผู้เกี่ยวข้อง -->
                            <div class="mb-3 mt-3">
                                <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">ข้อมูลผู้เสียหาย / ผู้เกี่ยวข้อง</h6>

                                <div class="mb-4">
                                    <label class="form-label small text-primary fw-bold mb-2">
                                        ประเภทบุคคล <span class="text-danger">* </span> <small class="fw-normal text-muted"> (เลือก 1 รายการ)</small>
                                    </label>

                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input victim-type-check" type="checkbox" name="victim_type" value="owner" id="victim_type_owner">
                                                <label class="form-check-label cursor-pointer" for="victim_type_owner">เจ้าของบ้าน</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input victim-type-check" type="checkbox" name="victim_type" value="victim" id="victim_type_victim">
                                                <label class="form-check-label cursor-pointer" for="victim_type_victim">ผู้เสียหาย</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input victim-type-check" type="checkbox" name="victim_type" value="other" id="victim_type_other_cb">
                                                <label class="form-check-label cursor-pointer" for="victim_type_other_cb">ผู้เกี่ยวข้องอื่น ๆ</label>
                                            </div>
                                            <div id="victim_type_other_div" class="d-none mt-2">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm"
                                                        id="victim_type_other_text"
                                                        name="victim_type_other_text"
                                                        placeholder="ระบุความเกี่ยวข้อง...">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="victim_type_other_text" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- ชื่อ ผู้เสียหาย / ผู้เกี่ยวข้อง -->
                                    <div class="col-md-5 mb-3">
                                        <label for="victim_firstname" class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="victim_firstname" name="victim_firstname">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="victim_firstname" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <!-- นามสกุล ผู้เสียหาย / ผู้เกี่ยวข้อง -->
                                    <div class="col-md-5 mb-3">
                                        <label for="victim_lastname" class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="victim_lastname" name="victim_lastname">
                                            <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="victim_lastname" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                        </div>
                                    </div>
                                    <!-- อายุ ผู้เสียหาย / ผู้เกี่ยวข้อง -->
                                    <div class="col-md-2 mb-3">
                                        <label for="victim_age" class="form-label">อายุ (ปี)</label>
                                        <input type="number" class="form-control" id="victim_age" name="victim_age" min="1" max="120">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">3. วันเวลาที่ทราบเหตุ/เกิดเหตุ</legend>

                        <div class="row">
                            <!-- วันเวลาที่ผู้เสียหายทราบเหตุ/เกิดเหตุ -->
                            <div class="col-md-6 mb-3">
                                <label for="incident_datetime" class="form-label">
                                    วันเวลาที่ผู้เสียหายทราบเหตุ/เกิดเหตุ <span class="text-danger">*</span>
                                </label>
                                <input type="datetime-local" class="form-control" id="incident_datetime" name="incident_datetime" value="<?php echo $dateNow; ?>">
                            </div>

                            <!-- วันเวลาที่พนักงานสอบสวนทราบเหตุ -->
                            <div class="col-md-6 mb-3">
                                <label for="investigator_known_datetime" class="form-label">
                                    วันเวลาที่พนักงานสอบสวนทราบเหตุ <span class="text-danger">*</span>
                                </label>
                                <input type="datetime-local" class="form-control" id="investigator_known_datetime" name="investigator_known_datetime" value="<?php echo $dateNow; ?>">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 4. วันเวลาที่ตรวจเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">4. วันเวลาที่ตรวจเหตุ</legend>

                        <div class="row">
                            <!-- วันเวลาที่ทำการตรวจสถานที่เกิดเหตุ -->
                            <div class="col-md-6 mb-3">
                                <label for="inspection_datetime" class="form-label">
                                    วันเวลาที่ทำการตรวจสถานที่เกิดเหตุ <span class="text-danger">*</span>
                                </label>
                                <input type="datetime-local" class="form-control" id="inspection_datetime" name="inspection_datetime" value="<?php echo $dateNow; ?>">
                            </div>
                            <!-- วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเพิ่มเติม -->
                            <div class="col-md-6 mb-3">
                                <label for="inspection_additional_datetime" class="form-label">
                                    วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเพิ่มเติม <small class="text-muted">(ถ้ามี)</small>
                                </label>
                                <input type="datetime-local" class="form-control" id="inspection_additional_datetime" name="inspection_additional_datetime">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 5. ผู้ตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">5. ผู้ตรวจสถานที่เกิดเหตุ</legend>

                        <div id="inspector_container">
                            <div class="d-flex align-items-center mb-3 inspector-row">
                                <div class="text-end pe-3" style="width: 50px;">
                                    <span class="fw-bold text-secondary index-label">5.1</span>
                                </div>
                                <div class="flex-grow-1">
                                    <select class="form-select inspector-select" name="inspector_id[]">
                                        <?php echo $inspectorOptions; ?>
                                    </select>
                                </div>
                                <div class="ms-2" style="width: 32px;"></div>
                            </div>
                        </div>

                        <div class="d-flex mt-2">
                            <div style="width: 50px;"></div>
                            <div class="flex-grow-1">
                                <button type="button" class="btn btn-sm btn-outline-primary border-dashed w-100 py-2" id="btn_add_inspector">
                                    <i class="fas fa-plus-circle me-1"></i> เพิ่มผู้ตรวจ
                                </button>
                            </div>
                            <div class="ms-2" style="width: 38px;"></div>
                        </div>
                    </fieldset>

                    <!-- ==================== 6. ลักษณะสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">6. ลักษณะสถานที่เกิดเหตุ</legend>

                        <!-- การรักษาสถานที่เกิดเหตุ -->
                        <div class="mb-4 mt-2">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">สภาพสถานที่เกิดเหตุเมื่อไปถึง</h6>

                            <div class="mb-3">
                                <label class="form-label small text-primary fw-bold mb-2">
                                    การรักษาสถานที่เกิดเหตุ
                                    <span class="text-danger">*</span> <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small>
                                </label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input preservation-check cursor-pointer" type="checkbox" name="scene_preservation" value="yes" id="preservation_yes">
                                            <label class="form-check-label cursor-pointer" for="preservation_yes">มีการรักษาสถานที่</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input preservation-check cursor-pointer" type="checkbox" name="scene_preservation" value="no" id="preservation_no">
                                            <label class="form-check-label cursor-pointer" for="preservation_no">ไม่มีการรักษาสถานที่</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- รายละเอียดเพิ่มเติม (แสดงเมื่อเลือก มี/ไม่มี) -->
                                <div id="preservation_detail_div" class="mt-2 d-none">
                                    <label id="preservation_label" class="form-label small text-primary fw-bold mb-1">รายละเอียด:</label>
                                    <div class="input-group">
                                    <input type="text" class="form-control" id="preservation_text" name="preservation_text" placeholder="ระบุรายละเอียด..." disabled>
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="preservation_text" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                                </div>
                            </div>
                        </div>

                        <!-- ลักษณะภายนอก (สิ่งปลูกสร้าง) -->
                        <div class="mb-4 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">ลักษณะสถานที่เกิดเหตุ</h6>

                            <div class="mb-3">
                                <label class="form-label small text-primary fw-bold mb-2">ลักษณะภายนอก <span class="text-danger">*</span> <small class="text-muted fw-normal ms-1">(เลือกได้หลายรายการ)</small></label>

                                <!-- ชั้น -->
                                <div class="row mb-3">
                                    <div class="col-auto">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light border-0 small">ชั้น</span>
                                            <input type="text" class="form-control text-center" id="building_floor" name="building_floor" style="width: 60px;" placeholder="-">
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-2">
                                    <?php
                                    $buildingTypes = [
                                        'concrete' => 'บ้านคอนกรีต',
                                        'wood' => 'บ้านไม้',
                                        'half' => 'บ้านครึ่งตึกครึ่งไม้',
                                        'row_building' => 'ตึกแถว',
                                        'concrete_bldg' => 'อาคารคอนกรีต',
                                        'townhouse' => 'ทาวน์เฮาส์',
                                        'commercial' => 'อาคารพาณิชย์',
                                        'other' => 'อื่น ๆ'
                                    ];
                                    foreach ($buildingTypes as $key => $label) {
                                    ?>
                                        <div class="col-lg-3 col-md-4 col-6">
                                            <div class="form-check">
                                                <input class="form-check-input building-check cursor-pointer" type="checkbox" name="building_type[]" value="<?php echo $key; ?>" id="build_<?php echo $key; ?>" data-key="<?php echo $key; ?>">
                                                <label class="form-check-label cursor-pointer small" for="build_<?php echo $key; ?>"><?php echo $label; ?></label>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                                <!-- ช่องรายละเอียดสำหรับ อื่นๆ -->
                                <div class="row mt-2" id="building_other_detail_row" style="display:none;">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control form-control-sm" id="building_detail_other" name="building_detail_other" placeholder="ระบุรายละเอียด อื่นๆ...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- สภาพแวดล้อมและบริเวณโดยรอบ -->
                        <div class="mb-4 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">สภาพแวดล้อมและบริเวณโดยรอบ</h6>

                            <div class="mb-3">
                                <label class="form-label small text-primary fw-bold mb-2">
                                    ลักษณะรั้วกั้น
                                    <span class="text-danger">*</span>
                                    <small class="text-muted fw-normal ms-1">(เลือก 1 รายการ)</small>
                                </label>

                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input fence-check cursor-pointer" type="checkbox" name="fence_type" value="has_fence" id="fence_yes">
                                            <label class="form-check-label cursor-pointer" for="fence_yes">มีรั้วกั้น</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input fence-check cursor-pointer" type="checkbox" name="fence_type" value="no_fence" id="fence_no">
                                            <label class="form-check-label cursor-pointer" for="fence_no">ไม่มีรั้วกั้น</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-primary mb-2">
                                        เมื่อหันหน้าเข้าสถานที่เกิดเหตุ ติดกับ:
                                    </label>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted">ด้านหน้า</label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control form-control-sm" name="scene_front" placeholder="ระบุ...">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="scene_front" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted">ด้านซ้าย</label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control form-control-sm" name="scene_left" placeholder="ระบุ...">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="scene_left" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted">ด้านขวา</label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control form-control-sm" name="scene_right" placeholder="ระบุ...">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="scene_right" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted">ด้านหลัง</label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control form-control-sm" name="scene_back" placeholder="ระบุ...">
                                                <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="scene_back" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- รายละเอียดเพิ่มเติม -->
                        <div class="mb-2 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">รายละเอียดเพิ่มเติม</h6>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="scene_interior" class="form-label fw-bold text-primary">ลักษณะภายใน <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="scene_interior" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                    <textarea class="form-control" id="scene_interior" name="scene_interior" rows="3" placeholder="ระบุลักษณะการจัดวางเฟอร์นิเจอร์, สภาพห้อง..."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="scene_point" class="form-label fw-bold text-primary">จุดที่เกิดเหตุ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="scene_point" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                    <textarea class="form-control" id="scene_point" name="scene_point" rows="3" placeholder="ระบุบริเวณที่พบร่องรอย หรือจุดสำคัญ..."></textarea>
                                </div>
                            </div>
                        </div>

                        <style>
                            input:disabled, textarea:disabled {
                                background-color: #f8f9fa !important;
                                opacity: 0.7;
                                cursor: not-allowed;
                            }
                        </style>
                    </fieldset>

                    <!-- ==================== 7. ผลการตรวจสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">7. ผลการตรวจสถานที่เกิดเหตุ</legend>

                        <!-- พฤติการณ์ของคดี -->
                        <div class="mb-4">
                            <label for="case_behavior" class="form-label fw-bold text-primary">
                                พฤติการณ์ของคดี <span class="text-danger">*</span> <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="case_behavior" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                            </label>
                            <textarea class="form-control" id="case_behavior" name="case_behavior" rows="4" placeholder="ระบุพฤติการณ์โดยสังเขป..."></textarea>
                        </div>

                        <!-- ทางเข้าของคนร้าย -->
                        <div class="mb-4 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">ทางเข้าของคนร้าย</h6>

                            <div class="mb-3">
                                <label class="form-label small text-primary fw-bold mb-2">ลักษณะทางเข้า <span class="text-danger">*</span> <small class="text-muted fw-normal ms-1">(เลือกได้หลายรายการ)</small></label>

                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input entry-main-check cursor-pointer" type="checkbox" name="entry_point[]" value="no_trace" id="entry_no_trace">
                                            <label class="form-check-label cursor-pointer" for="entry_no_trace">
                                                ไม่พบร่องรอยใด ๆ <small class="text-muted">(บริเวณสถานที่เกิดเหตุ)</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input entry-main-check cursor-pointer" type="checkbox" name="entry_point[]" value="unlocked" id="entry_unlocked">
                                            <label class="form-check-label cursor-pointer" for="entry_unlocked">
                                                ผู้เสียหายไม่ได้ทำการปิดล็อก <small class="text-muted">(ประตู/หน้าต่าง)</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- พบร่องรอยการงัดแงะ/บุกรุก -->
                                <div class="border rounded p-3">
                                    <div class="form-check mb-3 pb-2 border-bottom">
                                        <input class="form-check-input entry-main-check cursor-pointer" type="checkbox" name="entry_point[]" value="found_trace" id="entry_found_trace" data-toggle="trace_details">
                                        <label class="form-check-label cursor-pointer fw-bold text-dark" for="entry_found_trace">พบร่องรอยการงัดแงะ/บุกรุก</label>
                                    </div>

                                    <div id="trace_details" class="opacity-50" style="pointer-events: none; transition: opacity 0.3s;">

                                        <!-- ลักษณะการกระทำ -->
                                        <div class="mb-3">
                                            <label class="form-label small text-dark fw-bold mb-2">ลักษณะการกระทำ:</label>
                                            <div class="d-flex flex-wrap align-items-center gap-3">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input entry-sub-check cursor-pointer" type="checkbox" name="entry_point[]" value="pry" id="entry_pry">
                                                    <label class="form-check-label cursor-pointer" for="entry_pry">การงัด</label>
                                                </div>
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input entry-sub-check cursor-pointer" type="checkbox" name="entry_point[]" value="cut" id="entry_cut">
                                                    <label class="form-check-label cursor-pointer" for="entry_cut">การตัด</label>
                                                </div>
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input entry-sub-check cursor-pointer" type="checkbox" name="entry_point[]" value="drill" id="entry_drill">
                                                    <label class="form-check-label cursor-pointer" for="entry_drill">การเจาะ</label>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check mb-0">
                                                        <input class="form-check-input entry-sub-check cursor-pointer toggle-input" type="checkbox" name="entry_point[]" value="other_trace" id="entry_other_trace" data-target="entry_other_trace_detail">
                                                        <label class="form-check-label cursor-pointer" for="entry_other_trace">อื่นๆ</label>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm" name="entry_other_trace_detail" id="entry_other_trace_detail" placeholder="ระบุ..." style="width: 160px;" disabled>
                                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" data-hw-targets="entry_other_trace_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- เครื่องมือที่คนร้ายใช้ -->
                                        <div class="mb-3 border-top pt-3">
                                            <label class="form-label small text-dark fw-bold mb-2">เครื่องมือที่คนร้ายใช้:</label>
                                            <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                                                <?php
                                                $tools = ['screwdriver' => 'ไขควง', 'crowbar' => 'ชะแลง', 'bolt_cutter' => 'คีมตัดโลหะ'];
                                                foreach ($tools as $val => $label) { ?>
                                                    <div class="form-check mb-0">
                                                        <input class="form-check-input entry-sub-check cursor-pointer" type="checkbox" name="burglary_tool[]" value="<?php echo $val; ?>" id="tool_<?php echo $val; ?>">
                                                        <label class="form-check-label cursor-pointer" for="tool_<?php echo $val; ?>"><?php echo $label; ?></label>
                                                    </div>
                                                <?php } ?>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="form-check mb-0">
                                                        <input class="form-check-input entry-sub-check cursor-pointer toggle-input" type="checkbox" name="burglary_tool[]" value="other" id="tool_other" data-target="tool_other_detail">
                                                        <label class="form-check-label cursor-pointer" for="tool_other">อื่นๆ</label>
                                                    </div>
                                                    <input type="text" class="form-control form-control-sm" name="tool_other_detail" id="tool_other_detail" placeholder="ระบุเครื่องมือ..." style="width: 160px;" disabled>
                                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" data-hw-targets="tool_other_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 mt-2">
                                                <label class="form-label mb-0 text-dark text-nowrap fw-bold small">ขนาดความกว้างของรอย:</label>
                                                <div class="input-group input-group-sm" style="width: 130px;">
                                                    <input type="number" class="form-control text-end" id="trace_width" name="trace_width" step="0.1" min="0" placeholder="0.0">
                                                    <span class="input-group-text text-secondary small">ซม.</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- บริเวณ/ตำแหน่ง -->
                                        <div class="mb-0 border-top pt-3">
                                            <label class="form-label small text-dark fw-bold mb-2">บริเวณ/ตำแหน่ง:</label>
                                            <?php
                                            $entryLocations = [
                                                'door' => 'ประตู',
                                                'window' => 'หน้าต่าง',
                                                'ceiling' => 'ฝ้าเพดาน',
                                                'roof' => 'หลังคา',
                                                'other_location' => 'อื่น ๆ'
                                            ];
                                            foreach ($entryLocations as $key => $label) {
                                                $isOther = ($key == 'other_location');
                                            ?>
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <div class="form-check mb-0" style="min-width: 100px;">
                                                        <input class="form-check-input entry-check-toggle cursor-pointer <?php echo $isOther ? 'toggle-input' : ''; ?>"
                                                            type="checkbox" name="entry_point[]" value="<?php echo $key; ?>" id="entry_<?php echo $key; ?>"
                                                            <?php echo $isOther ? 'data-target="entry_detail_' . $key . '"' : ''; ?>>
                                                        <label class="form-check-label cursor-pointer text-nowrap" for="entry_<?php echo $key; ?>"><?php echo $label; ?></label>
                                                    </div>

                                                    <?php if ($isOther) { ?>
                                                        <input type="text" class="form-control form-control-sm flex-grow-1"
                                                            name="entry_detail_<?php echo $key; ?>" id="entry_detail_<?php echo $key; ?>"
                                                            placeholder="ระบุตำแหน่ง..." disabled>
                                                    <?php } else { ?>
                                                        <div class="flex-grow-1 entry-input-div d-none">
                                                            <input type="text" class="form-control form-control-sm"
                                                                id="entry_detail_<?php echo $key; ?>" name="entry_detail_<?php echo $key; ?>" placeholder="ระบุรายละเอียดจุด...">
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- คดีชิงทรัพย์/ปล้นทรัพย์ -->
                        <div class="mb-4 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">คดีชิงทรัพย์/ปล้นทรัพย์ <small class="text-muted fw-normal">(กรณีทราบข้อมูลคนร้าย)</small></h6>

                            <!-- จำนวนคนร้าย -->
                            <div class="d-flex align-items-center mb-3">
                                <label class="me-2 text-nowrap">จำนวนคนร้าย</label>
                                <input type="text" class="form-control form-control-sm" id="perpetrator_count" name="perpetrator_count" placeholder="" style="max-width: 250px;">
                                <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" data-hw-targets="perpetrator_count" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                <label class="ms-2 text-nowrap">คน</label>
                            </div>

                            <!-- การใช้อาวุธ -->
                            <div class="form-check mb-2">
                                <input class="form-check-input weapon-main-check" type="checkbox" name="weapon_use_status" value="none" id="weapon_none">
                                <label class="form-check-label cursor-pointer" for="weapon_none">คนร้ายไม่ใช้อาวุธในการก่อเหตุ</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input weapon-main-check" type="checkbox" name="weapon_use_status" value="used" id="weapon_used">
                                <label class="form-check-label cursor-pointer" for="weapon_used">อาวุธที่คนร้ายใช้ในการก่อเหตุ</label>
                            </div>
                            <div class="ps-4 mb-3">
                                <div class="d-flex flex-wrap gap-3 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input weapon-sub-check cursor-pointer" type="checkbox" name="weapon_type[]" value="knife" id="wp_knife">
                                        <label class="form-check-label cursor-pointer" for="wp_knife">อาวุธมีด</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input weapon-sub-check cursor-pointer" type="checkbox" name="weapon_type[]" value="gun" id="wp_gun">
                                        <label class="form-check-label cursor-pointer" for="wp_gun">อาวุธปืน</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input weapon-sub-check cursor-pointer" type="checkbox" name="weapon_type[]" value="rope" id="wp_rope">
                                        <label class="form-check-label cursor-pointer" for="wp_rope">เชือก</label>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input weapon-sub-check toggle-input cursor-pointer" type="checkbox" name="weapon_type[]" value="other" id="wp_other" data-target="weapon_other_detail">
                                        <label class="form-check-label cursor-pointer" for="wp_other">อื่นๆ</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="weapon_other_detail" id="weapon_other_detail" placeholder="ระบุ..." style="max-width: 250px;" disabled>
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" data-hw-targets="weapon_other_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>

                            <!-- คนร้ายได้พันธนาการผู้เสียหายอย่างไร -->
                            <label class="form-label fw-bold text-primary mb-2">คนร้ายได้พันธนาการผู้เสียหายอย่างไร</label>
                            <div class="ps-3 mb-3">
                                <div class="form-check mb-2">
                                    <input class="form-check-input cursor-pointer" type="checkbox" name="restraint_method[]" value="confinement" id="restraint_confinement">
                                    <label class="form-check-label cursor-pointer" for="restraint_confinement">กักขังภายในห้อง</label>
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input cursor-pointer" type="checkbox" name="restraint_method[]" value="binding" id="restraint_binding">
                                        <label class="form-check-label cursor-pointer text-nowrap" for="restraint_binding">คนร้ายใช้</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="binding_material" placeholder="ระบุวัสดุ เช่น เชือก, เทปกาว" style="max-width: 300px;">
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open" data-hw-targets="binding_material" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                    <span class="text-nowrap">ในการพันธนาการ</span>
                                </div>
                            </div>

                            <!-- ผลต่อผู้เสียหาย -->
                            <div class="border rounded p-3">
                                <div class="d-flex flex-wrap gap-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input injury-check cursor-pointer" type="checkbox" name="victim_status[]" value="injured" id="status_injured">
                                        <label class="form-check-label cursor-pointer" for="status_injured">มีผู้บาดเจ็บ</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input injury-check cursor-pointer" type="checkbox" name="victim_status[]" value="deceased" id="status_deceased">
                                        <label class="form-check-label cursor-pointer" for="status_deceased">มีผู้เสียชีวิต</label>
                                    </div>
                                </div>
                                <label class="form-label fw-bold text-primary mb-2">ลักษณะ/ตำแหน่ง/จำนวนของบาดแผล <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="injury_detail" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" name="injury_detail" rows="4" placeholder="ระบุลักษณะ, ตำแหน่ง, จำนวนของบาดแผล..." style="resize: none;"></textarea>
                            </div>
                        </div>

                        <!-- สภาพร่องรอยและตำแหน่งที่ตรวจพบ -->
                        <div class="row mb-4 mt-4">
                            <div class="col-md-12 mb-2">
                                <h6 class="fw-bold text-primary border-bottom pb-2">
                                    สภาพร่องรอยและตำแหน่งที่ตรวจพบ
                                    <span class="text-muted fw-normal small">(สามารถเพิ่มได้หลายจุด)</span>
                                </h6>
                            </div>

                            <div id="trace_point_container" class="col-md-12">
                            </div>

                            <div class="col-md-12">
                                <button type="button" class="btn btn-outline-primary border-dashed w-100 py-2" id="btn_add_trace_point">
                                    <i class="fas fa-plus-circle me-1"></i> เพิ่มจุดที่ตรวจพบ
                                </button>
                            </div>
                        </div>

                        <!-- ทรัพย์สินถูกโจรกรรม -->
                        <div class="row mb-4 mt-4">
                            <div class="col-md-12">
                                <label for="stolen_property" class="form-label fw-bold text-primary d-block">
                                    ทรัพย์สินถูกโจรกรรม <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="stolen_property" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                </label>
                                <textarea class="form-control" id="stolen_property" name="stolen_property" rows="10" placeholder="ระบุรายการทรัพย์สินที่ถูกโจรกรรม..."></textarea>
                            </div>
                        </div>

                        <!-- วัตถุพยานที่ตรวจพบ -->
                        <div class="row mb-4 mt-4">
                            <div class="col-md-12 mb-2">
                                <h6 class="fw-bold text-primary border-bottom pb-2">
                                    วัตถุพยานที่ตรวจพบ
                                    <span class="text-muted fw-normal small">(สามารถเพิ่มได้หลายรายการ)</span>
                                </h6>
                            </div>

                            <div id="evidence_container" class="col-md-12">
                            </div>

                            <div class="col-md-12">
                                <button type="button" class="btn btn-outline-primary border-dashed w-100 py-2" id="btn_add_evidence">
                                    <i class="fas fa-plus-circle me-1"></i> เพิ่มวัตถุพยาน
                                </button>
                            </div>
                        </div>

                        <!-- คราบสีแดงคล้ายโลหิต -->
                        <div class="mb-4 mt-4">
                            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">คราบสีแดงคล้ายโลหิต</h6>

                            <div class="mb-3">
                                <div class="form-check mb-2">
                                    <input class="form-check-input cursor-pointer" type="checkbox" name="evidence_blood_stain" value="1" id="prop_evidence_blood">
                                    <label class="form-check-label cursor-pointer fw-bold" for="prop_evidence_blood">คราบสีแดงคล้ายโลหิต</label>
                                </div>
                                <div class="ms-4">
                                    <label class="form-label small text-muted mb-1">รายละเอียด</label>
                                    <div class="input-group">
                                    <input type="text" class="form-control" name="blood_stain_detail" placeholder="ระบุรายละเอียด...">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="blood_stain_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                                </div>
                            </div>

                            <!-- ทดสอบด้วยชุดทดสอบคราบโลหิตเบื้องต้น -->
                            <div class="mb-3">
                                <label class="form-label fw-bold text-primary mb-2">ทดสอบด้วยชุดทดสอบคราบโลหิตเบื้องต้น</label>

                                <!-- Hemastix -->
                                <div class="border rounded p-3 mb-3">
                                    <div class="form-check mb-2 pb-2 border-bottom">
                                        <input class="form-check-input cursor-pointer" type="checkbox" name="test_hemastix" value="1" id="prop_test_hemastix">
                                        <label class="form-check-label cursor-pointer fw-bold" for="prop_test_hemastix">Hemastix</label>
                                    </div>
                                    <label class="form-label small text-primary fw-bold mb-2">ผลการทดสอบ:</label>
                                    <div class="row g-2">
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" name="hemastix_result" value="มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน" id="prop_hemastix_positive" data-group="prop_hemastix_result">
                                                <label class="form-check-label cursor-pointer" for="prop_hemastix_positive">มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน</label>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" name="hemastix_result" value="ไม่มีการเปลี่ยนแปลง" id="prop_hemastix_negative" data-group="prop_hemastix_result">
                                                <label class="form-check-label cursor-pointer" for="prop_hemastix_negative">ไม่มีการเปลี่ยนแปลง</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Phenolphthalein -->
                                <div class="border rounded p-3">
                                    <div class="form-check mb-2 pb-2 border-bottom">
                                        <input class="form-check-input cursor-pointer" type="checkbox" name="test_phenolphthalein" value="1" id="prop_test_phenol">
                                        <label class="form-check-label cursor-pointer fw-bold" for="prop_test_phenol">Phenolphthalein</label>
                                    </div>
                                    <label class="form-label small text-primary fw-bold mb-2">ผลการทดสอบ:</label>
                                    <div class="row g-2">
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" name="phenol_result" value="มีการเปลี่ยนแปลงเป็นสีชมพูในทันที" id="prop_phenol_positive" data-group="prop_phenol_result">
                                                <label class="form-check-label cursor-pointer" for="prop_phenol_positive">มีการเปลี่ยนแปลงเป็นสีชมพูในทันที</label>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <div class="form-check">
                                                <input class="form-check-input life-radio-toggle cursor-pointer" type="checkbox" name="phenol_result" value="ไม่มีการเปลี่ยนแปลง" id="prop_phenol_negative" data-group="prop_phenol_result">
                                                <label class="form-check-label cursor-pointer" for="prop_phenol_negative">ไม่มีการเปลี่ยนแปลง</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- การตรวจสอบครั้งสุดท้าย -->
                        <div class="row mb-0 mt-3" id="final_verification_section">
                            <div class="col-md-12">
                                <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">การตรวจสอบครั้งสุดท้าย</h6>
                                <p class="text-muted small mb-3">กรุณายืนยันการดำเนินงานทั้ง 2 หัวข้อก่อนทำการบันทึกและส่งมอบงาน</p>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="final_check[]" value="collected_all" id="check_success_1">
                                    <label class="form-check-label cursor-pointer" for="check_success_1">
                                        ตรวจเก็บวัตถุพยานครบถ้วน
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="final_check[]" value="photos_taken" id="check_success_2">
                                    <label class="form-check-label cursor-pointer" for="check_success_2">
                                        ถ่ายภาพและดำเนินการส่งมอบสถานที่เกิดเหตุแก่พนักงานสอบสวน
                                    </label>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 8. การส่งมอบคืนสถานที่เกิดเหตุ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">8. การส่งมอบคืนสถานที่เกิดเหตุ</legend>

                        <div class="row mb-4">
                            <div class="col-md-12">
                                <label for="inspection_end_datetime" class="form-label fw-bold">วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จ <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="inspection_end_datetime" name="inspection_end_datetime" value="<?php echo $dateNow; ?>">
                            </div>
                        </div>

                        <h6 class="fw-bold text-primary border-bottom pb-2 mb-4">การส่งมอบสถานที่เกิดเหตุ</h6>

                        <div class="row g-4">

                            <!-- ผู้รับมอบ -->
                            <div class="col-lg-6">
                                <div class="p-3 bg-light rounded border h-100">
                                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">
                                        ผู้รับมอบสถานที่เกิดเหตุ
                                    </h6>

                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ลงชื่อ (ผู้รับมอบ) <span class="text-danger">*</span></label>
                                            <select class="form-select user-select-box" id="receiver_id" name="receiver_id" data-pos-target="#receiver_position">
                                                <?php echo $inspectorOptions; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ตำแหน่ง</label>
                                            <input type="text" class="form-control bg-white" id="receiver_position" name="receiver_position" readonly>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small text-muted">ลายเซ็นผู้รับมอบ</label>
                                        <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden w-100" style="height: 180px;">
                                            <canvas id="sig-canvas-receiver" class="signature-pad w-100 h-100"></canvas>
                                            <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none">
                                                <i class="fas fa-signature fa-2x mb-1 d-block text-center"></i> เซ็นชื่อที่นี่
                                            </div>
                                        </div>
                                        <div class="text-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('sig-canvas-receiver')">
                                                <i class="fas fa-eraser me-1"></i> ล้าง
                                            </button>
                                        </div>
                                        <input type="hidden" name="receiver_signature_data" id="receiver_signature_data">
                                    </div>
                                </div>
                            </div>

                            <!-- ผู้ส่งมอบ -->
                            <div class="col-lg-6">
                                <div class="p-3 bg-light rounded border h-100">
                                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">
                                        ผู้ส่งมอบสถานที่เกิดเหตุ
                                    </h6>

                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ลงชื่อ (ผู้ส่งมอบ) <span class="text-danger">*</span></label>
                                            <select class="form-select user-select-box" id="deliverer_id" name="deliverer_id" data-pos-target="#deliverer_position">
                                                <?php echo $inspectorOptions; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted">ตำแหน่ง</label>
                                            <input type="text" class="form-control bg-white" id="deliverer_position" name="deliverer_position" readonly>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small text-muted">ลายเซ็นผู้ส่งมอบ</label>
                                        <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden w-100" style="height: 180px;">
                                            <canvas id="sig-canvas-deliverer" class="signature-pad w-100 h-100"></canvas>
                                            <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none">
                                                <i class="fas fa-signature fa-2x mb-1 d-block text-center"></i> เซ็นชื่อที่นี่
                                            </div>
                                        </div>
                                        <div class="text-end mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('sig-canvas-deliverer')">
                                                <i class="fas fa-eraser me-1"></i> ล้าง
                                            </button>
                                        </div>
                                        <input type="hidden" name="deliverer_signature_data" id="deliverer_signature_data">
                                    </div>
                                </div>
                            </div>

                        </div>
                    </fieldset>

                    <!-- ==================== 9. แผนผังสังเขป ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">9. แผนผังสังเขป</legend>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="signature-box bg-white border rounded-3 shadow-sm position-relative overflow-hidden" style="height: 700px;">
                                    <canvas id="scene_sketch_canvas" class="signature-pad"></canvas>
                                    <div class="text-muted position-absolute top-50 start-50 translate-middle opacity-25 pe-none user-select-none">
                                        <div class="text-center">
                                            <i class="fas fa-pencil-alt fa-3x mb-2"></i>
                                            <div>วาดแผนผังที่นี่</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="text-danger fw-bold small">* NOT TO SCALE</span>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button type="button" class="btn btn-sm btn-outline-warning px-3" onclick="sketchUndo('scene_sketch_canvas')">
                                            <i class="fas fa-undo me-1"></i> ย้อนกลับ
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning px-3 btn-sketch-eraser" id="scene_sketch_canvas_eraser_btn" data-canvas="scene_sketch_canvas" onclick="sketchToggleEraser('scene_sketch_canvas')">
                                            <i class="fas fa-eraser me-1"></i> ยางลบ
                                        </button>
                                        <div class="d-flex align-items-center gap-1">
                                            <i class="fas fa-pen" style="font-size:10px;color:#666;"></i>
                                            <input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize('scene_sketch_canvas',this.value);this.nextElementSibling.textContent=this.value+'px'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">
                                            <span style="font-size:11px;color:#666;min-width:30px;">2px</span>
                                        </div>
                                        <label class="btn btn-sm btn-outline-primary px-2 mb-0" title="เลือกสี" style="cursor:pointer;">
                                            <i class="fas fa-palette me-1"></i>
                                            <input type="color" value="#000000" onchange="sketchSetColor('scene_sketch_canvas',this.value)" style="width:0;height:0;padding:0;border:0;visibility:hidden;position:absolute;">
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-secondary px-3" onclick="clearSignature('scene_sketch_canvas')">
                                            <i class="fas fa-trash-can me-1"></i> ล้างกระดาน
                                        </button>
                                    </div>
                                </div>

                                <input type="hidden" name="scene_sketch_data" id="scene_sketch_data">
                            </div>

                            <div class="col-md-12">
                                <label for="sketch_remark" class="form-label">หมายเหตุ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open ms-1" data-hw-targets="sketch_remark" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                                <textarea class="form-control" id="sketch_remark" name="sketch_remark" rows="4" placeholder="ระบุรายละเอียดเพิ่มเติม..."></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 10. บันทึกการตรวจเก็บวัตถุพยาน ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">10. บันทึกการตรวจเก็บวัตถุพยาน</legend>

                        <!-- วันที่ตรวจสอบที่เกิดเหตุ -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="measurement_inspection_date_property" class="form-label">วันที่ตรวจสอบที่เกิดเหตุ</label>
                                <input type="datetime-local" class="form-control" id="measurement_inspection_date_property" name="measurement_inspection_date_property">
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 text-secondary"><i class="fas fa-clipboard-list me-2"></i>รายการวัตถุพยาน</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addMeasurementCardProperty()">
                                    <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                                </button>
                            </div>

                            <div id="measurement_container_property">
                                <!-- Card แรก -->
                                <div class="measurement-card-property card mb-3 shadow-sm">
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <!-- แถวที่ 1: รายการวัตถุพยาน, จำนวน -->
                                            <div class="col-md-8">
                                                <label class="form-label small text-muted">1. รายการวัตถุพยาน</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="measurement_item_property[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">2. จำนวน</label>
                                                <input type="text" class="form-control" name="measurement_quantity_property[]" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                            </div>

                                            <!-- แถวที่ 2: บริเวณที่ตรวจพบ, ป้ายหมายเลข -->
                                            <div class="col-md-8">
                                                <label class="form-label small text-muted">3. บริเวณที่ตรวจพบ</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="measurement_area_property[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">4. ป้ายหมายเลข</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="measurement_label_number_property[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>

                                            <!-- แถวที่ 3: การบรรจุหีบ -->
                                            <div class="col-12">
                                                <label class="form-label small text-muted">5. การบรรจุหีบ</label>
                                                <div class="row g-2">
                                                    <div class="col-md-4">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="measurement_package_plastic_check_prop_0" value="1" onchange="togglePackageInputProperty(this, 'plastic_prop_0')">
                                                                <label class="form-check-label">พลาสติก</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm" name="measurement_package_plastic_text_prop_0" id="package_plastic_prop_0" disabled>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="measurement_package_paper_check_prop_0" value="1" onchange="togglePackageInputProperty(this, 'paper_prop_0')">
                                                                <label class="form-check-label">กระดาษ</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm" name="measurement_package_paper_text_prop_0" id="package_paper_prop_0" disabled>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="measurement_package_other_check_prop_0" value="1" onchange="togglePackageInputProperty(this, 'other_prop_0')">
                                                                <label class="form-check-label">อื่นๆ</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm" name="measurement_package_other_text_prop_0" id="package_other_prop_0" disabled>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- แถวที่ 4: การดำเนินการเกี่ยวกับวัตถุพยาน -->
                                            <div class="col-12">
                                                <label class="form-label small text-muted">6. การดำเนินการเกี่ยวกับวัตถุพยาน</label>
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                                                <input class="form-check-input" type="checkbox" name="measurement_action_return_check_prop_0" value="1" onchange="toggleActionInputProperty(this, 'return_prop_0')">
                                                                <label class="form-check-label">ส่งคืนพงส.</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_return_text_prop_0" id="action_return_prop_0" disabled>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                                                <input class="form-check-input" type="checkbox" name="measurement_action_other_check_prop_0" value="1" onchange="toggleActionInputProperty(this, 'other_action_prop_0')">
                                                                <label class="form-check-label">อื่นๆ</label>
                                                            </div>
                                                            <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_other_text_prop_0" id="action_other_action_prop_0" disabled>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- แถวที่ 5: หมายเหตุ -->
                                            <div class="col-md-12">
                                                <label class="form-label small text-muted">7. หมายเหตุ</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="measurement_remark_property[]">
                                                    <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                                </div>
                                            </div>

                                            <!-- แถวที่ 6: การตรวจพิสูจน์ -->
                                            <div class="col-md-12">
                                                <label class="form-label small text-muted">8. การตรวจพิสูจน์</label>
                                                <select class="form-select" name="measurement_forensic_unit_property[]">
                                                    <option value="" selected>-- กรุณาเลือก --</option>
                                                    <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                                    <option value="bio_dna">กลุ่มงานตรวจชีววิทยาและดีเอ็นเอ</option>
                                                    <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                                    <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                                    <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                                    <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                                    <option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option>
                                                    <option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                                                </select>
                                            </div>

                                            <!-- ปุ่มลบ -->
                                            <div class="col-12">
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMeasurementCardProperty(this)">
                                                    <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ผู้บันทึก และ วัน/เวลา -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="measurement_recorder_property" class="form-label">9. ผู้บันทึก</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="measurement_recorder_property" name="measurement_recorder_property">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="measurement_recorder_property" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="measurement_datetime_property" class="form-label">10. วัน/เวลา</label>
                                <input type="datetime-local" class="form-control" id="measurement_datetime_property" name="measurement_datetime_property">
                            </div>
                        </div>
                    </fieldset>

                    <!-- ==================== 11. บันทึกการถ่ายภาพ ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">11. บันทึกการถ่ายภาพ</legend>

                        <div class="row mb-4">
                            <div class="col-md-4 mb-2">
                                <label class="form-label small text-muted">รหัสภาพถ่ายที่</label>
                                <input type="text" class="form-control" name="photo_id_start" id="photo_id_start">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label small text-muted">ถึง</label>
                                <input type="text" class="form-control" name="photo_id_end" id="photo_id_end">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label small text-muted">รวมจำนวน (ภาพ)</label>
                                <input type="number" class="form-control" name="photo_amount" id="photo_amount" min="0" value="0">
                            </div>
                        </div>

                        <!-- Hidden file inputs -->
                        <input type="file" id="incident_photos" name="incident_photos[]" accept="image/*" style="display: none;" multiple>
                        <input type="file" id="camera_input_property" name="camera_photos[]" accept="image/*" capture="environment" style="display: none;" multiple>

                        <!-- Drag & Drop Zone -->
                        <div id="dropzone_property" class="dropzone-property-area">
                            <div class="dropzone-property-content" id="dropzone_property_content">
                                <i class="fas fa-cloud-upload-alt dropzone-property-icon"></i>
                                <p class="dropzone-property-title">ลากไฟล์รูปภาพมาวางที่นี่</p>
                                <p class="dropzone-property-subtitle">หรือคลิกเพื่อเลือกไฟล์ (รองรับหลายไฟล์พร้อมกัน)</p>
                                <div class="d-flex gap-2 justify-content-center mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn_choose_file_property">
                                        <i class="fas fa-folder-open me-1"></i>เลือกไฟล์
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" id="btn_open_camera_property">
                                        <i class="fas fa-camera me-1"></i>เปิดกล้อง
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Progress -->
                        <div id="uploading_container" class="mb-3"></div>

                        <!-- Attachments Grid (7 columns) -->
                        <div id="attachments_wrapper" class="d-none mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold text-primary mb-0">
                                    <i class="fas fa-images me-1"></i>รูปภาพที่แนบ <span class="badge bg-secondary rounded-pill ms-1" id="file_count_badge">0</span>
                                </h6>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="btn_clear_all_photos" title="ลบรูปทั้งหมด">
                                    <i class="fas fa-trash-alt me-1"></i>ลบทั้งหมด
                                </button>
                            </div>
                            <div class="row g-2 row-cols-3 row-cols-md-4 row-cols-lg-5" id="attachments_grid">
                            </div>
                        </div>

                        <style>
                            .dropzone-property-area {
                                border: 2px dashed #b0bec5;
                                border-radius: 12px;
                                padding: 30px 20px;
                                text-align: center;
                                cursor: pointer;
                                transition: all 0.3s ease;
                                background: #f8f9fa;
                                margin-bottom: 10px;
                            }
                            .dropzone-property-area:hover,
                            .dropzone-property-area.dragover {
                                border-color: #2196F3;
                                background: #e3f2fd;
                            }
                            .dropzone-property-area.dragover {
                                transform: scale(1.01);
                                box-shadow: 0 0 20px rgba(33,150,243,0.2);
                            }
                            .dropzone-property-icon {
                                font-size: 2.5rem;
                                color: #90a4ae;
                                margin-bottom: 8px;
                            }
                            .dropzone-property-area:hover .dropzone-property-icon,
                            .dropzone-property-area.dragover .dropzone-property-icon {
                                color: #2196F3;
                            }
                            .dropzone-property-title {
                                font-size: 1rem;
                                font-weight: 600;
                                color: #455a64;
                                margin-bottom: 2px;
                            }
                            .dropzone-property-subtitle {
                                font-size: 0.8rem;
                                color: #90a4ae;
                                margin-bottom: 0;
                            }
                            /* Compact attachment card — เหมือนตัวอย่างหน้างาน */
                            .attach-thumb-wrapper {
                                position: relative;
                                margin-bottom: 6px;
                            }
                            .attach-thumb-card {
                                position: relative;
                                border: 1.5px solid #333;
                                overflow: hidden;
                                background: #fff;
                            }
                            .attach-thumb-card img {
                                width: 100%;
                                aspect-ratio: 4/3;
                                object-fit: cover;
                                display: block;
                            }
                            .attach-thumb-card .attach-remove-btn {
                                position: absolute;
                                top: 2px;
                                right: 2px;
                                width: 18px;
                                height: 18px;
                                border-radius: 50%;
                                border: none;
                                background: rgba(220,53,69,0.85);
                                color: #fff;
                                font-size: 0.6rem;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                cursor: pointer;
                                opacity: 0;
                                transition: opacity 0.2s;
                                padding: 0;
                                line-height: 1;
                            }
                            .attach-thumb-card:hover .attach-remove-btn {
                                opacity: 1;
                            }
                            .attach-filename {
                                font-size: 0.7rem;
                                font-weight: 500;
                                color: #222;
                                text-align: center;
                                padding: 3px 2px 0;
                                white-space: nowrap;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                font-family: 'Sarabun', sans-serif;
                            }
                        </style>
                    </fieldset>

                    <!-- ==================== 12. ข้อมูลผู้จดบันทึก ==================== -->
                    <fieldset class="mb-4 bg-white p-4 rounded-3 shadow-sm border">
                        <legend class="fieldset-header">12. ข้อมูลผู้จดบันทึก</legend>
                        <p class="text-muted small mb-3">ข้อมูลนี้จะแสดงในส่วน "ผู้จดบันทึก" และ "วัน/เวลา" ของทุกหน้า (แผนผัง, ตารางวัตถุพยาน, บันทึกการถ่ายภาพ)</p>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="recorder_name" class="form-label">ผู้จดบันทึก</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="recorder_name" name="recorder_name" placeholder="ชื่อ-สกุล ผู้จดบันทึก">
                                    <button type="button" class="btn btn-outline-primary btn-hw-open" data-hw-targets="recorder_name" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="recorder_datetime" class="form-label">วัน/เวลา ที่บันทึก</label>
                                <input type="datetime-local" class="form-control" id="recorder_datetime" name="recorder_datetime">
                            </div>
                        </div>
                    </fieldset>

                    <div class="modal-footer justify-content-end">
                        <button type="button" class="btn btn-success" id="btn_save_all" onclick="prepareDataForSubmission()">
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
// แสดง/ซ่อนช่องรายละเอียดเมื่อเลือก "อื่นๆ"
document.addEventListener('DOMContentLoaded', function() {
    var otherCheckbox = document.getElementById('build_other');
    var otherDetailRow = document.getElementById('building_other_detail_row');
    
    if (otherCheckbox && otherDetailRow) {
        otherCheckbox.addEventListener('change', function() {
            otherDetailRow.style.display = this.checked ? 'flex' : 'none';
        });
    }
});

// ===== ฟังก์ชันสำหรับ Section 10: บันทึกการตรวจเก็บวัตถุพยาน (Property) =====
var measurementCounterProperty = 1;

function addMeasurementCardProperty() {
    var idx = measurementCounterProperty;
    var html = `
        <div class="measurement-card-property card mb-3 shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small text-muted">1. รายการวัตถุพยาน</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="measurement_item_property[]">
                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">2. จำนวน</label>
                        <input type="text" class="form-control" name="measurement_quantity_property[]" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small text-muted">3. บริเวณที่ตรวจพบ</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="measurement_area_property[]">
                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">4. ป้ายหมายเลข</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="measurement_label_number_property[]">
                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted">5. การบรรจุหีบ</label>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="measurement_package_plastic_check_prop_${idx}" value="1" onchange="togglePackageInputProperty(this, 'plastic_prop_${idx}')">
                                        <label class="form-check-label">พลาสติก</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="measurement_package_plastic_text_prop_${idx}" id="package_plastic_prop_${idx}" disabled>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="measurement_package_paper_check_prop_${idx}" value="1" onchange="togglePackageInputProperty(this, 'paper_prop_${idx}')">
                                        <label class="form-check-label">กระดาษ</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="measurement_package_paper_text_prop_${idx}" id="package_paper_prop_${idx}" disabled>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="measurement_package_other_check_prop_${idx}" value="1" onchange="togglePackageInputProperty(this, 'other_prop_${idx}')">
                                        <label class="form-check-label">อื่นๆ</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm" name="measurement_package_other_text_prop_${idx}" id="package_other_prop_${idx}" disabled>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted">6. การดำเนินการเกี่ยวกับวัตถุพยาน</label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                        <input class="form-check-input" type="checkbox" name="measurement_action_return_check_prop_${idx}" value="1" onchange="toggleActionInputProperty(this, 'return_prop_${idx}')">
                                        <label class="form-check-label">ส่งคืนพงส.</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_return_text_prop_${idx}" id="action_return_prop_${idx}" disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                        <input class="form-check-input" type="checkbox" name="measurement_action_other_check_prop_${idx}" value="1" onchange="toggleActionInputProperty(this, 'other_action_prop_${idx}')">
                                        <label class="form-check-label">อื่นๆ</label>
                                    </div>
                                    <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_other_text_prop_${idx}" id="action_other_action_prop_${idx}" disabled>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small text-muted">7. หมายเหตุ</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="measurement_remark_property[]">
                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small text-muted">8. การตรวจพิสูจน์</label>
                        <select class="form-select" name="measurement_forensic_unit_property[]">
                            <option value="" selected>-- กรุณาเลือก --</option>
                            <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                            <option value="bio_dna">กลุ่มงานตรวจชีววิทยาและดีเอ็นเอ</option>
                            <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                            <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                            <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                            <option value="document">กลุ่มงานตรวจเอกสาร</option>
                            <option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option>
                            <option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMeasurementCardProperty(this)">
                            <i class="fas fa-trash me-1"></i> ลบรายการนี้
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.getElementById('measurement_container_property').insertAdjacentHTML('beforeend', html);
    measurementCounterProperty++;
}

function removeMeasurementCardProperty(btn) {
    var card = btn.closest('.measurement-card-property');
    if (card) {
        card.remove();
    }
}

function togglePackageInputProperty(checkbox, targetId) {
    var input = document.getElementById('package_' + targetId);
    if (input) {
        input.disabled = !checkbox.checked;
        if (!checkbox.checked) {
            input.value = '';
        }
    }
}

function toggleActionInputProperty(checkbox, targetId) {
    var input = document.getElementById('action_' + targetId);
    if (input) {
        input.disabled = !checkbox.checked;
        if (!checkbox.checked) {
            input.value = '';
        }
    }
}
</script>
