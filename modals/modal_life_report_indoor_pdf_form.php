<?php
/**
 * Modal: ร่างรายงานคดีชีวิต ในอาคาร (ฟอร์มเสมือน PDF)
 * A4-paper style editable form — uses same rli_ field names as modal_report_life_indoor.php
 * Modal ID: modalReportLifeIndoorPdf   Form ID: formReportLifeIndoorPdf
 */
?>

<style>
/* ========== Life Report Indoor PDF Form — scoped to #modalReportLifeIndoorPdf ========== */
#modalReportLifeIndoorPdf .lripf-body { background: #bbb; }
#modalReportLifeIndoorPdf .lripf-page {
    width: 210mm; min-height: 297mm; margin: 16px auto; padding: 12mm 15mm 10mm 15mm;
    background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.25); position: relative;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; color: #000;
    display: flex; flex-direction: column; page-break-after: always;
}
#modalReportLifeIndoorPdf .lripf-page:last-child { page-break-after: auto; }

/* Row helpers */
#modalReportLifeIndoorPdf .fr  { display: flex; flex-wrap: wrap; align-items: baseline; line-height: 1.9; width: 100%; }
#modalReportLifeIndoorPdf .fl  { font-size: 14px; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportLifeIndoorPdf .fl-b{ font-size: 14px; font-weight: 700; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportLifeIndoorPdf .i1  { padding-left: 20px; }
#modalReportLifeIndoorPdf .i2  { padding-left: 40px; }
#modalReportLifeIndoorPdf .sec-heading  { font-weight: 700; font-size: 14px; margin-top: 2px; margin-bottom: 1px; }
#modalReportLifeIndoorPdf .sub-heading  { font-weight: 600; font-size: 14px; margin-left: 20px; }
#modalReportLifeIndoorPdf .form-title   { text-decoration: underline; text-align: center; font-size: 15px; font-weight: 700; margin: 6px auto 4px auto; }
#modalReportLifeIndoorPdf .page-header  { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0; }
#modalReportLifeIndoorPdf .form-footer  { margin-top: auto; padding-top: 6px; font-size: 10px; color: #333; display: flex; justify-content: space-between; }
#modalReportLifeIndoorPdf .form-footer-right { text-align: right; white-space: nowrap; line-height: 1.4; }
#modalReportLifeIndoorPdf .form-footer-left  { flex: 1; }
#modalReportLifeIndoorPdf .sub-heading-bottom { font-weight: 600; font-size: 11px; margin-left: 20px; }
#modalReportLifeIndoorPdf .signature-block { margin-top: 30px; text-align: center; padding-left: 50%; line-height: 2; }
#modalReportLifeIndoorPdf .blank-line { width: 100%; border-bottom: 1px dotted #888; height: 22px; margin-bottom: 0; }
#modalReportLifeIndoorPdf .wall-row { display: flex; align-items: baseline; line-height: 1.9; padding-left: 40px; }

/* Editable input styles */
#modalReportLifeIndoorPdf .lripf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; min-width: 40px; flex: 1; margin: 0 4px; color: #000;
}
#modalReportLifeIndoorPdf .lripf-inp:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }
#modalReportLifeIndoorPdf .lripf-inp-s { width: 60px; max-width: 80px; text-align: center; flex: 0 0 auto; }
#modalReportLifeIndoorPdf .lripf-inp-m { flex: 0 1 140px; min-width: 80px; text-align: center; }
#modalReportLifeIndoorPdf .lripf-inp-l { flex: 1; min-width: 160px; }
#modalReportLifeIndoorPdf .lripf-inp-s,
#modalReportLifeIndoorPdf .lripf-inp-m,
#modalReportLifeIndoorPdf .lripf-inp-l {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; margin: 0 4px; color: #000;
}
#modalReportLifeIndoorPdf .lripf-inp-s:focus,
#modalReportLifeIndoorPdf .lripf-inp-m:focus,
#modalReportLifeIndoorPdf .lripf-inp-l:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Textarea */
#modalReportLifeIndoorPdf .lripf-ta {
    width: 100%; border: none; border-bottom: 1px dotted #888;
    background: transparent; outline: none; resize: vertical;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.8;
    padding: 0 4px; min-height: 24px; margin: 0 4px 2px 20px; color: #000;
    background-image: repeating-linear-gradient(transparent, transparent 27px, #ddd 27px, #ddd 28px);
    background-position: 0 0;
}
#modalReportLifeIndoorPdf .lripf-ta:focus { border-bottom-color: #0d6efd; }

