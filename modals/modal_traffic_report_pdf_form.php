<?php
/**
 * Modal: ร่างรายงานคดีจราจร (ฟอร์มเสมือน PDF)
 * รายงานการตรวจพิสูจน์คดีจราจร — A4 paper style editable form
 * อ้างอิงจาก บัญชีแนบท้ายคำสั่ง สำนักงานพิสูจน์หลักฐานตำรวจ ที่/๔๔๔/๒๕๖๑
 * Modal ID: modalReportTrafficPdf   Form ID: formReportTrafficPdf
 * Prefix: rtpdf_
 */

// ดึงรายชื่อผู้ตรวจ
$rtpdfInspectorOptions = '<option value="" selected disabled>-- เลือกผู้ตรวจ --</option>';
if (isset($pdo)) {
    $qryRtpdfInsp = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                     FROM user_profile t1 
                     LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                     ORDER BY t1.user_id DESC";
    $stmtRtpdfInsp = $pdo->query($qryRtpdfInsp);
    while ($rowInsp = $stmtRtpdfInsp->fetch(PDO::FETCH_ASSOC)) {
        $fullName = trim((string)($rowInsp['fullname'] ?? ''));
        $rtpdfInspectorOptions .= '<option value="' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . '</option>';
    }
}
?>

<style>
/* ========== Traffic Report PDF Form — scoped to #modalReportTrafficPdf ========== */
#modalReportTrafficPdf .rtpf-body { background: #bbb; }
#modalReportTrafficPdf .rtpf-page {
    width: 210mm; min-height: 297mm; margin: 16px auto; padding: 12mm 15mm 10mm 15mm;
    background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.25); position: relative;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.6; color: #000;
    display: flex; flex-direction: column; page-break-after: always;
}
#modalReportTrafficPdf .rtpf-page:last-child { page-break-after: auto; }

/* Row helpers */
#modalReportTrafficPdf .fr  { display: flex; flex-wrap: wrap; align-items: baseline; line-height: 1.9; width: 100%; }
#modalReportTrafficPdf .fl  { font-size: 14px; white-space: normal; flex-shrink: 0; margin-right: 2px; word-break: break-word; }
#modalReportTrafficPdf .fl-b{ font-size: 14px; font-weight: 700; white-space: normal; flex-shrink: 0; margin-right: 2px; word-break: break-word; }
#modalReportTrafficPdf .i1  { padding-left: 20px; }
#modalReportTrafficPdf .i2  { padding-left: 40px; }
#modalReportTrafficPdf .fr-nowrap { flex-wrap: nowrap; }
#modalReportTrafficPdf .fr-break { flex: 0 0 100%; }
#modalReportTrafficPdf .rtpf-inp-line { width: calc(100% - 40px); margin-left: 40px; }
#modalReportTrafficPdf .sec-heading  { font-weight: 700; font-size: 14px; margin-top: 2px; margin-bottom: 1px; }
#modalReportTrafficPdf .sub-heading  { font-weight: 600; font-size: 14px; margin-left: 20px; }
#modalReportTrafficPdf .form-title   { text-decoration: underline; text-align: center; font-size: 15px; font-weight: 700; margin: 6px auto 4px auto; }
#modalReportTrafficPdf .page-header  { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0; }
#modalReportTrafficPdf .form-footer  { margin-top: auto; padding-top: 6px; font-size: 10px; color: #333; display: flex; justify-content: space-between; }
#modalReportTrafficPdf .form-footer-right { text-align: right; white-space: nowrap; line-height: 1.4; }
#modalReportTrafficPdf .signature-block { margin-top: 30px; text-align: center; padding-left: 50%; line-height: 2; }

/* Editable input styles — dotted bottom, transparent */
#modalReportTrafficPdf .rtpf-inp {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; min-width: 40px; flex: 1; margin: 0 4px; color: #000;
}
#modalReportTrafficPdf .rtpf-inp:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }
#modalReportTrafficPdf .rtpf-inp-s { width: 60px; max-width: 80px; text-align: center; flex: 0 0 auto; }
#modalReportTrafficPdf .rtpf-inp-m { flex: 0 1 165px; min-width: 120px; text-align: center; }
#modalReportTrafficPdf .rtpf-inp-l { flex: 1; min-width: 160px; }
#modalReportTrafficPdf .rtpf-inp-s,
#modalReportTrafficPdf .rtpf-inp-m,
#modalReportTrafficPdf .rtpf-inp-l {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 24px; margin: 0 4px; color: #000;
}
#modalReportTrafficPdf .rtpf-inp-date { width: 150px; max-width: 150px; min-width: 150px; flex: 0 0 150px; text-align: center; }
#modalReportTrafficPdf .rtpf-inp-time { width: 120px; max-width: 120px; min-width: 120px; flex: 0 0 120px; text-align: center; }
#modalReportTrafficPdf .rtpf-inp-s:focus,
#modalReportTrafficPdf .rtpf-inp-m:focus,
#modalReportTrafficPdf .rtpf-inp-l:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Textarea — dotted lines background */
#modalReportTrafficPdf .rtpf-ta {
    width: 100%; border: none; border-bottom: 1px dotted #888;
    background: transparent; outline: none; resize: vertical;
    font-family: 'Sarabun', sans-serif; font-size: 14px; line-height: 1.8;
    padding: 0 4px; min-height: 24px; margin: 0 4px 2px 20px; color: #000;
    background-image: repeating-linear-gradient(transparent, transparent 27px, #ddd 27px, #ddd 28px);
    background-position: 0 0;
}
#modalReportTrafficPdf .rtpf-ta:focus { border-bottom-color: #0d6efd; }
#modalReportTrafficPdf .rtpf-ta-line { margin-left: 40px; width: calc(100% - 40px); }

