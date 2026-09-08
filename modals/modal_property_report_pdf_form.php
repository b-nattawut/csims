<?php
/**
 * Modal: ร่างรายงานคดีทรัพย์ (ฟอร์มเสมือน PDF)
 * A4-paper style editable form — uses same rp_ field names as modal_report_property.php
 * Modal ID: modalReportPropertyPdf   Form ID: formReportPropertyPdf
 */
?>

<style>
/* ========== Property Report PDF Form — scoped to #modalReportPropertyPdf ========== */
#modalReportPropertyPdf .rppf-body { background: #bbb; }
#modalReportPropertyPdf .rppf-page {
    width: 210mm; min-height: 297mm; margin: 16px auto; padding: 12mm 15mm 10mm 15mm;
    background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.25); position: relative;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; color: #000;
    display: flex; flex-direction: column; page-break-after: always;
}
#modalReportPropertyPdf .rppf-page:last-child { page-break-after: auto; }

/* Row helpers */
#modalReportPropertyPdf .fr  { display: flex; flex-wrap: wrap; align-items: baseline; line-height: 1.9; width: 100%; }
#modalReportPropertyPdf .fl  { font-size: 14px; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportPropertyPdf .fl-b{ font-size: 14px; font-weight: 700; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportPropertyPdf .i1  { padding-left: 20px; }
#modalReportPropertyPdf .i2  { padding-left: 40px; }
#modalReportPropertyPdf .sec-heading  { font-weight: 700; font-size: 14px; margin-top: 2px; margin-bottom: 1px; }
#modalReportPropertyPdf .sub-heading  { font-weight: 600; font-size: 14px; margin-left: 20px; }
#modalReportPropertyPdf .form-title   { text-decoration: underline; text-align: center; font-size: 15px; font-weight: 700; margin: 6px auto 4px auto; }
#modalReportPropertyPdf .page-header  { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2px; padding-bottom: 4px; }
#modalReportPropertyPdf .form-footer  { margin-top: auto; padding-top: 6px; font-size: 10px; color: #333; display: flex; justify-content: space-between; align-items: flex-end; }
#modalReportPropertyPdf .form-footer-left  { flex: 1; }
#modalReportPropertyPdf .form-footer-right { text-align: right; white-space: nowrap; line-height: 1.4; }
#modalReportPropertyPdf .sub-heading-bottom { font-weight: 600; font-size: 11px; margin-left: 20px; }
#modalReportPropertyPdf .signature-block { margin-top: 30px; text-align: center; padding-left: 50%; line-height: 2; }
#modalReportPropertyPdf .blank-line { width: 100%; border-bottom: 1px dotted #888; height: 22px; margin-bottom: 0; }

/* Editable input styles — dotted bottom, transparent */
#modalReportPropertyPdf .rppf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; min-width: 40px; flex: 1; margin: 0 4px; color: #000;
}
#modalReportPropertyPdf .rppf-inp:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }
#modalReportPropertyPdf .rppf-inp-s { width: 60px; max-width: 80px; text-align: center; flex: 0 0 auto; }
#modalReportPropertyPdf .rppf-inp-m { flex: 0 1 140px; min-width: 80px; text-align: center; }
#modalReportPropertyPdf .rppf-inp-l { flex: 1; min-width: 160px; }
#modalReportPropertyPdf .rppf-inp-s,
#modalReportPropertyPdf .rppf-inp-m,
#modalReportPropertyPdf .rppf-inp-l {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; margin: 0 4px; color: #000;
}
#modalReportPropertyPdf .rppf-inp-s:focus,
#modalReportPropertyPdf .rppf-inp-m:focus,
#modalReportPropertyPdf .rppf-inp-l:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Textarea — dotted lines background */
#modalReportPropertyPdf .rppf-ta {
    width: 100%; border: none; border-bottom: 1px dotted #888;
    background: transparent; outline: none; resize: vertical;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.8;
    padding: 0 4px; min-height: 24px; margin: 0 4px 2px 20px; color: #000;
    background-image: repeating-linear-gradient(transparent, transparent 27px, #ddd 27px, #ddd 28px);
    background-position: 0 0;
}
#modalReportPropertyPdf .rppf-ta:focus { border-bottom-color: #0d6efd; }

/* Checkbox — square style like PDF preview */
#modalReportPropertyPdf .ck { display: inline-flex; align-items: baseline; margin-right: 8px; font-size: 14px; white-space: nowrap; }
#modalReportPropertyPdf .rppf-cb {
    -webkit-appearance: none; appearance: none;
    width: 14px; height: 14px; margin-right: 3px; cursor: pointer; position: relative; top: 2px;
    border: 1.5px solid #000; border-radius: 0; background: #fff;
}
#modalReportPropertyPdf .rppf-cb:checked { background: #fff; }
#modalReportPropertyPdf .rppf-cb:checked::after {
    content: '✓'; position: absolute; top: -3px; left: 1px;
    font-size: 13px; font-weight: bold; color: #000; line-height: 1;
}

