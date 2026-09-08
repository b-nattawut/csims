<?php
/**
 * Modal: รายงานการตรวจเก็บวัตถุพยานที่เกิดเหตุ (ฟอร์มเสมือน PDF)
 * complaints_type = '07'
 * Modal ID: sceneEvidenceFormPdfModal   Form ID: sceneEvidenceFormPdf
 * ออกแบบตามรูปแบบเดียวกับ modal_life_report_outdoor_pdf_form.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Query DB directly for reliable name data (session may have stale/empty values)
$sevpf_creator_fullname = '';
$sevpf_creator_position = '';
if (isset($_SESSION['user_id']) && isset($pdo)) {
    $stmtSevpfUser = $pdo->prepare("SELECT CONCAT(IFNULL(ur.rank_name,''),' ',IFNULL(up.first_name,''),' ',IFNULL(up.last_name,'')) AS fullname,
                                           IFNULL(upos.position_name,'') AS position_name
                                    FROM users u
                                    LEFT JOIN user_profile up ON u.user_id = up.user_id
                                    LEFT JOIN user_rank ur ON up.rank_id = ur.rank_id
                                    LEFT JOIN user_position upos ON up.position_id = upos.position_id
                                    WHERE u.user_id = ?");
    $stmtSevpfUser->execute([$_SESSION['user_id']]);
    $sevpfUserRow = $stmtSevpfUser->fetch(PDO::FETCH_ASSOC);
    if ($sevpfUserRow) {
        $sevpf_creator_fullname = trim($sevpfUserRow['fullname']);
        $sevpf_creator_position = trim($sevpfUserRow['position_name']);
    }
}
?>

<style>
/* ========== Scene Evidence PDF Form — scoped to #sceneEvidenceFormPdfModal ========== */
#sceneEvidenceFormPdfModal .sevpf-body { background: #bbb; }
#sceneEvidenceFormPdfModal .sevpf-page {
    width: 210mm; min-height: 297mm; margin: 16px auto; padding: 12mm 15mm 10mm 15mm;
    background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.25); position: relative;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; color: #000;
    display: flex; flex-direction: column; page-break-after: always;
}
#sceneEvidenceFormPdfModal .sevpf-page:last-child { page-break-after: auto; }

/* Row helpers */
#sceneEvidenceFormPdfModal .fr  { display: flex; flex-wrap: wrap; align-items: baseline; line-height: 1.9; width: 100%; }
#sceneEvidenceFormPdfModal .fl  { font-size: 14px; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#sceneEvidenceFormPdfModal .fl-b{ font-size: 14px; font-weight: 700; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#sceneEvidenceFormPdfModal .i1  { padding-left: 20px; }
#sceneEvidenceFormPdfModal .i2  { padding-left: 40px; }
#sceneEvidenceFormPdfModal .sec-heading  { font-weight: 700; font-size: 14px; margin-top: 2px; margin-bottom: 1px; }
#sceneEvidenceFormPdfModal .sub-heading  { font-weight: 600; font-size: 14px; margin-left: 20px; }
#sceneEvidenceFormPdfModal .form-title   { text-decoration: underline; text-align: center; font-size: 15px; font-weight: 700; margin: 6px auto 4px auto; }
#sceneEvidenceFormPdfModal .page-header  { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0; }
#sceneEvidenceFormPdfModal .form-footer  { margin-top: auto; padding-top: 6px; font-size: 10px; color: #333; display: flex; justify-content: space-between; }
#sceneEvidenceFormPdfModal .form-footer-right { text-align: right; white-space: nowrap; line-height: 1.4; }
#sceneEvidenceFormPdfModal .form-footer-left  { flex: 1; }
#sceneEvidenceFormPdfModal .sub-heading-bottom { font-weight: 600; font-size: 11px; margin-left: 20px; }
#sceneEvidenceFormPdfModal .signature-block { margin-top: 30px; text-align: center; padding-left: 50%; line-height: 2; }

/* Editable input styles */
#sceneEvidenceFormPdfModal .sevpf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; min-width: 40px; flex: 1; margin: 0 4px; color: #000;
}
#sceneEvidenceFormPdfModal .sevpf-inp:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }
#sceneEvidenceFormPdfModal .sevpf-inp-s { width: 60px; max-width: 80px; text-align: center; flex: 0 0 auto; }
#sceneEvidenceFormPdfModal .sevpf-inp-m { flex: 0 1 140px; min-width: 80px; text-align: center; }
#sceneEvidenceFormPdfModal .sevpf-inp-l { flex: 1; min-width: 160px; }
#sceneEvidenceFormPdfModal .sevpf-inp-s,
#sceneEvidenceFormPdfModal .sevpf-inp-m,
#sceneEvidenceFormPdfModal .sevpf-inp-l {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; margin: 0 4px; color: #000;
}
#sceneEvidenceFormPdfModal .sevpf-inp-s:focus,
#sceneEvidenceFormPdfModal .sevpf-inp-m:focus,
#sceneEvidenceFormPdfModal .sevpf-inp-l:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Textarea */
#sceneEvidenceFormPdfModal .sevpf-ta {
    width: 100%; border: none; border-bottom: 1px dotted #888;
    background: transparent; outline: none; resize: vertical;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.8;
    padding: 0 4px; min-height: 24px; margin: 0 4px 2px 20px; color: #000;
    background-image: repeating-linear-gradient(transparent, transparent 27px, #ddd 27px, #ddd 28px);
    background-position: 0 0;
}
#sceneEvidenceFormPdfModal .sevpf-ta:focus { border-bottom-color: #0d6efd; }

