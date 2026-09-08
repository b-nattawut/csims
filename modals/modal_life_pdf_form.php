<?php
/**
 * Modal: ฟอร์ม PDF คดีเกี่ยวกับชีวิต (แบบกรอกข้อมูล)
 * หน้าตาเหมือนฟอร์ม PDF เป๊ะ แต่กรอกข้อมูลได้
 * Prefix ID: lpf_ (life pdf form)
 * อ้างอิงจาก modal_fire_pdf_form.php
 */

// ดึง police station options
$lpfPoliceStationOptions = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qryLpfPS = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stmtLpfPS = $pdo->query($qryLpfPS);
    while ($rowPS = $stmtLpfPS->fetch(PDO::FETCH_ASSOC)) {
        $lpfPoliceStationOptions .= '<option value="' . htmlspecialchars($rowPS['station_name']) . '">' . htmlspecialchars($rowPS['station_name']) . '</option>';
    }
}

// ดึงรายชื่อผู้ตรวจ
$lpfInspectorOptions = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
if (isset($pdo)) {
    $qryLpfInsp = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                   FROM user_profile t1 
                   LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                   ORDER BY t1.user_id DESC";
    $stmtLpfInsp = $pdo->query($qryLpfInsp);
    while ($rowInsp = $stmtLpfInsp->fetch(PDO::FETCH_ASSOC)) {
        $lpfInspectorOptions .= '<option value="' . $rowInsp['user_id'] . '">' . htmlspecialchars($rowInsp['fullname']) . '</option>';
    }
}

$lpfTodayDate = date('Y-m-d');
$lpfTodayTime = date('H:i');
?>

<!-- ===== SCOPED CSS ===== -->
<style>
/* hw-pen button: ใช้ใน flex row (lpf-fr, lpf-cg) */
#lifeFormPdfModal .btn-hw-open {
    flex-shrink: 0; width: 20px; padding: 0; margin: 0;
    border: none; background: none; color: #6366f1;
    font-size: 0.7rem; cursor: pointer; line-height: 1; text-align: center;
}
#lifeFormPdfModal .btn-hw-open:hover { color: #4338ca; transform: scale(1.15); }
/* wrapper สำหรับ lpf-inp-full / textarea ที่เป็น block */
#lifeFormPdfModal .lpf-hw-wrap {
    display: flex; align-items: flex-start; width: 100%; margin-bottom: 3px;
}
#lifeFormPdfModal .lpf-hw-wrap > .lpf-inp-full,
#lifeFormPdfModal .lpf-hw-wrap > .lpf-ta {
    flex: 1; width: 0; margin-bottom: 0;
}

#lifeFormPdfModal .btn-purple {
    background-color: #8b5cf6;
    border-color: #8b5cf6;
    color: #fff;
}
#lifeFormPdfModal .btn-purple:hover {
    background-color: #7c3aed;
    border-color: #7c3aed;
    color: #fff;
}

#lifeFormPdfModal .lpf-rpt-no-mirror,
#lifeFormPdfModal .lpf-rpt-year-mirror {
    display: inline-block;
    border-bottom: 1px dotted #888;
    text-align: center;
}
#lifeFormPdfModal .lpf-rpt-no-mirror { min-width: 60px; }
#lifeFormPdfModal .lpf-rpt-year-mirror { min-width: 30px; }

#lifeFormPdfModal .lpf-body {
    background: #bbb;
    padding: 10px 0;
}

#lifeFormPdfModal .lpf-page {
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
#lifeFormPdfModal .lpf-header {
    position: relative; margin-bottom: 6px; height: 70px;
}
#lifeFormPdfModal .lpf-header-logo {
    position: absolute; left: 0; top: -5px; width: 70px; height: 70px;
}
#lifeFormPdfModal .lpf-header-logo img {
    width: 70px; height: 70px; object-fit: contain;
}
#lifeFormPdfModal .lpf-header-center {
    position: absolute; left: 80px; right: 180px; top: 8px; text-align: center;
}
#lifeFormPdfModal .lpf-header-center .lpf-title-main {
    font-size: 14px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 5px;
}
#lifeFormPdfModal .lpf-header-center .lpf-title-sub {
    font-size: 11.5px; font-weight: 600; margin-top: 3px;
}
#lifeFormPdfModal .lpf-header-right {
    position: absolute; right: 0; top: 7px;
}
#lifeFormPdfModal .lpf-doc-box {
    border: 1.5px solid #000; padding: 3px 8px; font-size: 11px; white-space: nowrap;
}
#lifeFormPdfModal .lpf-doc-box .lpf-doc-line { line-height: 1.5; }

/* ===== BODY TABLE ===== */
#lifeFormPdfModal .lpf-form-body {
    display: flex; border: 1.5px solid #000; align-items: stretch;
}
#lifeFormPdfModal .lpf-col-left {
    width: 50%; border-right: 1.5px solid #000; display: flex; flex-direction: column;
}
#lifeFormPdfModal .lpf-col-right {
    width: 50%; display: flex; flex-direction: column;
}
#lifeFormPdfModal .lpf-row-header {
    display: flex; border-bottom: 1px solid #000;
    font-weight: 700; font-size: 11px; text-align: center; background: transparent;
}
#lifeFormPdfModal .lpf-row-header .lpf-lbl-seq {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000; padding: 1px 2px;
}
#lifeFormPdfModal .lpf-row-header .lpf-lbl-data {
    flex: 1; padding: 1px 2px;
}
#lifeFormPdfModal .lpf-sec-row {
    display: flex; border-bottom: 1px solid #000;
}
#lifeFormPdfModal .lpf-sec-row:last-child { border-bottom: none; }
#lifeFormPdfModal .lpf-sec-label {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000;
    padding: 3px 3px; font-weight: 700; font-size: 11px; text-align: center;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
#lifeFormPdfModal .lpf-sec-label .lpf-sec-num { font-size: 13px; font-weight: 700; line-height: 1.2; }
#lifeFormPdfModal .lpf-sec-label .lpf-sec-txt { font-size: 10px; font-weight: 600; line-height: 1.15; text-align: center; margin-top: 1px; }
#lifeFormPdfModal .lpf-sec-body { flex: 1; padding: 5px 6px; font-size: 11.5px; }

/* ===== FIELD ROW ===== */
#lifeFormPdfModal .lpf-fr { display: flex; flex-wrap: wrap; align-items: center; margin-bottom: 6px; line-height: 1.8; }
#lifeFormPdfModal .lpf-fl { font-size: 11.5px; white-space: nowrap; margin-right: 4px; }
#lifeFormPdfModal .lpf-fl-b { font-size: 11.5px; font-weight: 600; white-space: nowrap; margin-right: 4px; }

/* ===== INPUT FIELDS ===== */
#lifeFormPdfModal .lpf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px;
}
#lifeFormPdfModal .lpf-inp-m {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 60px; margin: 0 2px; text-align: center;
}
#lifeFormPdfModal .lpf-inp-full {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    width: 100%; display: block; margin-bottom: 3px;
}
#lifeFormPdfModal .lpf-inp-s {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    min-width: 15px; max-width: 50px; margin: 0 2px; flex: 0 1 40px; text-align: center;
}
#lifeFormPdfModal .lpf-sel {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px; cursor: pointer;
}
#lifeFormPdfModal .lpf-ta {
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
#lifeFormPdfModal .lpf-cb {
    appearance: none; -webkit-appearance: none;
    width: 13px; height: 13px; border: 1.5px solid #000;
    margin-right: 3px; cursor: pointer; position: relative;
    vertical-align: middle; flex-shrink: 0; background: #fff;
}
#lifeFormPdfModal .lpf-cb:checked::after {
    content: '✓'; font-size: 12px; font-weight: 700;
    position: absolute; top: -3px; left: 0px; color: #000;
}
#lifeFormPdfModal .lpf-ck {
    display: inline-flex; align-items: center; margin-right: 14px;
    font-size: 11.5px; white-space: nowrap; vertical-align: middle; cursor: pointer;
}
#lifeFormPdfModal .lpf-bk {
    width: 10px; height: 10px; background: #000;
    display: inline-block; margin-right: 3px; flex-shrink: 0; position: relative; top: 1px;
}
#lifeFormPdfModal .lpf-bh {
    display: flex; align-items: center; font-weight: 600;
    font-size: 11.5px; margin-top: 8px; margin-bottom: 5px;
}
#lifeFormPdfModal .lpf-si { display: flex; align-items: center; font-size: 11.5px; line-height: 1.8; margin-bottom: 5px; }
#lifeFormPdfModal .lpf-si-no { min-width: 25px; padding-left: 8px; font-size: 11.5px; }
#lifeFormPdfModal .lpf-cg { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; margin-bottom: 5px; }
#lifeFormPdfModal .lpf-i1 { padding-left: 15px; }
#lifeFormPdfModal .lpf-i2 { padding-left: 28px; }

/* ===== FOOTER ===== */
#lifeFormPdfModal .lpf-footer {
    margin-top: auto; font-size: 9.5px; color: #333;
    display: flex; justify-content: space-between; align-items: flex-end; flex-shrink: 0;
}
#lifeFormPdfModal .lpf-footer-left { flex: 1; }
#lifeFormPdfModal .lpf-footer-right { text-align: right; white-space: nowrap; line-height: 1.3; }

/* ===== Buttons ===== */
#lifeFormPdfModal .lpf-add-btn {
    font-size: 10px; padding: 1px 8px; border: 1px dashed #888;
    background: #f8f8f8; cursor: pointer; color: #333; margin: 3px 0; font-family: 'Sarabun', sans-serif;
}
#lifeFormPdfModal .lpf-add-btn:hover { background: #e0e0e0; }
#lifeFormPdfModal .lpf-del-btn {
    font-size: 9px; padding: 0 4px; border: 1px solid #ccc;
    background: #fff; cursor: pointer; color: #c00; font-family: 'Sarabun', sans-serif; line-height: 1.5;
}
#lifeFormPdfModal .lpf-del-btn:hover { background: #fee; }

/* ===== Table ===== */
#lifeFormPdfModal .lpf-ev-table td {
    border: 1px solid #000; padding: 2px; text-align: center; vertical-align: middle;
}
#lifeFormPdfModal .lpf-ev-table input[type="text"] {
    border: none; border-bottom: 1px dotted #888; background: transparent; font-size: 10px; width: calc(100% - 18px);
    padding: 1px 2px; outline: none; font-family: 'Sarabun', sans-serif; text-align: center; display: inline-block;
}
#lifeFormPdfModal .lpf-ev-table .btn-hw-open {
    display: inline-block; vertical-align: middle; width: 16px; padding: 0; margin: 0;
    font-size: 0.6rem;
}
#lifeFormPdfModal .lpf-ev-table input[type="checkbox"] { width: 10px; height: 10px; cursor: pointer; }

/* ===== Signature / Sketch ===== */
#lifeFormPdfModal .lpf-sig-box {
    border: 1px solid #ccc; background: #fafafa; min-height: 60px; cursor: crosshair; position: relative;
}
#lifeFormPdfModal .lpf-sig-box canvas { width: 100%; height: 100%; display: block; }
#lifeFormPdfModal .lpf-sketch-area {
    border: 1.5px solid #000; min-height: 500px; position: relative;
    display: flex; align-items: center; justify-content: center; cursor: crosshair;
}

/* ===== Multi-page Sketch System ===== */
#lifeFormPdfModal .lpf-sketch-viewport {
    border: 1.5px solid #000; position: relative;
    background: #fff; cursor: crosshair;
}
#lifeFormPdfModal .lpf-sketch-canvas-wrap {
    position: relative; width: 100%;
}
#lifeFormPdfModal .lpf-sketch-canvas-wrap canvas {
    display: block; width: 100%; height: 100%; position: absolute; top: 0; left: 0; z-index: 2;
}
#lifeFormPdfModal .lpf-sketch-canvas-wrap .lpf-sketch-bg-img {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1;
    object-fit: contain; pointer-events: none; user-select: none;
}
#lifeFormPdfModal .lpf-not-to-scale {
    position: absolute; bottom: 6px; right: 8px; font-size: 10px; color: #555; z-index: 3;
    pointer-events: none;
}

/* ===== Photo Grid 5 คอลัมน์ × 7 แถว (35 รูป/หน้า) ===== */
#lifeFormPdfModal .lpf-photo-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 6px 4px;
    align-content: start;
}
#lifeFormPdfModal .lpf-photo-cell {
    position: relative;
    border: 1.5px solid #333;
    overflow: hidden;
    background: #fff;
}
#lifeFormPdfModal .lpf-photo-cell img {
    width: 100%;
    aspect-ratio: 4/3;
    object-fit: cover;
    display: block;
}
#lifeFormPdfModal .lpf-cell-delete {
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
#lifeFormPdfModal .lpf-cell-filename {
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
#lifeFormPdfModal .lpf-photo-dropzone {
    border: 2px dashed #b0bec5;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    background: #f8f9fa;
    margin-bottom: 8px;
    transition: all 0.2s;
}
#lifeFormPdfModal .lpf-photo-dropzone:hover,
#lifeFormPdfModal .lpf-photo-dropzone.dragover {
    border-color: #2196F3;
    background: #e3f2fd;
}
#lifeFormPdfModal .lpf-add-photo-page-btn {
    display: block; margin: 8px auto; padding: 4px 16px;
    border: 1px dashed #888; background: #f0f0f0; cursor: pointer;
    font-size: 11px; font-family: 'Sarabun', sans-serif; color: #333;
}
#lifeFormPdfModal .lpf-add-photo-page-btn:hover { background: #e0e0e0; }

/* ===== Body Diagram ===== */
#lifeFormPdfModal .lpf-body-diagram-area {
    border: 1.5px solid #000; min-height: 600px; position: relative;
    display: flex; align-items: center; justify-content: center; cursor: crosshair;
    background: #fff;
}

@media print {
    /* ===== ซ่อนทุกอย่างนอกจากฟอร์ม ===== */
    body > *:not(#lifeFormPdfModal),
    .navbar, .sidebar, .main-content, #status-banner,
    .modal-backdrop, .swal2-container,
    #lifeFormPdfModal .modal-header,
    #lifeFormPdfModal .modal-footer,
    #lifeFormPdfModal .csims-loading-overlay,
    #lifeFormPdfModal .lpf-add-btn,
    #lifeFormPdfModal .lpf-del-btn,
    #lifeFormPdfModal .lpf-add-photo-page-btn,
    #lifeFormPdfModal .btn-hw-open,
    #lifeFormPdfModal input[type="color"],
    #lifeFormPdfModal [id="editInfoLifePdf"],
    #lifeFormPdfModal .form-check.form-switch,
    #lifeFormPdfModal .d-flex.align-items-center.gap-3 {
        display: none !important;
    }

    /* ===== Reset modal เป็นแบบ static ===== */
    #lifeFormPdfModal,
    #lifeFormPdfModal .modal-dialog,
    #lifeFormPdfModal .modal-content,
    #lifeFormPdfModal .lpf-body {
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

    /* ===== Page: ขยายตามเนื้อหา ===== */
    @page {
        size: A4 portrait;
        margin: 0;
    }
    #lifeFormPdfModal .lpf-page {
        width: 100% !important;
        min-height: auto !important;
        height: auto !important;
        margin: 0 !important;
        padding: 8mm 10mm 5mm 10mm !important;
        box-shadow: none !important;
        overflow: visible !important;
        page-break-after: always;
        page-break-inside: auto;
    }
    #lifeFormPdfModal .lpf-page:last-of-type {
        page-break-after: auto;
    }

    /* ===== ป้องกัน section แตกกลางหน้า ===== */
    #lifeFormPdfModal .lpf-sec-row {
        page-break-inside: avoid;
    }
    #lifeFormPdfModal .lpf-form-body {
        page-break-inside: auto;
    }
    #lifeFormPdfModal .lpf-header {
        page-break-after: avoid;
    }
    #lifeFormPdfModal .lpf-footer {
        page-break-before: avoid;
    }

    /* ===== Photo grid ===== */
    #lifeFormPdfModal .lpf-photo-grid { gap: 2px !important; }
    #lifeFormPdfModal .lpf-photo-cell { page-break-inside: avoid; }
    #lifeFormPdfModal .lpf-photo-dropzone { display: none !important; }
    #lifeFormPdfModal .lpf-cell-delete { display: none !important; }

    /* ===== Signature / sketch box: ไม่ตัด ===== */
    #lifeFormPdfModal .lpf-sig-box,
    #lifeFormPdfModal .lpf-sketch-area,
    #lifeFormPdfModal .lpf-sketch-viewport,
    #lifeFormPdfModal .lpf-body-diagram-area {
        page-break-inside: avoid;
    }
    #lifeFormPdfModal .lpf-sketch-viewport { overflow: visible !important; height: auto !important; }

    /* ===== Input: แสดงค่าใน print ===== */
    #lifeFormPdfModal .lpf-inp,
    #lifeFormPdfModal .lpf-inp-m,
    #lifeFormPdfModal .lpf-inp-full,
    #lifeFormPdfModal .lpf-inp-s,
    #lifeFormPdfModal .lpf-sel,
    #lifeFormPdfModal .lpf-ta {
        border-bottom-color: #888 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* ===== Checkbox: แสดง check mark ===== */
    #lifeFormPdfModal .lpf-cb {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>