/* Checkbox */
#modalReportTrafficPdf .ck { display: inline-flex; align-items: baseline; margin-right: 8px; font-size: 14px; white-space: nowrap; }
#modalReportTrafficPdf .rtpf-cb {
    -webkit-appearance: none; appearance: none;
    width: 14px; height: 14px; margin-right: 3px; cursor: pointer; position: relative; top: 2px;
    border: 1.5px solid #000; border-radius: 0; background: #fff;
}
#modalReportTrafficPdf .rtpf-cb:checked::after {
    content: '✓'; position: absolute; top: -3px; left: 1px;
    font-size: 13px; font-weight: bold; color: #000; line-height: 1;
}

/* Button styles */
#modalReportTrafficPdf .rtpf-add-btn {
    font-size: 12px; padding: 1px 8px; border: 1px dashed #0d6efd; color: #0d6efd;
    background: transparent; border-radius: 4px; cursor: pointer; margin-left: 20px; margin-top: 2px;
}
#modalReportTrafficPdf .rtpf-add-btn:hover { background: rgba(13,110,253,.08); }
#modalReportTrafficPdf .rtpf-del-btn {
    font-size: 11px; padding: 0 5px; border: none; color: #dc3545; background: transparent; cursor: pointer; margin-left: 4px;
}

/* Select dropdown for inspector */
#modalReportTrafficPdf .rtpf-select {
    border: none; border-bottom: 1px dotted #888; background: transparent;
    outline: none; font-family: 'Sarabun', sans-serif; font-size: 14px;
    padding: 0 4px; height: 26px; flex: 1; margin: 0 4px; color: #000;
    cursor: pointer; min-width: 120px;
}
#modalReportTrafficPdf .rtpf-select:focus { border-bottom-color: #0d6efd; background: rgba(13,110,253,.04); }

/* Select2 inside PDF form — compact like fire form */
#modalReportTrafficPdf .rtpdf-inspector-row .select2-container { flex: 1; min-width: 120px; margin: 0 4px; }
#modalReportTrafficPdf .rtpdf-inspector-row .select2-container--bootstrap-5 .select2-selection {
    min-height: 26px !important; height: 26px !important; padding: 0 4px !important;
    border: none !important; border-bottom: 1px dotted #888 !important; border-radius: 0 !important;
    background: transparent !important; font-size: 14px !important;
}
#modalReportTrafficPdf .rtpdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    padding: 0 !important; line-height: 26px !important; font-size: 14px !important; color: #000 !important;
}
#modalReportTrafficPdf .rtpdf-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
    height: 26px !important;
}

/* Vehicle card in analysis */
#modalReportTrafficPdf .rtpf-vehicle-card {
    border: 1px solid #ccc; padding: 6px 8px; margin-bottom: 6px; background: #fafafa;
}

@media print {
    body > *:not(#modalReportTrafficPdf),
    .navbar, .sidebar, .main-content, #status-banner,
    .modal-backdrop, .swal2-container,
    #modalReportTrafficPdf .modal-header,
    #modalReportTrafficPdf .modal-footer {
        display: none !important;
    }
    #modalReportTrafficPdf,
    #modalReportTrafficPdf .modal-dialog,
    #modalReportTrafficPdf .modal-content,
    #modalReportTrafficPdf .modal-body {
        position: static !important; display: block !important;
        width: auto !important; max-width: none !important;
        max-height: none !important; height: auto !important;
        overflow: visible !important; margin: 0 !important;
        padding: 0 !important; background: #fff !important;
        border: none !important; box-shadow: none !important;
        transform: none !important; opacity: 1 !important;
    }
    @page { size: A4 portrait; margin: 0; }
    #modalReportTrafficPdf .rtpf-page {
        width: 100% !important; min-height: auto !important;
        height: auto !important; margin: 0 !important;
        padding: 10mm 12mm 8mm 12mm !important;
        box-shadow: none !important; overflow: visible !important;
        page-break-after: always; page-break-inside: auto;
    }
    #modalReportTrafficPdf .rtpf-page:last-of-type { page-break-after: auto; }
}
</style>