/* Checkbox — square style */
#modalReportLifeIndoorPdf .ck { display: inline-flex; align-items: baseline; margin-right: 8px; font-size: 14px; white-space: nowrap; }
#modalReportLifeIndoorPdf .lripf-cb {
    -webkit-appearance: none; appearance: none;
    width: 14px; height: 14px; margin-right: 3px; cursor: pointer; position: relative; top: 2px;
    border: 1.5px solid #000; border-radius: 0; background: #fff;
}
#modalReportLifeIndoorPdf .lripf-cb:checked { background: #fff; }
#modalReportLifeIndoorPdf .lripf-cb:checked::after {
    content: '✓'; position: absolute; top: -3px; left: 1px;
    font-size: 13px; font-weight: bold; color: #000; line-height: 1;
}

/* Add/Remove inspector buttons */
#modalReportLifeIndoorPdf .lripf-add-btn {
    font-size: 12px; padding: 1px 8px; border: 1px dashed #0d6efd; color: #0d6efd;
    background: transparent; border-radius: 4px; cursor: pointer; margin-left: 20px; margin-top: 2px;
}
#modalReportLifeIndoorPdf .lripf-add-btn:hover { background: rgba(13,110,253,.08); }
#modalReportLifeIndoorPdf .lripf-del-btn {
    font-size: 11px; padding: 0 5px; border: none; color: #dc3545; background: transparent; cursor: pointer; margin-left: 4px;
}

/* Select dropdown for inspector */
#modalReportLifeIndoorPdf .lripf-select {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 26px; flex: 1; margin: 0 4px; color: #000;
    cursor: pointer; min-width: 120px;
}
#modalReportLifeIndoorPdf .lripf-select:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Select2 inside PDF form — compact */
#modalReportLifeIndoorPdf .rlipdf-inspector-row .select2-container { flex: 1; min-width: 120px; margin: 0 4px; }
#modalReportLifeIndoorPdf .rlipdf-inspector-row .select2-container--bootstrap-5 .select2-selection {
    min-height: 26px !important; height: 26px !important; padding: 0 4px !important;
    border: none !important; border-bottom: 1px dotted #888 !important; border-radius: 0 !important;
    background: transparent !important; font-size: 14px !important;
}
#modalReportLifeIndoorPdf .rlipdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    padding: 0 !important; line-height: 26px !important; font-size: 14px !important; color: #000 !important;
}
#modalReportLifeIndoorPdf .rlipdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
    height: 26px !important;
}
</style>

<div class="modal fade" id="modalReportLifeIndoorPdf" aria-labelledby="modalReportLifeIndoorPdfLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 240mm;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportLifeIndoorPdfLabel">
                    รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต (ในอาคาร)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body lripf-body p-0" style="max-height: 80vh; overflow-y: auto;">
                <form id="formReportLifeIndoorPdf" novalidate>
                    <input type="hidden" id="rlipdf_incident_id" name="incident_id">

                    <!-- Switch back to standard form -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="rli_editInfoPdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="rli_editCountPdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchLifeIndoorReportToStd" checked
                                   style="width: 3rem; height: 1.5rem; cursor: pointer;"
                                   onchange="if(!this.checked){ this.checked=true; switchLifeIndoorReportToStandard(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchLifeIndoorReportToStd" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ==================== PAGE 1 ==================== -->
