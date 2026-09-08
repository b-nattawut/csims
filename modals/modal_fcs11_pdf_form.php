<?php
/**
 * Modal: แบบการตรวจเก็บและส่งมอบวัตถุพยาน (F-CS-11) - ฟอร์มเสมือน PDF
 * Prefix ID: rpf_  (report pdf form)
 * Modal ID: reportFormPdfModal   Form ID: reportFormPdf
 * Based on: api/incidentCheckList/form_report_preview.html
 */
date_default_timezone_set('Asia/Bangkok');

$rpfPoliceStationOptions = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qryRpfPS = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stmtRpfPS = $pdo->query($qryRpfPS);
    while ($rowPS = $stmtRpfPS->fetch(PDO::FETCH_ASSOC)) {
        $rpfPoliceStationOptions .= '<option value="' . htmlspecialchars($rowPS['station_name']) . '">' . htmlspecialchars($rowPS['station_name']) . '</option>';
    }
}

$rpfInspectorOptions = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
$rpfCollectorOptions = '<option value="" selected disabled>-- เลือกผู้จดบันทึก --</option>';
if (isset($pdo)) {
    $qryRpfInsp = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                   FROM user_profile t1 
                   LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                   ORDER BY t1.user_id DESC";
    $stmtRpfInsp = $pdo->query($qryRpfInsp);
    while ($rowInsp = $stmtRpfInsp->fetch(PDO::FETCH_ASSOC)) {
        $rpfInspectorOptions .= '<option value="' . $rowInsp['user_id'] . '">' . htmlspecialchars($rowInsp['fullname']) . '</option>';
        $rpfCollectorOptions .= '<option value="' . htmlspecialchars($rowInsp['fullname']) . '">' . htmlspecialchars($rowInsp['fullname']) . '</option>';
    }
}

$rpfTodayDate = date('Y-m-d');
$rpfTodayTime = date('H:i');
?>

<!-- ===== SCOPED CSS ===== -->
<style>
/* hwpen button */
#reportFormPdfModal .btn-hw-open {
    flex-shrink: 0; min-width: 18px; padding: 0 4px;
    border: none; background: none; color: #6366f1;
    font-size: 0.7rem; cursor: pointer; line-height: 1.5;
}
#reportFormPdfModal .btn-hw-open:hover { color: #4338ca; transform: scale(1.15); }

#reportFormPdfModal .btn-purple {
    background-color: #8b5cf6;
    border-color: #8b5cf6;
    color: #fff;
}
#reportFormPdfModal .btn-purple:hover {
    background-color: #7c3aed;
    border-color: #7c3aed;
    color: #fff;
}

#reportFormPdfModal .rpf-body {
    background: #bbb;
    padding: 10px 0;
}

#reportFormPdfModal .rpf-page {
    width: 210mm;
    min-height: 297mm;
    margin: 10px auto;
    padding: 12mm 15mm 10mm 15mm;
    background: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.25);
    position: relative;
    overflow: visible;
    display: flex;
    flex-direction: column;
    font-family: 'Sarabun', sans-serif;
    font-size: 14px;
    line-height: 1.6;
    color: #000;
}

/* ===== HEADER ===== */
#reportFormPdfModal .rpf-form-header {
    text-align: center;
    margin-bottom: 4px;
}
#reportFormPdfModal .rpf-form-header img {
    width: 90px;
    height: auto;
}

#reportFormPdfModal .rpf-report-no-line {
    text-align: right;
    font-size: 14px;
    margin-bottom: 2px;
    line-height: 1.8;
}

#reportFormPdfModal .rpf-form-title {
    text-align: center;
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 4px;
}

#reportFormPdfModal .rpf-right-line {
    text-align: right;
    font-size: 14px;
    margin-bottom: 2px;
    line-height: 1.8;
}

/* ===== INPUT FIELDS ===== */
#reportFormPdfModal .rpf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 2px; height: 24px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px;
}
#reportFormPdfModal .rpf-inp-m {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 2px; height: 24px; outline: none; color: #000;
    flex: 1; min-width: 60px; margin: 0 2px; text-align: center;
}
#reportFormPdfModal .rpf-inp-s {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 2px; height: 24px; outline: none; color: #000;
    min-width: 15px; max-width: 60px; margin: 0 2px; flex: 0 1 50px; text-align: center;
}
#reportFormPdfModal .rpf-inp-long {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 2px; height: 24px; outline: none; color: #000;
    min-width: 120px; margin: 0 2px; text-align: center;
}

/* SELECT styled like dotted line */
#reportFormPdfModal .rpf-sel {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0; height: 24px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px; cursor: pointer;
}

/* ===== FLEX ROW ===== */
#reportFormPdfModal .rpf-fr {
    display: flex;
    align-items: baseline;
    line-height: 2.2;
    width: 100%;
    flex-wrap: nowrap;
    margin: 8px 0;
}

/* ===== FIELD LABEL ===== */
#reportFormPdfModal .rpf-fl {
    font-size: 14px;
    white-space: nowrap;
    flex-shrink: 0;
    line-height: 1.35;
    padding-bottom: 1px;
}

/* ===== CHECKBOX ===== */
#reportFormPdfModal .rpf-cb {
    appearance: none; -webkit-appearance: none;
    width: 14px; height: 14px; border: 1.5px solid #000;
    margin-right: 3px; cursor: pointer; position: relative;
    vertical-align: middle; flex-shrink: 0; background: #fff;
    border-radius: 2px;
}
#reportFormPdfModal .rpf-cb:checked::after {
    content: '✓'; font-size: 11px; font-weight: 700;
    position: absolute; top: 50%; left: 50%; 
    transform: translate(-50%, -50%);
    color: #000; line-height: 1;
}
#reportFormPdfModal .rpf-ck {
    display: inline-flex; align-items: center; margin-right: 10px;
    font-size: 14px; white-space: nowrap; vertical-align: middle; cursor: pointer;
}

/* ===== CONTENT SECTION ===== */
#reportFormPdfModal .rpf-content-section {
    font-size: 14px;
    margin-bottom: 10px;
}
#reportFormPdfModal .rpf-content-section + .rpf-content-section {
    margin-top: -9px;
}
#reportFormPdfModal .rpf-content-section > .rpf-fr:first-child { margin-top: 0; }
#reportFormPdfModal .rpf-content-section > .rpf-fr:last-child { margin-bottom: 0; }