<!-- ===== MODAL ===== -->
<div class="modal fade" id="modalReportTrafficPdf" aria-labelledby="modalReportTrafficPdfLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog" style="max-width: 240mm; margin: 1.75rem auto;">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalReportTrafficPdfLabel">
                    รายงานการตรวจพิสูจน์คดีจราจร
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body rtpf-body p-0" style="max-height: 80vh; overflow-y: auto;">
                <!-- Loading Overlay -->
                <div id="trafficReportPdfLoadingOverlay" class="csims-loading-overlay d-none">
                    <div class="text-center">
                        <div class="csims-bar-track"><div class="csims-bar-fill"></div></div>
                        <div class="mt-3 fw-bold" style="font-size:1.05rem;color:#3b5998;">กำลังโหลดข้อมูล...</div>
                    </div>
                </div>

                <form id="formReportTrafficPdf" novalidate>
                    <input type="hidden" id="rtpdf_incident_id" name="incident_id">

                    <!-- Switch back to standard form -->
                    <div class="d-flex align-items-center gap-3 px-4 pt-3 pb-2">
                        <div id="rt_editInfoPdf" class="d-none bg-primary text-white rounded-pill shadow-sm d-inline-flex align-items-center px-4 py-2" style="font-size: 1.05rem;">
                            จำนวนการแก้ไขเอกสาร: <span id="rt_editCountPdf" class="fw-bold ms-1">0</span> <span class="ms-1">ครั้ง</span>
                        </div>
                        <div class="ms-auto bg-white rounded-pill shadow-sm border d-inline-flex align-items-center px-4 py-2 gap-2">
                            <div class="form-check form-switch mb-0" style="padding-left: 0;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="switchTrafficReportToStd" checked
                                   style="width: 3rem; height: 1.5rem; cursor: pointer;"
                                   onchange="if(!this.checked){ this.checked=true; switchTrafficReportToStandard(); }">
                            </div>
                            <label class="fw-bold text-danger mb-0" for="switchTrafficReportToStd" style="font-size: 1.05rem; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-file-pdf me-1"></i>กรอกตามฟอร์มเสมือนจริง
                            </label>
                        </div>
                    </div>