<div class="lripf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <input type="text" class="lripf-inp lripf-inp-s" name="rli_report_no_display_pdf" id="rlipdf_report_no_display" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;text-align:center" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="lripf-inp lripf-inp-s" id="rlipdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">1/4</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="lripf-inp" name="rli_agency_name" id="rlipdf_agency_name">
    </div>

    <div class="form-title">รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต (ในอาคาร)</div>

    <!-- ๑. การรับแจ้งเหตุ -->
    <div class="sec-heading">1. <u>การรับแจ้งเหตุ</u></div>
    <div class="fr i1" style="flex-wrap:nowrap;">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="lripf-inp lripf-inp-m" name="rli_receive_date" id="rlipdf_receive_date">
        <span class="fl">เวลา</span>
        <input type="time" class="lripf-inp lripf-inp-m" name="rli_receive_time" id="rlipdf_receive_time">
        <span class="fl">น. ตามประจำวันข้อที่</span>
        <input type="text" class="lripf-inp" name="rli_daily_ref" id="rlipdf_daily_ref" style="min-width:60px;">
    </div>
    <div class="fr i1">
        <span class="fl">กสก.พฐก./กลก.ศพฐ./พฐ.จว.</span>
        <input type="text" class="lripf-inp lripf-inp-m" name="rli_agency_name" id="rlipdf_agency_name_short" style="flex:0 1 160px;">
        <span class="fl">ได้รับแจ้งตาม</span>
        <label class="ck"><input type="checkbox" class="lripf-cb" name="rli_notify_method[]" value="หนังสือ" id="rlipdf_notify_letter">&nbsp;หนังสือ</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="lripf-cb" name="rli_notify_method[]" value="โทรศัพท์" id="rlipdf_notify_phone">&nbsp;ทางโทรศัพท์</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="lripf-cb" name="rli_notify_method[]" value="วิทยุสื่อสาร" id="rlipdf_notify_radio">&nbsp;วิทยุสื่อสาร</label>
    </div>
    <div class="fr i1">
        <span class="fl">จาก สน./สภ.</span>
        <input type="text" class="lripf-inp" name="rli_from_station" id="rlipdf_from_station">
        <span class="fl">ขอเจ้าหน้าที่ ร่วมตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต</span>
    </div>
    <div class="fr i1">
        <span class="fl">โดยมี</span>
        <input type="text" class="lripf-inp" name="rli_investigator" id="rlipdf_investigator">
        <span class="fl">เป็นพนักงานสอบสวนเจ้าของคดี</span>
    </div>

    <!-- หน่วยงาน radio (hidden for data sync) -->
    <input type="hidden" name="rli_agency_type" id="rlipdf_agency_type">

    <!-- ๒. สถานที่เกิดเหตุ -->
    <div class="sec-heading">2. <u>สถานที่เกิดเหตุ</u></div>
    <div class="fr i1">
        <span class="fl">สถานที่เกิดเหตุ</span>
        <input type="text" class="lripf-inp" name="rli_crime_location" id="rlipdf_crime_location">
    </div>
    <div class="fr i1">
        <span class="fl">ผู้เสียชีวิต/ผู้บาดเจ็บ/ผู้เสียหาย</span>
        <input type="text" class="lripf-inp" name="rli_victim_name" id="rlipdf_victim_name">
        <span class="fl" style="margin-left:20px;">อายุประมาณ</span>
        <input type="text" class="lripf-inp lripf-inp-s" name="rli_victim_age" id="rlipdf_victim_age" style="text-align:center;">
        <span class="fl">ปี</span>
    </div>

    <!-- ๓. วันเวลาที่ทราบเหตุ/เกิดเหตุ -->
    <div class="sec-heading">3. <u>วันเวลาที่ทราบเหตุ/เกิดเหตุ</u></div>
    <div class="fr i1">
        <span class="fl">ผู้เสียหายทราบเหตุ/เกิดเหตุ เมื่อวันที่</span>
        <input type="date" class="lripf-inp lripf-inp-m" name="rli_victim_know_date" id="rlipdf_victim_know_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="lripf-inp lripf-inp-m" name="rli_victim_know_time" id="rlipdf_victim_know_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">พนักงานสอบสวนทราบเหตุ เมื่อวันที่</span>
        <input type="date" class="lripf-inp lripf-inp-m" name="rli_officer_know_date" id="rlipdf_officer_know_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="lripf-inp lripf-inp-m" name="rli_officer_know_time" id="rlipdf_officer_know_time">
        <span class="fl">น.</span>
    </div>

    <!-- ๔. วันเวลาตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading">4. <u>วันเวลาตรวจสถานที่เกิดเหตุ</u></div>
    <div class="fr i1">
        <span class="fl">ตรวจสถานที่เกิดเหตุ เมื่อวันที่</span>
        <input type="date" class="lripf-inp lripf-inp-m" name="rli_inspect_date" id="rlipdf_inspect_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="lripf-inp lripf-inp-m" name="rli_inspect_time" id="rlipdf_inspect_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">ตรวจสถานที่เกิดเหตุเพิ่มเติม เมื่อวันที่</span>
        <input type="date" class="lripf-inp lripf-inp-m" name="rli_inspect_add_date" id="rlipdf_inspect_add_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="lripf-inp lripf-inp-m" name="rli_inspect_add_time" id="rlipdf_inspect_add_time">
        <span class="fl">น.</span>
    </div>

    <!-- ๕. ผู้ตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading">5. <u>ผู้ตรวจสถานที่เกิดเหตุ</u></div>
    <div id="rlipdf_inspector_container">
        <div class="fr i1 rlipdf-inspector-row">
            <span class="fl rlipdf-inspector-num">5.1.</span>
            <select class="lripf-select rp-inspector-select" name="rli_inspector_name[]">
                <option value="">-- เลือกผู้ตรวจ --</option>
            </select>
            <span class="fl" style="margin-left:6px;">ตำแหน่ง</span>
            <input type="text" class="lripf-inp rp-inspector-position" name="rli_inspector_position[]" placeholder="ตำแหน่ง" readonly>
            <button type="button" class="lripf-del-btn" title="ลบ" onclick="this.closest('.rlipdf-inspector-row').remove(); rlipdfRenumberInspectors();">✕</button>
        </div>
    </div>
    <button type="button" class="lripf-add-btn" onclick="rlipdfAddInspectorRow();">+ เพิ่มผู้ตรวจ</button>

    <!-- ๖. ลักษณะของสถานที่เกิดเหตุ -->
    <div class="sec-heading">6. <u>ลักษณะของสถานที่เกิดเหตุ</u></div>
    <div class="sub-heading">6.1 ลักษณะภายนอก</div>
    <div class="fr i2">
        <span class="fl">เป็น บ้าน/ตึกแถว/อาคาร/อื่น ๆ</span>
        <input type="text" class="lripf-inp" name="rli_building_type_text" id="rlipdf_building_type_text">
        <span class="fl" style="margin-left:6px;">ชั้น จำนวน</span>
        <input type="text" class="lripf-inp lripf-inp-s" name="rli_floor_count" id="rlipdf_floor_count">
        <span class="fl">หลัง/คูหา</span>
        <input type="text" class="lripf-inp lripf-inp-s" name="rli_unit_count" id="rlipdf_unit_count">
    </div>
    <div class="fr i2">
        <label class="ck"><input type="checkbox" class="lripf-cb" name="rli_mezzanine_pdf" value="มี" id="rlipdf_mezzanine_yes">&nbsp;มี</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="lripf-cb" name="rli_no_mezzanine_pdf" value="ไม่มี" id="rlipdf_mezzanine_no">&nbsp;ไม่มี</label>
        <span class="fl">ชั้นลอย</span>
        <span class="fl" style="margin-left:10px;"></span>
        <label class="ck"><input type="checkbox" class="lripf-cb" name="rli_rooftop_pdf_yes" value="มี" id="rlipdf_rooftop_yes">&nbsp;มี</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="lripf-cb" name="rli_rooftop_pdf_no" value="ไม่มี" id="rlipdf_rooftop_no">&nbsp;ไม่มี</label>
        <span class="fl">ดาดฟ้า</span>
        <span class="fl" style="margin-left:10px;">ปลูกอยู่ภายในบริเวณ&nbsp;</span>
        <label class="ck"><input type="checkbox" class="lripf-cb" name="rli_fence_pdf_yes" value="มี" id="rlipdf_fence_yes">&nbsp;มี</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="lripf-cb" name="rli_fence_pdf_no" value="ไม่มี" id="rlipdf_fence_no">&nbsp;ไม่มี</label>
        <span class="fl">รั้วล้อมรอบ เมื่อหันหน้าเข้า</span>
    </div>
    <div class="fr i2">
        <span class="fl">ด้านหน้าติด</span>
        <input type="text" class="lripf-inp" name="rli_ext_front" id="rlipdf_ext_front">
    </div>
    <div class="fr i2">
        <span class="fl">ด้านซ้ายติด</span>
        <input type="text" class="lripf-inp" name="rli_ext_left" id="rlipdf_ext_left">
    </div>
    <div class="fr i2">
        <span class="fl">ด้านขวาติด</span>
        <input type="text" class="lripf-inp" name="rli_ext_right" id="rlipdf_ext_right">
    </div>
    <div class="fr i2">
        <span class="fl">ด้านหลังติด</span>
        <input type="text" class="lripf-inp" name="rli_ext_back" id="rlipdf_ext_back">
    </div>

    <!-- Footer Page 1 -->
    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom">
            <div>ปฏิบัติงานตาม</div>
            <div>OPFS – CS – SP – 02</div>
        </div>
        <div class="form-footer-right sub-heading-bottom">
            F-CS-13 แก้ไขครั้งที่ 2<br>
            แก้ไขวันที่ 2 ก.ย. 63<br>
            เริ่มใช้ 1 ต.ค. 63
        </div>
    </div>