/* Checkbox — square style */
#sceneEvidenceFormPdfModal .ck { display: inline-flex; align-items: baseline; margin-right: 8px; font-size: 14px; white-space: nowrap; }
#sceneEvidenceFormPdfModal .sevpf-cb {
    -webkit-appearance: none; appearance: none;
    width: 14px; height: 14px; margin-right: 3px; cursor: pointer; position: relative; top: 2px;
    border: 1.5px solid #000; border-radius: 0; background: #fff;
}
#sceneEvidenceFormPdfModal .sevpf-cb:checked { background: #fff; }
#sceneEvidenceFormPdfModal .sevpf-cb:checked::after {
    content: '✓'; position: absolute; top: -3px; left: 1px;
    font-size: 13px; font-weight: bold; color: #000; line-height: 1;
}

/* Add/Remove buttons */
#sceneEvidenceFormPdfModal .sevpf-add-btn {
    font-size: 12px; padding: 1px 8px; border: 1px dashed #0d6efd; color: #0d6efd;
    background: transparent; border-radius: 4px; cursor: pointer; margin-left: 20px; margin-top: 2px;
}
#sceneEvidenceFormPdfModal .sevpf-add-btn:hover { background: rgba(13,110,253,.08); }
#sceneEvidenceFormPdfModal .sevpf-del-btn {
    font-size: 11px; padding: 0 5px; border: none; color: #dc3545; background: transparent; cursor: pointer; margin-left: 4px;
}

/* Select dropdown */
#sceneEvidenceFormPdfModal .sevpf-select {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 26px; flex: 1; margin: 0 4px; color: #000;
    cursor: pointer; min-width: 120px;
}
#sceneEvidenceFormPdfModal .sevpf-select:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Page bottom reference line */
#sceneEvidenceFormPdfModal .page-ref-line {
    text-align: right; font-size: 13px; margin-top: 12px; color: #555;
}
</style>

<div class="modal fade" id="sceneEvidenceFormPdfModal" aria-labelledby="sceneEvidenceFormPdfModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 240mm;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="sceneEvidenceFormPdfModalLabel">
                    รายงานการตรวจเก็บวัตถุพยานที่เกิดเหตุ
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body sevpf-body p-0" style="max-height: 80vh; overflow-y: auto;">
                <form id="sceneEvidenceFormPdf" novalidate>
                    <input type="hidden" id="sevpf_receiveNoti_id" name="receiveNoti_id_ev7">
                    <input type="hidden" id="sevpf_doc_no" name="doc_no_ev7">
                    <input type="hidden" id="sevpf_report_no" name="report_no_ev7">

                    <!-- Switch to standard form -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="editInfoEV7Pdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountEV7Pdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToStdFormEV7" checked
                                       style="width: 3rem; height: 1.5rem; cursor: pointer;"
                                       onchange="if(!this.checked){ this.checked=true; switchToSceneEvidenceStdForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToStdFormEV7" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์ม
                            </label>
                        </div>
                    </div>