/* ===== EVIDENCE TABLE ===== */
#reportFormPdfModal .rpf-ev-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
    margin-bottom: 4px;
}
#reportFormPdfModal .rpf-ev-table th,
#reportFormPdfModal .rpf-ev-table td {
    border: 1px solid #000;
    padding: 3px 6px;
    font-size: 13px;
    text-align: center;
}
#reportFormPdfModal .rpf-ev-table th {
    font-weight: 700;
    background-color: #fff;
}
#reportFormPdfModal .rpf-ev-table td.td-left {
    text-align: left;
}
#reportFormPdfModal .rpf-ev-table input[type="text"] {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 13px; width: 100%;
    padding: 1px 2px; outline: none; text-align: center;
}

/* ===== SIGNATURE SECTION ===== */
#reportFormPdfModal .rpf-signature-block {
    margin-top: 8px;
    text-align: center;
    padding-left: 50%;
    line-height: 1.6;
}
#reportFormPdfModal .rpf-sig-box {
    border: 1px solid #999; background: #fff; 
    height: 90px; width: 220px;
    cursor: crosshair; position: relative; display: inline-block;
    margin: 0 8px; vertical-align: middle;
}
#reportFormPdfModal .rpf-sig-box canvas {
    width: 100%; height: 100%; display: block;
}
#reportFormPdfModal .rpf-sig-clear-btn {
    position: absolute; top: -8px; right: -8px;
    width: 18px; height: 18px; border-radius: 50%;
    border: 1px solid #ccc; background: #fff; color: #c00;
    font-size: 12px; line-height: 16px; text-align: center;
    cursor: pointer; padding: 0; z-index: 2; opacity: 0.7;
    font-family: 'Sarabun', sans-serif;
}
#reportFormPdfModal .rpf-sig-clear-btn:hover { opacity: 1; background: #fee; }

/* ===== Evidence Test Dropdown ===== */
#reportFormPdfModal .rpf-ev-cell {
    display: flex; align-items: center; gap: 4px;
}
#reportFormPdfModal .rpf-ev-select {
    border: none; border-bottom: 1px dotted #888; 
    background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 13px; 
    flex: 1; padding: 1px 2px; outline: none; 
    text-align: center; cursor: pointer;
    color: #d35400; height: 24px;
}

/* ===== FOOTER ===== */
#reportFormPdfModal .rpf-form-footer {
    text-align: right;
    font-size: 10px;
    margin-top: auto;
    padding-top: 8px;
    flex-shrink: 0;
}

/* ===== Add/Remove buttons ===== */
#reportFormPdfModal .rpf-add-btn {
    font-size: 10px; padding: 1px 8px; border: 1px dashed #888;
    background: #f8f8f8; cursor: pointer; color: #333; margin: 3px 0;
    font-family: 'Sarabun', sans-serif;
}
#reportFormPdfModal .rpf-add-btn:hover { background: #e0e0e0; }
#reportFormPdfModal .rpf-del-btn {
    font-size: 9px; padding: 0 4px; border: 1px solid #ccc;
    background: #fff; cursor: pointer; color: #c00;
    font-family: 'Sarabun', sans-serif; line-height: 1.5;
}
#reportFormPdfModal .rpf-del-btn:hover { background: #fee; }

/* ===== Datetime helpers ===== */
#reportFormPdfModal .rpf-fr-datetime {
    align-items: baseline;
    flex-wrap: nowrap;
    column-gap: 4px;
    line-height: inherit;
}
#reportFormPdfModal .rpf-datetime-left {
    display: flex; align-items: baseline; flex: 1 1 auto; min-width: 0;
}
#reportFormPdfModal .rpf-datetime-right {
    display: flex; align-items: baseline; flex: 0 0 auto; margin-left: auto; white-space: nowrap;
}
#reportFormPdfModal .rpf-fr-victim {
    align-items: baseline;
    column-gap: 6px;
    line-height: inherit;
}
#reportFormPdfModal .rpf-victim-left {
    display: flex; align-items: baseline; flex: 1 1 auto; min-width: 0;
}
#reportFormPdfModal .rpf-victim-right {
    display: flex; align-items: baseline; flex: 0 0 auto; white-space: nowrap;
}

/* ===== PRINT ===== */
@media print {
    body > *:not(#reportFormPdfModal),
    .navbar, .sidebar, .main-content, #status-banner,
    .modal-backdrop, .swal2-container,
    #reportFormPdfModal .modal-header,
    #reportFormPdfModal .modal-footer,
    #reportFormPdfModal .csims-loading-overlay,
    #reportFormPdfModal .rpf-add-btn,
    #reportFormPdfModal .rpf-del-btn,
    #reportFormPdfModal .rpf-sig-clear-btn,
    #reportFormPdfModal .btn-hw-open,
    #reportFormPdfModal .form-check.form-switch,
    #reportFormPdfModal .d-flex.align-items-center.gap-3 {
        display: none !important;
    }
    #reportFormPdfModal,
    #reportFormPdfModal .modal-dialog,
    #reportFormPdfModal .modal-content,
    #reportFormPdfModal .rpf-body {
        position: static !important; display: block !important;
        width: auto !important; max-width: none !important;
        max-height: none !important; height: auto !important;
        overflow: visible !important; margin: 0 !important;
        padding: 0 !important; background: #fff !important;
        border: none !important; box-shadow: none !important;
        transform: none !important; opacity: 1 !important;
    }
    @page { size: A4 portrait; margin: 0; }
    #reportFormPdfModal .rpf-page {
        width: 100% !important; min-height: auto !important;
        height: auto !important; margin: 0 !important;
        padding: 10mm 12mm 8mm 12mm !important;
        box-shadow: none !important; overflow: visible !important;
        page-break-after: always; page-break-inside: auto;
    }
    #reportFormPdfModal .rpf-page:last-of-type { page-break-after: auto; }
    #reportFormPdfModal .rpf-inp, #reportFormPdfModal .rpf-inp-m,
    #reportFormPdfModal .rpf-inp-s, #reportFormPdfModal .rpf-inp-long,
    #reportFormPdfModal .rpf-sel {
        border-bottom-color: #888 !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    #reportFormPdfModal .rpf-cb {
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    #reportFormPdfModal .rpf-sel {
        -webkit-appearance: none; appearance: none;
        color: #000 !important; background: transparent !important;
    }
    #reportFormPdfModal .rpf-sig-box { page-break-inside: avoid; }
    #reportFormPdfModal .rpf-ev-table { page-break-inside: avoid; }
}
</style>

