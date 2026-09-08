<?php
/**
 * Modal: ร่างรายงานคดีชีวิต นอกอาคาร (ฟอร์มเสมือน PDF)
 * A4-paper style editable form — uses same rlo_ field names as modal_report_life_outdoor.php
 * Modal ID: modalReportLifeOutdoorPdf   Form ID: formReportLifeOutdoorPdf
 */
?>

<style>
/* ========== Life Report Outdoor PDF Form — scoped to #modalReportLifeOutdoorPdf ========== */
#modalReportLifeOutdoorPdf .lropf-body { background: #bbb; }
#modalReportLifeOutdoorPdf .lropf-page {
    width: 210mm; min-height: 297mm; margin: 16px auto; padding: 12mm 15mm 10mm 15mm;
    background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.25); position: relative;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; color: #000;
    display: flex; flex-direction: column; page-break-after: always;
}
#modalReportLifeOutdoorPdf .lropf-page:last-child { page-break-after: auto; }

/* Row helpers */
#modalReportLifeOutdoorPdf .fr  { display: flex; flex-wrap: wrap; align-items: baseline; line-height: 1.9; width: 100%; }
#modalReportLifeOutdoorPdf .fl  { font-size: 14px; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportLifeOutdoorPdf .fl-b{ font-size: 14px; font-weight: 700; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportLifeOutdoorPdf .i1  { padding-left: 20px; }
#modalReportLifeOutdoorPdf .i2  { padding-left: 40px; }
#modalReportLifeOutdoorPdf .sec-heading  { font-weight: 700; font-size: 14px; margin-top: 2px; margin-bottom: 1px; }
#modalReportLifeOutdoorPdf .sub-heading  { font-weight: 600; font-size: 14px; margin-left: 20px; }
#modalReportLifeOutdoorPdf .form-title   { text-decoration: underline; text-align: center; font-size: 15px; font-weight: 700; margin: 6px auto 4px auto; }
#modalReportLifeOutdoorPdf .page-header  { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0; }
#modalReportLifeOutdoorPdf .form-footer  { margin-top: auto; padding-top: 6px; font-size: 10px; color: #333; display: flex; justify-content: space-between; }
#modalReportLifeOutdoorPdf .form-footer-right { text-align: right; white-space: nowrap; line-height: 1.4; }
#modalReportLifeOutdoorPdf .form-footer-left  { flex: 1; }
#modalReportLifeOutdoorPdf .sub-heading-bottom { font-weight: 600; font-size: 11px; margin-left: 20px; }
#modalReportLifeOutdoorPdf .signature-block { margin-top: 30px; text-align: center; padding-left: 50%; line-height: 2; }
#modalReportLifeOutdoorPdf .blank-line { width: 100%; border-bottom: 1px dotted #888; height: 22px; margin-bottom: 0; }

/* Editable input styles */
#modalReportLifeOutdoorPdf .lropf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; min-width: 40px; flex: 1; margin: 0 4px; color: #000;
}
#modalReportLifeOutdoorPdf .lropf-inp:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }
#modalReportLifeOutdoorPdf .lropf-inp-s { width: 60px; max-width: 80px; text-align: center; flex: 0 0 auto; }
#modalReportLifeOutdoorPdf .lropf-inp-m { flex: 0 1 140px; min-width: 80px; text-align: center; }
#modalReportLifeOutdoorPdf .lropf-inp-l { flex: 1; min-width: 160px; }
#modalReportLifeOutdoorPdf .lropf-inp-s,
#modalReportLifeOutdoorPdf .lropf-inp-m,
#modalReportLifeOutdoorPdf .lropf-inp-l {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; margin: 0 4px; color: #000;
}
#modalReportLifeOutdoorPdf .lropf-inp-s:focus,
#modalReportLifeOutdoorPdf .lropf-inp-m:focus,
#modalReportLifeOutdoorPdf .lropf-inp-l:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Textarea */
#modalReportLifeOutdoorPdf .lropf-ta {
    width: 100%; border: none; border-bottom: 1px dotted #888;
    background: transparent; outline: none; resize: vertical;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.8;
    padding: 0 4px; min-height: 24px; margin: 0 4px 2px 20px; color: #000;
    background-image: repeating-linear-gradient(transparent, transparent 27px, #ddd 27px, #ddd 28px);
    background-position: 0 0;
}
#modalReportLifeOutdoorPdf .lropf-ta:focus { border-bottom-color: #0d6efd; }

