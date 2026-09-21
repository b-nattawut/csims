<?php
date_default_timezone_set('Asia/Bangkok');

$inspectorOptionsFP = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
if (isset($pdo)) {
    $qryInspectorFP = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname,
                     IFNULL(t3.position_name, '-') AS position_name
                     FROM user_profile t1 
                     LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                     LEFT JOIN user_position t3 ON t1.position_id = t3.position_id
                     ORDER BY t1.user_id DESC";
    $stmtFP = $pdo->query($qryInspectorFP);
    while ($rowFP = $stmtFP->fetch(PDO::FETCH_ASSOC)) {
        $inspectorOptionsFP .= '<option value="' . $rowFP['user_id'] . '" data-position="' . htmlspecialchars($rowFP['position_name']) . '">' . htmlspecialchars($rowFP['fullname']) . '</option>';
    }
}

$policeStationOptionsFP = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qryPS = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stmtPS = $pdo->query($qryPS);
    while ($rowPS = $stmtPS->fetch(PDO::FETCH_ASSOC)) {
        $policeStationOptionsFP .= '<option value="' . htmlspecialchars($rowPS['station_name']) . '">' . htmlspecialchars($rowPS['station_name']) . '</option>';
    }
}

$todayDateFP = date('Y-m-d');
$todayTimeFP = date('H:i');
?>

<style>
/* ============================================================ */
/* Scoped styles for Fingerprint Evidence Checklist (PDF-style)  */
/* ============================================================ */

/* hwpen button */
#addCheckListModalFingerprint .btn-hw-open {
    flex-shrink: 0; min-width: 18px; padding: 0 4px;
    border: none; background: none; color: #6366f1;
    font-size: 0.7rem; cursor: pointer; line-height: 1.5;
}
#addCheckListModalFingerprint .btn-hw-open:hover { color: #4338ca; transform: scale(1.15); }

/* ===== BODY / PAGE ===== */
#addCheckListModalFingerprint .fpf-body {
    background: #bbb; padding: 10px 0;
}
#addCheckListModalFingerprint .fpf-page {
    width: 210mm; min-height: 297mm; margin: 0 auto 10px auto;
    background: #fff; padding: 8mm 10mm 5mm 10mm;
    font-family: 'Sarabun', sans-serif; font-size: 13px; color: #000;
    line-height: 1.5; box-shadow: 0 1px 6px rgba(0,0,0,.25);
    display: flex; flex-direction: column; position: relative;
    box-sizing: border-box;
}

/* ===== HEADER ===== */
#addCheckListModalFingerprint .fpf-header {
    position: relative; height: 70px; margin-bottom: 4px; flex-shrink: 0;
}
#addCheckListModalFingerprint .fpf-header-logo {
    position: absolute; left: 0; top: 0;
}
#addCheckListModalFingerprint .fpf-header-logo img {
    width: 70px; height: 70px; object-fit: contain;
}
#addCheckListModalFingerprint .fpf-header-center {
    position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%);
    text-align: center; white-space: nowrap;
}
#addCheckListModalFingerprint .fpf-title-main {
    font-size: 15px; font-weight: 700;
}
#addCheckListModalFingerprint .fpf-title-sub {
    font-size: 13px; font-weight: 600; color: #333;
}
#addCheckListModalFingerprint .fpf-header-right {
    position: absolute; right: 0; top: 50%; transform: translateY(-50%);
}
#addCheckListModalFingerprint .fpf-doc-box {
    border: 1.5px solid #000; padding: 3px 8px; font-size: 11px; line-height: 1.6;
    white-space: nowrap;
}
#addCheckListModalFingerprint .fpf-doc-line {
    font-size: 11px;
}

/* ===== FORM BODY (single column) ===== */
#addCheckListModalFingerprint .fpf-form-body {
    display: flex; flex-direction: column; border: 1.5px solid #000; flex: 1;
}

/* ===== Column Headers ===== */
#addCheckListModalFingerprint .fpf-row-header {
    display: flex; border-bottom: 1px solid #000; background: #f5f5f5;
    font-weight: 600; font-size: 11.5px; flex-shrink: 0;
}
#addCheckListModalFingerprint .fpf-lbl-seq {
    min-width: 48px; text-align: center; padding: 2px 0; border-right: 1px solid #000;
}
#addCheckListModalFingerprint .fpf-lbl-data {
    flex: 1; text-align: center; padding: 2px 0;
}

/* ===== Section rows (numbered sections) ===== */
#addCheckListModalFingerprint .fpf-sec-row {
    display: flex; border-bottom: 1px solid #000; min-height: 30px;
}
#addCheckListModalFingerprint .fpf-sec-label {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000;
    padding: 3px 2px; text-align: center; font-size: 10px; line-height: 1.2;
    display: flex; flex-direction: column; align-items: center; justify-content: flex-start;
}
#addCheckListModalFingerprint .fpf-sec-num {
    font-weight: 700; font-size: 12px; display: block;
}
#addCheckListModalFingerprint .fpf-sec-txt {
    font-size: 10px; word-break: keep-all;
}
#addCheckListModalFingerprint .fpf-sec-body {
    flex: 1; padding: 4px 6px; font-size: 11.5px; line-height: 1.7;
}

/* ===== Field row ===== */
#addCheckListModalFingerprint .fpf-fr {
    display: flex; flex-wrap: wrap; align-items: center; margin-bottom: 3px; line-height: 1.7;
}
#addCheckListModalFingerprint .fpf-fl {
    font-size: 11.5px; white-space: nowrap; margin-right: 4px;
}
#addCheckListModalFingerprint .fpf-fl-b {
    font-size: 11.5px; font-weight: 600; white-space: nowrap; margin-right: 4px;
}

/* ===== INPUT FIELDS ===== */
#addCheckListModalFingerprint .fpf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 18px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px;
}
#addCheckListModalFingerprint .fpf-inp-m {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 18px; outline: none; color: #000;
    flex: 1; min-width: 60px; margin: 0 2px; text-align: center;
}
#addCheckListModalFingerprint .fpf-inp-full {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 18px; outline: none; color: #000;
    width: 100%; display: block;
}
#addCheckListModalFingerprint .fpf-inp-s {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 18px; outline: none; color: #000;
    min-width: 15px; max-width: 50px; margin: 0 2px; flex: 0 1 40px; text-align: center;
}

/* SELECT styled like dotted line */
#addCheckListModalFingerprint .fpf-sel {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0; height: 18px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px; cursor: pointer;
}

/* ===== CHECKBOX (PDF square box) ===== */
#addCheckListModalFingerprint .fpf-cb {
    appearance: none; -webkit-appearance: none;
    width: 13px; height: 13px; border: 1.5px solid #000;
    margin-right: 3px; cursor: pointer; position: relative;
    vertical-align: middle; flex-shrink: 0; background: #fff;
}
#addCheckListModalFingerprint .fpf-cb:checked::after {
    content: '✓'; font-size: 12px; font-weight: 700;
    position: absolute; top: -3px; left: 0px; color: #000;
}

/* Checkbox label */
#addCheckListModalFingerprint .fpf-ck {
    display: inline-flex; align-items: center; margin-right: 14px;
    font-size: 11.5px; white-space: nowrap; vertical-align: middle; cursor: pointer;
}

/* Filled square bullet */
#addCheckListModalFingerprint .fpf-bk {
    width: 10px; height: 10px; background: #000;
    display: inline-block; margin-right: 3px; flex-shrink: 0;
    position: relative; top: 1px;
}

/* Bullet header */
#addCheckListModalFingerprint .fpf-bh {
    display: flex; align-items: center; font-weight: 600;
    font-size: 11.5px; margin-top: 5px; margin-bottom: 3px;
}

/* Sub-items */
#addCheckListModalFingerprint .fpf-si {
    display: flex; align-items: center; font-size: 11.5px; line-height: 1.7; margin-bottom: 2px;
}
#addCheckListModalFingerprint .fpf-si-no {
    min-width: 25px; padding-left: 8px; font-size: 11.5px;
}

/* Checkbox group */
#addCheckListModalFingerprint .fpf-cg {
    display: flex; flex-wrap: wrap; align-items: center; gap: 2px 3px; margin-bottom: 3px;
}

/* Indents */
#addCheckListModalFingerprint .fpf-i1 { padding-left: 15px; }
#addCheckListModalFingerprint .fpf-i2 { padding-left: 28px; }

/* ===== FOOTER ===== */
#addCheckListModalFingerprint .fpf-footer {
    margin-top: auto; font-size: 9.5px; color: #333;
    display: flex; justify-content: space-between; align-items: flex-end; flex-shrink: 0;
}
#addCheckListModalFingerprint .fpf-footer-left { flex: 1; }
#addCheckListModalFingerprint .fpf-footer-right {
    text-align: right; white-space: nowrap; line-height: 1.3;
}

/* ===== Add/Remove buttons ===== */
#addCheckListModalFingerprint .fpf-add-btn {
    font-size: 10px; padding: 1px 8px; border: 1px dashed #888;
    background: #f8f8f8; cursor: pointer; color: #333; margin: 3px 0;
    font-family: 'Sarabun', sans-serif;
}
#addCheckListModalFingerprint .fpf-add-btn:hover { background: #e0e0e0; }
#addCheckListModalFingerprint .fpf-del-btn {
    font-size: 9px; padding: 0 4px; border: 1px solid #ccc;
    background: #fff; cursor: pointer; color: #c00;
    font-family: 'Sarabun', sans-serif; line-height: 1.5;
}
#addCheckListModalFingerprint .fpf-del-btn:hover { background: #fee; }

/* ===== Separator ===== */
#addCheckListModalFingerprint .fpf-sep {
    border-top: 1px dashed #ccc; margin: 3px 0;
}

/* ===== Section 6 set header ===== */
#addCheckListModalFingerprint .fp-section6-set-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 4px; padding: 4px 0; border-bottom: 2px solid #0d6efd;
}
#addCheckListModalFingerprint .fp-section6-set-header .fp-set-label {
    color: #0d6efd; font-weight: 700; font-size: 12px;
}
#addCheckListModalFingerprint .fp-btn-remove-section6 {
    font-size: 10px; padding: 2px 8px; border: 1px solid #dc3545;
    background: #fff; cursor: pointer; color: #dc3545;
    font-family: 'Sarabun', sans-serif; border-radius: 3px;
}
#addCheckListModalFingerprint .fp-btn-remove-section6:hover { background: #fee; }

/* ===== Evidence card ===== */
#addCheckListModalFingerprint .fpf-ev-card {
    padding: 3px 0; border-bottom: 1px dashed #ccc; margin-bottom: 2px;
}
#addCheckListModalFingerprint .fpf-ev-card:last-child { border-bottom: none; }

/* ===== Method row (dropdown per evidence card) ===== */
#addCheckListModalFingerprint .fpf-method-title {
    font-weight: 600; font-size: 11px; margin: 4px 0 2px;
    border-top: 1px dashed #ccc; padding-top: 3px;
}
#addCheckListModalFingerprint .fpf-method-row {
    display: flex; align-items: center; gap: 3px; margin-bottom: 2px; font-size: 11px;
}
#addCheckListModalFingerprint .fpf-method-row .fpf-sel {
    max-width: 200px; font-size: 11px;
}
#addCheckListModalFingerprint .fpf-method-row .fpf-inp {
    font-size: 11px; flex: 1;
}

/* ===== Found row ===== */
#addCheckListModalFingerprint .fpf-found-row {
    padding: 2px 0; border-bottom: 1px dashed #ccc; margin-bottom: 1px;
}

/* ===== Photo row ===== */
#addCheckListModalFingerprint .fpf-photo-row {
    display: flex; align-items: center; gap: 3px; margin-bottom: 2px;
}
#addCheckListModalFingerprint .fpf-photo-row .fpf-inp { flex: 1; }

