<?php
/**
 * Modal: ฟอร์ม PDF คดีเกี่ยวกับจราจร (แบบกรอกข้อมูล)
 * หน้าตาเหมือนฟอร์ม PDF เป๊ะ แต่กรอกข้อมูลได้
 * Prefix ID: tpf_ (traffic pdf form)
 * อ้างอิงจาก modal_life_pdf_form.php + form_traffic_preview.html
 */

// ดึง police station options
$tpfPoliceStationOptions = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qryTpfPS = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stmtTpfPS = $pdo->query($qryTpfPS);
    while ($rowPS = $stmtTpfPS->fetch(PDO::FETCH_ASSOC)) {
        $tpfPoliceStationOptions .= '<option value="' . htmlspecialchars($rowPS['station_name']) . '">' . htmlspecialchars($rowPS['station_name']) . '</option>';
    }
}

// ดึงรายชื่อผู้ตรวจ
$tpfInspectorOptions = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
$tpfPhotographerOptions = '<option value="" selected disabled>-- เลือกผู้จดบันทึก --</option>';
if (isset($pdo)) {
    $qryTpfInsp = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                   FROM user_profile t1 
                   LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                   ORDER BY t1.user_id DESC";
    $stmtTpfInsp = $pdo->query($qryTpfInsp);
    while ($rowInsp = $stmtTpfInsp->fetch(PDO::FETCH_ASSOC)) {
        $fullname = htmlspecialchars($rowInsp['fullname']);
        $tpfInspectorOptions .= '<option value="' . $rowInsp['user_id'] . '">' . $fullname . '</option>';
        $tpfPhotographerOptions .= '<option value="' . $fullname . '">' . $fullname . '</option>';
    }
}

$tpfTodayDate = date('Y-m-d');
$tpfTodayTime = date('H:i');
?>

<!-- ===== SCOPED CSS ===== -->
<style>
/* hwpen button */
#trafficFormPdfModal .btn-hw-open {
    flex-shrink: 0; min-width: 18px; padding: 0 4px;
    border: none; background: none; color: #6366f1;
    font-size: 0.7rem; cursor: pointer; line-height: 1.5;
}
#trafficFormPdfModal .btn-hw-open:hover { color: #4338ca; transform: scale(1.15); }

#trafficFormPdfModal .btn-purple {
    background-color: #8b5cf6;
    border-color: #8b5cf6;
    color: #fff;
}
#trafficFormPdfModal .btn-purple:hover {
    background-color: #7c3aed;
    border-color: #7c3aed;
    color: #fff;
}

#trafficFormPdfModal .tpf-rpt-no-mirror,
#trafficFormPdfModal .tpf-rpt-year-mirror {
    display: inline-block;
    border-bottom: 1px dotted #888;
    text-align: center;
}
#trafficFormPdfModal .tpf-rpt-no-mirror { min-width: 60px; }
#trafficFormPdfModal .tpf-rpt-year-mirror { min-width: 30px; }

#trafficFormPdfModal .tpf-body {
    background: #bbb;
    padding: 10px 0;
}

#trafficFormPdfModal .tpf-page {
    width: 210mm;
    min-height: 297mm;
    margin: 10px auto;
    padding: 10mm 12mm 6mm 12mm;
    background: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.25);
    position: relative;
    overflow: visible;
    display: flex;
    flex-direction: column;
    font-family: 'Sarabun', sans-serif;
    font-size: 13px;
    line-height: 1.35;
    color: #000;
}

/* ===== HEADER ===== */
#trafficFormPdfModal .tpf-header {
    position: relative; margin-bottom: 6px; height: 70px;
}
#trafficFormPdfModal .tpf-header-logo {
    position: absolute; left: 0; top: -5px; width: 70px; height: 70px;
}
#trafficFormPdfModal .tpf-header-logo img {
    width: 70px; height: 70px; object-fit: contain;
}
#trafficFormPdfModal .tpf-header-center {
    position: absolute; left: 80px; right: 180px; top: 8px; text-align: center;
}
#trafficFormPdfModal .tpf-header-center .tpf-title-main {
    font-size: 14px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 5px;
}
#trafficFormPdfModal .tpf-header-center .tpf-title-sub {
    font-size: 11.5px; font-weight: 600; margin-top: 3px;
}
#trafficFormPdfModal .tpf-header-right {
    position: absolute; right: 0; top: 7px;
}
#trafficFormPdfModal .tpf-doc-box {
    border: 1.5px solid #000; padding: 3px 8px; font-size: 11px; white-space: nowrap;
}
#trafficFormPdfModal .tpf-doc-box .tpf-doc-line { line-height: 1.5; }

/* ===== BODY TABLE ===== */
#trafficFormPdfModal .tpf-form-body {
    display: flex; border: 1.5px solid #000; align-items: stretch;
}
#trafficFormPdfModal .tpf-col-left {
    width: 50%; border-right: 1.5px solid #000; display: flex; flex-direction: column;
}
#trafficFormPdfModal .tpf-col-right {
    width: 50%; display: flex; flex-direction: column;
}
#trafficFormPdfModal .tpf-row-header {
    display: flex; border-bottom: 1px solid #000;
    font-weight: 700; font-size: 11px; text-align: center; background: transparent;
}
#trafficFormPdfModal .tpf-row-header .tpf-lbl-seq {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000; padding: 1px 2px;
}
#trafficFormPdfModal .tpf-row-header .tpf-lbl-data {
    flex: 1; padding: 1px 2px;
}
#trafficFormPdfModal .tpf-sec-row {
    display: flex; border-bottom: 1px solid #000;
}
#trafficFormPdfModal .tpf-sec-row:last-child { border-bottom: none; }
#trafficFormPdfModal .tpf-sec-label {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000;
    padding: 3px 3px; font-weight: 700; font-size: 11px; text-align: center;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
#trafficFormPdfModal .tpf-sec-label .tpf-sec-num { font-size: 13px; font-weight: 700; line-height: 1.2; }
#trafficFormPdfModal .tpf-sec-label .tpf-sec-txt { font-size: 10px; font-weight: 600; line-height: 1.15; text-align: center; margin-top: 1px; }
#trafficFormPdfModal .tpf-sec-body { flex: 1; padding: 5px 6px; font-size: 11.5px; }

/* ===== FIELD ROW ===== */
#trafficFormPdfModal .tpf-fr { display: flex; flex-wrap: wrap; align-items: center; margin-bottom: 6px; line-height: 1.8; }
#trafficFormPdfModal .tpf-fl { font-size: 11.5px; white-space: nowrap; margin-right: 4px; }
#trafficFormPdfModal .tpf-fl-b { font-size: 11.5px; font-weight: 600; white-space: nowrap; margin-right: 4px; }

/* ===== INPUT FIELDS ===== */
#trafficFormPdfModal .tpf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px;
}
#trafficFormPdfModal .tpf-inp-m {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 60px; margin: 0 2px; text-align: center;
}
#trafficFormPdfModal .tpf-inp-full {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    width: 100%; display: block; margin-bottom: 3px;
}
#trafficFormPdfModal .tpf-inp-s {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    min-width: 15px; max-width: 50px; margin: 0 2px; flex: 0 1 40px; text-align: center;
}
#trafficFormPdfModal .tpf-sel {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px; cursor: pointer;
}
#trafficFormPdfModal .tpf-ta {
    border: none; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; outline: none; color: #000;
    width: 100%; resize: none; line-height: 20px;
    white-space: pre-wrap; overflow-wrap: break-word; word-break: break-word;
    overflow: hidden;
    background-image: linear-gradient(transparent 19px, #888 19px);
    background-size: 100% 20px; background-position: 0 0; margin-bottom: 3px;
}

/* ===== CHECKBOX ===== */
#trafficFormPdfModal .tpf-cb {
    appearance: none; -webkit-appearance: none;
    width: 13px; height: 13px; border: 1.5px solid #000;
    margin-right: 3px; cursor: pointer; position: relative;
    vertical-align: middle; flex-shrink: 0; background: #fff;
}
#trafficFormPdfModal .tpf-cb:checked::after {
    content: '✓'; font-size: 12px; font-weight: 700;
    position: absolute; top: -3px; left: 0px; color: #000;
}
#trafficFormPdfModal .tpf-ck {
    display: inline-flex; align-items: center; margin-right: 14px;
    font-size: 11.5px; white-space: nowrap; vertical-align: middle; cursor: pointer;
}
#trafficFormPdfModal .tpf-bk {
    width: 10px; height: 10px; background: #000;
    display: inline-block; margin-right: 3px; flex-shrink: 0; position: relative; top: 1px;
}
#trafficFormPdfModal .tpf-bh {
    display: flex; align-items: center; font-weight: 600;
    font-size: 11.5px; margin-top: 8px; margin-bottom: 5px;
}
#trafficFormPdfModal .tpf-si { display: flex; align-items: center; font-size: 11.5px; line-height: 1.8; margin-bottom: 5px; }
#trafficFormPdfModal .tpf-si.tpf-forensic-row .tpf-sel { flex: 1; min-width: 0; }
#trafficFormPdfModal .tpf-si.tpf-forensic-row .tpf-inp { flex: 0 1 90px; min-width: 0; font-size: 10.5px; margin-left: 4px; }
#trafficFormPdfModal .tpf-si-no { min-width: 25px; padding-left: 8px; font-size: 11.5px; }
#trafficFormPdfModal .tpf-cg { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; margin-bottom: 5px; }
#trafficFormPdfModal .tpf-i1 { padding-left: 15px; }
#trafficFormPdfModal .tpf-i2 { padding-left: 28px; }

/* ===== FOOTER ===== */
#trafficFormPdfModal .tpf-footer {
    margin-top: auto; font-size: 9.5px; color: #333;
    display: flex; justify-content: space-between; align-items: flex-end; flex-shrink: 0;
}
#trafficFormPdfModal .tpf-footer-left { flex: 1; }
#trafficFormPdfModal .tpf-footer-right { text-align: right; white-space: nowrap; line-height: 1.3; }

