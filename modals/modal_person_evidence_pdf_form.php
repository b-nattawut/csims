<?php
/**
 * Modal: ฟอร์ม PDF ตรวจเก็บวัตถุพยานที่บุคคล (แบบเสมือนจริง)
 * complaints_type = '08'
 * Prefix: pepf_ (Person Evidence PDF Form)
 * UI pattern: อิงตาม modal_scene_evidence_pdf_form.php
 */

$pepfPoliceStationOptions = '<option value="" selected disabled>กรุณาเลือก</option>';
if (isset($pdo)) {
    $qryPepfPS = "SELECT * FROM master_police_station ORDER BY id DESC";
    $stmtPepfPS = $pdo->query($qryPepfPS);
    while ($rowPS = $stmtPepfPS->fetch(PDO::FETCH_ASSOC)) {
        $pepfPoliceStationOptions .= '<option value="' . htmlspecialchars($rowPS['station_name']) . '">' . htmlspecialchars($rowPS['station_name']) . '</option>';
    }
}

$pepfInspectorOptions = '<option value="" selected disabled>-- เลือก --</option>';
if (isset($pdo)) {
    $qryPepfInsp = "SELECT t1.user_id,
                           CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname,
                           IFNULL(t3.position_name, '-') AS position_name
                    FROM user_profile t1
                    LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                    LEFT JOIN user_position t3 ON t1.position_id = t3.position_id
                    ORDER BY t1.user_id DESC";
    $stmtPepfInsp = $pdo->query($qryPepfInsp);
    while ($rowInsp = $stmtPepfInsp->fetch(PDO::FETCH_ASSOC)) {
        $pepfInspectorOptions .= '<option value="' . $rowInsp['user_id'] . '" data-position="' . htmlspecialchars($rowInsp['position_name']) . '" data-fullname="' . htmlspecialchars($rowInsp['fullname']) . '">' . htmlspecialchars($rowInsp['fullname']) . '</option>';
    }
}

$pepfTodayDate = date('Y-m-d');
$pepfTodayTime = date('H:i');
?>

<!-- ===== SCOPED CSS ===== -->
<style>
#personEvidenceFormPdfModal .pepf-rpt-no-mirror,
#personEvidenceFormPdfModal .pepf-rpt-year-mirror {
    display: inline-block;
    border-bottom: 1px dotted #888;
    text-align: center;
}
#personEvidenceFormPdfModal .pepf-rpt-no-mirror { min-width: 60px; }
#personEvidenceFormPdfModal .pepf-rpt-year-mirror { min-width: 30px; }

#personEvidenceFormPdfModal .pepf-body {
    background: #bbb;
    padding: 10px 0;
}

#personEvidenceFormPdfModal .pepf-page {
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
    font-size: 11px;
    line-height: 1.28;
    color: #000;
}

/* ให้หน้าเสมือนกว้างขึ้นเล็กน้อยบนหน้าจอ เพื่อไม่ให้ข้อความตก */
@media screen {
    #personEvidenceFormPdfModal .pepf-page {
        width: 214mm;
    }
}

@media (max-width: 991.98px) {
    #personEvidenceFormPdfModal .pepf-page {
        width: calc(100vw - 36px);
        min-width: 760px;
        margin: 8px auto;
    }
}

/* ===== HEADER ===== */
#personEvidenceFormPdfModal .pepf-header {
    position: relative;
    margin-bottom: 6px;
    height: 70px;
}
#personEvidenceFormPdfModal .pepf-header-logo {
    position: absolute; left: 0; top: -5px; width: 70px; height: 70px;
}
#personEvidenceFormPdfModal .pepf-header-logo img {
    width: 70px; height: 70px; object-fit: contain;
}
#personEvidenceFormPdfModal .pepf-header-center {
    position: absolute; left: 80px; right: 180px; top: 8px; text-align: center;
}
#personEvidenceFormPdfModal .pepf-header-center .pepf-title-main {
    font-size: 12.5px; font-weight: 700; letter-spacing: 0.2px; margin-bottom: 4px;
}
#personEvidenceFormPdfModal .pepf-header-center .pepf-title-sub {
    font-size: 10px; font-weight: 600; margin-top: 2px;
}
#personEvidenceFormPdfModal .pepf-header-right {
    position: absolute; right: 0; top: 7px;
}
#personEvidenceFormPdfModal .pepf-doc-box {
    border: 1.5px solid #000; padding: 2px 6px; font-size: 10px; white-space: nowrap;
}
#personEvidenceFormPdfModal .pepf-doc-box .pepf-doc-line { line-height: 1.5; }

/* ===== BODY TABLE (2-column) ===== */
#personEvidenceFormPdfModal .pepf-form-body {
    display: flex; border: 1.5px solid #000; align-items: stretch;
}
#personEvidenceFormPdfModal .pepf-col-left {
    width: 50%; border-right: 1.5px solid #000; display: flex; flex-direction: column;
    min-width: 0; overflow: hidden;
}
#personEvidenceFormPdfModal .pepf-col-right {
    width: 50%; display: flex; flex-direction: column;
    min-width: 0; overflow: hidden;
}

/* ===== ROW HEADER ===== */
#personEvidenceFormPdfModal .pepf-row-header {
    display: flex; border-bottom: 1px solid #000;
    font-weight: 700; font-size: 10px; text-align: center; background: transparent;
}
#personEvidenceFormPdfModal .pepf-row-header .pepf-lbl-seq {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000; padding: 1px 2px;
}
#personEvidenceFormPdfModal .pepf-row-header .pepf-lbl-data {
    flex: 1; padding: 1px 2px;
}

/* ===== SECTION ROW ===== */
#personEvidenceFormPdfModal .pepf-sec-row {
    display: flex; border-bottom: 1px solid #000;
}
#personEvidenceFormPdfModal .pepf-sec-row:last-child { border-bottom: none; }
#personEvidenceFormPdfModal .pepf-sec-label {
    min-width: 48px; max-width: 48px; border-right: 1px solid #000;
    padding: 2px 2px; font-weight: 700; font-size: 10px; text-align: center;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
#personEvidenceFormPdfModal .pepf-sec-label .pepf-sec-num {
    font-size: 11px; font-weight: 700; line-height: 1.15;
}
#personEvidenceFormPdfModal .pepf-sec-label .pepf-sec-txt {
    font-size: 8.8px; font-weight: 600; line-height: 1.1; text-align: center; margin-top: 1px;
}
#personEvidenceFormPdfModal .pepf-sec-body {
    flex: 1; padding: 4px 5px; font-size: 10px;
    min-width: 0; overflow: visible; box-sizing: border-box;
}

/* ===== FIELD ROW ===== */
#personEvidenceFormPdfModal .pepf-fr {
    display: flex; flex-wrap: wrap; align-items: center; margin-bottom: 4px; line-height: 1.55;
    max-width: 100%; box-sizing: border-box; gap: 2px 6px;
}
#personEvidenceFormPdfModal .pepf-fl {
    font-size: 9.8px; white-space: normal; margin-right: 2px; line-height: 1.2;
}
#personEvidenceFormPdfModal .pepf-fl-b {
    font-size: 9.8px; font-weight: 600; white-space: normal; margin-right: 2px; line-height: 1.2;
}

/* ===== INPUT FIELDS ===== */
#personEvidenceFormPdfModal .pepf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 9.8px;
    padding: 0 1px; height: 19px; outline: none; color: #000;
    flex: 1; min-width: 20px; max-width: 100%; margin: 0 2px; box-sizing: border-box;
}
#personEvidenceFormPdfModal .pepf-inp-m {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 9.8px;
    padding: 0 1px; height: 19px; outline: none; color: #000;
    flex: 1; min-width: 60px; margin: 0 2px; text-align: center;
}
#personEvidenceFormPdfModal .pepf-inp-full {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 9.8px;
    padding: 0 1px; height: 19px; outline: none; color: #000;
    width: 100%; display: block; margin-bottom: 3px;
}
#personEvidenceFormPdfModal .pepf-inp-s {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 9.5px;
    padding: 0 1px; height: 19px; outline: none; color: #000;
    min-width: 15px; max-width: 50px; margin: 0 2px; flex: 0 1 40px; text-align: center;
}

#personEvidenceFormPdfModal .pepf-date-fixed {
    flex: 0 0 104px;
    min-width: 104px;
    max-width: 104px;
}

#personEvidenceFormPdfModal .pepf-time-fixed {
    flex: 0 0 86px;
    min-width: 86px;
    max-width: 86px;
    text-align: center;
}

/* Keep date/time rows on a single line to avoid dropped words */
#personEvidenceFormPdfModal .pepf-dt-row {
    flex-wrap: nowrap;
    gap: 2px;
}
#personEvidenceFormPdfModal .pepf-dt-row .pepf-fl {
    white-space: nowrap;
    flex: 0 0 auto;
    margin-right: 2px;
}
#personEvidenceFormPdfModal .pepf-dt-row .pepf-date-fixed {
    flex: 0 0 100px;
    min-width: 100px;
    max-width: 100px;
}
#personEvidenceFormPdfModal .pepf-dt-row .pepf-time-fixed {
    flex: 0 0 82px;
    min-width: 82px;
    max-width: 82px;
}
#personEvidenceFormPdfModal .pepf-dt-row-long .pepf-fl {
    font-size: 9.8px;
}

/* SELECT styled like dotted line */
#personEvidenceFormPdfModal .pepf-sel {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 9.6px;
    padding: 0; height: 19px; outline: none; color: #000;
    flex: 1; min-width: 0; margin: 0 2px; cursor: pointer; max-width: 100%;
}

/* TEXTAREA styled like dotted lines */
#personEvidenceFormPdfModal .pepf-ta {
    border: none; background: transparent;
    font-family: 'Sarabun', sans-serif; font-size: 9.8px;
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
#personEvidenceFormPdfModal .pepf-cb {
    appearance: none; -webkit-appearance: none;
    width: 13px; height: 13px; border: 1.5px solid #000;
    margin-right: 3px; cursor: pointer; position: relative;
    vertical-align: middle; flex-shrink: 0; background: #fff;
}
#personEvidenceFormPdfModal .pepf-cb:checked::after {
    content: '✓'; font-size: 12px; font-weight: 700;
    position: absolute; top: -3px; left: 0px; color: #000;
}

/* Checkbox label */
#personEvidenceFormPdfModal .pepf-ck {
    display: inline-flex; align-items: center; margin-right: 14px;
    font-size: 9.8px; white-space: normal; vertical-align: middle; cursor: pointer;
}

/* Filled square bullet */
#personEvidenceFormPdfModal .pepf-bk {
    width: 10px; height: 10px; background: #000;
    display: inline-block; margin-right: 3px; flex-shrink: 0;
    position: relative; top: 1px;
}

/* Bullet header */
#personEvidenceFormPdfModal .pepf-bh {
    display: flex; align-items: center; font-weight: 600;
    font-size: 10px; margin-top: 6px; margin-bottom: 4px;
}

/* Sub-items */
#personEvidenceFormPdfModal .pepf-si {
    display: flex; align-items: center; font-size: 10px; line-height: 1.5; margin-bottom: 4px;
    max-width: 100%; box-sizing: border-box;
}
#personEvidenceFormPdfModal .pepf-si-no {
    min-width: 25px; padding-left: 6px; font-size: 10px;
}

/* Checkbox group */
#personEvidenceFormPdfModal .pepf-cg {
    display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; margin-bottom: 5px;
}

/* Indents */
#personEvidenceFormPdfModal .pepf-i1 { padding-left: 15px; }
#personEvidenceFormPdfModal .pepf-i2 { padding-left: 28px; }