/* ===== Photo Grid 5×7 (35 photos/page) ===== */
#addCheckListModalFingerprint .fpf-photo-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 6px 4px;
    align-content: start;
}
#addCheckListModalFingerprint .fpf-photo-cell {
    position: relative;
    border: 1.5px solid #333;
    overflow: hidden;
    background: #fff;
}
#addCheckListModalFingerprint .fpf-photo-cell img {
    width: 100%;
    aspect-ratio: 4/3;
    object-fit: cover;
    display: block;
}
#addCheckListModalFingerprint .fpf-cell-delete {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: none;
    background: rgba(220,53,69,0.85);
    color: #fff;
    font-size: 10px;
    cursor: pointer;
    padding: 0;
    line-height: 18px;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
}
#addCheckListModalFingerprint .fpf-cell-filename {
    font-size: 7.5px;
    font-weight: 500;
    font-family: 'Sarabun', sans-serif;
    text-align: center;
    padding: 2px 2px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    background: transparent;
    color: #222;
    line-height: 1.4;
}
#addCheckListModalFingerprint .fpf-photo-dropzone {
    border: 2px dashed #b0bec5;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    background: #f8f9fa;
    margin-bottom: 8px;
    transition: all 0.2s;
}
#addCheckListModalFingerprint .fpf-photo-dropzone:hover,
#addCheckListModalFingerprint .fpf-photo-dropzone.dragover {
    border-color: #2196F3;
    background: #e3f2fd;
}
#addCheckListModalFingerprint .fpf-add-photo-page-btn {
    display: block; margin: 8px auto; padding: 4px 16px;
    border: 1px dashed #888; background: #f0f0f0; cursor: pointer;
    font-size: 11px; font-family: 'Sarabun', sans-serif; color: #333;
}
#addCheckListModalFingerprint .fpf-add-photo-page-btn:hover { background: #e0e0e0; }

@media print {
    #addCheckListModalFingerprint .fpf-page {
        margin: 0; box-shadow: none; page-break-after: always;
    }
    #addCheckListModalFingerprint .fpf-photo-grid { gap: 2px !important; }
    #addCheckListModalFingerprint .fpf-photo-cell { page-break-inside: avoid; }
    #addCheckListModalFingerprint .fpf-photo-dropzone { display: none !important; }
    #addCheckListModalFingerprint .fpf-cell-delete { display: none !important; }
}
</style>

<!-- ===== MODAL ===== -->
<div class="modal fade" id="addCheckListModalFingerprint" aria-labelledby="addCheckListModalFingerprintLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 860px; margin: 1.75rem auto;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-2 px-4">
                <h5 class="modal-title fw-bold" id="addCheckListModalFingerprintLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body fpf-body p-0" style="max-height: 80vh; overflow-y: auto; position:relative;">
                <div id="fpPdfLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track">
                            <div class="csims-bar-fill"></div>
                        </div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>
                <form id="incidentCheckListFormFingerprint" novalidate>
                    <input type="hidden" id="receiveNoti_id_fp" name="receiveNoti_id">
                    <input type="hidden" id="doc_no_fp" name="doc_no">
                    <input type="hidden" id="report_no_fp" name="report_no">

                    <!-- Switch ไปฟอร์มมาตรฐาน -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="editInfoFP" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountFP" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToStdFormFP" checked style="width: 3rem; height: 1.5rem; cursor: pointer;" onchange="if(!this.checked){ this.checked=true; switchToFingerprintStandardForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToStdFormFP" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ================================================================ -->