<!-- ================================================================ -->
<!-- ========================= PAGE 1 ============================== -->
<!-- ================================================================ -->
<div class="rtpf-page">

    <!-- Page header -->
    <div class="page-header">
        <div></div>
        <div class="rtpdf-page-no" style="font-size: 12px;"></div>
    </div>

    <!-- Report Number -->
    <div class="fr" style="margin-top: 10px;">
        <span class="fl">รายงานการตรวจพิสูจน์ที่</span>
        <input type="text" class="rtpf-inp rtpf-inp-m" name="rt_report_ref" id="rtpdf_report_ref">
        <span class="fl">/25</span>
        <input type="number" min="0" step="1" class="rtpf-inp rtpf-inp-s" name="rt_report_year" id="rtpdf_report_year" value="<?= substr((date('Y') + 543), -2) ?>">
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="rtpf-inp rtpf-inp-l" name="rt_agency_name" id="rtpdf_agency_name">
    </div>

    <!-- Title -->
    <div class="form-title">รายงานการตรวจพิสูจน์คดีจราจร</div>

    <!-- ==================== 1. การรับแจ้งเหตุ ==================== -->
    <div class="sec-heading">1. <u>การรับแจ้งเหตุ</u></div>

    <div class="fr i1">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="rtpf-inp rtpf-inp-m" name="rt_receive_date" id="rtpdf_receive_date">
        <span class="fl">เวลา</span>
        <input type="time" class="rtpf-inp rtpf-inp-time" name="rt_receive_time" id="rtpdf_receive_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">ตามประจำวันข้อที่</span>
        <input type="text" class="rtpf-inp rtpf-inp-l" name="rt_daily_ref" id="rtpdf_daily_ref">
    </div>

    <div class="fr i1">
        <span class="fl">กลุ่มงานตรวจสถานที่เกิดเหตุ</span>
    </div>

    <div class="fr i1">
        <span class="fl">กองพิสูจน์หลักฐานกลาง/ ศูนย์พิสูจน์หลักฐาน</span>
        <input type="text" class="rtpf-inp" name="rt_agency_center_name" id="rtpdf_agency_center_name">
        <span class="fl">/ พิสูจน์หลักฐานจังหวัด</span>
        <input type="text" class="rtpf-inp" name="rt_agency_province_name" id="rtpdf_agency_province_name">
    </div>

    <div class="fr i1">
        <span class="fl">ได้รับแจ้งตาม</span>
        <label class="ck"><input type="checkbox" class="rtpf-cb" name="rt_notify_method[]" value="หนังสือ" id="rtpdf_notify_letter">หนังสือ</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="rtpf-cb" name="rt_notify_method[]" value="โทรศัพท์" id="rtpdf_notify_phone">ทางโทรศัพท์</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="rtpf-cb" name="rt_notify_method[]" value="วิทยุสื่อสาร" id="rtpdf_notify_radio">วิทยุสื่อสาร</label>
        <span class="fl">/</span>
        <label class="ck"><input type="checkbox" class="rtpf-cb" name="rt_notify_method[]" value="อื่นๆ" id="rtpdf_notify_other">อื่นๆ</label>
    </div>
    <div class="fr i2">
        <span class="fl">ระบุ</span>
        <input type="text" class="rtpf-inp" name="rt_notify_other_text" id="rtpdf_notify_other_text">
    </div>

    <div class="fr i1">
        <span class="fl">จาก สถานีตำรวจนครบาล/สถานีตำรวจภูธร</span>
        <span class="fr-break"></span>
        <input type="text" class="rtpf-inp rtpf-inp-line" name="rt_from_station" id="rtpdf_from_station">
    </div>

    <div class="fr i1">
        <span class="fl">ของเจ้าหน้าที่ ร่วมตรวจ</span>
    </div>
    <div class="fr i1">
        <span class="fl">พิสูจน์คดีอุบัติเหตุจราจร โดยมี</span>
    </div>

    <div class="fr i1">
        <input type="text" class="rtpf-inp rtpf-inp-l" name="rt_officer_name" id="rtpdf_officer_name">
    </div>
    <div class="fr i1">
        <span class="fl">เป็นพนักงานสอบสวน</span>
    </div>

    <!-- รายละเอียดรถของกลาง -->
    <div class="fr i1" style="margin-top: 6px;">
        <span class="fl">รายละเอียดปรากฏดังนี้</span>
    </div>

    <div id="rtpdf_vehicle_container">
        <!-- รถของกลางรายการที่ 1 -->
        <div class="rtpf-vehicle-card rtpdf-vehicle-block">
            <div class="fr i1">
                <span class="fl-b">1.1</span>
                <span class="fl">รถของกลางรายการที่ ๑ เป็นรถ</span>
                <input type="text" class="rtpf-inp" name="rt_vehicle_type[]">
            </div>
            <div class="fr i2">
                <span class="fl">ภาษาอังกฤษด้วย) สี</span>
                <input type="text" class="rtpf-inp rtpf-inp-s" name="rt_vehicle_color[]">
                <span class="fl">ยี่ห้อ</span>
                <input type="text" class="rtpf-inp" name="rt_vehicle_brand[]">
            </div>
            <div class="fr i2">
                <span class="fl">แผ่นป้ายทะเบียนหมายเลข</span>
                <input type="text" class="rtpf-inp" name="rt_vehicle_plate[]">
                <span class="fl">จำนวน</span>
                <input type="number" min="0" step="1" class="rtpf-inp rtpf-inp-s" name="rt_vehicle_plate_qty[]" value="1">
                <span class="fl">คัน</span>
            </div>
        </div>
    </div>
    <button type="button" class="rtpf-add-btn" onclick="rtpdfAddVehicle()">+ เพิ่มรถของกลาง</button>

    <div class="fr i1" style="margin-top: 6px;">
        <span class="fl-b">1.3</span>
        <span class="fl">สถานที่เกิดเหตุ</span>
        <input type="text" class="rtpf-inp" name="rt_scene_location" id="rtpdf_scene_location">
    </div>
    <div class="fr i2">
        <span class="fl">(ให้ระบุกรณีที่มีการตรวจสถานที่เกิดเหตุ แต่ถ้าไม่ได้ตรวจฯ ให้ตัดข้อ ๑.๓ ออก)</span>
    </div>

    <!-- ==================== 2. จุดประสงค์ในการตรวจพิสูจน์ ==================== -->
    <div class="sec-heading" style="margin-top: 10px;">2. <u>จุดประสงค์ในการตรวจพิสูจน์</u> <span style="font-weight:400;">(เลือกตามความเหมาะสม)</span></div>

    <div class="fr i1">
        <span class="fl">(๑. เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนระหว่างรถของกลาง</span>
        <input type="number" min="0" step="1" class="rtpf-inp rtpf-inp-s" name="rt_purpose_qty_1" id="rtpdf_purpose_qty_1">
        <span class="fl">(ระบุจำนวนคัน) หรือไม่ อย่างไร</span>
    </div>
    <div class="fr i1">
        <span class="fl">หรือ ๒. เพื่อทราบว่ารถของกลาง</span>
        <input type="number" min="0" step="1" class="rtpf-inp rtpf-inp-s" name="rt_purpose_qty_2" id="rtpdf_purpose_qty_2">
        <span class="fl">(ระบุจำนวนคัน) มีการเฉี่ยวชนกันหรือไม่ อย่างไร</span>
    </div>
    <div class="fr i1">
        <span class="fl">หรือ ๓.เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนที่รถของกลางทั้งหนึ่งหรือไม่และมีลักษณะการเฉี่ยวชนอย่างไร</span>
    </div>
    <div class="fr i1">
        <span class="fl">หรือ ๔. เพื่อทราบว่ามีร่องรอยการเฉี่ยวชนระหว่างรถของกลางหรือไม่อย่างไร</span>
    </div>
    <div class="fr i1">
        <span class="fl">หรือ ๕.</span>
        <input type="text" class="rtpf-inp" name="rt_purpose_other_text" id="rtpdf_purpose_other_text">
        <span class="fl">)</span>
    </div>

    <!-- ==================== 3. ผู้ตรวจพิสูจน์ ==================== -->
    <div class="sec-heading" style="margin-top: 10px;">3. <u>ผู้ตรวจพิสูจน์</u></div>

    <div id="rtpdf_inspector_container">
        <div class="fr i1 rtpdf-inspector-row">
            <span class="fl">3.1</span>
            <select class="rtpf-select rp-inspector-select" name="rt_inspector_name[]">
                <?= $rtpdfInspectorOptions ?>
            </select>
            <span class="fl">ตำแหน่ง</span>
            <input type="text" class="rtpf-inp rtpf-inp-m rp-inspector-position" name="rt_inspector_position[]" readonly>
            <button type="button" class="rtpf-del-btn" onclick="rtpdfRemoveInspector(this)" title="ลบ">✕</button>
        </div>
    </div>
    <button type="button" class="rtpf-add-btn" onclick="rtpdfAddInspector()">+ เพิ่มผู้ตรวจ</button>

    <!-- ==================== 4. ผลการตรวจพิสูจน์ ==================== -->
    <div class="sec-heading" style="margin-top: 10px;">4. <u>ผลการตรวจพิสูจน์</u></div>

    <div class="fr i1">
        <span class="fl">พฤติการณ์คดี</span>
    </div>
    <textarea class="rtpf-ta" name="rt_case_behavior" id="rtpdf_case_behavior" rows="3" style="margin-left:20px;"></textarea>

    <!-- Footer Page 1 -->
    <div class="form-footer">
        <div></div>
        <div class="form-footer-right">รายงานการตรวจพิสูจน์คดีจราจร</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 2 ============================== -->