<!-- ===== MODAL ===== -->
<div class="modal fade" id="lifeFormPdfModal" aria-labelledby="lifeFormPdfModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 860px; margin: 1.75rem auto;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="lifeFormPdfModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body lpf-body p-0" style="max-height: 80vh; overflow-y: auto; position:relative;">
                <!-- Loading Overlay -->
                <div id="lifePdfLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track">
                            <div class="csims-bar-fill"></div>
                        </div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>
                <form id="lifeFormPdf" novalidate>
                    <input type="hidden" id="lpf_receiveNoti_id" name="receiveNoti_id">
                    <input type="hidden" id="lpf_doc_no" name="doc_no">
                    <input type="hidden" id="lpf_report_no" name="report_no">
                    <!-- Standard-only fields (hidden carriers for sync) -->
                    <input type="hidden" name="body_diagram_remark_life">
                    <input type="hidden" name="indoor_interior_detail">
                    <input type="hidden" name="outdoor_incident_area_detail">
                    <input type="hidden" name="wound_detail">
                    <input type="hidden" name="dead_name">
                    <input type="hidden" name="dead_age">
                    <input type="hidden" name="injured_name">
                    <input type="hidden" name="injured_age">
                    <input type="hidden" name="missing_name">
                    <input type="hidden" name="missing_age">

                    <!-- Switch กลับไปฟอร์มมาตรฐาน -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="editInfoLifePdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountLifePdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToStdFormLife" checked style="width: 3rem; height: 1.5rem; cursor: pointer;" onchange="if(!this.checked){ this.checked=true; switchToLifeStandardForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToStdFormLife" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ================================================================ -->
<!-- ========================= PAGE 1 ============================== -->
<!-- ================================================================ -->
<div class="lpf-page">

    <div class="lpf-header">
        <div class="lpf-header-logo">
            <img src="./images/office-of-police-forensic-icon.jpg" alt="ตราสำนักงานตำรวจแห่งชาติ">
        </div>
        <div class="lpf-header-center">
            <div class="lpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="lpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต</div>
        </div>
        <div class="lpf-header-right">
            <div class="lpf-doc-box">
                <div class="lpf-doc-line">รายงานที่ <span id="lpf_report_no_display" style="display:inline-block;min-width:60px;border-bottom:1px dotted #888;text-align:center;"></span> / 25<span id="lpf_report_year_display" style="display:inline-block;min-width:30px;border-bottom:1px dotted #888;text-align:center;"><?= substr((date('Y') + 543), -2) ?></span></div>
                <div class="lpf-doc-line">หน้าที่ <span class="lpf-cur-page">1</span> / <span class="lpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div class="lpf-form-body">

        <!-- LEFT COLUMN -->
        <div class="lpf-col-left">
            <div class="lpf-row-header">
                <div class="lpf-lbl-seq">ลำดับ</div>
                <div class="lpf-lbl-data">ข้อมูล</div>
            </div>

            <!-- 1. การรับแจ้งเหตุ -->
            <div class="lpf-sec-row">
                <div class="lpf-sec-label">
                    <span class="lpf-sec-num">1.</span>
                    <span class="lpf-sec-txt">การรับ<br>แจ้งเหตุ</span>
                </div>
                <div class="lpf-sec-body">
                    <div class="lpf-fr">
                        <span class="lpf-fl">คดี</span>
                        <input type="text" class="lpf-inp" name="case_doc_no" id="lpf_case_doc_no" readonly style="text-align:center; background:#f5f5f5;">
                    </div>
                    <div class="lpf-fr">
                        <span class="lpf-fl">วันที่</span>
                        <input type="date" class="lpf-inp-m" name="case_date" id="lpf_case_date" value="<?= $lpfTodayDate ?>">
                        <span class="lpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="lpf-inp" name="case_time" id="lpf_case_time" value="<?= $lpfTodayTime ?>" style="text-align:center;">
                    </div>
                    <div class="lpf-fr">
                        <span class="lpf-fl" style="margin-right:10px;">การรับแจ้ง</span>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="notify_method[]" value="ทางโทรศัพท์">ทางโทรศัพท์</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="notify_method[]" value="ทางวิทยุสื่อสาร">ทางวิทยุสื่อสาร</label>
                    </div>
                    <div class="lpf-fr" style="padding-left:38px;">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="notify_method[]" value="ทางหนังสือ">ทางหนังสือ</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="notify_method[]" value="อื่นๆ">อื่นๆ</label>
                        <input type="text" class="lpf-inp" name="notify_method_other_text" id="lpf_notify_other_text"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>
                    <div class="lpf-fr">
                        <span class="lpf-fl">สน./สภ.</span>
                        <select class="lpf-sel" name="police_station" id="lpf_police_station">
                            <?= $lpfPoliceStationOptions ?>
                        </select>
                    </div>
                    <div class="lpf-fr">
                        <span class="lpf-fl">ที่</span>
                        <input type="text" class="lpf-inp" name="location_at" id="lpf_location_at"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button><span class="lpf-fl">ลง</span>
                        <input type="date" class="lpf-inp" name="record_date" id="lpf_record_date" style="text-align:center;" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="lpf-fr">
                        <span class="lpf-fl">พนักงานสอบสวน</span>
                        <input type="text" class="lpf-inp" name="investigator_name" id="lpf_investigator_name"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>
                    <div class="lpf-fr">
                        <span class="lpf-fl">หมายเลขโทรศัพท์</span>
                        <input type="tel" class="lpf-inp" name="investigator_phone" id="lpf_investigator_phone">
                    </div>
                </div>
            </div>

            <!-- 2. สถานที่เกิดเหตุ -->
            <div class="lpf-sec-row">
                <div class="lpf-sec-label">
                    <span class="lpf-sec-num">2.</span>
                    <span class="lpf-sec-txt">สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="lpf-sec-body">
                    <div class="lpf-bh" style="margin-top:0;">
                        <span class="lpf-bk"></span><span>สถานที่เกิดเหตุ</span></div>
                    <div class="lpf-hw-wrap"><textarea class="lpf-ta" name="crime_location" id="lpf_crime_location" rows="3"></textarea><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>

                    <div id="lpf_victim_container">
                        <div class="lpf-victim-row" style="margin-top:3px; padding: 2px 0; border-top: 1px dotted #ccc;">
                            <div class="lpf-fr">
                                <span class="lpf-fl">ประเภท</span>
                                <select class="lpf-sel" name="victim_type_life[]" style="max-width:100px;">
                                    <option value="">--เลือก--</option>
                                    <option value="ผู้เสียชีวิต">ผู้เสียชีวิต</option>
                                    <option value="ผู้บาดเจ็บ">ผู้บาดเจ็บ</option>
                                    <option value="ผู้สูญหาย">ผู้สูญหาย</option>
                                </select>
                                <span class="lpf-fl" style="margin-left:6px;">ชื่อ</span>
                                <input type="text" class="lpf-inp" name="victim_name_life[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button><span class="lpf-fl" style="margin-left:4px;">อายุ</span>
                                <input type="text" class="lpf-inp-s" name="victim_age_life[]" style="max-width:30px;">
                                <span class="lpf-fl">ปี</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="lpf-add-btn" onclick="lpfAddVictim()">+ เพิ่มบุคคล</button>
                </div>
            </div>

            <!-- 3. วันเวลาที่ทราบเหตุ -->
            <div class="lpf-sec-row">
                <div class="lpf-sec-label">
                    <span class="lpf-sec-num">3.</span>
                    <span class="lpf-sec-txt">วันเวลา<br>ที่ทราบ<br>เหตุ/เกิด<br>เหตุ</span>
                </div>
                <div class="lpf-sec-body">
                    <div class="lpf-fr"><span class="lpf-fl-b">วันเวลาที่ผู้เสียหาย ทราบเหตุ/เกิดเหตุ</span></div>
                    <div class="lpf-fr">
                        <span class="lpf-fl">วันที่</span>
                        <input type="date" class="lpf-inp-m" name="victim_know_date" id="lpf_victim_know_date" value="<?= $lpfTodayDate ?>">
                        <span class="lpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="lpf-inp" name="victim_know_time" id="lpf_victim_know_time" value="<?= $lpfTodayTime ?>" style="text-align:center;">
                    </div>
                    <div class="lpf-fr" style="margin-top:2px;"><span class="lpf-fl-b">วันเวลาที่พนักงานสอบสวนทราบเหตุ</span></div>
                    <div class="lpf-fr">
                        <span class="lpf-fl">วันที่</span>
                        <input type="date" class="lpf-inp-m" name="officer_know_date" id="lpf_officer_know_date" value="<?= $lpfTodayDate ?>">
                        <span class="lpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="lpf-inp" name="officer_know_time" id="lpf_officer_know_time" value="<?= $lpfTodayTime ?>" style="text-align:center;">
                    </div>
                </div>
            </div>

            <!-- 4. วันเวลาที่ตรวจเหตุ -->
            <div class="lpf-sec-row">
                <div class="lpf-sec-label">
                    <span class="lpf-sec-num">4.</span>
                    <span class="lpf-sec-txt">วัน<br>เวลาที่<br>ตรวจ<br>เหตุ</span>
                </div>
                <div class="lpf-sec-body">
                    <div class="lpf-fr"><span class="lpf-fl-b">วันเวลาที่ทำการตรวจสถานที่เกิดเหตุ</span></div>
                    <div class="lpf-fr">
                        <span class="lpf-fl">วันที่</span>
                        <input type="date" class="lpf-inp-m" name="inspect_date" id="lpf_inspect_date" value="<?= $lpfTodayDate ?>">
                        <span class="lpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="lpf-inp" name="inspect_time" id="lpf_inspect_time" value="<?= $lpfTodayTime ?>" style="text-align:center;">
                    </div>
                    <div class="lpf-fr" style="margin-top:2px;"><span class="lpf-fl-b">วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเพิ่มเติม</span></div>
                    <div class="lpf-fr">
                        <span class="lpf-fl">วันที่</span>
                        <input type="date" class="lpf-inp-m" name="inspect_additional_date" id="lpf_inspect_additional_date">
                        <span class="lpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="lpf-inp" name="inspect_additional_time" id="lpf_inspect_additional_time" style="text-align:center;">
                    </div>
                </div>
            </div>

            <!-- 5. ผู้ตรวจสถานที่เกิดเหตุ -->
            <div class="lpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="lpf-sec-label">
                    <span class="lpf-sec-num">5.</span>
                    <span class="lpf-sec-txt">ผู้ตรวจ<br>สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="lpf-sec-body">
                    <div class="lpf-bh" style="margin-top:0;"><span class="lpf-bk"></span><span>ผู้ตรวจสถานที่เกิดเหตุ</span></div>
                    <div id="lpf_inspector_container">
                        <div class="lpf-si lpf-inspector-row">
                            <span class="lpf-si-no">5.1</span>
                            <select class="lpf-sel" name="inspector_id[]">
                                <?= $lpfInspectorOptions ?>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="lpf-add-btn" onclick="lpfAddInspector()">+ เพิ่มผู้ตรวจ</button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="lpf-col-right">
            <div class="lpf-row-header">
                <div class="lpf-lbl-seq">ลำดับ</div>
                <div class="lpf-lbl-data">ข้อมูล</div>
            </div>

            <!-- 6. ลักษณะของสถานที่เกิดเหตุ -->
            <div class="lpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="lpf-sec-label">
                    <span class="lpf-sec-num">6.</span>
                    <span class="lpf-sec-txt">ลักษณะ<br>สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="lpf-sec-body">
                    <!-- สภาพสถานที่เมื่อไปถึง -->
                    <div class="lpf-bh" style="margin-top:0;"><span class="lpf-bk"></span><span>สภาพสถานที่เกิดเหตุเมื่อไปถึง</span></div>

                    <!-- การรักษาสถานที่ -->
                    <div class="lpf-bh"><span class="lpf-bk"></span><span>การรักษาสถานที่เกิดเหตุ</span></div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="scene_preserved" value="มี" data-group="lpf_scene">มี</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="scene_preserved" value="ไม่มี" data-group="lpf_scene">ไม่มี</label>
                        <input type="text" class="lpf-inp" name="scene_preserved_no_text"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>

                    <!-- แสงสว่าง -->
                    <div class="lpf-bh"><span class="lpf-bk"></span><span>แสงสว่าง(ที่สังเกตเห็น)</span></div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="lighting[]" value="สว่าง">สว่าง</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="lighting[]" value="มืด">มืด</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="lighting[]" value="สลัว">สลัว</label>
                    </div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="lighting[]" value="อื่นๆ">อื่นๆ</label>
                        <input type="text" class="lpf-inp" name="lighting_other_text"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>

                    <!-- อุณหภูมิ -->
                    <div class="lpf-bh"><span class="lpf-bk"></span><span>อุณหภูมิ</span></div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="temperature[]" value="ร้อน">ร้อน</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="temperature[]" value="เย็น">เย็น</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="temperature[]" value="เครื่องปรับอากาศ">เครื่องปรับอากาศ</label>
                    </div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="temperature[]" value="อื่นๆ">อื่นๆ</label>
                        <input type="text" class="lpf-inp" name="temperature_other_text"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>

                    <!-- กลิ่น -->
                    <div class="lpf-bh"><span class="lpf-bk"></span><span>กลิ่น</span></div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="smell" value="มี" data-group="lpf_smell">มี</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="smell" value="ไม่มี" data-group="lpf_smell">ไม่มี</label>
                    </div>

                    <!-- ลักษณะสถานที่เกิดเหตุ -->
                    <div class="lpf-bh"><span class="lpf-bk"></span><span>ลักษณะสถานที่เกิดเหตุ</span></div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="has_outdoor_incident_life" value="1">กรณีเกิดเหตุภายนอกอาคาร</label>
                    </div>
                    <div class="lpf-cg lpf-i2">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="outdoor_type[]" value="ถนน">ถนน</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="outdoor_type[]" value="สนามหญ้า">สนามหญ้า</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="outdoor_type[]" value="ในสวน">ในสวน</label>
                    </div>
                    <div class="lpf-cg lpf-i2">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="outdoor_type[]" value="ที่ว่าง">ที่ว่าง</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="outdoor_type[]" value="อื่นๆ">อื่นๆ</label>
                        <input type="text" class="lpf-inp" name="outdoor_type_other_text"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>

                    <!-- สภาพบริเวณโดยรอบ เมื่อหันหน้าเข้า -->
                    <div class="lpf-bh lpf-i1"><span class="lpf-bk"></span><span>สภาพบริเวณโดยรอบ เมื่อหันหน้าเข้า</span></div>
                    <div class="lpf-i2">
                        <div class="lpf-fr"><span class="lpf-fl">เมื่อหันหน้าเข้า</span><input type="text" class="lpf-inp" name="outdoor_entrance_condition"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-fr"><span class="lpf-fl">ด้านหน้าติด</span><input type="text" class="lpf-inp" name="outdoor_front_adjacent"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-fr"><span class="lpf-fl">ด้านซ้ายติด</span><input type="text" class="lpf-inp" name="outdoor_left_adjacent"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-fr"><span class="lpf-fl">ด้านขวาติด</span><input type="text" class="lpf-inp" name="outdoor_right_adjacent"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-fr"><span class="lpf-fl">ด้านหลังติด</span><input type="text" class="lpf-inp" name="outdoor_back_adjacent"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    </div>

                    <!-- บริเวณที่เกิดเหตุ -->
                    <div class="lpf-bh lpf-i1"><span class="lpf-bk"></span><span>บริเวณที่เกิดเหตุ เกิดเหตุที่</span></div>
                    <div class="lpf-i2">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="has_indoor_incident_life" value="1">บริเวณภายในอาคาร</label>
                    </div>

                    <!-- ลักษณะภายนอก -->
                    <div class="lpf-bh lpf-i2"><span class="lpf-bk"></span><span>ลักษณะภายนอก</span></div>
                    <div class="lpf-cg lpf-i2">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="building_type[]" value="อาคารพาณิชย์">อาคารพาณิชย์</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="building_type[]" value="บ้านเดี่ยว">บ้านเดี่ยว</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="building_type[]" value="ทาวน์เฮาส์">ทาวน์เฮาส์</label>
                    </div>
                    <div class="lpf-cg lpf-i2">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="building_type[]" value="อื่นๆ">อื่นๆ</label>
                        <input type="text" class="lpf-inp" name="building_type_other_text"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>

                    <!-- สภาพบริเวณโดยรอบ มีรั้ว -->
                    <div class="lpf-bh lpf-i2"><span class="lpf-bk"></span><span>สภาพบริเวณโดยรอบ</span></div>
                    <div class="lpf-cg lpf-i2">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="indoor_surrounding" value="มีรั้ว" data-group="lpf_surrounding">มีรั้ว</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="indoor_surrounding" value="ไม่มีรั้ว" data-group="lpf_surrounding">ไม่มีรั้ว</label>
                    </div>
                    <div class="lpf-i2">
                        <div class="lpf-fr"><span class="lpf-fl">เมื่อหันหน้าเข้า</span><input type="text" class="lpf-inp" name="entrance_condition"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-fr"><span class="lpf-fl">ด้านหน้าติด</span><input type="text" class="lpf-inp" name="front_adjacent"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-fr"><span class="lpf-fl">ด้านซ้ายติด</span><input type="text" class="lpf-inp" name="left_adjacent"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-fr"><span class="lpf-fl">ด้านขวาติด</span><input type="text" class="lpf-inp" name="right_adjacent"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-fr"><span class="lpf-fl">ด้านหลังติด</span><input type="text" class="lpf-inp" name="back_adjacent"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    </div>

                    <!-- บริเวณที่เกิดเหตุ เกิดเหตุที่ -->
                    <div class="lpf-bh lpf-i2"><span class="lpf-bk"></span><span>บริเวณที่เกิดเหตุ เกิดเหตุที่</span></div>
                    <div class="lpf-i2">
                        <div class="lpf-fr"><span class="lpf-fl">บริเวณที่เกิดเหตุ เกิดเหตุที่</span><input type="text" class="lpf-inp" name="incident_area_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    </div>

                    <!-- โครงสร้างบริเวณที่เกิดเหตุ -->
                    <div class="lpf-bh lpf-i2"><span class="lpf-bk"></span><span>โครงสร้างบริเวณที่เกิดเหตุ</span></div>
                    <div class="lpf-cg lpf-i2">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="structure_size_check" value="1">ขนาดกว้าง x ยาว ประมาณ</label>
                        <input type="text" class="lpf-inp" name="structure_size" style="flex:1;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    <div class="lpf-cg lpf-i2">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="structure_type_check" value="1">ลักษณะโครงสร้าง</label>
                        <input type="text" class="lpf-inp" name="structure_type" style="flex:1;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                </div>
            </div>
        </div>
    </div>

    <div class="lpf-footer">
        <div class="lpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="lpf-footer-right">F-CS-09 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 2 ============================== -->