</div>

<!-- ==================== PAGE 2 ==================== -->
<div class="lripf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <input type="text" class="lripf-inp lripf-inp-s" name="rli_report_no_display_pdf_2" id="rlipdf_report_no_display_2" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;text-align:center" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="lripf-inp lripf-inp-s" id="rlipdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">2/4</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="lripf-inp" name="rli_agency_name_p2" id="rlipdf_agency_name_p2" readonly tabindex="-1">
    </div>

    <!-- 6.2 ลักษณะภายใน -->
    <div class="sub-heading" style="margin-left:20px; margin-top:8px;">6.2 ลักษณะภายใน</div>
    <textarea class="lripf-ta" name="rli_interior_detail" id="rlipdf_interior_detail" rows="2" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- 6.3 บริเวณที่เกิดเหตุ -->
    <div class="sub-heading" style="margin-left:20px; margin-top:6px;">6.3 บริเวณที่เกิดเหตุ</div>
    <div class="fr i2">
        <span class="fl">เกิดเหตุที่</span>
        <input type="text" class="lripf-inp" name="rli_incident_area" id="rlipdf_incident_area">
    </div>
    <div class="fr i2">
        <span class="fl">ซึ่งมีขนาด กว้าง x ยาว ประมาณ</span>
        <input type="text" class="lripf-inp" name="rli_area_size" id="rlipdf_area_size">
    </div>

    <!-- ลักษณะโครงสร้าง -->
    <div style="margin-left:40px; margin-top:6px;">
        <div style="font-weight:700; font-size:14px; text-align:center; text-decoration:underline; margin-bottom:4px;">ลักษณะโครงสร้าง</div>
    </div>
    <div class="wall-row">
        <span class="fl">ฝาผนังด้านหน้า</span>
        <input type="text" class="lripf-inp lripf-inp-l" name="rli_wall_front" id="rlipdf_wall_front">
        <span class="fl">หน้าต่าง</span>
        <input type="text" class="lripf-inp lripf-inp-m" name="rli_wall_front_window" id="rlipdf_wall_front_window" style="text-align:center;">
        <span class="fl">บาน</span>
        <span class="fl" style="margin-left:8px;">ประตู</span>
        <input type="text" class="lripf-inp lripf-inp-m" name="rli_wall_front_door" id="rlipdf_wall_front_door" style="text-align:center;">
        <span class="fl">บาน</span>
    </div>
    <div class="wall-row">
        <span class="fl">ฝาผนังด้านซ้าย</span>
        <input type="text" class="lripf-inp lripf-inp-l" name="rli_wall_left" id="rlipdf_wall_left">
        <span class="fl">หน้าต่าง</span>
        <input type="text" class="lripf-inp lripf-inp-m" name="rli_wall_left_window" id="rlipdf_wall_left_window" style="text-align:center;">
        <span class="fl">บาน</span>
        <span class="fl" style="margin-left:8px;">ประตู</span>
        <input type="text" class="lripf-inp lripf-inp-m" name="rli_wall_left_door" id="rlipdf_wall_left_door" style="text-align:center;">
        <span class="fl">บาน</span>
    </div>
    <div class="wall-row">
        <span class="fl">ฝาผนังด้านขวา</span>
        <input type="text" class="lripf-inp lripf-inp-l" name="rli_wall_right" id="rlipdf_wall_right">
        <span class="fl">หน้าต่าง</span>
        <input type="text" class="lripf-inp lripf-inp-m" name="rli_wall_right_window" id="rlipdf_wall_right_window" style="text-align:center;">
        <span class="fl">บาน</span>
        <span class="fl" style="margin-left:8px;">ประตู</span>
        <input type="text" class="lripf-inp lripf-inp-m" name="rli_wall_right_door" id="rlipdf_wall_right_door" style="text-align:center;">
        <span class="fl">บาน</span>
    </div>
    <div class="wall-row">
        <span class="fl">ฝาผนังด้านหลัง</span>
        <input type="text" class="lripf-inp lripf-inp-l" name="rli_wall_back" id="rlipdf_wall_back">
        <span class="fl">หน้าต่าง</span>
        <input type="text" class="lripf-inp lripf-inp-m" name="rli_wall_back_window" id="rlipdf_wall_back_window" style="text-align:center;">
        <span class="fl">บาน</span>
        <span class="fl" style="margin-left:8px;">ประตู</span>
        <input type="text" class="lripf-inp lripf-inp-m" name="rli_wall_back_door" id="rlipdf_wall_back_door" style="text-align:center;">
        <span class="fl">บาน</span>
    </div>
    <div class="wall-row">
        <span class="fl">พื้นห้อง</span>
        <input type="text" class="lripf-inp lripf-inp-m" name="rli_floor_material" id="rlipdf_floor_material">
        <span class="fl" style="margin-left:8px;">เพดาน/ฝ้า</span>
        <input type="text" class="lripf-inp lripf-inp-l" name="rli_ceiling" id="rlipdf_ceiling">
        <span class="fl" style="margin-left:8px;">หลังคา</span>
        <input type="text" class="lripf-inp lripf-inp-m" name="rli_roof" id="rlipdf_roof">
    </div>

    <!-- ลักษณะการจัดวางสิ่งของ -->
    <div style="margin-left:40px; margin-top:6px;">
        <div style="font-weight:700; font-size:14px; text-decoration:underline; margin-bottom:4px;">ลักษณะการจัดวางสิ่งของ</div>
    </div>
    <div class="fr i2">
        <span class="fl">ติดฝาผนังด้านหน้าเรียงจากซ้ายไปขวา</span>
        <input type="text" class="lripf-inp" name="rli_arrange_front" id="rlipdf_arrange_front">
    </div>
    <div class="fr i2">
        <span class="fl">ติดฝาผนังด้านซ้ายเรียงจากหน้าไปหลัง</span>
        <input type="text" class="lripf-inp" name="rli_arrange_left" id="rlipdf_arrange_left">
    </div>
    <div class="fr i2">
        <span class="fl">ติดฝาผนังด้านขวาเรียงจากหน้าไปหลัง</span>
        <input type="text" class="lripf-inp" name="rli_arrange_right" id="rlipdf_arrange_right">
    </div>
    <div class="fr i2">
        <span class="fl">ติดฝาผนังด้านหลังเรียงจากซ้ายไปขวา</span>
        <input type="text" class="lripf-inp" name="rli_arrange_back" id="rlipdf_arrange_back">
    </div>
    <div class="fr i2">
        <span class="fl">บริเวณอื่นๆ</span>
        <input type="text" class="lripf-inp" name="rli_arrange_other" id="rlipdf_arrange_other">
    </div>

    <!-- ๗. ผลการตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading" style="margin-top:6px;">7. <u>ผลการตรวจสถานที่เกิดเหตุ</u></div>
    <div class="fr i1">
        <span class="fl">พฤติการณ์ของคดีจากการสอบถามข้อมูลในเบื้องต้นจาก พงส. ได้ความว่า</span>
    </div>
    <textarea class="lripf-ta" name="rli_case_behavior" id="rlipdf_case_behavior" rows="2"></textarea>
    <div class="blank-line" style="margin-left:20px;"></div>

    <div class="fr i1" style="margin-top:2px;">
        <span class="fl">จากการตรวจสถานที่เกิดเหตุ</span>
    </div>
    <textarea class="lripf-ta" name="rli_inspection_detail" id="rlipdf_inspection_detail" rows="1" style="margin-left:20px;"></textarea>

    <div class="fr i1">
        <span class="fl-b">7.1 สภาพของสถานที่เกิดเหตุเมื่อไปถึง</span>
    </div>
    <textarea class="lripf-ta" name="rli_scene_condition" id="rlipdf_scene_condition" rows="2" style="margin-left:40px;"></textarea>

    <!-- Footer Page 2 -->
    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom">
            <div>ปฏิบัติงานตาม</div>
            <div>OPFS – CS – SP – 02</div>
        </div>
        <div class="form-footer-right sub-heading-bottom">
            F-CS-13 แก้ไขครั้งที่ 2<br>
            แก้ไขวันที่ 2 ก.ย. 63<br>
            เริ่มใช้ 1 ต.ค. 63
        </div>
    </div>
