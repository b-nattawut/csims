<?php
/**
 * Modal: ร่างรายงานคดีระเบิด ในอาคาร (ฟอร์มเสมือน PDF)
 * A4-paper style editable form — uses same rbi_ field names as modal_report_bomb_indoor.php
 * Modal ID: modalReportBombIndoorPdf   Form ID: formReportBombIndoorPdf
 */
?>

<style>
/* ========== Bomb Report Indoor PDF Form — scoped to #modalReportBombIndoorPdf ========== */
#modalReportBombIndoorPdf .rbipf-body { background: #bbb; }
#modalReportBombIndoorPdf .rbipf-page {
    width: 210mm; min-height: 297mm; margin: 16px auto; padding: 12mm 15mm 10mm 15mm;
    background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.25); position: relative;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; color: #000;
    display: flex; flex-direction: column; page-break-after: always;
}
#modalReportBombIndoorPdf .rbipf-page:last-child { page-break-after: auto; }

/* Row helpers */
#modalReportBombIndoorPdf .fr  { display: flex; flex-wrap: wrap; align-items: baseline; line-height: 1.9; width: 100%; }
#modalReportBombIndoorPdf .fl  { font-size: 14px; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportBombIndoorPdf .fl-b{ font-size: 14px; font-weight: 700; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportBombIndoorPdf .i1  { padding-left: 20px; }
#modalReportBombIndoorPdf .i2  { padding-left: 40px; }
#modalReportBombIndoorPdf .sec-heading  { font-weight: 700; font-size: 14px; margin-top: 2px; margin-bottom: 1px; text-decoration: underline; }
#modalReportBombIndoorPdf .sub-heading  { font-weight: 600; font-size: 14px; margin-left: 20px; }
#modalReportBombIndoorPdf .form-title   { text-decoration: underline; text-align: center; font-size: 15px; font-weight: 700; margin: 6px auto 4px auto; }
#modalReportBombIndoorPdf .page-header  { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2px; padding-bottom: 4px; }
#modalReportBombIndoorPdf .form-footer  { margin-top: auto; padding-top: 6px; font-size: 10px; color: #333; display: flex; justify-content: space-between; }
#modalReportBombIndoorPdf .form-footer-right { text-align: right; white-space: nowrap; line-height: 1.4; }
#modalReportBombIndoorPdf .form-footer-left  { flex: 1; }
#modalReportBombIndoorPdf .sub-heading-bottom { font-weight: 600; font-size: 11px; margin-left: 20px; }
#modalReportBombIndoorPdf .signature-block { margin-top: 30px; text-align: center; padding-left: 50%; line-height: 2; }
#modalReportBombIndoorPdf .blank-line { width: 100%; border-bottom: 1px dotted #888; height: 22px; margin-bottom: 0; }

/* Editable input styles */
#modalReportBombIndoorPdf .rbipf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; min-width: 40px; flex: 1; margin: 0 4px; color: #000;
}
#modalReportBombIndoorPdf .rbipf-inp:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }
#modalReportBombIndoorPdf .rbipf-inp-s { width: 60px; max-width: 80px; text-align: center; flex: 0 0 auto; }
#modalReportBombIndoorPdf .rbipf-inp-m { flex: 0 1 140px; min-width: 80px; text-align: center; }
#modalReportBombIndoorPdf .rbipf-inp-l { flex: 1; min-width: 160px; }
#modalReportBombIndoorPdf .rbipf-inp-s,
#modalReportBombIndoorPdf .rbipf-inp-m,
#modalReportBombIndoorPdf .rbipf-inp-l {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; margin: 0 4px; color: #000;
}
#modalReportBombIndoorPdf .rbipf-inp-s:focus,
#modalReportBombIndoorPdf .rbipf-inp-m:focus,
#modalReportBombIndoorPdf .rbipf-inp-l:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Textarea */
#modalReportBombIndoorPdf .rbipf-ta {
    width: 100%; border: none; border-bottom: 1px dotted #888;
    background: transparent; outline: none; resize: vertical;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.8;
    padding: 0 4px; min-height: 24px; margin: 0 4px 2px 20px; color: #000;
    background-image: repeating-linear-gradient(transparent, transparent 27px, #ddd 27px, #ddd 28px);
    background-position: 0 0;
}
#modalReportBombIndoorPdf .rbipf-ta:focus { border-bottom-color: #0d6efd; }