<!-- ================================================================ -->
<div class="lpf-page">

    <div class="lpf-header">
        <div class="lpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="lpf-header-center">
            <div class="lpf-title-main">ตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="lpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต</div>
        </div>
        <div class="lpf-header-right">
            <div class="lpf-doc-box">
                <div class="lpf-doc-line">รายงานที่ <span class="lpf-rpt-no-mirror"></span> / 25<span class="lpf-rpt-year-mirror"></span></div>
                <div class="lpf-doc-line">หน้าที่ <span class="lpf-cur-page">2</span> / <span class="lpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div class="lpf-form-body">
        <div class="lpf-col-left">
            <div class="lpf-row-header"><div class="lpf-lbl-seq">ลำดับ</div><div class="lpf-lbl-data">ข้อมูล</div></div>

            <!-- 6. (ต่อ) ลักษณะของสถานที่เกิดเหตุ -->
            <div class="lpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="lpf-sec-label"><span class="lpf-sec-num">6.</span><span class="lpf-sec-txt">(ต่อ)</span></div>
                <div class="lpf-sec-body">
                    <div class="lpf-fr"><span class="lpf-fl">รายละเอียดผนัง</span><input type="text" class="lpf-inp" name="structure_wall"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    <div class="lpf-fr"><span class="lpf-fl">ด้านหน้า</span><input type="text" class="lpf-inp" name="structure_front"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    <div class="lpf-fr"><span class="lpf-fl">ด้านขวา</span><input type="text" class="lpf-inp" name="structure_right"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    <div class="lpf-fr"><span class="lpf-fl">ด้านซ้าย</span><input type="text" class="lpf-inp" name="structure_left"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    <div class="lpf-fr"><span class="lpf-fl">ด้านหลัง</span><input type="text" class="lpf-inp" name="structure_back"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    <div class="lpf-cg">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="structure_floor_check" value="1">พื้น</label>
                        <input type="text" class="lpf-inp" name="structure_floor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    <div class="lpf-cg">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="structure_roof_check" value="1">หลังคา</label>
                        <input type="text" class="lpf-inp" name="structure_roof"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    <div class="lpf-cg">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="structure_arrangement_check" value="1">การจัดวางสิ่งของ</label>
                        <input type="text" class="lpf-inp" name="structure_arrangement"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>

                    <!-- ทางเข้า - ทางออก -->
                    <div class="lpf-fr">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="entrance_exit_check" value="ทางเข้า - ทางออก">ทางเข้า - ทางออก</label>
                        <input type="text" class="lpf-inp" name="entrance_exit"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                </div>
            </div>

            <!-- 7. พฤติการณ์คดี -->
            <div class="lpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="lpf-sec-label">
                    <span class="lpf-sec-num">7.</span>
                    <span class="lpf-sec-txt">ผลการ<br>ตรวจ<br>สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="lpf-sec-body">
                    <div class="lpf-bh" style="margin-top:0;"><span class="lpf-bk"></span><span>พฤติการณ์คดี</span></div>
                    <div class="lpf-hw-wrap"><textarea class="lpf-ta" name="case_behavior" rows="3"></textarea><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    <div class="lpf-fr" style="margin-top:2px;">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="entrance_exit_check2[]" value="ทางเข้า - ทางออก">ทางเข้า - ทางออก</label>
                        <input type="text" class="lpf-inp" name="entrance_exit_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>

                    <!-- ร่องรอยการต่อสู้ -->
                    <div class="lpf-bh"><span class="lpf-bk"></span><span>ร่องรอยการต่อสู้</span></div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="fight_trace" value="มี" data-group="lpf_fight">มี</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="fight_trace" value="ไม่มี" data-group="lpf_fight">ไม่มี</label>
                    </div>
                    <div class="lpf-hw-wrap lpf-i1"><input type="text" class="lpf-inp-full" name="fight_trace_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    <!-- ร่องรอยการรื้อค้น -->
                    <div class="lpf-bh"><span class="lpf-bk"></span><span>ร่องรอยการรื้อค้น</span></div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="search_trace" value="มี" data-group="lpf_search">มี</label>
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="search_trace" value="ไม่มี" data-group="lpf_search">ไม่มี</label>
                    </div>

                    <!-- ศพ -->
                    <div class="lpf-bh"><span class="lpf-bk"></span><span>ศพ</span></div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="body_status" value="พบศพ" data-group="lpf_body_status">พบศพ</label>
                    </div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="body_status" value="ไม่พบศพ" data-group="lpf_body_status">ไม่พบศพ</label>
                    </div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="body_status" value="ตำแหน่งที่พบศพ">ตำแหน่งที่พบศพ</label>
                        <input type="text" class="lpf-inp" name="body_location"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    <div class="lpf-hw-wrap lpf-i1"><input type="text" class="lpf-inp-full" name="body_location_2"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="lpf-col-right">
            <div class="lpf-row-header"><div class="lpf-lbl-seq">ลำดับ</div><div class="lpf-lbl-data">ข้อมูล</div></div>

            <!-- 7. (ต่อ) สภาพศพ -->
            <div class="lpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="lpf-sec-label"><span class="lpf-sec-num">7.</span><span class="lpf-sec-txt">(ต่อ)</span></div>
                <div class="lpf-sec-body">
                    <!-- สภาพศพ -->
                    <div class="lpf-bh" style="margin-top:0;"><span class="lpf-bk"></span><span>สภาพศพ</span></div>
                    <div class="lpf-hw-wrap"><textarea class="lpf-ta" name="body_condition" rows="2"></textarea><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    <!-- การแต่งกายและทรัพย์สิน -->
                    <div class="lpf-bh"><span class="lpf-bk"></span><span>การแต่งกายและทรัพย์สิน</span></div>
                    <div class="lpf-i1">
                        <div class="lpf-cg"><label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="clothing_items[]" value="เสื้อ">เสื้อ</label><input type="text" class="lpf-inp" name="clothing_shirt"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-cg"><label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="clothing_items[]" value="กางเกง">กางเกง</label><input type="text" class="lpf-inp" name="clothing_pants"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-cg"><label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="clothing_items[]" value="รองเท้า/ถุงเท้า">รองเท้า/ถุงเท้า</label><input type="text" class="lpf-inp" name="clothing_shoes"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-cg"><label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="clothing_items[]" value="เครื่องประดับ">เครื่องประดับ</label><input type="text" class="lpf-inp" name="clothing_accessories"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-cg"><label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="clothing_items[]" value="รอยสักหรือรอยแผลเป็น">รอยสักหรือรอยแผลเป็น</label><input type="text" class="lpf-inp" name="clothing_tattoo"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-cg"><label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="clothing_items[]" value="อื่นๆ">อื่นๆ</label><input type="text" class="lpf-inp" name="clothing_other"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    </div>

                    <!-- สภาพรอยบาดแผลเบื้องต้น -->
                    <div class="lpf-bh"><span class="lpf-bk"></span><span>สภาพรอยบาดแผลเบื้องต้น</span></div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="wound_status" value="ไม่พบรอยบาดแผล" data-group="lpf_wound">ไม่พบรอยบาดแผล</label>
                    </div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="wound_status" value="พบรอยบาดแผล" data-group="lpf_wound">พบรอยบาดแผลจำนวน</label>
                        <input type="text" class="lpf-inp-s" name="wound_count" style="max-width:30px;">
                        <span class="lpf-fl">รอย</span>
                    </div>
                    <div class="lpf-i1">
                        <div class="lpf-fr"><span class="lpf-fl">ลักษณะบาดแผลคือ</span><button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="lpf_wound_description" title="HW"><i class="fas fa-pen"></i></button></div>
                        <textarea class="lpf-ta" name="wound_description" id="lpf_wound_description" rows="2"></textarea>
                        <input type="hidden" name="wound_description_2">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="lpf-footer">
        <div class="lpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="lpf-footer-right">F-CS-09 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 3 ============================== -->
<!-- ================================================================ -->
<div class="lpf-page">

    <div class="lpf-header">
        <div class="lpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="lpf-header-center">
            <div class="lpf-title-main">ตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="lpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต</div>
        </div>
        <div class="lpf-header-right">
            <div class="lpf-doc-box">
                <div class="lpf-doc-line">รายงานที่ <span class="lpf-rpt-no-mirror"></span> / 25<span class="lpf-rpt-year-mirror"></span></div>
                <div class="lpf-doc-line">หน้าที่ <span class="lpf-cur-page">3</span> / <span class="lpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div class="lpf-form-body">
        <div class="lpf-col-left">
            <div class="lpf-row-header"><div class="lpf-lbl-seq">ลำดับ</div><div class="lpf-lbl-data">ข้อมูล</div></div>

            <!-- 7. (ต่อ) วัตถุพยานที่ตรวจพบ -->
            <div class="lpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="lpf-sec-label"><span class="lpf-sec-num">7.</span><span class="lpf-sec-txt">(ต่อ)</span></div>
                <div class="lpf-sec-body">
                    <!-- วัตถุพยานที่ตรวจพบ -->
                    <div class="lpf-bh" style="margin-top:0;"><span class="lpf-bk"></span><span>วัตถุพยานที่ตรวจพบ</span></div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="evidence_blood_stain" value="1">คราบสีแดงคล้ายโลหิต</label>
                        <input type="text" class="lpf-inp" name="blood_stain_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>

                    <!-- ทดสอบด้วยชุดทดสอบคราบโลหิต -->
                    <div class="lpf-fr lpf-i1"><span class="lpf-fl-b">ทดสอบด้วยชุดทดสอบคราบโลหิตเบื้องต้น</span></div>
                    <div class="lpf-i2">
                        <div class="lpf-cg"><label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="test_hemastix" value="1">Hemastix</label></div>
                        <div class="lpf-cg lpf-i1">
                            <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="hemastix_result" value="มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน" data-group="lpf_hemastix">มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน</label>
                        </div>
                        <div class="lpf-cg lpf-i1">
                            <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="hemastix_result" value="ไม่มีการเปลี่ยนแปลง" data-group="lpf_hemastix">ไม่มีการเปลี่ยนแปลง</label>
                        </div>
                        <div class="lpf-cg"><label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="test_phenolphthalein" value="1">Phenolphthalein</label></div>
                        <div class="lpf-cg lpf-i1">
                            <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="phenol_result" value="มีการเปลี่ยนแปลงเป็นสีชมพูในทันที" data-group="lpf_phenol">มีการเปลี่ยนแปลงเป็นสีชมพูในทันที</label>
                        </div>
                        <div class="lpf-cg lpf-i1">
                            <label class="lpf-ck"><input type="checkbox" class="lpf-cb lpf-radio-toggle" name="phenol_result" value="ไม่มีการเปลี่ยนแปลง" data-group="lpf_phenol">ไม่มีการเปลี่ยนแปลง</label>
                        </div>
                    </div>

                    <!-- วัตถุพยานอื่นๆ -->
                    <div class="lpf-cg lpf-i1"><label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="other_evidence_check" value="1">วัตถุพยานอื่นๆ</label><input type="text" class="lpf-inp" name="other_evidence"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>

                    <!-- วัตถุพยานที่ตรวจเก็บ -->
                    <div class="lpf-bh"><span class="lpf-bk"></span><span>วัตถุพยานที่ตรวจเก็บ/การดำเนินการเกี่ยวกับวัตถุพยาน<br>เพื่อส่งตรวจพิสูจน์</span></div>
                    <div class="lpf-i1">
                        <div class="lpf-cg"><label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="collected_evidence[]" value="วัตถุพยานประเภทอาวุธปืนและเครื่องกระสุน">วัตถุพยานประเภทอาวุธปืนและเครื่องกระสุน</label></div>
                        <div class="lpf-hw-wrap lpf-i1"><textarea class="lpf-ta" name="collected_gun_detail" rows="2"></textarea><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-cg"><label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="collected_evidence[]" value="วัตถุพยานประเภทสารพันธุกรรม">วัตถุพยานประเภทสารพันธุกรรม</label></div>
                        <div class="lpf-hw-wrap lpf-i1"><textarea class="lpf-ta" name="collected_dna_detail" rows="2"></textarea><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="lpf-col-right">
            <div class="lpf-row-header"><div class="lpf-lbl-seq">ลำดับ</div><div class="lpf-lbl-data">ข้อมูล</div></div>

            <!-- 7. (ต่อ) -->
            <div class="lpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="lpf-sec-label"><span class="lpf-sec-num">7.</span><span class="lpf-sec-txt">(ต่อ)</span></div>
                <div class="lpf-sec-body">
                    <div class="lpf-i1">
                        <div class="lpf-cg"><label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="evidence_fingerprint" value="1">วัตถุพยานประเภทลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง</label></div>
                        <div class="lpf-hw-wrap lpf-i1"><textarea class="lpf-ta" name="fingerprint_detail" rows="2"></textarea><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                        <div class="lpf-cg"><label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="evidence_other_type" value="1">วัตถุพยานประเภทอื่น ๆ</label></div>
                        <div class="lpf-hw-wrap lpf-i1"><textarea class="lpf-ta" name="other_evidence_type" rows="2"></textarea><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
                    </div>

                    <!-- การตรวจสอบครั้งสุดท้าย -->
                    <div class="lpf-bh"><span class="lpf-bk"></span><span>การตรวจสอบครั้งสุดท้าย</span></div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="final_check[]" value="การตรวจสอบครั้งสุดท้าย">การตรวจสอบครั้งสุดท้าย</label>
                    </div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="final_check[]" value="ตรวจเก็บวัตถุพยานครบถ้วน">ตรวจเก็บวัตถุพยานครบถ้วน</label>
                    </div>
                    <div class="lpf-cg lpf-i1">
                        <label class="lpf-ck"><input type="checkbox" class="lpf-cb" name="final_check[]" value="ถ่ายภาพสถานที่เกิดเหตุและดำเนินการส่งมอบสถานที่เกิดเหตุให้แก่พนักงานสอบสวน">ถ่ายภาพสถานที่เกิดเหตุ และดำเนินการส่งมอบสถานที่<br>เกิดเหตุให้แก่พนักงานสอบสวน</label>
                    </div>

                    <!-- วันเวลาตรวจเสร็จ -->
                    <div class="lpf-bh"><span class="lpf-bk"></span><span>วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น</span></div>
                    <div class="lpf-fr lpf-i1">
                        <span class="lpf-fl">วันที่</span>
                        <input type="date" class="lpf-inp" name="inspection_end_date" id="lpf_inspection_end_date" value="<?= $lpfTodayDate ?>" style="text-align:center;">
                        <span class="lpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="lpf-inp" name="inspection_end_time" id="lpf_inspection_end_time" value="<?= $lpfTodayTime ?>" style="text-align:center;">
                        <span class="lpf-fl">น.</span>
                    </div>

                    <!-- การส่งมอบสถานที่ -->
                    <div class="lpf-bh"><span class="lpf-bk"></span><span>การส่งมอบสถานที่เกิดเหตุ</span></div>

                    <!-- ผู้รับมอบ -->
                    <div class="lpf-fr" style="margin-top:6px; align-items:flex-end;">
                        <span class="lpf-fl">ลงชื่อ</span>
                        <div style="flex:1; min-height:50px; border-bottom:1px dotted #888; position:relative;">
                            <canvas id="lpf_sig_receiver" style="width:100%; height:50px; cursor:crosshair;"></canvas>
                            <input type="hidden" name="receiver_signature_data_life" id="lpf_receiver_sig_data">
                        </div>
                        <span class="lpf-fl">ผู้รับมอบสถานที่เกิดเหตุ</span>
                    </div>
                    <div class="lpf-fr" style="margin-top:2px; justify-content:flex-end;">
                        <button type="button" class="lpf-add-btn" onclick="lpfClearCanvas('lpf_sig_receiver')">ล้างลายเซ็นผู้รับมอบ</button>
                    </div>
                    <div class="lpf-fr">
                        <span class="lpf-fl" style="visibility:hidden;">ลงชื่อ</span>
                        <span class="lpf-fl">(</span>
                        <select class="lpf-sel" name="receiver_name" id="lpf_receiver_name" style="text-align:center;">
                            <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้รับมอบ --', $lpfInspectorOptions) ?>
                        </select>
                        <span class="lpf-fl">)</span>
                    </div>
                    <div class="lpf-fr">
                        <span class="lpf-fl">ตำแหน่ง</span>
                        <input type="text" class="lpf-inp" name="receiver_position" id="lpf_receiver_position" readonly>
                    </div>

                    <!-- ผู้ส่งมอบ -->
                    <div class="lpf-fr" style="margin-top:8px; align-items:flex-end;">
                        <span class="lpf-fl">ลงชื่อ</span>
                        <div style="flex:1; min-height:50px; border-bottom:1px dotted #888; position:relative;">
                            <canvas id="lpf_sig_sender" style="width:100%; height:50px; cursor:crosshair;"></canvas>
                            <input type="hidden" name="sender_signature_data_life" id="lpf_sender_sig_data">
                        </div>
                        <span class="lpf-fl">ผู้ส่งมอบสถานที่เกิดเหตุ</span>
                    </div>
                    <div class="lpf-fr" style="margin-top:2px; justify-content:flex-end;">
                        <button type="button" class="lpf-add-btn" onclick="lpfClearCanvas('lpf_sig_sender')">ล้างลายเซ็นผู้ส่งมอบ</button>
                    </div>
                    <div class="lpf-fr">
                        <span class="lpf-fl" style="visibility:hidden;">ลงชื่อ</span>
                        <span class="lpf-fl">(</span>
                        <select class="lpf-sel" name="sender_name" id="lpf_sender_name" style="text-align:center;">
                            <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งมอบ --', $lpfInspectorOptions) ?>
                        </select>
                        <span class="lpf-fl">)</span>
                    </div>
                    <div class="lpf-fr">
                        <span class="lpf-fl">ตำแหน่ง</span>
                        <input type="text" class="lpf-inp" name="sender_position" id="lpf_sender_position" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="lpf-footer">
        <div class="lpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="lpf-footer-right">F-CS-09 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 4 (SKETCH) ===================== -->
