<?php
/**
 * Modal: ร่างรายงานตรวจเก็บวัตถุพยานบุคคล (ฟอร์มเสมือน PDF)
 * A4-paper style editable form — uses same pe_ field names as modal_report_person_evidence.php
 * Modal ID: modalReportPersonEvidencePdf   Form ID: formReportPersonEvidencePdf
 */
?>

<style>
/* ========== Person Evidence Report PDF Form — scoped to #modalReportPersonEvidencePdf ========== */
#modalReportPersonEvidencePdf .pepf-body { background: #bbb; }
#modalReportPersonEvidencePdf .pepf-page {
    width: 210mm; min-height: 297mm; margin: 16px auto; padding: 12mm 15mm 10mm 15mm;
    background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.25); position: relative;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; color: #000;
    display: flex; flex-direction: column; page-break-after: always;
}
#modalReportPersonEvidencePdf .pepf-page:last-child { page-break-after: auto; }

/* Row helpers */
#modalReportPersonEvidencePdf .fr  { display: flex; flex-wrap: wrap; align-items: baseline; line-height: 1.9; width: 100%; }
#modalReportPersonEvidencePdf .fl  { font-size: 14px; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportPersonEvidencePdf .fl-b{ font-size: 14px; font-weight: 700; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportPersonEvidencePdf .i1  { padding-left: 20px; }
#modalReportPersonEvidencePdf .i2  { padding-left: 40px; }
#modalReportPersonEvidencePdf .sec-heading  { font-weight: 700; font-size: 14px; margin-top: 2px; margin-bottom: 1px; }
#modalReportPersonEvidencePdf .sub-heading  { font-weight: 600; font-size: 14px; margin-left: 20px; }
#modalReportPersonEvidencePdf .form-title   { text-decoration: underline; text-align: center; font-size: 15px; font-weight: 700; margin: 6px auto 4px auto; }
#modalReportPersonEvidencePdf .page-header  { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0; }
#modalReportPersonEvidencePdf .form-footer  { margin-top: auto; padding-top: 6px; font-size: 10px; color: #333; display: flex; justify-content: space-between; }
#modalReportPersonEvidencePdf .form-footer-right { text-align: right; white-space: nowrap; line-height: 1.4; }
#modalReportPersonEvidencePdf .signature-block { margin-top: 30px; text-align: center; padding-left: 50%; line-height: 2; }

/* Editable input styles — dotted bottom, transparent */
#modalReportPersonEvidencePdf .pepf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; min-width: 40px; flex: 1; margin: 0 4px;
    color: #000;
}
#modalReportPersonEvidencePdf .pepf-inp:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }
#modalReportPersonEvidencePdf .pepf-inp-s { width: 60px; max-width: 80px; text-align: center; flex: 0 0 auto; }
/* #modalReportPersonEvidencePdf #pepdf_report_no_display {
    width: 260px !important;
    max-width: 260px !important;
    text-align: left;
} */
#modalReportPersonEvidencePdf .pepf-inp-m { flex: 0 1 140px; min-width: 80px; text-align: center; }
#modalReportPersonEvidencePdf .pepf-inp-l { flex: 1; min-width: 160px; }
#modalReportPersonEvidencePdf .pepf-inp-s,
#modalReportPersonEvidencePdf .pepf-inp-m,
#modalReportPersonEvidencePdf .pepf-inp-l {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; margin: 0 4px; color: #000;
}
#modalReportPersonEvidencePdf .pepf-inp-s:focus,
#modalReportPersonEvidencePdf .pepf-inp-m:focus,
#modalReportPersonEvidencePdf .pepf-inp-l:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Textarea — dotted lines background */
#modalReportPersonEvidencePdf .pepf-ta {
    width: 100%; border: none; border-bottom: 1px dotted #888;
    background: transparent; outline: none; resize: vertical;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.8;
    padding: 0 4px; min-height: 24px; margin: 0 4px 2px 20px; color: #000;
    background-image: repeating-linear-gradient(transparent, transparent 27px, #ddd 27px, #ddd 28px);
    background-position: 0 0;
}
#modalReportPersonEvidencePdf .pepf-ta:focus { border-bottom-color: #0d6efd; }