/* Checkbox — square style */
#modalReportLifeOutdoorPdf .ck { display: inline-flex; align-items: baseline; margin-right: 8px; font-size: 14px; white-space: nowrap; }
#modalReportLifeOutdoorPdf .lropf-cb {
    -webkit-appearance: none; appearance: none;
    width: 14px; height: 14px; margin-right: 3px; cursor: pointer; position: relative; top: 2px;
    border: 1.5px solid #000; border-radius: 0; background: #fff;
}
#modalReportLifeOutdoorPdf .lropf-cb:checked { background: #fff; }
#modalReportLifeOutdoorPdf .lropf-cb:checked::after {
    content: '✓'; position: absolute; top: -3px; left: 1px;
    font-size: 13px; font-weight: bold; color: #000; line-height: 1;
}

/* Add/Remove inspector buttons */
#modalReportLifeOutdoorPdf .lropf-add-btn {
    font-size: 12px; padding: 1px 8px; border: 1px dashed #0d6efd; color: #0d6efd;
    background: transparent; border-radius: 4px; cursor: pointer; margin-left: 20px; margin-top: 2px;
}
#modalReportLifeOutdoorPdf .lropf-add-btn:hover { background: rgba(13,110,253,.08); }
#modalReportLifeOutdoorPdf .lropf-del-btn {
    font-size: 11px; padding: 0 5px; border: none; color: #dc3545; background: transparent; cursor: pointer; margin-left: 4px;
}

/* Select dropdown for inspector */
#modalReportLifeOutdoorPdf .lropf-select {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 26px; flex: 1; margin: 0 4px; color: #000;
    cursor: pointer; min-width: 120px;
}
#modalReportLifeOutdoorPdf .lropf-select:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Select2 inside PDF form — compact */
#modalReportLifeOutdoorPdf .rlopdf-inspector-row .select2-container { flex: 1; min-width: 120px; margin: 0 4px; }
#modalReportLifeOutdoorPdf .rlopdf-inspector-row .select2-container--bootstrap-5 .select2-selection {
    min-height: 26px !important; height: 26px !important; padding: 0 4px !important;
    border: none !important; border-bottom: 1px dotted #888 !important; border-radius: 0 !important;
    background: transparent !important; font-size: 14px !important;
}
#modalReportLifeOutdoorPdf .rlopdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    padding: 0 !important; line-height: 26px !important; font-size: 14px !important; color: #000 !important;
}
#modalReportLifeOutdoorPdf .rlopdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
    height: 26px !important;
}
</style>

<div class="modal fade" id="modalReportLifeOutdoorPdf" aria-labelledby="modalReportLifeOutdoorPdfLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 240mm;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportLifeOutdoorPdfLabel">
                    รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต (นอกอาคาร)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body lropf-body p-0" style="max-height: 80vh; overflow-y: auto;">
                <form id="formReportLifeOutdoorPdf" novalidate>
                    <input type="hidden" id="rlopdf_incident_id" name="incident_id">

                    <!-- Switch back to standard form -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="rlo_editInfoPdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="rlo_editCountPdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchLifeOutdoorReportToStd" checked
                                   style="width: 3rem; height: 1.5rem; cursor: pointer;"
                                   onchange="if(!this.checked){ this.checked=true; switchLifeOutdoorReportToStandard(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchLifeOutdoorReportToStd" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ==================== PAGE 1 ==================== -->