/* Checkbox — square style */
#modalReportBombIndoorPdf .ck { display: inline-flex; align-items: baseline; margin-right: 8px; font-size: 14px; white-space: nowrap; }
#modalReportBombIndoorPdf .rbipf-cb {
    -webkit-appearance: none; appearance: none;
    width: 14px; height: 14px; margin-right: 3px; cursor: pointer; position: relative; top: 2px;
    border: 1.5px solid #000; border-radius: 0; background: #fff;
}
#modalReportBombIndoorPdf .rbipf-cb:checked { background: #fff; }
#modalReportBombIndoorPdf .rbipf-cb:checked::after {
    content: '✓'; position: absolute; top: -3px; left: 1px;
    font-size: 13px; font-weight: bold; color: #000; line-height: 1;
}

/* Add/Remove inspector buttons */
#modalReportBombIndoorPdf .rbipf-add-btn {
    font-size: 12px; padding: 1px 8px; border: 1px dashed #0d6efd; color: #0d6efd;
    background: transparent; border-radius: 4px; cursor: pointer; margin-left: 20px; margin-top: 2px;
}
#modalReportBombIndoorPdf .rbipf-add-btn:hover { background: rgba(13,110,253,.08); }
#modalReportBombIndoorPdf .rbipf-del-btn {
    font-size: 11px; padding: 0 5px; border: none; color: #dc3545; background: transparent; cursor: pointer; margin-left: 4px;
}

/* Select dropdown for inspector */
#modalReportBombIndoorPdf .rbipf-select {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 26px; flex: 1; margin: 0 4px; color: #000;
    cursor: pointer; min-width: 120px;
}
#modalReportBombIndoorPdf .rbipf-select:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Select2 inside PDF form — compact */
#modalReportBombIndoorPdf .rbipdf-inspector-row .select2-container { flex: 1; min-width: 120px; margin: 0 4px; }
#modalReportBombIndoorPdf .rbipdf-inspector-row .select2-container--bootstrap-5 .select2-selection {
    min-height: 26px !important; height: 26px !important; padding: 0 4px !important;
    border: none !important; border-bottom: 1px dotted #888 !important; border-radius: 0 !important;
    background: transparent !important; font-size: 14px !important;
}
#modalReportBombIndoorPdf .rbipdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    padding: 0 !important; line-height: 26px !important; font-size: 14px !important; color: #000 !important;
}
#modalReportBombIndoorPdf .rbipdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
    height: 26px !important;
}
</style>

<div class="modal fade" id="modalReportBombIndoorPdf" aria-labelledby="modalReportBombIndoorPdfLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 240mm;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportBombIndoorPdfLabel">
                    รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด (ในอาคาร)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body rbipf-body p-0" style="max-height: 80vh; overflow-y: auto;">
                <form id="formReportBombIndoorPdf" novalidate>
                    <input type="hidden" id="rbipdf_incident_id" name="incident_id">

                    <!-- Switch back to standard form -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="rbi_editInfoPdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="rbi_editCountPdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchBombIndoorReportToStd" checked
                                   style="width: 3rem; height: 1.5rem; cursor: pointer;"
                                   onchange="if(!this.checked){ this.checked=true; switchBombIndoorReportToStandard(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchBombIndoorReportToStd" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ==================== PAGE 1 ==================== -->
