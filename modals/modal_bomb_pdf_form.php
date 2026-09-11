<?php
/**
 * Modal: ฟอร์ม PDF คดีระเบิด (แบบกรอกข้อมูล)
 * หน้าตาเหมือนฟอร์ม PDF เป๊ะ แต่กรอกข้อมูลได้
 * Prefix ID: bpf_  (bomb pdf form)
 * Based on: api/incidentCheckList/form_bomb_preview.html
 */
date_default_timezone_set('Asia/Bangkok');

$bpfPoliceStationOptions = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qryBpfPS = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stmtBpfPS = $pdo->query($qryBpfPS);
    while ($rowPS = $stmtBpfPS->fetch(PDO::FETCH_ASSOC)) {
        $bpfPoliceStationOptions .= '<option value="' . htmlspecialchars($rowPS['station_name']) . '">' . htmlspecialchars($rowPS['station_name']) . '</option>';
    }
}

// ดึงรายชื่อผู้ตรวจ (เหมือน life form)
$bpfInspectorOptions = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
// ดึงรายชื่อผู้จดบันทึก (ใช้ fullname เป็น value)
$bpfCollectorOptions = '<option value="" selected disabled>-- เลือกผู้จดบันทึก --</option>';
if (isset($pdo)) {
    $qryBpfInsp = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                   FROM user_profile t1 
                   LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                   ORDER BY t1.user_id DESC";
    $stmtBpfInsp = $pdo->query($qryBpfInsp);
    while ($rowInsp = $stmtBpfInsp->fetch(PDO::FETCH_ASSOC)) {
        $bpfInspectorOptions .= '<option value="' . $rowInsp['user_id'] . '">' . htmlspecialchars($rowInsp['fullname']) . '</option>';
        $bpfCollectorOptions .= '<option value="' . htmlspecialchars($rowInsp['fullname']) . '">' . htmlspecialchars($rowInsp['fullname']) . '</option>';
    }
}

$bpfTodayDate = date('Y-m-d');
$bpfTodayTime = date('H:i');
?>

<!-- ===== SCOPED CSS ===== -->
<style>
/* hwpen button */
#bombFormPdfModal .btn-hw-open {
    flex-shrink: 0; min-width: 18px; padding: 0 4px;
    border: none; background: none; color: #6366f1;
    font-size: 0.7rem; cursor: pointer; line-height: 1.5;
}
#bombFormPdfModal .btn-hw-open:hover { color: #4338ca; transform: scale(1.15); }
#bombFormPdfModal .bpf-cg > .bpf-inp { flex: 0 1 auto; }

#bombFormPdfModal .btn-purple {
    background-color: #8b5cf6;
    border-color: #8b5cf6;
    color: #fff;
}
#bombFormPdfModal .btn-purple:hover {
    background-color: #7c3aed;
    border-color: #7c3aed;
    color: #fff;
}

#bombFormPdfModal .bpf-rpt-no-mirror,
#bombFormPdfModal .bpf-rpt-year-mirror {
    display: inline-block;
    border-bottom: 1px dotted #888;
    text-align: center;
}
#bombFormPdfModal .bpf-rpt-no-mirror { min-width: 60px; }
#bombFormPdfModal .bpf-rpt-year-mirror { min-width: 30px; }

#bombFormPdfModal .bpf-body {
    background: #bbb;
    padding: 10px 0;
}

#bombFormPdfModal .bpf-page {
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
#bombFormPdfModal .bpf-header {
    position: relative; margin-bottom: 6px; height: 70px;
}
#bombFormPdfModal .bpf-header-logo {
    position: absolute; left: 0; top: -5px; width: 70px; height: 70px;
}
#bombFormPdfModal .bpf-header-logo img { width: 70px; height: 70px; object-fit: contain; }
#bombFormPdfModal .bpf-header-center {
    position: absolute; left: 80px; right: 180px; top: 8px; text-align: center;
}
#bombFormPdfModal .bpf-header-center .bpf-title-main {
    font-size: 14px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 5px;
}
#bombFormPdfModal .bpf-header-center .bpf-title-sub {
    font-size: 11.5px; font-weight: 600; margin-top: 3px;
}
#bombFormPdfModal .bpf-header-right { position: absolute; right: 0; top: 7px; }
#bombFormPdfModal .bpf-doc-box {
    border: 1.5px solid #000; padding: 3px 8px; font-size: 11px; white-space: nowrap;
}
#bombFormPdfModal .bpf-doc-box .bpf-doc-line { line-height: 1.5; }

/* ===== BODY TABLE ===== */
#bombFormPdfModal .bpf-form-body {
    display: flex; border: 1.5px solid #000; align-items: stretch;
}
#bombFormPdfModal .bpf-col-left {
    width: 50%; border-right: 1.5px solid #000; display: flex; flex-direction: column;
}
#bombFormPdfModal .bpf-col-right { width: 50%; display: flex; flex-direction: column; }

/* ===== ROW HEADER ===== */
#bombFormPdfModal .bpf-row-header {
    display: flex; border-bottom: 1px solid #000;
    font-weight: 700; font-size: 11px; text-align: center; background: transparent;
}
#bombFormPdfModal .bpf-row-header .bpf-lbl-seq {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000; padding: 1px 2px;
}
#bombFormPdfModal .bpf-row-header .bpf-lbl-data { flex: 1; padding: 1px 2px; }

/* ===== SECTION ROW ===== */
#bombFormPdfModal .bpf-sec-row { display: flex; border-bottom: 1px solid #000; }
#bombFormPdfModal .bpf-sec-row:last-child { border-bottom: none; }
#bombFormPdfModal .bpf-sec-label {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000;
    padding: 3px 3px; font-weight: 700; font-size: 11px; text-align: center;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
#bombFormPdfModal .bpf-sec-label .bpf-sec-num {
    font-size: 13px; font-weight: 700; line-height: 1.2;
}
#bombFormPdfModal .bpf-sec-label .bpf-sec-txt {
    font-size: 10px; font-weight: 600; line-height: 1.15; text-align: center; margin-top: 1px;
}
#bombFormPdfModal .bpf-sec-body { flex: 1; padding: 5px 6px; font-size: 11.5px; min-width: 0; overflow: hidden; }

/* ===== FIELD ROW ===== */
#bombFormPdfModal .bpf-fr {
    display: flex; flex-wrap: wrap; align-items: center; margin-bottom: 6px; line-height: 1.8;
}
#bombFormPdfModal .bpf-fl { font-size: 11.5px; white-space: nowrap; margin-right: 4px; }
#bombFormPdfModal .bpf-fl-b {
    font-size: 11.5px; font-weight: 600; white-space: nowrap; margin-right: 4px;
}

/* ===== INPUT FIELDS ===== */
#bombFormPdfModal .bpf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px;
}
#bombFormPdfModal .bpf-inp-m {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 60px; margin: 0 2px; text-align: center;
}
#bombFormPdfModal .bpf-inp-full {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    width: 100%; display: block; margin-bottom: 3px;
}
#bombFormPdfModal .bpf-inp-s {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    min-width: 15px; max-width: 50px; margin: 0 2px; flex: 0 1 40px; text-align: center;
}

/* Center numeric-like fields consistently in virtual PDF form */
#bombFormPdfModal input[name="photo_id_start_bomb"],
#bombFormPdfModal input[name="photo_id_end_bomb"],
#bombFormPdfModal input[name="photo_amount_bomb"],
#bombFormPdfModal input[name="victim_age_bomb[]"],
#bombFormPdfModal input[name="measurement_quantity_bomb[]"],
#bombFormPdfModal input[name="measurement_label_number_bomb[]"] {
    text-align: center;
}

/* SELECT styled like dotted line */
#bombFormPdfModal .bpf-sel {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px; cursor: pointer;
}

/* TEXTAREA styled like dotted lines */
#bombFormPdfModal .bpf-ta {
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
#bombFormPdfModal .bpf-cb {
    appearance: none; -webkit-appearance: none;
    width: 13px; height: 13px; border: 1.5px solid #000;
    margin-right: 3px; cursor: pointer; position: relative;
    vertical-align: middle; flex-shrink: 0; background: #fff;
}
#bombFormPdfModal .bpf-cb:checked::after {
    content: '✓'; font-size: 12px; font-weight: 700;
    position: absolute; top: -3px; left: 0px; color: #000;
}

/* ===== Checkbox label ===== */
#bombFormPdfModal .bpf-ck {
    display: inline-flex; align-items: center; margin-right: 14px;
    font-size: 11.5px; white-space: nowrap; vertical-align: middle; cursor: pointer;
}

/* Filled square bullet */
#bombFormPdfModal .bpf-bk {
    width: 10px; height: 10px; background: #000;
    display: inline-block; margin-right: 3px; flex-shrink: 0;
    position: relative; top: 1px;
}

/* ===== Bullet header ===== */
#bombFormPdfModal .bpf-bh {
    display: flex; align-items: center; font-weight: 600;
    font-size: 11.5px; margin-top: 8px; margin-bottom: 5px;
}

/* ===== Sub-items ===== */
#bombFormPdfModal .bpf-si {
    display: flex; align-items: center; font-size: 11.5px; line-height: 1.8; margin-bottom: 5px;
}
#bombFormPdfModal .bpf-si-no { min-width: 25px; padding-left: 8px; font-size: 11.5px; }

/* ===== Checkbox group ===== */
#bombFormPdfModal .bpf-cg {
    display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; margin-bottom: 5px;
}

/* ===== Indents ===== */
#bombFormPdfModal .bpf-i1 { padding-left: 15px; }
#bombFormPdfModal .bpf-i2 { padding-left: 28px; }
#bombFormPdfModal .bpf-i3 { padding-left: 44px; }

/* ===== FOOTER ===== */
#bombFormPdfModal .bpf-footer {
    margin-top: auto; font-size: 9.5px; color: #333;
    display: flex; justify-content: space-between; align-items: flex-end; flex-shrink: 0;
}
#bombFormPdfModal .bpf-footer-left { flex: 1; }
#bombFormPdfModal .bpf-footer-right { text-align: right; white-space: nowrap; line-height: 1.3; }

/* ===== Add/Remove buttons ===== */
#bombFormPdfModal .bpf-add-btn {
    font-size: 10px; padding: 1px 8px; border: 1px dashed #888;
    background: #f8f8f8; cursor: pointer; color: #333; margin: 3px 0;
    font-family: 'Sarabun', sans-serif;
}
#bombFormPdfModal .bpf-add-btn:hover { background: #e0e0e0; }
#bombFormPdfModal .bpf-del-btn {
    font-size: 9px; padding: 0 4px; border: 1px solid #ccc;
    background: #fff; cursor: pointer; color: #c00;
    font-family: 'Sarabun', sans-serif; line-height: 1.5;
}
#bombFormPdfModal .bpf-del-btn:hover { background: #fee; }

/* ===== Table inputs ===== */
#bombFormPdfModal .bpf-ev-table td {
    border: 1px solid #000; padding: 2px; text-align: center; vertical-align: middle;
}

#bombFormPdfModal .bpf-ev-table input[type="text"] {
    border: none; border-bottom: 1px dotted #888; background: transparent; font-size: 10px; width: 100%;
    padding: 1px 2px; outline: none; font-family: 'Sarabun', sans-serif; text-align: center;
}
#bombFormPdfModal .bpf-ev-table input[type="checkbox"] { width: 10px; height: 10px; cursor: pointer; }

/* ===== Signature box ===== */
#bombFormPdfModal .bpf-sig-box {
    border: 1px solid #ccc; background: #fafafa; min-height: 60px;
    cursor: crosshair; position: relative;
}
#bombFormPdfModal .bpf-sig-box canvas { width: 100%; height: 100%; display: block; }

/* ===== Sketch area (legacy single) ===== */
#bombFormPdfModal .bpf-sketch-area {
    border: 1.5px solid #000; min-height: 500px; position: relative;
    display: flex; align-items: center; justify-content: center; cursor: crosshair;
}

/* ===== Multi-page Sketch System ===== */
#bombFormPdfModal .bpf-sketch-viewport {
    border: 1.5px solid #000; position: relative;
    background: #fff; cursor: crosshair;
}
#bombFormPdfModal .bpf-sketch-canvas-wrap {
    position: relative; width: 100%;
}
#bombFormPdfModal .bpf-sketch-canvas-wrap canvas {
    display: block; width: 100%; height: 100%; position: absolute; top: 0; left: 0; z-index: 2;
}
#bombFormPdfModal .bpf-sketch-canvas-wrap .bpf-sketch-bg-img {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1;
    object-fit: contain; pointer-events: none; user-select: none;
}
#bombFormPdfModal .bpf-sketch-canvas-wrap .bpf-sketch-placeholder {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
    color: #bbb; font-size: 13px; z-index: 0; pointer-events: none; text-align: center;
}
#bombFormPdfModal .bpf-not-to-scale {
    position: absolute; bottom: 6px; right: 8px; font-size: 10px; color: #555; z-index: 3;
    pointer-events: none;
}

/* ===== Photo Grid 5 คอลัมน์ × 7 แถว (35 รูป/หน้า) ===== */
#bombFormPdfModal .bpf-photo-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 6px 4px;
    align-content: start;
}
#bombFormPdfModal .bpf-photo-cell {
    position: relative;
    border: 1.5px solid #333;
    overflow: hidden;
    background: #fff;
}
#bombFormPdfModal .bpf-photo-cell img {
    width: 100%;
    aspect-ratio: 4/3;
    object-fit: cover;
    display: block;
}
#bombFormPdfModal .bpf-cell-delete {
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
#bombFormPdfModal .bpf-cell-filename {
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
#bombFormPdfModal .bpf-photo-dropzone {
    border: 2px dashed #b0bec5;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    background: #f8f9fa;
    margin-bottom: 8px;
    transition: all 0.2s;
}
#bombFormPdfModal .bpf-photo-dropzone:hover,
#bombFormPdfModal .bpf-photo-dropzone.dragover {
    border-color: #2196F3;
    background: #e3f2fd;
}
#bombFormPdfModal .bpf-add-photo-page-btn {
    display: block; margin: 8px auto; padding: 4px 16px;
    border: 1px dashed #888; background: #f0f0f0; cursor: pointer;
    font-size: 11px; font-family: 'Sarabun', sans-serif; color: #333;
}
#bombFormPdfModal .bpf-add-photo-page-btn:hover { background: #e0e0e0; }

/* ===== Body diagram area ===== */
#bombFormPdfModal .bpf-body-diagram {
    border: 1.5px solid #000; min-height: 600px; position: relative;
    display: flex; align-items: center; justify-content: center; cursor: crosshair;
    background: #fff;
}

@media print {
    body > *:not(#bombFormPdfModal),
    .navbar, .sidebar, .main-content, #status-banner,
    .modal-backdrop, .swal2-container,
    #bombFormPdfModal .modal-header,
    #bombFormPdfModal .modal-footer,
    #bombFormPdfModal .csims-loading-overlay,
    #bombFormPdfModal .bpf-add-btn,
    #bombFormPdfModal .bpf-del-btn,
    #bombFormPdfModal .bpf-add-photo-page-btn,
    #bombFormPdfModal .btn-hw-open,
    #bombFormPdfModal input[type="color"],
    #bombFormPdfModal .form-check.form-switch,
    #bombFormPdfModal .d-flex.align-items-center.gap-3 {
        display: none !important;
    }
    #bombFormPdfModal,
    #bombFormPdfModal .modal-dialog,
    #bombFormPdfModal .modal-content,
    #bombFormPdfModal .bpf-body {
        position: static !important; display: block !important;
        width: auto !important; max-width: none !important;
        max-height: none !important; height: auto !important;
        overflow: visible !important; margin: 0 !important;
        padding: 0 !important; background: #fff !important;
        border: none !important; box-shadow: none !important;
        transform: none !important; opacity: 1 !important;
    }
    @page { size: A4 portrait; margin: 0; }
    #bombFormPdfModal .bpf-page {
        width: 100% !important; min-height: auto !important;
        height: auto !important; margin: 0 !important;
        padding: 8mm 10mm 5mm 10mm !important;
        box-shadow: none !important; overflow: visible !important;
        page-break-after: always; page-break-inside: auto;
    }
    #bombFormPdfModal .bpf-page:last-of-type { page-break-after: auto; }
    #bombFormPdfModal .bpf-sec-row { page-break-inside: avoid; }
    #bombFormPdfModal .bpf-form-body { page-break-inside: auto; }
    #bombFormPdfModal .bpf-header { page-break-after: avoid; }
    #bombFormPdfModal .bpf-footer { page-break-before: avoid; }
    #bombFormPdfModal .bpf-photo-grid { gap: 2px !important; }
    #bombFormPdfModal .bpf-photo-cell { page-break-inside: avoid; }
    #bombFormPdfModal .bpf-photo-dropzone { display: none !important; }
    #bombFormPdfModal .bpf-cell-delete { display: none !important; }
    #bombFormPdfModal .bpf-sig-box,
    #bombFormPdfModal .bpf-sketch-area,
    #bombFormPdfModal .bpf-sketch-viewport,
    #bombFormPdfModal .bpf-body-diagram { page-break-inside: avoid; }
    #bombFormPdfModal .bpf-sketch-viewport { overflow: visible !important; height: auto !important; }
    #bombFormPdfModal .bpf-inp, #bombFormPdfModal .bpf-inp-m,
    #bombFormPdfModal .bpf-inp-full, #bombFormPdfModal .bpf-inp-s,
    #bombFormPdfModal .bpf-sel, #bombFormPdfModal .bpf-ta {
        border-bottom-color: #888 !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    #bombFormPdfModal .bpf-sel {
        -webkit-appearance: none; appearance: none;
        color: #000 !important; background: transparent !important;
    }
    #bombFormPdfModal .bpf-cb {
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
}
</style>

<!-- ===== MODAL ===== -->
<div class="modal fade" id="bombFormPdfModal" aria-labelledby="bombFormPdfModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 860px; margin: 1.75rem auto;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="bombFormPdfModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body bpf-body p-0" style="max-height: 80vh; overflow-y: auto; position:relative;">
                <!-- Loading Overlay -->
                <div id="bombPdfLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track">
                            <div class="csims-bar-fill"></div>
                        </div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>
                <form id="bombFormPdf" novalidate>
                    <input type="hidden" id="bpf_receiveNoti_id" name="receiveNoti_id_bomb">
                    <input type="hidden" id="bpf_doc_no" name="doc_no_bomb">
                    <input type="hidden" id="bpf_report_no" name="report_no_bomb">

                    <!-- Switch กลับไปฟอร์มมาตรฐาน -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="editInfoBombPdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountBombPdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToStdFormBomb" checked style="width: 3rem; height: 1.5rem; cursor: pointer;" onchange="if(!this.checked){ this.checked=true; switchToBombStandardForm(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToStdFormBomb" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ================================================================ -->