<div class="lropf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <input type="text" class="lropf-inp lropf-inp-s" name="rlo_report_no_display_pdf" id="rlopdf_report_no_display" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="lropf-inp lropf-inp-s" id="rlopdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">1/2</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="lropf-inp" name="rlo_agency_name" id="rlopdf_agency_name">
    </div>

    <div class="form-title">รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต (นอกอาคาร)</div>

    <!-- ๑. การรับแจ้งเหตุ -->
    <div class="sec-heading">๑. <u>การรับแจ้งเหตุ</u></div>
    <div class="fr i1" style="flex-wrap:nowrap;">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="lropf-inp lropf-inp-m" name="rlo_receive_date" id="rlopdf_receive_date">
        <span class="fl">เวลา</span>
        <input type="time" class="lropf-inp lropf-inp-m" name="rlo_receive_time" id="rlopdf_receive_time">
        <span class="fl">น. ตามประจำวันข้อที่</span>
        <input type="text" class="lropf-inp" name="rlo_daily_ref" id="rlopdf_daily_ref" style="min-width:60px;">
        <span class="fl">กสก.พฐก./กลก.ศพฐ./</span>
    </div>
    <div class="fr i1">
        <span class="fl">พฐ.จว.</span>
        <input type="text" class="lropf-inp lropf-inp-m" name="rlo_agency_name" id="rlopdf_agency_name_short" style="flex:0 1 160px;">
        <span class="fl">ได้รับแจ้งตาม</span>
        <label class="ck"><input type="checkbox" class="lropf-cb" name="rlo_notify_method[]" value="หนังสือ" id="rlopdf_notify_letter">&nbsp;หนังสือ</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="lropf-cb" name="rlo_notify_method[]" value="โทรศัพท์" id="rlopdf_notify_phone">&nbsp;ทางโทรศัพท์</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="lropf-cb" name="rlo_notify_method[]" value="วิทยุ" id="rlopdf_notify_radio">&nbsp;วิทยุสื่อสาร</label>
    </div>
    <div class="fr i1">
        <span class="fl">จาก สน./สภ.</span>
        <input type="text" class="lropf-inp" name="rlo_from_station" id="rlopdf_from_station">
        <span class="fl">ขอเจ้าหน้าที่ ร่วมตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต</span>
    </div>
    <div class="fr i1">
        <span class="fl">โดยมี</span>
        <input type="text" class="lropf-inp" name="rlo_investigator" id="rlopdf_investigator">
        <span class="fl">เป็นพนักงานสอบสวนเจ้าของคดี</span>
    </div>

    <!-- หน่วยงาน radio (hidden from PDF view but kept for data sync) -->
    <input type="hidden" name="rlo_agency_type" id="rlopdf_agency_type">

    <!-- ๒. สถานที่เกิดเหตุ -->
    <div class="sec-heading">๒. <u>สถานที่เกิดเหตุ</u></div>
    <div class="fr i1">
        <span class="fl">สถานที่เกิดเหตุ</span>
        <input type="text" class="lropf-inp" name="rlo_crime_location" id="rlopdf_crime_location">
    </div>
    <div class="fr i1">
        <span class="fl">ผู้เสียชีวิต/ผู้บาดเจ็บ/ผู้เสียหาย</span>
        <input type="text" class="lropf-inp" name="rlo_victim_name" id="rlopdf_victim_name">
        <span class="fl" style="margin-left:20px;">อายุประมาณ</span>
        <input type="text" class="lropf-inp lropf-inp-s" name="rlo_victim_age" id="rlopdf_victim_age" style="text-align:center;">
        <span class="fl">ปี</span>
    </div>

    <!-- ๓. วันเวลาที่ทราบเหตุ/เกิดเหตุ -->
    <div class="sec-heading">๓. <u>วันเวลาที่ทราบเหตุ/เกิดเหตุ</u></div>
    <div class="fr i1">
        <span class="fl">ผู้เสียหายทราบเหตุ/เกิดเหตุ เมื่อวันที่</span>
        <input type="date" class="lropf-inp lropf-inp-m" name="rlo_victim_know_date" id="rlopdf_victim_know_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="lropf-inp lropf-inp-m" name="rlo_victim_know_time" id="rlopdf_victim_know_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">พนักงานสอบสวนทราบเหตุ เมื่อวันที่</span>
        <input type="date" class="lropf-inp lropf-inp-m" name="rlo_officer_know_date" id="rlopdf_officer_know_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="lropf-inp lropf-inp-m" name="rlo_officer_know_time" id="rlopdf_officer_know_time">
        <span class="fl">น.</span>
    </div>

    <!-- ๔. วันเวลาตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading">๔. <u>วันเวลาตรวจสถานที่เกิดเหตุ</u></div>
    <div class="fr i1">
        <span class="fl">ตรวจสถานที่เกิดเหตุ เมื่อวันที่</span>
        <input type="date" class="lropf-inp lropf-inp-m" name="rlo_inspect_date" id="rlopdf_inspect_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="lropf-inp lropf-inp-m" name="rlo_inspect_time" id="rlopdf_inspect_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">ตรวจสถานที่เกิดเหตุเพิ่มเติม เมื่อวันที่</span>
        <input type="date" class="lropf-inp lropf-inp-m" name="rlo_inspect_add_date" id="rlopdf_inspect_add_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="lropf-inp lropf-inp-m" name="rlo_inspect_add_time" id="rlopdf_inspect_add_time">
        <span class="fl">น.</span>
    </div>

    <!-- ๕. ผู้ตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading">๕. <u>ผู้ตรวจสถานที่เกิดเหตุ</u></div>
    <div id="rlopdf_inspector_container">
        <div class="fr i1 rlopdf-inspector-row">
            <span class="fl rlopdf-inspector-num">5.1.</span>
            <select class="lropf-select rp-inspector-select" name="rlo_inspector_name[]">
                <option value="">-- เลือกผู้ตรวจ --</option>
            </select>
            <span class="fl" style="margin-left:6px;">ตำแหน่ง</span>
            <input type="text" class="lropf-inp rp-inspector-position" name="rlo_inspector_position[]" placeholder="ตำแหน่ง" readonly>
            <button type="button" class="lropf-del-btn" title="ลบ" onclick="this.closest('.rlopdf-inspector-row').remove(); rlopdfRenumberInspectors();">✕</button>
        </div>
    </div>
    <button type="button" class="lropf-add-btn" onclick="rlopdfAddInspectorRow();">+ เพิ่มผู้ตรวจ</button>

    <!-- ๖. ลักษณะของสถานที่เกิดเหตุ -->
    <div class="sec-heading">๖. <u>ลักษณะของสถานที่เกิดเหตุ</u></div>
    <textarea class="lropf-ta" name="rlo_scene_characteristics" id="rlopdf_scene_characteristics" rows="3"></textarea>

    <!-- ๗. ผลการตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading">๗. <u>ผลการตรวจสถานที่เกิดเหตุ</u></div>
    <div class="fr i1"><span class="fl">พฤติการณ์ของคดีจากการสอบถามข้อมูลในเบื้องต้นจาก พงส. ได้ความว่า</span></div>
    <textarea class="lropf-ta" name="rlo_case_behavior" id="rlopdf_case_behavior" rows="2"></textarea>

    <div class="fr i1" style="margin-top:2px;">
        <span class="fl">จากการตรวจสถานที่เกิดเหตุ</span>
    </div>
    <div class="fr i1">
        <span class="fl-b">๗.๑ สภาพของสถานที่เกิดเหตุเมื่อไปถึง</span>
    </div>
    <textarea class="lropf-ta" name="rlo_scene_condition" id="rlopdf_scene_condition" rows="2" style="margin-left:40px;"></textarea>

    <!-- Footer Page 1 -->
    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom">
            <div>ปฏิบัติงานตาม</div>
            <div>OPFS – CS – SP – 02</div>
        </div>
        <div class="form-footer-right sub-heading-bottom">
            F-CS-14 แก้ไขครั้งที่ 2<br>
            แก้ไขวันที่ 2 ก.ย. 63<br>
            เริ่มใช้ 1 ต.ค. 63
        </div>
    </div>
