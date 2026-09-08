<?php
/**
 * Modal: ฟอร์ม PDF ตรวจเก็บวัตถุพยานที่เกิดเหตุ (แบบเสมือนจริง)
 * complaints_type = '07'
 * Prefix: sevpf_ (Scene Evidence PDF Form)
 * UI pattern: อิงตาม modal_fire_pdf_form.php
 */

$sevpfPoliceStationOptions = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qrySevpfPS = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stmtSevpfPS = $pdo->query($qrySevpfPS);
    while ($rowPS = $stmtSevpfPS->fetch(PDO::FETCH_ASSOC)) {
        $sevpfPoliceStationOptions .= '<option value="' . htmlspecialchars($rowPS['station_name']) . '">' . htmlspecialchars($rowPS['station_name']) . '</option>';
    }
}

// ดึงรายชื่อผู้ตรวจ/ผู้ลงนาม
$sevpfInspectorOptions = '<option value="" selected disabled>-- เลือก --</option>';
if (isset($pdo)) {
    $qrySevpfInsp = "SELECT t1.user_id,
                            CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname,
                            IFNULL(t3.position_name, '-') AS position_name
                     FROM user_profile t1
                     LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                     LEFT JOIN user_position t3 ON t1.position_id = t3.position_id
                     ORDER BY t1.user_id DESC";
    $stmtSevpfInsp = $pdo->query($qrySevpfInsp);
    while ($rowInsp = $stmtSevpfInsp->fetch(PDO::FETCH_ASSOC)) {
        $sevpfInspectorOptions .= '<option value="' . $rowInsp['user_id'] . '" data-position="' . htmlspecialchars($rowInsp['position_name']) . '">' . htmlspecialchars($rowInsp['fullname']) . '</option>';
    }
}

// Photographer options (value = fullname)
$sevpfPhotographerOptions = '<option value="" selected disabled>-- เลือก --</option>';
if (isset($pdo)) {
    $qrySevpfPhotog = "SELECT t1.user_id,
                            CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname
                     FROM user_profile t1
                     LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                     ORDER BY t1.user_id DESC";
    $stmtSevpfPhotog = $pdo->query($qrySevpfPhotog);
    while ($rowPhotog = $stmtSevpfPhotog->fetch(PDO::FETCH_ASSOC)) {
        $sevpfPhotographerOptions .= '<option value="' . htmlspecialchars($rowPhotog['fullname']) . '">' . htmlspecialchars($rowPhotog['fullname']) . '</option>';
    }
}

$sevpfTodayDate = date('Y-m-d');
$sevpfTodayTime = date('H:i');
?>

<!-- ===== SCOPED CSS (อิงจาก fpf- แต่เปลี่ยน prefix เป็น sevpf-) ===== -->
<style>
#sceneEvidenceFormPdfModal .sevpf-rpt-no-mirror,
#sceneEvidenceFormPdfModal .sevpf-rpt-year-mirror {
    display: inline-block;
    border-bottom: 1px dotted #888;
    text-align: center;
}
#sceneEvidenceFormPdfModal .sevpf-rpt-no-mirror { min-width: 60px; }
#sceneEvidenceFormPdfModal .sevpf-rpt-year-mirror { min-width: 30px; }

#sceneEvidenceFormPdfModal .sevpf-body {
    background: #bbb;
    padding: 10px 0;
}

#sceneEvidenceFormPdfModal .sevpf-page {
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
#sceneEvidenceFormPdfModal .sevpf-header {
    position: relative;
    margin-bottom: 6px;
    height: 70px;
}
#sceneEvidenceFormPdfModal .sevpf-header-logo {
    position: absolute; left: 0; top: -5px; width: 70px; height: 70px;
}
#sceneEvidenceFormPdfModal .sevpf-header-logo img {
    width: 70px; height: 70px; object-fit: contain;
}
#sceneEvidenceFormPdfModal .sevpf-header-center {
    position: absolute; left: 80px; right: 180px; top: 8px; text-align: center;
}
#sceneEvidenceFormPdfModal .sevpf-header-center .sevpf-title-main {
    font-size: 14px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 5px;
}
#sceneEvidenceFormPdfModal .sevpf-header-center .sevpf-title-sub {
    font-size: 11.5px; font-weight: 600; margin-top: 3px;
}
#sceneEvidenceFormPdfModal .sevpf-header-right {
    position: absolute; right: 0; top: 7px;
}
#sceneEvidenceFormPdfModal .sevpf-doc-box {
    border: 1.5px solid #000; padding: 3px 8px; font-size: 11px; white-space: nowrap;
}
#sceneEvidenceFormPdfModal .sevpf-doc-box .sevpf-doc-line { line-height: 1.5; }

/* ===== BODY TABLE (2-column) ===== */
#sceneEvidenceFormPdfModal .sevpf-form-body {
    display: flex; border: 1.5px solid #000; align-items: stretch;
}
#sceneEvidenceFormPdfModal .sevpf-col-left {
    width: 50%; border-right: 1.5px solid #000; display: flex; flex-direction: column;
}
#sceneEvidenceFormPdfModal .sevpf-col-right {
    width: 50%; display: flex; flex-direction: column;
}

/* ===== ROW HEADER ===== */
#sceneEvidenceFormPdfModal .sevpf-row-header {
    display: flex; border-bottom: 1px solid #000;
    font-weight: 700; font-size: 11px; text-align: center; background: transparent;
}
#sceneEvidenceFormPdfModal .sevpf-row-header .sevpf-lbl-seq {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000; padding: 1px 2px;
}
#sceneEvidenceFormPdfModal .sevpf-row-header .sevpf-lbl-data {
    flex: 1; padding: 1px 2px;
}

/* ===== SECTION ROW ===== */
#sceneEvidenceFormPdfModal .sevpf-sec-row {
    display: flex; border-bottom: 1px solid #000;
}
#sceneEvidenceFormPdfModal .sevpf-sec-row:last-child { border-bottom: none; }
#sceneEvidenceFormPdfModal .sevpf-sec-label {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000;
    padding: 3px 3px; font-weight: 700; font-size: 11px; text-align: center;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
#sceneEvidenceFormPdfModal .sevpf-sec-label .sevpf-sec-num {
    font-size: 13px; font-weight: 700; line-height: 1.2;
}
#sceneEvidenceFormPdfModal .sevpf-sec-label .sevpf-sec-txt {
    font-size: 10px; font-weight: 600; line-height: 1.15; text-align: center; margin-top: 1px;
}
#sceneEvidenceFormPdfModal .sevpf-sec-body {
    flex: 1; padding: 6px 8px; font-size: 11.5px;
}

/* Section 3.3 specific layout tuning */
#sceneEvidenceFormPdfModal .sevpf-sec-row-33 .sevpf-sec-label {
    justify-content: center;
    align-items: center;
    text-align: center;
    padding-left: 3px;
    padding-right: 3px;
}
#sceneEvidenceFormPdfModal .sevpf-sec-row-33 .sevpf-sec-txt {
    width: 100%;
    text-align: center;
    line-height: 1.2;
}
#sceneEvidenceFormPdfModal .sevpf-sec-row-33 .sevpf-sec-body {
    display: flex;
    flex-direction: column;
}
#sceneEvidenceFormPdfModal .sevpf-33-row {
    margin-bottom: 4px;
    line-height: 1.75;
    flex-wrap: nowrap;
}
#sceneEvidenceFormPdfModal .sevpf-33-row .sevpf-fl {
    white-space: nowrap;
    word-break: keep-all;
    overflow-wrap: normal;
}
#sceneEvidenceFormPdfModal .sevpf-33-method {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    white-space: nowrap;
}

/* ===== FIELD ROW ===== */
#sceneEvidenceFormPdfModal .sevpf-fr {
    display: flex; flex-wrap: wrap; align-items: center; margin-bottom: 6px; line-height: 1.8;
}
#sceneEvidenceFormPdfModal .sevpf-fl {
    font-size: 11.5px; white-space: nowrap; margin-right: 4px;
}
#sceneEvidenceFormPdfModal .sevpf-fl-b {
    font-size: 11.5px; font-weight: 600; white-space: nowrap; margin-right: 4px;
}

/* ===== INPUT FIELDS ===== */
#sceneEvidenceFormPdfModal .sevpf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px;
}
#sceneEvidenceFormPdfModal .sevpf-inp-m {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 60px; margin: 0 2px; text-align: center;
}
#sceneEvidenceFormPdfModal .sevpf-inp-full {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    width: 100%; display: block; margin-bottom: 3px;
}
#sceneEvidenceFormPdfModal .sevpf-inp-s {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0 2px; height: 22px; outline: none; color: #000;
    min-width: 15px; max-width: 50px; margin: 0 2px; flex: 0 1 40px; text-align: center;
}

/* Keep date/time values fully visible in virtual form */
#sceneEvidenceFormPdfModal input[type="date"].sevpf-inp-m {
    flex: 0 0 130px;
    min-width: 130px;
}
#sceneEvidenceFormPdfModal input[type="time"].sevpf-inp {
    flex: 0 0 95px;
    min-width: 95px;
}

/* SELECT styled like dotted line */
#sceneEvidenceFormPdfModal .sevpf-sel {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 11.5px;
    padding: 0; height: 22px; outline: none; color: #000;
    flex: 1; min-width: 20px; margin: 0 2px; cursor: pointer;
}

/* TEXTAREA styled like dotted lines */
#sceneEvidenceFormPdfModal .sevpf-ta {
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
#sceneEvidenceFormPdfModal .sevpf-cb {
    appearance: none; -webkit-appearance: none;
    width: 13px; height: 13px; border: 1.5px solid #000;
    margin-right: 3px; cursor: pointer; position: relative;
    vertical-align: middle; flex-shrink: 0; background: #fff;
}
#sceneEvidenceFormPdfModal .sevpf-cb:checked::after {
    content: '✓'; font-size: 12px; font-weight: 700;
    position: absolute; top: -3px; left: 0px; color: #000;
}

/* Checkbox label */
#sceneEvidenceFormPdfModal .sevpf-ck {
    display: inline-flex; align-items: center; margin-right: 14px;
    font-size: 11.5px; white-space: nowrap; vertical-align: middle; cursor: pointer;
}

/* Filled square bullet */
#sceneEvidenceFormPdfModal .sevpf-bk {
    width: 10px; height: 10px; background: #000;
    display: inline-block; margin-right: 3px; flex-shrink: 0;
    position: relative; top: 1px;
}

/* Bullet header */
#sceneEvidenceFormPdfModal .sevpf-bh {
    display: flex; align-items: center; font-weight: 600;
    font-size: 11.5px; margin-top: 10px; margin-bottom: 6px;
}

/* Sub-items */
#sceneEvidenceFormPdfModal .sevpf-si {
    display: flex; align-items: center; font-size: 11.5px; line-height: 1.8; margin-bottom: 5px;
}
#sceneEvidenceFormPdfModal .sevpf-si-no {
    min-width: 25px; padding-left: 8px; font-size: 11.5px;
}

/* Checkbox group */
#sceneEvidenceFormPdfModal .sevpf-cg {
    display: flex; flex-wrap: wrap; align-items: center; gap: 4px 12px; margin-bottom: 5px;
}

/* Indents */
#sceneEvidenceFormPdfModal .sevpf-i1 { padding-left: 15px; }
#sceneEvidenceFormPdfModal .sevpf-i2 { padding-left: 28px; }

/* Signature section separator */
#sceneEvidenceFormPdfModal .sevpf-sig-section {
    margin-top: 16px; padding-top: 10px;
    border-top: 1px solid #ddd;
}

/* Focus highlight */
#sceneEvidenceFormPdfModal .sevpf-inp:focus,
#sceneEvidenceFormPdfModal .sevpf-inp-m:focus,
#sceneEvidenceFormPdfModal .sevpf-inp-s:focus,
#sceneEvidenceFormPdfModal .sevpf-inp-full:focus,
#sceneEvidenceFormPdfModal .sevpf-sel:focus {
    border-bottom-color: #0d6efd;
    transition: border-color 0.2s;
}

/* Dotted line fill for blank spaces */
#sceneEvidenceFormPdfModal .sevpf-blank-line {
    flex: 1; min-width: 15px; padding: 0 2px;
    border-bottom: 1px dotted #888;
    display: inline-block; margin: 0 2px;
}

/* ===== FOOTER ===== */
#sceneEvidenceFormPdfModal .sevpf-footer {
    margin-top: auto; font-size: 9.5px; color: #333;
    display: flex; justify-content: space-between; align-items: flex-end; flex-shrink: 0;
}
#sceneEvidenceFormPdfModal .sevpf-footer-left { flex: 1; }
#sceneEvidenceFormPdfModal .sevpf-footer-right {
    text-align: right; white-space: nowrap; line-height: 1.3;
}

