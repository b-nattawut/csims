<?php
/**
 * Modal: ฟอร์ม PDF คดีเพลิงไหม้ (แบบกรอกข้อมูล)
 * หน้าตาเหมือนฟอร์ม PDF เป๊ะ แต่กรอกข้อมูลได้
 * Prefix ID: fpf_
 */
// ดึง police station options

$fpfPoliceStationOptions = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qryFpfPS = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stmtFpfPS = $pdo->query($qryFpfPS);
    while ($rowPS = $stmtFpfPS->fetch(PDO::FETCH_ASSOC)) {
        $fpfPoliceStationOptions .= '<option value="' . htmlspecialchars($rowPS['station_name']) . '">' . htmlspecialchars($rowPS['station_name']) . '</option>';
    }
}

$fpfTodayDate = date('Y-m-d');
$fpfTodayTime = date('H:i');
?>

<!-- ===== SCOPED CSS ===== -->
<style>
/* hwpen button */
#fireFormPdfModal .btn-hw-open {
    flex-shrink: 0; min-width: 18px; padding: 0 4px;
    border: none; background: none; color: #6366f1;
    font-size: 0.7rem; cursor: pointer; line-height: 1.5;
}
#fireFormPdfModal .btn-hw-open:hover { color: #4338ca; transform: scale(1.15); }

#fireFormPdfModal .btn-purple {
    background-color: #8b5cf6;
    border-color: #8b5cf6;
    color: #fff;
}
#fireFormPdfModal .btn-purple:hover {
    background-color: #7c3aed;
    border-color: #7c3aed;
    color: #fff;
}

#fireFormPdfModal .fpf-rpt-no-mirror,
#fireFormPdfModal .fpf-rpt-year-mirror {
    display: inline-block;
    border-bottom: 1px dotted #888;
    text-align: center;
}
#fireFormPdfModal .fpf-rpt-no-mirror { min-width: 60px; }
#fireFormPdfModal .fpf-rpt-year-mirror { min-width: 30px; }

#fireFormPdfModal .fpf-body {
    background: #bbb;
    padding: 10px 0;
}

#fireFormPdfModal .fpf-page {
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
#fireFormPdfModal .fpf-header {
    position: relative;
    margin-bottom: 6px;
    height: 70px;
}
#fireFormPdfModal .fpf-header-logo {
    position: absolute; left: 0; top: -5px; width: 70px; height: 70px;
}
#fireFormPdfModal .fpf-header-logo img {
    width: 70px; height: 70px; object-fit: contain;
}
#fireFormPdfModal .fpf-header-center {
    position: absolute; left: 80px; right: 180px; top: 8px; text-align: center;
}
#fireFormPdfModal .fpf-header-center .fpf-title-main {
    font-size: 14px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 5px;
}
#fireFormPdfModal .fpf-header-center .fpf-title-sub {
    font-size: 11.5px; font-weight: 600; margin-top: 3px;
}
#fireFormPdfModal .fpf-header-right {
    position: absolute; right: 0; top: 7px;
}
#fireFormPdfModal .fpf-doc-box {
    border: 1.5px solid #000; padding: 3px 8px; font-size: 11px; white-space: nowrap;
}
#fireFormPdfModal .fpf-doc-box .fpf-doc-line { line-height: 1.5; }

/* ===== BODY TABLE ===== */
#fireFormPdfModal .fpf-form-body {
    display: flex; border: 1.5px solid #000; align-items: stretch;
}
#fireFormPdfModal .fpf-col-left {
    width: 50%; border-right: 1.5px solid #000; display: flex; flex-direction: column;
}
#fireFormPdfModal .fpf-col-right {
    width: 50%; display: flex; flex-direction: column;
}

/* ===== ROW HEADER ===== */
#fireFormPdfModal .fpf-row-header {
    display: flex; border-bottom: 1px solid #000;
    font-weight: 700; font-size: 11px; text-align: center; background: transparent;
}
#fireFormPdfModal .fpf-row-header .fpf-lbl-seq {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000; padding: 1px 2px;
}
#fireFormPdfModal .fpf-row-header .fpf-lbl-data {
    flex: 1; padding: 1px 2px;
}

/* ===== SECTION ROW ===== */
#fireFormPdfModal .fpf-sec-row {
    display: flex; border-bottom: 1px solid #000;
}
#fireFormPdfModal .fpf-sec-row:last-child { border-bottom: none; }
#fireFormPdfModal .fpf-sec-label {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000;
    padding: 3px 3px; font-weight: 700; font-size: 11px; text-align: center;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
#fireFormPdfModal .fpf-sec-label .fpf-sec-num {
    font-size: 13px; font-weight: 700; line-height: 1.2;
}
#fireFormPdfModal .fpf-sec-label .fpf-sec-txt {
    font-size: 10px; font-weight: 600; line-height: 1.15; text-align: center; margin-top: 1px;
}
#fireFormPdfModal .fpf-sec-body {
    flex: 1; padding: 5px 6px; font-size: 11.5px;
}

/* ===== FIELD ROW ===== */
#fireFormPdfModal .fpf-fr {
    display: flex; flex-wrap: wrap; align-items: center; margin-bottom: 6px; line-height: 1.8;
}
#fireFormPdfModal .fpf-fl {
    font-size: 11.5px; white-space: nowrap; margin-right: 4px;
}
#fireFormPdfModal .fpf-fl-b {
    font-size: 11.5px; font-weight: 600; white-space: nowrap; margin-right: 4px;
}

/* ===== INPUT FIELDS (replace .fd / .fd-m / .fd-full / .fd-s) ===== */
#fireFormPdfModal .fpf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px;
}
#fireFormPdfModal .fpf-inp-m {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 60px; margin: 0 2px; text-align: center;
}
#fireFormPdfModal .fpf-inp-full {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    width: 100%; display: block; margin-bottom: 3px;
}
#fireFormPdfModal .fpf-inp-s {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    min-width: 15px; max-width: 50px; margin: 0 2px; flex: 0 1 40px; text-align: center;
}

/* SELECT styled like dotted line */
#fireFormPdfModal .fpf-sel {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px; cursor: pointer;
}

/* TEXTAREA styled like dotted lines */
#fireFormPdfModal .fpf-ta {
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
#fireFormPdfModal .fpf-cb {
    appearance: none; -webkit-appearance: none;
    width: 13px; height: 13px; border: 1.5px solid #000;
    margin-right: 3px; cursor: pointer; position: relative;
    vertical-align: middle; flex-shrink: 0; background: #fff;
}
#fireFormPdfModal .fpf-cb:checked::after {
    content: '✓'; font-size: 12px; font-weight: 700;
    position: absolute; top: -3px; left: 0px; color: #000;
}

/* ===== Checkbox label ===== */
#fireFormPdfModal .fpf-ck {
    display: inline-flex; align-items: center; margin-right: 14px;
    font-size: 11.5px; white-space: nowrap; vertical-align: middle; cursor: pointer;
}

/* Filled square bullet */
#fireFormPdfModal .fpf-bk {
    width: 10px; height: 10px; background: #000;
    display: inline-block; margin-right: 3px; flex-shrink: 0;
    position: relative; top: 1px;
}

/* ===== Bullet header ===== */
#fireFormPdfModal .fpf-bh {
    display: flex; align-items: center; font-weight: 600;
    font-size: 11.5px; margin-top: 8px; margin-bottom: 5px;
}

/* ===== Sub-items ===== */
#fireFormPdfModal .fpf-si {
    display: flex; align-items: center; font-size: 11.5px; line-height: 1.8; margin-bottom: 5px;
}
#fireFormPdfModal .fpf-si-no {
    min-width: 25px; padding-left: 8px; font-size: 11.5px;
}

/* ===== Checkbox group ===== */
#fireFormPdfModal .fpf-cg {
    display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; margin-bottom: 5px;
}

/* ===== Indents ===== */
#fireFormPdfModal .fpf-i1 { padding-left: 15px; }
#fireFormPdfModal .fpf-i2 { padding-left: 28px; }

/* ===== FOOTER ===== */
#fireFormPdfModal .fpf-footer {
    margin-top: auto; font-size: 9.5px; color: #333;
    display: flex; justify-content: space-between; align-items: flex-end; flex-shrink: 0;
}
#fireFormPdfModal .fpf-footer-left { flex: 1; }
#fireFormPdfModal .fpf-footer-right {
    text-align: right; white-space: nowrap; line-height: 1.3;
}

/* ===== Add/Remove buttons inside form ===== */
#fireFormPdfModal .fpf-add-btn {
    font-size: 10px; padding: 1px 8px; border: 1px dashed #888;
    background: #f8f8f8; cursor: pointer; color: #333; margin: 3px 0;
    font-family: 'Sarabun', sans-serif;
}
#fireFormPdfModal .fpf-add-btn:hover { background: #e0e0e0; }
#fireFormPdfModal .fpf-del-btn {
    font-size: 9px; padding: 0 4px; border: 1px solid #ccc;
    background: #fff; cursor: pointer; color: #c00;
    font-family: 'Sarabun', sans-serif; line-height: 1.5;
}
#fireFormPdfModal .fpf-del-btn:hover { background: #fee; }

/* ===== Table inputs ===== */
#fireFormPdfModal .fpf-ev-table td {
    border: 1px solid #000; padding: 2px; text-align: center; vertical-align: middle;
}
#fireFormPdfModal .fpf-ev-table input[type="text"] {
    border: none; border-bottom: 1px dotted #888; background: transparent; font-size: 10px; width: 100%;
    padding: 1px 2px; outline: none; font-family: 'Sarabun', sans-serif; text-align: center;
}
#fireFormPdfModal .fpf-ev-table input[type="checkbox"] {
    width: 10px; height: 10px; cursor: pointer;
}

/* ===== Signature box ===== */
#fireFormPdfModal .fpf-sig-box {
    border: 1px solid #ccc; background: #fafafa; min-height: 60px;
    cursor: crosshair; position: relative;
}
#fireFormPdfModal .fpf-sig-box canvas {
    width: 100%; height: 100%; display: block;
}

/* ===== Sketch area (legacy single) ===== */
#fireFormPdfModal .fpf-sketch-area {
    border: 1.5px solid #000; min-height: 500px; position: relative;
    display: flex; align-items: center; justify-content: center; cursor: crosshair;
}

/* ===== Multi-page Sketch System ===== */
#fireFormPdfModal .fpf-sketch-viewport {
    border: 1.5px solid #000; position: relative;
    background: #fff; cursor: crosshair;
}
#fireFormPdfModal .fpf-sketch-canvas-wrap {
    position: relative; width: 100%;
}
#fireFormPdfModal .fpf-sketch-canvas-wrap canvas {
    display: block; width: 100%; height: 100%; position: absolute; top: 0; left: 0; z-index: 2;
}
#fireFormPdfModal .fpf-sketch-canvas-wrap .fpf-sketch-bg-img {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1;
    object-fit: contain; pointer-events: none; user-select: none;
}
#fireFormPdfModal .fpf-not-to-scale {
    position: absolute; bottom: 6px; right: 8px; font-size: 10px; color: #555; z-index: 3;
    pointer-events: none;
}

/* ===== Photo Grid 5 คอลัมน์ × 7 แถว (35 รูป/หน้า) ===== */
#fireFormPdfModal .fpf-photo-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 6px 4px;
    align-content: start;
}
#fireFormPdfModal .fpf-photo-cell {
    position: relative;
    border: 1.5px solid #333;
    overflow: hidden;
    background: #fff;
}
#fireFormPdfModal .fpf-photo-cell img {
    width: 100%;
    aspect-ratio: 4/3;
    object-fit: cover;
    display: block;
}
#fireFormPdfModal .fpf-cell-delete {
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
#fireFormPdfModal .fpf-cell-filename {
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
#fireFormPdfModal .fpf-photo-dropzone {
    border: 2px dashed #b0bec5;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    background: #f8f9fa;
    margin-bottom: 8px;
    transition: all 0.2s;
}
#fireFormPdfModal .fpf-photo-dropzone:hover,
#fireFormPdfModal .fpf-photo-dropzone.dragover {
    border-color: #2196F3;
    background: #e3f2fd;
}
#fireFormPdfModal .fpf-add-photo-page-btn {
    display: block; margin: 8px auto; padding: 4px 16px;
    border: 1px dashed #888; background: #f0f0f0; cursor: pointer;
    font-size: 11px; font-family: 'Sarabun', sans-serif; color: #333;
}
#fireFormPdfModal .fpf-add-photo-page-btn:hover { background: #e0e0e0; }

