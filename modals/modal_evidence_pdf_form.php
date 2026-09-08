<?php
/**
 * Modal: ฟอร์มวัตถุพยาน (Evidence) แบบเสมือนจริง
 * ใช้สำหรับปุ่ม "วัตถุพยาน" ใน incidentChecklist.php
 * Prefix: evpf_ (Evidence PDF Form)
 * UI pattern: อิงตาม form_evidence_preview.html
 * Created: 2026-04-02
 */

// ดึงรายชื่อผู้ตรวจ/ผู้เก็บ
$evpfInspectorOptions = '<option value="" selected disabled>-- เลือก --</option>';
if (isset($pdo)) {
    $qryEvpfInsp = "SELECT t1.user_id,
                           CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname,
                           IFNULL(t3.position_name, '-') AS position_name
                    FROM user_profile t1
                    LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
                    LEFT JOIN user_position t3 ON t1.position_id = t3.position_id
                    ORDER BY t1.user_id DESC";
    $stmtEvpfInsp = $pdo->query($qryEvpfInsp);
    while ($rowInsp = $stmtEvpfInsp->fetch(PDO::FETCH_ASSOC)) {
        $evpfInspectorOptions .= '<option value="' . $rowInsp['user_id'] . '" data-position="' . htmlspecialchars($rowInsp['position_name']) . '" data-fullname="' . htmlspecialchars($rowInsp['fullname']) . '">' . htmlspecialchars($rowInsp['fullname']) . '</option>';
    }
}

$evpfTodayDate = date('Y-m-d');
$evpfTodayTime = date('H:i');
?>

<!-- ===== SCOPED CSS ===== -->
<style>
/* ===== MODAL BODY ===== */
#evidenceFormPdfModal .evpf-body {
    background: #bbb;
    padding: 10px 0;
}

/* ===== PAGE (A4 layout with sidebar) ===== */
#evidenceFormPdfModal .evpf-page {
    width: 210mm;
    min-height: 297mm;
    margin: 10px auto;
    background: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.25);
    display: flex;
    position: relative;
    overflow: hidden;
    font-family: 'Sarabun', sans-serif;
    font-size: 14.5px;
    line-height: 1.6;
    color: #1a1a1a;
}

/* ===== LEFT SIDEBAR (Dark) ===== */
#evidenceFormPdfModal .evpf-sidebar {
    width: 42mm;
    background: #000;
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 10mm 3mm;
    position: relative;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    flex-shrink: 0;
}
#evidenceFormPdfModal .evpf-sidebar .evpf-logo-img {
    width: 28mm;
    height: auto;
    filter: brightness(0) invert(1);
    position: absolute;
    top: 10mm;
}
#evidenceFormPdfModal .evpf-sidebar .evpf-text-group {
    display: flex;
    flex-direction: row;
    align-items: center;
}
#evidenceFormPdfModal .evpf-sidebar .evpf-vertical-text {
    writing-mode: vertical-rl;
    text-orientation: mixed;
    font-size: 42px;
    font-weight: 700;
    letter-spacing: 4px;
    white-space: nowrap;
    color: #c0c0c0;
}
#evidenceFormPdfModal .evpf-sidebar .evpf-vertical-text-en {
    writing-mode: vertical-rl;
    text-orientation: mixed;
    font-size: 20px;
    font-weight: 400;
    letter-spacing: 3px;
    color: #fff;
    margin-top: 5mm;
}

/* ===== RIGHT CONTENT ===== */
#evidenceFormPdfModal .evpf-content {
    flex: 1;
    padding: 8mm 8mm 6mm 8mm;
    display: flex;
    flex-direction: column;
}

/* Title */
#evidenceFormPdfModal .evpf-title {
    text-align: center;
    font-size: 26px;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 2px;
}
#evidenceFormPdfModal .evpf-subtitle {
    text-align: center;
    font-size: 14px;
    font-weight: 600;
    color: #555;
    letter-spacing: 3px;
    margin-bottom: 5mm;
}

/* Form rows */
#evidenceFormPdfModal .evpf-fr {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    margin-bottom: 2px;
    min-height: 20px;
    font-size: 14.5px;
}
#evidenceFormPdfModal .evpf-fl {
    font-weight: 600;
    white-space: nowrap;
    margin-right: 4px;
}
#evidenceFormPdfModal .evpf-fd {
    flex: 1;
    border-bottom: 1px dotted #888;
    min-width: 40px;
    padding: 0 4px;
    min-height: 18px;
}
#evidenceFormPdfModal .evpf-fd-short {
    display: inline-block;
    border-bottom: 1px dotted #888;
    min-width: 60px;
    padding: 0 4px;
    text-align: center;
}

/* Input fields (styled like dotted underline) */
#evidenceFormPdfModal .evpf-inp {
    border: none;
    border-bottom: 1px dotted #888;
    background: transparent;
    font-family: 'Sarabun', sans-serif;
    font-size: 14.5px;
    padding: 0 4px;
    height: 24px;
    outline: none;
    color: #1a1a1a;
    flex: 1;
    min-width: 40px;
    margin: 0 2px;
}
#evidenceFormPdfModal textarea.evpf-inp {
    border: none;
    border-bottom: 1px dotted #888;
    background: transparent;
    font-family: 'Sarabun', sans-serif;
    font-size: 14.5px;
    padding: 0 4px;
    min-height: 24px;
    outline: none;
    color: #1a1a1a;
    flex: 1;
    min-width: 40px;
    margin: 0 2px;
    resize: none;
    overflow: hidden;
    line-height: 1.4;
    display: block;
    width: 100%;
    box-sizing: border-box;
}
#evidenceFormPdfModal .evpf-inp-m {
    border: none;
    border-bottom: 1px dotted #888;
    background: transparent;
    font-family: 'Sarabun', sans-serif;
    font-size: 14.5px;
    padding: 0 4px;
    height: 24px;
    outline: none;
    color: #1a1a1a;
    min-width: 60px;
    margin: 0 2px;
    text-align: center;
}
#evidenceFormPdfModal .evpf-inp-full {
    border: none;
    border-bottom: 1px dotted #888;
    background: transparent;
    font-family: 'Sarabun', sans-serif;
    font-size: 14.5px;
    padding: 0 4px;
    height: 24px;
    outline: none;
    color: #1a1a1a;
    width: 100%;
    display: block;
    margin-bottom: 2px;
}

/* SELECT styled like dotted line */
#evidenceFormPdfModal .evpf-sel {
    border: none;
    border-bottom: 1px dotted #888;
    background: transparent;
    font-family: 'Sarabun', sans-serif;
    font-size: 14.5px;
    padding: 0;
    height: 24px;
    outline: none;
    color: #1a1a1a;
    flex: 1;
    min-width: 40px;
    margin: 0 2px;
    cursor: pointer;
}

/* Section label */
#evidenceFormPdfModal .evpf-section-label {
    font-weight: 700;
    font-size: 15px;
    margin-top: 4mm;
    margin-bottom: 2mm;
    text-decoration: underline;
}

/* Chain of Custody Table */
#evidenceFormPdfModal .evpf-custody-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin-top: 3mm;
    margin-bottom: 3mm;
}
#evidenceFormPdfModal .evpf-custody-table th,
#evidenceFormPdfModal .evpf-custody-table td {
    border: 1px solid #333;
    padding: 3px 5px;
    text-align: center;
    vertical-align: middle;
}
#evidenceFormPdfModal .evpf-custody-table th {
    background: #f0e6d2;
    font-weight: 700;
    font-size: 12.5px;
}
#evidenceFormPdfModal .evpf-custody-table td {
    font-size: 12.5px;
    min-height: 20px;
}
#evidenceFormPdfModal .evpf-custody-table td input {
    border: none;
    background: transparent;
    font-family: 'Sarabun', sans-serif;
    font-size: 12.5px;
    padding: 0 2px;
    outline: none;
    color: #1a1a1a;
    width: 100%;
    text-align: center;
}
#evidenceFormPdfModal .evpf-custody-table td input.text-left {
    text-align: left;
}
/* Fix date input for iOS/iPad */
#evidenceFormPdfModal .evpf-custody-table td input[type="date"],
#evidenceFormPdfModal input[type="date"] {
    -webkit-appearance: none;
    appearance: none;
    min-height: 32px;
    padding: 6px 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    background: #fff;
    cursor: pointer;
    font-size: 14px;
    touch-action: manipulation;
    -webkit-user-select: none;
    user-select: none;
}
#evidenceFormPdfModal .evpf-custody-table td input[type="date"]::-webkit-calendar-picker-indicator,
#evidenceFormPdfModal input[type="date"]::-webkit-calendar-picker-indicator {
    opacity: 1;
    cursor: pointer;
    padding: 5px;
    background-size: 18px;
}
/* iPad: ป้องกัน focus ค้าง */
#evidenceFormPdfModal input[type="date"]:focus {
    outline: 2px solid #0d6efd;
    outline-offset: 1px;
}
@supports (-webkit-touch-callout: none) {
    /* iOS/iPad specific */
    #evidenceFormPdfModal input[type="date"] {
        min-height: 44px;
        font-size: 16px; /* ป้องกัน zoom */
        padding: 8px 12px;
        -webkit-tap-highlight-color: transparent;
    }
    #evidenceFormPdfModal input[type="date"]::-webkit-date-and-time-value {
        text-align: center;
    }
}
/* ป้องกัน date picker ค้างบน iPad */
#evidenceFormPdfModal input[type="date"]:active,
#evidenceFormPdfModal input[type="date"]:focus {
    -webkit-tap-highlight-color: transparent;
}