/* ===== Add/Remove buttons ===== */
#sceneEvidenceFormPdfModal .sevpf-add-btn {
    font-size: 10px; padding: 1px 8px; border: 1px dashed #888;
    background: #f8f8f8; cursor: pointer; color: #333; margin: 3px 0;
    font-family: 'Sarabun', sans-serif;
}
#sceneEvidenceFormPdfModal .sevpf-add-btn:hover { background: #e0e0e0; }
#sceneEvidenceFormPdfModal .sevpf-del-btn {
    font-size: 9px; padding: 0 4px; border: 1px solid #ccc;
    background: #fff; cursor: pointer; color: #c00;
    font-family: 'Sarabun', sans-serif; line-height: 1.5;
}
#sceneEvidenceFormPdfModal .sevpf-del-btn:hover { background: #fee; }

/* ===== Signature box ===== */
#sceneEvidenceFormPdfModal .sevpf-sig-box {
    border: 1px solid #ccc; background: #fafafa; min-height: 60px;
    cursor: crosshair; position: relative;
}
#sceneEvidenceFormPdfModal .sevpf-sig-box canvas {
    width: 100%; height: 100%; display: block;
}

/* ===== Photo Grid 5×7 (35 photos/page) ===== */
#sceneEvidenceFormPdfModal .sevpf-photo-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 6px 4px;
    margin-bottom: 6px;
}
#sceneEvidenceFormPdfModal .sevpf-photo-cell {
    aspect-ratio: 1;
    border: 1.5px solid #333;
    overflow: hidden;
    position: relative;
    background: #fafafa;
    display: flex;
    align-items: center;
    justify-content: center;
}
#sceneEvidenceFormPdfModal .sevpf-photo-cell img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
#sceneEvidenceFormPdfModal .sevpf-cell-delete {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: none;
    background: #dc3545;
    color: #fff;
    font-size: 11px;
    cursor: pointer;
    padding: 0;
    line-height: 18px;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
}
#sceneEvidenceFormPdfModal .sevpf-cell-delete:hover {
    background: #bb2d3b;
}
#sceneEvidenceFormPdfModal .sevpf-cell-filename {
    font-size: 8px;
    text-align: center;
    margin-top: 1px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #333;
}

/* ===== Dropzone ===== */
#sceneEvidenceFormPdfModal .sevpf-photo-dropzone {
    border: 2px dashed #b0bec5;
    border-radius: 8px;
    padding: 16px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: #fafafa;
    margin-bottom: 8px;
}
#sceneEvidenceFormPdfModal .sevpf-photo-dropzone:hover {
    border-color: #78909c;
    background: #f5f5f5;
}
#sceneEvidenceFormPdfModal .sevpf-photo-dropzone.dragover {
    border-color: #2196f3;
    background: #e3f2fd;
}
#sceneEvidenceFormPdfModal .sevpf-add-photo-page-btn {
    display: block; margin: 8px auto; padding: 4px 16px;
    border: 1px dashed #888; background: #f0f0f0; cursor: pointer;
    font-size: 11px; font-family: 'Sarabun', sans-serif; color: #333;
}
#sceneEvidenceFormPdfModal .sevpf-add-photo-page-btn:hover { background: #e0e0e0; }

@media print {
    body > *:not(#sceneEvidenceFormPdfModal),
    .navbar, .sidebar, .main-content, #status-banner,
    .modal-backdrop, .swal2-container,
    #sceneEvidenceFormPdfModal .modal-header,
    #sceneEvidenceFormPdfModal .modal-footer,
    #sceneEvidenceFormPdfModal .csims-loading-overlay,
    #sceneEvidenceFormPdfModal .sevpf-add-btn,
    #sceneEvidenceFormPdfModal .sevpf-del-btn,
    #sceneEvidenceFormPdfModal .sevpf-add-photo-page-btn,
    #sceneEvidenceFormPdfModal .btn-hw-open,
    #sceneEvidenceFormPdfModal .btn-sketch-eraser,
    #sceneEvidenceFormPdfModal input[type="color"],
    #sceneEvidenceFormPdfModal .form-check.form-switch,
    #sceneEvidenceFormPdfModal .d-flex.align-items-center.gap-3,
    #sceneEvidenceFormPdfModal .sevpf-photo-dropzone,
    #sceneEvidenceFormPdfModal .sevpf-cell-delete {
        display: none !important;
    }
    #sceneEvidenceFormPdfModal,
    #sceneEvidenceFormPdfModal .modal-dialog,
    #sceneEvidenceFormPdfModal .modal-content,
    #sceneEvidenceFormPdfModal .sevpf-body {
        position: static !important; display: block !important;
        width: auto !important; max-width: none !important;
        max-height: none !important; height: auto !important;
        overflow: visible !important; margin: 0 !important;
        padding: 0 !important; background: #fff !important;
        border: none !important; box-shadow: none !important;
        transform: none !important; opacity: 1 !important;
    }
    @page { size: A4 portrait; margin: 0; }
    #sceneEvidenceFormPdfModal .sevpf-page {
        width: 100% !important; min-height: auto !important;
        height: auto !important; margin: 0 !important;
        padding: 8mm 10mm 5mm 10mm !important;
        box-shadow: none !important; overflow: visible !important;
        page-break-after: always; page-break-inside: auto;
    }
    #sceneEvidenceFormPdfModal .sevpf-page:last-of-type { page-break-after: auto; }
    #sceneEvidenceFormPdfModal .sevpf-sec-row { page-break-inside: avoid; }
    #sceneEvidenceFormPdfModal .sevpf-form-body { page-break-inside: auto; }
    #sceneEvidenceFormPdfModal .sevpf-header { page-break-after: avoid; }
    #sceneEvidenceFormPdfModal .sevpf-footer { page-break-before: avoid; }
    #sceneEvidenceFormPdfModal .sevpf-photo-slot { page-break-inside: avoid; }
    #sceneEvidenceFormPdfModal .sevpf-sig-box,
    #sceneEvidenceFormPdfModal .sevpf-sketch-area { page-break-inside: avoid; }
    #sceneEvidenceFormPdfModal .sevpf-inp, #sceneEvidenceFormPdfModal .sevpf-inp-m,
    #sceneEvidenceFormPdfModal .sevpf-inp-full, #sceneEvidenceFormPdfModal .sevpf-inp-s,
    #sceneEvidenceFormPdfModal .sevpf-sel, #sceneEvidenceFormPdfModal .sevpf-ta {
        border-bottom-color: #888 !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    #sceneEvidenceFormPdfModal .sevpf-cb {
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
}
</style>

<!-- ===== MODAL ===== -->
<div class="modal fade" id="sceneEvidenceFormPdfModal" aria-labelledby="sceneEvidenceFormPdfModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 860px; margin: 1.75rem auto;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="sceneEvidenceFormPdfModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด
                </h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body sevpf-body p-0" style="max-height: 80vh; overflow-y: auto; position:relative;">
                <!-- Loading Overlay -->
                <div id="sevpfLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track"><div class="csims-bar-fill"></div></div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>
                <form id="sceneEvidenceFormPdf" novalidate>
                    <input type="hidden" id="sevpf_receiveNoti_id" name="receiveNoti_id_ev7">
                    <input type="hidden" id="sevpf_doc_no" name="doc_no_ev7">
                    <input type="hidden" id="sevpf_report_no" name="report_no_ev7">
                    <input type="hidden" id="sevpf_receiver_sig_cleared" name="sevpf_receiver_sig_cleared" value="0">
                    <input type="hidden" id="sevpf_sender_sig_cleared" name="sevpf_sender_sig_cleared" value="0">
                    <input type="hidden" id="sevpf_signer_id_proxy" name="ev7_signer_id">
                    <input type="hidden" id="sevpf_signer_position_proxy" name="ev7_signer_position">
                    <input type="hidden" id="sevpf_sign_date_proxy" name="ev7_sign_date">

                    <!-- Switch + Edit info (เหมือน property form) -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="editInfoEV7Pdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountEV7Pdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToStdFormEV7" checked style="width: 3rem; height: 1.5rem; cursor: pointer;">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToStdFormEV7" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ================================================================ -->