/* Add/Remove inspector buttons */
#modalReportPropertyPdf .rppf-add-btn {
    font-size: 12px; padding: 1px 8px; border: 1px dashed #0d6efd; color: #0d6efd;
    background: transparent; border-radius: 4px; cursor: pointer; margin-left: 20px; margin-top: 2px;
}
#modalReportPropertyPdf .rppf-add-btn:hover { background: rgba(13,110,253,.08); }
#modalReportPropertyPdf .rppf-del-btn {
    font-size: 11px; padding: 0 5px; border: none; color: #dc3545; background: transparent; cursor: pointer; margin-left: 4px;
}

/* Select dropdown for inspector — blend with dotted-line style */
#modalReportPropertyPdf .rppf-select {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 26px; flex: 1; margin: 0 4px; color: #000;
    cursor: pointer; min-width: 120px;
}
#modalReportPropertyPdf .rppf-select:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Select2 inside PDF form — compact */
#modalReportPropertyPdf .rppdf-inspector-row .select2-container { flex: 1; min-width: 120px; margin: 0 4px; }
#modalReportPropertyPdf .rppdf-inspector-row .select2-container--bootstrap-5 .select2-selection {
    min-height: 26px !important; height: 26px !important; padding: 0 4px !important;
    border: none !important; border-bottom: 1px dotted #888 !important; border-radius: 0 !important;
    background: transparent !important; font-size: 14px !important;
}
#modalReportPropertyPdf .rppdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    padding: 0 !important; line-height: 26px !important; font-size: 14px !important; color: #000 !important;
}
#modalReportPropertyPdf .rppdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
    height: 26px !important;
}

/* Add/Remove room/stolen/evidence buttons */
#modalReportPropertyPdf .rppf-add-block-btn {
    font-size: 12px; padding: 1px 8px; border: 1px dashed #0d6efd; color: #0d6efd;
    background: transparent; border-radius: 4px; cursor: pointer; margin-left: 40px; margin-top: 2px;
}
#modalReportPropertyPdf .rppf-add-block-btn:hover { background: rgba(13,110,253,.08); }
#modalReportPropertyPdf .rppf-del-block-btn {
    font-size: 11px; padding: 0 5px; border: none; color: #dc3545; background: transparent; cursor: pointer; margin-left: 4px;
}

/* Room block styling */
#modalReportPropertyPdf .rppdf-room-block { border-left: 2px solid #0d6efd; padding-left: 10px; margin-left: 40px; margin-bottom: 6px; }
#modalReportPropertyPdf .rppdf-stolen-block { border-left: 2px solid #666; padding-left: 10px; margin-left: 40px; margin-bottom: 6px; }
#modalReportPropertyPdf .rppdf-evidence-block { border-left: 2px solid #666; padding-left: 10px; margin-left: 40px; margin-bottom: 6px; }
</style>

<div class="modal fade" id="modalReportPropertyPdf" aria-labelledby="modalReportPropertyPdfLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 240mm;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportPropertyPdfLabel">
                    รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body rppf-body p-0" style="max-height: 80vh; overflow-y: auto;">
                <form id="formReportPropertyPdf" novalidate>
                    <input type="hidden" id="rppdf_incident_id" name="incident_id">

                    <!-- Switch back to standard form -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="rp_editInfoPdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="rp_editCountPdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchPropertyReportToStd" checked
                                   style="width: 3rem; height: 1.5rem; cursor: pointer;"
                                   onchange="if(!this.checked){ this.checked=true; switchPropertyReportToStandard(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchPropertyReportToStd" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ==================== PAGE 1 ==================== -->