/* Condition section */
#evidenceFormPdfModal .evpf-condition-section {
    margin-top: 3mm;
    font-size: 14px;
}
#evidenceFormPdfModal .evpf-condition-section .evpf-section-title {
    font-weight: 700;
    font-size: 14px;
    margin-bottom: 2mm;
}

/* Checkbox (PDF-style square) */
#evidenceFormPdfModal .evpf-cb {
    appearance: none;
    -webkit-appearance: none;
    width: 13px;
    height: 13px;
    border: 1.5px solid #333;
    margin-right: 4px;
    cursor: pointer;
    position: relative;
    vertical-align: middle;
    flex-shrink: 0;
    background: #fff;
}
#evidenceFormPdfModal .evpf-cb:checked::after {
    content: '✓';
    font-size: 12px;
    font-weight: 700;
    position: absolute;
    top: -3px;
    left: 0px;
    color: #000;
}
#evidenceFormPdfModal .evpf-ck {
    display: inline-flex;
    align-items: center;
    margin-right: 10px;
    margin-bottom: 2px;
    cursor: pointer;
}

/* QR Code section */
#evidenceFormPdfModal .evpf-qr-section {
    margin-top: auto;
    display: flex;
    justify-content: center;
    padding-top: 3mm;
}

/* Focus highlight */
#evidenceFormPdfModal .evpf-inp:focus,
#evidenceFormPdfModal .evpf-inp-m:focus,
#evidenceFormPdfModal .evpf-inp-full:focus,
#evidenceFormPdfModal .evpf-sel:focus {
    border-bottom-color: #0d6efd;
    transition: border-color 0.2s;
}

/* Add/Remove buttons */
#evidenceFormPdfModal .evpf-add-btn {
    font-size: 10px;
    padding: 1px 8px;
    border: 1px dashed #888;
    background: #f8f8f8;
    cursor: pointer;
    color: #333;
    margin: 3px 0;
    font-family: 'Sarabun', sans-serif;
}
#evidenceFormPdfModal .evpf-add-btn:hover { background: #e0e0e0; }
#evidenceFormPdfModal .evpf-del-btn {
    font-size: 9px;
    padding: 0 4px;
    border: 1px solid #ccc;
    background: #fff;
    cursor: pointer;
    color: #c00;
    font-family: 'Sarabun', sans-serif;
    line-height: 1.5;
}
#evidenceFormPdfModal .evpf-del-btn:hover { background: #fee; }
#evidenceFormPdfModal .evpf-restore-btn {
    font-size: 9px;
    padding: 0 4px;
    border: 1px solid #28a745;
    background: #d4edda;
    cursor: pointer;
    color: #155724;
    font-family: 'Sarabun', sans-serif;
    line-height: 1.5;
}
#evidenceFormPdfModal .evpf-restore-btn:hover { background: #c3e6cb; }
#evidenceFormPdfModal .evpf-hidden-counter {
    display: none;
    font-size: 12px;
    color: #856404;
    background: #fff3cd;
    padding: 4px 10px;
    border-radius: 4px;
    margin-left: 10px;
}

/* Victim table */
#evidenceFormPdfModal .evpf-victim-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin-top: 2mm;
    margin-bottom: 2mm;
}
#evidenceFormPdfModal .evpf-victim-table th,
#evidenceFormPdfModal .evpf-victim-table td {
    border: 1px solid #333;
    padding: 3px 5px;
    text-align: center;
    vertical-align: middle;
}
#evidenceFormPdfModal .evpf-victim-table th {
    background: #f0e6d2;
    font-weight: 700;
    font-size: 12.5px;
}
#evidenceFormPdfModal .evpf-victim-table td {
    font-size: 12.5px;
}
#evidenceFormPdfModal .evpf-victim-table td input,
#evidenceFormPdfModal .evpf-victim-table td select {
    border: none;
    background: transparent;
    font-family: 'Sarabun', sans-serif;
    font-size: 12.5px;
    padding: 0 2px;
    outline: none;
    color: #1a1a1a;
    width: 100%;
}
#evidenceFormPdfModal .evpf-victim-table td input.text-left,
#evidenceFormPdfModal .evpf-victim-table td select {
    text-align: left;
}
#evidenceFormPdfModal .evpf-victim-table td input.text-center {
    text-align: center;
}

/* Evidence detail table */
#evidenceFormPdfModal .evpf-evidence-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    margin-top: 2mm;
    margin-bottom: 2mm;
}
#evidenceFormPdfModal .evpf-evidence-table th,
#evidenceFormPdfModal .evpf-evidence-table td {
    border: 1px solid #333;
    padding: 3px 5px;
    text-align: center;
    vertical-align: middle;
}
#evidenceFormPdfModal .evpf-evidence-table th {
    background: #f0e6d2;
    font-weight: 700;
    font-size: 12.5px;
}
#evidenceFormPdfModal .evpf-evidence-table td {
    font-size: 12.5px;
}
#evidenceFormPdfModal .evpf-evidence-table td input {
    border: none;
    background: transparent;
    font-family: 'Sarabun', sans-serif;
    font-size: 12.5px;
    padding: 0 2px;
    outline: none;
    color: #1a1a1a;
    width: 100%;
    text-align: left;
}

/* Textarea in tables - auto-grow */
#evidenceFormPdfModal td textarea {
    border: none;
    background: transparent;
    font-family: 'Sarabun', sans-serif;
    font-size: 12.5px;
    padding: 0 2px;
    outline: none;
    color: #1a1a1a;
    width: 100%;
    text-align: left;
    resize: none;
    overflow: hidden;
    min-height: 22px;
    line-height: 1.4;
    display: block;
    box-sizing: border-box;
}
#evidenceFormPdfModal td textarea.text-center {
    text-align: center;
}
</style>