<!-- ========================= PAGE 1 ============================== -->
<!-- ================================================================ -->
<div class="fpf-page">

    <!-- HEADER -->
    <div class="fpf-header">
        <div class="fpf-header-logo">
            <img src="./images/office-of-police-forensic-icon.jpg" alt="ตราสำนักงานพิสูจน์หลักฐานตำรวจ">
        </div>
        <div class="fpf-header-center">
            <div class="fpf-title-main">แบบฟอร์มตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="fpf-title-sub">การตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)</div>
        </div>
        <div class="fpf-header-right">
            <div class="fpf-doc-box">
                <div class="fpf-doc-line">รายงานที่ <span id="receiveNoti_No_fp" style="display:inline-block;min-width:60px;border-bottom:1px dotted #888;text-align:center;"></span> / 25<span id="receiveNotiYear_fp" style="display:inline-block;min-width:30px;border-bottom:1px dotted #888;text-align:center;"><?= substr((date('Y') + 543), -2) ?></span></div>
                <div class="fpf-doc-line">หน้าที่ <span class="fpf-cur-page">1</span> / <span class="fpf-total-page">2</span></div>
            </div>
        </div>
    </div>

    <!-- TWO-COLUMN BODY -->
    <div class="fpf-form-body">

        <div class="fpf-row-header">
            <div class="fpf-lbl-seq">ลำดับ</div>
            <div class="fpf-lbl-data">ข้อมูล</div>
        </div>

            <!-- 1. การรับแจ้ง -->
            <div class="fpf-sec-row">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">1.</span>
                    <span class="fpf-sec-txt">การรับ<br>แจ้ง</span>
                </div>
                <div class="fpf-sec-body">
                    <div class="fpf-fr">
                        <span class="fpf-fl">วันที่</span>
                        <input type="date" class="fpf-inp-m" id="fp_receive_date" name="fp_receive_date" value="<?= $todayDateFP ?>">
                        <span class="fpf-fl">เวลา</span>
                        <input type="time" class="fpf-inp" id="fp_receive_time" name="fp_receive_time" value="<?= $todayTimeFP ?>" step="60" style="text-align:center;">
                        <span class="fpf-fl">น.</span>
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">ปจว.ข้อที่</span>
                        <input type="text" class="fpf-inp" id="fp_case_no" name="fp_case_no">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fp_case_no" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">การรับแจ้งทางหนังสือ สน./สภ.</span>
                        <select class="fpf-sel" id="fp_police_station" name="fp_police_station" style="max-width:200px;"><?= $policeStationOptionsFP ?></select>
                        <span class="fpf-fl" style="margin-left:6px;">ที่</span>
                        <input type="text" class="fpf-inp" id="fp_letter_no" name="fp_letter_no">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fp_letter_no" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                        <span class="fpf-fl" style="margin-left:6px;">ลง</span>
                        <input type="date" class="fpf-inp" id="fp_letter_date" name="fp_letter_date" style="text-align:center;" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">ผู้ส่งของกลาง</span>
                        <select class="fpf-sel" id="fp_evidence_sender" name="fp_evidence_sender"><?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งของกลาง --', $inspectorOptionsFP) ?></select>
                        <span class="fpf-fl" style="margin-left:6px;">ตำแหน่ง</span>
                        <input type="text" class="fpf-inp" id="fp_evidence_sender_position" name="fp_evidence_sender_position" readonly>
                        <span class="fpf-fl" style="margin-left:6px;">โทร</span>
                        <input type="tel" class="fpf-inp fpf-phone-format" id="fp_evidence_sender_phone" name="fp_evidence_sender_phone" style="max-width:120px;" maxlength="12">
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">รับวัตถุพยานตามหนังสือ</span>
                        <input type="text" class="fpf-inp" id="fp_evidence_letter_no" name="fp_evidence_letter_no" placeholder="">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fp_evidence_letter_no" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                        <span class="fpf-fl">ที่</span>
                        <input type="text" class="fpf-inp" id="fp_evidence_doc_no" name="fp_evidence_doc_no">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fp_evidence_doc_no" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                        <span class="fpf-fl">ลง</span>
                        <input type="date" class="fpf-inp" id="fp_evidence_doc_date" name="fp_evidence_doc_date" style="text-align:center;" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="fpf-cg">
                        <span class="fpf-fl" style="margin-right:8px;">จุดประสงค์ในการตรวจพิสูจน์</span>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_purpose[]" value="เพื่อตรวจเก็บรอยลายนิ้วมือแฝง" id="fp_purpose_fingerprint">เพื่อตรวจเก็บรอยลายนิ้วมือแฝง</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_purpose[]" value="อื่นๆ" id="fp_purpose_other">อื่นๆ</label>
                        <input type="text" class="fpf-inp" name="fp_purpose_other_text" id="fp_purpose_other_text" style="flex:1; border-bottom:1px dotted #333; border-top:none; border-left:none; border-right:none; background:transparent;">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fp_purpose_other_text" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>
                </div>
            </div>

            <!-- 2. วันเวลาที่เกิดเหตุ/ที่ทราบเหตุ -->
            <div class="fpf-sec-row">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">2.</span>
                    <span class="fpf-sec-txt">วันเวลา<br>ที่เกิด<br>เหตุ/<br>ทราบ<br>เหตุ</span>
                </div>
                <div class="fpf-sec-body">
                    <div class="fpf-bh" style="margin-top:0;"><span class="fpf-bk"></span><span>วันเวลาที่เกิดเหตุ</span></div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">วันที่</span>
                        <input type="date" class="fpf-inp-m" id="fp_incident_date" name="fp_incident_date" value="<?= $todayDateFP ?>">
                        <span class="fpf-fl">เวลาประมาณ</span>
                        <input type="time" class="fpf-inp" id="fp_incident_time" name="fp_incident_time" value="<?= $todayTimeFP ?>" step="60" style="text-align:center;">
                        <span class="fpf-fl">น.</span>
                    </div>
                    <div class="fpf-bh"><span class="fpf-bk"></span><span>วันเวลาที่ทราบเหตุ</span></div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">วันที่</span>
                        <input type="date" class="fpf-inp-m" id="fp_known_date" name="fp_known_date" value="<?= $todayDateFP ?>">
                        <span class="fpf-fl">เวลา/ประมาณ</span>
                        <input type="time" class="fpf-inp" id="fp_known_time" name="fp_known_time" value="<?= $todayTimeFP ?>" step="60" style="text-align:center;">
                        <span class="fpf-fl">น.</span>
                    </div>
                </div>
            </div>

            <!-- 3. วันเวลาที่ตรวจเก็บ -->
            <div class="fpf-sec-row">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">3.</span>
                    <span class="fpf-sec-txt">วันเวลา<br>ที่ตรวจ<br>เก็บ</span>
                </div>
                <div class="fpf-sec-body">
                    <div class="fpf-bh" style="margin-top:0;"><span class="fpf-bk"></span><span>วันเวลาที่ทำการตรวจเก็บ</span></div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">วันที่</span>
                        <input type="date" class="fpf-inp-m" id="fp_collect_date" name="fp_collect_date" value="<?= $todayDateFP ?>">
                        <span class="fpf-fl">เวลาประมาณ</span>
                        <input type="time" class="fpf-inp" id="fp_collect_time" name="fp_collect_time" value="<?= $todayTimeFP ?>" step="60" style="text-align:center;">
                        <span class="fpf-fl">น.</span>
                    </div>
                </div>
            </div>

            <!-- 4. ผู้ตรวจพิสูจน์ -->
            <div class="fpf-sec-row">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">4.</span>
                    <span class="fpf-sec-txt">ผู้ตรวจ<br>พิสูจน์</span>
                </div>
                <div class="fpf-sec-body">
                    <div class="fpf-bh" style="margin-top:0;"><span class="fpf-bk"></span><span>ผู้ตรวจพิสูจน์</span></div>
                    <div id="fp_inspector_container">
                        <div class="fpf-si fp-inspector-row">
                            <span class="fpf-si-no fp-index-label">4.1</span>
                            <select class="fpf-sel fp-inspector-select" name="fp_inspector_id[]" style="max-width:280px;"><?= $inspectorOptionsFP ?></select>
                        </div>
                    </div>
                    <button type="button" class="fpf-add-btn" id="btn_add_inspector_fp">+ เพิ่มผู้ตรวจ</button>
                </div>
            </div>

            <!-- 5. ลักษณะการหีบห่อวัตถุพยาน -->
            <div class="fpf-sec-row">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">5.</span>
                    <span class="fpf-sec-txt">ลักษณะ<br>การหีบ<br>ห่อวัตถุ<br>พยาน</span>
                </div>
                <div class="fpf-sec-body">
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_package_type[]" value="บรรจุในซองวัตถุพยาน" id="fp_pkg_envelope">บรรจุในซองวัตถุพยาน</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_package_type[]" value="พลาสติก" id="fp_pkg_plastic">พลาสติก</label>
                    </div>
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_seal_condition[]" value="มีการปิดผนึกพร้อมลงลายมือชื่อกำกับ" id="fp_seal_signed">การปิดผนึกพร้อมลงลายมือชื่อกำกับ</label>
                    </div>
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_seal_condition[]" value="เขียนรายละเอียดหน้าซองครบถ้วน" id="fp_seal_detail_complete">เขียนรายละเอียดหน้าซองวัตถุพยาน</label>
                    </div>
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fp-radio-collector" name="fp_collector_type" value="เก็บโดยเจ้าหน้าที่พิสูจน์หลักฐาน" id="fp_collector_forensic" data-group="fp_collector_type">เก็บไว้เจ้าหน้าที่พิสูจน์หลักฐาน</label>
                    </div>
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fp-radio-collector" name="fp_collector_type" value="เก็บโดยพนักงานสอบสวน" id="fp_collector_investigator" data-group="fp_collector_type">เก็บโดยพนักงานสอบสวน</label>
                    </div>
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fp-radio-collector" name="fp_collector_type" value="เก็บโดย อื่นๆ" id="fp_collector_other" data-group="fp_collector_type">เก็บโดย อื่นๆ</label>
                        <input type="text" class="fpf-inp" name="fp_collector_other_text" id="fp_collector_other_text" style="display:none;" disabled>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fp_collector_other_text" id="fp_collector_other_text_hw" title="เขียนด้วยลายมือ" style="display:none;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="fpf-fr" style="margin-top:3px;">
                        <span class="fpf-fl">เก็บเมื่อ</span>
                        <input type="date" class="fpf-inp-m" id="fp_storage_date" name="fp_storage_date">
                        <span class="fpf-fl">เวลาประมาณ</span>
                        <input type="time" class="fpf-inp" id="fp_storage_time" name="fp_storage_time" step="60" style="text-align:center;">
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">ระยะเวลา</span>
                        <input type="number" class="fpf-inp-s" id="fp_duration_year" name="fp_duration_year" min="0" placeholder="..">
                        <span class="fpf-fl">ปี</span>
                        <input type="number" class="fpf-inp-s" id="fp_duration_month" name="fp_duration_month" min="0" max="12" placeholder="..">
                        <span class="fpf-fl">เดือน</span>
                        <input type="number" class="fpf-inp-s" id="fp_duration_day" name="fp_duration_day" min="0" max="31" placeholder="..">
                        <span class="fpf-fl">วัน</span>
                    </div>
                </div>
            </div>

            <!-- 6. ลักษณะวัตถุพยาน -->
            <div id="fp_section6_wrapper">
            <div class="fpf-sec-row fp-section6-set" style="flex:1; border-bottom:none;" data-set-index="1">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">6.</span>
                    <span class="fpf-sec-txt">ลักษณะ<br>วัตถุ<br>พยาน</span>
                </div>
                <div class="fpf-sec-body">
                    <div class="fp-section6-set-header">
                        <span class="fp-set-label">ชุดที่ 1</span>
                    </div>
                    <div class="fpf-fr" style="font-weight:600;">
                        <span class="fpf-fl-b">จำนวนวัตถุพยานทั้งสิ้น</span>
                        <input type="number" class="fpf-inp-s" id="fp_evidence_total" name="fp_evidence_total" min="0" placeholder="0" style="text-align:center;">
                        <span class="fpf-fl">รายการ</span>
                    </div>
                    <div id="fp_evidence_container">
                        <!-- รายการที่ 1 -->
                        <div class="fpf-ev-card fp-evidence-card">
                            <div class="fpf-fr">
                                <span class="fpf-fl">รายการที่</span>
                                <span class="fp-evidence-badge" style="font-weight:600;">1</span>
                                <span class="fpf-fl" style="margin-left:4px;">เป็น</span>
                                <input type="text" class="fpf-inp" name="fp_ev_description[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                            <div class="fpf-fr">
                                <span class="fpf-fl">ขนาด ก/ผคก.</span>
                                <input type="text" class="fpf-inp-s" name="fp_ev_width[]">
                                <span class="fpf-fl">cm.</span>
                                <span class="fpf-fl">ย.</span>
                                <input type="text" class="fpf-inp-s" name="fp_ev_length[]">
                                <span class="fpf-fl">cm.</span>
                                <span class="fpf-fl">ส.</span>
                                <input type="text" class="fpf-inp-s" name="fp_ev_height[]">
                                <span class="fpf-fl">cm.</span>
                            </div>
                            <div class="fpf-fr">
                                <span class="fpf-fl">จำนวน</span>
                                <input type="text" class="fpf-inp" name="fp_ev_quantity[]" style="max-width:80px;">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                <span class="fpf-fl">ป้ายหมายเลข</span>
                                <input type="text" class="fpf-inp" name="fp_ev_label_no[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                            <div class="fpf-fr">
                                <span class="fpf-fl">การตรวจพิสูจน์</span>
                                <select class="fpf-sel lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="max-width:280px;">
                                    <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                                    <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                    <option value="fingerprint" selected>กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                    <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                    <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                    <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                </select>
                                <input type="hidden" class="lab-unit-value" name="fp_ev_lab_unit[]" value="fingerprint">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="fpf-add-btn" id="btn_add_evidence_fp">+ เพิ่มรายการ</button>

                </div>
            </div>
            </div><!-- /fp_section6_wrapper -->
            <div style="padding: 6px; text-align: center; border-top: 1px dashed #aaa;">
                <button type="button" class="fpf-add-btn" id="btn_add_section6_set" style="font-size: 12px; padding: 4px 16px; border: 1.5px dashed #0d6efd; color: #0d6efd; background: #f0f7ff;">
                    + เพิ่มข้อมูล 
                </button>
            </div>

            <!-- 7. วิธีการดำเนินการ -->
            <div class="fpf-sec-row">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">7.</span>
                    <span class="fpf-sec-txt">วิธีการ<br>ดำเนินการ</span>
                </div>
                <div class="fpf-sec-body">
                    <div id="fp_method_global_container">
                        <div class="fpf-method-row fp-method-row">
                            <select class="fpf-sel fp-method-select" name="fp_ev_method_name[]">
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
                            <input type="text" class="fpf-inp fp-method-detail-input" name="fp_ev_method_detail[]" placeholder="รายละเอียด...">
                            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                        </div>
                    </div>
                    <button type="button" class="fpf-add-btn" id="btn_add_method_fp">+ เพิ่มวิธีการ</button>
                </div>
            </div>

            <!-- 8. การดำเนินการ -->
            <div class="fpf-sec-row">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">8.</span>
                    <span class="fpf-sec-txt">การ<br>ดำเนินการ</span>
                </div>
                <div class="fpf-sec-body">
                    <div class="fpf-fr" style="flex-wrap:wrap;">
                        <span class="fpf-fl-b">วัตถุพยานแผ่นเก็บรอยลายนิ้วมือแฝง/ภาพถ่ายรอยลายนิ้วมือแฝง</span>
                    </div>
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_action_evidence_dest" value="กนฝ." id="fp_action_gnf">กนฝ.</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_action_evidence_dest" value="อื่นๆ" id="fp_action_evidence_other_chk">อื่นๆ</label>
                        <input type="text" class="fpf-inp" name="fp_action_evidence_other_text" id="fp_action_evidence_other_text" style="max-width:200px;">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fp_action_evidence_other_text" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="fpf-fr" style="margin-top:3px;">
                        <span class="fpf-fl-b">ของกลาง</span>
                        <label class="fpf-ck" style="margin-left:6px;"><input type="checkbox" class="fpf-cb" name="fp_action_exhibit" value="ส่งคืนพนักงานสอบสวน" id="fp_action_return_investigator">ส่งคืนพนักงานสอบสวน</label>
                        <span class="fpf-fl">สภ.</span>
                        <select class="fpf-sel" name="fp_action_return_station" id="fp_action_return_station" style="max-width:200px;"><?= $policeStationOptionsFP ?></select>
                    </div>
                    <div class="fpf-cg" style="margin-top:3px;">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_action_forward" value="ส่งต่อกลุ่มงาน" id="fp_action_forward_group">ส่งต่อกลุ่มงาน</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_action_forward_dept[]" value="กชว.">กชว.</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_action_forward_dept[]" value="กอป.">กอป.</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_action_forward_dept[]" value="กอส.">กอส.</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_action_forward_dept[]" value="กคม.">กคม.</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_action_forward_dept[]" value="กคพ.">กคพ.</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_action_forward_dept[]" value="อื่นๆ" id="fp_action_forward_other_chk">อื่นๆ</label>
                        <input type="text" class="fpf-inp" name="fp_action_forward_other_text" id="fp_action_forward_other_text" style="max-width:150px;">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fp_action_forward_other_text" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>
                </div>
            </div>

            <!-- 9. การถ่ายภาพวัตถุพยาน / ผลการตรวจเก็บ -->
            <div class="fpf-sec-row" id="fp_section9_wrapper">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">9.</span>
                    <span class="fpf-sec-txt">การถ่ายภาพ<br>วัตถุพยาน<br>/ผลการ<br>ตรวจเก็บ</span>
                </div>
                <div class="fpf-sec-body">
                    <!-- === การถ่ายภาพวัตถุพยาน === -->
                    <div class="fpf-bh"><span class="fpf-bk"></span><span>การถ่ายภาพวัตถุพยาน</span></div>
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_photo_type[]" value="ภาพการหีบห่อวัตถุพยาน" id="fp_photo_package">ภาพการหีบห่อวัตถุพยาน</label>
                    </div>
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_photo_type[]" value="ภาพวัตถุพยานพร้อมหนังสือนำส่ง ด้านหน้า (วางสเกล)" id="fp_photo_front_scale">ภาพวัตถุพยานพร้อมหนังสือนำส่ง ด้านหน้า (วางสเกล)</label>
                    </div>
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_photo_type[]" value="ภาพวัตถุพยานระยะใกล้ (วางสเกล)" id="fp_photo_close_scale">ภาพวัตถุพยานระยะใกล้ (วางสเกล)</label>
                    </div>
                    <div class="fpf-cg fpf-i1">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_photo_side[]" value="ด้านหน้า" id="fp_side_front">ด้านหน้า</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_photo_side[]" value="ด้านซ้าย" id="fp_side_left">ด้านซ้าย</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_photo_side[]" value="ด้านขวา" id="fp_side_right">ด้านขวา</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_photo_side[]" value="ด้านหลัง" id="fp_side_back">ด้านหลัง</label>
                    </div>
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_photo_type[]" value="ภาพคำนิยามเฉพาะบนวัตถุพยาน" id="fp_photo_definition">ภาพค่าพิเศษบนวัตถุพยาน</label>
                    </div>
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_photo_type[]" value="ภาพแผ่นเก็บรอยลายนิ้วมือแฝง พร้อมหนังสือนำส่ง" id="fp_photo_lift_card">ภาพแผ่นเก็บรอยลายนิ้วมือแฝง พร้อมหนังสือนำส่ง</label>
                    </div>
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_photo_type[]" value="การปรับสี/แสง" id="fp_photo_color_adj">การปรับสี/แสง</label>
                        <input type="text" class="fpf-inp" name="fp_color_adj_detail" id="fp_color_adj_detail" style="display:none;" disabled>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fp_color_adj_detail" id="fp_color_adj_detail_hw" title="เขียนด้วยลายมือ" style="display:none;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_photo_type[]" value="การปรับขนาดภาพ" id="fp_photo_resize">การปรับขนาดภาพ</label>
                        <input type="text" class="fpf-inp" name="fp_resize_detail" id="fp_resize_detail" style="display:none;" disabled>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fp_resize_detail" id="fp_resize_detail_hw" title="เขียนด้วยลายมือ" style="display:none;"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- === ผลการตรวจเก็บ === -->
                    <div class="fpf-sep" style="margin-top:6px;"></div>
                    <div class="fpf-bh"><span class="fpf-bk"></span><span>ผลการตรวจเก็บ</span></div>
                    <!-- ไม่พบ -->
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fp-radio-result" name="fp_result_type" value="ไม่พบรอยลายนิ้วมือแฝง" id="fp_result_not_found" data-group="fp_result_type"><strong>ไม่พบรอยลายนิ้วมือแฝง</strong></label>
                    </div>
                    <div id="fp_not_found_detail_wrapper" style="display:none;" class="fpf-i1">
                        <div class="fpf-fr">
                            <span class="fpf-fl">เพราะ</span>
                            <input type="text" class="fpf-inp" name="fp_not_found_reason" id="fp_not_found_reason" disabled>
                            <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fp_not_found_reason" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                        </div>
                    </div>

                    <div class="fpf-sep"></div>

                    <!-- พบ -->
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fp-radio-result" name="fp_result_type" value="พบรอยลายนิ้วมือแฝง" id="fp_result_found" data-group="fp_result_type"><strong>พบรอยลายนิ้วมือแฝง</strong></label>
                        <span class="fpf-fl">จำนวน</span>
                        <input type="number" class="fpf-inp-s" name="fp_found_total_sheets" id="fp_found_total_sheets" min="0" disabled style="text-align:center;">
                        <span class="fpf-fl">แผ่น</span>
                    </div>
                    <div id="fp_found_detail_wrapper" style="display:none;" class="fpf-i1">
                        <div id="fp_found_items_container">
                            <div class="fpf-found-row fp-found-item-row">
                                <div class="fpf-fr">
                                    <span class="fpf-fl">จากวัตถุพยานรายการที่</span>
                                    <input type="text" class="fpf-inp" name="fp_found_from_item[]" disabled style="max-width:80px;">
                                    <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                    <span class="fpf-fl">จำนวน</span>
                                    <input type="number" class="fpf-inp-s" name="fp_found_item_sheets[]" min="0" disabled style="text-align:center;">
                                    <span class="fpf-fl">แผ่น</span>
                                    <button type="button" class="fpf-del-btn" onclick="removeFPFoundItem(this)" disabled title="ลบ">✕</button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="fpf-add-btn" id="btn_add_found_item_fp" disabled>+ เพิ่มรายการ</button>
                    </div>

                    <div class="fpf-sep"></div>

                    <!-- Photograph -->
                    <div class="fpf-cg">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fp_has_photograph" value="1" id="fp_has_photograph"><strong>Photograph</strong></label>
                    </div>
                    <div id="fp_photograph_wrapper" style="display:none;" class="fpf-i1">
                        <div id="fp_photograph_container">
                            <div class="fpf-photo-row fp-photograph-row">
                                <span class="fp-photo-index" style="font-size:11px;min-width:18px;">1)</span>
                                <input type="text" class="fpf-inp" name="fp_photograph_desc[]" disabled placeholder="วัน/เดือน/ปี / รายละเอียด">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                <button type="button" class="fpf-del-btn" onclick="removeFPPhotographRow(this)" disabled title="ลบ">✕</button>
                            </div>
                        </div>
                        <button type="button" class="fpf-add-btn" id="btn_add_photograph_fp" disabled>+ เพิ่ม</button>
                    </div>
                </div>
            </div>

    </div><!-- /fpf-form-body -->

    <!-- FOOTER -->
    <div class="fpf-footer">
        <div class="fpf-footer-left"><strong></strong></div>
        <div class="fpf-footer-right">F-CS-XX แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>