@media print {
    /* ===== ซ่อนทุกอย่างนอกจากฟอร์ม ===== */
    body > *:not(#fireFormPdfModal),
    .navbar, .sidebar, .main-content, #status-banner,
    .modal-backdrop, .swal2-container,
    #fireFormPdfModal .modal-header,
    #fireFormPdfModal .modal-footer,
    #fireFormPdfModal .csims-loading-overlay,
    #fireFormPdfModal .fpf-add-btn,
    #fireFormPdfModal .fpf-del-btn,
    #fireFormPdfModal .fpf-add-photo-page-btn,
    #fireFormPdfModal .btn-hw-open,
    #fireFormPdfModal .btn-sketch-eraser,
    #fireFormPdfModal input[type="color"],
    #fireFormPdfModal [id="editInfoFirePdf"],
    #fireFormPdfModal .form-check.form-switch,
    #fireFormPdfModal .d-flex.align-items-center.gap-3 {
        display: none !important;
    }

    /* ===== Reset modal เป็นแบบ static ===== */
    #fireFormPdfModal,
    #fireFormPdfModal .modal-dialog,
    #fireFormPdfModal .modal-content,
    #fireFormPdfModal .fpf-body {
        position: static !important;
        display: block !important;
        width: auto !important;
        max-width: none !important;
        max-height: none !important;
        height: auto !important;
        overflow: visible !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        border: none !important;
        box-shadow: none !important;
        transform: none !important;
        opacity: 1 !important;
    }

    /* ===== Page: ขยายตามเนื้อหา + @page margin ป้องกันถูกตัดขอบ ===== */
    @page {
        size: A4 portrait;
        margin: 5mm 0;
    }
    #fireFormPdfModal .fpf-page {
        width: 100% !important;
        min-height: auto !important;
        height: auto !important;
        margin: 0 !important;
        padding: 5mm 10mm 3mm 10mm !important;
        box-shadow: none !important;
        overflow: visible !important;
        page-break-after: always;
        page-break-inside: auto;
    }
    #fireFormPdfModal .fpf-page:last-of-type {
        page-break-after: auto;
    }

    /* ===== Header: ไม่ fix height ===== */
    #fireFormPdfModal .fpf-header {
        height: auto !important;
        min-height: 60px;
        page-break-after: avoid;
    }

    /* ===== Two-column → table layout (page-break ได้ดีกว่า flex) ===== */
    #fireFormPdfModal .fpf-form-body {
        display: table !important;
        width: 100% !important;
        table-layout: fixed;
        border-collapse: collapse;
        page-break-inside: auto;
    }
    #fireFormPdfModal .fpf-col-left,
    #fireFormPdfModal .fpf-col-right {
        display: table-cell !important;
        width: 50% !important;
        vertical-align: top;
        float: none !important;
    }

    /* ===== ป้องกัน section แตกกลางหน้า ===== */
    #fireFormPdfModal .fpf-sec-row {
        page-break-inside: avoid;
    }
    #fireFormPdfModal .fpf-footer {
        page-break-before: avoid;
    }

    /* ===== Photo / Signature: ไม่ตัด ===== */
    #fireFormPdfModal .fpf-photo-grid { gap: 2px !important; }
    #fireFormPdfModal .fpf-photo-cell { page-break-inside: avoid; }
    #fireFormPdfModal .fpf-photo-dropzone { display: none !important; }
    #fireFormPdfModal .fpf-cell-delete { display: none !important; }
    #fireFormPdfModal .fpf-sig-box,
    #fireFormPdfModal .fpf-sketch-area,
    #fireFormPdfModal .fpf-sketch-viewport {
        page-break-inside: avoid;
    }
    #fireFormPdfModal .fpf-sketch-viewport { overflow: visible !important; height: auto !important; }

    /* ===== Input / Checkbox: แสดงค่าใน print ===== */
    #fireFormPdfModal .fpf-inp, #fireFormPdfModal .fpf-inp-m,
    #fireFormPdfModal .fpf-inp-full, #fireFormPdfModal .fpf-inp-s,
    #fireFormPdfModal .fpf-sel, #fireFormPdfModal .fpf-ta,
    #fireFormPdfModal .fpf-cb {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>

<!-- ===== MODAL ===== -->
<div class="modal fade" id="fireFormPdfModal" aria-labelledby="fireFormPdfModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 860px; margin: 1.75rem auto;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="fireFormPdfModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body fpf-body p-0" style="max-height: 80vh; overflow-y: auto; position:relative;">
                <!-- Loading Overlay -->
                <div id="firePdfLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track">
                            <div class="csims-bar-fill"></div>
                        </div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>
                <form id="fireFormPdf" novalidate>
                    <input type="hidden" id="fpf_receiveNoti_id" name="receiveNoti_id_fire">
                    <input type="hidden" id="fpf_doc_no" name="doc_no_fire">
                    <input type="hidden" id="fpf_report_no" name="report_no_fire">

                    <!-- Switch กลับไปฟอร์มมาตรฐาน -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="editInfoFirePdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountFirePdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToStdFormFire" checked style="width: 3rem; height: 1.5rem; cursor: pointer;" onchange="if(!this.checked){ this.checked=true; switchToFireStandardForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToStdFormFire" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ================================================================ -->
<!-- ========================= PAGE 1 ============================== -->
<!-- ================================================================ -->
<div class="fpf-page">

    <div class="fpf-header">
        <div class="fpf-header-logo">
            <img src="./images/office-of-police-forensic-icon.jpg" alt="ตราสำนักงานตำรวจแห่งชาติ">
        </div>
        <div class="fpf-header-center">
            <div class="fpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="fpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับเพลิงไหม้</div>
        </div>
        <div class="fpf-header-right">
            <div class="fpf-doc-box">
                <div class="fpf-doc-line">รายงานที่ <span id="fpf_report_no_display" style="display:inline-block;min-width:60px;border-bottom:1px dotted #888;text-align:center;"></span> / 25<span id="fpf_report_year_display" style="display:inline-block;min-width:30px;border-bottom:1px dotted #888;text-align:center;"><?= substr((date('Y') + 543), -2) ?></span></div>
                <div class="fpf-doc-line">หน้าที่ <span class="fpf-cur-page">1</span> / <span class="fpf-total-page">7</span></div>
            </div>
        </div>
    </div>

    <div class="fpf-form-body">

        <!-- LEFT COLUMN -->
        <div class="fpf-col-left">
            <div class="fpf-row-header">
                <div class="fpf-lbl-seq">ลำดับ</div>
                <div class="fpf-lbl-data">ข้อมูล</div>
            </div>

            <!-- 1. การรับแจ้งเหตุ -->
            <div class="fpf-sec-row">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">1.</span>
                    <span class="fpf-sec-txt">การรับ<br>แจ้งเหตุ</span>
                </div>
                <div class="fpf-sec-body">
                    <div class="fpf-fr">
                        <span class="fpf-fl">คดี</span>
                        <input type="text" class="fpf-inp" name="case_doc_no_fire" id="fpf_case_doc_no" readonly style="text-align:center; background:#f5f5f5;">
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">วันที่</span>
                        <input type="date" class="fpf-inp-m" name="fire_report_date" id="fpf_fire_report_date" value="<?= $fpfTodayDate ?>">
                        <span class="fpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="fpf-inp" name="fire_report_time" id="fpf_fire_report_time" value="<?= $fpfTodayTime ?>" style="text-align:center;">
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl" style="margin-right:10px;">การรับแจ้ง</span>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fire_notify_method[]" value="ทางโทรศัพท์">ทางโทรศัพท์</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fire_notify_method[]" value="ทางวิทยุสื่อสาร">ทางวิทยุสื่อสาร</label>
                    </div>
                    <div class="fpf-fr" style="padding-left:38px;">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fire_notify_method[]" value="ทางหนังสือ">ทางหนังสือ</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb" name="fire_notify_method[]" value="อื่นๆ" id="fpf_notify_other_chk">อื่นๆ</label>
                        <input type="text" class="fpf-inp" name="fire_notify_method_other_text" id="fpf_notify_other_text">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_notify_other_text" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">สน./สภ.</span>
                        <select class="fpf-sel" name="police_station_fire" id="fpf_police_station">
                            <?= $fpfPoliceStationOptions ?>
                        </select>
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">ที่</span>
                        <input type="text" class="fpf-inp" name="fire_document_no" id="fpf_document_no">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_document_no" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <span class="fpf-fl">ลง</span>
                        <input type="date" class="fpf-inp" name="fire_document_date" id="fpf_document_date" style="text-align:center;" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">พนักงานสอบสวน</span>
                        <input type="text" class="fpf-inp" name="fire_investigator_name" id="fpf_investigator_name">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_investigator_name" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">หมายเลขโทรศัพท์</span>
                        <input type="tel" class="fpf-inp" name="fire_investigator_phone" id="fpf_investigator_phone">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_investigator_phone" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                </div>
            </div>

            <!-- 2. สถานที่เกิดเหตุ -->
            <div class="fpf-sec-row">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">2.</span>
                    <span class="fpf-sec-txt">สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="fpf-sec-body">
                    <div class="fpf-fr">
                        <span class="fpf-fl">สถานที่เกิดเหตุ</span>
                        <input type="text" class="fpf-inp" name="fire_incident_location" id="fpf_incident_location">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_incident_location" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <input type="text" class="fpf-inp-full" name="fire_incident_location_2" id="fpf_incident_location_2">
                    <!-- ผู้เสียหาย/ผู้บาดเจ็บ/ผู้เสียชีวิต -->
                    <div id="fpf_victim_container">
                        <div class="fpf-victim-row" style="margin-top:3px; padding: 2px 0; border-top: 1px dotted #ccc;">
                            <div class="fpf-fr">
                                <span class="fpf-fl">ประเภท</span>
                                <select class="fpf-sel" name="fire_person_type[]" style="max-width:90px;">
                                    <option value="">--เลือก--</option>
                                    <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                                    <option value="ผู้บาดเจ็บ">ผู้บาดเจ็บ</option>
                                    <option value="ผู้เสียชีวิต">ผู้เสียชีวิต</option>
                                </select>
                                <span class="fpf-fl" style="margin-left:6px;">ชื่อ</span>
                                <input type="text" class="fpf-inp" name="fire_person_name[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                                <span class="fpf-fl" style="margin-left:4px;">อายุ</span>
                                <input type="text" class="fpf-inp-s" name="fire_person_age[]" style="max-width:30px;">
                                <span class="fpf-fl">ปี</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="fpf-add-btn" onclick="fpfAddVictim()">+ เพิ่มผู้เสียหาย</button>
                </div>
            </div>

            <!-- 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ -->
            <div class="fpf-sec-row">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">3.</span>
                    <span class="fpf-sec-txt">วันเวลา<br>ที่ทราบ<br>เหตุ/เกิด<br>เหตุ</span>
                </div>
                <div class="fpf-sec-body">
                    <div class="fpf-fr"><span class="fpf-fl-b">วันเวลาที่ผู้เสียหาย ทราบเหตุ/เกิดเหตุ</span></div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">วันที่</span>
                        <input type="date" class="fpf-inp-m" name="fire_victim_known_date" id="fpf_victim_known_date" value="<?= $fpfTodayDate ?>">
                        <span class="fpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="fpf-inp" name="fire_victim_known_time" id="fpf_victim_known_time" value="<?= $fpfTodayTime ?>" style="text-align:center;">
                    </div>
                    <div class="fpf-fr" style="margin-top:2px;"><span class="fpf-fl-b">วันเวลาที่พนักงานสอบสวนทราบเหตุ</span></div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">วันที่</span>
                        <input type="date" class="fpf-inp-m" name="fire_investigator_known_date" id="fpf_investigator_known_date" value="<?= $fpfTodayDate ?>">
                        <span class="fpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="fpf-inp" name="fire_investigator_known_time" id="fpf_investigator_known_time" value="<?= $fpfTodayTime ?>" style="text-align:center;">
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">ทราบเหตุ</span>
                        <input type="text" class="fpf-inp" name="fire_known_detail" id="fpf_known_detail">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_known_detail" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                </div>
            </div>

            <!-- 4. วันเวลาที่ตรวจเหตุ -->
            <div class="fpf-sec-row">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">4.</span>
                    <span class="fpf-sec-txt">วัน<br>เวลาที่<br>ตรวจ<br>เหตุ</span>
                </div>
                <div class="fpf-sec-body">
                    <div class="fpf-fr"><span class="fpf-fl-b">วันเวลาที่ทำการตรวจสถานที่เกิดเหตุ</span></div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">วันที่</span>
                        <input type="date" class="fpf-inp-m" name="fire_inspection_date" id="fpf_inspection_date" value="<?= $fpfTodayDate ?>">
                        <span class="fpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="fpf-inp" name="fire_inspection_time" id="fpf_inspection_time" value="<?= $fpfTodayTime ?>" style="text-align:center;">
                    </div>
                    <div class="fpf-fr" style="margin-top:2px;"><span class="fpf-fl-b">วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเพิ่มเติม</span></div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">วันที่</span>
                        <input type="date" class="fpf-inp-m" name="fire_inspection_additional_date" id="fpf_inspection_additional_date">
                        <span class="fpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="fpf-inp" name="fire_inspection_additional_time" id="fpf_inspection_additional_time" style="text-align:center;">
                    </div>
                </div>
            </div>

            <!-- 5. ผู้ตรวจสถานที่เกิดเหตุ -->
            <div class="fpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">5.</span>
                    <span class="fpf-sec-txt">ผู้ตรวจ<br>สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="fpf-sec-body">
                    <div class="fpf-bh" style="margin-top:0;">
                        <span class="fpf-bk"></span>
                        <span>ผู้ตรวจสถานที่เกิดเหตุ</span>
                    </div>
                    <div id="fpf_inspector_container">
                        <div class="fpf-si fpf-inspector-row">
                            <span class="fpf-si-no">5.1</span>
                            <select class="fpf-sel" name="fire_inspector_id[]">
                                <?= $inspectorOptionsFire ?>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="fpf-add-btn" onclick="fpfAddInspector()">+ เพิ่มผู้ตรวจ</button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="fpf-col-right">
            <div class="fpf-row-header">
                <div class="fpf-lbl-seq">ลำดับ</div>
                <div class="fpf-lbl-data">ข้อมูล</div>
            </div>

            <!-- 6. ลักษณะของสถานที่เกิดเหตุ -->
            <div class="fpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">6.</span>
                    <span class="fpf-sec-txt">ลักษณะ<br>สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="fpf-sec-body">
                    <!-- 6.1 ลักษณะภายนอก -->
                    <div class="fpf-bh" style="margin-top:0;"><span class="fpf-bk"></span><span>6.1 ลักษณะภายนอก</span></div>
                    <div class="fpf-fr fpf-i1">
                        <span class="fpf-fl">รายละเอียด</span>
                        <input type="text" class="fpf-inp" name="fire_exterior_detail" id="fpf_exterior_detail">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_exterior_detail" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <input type="text" class="fpf-inp-full fpf-i1" name="fire_exterior_detail_2">
                    <div class="fpf-fr fpf-i1">
                        <span class="fpf-fl">จำนวนชั้น</span>
                        <input type="text" class="fpf-inp" name="fire_floor_count" id="fpf_floor_count">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_floor_count" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- บริเวณโดยรอบ -->
                    <div class="fpf-bh fpf-i1"><span class="fpf-bk"></span><span>บริเวณโดยรอบ</span></div>
                    <div class="fpf-cg fpf-i2">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fpf-radio-toggle" name="fire_fence" value="has_fence" data-group="fpf_fence">มีรั้ว</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fpf-radio-toggle" name="fire_fence" value="no_fence" data-group="fpf_fence">ไม่มีรั้ว</label>
                    </div>

                    <!-- เมื่อหันหน้าเข้า -->
                    <div class="fpf-bh fpf-i1"><span class="fpf-bk"></span><span>เมื่อหันหน้าเข้า</span></div>
                    <div class="fpf-i2">
                        <div class="fpf-fr"><span class="fpf-fl">ด้านหน้าติด</span><input type="text" class="fpf-inp" name="fire_scene_front"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">ด้านซ้ายติด</span><input type="text" class="fpf-inp" name="fire_scene_left"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">ด้านขวาติด</span><input type="text" class="fpf-inp" name="fire_scene_right"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">ด้านหลังติด</span><input type="text" class="fpf-inp" name="fire_scene_back"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    </div>

                    <!-- 6.2 ลักษณะภายใน -->
                    <div class="fpf-bh" style="margin-top:4px;"><span class="fpf-bk"></span><span>6.2 ลักษณะภายใน</span> <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_interior_detail" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div class="fpf-i1">
                        <textarea class="fpf-ta" name="fire_interior_detail" id="fpf_interior_detail" rows="4"></textarea>
                    </div>

                    <!-- 6.3 บริเวณที่เกิดเหตุ -->
                    <div class="fpf-bh" style="margin-top:4px;"><span class="fpf-bk"></span><span>6.3 บริเวณที่เกิดเหตุ</span></div>
                    <div class="fpf-fr fpf-i1">
                        <span class="fpf-fl">รายละเอียด</span>
                        <input type="text" class="fpf-inp" name="fire_incident_area_detail">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <input type="text" class="fpf-inp-full fpf-i1" name="fire_incident_area_detail_2">
                    <input type="text" class="fpf-inp-full fpf-i1" name="fire_incident_area_detail_3">
                    <div class="fpf-fr fpf-i1">
                        <span class="fpf-fl">ขนาดกว้าง x ยาว ประมาณ</span>
                        <input type="text" class="fpf-inp" name="fire_area_size">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="fpf-fr fpf-i1">
                        <span class="fpf-fl">เมื่อหันหน้าเข้า</span>
                        <input type="text" class="fpf-inp" name="fire_facing_direction">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <input type="text" class="fpf-inp-full fpf-i1">
                    <input type="text" class="fpf-inp-full fpf-i1">
                    <input type="text" class="fpf-inp-full fpf-i1">
                    <input type="text" class="fpf-inp-full fpf-i1">
                    <input type="text" class="fpf-inp-full fpf-i1">
                    <input type="text" class="fpf-inp-full fpf-i1">
                    <input type="text" class="fpf-inp-full fpf-i1">
                    <input type="text" class="fpf-inp-full fpf-i1">
                </div>
            </div>
        </div>
    </div>

    <div class="fpf-footer">
        <div class="fpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="fpf-footer-right">F-CS-12 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 2 ============================== -->