</div>

<!-- ==================== PAGE 3 ==================== -->
<div class="lripf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <input type="text" class="lripf-inp lripf-inp-s" name="rli_report_no_display_pdf_3" id="rlipdf_report_no_display_3" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;text-align:center" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="lripf-inp lripf-inp-s" id="rlipdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">3/4</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="lripf-inp" name="rli_agency_name_p3" id="rlipdf_agency_name_p3" readonly tabindex="-1">
    </div>

    <!-- 7.2 ลักษณะสภาพศพ -->
    <div class="fr i1" style="margin-top:8px;">
        <span class="fl-b">7.2 ลักษณะสภาพศพ</span>
    </div>
    <div class="fr i2">
        <span class="fl">7.2.1 พบศพ/ไม่พบศพ</span>
    </div>
    <textarea class="lripf-ta" name="rli_body_found" id="rlipdf_body_found" rows="2" style="margin-left:40px;"></textarea>

    <div class="fr i2">
        <span class="fl">7.2.2 ตำแหน่งที่พบศพ</span>
    </div>
    <textarea class="lripf-ta" name="rli_body_position" id="rlipdf_body_position" rows="2" style="margin-left:40px;"></textarea>

    <div class="fr i2">
        <span class="fl">7.2.3 สภาพศพ</span>
    </div>
    <textarea class="lripf-ta" name="rli_body_condition" id="rlipdf_body_condition" rows="2" style="margin-left:40px;"></textarea>

    <div class="fr i2">
        <span class="fl">7.2.4 สภาพเครื่องแต่งกายและทรัพย์สิน</span>
    </div>
    <textarea class="lripf-ta" name="rli_body_clothing" id="rlipdf_body_clothing" rows="2" style="margin-left:40px;"></textarea>

    <div class="fr i2">
        <span class="fl">7.2.5 รอยบาดแผลที่ศพ</span>
    </div>
    <textarea class="lripf-ta" name="rli_body_wounds" id="rlipdf_body_wounds" rows="3" style="margin-left:40px;"></textarea>

    <!-- 7.3 ร่องรอยและวัตถุพยาน -->
    <div class="fr i1" style="margin-top:8px;">
        <span class="fl-b">7.3 ร่องรอยและวัตถุพยานที่ตรวจพบในสถานที่เกิดเหตุ</span>
    </div>
    <textarea class="lripf-ta" name="rli_evidence_found" id="rlipdf_evidence_found" rows="2" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- 7.4 วัตถุพยานที่ตรวจเก็บ -->
    <div class="fr i1" style="margin-top:8px;">
        <span class="fl-b">7.4 วัตถุพยานที่ตรวจเก็บในสถานที่เกิดเหตุ</span>
    </div>
    <textarea class="lripf-ta" name="rli_evidence_collected" id="rlipdf_evidence_collected" rows="2" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- Footer Page 3 -->
    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom">
            <div>ปฏิบัติงานตาม</div>
            <div>OPFS – CS – SP – 02</div>
        </div>
        <div class="form-footer-right sub-heading-bottom">
            F-CS-13 แก้ไขครั้งที่ 2<br>
            แก้ไขวันที่ 2 ก.ย. 63<br>
            เริ่มใช้ 1 ต.ค. 63
        </div>
    </div>
