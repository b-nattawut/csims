<?php
/**
 * Modal: ร่างรายงานตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง) — ฟอร์มเสมือน PDF
 * A4-paper style editable form — uses same rlf_ field names as modal_report_fingerprint.php
 * Modal ID: modalReportFingerprintPdf   Form ID: formReportFingerprintPdf
 * Prefix: rlfpdf_
 */

$rlfpdfInspectorOpts = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
if (isset($pdo)) {
    $qryI = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname,
             IFNULL(t3.position_name, '-') AS position_name
             FROM user_profile t1
             LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
             LEFT JOIN user_position t3 ON t1.position_id = t3.position_id
             ORDER BY t1.user_id DESC";
    $stI = $pdo->query($qryI);
    while ($rI = $stI->fetch(PDO::FETCH_ASSOC)) {
        $rlfpdfInspectorOpts .= '<option value="' . $rI['user_id'] . '" data-position="' . htmlspecialchars($rI['position_name']) . '">' . htmlspecialchars($rI['fullname']) . '</option>';
    }
}

$rlfpdfStationOpts = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qryS = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stS = $pdo->query($qryS);
    while ($rS = $stS->fetch(PDO::FETCH_ASSOC)) {
        $rlfpdfStationOpts .= '<option value="' . htmlspecialchars($rS['station_name']) . '">' . htmlspecialchars($rS['station_name']) . '</option>';
    }
}
?>

<style>
/* ========== Fingerprint Report PDF Form — scoped to #modalReportFingerprintPdf ========== */
#modalReportFingerprintPdf .fppf-body { background: #bbb; }
#modalReportFingerprintPdf .fppf-page {
    width: 210mm; min-height: 297mm; margin: 16px auto; padding: 12mm 15mm 10mm 15mm;
    background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.25); position: relative;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; color: #000;
    display: flex; flex-direction: column; page-break-after: always;
    overflow-x: hidden; box-sizing: border-box;
}
#modalReportFingerprintPdf .fppf-page:last-child { page-break-after: auto; }

#modalReportFingerprintPdf .fr  { display: flex; flex-wrap: wrap; align-items: baseline; line-height: 1.9; width: 100%; box-sizing: border-box; }
#modalReportFingerprintPdf .fl  { font-size: 14px; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportFingerprintPdf .fl-b{ font-size: 14px; font-weight: 700; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }
#modalReportFingerprintPdf .i1  { padding-left: 20px; }
#modalReportFingerprintPdf .i2  { padding-left: 40px; }
#modalReportFingerprintPdf .sec-heading  { font-weight: 700; font-size: 14px; margin-top: 2px; margin-bottom: 1px; }
#modalReportFingerprintPdf .sub-heading  { font-weight: 600; font-size: 14px; margin-left: 20px; }
#modalReportFingerprintPdf .form-title   { text-decoration: underline; text-align: center; font-size: 15px; font-weight: 700; margin: 6px auto 4px auto; }
#modalReportFingerprintPdf .page-header  { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0; }
#modalReportFingerprintPdf .form-footer  { margin-top: auto; padding-top: 6px; font-size: 10px; color: #333; display: flex; justify-content: space-between; }
#modalReportFingerprintPdf .form-footer-right { text-align: right; white-space: nowrap; line-height: 1.4; }
#modalReportFingerprintPdf .signature-block { margin-top: 30px; text-align: center; padding-left: 50%; line-height: 2; }

/* Editable input — dotted bottom */
#modalReportFingerprintPdf .fppf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; min-width: 0; flex: 1 1 auto; margin: 0 4px; color: #000; box-sizing: border-box; max-width: 100%;
}
#modalReportFingerprintPdf .fppf-inp:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }
#modalReportFingerprintPdf .fppf-inp-s { width: 60px; max-width: 80px; text-align: center; flex: 0 0 auto; }
#modalReportFingerprintPdf .fppf-inp-m { flex: 0 1 140px; min-width: 80px; text-align: center; }
#modalReportFingerprintPdf .fppf-inp-l { flex: 1; min-width: 160px; }
#modalReportFingerprintPdf .fppf-inp-s,
#modalReportFingerprintPdf .fppf-inp-m,
#modalReportFingerprintPdf .fppf-inp-l {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; margin: 0 4px; color: #000;
}
#modalReportFingerprintPdf .fppf-inp-s:focus,
#modalReportFingerprintPdf .fppf-inp-m:focus,
#modalReportFingerprintPdf .fppf-inp-l:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Textarea */
#modalReportFingerprintPdf .fppf-ta {
    width: 100%; border: none; border-bottom: 1px dotted #888;
    background: transparent; outline: none; resize: vertical;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.8;
    padding: 0 4px; min-height: 24px; margin: 0 4px 2px 20px; color: #000;
    background-image: repeating-linear-gradient(transparent, transparent 27px, #ddd 27px, #ddd 28px);
}
#modalReportFingerprintPdf .fppf-ta:focus { border-bottom-color: #0d6efd; }