<!-- ================================================================ -->
<div class="rtpf-page">

    <!-- Page header -->
    <div class="page-header">
        <div  style="font-size:13px; line-height:1.6;">
            <span class="fl">รายงานการตรวจพิสูจน์ที่</span>
            <input type="text" class="rtpf-inp rtpf-inp-m" name="rt_report_ref_2" id="rtpdf_report_ref_2">
            <span class="fl">/25</span>
            <input type="number" min="0" step="1" class="rtpf-inp rtpf-inp-s" name="rt_report_year" id="rtpdf_report_year" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        
        <div class="rtpdf-page-no" style="font-size: 12px;"></div>
    </div>
    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="rtpf-inp rtpf-inp-l" name="rt_agency_name_2" id="rtpdf_agency_name_2">
    </div>
    <div class="fr i1" style="margin-top: 8px;">
        <span class="fl">ได้ทำการตรวจพิสูจน์</span>
        <span class="fr-break"></span>
        <input type="text" class="rtpf-inp rtpf-inp-line" name="rt_inspect_location" id="rtpdf_inspect_location">
    </div>
    <div class="fr i1">
        <span class="fl">ที่</span>
        <input type="text" class="rtpf-inp rtpf-inp-l" name="rt_inspect_place" id="rtpdf_inspect_place">
        <span class="fl">(ระบุสถานที่ตรวจฯ)</span>
    </div>
    <div class="fr i1 fr-nowrap">
        <span class="fl">เมื่อวันที่</span>
        <input type="date" class="rtpf-inp rtpf-inp-date" name="rt_analyze_date" id="rtpdf_analyze_date">
        <span class="fl">เวลาประมาณ</span>
        <input type="time" class="rtpf-inp rtpf-inp-time" name="rt_analyze_time" id="rtpdf_analyze_time">
        <span class="fl">น.</span>
    </div>
    <div class="fr i1">
        <span class="fl">ปรากฏรายละเอียดดังนี้</span>
    </div>

    <!-- 4.1 รถของกลางรายการที่ 1 -->
    <div id="rtpdf_analysis_vehicle_container">
        <div class="rtpdf-analysis-block" style="margin-top: 6px;">
            <div class="fl-b i1">4.1 รถของกลางรายการที่ 1</div>
            <div class="fr i1">
                <span class="fl">เป็นรถ</span>
                <input type="text" class="rtpf-inp" name="rt_analysis_vehicle_desc[]">
            </div>

            <!-- 4.1.1 ด้านหน้า -->
            <div class="i1" style="margin-top: 4px;">
                <span class="fl-b">4.1.1 ตรวจพบสภาพและความเสียหายด้านหน้ารถ ดังนี้</span>
            </div>
            <div class="i2">
                <div class="fr">
                    <span class="fl">4.1.1.1</span>
                    <input type="text" class="rtpf-inp" name="rt_damage_front_detail_1[]">
                </div>
                <div class="fr" style="padding-left: 55px;">
                    <span class="fl">(ป้ายหมายเลข</span>
                    <input type="text" class="rtpf-inp rtpf-inp-s" name="rt_damage_front_sign_1[]">
                    <span class="fl">ในภาพถ่ายที่</span>
                    <input type="text" class="rtpf-inp rtpf-inp-s" name="rt_damage_front_photo_1[]">
                    <span class="fl">)</span>
                </div>
                <div class="fr">
                    <span class="fl">4.1.1.2</span>
                    <input type="text" class="rtpf-inp" name="rt_damage_front_detail_2[]">
                </div>
                <div class="fr" style="padding-left: 55px;">
                    <span class="fl">(ป้ายหมายเลข</span>
                    <input type="text" class="rtpf-inp rtpf-inp-s" name="rt_damage_front_sign_2[]">
                    <span class="fl">ในภาพถ่ายที่</span>
                    <input type="text" class="rtpf-inp rtpf-inp-s" name="rt_damage_front_photo_2[]">
                    <span class="fl">)</span>
                </div>
            </div>

            <!-- 4.1.2 ด้านซ้าย -->
            <div class="i1" style="margin-top: 4px;">
                <span class="fl-b">4.1.2 ตรวจพบสภาพและความเสียหายด้านซ้ายรถ ดังนี้</span>
            </div>
            <div class="i2">
                <textarea class="rtpf-ta" name="rt_damage_left_detail[]" rows="2" style="margin-left:0;"></textarea>
            </div>

            <!-- 4.1.3 ด้านท้าย -->
            <div class="i1" style="margin-top: 4px;">
                <span class="fl-b">4.1.3 ตรวจพบสภาพและความเสียหายด้านท้ายรถ ดังนี้</span>
            </div>
            <div class="i2">
                <textarea class="rtpf-ta" name="rt_damage_rear_detail[]" rows="2" style="margin-left:0;"></textarea>
            </div>

            <!-- 4.1.4 ด้านขวา -->
            <div class="i1" style="margin-top: 4px;">
                <span class="fl-b">4.1.4 ตรวจพบสภาพและความเสียหายด้านขวารถ ดังนี้</span>
            </div>
            <div class="i2">
                <textarea class="rtpf-ta" name="rt_damage_right_detail[]" rows="2" style="margin-left:0;"></textarea>
            </div>

            <!-- 4.1.5 บริเวณอื่นๆ -->
            <div class="i1" style="margin-top: 4px;">
                <span class="fl-b">4.1.5 ตรวจพบสภาพและความเสียหายบริเวณอื่นๆ ดังนี้</span>
            </div>
            <div class="i2">
                <textarea class="rtpf-ta" name="rt_damage_other_detail[]" rows="2" style="margin-left:0;"></textarea>
            </div>
        </div>
    </div>
    <button type="button" class="rtpf-add-btn" onclick="rtpdfAddAnalysisVehicle()">+ เพิ่มรถของกลาง (ผลการตรวจ)</button>

    <!-- Footer Page 2 -->
    <div class="form-footer">
        <div></div>
        <div class="form-footer-right">รายงานการตรวจพิสูจน์คดีจราจร</div>
    </div>