<div class="rppf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <input type="text" class="rppf-inp rppf-inp-s" name="rp_report_no_display_pdf" id="rppdf_report_no_display" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="rppf-inp rppf-inp-s" id="rppdf_report_year" readonly style="display:inline-block; width:40px;" tabindex="-1">
        </div>
        <div style="font-size:13px; text-align:right;">1/4</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="rppf-inp" name="rp_agency_name" id="rppdf_agency_name">
    </div>

    <div class="form-title">รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์</div>

    <!-- 1. การรับแจ้งเหตุ -->
    <div class="sec-heading">1. <u>การรับแจ้งเหตุ</u></div>
    <div class="fr i1" style="flex-wrap:nowrap;">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="rppf-inp rppf-inp-m" name="rp_receive_date" id="rppdf_receive_date">
        <span class="fl">เวลา</span>
        <input type="time" class="rppf-inp rppf-inp-m" name="rp_receive_time" id="rppdf_receive_time">
        <span class="fl">น. ตามประจำวันข้อที่</span>
        <input type="text" class="rppf-inp" name="rp_daily_ref" id="rppdf_daily_ref" style="min-width:60px;">
    </div>
    <div class="fr i1">
        <span class="fl">ได้รับแจ้งตาม&nbsp;&nbsp;</span>
        <label class="ck"><input type="checkbox" class="rppf-cb" name="rp_notify_method[]" value="หนังสือ" id="rppdf_notify_letter">&nbsp;หนังสือ</label>
        <span class="fl">&nbsp;/&nbsp;</span>
        <label class="ck"><input type="checkbox" class="rppf-cb" name="rp_notify_method[]" value="โทรศัพท์" id="rppdf_notify_phone">&nbsp;ทางโทรศัพท์</label>
        <span class="fl">&nbsp;/&nbsp;</span>
        <label class="ck"><input type="checkbox" class="rppf-cb" name="rp_notify_method[]" value="วิทยุสื่อสาร" id="rppdf_notify_radio">&nbsp;วิทยุสื่อสาร</label>
    </div>
    <div class="fr i1">
        <span class="fl">จาก สน./สภ.</span>
        <input type="text" class="rppf-inp rppf-inp-l" name="rp_from_station" id="rppdf_from_station">
        <span class="fl">ขอเจ้าหน้าที่ ร่วมตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์</span>
    </div>
    <div class="fr i1">
        <span class="fl">โดยมี</span>
        <input type="text" class="rppf-inp" name="rp_investigator" id="rppdf_investigator">
        <span class="fl">เป็นพนักงานสอบสวนเจ้าของคดี</span>
    </div>

    <!-- 2. สถานที่เกิดเหตุ -->
    <div class="sec-heading">2. <u>สถานที่เกิดเหตุ</u></div>
    <div class="fr i1">
        <input type="text" class="rppf-inp" name="rp_crime_location" id="rppdf_crime_location">
    </div>
    <div class="fr i1">
        <span class="fl">เจ้าของบ้าน/ผู้เสียหาย/อื่นๆ</span>
        <input type="text" class="rppf-inp" name="rp_victim_name" id="rppdf_victim_name">
        <span class="fl">อายุประมาณ</span>
        <input type="text" class="rppf-inp rppf-inp-s" name="rp_victim_age" id="rppdf_victim_age">
        <span class="fl">ปี</span>
    </div>

    <!-- 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ -->
    <div class="sec-heading">3. <u>วันเวลาที่ทราบเหตุ/เกิดเหตุ</u></div>
    <div class="fr i1">
        <span class="fl">ผู้เสียหายทราบเหตุ/เกิดเหตุ เมื่อวันที่</span>
        <input type="date" class="rppf-inp rppf-inp-m" name="rp_victim_know_date" id="rppdf_victim_know_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rppf-inp rppf-inp-m" name="rp_victim_know_time" id="rppdf_victim_know_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">พนักงานสอบสวนทราบเหตุ เมื่อวันที่</span>
        <input type="date" class="rppf-inp rppf-inp-m" name="rp_officer_know_date" id="rppdf_officer_know_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rppf-inp rppf-inp-m" name="rp_officer_know_time" id="rppdf_officer_know_time">
        <span class="fl">น.</span>
    </div>

    <!-- 4. วันเวลาตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading">4. <u>วันเวลาตรวจสถานที่เกิดเหตุ</u></div>
    <div class="fr i1">
        <span class="fl">ตรวจสถานที่เกิดเหตุ เมื่อวันที่</span>
        <input type="date" class="rppf-inp rppf-inp-m" name="rp_inspect_date" id="rppdf_inspect_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rppf-inp rppf-inp-m" name="rp_inspect_time" id="rppdf_inspect_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">ตรวจสถานที่เกิดเหตุเพิ่มเติม เมื่อวันที่</span>
        <input type="date" class="rppf-inp rppf-inp-m" name="rp_inspect_add_date" id="rppdf_inspect_add_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rppf-inp rppf-inp-m" name="rp_inspect_add_time" id="rppdf_inspect_add_time">
        <span class="fl">น.</span>
    </div>

    <!-- 5. ผู้ตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading">5. <u>ผู้ตรวจสถานที่เกิดเหตุ</u></div>
    <div id="rppdf_inspector_container">
        <div class="fr i1 rppdf-inspector-row">
            <span class="fl rppdf-inspector-num">5.1.</span>
            <select class="rppf-select rp-inspector-select" name="rp_inspector_name[]">
                <option value="">-- เลือกผู้ตรวจ --</option>
            </select>
            <span class="fl" style="margin-left:6px;">ตำแหน่ง</span>
            <input type="text" class="rppf-inp rp-inspector-position" name="rp_inspector_position[]" placeholder="ตำแหน่ง" readonly>
            <button type="button" class="rppf-del-btn" title="ลบ" onclick="this.closest('.rppdf-inspector-row').remove(); rppdfRenumberInspectors();">✕</button>
        </div>
    </div>
    <button type="button" class="rppf-add-btn" onclick="rppdfAddInspectorRow();">+ เพิ่มผู้ตรวจ</button>

    <!-- 6. ลักษณะของสถานที่เกิดเหตุ -->
    <div class="sec-heading">6. <u>ลักษณะของสถานที่เกิดเหตุ</u></div>
    <div class="sub-heading">6.1 ลักษณะภายนอก</div>
    <div class="fr i2">
        <span class="fl">เป็นบ้าน/ตึกแถว/อาคาร/อื่น ๆ</span>
        <input type="text" class="rppf-inp" name="rp_building_type_text_pdf" id="rppdf_building_type_text">
        <span class="fl">ชั้น จำนวน</span>
        <input type="text" class="rppf-inp rppf-inp-s" name="rp_floor_count" id="rppdf_floor_count">
        <span class="fl">หลัง/คูหา</span>
        <input type="text" class="rppf-inp rppf-inp-s" name="rp_unit_count" id="rppdf_unit_count">
    </div>
    <div class="fr i2">
        <label class="ck"><input type="radio" class="rppf-cb" name="rp_mezzanine_pdf" value="มี" id="rppdf_mezzanine_yes">&nbsp;มี</label>
        <span class="fl">/&nbsp;</span>
        <label class="ck"><input type="radio" class="rppf-cb" name="rp_mezzanine_pdf" value="ไม่มี" id="rppdf_mezzanine_no">&nbsp;ไม่มีชั้นลอย</label>
        <span style="width:20px;"></span>
        <label class="ck"><input type="radio" class="rppf-cb" name="rp_rooftop_pdf" value="มี" id="rppdf_rooftop_yes">&nbsp;มี</label>
        <span class="fl">/&nbsp;</span>
        <label class="ck"><input type="radio" class="rppf-cb" name="rp_rooftop_pdf" value="ไม่มี" id="rppdf_rooftop_no">&nbsp;ไม่มีดาดฟ้า</label>
    </div>
    <div class="fr i2">
        <span class="fl">ปลูกอยู่ภายในบริเวณ&nbsp;</span>
        <label class="ck"><input type="radio" class="rppf-cb" name="rp_fence_pdf" value="มี" id="rppdf_fence_yes">&nbsp;มี</label>
        <span class="fl">/&nbsp;</span>
        <label class="ck"><input type="radio" class="rppf-cb" name="rp_fence_pdf" value="ไม่มี" id="rppdf_fence_no">&nbsp;ไม่มี</label>
        <span class="fl">&nbsp;รั้วล้อมรอบ เมื่อหันหน้าเข้า</span>
    </div>
    <div class="fr i2"><span class="fl">ด้านหน้าติด</span><input type="text" class="rppf-inp" name="rp_ext_front" id="rppdf_ext_front"></div>
    <div class="fr i2"><span class="fl">ด้านซ้ายติด</span><input type="text" class="rppf-inp" name="rp_ext_left" id="rppdf_ext_left"></div>
    <div class="fr i2"><span class="fl">ด้านขวาติด</span><input type="text" class="rppf-inp" name="rp_ext_right" id="rppdf_ext_right"></div>
    <div class="fr i2"><span class="fl">ด้านหลังติด</span><input type="text" class="rppf-inp" name="rp_ext_back" id="rppdf_ext_back"></div>

    <div class="sub-heading">6.2 ลักษณะภายใน</div>
    <textarea class="rppf-ta" name="rp_interior_detail" id="rppdf_interior_detail" rows="2"></textarea>

    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom"><div>ปฏิบัติงานตาม</div><div>OPFS – CS – SP – 01</div></div>
        <div class="form-footer-right sub-heading-bottom">F-CS-12 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ==================== PAGE 2 ==================== -->