<!-- ===== MODAL ===== -->
<div class="modal fade" id="evidenceFormPdfModal" aria-labelledby="evidenceFormPdfModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 880px; margin: 1.75rem auto;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="evidenceFormPdfModalLabel">
                    <i class="fas fa-box-open fa-lg me-2"></i> วัตถุพยาน (Evidence)
                </h5>
                <button type="button" class="btn-close btn-close-white js-close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body evpf-body p-0" style="max-height: 80vh; overflow-y: auto; position:relative;">
                <!-- Loading Overlay -->
                <div id="evpfLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track"><div class="csims-bar-fill"></div></div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>

                <form id="evidenceFormPdf" novalidate>
                    <input type="hidden" id="evpf_incident_id" name="evpf_incident_id">

                    <!-- =============== PAGE 1 (Evidence Form) =============== -->
                    <div class="evpf-page">

                        <!-- LEFT SIDEBAR -->
                        <div class="evpf-sidebar">
                            <img src="./images/icon-forensic-police.png" alt="Logo" class="evpf-logo-img">
                            <div class="evpf-text-group">
                                <div class="evpf-vertical-text-en">Forensic Police</div>
                                <div class="evpf-vertical-text">พิสูจน์หลักฐานตำรวจ</div>
                            </div>
                        </div>

                        <!-- RIGHT CONTENT -->
                        <div class="evpf-content">

                            <!-- Title -->
                            <div class="evpf-title">วัตถุพยาน</div>
                            <div class="evpf-subtitle">EVIDENCE</div>

                            <!-- สถานีตำรวจ & คดี -->
                            <div class="evpf-fr">
                                <span class="evpf-fl">สถานีตำรวจ</span>
                                <input type="text" class="evpf-inp" name="evpf_station_name" id="evpf_station_name" style="max-width:200px;">
                                <span class="evpf-fl" style="margin-left:8px;">คดี</span>
                                <input type="text" class="evpf-inp" name="evpf_case_no" id="evpf_case_no" readonly>
                            </div>

                            <!-- สถานที่เกิดเหตุ -->
                            <div class="evpf-fr" style="flex-wrap:wrap;">
                                <span class="evpf-fl">สถานที่เกิดเหตุ</span>
                                <textarea rows="1" class="evpf-inp" name="evpf_location_detail" id="evpf_location_detail" oninput="evpfAutoGrow(this)"></textarea>
                            </div>

                            <!-- วันที่เกิดเหตุ / เวลา -->
                            <div class="evpf-fr">
                                <span class="evpf-fl">วันที่เกิดเหตุ</span>
                                <input type="date" class="evpf-inp-m" name="evpf_incident_date" id="evpf_incident_date" value="<?= $evpfTodayDate ?>" style="max-width:180px;">
                                <span class="evpf-fl" style="margin-left:4px;">เวลาประมาณ</span>
                                <input type="time" class="evpf-inp-m" name="evpf_incident_time" id="evpf_incident_time" value="<?= $evpfTodayTime ?>" style="max-width:100px;">
                                <span class="evpf-fl">น.</span>
                            </div>

                            <!-- ผู้ต้องหา / ผู้ต้องสงสัย / ผู้เสียหาย -->
                            <div class="evpf-section-label" style="margin-top:2mm;">ผู้ต้องหา / ผู้ต้องสงสัย / ผู้เสียหาย</div>
                            <table class="evpf-victim-table" id="evpf_victim_table">
                                <thead>
                                    <tr>
                                        <th style="width:8%;">ลำดับ</th>
                                        <th style="width:28%;">ประเภท</th>
                                        <th style="width:45%;">ชื่อ-นามสกุล</th>
                                        <th style="width:13%;">อายุ (ปี)</th>
                                        <th style="width:6%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="evpf_victim_body">
                                    <tr>
                                        <td>1</td>
                                        <td>
                                            <select name="evpf_victim_type[]">
                                                <option value="">-- เลือก --</option>
                                                <option value="ผู้ต้องหา">ผู้ต้องหา</option>
                                                <option value="ผู้ต้องสงสัย">ผู้ต้องสงสัย</option>
                                                <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                                            </select>
                                        </td>
                                        <td><textarea rows="1" class="text-left" name="evpf_victim_name[]" placeholder="ชื่อ-นามสกุล"></textarea></td>
                                        <td><input type="text" class="text-center" name="evpf_victim_age[]" maxlength="3" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"></td>
                                        <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveVictimRow(this)">✕</button></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="evpf-add-btn" onclick="evpfAddVictimRow()">+ เพิ่มรายการ</button>

                            <!-- วันที่เก็บวัตถุพยาน / เวลา -->
                            <div class="evpf-fr">
                                <span class="evpf-fl">วันที่เก็บวัตถุพยาน</span>
                                <input type="date" class="evpf-inp-m" name="evpf_collect_date" id="evpf_collect_date" value="<?= $evpfTodayDate ?>" style="max-width:180px;">
                                <span class="evpf-fl" style="margin-left:4px;">เวลาประมาณ</span>
                                <input type="time" class="evpf-inp-m" name="evpf_collect_time" id="evpf_collect_time" value="<?= $evpfTodayTime ?>" style="max-width:100px;">
                                <span class="evpf-fl">น.</span>
                            </div>

                            <!-- ชื่อผู้เก็บวัตถุพยาน -->
                            <div class="evpf-fr">
                                <span class="evpf-fl">ชื่อผู้เก็บวัตถุพยาน</span>
                                <select class="evpf-sel" name="evpf_collector_id" id="evpf_collector_id">
                                    <?= $evpfInspectorOptions ?>
                                </select>
                            </div>

                            <!-- ลักษณะ / จำนวน / ตำแหน่ง วัตถุพยานที่ตรวจพบ -->
                            <div class="evpf-section-label">ลักษณะ / จำนวน / ตำแหน่ง วัตถุพยานที่ตรวจพบ</div>
                            <table class="evpf-evidence-table" id="evpf_evidence_table">
                                <thead>
                                    <tr>
                                        <th style="width:8%;">ลำดับ</th>
                                        <th style="width:50%;">รายละเอียดวัตถุพยาน</th>
                                        <th style="width:15%;">จำนวน</th>
                                        <th style="width:21%;">ตำแหน่งที่พบ</th>
                                        <th style="width:6%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="evpf_evidence_body">
                                    <tr>
                                        <td>1</td>
                                        <td><textarea rows="1" name="evpf_evidence_detail[]" placeholder="รายละเอียดวัตถุพยาน..."></textarea></td>
                                        <td><input type="text" name="evpf_evidence_qty[]" style="text-align:center;"></td>
                                        <td><input type="text" name="evpf_evidence_position[]" style="text-align:center;"></td>
                                        <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveEvidenceRow(this)">✕</button></td>
                                    </tr>
                                    <tr>
                                        <td>2</td>
                                        <td><textarea rows="1" name="evpf_evidence_detail[]"></textarea></td>
                                        <td><input type="text" name="evpf_evidence_qty[]" style="text-align:center;"></td>
                                        <td><input type="text" name="evpf_evidence_position[]" style="text-align:center;"></td>
                                        <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveEvidenceRow(this)">✕</button></td>
                                    </tr>
                                    <tr>
                                        <td>3</td>
                                        <td><textarea rows="1" name="evpf_evidence_detail[]"></textarea></td>
                                        <td><input type="text" name="evpf_evidence_qty[]" style="text-align:center;"></td>
                                        <td><input type="text" name="evpf_evidence_position[]" style="text-align:center;"></td>
                                        <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveEvidenceRow(this)">✕</button></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div style="display:flex; align-items:center; margin-top:3px;">
                                <button type="button" class="evpf-add-btn" onclick="evpfAddEvidenceRow()">+ เพิ่มบรรทัด</button>
                                <span id="evpf_hidden_counter" class="evpf-hidden-counter"></span>
                            </div>

                            <!-- ลำดับการครอบครองวัตถุพยาน (CHAIN OF CUSTODY) -->
                            <div class="evpf-section-label">ลำดับการครอบครองวัตถุพยาน (CHAIN OF CUSTODY)</div>
                            <table class="evpf-custody-table" id="evpf_custody_table">
                                <thead>
                                    <tr>
                                        <th style="width:10%;">ลำดับ<br>ที่</th>
                                        <th style="width:23%;">จากใคร</th>
                                        <th style="width:23%;">ถึงใคร</th>
                                        <th style="width:18%;">วันที่</th>
                                        <th style="width:10%;">หมายเหตุ</th>
                                        <th style="width:6%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="evpf_custody_body">
                                    <tr>
                                        <td>1</td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_from[]"></textarea></td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_to[]"></textarea></td>
                                        <td><input type="date" name="evpf_custody_date[]" value="<?= $evpfTodayDate ?>"></td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_remark[]"></textarea></td>
                                        <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveCustodyRow(this)">✕</button></td>
                                    </tr>
                                    <tr>
                                        <td>2</td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_from[]"></textarea></td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_to[]"></textarea></td>
                                        <td><input type="date" name="evpf_custody_date[]"></td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_remark[]"></textarea></td>
                                        <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveCustodyRow(this)">✕</button></td>
                                    </tr>
                                    <tr>
                                        <td>3</td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_from[]"></textarea></td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_to[]"></textarea></td>
                                        <td><input type="date" name="evpf_custody_date[]"></td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_remark[]"></textarea></td>
                                        <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveCustodyRow(this)">✕</button></td>
                                    </tr>
                                    <tr>
                                        <td>4</td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_from[]"></textarea></td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_to[]"></textarea></td>
                                        <td><input type="date" name="evpf_custody_date[]"></td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_remark[]"></textarea></td>
                                        <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveCustodyRow(this)">✕</button></td>
                                    </tr>
                                    <tr>
                                        <td>5</td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_from[]"></textarea></td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_to[]"></textarea></td>
                                        <td><input type="date" name="evpf_custody_date[]"></td>
                                        <td><textarea rows="1" class="text-left" name="evpf_custody_remark[]"></textarea></td>
                                        <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveCustodyRow(this)">✕</button></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="button" class="evpf-add-btn" onclick="evpfAddCustodyRow()">+ เพิ่มแถว</button>

                            <!-- ลักษณะของของกลางขณะมาถึงผู้ตรวจพิสูจน์หรือผู้รับผิดชอบ -->
                            <div class="evpf-condition-section">
                                <div class="evpf-section-title">ลักษณะของของกลางขณะมาถึงผู้ตรวจพิสูจน์หรือ<br>ผู้รับผิดชอบ</div>
                                <div style="margin-top: 2mm;">
                                    <label class="evpf-ck">
                                        <input type="checkbox" class="evpf-cb" name="evpf_condition_sealed" id="evpf_condition_sealed" value="1" checked>
                                        อยู่ในสภาพปิดผนึกเรียบร้อย
                                    </label>
                                </div>
                                <div>
                                    <label class="evpf-ck">
                                        <input type="checkbox" class="evpf-cb" name="evpf_condition_other" id="evpf_condition_other" value="1">
                                        อื่น ๆ
                                    </label>
                                    <input type="text" class="evpf-inp" name="evpf_condition_other_text" id="evpf_condition_other_text" style="max-width:300px;">
                                </div>
                            </div>

                            <!-- QR Code -->
                            <div class="evpf-qr-section">
                                <div id="evpf_qrcode"></div>
                            </div>

                        </div><!-- /evpf-content -->
                    </div><!-- /evpf-page -->

                </form>
            </div><!-- /modal-body -->

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_evidence_pdf" onclick="evpfSaveData()">
                    <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger btn-sm js-close-modal" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> ยกเลิก
                </button>
                
                     <button type="button" class="btn btn-info btn-sm text-white" onclick="evpfPrintForm()">
                    <i class="fas fa-print me-1"></i> พิมพ์
                </button>
            </div>

        </div><!-- /modal-content -->
    </div><!-- /modal-dialog -->
