<?php
/**
 * Modal: ร่างรายงานคดีระเบิด นอกอาคาร (ฟอร์มเสมือน PDF)
 * A4-paper style editable form — uses same rbo_ field names as modal_report_bomb_outdoor.php
 * Modal ID: modalReportBombOutdoorPdf   Form ID: formReportBombOutdoorPdf
 */
?>

<style>
/* ========== Bomb Report Outdoor PDF Form — scoped to #modalReportBombOutdoorPdf ========== */
#modalReportBombOutdoorPdf .rbopf-body { background: #bbb; }
#modalReportBombOutdoorPdf .rbopf-page {
    width: 210mm; min-height: 297mm; margin: 16px auto; padding: 12mm 15mm 10mm 15mm;
    background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.25); position: relative;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; color: #000;
    display: flex; flex-direction: column; page-break-after: always;
}
#modalReportBombOutdoorPdf .rbopf-page:last-child { page-break-after: auto; }

#modalReportBombOutdoorPdf .fr  { display: flex; flex-wrap: wrap; align-items: baseline; line-height: 1.9; width: 100%; }
#modalReportBombOutdoorPdf .fl  { font-size: 14px; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportBombOutdoorPdf .fl-b{ font-size: 14px; font-weight: 700; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportBombOutdoorPdf .i1  { padding-left: 20px; }
#modalReportBombOutdoorPdf .i2  { padding-left: 40px; }
#modalReportBombOutdoorPdf .sec-heading  { font-weight: 700; font-size: 14px; margin-top: 2px; margin-bottom: 1px; text-decoration: underline; }
#modalReportBombOutdoorPdf .sub-heading  { font-weight: 600; font-size: 14px; margin-left: 20px; }
#modalReportBombOutdoorPdf .form-title   { text-decoration: underline; text-align: center; font-size: 15px; font-weight: 700; margin: 6px auto 4px auto; }
#modalReportBombOutdoorPdf .page-header  { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2px; padding-bottom: 4px; }
#modalReportBombOutdoorPdf .form-footer  { margin-top: auto; padding-top: 6px; font-size: 10px; color: #333; display: flex; justify-content: space-between; }
#modalReportBombOutdoorPdf .form-footer-right { text-align: right; white-space: nowrap; line-height: 1.4; }
#modalReportBombOutdoorPdf .form-footer-left  { flex: 1; }
#modalReportBombOutdoorPdf .sub-heading-bottom { font-weight: 600; font-size: 11px; margin-left: 20px; }
#modalReportBombOutdoorPdf .signature-block { margin-top: 30px; text-align: center; padding-left: 50%; line-height: 2; }
#modalReportBombOutdoorPdf .blank-line { width: 100%; border-bottom: 1px dotted #888; height: 22px; margin-bottom: 0; }

/* Editable input styles */
#modalReportBombOutdoorPdf .rbopf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; min-width: 40px; flex: 1; margin: 0 4px; color: #000;
}
#modalReportBombOutdoorPdf .rbopf-inp:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }
#modalReportBombOutdoorPdf .rbopf-inp-s { width: 60px; max-width: 80px; text-align: center; flex: 0 0 auto; }
#modalReportBombOutdoorPdf .rbopf-inp-m { flex: 0 1 140px; min-width: 80px; text-align: center; }
#modalReportBombOutdoorPdf .rbopf-inp-l { flex: 1; min-width: 160px; }
#modalReportBombOutdoorPdf .rbopf-inp-s,
#modalReportBombOutdoorPdf .rbopf-inp-m,
#modalReportBombOutdoorPdf .rbopf-inp-l {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; margin: 0 4px; color: #000;
}
#modalReportBombOutdoorPdf .rbopf-inp-s:focus,
#modalReportBombOutdoorPdf .rbopf-inp-m:focus,
#modalReportBombOutdoorPdf .rbopf-inp-l:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Textarea */
#modalReportBombOutdoorPdf .rbopf-ta {
    width: 100%; border: none; border-bottom: 1px dotted #888;
    background: transparent; outline: none; resize: vertical;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.8;
    padding: 0 4px; min-height: 24px; margin: 0 4px 2px 20px; color: #000;
    background-image: repeating-linear-gradient(transparent, transparent 27px, #ddd 27px, #ddd 28px);
    background-position: 0 0;
}
#modalReportBombOutdoorPdf .rbopf-ta:focus { border-bottom-color: #0d6efd; }