</div>

<!-- ================================================================ -->
<!-- ========================= PAGE 3 ============================== -->
<!-- ================================================================ -->
<div class="rtpf-page">

    <!-- Page header -->
    <div class="page-header">
        <div  style="font-size:13px; line-height:1.6;">
            <span class="fl">รายงานการตรวจพิสูจน์ที่</span>
            <input type="text" class="rtpf-inp rtpf-inp-m" name="rt_report_ref_3" id="rtpdf_report_ref_3">
            <span class="fl">/25</span>
            <input type="number" min="0" step="1" class="rtpf-inp rtpf-inp-s" name="rt_report_year" id="rtpdf_report_year" value="<?= substr((date('Y') + 543), -2) ?>">
        </div>
        <div class="rtpdf-page-no" style="font-size: 12px;"></div>
    </div>

    <div class="fr">
        <span class="fl">หน่วยงาน</span>
        <input type="text" class="rtpf-inp rtpf-inp-l" name="rt_agency_name_3" id="rtpdf_agency_name_3">
    </div>

    <!-- 4.x ถ้ามี -->
    <div class="fr i1" style="margin-top: 8px;">
        <span class="fl-b">๔.๓</span>
    </div>
    <textarea class="rtpf-ta rtpf-ta-line" name="rt_analysis_extra" id="rtpdf_analysis_extra" rows="2"></textarea>

    <!-- 4.x+1 ลักษณะของสถานที่เกิดเหตุ -->
    <div class="fr i1" style="margin-top: 4px;">
        <span class="fl-b">๔.๔ ลักษณะของสถานที่เกิดเหตุ</span>
    </div>
    <textarea class="rtpf-ta rtpf-ta-line" name="rt_scene_detail" id="rtpdf_scene_detail" rows="2"></textarea>

    <!-- ==================== 5. ผลการตรวจเปรียบเทียบ ==================== -->
    <div class="sec-heading" style="margin-top: 12px;">5. <u>ผลการตรวจเปรียบเทียบ</u></div>

    <div class="fr i1">
        <span class="fl">จากการเปรียบเทียบสภาพร่องรอยความเสียหายและการแลกเปลี่ยนวัตถุพยานของรถของกลางทั้ง</span>
    </div>
    <div class="fr i2">
        <input type="text" class="rtpf-inp rtpf-inp-s" name="rt_compare_total_items" id="rtpdf_compare_total_items">
        <span class="fl">รายการ พบว่า</span>
    </div>

    <div class="fr i1" style="margin-top: 4px;">
        <span class="fl-b">๕.๑</span>
        <span class="fl">รอยครูดบริเวณ</span>
    </div>
    <textarea class="rtpf-ta rtpf-ta-line" name="rt_compare_5_1" id="rtpdf_compare_5_1" rows="2"></textarea>
    <div class="fr i2">
        <span class="fl">กับรอยครูดบริเวณ</span>
    </div>
    <textarea class="rtpf-ta rtpf-ta-line" name="rt_compare_5_1_sub" id="rtpdf_compare_5_1_sub" rows="2"></textarea>
    <div class="fr i2" style="margin-top: 2px;">
        <span class="fl">ตามผลการตรวจในข้อ</span>
        <input type="text" class="rtpf-inp rtpf-inp-s" name="rt_compare_5_ref" id="rtpdf_compare_5_ref" style="max-width:80px;">
    </div>

    <div class="fr i1" style="margin-top: 4px;">
        <span class="fl-b">๕.๒</span>
    </div>
    <textarea class="rtpf-ta rtpf-ta-line" name="rt_compare_5_2" id="rtpdf_compare_5_2" rows="2"></textarea>

    <div class="fr i1" style="margin-top: 4px;">
        <span class="fl-b">๕.๓</span>
    </div>
    <textarea class="rtpf-ta rtpf-ta-line" name="rt_compare_5_3" id="rtpdf_compare_5_3" rows="2"></textarea>

    <div class="fr i1" style="margin-top: 4px;">
        <span class="fl-b">๕.๔</span>
    </div>
    <textarea class="rtpf-ta rtpf-ta-line" name="rt_compare_5_4" id="rtpdf_compare_5_4" rows="2"></textarea>

    <!-- ==================== 6. ความเห็น ==================== -->
    <div class="sec-heading" style="margin-top: 12px;">6. <u>ความเห็น</u></div>

    <div class="fr i1">
        <span class="fl">จากผลการตรวจในข้อ ๔ และ ๕</span>
    </div>
    <textarea class="rtpf-ta rtpf-ta-line" name="rt_opinion" id="rtpdf_opinion" rows="3"></textarea>
    <div class="fr i1">
        <span class="fl">จนได้รับความเสียหายดังที่ปรากฏ</span>
    </div>

    <!-- ==================== ลงชื่อ ==================== -->
    <div class="signature-block">
        <div>ลงชื่อ ........................................ ผู้รายงาน</div>
        <div>(<input type="text" class="rtpf-inp" name="rt_sign_name" id="rtpdf_sign_name" style="width:200px; text-align:center; display:inline-block;">)</div>
        <div>ตำแหน่ง <input type="text" class="rtpf-inp" name="rt_sign_position" id="rtpdf_sign_position" style="width:200px; text-align:center; display:inline-block;"></div>
        <div>วันที่ <input type="date" class="rtpf-inp" name="rt_sign_date" id="rtpdf_sign_date" style="width:180px; text-align:center; display:inline-block;"></div>
    </div>

    <!-- Footer Page 3 -->
    <div class="form-footer">
        <div></div>
        <div class="form-footer-right">รายงานการตรวจพิสูจน์คดีจราจร</div>
    </div>