</div><!-- /modal -->

<script src="/csims/js/qrcode.min.js"></script>
<script>
// =========================================================
// Evidence PDF Form (evpf_) - JavaScript
// =========================================================

// --- Auto-grow textarea ---
function evpfAutoGrow(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}
function evpfAutoGrowAll() {
    $('#evidenceFormPdf textarea').each(function() {
        evpfAutoGrow(this);
    });
}
// Delegated auto-grow for all textareas in evidence form
$(document).on('input', '#evidenceFormPdf textarea', function() {
    evpfAutoGrow(this);
});

// --- Add victim row ---
function evpfAddVictimRow() {
    const tbody = document.getElementById('evpf_victim_body');
    const rowCount = tbody.rows.length + 1;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>${rowCount}</td>
        <td>
            <select name="evpf_victim_type[]">
                <option value="">-- เลือก --</option>
                <option value="ผู้ต้องหา">ผู้ต้องหา</option>
                <option value="ผู้ต้องสงสัย">ผู้ต้องสงสัย</option>
                <option value="ผู้เสียหาย">ผู้เสียหาย</option>
            </select>
        </td>
        <td><textarea rows="1" class="text-left" name="evpf_victim_name[]" placeholder="ชื่อ-นามสกุล"></textarea></td>
        <td><input type="text" class="text-center" name="evpf_victim_age[]" maxlength="3" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"></td>
        <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveVictimRow(this)">✕</button></td>
    `;
    tbody.appendChild(tr);
}

// --- Remove victim row & renumber ---
function evpfRemoveVictimRow(btn) {
    const tbody = document.getElementById('evpf_victim_body');
    btn.closest('tr').remove();
    Array.from(tbody.rows).forEach((row, i) => { row.cells[0].textContent = i + 1; });
}

// --- Add evidence row ---
function evpfAddEvidenceRow() {
    const tbody = document.getElementById('evpf_evidence_body');
    const rowCount = tbody.rows.length + 1;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>${rowCount}</td>
        <td><textarea rows="1" name="evpf_evidence_detail[]"></textarea></td>
        <td><input type="text" name="evpf_evidence_qty[]" style="text-align:center;"></td>
        <td><input type="text" name="evpf_evidence_position[]" style="text-align:center;"></td>
        <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveEvidenceRow(this)">✕</button></td>
    `;
    tbody.appendChild(tr);
}

// --- Remove evidence row (ซ่อนแทนลบ - ไม่ลบจาก DB) ---
function evpfRemoveEvidenceRow(btn) {
    const tr = btn.closest('tr');
    // ซ่อน row แทนการลบ
    tr.classList.add('evpf-row-hidden');
    tr.style.display = 'none';
    // เปลี่ยนปุ่ม ✕ เป็นปุ่ม ↩ (คืนค่า)
    btn.innerHTML = '↩';
    btn.classList.remove('evpf-del-btn');
    btn.classList.add('evpf-restore-btn');
    btn.setAttribute('onclick', 'evpfRestoreEvidenceRow(this)');
    btn.title = 'คืนค่ารายการนี้';
    // Renumber เฉพาะ row ที่ยังแสดงอยู่
    evpfRenumberEvidenceRows();
    // แสดงปุ่ม "แสดงรายการที่ซ่อน" ถ้ามี row ถูกซ่อน
    evpfUpdateHiddenCounter();
}

// --- Restore evidence row (คืนค่า row ที่ซ่อน) ---
function evpfRestoreEvidenceRow(btn) {
    const tr = btn.closest('tr');
    // แสดง row กลับมา
    tr.classList.remove('evpf-row-hidden');
    tr.style.display = '';
    // เปลี่ยนปุ่มกลับเป็น ✕
    btn.innerHTML = '✕';
    btn.classList.remove('evpf-restore-btn');
    btn.classList.add('evpf-del-btn');
    btn.setAttribute('onclick', 'evpfRemoveEvidenceRow(this)');
    btn.title = 'ซ่อนรายการนี้';
    // Renumber
    evpfRenumberEvidenceRows();
    evpfUpdateHiddenCounter();
}

// --- Renumber evidence rows (เฉพาะที่แสดงอยู่) ---
function evpfRenumberEvidenceRows() {
    const tbody = document.getElementById('evpf_evidence_body');
    let visibleIndex = 1;
    Array.from(tbody.rows).forEach((row) => {
        if (!row.classList.contains('evpf-row-hidden')) {
            row.cells[0].textContent = visibleIndex++;
        }
    });
}

// --- Update hidden counter ---
function evpfUpdateHiddenCounter() {
    const tbody = document.getElementById('evpf_evidence_body');
    const hiddenRows = tbody.querySelectorAll('tr.evpf-row-hidden');
    const counter = document.getElementById('evpf_hidden_counter');
    if (counter) {
        if (hiddenRows.length > 0) {
            counter.style.display = 'inline-block';
            counter.innerHTML = `<i class="fas fa-eye-slash me-1"></i> ซ่อนอยู่ ${hiddenRows.length} รายการ <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="evpfShowAllHidden()">แสดงทั้งหมด</button>`;
        } else {
            counter.style.display = 'none';
        }
    }
}

// --- Show all hidden rows ---
function evpfShowAllHidden() {
    const tbody = document.getElementById('evpf_evidence_body');
    tbody.querySelectorAll('tr.evpf-row-hidden').forEach(tr => {
        const btn = tr.querySelector('.evpf-restore-btn');
        if (btn) evpfRestoreEvidenceRow(btn);
    });
}