/* Checkbox — square style */
#modalReportBombOutdoorPdf .ck { display: inline-flex; align-items: baseline; margin-right: 8px; font-size: 14px; white-space: nowrap; }
#modalReportBombOutdoorPdf .rbopf-cb {
    -webkit-appearance: none; appearance: none;
    width: 14px; height: 14px; margin-right: 3px; cursor: pointer; position: relative; top: 2px;
    border: 1.5px solid #000; border-radius: 0; background: #fff;
}
#modalReportBombOutdoorPdf .rbopf-cb:checked { background: #fff; }
#modalReportBombOutdoorPdf .rbopf-cb:checked::after {
    content: '✓'; position: absolute; top: -3px; left: 1px;
    font-size: 13px; font-weight: bold; color: #000; line-height: 1;
}

/* Add/Remove inspector buttons */
#modalReportBombOutdoorPdf .rbopf-add-btn {
    font-size: 12px; padding: 1px 8px; border: 1px dashed #0d6efd; color: #0d6efd;
    background: transparent; border-radius: 4px; cursor: pointer; margin-left: 20px; margin-top: 2px;
}
#modalReportBombOutdoorPdf .rbopf-add-btn:hover { background: rgba(13,110,253,.08); }
#modalReportBombOutdoorPdf .rbopf-del-btn {
    font-size: 11px; padding: 0 5px; border: none; color: #dc3545; background: transparent; cursor: pointer; margin-left: 4px;
}

/* Select2 inside PDF form — compact */
#modalReportBombOutdoorPdf .rbopdf-inspector-row .select2-container { flex: 1; min-width: 120px; margin: 0 4px; }
#modalReportBombOutdoorPdf .rbopdf-inspector-row .select2-container--bootstrap-5 .select2-selection {
    min-height: 26px !important; height: 26px !important; padding: 0 4px !important;
    border: none !important; border-bottom: 1px dotted #888 !important; border-radius: 0 !important;
    background: transparent !important; font-size: 14px !important;
}
#modalReportBombOutdoorPdf .rbopdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    padding: 0 !important; line-height: 26px !important; font-size: 14px !important; color: #000 !important;
}
#modalReportBombOutdoorPdf .rbopdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
    height: 26px !important;
}

/* Select dropdown for inspector */
#modalReportBombOutdoorPdf .rbopf-select {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 26px; flex: 1; margin: 0 4px; color: #000;
    cursor: pointer; min-width: 120px;
}
#modalReportBombOutdoorPdf .rbopf-select:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }
</style>

<div class="modal fade" id="modalReportBombOutdoorPdf" aria-labelledby="modalReportBombOutdoorPdfLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 240mm;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportBombOutdoorPdfLabel">
                    รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด (นอกอาคาร)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body rbopf-body p-0" style="max-height: 80vh; overflow-y: auto;">
                <form id="formReportBombOutdoorPdf" novalidate>
                    <input type="hidden" id="rbopdf_incident_id" name="incident_id">

                    <!-- Switch back to standard form -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="rbo_editInfoPdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="rbo_editCountPdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchBombOutdoorReportToStd" checked
                                   style="width: 3rem; height: 1.5rem; cursor: pointer;"
                                   onchange="if(!this.checked){ this.checked=true; switchBombOutdoorReportToStandard(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchBombOutdoorReportToStd" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ==================== PAGE 1 ==================== -->
