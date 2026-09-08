<?php
/**
 * Modal: ร่างรายงานคดีเพลิงไหม้ (ฟอร์มเสมือน PDF)
 * A4-paper style editable form — uses same rf_ field names as modal_report_fire.php
 * Modal ID: modalReportFirePdf   Form ID: formReportFirePdf
 */
?>

<style>
/* ========== Fire Report PDF Form — scoped to #modalReportFirePdf ========== */
#modalReportFirePdf .frpf-body { background: #bbb; }
#modalReportFirePdf .frpf-page {
    width: 210mm; min-height: 297mm; margin: 16px auto; padding: 12mm 15mm 10mm 15mm;
    background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.25); position: relative;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; color: #000;
    display: flex; flex-direction: column; page-break-after: always;
}
#modalReportFirePdf .frpf-page:last-child { page-break-after: auto; }

/* Row helpers — same visual as PDF preview */
#modalReportFirePdf .fr  { display: flex; flex-wrap: wrap; align-items: baseline; line-height: 1.9; width: 100%; }
#modalReportFirePdf .fl  { font-size: 14px; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportFirePdf .fl-b{ font-size: 14px; font-weight: 700; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportFirePdf .i1  { padding-left: 20px; }
#modalReportFirePdf .i2  { padding-left: 40px; }
#modalReportFirePdf .wall-row { display: flex; align-items: baseline; line-height: 1.9; padding-left: 40px; }
#modalReportFirePdf .sec-heading  { font-weight: 700; font-size: 14px; margin-top: 2px; margin-bottom: 1px; }
#modalReportFirePdf .sub-heading  { font-weight: 600; font-size: 14px; margin-left: 20px; }
#modalReportFirePdf .form-title   { text-decoration: underline; text-align: center; font-size: 15px; font-weight: 700; margin: 6px auto 4px auto; }
#modalReportFirePdf .page-header  { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0; }
#modalReportFirePdf .form-footer  { margin-top: auto; padding-top: 6px; font-size: 10px; color: #333; display: flex; justify-content: space-between; }
#modalReportFirePdf .form-footer-right { text-align: right; white-space: nowrap; line-height: 1.4; }
#modalReportFirePdf .signature-block { margin-top: 30px; text-align: center; padding-left: 50%; line-height: 2; }

/* Editable input styles — dotted bottom, transparent */
#modalReportFirePdf .frpf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; min-width: 40px; flex: 1; margin: 0 4px;
    color: #000;
}
#modalReportFirePdf .frpf-inp:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }
#modalReportFirePdf .frpf-inp-s { /* short */ width: 60px; max-width: 80px; text-align: center; flex: 0 0 auto; }
#modalReportFirePdf .frpf-inp-m { /* medium */ flex: 0 1 140px; min-width: 80px; text-align: center; }
#modalReportFirePdf .frpf-inp-l { /* long */ flex: 1; min-width: 160px; }
#modalReportFirePdf .frpf-inp-s,
#modalReportFirePdf .frpf-inp-m,
#modalReportFirePdf .frpf-inp-l {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; margin: 0 4px; color: #000;
}
#modalReportFirePdf .frpf-inp-s:focus,
#modalReportFirePdf .frpf-inp-m:focus,
#modalReportFirePdf .frpf-inp-l:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Textarea — dotted lines background */
#modalReportFirePdf .frpf-ta {
    width: 100%; border: none; border-bottom: 1px dotted #888;
    background: transparent; outline: none; resize: vertical;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.8;
    padding: 0 4px; min-height: 24px; margin: 0 4px 2px 20px; color: #000;
    background-image: repeating-linear-gradient(transparent, transparent 27px, #ddd 27px, #ddd 28px);
    background-position: 0 0;
}
#modalReportFirePdf .frpf-ta:focus { border-bottom-color: #0d6efd; }