</div>

<!-- ==================== PAGE 2 ==================== -->
<div class="lropf-page">
    <div class="page-header">
         <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <input type="text" class="lropf-inp lropf-inp-s" name="rlo_report_no_display_pdf_2" id="rlopdf_report_no_display_2" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="lropf-inp lropf-inp-s" id="rlopdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">2/2</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="lropf-inp" name="rlo_agency_name_p2" id="rlopdf_agency_name_p2" readonly tabindex="-1">
    </div>

    <!-- ๗.๒ ลักษณะสภาพศพ -->
    <div class="fr i1" style="margin-top:8px;">
        <span class="fl-b">๗.๒ ลักษณะสภาพศพ......</span>
    </div>
    <div class="fr i2">
        <span class="fl">๗.๒.๑ พบศพ/ไม่พบศพ</span>
        <input type="text" class="lropf-inp" name="rlo_body_found" id="rlopdf_body_found">
    </div>
    <div class="fr i2">
        <span class="fl">๗.๒.๒ ตำแหน่งที่พบศพ</span>
        <input type="text" class="lropf-inp" name="rlo_body_position" id="rlopdf_body_position">
    </div>
    <div class="fr i2">
        <span class="fl">๗.๒.๓ สภาพศพ</span>
    </div>
    <textarea class="lropf-ta" name="rlo_body_condition" id="rlopdf_body_condition" rows="2" style="margin-left:40px;"></textarea>
    <div class="fr i2">
        <span class="fl">๗.๒.๔ สภาพเครื่องแต่งกายและทรัพย์สิน</span>
    </div>
    <textarea class="lropf-ta" name="rlo_body_clothing" id="rlopdf_body_clothing" rows="2" style="margin-left:40px;"></textarea>
    <div class="fr i2">
        <span class="fl">๗.๒.๕ รอยบาดแผลที่ศพ</span>
    </div>
    <textarea class="lropf-ta" name="rlo_body_wounds" id="rlopdf_body_wounds" rows="2" style="margin-left:40px;"></textarea>

    <!-- ๗.๓ ร่องรอยและวัตถุพยาน -->
    <div class="fr i1" style="margin-top:8px;">
        <span class="fl-b">๗.๓ ร่องรอยและวัตถุพยานที่ตรวจพบในสถานที่เกิดเหตุ</span>
    </div>
    <textarea class="lropf-ta" name="rlo_evidence_found" id="rlopdf_evidence_found" rows="2" style="margin-left:40px;"></textarea>

    <!-- ๗.๔ วัตถุพยานที่ตรวจเก็บ -->
    <div class="fr i1" style="margin-top:8px;">
        <span class="fl-b">๗.๔ วัตถุพยานที่ตรวจเก็บในสถานที่เกิดเหตุ</span>
    </div>
    <textarea class="lropf-ta" name="rlo_evidence_collected" id="rlopdf_evidence_collected" rows="2" style="margin-left:40px;"></textarea>

    <!-- ๗.๕ การดำเนินการเกี่ยวกับวัตถุพยาน -->
    <div class="fr i1" style="margin-top:8px;">
        <span class="fl-b">๗.๕ การดำเนินการเกี่ยวกับวัตถุพยาน</span>
    </div>
    <textarea class="lropf-ta" name="rlo_evidence_action" id="rlopdf_evidence_action" rows="2" style="margin-left:40px;"></textarea>

    <!-- ๗.๖ การส่งมอบ -->
    <div class="fr i1" style="margin-top:12px;">
        <span class="fl-b">๗.๖</span>
        <span class="fl" style="margin-left:6px;">เจ้าหน้าที่ กสก.พฐก. / กสก.ศพฐ ..... / พฐ.จว.</span>
        <input type="text" class="lropf-inp" name="rlo_handover_officer" id="rlopdf_handover_officer">
        <span class="fl">ตรวจสถานที่เกิดเหตุเสร็จสิ้น</span>
    </div>
    <div class="fr i1">
        <span class="fl">พร้อมทั้งส่งมอบสถานที่เกิดเหตุคืนให้กับ</span>
        <input type="text" class="lropf-inp" name="rlo_handover_to" id="rlopdf_handover_to">
    </div>
    <div class="fr i1">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="lropf-inp lropf-inp-m" name="rlo_handover_date" id="rlopdf_handover_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="lropf-inp lropf-inp-m" name="rlo_handover_time" id="rlopdf_handover_time">
        <span class="fl">น.</span>
    </div>

    <!-- ลงชื่อ -->
    <div class="signature-block">
        <div>ลงชื่อ ........................................ ผู้รายงาน</div>
        <div>(<input type="text" class="lropf-inp" name="rlo_signer_name" id="rlopdf_signer_name" style="width:200px; text-align:center; display:inline-block;">)</div>
        <div>ตำแหน่ง <input type="text" class="lropf-inp" name="rlo_signer_position" id="rlopdf_signer_position" style="width:200px; text-align:center; display:inline-block;"></div>
        <div>วันที่ <input type="date" class="lropf-inp" name="rlo_sign_date" id="rlopdf_sign_date" style="width:180px; text-align:center; display:inline-block;"></div>
    </div>

    <!-- Footer Page 2 -->
    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom">
            <div>ปฏิบัติงานตาม</div>
            <div>OPFS – CS – SP – 02</div>
        </div>
        <div class="form-footer-right sub-heading-bottom">
            F-CS-14 แก้ไขครั้งที่ 2<br>
            แก้ไขวันที่ 2 ก.ย. 63<br>
            เริ่มใช้ 1 ต.ค. 63
        </div>
    </div>