<div class="rbopf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <input type="text" class="rbopf-inp rbopf-inp-s" name="rbo_report_no_display_pdf" id="rbopdf_report_no_display" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="rbopf-inp rbopf-inp-s" id="rbopdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">1/3</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="rbopf-inp" name="rbo_agency_name" id="rbopdf_agency_name">
    </div>

    <div class="form-title">รายงานการตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด (นอกอาคาร)</div>

    <!-- 1. การรับแจ้งเหตุ -->
    <div class="sec-heading">1. การรับแจ้งเหตุ</div>
    <div class="fr i1" style="flex-wrap:nowrap;">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="rbopf-inp rbopf-inp-m" name="rbo_receive_date" id="rbopdf_receive_date">
        <span class="fl">เวลา</span>
        <input type="time" class="rbopf-inp rbopf-inp-m" name="rbo_receive_time" id="rbopdf_receive_time">
        <span class="fl">น. ตามประจำวันข้อที่</span>
        <input type="text" class="rbopf-inp" name="rbo_daily_ref" id="rbopdf_daily_ref" style="min-width:60px;">
    </div>
    <div class="fr i1">
        <span class="fl">กสก.พฐก./กลก.ศพฐ./พฐ.จว.</span>
        <input type="text" class="rbopf-inp rbopf-inp-m" name="rbo_agency_name" id="rbopdf_agency_name_short" style="flex:0 1 160px;">
    </div>
    <div class="fr i1">
        <span class="fl">ได้รับแจ้งตาม</span>
        <label class="ck"><input type="checkbox" class="rbopf-cb" name="rbo_notify_method[]" value="หนังสือ" id="rbopdf_notify_letter">&nbsp;หนังสือ</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="rbopf-cb" name="rbo_notify_method[]" value="โทรศัพท์" id="rbopdf_notify_phone">&nbsp;ทางโทรศัพท์</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="rbopf-cb" name="rbo_notify_method[]" value="วิทยุสื่อสาร" id="rbopdf_notify_radio">&nbsp;วิทยุสื่อสาร</label>
    </div>
    <div class="fr i1">
        <span class="fl">จาก สน./สภ.</span>
        <input type="text" class="rbopf-inp" name="rbo_from_station" id="rbopdf_from_station">
        <span class="fl">ขอเจ้าหน้าที่ ร่วมตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด</span>
    </div>
    <div class="fr i1">
        <span class="fl">โดยมี</span>
        <input type="text" class="rbopf-inp" name="rbo_investigator" id="rbopdf_investigator">
        <span class="fl">เป็นพนักงานสอบสวนเจ้าของคดี</span>
    </div>

    <!-- หน่วยงาน radio (hidden for data sync) -->
    <input type="hidden" name="rbo_agency_type" id="rbopdf_agency_type">

    <!-- 2. สถานที่เกิดเหตุ -->
    <div class="sec-heading">2. สถานที่เกิดเหตุ</div>
    <textarea class="rbopf-ta" name="rbo_crime_location" id="rbopdf_crime_location" rows="2" style="margin-left:20px;"></textarea>
    <div class="fr i1">
        <span class="fl">ผู้เสียชีวิต/ผู้บาดเจ็บ/ผู้เสียหาย</span>
        <input type="text" class="rbopf-inp" name="rbo_victim_name" id="rbopdf_victim_name">
        <span class="fl" style="margin-left:20px;">อายุประมาณ</span>
        <input type="text" class="rbopf-inp rbopf-inp-s" name="rbo_victim_age" id="rbopdf_victim_age" style="text-align:center;">
        <span class="fl">ปี</span>
    </div>

    <!-- 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ -->
    <div class="sec-heading">3. วันเวลาที่ทราบเหตุ/เกิดเหตุ</div>
    <div class="fr i1">
        <span class="fl">ผู้เสียหายทราบเหตุ/เกิดเหตุ เมื่อวันที่</span>
        <input type="date" class="rbopf-inp rbopf-inp-m" name="rbo_victim_know_date" id="rbopdf_victim_know_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rbopf-inp rbopf-inp-m" name="rbo_victim_know_time" id="rbopdf_victim_know_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">พนักงานสอบสวนทราบเหตุ เมื่อวันที่</span>
        <input type="date" class="rbopf-inp rbopf-inp-m" name="rbo_officer_know_date" id="rbopdf_officer_know_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rbopf-inp rbopf-inp-m" name="rbo_officer_know_time" id="rbopdf_officer_know_time">
        <span class="fl">น.</span>
    </div>

    <!-- 4. วันเวลาตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading">4. วันเวลาตรวจสถานที่เกิดเหตุ</div>
    <div class="fr i1">
        <span class="fl">ตรวจสถานที่เกิดเหตุ เมื่อวันที่</span>
        <input type="date" class="rbopf-inp rbopf-inp-m" name="rbo_inspect_date" id="rbopdf_inspect_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rbopf-inp rbopf-inp-m" name="rbo_inspect_time" id="rbopdf_inspect_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">ตรวจสถานที่เกิดเหตุเพิ่มเติม เมื่อวันที่</span>
        <input type="date" class="rbopf-inp rbopf-inp-m" name="rbo_inspect_add_date" id="rbopdf_inspect_add_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rbopf-inp rbopf-inp-m" name="rbo_inspect_add_time" id="rbopdf_inspect_add_time">
        <span class="fl">น.</span>
    </div>

    <!-- 5. ผู้ตรวจสถานที่เกิดเหตุ -->
    <div class="sec-heading">5. ผู้ตรวจสถานที่เกิดเหตุ</div>
    <div id="rbopdf_inspector_container">
        <div class="fr i1 rbopdf-inspector-row">
            <span class="fl rbopdf-inspector-num">5.1.</span>
            <select class="rbopf-select rp-inspector-select" name="rbo_inspector_name[]">
                <option value="">-- เลือกผู้ตรวจ --</option>
            </select>
            <span class="fl" style="margin-left:6px;">ตำแหน่ง</span>
            <input type="text" class="rbopf-inp rp-inspector-position" name="rbo_inspector_position[]" placeholder="ตำแหน่ง" readonly>
            <button type="button" class="rbopf-del-btn" title="ลบ" onclick="this.closest('.rbopdf-inspector-row').remove(); rbopdfRenumberInspectors();">✕</button>
        </div>
    </div>
    <button type="button" class="rbopf-add-btn" onclick="rbopdfAddInspectorRow();">+ เพิ่มผู้ตรวจ</button>

    <!-- 6. ลักษณะของสถานที่เกิดเหตุ -->
    <div class="sec-heading">6. ลักษณะของสถานที่เกิดเหตุ</div>
    <textarea class="rbopf-ta" name="rbo_scene_description" id="rbopdf_scene_description" rows="3" style="margin-left:20px;"></textarea>
    <div class="blank-line" style="margin-left:20px;"></div>

    <!-- Footer Page 1 -->
    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom">
            <div>ปฏิบัติงานตาม</div>
            <div>OPFS – CS – SP – 03</div>
        </div>
        <div class="form-footer-right sub-heading-bottom">
            F-CS-16 แก้ไขครั้งที่ 2<br>
            แก้ไขวันที่ 2 ก.ย. 63<br>
            เริ่มใช้ 1 ต.ค. 63
        </div>
    </div>