// --- Add custody row ---
function evpfAddCustodyRow() {
    const tbody = document.getElementById('evpf_custody_body');
    const rowCount = tbody.rows.length + 1;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>${rowCount}</td>
        <td><textarea rows="1" class="text-left" name="evpf_custody_from[]"></textarea></td>
        <td><textarea rows="1" class="text-left" name="evpf_custody_to[]"></textarea></td>
        <td><input type="date" name="evpf_custody_date[]"></td>
        <td><textarea rows="1" class="text-left" name="evpf_custody_remark[]"></textarea></td>
        <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveCustodyRow(this)">✕</button></td>
    `;
    tbody.appendChild(tr);
}

// --- Remove custody row & renumber ---
function evpfRemoveCustodyRow(btn) {
    const tbody = document.getElementById('evpf_custody_body');
    btn.closest('tr').remove();
    // Renumber
    Array.from(tbody.rows).forEach((row, i) => {
        row.cells[0].textContent = i + 1;
    });
}

// --- Build compact ASCII-safe QR payload (qrcodejs overflows on Thai UTF-8) ---
function evpfBuildQrText(incidentId) {
    var caseNo = ($('#evpf_case_no').val() || '').trim();
    // Thai digits -> Arabic
    caseNo = caseNo.replace(/[\u0E50-\u0E59]/g, function(c) {
        return String(c.charCodeAt(0) - 0x0E50);
    });
    // Keep ASCII printable only (strip Thai letters etc.)
    caseNo = caseNo.replace(/[^\x20-\x7E]/g, '').trim().substring(0, 40);
    var idPart = String(incidentId || '').trim();
    if (!idPart) return '';
    var qrText = 'CSIMS-EV|' + (caseNo || 'NA') + '|ID:' + idPart;
    if (qrText.length > 80) {
        qrText = 'CSIMS-EV|ID:' + idPart;
    }
    return qrText;
}

// --- Render QR code in modal ---
function evpfRenderQrCode(incidentId) {
    const qrEl = document.getElementById('evpf_qrcode');
    if (!qrEl) return;
    qrEl.innerHTML = '';
    if (typeof QRCode === 'undefined' || !incidentId) return;

    var qrText = evpfBuildQrText(incidentId);
    if (!qrText) return;

    var qrOptions = {
        width: 90,
        height: 90,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.L
    };

    try {
        new QRCode(qrEl, Object.assign({ text: qrText }, qrOptions));
    } catch (e) {
        console.warn('[EVPF] QR render failed, retry ID-only:', e, qrText);
        qrEl.innerHTML = '';
        try {
            new QRCode(qrEl, Object.assign({
                text: 'CSIMS-EV|ID:' + String(incidentId)
            }, qrOptions));
        } catch (e2) {
            console.error('[EVPF] QR render failed completely:', e2);
        }
    }
}

// --- Reset form ---
function resetEvidenceFormPdf() {
    const form = document.getElementById('evidenceFormPdf');
    // เก็บค่า incident_id ไว้ก่อน reset
    const savedIncidentId = $('#evpf_incident_id').val();
    
    if (form) form.reset();
    
    // คืนค่า incident_id หลัง reset
    if (savedIncidentId) {
        $('#evpf_incident_id').val(savedIncidentId);
    }

    // Reset victim table to 1 row
    const victimBody = document.getElementById('evpf_victim_body');
    victimBody.innerHTML = `
        <tr>
            <td>1</td>
            <td>
                <select name="evpf_victim_type[]">
                    <option value="">-- เลือก --</option>
                    <option value="ผู้ต้องหา">ผู้ต้องหา</option>
                    <option value="ผู้ต้องสงสัย">ผู้ต้องสงสัย</option>
                    <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                </select>
            </td>
            <td><textarea rows="1" class="text-left" name="evpf_victim_name[]" placeholder="ชื่อ-นามสกุล"></textarea></td>
            <td><input type="text" class="text-center" name="evpf_victim_age[]" maxlength="3" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"></td>
            <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveVictimRow(this)">✕</button></td>
        </tr>
    `;

    // Reset evidence table to 3 rows
    const evBody = document.getElementById('evpf_evidence_body');
    evBody.innerHTML = '';
    for (let i = 1; i <= 3; i++) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${i}</td>
            <td><textarea rows="1" name="evpf_evidence_detail[]" ${i === 1 ? 'placeholder="รายละเอียดวัตถุพยาน..."' : ''}></textarea></td>
            <td><input type="text" name="evpf_evidence_qty[]" style="text-align:center;"></td>
            <td><input type="text" name="evpf_evidence_position[]" style="text-align:center;"></td>
            <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveEvidenceRow(this)">✕</button></td>
        `;
        evBody.appendChild(tr);
    }
    // Reset custody to 5 rows
    const tbody = document.getElementById('evpf_custody_body');
    tbody.innerHTML = '';
    for (let i = 1; i <= 5; i++) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${i}</td>
            <td><textarea rows="1" class="text-left" name="evpf_custody_from[]"></textarea></td>
            <td><textarea rows="1" class="text-left" name="evpf_custody_to[]"></textarea></td>
            <td><input type="date" name="evpf_custody_date[]"></td>
            <td><textarea rows="1" class="text-left" name="evpf_custody_remark[]"></textarea></td>
            <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveCustodyRow(this)">✕</button></td>
        `;
        tbody.appendChild(tr);
    }
    // Clear QR
    const qrEl = document.getElementById('evpf_qrcode');
    if (qrEl) qrEl.innerHTML = '';
    // Reset checkboxes
    $('#evpf_condition_sealed').prop('checked', true);
    $('#evpf_condition_other').prop('checked', false);
    $('#evpf_condition_other_text').val('');
}

// --- Load data into modal ---
function evpfLoadData(incidentId) {
    $('#evpfLoadingOverlay').removeClass('d-none');
    
    console.log('[EVPF] Loading data for incident_id:', incidentId);
    
    var loadCount = 0;
    var totalLoads = 2;
    
    function checkDone() {
        loadCount++;
        if (loadCount >= totalLoads) {
            setTimeout(function() {
                evpfAutoGrowAll();
                evpfRenderQrCode(incidentId);
            }, 50);
            $('#evpfLoadingOverlay').addClass('d-none');
        }
    }

    // 1) ดึงข้อมูล ReceiveNoti (station, type และ base info)
    $.ajax({
        url: '/csims/api/ReceiveNoti/getDataByID.php',
        type: 'GET',
        dataType: 'json',
        data: { id: incidentId },
        success: function(rnResp) {
            console.log('[EVPF] ReceiveNoti response:', rnResp);
            if (rnResp && rnResp.status === 'success' && rnResp.data) {
                const rn = rnResp.data;
                if (rn.complaints_From) $('#evpf_station_name').val(rn.complaints_From);
                if (rn.receiveNoti_No) $('#evpf_case_no').val(rn.receiveNoti_No);
                if (rn.location_crime) $('#evpf_location_detail').val(rn.location_crime);

                if (rn.time_Occurrence && rn.time_Occurrence.trim() !== '') {
                    const dtParts = rn.time_Occurrence.trim().split(' ');
                    if (dtParts[0]) $('#evpf_incident_date').val(dtParts[0]);
                    if (dtParts[1]) $('#evpf_incident_time').val(dtParts[1].substring(0, 5));
                }
            }
            checkDone();
        },
        error: function(xhr, status, error) {
            console.error('[EVPF] ReceiveNoti error:', error);
            checkDone();
        }
    });

    // 2) ดึงข้อมูล evidence form (ที่บันทึกไว้)
    $.ajax({
        url: '/csims/api/incidentCheckList/getEvidenceData.php',
        type: 'GET',
        dataType: 'json',
        data: { incident_id: incidentId },
        success: function(evResp) {
            console.log('[EVPF] Evidence response:', evResp);
            if (evResp && evResp.success) {
                if (evResp.evidence_form) {
                    console.log('[EVPF] Using saved evidence_form data');
                    evpfPopulateFromSaved(evResp.evidence_form, incidentId);
                } else if (evResp.data) {
                    console.log('[EVPF] Using checklist data (no evidence_form yet)');
                    evpfPopulateForm(evResp.data, incidentId);
                }
            }
            checkDone();
        },
        error: function(xhr, status, error) {
            console.error('[EVPF] Evidence API error:', error, 'Response:', xhr.responseText);
            checkDone();
        }
    });
}

// --- Populate form with data ---
function evpfPopulateForm(data, incidentId) {
    // ===== ถ้ามี evidence_form ที่บันทึกไว้แล้ว ให้ใช้ข้อมูลนั้นก่อน =====
    const evForm = data.evidence_form || null;
    if (evForm) {
        evpfPopulateFromSaved(evForm, incidentId);
        return;
    }

    // ===== ถ้าไม่มี evidence_form → ใช้ข้อมูลจาก checklist หลัก =====
    const gen = data.general_info || {};
    const handover = data.handover || {};
    const evidences = Array.isArray(data.evidences) ? data.evidences : [];
    const measurements = Array.isArray(data.measurements) ? data.measurements : [];
    const evidencesFound = Array.isArray(data.evidences_found) ? data.evidences_found : [];
    const condition = data.evidence_condition || {};
    const inspectors = gen.inspectors || [];
    const victim = gen.victim || {};

    // สถานีตำรวจ
    if (gen.source_station) {
        $('#evpf_station_name').val(gen.source_station);
    }

    // คดี (case no จาก receiveNoti_No - set จาก prefix function)
    // สถานที่เกิดเหตุ
    if (gen.location_detail) {
        $('#evpf_location_detail').val(gen.location_detail);
    }

    // วันที่เกิดเหตุ / เวลา
    if (gen.incident_datetime) {
        const dt = gen.incident_datetime;
        if (dt.length >= 10) $('#evpf_incident_date').val(dt.substring(0, 10));
        if (dt.length >= 16) $('#evpf_incident_time').val(dt.substring(11, 16));
    }

    // ผู้ต้องหา / ผู้ต้องสงสัย / ผู้เสียหาย (ตาราง)
    // รองรับหลาย schema (Life/Bomb/Fire) เพราะแต่ละเอกสารเก็บ key ต่างกัน
    const allVictims = Array.isArray(gen.all_victims) ? gen.all_victims : [];
    const bombVictims = Array.isArray(data.victims) ? data.victims : [];
    const firePersons = (data.scene_info && Array.isArray(data.scene_info.persons)) ? data.scene_info.persons : [];
    const victimInfo = data.victim_info || {};
    const victimTypes = Array.isArray(victimInfo.victim_types) ? victimInfo.victim_types : [];
    const victimNames = Array.isArray(victimInfo.victim_names) ? victimInfo.victim_names : [];
    const victimAges = Array.isArray(victimInfo.victim_ages) ? victimInfo.victim_ages : [];

    function evpfNormalizeVictimRow(v) {
        const type = (v.type || v.victim_type || v.person_type || v.status || '').toString().trim();
        const name = (
            v.name ||
            v.victim_name ||
            v.fullname ||
            (((v.firstname || '') + ' ' + (v.lastname || '')).trim())
        ).toString().trim();
        const age = (v.age || v.victim_age || '').toString().trim();
        return { type: type, name: name, age: age };
    }

    // ลำดับความสำคัญ: life(all_victims) -> bomb(victims) -> fire(scene_info.persons) -> victim_info arrays
    let victimsArr = [];
    if (allVictims.length > 0) {
        victimsArr = allVictims.map(evpfNormalizeVictimRow);
    } else if (bombVictims.length > 0) {
        victimsArr = bombVictims.map(evpfNormalizeVictimRow);
    } else if (firePersons.length > 0) {
        victimsArr = firePersons.map(evpfNormalizeVictimRow);
    } else if (victimTypes.length > 0 || victimNames.length > 0 || victimAges.length > 0) {
        const maxLen = Math.max(victimTypes.length, victimNames.length, victimAges.length);
        for (let vi = 0; vi < maxLen; vi++) {
            victimsArr.push(evpfNormalizeVictimRow({
                type: victimTypes[vi] || '',
                name: victimNames[vi] || '',
                age: victimAges[vi] || ''
            }));
        }
    }

    // กันข้อมูลว่าง/ซ้ำจาก schema ที่ไม่ตรง
    victimsArr = victimsArr.filter(function(v) {
        return (v.type || v.name || v.age);
    });

    if (victimsArr.length > 0) {
        const victimBody = document.getElementById('evpf_victim_body');
        victimBody.innerHTML = '';
        victimsArr.forEach(function(v, idx) {
            const tr = document.createElement('tr');
            const typeOptions = ['', 'ผู้ต้องหา', 'ผู้ต้องสงสัย', 'ผู้เสียหาย', 'ผู้เสียชีวิต', 'ผู้บาดเจ็บ', 'ผู้สูญหาย'];
            // รองรับหลายรูปแบบ key จาก checklist และตัดช่องว่างก่อนเทียบค่า
            const rawType = (v.type || '').toString().trim();
            if (rawType && typeOptions.indexOf(rawType) === -1) {
                typeOptions.push(rawType);
            }
            let selectHtml = '<select name="evpf_victim_type[]">';
            typeOptions.forEach(function(opt) {
                const label = opt || '-- เลือก --';
                const selected = (rawType === opt) ? ' selected' : '';
                selectHtml += '<option value="' + escapeHtml(opt) + '"' + selected + '>' + escapeHtml(label) + '</option>';
            });
            selectHtml += '</select>';
            tr.innerHTML = `
                <td>${idx + 1}</td>
                <td>${selectHtml}</td>
                <td><textarea rows="1" class="text-left" name="evpf_victim_name[]" placeholder="ชื่อ-นามสกุล">${escapeHtml(v.name || '')}</textarea></td>
                <td><input type="text" class="text-center" name="evpf_victim_age[]" value="${escapeHtml(v.age || '')}" maxlength="3" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"></td>
                <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveVictimRow(this)">✕</button></td>
            `;
            victimBody.appendChild(tr);
        });
    }

    // วันที่เก็บวัตถุพยาน (inspection_datetime)
    if (gen.inspection_datetime) {
        const dt = gen.inspection_datetime;
        if (dt.length >= 10) $('#evpf_collect_date').val(dt.substring(0, 10));
        if (dt.length >= 16) $('#evpf_collect_time').val(dt.substring(11, 16));
    }

    // ผู้เก็บวัตถุพยาน (receiver)
    if (handover.receiver_id) {
        $('#evpf_collector_id').val(handover.receiver_id);
    }

    // รายละเอียดวัตถุพยาน (ตาราง)
    // รวมจากหลายโครงสร้างข้อมูล: evidences + measurements + evidences_found
    const evidenceRows = [];

    function evpfNormalizeEvidenceRow(src) {
        return {
            detail: (src.detail || src.item || src.description || '').toString().trim(),
            qty: (src.quantity_val || src.qty || src.quantity || '').toString().trim(),
            position: (src.area_found || src.position || src.area || '').toString().trim()
        };
    }

    evidences.forEach(function(ev) {
        evidenceRows.push(evpfNormalizeEvidenceRow(ev));
    });

    // เติมช่องที่ยังว่างจาก measurements ตาม index เดียวกัน
    measurements.forEach(function(ms, idx) {
        const m = evpfNormalizeEvidenceRow(ms);
        if (evidenceRows[idx]) {
            if (!evidenceRows[idx].detail && m.detail) evidenceRows[idx].detail = m.detail;
            if (!evidenceRows[idx].qty && m.qty) evidenceRows[idx].qty = m.qty;
            if (!evidenceRows[idx].position && m.position) evidenceRows[idx].position = m.position;
        } else {
            evidenceRows.push(m);
        }
    });

    // บางเอกสาร (bomb) มี evidences_found แยกอีกชุด
    evidencesFound.forEach(function(evf, idx) {
        const f = evpfNormalizeEvidenceRow(evf);
        if (evidenceRows[idx]) {
            if (!evidenceRows[idx].detail && f.detail) evidenceRows[idx].detail = f.detail;
            if (!evidenceRows[idx].qty && f.qty) evidenceRows[idx].qty = f.qty;
            if (!evidenceRows[idx].position && f.position) evidenceRows[idx].position = f.position;
        } else {
            evidenceRows.push(f);
        }
    });

    const evBody = document.getElementById('evpf_evidence_body');
    evBody.innerHTML = '';

    // แสดงเฉพาะแถวที่มีข้อมูลจริง
    const populatedRows = evidenceRows.filter(function(r) {
        return r.detail || r.qty || r.position;
    });

    populatedRows.forEach(function(row, idx) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${idx + 1}</td>
            <td><textarea rows="1" name="evpf_evidence_detail[]" ${idx === 0 ? 'placeholder="รายละเอียดวัตถุพยาน..."' : ''}>${escapeHtml(row.detail || '')}</textarea></td>
            <td><input type="text" name="evpf_evidence_qty[]" value="${escapeHtml(row.qty || '')}" style="text-align:center;"></td>
            <td><input type="text" name="evpf_evidence_position[]" value="${escapeHtml(row.position || '')}" style="text-align:center;"></td>
            <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveEvidenceRow(this)">✕</button></td>
        `;
        evBody.appendChild(tr);
    });

    // Ensure at least 3 rows
    while (evBody.rows.length < 3) {
        const i = evBody.rows.length;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${i + 1}</td>
            <td><textarea rows="1" name="evpf_evidence_detail[]" ${i === 0 ? 'placeholder="รายละเอียดวัตถุพยาน..."' : ''}></textarea></td>
            <td><input type="text" name="evpf_evidence_qty[]" style="text-align:center;"></td>
            <td><input type="text" name="evpf_evidence_position[]" style="text-align:center;"></td>
            <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveEvidenceRow(this)">✕</button></td>
        `;
        evBody.appendChild(tr);
    }

    // Chain of Custody
    if (inspectors.length > 0) {
        const tbody = document.getElementById('evpf_custody_body');
        tbody.innerHTML = '';
        inspectors.forEach(function(insp, idx) {
            const tr = document.createElement('tr');
            const fromName = insp.from_name || '';
            const toName = insp.to_name || insp.name || '';
            const custDate = insp.date || '';
            const remark = insp.remark || '';
            tr.innerHTML = `
                <td>${idx + 1}</td>
                <td><textarea rows="1" class="text-left" name="evpf_custody_from[]">${escapeHtml(fromName)}</textarea></td>
                <td><textarea rows="1" class="text-left" name="evpf_custody_to[]">${escapeHtml(toName)}</textarea></td>
                <td><input type="date" name="evpf_custody_date[]" value="${custDate}"></td>
                <td><textarea rows="1" class="text-left" name="evpf_custody_remark[]">${escapeHtml(remark)}</textarea></td>
                <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveCustodyRow(this)">✕</button></td>
            `;
            tbody.appendChild(tr);
        });
        // Ensure at least 5 rows
        while (tbody.rows.length < 5) {
            const i = tbody.rows.length;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${i + 1}</td>
                <td><textarea rows="1" class="text-left" name="evpf_custody_from[]"></textarea></td>
                <td><textarea rows="1" class="text-left" name="evpf_custody_to[]"></textarea></td>
                <td><input type="date" name="evpf_custody_date[]"></td>
                <td><textarea rows="1" class="text-left" name="evpf_custody_remark[]"></textarea></td>
                <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveCustodyRow(this)">✕</button></td>
            `;
            tbody.appendChild(tr);
        }
    }

    // Condition
    if (condition.sealed !== undefined) {
        $('#evpf_condition_sealed').prop('checked', !!condition.sealed);
    }
    if (condition.other !== undefined) {
        $('#evpf_condition_other').prop('checked', !!condition.other);
    }
    if (condition.other_text) {
        $('#evpf_condition_other_text').val(condition.other_text);
    }

    // QR Code
    evpfRenderQrCode(incidentId);
}