/* ===== Buttons ===== */
#trafficFormPdfModal .tpf-add-btn {
    font-size: 10px; padding: 1px 8px; border: 1px dashed #888;
    background: #f8f8f8; cursor: pointer; color: #333; margin: 3px 0; font-family: 'Sarabun', sans-serif;
}
#trafficFormPdfModal .tpf-add-btn:hover { background: #e0e0e0; }
#trafficFormPdfModal .tpf-del-btn {
    font-size: 9px; padding: 0 4px; border: 1px solid #ccc;
    background: #fff; cursor: pointer; color: #c00; font-family: 'Sarabun', sans-serif; line-height: 1.5;
}
#trafficFormPdfModal .tpf-del-btn:hover { background: #fee; }

/* ===== Trace Table ===== */
#trafficFormPdfModal .tpf-trace-table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 4px; }
#trafficFormPdfModal .tpf-trace-table th,
#trafficFormPdfModal .tpf-trace-table td { border: 1px solid #000; padding: 2px 3px; text-align: center; vertical-align: middle; }
#trafficFormPdfModal .tpf-trace-table input[type="text"] {
    border: none; border-bottom: 1px dotted #888; background: transparent; font-size: 10px; width: calc(100% - 20px);
    padding: 1px 2px; outline: none; font-family: 'Sarabun', sans-serif; text-align: center; display: inline-block;
}
#trafficFormPdfModal .tpf-trace-table input[type="number"] {
    border: none; border-bottom: 1px dotted #888; background: transparent; font-size: 10px; width: 100%;
    padding: 1px 2px; outline: none; font-family: 'Sarabun', sans-serif; text-align: center;
}
#trafficFormPdfModal .tpf-trace-table .btn-hw-open {
    display: inline-block; vertical-align: middle; width: 18px; padding: 0; margin: 0;
    border: none; background: none; color: #6366f1; font-size: 0.65rem; cursor: pointer;
}

/* ===== Signature ===== */
#trafficFormPdfModal .tpf-sig-box {
    border: 1px solid #ccc; background: #fafafa; min-height: 60px; cursor: crosshair; position: relative;
}
#trafficFormPdfModal .tpf-sig-box canvas { width: 100%; height: 100%; display: block; }

/* ===== Photo Grid 5×7 (35 photos/page) ===== */
#trafficFormPdfModal .tpf-photo-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 6px 4px;
    align-content: start;
}
#trafficFormPdfModal .tpf-photo-cell {
    position: relative;
    border: 1.5px solid #333;
    overflow: hidden;
    background: #fff;
}
#trafficFormPdfModal .tpf-photo-cell img {
    width: 100%;
    aspect-ratio: 4/3;
    object-fit: cover;
    display: block;
}
#trafficFormPdfModal .tpf-cell-delete {
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
#trafficFormPdfModal .tpf-cell-filename {
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
#trafficFormPdfModal .tpf-photo-dropzone {
    border: 2px dashed #b0bec5;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    background: #f8f9fa;
    margin-bottom: 8px;
    transition: all 0.2s;
}
#trafficFormPdfModal .tpf-photo-dropzone:hover,
#trafficFormPdfModal .tpf-photo-dropzone.dragover {
    border-color: #2196F3;
    background: #e3f2fd;
}
#trafficFormPdfModal .tpf-add-photo-page-btn {
    display: block; margin: 8px auto; padding: 4px 16px;
    border: 1px dashed #888; background: #f0f0f0; cursor: pointer;
    font-size: 11px; font-family: 'Sarabun', sans-serif; color: #333;
}
#trafficFormPdfModal .tpf-add-photo-page-btn:hover { background: #e0e0e0; }

/* ===== Vehicle card ===== */
#trafficFormPdfModal .tpf-vehicle-card {
    border: 1px solid #999; padding: 4px 6px; margin-bottom: 5px; background: #fafafa;
}
#trafficFormPdfModal .tpf-vehicle-header {
    font-size: 11px; font-weight: 600; margin-bottom: 3px; display: flex; justify-content: space-between; align-items: center;
}

/* ===== Analysis vehicle card ===== */
#trafficFormPdfModal .tpf-analysis-card {
    border: 1.5px solid #000; padding: 5px 6px; margin-bottom: 8px;
}
#trafficFormPdfModal .tpf-analysis-header {
    font-size: 11.5px; font-weight: 700; margin-bottom: 4px; border-bottom: 1px solid #aaa; padding-bottom: 2px;
}

@media print {
    body > *:not(#trafficFormPdfModal),
    .navbar, .sidebar, .main-content, #status-banner,
    .modal-backdrop, .swal2-container,
    #trafficFormPdfModal .modal-header,
    #trafficFormPdfModal .modal-footer,
    #trafficFormPdfModal .csims-loading-overlay,
    #trafficFormPdfModal .tpf-add-btn,
    #trafficFormPdfModal .tpf-del-btn,
    #trafficFormPdfModal .tpf-add-photo-page-btn,
    #trafficFormPdfModal .btn-hw-open,
    #trafficFormPdfModal .btn-sketch-eraser,
    #trafficFormPdfModal input[type="color"],
    #trafficFormPdfModal .form-check.form-switch,
    #trafficFormPdfModal .d-flex.align-items-center.gap-3 {
        display: none !important;
    }
    #trafficFormPdfModal,
    #trafficFormPdfModal .modal-dialog,
    #trafficFormPdfModal .modal-content,
    #trafficFormPdfModal .tpf-body {
        position: static !important; display: block !important;
        width: auto !important; max-width: none !important;
        max-height: none !important; height: auto !important;
        overflow: visible !important; margin: 0 !important;
        padding: 0 !important; background: #fff !important;
        border: none !important; box-shadow: none !important;
        transform: none !important; opacity: 1 !important;
    }
    @page { size: A4 portrait; margin: 0; }
    #trafficFormPdfModal .tpf-page {
        width: 100% !important; min-height: auto !important;
        height: auto !important; margin: 0 !important;
        padding: 8mm 10mm 5mm 10mm !important;
        box-shadow: none !important; overflow: visible !important;
        page-break-after: always; page-break-inside: auto;
    }
    #trafficFormPdfModal .tpf-page:last-of-type { page-break-after: auto; }
    #trafficFormPdfModal .tpf-sec-row { page-break-inside: avoid; }
    #trafficFormPdfModal .tpf-form-body { page-break-inside: auto; }
    #trafficFormPdfModal .tpf-header { page-break-after: avoid; }
    #trafficFormPdfModal .tpf-footer { page-break-before: avoid; }
    #trafficFormPdfModal .tpf-photo-grid { gap: 2px !important; }
    #trafficFormPdfModal .tpf-photo-cell { page-break-inside: avoid; }
    #trafficFormPdfModal .tpf-photo-dropzone { display: none !important; }
    #trafficFormPdfModal .tpf-cell-delete { display: none !important; }
    #trafficFormPdfModal .tpf-sig-box,
    #trafficFormPdfModal .tpf-sketch-area { page-break-inside: avoid; }
    #trafficFormPdfModal .tpf-inp, #trafficFormPdfModal .tpf-inp-m,
    #trafficFormPdfModal .tpf-inp-full, #trafficFormPdfModal .tpf-inp-s,
    #trafficFormPdfModal .tpf-sel, #trafficFormPdfModal .tpf-ta {
        border-bottom-color: #888 !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    #trafficFormPdfModal .tpf-cb {
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
}
</style>

<!-- ===== MODAL ===== -->
<div class="modal fade" id="trafficFormPdfModal" aria-labelledby="trafficFormPdfModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 860px; margin: 1.75rem auto;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="trafficFormPdfModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body tpf-body p-0" style="max-height: 80vh; overflow-y: auto; position:relative;">
                <!-- Loading Overlay -->
                <div id="trafficPdfLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track">
                            <div class="csims-bar-fill"></div>
                        </div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>
                <form id="trafficFormPdf" novalidate>
                    <input type="hidden" id="tpf_receiveNoti_id" name="receiveNoti_id">
                    <input type="hidden" id="tpf_doc_no" name="doc_no">
                    <input type="hidden" id="tpf_report_no" name="report_no">

                    <!-- Switch กลับไปฟอร์มมาตรฐาน -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="editInfoTrafficPdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountTrafficPdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToStdFormTraffic" checked style="width: 3rem; height: 1.5rem; cursor: pointer;" onchange="if(!this.checked){ this.checked=true; switchToTrafficStandardForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToStdFormTraffic" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ================================================================ -->