<div class="rppf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <span class="rppf-inp rppf-inp-s" style="width:auto;display:inline-block;min-width:80px; max-width:200px;border-bottom:1px dotted #888;text-align:center;" id="rppdf_report_no_p2"></span>
            &nbsp;/25&nbsp;
            <span class="rppf-inp rppf-inp-s" style="width:auto;display:inline-block;width:40px;border-bottom:1px dotted #888;text-align:center;" id="rppdf_report_year_p2"></span>
        </div>
        <div style="font-size:13px; text-align:right;">2/4</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <span class="rppf-inp" style="border-bottom:1px dotted #888;" id="rppdf_agency_name_p2"></span>
    </div>
    <br>
    <div class="sub-heading" style="margin-top:4px;">6.3 บริเวณที่เกิดเหตุ เกิดเหตุที่</div>
    <textarea class="rppf-ta" name="rp_incident_area" id="rppdf_incident_area" rows="2" style="margin-left:40px;"></textarea>

    <!-- 7. ผลการตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading" style="margin-top:6px;">7. <u>ผลการตรวจสถานที่เกิดเหตุ</u></div>
    <div class="fr i1"><span class="fl">พฤติการณ์ของคดี</span></div>
    <textarea class="rppf-ta" name="rp_case_behavior" id="rppdf_case_behavior" rows="3"></textarea>

    <div class="fr i1" style="margin-top:4px;"><span class="fl">จากการตรวจสถานที่เกิดเหตุ</span></div>
    <div class="sub-heading">7.1 สภาพของสถานที่เกิดเหตุเมื่อไปถึง</div>
    <textarea class="rppf-ta" name="rp_scene_condition" id="rppdf_scene_condition" rows="3" style="margin-left:40px;"></textarea>

    <div class="sub-heading" style="margin-top:4px;">7.2 ทางเข้าของคนร้าย</div>
    <textarea class="rppf-ta" name="rp_criminal_entry" id="rppdf_criminal_entry" rows="3" style="margin-left:40px;"></textarea>

    <!-- 7.3 ห้อง -->
    <div class="sub-heading" style="margin-top:4px;">7.3 คนร้ายได้เข้ามาในห้องต่าง ๆ ดังนี้</div>
    <div id="rppdf_room_container">
        <div class="rppdf-room-block">
            <div class="fr" style="align-items:center;">
                <span class="fl-b rppdf-room-num">7.3.1</span>
                <span class="fl">&nbsp;ที่ห้อง</span>
                <input type="text" class="rppf-inp" name="rp_room_name[]" placeholder="ชื่อห้อง">
                <button type="button" class="rppf-del-block-btn" title="ลบ" onclick="this.closest('.rppdf-room-block').remove(); rppdfRenumberRooms();">✕</button>
            </div>
            <div class="fr" style="padding-left:10px;">
                <span class="fl rppdf-room-sub">7.3.1</span><span class="fl">.1 ทางเข้าของคนร้าย พบ/ไม่พบรอยจัดที่</span>
            </div>
            <textarea class="rppf-ta" name="rp_room_entry[]" rows="1" style="margin-left:10px;"></textarea>
            <div class="fr" style="padding-left:10px;">
                <span class="fl rppdf-room-sub">7.3.1</span><span class="fl">.2 พบรอยจัดที่</span>
            </div>
            <textarea class="rppf-ta" name="rp_room_marks[]" rows="1" style="margin-left:10px;"></textarea>
            <div class="fr" style="padding-left:10px;">
                <span class="fl rppdf-room-sub">7.3.1</span><span class="fl">.3 พบร่องรอยรื้อค้นที่</span>
            </div>
            <textarea class="rppf-ta" name="rp_room_search_marks[]" rows="1" style="margin-left:10px;"></textarea>
            <div class="fr" style="padding-left:10px;">
                <span class="fl rppdf-room-sub">7.3.1</span><span class="fl">.4 วัตถุพยานอื่นๆที่ตรวจพบ</span>
            </div>
            <textarea class="rppf-ta" name="rp_room_other_evidence[]" rows="1" style="margin-left:10px;"></textarea>
        </div>
    </div>
    <button type="button" class="rppf-add-block-btn" onclick="rppdfAddRoomBlock();">+ เพิ่มห้อง</button>

    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom"><div>ปฏิบัติงานตาม</div><div>OPFS – CS – SP – 01</div></div>
        <div class="form-footer-right sub-heading-bottom">F-CS-12 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ==================== PAGE 3 ==================== -->