<!-- ================================================================ -->
<div class="lpf-page lpf-sketch-page" id="lpf_sketch_page_1" data-sketch-page="1">

    <div class="lpf-header">
        <div class="lpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="lpf-header-center">
            <div class="lpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="lpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต</div>
        </div>
        <div class="lpf-header-right">
            <div class="lpf-doc-box">
                <div class="lpf-doc-line">รายงานที่ <span class="lpf-rpt-no-mirror"></span> / 25<span class="lpf-rpt-year-mirror"></span></div>
                <div class="lpf-doc-line">หน้าที่ <span class="lpf-cur-page">4</span> / <span class="lpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
        <div></div>
        <div style="font-size:13px; font-weight:600;">แผนผังสังเขป</div>
        <div style="display:flex; gap:4px; align-items:center;">
            <label class="lpf-add-btn" style="padding:2px 8px; cursor:pointer; margin:0;" title="แนบรูปภาพพื้นหลัง">
                <i class="fas fa-image me-1"></i> แนบรูป
                <input type="file" accept="image/*" style="display:none;" onchange="lpfSketchAttachImage('lpf_sketch_page_1',this)">
            </label>
            <button type="button" class="lpf-add-btn" style="padding:2px 8px;" onclick="lpfSketchRemoveBg('lpf_sketch_page_1')" title="ลบรูปพื้นหลัง"><i class="fas fa-times"></i> ลบรูป</button>
        </div>
    </div>

    <div class="lpf-sketch-viewport" id="lpf_sketch_page_1_viewport">
        <div class="lpf-sketch-canvas-wrap" id="lpf_sketch_page_1_wrap" style="aspect-ratio:1120/660;">
            <canvas id="lpf_sketch_page_1_canvas" width="1120" height="660"></canvas>
        </div>
        <div class="lpf-not-to-scale">* NOT TO SCALE</div>
    </div>
    <div style="text-align:right; margin-top:4px; display:flex; justify-content:flex-end; gap:6px; align-items:center;">
        <button type="button" class="lpf-add-btn" onclick="sketchUndo('lpf_sketch_page_1_canvas')" style="padding:2px 8px;"><i class="fas fa-undo"></i> ย้อนกลับ</button>
        <button type="button" class="lpf-add-btn" id="lpf_sketch_page_1_canvas_eraser_btn" onclick="sketchToggleEraser('lpf_sketch_page_1_canvas')" style="padding:2px 8px;" title="ยางลบ"><i class="fas fa-eraser"></i></button>
        <div style="display:flex;align-items:center;gap:4px;">
            <i class="fas fa-pen" style="font-size:10px;color:#666;"></i>
            <input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize('lpf_sketch_page_1_canvas',this.value);this.nextElementSibling.textContent=this.value+'px'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">
            <span style="font-size:11px;color:#666;min-width:35px;">2px</span>
        </div>
        <label style="margin:0;cursor:pointer;" title="เลือกสี"><input type="color" value="#000000" onchange="sketchSetColor('lpf_sketch_page_1_canvas',this.value)" style="width:26px;height:22px;border:1px solid #aaa;border-radius:3px;padding:0;cursor:pointer;"></label>
        <button type="button" class="lpf-add-btn" onclick="lpfClearCanvas('lpf_sketch_page_1_canvas')">ล้างกระดาน</button>
    </div>

    <div style="margin-bottom:8px;">
        <div class="lpf-fr">
            <span class="lpf-fl">หมายเหตุ</span><button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="lpf_sketch_remark" title="HW"><i class="fas fa-pen"></i></button>
        </div>
        <textarea class="lpf-ta" name="sketch_remark_life" id="lpf_sketch_remark" rows="6"></textarea>
    </div>

    <div style="margin-top:10px; display:flex; flex-direction:column; align-items:flex-end;">
        <div class="lpf-fr" style="width:auto;">
            <span class="lpf-fl">ผู้จดบันทึก</span>
            <input type="text" class="lpf-inp" name="sketch_recorder_life" id="lpf_sketch_recorder" style="width:250px;">
        </div>
        <div class="lpf-fr" style="width:auto;">
            <span class="lpf-fl">วัน เวลา</span>
            <input type="datetime-local" class="lpf-inp" name="sketch_datetime_life" id="lpf_sketch_datetime" style="width:250px; text-align:center;">
        </div>
    </div>

    <div style="text-align:center; margin-top:8px;">
        <button type="button" class="lpf-add-btn" onclick="lpfSketchAddPage()" style="padding:3px 14px; font-size:12px;">
            <i class="fas fa-plus me-1"></i> เพิ่มหน้าแผนผัง
        </button>
    </div>

    <div class="lpf-footer">
        <div class="lpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="lpf-footer-right">F-CS-09 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>
<!-- ★ Dynamic sketch pages will be inserted here by JS (before PAGE 5) -->

<!-- Hidden inputs for data persistence -->
<input type="hidden" name="scene_sketch_data_life" id="lpf_scene_sketch_data">
<input type="hidden" name="scene_sketch_pages_life" id="lpf_scene_sketch_pages_data">

<!-- ================================================================ -->
<!-- ========================= PAGE 5 (EVIDENCE LOCATION) ========== -->
<!-- ================================================================ -->
<div class="lpf-page">

    <div class="lpf-header">
        <div class="lpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="lpf-header-center">
            <div class="lpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="lpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต</div>
        </div>
        <div class="lpf-header-right">
            <div class="lpf-doc-box">
                <div class="lpf-doc-line">รายงานที่ <span class="lpf-rpt-no-mirror"></span> / 25<span class="lpf-rpt-year-mirror"></span></div>
                <div class="lpf-doc-line">หน้าที่ <span class="lpf-cur-page">5</span> / <span class="lpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:6px;">
        <span class="lpf-bk"></span>
        <span style="font-size:12px; font-weight:600;">วัตถุพยานและตำแหน่งที่ตรวจพบ</span>
        <button type="button" class="lpf-add-btn" style="float:right;" onclick="lpfAddEvidenceRow()">+ เพิ่มรายการ</button>
    </div>

    <table class="lpf-ev-table" style="width:100%; border-collapse:collapse; font-size:11px;">
        <thead>
            <tr>
                <th rowspan="2" style="border:1.5px solid #000; width:40px; padding:2px; text-align:center; vertical-align:middle;">ป้าย<br>หมายเลข</th>
                <th rowspan="2" style="border:1.5px solid #000; padding:2px; text-align:center; vertical-align:middle;">วัตถุพยาน</th>
                <th colspan="4" style="border:1.5px solid #000; padding:2px; text-align:center;">ระยะห่าง (m) จากจุด<br>อ้างอิง</th>
                <th rowspan="2" style="border:1.5px solid #000; width:80px; padding:2px; text-align:center; vertical-align:middle;">Azimuth<br>พิกัด/องศา/<br>ระยะ</th>
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
        <tbody id="lpf_evidence_tbody">
            <tr>
                <td><input type="text" name="evidence_label_life[]" style="width:35px;"></td>
                <td><input type="text" name="evidence_item_life[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>
                <td><input type="text" name="evidence_level_1_life_0" style="width:28px;"></td>
                <td><input type="text" name="evidence_level_2_life_0" style="width:28px;"></td>
                <td><input type="text" name="evidence_level_3_life_0" style="width:28px;"></td>
                <td><input type="text" name="evidence_level_4_life_0" style="width:28px;"></td>
                <td><input type="text" name="evidence_azimuth_life[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>
                <td><input type="text" name="evidence_remark_life[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>
                <td><select name="evidence_lab_unit_life[]" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option></select></td>
                <td><button type="button" class="lpf-del-btn" onclick="lpfDelRow(this)">×</button></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top:8px; font-size:11.5px;">
        <div class="lpf-fr" style="margin-bottom:2px;"><span class="lpf-fl">จุดอ้างอิงที่ 1 คือ</span><input type="text" class="lpf-inp" name="reference_point_1_life"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
        <div class="lpf-fr" style="margin-bottom:2px;"><span class="lpf-fl">จุดอ้างอิงที่ 2 คือ</span><input type="text" class="lpf-inp" name="reference_point_2_life"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
        <div class="lpf-fr" style="margin-bottom:2px;"><span class="lpf-fl">จุดอ้างอิงที่ 3 คือ</span><input type="text" class="lpf-inp" name="reference_point_3_life"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
        <div class="lpf-fr" style="margin-bottom:2px;"><span class="lpf-fl">จุดอ้างอิงที่ 4 คือ</span><input type="text" class="lpf-inp" name="reference_point_4_life"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
    </div>

    <div style="margin-top:12px; display:flex; flex-direction:column; align-items:flex-end;">
        <div class="lpf-fr" style="width:auto;"><span class="lpf-fl">ผู้จดบันทึก</span><input type="text" class="lpf-inp" name="collector_name_life" id="lpf_collector_name" style="width:250px;"></div>
        <div class="lpf-fr" style="width:auto;"><span class="lpf-fl">วัน/เวลา</span><input type="datetime-local" class="lpf-inp" name="collection_datetime_life" id="lpf_collection_datetime" style="width:250px; text-align:center;"></div>
    </div>

    <div class="lpf-footer">
        <div class="lpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="lpf-footer-right">F-CS-09 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 6 (BODY DIAGRAM) =============== -->
<!-- ================================================================ -->
<div class="lpf-page">

    <div class="lpf-header">
        <div class="lpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="lpf-header-center">
            <div class="lpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="lpf-title-sub">แผนผังภาพแสดงตำแหน่งบาดแผล</div>
        </div>
        <div class="lpf-header-right">
            <div class="lpf-doc-box">
                <div class="lpf-doc-line">รายงานที่ <span class="lpf-rpt-no-mirror"></span> / 25<span class="lpf-rpt-year-mirror"></span></div>
                <div class="lpf-doc-line">หน้าที่ <span class="lpf-cur-page">6</span> / <span class="lpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:8px; font-size:12px;">
        <div class="lpf-fr">
            <span class="lpf-fl">ชื่อ สกุลผู้เสียชีวิต/บาดเจ็บ</span>
            <input type="text" class="lpf-inp" name="victim_name_life_diagram" id="lpf_victim_name_diagram"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button><span class="lpf-fl">อายุ</span>
            <input type="text" class="lpf-inp-s" name="victim_age_life_diagram" id="lpf_victim_age_diagram" style="max-width:30px;">
            <span class="lpf-fl">ปี</span>
            <span class="lpf-fl" style="margin-left:8px;">แพทย์ผู้ชันสูตร</span>
            <input type="text" class="lpf-inp" name="autopsy_doctor_life" id="lpf_autopsy_doctor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>
    </div>

    <div class="lpf-body-diagram-area">
        <img src="./assets/images/body_diagram.png" alt="Body Diagram" style="position:absolute; top:0; left:0; width:100%; height:100%; object-fit:contain; z-index:1; pointer-events:none;">
        <canvas id="lpf_body_diagram_canvas" style="position:absolute; top:0; left:0; width:100%; height:100%; z-index:2; background:transparent;"></canvas>
        <input type="hidden" name="body_diagram_data_life" id="lpf_body_diagram_data">
        <input type="hidden" name="body_diagram_strokes_life" id="lpf_body_diagram_strokes">
    </div>
    <div style="text-align:right; margin-top:4px; display:flex; justify-content:flex-end; gap:6px; align-items:center;">
        <button type="button" class="lpf-add-btn" onclick="sketchUndo('lpf_body_diagram_canvas')" style="padding:2px 8px;"><i class="fas fa-undo"></i> ย้อนกลับ</button>
        <button type="button" class="lpf-add-btn" id="lpf_body_diagram_eraser_btn" onclick="sketchToggleEraser('lpf_body_diagram_canvas')" style="padding:2px 8px;" title="ยางลบ"><i class="fas fa-eraser"></i></button>
        <div style="display:flex;align-items:center;gap:4px;">
            <i class="fas fa-pen" style="font-size:10px;color:#666;"></i>
            <input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize('lpf_body_diagram_canvas',this.value);this.nextElementSibling.textContent=this.value+'px'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">
            <span style="font-size:11px;color:#666;min-width:35px;">2px</span>
        </div>
        <label style="margin:0;cursor:pointer;" title="เลือกสี"><input type="color" value="#cc0000" onchange="sketchSetColor('lpf_body_diagram_canvas',this.value)" style="width:26px;height:22px;border:1px solid #aaa;border-radius:3px;padding:0;cursor:pointer;"></label>
        <button type="button" class="lpf-add-btn" onclick="lpfClearCanvas('lpf_body_diagram_canvas')">ล้างกระดาน</button>
    </div>

    <div class="lpf-footer">
        <div class="lpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="lpf-footer-right">F-CS-09 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 7 (EVIDENCE COLLECTION) ======== -->