<!-- ================================================================ -->
<div class="fpf-page">

    <div class="fpf-header">
        <div class="fpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="fpf-header-center">
            <div class="fpf-title-main">ตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="fpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับเพลิงไหม้</div>
        </div>
        <div class="fpf-header-right">
            <div class="fpf-doc-box">
                <div class="fpf-doc-line">รายงานที่ <span class="fpf-rpt-no-mirror"></span> / 25<span class="fpf-rpt-year-mirror"></span></div>
                <div class="fpf-doc-line">หน้าที่ <span class="fpf-cur-page">2</span> / <span class="fpf-total-page">7</span></div>
            </div>
        </div>
    </div>

    <div class="fpf-form-body">
        <!-- LEFT COLUMN -->
        <div class="fpf-col-left">
            <div class="fpf-row-header"><div class="fpf-lbl-seq">ลำดับ</div><div class="fpf-lbl-data">ข้อมูล</div></div>

            <!-- 6. (ต่อ) ลักษณะโครงสร้าง + สิ่งของ -->
            <div class="fpf-sec-row">
                <div class="fpf-sec-label"><span class="fpf-sec-num">6.</span><span class="fpf-sec-txt">(ต่อ)</span></div>
                <div class="fpf-sec-body">
                    <div class="fpf-bh" style="margin-top:0;"><span class="fpf-bk"></span><span>ลักษณะโครงสร้าง</span></div>
                    <div class="fpf-i1">
                        <div class="fpf-fr"><span class="fpf-fl">ฝาผนังหน้า</span><input type="text" class="fpf-inp" name="fire_structure_wall_front"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">ฝาผนังซ้าย</span><input type="text" class="fpf-inp" name="fire_structure_wall_left"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">ฝาผนังขวา</span><input type="text" class="fpf-inp" name="fire_structure_wall_right"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">ฝาผนังหลัง</span><input type="text" class="fpf-inp" name="fire_structure_wall_back"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">พื้นห้อง</span><input type="text" class="fpf-inp" name="fire_structure_floor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">หลังคา</span><input type="text" class="fpf-inp" name="fire_structure_roof"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">เพดาน</span><input type="text" class="fpf-inp" name="fire_structure_ceiling"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    </div>

                    <div class="fpf-bh"><span class="fpf-bk"></span><span>ลักษณะการจัดวางสิ่งของ</span></div>
                    <div class="fpf-i1">
                        <div class="fpf-fr"><span class="fpf-fl">สิ่งของชิดฝาผนังหน้า</span><input type="text" class="fpf-inp" name="fire_objects_wall_front"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">สิ่งของชิดฝาผนังซ้าย</span><input type="text" class="fpf-inp" name="fire_objects_wall_left"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">สิ่งของชิดฝาผนังขวา</span><input type="text" class="fpf-inp" name="fire_objects_wall_right"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">สิ่งของชิดฝาผนังหลัง</span><input type="text" class="fpf-inp" name="fire_objects_wall_back"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">บริเวณอื่นๆ</span><input type="text" class="fpf-inp" name="fire_objects_other"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    </div>
                </div>
            </div>

            <!-- 7. พฤติการณ์คดี & สภาพความเสียหาย -->
            <div class="fpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="fpf-sec-label">
                    <span class="fpf-sec-num">7.</span>
                    <span class="fpf-sec-txt">พฤติ-<br>การณ์คดี<br>&amp;<br>สภาพ<br>ความ<br>เสียหาย</span>
                </div>
                <div class="fpf-sec-body">
                    <div class="fpf-bh" style="margin-top:0;"><span class="fpf-bk"></span><span>พฤติการณ์คดี</span> <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_case_behavior" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="fpf-ta" name="fire_case_behavior" id="fpf_case_behavior" rows="4"></textarea>

                    <div class="fpf-fr" style="margin-top:2px;">
                        <span class="fpf-bk"></span>
                        <span class="fpf-fl" style="margin-right:6px;">ประกันภัย</span>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fpf-radio-toggle" name="fire_insurance" value="has_insurance" data-group="fpf_insurance">มีประกัน</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fpf-radio-toggle" name="fire_insurance" value="no_insurance" data-group="fpf_insurance">ไม่มีประกัน</label>
                    </div>

                    <div class="fpf-fr" style="margin-top:2px;">
                        <span class="fpf-bk"></span>
                        <span class="fpf-fl">เวลาไฟลุกไหม้</span>
                        <input type="text" class="fpf-inp" name="fire_burn_time">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <div class="fpf-fr" style="margin-top:2px;">
                        <span class="fpf-bk"></span>
                        <span class="fpf-fl" style="margin-right:6px;">การดับไฟ</span>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fpf-radio-toggle" name="fire_extinguish" value="yes" data-group="fpf_extinguish">มี</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fpf-radio-toggle" name="fire_extinguish" value="no" data-group="fpf_extinguish">ไม่มี</label>
                    </div>
                    <input type="text" class="fpf-inp-full fpf-i1" name="fire_extinguish_detail" placeholder="รายละเอียดการดับไฟ...">

                    <div class="fpf-bh" style="margin-top:2px;"><span class="fpf-bk"></span><span>สภาพความเสียหาย ของ</span> <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_damage_condition" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="fpf-ta" name="fire_damage_condition" id="fpf_damage_condition" rows="2"></textarea>

                    <div class="fpf-bh" style="margin-top:2px;"><span class="fpf-bk"></span><span>เพลิงลุกลาม ไหม้</span> <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_spread_detail" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="fpf-ta" name="fire_spread_detail" id="fpf_spread_detail" rows="2"></textarea>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="fpf-col-right">
            <div class="fpf-row-header"><div class="fpf-lbl-seq">ลำดับ</div><div class="fpf-lbl-data">ข้อมูล</div></div>

            <!-- 7. (ต่อ) -->
            <div class="fpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="fpf-sec-label"><span class="fpf-sec-num">7.</span><span class="fpf-sec-txt">(ต่อ)</span></div>
                <div class="fpf-sec-body">
                    <!-- 7.1 สภาพความเสียหายโครงสร้าง -->
                    <div class="fpf-bh" style="margin-top:0;"><span class="fpf-bk"></span><span>7.1 สภาพความเสียหายโครงสร้าง</span></div>
                    <div class="fpf-i1">
                        <div class="fpf-fr"><span class="fpf-fl">ฝาผนังหน้า</span><input type="text" class="fpf-inp" name="fire_damage_wall_front"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">ฝาผนังซ้าย</span><input type="text" class="fpf-inp" name="fire_damage_wall_left"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">ฝาผนังขวา</span><input type="text" class="fpf-inp" name="fire_damage_wall_right"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">ฝาผนังหลัง</span><input type="text" class="fpf-inp" name="fire_damage_wall_back"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">พื้นห้อง</span><input type="text" class="fpf-inp" name="fire_damage_floor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">หลังคา</span><input type="text" class="fpf-inp" name="fire_damage_roof"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">เพดาน</span><input type="text" class="fpf-inp" name="fire_damage_ceiling"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    </div>

                    <!-- 7.2 สภาพความเสียหายของสิ่งของ -->
                    <div class="fpf-bh"><span class="fpf-bk"></span><span>7.2 สภาพความเสียหายของสิ่งของ</span></div>
                    <div class="fpf-i1">
                        <div class="fpf-fr"><span class="fpf-fl">ชิดฝาผนังหน้า</span><input type="text" class="fpf-inp" name="fire_damage_obj_front"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">ชิดฝาผนังซ้าย</span><input type="text" class="fpf-inp" name="fire_damage_obj_left"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">ชิดฝาผนังขวา</span><input type="text" class="fpf-inp" name="fire_damage_obj_right"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">ชิดฝาผนังหลัง</span><input type="text" class="fpf-inp" name="fire_damage_obj_back"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">พื้นห้อง</span><input type="text" class="fpf-inp" name="fire_damage_obj_floor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">หลังคา</span><input type="text" class="fpf-inp" name="fire_damage_obj_roof"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="fpf-fr"><span class="fpf-fl">เพดาน</span><input type="text" class="fpf-inp" name="fire_damage_obj_ceiling"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    </div>

                    <!-- 7.3 -->
                    <div class="fpf-bh"><span class="fpf-bk"></span><span>7.3 บริเวณที่เกิดเหตุขึ้นก่อน</span> <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_first_area" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="fpf-ta fpf-i1" name="fire_first_area" id="fpf_first_area" rows="2"></textarea>

                    <!-- 7.4 -->
                    <div class="fpf-bh"><span class="fpf-bk"></span><span>7.4 สภาพแมนสวิตช์ควบคุมไฟฟ้า</span> <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_switch_condition" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="fpf-ta fpf-i1" name="fire_switch_condition" id="fpf_switch_condition" rows="2"></textarea>

                    <!-- 7.5 -->
                    <div class="fpf-bh"><span class="fpf-bk"></span><span>7.5 สภาพเสียหายบริเวณข้างเคียง</span></div>
                    <div class="fpf-cg fpf-i1">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fpf-radio-toggle" name="fire_adjacent_damage" value="found" data-group="fpf_adjacent">พบ</label>
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fpf-radio-toggle" name="fire_adjacent_damage" value="not_found" data-group="fpf_adjacent">ไม่พบ</label>
                    </div>
                    <input type="text" class="fpf-inp-full fpf-i1" name="fire_adjacent_damage_detail">

                    <!-- 7.6 -->
                    <div class="fpf-bh"><span class="fpf-bk"></span><span>7.6 วัตถุพยานที่ตรวจพบ</span> <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_evidence_found" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="fpf-ta fpf-i1" name="fire_evidence_found" id="fpf_evidence_found" rows="2"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="fpf-footer">
        <div class="fpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="fpf-footer-right">F-CS-12 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 3 ============================== -->