<!-- ========================= PAGE 1 ============================== -->
<!-- ================================================================ -->
<div class="bpf-page">

    <div class="bpf-header">
        <div class="bpf-header-logo">
            <img src="./images/office-of-police-forensic-icon.jpg" alt="ตราสำนักงานตำรวจแห่งชาติ">
        </div>
        <div class="bpf-header-center">
            <div class="bpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="bpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด</div>
        </div>
        <div class="bpf-header-right">
            <div class="bpf-doc-box">
                <div class="bpf-doc-line">เลขรับที่/เลขรายงาน <span id="bpf_report_no_display" style="display:inline-block;min-width:60px;border-bottom:1px dotted #888;text-align:center;"></span> / 25<span id="bpf_report_year_display" style="display:inline-block;min-width:30px;border-bottom:1px dotted #888;text-align:center;"><?= substr((date('Y') + 543), -2) ?></span></div>
                <div class="bpf-doc-line">หน้าที่ <span class="bpf-cur-page">1</span> / <span class="bpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div class="bpf-form-body">

        <!-- LEFT COLUMN -->
        <div class="bpf-col-left">
            <div class="bpf-row-header">
                <div class="bpf-lbl-seq">ลำดับ</div>
                <div class="bpf-lbl-data">ข้อมูล</div>
            </div>

            <!-- 1. การรับแจ้งเหตุ -->
            <div class="bpf-sec-row">
                <div class="bpf-sec-label">
                    <span class="bpf-sec-num">1.</span>
                    <span class="bpf-sec-txt">การรับ<br>แจ้งเหตุ</span>
                </div>
                <div class="bpf-sec-body">
                    <div class="bpf-fr">
                        <span class="bpf-fl">คดี</span>
                        <input type="text" class="bpf-inp" name="case_doc_bomb" id="bpf_case_doc_no" readonly style="text-align:center; background:#f5f5f5;">
                    </div>
                    <div class="bpf-fr">
                        <span class="bpf-fl">วันที่</span>
                        <input type="date" class="bpf-inp-m" name="case_date" id="bpf_report_date" value="<?= $bpfTodayDate ?>">
                        <span class="bpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="bpf-inp" name="case_time" id="bpf_report_time" value="<?= $bpfTodayTime ?>" style="text-align:center;">
                    </div>
                    <div class="bpf-fr">
                        <span class="bpf-fl" style="margin-right:10px;">การรับแจ้ง</span>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="notify_method[]" value="ทางโทรศัพท์">ทางโทรศัพท์</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="notify_method[]" value="ทางวิทยุสื่อสาร">ทางวิทยุสื่อสาร</label>
                    </div>
                    <div class="bpf-fr" style="padding-left:38px;">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="notify_method[]" value="ทางหนังสือ">ทางหนังสือ</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="notify_method[]" value="อื่นๆ" id="bpf_notify_other_chk">อื่นๆ</label>
                        <input type="text" class="bpf-inp" name="notify_method_other_text" id="bpf_notify_other_text">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="bpf_notify_other_text" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-fr">
                        <span class="bpf-fl">สน./สภ.</span>
                        <select class="bpf-sel" name="police_station" id="bpf_police_station">
                            <?= $bpfPoliceStationOptions ?>
                        </select>
                    </div>
                    <div class="bpf-fr">
                        <span class="bpf-fl">ที่</span>
                        <input type="text" class="bpf-inp" name="location_at" id="bpf_location_at">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="bpf_location_at" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <span class="bpf-fl">ลง</span>
                        <input type="date" class="bpf-inp" name="record_date" id="bpf_record_date" style="text-align:center;" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="bpf-fr">
                        <span class="bpf-fl">พนักงานสอบสวน</span>
                        <input type="text" class="bpf-inp" name="investigator_name" id="bpf_investigator_name">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="bpf_investigator_name" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-fr">
                        <span class="bpf-fl">หมายเลขโทรศัพท์</span>
                        <input type="tel" class="bpf-inp" name="investigator_phone" id="bpf_investigator_phone">
                    </div>
                </div>
            </div>

            <!-- 2. สถานที่เกิดเหตุ -->
            <div class="bpf-sec-row">
                <div class="bpf-sec-label">
                    <span class="bpf-sec-num">2.</span>
                    <span class="bpf-sec-txt">สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="bpf-sec-body">
                    <div class="bpf-fr">
                        <span class="bpf-fl">สถานที่เกิดเหตุ</span>
                        <input type="text" class="bpf-inp" name="crime_location" id="bpf_crime_location">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="bpf_crime_location" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <input type="text" class="bpf-inp-full" name="crime_location_2">
                    <!-- ผู้เสียหาย/ผู้บาดเจ็บ/ผู้เสียชีวิต -->
                    <div id="bpf_victim_container">
                        <div class="bpf-victim-row" style="margin-top:3px; padding: 2px 0; border-top: 1px dotted #ccc;">
                            <div class="bpf-fr">
                                <span class="bpf-fl">ประเภท</span>
                                <select class="bpf-sel" name="victim_type_bomb[]" style="max-width:90px;">
                                    <option value="">--เลือก--</option>
                                    <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                                    <option value="ผู้บาดเจ็บ">ผู้บาดเจ็บ</option>
                                    <option value="ผู้เสียชีวิต">ผู้เสียชีวิต</option>
                                </select>
                                <span class="bpf-fl" style="margin-left:6px;">ชื่อ</span>
                                <input type="text" class="bpf-inp" name="victim_name_bomb[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                                <span class="bpf-fl" style="margin-left:4px;">อายุ</span>
                                <input type="text" class="bpf-inp-s" name="victim_age_bomb[]" style="max-width:30px;">
                                <span class="bpf-fl">ปี</span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="bpf-add-btn" onclick="bpfAddVictim()">+ เพิ่มผู้เสียหาย</button>
                </div>
            </div>

            <!-- 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ -->
            <div class="bpf-sec-row">
                <div class="bpf-sec-label">
                    <span class="bpf-sec-num">3.</span>
                    <span class="bpf-sec-txt">วันเวลา<br>ที่ทราบ<br>เหตุ/เกิด<br>เหตุ</span>
                </div>
                <div class="bpf-sec-body">
                    <div class="bpf-fr"><span class="bpf-fl-b">วันเวลาที่ผู้เสียหาย ทราบเหตุ/เกิดเหตุ</span></div>
                    <div class="bpf-fr">
                        <span class="bpf-fl">วันที่</span>
                        <input type="date" class="bpf-inp-m" name="victim_know_date" id="bpf_victim_known_date" value="<?= $bpfTodayDate ?>">
                        <span class="bpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="bpf-inp" name="victim_know_time" id="bpf_victim_known_time" value="<?= $bpfTodayTime ?>" style="text-align:center;">
                    </div>
                    <div class="bpf-fr" style="margin-top:2px;"><span class="bpf-fl-b">วันเวลาที่พนักงานสอบสวนทราบเหตุ</span></div>
                    <div class="bpf-fr">
                        <span class="bpf-fl">วันที่</span>
                        <input type="date" class="bpf-inp-m" name="officer_know_date" id="bpf_officer_known_date" value="<?= $bpfTodayDate ?>">
                        <span class="bpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="bpf-inp" name="officer_know_time" id="bpf_officer_known_time" value="<?= $bpfTodayTime ?>" style="text-align:center;">
                    </div>
                </div>
            </div>

            <!-- 4. วันเวลาที่ตรวจเหตุ -->
            <div class="bpf-sec-row">
                <div class="bpf-sec-label">
                    <span class="bpf-sec-num">4.</span>
                    <span class="bpf-sec-txt">วัน<br>เวลาที่<br>ตรวจ<br>เหตุ</span>
                </div>
                <div class="bpf-sec-body">
                    <div class="bpf-fr"><span class="bpf-fl-b">วันเวลาที่ทำการตรวจสถานที่เกิดเหตุ</span></div>
                    <div class="bpf-fr">
                        <span class="bpf-fl">วันที่</span>
                        <input type="date" class="bpf-inp-m" name="inspect_date" id="bpf_inspect_date" value="<?= $bpfTodayDate ?>">
                        <span class="bpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="bpf-inp" name="inspect_time" id="bpf_inspect_time" value="<?= $bpfTodayTime ?>" style="text-align:center;">
                    </div>
                    <div class="bpf-fr" style="margin-top:2px;"><span class="bpf-fl-b">วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเพิ่มเติม</span></div>
                    <div class="bpf-fr">
                        <span class="bpf-fl">วันที่</span>
                        <input type="date" class="bpf-inp-m" name="inspect_additional_date" id="bpf_inspect_add_date">
                        <span class="bpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="bpf-inp" name="inspect_additional_time" id="bpf_inspect_add_time" style="text-align:center;">
                    </div>
                </div>
            </div>

            <!-- 5. ผู้ตรวจสถานที่เกิดเหตุ -->
            <div class="bpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="bpf-sec-label">
                    <span class="bpf-sec-num">5.</span>
                    <span class="bpf-sec-txt">ผู้ตรวจ<br>สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="bpf-sec-body">
                    <div class="bpf-bh" style="margin-top:0;">
                        <span class="bpf-bk"></span>
                        <span>ผู้ตรวจสถานที่เกิดเหตุ</span>
                    </div>
                    <div id="bpf_inspector_container">
                        <div class="bpf-si bpf-inspector-row">
                            <span class="bpf-si-no">5.1</span>
                            <select class="bpf-sel" name="inspector_id[]">
                                <?= $bpfInspectorOptions ?>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="bpf-add-btn" onclick="bpfAddInspector()">+ เพิ่มผู้ตรวจ</button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="bpf-col-right">
            <div class="bpf-row-header">
                <div class="bpf-lbl-seq">ลำดับ</div>
                <div class="bpf-lbl-data">ข้อมูล</div>
            </div>

            <!-- 6. ลักษณะสถานที่เกิดเหตุ -->
            <div class="bpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="bpf-sec-label">
                    <span class="bpf-sec-num">6.</span>
                    <span class="bpf-sec-txt">ลักษณะ<br>สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="bpf-sec-body">

                    <!-- สภาพสถานที่เกิดเหตุเมื่อไปถึง -->
                    <div class="bpf-bh" style="margin-top:0;"><span class="bpf-bk"></span><span>สภาพสถานที่เกิดเหตุเมื่อไปถึง</span></div>

                    <!-- การรักษาสถานที่เกิดเหตุ -->
                    <div class="bpf-bh bpf-i1"><span class="bpf-bk"></span><span>การรักษาสถานที่เกิดเหตุ</span></div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb bpf-radio-toggle" name="scene_preserved" value="มี" data-group="bpf_preserved">มี</label>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:2px;"><input type="checkbox" class="bpf-cb bpf-radio-toggle" name="scene_preserved" value="ไม่มี" data-group="bpf_preserved">ไม่มี</label>
                        <input type="text" class="bpf-inp" name="scene_preserved_no_text">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- แสงสว่าง -->
                    <div class="bpf-bh bpf-i1"><span class="bpf-bk"></span><span>แสงสว่าง (ที่สังเกตเห็น)</span></div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="lighting[]" value="สว่าง">สว่าง</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="lighting[]" value="มืด">มืด</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="lighting[]" value="เสาไฟส่องสว่าง">เสาไฟส่องสว่าง</label>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="lighting[]" value="อื่นๆ">อื่นๆ</label>
                        <input type="text" class="bpf-inp" name="lighting_other_text">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- อุณหภูมิ -->
                    <div class="bpf-bh bpf-i1"><span class="bpf-bk"></span><span>อุณหภูมิ</span></div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="temperature[]" value="ร้อน">ร้อน</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="temperature[]" value="เย็น">เย็น</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="temperature[]" value="เครื่องปรับอากาศ">เครื่องปรับอากาศ</label>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="temperature[]" value="อื่นๆ">อื่นๆ</label>
                        <input type="text" class="bpf-inp" name="temperature_other_text">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- กลิ่น -->
                    <div class="bpf-bh bpf-i1"><span class="bpf-bk"></span><span>กลิ่น</span></div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:2px;"><input type="checkbox" class="bpf-cb bpf-radio-toggle" name="smell" value="มี" data-group="bpf_smell">มี</label>
                        <input type="text" class="bpf-inp" name="bomb_smell_yes_text">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:2px;"><input type="checkbox" class="bpf-cb bpf-radio-toggle" name="smell" value="ไม่มี" data-group="bpf_smell">ไม่มี</label>
                        <input type="text" class="bpf-inp" name="bomb_smell_no_text">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- ลักษณะสถานที่เกิดเหตุ -->
                    <div class="bpf-bh" style="margin-top:4px;"><span class="bpf-bk"></span><span>ลักษณะสถานที่เกิดเหตุ</span></div>

                    <!-- กรณีเกิดเหตุภายนอกอาคาร -->
                    <div class="bpf-i1" style="margin-top:1px;">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="has_outdoor_incident" value="1" id="bpf_chk_outdoor">กรณีเกิดเหตุภายนอกอาคาร</label>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="outdoor_type[]" value="ถนน">ถนน</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="outdoor_type[]" value="สนามหญ้า">สนามหญ้า</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="outdoor_type[]" value="ในสวน">ในสวน</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="outdoor_type[]" value="ที่ว่าง">ที่ว่าง</label>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:2px;"><input type="checkbox" class="bpf-cb" name="outdoor_type[]" value="อื่นๆ">อื่นๆ</label>
                        <input type="text" class="bpf-inp" name="outdoor_type_other_text">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- สภาพบริเวณโดยรอบ (ภายนอก) -->
                    <div class="bpf-bh bpf-i2"><span class="bpf-bk"></span><span>สภาพบริเวณโดยรอบ</span>
                        <span class="bpf-fl" style="margin-left:3px;">เมื่อหันหน้าเข้าสถานที่เกิดเหตุ </span>
                      
                    </div>
                    <div class="bpf-i1">
                        <div class="bpf-fr"><span class="bpf-fl">ด้านหน้าติด</span><input type="text" class="bpf-inp" name="front_adjacent_outdoor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="bpf-fr"><span class="bpf-fl">ด้านซ้ายติด</span><input type="text" class="bpf-inp" name="left_adjacent_outdoor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="bpf-fr"><span class="bpf-fl">ด้านขวาติด</span><input type="text" class="bpf-inp" name="right_adjacent_outdoor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="bpf-fr"><span class="bpf-fl">ด้านหลังติด</span><input type="text" class="bpf-inp" name="back_adjacent_outdoor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    </div>

                    <!-- บริเวณที่เกิดเหตุ (ภายนอก) -->
                    <div class="bpf-bh bpf-i2"><span class="bpf-bk"></span><span>บริเวณที่เกิดเหตุ</span>
                        <span class="bpf-fl" style="margin-left:3px;">เกิดเหตุที่</span>
                        <input type="text" class="bpf-inp" name="incident_area_detail_outdoor">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- กรณีเกิดเหตุภายในอาคาร -->
                    <div class="bpf-bh bpf-i1">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="has_indoor_incident" value="1" id="bpf_chk_indoor">กรณีเกิดเหตุภายในอาคาร</label>
                    </div>

                    <!-- ลักษณะภายนอก -->
                    <div class="bpf-bh bpf-i2"><span class="bpf-bk"></span><span>ลักษณะภายนอก</span></div>
                    <div class="bpf-cg bpf-i2" style="margin-left:12px;">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="building_type_indoor[]" value="อาคารพาณิชย์">อาคารพาณิชย์</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="building_type_indoor[]" value="บ้านเดี่ยว">บ้านเดี่ยว</label>
                    </div>
                    <div class="bpf-cg bpf-i2" style="margin-left:12px;">
                        <label class="bpf-ck" style="margin-right:2px;"><input type="checkbox" class="bpf-cb" name="building_type_indoor[]" value="อื่นๆ">อื่นๆ</label>
                        <input type="text" class="bpf-inp" name="building_type_other_text_indoor" style="max-width:100px;">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <input type="text" class="bpf-inp-s" name="building_floor_count_indoor" style="max-width:40px;">
                        <span class="bpf-fl">ชั้น</span>
                    </div>

                    <!-- สภาพบริเวณโดยรอบ (ภายใน) -->
                    <div class="bpf-bh bpf-i2"><span class="bpf-bk"></span><span>สภาพบริเวณโดยรอบ</span>
                        <label class="bpf-ck" style="margin-left:6px;"><input type="checkbox" class="bpf-cb bpf-radio-toggle" name="surrounding_fence_indoor" value="มีรั้ว" data-group="bpf_fence">มีรั้ว</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb bpf-radio-toggle" name="surrounding_fence_indoor" value="ไม่มีรั้ว" data-group="bpf_fence">ไม่มีรั้ว</label>
                    </div>
                    <div class="bpf-i1">
                        <div class="bpf-fr"><span class="bpf-fl">เมื่อหันหน้าเข้าสถานที่เกิดเหตุ</span><input type="text" class="bpf-inp" name="entrance_condition_indoor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="bpf-fr"><span class="bpf-fl">ด้านหน้าติด</span><input type="text" class="bpf-inp" name="front_adjacent_indoor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="bpf-fr"><span class="bpf-fl">ด้านซ้ายติด</span><input type="text" class="bpf-inp" name="left_adjacent_indoor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="bpf-fr"><span class="bpf-fl">ด้านขวาติด</span><input type="text" class="bpf-inp" name="right_adjacent_indoor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="bpf-fr"><span class="bpf-fl">ด้านหลังติด</span><input type="text" class="bpf-inp" name="back_adjacent_indoor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="bpf-footer">
        <div class="bpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="bpf-footer-right">F-CS-10 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 2 ============================== -->