<!-- ================================================================ -->
<div class="lpf-page">

    <div class="lpf-header">
        <div class="lpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="lpf-header-center">
            <div class="lpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="lpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต</div>
            <div style="font-size:11px; font-weight:600;">บันทึกการตรวจเก็บวัตถุพยาน</div>
        </div>
        <div class="lpf-header-right">
            <div class="lpf-doc-box">
                <div class="lpf-doc-line">รายงานที่ <span class="lpf-rpt-no-mirror"></span> / 25<span class="lpf-rpt-year-mirror"></span></div>
                <div class="lpf-doc-line">หน้าที่ <span class="lpf-cur-page">7</span> / <span class="lpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:6px;">
        <div class="lpf-fr">
            <span class="lpf-fl">วันที่ตรวจสถานที่เกิดเหตุ</span>
            <input type="date" class="lpf-inp" name="measurement_inspection_date_life" id="lpf_measurement_date" style="text-align:center;">
            <span class="lpf-fl">เวลาประมาณ</span>
            <input type="time" class="lpf-inp" name="measurement_inspection_time_life" id="lpf_measurement_time" style="text-align:center;">
            <span class="lpf-fl">น.</span>
        </div>
        <button type="button" class="lpf-add-btn" style="float:right;" onclick="lpfAddCollectionRow()">+ เพิ่มรายการ</button>
    </div>

    <table class="lpf-ev-table" style="width:100%; border-collapse:collapse; font-size:10.5px;">
        <thead>
            <tr>
                <th rowspan="2" style="border:1.5px solid #000; width:30px; padding:2px; text-align:center; vertical-align:middle;">ลำดับ</th>
                <th rowspan="2" style="border:1.5px solid #000; padding:2px; text-align:center; vertical-align:middle;">รายการวัตถุพยาน</th>
                <th rowspan="2" style="border:1.5px solid #000; width:35px; padding:2px; text-align:center; vertical-align:middle;">จำนวน</th>
                <th rowspan="2" style="border:1.5px solid #000; padding:2px; text-align:center; vertical-align:middle;">บริเวณที่ตรวจพบ</th>
                <th rowspan="2" style="border:1.5px solid #000; width:35px; padding:2px; text-align:center; vertical-align:middle;">ป้าย<br>หมายเลข</th>
                <th colspan="3" style="border:1.5px solid #000; padding:2px; text-align:center;">การบรรจุหีบห่อ</th>
                <th colspan="2" style="border:1.5px solid #000; padding:2px; text-align:center;">การดำเนินการ<br>เกี่ยวกับวัตถุพยาน</th>
                <th rowspan="2" style="border:1.5px solid #000; width:45px; padding:2px; text-align:center; vertical-align:middle;">หมายเหตุ</th>
                <th rowspan="2" style="border:1.5px solid #000; padding:2px; text-align:center; vertical-align:middle;">การตรวจพิสูจน์</th>
                <th rowspan="2" style="border:1.5px solid #000; width:20px; padding:2px; text-align:center; vertical-align:middle; font-size:9px;">ลบ</th>
            </tr>
            <tr>
                <th style="border:1.5px solid #000; width:35px; padding:2px; text-align:center;">พลาสติก</th>
                <th style="border:1.5px solid #000; width:35px; padding:2px; text-align:center;">กระดาษ</th>
                <th style="border:1.5px solid #000; width:30px; padding:2px; text-align:center;">อื่นๆ</th>
                <th style="border:1.5px solid #000; width:40px; padding:2px; text-align:center;">ส่งคืน<br>พงส.</th>
                <th style="border:1.5px solid #000; width:30px; padding:2px; text-align:center;">อื่นๆ</th>
            </tr>
        </thead>
        <tbody id="lpf_collection_tbody">
            <tr>
                <td style="text-align:center;">1</td>
                <td><input type="text" name="measurement_item_life[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>
                <td><input type="text" name="measurement_quantity_life[]" style="width:30px; text-align:center;"></td>
                <td><input type="text" name="measurement_area_life[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>
                <td><input type="text" name="measurement_label_number_life[]" style="width:30px; text-align:center;"></td>
                <td><input type="checkbox" name="measurement_package_plastic_check_0" value="1"></td>
                <td><input type="checkbox" name="measurement_package_paper_check_0" value="1"></td>
                <td><input type="checkbox" name="measurement_package_other_check_0" value="1"></td>
                <td><input type="checkbox" name="measurement_action_return_check_0" value="1"></td>
                <td><input type="checkbox" name="measurement_action_other_check_0" value="1"></td>
                <td><input type="text" name="measurement_remark_life[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>
                <td>
                    <select name="measurement_forensic_unit_life[]" style="font-size:9px; padding:1px; width:100%;">
                        <option value="">--</option>
                        <option value="fingerprint">ลายนิ้วมือแฝง</option>
                        <option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option>
                        <option value="chemical">เคมีฟิสิกส์</option>
                        <option value="drug">ยาเสพติด</option>
                        <option value="gun">อาวุธปืน</option>
                        <option value="document">เอกสาร</option>
                        <option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option>
                    </select>
                </td>
                <td><button type="button" class="lpf-del-btn" onclick="lpfDelRow(this)">×</button></td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top:12px; display:flex; flex-direction:column; align-items:flex-end;">
        <div class="lpf-fr" style="width:auto;"><span class="lpf-fl">ผู้จดบันทึก</span><input type="text" class="lpf-inp" name="measurement_recorder_life" id="lpf_measurement_recorder" style="width:250px;"></div>
        <div class="lpf-fr" style="width:auto;"><span class="lpf-fl">วัน /เวลา</span><input type="datetime-local" class="lpf-inp" name="measurement_datetime_life" id="lpf_measurement_datetime" style="width:250px; text-align:center;"></div>
    </div>

    <div class="lpf-footer">
        <div class="lpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="lpf-footer-right">F-CS-09 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 8+ (PHOTOS) ==================== -->
<!-- ================================================================ -->
<div class="lpf-page lpf-photo-page" data-photo-page="1">

    <div class="lpf-header">
        <div class="lpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="lpf-header-center">
            <div class="lpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="lpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต</div>
            <div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ</div>
        </div>
        <div class="lpf-header-right">
            <div class="lpf-doc-box">
                <div class="lpf-doc-line">รายงานที่ <span class="lpf-rpt-no-mirror"></span> / 25<span class="lpf-rpt-year-mirror"></span></div>
                <div class="lpf-doc-line">หน้าที่ <span class="lpf-cur-page">8</span> / <span class="lpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:8px;">
        <div class="lpf-fr" style="margin-bottom:6px;">
            <span class="lpf-fl">วันที่ตรวจสถานที่เกิดเหตุ</span>
            <input type="date" class="lpf-inp" name="photo_inspect_date_life" style="text-align:center;">
            <span class="lpf-fl">เวลาประมาณ</span>
            <input type="time" class="lpf-inp" name="photo_inspect_time_life" style="text-align:center;">
            <span class="lpf-fl">น.</span>
        </div>
        <div class="lpf-fr" style="flex-wrap:nowrap; margin-bottom:6px;">
            <span class="lpf-fl">รหัสภาพถ่ายที่</span>
            <input type="text" class="lpf-inp" name="photo_id_start_life" style="min-width:40px;" readonly>
            <span class="lpf-fl">ถึง</span>
            <input type="text" class="lpf-inp" name="photo_id_end_life" style="min-width:40px;" readonly>
            <span class="lpf-fl">จำนวน</span>
            <input type="text" class="lpf-inp-s" name="photo_amount_life" style="max-width:40px; text-align:center;" readonly>
            <span class="lpf-fl">ภาพ</span>
        </div>
        <div style="font-size:10.5px; font-style:italic; margin-top:2px;">(ตามภาพถ่ายรวมที่แนบ)</div>
    </div>

    <!-- Drag & Drop zone for PDF form -->
    <div class="lpf-photo-dropzone" id="lpf_photo_dropzone">
        <i class="fas fa-cloud-upload-alt" style="font-size:1.2rem; color:#90a4ae;"></i>
        <div style="font-size:10px; color:#666; margin-top:2px;">ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือก</div>
    </div>
    <input type="file" id="lpf_photo_input_gallery" accept="image/*" multiple style="display:none;" onchange="lpfPreviewPhotos(this)">
    <input type="file" id="lpf_photo_input_camera" accept="image/*" capture="environment" multiple style="display:none;" onchange="lpfPreviewPhotos(this)">

    <!-- Photo Grid (35 photos per page, populated by JS) -->
    <div class="lpf-photo-grid" id="lpf_photo_grid_1"></div>

    <div style="margin-top:auto;">
        <div style="display:flex; flex-direction:column; align-items:flex-end; margin-bottom:6px;">
        <div class="lpf-fr" style="width:auto;">
            <span class="lpf-fl">ผู้จดบันทึก</span>
            <select class="lpf-sel" name="photographer_name_life" id="lpf_photographer_name" style="width:250px;">
                <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือก --', $lpfInspectorOptions) ?>
            </select>
        </div>
        <div class="lpf-fr" style="width:auto;">
            <span class="lpf-fl">วัน /เวลา</span>
            <input type="datetime-local" class="lpf-inp" name="photographer_datetime_life" id="lpf_photographer_datetime" style="width:250px; text-align:center;">
        </div>
        </div>
        <div class="lpf-footer">
            <div class="lpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
            <div class="lpf-footer-right">F-CS-09 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
        </div>
    </div>
</div>

<!-- Container สำหรับหน้ารูปถ่ายเพิ่มเติม -->
<div id="lpf_extra_photo_pages"></div>

<!-- ปุ่มเพิ่มหน้ากระดาษรูปถ่าย -->
<button type="button" class="lpf-add-photo-page-btn" onclick="lpfAddPhotoPage()">
    <i class="fas fa-plus me-1"></i> เพิ่มหน้าบันทึกการถ่ายภาพ