/* Checkbox — custom same as PDF preview (square style) */
#modalReportFirePdf .ck { display: inline-flex; align-items: baseline; margin-right: 8px; font-size: 14px; white-space: nowrap; }
#modalReportFirePdf .frpf-cb {
    -webkit-appearance: none; appearance: none;
    width: 14px; height: 14px; margin-right: 3px; cursor: pointer; position: relative; top: 2px;
    border: 1.5px solid #000; border-radius: 0; background: #fff;
}
#modalReportFirePdf .frpf-cb:checked {
    background: #fff;
}
#modalReportFirePdf .frpf-cb:checked::after {
    content: '✓'; position: absolute; top: -3px; left: 1px;
    font-size: 13px; font-weight: bold; color: #000; line-height: 1;
}

/* Add/Remove inspector buttons */
#modalReportFirePdf .frpf-add-btn {
    font-size: 12px; padding: 1px 8px; border: 1px dashed #0d6efd; color: #0d6efd;
    background: transparent; border-radius: 4px; cursor: pointer; margin-left: 20px; margin-top: 2px;
}
#modalReportFirePdf .frpf-add-btn:hover { background: rgba(13,110,253,.08); }
#modalReportFirePdf .frpf-del-btn {
    font-size: 11px; padding: 0 5px; border: none; color: #dc3545; background: transparent; cursor: pointer; margin-left: 4px;
}

/* Select dropdown for inspector — blend with dotted-line style */
#modalReportFirePdf .frpf-select {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 26px; flex: 1; margin: 0 4px; color: #000;
    cursor: pointer; min-width: 120px;
}
#modalReportFirePdf .frpf-select:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Select2 inside PDF form — compact */
#modalReportFirePdf .rfpdf-inspector-row .select2-container { flex: 1; min-width: 120px; margin: 0 4px; }
#modalReportFirePdf .rfpdf-inspector-row .select2-container--bootstrap-5 .select2-selection {
    min-height: 26px !important; height: 26px !important; padding: 0 4px !important;
    border: none !important; border-bottom: 1px dotted #888 !important; border-radius: 0 !important;
    background: transparent !important; font-size: 14px !important;
}
#modalReportFirePdf .rfpdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    padding: 0 !important; line-height: 26px !important; font-size: 14px !important; color: #000 !important;
}
#modalReportFirePdf .rfpdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
    height: 26px !important;
}
</style>

<div class="modal fade" id="modalReportFirePdf" aria-labelledby="modalReportFirePdfLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 240mm;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportFirePdfLabel">
                    รายงานการตรวจสถานที่เกิดเหตุคดีเพลิงไหม้
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body frpf-body p-0" style="max-height: 80vh; overflow-y: auto;">
                <form id="formReportFirePdf" novalidate>
                    <input type="hidden" id="rfpdf_incident_id" name="incident_id">

                    <!-- Switch back to standard form -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="rf_editInfoPdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="rf_editCountPdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchFireReportToStd" checked
                                   style="width: 3rem; height: 1.5rem; cursor: pointer;"
                                   onchange="if(!this.checked){ this.checked=true; switchFireReportToStandard(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchFireReportToStd" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ==================== PAGE 1 ==================== -->