/* Checkbox */
#modalReportFingerprintPdf .ck { display: inline-flex; align-items: baseline; margin-right: 8px; font-size: 14px; white-space: nowrap; }
#modalReportFingerprintPdf .fppf-cb {
    -webkit-appearance: none; appearance: none;
    width: 14px; height: 14px; margin-right: 3px; cursor: pointer; position: relative; top: 2px;
    border: 1.5px solid #000; border-radius: 0; background: #fff;
}
#modalReportFingerprintPdf .fppf-cb:checked::after {
    content: '✓'; position: absolute; top: -3px; left: 1px;
    font-size: 13px; font-weight: bold; color: #000; line-height: 1;
}

/* Buttons */
#modalReportFingerprintPdf .fppf-add-btn {
    font-size: 12px; padding: 1px 8px; border: 1px dashed #0d6efd; color: #0d6efd;
    background: transparent; border-radius: 4px; cursor: pointer; margin-left: 20px; margin-top: 2px;
}
#modalReportFingerprintPdf .fppf-add-btn:hover { background: rgba(13,110,253,.08); }
#modalReportFingerprintPdf .fppf-del-btn {
    font-size: 11px; padding: 0 5px; border: none; color: #dc3545; background: transparent; cursor: pointer; margin-left: 4px;
    flex: 0 0 auto; white-space: nowrap;
}

/* Select */
#modalReportFingerprintPdf .fppf-select {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 26px; flex: 1 1 auto; margin: 0 4px; color: #000;
    cursor: pointer; min-width: 0; box-sizing: border-box; max-width: 100%;
}
#modalReportFingerprintPdf .fppf-select:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Inspector row */
#modalReportFingerprintPdf .rlfpdf-inspector-row .fppf-select { flex: 1 1 auto; min-width: 80px; }
#modalReportFingerprintPdf .rlfpdf-inspector-row .fppf-inp { flex: 0 1 120px; min-width: 60px; }

/* Select2 compact */
#modalReportFingerprintPdf .rlfpdf-inspector-row .select2-container { flex: 1 1 auto; min-width: 80px; max-width: 250px; margin: 0 4px; }
#modalReportFingerprintPdf .rlfpdf-inspector-row .select2-container--bootstrap-5 .select2-selection {
    min-height: 26px !important; height: 26px !important; padding: 0 4px !important;
    border: none !important; border-bottom: 1px dotted #888 !important; border-radius: 0 !important;
    background: transparent !important; font-size: 14px !important;
}
#modalReportFingerprintPdf .rlfpdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    padding: 0 !important; line-height: 26px !important; font-size: 14px !important; color: #000 !important;
}
#modalReportFingerprintPdf .rlfpdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
    height: 26px !important;
}

/* Evidence card */
#modalReportFingerprintPdf .rlfpdf-ev-card {
    padding: 4px 0; border-bottom: 1px dashed #ccc; margin-bottom: 2px;
}
#modalReportFingerprintPdf .rlfpdf-ev-card:last-child { border-bottom: none; }

/* Method row — wrap so detail goes to next line if needed */
#modalReportFingerprintPdf .rlfpdf-method-row {
    display: flex; align-items: baseline; gap: 4px; margin-bottom: 4px; width: 100%; flex-wrap: wrap;
}
#modalReportFingerprintPdf .rlfpdf-method-row .fppf-select {
    flex: 0 0 auto; min-width: 120px;
}
#modalReportFingerprintPdf .rlfpdf-method-row .fppf-del-btn {
    flex: 0 0 auto;
}
#modalReportFingerprintPdf .rlfpdf-method-row .fppf-method-detail {
    flex: 1 1 100%; min-width: 200px;
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; margin: 0 4px; color: #000; resize: none;
    overflow: hidden; line-height: 1.7; height: auto; min-height: 26px;
}
#modalReportFingerprintPdf .rlfpdf-method-row .fppf-method-detail:focus {
    border-bottom-color: #0d6efd; background: rgba(13,110,253,.04);
}