<div class="rbipf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <input type="text" class="rbipf-inp rbipf-inp-s" name="rbi_report_no_display_pdf" id="rbipdf_report_no_display" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="rbipf-inp rbipf-inp-s" id="rbipdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">1/3</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="rbipf-inp" name="rbi_agency_name" id="rbipdf_agency_name">
    </div>

    <div class="form-title">รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด (ในอาคาร)</div>

    <!-- 1. การรับแจ้งเหตุ -->
    <div class="sec-heading">1. การรับแจ้งเหตุ</div>
    <div class="fr i1" style="flex-wrap:nowrap;">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="rbipf-inp rbipf-inp-m" name="rbi_receive_date" id="rbipdf_receive_date">
        <span class="fl">เวลา</span>
        <input type="time" class="rbipf-inp rbipf-inp-m" name="rbi_receive_time" id="rbipdf_receive_time">
        <span class="fl">น. ตามประจำวันข้อที่</span>
        <input type="text" class="rbipf-inp" name="rbi_daily_ref" id="rbipdf_daily_ref" style="min-width:60px;">
    </div>
    <div class="fr i1">
        <span class="fl">กสก.พฐก./กลก.ศพฐ./พฐ.จว.</span>
        <input type="text" class="rbipf-inp rbipf-inp-m" name="rbi_agency_name" id="rbipdf_agency_name_short" style="flex:0 1 160px;">
    </div>
    <div class="fr i1">
        <span class="fl">ได้รับแจ้งตาม</span>
        <label class="ck"><input type="checkbox" class="rbipf-cb" name="rbi_notify_method[]" value="หนังสือ" id="rbipdf_notify_letter">&nbsp;หนังสือ</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="rbipf-cb" name="rbi_notify_method[]" value="โทรศัพท์" id="rbipdf_notify_phone">&nbsp;ทางโทรศัพท์</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="rbipf-cb" name="rbi_notify_method[]" value="วิทยุสื่อสาร" id="rbipdf_notify_radio">&nbsp;วิทยุสื่อสาร</label>
    </div>
    <div class="fr i1">
        <span class="fl">จาก สน./สภ.</span>
        <input type="text" class="rbipf-inp" name="rbi_from_station" id="rbipdf_from_station">
        <span class="fl">ขอเจ้าหน้าที่ ร่วมตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด</span>
    </div>
    <div class="fr i1">
        <span class="fl">โดยมี</span>
        <input type="text" class="rbipf-inp" name="rbi_investigator" id="rbipdf_investigator">
        <span class="fl">เป็นพนักงานสอบสวนเจ้าของคดี</span>
    </div>

    <!-- หน่วยงาน radio (hidden for data sync) -->
    <input type="hidden" name="rbi_agency_type" id="rbipdf_agency_type">

    <!-- 2. สถานที่เกิดเหตุ -->
    <div class="sec-heading">2. สถานที่เกิดเหตุ</div>
    <div class="fr i1">
        <input type="text" class="rbipf-inp" name="rbi_crime_location" id="rbipdf_crime_location">
    </div>
    <div class="fr i1">
        <span class="fl">ผู้เสียชีวิต/ผู้บาดเจ็บ/ผู้เสียหาย</span>
        <input type="text" class="rbipf-inp" name="rbi_victim_name" id="rbipdf_victim_name">
        <span class="fl" style="margin-left:20px;">อายุประมาณ</span>
        <input type="text" class="rbipf-inp rbipf-inp-s" name="rbi_victim_age" id="rbipdf_victim_age" style="text-align:center;">
        <span class="fl">ปี</span>
    </div>

    <!-- 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ -->
    <div class="sec-heading">3. วันเวลาที่ทราบเหตุ/เกิดเหตุ</div>
    <div class="fr i1">
        <span class="fl">ผู้เสียหายทราบเหตุ/เกิดเหตุ เมื่อวันที่</span>
        <input type="date" class="rbipf-inp rbipf-inp-m" name="rbi_victim_know_date" id="rbipdf_victim_know_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rbipf-inp rbipf-inp-m" name="rbi_victim_know_time" id="rbipdf_victim_know_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">พนักงานสอบสวนทราบเหตุ เมื่อวันที่</span>
        <input type="date" class="rbipf-inp rbipf-inp-m" name="rbi_officer_know_date" id="rbipdf_officer_know_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rbipf-inp rbipf-inp-m" name="rbi_officer_know_time" id="rbipdf_officer_know_time">
        <span class="fl">น.</span>
    </div>

    <!-- 4. วันเวลาตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading">4. วันเวลาตรวจสถานที่เกิดเหตุ</div>
    <div class="fr i1">
        <span class="fl">ตรวจสถานที่เกิดเหตุ เมื่อวันที่</span>
        <input type="date" class="rbipf-inp rbipf-inp-m" name="rbi_inspect_date" id="rbipdf_inspect_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rbipf-inp rbipf-inp-m" name="rbi_inspect_time" id="rbipdf_inspect_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">ตรวจสถานที่เกิดเหตุเพิ่มเติม เมื่อวันที่</span>
        <input type="date" class="rbipf-inp rbipf-inp-m" name="rbi_inspect_add_date" id="rbipdf_inspect_add_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rbipf-inp rbipf-inp-m" name="rbi_inspect_add_time" id="rbipdf_inspect_add_time">
        <span class="fl">น.</span>
    </div>

    <!-- 5. ผู้ตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading">5. ผู้ตรวจสถานที่เกิดเหตุ</div>
    <div id="rbipdf_inspector_container">
        <div class="fr i1 rbipdf-inspector-row">
            <span class="fl rbipdf-inspector-num">5.1.</span>
            <select class="rbipf-select rp-inspector-select" name="rbi_inspector_name[]">
                <option value="">-- เลือกผู้ตรวจ --</option>
            </select>
            <span class="fl" style="margin-left:6px;">ตำแหน่ง</span>
            <input type="text" class="rbipf-inp rp-inspector-position" name="rbi_inspector_position[]" placeholder="ตำแหน่ง" readonly>
            <button type="button" class="rbipf-del-btn" title="ลบ" onclick="this.closest('.rbipdf-inspector-row').remove(); rbipdfRenumberInspectors();">✕</button>
        </div>
    </div>
    <button type="button" class="rbipf-add-btn" onclick="rbipdfAddInspectorRow();">+ เพิ่มผู้ตรวจ</button>

    <!-- 6. ลักษณะของสถานที่เกิดเหตุ -->
    <div class="sec-heading">6. ลักษณะของสถานที่เกิดเหตุ</div>
    <div class="sub-heading">6.1 ลักษณะภายนอก</div>
    <div class="fr i2">
        <span class="fl">เป็นบ้าน/ตึกแถว/อาคาร/อื่น ๆ</span>
        <input type="text" class="rbipf-inp" name="rbi_building_type_text" id="rbipdf_building_type_text">
    </div>
    <div class="fr i2">
        <span class="fl">ชั้น จำนวน</span>
        <input type="text" class="rbipf-inp rbipf-inp-s" name="rbi_floor_count" id="rbipdf_floor_count">
        <span class="fl">หลัง/คูหา</span>
        <input type="text" class="rbipf-inp rbipf-inp-s" name="rbi_unit_count" id="rbipdf_unit_count">
        <label class="ck"><input type="checkbox" class="rbipf-cb" name="rbi_mezzanine_pdf" value="มี" id="rbipdf_mezzanine_yes">&nbsp;มี</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="rbipf-cb" name="rbi_no_mezzanine_pdf" value="ไม่มี" id="rbipdf_mezzanine_no">&nbsp;ไม่มีชั้นลอย</label>
    </div>
    <div class="fr i2">
        <label class="ck"><input type="checkbox" class="rbipf-cb" name="rbi_rooftop_pdf_yes" value="มี" id="rbipdf_rooftop_yes">&nbsp;มี</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="rbipf-cb" name="rbi_rooftop_pdf_no" value="ไม่มี" id="rbipdf_rooftop_no">&nbsp;ไม่มีดาดฟ้า</label>
        <span class="fl" style="margin-left:10px;">ปลูกอยู่ภายในบริเวณ&nbsp;</span>
        <label class="ck"><input type="checkbox" class="rbipf-cb" name="rbi_fence_pdf_yes" value="มี" id="rbipdf_fence_yes">&nbsp;มี</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="rbipf-cb" name="rbi_fence_pdf_no" value="ไม่มี" id="rbipdf_fence_no">&nbsp;ไม่มี</label>
        <span class="fl">รั้วล้อมรอบ เมื่อหันหน้าเข้า</span>
    </div>
    <div class="fr i2">
        <span class="fl">ด้านหน้าติด</span>
        <input type="text" class="rbipf-inp" name="rbi_ext_front" id="rbipdf_ext_front">
    </div>
    <div class="fr i2">
        <span class="fl">ด้านซ้ายติด</span>
        <input type="text" class="rbipf-inp" name="rbi_ext_left" id="rbipdf_ext_left">
    </div>
    <div class="fr i2">
        <span class="fl">ด้านขวาติด</span>
        <input type="text" class="rbipf-inp" name="rbi_ext_right" id="rbipdf_ext_right">
    </div>
    <div class="fr i2">
        <span class="fl">ด้านหลังติด</span>
        <input type="text" class="rbipf-inp" name="rbi_ext_back" id="rbipdf_ext_back">
    </div>

    <!-- 6.2 ลักษณะภายใน -->
    <div class="sub-heading">6.2 ลักษณะภายใน</div>
    <textarea class="rbipf-ta" name="rbi_interior_detail" id="rbipdf_interior_detail" rows="2" style="margin-left:20px;"></textarea>
    <div class="blank-line" style="margin-left:20px;"></div>

    <!-- Footer Page 1 -->
    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom">
            <div>ปฏิบัติงานตาม</div>
            <div>OPFS – CS – SP – 03</div>
        </div>
        <div class="form-footer-right sub-heading-bottom">
            F-CS-15 แก้ไขครั้งที่ 2<br>
            แก้ไขวันที่ 2 ก.ย. 63<br>
            เริ่มใช้ 1 ต.ค. 63
        </div>
    </div>