</div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_report_life_outdoor_pdf">
                    <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> ยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>

<script>
/* ========== Inspector Row Management for Life Outdoor PDF ========== */
function rlopdfAddInspectorRow() {
    var container = document.getElementById('rlopdf_inspector_container');
    var count = container.querySelectorAll('.rlopdf-inspector-row').length + 1;
    var opts = '<option value="">-- เลือกผู้ตรวจ --</option>';
    if (typeof rpUsersList !== 'undefined' && rpUsersList.length > 0) {
        rpUsersList.forEach(function(u) {
            opts += '<option value="' + u.fullname + '">' + u.fullname + '</option>';
        });
    }
    var html = '<div class="fr i1 rlopdf-inspector-row">' +
        '<span class="fl rlopdf-inspector-num">5.' + count + '.</span>' +
        '<select class="lropf-select rp-inspector-select" name="rlo_inspector_name[]">' + opts + '</select>' +
        '<span class="fl" style="margin-left:6px;">ตำแหน่ง</span>' +
        '<input type="text" class="lropf-inp rp-inspector-position" name="rlo_inspector_position[]" placeholder="ตำแหน่ง" readonly>' +
        '<button type="button" class="lropf-del-btn" title="ลบ" onclick="this.closest(\'.rlopdf-inspector-row\').remove(); rlopdfRenumberInspectors();">✕</button>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
    var $modal = $('#modalReportLifeOutdoorPdf');
    var $newSelect = $(container).find('.rlopdf-inspector-row:last .rp-inspector-select');
    $newSelect.select2({
        theme: 'bootstrap-5', width: '100%', placeholder: '-- เลือกผู้ตรวจ --',
        allowClear: true, dropdownParent: $modal
    });
}

function rlopdfRenumberInspectors() {
    var rows = document.querySelectorAll('#rlopdf_inspector_container .rlopdf-inspector-num');
    rows.forEach(function(el, idx) { el.textContent = '5.' + (idx + 1) + '.'; });
}

/* Init Select2 + populate options when PDF modal opens */
document.addEventListener('DOMContentLoaded', function() {
    $('#modalReportLifeOutdoorPdf').on('shown.bs.modal', function() {
        var $modal = $(this);
        if (typeof rpLoadUsers === 'function') {
            rpLoadUsers(function() {
                $modal.find('#rlopdf_inspector_container .rp-inspector-select').each(function() {
                    var current = $(this).val();
                    $(this).html(rpBuildUserOptions(current));
                });
                $modal.find('#rlopdf_inspector_container .rp-inspector-select').each(function() {
                    if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
                    $(this).select2({
                        theme: 'bootstrap-5', width: '100%', placeholder: '-- เลือกผู้ตรวจ --',
                        allowClear: true, dropdownParent: $modal
                    });
                });
            });
        }
    });
});
</script>