<!-- ========================= PAGE 1 ============================== -->
<!-- ================================================================ -->
<div class="tpf-page">

    <div class="tpf-header">
        <div class="tpf-header-logo">
            <img src="./images/office-of-police-forensic-icon.jpg" alt="ตราสำนักงานตำรวจแห่งชาติ">
        </div>
        <div class="tpf-header-center">
            <div class="tpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="tpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับจราจร</div>
        </div>
        <div class="tpf-header-right">
            <div class="tpf-doc-box">
                <div class="tpf-doc-line">รายงานที่ <span id="tpf_report_no_display" style="display:inline-block;min-width:60px;border-bottom:1px dotted #888;text-align:center;"></span> / 25<span id="tpf_report_year_display" style="display:inline-block;min-width:30px;border-bottom:1px dotted #888;text-align:center;"><?= substr((date('Y') + 543), -2) ?></span></div>
                <div class="tpf-doc-line">หน้าที่ <span class="tpf-cur-page">1</span> / <span class="tpf-total-page">3</span></div>
            </div>
        </div>
    </div>

    <div class="tpf-form-body">

        <!-- LEFT COLUMN -->
        <div class="tpf-col-left">
            <div class="tpf-row-header">
                <div class="tpf-lbl-seq">ลำดับ</div>
                <div class="tpf-lbl-data">ข้อมูล</div>
            </div>

            <!-- 1. การรับแจ้งเหตุ -->
            <div class="tpf-sec-row">
                <div class="tpf-sec-label">
                    <span class="tpf-sec-num">1.</span>
                    <span class="tpf-sec-txt">การรับ<br>แจ้งเหตุ</span>
                </div>
                <div class="tpf-sec-body">
                    <div class="tpf-fr">
                        <span class="tpf-fl">คดี</span>
                        <input type="text" class="tpf-inp" name="case_doc_no" id="tpf_case_doc_no" readonly style="text-align:center; background:#f5f5f5;">
                    </div>
                    <div class="tpf-fr">
                        <span class="tpf-fl">วันที่</span>
                        <input type="date" class="tpf-inp-m" name="case_date" id="tpf_case_date" value="<?= $tpfTodayDate ?>">
                        <span class="tpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="tpf-inp" name="case_time" id="tpf_case_time" value="<?= $tpfTodayTime ?>" style="text-align:center;">
                    </div>
                    <div class="tpf-fr">
                        <span class="tpf-fl" style="margin-right:10px;">การรับแจ้ง</span>
                        <label class="tpf-ck"><input type="checkbox" class="tpf-cb" name="notify_method[]" value="ทางโทรศัพท์">ทางโทรศัพท์</label>
                        <label class="tpf-ck"><input type="checkbox" class="tpf-cb" name="notify_method[]" value="ทางวิทยุสื่อสาร">ทางวิทยุสื่อสาร</label>
                    </div>
                    <div class="tpf-fr" style="padding-left:38px;">
                        <label class="tpf-ck"><input type="checkbox" class="tpf-cb" name="notify_method[]" value="ทางหนังสือ">ทางหนังสือ</label>
                        <label class="tpf-ck"><input type="checkbox" class="tpf-cb" name="notify_method[]" value="อื่นๆ">อื่นๆ</label>
                        <input type="text" class="tpf-inp" name="notify_method_other_text" id="tpf_notify_other_text">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="tpf_notify_other_text" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="tpf-fr">
                        <span class="tpf-fl">สน./สภ.</span>
                        <select class="tpf-sel" name="police_station" id="tpf_police_station">
                            <?= $tpfPoliceStationOptions ?>
                        </select>
                    </div>
                    <div class="tpf-fr">
                        <span class="tpf-fl">พนักงานสอบสวน</span>
                        <input type="text" class="tpf-inp" name="investigator_name" id="tpf_investigator_name">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="tpf_investigator_name" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="tpf-fr">
                        <span class="tpf-fl">หมายเลขโทรศัพท์</span>
                        <input type="tel" class="tpf-inp" name="investigator_phone" id="tpf_investigator_phone">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="tpf_investigator_phone" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                </div>
            </div>

            <!-- 2. สถานที่เกิดเหตุ -->
            <div class="tpf-sec-row">
                <div class="tpf-sec-label">
                    <span class="tpf-sec-num">2.</span>
                    <span class="tpf-sec-txt">สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="tpf-sec-body">
                    <div style="margin-bottom:6px;">
                        <span class="tpf-fl-b">สถานที่เกิดเหตุ</span>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="tpf_crime_location" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <textarea class="tpf-ta" name="crime_location" id="tpf_crime_location" rows="2"></textarea>
                    </div>

                    <!-- รถของกลาง -->
                    <div id="tpf_vehicle_container">
                        <div class="tpf-vehicle-card">
                            <div class="tpf-vehicle-header">
                                <span>รถของกลางที่ 1</span>
                            </div>
                            <div class="tpf-fr">
                                <span class="tpf-fl">รถ</span>
                                <input type="text" class="tpf-inp" name="vehicle_detail[]" placeholder="ลักษณะรถ">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                <span class="tpf-fl">ยี่ห้อ</span>
                                <input type="text" class="tpf-inp" name="vehicle_brand[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                            <div class="tpf-fr">
                                <span class="tpf-fl">รุ่น</span>
                                <input type="text" class="tpf-inp" name="vehicle_model[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                <span class="tpf-fl">สี</span>
                                <input type="text" class="tpf-inp" name="vehicle_color[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                            <div class="tpf-fr">
                                <span class="tpf-fl">แผ่นป้าย</span>
                                <label class="tpf-ck"><input type="checkbox" class="tpf-cb" name="vehicle_plate_attach[]" value="ติด" checked>ติด</label>
                                <label class="tpf-ck"><input type="checkbox" class="tpf-cb" name="vehicle_plate_none[]" value="ไม่ติด">ไม่ติด</label>
                                <span class="tpf-fl">เลขทะเบียน</span>
                                <input type="text" class="tpf-inp" name="vehicle_plate_no[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="tpf-add-btn" onclick="tpfAddVehicle()">+ เพิ่มรถของกลาง</button>
                </div>
            </div>

            <!-- 3. วันเวลาที่ทราบเหตุ -->
            <div class="tpf-sec-row">
                <div class="tpf-sec-label">
                    <span class="tpf-sec-num">3.</span>
                    <span class="tpf-sec-txt">วันเวลา<br>ที่ทราบ<br>เหตุ/เกิด<br>เหตุ</span>
                </div>
                <div class="tpf-sec-body">
                    <div class="tpf-fr"><span class="tpf-fl-b">วันเวลาที่ผู้เสียหาย ทราบเหตุ/เกิดเหตุ</span></div>
                    <div class="tpf-fr">
                        <span class="tpf-fl">วันที่</span>
                        <input type="date" class="tpf-inp-m" name="victim_know_date" id="tpf_victim_know_date" value="<?= $tpfTodayDate ?>">
                        <span class="tpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="tpf-inp" name="victim_know_time" id="tpf_victim_know_time" value="<?= $tpfTodayTime ?>" style="text-align:center;">
                    </div>
                    <div class="tpf-fr" style="margin-top:2px;"><span class="tpf-fl-b">วันเวลาที่พนักงานสอบสวนทราบเหตุ</span></div>
                    <div class="tpf-fr">
                        <span class="tpf-fl">วันที่</span>
                        <input type="date" class="tpf-inp-m" name="officer_know_date" id="tpf_officer_know_date" value="<?= $tpfTodayDate ?>">
                        <span class="tpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="tpf-inp" name="officer_know_time" id="tpf_officer_know_time" value="<?= $tpfTodayTime ?>" style="text-align:center;">
                    </div>
                </div>
            </div>

            <!-- 4. วันเวลาที่ตรวจเหตุ -->
            <div class="tpf-sec-row">
                <div class="tpf-sec-label">
                    <span class="tpf-sec-num">4.</span>
                    <span class="tpf-sec-txt">วัน<br>เวลาที่<br>ตรวจ<br>เหตุ</span>
                </div>
                <div class="tpf-sec-body">
                    <div class="tpf-fr"><span class="tpf-fl-b">วันเวลาที่ทำการตรวจสถานที่เกิดเหตุ</span></div>
                    <div class="tpf-fr">
                        <span class="tpf-fl">วันที่</span>
                        <input type="date" class="tpf-inp-m" name="inspect_date" id="tpf_inspect_date" value="<?= $tpfTodayDate ?>">
                        <span class="tpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="tpf-inp" name="inspect_time" id="tpf_inspect_time" value="<?= $tpfTodayTime ?>" style="text-align:center;">
                    </div>
                </div>
            </div>

            <!-- 5. ผู้ตรวจสถานที่เกิดเหตุ -->
            <div class="tpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="tpf-sec-label">
                    <span class="tpf-sec-num">5.</span>
                    <span class="tpf-sec-txt">ผู้ตรวจ<br>สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="tpf-sec-body">
                    <div class="tpf-bh" style="margin-top:0;"><span class="tpf-bk"></span><span>ผู้ตรวจสถานที่เกิดเหตุ</span></div>
                    <div id="tpf_inspector_container">
                        <div class="tpf-si tpf-inspector-row">
                            <span class="tpf-si-no">5.1</span>
                            <select class="tpf-sel" name="inspector_id[]">
                                <?= $tpfInspectorOptions ?>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="tpf-add-btn" onclick="tpfAddInspector()">+ เพิ่มผู้ตรวจ</button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="tpf-col-right">
            <div class="tpf-row-header">
                <div class="tpf-lbl-seq">ลำดับ</div>
                <div class="tpf-lbl-data">ข้อมูล</div>
            </div>

            <!-- 6. จุดประสงค์ในการตรวจ -->
            <div class="tpf-sec-row">
                <div class="tpf-sec-label">
                    <span class="tpf-sec-num">6.</span>
                    <span class="tpf-sec-txt">จุดประสงค์<br>ในการตรวจ</span>
                </div>
                <div class="tpf-sec-body">
                    <div class="tpf-bh" style="margin-top:0;"><span class="tpf-bk"></span><span>จุดประสงค์ในการตรวจ</span></div>

                    <div class="tpf-cg tpf-i1" style="margin-top:4px;">
                        <label class="tpf-ck" style="margin-right:0;"><input type="checkbox" class="tpf-cb" name="inspect_purpose[]" value="1">1.เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนระหว่างรถของกลาง</label>
                    </div>
                    <div class="tpf-i2 tpf-fr" style="font-size:11.5px;">
                        <input type="text" class="tpf-inp-s" name="purpose_qty_1" style="max-width:40px;">
                        <span class="tpf-fl">คัน หรือไม่ อย่างไร</span>
                    </div>

                    <div class="tpf-cg tpf-i1">
                        <label class="tpf-ck" style="margin-right:0;"><input type="checkbox" class="tpf-cb" name="inspect_purpose[]" value="2">2. เพื่อทราบว่ารถของกลาง</label>
                        <input type="text" class="tpf-inp-s" name="purpose_qty_2" style="max-width:40px;">
                        <span class="tpf-fl">คัน มีการเฉี่ยวชนกัน</span>
                    </div>
                    <div class="tpf-i2 tpf-fr">
                        <span class="tpf-fl">หรือไม่ อย่างไร</span>
                    </div>

                    <div class="tpf-cg tpf-i1">
                        <label class="tpf-ck"><input type="checkbox" class="tpf-cb" name="inspect_purpose[]" value="3">3. เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนที่รถของกลาง</label>
                    </div>
                    <div class="tpf-i2 tpf-fr">
                        <span class="tpf-fl">ทั้งหมดนี้หรือไม่ และมีลักษณะการเฉี่ยวชนอย่างไร</span>
                    </div>

                    <div class="tpf-cg tpf-i1">
                        <label class="tpf-ck"><input type="checkbox" class="tpf-cb" name="inspect_purpose[]" value="4">4. เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนระหว่างรถของกลาง</label>
                    </div>
                    <div class="tpf-i2 tpf-fr">
                        <span class="tpf-fl" style="margin-right:0;">หรือไม่ อย่างไร หรือ</span>
                        <input type="text" class="tpf-inp" name="purpose_other_text" id="tpf_purpose_other_text">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="tpf_purpose_other_text" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                </div>
            </div>

            <!-- 7. ผู้ตรวจพิสูจน์ -->
            <div class="tpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="tpf-sec-label">
                    <span class="tpf-sec-num">7.</span>
                    <span class="tpf-sec-txt">ผู้ตรวจ<br>พิสูจน์</span>
                </div>
                <div class="tpf-sec-body">
                    <div class="tpf-bh" style="margin-top:0;"><span class="tpf-bk"></span><span>ผู้ตรวจพิสูจน์</span></div>
                    <div id="tpf_forensic_container">
                        <div class="tpf-si tpf-forensic-row">
                            <span class="tpf-si-no">7.1</span>
                            <select class="tpf-sel tpf-user-select" name="forensic_id[]" data-pos-target="#tpf_forensic_pos_0">
                                <?= $tpfInspectorOptions ?>
                            </select>
                            <input type="text" class="tpf-inp" id="tpf_forensic_pos_0" name="forensic_position[]" placeholder="ตำแหน่ง (แสดงอัตโนมัติ)" readonly>
                        </div>
                    </div>
                    <button type="button" class="tpf-add-btn" onclick="tpfAddForensic()">+ เพิ่มผู้ตรวจพิสูจน์</button>
                </div>
            </div>
        </div>
    </div>

    <div class="tpf-footer">
        <div class="tpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="tpf-footer-right">F-CS-... แก้ไขครั้งที่ ...<br>แก้ไขวันที่ ...<br>เริ่มใช้ ...</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 2 ============================== -->