</div>

<!-- ==================== PAGE 2 ==================== -->
<div class="rbipf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
             <input type="text" class="rbipf-inp rbipf-inp-s" name="rbi_report_no_display_pdf_2" id="rbipdf_report_no_display_2" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="rbipf-inp rbipf-inp-s" id="rbipdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">2/3</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="rbipf-inp" name="rbi_agency_name" id="rbipdf_agency_name_p2" readonly tabindex="-1">
    </div>

    <!-- 6.3 บริเวณที่เกิดเหตุ -->
    <div class="sub-heading" style="margin-top:4px;">6.3 บริเวณที่เกิดเหตุ</div>
    <div class="fr i2">
        <span class="fl">เกิดเหตุที่</span>
        <input type="text" class="rbipf-inp" name="rbi_incident_area" id="rbipdf_incident_area">
    </div>
    <div class="fr i2">
        <span class="fl">ซึ่งมีขนาด กว้าง x ยาว ประมาณ</span>
        <input type="text" class="rbipf-inp" name="rbi_area_size" id="rbipdf_area_size">
    </div>

    <!-- ลักษณะโครงสร้าง -->
    <div class="sec-heading" style="margin-top:4px;">ลักษณะโครงสร้าง</div>
    <div class="fr i2" style="flex-wrap:nowrap;">
        <span class="fl">ฝาผนังด้านหน้า</span>
        <input type="text" class="rbipf-inp" name="rbi_wall_front" id="rbipdf_wall_front" style="flex:1; min-width:60px;">
        <span class="fl">หน้าต่าง</span>
        <input type="text" class="rbipf-inp rbipf-inp-s" name="rbi_wall_front_window" id="rbipdf_wall_front_window" style="text-align:center;">
        <span class="fl">บาน</span>
        <span class="fl" style="margin-left:4px;">ประตู</span>
        <input type="text" class="rbipf-inp rbipf-inp-s" name="rbi_wall_front_door" id="rbipdf_wall_front_door" style="text-align:center;">
        <span class="fl">บาน</span>
    </div>
    <div class="fr i2" style="flex-wrap:nowrap;">
        <span class="fl">ฝาผนังด้านซ้าย</span>
        <input type="text" class="rbipf-inp" name="rbi_wall_left" id="rbipdf_wall_left" style="flex:1; min-width:60px;">
        <span class="fl">หน้าต่าง</span>
        <input type="text" class="rbipf-inp rbipf-inp-s" name="rbi_wall_left_window" id="rbipdf_wall_left_window" style="text-align:center;">
        <span class="fl">บาน</span>
        <span class="fl" style="margin-left:4px;">ประตู</span>
        <input type="text" class="rbipf-inp rbipf-inp-s" name="rbi_wall_left_door" id="rbipdf_wall_left_door" style="text-align:center;">
        <span class="fl">บาน</span>
    </div>
    <div class="fr i2" style="flex-wrap:nowrap;">
        <span class="fl">ฝาผนังด้านขวา</span>
        <input type="text" class="rbipf-inp" name="rbi_wall_right" id="rbipdf_wall_right" style="flex:1; min-width:60px;">
        <span class="fl">หน้าต่าง</span>
        <input type="text" class="rbipf-inp rbipf-inp-s" name="rbi_wall_right_window" id="rbipdf_wall_right_window" style="text-align:center;">
        <span class="fl">บาน</span>
        <span class="fl" style="margin-left:4px;">ประตู</span>
        <input type="text" class="rbipf-inp rbipf-inp-s" name="rbi_wall_right_door" id="rbipdf_wall_right_door" style="text-align:center;">
        <span class="fl">บาน</span>
    </div>
    <div class="fr i2" style="flex-wrap:nowrap;">
        <span class="fl">ฝาผนังด้านหลัง</span>
        <input type="text" class="rbipf-inp" name="rbi_wall_back" id="rbipdf_wall_back" style="flex:1; min-width:60px;">
        <span class="fl">หน้าต่าง</span>
        <input type="text" class="rbipf-inp rbipf-inp-s" name="rbi_wall_back_window" id="rbipdf_wall_back_window" style="text-align:center;">
        <span class="fl">บาน</span>
        <span class="fl" style="margin-left:4px;">ประตู</span>
        <input type="text" class="rbipf-inp rbipf-inp-s" name="rbi_wall_back_door" id="rbipdf_wall_back_door" style="text-align:center;">
        <span class="fl">บาน</span>
    </div>
    <div class="fr i2" style="flex-wrap:nowrap;">
        <span class="fl">พื้นห้อง</span>
        <input type="text" class="rbipf-inp" name="rbi_floor_material" id="rbipdf_floor_material" style="flex:1; min-width:50px;">
        <span class="fl" style="margin-left:4px;">เพดานห้อง</span>
        <input type="text" class="rbipf-inp" name="rbi_ceiling" id="rbipdf_ceiling" style="flex:1; min-width:50px;">
        <span class="fl" style="margin-left:4px;">หลังคา</span>
        <input type="text" class="rbipf-inp" name="rbi_roof" id="rbipdf_roof" style="flex:1; min-width:50px;">
    </div>

    <!-- ลักษณะการจัดวางสิ่งของ -->
    <div class="sec-heading" style="margin-top:4px;">ลักษณะการจัดวางสิ่งของ</div>
    <div class="fr i2">
        <span class="fl">ชิดฝาผนังด้านหน้าเรียงจากซ้ายไปขวา</span>
        <input type="text" class="rbipf-inp" name="rbi_arrange_front" id="rbipdf_arrange_front">
    </div>
    <div class="fr i2">
        <span class="fl">ชิดผนังด้านซ้ายเรียงจากหน้าไปหลัง</span>
        <input type="text" class="rbipf-inp" name="rbi_arrange_left" id="rbipdf_arrange_left">
    </div>
    <div class="fr i2">
        <span class="fl">ชิดผนังด้านขวาเรียงจากหน้าไปหลัง</span>
        <input type="text" class="rbipf-inp" name="rbi_arrange_right" id="rbipdf_arrange_right">
    </div>
    <div class="fr i2">
        <span class="fl">ชิดผนังด้านหลังเรียงจากซ้ายไปขวา</span>
        <input type="text" class="rbipf-inp" name="rbi_arrange_back" id="rbipdf_arrange_back">
    </div>

    <!-- รายละเอียดอื่นๆ -->
    <div class="fr i1" style="margin-top:4px;">
        <span class="fl">รายละเอียดอื่นๆ</span>
    </div>
    <textarea class="rbipf-ta" name="rbi_arrange_other" id="rbipdf_arrange_other" rows="1" style="margin-left:20px;"></textarea>
    <div class="blank-line" style="margin-left:20px;"></div>

    <!-- 7. ผลการตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading" style="margin-top:6px;">7. ผลการตรวจสถานที่เกิดเหตุ</div>
    <div class="fr i1">
        <span class="fl">พฤติการณ์ของคดีจากการสอบถามข้อมูลในเบื้องต้นจาก พงส. ได้ความว่า</span>
    </div>
    <textarea class="rbipf-ta" name="rbi_case_behavior" id="rbipdf_case_behavior" rows="2"></textarea>
    <div class="blank-line" style="margin-left:20px;"></div>

    <div class="fr i1" style="margin-top:4px;">
        <span class="fl">จากการตรวจสถานที่เกิดเหตุ</span>
    </div>
    <div class="sub-heading">7.1 สภาพของสถานที่เกิดเหตุเมื่อไปถึง</div>
    <textarea class="rbipf-ta" name="rbi_scene_condition" id="rbipdf_scene_condition" rows="2" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- 7.2 ลักษณะสภาพศพ -->
    <div class="sub-heading" style="margin-top:4px;">7.2 ลักษณะสภาพศพ</div>
    <div class="fr i2">
        <span class="fl">7.2.1 พบศพ/ไม่พบศพ</span>
        <input type="text" class="rbipf-inp" name="rbi_body_found" id="rbipdf_body_found">
    </div>
    <div class="fr i2">
        <span class="fl">7.2.2 ตำแหน่งที่พบศพ</span>
        <input type="text" class="rbipf-inp" name="rbi_body_position" id="rbipdf_body_position">
    </div>
    <div class="fr i2">
        <span class="fl">7.2.3 สภาพศพ</span>
        <input type="text" class="rbipf-inp" name="rbi_body_condition" id="rbipdf_body_condition">
    </div>
    <div class="fr i2">
        <span class="fl">7.2.4 สภาพเครื่องแต่งกายและทรัพย์สิน</span>
        <input type="text" class="rbipf-inp" name="rbi_body_clothing" id="rbipdf_body_clothing">
    </div>
    <div class="fr i2">
        <span class="fl">7.2.5 รอยบาดแผลที่ศพ</span>
        <input type="text" class="rbipf-inp" name="rbi_body_wounds" id="rbipdf_body_wounds">
    </div>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- Footer Page 2 -->
    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom">
            <div>ปฏิบัติงานตาม</div>
            <div>OPFS – CS – SP – 03</div>
        </div>
        <div class="form-footer-right sub-heading-bottom">
            F-CS-15 แก้ไขครั้งที่ 2<br>
            แก้ไขวันที่ 2 ก.ย. 63<br>
            เริ่มใช้ 1 ต.ค. 63
        </div>
    </div>