<div class="frpf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <input type="text" class="frpf-inp frpf-inp-s" name="rf_report_no_display_pdf" id="rfpdf_report_no_display" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="frpf-inp frpf-inp-s" id="rfpdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">1/3</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="frpf-inp" name="rf_agency_name" id="rfpdf_agency_name">
    </div>

    <div class="form-title">รายงานการตรวจสถานที่เกิดเหตุคดีเพลิงไหม้</div>

    <!-- 1. การรับแจ้งเหตุ -->
    <div class="sec-heading">1. <u>การรับแจ้งเหตุ</u></div>
    <div class="fr i1" style="flex-wrap:nowrap;">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="frpf-inp frpf-inp-m" name="rf_receive_date" id="rfpdf_receive_date">
        <span class="fl">เวลา</span>
        <input type="time" class="frpf-inp frpf-inp-m" name="rf_receive_time" id="rfpdf_receive_time">
        <span class="fl">น. ตามประจำวันข้อที่</span>
        <input type="text" class="frpf-inp" name="rf_daily_ref" id="rfpdf_daily_ref" style="min-width:60px;">
    </div>
    <div class="fr i1">
        <span class="fl">ได้รับแจ้งตาม&nbsp;&nbsp;</span>
        <label class="ck"><input type="checkbox" class="frpf-cb" name="rf_notify_method[]" value="หนังสือ" id="rfpdf_notify_letter">&nbsp;หนังสือ</label>
        <span class="fl">&nbsp;/&nbsp;</span>
        <label class="ck"><input type="checkbox" class="frpf-cb" name="rf_notify_method[]" value="โทรศัพท์" id="rfpdf_notify_phone">&nbsp;ทางโทรศัพท์</label>
        <span class="fl">&nbsp;/&nbsp;</span>
        <label class="ck"><input type="checkbox" class="frpf-cb" name="rf_notify_method[]" value="วิทยุสื่อสาร" id="rfpdf_notify_radio">&nbsp;วิทยุสื่อสาร</label>
    </div>
    <div class="fr i1">
        <span class="fl">จาก สน./สภ.</span>
        <input type="text" class="frpf-inp frpf-inp-l" name="rf_from_station" id="rfpdf_from_station">
        <span class="fl">ขอเจ้าหน้าที่ร่วมตรวจสถานที่เกิดเหตุคดีเพลิงไหม้</span>
    </div>
    <div class="fr i1">
        <span class="fl">โดยมี</span>
        <input type="text" class="frpf-inp" name="rf_investigator" id="rfpdf_investigator">
        <span class="fl">เป็นพนักงานสอบสวนเจ้าของคดี</span>
    </div>

    <!-- 2. สถานที่เกิดเหตุ -->
    <div class="sec-heading">2. <u>สถานที่เกิดเหตุ</u></div>
    <div class="fr i1">
        <span class="fl">เหตุเกิดที่</span>
        <input type="text" class="frpf-inp" name="rf_crime_location" id="rfpdf_crime_location">
    </div>

    <!-- 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ -->
    <div class="sec-heading">3. <u>วันเวลาที่ทราบเหตุ/เกิดเหตุ</u></div>
    <div class="fr i1">
        <span class="fl">ผู้เสียหายทราบเหตุ/เกิดเหตุ เมื่อวันที่</span>
        <input type="date" class="frpf-inp frpf-inp-m" name="rf_victim_know_date" id="rfpdf_victim_know_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="frpf-inp frpf-inp-m" name="rf_victim_know_time" id="rfpdf_victim_know_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">พนักงานสอบสวนทราบเหตุ เมื่อวันที่</span>
        <input type="date" class="frpf-inp frpf-inp-m" name="rf_officer_know_date" id="rfpdf_officer_know_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="frpf-inp frpf-inp-m" name="rf_officer_know_time" id="rfpdf_officer_know_time">
        <span class="fl">น.</span>
    </div>

    <!-- 4. วันเวลาตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading">4. <u>วันเวลาตรวจสถานที่เกิดเหตุ</u></div>
    <div class="fr i1">
        <span class="fl">ตรวจสถานที่เกิดเหตุ เมื่อวันที่</span>
        <input type="date" class="frpf-inp frpf-inp-m" name="rf_inspect_date" id="rfpdf_inspect_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="frpf-inp frpf-inp-m" name="rf_inspect_time" id="rfpdf_inspect_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">ตรวจเพิ่มเติม เมื่อวันที่</span>
        <input type="date" class="frpf-inp frpf-inp-m" name="rf_inspect_add_date" id="rfpdf_inspect_add_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="frpf-inp frpf-inp-m" name="rf_inspect_add_time" id="rfpdf_inspect_add_time">
        <span class="fl">น.</span>
    </div>

    <!-- 5. ผู้ตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading">5. <u>ผู้ตรวจสถานที่เกิดเหตุ</u></div>
    <div id="rfpdf_inspector_container">
        <div class="fr i1 rfpdf-inspector-row">
            <span class="fl rfpdf-inspector-num">5.1.</span>
            <select class="frpf-select rp-inspector-select" name="rf_inspector_name[]">
                <option value="">-- เลือกผู้ตรวจ --</option>
            </select>
            <span class="fl" style="margin-left:6px;">ตำแหน่ง</span>
            <input type="text" class="frpf-inp rp-inspector-position" name="rf_inspector_position[]" placeholder="ตำแหน่ง" readonly>
            <button type="button" class="frpf-del-btn" title="ลบ" onclick="this.closest('.rfpdf-inspector-row').remove(); rfpdfRenumberInspectors();">✕</button>
        </div>
    </div>
    <button type="button" class="frpf-add-btn" onclick="rfpdfAddInspectorRow();">+ เพิ่มผู้ตรวจ</button>

    <!-- 6. ลักษณะของสถานที่เกิดเหตุ -->
    <div class="sec-heading">6. <u>ลักษณะของสถานที่เกิดเหตุ</u></div>
    <div class="sub-heading">6.1 ลักษณะภายนอก</div>
    <textarea class="frpf-ta" name="rf_exterior_detail" id="rfpdf_exterior_detail" rows="2"></textarea>
    <div class="fr i2">
        <span class="fl">จำนวนชั้น</span>
        <input type="text" class="frpf-inp frpf-inp-s" name="rf_floor_count" id="rfpdf_floor_count">
        <span class="fl" style="margin-left:10px;">รั้วล้อมรอบ</span>
        <label class="ck"><input type="radio" class="frpf-cb" name="rf_fence" value="มี" id="rfpdf_fence_yes">&nbsp;มี</label>
        <label class="ck"><input type="radio" class="frpf-cb" name="rf_fence" value="ไม่มี" id="rfpdf_fence_no">&nbsp;ไม่มี</label>
    </div>
    <div class="fr i2">
        <span class="fl">ด้านหน้าติด</span>
        <input type="text" class="frpf-inp" name="rf_ext_front" id="rfpdf_ext_front">
        <span class="fl">ด้านซ้ายติด</span>
        <input type="text" class="frpf-inp" name="rf_ext_left" id="rfpdf_ext_left">
    </div>
    <div class="fr i2">
        <span class="fl">ด้านขวาติด</span>
        <input type="text" class="frpf-inp" name="rf_ext_right" id="rfpdf_ext_right">
        <span class="fl">ด้านหลังติด</span>
        <input type="text" class="frpf-inp" name="rf_ext_back" id="rfpdf_ext_back">
    </div>

    <div class="sub-heading">6.2 ลักษณะภายใน</div>
    <textarea class="frpf-ta" name="rf_interior_detail" id="rfpdf_interior_detail" rows="2"></textarea>

    <div class="sub-heading">6.3 บริเวณที่เกิดเหตุ</div>
    <div class="fr i2">
        <span class="fl">เกิดเหตุที่</span>
        <input type="text" class="frpf-inp" name="rf_incident_area" id="rfpdf_incident_area">
        <span class="fl">ขนาด</span>
        <input type="text" class="frpf-inp frpf-inp-m" name="rf_area_size" id="rfpdf_area_size">
        <span class="fl">หันหน้าไปทาง</span>
        <input type="text" class="frpf-inp frpf-inp-m" name="rf_facing_direction" id="rfpdf_facing_direction">
    </div>

    <div class="form-footer">
        <div></div>
        <div class="form-footer-right">รายงานการตรวจสถานที่เกิดเหตุคดีเพลิงไหม้</div>
    </div>