// --- Populate form from saved evidence_form data ---
function evpfPopulateFromSaved(ev, incidentId) {
    console.log('[EVPF] evpfPopulateFromSaved called with:', ev);
    console.log('[EVPF] evidence_details:', ev.evidence_details);
    console.log('[EVPF] custody:', ev.custody);
    
    // สถานีตำรวจ
    if (ev.station_name) $('#evpf_station_name').val(ev.station_name);
    
    // คดี
    if (ev.case_no) $('#evpf_case_no').val(ev.case_no);
    
    // สถานที่เกิดเหตุ
    if (ev.location_detail) $('#evpf_location_detail').val(ev.location_detail);
    
    // วันที่เกิดเหตุ / เวลา
    if (ev.incident_date) $('#evpf_incident_date').val(ev.incident_date);
    if (ev.incident_time) $('#evpf_incident_time').val(ev.incident_time);
    
    // วันที่เก็บวัตถุพยาน
    if (ev.collect_date) $('#evpf_collect_date').val(ev.collect_date);
    if (ev.collect_time) $('#evpf_collect_time').val(ev.collect_time);
    
    // ผู้เก็บวัตถุพยาน
    if (ev.collector_id) $('#evpf_collector_id').val(ev.collector_id);
    
    // ผู้ต้องหา / ผู้ต้องสงสัย / ผู้เสียหาย
    const victims = Array.isArray(ev.victims) ? ev.victims : [];
    if (victims.length > 0) {
        const victimBody = document.getElementById('evpf_victim_body');
        victimBody.innerHTML = '';
        victims.forEach(function(v, idx) {
            const typeOptions = ['', 'ผู้ต้องหา', 'ผู้ต้องสงสัย', 'ผู้เสียหาย', 'ผู้เสียชีวิต', 'ผู้บาดเจ็บ', 'ผู้สูญหาย'];
            const rawType = (v.type || '').trim();
            if (rawType && typeOptions.indexOf(rawType) === -1) typeOptions.push(rawType);
            let selectHtml = '<select name="evpf_victim_type[]">';
            typeOptions.forEach(function(opt) {
                const label = opt || '-- เลือก --';
                const selected = (rawType === opt) ? ' selected' : '';
                selectHtml += '<option value="' + escapeHtml(opt) + '"' + selected + '>' + escapeHtml(label) + '</option>';
            });
            selectHtml += '</select>';
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${idx + 1}</td>
                <td>${selectHtml}</td>
                <td><textarea rows="1" class="text-left" name="evpf_victim_name[]" placeholder="ชื่อ-นามสกุล">${escapeHtml(v.name || '')}</textarea></td>
                <td><input type="text" class="text-center" name="evpf_victim_age[]" value="${escapeHtml(v.age || '')}" maxlength="3" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')"></td>
                <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveVictimRow(this)">✕</button></td>
            `;
            victimBody.appendChild(tr);
        });
    }
    
    // รายละเอียดวัตถุพยาน
    const evidenceDetails = Array.isArray(ev.evidence_details) ? ev.evidence_details : [];
    const evBody = document.getElementById('evpf_evidence_body');
    evBody.innerHTML = '';
    
    let visibleIndex = 1;
    if (evidenceDetails.length > 0) {
        evidenceDetails.forEach(function(row, idx) {
            const tr = document.createElement('tr');
            const isHidden = row.hidden === true;
            // ถ้าซ่อน → ใช้ปุ่ม restore, ถ้าไม่ซ่อน → ใช้ปุ่ม delete
            const btnClass = isHidden ? 'evpf-restore-btn' : 'evpf-del-btn';
            const btnText = isHidden ? '↩' : '✕';
            const btnFunc = isHidden ? 'evpfRestoreEvidenceRow' : 'evpfRemoveEvidenceRow';
            const displayNum = isHidden ? '-' : visibleIndex++;
            
            tr.innerHTML = `
                <td>${displayNum}</td>
                <td><textarea rows="1" name="evpf_evidence_detail[]" ${idx === 0 ? 'placeholder="รายละเอียดวัตถุพยาน..."' : ''}>${escapeHtml(row.detail || '')}</textarea></td>
                <td><input type="text" name="evpf_evidence_qty[]" value="${escapeHtml(row.qty || '')}" style="text-align:center;"></td>
                <td><input type="text" name="evpf_evidence_position[]" value="${escapeHtml(row.position || '')}" style="text-align:center;"></td>
                <td><button type="button" class="${btnClass}" onclick="${btnFunc}(this)">${btnText}</button></td>
            `;
            
            if (isHidden) {
                tr.classList.add('evpf-row-hidden');
                tr.style.display = 'none';
            }
            
            evBody.appendChild(tr);
        });
    }
    // Update hidden counter หลังโหลดข้อมูล
    evpfUpdateHiddenCounter();
    // Ensure at least 3 rows
    while (evBody.rows.length < 3) {
        const i = evBody.rows.length;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${i + 1}</td>
            <td><textarea rows="1" name="evpf_evidence_detail[]" ${i === 0 ? 'placeholder="รายละเอียดวัตถุพยาน..."' : ''}></textarea></td>
            <td><input type="text" name="evpf_evidence_qty[]" style="text-align:center;"></td>
            <td><input type="text" name="evpf_evidence_position[]" style="text-align:center;"></td>
            <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveEvidenceRow(this)">✕</button></td>
        `;
        evBody.appendChild(tr);
    }
    
    // Chain of Custody
    const custody = Array.isArray(ev.custody) ? ev.custody : [];
    const custodyBody = document.getElementById('evpf_custody_body');
    custodyBody.innerHTML = '';
    
    if (custody.length > 0) {
        custody.forEach(function(row, idx) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${idx + 1}</td>
                <td><textarea rows="1" class="text-left" name="evpf_custody_from[]">${escapeHtml(row.from_name || '')}</textarea></td>
                <td><textarea rows="1" class="text-left" name="evpf_custody_to[]">${escapeHtml(row.to_name || '')}</textarea></td>
                <td><input type="date" name="evpf_custody_date[]" value="${row.date || ''}"></td>
                <td><textarea rows="1" class="text-left" name="evpf_custody_remark[]">${escapeHtml(row.remark || '')}</textarea></td>
                <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveCustodyRow(this)">✕</button></td>
            `;
            custodyBody.appendChild(tr);
        });
    }
    // Ensure at least 5 rows
    while (custodyBody.rows.length < 5) {
        const i = custodyBody.rows.length;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${i + 1}</td>
            <td><textarea rows="1" class="text-left" name="evpf_custody_from[]"></textarea></td>
            <td><textarea rows="1" class="text-left" name="evpf_custody_to[]"></textarea></td>
            <td><input type="date" name="evpf_custody_date[]"></td>
            <td><textarea rows="1" class="text-left" name="evpf_custody_remark[]"></textarea></td>
            <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveCustodyRow(this)">✕</button></td>
        `;
        custodyBody.appendChild(tr);
    }
    
    // Condition
    if (ev.condition) {
        $('#evpf_condition_sealed').prop('checked', !!ev.condition.sealed);
        $('#evpf_condition_other').prop('checked', !!ev.condition.other);
        if (ev.condition.other_text) $('#evpf_condition_other_text').val(ev.condition.other_text);
    }
    
    // QR Code
    evpfRenderQrCode(incidentId);
}

// --- Helper: escape HTML ---
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

// --- Save data ---
function evpfSaveData() {
    var incidentId = $('#evpf_incident_id').val();
    
    // Fallback: ใช้ global variable
    if (!incidentId && typeof currentEvidenceIncidentId !== 'undefined' && currentEvidenceIncidentId) {
        incidentId = currentEvidenceIncidentId;
        console.log('[EVPF] Save using global currentEvidenceIncidentId:', incidentId);
    }
    
    console.log('[EVPF] Save clicked, incident_id:', incidentId);
    
    if (!incidentId) {
        Swal.fire({ icon: 'warning', title: 'ไม่พบ incident_id', confirmButtonText: 'ตกลง' });
        return;
    }

    // Collect victim data
    const victims = [];
    $('#evpf_victim_body tr').each(function() {
        const type = $(this).find('select[name="evpf_victim_type[]"]').val() || '';
        const name = $(this).find('textarea[name="evpf_victim_name[]"]').val() || '';
        const age = $(this).find('input[name="evpf_victim_age[]"]').val() || '';
        if (name.trim()) victims.push({ type: type, name: name.trim(), age: age.trim() });
    });

    // Collect evidence data (save ทุกแถวที่มีข้อมูล + เก็บ flag hidden สำหรับแถวที่ซ่อน)
    const evidenceDetails = [];
    $('#evpf_evidence_body tr').each(function() {
        const detail = $(this).find('textarea[name="evpf_evidence_detail[]"]').val() || '';
        const qty = $(this).find('input[name="evpf_evidence_qty[]"]').val() || '';
        const position = $(this).find('input[name="evpf_evidence_position[]"]').val() || '';
        const isHidden = $(this).hasClass('evpf-row-hidden');
        if (detail.trim() || qty.trim() || position.trim()) {
            evidenceDetails.push({ 
                detail: detail.trim(), 
                qty: qty.trim(), 
                position: position.trim(),
                hidden: isHidden  // เก็บ flag ว่าถูกซ่อนหรือไม่
            });
        }
    });

    // Collect custody data
    const custodyRows = [];
    $('#evpf_custody_body tr').each(function() {
        // รองรับทั้ง date input ปกติ และ text input ที่แปลงสำหรับ iOS
        var $dateInput = $(this).find('input[name="evpf_custody_date[]"]');
        var dateVal = '';
        if ($dateInput.length) {
            // กรณี date input ปกติ
            dateVal = $dateInput.val() || '';
        } else {
            // กรณี iOS: หาจาก text input ที่มี data-date-value
            var $textInput = $(this).find('.evpf-date-text[name="evpf_custody_date[]"]');
            if ($textInput.length) {
                // ใช้ค่าจาก data-date-value (yyyy-mm-dd) หรือแปลงจาก dd/mm/yyyy
                dateVal = $textInput.attr('data-date-value') || '';
                if (!dateVal && $textInput.val()) {
                    var parts = $textInput.val().split('/');
                    if (parts.length === 3) {
                        dateVal = parts[2] + '-' + parts[1] + '-' + parts[0];
                    }
                }
            }
        }
        custodyRows.push({
            from_name: $(this).find('textarea[name="evpf_custody_from[]"]').val() || '',
            to_name: $(this).find('textarea[name="evpf_custody_to[]"]').val() || '',
            date: dateVal,
            remark: $(this).find('textarea[name="evpf_custody_remark[]"]').val() || ''
        });
    });

    // Helper function สำหรับดึงค่า date (รองรับทั้ง date input และ text input บน iOS)
    function getDateValue(selector) {
        var $el = $(selector);
        if ($el.attr('type') === 'date') {
            return $el.val() || '';
        }
        // กรณี iOS: text input
        var dataVal = $el.attr('data-date-value');
        if (dataVal) return dataVal;
        // แปลงจาก dd/mm/yyyy เป็น yyyy-mm-dd
        var val = $el.val() || '';
        if (val) {
            var parts = val.split('/');
            if (parts.length === 3) {
                return parts[2] + '-' + parts[1] + '-' + parts[0];
            }
        }
        return val;
    }

    const jsonData = {
        incident_id: incidentId,
        evidence_form: {
            station_name: $('#evpf_station_name').val(),
            case_no: $('#evpf_case_no').val(),
            location_detail: $('#evpf_location_detail').val(),
            incident_date: getDateValue('#evpf_incident_date'),
            incident_time: $('#evpf_incident_time').val(),
            victims: victims,
            collect_date: getDateValue('#evpf_collect_date'),
            collect_time: $('#evpf_collect_time').val(),
            collector_id: $('#evpf_collector_id').val(),
            evidence_details: evidenceDetails,
            custody: custodyRows,
            condition: {
                sealed: $('#evpf_condition_sealed').is(':checked'),
                other: $('#evpf_condition_other').is(':checked'),
                other_text: $('#evpf_condition_other_text').val()
            }
        }
    };

    $('#btn_save_evidence_pdf').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> กำลังบันทึก...');

    $.ajax({
        url: '/csims/api/incidentCheckList/saveEvidenceForm.php',
        type: 'POST',
        contentType: 'application/json; charset=utf-8',
        data: JSON.stringify(jsonData),
        dataType: 'json',
        success: function(response) {
            $('#btn_save_evidence_pdf').prop('disabled', false).html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล');
            if (response.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'บันทึกสำเร็จ',
                    text: response.message || 'บันทึกข้อมูลวัตถุพยานเรียบร้อยแล้ว',
                    confirmButtonText: 'ตกลง'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: response.message || 'ไม่สามารถบันทึกข้อมูลได้',
                    confirmButtonText: 'ตกลง'
                });
            }
        },
        error: function(xhr) {
            $('#btn_save_evidence_pdf').prop('disabled', false).html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล');
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้',
                confirmButtonText: 'ตกลง'
            });
        }
    });
}