<!-- ==================== PAGE 1 ==================== -->
<div class="sevpf-page">
    <!-- Header: บัญชีแนบท้ายคำสั่ง -->
    <!-- <div style="font-size: 11px; line-height: 1.4; margin-bottom: 4px;">
        บัญชีแนบท้ายคำสั่ง สำนักงานพิสูจน์หลักฐานตำรวจ ที่ ๘๔๘/๒๕๖๑ อวันที่ ๑๑ ธันวาคม ๒๕๖๑
    </div> -->
    <div class="page-header">
        <div style="font-size: 13px; line-height: 1.6;">
            <u>รายงานการตรวจเก็บวัตถุพยานที่</u>&nbsp;
            <input type="text" class="sevpf-inp" name="sevpf_report_ref" id="sevpf_report_ref" style="display:inline-block; width:auto; min-width:40px; max-width:200px;text-align:center;">
            <span>/25</span>
            <input type="text" class="sevpf-inp sevpf-inp-s" name="sevpf_report_year" id="sevpf_report_year" style="display:inline-block; width:50px;">
        </div>
        <div style="font-size: 13px; text-align: right;"><span class="sevpf-page-num">1</span>/<span class="sevpf-total-pages">2</span></div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="sevpf-inp" name="sevpf_unit_name" id="sevpf_unit_name">
    </div>

    <div class="form-title">รายงานการตรวจเก็บวัตถุพยานที่เกิดเหตุ</div>

    <!-- 1. สิ่งที่ได้รับจาก /การรับแจ้งเหตุ -->
    <div class="sec-heading">1. <u>สิ่งที่ได้รับจาก /การรับแจ้งเหตุ</u></div>
    <div class="fr i1" style="flex-wrap:nowrap;">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="sevpf-inp sevpf-inp-m" name="sevpf_receive_date" id="sevpf_receive_date">
        <span class="fl">เวลา</span>
        <input type="time" class="sevpf-inp sevpf-inp-m" name="sevpf_receive_time" id="sevpf_receive_time">
        <span class="fl">น. กลุ่มงานตรวจที่เกิดเหตุ กองพิสูจน์หลักฐานกลาง/</span>
    </div>
    <div class="fr i1">
        <span class="fl">ศูนย์พิสูจน์หลักฐาน</span>
        <input type="text" class="sevpf-inp" name="sevpf_center_name" style="max-width: 140px;">
        <span class="fl">/พิสูจน์หลักฐานจังหวัด</span>
        <input type="text" class="sevpf-inp" name="sevpf_province_name" style="max-width: 140px;">
        <span class="fl">ได้รับแจ้ง (</span>
        <label class="ck"><input type="checkbox" class="sevpf-cb" name="sevpf_notify_method[]" value="ตามหนังสือ">&nbsp;ตามหนังสือ</label>
        <span class="fl">/</span>
    </div>
    <div class="fr i1">
        <label class="ck"><input type="checkbox" class="sevpf-cb" name="sevpf_notify_method[]" value="ทางโทรศัพท์">&nbsp;ทางโทรศัพท์</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="sevpf-cb" name="sevpf_notify_method[]" value="ทางวิทยุสื่อสาร">&nbsp;วิทยุสื่อสาร</label>
        <span class="fl">) จากสถานีตำรวจนครบาล/สถานีตำรวจภูธร</span>
    </div>
    <div class="fr i1">
        <span class="fl">จาก สน./สภ.</span>
        <input type="text" class="sevpf-inp" name="sevpf_police_station" id="sevpf_police_station">
    </div>
    <div class="fr i1">
        <span class="fl">ที่</span>
        <input type="text" class="sevpf-inp" name="sevpf_document_no" id="sevpf_document_no" style="max-width: 200px;">
        <span class="fl">ลงวันที่</span>
        <input type="date" class="sevpf-inp" name="sevpf_document_date" id="sevpf_document_date" style="max-width: 200px;" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="fr i1">
        <span class="fl">สถานที่เกิดเหตุ</span>
        <input type="text" class="sevpf-inp" name="sevpf_incident_location" id="sevpf_incident_location">
    </div>
    <div class="fr i1">
        <span class="fl">เหตุเกิดเมื่อวันที่</span>
        <input type="date" class="sevpf-inp sevpf-inp-m" name="sevpf_incident_date" id="sevpf_incident_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="sevpf-inp sevpf-inp-m" name="sevpf_incident_time" id="sevpf_incident_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">มี</span>
        <input type="text" class="sevpf-inp" name="sevpf_investigator_name" id="sevpf_investigator_name">
        <span class="fl">เป็นพนักงานสอบสวน</span>
    </div>
    <div class="fr i1" style="margin-top: 2px;">
        <span class="fl">มีรายการของกลางดังนี้</span>
    </div>

    <!-- รายการของกลาง (dynamic) -->
    <div id="sevpf_evidence_items_container" style="padding-left: 20px;">
        <div class="fr sevpf-evidence-item-row" style="flex-wrap:wrap; gap:2px;">
            <span class="fl sevpf-si-no" style="min-width:30px;">1.1</span>
            <input type="text" class="sevpf-inp" name="sevpf_evidence_item[]" style="flex:1; min-width:120px;">
            <select class="sevpf-select" name="sevpf_lab_unit[]" style="max-width:180px; font-size:0.75rem;">
                <option value="">-- การตรวจพิสูจน์ --</option>
                <option value="bio_dna">ตรวจชีววิทยา</option>
                <option value="chemical">ตรวจทางเคมีฟิสิกส์</option>
                <option value="fingerprint">ตรวจลายนิ้วมือแฝง</option>
                <option value="drug">ตรวจยาเสพติด</option>
                <option value="gun">ตรวจอาวุธปืนฯ</option>
                <option value="document">ตรวจเอกสาร</option>
                <option value="digital">ตรวจพิสูจน์ดิจิทัล</option><option value="computer">ตรวจอาชญากรรมคอมพิวเตอร์</option>
            </select>
            <button type="button" class="sevpf-del-btn" onclick="this.closest('.sevpf-evidence-item-row').remove(); sevpfRenumberEvidenceItems();">✕</button>
        </div>
    </div>
    <button type="button" class="sevpf-add-btn" onclick="sevpfAddEvidenceItem();">+ เพิ่มรายการของกลาง</button>

    <!-- 2. จุดประสงค์ในการตรวจ -->
    <div class="sec-heading" style="margin-top: 8px;">2. <u>จุดประสงค์ในการตรวจ</u></div>
    <div class="fr i1">
        <span class="fl">เพื่อทำการตรวจเก็บ (</span>
        <label class="ck"><input type="checkbox" class="sevpf-cb" name="sevpf_purpose[]" value="รอยลายนิ้วมือแฝง">&nbsp;รอยลายนิ้วมือแฝง</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="sevpf-cb" name="sevpf_purpose[]" value="สารพันธุกรรม">&nbsp;สารพันธุกรรม</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="sevpf-cb" name="sevpf_purpose[]" value="วัตถุพยานอื่นๆ">&nbsp;วัตถุพยานอื่นๆ</label>
        <span class="fl">) ที่</span>
    </div>
    <div class="fr i1">
        <span class="fl">ของกลางดังกล่าว</span>
        <input type="text" class="sevpf-inp" name="sevpf_purpose_detail" id="sevpf_purpose_detail">
    </div>

    <!-- 3. ผลการตรวจ -->
    <div class="sec-heading" style="margin-top: 8px;">3. <u>ผลการตรวจ</u></div>
    <div class="fr i1">
        <span class="fl">ได้ทำการตรวจของกลางที่</span>
        <input type="text" class="sevpf-inp" name="sevpf_inspect_location" id="sevpf_inspect_location" style="max-width: 200px;">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="sevpf-inp sevpf-inp-m" name="sevpf_inspect_date" id="sevpf_inspect_date">
    </div>
    <div class="fr i1">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="sevpf-inp sevpf-inp-m" name="sevpf_inspect_time" id="sevpf_inspect_time">
        <span class="fl">น.</span>
    </div>

    <!-- 3.1 ลักษณะของกลาง -->
    <div class="fr i1" style="margin-top: 4px;">
        <span class="fl-b">3.1 ลักษณะของกลาง</span>
        <span class="fl" style="font-weight:normal;">(ให้ระบุสภาพทั่วไป เช่น รูปร่าง สี ขนาด ยี่ห้อ เป็นต้นและจำนวน)</span>
    </div>
    <div id="sevpf_exhibit_desc_container" style="padding-left: 40px;">
        <div class="fr sevpf-exhibit-row">
            <span class="fl sevpf-si-no" style="min-width:50px;">3.1.1</span>
            <span class="fl">ของกลางรายการที่ 1 เป็น</span>
            <input type="text" class="sevpf-inp" name="sevpf_exhibit_desc[]">
            <button type="button" class="sevpf-del-btn" onclick="this.closest('.sevpf-exhibit-row').remove(); sevpfRenumberExhibits();">✕</button>
        </div>
    </div>
    <button type="button" class="sevpf-add-btn" style="margin-left: 40px;" onclick="sevpfAddExhibitDesc();">+ เพิ่มรายการของกลาง</button>

    <!-- Footer Page 1 -->
    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom"></div>
        <div class="form-footer-right sub-heading-bottom">
            บัญชีแนบท้ายคำสั่ง สพฐ.ตร.<br>
            ที่ ๘๔๘/๒๕๖๑
        </div>
    </div>