</div>

<!-- ==================== PAGE 2 ==================== -->
<div class="frpf-page">
    <div class="page-header">
         <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <input type="text" class="frpf-inp frpf-inp-s" name="rf_report_no_display_pdf_2" id="rfpdf_report_no_display_2" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="frpf-inp frpf-inp-s" id="rfpdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">2/3</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="frpf-inp" name="rf_agency_name_2" id="rfpdf_agency_name_2">
    </div>

    <div class="sub-heading">ลักษณะโครงสร้าง</div>
    <div class="wall-row"><span class="fl">ฝาผนังด้านหน้า</span><input type="text" class="frpf-inp" name="rf_wall_front" id="rfpdf_wall_front"></div>
    <div class="wall-row"><span class="fl">ฝาผนังด้านซ้าย</span><input type="text" class="frpf-inp" name="rf_wall_left" id="rfpdf_wall_left"></div>
    <div class="wall-row"><span class="fl">ฝาผนังด้านขวา</span><input type="text" class="frpf-inp" name="rf_wall_right" id="rfpdf_wall_right"></div>
    <div class="wall-row"><span class="fl">ฝาผนังด้านหลัง</span><input type="text" class="frpf-inp" name="rf_wall_back" id="rfpdf_wall_back"></div>
    <div class="wall-row">
        <span class="fl">พื้นห้อง</span><input type="text" class="frpf-inp" name="rf_floor_material" id="rfpdf_floor_material">
        <span class="fl">เพดาน</span><input type="text" class="frpf-inp" name="rf_ceiling" id="rfpdf_ceiling">
        <span class="fl">หลังคา</span><input type="text" class="frpf-inp" name="rf_roof" id="rfpdf_roof">
    </div>

    <div class="sub-heading" style="margin-top:4px;">ลักษณะการจัดวางสิ่งของ</div>
    <div class="fr i2"><span class="fl">ด้านหน้า (ซ้ายไปขวา)</span><input type="text" class="frpf-inp" name="rf_arrange_front" id="rfpdf_arrange_front"></div>
    <div class="fr i2"><span class="fl">ด้านซ้าย (หน้าไปหลัง)</span><input type="text" class="frpf-inp" name="rf_arrange_left" id="rfpdf_arrange_left"></div>
    <div class="fr i2"><span class="fl">ด้านขวา (หน้าไปหลัง)</span><input type="text" class="frpf-inp" name="rf_arrange_right" id="rfpdf_arrange_right"></div>
    <div class="fr i2"><span class="fl">ด้านหลัง (ซ้ายไปขวา)</span><input type="text" class="frpf-inp" name="rf_arrange_back" id="rfpdf_arrange_back"></div>
    <div class="fr i2"><span class="fl">บริเวณอื่นๆ</span><input type="text" class="frpf-inp" name="rf_arrange_other" id="rfpdf_arrange_other"></div>

    <!-- 7. พฤติการณ์คดี -->
    <div class="sec-heading" style="margin-top:6px;">7. <u>พฤติการณ์คดี & สภาพความเสียหาย</u></div>
    <div class="fr i1"><span class="fl">พฤติการณ์ของคดีจากการสอบถามข้อมูลในเบื้องต้นจาก พงส. ได้ความว่า</span></div>
    <textarea class="frpf-ta" name="rf_case_behavior" id="rfpdf_case_behavior" rows="2"></textarea>
    <div class="fr i1">
        <span class="fl">การทำประกันภัย</span>
        <label class="ck"><input type="radio" class="frpf-cb" name="rf_insurance" value="มี" id="rfpdf_insurance_yes">&nbsp;มี</label>
        <label class="ck"><input type="radio" class="frpf-cb" name="rf_insurance" value="ไม่มี" id="rfpdf_insurance_no">&nbsp;ไม่มี</label>
        <span class="fl" style="margin-left:15px;">เวลาในการเผาไหม้</span>
        <input type="text" class="frpf-inp" name="rf_burn_time" id="rfpdf_burn_time">
    </div>
    <div class="fr i1">
        <span class="fl">การดับเพลิง</span>
        <label class="ck"><input type="radio" class="frpf-cb" name="rf_extinguish" value="ดับแล้ว" id="rfpdf_extinguish_yes">&nbsp;ดับแล้ว</label>
        <label class="ck"><input type="radio" class="frpf-cb" name="rf_extinguish" value="ยังไม่ดับ" id="rfpdf_extinguish_no">&nbsp;ยังไม่ดับ</label>
        <input type="text" class="frpf-inp" name="rf_extinguish_detail" id="rfpdf_extinguish_detail">
    </div>
    <div class="fr i1"><span class="fl">สภาพความเสียหาย</span></div>
    <textarea class="frpf-ta" name="rf_damage_condition" id="rfpdf_damage_condition" rows="2"></textarea>
    <div class="fr i1"><span class="fl">การลุกลามของเพลิง</span></div>
    <textarea class="frpf-ta" name="rf_spread_detail" id="rfpdf_spread_detail" rows="2"></textarea>

    <div class="sub-heading">7.1 ความเสียหายโครงสร้าง</div>
    <div class="wall-row"><span class="fl">ฝาผนังด้านหน้า</span><input type="text" class="frpf-inp" name="rf_damage_wall_front" id="rfpdf_damage_wall_front"></div>
    <div class="wall-row"><span class="fl">ฝาผนังด้านซ้าย</span><input type="text" class="frpf-inp" name="rf_damage_wall_left" id="rfpdf_damage_wall_left"></div>
    <div class="wall-row"><span class="fl">ฝาผนังด้านขวา</span><input type="text" class="frpf-inp" name="rf_damage_wall_right" id="rfpdf_damage_wall_right"></div>
    <div class="wall-row"><span class="fl">ฝาผนังด้านหลัง</span><input type="text" class="frpf-inp" name="rf_damage_wall_back" id="rfpdf_damage_wall_back"></div>
    <div class="wall-row">
        <span class="fl">พื้น</span><input type="text" class="frpf-inp" name="rf_damage_floor" id="rfpdf_damage_floor">
        <span class="fl">หลังคา</span><input type="text" class="frpf-inp" name="rf_damage_roof" id="rfpdf_damage_roof">
        <span class="fl">เพดาน</span><input type="text" class="frpf-inp" name="rf_damage_ceiling" id="rfpdf_damage_ceiling">
    </div>

    <div class="form-footer">
        <div></div>
        <div class="form-footer-right">รายงานการตรวจสถานที่เกิดเหตุคดีเพลิงไหม้</div>
    </div>
