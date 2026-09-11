<?php
/**
 * Modal: ฟอร์ม PDF คดีทรัพย์ (แบบกรอกข้อมูล)
 * หน้าตาเหมือนฟอร์ม PDF เป๊ะ แต่กรอกข้อมูลได้
 * Prefix ID: ppf_
 * Updated: 2026-05-19 - Changed distance checkboxes to text inputs
 */

$ppfPoliceStationOptions = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qryPpfPS = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stmtPpfPS = $pdo->query($qryPpfPS);
    while ($rowPS = $stmtPpfPS->fetch(PDO::FETCH_ASSOC)) {
        $ppfPoliceStationOptions .= '<option value="' . htmlspecialchars($rowPS['station_name']) . '">' . htmlspecialchars($rowPS['station_name']) . '</option>';
    }
}

$ppfInspectorOptions = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
if (isset($pdo)) {
    $qryPpfInsp = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                   FROM user_profile t1 
                   LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                   ORDER BY t1.user_id DESC";
    $stmtPpfInsp = $pdo->query($qryPpfInsp);
    while ($rowInsp = $stmtPpfInsp->fetch(PDO::FETCH_ASSOC)) {
        $ppfInspectorOptions .= '<option value="' . $rowInsp['user_id'] . '">' . htmlspecialchars($rowInsp['fullname']) . '</option>';
    }
}

$ppfTodayDate = date('Y-m-d');
$ppfTodayTime = date('H:i');
?>

<!-- ===== SCOPED CSS ===== -->
<style>
/* hwpen button */
#propertyFormPdfModal .btn-hw-open {
    flex-shrink: 0; min-width: 18px; padding: 0 4px;
    border: none; background: none; color: #6366f1;
    font-size: 0.7rem; cursor: pointer; line-height: 1.5;
}
#propertyFormPdfModal .btn-hw-open:hover { color: #4338ca; transform: scale(1.15); }
#propertyFormPdfModal .ppf-cg > .ppf-inp { flex: 0 1 auto; }

#propertyFormPdfModal .btn-purple {
    background-color: #8b5cf6;
    border-color: #8b5cf6;
    color: #fff;
}
#propertyFormPdfModal .btn-purple:hover {
    background-color: #7c3aed;
    border-color: #7c3aed;
    color: #fff;
}

#propertyFormPdfModal .ppf-rpt-no-mirror,
#propertyFormPdfModal .ppf-rpt-year-mirror {
    display: inline-block;
    border-bottom: 1px dotted #888;
    text-align: center;
}
#propertyFormPdfModal .ppf-rpt-no-mirror { min-width: 60px; }
#propertyFormPdfModal .ppf-rpt-year-mirror { min-width: 30px; }

#propertyFormPdfModal .ppf-body {
    background: #bbb;
    padding: 10px 0;
}

#propertyFormPdfModal .ppf-page {
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
#propertyFormPdfModal .ppf-header {
    position: relative;
    margin-bottom: 6px;
    height: 70px;
}
#propertyFormPdfModal .ppf-header-logo {
    position: absolute; left: 0; top: -5px; width: 70px; height: 70px;
}
#propertyFormPdfModal .ppf-header-logo img {
    width: 70px; height: 70px; object-fit: contain;
}
#propertyFormPdfModal .ppf-header-center {
    position: absolute; left: 80px; right: 180px; top: 8px; text-align: center;
}
#propertyFormPdfModal .ppf-header-center .ppf-title-main {
    font-size: 14px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 5px;
}
#propertyFormPdfModal .ppf-header-center .ppf-title-sub {
    font-size: 11.5px; font-weight: 600; margin-top: 3px;
}
#propertyFormPdfModal .ppf-header-right {
    position: absolute; right: 0; top: 7px;
}
#propertyFormPdfModal .ppf-doc-box {
    border: 1.5px solid #000; padding: 3px 8px; font-size: 11px; white-space: nowrap;
}
#propertyFormPdfModal .ppf-doc-box .ppf-doc-line { line-height: 1.5; }

/* ===== BODY TABLE ===== */
#propertyFormPdfModal .ppf-form-body {
    display: flex; border: 1.5px solid #000; align-items: stretch;
}
#propertyFormPdfModal .ppf-col-left {
    width: 50%; border-right: 1.5px solid #000; display: flex; flex-direction: column;
}
#propertyFormPdfModal .ppf-col-right {
    width: 50%; display: flex; flex-direction: column;
}

/* ===== ROW HEADER ===== */
#propertyFormPdfModal .ppf-row-header {
    display: flex; border-bottom: 1px solid #000;
    font-weight: 700; font-size: 11px; text-align: center; background: transparent;
}
#propertyFormPdfModal .ppf-row-header .ppf-lbl-seq {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000; padding: 1px 2px;
}
#propertyFormPdfModal .ppf-row-header .ppf-lbl-data {
    flex: 1; padding: 1px 2px;
}

/* ===== SECTION ROW ===== */
#propertyFormPdfModal .ppf-sec-row {
    display: flex; border-bottom: 1px solid #000;
}
#propertyFormPdfModal .ppf-sec-row:last-child { border-bottom: none; }
#propertyFormPdfModal .ppf-sec-label {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000;
    padding: 3px 3px; font-weight: 700; font-size: 11px; text-align: center;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
#propertyFormPdfModal .ppf-sec-label .ppf-sec-num {
    font-size: 13px; font-weight: 700; line-height: 1.2;
}
#propertyFormPdfModal .ppf-sec-label .ppf-sec-txt {
    font-size: 10px; font-weight: 600; line-height: 1.15; text-align: center; margin-top: 1px;
}
#propertyFormPdfModal .ppf-sec-body {
    flex: 1; padding: 5px 6px; font-size: 11.5px;
}

/* ===== FIELD ROW ===== */
#propertyFormPdfModal .ppf-fr {
    display: flex; flex-wrap: wrap; align-items: center; margin-bottom: 6px; line-height: 1.8;
}
#propertyFormPdfModal .ppf-fl {
    font-size: 11.5px; white-space: nowrap; margin-right: 4px;
}
#propertyFormPdfModal .ppf-fl-b {
    font-size: 11.5px; font-weight: 600; white-space: nowrap; margin-right: 4px;
}

/* ===== INPUT FIELDS ===== */
#propertyFormPdfModal .ppf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px;
}
#propertyFormPdfModal .ppf-inp-m {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 60px; margin: 0 2px; text-align: center;
}
#propertyFormPdfModal .ppf-inp-full {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    width: 100%; display: block; margin-bottom: 3px;
}
#propertyFormPdfModal .ppf-inp-s {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    min-width: 15px; max-width: 50px; margin: 0 2px; flex: 0 1 40px; text-align: center;
}

/* SELECT styled like dotted line */
#propertyFormPdfModal .ppf-sel {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px; cursor: pointer;
}

/* TEXTAREA styled like dotted lines */
#propertyFormPdfModal .ppf-ta {
    border: none; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; outline: none; color: #000;
    width: 100%; resize: none; line-height: 20px;
    white-space: pre-wrap; overflow-wrap: break-word; word-break: break-word;
    overflow: hidden;
    background-image: linear-gradient(transparent 19px, #888 19px);
    background-size: 100% 20px;
    background-position: 0 0;
    margin-bottom: 3px;
}

/* ===== CHECKBOX (styled like PDF square box) ===== */
#propertyFormPdfModal .ppf-cb {
    appearance: none; -webkit-appearance: none;
    width: 13px; height: 13px; border: 1.5px solid #000;
    margin-right: 3px; cursor: pointer; position: relative;
    vertical-align: middle; flex-shrink: 0; background: #fff;
}
#propertyFormPdfModal .ppf-cb:checked::after {
    content: '✓'; font-size: 12px; font-weight: 700;
    position: absolute; top: -3px; left: 0px; color: #000;
}

/* ===== Checkbox label ===== */
#propertyFormPdfModal .ppf-ck {
    display: inline-flex; align-items: center; margin-right: 14px;
    font-size: 11.5px; white-space: nowrap; vertical-align: middle; cursor: pointer;
}

/* Filled square bullet */
#propertyFormPdfModal .ppf-bk {
    width: 10px; height: 10px; background: #000;
    display: inline-block; margin-right: 3px; flex-shrink: 0;
    position: relative; top: 1px;
}

/* ===== Bullet header ===== */
#propertyFormPdfModal .ppf-bh {
    display: flex; align-items: center; font-weight: 600;
    font-size: 11.5px; margin-top: 8px; margin-bottom: 5px;
}

/* ===== Sub-items ===== */
#propertyFormPdfModal .ppf-si {
    display: flex; align-items: center; font-size: 11.5px; line-height: 1.8; margin-bottom: 5px;
}
#propertyFormPdfModal .ppf-si-no {
    min-width: 25px; padding-left: 8px; font-size: 11.5px;
}

/* ===== Checkbox group ===== */
#propertyFormPdfModal .ppf-cg {
    display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; margin-bottom: 5px;
}

/* ===== Indents ===== */
#propertyFormPdfModal .ppf-i1 { padding-left: 15px; }
#propertyFormPdfModal .ppf-i2 { padding-left: 28px; }

/* ===== FOOTER ===== */
#propertyFormPdfModal .ppf-footer {
    margin-top: auto; font-size: 9.5px; color: #333;
    display: flex; justify-content: space-between; align-items: flex-end; flex-shrink: 0;
}
#propertyFormPdfModal .ppf-footer-left { flex: 1; }
#propertyFormPdfModal .ppf-footer-right {
    text-align: right; white-space: nowrap; line-height: 1.3;
}

/* ===== Add/Remove buttons ===== */
#propertyFormPdfModal .ppf-add-btn {
    font-size: 10px; padding: 1px 8px; border: 1px dashed #888;
    background: #f8f8f8; cursor: pointer; color: #333; margin: 3px 0;
    font-family: 'Sarabun', sans-serif;
}
#propertyFormPdfModal .ppf-add-btn:hover { background: #e0e0e0; }
#propertyFormPdfModal .ppf-del-btn {
    font-size: 9px; padding: 0 4px; border: 1px solid #ccc;
    background: #fff; cursor: pointer; color: #c00;
    font-family: 'Sarabun', sans-serif; line-height: 1.5;
}
#propertyFormPdfModal .ppf-del-btn:hover { background: #fee; }

/* ===== Table inputs ===== */
#propertyFormPdfModal .ppf-ev-table td {
    border: 1px solid #000; padding: 2px; text-align: center; vertical-align: middle;
}
#propertyFormPdfModal .ppf-ev-table input[type="text"] {
    border: none; border-bottom: 1px dotted #888; background: transparent; font-size: 10px; width: 100%;
    padding: 1px 2px; outline: none; font-family: 'Sarabun', sans-serif; text-align: center;
}
#propertyFormPdfModal .ppf-ev-table input[type="checkbox"] {
    width: 10px; height: 10px; cursor: pointer;
}

/* ===== Sketch area ===== */
#propertyFormPdfModal .ppf-sketch-area {
    border: 1.5px solid #000; min-height: 500px; position: relative;
    display: flex; align-items: center; justify-content: center; cursor: crosshair;
}

/* ===== Multi-page Sketch System ===== */
#propertyFormPdfModal .ppf-sketch-viewport {
    border: 1.5px solid #000; position: relative;
    background: #fff; cursor: crosshair;
}
#propertyFormPdfModal .ppf-sketch-canvas-wrap {
    position: relative; width: 100%;
}
#propertyFormPdfModal .ppf-sketch-canvas-wrap canvas {
    display: block; width: 100%; height: 100%; position: absolute; top: 0; left: 0; z-index: 2;
}
#propertyFormPdfModal .ppf-sketch-canvas-wrap .ppf-sketch-bg-img {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1;
    object-fit: contain; pointer-events: none; user-select: none;
}
#propertyFormPdfModal .ppf-not-to-scale {
    position: absolute; bottom: 6px; right: 8px; font-size: 10px; color: #555; z-index: 3;
    pointer-events: none;
}

/* ===== Photo Grid 5 คอลัมน์ × 7 แถว ===== */
#propertyFormPdfModal .ppf-photo-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 6px 4px;
    align-content: start;
}
#propertyFormPdfModal .ppf-photo-cell {
    border: 1.5px solid #333;
    overflow: hidden;
    background: #fff;
    display: flex;
    flex-direction: column;
}
#propertyFormPdfModal .ppf-photo-cell img {
    width: 100%;
    aspect-ratio: 4/3;
    object-fit: cover;
    display: block;
}
#propertyFormPdfModal .ppf-photo-cell-wrapper {
    display: flex;
    flex-direction: column;
}
#propertyFormPdfModal .ppf-photo-fname {
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
#propertyFormPdfModal .ppf-photo-cell .ppf-cell-delete {
    position: absolute; top: 2px; right: 2px; width: 14px; height: 14px;
    border-radius: 50%; border: none; background: rgba(220,53,69,0.85); color: #fff;
    font-size: 8px; cursor: pointer; padding: 0; line-height: 14px; z-index: 2;
    display: none;
}
#propertyFormPdfModal .ppf-photo-cell:hover .ppf-cell-delete { display: block; }
#propertyFormPdfModal .ppf-photo-dropzone {
    border: 2px dashed #b0bec5; border-radius: 8px; padding: 12px;
    text-align: center; cursor: pointer; background: #f8f9fa;
    margin-bottom: 8px; transition: all 0.2s;
}
#propertyFormPdfModal .ppf-photo-dropzone:hover,
#propertyFormPdfModal .ppf-photo-dropzone.dragover {
    border-color: #2196F3; background: #e3f2fd;
}
#propertyFormPdfModal .ppf-add-photo-page-btn {
    display: block; margin: 8px auto; padding: 4px 16px;
    border: 1px dashed #888; background: #f0f0f0; cursor: pointer;
    font-size: 11px; font-family: 'Sarabun', sans-serif; color: #333;
}
#propertyFormPdfModal .ppf-add-photo-page-btn:hover { background: #e0e0e0; }