</div>

<!-- ==================== PAGE 2 ==================== -->
<div class="sevpf-page">
    <!-- Header -->
    <!-- <div style="font-size: 11px; line-height: 1.4; margin-bottom: 4px;">
        บัญชีแนบท้ายคำสั่ง สำนักงานพิสูจน์หลักฐานตำรวจ ที่ ๘๔๘/๒๕๖๑ อวันที่ ๑๑ ธันวาคม ๒๕๖๑
    </div> -->
    <div class="page-header">
        <div style="font-size: 13px; line-height: 1.6;">
            <u>รายงานการตรวจเก็บวัตถุพยานที่</u>&nbsp;
            <input type="text" class="sevpf-inp" name="sevpf_report_ref_2" id="sevpf_report_ref_2" style="display:inline-block; width:auto; min-width:40px; max-width:200px;text-align:center;">
            <span>/25</span>
            <input type="text" class="sevpf-inp sevpf-inp-s" name="sevpf_report_year_2" id="sevpf_report_year_2" style="display:inline-block; width:50px;">
        </div>
        <div style="font-size: 13px; text-align: right;"><span class="sevpf-page-num">2</span>/<span class="sevpf-total-pages">2</span></div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="sevpf-inp" name="sevpf_unit_name_2" id="sevpf_unit_name_2">
    </div>
    <!-- 3.2 วัตถุพยานที่ตรวจเก็บ -->
    <div class="fr" style="margin-top: 4px;">
        <span class="fl-b">3.2 วัตถุพยานที่ตรวจเก็บ</span>
    </div>

    <!-- 3.2.1 ตรวจเก็บ -->
    <div class="fr i1">
        <span class="fl-b">3.2.1</span>
        <span class="fl" style="margin-left:4px;">ตรวจเก็บ (</span>
        <label class="ck"><input type="checkbox" class="sevpf-cb" name="sevpf_collect_type[]" value="รอยลายนิ้วมือแฝง">&nbsp;รอยลายนิ้วมือแฝง</label>
        <span class="fl">,</span>
        <label class="ck"><input type="checkbox" class="sevpf-cb" name="sevpf_collect_type[]" value="ฝ่ามือแฝง">&nbsp;ฝ่ามือแฝง</label>
        <span class="fl">,</span>
        <label class="ck"><input type="checkbox" class="sevpf-cb" name="sevpf_collect_type[]" value="ฝ่าเท้าแฝง">&nbsp;ฝ่าเท้าแฝง</label>
        <span class="fl">) จำนวน</span>
        <input type="text" class="sevpf-inp sevpf-inp-s" name="sevpf_collect_sheet_count" id="sevpf_collect_sheet_count" style="width: 50px;">
        <span class="fl">แผ่น ที่</span>
    </div>

    <!-- รายละเอียดตรวจเก็บ (dynamic) -->
    <div id="sevpf_collect_detail_container" style="padding-left: 40px;">
        <div class="fr sevpf-collect-row">
            <span class="fl sevpf-si-no" style="min-width:60px;">3.2.1.1</span>
            <input type="text" class="sevpf-inp" name="sevpf_collect_detail[]">
            <button type="button" class="sevpf-del-btn" onclick="this.closest('.sevpf-collect-row').remove(); sevpfRenumberCollectDetails();">✕</button>
        </div>
    </div>
    <button type="button" class="sevpf-add-btn" style="margin-left: 40px;" onclick="sevpfAddCollectDetail();">+ เพิ่มรายละเอียด</button>

    <!-- 3.2.2 วัตถุพยานประเภทอื่น -->
    <div class="fr i1" style="margin-top: 6px;">
        <span class="fl-b">3.2.2</span>
        <span class="fl" style="margin-left:4px;">(ระบุวัตถุพยานประเภทอื่น)</span>
        <input type="text" class="sevpf-inp" name="sevpf_other_evidence_text" id="sevpf_other_evidence_text">
    </div>

    <!-- 3.3 การดำเนินการเกี่ยวกับวัตถุพยาน -->
    <div class="fr" style="margin-top: 10px;">
        <span class="fl-b">3.3 การดำเนินการเกี่ยวกับวัตถุพยาน</span>
    </div>
    <div class="fr i1">
        <span class="fl">ได้ให้</span>
        <input type="text" class="sevpf-inp" name="sevpf_witness_name" id="sevpf_witness_name">
        <span class="fl">ลงลายมือชื่อ/พิมพ์ลายนิ้วมือในแบบ</span>
        <input type="text" class="sevpf-inp" name="sevpf_witness_form" id="sevpf_witness_form">
    </div>
    <div class="fr i1">
        <input type="text" class="sevpf-inp" name="sevpf_witness_detail" id="sevpf_witness_detail">
        <span class="fl">ให้เป็นหลักฐาน และได้ (</span>
        <label class="ck"><input type="checkbox" class="sevpf-cb" name="sevpf_handover_method_check[]" value="นำส่ง">&nbsp;นำส่ง</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="sevpf-cb" name="sevpf_handover_method_check[]" value="ส่งมอบ">&nbsp;ส่งมอบ</label>
        <span class="fl">)</span>
    </div>
    <div class="fr i1">
        <span class="fl">วัตถุพยานตามข้อ</span>
        <input type="text" class="sevpf-inp" name="sevpf_handover_item_ref" id="sevpf_handover_item_ref" style="max-width: 120px;" value="3.2" readonly>
        <span class="fl">ให้</span>
        <input type="text" class="sevpf-inp" name="sevpf_handover_to" id="sevpf_handover_to">
    </div>
    <div class="fr i1">
        <input type="text" class="sevpf-inp" name="sevpf_handover_purpose" id="sevpf_handover_purpose">
        <span class="fl">เพื่อดำเนินการต่อไป</span>
    </div>

    <!-- ลงชื่อ -->
    <div class="signature-block">
        <div class="sevpf-signer-row" style="display:flex; align-items:baseline; justify-content:center; gap:4px;">
            <span>ลงชื่อ</span>
            <input type="text" class="sevpf-inp" name="sevpf_signer_name" id="sevpf_signer_name" style="width:240px; text-align:center; display:inline-block;" readonly>
            <span>ผู้รายงาน</span>
        </div>
        <div>(<input type="text" class="sevpf-inp" name="sevpf_signer_fullname" id="sevpf_signer_fullname" style="width:240px; text-align:center; display:inline-block;" value="<?= htmlspecialchars($sevpf_creator_fullname) ?>" readonly>)</div>
        <div class="sevpf-signer-row">
            <span>(ตำแหน่ง)</span>
            <input type="text" class="sevpf-inp" name="sevpf_signer_position" id="sevpf_signer_position" style="width:240px; text-align:center; display:inline-block;" value="<?= htmlspecialchars($sevpf_creator_position) ?>" readonly>
        </div>
    </div>

    <!-- Footer Page 2 -->
    <div class="form-footer">
        <div class="form-footer-left sub-heading-bottom"></div>
        <div class="form-footer-right sub-heading-bottom">
            บัญชีแนบท้ายคำสั่ง สพฐ.ตร.<br>
            ที่ ๘๔๘/๒๕๖๑
        </div>
    </div>