<div class="rppf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <span class="rppf-inp-s" style="width:auto;display:inline-block;min-width:80px; max-width:200px;border-bottom:1px dotted #888;text-align:center;" id="rppdf_report_no_p3"></span>
            &nbsp;/25&nbsp;
            <span class="rppf-inp-s" style="width:auto;display:inline-block;width:40px;border-bottom:1px dotted #888;text-align:center;" id="rppdf_report_year_p3"></span>
        </div>
        <div style="font-size:13px; text-align:right;">3/4</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <span class="rppf-inp" style="border-bottom:1px dotted #888;" id="rppdf_agency_name_p3"></span>
    </div>
    <br>
    <!-- 7.4 ทรัพย์สินที่ถูกโจรกรรม -->
    <div class="sub-heading" style="margin-top:6px;">7.4 ในเบื้องต้น แจ้งว่า คนร้ายโจรกรรมทรัพย์สินไปดังนี้</div>
    <div id="rppdf_stolen_container">
        <div class="rppdf-stolen-block">
            <div class="fr" style="align-items:center;">
                <span class="fl-b rppdf-stolen-num">7.4.1</span>
                <span class="fl">&nbsp;นาย/นาง/นางสาว/อื่น ๆ</span>
                <select class="rppf-select" name="rp_stolen_victim_prefix[]" style="max-width:80px; flex:0 0 80px;">
                    <option value="">--</option>
                    <option value="นาย">นาย</option>
                    <option value="นาง">นาง</option>
                    <option value="นางสาว">นางสาว</option>
                    <option value="อื่นๆ">อื่นๆ</option>
                </select>
                <input type="text" class="rppf-inp" name="rp_stolen_victim_name[]" placeholder="ชื่อ-นามสกุล">
                <span class="fl">สถานะ</span>
                <select class="rppf-select" name="rp_stolen_victim_role[]" style="max-width:120px; flex:0 0 120px;">
                    <option value="">--</option>
                    <option value="เจ้าของบ้าน">เจ้าของบ้าน</option>
                    <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                    <option value="อื่นๆ">อื่นๆ</option>
                </select>
                <button type="button" class="rppf-del-block-btn" title="ลบ" onclick="this.closest('.rppdf-stolen-block').remove(); rppdfRenumberStolen();">✕</button>
            </div>
            <div class="fr" style="padding-left:10px;">
                <span class="fl">แจ้งว่าสูญเสียทรัพย์สินดังนี้</span>
            </div>
            <textarea class="rppf-ta" name="rp_stolen_item_text[]" rows="2" style="margin-left:10px;" placeholder="รายการทรัพย์สิน (แต่ละรายการขึ้นบรรทัดใหม่)"></textarea>
        </div>
    </div>
    <button type="button" class="rppf-add-block-btn" onclick="rppdfAddStolenBlock();">+ เพิ่มผู้เสียหาย / ทรัพย์สิน</button>

    <div style="margin-top:6px;"></div>

    <!-- 7.5 วัตถุพยานที่ตรวจเก็บ -->
    <div class="sub-heading" style="margin-top:6px;">7.5 วัตถุพยานที่ตรวจเก็บในสถานที่เกิดเหตุ</div>
    <div id="rppdf_evidence_container">
        <div class="rppdf-evidence-block">
            <div class="fr" style="align-items:center;">
                <span class="fl-b rppdf-evidence-num">7.5.1</span>
                <span class="fl">&nbsp;ตรวจเก็บวัตถุพยาน รอยลายนิ้วมือแฝง/ฝ่ามือแฝง/ฝ่าเท้าแฝง จำนวน</span>
                <input type="text" class="rppf-inp rppf-inp-s" name="rp_evidence_count[]">
                <span class="fl">แผ่น/ชิ้น</span>
                <button type="button" class="rppf-del-block-btn" title="ลบ" onclick="this.closest('.rppdf-evidence-block').remove(); rppdfRenumberEvidence();">✕</button>
            </div>
            <div class="fr" style="padding-left:10px;">
                <span class="fl">ที่ (รายละเอียดสถานที่ตรวจพบ)</span>
            </div>
            <textarea class="rppf-ta" name="rp_evidence_loc_text[]" rows="2" style="margin-left:10px;" placeholder="รายละเอียดสถานที่ตรวจพบ (แต่ละรายการขึ้นบรรทัดใหม่)"></textarea>
            <div class="fr" style="padding-left:10px;">
                <span class="fl">และได้ให้ลงลายมือชื่อไว้เป็นหลักฐาน</span>
                <select class="rppf-select" name="rp_evidence_signer_prefix[]" style="max-width:80px; flex:0 0 80px;">
                    <option value="">--</option>
                    <option value="นาย">นาย</option>
                    <option value="นาง">นาง</option>
                    <option value="นางสาว">นางสาว</option>
                    <option value="อื่นๆ">อื่นๆ</option>
                </select>
                <input type="text" class="rppf-inp" name="rp_evidence_signer_name[]" placeholder="ชื่อ-นามสกุล">
                <span class="fl">สถานะ</span>
                <select class="rppf-select" name="rp_evidence_signer_role[]" style="max-width:120px; flex:0 0 120px;">
                    <option value="">--</option>
                    <option value="เจ้าของบ้าน">เจ้าของบ้าน</option>
                    <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                    <option value="อื่นๆ">อื่นๆ</option>
                </select>
            </div>
        </div>
    </div>
    <button type="button" class="rppf-add-block-btn" onclick="rppdfAddEvidenceBlock();">+ เพิ่มวัตถุพยาน</button>

    <div style="margin-top:6px;"></div>

    <!-- 7.6 การดำเนินการเกี่ยวกับวัตถุพยาน -->
    <div class="sub-heading" style="margin-top:6px;">7.6 การดำเนินการเกี่ยวกับวัตถุพยาน</div>
    <textarea class="rppf-ta" name="rp_evidence_action" id="rppdf_evidence_action" rows="2" style="margin-left:40px;"></textarea>

    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom"><div>ปฏิบัติงานตาม</div><div>OPFS – CS – SP – 01</div></div>
        <div class="form-footer-right sub-heading-bottom">F-CS-12 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ==================== PAGE 4 ==================== -->