<!-- ================================================================ -->
<div class="fpf-page">

    <div class="fpf-header">
        <div class="fpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="fpf-header-center">
            <div class="fpf-title-main">ตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="fpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับเพลิงไหม้</div>
        </div>
        <div class="fpf-header-right">
            <div class="fpf-doc-box">
                <div class="fpf-doc-line">รายงานที่ <span class="fpf-rpt-no-mirror"></span> / 25<span class="fpf-rpt-year-mirror"></span></div>
                <div class="fpf-doc-line">หน้าที่ <span class="fpf-cur-page">3</span> / <span class="fpf-total-page">7</span></div>
            </div>
        </div>
    </div>

    <div class="fpf-form-body">
        <!-- LEFT COLUMN -->
        <div class="fpf-col-left">
            <div class="fpf-row-header"><div class="fpf-lbl-seq">ลำดับ</div><div class="fpf-lbl-data">ข้อมูล</div></div>

            <!-- 8. สรุปผลการตรวจ -->
            <div class="fpf-sec-row">
                <div class="fpf-sec-label"><span class="fpf-sec-num">8.</span><span class="fpf-sec-txt">สรุปผล<br>การตรวจ</span></div>
                <div class="fpf-sec-body">
                    <div class="fpf-bh" style="margin-top:0;"><span class="fpf-bk"></span><span>8.1 บริเวณต้นเพลิงคือ</span> <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_origin_area" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="fpf-ta fpf-i1" name="fire_origin_area" id="fpf_origin_area" rows="5"></textarea>

                    <div class="fpf-bh"><span class="fpf-bk"></span><span>8.2 เชื้อเพลิงที่ทำให้เกิดการลุกไหม้บริเวณต้นเพลิง</span> <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_fuel_source" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="fpf-ta fpf-i1" name="fire_fuel_source" id="fpf_fuel_source" rows="5"></textarea>

                    <div class="fpf-bh"><span class="fpf-bk"></span><span>8.3 แหล่งความร้อนที่ทำให้เกิดเพลิงไหม้</span> <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_heat_source" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="fpf-ta fpf-i1" name="fire_heat_source" id="fpf_heat_source" rows="5"></textarea>

                    <div class="fpf-bh"><span class="fpf-bk"></span><span>8.4 อื่นๆ</span> <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_summary_other" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="fpf-ta fpf-i1" name="fire_summary_other" id="fpf_summary_other" rows="5"></textarea>
                </div>
            </div>

            <!-- 9. ความเห็น -->
            <div class="fpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="fpf-sec-label"><span class="fpf-sec-num">9.</span><span class="fpf-sec-txt">ความเห็น</span></div>
                <div class="fpf-sec-body">
                    <div class="fpf-fr" style="margin-top:0;">
                        <span class="fpf-fl">จากการตรวจสถานที่เกิดเหตุเชื่อว่า เพลิงลุกไหม้ขึ้นก่อนบริเวณ</span>
                    </div>
                    <textarea class="fpf-ta" name="fire_opinion_first_area" id="fpf_opinion_first_area" rows="3"></textarea>

                    <div class="fpf-fr" style="margin-top:4px;"><span class="fpf-fl-b">สาเหตุของการเกิดเพลิงไหม้ครั้งนี้</span></div>
                    <div class="fpf-cg fpf-i1" style="margin-top:2px;">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fpf-radio-toggle" name="fire_cause_type" value="believed" data-group="fpf_cause">น่าเชื่อว่าเกิดจาก</label>
                        <input type="text" class="fpf-inp" name="fire_cause_believed_detail">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <input type="text" class="fpf-inp-full fpf-i1">
                    <input type="text" class="fpf-inp-full fpf-i1">
                    <div class="fpf-cg fpf-i1" style="margin-top:2px;">
                        <label class="fpf-ck"><input type="checkbox" class="fpf-cb fpf-radio-toggle" name="fire_cause_type" value="unknown" data-group="fpf_cause">ไม่สามารถระบุให้ชัดได้ เนื่องจาก</label>
                    </div>
                    <input type="text" class="fpf-inp-full fpf-i1" name="fire_cause_unknown_detail">
                    <input type="text" class="fpf-inp-full fpf-i1">
                    <input type="text" class="fpf-inp-full fpf-i1">

                    <div class="fpf-bh" style="margin-top:6px;"><span class="fpf-bk"></span><span>หมายเหตุ</span> <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_remark" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="fpf-ta" name="fire_remark" id="fpf_remark" rows="6"></textarea>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="fpf-col-right">
            <div class="fpf-row-header"><div class="fpf-lbl-seq">ลำดับ</div><div class="fpf-lbl-data">ข้อมูล</div></div>

            <!-- 10. การส่งมอบคืนสถานที่เกิดเหตุ -->
            <div class="fpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="fpf-sec-label"><span class="fpf-sec-num">10.</span><span class="fpf-sec-txt">การส่ง<br>มอบคืน<br>สถานที่<br>เกิดเหตุ</span></div>
                <div class="fpf-sec-body">
                    <div class="fpf-bh" style="margin-top:0;"><span class="fpf-bk"></span><span>วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น</span></div>
                    <div class="fpf-fr fpf-i1">
                        <span class="fpf-fl">วันที่</span>
                        <input type="date" class="fpf-inp" name="fire_inspection_end_date" id="fpf_inspection_end_date" value="<?= $fpfTodayDate ?>" style="text-align:center;">
                        <span class="fpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="fpf-inp" name="fire_inspection_end_time" id="fpf_inspection_end_time" value="<?= $fpfTodayTime ?>" style="text-align:center;">
                        <span class="fpf-fl">น.</span>
                    </div>

                    <div class="fpf-bh" style="margin-top:6px;"><span class="fpf-bk"></span><span>การส่งมอบสถานที่เกิดเหตุ</span></div>

                    <!-- ผู้รับมอบ -->
                    <div class="fpf-fr" style="margin-top:6px; align-items:flex-end;">
                        <span class="fpf-fl">ลงชื่อ</span>
                        <div style="flex:1; min-height:50px; border-bottom:1px dotted #888; position:relative;">
                            <canvas id="fpf_sig_receiver" style="width:100%; height:50px; cursor:crosshair;"></canvas>
                            <input type="hidden" name="receiver_signature_data_fire" id="fpf_receiver_sig_data">
                        </div>
                        <span class="fpf-fl">ผู้รับมอบสถานที่เกิดเหตุ</span>
                    </div>
                    <div class="fpf-fr" style="margin-top:2px; justify-content:flex-end;">
                        <button type="button" class="fpf-add-btn" onclick="fpfClearCanvas('fpf_sig_receiver')">ล้างลายเซ็นผู้รับมอบ</button>
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl" style="visibility:hidden;">ลงชื่อ</span>
                        <span class="fpf-fl">(</span>
                        <select class="fpf-sel" name="receiver_name_fire" id="fpf_receiver_name" style="text-align:center;">
                            <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้รับมอบ --', $inspectorOptionsFire) ?>
                        </select>
                        <span class="fpf-fl">)</span>
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">ตำแหน่ง</span>
                        <input type="text" class="fpf-inp" name="receiver_position_fire" id="fpf_receiver_position" readonly>
                    </div>

                    <!-- ผู้ส่งมอบ -->
                    <div class="fpf-fr" style="margin-top:8px; align-items:flex-end;">
                        <span class="fpf-fl">ลงชื่อ</span>
                        <div style="flex:1; min-height:50px; border-bottom:1px dotted #888; position:relative;">
                            <canvas id="fpf_sig_sender" style="width:100%; height:50px; cursor:crosshair;"></canvas>
                            <input type="hidden" name="sender_signature_data_fire" id="fpf_sender_sig_data">
                        </div>
                        <span class="fpf-fl">ผู้ส่งมอบสถานที่เกิดเหตุ</span>
                    </div>
                    <div class="fpf-fr" style="margin-top:2px; justify-content:flex-end;">
                        <button type="button" class="fpf-add-btn" onclick="fpfClearCanvas('fpf_sig_sender')">ล้างลายเซ็นผู้ส่งมอบ</button>
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl" style="visibility:hidden;">ลงชื่อ</span>
                        <span class="fpf-fl">(</span>
                        <select class="fpf-sel" name="sender_name_fire" id="fpf_sender_name" style="text-align:center;">
                            <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งมอบ --', $inspectorOptionsFire) ?>
                        </select>
                        <span class="fpf-fl">)</span>
                    </div>
                    <div class="fpf-fr">
                        <span class="fpf-fl">ตำแหน่ง</span>
                        <input type="text" class="fpf-inp" name="sender_position_fire" id="fpf_sender_position" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="fpf-footer">
        <div class="fpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="fpf-footer-right">F-CS-12 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 4 (EVIDENCE LOCATION) ========== -->