<!-- ===== MODAL ===== -->
<div class="modal fade" id="reportFormPdfModal" aria-labelledby="reportFormPdfModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 860px; margin: 1.75rem auto;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="reportFormPdfModalLabel">
                    <i class="fas fa-file-alt fa-lg me-2"></i> แบบการตรวจเก็บและส่งมอบวัตถุพยาน (F-CS-11)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body rpf-body p-0" style="max-height: 80vh; overflow-y: auto; position:relative;">
                <!-- Loading Overlay -->
                <div id="reportPdfLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track">
                            <div class="csims-bar-fill"></div>
                        </div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>
                <form id="reportFormPdf" novalidate>
                    <input type="hidden" id="rpf_receiveNoti_id" name="receiveNoti_id">
                    <input type="hidden" id="rpf_doc_no" name="doc_no">
                    <input type="hidden" id="rpf_report_no" name="report_no">
                    <input type="hidden" id="rpf_incident_id" name="incident_id">

                    <!-- Switch กลับไปฟอร์มมาตรฐาน -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="editInfoReportPdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountReportPdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToStdFormReport" checked style="width: 3rem; height: 1.5rem; cursor: pointer;" onchange="if(!this.checked){ this.checked=true; switchToReportStandardForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToStdFormReport" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ================================================================ -->