<div class="rppf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <span class="rppf-inp-s" style="width:auto;display:inline-block;min-width:80px; max-width:200px;border-bottom:1px dotted #888;text-align:center;" id="rppdf_report_no_p4"></span>
            &nbsp;/25&nbsp;
            <span class="rppf-inp-s" style="width:auto;display:inline-block;width:40px;border-bottom:1px dotted #888;text-align:center;" id="rppdf_report_year_p4"></span>
        </div>
        <div style="font-size:13px; text-align:right;">4/4</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <span class="rppf-inp" style="border-bottom:1px dotted #888;" id="rppdf_agency_name_p4"></span>
    </div>
    <br>
    <!-- 7.7 การส่งมอบสถานที่เกิดเหตุ -->
    <div class="sub-heading" style="margin-top:6px;">7.7 เจ้าหน้าที่</div>
    <div class="fr i2">
        <select class="rppf-select" name="rp_handover_agency" id="rppdf_handover_agency" style="max-width:120px; flex:0 0 120px;">
            <option value="">-- เลือก --</option>
            <option value="กสก.พฐก.">กสก.พฐก.</option>
            <option value="กลก.ศพฐ.">กลก.ศพฐ.</option>
            <option value="พฐ.จว.">พฐ.จว.</option>
        </select>
        <input type="text" class="rppf-inp" name="rp_handover_detail" id="rppdf_handover_detail">
        <span class="fl">ตรวจสถานที่เกิดเหตุเสร็จสิ้น</span>
    </div>
    <div class="fr i2" style="margin-top:2px;">
        <span class="fl">พร้อมทั้งส่งมอบสถานที่เกิดเหตุคืนให้กับ</span>
        <input type="text" class="rppf-inp" name="rp_handover_to" id="rppdf_handover_to">
    </div>
    <div class="fr i2">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="rppf-inp rppf-inp-m" name="rp_handover_date" id="rppdf_handover_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rppf-inp rppf-inp-m" name="rp_handover_time" id="rppdf_handover_time" style="text-align:center;">
        <span class="fl">น.</span>
    </div>

    <!-- ลงชื่อ -->
    <div class="signature-block">
        <div>(ลงชื่อ) ............................................................</div>
        <div>(<input type="text" class="rppf-inp" name="rp_signer_name" id="rppdf_signer_name" style="width:200px; text-align:center; display:inline-block;">)</div>
        <div>(ตำแหน่ง) <input type="text" class="rppf-inp" name="rp_signer_position" id="rppdf_signer_position" style="width:200px; text-align:center; display:inline-block;"></div>
        <div>วันที่ <input type="date" class="rppf-inp" name="rp_sign_date" id="rppdf_sign_date" style="width:180px; text-align:center; display:inline-block;"></div>
    </div>

    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom"><div>ปฏิบัติงานตาม</div><div>OPFS – CS – SP – 01</div></div>
        <div class="form-footer-right sub-heading-bottom">F-CS-12 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_report_property_pdf">
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
function rppdfAddInspectorRow() {
    var container = document.getElementById('rppdf_inspector_container');
    var count = container.querySelectorAll('.rppdf-inspector-row').length + 1;
    var opts = '<option value="">-- เลือกผู้ตรวจ --</option>';
    if (typeof rpUsersList !== 'undefined' && rpUsersList.length > 0) {
        rpUsersList.forEach(function(u) {
            opts += '<option value="' + u.fullname + '">' + u.fullname + '</option>';
        });
    }
    var html = '<div class="fr i1 rppdf-inspector-row">' +
        '<span class="fl rppdf-inspector-num">5.' + count + '.</span>' +
        '<select class="rppf-select rp-inspector-select" name="rp_inspector_name[]">' + opts + '</select>' +
        '<span class="fl" style="margin-left:6px;">ตำแหน่ง</span>' +
        '<input type="text" class="rppf-inp rp-inspector-position" name="rp_inspector_position[]" placeholder="ตำแหน่ง" readonly>' +
        '<button type="button" class="rppf-del-btn" title="ลบ" onclick="this.closest(\'.rppdf-inspector-row\').remove(); rppdfRenumberInspectors();">✕</button>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
    var $modal = $('#modalReportPropertyPdf');
    var $newSelect = $(container).find('.rppdf-inspector-row:last .rp-inspector-select');
    $newSelect.select2({
        theme: 'bootstrap-5', width: '100%', placeholder: '-- เลือกผู้ตรวจ --',
        allowClear: true, dropdownParent: $modal
    });
}