/* Checkbox — square style */
#modalReportPersonEvidencePdf .ck { display: inline-flex; align-items: baseline; margin-right: 8px; font-size: 14px; white-space: nowrap; }
#modalReportPersonEvidencePdf .pepf-cb {
    -webkit-appearance: none; appearance: none;
    width: 14px; height: 14px; margin-right: 3px; cursor: pointer; position: relative; top: 2px;
    border: 1.5px solid #000; border-radius: 0; background: #fff;
}
#modalReportPersonEvidencePdf .pepf-cb:checked { background: #fff; }
#modalReportPersonEvidencePdf .pepf-cb:checked::after {
    content: '✓'; position: absolute; top: -3px; left: 1px;
    font-size: 13px; font-weight: bold; color: #000; line-height: 1;
}

/* Add/Remove buttons */
#modalReportPersonEvidencePdf .pepf-add-btn {
    font-size: 12px; padding: 1px 8px; border: 1px dashed #0d6efd; color: #0d6efd;
    background: transparent; border-radius: 4px; cursor: pointer; margin-left: 20px; margin-top: 2px;
}
#modalReportPersonEvidencePdf .pepf-add-btn:hover { background: rgba(13,110,253,.08); }
#modalReportPersonEvidencePdf .pepf-del-btn {
    font-size: 11px; padding: 0 5px; border: none; color: #dc3545; background: transparent; cursor: pointer; margin-left: 4px;
}

/* Select dropdown */
#modalReportPersonEvidencePdf .pepf-select {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 26px; flex: 1; margin: 0 4px; color: #000;
    cursor: pointer; min-width: 120px;
}
#modalReportPersonEvidencePdf .pepf-select:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Select2 inside PDF form — compact */
#modalReportPersonEvidencePdf .pepdf-inspector-row .select2-container { flex: 1; min-width: 120px; margin: 0 4px; }
#modalReportPersonEvidencePdf .pepdf-inspector-row .select2-container--bootstrap-5 .select2-selection {
    min-height: 26px !important; height: 26px !important; padding: 0 4px !important;
    border: none !important; border-bottom: 1px dotted #888 !important; border-radius: 0 !important;
    background: transparent !important; font-size: 14px !important;
}
#modalReportPersonEvidencePdf .pepdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    padding: 0 !important; line-height: 26px !important; font-size: 14px !important; color: #000 !important;
}
#modalReportPersonEvidencePdf .pepdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
    height: 26px !important;
}
</style>

<div class="modal fade" id="modalReportPersonEvidencePdf" aria-labelledby="modalReportPersonEvidencePdfLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 240mm;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportPersonEvidencePdfLabel">
                    รายงานการตรวจเก็บวัตถุพยานที่บุคคล
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body pepf-body p-0" style="max-height: 80vh; overflow-y: auto;">
                <form id="formReportPersonEvidencePdf" novalidate>
                    <input type="hidden" id="pepdf_incident_id" name="incident_id">

                    <!-- Switch back to standard form -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="pe_editInfoPdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="pe_editCountPdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchPersonEvidenceToStd" checked
                                   style="width: 3rem; height: 1.5rem; cursor: pointer;"
                                   onchange="if(!this.checked){ this.checked=true; switchPersonEvidenceReportToStandard(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchPersonEvidenceToStd" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ==================== PAGE 1 ==================== -->