/* ===== FOOTER ===== */
#personEvidenceFormPdfModal .pepf-footer {
    margin-top: auto; font-size: 9.5px; color: #333;
    display: flex; justify-content: space-between; align-items: flex-end; flex-shrink: 0;
}
#personEvidenceFormPdfModal .pepf-footer-left { flex: 1; }
#personEvidenceFormPdfModal .pepf-footer-right {
    text-align: right; white-space: nowrap; line-height: 1.3;
}

/* ===== Add/Remove buttons ===== */
#personEvidenceFormPdfModal .pepf-add-btn {
    font-size: 10px; padding: 1px 8px; border: 1px dashed #888;
    background: #f8f8f8; cursor: pointer; color: #333; margin: 3px 0;
    font-family: 'Sarabun', sans-serif;
}
#personEvidenceFormPdfModal .pepf-add-btn:hover { background: #e0e0e0; }
#personEvidenceFormPdfModal .pepf-del-btn {
    font-size: 9px; padding: 0 4px; border: 1px solid #ccc;
    background: #fff; cursor: pointer; color: #c00;
    font-family: 'Sarabun', sans-serif; line-height: 1.5;
}
#personEvidenceFormPdfModal .pepf-del-btn:hover { background: #fee; }

/* ===== Signature box ===== */
#personEvidenceFormPdfModal .pepf-sig-box {
    border: 1px solid #ccc; background: #fafafa; min-height: 60px;
    cursor: crosshair; position: relative;
}
#personEvidenceFormPdfModal .pepf-sig-box canvas {
    width: 100%; height: 100%; display: block;
}

/* ===== Photo Grid 5×7 (35 photos/page) ===== */
#personEvidenceFormPdfModal .pepf-photo-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 6px 4px;
    margin-bottom: 6px;
}
#personEvidenceFormPdfModal .pepf-photo-cell {
    aspect-ratio: 1;
    border: 1.5px solid #333;
    overflow: hidden;
    position: relative;
    background: #fafafa;
    display: flex;
    align-items: center;
    justify-content: center;
}
#personEvidenceFormPdfModal .pepf-photo-cell img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
#personEvidenceFormPdfModal .pepf-cell-delete {
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
#personEvidenceFormPdfModal .pepf-cell-delete:hover {
    background: #bb2d3b;
}
#personEvidenceFormPdfModal .pepf-cell-filename {
    font-size: 8px;
    text-align: center;
    margin-top: 1px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #333;
}

/* ===== Dropzone ===== */
#personEvidenceFormPdfModal .pepf-photo-dropzone {
    border: 2px dashed #b0bec5;
    border-radius: 8px;
    padding: 16px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: #fafafa;
    margin-bottom: 8px;
}
#personEvidenceFormPdfModal .pepf-photo-dropzone:hover {
    border-color: #78909c;
    background: #f5f5f5;
}
#personEvidenceFormPdfModal .pepf-photo-dropzone.dragover {
    border-color: #2196f3;
    background: #e3f2fd;
}
#personEvidenceFormPdfModal .pepf-add-photo-page-btn {
    display: block; margin: 8px auto; padding: 4px 16px;
    border: 1px dashed #888; background: #f0f0f0; cursor: pointer;
    font-size: 11px; font-family: 'Sarabun', sans-serif; color: #333;
}
#personEvidenceFormPdfModal .pepf-add-photo-page-btn:hover { background: #e0e0e0; }

/* ===== Person info card ===== */
#personEvidenceFormPdfModal .pepf-person-card {
    border: 1px solid #000; margin-bottom: 6px; padding: 4px 6px; font-size: 11px;
    overflow: hidden; box-sizing: border-box;
}
#personEvidenceFormPdfModal .pepf-person-card .pepf-pc-header {
    font-weight: 700; font-size: 11.5px; margin-bottom: 3px;
}

#personEvidenceFormPdfModal .pepf-lab-unit-sel {
    flex: 0 1 120px;
    min-width: 88px;
    max-width: 120px;
    font-size: 0.72rem;
}

#personEvidenceFormPdfModal .pepf-evidence-detail-row {
    display: block;
    margin-bottom: 4px;
}

#personEvidenceFormPdfModal .pepf-ed-line1,
#personEvidenceFormPdfModal .pepf-ed-line2 {
    display: flex;
    align-items: center;
    gap: 4px;
}

#personEvidenceFormPdfModal .pepf-ed-line2 {
    padding-left: 22px;
    margin-top: 2px;
    flex-wrap: nowrap;
}

#personEvidenceFormPdfModal .pepf-ed-line2 .pepf-fl {
    font-size: 9px;
    margin-right: 2px;
}

#personEvidenceFormPdfModal .pepf-ed-line2 .pepf-inp-s {
    flex: 0 0 30px;
    min-width: 30px;
    max-width: 30px;
    margin: 0;
}

#personEvidenceFormPdfModal .pepf-evidence-desc-inp {
    flex: 1 1 auto;
    min-width: 120px;
}

#personEvidenceFormPdfModal .pepf-sig-section {
    border: 1px solid #000; padding: 4px 6px; margin-top: 6px;
}

/* ===== EV7 Parity: reset global UI scale to match modal_scene_evidence_pdf_form ===== */
#personEvidenceFormPdfModal .pepf-page {
    font-size: 12px;
    line-height: 1.3;
}
#personEvidenceFormPdfModal .pepf-header-center .pepf-title-main {
    font-size: 13px;
    letter-spacing: 0.3px;
    margin-bottom: 4px;
}
#personEvidenceFormPdfModal .pepf-header-center .pepf-title-sub {
    font-size: 10.5px;
    margin-top: 2px;
}
#personEvidenceFormPdfModal .pepf-doc-box {
    padding: 3px 8px;
    font-size: 10.5px;
}
#personEvidenceFormPdfModal .pepf-row-header {
    font-size: 10.5px;
}
#personEvidenceFormPdfModal .pepf-sec-label {
    padding: 3px 3px;
    font-size: 10.2px;
}
#personEvidenceFormPdfModal .pepf-sec-label .pepf-sec-num {
    font-size: 12px;
    line-height: 1.2;
}
#personEvidenceFormPdfModal .pepf-sec-label .pepf-sec-txt {
    font-size: 9.2px;
    line-height: 1.15;
}
#personEvidenceFormPdfModal .pepf-sec-body {
    padding: 6px 8px;
    font-size: 10.5px;
    min-width: 0;
    overflow: hidden;
}
#personEvidenceFormPdfModal .pepf-fr {
    margin-bottom: 5px;
    line-height: 1.6;
}
#personEvidenceFormPdfModal .pepf-fl,
#personEvidenceFormPdfModal .pepf-fl-b {
    font-size: 10.4px;
    white-space: nowrap;
    line-height: normal;
}
#personEvidenceFormPdfModal .pepf-fl { margin-right: 4px; }
#personEvidenceFormPdfModal .pepf-fl-b { margin-right: 4px; }
#personEvidenceFormPdfModal .pepf-inp,
#personEvidenceFormPdfModal .pepf-inp-m,
#personEvidenceFormPdfModal .pepf-inp-full,
#personEvidenceFormPdfModal .pepf-inp-s,
#personEvidenceFormPdfModal .pepf-sel,
#personEvidenceFormPdfModal .pepf-ta {
    font-size: 10.2px;
}
#personEvidenceFormPdfModal .pepf-inp,
#personEvidenceFormPdfModal .pepf-inp-m,
#personEvidenceFormPdfModal .pepf-inp-full,
#personEvidenceFormPdfModal .pepf-inp-s,
#personEvidenceFormPdfModal .pepf-sel {
    height: 20px;
}
#personEvidenceFormPdfModal .pepf-inp,
#personEvidenceFormPdfModal .pepf-inp-m,
#personEvidenceFormPdfModal .pepf-inp-full,
#personEvidenceFormPdfModal .pepf-inp-s {
    padding: 0 2px;
}
#personEvidenceFormPdfModal .pepf-ck {
    font-size: 10.2px;
    white-space: nowrap;
}
#personEvidenceFormPdfModal .pepf-bh {
    font-size: 10.6px;
    margin-top: 8px;
    margin-bottom: 5px;
}
#personEvidenceFormPdfModal .pepf-si {
    font-size: 10.3px;
    line-height: 1.65;
    margin-bottom: 5px;
}
#personEvidenceFormPdfModal .pepf-si-no {
    padding-left: 8px;
    font-size: 10.3px;
}
#personEvidenceFormPdfModal .pepf-cg {
    gap: 4px 12px;
}

/* Keep action buttons on one line */
#personEvidenceFormPdfModal .modal-footer {
    flex-wrap: nowrap;
    gap: 8px;
}
#personEvidenceFormPdfModal .modal-footer .btn {
    white-space: nowrap;
    font-size: 0.84rem;
}

/* ===== Handwriting Pen Button ===== */
#personEvidenceFormPdfModal .btn-hw-open {
    flex-shrink: 0; min-width: 18px; padding: 0 4px;
    border: none; background: none; color: #6366f1;
    font-size: 0.7rem; cursor: pointer; line-height: 1.5;
}
#personEvidenceFormPdfModal .btn-hw-open:hover { color: #4338ca; transform: scale(1.15); }

@media print {
    body > *:not(#personEvidenceFormPdfModal),
    .navbar, .sidebar, .main-content, #status-banner,
    .modal-backdrop, .swal2-container,
    #personEvidenceFormPdfModal .modal-header,
    #personEvidenceFormPdfModal .modal-footer,
    #personEvidenceFormPdfModal .csims-loading-overlay,
    #personEvidenceFormPdfModal .pepf-add-btn,
    #personEvidenceFormPdfModal .pepf-del-btn,
    #personEvidenceFormPdfModal .pepf-add-photo-page-btn,
    #personEvidenceFormPdfModal .btn-hw-open,
    #personEvidenceFormPdfModal .btn-sketch-eraser,
    #personEvidenceFormPdfModal input[type="color"],
    #personEvidenceFormPdfModal .form-check.form-switch,
    #personEvidenceFormPdfModal .d-flex.align-items-center.gap-3 {
        display: none !important;
    }
    #personEvidenceFormPdfModal,
    #personEvidenceFormPdfModal .modal-dialog,
    #personEvidenceFormPdfModal .modal-content,
    #personEvidenceFormPdfModal .pepf-body {
        position: static !important; display: block !important;
        width: auto !important; max-width: none !important;
        max-height: none !important; height: auto !important;
        overflow: visible !important; margin: 0 !important;
        padding: 0 !important; background: #fff !important;
        border: none !important; box-shadow: none !important;
        transform: none !important; opacity: 1 !important;
    }
    @page { size: A4 portrait; margin: 0; }
    #personEvidenceFormPdfModal .pepf-page {
        width: 100% !important; min-height: auto !important;
        height: auto !important; margin: 0 !important;
        padding: 8mm 10mm 5mm 10mm !important;
        box-shadow: none !important; overflow: visible !important;
        page-break-after: always; page-break-inside: auto;
    }
    #personEvidenceFormPdfModal .pepf-page:last-of-type { page-break-after: auto; }
    #personEvidenceFormPdfModal .pepf-sec-row { page-break-inside: avoid; }
    #personEvidenceFormPdfModal .pepf-form-body { page-break-inside: auto; }
    #personEvidenceFormPdfModal .pepf-header { page-break-after: avoid; }
    #personEvidenceFormPdfModal .pepf-footer { page-break-before: avoid; }
    #personEvidenceFormPdfModal .pepf-photo-slot { page-break-inside: avoid; }
    #personEvidenceFormPdfModal .pepf-sig-box,
    #personEvidenceFormPdfModal .pepf-sketch-area { page-break-inside: avoid; }
    #personEvidenceFormPdfModal .pepf-inp, #personEvidenceFormPdfModal .pepf-inp-m,
    #personEvidenceFormPdfModal .pepf-inp-full, #personEvidenceFormPdfModal .pepf-inp-s,
    #personEvidenceFormPdfModal .pepf-sel, #personEvidenceFormPdfModal .pepf-ta {
        border-bottom-color: #888 !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    #personEvidenceFormPdfModal .pepf-cb {
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
}
</style>