/* Found row */
#modalReportFingerprintPdf .rlfpdf-found-row {
    display: flex; align-items: baseline; gap: 4px; margin-bottom: 2px;
}
</style>

<div class="modal fade" id="modalReportFingerprintPdf" aria-labelledby="modalReportFingerprintPdfLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 240mm;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportFingerprintPdfLabel">
                    รายงานการตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body fppf-body p-0" style="max-height: 80vh; overflow-y: auto;">
                <form id="formReportFingerprintPdf" novalidate>
                    <input type="hidden" id="rlfpdf_incident_id" name="incident_id">

                    <!-- Switch back to standard form -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="rlf_editInfoPdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="rlf_editCountPdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchFpReportToStd" checked
                                   style="width: 3rem; height: 1.5rem; cursor: pointer;">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchFpReportToStd" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ==================== PAGE 1 ==================== -->
<div class="fppf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจเก็บวัตถุพยานที่</u>&nbsp;
            <input type="text" class="fppf-inp fppf-inp-s" id="rlfpdf_report_no_display" name="rlf_report_no_display_pdf" readonly style="display:inline-block; width:auto; min-width:80px; max-width:160px;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="fppf-inp fppf-inp-s" name="rlf_report_year_pdf" id="rlfpdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">1/2</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="fppf-inp" name="rlf_agency_name" id="rlfpdf_agency_name">
    </div>

    <div class="form-title">รายงานการตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)</div>

    <!-- 1. การรับแจ้ง -->
    <div class="sec-heading">1. <u>การรับแจ้ง</u></div>
    <div class="fr i1">
        <span class="fl">วันที่</span>
        <input type="date" class="fppf-inp fppf-inp-m" name="rlf_receive_date" id="rlfpdf_receive_date">
        <span class="fl">เวลา</span>
        <input type="time" class="fppf-inp fppf-inp-m" name="rlf_receive_time" id="rlfpdf_receive_time">
        <span class="fl">น.</span>
        <span class="fl" style="margin-left:10px;">ปจว.ข้อที่</span>
        <input type="text" class="fppf-inp" name="rlf_case_no" id="rlfpdf_case_no" style="min-width:60px;">
    </div>
    <div class="fr i1">
        <span class="fl">การรับแจ้งทางหนังสือ สน./สภ.</span>
        <select class="fppf-select" name="rlf_police_station" id="rlfpdf_police_station" style="max-width:200px;"><?= $rlfpdfStationOpts ?></select>
        <span class="fl" style="margin-left:6px;">ที่</span>
        <input type="text" class="fppf-inp" name="rlf_letter_no" id="rlfpdf_letter_no">
        <span class="fl" style="margin-left:6px;">ลง</span>
        <input type="date" class="fppf-inp" name="rlf_letter_date" id="rlfpdf_letter_date" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="fr i1">
        <span class="fl">ผู้ส่งของกลาง</span>
        <select class="fppf-select rlfpdf-sender-select" name="rlf_evidence_sender" id="rlfpdf_evidence_sender" style="max-width:250px;">
            <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งของกลาง --', $rlfpdfInspectorOpts) ?>
        </select>
        <span class="fl" style="margin-left:6px;">ตำแหน่ง</span>
        <input type="text" class="fppf-inp" name="rlf_evidence_sender_position" id="rlfpdf_evidence_sender_position" readonly>
        <span class="fl" style="margin-left:6px;">โทร</span>
        <input type="tel" class="fppf-inp fppf-inp-m" name="rlf_evidence_sender_phone" id="rlfpdf_evidence_sender_phone" maxlength="12">
    </div>
    <div class="fr i1">
        <span class="fl">รับวัตถุพยานตามหนังสือ</span>
        <input type="text" class="fppf-inp" name="rlf_evidence_letter_no" id="rlfpdf_evidence_letter_no">
        <span class="fl">ที่</span>
        <input type="text" class="fppf-inp" name="rlf_evidence_doc_no" id="rlfpdf_evidence_doc_no">
        <span class="fl">ลง</span>
        <input type="date" class="fppf-inp" name="rlf_evidence_doc_date" id="rlfpdf_evidence_doc_date" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="fr i1">
        <span class="fl">จุดประสงค์ในการตรวจพิสูจน์</span>
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_purpose[]" value="เพื่อตรวจเก็บรอยลายนิ้วมือแฝง" id="rlfpdf_purpose_fingerprint">&nbsp;เพื่อตรวจเก็บรอยลายนิ้วมือแฝง</label>
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_purpose[]" value="อื่นๆ" id="rlfpdf_purpose_other">&nbsp;อื่นๆ</label>
        <input type="text" class="fppf-inp" name="rlf_purpose_other_text" id="rlfpdf_purpose_other_text" style="min-width:80px;">
    </div>

    <!-- 2. วันเวลาที่เกิดเหตุ/ทราบเหตุ -->
    <div class="sec-heading">2. <u>วันเวลาที่เกิดเหตุ/ทราบเหตุ</u></div>
    <div class="fr i1">
        <span class="fl-b">วันเวลาที่เกิดเหตุ</span>
        <span class="fl">วันที่</span>
        <input type="date" class="fppf-inp fppf-inp-m" name="rlf_incident_date" id="rlfpdf_incident_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="fppf-inp fppf-inp-m" name="rlf_incident_time" id="rlfpdf_incident_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl-b">วันเวลาที่ทราบเหตุ</span>
        <span class="fl">วันที่</span>
        <input type="date" class="fppf-inp fppf-inp-m" name="rlf_known_date" id="rlfpdf_known_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="fppf-inp fppf-inp-m" name="rlf_known_time" id="rlfpdf_known_time">
        <span class="fl">น.</span>
    </div>

    <!-- 3. วันเวลาที่ตรวจเก็บ -->
    <div class="sec-heading">3. <u>วันเวลาที่ตรวจเก็บ</u></div>
    <div class="fr i1">
        <span class="fl">วันที่</span>
        <input type="date" class="fppf-inp fppf-inp-m" name="rlf_collect_date" id="rlfpdf_collect_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="fppf-inp fppf-inp-m" name="rlf_collect_time" id="rlfpdf_collect_time">
        <span class="fl">น.</span>
    </div>

    <!-- 4. ผู้ตรวจพิสูจน์ -->
    <div class="sec-heading">4. <u>ผู้ตรวจพิสูจน์</u></div>
    <div id="rlfpdf_inspector_container">
        <div class="fr i1 rlfpdf-inspector-row">
            <span class="fl rlfpdf-inspector-num">4.1.</span>
            <select class="fppf-select rp-inspector-select" name="rlf_inspector_name[]">
                <?= $rlfpdfInspectorOpts ?>
            </select>
            <span class="fl" style="margin-left:6px;">ตำแหน่ง</span>
            <input type="text" class="fppf-inp rp-inspector-position" name="rlf_inspector_position[]" placeholder="ตำแหน่ง" readonly>
            <button type="button" class="fppf-del-btn" title="ลบ" onclick="this.closest('.rlfpdf-inspector-row').remove(); rlfpdfRenumberInspectors();">✕</button>
        </div>
    </div>
    <button type="button" class="fppf-add-btn" onclick="rlfpdfAddInspectorRow();">+ เพิ่มผู้ตรวจ</button>

    <!-- 5. ลักษณะการหีบห่อวัตถุพยาน -->
    <div class="sec-heading">5. <u>ลักษณะการหีบห่อวัตถุพยาน</u></div>
    <div class="fr i1">
        <span class="fl">ลักษณะการบรรจุ</span>
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_package_type[]" value="บรรจุในซองวัตถุพยาน" id="rlfpdf_pkg_envelope">&nbsp;บรรจุในซองวัตถุพยาน</label>
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_package_type[]" value="พลาสติก" id="rlfpdf_pkg_plastic">&nbsp;พลาสติก</label>
    </div>
    <div class="fr i1">
        <span class="fl">สภาพการปิดผนึก</span>
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_seal_condition[]" value="มีการปิดผนึกพร้อมลงลายมือชื่อกำกับ" id="rlfpdf_seal_signed">&nbsp;การปิดผนึกพร้อมลงลายมือชื่อกำกับ</label>
    </div>
    <div class="fr i1">
        <span class="fl" style="visibility:hidden;">สภาพการปิดผนึก</span>
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_seal_condition[]" value="เขียนรายละเอียดหน้าซองครบถ้วน" id="rlfpdf_seal_detail">&nbsp;เขียนรายละเอียดหน้าซองวัตถุพยาน</label>
    </div>
    <div class="fr i1">
        <span class="fl">ผู้เก็บรักษา</span>
        <label class="ck"><input type="radio" class="fppf-cb" name="rlf_collector_type" value="เก็บโดยเจ้าหน้าที่พิสูจน์หลักฐาน" id="rlfpdf_collector_forensic">&nbsp;เจ้าหน้าที่พิสูจน์หลักฐาน</label>
        <label class="ck"><input type="radio" class="fppf-cb" name="rlf_collector_type" value="เก็บโดยพนักงานสอบสวน" id="rlfpdf_collector_investigator">&nbsp;พนักงานสอบสวน</label>
        <label class="ck"><input type="radio" class="fppf-cb" name="rlf_collector_type" value="เก็บโดย อื่นๆ" id="rlfpdf_collector_other">&nbsp;อื่นๆ</label>
        <input type="text" class="fppf-inp" name="rlf_collector_other_text" id="rlfpdf_collector_other_text" style="max-width:150px;">
    </div>
    <div class="fr i1">
        <span class="fl">เก็บเมื่อ</span>
        <input type="date" class="fppf-inp fppf-inp-m" name="rlf_storage_date" id="rlfpdf_storage_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="fppf-inp fppf-inp-m" name="rlf_storage_time" id="rlfpdf_storage_time">
        <span class="fl" style="margin-left:10px;">ระยะเวลา</span>
        <input type="number" class="fppf-inp fppf-inp-s" name="rlf_duration_year" id="rlfpdf_duration_year" min="0">
        <span class="fl">ปี</span>
        <input type="number" class="fppf-inp fppf-inp-s" name="rlf_duration_month" id="rlfpdf_duration_month" min="0" max="12">
        <span class="fl">เดือน</span>
        <input type="number" class="fppf-inp fppf-inp-s" name="rlf_duration_day" id="rlfpdf_duration_day" min="0" max="31">
        <span class="fl">วัน</span>
    </div>

    <div class="form-footer">
        <div></div>
        <div class="form-footer-right">รายงานการตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)</div>
    </div>