// --- Print form ---
function evpfPrintForm() {
    // ดึง incident_id จาก hidden input ใน modal นี้โดยเฉพาะ
    const $modal = $('#evidenceFormPdfModal');
    var incidentId = $modal.find('#evpf_incident_id').val();
    
    // Fallback: ใช้ global variable
    if (!incidentId && typeof currentEvidenceIncidentId !== 'undefined' && currentEvidenceIncidentId) {
        incidentId = currentEvidenceIncidentId;
        console.log('[EVPF] Print using global currentEvidenceIncidentId:', incidentId);
    }
    
    console.log('[EVPF] Print clicked, incident_id:', incidentId);
    
    if (!incidentId) {
        Swal.fire({
            icon: 'warning',
            title: 'ไม่พบข้อมูล',
            text: 'ไม่พบ incident_id กรุณาปิดและเปิด modal ใหม่',
            confirmButtonText: 'ตกลง'
        });
        return;
    }
    
    window.open('/csims/api/incidentCheckList/gen_pdf_evidence_html.php?incident_id=' + incidentId, '_blank');
}

// --- Modal shown event: load data ---
$('#evidenceFormPdfModal').on('shown.bs.modal', function() {
    const $modal = $(this);
    // ลองดึงจาก hidden input ก่อน ถ้าไม่มีให้ใช้ global variable
    var incidentId = $modal.find('#evpf_incident_id').val();
    
    // Fallback: ใช้ global variable ถ้า hidden input ว่าง
    if (!incidentId && typeof currentEvidenceIncidentId !== 'undefined' && currentEvidenceIncidentId) {
        incidentId = currentEvidenceIncidentId;
        $modal.find('#evpf_incident_id').val(incidentId);
        console.log('[EVPF] Using global currentEvidenceIncidentId:', incidentId);
    }
    
    console.log('[EVPF] Modal shown, incident_id:', incidentId);
    
    if (incidentId) {
        evpfLoadData(incidentId);
    } else {
        console.error('[EVPF] ERROR: incident_id is empty on modal shown!');
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: 'ไม่พบ incident_id กรุณาปิดและเปิด modal ใหม่',
            confirmButtonText: 'ตกลง'
        });
    }
    // Fix date inputs for iOS/iPad
    evpfFixDateInputsForIOS();
});