<!-- ===== MODAL ===== -->
<div class="modal fade" id="personEvidenceFormPdfModal" aria-labelledby="personEvidenceFormPdfModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 930px; margin: 1rem auto;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="personEvidenceFormPdfModalLabel">
                    <i class="fas fa-plus fa-lg me-2"></i> ตรวจเก็บวัตถุพยานที่บุคคล
                </h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body pepf-body p-0" style="max-height: 86vh; overflow: auto; position:relative;">
                <!-- Loading Overlay -->
                <div id="pepfLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track"><div class="csims-bar-fill"></div></div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>
                <form id="personEvidenceFormPdf" novalidate>
                    <input type="hidden" id="pepf_receiveNoti_id" name="receiveNoti_id_ev8">
                    <input type="hidden" id="pepf_doc_no" name="doc_no_ev8">
                    <input type="hidden" id="pepf_report_no" name="report_no_ev8">

                    <!-- Switch + Edit info -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="editInfoEV8Pdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="editCountEV8Pdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchToStdFormEV8" checked style="width: 3rem; height: 1.5rem; cursor: pointer;">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchToStdFormEV8" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ================================================================ -->
<!-- ========================= PAGE 1 ============================== -->
<!-- ================================================================ -->
<div class="pepf-page">

    <div class="pepf-header">
        <div class="pepf-header-logo">
            <img src="./images/office-of-police-forensic-icon.jpg" alt="ตราสำนักงานพิสูจน์หลักฐานตำรวจ">
        </div>
        <div class="pepf-header-center">
            <div class="pepf-title-main">รายงานการตรวจเก็บวัตถุพยาน</div>
            <div class="pepf-title-sub">การตรวจเก็บวัตถุพยานที่บุคคล</div>
        </div>
        <div class="pepf-header-right">
            <div class="pepf-doc-box">
                <input type="hidden" name="pepf_report_ref" id="pepf_report_ref">
                <input type="hidden" name="pepf_report_year" id="pepf_report_year" value="<?= substr((date('Y') + 543), -2) ?>">
                <div class="pepf-doc-line">เลขรับที่/เลขรายงาน <span id="pepf_report_no_display" class="pepf-rpt-no-mirror"></span> / 25<span id="pepf_report_year_display" class="pepf-rpt-year-mirror"><?= substr((date('Y') + 543), -2) ?></span></div>
                <div class="pepf-doc-line">หน้าที่ <span class="pepf-cur-page">1</span> / <span class="pepf-total-page">3</span></div>
            </div>
        </div>
    </div>

    <div class="pepf-form-body">

        <!-- LEFT COLUMN: 1. สิ่งที่ได้รับจาก / การรับแจ้งเหตุ -->
        <div class="pepf-col-left">
            <div class="pepf-row-header">
                <div class="pepf-lbl-seq">ลำดับ</div>
                <div class="pepf-lbl-data">ข้อมูล</div>
            </div>

            <div class="pepf-sec-row" style="flex:1; border-bottom:none;">
                <div class="pepf-sec-label">
                    <span class="pepf-sec-num">1.</span>
                    <span class="pepf-sec-txt">สิ่งที่<br>ได้รับ<br>จาก/<br>การรับ<br>แจ้งเหตุ</span>
                </div>
                <div class="pepf-sec-body">
                    <div class="pepf-fr pepf-dt-row">
                        <span class="pepf-fl">เมื่อวันที่</span>
                        <input type="date" class="pepf-inp-m pepf-date-fixed" name="pepf_receive_date" id="pepf_receive_date" value="<?= $pepfTodayDate ?>">
                        <span class="pepf-fl">เวลา</span>
                        <input type="time" class="pepf-inp pepf-time-fixed" name="pepf_receive_time" id="pepf_receive_time" value="<?= $pepfTodayTime ?>" style="text-align:center;">
                        <span class="pepf-fl">น.</span>
                    </div>

                    <div class="pepf-bh" style="margin-top:2px;"><span class="pepf-bk"></span><span>หน่วยงาน</span></div>
                    <div class="pepf-fr pepf-i1" style="display:flex; align-items:center; gap:4px;">
                        <input type="text" class="pepf-inp" name="pepf_unit_name" id="pepf_unit_name" style="flex:1;">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="pepf_unit_name" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="pepf-cg pepf-i1">
                        <label class="pepf-ck"><input type="checkbox" class="pepf-cb" name="pepf_unit_type_check[]" value="กองพิสูจน์หลักฐานกลาง">กองพิสูจน์หลักฐานกลาง</label>
                    </div>
                    <div class="pepf-cg pepf-i1">
                        <label class="pepf-ck"><input type="checkbox" class="pepf-cb" name="pepf_unit_type_check[]" value="ศูนย์พิสูจน์หลักฐาน">ศูนย์พิสูจน์หลักฐาน</label>
                       
                    </div>
                    <div class="pepf-cg pepf-i1">
                        <label class="pepf-ck"><input type="checkbox" class="pepf-cb" name="pepf_unit_type_check[]" value="พิสูจน์หลักฐานจังหวัด">พิสูจน์หลักฐานจังหวัด</label>
                        
                    </div>

                    <div class="pepf-bh" style="margin-top:4px;"><span class="pepf-bk"></span><span>การรับแจ้ง</span></div>
                    <div class="pepf-fr pepf-i1" style="gap:4px 8px;">
                        <span class="pepf-fl" style="min-width:55px;">ได้รับแจ้ง</span>
                        <label class="pepf-ck"><input type="checkbox" class="pepf-cb" name="pepf_notify_method[]" value="ทางโทรศัพท์">ทางโทรศัพท์</label>
                        <label class="pepf-ck"><input type="checkbox" class="pepf-cb" name="pepf_notify_method[]" value="ทางวิทยุสื่อสาร">ทางวิทยุสื่อสาร</label>
                        <label class="pepf-ck"><input type="checkbox" class="pepf-cb" name="pepf_notify_method[]" value="ทางหนังสือ">ทางหนังสือ</label>
                        <label class="pepf-ck" style="margin-right:4px;"><input type="checkbox" class="pepf-cb" name="pepf_notify_method[]" value="อื่นๆ" id="pepf_notify_other">อื่นๆ</label>
                        <input type="text" class="pepf-inp" name="pepf_notify_method_other_text" id="pepf_notify_method_other_text" style="max-width:100px; display:none;" disabled>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="pepf_notify_method_other_text" id="pepf_notify_other_hw" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem; display:none;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="pepf-fr pepf-i1">
                        <span class="pepf-fl">จาก สน./สภ.</span>
                        <select class="pepf-sel" name="pepf_police_station" id="pepf_police_station">
                            <?= $pepfPoliceStationOptions ?>
                        </select>
                    </div>
                    <div class="pepf-fr pepf-i1">
                        <span class="pepf-fl">ที่</span>
                        <input type="text" class="pepf-inp" name="pepf_document_no" id="pepf_document_no">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <span class="pepf-fl">ลง</span>
                        <input type="date" class="pepf-inp" name="pepf_document_date" id="pepf_document_date" style="text-align:center;" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="pepf-fr pepf-i1">
                        <span class="pepf-fl">ในคดี</span>
                        <input type="text" class="pepf-inp" name="pepf_case_no" id="pepf_case_no" readonly>
                    </div>
                    <div class="pepf-fr pepf-i1">
                        <span class="pepf-fl">สถานที่เกิดเหตุ</span>
                        <input type="text" class="pepf-inp pepf-location-auto-line" name="pepf_incident_location" id="pepf_incident_location">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <input type="text" class="pepf-inp-full pepf-i1 pepf-location-auto-line" name="pepf_incident_location_2" id="pepf_incident_location_2">
                    <div class="pepf-fr pepf-i1 pepf-dt-row pepf-dt-row-long">
                        <span class="pepf-fl">เหตุเกิดเมื่อวันที่</span>
                        <input type="date" class="pepf-inp-m pepf-date-fixed" name="pepf_incident_date" id="pepf_incident_date" value="<?= $pepfTodayDate ?>">
                        <span class="pepf-fl">เวลาประมาณ</span>
                        <input type="time" class="pepf-inp pepf-time-fixed" name="pepf_incident_time" id="pepf_incident_time" value="<?= $pepfTodayTime ?>" style="text-align:center;">
                        <span class="pepf-fl">น.</span>
                    </div>
                    <div class="pepf-fr pepf-i1">
                        <span class="pepf-fl">พนักงานสอบสวน</span>
                        <input type="text" class="pepf-inp" name="pepf_investigator_name" id="pepf_investigator_name">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="pepf_investigator_name" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="pepf-fr pepf-i1">
                        <span class="pepf-fl">ขอส่ง</span>
                        <input type="text" class="pepf-inp" name="pepf_send_request" id="pepf_send_request">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="pepf_send_request" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>

                    <!-- รายการบุคคล -->
                    <div class="pepf-bh" style="margin-top:4px;"><span class="pepf-bk"></span><span>รายการบุคคล</span></div>
                    <div id="pepf_person_items_container" class="pepf-i1">
                        <div class="pepf-si pepf-person-item-row">
                            <span class="pepf-si-no">1.1</span>
                            <select class="pepf-sel" name="pepf_person_prefix[]" style="max-width:70px;">
                                <option value="" selected disabled>-</option>
                                <option value="นาย">นาย</option>
                                <option value="นาง">นาง</option>
                                <option value="นางสาว">นางสาว</option>
                                <option value="อื่นๆ">อื่นๆ</option>
                            </select>
                            <input type="text" class="pepf-inp" name="pepf_person_name[]">
                            <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        </div>
                    </div>
                    <div class="pepf-i1">
                        <button type="button" class="pepf-add-btn" onclick="pepfAddPersonItem()">+ เพิ่มรายการบุคคล</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="pepf-col-right">
            <div class="pepf-row-header">
                <div class="pepf-lbl-seq">ลำดับ</div>
                <div class="pepf-lbl-data">ข้อมูล</div>
            </div>

            <!-- 2. วัตถุประสงค์ในการตรวจ -->
            <div class="pepf-sec-row">
                <div class="pepf-sec-label">
                    <span class="pepf-sec-num">2.</span>
                    <span class="pepf-sec-txt">วัตถุ<br>ประสงค์<br>ในการ<br>ตรวจ</span>
                </div>
                <div class="pepf-sec-body">
                    <div class="pepf-fr">
                        <span class="pepf-fl">เพื่อทำการตรวจเก็บ</span>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="pepf_purpose_detail" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <textarea class="pepf-ta pepf-i1" name="pepf_purpose_detail" id="pepf_purpose_detail" rows="2"></textarea>
                </div>
            </div>

            <!-- 3. ผลการตรวจ -->
            <div class="pepf-sec-row" style="flex:1; border-bottom:none;">
                <div class="pepf-sec-label">
                    <span class="pepf-sec-num">3.</span>
                    <span class="pepf-sec-txt">ผลการ<br>ตรวจ</span>
                </div>
                <div class="pepf-sec-body">
                    <div class="pepf-fr">
                        <span class="pepf-fl">ได้ทำการตรวจเก็บ ที่</span>
                        <input type="text" class="pepf-inp" name="pepf_inspect_location" id="pepf_inspect_location">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="pepf_inspect_location" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="pepf-fr pepf-dt-row pepf-dt-row-long">
                        <span class="pepf-fl">เมื่อวันที่</span>
                        <input type="date" class="pepf-inp-m pepf-date-fixed" name="pepf_inspect_date" id="pepf_inspect_date" value="<?= $pepfTodayDate ?>">
                        <span class="pepf-fl">เวลาประมาณ</span>
                        <input type="time" class="pepf-inp pepf-time-fixed" name="pepf_inspect_time" id="pepf_inspect_time" value="<?= $pepfTodayTime ?>" style="text-align:center;">
                        <span class="pepf-fl">น.</span>
                    </div>

                    <!-- 3.1 ข้อมูลส่วนบุคคล -->
                    <div class="pepf-bh"><span class="pepf-bk"></span><span>3.1 ข้อมูลส่วนบุคคล</span></div>
                    <div class="pepf-fr pepf-i1">
                 
                    </div>
                    <div id="pepf_person_info_container" class="pepf-i1">
                        <div class="pepf-person-card pepf-person-info-row">
                            <div class="pepf-pc-header">3.1.1 บุคคลที่ 1</div>
                            <div class="pepf-fr">
                                <select class="pepf-sel" name="pepf_info_prefix[]" style="max-width:70px;">
                                    <option value="" selected disabled>-</option>
                                    <option value="นาย">นาย</option>
                                    <option value="นาง">นาง</option>
                                    <option value="นางสาว">นางสาว</option>
                                    <option value="อื่นๆ">อื่นๆ</option>
                                </select>
                                <input type="text" class="pepf-inp" name="pepf_info_fullname[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                            </div>
                            <div class="pepf-fr">
                                <span class="pepf-fl">บัตรปชช.</span>
                                <input type="text" class="pepf-inp" name="pepf_info_id_card[]" style="max-width:140px;">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                                <span class="pepf-fl">หนังสือเดินทาง</span>
                                <input type="text" class="pepf-inp" name="pepf_info_passport[]" style="max-width:120px;">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                            </div>
                            <div class="pepf-fr">
                                <span class="pepf-fl">สูง</span>
                                <input type="text" class="pepf-inp-s" name="pepf_info_height[]" style="max-width:45px;">
                                <span class="pepf-fl">ซม.</span>
                                <span class="pepf-fl">อายุ</span>
                                <input type="text" class="pepf-inp-s" name="pepf_info_age[]" style="max-width:35px;">
                                <span class="pepf-fl">ปี</span>
                                <span class="pepf-fl">สีผิว</span>
                                <input type="text" class="pepf-inp" name="pepf_info_skin[]" style="max-width:70px;">
                                <span class="pepf-fl">มือถนัด</span>
                                <select class="pepf-sel" name="pepf_info_hand[]" style="max-width:60px;">
                                    <option value="" selected disabled>-</option>
                                    <option value="ขวา">ขวา</option>
                                    <option value="ซ้าย">ซ้าย</option>
                                    <option value="ทั้งสองมือ">ทั้งสอง</option>
                                </select>
                            </div>
                            <div class="pepf-fr">
                                <span class="pepf-fl">คำหนี้รูปพรรณ</span>
                                <input type="text" class="pepf-inp" name="pepf_info_feature[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="pepf-i1">
                        <button type="button" class="pepf-add-btn" onclick="pepfAddPersonInfo()">+ เพิ่มบุคคล</button>
                    </div>

                    <!-- 3.2 รายละเอียดวัตถุพยานที่ทำการตรวจเก็บ -->
                    <div class="pepf-bh"><span class="pepf-bk"></span><span>3.2 รายละเอียดวัตถุพยานที่ทำการตรวจเก็บ</span></div>
                    <div id="pepf_evidence_detail_container" class="pepf-i1">
                        <div class="pepf-si pepf-evidence-detail-row">
                            <div class="pepf-ed-line1">
                                <span class="pepf-si-no">3.2.1</span>
                                <input type="text" class="pepf-inp pepf-evidence-desc-inp" name="pepf_evidence_desc[]">
                                <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                            </div>
                            <div class="pepf-ed-line2">
                                <span class="pepf-fl">กลุ่มตรวจ</span>
                                <select class="pepf-sel pepf-lab-unit-sel lab-unit-multi" multiple size="3" title="-- กลุ่มตรวจพิสูจน์ (เลือกได้หลายข้อ) --">
                                    <option value="">-- กลุ่มตรวจพิสูจน์ (เลือกได้หลายข้อ) --</option>
                                    <option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>
                                    <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                    <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                    <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                    <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                    <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                    <option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                                </select>
                                <input type="hidden" class="lab-unit-value" name="pepf_lab_unit[]" value="">
                                <span class="pepf-fl" style="margin-left:4px;">จำนวน</span>
                                <input type="text" class="pepf-inp-s" name="pepf_evidence_qty[]" style="max-width:40px;">
                            </div>
                        </div>
                    </div>
                    <div class="pepf-i1">
                        <button type="button" class="pepf-add-btn" onclick="pepfAddEvidenceDetail()">+ เพิ่มรายการ</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="pepf-footer">
        <div class="pepf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="pepf-footer-right">บัญชีแนบท้ายคำสั่ง สพฐ.ตร.<br>ที่ 848/2561</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 2 ============================== -->