<!-- ========================= PAGE 1 ============================== -->
<!-- ================================================================ -->
<div class="sevpf-page">

    <div class="sevpf-header">
        <div class="sevpf-header-logo">
            <img src="./images/office-of-police-forensic-icon.jpg" alt="ตราสำนักงานพิสูจน์หลักฐานตำรวจ">
        </div>
        <div class="sevpf-header-center">
            <div class="sevpf-title-main">รายงานการตรวจเก็บวัตถุพยาน</div>
            <div class="sevpf-title-sub">การตรวจเก็บวัตถุพยานที่เกิดเหตุ</div>
        </div>
        <div class="sevpf-header-right">
            <div class="sevpf-doc-box">
                <input type="hidden" name="sevpf_report_ref" id="sevpf_report_ref">
                <input type="hidden" name="sevpf_report_year" id="sevpf_report_year" value="<?= substr((date('Y') + 543), -2) ?>">
                <div class="sevpf-doc-line">เลขรับที่/เลขรายงาน <span id="sevpf_report_no_display" class="sevpf-rpt-no-mirror"></span> / 25<span id="sevpf_report_year_display" class="sevpf-rpt-year-mirror"><?= substr((date('Y') + 543), -2) ?></span></div>
                <div class="sevpf-doc-line">หน้าที่ <span class="sevpf-cur-page">1</span> / <span class="sevpf-total-page">3</span></div>
            </div>
        </div>
    </div>

    <div class="sevpf-form-body">

        <!-- LEFT COLUMN -->
        <div class="sevpf-col-left">
            <div class="sevpf-row-header">
                <div class="sevpf-lbl-seq">ลำดับ</div>
                <div class="sevpf-lbl-data">ข้อมูล</div>
            </div>

            <!-- 1. สิ่งที่ได้รับจาก / การรับแจ้งเหตุ -->
            <div class="sevpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="sevpf-sec-label">
                    <span class="sevpf-sec-num">1.</span>
                    <span class="sevpf-sec-txt">การรับ<br>แจ้งเหตุ</span>
                </div>
                <div class="sevpf-sec-body">
                    <div class="sevpf-fr">
                        <span class="sevpf-fl">เมื่อวันที่</span>
                        <input type="date" class="sevpf-inp-m" name="sevpf_receive_date" id="sevpf_receive_date" value="<?= $sevpfTodayDate ?>">
                        <span class="sevpf-fl">เวลา</span>
                        <input type="time" class="sevpf-inp" name="sevpf_receive_time" id="sevpf_receive_time" value="<?= $sevpfTodayTime ?>" style="text-align:center;">
                        <span class="sevpf-fl">น.</span>
                    </div>

                    <div class="sevpf-bh" style="margin-top:2px;"><span class="sevpf-bk"></span><span>หน่วยงาน</span></div>
                    <div class="sevpf-fr sevpf-i1">
                        <input type="text" class="sevpf-inp" name="sevpf_unit_name" id="sevpf_unit_name" placeholder="ระบุหน่วยงาน">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_unit_name" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="sevpf-cg sevpf-i1">
                        <label class="sevpf-ck"><input type="checkbox" class="sevpf-cb" name="sevpf_unit_type_check[]" value="กองพิสูจน์หลักฐานกลาง">กองพิสูจน์หลักฐานกลาง</label>
                    </div>
                    <div class="sevpf-cg sevpf-i1">
                        <label class="sevpf-ck"><input type="checkbox" class="sevpf-cb" name="sevpf_unit_type_check[]" value="ศูนย์พิสูจน์หลักฐาน">ศูนย์พิสูจน์หลักฐาน</label>
                        
                    </div>
                    <div class="sevpf-cg sevpf-i1">
                        <label class="sevpf-ck"><input type="checkbox" class="sevpf-cb" name="sevpf_unit_type_check[]" value="พิสูจน์หลักฐานจังหวัด">พิสูจน์หลักฐานจังหวัด</label>
                        
                    </div>
                    <div class="sevpf-cg sevpf-i1">
                        <label class="sevpf-ck"><input type="checkbox" class="sevpf-cb" name="sevpf_unit_type_check[]" value="กลุ่มงานการตรวจพิสูจน์">กลุ่มงานการตรวจพิสูจน์</label>
                    </div>

                    <div class="sevpf-bh" style="margin-top:4px;"><span class="sevpf-bk"></span><span>การรับแจ้ง</span></div>
                    <div class="sevpf-fr sevpf-i1" style="gap:4px 8px;">
                        <span class="sevpf-fl" style="min-width:55px;">ได้รับแจ้ง</span>
                        <label class="sevpf-ck"><input type="checkbox" class="sevpf-cb" name="sevpf_notify_method[]" value="ทางโทรศัพท์">ทางโทรศัพท์</label>
                        <label class="sevpf-ck"><input type="checkbox" class="sevpf-cb" name="sevpf_notify_method[]" value="ทางวิทยุสื่อสาร">ทางวิทยุสื่อสาร</label>
                        <label class="sevpf-ck"><input type="checkbox" class="sevpf-cb" name="sevpf_notify_method[]" value="ทางหนังสือ">ทางหนังสือ</label>
                        <label class="sevpf-ck" style="margin-right:4px;"><input type="checkbox" class="sevpf-cb" name="sevpf_notify_method[]" value="อื่นๆ">อื่นๆ</label>
                        <input type="text" class="sevpf-inp" name="sevpf_notify_method_other_text" id="sevpf_notify_method_other_text" placeholder="ระบุ" style="max-width:100px;">
                    </div>
                    <div class="sevpf-fr sevpf-i1">
                        <span class="sevpf-fl">จาก สน./สภ.</span>
                        <select class="sevpf-sel" name="sevpf_police_station" id="sevpf_police_station">
                            <?= $sevpfPoliceStationOptions ?>
                        </select>
                    </div>
                    <div class="sevpf-fr sevpf-i1">
                        <span class="sevpf-fl">ที่</span>
                        <input type="text" class="sevpf-inp" name="sevpf_document_no" id="sevpf_document_no">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_document_no" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <span class="sevpf-fl">ลง</span>
                        <input type="date" class="sevpf-inp" name="sevpf_document_date" id="sevpf_document_date" style="text-align:center;" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="sevpf-fr sevpf-i1">
                        <span class="sevpf-fl">ในคดี</span>
                        <input type="text" class="sevpf-inp" name="sevpf_case_no" id="sevpf_case_no" readonly>
                    </div>
                    <div class="sevpf-fr sevpf-i1">
                        <span class="sevpf-fl">สถานที่เกิดเหตุ</span>
                        <input type="text" class="sevpf-inp sevpf-auto-line-location" name="sevpf_incident_location" id="sevpf_incident_location">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_incident_location" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <input type="text" class="sevpf-inp-full sevpf-i1 sevpf-auto-line-location" name="sevpf_incident_location_2">
                    <div class="sevpf-fr sevpf-i1">
                        <span class="sevpf-fl">เหตุเกิดเมื่อวันที่</span>
                        <input type="date" class="sevpf-inp-m" name="sevpf_incident_date" id="sevpf_incident_date" value="<?= $sevpfTodayDate ?>">
                        <span class="sevpf-fl">เวลาประมาณ</span>
                        <input type="time" class="sevpf-inp" name="sevpf_incident_time" id="sevpf_incident_time" value="<?= $sevpfTodayTime ?>" style="text-align:center;">
                        <span class="sevpf-fl">น.</span>
                    </div>
                    <div class="sevpf-fr sevpf-i1">
                        <span class="sevpf-fl">พนักงานสอบสวน</span>
                        <input type="text" class="sevpf-inp" name="sevpf_investigator_name" id="sevpf_investigator_name">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_investigator_name" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="sevpf-fr sevpf-i1">
                        <span class="sevpf-fl">ขอส่ง</span>
                        <input type="text" class="sevpf-inp" name="sevpf_send_request" id="sevpf_send_request">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_send_request" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <div class="sevpf-bh" style="margin-top:4px;"><span class="sevpf-bk"></span><span>รายการของกลาง</span></div>
                    <div id="sevpf_evidence_items_container" class="sevpf-i1">
                        <div class="sevpf-si sevpf-evidence-item-row" style="flex-wrap:wrap;gap:2px;">
                            <span class="sevpf-si-no">1.1</span>
                            <input type="text" class="sevpf-inp" name="sevpf_evidence_item[]" style="flex:1;min-width:120px;">
                            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                            <select class="sevpf-sel lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="max-width:180px;font-size:0.75rem;">
                                <option value="">-- การตรวจพิสูจน์ (เลือกได้หลายข้อ) --</option>
                                <option value="bio_dna">ตรวจชีววิทยา</option>
                                <option value="chemical">ตรวจทางเคมีฟิสิกส์</option>
                                <option value="fingerprint">ตรวจลายนิ้วมือแฝง</option>
                                <option value="drug">ตรวจยาเสพติด</option>
                                <option value="gun">ตรวจอาวุธปืนฯ</option>
                                <option value="document">ตรวจเอกสาร</option>
                                <option value="digital">ตรวจพิสูจน์ดิจิทัล</option><option value="computer">ตรวจอาชญากรรมคอมพิวเตอร์</option>
                            </select>
                            <input type="hidden" class="lab-unit-value" name="sevpf_lab_unit[]" value="">
                            <!-- ไม่มีปุ่มลบแถวแรก -->
                        </div>
                    </div>
                    <div class="sevpf-i1">
                        <button type="button" class="sevpf-add-btn" onclick="sevpfAddEvidenceItem()">+ เพิ่มรายการของกลาง</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="sevpf-col-right">
            <div class="sevpf-row-header">
                <div class="sevpf-lbl-seq">ลำดับ</div>
                <div class="sevpf-lbl-data">ข้อมูล</div>
            </div>

            <!-- 2. จุดประสงค์ในการตรวจ -->
            <div class="sevpf-sec-row">
                <div class="sevpf-sec-label">
                    <span class="sevpf-sec-num">2.</span>
                    <span class="sevpf-sec-txt">จุดประสงค์<br>ในการ<br>ตรวจ</span>
                </div>
                <div class="sevpf-sec-body">
                    <div class="sevpf-fr">
                        <span class="sevpf-fl">เพื่อทำการตรวจเก็บ (</span>
                    </div>
                    <div class="sevpf-cg sevpf-i1">
                        <label class="sevpf-ck"><input type="checkbox" class="sevpf-cb" name="sevpf_purpose[]" value="รอยลายนิ้วมือแฝง">รอยลายนิ้วมือแฝง</label>
                        <label class="sevpf-ck"><input type="checkbox" class="sevpf-cb" name="sevpf_purpose[]" value="สารพันธุกรรม">สารพันธุกรรม</label>
                        <label class="sevpf-ck"><input type="checkbox" class="sevpf-cb" name="sevpf_purpose[]" value="วัตถุพยานอื่นๆ">วัตถุพยานอื่นๆ</label>
                        <span class="sevpf-fl">)</span>
                    </div>
                    <div class="sevpf-fr sevpf-i1">
                        <span class="sevpf-fl">ที่</span>
                        <input type="text" class="sevpf-inp" name="sevpf_purpose_detail" id="sevpf_purpose_detail">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_purpose_detail" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <span class="sevpf-fl">ของกลางดังกล่าว</span>
                    </div>
                </div>
            </div>

            <!-- 3. ผลการตรวจ -->
            <div class="sevpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="sevpf-sec-label">
                    <span class="sevpf-sec-num">3.</span>
                    <span class="sevpf-sec-txt">ผลการ<br>ตรวจ</span>
                </div>
                <div class="sevpf-sec-body">
                    <div class="sevpf-fr">
                        <span class="sevpf-fl">ได้ทำการตรวจของกลางที่</span>
                        <input type="text" class="sevpf-inp" name="sevpf_inspect_location" id="sevpf_inspect_location">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_inspect_location" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="sevpf-fr">
                        <span class="sevpf-fl">เมื่อวันที่</span>
                        <input type="date" class="sevpf-inp-m" name="sevpf_inspect_date" id="sevpf_inspect_date" value="<?= $sevpfTodayDate ?>">
                        <span class="sevpf-fl">เวลาประมาณ</span>
                        <input type="time" class="sevpf-inp" name="sevpf_inspect_time" id="sevpf_inspect_time" value="<?= $sevpfTodayTime ?>" style="text-align:center;">
                        <span class="sevpf-fl">น.</span>
                    </div>

                    <!-- 3.1 ลักษณะของกลาง -->
                    <div class="sevpf-bh"><span class="sevpf-bk"></span><span>3.1 ลักษณะของกลาง</span></div>
                    <div class="sevpf-fr sevpf-i1">
                        <span class="sevpf-fl" style="font-size:10px;">(ระบุสภาพทั่วไป เช่น รูปร่าง สี ขนาด ยี่ห้อ)</span>
                    </div>
                    <div id="sevpf_exhibit_desc_container" class="sevpf-i1">
                        <div class="sevpf-si sevpf-exhibit-row">
                            <span class="sevpf-si-no">3.1.1</span>
                            <span style="font-size:11px; margin-right:2px;">ของกลางรายการที่ 1 เป็น</span>
                            <input type="text" class="sevpf-inp" name="sevpf_exhibit_desc[]">
                            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        </div>
                    </div>
                    <div class="sevpf-i1">
                        <button type="button" class="sevpf-add-btn" onclick="sevpfAddExhibitDesc()">+ เพิ่มรายการ</button>
                    </div>

                    <!-- 3.2 วัตถุพยานที่ตรวจเก็บ -->
                    <div class="sevpf-bh"><span class="sevpf-bk"></span><span>3.2 วัตถุพยานที่ตรวจเก็บ</span></div>
                    <div class="sevpf-fr sevpf-i1" style="gap:4px 8px;">
                        <span class="sevpf-fl">ตรวจเก็บ (</span>
                        <label class="sevpf-ck"><input type="checkbox" class="sevpf-cb" name="sevpf_collect_type[]" value="รอยลายนิ้วมือแฝง">รอยลายนิ้วมือแฝง</label>
                        <label class="sevpf-ck"><input type="checkbox" class="sevpf-cb" name="sevpf_collect_type[]" value="ฝ่ามือแฝง">ฝ่ามือแฝง</label>
                        <label class="sevpf-ck"><input type="checkbox" class="sevpf-cb" name="sevpf_collect_type[]" value="ฝ่าเท้าแฝง">ฝ่าเท้าแฝง</label>
                        <span class="sevpf-fl">)</span>
                    </div>
                    <div class="sevpf-fr sevpf-i1">
                        <span class="sevpf-fl">จำนวน</span>
                        <input type="text" class="sevpf-inp-s" name="sevpf_collect_sheet_count" id="sevpf_collect_sheet_count" style="max-width:40px;">
                        <span class="sevpf-fl">แผ่น ที่</span>
                        <input type="text" class="sevpf-inp" name="sevpf_collect_location" id="sevpf_collect_location">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_collect_location" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <div id="sevpf_collect_detail_container" class="sevpf-i2">
                        <div class="sevpf-si sevpf-collect-row">
                            <span class="sevpf-si-no">3.2.1.1</span>
                            <input type="text" class="sevpf-inp" name="sevpf_collect_detail[]">
                            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        </div>
                    </div>
                    <div class="sevpf-i2">
                        <button type="button" class="sevpf-add-btn" onclick="sevpfAddCollectDetail()">+ เพิ่ม</button>
                    </div>

                    <div class="sevpf-fr sevpf-i1" style="margin-top:6px;">
                        <span class="sevpf-fl">วัตถุพยานประเภทอื่น</span>
                        <input type="text" class="sevpf-inp" name="sevpf_other_evidence_text" id="sevpf_other_evidence_text">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_other_evidence_text" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="sevpf-footer">
        <div class="sevpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="sevpf-footer-right">บัญชีแนบท้ายคำสั่ง สพฐ.ตร.<br>ที่ ๘๔๘/๒๕๖๑</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 2 ============================== -->