</div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_ev7_pdf" onclick="prepareDataForSubmissionSceneEvidence()">
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
(function() {
    // ===== Evidence items (dynamic) =====
    var sevpfItemIdx = 1;
    var sevpfLabUnitOpts = '<option value="">-- การตรวจพิสูจน์ --</option>' +
        '<option value="bio_dna">ตรวจชีววิทยา</option>' +
        '<option value="chemical">ตรวจทางเคมีฟิสิกส์</option>' +
        '<option value="fingerprint">ตรวจลายนิ้วมือแฝง</option>' +
        '<option value="drug">ตรวจยาเสพติด</option>' +
        '<option value="gun">ตรวจอาวุธปืนฯ</option>' +
        '<option value="document">ตรวจเอกสาร</option>' +
        '<option value="digital">ตรวจพิสูจน์ดิจิทัล</option><option value="computer">ตรวจอาชญากรรมคอมพิวเตอร์</option>';

    window.sevpfAddEvidenceItem = function() {
        sevpfItemIdx++;
        var container = document.getElementById('sevpf_evidence_items_container');
        var row = document.createElement('div');
        row.className = 'fr sevpf-evidence-item-row';
        row.style.cssText = 'flex-wrap:wrap; gap:2px;';
        row.innerHTML = '<span class="fl sevpf-si-no" style="min-width:30px;">1.' + sevpfItemIdx + '</span>' +
            '<input type="text" class="sevpf-inp" name="sevpf_evidence_item[]" style="flex:1; min-width:120px;">' +
            '<select class="sevpf-select" name="sevpf_lab_unit[]" style="max-width:180px; font-size:0.75rem;">' + sevpfLabUnitOpts + '</select>' +
            ' <button type="button" class="sevpf-del-btn" onclick="this.closest(\'.sevpf-evidence-item-row\').remove(); sevpfRenumberEvidenceItems();">✕</button>';
        container.appendChild(row);
    };
    window.sevpfRenumberEvidenceItems = function() {
        var rows = document.querySelectorAll('#sevpf_evidence_items_container .sevpf-evidence-item-row');
        rows.forEach(function(r, i) {
            var no = r.querySelector('.sevpf-si-no');
            if (no) no.textContent = '1.' + (i + 1);
        });
        sevpfItemIdx = rows.length;
    };

    // ===== Exhibit descriptions (dynamic) =====
    var sevpfExhibitIdx = 1;
    window.sevpfAddExhibitDesc = function() {
        sevpfExhibitIdx++;
        var n = sevpfExhibitIdx;
        var container = document.getElementById('sevpf_exhibit_desc_container');
        var row = document.createElement('div');
        row.className = 'fr sevpf-exhibit-row';
        row.innerHTML = '<span class="fl sevpf-si-no" style="min-width:50px;">3.1.' + n + '</span>' +
            '<span class="fl">ของกลางรายการที่ ' + n + ' เป็น</span>' +
            '<input type="text" class="sevpf-inp" name="sevpf_exhibit_desc[]">' +
            ' <button type="button" class="sevpf-del-btn" onclick="this.closest(\'.sevpf-exhibit-row\').remove(); sevpfRenumberExhibits();">✕</button>';
        container.appendChild(row);
    };
    window.sevpfRenumberExhibits = function() {
        var rows = document.querySelectorAll('#sevpf_exhibit_desc_container .sevpf-exhibit-row');
        rows.forEach(function(r, i) {
            var n = i + 1;
            var no = r.querySelector('.sevpf-si-no');
            if (no) no.textContent = '3.1.' + n;
            var spans = r.querySelectorAll('span.fl');
            if (spans.length > 1) spans[1].textContent = 'ของกลางรายการที่ ' + n + ' เป็น';
        });
        sevpfExhibitIdx = rows.length;
    };

    // ===== Collect details (dynamic) =====
    var sevpfCollectIdx = 1;
    window.sevpfAddCollectDetail = function() {
        sevpfCollectIdx++;
        var container = document.getElementById('sevpf_collect_detail_container');
        var row = document.createElement('div');
        row.className = 'fr sevpf-collect-row';
        row.innerHTML = '<span class="fl sevpf-si-no" style="min-width:60px;">3.2.1.' + sevpfCollectIdx + '</span>' +
            '<input type="text" class="sevpf-inp" name="sevpf_collect_detail[]">' +
            ' <button type="button" class="sevpf-del-btn" onclick="this.closest(\'.sevpf-collect-row\').remove(); sevpfRenumberCollectDetails();">✕</button>';
        container.appendChild(row);
    };
    window.sevpfRenumberCollectDetails = function() {
        var rows = document.querySelectorAll('#sevpf_collect_detail_container .sevpf-collect-row');
        rows.forEach(function(r, i) {
            var no = r.querySelector('.sevpf-si-no');
            if (no) no.textContent = '3.2.1.' + (i + 1);
        });
        sevpfCollectIdx = rows.length;
    };

    // ===== Report No sync =====
    window.sevpfSyncReportNo = function() {
        var docNoVal = document.getElementById('sevpf_doc_no') ? document.getElementById('sevpf_doc_no').value : '';
        var reportNoVal = document.getElementById('sevpf_report_no') ? document.getElementById('sevpf_report_no').value : '';
        var thDoc = (window.toThaiDocNo ? window.toThaiDocNo(docNoVal) : docNoVal);
        var yearVal = '';
        if (reportNoVal) {
            var reportParts = reportNoVal.toString().trim().split('/');
            yearVal = (reportParts[1] || '').toString().trim();
            if (!yearVal && reportParts.length === 1) {
                var match = reportNoVal.toString().trim().match(/(\d{2,4})$/);
                yearVal = match ? match[1] : '';
            }
            if (yearVal.length > 2) yearVal = yearVal.slice(-2);
        }
        var refInput = document.getElementById('sevpf_report_ref');
        var yearInput = document.getElementById('sevpf_report_year');
        if (refInput && !refInput.value) refInput.value = thDoc;
        if (yearInput && yearVal) yearInput.value = yearVal;
    };

    window.sevpfSyncCaseNoFromDocNo = function() {
        // Sync case_no from doc_no if empty
        var docNo = document.getElementById('sevpf_doc_no');
        var caseNo = document.getElementById('sevpf_case_no');
        if (docNo && caseNo && !caseNo.value && docNo.value) {
            caseNo.value = docNo.value;
        }
    };

    // ===== Page numbers (auto) =====
    window.sevpfUpdatePageNumbers = function() {
        var pages = document.querySelectorAll('#sceneEvidenceFormPdfModal .sevpf-page');
        var total = pages.length;
        document.querySelectorAll('#sceneEvidenceFormPdfModal .sevpf-total-pages').forEach(function(el) {
            el.textContent = total;
        });
        var pageNums = document.querySelectorAll('#sceneEvidenceFormPdfModal .sevpf-page-num');
        pageNums.forEach(function(el, idx) {
            el.textContent = idx + 1;
        });
    };

    // ===== Reset PDF form =====
    window.resetSceneEvidencePdfForm = function() {
        var form = document.getElementById('sceneEvidenceFormPdf');
        if (form) form.reset();

        // Reset dynamic containers to 1 row each
        [['#sevpf_evidence_items_container', '.sevpf-evidence-item-row', 'sevpfRenumberEvidenceItems'],
         ['#sevpf_exhibit_desc_container', '.sevpf-exhibit-row', 'sevpfRenumberExhibits'],
         ['#sevpf_collect_detail_container', '.sevpf-collect-row', 'sevpfRenumberCollectDetails']
        ].forEach(function(config) {
            var container = document.querySelector(config[0]);
            if (!container) return;
            var rows = container.querySelectorAll(config[1]);
            for (var i = rows.length - 1; i > 0; i--) rows[i].remove();
            if (typeof window[config[2]] === 'function') window[config[2]]();
        });
    };

    // ===== Switch Form Functions =====
    window.switchToSceneEvidencePdfForm = function() {
        syncSceneEvidenceFormData('incidentCheckListFormSceneEvidence', 'sceneEvidenceFormPdf');

        var docNo = $('#doc_no_ev7').val() || '';
        var rptNo = $('#report_no_ev7').val() || '';
        $('#sevpf_receiveNoti_id').val($('#receiveNoti_id_ev7').val());
        $('#sevpf_doc_no').val(docNo);
        $('#sevpf_report_no').val(rptNo);
        sevpfSyncReportNo();

        var stdModal = bootstrap.Modal.getInstance(document.getElementById('addCheckListModalSceneEvidence'));
        if (stdModal) stdModal.hide();
        var stdSwitch = document.getElementById('switchToPdfFormEV7');
        if (stdSwitch) stdSwitch.checked = false;
        setTimeout(function() {
            var pdfModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('sceneEvidenceFormPdfModal'));
            var pdfSwitch = document.getElementById('switchToStdFormEV7');
            if (pdfSwitch) pdfSwitch.checked = true;
            pdfModal.show();
        }, 400);
    };

    window.switchToSceneEvidenceStdForm = function() {
        syncSceneEvidenceFormData('sceneEvidenceFormPdf', 'incidentCheckListFormSceneEvidence');

        var docNo = $('#sevpf_doc_no').val() || '';
        var rptNo = $('#sevpf_report_no').val() || '';
        $('#receiveNoti_id_ev7').val($('#sevpf_receiveNoti_id').val());
        $('#doc_no_ev7').val(docNo);
        $('#report_no_ev7').val(rptNo);
        $('#receiveNoti_No_ev7').text(docNo);

        var pdfModal = bootstrap.Modal.getInstance(document.getElementById('sceneEvidenceFormPdfModal'));
        if (pdfModal) pdfModal.hide();
        setTimeout(function() {
            var stdModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('addCheckListModalSceneEvidence'));
            var stdSwitch = document.getElementById('switchToPdfFormEV7');
            if (stdSwitch) stdSwitch.checked = false;
            stdModal.show();
        }, 400);
    };

    // ===== Sync Form Data =====
    window.syncSceneEvidenceFormData = function(fromFormId, toFormId) {
        var fromForm = document.getElementById(fromFormId);
        var toForm = document.getElementById(toFormId);
        if (!fromForm || !toForm) return;

        if (window.__sceneEvidenceSyncing) return;
        window.__sceneEvidenceSyncing = true;

        try {
            var toStdForm = (toFormId === 'incidentCheckListFormSceneEvidence');

            var pickValue = function(selectors) {
                for (var i = 0; i < selectors.length; i++) {
                    var el = document.querySelector(selectors[i]);
                    if (!el) continue;
                    var val = (el.value || '').toString();
                    if (val !== '') return val;
                }
                return '';
            };
            var setValue = function(selector, value) {
                document.querySelectorAll(selector).forEach(function(el) { el.value = value == null ? '' : value; });
            };
            var collectChecked = function(name, scope) {
                return Array.prototype.slice.call((scope || document).querySelectorAll('[name="' + name + '"]:checked')).map(function(el) { return el.value; });
            };
            var setCheckedValues = function(name, values, scope) {
                var valueList = Array.isArray(values) ? values : [];
                (scope || document).querySelectorAll('[name="' + name + '"]').forEach(function(el) { el.checked = valueList.indexOf(el.value) !== -1; });
            };
            var syncSimpleField = function(stdSelector, pdfSelector) {
                var sourceSelector = fromFormId === 'incidentCheckListFormSceneEvidence' ? stdSelector : pdfSelector;
                var targetSelector = toStdForm ? stdSelector : pdfSelector;
                setValue(targetSelector, pickValue([sourceSelector]));
            };
            var ensureDynamicRows = function(config, desiredCount) {
                var count = Math.max(1, desiredCount || 1);
                var container = document.querySelector(config.containerSelector);
                if (!container) return;
                while (container.querySelectorAll(config.rowSelector).length < count && typeof window[config.addFn] === 'function') {
                    window[config.addFn]();
                }
                while (container.querySelectorAll(config.rowSelector).length > count) {
                    var rows = container.querySelectorAll(config.rowSelector);
                    rows[rows.length - 1].remove();
                }
                if (typeof window[config.renumberFn] === 'function') window[config.renumberFn]();
            };
            var syncDynamicValues = function(sourceSelector, targetSelector, targetConfig) {
                var values = Array.prototype.slice.call(document.querySelectorAll(sourceSelector)).map(function(el) { return el.value || ''; });
                ensureDynamicRows(targetConfig, values.length || 1);
                var targetInputs = document.querySelectorAll(targetSelector);
                targetInputs.forEach(function(el, idx) { el.value = values[idx] || ''; });
            };

            // Sync simple fields
            syncSimpleField('#receiveNoti_id_ev7', '#sevpf_receiveNoti_id');
            syncSimpleField('#doc_no_ev7', '#sevpf_doc_no');
            syncSimpleField('#report_no_ev7', '#sevpf_report_no');
            syncSimpleField('#ev7_receive_date', '[name="sevpf_receive_date"]');
            syncSimpleField('#ev7_receive_time', '[name="sevpf_receive_time"]');
            syncSimpleField('#ev7_unit_name', '#sevpf_unit_name');
            syncSimpleField('#ev7_police_station', '[name="sevpf_police_station"]');
            syncSimpleField('#ev7_document_no', '#sevpf_document_no');
            syncSimpleField('#ev7_document_date', '#sevpf_document_date');
            syncSimpleField('#ev7_case_no', '[name="sevpf_case_no"]');
            syncSimpleField('#ev7_incident_location', '#sevpf_incident_location');
            syncSimpleField('#ev7_incident_date', '#sevpf_incident_date');
            syncSimpleField('#ev7_incident_time', '#sevpf_incident_time');
            syncSimpleField('#ev7_investigator_name', '#sevpf_investigator_name');
            syncSimpleField('#ev7_send_request', '[name="sevpf_send_request"]');
            syncSimpleField('#ev7_purpose_detail', '#sevpf_purpose_detail');
            syncSimpleField('#ev7_inspect_location', '#sevpf_inspect_location');
            syncSimpleField('#ev7_inspect_date', '#sevpf_inspect_date');
            syncSimpleField('#ev7_inspect_time', '#sevpf_inspect_time');
            syncSimpleField('#ev7_collect_sheet_count', '#sevpf_collect_sheet_count');
            syncSimpleField('#ev7_witness_name', '#sevpf_witness_name');
            syncSimpleField('#ev7_witness_form', '#sevpf_witness_form');
            syncSimpleField('#ev7_witness_detail', '#sevpf_witness_detail');
            syncSimpleField('#ev7_handover_item_ref', '#sevpf_handover_item_ref');
            syncSimpleField('#ev7_handover_to', '#sevpf_handover_to');
            syncSimpleField('#ev7_handover_purpose', '#sevpf_handover_purpose');

            // Checkboxes
            if (fromFormId === 'incidentCheckListFormSceneEvidence') {
                // Notify method: standard → PDF (map values)
                var stdNotify = collectChecked('ev7_notify_method[]', fromForm);
                var pdfNotify = stdNotify.map(function(v) {
                    if (v === 'ตามหนังสือ') return 'ตามหนังสือ';
                    return v;
                });
                setCheckedValues('sevpf_notify_method[]', pdfNotify, toForm);
                setCheckedValues('sevpf_purpose[]', collectChecked('ev7_purpose[]', fromForm), toForm);
                setCheckedValues('sevpf_collect_type[]', collectChecked('ev7_collect_type[]', fromForm), toForm);
                // Handover method
                var hm = pickValue(['#ev7_handover_method']);
                setCheckedValues('sevpf_handover_method_check[]', hm ? [hm] : [], toForm);
            } else {
                var pdfNotify2 = collectChecked('sevpf_notify_method[]', fromForm);
                setCheckedValues('ev7_notify_method[]', pdfNotify2, toForm);
                setCheckedValues('ev7_purpose[]', collectChecked('sevpf_purpose[]', fromForm), toForm);
                setCheckedValues('ev7_collect_type[]', collectChecked('sevpf_collect_type[]', fromForm), toForm);
                var hmChecks = collectChecked('sevpf_handover_method_check[]', fromForm);
                setValue('#ev7_handover_method', hmChecks[0] || '');
            }

            // Dynamic arrays
            syncDynamicValues(
                fromFormId === 'incidentCheckListFormSceneEvidence' ? '[name="ev7_evidence_item[]"]' : '[name="sevpf_evidence_item[]"]',
                toStdForm ? '[name="ev7_evidence_item[]"]' : '[name="sevpf_evidence_item[]"]',
                toStdForm
                    ? { containerSelector: '#ev7_evidence_items_container', rowSelector: '.ev7-evidence-item-row', addFn: 'addEvidenceItemEV7', renumberFn: 'renumberEV7EvidenceItems' }
                    : { containerSelector: '#sevpf_evidence_items_container', rowSelector: '.sevpf-evidence-item-row', addFn: 'sevpfAddEvidenceItem', renumberFn: 'sevpfRenumberEvidenceItems' }
            );
            syncDynamicValues(
                fromFormId === 'incidentCheckListFormSceneEvidence' ? '[name="ev7_exhibit_desc[]"]' : '[name="sevpf_exhibit_desc[]"]',
                toStdForm ? '[name="ev7_exhibit_desc[]"]' : '[name="sevpf_exhibit_desc[]"]',
                toStdForm
                    ? { containerSelector: '#ev7_exhibit_desc_container', rowSelector: '.ev7-exhibit-desc-row', addFn: 'addExhibitDescEV7', renumberFn: 'renumberEV7ExhibitRows' }
                    : { containerSelector: '#sevpf_exhibit_desc_container', rowSelector: '.sevpf-exhibit-row', addFn: 'sevpfAddExhibitDesc', renumberFn: 'sevpfRenumberExhibits' }
            );
            syncDynamicValues(
                fromFormId === 'incidentCheckListFormSceneEvidence' ? '[name="ev7_collect_detail[]"]' : '[name="sevpf_collect_detail[]"]',
                toStdForm ? '[name="ev7_collect_detail[]"]' : '[name="sevpf_collect_detail[]"]',
                toStdForm
                    ? { containerSelector: '#ev7_collect_detail_container', rowSelector: '.ev7-collect-detail-row', addFn: 'addCollectDetailEV7', renumberFn: 'renumberEV7CollectDetailRows' }
                    : { containerSelector: '#sevpf_collect_detail_container', rowSelector: '.sevpf-collect-row', addFn: 'sevpfAddCollectDetail', renumberFn: 'sevpfRenumberCollectDetails' }
            );

            // Lab units
            (function() {
                var srcSel = fromFormId === 'incidentCheckListFormSceneEvidence' ? '[name="ev7_lab_unit[]"]' : '[name="sevpf_lab_unit[]"]';
                var tgtSel = toStdForm ? '[name="ev7_lab_unit[]"]' : '[name="sevpf_lab_unit[]"]';
                var srcEls = document.querySelectorAll(srcSel);
                var tgtEls = document.querySelectorAll(tgtSel);
                srcEls.forEach(function(el, idx) { if (tgtEls[idx]) tgtEls[idx].value = el.value || ''; });
            })();

            // Other evidence text ↔ array
            if (fromFormId === 'incidentCheckListFormSceneEvidence') {
                setValue('#sevpf_other_evidence_text', Array.prototype.slice.call(document.querySelectorAll('[name="ev7_other_evidence[]"]')).map(function(el) {
                    return (el.value || '').trim();
                }).filter(Boolean).join(', '));
            } else {
                var parsedOther = pickValue(['#sevpf_other_evidence_text']).split(/\n|,/).map(function(t) { return t.trim(); }).filter(Boolean);
                ensureDynamicRows({
                    containerSelector: '#ev7_other_evidence_container',
                    rowSelector: '.ev7-other-evidence-row',
                    addFn: 'addOtherEvidenceEV7',
                    renumberFn: null
                }, parsedOther.length || 1);
                document.querySelectorAll('[name="ev7_other_evidence[]"]').forEach(function(el, idx) { el.value = parsedOther[idx] || ''; });
            }

            if (typeof window.sevpfSyncReportNo === 'function') window.sevpfSyncReportNo();
        } finally {
            window.__sceneEvidenceSyncing = false;
        }
    };

    // ===== Init on modal show =====
    document.getElementById('sceneEvidenceFormPdfModal').addEventListener('shown.bs.modal', function() {
        var pdfSwitch = document.getElementById('switchToStdFormEV7');
        if (pdfSwitch) pdfSwitch.checked = true;
        sevpfSyncReportNo();
    });

    var stdModalEl = document.getElementById('addCheckListModalSceneEvidence');
    if (stdModalEl) {
        stdModalEl.addEventListener('shown.bs.modal', function() {
            var stdSwitch = document.getElementById('switchToPdfFormEV7');
            if (stdSwitch) stdSwitch.checked = false;
        });
    }

    $(document).on('click', '#switchToPdfFormEV7', function(e) {
        e.preventDefault();
        this.checked = false;
        if (typeof window.switchToSceneEvidencePdfForm === 'function') {
            window.switchToSceneEvidencePdfForm();
        }
    });

    $(document).on('click', '#switchToStdFormEV7', function(e) {
        e.preventDefault();
        this.checked = true;
        if (typeof window.switchToSceneEvidenceStdForm === 'function') {
            window.switchToSceneEvidenceStdForm();
        }
    });

    // ===== No-op stubs for backward compat =====
    window.sevpfClearCanvas = function() {};
    window.sevpfRenderPhotosFromStore = function() {};
    window.sevpfAddLabUnit = function() {};
    window.sevpfRefreshAutoWrapGroups = function() {};
    window.sevpfAddInspector = function() {};
    window.sevpfRenumberInspectors = function() {};
    window.sevpfUpdatePhotoAmount = function() {};
})();
</script>