</div>

<!-- ==================== PAGE 2 ==================== -->
<div class="rbopf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <input type="text" class="rbopf-inp rbopf-inp-s" name="rbo_report_no_display_pdf_2" id="rbopdf_report_no_display_2" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="rbopf-inp rbopf-inp-s" id="rbopdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">2/3</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="rbopf-inp" name="rbo_agency_name" id="rbopdf_agency_name_p2" readonly tabindex="-1">
    </div>

    <div class="fr i1" style="margin-top:4px;"><span class="fl">จากการตรวจสถานที่เกิดเหตุ</span></div>

    <!-- 7.1 สภาพของสถานที่เกิดเหตุเมื่อไปถึง -->
    <div class="sub-heading">7.1 สภาพของสถานที่เกิดเหตุเมื่อไปถึง</div>
    <textarea class="rbopf-ta" name="rbo_scene_condition" id="rbopdf_scene_condition" rows="2" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- 7.2 ลักษณะสภาพศพ -->
    <div class="sub-heading" style="margin-top:4px;">7.2 ลักษณะสภาพศพ</div>
    <div class="fr i2">
        <span class="fl">7.2.1 พบศพ/ไม่พบศพ</span>
        <input type="text" class="rbopf-inp" name="rbo_body_found" id="rbopdf_body_found">
    </div>
    <div class="fr i2">
        <span class="fl">7.2.2 ตำแหน่งที่พบศพ</span>
        <input type="text" class="rbopf-inp" name="rbo_body_position" id="rbopdf_body_position">
    </div>
    <div class="fr i2">
        <span class="fl">7.2.3 สภาพศพ</span>
        <input type="text" class="rbopf-inp" name="rbo_body_condition" id="rbopdf_body_condition">
    </div>
    <div class="fr i2">
        <span class="fl">7.2.4 สภาพเครื่องแต่งกายและทรัพย์สิน</span>
        <input type="text" class="rbopf-inp" name="rbo_body_clothing" id="rbopdf_body_clothing">
    </div>
    <div class="fr i2">
        <span class="fl">7.2.5 รอยบาดแผลที่ศพ</span>
        <input type="text" class="rbopf-inp" name="rbo_body_wounds" id="rbopdf_body_wounds">
    </div>

    <!-- 7.3 สภาพความเสียหาย -->
    <div class="sub-heading" style="margin-top:4px;">7.3 สภาพความเสียหาย</div>
    <textarea class="rbopf-ta" name="rbo_damage_detail" id="rbopdf_damage_detail" rows="2" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- 7.3.1 ตำแหน่งที่เกิดการระเบิด -->
    <div class="sub-heading" style="margin-top:4px;">7.3.1 ตำแหน่งที่เกิดการระเบิด</div>
    <textarea class="rbopf-ta" name="rbo_explosion_position" id="rbopdf_explosion_position" rows="2" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- 7.4 ร่องรอยและวัตถุพยานที่ตรวจพบ -->
    <div class="sub-heading" style="margin-top:4px;">7.4 ร่องรอยและวัตถุพยานที่ตรวจพบในสถานที่เกิดเหตุ</div>
    <textarea class="rbopf-ta" name="rbo_evidence_found" id="rbopdf_evidence_found" rows="2" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- 7.5 วัตถุพยานที่ตรวจเก็บ -->
    <div class="sub-heading" style="margin-top:4px;">7.5 วัตถุพยานที่ตรวจเก็บในสถานที่เกิดเหตุ</div>
    <textarea class="rbopf-ta" name="rbo_evidence_collected" id="rbopdf_evidence_collected" rows="2" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- 7.6 การดำเนินการเกี่ยวกับวัตถุพยาน -->
    <div class="sub-heading" style="margin-top:4px;">7.6 การดำเนินการเกี่ยวกับวัตถุพยาน</div>
    <textarea class="rbopf-ta" name="rbo_evidence_action" id="rbopdf_evidence_action" rows="2" style="margin-left:40px;"></textarea>
    <div class="blank-line" style="margin-left:40px;"></div>

    <!-- Footer Page 2 -->
    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom">
            <div>ปฏิบัติงานตาม</div>
            <div>OPFS – CS – SP – 03</div>
        </div>
        <div class="form-footer-right sub-heading-bottom">
            F-CS-16 แก้ไขครั้งที่ 2<br>
            แก้ไขวันที่ 2 ก.ย. 63<br>
            เริ่มใช้ 1 ต.ค. 63
        </div>
    </div>