</div>

<!-- ==================== PAGE 4 ==================== -->
<div class="lripf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <input type="text" class="lripf-inp lripf-inp-s" name="rli_report_no_display_pdf_4" id="rlipdf_report_no_display_4" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;text-align:center" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="lripf-inp lripf-inp-s" id="rlipdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">4/4</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="lripf-inp" name="rli_agency_name_p4" id="rlipdf_agency_name_p4" readonly tabindex="-1">
    </div>

    <!-- 7.5 การดำเนินการเกี่ยวกับวัตถุพยาน -->
    <div class="fr i1" style="margin-top:8px;">
        <span class="fl-b">7.5 การดำเนินการเกี่ยวกับวัตถุพยาน</span>
    </div>
    <textarea class="lripf-ta" name="rli_evidence_action" id="rlipdf_evidence_action" rows="3" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- 7.6 การส่งมอบ -->
    <div class="fr i1" style="margin-top:12px;">
        <span class="fl-b">7.6</span>
        <span class="fl" style="margin-left:6px;">เจ้าหน้าที่ กสก.พฐก. / กลก.ศพฐ. / พฐ.จว.</span>
        <input type="text" class="lripf-inp" name="rli_handover_officer" id="rlipdf_handover_officer">
        <span class="fl">ตรวจสถานที่เกิดเหตุเสร็จสิ้น</span>
    </div>
    <div class="fr i1">
        <span class="fl">พร้อมทั้งส่งมอบสถานที่เกิดเหตุคืนให้กับ</span>
        <input type="text" class="lripf-inp" name="rli_handover_to" id="rlipdf_handover_to">
    </div>
    <div class="fr i1">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="lripf-inp lripf-inp-m" name="rli_handover_date" id="rlipdf_handover_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="lripf-inp lripf-inp-m" name="rli_handover_time" id="rlipdf_handover_time">
        <span class="fl">น.</span>
    </div>

    <!-- ลงชื่อ -->
    <div class="signature-block">
        <div>ลงชื่อ ........................................ ผู้รายงาน</div>
        <div>(<input type="text" class="lripf-inp" name="rli_signer_name" id="rlipdf_signer_name" style="width:200px; text-align:center; display:inline-block;">)</div>
        <div>ตำแหน่ง <input type="text" class="lripf-inp" name="rli_signer_position" id="rlipdf_signer_position" style="width:200px; text-align:center; display:inline-block;"></div>
        <div>วันที่ <input type="date" class="lripf-inp" name="rli_sign_date" id="rlipdf_sign_date" style="width:180px; text-align:center; display:inline-block;"></div>
    </div>

    <!-- Footer Page 4 -->
    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom">
            <div>ปฏิบัติงานตาม</div>
            <div>OPFS – CS – SP – 02</div>
        </div>
        <div class="form-footer-right sub-heading-bottom">
            F-CS-13 แก้ไขครั้งที่ 2<br>
            แก้ไขวันที่ 2 ก.ย. 63<br>
            เริ่มใช้ 1 ต.ค. 63
        </div>
    </div>