<!-- ================================================================ -->
<div class="bpf-page">

    <div class="bpf-header">
        <div class="bpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="bpf-header-center">
            <div class="bpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="bpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด</div>
        </div>
        <div class="bpf-header-right">
            <div class="bpf-doc-box">
                <div class="bpf-doc-line">เลขรับที่/เลขรายงาน <span class="bpf-rpt-no-mirror"></span> / 25<span class="bpf-rpt-year-mirror"></span></div>
                <div class="bpf-doc-line">หน้าที่ <span class="bpf-cur-page">2</span> / <span class="bpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div class="bpf-form-body">
        <!-- LEFT COLUMN -->
        <div class="bpf-col-left">
            <div class="bpf-row-header"><div class="bpf-lbl-seq">ลำดับ</div><div class="bpf-lbl-data">ข้อมูล</div></div>

            <!-- 6. (ต่อ) -->
            <div class="bpf-sec-row">
                <div class="bpf-sec-label"><span class="bpf-sec-num">6.</span><span class="bpf-sec-txt">(ต่อ)</span></div>
                <div class="bpf-sec-body">

                    <!-- ลักษณะภายใน -->
                    <div class="bpf-bh" style="margin-top:2px;"><span class="bpf-bk"></span><span>ลักษณะภายใน</span><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div><textarea class="bpf-ta" name="interior_detail_indoor" rows="2"></textarea></div>

                    <!-- บริเวณที่เกิดเหตุ -->
                    <div class="bpf-bh" style="margin-top:0;"><span class="bpf-bk"></span><span>บริเวณที่เกิดเหตุ เกิดเหตุที่</span>
                        <input type="text" class="bpf-inp" name="incident_area_detail_indoor">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- โครงสร้างบริเวณที่เกิดเหตุ -->
                    <div class="bpf-bh"><span class="bpf-bk"></span><span>โครงสร้างบริเวณที่เกิดเหตุ</span></div>
                    <div class="bpf-fr bpf-i1">
                        <label class="bpf-ck" style="margin-right:2px;"><input type="checkbox" class="bpf-cb" name="structure_size_check_indoor" value="1">ขนาดกว้าง x ยาว ประมาณ</label>
                        <input type="text" class="bpf-inp" name="structure_size_indoor">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-fr bpf-i1">
                        <label class="bpf-ck" style="margin-right:2px;"><input type="checkbox" class="bpf-cb" name="structure_type_check_indoor" value="1">ลักษณะโครงสร้าง</label>
                        <input type="text" class="bpf-inp" name="structure_type_indoor">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-fr bpf-i1">
                        <label class="bpf-ck" style="margin-right:2px;"><input type="checkbox" class="bpf-cb" name="structure_wall_check_indoor" value="1">ผนัง</label>
                        <input type="text" class="bpf-inp" name="structure_wall_indoor">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-i2">
                        <div class="bpf-fr"><span class="bpf-fl">ด้านหน้า</span><input type="text" class="bpf-inp" name="structure_front_indoor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="bpf-fr"><span class="bpf-fl">ด้านซ้าย</span><input type="text" class="bpf-inp" name="structure_left_indoor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="bpf-fr"><span class="bpf-fl">ด้านขวา</span><input type="text" class="bpf-inp" name="structure_right_indoor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                        <div class="bpf-fr"><span class="bpf-fl">ด้านหลัง</span><input type="text" class="bpf-inp" name="structure_back_indoor"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    </div>
                    <div class="bpf-fr bpf-i1">
                        <label class="bpf-ck" style="margin-right:2px;"><input type="checkbox" class="bpf-cb" name="structure_floor_check_indoor" value="1">พื้นห้อง</label>
                        <input type="text" class="bpf-inp" name="structure_floor_indoor">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-fr bpf-i1">
                        <label class="bpf-ck" style="margin-right:2px;"><input type="checkbox" class="bpf-cb" name="structure_roof_check_indoor" value="1">หลังคา</label>
                        <input type="text" class="bpf-inp" name="structure_roof_indoor">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-fr bpf-i1">
                        <label class="bpf-ck" style="margin-right:2px;"><input type="checkbox" class="bpf-cb" name="structure_arrangement_check_indoor" value="1">การจัดวางสิ่งของ</label>
                        <input type="text" class="bpf-inp" name="structure_arrangement_indoor">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                </div>
            </div>

            <!-- 7. ผลการตรวจสถานที่เกิดเหตุ -->
            <div class="bpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="bpf-sec-label">
                    <span class="bpf-sec-num">7.</span>
                    <span class="bpf-sec-txt">ผลการ<br>ตรวจ<br>สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="bpf-sec-body">
                    <!-- พฤติการณ์คดี -->
                    <div class="bpf-bh" style="margin-top:0;"><span class="bpf-bk"></span><span>พฤติการณ์คดี</span><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="bpf-ta" name="case_behavior" rows="4"></textarea>

                    <!-- ศพ (Dynamic) -->
                    <div id="bpf_bodies_container">
                        <div class="bpf-bh"><span class="bpf-bk"></span><span>ศพ/ผู้บาดเจ็บ</span></div>
                        <div class="bpf-body-row" style="border-top:1px dotted #ccc; padding-top:4px; margin-top:4px;">
                            <div class="bpf-fr">
                                <label class="bpf-ck"><input type="checkbox" class="bpf-cb bpf-radio-toggle bpf-body-status" name="body_status_bomb[]" value="พบศพ" data-group="bpf_body_status_0">พบศพ</label>
                                <label class="bpf-ck"><input type="checkbox" class="bpf-cb bpf-radio-toggle bpf-body-status" name="body_status_bomb[]" value="ไม่พบศพ" data-group="bpf_body_status_0">ไม่พบศพ</label>
                                <input type="text" class="bpf-inp bpf-body-notfound" name="body_notfound_detail_bomb[]" style="max-width:180px; display:none;" placeholder="ระบุเหตุผล"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                            </div>
                            <div class="bpf-fr">
                                <span class="bpf-fl">ชื่อ-สกุล</span>
                                <input type="text" class="bpf-inp" name="body_name_bomb[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                            </div>
                            <div class="bpf-fr">
                                <span class="bpf-fl">ลักษณะบาดแผล</span>
                                <input type="text" class="bpf-inp" name="body_condition_bomb[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="bpf-add-btn" onclick="bpfAddBody()">+ เพิ่มศพ/ผู้บาดเจ็บ</button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="bpf-col-right">
            <div class="bpf-row-header"><div class="bpf-lbl-seq">ลำดับ</div><div class="bpf-lbl-data">ข้อมูล</div></div>

            <!-- 7. (ต่อ) -->
            <div class="bpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="bpf-sec-label"><span class="bpf-sec-num">7.</span><span class="bpf-sec-txt">(ต่อ)</span></div>
                <div class="bpf-sec-body">

                    <!-- ความเสียหาย -->
                    <div class="bpf-bh" style="margin-top:0;"><span class="bpf-bk"></span><span>ความเสียหาย (บ้านเรือน ยานพาหนะ ทรัพย์สิน)</span><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="bpf-ta" name="damage_details" rows="4"></textarea>

                    <!-- ตำแหน่งที่เกิดการระเบิด -->
                    <div class="bpf-bh" style="margin-top:4px;"><span class="bpf-bk"></span><span>ตำแหน่งที่เกิดการระเบิด</span><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <textarea class="bpf-ta" name="explosion_point" rows="3"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="bpf-footer">
        <div class="bpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="bpf-footer-right">F-CS-10 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 3 ============================== -->
<!-- ================================================================ -->
<div class="bpf-page">

    <div class="bpf-header">
        <div class="bpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="bpf-header-center">
            <div class="bpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="bpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด</div>
        </div>
        <div class="bpf-header-right">
            <div class="bpf-doc-box">
                <div class="bpf-doc-line">เลขรับที่/เลขรายงาน <span class="bpf-rpt-no-mirror"></span> / 25<span class="bpf-rpt-year-mirror"></span></div>
                <div class="bpf-doc-line">หน้าที่ <span class="bpf-cur-page">3</span> / <span class="bpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div class="bpf-form-body">
        <!-- LEFT COLUMN -->
        <div class="bpf-col-left">
            <div class="bpf-row-header"><div class="bpf-lbl-seq">ลำดับ</div><div class="bpf-lbl-data">ข้อมูล</div></div>

            <!-- 7. (ต่อ) วัตถุพยานที่ตรวจพบ -->
            <div class="bpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="bpf-sec-label"><span class="bpf-sec-num">7.</span><span class="bpf-sec-txt">(ต่อ)</span></div>
                <div class="bpf-sec-body">
                    <div class="bpf-bh" style="margin-top:0;"><span class="bpf-bk"></span><span>วัตถุพยานที่ตรวจพบ</span></div>

                    <!-- ภาชนะบรรจุ -->
                    <div class="bpf-bh bpf-i1" style="margin-top:0;"><span class="bpf-bk"></span><span>ภาชนะบรรจุ</span></div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="bomb_containers[]" value="กล่องเหล็ก">กล่องเหล็ก</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="bomb_containers[]" value="ถังแก๊ส">ถังแก๊ส</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="bomb_containers[]" value="ถังดับเพลิง">ถังดับเพลิง</label>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="bomb_containers[]" value="ท่อเหล็ก">ท่อเหล็ก</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="bomb_containers[]" value="ท่อ PVC">ท่อ PVC</label>
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="bomb_containers[]" value="ถังน้ำยาแอร์">ถังน้ำยาแอร์</label>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="bomb_containers[]" value="ระเบิดมาตรฐาน">ระเบิดมาตรฐาน</label>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="bomb_containers[]" value="อื่นๆ">อื่น ๆ</label>
                        <input type="text" class="bpf-inp" name="bomb_container_other_text"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- วิธีการจุดระเบิด -->
                    <div class="bpf-bh bpf-i1" style="margin-top:5px;"><span class="bpf-bk"></span><span>วิธีการจุดระเบิด</span></div>

                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="detonate_trap" value="1">กับดัก / เหยียบ / สะดุด / </label>
                        <input type="text" class="bpf-inp" name="detonate_trap_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="detonate_wire" value="1">ลากสายไฟ สี</label>
                        <input type="text" class="bpf-inp" name="detonate_wire_color" style="max-width:60px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <span class="bpf-fl">ยาว</span>
                        <input type="text" class="bpf-inp-s" name="detonate_wire_length" style="max-width:40px;">
                        <span class="bpf-fl">ซม.</span>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="detonate_radio" value="1">วิทยุสื่อสาร</label>
                        <span class="bpf-fl">ยี่ห้อ</span><input type="text" class="bpf-inp" name="detonate_radio_brand" style="max-width:80px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <span class="bpf-fl">รุ่น</span><input type="text" class="bpf-inp" name="detonate_radio_model" style="max-width:80px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-cg bpf-i3">
                        <span class="bpf-fl">สี</span><input type="text" class="bpf-inp" name="detonate_radio_color" style="max-width:60px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <span class="bpf-fl">s/n</span><input type="text" class="bpf-inp" name="detonate_radio_sn"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="detonate_phone" value="1">โทรศัพท์มือถือ</label>
                        <span class="bpf-fl">ยี่ห้อ</span><input type="text" class="bpf-inp" name="detonate_phone_brand" style="max-width:80px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <span class="bpf-fl">รุ่น</span><input type="text" class="bpf-inp" name="detonate_phone_model" style="max-width:80px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-cg bpf-i3">
                        <span class="bpf-fl">สี</span><input type="text" class="bpf-inp" name="detonate_phone_color" style="max-width:60px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <span class="bpf-fl">s/n</span><input type="text" class="bpf-inp" name="detonate_phone_sn"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="detonate_remote" value="1">รีโมทคอนโทรล</label>
                        <input type="text" class="bpf-inp" name="detonate_remote_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="detonate_timer" value="1">ตั้งเวลา</label>
                        <input type="text" class="bpf-inp" name="detonate_timer_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="detonate_other" value="1">อื่น ๆ</label>
                        <input type="text" class="bpf-inp" name="detonate_other_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- สะเก็ดระเบิด -->
                    <div class="bpf-bh bpf-i1" style="margin-top:5px;"><span class="bpf-bk"></span><span>สะเก็ดระเบิด</span></div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="fragment_rebar" value="1">เหล็กเส้นตัดท่อน</label>
                        <input type="text" class="bpf-inp-s" name="fragment_rebar_size" style="max-width:30px;"><span class="bpf-fl">หุน</span>
                        <span class="bpf-fl">ยาว</span><input type="text" class="bpf-inp-s" name="fragment_rebar_length" style="max-width:30px;"><span class="bpf-fl">ซม.</span>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="fragment_nail" value="1">ตะปู</label>
                        <input type="text" class="bpf-inp-s" name="fragment_nail_size" style="max-width:40px;"><span class="bpf-fl">นิ้ว</span>
                    </div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="fragment_other" value="1">อื่น ๆ</label>
                        <input type="text" class="bpf-inp" name="fragment_other_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- ส่วนประกอบของวัตถุระเบิดอื่น ๆ -->
                    <div class="bpf-bh bpf-i1" style="margin-top:5px;"><span class="bpf-bk"></span><span>ส่วนประกอบของวัตถุระเบิดอื่น ๆ</span></div>
                    <div class="bpf-cg bpf-i2"><label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="comp_booster" value="1">หลอดดินขยาย</label><input type="text" class="bpf-inp" name="comp_booster_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div class="bpf-cg bpf-i2"><label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="comp_detonator" value="1">เชื้อปะทุไฟฟ้า</label><input type="text" class="bpf-inp" name="comp_detonator_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div class="bpf-cg bpf-i2"><label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="comp_tape" value="1">เทปพันสายไฟ</label><input type="text" class="bpf-inp" name="comp_tape_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div class="bpf-cg bpf-i2"><label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="comp_sim" value="1">ซิมการ์ด</label><input type="text" class="bpf-inp" name="comp_sim_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div class="bpf-cg bpf-i2"><label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="comp_circuit" value="1">วงจรการจุดระเบิด</label><input type="text" class="bpf-inp" name="comp_circuit_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div class="bpf-cg bpf-i2">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="comp_battery" value="1">แบตเตอรี่</label>
                        <input type="text" class="bpf-inp" name="comp_battery_detail" style="max-width:80px;"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <span class="bpf-fl">V</span><input type="text" class="bpf-inp-s" name="comp_battery_voltage" style="max-width:30px;">
                    </div>
                    <div class="bpf-cg bpf-i2"><label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="comp_dtmf" value="1">แผงวงจร DTMF</label><input type="text" class="bpf-inp" name="comp_dtmf_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div class="bpf-cg bpf-i2"><label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="comp_pcb" value="1">แผงวงจร</label><input type="text" class="bpf-inp" name="comp_pcb_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div class="bpf-cg bpf-i2"><label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="comp_wire" value="1">สายไฟวงจร</label><input type="text" class="bpf-inp" name="comp_wire_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div class="bpf-cg bpf-i2"><label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="comp_box" value="1">กล่องบรรจุวงจร</label><input type="text" class="bpf-inp" name="comp_box_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div class="bpf-cg bpf-i2"><label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="comp_clock" value="1">นาฬิกา</label><input type="text" class="bpf-inp" name="comp_clock_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div class="bpf-cg bpf-i2"><label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="comp_lever" value="1">กระเดื่อง</label><input type="text" class="bpf-inp" name="comp_lever_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div class="bpf-cg bpf-i2"><label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="comp_pin" value="1">สลักนิรภัย</label><input type="text" class="bpf-inp" name="comp_pin_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                    <div class="bpf-cg bpf-i2"><label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="comp_misc_other" value="1">อื่น ๆ</label><input type="text" class="bpf-inp" name="comp_misc_other_detail"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="bpf-col-right">
            <div class="bpf-row-header"><div class="bpf-lbl-seq">ลำดับ</div><div class="bpf-lbl-data">ข้อมูล</div></div>

            <!-- 7. (ต่อ) -->
            <div class="bpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="bpf-sec-label"><span class="bpf-sec-num">7.</span><span class="bpf-sec-txt">(ต่อ)</span></div>
                <div class="bpf-sec-body">

                    <!-- คราบสีแดงคล้ายโลหิต -->
                    <div class="bpf-fr">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="evidence_blood_stain" value="1">คราบสีแดงคล้ายโลหิต</label>
                        <input type="text" class="bpf-inp" name="blood_stain_detail">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <textarea class="bpf-ta bpf-i1" name="blood_stain_detail_2" id="bpf_blood_detail_2" rows="3"></textarea>

                    <!-- ทดสอบด้วยชุดทดสอบคราบโลหิตเบื้องต้น -->
                    <div class="bpf-i1" style="margin-top:4px; margin-bottom:2px;">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="test_blood_main" value="1">ทดสอบด้วยชุดทดสอบคราบโลหิตเบื้องต้น</label>
                    </div>
                    <div class="bpf-i2">
                        <div class="bpf-cg"><label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="test_hemastix" value="1">Hemastix</label></div>
                        <div class="bpf-cg bpf-i1">
                            <label class="bpf-ck"><input type="checkbox" class="bpf-cb bpf-radio-toggle" name="hemastix_result" value="มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน" data-group="bpf_hema">เกิดการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน</label>
                        </div>
                        <div class="bpf-cg bpf-i1">
                            <label class="bpf-ck"><input type="checkbox" class="bpf-cb bpf-radio-toggle" name="hemastix_result" value="ไม่มีการเปลี่ยนแปลง" data-group="bpf_hema">ไม่เกิดการเปลี่ยนแปลง</label>
                        </div>
                    </div>
                    <div class="bpf-i2" style="margin-top:2px;">
                        <div class="bpf-cg"><label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="test_phenolphthalein" value="1">Phenolphthalein</label></div>
                        <div class="bpf-cg bpf-i1">
                            <label class="bpf-ck"><input type="checkbox" class="bpf-cb bpf-radio-toggle" name="phenol_result" value="มีการเปลี่ยนแปลงเป็นสีชมพูในทันที" data-group="bpf_phenol">เกิดการเปลี่ยนแปลงเป็นสีชมพูในทันที</label>
                        </div>
                        <div class="bpf-cg bpf-i1">
                            <label class="bpf-ck"><input type="checkbox" class="bpf-cb bpf-radio-toggle" name="phenol_result" value="ไม่มีการเปลี่ยนแปลง" data-group="bpf_phenol">ไม่เกิดการเปลี่ยนแปลง</label>
                        </div>
                    </div>

                    <!-- วัตถุพยานอื่นๆ -->
                    <div class="bpf-fr" style="margin-top:4px;">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="has_evidence_other" value="1">วัตถุพยานอื่นๆ</label>
                        <input type="text" class="bpf-inp" name="evidence_other_detail">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <textarea class="bpf-ta bpf-i1" name="evidence_other_detail_2" id="bpf_evidence_other_detail_2" rows="3"></textarea>

                    <!-- วัตถุพยานที่ตรวจเก็บ -->
                    <div class="bpf-bh" style="margin-top:6px;"><span class="bpf-bk"></span><span>วัตถุพยานที่ตรวจเก็บ/การดำเนินการเกี่ยวกับวัตถุพยาน</span></div>
                    <div style="padding-left:15px; font-weight:600; font-size:11.5px;">เพื่อส่งตรวจพิสูจน์</div>

                    <div class="bpf-fr bpf-i1" style="margin-top:2px;">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="collected_evidence[]" value="สารพันธุกรรม">วัตถุพยานประเภทสารพันธุกรรม</label>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="bpf_ta_dna" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <textarea class="bpf-ta bpf-i1" name="collected_dna_detail" id="bpf_ta_dna" rows="3"></textarea>

                    <div class="bpf-fr bpf-i1" style="margin-top:0;">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="collected_evidence[]" value="ลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง">วัตถุพยานประเภทลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง</label>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="bpf_ta_fingerprint" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <textarea class="bpf-ta" name="fingerprint_detail" id="bpf_ta_fingerprint" rows="3"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="bpf-footer">
        <div class="bpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="bpf-footer-right">F-CS-10 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 4 ============================== -->