</div><!-- /fpf-page -->

<!-- ================================================================ -->
<!-- ========================= หน้ารูปถ่าย (PHOTOS) ==================== -->
<!-- ================================================================ -->

<!-- หน้าแรกรูปภาพ: มี header ข้อมูล + 3 ช่องรูป -->
<div class="fpf-page fpf-photo-page" data-photo-page="1">

    <div class="fpf-header">
        <div class="fpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="fpf-header-center">
            <div class="fpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="fpf-title-sub">การตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)</div>
            <div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ</div>
        </div>
        <div class="fpf-header-right">
            <div class="fpf-doc-box">
                <div class="fpf-doc-line">รายงานที่ <span class="fp-doc-no-mirror" style="display:inline-block;min-width:60px;border-bottom:1px dotted #888;text-align:center;"></span> / 25<span class="fp-doc-year-mirror" style="display:inline-block;min-width:30px;border-bottom:1px dotted #888;text-align:center;"><?= substr((date('Y') + 543), -2) ?></span></div>
                <div class="fpf-doc-line">หน้าที่ <span class="fpf-cur-page">2</span> / <span class="fpf-total-page">2</span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:8px;">
        <div class="fpf-fr" style="margin-bottom:6px;">
            <span class="fpf-fl">วันที่ตรวจเก็บวัตถุพยาน</span>
            <input type="date" class="fpf-inp" name="fp_photo_inspect_date" style="text-align:center;">
            <span class="fpf-fl">เวลาประมาณ</span>
            <input type="time" class="fpf-inp" name="fp_photo_inspect_time" style="text-align:center;">
            <span class="fpf-fl">น.</span>
        </div>
        <div class="fpf-fr" style="flex-wrap:nowrap; margin-bottom:6px;">
            <span class="fpf-fl">รหัสภาพถ่ายที่</span>
            <input type="text" class="fpf-inp" name="fp_photo_id_start" id="fp_photo_id_start" style="min-width:40px;">
            <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fp_photo_id_start" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
            <span class="fpf-fl">ถึง</span>
            <input type="text" class="fpf-inp" name="fp_photo_id_end" id="fp_photo_id_end" style="min-width:40px;">
            <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fp_photo_id_end" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
            <span class="fpf-fl">จำนวน</span>
            <input type="text" class="fpf-inp-s" name="fp_photo_amount" id="fp_photo_amount" style="max-width:40px; text-align:center;" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
            <span class="fpf-fl">ภาพ</span>
        </div>
        <div style="font-size:10.5px; font-style:italic; margin-top:2px;">(ตามภาพถ่ายรวมที่แนบ)</div>
    </div>

    <!-- Drag & Drop zone -->
    <div class="fpf-photo-dropzone" id="fp_photo_dropzone">
        <i class="fas fa-cloud-upload-alt" style="font-size:1.2rem; color:#90a4ae;"></i>
        <div style="font-size:10px; color:#666; margin-top:2px;">ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือก</div>
    </div>
    <input type="file" id="fp_photo_input_gallery" accept="image/*" multiple style="display:none;" onchange="fpPhotoPreview(this)">
    <input type="file" id="fp_photo_input_camera" accept="image/*" capture="environment" multiple style="display:none;" onchange="fpPhotoPreview(this)">

    <!-- Photo Grid (35 photos per page, populated by JS) -->
    <div class="fpf-photo-grid" id="fp_photo_grid_1"></div>

    <div class="fpf-footer" style="margin-top:auto;">
        <div class="fpf-footer-left"><strong></strong> </div>
        <div class="fpf-footer-right" style="display:flex; flex-direction:column; align-items:flex-end; gap:2px;">
            <div style="display:flex; align-items:center; gap:4px; font-size:11px;">
                <span>ผู้จดบันทึก</span>
                <select class="fpf-sel" name="fp_photographer_name" id="fp_photographer_name" style="width:180px; font-size:10px; padding:1px 2px;">
                    <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือก --', $inspectorOptionsFP) ?>
                </select>
            </div>
            <div style="display:flex; align-items:center; gap:4px; font-size:11px;">
                <span>วัน/เวลา</span>
                <input type="datetime-local" class="fpf-inp" name="fp_photographer_datetime" id="fp_photographer_datetime" style="width:180px; font-size:10px; padding:1px 2px; text-align:center;">
            </div>
            <div style="font-size:9px; margin-top:2px;">F-CS-XX แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
        </div>
    </div>
</div>

<!-- Container สำหรับหน้ารูปถ่ายเพิ่มเติม -->
<div id="fp_extra_photo_pages"></div>

<!-- ปุ่มเพิ่มหน้ากระดาษรูปถ่าย -->
<button type="button" class="fpf-add-photo-page-btn" onclick="fpPhotoAddPage()">
    <i class="fas fa-plus me-1"></i> เพิ่มหน้าบันทึกการถ่ายภาพ