/* ===== Signature clear button ===== */
#propertyFormPdfModal .ppf-sig-clear-btn {
    position: absolute; top: 2px; right: 4px;
    width: 18px; height: 18px; border-radius: 50%;
    border: 1px solid #ccc; background: #fff; color: #c00;
    font-size: 12px; line-height: 16px; text-align: center;
    cursor: pointer; padding: 0; z-index: 2; opacity: 0.5;
    font-family: 'Sarabun', sans-serif;
}
#propertyFormPdfModal .ppf-sig-clear-btn:hover { opacity: 1; background: #fee; }

@media print {
    body > *:not(#propertyFormPdfModal),
    .navbar, .sidebar, .main-content, #status-banner,
    .modal-backdrop, .swal2-container,
    #propertyFormPdfModal .modal-header,
    #propertyFormPdfModal .modal-footer,
    #propertyFormPdfModal .csims-loading-overlay,
    #propertyFormPdfModal .ppf-add-btn,
    #propertyFormPdfModal .ppf-del-btn,
    #propertyFormPdfModal .ppf-add-photo-page-btn,
    #propertyFormPdfModal .ppf-sig-clear-btn,
    #propertyFormPdfModal .btn-hw-open,
    #propertyFormPdfModal .btn-sketch-eraser,
    #propertyFormPdfModal input[type="color"],
    #propertyFormPdfModal .form-check.form-switch,
    #propertyFormPdfModal .d-flex.align-items-center.gap-3 {
        display: none !important;
    }
    #propertyFormPdfModal,
    #propertyFormPdfModal .modal-dialog,
    #propertyFormPdfModal .modal-content,
    #propertyFormPdfModal .ppf-body {
        position: static !important; display: block !important;
        width: auto !important; max-width: none !important;
        max-height: none !important; height: auto !important;
        overflow: visible !important; margin: 0 !important;
        padding: 0 !important; background: #fff !important;
        border: none !important; box-shadow: none !important;
        transform: none !important; opacity: 1 !important;
    }
    @page { size: A4 portrait; margin: 0; }
    #propertyFormPdfModal .ppf-page {
        width: 100% !important; min-height: auto !important;
        height: auto !important; margin: 0 !important;
        padding: 8mm 10mm 5mm 10mm !important;
        box-shadow: none !important; overflow: visible !important;
        page-break-after: always; page-break-inside: auto;
    }
    #propertyFormPdfModal .ppf-page:last-of-type { page-break-after: auto; }
    #propertyFormPdfModal .ppf-sec-row { page-break-inside: avoid; }
    #propertyFormPdfModal .ppf-form-body { page-break-inside: auto; }
    #propertyFormPdfModal .ppf-header { page-break-after: avoid; }
    #propertyFormPdfModal .ppf-footer { page-break-before: avoid; }
    #propertyFormPdfModal .ppf-photo-grid { gap: 2px !important; }
    #propertyFormPdfModal .ppf-photo-cell { page-break-inside: avoid; }
    #propertyFormPdfModal .ppf-photo-dropzone { display: none !important; }
    #propertyFormPdfModal .ppf-photo-cell .ppf-cell-delete { display: none !important; }
    #propertyFormPdfModal .ppf-sig-box,
    #propertyFormPdfModal .ppf-sketch-area,
    #propertyFormPdfModal .ppf-sketch-viewport { page-break-inside: avoid; }
    #propertyFormPdfModal .ppf-sketch-viewport { overflow: visible !important; height: auto !important; }
    #propertyFormPdfModal .ppf-inp, #propertyFormPdfModal .ppf-inp-m,
    #propertyFormPdfModal .ppf-inp-full, #propertyFormPdfModal .ppf-inp-s,
    #propertyFormPdfModal .ppf-sel, #propertyFormPdfModal .ppf-ta {
        border-bottom-color: #888 !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    #propertyFormPdfModal .ppf-cb {
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
}
</style>

<!-- ===== MODAL ===== -->
<div class="modal fade" id="propertyFormPdfModal" aria-labelledby="propertyFormPdfModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 860px; margin: 1.75rem auto;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="propertyFormPdfModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body ppf-body p-0" style="max-height: 80vh; overflow-y: auto; position:relative;">
                <!-- Loading Overlay -->
                <div id="propertyPdfLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track">
                            <div class="csims-bar-fill"></div>
                        </div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>
                <form id="propertyFormPdf" novalidate>
                    <input type="hidden" id="ppf_receiveNoti_id" name="receiveNoti_id">
                    <input type="hidden" id="ppf_doc_no" name="doc_no">
                    <input type="hidden" id="ppf_report_no" name="report_no">

                    <!-- Switch กลับไปฟอร์มมาตรฐาน -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="editInfoPropertyPdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountPropertyPdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToStdFormProperty" checked style="width: 3rem; height: 1.5rem; cursor: pointer;" onchange="if(!this.checked){ this.checked=true; switchToPropertyStandardForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToStdFormProperty" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ================================================================ -->
<!-- ========================= PAGE 1 ============================== -->
<!-- ================================================================ -->
<div class="ppf-page">

    <div class="ppf-header">
        <div class="ppf-header-logo">
            <img src="./images/office-of-police-forensic-icon.jpg" alt="ตราสำนักงานตำรวจแห่งชาติ">
        </div>
        <div class="ppf-header-center">
            <div class="ppf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="ppf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์</div>
        </div>
        <div class="ppf-header-right">
            <div class="ppf-doc-box">
                <div class="ppf-doc-line">รายงานที่ <span id="ppf_report_no_display" style="display:inline-block;min-width:60px;border-bottom:1px dotted #888;text-align:center;"></span> / 25<span id="ppf_report_year_display" style="display:inline-block;min-width:30px;border-bottom:1px dotted #888;text-align:center;"><?= substr((date('Y') + 543), -2) ?></span></div>
                <div class="ppf-doc-line">หน้าที่ <span class="ppf-cur-page"></span> / <span class="ppf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div class="ppf-form-body">

        <!-- LEFT COLUMN -->
        <div class="ppf-col-left">
            <div class="ppf-row-header">
                <div class="ppf-lbl-seq">ลำดับ</div>
                <div class="ppf-lbl-data">ข้อมูล</div>
            </div>

            <!-- 1. การรับแจ้งเหตุ -->
            <div class="ppf-sec-row">
                <div class="ppf-sec-label">
                    <span class="ppf-sec-num">1.</span>
                    <span class="ppf-sec-txt">การรับ<br>แจ้งเหตุ</span>
                </div>
                <div class="ppf-sec-body">
                    <div class="ppf-fr">
                        <span class="ppf-fl">ประเภทคดี</span>
                        <select class="ppf-sel" name="case_type" id="ppf_case_type">
                            <option value="" selected disabled>กรุณาเลือก</option>
                            <option value="theft">ลักทรัพย์</option>
                            <option value="snatch">ชิงทรัพย์</option>
                            <option value="robbery">ปล้นทรัพย์</option>
                            <option value="other">อื่น ๆ</option>
                        </select>
                    </div>
                    <div class="ppf-fr" id="ppf_case_type_other_div" style="display:none;">
                        <span class="ppf-fl">อื่นๆ ระบุ</span>
                        <input type="text" class="ppf-inp" name="case_type_other" id="ppf_case_type_other"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="ppf-fr">
                        <span class="ppf-fl">วันที่รับแจ้ง</span>
                        <input type="datetime-local" class="ppf-inp" name="report_datetime" id="ppf_report_datetime" style="text-align:center;">
                    </div>
                    <div class="ppf-fr">
                        <span class="ppf-fl" style="margin-right:10px;">ช่องทาง</span>
                        <select class="ppf-sel" name="report_channel" id="ppf_report_channel" style="max-width:120px;">
                            <option value="" selected disabled>กรุณาเลือก</option>
                            <option value="phone">ทางโทรศัพท์</option>
                            <option value="radio">ทางวิทยุสื่อสาร</option>
                            <option value="document">ทางหนังสือ</option>
                            <option value="other">อื่น ๆ</option>
                        </select>
                    </div>
                    <div class="ppf-fr">
                        <span class="ppf-fl">สน./สภ.</span>
                        <select class="ppf-sel" name="source_station" id="ppf_source_station">
                            <?= $ppfPoliceStationOptions ?>
                        </select>
                    </div>
                    <div class="ppf-fr">
                        <span class="ppf-fl">ที่</span>
                        <input type="text" class="ppf-inp" name="document_no" id="ppf_document_no"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                        <span class="ppf-fl">ลง</span>
                        <input type="date" class="ppf-inp" name="document_date" id="ppf_document_date" style="text-align:center;" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="ppf-fr">
                        <span class="ppf-fl">จังหวัด</span>
                        <select class="ppf-sel" name="provinceID" id="ppf_provinceID">
                            <option value="" selected disabled>กรุณาเลือก</option>
                            <option value="95">ยะลา</option>
                            <option value="94">ปัตตานี</option>
                            <option value="96">นราธิวาส</option>
                        </select>
                    </div>
                    <div class="ppf-bh" style="margin-top:2px;"><span class="ppf-bk"></span><span>ข้อมูลพนักงานสอบสวน</span></div>
                    <div class="ppf-fr">
                        <span class="ppf-fl">ชื่อ</span>
                        <input type="text" class="ppf-inp" name="investigator_firstname" id="ppf_investigator_firstname"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="ppf-fr">
                        <span class="ppf-fl">นามสกุล</span>
                        <input type="text" class="ppf-inp" name="investigator_lastname" id="ppf_investigator_lastname"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="ppf-fr">
                        <span class="ppf-fl">หมายเลขโทรศัพท์</span>
                        <input type="tel" class="ppf-inp" name="investigator_phone" id="ppf_investigator_phone">
                    </div>
                </div>
            </div>

            <!-- 2. สถานที่เกิดเหตุ -->
            <div class="ppf-sec-row">
                <div class="ppf-sec-label">
                    <span class="ppf-sec-num">2.</span>
                    <span class="ppf-sec-txt">สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="ppf-sec-body">
                    <div class="ppf-fr">
                        <span class="ppf-fl">รายละเอียดสถานที่</span><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>
                    <textarea class="ppf-ta" name="incident_location" id="ppf_incident_location" rows="2"></textarea>

                    <div class="ppf-bh" style="margin-top:2px;"><span class="ppf-bk"></span><span>ข้อมูลผู้เสียหาย / ผู้เกี่ยวข้อง</span></div>
                    <div class="ppf-cg">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb ppf-radio-toggle" name="victim_type" value="owner" data-group="ppf_victim_type">เจ้าของบ้าน</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb ppf-radio-toggle" name="victim_type" value="victim" data-group="ppf_victim_type">ผู้เสียหาย</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb ppf-radio-toggle" name="victim_type" value="other" data-group="ppf_victim_type">อื่น ๆ</label>
                        <input type="text" class="ppf-inp" name="victim_type_other_text" id="ppf_victim_type_other_text" placeholder="ระบุ..." style="max-width:100px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="ppf-fr">
                        <span class="ppf-fl">ชื่อ</span>
                        <input type="text" class="ppf-inp" name="victim_firstname" id="ppf_victim_firstname"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                        <span class="ppf-fl" style="margin-left:4px;">นามสกุล</span>
                        <input type="text" class="ppf-inp" name="victim_lastname" id="ppf_victim_lastname"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="ppf-fr">
                        <span class="ppf-fl">อายุ</span>
                        <input type="text" class="ppf-inp-s" name="victim_age" id="ppf_victim_age" style="max-width:40px;">
                        <span class="ppf-fl">ปี</span>
                    </div>
                </div>
            </div>

            <!-- 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ -->
            <div class="ppf-sec-row">
                <div class="ppf-sec-label">
                    <span class="ppf-sec-num">3.</span>
                    <span class="ppf-sec-txt">วันเวลา<br>ที่ทราบ<br>เหตุ/เกิด<br>เหตุ</span>
                </div>
                <div class="ppf-sec-body">
                    <div class="ppf-fr"><span class="ppf-fl-b">วันเวลาที่ผู้เสียหายทราบเหตุ/เกิดเหตุ</span></div>
                    <div class="ppf-fr">
                        <input type="datetime-local" class="ppf-inp" name="incident_datetime" id="ppf_incident_datetime" style="text-align:center;">
                    </div>
                    <div class="ppf-fr" style="margin-top:2px;"><span class="ppf-fl-b">วันเวลาที่พนักงานสอบสวนทราบเหตุ</span></div>
                    <div class="ppf-fr">
                        <input type="datetime-local" class="ppf-inp" name="investigator_known_datetime" id="ppf_investigator_known_datetime" style="text-align:center;">
                    </div>
                </div>
            </div>

            <!-- 4. วันเวลาที่ตรวจเหตุ -->
            <div class="ppf-sec-row">
                <div class="ppf-sec-label">
                    <span class="ppf-sec-num">4.</span>
                    <span class="ppf-sec-txt">วัน<br>เวลาที่<br>ตรวจ<br>เหตุ</span>
                </div>
                <div class="ppf-sec-body">
                    <div class="ppf-fr"><span class="ppf-fl-b">วันเวลาที่ทำการตรวจสถานที่เกิดเหตุ</span></div>
                    <div class="ppf-fr">
                        <input type="datetime-local" class="ppf-inp" name="inspection_datetime" id="ppf_inspection_datetime" style="text-align:center;">
                    </div>
                    <div class="ppf-fr" style="margin-top:2px;"><span class="ppf-fl-b">วันเวลาตรวจเพิ่มเติม (ถ้ามี)</span></div>
                    <div class="ppf-fr">
                        <input type="datetime-local" class="ppf-inp" name="inspection_additional_datetime" id="ppf_inspection_additional_datetime" style="text-align:center;">
                    </div>
                </div>
            </div>

            <!-- 5. ผู้ตรวจสถานที่เกิดเหตุ -->
            <div class="ppf-sec-row" style="flex:1; border-bottom:none;">
                <div class="ppf-sec-label">
                    <span class="ppf-sec-num">5.</span>
                    <span class="ppf-sec-txt">ผู้ตรวจ<br>สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="ppf-sec-body">
                    <div class="ppf-bh" style="margin-top:0;">
                        <span class="ppf-bk"></span>
                        <span>ผู้ตรวจสถานที่เกิดเหตุ</span>
                    </div>
                    <div id="ppf_inspector_container">
                        <div class="ppf-si ppf-inspector-row">
                            <span class="ppf-si-no">5.1</span>
                            <select class="ppf-sel" name="inspector_id[]">
                                <?= $ppfInspectorOptions ?>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="ppf-add-btn" onclick="ppfAddInspector()">+ เพิ่มผู้ตรวจ</button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="ppf-col-right">
            <div class="ppf-row-header">
                <div class="ppf-lbl-seq">ลำดับ</div>
                <div class="ppf-lbl-data">ข้อมูล</div>
            </div>

            <!-- 6. ลักษณะสถานที่เกิดเหตุ -->
            <div class="ppf-sec-row" style="flex:1; border-bottom:none;">
                <div class="ppf-sec-label">
                    <span class="ppf-sec-num">6.</span>
                    <span class="ppf-sec-txt">ลักษณะ<br>สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="ppf-sec-body">
                    <!-- การรักษาสถานที่ -->
                    <div class="ppf-bh" style="margin-top:0;"><span class="ppf-bk"></span><span>สภาพสถานที่เกิดเหตุเมื่อไปถึง</span></div>
                    <div class="ppf-cg ppf-i1">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb ppf-radio-toggle" name="scene_preservation" value="yes" data-group="ppf_preservation">มีการรักษาสถานที่</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb ppf-radio-toggle" name="scene_preservation" value="no" data-group="ppf_preservation">ไม่มีการรักษาสถานที่</label>
                    </div>
                    <div class="ppf-fr ppf-i1">
                        <span class="ppf-fl">รายละเอียด</span><button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="ppf_preservation_text" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="ppf-i1">
                        <textarea class="ppf-ta" name="preservation_text" id="ppf_preservation_text" rows="2"></textarea>
                    </div>

                    <!-- ลักษณะภายนอก -->
                    <div class="ppf-bh"><span class="ppf-bk"></span><span>6.1 ลักษณะภายนอก</span></div>
                    <div class="ppf-cg ppf-i1" style="margin-bottom:4px;">
                        <span class="ppf-fl">ชั้น</span>
                        <input type="text" class="ppf-inp" name="building_floor" id="ppf_building_floor" style="flex:0 0 50px; max-width:50px; text-align:center;">
                    </div>
                    <div class="ppf-cg ppf-i1">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="building_type[]" value="concrete">บ้านคอนกรีต</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="building_type[]" value="wood">บ้านไม้</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="building_type[]" value="half">ครึ่งตึกครึ่งไม้</label>
                    </div>
                    <div class="ppf-cg ppf-i1">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="building_type[]" value="row_building">ตึกแถว</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="building_type[]" value="concrete_bldg">อาคารคอนกรีต</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="building_type[]" value="townhouse">ทาวน์เฮาส์</label>
                    </div>
                    <div class="ppf-cg ppf-i1">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="building_type[]" value="commercial">อาคารพาณิชย์</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="building_type[]" value="other">อื่นๆ</label>
                        <input type="text" class="ppf-inp" name="building_detail_other" id="ppf_building_detail_other" style="max-width:100px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- รั้วกั้น -->
                    <div class="ppf-bh ppf-i1"><span class="ppf-bk"></span><span>ลักษณะรั้วกั้น</span></div>
                    <div class="ppf-cg ppf-i2">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb ppf-radio-toggle" name="fence_type" value="has_fence" data-group="ppf_fence">มีรั้วกั้น</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb ppf-radio-toggle" name="fence_type" value="no_fence" data-group="ppf_fence">ไม่มีรั้วกั้น</label>
                    </div>

                    <!-- เมื่อหันหน้าเข้า -->
                    <div class="ppf-bh ppf-i1"><span class="ppf-bk"></span><span>เมื่อหันหน้าเข้าสถานที่เกิดเหตุ ติดกับ</span></div>
                    <div class="ppf-i2">
                        <div class="ppf-fr"><span class="ppf-fl">ด้านหน้า</span><input type="text" class="ppf-inp" name="scene_front"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>
                        <div class="ppf-fr"><span class="ppf-fl">ด้านซ้าย</span><input type="text" class="ppf-inp" name="scene_left"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>
                        <div class="ppf-fr"><span class="ppf-fl">ด้านขวา</span><input type="text" class="ppf-inp" name="scene_right"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>
                        <div class="ppf-fr"><span class="ppf-fl">ด้านหลัง</span><input type="text" class="ppf-inp" name="scene_back"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>
                    </div>

                    <!-- 6.2 ลักษณะภายใน -->
                    <div class="ppf-bh" style="margin-top:4px;"><span class="ppf-bk"></span><span>6.2 ลักษณะภายใน</span><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>
                    <div class="ppf-i1">
                        <textarea class="ppf-ta" name="scene_interior" id="ppf_scene_interior" rows="3"></textarea>
                    </div>

                    <!-- 6.3 จุดที่เกิดเหตุ -->
                    <div class="ppf-bh" style="margin-top:4px;"><span class="ppf-bk"></span><span>6.3 จุดที่เกิดเหตุ</span><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>
                    <div class="ppf-i1">
                        <textarea class="ppf-ta" name="scene_point" id="ppf_scene_point" rows="3"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ppf-footer">
        <div class="ppf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="ppf-footer-right">F-CS-05 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 2 ============================== -->