<!-- ================================================================ -->
<div class="tpf-page">

    <div class="tpf-header">
        <div class="tpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="tpf-header-center">
            <div class="tpf-title-main">ตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="tpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับจราจร</div>
        </div>
        <div class="tpf-header-right">
            <div class="tpf-doc-box">
                <div class="tpf-doc-line">รายงานที่ <span class="tpf-rpt-no-mirror"></span> / 25<span class="tpf-rpt-year-mirror"></span></div>
                <div class="tpf-doc-line">หน้าที่ <span class="tpf-cur-page">2</span> / <span class="tpf-total-page">3</span></div>
            </div>
        </div>
    </div>

    <div class="tpf-form-body">

        <!-- LEFT COLUMN -->
        <div class="tpf-col-left">
            <div class="tpf-row-header"><div class="tpf-lbl-seq">ลำดับ</div><div class="tpf-lbl-data">ข้อมูล</div></div>

            <!-- 8. ผลการตรวจพิสูจน์ -->
            <div class="tpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="tpf-sec-label">
                    <span class="tpf-sec-num">8.</span>
                    <span class="tpf-sec-txt">ผลการตรวจ<br>พิสูจน์</span>
                </div>
                <div class="tpf-sec-body">
                    <div class="tpf-bh" style="margin-top:0;"><span class="tpf-bk"></span><span>พฤติการณ์คดี</span> <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="tpf_case_behavior" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="tpf-ta" name="case_behavior" id="tpf_case_behavior" rows="3"></textarea>

                    <div class="tpf-fr">
                        <span class="tpf-fl">ได้ทำการตรวจพิสูจน์</span>
                        <input type="text" class="tpf-inp" name="forensic_inspection_target" id="tpf_inspection_target">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="tpf_inspection_target" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="tpf-fr">
                        <span class="tpf-fl">ที่</span>
                        <input type="text" class="tpf-inp" name="forensic_inspection_location" id="tpf_inspection_location">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="tpf_inspection_location" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="tpf-fr">
                        <span class="tpf-fl">การตรวจพิสูจน์</span>
                        <select class="tpf-inp" name="forensic_lab_unit" id="tpf_forensic_lab_unit">
                            <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                            <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                            <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                            <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                            <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                            <option value="document">กลุ่มงานตรวจเอกสาร</option>
                        </select>
                    </div>
                    <div class="tpf-fr">
                        <span class="tpf-fl">เมื่อวันที่</span>
                        <input type="date" class="tpf-inp-m" name="forensic_inspect_date" id="tpf_forensic_inspect_date" value="<?= $tpfTodayDate ?>">
                        <span class="tpf-fl">เวลาประมาณ</span>
                        <input type="time" class="tpf-inp" name="forensic_inspect_time" id="tpf_forensic_inspect_time" value="<?= $tpfTodayTime ?>" style="text-align:center;">
                        <span class="tpf-fl">น.</span>
                    </div>

                    <!-- รถของกลางเพื่อตรวจพิสูจน์ (Analysis vehicles) -->
                    <div style="margin-top:8px; border-top:1px solid #eee; padding-top:6px;">
                        <div id="tpf_analysis_vehicle_container">
                            <div class="tpf-analysis-card" data-v-idx="1">
                                <div class="tpf-analysis-header">
                                    <span>รถของกลางที่ 1</span>
                                    <button type="button" class="tpf-del-btn" onclick="tpfRemoveAnalysisVehicle(this)" style="float:right; display:none;">×</button>
                                </div>
                                <div class="tpf-fr">
                                    <span class="tpf-fl">สภาพรถ</span>
                                    <input type="text" class="tpf-inp" name="forensic_v_condition[]">
                                    <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>
                                <div class="tpf-fr">
                                    <span class="tpf-fl">การดัดแปลง</span>
                                    <label class="tpf-ck"><input type="checkbox" class="tpf-cb tpf-mod-check" name="forensic_v_mod_status_1" value="ไม่มี" checked>ไม่มี</label>
                                    <label class="tpf-ck"><input type="checkbox" class="tpf-cb tpf-mod-check" name="forensic_v_mod_status_1" value="มี">มี</label>
                                    <input type="text" class="tpf-inp" name="forensic_v_mod_detail[]" placeholder="รายละเอียด..." disabled>
                                    <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                </div>

                                <!-- ร่องรอย 4 ด้าน -->
                                <div style="margin-top:4px;">
                                    <div class="tpf-fl-b" style="font-size:10.5px; margin-bottom:3px;">ร่องรอยความเสียหาย</div>

                                    <!-- ด้านหน้า -->
                                    <div style="margin-bottom:4px;">
                                        <div style="font-size:10px; font-weight:600; margin-bottom:2px;">ด้านหน้า</div>
                                        <table class="tpf-trace-table">
                                            <thead><tr><th style="width:65%;">ร่องรอย/บริเวณ/ตำแหน่ง</th><th>สูงจากพื้น(cm.)</th><th style="width:20px;"></th></tr></thead>
                                            <tbody class="tpf-trace-body" data-side="front" data-vidx="1">
                                                <tr><td><input type="text" name="trace_front_detail_1[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></td><td><input type="number" name="trace_front_height_1[]"></td><td></td></tr>
                                            </tbody>
                                        </table>
                                        <button type="button" class="tpf-add-btn" onclick="tpfAddTraceRow(this, 'front', 1)">+ ร่องรอยด้านหน้า</button>
                                    </div>

                                    <!-- ด้านซ้าย -->
                                    <div style="margin-bottom:4px;">
                                        <div style="font-size:10px; font-weight:600; margin-bottom:2px;">ด้านซ้าย</div>
                                        <table class="tpf-trace-table">
                                            <thead><tr><th style="width:65%;">ร่องรอย/บริเวณ/ตำแหน่ง</th><th>สูงจากพื้น(cm.)</th><th style="width:20px;"></th></tr></thead>
                                            <tbody class="tpf-trace-body" data-side="left" data-vidx="1">
                                                <tr><td><input type="text" name="trace_left_detail_1[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></td><td><input type="number" name="trace_left_height_1[]"></td><td></td></tr>
                                            </tbody>
                                        </table>
                                        <button type="button" class="tpf-add-btn" onclick="tpfAddTraceRow(this, 'left', 1)">+ ร่องรอยด้านซ้าย</button>
                                    </div>

                                    <!-- ด้านขวา -->
                                    <div style="margin-bottom:4px;">
                                        <div style="font-size:10px; font-weight:600; margin-bottom:2px;">ด้านขวา</div>
                                        <table class="tpf-trace-table">
                                            <thead><tr><th style="width:65%;">ร่องรอย/บริเวณ/ตำแหน่ง</th><th>สูงจากพื้น(cm.)</th><th style="width:20px;"></th></tr></thead>
                                            <tbody class="tpf-trace-body" data-side="right" data-vidx="1">
                                                <tr><td><input type="text" name="trace_right_detail_1[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></td><td><input type="number" name="trace_right_height_1[]"></td><td></td></tr>
                                            </tbody>
                                        </table>
                                        <button type="button" class="tpf-add-btn" onclick="tpfAddTraceRow(this, 'right', 1)">+ ร่องรอยด้านขวา</button>
                                    </div>

                                    <!-- ด้านหลัง -->
                                    <div style="margin-bottom:4px;">
                                        <div style="font-size:10px; font-weight:600; margin-bottom:2px;">ด้านหลัง</div>
                                        <table class="tpf-trace-table">
                                            <thead><tr><th style="width:65%;">ร่องรอย/บริเวณ/ตำแหน่ง</th><th>สูงจากพื้น(cm.)</th><th style="width:20px;"></th></tr></thead>
                                            <tbody class="tpf-trace-body" data-side="back" data-vidx="1">
                                                <tr><td><input type="text" name="trace_back_detail_1[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></td><td><input type="number" name="trace_back_height_1[]"></td><td></td></tr>
                                            </tbody>
                                        </table>
                                        <button type="button" class="tpf-add-btn" onclick="tpfAddTraceRow(this, 'back', 1)">+ ร่องรอยด้านหลัง</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="tpf-add-btn" onclick="tpfAddAnalysisVehicle()">+ เพิ่มรถของกลางเพื่อตรวจพิสูจน์</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="tpf-col-right">
            <div class="tpf-row-header"><div class="tpf-lbl-seq">ลำดับ</div><div class="tpf-lbl-data">ข้อมูล</div></div>

            <!-- 9. ผลการตรวจเปรียบเทียบ -->
            <div class="tpf-sec-row">
                <div class="tpf-sec-label">
                    <span class="tpf-sec-num">9.</span>
                    <span class="tpf-sec-txt">ผลการตรวจ<br>เปรียบเทียบ</span>
                </div>
                <div class="tpf-sec-body">
                    <div class="tpf-bh" style="margin-top:0;"><span class="tpf-bk"></span><span>ผลการตรวจเปรียบเทียบ</span></div>
                    <div style="font-size:11.5px; line-height:1.6; margin-bottom:6px;">
                        <span class="tpf-fl tpf-i1">จากการตรวจเปรียบเทียบสภาพร่องรอยความเสียหายและ</span>
                        <span class="tpf-fl tpf-i1">การแลกเปลี่ยนวัตถุพยานของรถของกลางทั้ง</span>
                        <input type="text" class="tpf-inp-s" name="forensic_compare_v_count" style="max-width:40px;">
                        <span class="tpf-fl">รายการ พบว่า</span>
                    </div>

                    <div id="tpf_compare_container">
                        <div class="tpf-compare-row" style="margin-bottom:6px; padding:3px 0; border-top:1px dotted #ccc;">
                            <div class="tpf-fr">
                                <span class="tpf-fl-b" style="min-width:25px;">9.1</span>
                                <span class="tpf-fl">รอย</span>
                                <input type="text" class="tpf-inp" name="compare_trace_type[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                            <div class="tpf-fr">
                                <span class="tpf-fl">บริเวณ</span>
                                <input type="text" class="tpf-inp" name="compare_area_a[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                                <span class="tpf-fl">ตามผลการตรวจในข้อ</span>
                                <input type="text" class="tpf-inp" name="compare_ref_a[]" style="max-width:80px;">
                            </div>
                            <div class="tpf-fr">
                                <span class="tpf-fl">มีลักษณะร่องรอยเข้ากันได้กับบริเวณ</span>
                                <input type="text" class="tpf-inp" name="compare_area_b[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                            <div class="tpf-fr">
                                <span class="tpf-fl">ตามผลการตรวจในข้อ</span>
                                <input type="text" class="tpf-inp" name="compare_ref_b[]" style="max-width:80px;">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="tpf-add-btn" onclick="tpfAddCompareRow()">+ เพิ่มรายการเปรียบเทียบ</button>
                </div>
            </div>

            <!-- 10. ความเห็น -->
            <div class="tpf-sec-row" style="flex:1;">
                <div class="tpf-sec-label">
                    <span class="tpf-sec-num">10.</span>
                    <span class="tpf-sec-txt">ความเห็น</span>
                </div>
                <div class="tpf-sec-body">
                    <div class="tpf-bh" style="margin-top:0;"><span class="tpf-bk"></span><span>ความเห็น</span> <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="tpf_forensic_opinion" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div style="font-size:11.5px; line-height:1.6;">
                        <span class="tpf-fl tpf-i1">จากผลการตรวจในข้อ 8 และ 9</span>
                        <textarea class="tpf-ta" name="forensic_opinion" id="tpf_forensic_opinion" rows="3"></textarea>
                        <span class="tpf-fl">จนได้รับความเสียหายดังที่ปรากฏ</span>
                    </div>
                </div>
            </div>

            <!-- 11. การส่งมอบคืนสถานที่เกิดเหตุ -->
            <div class="tpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="tpf-sec-label">
                    <span class="tpf-sec-num">11.</span>
                    <span class="tpf-sec-txt">การส่ง<br>มอบคืน<br>สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="tpf-sec-body">
                    <!-- วันเวลาตรวจเสร็จ -->
                    <div class="tpf-bh" style="margin-top:0;"><span class="tpf-bk"></span><span>วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น</span></div>
                    <div class="tpf-fr tpf-i1">
                        <span class="tpf-fl">วันที่</span>
                        <input type="date" class="tpf-inp" name="inspection_end_date" id="tpf_inspection_end_date" value="<?= $tpfTodayDate ?>" style="text-align:center;">
                        <span class="tpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="tpf-inp" name="inspection_end_time" id="tpf_inspection_end_time" value="<?= $tpfTodayTime ?>" style="text-align:center;">
                        <span class="tpf-fl">น.</span>
                    </div>

                    <!-- การส่งมอบสถานที่ -->
                    <div class="tpf-bh" style="margin-top:6px;"><span class="tpf-bk"></span><span>การส่งมอบสถานที่เกิดเหตุ</span></div>

                    <!-- ผู้รับมอบ -->
                    <div class="tpf-fr" style="margin-top:6px; align-items:flex-end;">
                        <span class="tpf-fl">ลงชื่อ</span>
                        <div style="flex:1; min-height:50px; border-bottom:1px dotted #888; position:relative;">
                            <canvas id="tpf_sig_receiver" style="width:100%; height:50px; cursor:crosshair;"></canvas>
                            <input type="hidden" name="receiver_signature_data_traffic" id="tpf_receiver_sig_data">
                        </div>
                        <span class="tpf-fl">ผู้รับมอบสถานที่เกิดเหตุ</span>
                    </div>
                    <div class="tpf-fr">
                        <span class="tpf-fl" style="visibility:hidden;">ลงชื่อ</span>
                        <span class="tpf-fl">(</span>
                        <select class="tpf-sel tpf-user-select" name="receiver_name" id="tpf_receiver_name" style="text-align:center;" data-pos-target="#tpf_receiver_position">
                            <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้รับมอบ --', $tpfInspectorOptions) ?>
                        </select>
                        <span class="tpf-fl">)</span>
                    </div>
                    <div class="tpf-fr">
                        <span class="tpf-fl">ตำแหน่ง</span>
                        <input type="text" class="tpf-inp" name="receiver_position" id="tpf_receiver_position" readonly>
                    </div>

                    <!-- ผู้ส่งมอบ -->
                    <div class="tpf-fr" style="margin-top:8px; align-items:flex-end;">
                        <span class="tpf-fl">ลงชื่อ</span>
                        <div style="flex:1; min-height:50px; border-bottom:1px dotted #888; position:relative;">
                            <canvas id="tpf_sig_sender" style="width:100%; height:50px; cursor:crosshair;"></canvas>
                            <input type="hidden" name="sender_signature_data_traffic" id="tpf_sender_sig_data">
                        </div>
                        <span class="tpf-fl">ผู้ส่งมอบสถานที่เกิดเหตุ</span>
                    </div>
                    <div class="tpf-fr">
                        <span class="tpf-fl" style="visibility:hidden;">ลงชื่อ</span>
                        <span class="tpf-fl">(</span>
                        <select class="tpf-sel tpf-user-select" name="sender_name" id="tpf_sender_name" style="text-align:center;" data-pos-target="#tpf_sender_position">
                            <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งมอบ --', $tpfInspectorOptions) ?>
                        </select>
                        <span class="tpf-fl">)</span>
                    </div>
                    <div class="tpf-fr">
                        <span class="tpf-fl">ตำแหน่ง</span>
                        <input type="text" class="tpf-inp" name="sender_position" id="tpf_sender_position" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tpf-footer">
        <div class="tpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="tpf-footer-right">F-CS-... แก้ไขครั้งที่ ...<br>แก้ไขวันที่ ...<br>เริ่มใช้ ...</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 3 (PHOTOS) ===================== -->