<!-- ================================================================ -->
<div class="bpf-page">

    <div class="bpf-header">
        <div class="bpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="bpf-header-center">
            <div class="bpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="bpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด</div>
        </div>
        <div class="bpf-header-right">
            <div class="bpf-doc-box">
                <div class="bpf-doc-line">เลขรับที่/เลขรายงาน <span class="bpf-rpt-no-mirror"></span> / 25<span class="bpf-rpt-year-mirror"></span></div>
                <div class="bpf-doc-line">หน้าที่ <span class="bpf-cur-page">4</span> / <span class="bpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div class="bpf-form-body">
        <!-- LEFT COLUMN -->
        <div class="bpf-col-left">
            <div class="bpf-row-header"><div class="bpf-lbl-seq">ลำดับ</div><div class="bpf-lbl-data">ข้อมูล</div></div>

            <!-- 7. (ต่อ) วัตถุพยาน Toolmarks + สารระเบิด + ส่วนประกอบ -->
            <div class="bpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="bpf-sec-label"><span class="bpf-sec-num">7.</span><span class="bpf-sec-txt">(ต่อ)</span></div>
                <div class="bpf-sec-body">
                    <div class="bpf-fr" style="margin-top:0;">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="collected_evidence[]" value="ร่องรอยการตัด">วัตถุพยานประเภทร่องรอยการตัด (Toolmarks)</label>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="bpf_ta_toolmark" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <textarea class="bpf-ta" name="collected_toolmark_detail" id="bpf_ta_toolmark" rows="4"></textarea>

                    <div class="bpf-fr" style="margin-top:0;">
                        <label class="bpf-ck" style="margin-right:0;"><input type="checkbox" class="bpf-cb" name="collected_evidence[]" value="สารระเบิด">วัตถุพยานประเภทสารระเบิด</label>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="bpf_ta_explosive" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <textarea class="bpf-ta" name="collected_explosive_detail" id="bpf_ta_explosive" rows="5"></textarea>

                    <div class="bpf-fr" style="margin-top:0;">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="collected_evidence[]" value="ส่วนประกอบของวัตถุระเบิด">วัตถุพยานประเภทส่วนประกอบของวัตถุระเบิด</label>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="bpf_ta_comp" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <textarea class="bpf-ta" name="collected_comp_detail" id="bpf_ta_comp" rows="5"></textarea>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="bpf-col-right">
            <div class="bpf-row-header"><div class="bpf-lbl-seq">ลำดับ</div><div class="bpf-lbl-data">ข้อมูล</div></div>

            <!-- 7. (ต่อ) วัตถุพยานอื่นๆ + การตรวจสอบครั้งสุดท้าย -->
            <div class="bpf-sec-row">
                <div class="bpf-sec-label"><span class="bpf-sec-num">7.</span><span class="bpf-sec-txt">(ต่อ)</span></div>
                <div class="bpf-sec-body">
                    <div class="bpf-fr" style="margin-top:2px;">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="collected_evidence[]" value="อื่นๆ">วัตถุพยานประเภทอื่น ๆ</label>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="bpf_ta_other" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <textarea class="bpf-ta bpf-i1" name="other_evidence_type" id="bpf_ta_other" rows="6"></textarea>

                    <!-- การตรวจสอบครั้งสุดท้าย -->
                    <div class="bpf-cg bpf-i1" style="margin-top:4px;">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="final_check[]" value="การตรวจสอบครั้งสุดท้าย">การตรวจสอบครั้งสุดท้าย</label>
                    </div>
                    <div class="bpf-cg bpf-i1">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="final_check[]" value="ตรวจเก็บวัตถุพยานครบถ้วน">ตรวจเก็บวัตถุพยานครบถ้วน</label>
                    </div>
                    <div class="bpf-cg bpf-i1">
                        <label class="bpf-ck"><input type="checkbox" class="bpf-cb" name="final_check[]" value="ถ่ายภาพสถานที่เกิดเหตุและดำเนินการส่งมอบสถานที่เกิดเหตุให้แก่พนักงานสอบสวน">ถ่ายภาพสถานที่เกิดเหตุ และดำเนินการส่งมอบสถานที่</label>
                    </div>
                    <div class="bpf-i2" style="font-size:11.5px;">เกิดเหตุให้แก่พนักงานสอบสวน</div>
                </div>
            </div>

            <!-- 8. การส่งมอบคืนสถานที่เกิดเหตุ -->
            <div class="bpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="bpf-sec-label">
                    <span class="bpf-sec-num">8.</span>
                    <span class="bpf-sec-txt">การส่ง<br>มอบคืน<br>สถานที่<br>เกิดเหตุ</span>
                </div>
                <div class="bpf-sec-body">
                    <div class="bpf-bh" style="margin-top:0;"><span class="bpf-bk"></span><span>วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเสร็จสิ้น</span></div>
                    <div class="bpf-fr bpf-i1">
                        <span class="bpf-fl">วันที่</span>
                        <input type="date" class="bpf-inp-m" name="inspection_end_date" id="bpf_inspection_end_date">
                        <span class="bpf-fl">เวลาโดยประมาณ</span>
                        <input type="time" class="bpf-inp" name="inspection_end_time" id="bpf_inspection_end_time" style="text-align:center;">
                        <span class="bpf-fl">น.</span>
                    </div>

                    <div class="bpf-bh" style="margin-top:6px;"><span class="bpf-bk"></span><span>การส่งมอบสถานที่เกิดเหตุ</span></div>

                    <!-- ผู้รับมอบ -->
                    <div class="bpf-fr" style="margin-top:6px; align-items:flex-end;">
                        <span class="bpf-fl">ลงชื่อ</span>
                        <div style="flex:1; min-height:50px; border-bottom:1px dotted #888; position:relative;">
                            <canvas id="bpf_sig_receiver" style="width:100%; height:50px; cursor:crosshair;"></canvas>
                            <input type="hidden" name="receiver_signature_data_bomb" id="bpf_receiver_sig_data">
                        </div>
                        <span class="bpf-fl">ผู้รับมอบสถานที่เกิดเหตุ</span>
                    </div>
                    <div class="bpf-fr" style="margin-top:2px; justify-content:flex-end;">
                        <button type="button" class="bpf-add-btn" onclick="bpfClearCanvas('bpf_sig_receiver')">ล้างลายเซ็นผู้รับมอบ</button>
                    </div>
                    <div class="bpf-fr">
                        <span class="bpf-fl" style="visibility:hidden;">ลงชื่อ</span>
                        <span class="bpf-fl">(</span>
                        <select class="bpf-sel" name="receiver_name" id="bpf_receiver_name" style="text-align:center;">
                            <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้รับมอบ --', $bpfInspectorOptions) ?>
                        </select>
                        <span class="bpf-fl">)</span>
                    </div>
                    <div class="bpf-fr">
                        <span class="bpf-fl">ตำแหน่ง</span>
                        <input type="text" class="bpf-inp" name="receiver_position" id="bpf_receiver_pos" readonly>
                    </div>

                    <!-- ผู้ส่งมอบ -->
                    <div class="bpf-fr" style="margin-top:8px; align-items:flex-end;">
                        <span class="bpf-fl">ลงชื่อ</span>
                        <div style="flex:1; min-height:50px; border-bottom:1px dotted #888; position:relative;">
                            <canvas id="bpf_sig_sender" style="width:100%; height:50px; cursor:crosshair;"></canvas>
                            <input type="hidden" name="sender_signature_data_bomb" id="bpf_sender_sig_data">
                        </div>
                        <span class="bpf-fl">ผู้ส่งมอบสถานที่เกิดเหตุ</span>
                    </div>
                    <div class="bpf-fr" style="margin-top:2px; justify-content:flex-end;">
                        <button type="button" class="bpf-add-btn" onclick="bpfClearCanvas('bpf_sig_sender')">ล้างลายเซ็นผู้ส่งมอบ</button>
                    </div>
                    <div class="bpf-fr">
                        <span class="bpf-fl" style="visibility:hidden;">ลงชื่อ</span>
                        <span class="bpf-fl">(</span>
                        <select class="bpf-sel" name="sender_name" id="bpf_sender_name" style="text-align:center;">
                            <?= str_replace('-- เลือกผู้ตรวจ --', '-- เลือกผู้ส่งมอบ --', $bpfInspectorOptions) ?>
                        </select>
                        <span class="bpf-fl">)</span>
                    </div>
                    <div class="bpf-fr">
                        <span class="bpf-fl">ตำแหน่ง</span>
                        <input type="text" class="bpf-inp" name="sender_position" id="bpf_sender_pos" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bpf-footer">
        <div class="bpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="bpf-footer-right">F-CS-10 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 5 (SKETCH — Page 1) ============ -->
<!-- ================================================================ -->
<div class="bpf-page bpf-sketch-page" id="bpf_sketch_page_1" data-sketch-page="1">

    <div class="bpf-header">
        <div class="bpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="bpf-header-center">
            <div class="bpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="bpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด</div>
        </div>
        <div class="bpf-header-right">
            <div class="bpf-doc-box">
                <div class="bpf-doc-line">เลขรับที่/เลขรายงาน <span class="bpf-rpt-no-mirror"></span> / 25<span class="bpf-rpt-year-mirror"></span></div>
                <div class="bpf-doc-line">หน้าที่ <span class="bpf-cur-page">5</span> / <span class="bpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
        <div></div>
        <div style="font-size:13px; font-weight:600;">แผนผังสังเขป</div>
        <div style="display:flex; gap:4px; align-items:center;">
            <label class="bpf-add-btn" style="padding:2px 8px; cursor:pointer; margin:0;" title="แนบรูปภาพพื้นหลัง">
                <i class="fas fa-image me-1"></i> แนบรูป
                <input type="file" accept="image/*" style="display:none;" onchange="bpfSketchAttachImage('bpf_sketch_page_1',this)">
            </label>
            <button type="button" class="bpf-add-btn" style="padding:2px 8px;" onclick="bpfSketchRemoveBg('bpf_sketch_page_1')" title="ลบรูปพื้นหลัง"><i class="fas fa-times"></i> ลบรูป</button>
        </div>
    </div>

    <div class="bpf-sketch-viewport" id="bpf_sketch_page_1_viewport">
        <div class="bpf-sketch-canvas-wrap" id="bpf_sketch_page_1_wrap" style="aspect-ratio:1120/660;">
            <canvas id="bpf_sketch_page_1_canvas" width="1120" height="660"></canvas>
        </div>
        <div class="bpf-not-to-scale">* NOT TO SCALE</div>
    </div>
    <div style="text-align:right; margin-top:4px; display:flex; justify-content:flex-end; gap:6px; align-items:center;">
        <button type="button" class="bpf-add-btn" onclick="sketchUndo('bpf_sketch_page_1_canvas')" style="padding:2px 8px;"><i class="fas fa-undo"></i> ย้อนกลับ</button>
        <button type="button" class="bpf-add-btn" id="bpf_sketch_page_1_canvas_eraser_btn" onclick="sketchToggleEraser('bpf_sketch_page_1_canvas')" style="padding:2px 8px;" title="ยางลบ"><i class="fas fa-eraser"></i></button>
        <div style="display:flex;align-items:center;gap:4px;">
            <i class="fas fa-pen" style="font-size:10px;color:#666;"></i>
            <input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize('bpf_sketch_page_1_canvas',this.value);this.nextElementSibling.textContent=this.value+'px'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">
            <span style="font-size:11px;color:#666;min-width:35px;">2px</span>
        </div>
        <label style="margin:0;cursor:pointer;" title="เลือกสี"><input type="color" value="#000000" onchange="sketchSetColor('bpf_sketch_page_1_canvas',this.value)" style="width:26px;height:22px;border:1px solid #aaa;border-radius:3px;padding:0;cursor:pointer;"></label>
        <button type="button" class="bpf-add-btn" onclick="bpfSketchClearPage('bpf_sketch_page_1')">ล้างกระดาน</button>
    </div>

    <!-- Hidden inputs for data persistence -->
    <input type="hidden" name="scene_sketch_data_bomb" id="bpf_scene_sketch_data">
    <input type="hidden" name="scene_sketch_pages_bomb" id="bpf_scene_sketch_pages_data">

    <div style="margin-bottom:8px; margin-top:6px;">
        <div class="bpf-fr">
            <span class="bpf-fl">หมายเหตุ</span><button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="bpf_sketch_remark" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
        </div>
        <textarea class="bpf-ta" name="sketch_remark_bomb" id="bpf_sketch_remark" rows="6"></textarea>
    </div>

    <div style="margin-top:10px; display:flex; flex-direction:column; align-items:flex-end;">
        <div class="bpf-fr" style="width:auto;">
            <span class="bpf-fl">ผู้จดบันทึก</span>
            <select class="bpf-sel" name="sketch_recorder_bomb" id="bpf_recorder_name" style="width:250px;">
                <?= str_replace('-- เลือกผู้จดบันทึก --', '-- เลือก --', $bpfCollectorOptions) ?>
            </select>
        </div>
        <div class="bpf-fr" style="width:auto;">
            <span class="bpf-fl">วัน เวลา</span>
            <input type="datetime-local" class="bpf-inp" name="sketch_datetime_bomb" id="bpf_recorder_datetime" style="width:250px; text-align:center;">
        </div>
    </div>

    <div style="text-align:center; margin-top:8px;">
        <button type="button" class="bpf-add-btn" onclick="bpfSketchAddPage()" style="padding:3px 14px; font-size:12px;">
            <i class="fas fa-plus me-1"></i> เพิ่มหน้าแผนผัง
        </button>
    </div>

    <div class="bpf-footer">
        <div class="bpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="bpf-footer-right">F-CS-10 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>
<!-- ★ Dynamic sketch pages will be inserted here by JS (before PAGE 6) -->

<!-- ================================================================ -->
<!-- ========================= PAGE 6 (EVIDENCE TABLE) ============= -->
<!-- ================================================================ -->
<div class="bpf-page">

    <div class="bpf-header">
        <div class="bpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="bpf-header-center">
            <div class="bpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="bpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด</div>
        </div>
        <div class="bpf-header-right">
            <div class="bpf-doc-box">
                <div class="bpf-doc-line">เลขรับที่/เลขรายงาน <span class="bpf-rpt-no-mirror"></span> / 25<span class="bpf-rpt-year-mirror"></span></div>
                <div class="bpf-doc-line">หน้าที่ <span class="bpf-cur-page">6</span> / <span class="bpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:6px;">
        <span class="bpf-bk"></span>
        <span style="font-size:12px; font-weight:600;">วัตถุพยานและตำแหน่งที่ตรวจพบ</span>
        <button type="button" class="bpf-add-btn" style="float:right;" onclick="bpfAddEvidenceRow()">+ เพิ่มรายการ</button>
    </div>

    <div style="overflow-x:auto; width:100%;">
    <table class="bpf-ev-table" style="width:100%; border-collapse:collapse; font-size:11px; min-width:950px;">
        <thead>
            <tr>
                <th rowspan="2" style="border:1.5px solid #000; width:35px; padding:2px; text-align:center; vertical-align:middle;">ป้าย<br>หมายเลข</th>
                <th rowspan="2" style="border:1.5px solid #000; min-width:180px; padding:2px; text-align:center; vertical-align:middle;">วัตถุพยาน</th>
                <th colspan="4" style="border:1.5px solid #000; padding:2px; text-align:center;">ระยะห่าง (m) จากจุด<br>อ้างอิง</th>
                <th rowspan="2" style="border:1.5px solid #000; width:90px; padding:2px; text-align:center; vertical-align:middle;">Azimuth<br>พิกัด/องศา/<br>ระยะ</th>
                <th rowspan="2" style="border:1.5px solid #000; width:70px; padding:2px; text-align:center; vertical-align:middle;">หมายเหตุ</th>
                <th rowspan="2" style="border:1.5px solid #000; width:110px; padding:2px; text-align:center; vertical-align:middle;">การตรวจพิสูจน์</th>
                <th rowspan="2" style="border:1.5px solid #000; width:25px; padding:2px; text-align:center; vertical-align:middle; font-size:9px;">ลบ</th>
            </tr>
            <tr>
                <th style="border:1.5px solid #000; width:30px; padding:2px; text-align:center;">1</th>
                <th style="border:1.5px solid #000; width:30px; padding:2px; text-align:center;">2</th>
                <th style="border:1.5px solid #000; width:30px; padding:2px; text-align:center;">3</th>
                <th style="border:1.5px solid #000; width:30px; padding:2px; text-align:center;">4</th>
            </tr>
        </thead>
        <tbody id="bpf_evidence_tbody">
            <tr>
                <td><input type="text" name="bomb_ev_label[]" style="width:35px;"></td>
                <td><input type="text" name="bomb_ev_item[]" style="width:100%;"></td>
                <td><input type="text" name="bomb_ev_level_1_0" style="width:40px; text-align:center;" inputmode="decimal"></td>
                <td><input type="text" name="bomb_ev_level_2_0" style="width:40px; text-align:center;" inputmode="decimal"></td>
                <td><input type="text" name="bomb_ev_level_3_0" style="width:40px; text-align:center;" inputmode="decimal"></td>
                <td><input type="text" name="bomb_ev_level_4_0" style="width:40px; text-align:center;" inputmode="decimal"></td>
                <td><input type="text" name="bomb_ev_azimuth[]"></td>
                <td><input type="text" name="bomb_ev_remark[]"></td>
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
                        <option value="explosive">วัตถุระเบิด (กก.กตว.)</option>
                    </select>
                    <input type="hidden" class="lab-unit-value" name="evidence_lab_unit_bomb[]" value="">
                </td>
                <td><button type="button" class="bpf-del-btn" onclick="bpfDelRow(this)">×</button></td>
            </tr>
        </tbody>
    </table>
    </div>

    <div style="margin-top:8px; font-size:11.5px;">
        <div class="bpf-fr" style="margin-bottom:2px;"><span class="bpf-fl">จุดอ้างอิงที่ 1 คือ</span><input type="text" class="bpf-inp" name="reference_point_1_bomb"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
        <div class="bpf-fr" style="margin-bottom:2px;"><span class="bpf-fl">จุดอ้างอิงที่ 2 คือ</span><input type="text" class="bpf-inp" name="reference_point_2_bomb"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
        <div class="bpf-fr" style="margin-bottom:2px;"><span class="bpf-fl">จุดอ้างอิงที่ 3 คือ</span><input type="text" class="bpf-inp" name="reference_point_3_bomb"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
        <div class="bpf-fr" style="margin-bottom:2px;"><span class="bpf-fl">จุดอ้างอิงที่ 4 คือ</span><input type="text" class="bpf-inp" name="reference_point_4_bomb"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button></div>
    </div>

    <div style="margin-top:12px; display:flex; flex-direction:column; align-items:flex-end;">
        <div class="bpf-fr" style="width:auto;"><span class="bpf-fl">ผู้จดบันทึก</span><select class="bpf-sel" name="collector_name_bomb" id="bpf_ev_collector" style="width:250px;"><?php echo $bpfCollectorOptions; ?></select></div>
        <div class="bpf-fr" style="width:auto;"><span class="bpf-fl"></span><input type="datetime-local" class="bpf-inp" name="collection_datetime_bomb" style="width:250px; text-align:center;"></div>
    </div>

    <div class="bpf-footer">
        <div class="bpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="bpf-footer-right">F-CS-10 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ================== PAGE 7 (BODY DIAGRAM) ====================== -->