</div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_report_life_indoor_pdf">
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
/* ========== Inspector Row Management for Life Indoor PDF ========== */
function rlipdfAddInspectorRow() {
    var container = document.getElementById('rlipdf_inspector_container');
    var count = container.querySelectorAll('.rlipdf-inspector-row').length + 1;
    var opts = '<option value="">-- เลือกผู้ตรวจ --</option>';
    if (typeof rpUsersList !== 'undefined' && rpUsersList.length > 0) {
        rpUsersList.forEach(function(u) {
            opts += '<option value="' + u.fullname + '">' + u.fullname + '</option>';
        });
    }
    var html = '<div class="fr i1 rlipdf-inspector-row">' +
        '<span class="fl rlipdf-inspector-num">5.' + count + '.</span>' +
        '<select class="lripf-select rp-inspector-select" name="rli_inspector_name[]">' + opts + '</select>' +
        '<span class="fl" style="margin-left:6px;">ตำแหน่ง</span>' +
        '<input type="text" class="lripf-inp rp-inspector-position" name="rli_inspector_position[]" placeholder="ตำแหน่ง" readonly>' +
        '<button type="button" class="lripf-del-btn" title="ลบ" onclick="this.closest(\'.rlipdf-inspector-row\').remove(); rlipdfRenumberInspectors();">✕</button>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
    var $modal = $('#modalReportLifeIndoorPdf');
    var $newSelect = $(container).find('.rlipdf-inspector-row:last .rp-inspector-select');
    $newSelect.select2({
        theme: 'bootstrap-5', width: '100%', placeholder: '-- เลือกผู้ตรวจ --',
        allowClear: true, dropdownParent: $modal
    });
}

function rlipdfRenumberInspectors() {
    var rows = document.querySelectorAll('#rlipdf_inspector_container .rlipdf-inspector-num');
    rows.forEach(function(el, idx) { el.textContent = '5.' + (idx + 1) + '.'; });
}

/* Init Select2 + populate options when PDF modal opens */
document.addEventListener('DOMContentLoaded', function() {
    $('#modalReportLifeIndoorPdf').on('shown.bs.modal', function() {
        var $modal = $(this);
        if (typeof rpLoadUsers === 'function') {
            rpLoadUsers(function() {
                $modal.find('#rlipdf_inspector_container .rp-inspector-select').each(function() {
                    var current = $(this).val();
                    $(this).html(rpBuildUserOptions(current));
                });
                $modal.find('#rlipdf_inspector_container .rp-inspector-select').each(function() {
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