<div class="pepf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจเก็บวัตถุพยานที่</u>&nbsp;
            <input type="text" class="pepf-inp pepf-inp-s" name="pe_report_no_display_pdf" id="pepdf_report_no_display" readonly style="display:inline-block; width:auto; min-width:40px; max-width:200px;text-align:center;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="pepf-inp pepf-inp-s" name="pe_report_year_pdf" id="pepdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">1/2</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="pepf-inp" name="pe_agency_name" id="pepdf_agency_name">
    </div>

    <div class="form-title">รายงานการตรวจเก็บวัตถุพยานที่บุคคล</div>

    <!-- 1. การรับแจ้งเหตุ -->
    <div class="sec-heading">1. <u>การรับแจ้งเหตุ</u></div>
    <div class="fr i1" style="flex-wrap:nowrap;">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="pepf-inp pepf-inp-m" name="pe_receive_date" id="pepdf_receive_date">
        <span class="fl">เวลา</span>
        <input type="time" class="pepf-inp pepf-inp-m" name="pe_receive_time" id="pepdf_receive_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">ได้รับแจ้งตาม&nbsp;&nbsp;</span>
        <label class="ck"><input type="checkbox" class="pepf-cb" name="pe_notify_method[]" value="หนังสือ" id="pepdf_notify_letter">&nbsp;หนังสือ</label>
        <span class="fl">&nbsp;/&nbsp;</span>
        <label class="ck"><input type="checkbox" class="pepf-cb" name="pe_notify_method[]" value="โทรศัพท์" id="pepdf_notify_phone">&nbsp;ทางโทรศัพท์</label>
        <span class="fl">&nbsp;/&nbsp;</span>
        <label class="ck"><input type="checkbox" class="pepf-cb" name="pe_notify_method[]" value="วิทยุสื่อสาร" id="pepdf_notify_radio">&nbsp;วิทยุสื่อสาร</label>
        <span class="fl">&nbsp;/&nbsp;</span>
        <label class="ck"><input type="checkbox" class="pepf-cb" name="pe_notify_method[]" value="อื่นๆ" id="pepdf_notify_other">&nbsp;อื่นๆ</label>
        <input type="text" class="pepf-inp pepf-inp-m" name="pe_notify_method_other_text" id="pepdf_notify_method_other_text" placeholder="ระบุ...">
    </div>
    <div class="fr i1">
        <span class="fl">จาก สน./สภ.</span>
        <input type="text" class="pepf-inp" name="pe_police_station" id="pepdf_police_station">
    </div>
    <div class="fr i1">
        <span class="fl">ตามหนังสือที่</span>
        <input type="text" class="pepf-inp pepf-inp-m" name="pe_document_no" id="pepdf_document_no">
        <span class="fl">ลงวันที่</span>
        <input type="date" class="pepf-inp pepf-inp-m" name="pe_document_date" id="pepdf_document_date">
    </div>
    <div class="fr i1">
        <span class="fl">คดีอาญาที่</span>
        <input type="text" class="pepf-inp pepf-inp-m" name="pe_case_no" id="pepdf_case_no">
    </div>
    <div class="fr i1">
        <span class="fl">สถานที่เกิดเหตุ</span>
        <input type="text" class="pepf-inp pepdf-location-auto" name="pe_incident_location" id="pepdf_incident_location">
    </div>
    <div class="fr i1">
        <input type="text" class="pepf-inp pepdf-location-auto" name="pe_incident_location_2" id="pepdf_incident_location_2" style="width:100%;">
    </div>
    <div class="fr i1" style="flex-wrap:nowrap;">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="pepf-inp pepf-inp-m" name="pe_incident_date" id="pepdf_incident_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="pepf-inp pepf-inp-m" name="pe_incident_time" id="pepdf_incident_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">โดยมี</span>
        <input type="text" class="pepf-inp" name="pe_investigator_name" id="pepdf_investigator_name">
        <span class="fl">เป็นพนักงานสอบสวนเจ้าของคดี</span>
    </div>

    <!-- รายการบุคคล -->
    <div class="fr i1" style="margin-top:4px;">
        <span class="fl-b">ขอตรวจเก็บวัตถุพยานจากบุคคล ดังต่อไปนี้</span>
    </div>
    <div id="pepdf_persons_container">
        <div class="fr i2 pepdf-person-row">
            <span class="fl">๑.๑ (</span>
            <select class="pepf-select" name="pe_person_prefix[]" style="flex:0 0 80px; min-width:60px;">
                <option value="">คำนำหน้า</option>
                <option value="นาย">นาย</option>
                <option value="นาง">นาง</option>
                <option value="นางสาว">นางสาว</option>
                <option value="เด็กชาย">เด็กชาย</option>
                <option value="เด็กหญิง">เด็กหญิง</option>
            </select>
            <span class="fl">)</span>
            <input type="text" class="pepf-inp" name="pe_person_name[]" placeholder="ชื่อ-นามสกุล">
            <button type="button" class="pepf-del-btn" title="ลบ">✕</button>
        </div>
    </div>
    <button type="button" class="pepf-add-btn" id="pepdf_add_person_btn">+ เพิ่มบุคคล</button>

    <!-- 2. จุดประสงค์ -->
    <div class="sec-heading" style="margin-top:6px;">2. <u>จุดประสงค์ในการตรวจเก็บวัตถุพยาน</u></div>
    <textarea class="pepf-ta" name="pe_purpose_detail" id="pepdf_purpose_detail" rows="2"></textarea>

    <!-- 3. ผลการตรวจ -->
    <div class="sec-heading" style="margin-top:6px;">3. <u>ผลการตรวจ</u></div>
    <div class="fr i1">
        <span class="fl">ได้ทำการตรวจเก็บวัตถุพยานที่</span>
        <input type="text" class="pepf-inp" name="pe_inspect_location" id="pepdf_inspect_location">
    </div>
    <div class="fr i1" style="flex-wrap:nowrap;">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="pepf-inp pepf-inp-m" name="pe_inspect_date" id="pepdf_inspect_date">
        <span class="fl">เวลา</span>
        <input type="time" class="pepf-inp pepf-inp-m" name="pe_inspect_time" id="pepdf_inspect_time">
        <span class="fl">น. มีรายละเอียดดังนี้</span>
    </div>

    <!-- 3.1 ข้อมูลส่วนบุคคล -->
    <div class="sub-heading" style="margin-top:4px;">3.1 ข้อมูลส่วนบุคคล</div>
    <div id="pepdf_person_info_container">
        <div class="pepdf-person-info-block" style="margin-left:40px; margin-bottom:4px;">
            <div class="fr">
                <span class="fl">๓.๑.๑ บุคคลที่ ๑ (</span>
                <select class="pepf-select" name="pe_pi_prefix[]" style="flex:0 0 80px; min-width:60px;">
                    <option value="">คำนำหน้า</option>
                    <option value="นาย">นาย</option>
                    <option value="นาง">นาง</option>
                    <option value="นางสาว">นางสาว</option>
                    <option value="เด็กชาย">เด็กชาย</option>
                    <option value="เด็กหญิง">เด็กหญิง</option>
                </select>
                <span class="fl">)</span>
                <input type="text" class="pepf-inp" name="pe_pi_fullname[]" placeholder="ชื่อ-นามสกุล">
                <button type="button" class="pepf-del-btn pepdf-remove-person-info" title="ลบ">✕</button>
            </div>
            <div class="fr" style="padding-left:20px; flex-wrap:nowrap;">
                <span class="fl">บัตรประชาชน</span>
                <input type="text" class="pepf-inp pepf-inp-m" name="pe_pi_id_card[]" maxlength="13">
                <span class="fl">หนังสือเดินทาง</span>
                <input type="text" class="pepf-inp pepf-inp-m" name="pe_pi_passport[]">
            </div>
            <div class="fr" style="padding-left:20px; flex-wrap:nowrap;">
                <span class="fl">สูง</span>
                <input type="text" class="pepf-inp pepf-inp-s" name="pe_pi_height[]">
                <span class="fl">ซม. อายุ</span>
                <input type="text" class="pepf-inp pepf-inp-s" name="pe_pi_age[]">
                <span class="fl">ปี สีผิว</span>
                <input type="text" class="pepf-inp pepf-inp-s" name="pe_pi_skin[]">
                <span class="fl">มือที่ถนัด</span>
                <select class="pepf-select" name="pe_pi_hand[]" style="flex:0 0 60px; min-width:50px;">
                    <option value="">-</option>
                    <option value="ขวา">ขวา</option>
                    <option value="ซ้าย">ซ้าย</option>
                    <option value="ทั้งสอง">ทั้งสอง</option>
                </select>
            </div>
            <div class="fr" style="padding-left:20px;">
                <span class="fl">ลักษณะพิเศษ</span>
                <input type="text" class="pepf-inp" name="pe_pi_feature[]">
            </div>
        </div>
    </div>
    <button type="button" class="pepf-add-btn" id="pepdf_add_person_info_btn">+ เพิ่มข้อมูลบุคคล</button>

    <div class="form-footer">
        <div></div>
        <div class="form-footer-right">รายงานการตรวจเก็บวัตถุพยานที่บุคคล</div>
    </div>
