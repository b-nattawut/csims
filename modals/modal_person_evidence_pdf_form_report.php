<?php
/**
 * Modal: รายงานการตรวจเก็บวัตถุพยานที่บุคคล (ฟอร์มเสมือน PDF)
 * complaints_type = '08'
 * Modal ID: personEvidenceFormPdfModal   Form ID: personEvidenceFormPdf
 */

$peprPoliceStationOptions = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qryPeprPS = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stmtPeprPS = $pdo->query($qryPeprPS);
    while ($rowPS = $stmtPeprPS->fetch(PDO::FETCH_ASSOC)) {
        $peprPoliceStationOptions .= '<option value="' . htmlspecialchars($rowPS['station_name']) . '">' . htmlspecialchars($rowPS['station_name']) . '</option>';
    }
}

$peprInspectorOptions = '<option value="" selected disabled>-- เลือก --</option>';
if (isset($pdo)) {
    $qryPeprInsp = "SELECT t1.user_id,
                           CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname,
                           IFNULL(t3.position_name, '-') AS position_name
                    FROM user_profile t1
                    LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                    LEFT JOIN user_position t3 ON t1.position_id = t3.position_id
                    ORDER BY t1.user_id DESC";
    $stmtPeprInsp = $pdo->query($qryPeprInsp);
    while ($rowInsp = $stmtPeprInsp->fetch(PDO::FETCH_ASSOC)) {
        $peprInspectorOptions .= '<option value="' . htmlspecialchars($rowInsp['user_id']) . '" data-position="' . htmlspecialchars($rowInsp['position_name']) . '" data-fullname="' . htmlspecialchars($rowInsp['fullname']) . '">' . htmlspecialchars($rowInsp['fullname']) . '</option>';
    }
}

$peprTodayDate = date('Y-m-d');
?>

<style>
#personEvidenceFormPdfModal .pepr-body { background: #bbb; }
#personEvidenceFormPdfModal .pepr-page {
    width: 210mm; min-height: 297mm; margin: 16px auto; padding: 12mm 15mm 10mm 15mm;
    background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.25); position: relative;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; color: #000;
    display: flex; flex-direction: column; page-break-after: always;
}
#personEvidenceFormPdfModal .pepr-page:last-child { page-break-after: auto; }
#personEvidenceFormPdfModal .fr { display: flex; flex-wrap: wrap; align-items: baseline; line-height: 1.9; width: 100%; }
#personEvidenceFormPdfModal .fl { font-size: 14px; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#personEvidenceFormPdfModal .fl-b { font-size: 14px; font-weight: 700; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#personEvidenceFormPdfModal .i1 { padding-left: 20px; }
#personEvidenceFormPdfModal .i2 { padding-left: 40px; }
#personEvidenceFormPdfModal .sec-heading { font-weight: 700; font-size: 14px; margin-top: 2px; margin-bottom: 1px; }
#personEvidenceFormPdfModal .form-title { text-decoration: underline; text-align: center; font-size: 15px; font-weight: 700; margin: 6px auto 4px auto; }
#personEvidenceFormPdfModal .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0; }
#personEvidenceFormPdfModal .form-footer { margin-top: auto; padding-top: 6px; font-size: 10px; color: #333; display: flex; justify-content: space-between; }
#personEvidenceFormPdfModal .form-footer-right { text-align: right; white-space: nowrap; line-height: 1.4; }
#personEvidenceFormPdfModal .signature-block { margin-top: 30px; text-align: center; padding-left: 50%; line-height: 2.1; }
#personEvidenceFormPdfModal .header-note { font-size: 11px; line-height: 1.4; margin-bottom: 4px; }
#personEvidenceFormPdfModal .pepr-inp,
#personEvidenceFormPdfModal .pepr-inp-s,
#personEvidenceFormPdfModal .pepr-inp-m,
#personEvidenceFormPdfModal .pepr-inp-l,
#personEvidenceFormPdfModal .pepr-select {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; margin: 0 4px; color: #000;
}
#personEvidenceFormPdfModal .pepr-inp { min-width: 40px; flex: 1; }
#personEvidenceFormPdfModal .pepr-inp-s { width: 60px; max-width: 80px; text-align: center; flex: 0 0 auto; }
#personEvidenceFormPdfModal .pepr-inp-m { flex: 0 1 140px; min-width: 110px; text-align: center; }
#personEvidenceFormPdfModal .pepr-inp-l { flex: 1; min-width: 160px; }
#personEvidenceFormPdfModal .pepr-select { flex: 1; min-width: 120px; cursor: pointer; }
#personEvidenceFormPdfModal .pepr-inp:focus,
#personEvidenceFormPdfModal .pepr-inp-s:focus,
#personEvidenceFormPdfModal .pepr-inp-m:focus,
#personEvidenceFormPdfModal .pepr-inp-l:focus,
#personEvidenceFormPdfModal .pepr-select:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }
#personEvidenceFormPdfModal .ck { display: inline-flex; align-items: center; margin: 0 6px; font-size: 14px; white-space: nowrap; }
#personEvidenceFormPdfModal .pepr-cb {
    -webkit-appearance: none; appearance: none; width: 14px; height: 14px; margin-right: 3px;
    cursor: pointer; position: relative; top: 1px; border: 1.5px solid #000; background: #fff;
}
#personEvidenceFormPdfModal .pepr-cb:checked::after {
    content: '✓'; position: absolute; top: -4px; left: 1px; font-size: 13px; font-weight: 700; line-height: 1;
}
#personEvidenceFormPdfModal .pepr-add-btn {
    font-size: 12px; padding: 1px 8px; border: 1px dashed #0d6efd; color: #0d6efd;
    background: transparent; border-radius: 4px; cursor: pointer; margin-left: 20px; margin-top: 2px;
}
#personEvidenceFormPdfModal .pepr-add-btn:hover { background: rgba(13,110,253,.08); }
#personEvidenceFormPdfModal .pepr-del-btn {
    font-size: 11px; padding: 0 5px; border: none; color: #dc3545; background: transparent; cursor: pointer; margin-left: 4px;
}
#personEvidenceFormPdfModal .pepr-line-input { width: calc(100% - 40px); margin-left: 40px; }
#personEvidenceFormPdfModal .pepr-full-line { width: 100%; }
#personEvidenceFormPdfModal .pepr-ta {
    border: none; background: transparent; outline: none; resize: none;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.9;
    padding: 0 4px; color: #000; width: 100%; margin: 0 4px 2px 0;
    background-image: linear-gradient(transparent 25px, #888 25px);
    background-size: 100% 26px; background-position: 0 0;
}
#personEvidenceFormPdfModal .pepr-center { text-align: center; }
#personEvidenceFormPdfModal .pepr-hidden { display: none; }
#personEvidenceFormPdfModal .select2-container { min-width: 160px; }
#personEvidenceFormPdfModal .select2-container--bootstrap-5 .select2-selection {
    min-height: 24px !important; height: 24px !important; padding: 0 4px !important;
    border: none !important; border-bottom: 1px dotted #888 !important; border-radius: 0 !important;
    background: transparent !important;
}
#personEvidenceFormPdfModal .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    padding: 0 !important; line-height: 24px !important; font-size: 14px !important; color: #000 !important;
}
@media print {
    body > *:not(#personEvidenceFormPdfModal),
    .navbar, .sidebar, .main-content, #status-banner,
    .modal-backdrop, .swal2-container,
    #personEvidenceFormPdfModal .modal-header,
    #personEvidenceFormPdfModal .modal-footer {
        display: none !important;
    }
    #personEvidenceFormPdfModal,
    #personEvidenceFormPdfModal .modal-dialog,
    #personEvidenceFormPdfModal .modal-content,
    #personEvidenceFormPdfModal .modal-body {
        position: static !important; display: block !important;
        width: auto !important; max-width: none !important;
        max-height: none !important; height: auto !important;
        overflow: visible !important; margin: 0 !important;
        padding: 0 !important; background: #fff !important;
        border: none !important; box-shadow: none !important;
        transform: none !important; opacity: 1 !important;
    }
    @page { size: A4 portrait; margin: 0; }
    #personEvidenceFormPdfModal .pepr-page {
        width: 100% !important; min-height: auto !important;
        height: auto !important; margin: 0 !important;
        padding: 10mm 12mm 8mm 12mm !important;
        box-shadow: none !important; overflow: visible !important;
        page-break-after: always; page-break-inside: auto;
    }
    #personEvidenceFormPdfModal .pepr-page:last-of-type { page-break-after: auto; }
}
</style>