</div>

<!-- ==================== PAGE 2 ==================== -->
<div class="fppf-page">
    <div class="page-header">
        <div style="font-size:13px; line-height:1.6;">
            <u>รายงานการตรวจเก็บวัตถุพยานที่</u>&nbsp;
            <input type="text" class="fppf-inp fppf-inp-s" id="rlfpdf_report_no_display_p2" name="rlf_report_no_display_pdf" readonly style="display:inline-block; width:auto; min-width:80px; max-width:160px;" tabindex="-1">
            &nbsp;/25&nbsp;
            <input type="text" class="fppf-inp fppf-inp-s" name="rlf_report_year_pdf" id="rlfpdf_report_year" readonly style="display:inline-block; width:30px;" tabindex="-1" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div style="font-size:13px; text-align:right;">2/2</div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="fppf-inp" name="rlf_agency_name_2" id="rlfpdf_agency_name_2">
    </div>

    <!-- 6. ลักษณะวัตถุพยาน -->
    <div class="sec-heading">6. <u>ลักษณะวัตถุพยาน</u></div>
    <div class="fr i1">
        <span class="fl-b">จำนวนวัตถุพยานทั้งสิ้น</span>
        <input type="number" class="fppf-inp fppf-inp-s" name="rlf_evidence_total[]" id="rlfpdf_evidence_total" min="0" placeholder="0">
        <span class="fl">รายการ</span>
    </div>

    <div id="rlfpdf_evidence_container" style="padding-left:20px;">
        <!-- รายการที่ 1 -->
        <div class="rlfpdf-ev-card">
            <div class="fr">
                <span class="fl">รายการที่</span>
                <span class="fl-b rlfpdf-ev-num">1</span>
                <span class="fl">เป็น</span>
                <input type="text" class="fppf-inp" name="rlf_ev_description[]">
                <button type="button" class="fppf-del-btn rlfpdf-remove-ev" title="ลบ">✕</button>
            </div>
            <div class="fr">
                <span class="fl">ขนาด ก/ผคก.</span>
                <input type="text" class="fppf-inp fppf-inp-s" name="rlf_ev_width[]">
                <span class="fl">cm. ย.</span>
                <input type="text" class="fppf-inp fppf-inp-s" name="rlf_ev_length[]">
                <span class="fl">cm. ส.</span>
                <input type="text" class="fppf-inp fppf-inp-s" name="rlf_ev_height[]">
                <span class="fl">cm.</span>
                <span class="fl" style="margin-left:8px;">จำนวน</span>
                <input type="text" class="fppf-inp fppf-inp-s" name="rlf_ev_quantity[]">
                <span class="fl" style="margin-left:8px;">ป้ายหมายเลข</span>
                <input type="text" class="fppf-inp" name="rlf_ev_label_no[]" style="max-width:100px;">
            </div>
            <div class="fr">
                <span class="fl">การตรวจพิสูจน์</span>
                <select class="fppf-select lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="max-width:280px;">
                    <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                    <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                    <option value="fingerprint" selected>กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                    <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                    <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                    <option value="document">กลุ่มงานตรวจเอกสาร</option>
                </select>
                <input type="hidden" class="lab-unit-value" name="rlf_ev_lab_unit[]" value="fingerprint">
            </div>
        </div>
    </div>
    <button type="button" class="fppf-add-btn" id="rlfpdf_add_evidence">+ เพิ่มรายการวัตถุพยาน</button>

    <!-- 7. วิธีการดำเนินการ -->
    <div class="sec-heading" style="margin-top:8px;">7. <u>วิธีการดำเนินการ</u></div>
    <div id="rlfpdf_method_container" style="padding-left:20px;">
        <div class="rlfpdf-method-row">
            <select class="fppf-select" name="rlf_method_name[]">
                <option value="" selected disabled>-- เลือกวิธีการ --</option>
                <option value="Forensic Light Sources">Forensic Light Sources</option>
                <option value="Powder">Powder</option>
                <option value="Super Glue">Super Glue</option>
                <option value="Indanedione-Zine">Indanedione-Zine</option>
                <option value="Amido Black">Amido Black</option>
                <option value="Sticky Side">Sticky Side</option>
                <option value="Rhodamine 6G">Rhodamine 6G</option>
                <option value="Ninhydrin">Ninhydrin</option>
                <option value="Acid Yellow 7">Acid Yellow 7</option>
                <option value="SPR">SPR</option>
                <option value="Basic Yellow 40">Basic Yellow 40</option>
                <option value="อื่นๆ">อื่นๆ</option>
            </select>
            <button type="button" class="fppf-del-btn rlfpdf-remove-method" title="ลบ">✕</button>
            <textarea class="fppf-method-detail" name="rlf_method_detail[]" rows="1" placeholder="รายละเอียด..." oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px';"></textarea>
        </div>
    </div>
    <button type="button" class="fppf-add-btn" id="rlfpdf_add_method">+ เพิ่มวิธีการ</button>

    <!-- 8. การดำเนินการ -->
    <div class="sec-heading" style="margin-top:8px;">8. <u>การดำเนินการ</u></div>
    <div class="fr i1">
        <span class="fl-b">วัตถุพยานแผ่นเก็บรอยลายนิ้วมือแฝง/ภาพถ่ายรอยลายนิ้วมือแฝง</span>
    </div>
    <div class="fr i1">
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_action_evidence_dest" value="กนฝ." id="rlfpdf_action_gnf">&nbsp;กนฝ.</label>
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_action_evidence_dest" value="อื่นๆ" id="rlfpdf_action_evidence_other_chk">&nbsp;อื่นๆ</label>
        <input type="text" class="fppf-inp" name="rlf_action_evidence_other_text" id="rlfpdf_action_evidence_other_text" style="max-width:200px;">
    </div>
    <div class="fr i1">
        <span class="fl-b">ของกลาง</span>
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_action_exhibit" value="ส่งคืนพนักงานสอบสวน" id="rlfpdf_action_return_investigator">&nbsp;ส่งคืนพนักงานสอบสวน</label>
        <span class="fl">สภ.</span>
        <select class="fppf-select" name="rlf_action_return_station" id="rlfpdf_action_return_station" style="max-width:200px;"><?= $rlfpdfStationOpts ?></select>
    </div>
    <div class="fr i1">
        <span class="fl">ส่งต่อกลุ่มงาน</span>
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_action_forward_dept[]" value="กชว." id="rlfpdf_fwd_gchw">&nbsp;กชว.</label>
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_action_forward_dept[]" value="กอป." id="rlfpdf_fwd_gop">&nbsp;กอป.</label>
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_action_forward_dept[]" value="กอส." id="rlfpdf_fwd_gos">&nbsp;กอส.</label>
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_action_forward_dept[]" value="กคม." id="rlfpdf_fwd_gkm">&nbsp;กคม.</label>
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_action_forward_dept[]" value="กคพ." id="rlfpdf_fwd_gkp">&nbsp;กคพ.</label>
        <label class="ck"><input type="checkbox" class="fppf-cb" name="rlf_action_forward_dept[]" value="อื่นๆ" id="rlfpdf_fwd_other">&nbsp;อื่นๆ</label>
        <input type="text" class="fppf-inp" name="rlf_action_forward_other_text" id="rlfpdf_action_forward_other_text" style="max-width:150px;">
    </div>

    <!-- ลงชื่อ -->
    <div class="signature-block">
        <div>ลงชื่อ ........................................ ผู้รายงาน</div>
        <div>(<input type="text" class="fppf-inp" name="rlf_signer_name" id="rlfpdf_signer_name" style="width:200px; text-align:center; display:inline-block;">)</div>
        <div>ตำแหน่ง <input type="text" class="fppf-inp" name="rlf_signer_position" id="rlfpdf_signer_position" style="width:200px; text-align:center; display:inline-block;"></div>
        <div>วันที่ <input type="date" class="fppf-inp" name="rlf_sign_date" id="rlfpdf_sign_date" style="width:180px; text-align:center; display:inline-block;"></div>
    </div>

    <div class="form-footer">
        <div></div>
        <div class="form-footer-right">รายงานการตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)</div>
    </div>