</div>

<!-- ==================== PAGE 3 ==================== -->
<div class="rbipf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
             <input type="text" class="rbipf-inp rbipf-inp-s" name="rbi_report_no_display_pdf_3" id="rbipdf_report_no_display_3" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="rbipf-inp rbipf-inp-s" id="rbipdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">3/3</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="rbipf-inp" name="rbi_agency_name" id="rbipdf_agency_name_p3" readonly tabindex="-1">
    </div>

    <!-- 7.3 สภาพความเสียหาย -->
    <div class="sub-heading" style="margin-top:6px;">7.3 สภาพความเสียหาย</div>
    <textarea class="rbipf-ta" name="rbi_damage_detail" id="rbipdf_damage_detail" rows="2" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- 7.4 ร่องรอยและวัตถุพยานที่ตรวจพบ -->
    <div class="sub-heading" style="margin-top:6px;">7.4 ร่องรอยและวัตถุพยานที่ตรวจพบในสถานที่เกิดเหตุ</div>
    <textarea class="rbipf-ta" name="rbi_evidence_found" id="rbipdf_evidence_found" rows="2" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- 7.5 วัตถุพยานที่ตรวจเก็บ -->
    <div class="sub-heading" style="margin-top:6px;">7.5 วัตถุพยานที่ตรวจเก็บในสถานที่เกิดเหตุ</div>
    <textarea class="rbipf-ta" name="rbi_evidence_collected" id="rbipdf_evidence_collected" rows="2" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- 7.6 การดำเนินการเกี่ยวกับวัตถุพยาน -->
    <div class="sub-heading" style="margin-top:6px;">7.6 การดำเนินการเกี่ยวกับวัตถุพยาน</div>
    <textarea class="rbipf-ta" name="rbi_evidence_action" id="rbipdf_evidence_action" rows="2" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- 7.7 การส่งมอบสถานที่เกิดเหตุ -->
    <div class="sub-heading" style="margin-top:6px;">7.7 เจ้าหน้าที่ กสก.พฐก. / กลก.ศพฐ. / พฐ.จว.
        <input type="text" class="rbipf-inp" name="rbi_handover_officer" id="rbipdf_handover_officer" style="display:inline-block; width:200px;">
        <span>ตรวจสถานที่เกิดเหตุเสร็จสิ้น</span>
    </div>
    <div class="fr i2" style="margin-top:2px;">
        <span class="fl">พร้อมทั้งส่งมอบสถานที่เกิดเหตุคืนให้กับ</span>
        <input type="text" class="rbipf-inp" name="rbi_handover_to" id="rbipdf_handover_to">
    </div>
    <div class="fr i2">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="rbipf-inp rbipf-inp-m" name="rbi_handover_date" id="rbipdf_handover_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rbipf-inp rbipf-inp-m" name="rbi_handover_time" id="rbipdf_handover_time">
        <span class="fl">น.</span>
    </div>

    <!-- ลงชื่อ -->
    <div class="signature-block">
        <div>ลงชื่อ ........................................ ผู้รายงาน</div>
        <div>(<input type="text" class="rbipf-inp" name="rbi_signer_name" id="rbipdf_signer_name" style="width:200px; text-align:center; display:inline-block;">)</div>
        <div>ตำแหน่ง <input type="text" class="rbipf-inp" name="rbi_signer_position" id="rbipdf_signer_position" style="width:200px; text-align:center; display:inline-block;"></div>
        <div>วันที่ <input type="date" class="rbipf-inp" name="rbi_sign_date" id="rbipdf_sign_date" style="width:180px; text-align:center; display:inline-block;"></div>
    </div>

    <!-- Footer Page 3 -->
    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom">
            <div>ปฏิบัติงานตาม</div>
            <div>OPFS – CS – SP – 03</div>
        </div>
        <div class="form-footer-right sub-heading-bottom">
            F-CS-15 แก้ไขครั้งที่ 2<br>
            แก้ไขวันที่ 2 ก.ย. 63<br>
            เริ่มใช้ 1 ต.ค. 63
        </div>
    </div>