</div>

<!-- ==================== PAGE 2 ==================== -->
<div class="pepf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจเก็บวัตถุพยานที่</u>&nbsp;
            <input type="text" class="pepf-inp pepf-inp-s" name="pepdf_report_no_display_p2" id="pepdf_report_no_display_p2" readonly style="display:inline-block; width:auto; min-width:40px; max-width:200px;text-align:center;" tabindex="-1">
            <span id="pepdf_report_no_display_p2" style="font-size:13px;"></span>
            &nbsp;/25&nbsp;
            <span class="pepf-inp pepf-inp-s" id="pepdf_report_year_p2" style="font-size:13px;"><?= substr((date('Y') + 543), -2) ?></span>
        </div>
        <div style="font-size:13px; text-align:right;">2/2</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="pepf-inp" name="pe_agency_name_2" id="pepdf_agency_name_2">
    </div>
    <!-- 3.2 รายละเอียดวัตถุพยาน -->
    <div class="sub-heading" style="margin-top:4px;">3.2 รายละเอียดวัตถุพยานที่ตรวจเก็บ</div>
    <div id="pepdf_evidence_container">
        <div class="pepdf-evidence-row" style="margin-left:40px; margin-bottom:2px;">
            <div class="fr">
                <span class="fl">๓.๒.๑ ตัวอย่าง</span>
                <input type="text" class="pepf-inp" name="pe_ev_detail[]" placeholder="เช่น เนื้อเยื่อบุกระพุ้งแก้ม">
                <button type="button" class="pepf-del-btn pepdf-remove-evidence" title="ลบ">✕</button>
            </div>
            <div class="fr" style="padding-left:20px; flex-wrap:nowrap;">
                <span class="fl">จำนวน</span>
                <input type="text" class="pepf-inp pepf-inp-s" name="pe_ev_qty[]">
                <span class="fl">ส่งตรวจ</span>
                <select class="pepf-select lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="min-width:200px;">
                    <option value="">-- เลือกกลุ่มงาน (เลือกได้หลายข้อ) --</option>
                    <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                    <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                    <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                    <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                    <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                    <option value="document">กลุ่มงานตรวจเอกสาร</option>
                    <option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                </select>
                <input type="hidden" class="lab-unit-value" name="pe_ev_lab_unit[]" value="">
            </div>
        </div>
    </div>
    <button type="button" class="pepf-add-btn" id="pepdf_add_evidence_btn">+ เพิ่มรายการวัตถุพยาน</button>

    <!-- 3.3 การดำเนินการเกี่ยวกับวัตถุพยาน -->
    <div class="sub-heading" style="margin-top:8px;">3.3 การดำเนินการเกี่ยวกับวัตถุพยาน</div>
    <div class="fr i2">
        <span class="fl">ชื่อพยานในการตรวจเก็บ</span>
        <input type="text" class="pepf-inp" name="pe_witness_name" id="pepdf_witness_name">
    </div>
    <div class="fr i2">
        <span class="fl">แบบฟอร์มที่ใช้</span>
        <input type="text" class="pepf-inp" name="pe_witness_form" id="pepdf_witness_form">
    </div>
    <div class="fr i2">
        <span class="fl">รายละเอียด</span>
        <input type="text" class="pepf-inp" name="pe_witness_detail" id="pepdf_witness_detail">
    </div>
    <div class="fr i2">
        <span class="fl">โดย</span>
        <label class="ck"><input type="checkbox" class="pepf-cb" name="pe_handover_method_checks[]" value="นำส่ง" id="pepdf_handover_submit">&nbsp;นำส่ง</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="pepf-cb" name="pe_handover_method_checks[]" value="ส่งมอบ" id="pepdf_handover_transfer">&nbsp;ส่งมอบ</label>
        <span class="fl">ตามรายการวัตถุพยานที่</span>
        <input type="text" class="pepf-inp" name="pe_handover_item_ref" id="pepdf_handover_item_ref">
    </div>
    <div class="fr i2">
        <span class="fl">ให้</span>
        <input type="text" class="pepf-inp" name="pe_handover_to" id="pepdf_handover_to">
    </div>
    <div class="fr i2">
        <span class="fl">เพื่อ</span>
        <input type="text" class="pepf-inp" name="pe_handover_purpose" id="pepdf_handover_purpose">
    </div>

    <!-- ผู้ตรวจเก็บวัตถุพยาน -->
    <div style="margin-top:10px;">
        <div class="fr i1"><span class="fl-b">ผู้ตรวจเก็บวัตถุพยาน</span></div>
        <div id="pepdf_inspector_container">
            <div class="fr i2 pepdf-inspector-row">
                <span class="fl pepdf-inspector-num">1.</span>
                <select class="pepf-select pepdf-inspector-select" name="pe_inspector_name[]">
                    <option value="">-- เลือกผู้ตรวจ --</option>
                </select>
                <span class="fl">ตำแหน่ง</span>
                <input type="text" class="pepf-inp pepf-inp-m pepdf-inspector-position" name="pe_inspector_position[]" readonly>
                <button type="button" class="pepf-del-btn pepdf-remove-inspector" title="ลบ">✕</button>
            </div>
        </div>
        <button type="button" class="pepf-add-btn" id="pepdf_add_inspector_btn">+ เพิ่มผู้ตรวจ</button>
    </div>

    <!-- ลงชื่อ -->
    <div class="signature-block">
        <div>ลงชื่อ ........................................ ผู้รายงาน</div>
        <div>(<input type="text" class="pepf-inp" name="pe_signer_name" id="pepdf_signer_name" style="width:200px; text-align:center; display:inline-block;">)</div>
        <div>ตำแหน่ง <input type="text" class="pepf-inp" name="pe_signer_position" id="pepdf_signer_position" style="width:200px; text-align:center; display:inline-block;"></div>
        <div>วันที่ <input type="date" class="pepf-inp" name="pe_sign_date" id="pepdf_sign_date" style="width:180px; text-align:center; display:inline-block;"></div>
    </div>

    <div class="form-footer">
        <div></div>
        <div class="form-footer-right">รายงานการตรวจเก็บวัตถุพยานที่บุคคล</div>
    </div>
</div>

                </form>
            </div>

            <!-- Auto-wrap script for location fields -->
            <script>
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

                document.addEventListener('DOMContentLoaded', function() {
                    bindAutoWrap('#modalReportPersonEvidencePdf .pepdf-location-auto');
                });
            })();
            </script>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_report_person_evidence_pdf">
                    <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> ยกเลิก
                </button>
            </div>
        </div>
    </div>
</div>