<!-- ================================================================ -->
<div class="sevpf-page">

    <div class="sevpf-header">
        <div class="sevpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="sevpf-header-center">
            <div class="sevpf-title-main">รายงานการตรวจเก็บวัตถุพยาน</div>
            <div class="sevpf-title-sub">การตรวจเก็บวัตถุพยานที่เกิดเหตุ</div>
        </div>
        <div class="sevpf-header-right">
            <div class="sevpf-doc-box">
                <div class="sevpf-doc-line">เลขรับที่/เลขรายงาน <span class="sevpf-rpt-no-mirror"></span> / 25<span class="sevpf-rpt-year-mirror"></span></div>
                <div class="sevpf-doc-line">หน้าที่ <span class="sevpf-cur-page">2</span> / <span class="sevpf-total-page">3</span></div>
            </div>
        </div>
    </div>

    <div class="sevpf-form-body">
        <!-- LEFT COLUMN -->
        <div class="sevpf-col-left">
            <div class="sevpf-row-header"><div class="sevpf-lbl-seq">ลำดับ</div><div class="sevpf-lbl-data">ข้อมูล</div></div>

            <!-- 3. (ต่อ) การดำเนินการเกี่ยวกับวัตถุพยาน -->
            <div class="sevpf-sec-row sevpf-sec-row-33" style="flex:1; border-bottom:none;">
                <div class="sevpf-sec-label"><span class="sevpf-sec-num">3.</span><span class="sevpf-sec-txt">(ต่อ)</span></div>
                <div class="sevpf-sec-body">
                    <div class="sevpf-bh" style="margin-top:0;"><span class="sevpf-bk"></span><span>3.3 การดำเนินการเกี่ยวกับวัตถุพยาน</span></div>

                    <!-- พยาน -->
                    <div class="sevpf-fr sevpf-i1 sevpf-33-row">
                        <span class="sevpf-fl">ได้ให้</span>
                        <input type="text" class="sevpf-inp" name="sevpf_witness_name" id="sevpf_witness_name" style="flex:1;">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_witness_name" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="sevpf-fr sevpf-i1 sevpf-33-row">
                        <span class="sevpf-fl">ลงลายมือชื่อ/พิมพ์ลายนิ้วมือในแบบ</span>
                        <input type="text" class="sevpf-inp" name="sevpf_witness_form" id="sevpf_witness_form" style="flex:1;">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_witness_form" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="sevpf-fr sevpf-i1 sevpf-33-row">
                        <span class="sevpf-fl">รายละเอียดเพิ่มเติม</span>
                        <input type="text" class="sevpf-inp" name="sevpf_witness_detail" id="sevpf_witness_detail" style="flex:1;">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_witness_detail" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- การส่งมอบ -->
                    <div class="sevpf-fr sevpf-i1 sevpf-33-row">
                        <span class="sevpf-fl">ให้เป็นหลักฐาน และได้ (</span>
                        <span class="sevpf-33-method">
                            <label class="sevpf-ck" style="margin:0;"><input type="checkbox" class="sevpf-cb" name="sevpf_handover_method_check[]" value="นำส่ง">นำส่ง</label>
                            <label class="sevpf-ck" style="margin:0;"><input type="checkbox" class="sevpf-cb" name="sevpf_handover_method_check[]" value="ส่งมอบ">ส่งมอบ</label>
                        </span>
                        <span class="sevpf-fl">)</span>
                        <input type="text" class="sevpf-inp" name="sevpf_handover_method_detail" id="sevpf_handover_method_detail" style="flex:1;">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_handover_method_detail" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="sevpf-fr sevpf-i1 sevpf-33-row">
                        <span class="sevpf-fl">วัตถุพยานตามข้อ</span>
                        <input type="text" class="sevpf-inp-m" name="sevpf_handover_item_ref" id="sevpf_handover_item_ref" style="flex:0 1 60px; min-width:40px;" value="3.2" readonly>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_handover_item_ref" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <span class="sevpf-fl">ให้</span>
                        <input type="text" class="sevpf-inp" name="sevpf_handover_to" id="sevpf_handover_to" style="flex:1;">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_handover_to" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="sevpf-fr sevpf-i1 sevpf-33-row">
                        <input type="text" class="sevpf-inp" name="sevpf_handover_purpose" id="sevpf_handover_purpose" style="flex:1;">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_handover_purpose" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="sevpf-fr sevpf-i1 sevpf-33-row">
                        <span class="sevpf-fl">เพื่อดำเนินการต่อไป</span>
                        <input type="text" class="sevpf-inp sevpf-33-auto-line" name="sevpf_handover_next_action" id="sevpf_handover_next_action" style="flex:1;">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="sevpf_handover_next_action" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <input type="text" class="sevpf-inp-full sevpf-i1 sevpf-33-auto-line" name="sevpf_handover_more_1">
                    <input type="text" class="sevpf-inp-full sevpf-i1 sevpf-33-auto-line" name="sevpf_handover_more_2">
                    <input type="text" class="sevpf-inp-full sevpf-i1 sevpf-33-auto-line" name="sevpf_handover_more_3">
                    <input type="text" class="sevpf-inp-full sevpf-i1 sevpf-33-auto-line" name="sevpf_handover_more_4">
                    <input type="text" class="sevpf-inp-full sevpf-i1 sevpf-33-auto-line" name="sevpf_handover_more_5">
                    <input type="text" class="sevpf-inp-full sevpf-i1 sevpf-33-auto-line" name="sevpf_handover_more_6">
                    <input type="text" class="sevpf-inp-full sevpf-i1 sevpf-33-auto-line" name="sevpf_handover_more_7">
                    <input type="text" class="sevpf-inp-full sevpf-i1 sevpf-33-auto-line" name="sevpf_handover_more_8">
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="sevpf-col-right">
            <div class="sevpf-row-header"><div class="sevpf-lbl-seq">ลำดับ</div><div class="sevpf-lbl-data">ข้อมูล</div></div>

            <!-- 4. ผู้ตรวจ + ลงนาม -->
            <div class="sevpf-sec-row" style="flex:1; border-bottom:none;">
                <div class="sevpf-sec-label"><span class="sevpf-sec-num">4.</span><span class="sevpf-sec-txt">ผู้ตรวจ<br>&amp;<br>ลงนาม</span></div>
                <div class="sevpf-sec-body">
                    <div class="sevpf-bh" style="margin-top:0;"><span class="sevpf-bk"></span><span>ผู้ตรวจสถานที่เกิดเหตุ</span></div>
                    <div id="sevpf_inspector_container">
                        <div class="sevpf-si sevpf-inspector-row">
                            <span class="sevpf-si-no">4.1</span>
                            <select class="sevpf-sel" name="ev7_inspector_id[]">
                                <?= $inspectorOptionsEV7 ?>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="sevpf-add-btn" onclick="sevpfAddInspector()">+ เพิ่มผู้ตรวจ</button>

                    <!-- ผู้รับมอบ -->
                    <div class="sevpf-sig-section">
                        <div class="sevpf-bh" style="margin-top:0; margin-bottom:4px;"><span class="sevpf-bk"></span><span>ผู้รับมอบวัตถุพยาน</span></div>
                        <div class="sevpf-fr" style="align-items:flex-end; margin-bottom:4px;">
                            <span class="sevpf-fl" style="min-width:38px;">ลงชื่อ</span>
                            <div style="flex:1; min-height:60px; border-bottom:1px dotted #888; position:relative;">
                                <canvas id="sevpf_sig_receiver" style="width:100%; height:60px; cursor:crosshair;"></canvas>
                                <input type="hidden" name="sevpf_receiver_signature_data" id="sevpf_receiver_sig_data">
                            </div>
                            <span class="sevpf-fl" style="margin-left:4px;">ผู้รับมอบ</span>
                        </div>
                        <div class="sevpf-fr" style="margin-top:-2px; margin-bottom:3px; justify-content:flex-end;">
                            <button type="button" class="sevpf-del-btn" style="font-size:10px; padding:1px 10px;" onclick="if (typeof window.clearEV7ReceiverSignatures === 'function') { window.clearEV7ReceiverSignatures(); } else { sevpfClearCanvas('sevpf_sig_receiver'); }">
                                <i class="fas fa-eraser"></i> ล้างลายเซ็น
                            </button>
                        </div>
                        <div class="sevpf-fr" style="margin-top:2px; margin-bottom:3px;">
                            <span class="sevpf-fl" style="min-width:38px; visibility:hidden;">ลงชื่อ</span>
                            <span class="sevpf-fl">(</span>
                            <select class="sevpf-sel sevpf-user-select" name="sevpf_receiver_id" id="sevpf_receiver_id" style="text-align:center;" data-pos-target="#sevpf_receiver_position">
                                <?= str_replace('-- เลือก --', '-- เลือกผู้รับมอบ --', $sevpfInspectorOptions) ?>
                            </select>
                            <span class="sevpf-fl">)</span>
                        </div>
                        <div class="sevpf-fr" style="margin-bottom:0;">
                            <span class="sevpf-fl" style="min-width:38px;">ตำแหน่ง</span>
                            <input type="text" class="sevpf-inp" name="sevpf_receiver_position" id="sevpf_receiver_position" readonly>
                        </div>
                    </div>

                    <!-- ผู้ส่งมอบ -->
                    <div class="sevpf-sig-section">
                        <div class="sevpf-bh" style="margin-top:0; margin-bottom:4px;"><span class="sevpf-bk"></span><span>ผู้ส่งมอบวัตถุพยาน</span></div>
                        <div class="sevpf-fr" style="align-items:flex-end; margin-bottom:4px;">
                            <span class="sevpf-fl" style="min-width:38px;">ลงชื่อ</span>
                            <div style="flex:1; min-height:60px; border-bottom:1px dotted #888; position:relative;">
                                <canvas id="sevpf_sig_sender" style="width:100%; height:60px; cursor:crosshair;"></canvas>
                                <input type="hidden" name="sevpf_sender_signature_data" id="sevpf_sender_sig_data">
                            </div>
                            <span class="sevpf-fl" style="margin-left:4px;">ผู้ส่งมอบ</span>
                        </div>
                        <div class="sevpf-fr" style="margin-top:-2px; margin-bottom:3px; justify-content:flex-end;">
                            <button type="button" class="sevpf-del-btn" style="font-size:10px; padding:1px 10px;" onclick="if (typeof window.clearEV7SenderSignatures === 'function') { window.clearEV7SenderSignatures(); } else { sevpfClearCanvas('sevpf_sig_sender'); }">
                                <i class="fas fa-eraser"></i> ล้างลายเซ็น
                            </button>
                        </div>
                        <div class="sevpf-fr" style="margin-top:2px; margin-bottom:3px;">
                            <span class="sevpf-fl" style="min-width:38px; visibility:hidden;">ลงชื่อ</span>
                            <span class="sevpf-fl">(</span>
                            <select class="sevpf-sel sevpf-user-select" name="sevpf_sender_id" id="sevpf_sender_id" style="text-align:center;" data-pos-target="#sevpf_sender_position">
                                <?= str_replace('-- เลือก --', '-- เลือกผู้ส่งมอบ --', $sevpfInspectorOptions) ?>
                            </select>
                            <span class="sevpf-fl">)</span>
                        </div>
                        <div class="sevpf-fr" style="margin-bottom:0;">
                            <span class="sevpf-fl" style="min-width:38px;">ตำแหน่ง</span>
                            <input type="text" class="sevpf-inp" name="sevpf_sender_position" id="sevpf_sender_position" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="sevpf-footer">
        <div class="sevpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="sevpf-footer-right">บัญชีแนบท้ายคำสั่ง สพฐ.ตร.<br>ที่ ๘๔๘/๒๕๖๑</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 3 (ภาพถ่าย) ==================== -->
<!-- ================================================================ -->
<div class="sevpf-page sevpf-photo-page">

    <div class="sevpf-header">
        <div class="sevpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="sevpf-header-center">
            <div class="sevpf-title-main">รายงานการตรวจเก็บวัตถุพยาน</div>
            <div class="sevpf-title-sub">บันทึกการถ่ายภาพ</div>
        </div>
        <div class="sevpf-header-right">
            <div class="sevpf-doc-box">
                <div class="sevpf-doc-line">เลขรับที่/เลขรายงาน <span class="sevpf-rpt-no-mirror"></span> / 25<span class="sevpf-rpt-year-mirror"></span></div>
                <div class="sevpf-doc-line">หน้าที่ <span class="sevpf-cur-page">3</span> / <span class="sevpf-total-page">3</span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:8px;">
        <div class="sevpf-fr" style="margin-bottom:6px;">
            <span class="sevpf-fl">วันที่ตรวจสถานที่เกิดเหตุ</span>
            <input type="date" class="sevpf-inp" name="photo_inspect_date_ev7" style="text-align:center;">
            <span class="sevpf-fl">เวลาประมาณ</span>
            <input type="time" class="sevpf-inp" name="photo_inspect_time_ev7" style="text-align:center;">
            <span class="sevpf-fl">น.</span>
        </div>
        <div class="sevpf-fr" style="flex-wrap:nowrap; margin-bottom:6px;">
            <span class="sevpf-fl">รหัสภาพถ่ายที่</span>
            <input type="text" class="sevpf-inp" name="photo_id_start_ev7" style="min-width:80px;" readonly>
            <span class="sevpf-fl">ถึง</span>
            <input type="text" class="sevpf-inp" name="photo_id_end_ev7" style="min-width:80px;" readonly>
            <span class="sevpf-fl">จำนวน</span>
            <input type="text" class="sevpf-inp-s" name="photo_amount_ev7" style="max-width:45px; text-align:center;" readonly>
            <span class="sevpf-fl">ภาพ</span>
        </div>
        <div style="font-size:10.5px; font-style:italic; margin-top:2px;">(ตามภาพถ่ายรวมที่แนบ)</div>
    </div>

    <!-- Drag & Drop zone -->
    <div class="sevpf-photo-dropzone" id="sevpf_photo_dropzone">
        <i class="fas fa-cloud-upload-alt" style="font-size:1.2rem; color:#90a4ae;"></i>
        <div style="font-size:10px; color:#666; margin-top:2px;">ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือก (Grid 5×7)</div>
    </div>
    <input type="file" id="sevpf_photo_input_gallery" accept="image/*" multiple style="display:none;" onchange="sevpfPreviewPhotos(this)">
    <input type="file" id="sevpf_photo_input_camera" accept="image/*" capture="environment" multiple style="display:none;" onchange="sevpfPreviewPhotos(this)">

    <!-- Photo Grid (35 photos per page, populated by JS) -->
    <div class="sevpf-photo-grid" id="sevpf_photo_grid_1"></div>

    <div class="sevpf-footer" style="margin-top:auto;">
        <div class="sevpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="sevpf-footer-right" style="display:flex; flex-direction:column; align-items:flex-end; gap:2px;">
            <div style="display:flex; align-items:center; gap:4px; font-size:11px;">
                <span>ผู้จดบันทึก</span>
                <select class="sevpf-sel" name="sevpf_photographer_name" id="sevpf_photographer_name" style="width:180px; font-size:10px; padding:1px 2px;">
                    <?= $sevpfPhotographerOptions ?>
                </select>
            </div>
            <div style="display:flex; align-items:center; gap:4px; font-size:11px;">
                <span>วัน/เวลา</span>
                <input type="datetime-local" class="sevpf-inp" name="sevpf_photographer_datetime" id="sevpf_photographer_datetime" style="width:180px; font-size:10px; padding:1px 2px; text-align:center;">
            </div>
            <div style="font-size:9px; margin-top:2px;">บัญชีแนบท้ายคำสั่ง สพฐ.ตร.<br>ที่ ๘๔๘/๒๕๖๑</div>
        </div>
    </div>