</div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_report_bomb_indoor_pdf">
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
/* ========== Inspector Row Management for Bomb Indoor PDF ========== */
function rbipdfAddInspectorRow() {
    var container = document.getElementById('rbipdf_inspector_container');
    var count = container.querySelectorAll('.rbipdf-inspector-row').length + 1;
    var opts = '<option value="">-- เลือกผู้ตรวจ --</option>';
    if (typeof rpUsersList !== 'undefined' && rpUsersList.length > 0) {
        rpUsersList.forEach(function(u) {
            opts += '<option value="' + u.fullname + '">' + u.fullname + '</option>';
        });
    }
    var html = '<div class="fr i1 rbipdf-inspector-row">' +
        '<span class="fl rbipdf-inspector-num">5.' + count + '.</span>' +
        '<select class="rbipf-select rp-inspector-select" name="rbi_inspector_name[]">' + opts + '</select>' +
        '<span class="fl" style="margin-left:6px;">ตำแหน่ง</span>' +
        '<input type="text" class="rbipf-inp rp-inspector-position" name="rbi_inspector_position[]" placeholder="ตำแหน่ง" readonly>' +
        '<button type="button" class="rbipf-del-btn" title="ลบ" onclick="this.closest(\'.rbipdf-inspector-row\').remove(); rbipdfRenumberInspectors();">✕</button>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
    var $modal = $('#modalReportBombIndoorPdf');
    var $newSelect = $(container).find('.rbipdf-inspector-row:last .rp-inspector-select');
    $newSelect.select2({
        theme: 'bootstrap-5', width: '100%', placeholder: '-- เลือกผู้ตรวจ --',
        allowClear: true, dropdownParent: $modal
    });
}

function rbipdfRenumberInspectors() {
    var rows = document.querySelectorAll('#rbipdf_inspector_container .rbipdf-inspector-num');
    rows.forEach(function(el, idx) { el.textContent = '5.' + (idx + 1) + '.'; });
}

/* Sync agency name from page 1 to pages 2-3 */
document.addEventListener('DOMContentLoaded', function() {
    $('#rbipdf_agency_name').on('input change', function() {
        var v = $(this).val();
        $('#rbipdf_agency_name_p2').val(v);
        $('#rbipdf_agency_name_p3').val(v);
    });

    $('#modalReportBombIndoorPdf').on('shown.bs.modal', function() {
        var v = $('#rbipdf_agency_name').val();
        if (v) { $('#rbipdf_agency_name_p2').val(v); $('#rbipdf_agency_name_p3').val(v); }
        var $modal = $(this);
        if (typeof rpLoadUsers === 'function') {
            rpLoadUsers(function() {
                $modal.find('#rbipdf_inspector_container .rp-inspector-select').each(function() {
                    var current = $(this).val();
                    $(this).html(rpBuildUserOptions(current));
                });
                $modal.find('#rbipdf_inspector_container .rp-inspector-select').each(function() {
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