</div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-end py-2">
                <button type="button" class="btn btn-success btn-sm" id="btn_save_report_traffic_pdf">
                    <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                </button>
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> ยกเลิก
                </button>
            </div>

        </div>
    </div>
</div>

<script>
/* ========== Traffic Report PDF: Inspector Management ========== */
function rtpdfAddInspector() {
    var container = document.getElementById('rtpdf_inspector_container');
    var count = container.querySelectorAll('.rtpdf-inspector-row').length + 1;
    var html = `
    <div class="fr i1 rtpdf-inspector-row">
        <span class="fl">3.${count}</span>
        <select class="rtpf-select rp-inspector-select" name="rt_inspector_name[]">
            <?= str_replace("'", "\\'", $rtpdfInspectorOptions) ?>
        </select>
        <span class="fl">ตำแหน่ง</span>
        <input type="text" class="rtpf-inp rtpf-inp-m rp-inspector-position" name="rt_inspector_position[]" readonly>
        <button type="button" class="rtpf-del-btn" onclick="rtpdfRemoveInspector(this)" title="ลบ">✕</button>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);

    var $modal = $('#modalReportTrafficPdf');
    var $newSelect = $(container).find('.rtpdf-inspector-row:last .rp-inspector-select');
    if (typeof rpLoadUsers === 'function' && typeof rpBuildUserOptions === 'function') {
        rpLoadUsers(function() {
            var current = $newSelect.val() || '';
            $newSelect.html(rpBuildUserOptions(current));
            if ($newSelect.hasClass('select2-hidden-accessible')) {
                $newSelect.select2('destroy');
            }
            $newSelect.select2({
                theme: 'bootstrap-5', width: '100%', placeholder: '-- เลือกผู้ตรวจ --',
                allowClear: true, dropdownParent: $modal
            });
            $newSelect.trigger('change');
        });
    }
}

function rtpdfRemoveInspector(btn) {
    var row = btn.closest('.rtpdf-inspector-row');
    row.remove();
    rtpdfRenumberInspectors();
}

function rtpdfRenumberInspectors() {
    var rows = document.querySelectorAll('#rtpdf_inspector_container .rtpdf-inspector-row');
    rows.forEach(function(row, idx) {
        var label = row.querySelector('.fl');
        if (label) label.textContent = '3.' + (idx + 1);
    });
}

/* ========== Traffic Report PDF: Vehicle Management ========== */
function rtpdfAddVehicle() {
    var container = document.getElementById('rtpdf_vehicle_container');
    var count = container.querySelectorAll('.rtpdf-vehicle-block').length + 1;
    var parentNum = '1.' + count;
    var html = `
    <div class="rtpf-vehicle-card rtpdf-vehicle-block">
        <div class="d-flex justify-content-between align-items-center">
            <div class="fr i1">
                <span class="fl-b rtpdf-veh-num">${parentNum}</span>
                <span class="fl">รถของกลางรายการที่ ${count} เป็นรถ</span>
                <input type="text" class="rtpf-inp" name="rt_vehicle_type[]">
            </div>
            <button type="button" class="rtpf-del-btn" onclick="rtpdfRemoveVehicle(this)">✕</button>
        </div>
        <div class="fr i2">
            <span class="fl">ภาษาอังกฤษด้วย) สี</span>
            <input type="text" class="rtpf-inp rtpf-inp-s" name="rt_vehicle_color[]">
            <span class="fl">ยี่ห้อ</span>
            <input type="text" class="rtpf-inp" name="rt_vehicle_brand[]">
        </div>
        <div class="fr i2">
            <span class="fl">แผ่นป้ายทะเบียนหมายเลข</span>
            <input type="text" class="rtpf-inp" name="rt_vehicle_plate[]">
            <span class="fl">จำนวน</span>
            <input type="text" class="rtpf-inp rtpf-inp-s" name="rt_vehicle_plate_qty[]" value="1">
            <span class="fl">คัน</span>
        </div>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

function rtpdfRemoveVehicle(btn) {
    var block = btn.closest('.rtpdf-vehicle-block');
    block.remove();
    rtpdfRenumberVehicles();
}

function rtpdfRenumberVehicles() {
    var blocks = document.querySelectorAll('#rtpdf_vehicle_container .rtpdf-vehicle-block');
    blocks.forEach(function(b, idx) {
        var numEl = b.querySelector('.rtpdf-veh-num');
        if (numEl) numEl.textContent = '1.' + (idx + 1);
    });
}