<!-- ================================================================ -->
<div class="fpf-page">

    <div class="fpf-header">
        <div class="fpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="fpf-header-center">
            <div class="fpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="fpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับเพลิงไหม้</div>
        </div>
        <div class="fpf-header-right">
            <div class="fpf-doc-box">
                <div class="fpf-doc-line">รายงานที่ <span class="fpf-rpt-no-mirror"></span> / 25<span class="fpf-rpt-year-mirror"></span></div>
                <div class="fpf-doc-line">หน้าที่ <span class="fpf-cur-page">4</span> / <span class="fpf-total-page">7</span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:6px;">
        <span class="fpf-bk"></span>
        <span style="font-size:12px; font-weight:600;">วัตถุพยานและตำแหน่งที่ตรวจพบ</span>
        <button type="button" class="fpf-add-btn" style="float:right;" onclick="fpfAddEvidenceRow()">+ เพิ่มรายการ</button>
    </div>

    <table class="fpf-ev-table" style="width:100%; border-collapse:collapse; font-size:11px;">
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
        <tbody id="fpf_evidence_tbody">
            <tr>
                <td style="width:40px; text-align:center; font-weight:600;">1<input type="hidden" name="evidence_label_fire[]" value="1"></td>
                <td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_item_fire[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>
                <td><input type="text" name="evidence_level_1_fire_0" style="width:50px; text-align:center;" inputmode="decimal"></td>
                <td><input type="text" name="evidence_level_2_fire_0" style="width:50px; text-align:center;" inputmode="decimal"></td>
                <td><input type="text" name="evidence_level_3_fire_0" style="width:50px; text-align:center;" inputmode="decimal"></td>
                <td><input type="text" name="evidence_level_4_fire_0" style="width:50px; text-align:center;" inputmode="decimal"></td>
                <td><input type="text" name="evidence_azimuth_fire[]"></td>
                <td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_remark_fire[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>
                <td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option></select><input type="hidden" class="lab-unit-value" name="evidence_lab_unit_fire[]" value=""></td>
                <td><button type="button" class="fpf-del-btn" onclick="fpfDelRow(this)">×</button></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top:8px; font-size:11.5px;">
        <div class="fpf-fr" style="margin-bottom:2px;">
            <span class="fpf-fl">จุดอ้างอิงที่ 1 คือ</span>
            <input type="text" class="fpf-inp" name="reference_point_1_fire">
            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
        </div>
        <div class="fpf-fr" style="margin-bottom:2px;">
            <span class="fpf-fl">จุดอ้างอิงที่ 2 คือ</span>
            <input type="text" class="fpf-inp" name="reference_point_2_fire">
            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
        </div>
        <div class="fpf-fr" style="margin-bottom:2px;">
            <span class="fpf-fl">จุดอ้างอิงที่ 3 คือ</span>
            <input type="text" class="fpf-inp" name="reference_point_3_fire">
            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
        </div>
        <div class="fpf-fr" style="margin-bottom:2px;">
            <span class="fpf-fl">จุดอ้างอิงที่ 4 คือ</span>
            <input type="text" class="fpf-inp" name="reference_point_4_fire">
            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
        </div>
    </div>

    <div style="margin-top:12px; display:flex; flex-direction:column; align-items:flex-end;">
        <div class="fpf-fr" style="width:auto;">
            <span class="fpf-fl">ผู้จดบันทึก</span>
            <select class="fpf-sel" name="fire_sketch_recorder" id="fpf_sketch_recorder" style="width:250px;">
                <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือก --', $inspectorOptionsFire) ?>
            </select>
        </div>
        <div class="fpf-fr" style="width:auto;">
            <span class="fpf-fl">วัน/เวลา</span>
            <input type="datetime-local" class="fpf-inp" name="fire_sketch_datetime" style="width:250px; text-align:center;">
        </div>
    </div>

    <div class="fpf-footer">
        <div class="fpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="fpf-footer-right">F-CS-12 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 5 (EVIDENCE COLLECTION) ======== -->
<!-- ================================================================ -->
<div class="fpf-page">

    <div class="fpf-header">
        <div class="fpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="fpf-header-center">
            <div class="fpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="fpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับเพลิงไหม้</div>
            <div style="font-size:11px; font-weight:600;">บันทึกการตรวจเก็บวัตถุพยาน</div>
        </div>
        <div class="fpf-header-right">
            <div class="fpf-doc-box">
                <div class="fpf-doc-line">รายงานที่ <span class="fpf-rpt-no-mirror"></span> / 25<span class="fpf-rpt-year-mirror"></span></div>
                <div class="fpf-doc-line">หน้าที่ <span class="fpf-cur-page">5</span> / <span class="fpf-total-page">7</span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:6px;">
        <div class="fpf-fr">
            <span class="fpf-fl">วันที่ตรวจสถานที่เกิดเหตุ</span>
            <input type="date" class="fpf-inp" name="measurement_inspection_date_fire" style="text-align:center;">
            <span class="fpf-fl">เวลาประมาณ</span>
            <input type="time" class="fpf-inp" name="measurement_inspection_time_fire" style="text-align:center;">
            <span class="fpf-fl">น.</span>
        </div>
        <button type="button" class="fpf-add-btn" style="float:right;" onclick="fpfAddCollectionRow()">+ เพิ่มรายการ</button>
    </div>

    <table class="fpf-ev-table" style="width:100%; border-collapse:collapse; font-size:10.5px;">
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
        <tbody id="fpf_collection_tbody">
            <tr>
                <td style="text-align:center;">1</td>
                <td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="measurement_item_fire[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>
                <td><input type="text" name="measurement_quantity_fire[]" style="width:30px; text-align:center;"></td>
                <td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="measurement_area_fire[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>
                <td><input type="text" name="measurement_label_number_fire[]" style="width:30px; text-align:center;"></td>
                <td><input type="checkbox" name="measurement_package_plastic_check_fire_0" value="1"></td>
                <td><input type="checkbox" name="measurement_package_paper_check_fire_0" value="1"></td>
                <td><input type="checkbox" name="measurement_package_other_check_fire_0" value="1"></td>
                <td><input type="checkbox" name="measurement_action_return_check_fire_0" value="1"></td>
                <td><input type="checkbox" name="measurement_action_other_check_fire_0" value="1"></td>
                <td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="measurement_remark_fire[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>
                <td>
                    <select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;">
                        <option value="">--</option>
                        <option value="fingerprint">ลายนิ้วมือแฝง</option>
                        <option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option>
                        <option value="chemical">เคมีฟิสิกส์</option>
                        <option value="drug">ยาเสพติด</option>
                        <option value="gun">อาวุธปืน</option>
                        <option value="document">เอกสาร</option>
                        <option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option>
                    </select>
                    <input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_fire[]" value="">
                </td>
                <td><button type="button" class="fpf-del-btn" onclick="fpfDelRow(this)">×</button></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top:12px; display:flex; flex-direction:column; align-items:flex-end;">
        <div class="fpf-fr" style="width:auto;">
            <span class="fpf-fl">ผู้จดบันทึก</span>
            <select class="fpf-sel" name="ec_recorder_fire" id="fpf_ec_recorder" style="width:250px;">
                <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือก --', $inspectorOptionsFire) ?>
            </select>
        </div>
        <div class="fpf-fr" style="width:auto;">
            <span class="fpf-fl">วัน /เวลา</span>
            <input type="datetime-local" class="fpf-inp" name="ec_datetime_fire" style="width:250px; text-align:center;">
        </div>
    </div>

    <div class="fpf-footer">
        <div class="fpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="fpf-footer-right">F-CS-12 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 6 (SKETCH) ===================== -->
<!-- ================================================================ -->
<div class="fpf-page fpf-sketch-page" id="fpf_sketch_page_1" data-sketch-page="1">

    <div class="fpf-header">
        <div class="fpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="fpf-header-center">
            <div class="fpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="fpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับเพลิงไหม้</div>
        </div>
        <div class="fpf-header-right">
            <div class="fpf-doc-box">
                <div class="fpf-doc-line">รายงานที่ <span class="fpf-rpt-no-mirror"></span> / 25<span class="fpf-rpt-year-mirror"></span></div>
                <div class="fpf-doc-line">หน้าที่ <span class="fpf-cur-page">6</span> / <span class="fpf-total-page">7</span></div>
            </div>
        </div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
        <div></div>
        <div style="font-size:13px; font-weight:600;">แผนผังสังเขป</div>
        <div style="display:flex; gap:4px; align-items:center;">
            <label class="fpf-add-btn" style="padding:2px 8px; cursor:pointer; margin:0;" title="แนบรูปภาพพื้นหลัง">
                <i class="fas fa-image me-1"></i> แนบรูป
                <input type="file" accept="image/*" style="display:none;" onchange="fpfSketchAttachImage('fpf_sketch_page_1',this)">
            </label>
            <button type="button" class="fpf-add-btn" style="padding:2px 8px;" onclick="fpfSketchRemoveBg('fpf_sketch_page_1')" title="ลบรูปพื้นหลัง"><i class="fas fa-times"></i> ลบรูป</button>
        </div>
    </div>

    <div class="fpf-sketch-viewport" id="fpf_sketch_page_1_viewport">
        <div class="fpf-sketch-canvas-wrap" id="fpf_sketch_page_1_wrap" style="aspect-ratio:1120/660;">
            <canvas id="fpf_sketch_page_1_canvas" width="1120" height="660"></canvas>
        </div>
        <div class="fpf-not-to-scale">* NOT TO SCALE</div>
    </div>
    <div style="text-align:right; margin-top:4px; display:flex; justify-content:flex-end; gap:6px; align-items:center;">
        <button type="button" class="fpf-add-btn" onclick="sketchUndo('fpf_sketch_page_1_canvas')" style="padding:2px 8px;"><i class="fas fa-undo"></i> ย้อนกลับ</button>
        <button type="button" class="fpf-add-btn" id="fpf_sketch_page_1_canvas_eraser_btn" onclick="sketchToggleEraser('fpf_sketch_page_1_canvas')" style="padding:2px 8px;" title="ยางลบ"><i class="fas fa-eraser"></i></button>
        <div style="display:flex;align-items:center;gap:4px;">
            <i class="fas fa-pen" style="font-size:10px;color:#666;"></i>
            <input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize('fpf_sketch_page_1_canvas',this.value);this.nextElementSibling.textContent=this.value+'px'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">
            <span style="font-size:11px;color:#666;min-width:35px;">2px</span>
        </div>
        <label style="margin:0;cursor:pointer;" title="เลือกสี"><input type="color" value="#000000" onchange="sketchSetColor('fpf_sketch_page_1_canvas',this.value)" style="width:26px;height:22px;border:1px solid #aaa;border-radius:3px;padding:0;cursor:pointer;"></label>
        <button type="button" class="fpf-add-btn" onclick="fpfClearCanvas('fpf_sketch_page_1_canvas')">ล้างกระดาน</button>
    </div>

    <div style="margin-bottom:8px;">
        <div class="fpf-fr">
            <span class="fpf-fl">หมายเหตุ</span><button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="fpf_sketch_remark" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
        </div>
        <textarea class="fpf-ta" name="fire_sketch_remark" id="fpf_sketch_remark" rows="6"></textarea>
    </div>

    <div style="margin-top:10px; display:flex; flex-direction:column; align-items:flex-end;">
        <div class="fpf-fr" style="width:auto;">
            <span class="fpf-fl">ผู้จดบันทึก</span>
            <select class="fpf-sel" name="recorder_name_fire" id="fpf_recorder_name" style="width:250px;">
                <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือก --', $inspectorOptionsFire) ?>
            </select>
        </div>
        <div class="fpf-fr" style="width:auto;">
            <span class="fpf-fl">วัน เวลา</span>
            <input type="datetime-local" class="fpf-inp" name="recorder_datetime_fire" id="fpf_recorder_datetime" style="width:250px; text-align:center;">
        </div>
    </div>

    <div style="text-align:center; margin-top:8px;">
        <button type="button" class="fpf-add-btn" onclick="fpfSketchAddPage()" style="padding:3px 14px; font-size:12px;">
            <i class="fas fa-plus me-1"></i> เพิ่มหน้าแผนผัง
        </button>
    </div>

    <div class="fpf-footer">
        <div class="fpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="fpf-footer-right">F-CS-12 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>
<!-- ★ Dynamic sketch pages will be inserted here by JS (before PAGE 7) -->

<!-- Hidden inputs for data persistence -->
<input type="hidden" name="scene_sketch_data_fire" id="fpf_scene_sketch_data">
<input type="hidden" name="scene_sketch_pages_fire" id="fpf_scene_sketch_pages_data">

<!-- ================================================================ -->
<!-- ========================= PAGE 7+ (PHOTOS) ==================== -->
<!-- ================================================================ -->

<!-- หน้าแรกรูปภาพ: มี header ข้อมูล + 3 ช่องรูป -->
<div class="fpf-page fpf-photo-page" data-photo-page="1">

    <div class="fpf-header">
        <div class="fpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="fpf-header-center">
            <div class="fpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="fpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับเพลิงไหม้</div>
            <div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ</div>
        </div>
        <div class="fpf-header-right">
            <div class="fpf-doc-box">
                <div class="fpf-doc-line">รายงานที่ <span class="fpf-rpt-no-mirror"></span> / 25<span class="fpf-rpt-year-mirror"></span></div>
                <div class="fpf-doc-line">หน้าที่ <span class="fpf-cur-page">7</span> / <span class="fpf-total-page">7</span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:8px;">
        <div class="fpf-fr" style="margin-bottom:6px;">
            <span class="fpf-fl">วันที่ตรวจสถานที่เกิดเหตุ</span>
            <input type="date" class="fpf-inp" name="photo_inspect_date_fire" style="text-align:center;">
            <span class="fpf-fl">เวลาประมาณ</span>
            <input type="time" class="fpf-inp" name="photo_inspect_time_fire" style="text-align:center;">
            <span class="fpf-fl">น.</span>
        </div>
        <div class="fpf-fr" style="flex-wrap:nowrap; margin-bottom:6px;">
            <span class="fpf-fl">รหัสภาพถ่ายที่</span>
            <input type="text" class="fpf-inp" name="photo_id_start_fire" style="min-width:40px;" readonly>
            <span class="fpf-fl">ถึง</span>
            <input type="text" class="fpf-inp" name="photo_id_end_fire" style="min-width:40px;" readonly>
            <span class="fpf-fl">จำนวน</span>
            <input type="text" class="fpf-inp-s" name="photo_amount_fire" style="max-width:40px; text-align:center;" readonly>
            <span class="fpf-fl">ภาพ</span>
        </div>
        <div style="font-size:10.5px; font-style:italic; margin-top:2px;">(ตามภาพถ่ายรวมที่แนบ)</div>
    </div>

    <!-- Drag & Drop zone for PDF form -->
    <div class="fpf-photo-dropzone" id="fpf_photo_dropzone">
        <i class="fas fa-cloud-upload-alt" style="font-size:1.2rem; color:#90a4ae;"></i>
        <div style="font-size:10px; color:#666; margin-top:2px;">ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือก</div>
    </div>
    <input type="file" id="fpf_photo_input_gallery" accept="image/*" multiple style="display:none;" onchange="fpfPreviewPhotos(this)">
    <input type="file" id="fpf_photo_input_camera" accept="image/*" capture="environment" multiple style="display:none;" onchange="fpfPreviewPhotos(this)">

    <!-- Photo Grid (35 photos per page, populated by JS) -->
    <div class="fpf-photo-grid" id="fpf_photo_grid_1"></div>

    <div class="fpf-footer" style="margin-top:auto;">
        <div class="fpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="fpf-footer-right" style="display:flex; flex-direction:column; align-items:flex-end; gap:2px;">
            <div style="display:flex; align-items:center; gap:4px; font-size:11px;">
                <span>ผู้จดบันทึก</span>
                <select class="fpf-sel" name="photographer_name_fire" id="fpf_photographer_name" style="width:180px; font-size:10px; padding:1px 2px;">
                    <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือก --', $inspectorOptionsFire) ?>
                </select>
            </div>
            <div style="display:flex; align-items:center; gap:4px; font-size:11px;">
                <span>วัน/เวลา</span>
                <input type="datetime-local" class="fpf-inp" name="photographer_datetime_fire" id="fpf_photographer_datetime" style="width:180px; font-size:10px; padding:1px 2px; text-align:center;">
            </div>
            <div style="font-size:9px; margin-top:2px;">F-CS-12 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>
        </div>
    </div>
</div>

<!-- Container สำหรับหน้ารูปถ่ายเพิ่มเติม (JS จะ append เข้าที่นี่) -->
<div id="fpf_extra_photo_pages"></div>

<!-- ปุ่มเพิ่มหน้ากระดาษรูปถ่าย -->
<button type="button" class="fpf-add-photo-page-btn" onclick="fpfAddPhotoPage()">
    <i class="fas fa-plus me-1"></i> เพิ่มหน้าบันทึกการถ่ายภาพ
                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <div>
                    <button type="button" class="btn btn-success btn-sm" id="btn_save_fire_pdf">
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
    window.fpfFireUpdatePageNumbers = function() {
        var pages = document.querySelectorAll('#fireFormPdfModal .fpf-page');
        var total = pages.length;
        pages.forEach(function(page, idx) {
            var curEl = page.querySelector('.fpf-cur-page');
            var totalEl = page.querySelector('.fpf-total-page');
            if (curEl) curEl.textContent = (idx + 1);
            if (totalEl) totalEl.textContent = total;
        });
    };
    fpfFireUpdatePageNumbers();

    // ===== Radio-toggle (mutually exclusive checkboxes) =====
    document.querySelectorAll('#fireFormPdfModal .fpf-radio-toggle').forEach(function(el) {
        el.addEventListener('change', function() {
            if (!this.checked) return;
            var group = this.getAttribute('data-group');
            document.querySelectorAll('#fireFormPdfModal .fpf-radio-toggle[data-group="' + group + '"]').forEach(function(cb) {
                if (cb !== el) cb.checked = false;
            });
        });
    });

    // ===== Inspector counter =====
    var fpfInspectorIdx = 1;
    window.fpfAddInspector = function() {
        fpfInspectorIdx++;
        var container = document.getElementById('fpf_inspector_container');
        var row = document.createElement('div');
        row.className = 'fpf-si fpf-inspector-row';
        row.innerHTML = '<span class="fpf-si-no">5.' + fpfInspectorIdx + '</span>' +
            '<select class="fpf-sel" name="fire_inspector_id[]">' +
            document.querySelector('#fpf_inspector_container select').innerHTML +
            '</select>' +
            ' <button type="button" class="fpf-del-btn" onclick="this.parentElement.remove()">×</button>';
        container.appendChild(row);
    };

    // ===== Victim rows =====
    window.fpfAddVictim = function() {
        var container = document.getElementById('fpf_victim_container');
        var row = document.createElement('div');
        row.className = 'fpf-victim-row';
        row.style.cssText = 'margin-top:3px; padding:2px 0; border-top:1px dotted #ccc;';
        row.innerHTML =
            '<div class="fpf-fr">' +
            '<span class="fpf-fl">ประเภท</span>' +
            '<select class="fpf-sel" name="fire_person_type[]" style="max-width:90px;">' +
            '<option value="">--เลือก--</option><option value="ผู้เสียหาย">ผู้เสียหาย</option>' +
            '<option value="ผู้บาดเจ็บ">ผู้บาดเจ็บ</option><option value="ผู้เสียชีวิต">ผู้เสียชีวิต</option></select>' +
            '<span class="fpf-fl" style="margin-left:6px;">ชื่อ</span>' +
            '<input type="text" class="fpf-inp" name="fire_person_name[]">' +
            '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="\u0e40\u0e02\u0e35\u0e22\u0e19\u0e14\u0e49\u0e27\u0e22\u0e25\u0e32\u0e22\u0e21\u0e37\u0e2d" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
            '<span class="fpf-fl" style="margin-left:4px;">อายุ</span>' +
            '<input type="text" class="fpf-inp-s" name="fire_person_age[]" style="max-width:30px;">' +
            '<span class="fpf-fl">ปี</span>' +
            ' <button type="button" class="fpf-del-btn" onclick="this.closest(\'.fpf-victim-row\').remove()">×</button>' +
            '</div>';
        container.appendChild(row);
    };

    // ===== Evidence table rows =====
    window.fpfAddEvidenceRow = function() {
        var tbody = document.getElementById('fpf_evidence_tbody');
        if (!tbody) return;
        var rowIdx = tbody.rows.length;
        var nextNum = rowIdx + 1;
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td style="width:40px; text-align:center; font-weight:600;">' + nextNum + '<input type="hidden" name="evidence_label_fire[]" value="' + nextNum + '"></td>' +
            '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_item_fire[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
            '<td><input type="text" name="evidence_level_1_fire_' + rowIdx + '" style="width:50px; text-align:center;" inputmode="decimal"></td>' +
            '<td><input type="text" name="evidence_level_2_fire_' + rowIdx + '" style="width:50px; text-align:center;" inputmode="decimal"></td>' +
            '<td><input type="text" name="evidence_level_3_fire_' + rowIdx + '" style="width:50px; text-align:center;" inputmode="decimal"></td>' +
            '<td><input type="text" name="evidence_level_4_fire_' + rowIdx + '" style="width:50px; text-align:center;" inputmode="decimal"></td>' +
            '<td><input type="text" name="evidence_azimuth_fire[]"></td>' +
            '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_remark_fire[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
            '<td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option></select><input type="hidden" class="lab-unit-value" name="evidence_lab_unit_fire[]" value=""></td>' +
            '<td><button type="button" class="fpf-del-btn" onclick="fpfDelRow(this)">×</button></td>';
        tbody.appendChild(tr);
    };

    // ===== Evidence collection rows =====
    window.fpfAddCollectionRow = function() {
        var tbody = document.getElementById('fpf_collection_tbody');
        if (!tbody) return;
        var rowIdx = tbody.rows.length;
        var count = rowIdx + 1;
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td style="text-align:center;">' + count + '</td>' +
            '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="measurement_item_fire[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
            '<td><input type="text" name="measurement_quantity_fire[]" style="width:30px; text-align:center;"></td>' +
            '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="measurement_area_fire[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
            '<td><input type="text" name="measurement_label_number_fire[]" style="width:30px; text-align:center;"></td>' +
            '<td><input type="checkbox" name="measurement_package_plastic_check_fire_' + rowIdx + '" value="1"></td>' +
            '<td><input type="checkbox" name="measurement_package_paper_check_fire_' + rowIdx + '" value="1"></td>' +
            '<td><input type="checkbox" name="measurement_package_other_check_fire_' + rowIdx + '" value="1"></td>' +
            '<td><input type="checkbox" name="measurement_action_return_check_fire_' + rowIdx + '" value="1"></td>' +
            '<td><input type="checkbox" name="measurement_action_other_check_fire_' + rowIdx + '" value="1"></td>' +
            '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="measurement_remark_fire[]" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
            '<td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option></select><input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_fire[]" value=""></td>' +
            '<td><button type="button" class="fpf-del-btn" onclick="fpfDelRow(this)">×</button></td>';
        tbody.appendChild(tr);
    };

    // ===== Delete table row =====
    window.fpfDelRow = function(btn) {
        var tbody = btn.closest('tbody');
        if (!tbody || tbody.rows.length <= 1) return;
        btn.closest('tr').remove();
        if (tbody.id === 'fpf_evidence_tbody') {
            fpfRenumberEvidence();
        } else if (tbody.id === 'fpf_collection_tbody') {
            fpfRenumberCollection();
        }
    };

    // ===== Renumber evidence rows (label + indexed level fields) =====
    window.fpfRenumberEvidence = function() {
        var tbody = document.getElementById('fpf_evidence_tbody');
        if (!tbody) return;
        Array.from(tbody.rows).forEach(function(row, idx) {
            var td = row.cells[0];
            var num = idx + 1;
            if (td) {
                var hiddenInput = td.querySelector('input[type="hidden"]');
                if (hiddenInput) {
                    hiddenInput.value = num;
                    if (td.firstChild && td.firstChild.nodeType === Node.TEXT_NODE) {
                        td.firstChild.textContent = num;
                    } else {
                        td.innerHTML = num + '<input type="hidden" name="evidence_label_fire[]" value="' + num + '">';
                    }
                } else {
                    td.innerHTML = num + '<input type="hidden" name="evidence_label_fire[]" value="' + num + '">';
                }
            }
            var lv1 = row.querySelector('input[name^="evidence_level_1_fire_"]');
            var lv2 = row.querySelector('input[name^="evidence_level_2_fire_"]');
            var lv3 = row.querySelector('input[name^="evidence_level_3_fire_"]');
            var lv4 = row.querySelector('input[name^="evidence_level_4_fire_"]');
            if (lv1) lv1.name = 'evidence_level_1_fire_' + idx;
            if (lv2) lv2.name = 'evidence_level_2_fire_' + idx;
            if (lv3) lv3.name = 'evidence_level_3_fire_' + idx;
            if (lv4) lv4.name = 'evidence_level_4_fire_' + idx;
        });
    };

    // ===== Renumber collection rows (indexed checkbox fields) =====
    window.fpfRenumberCollection = function() {
        var tbody = document.getElementById('fpf_collection_tbody');
        if (!tbody) return;
        Array.from(tbody.rows).forEach(function(row, idx) {
            var td = row.cells[0];
            if (td) td.textContent = String(idx + 1);
            var fields = [
                'measurement_package_plastic_check_fire_',
                'measurement_package_paper_check_fire_',
                'measurement_package_other_check_fire_',
                'measurement_action_return_check_fire_',
                'measurement_action_other_check_fire_'
            ];
            fields.forEach(function(prefix) {
                var el = row.querySelector('input[name^="' + prefix + '"]');
                if (el) el.name = prefix + idx;
            });
        });
    };

    // ===== Photo source chooser =====
    window.fpfFireChoosePhotoSource = function() {
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
                document.getElementById('fpf_photo_input_gallery').click();
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                document.getElementById('fpf_photo_input_camera').click();
            }
        });
    };

    // ===== Photo preview (เพิ่มเข้า attachmentStoreFire ที่แชร์กับฟอร์มมาตรฐาน) =====
    var FPF_PHOTOS_PER_PAGE = 35;

    window.fpfPreviewPhotos = function(input) {
        if (!input.files || !input.files.length) return;
        Array.from(input.files).forEach(function(file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var fileId = Date.now() + '_' + Math.random().toString(16).slice(2);
                var objectUrl = URL.createObjectURL(file);
                attachmentStoreFire.push({ file: file, id: fileId, src: objectUrl, base64: e.target.result, name: file.name });
                fpfRenderPhotosFromStore();
                if (typeof updateFireRealInput === 'function') updateFireRealInput();
            };
            reader.readAsDataURL(file);
        });
        input.value = '';
    };

    // ===== Dropzone drag & drop =====
    (function() {
        var dz = document.getElementById('fpf_photo_dropzone');
        if (!dz) return;
        dz.addEventListener('click', function() { fpfFireChoosePhotoSource(); });
        dz.addEventListener('dragover', function(e) { e.preventDefault(); dz.classList.add('dragover'); });
        dz.addEventListener('dragleave', function() { dz.classList.remove('dragover'); });
        dz.addEventListener('drop', function(e) {
            e.preventDefault(); dz.classList.remove('dragover');
            if (e.dataTransfer.files && e.dataTransfer.files.length) {
                var fakeInput = { files: e.dataTransfer.files };
                fpfPreviewPhotos(fakeInput);
            }
        });
    })();

    // ===== เพิ่มหน้ากระดาษรูปถ่าย =====
    var fpfPhotoPageCount = 1;
    window.fpfAddPhotoPage = function() {
        fpfPhotoPageCount++;
        var pageNum = fpfPhotoPageCount;
        var container = document.getElementById('fpf_extra_photo_pages');
        if (!container) return;

        var rptNo = document.getElementById('fpf_doc_no') ? document.getElementById('fpf_doc_no').value : '';
        var rptNoFull = document.getElementById('fpf_report_no') ? document.getElementById('fpf_report_no').value : '';
        var rptYear = rptNoFull ? (rptNoFull.split('/').pop() || '').toString().slice(-2) : '';

        var page = document.createElement('div');
        page.className = 'fpf-page fpf-photo-page';
        page.setAttribute('data-photo-page', pageNum);
        var photos = (typeof attachmentStoreFire !== 'undefined') ? attachmentStoreFire : [];
        var startIdx = (pageNum - 1) * FPF_PHOTOS_PER_PAGE;
        var endIdx = Math.min(pageNum * FPF_PHOTOS_PER_PAGE, photos.length);
        var startName = photos[startIdx] ? (photos[startIdx].name || 'photo') : '';
        var endName = photos[endIdx - 1] ? (photos[endIdx - 1].name || 'photo') : '';

        page.innerHTML =
            '<div class="fpf-header">' +
                '<div class="fpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>' +
                '<div class="fpf-header-center">' +
                    '<div class="fpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>' +
                    '<div class="fpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับเพลิงไหม้</div>' +
                    '<div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ (ต่อ)</div>' +
                '</div>' +
                '<div class="fpf-header-right">' +
                    '<div class="fpf-doc-box">' +
                        '<div class="fpf-doc-line">รายงานที่ <span class="fpf-rpt-no-mirror">' + rptNo + '</span> / 25<span class="fpf-rpt-year-mirror">' + rptYear + '</span></div>' +
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
            '</div>' +
            '<div style="text-align:right; margin-bottom:4px;">' +
                '<button type="button" class="fpf-del-btn" style="font-size:10px; padding:1px 8px;" onclick="fpfRemovePhotoPage(this)">× ลบหน้านี้</button>' +
            '</div>' +
            '<div class="fpf-photo-grid" id="fpf_photo_grid_' + pageNum + '"></div>' +
            '<div class="fpf-footer">' +
                '<div class="fpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>' +
                '<div class="fpf-footer-right">F-CS-12 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>' +
            '</div>';
        container.appendChild(page);
        fpfRenderPhotosFromStore();
        if (typeof fpfFireUpdatePageNumbers === 'function') fpfFireUpdatePageNumbers();
    };

    // ===== ลบหน้ากระดาษรูปถ่ายเพิ่มเติม =====
    window.fpfRemovePhotoPage = function(btn) {
        var page = btn.closest('.fpf-photo-page');
        if (page) {
            page.remove();
            fpfPhotoPageCount--;
            fpfRenderPhotosFromStore();
            if (typeof fpfFireUpdatePageNumbers === 'function') fpfFireUpdatePageNumbers();
        }
    };

    // ===== Render photos จาก attachmentStoreFire ลง grid ทุกหน้า (35 รูป/หน้า) =====
    window.fpfRenderPhotosFromStore = function() {
        var photos = attachmentStoreFire;
        var totalPhotos = photos.length;

        // ★ auto-add pages ถ้ารูปเกิน
        var pagesNeeded = Math.max(1, Math.ceil(totalPhotos / FPF_PHOTOS_PER_PAGE));
        var pagesNow = document.querySelectorAll('#fireFormPdfModal .fpf-photo-page').length;
        while (pagesNow < pagesNeeded) {
            fpfAddPhotoPage();
            pagesNow++;
        }

        // รวบรวม grid container ทั้งหมดจากทุกหน้า
        var allGrids = document.querySelectorAll('#fireFormPdfModal .fpf-photo-page .fpf-photo-grid');
        allGrids.forEach(function(grid, pageIdx) {
            grid.innerHTML = '';
            var startI = pageIdx * FPF_PHOTOS_PER_PAGE;
            var endI = Math.min(startI + FPF_PHOTOS_PER_PAGE, totalPhotos);
            for (var i = startI; i < endI; i++) {
                var item = photos[i];
                var cell = document.createElement('div');
                cell.style.position = 'relative';
                cell.innerHTML =
                    '<div class="fpf-photo-cell">' +
                        '<img src="' + (item.src || item.base64 || '') + '">' +
                        '<button type="button" class="fpf-cell-delete" onclick="fpfRemovePhoto(\'' + item.id + '\')">&times;</button>' +
                    '</div>' +
                    '<div class="fpf-cell-filename" title="' + (item.name || '') + '">' + (item.name || 'photo_' + (i+1)) + '</div>';
                grid.appendChild(cell);
            }
        });

        // อัพเดทจำนวนรูปอัตโนมัติ
        if (typeof updateFireRealInput === 'function') updateFireRealInput();
        fpfUpdatePhotoAmount();
    };

    window.fpfUpdatePhotoAmount = function() {
        var form = document.getElementById('fireFormPdf');
        if (!form) return;

        var startInput = form.querySelector('input[name="photo_id_start_fire"]');
        var endInput = form.querySelector('input[name="photo_id_end_fire"]');
        var amountInput = form.querySelector('input[name="photo_amount_fire"]');
        if (!startInput || !endInput || !amountInput) return;

        var totalPhotos = (typeof attachmentStoreFire !== 'undefined' && Array.isArray(attachmentStoreFire)) ? attachmentStoreFire.length : 0;
        if (totalPhotos > 0) {
            var lastIdxPage1 = Math.min(FPF_PHOTOS_PER_PAGE, totalPhotos) - 1;
            startInput.value = attachmentStoreFire[0].name || 'photo';
            endInput.value = attachmentStoreFire[lastIdxPage1].name || 'photo';
            amountInput.value = String(totalPhotos);
        } else {
            startInput.value = '';
            endInput.value = '';
            amountInput.value = '';
        }
    };

    // ===== ลบรูปจาก store (PDF form) =====
    window.fpfRemovePhoto = function(fileId) {
        var item = attachmentStoreFire.find(function(x) { return x.id === fileId; });
        if (item && item.existing && item.db_file_id) {
            deletedExistingPhotosFire.push(item.db_file_id);
        }
        attachmentStoreFire = attachmentStoreFire.filter(function(x) { return x.id !== fileId; });
        fpfRenderPhotosFromStore();
        if (typeof updateFireRealInput === 'function') updateFireRealInput();
    };

    // ===== ตรวจสอบว่า canvas ว่างหรือไม่ =====
    function fpfIsCanvasBlank(canvas) {
        var ctx = canvas.getContext('2d');
        var data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        for (var i = 3; i < data.length; i += 4) {
            if (data[i] !== 0) return false;
        }
        return true;
    }

    // ===== Simple canvas drawing =====
    window.fpfClearCanvas = function(canvasId) {
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
                        }
                        });
                            return;
                    } else {
                        if (!confirm('คุณต้องการล้างภาพทั้งหมดหรือไม่?')) {
                            return;
                        }
                    }
        }
        if (typeof sketchSetEraser === 'function') sketchSetEraser(canvasId, false);
    };

    function initCanvas(canvasId) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || canvas.dataset.fpfInit === '1') return;
        canvas.dataset.fpfInit = '1';
        
        var ctx = canvas.getContext('2d');
        canvas.width = canvas.offsetWidth || canvas.parentElement.offsetWidth;
        canvas.height = canvas.offsetHeight || parseInt(canvas.style.height) || 500;
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        var drawing = false;
        var currentStroke = null;
        
        // ใช้ global sketch variables (เหมือน sketch-tools.js)
        if (!window._sketchColor) window._sketchColor = {};
        if (!window._sketchOrigColor) window._sketchOrigColor = {};
        if (!window._sketchEraser) window._sketchEraser = {};
        if (!window._sketchPenSize) window._sketchPenSize = {};
        if (!window._bpfSketchStrokes) window._bpfSketchStrokes = {};
        
        window._sketchColor[canvasId] = window._sketchColor[canvasId] || '#000';
        window._sketchOrigColor[canvasId] = '#000';
        window._sketchEraser[canvasId] = false;
        window._sketchPenSize[canvasId] = window._sketchPenSize[canvasId] || 2;
        window._bpfSketchStrokes[canvasId] = [];

        function getPos(e) {
            var rect = canvas.getBoundingClientRect();
            var sx = canvas.width / rect.width, sy = canvas.height / rect.height;
            return { x: e.offsetX * sx, y: e.offsetY * sy };
        }
        function getTouchPos(e) {
            var t = e.touches[0];
            var r = canvas.getBoundingClientRect();
            var sx = canvas.width / r.width, sy = canvas.height / r.height;
            return { x: (t.clientX - r.left) * sx, y: (t.clientY - r.top) * sy };
        }
        function start(e, pos) {
            e.preventDefault(); drawing = true;
            var isEraser = !!window._sketchEraser[canvasId];
            var color = window._sketchColor[canvasId] || '#000';
            var w = window._sketchPenSize[canvasId] || 2;
            currentStroke = { eraser: isEraser, color: color, width: isEraser ? 20 : w, points: [pos] };
            ctx.beginPath(); ctx.moveTo(pos.x, pos.y);
            ctx.globalCompositeOperation = isEraser ? 'destination-out' : 'source-over';
            if (!isEraser) ctx.strokeStyle = color;
            ctx.lineWidth = isEraser ? 20 : w;
            ctx.lineCap = 'round'; ctx.lineJoin = 'round';
        }
        function move(e, pos) {
            if (!drawing) return; e.preventDefault();
            if (currentStroke) currentStroke.points.push(pos);
            ctx.lineTo(pos.x, pos.y); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(pos.x, pos.y);
        }
        function end() {
            if (!drawing) return; drawing = false;
            ctx.globalCompositeOperation = 'source-over';
            if (currentStroke && currentStroke.points.length > 0) {
                window._bpfSketchStrokes[canvasId].push(currentStroke);
            }
            currentStroke = null;
        }

        canvas.addEventListener('mousedown', function(e) { start(e, getPos(e)); });
        canvas.addEventListener('mousemove', function(e) { move(e, getPos(e)); });
        canvas.addEventListener('mouseup', end);
        canvas.addEventListener('mouseleave', end);
        canvas.addEventListener('touchstart', function(e) { start(e, getTouchPos(e)); }, {passive:false});
        canvas.addEventListener('touchmove', function(e) { move(e, getTouchPos(e)); }, {passive:false});
        canvas.addEventListener('touchend', end);
    }

    // ===== Auto-fill position for PDF form receiver/sender =====
    document.addEventListener('change', function(e) {
        var target = e.target;
        if (target.id !== 'fpf_receiver_name' && target.id !== 'fpf_sender_name') return;
        var idEmp = target.value;
        var posInputId = (target.id === 'fpf_receiver_name') ? 'fpf_receiver_position' : 'fpf_sender_position';
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

    // ===== Multi-page Sketch Engine =====
    var SKETCH_W = 1120, SKETCH_H = 660;
    var _sketchPageCounter = 1;
    window._fpfSketchPages = window._fpfSketchPages || [];

    // Register first page
    window._fpfSketchPages.push({
        id: 'fpf_sketch_page_1',
        canvasId: 'fpf_sketch_page_1_canvas',
        bgImage: null,
        bgImageData: null,
        _bgImgEl: null
    });

    function _initSketchDraw(canvasId) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || canvas.dataset.fpfInit === '1') return;
        canvas.dataset.fpfInit = '1';
        
        var ctx = canvas.getContext('2d');
        var drawing = false;
        var currentStroke = null;
        
        if (!window._sketchColor) window._sketchColor = {};
        if (!window._sketchOrigColor) window._sketchOrigColor = {};
        if (!window._sketchEraser) window._sketchEraser = {};
        if (!window._sketchPenSize) window._sketchPenSize = {};
        if (!window._bpfSketchStrokes) window._bpfSketchStrokes = {};
        
        window._sketchColor[canvasId] = window._sketchColor[canvasId] || '#000';
        window._sketchOrigColor[canvasId] = '#000';
        window._sketchEraser[canvasId] = false;
        window._sketchPenSize[canvasId] = window._sketchPenSize[canvasId] || 2;
        window._bpfSketchStrokes[canvasId] = [];

        function getPos(e) {
            var rect = canvas.getBoundingClientRect();
            var sx = canvas.width / rect.width, sy = canvas.height / rect.height;
            return { x: e.offsetX * sx, y: e.offsetY * sy };
        }
        function getTouchPos(e) {
            var t = e.touches[0];
            var r = canvas.getBoundingClientRect();
            var sx = canvas.width / r.width, sy = canvas.height / r.height;
            return { x: (t.clientX - r.left) * sx, y: (t.clientY - r.top) * sy };
        }
        function start(e, pos) {
            e.preventDefault(); drawing = true;
            var isEraser = !!window._sketchEraser[canvasId];
            var color = window._sketchColor[canvasId] || '#000';
            var w = window._sketchPenSize[canvasId] || 2;
            currentStroke = { eraser: isEraser, color: color, width: isEraser ? 20 : w, points: [pos] };
            ctx.beginPath(); ctx.moveTo(pos.x, pos.y);
            ctx.globalCompositeOperation = isEraser ? 'destination-out' : 'source-over';
            if (!isEraser) ctx.strokeStyle = color;
            ctx.lineWidth = isEraser ? 20 : w;
            ctx.lineCap = 'round'; ctx.lineJoin = 'round';
        }
        function move(e, pos) {
            if (!drawing) return; e.preventDefault();
            if (currentStroke) currentStroke.points.push(pos);
            ctx.lineTo(pos.x, pos.y); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(pos.x, pos.y);
        }
        function end() {
            if (!drawing) return; drawing = false;
            ctx.globalCompositeOperation = 'source-over';
            if (currentStroke && currentStroke.points.length > 0) {
                window._bpfSketchStrokes[canvasId].push(currentStroke);
            }
            currentStroke = null;
        }

        canvas.addEventListener('mousedown', function(e) { start(e, getPos(e)); });
        canvas.addEventListener('mousemove', function(e) { move(e, getPos(e)); });
        canvas.addEventListener('mouseup', end);
        canvas.addEventListener('mouseleave', end);
        canvas.addEventListener('touchstart', function(e) { start(e, getTouchPos(e)); }, {passive:false});
        canvas.addEventListener('touchmove', function(e) { move(e, getTouchPos(e)); }, {passive:false});
        canvas.addEventListener('touchend', end);
    }

    window.fpfSketchAddPage = function() {
        _sketchPageCounter++;
        var n = _sketchPageCounter;
        var pid = 'fpf_sketch_page_' + n;
        var canvasId = pid + '_canvas';
        var pageData = { id: pid, canvasId: canvasId, bgImage: null, bgImageData: null, _bgImgEl: null };
        var pageDiv = document.createElement('div');
        pageDiv.className = 'fpf-page fpf-sketch-page';
        pageDiv.id = pid;
        pageDiv.setAttribute('data-sketch-page', n);
        pageDiv.innerHTML =
            '<div class="fpf-header">' +
                '<div class="fpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>' +
                '<div class="fpf-header-center">' +
                    '<div class="fpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>' +
                    '<div class="fpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับเพลิงไหม้</div>' +
                '</div>' +
                '<div class="fpf-header-right">' +
                    '<div class="fpf-doc-box">' +
                        '<div class="fpf-doc-line">รายงานที่ <span class="fpf-rpt-no-mirror"></span> / 25<span class="fpf-rpt-year-mirror"></span></div>' +
                        '<div class="fpf-doc-line">หน้าที่ <span class="fpf-cur-page"></span> / <span class="fpf-total-page"></span></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +

            '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">' +
                '<div></div>' +
                '<div style="font-size:13px; font-weight:600;">แผนผังสังเขป (ต่อ)</div>' +
                '<div style="display:flex; gap:4px; align-items:center;">' +
                    '<label class="fpf-add-btn" style="padding:2px 8px; cursor:pointer; margin:0;" title="แนบรูปภาพพื้นหลัง">' +
                        '<i class="fas fa-image me-1"></i> แนบรูป' +
                        '<input type="file" accept="image/*" style="display:none;" onchange="fpfSketchAttachImage(\'' + pid + '\',this)">' +
                    '</label>' +
                    '<button type="button" class="fpf-add-btn" style="padding:2px 8px;" onclick="fpfSketchRemoveBg(\'' + pid + '\')" title="ลบรูปพื้นหลัง"><i class="fas fa-times"></i> ลบรูป</button>' +
                    '<button type="button" class="fpf-add-btn" style="padding:2px 8px; border-color:#dc3545; color:#dc3545;" onclick="fpfSketchRemovePage(\'' + pid + '\')" title="ลบหน้านี้"><i class="fas fa-trash-alt"></i> ลบหน้า</button>' +
                '</div>' +
            '</div>' +

            '<div class="fpf-sketch-viewport" id="' + pid + '_viewport">' +
                '<div class="fpf-sketch-canvas-wrap" id="' + pid + '_wrap" style="aspect-ratio:' + SKETCH_W + '/' + SKETCH_H + ';">' +
                    '<canvas id="' + canvasId + '" width="' + SKETCH_W + '" height="' + SKETCH_H + '"></canvas>' +
                '</div>' +
                '<div class="fpf-not-to-scale">* NOT TO SCALE</div>' +
            '</div>' +

            '<div style="text-align:right; margin-top:4px; display:flex; justify-content:flex-end; gap:6px; align-items:center;">' +
                '<button type="button" class="fpf-add-btn" onclick="sketchUndo(\'' + canvasId + '\')" style="padding:2px 8px;"><i class="fas fa-undo"></i> ย้อนกลับ</button>' +
                '<button type="button" class="fpf-add-btn" id="' + canvasId + '_eraser_btn" onclick="sketchToggleEraser(\'' + canvasId + '\')" style="padding:2px 8px;" title="ยางลบ"><i class="fas fa-eraser"></i></button>' +
                '<div style="display:flex;align-items:center;gap:4px;">' +
                    '<i class="fas fa-pen" style="font-size:10px;color:#666;"></i>' +
                    '<input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize(\'' + canvasId + '\',this.value);this.nextElementSibling.textContent=this.value+\'px\'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">' +
                    '<span style="font-size:11px;color:#666;min-width:35px;">2px</span>' +
                '</div>' +
                '<label style="margin:0;cursor:pointer;" title="เลือกสี"><input type="color" value="#000000" onchange="sketchSetColor(\'' + canvasId + '\',this.value)" style="width:26px;height:22px;border:1px solid #aaa;border-radius:3px;padding:0;cursor:pointer;"></label>' +
                '<button type="button" class="fpf-add-btn" onclick="fpfClearCanvas(\'' + canvasId + '\')">ล้างกระดาน</button>' +
            '</div>' +

            '<div style="margin-bottom:8px;">' +
                '<div class="fpf-fr"><span class="fpf-fl">หมายเหตุ</span></div>' +
                '<textarea class="fpf-ta" rows="6"></textarea>' +
            '</div>' +

            '<div class="fpf-footer">' +
                '<div class="fpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>' +
                '<div class="fpf-footer-right">F-CS-12 แก้ไขครั้งที่ 1<br>เริ่มใช้ 1 ต.ค. 63</div>' +
            '</div>';

        var allSketchPages = document.querySelectorAll('#fireFormPdfModal .fpf-sketch-page');
        var lastSketchPage = allSketchPages[allSketchPages.length - 1];
        if (lastSketchPage && lastSketchPage.nextElementSibling) {
            lastSketchPage.parentNode.insertBefore(pageDiv, lastSketchPage.nextElementSibling);
        } else {
            var body = document.querySelector('#fireFormPdfModal .fpf-body');
            if (body) body.appendChild(pageDiv);
        }
        window._fpfSketchPages.push(pageData);
        _initSketchDraw(canvasId);
        if (typeof fpfFireUpdatePageNumbers === 'function') fpfFireUpdatePageNumbers();
        pageDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.fpfSketchRemovePage = function(pid) {
        if (pid === 'fpf_sketch_page_1') return;
        var idx = window._fpfSketchPages.findIndex(function(p) { return p.id === pid; });
        if (idx !== -1) window._fpfSketchPages.splice(idx, 1);
        var pageDiv = document.getElementById(pid);
        if (pageDiv) pageDiv.remove();
        if (typeof fpfFireUpdatePageNumbers === 'function') fpfFireUpdatePageNumbers();
    };

    window.fpfSketchAttachImage = function(pid, input) {
        var file = input.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function(ev) {
            var pageData = window._fpfSketchPages.find(function(p) { return p.id === pid; });
            if (!pageData) return;
            pageData.bgImageData = ev.target.result;
            var img = new Image();
            img.onload = function() {
                pageData.bgImage = img;
                pageData._bgImgEl = img;
                var wrap = document.getElementById(pid + '_wrap');
                if (wrap) {
                    var existing = wrap.querySelector('.fpf-sketch-bg-img');
                    if (existing) existing.remove();
                    var bgImg = document.createElement('img');
                    bgImg.className = 'fpf-sketch-bg-img';
                    bgImg.src = ev.target.result;
                    wrap.insertBefore(bgImg, wrap.firstChild);
                }
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
        input.value = '';
    };

    window.fpfSketchRemoveBg = function(pid) {
        var pageData = window._fpfSketchPages.find(function(p) { return p.id === pid; });
        if (!pageData) return;
        pageData.bgImage = null;
        pageData.bgImageData = null;
        pageData._bgImgEl = null;
        var wrap = document.getElementById(pid + '_wrap');
        if (wrap) {
            var existing = wrap.querySelector('.fpf-sketch-bg-img');
            if (existing) existing.remove();
        }
    };

    window.fpfCollectSketchPagesData = function() {
        var pagesData = [];
        window._fpfSketchPages.forEach(function(p) {
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
            pagesData.push({
                pageId: p.id,
                dataUrl: tmpCanvas.toDataURL('image/png'),
                bgImageName: p.bgImage || null
            });
        });
        var inp = document.getElementById('fpf_scene_sketch_pages_data');
        if (inp) inp.value = JSON.stringify(pagesData);
        var legacyInp = document.getElementById('fpf_scene_sketch_data');
        if (legacyInp && pagesData.length > 0) legacyInp.value = pagesData[0].dataUrl;
        return pagesData;
    };

    // ===== Init on modal shown =====
    var modalEl = document.getElementById('fireFormPdfModal');
    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', function() {
            // Init multi-page sketch (first page)
            _initSketchDraw('fpf_sketch_page_1_canvas');
            // Init signature canvases
            initCanvas('fpf_sig_receiver');
            initCanvas('fpf_sig_sender');

            // ★ Auto-fill พฤติการณ์คดี from basic_info (rn_ReceiveNoti)
            var dstBehavior = document.getElementById('fpf_case_behavior');
            if (dstBehavior && !dstBehavior.value && window._basicInfoFire) {
                dstBehavior.value = window._basicInfoFire;
            }

            // แสดงเลขที่เอกสาร / เลขรายงาน
            var docNo  = document.getElementById('fpf_doc_no')  ? document.getElementById('fpf_doc_no').value  : '';
            var rptNo  = document.getElementById('fpf_report_no') ? document.getElementById('fpf_report_no').value : '';
            var rptParts = (rptNo || '').split('/');
            var rptNum = rptParts[0] || docNo;
            var rptYear = (rptParts[1] || '').toString().slice(-2);

            var rptDisplay  = document.getElementById('fpf_report_no_display');
            var yearDisplay = document.getElementById('fpf_report_year_display');
            if (rptDisplay) rptDisplay.textContent = rptNum;
            if (yearDisplay) yearDisplay.textContent = rptYear;

            // mirror หน้าที่ 2-7
            modalEl.querySelectorAll('.fpf-rpt-no-mirror').forEach(function(el)   { el.textContent = rptNum; });
            modalEl.querySelectorAll('.fpf-rpt-year-mirror').forEach(function(el) { el.textContent = rptYear; });

            // ตั้งค่า case_doc_no ถ้ายังว่าง
            var caseDocEl = document.getElementById('fpf_case_doc_no');
            if (caseDocEl && !caseDocEl.value && docNo) caseDocEl.value = (window.toThaiDocNo ? window.toThaiDocNo(docNo) : docNo);
            
            // Render photos from store
            if (typeof fpfRenderPhotosFromStore === 'function') {
                fpfRenderPhotosFromStore();
            }
            fpfUpdatePhotoAmount();
        });
    }

    // ===== Save handler (BLOB approach) =====
    document.getElementById('btn_save_fire_pdf').addEventListener('click', async function() {
        var form = document.getElementById('fireFormPdf');
        
        // ★ Collect multi-page sketch data before creating FormData
        if (typeof fpfCollectSketchPagesData === 'function') {
            fpfCollectSketchPagesData();
        }
        
        var formData = new FormData(form);

        // ลบ file input เดิม + hidden signature base64 ออกจาก FormData
        formData.delete('incident_photos_fire[]');
        formData.delete('scene_sketch_data_fire');
        formData.delete('scene_sketch_pages_fire');
        formData.delete('receiver_signature_data_fire');
        formData.delete('sender_signature_data_fire');

        // Add form_mode flag
        formData.append('form_mode', 'pdf_form');

        // ★ แปลง canvas เป็น Blob แล้ว append เป็นไฟล์
        // Sketch: ใช้ข้อมูลจาก hidden input (multi-page)
        var sketchDataInp = document.getElementById('fpf_scene_sketch_data');
        if (sketchDataInp && sketchDataInp.value) {
            var sketchBlob = await dataURLtoBlob(sketchDataInp.value);
            if (sketchBlob) {
                formData.append('sig_file_scene_sketch', sketchBlob, 'scene_sketch.png');
            }
        }
        
        // Signatures
        var sigCanvasMap = {
            'receiver_signature': 'fpf_sig_receiver',
            'sender_signature': 'fpf_sig_sender'
        };
        for (var key in sigCanvasMap) {
            var canvas = document.getElementById(sigCanvasMap[key]);
            if (canvas && !fpfIsCanvasBlank(canvas)) {
                var blob = await canvasToBlob(canvas, 'image/png');
                if (blob) {
                    formData.append('sig_file_' + key, blob, key + '.png');
                }
            }
        }

        // ★ Append รูปใหม่จาก attachmentStoreFire
        attachmentStoreFire.forEach(function(item) {
            if (item.file) {
                formData.append('incident_photos_fire[]', item.file);
            }
        });

        // ★ Append รายการ file_id ที่ต้องลบ
        if (deletedExistingPhotosFire.length > 0) {
            formData.append('deleted_photo_file_ids', JSON.stringify(deletedExistingPhotosFire));
        }

        var btn = this;
        var btnOriginalHtml = '<i class="fas fa-save me-1"></i> บันทึกข้อมูล';

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

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> กำลังบันทึก...';

        // ★ เช็คเน็ตก่อนส่ง (Offline Mode — เหมือนเรื่องทรัพย์)
        var fireUrl = './api/incidentCheckList/saveFire.php';
        if (!navigator.onLine) {
            btn.disabled = false;
            btn.innerHTML = btnOriginalHtml;
            if (typeof saveChecklistOffline === 'function') {
                await saveChecklistOffline(formData, fireUrl, '#fireFormPdfModal');
            }
            return;
        }
        if (typeof checkBackendHealth === 'function') {
            var backendOk = await checkBackendHealth();
            if (!backendOk) {
                btn.disabled = false;
                btn.innerHTML = btnOriginalHtml;
                if (typeof saveChecklistOffline === 'function') {
                    await saveChecklistOffline(formData, fireUrl, '#fireFormPdfModal');
                }
                return;
            }
        }

        fetch(fireUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.status === 'success' || data.success) {
                Swal.fire({ icon: 'success', title: 'สำเร็จ', text: data.message || 'บันทึกข้อมูลเรียบร้อย', confirmButtonText: 'ตกลง' }).then(function() {
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                    if (typeof loadData === 'function') loadData();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: data.message || 'ไม่สามารถบันทึกข้อมูลได้', confirmButtonText: 'ตกลง' });
            }
        })
        .catch(async function(err) {
            console.error(err);
            // ★ ส่งไม่ได้ → fallback Offline (เหมือนเรื่องทรัพย์)
            if (typeof saveChecklistOffline === 'function') {
                await saveChecklistOffline(formData, fireUrl, '#fireFormPdfModal');
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', confirmButtonText: 'ตกลง' });
            }
        })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = btnOriginalHtml;
        });
    });

    // ===== Show Print/Download Modal (ใช้ SweetAlert2) =====
    window.fpfShowPrintDownloadModal = function() {
        var incidentId = document.getElementById('fpf_incident_id')?.value || 
                         document.getElementById('incident_id_fire')?.value || '';
        if (!incidentId) {
            Swal.fire({ icon: 'warning', title: 'ไม่พบ incident_id', text: 'กรุณาบันทึกข้อมูลก่อนพิมพ์/ดาวน์โหลด' });
            return;
        }
        Swal.fire({
            title: '<i class="fas fa-file-pdf text-primary me-2"></i> เลือกรูปแบบ',
            html: '<p class="mb-3">เลือกรูปแบบที่ต้องการ</p>',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: '<i class="fas fa-print me-1"></i> พิมพ์',
            denyButtonText: '<i class="fas fa-download me-1"></i> ดาวน์โหลด PDF',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#0d6efd',
            denyButtonColor: '#198754',
            reverseButtons: false
        }).then(function(result) {
            if (result.isConfirmed) {
                fpfDoPrint();
            } else if (result.isDenied) {
                fpfDoDownload();
            }
        });
    };

    // ===== Print PDF =====
    window.fpfDoPrint = function() {
        var incidentId = document.getElementById('fpf_incident_id')?.value || 
                         document.getElementById('incident_id_fire')?.value || '';
        var printWin = window.open('/csims/api/incidentCheckList/gen_pdf_fire_html.php?incident_id=' + incidentId, '_blank');
        if (printWin) {
            printWin.onload = function() { printWin.print(); };
        }
    };

    // ===== Download PDF =====
    window.fpfDoDownload = function() {
        var incidentId = document.getElementById('fpf_incident_id')?.value || 
                         document.getElementById('incident_id_fire')?.value || '';
        window.location.href = '/csims/api/incidentCheckList/gen_pdf_fire_download.php?incident_id=' + incidentId;
    };
})();
</script>