</div>

<!-- ==================== PAGE 3 ==================== -->
<div class="rbopf-page">
    <div class="page-header">
       <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจสถานที่เกิดเหตุที่</u>&nbsp;
            <input type="text" class="rbopf-inp rbopf-inp-s" name="rbo_report_no_display_pdf_3" id="rbopdf_report_no_display_3" readonly style="display:inline-block; width:auto; min-width:80px; max-width:200px;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="rbopf-inp rbopf-inp-s" id="rbopdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">3/3</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="rbopf-inp" name="rbo_agency_name" id="rbopdf_agency_name_p3" readonly tabindex="-1">
    </div>

    <!-- 7.7 การส่งมอบสถานที่เกิดเหตุ -->
    <div class="sub-heading" style="margin-top:6px;">7.7 เจ้าหน้าที่ กสก.พฐก. / กสก.ศพฐ. / พฐ.จว.
        <input type="text" class="rbopf-inp" name="rbo_handover_officer" id="rbopdf_handover_officer" style="display:inline-block; width:200px;">
        <span>ตรวจสถานที่เกิดเหตุเสร็จสิ้น</span>
    </div>
    <div class="fr i2" style="margin-top:2px;">
        <span class="fl">พร้อมทั้งส่งมอบสถานที่เกิดเหตุคืนให้กับ</span>
        <input type="text" class="rbopf-inp" name="rbo_handover_to" id="rbopdf_handover_to">
    </div>
    <div class="fr i2">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="rbopf-inp rbopf-inp-m" name="rbo_handover_date" id="rbopdf_handover_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rbopf-inp rbopf-inp-m" name="rbo_handover_time" id="rbopdf_handover_time">
        <span class="fl">น.</span>
    </div>

    <!-- ลงชื่อ -->
    <div class="signature-block">
        <div>ลงชื่อ ........................................ ผู้รายงาน</div>
        <div>(<input type="text" class="rbopf-inp" name="rbo_signer_name" id="rbopdf_signer_name" style="width:200px; text-align:center; display:inline-block;">)</div>
        <div>ตำแหน่ง <input type="text" class="rbopf-inp" name="rbo_signer_position" id="rbopdf_signer_position" style="width:200px; text-align:center; display:inline-block;"></div>
        <div>วันที่ <input type="date" class="rbopf-inp" name="rbo_sign_date" id="rbopdf_sign_date" style="width:180px; text-align:center; display:inline-block;"></div>
    </div>

    <!-- Footer Page 3 -->
    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom">
            <div>ปฏิบัติงานตาม</div>
            <div>OPFS – CS – SP – 03</div>
        </div>
        <div class="form-footer-right sub-heading-bottom">
            F-CS-16 แก้ไขครั้งที่ 2<br>
            แก้ไขวันที่ 2 ก.ย. 63<br>
            เริ่มใช้ 1 ต.ค. 63
        </div>
    </div>