/* ========== Traffic Report PDF: Analysis Vehicle Management ========== */
function rtpdfAddAnalysisVehicle() {
    var container = document.getElementById('rtpdf_analysis_vehicle_container');
    var count = container.querySelectorAll('.rtpdf-analysis-block').length + 1;
    var sec = '4.' + count;
    var html = `
    <div class="rtpdf-analysis-block" style="margin-top: 6px;">
        <div class="d-flex justify-content-between align-items-center">
            <div class="fl-b i1 rtpdf-analysis-num">${sec} รถของกลางรายการที่ ${count}</div>
            <button type="button" class="rtpf-del-btn" onclick="rtpdfRemoveAnalysisVehicle(this)">✕</button>
        </div>
        <div class="fr i1">
            <span class="fl">เป็นรถ</span>
            <input type="text" class="rtpf-inp" name="rt_analysis_vehicle_desc[]">
        </div>
        <div class="i1" style="margin-top:4px;"><span class="fl-b">${sec}.1 ตรวจพบสภาพและความเสียหายด้านหน้ารถ ดังนี้</span></div>
        <div class="i2">
            <div class="fr"><span class="fl">${sec}.1.1</span><input type="text" class="rtpf-inp" name="rt_damage_front_detail_1[]"></div>
            <div class="fr"><span class="fl">${sec}.1.2</span><input type="text" class="rtpf-inp" name="rt_damage_front_detail_2[]"></div>
        </div>
        <div class="i1" style="margin-top:4px;"><span class="fl-b">${sec}.2 ตรวจพบสภาพและความเสียหายด้านซ้ายรถ ดังนี้</span></div>
        <div class="i2"><textarea class="rtpf-ta" name="rt_damage_left_detail[]" rows="2" style="margin-left:0;"></textarea></div>
        <div class="i1" style="margin-top:4px;"><span class="fl-b">${sec}.3 ตรวจพบสภาพและความเสียหายด้านท้ายรถ ดังนี้</span></div>
        <div class="i2"><textarea class="rtpf-ta" name="rt_damage_rear_detail[]" rows="2" style="margin-left:0;"></textarea></div>
        <div class="i1" style="margin-top:4px;"><span class="fl-b">${sec}.4 ตรวจพบสภาพและความเสียหายด้านขวารถ ดังนี้</span></div>
        <div class="i2"><textarea class="rtpf-ta" name="rt_damage_right_detail[]" rows="2" style="margin-left:0;"></textarea></div>
        <div class="i1" style="margin-top:4px;"><span class="fl-b">${sec}.5 ตรวจพบสภาพและความเสียหายบริเวณอื่นๆ ดังนี้</span></div>
        <div class="i2"><textarea class="rtpf-ta" name="rt_damage_other_detail[]" rows="2" style="margin-left:0;"></textarea></div>
    </div>`;
    container.insertAdjacentHTML('beforeend', html);
}

function rtpdfRemoveAnalysisVehicle(btn) {
    var block = btn.closest('.rtpdf-analysis-block');
    block.remove();
}

/* ========== Traffic Report PDF: Page Numbers + Arabic Digits ========== */
function rtpdfUpdatePageNumbers() {
    var pages = document.querySelectorAll('#modalReportTrafficPdf .rtpf-page');
    var total = pages.length;
    pages.forEach(function(page, idx) {
        var pageNo = page.querySelector('.rtpdf-page-no');
        if (pageNo) {
            pageNo.textContent = (idx + 1) + '/' + total;
        }
    });
}

function rtpdfThaiDigitsToArabic(str) {
    if (!str) return str;
    var map = { '๐':'0','๑':'1','๒':'2','๓':'3','๔':'4','๕':'5','๖':'6','๗':'7','๘':'8','๙':'9' };
    return String(str).replace(/[๐-๙]/g, function(ch) { return map[ch] || ch; });
}

function rtpdfConvertStaticThaiDigitsToArabic() {
    var root = document.getElementById('modalReportTrafficPdf');
    if (!root || root.dataset.digitsConverted === '1') return;

    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
    var node;
    while ((node = walker.nextNode())) {
        if (!node.nodeValue) continue;
        node.nodeValue = rtpdfThaiDigitsToArabic(node.nodeValue);
    }

    root.dataset.digitsConverted = '1';
}

document.addEventListener('DOMContentLoaded', function() {
    rtpdfConvertStaticThaiDigitsToArabic();
    rtpdfUpdatePageNumbers();

    var modalEl = document.getElementById('modalReportTrafficPdf');
    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', function() {
            rtpdfUpdatePageNumbers();
        });
    }

    $('#modalReportTrafficPdf').on('shown.bs.modal', function() {
        var $modal = $(this);
        if (typeof rpLoadUsers === 'function' && typeof rpBuildUserOptions === 'function') {
            rpLoadUsers(function() {
                $modal.find('#rtpdf_inspector_container .rp-inspector-select').each(function() {
                    var current = $(this).val();
                    $(this).html(rpBuildUserOptions(current));
                });
                $modal.find('#rtpdf_inspector_container .rp-inspector-select').each(function() {
                    if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
                    $(this).select2({
                        theme: 'bootstrap-5', width: '100%', placeholder: '-- เลือกผู้ตรวจ --',
                        allowClear: true, dropdownParent: $modal
                    });
                    $(this).trigger('change');
                });
            });
        }
    });

});

function toThaiNumeral(num) {
    return String(num);
}
</script>