<!-- ========================= PAGE 1 ============================== -->
<!-- ================================================================ -->
<div class="rpf-page" id="rpf-page-1">

    <!-- HEADER: Logo -->
    <div class="rpf-form-header">
        <img src="./images/office-of-police-forensic-icon.png" alt="Logo">
    </div>

    <!-- Report No -->
    <div class="rpf-report-no-line">
        เลขรับที่/เลขรายงาน
        <input type="text" class="rpf-inp-long" name="report_no_display" id="rpf_report_no_display" style="min-width:180px;">
    </div>

    <!-- Title -->
    <div class="rpf-form-title">แบบการตรวจเก็บและส่งมอบวัตถุพยาน</div>

    <!-- เขียนที่ + วันที่ -->
    <div class="rpf-right-line">
        เขียนที่
        <select class="rpf-sel" name="source_station" id="rpf_source_station" style="max-width:200px; display:inline-block; width:auto;">
            <?= $rpfPoliceStationOptions ?>
        </select>
    </div>
    <div class="rpf-right-line">
        วันที่ <input type="text" class="rpf-inp-s" name="report_day" id="rpf_report_day" style="max-width:40px;">
        เดือน <input type="text" class="rpf-inp-long" name="report_month" id="rpf_report_month" style="max-width:130px;">
        พ.ศ. <input type="text" class="rpf-inp-s" name="report_year" id="rpf_report_year" style="max-width:50px;">
    </div>

    <!-- Content Section 1 -->
    <div class="rpf-content-section">
        <!-- รับแจ้งเหตุ -->
        <div class="rpf-fr">
            <span class="rpf-fl">รับแจ้งเหตุ จาก ท้องที่ สน./สภ.</span>
            <select class="rpf-sel" name="source_station_2" id="rpf_source_station_2">
                <?= $rpfPoliceStationOptions ?>
            </select>
        </div>

        <!-- ช่องทาง -->
        <div class="rpf-fr">
            <label class="rpf-ck"><input type="checkbox" class="rpf-cb" name="channel[]" value="phone" id="rpf_chk_phone"> ทางโทรศัพท์</label>
            <label class="rpf-ck"><input type="checkbox" class="rpf-cb" name="channel[]" value="document" id="rpf_chk_document"> หนังสือนำส่ง</label>
            <label class="rpf-ck"><input type="checkbox" class="rpf-cb" name="channel[]" value="radio" id="rpf_chk_radio"> ทางวิทยุ</label>
            <label class="rpf-ck"><input type="checkbox" class="rpf-cb" name="channel[]" value="other" id="rpf_chk_other"> อื่น ๆ</label>
            <input type="text" class="rpf-inp" name="channel_other_txt" id="rpf_channel_other_txt" style="max-width:150px;">
            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
        </div>

        <!-- ที่ / เมื่อวันที่ -->
        <div class="rpf-fr">
            <span class="rpf-fl">ที่</span>
            <input type="text" class="rpf-inp" name="doc_no_display" id="rpf_doc_no_display" style="max-width:150px;">
            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
            <span class="rpf-fl">เมื่อวันที่</span>
            <input type="text" class="rpf-inp-s" name="report_day_2" id="rpf_report_day_2" style="max-width:40px;">
            <span class="rpf-fl">เดือน</span>
            <input type="text" class="rpf-inp-long" name="report_month_2" id="rpf_report_month_2" style="max-width:120px;">
            <span class="rpf-fl">พ.ศ.</span>
            <input type="text" class="rpf-inp-s" name="report_year_2" id="rpf_report_year_2" style="max-width:50px;">
        </div>

        <!-- คดี / สถานที่เกิดเหตุ -->
        <div class="rpf-fr">
            <span class="rpf-fl">คดี</span>
            <input type="text" class="rpf-inp" name="case_type_text" id="rpf_case_type_text" style="max-width:280px;">
            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
            <span class="rpf-fl">สถานที่เกิดเหตุ</span>
            <input type="text" class="rpf-inp" name="incident_location" id="rpf_incident_location">
            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
        </div>
    </div>

    <!-- Content Section 2 -->
    <div class="rpf-content-section">
        <!-- วัน เวลา ทราบเหตุ/เกิดเหตุ -->
        <div class="rpf-fr rpf-fr-datetime" style="display:flex; align-items:baseline;">
            <span class="rpf-fl" style="white-space:nowrap;">วัน เวลา ทราบเหตุ/เกิดเหตุ</span>
            <input type="text" class="rpf-inp" name="incident_date_thai" id="rpf_incident_date_thai" style="flex:1;" readonly>
            <input type="hidden" name="incident_date" id="rpf_incident_date">
            <span class="rpf-fl" style="white-space:nowrap;">เวลาประมาณ</span>
            <input type="text" class="rpf-inp-s" name="incident_time" id="rpf_incident_time" style="max-width:80px;" readonly>
            <span class="rpf-fl">น.</span>
        </div>

        <!-- วัน เวลา ตรวจสถานที่เกิดเหตุ -->
        <div class="rpf-fr rpf-fr-datetime" style="display:flex; align-items:baseline;">
            <span class="rpf-fl" style="white-space:nowrap;">วัน เวลา ตรวจสถานที่เกิดเหตุ/ตรวจเก็บวัตถุพยาน</span>
            <input type="text" class="rpf-inp" name="inspect_date_thai" id="rpf_inspect_date_thai" style="flex:1;" readonly>
            <input type="hidden" name="inspect_date" id="rpf_inspect_date">
            <span class="rpf-fl" style="white-space:nowrap;">เวลาประมาณ</span>
            <input type="text" class="rpf-inp-s" name="inspect_time" id="rpf_inspect_time" style="max-width:80px;" readonly>
            <span class="rpf-fl">น.</span>
        </div>

        <!-- ผู้เสียหาย/ผู้เสียชีวิต -->
        <div class="rpf-fr rpf-fr-victim">
            <div class="rpf-victim-left">
                <span class="rpf-fl">ผู้เสียหาย/ผู้เสียชีวิต</span>
                <input type="text" class="rpf-inp" name="victim_name" id="rpf_victim_name">
                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
            </div>
            <div class="rpf-victim-right">
                <span class="rpf-fl">อายุประมาณ</span>
                <input type="text" class="rpf-inp-s" name="victim_age" id="rpf_victim_age" style="max-width:50px;">
                <span class="rpf-fl">ปี</span>
            </div>
        </div>

        <!-- ชื่อพนักงานสอบสวน -->
        <div class="rpf-fr">
            <span class="rpf-fl">ชื่อพนักงานสอบสวนเจ้าของคดี</span>
            <input type="text" class="rpf-inp" name="officer_name" id="rpf_officer_name">
            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
        </div>

        <!-- กสก. / พฐ.จว. -->
        <div class="rpf-fr">
            <span class="rpf-fl">กสก.</span>
            <input type="text" class="rpf-inp" name="police_unit" id="rpf_police_unit" style="max-width:100px;">
            <span class="rpf-fl">/พฐ.จว.</span>
            <input type="text" class="rpf-inp" name="province" id="rpf_province" style="max-width:100px;">
            <span class="rpf-fl">ได้ทำการตรวจเก็บวัตถุพยานในสถานที่เกิดเหตุ จำนวน</span>
            <input type="text" class="rpf-inp-s" name="evidence_count" id="rpf_evidence_count" style="max-width:40px;" readonly>
            <span class="rpf-fl">รายการ ดังนี้</span>
        </div>
    </div>

    <!-- Evidence Table -->
    <table class="rpf-ev-table" id="rpf_evidence_table">
        <thead>
            <tr>
                <th style="width: 10%;">ลำดับ</th>
                <th style="width: 50%;">รายการ</th>
                <th style="width: 40%;">การตรวจพิสูจน์</th>
            </tr>
        </thead>
        <tbody id="rpf_evidence_tbody">
            <tr>
                <td>1</td>
                <td><input type="text" name="evidence_item[]" class="rpf-ev-item" style="text-align:left;"></td>
                <td>
                    <div class="rpf-ev-cell">
                        <select name="evidence_test[]" class="rpf-ev-select">
                            <option value="">-- เลือก --</option>
                            <option value="กลุ่มงานตรวจชีววิทยา">กลุ่มงานตรวจชีววิทยา</option>
                            <option value="กลุ่มงานตรวจทางเคมีฟิสิกส์">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                            <option value="กลุ่มงานตรวจลายนิ้วมือแฝง">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                            <option value="กลุ่มงานตรวจยาเสพติด">กลุ่มงานตรวจยาเสพติด</option>
                            <option value="กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                            <option value="กลุ่มงานตรวจเอกสาร">กลุ่มงานตรวจเอกสาร</option>
                            <option value="กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option>
                            <option value="กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                            <option value="กลุ่มงานตรวจวัตถุระเบิด (กก.กตว.)">กลุ่มงานตรวจวัตถุระเบิด (กก.กตว.)</option>
                        </select>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
    <button type="button" class="rpf-add-btn" onclick="rpfAddEvidenceRow()">+ เพิ่มรายการวัตถุพยาน</button>

    <!-- Handover Text -->
    <div class="rpf-content-section" style="margin-top: 6px;">
        <div class="rpf-fr">
            <span class="rpf-fl">วัตถุพยานลำดับที่</span>
            <input type="text" class="rpf-inp-long" name="evidence_nos" id="rpf_evidence_nos">
            <span class="rpf-fl">ได้ส่งมอบให้กับพนักงานสอบสวนเจ้าของคดีเพื่อดำเนินการต่อไป</span>
        </div>
    </div>

    <!-- Receiver Signature -->
    <div class="rpf-signature-block">
        <div style="white-space:nowrap;">(ลงชื่อ)
            <span class="rpf-sig-box" id="rpf_receiver_sig_box">
                <canvas id="rpf_receiver_sig_canvas"></canvas>
                <button type="button" class="rpf-sig-clear-btn" onclick="rpfClearSig('rpf_receiver_sig_canvas', true)" title="ล้าง">×</button>
            </span>
            ผู้รับมอบ
        </div>
        <div>(
            <input type="text" class="rpf-inp-long" name="receiver_name" id="rpf_receiver_name" style="min-width:150px;" readonly>
            )</div>
        <div style="white-space:nowrap;">ตำแหน่ง
            <input type="text" class="rpf-inp-long" name="receiver_position" id="rpf_receiver_position" style="min-width:220px;" readonly>
        </div>
        <div style="white-space:nowrap;">วันที่
            <input type="text" class="rpf-inp-long" name="handover_date_thai" id="rpf_handover_date_thai" style="min-width:150px;" readonly>
            <input type="hidden" name="handover_date" id="rpf_handover_date">
            เวลา
            <input type="text" class="rpf-inp-s" name="handover_time" id="rpf_handover_time" style="width:60px;" readonly>
            น.
        </div>
    </div>

    <!-- Deliverer Signature -->
    <div class="rpf-signature-block">
        <div style="white-space:nowrap;">(ลงชื่อ)
            <span class="rpf-sig-box" id="rpf_deliverer_sig_box">
                <canvas id="rpf_deliverer_sig_canvas"></canvas>
                <button type="button" class="rpf-sig-clear-btn" onclick="rpfClearSig('rpf_deliverer_sig_canvas', true)" title="ล้าง">×</button>
            </span>
            ผู้มอบ
        </div>
        <div>(
            <input type="text" class="rpf-inp-long" name="deliverer_name" id="rpf_deliverer_name" style="min-width:150px;" readonly>
            )</div>
        <div style="white-space:nowrap;">ตำแหน่ง
            <input type="text" class="rpf-inp-long" name="deliverer_position" id="rpf_deliverer_position" style="min-width:220px;" readonly>
        </div>
        <div style="white-space:nowrap;">วันที่
            <input type="text" class="rpf-inp-long" name="handover_date_thai_2" id="rpf_handover_date_thai_2" style="min-width:150px;" readonly>
            <input type="hidden" name="handover_date_2" id="rpf_handover_date_2">
            เวลา
            <input type="text" class="rpf-inp-s" name="handover_time_2" id="rpf_handover_time_2" style="width:60px;" readonly>
            น.
        </div>
    </div>

    <!-- Footer -->
    <div class="rpf-form-footer">
        F-CS-11 แก้ไขครั้งที่ 1<br>
        เริ่มใช้ 1 ส.ค. 60
    </div>