</button>
                </form>
            </div><!-- /modal-body -->
            <div class="modal-footer justify-content-end gap-2 py-2 px-3 bg-white border-top">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_fp" onclick="prepareDataForSubmissionFingerprint()">
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
    'use strict';

    // =================================================================
    // Auto-generate page numbers
    // =================================================================
    window.fpfUpdatePageNumbers = function() {
        var pages = document.querySelectorAll('#addCheckListModalFingerprint .fpf-page');
        var total = pages.length;
        pages.forEach(function(page, idx) {
            var curEl = page.querySelector('.fpf-cur-page');
            var totalEl = page.querySelector('.fpf-total-page');
            if (curEl) curEl.textContent = (idx + 1);
            if (totalEl) totalEl.textContent = total;
        });
    };
    fpfUpdatePageNumbers();

    // =================================================================
    // Method dropdown options HTML (shared)
    // =================================================================
    const fpMethodOptionsHTML = `
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
        <option value="อื่นๆ">อื่นๆ</option>`;

    function buildMethodRowHTML(showDel) {
        let h = '<div class="fpf-method-row fp-method-row">';
        h += '<select class="fpf-sel fp-method-select" name="fp_ev_method_name[]">' + fpMethodOptionsHTML + '</select>';
        h += '<input type="text" class="fpf-inp fp-method-detail-input" name="fp_ev_method_detail[]" placeholder="รายละเอียด...">';
        h += '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>';
        if (showDel) h += '<button type="button" class="fpf-del-btn" onclick="removeFPMethodRow(this)" title="ลบ">✕</button>';
        h += '</div>';
        return h;
    }

    // Section 7: global method rows
    document.getElementById('btn_add_method_fp').addEventListener('click', function() {
        const container = document.getElementById('fp_method_global_container');
        if (!container) return;
        const tmp = document.createElement('div');
        tmp.innerHTML = buildMethodRowHTML(true);
        container.appendChild(tmp.firstElementChild);
    });

    window.removeFPMethodRow = function(btn) {
        const container = btn.closest('.fp-method-container') || document.getElementById('fp_method_global_container');
        if (!container) return;
        if (container.querySelectorAll('.fp-method-row').length <= 1) {
            Swal.fire({ icon: 'warning', title: 'ไม่สามารถลบได้', text: 'ต้องมีอย่างน้อย 1 วิธีการ', confirmButtonText: 'ตกลง' });
            return;
        }
        btn.closest('.fp-method-row').remove();
    };

    // =================================================================
    // 4. ผู้ตรวจพิสูจน์ - Dynamic Rows
    // =================================================================
    const inspectorOptionsFPHTML = `<?= $inspectorOptionsFP ?>`;
    let fpInspectorIndex = 1;

    document.getElementById('btn_add_inspector_fp').addEventListener('click', function() {
        fpInspectorIndex++;
        const row = document.createElement('div');
        row.className = 'fpf-si fp-inspector-row';
        row.innerHTML = `
            <span class="fpf-si-no fp-index-label">4.${fpInspectorIndex}</span>
            <select class="fpf-sel fp-inspector-select" name="fp_inspector_id[]" style="max-width:280px;">${inspectorOptionsFPHTML}</select>
            <button type="button" class="fpf-del-btn" onclick="removeFPInspectorRow(this)" style="margin-left:5px;" title="ลบ">✕</button>`;
        document.getElementById('fp_inspector_container').appendChild(row);
    });

    window.removeFPInspectorRow = function(btn) {
        btn.closest('.fp-inspector-row').remove();
        reindexFPInspectors();
    };

    function reindexFPInspectors() {
        const rows = document.querySelectorAll('#fp_inspector_container .fp-inspector-row');
        rows.forEach((row, i) => {
            row.querySelector('.fp-index-label').textContent = '4.' + (i + 1);
        });
        fpInspectorIndex = rows.length;
    }

    // =================================================================
    // 6. รายการวัตถุพยาน - Dynamic Cards
    // =================================================================
    let fpEvidenceIndex = 1;

    document.getElementById('btn_add_evidence_fp').addEventListener('click', function() {
        fpEvidenceIndex++;
        const card = document.createElement('div');
        card.className = 'fpf-ev-card fp-evidence-card';
        card.innerHTML = `
            <div class="fpf-fr">
                <span class="fpf-fl">รายการที่</span>
                <span class="fp-evidence-badge" style="font-weight:600;">${fpEvidenceIndex}</span>
                <span class="fpf-fl" style="margin-left:4px;">เป็น</span>
                <input type="text" class="fpf-inp" name="fp_ev_description[]">
                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                <button type="button" class="fpf-del-btn" onclick="removeFPEvidenceCard(this)" title="ลบ">✕</button>
            </div>
            <div class="fpf-fr">
                <span class="fpf-fl">ขนาด ก/ผคก.</span>
                <input type="text" class="fpf-inp-s" name="fp_ev_width[]">
                <span class="fpf-fl">cm.</span>
                <span class="fpf-fl">ย.</span>
                <input type="text" class="fpf-inp-s" name="fp_ev_length[]">
                <span class="fpf-fl">cm.</span>
                <span class="fpf-fl">ส.</span>
                <input type="text" class="fpf-inp-s" name="fp_ev_height[]">
                <span class="fpf-fl">cm.</span>
            </div>
            <div class="fpf-fr">
                <span class="fpf-fl">จำนวน</span>
                <input type="text" class="fpf-inp" name="fp_ev_quantity[]" style="max-width:80px;">
                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                <span class="fpf-fl">ป้ายหมายเลข</span>
                <input type="text" class="fpf-inp" name="fp_ev_label_no[]">
                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
            </div>
            <div class="fpf-fr">
                <span class="fpf-fl">การตรวจพิสูจน์</span>
                <select class="fpf-sel lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="max-width:280px;">
                    <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                    <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                    <option value="fingerprint" selected>กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                    <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                    <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                    <option value="document">กลุ่มงานตรวจเอกสาร</option>
                </select>
                <input type="hidden" class="lab-unit-value" name="fp_ev_lab_unit[]" value="fingerprint">
            </div>`;
        document.getElementById('fp_evidence_container').appendChild(card);
    });

    window.removeFPEvidenceCard = function(btn) {
        const container = document.getElementById('fp_evidence_container');
        if (container.querySelectorAll('.fp-evidence-card').length <= 1) {
            Swal.fire({ icon: 'warning', title: 'ไม่สามารถลบได้', text: 'ต้องมีอย่างน้อย 1 รายการ', confirmButtonText: 'ตกลง' });
            return;
        }
        btn.closest('.fp-evidence-card').remove();
        reindexFPEvidence();
    };

    function reindexFPEvidence() {
        const cards = document.querySelectorAll('#fp_evidence_container .fp-evidence-card');
        cards.forEach((card, i) => {
            card.querySelector('.fp-evidence-badge').textContent = (i + 1);
        });
        fpEvidenceIndex = cards.length;
    }

    // =================================================================
    // 5. Toggle ลักษณะการหีบห่อ (Radio-like for collector)
    // =================================================================
    document.querySelectorAll('.fp-radio-collector').forEach(function(cb) {
        cb.addEventListener('change', function() {
            if (this.checked) {
                document.querySelectorAll('.fp-radio-collector').forEach(function(other) {
                    if (other !== cb) other.checked = false;
                });
            }
            const otherTxt = document.getElementById('fp_collector_other_text');
            var hwBtn = document.getElementById('fp_collector_other_text_hw');
            if (document.getElementById('fp_collector_other').checked) {
                otherTxt.style.display = '';
                otherTxt.disabled = false;
                if (hwBtn) hwBtn.style.display = '';
            } else {
                otherTxt.style.display = 'none';
                otherTxt.disabled = true;
                otherTxt.value = '';
                if (hwBtn) hwBtn.style.display = 'none';
            }
        });
    });

    // =================================================================
    // 9. Toggle การถ่ายภาพ / ผลการตรวจเก็บ
    // =================================================================
    document.getElementById('fp_photo_color_adj').addEventListener('change', function() {
        const el = document.getElementById('fp_color_adj_detail');
        const hw = document.getElementById('fp_color_adj_detail_hw');
        if (this.checked) { el.style.display = ''; el.disabled = false; if(hw) hw.style.display=''; }
        else { el.style.display = 'none'; el.disabled = true; el.value = ''; if(hw) hw.style.display='none'; }
    });

    document.getElementById('fp_photo_resize').addEventListener('change', function() {
        const el = document.getElementById('fp_resize_detail');
        const hw = document.getElementById('fp_resize_detail_hw');
        if (this.checked) { el.style.display = ''; el.disabled = false; if(hw) hw.style.display=''; }
        else { el.style.display = 'none'; el.disabled = true; el.value = ''; if(hw) hw.style.display='none'; }
    });

    // =================================================================
    // 9. ผลการตรวจเก็บ
    // =================================================================
    document.querySelectorAll('.fp-radio-result').forEach(function(cb) {
        cb.addEventListener('change', function() {
            if (this.checked) {
                document.querySelectorAll('.fp-radio-result').forEach(function(other) {
                    if (other !== cb) other.checked = false;
                });
            }
            toggleFPResultSections();
        });
    });

    function toggleFPResultSections() {
        const notFound = document.getElementById('fp_result_not_found').checked;
        const found = document.getElementById('fp_result_found').checked;

        const notFoundWrapper = document.getElementById('fp_not_found_detail_wrapper');
        const notFoundReason = document.getElementById('fp_not_found_reason');
        if (notFound) {
            notFoundWrapper.style.display = '';
            notFoundReason.disabled = false;
        } else {
            notFoundWrapper.style.display = 'none';
            notFoundReason.disabled = true;
        }

        const foundWrapper = document.getElementById('fp_found_detail_wrapper');
        const foundInputs = foundWrapper.querySelectorAll('input, button');
        if (found) {
            foundWrapper.style.display = '';
            foundInputs.forEach(el => el.disabled = false);
        } else {
            foundWrapper.style.display = 'none';
            foundInputs.forEach(el => el.disabled = true);
        }
    }

    // Found items dynamic
    let fpFoundItemIndex = 1;
    document.getElementById('btn_add_found_item_fp').addEventListener('click', function() {
        fpFoundItemIndex++;
        const row = document.createElement('div');
        row.className = 'fpf-found-row fp-found-item-row';
        row.innerHTML = `
            <div class="fpf-fr">
                <span class="fpf-fl">จากวัตถุพยานรายการที่</span>
                <input type="text" class="fpf-inp" name="fp_found_from_item[]" style="max-width:80px;">
                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                <span class="fpf-fl">จำนวน</span>
                <input type="number" class="fpf-inp-s" name="fp_found_item_sheets[]" min="0" style="text-align:center;">
                <span class="fpf-fl">แผ่น</span>
                <button type="button" class="fpf-del-btn" onclick="removeFPFoundItem(this)" title="ลบ">✕</button>
            </div>`;
        document.getElementById('fp_found_items_container').appendChild(row);
    });

    window.removeFPFoundItem = function(btn) {
        const container = document.getElementById('fp_found_items_container');
        if (container.querySelectorAll('.fp-found-item-row').length <= 1) {
            Swal.fire({ icon: 'warning', title: 'ไม่สามารถลบได้', text: 'ต้องมีอย่างน้อย 1 รายการ', confirmButtonText: 'ตกลง' });
            return;
        }
        btn.closest('.fp-found-item-row').remove();
    };

    // Photograph
    document.getElementById('fp_has_photograph').addEventListener('change', function() {
        const wrapper = document.getElementById('fp_photograph_wrapper');
        const inputs = wrapper.querySelectorAll('input, button');
        if (this.checked) {
            wrapper.style.display = '';
            inputs.forEach(el => el.disabled = false);
        } else {
            wrapper.style.display = 'none';
            inputs.forEach(el => el.disabled = true);
        }
    });

    let fpPhotoIndex = 1;
    document.getElementById('btn_add_photograph_fp').addEventListener('click', function() {
        fpPhotoIndex++;
        const row = document.createElement('div');
        row.className = 'fpf-photo-row fp-photograph-row';
        row.innerHTML = `
            <span class="fp-photo-index" style="font-size:11px;min-width:18px;">${fpPhotoIndex})</span>
            <input type="text" class="fpf-inp" name="fp_photograph_desc[]" placeholder="วัน/เดือน/ปี / รายละเอียด">
            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
            <button type="button" class="fpf-del-btn" onclick="removeFPPhotographRow(this)" title="ลบ">✕</button>`;
        document.getElementById('fp_photograph_container').appendChild(row);
    });

    window.removeFPPhotographRow = function(btn) {
        const container = document.getElementById('fp_photograph_container');
        if (container.querySelectorAll('.fp-photograph-row').length <= 1) {
            Swal.fire({ icon: 'warning', title: 'ไม่สามารถลบได้', text: 'ต้องมีอย่างน้อย 1 รายการ', confirmButtonText: 'ตกลง' });
            return;
        }
        btn.closest('.fp-photograph-row').remove();
        reindexFPPhotographs();
    };

    function reindexFPPhotographs() {
        const rows = document.querySelectorAll('#fp_photograph_container .fp-photograph-row');
        rows.forEach((row, i) => {
            row.querySelector('.fp-photo-index').textContent = (i + 1) + ')';
        });
        fpPhotoIndex = rows.length;
    }

    // =================================================================
    // Section 6 - Dynamic Set Cloning
    // =================================================================
    let fpSection6SetCount = 1;

    function buildSection6SetHTML(setIndex) {
        return `
            <div class="fpf-sec-row fp-section6-set" style="flex:1; border-bottom:none;" data-set-index="${setIndex}">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">6.</span>
                    <span class="fpf-sec-txt">ลักษณะ<br>วัตถุ<br>พยาน</span>
                </div>
                <div class="fpf-sec-body">
                    <div class="fp-section6-set-header">
                        <span class="fp-set-label">ชุดที่ ${setIndex}</span>
                        <button type="button" class="fp-btn-remove-section6" title="ลบชุดนี้">✕ ลบชุดนี้</button>
                    </div>
                    <div class="fpf-fr" style="font-weight:600;">
                        <span class="fpf-fl-b">จำนวนวัตถุพยานทั้งสิ้น</span>
                        <input type="number" class="fpf-inp-s fp-s6-evidence-total" min="0" placeholder="0" style="text-align:center;">
                        <span class="fpf-fl">รายการ</span>
                    </div>
                    <div class="fp-s6-evidence-container">
                        <div class="fpf-ev-card fp-evidence-card">
                            <div class="fpf-fr">
                                <span class="fpf-fl">รายการที่</span>
                                <span class="fp-evidence-badge" style="font-weight:600;">1</span>
                                <span class="fpf-fl" style="margin-left:4px;">เป็น</span>
                                <input type="text" class="fpf-inp" name="fp_ev_description[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                            <div class="fpf-fr">
                                <span class="fpf-fl">ขนาด ก/ผคก.</span>
                                <input type="text" class="fpf-inp-s" name="fp_ev_width[]">
                                <span class="fpf-fl">cm.</span>
                                <span class="fpf-fl">ย.</span>
                                <input type="text" class="fpf-inp-s" name="fp_ev_length[]">
                                <span class="fpf-fl">cm.</span>
                                <span class="fpf-fl">ส.</span>
                                <input type="text" class="fpf-inp-s" name="fp_ev_height[]">
                                <span class="fpf-fl">cm.</span>
                            </div>
                            <div class="fpf-fr">
                                <span class="fpf-fl">จำนวน</span>
                                <input type="text" class="fpf-inp" name="fp_ev_quantity[]" style="max-width:80px;">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                <span class="fpf-fl">ป้ายหมายเลข</span>
                                <input type="text" class="fpf-inp" name="fp_ev_label_no[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                            <div class="fpf-fr">
                                <span class="fpf-fl">การตรวจพิสูจน์</span>
                                <select class="fpf-sel lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="max-width:280px;">
                                    <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                                    <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                    <option value="fingerprint" selected>กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                    <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                    <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                    <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                </select>
                                <input type="hidden" class="lab-unit-value" name="fp_ev_lab_unit[]" value="fingerprint">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="fpf-add-btn fp-btn-add-evidence-set">+ เพิ่มรายการ</button>

                </div>
            </div>`;
    }

    // Add Section 6 Set
    document.getElementById('btn_add_section6_set').addEventListener('click', function() {
        fpSection6SetCount++;
        const wrapper = document.getElementById('fp_section6_wrapper');
        const tmp = document.createElement('div');
        tmp.innerHTML = buildSection6SetHTML(fpSection6SetCount);
        const newSet = tmp.firstElementChild;
        wrapper.appendChild(newSet);
        // Skip scroll when loading data programmatically
        if (!window.fpSkipScroll) {
            newSet.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    // Event delegation for dynamically added section 6 sets
    document.addEventListener('click', function(e) {
        const set = e.target.closest('.fp-section6-set');
        if (!set || set.dataset.setIndex === '1') {
            // Handle remove button for non-first sets only
            if (e.target.classList.contains('fp-btn-remove-section6')) {
                const targetSet = e.target.closest('.fp-section6-set');
                if (targetSet && targetSet.dataset.setIndex !== '1') {
                    Swal.fire({
                        icon: 'warning', title: 'ยืนยันการลบ',
                        text: 'ต้องการลบชุดข้อมูลนี้ใช่หรือไม่?',
                        showCancelButton: true, confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก',
                        confirmButtonColor: '#dc3545'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            targetSet.remove();
                            reindexSection6Sets();
                        }
                    });
                }
                return;
            }
            if (set && set.dataset.setIndex === '1') return; // First set uses existing handlers
        }
        if (!set) return;

        // Remove section 6 set
        if (e.target.classList.contains('fp-btn-remove-section6')) {
            Swal.fire({
                icon: 'warning', title: 'ยืนยันการลบ',
                text: 'ต้องการลบชุดข้อมูลนี้ใช่หรือไม่?',
                showCancelButton: true, confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#dc3545'
            }).then(function(result) {
                if (result.isConfirmed) {
                    set.remove();
                    reindexSection6Sets();
                }
            });
            return;
        }

        // Add evidence card in set
        if (e.target.classList.contains('fp-btn-add-evidence-set')) {
            const container = set.querySelector('.fp-s6-evidence-container');
            const count = container.querySelectorAll('.fp-evidence-card').length + 1;
            const card = document.createElement('div');
            card.className = 'fpf-ev-card fp-evidence-card';
            card.innerHTML = `
                <div class="fpf-fr">
                    <span class="fpf-fl">รายการที่</span>
                    <span class="fp-evidence-badge" style="font-weight:600;">${count}</span>
                    <span class="fpf-fl" style="margin-left:4px;">เป็น</span>
                    <input type="text" class="fpf-inp" name="fp_ev_description[]">
                    <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    <button type="button" class="fpf-del-btn fp-btn-del-evidence-set" title="ลบ">✕</button>
                </div>
                <div class="fpf-fr">
                    <span class="fpf-fl">ขนาด ก/ผคก.</span>
                    <input type="text" class="fpf-inp-s" name="fp_ev_width[]">
                    <span class="fpf-fl">cm.</span>
                    <span class="fpf-fl">ย.</span>
                    <input type="text" class="fpf-inp-s" name="fp_ev_length[]">
                    <span class="fpf-fl">cm.</span>
                    <span class="fpf-fl">ส.</span>
                    <input type="text" class="fpf-inp-s" name="fp_ev_height[]">
                    <span class="fpf-fl">cm.</span>
                </div>
                <div class="fpf-fr">
                    <span class="fpf-fl">จำนวน</span>
                    <input type="text" class="fpf-inp" name="fp_ev_quantity[]" style="max-width:80px;">
                    <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    <span class="fpf-fl">ป้ายหมายเลข</span>
                    <input type="text" class="fpf-inp" name="fp_ev_label_no[]">
                    <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                </div>
                <div class="fpf-fr">
                    <span class="fpf-fl">การตรวจพิสูจน์</span>
                    <select class="fpf-sel lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="max-width:280px;">
                        <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                        <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                        <option value="fingerprint" selected>กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                        <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                        <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                        <option value="document">กลุ่มงานตรวจเอกสาร</option>
                    </select>
                    <input type="hidden" class="lab-unit-value" name="fp_ev_lab_unit[]" value="fingerprint">
                </div>
                `;
            container.appendChild(card);
            return;
        }

        // Delete evidence card in set
        if (e.target.classList.contains('fp-btn-del-evidence-set')) {
            const container = set.querySelector('.fp-s6-evidence-container');
            if (container.querySelectorAll('.fp-evidence-card').length <= 1) {
                Swal.fire({ icon: 'warning', title: 'ไม่สามารถลบได้', text: 'ต้องมีอย่างน้อย 1 รายการ', confirmButtonText: 'ตกลง' });
                return;
            }
            e.target.closest('.fp-evidence-card').remove();
            const badges = container.querySelectorAll('.fp-evidence-badge');
            badges.forEach((b, i) => b.textContent = i + 1);
            return;
        }

        // Add found item in set
        if (e.target.classList.contains('fp-btn-add-found-item-set')) {
            const si = set.dataset.setIndex;
            const container = set.querySelector('.fp-s6-found-items-container');
            const row = document.createElement('div');
            row.className = 'fpf-found-row fp-found-item-row';
            row.innerHTML = `<div class="fpf-fr">
                <span class="fpf-fl">จากวัตถุพยานรายการที่</span>
                <input type="text" class="fpf-inp" name="fp_found_from_item_s${si}[]" style="max-width:80px;">
                <span class="fpf-fl">จำนวน</span>
                <input type="number" class="fpf-inp-s" name="fp_found_item_sheets_s${si}[]" min="0" style="text-align:center;">
                <span class="fpf-fl">แผ่น</span>
                <button type="button" class="fpf-del-btn fp-btn-del-found-item-set" title="ลบ">✕</button>
            </div>`;
            container.appendChild(row);
            return;
        }

        // Delete found item in set
        if (e.target.classList.contains('fp-btn-del-found-item-set')) {
            const container = set.querySelector('.fp-s6-found-items-container');
            if (container.querySelectorAll('.fp-found-item-row').length <= 1) {
                Swal.fire({ icon: 'warning', title: 'ไม่สามารถลบได้', text: 'ต้องมีอย่างน้อย 1 รายการ', confirmButtonText: 'ตกลง' });
                return;
            }
            e.target.closest('.fp-found-item-row').remove();
            return;
        }

        // Add photograph row in set
        if (e.target.classList.contains('fp-btn-add-photograph-set')) {
            const si = set.dataset.setIndex;
            const container = set.querySelector('.fp-s6-photograph-container');
            const count = container.querySelectorAll('.fp-photograph-row').length + 1;
            const row = document.createElement('div');
            row.className = 'fpf-photo-row fp-photograph-row';
            row.innerHTML = `
                <span class="fp-photo-index" style="font-size:11px;min-width:18px;">${count})</span>
                <input type="text" class="fpf-inp" name="fp_photograph_desc_s${si}[]" placeholder="วัน/เดือน/ปี / รายละเอียด">
                <button type="button" class="fpf-del-btn fp-btn-del-photograph-set" title="ลบ">✕</button>`;
            container.appendChild(row);
            return;
        }

        // Delete photograph row in set
        if (e.target.classList.contains('fp-btn-del-photograph-set')) {
            const container = set.querySelector('.fp-s6-photograph-container');
            if (container.querySelectorAll('.fp-photograph-row').length <= 1) {
                Swal.fire({ icon: 'warning', title: 'ไม่สามารถลบได้', text: 'ต้องมีอย่างน้อย 1 รายการ', confirmButtonText: 'ตกลง' });
                return;
            }
            e.target.closest('.fp-photograph-row').remove();
            const indices = container.querySelectorAll('.fp-photo-index');
            indices.forEach((el, i) => el.textContent = (i + 1) + ')');
            return;
        }
    });

    // Event delegation for checkbox toggles in dynamic sets
    document.addEventListener('change', function(e) {
        const set = e.target.closest('.fp-section6-set');
        if (!set || set.dataset.setIndex === '1') return; // First set uses existing handlers

        // Toggle input visibility (color adj / resize)
        if (e.target.classList.contains('fp-s6-toggle-input')) {
            const targetClass = e.target.dataset.targetClass;
            const inp = e.target.closest('.fpf-cg').querySelector('.' + targetClass);
            if (inp) {
                inp.style.display = e.target.checked ? '' : 'none';
                inp.disabled = !e.target.checked;
                if (!e.target.checked) inp.value = '';
            }
            return;
        }

        // Radio-like result toggle
        if (e.target.classList.contains('fp-s6-radio-result')) {
            if (e.target.checked) {
                set.querySelectorAll('.fp-s6-radio-result').forEach(function(other) {
                    if (other !== e.target) other.checked = false;
                });
            }
            const notFoundCb = set.querySelector('.fp-s6-radio-result[value="ไม่พบรอยลายนิ้วมือแฝง"]');
            const foundCb = set.querySelector('.fp-s6-radio-result[value="พบรอยลายนิ้วมือแฝง"]');
            const nfWrapper = set.querySelector('.fp-s6-not-found-wrapper');
            const nfReason = set.querySelector('.fp-s6-not-found-reason');
            if (notFoundCb && notFoundCb.checked) {
                nfWrapper.style.display = ''; nfReason.disabled = false;
            } else {
                nfWrapper.style.display = 'none'; nfReason.disabled = true;
            }
            const fWrapper = set.querySelector('.fp-s6-found-wrapper');
            const fTotal = set.querySelector('.fp-s6-found-total');
            const fInputs = fWrapper.querySelectorAll('input, button');
            if (foundCb && foundCb.checked) {
                fWrapper.style.display = ''; fTotal.disabled = false;
                fInputs.forEach(el => el.disabled = false);
            } else {
                fWrapper.style.display = 'none'; fTotal.disabled = true;
                fInputs.forEach(el => el.disabled = true);
            }
            return;
        }

        // Photograph toggle
        if (e.target.classList.contains('fp-s6-has-photograph')) {
            const wrapper = set.querySelector('.fp-s6-photograph-wrapper');
            const inputs = wrapper.querySelectorAll('input, button');
            if (e.target.checked) {
                wrapper.style.display = '';
                inputs.forEach(el => el.disabled = false);
            } else {
                wrapper.style.display = 'none';
                inputs.forEach(el => el.disabled = true);
            }
            return;
        }
    });

    function reindexSection6Sets() {
        const sets = document.querySelectorAll('#fp_section6_wrapper .fp-section6-set');
        sets.forEach((set, i) => {
            const idx = i + 1;
            set.dataset.setIndex = idx;
            const label = set.querySelector('.fp-set-label');
            if (label) label.textContent = 'ชุดที่ ' + idx;
        });
        fpSection6SetCount = sets.length;
    }

    // Collect data from all section 6 sets
    function collectSection6SetData(set) {
        const si = set.dataset.setIndex;
        const setData = {
            set_index: si,
            evidence_total: '',
            evidence_items: [],
            global_methods: [],
            action_evidence_dest: [],
            action_evidence_other_text: '',
            action_exhibit: '',
            action_return_station: '',
            action_forward: false,
            action_forward_depts: [],
            action_forward_other_text: ''
        };

        // Evidence total
        const totalEl = set.querySelector('.fp-s6-evidence-total') || set.querySelector('#fp_evidence_total');
        if (totalEl) setData.evidence_total = totalEl.value;

        // Evidence items
        const evContainer = set.querySelector('.fp-s6-evidence-container') || set.querySelector('#fp_evidence_container');
        if (evContainer) {
            evContainer.querySelectorAll('.fp-evidence-card').forEach(card => {
                const item = {
                    description: (card.querySelector('[name="fp_ev_description[]"]') || {}).value || '',
                    width: (card.querySelector('[name="fp_ev_width[]"]') || {}).value || '',
                    length: (card.querySelector('[name="fp_ev_length[]"]') || {}).value || '',
                    height: (card.querySelector('[name="fp_ev_height[]"]') || {}).value || '',
                    quantity: (card.querySelector('[name="fp_ev_quantity[]"]') || {}).value || '',
                    label_no: (card.querySelector('[name="fp_ev_label_no[]"]') || {}).value || '',
                    lab_unit: window.getLabUnits(card.querySelector('[name="fp_ev_lab_unit[]"]')),
                    methods: []
                };
                card.querySelectorAll('.fp-method-row').forEach(row => {
                    const sel = row.querySelector('.fp-method-select');
                    const det = row.querySelector('.fp-method-detail-input');
                    if (sel && sel.value) {
                        item.methods.push({ name: sel.value, detail: det ? det.value : '' });
                    }
                });
                setData.evidence_items.push(item);
            });
        }

        // Actions
        set.querySelectorAll('input[name*="fp_action_evidence_dest"]:checked').forEach(cb => setData.action_evidence_dest.push(cb.value));
        const aeOther = set.querySelector('.fp-s6-action-evidence-other') || set.querySelector('#fp_action_evidence_other_text');
        if (aeOther) setData.action_evidence_other_text = (aeOther.value || '').trim();
        const exhibitCb = set.querySelector('input[name*="fp_action_exhibit"]:checked');
        if (exhibitCb) setData.action_exhibit = exhibitCb.value;
        const returnStation = set.querySelector('.fp-s6-return-station') || set.querySelector('#fp_action_return_station');
        if (returnStation) setData.action_return_station = (returnStation.value || '').trim();
        const forwardCb = set.querySelector('.fp-s6-forward-group') || set.querySelector('#fp_action_forward_group');
        setData.action_forward = forwardCb ? forwardCb.checked : false;
        set.querySelectorAll('input[name*="fp_action_forward_dept"]:checked').forEach(cb => setData.action_forward_depts.push(cb.value));
        const fwdOther = set.querySelector('.fp-s6-forward-other') || set.querySelector('#fp_action_forward_other_text');
        if (fwdOther) setData.action_forward_other_text = (fwdOther.value || '').trim();

        // Section 7 (global methods) + Section 8 (global actions) fallback for first set
        if (String(si) === '1') {
            const globalMethodContainer = document.getElementById('fp_method_global_container');
            if (globalMethodContainer) {
                globalMethodContainer.querySelectorAll('.fp-method-row').forEach(row => {
                    const sel = row.querySelector('.fp-method-select');
                    const det = row.querySelector('.fp-method-detail-input');
                    if (sel && sel.value) setData.global_methods.push({ name: sel.value, detail: det ? det.value : '' });
                });
            }

            if (setData.action_evidence_dest.length === 0) {
                document.querySelectorAll('input[name="fp_action_evidence_dest"]:checked').forEach(cb => setData.action_evidence_dest.push(cb.value));
            }
            if (!setData.action_exhibit) {
                const exhibitMain = document.querySelector('input[name="fp_action_exhibit"]:checked');
                if (exhibitMain) setData.action_exhibit = exhibitMain.value;
            }
            if (!setData.action_return_station) {
                const returnMain = document.getElementById('fp_action_return_station');
                if (returnMain) setData.action_return_station = (returnMain.value || '').trim();
            }
            if (setData.action_forward_depts.length === 0) {
                document.querySelectorAll('input[name="fp_action_forward_dept[]"]:checked').forEach(cb => setData.action_forward_depts.push(cb.value));
            }
            if (!setData.action_forward) {
                const forwardMain = document.getElementById('fp_action_forward_group');
                if (forwardMain) setData.action_forward = forwardMain.checked;
            }
            if (!setData.action_evidence_other_text) {
                const aeMain = document.getElementById('fp_action_evidence_other_text');
                if (aeMain) setData.action_evidence_other_text = (aeMain.value || '').trim();
            }
            if (!setData.action_forward_other_text) {
                const fwMain = document.getElementById('fp_action_forward_other_text');
                if (fwMain) setData.action_forward_other_text = (fwMain.value || '').trim();
            }
        }

        return setData;
    }

    // =================================================================
    // Collect data from section 9 (การถ่ายภาพวัตถุพยาน / ผลการตรวจเก็บ)
    // =================================================================
    function collectSection9DataFP() {
        const wrapper = document.getElementById('fp_section9_wrapper');
        if (!wrapper) return {};

        const s9 = {
            photo_types: [],
            photo_sides: [],
            color_adj_detail: '',
            resize_detail: '',
            result_type: '',
            not_found_reason: '',
            found_total_sheets: '',
            found_items: [],
            has_photograph: false,
            photographs: []
        };

        wrapper.querySelectorAll('input[type="checkbox"][name*="fp_photo_type"]:checked').forEach(cb => s9.photo_types.push(cb.value));
        wrapper.querySelectorAll('input[type="checkbox"][name*="fp_photo_side"]:checked').forEach(cb => s9.photo_sides.push(cb.value));

        const colorAdj = wrapper.querySelector('#fp_color_adj_detail');
        if (colorAdj) s9.color_adj_detail = (colorAdj.value || '').trim();
        const resize = wrapper.querySelector('#fp_resize_detail');
        if (resize) s9.resize_detail = (resize.value || '').trim();

        const checkedResult = wrapper.querySelector('.fp-radio-result:checked');
        if (checkedResult) s9.result_type = checkedResult.value;
        if (s9.result_type === 'ไม่พบรอยลายนิ้วมือแฝง') {
            const reason = wrapper.querySelector('#fp_not_found_reason');
            if (reason) s9.not_found_reason = reason.value;
        }
        if (s9.result_type === 'พบรอยลายนิ้วมือแฝง') {
            const totalSheets = wrapper.querySelector('#fp_found_total_sheets');
            if (totalSheets) s9.found_total_sheets = totalSheets.value;
            wrapper.querySelectorAll('.fp-found-item-row').forEach(row => {
                const fromItem = row.querySelector('input[name*="fp_found_from_item"]');
                const sheets = row.querySelector('input[name*="fp_found_item_sheets"]');
                s9.found_items.push({ from_item: fromItem ? fromItem.value : '', sheets: sheets ? sheets.value : '' });
            });
        }

        const photoChk = wrapper.querySelector('#fp_has_photograph');
        s9.has_photograph = photoChk ? photoChk.checked : false;
        if (s9.has_photograph) {
            wrapper.querySelectorAll('input[name*="fp_photograph_desc"]').forEach(input => {
                s9.photographs.push(input.value);
            });
        }

        return s9;
    }

    // =================================================================
    // Submit / Collect Data
    // =================================================================
    window.prepareDataForSubmissionFingerprint = function() {
        const form = document.getElementById('incidentCheckListFormFingerprint');
        if (!form) return;

        const incidentIdEl = document.getElementById('receiveNoti_id_fp');
        if (!incidentIdEl || !(incidentIdEl.value || '').trim()) {
            Swal.fire({ icon: 'warning', title: 'ไม่พบรหัสคดี', text: 'กรุณาเปิดฟอร์มจากรายการคดีก่อนบันทึก', confirmButtonText: 'ตกลง' });
            return;
        }

        const data = {
            receiveNoti_id: document.getElementById('receiveNoti_id_fp').value,
            doc_no: document.getElementById('doc_no_fp').value,
            report_no: document.getElementById('report_no_fp').value,
            receive_date: document.getElementById('fp_receive_date').value,
            receive_time: document.getElementById('fp_receive_time').value,
            case_no: document.getElementById('fp_case_no').value,
            police_station: document.getElementById('fp_police_station').value,
            letter_no: document.getElementById('fp_letter_no').value,
            letter_date: document.getElementById('fp_letter_date').value,
            evidence_sender: (document.getElementById('fp_evidence_sender') || {}).value || '',
            evidence_sender_position: (document.getElementById('fp_evidence_sender_position') || {}).value || '',
            evidence_sender_phone: (document.getElementById('fp_evidence_sender_phone') || {}).value || '',
            evidence_letter_no: (document.getElementById('fp_evidence_letter_no') || {}).value || '',
            evidence_doc_no: (document.getElementById('fp_evidence_doc_no') || {}).value || '',
            evidence_doc_date: (document.getElementById('fp_evidence_doc_date') || {}).value || '',
            purposes: [],
            purpose_other_text: (document.getElementById('fp_purpose_other_text') || {}).value || '',
            incident_date: document.getElementById('fp_incident_date').value,
            incident_time: document.getElementById('fp_incident_time').value,
            known_date: document.getElementById('fp_known_date').value,
            known_time: document.getElementById('fp_known_time').value,
            collect_date: document.getElementById('fp_collect_date').value,
            collect_time: document.getElementById('fp_collect_time').value,
            inspectors: [],
            package_types: [],
            seal_conditions: [],
            collector_type: '',
            collector_other_text: ((document.getElementById('fp_collector_other_text') || {}).value || '').trim(),
            storage_date: document.getElementById('fp_storage_date').value,
            storage_time: document.getElementById('fp_storage_time').value,
            duration_year: document.getElementById('fp_duration_year').value,
            duration_month: document.getElementById('fp_duration_month').value,
            duration_day: document.getElementById('fp_duration_day').value,
            section6_sets: [],
            section9: {},
            // Photo page fields
            photo_inspect_date: (document.querySelector('[name="fp_photo_inspect_date"]') || {}).value || '',
            photo_inspect_time: (document.querySelector('[name="fp_photo_inspect_time"]') || {}).value || '',
            photo_id_start: (document.getElementById('fp_photo_id_start') || {}).value || '',
            photo_id_end: (document.getElementById('fp_photo_id_end') || {}).value || '',
            photo_amount: (document.getElementById('fp_photo_amount') || {}).value || '',
            photographer_name: (document.getElementById('fp_photographer_name') || {}).value || '',
            photographer_datetime: (document.getElementById('fp_photographer_datetime') || {}).value || ''
        };

        // Inspectors
        document.querySelectorAll('#fp_inspector_container .fp-inspector-select').forEach(sel => {
            if (sel.value) data.inspectors.push(sel.value);
        });

        // Package types
        document.querySelectorAll('input[name="fp_package_type[]"]:checked').forEach(cb => {
            data.package_types.push(cb.value);
        });

        // Seal conditions
        document.querySelectorAll('input[name="fp_seal_condition[]"]:checked').forEach(cb => {
            data.seal_conditions.push(cb.value);
        });

        // Purposes
        document.querySelectorAll('input[name="fp_purpose[]"]:checked').forEach(cb => {
            data.purposes.push(cb.value);
        });

        // Collector type
        const checkedCollector = document.querySelector('.fp-radio-collector:checked');
        if (checkedCollector) data.collector_type = checkedCollector.value;

        // Collect data from all section 6 sets
        document.querySelectorAll('#fp_section6_wrapper .fp-section6-set').forEach(set => {
            data.section6_sets.push(collectSection6SetData(set));
        });

        // Collect section 9 data
        data.section9 = collectSection9DataFP();

        Swal.fire({
            title: 'ยืนยันการบันทึกข้อมูล',
            text: 'กรุณาตรวจสอบความถูกต้องก่อนบันทึก',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#d33',
            confirmButtonText: 'ยืนยัน, บันทึกเลย!',
            cancelButtonText: 'ยกเลิก',
        }).then(async function(result) {
            if (result.isConfirmed) {
                // Collect photo captions from slots
                var captions = [];
                if (typeof attachmentStoreFP !== 'undefined') {
                    attachmentStoreFP.forEach(function(item) {
                        captions.push(item.caption || '');
                    });
                }
                data.photo_captions = captions;

                var submitData = new FormData();
                submitData.append('payload', JSON.stringify(data));

                // Append new photo files from shared store
                if (typeof attachmentStoreFP !== 'undefined') {
                    attachmentStoreFP.forEach(function(item) {
                        if (item.file && !item.existing) {
                            submitData.append('incident_photos_fp[]', item.file);
                        }
                    });
                }

                // Append deleted photo file_ids
                if (typeof deletedExistingPhotosFP !== 'undefined' && deletedExistingPhotosFP.length > 0) {
                    submitData.append('deleted_photo_file_ids', JSON.stringify(deletedExistingPhotosFP));
                }

                var fpUrl = './api/incidentCheckList/saveFingerprint.php';
                if (!navigator.onLine) {
                    await saveChecklistOffline(submitData, fpUrl, '#addCheckListModalFingerprint');
                    return;
                }
                var backendOkFP = await checkBackendHealth();
                if (!backendOkFP) {
                    await saveChecklistOffline(submitData, fpUrl, '#addCheckListModalFingerprint');
                    return;
                }

                $.ajax({
                    url: fpUrl,
                    method: 'POST',
                    data: submitData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({ icon: 'success', title: 'สำเร็จ', text: response.message || 'บันทึกข้อมูลเรียบร้อย', confirmButtonText: 'ตกลง' }).then(function() {
                                const modal = bootstrap.Modal.getInstance(document.getElementById('addCheckListModalFingerprint'));
                                if (modal) modal.hide();
                                if (typeof resetFingerprintForm === 'function') resetFingerprintForm();
                                if (typeof loadData === 'function') loadData();
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message || 'ไม่สามารถบันทึกได้', confirmButtonText: 'ตกลง' });
                        }
                    },
                    error: async function(xhr) {
                        console.error('Save fingerprint error:', xhr);
                        await saveChecklistOffline(submitData, fpUrl, '#addCheckListModalFingerprint');
                    }
                });
            }
        });
    };

    // =================================================================
    // Reset Form
    // =================================================================
    window.resetFingerprintForm = function() {
        const form = document.getElementById('incidentCheckListFormFingerprint');
        if (!form) return;
        form.reset();

        // Reset inspectors to 1
        const inspectorContainer = document.getElementById('fp_inspector_container');
        const firstInspector = inspectorContainer.querySelector('.fp-inspector-row');
        inspectorContainer.innerHTML = '';
        if (firstInspector) {
            firstInspector.querySelector('.fp-index-label').textContent = '4.1';
            const delBtn = firstInspector.querySelector('.fpf-del-btn');
            if (delBtn) delBtn.remove();
            inspectorContainer.appendChild(firstInspector);
        }
        fpInspectorIndex = 1;

        // Reset evidence to 1 (keep first card, reset methods to 1 row)
        const evContainer = document.getElementById('fp_evidence_container');
        const firstEv = evContainer.querySelector('.fp-evidence-card');
        evContainer.innerHTML = '';
        if (firstEv) {
            firstEv.querySelector('.fp-evidence-badge').textContent = '1';
            const mc = firstEv.querySelector('.fp-method-container');
            if (mc) {
                const firstMethod = mc.querySelector('.fp-method-row');
                mc.innerHTML = '';
                if (firstMethod) {
                    const delBtn = firstMethod.querySelector('.fpf-del-btn');
                    if (delBtn) delBtn.remove();
                    mc.appendChild(firstMethod);
                }
            }
            evContainer.appendChild(firstEv);
        }
        fpEvidenceIndex = 1;

        // Reset found items to 1
        const foundContainer = document.getElementById('fp_found_items_container');
        const firstFound = foundContainer.querySelector('.fp-found-item-row');
        foundContainer.innerHTML = '';
        if (firstFound) foundContainer.appendChild(firstFound);
        fpFoundItemIndex = 1;

        // Reset photographs to 1
        const photoContainer = document.getElementById('fp_photograph_container');
        const firstPhoto = photoContainer.querySelector('.fp-photograph-row');
        photoContainer.innerHTML = '';
        if (firstPhoto) {
            firstPhoto.querySelector('.fp-photo-index').textContent = '1)';
            photoContainer.appendChild(firstPhoto);
        }
        fpPhotoIndex = 1;

        // Hide conditional sections
        document.getElementById('fp_collector_other_text').style.display = 'none';
        document.getElementById('fp_collector_other_text').disabled = true;
        document.getElementById('fp_color_adj_detail').style.display = 'none';
        document.getElementById('fp_color_adj_detail').disabled = true;
        document.getElementById('fp_resize_detail').style.display = 'none';
        document.getElementById('fp_resize_detail').disabled = true;
        document.getElementById('fp_not_found_detail_wrapper').style.display = 'none';
        document.getElementById('fp_not_found_reason').disabled = true;
        document.getElementById('fp_found_detail_wrapper').style.display = 'none';
        document.getElementById('fp_photograph_wrapper').style.display = 'none';

        // Reset section 7 global method rows to 1 empty row
        const globalMethodContainer = document.getElementById('fp_method_global_container');
        if (globalMethodContainer) {
            const firstMethodRow = globalMethodContainer.querySelector('.fp-method-row');
            globalMethodContainer.innerHTML = '';
            if (firstMethodRow) {
                const sel = firstMethodRow.querySelector('.fp-method-select');
                if (sel) sel.selectedIndex = 0;
                const det = firstMethodRow.querySelector('.fp-method-detail-input');
                if (det) det.value = '';
                const delBtn = firstMethodRow.querySelector('.fpf-del-btn');
                if (delBtn) delBtn.remove();
                globalMethodContainer.appendChild(firstMethodRow);
            }
        }

        // Remove extra section 6 sets (keep only the first)
        const wrapper = document.getElementById('fp_section6_wrapper');
        const allSets = wrapper.querySelectorAll('.fp-section6-set');
        allSets.forEach((set, i) => {
            if (i > 0) set.remove();
        });
        fpSection6SetCount = 1;
        const firstSet = wrapper.querySelector('.fp-section6-set');
        if (firstSet) firstSet.dataset.setIndex = '1';
        const firstLabel = wrapper.querySelector('.fp-set-label');
        if (firstLabel) firstLabel.textContent = 'ชุดที่ 1';

        // Reset dates
        const today = '<?= $todayDateFP ?>';
        const nowTime = '<?= $todayTimeFP ?>';
        ['fp_receive_date', 'fp_incident_date', 'fp_known_date', 'fp_collect_date'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = today;
        });
        ['fp_receive_time', 'fp_incident_time', 'fp_known_time', 'fp_collect_time'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = nowTime;
        });
    };

    // =================================================================
    // ผู้ส่งของกลาง - Auto-fill ตำแหน่ง
    // =================================================================
    const senderEl = document.getElementById('fp_evidence_sender');
    if (senderEl) {
        senderEl.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const pos = opt ? (opt.getAttribute('data-position') || '') : '';
            document.getElementById('fp_evidence_sender_position').value = pos;
        });
    }

    // =================================================================
    // Phone format: xxx-xxx-xxxx
    // =================================================================
    document.querySelectorAll('#addCheckListModalFingerprint .fpf-phone-format').forEach(function(el) {
        el.addEventListener('input', function() {
            let v = this.value.replace(/\D/g, '').substring(0, 10);
            if (v.length > 6) v = v.slice(0,3) + '-' + v.slice(3,6) + '-' + v.slice(6);
            else if (v.length > 3) v = v.slice(0,3) + '-' + v.slice(3);
            this.value = v;
        });
    });

})();