<!-- ================================================================ -->
<div class="ppf-page">

    <div class="ppf-header">
        <div class="ppf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="ppf-header-center">
            <div class="ppf-title-main">ตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="ppf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์</div>
        </div>
        <div class="ppf-header-right">
            <div class="ppf-doc-box">
                <div class="ppf-doc-line">รายงานที่ <span class="ppf-rpt-no-mirror"></span> / 25<span class="ppf-rpt-year-mirror"></span></div>
                <div class="ppf-doc-line">หน้าที่ <span class="ppf-cur-page"></span> / <span class="ppf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div class="ppf-form-body">
        <!-- LEFT COLUMN -->
        <div class="ppf-col-left">
            <div class="ppf-row-header"><div class="ppf-lbl-seq">ลำดับ</div><div class="ppf-lbl-data">ข้อมูล</div></div>

            <!-- 7. ผลการตรวจสถานที่เกิดเหตุ -->
            <div class="ppf-sec-row" style="flex:1; border-bottom:none;">
                <div class="ppf-sec-label">
                    <span class="ppf-sec-num">7.</span>
                    <span class="ppf-sec-txt">ผลการ<br>ตรวจ<br>สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="ppf-sec-body">
                    <!-- พฤติการณ์ของคดี -->
                    <div class="ppf-bh" style="margin-top:0;"><span class="ppf-bk"></span><span>พฤติการณ์ของคดี</span><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>
                    <textarea class="ppf-ta" name="case_behavior" id="ppf_case_behavior" rows="4"></textarea>

                    <!-- ทางเข้าของคนร้าย -->
                    <div class="ppf-bh"><span class="ppf-bk"></span><span>ทางเข้าของคนร้าย</span></div>
                    <div class="ppf-cg ppf-i1">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="entry_point[]" value="no_trace">ไม่พบร่องรอย</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="entry_point[]" value="unlocked">ไม่ได้ปิดล็อก</label>
                    </div>
                    <div class="ppf-cg ppf-i1">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="entry_point[]" value="found_trace">พบร่องรอยงัดแงะ/บุกรุก</label>
                    </div>

                    <!-- ลักษณะการกระทำ -->
                    <div class="ppf-bh ppf-i1"><span class="ppf-bk"></span><span>ลักษณะการกระทำ</span></div>
                    <div class="ppf-cg ppf-i2">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="entry_point[]" value="pry">การงัด</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="entry_point[]" value="cut">การตัด</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="entry_point[]" value="drill">การเจาะ</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="entry_point[]" value="other_trace">อื่นๆ</label>
                        <input type="text" class="ppf-inp" name="entry_other_trace_detail" style="max-width:80px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- เครื่องมือที่คนร้ายใช้ -->
                    <div class="ppf-bh ppf-i1"><span class="ppf-bk"></span><span>เครื่องมือที่คนร้ายใช้</span></div>
                    <div class="ppf-cg ppf-i2">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="burglary_tool[]" value="screwdriver">ไขควง</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="burglary_tool[]" value="crowbar">ชะแลง</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="burglary_tool[]" value="bolt_cutter">คีมตัดโลหะ</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="burglary_tool[]" value="other">อื่นๆ</label>
                        <input type="text" class="ppf-inp" name="tool_other_detail" style="max-width:80px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="ppf-fr ppf-i2">
                        <span class="ppf-fl">ขนาดความกว้างของรอย</span>
                        <input type="text" class="ppf-inp-s" name="trace_width" style="max-width:40px;">
                        <span class="ppf-fl">ซม.</span>
                    </div>

                    <!-- บริเวณ/ตำแหน่ง -->
                    <div class="ppf-bh ppf-i1"><span class="ppf-bk"></span><span>บริเวณ/ตำแหน่ง</span></div>
                    <div class="ppf-cg ppf-i2">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="entry_point[]" value="door">ประตู</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="entry_point[]" value="window">หน้าต่าง</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="entry_point[]" value="ceiling">ฝ้าเพดาน</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="entry_point[]" value="roof">หลังคา</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="entry_point[]" value="other_location">อื่นๆ</label>
                    </div>

                    <!-- คดีชิงทรัพย์/ปล้นทรัพย์ -->
                    <div class="ppf-bh"><span class="ppf-bk"></span><span>คดีชิงทรัพย์/ปล้นทรัพย์</span></div>
                    <div class="ppf-fr ppf-i1">
                        <span class="ppf-fl">จำนวนคนร้าย</span>
                        <input type="text" class="ppf-inp-s" name="perpetrator_count" style="max-width:40px;">
                        <span class="ppf-fl">คน</span>
                    </div>
                    <div class="ppf-cg ppf-i1">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb ppf-radio-toggle" name="weapon_use_status" value="none" data-group="ppf_weapon">ไม่ใช้อาวุธ</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb ppf-radio-toggle" name="weapon_use_status" value="used" data-group="ppf_weapon">ใช้อาวุธ</label>
                    </div>
                    <div class="ppf-cg ppf-i2">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="weapon_type[]" value="knife">มีด</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="weapon_type[]" value="gun">ปืน</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="weapon_type[]" value="rope">เชือก</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="weapon_type[]" value="other">อื่นๆ</label>
                        <input type="text" class="ppf-inp" name="weapon_other_detail" style="max-width:80px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- พันธนาการ -->
                    <div class="ppf-bh ppf-i1"><span class="ppf-bk"></span><span>คนร้ายพันธนาการผู้เสียหาย</span></div>
                    <div class="ppf-cg ppf-i2">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="restraint_method[]" value="confinement">กักขังภายในห้อง</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="restraint_method[]" value="binding">ใช้วัสดุพันธนาการ</label>
                    </div>
                    <div class="ppf-fr ppf-i2">
                        <span class="ppf-fl">วัสดุ</span>
                        <input type="text" class="ppf-inp" name="binding_material"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- ผลต่อผู้เสียหาย -->
                    <div class="ppf-cg ppf-i1">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="victim_status[]" value="injured">มีผู้บาดเจ็บ</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="victim_status[]" value="deceased">มีผู้เสียชีวิต</label>
                    </div>
                    <div class="ppf-fr ppf-i1">
                        <span class="ppf-fl">ลักษณะ/ตำแหน่ง/จำนวนบาดแผล</span><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>
                    <textarea class="ppf-ta ppf-i1" name="injury_detail" rows="3"></textarea>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="ppf-col-right">
            <div class="ppf-row-header"><div class="ppf-lbl-seq">ลำดับ</div><div class="ppf-lbl-data">ข้อมูล</div></div>

            <!-- 7. (ต่อ) -->
            <div class="ppf-sec-row" style="flex:1; border-bottom:none;">
                <div class="ppf-sec-label"><span class="ppf-sec-num">7.</span><span class="ppf-sec-txt">(ต่อ)</span></div>
                <div class="ppf-sec-body">
                    <!-- ทรัพย์สินถูกโจรกรรม -->
                    <div class="ppf-bh" style="margin-top:0;"><span class="ppf-bk"></span><span>ทรัพย์สินถูกโจรกรรม</span><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>
                    <textarea class="ppf-ta" name="stolen_property" id="ppf_stolen_property" rows="6"></textarea>

                    <!-- วัตถุพยานที่ตรวจพบ -->
                    <div class="ppf-bh"><span class="ppf-bk"></span><span>วัตถุพยานที่ตรวจพบ</span></div>
                    <div id="ppf_evidence_container">
                        <div class="ppf-fr ppf-evidence-row">
                            <span class="ppf-si-no">1.</span>
                            <input type="text" class="ppf-inp" name="evidence_item[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            <button type="button" class="ppf-del-btn" onclick="ppfDelEvidenceRow(this)" style="display:none;">×</button>
                        </div>
                    </div>
                    <button type="button" class="ppf-add-btn" onclick="ppfAddEvidenceRow()">+ เพิ่มวัตถุพยาน</button>

                    <!-- คราบสีแดงคล้ายโลหิต -->
                    <div class="ppf-bh"><span class="ppf-bk"></span><span>คราบสีแดงคล้ายโลหิต</span></div>
                    <div class="ppf-cg ppf-i1">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="evidence_blood_stain" value="1">คราบสีแดงคล้ายโลหิต</label>
                    </div>
                    <div class="ppf-fr ppf-i1">
                        <span class="ppf-fl">รายละเอียด</span><button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="ppf_blood_stain_detail" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="ppf-i1">
                        <textarea class="ppf-ta" name="blood_stain_detail" id="ppf_blood_stain_detail" rows="2"></textarea>
                    </div>

                    <!-- Hemastix -->
                    <div class="ppf-bh ppf-i1"><span class="ppf-bk"></span><span>Hemastix</span></div>
                    <div class="ppf-cg ppf-i2">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="test_hemastix" value="1">ทดสอบ</label>
                    </div>
                    <div class="ppf-cg ppf-i2">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb ppf-radio-toggle" name="hemastix_result" value="มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน" data-group="ppf_hemastix">เปลี่ยนเป็นสีเขียวแกมน้ำเงิน</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb ppf-radio-toggle" name="hemastix_result" value="ไม่มีการเปลี่ยนแปลง" data-group="ppf_hemastix">ไม่มีการเปลี่ยนแปลง</label>
                    </div>

                    <!-- Phenolphthalein -->
                    <div class="ppf-bh ppf-i1"><span class="ppf-bk"></span><span>Phenolphthalein</span></div>
                    <div class="ppf-cg ppf-i2">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="test_phenolphthalein" value="1">ทดสอบ</label>
                    </div>
                    <div class="ppf-cg ppf-i2">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb ppf-radio-toggle" name="phenol_result" value="มีการเปลี่ยนแปลงเป็นสีชมพูในทันที" data-group="ppf_phenol">เปลี่ยนเป็นสีชมพูในทันที</label>
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb ppf-radio-toggle" name="phenol_result" value="ไม่มีการเปลี่ยนแปลง" data-group="ppf_phenol">ไม่มีการเปลี่ยนแปลง</label>
                    </div>

                    <!-- การตรวจสอบครั้งสุดท้าย -->
                    <div class="ppf-bh"><span class="ppf-bk"></span><span>การตรวจสอบครั้งสุดท้าย</span></div>
                    <div class="ppf-cg ppf-i1">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="final_check[]" value="collected_all">ตรวจเก็บวัตถุพยานครบถ้วน</label>
                    </div>
                    <div class="ppf-cg ppf-i1">
                        <label class="ppf-ck"><input type="checkbox" class="ppf-cb" name="final_check[]" value="photos_taken">ถ่ายภาพและส่งมอบ</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ppf-footer">
        <div class="ppf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="ppf-footer-right">F-CS-05 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 3 ============================== -->