</div><!-- /rpf-page-1 -->

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm" id="btn_save_report_pdf" onclick="rpfSaveViaStandardForm()">
                        <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> ยกเลิก
                    </button>
                    <button type="button" class="btn btn-info btn-sm" id="btn_print_report_pdf" onclick="rpfPrintForm()">
                        <i class="fas fa-print me-1"></i> พิมพ์
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== JAVASCRIPT ===== -->
<script>
(function() {

    // ===== Lab Unit Options =====
    var labUnitOptions = [
        { value: '', text: '-- เลือก --' },
        { value: 'กลุ่มงานตรวจชีววิทยา', text: 'กลุ่มงานตรวจชีววิทยา' },
        { value: 'กลุ่มงานตรวจทางเคมีฟิสิกส์', text: 'กลุ่มงานตรวจทางเคมีฟิสิกส์' },
        { value: 'กลุ่มงานตรวจลายนิ้วมือแฝง', text: 'กลุ่มงานตรวจลายนิ้วมือแฝง' },
        { value: 'กลุ่มงานตรวจยาเสพติด', text: 'กลุ่มงานตรวจยาเสพติด' },
        { value: 'กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน', text: 'กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน' },
        { value: 'กลุ่มงานตรวจเอกสาร', text: 'กลุ่มงานตรวจเอกสาร' },
        { value: 'กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล', text: 'กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล' },
        { value: 'กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์', text: 'กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์' },
        { value: 'กลุ่มงานตรวจวัตถุระเบิด (กก.กตว.)', text: 'กลุ่มงานตรวจวัตถุระเบิด (กก.กตว.)' }
    ];
    
    function buildLabUnitSelect(selectedValue) {
        var html = '<select name="evidence_test[]" class="rpf-ev-select">';
        labUnitOptions.forEach(function(opt) {
            var sel = (opt.value === selectedValue) ? ' selected' : '';
            html += '<option value="' + opt.value + '"' + sel + '>' + opt.text + '</option>';
        });
        html += '</select>';
        return html;
    }

    // ===== Add Evidence Row =====
    window.rpfAddEvidenceRow = function() {
        var tbody = document.getElementById('rpf_evidence_tbody');
        if (!tbody) return;
        var rowCount = tbody.rows.length + 1;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td>' + rowCount + '</td>' +
            '<td><input type="text" name="evidence_item[]" class="rpf-ev-item" style="text-align:left;"></td>' +
            '<td><div class="rpf-ev-cell">' +
                buildLabUnitSelect('') +
                '<button type="button" class="rpf-del-btn" onclick="rpfDelEvidenceRow(this)" title="ลบ">×</button>' +
            '</div></td>';
        tbody.appendChild(tr);
        rpfUpdateEvidenceCount();
    };

    // ===== Delete Evidence Row =====
    window.rpfDelEvidenceRow = function(btn) {
        var tr = btn.closest('tr');
        if (tr) tr.remove();
        rpfReindexEvidence();
        rpfUpdateEvidenceCount();
    };

    // ===== Reindex Evidence Rows =====
    function rpfReindexEvidence() {
        var tbody = document.getElementById('rpf_evidence_tbody');
        if (!tbody) return;
        var rows = tbody.querySelectorAll('tr');
        rows.forEach(function(row, i) {
            row.cells[0].textContent = i + 1;
        });
    }

    // ===== Update Evidence Count =====
    function rpfUpdateEvidenceCount() {
        var tbody = document.getElementById('rpf_evidence_tbody');
        var countInput = document.getElementById('rpf_evidence_count');
        if (tbody && countInput) {
            countInput.value = tbody.rows.length;
        }
        // Auto-update evidence_nos
        rpfUpdateEvidenceNos();
    }

    // ===== Auto-update evidence_nos =====
    function rpfUpdateEvidenceNos() {
        var tbody = document.getElementById('rpf_evidence_tbody');
        var nosInput = document.getElementById('rpf_evidence_nos');
        if (!tbody || !nosInput) return;
        var count = tbody.rows.length;
        if (count === 0) { nosInput.value = ''; return; }
        var nums = [];
        for (var i = 1; i <= count; i++) nums.push(i);
        nosInput.value = nums.join(', ');
    }

    // ===== Signature canvas setup =====
    function rpfInitSignature(canvasId) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var drawing = false;
        var rect;

        function resize() {
            var parent = canvas.parentElement;
            canvas.width = parent.clientWidth;
            canvas.height = parent.clientHeight;
        }
        resize();

        canvas.addEventListener('mousedown', function(e) {
            drawing = true;
            rect = canvas.getBoundingClientRect();
            ctx.beginPath();
            ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
            // Remove cleared flag when user starts drawing
            delete canvas.dataset.cleared;
        });
        canvas.addEventListener('mousemove', function(e) {
            if (!drawing) return;
            ctx.lineWidth = 1.5;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#000';
            ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
            ctx.stroke();
        });
        canvas.addEventListener('mouseup', function() { drawing = false; });
        canvas.addEventListener('mouseleave', function() { drawing = false; });

        // Touch support
        canvas.addEventListener('touchstart', function(e) {
            e.preventDefault();
            drawing = true;
            rect = canvas.getBoundingClientRect();
            var t = e.touches[0];
            ctx.beginPath();
            ctx.moveTo(t.clientX - rect.left, t.clientY - rect.top);
            // Remove cleared flag when user starts drawing
            delete canvas.dataset.cleared;
        }, { passive: false });
        canvas.addEventListener('touchmove', function(e) {
            e.preventDefault();
            if (!drawing) return;
            var t = e.touches[0];
            ctx.lineWidth = 1.5;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#000';
            ctx.lineTo(t.clientX - rect.left, t.clientY - rect.top);
            ctx.stroke();
        }, { passive: false });
        canvas.addEventListener('touchend', function() { drawing = false; });
    }

    // ===== Clear Signature =====
    // markAsCleared: true = user clicked clear button, false = just clearing canvas
    window.rpfClearSig = function(canvasId, markAsCleared) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        // Mark canvas as cleared only if user clicked clear button
        if (markAsCleared === true) {
            canvas.dataset.cleared = 'true';
        } else {
            delete canvas.dataset.cleared;
        }
    };

    // ===== Init on modal show =====
    var modalEl = document.getElementById('reportFormPdfModal');
    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', function() {
            rpfInitSignature('rpf_receiver_sig_canvas');
            rpfInitSignature('rpf_deliverer_sig_canvas');
            rpfUpdateEvidenceCount();
            
            // Draw pending signatures after canvas init (or clear if null)
            setTimeout(function() {
                if (window._rpfPendingSigs) {
                    if (window._rpfPendingSigs.receiver) {
                        rpfDrawSigFromData('rpf_receiver_sig_canvas', window._rpfPendingSigs.receiver);
                    } else {
                        rpfClearSig('rpf_receiver_sig_canvas');
                    }
                    if (window._rpfPendingSigs.deliverer) {
                        rpfDrawSigFromData('rpf_deliverer_sig_canvas', window._rpfPendingSigs.deliverer);
                    } else {
                        rpfClearSig('rpf_deliverer_sig_canvas');
                    }
                }
            }, 150);
        });
    }

    // ===== Print Form - เปิด PDF form ในหน้าต่างใหม่ =====
    window.rpfPrintForm = function() {
        var incidentId = document.getElementById('rpf_incident_id').value;
        if (!incidentId) {
            Swal.fire({ icon: 'warning', title: 'ไม่พบ incident_id', text: 'กรุณาลองใหม่อีกครั้ง' });
            return;
        }
        // เปิด PDF form ในหน้าต่างใหม่
        window.open('/csims/api/incidentCheckList/gen_pdf_report_html.php?incident_id=' + incidentId, '_blank');
    };

    // ===== Load Data from API =====
    window.rpfLoadData = function(incidentId) {
        if (!incidentId) return;
        
        // Show loading
        var overlay = document.getElementById('reportPdfLoadingOverlay');
        if (overlay) overlay.classList.remove('d-none');
        
        fetch('/csims/api/incidentCheckList/getFCS11Data.php?incident_id=' + incidentId)
            .then(function(res) { return res.json(); })
            .then(function(json) {
                if (overlay) overlay.classList.add('d-none');
                
                if (json.status !== 'success' || !json.data) {
                    console.warn('FCS11 load failed:', json.message);
                    return;
                }
                
                var d = json.data;
                
                // Fill form fields
                rpfSetVal('rpf_report_no_display', d.report_no);
                rpfSetSelect('rpf_source_station', d.source_station);
                rpfSetVal('rpf_report_day', d.report_day);
                rpfSetVal('rpf_report_month', d.report_month);
                rpfSetVal('rpf_report_year', d.report_year);
                
                rpfSetSelect('rpf_source_station_2', d.source_station);
                
                // Channels
                var channels = d.channels || [];
                document.getElementById('rpf_chk_phone').checked = channels.includes('phone') || channels.includes('ทางโทรศัพท์');
                document.getElementById('rpf_chk_document').checked = channels.includes('document') || channels.includes('letter') || channels.includes('หนังสือนำส่ง') || channels.includes('ทางหนังสือ');
                document.getElementById('rpf_chk_radio').checked = channels.includes('radio') || channels.includes('ทางวิทยุ') || channels.includes('ทางวิทยุสื่อสาร');
                document.getElementById('rpf_chk_other').checked = channels.includes('other') || channels.includes('อื่นๆ');
                rpfSetVal('rpf_channel_other_txt', d.channel_other_txt);
                
                rpfSetVal('rpf_doc_no_display', d.doc_no);
                rpfSetVal('rpf_report_day_2', d.report_day);
                rpfSetVal('rpf_report_month_2', d.report_month);
                rpfSetVal('rpf_report_year_2', d.report_year);
                
                // แปลง case_type เป็นภาษาไทย
                var caseTypeMap = {
                    'theft': 'ลักทรัพย์',
                    'snatch': 'ชิงทรัพย์',
                    'robbery': 'ปล้นทรัพย์',
                    'murder': 'ฆาตกรรม',
                    'bomb': 'ระเบิด',
                    'arson': 'วางเพลิง',
                    'fire': 'เพลิงไหม้',
                    'life': 'คดีชีวิต',
                    'traffic': 'จราจร',
                    'fingerprint': 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)',
                    'scene_evidence': 'ตรวจเก็บและส่งมอบวัตถุพยาน',
                    'person_evidence': 'ตรวจเก็บวัตถุพยานบุคคล',
                    'person evidence': 'ตรวจเก็บวัตถุพยานบุคคล'
                };
                var caseText = d.case_type_text;
                if (caseTypeMap[caseText]) caseText = caseTypeMap[caseText];
                rpfSetVal('rpf_case_type_text', caseText);
                rpfSetVal('rpf_incident_location', d.incident_location);
                
                // Datetime - Thai format
                rpfSetVal('rpf_incident_date', d.incident_date);
                rpfSetVal('rpf_incident_date_thai', d.incident_date_thai);
                rpfSetVal('rpf_incident_time', d.incident_time_thai || d.incident_time);
                rpfSetVal('rpf_inspect_date', d.inspect_date);
                rpfSetVal('rpf_inspect_date_thai', d.inspect_date_thai);
                rpfSetVal('rpf_inspect_time', d.inspect_time_thai || d.inspect_time);
                
                rpfSetVal('rpf_victim_name', d.victim_name);
                rpfSetVal('rpf_victim_age', d.victim_age);
                rpfSetVal('rpf_officer_name', d.officer_name);
                rpfSetVal('rpf_police_unit', d.police_unit);
                rpfSetVal('rpf_province', d.province);
                
                // Evidence table
                rpfLoadEvidences(d.evidences || []);
                
                // Handover - Thai format
                rpfSetVal('rpf_receiver_name', d.receiver_name);
                rpfSetVal('rpf_receiver_position', d.receiver_position);
                rpfSetVal('rpf_deliverer_name', d.deliverer_name);
                rpfSetVal('rpf_deliverer_position', d.deliverer_position);
                
                rpfSetVal('rpf_handover_date', d.handover_date);
                rpfSetVal('rpf_handover_date_thai', d.handover_date_thai);
                rpfSetVal('rpf_handover_time', d.handover_time_thai || d.handover_time);
                rpfSetVal('rpf_handover_date_2', d.handover_date);
                rpfSetVal('rpf_handover_date_thai_2', d.handover_date_thai);
                rpfSetVal('rpf_handover_time_2', d.handover_time_thai || d.handover_time);
                
                // Signatures - เก็บไว้ใน global variable เพื่อ draw หลัง modal shown
                window._rpfPendingSigs = {
                    receiver: d.receiver_sig,
                    deliverer: d.deliverer_sig
                };
                
                // ถ้า modal แสดงแล้ว ให้ draw หรือ clear
                var modalEl = document.getElementById('reportFormPdfModal');
                if (modalEl && modalEl.classList.contains('show')) {
                    setTimeout(function() {
                        if (d.receiver_sig) {
                            rpfDrawSigFromData('rpf_receiver_sig_canvas', d.receiver_sig);
                        } else {
                            rpfClearSig('rpf_receiver_sig_canvas');
                        }
                        if (d.deliverer_sig) {
                            rpfDrawSigFromData('rpf_deliverer_sig_canvas', d.deliverer_sig);
                        } else {
                            rpfClearSig('rpf_deliverer_sig_canvas');
                        }
                    }, 100);
                }
                
            })
            .catch(function(err) {
                if (overlay) overlay.classList.add('d-none');
                console.error('FCS11 load error:', err);
            });
    };
    
    // Helper: set input value
    function rpfSetVal(id, val) {
        var el = document.getElementById(id);
        if (el) el.value = val || '';
    }
    
    // Helper: set select value
    function rpfSetSelect(id, val) {
        var el = document.getElementById(id);
        if (!el || !val) return;
        // Try exact match first
        for (var i = 0; i < el.options.length; i++) {
            if (el.options[i].value === val || el.options[i].text === val) {
                el.selectedIndex = i;
                return;
            }
        }
    }
    
    // Helper: load evidences into table
    function rpfLoadEvidences(evidences) {
        var tbody = document.getElementById('rpf_evidence_tbody');
        if (!tbody) return;
        
        // Clear existing rows
        tbody.innerHTML = '';
        
        if (!evidences || evidences.length === 0) {
            // Add one empty row
            rpfAddEvidenceRow();
            return;
        }
        
        evidences.forEach(function(ev, idx) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + (idx + 1) + '</td>' +
                '<td><input type="text" name="evidence_item[]" class="rpf-ev-item" style="text-align:left;" value="' + escapeHtml(ev.item || '') + '"></td>' +
                '<td><div class="rpf-ev-cell">' +
                    buildLabUnitSelect(ev.test || '') +
                    '<button type="button" class="rpf-del-btn" onclick="rpfDelEvidenceRow(this)" title="ลบ">×</button>' +
                '</div></td>';
            tbody.appendChild(tr);
        });
        
        rpfUpdateEvidenceCount();
    }
    
    // Helper: escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Helper: draw signature from data
    function rpfDrawSigFromData(canvasId, sigData) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;
        
        var imgSrc = '';
        if (typeof sigData === 'string' && sigData.indexOf('data:image') === 0) {
            imgSrc = sigData;
        } else if (sigData && sigData.base64 && sigData.base64.indexOf('data:image') === 0) {
            imgSrc = sigData.base64;
        } else if (sigData && sigData.file_id) {
            imgSrc = '/csims/api/incidentCheckList/getFile.php?id=' + sigData.file_id;
        } else if (sigData && sigData.filename) {
            imgSrc = '/csims/uploads/checklist_signatures/' + sigData.filename;
        }
        
        if (!imgSrc) {
            console.log('rpfDrawSigFromData: no imgSrc for', canvasId, sigData);
            return;
        }
        
        console.log('rpfDrawSigFromData:', canvasId, imgSrc.substring(0, 50));
        
        // Ensure canvas is sized to match CSS (220x90)
        var parent = canvas.parentElement;
        canvas.width = parent.clientWidth || 220;
        canvas.height = parent.clientHeight || 90;
        
        var ctx = canvas.getContext('2d');
        var img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function() {
            console.log('Signature image loaded:', canvasId, 'img:', img.width, 'x', img.height, 'canvas:', canvas.width, 'x', canvas.height);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Calculate aspect ratio to fit image properly
            var imgRatio = img.width / img.height;
            var canvasRatio = canvas.width / canvas.height;
            var drawWidth, drawHeight, offsetX, offsetY;
            
            if (imgRatio > canvasRatio) {
                // Image is wider - fit to width
                drawWidth = canvas.width;
                drawHeight = canvas.width / imgRatio;
                offsetX = 0;
                offsetY = (canvas.height - drawHeight) / 2;
            } else {
                // Image is taller - fit to height
                drawHeight = canvas.height;
                drawWidth = canvas.height * imgRatio;
                offsetX = (canvas.width - drawWidth) / 2;
                offsetY = 0;
            }
            
            ctx.drawImage(img, offsetX, offsetY, drawWidth, drawHeight);
        };
        img.onerror = function(e) {
            console.error('Signature image load error:', canvasId, e);
        };
        img.src = imgSrc;
    }

    // ===== Save Form =====
    window.rpfSaveViaStandardForm = async function() {
        var incidentId = document.getElementById('rpf_incident_id').value;
        if (!incidentId) {
            Swal.fire({ icon: 'warning', title: 'ไม่พบ incident_id', text: 'กรุณาลองใหม่อีกครั้ง' });
            return;
        }
        
        // Helper to get value safely
        function getVal(id) {
            var el = document.getElementById(id);
            return el ? el.value : '';
        }
        
        // Collect form data
        var formData = {
            incident_id: incidentId,
            report_no: getVal('rpf_report_no_display'),
            source_station: getVal('rpf_source_station'),
            report_day: getVal('rpf_report_day'),
            report_month: getVal('rpf_report_month'),
            report_year: getVal('rpf_report_year'),
            channels: [],
            channel_other_txt: getVal('rpf_channel_other_txt'),
            doc_no: getVal('rpf_doc_no_display'),
            case_type_text: getVal('rpf_case_type_text'),
            incident_location: getVal('rpf_incident_location'),
            incident_date: getVal('rpf_incident_date'),
            incident_time: getVal('rpf_incident_time'),
            inspect_date: getVal('rpf_inspect_date'),
            inspect_time: getVal('rpf_inspect_time'),
            victim_name: getVal('rpf_victim_name'),
            victim_age: getVal('rpf_victim_age'),
            officer_name: getVal('rpf_officer_name'),
            police_unit: getVal('rpf_police_unit'),
            province: getVal('rpf_province'),
            receiver_name: getVal('rpf_receiver_name'),
            receiver_position: getVal('rpf_receiver_position'),
            deliverer_name: getVal('rpf_deliverer_name'),
            deliverer_position: getVal('rpf_deliverer_position'),
            handover_date: getVal('rpf_handover_date'),
            handover_time: getVal('rpf_handover_time'),
            evidences: [],
            receiver_sig: rpfGetSigBase64('rpf_receiver_sig_canvas'),
            deliverer_sig: rpfGetSigBase64('rpf_deliverer_sig_canvas')
        };
        
        // Channels
        var chkPhone = document.getElementById('rpf_chk_phone');
        var chkDoc = document.getElementById('rpf_chk_document');
        var chkRadio = document.getElementById('rpf_chk_radio');
        var chkOther = document.getElementById('rpf_chk_other');
        if (chkPhone && chkPhone.checked) formData.channels.push('phone');
        if (chkDoc && chkDoc.checked) formData.channels.push('document');
        if (chkRadio && chkRadio.checked) formData.channels.push('radio');
        if (chkOther && chkOther.checked) formData.channels.push('other');
        
        // Evidences
        var items = document.querySelectorAll('#rpf_evidence_tbody input[name="evidence_item[]"]');
        var tests = document.querySelectorAll('#rpf_evidence_tbody select[name="evidence_test[]"]');
        items.forEach(function(item, idx) {
            if (item.value.trim()) {
                formData.evidences.push({
                    no: idx + 1,
                    item: item.value.trim(),
                    test: tests[idx] ? tests[idx].value : ''
                });
            }
        });
        
        // Show loading
        Swal.fire({
            title: 'กำลังบันทึก...',
            allowOutsideClick: false,
            didOpen: function() { Swal.showLoading(); }
        });
        
        console.log('FCS11 Save - formData:', formData);
        
        try {
            var response = await fetch('/csims/api/incidentCheckList/saveFCS11.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            
            console.log('FCS11 Save - response status:', response.status);
            var responseText = await response.text();
            console.log('FCS11 Save - response text:', responseText);
            
            var result;
            try {
                result = JSON.parse(responseText);
            } catch (parseErr) {
                console.error('FCS11 Save - JSON parse error:', parseErr);
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถอ่านผลลัพธ์จาก server: ' + responseText.substring(0, 200) });
                return;
            }
            
            if (result.status === 'success') {
                Swal.fire({ icon: 'success', title: 'บันทึกสำเร็จ', timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: result.message || 'ไม่สามารถบันทึกได้' });
            }
        } catch (err) {
            console.error('FCS11 Save - fetch error:', err);
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: err.message });
        }
    };
    
    // Helper: get signature as base64
    function rpfGetSigBase64(canvasId) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return '';
        
        // Check if canvas was cleared by user
        if (canvas.dataset.cleared === 'true') {
            return 'CLEARED';
        }
        
        try {
            // Check if canvas has content
            if (canvas.width === 0 || canvas.height === 0) return '';
            var dataUrl = canvas.toDataURL('image/png');
            // Simple check - if dataUrl is very short, canvas is likely empty
            if (dataUrl.length < 1000) return '';
            return dataUrl;
        } catch (e) {
            console.error('rpfGetSigBase64 error:', e);
            return '';
        }
    }

    // ===== Switch to standard form (placeholder) =====
    window.switchToReportStandardForm = function() {
        var modal = bootstrap.Modal.getInstance(document.getElementById('reportFormPdfModal'));
        if (modal) modal.hide();
        
        // Open PDF in new window as fallback
        var incidentId = document.getElementById('rpf_incident_id').value;
        if (incidentId) {
            window.open('/csims/api/incidentCheckList/gen_pdf_report_html.php?incident_id=' + incidentId, '_blank');
        }
    };

    // ===== Open Modal with incident data =====
    window.openReportFormPdfModal = function(incidentId, complaintsType) {
        // Store incident info
        document.getElementById('rpf_incident_id').value = incidentId || '';
        
        // Reset form
        document.getElementById('reportFormPdf').reset();
        
        // Clear evidence table
        var tbody = document.getElementById('rpf_evidence_tbody');
        if (tbody) tbody.innerHTML = '';
        rpfAddEvidenceRow();
        
        // Clear signatures
        rpfClearSig('rpf_receiver_sig_canvas');
        rpfClearSig('rpf_deliverer_sig_canvas');
        
        // Show modal
        var modal = new bootstrap.Modal(document.getElementById('reportFormPdfModal'));
        modal.show();
        
        // Load existing data
        if (incidentId) {
            rpfLoadData(incidentId);
        }
    };

    // ===== Initial evidence count =====
    rpfUpdateEvidenceCount();

})();
</script>