</div>

<!-- ==================== PAGE 3 ==================== -->
<div class="frpf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <input type="text" class="frpf-inp frpf-inp-s" name="rf_report_no_display_pdf_3" id="rfpdf_report_no_display_3" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="frpf-inp frpf-inp-s" id="rfpdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">3/3</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="frpf-inp" name="rf_agency_name_3" id="rfpdf_agency_name_3">
    </div>

    <div class="sub-heading">7.2 ความเสียหายสิ่งของ</div>
    <div class="wall-row"><span class="fl">ด้านหน้า</span><input type="text" class="frpf-inp" name="rf_damage_obj_front" id="rfpdf_damage_obj_front"></div>
    <div class="wall-row"><span class="fl">ด้านซ้าย</span><input type="text" class="frpf-inp" name="rf_damage_obj_left" id="rfpdf_damage_obj_left"></div>
    <div class="wall-row"><span class="fl">ด้านขวา</span><input type="text" class="frpf-inp" name="rf_damage_obj_right" id="rfpdf_damage_obj_right"></div>
    <div class="wall-row"><span class="fl">ด้านหลัง</span><input type="text" class="frpf-inp" name="rf_damage_obj_back" id="rfpdf_damage_obj_back"></div>
    <div class="wall-row">
        <span class="fl">พื้น</span><input type="text" class="frpf-inp" name="rf_damage_obj_floor" id="rfpdf_damage_obj_floor">
        <span class="fl">หลังคา</span><input type="text" class="frpf-inp" name="rf_damage_obj_roof" id="rfpdf_damage_obj_roof">
        <span class="fl">เพดาน</span><input type="text" class="frpf-inp" name="rf_damage_obj_ceiling" id="rfpdf_damage_obj_ceiling">
    </div>

    <div class="sub-heading" style="margin-top:4px;">7.3 บริเวณจุดเริ่มต้นของเพลิง</div>
    <textarea class="frpf-ta" name="rf_first_area" id="rfpdf_first_area" rows="2"></textarea>

    <div class="sub-heading">7.4 สภาพสวิตช์ไฟฟ้า/อุปกรณ์ไฟฟ้า</div>
    <textarea class="frpf-ta" name="rf_switch_condition" id="rfpdf_switch_condition" rows="2"></textarea>

    <div class="sub-heading">7.5 ความเสียหายอาคารข้างเคียง</div>
    <div class="fr i2">
        <label class="ck"><input type="radio" class="frpf-cb" name="rf_adjacent_damage" value="พบ" id="rfpdf_adjacent_found">&nbsp;พบ</label>
        <label class="ck"><input type="radio" class="frpf-cb" name="rf_adjacent_damage" value="ไม่พบ" id="rfpdf_adjacent_notfound">&nbsp;ไม่พบ</label>
        <input type="text" class="frpf-inp" name="rf_adjacent_damage_detail" id="rfpdf_adjacent_damage_detail">
    </div>

    <div class="sub-heading">7.6 ร่องรอยและวัตถุพยานที่ตรวจพบในสถานที่เกิดเหตุ</div>
    <textarea class="frpf-ta" name="rf_evidence_found" id="rfpdf_evidence_found" rows="2"></textarea>

    <!-- 8. สรุปผลการตรวจ -->
    <div class="sec-heading" style="margin-top:6px;">8. <u>สรุปผลการตรวจ</u></div>
    <div class="sub-heading">8.1 บริเวณจุดเริ่มต้นของเพลิง</div>
    <textarea class="frpf-ta" name="rf_origin_area" id="rfpdf_origin_area" rows="2"></textarea>
    <div class="sub-heading">8.2 แหล่งเชื้อเพลิง</div>
    <textarea class="frpf-ta" name="rf_fuel_source" id="rfpdf_fuel_source" rows="2"></textarea>
    <div class="sub-heading">8.3 แหล่งความร้อน</div>
    <textarea class="frpf-ta" name="rf_heat_source" id="rfpdf_heat_source" rows="2"></textarea>
    <div class="sub-heading">8.4 อื่นๆ</div>
    <textarea class="frpf-ta" name="rf_summary_other" id="rfpdf_summary_other" rows="2"></textarea>

    <!-- 9. ความเห็น -->
    <div class="sec-heading" style="margin-top:6px;">9. <u>ความเห็น</u></div>
    <div class="fr i1"><span class="fl">จุดเริ่มต้นของเพลิง</span></div>
    <textarea class="frpf-ta" name="rf_opinion_first_area" id="rfpdf_opinion_first_area" rows="2"></textarea>
    <div class="fr i1">
        <span class="fl">สาเหตุของเพลิง</span>
        <label class="ck"><input type="radio" class="frpf-cb" name="rf_cause_type" value="เชื่อว่า" id="rfpdf_cause_believed">&nbsp;เชื่อว่า</label>
        <input type="text" class="frpf-inp" name="rf_cause_believed_detail" id="rfpdf_cause_believed_detail">
    </div>
    <div class="fr i1">
        <label class="ck" style="margin-left:100px;"><input type="radio" class="frpf-cb" name="rf_cause_type" value="ไม่ทราบสาเหตุ" id="rfpdf_cause_unknown">&nbsp;ไม่ทราบสาเหตุ</label>
        <input type="text" class="frpf-inp" name="rf_cause_unknown_detail" id="rfpdf_cause_unknown_detail">
    </div>

    <!-- การส่งมอบ -->
    <div style="margin-top: 10px;">
        <div class="fr i1">
            <span class="fl">เจ้าหน้าที่</span>
            <input type="text" class="frpf-inp" name="rf_handover_officer" id="rfpdf_handover_officer">
        </div>
        <div class="fr i1">
            <span class="fl">ตรวจสถานที่เกิดเหตุเสร็จสิ้น ส่งมอบสถานที่เกิดเหตุคืนให้กับ</span>
            <input type="text" class="frpf-inp" name="rf_handover_to" id="rfpdf_handover_to">
        </div>
        <div class="fr i1">
            <span class="fl">เมื่อวันที่</span>
            <input type="date" class="frpf-inp frpf-inp-m" name="rf_handover_date" id="rfpdf_handover_date">
            <span class="fl">เวลาประมาณ</span>
            <input type="time" class="frpf-inp frpf-inp-m" name="rf_handover_time" id="rfpdf_handover_time">
            <span class="fl">น.</span>
        </div>
    </div>

    <!-- ลงชื่อ -->
    <div class="signature-block">
        <div>ลงชื่อ ........................................ ผู้รายงาน</div>
        <div>(<input type="text" class="frpf-inp" name="rf_signer_name" id="rfpdf_signer_name" style="width:200px; text-align:center; display:inline-block;">)</div>
        <div>ตำแหน่ง <input type="text" class="frpf-inp" name="rf_signer_position" id="rfpdf_signer_position" style="width:200px; text-align:center; display:inline-block;"></div>
        <div>วันที่ <input type="date" class="frpf-inp" name="rf_sign_date" id="rfpdf_sign_date" style="width:180px; text-align:center; display:inline-block;"></div>
    </div>

    <div class="form-footer">
        <div></div>
        <div class="form-footer-right">รายงานการตรวจสถานที่เกิดเหตุคดีเพลิงไหม้</div>
    </div>