<!-- ================================================================ -->
<div class="pepf-page">

    <div class="pepf-header">
        <div class="pepf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="pepf-header-center">
            <div class="pepf-title-main">รายงานการตรวจเก็บวัตถุพยาน</div>
            <div class="pepf-title-sub">การตรวจเก็บวัตถุพยานที่บุคคล</div>
        </div>
        <div class="pepf-header-right">
            <div class="pepf-doc-box">
                <div class="pepf-doc-line">รายงานที่ <span class="pepf-rpt-no-mirror"></span> / 25<span class="pepf-rpt-year-mirror"></span></div>
                <div class="pepf-doc-line">หน้าที่ <span class="pepf-cur-page">2</span> / <span class="pepf-total-page">3</span></div>
            </div>
        </div>
    </div>

    <div class="pepf-form-body">
        <!-- LEFT COLUMN: 3.3 การดำเนินการเกี่ยวกับวัตถุพยาน -->
        <div class="pepf-col-left">
            <div class="pepf-row-header"><div class="pepf-lbl-seq">ลำดับ</div><div class="pepf-lbl-data">ข้อมูล</div></div>

            <div class="pepf-sec-row" style="flex:1; border-bottom:none;">
                <div class="pepf-sec-label"><span class="pepf-sec-num">3.</span><span class="pepf-sec-txt">(ต่อ)<br>3.3<br>การ<br>ดำเนิน<br>การ</span></div>
                <div class="pepf-sec-body">
                    <div class="pepf-bh" style="margin-top:0;"><span class="pepf-bk"></span><span>3.3 การดำเนินการเกี่ยวกับวัตถุพยาน</span></div>
                    <div class="pepf-fr pepf-i1">
                        <span class="pepf-fl">ได้ให้</span>
                        <input type="text" class="pepf-inp" name="pepf_witness_name" id="pepf_witness_name">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="pepf_witness_name" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="pepf-fr pepf-i1">
                        <span class="pepf-fl">ลงลายมือชื่อในแบบ</span>
                        <input type="text" class="pepf-inp" name="pepf_witness_form" id="pepf_witness_form">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="pepf_witness_form" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="pepf-fr pepf-i1">
                        <span class="pepf-fl">รายละเอียดเพิ่มเติม</span>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="pepf_witness_detail" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <textarea class="pepf-ta pepf-i1" name="pepf_witness_detail" id="pepf_witness_detail" rows="2"></textarea>
                    <div class="pepf-fr pepf-i1">
                        <span class="pepf-fl">ให้เป็นหลักฐาน และได้ (</span>
                        <label class="pepf-ck"><input type="checkbox" class="pepf-cb" name="pepf_handover_method_check[]" value="นำส่ง">นำส่ง</label>
                        <label class="pepf-ck"><input type="checkbox" class="pepf-cb" name="pepf_handover_method_check[]" value="ส่งมอบ">ส่งมอบ</label>
                        <span class="pepf-fl">)</span>
                        <input type="text" class="pepf-inp" name="pepf_handover_method_detail" id="pepf_handover_method_detail">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="pepf_handover_method_detail" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="pepf-fr pepf-i1">
                        <span class="pepf-fl">วัตถุพยานตามข้อ</span>
                        <input type="text" class="pepf-inp" name="pepf_handover_item_ref" id="pepf_handover_item_ref" style="max-width:80px;">
                        <button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                        <span class="pepf-fl">ให้</span>
                        <input type="text" class="pepf-inp" name="pepf_handover_to" id="pepf_handover_to">
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="pepf_handover_to" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <div class="pepf-fr pepf-i1">
                        <span class="pepf-fl">เพื่อดำเนินการต่อไป</span>
                        <button type="button" class="btn btn-sm btn-hw-open" data-hw-targets="pepf_handover_purpose" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>
                    </div>
                    <textarea class="pepf-ta pepf-i1" name="pepf_handover_purpose" id="pepf_handover_purpose" rows="7"></textarea>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="pepf-col-right">
            <div class="pepf-row-header"><div class="pepf-lbl-seq">ลำดับ</div><div class="pepf-lbl-data">ข้อมูล</div></div>

            <div class="pepf-sec-row" style="flex:1; border-bottom:none;">
                <div class="pepf-sec-label"><span class="pepf-sec-num">4.</span><span class="pepf-sec-txt">ผู้ตรวจ<br>&amp;<br>ลงนาม</span></div>
                <div class="pepf-sec-body">
                    <input type="hidden" name="pepf_signer_id" id="pepf_signer_id">
                    <input type="hidden" name="pepf_signer_position" id="pepf_signer_position">

                    <div class="pepf-bh" style="margin-top:0;"><span class="pepf-bk"></span><span>ผู้ตรวจสถานที่เกิดเหตุ</span></div>
                    <div id="pepf_inspector_container">
                        <div class="pepf-si pepf-inspector-row">
                            <span class="pepf-si-no">4.1</span>
                            <select class="pepf-sel" name="pepf_inspector_id[]">
                                <?= $pepfInspectorOptions ?>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="pepf-add-btn" onclick="pepfAddInspector()">+ เพิ่มผู้ตรวจ</button>

                    <div class="pepf-sig-section">
                        <div class="pepf-bh" style="margin-top:0; margin-bottom:4px;"><span class="pepf-bk"></span><span>ผู้รับมอบวัตถุพยาน</span></div>
                        <div class="pepf-fr" style="align-items:flex-end; margin-bottom:4px;">
                            <span class="pepf-fl" style="min-width:38px;">ลงชื่อ</span>
                            <div style="flex:1; min-height:60px; border-bottom:1px dotted #888; position:relative;">
                                <canvas id="pepf_sig_receiver" style="width:100%; height:60px; cursor:crosshair;"></canvas>
                                <input type="hidden" name="ev8_receiver_signature_data" id="pepf_receiver_sig_data">
                            </div>
                            <span class="pepf-fl" style="margin-left:4px;">ผู้รับมอบ</span>
                        </div>
                        <div class="text-end" style="margin-top:-2px; margin-bottom:4px;">
                            <button type="button" class="pepf-del-btn" onclick="if (typeof window.clearEV8ReceiverSignatures === 'function') { window.clearEV8ReceiverSignatures(); } else { pepfClearCanvas('pepf_sig_receiver'); }">ล้างลายเซ็น</button>
                        </div>
                        <div class="pepf-fr" style="margin-top:2px; margin-bottom:3px;">
                            <span class="pepf-fl" style="min-width:38px; visibility:hidden;">ลงชื่อ</span>
                            <span class="pepf-fl">(</span>
                            <select class="pepf-sel pepf-user-select" name="pepf_receiver_id" id="pepf_receiver_id" style="text-align:center;" data-pos-target="#pepf_receiver_position">
                                <?= str_replace('-- เลือก --', '-- เลือกผู้รับมอบ --', $pepfInspectorOptions) ?>
                            </select>
                            <span class="pepf-fl">)</span>
                        </div>
                        <div class="pepf-fr" style="margin-bottom:0;">
                            <span class="pepf-fl" style="min-width:38px;">ตำแหน่ง</span>
                            <input type="text" class="pepf-inp" name="pepf_receiver_position" id="pepf_receiver_position" readonly>
                        </div>
                    </div>

                    <div class="pepf-sig-section">
                        <div class="pepf-bh" style="margin-top:0; margin-bottom:4px;"><span class="pepf-bk"></span><span>ผู้ส่งมอบวัตถุพยาน</span></div>
                        <div class="pepf-fr" style="align-items:flex-end; margin-bottom:4px;">
                            <span class="pepf-fl" style="min-width:38px;">ลงชื่อ</span>
                            <div style="flex:1; min-height:60px; border-bottom:1px dotted #888; position:relative;">
                                <canvas id="pepf_sig_sender" style="width:100%; height:60px; cursor:crosshair;"></canvas>
                                <input type="hidden" name="ev8_sender_signature_data" id="pepf_sender_sig_data">
                            </div>
                            <span class="pepf-fl" style="margin-left:4px;">ผู้ส่งมอบ</span>
                        </div>
                        <div class="text-end" style="margin-top:-2px; margin-bottom:4px;">
                            <button type="button" class="pepf-del-btn" onclick="if (typeof window.clearEV8SenderSignatures === 'function') { window.clearEV8SenderSignatures(); } else { pepfClearCanvas('pepf_sig_sender'); }">ล้างลายเซ็น</button>
                        </div>
                        <div class="pepf-fr" style="margin-top:2px; margin-bottom:3px;">
                            <span class="pepf-fl" style="min-width:38px; visibility:hidden;">ลงชื่อ</span>
                            <span class="pepf-fl">(</span>
                            <select class="pepf-sel pepf-user-select" name="pepf_sender_id" id="pepf_sender_id" style="text-align:center;" data-pos-target="#pepf_sender_position">
                                <?= str_replace('-- เลือก --', '-- เลือกผู้ส่งมอบ --', $pepfInspectorOptions) ?>
                            </select>
                            <span class="pepf-fl">)</span>
                        </div>
                        <div class="pepf-fr" style="margin-bottom:0;">
                            <span class="pepf-fl" style="min-width:38px;">ตำแหน่ง</span>
                            <input type="text" class="pepf-inp" name="pepf_sender_position" id="pepf_sender_position" readonly>
                        </div>
                    </div>

                    <div class="pepf-fr" style="margin-top:6px; margin-bottom:0;">
                        <span class="pepf-fl">วันที่</span>
                        <input type="date" class="pepf-inp-m" name="pepf_sign_date" id="pepf_sign_date" value="<?= $pepfTodayDate ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="pepf-footer">
        <div class="pepf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="pepf-footer-right">บัญชีแนบท้ายคำสั่ง สพฐ.ตร.<br>ที่ 848/2561</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 3 (ภาพถ่าย) ==================== -->