</div>

<div id="sevpf_extra_photo_pages"></div>

<button type="button" class="sevpf-add-photo-page-btn" onclick="sevpfAddPhotoPage()">+ เพิ่มหน้ารูปถ่าย</button>

                </form>
            </div>

            <!-- FOOTER (เหมือน property form) -->
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_ev7_pdf" onclick="prepareDataForSubmissionSceneEvidence()">
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
    // ===== อัปเดตหมายเลขหน้า =====
    var sevpfUpdatePageNumbers = function() {
        var pages = document.querySelectorAll('#sceneEvidenceFormPdfModal .sevpf-page');
        var total = pages.length;
        pages.forEach(function(page, idx) {
            var curEl = page.querySelector('.sevpf-cur-page');
            var totalEl = page.querySelector('.sevpf-total-page');
            if (curEl) curEl.textContent = (idx + 1);
            if (totalEl) totalEl.textContent = total;
        });
    };
    sevpfUpdatePageNumbers();
    window.sevpfUpdatePageNumbers = sevpfUpdatePageNumbers;

    // ===== Mirror report no → หน้าอื่น =====
    var sevpfSyncReportNo = function() {
        var docNoVal = document.getElementById('sevpf_doc_no') ? document.getElementById('sevpf_doc_no').value : '';
        var reportNoVal = document.getElementById('sevpf_report_no') ? document.getElementById('sevpf_report_no').value : '';
        var thDoc = (window.toThaiDocNo ? window.toThaiDocNo(docNoVal) : docNoVal);
        var thRef = function(v) {
            return (window.smartThaiReportOrDoc ? window.smartThaiReportOrDoc(String(v || '')) : v);
        };
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
        // Preserve saved values; only fill defaults when field is empty.
        if (refInput && !String(refInput.value || '').trim()) refInput.value = thDoc;
        if (yearInput && yearVal && !String(yearInput.value || '').trim()) yearInput.value = yearVal;

        var yearDisplay = yearVal || (yearInput ? yearInput.value : '');
        var refDisplay = thRef((refInput ? refInput.value : '') || docNoVal);
        document.querySelectorAll('#sceneEvidenceFormPdfModal .sevpf-rpt-no-mirror').forEach(function(el) { el.textContent = refDisplay; });
        document.querySelectorAll('#sceneEvidenceFormPdfModal .sevpf-rpt-year-mirror').forEach(function(el) { el.textContent = yearDisplay; });
    };
    window.sevpfSyncReportNo = sevpfSyncReportNo;
    var sevpfHydrateReportNoFromHidden = function() {
        sevpfSyncReportNo();
    };
    var refEl = document.getElementById('sevpf_report_ref');
    var yearEl = document.getElementById('sevpf_report_year');
    if (refEl) refEl.addEventListener('input', sevpfSyncReportNo);
    if (yearEl) yearEl.addEventListener('input', sevpfSyncReportNo);
    sevpfSyncReportNo();
    sevpfHydrateReportNoFromHidden(false);

    // ===== Inspector counter =====
    var sevpfInspectorIdx = 1;
    window.sevpfAddInspector = function() {
        sevpfInspectorIdx++;
        var container = document.getElementById('sevpf_inspector_container');
        var row = document.createElement('div');
        row.className = 'sevpf-si sevpf-inspector-row';
        row.innerHTML = '<span class="sevpf-si-no">4.' + sevpfInspectorIdx + '</span>' +
            '<select class="sevpf-sel" name="ev7_inspector_id[]">' +
            container.querySelector('select').innerHTML +
            '</select>' +
            ' <button type="button" class="sevpf-del-btn" onclick="this.parentElement.remove(); sevpfRenumberInspectors();">×</button>';
        container.appendChild(row);
    };
    window.sevpfRenumberInspectors = function() {
        var rows = document.querySelectorAll('#sevpf_inspector_container .sevpf-inspector-row');
        rows.forEach(function(r, i) {
            var no = r.querySelector('.sevpf-si-no');
            if (no) no.textContent = '4.' + (i + 1);
        });
        sevpfInspectorIdx = rows.length;
    };

    // ===== Evidence items =====
    var sevpfItemIdx = 1;
    var sevpfLabUnitOpts = '<option value="">-- การตรวจพิสูจน์ (เลือกได้หลายข้อ) --</option>' +
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
        row.className = 'sevpf-si sevpf-evidence-item-row';
        row.style.cssText = 'flex-wrap:wrap;gap:2px;';
        row.innerHTML = '<span class="sevpf-si-no">1.' + sevpfItemIdx + '</span>' +
            '<input type="text" class="sevpf-inp" name="sevpf_evidence_item[]" style="flex:1;min-width:120px;">' +
            '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="\u0e40\u0e02\u0e35\u0e22\u0e19\u0e14\u0e49\u0e27\u0e22\u0e25\u0e32\u0e22\u0e21\u0e37\u0e2d" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
            '<select class="sevpf-sel lab-unit-multi" multiple size="3" title="\u0e40\u0e25\u0e37\u0e2d\u0e01\u0e44\u0e14\u0e49\u0e21\u0e32\u0e01\u0e01\u0e27\u0e48\u0e32 1 \u0e01\u0e25\u0e38\u0e48\u0e21\u0e07\u0e32\u0e19" style="max-width:180px;font-size:0.75rem;">' + sevpfLabUnitOpts + '</select>' +
            '<input type="hidden" class="lab-unit-value" name="sevpf_lab_unit[]" value="">' +
            ' <button type="button" class="sevpf-del-btn" onclick="this.parentElement.remove(); sevpfRenumberEvidenceItems();">×</button>';
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

    // ===== Exhibit descriptions =====
    var sevpfExhibitIdx = 1;
    window.sevpfAddExhibitDesc = function() {
        sevpfExhibitIdx++;
        var n = sevpfExhibitIdx;
        var container = document.getElementById('sevpf_exhibit_desc_container');
        var row = document.createElement('div');
        row.className = 'sevpf-si sevpf-exhibit-row';
        row.innerHTML = '<span class="sevpf-si-no">3.1.' + n + '</span>' +
            '<span style="font-size:11px; margin-right:2px;">ของกลางรายการที่ ' + n + ' เป็น</span>' +
            '<input type="text" class="sevpf-inp" name="sevpf_exhibit_desc[]">' +
            '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="\u0e40\u0e02\u0e35\u0e22\u0e19\u0e14\u0e49\u0e27\u0e22\u0e25\u0e32\u0e22\u0e21\u0e37\u0e2d" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
            ' <button type="button" class="sevpf-del-btn" onclick="this.parentElement.remove(); sevpfRenumberExhibits();">×</button>';
        container.appendChild(row);
    };
    window.sevpfRenumberExhibits = function() {
        var rows = document.querySelectorAll('#sevpf_exhibit_desc_container .sevpf-exhibit-row');
        rows.forEach(function(r, i) {
            var n = i + 1;
            var no = r.querySelector('.sevpf-si-no');
            if (no) no.textContent = '3.1.' + n;
            var label = r.querySelectorAll('span')[1];
            if (label) label.textContent = 'ของกลางรายการที่ ' + n + ' เป็น';
        });
        sevpfExhibitIdx = rows.length;
    };

    // ===== Collect details =====
    var sevpfCollectIdx = 1;
    window.sevpfAddCollectDetail = function() {
        sevpfCollectIdx++;
        var container = document.getElementById('sevpf_collect_detail_container');
        var row = document.createElement('div');
        row.className = 'sevpf-si sevpf-collect-row';
        row.innerHTML = '<span class="sevpf-si-no">3.2.1.' + sevpfCollectIdx + '</span>' +
            '<input type="text" class="sevpf-inp" name="sevpf_collect_detail[]">' +
            '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="\u0e40\u0e02\u0e35\u0e22\u0e19\u0e14\u0e49\u0e27\u0e22\u0e25\u0e32\u0e22\u0e21\u0e37\u0e2d" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
            ' <button type="button" class="sevpf-del-btn" onclick="this.parentElement.remove(); sevpfRenumberCollectDetails();">×</button>';
        container.appendChild(row);
    };

    // ===== Lab Unit (การตรวจพิสูจน์) =====
    var sevpfLabUnitOptions = '<option value="" selected disabled>-- กรุณาเลือก --</option>' +
        '<option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>' +
        '<option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>' +
        '<option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>' +
        '<option value="drug">กลุ่มงานตรวจยาเสพติด</option>' +
        '<option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>' +
        '<option value="document">กลุ่มงานตรวจเอกสาร</option>' +
        '<option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>';
    // sevpfAddLabUnit kept as no-op for backward compatibility (lab_unit is now inline in evidence rows)
    window.sevpfAddLabUnit = function() { };
    window.sevpfRenumberCollectDetails = function() {
        var rows = document.querySelectorAll('#sevpf_collect_detail_container .sevpf-collect-row');
        rows.forEach(function(r, i) {
            var no = r.querySelector('.sevpf-si-no');
            if (no) no.textContent = '3.2.1.' + (i + 1);
        });
        sevpfCollectIdx = rows.length;
    };

    // ===== Photo source chooser (แนบรูป / ถ่ายรูป) =====
    window.sevpfChoosePhotoSource = function() {
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
                document.getElementById('sevpf_photo_input_gallery').click();
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                document.getElementById('sevpf_photo_input_camera').click();
            }
        });
    };

    // ===== Photo handling =====
    var SEVPF_PHOTOS_PER_PAGE = 35;

    window.sevpfPreviewPhotos = function(input) {
        if (!input.files || !input.files.length) return;
        sevpfHandlePhotoFiles(input.files);
        input.value = '';
    };

    function sevpfHandlePhotoFiles(files) {
        if (!files || !files.length) return;
        if (typeof attachmentStoreEV7 === 'undefined') window.attachmentStoreEV7 = [];
        Array.from(files).forEach(function(file) {
            if (!file.type.startsWith('image/')) return;
            var fileId = 'sevpf_' + Date.now() + '_' + Math.random().toString(36).substr(2,5);
            var objectUrl = URL.createObjectURL(file);
            attachmentStoreEV7.push({ file: file, id: fileId, src: objectUrl, name: file.name });
        });
        sevpfRenderPhotosFromStore();
    }

    // ===== Drag & Drop handlers =====
    var sevpfPhotoDZ = document.getElementById('sevpf_photo_dropzone');
    if (sevpfPhotoDZ) {
        sevpfPhotoDZ.addEventListener('click', function() { sevpfChoosePhotoSource(); });
        sevpfPhotoDZ.addEventListener('dragenter', function(e) { e.preventDefault(); this.classList.add('dragover'); });
        sevpfPhotoDZ.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('dragover'); });
        sevpfPhotoDZ.addEventListener('dragleave', function(e) { e.preventDefault(); this.classList.remove('dragover'); });
        sevpfPhotoDZ.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                sevpfHandlePhotoFiles(e.dataTransfer.files);
            }
        });
    }

    // ===== Render photos (35 per page grid) =====
    window.sevpfRenderPhotosFromStore = function() {
        var photos = (typeof attachmentStoreEV7 !== 'undefined') ? attachmentStoreEV7 : [];
        var pagesNeeded = Math.max(1, Math.ceil(photos.length / SEVPF_PHOTOS_PER_PAGE));
        
        console.log('[SEVPF] Rendering photos, count:', photos.length, 'pages:', pagesNeeded);

        // Render first page grid
        var grid1 = document.getElementById('sevpf_photo_grid_1');
        console.log('[SEVPF] Grid element:', grid1);
        if (grid1) {
            grid1.innerHTML = '';
            var endIdx = Math.min(SEVPF_PHOTOS_PER_PAGE, photos.length);
            console.log('[SEVPF] Rendering', endIdx, 'photos to grid');
            for (var i = 0; i < endIdx; i++) {
                grid1.appendChild(sevpfCreatePhotoCell(photos[i], i));
            }
        } else {
            console.error('[SEVPF] Grid element not found!');
        }

        // Render extra pages
        var extraContainer = document.getElementById('sevpf_extra_photo_pages');
        if (extraContainer) {
            extraContainer.innerHTML = '';
            for (var p = 2; p <= pagesNeeded; p++) {
                var startIdx = (p - 1) * SEVPF_PHOTOS_PER_PAGE;
                var endIdx = Math.min(p * SEVPF_PHOTOS_PER_PAGE, photos.length);
                extraContainer.appendChild(sevpfCreatePhotoPageElement(p, photos, startIdx, endIdx));
            }
        }

        sevpfUpdatePhotoAmount();
        if (typeof sevpfUpdatePageNumbers === 'function') sevpfUpdatePageNumbers();
    };

    function sevpfCreatePhotoCell(item, idx) {
        var wrapper = document.createElement('div');
        wrapper.style.cssText = 'display:flex; flex-direction:column;';
        
        var cell = document.createElement('div');
        cell.className = 'sevpf-photo-cell';
        cell.style.position = 'relative';
        
        var img = document.createElement('img');
        img.src = item.src || item.base64 || '';
        img.alt = item.name || 'photo';
        cell.appendChild(img);
        
        var delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'sevpf-cell-delete';
        delBtn.innerHTML = '&times;';
        delBtn.onclick = function(e) {
            e.stopPropagation();
            sevpfRemovePhoto(item.id);
        };
        cell.appendChild(delBtn);
        
        wrapper.appendChild(cell);
        
        var fname = document.createElement('div');
        fname.className = 'sevpf-cell-filename';
        fname.textContent = item.name || 'photo';
        fname.title = item.name || 'photo';
        wrapper.appendChild(fname);
        
        return wrapper;
    }

    function sevpfCreatePhotoPageElement(pageNum, photos, startIdx, endIdx) {
        var page = document.createElement('div');
        page.className = 'sevpf-page sevpf-photo-page';
        page.setAttribute('data-photo-page', pageNum);
        
        var startName = photos[startIdx] ? (photos[startIdx].name || 'photo') : '';
        var endName = photos[endIdx - 1] ? (photos[endIdx - 1].name || 'photo') : '';
        
        // Get report number and year
        var rptNo = document.getElementById('sevpf_report_no') ? document.getElementById('sevpf_report_no').value : '';
        var rptParts = (rptNo || '').split('/');
        var rptNum = rptParts[0] || '';
        var rptYear = (rptParts[1] || '').toString().slice(-2);
        
        page.innerHTML =
            '<div class="sevpf-header">' +
                '<div class="sevpf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>' +
                '<div class="sevpf-header-center">' +
                    '<div class="sevpf-title-main">รายงานการตรวจเก็บวัตถุพยาน</div>' +
                    '<div class="sevpf-title-sub">บันทึกการถ่ายภาพ (ต่อ)</div>' +
                '</div>' +
                '<div class="sevpf-header-right">' +
                    '<div class="sevpf-doc-box">' +
                        '<div class="sevpf-doc-line">เลขรับที่/เลขรายงาน <span class="sevpf-rpt-no-mirror">' + rptNum + '</span> / 25<span class="sevpf-rpt-year-mirror">' + rptYear + '</span></div>' +
                        '<div class="sevpf-doc-line">หน้าที่ <span class="sevpf-cur-page"></span> / <span class="sevpf-total-page"></span></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div style="margin-bottom:4px;">' +
                '<div class="sevpf-fr" style="flex-wrap:nowrap;">' +
                    '<span class="sevpf-fl">รหัสภาพถ่ายที่</span>' +
                    '<span class="sevpf-inp" style="flex:1; min-width:40px; text-align:center;">' + startName + '</span>' +
                    '<span class="sevpf-fl">ถึง</span>' +
                    '<span class="sevpf-inp" style="flex:1; min-width:40px; text-align:center;">' + endName + '</span>' +
                '</div>' +
                '<div style="font-size:10.5px; font-style:italic; margin-top:1px;">(ตามภาพถ่ายรวมที่แนบ)</div>' +
            '</div>' +
            '<div style="text-align:right; margin-bottom:4px;">' +
                '<button type="button" class="sevpf-del-btn" style="font-size:10px; padding:1px 8px;" onclick="sevpfRemovePhotoPage(this)">× ลบหน้านี้</button>' +
            '</div>';
        
        var grid = document.createElement('div');
        grid.className = 'sevpf-photo-grid';
        for (var i = startIdx; i < endIdx; i++) {
            grid.appendChild(sevpfCreatePhotoCell(photos[i], i));
        }
        page.appendChild(grid);
        
        var footer = document.createElement('div');
        footer.className = 'sevpf-footer';
        footer.style.marginTop = 'auto';
        footer.innerHTML =
            '<div class="sevpf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>' +
            '<div class="sevpf-footer-right">บัญชีแนบท้ายคำสั่ง สพฐ.ตร.<br>ที่ ๘๔๘/๒๕๖๑</div>';
        page.appendChild(footer);
        
        return page;
    }

    window.sevpfUpdatePhotoAmount = function() {
        var photos = (typeof attachmentStoreEV7 !== 'undefined' && Array.isArray(attachmentStoreEV7)) ? attachmentStoreEV7 : [];
        var totalPhotos = photos.length;
        
        // Scope to the Evidence modal only
        var modal = document.getElementById('sceneEvidenceFormPdfModal');
        if (!modal) return;

        var startInput = modal.querySelector('input[name="photo_id_start_ev7"]');
        var endInput = modal.querySelector('input[name="photo_id_end_ev7"]');
        var amountInput = modal.querySelector('input[name="photo_amount_ev7"]');

        if (startInput && endInput && amountInput) {
            if (totalPhotos > 0) {
                var lastIdxPage1 = Math.min(SEVPF_PHOTOS_PER_PAGE, totalPhotos) - 1;
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


    window.sevpfRemovePhoto = function(fileId) {
        if (typeof attachmentStoreEV7 === 'undefined') return;
        var item = attachmentStoreEV7.find(function(x) { return x.id === fileId; });
        if (item && item.existing) {
            if (typeof deletedExistingPhotosEV7 === 'undefined') window.deletedExistingPhotosEV7 = [];
            deletedExistingPhotosEV7.push({ file_id: item.db_file_id, db_filename: item.db_filename });
        }
        attachmentStoreEV7 = attachmentStoreEV7.filter(function(x) { return x.id !== fileId; });
        sevpfRenderPhotosFromStore();
    };

    // Auto-pagination handled by sevpfRenderPhotosFromStore

    window.sevpfRemovePhotoPage = function(btn) {
        var page = btn.closest('.sevpf-photo-page');
        if (page && page.parentElement.id === 'sevpf_extra_photo_pages') {
            page.remove();
            sevpfPhotoPageCount--;
            sevpfRenderPhotosFromStore();
            sevpfUpdatePageNumbers();
        }
    };

    // ===== Canvas drawing (ลายเซ็น) =====
    function initSevpfCanvas(canvasId) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;
        if (canvas.dataset.sevpfInit === '1') return;
        var ctx = canvas.getContext('2d');
        canvas.width = canvas.offsetWidth || canvas.parentElement.offsetWidth;
        canvas.height = canvas.offsetHeight || parseInt(canvas.style.height) || 60;

        var setClearedFlag = function(cId, val) {
            if (cId === 'sevpf_sig_receiver') {
                var receiverFlag = document.getElementById('sevpf_receiver_sig_cleared');
                if (receiverFlag) receiverFlag.value = val;
            }
            if (cId === 'sevpf_sig_sender') {
                var senderFlag = document.getElementById('sevpf_sender_sig_cleared');
                if (senderFlag) senderFlag.value = val;
            }
        };

        var drawing = false;
        var getPos = function(e) {
            var rect = canvas.getBoundingClientRect();
            var scaleX = canvas.width / rect.width;
            var scaleY = canvas.height / rect.height;
            if (e.touches) {
                return { x: (e.touches[0].clientX - rect.left) * scaleX, y: (e.touches[0].clientY - rect.top) * scaleY };
            }
            return { x: (e.clientX - rect.left) * scaleX, y: (e.clientY - rect.top) * scaleY };
        };

        canvas.addEventListener('mousedown', function(e) { drawing = true; setClearedFlag(canvasId, '0'); ctx.beginPath(); var p = getPos(e); ctx.moveTo(p.x, p.y); e.preventDefault(); });
        canvas.addEventListener('mousemove', function(e) { if (!drawing) return; var p = getPos(e); ctx.lineWidth = 1.5; ctx.lineCap = 'round'; ctx.strokeStyle = '#000'; ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); });
        canvas.addEventListener('mouseup', function() { drawing = false; });
        canvas.addEventListener('mouseleave', function() { drawing = false; });
        canvas.addEventListener('touchstart', function(e) { drawing = true; setClearedFlag(canvasId, '0'); ctx.beginPath(); var p = getPos(e); ctx.moveTo(p.x, p.y); e.preventDefault(); }, { passive: false });
        canvas.addEventListener('touchmove', function(e) { if (!drawing) return; var p = getPos(e); ctx.lineWidth = 1.5; ctx.lineCap = 'round'; ctx.strokeStyle = '#000'; ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }, { passive: false });
        canvas.addEventListener('touchend', function() { drawing = false; });
        canvas.dataset.sevpfInit = '1';
    }

    // Init canvas เมื่อ modal แสดง
    // Position auto-fill สำหรับ select ผู้ลงนาม
    document.querySelectorAll('#sceneEvidenceFormPdfModal .sevpf-user-select').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];
            var pos = opt ? opt.getAttribute('data-position') : '';
            var target = this.getAttribute('data-pos-target');
            if (target) {
                var el = document.querySelector(target);
                if (el) el.value = pos || '';
            }
        });
    });

    document.getElementById('sceneEvidenceFormPdfModal').addEventListener('shown.bs.modal', function() {
        console.log('[SEVPF] Modal shown event fired');
        console.log('[SEVPF] attachmentStoreEV7:', typeof attachmentStoreEV7 !== 'undefined' ? attachmentStoreEV7.length : 'undefined');
        
        var pdfSwitch = document.getElementById('switchToStdFormEV7');
        if (pdfSwitch) pdfSwitch.checked = true;
        initSevpfCanvas('sevpf_sig_receiver');
        initSevpfCanvas('sevpf_sig_sender');
        sevpfHydrateReportNoFromHidden(false);
        sevpfSyncCaseNoFromDocNo();
        sevpfSyncReportNo();
        if (typeof window.sevpfRefreshAutoWrapGroups === 'function') window.sevpfRefreshAutoWrapGroups();
        
        // Populate mirror spans for report number and year
        var rptNo = document.getElementById('sevpf_report_no') ? document.getElementById('sevpf_report_no').value : '';
        var rptParts = (rptNo || '').split('/');
        var rptNum = rptParts[0] || '';
        var rptYear = (rptParts[1] || '').toString().slice(-2);
        
        var modalEl = document.getElementById('sceneEvidenceFormPdfModal');
        if (modalEl) {
            modalEl.querySelectorAll('.sevpf-rpt-no-mirror').forEach(function(el) { el.textContent = rptNum; });
            modalEl.querySelectorAll('.sevpf-rpt-year-mirror').forEach(function(el) { el.textContent = rptYear; });
        }
        
        // Render photos from store (with delay to ensure data is loaded)
        setTimeout(function() {
            console.log('[SEVPF] Delayed render, attachmentStoreEV7:', typeof attachmentStoreEV7 !== 'undefined' ? attachmentStoreEV7.length : 'undefined');
            if (typeof sevpfRenderPhotosFromStore === 'function') {
                sevpfRenderPhotosFromStore();
            }
        }, 300);
        
        sevpfUpdatePageNumbers();
        console.log('[SEVPF] Modal initialization complete');
    });

    var stdModalEl = document.getElementById('addCheckListModalSceneEvidence');
    if (stdModalEl) {
        stdModalEl.addEventListener('shown.bs.modal', function() {
            var stdSwitch = document.getElementById('switchToPdfFormEV7');
            if (stdSwitch) stdSwitch.checked = false;
        });
    }

    window.sevpfClearCanvas = function(canvasId) {
        var canvas = document.getElementById(canvasId);
        if (canvas) {
            var ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
        if (canvasId === 'sevpf_sig_receiver') {
            var receiverHidden = document.getElementById('sevpf_receiver_sig_data');
            if (receiverHidden) receiverHidden.value = '';
            var receiverFlag = document.getElementById('sevpf_receiver_sig_cleared');
            if (receiverFlag) receiverFlag.value = '1';
        }
        if (canvasId === 'sevpf_sig_sender') {
            var senderHidden = document.getElementById('sevpf_sender_sig_data');
            if (senderHidden) senderHidden.value = '';
            var senderFlag = document.getElementById('sevpf_sender_sig_cleared');
            if (senderFlag) senderFlag.value = '1';
        }
    };

    // EV8-style behavior: one clear action clears both standard and PDF signature pads.
    window.clearEV7ReceiverSignatures = function() {
        if (typeof window.clearSignature === 'function') {
            window.clearSignature('sig-canvas-ev7-receiver');
        }
        window.sevpfClearCanvas('sevpf_sig_receiver');
    };

    window.clearEV7SenderSignatures = function() {
        if (typeof window.clearSignature === 'function') {
            window.clearSignature('sig-canvas-ev7-sender');
        }
        window.sevpfClearCanvas('sevpf_sig_sender');
    };

    // ===== Switch ฟอร์ม =====
    window.switchToSceneEvidencePdfForm = function() {
        syncSceneEvidenceFormData('incidentCheckListFormSceneEvidence', 'sceneEvidenceFormPdf');

        // sync hidden fields: standard → PDF
        var docNo = $('#doc_no_ev7').val() || '';
        var rptNo = $('#report_no_ev7').val() || '';
        $('#sevpf_receiveNoti_id').val($('#receiveNoti_id_ev7').val());
        $('#sevpf_doc_no').val(docNo);
        $('#sevpf_report_no').val(rptNo);
        sevpfHydrateReportNoFromHidden(false);
        sevpfSyncCaseNoFromDocNo();

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

        // sync hidden fields: PDF → standard
        var docNo = $('#sevpf_doc_no').val() || '';
        var rptNo = $('#sevpf_report_no').val() || '';
        $('#receiveNoti_id_ev7').val($('#sevpf_receiveNoti_id').val());
        $('#doc_no_ev7').val(docNo);
        $('#report_no_ev7').val(rptNo);
        $('#receiveNoti_No_ev7').text(docNo);

        var pdfModal = bootstrap.Modal.getInstance(document.getElementById('sceneEvidenceFormPdfModal'));
        if (pdfModal) pdfModal.hide();
        var pdfSwitch = document.getElementById('switchToStdFormEV7');
        if (pdfSwitch) pdfSwitch.checked = true;
        setTimeout(function() {
            var stdModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('addCheckListModalSceneEvidence'));
            var stdSwitch = document.getElementById('switchToPdfFormEV7');
            if (stdSwitch) stdSwitch.checked = false;
            stdModal.show();
        }, 400);
    };

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
                var val = value == null ? '' : value;
                document.querySelectorAll(selector).forEach(function(el) {
                    el.value = val;
                });
            };
            var setText = function(selector, value) {
                document.querySelectorAll(selector).forEach(function(el) {
                    el.textContent = value || '';
                });
            };
            var collectChecked = function(name, scope) {
                return Array.prototype.slice.call((scope || document).querySelectorAll('[name="' + name + '"]:checked')).map(function(el) {
                    return el.value;
                });
            };
            var setCheckedValues = function(name, values, scope) {
                var valueList = Array.isArray(values) ? values : [];
                (scope || document).querySelectorAll('[name="' + name + '"]').forEach(function(el) {
                    el.checked = valueList.indexOf(el.value) !== -1;
                });
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
                    var lastRow = rows[rows.length - 1];
                    if (!lastRow) break;
                    lastRow.remove();
                }
                if (typeof config.renumber === 'function') {
                    config.renumber();
                } else if (typeof window[config.renumberFn] === 'function') {
                    window[config.renumberFn]();
                }
            };
            var syncDynamicValues = function(sourceSelector, targetSelector, targetConfig) {
                var values = Array.prototype.slice.call(document.querySelectorAll(sourceSelector)).map(function(el) {
                    return el.value || '';
                });
                ensureDynamicRows(targetConfig, values.length || 1);
                var targetInputs = document.querySelectorAll(targetSelector);
                targetInputs.forEach(function(el, idx) {
                    el.value = values[idx] || '';
                });
            };
            var syncNotifyMethods = function() {
                if (fromFormId === 'incidentCheckListFormSceneEvidence') {
                    var stdValues = collectChecked('ev7_notify_method[]', fromForm);
                    var pdfValues = [];
                    var stdOtherText = pickValue(['#ev7_notify_other_text']).trim();
                    if (!stdOtherText) stdOtherText = pickValue(['#sevpf_notify_method_other_text']).trim();
                    stdValues.forEach(function(value) {
                        if (value === 'ตามหนังสือ') pdfValues.push('ทางหนังสือ');
                        else if (value === 'ทางโทรศัพท์') pdfValues.push('ทางโทรศัพท์');
                        else if (value === 'ทางวิทยุสื่อสาร') pdfValues.push('ทางวิทยุสื่อสาร');
                        else if (value === 'อื่นๆ') {
                            pdfValues.push('อื่นๆ');
                        }
                    });
                    setCheckedValues('sevpf_notify_method[]', pdfValues, toForm);
                    setValue('#sevpf_notify_method_other_text', stdOtherText);
                } else {
                    var sourcePdfValues = collectChecked('sevpf_notify_method[]', fromForm);
                    var stdMapped = [];
                    var stdOtherText = pickValue(['#sevpf_notify_method_other_text']).trim();
                    if (!stdOtherText) stdOtherText = pickValue(['#ev7_notify_other_text']).trim();
                    sourcePdfValues.forEach(function(value) {
                        if (value === 'ทางหนังสือ') stdMapped.push('ตามหนังสือ');
                        else if (value === 'ทางโทรศัพท์') stdMapped.push('ทางโทรศัพท์');
                        else if (value === 'ทางวิทยุสื่อสาร') stdMapped.push('ทางวิทยุสื่อสาร');
                        else if (value === 'อื่นๆ') stdMapped.push('อื่นๆ');
                    });
                    setCheckedValues('ev7_notify_method[]', stdMapped, toForm);
                    setValue('#ev7_notify_other_text', stdOtherText);
                    var otherChecked = stdMapped.indexOf('อื่นๆ') !== -1;
                    $('#ev7_notify_other_text').toggle(otherChecked).prop('disabled', !otherChecked);
                    if (!otherChecked) $('#ev7_notify_other_text').val('');
                }
            };
            var syncUnitType = function() {
                if (fromFormId === 'incidentCheckListFormSceneEvidence') {
                    var stdType = pickValue(['#ev7_unit_type']);
                    var unitName = pickValue(['#ev7_unit_name']);
                    setCheckedValues('sevpf_unit_type_check[]', stdType ? [stdType] : [], toForm);
                    setValue('#sevpf_unit_name', unitName);
                    setValue('[name="sevpf_center_name"]', stdType === 'ศูนย์พิสูจน์หลักฐาน' ? unitName : '');
                    setValue('[name="sevpf_province_name"]', stdType === 'พิสูจน์หลักฐานจังหวัด' ? unitName : '');
                } else {
                    var pdfType = collectChecked('sevpf_unit_type_check[]', fromForm)[0] || '';
                    var resolvedName = pickValue([
                        pdfType === 'ศูนย์พิสูจน์หลักฐาน' ? '[name="sevpf_center_name"]' : '',
                        pdfType === 'พิสูจน์หลักฐานจังหวัด' ? '[name="sevpf_province_name"]' : '',
                        '#sevpf_unit_name'
                    ].filter(Boolean));
                    setValue('#ev7_unit_type', pdfType);
                    setValue('#ev7_unit_name', resolvedName);
                }
            };
            var syncHandoverMethod = function() {
                if (fromFormId === 'incidentCheckListFormSceneEvidence') {
                    var method = pickValue(['#ev7_handover_method']);
                    setCheckedValues('sevpf_handover_method_check[]', method ? [method] : [], toForm);
                } else {
                    var checked = collectChecked('sevpf_handover_method_check[]', fromForm);
                    setValue('#ev7_handover_method', checked[0] || '');
                }
            };
            var syncNextAction = function() {
                if (fromFormId === 'incidentCheckListFormSceneEvidence') {
                    var stdNextAction = pickValue(['#ev7_handover_next_action']);
                    setValue('#sevpf_handover_next_action', stdNextAction);
                    document.querySelectorAll('[name^="sevpf_handover_more_"]').forEach(function(el) {
                        el.value = '';
                    });
                    if (typeof window.sevpfRefreshAutoWrapGroups === 'function') window.sevpfRefreshAutoWrapGroups();
                } else {
                    var pdfNextAction = Array.prototype.slice.call(document.querySelectorAll('[name="sevpf_handover_next_action"], [name^="sevpf_handover_more_"]')).map(function(el) {
                        return el.value || '';
                    }).join('\n').replace(/\n+$/g, '');
                    setValue('#ev7_handover_next_action', pdfNextAction);
                }
            };

            syncSimpleField('#receiveNoti_id_ev7', '#sevpf_receiveNoti_id');
            syncSimpleField('#doc_no_ev7', '#sevpf_doc_no');
            syncSimpleField('#report_no_ev7', '#sevpf_report_no');
            syncSimpleField('#ev7_receive_date', '#sevpf_receive_date');
            syncSimpleField('#ev7_receive_time', '#sevpf_receive_time');
            syncSimpleField('#ev7_unit_name', '#sevpf_unit_name');
            syncSimpleField('#ev7_police_station', '#sevpf_police_station');
            syncSimpleField('#ev7_document_no', '#sevpf_document_no');
            syncSimpleField('#ev7_document_date', '#sevpf_document_date');
            syncSimpleField('#ev7_case_no', '#sevpf_case_no');
            syncSimpleField('#ev7_incident_location', '#sevpf_incident_location');
            syncSimpleField('#ev7_incident_date', '#sevpf_incident_date');
            syncSimpleField('#ev7_incident_time', '#sevpf_incident_time');
            syncSimpleField('#ev7_investigator_name', '#sevpf_investigator_name');
            syncSimpleField('#ev7_send_request', '#sevpf_send_request');
            syncSimpleField('#ev7_purpose_detail', '#sevpf_purpose_detail');
            syncSimpleField('#ev7_inspect_location', '#sevpf_inspect_location');
            syncSimpleField('#ev7_inspect_date', '#sevpf_inspect_date');
            syncSimpleField('#ev7_inspect_time', '#sevpf_inspect_time');
            syncSimpleField('#ev7_collect_sheet_count', '#sevpf_collect_sheet_count');
            syncSimpleField('#ev7_collect_location', '#sevpf_collect_location');
            syncSimpleField('#ev7_witness_name', '#sevpf_witness_name');
            syncSimpleField('#ev7_witness_form', '#sevpf_witness_form');
            syncSimpleField('#ev7_witness_detail', '#sevpf_witness_detail');
            syncSimpleField('#ev7_handover_method_detail', '#sevpf_handover_method_detail');
            syncSimpleField('#ev7_handover_item_ref', '#sevpf_handover_item_ref');
            syncSimpleField('#ev7_handover_to', '#sevpf_handover_to');
            syncSimpleField('#ev7_handover_purpose', '#sevpf_handover_purpose');
            syncSimpleField('#ev7_receiver_id', '#sevpf_receiver_id');
            syncSimpleField('#ev7_receiver_position', '#sevpf_receiver_position');
            syncSimpleField('#ev7_sender_id', '#sevpf_sender_id');
            syncSimpleField('#ev7_sender_position', '#sevpf_sender_position');
            syncSimpleField('#ev7_signer_id', '#sevpf_signer_id_proxy');
            syncSimpleField('#ev7_signer_position', '#sevpf_signer_position_proxy');
            syncSimpleField('#ev7_sign_date', '#sevpf_sign_date_proxy');
            syncSimpleField('#ev7_photo_id_start', '[name="photo_id_start_ev7"]');
            syncSimpleField('#ev7_photo_id_end', '[name="photo_id_end_ev7"]');
            syncSimpleField('#ev7_photo_amount', '[name="photo_amount_ev7"]');
            syncSimpleField('#ev7_photographer_name', '#sevpf_photographer_name');
            syncSimpleField('#ev7_photographer_datetime', '#sevpf_photographer_datetime');

            // Incident location can wrap into two PDF lines; merge back when syncing to standard.
            if (fromFormId === 'sceneEvidenceFormPdf' && toStdForm) {
                var loc1 = pickValue(['#sevpf_incident_location']).trim();
                var loc2 = pickValue(['[name="sevpf_incident_location_2"]']).trim();
                var mergedLocation = [loc1, loc2].filter(Boolean).join(' ').trim();
                if (mergedLocation) {
                    setValue('#ev7_incident_location', mergedLocation);
                }
            }

            setText(toStdForm ? '#receiveNoti_No_ev7' : '#sevpf_receiveNoti_No', pickValue([fromFormId === 'incidentCheckListFormSceneEvidence' ? '#doc_no_ev7' : '#sevpf_doc_no']));
            setText(toStdForm ? '#receiveNotiReportNo_ev7' : '#sevpf_receiveNotiReportNo', pickValue([fromFormId === 'incidentCheckListFormSceneEvidence' ? '#report_no_ev7' : '#sevpf_report_no']));

            if (fromFormId === 'incidentCheckListFormSceneEvidence') {
                setCheckedValues('sevpf_purpose[]', collectChecked('ev7_purpose[]', fromForm), toForm);
                setCheckedValues('sevpf_collect_type[]', collectChecked('ev7_collect_type[]', fromForm), toForm);
            } else {
                setCheckedValues('ev7_purpose[]', collectChecked('sevpf_purpose[]', fromForm), toForm);
                setCheckedValues('ev7_collect_type[]', collectChecked('sevpf_collect_type[]', fromForm), toForm);
            }

            syncNotifyMethods();
            syncUnitType();
            syncHandoverMethod();
            syncNextAction();

            syncDynamicValues(
                fromFormId === 'incidentCheckListFormSceneEvidence' ? '[name="ev7_evidence_item[]"]' : '[name="sevpf_evidence_item[]"]',
                toStdForm ? '[name="ev7_evidence_item[]"]' : '[name="sevpf_evidence_item[]"]',
                toStdForm
                    ? { containerSelector: '#ev7_evidence_items_container', rowSelector: '.ev7-evidence-item-row', addFn: 'addEvidenceItemEV7', renumber: function() { if (typeof window.renumberEV7Rows === 'function') window.renumberEV7Rows('#ev7_evidence_items_container', '1.'); } }
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
            if (fromFormId === 'incidentCheckListFormSceneEvidence') {
                setValue('#sevpf_other_evidence_text', Array.prototype.slice.call(document.querySelectorAll('[name="ev7_other_evidence[]"]')).map(function(el) {
                    return (el.value || '').trim();
                }).filter(Boolean).join(', '));
            } else {
                var parsedOtherEvidence = pickValue(['#sevpf_other_evidence_text']).split(/\n|,/).map(function(text) {
                    return text.trim();
                }).filter(Boolean);
                ensureDynamicRows({
                    containerSelector: '#ev7_other_evidence_container',
                    rowSelector: '.ev7-other-evidence-row',
                    addFn: 'addOtherEvidenceEV7'
                }, parsedOtherEvidence.length || 1);
                document.querySelectorAll('[name="ev7_other_evidence[]"]').forEach(function(el, idx) {
                    el.value = parsedOtherEvidence[idx] || '';
                });
            }

            // ===== Lab Unit (การตรวจพิสูจน์) sync — inline ในแถวของกลาง, รองรับหลายค่า =====
            (function() {
                // hidden input ถือค่าจริง (comma-separated) — sync จาก <select multiple> ก่อน
                if (window.LabUnitMulti) window.LabUnitMulti.syncAll(document);
                var srcSel  = fromFormId === 'incidentCheckListFormSceneEvidence' ? 'input.lab-unit-value[name="ev7_lab_unit[]"]' : 'input.lab-unit-value[name="sevpf_lab_unit[]"]';
                var tgtSel  = toStdForm ? 'input.lab-unit-value[name="ev7_lab_unit[]"]' : 'input.lab-unit-value[name="sevpf_lab_unit[]"]';
                var srcEls  = document.querySelectorAll(srcSel);
                var tgtEls  = document.querySelectorAll(tgtSel);
                srcEls.forEach(function(el, idx) {
                    var tgt = tgtEls[idx];
                    if (!tgt) return;
                    var val = window.LabUnitMulti ? window.LabUnitMulti.join(el.value) : (el.value || '');
                    tgt.value = val;
                    var row = tgt.parentNode;
                    var sel = row ? row.querySelector('select.lab-unit-multi') : null;
                    if (sel && window.LabUnitMulti) window.LabUnitMulti.setValue(sel, val);
                });
            })();

            syncDynamicValues(
                fromFormId === 'incidentCheckListFormSceneEvidence' ? '#ev7_inspector_container .ev7-inspector-select' : '#sevpf_inspector_container select[name="ev7_inspector_id[]"]',
                toStdForm ? '#ev7_inspector_container .ev7-inspector-select' : '#sevpf_inspector_container select[name="ev7_inspector_id[]"]',
                toStdForm
                    ? { containerSelector: '#ev7_inspector_container', rowSelector: '.ev7-inspector-row', addFn: 'sceneEvidenceStdAddInspectorProxy', renumberFn: 'sceneEvidenceStdRenumberInspectors' }
                    : { containerSelector: '#sevpf_inspector_container', rowSelector: '.sevpf-inspector-row', addFn: 'sevpfAddInspector', renumberFn: 'sevpfRenumberInspectors' }
            );

            if (typeof window.sevpfSyncReportNo === 'function') window.sevpfSyncReportNo();
            if (typeof window.sevpfSyncCaseNoFromDocNo === 'function') window.sevpfSyncCaseNoFromDocNo();
            if (typeof window.sevpfRefreshAutoWrapGroups === 'function') window.sevpfRefreshAutoWrapGroups();
            if (typeof window.sevpfUpdatePhotoAmount === 'function') window.sevpfUpdatePhotoAmount();
        } finally {
            window.__sceneEvidenceSyncing = false;
        }
    };

    window.sceneEvidenceStdAddInspectorProxy = function() {
        var btn = document.getElementById('btn_add_inspector_ev7');
        if (btn) btn.click();
    };

    window.sceneEvidenceStdRenumberInspectors = function() {
        var rows = document.querySelectorAll('#ev7_inspector_container .ev7-inspector-row');
        rows.forEach(function(row, idx) {
            var label = row.querySelector('.ev7-index-label');
            if (label) label.textContent = '4.' + (idx + 1);
        });
        if (typeof window.ev7InspectorIdx !== 'undefined') window.ev7InspectorIdx = rows.length;
    };

    window.resetSceneEvidencePdfForm = function() {
        var form = document.getElementById('sceneEvidenceFormPdf');
        if (form) form.reset();
        document.querySelectorAll('#sceneEvidenceFormPdfModal .sevpf-rpt-no-mirror, #sceneEvidenceFormPdfModal .sevpf-rpt-year-mirror').forEach(function(el) {
            el.textContent = '';
        });
        [['#sevpf_evidence_items_container', '.sevpf-evidence-item-row', 'sevpfRenumberEvidenceItems'], ['#sevpf_exhibit_desc_container', '.sevpf-exhibit-row', 'sevpfRenumberExhibits'], ['#sevpf_collect_detail_container', '.sevpf-collect-row', 'sevpfRenumberCollectDetails'], ['#sevpf_inspector_container', '.sevpf-inspector-row', 'sevpfRenumberInspectors']].forEach(function(config) {
            var container = document.querySelector(config[0]);
            if (!container) return;
            var rows = container.querySelectorAll(config[1]);
            rows.forEach(function(row, idx) {
                if (idx > 0) row.remove();
            });
            if (typeof window[config[2]] === 'function') window[config[2]]();
            container.querySelectorAll('input, select, textarea').forEach(function(el) {
                if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
                else el.value = '';
            });
        });
        document.querySelectorAll('#sceneEvidenceFormPdfModal input[type="checkbox"]').forEach(function(el) { el.checked = false; });
        ['sevpf_sig_receiver', 'sevpf_sig_sender', 'sig-canvas-ev7-receiver', 'sig-canvas-ev7-sender'].forEach(function(canvasId) { window.sevpfClearCanvas(canvasId); });
        var receiverFlag = document.getElementById('sevpf_receiver_sig_cleared');
        if (receiverFlag) receiverFlag.value = '0';
        var senderFlag = document.getElementById('sevpf_sender_sig_cleared');
        if (senderFlag) senderFlag.value = '0';
        var extraPages = document.getElementById('sevpf_extra_photo_pages');
        if (extraPages) extraPages.innerHTML = '';
        sevpfPhotoPageCount = 1;
        if (typeof window.sevpfRenderPhotosFromStore === 'function') window.sevpfRenderPhotosFromStore();
        if (typeof window.sevpfUpdatePageNumbers === 'function') window.sevpfUpdatePageNumbers();
        if (typeof window.sevpfRefreshAutoWrapGroups === 'function') window.sevpfRefreshAutoWrapGroups();
    };

    $(document).on('input change', '#incidentCheckListFormSceneEvidence input, #incidentCheckListFormSceneEvidence select, #incidentCheckListFormSceneEvidence textarea, #sceneEvidenceFormPdf input, #sceneEvidenceFormPdf select, #sceneEvidenceFormPdf textarea', function() {
        var form = $(this).closest('form').attr('id');
        if (!form || window.__sceneEvidenceSyncing) return;
        var targetForm = form === 'incidentCheckListFormSceneEvidence' ? 'sceneEvidenceFormPdf' : 'incidentCheckListFormSceneEvidence';
        window.syncSceneEvidenceFormData(form, targetForm);
    });

    $(document).on('click', '#incidentCheckListFormSceneEvidence button, #sceneEvidenceFormPdf button', function() {
        var form = $(this).closest('form').attr('id');
        if (!form) return;
        setTimeout(function() {
            if (window.__sceneEvidenceSyncing) return;
            var targetForm = form === 'incidentCheckListFormSceneEvidence' ? 'sceneEvidenceFormPdf' : 'incidentCheckListFormSceneEvidence';
            window.syncSceneEvidenceFormData(form, targetForm);
        }, 0);
    });

    $(document).on('click', '#switchToPdfFormEV7', function(e) {
        e.preventDefault();
        this.checked = false;
        window.switchToSceneEvidencePdfForm();
    });

    $(document).on('click', '#switchToStdFormEV7', function(e) {
        e.preventDefault();
        this.checked = true;
        window.switchToSceneEvidenceStdForm();
    });

    var sevpfSyncCaseNoFromDocNo = function() {
        var docNoEl = document.getElementById('sevpf_doc_no');
        var caseNoEl = document.getElementById('sevpf_case_no');
        if (!docNoEl || !caseNoEl) return;
        var docNo = (docNoEl.value || '').toString().trim();
        if (!docNo) return;
        caseNoEl.value = (window.toThaiDocNo ? toThaiDocNo(docNo) : docNo);
    };
    window.sevpfSyncCaseNoFromDocNo = sevpfSyncCaseNoFromDocNo;

    // ===== Generic auto-wrap for multi-line input groups =====
    (function initSevpfAutoWrapGroups() {
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d');
        if (!ctx) return;
        var refreshers = [];

        function getFont(el) {
            var st = window.getComputedStyle(el);
            return st.font || [st.fontStyle, st.fontVariant, st.fontWeight, st.fontSize + '/' + st.lineHeight, st.fontFamily].join(' ');
        }

        function splitToFit(text, el) {
            if (!text) return { fit: '', rest: '' };
            ctx.font = getFont(el);
            var padding = 8;
            var maxWidth = Math.max(20, el.clientWidth - padding);
            var fit = '';
            var i;
            for (i = 0; i < text.length; i++) {
                var next = fit + text.charAt(i);
                if (ctx.measureText(next).width > maxWidth) break;
                fit = next;
            }
            return { fit: fit, rest: text.slice(i) };
        }

        function bindGroup(selector) {
            var inputs = Array.prototype.slice.call(document.querySelectorAll(selector));
            if (!inputs.length) return;

            function distribute(rawText) {
                var remaining = rawText || '';
                inputs.forEach(function(el, idx) {
                    if (!remaining) {
                        el.value = '';
                        return;
                    }
                    if (idx === inputs.length - 1) {
                        el.value = remaining;
                        remaining = '';
                        return;
                    }
                    var parts = splitToFit(remaining, el);
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

            refreshers.push(function() {
                distribute(collectAllText());
            });

            window.addEventListener('resize', function() {
                distribute(collectAllText());
            });
        }

        bindGroup('#sceneEvidenceFormPdfModal .sevpf-33-auto-line');
        bindGroup('#sceneEvidenceFormPdfModal .sevpf-auto-line-location');

        window.sevpfRefreshAutoWrapGroups = function() {
            refreshers.forEach(function(refresh) { refresh(); });
        };
    })();
})();
</script>