</div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_report_fire_pdf">
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
/* ========== Inspector Row Management ========== */
function rfpdfAddInspectorRow() {
    var container = document.getElementById('rfpdf_inspector_container');
    var count = container.querySelectorAll('.rfpdf-inspector-row').length + 1;
    // Build options from rpUsersList (loaded by standard form)
    var opts = '<option value="">-- เลือกผู้ตรวจ --</option>';
    if (typeof rpUsersList !== 'undefined' && rpUsersList.length > 0) {
        rpUsersList.forEach(function(u) {
            opts += '<option value="' + u.fullname + '">' + u.fullname + '</option>';
        });
    }
    var html = '<div class="fr i1 rfpdf-inspector-row">' +
        '<span class="fl rfpdf-inspector-num">5.' + count + '.</span>' +
        '<select class="frpf-select rp-inspector-select" name="rf_inspector_name[]">' + opts + '</select>' +
        '<span class="fl" style="margin-left:6px;">ตำแหน่ง</span>' +
        '<input type="text" class="frpf-inp rp-inspector-position" name="rf_inspector_position[]" placeholder="ตำแหน่ง" readonly>' +
        '<button type="button" class="frpf-del-btn" title="ลบ" onclick="this.closest(\'.rfpdf-inspector-row\').remove(); rfpdfRenumberInspectors();">✕</button>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
    // Init Select2 on the new select
    var $modal = $('#modalReportFirePdf');
    var $newSelect = $(container).find('.rfpdf-inspector-row:last .rp-inspector-select');
    $newSelect.select2({
        theme: 'bootstrap-5', width: '100%', placeholder: '-- เลือกผู้ตรวจ --',
        allowClear: true, dropdownParent: $modal
    });
}

function rfpdfRenumberInspectors() {
    var rows = document.querySelectorAll('#rfpdf_inspector_container .rfpdf-inspector-num');
    rows.forEach(function(el, idx) { el.textContent = '5.' + (idx + 1) + '.'; });
}

/* Init Select2 + populate options when PDF modal opens */
document.addEventListener('DOMContentLoaded', function() {
    $('#modalReportFirePdf').on('shown.bs.modal', function() {
        var $modal = $(this);
        if (typeof rpLoadUsers === 'function') {
            rpLoadUsers(function() {
                $modal.find('#rfpdf_inspector_container .rp-inspector-select').each(function() {
                    var current = $(this).val();
                    $(this).html(rpBuildUserOptions(current));
                });
                $modal.find('#rfpdf_inspector_container .rp-inspector-select').each(function() {
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