function rppdfRenumberInspectors() {
    var rows = document.querySelectorAll('#rppdf_inspector_container .rppdf-inspector-num');
    rows.forEach(function(el, idx) { el.textContent = '5.' + (idx + 1) + '.'; });
}

/* ========== Room Block Management ========== */
function rppdfAddRoomBlock() {
    var container = document.getElementById('rppdf_room_container');
    var count = container.querySelectorAll('.rppdf-room-block').length + 1;
    var num = '7.3.' + count;
    var html = '<div class="rppdf-room-block">' +
        '<div class="fr" style="align-items:center;">' +
        '<span class="fl-b rppdf-room-num">' + num + '</span>' +
        '<span class="fl">&nbsp;ที่ห้อง</span>' +
        '<input type="text" class="rppf-inp" name="rp_room_name[]" placeholder="ชื่อห้อง">' +
        '<button type="button" class="rppf-del-block-btn" title="ลบ" onclick="this.closest(\'.rppdf-room-block\').remove(); rppdfRenumberRooms();">✕</button>' +
        '</div>' +
        '<div class="fr" style="padding-left:10px;"><span class="fl rppdf-room-sub">' + num + '</span><span class="fl">.1 ทางเข้าของคนร้าย พบ/ไม่พบรอยจัดที่</span></div>' +
        '<textarea class="rppf-ta" name="rp_room_entry[]" rows="1" style="margin-left:10px;"></textarea>' +
        '<div class="fr" style="padding-left:10px;"><span class="fl rppdf-room-sub">' + num + '</span><span class="fl">.2 พบรอยจัดที่</span></div>' +
        '<textarea class="rppf-ta" name="rp_room_marks[]" rows="1" style="margin-left:10px;"></textarea>' +
        '<div class="fr" style="padding-left:10px;"><span class="fl rppdf-room-sub">' + num + '</span><span class="fl">.3 พบร่องรอยรื้อค้นที่</span></div>' +
        '<textarea class="rppf-ta" name="rp_room_search_marks[]" rows="1" style="margin-left:10px;"></textarea>' +
        '<div class="fr" style="padding-left:10px;"><span class="fl rppdf-room-sub">' + num + '</span><span class="fl">.4 วัตถุพยานอื่นๆที่ตรวจพบ</span></div>' +
        '<textarea class="rppf-ta" name="rp_room_other_evidence[]" rows="1" style="margin-left:10px;"></textarea>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
}

function rppdfRenumberRooms() {
    var blocks = document.querySelectorAll('#rppdf_room_container .rppdf-room-block');
    blocks.forEach(function(block, idx) {
        var num = '7.3.' + (idx + 1);
        block.querySelector('.rppdf-room-num').textContent = num;
        block.querySelectorAll('.rppdf-room-sub').forEach(function(el) { el.textContent = num; });
    });
}