</div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_report_fingerprint_pdf">
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
    'use strict';

    var methodOpts = '<option value="" selected disabled>-- เลือกวิธีการ --</option>' +
        '<option value="Forensic Light Sources">Forensic Light Sources</option>' +
        '<option value="Powder">Powder</option><option value="Super Glue">Super Glue</option>' +
        '<option value="Indanedione-Zine">Indanedione-Zine</option><option value="Amido Black">Amido Black</option>' +
        '<option value="Sticky Side">Sticky Side</option><option value="Rhodamine 6G">Rhodamine 6G</option>' +
        '<option value="Ninhydrin">Ninhydrin</option><option value="Acid Yellow 7">Acid Yellow 7</option>' +
        '<option value="SPR">SPR</option><option value="Basic Yellow 40">Basic Yellow 40</option>' +
        '<option value="อื่นๆ">อื่นๆ</option>';

    var labOpts = '<option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>' +
        '<option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>' +
        '<option value="fingerprint" selected>กลุ่มงานตรวจลายนิ้วมือแฝง</option>' +
        '<option value="drug">กลุ่มงานตรวจยาเสพติด</option>' +
        '<option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>' +
        '<option value="document">กลุ่มงานตรวจเอกสาร</option>';

    /* ========== Inspector Row Management ========== */
    window.rlfpdfAddInspectorRow = function() {
        var container = document.getElementById('rlfpdf_inspector_container');
        var count = container.querySelectorAll('.rlfpdf-inspector-row').length + 1;
        var opts = '<option value="">-- เลือกผู้ตรวจ --</option>';
        if (typeof rpUsersList !== 'undefined' && rpUsersList.length > 0) {
            rpUsersList.forEach(function(u) {
                opts += '<option value="' + u.fullname + '">' + u.fullname + '</option>';
            });
        }
        var html = '<div class="fr i1 rlfpdf-inspector-row">' +
            '<span class="fl rlfpdf-inspector-num">4.' + count + '.</span>' +
            '<select class="fppf-select rp-inspector-select" name="rlf_inspector_name[]">' + opts + '</select>' +
            '<span class="fl" style="margin-left:6px;">ตำแหน่ง</span>' +
            '<input type="text" class="fppf-inp rp-inspector-position" name="rlf_inspector_position[]" placeholder="ตำแหน่ง" readonly>' +
            '<button type="button" class="fppf-del-btn" title="ลบ" onclick="this.closest(\'.rlfpdf-inspector-row\').remove(); rlfpdfRenumberInspectors();">✕</button>' +
            '</div>';
        container.insertAdjacentHTML('beforeend', html);
        var $modal = $('#modalReportFingerprintPdf');
        var $newSel = $(container).find('.rlfpdf-inspector-row:last .rp-inspector-select');
        $newSel.select2({ theme: 'bootstrap-5', width: '100%', placeholder: '-- เลือกผู้ตรวจ --', allowClear: true, dropdownParent: $modal });
    };

    window.rlfpdfRenumberInspectors = function() {
        var rows = document.querySelectorAll('#rlfpdf_inspector_container .rlfpdf-inspector-num');
        rows.forEach(function(el, idx) { el.textContent = '4.' + (idx + 1) + '.'; });
    };

    /* ========== Evidence Items ========== */
    document.getElementById('rlfpdf_add_evidence').addEventListener('click', function() {
        var container = document.getElementById('rlfpdf_evidence_container');
        var count = container.querySelectorAll('.rlfpdf-ev-card').length + 1;
        var html = '<div class="rlfpdf-ev-card">' +
            '<div class="fr"><span class="fl">รายการที่</span><span class="fl-b rlfpdf-ev-num">' + count + '</span>' +
            '<span class="fl">เป็น</span><input type="text" class="fppf-inp" name="rlf_ev_description[]">' +
            '<button type="button" class="fppf-del-btn rlfpdf-remove-ev" title="ลบ">✕</button></div>' +
            '<div class="fr"><span class="fl">ขนาด ก/ผคก.</span><input type="text" class="fppf-inp fppf-inp-s" name="rlf_ev_width[]">' +
            '<span class="fl">cm. ย.</span><input type="text" class="fppf-inp fppf-inp-s" name="rlf_ev_length[]">' +
            '<span class="fl">cm. ส.</span><input type="text" class="fppf-inp fppf-inp-s" name="rlf_ev_height[]"><span class="fl">cm.</span>' +
            '<span class="fl" style="margin-left:8px;">จำนวน</span><input type="text" class="fppf-inp fppf-inp-s" name="rlf_ev_quantity[]">' +
            '<span class="fl" style="margin-left:8px;">ป้ายหมายเลข</span><input type="text" class="fppf-inp" name="rlf_ev_label_no[]" style="max-width:100px;"></div>' +
            '<div class="fr"><span class="fl">การตรวจพิสูจน์</span><select class="fppf-select lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="max-width:280px;">' + labOpts + '</select><input type="hidden" class="lab-unit-value" name="rlf_ev_lab_unit[]" value=""></div>' +
            '</div>';
        container.insertAdjacentHTML('beforeend', html);
    });

    document.getElementById('rlfpdf_evidence_container').addEventListener('click', function(e) {
        var btn = e.target.closest('.rlfpdf-remove-ev');
        if (!btn) return;
        var cards = this.querySelectorAll('.rlfpdf-ev-card');
        if (cards.length <= 1) return;
        btn.closest('.rlfpdf-ev-card').remove();
        this.querySelectorAll('.rlfpdf-ev-num').forEach(function(el, i) { el.textContent = (i + 1); });
    });

    /* ========== Methods ========== */
    document.getElementById('rlfpdf_add_method').addEventListener('click', function() {
        var container = document.getElementById('rlfpdf_method_container');
        var html = '<div class="rlfpdf-method-row">' +
            '<select class="fppf-select" name="rlf_method_name[]">' + methodOpts + '</select>' +
            '<button type="button" class="fppf-del-btn rlfpdf-remove-method" title="ลบ">✕</button>' +
            '<textarea class="fppf-method-detail" name="rlf_method_detail[]" rows="1" placeholder="รายละเอียด..." oninput="this.style.height=\'auto\';this.style.height=this.scrollHeight+\'px\';"></textarea></div>';
        container.insertAdjacentHTML('beforeend', html);
    });

    document.getElementById('rlfpdf_method_container').addEventListener('click', function(e) {
        var btn = e.target.closest('.rlfpdf-remove-method');
        if (!btn) return;
        if (this.querySelectorAll('.rlfpdf-method-row').length <= 1) return;
        btn.closest('.rlfpdf-method-row').remove();
    });

    /* ========== Sender auto-fill position ========== */
    var senderSel = document.getElementById('rlfpdf_evidence_sender');
    if (senderSel) {
        senderSel.addEventListener('change', function() {
            var opt = this.selectedOptions[0];
            var pos = document.getElementById('rlfpdf_evidence_sender_position');
            if (pos && opt) pos.value = opt.dataset.position || '';
        });
    }

    /* ========== Init Select2 on modal shown ========== */
    document.addEventListener('DOMContentLoaded', function() {
        $('#modalReportFingerprintPdf').on('shown.bs.modal', function() {
            var $modal = $(this);
            if (typeof rpLoadUsers === 'function') {
                rpLoadUsers(function() {
                    $modal.find('#rlfpdf_inspector_container .rp-inspector-select').each(function() {
                        var current = $(this).val();
                        if (typeof rpBuildUserOptions === 'function') {
                            $(this).html(rpBuildUserOptions(current));
                        }
                    });
                    $modal.find('#rlfpdf_inspector_container .rp-inspector-select').each(function() {
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

})();
</script>