// --- Fix date inputs for iOS/iPad ---
function evpfFixDateInputsForIOS() {
    // ตรวจสอบว่าเป็น iOS หรือไม่
    var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || 
                (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    
    if (!isIOS) return;
    
    // เพิ่ม event handlers สำหรับ date inputs บน iOS
    $('#evidenceFormPdfModal input[type="date"]').each(function() {
        var $input = $(this);
        
        // ป้องกัน focus ค้าง - blur เมื่อเลือกวันที่เสร็จ
        $input.off('change.iosfix').on('change.iosfix', function() {
            var self = this;
            setTimeout(function() {
                self.blur();
                // Force redraw
                $(self).css('opacity', '0.99');
                setTimeout(function() {
                    $(self).css('opacity', '1');
                }, 50);
            }, 100);
        });
        
        // ป้องกัน double-tap zoom
        $input.off('touchend.iosfix').on('touchend.iosfix', function(e) {
            e.preventDefault();
            this.focus();
            this.click();
        });
        
        // Blur เมื่อ scroll หรือ touch outside
        $input.off('blur.iosfix').on('blur.iosfix', function() {
            var self = this;
            setTimeout(function() {
                // Force close picker
                $(self).css('pointer-events', 'none');
                setTimeout(function() {
                    $(self).css('pointer-events', 'auto');
                }, 100);
            }, 50);
        });
    });
    
    // Touch outside to close date picker
    $('#evidenceFormPdfModal').off('touchstart.iosfix').on('touchstart.iosfix', function(e) {
        if (!$(e.target).is('input[type="date"]')) {
            $('#evidenceFormPdfModal input[type="date"]').blur();
        }
    });
}

// --- Override evpfAddCustodyRow เพื่อรองรับ iOS ---
var _originalEvpfAddCustodyRow = typeof evpfAddCustodyRow === 'function' ? evpfAddCustodyRow : null;
evpfAddCustodyRow = function() {
    if (_originalEvpfAddCustodyRow) {
        _originalEvpfAddCustodyRow();
    } else {
        const tbody = document.getElementById('evpf_custody_body');
        const rowCount = tbody.rows.length + 1;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${rowCount}</td>
            <td><textarea rows="1" class="text-left" name="evpf_custody_from[]"></textarea></td>
            <td><textarea rows="1" class="text-left" name="evpf_custody_to[]"></textarea></td>
            <td><input type="date" name="evpf_custody_date[]"></td>
            <td><textarea rows="1" class="text-left" name="evpf_custody_remark[]"></textarea></td>
            <td><button type="button" class="evpf-del-btn" onclick="evpfRemoveCustodyRow(this)">✕</button></td>
        `;
        tbody.appendChild(tr);
    }
    // Fix date inputs หลังเพิ่มแถวใหม่
    setTimeout(evpfFixDateInputsForIOS, 100);
};
</script>