<!-- ================================================================ -->
<div class="ppf-page">

    <div class="ppf-header">
        <div class="ppf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="ppf-header-center">
            <div class="ppf-title-main">ตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="ppf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์</div>
        </div>
        <div class="ppf-header-right">
            <div class="ppf-doc-box">
                <div class="ppf-doc-line">รายงานที่ <span class="ppf-rpt-no-mirror"></span> / 25<span class="ppf-rpt-year-mirror"></span></div>
                <div class="ppf-doc-line">หน้าที่ <span class="ppf-cur-page"></span> / <span class="ppf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div class="ppf-form-body">
        <!-- LEFT COLUMN -->
        <div class="ppf-col-left">
            <div class="ppf-row-header"><div class="ppf-lbl-seq">ลำดับ</div><div class="ppf-lbl-data">ข้อมูล</div></div>

            <!-- 7. (ต่อ) สภาพร่องรอยและตำแหน่งที่ตรวจพบ -->
            <div class="ppf-sec-row" style="flex:1; border-bottom:none;">
                <div class="ppf-sec-label"><span class="ppf-sec-num">7.</span><span class="ppf-sec-txt">(ต่อ)<br>สภาพ<br>ร่องรอย</span></div>
                <div class="ppf-sec-body">
                    <div class="ppf-bh" style="margin-top:0;"><span class="ppf-bk"></span><span>สภาพร่องรอยและตำแหน่งที่ตรวจพบ</span></div>
                    <div id="ppf_trace_point_container">
                        <div class="ppf-trace-row" style="margin-bottom:6px; padding-bottom:4px; border-bottom:1px dotted #ccc;">
                            <div class="ppf-fr">
                                <span class="ppf-fl-b">จุดที่ 1</span>
                            </div>
                            <div class="ppf-fr">
                                <span class="ppf-fl">ตำแหน่ง</span>
                                <input type="text" class="ppf-inp" name="trace_position[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                            <div class="ppf-fr">
                                <span class="ppf-fl">ลักษณะร่องรอย</span>
                                <input type="text" class="ppf-inp" name="trace_detail[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="ppf-add-btn" onclick="ppfAddTracePoint()">+ เพิ่มจุดตรวจพบ</button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="ppf-col-right">
            <div class="ppf-row-header"><div class="ppf-lbl-seq">ลำดับ</div><div class="ppf-lbl-data">ข้อมูล</div></div>

            <!-- 8. การส่งมอบคืนสถานที่เกิดเหตุ -->
            <div class="ppf-sec-row" style="flex:1; border-bottom:none;">
                <div class="ppf-sec-label"><span class="ppf-sec-num">8.</span><span class="ppf-sec-txt">การส่ง<br>มอบคืน<br>สถานที่<br>เกิดเหตุ</span></div>
                <div class="ppf-sec-body">
                    <div class="ppf-bh" style="margin-top:0;"><span class="ppf-bk"></span><span>วันเวลาที่ตรวจเสร็จสิ้น</span></div>
                    <div class="ppf-fr ppf-i1">
                        <input type="datetime-local" class="ppf-inp" name="inspection_end_datetime" id="ppf_inspection_end_datetime" style="text-align:center;">
                    </div>

                    <div class="ppf-bh" style="margin-top:6px;"><span class="ppf-bk"></span><span>การส่งมอบสถานที่เกิดเหตุ</span></div>

                    <!-- ผู้รับมอบ -->
                    <div class="ppf-fr" style="margin-top:6px; align-items:flex-end;">
                        <span class="ppf-fl">ลงชื่อ</span>
                        <div style="flex:1; min-height:50px; border-bottom:1px dotted #888; position:relative;">
                            <canvas id="ppf_sig_receiver" style="width:100%; height:50px; cursor:crosshair;"></canvas>
                            <input type="hidden" name="receiver_signature_data" id="ppf_receiver_sig_data">
                            <button type="button" class="ppf-sig-clear-btn" onclick="ppfClearCanvas('ppf_sig_receiver')" title="ล้างลายเซ็น">&times;</button>
                        </div>
                        <span class="ppf-fl">ผู้รับมอบ</span>
                    </div>
                    <div class="ppf-fr" style="margin-top:2px; justify-content:flex-end;">
                        <button type="button" class="ppf-add-btn" onclick="ppfClearCanvas('ppf_sig_receiver')">ล้างลายเซ็นผู้รับมอบ</button>
                    </div>
                    <div class="ppf-fr">
                        <span class="ppf-fl" style="visibility:hidden;">ลงชื่อ</span>
                        <span class="ppf-fl">(</span>
                        <select class="ppf-sel" name="receiver_id" id="ppf_receiver_name" style="text-align:center;">
                            <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้รับมอบ --', $ppfInspectorOptions) ?>
                        </select>
                        <span class="ppf-fl">)</span>
                    </div>
                    <div class="ppf-fr">
                        <span class="ppf-fl">ตำแหน่ง</span>
                        <input type="text" class="ppf-inp" name="receiver_position" id="ppf_receiver_position" readonly>
                    </div>

                    <!-- ผู้ส่งมอบ -->
                    <div class="ppf-fr" style="margin-top:8px; align-items:flex-end;">
                        <span class="ppf-fl">ลงชื่อ</span>
                        <div style="flex:1; min-height:50px; border-bottom:1px dotted #888; position:relative;">
                            <canvas id="ppf_sig_sender" style="width:100%; height:50px; cursor:crosshair;"></canvas>
                            <input type="hidden" name="deliverer_signature_data" id="ppf_sender_sig_data">
                            <button type="button" class="ppf-sig-clear-btn" onclick="ppfClearCanvas('ppf_sig_sender')" title="ล้างลายเซ็น">&times;</button>
                        </div>
                        <span class="ppf-fl">ผู้ส่งมอบ</span>
                    </div>
                    <div class="ppf-fr" style="margin-top:2px; justify-content:flex-end;">
                        <button type="button" class="ppf-add-btn" onclick="ppfClearCanvas('ppf_sig_sender')">ล้างลายเซ็นผู้ส่งมอบ</button>
                    </div>
                    <div class="ppf-fr">
                        <span class="ppf-fl" style="visibility:hidden;">ลงชื่อ</span>
                        <span class="ppf-fl">(</span>
                        <select class="ppf-sel" name="deliverer_id" id="ppf_sender_name" style="text-align:center;">
                            <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งมอบ --', $ppfInspectorOptions) ?>
                        </select>
                        <span class="ppf-fl">)</span>
                    </div>
                    <div class="ppf-fr">
                        <span class="ppf-fl">ตำแหน่ง</span>
                        <input type="text" class="ppf-inp" name="deliverer_position" id="ppf_sender_position" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ppf-footer">
        <div class="ppf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="ppf-footer-right">F-CS-05 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 4 (EVIDENCE LOCATION) ========== -->