<!-- ================================================================ -->
<div class="pepf-page pepf-photo-page">

    <div class="pepf-header">
        <div class="pepf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>
        <div class="pepf-header-center">
            <div class="pepf-title-main">รายงานการตรวจเก็บวัตถุพยาน</div>
            <div class="pepf-title-sub">บันทึกการถ่ายภาพ</div>
        </div>
        <div class="pepf-header-right">
            <div class="pepf-doc-box">
                <div class="pepf-doc-line">รายงานที่ <span class="pepf-rpt-no-mirror"></span> / 25<span class="pepf-rpt-year-mirror"></span></div>
                <div class="pepf-doc-line">หน้าที่ <span class="pepf-cur-page">3</span> / <span class="pepf-total-page">3</span></div>
            </div>
        </div>
    </div>

    <div style="margin-bottom:8px;">
        <div class="pepf-fr" style="margin-bottom:6px;">
            <span class="pepf-fl">วันที่ตรวจสถานที่เกิดเหตุ</span>
            <input type="date" class="pepf-inp" name="photo_inspect_date_ev8" style="text-align:center;">
            <span class="pepf-fl">เวลาประมาณ</span>
            <input type="time" class="pepf-inp" name="photo_inspect_time_ev8" style="text-align:center;">
            <span class="pepf-fl">น.</span>
        </div>
        <div class="pepf-fr" style="flex-wrap:nowrap; margin-bottom:6px;">
            <span class="pepf-fl">รหัสภาพถ่ายที่</span>
            <input type="text" class="pepf-inp" name="photo_id_start_ev8" style="min-width:80px;" readonly>
            <span class="pepf-fl">ถึง</span>
            <input type="text" class="pepf-inp" name="photo_id_end_ev8" style="min-width:80px;" readonly>
            <span class="pepf-fl">จำนวน</span>
            <input type="text" class="pepf-inp-s" name="photo_amount_ev8" style="max-width:45px; text-align:center;" readonly>
            <span class="pepf-fl">ภาพ</span>
        </div>
        <div style="font-size:10.5px; font-style:italic; margin-top:2px;">(ตามภาพถ่ายรวมที่แนบ)</div>
    </div>

    <!-- Drag & Drop zone -->
    <div class="pepf-photo-dropzone" id="pepf_photo_dropzone">
        <i class="fas fa-cloud-upload-alt" style="font-size:1.2rem; color:#90a4ae;"></i>
        <div style="font-size:10px; color:#666; margin-top:2px;">ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือก</div>
    </div>
    <input type="file" id="pepf_photo_input_gallery" accept="image/*" multiple style="display:none;" onchange="pepfPreviewPhotos(this)">
    <input type="file" id="pepf_photo_input_camera" accept="image/*" capture="environment" multiple style="display:none;" onchange="pepfPreviewPhotos(this)">

    <!-- Photo Grid (35 photos per page, populated by JS) -->
    <div class="pepf-photo-grid" id="pepf_photo_grid_1"></div>

    <div class="pepf-footer">
        <div class="pepf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>
        <div class="pepf-footer-right">บัญชีแนบท้ายคำสั่ง สพฐ.ตร.<br>ที่ 848/2561</div>
    </div>
</div>

<div id="pepf_extra_photo_pages"></div>