// =================================================================
// Photo Page Functions (ฟอร์มเสมือนจริง - ลายนิ้วมือแฝง)
// Uses shared `attachmentStoreFP` from incidentChecklist.php
// =================================================================

// ===== Photo source chooser (แนบรูป / ถ่ายรูป) =====
window.fpChoosePhotoSource = function() {
    Swal.fire({
        title: 'เลือกแหล่งรูปภาพ',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-folder-open me-1"></i> แนบรูปภาพ',
        cancelButtonText: '<i class="fas fa-camera me-1"></i> ถ่ายรูป',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#198754',
        reverseButtons: true
    }).then(function(result) {
        if (result.isConfirmed) {
            document.getElementById('fp_photo_input_gallery').click();
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            document.getElementById('fp_photo_input_camera').click();
        }
    });
};

// ===== Photo preview (add to shared store) =====
window.fpPhotoPreview = function(input) {
    if (!input.files || !input.files.length) return;
    fpHandlePhotoFiles(input.files);
    input.value = '';
};

function fpHandlePhotoFiles(files) {
    if (!files || !files.length) return;
    if (typeof attachmentStoreFP === 'undefined') window.attachmentStoreFP = [];
    Array.from(files).forEach(function(file) {
        if (!file.type.startsWith('image/')) return;
        var fileId = 'fp_' + Date.now() + '_' + Math.random().toString(36).substr(2,5);
        var objectUrl = URL.createObjectURL(file);
        attachmentStoreFP.push({ file: file, id: fileId, src: objectUrl, name: file.name });
    });
    fpPhotoRenderFromStore();
    if (typeof updateFPRealInput === 'function') updateFPRealInput();
}