</button>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <div>
                    <button type="button" class="btn btn-success btn-sm" id="btn_save_life_pdf" onclick="lpfSaveViaStandardForm()">
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
    // ===== Radio-toggle (mutually exclusive checkboxes) =====
    document.querySelectorAll('#lifeFormPdfModal .lpf-radio-toggle').forEach(function(cb) {
        cb.addEventListener('change', function() {
            if (!this.checked) return;
            var grp = this.getAttribute('data-group');
            if (!grp) return;
            document.querySelectorAll('#lifeFormPdfModal .lpf-radio-toggle[data-group="' + grp + '"]').forEach(function(other) {
                if (other !== cb) other.checked = false;
            });
        });
    });

    // ===== Inspector rows =====
    var lpfInspectorIdx = 1;
    window.lpfAddInspector = function() {
        lpfInspectorIdx++;
        var c = document.getElementById('lpf_inspector_container');
        var div = document.createElement('div');
        div.className = 'lpf-si lpf-inspector-row';
        div.innerHTML = '<span class="lpf-si-no">5.' + lpfInspectorIdx + '</span>' +
            '<select class="lpf-sel" name="inspector_id[]"><?= addslashes($lpfInspectorOptions) ?></select>' +
            ' <button type="button" class="lpf-del-btn" onclick="this.parentElement.remove(); if(typeof lpfRenumberInspectors===\'function\') lpfRenumberInspectors();">×</button>';
        c.appendChild(div);
    };

    // ===== Renumber inspector rows =====
    window.lpfRenumberInspectors = function() {
        var rows = document.querySelectorAll('#lpf_inspector_container .lpf-inspector-row');
        rows.forEach(function(row, idx) {
            var noSpan = row.querySelector('.lpf-si-no');
            if (noSpan) noSpan.textContent = '5.' + (idx + 1);
        });
        lpfInspectorIdx = rows.length;
    };

    // ===== Victim rows =====
    window.lpfAddVictim = function() {
        var c = document.getElementById('lpf_victim_container');
        var div = document.createElement('div');
        div.className = 'lpf-victim-row';
        div.style.cssText = 'margin-top:3px; padding:2px 0; border-top:1px dotted #ccc;';
        div.innerHTML = '<div class="lpf-fr">' +
            '<span class="lpf-fl">ประเภท</span>' +
            '<select class="lpf-sel" name="victim_type_life[]" style="max-width:100px;">' +
            '<option value="">--เลือก--</option><option value="ผู้เสียชีวิต">ผู้เสียชีวิต</option><option value="ผู้บาดเจ็บ">ผู้บาดเจ็บ</option><option value="ผู้สูญหาย">ผู้สูญหาย</option></select>' +
            '<span class="lpf-fl" style="margin-left:6px;">ชื่อ</span>' +
            '<input type="text" class="lpf-inp" name="victim_name_life[]">' +
            '<span class="lpf-fl" style="margin-left:4px;">อายุ</span>' +
            '<input type="text" class="lpf-inp-s" name="victim_age_life[]" style="max-width:30px;">' +
            '<span class="lpf-fl">ปี</span>' +
            ' <button type="button" class="lpf-del-btn" onclick="this.closest(\'.lpf-victim-row\').remove()">×</button>' +
            '</div>';
        c.appendChild(div);
    };

    // ===== Evidence table rows =====
    window.lpfAddEvidenceRow = function() {
        var tbody = document.getElementById('lpf_evidence_tbody');
        if (!tbody) return;
        var rowIdx = tbody.querySelectorAll('tr').length;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" name="evidence_label_life[]" style="width:35px;" value="' + (rowIdx + 1) + '"></td>' +
            '<td><input type="text" name="evidence_item_life[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
            '<td><input type="text" name="evidence_level_1_life_' + rowIdx + '" style="width:28px;"></td>' +
            '<td><input type="text" name="evidence_level_2_life_' + rowIdx + '" style="width:28px;"></td>' +
            '<td><input type="text" name="evidence_level_3_life_' + rowIdx + '" style="width:28px;"></td>' +
            '<td><input type="text" name="evidence_level_4_life_' + rowIdx + '" style="width:28px;"></td>' +
            '<td><input type="text" name="evidence_azimuth_life[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
            '<td><input type="text" name="evidence_remark_life[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
            '<td><select name="evidence_lab_unit_life[]" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option></select></td>' +
            '<td><button type="button" class="lpf-del-btn" onclick="lpfDelRow(this)">×</button></td>';
        tbody.appendChild(tr);
    };

    // ===== Collection table rows =====
    window.lpfAddCollectionRow = function() {
        var tbody = document.getElementById('lpf_collection_tbody');
        if (!tbody) return;
        var rowIdx = tbody.querySelectorAll('tr').length;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td style="text-align:center;">' + (rowIdx + 1) + '</td>' +
            '<td><input type="text" name="measurement_item_life[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
            '<td><input type="text" name="measurement_quantity_life[]" style="width:30px; text-align:center;"></td>' +
            '<td><input type="text" name="measurement_area_life[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
            '<td><input type="text" name="measurement_label_number_life[]" style="width:30px; text-align:center;"></td>' +
            '<td><input type="checkbox" name="measurement_package_plastic_check_' + rowIdx + '" value="1"></td>' +
            '<td><input type="checkbox" name="measurement_package_paper_check_' + rowIdx + '" value="1"></td>' +
            '<td><input type="checkbox" name="measurement_package_other_check_' + rowIdx + '" value="1"></td>' +
            '<td><input type="checkbox" name="measurement_action_return_check_' + rowIdx + '" value="1"></td>' +
            '<td><input type="checkbox" name="measurement_action_other_check_' + rowIdx + '" value="1"></td>' +
            '<td><input type="text" name="measurement_remark_life[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
            '<td><select name="measurement_forensic_unit_life[]" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option></select></td>' +
            '<td><button type="button" class="lpf-del-btn" onclick="lpfDelRow(this)">×</button></td>';
        tbody.appendChild(tr);
    };

    // ===== Delete row =====
    window.lpfDelRow = function(btn) {
        var tr = btn.closest('tr');
        if (tr) tr.remove();
    };

    // ===== Photo source chooser =====
    window.lpfChoosePhotoSource = function() {
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
                document.getElementById('lpf_photo_input_gallery').click();
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                document.getElementById('lpf_photo_input_camera').click();
            }
        });
    };

    // ===== Photo handling (★ matching bomb form pattern with FileReader + dual src support) =====
    window.lpfPreviewPhotos = function(input) {
        if (!input.files || !input.files.length) return;
        lpfHandlePhotoFiles(input.files);
        input.value = '';
    };

    function lpfHandlePhotoFiles(files) {
        if (!files || !files.length) return;
        if (typeof window.attachmentStoreLife === 'undefined') window.attachmentStoreLife = [];
        Array.from(files).forEach(function(file) {
            if (!file.type.startsWith('image/')) return;
            var fileId = 'lpf_' + Date.now() + '_' + Math.random().toString(36).substr(2,5);
            var objectUrl = URL.createObjectURL(file);
            window.attachmentStoreLife.push({ file: file, id: fileId, src: objectUrl, name: file.name });
        });
        lpfRenderPhotosFromStore();
        if (typeof renderLifeAttachmentGrid === 'function') renderLifeAttachmentGrid();
    }

    // ===== Drag & Drop + Click handlers for dropzone =====
    var lpfPhotoDZ = document.getElementById('lpf_photo_dropzone');
    if (lpfPhotoDZ) {
        lpfPhotoDZ.addEventListener('click', function() {
            lpfChoosePhotoSource();
        });
        lpfPhotoDZ.addEventListener('dragenter', function(e) { e.preventDefault(); this.classList.add('dragover'); });
        lpfPhotoDZ.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('dragover'); });
        lpfPhotoDZ.addEventListener('dragleave', function(e) { e.preventDefault(); this.classList.remove('dragover'); });
        lpfPhotoDZ.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                lpfHandlePhotoFiles(e.dataTransfer.files);
            }
        });
    }

    // ===== ปุ่มเพิ่มหน้า (manual) — ยังใช้ได้เพื่อเพิ่มหน้าว่าง =====
    window.lpfAddPhotoPage = function() {
        lpfRenderPhotosFromStore();
    };

    window.lpfRemovePhotoPage = function(btn) {
        var page = btn.closest('.lpf-photo-page');
        if (page) page.remove();
        lpfUpdatePageNumbers();
    };

    // ===== Update page numbers dynamically =====
    window.lpfUpdatePageNumbers = function() {
        var pages = document.querySelectorAll('#lifeFormPdfModal .lpf-page');
        var total = pages.length;
        pages.forEach(function(page, idx) {
            var curSpan = page.querySelector('.lpf-cur-page');
            var totalSpan = page.querySelector('.lpf-total-page');
            if (curSpan) curSpan.textContent = idx + 1;
            if (totalSpan) totalSpan.textContent = total;
        });
    };
    lpfUpdatePageNumbers();

    // ===== Render photos จาก attachmentStoreLife ลง grid (35 รูป/หน้า) =====
    var LFP_PHOTOS_PER_PAGE = 35;

    window.lpfRenderPhotosFromStore = function() {
        var photos = (typeof window.attachmentStoreLife !== 'undefined') ? window.attachmentStoreLife : [];
        var pagesNeeded = Math.max(1, Math.ceil(photos.length / LFP_PHOTOS_PER_PAGE));
        console.log('[LFP] lpfRenderPhotosFromStore called, photos:', photos.length, 'pagesNeeded:', pagesNeeded);

        // หน้าแรก → grid อยู่ใน #lpf_photo_grid_1
        var grid1 = document.getElementById('lpf_photo_grid_1');
        if (grid1) {
            var startIdx = 0;
            var endIdx = Math.min(LFP_PHOTOS_PER_PAGE, photos.length);
            grid1.innerHTML = '';
            for (var i = startIdx; i < endIdx; i++) {
                grid1.appendChild(lpfCreatePhotoCell(photos[i], i));
            }
        }

        // สร้าง/ลบ extra pages ตามจำนวนรูป
        var extraContainer = document.getElementById('lpf_extra_photo_pages');
        if (extraContainer) {
            extraContainer.innerHTML = '';
            for (var p = 2; p <= pagesNeeded; p++) {
                var startI = (p - 1) * LFP_PHOTOS_PER_PAGE;
                var endI = Math.min(p * LFP_PHOTOS_PER_PAGE, photos.length);
                extraContainer.appendChild(lpfCreatePhotoPageElement(p, photos, startI, endI));
            }
        }

        lpfUpdatePhotoAmount();
        if (typeof lpfUpdatePageNumbers === 'function') lpfUpdatePageNumbers();
        if (typeof lpfLifeUpdateReportMirrors === 'function') lpfLifeUpdateReportMirrors();
    };

    function lpfCreatePhotoCell(item, idx) {
        var wrapper = document.createElement('div');
        wrapper.className = 'lpf-photo-cell-wrapper';
        var safeName = (item.name || 'photo').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
        wrapper.innerHTML =
            '<div class="lpf-photo-cell" style="position:relative;">' +
                '<img src="' + item.src + '" alt="' + safeName + '" loading="lazy">' +
                '<button type="button" class="lpf-cell-delete" onclick="event.stopPropagation(); lpfRemovePhoto(\'' + item.id + '\')">&times;</button>' +
            '</div>' +
            '<div class="lpf-cell-filename" title="' + safeName + '">' + (item.name || 'photo') + '</div>';
        return wrapper;
    }

    function lpfCreatePhotoPageElement(pageNum, photos, startIdx, endIdx) {
        var page = document.createElement('div');
        page.className = 'lpf-page lpf-photo-page';
        page.setAttribute('data-photo-page', pageNum);
        page.innerHTML =
            '<div class="lpf-header">' +
                '<div class="lpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>' +
                '<div class="lpf-header-center">' +
                    '<div class="lpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>' +
                    '<div class="lpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต</div>' +
                    '<div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ (ต่อ)</div>' +
                '</div>' +
                '<div class="lpf-header-right">' +
                    '<div class="lpf-doc-box">' +
                        '<div class="lpf-doc-line">รายงานที่ <span class="lpf-rpt-no-mirror"></span> / 25<span class="lpf-rpt-year-mirror"></span></div>' +
                        '<div class="lpf-doc-line">หน้าที่ <span class="lpf-cur-page"></span> / <span class="lpf-total-page"></span></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div style="margin-bottom:8px;">' +
                '<div class="lpf-fr" style="flex-wrap:nowrap; margin-bottom:6px;">' +
                    '<span class="lpf-fl">รหัสภาพถ่ายที่</span>' +
                    '<input type="text" class="lpf-inp lpf-page-photo-start" style="min-width:40px;" readonly>' +
                    '<span class="lpf-fl">ถึง</span>' +
                    '<input type="text" class="lpf-inp lpf-page-photo-end" style="min-width:40px;" readonly>' +
                '</div>' +
                '<div style="font-size:10.5px; font-style:italic; margin-top:2px;">(ตามภาพถ่ายรวมที่แนบ)</div>' +
            '</div>' +
            '<div class="lpf-photo-grid" id="lpf_photo_grid_' + pageNum + '"></div>' +
            '<div style="margin-top:auto;">' +
                '<div class="lpf-footer">' +
                    '<div class="lpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>' +
                    '<div class="lpf-footer-right">F-CS-09 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>' +
                '</div>' +
            '</div>';

        // Populate grid
        var grid = page.querySelector('.lpf-photo-grid');
        for (var i = startIdx; i < endIdx; i++) {
            grid.appendChild(lpfCreatePhotoCell(photos[i], i));
        }
        return page;
    }

    window.lpfUpdatePhotoAmount = function() {
        var form = document.getElementById('lifeFormPdf');
        if (!form) return;

        var photos = (typeof window.attachmentStoreLife !== 'undefined' && Array.isArray(window.attachmentStoreLife)) ? window.attachmentStoreLife : [];
        var totalPhotos = photos.length;

        var startInput = form.querySelector('input[name="photo_id_start_life"]');
        var endInput = form.querySelector('input[name="photo_id_end_life"]');
        var amountInput = form.querySelector('input[name="photo_amount_life"]');

        if (startInput && endInput && amountInput) {
            if (totalPhotos > 0) {
                var lastIdxPage1 = Math.min(LFP_PHOTOS_PER_PAGE, totalPhotos) - 1;
                startInput.value = photos[0].name || 'photo';
                endInput.value = photos[lastIdxPage1].name || 'photo';
                amountInput.value = String(totalPhotos);
            } else {
                startInput.value = '';
                endInput.value = '';
                amountInput.value = '';
            }
        }

        // Update extra pages photo ranges
        var extraPages = document.querySelectorAll('#lpf_extra_photo_pages .lpf-photo-page');
        extraPages.forEach(function(page, pIdx) {
            var pageNum = pIdx + 2;
            var startI = (pageNum - 1) * LFP_PHOTOS_PER_PAGE;
            var endI = Math.min(pageNum * LFP_PHOTOS_PER_PAGE, totalPhotos) - 1;
            var ps = page.querySelector('.lpf-page-photo-start');
            var pe = page.querySelector('.lpf-page-photo-end');
            if (ps && photos[startI]) ps.value = photos[startI].name || 'photo';
            if (pe && photos[endI]) pe.value = photos[endI].name || 'photo';
        });
    };

    // ===== ลบรูปจาก store (★ track BLOB file_id สำหรับลบบน server ด้วย — เหมือน property form) =====
    window.lpfRemovePhoto = function(fileId) {
        if (typeof window.attachmentStoreLife === 'undefined') return;
        var item = window.attachmentStoreLife.find(function(x) { return x.id === fileId; });
        if (item && item.existing && item.db_file_id) {
            // BLOB photo → track file_id สำหรับลบจาก DB
            if (typeof window.deletedExistingPhotosLife !== 'undefined') {
                window.deletedExistingPhotosLife.push({ file_id: item.db_file_id });
            }
        } else if (item && item.existing && !item.db_file_id && item.disk_filename) {
            // Disk photo → track filename สำหรับลบจากดิสก์
            if (typeof window.deletedExistingPhotosLife !== 'undefined') {
                window.deletedExistingPhotosLife.push(item.disk_filename);
            }
        }
        window.attachmentStoreLife = window.attachmentStoreLife.filter(function(x) { return x.id !== fileId; });
        lpfRenderPhotosFromStore();
        // อัปเดต standard form grid ด้วย (ถ้ามี)
        if (typeof renderLifeAttachmentGrid === 'function') renderLifeAttachmentGrid();
    };

    // ===== Canvas utilities =====
    function lpfIsCanvasBlank(canvas) {
        var ctx = canvas.getContext('2d');
        var pixelData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        for (var i = 3; i < pixelData.length; i += 4) {
            if (pixelData[i] !== 0) return false;
        }
        return true;
    }

    window.lpfClearCanvas = function(canvasId) {
        // เพิ่ม confirm popup สำหรับ body diagram
        if (canvasId === 'lpf_body_diagram_canvas' || canvasId === 'lpf_scene_sketch_canvas') {
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
                        performClearCanvas(canvasId);
                    }
                });
                return;
            } else {
                if (!confirm('คุณต้องการล้างภาพทั้งหมดหรือไม่?')) {
                    return;
                }
            }
        }
        
        performClearCanvas(canvasId);
    };
    
    function performClearCanvas(canvasId) {
        if (typeof signaturePads !== 'undefined' && signaturePads[canvasId]) {
            signaturePads[canvasId].clear();
        } else {
            var canvas = document.getElementById(canvasId);
            if (canvas) {
                // ล้าง canvas แล้ว re-init drawing handler
                canvas.dataset.lpfDrawInit = '0';
                var ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                var penColor = (canvasId === 'lpf_body_diagram_canvas') ? '#c00' : '#000';
                initLpfCanvas(canvasId, penColor);
            }
        }
        if (typeof sketchSetEraser === 'function') sketchSetEraser(canvasId, false);

        // ★ ล้าง canvas ของฟอร์มมาตรฐานด้วย (ป้องกัน fallback เจอรูปเก่า)
        var stdCanvasMap = {
            'lpf_sig_receiver': 'sig-canvas-receiver-life',
            'lpf_sig_sender': 'sig-canvas-sender-life',
            'lpf_scene_sketch_canvas': 'scene_sketch_canvas_life',
            'lpf_body_diagram_canvas': 'body_diagram_canvas_life'
        };
        var stdCanvasId = stdCanvasMap[canvasId];
        if (stdCanvasId) {
            if (typeof signaturePads !== 'undefined' && signaturePads[stdCanvasId]) {
                signaturePads[stdCanvasId].clear();
            } else {
                var stdCanvas = document.getElementById(stdCanvasId);
                if (stdCanvas) {
                    var stdCtx = stdCanvas.getContext('2d');
                    stdCtx.clearRect(0, 0, stdCanvas.width, stdCanvas.height);
                }
            }
        }

        // clear hidden inputs ที่ผูกกับ canvas
        if (canvasId === 'lpf_sig_receiver') {
            var inpR = document.getElementById('lpf_receiver_sig_data');
            if (inpR) inpR.value = '';
        }
        if (canvasId === 'lpf_sig_sender') {
            var inpS = document.getElementById('lpf_sender_sig_data');
            if (inpS) inpS.value = '';
        }
        if (canvasId === 'lpf_scene_sketch_canvas') {
            var inpSk = document.getElementById('lpf_scene_sketch_data');
            if (inpSk) inpSk.value = '';
        }
        if (canvasId === 'lpf_body_diagram_canvas') {
            var inpBd = document.getElementById('lpf_body_diagram_data');
            if (inpBd) inpBd.value = '';
            var inpBdStrokes = document.getElementById('lpf_body_diagram_strokes');
            if (inpBdStrokes) inpBdStrokes.value = '';
        }
    };

    function initLpfCanvas(canvasId, penColor) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;
        // ★ Force re-init เพื่อให้ eraser ใหม่ทำงาน (ลบ event listeners เก่าก่อน)
        if (canvas.dataset.lpfDrawInit === '1') {
            var newCanvas = canvas.cloneNode(true);
            newCanvas.dataset.lpfDrawInit = '0';
            canvas.parentNode.replaceChild(newCanvas, canvas);
            canvas = newCanvas;
        }
        canvas.dataset.lpfDrawInit = '1';
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
        var ctx = canvas.getContext('2d');
        var drawing = false;
        var defColor = penColor || '#000';
        // เก็บสี + eraser state ใน shared object
        if (typeof _sketchColor !== 'undefined') { _sketchOrigColor[canvasId] = defColor; _sketchColor[canvasId] = _sketchColor[canvasId] || defColor; }

        var lastPos = null;
        function isErasing() {
            return (typeof _sketchEraser !== 'undefined' && _sketchEraser[canvasId]);
        }
        function getStroke() {
            if (isErasing()) return { width: 10 };
            var c = (typeof _sketchColor !== 'undefined' && _sketchColor[canvasId]) ? _sketchColor[canvasId] : defColor;
            return { color: c, width: 2 };
        }

        function getPos(e) {
            var rect = canvas.getBoundingClientRect();
            var touch = e.touches ? e.touches[0] : e;
            return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
        }
        function start(e) {
            e.preventDefault(); drawing = true; var p = getPos(e); lastPos = p;
            if (!isErasing()) { ctx.beginPath(); ctx.moveTo(p.x, p.y); }
        }
        function move(e) {
            if (!drawing) return; e.preventDefault(); var p = getPos(e); var s = getStroke();
            if (isErasing()) {
                // ★ ใช้ clearRect แทน destination-out เพื่อลบ pixel จริงๆ
                var dx = p.x - lastPos.x;
                var dy = p.y - lastPos.y;
                var dist = Math.sqrt(dx*dx + dy*dy);
                var steps = Math.max(1, Math.ceil(dist / 2));
                for (var i = 0; i <= steps; i++) {
                    var t = i / steps;
                    var cx = lastPos.x + dx * t;
                    var cy = lastPos.y + dy * t;
                    ctx.save();
                    ctx.beginPath();
                    ctx.arc(cx, cy, s.width / 2, 0, Math.PI * 2);
                    ctx.clip();
                    ctx.clearRect(cx - s.width, cy - s.width, s.width * 2, s.width * 2);
                    ctx.restore();
                }
            } else {
                ctx.lineTo(p.x, p.y); ctx.strokeStyle = s.color; ctx.lineWidth = s.width; ctx.lineCap = 'round'; ctx.stroke();
            }
            lastPos = p;
        }
        function end() { drawing = false; lastPos = null; }

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseup', end);
        canvas.addEventListener('mouseleave', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end);
    }

    // ===== Auto-fill position for PDF form receiver/sender =====
    document.addEventListener('change', function(e) {
        var target = e.target;
        if (target.id !== 'lpf_receiver_name' && target.id !== 'lpf_sender_name') return;
        var idEmp = target.value;
        var posInputId = (target.id === 'lpf_receiver_name') ? 'lpf_receiver_position' : 'lpf_sender_position';
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
    window._lpfSketchPages = window._lpfSketchPages || [];

    // Register first page
    window._lpfSketchPages.push({
        id: 'lpf_sketch_page_1',
        canvasId: 'lpf_sketch_page_1_canvas',
        bgImage: null,
        bgImageData: null,
        _bgImgEl: null
    });

    function _initSketchDraw(canvasId) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || canvas.dataset.lpfInit === '1') return;
        canvas.dataset.lpfInit = '1';
        
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

    window.lpfSketchAddPage = function() {
        _sketchPageCounter++;
        var n = _sketchPageCounter;
        var pid = 'lpf_sketch_page_' + n;
        var canvasId = pid + '_canvas';
        var pageData = { id: pid, canvasId: canvasId, bgImage: null, bgImageData: null, _bgImgEl: null };
        var pageDiv = document.createElement('div');
        pageDiv.className = 'lpf-page lpf-sketch-page';
        pageDiv.id = pid;
        pageDiv.setAttribute('data-sketch-page', n);
        pageDiv.innerHTML =
            '<div class="lpf-header">' +
                '<div class="lpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>' +
                '<div class="lpf-header-center">' +
                    '<div class="lpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>' +
                    '<div class="lpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับชีวิต</div>' +
                '</div>' +
                '<div class="lpf-header-right">' +
                    '<div class="lpf-doc-box">' +
                        '<div class="lpf-doc-line">รายงานที่ <span class="lpf-rpt-no-mirror"></span> / 25<span class="lpf-rpt-year-mirror"></span></div>' +
                        '<div class="lpf-doc-line">หน้าที่ <span class="lpf-cur-page"></span> / <span class="lpf-total-page"></span></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +

            '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">' +
                '<div></div>' +
                '<div style="font-size:13px; font-weight:600;">แผนผังสังเขป (ต่อ)</div>' +
                '<div style="display:flex; gap:4px; align-items:center;">' +
                    '<label class="lpf-add-btn" style="padding:2px 8px; cursor:pointer; margin:0;" title="แนบรูปภาพพื้นหลัง">' +
                        '<i class="fas fa-image me-1"></i> แนบรูป' +
                        '<input type="file" accept="image/*" style="display:none;" onchange="lpfSketchAttachImage(\'' + pid + '\',this)">' +
                    '</label>' +
                    '<button type="button" class="lpf-add-btn" style="padding:2px 8px;" onclick="lpfSketchRemoveBg(\'' + pid + '\')" title="ลบรูปพื้นหลัง"><i class="fas fa-times"></i> ลบรูป</button>' +
                    '<button type="button" class="lpf-add-btn" style="padding:2px 8px; border-color:#dc3545; color:#dc3545;" onclick="lpfSketchRemovePage(\'' + pid + '\')" title="ลบหน้านี้"><i class="fas fa-trash-alt"></i> ลบหน้า</button>' +
                '</div>' +
            '</div>' +

            '<div class="lpf-sketch-viewport" id="' + pid + '_viewport">' +
                '<div class="lpf-sketch-canvas-wrap" id="' + pid + '_wrap" style="aspect-ratio:' + SKETCH_W + '/' + SKETCH_H + ';">' +
                    '<canvas id="' + canvasId + '" width="' + SKETCH_W + '" height="' + SKETCH_H + '"></canvas>' +
                '</div>' +
                '<div class="lpf-not-to-scale">* NOT TO SCALE</div>' +
            '</div>' +

            '<div style="text-align:right; margin-top:4px; display:flex; justify-content:flex-end; gap:6px; align-items:center;">' +
                '<button type="button" class="lpf-add-btn" onclick="sketchUndo(\'' + canvasId + '\')" style="padding:2px 8px;"><i class="fas fa-undo"></i> ย้อนกลับ</button>' +
                '<button type="button" class="lpf-add-btn" id="' + canvasId + '_eraser_btn" onclick="sketchToggleEraser(\'' + canvasId + '\')" style="padding:2px 8px;" title="ยางลบ"><i class="fas fa-eraser"></i></button>' +
                '<div style="display:flex;align-items:center;gap:4px;">' +
                    '<i class="fas fa-pen" style="font-size:10px;color:#666;"></i>' +
                    '<input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize(\'' + canvasId + '\',this.value);this.nextElementSibling.textContent=this.value+\'px\'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">' +
                    '<span style="font-size:11px;color:#666;min-width:35px;">2px</span>' +
                '</div>' +
                '<label style="margin:0;cursor:pointer;" title="เลือกสี"><input type="color" value="#000000" onchange="sketchSetColor(\'' + canvasId + '\',this.value)" style="width:26px;height:22px;border:1px solid #aaa;border-radius:3px;padding:0;cursor:pointer;"></label>' +
                '<button type="button" class="lpf-add-btn" onclick="lpfClearCanvas(\'' + canvasId + '\')">ล้างกระดาน</button>' +
            '</div>' +

            '<div style="margin-bottom:8px;">' +
                '<div class="lpf-fr"><span class="lpf-fl">หมายเหตุ</span><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></div>' +
                '<textarea class="lpf-ta" rows="5"></textarea>' +
            '</div>' +

            '<div class="lpf-footer">' +
                '<div class="lpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>' +
                '<div class="lpf-footer-right">F-CS-09 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>' +
            '</div>';

        var allSketchPages = document.querySelectorAll('#lifeFormPdfModal .lpf-sketch-page');
        var lastSketchPage = allSketchPages[allSketchPages.length - 1];
        if (lastSketchPage && lastSketchPage.nextElementSibling) {
            lastSketchPage.parentNode.insertBefore(pageDiv, lastSketchPage.nextElementSibling);
        } else {
            var body = document.querySelector('#lifeFormPdfModal .lpf-body');
            if (body) body.appendChild(pageDiv);
        }
        window._lpfSketchPages.push(pageData);
        _initSketchDraw(canvasId);
        if (typeof lpfLifeUpdatePageNumbers === 'function') lpfLifeUpdatePageNumbers();
        pageDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.lpfSketchRemovePage = function(pid) {
        if (pid === 'lpf_sketch_page_1') return;
        var idx = window._lpfSketchPages.findIndex(function(p) { return p.id === pid; });
        if (idx !== -1) window._lpfSketchPages.splice(idx, 1);
        var pageDiv = document.getElementById(pid);
        if (pageDiv) pageDiv.remove();
        if (typeof lpfLifeUpdatePageNumbers === 'function') lpfLifeUpdatePageNumbers();
    };

    window.lpfSketchAttachImage = function(pid, input) {
        var file = input.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function(ev) {
            var pageData = window._lpfSketchPages.find(function(p) { return p.id === pid; });
            if (!pageData) return;
            pageData.bgImageData = ev.target.result;
            var img = new Image();
            img.onload = function() {
                pageData.bgImage = img;
                pageData._bgImgEl = img;
                var wrap = document.getElementById(pid + '_wrap');
                if (wrap) {
                    var existing = wrap.querySelector('.lpf-sketch-bg-img');
                    if (existing) existing.remove();
                    var bgImg = document.createElement('img');
                    bgImg.className = 'lpf-sketch-bg-img';
                    bgImg.src = ev.target.result;
                    wrap.insertBefore(bgImg, wrap.firstChild);
                }
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
        input.value = '';
    };

    window.lpfSketchRemoveBg = function(pid) {
        var pageData = window._lpfSketchPages.find(function(p) { return p.id === pid; });
        if (!pageData) return;
        pageData.bgImage = null;
        pageData.bgImageData = null;
        pageData._bgImgEl = null;
        var wrap = document.getElementById(pid + '_wrap');
        if (wrap) {
            var existing = wrap.querySelector('.lpf-sketch-bg-img');
            if (existing) existing.remove();
        }
    };

    window.lpfCollectSketchPagesData = function() {
        var pagesData = [];
        window._lpfSketchPages.forEach(function(p) {
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
        var inp = document.getElementById('lpf_scene_sketch_pages_data');
        if (inp) inp.value = JSON.stringify(pagesData);
        var legacyInp = document.getElementById('lpf_scene_sketch_data');
        if (legacyInp && pagesData.length > 0) legacyInp.value = pagesData[0].dataUrl;
        return pagesData;
    };

    // ===== Modal initialization =====
    var modalEl = document.getElementById('lifeFormPdfModal');
    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', function() {
            // Init multi-page sketch (first page)
            _initSketchDraw('lpf_sketch_page_1_canvas');
            
            // ใช้ fallback drawing handlers เฉพาะกรณีที่ระบบ SignaturePad หลักไม่พร้อม
            if (typeof signaturePads === 'undefined') {
                initLpfCanvas('lpf_sig_receiver', '#000');
                initLpfCanvas('lpf_sig_sender', '#000');
                initLpfCanvas('lpf_body_diagram_canvas', '#c00');
            }

            // ★ Auto-fill พฤติการณ์คดี from basic_info (rn_ReceiveNoti)
            var dstBehavior = document.querySelector('#lifeFormPdfModal textarea[name="case_behavior"]');
            if (dstBehavior && !dstBehavior.value && window._basicInfoLife) {
                dstBehavior.value = window._basicInfoLife;
            }

            // populate mirror spans pages 2+
            var docNo = document.getElementById('lpf_doc_no') ? document.getElementById('lpf_doc_no').value : '';
            var rptNo = document.getElementById('lpf_report_no') ? document.getElementById('lpf_report_no').value : '';
            var rptParts = (rptNo || '').split('/');
            var rptNum = rptParts[0] || docNo;
            var rptYear = (rptParts[1] || '').toString().slice(-2);

            var rptDisplay = document.getElementById('lpf_report_no_display');
            var yearDisplay = document.getElementById('lpf_report_year_display');
            if (rptDisplay) rptDisplay.textContent = rptNum;
            if (yearDisplay) yearDisplay.textContent = rptYear;

            modalEl.querySelectorAll('.lpf-rpt-no-mirror').forEach(function(el) { el.textContent = rptNum; });
            modalEl.querySelectorAll('.lpf-rpt-year-mirror').forEach(function(el) { el.textContent = rptYear; });
            lpfUpdatePhotoAmount();
        });
    }

    // ===== Update report number/year mirrors on ALL pages =====
    window.lpfLifeUpdateReportMirrors = function() {
        var modalEl = document.getElementById('lifeFormPdfModal');
        if (!modalEl) return;
        var docNo = document.getElementById('lpf_doc_no') ? document.getElementById('lpf_doc_no').value : '';
        var rptNo = document.getElementById('lpf_report_no') ? document.getElementById('lpf_report_no').value : '';
        var rptParts = (rptNo || '').split('/');
        var rptNum = rptParts[0] || docNo;
        var rptYear = (rptParts[1] || '').toString().slice(-2);

        modalEl.querySelectorAll('.lpf-rpt-no-mirror').forEach(function(el) { el.textContent = rptNum; });
        modalEl.querySelectorAll('.lpf-rpt-year-mirror').forEach(function(el) { el.textContent = rptYear; });
    };

    // ===== Save handler: สร้าง FormData จาก lifeFormPdf โดยตรง (แบบ Fire) + sync กลับไปฟอร์มมาตรฐาน =====
    window.lpfSaveViaStandardForm = async function() {
        var form = document.getElementById('lifeFormPdf');
        if (!form) { console.error('lifeFormPdf not found'); return; }

        // --- 0. Collect multi-page sketch data ---
        if (typeof lpfCollectSketchPagesData === 'function') {
            lpfCollectSketchPagesData();
        }

        // --- 1. เก็บ stroke data ของ body diagram จาก PDF canvas ---
        var pdfBodyPad = (typeof signaturePads !== 'undefined') ? signaturePads['lpf_body_diagram_canvas'] : null;
        var bodyStrokeInput = document.getElementById('lpf_body_diagram_strokes');
        if (bodyStrokeInput) {
            if (pdfBodyPad && !pdfBodyPad.isEmpty()) {
                bodyStrokeInput.value = JSON.stringify(pdfBodyPad.toData());
            } else {
                bodyStrokeInput.value = '';
            }
        }

        // --- 1. Sync PDF → ฟอร์มมาตรฐาน (เพื่อให้ข้อมูลตรงกัน) ---
        if (typeof syncLifeFormData === 'function') {
            syncLifeFormData('lifeFormPdf', 'incidentCheckListFormLife');
        }
        if (typeof _syncLifeInspectorToStd === 'function') _syncLifeInspectorToStd();
        if (typeof _syncLifeVictimToStd === 'function') _syncLifeVictimToStd();
        if (typeof _syncLifeEvidenceToStd === 'function') _syncLifeEvidenceToStd();
        if (typeof _syncLifeMeasurementToStd === 'function') _syncLifeMeasurementToStd();
        if (typeof syncLifeFormData === 'function') {
            syncLifeFormData('lifeFormPdf', 'incidentCheckListFormLife');
        }
        // sync hidden fields กลับไปฟอร์มมาตรฐาน
        var docNo = document.getElementById('lpf_doc_no') ? document.getElementById('lpf_doc_no').value : '';
        var rptNo = document.getElementById('lpf_report_no') ? document.getElementById('lpf_report_no').value : '';
        var notiId = document.getElementById('lpf_receiveNoti_id') ? document.getElementById('lpf_receiveNoti_id').value : '';
        if (document.getElementById('doc_no_life')) document.getElementById('doc_no_life').value = docNo;
        if (document.getElementById('report_no_life')) document.getElementById('report_no_life').value = rptNo;
        if (document.getElementById('receiveNoti_id_life')) document.getElementById('receiveNoti_id_life').value = notiId;

        // --- 2. ยืนยันก่อนบันทึก ---
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

        // --- 3. แสดง Loading ---
        Swal.fire({
            title: 'กำลังบันทึกข้อมูล...',
            html: 'กรุณารอสักครู่',
            allowOutsideClick: false,
            didOpen: function() { Swal.showLoading(); }
        });

        // --- 4. สร้าง FormData จาก lifeFormPdf โดยตรง ---
        var formData = new FormData(form);
        formData.append('form_mode', 'pdf_form');

        // ★ DEBUG: ตรวจสอบว่า FormData เก็บ field อะไรบ้าง
        console.group('🔍 [LPF DEBUG] FormData contents before send');
        var debugFields = {};
        var debugSceneFields = ['scene_preserved', 'lighting[]', 'temperature[]', 'smell',
            'has_outdoor_incident_life', 'outdoor_type[]', 'outdoor_entrance_condition',
            'outdoor_front_adjacent', 'outdoor_left_adjacent', 'outdoor_right_adjacent',
            'outdoor_back_adjacent', 'has_indoor_incident_life', 'indoor_surrounding',
            'entrance_condition', 'front_adjacent', 'police_station', 'receiveNoti_id'];
        for (var pair of formData.entries()) {
            debugFields[pair[0]] = pair[1];
        }
        console.log('Total FormData keys:', Object.keys(debugFields).length);
        console.log('All keys:', Object.keys(debugFields));
        debugSceneFields.forEach(function(f) {
            var val = formData.getAll(f);
            console.log('  ' + f + ' =', val.length > 0 ? val : '❌ MISSING');
        });
        // ★ ตรวจสอบว่า field อยู่ใน DOM ของ form หรือไม่
        var formEl = document.getElementById('lifeFormPdf');
        console.log('--- DOM check ---');
        debugSceneFields.forEach(function(f) {
            var cleanName = f.replace('[]', '\\[\\]');
            if (f.indexOf('[]') > -1) cleanName = f.replace('[]', '');
            var els = formEl.querySelectorAll('[name="' + f + '"]');
            if (els.length === 0) els = formEl.querySelectorAll('[name="' + cleanName + '[]"]');
            console.log('  ' + f + ': ' + els.length + ' elements in form, disabled=' +
                (els.length > 0 ? Array.from(els).map(function(e) { return e.disabled; }) : 'N/A') +
                ', values=' + (els.length > 0 ? Array.from(els).map(function(e) {
                    return e.type === 'checkbox' ? (e.checked ? e.value : '(unchecked)') : e.value;
                }) : 'N/A'));
        });
        console.groupEnd();

        // ลบ base64 hidden inputs ออก (จะส่งเป็น Blob file แทน)
        formData.delete('receiver_signature_data_life');
        formData.delete('sender_signature_data_life');
        formData.delete('scene_sketch_data_life');
        formData.delete('body_diagram_data_life');

        // --- 5. Canvas → Blob (ลายเซ็น / แผนผัง / body diagram) ---
        function lpfIsCanvasBlank(canvas) {
            try {
                var ctx = canvas.getContext('2d');
                var data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
                for (var i = 3; i < data.length; i += 4) {
                    if (data[i] > 0) return false;
                }
            } catch(e) {}
            return true;
        }

        var sigCanvasMap = {
            'receiver_signature': 'lpf_sig_receiver',
            'sender_signature': 'lpf_sig_sender'
        };
        var clearedSignatures = [];

        // --- แผนผัง (scene_sketch) ---
        // แผนผังเป็นแบบหลายหน้า วาดบน canvas ชื่อ lpf_sketch_page_N_canvas
        // lpfCollectSketchPagesData() (เรียกไว้ด้านบน) จะรวมทุกหน้า + เติมพื้นขาว
        // ลงใน hidden input #lpf_scene_sketch_data
        // ต้องอ่านจาก input นี้ ไม่ใช่จาก canvas ชื่อ lpf_scene_sketch_canvas ซึ่งไม่มีอยู่จริง
        // (ของเดิมหา canvas ไม่เจอ จึงถือว่า "ล้างแผนผัง" แล้วลบรูปเดิมทิ้ง
        //  ทำให้แผนผังไม่ขึ้นในไฟล์รายงาน)
        // ภาพที่รวมแล้วจะมีพื้นขาวเสมอ จึงต้องเช็คที่ canvas ต้นทางว่ามีคนวาดจริงไหม
        var lpfSketchHasContent = (window._lpfSketchPages || []).some(function (p) {
            if (p.bgImage) return true;
            var c = document.getElementById(p.canvasId);
            return c && !lpfIsCanvasBlank(c);
        });

        var lpfSketchInp = document.getElementById('lpf_scene_sketch_data');
        var lpfSketchVal = lpfSketchInp ? (lpfSketchInp.value || '') : '';

        if (lpfSketchHasContent && lpfSketchVal.indexOf('data:image') === 0) {
            var lpfSketchBlob = await dataURLtoBlob(lpfSketchVal);
            if (lpfSketchBlob) {
                formData.append('sig_file_scene_sketch', lpfSketchBlob, 'scene_sketch.png');
            }
        } else if (!lpfSketchHasContent) {
            clearedSignatures.push('scene_sketch');
        }

        for (var sigKey in sigCanvasMap) {
            var cvs = document.getElementById(sigCanvasMap[sigKey]);
            if (cvs && !lpfIsCanvasBlank(cvs)) {
                var blob = await canvasToBlob(cvs, 'image/png');
                if (blob) {
                    formData.append('sig_file_' + sigKey, blob, sigKey + '.png');
                }
            } else {
                clearedSignatures.push(sigKey);
            }
        }

        // body_diagram: composite กับรูปพื้นหลัง
        var bdCanvas = document.getElementById('lpf_body_diagram_canvas');
        var bdPad = (typeof signaturePads !== 'undefined') ? signaturePads['lpf_body_diagram_canvas'] : null;
        var bdHasContent = bdCanvas && !lpfIsCanvasBlank(bdCanvas);
        var bdPadNotEmpty = bdPad && !bdPad.isEmpty();
        if (bdCanvas && (bdHasContent || bdPadNotEmpty)) {
            var bgImg = bdCanvas.parentElement ? bdCanvas.parentElement.querySelector('img[src*="body_diagram"]') : null;
            var targetCanvas = bdCanvas;
            if (bgImg && bgImg.naturalWidth > 0) {
                var tempCanvas = document.createElement('canvas');
                tempCanvas.width = bdCanvas.width;
                tempCanvas.height = bdCanvas.height;
                var tempCtx = tempCanvas.getContext('2d');
                tempCtx.fillStyle = '#FFFFFF';
                tempCtx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
                var imgW = bgImg.naturalWidth, imgH = bgImg.naturalHeight;
                var canW = tempCanvas.width, canH = tempCanvas.height;
                var scale = Math.min(canW / imgW, canH / imgH);
                var drawW = imgW * scale, drawH = imgH * scale;
                var drawX = (canW - drawW) / 2, drawY = (canH - drawH) / 2;
                tempCtx.drawImage(bgImg, drawX, drawY, drawW, drawH);
                tempCtx.drawImage(bdCanvas, 0, 0);
                targetCanvas = tempCanvas;
            }
            var bdBlob = await canvasToBlob(targetCanvas, 'image/png');
            if (bdBlob) {
                formData.append('sig_file_body_diagram', bdBlob, 'body_diagram.png');
            }
        } else {
            clearedSignatures.push('body_diagram');
        }

        if (clearedSignatures.length > 0) {
            formData.append('cleared_signatures', JSON.stringify(clearedSignatures));
        }

        // --- 6. Photos จาก attachmentStoreLife ---
        formData.delete('incident_photos_life[]');
        formData.delete('camera_photos_life[]');

        if (typeof window.attachmentStoreLife !== 'undefined' && window.attachmentStoreLife.length > 0) {
            window.attachmentStoreLife.forEach(function(item, index) {
                // Skip existing photos (already in DB)
                if (item.existing) return;
                
                if (item.file) {
                    formData.append('incident_photos_life[]', item.file, item.file.name || 'photo_' + (index + 1) + '.jpg');
                } else if (item.base64) {
                    var byteString = atob(item.base64.split(',')[1]);
                    var mimeString = item.base64.split(',')[0].split(':')[1].split(';')[0];
                    var ab = new ArrayBuffer(byteString.length);
                    var ia = new Uint8Array(ab);
                    for (var bi = 0; bi < byteString.length; bi++) {
                        ia[bi] = byteString.charCodeAt(bi);
                    }
                    var photoBlob = new Blob([ab], { type: mimeString });
                    formData.append('incident_photos_life[]', photoBlob, item.filename || 'photo_' + (index + 1) + '.jpg');
                }
            });
        }

        // --- 7. Deleted photos ---
        var deletedFileIds = [];
        var deletedFilenames = [];
        if (typeof window.deletedExistingPhotosLife !== 'undefined' && window.deletedExistingPhotosLife.length > 0) {
            window.deletedExistingPhotosLife.forEach(function(item) {
                if (typeof item === 'object' && item.file_id) {
                    deletedFileIds.push(item.file_id);
                } else if (typeof item === 'object' && item.filename) {
                    deletedFilenames.push(item.filename);
                } else if (typeof item === 'number') {
                    deletedFileIds.push(item);
                } else if (typeof item === 'string') {
                    deletedFilenames.push(item);
                }
            });
        }
        if (deletedFileIds.length > 0) {
            formData.append('deleted_photo_file_ids', JSON.stringify(deletedFileIds));
        }
        if (deletedFilenames.length > 0) {
            formData.append('deleted_photos', JSON.stringify(deletedFilenames));
        }

        // --- 8. POST ไปที่ saveLife.php โดยตรง ---
        var btn = document.getElementById('btn_save_life_pdf');
        var btnOriginalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> กำลังบันทึก...';
        }

        var lifeUrl = './api/incidentCheckList/saveLife.php';

        // ★ เช็คเน็ตก่อนส่ง (Offline Mode)
        if (!navigator.onLine) {
            if (btn) { btn.disabled = false; btn.innerHTML = btnOriginalHtml; }
            if (typeof saveChecklistOffline === 'function') {
                await saveChecklistOffline(formData, lifeUrl, '#lifeFormPdfModal');
            }
            return;
        }
        if (typeof checkBackendHealth === 'function') {
            var backendOk = await checkBackendHealth();
            if (!backendOk) {
                if (btn) { btn.disabled = false; btn.innerHTML = btnOriginalHtml; }
                if (typeof saveChecklistOffline === 'function') {
                    await saveChecklistOffline(formData, lifeUrl, '#lifeFormPdfModal');
                }
                return;
            }
        }

        fetch(lifeUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.status === 'success' || data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: data.message || 'บันทึกข้อมูลเรียบร้อย',
                    confirmButtonText: 'ตกลง'
                }).then(function() {
                    ['addCheckListModalLife', 'lifeFormPdfModal'].forEach(function(id) {
                        var el = document.getElementById(id);
                        if (el) { var m = bootstrap.Modal.getInstance(el); if (m) m.hide(); }
                    });
                    if (typeof resetLifeForm === 'function') resetLifeForm();
                    if (typeof loadData === 'function') loadData();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: data.message || 'ไม่สามารถบันทึกข้อมูลได้',
                    confirmButtonText: 'ตกลง'
                });
            }
        })
        .catch(async function(err) {
            console.error('Life PDF save error:', err);
            if (typeof saveChecklistOffline === 'function') {
                await saveChecklistOffline(formData, lifeUrl, '#lifeFormPdfModal');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้',
                    confirmButtonText: 'ตกลง'
                });
            }
        })
        .finally(function() {
            if (btn) { btn.disabled = false; btn.innerHTML = btnOriginalHtml; }
        });
    };

    // ===== Download Checklist PDF =====
    window.lpfDownloadChecklistPdf = function() {
        var incidentId = document.getElementById('lpf_incident_id')?.value || 
                         document.getElementById('incident_id_life')?.value || '';
        if (!incidentId) {
            Swal.fire({ icon: 'warning', title: 'ไม่พบ incident_id', text: 'กรุณาบันทึกข้อมูลก่อนดาวน์โหลด' });
            return;
        }
        window.open('/csims/api/incidentCheckList/gen_pdf_life_html.php?incident_id=' + incidentId, '_blank');
    };

    // ===== ★ Real-time Sync ระหว่างฟอร์มมาตรฐานและ PDF =====
    // Sync PDF → Standard เมื่อแก้ไขใน PDF form
    $(document).on('input change', '#lifeFormPdf input, #lifeFormPdf select, #lifeFormPdf textarea', function() {
        var $el = $(this);
        var name = $el.attr('name');
        if (!name) return;
        
        var $stdForm = $('#incidentCheckListFormLife, #addCheckListModalLife');
        if ($stdForm.length === 0) return;
        
        var $target = $stdForm.find('[name="' + name + '"]');
        if ($target.length === 0) return;
        
        if ($el.is(':checkbox') || $el.is(':radio')) {
            $target.prop('checked', $el.prop('checked'));
        } else {
            $target.val($el.val());
        }
    });

    // Sync Standard → PDF เมื่อแก้ไขในฟอร์มมาตรฐาน
    $(document).on('input change', '#incidentCheckListFormLife input, #incidentCheckListFormLife select, #incidentCheckListFormLife textarea', function() {
        var $el = $(this);
        var name = $el.attr('name');
        if (!name) return;
        
        var $pdfForm = $('#lifeFormPdf');
        if ($pdfForm.length === 0) return;
        
        var $target = $pdfForm.find('[name="' + name + '"]');
        if ($target.length === 0) return;
        
        if ($el.is(':checkbox') || $el.is(':radio')) {
            $target.prop('checked', $el.prop('checked'));
        } else {
            $target.val($el.val());
        }
    });

    // ===== ★ Sync ตารางวัตถุพยาน (บันทึกการวัดพบ) จากฟอร์มมาตรฐาน → PDF =====
    window._syncLifeMeasurementToStd = function() {
        // Sync จาก PDF → Standard (ถ้าต้องการ)
    };

    window.syncMeasurementStdToPdf = function() {
        var stdContainer = document.getElementById('measurement_container_life');
        var pdfTbody = document.getElementById('lpf_collection_tbody');
        if (!stdContainer || !pdfTbody) return;

        var stdCards = stdContainer.querySelectorAll('.measurement-card-life');
        // ถ้าไม่มี card ในฟอร์มมาตรฐาน ไม่ต้อง sync (เก็บข้อมูลเดิมใน PDF ไว้)
        if (stdCards.length === 0) return;

        // ตรวจสอบว่ามีข้อมูลจริงหรือไม่ (ไม่ใช่แค่ card ว่าง)
        var hasData = false;
        stdCards.forEach(function(card) {
            var item = card.querySelector('[name="measurement_item_life[]"]');
            if (item && item.value.trim() !== '') hasData = true;
        });
        if (!hasData) return; // ถ้าไม่มีข้อมูล ไม่ต้อง sync

        // ล้าง tbody ของ PDF ก่อน
        pdfTbody.innerHTML = '';

        stdCards.forEach(function(card, idx) {
            var item = card.querySelector('[name="measurement_item_life[]"]');
            var qty = card.querySelector('[name="measurement_quantity_life[]"]');
            var area = card.querySelector('[name="measurement_area_life[]"]');
            var label = card.querySelector('[name="measurement_label_number_life[]"]');
            var plasticChk = card.querySelector('[name="measurement_package_plastic_check_' + idx + '"]');
            var paperChk = card.querySelector('[name="measurement_package_paper_check_' + idx + '"]');
            var otherChk = card.querySelector('[name="measurement_package_other_check_' + idx + '"]');
            var returnChk = card.querySelector('[name="measurement_action_return_check_' + idx + '"]');
            var actionOtherChk = card.querySelector('[name="measurement_action_other_check_' + idx + '"]');
            var remark = card.querySelector('[name="measurement_remark_life[]"]');
            var forensicUnit = card.querySelector('[name="measurement_forensic_unit_life[]"]');
            var forensicVal = forensicUnit ? forensicUnit.value : '';

            // สร้าง options สำหรับ select การตรวจพิสูจน์
            var forensicOptions = '<option value=""' + (forensicVal === '' ? ' selected' : '') + '>--</option>' +
                '<option value="fingerprint"' + (forensicVal === 'fingerprint' ? ' selected' : '') + '>ลายนิ้วมือแฝง</option>' +
                '<option value="bio_dna"' + (forensicVal === 'bio_dna' ? ' selected' : '') + '>ชีววิทยา/ดีเอ็นเอ</option>' +
                '<option value="chemical"' + (forensicVal === 'chemical' ? ' selected' : '') + '>เคมีฟิสิกส์</option>' +
                '<option value="drug"' + (forensicVal === 'drug' ? ' selected' : '') + '>ยาเสพติด</option>' +
                '<option value="gun"' + (forensicVal === 'gun' ? ' selected' : '') + '>อาวุธปืน</option>' +
                '<option value="document"' + (forensicVal === 'document' ? ' selected' : '') + '>เอกสาร</option>';

            var tr = document.createElement('tr');
            tr.innerHTML = '<td style="text-align:center;">' + (idx + 1) + '</td>' +
                '<td><input type="text" name="measurement_item_life[]" value="' + (item ? item.value : '') + '"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
                '<td><input type="text" name="measurement_quantity_life[]" style="width:30px; text-align:center;" value="' + (qty ? qty.value : '') + '"></td>' +
                '<td><input type="text" name="measurement_area_life[]" value="' + (area ? area.value : '') + '"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
                '<td><input type="text" name="measurement_label_number_life[]" style="width:30px; text-align:center;" value="' + (label ? label.value : '') + '"></td>' +
                '<td><input type="checkbox" name="measurement_package_plastic_check_' + idx + '" value="1"' + (plasticChk && plasticChk.checked ? ' checked' : '') + '></td>' +
                '<td><input type="checkbox" name="measurement_package_paper_check_' + idx + '" value="1"' + (paperChk && paperChk.checked ? ' checked' : '') + '></td>' +
                '<td><input type="checkbox" name="measurement_package_other_check_' + idx + '" value="1"' + (otherChk && otherChk.checked ? ' checked' : '') + '></td>' +
                '<td><input type="checkbox" name="measurement_action_return_check_' + idx + '" value="1"' + (returnChk && returnChk.checked ? ' checked' : '') + '></td>' +
                '<td><input type="checkbox" name="measurement_action_other_check_' + idx + '" value="1"' + (actionOtherChk && actionOtherChk.checked ? ' checked' : '') + '></td>' +
                '<td><input type="text" name="measurement_remark_life[]" value="' + (remark ? remark.value : '') + '"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
                '<td><select name="measurement_forensic_unit_life[]" style="font-size:9px; padding:1px; width:100%;">' + forensicOptions + '</select></td>' +
                '<td><button type="button" class="lpf-del-btn" onclick="lpfDelRow(this)">×</button></td>';
            pdfTbody.appendChild(tr);
        });
    };

    // Sync เมื่อเปิด PDF modal
    $('#lifeFormPdfModal').on('shown.bs.modal', function() {
        // ตรวจสอบว่าตาราง collection มี row หรือไม่ ถ้าไม่มีให้เพิ่ม row เริ่มต้น
        var pdfTbody = document.getElementById('lpf_collection_tbody');
        if (pdfTbody && pdfTbody.querySelectorAll('tr').length === 0) {
            lpfAddCollectionRow();
        }
        syncMeasurementStdToPdf();
    });

    // ===== ★ Canvas Sync - ปิดการ sync เพราะขนาด canvas ต่างกันมาก =====
    // ให้วาดในฟอร์มที่จะใช้บันทึกโดยตรง
    // Input/Select/Checkbox/ตารางวัตถุพยาน ยังคง sync ปกติ

    // ฟังก์ชันสลับจากฟอร์มมาตรฐาน → PDF
    window.switchToLifePdfForm = function() {
        // Sync ตารางวัตถุพยานก่อนสลับ
        syncMeasurementStdToPdf();
        // ซ่อน modal มาตรฐาน แสดง modal PDF
        $('#addCheckListModalLife').modal('hide');
        setTimeout(function() {
            $('#lifeFormPdfModal').modal('show');
        }, 300);
    };

    // ฟังก์ชันสลับจาก PDF → ฟอร์มมาตรฐาน
    window.switchToLifeStandardForm = function() {
        // ซ่อน modal PDF แสดง modal มาตรฐาน
        $('#lifeFormPdfModal').modal('hide');
        setTimeout(function() {
            $('#addCheckListModalLife').modal('show');
        }, 300);
    };
})();
</script>