<!-- ================================================================ -->
<div class="tpf-page tpf-photo-page" data-photo-page="1">

    <div class="tpf-header">
        <div class="tpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="tpf-header-center">
            <div class="tpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="tpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับจราจร</div>
            <div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ</div>
        </div>
        <div class="tpf-header-right">
            <div class="tpf-doc-box">
                <div class="tpf-doc-line">รายงานที่ <span class="tpf-rpt-no-mirror"></span> / 25<span class="tpf-rpt-year-mirror"></span></div>
                <div class="tpf-doc-line">หน้าที่ <span class="tpf-cur-page">3</span> / <span class="tpf-total-page">3</span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:8px;">
        <div class="tpf-fr" style="margin-bottom:6px;">
            <span class="tpf-fl">วันที่ตรวจสถานที่เกิดเหตุ</span>
            <input type="date" class="tpf-inp" name="photo_inspect_date_traffic" style="text-align:center;">
            <span class="tpf-fl">เวลาประมาณ</span>
            <input type="time" class="tpf-inp" name="photo_inspect_time_traffic" style="text-align:center;">
            <span class="tpf-fl">น.</span>
        </div>
        <div class="tpf-fr" style="flex-wrap:nowrap; margin-bottom:6px;">
            <span class="tpf-fl">รหัสภาพถ่ายที่</span>
            <input type="text" class="tpf-inp" name="photo_id_start_traffic" style="min-width:80px;" readonly>
            <span class="tpf-fl">ถึง</span>
            <input type="text" class="tpf-inp" name="photo_id_end_traffic" style="min-width:80px;" readonly>
            <span class="tpf-fl">จำนวน</span>
            <input type="text" class="tpf-inp-s" name="photo_amount_traffic" style="max-width:45px; text-align:center;" readonly>
            <span class="tpf-fl">ภาพ</span>
        </div>
        <div style="font-size:10.5px; font-style:italic; margin-top:2px;">(ตามภาพถ่ายรวมที่แนบ)</div>
    </div>

    <!-- Drag & Drop zone -->
    <div class="tpf-photo-dropzone" id="tpf_photo_dropzone">
        <i class="fas fa-cloud-upload-alt" style="font-size:1.2rem; color:#90a4ae;"></i>
        <div style="font-size:10px; color:#666; margin-top:2px;">ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือก</div>
    </div>
    <input type="file" id="tpf_photo_input_gallery" accept="image/*" multiple style="display:none;" onchange="tpfPreviewPhotos(this)">
    <input type="file" id="tpf_photo_input_camera" accept="image/*" capture="environment" multiple style="display:none;" onchange="tpfPreviewPhotos(this)">

    <!-- Photo Grid (35 photos per page, populated by JS) -->
    <div class="tpf-photo-grid" id="tpf_photo_grid_1"></div>

    <div class="tpf-footer" style="margin-top:auto;">
        <div class="tpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="tpf-footer-right" style="display:flex; flex-direction:column; align-items:flex-end; gap:2px;">
            <div style="display:flex; align-items:center; gap:4px; font-size:11px;">
                <span>ผู้จดบันทึก</span>
                <select class="tpf-sel" name="photographer_name_traffic" id="tpf_photographer_name" style="width:180px; font-size:10px; padding:1px 2px;">
                    <?= $tpfPhotographerOptions ?>
                </select>
            </div>
            <div style="display:flex; align-items:center; gap:4px; font-size:11px;">
                <span>วัน/เวลา</span>
                <input type="datetime-local" class="tpf-inp" name="photographer_datetime_traffic" id="tpf_photographer_datetime" style="width:180px; font-size:10px; padding:1px 2px; text-align:center;">
            </div>
            <div style="font-size:9px; margin-top:2px;">F-CS-... แก้ไขครั้งที่ ...<br>เริ่มใช้ ...</div>
        </div>
    </div>