// ===== Drag & Drop + Click handlers for dropzone =====
var fpPhotoDZ = document.getElementById('fp_photo_dropzone');
if (fpPhotoDZ) {
    fpPhotoDZ.addEventListener('click', function() {
        fpChoosePhotoSource();
    });
    fpPhotoDZ.addEventListener('dragenter', function(e) { e.preventDefault(); this.classList.add('dragover'); });
    fpPhotoDZ.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('dragover'); });
    fpPhotoDZ.addEventListener('dragleave', function(e) { e.preventDefault(); this.classList.remove('dragover'); });
    fpPhotoDZ.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        if (e.dataTransfer && e.dataTransfer.files.length > 0) {
            fpHandlePhotoFiles(e.dataTransfer.files);
        }
    });
}

// ===== Manual add page button (auto-pagination handled by render) =====
window.fpPhotoAddPage = function() {
    fpPhotoRenderFromStore();
};

// ===== ลบหน้ากระดาษรูปถ่ายเพิ่มเติม =====
window.fpPhotoRemovePage = function(btn) {
    var page = btn.closest('.fpf-photo-page');
    if (page) {
        page.remove();
        fpPhotoPageCount--;
        fpPhotoRenderFromStore();
        if (typeof fpfUpdatePageNumbers === 'function') fpfUpdatePageNumbers();
    }
};