<!-- ================================================================ -->
<div class="ppf-page">

    <div class="ppf-header">
        <div class="ppf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="ppf-header-center">
            <div class="ppf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="ppf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์</div>
        </div>
        <div class="ppf-header-right">
            <div class="ppf-doc-box">
                <div class="ppf-doc-line">รายงานที่ <span class="ppf-rpt-no-mirror"></span> / 25<span class="ppf-rpt-year-mirror"></span></div>
                <div class="ppf-doc-line">หน้าที่ <span class="ppf-cur-page"></span> / <span class="ppf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:6px;">
        <span class="ppf-bk"></span>
        <span style="font-size:12px; font-weight:600;">วัตถุพยานและตำแหน่งที่ตรวจพบ</span>
        <button type="button" class="ppf-add-btn" style="float:right;" onclick="ppfAddEvidenceLocationRow()">+ เพิ่มรายการ</button>
    </div>

    <!-- UPDATED 2026-05-19: Distance inputs changed from checkbox to text -->
    <table class="ppf-ev-table" style="width:100%; border-collapse:collapse; font-size:11px;">
        <thead>
            <tr>
                <th rowspan="2" style="border:1.5px solid #000; width:40px; padding:2px; text-align:center; vertical-align:middle;">ป้าย<br>หมายเลข</th>
                <th rowspan="2" style="border:1.5px solid #000; padding:2px; text-align:center; vertical-align:middle;">วัตถุพยาน</th>
                <th colspan="4" style="border:1.5px solid #000; padding:2px; text-align:center;">ระยะห่าง (m) จากจุดอ้างอิง</th>
                <th rowspan="2" style="border:1.5px solid #000; width:80px; padding:2px; text-align:center; vertical-align:middle;">Azimuth<br>พิกัด/องศา/ระยะ</th>
                <th rowspan="2" style="border:1.5px solid #000; width:60px; padding:2px; text-align:center; vertical-align:middle;">หมายเหตุ</th>
                <th rowspan="2" style="border:1.5px solid #000; width:120px; padding:2px; text-align:center; vertical-align:middle;">การตรวจพิสูจน์</th>
                <th rowspan="2" style="border:1.5px solid #000; width:25px; padding:2px; text-align:center; vertical-align:middle; font-size:9px;">ลบ</th>
            </tr>
            <tr>
                <th style="border:1.5px solid #000; width:30px; padding:2px; text-align:center;">1</th>
                <th style="border:1.5px solid #000; width:30px; padding:2px; text-align:center;">2</th>
                <th style="border:1.5px solid #000; width:30px; padding:2px; text-align:center;">3</th>
                <th style="border:1.5px solid #000; width:30px; padding:2px; text-align:center;">4</th>
            </tr>
        </thead>
        <tbody id="ppf_evidence_location_tbody">
            <tr>
                <td><input type="text" name="evidence_label[]" style="width:35px;" value="1"></td>
                <td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_name[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>
                <td><input type="text" name="evidence_level_1[]" style="width:28px; background:#fffacd;"></td>
                <td><input type="text" name="evidence_level_2[]" style="width:28px; background:#fffacd;"></td>
                <td><input type="text" name="evidence_level_3[]" style="width:28px; background:#fffacd;"></td>
                <td><input type="text" name="evidence_level_4[]" style="width:28px; background:#fffacd;"></td>
                <td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_azimuth[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>
                <td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_remark[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>
                <td>
                    <select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;">
                        <option value="">--</option>
                        <option value="fingerprint">ลายนิ้วมือแฝง</option>
                        <option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option>
                        <option value="chemical">เคมีฟิสิกส์</option>
                        <option value="drug">ยาเสพติด</option>
                        <option value="gun">อาวุธปืน</option>
                        <option value="document">เอกสาร</option>
                        <option value="digital">ดิจิทัล</option>
                        <option value="computer">คอมพิวเตอร์</option>
                    </select>
                    <input type="hidden" class="lab-unit-value" name="evidence_lab_unit[]" value="">
                </td>
                <td><button type="button" class="ppf-del-btn" onclick="ppfDelRow(this)">×</button></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top:8px; font-size:11.5px;">
        <div class="ppf-fr" style="margin-bottom:2px;">
            <span class="ppf-fl">จุดอ้างอิงที่ 1 คือ</span>
            <input type="text" class="ppf-inp" name="reference_point_1"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
        </div>
        <div class="ppf-fr" style="margin-bottom:2px;">
            <span class="ppf-fl">จุดอ้างอิงที่ 2 คือ</span>
            <input type="text" class="ppf-inp" name="reference_point_2"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
        </div>
        <div class="ppf-fr" style="margin-bottom:2px;">
            <span class="ppf-fl">จุดอ้างอิงที่ 3 คือ</span>
            <input type="text" class="ppf-inp" name="reference_point_3"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
        </div>
        <div class="ppf-fr" style="margin-bottom:2px;">
            <span class="ppf-fl">จุดอ้างอิงที่ 4 คือ</span>
            <input type="text" class="ppf-inp" name="reference_point_4"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
        </div>
    </div>

    <div style="margin-top:12px; display:flex; flex-direction:column; align-items:flex-end;">
        <div class="ppf-fr" style="width:auto;">
            <span class="ppf-fl">ผู้จดบันทึก</span>
            <input type="text" class="ppf-inp" name="evidence_location_recorder" id="ppf_ev_loc_recorder" style="width:250px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
        </div>
        <div class="ppf-fr" style="width:auto;">
            <span class="ppf-fl">วัน/เวลา</span>
            <input type="datetime-local" class="ppf-inp" name="evidence_location_datetime" id="ppf_ev_loc_datetime" style="width:250px; text-align:center;">
        </div>
    </div>

    <div class="ppf-footer">
        <div class="ppf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="ppf-footer-right">F-CS-05 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 5 (EVIDENCE COLLECTION) ======== -->
<!-- ================================================================ -->
<div class="ppf-page">

    <div class="ppf-header">
        <div class="ppf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="ppf-header-center">
            <div class="ppf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="ppf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์</div>
            <div style="font-size:11px; font-weight:600;">บันทึกการตรวจเก็บวัตถุพยาน</div>
        </div>
        <div class="ppf-header-right">
            <div class="ppf-doc-box">
                <div class="ppf-doc-line">รายงานที่ <span class="ppf-rpt-no-mirror"></span> / 25<span class="ppf-rpt-year-mirror"></span></div>
                <div class="ppf-doc-line">หน้าที่ <span class="ppf-cur-page"></span> / <span class="ppf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:6px;">
        <div class="ppf-fr">
            <span class="ppf-fl">วันที่ตรวจสถานที่เกิดเหตุ</span>
            <input type="date" class="ppf-inp" name="collection_inspection_date" style="text-align:center;">
            <span class="ppf-fl">เวลาประมาณ</span>
            <input type="time" class="ppf-inp" name="collection_inspection_time" style="text-align:center;">
            <span class="ppf-fl">น.</span>
        </div>
        <button type="button" class="ppf-add-btn" style="float:right;" onclick="ppfAddCollectionRow()">+ เพิ่มรายการ</button>
    </div>

    <table class="ppf-ev-table" style="width:100%; border-collapse:collapse; font-size:10.5px;">
        <thead>
            <tr>
                <th rowspan="2" style="border:1.5px solid #000; width:30px; padding:2px; text-align:center; vertical-align:middle;">ลำดับ</th>
                <th rowspan="2" style="border:1.5px solid #000; padding:2px; text-align:center; vertical-align:middle;">รายการวัตถุพยาน</th>
                <th rowspan="2" style="border:1.5px solid #000; width:35px; padding:2px; text-align:center; vertical-align:middle;">จำนวน</th>
                <th rowspan="2" style="border:1.5px solid #000; padding:2px; text-align:center; vertical-align:middle;">บริเวณที่ตรวจพบ</th>
                <th rowspan="2" style="border:1.5px solid #000; width:35px; padding:2px; text-align:center; vertical-align:middle;">ป้าย<br>หมายเลข</th>
                <th colspan="3" style="border:1.5px solid #000; padding:2px; text-align:center;">การบรรจุหีบห่อ</th>
                <th colspan="2" style="border:1.5px solid #000; padding:2px; text-align:center;">การดำเนินการ</th>
                <th rowspan="2" style="border:1.5px solid #000; width:45px; padding:2px; text-align:center; vertical-align:middle;">หมายเหตุ</th>
                <th rowspan="2" style="border:1.5px solid #000; width:120px; padding:2px; text-align:center; vertical-align:middle;">การตรวจพิสูจน์</th>
                <th rowspan="2" style="border:1.5px solid #000; width:20px; padding:2px; text-align:center; vertical-align:middle; font-size:9px;">ลบ</th>
            </tr>
            <tr>
                <th style="border:1.5px solid #000; width:35px; padding:2px; text-align:center;">พลาสติก</th>
                <th style="border:1.5px solid #000; width:35px; padding:2px; text-align:center;">กระดาษ</th>
                <th style="border:1.5px solid #000; width:30px; padding:2px; text-align:center;">อื่นๆ</th>
                <th style="border:1.5px solid #000; width:40px; padding:2px; text-align:center;">คืนพงส.</th>
                <th style="border:1.5px solid #000; width:30px; padding:2px; text-align:center;">อื่นๆ</th>
            </tr>
        </thead>
        <tbody id="ppf_collection_tbody">
            <tr>
                <td style="text-align:center;">1</td>
                <td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="collection_item[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>
                <td><input type="text" name="collection_quantity[]" style="width:30px; text-align:center;"></td>
                <td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="collection_area[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>
                <td><input type="text" name="collection_label[]" style="width:30px; text-align:center;" value="1"></td>
                <td><input type="checkbox" name="collection_plastic_0" value="1"></td>
                <td><input type="checkbox" name="collection_paper_0" value="1"></td>
                <td><input type="checkbox" name="collection_pack_other_0" value="1"></td>
                <td><input type="checkbox" name="collection_return_0" value="1"></td>
                <td><input type="checkbox" name="collection_action_other_0" value="1"></td>
                <td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="collection_remark[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>
                <td>
                    <select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;">
                        <option value="">--</option>
                        <option value="fingerprint">ลายนิ้วมือแฝง</option>
                        <option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option>
                        <option value="chemical">เคมีฟิสิกส์</option>
                        <option value="drug">ยาเสพติด</option>
                        <option value="gun">อาวุธปืน</option>
                        <option value="document">เอกสาร</option>
                        <option value="digital">ดิจิทัล</option>
                        <option value="computer">คอมพิวเตอร์</option>
                    </select>
                    <input type="hidden" class="lab-unit-value" name="collection_forensic_unit[]" value="">
                </td>
                <td><button type="button" class="ppf-del-btn" onclick="ppfDelRow(this)">×</button></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top:12px; display:flex; flex-direction:column; align-items:flex-end;">
        <div class="ppf-fr" style="width:auto;">
            <span class="ppf-fl">ผู้จดบันทึก</span>
            <input type="text" class="ppf-inp" name="collection_recorder" id="ppf_collection_recorder" style="width:250px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
        </div>
        <div class="ppf-fr" style="width:auto;">
            <span class="ppf-fl">วัน /เวลา</span>
            <input type="datetime-local" class="ppf-inp" name="collection_datetime" id="ppf_collection_datetime" style="width:250px; text-align:center;">
        </div>
    </div>

    <div class="ppf-footer">
        <div class="ppf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="ppf-footer-right">F-CS-05 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 6 (SKETCH) ===================== -->
<!-- ================================================================ -->
<div class="ppf-page ppf-sketch-page" id="ppf_sketch_page_1" data-sketch-page="1">

    <div class="ppf-header">
        <div class="ppf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="ppf-header-center">
            <div class="ppf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="ppf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์</div>
        </div>
        <div class="ppf-header-right">
            <div class="ppf-doc-box">
                <div class="ppf-doc-line">รายงานที่ <span class="ppf-rpt-no-mirror"></span> / 25<span class="ppf-rpt-year-mirror"></span></div>
                <div class="ppf-doc-line">หน้าที่ <span class="ppf-cur-page"></span> / <span class="ppf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
        <div></div>
        <div style="font-size:13px; font-weight:600;">แผนผังสังเขป</div>
        <div style="display:flex; gap:4px; align-items:center;">
            <label class="ppf-add-btn" style="padding:2px 8px; cursor:pointer; margin:0;" title="แนบรูปภาพพื้นหลัง">
                <i class="fas fa-image me-1"></i> แนบรูป
                <input type="file" accept="image/*" style="display:none;" onchange="ppfSketchAttachImage('ppf_sketch_page_1',this)">
            </label>
            <button type="button" class="ppf-add-btn" style="padding:2px 8px;" onclick="ppfSketchRemoveBg('ppf_sketch_page_1')" title="ลบรูปพื้นหลัง"><i class="fas fa-times"></i> ลบรูป</button>
        </div>
    </div>

    <div class="ppf-sketch-viewport" id="ppf_sketch_page_1_viewport">
        <div class="ppf-sketch-canvas-wrap" id="ppf_sketch_page_1_wrap" style="aspect-ratio:1120/660;">
            <canvas id="ppf_sketch_page_1_canvas" width="1120" height="660"></canvas>
        </div>
        <div class="ppf-not-to-scale">* NOT TO SCALE</div>
    </div>
    <div style="text-align:right; margin-top:4px; display:flex; justify-content:flex-end; gap:6px; align-items:center;">
        <button type="button" class="ppf-add-btn" onclick="sketchUndo('ppf_sketch_page_1_canvas')" style="padding:2px 8px;"><i class="fas fa-undo"></i> ย้อนกลับ</button>
        <button type="button" class="ppf-add-btn btn-sketch-eraser" id="ppf_sketch_page_1_canvas_eraser_btn" onclick="sketchToggleEraser('ppf_sketch_page_1_canvas')" style="padding:2px 8px;" title="ยางลบ"><i class="fas fa-eraser"></i></button>
        <div style="display:flex;align-items:center;gap:4px;">
            <i class="fas fa-pen" style="font-size:10px;color:#666;"></i>
            <input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize('ppf_sketch_page_1_canvas',this.value);this.nextElementSibling.textContent=this.value+'px'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">
            <span style="font-size:11px;color:#666;min-width:35px;">2px</span>
        </div>
        <label style="margin:0;cursor:pointer;" title="เลือกสี"><input type="color" value="#000000" onchange="sketchSetColor('ppf_sketch_page_1_canvas',this.value)" style="width:26px;height:22px;border:1px solid #aaa;border-radius:3px;padding:0;cursor:pointer;"></label>
        <button type="button" class="ppf-add-btn" onclick="ppfClearCanvas('ppf_sketch_page_1_canvas')">ล้างกระดาน</button>
    </div>

    <div style="margin-bottom:8px;">
        <div class="ppf-fr">
            <span class="ppf-fl">หมายเหตุ</span><button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="ppf_sketch_remark" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
        </div>
        <textarea class="ppf-ta" name="sketch_remark" id="ppf_sketch_remark" rows="5"></textarea>
    </div>

    <div style="margin-top:10px; display:flex; flex-direction:column; align-items:flex-end;">
        <div class="ppf-fr" style="width:auto;">
            <span class="ppf-fl">ผู้จดบันทึก</span>
            <input type="text" class="ppf-inp" name="recorder_name" id="ppf_recorder_name" style="width:250px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
        </div>
        <div class="ppf-fr" style="width:auto;">
            <span class="ppf-fl">วัน เวลา</span>
            <input type="datetime-local" class="ppf-inp" name="recorder_datetime" id="ppf_recorder_datetime" style="width:250px; text-align:center;">
        </div>
    </div>

    <div style="text-align:center; margin-top:8px;">
        <button type="button" class="ppf-add-btn" onclick="ppfSketchAddPage()" style="padding:3px 14px; font-size:12px;">
            <i class="fas fa-plus me-1"></i> เพิ่มหน้าแผนผัง
        </button>
    </div>

    <div class="ppf-footer">
        <div class="ppf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="ppf-footer-right">F-CS-05 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>
<!-- ★ Dynamic sketch pages will be inserted here by JS (before PAGE 7) -->

<!-- Hidden inputs for data persistence -->
<input type="hidden" name="scene_sketch_data" id="ppf_scene_sketch_data">
<input type="hidden" name="scene_sketch_pages" id="ppf_scene_sketch_pages_data">

<!-- ================================================================ -->
<!-- ========================= PAGE 5 (PHOTOS) ===================== -->
<!-- ================================================================ -->
<div class="ppf-page ppf-photo-page" data-photo-page="1">

    <div class="ppf-header">
        <div class="ppf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="ppf-header-center">
            <div class="ppf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="ppf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์</div>
            <div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ</div>
        </div>
        <div class="ppf-header-right">
            <div class="ppf-doc-box">
                <div class="ppf-doc-line">รายงานที่ <span class="ppf-rpt-no-mirror"></span> / 25<span class="ppf-rpt-year-mirror"></span></div>
                <div class="ppf-doc-line">หน้าที่ <span class="ppf-cur-page"></span> / <span class="ppf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:8px;">
        <div class="ppf-fr" style="flex-wrap:nowrap; margin-bottom:6px;">
            <span class="ppf-fl">วันที่ตรวจสถานที่เกิดเหตุ</span>
            <input type="date" class="ppf-inp" id="ppf_photo_inspect_date" style="min-width:120px;">
            <span class="ppf-fl" style="margin-left:12px;">เวลาประมาณ</span>
            <input type="time" class="ppf-inp-s" id="ppf_photo_inspect_time" style="min-width:60px;">
            <span class="ppf-fl">น.</span>
        </div>
        <div class="ppf-fr" style="flex-wrap:nowrap; margin-bottom:6px;">
            <span class="ppf-fl">รหัสภาพถ่ายที่</span>
            <input type="text" class="ppf-inp" name="photo_id_start" style="min-width:100px; font-size:9px;" readonly>
            <span class="ppf-fl">ถึง</span>
            <input type="text" class="ppf-inp" name="photo_id_end" style="min-width:100px; font-size:9px;" readonly>
            <span class="ppf-fl">จำนวน</span>
            <input type="text" class="ppf-inp-s" name="photo_amount" style="max-width:40px; text-align:center;" readonly>
            <span class="ppf-fl">ภาพ</span>
        </div>
        <div style="font-size:10.5px; font-style:italic; margin-top:2px;">(ตามภาพถ่ายรวมที่แนบ)</div>
    </div>

    <!-- Drag & Drop zone for PDF form -->
    <div class="ppf-photo-dropzone" id="ppf_photo_dropzone">
        <i class="fas fa-cloud-upload-alt" style="font-size:1.2rem; color:#90a4ae;"></i>
        <div style="font-size:10px; color:#666; margin-top:2px;">ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือก</div>
    </div>
    <input type="file" id="ppf_photo_input_gallery" accept="image/*" multiple style="display:none;">
    <input type="file" id="ppf_photo_input_camera" accept="image/*" capture="environment" multiple style="display:none;">

    <!-- 7×5 Photo Grid (35 photos per page, populated by JS) -->
    <div class="ppf-photo-grid" id="ppf_photo_grid_1"></div>

    <div class="ppf-footer">
        <div class="ppf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="ppf-footer-right">F-CS-05 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- Container สำหรับหน้ารูปถ่ายเพิ่มเติม -->
<div id="ppf_extra_photo_pages"></div>

<!-- ปุ่มเพิ่มหน้ากระดาษรูปถ่าย (ซ่อน — ระบบสร้างหน้าอัตโนมัติจากจำนวนรูป) -->
<button type="button" class="ppf-add-photo-page-btn" onclick="ppfAddPhotoPage()" style="display:none;">
    <i class="fas fa-plus me-1"></i> เพิ่มหน้าบันทึกการถ่ายภาพ
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <div>
                    <button type="button" class="btn btn-success btn-sm" id="btn_save_property_pdf" onclick="ppfSaveViaStandardForm()">
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
    window.ppfUpdatePageNumbers = function() {
        var pages = document.querySelectorAll('#propertyFormPdfModal .ppf-page');
        var total = pages.length;
        pages.forEach(function(page, idx) {
            var curEl = page.querySelector('.ppf-cur-page');
            var totalEl = page.querySelector('.ppf-total-page');
            if (curEl) curEl.textContent = (idx + 1);
            if (totalEl) totalEl.textContent = total;
        });
    };
    // Run on load
    ppfUpdatePageNumbers();

    // ===== Auto-grow textareas (พิมพ์เกิน → ช่องสูงขึ้น เส้นประเพิ่มเอง, คำตัดบรรทัดใหม่) =====
    var PPF_TA_LINE = 20; // ต้องตรงกับ line-height และ background-size ของ .ppf-ta
    function ppfAutoGrow(ta) {
        ta.style.height = 'auto';
        var h = Math.ceil(ta.scrollHeight / PPF_TA_LINE) * PPF_TA_LINE;
        ta.style.height = h + 'px';
    }
    window.ppfAutoGrowAll = function() {
        document.querySelectorAll('#propertyFormPdfModal .ppf-ta').forEach(function(ta) {
            if (!ta.dataset.ppfMinH) {
                var rows = parseInt(ta.getAttribute('rows'), 10) || 1;
                ta.style.minHeight = (rows * PPF_TA_LINE) + 'px';
                ta.dataset.ppfMinH = '1';
            }
            ppfAutoGrow(ta);
        });
    };
    document.querySelectorAll('#propertyFormPdfModal .ppf-ta').forEach(function(ta) {
        ta.addEventListener('input', function() { ppfAutoGrow(this); });
    });
    ppfAutoGrowAll();
    var ppfModalElAG = document.getElementById('propertyFormPdfModal');
    if (ppfModalElAG) {
        ppfModalElAG.addEventListener('shown.bs.modal', function() { ppfAutoGrowAll(); });
    }

    // ===== Radio-toggle (mutually exclusive checkboxes) =====
    document.querySelectorAll('#propertyFormPdfModal .ppf-radio-toggle').forEach(function(el) {
        el.addEventListener('change', function() {
            if (!this.checked) return;
            var group = this.getAttribute('data-group');
            document.querySelectorAll('#propertyFormPdfModal .ppf-radio-toggle[data-group="' + group + '"]').forEach(function(cb) {
                if (cb !== el) cb.checked = false;
            });
        });
    });

    // ===== case_type change → show/hide other =====
    var ppfCaseType = document.getElementById('ppf_case_type');
    if (ppfCaseType) {
        ppfCaseType.addEventListener('change', function() {
            var otherDiv = document.getElementById('ppf_case_type_other_div');
            if (otherDiv) otherDiv.style.display = this.value === 'other' ? '' : 'none';
        });
    }

    // ===== Inspector counter =====
    window.ppfRenumberInspectors = function() {
        var rows = document.querySelectorAll('#ppf_inspector_container .ppf-inspector-row');
        rows.forEach(function(row, idx) {
            row.querySelector('.ppf-si-no').textContent = '5.' + (idx + 1);
        });
    };
    window.ppfAddInspector = function() {
        var container = document.getElementById('ppf_inspector_container');
        var nextNum = container.querySelectorAll('.ppf-inspector-row').length + 1;
        var row = document.createElement('div');
        row.className = 'ppf-si ppf-inspector-row';
        row.innerHTML = '<span class="ppf-si-no">5.' + nextNum + '</span>' +
            '<select class="ppf-sel" name="inspector_id[]">' +
            document.querySelector('#ppf_inspector_container select').innerHTML +
            '</select>' +
            ' <button type="button" class="ppf-del-btn" onclick="ppfDelInspector(this)">×</button>';
        container.appendChild(row);
    };
    window.ppfDelInspector = function(btn) {
        btn.closest('.ppf-inspector-row').remove();
        ppfRenumberInspectors();
    };

    // ===== Evidence rows =====
    var ppfEvidenceIdx = 1;
    window.ppfAddEvidenceRow = function() {
        ppfEvidenceIdx++;
        var container = document.getElementById('ppf_evidence_container');
        var row = document.createElement('div');
        row.className = 'ppf-fr ppf-evidence-row';
        row.innerHTML = '<span class="ppf-si-no">' + ppfEvidenceIdx + '.</span>' +
            '<input type="text" class="ppf-inp" name="evidence_item[]">' +
            '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>' +
            ' <button type="button" class="ppf-del-btn" onclick="ppfDelEvidenceRow(this)">×</button>';
        container.appendChild(row);
    };

    window.ppfDelEvidenceRow = function(btn) {
        var container = document.getElementById('ppf_evidence_container');
        if (container.querySelectorAll('.ppf-evidence-row').length > 1) {
            btn.closest('.ppf-evidence-row').remove();
        }
    };

    // ===== Trace point rows =====
    var ppfTraceIdx = 1;
    window.ppfAddTracePoint = function() {
        ppfTraceIdx++;
        var container = document.getElementById('ppf_trace_point_container');
        var row = document.createElement('div');
        row.className = 'ppf-trace-row';
        row.style.cssText = 'margin-bottom:6px; padding-bottom:4px; border-bottom:1px dotted #ccc;';
        row.innerHTML =
            '<div class="ppf-fr"><span class="ppf-fl-b">จุดที่ ' + ppfTraceIdx + '</span>' +
            ' <button type="button" class="ppf-del-btn" onclick="this.closest(\'.ppf-trace-row\').remove()">×</button></div>' +
            '<div class="ppf-fr"><span class="ppf-fl">ตำแหน่ง</span><input type="text" class="ppf-inp" name="trace_position[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>' +
            '<div class="ppf-fr"><span class="ppf-fl">ลักษณะร่องรอย</span><input type="text" class="ppf-inp" name="trace_detail[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>';
        container.appendChild(row);
    };

    // ===== Evidence Location rows (Page 4) =====
    var ppfEvLocRowIdx = 0;
    window.ppfAddEvidenceLocationRow = function() {
        ppfEvLocRowIdx++;
        var tbody = document.getElementById('ppf_evidence_location_tbody');
        if (!tbody) return;
        var rowNum = tbody.rows.length + 1;
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><input type="text" name="evidence_label[]" style="width:35px;" value="' + rowNum + '"></td>' +
            '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_name[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
            '<td><input type="text" name="evidence_level_1[]" style="width:28px;"></td>' +
            '<td><input type="text" name="evidence_level_2[]" style="width:28px;"></td>' +
            '<td><input type="text" name="evidence_level_3[]" style="width:28px;"></td>' +
            '<td><input type="text" name="evidence_level_4[]" style="width:28px;"></td>' +
            '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_azimuth[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
            '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_remark[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
            '<td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option></select><input type="hidden" class="lab-unit-value" name="evidence_lab_unit[]" value=""></td>' +
            '<td><button type="button" class="ppf-del-btn" onclick="ppfDelRow(this)">×</button></td>';
        tbody.appendChild(tr);
    };

    // ===== Evidence Collection rows (Page 5) =====
    var ppfCollRowIdx = 0;
    window.ppfAddCollectionRow = function() {
        ppfCollRowIdx++;
        var tbody = document.getElementById('ppf_collection_tbody');
        if (!tbody) return;
        var rowNum = tbody.rows.length + 1;
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td style="text-align:center;">' + rowNum + '</td>' +
            '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="collection_item[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
            '<td><input type="text" name="collection_quantity[]" style="width:30px; text-align:center;"></td>' +
            '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="collection_area[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
            '<td><input type="text" name="collection_label[]" style="width:30px; text-align:center;" value="' + rowNum + '"></td>' +
            '<td><input type="checkbox" name="collection_plastic_' + ppfCollRowIdx + '" value="1"></td>' +
            '<td><input type="checkbox" name="collection_paper_' + ppfCollRowIdx + '" value="1"></td>' +
            '<td><input type="checkbox" name="collection_pack_other_' + ppfCollRowIdx + '" value="1"></td>' +
            '<td><input type="checkbox" name="collection_return_' + ppfCollRowIdx + '" value="1"></td>' +
            '<td><input type="checkbox" name="collection_action_other_' + ppfCollRowIdx + '" value="1"></td>' +
            '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="collection_remark[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
            '<td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option></select><input type="hidden" class="lab-unit-value" name="collection_forensic_unit[]" value=""></td>' +
            '<td><button type="button" class="ppf-del-btn" onclick="ppfDelRow(this)">×</button></td>';
        tbody.appendChild(tr);
    };

    // ===== Delete table row =====
    window.ppfDelRow = function(btn) {
        var tbody = btn.closest('tbody');
        if (tbody && tbody.rows.length > 1) {
            btn.closest('tr').remove();
        }
    };

    // ===== Canvas clear =====
    window.ppfClearCanvas = function(canvasId) {
        var canvas = document.getElementById(canvasId);
        if (canvas) {
              if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'ยืนยันการล้างภาพ',
                    text: 'คุณต้องการล้างภาพทั้งหมดหรือไม่?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#dc3545',
                    confirmButtonText: 'ยืนยัน, ล้างเลย!',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                         var ctx = canvas.getContext('2d');
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        // ★ clear SignaturePad instance ด้วย
                        if (typeof signaturePads !== 'undefined' && signaturePads[canvasId]) {
                            signaturePads[canvasId].clear();
                        }
                        if (typeof sketchSetEraser === 'function') sketchSetEraser(canvasId, false);
                                }
                            });
                            return;
                    } else {
                        if (!confirm('คุณต้องการล้างภาพทั้งหมดหรือไม่?')) {
                            return;
                        }
                    }
        }

    };

    // ===== Auto-fill position for PDF form receiver/sender =====
    document.addEventListener('change', function(e) {
        var target = e.target;
        if (target.id !== 'ppf_receiver_name' && target.id !== 'ppf_sender_name') return;
        var idEmp = target.value;
        var posInputId = (target.id === 'ppf_receiver_name') ? 'ppf_receiver_position' : 'ppf_sender_position';
        var posInput = document.getElementById(posInputId);
        if (!posInput) return;
        if (!idEmp) { posInput.value = ''; return; }
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/csims/api/ReceiveNoti/getPos.php?idEmp=' + encodeURIComponent(idEmp), true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    posInput.value = (resp.message === 'success' && resp.data) ? (resp.data.position_name || '') : '';
                } catch(ex) { posInput.value = ''; }
            }
        };
        xhr.send();
    });

    // ===== PDF Photo: Drag & Drop + File input =====
    var ppfPhotoDZ = document.getElementById('ppf_photo_dropzone');
    if (ppfPhotoDZ) {
        ppfPhotoDZ.addEventListener('click', function() {
            document.getElementById('ppf_photo_input_gallery').click();
        });
        ppfPhotoDZ.addEventListener('dragenter', function(e) { e.preventDefault(); this.classList.add('dragover'); });
        ppfPhotoDZ.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('dragover'); });
        ppfPhotoDZ.addEventListener('dragleave', function(e) { e.preventDefault(); this.classList.remove('dragover'); });
        ppfPhotoDZ.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                ppfHandlePhotoFiles(e.dataTransfer.files);
            }
        });
    }
    var ppfGalleryInput = document.getElementById('ppf_photo_input_gallery');
    var ppfCameraInput = document.getElementById('ppf_photo_input_camera');
    if (ppfGalleryInput) ppfGalleryInput.addEventListener('change', function() { ppfHandlePhotoFiles(this.files); this.value = ''; });
    if (ppfCameraInput) ppfCameraInput.addEventListener('change', function() { ppfHandlePhotoFiles(this.files); this.value = ''; });

    function ppfHandlePhotoFiles(files) {
        if (!files || !files.length) return;
        Array.from(files).forEach(function(file) {
            if (!file.type.startsWith('image/')) return;
            var fileId = Date.now() + '_' + Math.random().toString(16).slice(2);
            var objectUrl = URL.createObjectURL(file);
            attachmentStore.push({ file: file, id: fileId, src: objectUrl, name: file.name });
        });
        ppfRenderPhotosFromStore();
        if (typeof updatePropertyRealInput === 'function') updatePropertyRealInput();
        if (typeof renderPropertyAttachmentGrid === 'function') renderPropertyAttachmentGrid();
    }

    // ===== Render photos จาก attachmentStore ลง 7×5 grid (35 รูป/หน้า) =====
    var PPF_PHOTOS_PER_PAGE = 35; // 7 columns × 5 rows

    window.ppfRenderPhotosFromStore = function() {
        var photos = (typeof attachmentStore !== 'undefined') ? attachmentStore : [];
        var pagesNeeded = Math.max(1, Math.ceil(photos.length / PPF_PHOTOS_PER_PAGE));
        console.log('[PPF] ppfRenderPhotosFromStore called, photos:', photos.length, 'pagesNeeded:', pagesNeeded, 'grid1:', !!document.getElementById('ppf_photo_grid_1'));

        // หน้าแรก (page 1) → grid อยู่ใน #ppf_photo_grid_1
        var grid1 = document.getElementById('ppf_photo_grid_1');
        if (grid1) {
            var startIdx = 0;
            var endIdx = Math.min(PPF_PHOTOS_PER_PAGE, photos.length);
            grid1.innerHTML = '';
            for (var i = startIdx; i < endIdx; i++) {
                grid1.appendChild(ppfCreatePhotoCell(photos[i], i));
            }
        }

        // สร้าง/ลบ extra pages ตามจำนวนรูป
        var extraContainer = document.getElementById('ppf_extra_photo_pages');
        if (extraContainer) {
            extraContainer.innerHTML = '';
            for (var p = 2; p <= pagesNeeded; p++) {
                var startI = (p - 1) * PPF_PHOTOS_PER_PAGE;
                var endI = Math.min(p * PPF_PHOTOS_PER_PAGE, photos.length);
                extraContainer.appendChild(ppfCreatePhotoPageElement(p, photos, startI, endI));
            }
        }

        ppfUpdatePhotoAmount();
        if (typeof ppfUpdatePageNumbers === 'function') ppfUpdatePageNumbers();
    };

    function ppfCreatePhotoCell(item, idx) {
        var wrapper = document.createElement('div');
        wrapper.className = 'ppf-photo-cell-wrapper';
        var safeName = (item.name || 'photo').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
        wrapper.innerHTML =
            '<div class="ppf-photo-cell" style="position:relative;">' +
                '<img src="' + item.src + '" alt="' + safeName + '" loading="lazy">' +
                '<button type="button" class="ppf-cell-delete" onclick="event.stopPropagation(); ppfRemovePhoto(\'' + item.id + '\')">&times;</button>' +
            '</div>' +
            '<div class="ppf-photo-fname" title="' + safeName + '">' + (item.name || 'photo') + '</div>';
        return wrapper;
    }

    function ppfCreatePhotoPageElement(pageNum, photos, startIdx, endIdx) {
        var docNo = document.getElementById('ppf_doc_no') ? document.getElementById('ppf_doc_no').value : '';
        var rptNoFull = document.getElementById('ppf_report_no') ? document.getElementById('ppf_report_no').value : '';
        var rptParts = (rptNoFull || '').split('/');
        var rptNo = rptParts[0] || docNo;
        var rptYear = (rptParts[1] || '').toString().slice(-2);

        var page = document.createElement('div');
        page.className = 'ppf-page ppf-photo-page';
        page.setAttribute('data-photo-page', pageNum);
        page.innerHTML =
            '<div class="ppf-header">' +
                '<div class="ppf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>' +
                '<div class="ppf-header-center">' +
                    '<div class="ppf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>' +
                    '<div class="ppf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์</div>' +
                    '<div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ (ต่อ)</div>' +
                '</div>' +
                '<div class="ppf-header-right">' +
                    '<div class="ppf-doc-box">' +
                        '<div class="ppf-doc-line">รายงานที่ <span class="ppf-rpt-no-mirror">' + rptNo + '</span> / 25<span class="ppf-rpt-year-mirror">' + rptYear + '</span></div>' +
                        '<div class="ppf-doc-line">หน้าที่ <span class="ppf-cur-page"></span> / <span class="ppf-total-page"></span></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div style="margin-bottom:8px;">' +
                '<div class="ppf-fr" style="flex-wrap:nowrap; margin-bottom:6px;">' +
                    '<span class="ppf-fl">รหัสภาพถ่ายที่</span>' +
                    '<input type="text" class="ppf-inp ppf-page-photo-start" style="min-width:40px;" readonly>' +
                    '<span class="ppf-fl">ถึง</span>' +
                    '<input type="text" class="ppf-inp ppf-page-photo-end" style="min-width:40px;" readonly>' +
                '</div>' +
                '<div style="font-size:10.5px; font-style:italic; margin-top:2px;">(ตามภาพถ่ายรวมที่แนบ)</div>' +
            '</div>' +
            '<div class="ppf-photo-grid" id="ppf_photo_grid_' + pageNum + '"></div>' +
            '<div class="ppf-footer">' +
                '<div class="ppf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>' +
                '<div class="ppf-footer-right">F-CS-05 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>' +
            '</div>';

        // Populate grid
        var grid = page.querySelector('.ppf-photo-grid');
        for (var i = startIdx; i < endIdx; i++) {
            grid.appendChild(ppfCreatePhotoCell(photos[i], i));
        }
        return page;
    }

    // ===== ปุ่มเพิ่มหน้า (manual) — ยังใช้ได้เพื่อเพิ่มหน้าว่าง =====
    window.ppfAddPhotoPage = function() {
        ppfRenderPhotosFromStore();
    };

    window.ppfUpdatePhotoAmount = function() {
        var photos = (typeof attachmentStore !== 'undefined' && Array.isArray(attachmentStore)) ? attachmentStore : [];
        var totalPhotos = photos.length;

        // หน้าแรก: ชื่อไฟล์แรก → ชื่อไฟล์สุดท้ายของหน้า + จำนวนรวม
        var form = document.getElementById('propertyFormPdf');
        if (form) {
            var startInput = form.querySelector('input[name="photo_id_start"]');
            var endInput = form.querySelector('input[name="photo_id_end"]');
            var amountInput = form.querySelector('input[name="photo_amount"]');
            if (startInput && endInput && amountInput) {
                if (totalPhotos > 0) {
                    var lastIdxPage1 = Math.min(PPF_PHOTOS_PER_PAGE, totalPhotos) - 1;
                    startInput.value = photos[0].name || 'photo';
                    endInput.value = photos[lastIdxPage1].name || 'photo';
                    amountInput.value = String(totalPhotos);
                } else {
                    startInput.value = '';
                    endInput.value = '';
                    amountInput.value = '';
                }
            }
        }

        // หน้าต่อๆ ไป: อัปเดต photo_id_start / photo_id_end ของแต่ละหน้า
        var extraPages = document.querySelectorAll('#ppf_extra_photo_pages .ppf-photo-page');
        extraPages.forEach(function(page, pIdx) {
            var pageNum = pIdx + 2;
            var startI = (pageNum - 1) * PPF_PHOTOS_PER_PAGE;
            var endI = Math.min(pageNum * PPF_PHOTOS_PER_PAGE, totalPhotos) - 1;
            var ps = page.querySelector('.ppf-page-photo-start');
            var pe = page.querySelector('.ppf-page-photo-end');
            if (ps && photos[startI]) ps.value = photos[startI].name || 'photo';
            if (pe && photos[endI]) pe.value = photos[endI].name || 'photo';
        });
    };

    // ===== บันทึก caption =====
    window.ppfUpdateCaption = function(idx, val) {
        if (idx < attachmentStore.length) {
            attachmentStore[idx].caption = val;
        }
    };

    // ===== ลบรูปจาก store (★ track BLOB file_id สำหรับลบบน server ด้วย) =====
    window.ppfRemovePhoto = function(fileId) {
        var item = attachmentStore.find(function(x) { return x.id === fileId; });
        if (item && item.existing && item.db_file_id) {
            // BLOB photo → track file_id สำหรับลบจาก DB
            if (typeof deletedExistingPhotos !== 'undefined') {
                deletedExistingPhotos.push({ file_id: item.db_file_id });
            }
        } else if (item && item.existing && !item.db_file_id && item.disk_filename) {
            // Disk photo → track filename สำหรับลบจากดิสก์
            if (typeof deletedExistingPhotos !== 'undefined') {
                deletedExistingPhotos.push(item.disk_filename);
            }
        }
        attachmentStore = attachmentStore.filter(function(x) { return x.id !== fileId; });
        ppfRenderPhotosFromStore();
        if (typeof renderPropertyAttachmentGrid === 'function') renderPropertyAttachmentGrid();
        if (typeof updatePropertyRealInput === 'function') updatePropertyRealInput();
    };

    // ===== Multi-page Sketch Engine =====
    var SKETCH_W = 1120, SKETCH_H = 660;
    var _sketchPageCounter = 1;
    window._ppfSketchPages = window._ppfSketchPages || [];

    // Register first page
    window._ppfSketchPages.push({
        id: 'ppf_sketch_page_1',
        canvasId: 'ppf_sketch_page_1_canvas',
        bgImage: null,
        bgImageData: null,
        _bgImgEl: null
    });

    function _initSketchDraw(canvasId) {
        if (typeof window.initFreehandCanvas === 'function') {
            window.initFreehandCanvas(canvasId);
        }
    }

    window.ppfSketchAddPage = function() {
        _sketchPageCounter++;
        var n = _sketchPageCounter;
        var pid = 'ppf_sketch_page_' + n;
        var canvasId = pid + '_canvas';
        var pageData = { id: pid, canvasId: canvasId, bgImage: null, bgImageData: null, _bgImgEl: null };
        var pageDiv = document.createElement('div');
        pageDiv.className = 'ppf-page ppf-sketch-page';
        pageDiv.id = pid;
        pageDiv.setAttribute('data-sketch-page', n);
        pageDiv.innerHTML =
            '<div class="ppf-header">' +
                '<div class="ppf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>' +
                '<div class="ppf-header-center">' +
                    '<div class="ppf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>' +
                    '<div class="ppf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับทรัพย์</div>' +
                '</div>' +
                '<div class="ppf-header-right">' +
                    '<div class="ppf-doc-box">' +
                        '<div class="ppf-doc-line">รายงานที่ <span class="ppf-rpt-no-mirror"></span> / 25<span class="ppf-rpt-year-mirror"></span></div>' +
                        '<div class="ppf-doc-line">หน้าที่ <span class="ppf-cur-page"></span> / <span class="ppf-total-page"></span></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +

            '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">' +
                '<div></div>' +
                '<div style="font-size:13px; font-weight:600;">แผนผังสังเขป (ต่อ)</div>' +
                '<div style="display:flex; gap:4px; align-items:center;">' +
                    '<label class="ppf-add-btn" style="padding:2px 8px; cursor:pointer; margin:0;" title="แนบรูปภาพพื้นหลัง">' +
                        '<i class="fas fa-image me-1"></i> แนบรูป' +
                        '<input type="file" accept="image/*" style="display:none;" onchange="ppfSketchAttachImage(\'' + pid + '\',this)">' +
                    '</label>' +
                    '<button type="button" class="ppf-add-btn" style="padding:2px 8px;" onclick="ppfSketchRemoveBg(\'' + pid + '\')" title="ลบรูปพื้นหลัง"><i class="fas fa-times"></i> ลบรูป</button>' +
                    '<button type="button" class="ppf-add-btn" style="padding:2px 8px; border-color:#dc3545; color:#dc3545;" onclick="ppfSketchRemovePage(\'' + pid + '\')" title="ลบหน้านี้"><i class="fas fa-trash-alt"></i> ลบหน้า</button>' +
                '</div>' +
            '</div>' +

            '<div class="ppf-sketch-viewport" id="' + pid + '_viewport">' +
                '<div class="ppf-sketch-canvas-wrap" id="' + pid + '_wrap" style="aspect-ratio:' + SKETCH_W + '/' + SKETCH_H + ';">' +
                    '<canvas id="' + canvasId + '" width="' + SKETCH_W + '" height="' + SKETCH_H + '"></canvas>' +
                '</div>' +
                '<div class="ppf-not-to-scale">* NOT TO SCALE</div>' +
            '</div>' +

            '<div style="text-align:right; margin-top:4px; display:flex; justify-content:flex-end; gap:6px; align-items:center;">' +
                '<button type="button" class="ppf-add-btn" onclick="sketchUndo(\'' + canvasId + '\')" style="padding:2px 8px;"><i class="fas fa-undo"></i> ย้อนกลับ</button>' +
                '<button type="button" class="ppf-add-btn btn-sketch-eraser" id="' + canvasId + '_eraser_btn" onclick="sketchToggleEraser(\'' + canvasId + '\')" style="padding:2px 8px;" title="ยางลบ"><i class="fas fa-eraser"></i></button>' +
                '<div style="display:flex;align-items:center;gap:4px;">' +
                    '<i class="fas fa-pen" style="font-size:10px;color:#666;"></i>' +
                    '<input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize(\'' + canvasId + '\',this.value);this.nextElementSibling.textContent=this.value+\'px\'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">' +
                    '<span style="font-size:11px;color:#666;min-width:35px;">2px</span>' +
                '</div>' +
                '<label style="margin:0;cursor:pointer;" title="เลือกสี"><input type="color" value="#000000" onchange="sketchSetColor(\'' + canvasId + '\',this.value)" style="width:26px;height:22px;border:1px solid #aaa;border-radius:3px;padding:0;cursor:pointer;"></label>' +
                '<button type="button" class="ppf-add-btn" onclick="ppfClearCanvas(\'' + canvasId + '\')">ล้างกระดาน</button>' +
            '</div>' +

            '<div style="margin-bottom:8px;">' +
                '<div class="ppf-fr">' +
                    '<span class="ppf-fl">หมายเหตุ</span><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>' +
                '</div>' +
                '<textarea class="ppf-ta" rows="5"></textarea>' +
            '</div>' +

            '<div class="ppf-footer">' +
                '<div class="ppf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>' +
                '<div class="ppf-footer-right">F-CS-05 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>' +
            '</div>';

        var allSketchPages = document.querySelectorAll('#propertyFormPdfModal .ppf-sketch-page');
        var lastSketchPage = allSketchPages[allSketchPages.length - 1];
        if (lastSketchPage && lastSketchPage.nextElementSibling) {
            lastSketchPage.parentNode.insertBefore(pageDiv, lastSketchPage.nextElementSibling);
        } else {
            var body = document.querySelector('#propertyFormPdfModal .ppf-body');
            if (body) body.appendChild(pageDiv);
        }
        window._ppfSketchPages.push(pageData);
        _initSketchDraw(canvasId);
        if (typeof ppfUpdatePageNumbers === 'function') ppfUpdatePageNumbers();
        pageDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.ppfSketchRemovePage = function(pid) {
        if (pid === 'ppf_sketch_page_1') return;
        var idx = window._ppfSketchPages.findIndex(function(p) { return p.id === pid; });
        if (idx !== -1) window._ppfSketchPages.splice(idx, 1);
        var pageDiv = document.getElementById(pid);
        if (pageDiv) pageDiv.remove();
        if (typeof ppfUpdatePageNumbers === 'function') ppfUpdatePageNumbers();
    };

    window.ppfSketchAttachImage = function(pid, input) {
        var file = input.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function(ev) {
            var pageData = window._ppfSketchPages.find(function(p) { return p.id === pid; });
            if (!pageData) return;
            pageData.bgImageData = ev.target.result;
            var img = new Image();
            img.onload = function() {
                pageData.bgImage = img;
                pageData._bgImgEl = img;
                var wrap = document.getElementById(pid + '_wrap');
                if (wrap) {
                    var existing = wrap.querySelector('.ppf-sketch-bg-img');
                    if (existing) existing.remove();
                    var bgImg = document.createElement('img');
                    bgImg.className = 'ppf-sketch-bg-img';
                    bgImg.src = ev.target.result;
                    wrap.insertBefore(bgImg, wrap.firstChild);
                }
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
        input.value = '';
    };

    window.ppfSketchRemoveBg = function(pid) {
        var pageData = window._ppfSketchPages.find(function(p) { return p.id === pid; });
        if (!pageData) return;
        pageData.bgImage = null;
        pageData.bgImageData = null;
        pageData._bgImgEl = null;
        var wrap = document.getElementById(pid + '_wrap');
        if (wrap) {
            var existing = wrap.querySelector('.ppf-sketch-bg-img');
            if (existing) existing.remove();
        }
    };

    window.ppfCollectSketchPagesData = function() {
        var pagesData = [];
        window._ppfSketchPages.forEach(function(p) {
            var dataUrl = (typeof window.exportSketchDataUrl === 'function')
                ? window.exportSketchDataUrl(p.canvasId, p.bgImage || p._bgImgEl || null)
                : '';
            if (!dataUrl) {
                var canvas = document.getElementById(p.canvasId);
                if (!canvas) return;
                var tmpCanvas = document.createElement('canvas');
                tmpCanvas.width = canvas.width;
                tmpCanvas.height = canvas.height;
                var tmpCtx = tmpCanvas.getContext('2d');
                tmpCtx.fillStyle = '#fff';
                tmpCtx.fillRect(0, 0, tmpCanvas.width, tmpCanvas.height);
                if (p.bgImage) tmpCtx.drawImage(p.bgImage, 0, 0, tmpCanvas.width, tmpCanvas.height);
                tmpCtx.drawImage(canvas, 0, 0);
                dataUrl = tmpCanvas.toDataURL('image/png');
            }
            pagesData.push({
                pageId: p.id,
                dataUrl: dataUrl,
                bgImageName: p.bgImage || null
            });
        });
        var inp = document.getElementById('ppf_scene_sketch_pages_data');
        if (inp) inp.value = JSON.stringify(pagesData);
        var legacyInp = document.getElementById('ppf_scene_sketch_data');
        if (legacyInp && pagesData.length > 0) legacyInp.value = pagesData[0].dataUrl || '';
        var stdInp = document.getElementById('scene_sketch_data');
        if (stdInp && pagesData.length > 0) stdInp.value = pagesData[0].dataUrl || '';
        return pagesData;
    };

    // Init first page on modal shown
    var ppfModal = document.getElementById('propertyFormPdfModal');
    if (ppfModal) {
        ppfModal.addEventListener('shown.bs.modal', function() {
            _initSketchDraw('ppf_sketch_page_1_canvas');
        });
    }

    // ===== บันทึก: sync กลับไปฟอร์มมาตรฐานแล้ว submit =====
    window.ppfSaveViaStandardForm = async function() {
        // ★ Collect multi-page sketch data first
        if (typeof ppfCollectSketchPagesData === 'function') {
            ppfCollectSketchPagesData();
        }

        // ★ ดึง base64 จาก canvas ของ PDF form ใส่ hidden input ก่อน sync
        var pdfCanvasMap = {
            'ppf_sig_receiver': 'ppf_receiver_sig_data',
            'ppf_sig_sender': 'ppf_sender_sig_data'
        };
        Object.keys(pdfCanvasMap).forEach(function(canvasId) {
            var canvas = document.getElementById(canvasId);
            var hiddenInput = document.getElementById(pdfCanvasMap[canvasId]);
            if (canvas && hiddenInput) {
                try {
                    // ตรวจสอบว่า canvas มีข้อมูลจริง (ไม่ใช่ว่างเปล่า)
                    var ctx = canvas.getContext('2d');
                    var pixelData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
                    var hasContent = false;
                    for (var i = 3; i < pixelData.length; i += 4) {
                        if (pixelData[i] > 0) { hasContent = true; break; }
                    }
                    if (hasContent) {
                        hiddenInput.value = canvas.toDataURL('image/png');
                    }
                } catch(e) { /* ignore cross-origin or empty canvas */ }
            }
        });

        // sync ข้อมูลจาก PDF → ฟอร์มมาตรฐาน (รวม hidden inputs ที่เพิ่งเซ็ตไว้)
        if (typeof syncPropertyFormData === 'function') {
            syncPropertyFormData('propertyFormPdf', 'incidentCheckListForm');
        }

        // ★ sync inspector rows จาก PDF → ฟอร์มมาตรฐาน (กันกรณี row ไม่ตรงกัน)
        if (typeof _syncPropertyInspectorToStd === 'function') {
            _syncPropertyInspectorToStd();
        }
        // ★ sync trace points จาก PDF → ฟอร์มมาตรฐาน
        if (typeof _syncPropertyTracePointsToStd === 'function') {
            _syncPropertyTracePointsToStd();
        }
        // ★ sync evidence rows จาก PDF → ฟอร์มมาตรฐาน (หน้า 4 & 5)
        if (typeof _syncPropertyEvidenceToStd === 'function') {
            _syncPropertyEvidenceToStd();
        }
        // ★ sync measurement rows จาก PDF → ฟอร์มมาตรฐาน (บันทึกการตรวจเก็บวัตถุพยาน)
        if (typeof _syncPropertyMeasurementPdfToStd === 'function') {
            _syncPropertyMeasurementPdfToStd();
        }
        // sync ค่า inspector_id[] อีกครั้งหลังเพิ่ม row แล้ว
        if (typeof syncPropertyFormData === 'function') {
            syncPropertyFormData('propertyFormPdf', 'incidentCheckListForm');
        }

        // sync hidden fields
        var docNo = document.getElementById('ppf_doc_no') ? document.getElementById('ppf_doc_no').value : '';
        var rptNo = document.getElementById('ppf_report_no') ? document.getElementById('ppf_report_no').value : '';
        var notiId = document.getElementById('ppf_receiveNoti_id') ? document.getElementById('ppf_receiveNoti_id').value : '';
        if (document.getElementById('doc_no')) document.getElementById('doc_no').value = docNo;
        if (document.getElementById('report_no')) document.getElementById('report_no').value = rptNo;
        if (document.getElementById('receiveNoti_id')) document.getElementById('receiveNoti_id').value = notiId;

        // เรียกฟังก์ชัน submit ของฟอร์มมาตรฐาน
        if (typeof prepareDataForSubmission === 'function') {
            window._savingFromPdfForm = true; // ★ flag ให้ prepareDataForSubmission เช็ค PDF canvas ก่อน
            await prepareDataForSubmission();
        }
    };

    // ===== Download Checklist PDF =====
    window.ppfDownloadChecklistPdf = function() {
        var incidentId = document.getElementById('ppf_incident_id')?.value || 
                         document.getElementById('incident_id')?.value || '';
        if (!incidentId) {
            Swal.fire({ icon: 'warning', title: 'ไม่พบ incident_id', text: 'กรุณาบันทึกข้อมูลก่อนดาวน์โหลด' });
            return;
        }
        window.open('/csims/api/incidentCheckList/gen_pdf_property_html.php?incident_id=' + incidentId, '_blank');
    };

})();
</script>