<div class="modal fade" id="personEvidenceFormPdfModal" aria-labelledby="personEvidenceFormPdfModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 240mm;">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="personEvidenceFormPdfModalLabel">รายงานการตรวจเก็บวัตถุพยานที่บุคคลaaa</h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pepr-body p-0" style="max-height: 80vh; overflow-y: auto; position:relative;">
                <div id="peprLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track"><div class="csims-bar-fill"></div></div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>

                <form id="personEvidenceFormPdf" novalidate>
                    <input type="hidden" id="pepf_receiveNoti_id" name="receiveNoti_id_ev8">
                    <input type="hidden" id="pepf_doc_no" name="doc_no_ev8">
                    <input type="hidden" id="pepf_report_no" name="report_no_ev8">
                    <input type="hidden" id="pepf_case_no" name="pepf_case_no">
                    <input type="hidden" id="pepf_signer_id" name="pepf_signer_id">

                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="editInfoEV8Pdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountEV8Pdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToStdFormEV8" checked style="width: 3rem; height: 1.5rem; cursor: pointer;"
                                       onchange="if(!this.checked){ this.checked=true; switchToPersonEvidenceStdForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToStdFormEV8" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

                    <div class="pepr-page">
                        <div class="header-note">บัญชีแนบท้ายคำสั่ง สำนักงานพิสูจน์หลักฐานตำรวจ ที่ ๘๔๘/๒๕๖๑ ลงวันที่ ๓๑ ธันวาคม ๒๕๖๑</div>
                        <div class="page-header">
                            <div style="font-size:13px; line-height:1.6;">
                                <u>รายงานการตรวจเก็บวัตถุพยานที่</u>
                                <input type="text" class="pepr-inp pepr-inp-m" name="pepf_report_ref" id="pepf_report_ref" style="display:inline-block; max-width:140px;">
                                <span>/25</span>
                                <input type="text" class="pepr-inp pepr-inp-s" name="pepf_report_year" id="pepf_report_year" style="display:inline-block; max-width:50px;">
                            </div>
                            <div style="font-size:13px; text-align:right;"><span class="pepr-cur-page">1</span>/<span class="pepr-total-pages">2</span></div>
                        </div>
                        <div class="fr"><span class="fl">หน่วยงาน</span><input type="text" class="pepr-inp" name="pepf_unit_name" id="pepf_unit_name"></div>

                        <div class="form-title">รายงานการตรวจเก็บวัตถุพยานที่บุคคล</div>

                        <div class="sec-heading">๑. <u>สิ่งที่ได้รับจาก /การรับแจ้งเหตุ</u></div>
                        <div class="fr i1">
                            <span class="fl">เมื่อวันที่</span>
                            <input type="date" class="pepr-inp pepr-inp-m" name="pepf_receive_date" id="pepf_receive_date" value="<?= $peprTodayDate ?>">
                            <span class="fl">เวลา</span>
                            <input type="time" class="pepr-inp pepr-inp-m" name="pepf_receive_time" id="pepf_receive_time">
                            <span class="fl">น. กลุ่มงานตรวจที่เกิดเหตุ กองพิสูจน์หลักฐานกลาง/</span>
                        </div>
                        <div class="fr i1">
                            <span class="fl">ศูนย์พิสูจน์หลักฐาน</span>
                            <input type="text" class="pepr-inp pepr-inp-m" name="pepf_center_name" id="pepf_center_name">
                            <span class="fl">/พิสูจน์หลักฐานจังหวัด</span>
                            <input type="text" class="pepr-inp pepr-inp-m" name="pepf_province_name" id="pepf_province_name">
                        </div>
                        <div class="fr i1">
                            <label class="ck"><input type="checkbox" class="pepr-cb" name="pepf_unit_type_check[]" value="กองพิสูจน์หลักฐานกลาง">กองพิสูจน์หลักฐานกลาง</label>
                            <label class="ck"><input type="checkbox" class="pepr-cb" name="pepf_unit_type_check[]" value="ศูนย์พิสูจน์หลักฐาน">ศูนย์พิสูจน์หลักฐาน</label>
                            <label class="ck"><input type="checkbox" class="pepr-cb" name="pepf_unit_type_check[]" value="พิสูจน์หลักฐานจังหวัด">พิสูจน์หลักฐานจังหวัด</label>
                        </div>
                        <div class="fr i1">
                            <span class="fl">ได้รับแจ้ง (</span>
                            <label class="ck"><input type="checkbox" class="pepr-cb" name="pepf_notify_method[]" value="ทางหนังสือ">ตามหนังสือ</label>
                            <span class="fl">/</span>
                            <label class="ck"><input type="checkbox" class="pepr-cb" name="pepf_notify_method[]" value="ทางโทรศัพท์">ทางโทรศัพท์</label>
                            <span class="fl">/</span>
                            <label class="ck"><input type="checkbox" class="pepr-cb" name="pepf_notify_method[]" value="ทางวิทยุสื่อสาร">วิทยุสื่อสาร</label>
                            <span class="fl">/</span>
                            <label class="ck"><input type="checkbox" class="pepr-cb" name="pepf_notify_method[]" value="อื่นๆ">อื่นๆ</label>
                            <span class="fl">)</span>
                            <input type="text" class="pepr-inp" name="pepf_notify_method_other_text" id="pepf_notify_method_other_text" style="display:none; max-width:160px;">
                        </div>
                        <div class="fr i1">
                            <span class="fl">จากสถานีตำรวจนครบาล/สถานีตำรวจภูธร</span>
                            <select class="pepr-select" name="pepf_police_station" id="pepf_police_station"><?= $peprPoliceStationOptions ?></select>
                        </div>
                        <div class="fr i1">
                            <span class="fl">ที่</span>
                            <input type="text" class="pepr-inp pepr-inp-m" name="pepf_document_no" id="pepf_document_no">
                            <span class="fl">ลงวันที่</span>
                            <input type="date" class="pepr-inp pepr-inp-m" name="pepf_document_date" id="pepf_document_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="fr i1">
                            <span class="fl">ในคดี</span>
                            <input type="text" class="pepr-inp pepr-inp-m" name="pepf_case_no_display" id="pepf_case_no_display" readonly>
                            <span class="fl">สถานที่เกิดเหตุ</span>
                        </div>
                        <div class="fr i1"><input type="text" class="pepr-inp pepr-full-line pepr-location-auto" name="pepf_incident_location" id="pepf_incident_location"></div>
                        <div class="fr i1"><input type="text" class="pepr-inp pepr-full-line pepr-location-auto" name="pepf_incident_location_2" id="pepf_incident_location_2"></div>
                        <div class="fr i1">
                            <span class="fl">เหตุเกิดเมื่อวันที่</span>
                            <input type="date" class="pepr-inp pepr-inp-m" name="pepf_incident_date" id="pepf_incident_date">
                            <span class="fl">เวลาประมาณ</span>
                            <input type="time" class="pepr-inp pepr-inp-m" name="pepf_incident_time" id="pepf_incident_time">
                            <span class="fl">น.</span>
                        </div>
                        <div class="fr i1">
                            <span class="fl">มี</span>
                            <input type="text" class="pepr-inp" name="pepf_investigator_name" id="pepf_investigator_name">
                            <span class="fl">เป็นพนักงานสอบสวน</span>
                            <span class="fl" style="margin-left:8px;">ขอส่ง</span>
                            <input type="text" class="pepr-inp pepr-inp-m" name="pepf_send_request" id="pepf_send_request">
                        </div>
                        <div id="pepf_person_items_container">
                            <div class="fr i2 pepr-person-item-row">
                                <span class="fl pepr-item-no">๑.๑</span>
                                <select class="pepr-select" name="pepf_person_prefix[]" style="max-width:120px;">
                                    <option value="" selected disabled>คำนำหน้า</option>
                                    <option value="นาย">นาย</option>
                                    <option value="นาง">นาง</option>
                                    <option value="นางสาว">นางสาว</option>
                                    <option value="อื่นๆ">อื่นๆ</option>
                                </select>
                                <input type="text" class="pepr-inp" name="pepf_person_name[]">
                                <button type="button" class="pepr-del-btn" onclick="this.closest('.pepr-person-item-row').remove(); peprRenumberPersonItems();">×</button>
                            </div>
                        </div>
                        <button type="button" class="pepr-add-btn" onclick="peprAddPersonItem()">+ เพิ่มบุคคล</button>

                        <div class="sec-heading" style="margin-top:8px;">๒. <u>จุดประสงค์ในการตรวจ</u></div>
                        <div class="fr i1">
                            <span class="fl">เพื่อทำการตรวจเก็บ</span>
                            <input type="text" class="pepr-inp" name="pepf_purpose_detail" id="pepf_purpose_detail">
                            <span class="fl">บุคคลดังกล่าว</span>
                        </div>

                        <div class="sec-heading" style="margin-top:8px;">๓. <u>ผลการตรวจ</u></div>
                        <div class="fr i1">
                            <span class="fl">ได้ทำการตรวจเก็บ</span>
                            <span class="fl">ที่</span>
                            <input type="text" class="pepr-inp pepr-inp-m" name="pepf_inspect_location" id="pepf_inspect_location">
                        </div>
                        <div class="fr i1">
                            <span class="fl">เมื่อวันที่</span>
                            <input type="date" class="pepr-inp pepr-inp-m" name="pepf_inspect_date" id="pepf_inspect_date">
                            <span class="fl">เวลาประมาณ</span>
                            <input type="time" class="pepr-inp pepr-inp-m" name="pepf_inspect_time" id="pepf_inspect_time">
                            <span class="fl">น.</span>
                        </div>

                        <div class="fr i1" style="margin-top:4px;">
                            <span class="fl-b">๓.๑ ข้อมูลส่วนบุคคล</span>
                            <span class="fl" style="font-weight:normal;">(ให้ระบุ เช่น หมายเลขบัตรประจำตัวประชาชน/ แบบหนังสือเดินทาง/ ความสูง/ คำหนี้รูปพรรณ)</span>
                        </div>
                        <div id="pepf_person_info_container">
                            <div class="fr i2 pepr-person-info-row">
                                <span class="fl pepr-info-no">๓.๑.๑</span>
                                <select class="pepr-select" name="pepf_info_prefix[]" style="max-width:110px;">
                                    <option value="" selected disabled>คำนำหน้า</option>
                                    <option value="นาย">นาย</option>
                                    <option value="นาง">นาง</option>
                                    <option value="นางสาว">นางสาว</option>
                                    <option value="อื่นๆ">อื่นๆ</option>
                                </select>
                                <input type="text" class="pepr-inp" name="pepf_info_fullname[]">
                                <button type="button" class="pepr-del-btn" onclick="this.closest('.pepr-person-info-row').remove(); peprRenumberPersonInfo();">×</button>
                                <div class="fr pepr-line-input">
                                    <span class="fl">บัตรประชาชน</span><input type="text" class="pepr-inp pepr-inp-m" name="pepf_info_id_card[]">
                                    <span class="fl">หนังสือเดินทาง</span><input type="text" class="pepr-inp pepr-inp-m" name="pepf_info_passport[]">
                                </div>
                                <div class="fr pepr-line-input">
                                    <span class="fl">สูง</span><input type="text" class="pepr-inp pepr-inp-s" name="pepf_info_height[]">
                                    <span class="fl">ซม.</span><span class="fl">สีผิว</span><input type="text" class="pepr-inp pepr-inp-m" name="pepf_info_skin[]">
                                    <span class="fl">อายุ</span><input type="text" class="pepr-inp pepr-inp-s" name="pepf_info_age[]">
                                    <span class="fl">มือที่ถนัด</span>
                                    <select class="pepr-select" name="pepf_info_hand[]" style="max-width:120px;">
                                        <option value="" selected disabled>เลือก</option>
                                        <option value="ขวา">ขวา</option>
                                        <option value="ซ้าย">ซ้าย</option>
                                        <option value="ทั้งสองมือ">ทั้งสองมือ</option>
                                    </select>
                                </div>
                                <div class="fr pepr-line-input">
                                    <span class="fl">คำหนี้รูปพรรณ</span><input type="text" class="pepr-inp" name="pepf_info_feature[]">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="pepr-add-btn" onclick="peprAddPersonInfo()">+ เพิ่มข้อมูลบุคคล</button>

                        <div class="fr i1" style="margin-top:4px;"><span class="fl-b">๓.๒ รายละเอียดวัตถุพยานที่ทำการตรวจเก็บ</span></div>
                        <div id="pepf_evidence_detail_container">
                            <div class="fr i2 pepr-evidence-row">
                                <span class="fl pepr-evidence-no">๓.๒.๑</span>
                                <input type="text" class="pepr-inp" name="pepf_evidence_desc[]">
                                <button type="button" class="pepr-del-btn" onclick="this.closest('.pepr-evidence-row').remove(); peprRenumberEvidenceDetails();">×</button>
                                <div class="fr pepr-line-input">
                                    <span class="fl">กลุ่มตรวจพิสูจน์</span>
                                    <select class="pepr-select lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="max-width:220px;">
                                        <option value="">-- กลุ่มตรวจพิสูจน์ (เลือกได้หลายข้อ) --</option>
                                        <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                                        <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                        <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                        <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                        <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                        <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                        <option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                                    </select>
                                    <input type="hidden" class="lab-unit-value" name="pepf_lab_unit[]" value="">
                                    <span class="fl">จำนวน</span><input type="text" class="pepr-inp pepr-inp-s" name="pepf_evidence_qty[]">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="pepr-add-btn" onclick="peprAddEvidenceDetail()">+ เพิ่มรายการวัตถุพยาน</button>

                        <div class="form-footer">
                            <div></div>
                            <div class="form-footer-right">บัญชีแนบท้ายคำสั่ง สพฐ.ตร.<br>ที่ ๘๔๘/๒๕๖๑</div>
                        </div>
                    </div>

                    <div class="pepr-page">
                        <div class="header-note">บัญชีแนบท้ายคำสั่ง สำนักงานพิสูจน์หลักฐานตำรวจ ที่ ๘๔๘/๒๕๖๑ ลงวันที่ ๓๑ ธันวาคม ๒๕๖๑</div>
                        <div class="page-header">
                            <div></div>
                            <div style="font-size:13px; text-align:right;"><span class="pepr-cur-page">2</span>/<span class="pepr-total-pages">2</span></div>
                        </div>

                        <div id="pepr_evidence_detail_continued"></div>

                        <div class="fr" style="margin-top:4px;"><span class="fl-b">๓.๓ การดำเนินการเกี่ยวกับวัตถุพยาน</span></div>
                        <div class="fr i1">
                            <span class="fl">ได้ให้</span>
                            <input type="text" class="pepr-inp" name="pepf_witness_name" id="pepf_witness_name">
                            <span class="fl">ลงลายมือชื่อในแบบ</span>
                            <input type="text" class="pepr-inp" name="pepf_witness_form" id="pepf_witness_form">
                        </div>
                        <div class="fr i1">
                            <input type="text" class="pepr-inp" name="pepf_witness_detail" id="pepf_witness_detail">
                            <span class="fl">ให้เป็นหลักฐาน และได้(</span>
                            <label class="ck"><input type="checkbox" class="pepr-cb" name="pepf_handover_method_check[]" value="นำส่ง">นำส่ง</label>
                            <span class="fl">/</span>
                            <label class="ck"><input type="checkbox" class="pepr-cb" name="pepf_handover_method_check[]" value="ส่งมอบ">ส่งมอบ</label>
                            <span class="fl">)</span>
                            <input type="text" class="pepr-inp pepr-inp-m" name="pepf_handover_method_detail" id="pepf_handover_method_detail">
                        </div>
                        <div class="fr i1">
                            <span class="fl">วัตถุพยานตามข้อ</span><input type="text" class="pepr-inp pepr-inp-s" name="pepf_handover_item_ref" id="pepf_handover_item_ref">
                            <span class="fl">ให้</span><input type="text" class="pepr-inp" name="pepf_handover_to" id="pepf_handover_to">
                        </div>
                        <div class="fr i1">
                            <input type="text" class="pepr-inp" name="pepf_handover_purpose" id="pepf_handover_purpose">
                            <span class="fl">เพื่อดำเนินการต่อไป</span>
                        </div>
                        <div class="fr i1"><input type="text" class="pepr-inp pepr-full-line" name="pepf_handover_more_1"></div>
                        <div class="fr i1"><input type="text" class="pepr-inp pepr-full-line" name="pepf_handover_more_2"></div>
                        <div class="fr i1"><input type="text" class="pepr-inp pepr-full-line" name="pepf_handover_more_3"></div>
                        <div class="fr i1"><input type="text" class="pepr-inp pepr-full-line" name="pepf_handover_more_4"></div>

                        <div class="signature-block">
                            <div>(ลงชื่อ)&nbsp;.......................................................&nbsp;</div>
                            <div>
                                (&nbsp;
                                <select class="pepr-select pepr-center" name="pepf_signer_fullname" id="pepf_signer_fullname" style="display:inline-block; width:240px;">
                                    <option value="" selected disabled>-- เลือกผู้ลงนาม --</option>
                                    <?= $peprInspectorOptions ?>
                                </select>
                                &nbsp;)
                            </div>
                            <div>(ตำแหน่ง)&nbsp;<input type="text" class="pepr-inp pepr-center" name="pepf_signer_position" id="pepf_signer_position" style="display:inline-block; width:240px;"></div>
                            <div style="margin-top:8px;">
                                <input type="text" class="pepr-inp pepr-inp-s pepr-center" name="pepf_sign_day" id="pepf_sign_day" style="display:inline-block; max-width:40px;"> /
                                <input type="text" class="pepr-inp pepr-inp-m pepr-center" name="pepf_sign_month" id="pepf_sign_month" style="display:inline-block; max-width:90px;"> /
                                <input type="text" class="pepr-inp pepr-inp-s pepr-center" name="pepf_sign_year" id="pepf_sign_year" style="display:inline-block; max-width:60px;">
                                <input type="hidden" name="pepf_sign_date" id="pepf_sign_date" value="<?= $peprTodayDate ?>">
                            </div>
                        </div>

                        <div class="fr i1 pepr-hidden">
                            <span class="fl">ผู้ตรวจ</span>
                            <div id="pepf_inspector_container">
                                <div class="pepr-inspector-row fr">
                                    <span class="fl pepr-inspector-no">4.1</span>
                                    <select class="pepr-select" name="pepf_inspector_id[]"><?= $peprInspectorOptions ?></select>
                                </div>
                            </div>
                            <button type="button" class="pepr-add-btn" onclick="peprAddInspector()">+ เพิ่มผู้ตรวจ</button>
                        </div>

                        <div class="form-footer">
                            <div></div>
                            <div class="form-footer-right">บัญชีแนบท้ายคำสั่ง สพฐ.ตร.<br>ที่ ๘๔๘/๒๕๖๑</div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_ev8_pdf" onclick="prepareDataForSubmissionPersonEvidence()">
                    <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger btn-sm js-close-modal" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> ยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    function toThaiDigits(value) {
        var map = ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'];
        return String(value).replace(/\d/g, function(d) { return map[parseInt(d, 10)]; });
    }

    window.pepfUpdatePageNumbers = function() {
        document.querySelectorAll('#personEvidenceFormPdfModal .pepr-page').forEach(function(page, idx) {
            var cur = page.querySelector('.pepr-cur-page');
            var total = page.querySelector('.pepr-total-pages');
            if (cur) cur.textContent = String(idx + 1);
            if (total) total.textContent = '2';
        });
    };

    window.pepfRenderPhotosFromStore = function() {};

    window.pepfSyncReportNo = function() {
        var reportNoVal = document.getElementById('pepf_report_no') ? document.getElementById('pepf_report_no').value : '';
        var docNoVal = document.getElementById('pepf_doc_no') ? document.getElementById('pepf_doc_no').value : '';
        var caseNoVal = document.getElementById('pepf_case_no') ? document.getElementById('pepf_case_no').value : '';
        var reportRef = '';
        var year = '';
        if (reportNoVal) {
            var parts = reportNoVal.toString().trim().split('/');
            reportRef = (parts[0] || '').trim();
            year = (parts[1] || '').trim();
            if (year.length > 2) year = year.slice(-2);
        }
        var refEl = document.getElementById('pepf_report_ref');
        var yearEl = document.getElementById('pepf_report_year');
        if (refEl && !refEl.value) refEl.value = reportRef;
        if (yearEl && !yearEl.value) yearEl.value = year;
        var caseEl = document.getElementById('pepf_case_no_display');
        if (caseEl) caseEl.value = caseNoVal || docNoVal || '';
    };

    window.pepfToggleNotifyOther = function() {
        var checked = !!document.querySelector('#personEvidenceFormPdfModal input[name="pepf_notify_method[]"][value="อื่นๆ"]:checked');
        var otherEl = document.getElementById('pepf_notify_method_other_text');
        if (!otherEl) return;
        otherEl.style.display = checked ? '' : 'none';
        otherEl.disabled = !checked;
        if (!checked) otherEl.value = '';
    };

    window.peprAddPersonItem = function() {
        var container = document.getElementById('pepf_person_items_container');
        if (!container) return;
        var row = document.createElement('div');
        row.className = 'fr i2 pepr-person-item-row';
        row.innerHTML = '<span class="fl pepr-item-no"></span>' +
            '<select class="pepr-select" name="pepf_person_prefix[]" style="max-width:120px;">' +
            '<option value="" selected disabled>คำนำหน้า</option><option value="นาย">นาย</option><option value="นาง">นาง</option><option value="นางสาว">นางสาว</option><option value="อื่นๆ">อื่นๆ</option>' +
            '</select>' +
            '<input type="text" class="pepr-inp" name="pepf_person_name[]">' +
            '<button type="button" class="pepr-del-btn" onclick="this.closest(\'.pepr-person-item-row\').remove(); peprRenumberPersonItems();">×</button>';
        container.appendChild(row);
        peprRenumberPersonItems();
    };

    window.peprRenumberPersonItems = function() {
        document.querySelectorAll('#pepf_person_items_container .pepr-person-item-row').forEach(function(row, idx) {
            var no = row.querySelector('.pepr-item-no');
            if (no) no.textContent = '๑.' + toThaiDigits(idx + 1);
        });
    };

    window.peprAddPersonInfo = function() {
        var container = document.getElementById('pepf_person_info_container');
        if (!container) return;
        var row = document.createElement('div');
        row.className = 'fr i2 pepr-person-info-row';
        row.innerHTML = '<span class="fl pepr-info-no"></span>' +
            '<select class="pepr-select" name="pepf_info_prefix[]" style="max-width:110px;">' +
            '<option value="" selected disabled>คำนำหน้า</option><option value="นาย">นาย</option><option value="นาง">นาง</option><option value="นางสาว">นางสาว</option><option value="อื่นๆ">อื่นๆ</option>' +
            '</select>' +
            '<input type="text" class="pepr-inp" name="pepf_info_fullname[]">' +
            '<button type="button" class="pepr-del-btn" onclick="this.closest(\'.pepr-person-info-row\').remove(); peprRenumberPersonInfo();">×</button>' +
            '<div class="fr pepr-line-input"><span class="fl">บัตรประชาชน</span><input type="text" class="pepr-inp pepr-inp-m" name="pepf_info_id_card[]"><span class="fl">หนังสือเดินทาง</span><input type="text" class="pepr-inp pepr-inp-m" name="pepf_info_passport[]"></div>' +
            '<div class="fr pepr-line-input"><span class="fl">สูง</span><input type="text" class="pepr-inp pepr-inp-s" name="pepf_info_height[]"><span class="fl">ซม.</span><span class="fl">สีผิว</span><input type="text" class="pepr-inp pepr-inp-m" name="pepf_info_skin[]"><span class="fl">อายุ</span><input type="text" class="pepr-inp pepr-inp-s" name="pepf_info_age[]"><span class="fl">มือที่ถนัด</span><select class="pepr-select" name="pepf_info_hand[]" style="max-width:120px;"><option value="" selected disabled>เลือก</option><option value="ขวา">ขวา</option><option value="ซ้าย">ซ้าย</option><option value="ทั้งสองมือ">ทั้งสองมือ</option></select></div>' +
            '<div class="fr pepr-line-input"><span class="fl">คำหนี้รูปพรรณ</span><input type="text" class="pepr-inp" name="pepf_info_feature[]"></div>';
        container.appendChild(row);
        peprRenumberPersonInfo();
    };

    window.peprRenumberPersonInfo = function() {
        document.querySelectorAll('#pepf_person_info_container .pepr-person-info-row').forEach(function(row, idx) {
            var no = row.querySelector('.pepr-info-no');
            if (no) no.textContent = '๓.๑.' + toThaiDigits(idx + 1);
        });
    };

    window.peprAddEvidenceDetail = function() {
        var container = document.getElementById('pepf_evidence_detail_container');
        if (!container) return;
        var row = document.createElement('div');
        row.className = 'fr i2 pepr-evidence-row';
        row.innerHTML = '<span class="fl pepr-evidence-no"></span>' +
            '<input type="text" class="pepr-inp" name="pepf_evidence_desc[]">' +
            '<button type="button" class="pepr-del-btn" onclick="this.closest(\'.pepr-evidence-row\').remove(); peprRenumberEvidenceDetails();">×</button>' +
            '<div class="fr pepr-line-input"><span class="fl">กลุ่มตรวจพิสูจน์</span><select class="pepr-select lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="max-width:220px;"><option value="">-- กลุ่มตรวจพิสูจน์ (เลือกได้หลายข้อ) --</option><option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option><option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option><option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option><option value="drug">กลุ่มงานตรวจยาเสพติด</option><option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option><option value="document">กลุ่มงานตรวจเอกสาร</option><option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option></select><input type="hidden" class="lab-unit-value" name="pepf_lab_unit[]" value=""><span class="fl">จำนวน</span><input type="text" class="pepr-inp pepr-inp-s" name="pepf_evidence_qty[]"></div>';
        container.appendChild(row);
        peprRenumberEvidenceDetails();
    };

    window.peprRenumberEvidenceDetails = function() {
        document.querySelectorAll('#pepf_evidence_detail_container .pepr-evidence-row').forEach(function(row, idx) {
            var no = row.querySelector('.pepr-evidence-no');
            if (no) no.textContent = '๓.๒.' + toThaiDigits(idx + 1);
        });
    };

    window.peprAddInspector = function() {
        var container = document.getElementById('pepf_inspector_container');
        if (!container) return;
        var row = document.createElement('div');
        row.className = 'pepr-inspector-row fr';
        row.innerHTML = '<span class="fl pepr-inspector-no"></span><select class="pepr-select" name="pepf_inspector_id[]"><?= str_replace("'", "\'", $peprInspectorOptions) ?></select><button type="button" class="pepr-del-btn" onclick="this.closest(\'.pepr-inspector-row\').remove(); peprRenumberInspectors();">×</button>';
        container.appendChild(row);
        peprRenumberInspectors();
    };

    window.peprRenumberInspectors = function() {
        document.querySelectorAll('#pepf_inspector_container .pepr-inspector-row').forEach(function(row, idx) {
            var no = row.querySelector('.pepr-inspector-no');
            if (no) no.textContent = '4.' + (idx + 1);
        });
    };

    window.pepfAddPersonItem = window.peprAddPersonItem;
    window.pepfRenumberPersonItems = window.peprRenumberPersonItems;
    window.pepfAddPersonInfo = window.peprAddPersonInfo;
    window.pepfRenumberPersonInfo = window.peprRenumberPersonInfo;
    window.pepfAddEvidenceDetail = window.peprAddEvidenceDetail;
    window.pepfRenumberEvidenceDetails = window.peprRenumberEvidenceDetails;
    window.pepfAddInspector = window.peprAddInspector;
    window.pepfRenumberInspectors = window.peprRenumberInspectors;
    window.pepfRefreshAutoWrapGroups = function() {};

    window.syncPersonEvidenceFormData = function(fromFormId, toFormId) {
        var fromForm = document.getElementById(fromFormId);
        var toForm = document.getElementById(toFormId);
        if (!fromForm || !toForm) return;
        var toStdForm = (toFormId === 'incidentCheckListFormPersonEvidence');

        function ensureDynamicRows(selector, count, addFnName) {
            if (!count || count <= 1) return;
            var existing = toForm.querySelectorAll(selector).length;
            while (existing < count) {
                if (typeof addFnName === 'string' && typeof window[addFnName] === 'function') {
                    window[addFnName]();
                } else if (typeof addFnName === 'string' && document.getElementById(addFnName)) {
                    document.getElementById(addFnName).click();
                } else {
                    break;
                }
                existing = toForm.querySelectorAll(selector).length;
            }
        }

        ensureDynamicRows('[name="' + (toStdForm ? 'ev8_person_prefix[]' : 'pepf_person_prefix[]') + '"]', fromForm.querySelectorAll('[name="' + (toStdForm ? 'pepf_person_prefix[]' : 'ev8_person_prefix[]') + '"]').length, toStdForm ? 'addPersonItemEV8' : 'peprAddPersonItem');
        ensureDynamicRows('[name="' + (toStdForm ? 'ev8_info_fullname[]' : 'pepf_info_fullname[]') + '"]', fromForm.querySelectorAll('[name="' + (toStdForm ? 'pepf_info_fullname[]' : 'ev8_info_fullname[]') + '"]').length, toStdForm ? 'addPersonInfoEV8' : 'peprAddPersonInfo');
        ensureDynamicRows('[name="' + (toStdForm ? 'ev8_evidence_desc[]' : 'pepf_evidence_desc[]') + '"]', fromForm.querySelectorAll('[name="' + (toStdForm ? 'pepf_evidence_desc[]' : 'ev8_evidence_desc[]') + '"]').length, toStdForm ? 'addEvidenceDetailEV8' : 'peprAddEvidenceDetail');
        ensureDynamicRows('[name="' + (toStdForm ? 'ev8_inspector_id[]' : 'pepf_inspector_id[]') + '"]', fromForm.querySelectorAll('[name="' + (toStdForm ? 'pepf_inspector_id[]' : 'ev8_inspector_id[]') + '"]').length, toStdForm ? (function(){ return 'btn_add_inspector_ev8'; })() : 'peprAddInspector');

        function mapName(name) {
            if (toStdForm) return name.replace(/^pepf_/, 'ev8_');
            return name.replace(/^ev8_/, 'pepf_');
        }

        var dataMap = {};
        fromForm.querySelectorAll('input, select, textarea').forEach(function(el) {
            var name = el.name;
            if (!name || el.type === 'file' || el.type === 'hidden') return;
            if (el.type === 'checkbox') {
                if (!dataMap[name]) dataMap[name] = { type: 'checkbox', values: [] };
                if (el.checked) dataMap[name].values.push(el.value);
            } else {
                if (!dataMap[name]) dataMap[name] = { type: 'text', values: [] };
                dataMap[name].values.push(el.value || '');
            }
        });

        Object.keys(dataMap).forEach(function(name) {
            var info = dataMap[name];
            var targetName = mapName(name);
            var toEls = toForm.querySelectorAll('[name="' + targetName + '"]');
            if (toEls.length === 0) toEls = toForm.querySelectorAll('[name="' + name + '"]');
            if (toEls.length === 0) return;

            if (info.type === 'checkbox') {
                toEls.forEach(function(el) {
                    el.checked = info.values.indexOf(el.value) !== -1;
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                });
            } else {
                toEls.forEach(function(el, idx) {
                    el.value = info.values[idx] || '';
                });
            }
        });

        var stdOther = document.getElementById('ev8_notify_other_text');
        var pdfOther = document.getElementById('pepf_notify_method_other_text');
        if (fromFormId === 'incidentCheckListFormPersonEvidence' && pdfOther && stdOther) pdfOther.value = stdOther.value || '';
        if (fromFormId === 'personEvidenceFormPdf' && stdOther && pdfOther) {
            stdOther.value = pdfOther.value || '';
            stdOther.style.display = pdfOther.value ? '' : 'none';
            stdOther.disabled = !pdfOther.value;
        }

        if (!toStdForm) {
            var caseHidden = document.getElementById('pepf_case_no');
            var caseDisplay = document.getElementById('pepf_case_no_display');
            if (caseHidden && !caseHidden.value) caseHidden.value = document.getElementById('ev8_case_no') ? document.getElementById('ev8_case_no').value : '';
            if (caseDisplay && !caseDisplay.value) caseDisplay.value = caseHidden ? caseHidden.value : '';
            // Sync unit_type select → PDF checkboxes
            var unitTypeVal = document.getElementById('ev8_unit_type') ? document.getElementById('ev8_unit_type').value : '';
            if (unitTypeVal) {
                var pepfChecks = toForm.querySelectorAll('[name="pepf_unit_type_check[]"]');
                pepfChecks.forEach(function(cb) { cb.checked = (cb.value === unitTypeVal); });
            }
        } else {
            // Sync PDF unit_type checkboxes → std select
            var checkedUnit = fromForm.querySelector('[name="pepf_unit_type_check[]"]:checked');
            if (checkedUnit) {
                var stdSelect = document.getElementById('ev8_unit_type');
                if (stdSelect) stdSelect.value = checkedUnit.value;
            }
        }

        if (typeof window.pepfToggleNotifyOther === 'function') window.pepfToggleNotifyOther();
        if (typeof window.pepfSyncReportNo === 'function') window.pepfSyncReportNo();
    };

    window.switchToPersonEvidencePdfForm = function() {
        syncPersonEvidenceFormData('incidentCheckListFormPersonEvidence', 'personEvidenceFormPdf');
        $('#pepf_receiveNoti_id').val($('#receiveNoti_id_ev8').val());
        $('#pepf_doc_no').val($('#doc_no_ev8').val());
        $('#pepf_report_no').val($('#report_no_ev8').val());
        if (typeof window.pepfSyncReportNo === 'function') window.pepfSyncReportNo();
        // Sync sign date from std form if available
        var stdSignDate = $('#ev8_sign_date').val();
        if (stdSignDate) $('#pepf_sign_date').val(stdSignDate);
        syncSignDatePartsFromDate();

        var stdEl = document.getElementById('addCheckListModalPersonEvidence');
        var stdModal = bootstrap.Modal.getInstance(stdEl);
        if (stdModal) {
            stdEl.addEventListener('hidden.bs.modal', function onHidden() {
                stdEl.removeEventListener('hidden.bs.modal', onHidden);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('personEvidenceFormPdfModal')).show();
            });
            stdModal.hide();
        } else {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('personEvidenceFormPdfModal')).show();
        }
    };

    window.switchToPersonEvidenceStdForm = function() {
        syncPersonEvidenceFormData('personEvidenceFormPdf', 'incidentCheckListFormPersonEvidence');
        $('#receiveNoti_id_ev8').val($('#pepf_receiveNoti_id').val());
        $('#doc_no_ev8').val($('#pepf_doc_no').val());
        $('#report_no_ev8').val($('#pepf_report_no').val());
        $('#receiveNoti_No_ev8').text($('#pepf_doc_no').val() || '');
        $('#receiveNotiReportNo_ev8').text($('#pepf_report_no').val() || '');
        // Sync case_no from PDF hidden → std visible
        var pdfCaseNo = $('#pepf_case_no').val() || $('#pepf_case_no_display').val() || '';
        if (pdfCaseNo) $('#ev8_case_no').val(pdfCaseNo);

        var pdfEl = document.getElementById('personEvidenceFormPdfModal');
        var pdfModal = bootstrap.Modal.getInstance(pdfEl);
        if (pdfModal) {
            pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
                pdfEl.removeEventListener('hidden.bs.modal', onHidden);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('addCheckListModalPersonEvidence')).show();
            });
            pdfModal.hide();
        } else {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('addCheckListModalPersonEvidence')).show();
        }
    };

    window.resetPersonEvidencePdfForm = function() {
        var form = document.getElementById('personEvidenceFormPdf');
        if (form) form.reset();
        ['#pepf_person_items_container', '#pepf_person_info_container', '#pepf_evidence_detail_container', '#pepf_inspector_container'].forEach(function(sel) {
            var container = document.querySelector(sel);
            if (!container) return;
            Array.prototype.slice.call(container.children).forEach(function(row, idx) { if (idx > 0) row.remove(); });
        });
        peprRenumberPersonItems();
        peprRenumberPersonInfo();
        peprRenumberEvidenceDetails();
        peprRenumberInspectors();
        if (typeof window.pepfToggleNotifyOther === 'function') window.pepfToggleNotifyOther();
        if (typeof window.pepfSyncReportNo === 'function') window.pepfSyncReportNo();
    };

    function syncSignDatePartsFromDate() {
        var dateEl = document.getElementById('pepf_sign_date');
        if (!dateEl || !dateEl.value) return;
        var dt = new Date(dateEl.value + 'T00:00:00');
        if (isNaN(dt.getTime())) return;
        var months = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
        $('#pepf_sign_day').val(dt.getDate());
        $('#pepf_sign_month').val(months[dt.getMonth()] || '');
        $('#pepf_sign_year').val(dt.getFullYear() + 543);
    }
    window.syncSignDatePartsFromDate = syncSignDatePartsFromDate;

    $(document).on('change', '#personEvidenceFormPdfModal input[name="pepf_notify_method[]"]', function() {
        if (typeof window.pepfToggleNotifyOther === 'function') window.pepfToggleNotifyOther();
    });

    $(document).on('change', '#pepf_signer_fullname', function() {
        var val = $(this).val() || '';
        var opt = $(this).find(':selected');
        $('#pepf_signer_id').val(val);
        $('#pepf_signer_position').val(opt.data('position') || '');
    });

    $(document).on('change', '#pepf_sign_date', function() {
        syncSignDatePartsFromDate();
    });

    document.getElementById('personEvidenceFormPdfModal').addEventListener('shown.bs.modal', function() {
        if (typeof window.pepfUpdatePageNumbers === 'function') window.pepfUpdatePageNumbers();
        if (typeof window.pepfToggleNotifyOther === 'function') window.pepfToggleNotifyOther();
        if (typeof window.pepfSyncReportNo === 'function') window.pepfSyncReportNo();
        syncSignDatePartsFromDate();
    });

    peprRenumberPersonItems();
    peprRenumberPersonInfo();
    peprRenumberEvidenceDetails();
    peprRenumberInspectors();

    // Auto-wrap for location fields
    (function() {
        function bindAutoWrap(selector) {
            var inputs = Array.from(document.querySelectorAll(selector));
            if (inputs.length < 2) return;

            function measureText(el, text) {
                var canvas = document.createElement('canvas');
                var ctx = canvas.getContext('2d');
                var style = window.getComputedStyle(el);
                ctx.font = style.fontSize + ' ' + style.fontFamily;
                return ctx.measureText(text).width;
            }

            function getAvailableWidth(el) {
                var style = window.getComputedStyle(el);
                return el.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight) - 4;
            }

            function splitText(el, text) {
                var maxW = getAvailableWidth(el);
                if (measureText(el, text) <= maxW) return { fit: text, rest: '' };
                var fit = '', rest = text;
                for (var i = 1; i <= text.length; i++) {
                    if (measureText(el, text.substring(0, i)) > maxW) {
                        fit = text.substring(0, i - 1);
                        rest = text.substring(i - 1);
                        break;
                    }
                }
                return { fit: fit, rest: rest };
            }

            function distribute(fullText) {
                var remaining = fullText;
                inputs.forEach(function(el) {
                    var parts = splitText(el, remaining);
                    el.value = parts.fit;
                    remaining = parts.rest;
                });
            }

            function collectAllText() {
                return inputs.map(function(el) { return el.value || ''; }).join('');
            }

            inputs.forEach(function(el) {
                el.addEventListener('input', function() {
                    distribute(collectAllText());
                });
            });

            window.addEventListener('resize', function() {
                distribute(collectAllText());
            });
        }

        bindAutoWrap('#personEvidenceFormPdfModal .pepr-location-auto');
    })();
})();
</script>