// ===== Render photos จาก attachmentStoreFP ลง grid (35 รูป/หน้า) =====
var FP_PHOTOS_PER_PAGE = 35;

window.fpPhotoRenderFromStore = function() {
    var photos = (typeof attachmentStoreFP !== 'undefined') ? attachmentStoreFP : [];
    var pagesNeeded = Math.max(1, Math.ceil(photos.length / FP_PHOTOS_PER_PAGE));
    console.log('[FP] fpPhotoRenderFromStore called, photos:', photos.length, 'pagesNeeded:', pagesNeeded);

    // Render first page grid
    var grid1 = document.getElementById('fp_photo_grid_1');
    if (grid1) {
        grid1.innerHTML = '';
        var endIdx = Math.min(FP_PHOTOS_PER_PAGE, photos.length);
        for (var i = 0; i < endIdx; i++) {
            grid1.appendChild(fpCreatePhotoCell(photos[i], i));
        }
    }

    // Render extra pages
    var extraContainer = document.getElementById('fp_extra_photo_pages');
    if (extraContainer) {
        extraContainer.innerHTML = '';
        for (var p = 2; p <= pagesNeeded; p++) {
            var startIdx = (p - 1) * FP_PHOTOS_PER_PAGE;
            var endIdx = Math.min(p * FP_PHOTOS_PER_PAGE, photos.length);
            extraContainer.appendChild(fpCreatePhotoPageElement(p, photos, startIdx, endIdx));
        }
    }

    fpUpdatePhotoAmount();
    if (typeof fpfUpdatePageNumbers === 'function') fpfUpdatePageNumbers();
    if (typeof updateFPRealInput === 'function') updateFPRealInput();
};

function fpCreatePhotoCell(item, idx) {
    var wrapper = document.createElement('div');
    wrapper.style.cssText = 'display:flex; flex-direction:column;';
    
    var cell = document.createElement('div');
    cell.className = 'fpf-photo-cell';
    cell.style.position = 'relative';
    
    var img = document.createElement('img');
    img.src = item.src || item.base64 || '';
    img.alt = item.name || 'photo';
    cell.appendChild(img);
    
    var delBtn = document.createElement('button');
    delBtn.type = 'button';
    delBtn.className = 'fpf-cell-delete';
    delBtn.innerHTML = '&times;';
    delBtn.onclick = function(e) {
        e.stopPropagation();
        fpPhotoRemove(item.id);
    };
    cell.appendChild(delBtn);
    
    wrapper.appendChild(cell);
    
    var fname = document.createElement('div');
    fname.className = 'fpf-cell-filename';
    fname.textContent = item.name || 'photo';
    fname.title = item.name || 'photo';
    wrapper.appendChild(fname);
    
    return wrapper;
}

function fpCreatePhotoPageElement(pageNum, photos, startIdx, endIdx) {
    var page = document.createElement('div');
    page.className = 'fpf-page fpf-photo-page';
    page.setAttribute('data-photo-page', pageNum);
    
    var fpDocNo = document.getElementById('doc_no_fp') ? document.getElementById('doc_no_fp').value : '';
    var fpDocYear = <?= substr((date('Y') + 543), -2) ?>;
    
    var startName = photos[startIdx] ? (photos[startIdx].name || 'photo') : '';
    var endName = photos[endIdx - 1] ? (photos[endIdx - 1].name || 'photo') : '';
    
    page.innerHTML =
        '<div class="fpf-header">' +
            '<div class="fpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>' +
            '<div class="fpf-header-center">' +
                '<div class="fpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>' +
                '<div class="fpf-title-sub">การตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)</div>' +
                '<div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ (ต่อ)</div>' +
            '</div>' +
            '<div class="fpf-header-right">' +
                '<div class="fpf-doc-box">' +
                    '<div class="fpf-doc-line">รายงานที่ <span style="display:inline-block;min-width:60px;border-bottom:1px dotted #888;text-align:center;">' + fpDocNo + '</span> / 25<span style="display:inline-block;min-width:30px;border-bottom:1px dotted #888;text-align:center;">' + fpDocYear + '</span></div>' +
                    '<div class="fpf-doc-line">หน้าที่ <span class="fpf-cur-page"></span> / <span class="fpf-total-page"></span></div>' +
                '</div>' +
            '</div>' +
        '</div>' +
        '<div style="margin-bottom:4px;">' +
            '<div class="fpf-fr" style="flex-wrap:nowrap;">' +
                '<span class="fpf-fl">รหัสภาพถ่ายที่</span>' +
                '<span class="fpf-inp" style="flex:1; min-width:40px; text-align:center;">' + startName + '</span>' +
                '<span class="fpf-fl">ถึง</span>' +
                '<span class="fpf-inp" style="flex:1; min-width:40px; text-align:center;">' + endName + '</span>' +
            '</div>' +
            '<div style="font-size:10.5px; font-style:italic; margin-top:1px;">(ตามภาพถ่ายรวมที่แนบ)</div>' +
        '</div>';
    
    var grid = document.createElement('div');
    grid.className = 'fpf-photo-grid';
    for (var i = startIdx; i < endIdx; i++) {
        grid.appendChild(fpCreatePhotoCell(photos[i], i));
    }
    page.appendChild(grid);
    
    var footer = document.createElement('div');
    footer.className = 'fpf-footer';
    footer.style.marginTop = 'auto';
    footer.innerHTML =
        '<div class="fpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>' +
        '<div class="fpf-footer-right">F-CS-XX แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>';
    page.appendChild(footer);
    
    return page;
}

window.fpUpdatePhotoAmount = function() {
    var photos = (typeof attachmentStoreFP !== 'undefined' && Array.isArray(attachmentStoreFP)) ? attachmentStoreFP : [];
    var totalPhotos = photos.length;

    var startInput = document.getElementById('fp_photo_id_start');
    var endInput = document.getElementById('fp_photo_id_end');
    var amountInput = document.getElementById('fp_photo_amount');

    if (startInput && endInput && amountInput) {
        if (totalPhotos > 0) {
            var lastIdxPage1 = Math.min(FP_PHOTOS_PER_PAGE, totalPhotos) - 1;
            startInput.value = photos[0].name || 'photo';
            endInput.value = photos[lastIdxPage1].name || 'photo';
            amountInput.value = String(totalPhotos);
        } else {
            startInput.value = '';
            endInput.value = '';
            amountInput.value = '';
        }
    }
};

// ===== ลบรูปจาก store =====
window.fpPhotoRemove = function(fileId) {
    if (typeof attachmentStoreFP === 'undefined') return;
    var item = attachmentStoreFP.find(function(x) { return x.id === fileId; });
    if (item && item.existing && item.db_file_id && typeof deletedExistingPhotosFP !== 'undefined') {
        deletedExistingPhotosFP.push(item.db_file_id);
    }
    attachmentStoreFP = attachmentStoreFP.filter(function(x) { return x.id !== fileId; });
    fpPhotoRenderFromStore();
    if (typeof updateFPRealInput === 'function') updateFPRealInput();
};
</script>