<!-- ================================================================ -->
<div class="bpf-page" style="display:flex; flex-direction:column;">

    <div class="bpf-header">
        <div class="bpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="bpf-header-center">
            <div class="bpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="bpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด</div>
            <div style="font-size:11px; font-weight:600;">แผนผังภาพแสดงตำแหน่งบาดแผล</div>
        </div>
        <div class="bpf-header-right">
            <div class="bpf-doc-box">
                <div class="bpf-doc-line">เลขรับที่/เลขรายงาน <span class="bpf-rpt-no-mirror"></span> / 25<span class="bpf-rpt-year-mirror"></span></div>
                <div class="bpf-doc-line">หน้าที่ <span class="bpf-cur-page">7</span> / <span class="bpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:4px;">
        <div class="bpf-fr">
            <span class="bpf-fl">ชื่อ-สกุล(ผู้เสียชีวิต/บาดเจ็บ)</span>
            <input type="text" class="bpf-inp" name="victim_name_bomb_diagram">
            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
            <span class="bpf-fl">อายุ</span>
            <input type="text" class="bpf-inp-s" name="victim_age_bomb_diagram" style="width:40px;">
            <span class="bpf-fl">ปี</span>
            <span class="bpf-fl" style="margin-left:10px;">แพทย์ผู้ชันสูตร</span>
            <input type="text" class="bpf-inp" name="autopsy_doctor_bomb">
            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
        </div>
    </div>

    <div class="bpf-body-diagram" style="flex:1;">
        <img src="./assets/images/body_diagram.png" alt="Body Diagram" style="position:absolute; top:0; left:0; width:100%; height:100%; object-fit:contain; z-index:1; pointer-events:none;">
        <canvas id="bpf_body_diagram_canvas" style="position:absolute; top:0; left:0; width:100%; height:100%; z-index:2; background:transparent;"></canvas>
        <input type="hidden" name="body_diagram_data_bomb" id="bpf_body_diagram_data">
        <input type="hidden" name="body_diagram_strokes_bomb" id="bpf_body_diagram_strokes">
    </div>
    <!-- Updated: 2026-05-26 10:02 - Changed to slider for pen size -->
    <div style="text-align:right; margin-top:4px; display:flex; justify-content:flex-end; gap:6px; align-items:center;">
        <button type="button" class="bpf-add-btn" onclick="sketchUndo('bpf_body_diagram_canvas')" style="padding:2px 8px;"><i class="fas fa-undo"></i> ย้อนกลับ</button>
        <button type="button" class="bpf-add-btn" id="bpf_body_diagram_eraser_btn" onclick="sketchToggleEraser('bpf_body_diagram_canvas')" style="padding:2px 8px;" title="ยางลบ"><i class="fas fa-eraser"></i></button>
        <div style="display:flex;align-items:center;gap:4px;">
            <i class="fas fa-pen" style="font-size:10px;color:#666;"></i>
            <input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize('bpf_body_diagram_canvas',this.value);this.nextElementSibling.textContent=this.value+'px'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">
            <span style="font-size:11px;color:#666;min-width:35px;">2px</span>
        </div>
        <label style="margin:0;cursor:pointer;" title="เลือกสี"><input type="color" value="#cc0000" onchange="sketchSetColor('bpf_body_diagram_canvas',this.value)" style="width:26px;height:22px;border:1px solid #aaa;border-radius:3px;padding:0;cursor:pointer;"></label>
        <button type="button" class="bpf-add-btn" onclick="bpfClearCanvas('bpf_body_diagram_canvas')">ล้างกระดาน</button>
    </div>

    <div style="margin-top:8px;">
        <div class="bpf-fr">
            <span class="bpf-fl">หมายเหตุ</span><button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="bpf_body_diagram_remark" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
        </div>
        <textarea class="bpf-ta" name="body_diagram_remark_bomb" id="bpf_body_diagram_remark" rows="3"></textarea>
    </div>

    <div style="margin-top:12px; display:flex; flex-direction:column; align-items:flex-end;">
        <div class="bpf-fr" style="width:auto;">
            <span class="bpf-fl">ผู้จดบันทึก</span>
            <select class="bpf-sel" name="body_diagram_recorder_bomb" style="width:250px;">
                <?= str_replace('-- เลือกผู้จดบันทึก --', '-- เลือก --', $bpfCollectorOptions) ?>
            </select>
        </div>
        <div class="bpf-fr" style="width:auto;">
            <span class="bpf-fl">วัน/เวลา</span>
            <input type="datetime-local" class="bpf-inp" name="body_diagram_datetime_bomb" style="width:250px; text-align:center;">
        </div>
    </div>

    <div class="bpf-footer" style="margin-top:auto;">
        <div class="bpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="bpf-footer-right">F-CS-10 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ============== PAGE 8 (EVIDENCE COLLECTION) =================== -->
<!-- ================================================================ -->
<div class="bpf-page" style="display:flex; flex-direction:column;">

    <div class="bpf-header">
        <div class="bpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="bpf-header-center">
            <div class="bpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="bpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด</div>
            <div style="font-size:11px; font-weight:600;">บันทึกการตรวจเก็บวัตถุพยาน</div>
        </div>
        <div class="bpf-header-right">
            <div class="bpf-doc-box">
                <div class="bpf-doc-line">เลขรับที่/เลขรายงาน <span class="bpf-rpt-no-mirror"></span> / 25<span class="bpf-rpt-year-mirror"></span></div>
                <div class="bpf-doc-line">หน้าที่ <span class="bpf-cur-page">8</span> / <span class="bpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:6px;">
        <div class="bpf-fr">
            <span class="bpf-fl">วันที่ตรวจสถานที่เกิดเหตุ</span>
            <input type="date" class="bpf-inp" name="measurement_inspection_date_bomb" id="bpf_measurement_date" style="text-align:center;">
            <span class="bpf-fl">เวลาประมาณ</span>
            <input type="time" class="bpf-inp" name="measurement_inspection_time_bomb" id="bpf_measurement_time" style="text-align:center;">
            <span class="bpf-fl">น.</span>
        </div>
        <button type="button" class="bpf-add-btn" style="float:right;" onclick="bpfAddCollectionRow()">+ เพิ่มรายการ</button>
    </div>

    <div style="overflow-x:auto; width:100%;">
    <table class="bpf-ev-table" style="width:100%; border-collapse:collapse; font-size:10.5px; min-width:950px;">
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
                <th rowspan="2" style="border:1.5px solid #000; width:120px; padding:2px; text-align:center; vertical-align:middle;">การตรวจพิสูจน์</th>
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
        <tbody id="bpf_collection_tbody">
            <tr>
                <td style="text-align:center;">1</td>
                <td><input type="text" name="measurement_item_bomb[]"></td>
                <td><input type="text" name="measurement_quantity_bomb[]" style="width:30px; text-align:center;"></td>
                <td><input type="text" name="measurement_area_bomb[]"></td>
                <td><input type="text" name="measurement_label_number_bomb[]" style="width:30px; text-align:center;"></td>
                <td><input type="checkbox" name="measurement_package_plastic_check[0]" value="1"><input type="hidden" name="measurement_package_plastic_text[0]" value=""></td>
                <td><input type="checkbox" name="measurement_package_paper_check[0]" value="1"><input type="hidden" name="measurement_package_paper_text[0]" value=""></td>
                <td><input type="checkbox" name="measurement_package_other_check[0]" value="1"><input type="hidden" name="measurement_package_other_text[0]" value=""></td>
                <td><input type="checkbox" name="measurement_action_return_check[0]" value="1"><input type="hidden" name="measurement_action_return_text[0]" value=""></td>
                <td><input type="checkbox" name="measurement_action_other_check[0]" value="1"><input type="hidden" name="measurement_action_other_text[0]" value=""></td>
                <td><input type="text" name="measurement_remark_bomb[]"></td>
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
                        <option value="explosive">วัตถุระเบิด (กก.กตว.)</option>
                    </select>
                    <input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_bomb[]" value="">
                </td>
                <td><button type="button" class="bpf-del-btn" onclick="bpfDelRow(this)">×</button></td>
            </tr>
        </tbody>
    </table>
    </div>

    <div style="margin-top:12px; display:flex; flex-direction:column; align-items:flex-end;">
        <div class="bpf-fr" style="width:auto;"><span class="bpf-fl">ผู้เก็บวัตถุพยาน</span><select class="bpf-sel" name="measurement_recorder_bomb" id="bpf_measurement_recorder" style="width:250px;"><?php echo $bpfCollectorOptions; ?></select></div>
        <div class="bpf-fr" style="width:auto;"><span class="bpf-fl">วัน /เวลา</span><input type="datetime-local" class="bpf-inp" name="measurement_datetime_bomb" id="bpf_measurement_datetime" style="width:250px; text-align:center;"></div>
    </div>

    <div class="bpf-footer" style="margin-top:auto;">
        <div class="bpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="bpf-footer-right">F-CS-10 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ================== PAGE 9+ (PHOTOS) =========================== -->
<!-- ================================================================ -->
<div class="bpf-page bpf-photo-page" data-photo-page="1">

    <div class="bpf-header">
        <div class="bpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="bpf-header-center">
            <div class="bpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>
            <div class="bpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด</div>
            <div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ</div>
        </div>
        <div class="bpf-header-right">
            <div class="bpf-doc-box">
                <div class="bpf-doc-line">เลขรับที่/เลขรายงาน <span class="bpf-rpt-no-mirror"></span> / 25<span class="bpf-rpt-year-mirror"></span></div>
                <div class="bpf-doc-line">หน้าที่ <span class="bpf-cur-page">8</span> / <span class="bpf-total-page"></span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:8px;">
        <div class="bpf-fr" style="margin-bottom:6px;">
            <span class="bpf-fl">วันที่ตรวจสถานที่เกิดเหตุ</span>
            <input type="date" class="bpf-inp" name="photo_inspect_date_bomb" style="text-align:center;">
            <span class="bpf-fl">เวลาประมาณ</span>
            <input type="time" class="bpf-inp" name="photo_inspect_time_bomb" style="text-align:center;">
            <span class="bpf-fl">น.</span>
        </div>
        <div class="bpf-fr" style="flex-wrap:nowrap; margin-bottom:6px;">
            <span class="bpf-fl">รหัสภาพถ่ายที่</span>
            <input type="text" class="bpf-inp" name="photo_id_start_bomb" style="min-width:40px; text-align:center;" readonly>
            <span class="bpf-fl">ถึง</span>
            <input type="text" class="bpf-inp" name="photo_id_end_bomb" style="min-width:40px; text-align:center;" readonly>
            <span class="bpf-fl">จำนวน</span>
            <input type="text" class="bpf-inp-s" name="photo_amount_bomb" style="max-width:40px; text-align:center;" readonly>
            <span class="bpf-fl">ภาพ</span>
        </div>
        <div style="font-size:10.5px; font-style:italic; margin-top:2px;">(ตามภาพถ่ายรวมที่แนบ)</div>
    </div>

    <!-- Drag & Drop zone for PDF form -->
    <div class="bpf-photo-dropzone" id="bpf_photo_dropzone">
        <i class="fas fa-cloud-upload-alt" style="font-size:1.2rem; color:#90a4ae;"></i>
        <div style="font-size:10px; color:#666; margin-top:2px;">ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือก</div>
    </div>
    <input type="file" id="bpf_photo_input_gallery" accept="image/*" multiple style="display:none;" onchange="bpfPreviewPhotos(this)">
    <input type="file" id="bpf_photo_input_camera" accept="image/*" capture="environment" multiple style="display:none;" onchange="bpfPreviewPhotos(this)">

    <!-- Photo Grid (35 photos per page, populated by JS) -->
    <div class="bpf-photo-grid" id="bpf_photo_grid_1"></div>

    <div style="margin-top:auto;">
        <div style="display:flex; flex-direction:column; align-items:flex-end; margin-bottom:6px;">
        <div class="bpf-fr" style="width:auto;">
            <span class="bpf-fl">ผู้จดบันทึก</span>
            <select class="bpf-sel" name="photographer_name_bomb" id="bpf_photographer_name" style="width:250px;">
                <?php echo $bpfCollectorOptions; ?>
            </select>
        </div>
        <div class="bpf-fr" style="width:auto;">
            <span class="bpf-fl">วัน/เวลา</span>
            <input type="datetime-local" class="bpf-inp" name="photographer_datetime_bomb" id="bpf_photographer_datetime" style="width:250px; text-align:center;">
        </div>
        </div>
        <div class="bpf-footer">
            <div class="bpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
            <div class="bpf-footer-right">F-CS-10 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>
        </div>
    </div>
</div>

<!-- Container สำหรับหน้ารูปถ่ายเพิ่มเติม -->
<div id="bpf_extra_photo_pages"></div>

<!-- ปุ่มเพิ่มหน้ากระดาษรูปถ่าย -->
<button type="button" class="bpf-add-photo-page-btn" onclick="bpfAddPhotoPage()">
    <i class="fas fa-plus me-1"></i> เพิ่มหน้าบันทึกการถ่ายภาพ
</button>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <div>
                    <button type="button" class="btn btn-success btn-sm" id="btn_save_bomb_pdf" onclick="bpfSaveViaStandardForm()">
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
// ===== Global sketch variables =====
if (typeof window._sketchEraser === 'undefined') window._sketchEraser = {};
if (typeof window._sketchColor === 'undefined') window._sketchColor = {};
if (typeof window._sketchOrigColor === 'undefined') window._sketchOrigColor = {};
if (typeof window._sketchPenSize === 'undefined') window._sketchPenSize = {};