<button type="button" class="pepf-add-photo-page-btn" onclick="pepfAddPhotoPage()">+ เพิ่มหน้ารูปถ่าย</button>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_ev8_pdf" onclick="prepareDataForSubmissionPersonEvidence()">
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
    var pepfUpdatePageNumbers = function() {
        var pages = document.querySelectorAll('#personEvidenceFormPdfModal .pepf-page');
        var total = pages.length;
        pages.forEach(function(page, idx) {
            var curEl = page.querySelector('.pepf-cur-page');
            var totalEl = page.querySelector('.pepf-total-page');
            if (curEl) curEl.textContent = (idx + 1);
            if (totalEl) totalEl.textContent = total;
        });
    };
    pepfUpdatePageNumbers();
    window.pepfUpdatePageNumbers = pepfUpdatePageNumbers;

    // ===== Mirror report no → หน้าอื่น =====
    var pepfSyncReportNo = function() {
        var docNoVal = document.getElementById('pepf_doc_no') ? document.getElementById('pepf_doc_no').value : '';
        var reportNoVal = document.getElementById('pepf_report_no') ? document.getElementById('pepf_report_no').value : '';
        var reportRefVal = reportNoVal;
        var yearVal = '';
        if (reportNoVal) {
            var reportParts = reportNoVal.toString().trim().split('/');
            reportRefVal = (reportParts[0] || '').toString().trim();
            yearVal = (reportParts[1] || '').toString().trim();
            if (!yearVal && reportParts.length === 1) {
                var match = reportNoVal.toString().trim().match(/(\d{2,4})$/);
                yearVal = match ? match[1] : '';
            }
            if (yearVal.length > 2) yearVal = yearVal.slice(-2);
        }

        var refInput = document.getElementById('pepf_report_ref');
        var yearInput = document.getElementById('pepf_report_year');
        if (refInput) refInput.value = reportRefVal;
        if (yearInput && yearVal) yearInput.value = yearVal;

        var yearDisplay = yearVal || (yearInput ? yearInput.value : '');
        document.querySelectorAll('#personEvidenceFormPdfModal .pepf-rpt-no-mirror').forEach(function(el) { el.textContent = reportRefVal; });
        document.querySelectorAll('#personEvidenceFormPdfModal .pepf-rpt-year-mirror').forEach(function(el) { el.textContent = yearDisplay; });

        // Fix ช่อง "ในคดี" ให้ใช้เลขเอกสาร/เลขรับ (doc no) — แสดงเป็นภาษาไทย
        var caseNoEl = document.getElementById('pepf_case_no');
        if (caseNoEl) {
            caseNoEl.value = (window.toThaiDocNo ? toThaiDocNo(docNoVal) : docNoVal) || '';
            caseNoEl.readOnly = true;
        }
    };
    var refEl = document.getElementById('pepf_report_ref');
    var yearEl = document.getElementById('pepf_report_year');
    var docNoEl = document.getElementById('pepf_doc_no');
    var reportNoEl = document.getElementById('pepf_report_no');
    if (refEl) refEl.addEventListener('input', pepfSyncReportNo);
    if (yearEl) yearEl.addEventListener('input', pepfSyncReportNo);
    if (docNoEl) docNoEl.addEventListener('input', pepfSyncReportNo);
    if (reportNoEl) reportNoEl.addEventListener('input', pepfSyncReportNo);
    pepfSyncReportNo();

    // ===== Toggle ช่องระบุ "อื่นๆ" =====
    var pepfToggleNotifyOther = function() {
        var checked = !!document.querySelector('#personEvidenceFormPdfModal input[name="pepf_notify_method[]"][value="อื่นๆ"]:checked');
        var otherEl = document.getElementById('pepf_notify_method_other_text');
        if (!otherEl) return;
        otherEl.style.display = checked ? '' : 'none';
        otherEl.disabled = !checked;
        if (!checked) otherEl.value = '';
        var hwBtn = document.getElementById('pepf_notify_other_hw');
        if (hwBtn) hwBtn.style.display = checked ? '' : 'none';
    };
    window.pepfToggleNotifyOther = pepfToggleNotifyOther;
    $(document).on('change', '#personEvidenceFormPdfModal input[name="pepf_notify_method[]"]', pepfToggleNotifyOther);

    // ===== Inspector counter =====
    var pepfInspectorIdx = 1;
    window.pepfAddInspector = function() {
        pepfInspectorIdx++;
        var container = document.getElementById('pepf_inspector_container');
        var row = document.createElement('div');
        row.className = 'pepf-si pepf-inspector-row';
        row.innerHTML = '<span class="pepf-si-no">4.' + pepfInspectorIdx + '</span>' +
            '<select class="pepf-sel" name="pepf_inspector_id[]">' +
            container.querySelector('select').innerHTML +
            '</select>' +
            ' <button type="button" class="pepf-del-btn" onclick="this.parentElement.remove(); pepfRenumberInspectors();">×</button>';
        container.appendChild(row);
    };
    window.pepfRenumberInspectors = function() {
        var rows = document.querySelectorAll('#pepf_inspector_container .pepf-inspector-row');
        rows.forEach(function(r, i) {
            var no = r.querySelector('.pepf-si-no');
            if (no) no.textContent = '4.' + (i + 1);
        });
        pepfInspectorIdx = rows.length;
    };

    // ===== Person items =====
    var pepfPersonItemIdx = 1;
    window.pepfAddPersonItem = function() {
        pepfPersonItemIdx++;
        var container = document.getElementById('pepf_person_items_container');
        var row = document.createElement('div');
        row.className = 'pepf-si pepf-person-item-row';
        row.innerHTML = '<span class="pepf-si-no">1.' + pepfPersonItemIdx + '</span>' +
            '<select class="pepf-sel" name="pepf_person_prefix[]" style="max-width:70px;">' +
                '<option value="" selected disabled>-</option><option value="นาย">นาย</option><option value="นาง">นาง</option><option value="นางสาว">นางสาว</option><option value="อื่นๆ">อื่นๆ</option>' +
            '</select>' +
            '<input type="text" class="pepf-inp" name="pepf_person_name[]">' +
            '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="\u0e40\u0e02\u0e35\u0e22\u0e19\u0e14\u0e49\u0e27\u0e22\u0e25\u0e32\u0e22\u0e21\u0e37\u0e2d" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
            ' <button type="button" class="pepf-del-btn" onclick="this.parentElement.remove(); pepfRenumberPersonItems();">×</button>';
        container.appendChild(row);
    };
    window.pepfRenumberPersonItems = function() {
        var rows = document.querySelectorAll('#pepf_person_items_container .pepf-person-item-row');
        rows.forEach(function(r, i) {
            var no = r.querySelector('.pepf-si-no');
            if (no) no.textContent = '1.' + (i + 1);
        });
        pepfPersonItemIdx = rows.length;
    };

    // ===== Person info (3.1) =====
    var pepfPersonInfoIdx = 1;
    window.pepfAddPersonInfo = function() {
        pepfPersonInfoIdx++;
        var n = pepfPersonInfoIdx;
        var container = document.getElementById('pepf_person_info_container');
        var card = document.createElement('div');
        card.className = 'pepf-person-card pepf-person-info-row';
        card.innerHTML =
            '<div class="pepf-pc-header">3.1.' + n + ' บุคคลที่ ' + n +
            ' <button type="button" class="pepf-del-btn" onclick="this.closest(\'.pepf-person-info-row\').remove(); pepfRenumberPersonInfo();">×</button></div>' +
            '<div class="pepf-fr">' +
                '<select class="pepf-sel" name="pepf_info_prefix[]" style="max-width:70px;">' +
                    '<option value="" selected disabled>-</option><option value="นาย">นาย</option><option value="นาง">นาง</option><option value="นางสาว">นางสาว</option><option value="อื่นๆ">อื่นๆ</option>' +
                '</select>' +
                '<input type="text" class="pepf-inp" name="pepf_info_fullname[]">' +
                '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="\u0e40\u0e02\u0e35\u0e22\u0e19\u0e14\u0e49\u0e27\u0e22\u0e25\u0e32\u0e22\u0e21\u0e37\u0e2d" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
            '</div>' +
            '<div class="pepf-fr">' +
                '<span class="pepf-fl">บัตรปชช.</span><input type="text" class="pepf-inp" name="pepf_info_id_card[]" style="max-width:140px;">' +
                '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="\u0e40\u0e02\u0e35\u0e22\u0e19\u0e14\u0e49\u0e27\u0e22\u0e25\u0e32\u0e22\u0e21\u0e37\u0e2d" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
                '<span class="pepf-fl">หนังสือเดินทาง</span><input type="text" class="pepf-inp" name="pepf_info_passport[]" style="max-width:120px;">' +
                '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="\u0e40\u0e02\u0e35\u0e22\u0e19\u0e14\u0e49\u0e27\u0e22\u0e25\u0e32\u0e22\u0e21\u0e37\u0e2d" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
            '</div>' +
            '<div class="pepf-fr">' +
                '<span class="pepf-fl">สูง</span><input type="text" class="pepf-inp-s" name="pepf_info_height[]" style="max-width:45px;"><span class="pepf-fl">ซม.</span>' +
                '<span class="pepf-fl">อายุ</span><input type="text" class="pepf-inp-s" name="pepf_info_age[]" style="max-width:35px;"><span class="pepf-fl">ปี</span>' +
                '<span class="pepf-fl">สีผิว</span><input type="text" class="pepf-inp" name="pepf_info_skin[]" style="max-width:70px;">' +
                '<span class="pepf-fl">มือถนัด</span><select class="pepf-sel" name="pepf_info_hand[]" style="max-width:60px;"><option value="" selected disabled>-</option><option value="ขวา">ขวา</option><option value="ซ้าย">ซ้าย</option><option value="ทั้งสองมือ">ทั้งสอง</option></select>' +
            '</div>' +
            '<div class="pepf-fr">' +
                '<span class="pepf-fl">คำหนี้รูปพรรณ</span><input type="text" class="pepf-inp" name="pepf_info_feature[]">' +
                '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="\u0e40\u0e02\u0e35\u0e22\u0e19\u0e14\u0e49\u0e27\u0e22\u0e25\u0e32\u0e22\u0e21\u0e37\u0e2d" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
            '</div>';
        container.appendChild(card);
    };
    window.pepfRenumberPersonInfo = function() {
        var rows = document.querySelectorAll('#pepf_person_info_container .pepf-person-info-row');
        rows.forEach(function(r, i) {
            var header = r.querySelector('.pepf-pc-header');
            if (header) {
                var btn = header.querySelector('button');
                header.textContent = '3.1.' + (i + 1) + ' บุคคลที่ ' + (i + 1) + ' ';
                if (btn) header.appendChild(btn);
            }
        });
        pepfPersonInfoIdx = rows.length;
    };

    // ===== Evidence details (3.2) =====
    var pepfEvidenceDetailIdx = 1;
    var pepfLabUnitOptions = '<option value="">-- กลุ่มตรวจพิสูจน์ (เลือกได้หลายข้อ) --</option>' +
        '<option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option>' +
        '<option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>' +
        '<option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>' +
        '<option value="drug">กลุ่มงานตรวจยาเสพติด</option>' +
        '<option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>' +
        '<option value="document">กลุ่มงานตรวจเอกสาร</option>' +
        '<option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>';
    window.pepfAddEvidenceDetail = function() {
        pepfEvidenceDetailIdx++;
        var container = document.getElementById('pepf_evidence_detail_container');
        var row = document.createElement('div');
        row.className = 'pepf-si pepf-evidence-detail-row';
        row.innerHTML = '<div class="pepf-ed-line1">' +
                '<span class="pepf-si-no">3.2.' + pepfEvidenceDetailIdx + '</span>' +
                '<input type="text" class="pepf-inp pepf-evidence-desc-inp" name="pepf_evidence_desc[]">' +
                '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="\u0e40\u0e02\u0e35\u0e22\u0e19\u0e14\u0e49\u0e27\u0e22\u0e25\u0e32\u0e22\u0e21\u0e37\u0e2d" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
            '</div>' +
            '<div class="pepf-ed-line2">' +
                '<span class="pepf-fl">กลุ่มตรวจ</span>' +
                '<select class="pepf-sel pepf-lab-unit-sel lab-unit-multi" multiple size="3" title="-- กลุ่มตรวจพิสูจน์ (เลือกได้หลายข้อ) --">' + pepfLabUnitOptions + '</select>' +
                '<input type="hidden" class="lab-unit-value" name="pepf_lab_unit[]" value="">' +
                '<span class="pepf-fl" style="margin-left:4px;">จำนวน</span>' +
                '<input type="text" class="pepf-inp-s" name="pepf_evidence_qty[]" style="max-width:40px;">' +
                '<button type="button" class="pepf-del-btn" onclick="this.closest(\'.pepf-evidence-detail-row\').remove(); pepfRenumberEvidenceDetails();">×</button>' +
            '</div>';
        container.appendChild(row);
    };

    // Show full selected text on hover for long group names
    $(document).on('change', '#personEvidenceFormPdfModal select.pepf-lab-unit-sel', function() {
        var txts = $(this).find('option:selected').map(function() { return $(this).text(); }).get();
        $(this).attr('title', txts.length ? txts.join(', ') : '-- กลุ่มตรวจพิสูจน์ --');
    });
    window.pepfRenumberEvidenceDetails = function() {
        var rows = document.querySelectorAll('#pepf_evidence_detail_container .pepf-evidence-detail-row');
        rows.forEach(function(r, i) {
            var no = r.querySelector('.pepf-si-no');
            if (no) no.textContent = '3.2.' + (i + 1);
        });
        pepfEvidenceDetailIdx = rows.length;
    };

    // ===== Photo constants =====
    var PEPF_PHOTOS_PER_PAGE = 35;

    // ===== Photo file handling =====
    window.pepfHandlePhotoFiles = function(files) {
        Array.from(files).forEach(function(file) {
            var fileId = 'pepf_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
            var objectUrl = URL.createObjectURL(file);
            attachmentStoreEV8.push({ file: file, id: fileId, src: objectUrl, name: file.name });
        });
        pepfRenderPhotosFromStore();
        if (typeof renderEV8AttachmentGrid === 'function') renderEV8AttachmentGrid();
    };

    // ===== Photo preview =====
    window.pepfPreviewPhotos = function(input) {
        if (input.files && input.files.length > 0) {
            Array.from(input.files).forEach(function(file) {
                var fileId = Date.now() + '_' + Math.random().toString(16).slice(2);
                var objectUrl = URL.createObjectURL(file);
                attachmentStoreEV8.push({ file: file, id: fileId, src: objectUrl, name: file.name, existing: false });
            });
            input.value = '';
            pepfRenderPhotosFromStore();
            if (typeof renderEV8AttachmentGrid === 'function') renderEV8AttachmentGrid();
        }
    };

    // ===== Render photos (35 per page grid) =====
    window.pepfRenderPhotosFromStore = function() {
        var photos = (typeof attachmentStoreEV8 !== 'undefined') ? attachmentStoreEV8 : [];
        var pagesNeeded = Math.max(1, Math.ceil(photos.length / PEPF_PHOTOS_PER_PAGE));

        // Render first page grid
        var grid1 = document.getElementById('pepf_photo_grid_1');
        if (grid1) {
            grid1.innerHTML = '';
            var endIdx = Math.min(PEPF_PHOTOS_PER_PAGE, photos.length);
            for (var i = 0; i < endIdx; i++) {
                grid1.appendChild(pepfCreatePhotoCell(photos[i], i));
            }
        }

        // Render extra pages
        var extraContainer = document.getElementById('pepf_extra_photo_pages');
        if (extraContainer) {
            extraContainer.innerHTML = '';
            for (var p = 2; p <= pagesNeeded; p++) {
                var startIdx = (p - 1) * PEPF_PHOTOS_PER_PAGE;
                var endIdx = Math.min(p * PEPF_PHOTOS_PER_PAGE, photos.length);
                extraContainer.appendChild(pepfCreatePhotoPageElement(p, photos, startIdx, endIdx));
            }
        }

        pepfUpdatePhotoAmount();
        if (typeof pepfUpdatePageNumbers === 'function') pepfUpdatePageNumbers();
    };

    function pepfCreatePhotoCell(item, idx) {
        var wrapper = document.createElement('div');
        wrapper.style.cssText = 'display:flex; flex-direction:column;';
        
        var cell = document.createElement('div');
        cell.className = 'pepf-photo-cell';
        cell.style.position = 'relative';
        
        var img = document.createElement('img');
        img.src = item.src || item.base64 || '';
        img.alt = item.name || '';
        cell.appendChild(img);
        
        var delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'pepf-cell-delete';
        delBtn.innerHTML = '×';
        delBtn.onclick = function() { pepfRemovePhoto(item.id); };
        cell.appendChild(delBtn);
        
        wrapper.appendChild(cell);
        
        var filename = document.createElement('div');
        filename.className = 'pepf-cell-filename';
        filename.textContent = item.name || 'photo.jpg';
        wrapper.appendChild(filename);
        
        return wrapper;
    }

    function pepfCreatePhotoPageElement(pageNum, photos, startIdx, endIdx) {
        var page = document.createElement('div');
        page.className = 'pepf-page pepf-photo-page';
        page.setAttribute('data-photo-page', pageNum);
        
        var startName = photos[startIdx] ? (photos[startIdx].name || 'photo') : '';
        var endName = photos[endIdx - 1] ? (photos[endIdx - 1].name || 'photo') : '';
        
        // Get report number and year
        var rptNo = document.getElementById('pepf_report_ref') ? document.getElementById('pepf_report_ref').value : '';
        var rptYear = document.getElementById('pepf_report_year') ? document.getElementById('pepf_report_year').value : '';
        
        page.innerHTML =
            '<div class="pepf-header">' +
                '<div class="pepf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>' +
                '<div class="pepf-header-center">' +
                    '<div class="pepf-title-main">รายงานการตรวจเก็บวัตถุพยาน</div>' +
                    '<div class="pepf-title-sub">บันทึกการถ่ายภาพ (ต่อ)</div>' +
                '</div>' +
                '<div class="pepf-header-right">' +
                    '<div class="pepf-doc-box">' +
                        '<div class="pepf-doc-line">รายงานที่ <span class="pepf-rpt-no-mirror">' + rptNo + '</span> / 25<span class="pepf-rpt-year-mirror">' + rptYear + '</span></div>' +
                        '<div class="pepf-doc-line">หน้าที่ <span class="pepf-cur-page"></span> / <span class="pepf-total-page"></span></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div style="margin-bottom:4px;">' +
                '<div class="pepf-fr" style="flex-wrap:nowrap;">' +
                    '<span class="pepf-fl">รหัสภาพถ่ายที่</span>' +
                    '<span class="pepf-inp" style="flex:1; min-width:40px; text-align:center;">' + startName + '</span>' +
                    '<span class="pepf-fl">ถึง</span>' +
                    '<span class="pepf-inp" style="flex:1; min-width:40px; text-align:center;">' + endName + '</span>' +
                '</div>' +
            '</div>';
        
        var grid = document.createElement('div');
        grid.className = 'pepf-photo-grid';
        for (var i = startIdx; i < endIdx; i++) {
            grid.appendChild(pepfCreatePhotoCell(photos[i], i));
        }
        page.appendChild(grid);
        
        var footer = document.createElement('div');
        footer.className = 'pepf-footer';
        footer.innerHTML =
            '<div class="pepf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>' +
            '<div class="pepf-footer-right">บัญชีแนบท้ายคำสั่ง สพฐ.ตร.<br>ที่ ๘๔๘/๒๕๖๑</div>';
        page.appendChild(footer);
        
        return page;
    }

    window.pepfUpdatePhotoAmount = function() {
        var photos = (typeof attachmentStoreEV8 !== 'undefined' && Array.isArray(attachmentStoreEV8)) ? attachmentStoreEV8 : [];
        var totalPhotos = photos.length;
        
        var modal = document.getElementById('personEvidenceFormPdfModal');
        if (!modal) return;

        var startInput = modal.querySelector('input[name="photo_id_start_ev8"]');
        var endInput = modal.querySelector('input[name="photo_id_end_ev8"]');
        var amountInput = modal.querySelector('input[name="photo_amount_ev8"]');

        if (startInput && endInput && amountInput) {
            if (totalPhotos > 0) {
                var lastIdxPage1 = Math.min(PEPF_PHOTOS_PER_PAGE, totalPhotos) - 1;
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


    window.pepfRemovePhoto = function(fileId) {
        if (typeof attachmentStoreEV8 === 'undefined') return;
        var item = attachmentStoreEV8.find(function(x) { return x.id === fileId; });
        if (item && item.existing) {
            deletedExistingPhotosEV8.push({ file_id: item.db_file_id, db_filename: item.db_filename });
        }
        attachmentStoreEV8 = attachmentStoreEV8.filter(function(x) { return x.id !== fileId; });
        pepfRenderPhotosFromStore();
        if (typeof renderEV8AttachmentGrid === 'function') renderEV8AttachmentGrid();
    };

    // ===== เพิ่มหน้ารูปถ่าย =====
    var pepfPhotoPageCount = 1;
    window.pepfAddPhotoPage = function() {
        pepfPhotoPageCount++;
        var container = document.getElementById('pepf_extra_photo_pages');
        if (!container) return;

        var rptNo = document.getElementById('pepf_report_ref') ? document.getElementById('pepf_report_ref').value : '';
        var rptYear = document.getElementById('pepf_report_year') ? document.getElementById('pepf_report_year').value : '';

        var page = document.createElement('div');
        page.className = 'pepf-page pepf-photo-page';
        page.innerHTML =
            '<div class="pepf-header">' +
                '<div class="pepf-header-logo"><img src="./images/office-of-police-forensic-icon.jpg" alt=""></div>' +
                '<div class="pepf-header-center">' +
                    '<div class="pepf-title-main">รายงานการตรวจเก็บวัตถุพยาน</div>' +
                    '<div class="pepf-title-sub">บันทึกการถ่ายภาพ (ต่อ)</div>' +
                '</div>' +
                '<div class="pepf-header-right">' +
                    '<div class="pepf-doc-box">' +
                        '<div class="pepf-doc-line">รายงานที่ <span class="pepf-rpt-no-mirror">' + rptNo + '</span> / 25<span class="pepf-rpt-year-mirror">' + rptYear + '</span></div>' +
                        '<div class="pepf-doc-line">หน้าที่ <span class="pepf-cur-page"></span> / <span class="pepf-total-page"></span></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div style="text-align:right; margin-bottom:4px;">' +
                '<button type="button" class="pepf-del-btn" style="font-size:10px; padding:1px 8px;" onclick="pepfRemovePhotoPage(this)">× ลบหน้านี้</button>' +
            '</div>' +
            '<div class="pepf-photo-slots" style="flex:1; display:flex; flex-direction:column; gap:6px;">' +
                '<div class="pepf-photo-slot" onclick="pepfChoosePhotoSource()">' +
                    '<div class="pepf-slot-placeholder"><i class="fas fa-camera" style="font-size:24px;"></i><br>ว่าง</div>' +
                '</div>' +
                '<div class="pepf-photo-slot" onclick="pepfChoosePhotoSource()">' +
                    '<div class="pepf-slot-placeholder"><i class="fas fa-camera" style="font-size:24px;"></i><br>ว่าง</div>' +
                '</div>' +
                '<div class="pepf-photo-slot" onclick="pepfChoosePhotoSource()">' +
                    '<div class="pepf-slot-placeholder"><i class="fas fa-camera" style="font-size:24px;"></i><br>ว่าง</div>' +
                '</div>' +
            '</div>' +
            '<div class="pepf-footer">' +
                '<div class="pepf-footer-left"><strong>หมายเหตุ</strong> กรณีมีข้อมูลมากกว่าที่กำหนดไว้สามารถเขียนเพิ่มเติมได้ที่ด้านหลังกระดาษ</div>' +
                '<div class="pepf-footer-right">บัญชีแนบท้ายคำสั่ง สพฐ.ตร.<br>ที่ ๘๔๘/๒๕๖๑</div>' +
            '</div>';
        container.appendChild(page);
        pepfRenderPhotosFromStore();
        pepfUpdatePageNumbers();
    };

    window.pepfRemovePhotoPage = function(btn) {
        var page = btn.closest('.pepf-photo-page');
        if (page && page.parentElement.id === 'pepf_extra_photo_pages') {
            page.remove();
            pepfPhotoPageCount--;
            pepfRenderPhotosFromStore();
            pepfUpdatePageNumbers();
        }
    };

    // ===== Auto-fill position from user select =====
    $(document).on('change', '#personEvidenceFormPdfModal .pepf-user-select', function() {
        var pos = $(this).find(':selected').data('position') || '';
        var target = $(this).data('pos-target');
        if (target) $(target).val(pos);
    });

    // ===== Canvas drawing (ลายเซ็น) =====
    function initPepfCanvas(canvasId) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        canvas.width = canvas.offsetWidth || canvas.parentElement.offsetWidth;
        canvas.height = canvas.offsetHeight || parseInt(canvas.style.height) || 50;

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

        canvas.addEventListener('mousedown', function(e) { drawing = true; ctx.beginPath(); var p = getPos(e); ctx.moveTo(p.x, p.y); e.preventDefault(); });
        canvas.addEventListener('mousemove', function(e) { if (!drawing) return; var p = getPos(e); ctx.lineWidth = 1.5; ctx.lineCap = 'round'; ctx.strokeStyle = '#000'; ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); });
        canvas.addEventListener('mouseup', function() { drawing = false; });
        canvas.addEventListener('mouseleave', function() { drawing = false; });
        canvas.addEventListener('touchstart', function(e) { drawing = true; ctx.beginPath(); var p = getPos(e); ctx.moveTo(p.x, p.y); e.preventDefault(); }, { passive: false });
        canvas.addEventListener('touchmove', function(e) { if (!drawing) return; var p = getPos(e); ctx.lineWidth = 1.5; ctx.lineCap = 'round'; ctx.strokeStyle = '#000'; ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }, { passive: false });
        canvas.addEventListener('touchend', function() { drawing = false; });
    }

    // ===== Dropzone handlers =====
    if (document.getElementById('pepf_photo_dropzone')) {
        document.getElementById('pepf_photo_dropzone').addEventListener('click', function() {
            document.getElementById('pepf_photo_input_gallery').click();
        });
        document.getElementById('pepf_photo_dropzone').addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('dragover');
        });
        document.getElementById('pepf_photo_dropzone').addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('dragover');
        });
        document.getElementById('pepf_photo_dropzone').addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('dragover');
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                pepfHandlePhotoFiles(e.dataTransfer.files);
            }
        });
    }

    // Init canvas เมื่อ modal แสดง
    document.getElementById('personEvidenceFormPdfModal').addEventListener('shown.bs.modal', function() {
        initPepfCanvas('pepf_sig_receiver');
        initPepfCanvas('pepf_sig_sender');
        $('#personEvidenceFormPdfModal select.pepf-lab-unit-sel').trigger('change');
        pepfToggleNotifyOther();
        pepfSyncReportNo();
        if (typeof window.pepfRefreshAutoWrapGroups === 'function') window.pepfRefreshAutoWrapGroups();
        
        // Populate mirror spans for report number and year
        var rptNo = document.getElementById('pepf_report_ref') ? document.getElementById('pepf_report_ref').value : '';
        var rptYear = document.getElementById('pepf_report_year') ? document.getElementById('pepf_report_year').value : '';
        
        var modalEl = document.getElementById('personEvidenceFormPdfModal');
        if (modalEl) {
            modalEl.querySelectorAll('.pepf-rpt-no-mirror').forEach(function(el) { el.textContent = rptNo; });
            modalEl.querySelectorAll('.pepf-rpt-year-mirror').forEach(function(el) { el.textContent = rptYear; });
        }
        
        // Render photos from store (with delay to ensure data is loaded)
        setTimeout(function() {
            if (typeof pepfRenderPhotosFromStore === 'function') {
                pepfRenderPhotosFromStore();
            }
        }, 300);
        
        pepfUpdatePageNumbers();
    });

    window.pepfClearCanvas = function(canvasId) {
        var canvas = document.getElementById(canvasId);
        if (canvas) {
            var ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
    };

    // ===== Switch ฟอร์ม =====
    window.switchToPersonEvidencePdfForm = function() {
        syncPersonEvidenceFormData('incidentCheckListFormPersonEvidence', 'personEvidenceFormPdf');

        // sync hidden fields: standard → PDF
        var docNo = $('#doc_no_ev8').val() || '';
        var rptNo = $('#report_no_ev8').val() || '';
        $('#pepf_receiveNoti_id').val($('#receiveNoti_id_ev8').val());
        $('#pepf_doc_no').val(docNo);
        $('#pepf_report_no').val(rptNo);

        var stdModal = bootstrap.Modal.getInstance(document.getElementById('addCheckListModalPersonEvidence'));
        if (stdModal) stdModal.hide();
        setTimeout(function() {
            var pdfModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('personEvidenceFormPdfModal'));
            pdfModal.show();
        }, 400);
    };

    window.switchToPersonEvidenceStdForm = function() {
        syncPersonEvidenceFormData('personEvidenceFormPdf', 'incidentCheckListFormPersonEvidence');

        // sync hidden fields: PDF → standard
        var docNo = $('#pepf_doc_no').val() || '';
        var rptNo = $('#pepf_report_no').val() || '';
        $('#receiveNoti_id_ev8').val($('#pepf_receiveNoti_id').val());
        $('#doc_no_ev8').val(docNo);
        $('#report_no_ev8').val(rptNo);
        $('#receiveNoti_No_ev8').text(docNo);

        var pdfModal = bootstrap.Modal.getInstance(document.getElementById('personEvidenceFormPdfModal'));
        if (pdfModal) pdfModal.hide();
        setTimeout(function() {
            var stdModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('addCheckListModalPersonEvidence'));
            stdModal.show();
        }, 400);
    };

    window.syncPersonEvidenceFormData = function(fromFormId, toFormId) {
        var fromForm = document.getElementById(fromFormId);
        var toForm = document.getElementById(toFormId);
        if (!fromForm || !toForm) return;

        // Determine direction for prefix mapping: ev8_ <-> pepf_
        var toStdForm = (toFormId === 'incidentCheckListFormPersonEvidence');
        function mapName(name) {
            if (toStdForm) {
                return name.replace(/^pepf_/, 'ev8_');
            } else {
                return name.replace(/^ev8_/, 'pepf_');
            }
        }

        var fromEls = fromForm.querySelectorAll('input, select, textarea');
        var dataMap = {};

        fromEls.forEach(function(el) {
            var name = el.name;
            if (!name || el.type === 'file' || el.type === 'hidden') return;
            if (el.type === 'checkbox') {
                if (!dataMap[name]) dataMap[name] = { type: 'checkbox', values: [] };
                if (el.checked) dataMap[name].values.push(el.value);
            } else if (el.type === 'radio') {
                if (el.checked) dataMap[name] = { type: 'radio', value: el.value };
            } else {
                dataMap[name] = { type: 'text', value: el.value };
            }
        });

        Object.keys(dataMap).forEach(function(name) {
            var info = dataMap[name];
            var targetName = mapName(name);

            var toEls = toForm.querySelectorAll('[name="' + targetName + '"]');
            if (toEls.length === 0) toEls = toForm.querySelectorAll('[name="' + name + '"]');
            if (toEls.length === 0) return;

            if (info.type === 'checkbox') {
                toEls.forEach(function(el) {
                    el.checked = info.values && info.values.indexOf(el.value) !== -1;
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                });
            } else if (info.type === 'radio') {
                toEls.forEach(function(el) {
                    el.checked = (info.value === el.value);
                });
            } else {
                toEls.forEach(function(el) {
                    el.value = info.value || '';
                });
            }
        });

        // Handle notify method mapping explicitly because EV8 std uses "ตามหนังสือ" but PDF uses "ทางหนังสือ"
        (function syncNotifyMethods() {
            var collectChecked = function(name, scope) {
                return Array.prototype.slice.call((scope || document).querySelectorAll('[name="' + name + '"]:checked')).map(function(el) { return el.value; });
            };
            var setCheckedValues = function(name, values, scope) {
                var valueList = Array.isArray(values) ? values : [];
                (scope || document).querySelectorAll('[name="' + name + '"]').forEach(function(el) {
                    el.checked = valueList.indexOf(el.value) !== -1;
                });
            };
            var pickValue = function(selectors) {
                for (var i = 0; i < selectors.length; i++) {
                    var el = document.querySelector(selectors[i]);
                    if (!el) continue;
                    var val = (el.value || '').toString();
                    if (val !== '') return val;
                }
                return '';
            };

            if (fromFormId === 'incidentCheckListFormPersonEvidence') {
                var stdValues = collectChecked('ev8_notify_method[]', fromForm);
                var pdfValues = [];
                stdValues.forEach(function(value) {
                    if (value === 'ตามหนังสือ') pdfValues.push('ทางหนังสือ');
                    else if (value === 'ทางโทรศัพท์') pdfValues.push('ทางโทรศัพท์');
                    else if (value === 'ทางวิทยุสื่อสาร') pdfValues.push('ทางวิทยุสื่อสาร');
                    else if (value === 'อื่นๆ') pdfValues.push('อื่นๆ');
                });
                setCheckedValues('pepf_notify_method[]', pdfValues, toForm);
                var otherTextPdf = toForm.querySelector('#pepf_notify_method_other_text');
                if (otherTextPdf) otherTextPdf.value = pickValue(['#ev8_notify_other_text']);
            } else {
                var sourcePdfValues = collectChecked('pepf_notify_method[]', fromForm);
                var stdMapped = [];
                sourcePdfValues.forEach(function(value) {
                    if (value === 'ทางหนังสือ') stdMapped.push('ตามหนังสือ');
                    else if (value === 'ทางโทรศัพท์') stdMapped.push('ทางโทรศัพท์');
                    else if (value === 'ทางวิทยุสื่อสาร') stdMapped.push('ทางวิทยุสื่อสาร');
                    else if (value === 'อื่นๆ') stdMapped.push('อื่นๆ');
                });
                setCheckedValues('ev8_notify_method[]', stdMapped, toForm);
                var stdOther = toForm.querySelector('#ev8_notify_other_text');
                var stdOtherChecked = stdMapped.indexOf('อื่นๆ') !== -1;
                if (stdOther) {
                    stdOther.value = pickValue(['#pepf_notify_method_other_text']);
                    stdOther.style.display = stdOtherChecked ? '' : 'none';
                    stdOther.disabled = !stdOtherChecked;
                    if (!stdOtherChecked) stdOther.value = '';
                }
            }

            if (typeof window.pepfToggleNotifyOther === 'function') {
                window.pepfToggleNotifyOther();
            }
        })();

        // Handle unit type conversion explicitly (std = select, PDF = checkbox group)
        (function syncUnitType() {
            var pickValue = function(selectors) {
                for (var i = 0; i < selectors.length; i++) {
                    var el = document.querySelector(selectors[i]);
                    if (!el) continue;
                    var val = (el.value || '').toString();
                    if (val !== '') return val;
                }
                return '';
            };
            var collectChecked = function(name, scope) {
                return Array.prototype.slice.call((scope || document).querySelectorAll('[name="' + name + '"]:checked')).map(function(el) { return el.value; });
            };

            if (fromFormId === 'incidentCheckListFormPersonEvidence') {
                var stdType = pickValue(['#ev8_unit_type']);
                var unitName = pickValue(['#ev8_unit_name']);
                toForm.querySelectorAll('[name="pepf_unit_type_check[]"]').forEach(function(el) {
                    el.checked = stdType && el.value === stdType;
                });
                var unitEl = toForm.querySelector('#pepf_unit_name');
                var centerEl = toForm.querySelector('[name="pepf_center_name"]');
                var provinceEl = toForm.querySelector('[name="pepf_province_name"]');
                if (unitEl) unitEl.value = unitName;
                if (centerEl) centerEl.value = stdType === 'ศูนย์พิสูจน์หลักฐาน' ? unitName : '';
                if (provinceEl) provinceEl.value = stdType === 'พิสูจน์หลักฐานจังหวัด' ? unitName : '';
            } else {
                var pdfType = collectChecked('pepf_unit_type_check[]', fromForm)[0] || '';
                var resolvedName = pickValue([
                    pdfType === 'ศูนย์พิสูจน์หลักฐาน' ? '#personEvidenceFormPdf [name="pepf_center_name"]' : '',
                    pdfType === 'พิสูจน์หลักฐานจังหวัด' ? '#personEvidenceFormPdf [name="pepf_province_name"]' : '',
                    '#pepf_unit_name'
                ].filter(Boolean));
                var stdTypeEl = toForm.querySelector('#ev8_unit_type');
                var stdNameEl = toForm.querySelector('#ev8_unit_name');
                if (stdTypeEl) stdTypeEl.value = pdfType;
                if (stdNameEl) stdNameEl.value = resolvedName;
            }
        })();

        // handover_purpose เป็น textarea เดี่ยวทั้งสองฟอร์ม (pepf_handover_purpose <-> ev8_handover_purpose)
        // ถูก sync โดย generic field loop ด้านบนแล้ว โดยคงการขึ้นบรรทัดใหม่ไว้

        pepfSyncReportNo();
    };

    // ===== jQuery click handlers for form switching (match EV7 pattern) =====
    $(document).on('click', '#switchToPdfFormEV8', function(e) {
        e.preventDefault();
        this.checked = false;
        window.switchToPersonEvidencePdfForm();
    });

    $(document).on('click', '#switchToStdFormEV8', function(e) {
        e.preventDefault();
        this.checked = true;
        window.switchToPersonEvidenceStdForm();
    });

    // ===== Reset PDF form =====
    window.resetPersonEvidencePdfForm = function() {
        var form = document.getElementById('personEvidenceFormPdf');
        if (form) form.reset();
        document.querySelectorAll('#personEvidenceFormPdfModal .pepf-rpt-no-mirror, #personEvidenceFormPdfModal .pepf-rpt-year-mirror').forEach(function(el) {
            el.textContent = '';
        });
        [['#pepf_person_items_container', '.pepf-person-item-row', 'pepfRenumberPersonItems'],
         ['#pepf_person_info_container', '.pepf-person-info-row', 'pepfRenumberPersonInfo'],
         ['#pepf_evidence_detail_container', '.pepf-evidence-detail-row', 'pepfRenumberEvidenceDetails'],
         ['#pepf_inspector_container', '.pepf-inspector-row', 'pepfRenumberInspectors']
        ].forEach(function(config) {
            var container = document.querySelector(config[0]);
            if (!container) return;
            var rows = container.querySelectorAll(config[1]);
            rows.forEach(function(row, idx) { if (idx > 0) row.remove(); });
            if (typeof window[config[2]] === 'function') window[config[2]]();
            container.querySelectorAll('input, select, textarea').forEach(function(el) {
                if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
                else el.value = '';
            });
        });
        window.pepfClearCanvas('pepf_sig_receiver');
        window.pepfClearCanvas('pepf_sig_sender');
        var extraPages = document.getElementById('pepf_extra_photo_pages');
        if (extraPages) extraPages.innerHTML = '';
        if (typeof window.pepfRenderPhotosFromStore === 'function') window.pepfRenderPhotosFromStore();
        if (typeof window.pepfUpdatePageNumbers === 'function') window.pepfUpdatePageNumbers();
        if (typeof window.pepfToggleNotifyOther === 'function') window.pepfToggleNotifyOther();
        if (typeof window.pepfRefreshAutoWrapGroups === 'function') window.pepfRefreshAutoWrapGroups();
    };

    // ===== Auto-wrap for section 3.3 continuation lines =====
    (function initPepfAutoWrapGroups() {
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
                var remaining = (rawText || '').replace(/\r?\n/g, ' ');
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

        bindGroup('#personEvidenceFormPdfModal .pepf-33-auto-line');
        bindGroup('#personEvidenceFormPdfModal .pepf-location-auto-line');

        window.pepfRefreshAutoWrapGroups = function() {
            refreshers.forEach(function(refresh) { refresh(); });
        };
    })();
})();
</script>