/* ========== Stolen Block Management ========== */
function rppdfAddStolenBlock() {
    var container = document.getElementById('rppdf_stolen_container');
    var count = container.querySelectorAll('.rppdf-stolen-block').length + 1;
    var num = '7.4.' + count;
    var html = '<div class="rppdf-stolen-block">' +
        '<div class="fr" style="align-items:center;">' +
        '<span class="fl-b rppdf-stolen-num">' + num + '</span>' +
        '<span class="fl">&nbsp;นาย/นาง/นางสาว/อื่น ๆ</span>' +
        '<select class="rppf-select" name="rp_stolen_victim_prefix[]" style="max-width:80px; flex:0 0 80px;">' +
        '<option value="">--</option><option value="นาย">นาย</option><option value="นาง">นาง</option><option value="นางสาว">นางสาว</option><option value="อื่นๆ">อื่นๆ</option></select>' +
        '<input type="text" class="rppf-inp" name="rp_stolen_victim_name[]" placeholder="ชื่อ-นามสกุล">' +
        '<span class="fl">สถานะ</span>' +
        '<select class="rppf-select" name="rp_stolen_victim_role[]" style="max-width:120px; flex:0 0 120px;">' +
        '<option value="">--</option><option value="เจ้าของบ้าน">เจ้าของบ้าน</option><option value="ผู้เสียหาย">ผู้เสียหาย</option><option value="อื่นๆ">อื่นๆ</option></select>' +
        '<button type="button" class="rppf-del-block-btn" title="ลบ" onclick="this.closest(\'.rppdf-stolen-block\').remove(); rppdfRenumberStolen();">✕</button>' +
        '</div>' +
        '<div class="fr" style="padding-left:10px;"><span class="fl">แจ้งว่าสูญเสียทรัพย์สินดังนี้</span></div>' +
        '<textarea class="rppf-ta" name="rp_stolen_item_text[]" rows="2" style="margin-left:10px;" placeholder="รายการทรัพย์สิน (แต่ละรายการขึ้นบรรทัดใหม่)"></textarea>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
}

function rppdfRenumberStolen() {
    var blocks = document.querySelectorAll('#rppdf_stolen_container .rppdf-stolen-block');
    blocks.forEach(function(block, idx) {
        block.querySelector('.rppdf-stolen-num').textContent = '7.4.' + (idx + 1);
    });
}

/* ========== Evidence Block Management ========== */
function rppdfAddEvidenceBlock() {
    var container = document.getElementById('rppdf_evidence_container');
    var count = container.querySelectorAll('.rppdf-evidence-block').length + 1;
    var num = '7.5.' + count;
    var html = '<div class="rppdf-evidence-block">' +
        '<div class="fr" style="align-items:center;">' +
        '<span class="fl-b rppdf-evidence-num">' + num + '</span>' +
        '<span class="fl">&nbsp;ตรวจเก็บวัตถุพยาน รอยลายนิ้วมือแฝง/ฝ่ามือแฝง/ฝ่าเท้าแฝง จำนวน</span>' +
        '<input type="text" class="rppf-inp rppf-inp-s" name="rp_evidence_count[]">' +
        '<span class="fl">แผ่น/ชิ้น</span>' +
        '<button type="button" class="rppf-del-block-btn" title="ลบ" onclick="this.closest(\'.rppdf-evidence-block\').remove(); rppdfRenumberEvidence();">✕</button>' +
        '</div>' +
        '<div class="fr" style="padding-left:10px;"><span class="fl">ที่ (รายละเอียดสถานที่ตรวจพบ)</span></div>' +
        '<textarea class="rppf-ta" name="rp_evidence_loc_text[]" rows="2" style="margin-left:10px;" placeholder="รายละเอียดสถานที่ตรวจพบ (แต่ละรายการขึ้นบรรทัดใหม่)"></textarea>' +
        '<div class="fr" style="padding-left:10px;">' +
        '<span class="fl">และได้ให้ลงลายมือชื่อไว้เป็นหลักฐาน</span>' +
        '<select class="rppf-select" name="rp_evidence_signer_prefix[]" style="max-width:80px; flex:0 0 80px;">' +
        '<option value="">--</option><option value="นาย">นาย</option><option value="นาง">นาง</option><option value="นางสาว">นางสาว</option><option value="อื่นๆ">อื่นๆ</option></select>' +
        '<input type="text" class="rppf-inp" name="rp_evidence_signer_name[]" placeholder="ชื่อ-นามสกุล">' +
        '<span class="fl">สถานะ</span>' +
        '<select class="rppf-select" name="rp_evidence_signer_role[]" style="max-width:120px; flex:0 0 120px;">' +
        '<option value="">--</option><option value="เจ้าของบ้าน">เจ้าของบ้าน</option><option value="ผู้เสียหาย">ผู้เสียหาย</option><option value="อื่นๆ">อื่นๆ</option></select>' +
        '</div>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
}

function rppdfRenumberEvidence() {
    var blocks = document.querySelectorAll('#rppdf_evidence_container .rppdf-evidence-block');
    blocks.forEach(function(block, idx) {
        block.querySelector('.rppdf-evidence-num').textContent = '7.5.' + (idx + 1);
    });
}

/* Init Select2 + populate options when PDF modal opens */
document.addEventListener('DOMContentLoaded', function() {
    $('#modalReportPropertyPdf').on('shown.bs.modal', function() {
        var $modal = $(this);
        if (typeof rpLoadUsers === 'function') {
            rpLoadUsers(function() {
                $modal.find('#rppdf_inspector_container .rp-inspector-select').each(function() {
                    var current = $(this).val();
                    $(this).html(rpBuildUserOptions(current));
                });
                $modal.find('#rppdf_inspector_container .rp-inspector-select').each(function() {
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