</div>

<!-- Container สำหรับหน้ารูปถ่ายเพิ่มเติม -->
<div id="tpf_extra_photo_pages"></div>

<!-- ปุ่มเพิ่มหน้ากระดาษรูปถ่าย -->
<button type="button" class="tpf-add-photo-page-btn" onclick="tpfAddPhotoPage()">
    <i class="fas fa-plus me-1"></i> เพิ่มหน้าบันทึกการถ่ายภาพ
</button>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <div>
                    <button type="button" class="btn btn-success btn-sm" id="btn_save_traffic_pdf">
                        <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> ยกเลิก
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== JAVASCRIPT ===== -->
<script>
(function() {
    // ===== Auto-generate page numbers =====
    window.tpfUpdatePageNumbers = function() {
        var pages = document.querySelectorAll('#trafficFormPdfModal .tpf-page');
        var total = pages.length;
        pages.forEach(function(page, idx) {
            var curEl = page.querySelector('.tpf-cur-page');
            var totalEl = page.querySelector('.tpf-total-page');
            if (curEl) curEl.textContent = (idx + 1);
            if (totalEl) totalEl.textContent = total;
        });
    };
    tpfUpdatePageNumbers();

    // ===== Radio-toggle for modification status (mutually exclusive) =====
    document.querySelectorAll('#trafficFormPdfModal .tpf-mod-check').forEach(function(cb) {
        cb.addEventListener('change', function() {
            if (!this.checked) return;
            var name = this.getAttribute('name');
            document.querySelectorAll('#trafficFormPdfModal .tpf-mod-check[name="' + name + '"]').forEach(function(other) {
                if (other !== cb) other.checked = false;
            });
            // Enable/disable detail input
            var card = this.closest('.tpf-analysis-card');
            if (card) {
                var detailInput = card.querySelector('input[name="forensic_v_mod_detail[]"]');
                if (detailInput) {
                    detailInput.disabled = (this.value !== 'มี');
                    if (this.value !== 'มี') detailInput.value = '';
                }
            }
        });
    });

    // ===== Inspector rows =====
    var tpfInspectorIdx = 1;
    window.tpfResetInspectorIdx = function() { tpfInspectorIdx = 1; };
    window.tpfAddInspector = function() {
        tpfInspectorIdx++;
        var c = document.getElementById('tpf_inspector_container');
        var div = document.createElement('div');
        div.className = 'tpf-si tpf-inspector-row';
        div.innerHTML = '<span class="tpf-si-no">5.' + tpfInspectorIdx + '</span>' +
            '<select class="tpf-sel" name="inspector_id[]"><?= addslashes($tpfInspectorOptions) ?></select>' +
            ' <button type="button" class="tpf-del-btn" onclick="this.parentElement.remove()">×</button>';
        c.appendChild(div);
    };

    // ===== Vehicle cards (Section 2) =====
    var tpfVehicleIdx = 1;
    window.tpfResetVehicleIdx = function() { tpfVehicleIdx = 1; };
    window.tpfAddVehicle = function() {
        tpfVehicleIdx++;
        var c = document.getElementById('tpf_vehicle_container');
        var div = document.createElement('div');
        div.className = 'tpf-vehicle-card';
        div.innerHTML = '<div class="tpf-vehicle-header"><span>รถของกลางที่ ' + tpfVehicleIdx + '</span>' +
            '<button type="button" class="tpf-del-btn" onclick="this.closest(\'.tpf-vehicle-card\').remove()">×</button></div>' +
            '<div class="tpf-fr"><span class="tpf-fl">รถ</span><input type="text" class="tpf-inp" name="vehicle_detail[]" placeholder="ลักษณะรถ"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button><span class="tpf-fl">ยี่ห้อ</span><input type="text" class="tpf-inp" name="vehicle_brand[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>' +
            '<div class="tpf-fr"><span class="tpf-fl">รุ่น</span><input type="text" class="tpf-inp" name="vehicle_model[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button><span class="tpf-fl">สี</span><input type="text" class="tpf-inp" name="vehicle_color[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>' +
            '<div class="tpf-fr"><span class="tpf-fl">แผ่นป้าย</span>' +
            '<label class="tpf-ck"><input type="checkbox" class="tpf-cb" name="vehicle_plate_attach[]" value="ติด" checked>ติด</label>' +
            '<label class="tpf-ck"><input type="checkbox" class="tpf-cb" name="vehicle_plate_none[]" value="ไม่ติด">ไม่ติด</label>' +
            '<span class="tpf-fl">เลขทะเบียน</span><input type="text" class="tpf-inp" name="vehicle_plate_no[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>';
        c.appendChild(div);
    };

    // ===== Forensic rows (Section 7) with position =====
    var tpfForensicIdx = 1;
    window.tpfResetForensicIdx = function() { tpfForensicIdx = 1; };
    window.tpfAddForensic = function() {
        tpfForensicIdx++;
        var posId = 'tpf_forensic_pos_' + tpfForensicIdx;
        var c = document.getElementById('tpf_forensic_container');
        var div = document.createElement('div');
        div.className = 'tpf-si tpf-forensic-row';
        div.innerHTML = '<span class="tpf-si-no">7.' + tpfForensicIdx + '</span>' +
            '<select class="tpf-sel tpf-user-select" name="forensic_id[]" data-pos-target="#' + posId + '"><?= addslashes($tpfInspectorOptions) ?></select>' +
            '<input type="text" class="tpf-inp" id="' + posId + '" name="forensic_position[]" placeholder="ตำแหน่ง (แสดงอัตโนมัติ)" readonly>' +
            ' <button type="button" class="tpf-del-btn" onclick="this.parentElement.remove()">×</button>';
        c.appendChild(div);
    };

    // ===== Analysis Vehicle cards (Section 8) =====
    var tpfAnalysisVIdx = 1;
    window.tpfResetAnalysisVIdx = function() { tpfAnalysisVIdx = 1; };
    window.tpfAddAnalysisVehicle = function() {
        tpfAnalysisVIdx++;
        var idx = tpfAnalysisVIdx;
        var c = document.getElementById('tpf_analysis_vehicle_container');
        var div = document.createElement('div');
        div.className = 'tpf-analysis-card';
        div.setAttribute('data-v-idx', idx);

        var sides = ['front', 'left', 'right', 'back'];
        var sideLabels = { front: 'ด้านหน้า', left: 'ด้านซ้าย', right: 'ด้านขวา', back: 'ด้านหลัง' };
        var traceTables = '';
        sides.forEach(function(side) {
            traceTables += '<div style="margin-bottom:4px;">' +
                '<div style="font-size:10px; font-weight:600; margin-bottom:2px;">' + sideLabels[side] + '</div>' +
                '<table class="tpf-trace-table"><thead><tr><th style="width:65%;">ร่องรอย/บริเวณ/ตำแหน่ง</th><th>สูงจากพื้น(cm.)</th><th style="width:20px;"></th></tr></thead>' +
                '<tbody class="tpf-trace-body" data-side="' + side + '" data-vidx="' + idx + '">' +
                '<tr><td><input type="text" name="trace_' + side + '_detail_' + idx + '[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></td><td><input type="number" name="trace_' + side + '_height_' + idx + '[]"></td><td></td></tr>' +
                '</tbody></table>' +
                '<button type="button" class="tpf-add-btn" onclick="tpfAddTraceRow(this, \'' + side + '\', ' + idx + ')">+ ร่องรอย' + sideLabels[side] + '</button>' +
                '</div>';
        });

        div.innerHTML = '<div class="tpf-analysis-header"><span>รถของกลางที่ ' + idx + '</span>' +
            '<button type="button" class="tpf-del-btn" onclick="tpfRemoveAnalysisVehicle(this)" style="float:right;">×</button></div>' +
            '<div class="tpf-fr"><span class="tpf-fl">สภาพรถ</span><input type="text" class="tpf-inp" name="forensic_v_condition[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>' +
            '<div class="tpf-fr"><span class="tpf-fl">การดัดแปลง</span>' +
            '<label class="tpf-ck"><input type="checkbox" class="tpf-cb tpf-mod-check" name="forensic_v_mod_status_' + idx + '" value="ไม่มี" checked>ไม่มี</label>' +
            '<label class="tpf-ck"><input type="checkbox" class="tpf-cb tpf-mod-check" name="forensic_v_mod_status_' + idx + '" value="มี">มี</label>' +
            '<input type="text" class="tpf-inp" name="forensic_v_mod_detail[]" placeholder="รายละเอียด..." disabled><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>' +
            '<div style="margin-top:4px;"><div class="tpf-fl-b" style="font-size:10.5px; margin-bottom:3px;">ร่องรอยความเสียหาย</div>' +
            traceTables + '</div>';
        c.appendChild(div);

        // Re-bind mod-check for new card
        div.querySelectorAll('.tpf-mod-check').forEach(function(cb) {
            cb.addEventListener('change', function() {
                if (!this.checked) return;
                var name = this.getAttribute('name');
                div.querySelectorAll('.tpf-mod-check[name="' + name + '"]').forEach(function(other) {
                    if (other !== cb) other.checked = false;
                });
                var detailInput = div.querySelector('input[name="forensic_v_mod_detail[]"]');
                if (detailInput) {
                    detailInput.disabled = (this.value !== 'มี');
                    if (this.value !== 'มี') detailInput.value = '';
                }
            });
        });
    };

    window.tpfRemoveAnalysisVehicle = function(btn) {
        var card = btn.closest('.tpf-analysis-card');
        if (card) card.remove();
    };

    // ===== Trace rows =====
    window.tpfAddTraceRow = function(btn, side, vIdx) {
        var table = btn.previousElementSibling;
        var tbody = table.querySelector('.tpf-trace-body');
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" name="trace_' + side + '_detail_' + vIdx + '[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></td>' +
            '<td><input type="number" name="trace_' + side + '_height_' + vIdx + '[]"></td>' +
            '<td><button type="button" class="tpf-del-btn" onclick="this.closest(\'tr\').remove()">×</button></td>';
        tbody.appendChild(tr);
    };

    // ===== Compare rows (Section 9) =====
    var tpfCompareIdx = 1;
    window.tpfResetCompareIdx = function() { tpfCompareIdx = 1; };
    window.tpfAddCompareRow = function() {
        tpfCompareIdx++;
        var c = document.getElementById('tpf_compare_container');
        var div = document.createElement('div');
        div.className = 'tpf-compare-row';
        div.style.cssText = 'margin-bottom:6px; padding:3px 0; border-top:1px dotted #ccc;';
        div.innerHTML = '<div class="tpf-fr"><span class="tpf-fl-b" style="min-width:25px;">9.' + tpfCompareIdx + '</span><span class="tpf-fl">รอย</span><input type="text" class="tpf-inp" name="compare_trace_type[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>' +
            ' <button type="button" class="tpf-del-btn" onclick="this.closest(\'\.๴pf-compare-row\').remove()">×</button></div>' +
            '<div class="tpf-fr"><span class="tpf-fl">บริเวณ</span><input type="text" class="tpf-inp" name="compare_area_a[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button><span class="tpf-fl">ตามผลการตรวจในข้อ</span><input type="text" class="tpf-inp" name="compare_ref_a[]" style="max-width:80px;"></div>' +
            '<div class="tpf-fr"><span class="tpf-fl">มีลักษณะร่องรอยเข้ากันได้กับบริเวณ</span><input type="text" class="tpf-inp" name="compare_area_b[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>' +
            '<div class="tpf-fr"><span class="tpf-fl">ตามผลการตรวจในข้อ</span><input type="text" class="tpf-inp" name="compare_ref_b[]" style="max-width:80px;"></div>';
        c.appendChild(div);
    };

    // ===== Photo source chooser =====
    window.tpfChoosePhotoSource = function() {
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
                document.getElementById('tpf_photo_input_gallery').click();
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                document.getElementById('tpf_photo_input_camera').click();
            }
        });
    };

    // ===== Photo handling =====
    window.tpfPreviewPhotos = function(input) {
        if (!input.files || !input.files.length) return;
        tpfHandlePhotoFiles(input.files);
        input.value = '';
    };

    function tpfHandlePhotoFiles(files) {
        if (!files || !files.length) return;
        if (typeof window.attachmentStoreTraffic === 'undefined') window.attachmentStoreTraffic = [];
        Array.from(files).forEach(function(file) {
            if (!file.type.startsWith('image/')) return;
            var fileId = 'tpf_' + Date.now() + '_' + Math.random().toString(36).substr(2,5);
            var objectUrl = URL.createObjectURL(file);
            window.attachmentStoreTraffic.push({
                file: file,
                id: fileId,
                src: objectUrl,
                base64: objectUrl,
                name: file.name,
                filename: file.name,
                isExisting: false,
                existing: false,
                caption: '',
                size: (file.size ? (file.size / 1024 / 1024).toFixed(2) + ' MB' : 'N/A'),
                date: new Date().toLocaleString('th-TH')
            });
        });
        if (typeof window.trafficRenderAll === 'function') { window.trafficRenderAll(); } else { tpfRenderPhotosFromStore(); }
    }

    // ===== Drag & Drop handlers =====
    var tpfPhotoDZ = document.getElementById('tpf_photo_dropzone');
    if (tpfPhotoDZ) {
        tpfPhotoDZ.addEventListener('click', function() { tpfChoosePhotoSource(); });
        tpfPhotoDZ.addEventListener('dragenter', function(e) { e.preventDefault(); this.classList.add('dragover'); });
        tpfPhotoDZ.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('dragover'); });
        tpfPhotoDZ.addEventListener('dragleave', function(e) { e.preventDefault(); this.classList.remove('dragover'); });
        tpfPhotoDZ.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                tpfHandlePhotoFiles(e.dataTransfer.files);
            }
        });
    }

    // ===== Render photos (35 per page grid) =====
    var TPF_PHOTOS_PER_PAGE = 35;

    window.tpfRenderPhotosFromStore = function() {
        var photos = (typeof window.attachmentStoreTraffic !== 'undefined') ? window.attachmentStoreTraffic : [];
        var pagesNeeded = Math.max(1, Math.ceil(photos.length / TPF_PHOTOS_PER_PAGE));

        // Render first page grid
        var grid1 = document.getElementById('tpf_photo_grid_1');
        if (grid1) {
            grid1.innerHTML = '';
            var endIdx = Math.min(TPF_PHOTOS_PER_PAGE, photos.length);
            for (var i = 0; i < endIdx; i++) {
                grid1.appendChild(tpfCreatePhotoCell(photos[i], i));
            }
        }

        // Render extra pages
        var extraContainer = document.getElementById('tpf_extra_photo_pages');
        if (extraContainer) {
            extraContainer.innerHTML = '';
            for (var p = 2; p <= pagesNeeded; p++) {
                var startIdx = (p - 1) * TPF_PHOTOS_PER_PAGE;
                var endIdx = Math.min(p * TPF_PHOTOS_PER_PAGE, photos.length);
                extraContainer.appendChild(tpfCreatePhotoPageElement(p, photos, startIdx, endIdx));
            }
        }

        tpfUpdatePhotoAmount();
        if (typeof tpfUpdatePageNumbers === 'function') tpfUpdatePageNumbers();
    };

    function tpfCreatePhotoCell(item, idx) {
        var wrapper = document.createElement('div');
        wrapper.style.cssText = 'display:flex; flex-direction:column;';
        
        var cell = document.createElement('div');
        cell.className = 'tpf-photo-cell';
        cell.style.position = 'relative';
        
        var img = document.createElement('img');
        img.src = item.src || item.base64 || '';
        img.alt = item.name || 'photo';
        cell.appendChild(img);
        
        var delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'tpf-cell-delete';
        delBtn.innerHTML = '&times;';
        delBtn.onclick = function(e) {
            e.stopPropagation();
            tpfRemovePhoto(item.id);
        };
        cell.appendChild(delBtn);
        
        wrapper.appendChild(cell);
        
        var fname = document.createElement('div');
        fname.className = 'tpf-cell-filename';
        fname.textContent = item.name || 'photo';
        fname.title = item.name || 'photo';
        wrapper.appendChild(fname);
        
        return wrapper;
    }

    function tpfCreatePhotoPageElement(pageNum, photos, startIdx, endIdx) {
        var page = document.createElement('div');
        page.className = 'tpf-page tpf-photo-page';
        page.setAttribute('data-photo-page', pageNum);
        
        var startName = photos[startIdx] ? (photos[startIdx].name || 'photo') : '';
        var endName = photos[endIdx - 1] ? (photos[endIdx - 1].name || 'photo') : '';
        
        // Get report number and year
        var rptNo = document.getElementById('tpf_report_no') ? document.getElementById('tpf_report_no').value : '';
        var rptParts = (rptNo || '').split('/');
        var rptNum = rptParts[0] || '';
        var rptYear = (rptParts[1] || '').toString().slice(-2);
        
        page.innerHTML =
            '<div class="tpf-header">' +
                '<div class="tpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>' +
                '<div class="tpf-header-center">' +
                    '<div class="tpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>' +
                    '<div class="tpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับจราจร</div>' +
                    '<div style="font-size:11px;font-weight:600;">บันทึกการถ่ายภาพ (ต่อ)</div>' +
                '</div>' +
                '<div class="tpf-header-right">' +
                    '<div class="tpf-doc-box">' +
                        '<div class="tpf-doc-line">รายงานที่ <span class="tpf-rpt-no-mirror">' + rptNum + '</span> / 25<span class="tpf-rpt-year-mirror">' + rptYear + '</span></div>' +
                        '<div class="tpf-doc-line">หน้าที่ <span class="tpf-cur-page"></span> / <span class="tpf-total-page"></span></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div style="margin-bottom:4px;">' +
                '<div class="tpf-fr" style="flex-wrap:nowrap;">' +
                    '<span class="tpf-fl">รหัสภาพถ่ายที่</span>' +
                    '<span class="tpf-inp" style="flex:1; min-width:40px; text-align:center;">' + startName + '</span>' +
                    '<span class="tpf-fl">ถึง</span>' +
                    '<span class="tpf-inp" style="flex:1; min-width:40px; text-align:center;">' + endName + '</span>' +
                '</div>' +
                '<div style="font-size:10.5px; font-style:italic; margin-top:1px;">(ตามภาพถ่ายรวมที่แนบ)</div>' +
            '</div>';
        
        var grid = document.createElement('div');
        grid.className = 'tpf-photo-grid';
        for (var i = startIdx; i < endIdx; i++) {
            grid.appendChild(tpfCreatePhotoCell(photos[i], i));
        }
        page.appendChild(grid);
        
        var footer = document.createElement('div');
        footer.className = 'tpf-footer';
        footer.style.marginTop = 'auto';
        footer.innerHTML =
            '<div class="tpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>' +
            '<div class="tpf-footer-right">F-CS-... แก้ไขครั้งที่ ...<br>เริ่มใช้ ...</div>';
        page.appendChild(footer);
        
        return page;
    }

    window.tpfUpdatePhotoAmount = function() {
        var photos = (typeof window.attachmentStoreTraffic !== 'undefined' && Array.isArray(window.attachmentStoreTraffic)) ? window.attachmentStoreTraffic : [];
        var totalPhotos = photos.length;
        
        // Scope to the traffic modal only
        var modal = document.getElementById('trafficFormPdfModal');
        if (!modal) return;

        var startInput = modal.querySelector('input[name="photo_id_start_traffic"]');
        var endInput = modal.querySelector('input[name="photo_id_end_traffic"]');
        var amountInput = modal.querySelector('input[name="photo_amount_traffic"]');

        if (startInput && endInput && amountInput) {
            if (totalPhotos > 0) {
                amountInput.value = String(totalPhotos);
                // รหัสภาพ: อัปเดตเฉพาะตอน user แนบ/ลบเอง ไม่ทับค่าที่โหลดจาก DB
                if (!window.__trafficLoadingPhotos) {
                    startInput.value = (photos[0].name || photos[0].filename || 'photo');
                    endInput.value = (photos[totalPhotos - 1].name || photos[totalPhotos - 1].filename || 'photo');
                }
            } else {
                amountInput.value = '';
                if (!window.__trafficLoadingPhotos) {
                    startInput.value = '';
                    endInput.value = '';
                }
            }
        }
    };

    window.tpfUpdateCaption = function(idx, val) {
        if (typeof window.attachmentStoreTraffic !== 'undefined' && window.attachmentStoreTraffic[idx]) {
            window.attachmentStoreTraffic[idx].caption = val;
        }
    };

    window.tpfRemovePhoto = function(fileId) {
        if (typeof window.removeTrafficPhoto === 'function') { window.removeTrafficPhoto(fileId); return; }
        if (typeof window.attachmentStoreTraffic === 'undefined') return;
        window.attachmentStoreTraffic = window.attachmentStoreTraffic.filter(function(item) { return item.id !== fileId; });
        if (typeof window.trafficRenderAll === 'function') { window.trafficRenderAll(); } else { tpfRenderPhotosFromStore(); }
    };

    // ===== Canvas utilities =====
    function tpfIsCanvasBlank(canvas) {
        var ctx = canvas.getContext('2d');
        var pixelData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        for (var i = 3; i < pixelData.length; i += 4) {
            if (pixelData[i] !== 0) return false;
        }
        return true;
    }

    window.tpfClearCanvas = function(canvasId) {
        var canvas = document.getElementById(canvasId);
        if (canvas) {
            var ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
    };

    function initTpfCanvas(canvasId, penColor) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
        var ctx = canvas.getContext('2d');
        var drawing = false;
        var color = penColor || '#000';

        function getPos(e) {
            var rect = canvas.getBoundingClientRect();
            var touch = e.touches ? e.touches[0] : e;
            return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
        }
        function start(e) { e.preventDefault(); drawing = true; var p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
        function move(e) { if (!drawing) return; e.preventDefault(); var p = getPos(e); ctx.lineTo(p.x, p.y); ctx.strokeStyle = color; ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.stroke(); }
        function end() { drawing = false; }

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseup', end);
        canvas.addEventListener('mouseleave', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end);
    }

    // ===== Auto-fill position on person select =====
    $(document).on('change', '.tpf-user-select', function() {
        var $this = $(this);
        var idEmp = $this.val();
        var targetInput = $this.data('pos-target');
        if (!idEmp) {
            $(targetInput).val('');
            return;
        }
        $.ajax({
            url: '/csims/api/ReceiveNoti/getPos.php',
            type: 'GET',
            data: { idEmp: idEmp },
            dataType: 'json',
            success: function(response) {
                if (response.message == 'success' && response.data) {
                    $(targetInput).val(response.data.position_name);
                } else {
                    $(targetInput).val('');
                }
            },
            error: function() {
                $(targetInput).val('');
            }
        });
    });

    // ===== Modal initialization =====
    var modalEl = document.getElementById('trafficFormPdfModal');
    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', function() {
            console.log('[TPF] Modal shown event fired');
            console.log('[TPF] attachmentStoreTraffic:', typeof attachmentStoreTraffic !== 'undefined' ? attachmentStoreTraffic.length : 'undefined');
            
            initTpfCanvas('tpf_sig_receiver', '#000');
            initTpfCanvas('tpf_sig_sender', '#000');

            // ★ Auto-fill พฤติการณ์คดี from basic_info (rn_ReceiveNoti)
            var dstBehavior = document.getElementById('tpf_case_behavior');
            if (dstBehavior && !dstBehavior.value && window._basicInfoTraffic) {
                dstBehavior.value = window._basicInfoTraffic;
            }

            // populate mirror spans pages 2+
            var docNo = document.getElementById('tpf_doc_no') ? document.getElementById('tpf_doc_no').value : '';
            var rptNo = document.getElementById('tpf_report_no') ? document.getElementById('tpf_report_no').value : '';
            var rptParts = (rptNo || '').split('/');
            var rptNum = rptParts[0] || docNo;
            var rptYear = (rptParts[1] || '').toString().slice(-2);

            var rptDisplay = document.getElementById('tpf_report_no_display');
            var yearDisplay = document.getElementById('tpf_report_year_display');
            if (rptDisplay) rptDisplay.textContent = rptNum;
            if (yearDisplay) yearDisplay.textContent = rptYear;

            modalEl.querySelectorAll('.tpf-rpt-no-mirror').forEach(function(el) { el.textContent = rptNum; });
            
            // Render photos from store
            console.log('[TPF] Calling tpfRenderPhotosFromStore...');
            if (typeof tpfRenderPhotosFromStore === 'function') {
                tpfRenderPhotosFromStore();
            }
            modalEl.querySelectorAll('.tpf-rpt-year-mirror').forEach(function(el) { el.textContent = rptYear; });

            // อัพเดทเลขหน้า
            if (typeof tpfUpdatePageNumbers === 'function') tpfUpdatePageNumbers();
            
            console.log('[TPF] Calling tpfUpdatePhotoAmount...');
            tpfUpdatePhotoAmount();
            console.log('[TPF] Modal initialization complete');
        });
    }

    // ===== Save handler =====
    document.getElementById('btn_save_traffic_pdf').addEventListener('click', async function() {
        var form = document.getElementById('trafficFormPdf');
        var formData = new FormData(form);
        formData.append('form_mode', 'pdf_form');

        // Canvas to Blob helper
        function canvasToBlob(canvasId) {
            return new Promise(function(resolve) {
                var canvas = document.getElementById(canvasId);
                if (!canvas || tpfIsCanvasBlank(canvas)) { resolve(null); return; }
                canvas.toBlob(function(blob) { resolve(blob); }, 'image/png');
            });
        }

        // Append canvas data
        var rcvSigBlob = await canvasToBlob('tpf_sig_receiver');
        if (rcvSigBlob) formData.append('receiver_signature_file', rcvSigBlob, 'receiver_sig.png');

        var sndSigBlob = await canvasToBlob('tpf_sig_sender');
        if (sndSigBlob) formData.append('sender_signature_file', sndSigBlob, 'sender_sig.png');

        // Append photos — ส่งเฉพาะรูปใหม่ (มี File object) รูปเดิมคงไว้ที่ DB เว้นแต่ถูกลบ
        formData.delete('incident_photos_traffic[]');
        if (typeof attachmentStoreTraffic !== 'undefined') {
            attachmentStoreTraffic.forEach(function(item) {
                if (item.file) {
                    formData.append('incident_photos_traffic[]', item.file, item.filename || item.name || 'photo.jpg');
                    formData.append('photo_captions_traffic[]', item.caption || '');
                }
            });
        }
        // รายการรูปเดิมที่ถูกลบ -> ส่ง file_id ให้ API ลบ BLOB
        if (window.deletedExistingPhotosTraffic && window.deletedExistingPhotosTraffic.length > 0) {
            formData.append('deleted_photo_file_ids', JSON.stringify(window.deletedExistingPhotosTraffic));
        }

        // ★ ยืนยันก่อนบันทึก
        var confirmResult = await Swal.fire({
            title: 'ยืนยันการบันทึกข้อมูล',
            text: 'กรุณาตรวจสอบความถูกต้องก่อนบันทึก',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#d33',
            confirmButtonText: 'ยืนยัน, บันทึกเลย!',
            cancelButtonText: 'ยกเลิก'
        });
        if (!confirmResult.isConfirmed) return;

        var tpfUrl = './api/incidentCheckList/saveTraffic.php';
        if (!navigator.onLine) {
            await saveChecklistOffline(formData, tpfUrl, '#trafficFormPdfModal');
            return;
        }
        var backendOkTPF = await checkBackendHealth();
        if (!backendOkTPF) {
            await saveChecklistOffline(formData, tpfUrl, '#trafficFormPdfModal');
            return;
        }

        try {
            var response = await fetch(tpfUrl, {
                method: 'POST',
                body: formData
            });
            var result = await response.json();
            if (result.status === 'success' || result.success) {
                await Swal.fire({ icon: 'success', title: 'สำเร็จ', text: result.message || 'บันทึกข้อมูลเรียบร้อย', confirmButtonText: 'ตกลง' });
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                if (typeof loadData === 'function') loadData();
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: result.message || 'ไม่สามารถบันทึกข้อมูลได้', confirmButtonText: 'ตกลง' });
            }
        } catch (err) {
            await saveChecklistOffline(formData, tpfUrl, '#trafficFormPdfModal');
        }
    });

    // ===== Download Checklist PDF =====
    window.tpfDownloadChecklistPdf = function() {
        var incidentId = document.getElementById('tpf_incident_id')?.value || 
                         document.getElementById('incident_id_traffic')?.value || '';
        if (!incidentId) {
            Swal.fire({ icon: 'warning', title: 'ไม่พบ incident_id', text: 'กรุณาบันทึกข้อมูลก่อนดาวน์โหลด' });
            return;
        }
        window.open('/csims/api/incidentCheckList/gen_pdf_traffic_html.php?incident_id=' + incidentId, '_blank');
    };
})();
</script>