</div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_report_bomb_outdoor_pdf">
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
/* ========== Inspector Row Management for Bomb Outdoor PDF ========== */
function rbopdfAddInspectorRow() {
    var container = document.getElementById('rbopdf_inspector_container');
    var count = container.querySelectorAll('.rbopdf-inspector-row').length + 1;
    var opts = '<option value="">-- เลือกผู้ตรวจ --</option>';
    if (typeof rpUsersList !== 'undefined' && rpUsersList.length > 0) {
        rpUsersList.forEach(function(u) {
            opts += '<option value="' + u.fullname + '">' + u.fullname + '</option>';
        });
    }
    var html = '<div class="fr i1 rbopdf-inspector-row">' +
        '<span class="fl rbopdf-inspector-num">5.' + count + '.</span>' +
        '<select class="rbopf-select rp-inspector-select" name="rbo_inspector_name[]">' + opts + '</select>' +
        '<span class="fl" style="margin-left:6px;">ตำแหน่ง</span>' +
        '<input type="text" class="rbopf-inp rp-inspector-position" name="rbo_inspector_position[]" placeholder="ตำแหน่ง" readonly>' +
        '<button type="button" class="rbopf-del-btn" title="ลบ" onclick="this.closest(\'.rbopdf-inspector-row\').remove(); rbopdfRenumberInspectors();">✕</button>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
    var $modal = $('#modalReportBombOutdoorPdf');
    var $newSelect = $(container).find('.rbopdf-inspector-row:last .rp-inspector-select');
    $newSelect.select2({
        theme: 'bootstrap-5', width: '100%', placeholder: '-- เลือกผู้ตรวจ --',
        allowClear: true, dropdownParent: $modal
    });
}

function rbopdfRenumberInspectors() {
    var rows = document.querySelectorAll('#rbopdf_inspector_container .rbopdf-inspector-num');
    rows.forEach(function(el, idx) { el.textContent = '5.' + (idx + 1) + '.'; });
}

/* Sync agency name from page 1 to pages 2-3 */
document.addEventListener('DOMContentLoaded', function() {
    $('#rbopdf_agency_name').on('input change', function() {
        var v = $(this).val();
        $('#rbopdf_agency_name_p2').val(v);
        $('#rbopdf_agency_name_p3').val(v);
    });

    $('#modalReportBombOutdoorPdf').on('shown.bs.modal', function() {
        var v = $('#rbopdf_agency_name').val();
        if (v) { $('#rbopdf_agency_name_p2').val(v); $('#rbopdf_agency_name_p3').val(v); }

        var $modal = $(this);
        if (typeof rpLoadUsers === 'function') {
            rpLoadUsers(function() {
                $modal.find('#rbopdf_inspector_container .rp-inspector-select').each(function() {
                    var current = $(this).val();
                    $(this).html(rpBuildUserOptions(current));
                });
                $modal.find('#rbopdf_inspector_container .rp-inspector-select').each(function() {
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