(function() {
    // ===== Radio-toggle (mutually exclusive checkboxes) =====
    document.querySelectorAll('#bombFormPdfModal .bpf-radio-toggle').forEach(function(cb) {
        cb.addEventListener('change', function() {
            if (!this.checked) return;
            var grp = this.getAttribute('data-group');
            if (!grp) return;
            document.querySelectorAll('#bombFormPdfModal .bpf-radio-toggle[data-group="' + grp + '"]').forEach(function(other) {
                if (other !== cb) other.checked = false;
            });
        });
    });

    // ===== Blood section toggle (PDF form) =====
    function bpfToggleBloodSection() {
        var form = document.getElementById('bombFormPdf');
        if (!form) return;

        var bloodMaster = form.querySelector('input[name="evidence_blood_stain"]');
        if (!bloodMaster) return;

        bpfSyncBloodTests();
    }

    function bpfSyncBloodTests() {
        var form = document.getElementById('bombFormPdf');
        if (!form) return;

        var bloodMaster = form.querySelector('input[name="evidence_blood_stain"]');
        var testMain = form.querySelector('input[name="test_blood_main"]');
        var testHema = form.querySelector('input[name="test_hemastix"]');
        var testPhenol = form.querySelector('input[name="test_phenolphthalein"]');
        var hemaResults = form.querySelectorAll('input[name="hemastix_result"]');
        var phenolResults = form.querySelectorAll('input[name="phenol_result"]');

        var bloodOn = !!(bloodMaster && bloodMaster.checked);
        var hemaOn = !!(testHema && testHema.checked);
        var phenolOn = !!(testPhenol && testPhenol.checked);
        var hasHemaResult = false;
        var hasPhenolResult = false;

        hemaResults.forEach(function(cb) {
            if (cb.checked) hasHemaResult = true;
        });
        phenolResults.forEach(function(cb) {
            if (cb.checked) hasPhenolResult = true;
        });

        // ไม่ล็อก checkbox ย่อย เพื่อให้ติ๊กได้ปกติ แต่ช่วย sync ตัวแม่ให้อัตโนมัติ
        if ((hemaOn || phenolOn || hasHemaResult || hasPhenolResult) && testMain) {
            testMain.checked = true;
        }
        if ((testMain && testMain.checked) && bloodMaster) {
            bloodMaster.checked = true;
            bloodOn = true;
        }
        if (hasHemaResult && testHema) testHema.checked = true;
        if (hasPhenolResult && testPhenol) testPhenol.checked = true;

        // ถ้าเอาติ๊กตัวแม่ออก ให้ล้างกลุ่มเลือดทั้งหมด
        if (!bloodOn) {
            if (testMain) testMain.checked = false;
            if (testHema) testHema.checked = false;
            if (testPhenol) testPhenol.checked = false;
            hemaResults.forEach(function(cb) { cb.checked = false; });
            phenolResults.forEach(function(cb) { cb.checked = false; });
        }
    }

    document.addEventListener('change', function(e) {
        if (!e || !e.target) return;
        var name = e.target.name || '';
        if (name === 'evidence_blood_stain') {
            bpfToggleBloodSection();
            return;
        }
        if (name === 'test_blood_main' || name === 'test_hemastix' || name === 'test_phenolphthalein') {
            bpfSyncBloodTests();
        }

        if (name === 'body_status_bomb[]') {
            bpfToggleBodyRow(e.target);
        }
    });

    function bpfToggleBodyRow(target) {
        var row = target.closest('.bpf-body-row');
        if (!row) return;

        var statuses = row.querySelectorAll('.bpf-body-status');
        var notFoundInput = row.querySelector('.bpf-body-notfound');
        var nameInput = row.querySelector('input[name="body_name_bomb[]"]');
        var conditionInput = row.querySelector('input[name="body_condition_bomb[]"]');
        var selectedValue = target.checked ? target.value : '';

        statuses.forEach(function(cb) {
            if (cb !== target) cb.checked = false;
        });

        if (selectedValue === 'ไม่พบศพ') {
            if (notFoundInput) {
                notFoundInput.style.display = '';
            }
            if (nameInput) nameInput.value = '';
            if (conditionInput) conditionInput.value = '';
        } else {
            if (notFoundInput) {
                notFoundInput.style.display = 'none';
                notFoundInput.value = '';
            }
        }
    }

    // ===== Inspector rows =====
    var bpfInspectorIdx = 1;
    window.bpfAddInspector = function() {
        bpfInspectorIdx++;
        var c = document.getElementById('bpf_inspector_container');
        var div = document.createElement('div');
        div.className = 'bpf-si bpf-inspector-row';
        div.innerHTML = '<span class="bpf-si-no">5.' + bpfInspectorIdx + '</span>' +
            '<select class="bpf-sel" name="inspector_id[]"><?= addslashes($bpfInspectorOptions) ?></select>' +
            ' <button type="button" class="bpf-del-btn" onclick="this.parentElement.remove(); if(typeof bpfRenumberInspectors===\'function\') bpfRenumberInspectors();">×</button>';
        c.appendChild(div);
    };

    // ===== Renumber inspector rows =====
    window.bpfRenumberInspectors = function() {
        var rows = document.querySelectorAll('#bpf_inspector_container .bpf-inspector-row');
        rows.forEach(function(row, idx) {
            var noSpan = row.querySelector('.bpf-si-no');
            if (noSpan) noSpan.textContent = '5.' + (idx + 1);
        });
        bpfInspectorIdx = rows.length;
    };

    // ===== Victim rows =====
    window.bpfAddVictim = function() {
        var c = document.getElementById('bpf_victim_container');
        var div = document.createElement('div');
        div.className = 'bpf-victim-row';
        div.style.cssText = 'margin-top:3px; padding:2px 0; border-top:1px dotted #ccc;';
        div.innerHTML = '<div class="bpf-fr">' +
            '<span class="bpf-fl">ประเภท</span>' +
            '<select class="bpf-sel" name="victim_type_bomb[]" style="max-width:90px;">' +
            '<option value="">--เลือก--</option><option value="ผู้เสียหาย">ผู้เสียหาย</option>' +
            '<option value="ผู้บาดเจ็บ">ผู้บาดเจ็บ</option><option value="ผู้เสียชีวิต">ผู้เสียชีวิต</option></select>' +
            '<span class="bpf-fl" style="margin-left:6px;">ชื่อ</span>' +
            '<input type="text" class="bpf-inp" name="victim_name_bomb[]">' +
            '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
            '<span class="bpf-fl" style="margin-left:4px;">อายุ</span>' +
            '<input type="text" class="bpf-inp-s" name="victim_age_bomb[]" style="max-width:30px;">' +
            '<span class="bpf-fl">ปี</span>' +
            ' <button type="button" class="bpf-del-btn" onclick="this.closest(\'.bpf-victim-row\').remove()">×</button>' +
            '</div>';
        c.appendChild(div);
        bpfBombApplyNumericAttributes(div);
    };

    // ===== Body rows (ศพ/ผู้บาดเจ็บ) =====
    window.bpfAddBody = function() {
        var c = document.getElementById('bpf_bodies_container');
        var rowIndex = c.querySelectorAll('.bpf-body-row').length;
        var div = document.createElement('div');
        div.className = 'bpf-body-row';
        div.style.cssText = 'border-top:1px dotted #ccc; padding-top:4px; margin-top:4px;';
        div.innerHTML =
            '<div class="bpf-fr">' +
            '<label class="bpf-ck"><input type="checkbox" class="bpf-cb bpf-radio-toggle bpf-body-status" name="body_status_bomb[]" value="พบศพ" data-group="bpf_body_status_' + rowIndex + '">พบศพ</label>' +
            '<label class="bpf-ck"><input type="checkbox" class="bpf-cb bpf-radio-toggle bpf-body-status" name="body_status_bomb[]" value="ไม่พบศพ" data-group="bpf_body_status_' + rowIndex + '">ไม่พบศพ</label>' +
            '<input type="text" class="bpf-inp bpf-body-notfound" name="body_notfound_detail_bomb[]" style="max-width:180px; display:none;" placeholder="ระบุเหตุผล">' +
            '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
            '</div>' +
            '<div class="bpf-fr">' +
            '<span class="bpf-fl">ชื่อ-สกุล</span>' +
            '<input type="text" class="bpf-inp" name="body_name_bomb[]">' +
            '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
            ' <button type="button" class="bpf-del-btn" onclick="this.closest(\'.bpf-body-row\').remove()">×</button>' +
            '</div>' +
            '<div class="bpf-fr">' +
            '<span class="bpf-fl">ลักษณะบาดแผล</span>' +
            '<input type="text" class="bpf-inp" name="body_condition_bomb[]">' +
            '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
            '</div>';
        c.appendChild(div);
    };

    // ===== Evidence table rows =====
    window.bpfAddEvidenceRow = function() {
        var tbody = document.getElementById('bpf_evidence_tbody');
        if (!tbody) return;
        var rowIdx = tbody.querySelectorAll('tr').length;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" name="bomb_ev_label[]" style="width:35px;" value="' + (rowIdx + 1) + '"></td>' +
            '<td><input type="text" name="bomb_ev_item[]"></td>' +
            '<td><input type="text" name="bomb_ev_level_1_' + rowIdx + '" style="width:40px; text-align:center;" inputmode="decimal"></td>' +
            '<td><input type="text" name="bomb_ev_level_2_' + rowIdx + '" style="width:40px; text-align:center;" inputmode="decimal"></td>' +
            '<td><input type="text" name="bomb_ev_level_3_' + rowIdx + '" style="width:40px; text-align:center;" inputmode="decimal"></td>' +
            '<td><input type="text" name="bomb_ev_level_4_' + rowIdx + '" style="width:40px; text-align:center;" inputmode="decimal"></td>' +
            '<td><input type="text" name="bomb_ev_azimuth[]"></td>' +
            '<td><input type="text" name="bomb_ev_remark[]"></td>' +
            '<td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option><option value="explosive">วัตถุระเบิด (กก.กตว.)</option></select><input type="hidden" class="lab-unit-value" name="evidence_lab_unit_bomb[]" value=""></td>' +
            '<td><button type="button" class="bpf-del-btn" onclick="bpfDelRow(this)">×</button></td>';
        tbody.appendChild(tr);
        bpfBombApplyNumericAttributes(tr);
    };

    function bpfReindexCollectionRows() {
        var tbody = document.getElementById('bpf_collection_tbody');
        if (!tbody) return;
        Array.from(tbody.querySelectorAll('tr')).forEach(function(tr, index) {
            var firstCell = tr.querySelector('td');
            if (firstCell) firstCell.textContent = index + 1;
            ['measurement_package_plastic', 'measurement_package_paper', 'measurement_package_other', 'measurement_action_return', 'measurement_action_other'].forEach(function(prefix) {
                var check = tr.querySelector('input[name^="' + prefix + '_check["]');
                var text = tr.querySelector('input[name^="' + prefix + '_text["]');
                if (check) check.name = prefix + '_check[' + index + ']';
                if (text) text.name = prefix + '_text[' + index + ']';
            });
        });
    }

    // ===== Delete row =====
    window.bpfDelRow = function(btn) {
        var tr = btn.closest('tr');
        if (!tr) return;
        var isCollection = !!tr.closest('#bpf_collection_tbody');
        tr.remove();
        if (isCollection) {
            bpfReindexCollectionRows();
        }
    };

    // ===== Collection table rows (บันทึกการตรวจเก็บวัตถุพยาน — เหมือน life form) =====
    window.bpfAddCollectionRow = function() {
        var tbody = document.getElementById('bpf_collection_tbody');
        if (!tbody) return;
        var rowIdx = tbody.querySelectorAll('tr').length;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td style="text-align:center;">' + (rowIdx + 1) + '</td>' +
            '<td><input type="text" name="measurement_item_bomb[]"></td>' +
            '<td><input type="text" name="measurement_quantity_bomb[]" style="width:30px; text-align:center;"></td>' +
            '<td><input type="text" name="measurement_area_bomb[]"></td>' +
            '<td><input type="text" name="measurement_label_number_bomb[]" style="width:30px; text-align:center;"></td>' +
            '<td><input type="checkbox" name="measurement_package_plastic_check[' + rowIdx + ']" value="1"><input type="hidden" name="measurement_package_plastic_text[' + rowIdx + ']" value=""></td>' +
            '<td><input type="checkbox" name="measurement_package_paper_check[' + rowIdx + ']" value="1"><input type="hidden" name="measurement_package_paper_text[' + rowIdx + ']" value=""></td>' +
            '<td><input type="checkbox" name="measurement_package_other_check[' + rowIdx + ']" value="1"><input type="hidden" name="measurement_package_other_text[' + rowIdx + ']" value=""></td>' +
            '<td><input type="checkbox" name="measurement_action_return_check[' + rowIdx + ']" value="1"><input type="hidden" name="measurement_action_return_text[' + rowIdx + ']" value=""></td>' +
            '<td><input type="checkbox" name="measurement_action_other_check[' + rowIdx + ']" value="1"><input type="hidden" name="measurement_action_other_text[' + rowIdx + ']" value=""></td>' +
            '<td><input type="text" name="measurement_remark_bomb[]"></td>' +
            '<td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option><option value="explosive">วัตถุระเบิด (กก.กตว.)</option></select><input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_bomb[]" value=""></td>' +
            '<td><button type="button" class="bpf-del-btn" onclick="bpfDelRow(this)">×</button></td>';
        tbody.appendChild(tr);
        bpfReindexCollectionRows();
        bpfBombApplyNumericAttributes(tr);
    };

    // ===== Photo source chooser (แนบรูป / ถ่ายรูป) =====
    window.bpfChoosePhotoSource = function() {
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
                document.getElementById('bpf_photo_input_gallery').click();
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                document.getElementById('bpf_photo_input_camera').click();
            }
        });
    };

    // ===== Photo handling (★ matching life form pattern with FileReader + dual src support) =====
    window.bpfPreviewPhotos = function(input) {
        if (!input.files || !input.files.length) return;
        bpfHandlePhotoFiles(input.files);
        input.value = '';
    };

    function bpfHandlePhotoFiles(files) {
        if (!files || !files.length) return;
        if (typeof attachmentStoreBomb === 'undefined') window.attachmentStoreBomb = [];
        Array.from(files).forEach(function(file) {
            if (!file.type.startsWith('image/')) return;
            var fileId = 'bpf_' + Date.now() + '_' + Math.random().toString(36).substr(2,5);
            var objectUrl = URL.createObjectURL(file);
            attachmentStoreBomb.push({ file: file, id: fileId, src: objectUrl, name: file.name });
        });
        bpfRenderPhotosFromStore();
        if (typeof renderBombAttachmentGrid === 'function') renderBombAttachmentGrid();
    }

    // ===== Drag & Drop + Click handlers for dropzone =====
    var bpfPhotoDZ = document.getElementById('bpf_photo_dropzone');
    if (bpfPhotoDZ) {
        bpfPhotoDZ.addEventListener('click', function() {
            bpfChoosePhotoSource();
        });
        bpfPhotoDZ.addEventListener('dragenter', function(e) { e.preventDefault(); this.classList.add('dragover'); });
        bpfPhotoDZ.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('dragover'); });
        bpfPhotoDZ.addEventListener('dragleave', function(e) { e.preventDefault(); this.classList.remove('dragover'); });
        bpfPhotoDZ.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                bpfHandlePhotoFiles(e.dataTransfer.files);
            }
        });
    }

    // ===== ปุ่มเพิ่มหน้า (manual) — ยังใช้ได้เพื่อเพิ่มหน้าว่าง =====
    window.bpfAddPhotoPage = function() {
        bpfRenderPhotosFromStore();
    };

    // ===== Update report number/year mirrors on ALL pages =====
    window.bpfBombUpdateReportMirrors = function() {
        var modalEl = document.getElementById('bombFormPdfModal');
        if (!modalEl) return;
        var docNo = document.getElementById('bpf_doc_no') ? document.getElementById('bpf_doc_no').value : '';
        var rptNo = document.getElementById('bpf_report_no') ? document.getElementById('bpf_report_no').value : '';
        var rptParts = (rptNo || '').split('/');
        var rptNum = rptParts[0] || docNo;
        var rptYear = (rptParts[1] || '').toString().slice(-2);
        var rptDisplay = document.getElementById('bpf_report_no_display');
        var yearDisplay = document.getElementById('bpf_report_year_display');
        if (rptDisplay) rptDisplay.textContent = rptNum;
        if (yearDisplay) yearDisplay.textContent = rptYear;
        modalEl.querySelectorAll('.bpf-rpt-no-mirror').forEach(function(el) { el.textContent = rptNum; });
        modalEl.querySelectorAll('.bpf-rpt-year-mirror').forEach(function(el) { el.textContent = rptYear; });
    };

    // ===== Update page numbers dynamically =====
    window.bpfBombUpdatePageNumbers = function() {
        var pages = document.querySelectorAll('#bombFormPdfModal .bpf-page');
        var total = pages.length;
        pages.forEach(function(page, idx) {
            var curSpan = page.querySelector('.bpf-cur-page');
            var totalSpan = page.querySelector('.bpf-total-page');
            if (curSpan) curSpan.textContent = idx + 1;
            if (totalSpan) totalSpan.textContent = total;
        });
    };
    bpfBombUpdatePageNumbers();

    // ===== Render photos จาก attachmentStoreBomb ลง grid (35 รูป/หน้า) =====
    var BPF_PHOTOS_PER_PAGE = 35;

    window.bpfRenderPhotosFromStore = function() {
        var photos = (typeof attachmentStoreBomb !== 'undefined') ? attachmentStoreBomb : [];
        var pagesNeeded = Math.max(1, Math.ceil(photos.length / BPF_PHOTOS_PER_PAGE));
        console.log('[BPF] bpfRenderPhotosFromStore called, photos:', photos.length, 'pagesNeeded:', pagesNeeded);

        // หน้าแรก → grid อยู่ใน #bpf_photo_grid_1
        var grid1 = document.getElementById('bpf_photo_grid_1');
        if (grid1) {
            var startIdx = 0;
            var endIdx = Math.min(BPF_PHOTOS_PER_PAGE, photos.length);
            grid1.innerHTML = '';
            for (var i = startIdx; i < endIdx; i++) {
                grid1.appendChild(bpfCreatePhotoCell(photos[i], i));
            }
        }

        // สร้าง/ลบ extra pages ตามจำนวนรูป
        var extraContainer = document.getElementById('bpf_extra_photo_pages');
        if (extraContainer) {
            extraContainer.innerHTML = '';
            for (var p = 2; p <= pagesNeeded; p++) {
                var startI = (p - 1) * BPF_PHOTOS_PER_PAGE;
                var endI = Math.min(p * BPF_PHOTOS_PER_PAGE, photos.length);
                extraContainer.appendChild(bpfCreatePhotoPageElement(p, photos, startI, endI));
            }
        }

        bpfUpdatePhotoAmountBomb();
        if (typeof bpfBombUpdatePageNumbers === 'function') bpfBombUpdatePageNumbers();
        if (typeof bpfBombUpdateReportMirrors === 'function') bpfBombUpdateReportMirrors();
    };

    function bpfCreatePhotoCell(item, idx) {
        var wrapper = document.createElement('div');
        wrapper.className = 'bpf-photo-cell-wrapper';
        var displayName = item.name || item.filename || item.caption || 'photo';
        var safeName = displayName.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
        var imgSrc = item.src || item.base64 || '';
        wrapper.innerHTML =
            '<div class="bpf-photo-cell" style="position:relative;">' +
                '<img src="' + imgSrc + '" alt="' + safeName + '" loading="lazy">' +
                '<button type="button" class="bpf-cell-delete" onclick="event.stopPropagation(); bpfRemovePhoto(\'' + item.id + '\')">&times;</button>' +
            '</div>' +
            '<div class="bpf-cell-filename" title="' + safeName + '">' + displayName + '</div>';
        return wrapper;
    }

    function bpfCreatePhotoPageElement(pageNum, photos, startIdx, endIdx) {
        var page = document.createElement('div');
        page.className = 'bpf-page bpf-photo-page';
        page.setAttribute('data-photo-page', pageNum);
        page.innerHTML =
            '<div class="bpf-header">' +
                '<div class="bpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>' +
                '<div class="bpf-header-center">' +
                    '<div class="bpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>' +
                    '<div class="bpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด</div>' +
                    '<div style="font-size:11px; font-weight:600;">บันทึกการถ่ายภาพ (ต่อ)</div>' +
                '</div>' +
                '<div class="bpf-header-right">' +
                    '<div class="bpf-doc-box">' +
                        '<div class="bpf-doc-line">เลขรับที่/เลขรายงาน <span class="bpf-rpt-no-mirror"></span> / 25<span class="bpf-rpt-year-mirror"></span></div>' +
                        '<div class="bpf-doc-line">หน้าที่ <span class="bpf-cur-page"></span> / <span class="bpf-total-page"></span></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div style="margin-bottom:8px;">' +
                '<div class="bpf-fr" style="flex-wrap:nowrap; margin-bottom:6px;">' +
                    '<span class="bpf-fl">รหัสภาพถ่ายที่</span>' +
                    '<input type="text" class="bpf-inp bpf-page-photo-start" style="min-width:40px;" readonly>' +
                    '<span class="bpf-fl">ถึง</span>' +
                    '<input type="text" class="bpf-inp bpf-page-photo-end" style="min-width:40px;" readonly>' +
                '</div>' +
                '<div style="font-size:10.5px; font-style:italic; margin-top:2px;">(ตามภาพถ่ายรวมที่แนบ)</div>' +
            '</div>' +
            '<div class="bpf-photo-grid" id="bpf_photo_grid_' + pageNum + '"></div>' +
            '<div style="margin-top:auto;">' +
                '<div class="bpf-footer">' +
                    '<div class="bpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>' +
                    '<div class="bpf-footer-right">F-CS-10 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>' +
                '</div>' +
            '</div>';

        // Populate grid
        var grid = page.querySelector('.bpf-photo-grid');
        for (var i = startIdx; i < endIdx; i++) {
            grid.appendChild(bpfCreatePhotoCell(photos[i], i));
        }
        return page;
    }

    // ===== ลบรูปจาก store (★ track BLOB file_id + disk filename สำหรับลบบน server — เหมือน life form) =====
    window.bpfRemovePhoto = function(fileId) {
        if (typeof attachmentStoreBomb === 'undefined') return;
        var item = attachmentStoreBomb.find(function(x) { return x.id === fileId; });
        if (item && item.existing && item.db_file_id) {
            // BLOB photo → track file_id สำหรับลบจาก DB
            if (typeof deletedExistingPhotosBomb !== 'undefined') {
                deletedExistingPhotosBomb.push({ file_id: item.db_file_id });
            }
        } else if (item && item.existing && !item.db_file_id && (item.disk_filename || item.filename)) {
            // Disk photo → track filename สำหรับลบจากดิสก์
            if (typeof deletedExistingPhotosBomb !== 'undefined') {
                deletedExistingPhotosBomb.push(item.disk_filename || item.filename);
            }
        }
        attachmentStoreBomb = attachmentStoreBomb.filter(function(x) { return x.id !== fileId; });
        bpfRenderPhotosFromStore();
        // อัปเดต standard form grid ด้วย (ถ้ามี)
        if (typeof renderBombAttachmentGrid === 'function') renderBombAttachmentGrid();
    };

    // ===== Canvas utilities (★ matching life form pattern with initBpfCanvas + bpfDrawInit guard) =====
    function bpfIsCanvasBlank(canvas) {
        var ctx = canvas.getContext('2d');
        var pixelData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        for (var i = 3; i < pixelData.length; i += 4) {
            if (pixelData[i] !== 0) return false;
        }
        return true;
    }

    window.bpfClearCanvas = function(canvasId) {

            var canvas = document.getElementById(canvasId);
            console.log(canvas,"<----------canvas");
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
                            // ล้าง canvas แล้ว re-init drawing handler
                            canvas.dataset.bpfDrawInit = '0';
                            var ctx = canvas.getContext('2d');
                            ctx.clearRect(0, 0, canvas.width, canvas.height);
                            var penColor = (canvasId === 'bpf_body_diagram_canvas') ? '#c00' : '#000';
                            initBpfCanvas(canvasId, penColor);
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

        // ★ ล้าง canvas ของฟอร์มมาตรฐานด้วย (ป้องกัน fallback เจอรูปเก่า)
        var stdCanvasMap = {
            'bpf_sig_receiver': 'sig-canvas-receiver-bomb',
            'bpf_sig_sender': 'sig-canvas-sender-bomb',
            'bpf_scene_sketch_canvas': 'scene_sketch_canvas_bomb',
            'bpf_body_diagram_canvas': 'body_diagram_canvas_bomb'
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
        if (canvasId === 'bpf_sig_receiver') {
            var inpR = document.getElementById('bpf_receiver_sig_data');
            if (inpR) inpR.value = '';
        }
        if (canvasId === 'bpf_sig_sender') {
            var inpS = document.getElementById('bpf_sender_sig_data');
            if (inpS) inpS.value = '';
        }
        if (canvasId === 'bpf_scene_sketch_canvas') {
            var inpSk = document.getElementById('bpf_scene_sketch_data');
            if (inpSk) inpSk.value = '';
        }
        if (canvasId === 'bpf_body_diagram_canvas') {
            var inpBd = document.getElementById('bpf_body_diagram_data');
            if (inpBd) inpBd.value = '';
            var inpBdStrokes = document.getElementById('bpf_body_diagram_strokes');
            if (inpBdStrokes) inpBdStrokes.value = '';
        }
    };

    // ===== Pen size control =====
    window.sketchSetPenSize = function(canvasId, size) {
        if (typeof window._sketchPenSize === 'undefined') window._sketchPenSize = {};
        window._sketchPenSize[canvasId] = parseInt(size) || 2;
    };

    // ===== Eraser toggle function =====
    window.sketchToggleEraser = function(canvasId) {
        if (typeof _sketchEraser === 'undefined') window._sketchEraser = {};
        _sketchEraser[canvasId] = !_sketchEraser[canvasId];
        
        // Update button style (try both ID conventions)
        var btn = document.getElementById(canvasId + '_eraser_btn')
               || document.getElementById(canvasId.replace('_canvas', '_eraser_btn'));
        if (btn) {
            if (_sketchEraser[canvasId]) {
                btn.style.backgroundColor = '#fbbf24';
                btn.style.color = '#000';
            } else {
                btn.style.backgroundColor = '';
                btn.style.color = '';
            }
        }
    };

    function initBpfCanvas(canvasId, penColor) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;
        if (canvas.dataset.bpfDrawInit === '1') return;
        canvas.dataset.bpfDrawInit = '1';
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
        var ctx = canvas.getContext('2d');
        var drawing = false;
        var defColor = penColor || '#000';
        if (typeof _sketchColor !== 'undefined') { _sketchOrigColor[canvasId] = defColor; _sketchColor[canvasId] = _sketchColor[canvasId] || defColor; }
        if (typeof _sketchPenSize !== 'undefined') { _sketchPenSize[canvasId] = _sketchPenSize[canvasId] || 2; }

        function getStroke() {
            var isEraser = (typeof _sketchEraser !== 'undefined' && _sketchEraser[canvasId]);
            var c = (typeof _sketchColor !== 'undefined' && _sketchColor[canvasId]) ? _sketchColor[canvasId] : defColor;
            var w = (typeof _sketchPenSize !== 'undefined' && _sketchPenSize[canvasId]) ? _sketchPenSize[canvasId] : 2;
            console.log('[BPF v2.2] Canvas:', canvasId, 'Eraser:', isEraser, 'Width:', w, 'Color:', c);
            if (isEraser) return { eraser: true, width: 20 };
            return { eraser: false, color: c, width: w };
        }

        function getPos(e) {
            var rect = canvas.getBoundingClientRect();
            var touch = e.touches ? e.touches[0] : e;
            return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
        }
        function start(e) { 
            e.preventDefault(); 
            drawing = true; 
            var p = getPos(e); 
            var s = getStroke();
            if (s.eraser) {
                ctx.globalCompositeOperation = 'destination-out';
            } else {
                ctx.globalCompositeOperation = 'source-over';
            }
            ctx.beginPath(); 
            ctx.moveTo(p.x, p.y); 
        }
        function move(e) { 
            if (!drawing) return; 
            e.preventDefault(); 
            var p = getPos(e); 
            var s = getStroke(); 
            ctx.lineTo(p.x, p.y); 
            if (s.eraser) {
                ctx.strokeStyle = 'rgba(0,0,0,1)';
            } else {
                ctx.strokeStyle = s.color;
            }
            ctx.lineWidth = s.width; 
            ctx.lineCap = 'round'; 
            ctx.stroke(); 
        }
        function end() { drawing = false; ctx.globalCompositeOperation = 'source-over'; }

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
        if (target.id !== 'bpf_receiver_name' && target.id !== 'bpf_sender_name') return;
        var idEmp = target.value;
        var posInputId = (target.id === 'bpf_receiver_name') ? 'bpf_receiver_pos' : 'bpf_sender_pos';
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

    // ===== Modal initialization (★ canvas init + mirror span population — matching life form) =====
    var modalEl = document.getElementById('bombFormPdfModal');
    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', function() {
            // ใช้ fallback drawing handlers เฉพาะกรณีที่ระบบ SignaturePad หลักไม่พร้อม
            // (scene_sketch ย้ายไป multi-page sketch engine แล้ว — ไม่ต้อง init ที่นี่)
            if (typeof signaturePads === 'undefined') {
                initBpfCanvas('bpf_sig_receiver', '#000');
                initBpfCanvas('bpf_sig_sender', '#000');
                initBpfCanvas('bpf_body_diagram_canvas', '#c00');
            }

            // ★ Auto-fill พฤติการณ์คดี from basic_info (rn_ReceiveNoti)
            var dstBehavior = document.querySelector('#bombFormPdfModal textarea[name="case_behavior"]');
            if (dstBehavior && !dstBehavior.value && window._basicInfoBomb) {
                dstBehavior.value = window._basicInfoBomb;
            }

            // populate mirror spans pages 2+
            if (typeof bpfBombUpdateReportMirrors === 'function') bpfBombUpdateReportMirrors();

            bpfToggleBloodSection();

            bpfBombUpdatePageNumbers();
            bpfBombApplyNumericAttributes(modalEl);
            bpfUpdatePhotoAmountBomb();
        });
    }

    function bpfBombGetNumericRule(input) {
        if (!input || !input.name) return null;
        var name = input.name;
        if ([
            'victim_age_bomb[]',
            'victim_age_bomb_diagram',
            'building_floor_count_indoor',
            'measurement_quantity_bomb[]',
            'measurement_label_number_bomb[]',
            'bomb_ev_label[]',
            'photo_id_start_bomb',
            'photo_id_end_bomb',
            'photo_amount_bomb'
        ].includes(name)) {
            return 'integer';
        }
        if (name === 'investigator_phone') {
            return 'phone';
        }
        if ([
            'detonate_wire_length',
            'fragment_rebar_size',
            'fragment_nail_size',
            'comp_battery_voltage'
        ].includes(name)) {
            return 'decimal';
        }
        return null;
    }

    function bpfBombSanitizeNumericInput(input) {
        var rule = bpfBombGetNumericRule(input);
        if (!rule) return;
        var value = input.value || '';
        if (rule === 'integer') {
            input.value = value.replace(/\D/g, '');
            return;
        }
        if (rule === 'phone') {
            var digits = value.replace(/\D/g, '').slice(0, 10);
            if (digits.length <= 3) {
                input.value = digits;
                return;
            }
            if (digits.length <= 6) {
                input.value = digits.slice(0, 3) + '-' + digits.slice(3);
                return;
            }
            input.value = digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6);
            return;
        }
        var sanitized = value.replace(/[^\d.]/g, '');
        var parts = sanitized.split('.');
        if (parts.length > 2) {
            sanitized = parts.shift() + '.' + parts.join('');
        }
        input.value = sanitized;
    }

    function bpfBombApplyNumericAttributes(root) {
        if (!root) return;
        var inputs = root.matches && root.matches('input') ? [root] : root.querySelectorAll('input');
        Array.from(inputs).forEach(function(input) {
            var rule = bpfBombGetNumericRule(input);
            if (!rule) return;
            input.setAttribute('autocomplete', 'off');
            if (rule === 'integer') {
                input.setAttribute('inputmode', 'numeric');
                input.setAttribute('pattern', '[0-9]*');
            } else if (rule === 'phone') {
                input.setAttribute('inputmode', 'tel');
                input.setAttribute('pattern', '^\\d{3}-\\d{3}-\\d{4}$');
                input.setAttribute('maxlength', '12');
                input.setAttribute('placeholder', '081-234-5678');
            } else {
                input.setAttribute('inputmode', 'decimal');
                input.setAttribute('pattern', '^\\d*\\.?\\d*$');
            }
            bpfBombSanitizeNumericInput(input);
        });
    }

    function bpfUpdatePhotoAmountBomb() {
        var form = document.getElementById('bombFormPdf');
        if (!form) return;

        var photos = (typeof attachmentStoreBomb !== 'undefined' && Array.isArray(attachmentStoreBomb))
            ? attachmentStoreBomb
            : [];
        var totalPhotos = photos.length;

        var startInput = form.querySelector('input[name="photo_id_start_bomb"]');
        var endInput = form.querySelector('input[name="photo_id_end_bomb"]');
        var amountInput = form.querySelector('input[name="photo_amount_bomb"]');

        if (startInput && endInput && amountInput) {
            if (totalPhotos > 0) {
                var lastIdxPage1 = Math.min(BPF_PHOTOS_PER_PAGE, totalPhotos) - 1;
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
        var extraPages = document.querySelectorAll('#bpf_extra_photo_pages .bpf-photo-page');
        extraPages.forEach(function(page, pIdx) {
            var pageNum = pIdx + 2;
            var startI = (pageNum - 1) * BPF_PHOTOS_PER_PAGE;
            var endI = Math.min(pageNum * BPF_PHOTOS_PER_PAGE, totalPhotos) - 1;
            var ps = page.querySelector('.bpf-page-photo-start');
            var pe = page.querySelector('.bpf-page-photo-end');
            if (ps && photos[startI]) ps.value = photos[startI].name || 'photo';
            if (pe && photos[endI]) pe.value = photos[endI].name || 'photo';
        });
    }

    function bpfValidateBombPdfForm() {
        var outdoor = document.getElementById('bpf_chk_outdoor');
        var indoor = document.getElementById('bpf_chk_indoor');
        if (outdoor && indoor && !outdoor.checked && !indoor.checked) {
            return {
                valid: false,
                message: 'กรุณาเลือกประเภทสถานที่อย่างน้อย 1 รายการ (ในอาคาร หรือ นอกอาคาร)',
                target: outdoor
            };
        }
        if (outdoor && indoor && outdoor.checked && indoor.checked) {
            return {
                valid: false,
                message: 'กรุณาเลือกประเภทสถานที่เพียง 1 รายการ',
                target: outdoor
            };
        }

        return { valid: true };
    }

    if (!window._bpfBombPdfValidationBound) {
        document.addEventListener('input', function(event) {
            if (!event.target || !event.target.closest('#bombFormPdfModal')) return;
            if (!bpfBombGetNumericRule(event.target)) return;
            bpfBombSanitizeNumericInput(event.target);
            if (event.target.name === 'photo_id_start_bomb' || event.target.name === 'photo_id_end_bomb') {
                bpfUpdatePhotoAmountBomb();
            }
        });

        document.addEventListener('change', function(event) {
            if (!event.target || !event.target.closest('#bombFormPdfModal')) return;
            if (event.target.id === 'bpf_chk_outdoor' && event.target.checked) {
                var indoor = document.getElementById('bpf_chk_indoor');
                if (indoor) indoor.checked = false;
            }
            if (event.target.id === 'bpf_chk_indoor' && event.target.checked) {
                var outdoor = document.getElementById('bpf_chk_outdoor');
                if (outdoor) outdoor.checked = false;
            }
        });

        window._bpfBombPdfValidationBound = true;
    }

    // ===== Sync all matching fields from PDF → standard form =====
    window.syncBombFormData = function(pdfFormId, stdFormId) {
        var pdfForm = document.getElementById(pdfFormId);
        var stdForm = document.getElementById(stdFormId);
        if (!pdfForm || !stdForm) return;

        // Sync all input/select/textarea with matching names
        var pdfFields = pdfForm.querySelectorAll('input, select, textarea');
        pdfFields.forEach(function(src) {
            var name = src.getAttribute('name');
            if (!name) return;

            // Skip array fields (handled by dedicated sync functions)
            if (name.endsWith('[]')) return;
            // Skip checkbox/radio arrays with index
            if (/\[.*\]/.test(name)) return;

            var dst = stdForm.querySelector('[name="' + name.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
            if (!dst) return;

            if (src.type === 'checkbox' || src.type === 'radio') {
                dst.checked = src.checked;
            } else {
                dst.value = src.value || '';
            }
        });
    };

    // ===== Sync measurement rows from PDF → standard form =====
    window._syncBombMeasurementToStd = function() {
        var pdfTbody = document.getElementById('bpf_collection_tbody');
        var stdContainer = document.getElementById('measurement_container_bomb');
        if (!pdfTbody || !stdContainer) return;

        // Clear existing measurement cards in standard form
        stdContainer.innerHTML = '';

        // Get all rows from PDF table
        var rows = pdfTbody.querySelectorAll('tr');
        rows.forEach(function(row, rowIdx) {
            // Add a new card to standard form
            if (typeof addMeasurementCardBomb === 'function') {
                addMeasurementCardBomb();
            } else {
                return;
            }

            // Get the newly created card (last one)
            var cards = stdContainer.querySelectorAll('.measurement-card-bomb');
            var card = cards[cards.length - 1];
            if (!card) return;

            // Helper to copy value from PDF row to standard card
            function copyVal(namePrefix, cardSelector) {
                var src = row.querySelector('[name="' + namePrefix + '"]');
                var dst = card.querySelector('[name="' + namePrefix + '"]');
                if (src && dst) {
                    dst.value = src.value || '';
                }
            }

            // Copy basic fields
            copyVal('measurement_item_bomb[]');
            copyVal('measurement_quantity_bomb[]');
            copyVal('measurement_area_bomb[]');
            copyVal('measurement_label_number_bomb[]');
            copyVal('measurement_remark_bomb[]');

            // Copy forensic unit (รองรับเลือกหลายกลุ่มงาน)
            var srcForensic = row.querySelector('[name="measurement_forensic_unit_bomb[]"]');
            var dstForensic = card.querySelector('[name="measurement_forensic_unit_bomb[]"]');
            if (srcForensic && dstForensic) {
                window.setLabUnits(dstForensic, window.getLabUnitsString(srcForensic));
            }

            // Copy packaging checkboxes and text
            var pkgTypes = ['plastic', 'paper', 'other'];
            pkgTypes.forEach(function(type) {
                var srcCheck = row.querySelector('[name="measurement_package_' + type + '_check[' + rowIdx + ']"]');
                var dstCheck = card.querySelector('[name^="measurement_package_' + type + '_check"]');
                if (srcCheck && dstCheck) {
                    dstCheck.checked = srcCheck.checked;
                    // Trigger change to enable/disable text input
                    if (typeof togglePackageInput === 'function') {
                        togglePackageInput(dstCheck, type + '_[' + rowIdx + ']');
                    }
                }

                var srcText = row.querySelector('[name="measurement_package_' + type + '_text[' + rowIdx + ']"]');
                var dstText = card.querySelector('[name^="measurement_package_' + type + '_text"]');
                if (srcText && dstText) {
                    dstText.value = srcText.value || '';
                    dstText.disabled = !(srcCheck && srcCheck.checked);
                }
            });

            // Copy action checkboxes and text
            var actTypes = ['return', 'other'];
            actTypes.forEach(function(type) {
                var srcCheck = row.querySelector('[name="measurement_action_' + type + '_check[' + rowIdx + ']"]');
                var dstCheck = card.querySelector('[name^="measurement_action_' + type + '_check"]');
                if (srcCheck && dstCheck) {
                    dstCheck.checked = srcCheck.checked;
                }

                var srcText = row.querySelector('[name="measurement_action_' + type + '_text[' + rowIdx + ']"]');
                var dstText = card.querySelector('[name^="measurement_action_' + type + '_text"]');
                if (srcText && dstText) {
                    dstText.value = srcText.value || '';
                }
            });
        });

        // Re-index after sync
        if (typeof reIndexMeasurementCardsBomb === 'function') {
            reIndexMeasurementCardsBomb();
        }
    };

    // ===== Save handler: sync PDF → standard form → use prepareDataForSubmissionBomb (★ matching life form) =====
    window.bpfSaveViaStandardForm = async function() {
        var pdfValidation = bpfValidateBombPdfForm();
        if (!pdfValidation.valid) {
            Swal.fire({
                icon: 'warning',
                title: 'ข้อมูลไม่ถูกต้อง',
                text: pdfValidation.message,
                confirmButtonText: 'ตกลง'
            }).then(function() {
                if (pdfValidation.target && typeof pdfValidation.target.focus === 'function') {
                    pdfValidation.target.focus({ preventScroll: false });
                }
            });
            return;
        }

        // เก็บ stroke data ของ body diagram จาก PDF canvas เพื่อให้โหลดกลับได้คมเหมือนเดิม
        var pdfBodyPad = (typeof signaturePads !== 'undefined') ? signaturePads['bpf_body_diagram_canvas'] : null;
        var bodyStrokeInput = document.getElementById('bpf_body_diagram_strokes');
        if (bodyStrokeInput) {
            if (pdfBodyPad && !pdfBodyPad.isEmpty()) {
                bodyStrokeInput.value = JSON.stringify(pdfBodyPad.toData());
            } else {
                bodyStrokeInput.value = '';
            }
        }

        // ★ ดึง base64 จาก canvas ของ PDF form ใส่ hidden input ก่อน sync
        var pdfCanvasMap = {
            'bpf_sig_receiver': 'bpf_receiver_sig_data',
            'bpf_sig_sender': 'bpf_sender_sig_data',
            'bpf_scene_sketch_canvas': 'bpf_scene_sketch_data',
            'bpf_body_diagram_canvas': 'bpf_body_diagram_data'
        };
        Object.keys(pdfCanvasMap).forEach(function(canvasId) {
            var canvas = document.getElementById(canvasId);
            var hiddenInput = document.getElementById(pdfCanvasMap[canvasId]);
            if (canvas && hiddenInput) {
                try {
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
        if (typeof syncBombFormData === 'function') {
            syncBombFormData('bombFormPdf', 'incidentCheckListFormBomb');
        }

        // ★ sync dynamic rows จาก PDF → ฟอร์มมาตรฐาน
        if (typeof _syncBombInspectorToStd === 'function') {
            _syncBombInspectorToStd();
        }
        if (typeof _syncBombVictimToStd === 'function') {
            _syncBombVictimToStd();
        }
        if (typeof _syncBombBodyToStd === 'function') {
            _syncBombBodyToStd();
        }
        if (typeof _syncBombEvidenceToStd === 'function') {
            _syncBombEvidenceToStd();
        }
        if (typeof _syncBombMeasurementToStd === 'function') {
            _syncBombMeasurementToStd();
        }

        // sync อีกครั้งหลังเพิ่ม dynamic rows แล้ว
        if (typeof syncBombFormData === 'function') {
            syncBombFormData('bombFormPdf', 'incidentCheckListFormBomb');
        }

        // ★ บังคับ copy ฟิลด์สำคัญที่มักหลุดตอน save จากฟอร์มเสมือน
        (function forceSyncBombRecorderFields() {
            var pdfForm = document.getElementById('bombFormPdf');
            var stdForm = document.getElementById('incidentCheckListFormBomb');
            if (!pdfForm || !stdForm) return;

            function copyField(name) {
                var src = pdfForm.querySelector('[name="' + name + '"]');
                var dst = stdForm.querySelector('[name="' + name + '"]');
                if (!src || !dst) return;
                dst.value = src.value || '';
                if (dst.tagName === 'SELECT') {
                    dst.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            copyField('measurement_recorder_bomb');
            copyField('measurement_datetime_bomb');
            copyField('collector_name_bomb');
            copyField('collection_datetime_bomb');
            copyField('sketch_recorder_bomb');
            copyField('sketch_datetime_bomb');
            copyField('photographer_name_bomb');
            copyField('photographer_datetime_bomb');
            copyField('reference_point_1_bomb');
            copyField('reference_point_2_bomb');
            copyField('reference_point_3_bomb');
            copyField('reference_point_4_bomb');
            copyField('collector_name_bomb');
            copyField('collection_datetime_bomb');

            // PDF แยกวัน/เวลา แต่ฟอร์มหลักใช้ datetime-local เดียว
            var mDate = pdfForm.querySelector('[name="measurement_inspection_date_bomb"]');
            var mTime = pdfForm.querySelector('[name="measurement_inspection_time_bomb"]');
            var mDst = stdForm.querySelector('[name="measurement_inspection_date_bomb"]');
            if (mDst && mDate) {
                var datePart = (mDate.value || '').trim();
                var timePart = (mTime && mTime.value) ? mTime.value : '00:00';
                mDst.value = datePart ? (datePart + 'T' + timePart) : '';
            }

            // PDF หน้าบันทึกภาพใช้ฟิลด์เฉพาะ photo_inspect_*, แต่ฟอร์มหลักบันทึกที่ inspect_date/inspect_time
            var pDate = pdfForm.querySelector('[name="photo_inspect_date_bomb"]');
            var pTime = pdfForm.querySelector('[name="photo_inspect_time_bomb"]');
            var stdInspectDate = stdForm.querySelector('[name="inspect_date"]');
            var stdInspectTime = stdForm.querySelector('[name="inspect_time"]');
            if (stdInspectDate && pDate) stdInspectDate.value = (pDate.value || '').trim();
            if (stdInspectTime && pTime) stdInspectTime.value = (pTime.value || '').trim();
        })();

        // sync hidden fields
        var docNo = document.getElementById('bpf_doc_no') ? document.getElementById('bpf_doc_no').value : '';
        var rptNo = document.getElementById('bpf_report_no') ? document.getElementById('bpf_report_no').value : '';
        var notiId = document.getElementById('bpf_receiveNoti_id') ? document.getElementById('bpf_receiveNoti_id').value : '';
        if (document.getElementById('doc_no_bomb')) document.getElementById('doc_no_bomb').value = docNo;
        if (document.getElementById('report_no_bomb')) document.getElementById('report_no_bomb').value = rptNo;
        if (document.getElementById('receiveNoti_id_bomb')) document.getElementById('receiveNoti_id_bomb').value = notiId;

        // ★ Collect multi-page sketch data ก่อน submit
        if (typeof bpfCollectSketchPagesData === 'function') {
            bpfCollectSketchPagesData();
        }

        // เรียกฟังก์ชัน submit ของฟอร์มมาตรฐาน
        if (typeof prepareDataForSubmissionBomb === 'function') {
            window._savingFromBombPdfForm = true; // ★ flag ให้ prepareDataForSubmissionBomb เช็ค PDF canvas ก่อน
            await prepareDataForSubmissionBomb();
        }
    };

    // ===== Switch to standard form =====
    // ฟังก์ชันหลักจะถูกกำหนดใน incidentChecklist.php (มี data sync)
    // ถ้ายังไม่ถูกกำหนด ให้ fallback แบบง่าย
    if (typeof window.switchToBombStandardForm !== 'function') {
        window.switchToBombStandardForm = function() {
            var pdfModal = bootstrap.Modal.getInstance(document.getElementById('bombFormPdfModal'));
            if (pdfModal) pdfModal.hide();

            setTimeout(function() {
                var stdModal = new bootstrap.Modal(document.getElementById('addCheckListModalBomb'));
                stdModal.show();
            }, 400);
        };
    }

    // ===== Download Checklist PDF =====
    window.bpfDownloadChecklistPdf = function() {
        var incidentId = document.getElementById('bpf_incident_id')?.value || 
                         document.getElementById('incident_id_bomb')?.value || '';
        if (!incidentId) {
            Swal.fire({ icon: 'warning', title: 'ไม่พบ incident_id', text: 'กรุณาบันทึกข้อมูลก่อนดาวน์โหลด' });
            return;
        }
        window.open('/csims/api/incidentCheckList/gen_pdf_bomb_html.php?incident_id=' + incidentId, '_blank');
    };

    // ==========================================================================
    // ===== MULTI-PAGE SKETCH ENGINE (Image / Draw / Full-page per sheet) =====
    // ==========================================================================
    window._bpfSketchPages = [];  // [{id, canvasId, bgImage, bgImageData, _bgImgEl}]
    var _sketchPageCounter = 1;   // Page 1 is the static HTML page
    var SKETCH_W = 1120, SKETCH_H = 660;

    // ----- Register the static first page -----
    window._bpfSketchPages.push({
        id: 'bpf_sketch_page_1',
        canvasId: 'bpf_sketch_page_1_canvas',
        bgImage: null, bgImageData: null, _bgImgEl: null
    });

    // ----- Initialize drawing on a canvas (undo + กันฝ่ามือ อยู่ใน sketch-tools.js) -----
    function _initSketchDraw(canvasId) {
        if (typeof window.initFreehandCanvas === 'function') {
            window.initFreehandCanvas(canvasId);
        }
    }

    // ----- Redraw from strokes (for undo) -----
    function _redrawFromStrokes(canvasId) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var strokes = (window._bpfSketchStrokes && window._bpfSketchStrokes[canvasId]) || [];
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        strokes.forEach(function(s) {
            if (s.points.length < 1) return;
            ctx.beginPath(); ctx.moveTo(s.points[0].x, s.points[0].y);
            ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.lineWidth = s.width;
            ctx.globalCompositeOperation = s.eraser ? 'destination-out' : 'source-over';
            if (!s.eraser) ctx.strokeStyle = s.color;
            for (var i = 1; i < s.points.length; i++) ctx.lineTo(s.points[i].x, s.points[i].y);
            ctx.stroke();
        });
        ctx.globalCompositeOperation = 'source-over';
    }

    function _findPage(pid) {
        return window._bpfSketchPages.find(function(p) { return p.id === pid; });
    }

    // ----- Public: Add a whole new sketch page (full bpf-page) -----
    window.bpfSketchAddPage = function() {
        _sketchPageCounter++;
        var n = _sketchPageCounter;
        var pid = 'bpf_sketch_page_' + n;
        var canvasId = pid + '_canvas';

        var pageData = { id: pid, canvasId: canvasId, bgImage: null, bgImageData: null, _bgImgEl: null };

        // Build full page HTML
        var pageDiv = document.createElement('div');
        pageDiv.className = 'bpf-page bpf-sketch-page';
        pageDiv.id = pid;
        pageDiv.setAttribute('data-sketch-page', n);

        pageDiv.innerHTML =
            '<div class="bpf-header">' +
                '<div class="bpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>' +
                '<div class="bpf-header-center">' +
                    '<div class="bpf-title-main">แบบตรวจสอบการปฏิบัติงาน (Check list)</div>' +
                    '<div class="bpf-title-sub">การตรวจสถานที่เกิดเหตุคดีเกี่ยวกับระเบิด</div>' +
                '</div>' +
                '<div class="bpf-header-right">' +
                    '<div class="bpf-doc-box">' +
                        '<div class="bpf-doc-line">เลขรับที่/เลขรายงาน <span class="bpf-rpt-no-mirror"></span> / 25<span class="bpf-rpt-year-mirror"></span></div>' +
                        '<div class="bpf-doc-line">หน้าที่ <span class="bpf-cur-page"></span> / <span class="bpf-total-page"></span></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +

            '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">' +
                '<div></div>' +
                '<div style="font-size:13px; font-weight:600;">แผนผังสังเขป (ต่อ)</div>' +
                '<div style="display:flex; gap:4px; align-items:center;">' +
                    '<label class="bpf-add-btn" style="padding:2px 8px; cursor:pointer; margin:0;" title="แนบรูปภาพพื้นหลัง">' +
                        '<i class="fas fa-image me-1"></i> แนบรูป' +
                        '<input type="file" accept="image/*" style="display:none;" onchange="bpfSketchAttachImage(\'' + pid + '\',this)">' +
                    '</label>' +
                    '<button type="button" class="bpf-add-btn" style="padding:2px 8px;" onclick="bpfSketchRemoveBg(\'' + pid + '\')" title="ลบรูปพื้นหลัง"><i class="fas fa-times"></i> ลบรูป</button>' +
                    '<button type="button" class="bpf-add-btn" style="padding:2px 8px; border-color:#dc3545; color:#dc3545;" onclick="bpfSketchRemovePage(\'' + pid + '\')" title="ลบหน้านี้"><i class="fas fa-trash-alt"></i> ลบหน้า</button>' +
                '</div>' +
            '</div>' +

            '<div class="bpf-sketch-viewport" id="' + pid + '_viewport">' +
                '<div class="bpf-sketch-canvas-wrap" id="' + pid + '_wrap" style="aspect-ratio:' + SKETCH_W + '/' + SKETCH_H + ';">' +
                    '<canvas id="' + canvasId + '" width="' + SKETCH_W + '" height="' + SKETCH_H + '"></canvas>' +
                '</div>' +
                '<div class="bpf-not-to-scale">* NOT TO SCALE</div>' +
            '</div>' +

            '<div style="text-align:right; margin-top:4px; display:flex; justify-content:flex-end; gap:6px; align-items:center;">' +
                '<button type="button" class="bpf-add-btn" onclick="sketchUndo(\'' + canvasId + '\')" style="padding:2px 8px;"><i class="fas fa-undo"></i> ย้อนกลับ</button>' +
                '<button type="button" class="bpf-add-btn" id="' + canvasId + '_eraser_btn" onclick="sketchToggleEraser(\'' + canvasId + '\')" style="padding:2px 8px;" title="ยางลบ"><i class="fas fa-eraser"></i></button>' +
                '<div style="display:flex;align-items:center;gap:4px;">' +
                    '<i class="fas fa-pen" style="font-size:10px;color:#666;"></i>' +
                    '<input type="range" min="1" max="20" value="2" oninput="sketchSetPenSize(\'' + canvasId + '\',this.value);this.nextElementSibling.textContent=this.value+\'px\'" style="width:80px;height:4px;cursor:pointer;" title="ขนาดปากกา">' +
                    '<span style="font-size:11px;color:#666;min-width:35px;">2px</span>' +
                '</div>' +
                '<label style="margin:0;cursor:pointer;" title="เลือกสี"><input type="color" value="#000000" onchange="sketchSetColor(\'' + canvasId + '\',this.value)" style="width:26px;height:22px;border:1px solid #aaa;border-radius:3px;padding:0;cursor:pointer;"></label>' +
                '<button type="button" class="bpf-add-btn" onclick="bpfSketchClearPage(\'' + pid + '\')">ล้างกระดาน</button>' +
            '</div>' +

            '<div style="margin-bottom:8px; margin-top:6px;">' +
                '<div class="bpf-fr"><span class="bpf-fl">หมายเหตุ</span></div>' +
                '<textarea class="bpf-ta" name="sketch_remark_bomb_' + n + '" rows="6"></textarea>' +
            '</div>' +

            '<div class="bpf-footer" style="margin-top:auto;">' +
                '<div class="bpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>' +
                '<div class="bpf-footer-right">F-CS-10 แก้ไขครั้งที่ 2<br>แก้ไขวันที่ 2 ก.ย. 63<br>เริ่มใช้ 1 ต.ค. 63</div>' +
            '</div>';

        // Insert after the last sketch page (before PAGE 6 evidence table)
        var allSketchPages = document.querySelectorAll('#bombFormPdfModal .bpf-sketch-page');
        var lastSketchPage = allSketchPages[allSketchPages.length - 1];
        if (lastSketchPage && lastSketchPage.nextElementSibling) {
            lastSketchPage.parentNode.insertBefore(pageDiv, lastSketchPage.nextElementSibling);
        } else {
            // Fallback: append to bpf-body
            var body = document.querySelector('#bombFormPdfModal .bpf-body');
            if (body) body.appendChild(pageDiv);
        }

        window._bpfSketchPages.push(pageData);
        _initSketchDraw(canvasId);

        // Update page numbers & mirrors
        if (typeof bpfBombUpdatePageNumbers === 'function') bpfBombUpdatePageNumbers();
        if (typeof bpfBombUpdateReportMirrors === 'function') bpfBombUpdateReportMirrors();

        // Scroll to new page
        pageDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    // ----- Public: Remove sketch page (only dynamic ones) -----
    window.bpfSketchRemovePage = function(pid) {
        if (pid === 'bpf_sketch_page_1') return; // ห้ามลบหน้าแรก
        if (!confirm('ลบหน้าแผนผังนี้?')) return;
        var el = document.getElementById(pid);
        if (el) el.remove();
        window._bpfSketchPages = window._bpfSketchPages.filter(function(p) { return p.id !== pid; });
        if (typeof bpfBombUpdatePageNumbers === 'function') bpfBombUpdatePageNumbers();
    };

    // ----- Public: Attach background image -----
    window.bpfSketchAttachImage = function(pid, input) {
        if (!input.files || !input.files[0]) return;
        var file = input.files[0];
        var pageData = _findPage(pid);
        if (!pageData) return;

        var reader = new FileReader();
        reader.onload = function(ev) {
            var img = new Image();
            img.onload = function() {
                pageData.bgImage = file.name;
                pageData.bgImageData = ev.target.result;
                pageData._bgImgEl = img;

                // Show bg image under canvas
                var wrap = document.getElementById(pid + '_wrap');
                if (wrap) {
                    var existing = wrap.querySelector('.bpf-sketch-bg-img');
                    if (existing) existing.remove();
                    var bgImg = document.createElement('img');
                    bgImg.className = 'bpf-sketch-bg-img';
                    bgImg.src = ev.target.result;
                    wrap.insertBefore(bgImg, wrap.firstChild);
                    var ph = wrap.querySelector('.bpf-sketch-placeholder');
                    if (ph) ph.style.display = 'none';
                }
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
        input.value = '';
    };

    // ----- Public: Remove background image -----
    window.bpfSketchRemoveBg = function(pid) {
        var pageData = _findPage(pid);
        if (!pageData || !pageData.bgImage) return;
        pageData.bgImage = null; pageData.bgImageData = null; pageData._bgImgEl = null;
        var wrap = document.getElementById(pid + '_wrap');
        if (wrap) {
            var existing = wrap.querySelector('.bpf-sketch-bg-img');
            if (existing) existing.remove();
            var ph = wrap.querySelector('.bpf-sketch-placeholder');
            if (ph) ph.style.display = '';
        }
    };

    // ----- Public: Clear page canvas -----
    window.bpfSketchClearPage = function(pid) {
        if (!confirm('ล้างภาพวาดในหน้านี้?')) return;
        var pageData = _findPage(pid);
        if (!pageData) return;
        var canvas = document.getElementById(pageData.canvasId);
        if (canvas) {
            var ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
        if (window._bpfSketchStrokes) window._bpfSketchStrokes[pageData.canvasId] = [];
        if (typeof window.sketchResetHistory === 'function') window.sketchResetHistory(pageData.canvasId);
        window._sketchEraser[pageData.canvasId] = false;
        _updateEraserBtn(pageData.canvasId, false);
    };

    // ----- Collect all sketch pages data for saving -----
    window.bpfCollectSketchPagesData = function() {
        var pagesData = [];
        window._bpfSketchPages.forEach(function(p) {
            var bg = p._bgImgEl || null;
            var dataUrl = (typeof window.exportSketchDataUrl === 'function')
                ? window.exportSketchDataUrl(p.canvasId, bg)
                : '';
            if (!dataUrl) {
                var canvas = document.getElementById(p.canvasId);
                if (canvas) {
                    try {
                        var merged = document.createElement('canvas');
                        merged.width = canvas.width; merged.height = canvas.height;
                        var mCtx = merged.getContext('2d');
                        mCtx.fillStyle = '#fff';
                        mCtx.fillRect(0, 0, merged.width, merged.height);
                        if (bg) mCtx.drawImage(bg, 0, 0, canvas.width, canvas.height);
                        mCtx.drawImage(canvas, 0, 0);
                        dataUrl = merged.toDataURL('image/png');
                    } catch (e) { dataUrl = ''; }
                }
            }
            pagesData.push({
                id: p.id, dataUrl: dataUrl,
                bgImageData: p.bgImageData || null,
                bgImageName: p.bgImage || null
            });
        });
        var inp = document.getElementById('bpf_scene_sketch_pages_data');
        if (inp) inp.value = JSON.stringify(pagesData);
        var legacyInp = document.getElementById('bpf_scene_sketch_data');
        if (legacyInp && pagesData.length > 0) legacyInp.value = pagesData[0].dataUrl || '';
        var stdInp = document.getElementById('scene_sketch_data_bomb');
        if (stdInp && pagesData.length > 0) stdInp.value = pagesData[0].dataUrl || '';
        return pagesData;
    };

    // ----- Init first page canvas on modal open -----
    var _sketchInitDone = false;
    var sketchModalEl = document.getElementById('bombFormPdfModal');
    if (sketchModalEl) {
        sketchModalEl.addEventListener('shown.bs.modal', function() {
            if (!_sketchInitDone) {
                _sketchInitDone = true;
                _initSketchDraw('bpf_sketch_page_1_canvas');
            }
        });
    }

})();
</script>
