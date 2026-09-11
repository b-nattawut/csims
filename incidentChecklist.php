<?php
require_once __DIR__ . '/includes/session_config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require __DIR__ . '/helpers/report_no.php';

$qryData = "SELECT count(id) as countData FROM rn_ReceiveNoti where statusDelete = 0";
$stmt = $pdo->prepare($qryData);
$stmt->execute();
$countData = $stmt->fetchColumn();

$limit = 15;
$total_pages = ceil($countData / $limit);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$title = "รายการตรวจสอบการปฏิบัติงาน (Checklist) - ระบบบริหารจัดการฐานข้อมูลวัตถุพยาน";

ob_start();
?>

<?php
$extra_css = ob_get_clean();
ob_start();
?>

<!-- Card ของ Filter Form รวม input Field ที่ใช้สำหรับกรองข้อมูลรายการ checklist ที่แสดงในตาราง -->
<div class="card mb-3 shadow-sm" style="font-size:13px;">
    <div class="card-body p-2">
        <div class="d-flex flex-wrap justify-content-end align-items-center mb-2">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterSection" aria-expanded="true">
                <i class="fas fa-filter me-1"></i> ตัวกรอง
            </button>
        </div>

        <div class="collapse show" id="filterSection">
            <div class="bg-light p-3 rounded-3 border mb-2 shadow-sm">

                <form id="searchFilterForm" name="searchFilterForm">
                    <div class="row g-3 justify-content-center align-items-end">

                        <!-- <div class="col-md-2 col-sm-2 mt-4">
                            <button class="mt-1 btn btn-success btn-sm px-3 fw-bold shadow-sm text-nowrap px-4 w-100" type="button" data-bs-toggle="modal" data-bs-target="#addChecklistModal">
                                <i class="fas fa-plus me-1"></i> เพิ่ม<span class="d-none d-lg-inline">รายการ</span>
                            </button>
                        </div> -->

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">เลขที่เอกสาร</label>
                            <input type="text" class="form-control form-control-sm" id="filter_doc_no" name="filter_doc_no">
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">เลขที่รายงาน</label>
                            <input type="text" class="form-control form-control-sm" id="filter_report_no" name="filter_report_no">
                        </div>


                        <div class="col-md-3 col-sm-6 ms-2">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">เหตุที่รับแจ้ง</label>
                            <select class="form-select form-select-sm" id="filter_incident_type" name="filter_incident_type">
                                <option value="" selected disabled>กรุณาเลือก</option>
                                <option value="01">ทรัพย์</option>
                                <option value="02">ชีวิต</option>
                                <option value="03">ระเบิด</option>
                                <option value="04">เพลิงไหม้</option>
                                <option value="05">จราจร</option>
                                <option value="06">ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)</option>
                                <option value="07">ตรวจเก็บวัตถุพยานที่เกิดเหตุ</option>
                                <option value="08">ตรวจเก็บวัตถุพยานบุคคล</option>
                            </select>
                        </div>

                        <div class="col-md-2 col-sm-6 ">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">จังหวัด</label>
                            <select class="form-select form-select-sm" id="filter_province" name="filter_province">
                                <option value="" selected disabled>กรุณาเลือก</option>
                                <option value="95">ยะลา</option>
                                <option value="94">ปัตตานี</option>
                                <option value="96">นราธิวาส</option>
                            </select>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">สภ./สน.</label>
                            <select class="form-select form-select-sm" id="filter_station" name="filter_station">
                                <?php
                                $qryPoliceStation = "SELECT * FROM master_police_station ORDER BY id DESC";
                                $stmt = $pdo->query($qryPoliceStation);
                                ?>
                                <option value="" selected disabled>กรุณาเลือก</option>
                                <?php
                                while ($station = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<option value="' . $station['station_name'] . '" data-province="' . (int)($station['province_id'] ?? '') . '">' . $station['station_name'] . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-2 col-sm-3">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">ผู้แจ้ง</label>
                            <input type="text" class="form-control form-control-sm" id="filter_person" name="filter_person">
                        </div>

                        <!-- <div class="col-md-2 col-sm-6 ms-2">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">ช่องทางที่รับแจ้ง</label>
                            <select class="form-select form-select-sm" id="filter_report_channel" name="filter_report_channel">
                                <option value="" selected disabled>กรุณาเลือก</option>
                                <option value="t">โทรศัพท์</option>
                                <option value="r">วิทยุสื่อสาร</option>
                                <option value="o">อื่น ๆ</option>
                            </select>
                        </div> -->

                        <div class="col-md-12 d-flex justify-content-center align-items-center gap-2 mt-4">
                            <button type="button" class="btn btn-warning btn-sm text-dark fw-bold px-3" id="btn_clear_filter">
                                <i class="fas fa-undo me-1"></i> ล้างค่า
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" id="btn_search_filter">
                                <i class="fas fa-search me-1"></i> ค้นหา
                            </button>
                            <button type="button" class="btn btn-success btn-sm px-3 fw-bold" id="btn_export">
                                <i class="fas fa-file-excel me-1"></i> Export
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Card ของ Table ที่แสดงรายการ checklist ทั้งหมด -->
<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-end align-items-center mb-3">
            <div class="text-muted small">
                จำนวนข้อมูลทั้งหมด : <span id="count_display" name="count_display"><?= $countData ?></span> รายการ
            </div>
        </div>

        <div class="table-responsive">
            <div class="table-wrapper-focus">
                <table class="table table-bordered table-striped table-hover table-custom align-middle mb-0">
                    <thead style="font-size: 14px;">
                        <tr class="text-nowrap text-center ">
                            <th style="width: 4%">ลำดับ</th>
                            <th style="width: 11%">เลขที่เอกสาร</th>
                            <th style="width: 9%">เลขที่รายงาน</th>
                            <th style="width: 12%">สภ./สน.</th>
                            <th style="width: 7%">จังหวัด</th>
                            <th style="width: 12%">เหตุที่รับแจ้ง</th>
                            <th style="width: 8%">ช่องทาง</th>
                            <th style="width: 13%">ผู้แจ้ง</th>
                            <th style="width: 11%">รายงาน Checklist</th>
                            <th style="width: 8%">F-CS-11</th>
                            <th style="width: 8%">QR Code วัตถุพยาน</th>
                        </tr>
                    </thead>
                    <tbody id="table_body" style="font-size: 14px;">
                        <?php
                        $qryDataTable = "SELECT t1.province,t1.id,t1.create_by,t1.receiveNoti_No,t1.receiveNoti_No_TH,t1.receiveNotiReportNo,t1.receiveNotiReportNo_TH,t1.complaints_From,t1.inquiry_official_full_name,CONCAT(t4.rank_name,' ',t3.first_name,' ',t3.last_name) AS fullname_create,
                                        t1.statusChecklist,
                                        t1.complaints_type,
                                        case when t1.complaints_type = '01' then 'ทรัพย์'
                                        when t1.complaints_type = '02' then 'ชีวิต'
                                        when t1.complaints_type = '03' then 'ระเบิด'
                                        when t1.complaints_type = '04' then 'เพลิงไหม้'
                                        when t1.complaints_type = '05' then 'จราจร'
                                        when t1.complaints_type = '06' then 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)'
                                        when t1.complaints_type = '07' then 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ'
                                        when t1.complaints_type = '08' then 'ตรวจเก็บวัตถุพยานบุคคล'
                                        ELSE CONCAT('ไม่ทราบ (', t1.complaints_type, ')') END AS complaintstype,
                                        case when t1.complaints_From_Device = 'r' then 'วิทยุสื่อสาร'
                                        when t1.complaints_From_Device = 't' then 'ทางโทรศัพท์'
                                        ELSE 'อื่นๆ' END AS complaintsdevice,
                                        DATE_FORMAT(t1.create_dateTimeStamp, '%d/%m/%Y %H:%i:%s') AS createdate
                                        FROM rn_ReceiveNoti t1
                                        LEFT JOIN users t2 ON t1.create_by = t2.user_id
                                        LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
                                        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
                                        WHERE t1.statusDelete = 0 AND t2.is_active = 1
                                        ORDER BY t1.id DESC
                                        LIMIT 15 OFFSET 0
                                    ";
                        $stmt = $pdo->prepare($qryDataTable);
                        $stmt->execute();
                        if ($stmt->rowCount() > 0) {
                            // วนลูปข้อมูล
                            $index = 1;
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                // กำหนดสีพื้นหลังตามสถานะ: บันทึกแล้ว = สีขาว, ยังไม่บันทึก = สีเทา
                                $id = $row['id'];
                                $rowBgStyle = (!empty($row['statusChecklist']) && $row['statusChecklist'] == 1)
                                    ? 'background-color: #ffffff !important;'
                                    : 'background-color: #e9ecef !important;';
                        ?>
                                <tr class="checklist-row" style="cursor: pointer; <?= $rowBgStyle ?>"
                                    data-id="<?= $row['id']; ?>"
                                    data-doc-no="<?= $row['receiveNoti_No']; ?>"
                                    data-report-no="<?= $row['receiveNotiReportNo']; ?>"
                                    data-complaints-type="<?= $row['complaints_type']; ?>"
                                    data-status-checklist="<?= $row['statusChecklist'] ?? '0'; ?>" data-create-by="<?= $row['create_by'] ?? ''; ?>">
                                    <td class="text-center"><?= $index++; ?></td>
                                    <td class="text-center"><?= htmlspecialchars(!empty($row['receiveNoti_No_TH']) ? $row['receiveNoti_No_TH'] : convertDocNoToThai($row['receiveNoti_No'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-center"><?= htmlspecialchars(!empty($row['receiveNotiReportNo_TH']) ? $row['receiveNotiReportNo_TH'] : convertReportNoToThai($row['receiveNotiReportNo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="white-space: nowrap;"><?= $row['complaints_From']; ?></td>
                                    <td class="text-center"><?= $row['province']; ?></td>
                                    <td><?= $row['complaintstype']; ?></td>
                                    <td><?= $row['complaintsdevice']; ?></td>
                                    <td><?= $row['fullname_create']; ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($row['statusChecklist']) && $row['statusChecklist'] == 1): ?>
                                            <button type="button" class="btn btn-sm btn-draft-report btn-download-pdf" style="background:#7c3aed; box-shadow:0 2px 6px rgba(124,58,237,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-pdf-id="<?= $row['id']; ?>" title="ดาวน์โหลดรายการตรวจสอบ">
                                                <i class="fas fa-file-pdf me-1"></i>รายงาน Checklist
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-secondary btn-no-action" style="background:#9ca3af; box-shadow:none; color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; cursor:not-allowed; opacity:.55;" disabled title="ยังไม่มีข้อมูล">
                                                <i class="fas fa-file-pdf me-1"></i>รายงาน Checklist
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($row['statusChecklist']) && $row['statusChecklist'] == 1): ?>
                                            <button type="button" class="btn btn-sm btn-report-pdf" style="background:#0d9488; box-shadow:0 2px 6px rgba(13,148,136,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-pdf-id="<?= $row['id']; ?>" data-pdf-type="<?= $row['complaints_type']; ?>" title="ดาวน์โหลด F-CS-11">
                                                <i class="fas fa-file-pdf me-1"></i>F-CS-11
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-secondary btn-no-action" style="background:#9ca3af; box-shadow:none; color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; cursor:not-allowed; opacity:.55;" disabled title="ยังไม่มีข้อมูล">
                                                <i class="fas fa-file-pdf me-1"></i>F-CS-11
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($row['statusChecklist']) && $row['statusChecklist'] == 1): ?>
                                            <button type="button" class="btn btn-sm btn-qr" style="background:#4f46e5; box-shadow:0 2px 6px rgba(79,70,229,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" onclick="openQRCodeModal(<?= $id ?>)">
                                                <i class="fa-solid fa-qrcode me-1"></i>วัตถุพยาน
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-no-action" style="background:#9ca3af; box-shadow:none; color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; cursor:not-allowed; opacity:.55;" disabled title="ยังไม่มีข้อมูล">
                                                <i class="fa-solid fa-qrcode me-1"></i>วัตถุพยาน
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                        <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="row col-md-12 d-flex justify-content-center mt-3">
            <nav aria-label="Page navigation mt-3">
                <ul id="pagination-list" class="pagination justify-content-center"></ul>
            </nav>
        </div>
    </div>
</div>

<?php
// เตรียมข้อมูลรายชื่อผู้ตรวจ (ดึงครั้งเดียว เก็บใส่ตัวแปรไว้ใช้ซ้ำ)
$inspectorOptions = '<option value="" selected disabled>กรุณาเลือก</option>';
$qryInspector = "SELECT t1.user_id, CONCAT(t2.rank_name,' ',t1.first_name,' ',t1.last_name) AS fullname 
                 FROM user_profile t1 
                 LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id 
                 ORDER BY t1.user_id DESC";

if (isset($pdo)) {
    $stmt = $pdo->query($qryInspector);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // เก็บ HTML Option ไว้ใน string
        $inspectorOptions .= '<option value="' . $row['user_id'] . '">' . $row['fullname'] . '</option>';
    }
}
?>

<div id="customLightbox" class="lightbox-overlay d-none">

    <div class="lightbox-header">
        <h5 class="text-white text-truncate m-0" id="lightboxFileName" style="max-width: 80%;">
        </h5>
        <button type="button" class="btn btn-link text-white p-0" onclick="closeLightbox()">
            <i class="fas fa-times fa-2x"></i>
        </button>
    </div>

    <div class="lightbox-content">
        <img src="" id="lightboxImage" class="img-fluid rounded shadow-lg">
    </div>

</div>

<?php
$content = ob_get_clean();
ob_start();
?>

<!-- Include Modal สำหรับคดีทรัพย์ -->
<?php include './modals/modal_property.php'; ?>
<?php include './modals/modal_property_pdf_form.php'; /* Updated: 2026-05-19 v2 */ ?>

<!-- Include Modal สำหรับเรื่องชีวิต -->
<?php include './modals/modal_life.php'; /* Updated: 2026-05-19 23:06 - Changed checkbox to text input */ ?>
<?php include './modals/modal_life_pdf_form.php'; ?>
<!-- Include Modal สำหรับเรื่องระเบิด -->
<?php include './modals/modal_bomb.php'; /* Updated: 2026-02-26 */ ?>
<?php include './modals/modal_bomb_pdf_form.php'; /* Updated: 2026-05-26 10:11 - Pen size slider fix */ ?>
<!-- Include Modal สำหรับเรื่องเพลิงไหม้ -->
<?php include './modals/modal_fire_new.php'; ?>
<?php include './modals/modal_fire_pdf_form.php'; ?>
<!-- Include Modal สำหรับเรื่องจราจร -->
<?php include './modals/modal_traffic.php'; ?>
<?php include './modals/modal_traffic_pdf_form.php'; ?>
<!-- Include Modal สำหรับเรื่องวัตถุพยาน -->
<?php include './modals/modal_evidence.php'; /* Updated: 2026-03-10 */ ?>
<?php include './modals/modal_evidence_pdf_form.php'; /* Created: 2026-04-02 */ ?>
<!-- Include Modal สำหรับตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง) -->
<?php include './modals/modal_fingerprint_evidence_new.php'; ?>
<?php include './modals/modal_fingerprint_evidence.php'; /* Created: 2026-03-12 */ ?>
<!-- Include Modal สำหรับตรวจเก็บวัตถุพยานที่เกิดเหตุ (type 07) -->
<?php include './modals/modal_scene_evidence.php'; ?>
<?php include './modals/modal_scene_evidence_pdf_form.php'; ?>
<!-- Include Modal สำหรับตรวจเก็บวัตถุพยานที่บุคคล (type 08) -->
<?php include './modals/modal_person_evidence.php'; ?>
<?php include './modals/modal_person_evidence_pdf_form.php'; ?>
<!-- Include Modal สำหรับ F-CS-11 (แบบการตรวจเก็บและส่งมอบวัตถุพยาน) -->
<?php include './modals/modal_fcs11_pdf_form.php'; ?>

<script src="js/sketch-tools.js?v=10"></script>
<script src="js/report_no_th.js"></script>

<script>
    // ---------------------------------------------------------
    // 1. GLOBAL VARIABLES & CONSTANTS (ประกาศตัวแปรหลัก)
    // ---------------------------------------------------------
    function thaiDocNo(s) {
        return (window.toThaiDocNo ? window.toThaiDocNo(String(s || '')) : String(s || '')).trim();
    }
    function thaiReportNo(s) {
        return (window.toThaiReportNo ? window.toThaiReportNo(String(s || '')) : String(s || '')).trim();
    }
    const inspectorOptionsHTML = `<?php echo $inspectorOptions; ?>`;
    // inspectorOptionsHTMLBomb ประกาศใน modal_bomb.php แล้ว (ไม่ต้องประกาศซ้ำ)
    const inspectorOptionsHTMLLife = `<?php echo $inspectorOptionsLife; ?>`;
    const inspectorOptionsHTMLEV7 = `<?php echo $inspectorOptionsEV7; ?>`;

    const MAX_FILE_SIZE_MB = 10;

    // ★ Session user_id สำหรับเช็คสิทธิ์ ปุ่ม save ใน modal
    window._sessionUserId = '<?= $_SESSION["user_id"] ?>';

    var signaturePads = window.signaturePads;
    var _sketchEraser = window._sketchEraser;
    var _sketchColor = window._sketchColor;
    var _sketchOrigColor = window._sketchOrigColor;
    let attachmentStore = [];
    let deletedExistingPhotos = []; // เก็บ filename ของรูปเก่าที่ต้องการลบ (ทรัพย์)
    let existingPhotosStore = []; // เก็บข้อมูลรูปเดิมจาก DB (base64 + filename) สำหรับ preview

    // ★ Helper: คัดลอกภาพ canvas ข้ามฟอร์มที่มีขนาดต่างกัน (fit-contain + จัดกลาง)
    function transferCanvasImage(srcCanvasId, dstCanvasId) {
        var src = document.getElementById(srcCanvasId);
        var dst = document.getElementById(dstCanvasId);
        if (!src || !dst) return;

        var srcPad = signaturePads[srcCanvasId];
        if (srcPad && srcPad.isEmpty()) return;
        if (!src.width || !src.height) return;

        var dataUrl;
        try {
            dataUrl = src.toDataURL('image/png');
        } catch (e) {
            return;
        }

        var dstPad = signaturePads[dstCanvasId];
        if (dstPad) dstPad.clear();

        var dstCssW = dst.offsetWidth || dst.clientWidth || 300;
        var dstCssH = dst.offsetHeight || dst.clientHeight || 150;

        var img = new Image();
        img.onload = function() {
            // สร้าง offscreen canvas ขนาดเท่า CSS ของปลายทาง
            var off = document.createElement('canvas');
            off.width = dstCssW;
            off.height = dstCssH;
            var offCtx = off.getContext('2d');

            // fit-contain: scale รักษาสัดส่วน + จัดกลาง
            var scale = Math.min(dstCssW / img.naturalWidth, dstCssH / img.naturalHeight);
            var drawW = img.naturalWidth * scale;
            var drawH = img.naturalHeight * scale;
            var offsetX = (dstCssW - drawW) / 2;
            var offsetY = (dstCssH - drawH) / 2;

            offCtx.drawImage(img, offsetX, offsetY, drawW, drawH);
            var scaledUrl = off.toDataURL('image/png');

            if (dstPad) {
                dstPad.fromDataURL(scaledUrl, {
                    ratio: 1,
                    width: dstCssW,
                    height: dstCssH
                });
            } else {
                var ctx = dst.getContext('2d');
                ctx.save();
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.clearRect(0, 0, dst.width, dst.height);
                ctx.drawImage(off, 0, 0, dst.width, dst.height);
                ctx.restore();
            }
        };
        img.src = dataUrl;
    }

    // ★ Helper: โหลดภาพจาก URL ลง canvas (fit-contain + จัดกลาง)
    function loadImageToCanvas(canvasId, imgUrl, hiddenInputId, fileIdValue) {
        if (hiddenInputId) {
            $('#' + hiddenInputId).val('existing_file_id:' + fileIdValue);
        }
        setTimeout(function() {
            var cvs = document.getElementById(canvasId);
            if (!cvs) return;
            var pad = signaturePads[canvasId];
            if (pad) pad.clear();

            var cssW = cvs.offsetWidth || cvs.clientWidth || 300;
            var cssH = cvs.offsetHeight || cvs.clientHeight || 150;

            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function() {
                var off = document.createElement('canvas');
                off.width = cssW;
                off.height = cssH;
                var offCtx = off.getContext('2d');

                var scale = Math.min(cssW / img.naturalWidth, cssH / img.naturalHeight);
                var drawW = img.naturalWidth * scale;
                var drawH = img.naturalHeight * scale;
                var offsetX = (cssW - drawW) / 2;
                var offsetY = (cssH - drawH) / 2;

                offCtx.drawImage(img, offsetX, offsetY, drawW, drawH);
                var scaledUrl = off.toDataURL('image/png');

                if (pad) {
                    pad.fromDataURL(scaledUrl, {
                        ratio: 1,
                        width: cssW,
                        height: cssH
                    });
                } else {
                    var ctx = cvs.getContext('2d');
                    ctx.save();
                    ctx.setTransform(1, 0, 0, 1, 0, 0);
                    ctx.clearRect(0, 0, cvs.width, cvs.height);
                    ctx.drawImage(off, 0, 0, cvs.width, cvs.height);
                    ctx.restore();
                }
            };
            img.src = imgUrl;
        }, 600);
    }

    function initializeSignaturePadCanvases(canvasIds) {
        canvasIds.forEach(function(canvasId) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            var existingData = null;
            if (signaturePads[canvasId] && !signaturePads[canvasId].isEmpty()) {
                existingData = signaturePads[canvasId].toDataURL();
            }

            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            if (canvas.offsetWidth > 0) {
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);
            }

            if (signaturePads[canvasId]) {
                if (existingData) {
                    signaturePads[canvasId].fromDataURL(existingData);
                }
                return;
            }

            const pad = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)',
                penColor: 'rgb(0, 0, 0)',
                minWidth: 0.3,
                maxWidth: 1.2,
            });

            signaturePads[canvasId] = pad;
        });
    }

    let inputPhotos;
    let dropzone;

    // Fire Photo Variables (ประกาศที่ script scope เพื่อให้ฟังก์ชันนอก document.ready เข้าถึงได้)
    let attachmentStoreFire = [];
    let deletedExistingPhotosFire = []; // เก็บ file_id ของรูปเก่าที่ต้องการลบ

    // Fingerprint Photo Variables (shared between standard & PDF fingerprint modals)
    let attachmentStoreFP = [];
    let deletedExistingPhotosFP = [];

    // Bomb Photo Variables (shared between standard & PDF bomb modals)
    var attachmentStoreBomb = window.attachmentStoreBomb || [];
    window.attachmentStoreBomb = attachmentStoreBomb;
    var deletedExistingPhotosBomb = window.deletedExistingPhotosBomb || [];
    window.deletedExistingPhotosBomb = deletedExistingPhotosBomb;

    // Helper: แปลง canvas เป็น Blob (Promise-based)
    function canvasToBlob(canvas, mimeType) {
        return new Promise(function(resolve) {
            canvas.toBlob(function(blob) {
                resolve(blob);
            }, mimeType || 'image/png');
        });
    }

    // Helper: แปลง data URL (base64) เป็น Blob
    function dataURLtoBlob(dataUrl) {
        return new Promise(function(resolve) {
            if (!dataUrl || typeof dataUrl !== 'string' || dataUrl.indexOf('data:') !== 0) {
                resolve(null);
                return;
            }
            try {
                var parts = dataUrl.split(',');
                if (parts.length < 2) {
                    resolve(null);
                    return;
                }
                var mimeMatch = parts[0].match(/:(.*?);/);
                var mime = mimeMatch ? mimeMatch[1] : 'image/png';
                var binary = atob(parts[1]);
                var len = binary.length;
                var bytes = new Uint8Array(len);
                for (var i = 0; i < len; i++) {
                    bytes[i] = binary.charCodeAt(i);
                }
                resolve(new Blob([bytes], { type: mime }));
            } catch (e) {
                resolve(null);
            }
        });
    }

    // ตัวแปรสำหรับรอโหลดข้อมูล fire หลัง modal shown เพื่อป้องกัน race condition
    let pendingFireLoadId = null;
    let pendingFireLoadMode = null; // 'edit' หรือ 'prefill'

    // ตัวแปรสำหรับรอโหลดข้อมูล traffic หลัง modal shown
    let pendingTrafficLoadId = null;
    let pendingTrafficLoadMode = null; // 'edit' หรือ 'prefill'

    // ตัวแปรสำหรับรอโหลดข้อมูล bomb หลัง modal shown
    let pendingBombLoadId = null;
    let pendingBombLoadMode = null; // 'edit' หรือ 'prefill'

    // ตัวแปรสำหรับรอโหลดข้อมูล fingerprint หลัง modal shown
    let pendingFPLoadId = null;
    let pendingFPLoadMode = null; // 'edit' หรือ 'prefill'

    // ตัวแปรสำหรับรอโหลดข้อมูล scene evidence (type 07) หลัง modal shown
    let pendingEV7LoadId = null;
    let pendingEV7LoadMode = null; // 'edit' หรือ 'prefill'

    // ตัวแปรสำหรับรอโหลดข้อมูล person evidence (type 08) หลัง modal shown
    let pendingEV8LoadId = null;
    let pendingEV8LoadMode = null; // 'edit' หรือ 'prefill'

    // ตัวแปรสำหรับรอโหลดข้อมูล property (PDF) หลัง modal shown
    let pendingPropertyLoadId = null;
    let pendingPropertyLoadMode = null; // 'edit' หรือ 'prefill'

    // ตัวแปรสำหรับรอโหลดข้อมูล life (PDF) หลัง modal shown
    let pendingLifeLoadId = null;
    let pendingLifeLoadMode = null; // 'edit' หรือ 'prefill'

    // ===== ฟังก์ชัน sync ข้อมูลระหว่างฟอร์มเพลิงไหม้ (มาตรฐาน ↔ PDF) =====
    function syncFireFormData(fromFormId, toFormId) {
        const fromForm = document.getElementById(fromFormId);
        const toForm = document.getElementById(toFormId);
        if (!fromForm || !toForm) return;

        // รวบรวม input/select/textarea จากฟอร์มต้นทางตาม name
        const fromElements = fromForm.querySelectorAll('input, select, textarea');
        const dataMap = {};

        fromElements.forEach(el => {
            const name = el.name;
            if (!name) return;

            if (el.type === 'checkbox') {
                if (!dataMap[name]) dataMap[name] = {
                    type: 'checkbox',
                    values: []
                };
                if (el.checked) dataMap[name].values.push(el.value);
            } else if (el.type === 'radio') {
                if (el.checked) dataMap[name] = {
                    type: 'radio',
                    value: el.value
                };
            } else if (el.type === 'file') {
                // ข้ามไฟล์ — ไม่สามารถ copy ได้
                return;
            } else {
                // text, date, time, hidden, textarea, select
                if (!dataMap[name]) dataMap[name] = {
                    type: 'value',
                    values: []
                };
                dataMap[name].values.push(el.value);
            }
        });

        // ใส่ข้อมูลลงฟอร์มปลายทาง
        Object.keys(dataMap).forEach(name => {
            const info = dataMap[name];
            const toEls = toForm.querySelectorAll('[name="' + name + '"]');
            if (toEls.length === 0) return;

            if (info.type === 'checkbox') {
                toEls.forEach(el => {
                    el.checked = info.values.includes(el.value);
                });
            } else if (info.type === 'radio') {
                toEls.forEach(el => {
                    el.checked = (el.value === info.value);
                });
            } else {
                // สำหรับ array fields (เช่น name="fire_person_name[]") — map ตาม index
                toEls.forEach((el, idx) => {
                    if (idx < info.values.length) {
                        el.value = info.values[idx];
                    }
                });
            }
        });
    }

    // ===== sync จำนวน row ทุก dynamic section ก่อน sync ข้อมูล =====
    // direction: 'toPdf' = มาตรฐาน→PDF, 'toStd' = PDF→มาตรฐาน
    function syncAllDynamicRows(direction) {
        if (direction === 'toPdf') {
            // === 1. Inspector rows ===
            _syncInspectorToPdf();
            // === 2. Victim rows ===
            _syncVictimToPdf();
            // === 3. Evidence rows ===
            _syncEvidenceToPdf();
            // === 4. Collection/Measurement rows ===
            _syncCollectionToPdf();
        } else {
            // === 1. Inspector rows ===
            _syncInspectorToStd();
            // === 2. Victim rows ===
            _syncVictimToStd();
            // === 3. Evidence rows ===
            _syncEvidenceToStd();
            // === 4. Collection/Measurement rows ===
            _syncCollectionToStd();
        }
    }

    // ---------- Inspector: มาตรฐาน → PDF ----------
    function _syncInspectorToPdf() {
        const stdForm = document.getElementById('incidentCheckListFormFire');
        if (!stdForm) return;
        const srcSelects = stdForm.querySelectorAll('[name="fire_inspector_id[]"]');
        const container = document.getElementById('fpf_inspector_container');
        if (!container) return;

        const existingSel = container.querySelector('select');
        const optionsHtml = existingSel ? existingSel.innerHTML : (srcSelects[0] ? srcSelects[0].innerHTML : '');
        container.innerHTML = '';

        const count = Math.max(srcSelects.length, 1);
        for (let i = 0; i < count; i++) {
            const row = document.createElement('div');
            row.className = 'fpf-si fpf-inspector-row';
            row.innerHTML = '<span class="fpf-si-no">5.' + (i + 1) + '</span>' +
                '<select class="fpf-sel" name="fire_inspector_id[]">' + optionsHtml + '</select>' +
                (i > 0 ? ' <button type="button" class="fpf-del-btn" onclick="this.parentElement.remove()">×</button>' : '');
            container.appendChild(row);
        }
        if (typeof fpfInspectorIdx !== 'undefined') fpfInspectorIdx = count;
    }

    // ---------- Inspector: PDF → มาตรฐาน ----------
    function _syncInspectorToStd() {
        const pdfForm = document.getElementById('fireFormPdf');
        if (!pdfForm) return;
        const srcSelects = pdfForm.querySelectorAll('[name="fire_inspector_id[]"]');
        const container = document.getElementById('inspector_container_fire');
        if (!container) return;

        const existingCount = container.querySelectorAll('[name="fire_inspector_id[]"]').length;
        const needed = Math.max(srcSelects.length, 1);
        for (let i = existingCount; i < needed; i++) {
            $('#btn_add_inspector_fire').trigger('click');
        }
    }

    // ---------- Victim: มาตรฐาน → PDF ----------
    function _syncVictimToPdf() {
        const stdForm = document.getElementById('incidentCheckListFormFire');
        if (!stdForm) return;
        const srcCount = stdForm.querySelectorAll('[name="fire_person_type[]"]').length;
        const container = document.getElementById('fpf_victim_container');
        if (!container) return;

        // ลบ row เก่าทั้งหมดใน PDF victim container
        container.innerHTML = '';

        const count = Math.max(srcCount, 1);
        for (let i = 0; i < count; i++) {
            const row = document.createElement('div');
            row.className = 'fpf-victim-row';
            row.style.cssText = 'margin-top:3px; padding:2px 0;' + (i > 0 ? ' border-top:1px dotted #ccc;' : '');
            row.innerHTML =
                '<div class="fpf-fr">' +
                '<span class="fpf-fl">ประเภท</span>' +
                '<select class="fpf-sel" name="fire_person_type[]" style="max-width:90px;">' +
                '<option value="">--เลือก--</option><option value="ผู้เสียหาย">ผู้เสียหาย</option>' +
                '<option value="ผู้บาดเจ็บ">ผู้บาดเจ็บ</option><option value="ผู้เสียชีวิต">ผู้เสียชีวิต</option></select>' +
                '<span class="fpf-fl" style="margin-left:6px;">ชื่อ</span>' +
                '<input type="text" class="fpf-inp" name="fire_person_name[]">' +
                '<span class="fpf-fl" style="margin-left:4px;">อายุ</span>' +
                '<input type="text" class="fpf-inp-s" name="fire_person_age[]" style="max-width:30px;">' +
                '<span class="fpf-fl">ปี</span>' +
                (i > 0 ? ' <button type="button" class="fpf-del-btn" onclick="this.closest(\'.fpf-victim-row\').remove()">×</button>' : '') +
                '</div>';
            container.appendChild(row);
        }
    }

    // ---------- Victim: PDF → มาตรฐาน ----------
    function _syncVictimToStd() {
        const pdfForm = document.getElementById('fireFormPdf');
        const stdForm = document.getElementById('incidentCheckListFormFire');
        if (!pdfForm || !stdForm) return;
        const srcCount = pdfForm.querySelectorAll('[name="fire_person_type[]"]').length;
        const container = document.getElementById('fire_person_container');
        if (!container) return;

        const existingCount = container.querySelectorAll('[name="fire_person_type[]"]').length;
        const needed = Math.max(srcCount, 1);
        for (let i = existingCount; i < needed; i++) {
            if (typeof addVictimCardFire === 'function') addVictimCardFire();
        }
    }

    // ---------- Evidence: มาตรฐาน → PDF ----------
    function _syncEvidenceToPdf() {
        const $stdCards = $('#evidence_container_fire .evidence-card-fire');
        const tbody = document.getElementById('fpf_evidence_tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!$stdCards.length) return;

        $stdCards.each(function(idx, card) {
            const $card = $(card);
            const item = $card.find('input[name="evidence_item_fire[]"]').val() || '';
            const azimuth = $card.find('input[name="evidence_azimuth_fire[]"]').val() || '';
            const remark = $card.find('input[name="evidence_remark_fire[]"]').val() || '';
            const labUnit = window.getLabUnitsString($card.find('[name="evidence_lab_unit_fire[]"]'));
            const lvl1 = $card.find('input[name^="evidence_level_1_fire_"]').val() || '';
            const lvl2 = $card.find('input[name^="evidence_level_2_fire_"]').val() || '';
            const lvl3 = $card.find('input[name^="evidence_level_3_fire_"]').val() || '';
            const lvl4 = $card.find('input[name^="evidence_level_4_fire_"]').val() || '';

            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td style="width:40px; text-align:center; font-weight:600;">' + (idx + 1) + '<input type="hidden" name="evidence_label_fire[]" value="' + (idx + 1) + '"></td>' +
                '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_item_fire[]" value="' + item.replace(/"/g, '&quot;') + '" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
                '<td><input type="text" name="evidence_level_1_fire_' + idx + '" value="' + String(lvl1).replace(/"/g, '&quot;') + '" style="width:50px; text-align:center;" inputmode="decimal"></td>' +
                '<td><input type="text" name="evidence_level_2_fire_' + idx + '" value="' + String(lvl2).replace(/"/g, '&quot;') + '" style="width:50px; text-align:center;" inputmode="decimal"></td>' +
                '<td><input type="text" name="evidence_level_3_fire_' + idx + '" value="' + String(lvl3).replace(/"/g, '&quot;') + '" style="width:50px; text-align:center;" inputmode="decimal"></td>' +
                '<td><input type="text" name="evidence_level_4_fire_' + idx + '" value="' + String(lvl4).replace(/"/g, '&quot;') + '" style="width:50px; text-align:center;" inputmode="decimal"></td>' +
                '<td><input type="text" name="evidence_azimuth_fire[]" value="' + azimuth.replace(/"/g, '&quot;') + '"></td>' +
                '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_remark_fire[]" value="' + remark.replace(/"/g, '&quot;') + '" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
                '<td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option></select><input type="hidden" class="lab-unit-value" name="evidence_lab_unit_fire[]" value=""></td>' +
                '<td><button type="button" class="fpf-del-btn" onclick="fpfDelRow(this)">×</button></td>';
            tbody.appendChild(tr);
            window.setLabUnits($(tr).find('[name="evidence_lab_unit_fire[]"]'), labUnit);
        });
        if (typeof window.fpfRenumberEvidence === 'function') {
            window.fpfRenumberEvidence();
        }
    }

    // ---------- Evidence: PDF → มาตรฐาน ----------
    function _syncEvidenceToStd() {
        const tbody = document.getElementById('fpf_evidence_tbody');
        if (!tbody) return;
        const rows = tbody.querySelectorAll('tr');
        const container = document.getElementById('evidence_container_fire');
        if (!container) return;
        container.innerHTML = '';

        const count = Math.max(rows.length, 1);
        for (let i = 0; i < count; i++) {
            const row = (i < rows.length) ? rows[i] : null;
            const item = row ? (row.querySelector('input[name="evidence_item_fire[]"]') || {}).value || '' : '';
            const azimuth = row ? (row.querySelector('input[name="evidence_azimuth_fire[]"]') || {}).value || '' : '';
            const remark = row ? (row.querySelector('input[name="evidence_remark_fire[]"]') || {}).value || '' : '';
            const labUnit = row ? window.getLabUnitsString(row.querySelector('[name="evidence_lab_unit_fire[]"]')) : '';
            const lv1 = row ? ((row.querySelector('input[name^="evidence_level_1_fire_"]') || {}).value || '') : '';
            const lv2 = row ? ((row.querySelector('input[name^="evidence_level_2_fire_"]') || {}).value || '') : '';
            const lv3 = row ? ((row.querySelector('input[name^="evidence_level_3_fire_"]') || {}).value || '') : '';
            const lv4 = row ? ((row.querySelector('input[name^="evidence_level_4_fire_"]') || {}).value || '') : '';

            if (typeof addEvidenceRowFire === 'function') {
                addEvidenceRowFire();
            }
            const $card = $('#evidence_container_fire .evidence-card-fire').last();
            $card.find('[name="evidence_item_fire[]"]').val(item);
            $card.find('[name="evidence_azimuth_fire[]"]').val(azimuth);
            $card.find('[name="evidence_remark_fire[]"]').val(remark);
            window.setLabUnits($card.find('[name="evidence_lab_unit_fire[]"]'), labUnit);
            $card.find('input[name^="evidence_level_1_fire_"]').val(lv1);
            $card.find('input[name^="evidence_level_2_fire_"]').val(lv2);
            $card.find('input[name^="evidence_level_3_fire_"]').val(lv3);
            $card.find('input[name^="evidence_level_4_fire_"]').val(lv4);
        }

        if (typeof reIndexEvidenceCardsFire === 'function') {
            reIndexEvidenceCardsFire();
        }
    }

    // ---------- Collection/Measurement: Fallback (disabled — evidence & collection are independent) ----------
    function _syncFireEvidenceToCollectionFallback() {
        // Evidence table กับ Collection table เป็นคนละส่วนกัน ไม่ cross-sync
    }

    // ---------- Collection/Measurement: มาตรฐาน → PDF ----------
    function _syncCollectionToPdf() {
        const stdForm = document.getElementById('incidentCheckListFormFire');
        if (!stdForm) return;
        const srcCount = stdForm.querySelectorAll('[name="measurement_item_fire[]"]').length;
        const tbody = document.getElementById('fpf_collection_tbody');
        if (!tbody) return;

        const existingCount = tbody.rows.length;
        const needed = Math.max(srcCount, 1);
        for (let i = existingCount; i < needed; i++) {
            if (typeof fpfAddCollectionRow === 'function') fpfAddCollectionRow();
        }
    }

    // ---------- Collection/Measurement: PDF → มาตรฐาน ----------
    function _syncCollectionToStd() {
        const pdfForm = document.getElementById('fireFormPdf');
        const stdForm = document.getElementById('incidentCheckListFormFire');
        if (!pdfForm || !stdForm) return;
        const srcCount = pdfForm.querySelectorAll('[name="measurement_item_fire[]"]').length;
        const container = document.getElementById('measurement_container_fire');
        if (!container) return;

        const existingCount = container.querySelectorAll('[name="measurement_item_fire[]"]').length;
        const needed = Math.max(srcCount, 1);
        for (let i = existingCount; i < needed; i++) {
            if (typeof addMeasurementCardFire === 'function') addMeasurementCardFire();
        }
    }

    // =============================================================
    //  FINGERPRINT: สลับระหว่าง modal มาตรฐาน ↔ PDF
    // =============================================================

    /**
     * Sync form data between the two fingerprint modals.
     * Names differ only by prefix: fp_ (PDF) vs fpn_ (standard/new).
     */
    function syncFingerprintFormData(fromFormId, toFormId) {
        const fromForm = document.getElementById(fromFormId);
        const toForm = document.getElementById(toFormId);
        if (!fromForm || !toForm) return;

        // Determine direction
        const toNewForm = (toFormId === 'incidentCheckListFormFingerprintNew');

        function mapName(name) {
            if (toNewForm) {
                return name.replace(/^fp_/, 'fpn_');
            } else {
                return name.replace(/^fpn_/, 'fp_');
            }
        }

        const fromElements = fromForm.querySelectorAll('input, select, textarea');
        const dataMap = {};

        fromElements.forEach(function(el) {
            const name = el.name;
            if (!name) return;
            if (el.type === 'file') return;

            if (el.type === 'checkbox') {
                if (!dataMap[name]) dataMap[name] = {
                    type: 'checkbox',
                    values: []
                };
                if (el.checked) dataMap[name].values.push(el.value);
            } else if (el.type === 'radio') {
                if (el.checked) dataMap[name] = {
                    type: 'radio',
                    value: el.value
                };
            } else {
                if (!dataMap[name]) dataMap[name] = {
                    type: 'value',
                    values: []
                };
                dataMap[name].values.push(el.value);
            }
        });

        Object.keys(dataMap).forEach(function(name) {
            const info = dataMap[name];
            const targetName = mapName(name);

            // Try mapped name first, then original (for shared names like receiveNoti_id)
            let toEls = toForm.querySelectorAll('[name="' + targetName + '"]');
            if (toEls.length === 0) toEls = toForm.querySelectorAll('[name="' + name + '"]');
            if (toEls.length === 0) return;

            if (info.type === 'checkbox') {
                toEls.forEach(function(el) {
                    el.checked = info.values.includes(el.value);
                    // Trigger change for radio-like toggles
                    el.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                });
            } else if (info.type === 'radio') {
                toEls.forEach(function(el) {
                    el.checked = (el.value === info.value);
                });
            } else {
                toEls.forEach(function(el, idx) {
                    if (idx < info.values.length) el.value = info.values[idx];
                });
            }
        });
    }

    /**
     * Sync dynamic row counts between fingerprint modals.
     * direction: 'toPdf' = standard→PDF, 'toStd' = PDF→standard
     */
    function syncFingerprintDynamicRows(direction) {
        if (direction === 'toPdf') {
            // Inspector rows: standard → PDF
            const stdForm = document.getElementById('incidentCheckListFormFingerprintNew');
            if (!stdForm) return;
            const srcInsp = stdForm.querySelectorAll('[name="fpn_inspector_id[]"]');
            const pdfContainer = document.getElementById('addCheckListModalFingerprint');
            if (pdfContainer) {
                const pdfInsp = pdfContainer.querySelectorAll('[name="fp_inspector_id[]"]');
                const needed = Math.max(srcInsp.length, 1) - pdfInsp.length;
                for (let i = 0; i < needed; i++) {
                    const addBtn = pdfContainer.querySelector('#btn_add_inspector_fp, .fp-add-inspector');
                    if (addBtn) addBtn.click();
                }
            }

            // Evidence cards: sync count
            const srcEvCards = stdForm.querySelectorAll('.fpn-evidence-card');
            const pdfEvContainer = document.querySelector('#addCheckListModalFingerprint .fp-evidence-container');
            if (pdfEvContainer) {
                const pdfCards = pdfEvContainer.querySelectorAll('.fp-evidence-card');
                const needed = Math.max(srcEvCards.length, 1) - pdfCards.length;
                for (let i = 0; i < needed; i++) {
                    const addBtn = pdfEvContainer.closest('.fpf-page, fieldset, .fp-section6-set')
                        ?.querySelector('.fp-btn-add-evidence, .btn-add-evidence');
                    if (addBtn) addBtn.click();
                }
            }

            // Section 6 sets
            const srcSets = stdForm.querySelectorAll('.fpn-section6-set');
            const pdfSets = document.querySelectorAll('#addCheckListModalFingerprint .fp-section6-set');
            const setsNeeded = Math.max(srcSets.length, 1) - pdfSets.length;
            for (let i = 0; i < setsNeeded; i++) {
                const addSetBtn = document.querySelector('#btn_add_section6_fp');
                if (addSetBtn) addSetBtn.click();
            }
        } else {
            // Inspector rows: PDF → standard
            const pdfForm = document.getElementById('incidentCheckListFormFingerprint');
            if (!pdfForm) return;
            const srcInsp = pdfForm.querySelectorAll('[name="fp_inspector_id[]"]');
            const stdContainer = document.getElementById('fpn_inspector_container');
            if (stdContainer) {
                const stdInsp = stdContainer.querySelectorAll('[name="fpn_inspector_id[]"]');
                const needed = Math.max(srcInsp.length, 1) - stdInsp.length;
                for (let i = 0; i < needed; i++) {
                    const addBtn = document.getElementById('btn_add_inspector_fpn');
                    if (addBtn) addBtn.click();
                }
            }

            // Evidence cards
            const srcEvCards = pdfForm.querySelectorAll('.fp-evidence-card');
            const stdEvContainer = document.querySelector('#addCheckListModalFingerprintNew .fpn-evidence-container');
            if (stdEvContainer) {
                const stdCards = stdEvContainer.querySelectorAll('.fpn-evidence-card');
                const needed = Math.max(srcEvCards.length, 1) - stdCards.length;
                for (let i = 0; i < needed; i++) {
                    const addBtn = document.querySelector('#addCheckListModalFingerprintNew .fpn-btn-add-evidence');
                    if (addBtn) addBtn.click();
                }
            }

            // Section 6 sets
            const srcSets = pdfForm.querySelectorAll('.fp-section6-set');
            const stdSets = document.querySelectorAll('#fpn_section6_wrapper .fpn-section6-set');
            const setsNeeded = Math.max(srcSets.length, 1) - stdSets.length;
            for (let i = 0; i < setsNeeded; i++) {
                const addSetBtn = document.getElementById('btn_add_section6_fpn');
                if (addSetBtn) addSetBtn.click();
            }
        }
    }

    // ===== สลับ: ฟอร์มมาตรฐาน → PDF (ลายนิ้วมือแฝง) =====
    function switchToFingerprintPdfForm() {
        // sync dynamic rows
        syncFingerprintDynamicRows('toPdf');

        // sync form data standard → PDF
        syncFingerprintFormData('incidentCheckListFormFingerprintNew', 'incidentCheckListFormFingerprint');

        // sync hidden/display fields
        var docNo = $('#doc_no_fpn').val() || '';
        var rptNo = $('#report_no_fpn').val() || '';
        $('#receiveNoti_id_fp').val($('#receiveNoti_id_fpn').val());
        $('#doc_no_fp').val(docNo);
        $('#report_no_fp').val(rptNo);
        $('#receiveNoti_No_fp').text(docNo);
        $('#receiveNotiReportNo_fp').text(rptNo);
        // sync photo page mirrors
        $('#addCheckListModalFingerprint .fp-doc-no-mirror').text(docNo);

        // close standard modal
        var stdEl = document.getElementById('addCheckListModalFingerprintNew');
        var stdModal = bootstrap.Modal.getInstance(stdEl);
        if (stdModal) stdModal.hide();

        stdEl.addEventListener('hidden.bs.modal', function onHidden() {
            stdEl.removeEventListener('hidden.bs.modal', onHidden);
            var pdfEl = document.getElementById('addCheckListModalFingerprint');
            if (pdfEl) {
                pdfEl.addEventListener('shown.bs.modal', function onShown() {
                    pdfEl.removeEventListener('shown.bs.modal', onShown);
                    // Re-render photos in PDF grid from shared store
                    renderFPAttachmentGrid();
                    updateFPRealInput();
                });
                bootstrap.Modal.getOrCreateInstance(pdfEl).show();
            }
        });
    }

    // ===== สลับ: PDF → ฟอร์มมาตรฐาน (ลายนิ้วมือแฝง) =====
    function switchToFingerprintStandardForm() {
        // sync dynamic rows
        syncFingerprintDynamicRows('toStd');

        // sync form data PDF → standard
        syncFingerprintFormData('incidentCheckListFormFingerprint', 'incidentCheckListFormFingerprintNew');

        // sync hidden/display fields
        var docNo = $('#doc_no_fp').val() || '';
        var rptNo = $('#report_no_fp').val() || '';
        $('#receiveNoti_id_fpn').val($('#receiveNoti_id_fp').val());
        $('#doc_no_fpn').val(docNo);
        $('#report_no_fpn').val(rptNo);
        $('#receiveNoti_No_fpn').text(docNo);
        $('#receiveNotiReportNo_fpn').text(rptNo);

        // close PDF modal
        var pdfEl = document.getElementById('addCheckListModalFingerprint');
        var pdfModal = bootstrap.Modal.getInstance(pdfEl);
        if (pdfModal) pdfModal.hide();

        pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
            pdfEl.removeEventListener('hidden.bs.modal', onHidden);
            var stdEl = document.getElementById('addCheckListModalFingerprintNew');
            if (stdEl) {
                stdEl.addEventListener('shown.bs.modal', function onShown() {
                    stdEl.removeEventListener('shown.bs.modal', onShown);
                    // Re-render photos in standard grid from shared store
                    renderFPAttachmentGrid();
                    updateFPRealInput();
                    // ★ คง badge "จำนวนการแก้ไขเอกสาร" ให้แสดงในฟอร์มปกติ (copy จากฟอร์มเสมือน)
                    if (!$('#editInfoFP').hasClass('d-none')) {
                        $('#editCountFPN').text($('#editCountFP').text());
                        $('#editDateFPN').text($('#editDateFP').text());
                        $('#editInfoFPN').removeClass('d-none');
                    }
                });
                bootstrap.Modal.getOrCreateInstance(stdEl).show();
            }
        });
    }

    // =============================================================
    //  FIRE: สลับจากฟอร์มมาตรฐาน → PDF
    // =============================================================

    // ===== สลับจากฟอร์มมาตรฐาน → PDF =====
    function switchToFirePdfForm() {
        // sync dynamic rows ก่อน — สร้าง row ใน PDF ให้ตรงกับจำนวนของฟอร์มมาตรฐาน
        syncAllDynamicRows('toPdf');

        // sync ข้อมูลจากฟอร์มมาตรฐาน → PDF
        syncFireFormData('incidentCheckListFormFire', 'fireFormPdf');

        // ★ Fallback: ถ้า FCS-11 ว่าง ให้ดึงจาก "วัตถุพยานและตำแหน่งที่ตรวจพบ"
        _syncFireEvidenceToCollectionFallback();

        // sync ข้อมูล display (เลขที่เอกสาร/เลขรายงาน) ไปยัง PDF
        const docNo = $('#doc_no_fire').val() || '';
        const rptNo = $('#report_no_fire').val() || '';
        $('#fpf_doc_no').val(docNo);
        $('#fpf_report_no').val(rptNo);
        $('#fpf_case_doc_no').val(thaiDocNo($('#case_doc_no_fire').val() || docNo));

        // ★ Capture canvas data ก่อนปิด modal มาตรฐาน
        var canvasCopyData = {};
        var stdCanvasIds = {
            'scene_sketch': 'scene_sketch_canvas_fire',
            'receiver_signature': 'sig-canvas-receiver-fire',
            'sender_signature': 'sig-canvas-sender-fire'
        };
        var pdfCanvasIds = {
            'scene_sketch': 'fpf_scene_sketch_canvas',
            'receiver_signature': 'fpf_sig_receiver',
            'sender_signature': 'fpf_sig_sender'
        };
        Object.keys(stdCanvasIds).forEach(function(key) {
            var srcCvs = document.getElementById(stdCanvasIds[key]);
            if (srcCvs && srcCvs.width > 0 && srcCvs.height > 0) {
                try {
                    canvasCopyData[key] = srcCvs.toDataURL('image/png');
                } catch (e) {}
            }
        });

        // ปิด modal มาตรฐาน
        const stdEl = document.getElementById('addCheckListModalFire');
        const stdModal = bootstrap.Modal.getInstance(stdEl);
        if (stdModal) stdModal.hide();

        // รอให้ modal ปิดเสร็จแล้วเปิด PDF modal
        stdEl.addEventListener('hidden.bs.modal', function onHidden() {
            stdEl.removeEventListener('hidden.bs.modal', onHidden);
            const pdfEl = document.getElementById('fireFormPdfModal');
            if (pdfEl) {
                // ★ หลัง PDF modal แสดง + initCanvas เสร็จ → copy canvas + render photos
                pdfEl.addEventListener('shown.bs.modal', function onShown() {
                    pdfEl.removeEventListener('shown.bs.modal', onShown);
                    setTimeout(function() {
                        Object.keys(pdfCanvasIds).forEach(function(key) {
                            if (canvasCopyData[key]) {
                                var dst = document.getElementById(pdfCanvasIds[key]);
                                if (dst) {
                                    var img = new Image();
                                    img.onload = function() {
                                        var ctx = dst.getContext('2d');
                                        ctx.clearRect(0, 0, dst.width, dst.height);
                                        ctx.drawImage(img, 0, 0, dst.width, dst.height);
                                    };
                                    img.src = canvasCopyData[key];
                                }
                            }
                        });
                        if (typeof fpfRenderPhotosFromStore === 'function') fpfRenderPhotosFromStore();
                    }, 150);
                });
                bootstrap.Modal.getOrCreateInstance(pdfEl).show();
            }
        });
    }

    // ===== สลับจาก PDF → ฟอร์มมาตรฐาน =====
    function switchToFireStandardForm() {
        // sync dynamic rows ก่อน — สร้าง row ใน มาตรฐาน ให้ตรงกับจำนวนของ PDF
        syncAllDynamicRows('toStd');

        // sync ข้อมูลจาก PDF → ฟอร์มมาตรฐาน
        syncFireFormData('fireFormPdf', 'incidentCheckListFormFire');

        // ★ sync รหัสภาพ/จำนวนจาก store ทันที (กันค่าหาย/ไม่ตรงตอน toggle)
        renderFireAttachmentGrid();
        updateFireRealInput();

        // sync ข้อมูล display กลับไปยังฟอร์มมาตรฐาน
        const docNo = $('#fpf_doc_no').val() || '';
        const rptNo = $('#fpf_report_no').val() || '';
        $('#doc_no_fire').val(docNo);
        $('#report_no_fire').val(rptNo);
        $('#receiveNoti_No_fire').text(thaiDocNo(docNo));
        $('#receiveNotiReportNo_fire').text(thaiReportNo(rptNo));
        $('#case_doc_no_fire').val(thaiDocNo($('#fpf_case_doc_no').val() || docNo));

        // ★ Capture canvas data จาก PDF form
        var canvasCopyData = {};
        var pdfCanvasIds = {
            'scene_sketch': 'fpf_scene_sketch_canvas',
            'receiver_signature': 'fpf_sig_receiver',
            'sender_signature': 'fpf_sig_sender'
        };
        var stdCanvasIds = {
            'scene_sketch': 'scene_sketch_canvas_fire',
            'receiver_signature': 'sig-canvas-receiver-fire',
            'sender_signature': 'sig-canvas-sender-fire'
        };
        Object.keys(pdfCanvasIds).forEach(function(key) {
            var srcCvs = document.getElementById(pdfCanvasIds[key]);
            if (srcCvs && srcCvs.width > 0 && srcCvs.height > 0) {
                try {
                    canvasCopyData[key] = srcCvs.toDataURL('image/png');
                } catch (e) {}
            }
        });

        // ปิด PDF modal
        const pdfEl = document.getElementById('fireFormPdfModal');
        const pdfModal = bootstrap.Modal.getInstance(pdfEl);
        if (pdfModal) pdfModal.hide();

        // รอให้ modal ปิดเสร็จแล้วเปิดฟอร์มมาตรฐาน
        pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
            pdfEl.removeEventListener('hidden.bs.modal', onHidden);
            const stdEl = document.getElementById('addCheckListModalFire');
            if (stdEl) {
                // ★ หลัง standard modal แสดง → copy canvas + re-render photos
                stdEl.addEventListener('shown.bs.modal', function onShown() {
                    stdEl.removeEventListener('shown.bs.modal', onShown);
                    setTimeout(function() {
                        Object.keys(stdCanvasIds).forEach(function(key) {
                            if (canvasCopyData[key]) {
                                var dst = document.getElementById(stdCanvasIds[key]);
                                if (dst) {
                                    var img = new Image();
                                    img.onload = function() {
                                        var ctx = dst.getContext('2d');
                                        ctx.clearRect(0, 0, dst.width, dst.height);
                                        ctx.drawImage(img, 0, 0, dst.width, dst.height);
                                    };
                                    img.src = canvasCopyData[key];
                                }
                            }
                        });
                        renderFireAttachmentGrid();
                        updateFireRealInput();
                    }, 150);
                });
                bootstrap.Modal.getOrCreateInstance(stdEl).show();
            }
        });
    }

    // =============================================================
    //  BOMB: sync ข้อมูลระหว่างฟอร์ม (มาตรฐาน ↔ PDF)
    // =============================================================
    function syncBombFormData(fromFormId, toFormId) {
        const fromForm = document.getElementById(fromFormId);
        const toForm = document.getElementById(toFormId);
        if (!fromForm || !toForm) return;

        const fromElements = fromForm.querySelectorAll('input, select, textarea');
        const dataMap = {};

        fromElements.forEach(el => {
            const name = el.name;
            if (!name) return;

            if (el.type === 'checkbox') {
                if (!dataMap[name]) dataMap[name] = {
                    type: 'checkbox',
                    values: []
                };
                if (el.checked) dataMap[name].values.push(el.value);
            } else if (el.type === 'radio') {
                if (el.checked) dataMap[name] = {
                    type: 'radio',
                    value: el.value
                };
            } else if (el.type === 'file') {
                return;
            } else {
                if (!dataMap[name]) dataMap[name] = {
                    type: 'value',
                    values: []
                };
                dataMap[name].values.push(el.value);
            }
        });

        Object.keys(dataMap).forEach(name => {
            const info = dataMap[name];
            const toEls = Array.from(toForm.querySelectorAll('[name="' + name + '"]'));
            if (toEls.length === 0) return;

            if (info.type === 'checkbox') {
                const checkboxEls = toEls.filter(el => el.type === 'checkbox');
                if (checkboxEls.length === 0) return;
                checkboxEls.forEach(el => {
                    el.checked = info.values.includes(el.value);
                });
            } else if (info.type === 'radio') {
                const radioEls = toEls.filter(el => el.type === 'radio');
                if (radioEls.length === 0) return;
                radioEls.forEach(el => {
                    el.checked = (el.value === info.value);
                });
            } else {
                const valueEls = toEls.filter(el => el.type !== 'checkbox' && el.type !== 'radio' && el.type !== 'file');
                valueEls.forEach((el, idx) => {
                    if (idx < info.values.length) el.value = info.values[idx];
                });
            }
        });

        // ★ จัดการ field พิเศษที่ชื่อต่างกันระหว่าง PDF ↔ Standard
        _syncBombSpecialFields(fromForm, toForm);
    }

    // =============================================================
    //  BOMB: สลับจากฟอร์มมาตรฐาน → PDF
    // =============================================================
    function switchToBombPdfForm() {
        // sync ข้อมูลจากฟอร์มมาตรฐาน → PDF
        syncBombFormData('incidentCheckListFormBomb', 'bombFormPdf');

        // sync dynamic rows (inspector, victim, evidence, measurement)
        _syncBombInspectorToPdf();
        _syncBombVictimToPdf();
        _syncBombBodyToPdf();
        _syncBombEvidenceToPdf();
        _syncBombMeasurementToPdf();

        // sync ข้อมูล display (เลขที่เอกสาร/เลขรายงาน) ไปยัง PDF
        const docNo = $('#doc_no_bomb').val() || '';
        const rptNo = $('#report_no_bomb').val() || '';
        const rcvId = $('#receiveNoti_id_bomb').val() || '';
        $('#bpf_receiveNoti_id').val(rcvId);
        $('#bpf_doc_no').val(docNo);
        $('#bpf_report_no').val(rptNo);
        $('#bpf_case_doc_no').val(thaiDocNo($('#case_doc_no_bomb').val() || docNo));
        var bpfRptParts = (rptNo || '').split('/');
        $('#bpf_report_no_display').text(thaiReportNo(bpfRptParts[0] || ''));

        // ★ Capture canvas data ก่อนปิด modal มาตรฐาน
        var canvasCopyData = {};
        var stdCanvasIds = {
            'scene_sketch': 'scene_sketch_canvas_bomb',
            'body_diagram': 'body_diagram_canvas_bomb',
            'receiver_signature': 'sig-canvas-receiver-bomb',
            'sender_signature': 'sig-canvas-sender-bomb'
        };
        var pdfCanvasIds = {
            'scene_sketch': 'bpf_scene_sketch_canvas',
            'body_diagram': 'bpf_body_diagram_canvas',
            'receiver_signature': 'bpf_sig_receiver',
            'sender_signature': 'bpf_sig_sender'
        };
        Object.keys(stdCanvasIds).forEach(function(key) {
            var srcCvs = document.getElementById(stdCanvasIds[key]);
            if (srcCvs && srcCvs.width > 0 && srcCvs.height > 0) {
                try {
                    canvasCopyData[key] = srcCvs.toDataURL('image/png');
                } catch (e) {}
            }
        });

        // ปิด modal มาตรฐาน
        const stdEl = document.getElementById('addCheckListModalBomb');
        const stdModal = bootstrap.Modal.getInstance(stdEl);
        if (stdModal) stdModal.hide();

        // รอให้ modal ปิดเสร็จแล้วเปิด PDF modal
        stdEl.addEventListener('hidden.bs.modal', function onHidden() {
            stdEl.removeEventListener('hidden.bs.modal', onHidden);
            const pdfEl = document.getElementById('bombFormPdfModal');
            if (pdfEl) {
                pdfEl.addEventListener('shown.bs.modal', function onShown() {
                    pdfEl.removeEventListener('shown.bs.modal', onShown);
                    setTimeout(function() {
                        Object.keys(pdfCanvasIds).forEach(function(key) {
                            if (canvasCopyData[key]) {
                                var dst = document.getElementById(pdfCanvasIds[key]);
                                if (dst) {
                                    // ★ ใช้ SignaturePad.fromDataURL ถ้ามี (รองรับ HiDPI scaled context)
                                    var dstPad = (typeof signaturePads !== 'undefined') ? signaturePads[pdfCanvasIds[key]] : null;
                                    if (dstPad) {
                                        var cssW = dst.offsetWidth || dst.clientWidth || 300;
                                        var cssH = dst.offsetHeight || dst.clientHeight || 150;
                                        dstPad.fromDataURL(canvasCopyData[key], {
                                            ratio: 1,
                                            width: cssW,
                                            height: cssH
                                        });
                                    } else {
                                        var img = new Image();
                                        img.onload = function() {
                                            var ctx = dst.getContext('2d');
                                            ctx.clearRect(0, 0, dst.width, dst.height);
                                            var w = dst.offsetWidth || dst.clientWidth || dst.width;
                                            var h = dst.offsetHeight || dst.clientHeight || dst.height;
                                            ctx.drawImage(img, 0, 0, w, h);
                                        };
                                        img.src = canvasCopyData[key];
                                    }
                                }
                            }
                        });
                        if (typeof bpfRenderPhotosFromStore === 'function') bpfRenderPhotosFromStore();
                    }, 150);
                });
                bootstrap.Modal.getOrCreateInstance(pdfEl).show();
            }
        });
    }

    // ===== BOMB: สลับจาก PDF → ฟอร์มมาตรฐาน =====
    window.switchToBombStandardForm = function() {
        // sync ข้อมูลจาก PDF → ฟอร์มมาตรฐาน
        syncBombFormData('bombFormPdf', 'incidentCheckListFormBomb');

        // ★ sync dynamic rows จาก PDF → ฟอร์มมาตรฐาน
        if (typeof _syncBombInspectorToStd === 'function') _syncBombInspectorToStd();
        if (typeof _syncBombVictimToStd === 'function') _syncBombVictimToStd();
        if (typeof _syncBombBodyToStd === 'function') _syncBombBodyToStd();
        if (typeof _syncBombEvidenceToStd === 'function') _syncBombEvidenceToStd();
        if (typeof _syncBombMeasurementToStd === 'function') _syncBombMeasurementToStd();

        // sync ข้อมูล display กลับไปยังฟอร์มมาตรฐาน
        const docNo = $('#bpf_doc_no').val() || '';
        const rptNo = $('#bpf_report_no').val() || '';
        const rcvId = $('#bpf_receiveNoti_id').val() || '';
        $('#receiveNoti_id_bomb').val(rcvId);
        $('#doc_no_bomb').val(docNo);
        $('#report_no_bomb').val(rptNo);
        $('#receiveNoti_No_bomb').text(thaiDocNo(docNo));
        $('#receiveNotiReportNo_bomb').text(thaiReportNo(rptNo));
        $('#case_doc_no_bomb').val(thaiDocNo($('#bpf_case_doc_no').val() || docNo));

        // ★ Capture canvas data จาก PDF form
        var canvasCopyData = {};
        var pdfCanvasIds = {
            'scene_sketch': 'bpf_scene_sketch_canvas',
            'body_diagram': 'bpf_body_diagram_canvas',
            'receiver_signature': 'bpf_sig_receiver',
            'sender_signature': 'bpf_sig_sender'
        };
        var stdCanvasIds = {
            'scene_sketch': 'scene_sketch_canvas_bomb',
            'body_diagram': 'body_diagram_canvas_bomb',
            'receiver_signature': 'sig-canvas-receiver-bomb',
            'sender_signature': 'sig-canvas-sender-bomb'
        };
        Object.keys(pdfCanvasIds).forEach(function(key) {
            var srcCvs = document.getElementById(pdfCanvasIds[key]);
            if (srcCvs && srcCvs.width > 0 && srcCvs.height > 0) {
                try {
                    canvasCopyData[key] = srcCvs.toDataURL('image/png');
                } catch (e) {}
            }
        });

        // ปิด PDF modal
        const pdfEl = document.getElementById('bombFormPdfModal');
        const pdfModal = bootstrap.Modal.getInstance(pdfEl);
        if (pdfModal) pdfModal.hide();

        // รอให้ modal ปิดเสร็จแล้วเปิดฟอร์มมาตรฐาน
        pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
            pdfEl.removeEventListener('hidden.bs.modal', onHidden);
            const stdEl = document.getElementById('addCheckListModalBomb');
            if (stdEl) {
                stdEl.addEventListener('shown.bs.modal', function onShown() {
                    stdEl.removeEventListener('shown.bs.modal', onShown);
                    setTimeout(function() {
                        Object.keys(stdCanvasIds).forEach(function(key) {
                            if (canvasCopyData[key]) {
                                var dst = document.getElementById(stdCanvasIds[key]);
                                if (dst) {
                                    // ★ ใช้ SignaturePad.fromDataURL ถ้ามี (รองรับ HiDPI scaled context)
                                    var dstPad = (typeof signaturePads !== 'undefined') ? signaturePads[stdCanvasIds[key]] : null;
                                    if (dstPad) {
                                        var cssW = dst.offsetWidth || dst.clientWidth || 300;
                                        var cssH = dst.offsetHeight || dst.clientHeight || 150;
                                        dstPad.fromDataURL(canvasCopyData[key], {
                                            ratio: 1,
                                            width: cssW,
                                            height: cssH
                                        });
                                    } else {
                                        var img = new Image();
                                        img.onload = function() {
                                            var ctx = dst.getContext('2d');
                                            ctx.clearRect(0, 0, dst.width, dst.height);
                                            var w = dst.offsetWidth || dst.clientWidth || dst.width;
                                            var h = dst.offsetHeight || dst.clientHeight || dst.height;
                                            ctx.drawImage(img, 0, 0, w, h);
                                        };
                                        img.src = canvasCopyData[key];
                                    }
                                }
                            }
                        });
                        // ★ render รูปที่แนบ (รวมที่เพิ่มในฟอร์มเสมือน) ลงฟอร์มมาตรฐาน
                        if (typeof window.renderBombAttachmentGrid === 'function') window.renderBombAttachmentGrid();
                    }, 150);
                });
                bootstrap.Modal.getOrCreateInstance(stdEl).show();
            }
        });
    };

    // =============================================================
    //  BOMB: Dynamic Row Sync Functions (★ matching life form pattern)
    // =============================================================

    // ===== BOMB: Sync Inspector rows → PDF =====
    function _syncBombInspectorToPdf() {
        const stdForm = document.getElementById('incidentCheckListFormBomb');
        if (!stdForm) return;
        const srcSelects = stdForm.querySelectorAll('[name="inspector_id[]"]');
        const container = document.getElementById('bpf_inspector_container');
        if (!container) return;

        const existingSel = container.querySelector('select');
        const optionsHtml = existingSel ? existingSel.innerHTML : (srcSelects[0] ? srcSelects[0].innerHTML : '');
        container.innerHTML = '';

        const count = Math.max(srcSelects.length, 1);
        for (let i = 0; i < count; i++) {
            const row = document.createElement('div');
            row.className = 'bpf-si bpf-inspector-row';
            row.innerHTML = '<span class="bpf-si-no">5.' + (i + 1) + '</span>' +
                '<select class="bpf-sel" name="inspector_id[]">' + optionsHtml + '</select>' +
                (i > 0 ? ' <button type="button" class="bpf-del-btn" onclick="this.parentElement.remove(); if (typeof bpfRenumberInspectors === \'function\') bpfRenumberInspectors();">×</button>' : '');
            container.appendChild(row);
            if (i < srcSelects.length && srcSelects[i].value) {
                row.querySelector('select').value = srcSelects[i].value;
            }
        }

        if (typeof window.bpfRenumberInspectors === 'function') {
            window.bpfRenumberInspectors();
        }
    }

    // ===== BOMB: Sync Victim rows → PDF =====
    function _syncBombVictimToPdf() {
        const $stdVictims = $('#victim_container_bomb .victim-card-bomb');
        const container = document.getElementById('bpf_victim_container');
        if (!container) return;

        const victims = [];
        $stdVictims.each(function(_, card) {
            const $card = $(card);
            const type = ($card.find('select[name="victim_type_bomb[]"]').val() || '').trim();
            const name = ($card.find('input[name="victim_name_bomb[]"]').val() || '').trim();
            const age = ($card.find('input[name="victim_age_bomb[]"]').val() || '').trim();
            if (!type && !name && !age) return;
            victims.push({
                type,
                name,
                age
            });
        });

        if (!victims.length) {
            victims.push({
                type: '',
                name: '',
                age: ''
            });
        }

        container.innerHTML = '';
        victims.forEach(function(v, idx) {
            const row = document.createElement('div');
            row.className = 'bpf-victim-row';
            row.style.cssText = 'margin-top:3px; padding:2px 0; border-top:1px dotted #ccc;';
            row.innerHTML = '<div class="bpf-fr">' +
                '<span class="bpf-fl">ประเภท</span>' +
                '<select class="bpf-sel" name="victim_type_bomb[]" style="max-width:90px;">' +
                '<option value=""' + (!v.type ? ' selected' : '') + '>--เลือก--</option>' +
                '<option value="ผู้เสียหาย"' + (v.type === 'ผู้เสียหาย' ? ' selected' : '') + '>ผู้เสียหาย</option>' +
                '<option value="ผู้บาดเจ็บ"' + (v.type === 'ผู้บาดเจ็บ' ? ' selected' : '') + '>ผู้บาดเจ็บ</option>' +
                '<option value="ผู้เสียชีวิต"' + (v.type === 'ผู้เสียชีวิต' ? ' selected' : '') + '>ผู้เสียชีวิต</option>' +
                '</select>' +
                '<span class="bpf-fl" style="margin-left:6px;">ชื่อ</span>' +
                '<input type="text" class="bpf-inp" name="victim_name_bomb[]" value="' + (v.name || '').replace(/"/g, '&quot;') + '">' +
                '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
                '<span class="bpf-fl" style="margin-left:4px;">อายุ</span>' +
                '<input type="text" class="bpf-inp-s" name="victim_age_bomb[]" style="max-width:30px;" value="' + (v.age || '').replace(/"/g, '&quot;') + '">' +
                '<span class="bpf-fl">ปี</span>' +
                (idx > 0 ? ' <button type="button" class="bpf-del-btn" onclick="this.closest(\'.bpf-victim-row\').remove()">×</button>' : '') +
                '</div>';
            container.appendChild(row);
        });
    }

    // ===== BOMB: Sync Body rows → PDF =====
    function _syncBombBodyToPdf() {
        const $stdBodies = $('#body_container_bomb .body-card-bomb');
        const container = document.getElementById('bpf_bodies_container');
        if (!container) return;

        const headerHtml = '<div class="bpf-bh"><span class="bpf-bk"></span><span>ศพ/ผู้บาดเจ็บ</span></div>';
        const bodies = [];

        $stdBodies.each(function(_, card) {
            const $card = $(card);
            const status = ($card.find('.js-body-status:checked').val() || '').trim();
            const name = ($card.find('.js-body-name').val() || '').trim();
            const condition = ($card.find('.js-body-condition-area').val() || '').trim();
            const notfound = ($card.find('.js-body-notfound-text').val() || '').trim();
            if (!status && !name && !condition && !notfound) return;
            bodies.push({
                status,
                name,
                condition,
                notfound
            });
        });

        if (!bodies.length) {
            bodies.push({
                status: '',
                name: '',
                condition: '',
                notfound: ''
            });
        }

        container.innerHTML = headerHtml;
        bodies.forEach(function(body, idx) {
            const row = document.createElement('div');
            row.className = 'bpf-body-row';
            row.style.cssText = 'border-top:1px dotted #ccc; padding-top:4px; margin-top:4px;';
            row.innerHTML =
                '<div class="bpf-fr">' +
                '<label class="bpf-ck"><input type="checkbox" class="bpf-cb bpf-radio-toggle bpf-body-status" name="body_status_bomb[]" value="พบศพ" data-group="bpf_body_status_' + idx + '"' + (body.status === 'พบศพ' ? ' checked' : '') + '>พบศพ</label>' +
                '<label class="bpf-ck"><input type="checkbox" class="bpf-cb bpf-radio-toggle bpf-body-status" name="body_status_bomb[]" value="ไม่พบศพ" data-group="bpf_body_status_' + idx + '"' + (body.status === 'ไม่พบศพ' ? ' checked' : '') + '>ไม่พบศพ</label>' +
                '<input type="text" class="bpf-inp bpf-body-notfound" name="body_notfound_detail_bomb[]" style="max-width:180px;' + (body.status === 'ไม่พบศพ' ? '' : ' display:none;') + '" placeholder="ระบุเหตุผล" value="' + (body.notfound || '').replace(/"/g, '&quot;') + '">' +
                '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
                '</div>' +
                '<div class="bpf-fr">' +
                '<span class="bpf-fl">ชื่อ-สกุล</span>' +
                '<input type="text" class="bpf-inp" name="body_name_bomb[]" value="' + (body.name || '').replace(/"/g, '&quot;') + '">' +
                '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
                (idx > 0 ? ' <button type="button" class="bpf-del-btn" onclick="this.closest(\'.bpf-body-row\').remove()">×</button>' : '') +
                '</div>' +
                '<div class="bpf-fr">' +
                '<span class="bpf-fl">ลักษณะบาดแผล</span>' +
                '<input type="text" class="bpf-inp" name="body_condition_bomb[]" value="' + (body.condition || '').replace(/"/g, '&quot;') + '">' +
                '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:0 4px; border:none; background:none; color:#6366f1; font-size:0.7rem;"><i class="fas fa-pen"></i></button>' +
                '</div>';
            container.appendChild(row);
        });
    }

    // ===== BOMB: Sync Evidence rows → PDF =====
    function _syncBombEvidenceToPdf() {
        const $stdCards = $('#evidence_container_bomb .evidence-card-bomb');
        const tbody = document.getElementById('bpf_evidence_tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!$stdCards.length) return;

        $stdCards.each(function(idx, card) {
            const $card = $(card);
            const item = $card.find('input[name="evidence_item_bomb[]"]').val() || '';
            const azimuth = $card.find('input[name="evidence_azimuth_bomb[]"]').val() || '';
            const remark = $card.find('input[name="evidence_remark_bomb[]"]').val() || '';
            const labUnit = window.getLabUnitsString($card.find('[name="evidence_lab_unit_bomb[]"]'));

            const ref1 = $card.find('input[name^="evidence_ref1_dist_bomb"]').val() || '';
            const ref2 = $card.find('input[name^="evidence_ref2_dist_bomb"]').val() || '';
            const ref3 = $card.find('input[name^="evidence_ref3_dist_bomb"]').val() || '';
            const ref4 = $card.find('input[name^="evidence_ref4_dist_bomb"]').val() || '';

            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td><input type="text" name="bomb_ev_label[]" style="width:35px;" value="' + (idx + 1) + '"></td>' +
                '<td><input type="text" name="bomb_ev_item[]" value="' + item.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="text" name="bomb_ev_level_1_' + idx + '" value="' + ref1.replace(/"/g, '&quot;') + '" style="width:40px; text-align:center;" inputmode="decimal"></td>' +
                '<td><input type="text" name="bomb_ev_level_2_' + idx + '" value="' + ref2.replace(/"/g, '&quot;') + '" style="width:40px; text-align:center;" inputmode="decimal"></td>' +
                '<td><input type="text" name="bomb_ev_level_3_' + idx + '" value="' + ref3.replace(/"/g, '&quot;') + '" style="width:40px; text-align:center;" inputmode="decimal"></td>' +
                '<td><input type="text" name="bomb_ev_level_4_' + idx + '" value="' + ref4.replace(/"/g, '&quot;') + '" style="width:40px; text-align:center;" inputmode="decimal"></td>' +
                '<td><input type="text" name="bomb_ev_azimuth[]" value="' + azimuth.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="text" name="bomb_ev_remark[]" value="' + remark.replace(/"/g, '&quot;') + '"></td>' +
                '<td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option><option value="explosive">วัตถุระเบิด (กก.กตว.)</option></select><input type="hidden" class="lab-unit-value" name="evidence_lab_unit_bomb[]" value=""></td>' +
                '<td><button type="button" class="bpf-del-btn" onclick="bpfDelRow(this)">×</button></td>';
            tbody.appendChild(tr);
            window.setLabUnits($(tr).find('[name="evidence_lab_unit_bomb[]"]'), labUnit);
        });
    }

    // ========== BOMB: Reverse Sync (PDF → มาตรฐาน) ==========

    // ---------- Bomb Inspector: PDF → มาตรฐาน ----------
    function _syncBombInspectorToStd() {
        const pdfForm = document.getElementById('bombFormPdf');
        if (!pdfForm) return;
        const srcSelects = pdfForm.querySelectorAll('[name="inspector_id[]"]');
        const container = document.getElementById('inspector_container_bomb');
        if (!container) return;

        // ลบ inspector rows เดิม (ทำลาย Select2 ก่อน)
        $('#inspector_container_bomb .inspector-select-bomb').each(function() {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).select2('destroy');
            }
        });
        $('#inspector_container_bomb').empty();

        const count = Math.max(srcSelects.length, 1);
        for (let i = 0; i < count; i++) {
            const deleteBtn = (i > 0) ? `
                <div class="ms-2">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0 rounded-circle remove-inspector-btn-bomb d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" title="ลบ">
                        <i class="fas fa-times"></i>
                    </button>
                </div>` : '<div class="ms-2" style="width: 32px;"></div>';
            const rowHtml = `
                <div class="d-flex align-items-center mb-3 inspector-row-bomb">
                    <div class="text-end pe-3" style="width: 50px;">
                        <span class="fw-bold text-secondary index-label">5.${i + 1}</span>
                    </div>
                    <div class="flex-grow-1">
                        <select class="form-select inspector-select-bomb" name="inspector_id[]">
                            ${typeof inspectorOptionsHTMLBomb !== 'undefined' ? inspectorOptionsHTMLBomb : ''}
                        </select>
                    </div>
                    ${deleteBtn}
                </div>`;
            $('#inspector_container_bomb').append(rowHtml);
        }

        // Re-init Select2 แล้วตั้งค่าจาก PDF form
        $('#inspector_container_bomb .inspector-select-bomb').each(function(idx) {
            $(this).select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'กรุณาเลือก',
                allowClear: true,
                dropdownParent: $('#addCheckListModalBomb'),
                dropdownAutoWidth: true
            });
            if (idx < srcSelects.length && srcSelects[idx].value) {
                $(this).val(srcSelects[idx].value).trigger('change');
            }
        });
    }

    // ---------- Bomb Victim: PDF → มาตรฐาน ----------
    function _syncBombVictimToStd() {
        const pdfRows = document.querySelectorAll('#bpf_victim_container .bpf-victim-row');
        const container = document.getElementById('victim_container_bomb');
        if (!container || !pdfRows.length) return;
        container.innerHTML = '';

        let cardIdx = 0;
        pdfRows.forEach(function(row) {
            const typeSel = row.querySelector('select[name="victim_type_bomb[]"]');
            const type = typeSel ? (typeSel.value || '') : '';
            const nameInp = row.querySelector('input[name="victim_name_bomb[]"]');
            const ageInp = row.querySelector('input[name="victim_age_bomb[]"]');
            const name = nameInp ? (nameInp.value || '') : '';
            const age = ageInp ? (ageInp.value || '') : '';

            if (!type && !name && !age) return;
            cardIdx++;

            const html = `
                <div class="victim-card-bomb bg-light p-3 rounded-3 border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-primary">รายการที่ ${cardIdx}</span>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVictimCardBomb(this)">
                            <i class="fas fa-trash me-1"></i> ลบรายการนี้
                        </button>
                    </div>
                    <div class="bg-white p-3 rounded-3 shadow-sm">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">ประเภทผู้ประสบเหตุ <span class="text-danger">*</span></label>
                            <select class="form-select" name="victim_type_bomb[]" onchange="toggleVictimFields(this)">
                                <option value="" ${!type ? 'selected' : ''}>-- เลือกประเภท --</option>
                                <option value="ผู้เสียชีวิต" ${type === 'ผู้เสียชีวิต' ? 'selected' : ''}>ผู้เสียชีวิต</option>
                                <option value="ผู้บาดเจ็บ" ${type === 'ผู้บาดเจ็บ' ? 'selected' : ''}>ผู้บาดเจ็บ</option>
                                <option value="ผู้เสียหาย" ${type === 'ผู้เสียหาย' ? 'selected' : ''}>ผู้เสียหาย</option>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-10">
                                <label class="form-label small text-muted">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="victim_name_bomb[]" value="${(name || '').replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted">อายุ (ปี)</label>
                                <input type="text" class="form-control text-center" name="victim_age_bomb[]" value="${(age || '').replace(/"/g, '&quot;')}">
                            </div>
                        </div>
                    </div>
                </div>`;
            $(container).append(html);
        });

        if (cardIdx === 0) {
            const html = `
                <div class="victim-card-bomb bg-light p-3 rounded-3 border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-primary">รายการที่ 1</span>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVictimCardBomb(this)">
                            <i class="fas fa-trash me-1"></i> ลบรายการนี้
                        </button>
                    </div>
                    <div class="bg-white p-3 rounded-3 shadow-sm">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">ประเภทผู้ประสบเหตุ <span class="text-danger">*</span></label>
                            <select class="form-select" name="victim_type_bomb[]" onchange="toggleVictimFields(this)">
                                <option value="" selected>-- เลือกประเภท --</option>
                                <option value="ผู้เสียชีวิต">ผู้เสียชีวิต</option>
                                <option value="ผู้บาดเจ็บ">ผู้บาดเจ็บ</option>
                                <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-10">
                                <label class="form-label small text-muted">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="victim_name_bomb[]">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted">อายุ (ปี)</label>
                                <input type="text" class="form-control text-center" name="victim_age_bomb[]">
                            </div>
                        </div>
                    </div>
                </div>`;
            $(container).append(html);
        }
    }

    // ===== BOMB: Sync Body rows → มาตรฐาน =====
    function _syncBombBodyToStd() {
        const pdfRows = document.querySelectorAll('#bpf_bodies_container .bpf-body-row');
        const container = document.getElementById('body_container_bomb');
        if (!container) return;
        container.innerHTML = '';

        let bodyIdx = 0;
        pdfRows.forEach(function(row) {
            const status = (row.querySelector('input[name="body_status_bomb[]"]:checked') || {}).value || '';
            const name = (row.querySelector('input[name="body_name_bomb[]"]') || {}).value || '';
            const condition = (row.querySelector('input[name="body_condition_bomb[]"]') || {}).value || '';
            const notfound = (row.querySelector('input[name="body_notfound_detail_bomb[]"]') || {}).value || '';
            if (!status && !name && !condition && !notfound) return;
            bodyIdx++;

            if (typeof addBodyCardBomb === 'function') {
                addBodyCardBomb();
            }

            const $card = $('#body_container_bomb .body-card-bomb').last();
            const $status = $card.find('.js-body-status[value="' + status + '"]');
            if ($status.length) {
                $status.prop('checked', true);
                if (typeof toggleBodyCondition === 'function') {
                    toggleBodyCondition($status.get(0), status === 'พบศพ' ? 'found' : 'notfound');
                }
            }
            // ★ Enable inputs and fill values AFTER toggleBodyCondition (toggle disables name for ไม่พบศพ)
            // Disabled inputs are NOT submitted with the form → names/conditions would be lost
            $card.find('.js-body-name').prop('disabled', false).val(name);
            $card.find('.js-body-condition-area').prop('disabled', false).val(condition);
            $card.find('.js-body-notfound-text').val(notfound);
            if (status === 'ไม่พบศพ') {
                $card.find('.js-body-notfound-text').show().prop('disabled', false);
            }
        });

        if (bodyIdx === 0 && typeof addBodyCardBomb === 'function') {
            addBodyCardBomb();
        }

        if (typeof reIndexBodyCardsBomb === 'function') reIndexBodyCardsBomb();
    }

    // ---------- Bomb Evidence: PDF → มาตรฐาน ----------
    function _syncBombEvidenceToStd() {
        const tbody = document.getElementById('bpf_evidence_tbody');
        if (!tbody) return;
        const rows = tbody.querySelectorAll('tr');
        const container = document.getElementById('evidence_container_bomb');
        if (!container) return;
        container.innerHTML = '';

        const count = Math.max(rows.length, 1);
        for (let i = 0; i < count; i++) {
            const row = (i < rows.length) ? rows[i] : null;
            const item = row ? (row.querySelector('input[name="bomb_ev_item[]"]') || {}).value || '' : '';
            // ★ ใช้ starts-with selector เพื่อหา distance inputs โดยไม่ขึ้นกับ index
            const ref1cb = row ? row.querySelector('input[name^="bomb_ev_level_1_"]') : null;
            const ref2cb = row ? row.querySelector('input[name^="bomb_ev_level_2_"]') : null;
            const ref3cb = row ? row.querySelector('input[name^="bomb_ev_level_3_"]') : null;
            const ref4cb = row ? row.querySelector('input[name^="bomb_ev_level_4_"]') : null;
            const ref1 = ref1cb ? (ref1cb.value || '') : '';
            const ref2 = ref2cb ? (ref2cb.value || '') : '';
            const ref3 = ref3cb ? (ref3cb.value || '') : '';
            const ref4 = ref4cb ? (ref4cb.value || '') : '';
            const azimuth = row ? (row.querySelector('input[name="bomb_ev_azimuth[]"]') || {}).value || '' : '';
            const remark = row ? (row.querySelector('input[name="bomb_ev_remark[]"]') || {}).value || '' : '';
            const labUnit = row ? window.getLabUnitsString(row.querySelector('[name="evidence_lab_unit_bomb[]"]')) : '';

            const cardHtml = `
                <div class="evidence-card-bomb bg-light p-3 rounded-3 border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-primary text-white js-evidence-index-badge">รายการวัตถุพยานที่ ${i + 1}</span>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEvidenceCardBomb(this)" ${i === 0 ? 'style="display:none;"' : ''}>
                            <i class="fas fa-trash me-1"></i> ลบรายการนี้
                        </button>
                    </div>
                    <div class="bg-white p-3 rounded-3 shadow-sm border">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small text-muted">วัตถุพยาน</label>
                                <input type="text" class="form-control" name="evidence_item_bomb[]" value="${item.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted">ระยะห่าง (m) จากจุดอ้างอิง</label>
                                <div class="row g-2">
                                    <div class="col-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">จุดที่ 1</span>
                                            <input type="text" class="form-control" name="evidence_ref1_dist_bomb[]" value="${ref1.replace(/"/g, '&quot;')}">
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">จุดที่ 2</span>
                                            <input type="text" class="form-control" name="evidence_ref2_dist_bomb[]" value="${ref2.replace(/"/g, '&quot;')}">
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">จุดที่ 3</span>
                                            <input type="text" class="form-control" name="evidence_ref3_dist_bomb[]" value="${ref3.replace(/"/g, '&quot;')}">
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">จุดที่ 4</span>
                                            <input type="text" class="form-control" name="evidence_ref4_dist_bomb[]" value="${ref4.replace(/"/g, '&quot;')}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Azimuth (พิกัด/องศา/ระยะ)</label>
                                <input type="text" class="form-control" name="evidence_azimuth_bomb[]" value="${azimuth.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">หมายเหตุ</label>
                                <input type="text" class="form-control" name="evidence_remark_bomb[]" value="${remark.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">การตรวจพิสูจน์</label>
                                <select class="form-select lab-unit-multi" multiple size="4" title="เลือกได้มากกว่า 1 กลุ่มงาน">
                                    <option value="">-- เลือก (เลือกได้หลายข้อ) --</option>
                                    <option value="fingerprint">ลายนิ้วมือแฝง</option>
                                    <option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option>
                                    <option value="chemical">เคมีฟิสิกส์</option>
                                    <option value="drug">ยาเสพติด</option>
                                    <option value="gun">อาวุธปืน</option>
                                    <option value="document">เอกสาร</option>
                                    <option value="digital">ดิจิทัล</option>
                                    <option value="computer">คอมพิวเตอร์</option>
                                    <option value="explosive">วัตถุระเบิด (กก.กตว.)</option>
                                </select>
                                <input type="hidden" class="lab-unit-value" name="evidence_lab_unit_bomb[]" value="">
                            </div>
                        </div>
                    </div>
                </div>`;
            $(container).append(cardHtml);
            window.setLabUnits($(container).find('.evidence-card-bomb').last().find('[name="evidence_lab_unit_bomb[]"]'), labUnit);
        }
    }

    // ---------- Bomb Measurement: PDF → มาตรฐาน (บันทึกการตรวจเก็บวัตถุพยาน) ----------
    function _syncBombMeasurementToStd() {
        const tbody = document.getElementById('bpf_collection_tbody');
        if (!tbody) return;
        const rows = tbody.querySelectorAll('tr');
        const container = document.getElementById('measurement_container_bomb');
        if (!container) return;
        container.innerHTML = '';

        const count = Math.max(rows.length, 1);
        for (let i = 0; i < count; i++) {
            const row = (i < rows.length) ? rows[i] : null;
            const item = row ? (row.querySelector('input[name="measurement_item_bomb[]"]') || {}).value || '' : '';
            const qty = row ? (row.querySelector('input[name="measurement_quantity_bomb[]"]') || {}).value || '' : '';
            const area = row ? (row.querySelector('input[name="measurement_area_bomb[]"]') || {}).value || '' : '';
            const labelNo = row ? (row.querySelector('input[name="measurement_label_number_bomb[]"]') || {}).value || '' : '';
            const remark = row ? (row.querySelector('input[name="measurement_remark_bomb[]"]') || {}).value || '' : '';
            const forensicUnit = row ? window.getLabUnitsString(row.querySelector('[name="measurement_forensic_unit_bomb[]"]')) : '';

            const pkgPlastic = row ? !!(row.querySelector('input[name="measurement_package_plastic_check[' + i + ']"]') || {}).checked : false;
            const pkgPaper = row ? !!(row.querySelector('input[name="measurement_package_paper_check[' + i + ']"]') || {}).checked : false;
            const pkgOther = row ? !!(row.querySelector('input[name="measurement_package_other_check[' + i + ']"]') || {}).checked : false;
            const actReturn = row ? !!(row.querySelector('input[name="measurement_action_return_check[' + i + ']"]') || {}).checked : false;
            const actOther = row ? !!(row.querySelector('input[name="measurement_action_other_check[' + i + ']"]') || {}).checked : false;
            const pkgPlasticText = row ? (row.querySelector('input[name="measurement_package_plastic_text[' + i + ']"]') || {}).value || '' : '';
            const pkgPaperText = row ? (row.querySelector('input[name="measurement_package_paper_text[' + i + ']"]') || {}).value || '' : '';
            const pkgOtherText = row ? (row.querySelector('input[name="measurement_package_other_text[' + i + ']"]') || {}).value || '' : '';
            const actReturnText = row ? (row.querySelector('input[name="measurement_action_return_text[' + i + ']"]') || {}).value || '' : '';
            const actOtherText = row ? (row.querySelector('input[name="measurement_action_other_text[' + i + ']"]') || {}).value || '' : '';

            const cardHtml = `
                <div class="measurement-card-bomb bg-light p-3 rounded-3 border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-primary text-white js-measurement-index-badge">รายการวัตถุพยานที่ ${i + 1}</span>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMeasurementCardBomb(this)" ${i === 0 ? 'style="display:none;"' : ''}>
                            <i class="fas fa-trash me-1"></i> ลบรายการนี้
                        </button>
                    </div>
                    <div class="bg-white p-3 rounded-3 shadow-sm border">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label small text-muted">รายการวัตถุพยาน</label>
                                <input type="text" class="form-control" name="measurement_item_bomb[]" value="${item.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">จำนวน</label>
                                <input type="text" class="form-control" name="measurement_quantity_bomb[]" value="${qty.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small text-muted">บริเวณที่ตรวจพบ</label>
                                <input type="text" class="form-control" name="measurement_area_bomb[]" value="${area.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">ป้ายหมายเลข</label>
                                <input type="text" class="form-control" name="measurement_label_number_bomb[]" value="${labelNo.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted">การบรรจุหีบห่อ</label>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="measurement_package_plastic_check[${i}]" value="1" ${pkgPlastic ? 'checked' : ''}>
                                            <label class="form-check-label">พลาสติก</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm mt-1" name="measurement_package_plastic_text[${i}]" value="${pkgPlasticText.replace(/"/g, '&quot;')}">
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="measurement_package_paper_check[${i}]" value="1" ${pkgPaper ? 'checked' : ''}>
                                            <label class="form-check-label">กระดาษ</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm mt-1" name="measurement_package_paper_text[${i}]" value="${pkgPaperText.replace(/"/g, '&quot;')}">
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="measurement_package_other_check[${i}]" value="1" ${pkgOther ? 'checked' : ''}>
                                            <label class="form-check-label">อื่นๆ</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm mt-1" name="measurement_package_other_text[${i}]" value="${pkgOtherText.replace(/"/g, '&quot;')}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted">การดำเนินการเกี่ยวกับวัตถุพยาน</label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="measurement_action_return_check[${i}]" value="1" ${actReturn ? 'checked' : ''}>
                                            <label class="form-check-label">ส่งคืน พงส.</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm mt-1" name="measurement_action_return_text[${i}]" value="${actReturnText.replace(/"/g, '&quot;')}">
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="measurement_action_other_check[${i}]" value="1" ${actOther ? 'checked' : ''}>
                                            <label class="form-check-label">อื่นๆ</label>
                                        </div>
                                        <input type="text" class="form-control form-control-sm mt-1" name="measurement_action_other_text[${i}]" value="${actOtherText.replace(/"/g, '&quot;')}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted">หมายเหตุ</label>
                                <input type="text" class="form-control" name="measurement_remark_bomb[]" value="${remark.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted">การตรวจพิสูจน์</label>
                                <select class="form-select lab-unit-multi" multiple size="4" title="เลือกได้มากกว่า 1 กลุ่มงาน">
                                    <option value="">-- กรุณาเลือก (เลือกได้หลายข้อ) --</option>
                                    <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                    <option value="bio_dna">กลุ่มงานตรวจชีววิทยาและดีเอ็นเอ</option>
                                    <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                    <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                    <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                    <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                    <option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option>
                                    <option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                                    <option value="explosive">กองกำกับการเก็บกู้และตรวจสอบวัตถุระเบิด (กก.กตว.)</option>
                                </select>
                                <input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_bomb[]" value="">
                            </div>
                        </div>
                    </div>
                </div>`;
            $(container).append(cardHtml);
            window.setLabUnits($(container).find('.measurement-card-bomb').last().find('[name="measurement_forensic_unit_bomb[]"]'), forensicUnit);
        }
    }

    // ---------- Bomb Measurement: มาตรฐาน → PDF (sync กลับ) ----------
    function _syncBombMeasurementToPdf() {
        const container = document.getElementById('measurement_container_bomb');
        if (!container) return;
        const cards = container.querySelectorAll('.measurement-card-bomb');
        const tbody = document.getElementById('bpf_collection_tbody');
        if (!tbody) return;

        // ตรวจสอบว่า measurement cards มีข้อมูลจริงหรือไม่
        var hasMeasurementData = false;
        cards.forEach(function(c) {
            var v = (c.querySelector('input[name^="measurement_item_bomb"]') || {}).value || '';
            if (v.trim() !== '') hasMeasurementData = true;
        });

        // Evidence table กับ Collection table เป็นคนละส่วนกัน ไม่ cross-sync
        if (!hasMeasurementData) return;

        tbody.innerHTML = '';

        const count = Math.max(cards.length, 1);
        for (let i = 0; i < count; i++) {
            const card = (i < cards.length) ? cards[i] : null;
            const _qv = (c, sel) => c ? ((c.querySelector(sel) || {}).value || '') : '';
            const item = _qv(card, 'input[name^="measurement_item_bomb"]');
            const qty = _qv(card, 'input[name^="measurement_quantity_bomb"]');
            const area = _qv(card, 'input[name^="measurement_area_bomb"]');
            const labelNo = _qv(card, 'input[name^="measurement_label_number_bomb"]');
            const remark = _qv(card, 'input[name^="measurement_remark_bomb"]');
            const forensicUnit = card ? window.getLabUnitsString(card.querySelector('[name^="measurement_forensic_unit_bomb"]')) : '';

            const pkgPlastic = card ? !!(card.querySelector('input[name^="measurement_package_plastic_check"]') || {}).checked : false;
            const pkgPaper = card ? !!(card.querySelector('input[name^="measurement_package_paper_check"]') || {}).checked : false;
            const pkgOther = card ? !!(card.querySelector('input[name^="measurement_package_other_check"]') || {}).checked : false;
            const actReturn = card ? !!(card.querySelector('input[name^="measurement_action_return_check"]') || {}).checked : false;
            const actOther = card ? !!(card.querySelector('input[name^="measurement_action_other_check"]') || {}).checked : false;
            const pkgPlasticText = card ? (card.querySelector('input[name^="measurement_package_plastic_text"]') || {}).value || '' : '';
            const pkgPaperText = card ? (card.querySelector('input[name^="measurement_package_paper_text"]') || {}).value || '' : '';
            const pkgOtherText = card ? (card.querySelector('input[name^="measurement_package_other_text"]') || {}).value || '' : '';
            const actReturnText = card ? (card.querySelector('input[name^="measurement_action_return_text"]') || {}).value || '' : '';
            const actOtherText = card ? (card.querySelector('input[name^="measurement_action_other_text"]') || {}).value || '' : '';

            const tr = document.createElement('tr');
            tr.innerHTML = '<td style="text-align:center;">' + (i + 1) + '</td>' +
                '<td><input type="text" name="measurement_item_bomb[]" value="' + item.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="text" name="measurement_quantity_bomb[]" style="width:30px; text-align:center;" value="' + qty.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="text" name="measurement_area_bomb[]" value="' + area.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="text" name="measurement_label_number_bomb[]" style="width:30px; text-align:center;" value="' + labelNo.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="checkbox" name="measurement_package_plastic_check[' + i + ']" value="1"' + (pkgPlastic ? ' checked' : '') + '><input type="hidden" name="measurement_package_plastic_text[' + i + ']" value="' + pkgPlasticText.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="checkbox" name="measurement_package_paper_check[' + i + ']" value="1"' + (pkgPaper ? ' checked' : '') + '><input type="hidden" name="measurement_package_paper_text[' + i + ']" value="' + pkgPaperText.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="checkbox" name="measurement_package_other_check[' + i + ']" value="1"' + (pkgOther ? ' checked' : '') + '><input type="hidden" name="measurement_package_other_text[' + i + ']" value="' + pkgOtherText.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="checkbox" name="measurement_action_return_check[' + i + ']" value="1"' + (actReturn ? ' checked' : '') + '><input type="hidden" name="measurement_action_return_text[' + i + ']" value="' + actReturnText.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="checkbox" name="measurement_action_other_check[' + i + ']" value="1"' + (actOther ? ' checked' : '') + '><input type="hidden" name="measurement_action_other_text[' + i + ']" value="' + actOtherText.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="text" name="measurement_remark_bomb[]" value="' + remark.replace(/"/g, '&quot;') + '"></td>' +
                '<td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option><option value="explosive">วัตถุระเบิด (กก.กตว.)</option></select><input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_bomb[]" value=""></td>' +
                '<td><button type="button" class="bpf-del-btn" onclick="bpfDelRow(this)">×</button></td>';
            tbody.appendChild(tr);
            window.setLabUnits($(tr).find('[name="measurement_forensic_unit_bomb[]"]'), forensicUnit);
        }
    }


    // ===== BOMB: Special field sync (field names differ between PDF ↔ Standard) =====
    // ===== Helpers สำหรับ bomb special fields (แบบเดียวกับ Life) =====
    function _getBombFieldValue(form, name) {
        if (!form) return '';
        var el = form.querySelector('[name="' + name + '"]');
        return el ? (el.value || '').trim() : '';
    }

    function _isBombChecked(form, name) {
        var el = form.querySelector('[name="' + name + '"]');
        return el ? el.checked : false;
    }

    function _setBombCheckboxByName(form, name, checked) {
        var el = form.querySelector('[name="' + name + '"]');
        if (el && el.type === 'checkbox') el.checked = !!checked;
    }

    function _getBombFieldValueAny(form, names) {
        if (!Array.isArray(names)) names = [names];
        for (var i = 0; i < names.length; i++) {
            var value = _getBombFieldValue(form, names[i]);
            if (value !== '') return value;
        }
        return '';
    }

    function _isBombCheckedAny(form, names) {
        if (!Array.isArray(names)) names = [names];
        for (var i = 0; i < names.length; i++) {
            if (_isBombChecked(form, names[i])) return true;
        }
        return false;
    }

    function _setBombCheckboxByNames(form, names, checked) {
        if (!Array.isArray(names)) names = [names];
        names.forEach(function(name) {
            _setBombCheckboxByName(form, name, checked);
        });
    }

    function _syncBombSpecialFields(fromForm, toForm) {
        const fromId = fromForm ? (fromForm.id || '') : '';
        const toId = toForm ? (toForm.id || '') : '';
        var _splitPrimarySecondary = function(text) {
            var raw = (text || '').toString().replace(/\r/g, '').trim();
            if (!raw) return {
                primary: '',
                secondary: ''
            };
            var lines = raw.split('\n');
            return {
                primary: (lines.shift() || '').trim(),
                secondary: lines.join('\n').trim()
            };
        };
        var _joinPrimarySecondary = function(primary, secondary) {
            var p = (primary || '').toString().trim();
            var s = (secondary || '').toString().trim();
            if (p && s) return p + '\n' + s;
            return p || s;
        };

        // ===== Standard → PDF =====
        if (fromId === 'incidentCheckListFormBomb' && toId === 'bombFormPdf') {

            // ลักษณะภายนอก (ภายในอาคาร): แปลงค่า standard -> PDF
            var stdToPdfBuildingType = {
                'commercial': 'อาคารพาณิชย์',
                'house': 'บ้านเดี่ยว',
                'other': 'อื่นๆ'
            };
            toForm.querySelectorAll('input[name="building_type_indoor[]"]').forEach(function(cb) {
                cb.checked = false;
            });
            fromForm.querySelectorAll('input[name="building_type_indoor[]"]:checked').forEach(function(cb) {
                var mappedVal = stdToPdfBuildingType[cb.value] || cb.value;
                var pdfCb = toForm.querySelector('input[name="building_type_indoor[]"][value="' + mappedVal + '"]');
                if (pdfCb) pdfCb.checked = true;
            });

            // โครงสร้างอาคาร: auto-check PDF-only checkboxes ถ้า text field มีค่า
            _setBombCheckboxByName(toForm, 'structure_size_check_indoor', _getBombFieldValue(fromForm, 'structure_size_indoor') !== '');
            _setBombCheckboxByName(toForm, 'structure_type_check_indoor', _getBombFieldValue(fromForm, 'structure_type_indoor') !== '');
            _setBombCheckboxByName(toForm, 'structure_wall_check_indoor', _getBombFieldValue(fromForm, 'structure_wall_indoor') !== '');
            _setBombCheckboxByName(toForm, 'structure_floor_check_indoor', _getBombFieldValue(fromForm, 'structure_floor_indoor') !== '');
            _setBombCheckboxByName(toForm, 'structure_roof_check_indoor', _getBombFieldValue(fromForm, 'structure_roof_indoor') !== '');
            _setBombCheckboxByName(toForm, 'structure_arrangement_check_indoor', _getBombFieldValue(fromForm, 'structure_arrangement_indoor') !== '');
            _setBombCheckboxByName(toForm, 'building_floor_check_indoor', _getBombFieldValue(fromForm, 'building_floor_indoor') !== '');

            // ทดสอบคราบโลหิต: auto-check "test_blood_main" ถ้า hemastix หรือ phenol ถูกเลือก
            _setBombCheckboxByName(toForm, 'test_blood_main',
                _isBombChecked(fromForm, 'test_hemastix') || _isBombChecked(fromForm, 'test_phenolphthalein'));

            // ถ่ายภาพ: copy inspect_date/time → PDF-only photo fields
            var pdfPhotoDate = toForm.querySelector('[name="photo_inspect_date_bomb"]');
            var pdfPhotoTime = toForm.querySelector('[name="photo_inspect_time_bomb"]');
            if (pdfPhotoDate) pdfPhotoDate.value = _getBombFieldValue(fromForm, 'inspect_date');
            if (pdfPhotoTime) pdfPhotoTime.value = _getBombFieldValue(fromForm, 'inspect_time');

            // วัตถุพยาน: datetime-local → date + time แยก
            var stdMeasDate = _getBombFieldValue(fromForm, 'measurement_inspection_date_bomb');
            var pdfMDate = toForm.querySelector('[name="measurement_inspection_date_bomb"]');
            var pdfMTime = toForm.querySelector('[name="measurement_inspection_time_bomb"]');
            if (pdfMDate) pdfMDate.value = stdMeasDate ? stdMeasDate.split('T')[0] : '';
            if (pdfMTime && stdMeasDate && stdMeasDate.indexOf('T') !== -1) {
                pdfMTime.value = stdMeasDate.split('T')[1] || '';
            }

            // ★ Helper: copy value from standard field to PDF field
            var _svR = function(stdN, pdfN) {
                var v = _getBombFieldValue(fromForm, stdN);
                var el = toForm.querySelector('[name="' + pdfN + '"]');
                if (el) el.value = v;
            };

            // ★ Detonation: Standard individual checkboxes → PDF bomb_detonation[]
            var detRMap = {
                'detonate_trap': 'กับดัก',
                'detonate_wire': 'ลากสายไฟ',
                'detonate_radio': 'วิทยุสื่อสาร',
                'detonate_phone': 'โทรศัพท์มือถือ',
                'detonate_remote': 'รีโมทคอนโทรล',
                'detonate_timer': 'ตั้งเวลา',
                'detonate_other': 'อื่นๆ'
            };
            Object.keys(detRMap).forEach(function(stdName) {
                var isChecked = _isBombChecked(fromForm, stdName);
                var pdfCb = toForm.querySelector('input[name="bomb_detonation[]"][value="' + detRMap[stdName] + '"]');
                if (pdfCb) pdfCb.checked = isChecked;
            });
            _svR('detonate_trap_detail', 'bomb_det_trap_text');
            _svR('detonate_wire_color', 'bomb_det_wire_color');
            _svR('detonate_wire_length', 'bomb_det_wire_len');
            _svR('detonate_radio_brand', 'bomb_det_radio_brand');
            _svR('detonate_radio_model', 'bomb_det_radio_model');
            _svR('detonate_radio_color', 'bomb_det_radio_color');
            _svR('detonate_radio_sn', 'bomb_det_radio_sn');
            _svR('detonate_phone_brand', 'bomb_det_phone_brand');
            _svR('detonate_phone_model', 'bomb_det_phone_model');
            _svR('detonate_phone_color', 'bomb_det_phone_color');
            _svR('detonate_phone_sn', 'bomb_det_phone_sn');
            _svR('detonate_remote_detail', 'bomb_det_remote_text');
            _svR('detonate_timer_detail', 'bomb_det_timer_text');
            _svR('detonate_other_detail', 'bomb_det_other_text');

            // ★ Fragments: Standard → PDF bomb_frag[]
            var fragRMap = {
                'fragment_rebar': 'เหล็กเส้นตัดท่อน',
                'fragment_nail': 'ตะปู',
                'fragment_other': 'อื่นๆ'
            };
            Object.keys(fragRMap).forEach(function(stdName) {
                var isChecked = _isBombChecked(fromForm, stdName);
                var pdfCb = toForm.querySelector('input[name="bomb_frag[]"][value="' + fragRMap[stdName] + '"]');
                if (pdfCb) pdfCb.checked = isChecked;
            });
            _svR('fragment_rebar_size', 'bomb_frag_rebar_size');
            _svR('fragment_rebar_length', 'bomb_frag_rebar_len');
            _svR('fragment_nail_size', 'bomb_frag_nail_size');
            _svR('fragment_other_detail', 'bomb_frag_other_text');

            // ★ Components: Standard → PDF bomb_comp[]
            var compRMap = {
                'comp_booster': 'หลอดดินขยาย',
                'comp_detonator': 'เชื้อปะทุไฟฟ้า',
                'comp_tape': 'เทปพันสายไฟ',
                'comp_sim': 'ซิมการ์ด',
                'comp_circuit': 'วงจรการจุดระเบิด',
                'comp_battery': 'แบตเตอรี่',
                'comp_dtmf': 'แผงวงจร DTMF',
                'comp_pcb': 'แผงวงจร',
                'comp_wire': 'สายไฟวงจร',
                'comp_box': 'กล่องบรรจุวงจร',
                'comp_clock': 'นาฬิกา',
                'comp_lever': 'กระเดื่อง',
                'comp_pin': 'สลักนิรภัย',
                'comp_misc_other': 'อื่นๆ'
            };
            Object.keys(compRMap).forEach(function(stdName) {
                var isChecked = _isBombChecked(fromForm, stdName);
                var pdfCb = toForm.querySelector('input[name="bomb_comp[]"][value="' + compRMap[stdName] + '"]');
                if (pdfCb) pdfCb.checked = isChecked;
            });
            _svR('comp_booster_detail', 'bomb_comp_booster_text');
            _svR('comp_detonator_detail', 'bomb_comp_detonator_text');
            _svR('comp_tape_detail', 'bomb_comp_tape_text');
            _svR('comp_sim_detail', 'bomb_comp_sim_text');
            _svR('comp_circuit_detail', 'bomb_comp_circuit_text');
            _svR('comp_battery_detail', 'bomb_comp_battery_text');
            _svR('comp_battery_voltage', 'bomb_comp_battery_v');
            _svR('comp_dtmf_detail', 'bomb_comp_dtmf_text');
            _svR('comp_pcb_detail', 'bomb_comp_pcb_text');
            _svR('comp_wire_detail', 'bomb_comp_wire_text');
            _svR('comp_box_detail', 'bomb_comp_box_text');
            _svR('comp_clock_detail', 'bomb_comp_clock_text');
            _svR('comp_lever_detail', 'bomb_comp_lever_text');
            _svR('comp_pin_detail', 'bomb_comp_pin_text');
            _svR('comp_misc_other_detail', 'bomb_comp_other_text');

            // ★ Container other text
            _svR('bomb_container_other_text', 'bomb_cont_other_text');

            // ★ Blood evidence
            _setBombCheckboxByNames(toForm, ['evidence_blood_stain', 'bomb_blood_active'], _isBombChecked(fromForm, 'evidence_blood_stain'));
            var _bloodParts = _splitPrimarySecondary(_getBombFieldValue(fromForm, 'blood_stain_detail'));
            var _pdfBloodMain = toForm.querySelector('[name="blood_stain_detail"], [name="bomb_blood_detail"]');
            var _pdfBloodSub = toForm.querySelector('[name="blood_stain_detail_2"], [name="bomb_blood_detail_2"]');
            if (_pdfBloodMain) _pdfBloodMain.value = _bloodParts.primary;
            if (_pdfBloodSub) _pdfBloodSub.value = _bloodParts.secondary;
            _setBombCheckboxByNames(toForm, ['test_hemastix', 'bomb_test_hema'], _isBombChecked(fromForm, 'test_hemastix'));
            _setBombCheckboxByNames(toForm, ['test_phenolphthalein', 'bomb_test_phenol'], _isBombChecked(fromForm, 'test_phenolphthalein'));
            // hemastix/phenol results: ใช้ค่าภาษาไทยตาม checkbox จริงของ PDF form
            var _hemaStdToPdf = {
                'มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน': 'มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน',
                'ไม่มีการเปลี่ยนแปลง': 'ไม่มีการเปลี่ยนแปลง',
                'ไม่เกิดการเปลี่ยนแปลง': 'ไม่มีการเปลี่ยนแปลง'
            };
            var _phenolStdToPdf = {
                'มีการเปลี่ยนแปลงเป็นสีชมพูในทันที': 'มีการเปลี่ยนแปลงเป็นสีชมพูในทันที',
                'ไม่มีการเปลี่ยนแปลง': 'ไม่มีการเปลี่ยนแปลง',
                'ไม่เกิดการเปลี่ยนแปลง': 'ไม่มีการเปลี่ยนแปลง'
            };
            var stdHemaResult = '';
            fromForm.querySelectorAll('input[name="hemastix_result"]').forEach(function(r) {
                if (r.checked) stdHemaResult = r.value;
            });
            var pdfHemaVal = _hemaStdToPdf[stdHemaResult] || stdHemaResult;
            toForm.querySelectorAll('input[name="hemastix_result"], input[name="bomb_hema_result"]').forEach(function(cb) {
                cb.checked = (cb.value === pdfHemaVal);
            });
            var stdPhenolResult = '';
            fromForm.querySelectorAll('input[name="phenol_result"]').forEach(function(r) {
                if (r.checked) stdPhenolResult = r.value;
            });
            var pdfPhenolVal = _phenolStdToPdf[stdPhenolResult] || stdPhenolResult;
            toForm.querySelectorAll('input[name="phenol_result"], input[name="bomb_phenol_result"]').forEach(function(cb) {
                cb.checked = (cb.value === pdfPhenolVal);
            });

            // ★ Other evidence
            _setBombCheckboxByNames(toForm, ['has_evidence_other', 'bomb_other_ev_active'], _isBombChecked(fromForm, 'has_evidence_other'));
            var _otherParts = _splitPrimarySecondary(_getBombFieldValue(fromForm, 'evidence_other_detail'));
            var _pdfOtherMain = toForm.querySelector('[name="evidence_other_detail"], [name="bomb_other_ev_detail"]');
            var _pdfOtherSub = toForm.querySelector('[name="evidence_other_detail_2"], [name="bomb_other_ev_detail_2"]');
            if (_pdfOtherMain) _pdfOtherMain.value = _otherParts.primary;
            if (_pdfOtherSub) _pdfOtherSub.value = _otherParts.secondary;

            return;
        }

        // ===== PDF → Standard =====
        if (fromId === 'bombFormPdf' && toId === 'incidentCheckListFormBomb') {

            // ลักษณะภายนอก (ภายในอาคาร): แปลงค่า PDF -> standard
            var pdfToStdBuildingType = {
                'อาคารพาณิชย์': 'commercial',
                'บ้านเดี่ยว': 'house',
                'อื่นๆ': 'other'
            };
            toForm.querySelectorAll('input[name="building_type_indoor[]"]').forEach(function(cb) {
                cb.checked = false;
            });
            fromForm.querySelectorAll('input[name="building_type_indoor[]"]:checked').forEach(function(cb) {
                var mappedVal = pdfToStdBuildingType[cb.value] || cb.value;
                var stdCb = toForm.querySelector('input[name="building_type_indoor[]"][value="' + mappedVal + '"]');
                if (stdCb) stdCb.checked = true;
            });

            var hasLegacyDetonation = !!fromForm.querySelector('input[name="bomb_detonation[]"], [name="bomb_det_trap_text"], [name="bomb_det_wire_color"]');
            var hasLegacyFragments = !!fromForm.querySelector('input[name="bomb_frag[]"], [name="bomb_frag_rebar_size"], [name="bomb_frag_nail_size"]');
            var hasLegacyComponents = !!fromForm.querySelector('input[name="bomb_comp[]"], [name="bomb_comp_booster_text"], [name="bomb_comp_battery_text"]');

            // วัตถุพยาน: date + time แยก → datetime-local รวม
            var pdfMeasDate2 = _getBombFieldValue(fromForm, 'measurement_inspection_date_bomb');
            var pdfMeasTime2 = _getBombFieldValue(fromForm, 'measurement_inspection_time_bomb');
            var stdMeasField = toForm.querySelector('[name="measurement_inspection_date_bomb"]');
            if (stdMeasField) {
                if (pdfMeasDate2) {
                    stdMeasField.value = pdfMeasDate2 + 'T' + (pdfMeasTime2 || '00:00');
                } else {
                    stdMeasField.value = '';
                }
            }

            // ★ Detonation: PDF bomb_detonation[] (legacy) → Standard individual checkboxes
            var _sv = function(pdfN, stdN) {
                var v = _getBombFieldValue(fromForm, pdfN);
                var el = toForm.querySelector('[name="' + stdN + '"]');
                if (el) el.value = v;
            };
            if (hasLegacyDetonation) {
                var pdfDetChecks = fromForm.querySelectorAll('input[name="bomb_detonation[]"]');
                var detValMap = {
                    'กับดัก': 'detonate_trap',
                    'ลากสายไฟ': 'detonate_wire',
                    'วิทยุสื่อสาร': 'detonate_radio',
                    'โทรศัพท์มือถือ': 'detonate_phone',
                    'รีโมทคอนโทรล': 'detonate_remote',
                    'ตั้งเวลา': 'detonate_timer',
                    'อื่นๆ': 'detonate_other'
                };
                Object.values(detValMap).forEach(function(stdName) {
                    _setBombCheckboxByName(toForm, stdName, false);
                });
                pdfDetChecks.forEach(function(cb) {
                    if (cb.checked && detValMap[cb.value]) {
                        _setBombCheckboxByName(toForm, detValMap[cb.value], true);
                    }
                });
                _sv('bomb_det_trap_text', 'detonate_trap_detail');
                _sv('bomb_det_wire_color', 'detonate_wire_color');
                _sv('bomb_det_wire_len', 'detonate_wire_length');
                _sv('bomb_det_radio_brand', 'detonate_radio_brand');
                _sv('bomb_det_radio_model', 'detonate_radio_model');
                _sv('bomb_det_radio_color', 'detonate_radio_color');
                _sv('bomb_det_radio_sn', 'detonate_radio_sn');
                _sv('bomb_det_phone_brand', 'detonate_phone_brand');
                _sv('bomb_det_phone_model', 'detonate_phone_model');
                _sv('bomb_det_phone_color', 'detonate_phone_color');
                _sv('bomb_det_phone_sn', 'detonate_phone_sn');
                _sv('bomb_det_remote_text', 'detonate_remote_detail');
                _sv('bomb_det_timer_text', 'detonate_timer_detail');
                _sv('bomb_det_other_text', 'detonate_other_detail');
            }

            // ★ Fragments: PDF bomb_frag[] (legacy) → Standard individual checkboxes
            if (hasLegacyFragments) {
                var pdfFragChecks = fromForm.querySelectorAll('input[name="bomb_frag[]"]');
                var fragValMap = {
                    'เหล็กเส้นตัดท่อน': 'fragment_rebar',
                    'ตะปู': 'fragment_nail',
                    'อื่นๆ': 'fragment_other'
                };
                Object.values(fragValMap).forEach(function(stdName) {
                    _setBombCheckboxByName(toForm, stdName, false);
                });
                pdfFragChecks.forEach(function(cb) {
                    if (cb.checked && fragValMap[cb.value]) {
                        _setBombCheckboxByName(toForm, fragValMap[cb.value], true);
                    }
                });
                _sv('bomb_frag_rebar_size', 'fragment_rebar_size');
                _sv('bomb_frag_rebar_len', 'fragment_rebar_length');
                _sv('bomb_frag_nail_size', 'fragment_nail_size');
                _sv('bomb_frag_other_text', 'fragment_other_detail');
            }

            // ★ Components: PDF bomb_comp[] (legacy) → Standard individual checkboxes
            if (hasLegacyComponents) {
                var pdfCompChecks = fromForm.querySelectorAll('input[name="bomb_comp[]"]');
                var compValMap = {
                    'หลอดดินขยาย': 'comp_booster',
                    'เชื้อปะทุไฟฟ้า': 'comp_detonator',
                    'เทปพันสายไฟ': 'comp_tape',
                    'ซิมการ์ด': 'comp_sim',
                    'วงจรการจุดระเบิด': 'comp_circuit',
                    'แบตเตอรี่': 'comp_battery',
                    'แผงวงจร DTMF': 'comp_dtmf',
                    'แผงวงจร': 'comp_pcb',
                    'สายไฟวงจร': 'comp_wire',
                    'กล่องบรรจุวงจร': 'comp_box',
                    'นาฬิกา': 'comp_clock',
                    'กระเดื่อง': 'comp_lever',
                    'สลักนิรภัย': 'comp_pin',
                    'อื่นๆ': 'comp_misc_other'
                };
                Object.values(compValMap).forEach(function(stdName) {
                    _setBombCheckboxByName(toForm, stdName, false);
                });
                pdfCompChecks.forEach(function(cb) {
                    if (cb.checked && compValMap[cb.value]) {
                        _setBombCheckboxByName(toForm, compValMap[cb.value], true);
                    }
                });
                _sv('bomb_comp_booster_text', 'comp_booster_detail');
                _sv('bomb_comp_detonator_text', 'comp_detonator_detail');
                _sv('bomb_comp_tape_text', 'comp_tape_detail');
                _sv('bomb_comp_sim_text', 'comp_sim_detail');
                _sv('bomb_comp_circuit_text', 'comp_circuit_detail');
                _sv('bomb_comp_battery_text', 'comp_battery_detail');
                _sv('bomb_comp_battery_v', 'comp_battery_voltage');
                _sv('bomb_comp_dtmf_text', 'comp_dtmf_detail');
                _sv('bomb_comp_pcb_text', 'comp_pcb_detail');
                _sv('bomb_comp_wire_text', 'comp_wire_detail');
                _sv('bomb_comp_box_text', 'comp_box_detail');
                _sv('bomb_comp_clock_text', 'comp_clock_detail');
                _sv('bomb_comp_lever_text', 'comp_lever_detail');
                _sv('bomb_comp_pin_text', 'comp_pin_detail');
                _sv('bomb_comp_other_text', 'comp_misc_other_detail');
            }

            // ★ Container other text
            _sv('bomb_cont_other_text', 'bomb_container_other_text');

            // ★ Bomb evidence master: auto-check if any sub-checkbox is checked
            var hasAnyBomb = false;
            fromForm.querySelectorAll('input[name="bomb_containers[]"], input[name="bomb_detonation[]"], input[name="bomb_frag[]"], input[name="bomb_comp[]"]').forEach(function(cb) {
                if (cb.checked) hasAnyBomb = true;
            });
            _setBombCheckboxByName(toForm, 'has_bomb_evidence', hasAnyBomb);

            // ★ Blood evidence
            _setBombCheckboxByName(toForm, 'evidence_blood_stain', _isBombCheckedAny(fromForm, ['evidence_blood_stain', 'bomb_blood_active']));
            var _bloodCombined = _joinPrimarySecondary(
                _getBombFieldValueAny(fromForm, ['blood_stain_detail', 'bomb_blood_detail']),
                _getBombFieldValueAny(fromForm, ['blood_stain_detail_2', 'bomb_blood_detail_2'])
            );
            var _stdBlood = toForm.querySelector('[name="blood_stain_detail"]');
            if (_stdBlood) _stdBlood.value = _bloodCombined;
            _setBombCheckboxByName(toForm, 'test_hemastix', _isBombCheckedAny(fromForm, ['test_hemastix', 'bomb_test_hema']));
            _setBombCheckboxByName(toForm, 'test_phenolphthalein', _isBombCheckedAny(fromForm, ['test_phenolphthalein', 'bomb_test_phenol']));
            // hemastix/phenol results: รองรับทั้งค่าเก่า (positive/negative) และค่าจริงภาษาไทย
            var _hemaPdfToStd = {
                'positive': 'มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน',
                'negative': 'ไม่มีการเปลี่ยนแปลง',
                'มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน': 'มีการเปลี่ยนแปลงเป็นสีเขียวแกมน้ำเงิน',
                'ไม่มีการเปลี่ยนแปลง': 'ไม่มีการเปลี่ยนแปลง',
                'ไม่เกิดการเปลี่ยนแปลง': 'ไม่มีการเปลี่ยนแปลง'
            };
            var _phenolPdfToStd = {
                'positive': 'มีการเปลี่ยนแปลงเป็นสีชมพูในทันที',
                'negative': 'ไม่มีการเปลี่ยนแปลง',
                'มีการเปลี่ยนแปลงเป็นสีชมพูในทันที': 'มีการเปลี่ยนแปลงเป็นสีชมพูในทันที',
                'ไม่มีการเปลี่ยนแปลง': 'ไม่มีการเปลี่ยนแปลง',
                'ไม่เกิดการเปลี่ยนแปลง': 'ไม่มีการเปลี่ยนแปลง'
            };
            var pdfHemaResult = '';
            fromForm.querySelectorAll('input[name="hemastix_result"], input[name="bomb_hema_result"]').forEach(function(cb) {
                if (cb.checked) pdfHemaResult = cb.value;
            });
            var stdHemaVal = _hemaPdfToStd[pdfHemaResult] || pdfHemaResult;
            toForm.querySelectorAll('input[name="hemastix_result"]').forEach(function(r) {
                r.checked = (r.value === stdHemaVal);
            });
            var pdfPhenolResult = '';
            fromForm.querySelectorAll('input[name="phenol_result"], input[name="bomb_phenol_result"]').forEach(function(cb) {
                if (cb.checked) pdfPhenolResult = cb.value;
            });
            var stdPhenolVal = _phenolPdfToStd[pdfPhenolResult] || pdfPhenolResult;
            toForm.querySelectorAll('input[name="phenol_result"]').forEach(function(r) {
                r.checked = (r.value === stdPhenolVal);
            });

            // ★ Other evidence
            _setBombCheckboxByName(toForm, 'has_evidence_other', _isBombCheckedAny(fromForm, ['has_evidence_other', 'bomb_other_ev_active']));
            var _otherCombined = _joinPrimarySecondary(
                _getBombFieldValueAny(fromForm, ['evidence_other_detail', 'bomb_other_ev_detail']),
                _getBombFieldValueAny(fromForm, ['evidence_other_detail_2', 'bomb_other_ev_detail_2'])
            );
            var _stdOther = toForm.querySelector('[name="evidence_other_detail"]');
            if (_stdOther) _stdOther.value = _otherCombined;

            _refreshBombStandardUi();
            return;
        }

        if (toId === 'incidentCheckListFormBomb') {
            _refreshBombStandardUi();
        }
    }

    function _refreshBombStandardUi() {
        $('#check_outdoor_main, #check_indoor_main').trigger('change');
        if (typeof toggleBloodSection === 'function') toggleBloodSection();
        if (typeof syncBloodSubTests === 'function') syncBloodSubTests();
    }

    // ===== LIFE: สลับจากฟอร์มมาตรฐาน → PDF =====
    function switchToLifePdfForm() {
        // sync ข้อมูลจากฟอร์มมาตรฐาน → PDF
        syncLifeFormData('incidentCheckListFormLife', 'lifeFormPdf');

        // sync dynamic rows (inspector, victim, evidence, measurement)
        _syncLifeInspectorToPdf();
        _syncLifeVictimToPdf();
        _syncLifeEvidenceToPdf();
        _syncLifeMeasurementToPdf();

        // sync ข้อมูล display (เลขที่เอกสาร/เลขรายงาน) ไปยัง PDF
        const docNo = $('#doc_no_life').val() || '';
        const rptNo = $('#report_no_life').val() || '';
        const rcvId = $('#receiveNoti_id_life').val() || '';
        $('#lpf_doc_no').val(docNo);
        $('#lpf_report_no').val(rptNo);
        $('#lpf_receiveNoti_id').val(rcvId);
        $('#lpf_case_doc_no').val(thaiDocNo($('#case_doc_no_life').val() || docNo));
        var lpfRptParts = (rptNo || '').split('/');
        $('#lpf_report_no_display').text(thaiReportNo(lpfRptParts[0] || ''));

        // ★ Capture canvas data ก่อนปิด modal มาตรฐาน
        var canvasCopyData = {};
        var stdCanvasIds = {
            'scene_sketch': 'scene_sketch_canvas_life',
            'body_diagram': 'body_diagram_canvas_life',
            'receiver_signature': 'sig-canvas-receiver-life',
            'sender_signature': 'sig-canvas-sender-life'
        };
        var pdfCanvasIds = {
            'scene_sketch': 'lpf_scene_sketch_canvas',
            'body_diagram': 'lpf_body_diagram_canvas',
            'receiver_signature': 'lpf_sig_receiver',
            'sender_signature': 'lpf_sig_sender'
        };
        Object.keys(stdCanvasIds).forEach(function(key) {
            var srcCvs = document.getElementById(stdCanvasIds[key]);
            if (srcCvs && srcCvs.width > 0 && srcCvs.height > 0) {
                try {
                    canvasCopyData[key] = srcCvs.toDataURL('image/png');
                } catch (e) {}
            }
        });

        // ปิด modal มาตรฐาน
        const stdEl = document.getElementById('addCheckListModalLife');
        const stdModal = bootstrap.Modal.getInstance(stdEl);
        if (stdModal) stdModal.hide();

        stdEl.addEventListener('hidden.bs.modal', function onHidden() {
            stdEl.removeEventListener('hidden.bs.modal', onHidden);
            const pdfEl = document.getElementById('lifeFormPdfModal');
            if (pdfEl) {
                pdfEl.addEventListener('shown.bs.modal', function onShown() {
                    pdfEl.removeEventListener('shown.bs.modal', onShown);
                    setTimeout(function() {
                        Object.keys(pdfCanvasIds).forEach(function(key) {
                            if (canvasCopyData[key]) {
                                var dst = document.getElementById(pdfCanvasIds[key]);
                                if (dst) {
                                    var img = new Image();
                                    img.onload = function() {
                                        var ctx = dst.getContext('2d');
                                        ctx.clearRect(0, 0, dst.width, dst.height);
                                        ctx.drawImage(img, 0, 0, dst.width, dst.height);
                                    };
                                    img.src = canvasCopyData[key];
                                }
                            }
                        });
                        if (typeof lpfRenderPhotosFromStore === 'function') lpfRenderPhotosFromStore();
                    }, 150);
                });
                bootstrap.Modal.getOrCreateInstance(pdfEl).show();
            }
        });
    }

    // ===== LIFE: สลับจาก PDF → ฟอร์มมาตรฐาน =====
    function switchToLifeStandardForm() {
        // sync ข้อมูลจาก PDF → ฟอร์มมาตรฐาน
        syncLifeFormData('lifeFormPdf', 'incidentCheckListFormLife');

        // sync dynamic rows (inspector, victim, evidence, measurement) → standard
        _syncLifeInspectorToStd();
        _syncLifeVictimToStd();
        _syncLifeEvidenceToStd();
        _syncLifeMeasurementToStd();

        // sync ข้อมูล display กลับไปยังฟอร์มมาตรฐาน
        const docNo = $('#lpf_doc_no').val() || '';
        const rptNo = $('#lpf_report_no').val() || '';
        const rcvId = $('#lpf_receiveNoti_id').val() || '';
        $('#doc_no_life').val(docNo);
        $('#report_no_life').val(rptNo);
        $('#receiveNoti_id_life').val(rcvId);
        $('#receiveNoti_No_life').text(thaiDocNo(docNo));
        $('#receiveNotiReportNo_life').text(thaiReportNo(rptNo));
        $('#case_doc_no_life').val(thaiDocNo($('#lpf_case_doc_no').val() || docNo));

        // ★ Capture canvas data จาก PDF form
        var canvasCopyData = {};
        var pdfCanvasIds = {
            'scene_sketch': 'lpf_scene_sketch_canvas',
            'body_diagram': 'lpf_body_diagram_canvas',
            'receiver_signature': 'lpf_sig_receiver',
            'sender_signature': 'lpf_sig_sender'
        };
        var stdCanvasIds = {
            'scene_sketch': 'scene_sketch_canvas_life',
            'body_diagram': 'body_diagram_canvas_life',
            'receiver_signature': 'sig-canvas-receiver-life',
            'sender_signature': 'sig-canvas-sender-life'
        };
        Object.keys(pdfCanvasIds).forEach(function(key) {
            var srcCvs = document.getElementById(pdfCanvasIds[key]);
            if (srcCvs && srcCvs.width > 0 && srcCvs.height > 0) {
                try {
                    canvasCopyData[key] = srcCvs.toDataURL('image/png');
                } catch (e) {}
            }
        });

        // ปิด PDF modal
        const pdfEl = document.getElementById('lifeFormPdfModal');
        const pdfModal = bootstrap.Modal.getInstance(pdfEl);
        if (pdfModal) pdfModal.hide();

        pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
            pdfEl.removeEventListener('hidden.bs.modal', onHidden);
            const stdEl = document.getElementById('addCheckListModalLife');
            if (stdEl) {
                stdEl.addEventListener('shown.bs.modal', function onShown() {
                    stdEl.removeEventListener('shown.bs.modal', onShown);
                    setTimeout(function() {
                        Object.keys(stdCanvasIds).forEach(function(key) {
                            if (canvasCopyData[key]) {
                                var dst = document.getElementById(stdCanvasIds[key]);
                                if (dst) {
                                    var img = new Image();
                                    img.onload = function() {
                                        var ctx = dst.getContext('2d');
                                        ctx.clearRect(0, 0, dst.width, dst.height);
                                        ctx.drawImage(img, 0, 0, dst.width, dst.height);
                                    };
                                    img.src = canvasCopyData[key];
                                }
                            }
                        });
                    }, 150);
                });
                bootstrap.Modal.getOrCreateInstance(stdEl).show();
            }
        });
    }

    // ===== LIFE: Sync form data between standard <-> PDF =====
    function syncLifeFormData(fromFormId, toFormId) {
        const fromForm = document.getElementById(fromFormId);
        const toForm = document.getElementById(toFormId);
        if (!fromForm || !toForm) return;

        const fromElements = fromForm.querySelectorAll('input, select, textarea');
        const dataMap = {};

        fromElements.forEach(el => {
            const name = el.name;
            if (!name) return;
            if (el.type === 'checkbox') {
                if (!dataMap[name]) dataMap[name] = {
                    type: 'checkbox',
                    values: []
                };
                if (el.checked) dataMap[name].values.push(el.value);
            } else if (el.type === 'radio') {
                if (el.checked) dataMap[name] = {
                    type: 'radio',
                    value: el.value
                };
            } else if (el.type === 'file') {
                return;
            } else {
                if (!dataMap[name]) dataMap[name] = {
                    type: 'value',
                    values: []
                };
                dataMap[name].values.push(el.value);
            }
        });

        Object.keys(dataMap).forEach(name => {
            const info = dataMap[name];
            const toEls = Array.from(toForm.querySelectorAll('[name="' + name + '"]'));
            if (toEls.length === 0) return;

            if (info.type === 'checkbox') {
                const checkboxEls = toEls.filter(el => el.type === 'checkbox');
                if (checkboxEls.length === 0) return;
                checkboxEls.forEach(el => {
                    el.checked = info.values.includes(el.value);
                });
            } else if (info.type === 'radio') {
                const radioEls = toEls.filter(el => el.type === 'radio');
                if (radioEls.length === 0) return;
                radioEls.forEach(el => {
                    el.checked = (el.value === info.value);
                });
            } else {
                const valueEls = toEls.filter(el => el.type !== 'checkbox' && el.type !== 'radio' && el.type !== 'file');
                valueEls.forEach((el, idx) => {
                    if (idx < info.values.length) el.value = info.values[idx];
                });
            }
        });

        _syncLifeSpecialFields(fromForm, toForm);
    }

    function _getLifeFieldValue(form, name) {
        if (!form) return '';
        const field = form.querySelector('[name="' + name + '"]');
        return field ? (field.value || '').trim() : '';
    }

    function _hasLifeCheckedValue(form, name, value) {
        return !!form.querySelector('[name="' + name + '"][value="' + value + '"]:checked');
    }

    function _setLifeCheckboxByName(form, name, checked) {
        const field = form.querySelector('[name="' + name + '"]');
        if (field && field.type === 'checkbox') {
            field.checked = !!checked;
        }
    }

    function _setLifeCheckboxValue(form, name, value, checked) {
        const field = form.querySelector('[name="' + name + '"][value="' + value + '"]');
        if (field && field.type === 'checkbox') {
            field.checked = !!checked;
        }
    }

    function _refreshLifeStandardUi() {
        $('#check_outdoor_main_life, #check_indoor_main_life').trigger('change');
        $('#life_scene_no, #life_case_other, #life_notify_other, #life_light_other, #life_temp_other, #life_outdoor_other, #life_bld_other').trigger('change');
        if (typeof window.toggleLifeBloodTests === 'function') {
            window.toggleLifeBloodTests();
        }
    }

    function _syncLifeSpecialFields(fromForm, toForm) {
        const fromId = fromForm ? (fromForm.id || '') : '';
        const toId = toForm ? (toForm.id || '') : '';

        if (fromId === 'incidentCheckListFormLife' && toId === 'lifeFormPdf') {
            const structureWall = _getLifeFieldValue(fromForm, 'structure_wall');
            const hasNoWall = /^ไม่มี/.test(structureWall);

            _setLifeCheckboxByName(toForm, 'other_evidence_check', _getLifeFieldValue(fromForm, 'other_evidence') !== '');
            _setLifeCheckboxValue(toForm, 'entrance_exit_check', 'ทางเข้า - ทางออก', _getLifeFieldValue(fromForm, 'entrance_exit') !== '');
            _setLifeCheckboxValue(toForm, 'entrance_exit_check2[]', 'ทางเข้า - ทางออก', _getLifeFieldValue(fromForm, 'entrance_exit_detail') !== '');
            _setLifeCheckboxValue(toForm, 'body_status', 'ตำแหน่งที่พบศพ', _getLifeFieldValue(fromForm, 'body_location') !== '' || _getLifeFieldValue(fromForm, 'body_location_2') !== '');
            _setLifeCheckboxByName(toForm, 'structure_type_check', _getLifeFieldValue(fromForm, 'structure_type') !== '');
            _setLifeCheckboxValue(toForm, 'structure_wall_option', 'ไม่มี', hasNoWall);
            _setLifeCheckboxValue(toForm, 'structure_wall_option', 'ผนัง', structureWall !== '' && !hasNoWall);
            _setLifeCheckboxByName(toForm, 'structure_floor_check', _getLifeFieldValue(fromForm, 'structure_floor') !== '');
            _setLifeCheckboxByName(toForm, 'structure_roof_check', _getLifeFieldValue(fromForm, 'structure_roof') !== '');
            _setLifeCheckboxByName(toForm, 'structure_arrangement_check', _getLifeFieldValue(fromForm, 'structure_arrangement') !== '');

            // sync measurement date/time: datetime-local → separate date + time fields
            const stdMeasurementDate = _getLifeFieldValue(fromForm, 'measurement_inspection_date_life');
            const pdfMeasurementDate = toForm.querySelector('[name="measurement_inspection_date_life"]');
            if (pdfMeasurementDate) {
                pdfMeasurementDate.value = stdMeasurementDate ? stdMeasurementDate.split('T')[0] : '';
            }
            const pdfMeasurementTime = toForm.querySelector('[name="measurement_inspection_time_life"]');
            if (pdfMeasurementTime && stdMeasurementDate && stdMeasurementDate.indexOf('T') !== -1) {
                pdfMeasurementTime.value = stdMeasurementDate.split('T')[1] || '';
            }

            // sync photographer datetime: datetime-local → separate date + time fields
            const stdPhotoDatetime = _getLifeFieldValue(fromForm, 'photographer_datetime_life');
            const pdfPhotoDate = toForm.querySelector('[name="photo_inspect_date_life"]');
            const pdfPhotoTime = toForm.querySelector('[name="photo_inspect_time_life"]');
            if (pdfPhotoDate && stdPhotoDatetime) {
                pdfPhotoDate.value = stdPhotoDatetime.split('T')[0] || '';
            }
            if (pdfPhotoTime && stdPhotoDatetime && stdPhotoDatetime.indexOf('T') !== -1) {
                pdfPhotoTime.value = stdPhotoDatetime.split('T')[1] || '';
            }

            // sync evidence labels from standard → PDF
            _syncLifeEvidenceLabelsToPdf(fromForm, toForm);
            return;
        }

        if (fromId === 'lifeFormPdf' && toId === 'incidentCheckListFormLife') {
            if (_hasLifeCheckedValue(fromForm, 'structure_wall_option', 'ไม่มี') && !_getLifeFieldValue(fromForm, 'structure_wall')) {
                const structureWallField = toForm.querySelector('[name="structure_wall"]');
                if (structureWallField) {
                    structureWallField.value = 'ไม่มี';
                }
            }

            // sync measurement date/time: separate date + time fields → datetime-local
            const pdfMeasurementDate = _getLifeFieldValue(fromForm, 'measurement_inspection_date_life');
            const pdfMeasurementTime = _getLifeFieldValue(fromForm, 'measurement_inspection_time_life');
            const stdMeasurementDate = toForm.querySelector('[name="measurement_inspection_date_life"]');
            if (stdMeasurementDate) {
                if (pdfMeasurementDate) {
                    const timePart = pdfMeasurementTime || '00:00';
                    stdMeasurementDate.value = pdfMeasurementDate + 'T' + timePart;
                } else {
                    stdMeasurementDate.value = '';
                }
            }
            // sync hidden time field for standard form
            const stdMeasurementTimeHidden = toForm.querySelector('[name="measurement_inspection_time_life"]');
            if (stdMeasurementTimeHidden) {
                stdMeasurementTimeHidden.value = pdfMeasurementTime || '';
            }

            // sync photographer datetime: separate date + time fields → datetime-local
            const pdfPhotoDate = _getLifeFieldValue(fromForm, 'photo_inspect_date_life');
            const pdfPhotoTime = _getLifeFieldValue(fromForm, 'photo_inspect_time_life');
            const stdPhotoDatetime = toForm.querySelector('[name="photographer_datetime_life"]');
            if (stdPhotoDatetime) {
                if (pdfPhotoDate) {
                    const timePart = pdfPhotoTime || '00:00';
                    stdPhotoDatetime.value = pdfPhotoDate + 'T' + timePart;
                } else {
                    stdPhotoDatetime.value = '';
                }
            }
            // sync hidden fields for standard form
            const stdPhotoDateHidden = toForm.querySelector('[name="photo_inspect_date_life"]');
            const stdPhotoTimeHidden = toForm.querySelector('[name="photo_inspect_time_life"]');
            if (stdPhotoDateHidden) stdPhotoDateHidden.value = pdfPhotoDate || '';
            if (stdPhotoTimeHidden) stdPhotoTimeHidden.value = pdfPhotoTime || '';

            // sync evidence labels from PDF → standard
            _syncLifeEvidenceLabelsToStd(fromForm, toForm);

            _refreshLifeStandardUi();
            return;
        }

        if (toId === 'incidentCheckListFormLife') {
            _refreshLifeStandardUi();
        }
    }

    // ===== LIFE: Sync Evidence Labels → PDF =====
    function _syncLifeEvidenceLabelsToPdf(fromForm, toForm) {
        // Evidence labels are dynamically created, sync them via the evidence sync functions
        // This is handled by _syncLifeEvidenceToPdf already
    }

    // ===== LIFE: Sync Evidence Labels → Standard =====
    function _syncLifeEvidenceLabelsToStd(fromForm, toForm) {
        // Evidence labels are dynamically created, sync them via the evidence sync functions
        // This is handled by _syncLifeEvidenceToStd already
    }

    // ===== LIFE: Sync Inspector rows → PDF =====
    function _syncLifeInspectorToPdf() {
        const stdForm = document.getElementById('incidentCheckListFormLife');
        if (!stdForm) return;
        const srcSelects = stdForm.querySelectorAll('[name="inspector_id[]"]');
        const container = document.getElementById('lpf_inspector_container');
        if (!container) return;

        const existingSel = container.querySelector('select');
        const optionsHtml = existingSel ? existingSel.innerHTML : (srcSelects[0] ? srcSelects[0].innerHTML : '');
        container.innerHTML = '';

        const count = Math.max(srcSelects.length, 1);
        for (let i = 0; i < count; i++) {
            const row = document.createElement('div');
            row.className = 'lpf-si lpf-inspector-row';
            row.innerHTML = '<span class="lpf-si-no">5.' + (i + 1) + '</span>' +
                '<select class="lpf-sel" name="inspector_id[]">' + optionsHtml + '</select>' +
                (i > 0 ? ' <button type="button" class="lpf-del-btn" onclick="this.parentElement.remove(); if (typeof lpfRenumberInspectors === \'function\') lpfRenumberInspectors();">×</button>' : '');
            container.appendChild(row);
            if (i < srcSelects.length && srcSelects[i].value) {
                row.querySelector('select').value = srcSelects[i].value;
            }
        }

        if (typeof window.lpfRenumberInspectors === 'function') {
            window.lpfRenumberInspectors();
        }
    }

    // ===== LIFE: Sync Victim rows → PDF =====
    function _syncLifeVictimToPdf() {
        const $stdVictims = $('#victim_container_life .victim-card-life');
        const container = document.getElementById('lpf_victim_container');
        if (!container) return;

        const victims = [];
        $stdVictims.each(function(_, card) {
            const $card = $(card);
            const type = ($card.find('select[name="victim_type_life[]"]').val() || '').trim();
            const name = ($card.find('input[name="victim_name_life[]"]').val() || '').trim();
            const age = ($card.find('input[name="victim_age_life[]"]').val() || '').trim();
            if (!type && !name && !age) return;
            victims.push({
                type,
                name,
                age
            });
        });

        if (!victims.length) {
            victims.push({
                type: '',
                name: '',
                age: ''
            });
        }

        container.innerHTML = '';
        victims.forEach(function(v, idx) {
            const row = document.createElement('div');
            row.className = 'lpf-victim-row';
            row.style.cssText = 'margin-top:3px; padding:2px 0; border-top:1px dotted #ccc;';
            row.innerHTML = '<div class="lpf-fr">' +
                '<span class="lpf-fl">ประเภท</span>' +
                '<select class="lpf-sel" name="victim_type_life[]" style="max-width:100px;">' +
                '<option value=""' + (!v.type ? ' selected' : '') + '>--เลือก--</option>' +
                '<option value="ผู้เสียชีวิต"' + (v.type === 'ผู้เสียชีวิต' ? ' selected' : '') + '>ผู้เสียชีวิต</option>' +
                '<option value="ผู้บาดเจ็บ"' + (v.type === 'ผู้บาดเจ็บ' ? ' selected' : '') + '>ผู้บาดเจ็บ</option>' +
                '<option value="ผู้สูญหาย"' + (v.type === 'ผู้สูญหาย' ? ' selected' : '') + '>ผู้สูญหาย</option>' +
                '</select>' +
                '<span class="lpf-fl" style="margin-left:6px;">ชื่อ</span>' +
                '<input type="text" class="lpf-inp" name="victim_name_life[]" value="' + (v.name || '').replace(/"/g, '&quot;') + '">' +
                '<span class="lpf-fl" style="margin-left:4px;">อายุ</span>' +
                '<input type="text" class="lpf-inp-s" name="victim_age_life[]" style="max-width:30px;" value="' + (v.age || '').replace(/"/g, '&quot;') + '">' +
                '<span class="lpf-fl">ปี</span>' +
                (idx > 0 ? ' <button type="button" class="lpf-del-btn" onclick="this.closest(\'.lpf-victim-row\').remove()">×</button>' : '') +
                '</div>';
            container.appendChild(row);
        });
    }

    // ===== LIFE: Sync Evidence rows → PDF =====
    function _syncLifeEvidenceToPdf() {
        const $stdCards = $('#evidence_container_life .evidence-card-life');
        const tbody = document.getElementById('lpf_evidence_tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!$stdCards.length) return;

        $stdCards.each(function(idx, card) {
            const $card = $(card);
            const item = $card.find('input[name="evidence_item_life[]"]').val() || '';
            const azimuth = $card.find('input[name="evidence_azimuth_life[]"]').val() || '';
            const remark = $card.find('input[name="evidence_remark_life[]"]').val() || '';
            const labUnit = window.getLabUnitsString($card.find('[name="evidence_lab_unit_life[]"]'));

            // ระยะห่าง (m) จากจุดอ้างอิง 1-4 (text input)
            const lvl1 = $card.find('input[name^="evidence_level_1_life_"]').val() || '';
            const lvl2 = $card.find('input[name^="evidence_level_2_life_"]').val() || '';
            const lvl3 = $card.find('input[name^="evidence_level_3_life_"]').val() || '';
            const lvl4 = $card.find('input[name^="evidence_level_4_life_"]').val() || '';

            const labUnitOptions = '<option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option>';

            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td><input type="text" name="evidence_label_life[]" style="width:35px;" value="' + (idx + 1) + '"></td>' +
                '<td><input type="text" name="evidence_item_life[]" value="' + item.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="text" name="evidence_level_1_life_' + idx + '" style="width:28px;" value="' + String(lvl1).replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="text" name="evidence_level_2_life_' + idx + '" style="width:28px;" value="' + String(lvl2).replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="text" name="evidence_level_3_life_' + idx + '" style="width:28px;" value="' + String(lvl3).replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="text" name="evidence_level_4_life_' + idx + '" style="width:28px;" value="' + String(lvl4).replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="text" name="evidence_azimuth_life[]" value="' + azimuth.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="text" name="evidence_remark_life[]" value="' + remark.replace(/"/g, '&quot;') + '"></td>' +
                '<td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;">' + labUnitOptions + '</select><input type="hidden" class="lab-unit-value" name="evidence_lab_unit_life[]" value=""></td>' +
                '<td><button type="button" class="lpf-del-btn" onclick="lpfDelRow(this)">×</button></td>';
            tbody.appendChild(tr);
            window.setLabUnits($(tr).find('[name="evidence_lab_unit_life[]"]'), labUnit);
        });
    }

    // ===== LIFE: Sync Measurement rows → PDF =====
    function _syncLifeMeasurementToPdf() {
        const $stdCards = $('#measurement_container_life .measurement-card-life');
        const tbody = document.getElementById('lpf_collection_tbody');
        if (!tbody) return;

        // ตรวจสอบว่า measurement cards มีข้อมูลจริงหรือไม่
        let hasMeasurementData = false;
        $stdCards.each(function() {
            const item = $(this).find('input[name="measurement_item_life[]"]').val() || '';
            if (item.trim() !== '') hasMeasurementData = true;
        });

        // Evidence table กับ Collection table เป็นคนละส่วนกัน ไม่ cross-sync
        if (!hasMeasurementData) return;

        tbody.innerHTML = '';

        $stdCards.each(function(idx, card) {
            const $card = $(card);
            const item = $card.find('input[name="measurement_item_life[]"]').val() || '';
            const qty = $card.find('input[name="measurement_quantity_life[]"]').val() || '';
            const area = $card.find('input[name="measurement_area_life[]"]').val() || '';
            const labelNo = $card.find('input[name="measurement_label_number_life[]"]').val() || '';
            const remarkM = $card.find('input[name="measurement_remark_life[]"]').val() || '';
            const forensicUnit = window.getLabUnitsString($card.find('[name="measurement_forensic_unit_life[]"]'));

            // checkboxes
            const pkgPlastic = $card.find('input[name^="measurement_package_plastic_check_"]').is(':checked');
            const pkgPaper = $card.find('input[name^="measurement_package_paper_check_"]').is(':checked');
            const pkgOther = $card.find('input[name^="measurement_package_other_check_"]').is(':checked');
            const actReturn = $card.find('input[name^="measurement_action_return_check_"]').is(':checked');
            const actOther = $card.find('input[name^="measurement_action_other_check_"]').is(':checked');

            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td style="text-align:center;">' + (idx + 1) + '</td>' +
                '<td><input type="text" name="measurement_item_life[]" value="' + item.replace(/"/g, '&quot;') + '"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
                '<td><input type="text" name="measurement_quantity_life[]" style="width:30px; text-align:center;" value="' + qty.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="text" name="measurement_area_life[]" value="' + area.replace(/"/g, '&quot;') + '"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
                '<td><input type="text" name="measurement_label_number_life[]" style="width:30px; text-align:center;" value="' + labelNo.replace(/"/g, '&quot;') + '"></td>' +
                '<td><input type="checkbox" name="measurement_package_plastic_check_' + idx + '" value="1"' + (pkgPlastic ? ' checked' : '') + '></td>' +
                '<td><input type="checkbox" name="measurement_package_paper_check_' + idx + '" value="1"' + (pkgPaper ? ' checked' : '') + '></td>' +
                '<td><input type="checkbox" name="measurement_package_other_check_' + idx + '" value="1"' + (pkgOther ? ' checked' : '') + '></td>' +
                '<td><input type="checkbox" name="measurement_action_return_check_' + idx + '" value="1"' + (actReturn ? ' checked' : '') + '></td>' +
                '<td><input type="checkbox" name="measurement_action_other_check_' + idx + '" value="1"' + (actOther ? ' checked' : '') + '></td>' +
                '<td><input type="text" name="measurement_remark_life[]" value="' + remarkM.replace(/"/g, '&quot;') + '"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
                '<td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option></select><input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_life[]" value=""></td>' +
                '<td><button type="button" class="lpf-del-btn" onclick="lpfDelRow(this)">×</button></td>';
            tbody.appendChild(tr);
            window.setLabUnits($(tr).find('[name="measurement_forensic_unit_life[]"]'), forensicUnit);
        });
    }

    // ========== LIFE: Reverse Sync (PDF → มาตรฐาน) ==========

    // ---------- Life Inspector: PDF → มาตรฐาน ----------
    function _syncLifeInspectorToStd() {
        const pdfForm = document.getElementById('lifeFormPdf');
        if (!pdfForm) return;
        const srcSelects = pdfForm.querySelectorAll('[name="inspector_id[]"]');
        const container = document.getElementById('inspector_container_life');
        if (!container) return;

        // ลบ inspector rows เดิม (ทำลาย Select2 ก่อน)
        $('#inspector_container_life .inspector-select-life').each(function() {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).select2('destroy');
            }
        });
        $('#inspector_container_life').empty();

        const count = Math.max(srcSelects.length, 1);
        for (let i = 0; i < count; i++) {
            const deleteBtn = (i > 0) ? `
                <div class="ms-2">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0 rounded-circle remove-inspector-btn-life d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" title="ลบ">
                        <i class="fas fa-times"></i>
                    </button>
                </div>` : '<div class="ms-2" style="width: 32px;"></div>';
            const rowHtml = `
                <div class="d-flex align-items-center mb-3 inspector-row-life">
                    <div class="text-end pe-3" style="width: 50px;">
                        <span class="fw-bold text-secondary index-label">5.${i + 1}</span>
                    </div>
                    <div class="flex-grow-1">
                        <select class="form-select inspector-select-life" name="inspector_id[]">
                            ${inspectorOptionsHTMLLife}
                        </select>
                    </div>
                    ${deleteBtn}
                </div>`;
            $('#inspector_container_life').append(rowHtml);
        }

        // Re-init Select2 แล้วตั้งค่าจาก PDF form
        $('#inspector_container_life .inspector-select-life').each(function(idx) {
            $(this).select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'กรุณาเลือก',
                allowClear: true,
                dropdownParent: $('#addCheckListModalLife'),
                dropdownAutoWidth: true
            });
            if (idx < srcSelects.length && srcSelects[idx].value) {
                $(this).val(srcSelects[idx].value).trigger('change');
            }
        });
    }

    // ---------- Life Victim: PDF → มาตรฐาน ----------
    function _syncLifeVictimToStd() {
        const pdfRows = document.querySelectorAll('#lpf_victim_container .lpf-victim-row');
        const container = document.getElementById('victim_container_life');
        if (!container || !pdfRows.length) return;
        container.innerHTML = '';

        let cardIdx = 0;
        pdfRows.forEach(function(row) {
            const typeSel = row.querySelector('select[name="victim_type_life[]"], select[name="victim_type_extra_life[]"]');
            const checkedType = row.querySelector('input[name="victim_type_life[]"]:checked');

            let type = typeSel ? (typeSel.value || '') : '';
            const nameInp = row.querySelector('input[name="victim_name_life[]"], input[name="victim_extra_name_life[]"], input[name="victim_injured_name_life[]"], input[name="victim_missing_name_life[]"]');
            const ageInp = row.querySelector('input[name="victim_age_life[]"], input[name="victim_extra_age_life[]"], input[name="victim_injured_age_life[]"], input[name="victim_missing_age_life[]"]');
            const name = nameInp ? (nameInp.value || '') : '';
            const age = ageInp ? (ageInp.value || '') : '';

            if (!type && checkedType) {
                type = checkedType.value || '';
            }
            if (!type) {
                if (row.querySelector('input[name="victim_injured_name_life[]"], input[name="victim_injured_age_life[]"]')) {
                    type = 'ผู้บาดเจ็บ';
                } else if (row.querySelector('input[name="victim_missing_name_life[]"], input[name="victim_missing_age_life[]"]')) {
                    type = 'ผู้สูญหาย';
                } else if (row.querySelector('input[name="victim_name_life[]"], input[name="victim_age_life[]"]')) {
                    type = 'ผู้เสียชีวิต';
                }
            }

            if (!type && !name && !age) return;
            cardIdx++;
            _appendVictimCardStd(container, cardIdx, type, name, age);
        });

        // ถ้าไม่มี card เลย สร้าง card ว่าง 1 ใบ
        if (cardIdx === 0) {
            _appendVictimCardStd(container, 1, '', '', '');
        }
    }

    function _appendVictimCardStd(container, idx, type, name, age) {
        const html = `
            <div class="victim-card-life bg-light p-3 rounded-3 border mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-primary">รายการที่ ${idx}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVictimCardLife(this)">
                        <i class="fas fa-trash me-1"></i> ลบรายการนี้
                    </button>
                </div>
                <div class="bg-white p-3 rounded-3 shadow-sm">
                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภทผู้ประสบเหตุ <span class="text-danger">*</span></label>
                        <select class="form-select" name="victim_type_life[]" onchange="toggleVictimFields(this)">
                            <option value="" ${!type ? 'selected' : ''}>-- เลือกประเภท --</option>
                            <option value="ผู้เสียชีวิต" ${type === 'ผู้เสียชีวิต' ? 'selected' : ''}>ผู้เสียชีวิต</option>
                            <option value="ผู้บาดเจ็บ" ${type === 'ผู้บาดเจ็บ' ? 'selected' : ''}>ผู้บาดเจ็บ</option>
                            <option value="ผู้สูญหาย" ${type === 'ผู้สูญหาย' ? 'selected' : ''}>ผู้สูญหาย</option>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="victim_name_life[]" value="${(name || '').replace(/"/g, '&quot;')}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">อายุ (ปี)</label>
                            <input type="text" class="form-control text-center" name="victim_age_life[]" inputmode="numeric" pattern="[0-9]*" maxlength="3" value="${(age || '').replace(/"/g, '&quot;')}">
                        </div>
                    </div>
                </div>
            </div>`;
        $(container).append(html);
    }

    // ---------- Life Evidence: PDF → มาตรฐาน ----------
    function _syncLifeEvidenceToStd() {
        const tbody = document.getElementById('lpf_evidence_tbody');
        if (!tbody) return;
        const rows = tbody.querySelectorAll('tr');
        const container = document.getElementById('evidence_container_life');
        if (!container) return;
        container.innerHTML = '';

        const count = Math.max(rows.length, 1);
        for (let i = 0; i < count; i++) {
            const row = (i < rows.length) ? rows[i] : null;
            const item = row ? (row.querySelector('input[name="evidence_item_life[]"]') || {}).value || '' : '';
            const azimuth = row ? (row.querySelector('input[name="evidence_azimuth_life[]"]') || {}).value || '' : '';
            const remark = row ? (row.querySelector('input[name="evidence_remark_life[]"]') || {}).value || '' : '';
            const labUnit = row ? window.getLabUnitsString(row.querySelector('[name="evidence_lab_unit_life[]"]')) : '';
            const lv1 = row ? ((row.querySelector('input[name^="evidence_level_1_life_"]') || {}).value || '') : '';
            const lv2 = row ? ((row.querySelector('input[name^="evidence_level_2_life_"]') || {}).value || '') : '';
            const lv3 = row ? ((row.querySelector('input[name^="evidence_level_3_life_"]') || {}).value || '') : '';
            const lv4 = row ? ((row.querySelector('input[name^="evidence_level_4_life_"]') || {}).value || '') : '';

            const labUnitOptionsStd = [
                {v:'',t:'-- เลือก (เลือกได้หลายข้อ) --'},{v:'fingerprint',t:'ลายนิ้วมือแฝง'},{v:'bio_dna',t:'ชีววิทยา/ดีเอ็นเอ'},
                {v:'chemical',t:'เคมีฟิสิกส์'},{v:'drug',t:'ยาเสพติด'},{v:'gun',t:'อาวุธปืน'},
                {v:'document',t:'เอกสาร'},{v:'digital',t:'ดิจิทัล'},{v:'computer',t:'คอมพิวเตอร์'}
            ].map(o => '<option value="' + o.v + '">' + o.t + '</option>').join('');

            const cardHtml = `
                <div class="evidence-card-life card mb-3 shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small text-muted">วัตถุพยาน</label>
                                <input type="text" class="form-control" name="evidence_item_life[]" value="${item.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted">ระยะห่าง (m) จากจุดอ้างอิง</label>
                                <div class="row g-2">
                                    <div class="col-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">1</span>
                                            <input type="text" class="form-control" name="evidence_level_1_life_${i}" value="${String(lv1).replace(/"/g, '&quot;')}" placeholder="m">
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">2</span>
                                            <input type="text" class="form-control" name="evidence_level_2_life_${i}" value="${String(lv2).replace(/"/g, '&quot;')}" placeholder="m">
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">3</span>
                                            <input type="text" class="form-control" name="evidence_level_3_life_${i}" value="${String(lv3).replace(/"/g, '&quot;')}" placeholder="m">
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">4</span>
                                            <input type="text" class="form-control" name="evidence_level_4_life_${i}" value="${String(lv4).replace(/"/g, '&quot;')}" placeholder="m">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Azimuth (ทิศ/อ้าง/ระยะ)</label>
                                <input type="text" class="form-control" name="evidence_azimuth_life[]" value="${azimuth.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">หมายเหตุ</label>
                                <input type="text" class="form-control" name="evidence_remark_life[]" value="${remark.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">การตรวจพิสูจน์</label>
                                <select class="form-select lab-unit-multi" multiple size="4" title="เลือกได้มากกว่า 1 กลุ่มงาน">${labUnitOptionsStd}</select>
                                <input type="hidden" class="lab-unit-value" name="evidence_lab_unit_life[]" value="">
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEvidenceCardLife(this)">
                                    <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                </button>
                            </div>
                        </div>
                    </div>
                </div>`;
            $(container).append(cardHtml);
            window.setLabUnits($(container).find('.evidence-card-life').last().find('[name="evidence_lab_unit_life[]"]'), labUnit);
        }

        evidenceIndexLife = count;
    }

    // ---------- Life Measurement: PDF → มาตรฐาน ----------
    function _syncLifeMeasurementToStd() {
        const tbody = document.getElementById('lpf_collection_tbody');
        if (!tbody) return;
        const rows = tbody.querySelectorAll('tr');
        const container = document.getElementById('measurement_container_life');
        if (!container) return;
        container.innerHTML = '';

        const count = Math.max(rows.length, 1);
        for (let i = 0; i < count; i++) {
            const row = (i < rows.length) ? rows[i] : null;
            const item = row ? (row.querySelector('input[name="measurement_item_life[]"]') || {}).value || '' : '';
            const qty = row ? (row.querySelector('input[name="measurement_quantity_life[]"]') || {}).value || '' : '';
            const area = row ? (row.querySelector('input[name="measurement_area_life[]"]') || {}).value || '' : '';
            const labelNo = row ? (row.querySelector('input[name="measurement_label_number_life[]"]') || {}).value || '' : '';
            const remark = row ? (row.querySelector('input[name="measurement_remark_life[]"]') || {}).value || '' : '';
            const forensicUnit = row ? window.getLabUnitsString(row.querySelector('[name="measurement_forensic_unit_life[]"]')) : '';

            const pkgPlastic = row ? !!(row.querySelector('input[name^="measurement_package_plastic_check_"]') || {}).checked : false;
            const pkgPaper = row ? !!(row.querySelector('input[name^="measurement_package_paper_check_"]') || {}).checked : false;
            const pkgOther = row ? !!(row.querySelector('input[name^="measurement_package_other_check_"]') || {}).checked : false;
            const actReturn = row ? !!(row.querySelector('input[name^="measurement_action_return_check_"]') || {}).checked : false;
            const actOther = row ? !!(row.querySelector('input[name^="measurement_action_other_check_"]') || {}).checked : false;
            const pkgPlasticText = row ? (row.querySelector('input[name^="measurement_package_plastic_text_"]') || {}).value || '' : '';
            const pkgPaperText = row ? (row.querySelector('input[name^="measurement_package_paper_text_"]') || {}).value || '' : '';
            const pkgOtherText = row ? (row.querySelector('input[name^="measurement_package_other_text_"]') || {}).value || '' : '';
            const actReturnText = row ? (row.querySelector('input[name^="measurement_action_return_text_"]') || {}).value || '' : '';
            const actOtherText = row ? (row.querySelector('input[name^="measurement_action_other_text_"]') || {}).value || '' : '';

            const cardHtml = `
                <div class="measurement-card-life card mb-3 shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label small text-muted">1. รายการวัตถุพยาน</label>
                                <input type="text" class="form-control" name="measurement_item_life[]" value="${item.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">2. จำนวน</label>
                                <input type="text" class="form-control" name="measurement_quantity_life[]" inputmode="numeric" pattern="[0-9]*" value="${qty.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small text-muted">3. บริเวณที่ตรวจพบ</label>
                                <input type="text" class="form-control" name="measurement_area_life[]" value="${area.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">4. ป้ายหมายเลข</label>
                                <input type="text" class="form-control" name="measurement_label_number_life[]" value="${labelNo.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted">5. การบรรจุหีบ</label>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="measurement_package_plastic_check_${i}" value="1" ${pkgPlastic ? 'checked' : ''} onchange="togglePackageInput(this, 'plastic_${i}')">
                                                <label class="form-check-label">พลาสติก</label>
                                            </div>
                                            <input type="text" class="form-control form-control-sm" name="measurement_package_plastic_text_${i}" id="package_plastic_${i}" value="${pkgPlasticText.replace(/"/g, '&quot;')}" ${!pkgPlastic ? 'disabled' : ''}>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="measurement_package_paper_check_${i}" value="1" ${pkgPaper ? 'checked' : ''} onchange="togglePackageInput(this, 'paper_${i}')">
                                                <label class="form-check-label">กระดาษ</label>
                                            </div>
                                            <input type="text" class="form-control form-control-sm" name="measurement_package_paper_text_${i}" id="package_paper_${i}" value="${pkgPaperText.replace(/"/g, '&quot;')}" ${!pkgPaper ? 'disabled' : ''}>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="measurement_package_other_check_${i}" value="1" ${pkgOther ? 'checked' : ''} onchange="togglePackageInput(this, 'other_${i}')">
                                                <label class="form-check-label">อื่นๆ</label>
                                            </div>
                                            <input type="text" class="form-control form-control-sm" name="measurement_package_other_text_${i}" id="package_other_${i}" value="${pkgOtherText.replace(/"/g, '&quot;')}" ${!pkgOther ? 'disabled' : ''}>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted">6. การดำเนินการเกี่ยวกับวัตถุพยาน</label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                                <input class="form-check-input" type="checkbox" name="measurement_action_return_check_${i}" value="1" ${actReturn ? 'checked' : ''} onchange="toggleActionInput(this, 'return_${i}')">
                                                <label class="form-check-label">ส่งคืนพงส.</label>
                                            </div>
                                            <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_return_text_${i}" id="action_return_${i}" value="${actReturnText.replace(/"/g, '&quot;')}" ${!actReturn ? 'disabled' : ''}>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="form-check" style="min-width: 100px; white-space: nowrap;">
                                                <input class="form-check-input" type="checkbox" name="measurement_action_other_check_${i}" value="1" ${actOther ? 'checked' : ''} onchange="toggleActionInput(this, 'other_action_${i}')">
                                                <label class="form-check-label">อื่นๆ</label>
                                            </div>
                                            <input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_other_text_${i}" id="action_other_action_${i}" value="${actOtherText.replace(/"/g, '&quot;')}" ${!actOther ? 'disabled' : ''}>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted">7. หมายเหตุ</label>
                                <input type="text" class="form-control" name="measurement_remark_life[]" value="${remark.replace(/"/g, '&quot;')}">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small text-muted">8. การตรวจพิสูจน์</label>
                                <select class="form-select lab-unit-multi" multiple size="4" title="เลือกได้มากกว่า 1 กลุ่มงาน">
                                    <option value="">-- กรุณาเลือก (เลือกได้หลายข้อ) --</option>
                                    <option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>
                                    <option value="bio_dna">กลุ่มงานตรวจชีววิทยาและดีเอ็นเอ</option>
                                    <option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>
                                    <option value="drug">กลุ่มงานตรวจยาเสพติด</option>
                                    <option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>
                                    <option value="document">กลุ่มงานตรวจเอกสาร</option>
                                    <option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option>
                                    <option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>
                                </select>
                                <input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_life[]" value="">
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMeasurementCardLife(this)">
                                    <i class="fas fa-trash me-1"></i> ลบรายการนี้
                                </button>
                            </div>
                        </div>
                    </div>
                </div>`;
            $(container).append(cardHtml);
            window.setLabUnits($(container).find('.measurement-card-life').last().find('[name="measurement_forensic_unit_life[]"]'), forensicUnit);
        }

        measurementIndexLife = count;
    }

    // ===== TRAFFIC: Sync dynamic rows between standard ↔ PDF =====
    function syncTrafficDynamicRows(direction) {
        if (direction === 'toPdf') {
            // --- Vehicles (Section 2): std → PDF ---
            (function() {
                var srcCount = $('#vehicle_container_traffic .vehicle-card-traffic').length;
                var dstContainer = document.getElementById('tpf_vehicle_container');
                if (!dstContainer) return;
                var dstCount = $(dstContainer).find('.tpf-vehicle-card').length;
                if (typeof tpfResetVehicleIdx === 'function') tpfResetVehicleIdx();
                for (var i = dstCount; i < srcCount; i++) {
                    if (typeof tpfAddVehicle === 'function') tpfAddVehicle();
                }
                if (srcCount < dstCount) {
                    $(dstContainer).find('.tpf-vehicle-card').slice(srcCount).remove();
                }
            })();

            // --- Inspectors (Section 5): std → PDF ---
            (function() {
                var srcCount = $('#inspector_container_traffic .inspector-row-traffic').length;
                var dstContainer = document.getElementById('tpf_inspector_container');
                if (!dstContainer) return;
                var dstCount = $(dstContainer).find('.tpf-inspector-row').length;
                if (typeof tpfResetInspectorIdx === 'function') tpfResetInspectorIdx();
                for (var i = dstCount; i < srcCount; i++) {
                    if (typeof tpfAddInspector === 'function') tpfAddInspector();
                }
                if (srcCount < dstCount) {
                    $(dstContainer).find('.tpf-inspector-row').slice(srcCount).remove();
                }
            })();

            // --- Forensic Officers (Section 7): std → PDF ---
            (function() {
                var srcCount = $('#forensic_container_traffic .forensic-row-traffic').length;
                var dstContainer = document.getElementById('tpf_forensic_container');
                if (!dstContainer) return;
                var dstCount = $(dstContainer).find('.tpf-forensic-row').length;
                if (typeof tpfResetForensicIdx === 'function') tpfResetForensicIdx();
                for (var i = dstCount; i < srcCount; i++) {
                    if (typeof tpfAddForensic === 'function') tpfAddForensic();
                }
                if (srcCount < dstCount) {
                    $(dstContainer).find('.tpf-forensic-row').slice(srcCount).remove();
                }
            })();

            // --- Analysis Vehicles (Section 8): std → PDF ---
            (function() {
                var srcCount = $('#forensic_vehicle_container .forensic-vehicle-card').length;
                var dstContainer = document.getElementById('tpf_analysis_vehicle_container');
                if (!dstContainer) return;
                var dstCount = $(dstContainer).find('.tpf-analysis-card').length;
                if (typeof tpfResetAnalysisVIdx === 'function') tpfResetAnalysisVIdx();
                for (var i = dstCount; i < srcCount; i++) {
                    if (typeof tpfAddAnalysisVehicle === 'function') tpfAddAnalysisVehicle();
                }
                if (srcCount < dstCount) {
                    $(dstContainer).find('.tpf-analysis-card').slice(srcCount).remove();
                }
            })();

            // --- Compare Rows (Section 9): std → PDF ---
            (function() {
                var srcCount = $('#comparison_container .comparison-row').length;
                var dstContainer = document.getElementById('tpf_compare_container');
                if (!dstContainer) return;
                var dstCount = $(dstContainer).find('.tpf-compare-row').length;
                if (typeof tpfResetCompareIdx === 'function') tpfResetCompareIdx();
                for (var i = dstCount; i < srcCount; i++) {
                    if (typeof tpfAddCompareRow === 'function') tpfAddCompareRow();
                }
                if (srcCount < dstCount) {
                    $(dstContainer).find('.tpf-compare-row').slice(srcCount).remove();
                }
            })();

        } else {
            // --- Vehicles (Section 2): PDF → std ---
            (function() {
                var srcCount = $('#tpf_vehicle_container .tpf-vehicle-card').length;
                var dstContainer = document.getElementById('vehicle_container_traffic');
                if (!dstContainer) return;
                var dstCount = $(dstContainer).find('.vehicle-card-traffic').length;
                for (var i = dstCount; i < srcCount; i++) {
                    if (typeof addVehicleCardTraffic === 'function') addVehicleCardTraffic();
                }
                if (srcCount < dstCount) {
                    $(dstContainer).find('.vehicle-card-traffic').slice(srcCount).remove();
                }
            })();

            // --- Inspectors (Section 5): PDF → std ---
            (function() {
                var srcCount = $('#tpf_inspector_container .tpf-inspector-row').length;
                var dstContainer = document.getElementById('inspector_container_traffic');
                if (!dstContainer) return;
                var dstCount = $(dstContainer).find('.inspector-row-traffic').length;
                for (var i = dstCount; i < srcCount; i++) {
                    if (typeof addInspectorRowTraffic === 'function') addInspectorRowTraffic();
                }
                if (srcCount < dstCount) {
                    $(dstContainer).find('.inspector-row-traffic').slice(srcCount).remove();
                }
            })();

            // --- Forensic Officers (Section 7): PDF → std ---
            (function() {
                var srcCount = $('#tpf_forensic_container .tpf-forensic-row').length;
                var dstContainer = document.getElementById('forensic_container_traffic');
                if (!dstContainer) return;
                var dstCount = $(dstContainer).find('.forensic-row-traffic').length;
                for (var i = dstCount; i < srcCount; i++) {
                    if (typeof addForensicRowTraffic === 'function') addForensicRowTraffic();
                }
                if (srcCount < dstCount) {
                    $(dstContainer).find('.forensic-row-traffic').slice(srcCount).remove();
                }
            })();

            // --- Analysis Vehicles (Section 8): PDF → std ---
            (function() {
                var srcCount = $('#tpf_analysis_vehicle_container .tpf-analysis-card').length;
                var dstContainer = document.getElementById('forensic_vehicle_container');
                if (!dstContainer) return;
                var dstCount = $(dstContainer).find('.forensic-vehicle-card').length;
                for (var i = dstCount; i < srcCount; i++) {
                    if (typeof addForensicVehicleCard === 'function') addForensicVehicleCard();
                }
                if (srcCount < dstCount) {
                    $(dstContainer).find('.forensic-vehicle-card').slice(srcCount).remove();
                }
            })();

            // --- Compare Rows (Section 9): PDF → std ---
            (function() {
                var srcCount = $('#tpf_compare_container .tpf-compare-row').length;
                var dstContainer = document.getElementById('comparison_container');
                if (!dstContainer) return;
                var dstCount = $(dstContainer).find('.comparison-row').length;
                for (var i = dstCount; i < srcCount; i++) {
                    if (typeof addCompareRow === 'function') addCompareRow();
                }
                if (srcCount < dstCount) {
                    $(dstContainer).find('.comparison-row').slice(srcCount).remove();
                }
            })();
        }
    }

    // ===== TRAFFIC: สลับจากฟอร์มมาตรฐาน → PDF =====
    function switchToTrafficPdfForm() {
        // sync จำนวน dynamic rows ก่อน แล้วค่อย sync ค่า
        syncTrafficDynamicRows('toPdf');
        syncTrafficFormData('incidentCheckListFormTraffic', 'trafficFormPdf');

        // sync ข้อมูล display
        const docNo = $('#doc_no_traffic').val() || '';
        const rptNo = $('#report_no_traffic').val() || '';
        const rcvId = $('#receiveNoti_id_traffic').val() || '';
        $('#tpf_doc_no').val(docNo);
        $('#tpf_report_no').val(rptNo);
        $('#tpf_receiveNoti_id').val(rcvId);
        $('#tpf_case_doc_no').val(thaiDocNo($('#case_doc_no_traffic').val() || docNo));
        var tpfRptParts = (rptNo || '').split('/');
        $('#tpf_report_no_display').text(thaiReportNo(tpfRptParts[0] || ''));

        // ★ Capture canvas data
        var canvasCopyData = {};
        var stdCanvasIds = {
            'receiver_signature': 'sig-canvas-receiver-traffic',
            'sender_signature': 'sig-canvas-sender-traffic'
        };
        var pdfCanvasIds = {
            'receiver_signature': 'tpf_sig_receiver',
            'sender_signature': 'tpf_sig_sender'
        };
        Object.keys(stdCanvasIds).forEach(function(key) {
            var srcCvs = document.getElementById(stdCanvasIds[key]);
            if (srcCvs && srcCvs.width > 0 && srcCvs.height > 0) {
                try {
                    canvasCopyData[key] = srcCvs.toDataURL('image/png');
                } catch (e) {}
            }
        });

        // ปิด modal มาตรฐาน
        const stdEl = document.getElementById('addCheckListModalTraffic');
        const stdModal = bootstrap.Modal.getInstance(stdEl);
        if (stdModal) stdModal.hide();

        stdEl.addEventListener('hidden.bs.modal', function onHidden() {
            stdEl.removeEventListener('hidden.bs.modal', onHidden);
            const pdfEl = document.getElementById('trafficFormPdfModal');
            if (pdfEl) {
                pdfEl.addEventListener('shown.bs.modal', function onShown() {
                    pdfEl.removeEventListener('shown.bs.modal', onShown);
                    setTimeout(function() {
                        Object.keys(pdfCanvasIds).forEach(function(key) {
                            if (canvasCopyData[key]) {
                                var dst = document.getElementById(pdfCanvasIds[key]);
                                if (dst) {
                                    var img = new Image();
                                    img.onload = function() {
                                        var ctx = dst.getContext('2d');
                                        ctx.drawImage(img, 0, 0, dst.width, dst.height);
                                    };
                                    img.src = canvasCopyData[key];
                                }
                            }
                        });
                        if (typeof tpfRenderPhotosFromStore === 'function') tpfRenderPhotosFromStore();
                        // Update page numbers after dynamic rows may have changed
                        if (typeof tpfUpdatePageNumbers === 'function') tpfUpdatePageNumbers();
                    }, 150);
                });
                bootstrap.Modal.getOrCreateInstance(pdfEl).show();
            }
        });
    }

    // ===== TRAFFIC: สลับจาก PDF → ฟอร์มมาตรฐาน =====
    function switchToTrafficStandardForm() {
        // sync จำนวน dynamic rows ก่อน แล้วค่อย sync ค่า
        syncTrafficDynamicRows('toStd');
        syncTrafficFormData('trafficFormPdf', 'incidentCheckListFormTraffic');

        // sync ข้อมูล display กลับ
        const docNo = $('#tpf_doc_no').val() || '';
        const rptNo = $('#tpf_report_no').val() || '';
        const rcvId = $('#tpf_receiveNoti_id').val() || '';
        $('#doc_no_traffic').val(docNo);
        $('#report_no_traffic').val(rptNo);
        $('#receiveNoti_id_traffic').val(rcvId);
        $('#receiveNoti_No_Traffic').text(thaiDocNo(docNo));
        $('#receiveNotiReportNo_traffic').text(thaiReportNo(rptNo));
        $('#case_doc_no_traffic').val(thaiDocNo($('#tpf_case_doc_no').val() || docNo));

        // ★ Capture canvas data จาก PDF form
        var canvasCopyData = {};
        var pdfCanvasIds = {
            'receiver_signature': 'tpf_sig_receiver',
            'sender_signature': 'tpf_sig_sender'
        };
        var stdCanvasIds = {
            'receiver_signature': 'sig-canvas-receiver-traffic',
            'sender_signature': 'sig-canvas-sender-traffic'
        };
        Object.keys(pdfCanvasIds).forEach(function(key) {
            var srcCvs = document.getElementById(pdfCanvasIds[key]);
            if (srcCvs && srcCvs.width > 0 && srcCvs.height > 0) {
                try {
                    canvasCopyData[key] = srcCvs.toDataURL('image/png');
                } catch (e) {}
            }
        });

        // ปิด PDF modal
        const pdfEl = document.getElementById('trafficFormPdfModal');
        const pdfModal = bootstrap.Modal.getInstance(pdfEl);
        if (pdfModal) pdfModal.hide();

        pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
            pdfEl.removeEventListener('hidden.bs.modal', onHidden);
            const stdEl = document.getElementById('addCheckListModalTraffic');
            if (stdEl) {
                stdEl.addEventListener('shown.bs.modal', function onShown() {
                    stdEl.removeEventListener('shown.bs.modal', onShown);
                    setTimeout(function() {
                        Object.keys(stdCanvasIds).forEach(function(key) {
                            if (canvasCopyData[key]) {
                                var dst = document.getElementById(stdCanvasIds[key]);
                                if (dst) {
                                    var img = new Image();
                                    img.onload = function() {
                                        var ctx = dst.getContext('2d');
                                        ctx.drawImage(img, 0, 0, dst.width, dst.height);
                                    };
                                    img.src = canvasCopyData[key];
                                }
                            }
                        });
                        if (typeof renderAttachmentStoreTraffic === 'function') renderAttachmentStoreTraffic();
                        // Refresh Select2 displays after value sync
                        $('#addCheckListModalTraffic select.select2-hidden-accessible').trigger('change.select2');
                        // Re-index all dynamic row labels
                        if (typeof reIndexVehicleCardsTraffic === 'function') reIndexVehicleCardsTraffic();
                        if (typeof reIndexInspectorsTraffic === 'function') reIndexInspectorsTraffic();
                        if (typeof reIndexForensicRowsTraffic === 'function') reIndexForensicRowsTraffic();
                        if (typeof reIndexForensicVehicles === 'function') reIndexForensicVehicles();
                        if (typeof reIndexCompareRows === 'function') reIndexCompareRows();
                    }, 150);
                });
                bootstrap.Modal.getOrCreateInstance(stdEl).show();
            }
        });
    }

    // ===== TRAFFIC: Sync form data between standard <-> PDF =====
    function syncTrafficFormData(fromFormId, toFormId) {
        const fromForm = document.getElementById(fromFormId);
        const toForm = document.getElementById(toFormId);
        if (!fromForm || !toForm) return;

        const fromElements = fromForm.querySelectorAll('input, select, textarea');
        const dataMap = {};

        fromElements.forEach(el => {
            const name = el.name;
            if (!name) return;
            if (el.type === 'checkbox') {
                if (!dataMap[name]) dataMap[name] = {
                    type: 'checkbox',
                    values: []
                };
                if (el.checked) dataMap[name].values.push(el.value);
            } else if (el.type === 'radio') {
                if (el.checked) dataMap[name] = {
                    type: 'radio',
                    value: el.value
                };
            } else if (el.type === 'file') {
                return;
            } else {
                if (!dataMap[name]) dataMap[name] = {
                    type: 'value',
                    values: []
                };
                dataMap[name].values.push(el.value);
            }
        });

        Object.keys(dataMap).forEach(name => {
            const info = dataMap[name];
            const toEls = toForm.querySelectorAll('[name="' + name + '"]');
            if (toEls.length === 0) return;

            if (info.type === 'checkbox') {
                toEls.forEach(el => {
                    el.checked = info.values.includes(el.value);
                });
            } else if (info.type === 'radio') {
                toEls.forEach(el => {
                    el.checked = (el.value === info.value);
                });
            } else {
                toEls.forEach((el, idx) => {
                    if (idx < info.values.length) el.value = info.values[idx];
                });
            }
        });
    }

    // =============================================================
    //  PROPERTY: สลับจากฟอร์มมาตรฐาน → PDF
    // =============================================================
    function switchToPropertyPdfForm() {
        // sync ข้อมูลจากฟอร์มมาตรฐาน → PDF (ใช้ syncPropertyFormData — generic by name)
        syncPropertyFormData('incidentCheckListForm', 'propertyFormPdf');

        // sync dynamic rows (inspectors + trace points + evidence)
        _syncPropertyInspectorToPdf();
        _syncPropertyTracePointsToPdf();
        _syncPropertyEvidenceToPdf();

        // sync hidden fields
        const docNo = $('#doc_no').val() || '';
        const rptNo = $('#report_no').val() || '';
        const notiId = $('#receiveNoti_id').val() || '';
        $('#ppf_doc_no').val(docNo);
        $('#ppf_report_no').val(rptNo);
        $('#ppf_receiveNoti_id').val(notiId);

        // mirror report no/year display
        var rptParts = rptNo.split('/');
        var rptNum = rptParts[0] || '';
        var rptYear = (rptParts[1] || '').toString().slice(-2);
        $('#ppf_report_no_display').text(rptNum);
        $('#ppf_report_year_display').text(rptYear);
        $('#propertyFormPdfModal .ppf-rpt-no-mirror').text(rptNum);
        $('#propertyFormPdfModal .ppf-rpt-year-mirror').text(rptYear);

        // ★ sync วันที่/เวลาตรวจสถานที่ → หน้ารูปถ่าย PDF
        var inspDT = $('#inspection_datetime').val() || '';
        if (inspDT) {
            var parts = inspDT.split('T');
            $('#ppf_photo_inspect_date').val(parts[0] || '');
            $('#ppf_photo_inspect_time').val(parts[1] || '');
        }

        // ★ Capture canvas data ก่อนปิด modal มาตรฐาน
        var stdCanvasIds = {
            'scene_sketch': 'scene_sketch_canvas',
            'receiver_signature': 'sig-canvas-receiver',
            'sender_signature': 'sig-canvas-deliverer'
        };
        var pdfCanvasIds = {
            'scene_sketch': 'ppf_scene_sketch_canvas',
            'receiver_signature': 'ppf_sig_receiver',
            'sender_signature': 'ppf_sig_sender'
        };
        // เก็บ toDataURL ไว้ก่อน (canvas ยังมองเห็นอยู่)
        var canvasCopyData = {};
        Object.keys(stdCanvasIds).forEach(function(key) {
            var srcCvs = document.getElementById(stdCanvasIds[key]);
            if (srcCvs && srcCvs.width > 0 && srcCvs.height > 0) {
                try {
                    canvasCopyData[key] = srcCvs.toDataURL('image/png');
                } catch (e) {}
            }
        });

        // ปิด modal มาตรฐาน
        const stdEl = document.getElementById('addCheckListModal');
        const stdModal = bootstrap.Modal.getInstance(stdEl);
        if (stdModal) stdModal.hide();

        // รอให้ modal ปิดเสร็จแล้วเปิด PDF modal
        stdEl.addEventListener('hidden.bs.modal', function onHidden() {
            stdEl.removeEventListener('hidden.bs.modal', onHidden);
            const pdfEl = document.getElementById('propertyFormPdfModal');
            if (pdfEl) {
                pdfEl.addEventListener('shown.bs.modal', function onShown() {
                    pdfEl.removeEventListener('shown.bs.modal', onShown);
                    setTimeout(function() {
                        // ★ ใช้ transferCanvasImage (fit-contain + จัดกลาง)
                        Object.keys(stdCanvasIds).forEach(function(key) {
                            if (canvasCopyData[key]) {
                                var dstId = pdfCanvasIds[key];
                                loadImageToCanvas(dstId, canvasCopyData[key], null, null);
                            }
                        });
                        if (typeof ppfRenderPhotosFromStore === 'function') ppfRenderPhotosFromStore();
                    }, 150);
                });
                bootstrap.Modal.getOrCreateInstance(pdfEl).show();
            }
        });
    }

    // ===== PROPERTY: สลับจาก PDF → ฟอร์มมาตรฐาน =====
    function switchToPropertyStandardForm() {
        // sync ข้อมูลจาก PDF → ฟอร์มมาตรฐาน
        syncPropertyFormData('propertyFormPdf', 'incidentCheckListForm');

        // sync dynamic rows (inspectors + trace points + evidence)
        _syncPropertyInspectorToStd();
        _syncPropertyTracePointsToStd();
        _syncPropertyEvidenceToStd();
        _syncPropertyMeasurementPdfToStd();

        // sync hidden fields
        const docNo = $('#ppf_doc_no').val() || '';
        const rptNo = $('#ppf_report_no').val() || '';
        const notiId = $('#ppf_receiveNoti_id').val() || '';
        $('#doc_no').val(docNo);
        $('#report_no').val(rptNo);
        $('#receiveNoti_id').val(notiId);
        $('#receiveNoti_No').text(thaiDocNo(docNo));
        $('#receiveNotiReportNo').text(thaiReportNo(rptNo));

        // ★ Capture canvas data จาก PDF form
        var canvasCopyData = {};
        var pdfCanvasIds = {
            'scene_sketch': 'ppf_scene_sketch_canvas',
            'receiver_signature': 'ppf_sig_receiver',
            'sender_signature': 'ppf_sig_sender'
        };
        var stdCanvasIds = {
            'scene_sketch': 'scene_sketch_canvas',
            'receiver_signature': 'sig-canvas-receiver',
            'sender_signature': 'sig-canvas-deliverer'
        };
        Object.keys(pdfCanvasIds).forEach(function(key) {
            var srcCvs = document.getElementById(pdfCanvasIds[key]);
            if (srcCvs && srcCvs.width > 0 && srcCvs.height > 0) {
                try {
                    canvasCopyData[key] = srcCvs.toDataURL('image/png');
                } catch (e) {}
            }
        });

        // ปิด PDF modal
        const pdfEl = document.getElementById('propertyFormPdfModal');
        const pdfModal = bootstrap.Modal.getInstance(pdfEl);
        if (pdfModal) pdfModal.hide();

        // รอให้ modal ปิดเสร็จแล้วเปิดฟอร์มมาตรฐาน
        pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
            pdfEl.removeEventListener('hidden.bs.modal', onHidden);
            const stdEl = document.getElementById('addCheckListModal');
            if (stdEl) {
                stdEl.addEventListener('shown.bs.modal', function onShown() {
                    stdEl.removeEventListener('shown.bs.modal', onShown);
                    setTimeout(function() {
                        // ★ ใช้ loadImageToCanvas (fit-contain + จัดกลาง)
                        Object.keys(stdCanvasIds).forEach(function(key) {
                            if (canvasCopyData[key]) {
                                var dstId = stdCanvasIds[key];
                                loadImageToCanvas(dstId, canvasCopyData[key], null, null);
                            }
                        });
                        if (typeof renderPropertyAttachmentGrid === 'function') renderPropertyAttachmentGrid();
                        if (typeof updatePropertyRealInput === 'function') updatePropertyRealInput();
                    }, 150);
                });
                bootstrap.Modal.getOrCreateInstance(stdEl).show();
            }
        });
    }

    // ===== PROPERTY: Sync form data between standard <-> PDF =====
    function syncPropertyFormData(fromFormId, toFormId) {
        const fromForm = document.getElementById(fromFormId);
        const toForm = document.getElementById(toFormId);
        if (!fromForm || !toForm) return;

        if (window.LabUnitMulti) window.LabUnitMulti.syncAll(fromForm);

        const fromElements = fromForm.querySelectorAll('input, select, textarea');
        const dataMap = {};

        fromElements.forEach(el => {
            const name = el.name;
            if (!name) return;
            if (el.type === 'checkbox') {
                if (!dataMap[name]) dataMap[name] = {
                    type: 'checkbox',
                    values: []
                };
                if (el.checked) dataMap[name].values.push(el.value);
            } else if (el.type === 'radio') {
                if (el.checked) dataMap[name] = {
                    type: 'radio',
                    value: el.value
                };
            } else if (el.type === 'file') {
                return;
            } else {
                if (!dataMap[name]) dataMap[name] = {
                    type: 'value',
                    values: []
                };
                dataMap[name].values.push(el.value);
            }
        });

        Object.keys(dataMap).forEach(name => {
            const info = dataMap[name];
            const toEls = toForm.querySelectorAll('[name="' + name + '"]');
            if (toEls.length === 0) return;

            if (info.type === 'checkbox') {
                toEls.forEach(el => {
                    el.checked = info.values.includes(el.value);
                });
            } else if (info.type === 'radio') {
                toEls.forEach(el => {
                    el.checked = (el.value === info.value);
                });
            } else {
                toEls.forEach((el, idx) => {
                    if (idx < info.values.length) el.value = info.values[idx];
                });
            }
        });
    }

    // ช่อง "การตรวจพิสูจน์" เก็บค่าที่เลือกหลายรายการไว้ใน hidden input (class lab-unit-value)
    // ฟังก์ชัน sync ทุกตัวด้านบน copy ค่าตาม name อยู่แล้ว จึงแค่ต้อง sync select -> hidden
    // ก่อน copy และ hidden -> select ของฟอร์มปลายทางหลัง copy
    ['syncFireFormData', 'syncFingerprintFormData', 'syncBombFormData',
        'syncLifeFormData', 'syncTrafficFormData', 'syncPropertyFormData'
    ].forEach(function(fnName) {
        const original = window[fnName];
        if (typeof original !== 'function' || original.__labUnitWrapped) return;
        const wrapped = function(fromFormId, toFormId) {
            if (window.LabUnitMulti) {
                const f = document.getElementById(fromFormId);
                if (f) window.LabUnitMulti.syncAll(f);
            }
            const result = original.apply(this, arguments);
            if (window.LabUnitMulti) {
                const t = document.getElementById(toFormId);
                if (t) window.LabUnitMulti.refreshFromHidden(t);
            }
            return result;
        };
        wrapped.__labUnitWrapped = true;
        window[fnName] = wrapped;
    });

    // ---------- PROPERTY: Inspector sync helpers ----------
    function _syncPropertyInspectorToPdf() {
        const stdForm = document.getElementById('incidentCheckListForm');
        if (!stdForm) return;
        const srcSelects = stdForm.querySelectorAll('[name="inspector_id[]"]');
        const container = document.getElementById('ppf_inspector_container');
        if (!container) return;

        const existingSel = container.querySelector('select');
        const optionsHtml = existingSel ? existingSel.innerHTML : (srcSelects[0] ? srcSelects[0].innerHTML : '');
        container.innerHTML = '';

        const count = Math.max(srcSelects.length, 1);
        for (let i = 0; i < count; i++) {
            const row = document.createElement('div');
            row.className = 'ppf-si ppf-inspector-row';
            row.innerHTML = '<span class="ppf-si-no">5.' + (i + 1) + '</span>' +
                '<select class="ppf-sel" name="inspector_id[]">' + optionsHtml + '</select>' +
                (i > 0 ? ' <button type="button" class="ppf-del-btn" onclick="ppfDelInspector(this)">×</button>' : '');
            container.appendChild(row);
            // ★ ตั้งค่าที่เลือกจากฟอร์มมาตรฐาน
            if (i < srcSelects.length && srcSelects[i].value) {
                row.querySelector('select').value = srcSelects[i].value;
            }
        }
    }

    function _syncPropertyInspectorToStd() {
        const pdfForm = document.getElementById('propertyFormPdf');
        if (!pdfForm) return;
        const srcSelects = pdfForm.querySelectorAll('[name="inspector_id[]"]');
        const container = document.getElementById('inspector_container');
        if (!container) return;

        // ลบ inspector rows เดิม (ทำลาย Select2 ก่อน)
        $('#inspector_container .inspector-select').each(function() {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).select2('destroy');
            }
        });
        $('#inspector_container').empty();

        const count = Math.max(srcSelects.length, 1);
        for (let i = 0; i < count; i++) {
            const deleteBtn = (i > 0) ? `
                <div class="ms-2">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0 rounded-circle remove-inspector-btn d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" title="ลบ">
                        <i class="fas fa-times"></i>
                    </button>
                </div>` : '<div class="ms-2" style="width: 32px;"></div>';
            const rowHtml = `
                <div class="d-flex align-items-center mb-3 inspector-row">
                    <div class="text-end pe-3" style="width: 50px;">
                        <span class="fw-bold text-secondary index-label">5.${i + 1}</span>
                    </div>
                    <div class="flex-grow-1">
                        <select class="form-select inspector-select" name="inspector_id[]">
                            <?php echo str_replace("'", "\\'", $inspectorOptionsProp); ?>
                        </select>
                    </div>
                    ${deleteBtn}
                </div>`;
            $('#inspector_container').append(rowHtml);
        }

        // Re-init Select2 แล้วตั้งค่าจาก PDF form
        $('#inspector_container .inspector-select').each(function(idx) {
            $(this).select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'กรุณาเลือก',
                allowClear: true,
                dropdownParent: $('#addCheckListModal'),
                dropdownAutoWidth: true
            });
            if (idx < srcSelects.length && srcSelects[idx].value) {
                $(this).val(srcSelects[idx].value).trigger('change');
            }
        });
    }

    // ---------- Property Trace Points: มาตรฐาน → PDF ----------
    function _syncPropertyTracePointsToPdf() {
        var cards = document.querySelectorAll('#trace_point_container .trace-card');
        var container = document.getElementById('ppf_trace_point_container');
        if (!container) return;
        container.innerHTML = '';

        var count = Math.max(cards.length, 1);
        for (var i = 0; i < count; i++) {
            var position = '',
                detail = '';
            if (i < cards.length) {
                var card = cards[i];
                position = card.querySelector('textarea[name*="[area_detail]"]') ? card.querySelector('textarea[name*="[area_detail]"]').value : '';

                // รวมรายละเอียด entry/pry/rummage ที่ติ๊กไว้
                var details = [];
                var entryChk = card.querySelector('input[name*="[entry_check]"]');
                if (entryChk && entryChk.checked) {
                    var entryDet = card.querySelector('textarea[name*="[entry_detail]"]');
                    details.push('ทางเข้าคนร้าย' + (entryDet && entryDet.value ? ': ' + entryDet.value : ''));
                }
                var pryChk = card.querySelector('input[name*="[pry_check]"]');
                if (pryChk && pryChk.checked) {
                    var pryDet = card.querySelector('textarea[name*="[pry_detail]"]');
                    details.push('รอยงัดแงะ' + (pryDet && pryDet.value ? ': ' + pryDet.value : ''));
                }
                var rumChk = card.querySelector('input[name*="[rummage_check]"]');
                if (rumChk && rumChk.checked) {
                    var rumDet = card.querySelector('textarea[name*="[rummage_detail]"]');
                    details.push('ร่องรอยรื้อค้น' + (rumDet && rumDet.value ? ': ' + rumDet.value : ''));
                }
                detail = details.join(', ');
            }

            var row = document.createElement('div');
            row.className = 'ppf-trace-row';
            row.style.cssText = 'margin-bottom:6px; padding-bottom:4px; border-bottom:1px dotted #ccc;';
            row.innerHTML =
                '<div class="ppf-fr"><span class="ppf-fl-b">จุดที่ ' + (i + 1) + '</span>' +
                (i > 0 ? ' <button type="button" class="ppf-del-btn" onclick="this.closest(\'.ppf-trace-row\').remove()">×</button>' : '') + '</div>' +
                '<div class="ppf-fr"><span class="ppf-fl">ตำแหน่ง</span><input type="text" class="ppf-inp" name="trace_position[]" value="' + position.replace(/"/g, '&quot;') + '"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>' +
                '<div class="ppf-fr"><span class="ppf-fl">ลักษณะร่องรอย</span><input type="text" class="ppf-inp" name="trace_detail[]" value="' + detail.replace(/"/g, '&quot;') + '"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div>';
            container.appendChild(row);
        }
        if (typeof ppfTraceIdx !== 'undefined') ppfTraceIdx = count;
    }

    // ---------- Property Trace Points: PDF → มาตรฐาน ----------
    function _syncPropertyTracePointsToStd() {
        var container = document.getElementById('ppf_trace_point_container');
        if (!container) return;
        var rows = container.querySelectorAll('.ppf-trace-row');
        if (!rows.length) return;

        $('#trace_point_container').empty();

        rows.forEach(function(row, idx) {
            var posInput = row.querySelector('input[name="trace_position[]"]');
            var detInput = row.querySelector('input[name="trace_detail[]"]');
            var position = posInput ? posInput.value : '';
            var detail = detInput ? detInput.value : '';

            var cardIndex = idx + 1;
            var htmlString = createTraceCard(cardIndex);
            var $card = $(htmlString);
            $('#trace_point_container').append($card);

            // ใส่ค่าตำแหน่ง
            if (position) $card.find('textarea[name*="[area_detail]"]').val(position);

            // แยกลักษณะร่องรอยกลับเข้า checkbox + detail
            if (detail) {
                if (detail.indexOf('ทางเข้าคนร้าย') !== -1) {
                    $card.find('input[name*="[entry_check]"]').prop('checked', true);
                    $card.find('input[name*="[entry_check]"]').closest('.col-md-4').find('.trace-input-div').removeClass('d-none');
                    var entryMatch = detail.match(/ทางเข้าคนร้าย(?::\s*([^,]*))?/);
                    if (entryMatch && entryMatch[1]) $card.find('textarea[name*="[entry_detail]"]').val(entryMatch[1].trim());
                }
                if (detail.indexOf('รอยงัดแงะ') !== -1) {
                    $card.find('input[name*="[pry_check]"]').prop('checked', true);
                    $card.find('input[name*="[pry_check]"]').closest('.col-md-4').find('.trace-input-div').removeClass('d-none');
                    var pryMatch = detail.match(/รอยงัดแงะ(?::\s*([^,]*))?/);
                    if (pryMatch && pryMatch[1]) $card.find('textarea[name*="[pry_detail]"]').val(pryMatch[1].trim());
                }
                if (detail.indexOf('ร่องรอยรื้อค้น') !== -1) {
                    $card.find('input[name*="[rummage_check]"]').prop('checked', true);
                    $card.find('input[name*="[rummage_check]"]').closest('.col-md-4').find('.trace-input-div').removeClass('d-none');
                    var rumMatch = detail.match(/ร่องรอยรื้อค้น(?::\s*([^,]*))?/);
                    if (rumMatch && rumMatch[1]) $card.find('textarea[name*="[rummage_detail]"]').val(rumMatch[1].trim());
                }
                // ถ้าไม่ match คำหลักใดเลย ใส่ใน entry detail แทน
                if (detail.indexOf('ทางเข้า') === -1 && detail.indexOf('รอยงัด') === -1 && detail.indexOf('ร่องรอย') === -1) {
                    $card.find('input[name*="[entry_check]"]').prop('checked', true);
                    $card.find('input[name*="[entry_check]"]').closest('.col-md-4').find('.trace-input-div').removeClass('d-none');
                    $card.find('textarea[name*="[entry_detail]"]').val(detail);
                }
            }
        });
    }

    // ---------- Property Evidence: มาตรฐาน → PDF (หน้า 3 evidence_item + หน้า 4 evidence_location + หน้า 5 collection) ----------
    function _syncPropertyEvidenceToPdf() {
        // อ่านทุก evidence card จากฟอร์มมาตรฐาน (ข้ามรายการ blood _summary_only)
        var cards = document.querySelectorAll('#evidence_container .evidence-card');
        if (!cards.length) return;

        // === Evidence type label map ===
        var typeLabels = {
            'blood': 'คราบสีแดงคล้ายโลหิต',
            'fingerprint': 'ลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง',
            'dna': 'สารพันธุกรรม',
            'toolmark': 'ร่องรอยการตัด (Toolmark)',
            'other': 'อื่น ๆ'
        };

        // === หน้า 3: ppf_evidence_container (รายการวัตถุพยานที่ตรวจพบ) ===
        var evContainer = document.getElementById('ppf_evidence_container');
        if (evContainer) {
            evContainer.innerHTML = '';
            cards.forEach(function(card, idx) {
                var typeVal = card.querySelector('.evidence-type-select') ? card.querySelector('.evidence-type-select').value : '';
                var detailVal = card.querySelector('input[name*="[detail]"]') ? card.querySelector('input[name*="[detail]"]').value : '';
                var displayText = detailVal || '';
                var row = document.createElement('div');
                row.className = 'ppf-fr ppf-evidence-row';
                row.innerHTML = '<span class="ppf-si-no">' + (idx + 1) + '.</span>' +
                    '<input type="text" class="ppf-inp" name="evidence_item[]" value="' + displayText.replace(/"/g, '&quot;') + '">' +
                    '<button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>' +
                    (idx > 0 ? ' <button type="button" class="ppf-del-btn" onclick="ppfDelEvidenceRow(this)">×</button>' : '');
                evContainer.appendChild(row);
            });
            if (typeof ppfEvidenceIdx !== 'undefined') ppfEvidenceIdx = cards.length;
        }

        // === หน้า 4: ppf_evidence_location_tbody (วัตถุพยานและตำแหน่งที่ตรวจพบ) ===
        var locTbody = document.getElementById('ppf_evidence_location_tbody');
        if (locTbody) {
            locTbody.innerHTML = '';
            var firstRefDescs = ['', '', '', ''];
            cards.forEach(function(card, idx) {
                var labelNo = card.querySelector('input[name*="[label_no]"]') ? card.querySelector('input[name*="[label_no]"]').value : '';
                var typeVal = card.querySelector('.evidence-type-select') ? card.querySelector('.evidence-type-select').value : '';
                var detailVal = card.querySelector('input[name*="[detail]"]') ? card.querySelector('input[name*="[detail]"]').value : '';
                var evidenceName = detailVal || '';
                var azimuth = card.querySelector('input[name*="[azimuth]"]') ? card.querySelector('input[name*="[azimuth]"]').value : '';
                var remark = card.querySelector('textarea[name*="[remark]"]') ? card.querySelector('textarea[name*="[remark]"]').value : '';
                var labUnit = window.getLabUnitsString(card.querySelector('[name*="[lab_unit]"]'));

                // เช็ค ref point distances
                var refValues = ['', '', '', ''];
                for (var r = 1; r <= 4; r++) {
                    var distInput = card.querySelector('input[name*="[ref' + r + '_dist]"]');
                    var descInput = card.querySelector('input[name*="[ref' + r + '_desc]"]');
                    if (distInput && distInput.value !== '') {
                        refValues[r - 1] = distInput.value;
                    }
                    // เก็บ description จาก row แรกที่มีค่า
                    if (descInput && descInput.value && !firstRefDescs[r - 1]) {
                        firstRefDescs[r - 1] = descInput.value;
                    }
                }

                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><input type="text" name="evidence_label[]" style="width:35px;" value="' + (idx + 1) + '"></td>' +
                    '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_name[]" value="' + evidenceName.replace(/"/g, '&quot;') + '" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
                    '<td><input type="text" name="evidence_level_1[]" style="width:28px;" value="' + refValues[0] + '"></td>' +
                    '<td><input type="text" name="evidence_level_2[]" style="width:28px;" value="' + refValues[1] + '"></td>' +
                    '<td><input type="text" name="evidence_level_3[]" style="width:28px;" value="' + refValues[2] + '"></td>' +
                    '<td><input type="text" name="evidence_level_4[]" style="width:28px;" value="' + refValues[3] + '"></td>' +
                    '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_azimuth[]" value="' + azimuth.replace(/"/g, '&quot;') + '" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
                    '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="evidence_remark[]" value="' + remark.replace(/"/g, '&quot;') + '" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
                    '<td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option></select><input type="hidden" class="lab-unit-value" name="evidence_lab_unit[]" value=""></td>' +
                    '<td><button type="button" class="ppf-del-btn" onclick="ppfDelRow(this)">×</button></td>';
                locTbody.appendChild(tr);
                window.setLabUnits($(tr).find('[name="evidence_lab_unit[]"]'), labUnit);
            });
            if (typeof ppfEvLocRowIdx !== 'undefined') ppfEvLocRowIdx = cards.length - 1;

            // ใส่จุดอ้างอิง
            var pdfForm = document.getElementById('propertyFormPdf');
            if (pdfForm) {
                for (var r = 1; r <= 4; r++) {
                    var refInput = pdfForm.querySelector('input[name="reference_point_' + r + '"]');
                    if (refInput && firstRefDescs[r - 1]) refInput.value = firstRefDescs[r - 1];
                }
            }
        }

        // === หน้า 5: ppf_collection_tbody (บันทึกการตรวจเก็บวัตถุพยาน) ===
        var collTbody = document.getElementById('ppf_collection_tbody');
        if (collTbody) {
            // ★ ถ้ามี measurement cards (Section 10) ใช้เป็นแหล่งข้อมูลหลัก
            //    ไม่งั้น fallback ไปใช้ evidence cards (Section 7) เหมือนเดิม
            var measurementCards = document.querySelectorAll('#measurement_container_property .measurement-card-property');
            var hasMeasurementData = false;
            measurementCards.forEach(function(mc) {
                var v = mc.querySelector('input[name="measurement_item_property[]"]');
                if (v && v.value && v.value.trim() !== '') hasMeasurementData = true;
            });

            // Evidence table กับ Collection table เป็นคนละส่วนกัน ไม่ cross-sync
            if (hasMeasurementData) {
                _syncPropertyMeasurementToPdfTable(measurementCards, collTbody);
            }
        }

        // === sync ผู้จดบันทึก & วัน/เวลา จากฟอร์มมาตรฐาน → PDF หน้า 4 & 5 ===
        var recorderName = document.getElementById('recorder_name') ? document.getElementById('recorder_name').value : '';
        var recorderDt = document.getElementById('recorder_datetime') ? document.getElementById('recorder_datetime').value : '';
        if (document.getElementById('ppf_ev_loc_recorder')) document.getElementById('ppf_ev_loc_recorder').value = recorderName;
        if (document.getElementById('ppf_ev_loc_datetime')) document.getElementById('ppf_ev_loc_datetime').value = recorderDt;
        if (document.getElementById('ppf_collection_recorder')) document.getElementById('ppf_collection_recorder').value = recorderName;
        if (document.getElementById('ppf_collection_datetime')) document.getElementById('ppf_collection_datetime').value = recorderDt;

        // ★ ถ้ามี measurement_meta จาก Section 10 ให้ override → PDF หน้า 5
        var stdMRecorder = document.getElementById('measurement_recorder_property');
        var stdMDt = document.getElementById('measurement_datetime_property');
        var stdMInsp = document.getElementById('measurement_inspection_date_property');
        if (stdMRecorder && stdMRecorder.value && document.getElementById('ppf_collection_recorder')) {
            document.getElementById('ppf_collection_recorder').value = stdMRecorder.value;
        }
        if (stdMDt && stdMDt.value && document.getElementById('ppf_collection_datetime')) {
            document.getElementById('ppf_collection_datetime').value = stdMDt.value;
        }

        // === sync วันที่ตรวจสถานที่เกิดเหตุ → หน้า 5 ===
        var inspDt = document.getElementById('inspection_datetime') ? document.getElementById('inspection_datetime').value : '';
        // ★ ถ้ามี measurement_inspection_date_property ใช้ค่านั้นแทน
        if (stdMInsp && stdMInsp.value) inspDt = stdMInsp.value;
        if (inspDt) {
            var dtParts = inspDt.split('T');
            var dateInp = document.querySelector('#propertyFormPdf input[name="collection_inspection_date"]');
            var timeInp = document.querySelector('#propertyFormPdf input[name="collection_inspection_time"]');
            if (dateInp && dtParts[0]) dateInp.value = dtParts[0];
            if (timeInp && dtParts[1]) timeInp.value = dtParts[1];
        }
    }

    // ---------- Property Evidence: PDF → มาตรฐาน (หน้า 4 & 5 → evidence cards) ----------
    function _syncPropertyEvidenceToStd() {
        // อ่านข้อมูลจาก PDF page 4 (evidence_location) & page 5 (collection)
        var locTbody = document.getElementById('ppf_evidence_location_tbody');
        var collTbody = document.getElementById('ppf_collection_tbody');
        if (!locTbody && !collTbody) return;

        // ใช้ page 4 rows เป็นฐาน (มี label, name, ref, azimuth, remark)
        var locRows = locTbody ? locTbody.querySelectorAll('tr') : [];
        var collRows = collTbody ? collTbody.querySelectorAll('tr') : [];
        var rowCount = Math.max(locRows.length, collRows.length);
        if (rowCount === 0) return;

        // เตรียม reference point descriptions จาก PDF form
        var pdfForm = document.getElementById('propertyFormPdf');
        var refDescs = ['', '', '', ''];
        if (pdfForm) {
            for (var r = 1; r <= 4; r++) {
                var refInput = pdfForm.querySelector('input[name="reference_point_' + r + '"]');
                if (refInput) refDescs[r - 1] = refInput.value || '';
            }
        }

        // ลบ evidence cards เดิม
        $('#evidence_container').empty();

        for (var i = 0; i < rowCount; i++) {
            var locRow = i < locRows.length ? locRows[i] : null;
            var collRow = i < collRows.length ? collRows[i] : null;

            // อ่านค่า page 4
            var labelNo = '',
                evidenceName = '',
                azimuth = '',
                remark = '';
            var labUnit = '';
            var refValues = ['', '', '', ''];
            if (locRow) {
                var locInputs = locRow.querySelectorAll('input');
                labelNo = locRow.querySelector('input[name="evidence_label[]"]') ? locRow.querySelector('input[name="evidence_label[]"]').value : '';
                evidenceName = locRow.querySelector('input[name="evidence_name[]"]') ? locRow.querySelector('input[name="evidence_name[]"]').value : '';
                azimuth = locRow.querySelector('input[name="evidence_azimuth[]"]') ? locRow.querySelector('input[name="evidence_azimuth[]"]').value : '';
                remark = locRow.querySelector('input[name="evidence_remark[]"]') ? locRow.querySelector('input[name="evidence_remark[]"]').value : '';
                labUnit = window.getLabUnitsString(locRow.querySelector('[name="evidence_lab_unit[]"]'));
                for (var r = 0; r < 4; r++) {
                    var lvlInput = locRow.querySelector('input[name="evidence_level_' + (r + 1) + '[]"]');
                    if (lvlInput && lvlInput.value) refValues[r] = lvlInput.value;
                }
            }

            // อ่านค่า page 5 (เสริม)
            var qtyVal = '',
                areaVal = '',
                collLabelVal = '',
                collRemarkVal = '';
            var isPlastic = false,
                isPaper = false,
                isPackOther = false,
                isReturn = false,
                isActOther = false;
            if (collRow) {
                qtyVal = collRow.querySelector('input[name="collection_quantity[]"]') ? collRow.querySelector('input[name="collection_quantity[]"]').value : '';
                areaVal = collRow.querySelector('input[name="collection_area[]"]') ? collRow.querySelector('input[name="collection_area[]"]').value : '';
                collLabelVal = collRow.querySelector('input[name="collection_label[]"]') ? collRow.querySelector('input[name="collection_label[]"]').value : '';
                collRemarkVal = collRow.querySelector('input[name="collection_remark[]"]') ? collRow.querySelector('input[name="collection_remark[]"]').value : '';
                var cbPlastic = collRow.querySelector('input[name^="collection_plastic_"]');
                var cbPaper = collRow.querySelector('input[name^="collection_paper_"]');
                var cbPackOth = collRow.querySelector('input[name^="collection_pack_other_"]');
                var cbReturn = collRow.querySelector('input[name^="collection_return_"]');
                var cbActOth = collRow.querySelector('input[name^="collection_action_other_"]');
                if (cbPlastic && cbPlastic.checked) isPlastic = true;
                if (cbPaper && cbPaper.checked) isPaper = true;
                if (cbPackOth && cbPackOth.checked) isPackOther = true;
                if (cbReturn && cbReturn.checked) isReturn = true;
                if (cbActOth && cbActOth.checked) isActOther = true;
            }

            // ใช้ label จาก page 4 ก่อน ถ้าไม่มีค่อยใช้จาก page 5
            if (!labelNo && collLabelVal) labelNo = collLabelVal;
            if (!remark && collRemarkVal) remark = collRemarkVal;

            // สร้าง evidence card
            var cardIndex = i + 1;
            var htmlString = createEvidenceCard(cardIndex);
            var $card = $(htmlString);
            $('#evidence_container').append($card);

            // ตรวจหาประเภทจากชื่อ
            var matchedType = '';
            var matchedDetail = evidenceName;
            // ★ ใช้ array เพื่อให้เช็คตามลำดับ: ชื่อยาวก่อน, 'อื่น ๆ' สุดท้าย
            var typeMapArr = [
                ['คราบสีแดงคล้ายโลหิต', 'blood'],
                ['ลายนิ้วมือ', 'fingerprint'],
                ['สารพันธุกรรม', 'dna'],
                ['ร่องรอยการตัด', 'toolmark'],
                ['อื่น ๆ', 'other']
            ];
            for (var t = 0; t < typeMapArr.length; t++) {
                if (evidenceName.indexOf(typeMapArr[t][0]) !== -1) {
                    matchedType = typeMapArr[t][1];
                    // ★ ตัด label + ขีด ออกทั้งหมด (กัน "อื่น ๆ - อื่น ๆ - xxx")
                    matchedDetail = evidenceName;
                    while (matchedDetail.indexOf(typeMapArr[t][0]) !== -1) {
                        matchedDetail = matchedDetail.replace(typeMapArr[t][0], '');
                    }
                    matchedDetail = matchedDetail.replace(/^[\s\-]+/, '').trim();
                    break;
                }
            }
            if (!matchedType && evidenceName) matchedType = 'other';
            if (!matchedDetail && evidenceName) matchedDetail = evidenceName;
            if (matchedType) $card.find('.evidence-type-select').val(matchedType);
            if (matchedDetail) $card.find('input[name*="[detail]"]').first().val(matchedDetail);

            // ใส่ค่า
            if (labelNo) $card.find('input[name*="[label_no]"]').val(labelNo);
            if (azimuth) $card.find('input[name*="[azimuth]"]').val(azimuth);
            if (areaVal) $card.find('textarea[name*="[area_found]"]').val(areaVal);
            if (qtyVal) $card.find('input[name*="[quantity_val]"]').val(qtyVal);

            // ref points
            for (var r = 0; r < 4; r++) {
                if (refValues[r]) {
                    $card.find('input[name*="[ref' + (r + 1) + '_dist]"]').val(refValues[r]);
                }
                if (refDescs[r]) {
                    $card.find('input[name*="[ref' + (r + 1) + '_desc]"]').val(refDescs[r]);
                }
            }

            // packaging
            if (isPlastic) $card.find('input[value="plastic"]').prop('checked', true);
            if (isPaper) $card.find('input[value="paper"]').prop('checked', true);
            if (isPackOther) {
                $card.find('input[value="other"].package-check').prop('checked', true);
                $card.find('[id^="pack_other_detail_"]').removeClass('d-none');
            }

            // action
            if (isReturn) $card.find('input[value="return_investigator"]').prop('checked', true);
            if (isActOther) {
                $card.find('input[value="other"].action-check').prop('checked', true);
                $card.find('[id^="act_other_detail_"]').removeClass('d-none');
            }

            // remark
            if (remark) $card.find('textarea[name*="[remark]"]').val(remark);
            window.setLabUnits($card.find('[name*="[lab_unit]"]'), labUnit);
        }

        // === sync ผู้จดบันทึก & วัน/เวลา จาก PDF → มาตรฐาน ===
        var ppfRecName = document.getElementById('ppf_ev_loc_recorder') ? document.getElementById('ppf_ev_loc_recorder').value : '';
        var ppfRecDt = document.getElementById('ppf_ev_loc_datetime') ? document.getElementById('ppf_ev_loc_datetime').value : '';
        if (ppfRecName && document.getElementById('recorder_name')) document.getElementById('recorder_name').value = ppfRecName;
        if (ppfRecDt && document.getElementById('recorder_datetime')) document.getElementById('recorder_datetime').value = ppfRecDt;

        // === sync วันที่ตรวจสถานที่ → inspection_datetime ===
        var collDate = pdfForm ? pdfForm.querySelector('input[name="collection_inspection_date"]') : null;
        var collTime = pdfForm ? pdfForm.querySelector('input[name="collection_inspection_time"]') : null;
        if (collDate && collDate.value && collTime && collTime.value) {
            var inspDtEl = document.getElementById('inspection_datetime');
            if (inspDtEl) inspDtEl.value = collDate.value + 'T' + collTime.value;
        }
    }

    // ============================================================
    // PROPERTY MEASUREMENT (Section 10): Sync helpers
    // ============================================================

    // Helper: เขียนข้อมูล measurement cards → PDF table (ppf_collection_tbody)
    function _syncPropertyMeasurementToPdfTable(measurementCards, collTbody) {
        collTbody.innerHTML = '';
        measurementCards.forEach(function(card, idx) {
            var item = (card.querySelector('input[name="measurement_item_property[]"]') || {}).value || '';
            var qty = (card.querySelector('input[name="measurement_quantity_property[]"]') || {}).value || '';
            var area = (card.querySelector('input[name="measurement_area_property[]"]') || {}).value || '';
            var labelNo = (card.querySelector('input[name="measurement_label_number_property[]"]') || {}).value || '';
            var remark = (card.querySelector('input[name="measurement_remark_property[]"]') || {}).value || '';
            var forensicUnit = window.getLabUnitsString(card.querySelector('[name="measurement_forensic_unit_property[]"]'));

            var pkgPlastic = !!(card.querySelector('input[name^="measurement_package_plastic_check_prop_"]') || {}).checked;
            var pkgPaper = !!(card.querySelector('input[name^="measurement_package_paper_check_prop_"]') || {}).checked;
            var pkgOther = !!(card.querySelector('input[name^="measurement_package_other_check_prop_"]') || {}).checked;
            var actReturn = !!(card.querySelector('input[name^="measurement_action_return_check_prop_"]') || {}).checked;
            var actOther = !!(card.querySelector('input[name^="measurement_action_other_check_prop_"]') || {}).checked;

            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td style="text-align:center;">' + (idx + 1) + '</td>' +
                '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="collection_item[]" value="' + item.replace(/"/g, '&quot;') + '" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
                '<td><input type="text" name="collection_quantity[]" style="width:30px; text-align:center;" value="' + qty.replace(/"/g, '&quot;') + '"></td>' +
                '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="collection_area[]" value="' + area.replace(/"/g, '&quot;') + '" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
                '<td><input type="text" name="collection_label[]" style="width:30px; text-align:center;" value="' + (idx + 1) + '"></td>' +
                '<td><input type="checkbox" name="collection_plastic_' + idx + '" value="1"' + (pkgPlastic ? ' checked' : '') + '></td>' +
                '<td><input type="checkbox" name="collection_paper_' + idx + '" value="1"' + (pkgPaper ? ' checked' : '') + '></td>' +
                '<td><input type="checkbox" name="collection_pack_other_' + idx + '" value="1"' + (pkgOther ? ' checked' : '') + '></td>' +
                '<td><input type="checkbox" name="collection_return_' + idx + '" value="1"' + (actReturn ? ' checked' : '') + '></td>' +
                '<td><input type="checkbox" name="collection_action_other_' + idx + '" value="1"' + (actOther ? ' checked' : '') + '></td>' +
                '<td><div style="display:flex; align-items:center; gap:2px;"><input type="text" name="collection_remark[]" value="' + remark.replace(/"/g, '&quot;') + '" style="flex:1; min-width:0;"><button type="button" class="btn btn-hw-open btn-hw-dynamic" title="HW" style="padding:0 3px; font-size:0.65rem; line-height:1; flex-shrink:0;"><i class="fas fa-pen"></i></button></div></td>' +
                '<td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option><option value="computer">คอมพิวเตอร์</option></select><input type="hidden" class="lab-unit-value" name="collection_forensic_unit[]" value=""></td>' +
                '<td><button type="button" class="ppf-del-btn" onclick="ppfDelRow(this)">×</button></td>';
            collTbody.appendChild(tr);
            window.setLabUnits($(tr).find('[name="collection_forensic_unit[]"]'), forensicUnit);
        });
        if (typeof ppfCollRowIdx !== 'undefined') ppfCollRowIdx = measurementCards.length - 1;
    }

    // Property Measurement: PDF (ppf_collection_tbody) → ฟอร์มมาตรฐาน Section 10 cards
    function _syncPropertyMeasurementPdfToStd() {
        var collTbody = document.getElementById('ppf_collection_tbody');
        if (!collTbody) return;
        var rows = collTbody.querySelectorAll('tr');
        if (!rows.length) return;

        var container = document.getElementById('measurement_container_property');
        if (!container) return;

        // ตรวจสอบว่ามีข้อมูลที่กรอกจริงหรือไม่
        var hasData = false;
        rows.forEach(function(row) {
            var v = row.querySelector('input[name="collection_item[]"]');
            if (v && v.value && v.value.trim() !== '') hasData = true;
        });
        if (!hasData) return;

        // Clear existing cards
        $(container).empty();
        if (typeof measurementCounterProperty !== 'undefined') {
            measurementCounterProperty = 0;
        }

        rows.forEach(function(row, idx) {
            var item = (row.querySelector('input[name="collection_item[]"]') || {}).value || '';
            var qty = (row.querySelector('input[name="collection_quantity[]"]') || {}).value || '';
            var area = (row.querySelector('input[name="collection_area[]"]') || {}).value || '';
            var labelNo = (row.querySelector('input[name="collection_label[]"]') || {}).value || '';
            var remark = (row.querySelector('input[name="collection_remark[]"]') || {}).value || '';

            var pkgPlastic = !!(row.querySelector('input[name^="collection_plastic_"]') || {}).checked;
            var pkgPaper = !!(row.querySelector('input[name^="collection_paper_"]') || {}).checked;
            var pkgOther = !!(row.querySelector('input[name^="collection_pack_other_"]') || {}).checked;
            var actReturn = !!(row.querySelector('input[name^="collection_return_"]') || {}).checked;
            var actOther = !!(row.querySelector('input[name^="collection_action_other_"]') || {}).checked;
            var forensicUnit = window.getLabUnitsString(row.querySelector('[name="collection_forensic_unit[]"]'));

            // ใช้ idx เป็น index ของ checkbox suffix
            var html =
                '<div class="measurement-card-property card mb-3 shadow-sm">' +
                '<div class="card-body"><div class="row g-3">' +
                    '<div class="col-md-8"><label class="form-label small text-muted">1. รายการวัตถุพยาน</label>' +
                        '<div class="input-group"><input type="text" class="form-control" name="measurement_item_property[]" value="' + item.replace(/"/g, '&quot;') + '">' +
                        '<button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>' +
                    '<div class="col-md-4"><label class="form-label small text-muted">2. จำนวน</label>' +
                        '<input type="text" class="form-control" name="measurement_quantity_property[]" inputmode="numeric" pattern="[0-9]*" value="' + qty.replace(/"/g, '&quot;') + '"></div>' +
                    '<div class="col-md-8"><label class="form-label small text-muted">3. บริเวณที่ตรวจพบ</label>' +
                        '<div class="input-group"><input type="text" class="form-control" name="measurement_area_property[]" value="' + area.replace(/"/g, '&quot;') + '">' +
                        '<button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>' +
                    '<div class="col-md-4"><label class="form-label small text-muted">4. ป้ายหมายเลข</label>' +
                        '<div class="input-group"><input type="text" class="form-control" name="measurement_label_number_property[]" value="' + labelNo.replace(/"/g, '&quot;') + '">' +
                        '<button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>' +
                    '<div class="col-12"><label class="form-label small text-muted">5. การบรรจุหีบ</label><div class="row g-2">' +
                        '<div class="col-md-4"><div class="d-flex align-items-center gap-2">' +
                            '<div class="form-check"><input class="form-check-input" type="checkbox" name="measurement_package_plastic_check_prop_' + idx + '" value="1"' + (pkgPlastic ? ' checked' : '') + ' onchange="togglePackageInputProperty(this, \'plastic_prop_' + idx + '\')"><label class="form-check-label">พลาสติก</label></div>' +
                            '<input type="text" class="form-control form-control-sm" name="measurement_package_plastic_text_prop_' + idx + '" id="package_plastic_prop_' + idx + '"' + (pkgPlastic ? '' : ' disabled') + '></div></div>' +
                        '<div class="col-md-4"><div class="d-flex align-items-center gap-2">' +
                            '<div class="form-check"><input class="form-check-input" type="checkbox" name="measurement_package_paper_check_prop_' + idx + '" value="1"' + (pkgPaper ? ' checked' : '') + ' onchange="togglePackageInputProperty(this, \'paper_prop_' + idx + '\')"><label class="form-check-label">กระดาษ</label></div>' +
                            '<input type="text" class="form-control form-control-sm" name="measurement_package_paper_text_prop_' + idx + '" id="package_paper_prop_' + idx + '"' + (pkgPaper ? '' : ' disabled') + '></div></div>' +
                        '<div class="col-md-4"><div class="d-flex align-items-center gap-2">' +
                            '<div class="form-check"><input class="form-check-input" type="checkbox" name="measurement_package_other_check_prop_' + idx + '" value="1"' + (pkgOther ? ' checked' : '') + ' onchange="togglePackageInputProperty(this, \'other_prop_' + idx + '\')"><label class="form-check-label">อื่นๆ</label></div>' +
                            '<input type="text" class="form-control form-control-sm" name="measurement_package_other_text_prop_' + idx + '" id="package_other_prop_' + idx + '"' + (pkgOther ? '' : ' disabled') + '></div></div>' +
                    '</div></div>' +
                    '<div class="col-12"><label class="form-label small text-muted">6. การดำเนินการเกี่ยวกับวัตถุพยาน</label><div class="row g-2">' +
                        '<div class="col-md-6"><div class="d-flex align-items-center gap-2">' +
                            '<div class="form-check" style="min-width: 100px; white-space: nowrap;"><input class="form-check-input" type="checkbox" name="measurement_action_return_check_prop_' + idx + '" value="1"' + (actReturn ? ' checked' : '') + ' onchange="toggleActionInputProperty(this, \'return_prop_' + idx + '\')"><label class="form-check-label">ส่งคืนพงส.</label></div>' +
                            '<input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_return_text_prop_' + idx + '" id="action_return_prop_' + idx + '"' + (actReturn ? '' : ' disabled') + '></div></div>' +
                        '<div class="col-md-6"><div class="d-flex align-items-center gap-2">' +
                            '<div class="form-check" style="min-width: 100px; white-space: nowrap;"><input class="form-check-input" type="checkbox" name="measurement_action_other_check_prop_' + idx + '" value="1"' + (actOther ? ' checked' : '') + ' onchange="toggleActionInputProperty(this, \'other_action_prop_' + idx + '\')"><label class="form-check-label">อื่นๆ</label></div>' +
                            '<input type="text" class="form-control form-control-sm flex-grow-1" name="measurement_action_other_text_prop_' + idx + '" id="action_other_action_prop_' + idx + '"' + (actOther ? '' : ' disabled') + '></div></div>' +
                    '</div></div>' +
                    '<div class="col-md-12"><label class="form-label small text-muted">7. หมายเหตุ</label>' +
                        '<div class="input-group"><input type="text" class="form-control" name="measurement_remark_property[]" value="' + remark.replace(/"/g, '&quot;') + '">' +
                        '<button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></div></div>' +
                    '<div class="col-md-12"><label class="form-label small text-muted">8. การตรวจพิสูจน์</label>' +
                        '<select class="form-select lab-unit-multi" multiple size="4" title="เลือกได้มากกว่า 1 กลุ่มงาน">' +
                            '<option value="">-- กรุณาเลือก (เลือกได้หลายข้อ) --</option>' +
                            '<option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option>' +
                            '<option value="bio_dna">กลุ่มงานตรวจชีววิทยาและดีเอ็นเอ</option>' +
                            '<option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option>' +
                            '<option value="drug">กลุ่มงานตรวจยาเสพติด</option>' +
                            '<option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option>' +
                            '<option value="document">กลุ่มงานตรวจเอกสาร</option>' +
                            '<option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option>' +
                            '<option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option>' +
                        '</select>' +
                        '<input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_property[]" value=""></div>' +
                    '<div class="col-12"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMeasurementCardProperty(this)"><i class="fas fa-trash me-1"></i> ลบรายการนี้</button></div>' +
                '</div></div></div>';
            $(container).append(html);
            window.setLabUnits($(container).find('.measurement-card-property').last().find('[name="measurement_forensic_unit_property[]"]'), forensicUnit);
        });

        if (typeof measurementCounterProperty !== 'undefined') {
            measurementCounterProperty = rows.length;
        }

        // sync วันที่/ผู้บันทึก/datetime จาก PDF → standard
        var ppfRecorder = document.getElementById('ppf_collection_recorder');
        var ppfDt = document.getElementById('ppf_collection_datetime');
        if (ppfRecorder && ppfRecorder.value) {
            var stdRecorder = document.getElementById('measurement_recorder_property');
            if (stdRecorder) stdRecorder.value = ppfRecorder.value;
        }
        if (ppfDt && ppfDt.value) {
            var stdDt = document.getElementById('measurement_datetime_property');
            if (stdDt) stdDt.value = ppfDt.value;
        }
        // วันที่ตรวจสถานที่: รวม date + time → datetime-local
        var pdfForm = document.getElementById('propertyFormPdf');
        if (pdfForm) {
            var dateInp = pdfForm.querySelector('input[name="collection_inspection_date"]');
            var timeInp = pdfForm.querySelector('input[name="collection_inspection_time"]');
            if (dateInp && dateInp.value) {
                var dtVal = dateInp.value + (timeInp && timeInp.value ? 'T' + timeInp.value : 'T00:00');
                var stdInsp = document.getElementById('measurement_inspection_date_property');
                if (stdInsp) stdInsp.value = dtVal;
            }
        }
    }

    // ---------- Property Photo: render & update ----------
    function updatePropertyRealInput() {
        const count = attachmentStore.length;
        $('#photo_amount').val(count);
        $('#file_count_badge').text(count);
    }

    function renderPropertyAttachmentGrid() {
        const grid = $('#attachments_grid');
        grid.empty();
        if (attachmentStore.length === 0) {
            $('#attachments_wrapper').addClass('d-none');
            $('#file_count_badge').text(0);
            return;
        }

        $('#attachments_wrapper').removeClass('d-none');
        $('#file_count_badge').text(attachmentStore.length);

        attachmentStore.forEach(function(item, idx) {
            // ชื่อไฟล์สำหรับแสดง (escape HTML) และสำหรับ onclick (escape quote)
            const displayName = (item.name || 'photo').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            const jsName = (item.name || 'photo').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
            const imgSrc = item.src || '';
            const isExisting = item.existing || false;
            const badgeHtml = isExisting ?
                '<div class="pt-2"><span class="badge bg-info text-white fw-normal" style="font-size:0.65rem;">รูปเดิม</span></div>' :
                '<div class="pt-2"><span class="badge bg-success text-white fw-normal" style="font-size:0.65rem;">รูปใหม่</span></div>';

            const cardHtml =
                '<div class="col attachment-item" id="prop_attach_' + item.id + '">' +
                    '<div class="card attachment-card h-100">' +
                        '<div class="card-actions-bar">' +
                            '<button type="button" class="action-btn" title="ดูรูปภาพ" onclick="showImagePreview(\'' + imgSrc + '\', \'' + jsName + '\')"><i class="far fa-eye" style="font-size:0.8rem;"></i></button>' +
                            '<button type="button" class="action-btn delete" onclick="removePropertyAttachment(\'' + item.id + '\')" title="ลบรูปนี้"><i class="fas fa-times" style="font-size:0.85rem;"></i></button>' +
                        '</div>' +
                        '<div class="img-thumbnail-box"><img src="' + imgSrc + '" alt="' + displayName + '"></div>' +
                        '<div class="card-body d-flex flex-column">' +
                            '<div class="filename-text mb-auto" title="' + displayName + '">' + displayName + '</div>' +
                            badgeHtml +
                        '</div>' +
                    '</div>' +
                '</div>';
            grid.append(cardHtml);
        });
    }

    function removePropertyAttachment(fileId) {
        // ★ track สำหรับลบบน server (เหมือน fire)
        const item = attachmentStore.find(x => x.id === fileId);
        if (item && item.existing && item.db_file_id) {
            deletedExistingPhotos.push({
                file_id: item.db_file_id
            });
        } else if (item && item.existing && item.disk_filename) {
            deletedExistingPhotos.push(item.disk_filename);
        }
        attachmentStore = attachmentStore.filter(item => item.id !== fileId);
        renderPropertyAttachmentGrid();
        updatePropertyRealInput();
    }

    function updateFireRealInput() {
        const count = attachmentStoreFire.length;
        $('#photo_amount_fire').val(count);
        $('#file_count_badge_fire').text(count);
        if (count > 0) {
            $('#photo_id_start_fire').val(attachmentStoreFire[0].name || '1');
            $('#photo_id_end_fire').val(attachmentStoreFire[count - 1].name || String(count));
        } else {
            $('#photo_id_start_fire').val('');
            $('#photo_id_end_fire').val('');
        }
    }

    function renderFireAttachmentGrid() {
        const grid = $('#attachments_grid_fire');
        grid.empty();
        if (attachmentStoreFire.length === 0) {
            $('#attachments_wrapper_fire').addClass('d-none');
            $('#file_count_badge_fire').text(0);
            return;
        }
        $('#attachments_wrapper_fire').removeClass('d-none');
        $('#file_count_badge_fire').text(attachmentStoreFire.length);
        attachmentStoreFire.forEach(function(item, idx) {
            const displayName = (item.name || 'photo').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            const jsName = (item.name || 'photo').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
            const imgSrc = item.src || '';
            const badgeHtml = item.existing ?
                '<div class="pt-2"><span class="badge bg-info text-white fw-normal" style="font-size:0.65rem;">รูปเดิม</span></div>' :
                '<div class="pt-2"><span class="badge bg-success text-white fw-normal" style="font-size:0.65rem;">รูปใหม่</span></div>';
            const cardHtml =
                '<div class="col attachment-item" id="fire_attach_' + item.id + '">' +
                    '<div class="card attachment-card h-100">' +
                        '<div class="card-actions-bar">' +
                            '<button type="button" class="action-btn" title="ดูรูปภาพ" onclick="showImagePreview(\'' + imgSrc + '\', \'' + jsName + '\')"><i class="far fa-eye" style="font-size:0.8rem;"></i></button>' +
                            '<button type="button" class="action-btn delete" onclick="removeFireAttachment(\'' + item.id + '\')" title="ลบรูปนี้"><i class="fas fa-times" style="font-size:0.85rem;"></i></button>' +
                        '</div>' +
                        '<div class="img-thumbnail-box"><img src="' + imgSrc + '" alt="' + displayName + '"></div>' +
                        '<div class="card-body d-flex flex-column">' +
                            '<div class="filename-text mb-auto" title="' + displayName + '">' + displayName + '</div>' +
                            badgeHtml +
                        '</div>' +
                    '</div>' +
                '</div>';
            grid.append(cardHtml);
        });
    }

    // function openQRCodeModal(id){
    //     window.open('/csims/api/incidentCheckList/gen_pdf_evidence_html.php?incident_id=' + id, '_blank');
    // }

    // Global variable เก็บ incident_id สำหรับ Evidence modal
    var currentEvidenceIncidentId = null;

    function openQRCodeModal(id) {
        // เปิด Modal วัตถุพยาน (Evidence PDF Form)
        console.log('[openQRCodeModal] Opening for id:', id);

        // 0. เก็บ id ไว้ใน global variable
        currentEvidenceIncidentId = id;

        // 1. Reset form ก่อน
        if (typeof resetEvidenceFormPdf === 'function') {
            resetEvidenceFormPdf();
        }

        // 2. Set incident_id หลัง reset (ทั้ง hidden input และ global)
        $('#evpf_incident_id').val(id);
        console.log('[openQRCodeModal] Set evpf_incident_id to:', $('#evpf_incident_id').val());

        // 3. ดึง docNo จากแถวตาราง
        const row = $('button[onclick="openQRCodeModal(' + id + ')"]:first').closest('tr');
        let docNo = '';
        if (row.length) {
            docNo = row.find('td').eq(1).text().trim(); // เลขที่เอกสาร column
        }
        $('#evpf_case_no').val(docNo);

        // 4. เปิด modal
        const modalEl = document.getElementById('evidenceFormPdfModal');
        if (modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    }
    // --- ฟังก์ชันบีบอัดรูปภาพ (ลดความละเอียด + ลด quality) ---
    // maxWidth/maxHeight = ขนาดสูงสุด (px), quality = คุณภาพ JPEG (0-1)
    // ★ ถ้าผลลัพธ์เกิน 1.8MB จะลด quality ลงอัตโนมัติจนผ่าน (รองรับ PHP upload_max_filesize = 2M)
    const UPLOAD_MAX_BYTES = 1.8 * 1024 * 1024;

    function compressImage(file, maxWidth, maxHeight, quality) {
        return new Promise((resolve, reject) => {
            // ถ้าไม่ใช่รูปภาพ ส่งไฟล์เดิมกลับ
            if (!file.type.startsWith('image/')) {
                resolve(file);
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    // คำนวณขนาดใหม่ (รักษาสัดส่วน)
                    let width = img.width;
                    let height = img.height;

                    // ถ้ารูปเล็กกว่า max อยู่แล้ว ก็แค่ลด quality
                    if (width > maxWidth || height > maxHeight) {
                        const ratio = Math.min(maxWidth / width, maxHeight / height);
                        width = Math.round(width * ratio);
                        height = Math.round(height * ratio);
                    }

                    // วาดลง Canvas
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    // ★ บีบอัดซ้ำจนขนาดไม่เกิน UPLOAD_MAX_BYTES (1.8MB)
                    function tryCompress(q) {
                        canvas.toBlob(function(blob) {
                            if (blob) {
                                if (blob.size > UPLOAD_MAX_BYTES && q > 0.15) {
                                    // ยังใหญ่เกิน → ลด quality แล้วลองใหม่
                                    console.log(`📸 Re-compress: ${file.name} | q=${q.toFixed(2)}→${(q-0.1).toFixed(2)} | ${(blob.size/1024/1024).toFixed(2)}MB > ${(UPLOAD_MAX_BYTES/1024/1024).toFixed(1)}MB`);
                                    tryCompress(q - 0.1);
                                    return;
                                }
                                const compressedFile = new File([blob], file.name, {
                                    type: 'image/jpeg',
                                    lastModified: Date.now()
                                });
                                console.log(`📸 Compressed: ${file.name} | ${(file.size/1024/1024).toFixed(2)}MB → ${(compressedFile.size/1024/1024).toFixed(2)}MB | ${img.width}x${img.height} → ${width}x${height} | q=${q.toFixed(2)}`);
                                resolve(compressedFile);
                            } else {
                                resolve(file); // fallback ถ้า toBlob ล้มเหลว
                            }
                        }, 'image/jpeg', q);
                    }
                    tryCompress(quality);
                };
                img.onerror = function() {
                    resolve(file); // fallback
                };
                img.src = e.target.result;
            };
            reader.onerror = function() {
                resolve(file); // fallback
            };
            reader.readAsDataURL(file);
        });
    }

    // ---------------------------------------------------------
    // 2. HELPER FUNCTIONS (ฟังก์ชันสร้าง HTML/คำนวณต่างๆ - อยู่นอก ready)
    // ---------------------------------------------------------

    function setDefaultDate() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        $('#filter_date_start').val(`${year}-${month}-01`);
        $('#filter_date_end').val(`${year}-${month}-${day}`);
    }

    function findFirstInvalidInput(form) {
        if (!form) return null;
        return form.querySelector('input:invalid, select:invalid, textarea:invalid');
    }

    // ฟังก์ชัน Radio Toggle สำหรับ checkbox ที่ต้องเลือกได้ทีละ 1 ตัวในกลุ่มเดียวกัน (ใช้ร่วมกันทั้ง life, property, bomb)
    function lifeRadioToggle(el) {
        const group = el.getAttribute('data-group');
        if (!group) return;
        const checkboxes = document.querySelectorAll(`input[data-group="${group}"]`);
        checkboxes.forEach(function(cb) {
            if (cb !== el) cb.checked = false;
        });
    }

    function clearSignature(canvasId) {
        // เพิ่ม confirm popup สำหรับ body_diagram_canvas_life (Updated: 2026-05-20 16:14)
        console.log(canvasId,"<----------canvasId");
        if (canvasId === 'body_diagram_canvas_life') {
            if (typeof Swal === 'undefined') {
                // Fallback ถ้า Swal ยังไม่โหลด
                if (!confirm('คุณต้องการล้างภาพทั้งหมดหรือไม่?')) {
                    return;
                }
            } else {
                Swal.fire({
                title: 'ยืนยันการบันทึกข้อมูล',
                text: 'กรุณาตรวจสอบความถูกต้องก่อนบันทึกทึก',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#dc3545',
                confirmButtonText: 'ยืนยัน, บันทึกเลย!',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    // ดำเนินการล้างภาพ
                    const pad = signaturePads[canvasId];
                    if (pad) {
                        pad.clear();
                    } else {
                        const canvas = document.getElementById(canvasId);
                        if (canvas) {
                            const ctx = canvas.getContext('2d');
                            if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
                        }
                    }
                    // ล้าง hidden input และ strokes
                    if (canvasId === 'body_diagram_canvas_life') {
                        $('#body_diagram_data_life').val('');
                        $('#body_diagram_strokes_life').val('');
                    }
                }
            });
                return; // หยุดการทำงานปกติ
            }
        }
        
        const pad = signaturePads[canvasId];
        if (pad) {
            pad.clear();
        } else {
            const canvas = document.getElementById(canvasId);
            if (canvas) {
                const ctx = canvas.getContext('2d');
                if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
            }
        }
        if (pad || document.getElementById(canvasId)) {
            // ★ ติดตามว่า canvas ถูก clear โดยผู้ใช้อย่างตั้งใจ (เพื่อป้องกัน save แบบไม่วาดทำให้รูปหาย)
            var _sKeyMap = {
                'scene_sketch_canvas_bomb': 'scene_sketch',
                'body_diagram_canvas_bomb': 'body_diagram',
                'sig-canvas-receiver-bomb': 'receiver_signature',
                'sig-canvas-sender-bomb': 'sender_signature'
            };
            if (_sKeyMap[canvasId]) {
                (window._explicitlyClearedBombSigKeys = window._explicitlyClearedBombSigKeys || new Set()).add(_sKeyMap[canvasId]);
            }
            // ล้างค่าใน hidden input ตาม ID
            if (canvasId === 'sig-canvas-receiver') $('#receiver_signature_data').val('');
            if (canvasId === 'sig-canvas-deliverer') $('#deliverer_signature_data').val('');
            if (canvasId === 'scene_sketch_canvas') $('#scene_sketch_data').val('');
            if (canvasId === 'sig-canvas-receiver-life') $('#receiver_signature_data_life').val('');
            if (canvasId === 'sig-canvas-sender-life') $('#sender_signature_data_life').val('');
            if (canvasId === 'scene_sketch_canvas_life') $('#scene_sketch_data_life').val('');
            if (canvasId === 'body_diagram_canvas_life') $('#body_diagram_data_life').val('');
            if (canvasId === 'sig-canvas-receiver-bomb') $('#receiver_signature_data_bomb').val('');
            if (canvasId === 'sig-canvas-sender-bomb') $('#sender_signature_data_bomb').val('');
            if (canvasId === 'scene_sketch_canvas_bomb') $('#scene_sketch_data_bomb').val('');
            if (canvasId === 'body_diagram_canvas_bomb') $('#body_diagram_data_bomb').val('');
            if (canvasId === 'scene_sketch_canvas_fire') $('#scene_sketch_data_fire').val('');
            if (canvasId === 'sig-canvas-receiver-traffic') $('#receiver_signature_data_traffic').val('');
            if (canvasId === 'sig-canvas-sender-traffic') $('#sender_signature_data_traffic').val('');
            if (canvasId === 'sig-canvas-ev7-receiver') $('#ev7_receiver_signature_data').val('');
            if (canvasId === 'sig-canvas-ev7-sender') $('#ev7_sender_signature_data').val('');
        }
    }

    // --- Trace Card Helper ---
    function createTraceCard(index) {
        const id = Date.now() + Math.random().toString(16).slice(2);

        const deleteButtonHtml = (index > 1) ? `
        <button type="button" class="btn btn-outline-danger btn-sm border-0 rounded-circle remove-trace-btn d-flex align-items-center justify-content-center" 
                style="width: 28px; height: 28px;" title="ลบจุดนี้">
            <i class="fas fa-times"></i>
        </button>
        ` : '';

        return `
            <div class="bg-body-tertiary p-3 rounded-3 border mb-3 trace-card" id="trace_card_${id}">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-bold text-primary trace-index-label">จุดที่ ${index}</span>
                    ${deleteButtonHtml}
                </div>

                <div class="mb-3">
                    <label class="form-label small text-secondary fw-bold mb-1">บริเวณที่ตรวจพบ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic ms-1" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                    <textarea class="form-control" name="trace_points[${id}][area_detail]" rows="2"></textarea>
                </div>

                <label class="form-label small text-secondary fw-bold mb-2">สิ่งที่ตรวจพบ</label>
                <div class="bg-white p-3 rounded-3 border">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-check mb-2">
                                <input class="form-check-input trace-check-toggle cursor-pointer" type="checkbox" name="trace_points[${id}][entry_check]" value="1" id="chk_entry_${id}" style="transform: scale(1.1);">
                                <label class="form-check-label cursor-pointer text-dark" for="chk_entry_${id}">ทางเข้าของคนร้าย</label>
                            </div>
                            <div class="d-none trace-input-div">
                                <div class="d-flex gap-1 align-items-start">
                                    <textarea class="form-control form-control-sm bg-light" name="trace_points[${id}][entry_detail]" rows="2" placeholder="ระบุรายละเอียด..." style="resize: none;"></textarea>
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mb-2">
                                <input class="form-check-input trace-check-toggle cursor-pointer" type="checkbox" name="trace_points[${id}][pry_check]" value="1" id="chk_pry_${id}" style="transform: scale(1.1);">
                                <label class="form-check-label cursor-pointer text-dark" for="chk_pry_${id}">รอยงัดแงะ</label>
                            </div>
                            <div class="d-none trace-input-div">
                                <div class="d-flex gap-1 align-items-start">
                                    <textarea class="form-control form-control-sm bg-light" name="trace_points[${id}][pry_detail]" rows="2" placeholder="ระบุลักษณะรอยงัด..." style="resize: none;"></textarea>
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mb-2">
                                <input class="form-check-input trace-check-toggle cursor-pointer" type="checkbox" name="trace_points[${id}][rummage_check]" value="1" id="chk_rummage_${id}" style="transform: scale(1.1);">
                                <label class="form-check-label cursor-pointer text-dark" for="chk_rummage_${id}">ร่องรอยรื้อค้น</label>
                            </div>
                            <div class="d-none trace-input-div">
                                <div class="d-flex gap-1 align-items-start">
                                    <textarea class="form-control form-control-sm bg-light" name="trace_points[${id}][rummage_detail]" rows="2" placeholder="ระบุรายการทรัพย์สิน..." style="resize: none;"></textarea>
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            `;
    }

    // ฟังก์ชันรันเลขจุดใหม่ (จุดที่ 1, 2, 3...)
    function reIndexTracePoints() {
        $('#trace_point_container .trace-card').each(function(index) {
            $(this).find('.trace-index-label').text(`จุดที่ ${index + 1}`);
        });
    }

    // --- Evidence Card Helper ---
    function createEvidenceCard(index) {
        const id = Date.now() + Math.random().toString(16).slice(2);
        const formattedIndex = String(index).padStart(4, '0');

        const deleteButtonHtml = (index > 1) ? `
                <button type="button" class="btn btn-outline-danger btn-sm border-0 rounded-circle remove-evidence-btn d-flex align-items-center justify-content-center" 
                        style="width: 28px; height: 28px;" title="ลบรายการนี้">
                    <i class="fas fa-times"></i>
                </button>
            ` : '';

        return `
            <div class="bg-body-tertiary p-3 rounded-3 border mb-3 evidence-card" id="evidence_card_${id}">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-bold text-primary">วัตถุพยานลำดับที่ <span class="evidence-index-label">${formattedIndex}</span></span>
                    ${deleteButtonHtml}
                </div>

                <input type="hidden" class="evidence-no-input" name="evidence[${id}][no]" value="${formattedIndex}">
                <input type="hidden" class="evidence-type-select" name="evidence[${id}][type]" value="other">

                <div class="row g-3 mb-3">
                    <div class="col-md-12">
                        <label class="form-label small text-secondary fw-bold mb-1">วัตถุพยาน <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="evidence[${id}][detail]" placeholder="ระบุวัตถุพยาน...">
                            <button type="button" class="btn btn-outline-primary btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-12">
                        <label class="form-label small text-secondary fw-bold mb-1">ตำแหน่งที่ตรวจพบ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic ms-1" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                        <textarea class="form-control" name="evidence[${id}][area_found]" rows="2" placeholder="ระบุบริเวณที่พบ..."></textarea>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-white small">ป้ายหมายเลข</span>
                            <input type="text" class="form-control" name="evidence[${id}][label_no]" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-white small">Azimuth / พิกัด</span>
                            <input type="text" class="form-control" name="evidence[${id}][azimuth]" placeholder="ระบุพิกัด...">
                        </div>
                    </div>
                </div>

                <!-- จุดอ้างอิง -->
                <div class="bg-white p-3 rounded-3 border mb-3">
                    <label class="form-label small text-secondary fw-bold mb-2">จุดอ้างอิง</label>
                    <div class="row g-3">
                        ${[1, 2, 3, 4].map(i => `
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">จุดอ้างอิงที่ ${i}</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white small">ระยะ (ม.)</span>
                                    <input type="text" class="form-control" name="evidence[${id}][ref${i}_dist]" placeholder="0.00" inputmode="decimal" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\\..*)\\./g, '$1');">
                                    <span class="input-group-text bg-white small">จาก</span>
                                    <input type="text" class="form-control" name="evidence[${id}][ref${i}_desc]" placeholder="ระบุจุด...">
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small text-secondary fw-bold mb-1">หมายเหตุ <button type="button" class="btn btn-outline-primary btn-sm btn-hw-open btn-hw-dynamic ms-1" title="เขียนด้วยลายมือ" style="padding:1px 7px; font-size:0.75rem;"><i class="fas fa-pen"></i></button></label>
                        <textarea class="form-control" name="evidence[${id}][remark]" rows="2"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary fw-bold mb-1">การตรวจพิสูจน์</label>
                        <select class="form-select lab-unit-multi" multiple size="4" title="เลือกได้มากกว่า 1 กลุ่มงาน">
                            <option value="">-- เลือก (เลือกได้หลายข้อ) --</option>
                            <option value="fingerprint">ลายนิ้วมือแฝง</option>
                            <option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option>
                            <option value="chemical">เคมีฟิสิกส์</option>
                            <option value="drug">ยาเสพติด</option>
                            <option value="gun">อาวุธปืน</option>
                            <option value="document">เอกสาร</option>
                            <option value="digital">ดิจิทัล</option>
                        </select>
                        <input type="hidden" class="lab-unit-value" name="evidence[${id}][lab_unit]" value="">
                    </div>
                </div>

            </div>
            `;
    }

    // ฟังก์ชันรันเลข Index ใหม่ (0001, 0002, ...)
    function reIndexEvidence() {
        $('#evidence_container .evidence-card').each(function(index) {
            const num = index + 1;
            const formatted = String(num).padStart(4, '0');
            // อัปเดต Label หัวการ์ด
            $(this).find('.evidence-index-label').text(formatted);
            // อัปเดต Input Hidden
            $(this).find('.evidence-no-input').val(formatted);
        });
    }

    // --- File Upload Helpers ---
    function handleFiles(files) {
        if (files.length === 0) return;

        Array.from(files).forEach(file => {
            // 1. Validate Size (10MB)
            if (file.size > MAX_FILE_SIZE_MB * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'ไฟล์ขนาดใหญ่เกินไป',
                    text: `ไฟล์ ${file.name} มีขนาดเกิน ${MAX_FILE_SIZE_MB}MB`,
                    timer: 3000
                });
                return;
            }

            // 2. Generate UI ID
            const fileId = Date.now() + Math.random().toString(16).slice(2);

            // 3. Create Uploading UI (List Style)
            const uploadingHtml = `
                <div class="progress-wrapper p-3 mb-2 fade-in" id="uploading_${fileId}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-truncate" style="max-width: 80%;">${file.name}</span>
                        <button type="button" class="btn-close btn-sm" aria-label="Cancel" onclick="cancelUpload('${fileId}')"></button>
                    </div>
                    <div class="custom-progress mb-2">
                        <div class="custom-progress-bar" id="bar_${fileId}"></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center small text-muted">
                        <span id="percent_${fileId}">0% uploaded</span>
                        <span id="size_${fileId}">0.00 of ${(file.size / 1024 / 1024).toFixed(2)} MB</span>
                    </div>
                </div>
            `;
            $('#uploading_container').append(uploadingHtml);

            // 4. Simulate Upload
            simulateUpload(file, fileId);
        });
        // Reset Input เพื่อให้เลือกไฟล์ซ้ำได้ (แต่ยังไม่อัปเดต value จริง จนกว่าจะโหลดเสร็จ)
        if (inputPhotos) inputPhotos.val('');
    }

    // --- Simulate Upload Progress ---
    function simulateUpload(file, fileId) {
        const totalSize = file.size; // bytes
        const totalSizeMB = (totalSize / 1024 / 1024).toFixed(2);
        let loaded = 0;

        // จำลองความเร็ว upload (random)
        const interval = setInterval(() => {
            const chunk = Math.random() * (totalSize / 10); // 10% chunks
            loaded += chunk;

            if (loaded >= totalSize) {
                loaded = totalSize;
                clearInterval(interval);
                completeUpload(file, fileId); // เสร็จสิ้น
            }

            // Update UI
            const percent = Math.min(100, Math.round((loaded / totalSize) * 100));
            const loadedMB = (loaded / 1024 / 1024).toFixed(2);

            $(`#bar_${fileId}`).css('width', percent + '%');
            $(`#percent_${fileId}`).text(`${percent}% uploaded`);
            $(`#size_${fileId}`).text(`${loadedMB} of ${totalSizeMB} MB`);

        }, 100); // อัปเดตทุก 100ms

        // เก็บ interval ไว้เผื่อกดยกเลิก
        $(`#uploading_${fileId}`).data('interval', interval);
    }

    // --- Complete Upload -> Move to Grid ---
    function completeUpload(file, fileId) {
        // 1. Remove Uploading UI
        $(`#uploading_${fileId}`).fadeOut(300, function() {
            $(this).remove();
        });

        // บีบอัดรูปก่อนเก็บ (max 1920px, quality 0.7)
        compressImage(file, 1920, 1920, 0.7).then(function(compressedFile) {
            // เก็บไฟล์ที่บีบอัดแล้วเข้า Store
            var objectUrl = URL.createObjectURL(compressedFile);
            attachmentStore.push({
                file: compressedFile,
                id: fileId,
                src: objectUrl,
                name: file.name
            });

            // อัปเดต Input + render grid
            updateRealInput();
            renderPropertyAttachmentGrid();
        }); // end compressImage.then
    }

    function updateRealInput() {
        const dataTransfer = new DataTransfer();

        // วนลูปเอาไฟล์ที่เหลืออยู่ใน Store ยัดกลับเข้า DataTransfer
        // ★ ข้ามรูปเดิมที่โหลดจาก DB (ไม่มี item.file เพราะอยู่บน server แล้ว)
        //   มิฉะนั้น DataTransfer.items.add(undefined) จะ throw → render/count/รหัส ไม่อัปเดต
        attachmentStore.forEach(item => {
            if (item.file) dataTransfer.items.add(item.file);
        });

        // ยัดใส่ Input
        if (inputPhotos.length > 0) {
            inputPhotos[0].files = dataTransfer.files;
        }

        // อัปเดตตัวเลข
        const count = attachmentStore.length;
        $('#photo_amount').val(count);

        // Auto Fill รหัสภาพ (ใช้ชื่อไฟล์จริงของรูปแรก/รูปล่าสุด)
        if (count > 0) {
            $('#photo_id_start').val(attachmentStore[0].name || '1');
            $('#photo_id_end').val(attachmentStore[count - 1].name || String(count));
        } else {
            $('#photo_id_start').val('');
            $('#photo_id_end').val('');
        }

        // อัปเดต Badge บนปุ่มอัปโหลด เพื่อให้ User รู้ว่าเลือกไฟล์ค้างไว้กี่ไฟล์
        $('#file_count_badge').text(count);
    }

    // --- ฟังก์ชันเปิด Lightbox ---
    function showImagePreview(base64DataOrIndex, fileName) {
        let imgSrc = base64DataOrIndex;
        // ถ้าเป็น index (ตัวเลข) → ดึง base64 จาก existingPhotosStore
        if (typeof base64DataOrIndex === 'number') {
            imgSrc = existingPhotosStore[base64DataOrIndex]?.base64 || '';
            fileName = fileName || existingPhotosStore[base64DataOrIndex]?.fileName || 'photo';
        }

        // 1. ตั้งค่ารูปและชื่อ
        $('#lightboxImage').attr('src', imgSrc);
        $('#lightboxFileName').text(fileName);

        // 2. แสดง Lightbox (ลบ class d-none)
        $('#customLightbox').removeClass('d-none').hide().fadeIn(200); // fadeIn นุ่มๆ

        // 3. ล็อก Scrollbar ของหน้าหลัง
        $('body').addClass('lightbox-open');
    }

    // --- ฟังก์ชันปิด Lightbox ---
    function closeLightbox() {
        $('#customLightbox').fadeOut(200, function() {
            $(this).addClass('d-none');
        });
        $('body').removeClass('lightbox-open');
    }

    // --- Window Global Functions (สำหรับ onclick ใน HTML) ---
    window.showImagePreview = showImagePreview;
    window.closeLightbox = closeLightbox;
    window.cancelUpload = function(fileId) {
        // หยุด Interval
        const item = $(`#uploading_${fileId}`);
        clearInterval(item.data('interval'));
        item.fadeOut(300, () => item.remove());
    };

    window.removeAttachment = function(fileId) {
        // ลบ UI
        $(`#attach_${fileId}`).fadeOut(300, function() {
            $(this).remove();
            if ($('#attachments_grid').children().length === 0) {
                $('#attachments_wrapper').addClass('d-none');
            }
        });
        // ลบข้อมูลออกจาก Array กลาง โดยใช้ ID
        attachmentStore = attachmentStore.filter(item => item.id !== fileId);

        // อัปเดต Input จริงใหม่
        updateRealInput();
    };

    // --- ลบรูปเดิมที่โหลดมาจาก DB (ทรัพย์) ---
    window.removeExistingPhoto = function(filename) {
        Swal.fire({
            title: 'ลบรูปภาพนี้?',
            text: 'รูปภาพจะถูกลบเมื่อกดบันทึก',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ลบ',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                // เพิ่ม filename เข้ารายการลบ
                deletedExistingPhotos.push(filename);

                // ลบ UI
                $(`.attachment-item[data-filename="${filename}"]`).fadeOut(300, function() {
                    $(this).remove();
                    if ($('#attachments_grid').children().length === 0) {
                        $('#attachments_wrapper').addClass('d-none');
                    }
                });
                console.log('🗑️ Marked for deletion (Property):', filename, 'Total:', deletedExistingPhotos);
            }
        });
    };

    // =========================================================
    // 3. DOWNLOAD PDF FUNCTION
    // =========================================================
    function downloadPDF(id) {
        window.open('/csims/api/incidentCheckList/gen_pdf_router.php?incident_id=' + id, '_blank');
    }

    // Event delegation for PDF download button
    $(document).on('click', '.btn-download-pdf', function(e) {
        e.stopPropagation();
        e.preventDefault();
        e.stopImmediatePropagation();

        const id = $(this).data('pdf-id');
        downloadPDF(id);
        return false;
    });

    // Prevent disabled button from triggering modal
    $(document).on('click', '.btn-no-action', function(e) {
        e.stopPropagation();
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
    });

    // =========================================================
    // 3.1 REPORT PDF FUNCTION (Green Button) - เปิด Modal F-CS-11
    // =========================================================
    function reportPDF(id, complaintsType) {
        // เปิด Modal F-CS-11 แทนการเปิดหน้าต่างใหม่
        if (typeof openReportFormPdfModal === 'function') {
            openReportFormPdfModal(id, complaintsType);
        } else {
            // Fallback: เปิดหน้าต่างใหม่สำหรับดาวน์โหลด PDF รายงาน F-CS-11 (HTML Template)
            let phpFile = 'gen_pdf_report_html.php';
            if (complaintsType === '03') {
                phpFile = 'gen_pdf_report_bomb_html.php';
            }
            const url = `/csims/api/incidentCheckList/${phpFile}?incident_id=${id}`;
            window.open(url, '_blank');
        }
    }

    // Event delegation for Report PDF button (Green)
    $(document).on('click', '.btn-report-pdf', function(e) {
        e.stopPropagation();
        e.preventDefault();
        e.stopImmediatePropagation();

        const id = $(this).data('pdf-id');
        const complaintsType = $(this).data('pdf-type');
        reportPDF(id, complaintsType);
        return false;
    });

    // Event delegation for Report PDF button (Green)ห
    $(document).on('click', '.btn-qr', function(e) {
        // e.stopPropagation();
        // e.preventDefault();
        // e.stopImmediatePropagation();

        // const id = $(this).data('pdf-id');
        // reportPDF(id);
        // return false;
    });
    // =========================================================
    // ฟังก์ชันดึงข้อมูลรับแจ้งเหตุมา prefill ในฟอร์ม Modal ทรัพย์
    // =========================================================
    function prefillIncidentDataForProperty(incidentId, onComplete) {
        $.ajax({
            url: '/csims/api/ReceiveNoti/getDataByID.php',
            type: 'GET',
            dataType: 'json',
            data: {
                id: incidentId
            },
            success: function(response) {
                if (response.status === 'success' && response.data) {
                    const data = response.data;

                    // 1. รับแจ้งเหตุจาก สภ./สน. (Select2)
                    if (data.complaints_From && data.complaints_From.trim() !== '') {
                        // Standard form
                        if ($('#source_station option[value="' + data.complaints_From + '"]').length > 0) {
                            $('#source_station').val(data.complaints_From).trigger('change');
                        } else {
                            const newOption = new Option(data.complaints_From, data.complaints_From, true, true);
                            $('#source_station').append(newOption).trigger('change');
                        }
                        // PDF form
                        if ($('#ppf_source_station option[value="' + data.complaints_From + '"]').length > 0) {
                            $('#ppf_source_station').val(data.complaints_From);
                        } else {
                            const newOptionPdf = new Option(data.complaints_From, data.complaints_From, true, true);
                            $('#ppf_source_station').append(newOptionPdf);
                        }
                    }

                    // 2. จังหวัด
                    if (data.provinceID && data.provinceID.toString().trim() !== '') {
                        $('#provinceID').val(data.provinceID);
                        $('#ppf_provinceID').val(data.provinceID);
                    }

                    // 3. ช่องทางที่รับแจ้ง (t=phone, r=radio, d=document)
                    if (data.complaints_From_Device && data.complaints_From_Device.trim() !== '') {
                        let channelVal = '';
                        switch (data.complaints_From_Device) {
                            case 't':
                                channelVal = 'phone';
                                break;
                            case 'r':
                                channelVal = 'radio';
                                break;
                            case 'd':
                                channelVal = 'document';
                                break;
                            default:
                                channelVal = 'other';
                                if (data.complaints_From_Device_Other) {
                                    $('#report_channel_other_div').removeClass('d-none');
                                    $('#report_channel_other').val(data.complaints_From_Device_Other);
                                    $('#ppf_report_channel_other').val(data.complaints_From_Device_Other);
                                }
                        }
                        $('#report_channel').val(channelVal);
                        $('#ppf_report_channel').val(channelVal);
                    }

                    // 4. วันที่รับแจ้งเหตุ (create_date)
                    if (data.create_date && data.create_date.trim() !== '') {
                        const dt = data.create_date.replace(' ', 'T').substring(0, 16);
                        $('#report_datetime').val(dt);
                        $('#ppf_report_datetime').val(dt);
                    }

                    // 5. ข้อมูลพนักงานสอบสวน
                    if (data.inquiry_official_first_name && data.inquiry_official_first_name.trim() !== '') {
                        $('#investigator_firstname').val(data.inquiry_official_first_name);
                        $('#ppf_investigator_firstname').val(data.inquiry_official_first_name);
                    }
                    if (data.inquiry_official_last_name && data.inquiry_official_last_name.trim() !== '') {
                        $('#investigator_lastname').val(data.inquiry_official_last_name);
                        $('#ppf_investigator_lastname').val(data.inquiry_official_last_name);
                    }
                    if (data.inquiry_official_phone && data.inquiry_official_phone.trim() !== '') {
                        $('#investigator_phone').val(data.inquiry_official_phone);
                        $('#ppf_investigator_phone').val(data.inquiry_official_phone);
                    }

                    // 6. สถานที่เกิดเหตุ (location_crime)
                    if (data.location_crime && data.location_crime.trim() !== '') {
                        $('#incident_location').val(data.location_crime);
                        $('#ppf_incident_location').val(data.location_crime);
                    }

                    // 7. ข้อมูลผู้เสียหาย
                    if (data.suffer_first_name && data.suffer_first_name.trim() !== '') {
                        $('#victim_firstname').val(data.suffer_first_name);
                        $('#ppf_victim_firstname').val(data.suffer_first_name);
                    }
                    if (data.suffer_last_name && data.suffer_last_name.trim() !== '') {
                        $('#victim_lastname').val(data.suffer_last_name);
                        $('#ppf_victim_lastname').val(data.suffer_last_name);
                    }

                    // 8. วันเวลาที่เกิดเหตุ (time_Occurrence)
                    if (data.time_Occurrence && data.time_Occurrence.trim() !== '') {
                        const dtOccur = data.time_Occurrence.replace(' ', 'T').substring(0, 16);
                        $('#incident_datetime').val(dtOccur);
                        $('#investigator_known_datetime').val(dtOccur);
                        $('#inspection_datetime').val(dtOccur);
                        // PDF form
                        $('#ppf_incident_datetime').val(dtOccur);
                        $('#ppf_investigator_known_datetime').val(dtOccur);
                        $('#ppf_inspection_datetime').val(dtOccur);
                    }

                    // 9. เลขที่หนังสือ (daily_no) → ที่
                    if (data.daily_no && data.daily_no.toString().trim() !== '') {
                        $('#document_no').val(data.daily_no);
                        $('#ppf_document_no').val(data.daily_no);
                    }

                    // 10. เลขที่เอกสาร (receiveNoti_No) → แสดงในหัวฟอร์ม
                    if (data.receiveNoti_No && data.receiveNoti_No.trim() !== '') {
                        $('#doc_no').val(data.receiveNoti_No);
                        $('#ppf_doc_no').val(data.receiveNoti_No);
                    }

                    // 11. เลขที่รายงาน (receiveNotiReportNo)
                    if (data.receiveNotiReportNo && data.receiveNotiReportNo.trim() !== '') {
                        $('#report_no').val(data.receiveNotiReportNo);
                        $('#ppf_report_no').val(data.receiveNotiReportNo);
                        // แยกเลขรายงานสำหรับแสดงในหัวฟอร์ม
                        var rptParts = (data.receiveNotiReportNo || '').split('/');
                        var rptNum = rptParts[0] || '';
                        var rptYear = (rptParts[1] || '').toString().slice(-2);
                        $('#ppf_report_no_display').text(rptNum);
                        $('#ppf_report_year_display').text(rptYear);
                    }

                    // 12. พฤติการณ์คดี (basic_Info)
                    if (data.basic_Info && data.basic_Info.trim() !== '') {
                        $('#incidentCheckListForm [name="case_behavior"]').val(data.basic_Info);
                        $('#ppf_case_behavior').val(data.basic_Info);
                        window._basicInfoProperty = data.basic_Info;
                    }
                }
                if (typeof onComplete === 'function') onComplete();
            },
            error: function(xhr, status, error) {
                // Silent fail
                if (typeof onComplete === 'function') onComplete();
            }
        });
    }

    // =========================================================
    // 3.5 PREFILL INCIDENT DATA FOR LIFE MODAL
    // =========================================================
    function prefillIncidentDataForLife(incidentId, onComplete) {
        $.ajax({
            url: '/csims/api/ReceiveNoti/getDataByID.php',
            type: 'GET',
            dataType: 'json',
            data: {
                id: incidentId
            },
            success: function(response) {
                if (response.status === 'success' && response.data) {
                    const data = response.data;
                    // ★ ใช้ทั้ง standard form และ PDF form เพื่อให้ครอบคลุมทุกกรณี
                    const $formStd = $('#incidentCheckListFormLife');
                    const $formPdf = $('#lifeFormPdf');

                    // 1. สน/สภ. (Select2)
                    if (data.complaints_From && data.complaints_From.trim() !== '') {
                        // Standard form
                        if ($('#police_station_life option[value="' + data.complaints_From + '"]').length > 0) {
                            $('#police_station_life').val(data.complaints_From).trigger('change');
                        } else {
                            const newOption = new Option(data.complaints_From, data.complaints_From, true, true);
                            $('#police_station_life').append(newOption).trigger('change');
                        }
                        // PDF form
                        if ($('#lpf_police_station option[value="' + data.complaints_From + '"]').length > 0) {
                            $('#lpf_police_station').val(data.complaints_From);
                        } else {
                            const newOptionPdf = new Option(data.complaints_From, data.complaints_From, true, true);
                            $('#lpf_police_station').append(newOptionPdf);
                        }
                    }

                    // 2. ช่องทางที่รับแจ้ง (checkboxes)
                    if (data.complaints_From_Device && data.complaints_From_Device.trim() !== '') {
                        let notifyVal = '';
                        switch (data.complaints_From_Device) {
                            case 't':
                                $('#life_notify_phone').prop('checked', true);
                                notifyVal = 'ทางโทรศัพท์';
                                break;
                            case 'r':
                                $('#life_notify_radio').prop('checked', true);
                                notifyVal = 'ทางวิทยุสื่อสาร';
                                break;
                            case 'd':
                                $('#life_notify_letter').prop('checked', true);
                                notifyVal = 'ทางหนังสือ';
                                break;
                            default:
                                $('#life_notify_other').prop('checked', true);
                                $('#life_notify_other_text').show().prop('disabled', false);
                                notifyVal = 'อื่นๆ';
                                if (data.complaints_From_Device_Other) {
                                    $('#life_notify_other_text').val(data.complaints_From_Device_Other);
                                    $('#lpf_notify_other_text').val(data.complaints_From_Device_Other);
                                }
                        }
                        // PDF form - check the corresponding checkbox
                        if (notifyVal) {
                            $('#lifeFormPdf input[name="notify_method[]"][value="' + notifyVal + '"]').prop('checked', true);
                        }
                    }

                    // 3. วันที่รับแจ้งเหตุ (case_date_life + case_time_life)
                    if (data.create_date && data.create_date.trim() !== '') {
                        const dtParts = data.create_date.trim().split(' ');
                        if (dtParts[0]) {
                            $('#case_date_life').val(dtParts[0]);
                            $('#lpf_case_date').val(dtParts[0]);
                        }
                        if (dtParts[1]) {
                            const timeVal = dtParts[1].substring(0, 5);
                            $('#case_time_life').val(timeVal);
                            $('#lpf_case_time').val(timeVal);
                        }
                    }

                    // 4. ข้อมูลพนักงานสอบสวน (ชื่อ-นามสกุลรวมกัน)
                    let investigatorName = '';
                    if (data.inquiry_official_first_name && data.inquiry_official_first_name.trim() !== '') {
                        investigatorName = data.inquiry_official_first_name.trim();
                    }
                    if (data.inquiry_official_last_name && data.inquiry_official_last_name.trim() !== '') {
                        investigatorName += (investigatorName ? ' ' : '') + data.inquiry_official_last_name.trim();
                    }
                    if (investigatorName) {
                        $formStd.find('[name="investigator_name"]').val(investigatorName);
                        $formPdf.find('[name="investigator_name"]').val(investigatorName);
                        $('#lpf_investigator_name').val(investigatorName);
                    }

                    if (data.inquiry_official_phone && data.inquiry_official_phone.trim() !== '') {
                        $formStd.find('[name="investigator_phone"]').val(data.inquiry_official_phone);
                        $formPdf.find('[name="investigator_phone"]').val(data.inquiry_official_phone);
                        $('#lpf_investigator_phone').val(data.inquiry_official_phone);
                    }

                    // 5. สถานที่เกิดเหตุ
                    if (data.location_crime && data.location_crime.trim() !== '') {
                        $formStd.find('[name="crime_location"]').val(data.location_crime);
                        $formPdf.find('[name="crime_location"]').val(data.location_crime);
                        $('#lpf_crime_location').val(data.location_crime);
                    }

                    // 6. ข้อมูลผู้ประสบเหตุ (คนแรก)
                    let victimName = '';
                    if (data.suffer_first_name && data.suffer_first_name.trim() !== '') {
                        victimName = data.suffer_first_name.trim();
                    }
                    if (data.suffer_last_name && data.suffer_last_name.trim() !== '') {
                        victimName += (victimName ? ' ' : '') + data.suffer_last_name.trim();
                    }
                    if (victimName) {
                        $formStd.find('[name="victim_name_life[]"]').first().val(victimName);
                        // PDF form - first victim row
                        $('#lpf_victim_container').find('[name="victim_name[]"]').first().val(victimName);
                    }

                    // 7. วันเวลาที่เกิดเหตุ (time_Occurrence) → split เป็น date/time
                    if (data.time_Occurrence && data.time_Occurrence.trim() !== '') {
                        const dtOccur = data.time_Occurrence.trim().split(' ');
                        const occurDate = dtOccur[0] || '';
                        const occurTime = dtOccur[1] ? dtOccur[1].substring(0, 5) : '';

                        // วันที่ผู้เสียหายทราบเหตุ
                        if (occurDate) {
                            $formStd.find('[name="victim_know_date"]').val(occurDate);
                            $formPdf.find('[name="victim_know_date"]').val(occurDate);
                            $('#lpf_victim_know_date').val(occurDate);
                        }
                        if (occurTime) {
                            $formStd.find('[name="victim_know_time"]').val(occurTime);
                            $formPdf.find('[name="victim_know_time"]').val(occurTime);
                            $('#lpf_victim_know_time').val(occurTime);
                        }

                        // วันที่พนักงานสอบสวนทราบเหตุ
                        if (occurDate) {
                            $formStd.find('[name="officer_know_date"]').val(occurDate);
                            $formPdf.find('[name="officer_know_date"]').val(occurDate);
                            $('#lpf_officer_know_date').val(occurDate);
                        }
                        if (occurTime) {
                            $formStd.find('[name="officer_know_time"]').val(occurTime);
                            $formPdf.find('[name="officer_know_time"]').val(occurTime);
                            $('#lpf_officer_know_time').val(occurTime);
                        }

                        // วันที่ทำการตรวจสถานที่เกิดเหตุ
                        if (occurDate) {
                            $formStd.find('[name="inspect_date"]').val(occurDate);
                            $formPdf.find('[name="inspect_date"]').val(occurDate);
                            $('#lpf_inspect_date').val(occurDate);
                        }
                        if (occurTime) {
                            $formStd.find('[name="inspect_time"]').val(occurTime);
                            $formPdf.find('[name="inspect_time"]').val(occurTime);
                            $('#lpf_inspect_time').val(occurTime);
                        }
                    }

                    // 8. เลขที่หนังสือ (daily_no) → ที่
                    if (data.daily_no && data.daily_no.toString().trim() !== '') {
                        $formStd.find('[name="location_at"]').val(data.daily_no);
                        $formPdf.find('[name="location_at"]').val(data.daily_no);
                        $('#lpf_location_at').val(data.daily_no);
                    }

                    // 9. เลขที่เอกสาร (receiveNoti_No)
                    if (data.receiveNoti_No && data.receiveNoti_No.trim() !== '') {
                        $('#doc_no_life').val(data.receiveNoti_No);
                        $('#lpf_doc_no').val(data.receiveNoti_No);
                        $('#lpf_case_doc_no').val(data.receiveNoti_No);
                    }

                    // 10. เลขที่รายงาน (receiveNotiReportNo)
                    if (data.receiveNotiReportNo && data.receiveNotiReportNo.trim() !== '') {
                        $('#report_no_life').val(data.receiveNotiReportNo);
                        $('#lpf_report_no').val(data.receiveNotiReportNo);
                        // แยกเลขรายงานสำหรับแสดงในหัวฟอร์ม
                        var rptParts = (data.receiveNotiReportNo || '').split('/');
                        var rptNum = rptParts[0] || '';
                        var rptYear = (rptParts[1] || '').toString().slice(-2);
                        $('#lpf_report_no_display').text(rptNum);
                        $('#lpf_report_year_display').text(rptYear);
                    }

                    // 11. receiveNoti_id (hidden field)
                    if (data.id) {
                        $('#receiveNoti_id_life').val(data.id);
                        $('#lpf_receiveNoti_id').val(data.id);
                    }

                    // 12. จังหวัด (provinceID) - ถ้ามี
                    if (data.provinceID && data.provinceID.toString().trim() !== '') {
                        $formStd.find('[name="provinceID"]').val(data.provinceID);
                        $formPdf.find('[name="provinceID"]').val(data.provinceID);
                    }

                    // 13. พฤติการณ์คดี (basic_Info)
                    if (data.basic_Info && data.basic_Info.trim() !== '') {
                        $formStd.find('[name="case_behavior"]').val(data.basic_Info);
                        $formPdf.find('[name="case_behavior"]').val(data.basic_Info);
                        window._basicInfoLife = data.basic_Info;
                    }

                    // ★ เรียก onComplete callback หลัง prefill เสร็จ
                    if (typeof onComplete === 'function') {
                        setTimeout(function() {
                            onComplete();
                        }, 300);
                    }
                } else {
                    // ★ กรณีไม่มีข้อมูล ก็ต้องเรียก onComplete เพื่อซ่อน loading overlay
                    if (typeof onComplete === 'function') {
                        setTimeout(function() {
                            onComplete();
                        }, 300);
                    }
                }
            },
            error: function(xhr, status, error) {
                // ★ กรณี error ก็ต้องเรียก onComplete เพื่อซ่อน loading overlay
                if (typeof onComplete === 'function') {
                    setTimeout(function() {
                        onComplete();
                    }, 300);
                }
            }
        });
    }

    function splitReceiveNotiDateTime(dateTimeValue) {
        const raw = (dateTimeValue || '').toString().trim();
        if (!raw) {
            return {
                date: '',
                time: ''
            };
        }
        const normalized = raw.replace('T', ' ');
        const parts = normalized.split(/\s+/);
        return {
            date: parts[0] || '',
            time: parts[1] ? parts[1].substring(0, 5) : ''
        };
    }

    function mapReceiveNotiChannel(deviceCode) {
        switch ((deviceCode || '').toString().trim()) {
            case 't':
                return 'ทางโทรศัพท์';
            case 'r':
                return 'ทางวิทยุสื่อสาร';
            case 'd':
                return 'ทางหนังสือ';
            default:
                return 'อื่นๆ';
        }
    }

    function setSelectValueWithOption($select, value) {
        const val = (value || '').toString().trim();
        if (!val || !$select || $select.length === 0) {
            return;
        }
        if ($select.find('option[value="' + val + '"]').length > 0) {
            $select.val(val).trigger('change');
            return;
        }
        const newOption = new Option(val, val, true, true);
        $select.append(newOption).trigger('change');
    }

    function prefillIncidentDataForBomb(incidentId, onComplete) {
        $.ajax({
            url: '/csims/api/ReceiveNoti/getDataByID.php',
            type: 'GET',
            dataType: 'json',
            data: {
                id: incidentId
            },
            success: function(response) {
                if (response.status === 'success' && response.data) {
                    const data = response.data;
                    const $form = $('#incidentCheckListFormBomb');

                    setSelectValueWithOption($('#police_station_bomb'), data.complaints_From);

                    const channelVal = mapReceiveNotiChannel(data.complaints_From_Device);
                    $form.find('input[name="notify_method[]"]').prop('checked', false);
                    if (channelVal) {
                        $form.find('input[name="notify_method[]"][value="' + channelVal + '"]').prop('checked', true).trigger('change');
                    }
                    if (channelVal === 'อื่นๆ') {
                        const otherText = (data.complaints_From_Device_Other || '').toString().trim();
                        if (otherText) {
                            $('#bomb_notify_other_text').show().prop('disabled', false).val(otherText);
                        }
                    }

                    const receiveDateTime = splitReceiveNotiDateTime(data.create_date);
                    if (receiveDateTime.date) {
                        $('#case_date_bomb').val(receiveDateTime.date);
                    }
                    if (receiveDateTime.time) {
                        $('#case_time_bomb').val(receiveDateTime.time);
                    }

                    const investigatorName = [data.inquiry_official_first_name, data.inquiry_official_last_name]
                        .map(function(v) {
                            return (v || '').toString().trim();
                        })
                        .filter(Boolean)
                        .join(' ');
                    if (investigatorName) {
                        $form.find('[name="investigator_name"]').val(investigatorName);
                    }
                    if (data.inquiry_official_phone && data.inquiry_official_phone.toString().trim() !== '') {
                        $form.find('[name="investigator_phone"]').val(data.inquiry_official_phone);
                    }
                    if (data.location_crime && data.location_crime.toString().trim() !== '') {
                        $form.find('[name="crime_location"]').val(data.location_crime);
                    }

                    const occurDateTime = splitReceiveNotiDateTime(data.time_Occurrence);
                    if (occurDateTime.date) {
                        $form.find('[name="victim_know_date"]').val(occurDateTime.date);
                        $form.find('[name="officer_know_date"]').val(occurDateTime.date);
                        $form.find('[name="inspect_date"]').val(occurDateTime.date);
                    }
                    if (occurDateTime.time) {
                        $form.find('[name="victim_know_time"]').val(occurDateTime.time);
                        $form.find('[name="officer_know_time"]').val(occurDateTime.time);
                        $form.find('[name="inspect_time"]').val(occurDateTime.time);
                    }

                    const victimName = [data.suffer_first_name, data.suffer_last_name]
                        .map(function(v) {
                            return (v || '').toString().trim();
                        })
                        .filter(Boolean)
                        .join(' ');
                    if (victimName) {
                        $form.find('[name="victim_name_bomb[]"]').first().val(victimName);
                    }

                    // พฤติการณ์คดี (basic_Info)
                    if (data.basic_Info && data.basic_Info.trim() !== '') {
                        $form.find('[name="case_behavior"]').val(data.basic_Info);
                        window._basicInfoBomb = data.basic_Info;
                    }
                }
                if (typeof onComplete === 'function') onComplete();
            },
            error: function() {
                if (typeof onComplete === 'function') onComplete();
            }
        });
    }

    function prefillIncidentDataForTraffic(incidentId, onComplete) {
        $.ajax({
            url: '/csims/api/ReceiveNoti/getDataByID.php',
            type: 'GET',
            dataType: 'json',
            data: {
                id: incidentId
            },
            success: function(response) {
                if (response.status === 'success' && response.data) {
                    const data = response.data;
                    const $form = $('#incidentCheckListFormTraffic');

                    setSelectValueWithOption($('#police_station_traffic'), data.complaints_From);

                    const channelVal = mapReceiveNotiChannel(data.complaints_From_Device);
                    $form.find('input[name="notify_method[]"]').prop('checked', false);
                    if (channelVal) {
                        $form.find('input[name="notify_method[]"][value="' + channelVal + '"]').prop('checked', true).trigger('change');
                    }
                    if (channelVal === 'อื่นๆ') {
                        const otherText = (data.complaints_From_Device_Other || '').toString().trim();
                        if (otherText) {
                            $('#traffic_notify_other_text').show().prop('disabled', false).val(otherText);
                        }
                    }

                    const receiveDateTime = splitReceiveNotiDateTime(data.create_date);
                    if (receiveDateTime.date) {
                        $('#case_date_traffic').val(receiveDateTime.date);
                    }
                    if (receiveDateTime.time) {
                        $('#case_time_traffic').val(receiveDateTime.time);
                    }

                    const investigatorName = [data.inquiry_official_first_name, data.inquiry_official_last_name]
                        .map(function(v) {
                            return (v || '').toString().trim();
                        })
                        .filter(Boolean)
                        .join(' ');
                    if (investigatorName) {
                        $form.find('[name="investigator_name"]').val(investigatorName);
                    }
                    if (data.inquiry_official_phone && data.inquiry_official_phone.toString().trim() !== '') {
                        $form.find('[name="investigator_phone"]').val(data.inquiry_official_phone);
                    }
                    if (data.location_crime && data.location_crime.toString().trim() !== '') {
                        $form.find('[name="crime_location"]').val(data.location_crime);
                    }

                    const occurDateTime = splitReceiveNotiDateTime(data.time_Occurrence);
                    if (occurDateTime.date) {
                        $form.find('[name="victim_know_date"]').val(occurDateTime.date);
                        $form.find('[name="officer_know_date"]').val(occurDateTime.date);
                        $form.find('[name="inspect_date"]').val(occurDateTime.date);
                    }
                    if (occurDateTime.time) {
                        $form.find('[name="victim_know_time"]').val(occurDateTime.time);
                        $form.find('[name="officer_know_time"]').val(occurDateTime.time);
                        $form.find('[name="inspect_time"]').val(occurDateTime.time);
                    }

                    // พฤติการณ์คดี (basic_Info)
                    if (data.basic_Info && data.basic_Info.trim() !== '') {
                        $form.find('[name="case_behavior"]').val(data.basic_Info);
                        $('#tpf_case_behavior').val(data.basic_Info);
                        window._basicInfoTraffic = data.basic_Info;
                    }
                }
                if (typeof onComplete === 'function') onComplete();
            },
            error: function() {
                if (typeof onComplete === 'function') onComplete();
            }
        });
    }

    // =========================================================
    // 3.4.3 PREFILL INCIDENT DATA FOR FINGERPRINT (ดึงข้อมูลรับแจ้งเหตุ → ลายนิ้วมือ)
    // =========================================================
    function prefillIncidentDataForFingerprint(incidentId) {
        console.log('[FP Prefill] เริ่มดึงข้อมูลรับแจ้งเหตุ ID:', incidentId);
        $.ajax({
            url: '/csims/api/ReceiveNoti/getDataByID.php',
            type: 'GET',
            dataType: 'json',
            data: {
                id: incidentId
            },
            success: function(response) {
                console.log('[FP Prefill] API response:', response);
                if (response.status === 'success' && response.data) {
                    const data = response.data;

                    // 1. สน./สภ. (select dropdown ทั้ง 2 modal)
                    if (data.complaints_From && data.complaints_From.trim() !== '') {
                        const val = data.complaints_From.trim();
                        if ($('#fp_police_station option[value="' + val + '"]').length > 0) {
                            $('#fp_police_station').val(val);
                        } else {
                            $('#fp_police_station').append(new Option(val, val, true, true));
                        }
                        if ($('#fpn_police_station option[value="' + val + '"]').length > 0) {
                            $('#fpn_police_station').val(val);
                        } else {
                            $('#fpn_police_station').append(new Option(val, val, true, true));
                        }
                        console.log('[FP Prefill] ตั้งค่า สน./สภ.:', val);
                    }

                    // 2. วันเวลาที่รับแจ้ง (create_date → receive_date + receive_time)
                    if (data.create_date && data.create_date.trim() !== '') {
                        const dtParts = data.create_date.trim().split(' ');
                        const rDate = dtParts[0] || '';
                        const rTime = dtParts[1] ? dtParts[1].substring(0, 5) : '';
                        if (rDate) {
                            $('#fp_receive_date').val(rDate);
                            $('#fpn_receive_date').val(rDate);
                        }
                        if (rTime) {
                            $('#fp_receive_time').val(rTime);
                            $('#fpn_receive_time').val(rTime);
                        }
                        console.log('[FP Prefill] วันที่รับแจ้ง:', rDate, rTime);
                    }

                    // 3. วันเวลาที่เกิดเหตุ / ทราบเหตุ / ตรวจเก็บ (time_Occurrence)
                    if (data.time_Occurrence && data.time_Occurrence.trim() !== '') {
                        const dtOccur = data.time_Occurrence.trim().split(' ');
                        const occurDate = dtOccur[0] || '';
                        const occurTime = dtOccur[1] ? dtOccur[1].substring(0, 5) : '';

                        if (occurDate) {
                            $('#fp_incident_date').val(occurDate);
                            $('#fpn_incident_date').val(occurDate);
                        }
                        if (occurTime) {
                            $('#fp_incident_time').val(occurTime);
                            $('#fpn_incident_time').val(occurTime);
                        }
                        if (occurDate) {
                            $('#fp_known_date').val(occurDate);
                            $('#fpn_known_date').val(occurDate);
                        }
                        if (occurTime) {
                            $('#fp_known_time').val(occurTime);
                            $('#fpn_known_time').val(occurTime);
                        }
                        if (occurDate) {
                            $('#fp_collect_date').val(occurDate);
                            $('#fpn_collect_date').val(occurDate);
                        }
                        if (occurTime) {
                            $('#fp_collect_time').val(occurTime);
                            $('#fpn_collect_time').val(occurTime);
                        }
                        console.log('[FP Prefill] วันที่เกิดเหตุ:', occurDate, occurTime);
                    }

                    console.log('[FP Prefill] ✅ Prefill เสร็จสิ้น');
                } else {
                    console.warn('[FP Prefill] ⚠️ API ตอบกลับไม่สำเร็จ:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('[FP Prefill] ❌ AJAX Error:', status, error);
            }
        });
    }

    // =========================================================
    // 3.5.1 RESET PROPERTY FORM (รีเซ็ตฟอร์มทรัพย์ก่อนโหลดข้อมูลใหม่)
    // =========================================================
    function resetPropertyForm() {
        const modal = $('#addCheckListModal');

        // รีเซ็ตข้อมูลการแก้ไข
        $('#editInfoProperty').addClass('d-none');

        // 1. รีเซ็ต text inputs, textareas, number inputs, date/datetime inputs
        modal.find('input[type="text"], input[type="number"], input[type="datetime-local"], input[type="date"], textarea').val('');

        // ★ คืนค่า default วันที่ปัจจุบันให้ field "ลง"
        var _today = new Date().toISOString().slice(0, 10);
        $('#document_date').val(_today);
        $('#ppf_document_date').val(_today);

        // 2. รีเซ็ต checkboxes ทั้งหมด
        modal.find('input[type="checkbox"]').prop('checked', false);

        // 3. รีเซ็ต radio buttons
        modal.find('input[type="radio"]').prop('checked', false);

        // 4. รีเซ็ต select dropdowns (ไม่ใช่ Select2)
        modal.find('select').not('.inspector-select, .user-select-box').val('');

        // 5. รีเซ็ต Select2 fields
        if ($('#source_station').hasClass('select2-hidden-accessible')) {
            $('#source_station').val(null).trigger('change');
        }
        if ($('#receiver_id').hasClass('select2-hidden-accessible')) {
            $('#receiver_id').val(null).trigger('change');
        }
        if ($('#deliverer_id').hasClass('select2-hidden-accessible')) {
            $('#deliverer_id').val(null).trigger('change');
        }

        // 6. รีเซ็ต Inspector rows → เหลือแค่ 1 row ว่างๆ
        const $inspContainer = $('#inspector_container');
        $inspContainer.find('.inspector-row').slice(1).remove(); // ลบ row เกิน เหลือแค่ตัวแรก
        const $firstInspSelect = $inspContainer.find('.inspector-select').first();
        if ($firstInspSelect.hasClass('select2-hidden-accessible')) {
            $firstInspSelect.val(null).trigger('change');
        } else {
            $firstInspSelect.val('');
        }

        // 7. รีเซ็ต Trace Point cards → ลบให้หมดแล้วสร้างใหม่ 1 card
        $('#trace_point_container').empty();
        const $newTrace = $(createTraceCard(1));
        $('#trace_point_container').append($newTrace);

        // 8. รีเซ็ต Evidence cards → ลบให้หมดแล้วสร้างใหม่ 1 card
        $('#evidence_container').empty();
        const $newEvidence = $(createEvidenceCard(1));
        $('#evidence_container').append($newEvidence);

        // 8.1 รีเซ็ต Section 10: Measurement cards (Property)
        $('#measurement_container_property').empty();
        if (typeof measurementCounterProperty !== 'undefined') {
            measurementCounterProperty = 0;
        }
        if (typeof addMeasurementCardProperty === 'function') {
            addMeasurementCardProperty();
        }
        $('#measurement_inspection_date_property').val('');
        $('#measurement_recorder_property').val('');
        $('#measurement_datetime_property').val('');

        // 9. รีเซ็ต Signatures (receiver, deliverer, sketch)
        ['sig-canvas-receiver', 'sig-canvas-deliverer', 'scene_sketch_canvas'].forEach(function(canvasId) {
            if (signaturePads[canvasId]) {
                signaturePads[canvasId].clear();
            }
        });

        // 10. รีเซ็ต hidden fields (ยกเว้น receiveNoti_id, doc_no, report_no ที่จะ set ใหม่)
        // ไม่ต้องรีเซ็ตเพราะจะถูก set ก่อนเปิด modal

        // 11. รีเซ็ต building detail/floor inputs (disable + clear)
        modal.find('[id^="detail_"]').val('').prop('disabled', true);
        modal.find('[id^="floor_"]').val('').prop('disabled', true);

        // 12. ซ่อน conditional sections
        $('#case_type_other_div').addClass('d-none');
        $('#report_channel_other_div').addClass('d-none');
        $('#victim_type_other_div').addClass('d-none');
        $('#preservation_detail_div').addClass('d-none');
        $('#trace_details').addClass('opacity-50').css({
            'pointer-events': 'none',
            'opacity': ''
        });
        modal.find('.entry-input-div').addClass('d-none');
        modal.find('#entry_other_trace_detail').val('').prop('disabled', true);
        modal.find('#tool_other_detail').val('').prop('disabled', true);
        modal.find('#weapon_other_detail').val('').prop('disabled', true);

        // 13. รีเซ็ต photo fields
        $('#photo_id_start').val('');
        $('#photo_id_end').val('');
        $('#photo_amount').val('');

        // 14. รีเซ็ต recorder info
        $('#recorder_name').val('');
        $('#recorder_datetime').val('');

        // 15. รีเซ็ต stolen property
        $('#stolen_property').val('');

        // 16. รีเซ็ต receiver/deliverer position display
        $('#receiver_position').val('');
        $('#deliverer_position').val('');

        // 17. รีเซ็ต case behavior
        $('#case_behavior').val('');

        // 18. รีเซ็ต perpetrator count
        $('#perpetrator_count').val('');

        // 19. รีเซ็ต injury detail
        $('#injury_detail').val('');

        // 20. รีเซ็ต binding material
        $('#binding_material').val('');

        // 21. รีเซ็ต trace width
        $('#trace_width').val('');

        // 22. รีเซ็ต scene description fields
        $('#scene_front, #scene_left, #scene_right, #scene_back, #scene_interior, #scene_point').val('');

        // 23. รีเซ็ต preservation text
        $('#preservation_text').val('');

        // 24. ล้าง sketch remark
        $('#sketch_remark').val('');

        // 25. ล้างรูปภาพ attachments
        $('#attachments_grid').empty();
        $('#attachments_wrapper').addClass('d-none');
        $('#file_count_badge').text('0');
        // รีเซ็ต arrays ที่เก็บรูปใหม่ + รูปที่จะลบ + รูปเดิม
        attachmentStore.length = 0;
        deletedExistingPhotos.length = 0;
        existingPhotosStore = [];

        // 26. ล้าง file input
        if (inputPhotos && inputPhotos.length) {
            inputPhotos.val('');
        }
    }

    // =========================================================
    // Helper: โหลดรูปจาก URL ลง Canvas/SignaturePad (ใช้ร่วมทั้ง standard + PDF)
    // =========================================================
    function loadSigFromUrl(canvasId, imgUrl, hiddenInputId, fileIdValue) {
        loadImageToCanvas(canvasId, imgUrl, hiddenInputId, fileIdValue);
    }

    // =========================================================
    // 3.6 LOAD SAVED PROPERTY DATA (โหลดข้อมูลที่บันทึกไว้กลับมาแก้ไข)
    // =========================================================
    function loadPropertyData(incidentId, onComplete) {
        $.ajax({
            url: '/csims/api/incidentCheckList/getPropertyData.php',
            type: 'GET',
            dataType: 'json',
            data: {
                incident_id: incidentId
            },
            success: function(response) {
                if (!response.success || !response.data) {
                    // ★ ถ้าไม่มี checklist data ก็ยังเก็บ basic_info
                    if (response && response.basic_info) window._basicInfoProperty = response.basic_info;
                    if (typeof onComplete === 'function') onComplete(null);
                    return;
                }

                // ★ เก็บ basic_info ไว้ใน global เพื่อ auto-fill พฤติการณ์คดี
                if (response.basic_info) window._basicInfoProperty = response.basic_info;

                const d = response.data;
                const gi = d.general_info || {};
                const sc = d.scene_characteristics || {};
                const cb = d.case_behavior_info || {};
                const ho = d.handover || {};
                const am = d.attachments_meta || {};
                const ri = d.recorder_info || {};

                // ==================== แสดงจำนวนการแก้ไข ====================
                if (response.edit_info) {
                    const ei = response.edit_info;
                    $('#editCountProperty').text(ei.count_edit || 0);
                    $('#editCountPropertyPdf').text(ei.count_edit || 0);
                    if (ei.edit_date) {
                        const ed = new Date(ei.edit_date);
                        const dd = String(ed.getDate()).padStart(2, '0');
                        const mm = String(ed.getMonth() + 1).padStart(2, '0');
                        const yyyy = ed.getFullYear() + 543;
                        const hh = String(ed.getHours()).padStart(2, '0');
                        const mi = String(ed.getMinutes()).padStart(2, '0');
                        $('#editDateProperty').text(dd + '/' + mm + '/' + yyyy + ' ' + hh + ':' + mi + ' น.');
                    } else {
                        $('#editDateProperty').text('-');
                    }
                    $('#editInfoProperty').removeClass('d-none');
                    $('#editInfoPropertyPdf').removeClass('d-none');
                }

                // ==================== 1. ข้อมูลการรับแจ้งเหตุ ====================

                // ประเภทคดี
                if (gi.case_type) {
                    $('#case_type').val(gi.case_type);
                    if (gi.case_type === 'other') {
                        $('#case_type_other_div').removeClass('d-none');
                        if (gi.case_type_other) $('#case_type_other').val(gi.case_type_other);
                    }
                }

                // วันที่รับแจ้งเหตุ
                if (gi.report_datetime) $('#report_datetime').val(gi.report_datetime);

                // รับแจ้งเหตุจาก (Select2)
                if (gi.source_station) {
                    if ($('#source_station option[value="' + gi.source_station + '"]').length > 0) {
                        $('#source_station').val(gi.source_station).trigger('change');
                    } else {
                        const opt = new Option(gi.source_station, gi.source_station, true, true);
                        $('#source_station').append(opt).trigger('change');
                    }
                }

                // จังหวัด
                if (gi.province_id) $('#provinceID').val(gi.province_id);

                // ช่องทางที่รับแจ้ง
                if (gi.report_channel) {
                    $('#report_channel').val(gi.report_channel);
                    if (gi.report_channel === 'other') {
                        $('#report_channel_other_div').removeClass('d-none');
                        if (gi.report_channel_other) $('#report_channel_other').val(gi.report_channel_other);
                    }
                }

                // ที่ / ลง
                if (gi.document_no) $('#document_no').val(gi.document_no);
                $('#document_date').val(gi.document_date || new Date().toISOString().slice(0, 10));

                // ข้อมูลพนักงานสอบสวน
                if (gi.investigator) {
                    if (gi.investigator.firstname) $('#investigator_firstname').val(gi.investigator.firstname);
                    if (gi.investigator.lastname) $('#investigator_lastname').val(gi.investigator.lastname);
                    if (gi.investigator.phone) $('#investigator_phone').val(gi.investigator.phone);
                }

                // ==================== 2. สถานที่เกิดเหตุ ====================

                if (gi.location_detail) $('#incident_location').val(gi.location_detail);

                // ผู้เสียหาย
                if (gi.victim) {
                    if (gi.victim.type) {
                        $('input[name="victim_type"][value="' + gi.victim.type + '"]').prop('checked', true);
                        if (gi.victim.type === 'other') {
                            $('#victim_type_other_div').removeClass('d-none');
                            if (gi.victim.type_other) $('#victim_type_other_text').val(gi.victim.type_other);
                        }
                    }
                    if (gi.victim.firstname) $('#victim_firstname').val(gi.victim.firstname);
                    if (gi.victim.lastname) $('#victim_lastname').val(gi.victim.lastname);
                    if (gi.victim.age) $('#victim_age').val(gi.victim.age);
                }

                // ==================== 3. วันเวลาที่ทราบเหตุ ====================
                if (gi.incident_datetime) $('#incident_datetime').val(gi.incident_datetime);
                if (gi.investigator_known_datetime) $('#investigator_known_datetime').val(gi.investigator_known_datetime);

                // ==================== 4. วันเวลาที่ตรวจเหตุ ====================
                if (gi.inspection_datetime) $('#inspection_datetime').val(gi.inspection_datetime);
                if (gi.inspection_additional_datetime) $('#inspection_additional_datetime').val(gi.inspection_additional_datetime);
                if (gi.inspection_end_datetime) $('#inspection_end_datetime').val(gi.inspection_end_datetime);

                // ==================== 5. ผู้ตรวจสถานที่เกิดเหตุ ====================
                if (d.inspectors && d.inspectors.length > 0) {
                    // ลบ inspector rows เดิม (ทำลาย Select2 ก่อน)
                    $('#inspector_container .inspector-select').each(function() {
                        if ($(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2('destroy');
                        }
                    });
                    $('#inspector_container').empty();

                    d.inspectors.forEach(function(inspId, idx) {
                        const count = idx + 1;
                        const deleteBtn = (count > 1) ? `
                            <div class="ms-2">
                                <button type="button" class="btn btn-outline-danger btn-sm border-0 rounded-circle remove-inspector-btn d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" title="ลบ">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>` : '<div class="ms-2" style="width: 32px;"></div>';
                        const rowHtml = `
                            <div class="d-flex align-items-center mb-3 inspector-row">
                                <div class="text-end pe-3" style="width: 50px;">
                                    <span class="fw-bold text-secondary index-label">5.${count}</span>
                                </div>
                                <div class="flex-grow-1">
                                    <select class="form-select inspector-select" name="inspector_id[]">
                                        <?php echo str_replace("'", "\\'", $inspectorOptionsProp); ?>
                                    </select>
                                </div>
                                ${deleteBtn}
                            </div>`;
                        $('#inspector_container').append(rowHtml);
                    });

                    // ★ Re-init Select2 แล้วตั้งค่า
                    $('#inspector_container .inspector-select').each(function(idx) {
                        $(this).select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            placeholder: 'กรุณาเลือก',
                            allowClear: true,
                            dropdownParent: $('#addCheckListModal'),
                            dropdownAutoWidth: true
                        });
                        if (idx < d.inspectors.length) {
                            $(this).val(d.inspectors[idx]).trigger('change');
                        }
                    });
                }

                // ==================== 6. ลักษณะสถานที่เกิดเหตุ ====================

                // การรักษาสถานที่
                if (sc.preservation) {
                    $('input[name="scene_preservation"][value="' + sc.preservation + '"]').prop('checked', true);
                    if (sc.preservation) {
                        $('#preservation_detail_div').removeClass('d-none');
                        $('#preservation_text').prop('disabled', false);
                        if (sc.preservation === 'yes') {
                            $('#preservation_label').text('มีการรักษาโดย:');
                        } else {
                            $('#preservation_label').text('ไม่มีการรักษาเนื่องจาก:');
                        }
                    }
                }
                if (sc.preservation_detail) $('#preservation_text').val(sc.preservation_detail);

                // ลักษณะภายนอก (สิ่งปลูกสร้าง)
                if (sc.building_floor) {
                    $('#building_floor').val(sc.building_floor);
                }
                if (sc.building_types && sc.building_types.length > 0) {
                    sc.building_types.forEach(function(bt) {
                        // รองรับทั้งแบบเก่า (object) และแบบใหม่ (string)
                        const typeVal = (typeof bt === 'object') ? bt.type : bt;
                        if (typeVal) {
                            $('input[name="building_type[]"][value="' + typeVal + '"]').prop('checked', true);
                            // แสดงช่องรายละเอียด อื่นๆ ถ้าเลือก other
                            if (typeVal === 'other') {
                                $('#building_other_detail_row').show();
                            }
                        }
                    });
                }
                if (sc.building_detail_other) {
                    $('#building_detail_other').val(sc.building_detail_other);
                    $('#building_other_detail_row').show();
                }

                // รั้วกั้น
                if (sc.fence) {
                    $('input[name="fence_type"][value="' + sc.fence + '"]').prop('checked', true);
                }

                // สภาพแวดล้อม
                if (sc.surroundings) {
                    if (sc.surroundings.front) $('input[name="scene_front"]').val(sc.surroundings.front);
                    if (sc.surroundings.left) $('input[name="scene_left"]').val(sc.surroundings.left);
                    if (sc.surroundings.right) $('input[name="scene_right"]').val(sc.surroundings.right);
                    if (sc.surroundings.back) $('input[name="scene_back"]').val(sc.surroundings.back);
                }

                // ลักษณะภายใน / จุดที่เกิดเหตุ
                if (sc.interior_detail) $('#scene_interior').val(sc.interior_detail);
                if (sc.point_detail) $('#scene_point').val(sc.point_detail);

                // ==================== 7. ผลการตรวจสถานที่เกิดเหตุ ====================

                // พฤติการณ์ของคดี
                if (cb.behavior_text) {
                    $('#case_behavior').val(cb.behavior_text);
                } else if (window._basicInfoProperty) {
                    $('#case_behavior').val(window._basicInfoProperty);
                }

                // ทางเข้าของคนร้าย
                if (cb.entry_points && cb.entry_points.length > 0) {
                    cb.entry_points.forEach(function(ep) {
                        $('input[name="entry_point[]"][value="' + ep + '"]').prop('checked', true);
                    });
                    // เปิด trace_details section ถ้ามี found_trace
                    if (cb.entry_points.includes('found_trace')) {
                        $('#trace_details').removeClass('opacity-50').css({
                            'pointer-events': 'auto',
                            'opacity': '1'
                        });
                    }
                }
                if (cb.entry_other_detail) {
                    $('#entry_other_trace_detail').val(cb.entry_other_detail).prop('disabled', false);
                }

                // บริเวณ/ตำแหน่ง entry_locations_detail
                if (cb.entry_locations_detail) {
                    Object.keys(cb.entry_locations_detail).forEach(function(key) {
                        $('#entry_' + key).prop('checked', true);
                        const $inputDiv = $('#entry_' + key).closest('.d-flex').find('.entry-input-div');
                        if ($inputDiv.length) $inputDiv.removeClass('d-none');
                        $('#entry_detail_' + key).val(cb.entry_locations_detail[key]);
                        if (key === 'other_location') {
                            $('#entry_detail_' + key).prop('disabled', false);
                        }
                    });
                }

                // เครื่องมือ
                if (cb.burglary_tools && cb.burglary_tools.length > 0) {
                    cb.burglary_tools.forEach(function(tool) {
                        $('input[name="burglary_tool[]"][value="' + tool + '"]').prop('checked', true);
                    });
                }
                if (cb.tool_other_detail) {
                    $('#tool_other_detail').val(cb.tool_other_detail).prop('disabled', false);
                }

                // ขนาดความกว้างของรอย
                if (cb.trace_width) $('#trace_width').val(cb.trace_width);

                // การใช้อาวุธ
                if (cb.weapon_status) {
                    $('input[name="weapon_use_status"][value="' + cb.weapon_status + '"]').prop('checked', true);
                }
                if (cb.weapon_types && cb.weapon_types.length > 0) {
                    cb.weapon_types.forEach(function(wt) {
                        $('input[name="weapon_type[]"][value="' + wt + '"]').prop('checked', true);
                    });
                }
                if (cb.weapon_other_detail) {
                    $('#weapon_other_detail').val(cb.weapon_other_detail).prop('disabled', false);
                }

                // จำนวนคนร้าย
                if (cb.perpetrator_count) $('#perpetrator_count').val(cb.perpetrator_count);

                // การพันธนาการ
                if (cb.restraint_methods && cb.restraint_methods.length > 0) {
                    cb.restraint_methods.forEach(function(rm) {
                        $('input[name="restraint_method[]"][value="' + rm + '"]').prop('checked', true);
                    });
                }
                if (cb.binding_material) $('input[name="binding_material"]').val(cb.binding_material);

                // ผลต่อผู้เสียหาย
                if (cb.victim_status && cb.victim_status.length > 0) {
                    cb.victim_status.forEach(function(vs) {
                        $('input[name="victim_status[]"][value="' + vs + '"]').prop('checked', true);
                    });
                }
                if (cb.injury_detail) $('textarea[name="injury_detail"]').val(cb.injury_detail);

                // ==================== Trace Points (จุดที่ตรวจพบร่องรอย) ====================
                if (d.trace_points && d.trace_points.length > 0) {
                    // ลบ trace cards เดิม
                    $('#trace_point_container').empty();

                    d.trace_points.forEach(function(tp, idx) {
                        const count = idx + 1;
                        const htmlString = createTraceCard(count);
                        const $card = $(htmlString);
                        $('#trace_point_container').append($card);

                        // ใส่ค่า
                        if (tp.area_detail) $card.find('textarea[name*="[area_detail]"]').val(tp.area_detail);

                        if (tp.entry && tp.entry.checked) {
                            $card.find('input[name*="[entry_check]"]').prop('checked', true);
                            $card.find('input[name*="[entry_check]"]').closest('.col-md-4').find('.trace-input-div').removeClass('d-none');
                            if (tp.entry.detail) $card.find('textarea[name*="[entry_detail]"]').val(tp.entry.detail);
                        }
                        if (tp.pry && tp.pry.checked) {
                            $card.find('input[name*="[pry_check]"]').prop('checked', true);
                            $card.find('input[name*="[pry_check]"]').closest('.col-md-4').find('.trace-input-div').removeClass('d-none');
                            if (tp.pry.detail) $card.find('textarea[name*="[pry_detail]"]').val(tp.pry.detail);
                        }
                        if (tp.rummage && tp.rummage.checked) {
                            $card.find('input[name*="[rummage_check]"]').prop('checked', true);
                            $card.find('input[name*="[rummage_check]"]').closest('.col-md-4').find('.trace-input-div').removeClass('d-none');
                            if (tp.rummage.detail) $card.find('textarea[name*="[rummage_detail]"]').val(tp.rummage.detail);
                        }
                    });
                }

                // ==================== ทรัพย์สินถูกโจรกรรม ====================
                if (d.stolen_property) $('#stolen_property').val(d.stolen_property);

                // ==================== Evidences (วัตถุพยาน) ====================
                if (d.evidences && d.evidences.length > 0) {
                    // ลบ evidence cards เดิม
                    $('#evidence_container').empty();

                    let evidenceIndex = 0;
                    d.evidences.forEach(function(ev) {
                        // ★ ล้างข้อมูลเก่าที่ค้าง "อื่น ๆ - อื่น ๆ - ..." จาก DB
                        if (ev.type === 'other' && ev.detail) {
                            ev.detail = ev.detail.replace(/^(อื่น ๆ\s*-\s*)+/g, '').trim();
                        }

                        // ข้าม _summary_only (คราบสีแดงคล้ายโลหิต static section)
                        if (ev._summary_only) {
                            // โหลดเข้า static blood section แทน
                            if (ev.type === 'blood') {
                                $('#prop_evidence_blood').prop('checked', true);
                                if (ev.detail) $('input[name="blood_stain_detail"]').val(ev.detail);
                                if (ev.blood_test) {
                                    if (ev.blood_test.hemastix_tested) {
                                        $('#prop_test_hemastix').prop('checked', true);
                                    }
                                    if (ev.blood_test.hemastix_result === 'change') {
                                        $('#prop_hemastix_positive').prop('checked', true);
                                    } else if (ev.blood_test.hemastix_result === 'no_change') {
                                        $('#prop_hemastix_negative').prop('checked', true);
                                    }
                                    if (ev.blood_test.phenol_tested) {
                                        $('#prop_test_phenol').prop('checked', true);
                                    }
                                    if (ev.blood_test.phenol_result === 'change') {
                                        $('#prop_phenol_positive').prop('checked', true);
                                    } else if (ev.blood_test.phenol_result === 'no_change') {
                                        $('#prop_phenol_negative').prop('checked', true);
                                    }
                                }

                            }
                            return;
                        }

                        evidenceIndex++;
                        const htmlString = createEvidenceCard(evidenceIndex);
                        const $card = $(htmlString);
                        $('#evidence_container').append($card);

                        // ใส่ค่า
                        if (ev.type) {
                            $card.find('.evidence-type-select').val(ev.type);
                        }
                        if (ev.detail) $card.find('input[name*="[detail]"]').first().val(ev.detail);
                        if (ev.area_found) $card.find('textarea[name*="[area_found]"]').val(ev.area_found);
                        if (ev.label_no) $card.find('input[name*="[label_no]"]').val(ev.label_no);
                        if (ev.azimuth) $card.find('input[name*="[azimuth]"]').val(ev.azimuth);

                        // จำนวน
                        if (ev.quantity) {
                            if (ev.quantity.val) $card.find('input[name*="[quantity_val]"]').val(ev.quantity.val);
                            if (ev.quantity.unit) $card.find('select[name*="[quantity_unit]"]').val(ev.quantity.unit);
                        }

                        // หน่วยส่งตรวจ
                        window.setLabUnits($card.find('[name*="[lab_unit]"]'), ev.lab_unit);

                        // การบรรจุหีบห่อ
                        if (ev.packaging) {
                            if (ev.packaging.plastic) $card.find('input[value="plastic"]').prop('checked', true);
                            if (ev.packaging.paper) $card.find('input[value="paper"]').prop('checked', true);
                            if (ev.packaging.other) {
                                $card.find('input[value="other"].package-check').prop('checked', true);
                                const packOtherDiv = $card.find('.package-other-input, [id^="pack_other_detail_"]');
                                packOtherDiv.removeClass('d-none');
                                if (ev.packaging.other_text) {
                                    $card.find('input[name*="[package_other_text]"]').val(ev.packaging.other_text).prop('disabled', false);
                                }
                            }
                        }

                        // จุดอ้างอิง
                        if (ev.ref_points && ev.ref_points.length > 0) {
                            ev.ref_points.forEach(function(rp, ri) {
                                const refIdx = ri + 1;
                                if (rp.dist) $card.find('input[name*="[ref' + refIdx + '_dist]"]').val(rp.dist);
                                if (rp.desc) $card.find('input[name*="[ref' + refIdx + '_desc]"]').val(rp.desc);
                            });
                        }

                        // การดำเนินการ
                        if (ev.action) {
                            if (ev.action.return) $card.find('input[value="return_investigator"]').prop('checked', true);
                            if (ev.action.other) {
                                $card.find('input[value="other"].action-check').prop('checked', true);
                                const actOtherDiv = $card.find('.action-other-input, [id^="act_other_detail_"]');
                                actOtherDiv.removeClass('d-none');
                                if (ev.action.other_text) {
                                    $card.find('input[name*="[action_other_text]"]').val(ev.action.other_text).prop('disabled', false);
                                }
                            }
                        }

                        // หมายเหตุ
                        if (ev.remark) $card.find('textarea[name*="[remark]"]').val(ev.remark);
                    });
                }

                // ==================== Final Check ====================
                if (d.final_check && d.final_check.length > 0) {
                    d.final_check.forEach(function(fc) {
                        $('input[name="final_check[]"][value="' + fc + '"]').prop('checked', true);
                    });
                }

                // ==================== Section 10: บันทึกการตรวจเก็บวัตถุพยาน (measurements) ====================
                if (Array.isArray(d.measurements) && d.measurements.length > 0) {
                    var $mContainer = $('#measurement_container_property');
                    if ($mContainer.length) {
                        $mContainer.empty();
                        if (typeof measurementCounterProperty !== 'undefined') {
                            measurementCounterProperty = 0;
                        }
                        d.measurements.forEach(function(m, idx) {
                            // เรียก addMeasurementCardProperty เพื่อสร้าง card ใหม่
                            if (typeof addMeasurementCardProperty === 'function') {
                                addMeasurementCardProperty();
                            }
                            var $card = $mContainer.find('.measurement-card-property').eq(idx);
                            if (!$card.length) return;

                            $card.find('input[name="measurement_item_property[]"]').val(m.item || '');
                            $card.find('input[name="measurement_quantity_property[]"]').val(m.quantity || '');
                            $card.find('input[name="measurement_area_property[]"]').val(m.area || '');
                            $card.find('input[name="measurement_label_number_property[]"]').val(m.label_number || '');
                            $card.find('input[name="measurement_remark_property[]"]').val(m.remark || '');
                            window.setLabUnits($card.find('[name="measurement_forensic_unit_property[]"]'), m.forensic_unit);

                            // checkboxes + texts
                            if (m.package_plastic) {
                                $card.find('input[name^="measurement_package_plastic_check_prop_"]').prop('checked', true);
                                $card.find('input[name^="measurement_package_plastic_text_prop_"]').prop('disabled', false).val(m.package_plastic_text || '');
                            }
                            if (m.package_paper) {
                                $card.find('input[name^="measurement_package_paper_check_prop_"]').prop('checked', true);
                                $card.find('input[name^="measurement_package_paper_text_prop_"]').prop('disabled', false).val(m.package_paper_text || '');
                            }
                            if (m.package_other) {
                                $card.find('input[name^="measurement_package_other_check_prop_"]').prop('checked', true);
                                $card.find('input[name^="measurement_package_other_text_prop_"]').prop('disabled', false).val(m.package_other_text || '');
                            }
                            if (m.action_return) {
                                $card.find('input[name^="measurement_action_return_check_prop_"]').prop('checked', true);
                                $card.find('input[name^="measurement_action_return_text_prop_"]').prop('disabled', false).val(m.action_return_text || '');
                            }
                            if (m.action_other) {
                                $card.find('input[name^="measurement_action_other_check_prop_"]').prop('checked', true);
                                $card.find('input[name^="measurement_action_other_text_prop_"]').prop('disabled', false).val(m.action_other_text || '');
                            }
                        });
                    }
                }
                if (d.measurement_meta) {
                    var mm = d.measurement_meta;
                    if (mm.inspection_date) $('#measurement_inspection_date_property').val(mm.inspection_date);
                    if (mm.recorder) $('#measurement_recorder_property').val(mm.recorder);
                    if (mm.datetime) $('#measurement_datetime_property').val(mm.datetime);
                }

                // ==================== 8. การส่งมอบคืนสถานที่เกิดเหตุ ====================

                if (ho.receiver_id) {
                    $('#receiver_id').val(ho.receiver_id);
                    // trigger change เพื่อ auto fill position
                    $('#receiver_id').trigger('change');
                }
                if (ho.receiver_pos) $('#receiver_position').val(ho.receiver_pos);
                if (ho.deliverer_id) {
                    $('#deliverer_id').val(ho.deliverer_id);
                    $('#deliverer_id').trigger('change');
                }
                if (ho.deliverer_pos) $('#deliverer_position').val(ho.deliverer_pos);

                // ลายเซ็น - โหลดจาก base64 กลับเข้า canvas
                setTimeout(function() {
                    if (ho.receiver_sig && ho.receiver_sig.indexOf('data:image') !== -1) {
                        const recvPad = signaturePads['sig-canvas-receiver'];
                        if (recvPad) {
                            recvPad.fromDataURL(ho.receiver_sig);
                            $('#receiver_signature_data').val(ho.receiver_sig);
                        }
                    }
                    if (ho.deliverer_sig && ho.deliverer_sig.indexOf('data:image') !== -1) {
                        const delPad = signaturePads['sig-canvas-deliverer'];
                        if (delPad) {
                            delPad.fromDataURL(ho.deliverer_sig);
                            $('#deliverer_signature_data').val(ho.deliverer_sig);
                        }
                    }
                }, 500);

                // ==================== 9. แผนผังสังเขป ====================
                setTimeout(function() {
                    if (am.sketch && am.sketch.indexOf('data:image') !== -1) {
                        const skPad = signaturePads['scene_sketch_canvas'];
                        if (skPad) {
                            skPad.fromDataURL(am.sketch);
                            $('#scene_sketch_data').val(am.sketch);
                        }
                    }
                }, 700);

                if (am.sketch_remark) $('#sketch_remark').val(am.sketch_remark);

                // ==================== 10. บันทึกการถ่ายภาพ ====================
                if (am.photo_start) $('input[name="photo_id_start"]').val(am.photo_start);
                if (am.photo_end) $('input[name="photo_id_end"]').val(am.photo_end);
                if (am.photo_amount) $('input[name="photo_amount"]').val(am.photo_amount);

                // รูปภาพ (photos) - เก็บข้อมูลใน attachmentStore แล้ว render grid
                existingPhotosStore = [];
                attachmentStore.length = 0;
                if (d.photos && d.photos.length > 0) {
                    d.photos.forEach(function(photo, photoIndex) {
                        if (photo.file_id) {
                            // กรณี A: BLOB ใน DB
                            const imgUrl = './api/incidentCheckList/getFile.php?id=' + photo.file_id;
                            attachmentStore.push({
                                id: 'existing_' + photo.file_id,
                                src: imgUrl,
                                name: photo.original_name || ('photo_' + photo.file_id + '.jpg'),
                                existing: true,
                                db_file_id: photo.file_id,
                                caption: photo.caption || ''
                            });
                            existingPhotosStore.push({
                                base64: imgUrl,
                                fileName: photo.original_name || ('photo_' + photo.file_id),
                                filename: '',
                                file_id: photo.file_id
                            });
                        } else {
                            // กรณี B: ไฟล์บนดิสก์
                            const imgSrc = photo.base64 || '';
                            const fileName = photo.original_name || photo.filename || 'photo';
                            const safeFilename = photo.filename || '';
                            existingPhotosStore.push({
                                base64: imgSrc,
                                fileName: fileName,
                                filename: safeFilename
                            });
                            if (imgSrc) {
                                attachmentStore.push({
                                    id: 'existing_disk_' + photoIndex,
                                    src: imgSrc,
                                    name: fileName,
                                    existing: true,
                                    db_file_id: null,
                                    disk_filename: safeFilename,
                                    caption: ''
                                });
                            }
                        }
                    });
                    renderPropertyAttachmentGrid();
                    updatePropertyRealInput();
                }

                // ==================== ลายเซ็น file_id (จาก saveProperty.php) ====================
                const ho2 = d.handover || {};

                if (ho2.receiver_sig_file_id) {
                    const url = './api/incidentCheckList/getFile.php?id=' + ho2.receiver_sig_file_id;
                    loadSigFromUrl('sig-canvas-receiver', url, 'receiver_signature_data', ho2.receiver_sig_file_id);
                }
                if (ho2.sender_sig_file_id) {
                    const url = './api/incidentCheckList/getFile.php?id=' + ho2.sender_sig_file_id;
                    loadSigFromUrl('sig-canvas-deliverer', url, 'deliverer_signature_data', ho2.sender_sig_file_id);
                }
                if (ho2.sketch_file_id) {
                    const url = './api/incidentCheckList/getFile.php?id=' + ho2.sketch_file_id;
                    loadSigFromUrl('scene_sketch_canvas', url, 'scene_sketch_data', ho2.sketch_file_id);
                }

                // ==================== 11. ข้อมูลผู้จดบันทึก ====================
                if (ri.name) $('#recorder_name').val(ri.name);
                if (ri.datetime) $('#recorder_datetime').val(ri.datetime);

                // ★ เรียก callback หลังโหลดข้อมูลเสร็จ (สำหรับ sync ไป PDF form)
                if (typeof onComplete === 'function') {
                    setTimeout(function() {
                        onComplete(d);
                    }, 300);
                }

            },
            error: function(xhr, status, error) {
                console.error('Error loading property data:', error);
                if (typeof onComplete === 'function') onComplete(null);
            }
        });
    }

    // =========================================================
    // 3.7.1 RESET LIFE FORM (รีเซ็ตฟอร์มชีวิตก่อนโหลดข้อมูลใหม่)
    // =========================================================
    function resetLifeForm() {
        const modal = $('#addCheckListModalLife');

        // รีเซ็ตข้อมูลการแก้ไข
        $('#editInfoLife').addClass('d-none');

        // 1. รีเซ็ต text inputs, textareas, number inputs, date/datetime inputs
        modal.find('input[type="text"], input[type="number"], input[type="datetime-local"], input[type="date"], input[type="time"], input[type="tel"], textarea').not('#receiveNoti_id_life, #doc_no_life, #report_no_life').val('');

        // ★ คืนค่า default วันที่ปัจจุบันให้ field "ลง"
        var _today = new Date().toISOString().slice(0, 10);
        modal.find('[name="record_date"]').val(_today);
        $('#lpf_record_date').val(_today);

        // 2. รีเซ็ต checkboxes ทั้งหมด (รวม life-radio-toggle)
        modal.find('input[type="checkbox"]').prop('checked', false);

        // 3. รีเซ็ต select dropdowns (ไม่ใช่ Select2)
        modal.find('select').not('.inspector-select-life, .user-select-box-life').each(function() {
            $(this).val($(this).find('option:first').val());
        });

        // 4. รีเซ็ต Select2 fields (police_station_life, receiver_name_life, sender_name_life)
        if ($('#police_station_life').hasClass('select2-hidden-accessible')) {
            $('#police_station_life').val(null).trigger('change');
        }
        if ($('#receiver_name_life').hasClass('select2-hidden-accessible')) {
            $('#receiver_name_life').val(null).trigger('change');
        }
        if ($('#sender_name_life').hasClass('select2-hidden-accessible')) {
            $('#sender_name_life').val(null).trigger('change');
        }

        // 5. รีเซ็ต Inspector rows → เหลือแค่ 1 row ว่างๆ
        const $inspContainer = $('#inspector_container_life');
        $inspContainer.find('.inspector-row-life').slice(1).remove();
        const $firstInspSelect = $inspContainer.find('.inspector-select-life').first();
        if ($firstInspSelect.hasClass('select2-hidden-accessible')) {
            $firstInspSelect.val(null).trigger('change');
        } else {
            $firstInspSelect.val('');
        }

        // 6. รีเซ็ต Victim cards → เหลือแค่ 1 card ว่างๆ
        const $victimContainer = $('#victim_container_life');
        $victimContainer.find('.victim-card-life').slice(1).remove();
        const $firstVictimCard = $victimContainer.find('.victim-card-life').first();
        $firstVictimCard.find('select').val('');
        $firstVictimCard.find('input').val('');
        victimIndexLife = 1;

        // 7. รีเซ็ต Evidence cards → ลบให้หมดแล้วเหลือ 1
        const $evidenceContainer = $('#evidence_container_life');
        $evidenceContainer.find('.evidence-card-life').slice(1).remove();
        const $firstEvidence = $evidenceContainer.find('.evidence-card-life').first();
        $firstEvidence.find('input[type="text"]').val('');
        $firstEvidence.find('input[type="checkbox"]').prop('checked', false);
        $firstEvidence.find('select').val('');
        evidenceIndexLife = 1;

        // 8. รีเซ็ต Measurement cards → ลบให้หมดแล้วเหลือ 1
        const $measurementContainer = $('#measurement_container_life');
        $measurementContainer.find('.measurement-card-life').slice(1).remove();
        const $firstMeasurement = $measurementContainer.find('.measurement-card-life').first();
        $firstMeasurement.find('input[type="text"]').val('');
        $firstMeasurement.find('input[type="checkbox"]').prop('checked', false);
        // Disable package/action text inputs ตัวแรก
        $firstMeasurement.find('input[type="text"].form-control-sm').prop('disabled', true);
        measurementIndexLife = 1;

        // 9. รีเซ็ต Signatures (receiver, sender, sketch, body_diagram)
        ['sig-canvas-receiver-life', 'sig-canvas-sender-life', 'scene_sketch_canvas_life', 'body_diagram_canvas_life'].forEach(function(canvasId) {
            if (signaturePads[canvasId]) {
                signaturePads[canvasId].clear();
            }
        });
        // ล้าง hidden fields ลายเซ็น
        $('#receiver_signature_data_life').val('');
        $('#sender_signature_data_life').val('');
        $('#scene_sketch_data_life').val('');
        $('#body_diagram_data_life').val('');
        $('#body_diagram_strokes_life').val('');

        // 10. ซ่อน conditional sections
        $('#life_notify_other_text').hide().prop('disabled', true).val('');
        $('#life_scene_no_text').hide().prop('disabled', true).val('');
        $('#life_light_other_text').hide().prop('disabled', true).val('');
        $('#life_temp_other_text').hide().prop('disabled', true).val('');
        $('#life_outdoor_other_text').hide().prop('disabled', true).val('');
        $('#life_bld_other_text').hide().prop('disabled', true).val('');

        // 10.1 รีเซ็ต master checkboxes ภายนอก/ภายในอาคาร
        $('#check_outdoor_main_life').prop('checked', false).trigger('change');
        $('#check_indoor_main_life').prop('checked', false).trigger('change');

        // 11. รีเซ็ต receiver/sender position
        $('#receiver_position_life').val('');
        $('#sender_position_life').val('');

        // 12. รีเซ็ต photo fields
        $('#photo_id_start_life').val('');
        $('#photo_id_end_life').val('');
        $('#photo_amount_life').val('');

        // 13. ล้างรูปภาพ attachments
        $('#attachments_grid_life').empty();
        $('#attachments_wrapper_life').addClass('d-none');
        $('#file_count_badge_life').text('0');
        // รีเซ็ต arrays ที่เก็บรูปใหม่ + รูปที่จะลบ + รูปเดิม
        if (typeof attachmentStoreLife !== 'undefined') attachmentStoreLife.length = 0;
        if (typeof deletedExistingPhotosLife !== 'undefined') deletedExistingPhotosLife.length = 0;
        if (typeof window.existingPhotosStoreLife !== 'undefined') window.existingPhotosStoreLife = [];

        // 14. ล้าง file input
        if ($('#incident_photos_life').length) {
            $('#incident_photos_life').val('');
        }
        if ($('#camera_input_life').length) {
            $('#camera_input_life').val('');
        }

        // 15. รีเซ็ต sketch/body diagram info
        $('#sketch_remark_life').val('');
        $('#sketch_recorder_life').val('');
        $('#sketch_datetime_life').val('');
        $('#body_diagram_remark_life').val('');
        $('#victim_name_life_diagram').val('');
        $('#victim_age_life_diagram').val('');
        $('#autopsy_doctor_life').val('');

        // 16. รีเซ็ต measurement meta
        $('#measurement_inspection_date_life').val('');
        $('#measurement_recorder_life').val('');
        $('#measurement_datetime_life').val('');

        // 17. รีเซ็ต reference points & collector
        $('#reference_point_1_life, #reference_point_2_life, #reference_point_3_life, #reference_point_4_life').val('');
        $('#collector_name_life').val('');
        $('#collection_datetime_life').val('');
    }

    // =========================================================
    // 3.7.2 LOAD SAVED LIFE DATA (โหลดข้อมูลชีวิตที่บันทึกไว้กลับมาแก้ไข)
    // =========================================================
    function loadLifeData(incidentId, onComplete) {
        $.ajax({
            url: '/csims/api/incidentCheckList/getLifeData.php',
            type: 'GET',
            dataType: 'json',
            data: {
                incident_id: incidentId
            },
            success: function(response) {
                if (!response.success || !response.data) {
                    // ★ กรณีไม่มีข้อมูล ก็ต้องเรียก onComplete เพื่อซ่อน loading overlay
                    if (typeof onComplete === 'function') {
                        setTimeout(function() {
                            onComplete(null);
                        }, 300);
                    }
                    return;
                }

                // ★ เก็บ basic_info ไว้ใน global เพื่อ auto-fill พฤติการณ์คดี
                if (response.basic_info) window._basicInfoLife = response.basic_info;

                const d = response.data;
                const gi = d.general_info || {};
                const sc = d.scene_characteristics || {};
                const ir = d.inspection_result || {};
                const ho = d.handover || {};
                const vi = d.victim_info || {};
                const bi = d.body_info || {};
                console.log('[loadLifeData] sc:', sc);
                console.log('[loadLifeData] sc.structure:', sc.structure);
                console.log('[loadLifeData] sc.incident_area_detail:', sc.incident_area_detail);

                // ==================== แสดงจำนวนการแก้ไข ====================
                if (response.edit_info) {
                    const ei = response.edit_info;
                    $('#editCountLife').text(ei.count_edit || 0);
                    $('#editCountLifePdf').text(ei.count_edit || 0);
                    if (ei.edit_date) {
                        const ed = new Date(ei.edit_date);
                        const dd = String(ed.getDate()).padStart(2, '0');
                        const mm = String(ed.getMonth() + 1).padStart(2, '0');
                        const yyyy = ed.getFullYear() + 543;
                        const hh = String(ed.getHours()).padStart(2, '0');
                        const mi = String(ed.getMinutes()).padStart(2, '0');
                        $('#editDateLife').text(dd + '/' + mm + '/' + yyyy + ' ' + hh + ':' + mi + ' น.');
                    } else {
                        $('#editDateLife').text('-');
                    }
                    $('#editInfoLife').removeClass('d-none');
                    $('#editInfoLifePdf').removeClass('d-none');
                }
                const pr = d.photo_records || {};
                const si = d.sketch_info || {};
                const bdi = d.body_diagram_info || {};
                const em = d.evidence_meta || {};
                const mmeta = d.measurement_meta || {};

                // ★ โหลดข้อมูลลงทั้งฟอร์มมาตรฐานและ PDF form พร้อมกัน
                // ใช้ modal selector แทน form selector เพราะ form อาจไม่ถูกพบใน DOM
                const $form = $('#addCheckListModalLife');
                const $pdfForm = $('#lifeFormPdf');

                // ==================== 1. ข้อมูลการรับแจ้งเหตุ (Fieldset 1) ====================

                // วันที่/เวลา รับแจ้ง
                if (gi.report_datetime) {
                    const parts = gi.report_datetime.split('T');
                    if (parts[0]) {
                        $('#case_date_life').val(parts[0]);
                        $('#lpf_case_date').val(parts[0]);
                    }
                    if (parts[1]) {
                        $('#case_time_life').val(parts[1]);
                        $('#lpf_case_time').val(parts[1]);
                    }
                }

                // ช่องทางการรับแจ้ง (checkboxes)
                if (gi.report_channel && Array.isArray(gi.report_channel)) {
                    gi.report_channel.forEach(function(ch) {
                        $form.find('input[name="notify_method[]"][value="' + ch + '"]').prop('checked', true);
                        $pdfForm.find('input[name="notify_method[]"][value="' + ch + '"]').prop('checked', true);
                        if (ch === 'อื่นๆ') {
                            $('#life_notify_other_text').show().prop('disabled', false);
                            $('#lpf_notify_other_text').show().prop('disabled', false);
                            if (gi.report_channel_other) {
                                $('#life_notify_other_text').val(gi.report_channel_other);
                                $('#lpf_notify_other_text').val(gi.report_channel_other);
                            }
                        }
                    });
                }

                // สน/สภ (Select2)
                if (gi.source_station) {
                    // Standard form
                    if ($('#police_station_life option[value="' + gi.source_station + '"]').length > 0) {
                        $('#police_station_life').val(gi.source_station).trigger('change');
                    } else {
                        const opt = new Option(gi.source_station, gi.source_station, true, true);
                        $('#police_station_life').append(opt).trigger('change');
                    }
                    // PDF form
                    if ($('#lpf_police_station option[value="' + gi.source_station + '"]').length > 0) {
                        $('#lpf_police_station').val(gi.source_station);
                    } else {
                        const opt2 = new Option(gi.source_station, gi.source_station, true, true);
                        $('#lpf_police_station').append(opt2);
                    }
                }

                // ที่/ลง/วันที่
                if (gi.document_no) {
                    $form.find('[name="location_at"]').val(gi.document_no);
                    $('#lpf_location_at').val(gi.document_no);
                }
                const recordDate = gi.document_date || new Date().toISOString().slice(0, 10);
                $form.find('[name="record_date"]').val(recordDate);
                $('#lpf_record_date').val(recordDate);

                // ข้อมูลพนักงานสอบสวน
                if (gi.investigator) {
                    if (gi.investigator.firstname) {
                        $form.find('[name="investigator_name"]').val(gi.investigator.firstname);
                        $('#lpf_investigator_name').val(gi.investigator.firstname);
                    }
                    if (gi.investigator.phone) {
                        $form.find('[name="investigator_phone"]').val(gi.investigator.phone);
                        $('#lpf_investigator_phone').val(gi.investigator.phone);
                    }
                }

                // สถานที่เกิดเหตุ
                if (gi.location_detail) {
                    $form.find('[name="crime_location"]').val(gi.location_detail);
                    $('#lpf_crime_location').val(gi.location_detail);
                }

                // ==================== 2. ผู้ประสบเหตุ (Fieldset 2) ====================
                if (vi.victim_types && vi.victim_types.length > 0) {
                    // Standard form - ลบ victim cards เดิม (ยกเว้นตัวแรก)
                    $('#victim_container_life').find('.victim-card-life').slice(1).remove();
                    victimIndexLife = 0;
                    // PDF form - ลบ victim rows เดิม (ยกเว้นตัวแรก)
                    $('#lpf_victim_container').find('.lpf-victim-row').slice(1).remove();

                    vi.victim_types.forEach(function(vt, idx) {
                        // Standard form
                        if (idx > 0) {
                            addVictimCardLife();
                        }
                        const $cards = $('#victim_container_life .victim-card-life');
                        const $card = $cards.eq(idx);
                        if ($card.length) {
                            $card.find('select[name="victim_type_life[]"]').val(vt);
                            if (vi.victim_names && vi.victim_names[idx]) {
                                $card.find('input[name="victim_name_life[]"]').val(vi.victim_names[idx]);
                            }
                            if (vi.victim_ages && vi.victim_ages[idx]) {
                                $card.find('input[name="victim_age_life[]"]').val(vi.victim_ages[idx]);
                            }
                        }

                        // PDF form
                        if (idx > 0 && typeof window.lpfAddVictim === 'function') {
                            window.lpfAddVictim();
                        }
                        const $pdfRows = $('#lpf_victim_container .lpf-victim-row');
                        const $pdfRow = $pdfRows.eq(idx);
                        if ($pdfRow.length) {
                            $pdfRow.find('select[name="victim_type_life[]"]').val(vt);
                            if (vi.victim_names && vi.victim_names[idx]) {
                                $pdfRow.find('input[name="victim_name_life[]"]').val(vi.victim_names[idx]);
                            }
                            if (vi.victim_ages && vi.victim_ages[idx]) {
                                $pdfRow.find('input[name="victim_age_life[]"]').val(vi.victim_ages[idx]);
                            }
                        }
                    });
                }

                // ==================== 3. วันเวลาที่ทราบเหตุ/เกิดเหตุ (Fieldset 3) ====================
                if (gi.incident_datetime) {
                    const parts = gi.incident_datetime.split('T');
                    if (parts[0]) {
                        $form.find('[name="victim_know_date"]').val(parts[0]);
                        $('#lpf_victim_know_date').val(parts[0]);
                    }
                    if (parts[1]) {
                        $form.find('[name="victim_know_time"]').val(parts[1]);
                        $('#lpf_victim_know_time').val(parts[1]);
                    }
                }
                if (gi.investigator_known_datetime) {
                    const parts = gi.investigator_known_datetime.split('T');
                    if (parts[0]) {
                        $form.find('[name="officer_know_date"]').val(parts[0]);
                        $('#lpf_officer_know_date').val(parts[0]);
                    }
                    if (parts[1]) {
                        $form.find('[name="officer_know_time"]').val(parts[1]);
                        $('#lpf_officer_know_time').val(parts[1]);
                    }
                }

                // ==================== 4. วันเวลาที่ตรวจเหตุ (Fieldset 4) ====================
                if (gi.inspection_datetime) {
                    const parts = gi.inspection_datetime.split('T');
                    if (parts[0]) {
                        $form.find('[name="inspect_date"]').val(parts[0]);
                        $('#lpf_inspect_date').val(parts[0]);
                    }
                    if (parts[1]) {
                        $form.find('[name="inspect_time"]').val(parts[1]);
                        $('#lpf_inspect_time').val(parts[1]);
                    }
                }
                if (gi.inspection_additional_datetime) {
                    const parts = gi.inspection_additional_datetime.split('T');
                    if (parts[0]) {
                        $form.find('[name="inspect_additional_date"]').val(parts[0]);
                        $('#lpf_inspect_additional_date').val(parts[0]);
                    }
                    if (parts[1]) {
                        $form.find('[name="inspect_additional_time"]').val(parts[1]);
                        $('#lpf_inspect_additional_time').val(parts[1]);
                    }
                }

                // ==================== 5. ผู้ตรวจสถานที่เกิดเหตุ (Fieldset 5) ====================
                if (d.inspectors && d.inspectors.length > 0) {
                    // Standard form
                    $('#inspector_container_life').empty();
                    // PDF form
                    $('#lpf_inspector_container').empty();

                    d.inspectors.forEach(function(inspId, idx) {
                        const count = idx + 1;

                        // Standard form row
                        const deleteBtn = (count > 1) ? `
                            <div class="ms-2">
                                <button type="button" class="btn btn-link btn-sm p-0 border-0 btn-delete-inspector remove-inspector-life d-flex align-items-center justify-content-center"
                                        style="width: 32px; height: 32px; text-decoration: none; color: #dc3545;" title="ลบรายการ">
                                    <i class="fas fa-times fs-5"></i>
                                </button>
                            </div>` : '<div class="ms-2" style="width: 32px;"></div>';
                        const rowHtml = `
                            <div class="d-flex align-items-center mb-3 inspector-row-life">
                                <div class="text-end pe-3" style="width: 50px;">
                                    <span class="fw-bold text-secondary index-label">5.${count}</span>
                                </div>
                                <div class="flex-grow-1">
                                    <select class="form-select inspector-select-life" name="inspector_id[]">
                                        ${inspectorOptionsHTMLLife}
                                    </select>
                                </div>
                                ${deleteBtn}
                            </div>`;
                        $('#inspector_container_life').append(rowHtml);

                        const $lastSelect = $('#inspector_container_life .inspector-row-life:last .inspector-select-life');
                        $lastSelect.val(inspId);

                        // Init Select2 สำหรับแต่ละ row
                        if ($.fn.select2) {
                            $lastSelect.select2({
                                theme: 'bootstrap-5',
                                width: '100%',
                                placeholder: "กรุณาเลือก",
                                allowClear: true,
                                dropdownParent: $('#addCheckListModalLife'),
                                dropdownAutoWidth: true
                            });
                            $lastSelect.val(inspId).trigger('change');
                        }

                        // PDF form row
                        const pdfDeleteBtn = (count > 1) ? ' <button type="button" class="lpf-del-btn" onclick="this.parentElement.remove(); if(typeof lpfRenumberInspectors===\'function\') lpfRenumberInspectors();">×</button>' : '';
                        const pdfRowHtml = `
                            <div class="lpf-si lpf-inspector-row">
                                <span class="lpf-si-no">5.${count}</span>
                                <select class="lpf-sel" name="inspector_id[]">
                                    ${inspectorOptionsHTMLLife}
                                </select>
                                ${pdfDeleteBtn}
                            </div>`;
                        $('#lpf_inspector_container').append(pdfRowHtml);
                        $('#lpf_inspector_container .lpf-inspector-row:last select').val(inspId);
                    });
                    // Update PDF inspector index
                    if (typeof window.lpfInspectorIdx !== 'undefined') {
                        window.lpfInspectorIdx = d.inspectors.length;
                    }
                }

                // ==================== 6. ลักษณะสถานที่เกิดเหตุ (Fieldset 6) ====================

                // การรักษาสถานที่ (life-radio-toggle)
                if (sc.preservation) {
                    $form.find('input[name="scene_preserved"][value="' + sc.preservation + '"]').prop('checked', true);
                    $pdfForm.find('input[name="scene_preserved"][value="' + sc.preservation + '"]').prop('checked', true);
                    if (sc.preservation === 'ไม่มี') {
                        $('#life_scene_no_text').show().prop('disabled', false);
                        if (sc.preservation_detail) {
                            $('#life_scene_no_text').val(sc.preservation_detail);
                            $pdfForm.find('[name="scene_preserved_no_text"]').val(sc.preservation_detail);
                        }
                    }
                }

                // แสงสว่าง (checkboxes)
                if (sc.lighting && Array.isArray(sc.lighting)) {
                    sc.lighting.forEach(function(v) {
                        $form.find('input[name="lighting[]"][value="' + v + '"]').prop('checked', true);
                        $pdfForm.find('input[name="lighting[]"][value="' + v + '"]').prop('checked', true);
                        if (v === 'อื่นๆ') {
                            $('#life_light_other_text').show().prop('disabled', false);
                            if (sc.lighting_other) {
                                $('#life_light_other_text').val(sc.lighting_other);
                                $pdfForm.find('[name="lighting_other_text"]').val(sc.lighting_other);
                            }
                        }
                    });
                }

                // อุณหภูมิ (checkboxes)
                if (sc.temperature && Array.isArray(sc.temperature)) {
                    sc.temperature.forEach(function(v) {
                        $form.find('input[name="temperature[]"][value="' + v + '"]').prop('checked', true);
                        $pdfForm.find('input[name="temperature[]"][value="' + v + '"]').prop('checked', true);
                        if (v === 'อื่นๆ') {
                            $('#life_temp_other_text').show().prop('disabled', false);
                            if (sc.temperature_other) {
                                $('#life_temp_other_text').val(sc.temperature_other);
                                $pdfForm.find('[name="temperature_other_text"]').val(sc.temperature_other);
                            }
                        }
                    });
                }

                // กลิ่น (life-radio-toggle)
                if (sc.smell) {
                    $form.find('input[name="smell"][value="' + sc.smell + '"]').prop('checked', true);
                    $pdfForm.find('input[name="smell"][value="' + sc.smell + '"]').prop('checked', true);
                }

                // ภายนอกอาคาร (checkboxes)
                if (sc.has_outdoor || (sc.outdoor_type && sc.outdoor_type.length > 0)) {
                    $('#check_outdoor_main_life').prop('checked', true).trigger('change');
                    $pdfForm.find('input[name="has_outdoor_incident_life"]').prop('checked', true);
                }
                if (sc.outdoor_type && Array.isArray(sc.outdoor_type)) {
                    sc.outdoor_type.forEach(function(v) {
                        $form.find('input[name="outdoor_type[]"][value="' + v + '"]').prop('checked', true);
                        $pdfForm.find('input[name="outdoor_type[]"][value="' + v + '"]').prop('checked', true);
                        if (v === 'อื่นๆ') {
                            $('#life_outdoor_other_text').show().prop('disabled', false);
                            if (sc.outdoor_type_other) {
                                $('#life_outdoor_other_text').val(sc.outdoor_type_other);
                                $pdfForm.find('[name="outdoor_type_other_text"]').val(sc.outdoor_type_other);
                            }
                        }
                    });
                }
                if (sc.outdoor_entrance_condition) {
                    $form.find('[name="outdoor_entrance_condition"]').val(sc.outdoor_entrance_condition);
                    $pdfForm.find('[name="outdoor_entrance_condition"]').val(sc.outdoor_entrance_condition);
                }
                if (sc.outdoor_front_adjacent) {
                    $form.find('[name="outdoor_front_adjacent"]').val(sc.outdoor_front_adjacent);
                    $pdfForm.find('[name="outdoor_front_adjacent"]').val(sc.outdoor_front_adjacent);
                }
                if (sc.outdoor_left_adjacent) {
                    $form.find('[name="outdoor_left_adjacent"]').val(sc.outdoor_left_adjacent);
                    $pdfForm.find('[name="outdoor_left_adjacent"]').val(sc.outdoor_left_adjacent);
                }
                if (sc.outdoor_right_adjacent) {
                    $form.find('[name="outdoor_right_adjacent"]').val(sc.outdoor_right_adjacent);
                    $pdfForm.find('[name="outdoor_right_adjacent"]').val(sc.outdoor_right_adjacent);
                }
                if (sc.outdoor_back_adjacent) {
                    $form.find('[name="outdoor_back_adjacent"]').val(sc.outdoor_back_adjacent);
                    $pdfForm.find('[name="outdoor_back_adjacent"]').val(sc.outdoor_back_adjacent);
                }
                if (sc.outdoor_incident_area_detail) {
                    $form.find('[name="outdoor_incident_area_detail"]').val(sc.outdoor_incident_area_detail);
                    $pdfForm.find('[name="outdoor_incident_area_detail"]').val(sc.outdoor_incident_area_detail);
                }

                // ภายในอาคาร (checkboxes)
                if (sc.has_indoor || (sc.building_type && sc.building_type.length > 0)) {
                    $('#check_indoor_main_life').prop('checked', true).trigger('change');
                    $pdfForm.find('input[name="has_indoor_incident_life"]').prop('checked', true);
                }
                if (sc.building_type && Array.isArray(sc.building_type)) {
                    sc.building_type.forEach(function(v) {
                        $form.find('input[name="building_type[]"][value="' + v + '"]').prop('checked', true);
                        $pdfForm.find('input[name="building_type[]"][value="' + v + '"]').prop('checked', true);
                        if (v === 'อื่นๆ') {
                            $('#life_bld_other_text').show().prop('disabled', false);
                            if (sc.building_type_other) {
                                $('#life_bld_other_text').val(sc.building_type_other);
                                $pdfForm.find('[name="building_type_other_text"]').val(sc.building_type_other);
                            }
                        }
                    });
                }

                // รั้วกั้น (life-radio-toggle)
                if (sc.fence) {
                    $form.find('input[name="indoor_surrounding"][value="' + sc.fence + '"]').prop('checked', true);
                    $pdfForm.find('input[name="indoor_surrounding"][value="' + sc.fence + '"]').prop('checked', true);
                }

                // ลักษณะภายใน
                if (sc.interior_detail) {
                    $form.find('[name="indoor_interior_detail"]').val(sc.interior_detail);
                    $pdfForm.find('[name="indoor_interior_detail"]').val(sc.interior_detail);
                }

                // สภาพแวดล้อมและบริเวณโดยรอบ
                if (sc.entrance_condition) {
                    $form.find('[name="entrance_condition"]').val(sc.entrance_condition);
                    $pdfForm.find('[name="entrance_condition"]').val(sc.entrance_condition);
                }
                if (sc.front_adjacent) {
                    $form.find('[name="front_adjacent"]').val(sc.front_adjacent);
                    $pdfForm.find('[name="front_adjacent"]').val(sc.front_adjacent);
                }
                if (sc.left_adjacent) {
                    $form.find('[name="left_adjacent"]').val(sc.left_adjacent);
                    $pdfForm.find('[name="left_adjacent"]').val(sc.left_adjacent);
                }
                if (sc.right_adjacent) {
                    $form.find('[name="right_adjacent"]').val(sc.right_adjacent);
                    $pdfForm.find('[name="right_adjacent"]').val(sc.right_adjacent);
                }
                if (sc.back_adjacent) {
                    $form.find('[name="back_adjacent"]').val(sc.back_adjacent);
                    $pdfForm.find('[name="back_adjacent"]').val(sc.back_adjacent);
                }
                if (sc.incident_area_detail) {
                    $form.find('[name="incident_area_detail"]').val(sc.incident_area_detail);
                    $pdfForm.find('[name="incident_area_detail"]').val(sc.incident_area_detail);
                }

                // โครงสร้าง
                if (sc.structure) {
                    if (sc.structure.size) {
                        $form.find('[name="structure_size"]').val(sc.structure.size);
                        $pdfForm.find('[name="structure_size"]').val(sc.structure.size);
                        $pdfForm.find('input[name="structure_size_check"]').prop('checked', true);
                    }
                    if (sc.structure.type) {
                        $form.find('[name="structure_type"]').val(sc.structure.type);
                        $pdfForm.find('[name="structure_type"]').val(sc.structure.type);
                        $pdfForm.find('input[name="structure_type_check"]').prop('checked', true);
                    }
                    if (sc.structure.wall) {
                        $form.find('[name="structure_wall"]').val(sc.structure.wall);
                        $pdfForm.find('[name="structure_wall"]').val(sc.structure.wall);
                    }
                    if (sc.structure.front) {
                        $form.find('[name="structure_front"]').val(sc.structure.front);
                        $pdfForm.find('[name="structure_front"]').val(sc.structure.front);
                    }
                    if (sc.structure.left) {
                        $form.find('[name="structure_left"]').val(sc.structure.left);
                        $pdfForm.find('[name="structure_left"]').val(sc.structure.left);
                    }
                    if (sc.structure.right) {
                        $form.find('[name="structure_right"]').val(sc.structure.right);
                        $pdfForm.find('[name="structure_right"]').val(sc.structure.right);
                    }
                    if (sc.structure.back) {
                        $form.find('[name="structure_back"]').val(sc.structure.back);
                        $pdfForm.find('[name="structure_back"]').val(sc.structure.back);
                    }
                    if (sc.structure.floor) {
                        $form.find('[name="structure_floor"]').val(sc.structure.floor);
                        $pdfForm.find('input[name="structure_floor_check"]').prop('checked', true);
                        $pdfForm.find('[name="structure_floor"]').val(sc.structure.floor);
                    }
                    if (sc.structure.roof) {
                        $form.find('[name="structure_roof"]').val(sc.structure.roof);
                        $pdfForm.find('input[name="structure_roof_check"]').prop('checked', true);
                        $pdfForm.find('[name="structure_roof"]').val(sc.structure.roof);
                    }
                    if (sc.structure.arrangement) {
                        $form.find('[name="structure_arrangement"]').val(sc.structure.arrangement);
                        $pdfForm.find('input[name="structure_arrangement_check"]').prop('checked', true);
                        $pdfForm.find('[name="structure_arrangement"]').val(sc.structure.arrangement);
                    }
                }

                // ==================== 7. ผลการตรวจสถานที่เกิดเหตุ (Fieldset 7) ====================

                // พฤติการณ์คดี
                if (ir.case_behavior) {
                    $form.find('[name="case_behavior"]').val(ir.case_behavior);
                    $pdfForm.find('[name="case_behavior"]').val(ir.case_behavior);
                } else if (window._basicInfoLife) {
                    $form.find('[name="case_behavior"]').val(window._basicInfoLife);
                    $pdfForm.find('[name="case_behavior"]').val(window._basicInfoLife);
                }
                if (ir.entrance_exit) {
                    $form.find('[name="entrance_exit"]').val(ir.entrance_exit);
                    $pdfForm.find('input[name="entrance_exit_check"]').prop('checked', true);
                    $pdfForm.find('[name="entrance_exit"]').val(ir.entrance_exit);
                }
                if (ir.entrance_exit_detail) {
                    $form.find('[name="entrance_exit_detail"]').val(ir.entrance_exit_detail);
                    $pdfForm.find('input[name="entrance_exit_check2[]"]').prop('checked', true);
                    $pdfForm.find('[name="entrance_exit_detail"]').val(ir.entrance_exit_detail);
                }

                // ร่องรอยการต่อสู้ (life-radio-toggle)
                if (ir.fight_trace) {
                    $form.find('input[name="fight_trace"][value="' + ir.fight_trace + '"]').prop('checked', true);
                    $pdfForm.find('input[name="fight_trace"][value="' + ir.fight_trace + '"]').prop('checked', true);
                }
                if (ir.fight_trace_detail) {
                    $form.find('[name="fight_trace_detail"]').val(ir.fight_trace_detail);
                    $pdfForm.find('[name="fight_trace_detail"]').val(ir.fight_trace_detail);
                }
                // ร่องรอยการค้น (life-radio-toggle)
                if (ir.search_trace) {
                    $form.find('input[name="search_trace"][value="' + ir.search_trace + '"]').prop('checked', true);
                    $pdfForm.find('input[name="search_trace"][value="' + ir.search_trace + '"]').prop('checked', true);
                }

                // สถานะศพ (life-radio-toggle)
                if (ir.body_found) {
                    $form.find('input[name="body_status"][value="' + ir.body_found + '"]').prop('checked', true);
                    $pdfForm.find('input[name="body_status"][value="' + ir.body_found + '"]').prop('checked', true);
                }
                if (ir.body_location) {
                    $form.find('[name="body_location"]').val(ir.body_location);
                    $pdfForm.find('input[name="body_status"][value="ตำแหน่งที่พบศพ"]').prop('checked', true);
                    $pdfForm.find('[name="body_location"]').val(ir.body_location);
                }
                if (ir.body_location_2) {
                    $form.find('[name="body_location_2"]').val(ir.body_location_2);
                    $pdfForm.find('[name="body_location_2"]').val(ir.body_location_2);
                }
                if (ir.body_condition) {
                    $form.find('[name="body_condition"]').val(ir.body_condition);
                    $pdfForm.find('[name="body_condition"]').val(ir.body_condition);
                }

                // การแต่งกาย
                if (ir.clothing) {
                    const clothingMap = {
                        'shirt': {
                            checkbox: 'เสื้อ',
                            field: 'clothing_shirt'
                        },
                        'pants': {
                            checkbox: 'กางเกง',
                            field: 'clothing_pants'
                        },
                        'shoes': {
                            checkbox: 'รองเท้า/ถุงเท้า',
                            field: 'clothing_shoes'
                        },
                        'accessories': {
                            checkbox: 'เครื่องประดับ',
                            field: 'clothing_accessories'
                        },
                        'tattoo': {
                            checkbox: 'รอยสักหรือรอยแผลเป็น',
                            field: 'clothing_tattoo'
                        },
                        'other': {
                            checkbox: 'อื่นๆ',
                            field: 'clothing_other'
                        }
                    };
                    Object.keys(clothingMap).forEach(function(key) {
                        if (ir.clothing[key]) {
                            $form.find('input[name="clothing_items[]"][value="' + clothingMap[key].checkbox + '"]').prop('checked', true);
                            $pdfForm.find('input[name="clothing_items[]"][value="' + clothingMap[key].checkbox + '"]').prop('checked', true);
                            $form.find('[name="' + clothingMap[key].field + '"]').val(ir.clothing[key]);
                            $pdfForm.find('[name="' + clothingMap[key].field + '"]').val(ir.clothing[key]);
                        }
                    });
                }

                // บาดแผล (life-radio-toggle)
                if (ir.wound) {
                    if (ir.wound.status) {
                        $form.find('input[name="wound_status"][value="' + ir.wound.status + '"]').prop('checked', true);
                        $pdfForm.find('input[name="wound_status"][value="' + ir.wound.status + '"]').prop('checked', true);
                    }
                    if (ir.wound.count) {
                        $form.find('[name="wound_count"]').val(ir.wound.count);
                        $pdfForm.find('[name="wound_count"]').val(ir.wound.count);
                    }
                    if (ir.wound.description) {
                        $form.find('[name="wound_description"]').val(ir.wound.description);
                        $pdfForm.find('[name="wound_description"]').val(ir.wound.description);
                    }
                    if (ir.wound.description_2) {
                        $form.find('[name="wound_description_2"]').val(ir.wound.description_2);
                        $pdfForm.find('[name="wound_description_2"]').val(ir.wound.description_2);
                    }
                    if (ir.wound.detail) {
                        $form.find('[name="wound_detail"]').val(ir.wound.detail);
                        $pdfForm.find('[name="wound_detail"]').val(ir.wound.detail);
                    }
                }

                // คราบสีแดงคล้ายโลหิต
                if (ir.evidence) {
                    if (ir.evidence.blood_stain) {
                        $('#life_evidence_blood').prop('checked', true);
                        $pdfForm.find('input[name="evidence_blood_stain"]').prop('checked', true);
                        $form.find('[name="blood_stain_detail"]').val(ir.evidence.blood_stain);
                        $pdfForm.find('[name="blood_stain_detail"]').val(ir.evidence.blood_stain);
                    }
                    // ผลทดสอบเลือด
                    if (ir.evidence.blood_test) {
                        if (ir.evidence.blood_test.hemastix_result) {
                            $('#life_test_hemastix').prop('checked', true);
                            $pdfForm.find('input[name="test_hemastix"]').prop('checked', true);
                        }
                        if (ir.evidence.blood_test.phenol_result) {
                            $('#life_test_phenol').prop('checked', true);
                            $pdfForm.find('input[name="test_phenolphthalein"]').prop('checked', true);
                        }
                        // Enable ผลทดสอบก่อนแล้วค่อย set ค่า
                        if (typeof window.toggleLifeBloodTests === 'function') window.toggleLifeBloodTests();
                        if (ir.evidence.blood_test.hemastix_result) {
                            $form.find('input[name="hemastix_result"][value="' + ir.evidence.blood_test.hemastix_result + '"]').prop('checked', true);
                            $pdfForm.find('input[name="hemastix_result"][value="' + ir.evidence.blood_test.hemastix_result + '"]').prop('checked', true);
                        }
                        if (ir.evidence.blood_test.phenol_result) {
                            $form.find('input[name="phenol_result"][value="' + ir.evidence.blood_test.phenol_result + '"]').prop('checked', true);
                            $pdfForm.find('input[name="phenol_result"][value="' + ir.evidence.blood_test.phenol_result + '"]').prop('checked', true);
                        }
                    }
                    if (ir.evidence.other) {
                        $form.find('[name="other_evidence"]').val(ir.evidence.other);
                        $pdfForm.find('input[name="other_evidence_check"]').prop('checked', true);
                        $pdfForm.find('[name="other_evidence"]').val(ir.evidence.other);
                    }
                }

                // วัตถุพยานที่ตรวจเก็บ
                if (ir.collected_evidence) {
                    if (ir.collected_evidence.gun) {
                        $('#life_collect_gun').prop('checked', true);
                        $pdfForm.find('input[name="collected_evidence[]"][value="วัตถุพยานประเภทอาวุธปืนและเครื่องกระสุน"]').prop('checked', true);
                        $form.find('[name="collected_gun_detail"]').val(ir.collected_evidence.gun);
                        $pdfForm.find('[name="collected_gun_detail"]').val(ir.collected_evidence.gun);
                    }
                    if (ir.collected_evidence.dna) {
                        $('#life_collect_dna').prop('checked', true);
                        $pdfForm.find('input[name="collected_evidence[]"][value="วัตถุพยานประเภทสารพันธุกรรม"]').prop('checked', true);
                        $form.find('[name="collected_dna_detail"]').val(ir.collected_evidence.dna);
                        $pdfForm.find('[name="collected_dna_detail"]').val(ir.collected_evidence.dna);
                    }
                    if (ir.collected_evidence.fingerprint) {
                        $('#life_evidence_fingerprint').prop('checked', true);
                        $pdfForm.find('input[name="evidence_fingerprint"]').prop('checked', true);
                        $form.find('[name="fingerprint_detail"]').val(ir.collected_evidence.fingerprint);
                        $pdfForm.find('[name="fingerprint_detail"]').val(ir.collected_evidence.fingerprint);
                    }
                    if (ir.collected_evidence.other_type) {
                        $('#life_evidence_other_type').prop('checked', true);
                        $pdfForm.find('input[name="evidence_other_type"]').prop('checked', true);
                        $form.find('[name="other_evidence_type"]').val(ir.collected_evidence.other_type);
                        $pdfForm.find('[name="other_evidence_type"]').val(ir.collected_evidence.other_type);
                    }
                }

                // การตรวจสอบครั้งสุดท้าย
                if (ir.final_check && Array.isArray(ir.final_check)) {
                    ir.final_check.forEach(function(fc) {
                        $form.find('input[name="final_check[]"][value="' + fc + '"]').prop('checked', true);
                        $pdfForm.find('input[name="final_check[]"][value="' + fc + '"]').prop('checked', true);
                    });
                }

                // ==================== 8. การส่งมอบคืนสถานที่ (Fieldset 8) ====================

                // วันเวลาตรวจเสร็จ
                if (gi.inspection_end_datetime) {
                    const parts = gi.inspection_end_datetime.split('T');
                    if (parts[0]) {
                        $form.find('[name="inspection_end_date"]').val(parts[0]);
                        $pdfForm.find('[name="inspection_end_date"]').val(parts[0]);
                    }
                    if (parts[1]) {
                        $form.find('[name="inspection_end_time"]').val(parts[1]);
                        $pdfForm.find('[name="inspection_end_time"]').val(parts[1]);
                    }
                }

                // ผู้รับมอบ (Select2)
                if (ho.receiver_id) {
                    if ($('#receiver_name_life').hasClass('select2-hidden-accessible')) {
                        $('#receiver_name_life').val(ho.receiver_id).trigger('change');
                    } else {
                        $('#receiver_name_life').val(ho.receiver_id);
                    }
                    // PDF form
                    $pdfForm.find('[name="receiver_name"]').val(ho.receiver_id);
                }
                if (ho.receiver_pos) {
                    $('#receiver_position_life').val(ho.receiver_pos);
                    $pdfForm.find('[name="receiver_position"]').val(ho.receiver_pos);
                }

                // ผู้ส่งมอบ (Select2)
                if (ho.deliverer_id) {
                    if ($('#sender_name_life').hasClass('select2-hidden-accessible')) {
                        $('#sender_name_life').val(ho.deliverer_id).trigger('change');
                    } else {
                        $('#sender_name_life').val(ho.deliverer_id);
                    }
                    // PDF form
                    $pdfForm.find('[name="sender_name"]').val(ho.deliverer_id);
                }
                if (ho.deliverer_pos) {
                    $('#sender_position_life').val(ho.deliverer_pos);
                    $pdfForm.find('[name="sender_position"]').val(ho.deliverer_pos);
                }

                // ลายเซ็น receiver & sender (รองรับทั้ง file_id BLOB และ base64 legacy)
                setTimeout(function() {
                    if (d.signatures) {
                        if (d.signatures.receiver_sig) {
                            const sigData = d.signatures.receiver_sig;
                            const sigSrc = sigData.file_id ?
                                './api/incidentCheckList/getFile.php?id=' + sigData.file_id :
                                sigData.base64 || '';
                            if (sigSrc) {
                                const pad = signaturePads['sig-canvas-receiver-life'];
                                if (pad) {
                                    pad.clear();
                                    pad.fromDataURL(sigSrc);
                                }
                            }
                        }
                        if (d.signatures.sender_sig) {
                            const sigData = d.signatures.sender_sig;
                            const sigSrc = sigData.file_id ?
                                './api/incidentCheckList/getFile.php?id=' + sigData.file_id :
                                sigData.base64 || '';
                            if (sigSrc) {
                                const pad = signaturePads['sig-canvas-sender-life'];
                                if (pad) {
                                    pad.clear();
                                    pad.fromDataURL(sigSrc);
                                }
                            }
                        }
                    }
                }, 500);

                // ==================== 9. แผนผังสังเขป (Fieldset 9) ====================
                setTimeout(function() {
                    if (d.signatures && d.signatures.scene_sketch) {
                        const sigData = d.signatures.scene_sketch;
                        const sigSrc = sigData.file_id ?
                            './api/incidentCheckList/getFile.php?id=' + sigData.file_id :
                            sigData.base64 || '';
                        if (sigSrc) {
                            const pad = signaturePads['scene_sketch_canvas_life'];
                            if (pad) {
                                pad.clear();
                                pad.fromDataURL(sigSrc);
                            }
                        }
                    }
                }, 700);

                if (si.remark) {
                    $('#sketch_remark_life').val(si.remark);
                    $pdfForm.find('[name="sketch_remark_life"]').val(si.remark);
                }
                if (si.recorder) {
                    $('#sketch_recorder_life').val(si.recorder);
                    $('#lpf_sketch_recorder').val(si.recorder);
                }
                if (si.datetime) {
                    $('#sketch_datetime_life').val(si.datetime);
                    $('#lpf_sketch_datetime').val(si.datetime);
                }

                // ==================== 10. วัตถุพยานหรือร่องรอยที่ตรวจพบ (Fieldset 10) ====================
                if (d.evidences && d.evidences.length > 0) {
                    // Standard form
                    $('#evidence_container_life').empty();
                    evidenceIndexLife = 0;
                    // PDF form - clear existing rows except header
                    const $pdfEvidenceTable = $pdfForm.find('table').filter(function() {
                        return $(this).find('th:contains("วัตถุพยาน")').length > 0;
                    });
                    if ($pdfEvidenceTable.length) {
                        $pdfEvidenceTable.find('tbody tr').slice(1).remove();
                    }

                    d.evidences.forEach(function(ev, idx) {
                        // ระยะห่าง (m) จากจุดอ้างอิง 1-4
                        const evDist1 = ev.ref1_dist ?? (ev.level_1 === true || ev.level_1 === '1' ? '' : (ev.level_1 || ''));
                        const evDist2 = ev.ref2_dist ?? (ev.level_2 === true || ev.level_2 === '1' ? '' : (ev.level_2 || ''));
                        const evDist3 = ev.ref3_dist ?? (ev.level_3 === true || ev.level_3 === '1' ? '' : (ev.level_3 || ''));
                        const evDist4 = ev.ref4_dist ?? (ev.level_4 === true || ev.level_4 === '1' ? '' : (ev.level_4 || ''));

                        // Standard form
                        addEvidenceRowLife();

                        const $cards = $('#evidence_container_life .evidence-card-life');
                        const $card = $cards.eq(idx);
                        if ($card.length) {
                            // วัตถุพยาน
                            const evDetail = ev.detail || ev.item || '';
                            if (evDetail) $card.find('input[name="evidence_item_life[]"]').val(evDetail);

                            if (evDist1) $card.find('input[name^="evidence_level_1_life_"]').val(evDist1);
                            if (evDist2) $card.find('input[name^="evidence_level_2_life_"]').val(evDist2);
                            if (evDist3) $card.find('input[name^="evidence_level_3_life_"]').val(evDist3);
                            if (evDist4) $card.find('input[name^="evidence_level_4_life_"]').val(evDist4);

                            // Azimuth & หมายเหตุ & การตรวจพิสูจน์
                            if (ev.azimuth) $card.find('input[name="evidence_azimuth_life[]"]').val(ev.azimuth);
                            if (ev.remark) $card.find('input[name="evidence_remark_life[]"]').val(ev.remark);
                            window.setLabUnits($card.find('[name="evidence_lab_unit_life[]"]'), ev.lab_unit);
                        }

                        // PDF form - add row
                        if (idx > 0 && typeof window.lpfAddEvidenceRow === 'function') {
                            window.lpfAddEvidenceRow();
                        }
                        const $pdfRows = $pdfForm.find('input[name="evidence_item_life[]"]');
                        const $pdfRow = $pdfRows.eq(idx).closest('tr');
                        if ($pdfRow.length) {
                            const evDetail = ev.detail || ev.item || '';
                            $pdfRow.find('input[name="evidence_label_life[]"]').val(idx + 1);
                            $pdfRow.find('input[name="evidence_item_life[]"]').val(evDetail);
                            if (evDist1) $pdfRow.find('input[name="evidence_level_1_life_' + idx + '"]').val(evDist1);
                            if (evDist2) $pdfRow.find('input[name="evidence_level_2_life_' + idx + '"]').val(evDist2);
                            if (evDist3) $pdfRow.find('input[name="evidence_level_3_life_' + idx + '"]').val(evDist3);
                            if (evDist4) $pdfRow.find('input[name="evidence_level_4_life_' + idx + '"]').val(evDist4);
                            if (ev.azimuth) $pdfRow.find('input[name="evidence_azimuth_life[]"]').val(ev.azimuth);
                            if (ev.remark) $pdfRow.find('input[name="evidence_remark_life[]"]').val(ev.remark);
                            window.setLabUnits($pdfRow.find('[name="evidence_lab_unit_life[]"]'), ev.lab_unit);
                        }
                    });
                }

                // จุดอ้างอิง
                if (em.reference_point_1) {
                    $('#reference_point_1_life').val(em.reference_point_1);
                    $pdfForm.find('[name="reference_point_1_life"]').val(em.reference_point_1);
                }
                if (em.reference_point_2) {
                    $('#reference_point_2_life').val(em.reference_point_2);
                    $pdfForm.find('[name="reference_point_2_life"]').val(em.reference_point_2);
                }
                if (em.reference_point_3) {
                    $('#reference_point_3_life').val(em.reference_point_3);
                    $pdfForm.find('[name="reference_point_3_life"]').val(em.reference_point_3);
                }
                if (em.reference_point_4) {
                    $('#reference_point_4_life').val(em.reference_point_4);
                    $pdfForm.find('[name="reference_point_4_life"]').val(em.reference_point_4);
                }
                if (em.collector_name) {
                    $('#collector_name_life').val(em.collector_name);
                    $('#lpf_collector_name').val(em.collector_name);
                }
                if (em.collection_datetime) {
                    $('#collection_datetime_life').val(em.collection_datetime);
                    $('#lpf_collection_datetime').val(em.collection_datetime);
                    $pdfForm.find('[name="collection_datetime_life"]').val(em.collection_datetime);
                }

                // ==================== 11. แผนผังร่างกาย (Fieldset 11) ====================
                if (bdi.victim_name) {
                    $('#victim_name_life_diagram').val(bdi.victim_name);
                    $('#lpf_victim_name_diagram').val(bdi.victim_name);
                }
                if (bdi.victim_age) {
                    $('#victim_age_life_diagram').val(bdi.victim_age);
                    $('#lpf_victim_age_diagram').val(bdi.victim_age);
                }
                if (bdi.autopsy_doctor) {
                    $('#autopsy_doctor_life').val(bdi.autopsy_doctor);
                    $('#lpf_autopsy_doctor').val(bdi.autopsy_doctor);
                }
                if (bdi.remark) {
                    $('#body_diagram_remark_life').val(bdi.remark);
                    $pdfForm.find('[name="body_diagram_remark"]').val(bdi.remark);
                }

                setTimeout(function() {
                    const pad = signaturePads['body_diagram_canvas_life'];
                    if (!pad) return;

                    // ถ้ามี stroke data ให้ใช้ fromData (คุณภาพเท่าเดิม 100%)
                    if (d.body_diagram_strokes) {
                        try {
                            const strokes = typeof d.body_diagram_strokes === 'string' ?
                                JSON.parse(d.body_diagram_strokes) :
                                d.body_diagram_strokes;
                            if (strokes && strokes.length > 0) {
                                pad.clear();
                                pad.fromData(strokes);
                                $('#body_diagram_strokes_life').val(JSON.stringify(strokes));
                                // PDF form - โหลด strokes ลง canvas
                                $('#lpf_body_diagram_strokes').val(JSON.stringify(strokes));
                                const lpfPad = signaturePads['lpf_body_diagram_canvas'];
                                if (lpfPad) {
                                    lpfPad.clear();
                                    lpfPad.fromData(strokes);
                                }
                            }
                        } catch (e) {
                            console.warn('Cannot parse body_diagram_strokes', e);
                        }
                    }
                    // fallback: ถ้าไม่มี stroke data ใช้ image เดิม (BLOB file_id หรือ base64 legacy)
                    else if (d.signatures && d.signatures.body_diagram) {
                        const sigData = d.signatures.body_diagram;
                        const imgSrc = sigData.file_id ?
                            './api/incidentCheckList/getFile.php?id=' + sigData.file_id :
                            sigData.base64 || '';
                        if (imgSrc) {
                            // Standard form canvas
                            const canvas = document.getElementById('body_diagram_canvas_life');
                            const ctx = canvas.getContext('2d');
                            const img = new Image();
                            img.onload = function() {
                                ctx.save();
                                ctx.setTransform(1, 0, 0, 1, 0, 0);
                                ctx.clearRect(0, 0, canvas.width, canvas.height);
                                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                                ctx.restore();
                                pad._isEmpty = false;
                            };
                            img.src = imgSrc;
                            // PDF form canvas
                            const lpfCanvas = document.getElementById('lpf_body_diagram_canvas');
                            if (lpfCanvas) {
                                const lpfCtx = lpfCanvas.getContext('2d');
                                const lpfImg = new Image();
                                lpfImg.onload = function() {
                                    lpfCtx.save();
                                    lpfCtx.setTransform(1, 0, 0, 1, 0, 0);
                                    lpfCtx.clearRect(0, 0, lpfCanvas.width, lpfCanvas.height);
                                    lpfCtx.drawImage(lpfImg, 0, 0, lpfCanvas.width, lpfCanvas.height);
                                    lpfCtx.restore();
                                    const lpfPad = signaturePads['lpf_body_diagram_canvas'];
                                    if (lpfPad) lpfPad._isEmpty = false;
                                };
                                lpfImg.src = imgSrc;
                            }
                        }
                    }
                }, 900);

                // ==================== 12. บันทึกการวัดพบ (Fieldset 12) ====================
                if (mmeta.inspection_date) {
                    const timeVal = mmeta.inspection_time || '00:00';
                    const measurementDateValue = mmeta.inspection_date.indexOf('T') !== -1 ?
                        mmeta.inspection_date :
                        mmeta.inspection_date + 'T' + timeVal;
                    $('#measurement_inspection_date_life').val(measurementDateValue);
                    // PDF form - แยก date และ time
                    $('#lpf_measurement_date').val(mmeta.inspection_date.split('T')[0] || mmeta.inspection_date);
                    $('#lpf_measurement_time').val(timeVal);
                }

                if (d.measurements && d.measurements.length > 0) {
                    $('#measurement_container_life').empty();
                    measurementIndexLife = 0;

                    // ★ เพิ่ม: ล้างและ populate ตาราง PDF ด้วย
                    const pdfTbody = document.getElementById('lpf_collection_tbody');
                    if (pdfTbody) pdfTbody.innerHTML = '';

                    d.measurements.forEach(function(m, idx) {
                        addMeasurementCardLife();

                        const $cards = $('#measurement_container_life .measurement-card-life');
                        const $card = $cards.eq(idx);
                        if ($card.length) {
                            if (m.item) $card.find('input[name="measurement_item_life[]"]').val(m.item);
                            if (m.quantity) $card.find('input[name="measurement_quantity_life[]"]').val(m.quantity);
                            if (m.area) $card.find('input[name="measurement_area_life[]"]').val(m.area);
                            if (m.label_number) $card.find('input[name="measurement_label_number_life[]"]').val(m.label_number);
                            if (m.remark) $card.find('input[name="measurement_remark_life[]"]').val(m.remark);
                            window.setLabUnits($card.find('[name="measurement_forensic_unit_life[]"]'), m.forensic_unit);

                            // การบรรจุหีบ
                            const mIdx = idx;
                            if (m.package_plastic) {
                                $card.find('input[name="measurement_package_plastic_check_' + mIdx + '"]').prop('checked', true);
                                const $pkgInput = $card.find('#package_plastic_' + mIdx);
                                if ($pkgInput.length) {
                                    $pkgInput.prop('disabled', false);
                                    if (m.package_plastic_text) $pkgInput.val(m.package_plastic_text);
                                }
                            }
                            if (m.package_paper) {
                                $card.find('input[name="measurement_package_paper_check_' + mIdx + '"]').prop('checked', true);
                                const $pkgInput = $card.find('#package_paper_' + mIdx);
                                if ($pkgInput.length) {
                                    $pkgInput.prop('disabled', false);
                                    if (m.package_paper_text) $pkgInput.val(m.package_paper_text);
                                }
                            }
                            if (m.package_other) {
                                $card.find('input[name="measurement_package_other_check_' + mIdx + '"]').prop('checked', true);
                                const $pkgInput = $card.find('#package_other_' + mIdx);
                                if ($pkgInput.length) {
                                    $pkgInput.prop('disabled', false);
                                    if (m.package_other_text) $pkgInput.val(m.package_other_text);
                                }
                            }

                            // การดำเนินการ
                            if (m.action_return) {
                                $card.find('input[name="measurement_action_return_check_' + mIdx + '"]').prop('checked', true);
                                const $actInput = $card.find('#action_return_' + mIdx);
                                if ($actInput.length) {
                                    $actInput.prop('disabled', false);
                                    if (m.action_return_text) $actInput.val(m.action_return_text);
                                }
                            }
                            if (m.action_other) {
                                $card.find('input[name="measurement_action_other_check_' + mIdx + '"]').prop('checked', true);
                                const $actInput = $card.find('#action_other_action_' + mIdx);
                                if ($actInput.length) {
                                    $actInput.prop('disabled', false);
                                    if (m.action_other_text) $actInput.val(m.action_other_text);
                                }
                            }
                        }

                        // ★ เพิ่ม: สร้าง row ในตาราง PDF ด้วย
                        if (pdfTbody) {
                            const tr = document.createElement('tr');
                            tr.innerHTML =
                                '<td style="text-align:center;">' + (idx + 1) + '</td>' +
                                '<td><input type="text" name="measurement_item_life[]" value="' + (m.item || '').replace(/"/g, '&quot;') + '"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
                                '<td><input type="text" name="measurement_quantity_life[]" style="width:30px; text-align:center;" value="' + (m.quantity || '').replace(/"/g, '&quot;') + '"></td>' +
                                '<td><input type="text" name="measurement_area_life[]" value="' + (m.area || '').replace(/"/g, '&quot;') + '"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
                                '<td><input type="text" name="measurement_label_number_life[]" style="width:30px; text-align:center;" value="' + (m.label_number || '').replace(/"/g, '&quot;') + '"></td>' +
                                '<td><input type="checkbox" name="measurement_package_plastic_check_' + idx + '" value="1"' + (m.package_plastic ? ' checked' : '') + '></td>' +
                                '<td><input type="checkbox" name="measurement_package_paper_check_' + idx + '" value="1"' + (m.package_paper ? ' checked' : '') + '></td>' +
                                '<td><input type="checkbox" name="measurement_package_other_check_' + idx + '" value="1"' + (m.package_other ? ' checked' : '') + '></td>' +
                                '<td><input type="checkbox" name="measurement_action_return_check_' + idx + '" value="1"' + (m.action_return ? ' checked' : '') + '></td>' +
                                '<td><input type="checkbox" name="measurement_action_other_check_' + idx + '" value="1"' + (m.action_other ? ' checked' : '') + '></td>' +
                                '<td><input type="text" name="measurement_remark_life[]" value="' + (m.remark || '').replace(/"/g, '&quot;') + '"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="HW"><i class="fas fa-pen"></i></button></td>' +
                                '<td><select class="lab-unit-multi" multiple size="3" title="เลือกได้มากกว่า 1 กลุ่มงาน" style="font-size:9px; padding:1px; width:100%;"><option value="">--</option><option value="fingerprint">ลายนิ้วมือแฝง</option><option value="bio_dna">ชีววิทยา/ดีเอ็นเอ</option><option value="chemical">เคมีฟิสิกส์</option><option value="drug">ยาเสพติด</option><option value="gun">อาวุธปืน</option><option value="document">เอกสาร</option><option value="digital">ดิจิทัล</option></select><input type="hidden" class="lab-unit-value" name="measurement_forensic_unit_life[]" value=""></td>' +
                                '<td><button type="button" class="lpf-del-btn" onclick="lpfDelRow(this)">×</button></td>';
                            pdfTbody.appendChild(tr);
                            window.setLabUnits($(tr).find('[name="measurement_forensic_unit_life[]"]'), m.forensic_unit);
                        }
                    });
                }

                if (mmeta.recorder) {
                    $('#measurement_recorder_life').val(mmeta.recorder);
                    $('#lpf_measurement_recorder').val(mmeta.recorder);
                }
                if (mmeta.datetime) {
                    $('#measurement_datetime_life').val(mmeta.datetime);
                    $('#lpf_measurement_datetime').val(mmeta.datetime);
                }

                // ==================== 13. บันทึกการถ่ายภาพ (Fieldset 13) ====================
                if (pr.photo_id_start) {
                    $('#photo_id_start_life').val(pr.photo_id_start);
                    $pdfForm.find('[name="photo_id_start_life"]').val(pr.photo_id_start);
                }
                if (pr.photo_id_end) {
                    $('#photo_id_end_life').val(pr.photo_id_end);
                    $pdfForm.find('[name="photo_id_end_life"]').val(pr.photo_id_end);
                }
                if (pr.photo_amount) {
                    $('#photo_amount_life').val(pr.photo_amount);
                    $pdfForm.find('[name="photo_amount_life"]').val(pr.photo_amount);
                }
                if (pr.photographer_name) {
                    $('#photographer_name_life').val(pr.photographer_name);
                    $('#lpf_photographer_name').val(pr.photographer_name);
                }
                if (pr.photo_inspect_date) {
                    $('#photo_inspect_date_life').val(pr.photo_inspect_date);
                    $pdfForm.find('[name="photo_inspect_date_life"]').val(pr.photo_inspect_date);
                }
                if (pr.photo_inspect_time) {
                    $pdfForm.find('[name="photo_inspect_time_life"]').val(pr.photo_inspect_time);
                }
                // สร้าง datetime สำหรับ PDF form (ผู้จดบันทึก)
                if (pr.photo_inspect_date || pr.photo_inspect_time) {
                    const photoDate = pr.photo_inspect_date || new Date().toISOString().split('T')[0];
                    const photoTime = pr.photo_inspect_time || '00:00';
                    const photoDatetime = photoDate + 'T' + photoTime;
                    $('#lpf_photographer_datetime').val(photoDatetime);
                }

                _refreshLifeStandardUi();

                // รูปภาพ (photos) - แสดง preview จาก BLOB file_id หรือ base64 (legacy)
                window.existingPhotosStoreLife = []; // รีเซ็ตก่อนโหลดใหม่
                // ★ รีเซ็ต + เตรียม attachmentStoreLife สำหรับ PDF form (ใช้ window scope เพราะ loadLifeData อยู่นอก document.ready)
                if (typeof window.attachmentStoreLife === 'undefined') window.attachmentStoreLife = [];
                window.attachmentStoreLife.length = 0;
                if (d.photos && d.photos.length > 0) {
                    $('#attachments_wrapper_life').removeClass('d-none');
                    $('#file_count_badge_life').text(d.photos.length);

                    d.photos.forEach(function(photo, photoIndex) {
                        let imgSrc = '';
                        let storeEntry = {};

                        if (photo.file_id) {
                            // ★ BLOB: ใช้ getFile.php?id=N
                            imgSrc = './api/incidentCheckList/getFile.php?id=' + photo.file_id;
                            storeEntry = {
                                file_id: photo.file_id
                            };
                            // ★ เพิ่มเข้า attachmentStoreLife สำหรับ PDF form (เหมือน property form)
                            window.attachmentStoreLife.push({
                                id: 'existing_' + photo.file_id,
                                src: imgSrc,
                                existing: true,
                                db_file_id: photo.file_id,
                                name: photo.filename || photo.name || 'photo_' + (photoIndex + 1) + '.jpg',
                                caption: photo.caption || ''
                            });
                        } else {
                            // Legacy: base64 จาก disk
                            imgSrc = photo.base64 || '';
                            storeEntry = {
                                base64: imgSrc,
                                fileName: photo.original_name || photo.filename || 'photo',
                                filename: photo.filename || ''
                            };
                            // ★ เพิ่มเข้า attachmentStoreLife สำหรับ PDF form (เหมือน property form)
                            if (imgSrc) {
                                window.attachmentStoreLife.push({
                                    id: 'existing_disk_' + photoIndex,
                                    src: imgSrc,
                                    existing: true,
                                    db_file_id: null,
                                    name: photo.filename || photo.name || 'photo_' + (photoIndex + 1) + '.jpg',
                                    disk_filename: photo.filename || '',
                                    caption: ''
                                });
                            }
                        }

                        window.existingPhotosStoreLife.push(storeEntry);

                        if (imgSrc) {
                            const fileId = photo.file_id || '';
                            const safeFilename = photo.filename || '';
                            const fileName = photo.original_name || photo.filename || 'photo';
                            const deleteArg = fileId ? fileId : "'" + safeFilename + "'";
                            const dataAttr = fileId ?
                                `data-file-id="${fileId}"` :
                                `data-file-id="saved_life_${safeFilename}" data-filename="${safeFilename}"`;

                            const cardHtml = `
                                <div class="col attachment-item" ${dataAttr}>
                                    <div class="card attachment-card h-100">
                                        <div class="card-actions-bar">
                                            <button type="button" class="action-btn" title="ดูรูปภาพ"
                                                onclick="showImagePreviewLife(${photoIndex})">
                                                <i class="far fa-eye" style="font-size: 0.8rem;"></i>
                                            </button>
                                            <button type="button" class="action-btn delete"
                                                onclick="removeExistingPhotoLife(${deleteArg})"
                                                title="ลบรูปนี้">
                                                <i class="fas fa-times" style="font-size: 0.85rem;"></i>
                                            </button>
                                        </div>
                                        <div class="img-thumbnail-box">
                                            <img src="${imgSrc}" alt="${fileName}">
                                        </div>
                                        <div class="card-body d-flex flex-column">
                                            <div class="filename-text mb-auto" title="${fileName}">${fileName}</div>
                                            <div class="pt-2">
                                                <span class="badge bg-info text-white fw-normal" style="font-size: 0.65rem;">รูปเดิม</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>`;
                            $('#attachments_grid_life').append(cardHtml);
                        }
                    });
                }

                // ★ เรียก onComplete callback หลังโหลดข้อมูลเสร็จ
                if (typeof onComplete === 'function') {
                    setTimeout(function() {
                        onComplete(d);
                    }, 300);
                }

            },
            error: function(xhr, status, error) {
                console.error('Error loading life data:', error);
                // ★ กรณี error ก็ต้องเรียก onComplete เพื่อซ่อน loading overlay
                if (typeof onComplete === 'function') {
                    setTimeout(function() {
                        onComplete(null);
                    }, 300);
                }
            }
        });
    }

    // =========================================================
    // Helper: Lock / Unlock scroll on modal-body during loading
    // Uses native addEventListener with { passive: false } so
    // preventDefault() actually works on wheel & touchmove.
    // Also blocks keyboard scrolling (arrows, space, page keys).
    // =========================================================
    var _scrollKeys = {
        32: 1,
        33: 1,
        34: 1,
        35: 1,
        36: 1,
        37: 1,
        38: 1,
        39: 1,
        40: 1
    };

    function lockModalBodyScroll($modalBody) {
        var el = $modalBody[0];
        if (!el) return;

        function wheelHandler(e) {
            e.preventDefault();
        }

        function keyHandler(e) {
            if (_scrollKeys[e.keyCode]) {
                e.preventDefault();
            }
        }
        el._loadLockWheel = wheelHandler;
        el._loadLockKey = keyHandler;
        el.addEventListener('wheel', wheelHandler, {
            passive: false
        });
        el.addEventListener('touchmove', wheelHandler, {
            passive: false
        });
        document.addEventListener('keydown', keyHandler, {
            passive: false
        });
    }

    function unlockModalBodyScroll($modalBody) {
        var el = $modalBody[0];
        if (!el) return;
        if (el._loadLockWheel) {
            el.removeEventListener('wheel', el._loadLockWheel);
            el.removeEventListener('touchmove', el._loadLockWheel);
            delete el._loadLockWheel;
        }
        if (el._loadLockKey) {
            document.removeEventListener('keydown', el._loadLockKey);
            delete el._loadLockKey;
        }
    }

    // =========================================================
    // 4. DOCUMENT READY (Main Logic)
    // =========================================================
    $(document).ready(function() {

        // --- Initialization ---
        // กำหนดค่าตัวแปร Global
        inputPhotos = $('#incident_photos');

        // ตัวแปรสำหรับ modal_life
        let inputPhotosLife = $('#incident_photos_life');
        let cameraInputLife = $('#camera_input_life');
        window.attachmentStoreLife = window.attachmentStoreLife || []; // ★ ใช้ window scope เพื่อแชร์กับ PDF form
        window.deletedExistingPhotosLife = window.deletedExistingPhotosLife || []; // ★ ใช้ window scope เพื่อแชร์กับ PDF form
        window.existingPhotosStoreLife = window.existingPhotosStoreLife || []; // ★ ใช้ window scope เพื่อให้ showImagePreviewLife เข้าถึงได้ (ชีวิต)

        // ตัวแปรสำหรับ modal_bomb
        let inputPhotosBomb = $('#incident_photos_bomb');
        let cameraInputBomb = $('#camera_input_bomb');
        window.attachmentStoreBomb = window.attachmentStoreBomb || attachmentStoreBomb;
        window.deletedExistingPhotosBomb = window.deletedExistingPhotosBomb || deletedExistingPhotosBomb;

        // ตัวแปรสำหรับ modal_traffice
        let inputPhotosTraffic = $('#incident_photos_traffic');
        let cameraInputTraffic = $('#camera_input_traffic');

        const totalPagesFromPHP = <?php echo $total_pages; ?>;
        const currentPageFromPHP = <?php echo $page; ?>;
        if (totalPagesFromPHP > 0) {
            setupPagination(totalPagesFromPHP, currentPageFromPHP);
        }

        setDefaultDate();

        // ★ เลือกจังหวัด (filter) → กรอง สภ./สน.
        $('#filter_province').on('change', function() {
            var provId = $(this).val();
            $('#filter_station option').each(function() {
                var prov = $(this).data('province');
                if (!prov) {
                    $(this).show();
                    return;
                }
                $(this).toggle(String(prov) === String(provId));
            });
            $('#filter_station').val('');
        });

        // --- Event: คลิกแถวตารางเพื่อเปิด Modal และ set ค่า hidden inputs ---
        $(document).on('click', '.checklist-row', function(e) {
            // ถ้าคลิกที่ปุ่มดาวน์โหลด PDF หรือ QR ไม่ต้องเปิด modal
            if ($(e.target).closest('.btn-download-pdf, .btn-report-pdf, .btn-no-action, .btn-qr').length) {
                return;
            }

            const $row = $(this);
            const id = $row.data('id');
            const docNo = $row.data('doc-no');
            const reportNo = $row.data('report-no');
            const docNoTH = thaiDocNo(docNo);
            const reportNoTH = thaiReportNo(reportNo);
            const complaintsType = String($row.data('complaints-type')).padStart(2, '0');
            const statusChecklist = $row.data('status-checklist');

            console.log($row, "<------row");

            console.log('complaintsType:', complaintsType, typeof complaintsType);

            // เช็คประเภทเหตุและเปิด Modal ที่ถูกต้อง
            let modalId = '';
            let modalElement = null;

            if (complaintsType === '01') {
                // ทรัพย์ → เปิด PDF modal เป็นค่าเริ่มต้น
                modalId = 'propertyFormPdfModal';

                // รีเซ็ตฟอร์มก่อนโหลดข้อมูล
                resetPropertyForm();

                // ตั้งค่า hidden fields ทั้งสองฟอร์ม
                $('#receiveNoti_id').val(id);
                $('#doc_no').val(docNo);
                $('#report_no').val(reportNo);
                $('#receiveNoti_No').text(docNoTH);
                $('#receiveNotiReportNo').text(reportNoTH);

                // PDF form hidden fields
                $('#ppf_receiveNoti_id').val(id);
                $('#ppf_doc_no').val(docNo);
                $('#ppf_report_no').val(reportNo);
                var rptParts = (reportNo || '').split('/');
                var rptNum = thaiReportNo(rptParts[0] || '');
                var rptYear = (rptParts[1] || '').toString().slice(-2);
                $('#ppf_report_no_display').text(rptNum);
                $('#ppf_report_year_display').text(rptYear);
                $('#propertyFormPdfModal .ppf-rpt-no-mirror').text(rptNum);
                $('#propertyFormPdfModal .ppf-rpt-year-mirror').text(rptYear);

                // Auto-fill ข้อมูลผู้จดบันทึก (Section 11) จาก session ของ user ที่ login อยู่
                const propUserName = '<?php echo addslashes(trim(($_SESSION["rank_name"] ?? "") . " " . ($_SESSION["first_name"] ?? "") . " " . ($_SESSION["last_name"] ?? ""))); ?>';
                $('#recorder_name').val(propUserName);
                $('#ppf_recorder_name').val(propUserName);
                $('#ppf_ev_loc_recorder').val(propUserName);
                $('#ppf_collection_recorder').val(propUserName);
                // ตั้งวัน/เวลาปัจจุบัน
                const now = new Date();
                const pad = (n) => String(n).padStart(2, '0');
                const localDatetime = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
                $('#recorder_datetime').val(localDatetime);
                $('#ppf_recorder_datetime').val(localDatetime);
                $('#ppf_ev_loc_datetime').val(localDatetime);
                $('#ppf_collection_datetime').val(localDatetime);

                if (statusChecklist == 1) {
                    // มีข้อมูลบันทึกแล้ว → โหลดข้อมูลหลัง modal shown
                    $('#propertyFormPdfModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด');
                    pendingPropertyLoadId = id;
                    pendingPropertyLoadMode = 'edit';
                } else {
                    // ยังไม่มีข้อมูล → prefill หลัง modal shown
                    $('#propertyFormPdfModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด');
                    pendingPropertyLoadId = id;
                    pendingPropertyLoadMode = 'prefill';
                }
            } else if (complaintsType === '02') {
                // ชีวิต → เปิด PDF modal เป็นค่าเริ่มต้น
                modalId = 'lifeFormPdfModal';

                // รีเซ็ตฟอร์มก่อนโหลดข้อมูล
                resetLifeForm();

                // ตั้งค่า hidden fields ทั้งสองฟอร์ม
                $('#receiveNoti_id_life').val(id);
                $('#doc_no_life').val(docNo);
                $('#report_no_life').val(reportNo);
                $('#receiveNoti_No_life').text(docNoTH);
                $('#receiveNotiReportNo_life').text(reportNoTH);
                $('#case_doc_no_life').val(docNoTH);

                // PDF form hidden fields
                $('#lpf_receiveNoti_id').val(id);
                $('#lpf_doc_no').val(docNo);
                $('#lpf_report_no').val(reportNo);
                $('#lpf_case_doc_no').val(docNoTH);
                var lpfRptParts2 = (reportNo || '').split('/');
                $('#lpf_report_no_display').text(thaiReportNo(lpfRptParts2[0] || ''));

                // Auto-fill ข้อมูลผู้วัดบันทึก จาก session ของ user ที่ login อยู่
                const lifeUserName = '<?php echo addslashes(trim(($_SESSION["rank_name"] ?? "") . " " . ($_SESSION["first_name"] ?? "") . " " . ($_SESSION["last_name"] ?? ""))); ?>';
                $('#sketch_recorder_life').val(lifeUserName);
                $('#measurement_recorder_life').val(lifeUserName);
                $('#lpf_sketch_recorder').val(lifeUserName);
                $('#lpf_measurement_recorder').val(lifeUserName);

                // ตั้งวัน/เวลาปัจจุบัน
                const now = new Date();
                const pad = (n) => String(n).padStart(2, '0');
                const dateLocal = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
                const timeLocal = pad(now.getHours()) + ':' + pad(now.getMinutes());
                const localDatetime = dateLocal + 'T' + timeLocal;
                $('#sketch_datetime_life').val(localDatetime);
                $('#measurement_datetime_life').val(localDatetime);
                $('#lpf_sketch_datetime').val(localDatetime);
                $('#lpf_measurement_datetime').val(localDatetime);

                if (statusChecklist == 1) {
                    // มีข้อมูลบันทึกแล้ว → โหลดข้อมูลหลัง modal shown
                    $('#lifeFormPdfModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด');
                    pendingLifeLoadId = id;
                    pendingLifeLoadMode = 'edit';
                } else {
                    // ยังไม่มีข้อมูล → prefill หลัง modal shown
                    $('#lifeFormPdfModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด');
                    pendingLifeLoadId = id;
                    pendingLifeLoadMode = 'prefill';
                }
            } else if (complaintsType === '03') {
                // ระเบิด → เปิด PDF form modal เป็นค่าเริ่มต้น
                modalId = 'bombFormPdfModal';

                // ตั้งค่า hidden fields (standard form)
                $('#receiveNoti_id_bomb').val(id);
                $('#doc_no_bomb').val(docNo);
                $('#report_no_bomb').val(reportNo);
                $('#receiveNoti_No_bomb').text(docNoTH);
                $('#receiveNotiReportNo_bomb').text(reportNoTH);
                $('#case_doc_no_bomb').val(docNoTH);

                // ตั้งค่า PDF form hidden fields ด้วย
                $('#bpf_receiveNoti_id').val(id);
                $('#bpf_doc_no').val(docNo);
                $('#bpf_report_no').val(reportNo);
                $('#bpf_case_doc_no').val(docNoTH);
                var bpfRptParts2 = (reportNo || '').split('/');
                $('#bpf_report_no_display').text(thaiReportNo(bpfRptParts2[0] || ''));

                // ตั้งค่าวันที่และเวลาปัจจุบัน
                const now = new Date();
                const pad = (n) => String(n).padStart(2, '0');
                const dateLocal = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
                const timeLocal = pad(now.getHours()) + ':' + pad(now.getMinutes());
                const localDatetime = dateLocal + 'T' + timeLocal;

                // Set วันที่และเวลาสำหรับทุกฟิลด์ (standard form)
                $('#death_date_bomb').val(dateLocal);
                $('#death_time_bomb').val(timeLocal);
                $('#depart_date_bomb').val(dateLocal);
                $('#depart_time_bomb').val(timeLocal);
                $('#inspect_date1_bomb').val(dateLocal);
                $('#inspect_time1_bomb').val(timeLocal);
                $('#inspect_date2_bomb').val(dateLocal);
                $('#inspect_time2_bomb').val(timeLocal);

                if (statusChecklist == 1) {
                    pendingBombLoadId = id;
                    pendingBombLoadMode = 'edit';
                } else {
                    pendingBombLoadId = id;
                    pendingBombLoadMode = 'prefill';
                }
            } else if (complaintsType === '04') {
                // เพลิงไหม้
                modalId = 'fireFormPdfModal';

                // รีเซ็ตฟอร์มก่อนโหลดข้อมูล
                try {
                    resetFireForm();
                } catch (e) {
                    console.error('resetFireForm error:', e);
                }

                $('#receiveNoti_id_fire').val(id);
                $('#doc_no_fire').val(docNo);
                $('#report_no_fire').val(reportNo);
                $('#receiveNoti_No_fire').text(docNoTH);
                $('#receiveNotiReportNo_fire').text(reportNoTH);
                $('#case_doc_no_fire').val(docNoTH);

                // ตั้งค่า PDF form hidden fields ด้วย
                $('#fpf_receiveNoti_id').val(id);
                $('#fpf_doc_no').val(docNo);
                $('#fpf_report_no').val(reportNo);
                $('#fpf_case_doc_no').val(docNoTH);

                // ตั้งวัน/เวลาปัจจุบัน
                const now = new Date();
                const pad = (n) => String(n).padStart(2, '0');
                const localDatetime = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
                $('#recorder_datetime_fire').val(localDatetime);
                $('#photographer_datetime_fire').val(localDatetime);

                if (statusChecklist == 1) {
                    // มีข้อมูลบันทึกแล้ว → เก็บ ID ไว้โหลดหลัง modal shown เพื่อให้ Select2/Canvas พร้อมก่อน
                    $('#fireFormPdfModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด');
                    pendingFireLoadId = id;
                    pendingFireLoadMode = 'edit';
                } else {
                    // ยังไม่มีข้อมูล → เก็บ ID ไว้ prefill หลัง modal shown
                    $('#fireFormPdfModalLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด');
                    pendingFireLoadId = id;
                    pendingFireLoadMode = 'prefill';
                }
            } else if (complaintsType === '05') {
                // จราจร → เปิด PDF modal เป็นค่าเริ่มต้น
                modalId = 'trafficFormPdfModal';

                // ตั้งค่า hidden fields ทั้งสองฟอร์ม (standard)
                $('#receiveNoti_id_traffic').val(id);
                $('#doc_no_traffic').val(docNo);
                $('#report_no_traffic').val(reportNo);
                $('#receiveNoti_No_Traffic').text(docNoTH);
                $('#receiveNotiReportNo_traffic').text(reportNoTH);
                $('#case_doc_no_traffic').val(docNoTH);

                // PDF form hidden fields
                $('#tpf_receiveNoti_id').val(id);
                $('#tpf_doc_no').val(docNo);
                $('#tpf_report_no').val(reportNo);
                $('#tpf_case_doc_no').val(docNoTH);
                var tpfRptParts2 = (reportNo || '').split('/');
                $('#tpf_report_no_display').text(thaiReportNo(tpfRptParts2[0] || ''));

                // ตั้งค่าวันที่และเวลาปัจจุบัน
                const now = new Date();
                const dateLocal = now.getFullYear() + '-' +
                    String(now.getMonth() + 1).padStart(2, '0') + '-' +
                    String(now.getDate()).padStart(2, '0');
                const timeLocal = String(now.getHours()).padStart(2, '0') + ':' +
                    String(now.getMinutes()).padStart(2, '0');

                if (statusChecklist == 1) {
                    // มีข้อมูลบันทึกแล้ว → เก็บ ID ไว้โหลดหลัง modal shown
                    pendingTrafficLoadId = id;
                    pendingTrafficLoadMode = 'edit';
                } else {
                    // ยังไม่มีข้อมูล → prefill
                    pendingTrafficLoadId = id;
                    pendingTrafficLoadMode = 'prefill';
                }
            } else if (complaintsType === '06') {
                // ตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง)
                modalId = 'addCheckListModalFingerprint';

                // Reset form ก่อนโหลดข้อมูล
                if (typeof resetFingerprintNewForm === 'function') resetFingerprintNewForm();
                if (typeof resetFingerprintForm === 'function') resetFingerprintForm();

                // Clear photo stores
                attachmentStoreFP = [];
                deletedExistingPhotosFP = [];

                // ตั้งค่าข้อมูลฟอร์มมาตรฐาน (new)
                $('#receiveNoti_id_fpn').val(id);
                $('#doc_no_fpn').val(docNo);
                $('#report_no_fpn').val(reportNo);
                $('#receiveNoti_No_fpn').text(docNoTH);
                $('#receiveNotiReportNo_fpn').text(reportNoTH);

                // ตั้งค่าข้อมูลฟอร์ม PDF ด้วย (เผื่อสลับ)
                $('#receiveNoti_id_fp').val(id);
                $('#doc_no_fp').val(docNo);
                $('#report_no_fp').val(reportNo);
                $('#receiveNoti_No_fp').text(docNoTH);
                $('#receiveNotiReportNo_fp').text(reportNoTH);
                // sync photo page mirrors
                $('#addCheckListModalFingerprint .fp-doc-no-mirror').text(docNoTH);

                if (statusChecklist == 1) {
                    $('#addCheckListModalFingerprintLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด');
                    $('#addCheckListModalFingerprintNewLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด');
                    pendingFPLoadId = id;
                    pendingFPLoadMode = 'edit';
                } else {
                    $('#addCheckListModalFingerprintLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด');
                    $('#addCheckListModalFingerprintNewLabel').html('<i class="fas fa-plus fa-lg me-2"></i> เพิ่มรายละเอียด');
                    pendingFPLoadId = id;
                    pendingFPLoadMode = 'prefill';
                }
            } else if (complaintsType === '08') {
                // ตรวจเก็บวัตถุพยานที่บุคคล (Person Evidence)
                modalId = 'personEvidenceFormPdfModal';

                // Reset form ก่อนโหลดข้อมูล
                if (typeof resetPersonEvidenceForm === 'function') resetPersonEvidenceForm();

                // Clear photo stores
                attachmentStoreEV8 = [];
                deletedExistingPhotosEV8 = [];

                // ตั้งค่าข้อมูลฟอร์มมาตรฐาน
                $('#receiveNoti_id_ev8').val(id);
                $('#doc_no_ev8').val(docNo);
                $('#report_no_ev8').val(reportNo);
                $('#receiveNoti_No_ev8').text(docNoTH);
                $('#receiveNotiReportNo_ev8').text(reportNoTH);

                // ตั้งค่าข้อมูลฟอร์ม PDF ด้วย (เผื่อสลับ)
                $('#pepf_receiveNoti_id').val(id);
                $('#pepf_doc_no').val(docNo);
                $('#pepf_report_no').val(reportNo);

                if (statusChecklist == 1) {
                    pendingEV8LoadId = id;
                    pendingEV8LoadMode = 'edit';
                } else {
                    pendingEV8LoadId = id;
                    pendingEV8LoadMode = 'prefill';
                }
            } else if (complaintsType === '07') {
                // ตรวจเก็บวัตถุพยานที่เกิดเหตุ (Scene Evidence)
                modalId = 'sceneEvidenceFormPdfModal';

                // Reset form ก่อนโหลดข้อมูล
                if (typeof resetSceneEvidenceForm === 'function') resetSceneEvidenceForm();

                // Clear photo stores
                attachmentStoreEV7 = [];
                deletedExistingPhotosEV7 = [];

                // ตั้งค่าข้อมูลฟอร์มมาตรฐาน
                $('#receiveNoti_id_ev7').val(id);
                $('#doc_no_ev7').val(docNo);
                $('#report_no_ev7').val(reportNo);
                $('#receiveNoti_No_ev7').text(docNoTH);
                $('#receiveNotiReportNo_ev7').text(reportNoTH);

                // ตั้งค่าข้อมูลฟอร์ม PDF ด้วย (เผื่อสลับ)
                $('#sevpf_receiveNoti_id').val(id);
                $('#sevpf_doc_no').val(docNo);
                $('#sevpf_report_no').val(reportNo);
                $('#sevpf_receiveNoti_No').text(docNoTH);
                $('#sevpf_receiveNotiReportNo').text(reportNoTH);

                if (statusChecklist == 1) {
                    pendingEV7LoadId = id;
                    pendingEV7LoadMode = 'edit';
                    console.log('[EV7 Click] statusChecklist=1 → edit mode, id:', id);
                } else {
                    pendingEV7LoadId = id;
                    pendingEV7LoadMode = 'prefill';
                    console.log('[EV7 Click] statusChecklist=' + statusChecklist + ' → prefill mode, id:', id);
                }
            } else {
                // ยังไม่มี Modal สำหรับประเภทอื่น
                Swal.fire({
                    icon: 'warning',
                    title: 'ยังไม่รองรับ',
                    text: 'ยังไม่มีฟอร์มสำหรับประเภทเหตุนี้',
                    confirmButtonText: 'ตกลง'
                });
                return;
            }

            // ★ เช็คสิทธิ์: create_by ตรงกับ session user_id หรือไม่
            const createBy = String($row.data('create-by') || '');
            const isOwner = (createBy === '' || createBy === String(window._sessionUserId));
            window._currentIsOwner = isOwner;

            // เปิด Modal
            if (modalId) {
                modalElement = document.getElementById(modalId);
                if (modalElement) {
                    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
                    modal.show();

                    // ★ Toggle ปุ่มบันทึกตาม permission (ทั้ง standard + PDF form)
                    var saveBtns = [
                        '#btn_save_all', '#btn_save_property_pdf',
                        '#btn_save_life', '#btn_save_life_pdf',
                        '#btn_save_bomb', '#btn_save_bomb_pdf',
                        '#btn_save_fire', '#btn_save_fire_pdf',
                        '#btn_save_ev7', '#btn_save_ev7_pdf',
                        '#btn_save_ev8', '#btn_save_ev8_pdf',
                        '#btn_save_fpn', '#btn_save_rlfp'
                    ];
                    saveBtns.forEach(function(sel) {
                        var btn = document.querySelector(sel);
                        if (btn) {
                            btn.disabled = !isOwner;
                            btn.title = isOwner ? '' : 'คุณไม่มีสิทธิ์แก้ไข (ไม่ใช่ผู้สร้างรายการ)';
                        }
                    });
                }
            }
        });

        $('#source_station').select2({
            theme: 'bootstrap-5',
            placeholder: "กรุณาเลือก",
            allowClear: true,
            width: '100%',
            dropdownParent: $('#source_station').closest('.col-sm-12'),
            dropdownAutoWidth: true,
            dropdownPosition: 'below'
        });

        $('#userReviewID').select2({
            theme: 'bootstrap-5',
            placeholder: "กรุณาเลือก",
            allowClear: true,
            width: '100%',
            // ระบุให้ช่อง Dropdown ไปเกิดใน Modal นี้
            dropdownParent: $('#addCheckListModal'),
            dropdownAutoWidth: true
        });

        $('#userApproveID').select2({
            theme: 'bootstrap-5',
            placeholder: "กรุณาเลือก",
            allowClear: true,
            width: '100%',
            // ระบุให้ช่อง Dropdown ไปเกิดใน Modal นี้
            dropdownParent: $('#addCheckListModal'),
            dropdownAutoWidth: true
        });

        $('.inspector-select').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: "กรุณาเลือก",
            allowClear: true,
            dropdownParent: $('#addCheckListModal'),
            dropdownAutoWidth: true
        });

        $(document).on('select2:open', '#userReviewID', function(e) {
            window.setTimeout(function() {
                document.querySelector('.select2-search__field').focus();
            }, 0);
        });

        $(document).on('select2:open', '#userApproveID', function(e) {
            window.setTimeout(function() {
                document.querySelector('.select2-search__field').focus();
            }, 0);
        });

        $('.person-phone').mask('000-000-0000', {
            onKeyPress: function(val, e, field, options) {
                var mask = val.startsWith('02') ? '00-000-0000' : '000-000-0000';
                $('.person-phone').mask(mask, options);
            }
        });

        // ฟังก์ชันสำหรับ Re-index (รันเลข 5.1, 5.2 ใหม่)
        function reIndexInspectors() {
            $('.inspector-row').each(function(index) {
                // index เริ่มที่ 0 ดังนั้นต้อง +1
                $(this).find('.index-label').text(`5.${index + 1}`);
            });
        }

        // --- Inspector Logic ---
        // 1. เพิ่มผู้ตรวจ
        $('#btn_add_inspector').on('click', function() {
            const newRow = `
                <div class="d-flex align-items-center mb-3 inspector-row animate__animated animate__fadeIn">
                    
                    <div class="text-end pe-3" style="width: 50px;">
                        <span class="fw-bold text-secondary index-label"></span>
                    </div>
                    
                    <div class="flex-grow-1">
                        <select class="form-select inspector-select" name="inspector_id[]">
                            ${inspectorOptionsHTML}
                        </select>
                    </div>
                    
                    <div class="ms-2">
                        <button type="button" class="btn btn-link btn-sm p-0 border-0 btn-delete-inspector remove-inspector-btn d-flex align-items-center justify-content-center" 
                                style="width: 32px; height: 32px; text-decoration: none;" title="ลบรายการ">
                            <i class="fas fa-times fs-5"></i>
                        </button>
                    </div>

                </div>
            `;

            $('#inspector_container').append(newRow);

            // เก็บ Selector ของตัวล่าสุดไว้ในตัวแปร เพื่อเรียกใช้ง่ายๆ
            const $newSelect = $('#inspector_container .inspector-row:last .inspector-select');

            // Init Select2
            $newSelect.select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: "กรุณาเลือก",
                allowClear: true,
                dropdownParent: $('#addCheckListModal'),
                dropdownAutoWidth: true
            });

            reIndexInspectors();

            // setTimeout(() => {
            //     $newSelect.select2('open');
            // }, 100);
        });

        // 2. ลบผู้ตรวจ
        $(document).on('click', '.remove-inspector-btn', function() {
            $(this).closest('.inspector-row').remove();
            reIndexInspectors();
        });

        // --- Inspector Logic for Life Modal ---
        function reIndexInspectorsLife() {
            $('.inspector-row-life').each(function(index) {
                $(this).find('.index-label').text(`5.${index + 1}`);
                // Show/hide delete button
                if (index === 0) {
                    $(this).find('.remove-inspector-life').hide();
                } else {
                    $(this).find('.remove-inspector-life').show();
                }
            });
        }

        $('#btn_add_inspector_life').on('click', function() {
            const newRow = `
                <div class="d-flex align-items-center mb-3 inspector-row-life animate__animated animate__fadeIn">

                    <div class="text-end pe-3" style="width: 50px;">
                        <span class="fw-bold text-secondary index-label"></span>
                    </div>

                    <div class="flex-grow-1">
                        <select class="form-select inspector-select-life" name="inspector_id[]">
                            ${inspectorOptionsHTMLLife}
                        </select>
                    </div>

                    <div class="ms-2">
                        <button type="button" class="btn btn-link btn-sm p-0 border-0 btn-delete-inspector remove-inspector-life d-flex align-items-center justify-content-center"
                                style="width: 32px; height: 32px; text-decoration: none; color: #dc3545;" title="ลบรายการ">
                            <i class="fas fa-times fs-5"></i>
                        </button>
                    </div>

                </div>
            `;

            $('#inspector_container_life').append(newRow);

            // เก็บ Selector ของตัวล่าสุดไว้ในตัวแปร เพื่อเรียกใช้ง่ายๆ
            const $newSelect = $('#inspector_container_life .inspector-row-life:last .inspector-select-life');

            // Init Select2
            $newSelect.select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: "กรุณาเลือก",
                allowClear: true,
                dropdownParent: $('#addCheckListModalLife'),
                dropdownAutoWidth: true
            });

            reIndexInspectorsLife();
        });

        $(document).on('click', '.remove-inspector-life', function() {
            $(this).closest('.inspector-row-life').remove();
            reIndexInspectorsLife();
        });

        // --- Evidence Logic for Life Modal ---
        function reIndexEvidenceLife() {
            $('.evidence-row-life').each(function(index) {
                $(this).find('.evidence-index-label-life').text(`9.${index + 1}`);
                if (index === 0) {
                    $(this).find('.remove-evidence-life').hide();
                } else {
                    $(this).find('.remove-evidence-life').show();
                }
            });
        }

        $('#btn_add_evidence_life').on('click', function() {
            const count = $('#evidence_container_life .evidence-row-life').length + 1;
            const newRow = `
                <div class="evidence-row-life mb-3 p-3 border rounded animate__animated animate__fadeIn">
                    <div class="row mb-2">
                        <div class="col-md-1">
                            <label class="form-label evidence-index-label-life fw-bold">9.${count}</label>
                        </div>
                        <div class="col-md-10">
                            <label class="form-label">ชนิดวัตถุพยาน</label>
                            <input type="text" class="form-control" name="evidence_type[]" placeholder="ระบุชนิดวัตถุพยาน">
                        </div>
                        <div class="col-md-1 text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-evidence-life">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label class="form-label">ลักษณะ</label>
                            <input type="text" class="form-control" name="evidence_appearance[]" placeholder="ระบุลักษณะ">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">จุดที่พบ</label>
                            <input type="text" class="form-control" name="evidence_location[]" placeholder="ระบุจุดที่พบ">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <label class="form-label">การดำเนินการ</label>
                            <textarea class="form-control" name="evidence_action[]" rows="2" placeholder="ระบุการดำเนินการกับวัตถุพยาน"></textarea>
                        </div>
                    </div>
                </div>
            `;
            $('#evidence_container_life').append(newRow);
            reIndexEvidenceLife();
        });

        $(document).on('click', '.remove-evidence-life', function() {
            $(this).closest('.evidence-row-life').remove();
            reIndexEvidenceLife();
        });

        // --- Evidence Logic for Bomb Modal ** evidence มีให้เพิ่ม 2 ที่ต้องมาดูว่าจะจัดการยังไง ---
        function reIndexEvidenceBomb() {
            $('.evidence-row-bomb').each(function(index) {
                $(this).find('.evidence-index-label-bomb').text(`9.${index + 1}`);
                if (index === 0) {
                    $(this).find('.remove-evidence-bomb').hide();
                } else {
                    $(this).find('.remove-evidence-bomb').show();
                }
            });
        }

        $('#btn_add_evidence_bomb').on('click', function() {
            const count = $('#evidence_container_bomb .evidence-row-bomb').length + 1;
            const newRow = `
                <div class="evidence-row-bomb mb-3 p-3 border rounded animate__animated animate__fadeIn">
                    <div class="row mb-2">
                        <div class="col-md-1">
                            <label class="form-label evidence-index-label-bomb fw-bold">9.${count}</label>
                        </div>
                        <div class="col-md-10">
                            <label class="form-label">ชนิดวัตถุพยาน</label>
                            <input type="text" class="form-control" name="evidence_type[]" placeholder="ระบุชนิดวัตถุพยาน">
                        </div>
                        <div class="col-md-1 text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-evidence-bomb">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label class="form-label">ลักษณะ</label>
                            <input type="text" class="form-control" name="evidence_appearance[]" placeholder="ระบุลักษณะ">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">จุดที่พบ</label>
                            <input type="text" class="form-control" name="evidence_location[]" placeholder="ระบุจุดที่พบ">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <label class="form-label">การดำเนินการ</label>
                            <textarea class="form-control" name="evidence_action[]" rows="2" placeholder="ระบุการดำเนินการกับวัตถุพยาน"></textarea>
                        </div>
                    </div>
                </div>
            `;
            $('#evidence_container_bomb').append(newRow);
            reIndexEvidenceBomb();
        });

        $(document).on('click', '.remove-evidence-bomb', function() {
            $(this).closest('.evidence-row-bomb').remove();
            reIndexEvidenceBomb();
        });

        // --- Injury Logic for Life Modal ---
        function reIndexInjuryLife() {
            $('.injury-row-life').each(function(index) {
                $(this).find('.injury-index-label-life').text(`10.${index + 1}`);
                if (index === 0) {
                    $(this).find('.remove-injury-life').hide();
                } else {
                    $(this).find('.remove-injury-life').show();
                }
            });
        }

        $('#btn_add_injury_life').on('click', function() {
            const count = $('#injury_container_life .injury-row-life').length + 1;
            const newRow = `
                <div class="injury-row-life mb-3 p-3 border rounded bg-light animate__animated animate__fadeIn">
                    <div class="row mb-2">
                        <div class="col-md-1">
                            <label class="form-label injury-index-label-life fw-bold">10.${count}</label>
                        </div>
                        <div class="col-md-10">
                            <label class="form-label">ตำแหน่งบาดแผล</label>
                            <input type="text" class="form-control" name="injury_location[]" placeholder="ระบุตำแหน่งบาดแผล">
                        </div>
                        <div class="col-md-1 text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-injury-life">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label class="form-label">ลักษณะบาดแผล</label>
                            <input type="text" class="form-control" name="injury_type[]" placeholder="เช่น แผลถลอก, แผลฟกช้ำ, แผลแทง">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ขนาดโดยประมาณ</label>
                            <input type="text" class="form-control" name="injury_size[]" placeholder="เช่น 5x3 ซม.">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea class="form-control" name="injury_note[]" rows="2" placeholder="หมายเหตุเพิ่มเติม"></textarea>
                        </div>
                    </div>
                </div>
            `;
            $('#injury_container_life').append(newRow);
            reIndexInjuryLife();
        });

        $(document).on('click', '.remove-injury-life', function() {
            $(this).closest('.injury-row-life').remove();
            reIndexInjuryLife();
        });

        // --- Document Logic for Life Modal ---
        function reIndexDocumentLife() {
            $('.document-row-life').each(function(index) {
                $(this).find('.document-index-label-life').text(`11.${index + 1}`);
                if (index === 0) {
                    $(this).find('.remove-document-life').hide();
                } else {
                    $(this).find('.remove-document-life').show();
                }
            });
        }

        $('#btn_add_document_life').on('click', function() {
            const count = $('#document_container_life .document-row-life').length + 1;
            const newRow = `
                <div class="document-row-life mb-3 p-3 border rounded animate__animated animate__fadeIn">
                    <div class="row mb-2">
                        <div class="col-md-1">
                            <label class="form-label document-index-label-life fw-bold">11.${count}</label>
                        </div>
                        <div class="col-md-10">
                            <label class="form-label">ชื่อเอกสาร</label>
                            <input type="text" class="form-control" name="document_name[]" placeholder="ระบุชื่อเอกสาร">
                        </div>
                        <div class="col-md-1 text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-document-life">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label class="form-label">เลขที่เอกสาร</label>
                            <input type="text" class="form-control" name="document_number[]" placeholder="ระบุเลขที่เอกสาร">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">วันที่</label>
                            <input type="date" class="form-control" name="document_date[]">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea class="form-control" name="document_note[]" rows="2" placeholder="หมายเหตุเพิ่มเติม"></textarea>
                        </div>
                    </div>
                </div>
            `;
            $('#document_container_life').append(newRow);
            reIndexDocumentLife();
        });

        $(document).on('click', '.remove-document-life', function() {
            $(this).closest('.document-row-life').remove();
            reIndexDocumentLife();
        });

        // 1. Init Select2 สำหรับรายชื่อ
        $('.user-select-box').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: "กรุณาเลือก",
            allowClear: true,
            dropdownParent: $('#addCheckListModal'),
            dropdownAutoWidth: true
        });

        // 2. Auto Fill Position (เมื่อเปลี่ยนชื่อ -> ดึงตำแหน่ง)
        $('.user-select-box').on('change', function() {
            var idEmp = $(this).val();
            var targetInput = $(this).data('pos-target'); // รับ ID ของช่องตำแหน่งจาก attribute

            if (!idEmp) {
                $(targetInput).val('');
                return;
            }

            $.ajax({
                url: "/csims/api/ReceiveNoti/getPos.php", // ตรวจสอบ path ให้ถูกต้อง
                type: 'GET',
                data: {
                    idEmp: idEmp
                },
                dataType: 'json',
                success: function(response) {
                    if (response.message == "success") {
                        $(targetInput).val(response.data.position_name);
                    } else {
                        $(targetInput).val('');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error fetching position:", error);
                    $(targetInput).val('');
                }
            });
        });

        $('#addCheckListModal').on('shown.bs.modal', function() {

            // ★ ปลดล็อค checkbox ผลทดสอบเลือด (ไม่มี toggle ทดสอบ → เลือกได้เลย)
            $('#prop_hemastix_positive, #prop_hemastix_negative, #prop_phenol_positive, #prop_phenol_negative').prop('disabled', false);

            const canvases = document.querySelectorAll('.signature-pad');

            canvases.forEach((canvas) => {
                const canvasId = canvas.id;

                // บันทึกข้อมูลเดิมก่อน resize (ถ้ามี)
                var existingData = null;
                if (signaturePads[canvasId] && !signaturePads[canvasId].isEmpty()) {
                    existingData = signaturePads[canvasId].toDataURL();
                }

                // ★ HiDPI: ตั้ง canvas buffer ใหญ่กว่า CSS + scale context (SignaturePad v4 ต้องการสิ่งนี้)
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                if (canvas.offsetWidth > 0) {
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext('2d').scale(ratio, ratio);
                }

                if (signaturePads[canvasId]) {
                    // ถ้า init แล้ว: restore ข้อมูลที่บันทึกไว้
                    if (existingData) {
                        signaturePads[canvasId].fromDataURL(existingData);
                    }
                    return;
                }

                // Init SignaturePad ครั้งแรก
                const pad = new SignaturePad(canvas, {
                    backgroundColor: 'rgba(255, 255, 255, 0)',
                    penColor: 'rgb(0, 0, 0)',
                });

                signaturePads[canvasId] = pad;
            });

            // โหลดข้อมูล property หลัง modal shown (Canvas/Select พร้อมแล้ว)
            if (pendingPropertyLoadId) {
                const loadId = pendingPropertyLoadId;
                const loadMode = pendingPropertyLoadMode;
                pendingPropertyLoadId = null;
                pendingPropertyLoadMode = null;

                // แสดง loading overlay
                var $overlay = $('#propertyLoadingOverlay');
                $overlay.removeClass('d-none').css('display', 'flex');
                var $propModalBody = $overlay.closest('.modal-body');
                lockModalBodyScroll($propModalBody);
                var overlayStart = Date.now();
                var ANIM_MS = 2000; // ตรงกับ animation duration

                function hideOverlayProperty() {
                    var elapsed = Date.now() - overlayStart;
                    var remaining = ANIM_MS - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() {
                            $overlay.addClass('d-none');
                            unlockModalBodyScroll($propModalBody);
                        }, remaining);
                    } else {
                        $overlay.addClass('d-none');
                        unlockModalBodyScroll($propModalBody);
                    }
                }

                if (loadMode === 'edit') {
                    loadPropertyData(loadId, function() {
                        hideOverlayProperty();
                    });
                } else if (loadMode === 'prefill') {
                    prefillIncidentDataForProperty(loadId, function() {
                        hideOverlayProperty();
                    });
                }
            }
        });

        // ========================================================================
        // Initialize Property PDF Modal (ฟอร์มเสมือนคดีทรัพย์)
        // ========================================================================
        $('#propertyFormPdfModal').on('shown.bs.modal', function() {

            // ★ Auto-fill พฤติการณ์คดี from basic_info (rn_ReceiveNoti)
            var dstBehavior = document.getElementById('ppf_case_behavior');
            if (dstBehavior && !dstBehavior.value && window._basicInfoProperty) {
                dstBehavior.value = window._basicInfoProperty;
            }

            // ★ ปลดล็อค checkbox ผลทดสอบเลือด PDF form (ไม่มี toggle ทดสอบ → เลือกได้เลย)
            $('#propertyFormPdf input[name="hemastix_result"], #propertyFormPdf input[name="phenol_result"]').prop('disabled', false);

            // ★ Init/Resize SignaturePad สำหรับ canvas ใน PDF form (ทำทุกครั้งที่ modal เปิด)
            ['ppf_sig_receiver', 'ppf_sig_sender', 'ppf_scene_sketch_canvas'].forEach(function(canvasId) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;

                // บันทึกข้อมูลเดิมก่อน resize
                var existingData = null;
                if (signaturePads[canvasId] && !signaturePads[canvasId].isEmpty()) {
                    existingData = signaturePads[canvasId].toDataURL();
                }

                // ★ HiDPI: ตั้ง canvas buffer ใหญ่กว่า CSS + scale context (SignaturePad v4 ต้องการสิ่งนี้)
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                if (canvas.offsetWidth > 0) {
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext('2d').scale(ratio, ratio);
                }

                if (signaturePads[canvasId]) {
                    // Restore ข้อมูลเดิมหลัง resize
                    if (existingData) {
                        signaturePads[canvasId].fromDataURL(existingData);
                    }
                    return;
                }

                // Init ครั้งแรก — เส้นบางสำหรับ sig, ปกติสำหรับ sketch
                var isSig = (canvasId === 'ppf_sig_receiver' || canvasId === 'ppf_sig_sender');
                const pad = new SignaturePad(canvas, {
                    backgroundColor: 'rgba(255, 255, 255, 0)',
                    penColor: 'rgb(0, 0, 0)',
                    minWidth: isSig ? 0.3 : 0.5,
                    maxWidth: isSig ? 1.2 : 2.5,
                });
                signaturePads[canvasId] = pad;
            });

            // โหลดข้อมูล property หลัง PDF modal shown
            if (pendingPropertyLoadId) {
                const loadId = pendingPropertyLoadId;
                const loadMode = pendingPropertyLoadMode;
                pendingPropertyLoadId = null;
                pendingPropertyLoadMode = null;

                // แสดง loading overlay
                var $overlayPdf = $('#propertyPdfLoadingOverlay');
                $overlayPdf.removeClass('d-none').css('display', 'flex');
                var $propPdfModalBody = $overlayPdf.closest('.modal-body');
                lockModalBodyScroll($propPdfModalBody);
                var overlayPdfStart = Date.now();
                var ANIM_MS_PDF = 2000;

                function hideOverlayPropertyPdf() {
                    var elapsed = Date.now() - overlayPdfStart;
                    var remaining = ANIM_MS_PDF - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() {
                            $overlayPdf.addClass('d-none');
                            unlockModalBodyScroll($propPdfModalBody);
                        }, remaining);
                    } else {
                        $overlayPdf.addClass('d-none');
                        unlockModalBodyScroll($propPdfModalBody);
                    }
                }

                if (loadMode === 'edit') {
                    // โหลดข้อมูลลงฟอร์มมาตรฐานก่อน แล้ว sync ไปยัง PDF
                    loadPropertyData(loadId, function(d) {
                        syncPropertyFormData('incidentCheckListForm', 'propertyFormPdf');
                        _syncPropertyInspectorToPdf();
                        _syncPropertyTracePointsToPdf();
                        _syncPropertyEvidenceToPdf();
                        // sync วันที่/เวลาตรวจ → หน้ารูปถ่าย
                        var inspDT2 = $('#inspection_datetime').val() || '';
                        if (inspDT2) {
                            var p2 = inspDT2.split('T');
                            $('#ppf_photo_inspect_date').val(p2[0] || '');
                            $('#ppf_photo_inspect_time').val(p2[1] || '');
                        }
                        if (typeof ppfRenderPhotosFromStore === 'function') ppfRenderPhotosFromStore();

                        // ★ โหลด BLOB signatures ลง PDF canvases ด้วย
                        var ho2 = (d && d.handover) || {};
                        var pdfSigMap = {
                            'receiver_sig_file_id': 'ppf_sig_receiver',
                            'sender_sig_file_id': 'ppf_sig_sender',
                            'sketch_file_id': 'ppf_scene_sketch_canvas'
                        };
                        Object.keys(pdfSigMap).forEach(function(key) {
                            if (ho2[key]) {
                                var url = './api/incidentCheckList/getFile.php?id=' + ho2[key];
                                loadSigFromUrl(pdfSigMap[key], url, null, null);
                            }
                        });
                        if (typeof window.ppfAutoGrowAll === 'function') window.ppfAutoGrowAll();
                        hideOverlayPropertyPdf();
                    });
                } else if (loadMode === 'prefill') {
                    prefillIncidentDataForProperty(loadId, function() {
                        syncPropertyFormData('incidentCheckListForm', 'propertyFormPdf');
                        if (typeof ppfRenderPhotosFromStore === 'function') ppfRenderPhotosFromStore();
                        if (typeof window.ppfAutoGrowAll === 'function') window.ppfAutoGrowAll();
                        hideOverlayPropertyPdf();
                    });
                }
            }
        });

        // ========================================================================
        // Global auto-grow: textarea แบบเส้นบรรทัด (lined) ในฟอร์ม PDF checklist
        // พิมพ์เกิน → ช่องสูงขึ้นทีละ 20px เส้นประเพิ่มเอง / โหลดข้อมูลเดิมมาก็ขยายตาม
        // (property จัดการแยกในไฟล์ modal_property_pdf_form.php แล้ว)
        // ========================================================================
        (function() {
            var LINE = 20;
            var SEL = '#propertyFormPdfModal .ppf-ta, #lifeFormPdfModal .lpf-ta, #bombFormPdfModal .bpf-ta, '
                    + '#fireFormPdfModal .fpf-ta, #trafficFormPdfModal .tpf-ta, '
                    + '#personEvidenceFormPdfModal .pepf-ta, #sceneEvidenceFormPdfModal .sevpf-ta';
            function autoGrow(ta) {
                ta.style.height = 'auto';
                ta.style.height = (Math.ceil(ta.scrollHeight / LINE) * LINE) + 'px';
            }
            window.csimsAutoGrowAll = function(root) {
                (root || document).querySelectorAll(SEL).forEach(function(ta) {
                    if (!ta.dataset.agMin) {
                        var rows = parseInt(ta.getAttribute('rows'), 10) || 1;
                        ta.style.minHeight = (rows * LINE) + 'px';
                        ta.dataset.agMin = '1';
                    }
                    autoGrow(ta);
                });
            };
            // พิมพ์เอง → ขยายทันที (event delegation)
            document.addEventListener('input', function(e) {
                var t = e.target;
                if (t && t.matches && t.matches(SEL)) autoGrow(t);
            });
            // เปิด modal / โหลดข้อมูลเดิมเสร็จ → คำนวณความสูงใหม่ (เผื่อโหลด async)
            ['lifeFormPdfModal','bombFormPdfModal','fireFormPdfModal','trafficFormPdfModal',
             'personEvidenceFormPdfModal','sceneEvidenceFormPdfModal'].forEach(function(id) {
                var el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('shown.bs.modal', function() {
                    window.csimsAutoGrowAll(el);
                    setTimeout(function(){ window.csimsAutoGrowAll(el); }, 400);
                    setTimeout(function(){ window.csimsAutoGrowAll(el); }, 1200);
                });
            });

            // ----- textarea แบบ inline (.csims-tline) ที่แปลงมาจาก <input> -----
            // โตตามเนื้อหาจริง (ไม่ snap 20px เพราะไม่มีเส้นพื้นหลัง)
            function growLine(ta) {
                ta.style.height = 'auto';
                ta.style.height = ta.scrollHeight + 'px';
            }
            window.csimsGrowLineAll = function(root) {
                (root || document).querySelectorAll('.csims-tline').forEach(growLine);
            };
            document.addEventListener('input', function(e) {
                var t = e.target;
                if (t && t.classList && t.classList.contains('csims-tline')) growLine(t);
            });
            // recompute เมื่อ modal ใด ๆ ถูกเปิด หรือโหลดข้อมูลเดิมเสร็จ (async)
            $(document).on('shown.bs.modal', '.modal', function() {
                var el = this;
                window.csimsGrowLineAll(el);
                setTimeout(function(){ window.csimsGrowLineAll(el); }, 400);
                setTimeout(function(){ window.csimsGrowLineAll(el); }, 1200);
            });
        })();

        // ========================================================================
        // Initialize Life PDF Modal (ฟอร์มเสมือนคดีชีวิต)
        // ========================================================================
        $('#lifeFormPdfModal').on('shown.bs.modal', function() {

            // ★ ปลดล็อค checkbox ผลทดสอบเลือด PDF form
            $('#lifeFormPdf input[name="hemastix_result"], #lifeFormPdf input[name="phenol_result"]').prop('disabled', false);

            // ★ Init/Resize SignaturePad สำหรับ canvas ใน PDF form (ทำทุกครั้งที่ modal เปิด)
            ['lpf_sig_receiver', 'lpf_sig_sender', 'lpf_scene_sketch_canvas', 'lpf_body_diagram_canvas'].forEach(function(canvasId) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;

                // บันทึกข้อมูลเดิมก่อน resize
                var existingData = null;
                if (signaturePads[canvasId] && !signaturePads[canvasId].isEmpty()) {
                    existingData = signaturePads[canvasId].toDataURL();
                }

                // ★ HiDPI: ตั้ง canvas buffer ใหญ่กว่า CSS + scale context
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                if (canvas.offsetWidth > 0) {
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext('2d').scale(ratio, ratio);
                }

                if (signaturePads[canvasId]) {
                    if (existingData) {
                        signaturePads[canvasId].fromDataURL(existingData);
                    }
                    return;
                }

                // Init ครั้งแรก — เส้นบาง sig, ปกติ sketch, แดง body diagram
                var isSig = (canvasId === 'lpf_sig_receiver' || canvasId === 'lpf_sig_sender');
                var isBody = (canvasId === 'lpf_body_diagram_canvas');
                const pad = new SignaturePad(canvas, {
                    backgroundColor: 'rgba(255, 255, 255, 0)',
                    penColor: isBody ? 'rgb(204, 0, 0)' : 'rgb(0, 0, 0)',
                    minWidth: isSig ? 0.3 : 0.5,
                    maxWidth: isSig ? 1.2 : 2.5,
                });
                signaturePads[canvasId] = pad;
            });

            // เปิด modal แบบ edit/prefill ให้เคลียร์ผิววาดเดิมก่อน เพื่อกันภาพค้างจากเคสก่อนหน้า
            if (pendingLifeLoadId) {
                ['lpf_sig_receiver', 'lpf_sig_sender', 'lpf_scene_sketch_canvas', 'lpf_body_diagram_canvas'].forEach(function(canvasId) {
                    const pad = signaturePads[canvasId];
                    if (pad) {
                        pad.clear();
                    } else {
                        const canvas = document.getElementById(canvasId);
                        if (canvas) {
                            const ctx = canvas.getContext('2d');
                            ctx.clearRect(0, 0, canvas.width, canvas.height);
                        }
                    }
                });
            }

            // โหลดข้อมูล life หลัง PDF modal shown
            if (pendingLifeLoadId) {
                const loadId = pendingLifeLoadId;
                const loadMode = pendingLifeLoadMode;
                pendingLifeLoadId = null;
                pendingLifeLoadMode = null;

                // แสดง loading overlay
                var $overlayLifePdf = $('#lifePdfLoadingOverlay');
                $overlayLifePdf.removeClass('d-none').css('display', 'flex');
                var $lifePdfModalBody = $overlayLifePdf.closest('.modal-body');
                lockModalBodyScroll($lifePdfModalBody);
                var overlayLifePdfStart = Date.now();
                var ANIM_MS_LIFE_PDF = 2000;

                function hideOverlayLifePdf() {
                    var elapsed = Date.now() - overlayLifePdfStart;
                    var remaining = ANIM_MS_LIFE_PDF - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() {
                            $overlayLifePdf.addClass('d-none');
                            unlockModalBodyScroll($lifePdfModalBody);
                        }, remaining);
                    } else {
                        $overlayLifePdf.addClass('d-none');
                        unlockModalBodyScroll($lifePdfModalBody);
                    }
                }

                if (loadMode === 'edit') {
                    // โหลดข้อมูลลงทั้ง 2 ฟอร์มโดยตรง (loadLifeData โหลดลงทั้งคู่แล้ว)
                    loadLifeData(loadId, function(d) {
                        // sync เฉพาะเมื่อฟอร์มมาตรฐานมีอยู่ใน DOM
                        if (document.getElementById('incidentCheckListFormLife')) {
                            syncLifeFormData('incidentCheckListFormLife', 'lifeFormPdf');
                            _syncLifeInspectorToPdf();
                            _syncLifeVictimToPdf();
                            _syncLifeEvidenceToPdf();
                            _syncLifeMeasurementToPdf();
                        }
                        if (typeof lpfRenderPhotosFromStore === 'function') lpfRenderPhotosFromStore();

                        // ★ โหลด signatures จาก BLOB file_id หรือ base64 ลง PDF canvases
                        if (d && d.signatures) {
                            var sigMap = {
                                'receiver_sig': 'lpf_sig_receiver',
                                'sender_sig': 'lpf_sig_sender',
                                'scene_sketch': 'lpf_scene_sketch_canvas'
                            };
                            Object.keys(sigMap).forEach(function(key) {
                                if (d.signatures[key]) {
                                    var sigData = d.signatures[key];
                                    var canvasId = sigMap[key];
                                    var pad = signaturePads[canvasId];
                                    // ★ รองรับ file_id (BLOB) + base64 (legacy)
                                    var sigSrc = sigData.file_id ?
                                        './api/incidentCheckList/getFile.php?id=' + sigData.file_id :
                                        sigData.base64 || '';
                                    if (pad && sigSrc) {
                                        pad.clear();
                                        pad.fromDataURL(sigSrc);
                                    }
                                }
                            });

                            // Body diagram: ใช้ strokes ถ้ามี, fallback ใช้ BLOB/base64
                            setTimeout(function() {
                                var bdPad = signaturePads['lpf_body_diagram_canvas'];
                                if (bdPad && d.body_diagram_strokes) {
                                    try {
                                        var strokes = typeof d.body_diagram_strokes === 'string' ?
                                            JSON.parse(d.body_diagram_strokes) : d.body_diagram_strokes;
                                        if (strokes && strokes.length > 0) {
                                            bdPad.clear();
                                            bdPad.fromData(strokes);
                                        }
                                    } catch (e) {}
                                } else if (bdPad && d.signatures && d.signatures.body_diagram) {
                                    var bdSigData = d.signatures.body_diagram;
                                    // ★ รองรับ file_id (BLOB) + base64 (legacy)
                                    var bdImgSrc = bdSigData.file_id ?
                                        './api/incidentCheckList/getFile.php?id=' + bdSigData.file_id :
                                        bdSigData.base64 || '';
                                    if (bdImgSrc) {
                                        var canvas = document.getElementById('lpf_body_diagram_canvas');
                                        var ctx = canvas.getContext('2d');
                                        var img = new Image();
                                        img.onload = function() {
                                            ctx.save();
                                            ctx.setTransform(1, 0, 0, 1, 0, 0);
                                            ctx.clearRect(0, 0, canvas.width, canvas.height);
                                            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                                            ctx.restore();
                                            bdPad._isEmpty = false;
                                        };
                                        img.src = bdImgSrc;
                                    }
                                }
                            }, 200);
                        }
                        hideOverlayLifePdf();
                        if (typeof lpfUpdatePageNumbers === 'function') lpfUpdatePageNumbers();
                    });
                } else if (loadMode === 'prefill') {
                    prefillIncidentDataForLife(loadId, function() {
                        // sync เฉพาะเมื่อฟอร์มมาตรฐานมีอยู่ใน DOM
                        if (document.getElementById('incidentCheckListFormLife')) {
                            syncLifeFormData('incidentCheckListFormLife', 'lifeFormPdf');
                            _syncLifeInspectorToPdf();
                            _syncLifeVictimToPdf();
                        }
                        if (typeof lpfRenderPhotosFromStore === 'function') lpfRenderPhotosFromStore();
                        hideOverlayLifePdf();
                        if (typeof lpfUpdatePageNumbers === 'function') lpfUpdatePageNumbers();
                    });
                }
            }
        });

        // ========================================================================
        // Initialize Modal Life (ชีวิต)
        // ========================================================================

        // Init Select2 สำหรับ modal life (ทำครั้งเดียวพอ)
        if (!$('.user-select-box-life').hasClass('select2-hidden-accessible')) {
            $('.user-select-box-life').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: "กรุณาเลือก",
                allowClear: true,
                dropdownParent: $('#addCheckListModalLife'),
                dropdownAutoWidth: true
            });
        }

        // Auto Fill Position สำหรับ modal life
        $('.user-select-box-life').on('change', function() {
            var idEmp = $(this).val();
            var targetInput = $(this).data('pos-target');

            if (!idEmp) {
                $(targetInput).val('');
                return;
            }

            $.ajax({
                url: "/csims/api/ReceiveNoti/getPos.php",
                type: 'GET',
                data: {
                    idEmp: idEmp
                },
                dataType: 'json',
                success: function(response) {
                    if (response.message == "success" && response.data) {
                        $(targetInput).val(response.data.position_name);
                    } else {
                        $(targetInput).val('');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error fetching position:", error);
                    $(targetInput).val('');
                }
            });
        });

        // Initialize Signature Pads เมื่อเปิด modal life
        $('#addCheckListModalLife').on('shown.bs.modal', function() {
            // ตั้งค่าวันเวลาปัจจุบัน
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const currentDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;

            // ตั้งค่าวันเวลาสำหรับ fieldset ต่างๆ
            $('#collection_datetime_life').val(currentDateTime);
            $('#measurement_inspection_date_life').val(currentDateTime);
            $('#measurement_datetime_life').val(currentDateTime);

            // Initialize phone mask
            $('input[name="investigator_phone"]').mask('000-000-0000', {
                onKeyPress: function(val, e, field, options) {
                    var mask = val.startsWith('02') ? '00-000-0000' : '000-000-0000';
                    $('input[name="investigator_phone"]').mask(mask, options);
                }
            });

            // Initialize Select2 สำหรับ police_station_life
            if (!$('#police_station_life').hasClass('select2-hidden-accessible')) {
                $('#police_station_life').select2({
                    theme: 'bootstrap-5',
                    placeholder: "กรุณาเลือก",
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#addCheckListModalLife'),
                    dropdownAutoWidth: false
                });
            }

            // Initialize Signature Pads
            const canvasesLife = document.querySelectorAll('#addCheckListModalLife .signature-pad');

            canvasesLife.forEach((canvas) => {
                const canvasId = canvas.id;

                // ฟังก์ชันปรับขนาด Canvas ให้พอดีกับกล่อง (สำคัญมาก)
                function resizeCanvas() {
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);

                    // เช็คว่ามีความกว้างจริงไหม (กันพลาด)
                    if (canvas.offsetWidth === 0) return;

                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext('2d').scale(ratio, ratio);
                }

                // ถ้าเคยประกาศไปแล้ว ให้แค่ Resize ก็พอ เพื่อไม่ให้ทับซ้อน
                if (signaturePads[canvasId]) {
                    // Resize เพื่อให้เส้นไม่เบี้ยวเวลากลับมาเปิดใหม่
                    resizeCanvas();
                    return;
                }

                // เรียก Resize ครั้งแรก
                resizeCanvas();

                // Init SignaturePad - ตั้งค่าสีตาม canvas
                let penColor = 'rgb(0, 0, 0)'; // สีดำ (default)

                // ถ้าเป็น body diagram ให้ใช้สีแดง
                if (canvasId === 'body_diagram_canvas_life') {
                    penColor = 'rgb(255, 0, 0)'; // สีแดง
                }

                const pad = new SignaturePad(canvas, {
                    backgroundColor: 'rgba(255, 255, 255, 0)',
                    penColor: penColor,
                    minWidth: 1,
                    maxWidth: 3
                });

                signaturePads[canvasId] = pad;

                // Resize เมื่อ window เปลี่ยนขนาด
                window.addEventListener('resize', resizeCanvas);
            });
        });

        // ========================================================================

        // ========================================================================
        // Initialize Modal Bomb (ระเบิด)
        // ========================================================================

        // Init Select2 สำหรับ modal bomb
        if (!$('.user-select-box-bomb').hasClass('select2-hidden-accessible')) {
            $('.user-select-box-bomb').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: "กรุณาเลือก",
                allowClear: true,
                dropdownParent: $('#addCheckListModalBomb'),
                dropdownAutoWidth: true
            });
        }

        // Auto Fill Position สำหรับ modal bomb
        $('.user-select-box-bomb').on('change', function() {
            var idEmp = $(this).val();
            var targetInput = $(this).data('pos-target');

            if (!idEmp) {
                $(targetInput).val('');
                return;
            }

            $.ajax({
                url: "/csims/api/ReceiveNoti/getPos.php",
                type: 'GET',
                data: {
                    idEmp: idEmp
                },
                dataType: 'json',
                success: function(response) {
                    if (response.message == "success" && response.data) {
                        $(targetInput).val(response.data.position_name);
                    } else {
                        $(targetInput).val('');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error fetching position:", error);
                    $(targetInput).val('');
                }
            });
        });

        // Initialize Signature Pads เมื่อเปิด modalbomb
        $('#addCheckListModalBomb').on('shown.bs.modal', function() {
            // ตั้งค่าวันเวลาปัจจุบัน
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const currentDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;

            // ตั้งค่าวันเวลาสำหรับ fieldset ต่างๆ
            $('#collection_datetime_bomb').val(currentDateTime);
            $('#measurement_inspection_date_bomb').val(currentDateTime);
            $('#measurement_datetime_bomb').val(currentDateTime);

            // Initialize phone mask
            $('input[name="investigator_phone"]').mask('000-000-0000', {
                onKeyPress: function(val, e, field, options) {
                    var mask = val.startsWith('02') ? '00-000-0000' : '000-000-0000';
                    $('input[name="investigator_phone"]').mask(mask, options);
                }
            });

            // Initialize Select2 สำหรับ police_station_bomb
            if (!$('#police_station_bomb').hasClass('select2-hidden-accessible')) {
                $('#police_station_bomb').select2({
                    theme: 'bootstrap-5',
                    placeholder: "กรุณาเลือก",
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#addCheckListModalBomb'),
                    dropdownAutoWidth: false
                });
            }

            // Initialize Signature Pads
            const canvasesBomb = document.querySelectorAll('#addCheckListModalBomb .signature-pad');

            canvasesBomb.forEach((canvas) => {
                const canvasId = canvas.id;

                // ฟังก์ชันปรับขนาด Canvas ให้พอดีกับกล่อง (สำคัญมาก)
                function resizeCanvas() {
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);

                    // เช็คว่ามีความกว้างจริงไหม (กันพลาด)
                    if (canvas.offsetWidth === 0) return;

                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext('2d').scale(ratio, ratio);
                }

                // ถ้าเคยประกาศไปแล้ว ให้แค่ Resize ก็พอ เพื่อไม่ให้ทับซ้อน
                if (signaturePads[canvasId]) {
                    // Resize เพื่อให้เส้นไม่เบี้ยวเวลากลับมาเปิดใหม่
                    resizeCanvas();
                    return;
                }

                // เรียก Resize ครั้งแรก
                resizeCanvas();

                // Init SignaturePad - ตั้งค่าสีตาม canvas
                let penColor = 'rgb(0, 0, 0)'; // สีดำ (default)

                // ถ้าเป็น body diagram ให้ใช้สีแดง
                if (canvasId === 'body_diagram_canvas_bomb') {
                    penColor = 'rgb(255, 0, 0)'; // สีแดง
                }

                const pad = new SignaturePad(canvas, {
                    backgroundColor: 'rgba(255, 255, 255, 0)',
                    penColor: penColor,
                    minWidth: 1,
                    maxWidth: 3
                });

                signaturePads[canvasId] = pad;

                // Resize เมื่อ window เปลี่ยนขนาด
                window.addEventListener('resize', resizeCanvas);
            });

            // โหลดข้อมูล bomb หลัง modal shown (เหมือน life modal)
            if (pendingBombLoadId) {
                const loadId = pendingBombLoadId;
                const loadMode = pendingBombLoadMode;
                pendingBombLoadId = null;
                pendingBombLoadMode = null;

                if (loadMode === 'edit' && typeof loadBombDataToModal === 'function') {
                    loadBombDataToModal(loadId, {
                        showStandardModal: false,
                        onLoaded: function(d) {
                            console.log('Bomb data loaded successfully:', d);
                        }
                    });
                } else if (loadMode === 'prefill') {
                    prefillIncidentDataForBomb(loadId);
                }
            }
        });

        // ========================================================================
        // Bomb PDF Form Modal Initialization
        // ========================================================================
        $('#bombFormPdfModal').on('shown.bs.modal', function() {
            // ★ Init/Resize SignaturePad สำหรับ canvas ใน PDF form (ทำทุกครั้งที่ modal เปิด — เหมือน life form)
            ['bpf_sig_receiver', 'bpf_sig_sender', 'bpf_scene_sketch_canvas', 'bpf_body_diagram_canvas'].forEach(function(canvasId) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;

                // บันทึกข้อมูลเดิมก่อน resize
                var existingData = null;
                if (signaturePads[canvasId] && !signaturePads[canvasId].isEmpty()) {
                    existingData = signaturePads[canvasId].toDataURL();
                }

                // ★ HiDPI: ตั้ง canvas buffer ใหญ่กว่า CSS + scale context
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                if (canvas.offsetWidth > 0) {
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext('2d').scale(ratio, ratio);
                }

                if (signaturePads[canvasId]) {
                    if (existingData) {
                        signaturePads[canvasId].fromDataURL(existingData);
                    }
                    return;
                }

                // Init ครั้งแรก — เส้นบาง sig, ปกติ sketch, แดง body diagram
                var isSig = (canvasId === 'bpf_sig_receiver' || canvasId === 'bpf_sig_sender');
                var isBody = (canvasId === 'bpf_body_diagram_canvas');
                const pad = new SignaturePad(canvas, {
                    backgroundColor: 'rgba(255, 255, 255, 0)',
                    penColor: isBody ? 'rgb(204, 0, 0)' : 'rgb(0, 0, 0)',
                    minWidth: isSig ? 0.3 : 0.5,
                    maxWidth: isSig ? 1.2 : 2.5,
                });
                signaturePads[canvasId] = pad;
            });

            // เปิด modal แบบ edit/prefill ให้เคลียร์ผิววาดเดิมก่อน เพื่อกันภาพค้างจากเคสก่อนหน้า
            if (pendingBombLoadId) {
                ['bpf_sig_receiver', 'bpf_sig_sender', 'bpf_scene_sketch_canvas', 'bpf_body_diagram_canvas'].forEach(function(canvasId) {
                    const pad = signaturePads[canvasId];
                    if (pad) {
                        pad.clear();
                    } else {
                        const canvas = document.getElementById(canvasId);
                        if (canvas) {
                            const ctx = canvas.getContext('2d');
                            ctx.clearRect(0, 0, canvas.width, canvas.height);
                        }
                    }
                });
            }

            // Initialize canvas/page numbers สำหรับ PDF form
            if (typeof bpfBombUpdatePageNumbers === 'function') bpfBombUpdatePageNumbers();

            // โหลดข้อมูล bomb หลัง PDF modal shown
            if (pendingBombLoadId) {
                const loadId = pendingBombLoadId;
                const loadMode = pendingBombLoadMode;
                pendingBombLoadId = null;
                pendingBombLoadMode = null;

                // แสดง loading overlay (เหมือน life form)
                var $overlayBombPdf = $('#bombPdfLoadingOverlay');
                $overlayBombPdf.removeClass('d-none').css('display', 'flex');
                var $bombPdfModalBody = $overlayBombPdf.closest('.modal-body');
                if (typeof lockModalBodyScroll === 'function') lockModalBodyScroll($bombPdfModalBody);
                var overlayBombPdfStart = Date.now();
                var ANIM_MS_BOMB_PDF = 2000;

                function hideOverlayBombPdf() {
                    var elapsed = Date.now() - overlayBombPdfStart;
                    var remaining = ANIM_MS_BOMB_PDF - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() {
                            $overlayBombPdf.addClass('d-none');
                            if (typeof unlockModalBodyScroll === 'function') unlockModalBodyScroll($bombPdfModalBody);
                        }, remaining);
                    } else {
                        $overlayBombPdf.addClass('d-none');
                        if (typeof unlockModalBodyScroll === 'function') unlockModalBodyScroll($bombPdfModalBody);
                    }
                }

                if (loadMode === 'edit' && typeof loadBombDataToModal === 'function') {
                    loadBombDataToModal(loadId, {
                        showStandardModal: false,
                        onLoaded: function(d) {
                            console.log('Bomb PDF data loaded successfully:', d);
                            // sync ข้อมูลจาก standard → PDF form หลังโหลดเสร็จ
                            syncBombFormData('incidentCheckListFormBomb', 'bombFormPdf');
                            // sync dynamic rows
                            if (typeof _syncBombInspectorToPdf === 'function') _syncBombInspectorToPdf();
                            if (typeof _syncBombVictimToPdf === 'function') _syncBombVictimToPdf();
                            if (typeof _syncBombBodyToPdf === 'function') _syncBombBodyToPdf();
                            if (typeof _syncBombEvidenceToPdf === 'function') _syncBombEvidenceToPdf();
                            if (typeof _syncBombMeasurementToPdf === 'function') _syncBombMeasurementToPdf();

                            // ★ Ensure case_doc is preserved after sync (fallback to docNo from header)
                            var _bombDocNo = $('#case_doc_no_bomb').val() || $('#doc_no_bomb').val() || '';
                            if (_bombDocNo) {
                                var _bombDocNoTH = thaiDocNo(_bombDocNo);
                                $('#bpf_case_doc_no').val(_bombDocNoTH);
                                $('#case_doc_no_bomb').val(_bombDocNoTH);
                            }

                            // ★ Re-populate report number mirrors on all pages (shown.bs.modal fires before AJAX loads data)
                            if (typeof bpfBombUpdateReportMirrors === 'function') bpfBombUpdateReportMirrors();

                            // ★ โหลด signatures ลง PDF canvas โดยตรง (เพราะ standard modal ไม่แสดง → shown.bs.modal ไม่ fire)
                            if (d && d.signatures) {
                                var bpfSigMap = [{
                                        key: 'scene_sketch',
                                        canvasId: 'bpf_scene_sketch_canvas'
                                    },
                                    {
                                        key: 'body_diagram',
                                        canvasId: 'bpf_body_diagram_canvas'
                                    },
                                    {
                                        key: 'receiver_sig',
                                        canvasId: 'bpf_sig_receiver'
                                    },
                                    {
                                        key: 'sender_sig',
                                        canvasId: 'bpf_sig_sender'
                                    }
                                ];
                                bpfSigMap.forEach(function(item) {
                                    var sigData = d.signatures[item.key];
                                    if (!sigData) return;
                                    var canvas = document.getElementById(item.canvasId);
                                    var pad = signaturePads[item.canvasId];
                                    if (!canvas) return;

                                    if (sigData.file_id) {
                                        var imgUrl = './api/incidentCheckList/getFile.php?id=' + sigData.file_id;
                                        var img = new Image();
                                        img.crossOrigin = 'anonymous';
                                        img.onload = function() {
                                            var cssW = canvas.offsetWidth || canvas.clientWidth || 300;
                                            var cssH = canvas.offsetHeight || canvas.clientHeight || 150;
                                            if (pad) {
                                                // วาดผ่าน context ตรงแทน fromDataURL เพื่อรองรับ BLOB
                                                var ctx = canvas.getContext('2d');
                                                ctx.drawImage(img, 0, 0, cssW, cssH);
                                                // อัปเดต SignaturePad internal state
                                                pad._data = [{
                                                    points: [{
                                                        x: 0,
                                                        y: 0
                                                    }]
                                                }];
                                            } else {
                                                var ctx2 = canvas.getContext('2d');
                                                ctx2.drawImage(img, 0, 0, cssW, cssH);
                                            }
                                        };
                                        img.src = imgUrl;
                                    } else if (sigData.base64) {
                                        if (pad) {
                                            var cssW = canvas.offsetWidth || canvas.clientWidth || 300;
                                            var cssH = canvas.offsetHeight || canvas.clientHeight || 150;
                                            pad.fromDataURL(sigData.base64, {
                                                ratio: 1,
                                                width: cssW,
                                                height: cssH
                                            });
                                        }
                                    }
                                });
                            }

                            // ★ โหลด photos ลง attachmentStoreBomb โดยตรง (เพราะ standard modal shown.bs.modal ไม่ fire)
                            if (d && d.photos && d.photos.length > 0) {
                                if (typeof window.attachmentStoreBomb === 'undefined') window.attachmentStoreBomb = [];
                                window.attachmentStoreBomb.length = 0;
                                if (typeof window.deletedExistingPhotosBomb === 'undefined') window.deletedExistingPhotosBomb = [];
                                window.deletedExistingPhotosBomb.length = 0;
                                d.photos.forEach(function(p, idx) {
                                    if (p.file_id) {
                                        attachmentStoreBomb.push({
                                            id: 'existing_' + p.file_id,
                                            src: './api/incidentCheckList/getFile.php?id=' + p.file_id,
                                            existing: true,
                                            isExisting: true,
                                            db_file_id: p.file_id,
                                            filename: p.filename || p.name || 'photo_' + (idx + 1) + '.jpg',
                                            name: p.filename || p.name || 'photo_' + (idx + 1) + '.jpg',
                                            caption: p.caption || ''
                                        });
                                    } else if (p.base64) {
                                        attachmentStoreBomb.push({
                                            id: 'existing_legacy_' + idx,
                                            src: p.base64,
                                            existing: true,
                                            isExisting: true,
                                            name: p.filename || p.name || 'photo_' + (idx + 1) + '.jpg',
                                            filename: p.filename || 'photo',
                                            disk_filename: p.filename || 'photo',
                                            size: p.size || 'N/A',
                                            date: p.date || '-'
                                        });
                                    }
                                });
                            }

                            // render photos
                            if (typeof bpfRenderPhotosFromStore === 'function') bpfRenderPhotosFromStore();
                            hideOverlayBombPdf();
                        }
                    });
                } else if (loadMode === 'prefill') {
                    // ★ Prefill: สร้าง default rows + sync ไปยัง PDF form
                    if (typeof loadBombDataToModal === 'function') {
                        loadBombDataToModal(loadId, {
                            showStandardModal: false,
                            onLoaded: function(d) {
                                prefillIncidentDataForBomb(loadId, function() {
                                    // sync ข้อมูลจาก standard → PDF form (รวม measurement_inspection_date)
                                    if (typeof syncBombFormData === 'function') syncBombFormData('incidentCheckListFormBomb', 'bombFormPdf');
                                    if (typeof _syncBombInspectorToPdf === 'function') _syncBombInspectorToPdf();
                                    if (typeof _syncBombVictimToPdf === 'function') _syncBombVictimToPdf();
                                    if (typeof _syncBombBodyToPdf === 'function') _syncBombBodyToPdf();
                                    if (typeof _syncBombEvidenceToPdf === 'function') _syncBombEvidenceToPdf();
                                    if (typeof _syncBombMeasurementToPdf === 'function') _syncBombMeasurementToPdf();
                                    // ★ Ensure case_doc is preserved after sync
                                    var _bombDocNo2 = $('#case_doc_no_bomb').val() || $('#doc_no_bomb').val() || '';
                                    if (_bombDocNo2) {
                                        var _bombDocNo2TH = thaiDocNo(_bombDocNo2);
                                        $('#bpf_case_doc_no').val(_bombDocNo2TH);
                                        $('#case_doc_no_bomb').val(_bombDocNo2TH);
                                    }
                                    // ★ Re-populate report number mirrors on all pages
                                    if (typeof bpfBombUpdateReportMirrors === 'function') bpfBombUpdateReportMirrors();
                                    hideOverlayBombPdf();
                                });
                            }
                        });
                    } else {
                        hideOverlayBombPdf();
                    }
                } else {
                    // กรณี fallback — ไม่มีฟังก์ชัน loadBombDataToModal หรือ mode ไม่ตรง
                    hideOverlayBombPdf();
                }
            }
        });

        // ========================================================================
        // Fire Modal Initialization
        // ========================================================================

        // Initialize Fire Modal SignaturePad
        const canvasesFire = document.querySelectorAll('#addCheckListModalFire .signature-pad');
        canvasesFire.forEach((canvas) => {
            const canvasId = canvas.id;

            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                if (canvas.offsetWidth === 0) return;
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);
            }
            if (signaturePads[canvasId]) {
                resizeCanvas();
                return;
            }
            resizeCanvas();
            const pad = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)',
                penColor: 'rgb(0, 0, 0)',
                minWidth: 1,
                maxWidth: 3
            });
            signaturePads[canvasId] = pad;
            window.addEventListener('resize', resizeCanvas);
        });

        // Fire Inspector - เพิ่มผู้ตรวจ
        let fireInspectorCount = 1;
        $('#btn_add_inspector_fire').on('click', function() {
            fireInspectorCount++;
            const newRow = `
                <div class="d-flex align-items-center mb-3 inspector-row-fire">
                    <div class="text-end pe-3" style="width: 50px;">
                        <span class="fw-bold text-secondary index-label">5.${fireInspectorCount}</span>
                    </div>
                    <div class="flex-grow-1">
                        <select class="form-select inspector-select-fire" name="fire_inspector_id[]">
                            <?php echo $inspectorOptionsFire; ?>
                        </select>
                    </div>
                    <div class="ms-2" style="width: 32px;">
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-0" 
                            style="width:28px;height:28px;" title="ลบ" onclick="removeFireInspectorRow(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>`;
            $('#inspector_container_fire').append(newRow);
            const $newSelect = $('#inspector_container_fire .inspector-select-fire').last();
            $newSelect.select2({
                dropdownParent: $('#addCheckListModalFire'),
                width: '100%'
            });
        });

        // Fire Photo Upload
        const inputPhotosFire = $('#incident_photos_fire');
        const cameraInputFire = $('#camera_input_fire');
        const MAX_FILE_SIZE_MB_FIRE = 10;

        // ปุ่มเลือกไฟล์
        $('#btn_choose_file_fire').on('click', function() {
            inputPhotosFire.trigger('click');
        });

        // ปุ่มเปิดกล้อง
        $('#btn_open_camera_fire').on('click', function() {
            cameraInputFire.trigger('click');
        });

        inputPhotosFire.on('change', function(e) {
            handleFilesFire(e.target.files);
            $(this).val('');
        });

        cameraInputFire.on('change', function(e) {
            handleFilesFire(e.target.files);
            $(this).val('');
        });

        // Dropzone drag & drop for fire photos
        (function() {
            var dz = document.getElementById('dropzone_fire');
            if (!dz) return;
            dz.addEventListener('click', function() {
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
                        inputPhotosFire.trigger('click');
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        cameraInputFire.trigger('click');
                    }
                });
            });
            dz.addEventListener('dragover', function(e) {
                e.preventDefault();
                dz.style.borderColor = '#2196F3';
                dz.style.background = '#e3f2fd';
            });
            dz.addEventListener('dragleave', function() {
                dz.style.borderColor = '#b0bec5';
                dz.style.background = '#f8f9fa';
            });
            dz.addEventListener('drop', function(e) {
                e.preventDefault();
                dz.style.borderColor = '#b0bec5';
                dz.style.background = '#f8f9fa';
                if (e.dataTransfer.files && e.dataTransfer.files.length) {
                    handleFilesFire(e.dataTransfer.files);
                }
            });
        })();

        function handleFilesFire(files) {
            if (files.length === 0) return;
            Array.from(files).forEach(file => {
                if (file.size > MAX_FILE_SIZE_MB_FIRE * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ไฟล์ขนาดใหญ่เกินไป',
                        text: 'ไฟล์ ' + file.name + ' มีขนาดเกิน ' + MAX_FILE_SIZE_MB_FIRE + 'MB',
                        timer: 3000
                    });
                    return;
                }
                const fileId = Date.now() + Math.random().toString(16).slice(2);
                const uploadingHtml = '<div id="uploading_fire_' + fileId + '" class="d-flex align-items-center mb-2 p-2 border rounded"><i class="fas fa-spinner fa-spin me-2 text-primary"></i><span class="small">' + file.name + '</span></div>';
                $('#uploading_container_fire').append(uploadingHtml);
                completeUploadFire(file, fileId);
            });
        }

        function completeUploadFire(file, fileId) {
            setTimeout(function() {
                $('#uploading_fire_' + fileId).fadeOut(300, function() {
                    $(this).remove();
                });
                const objectUrl = URL.createObjectURL(file);
                attachmentStoreFire.push({
                    file: file,
                    id: fileId,
                    src: objectUrl,
                    name: file.name
                });
                updateFireRealInput();
                renderFireAttachmentGrid();
            }, 500);
        }

        // =============================================================
        //  Fingerprint Photo Upload Handlers
        // =============================================================
        const inputPhotosFPN = $('#incident_photos_fpn');
        const cameraInputFPN = $('#camera_input_fpn');
        const inputPhotosFP = $('#incident_photos_fp');
        const cameraInputFP = $('#camera_input_fp');
        const MAX_FILE_SIZE_MB_FP = 10;

        // Standard form buttons
        $('#btn_choose_file_fpn').on('click', function() {
            inputPhotosFPN.trigger('click');
        });
        $('#btn_open_camera_fpn').on('click', function() {
            cameraInputFPN.trigger('click');
        });

        // ★ Drag & Drop zone (ฟอร์มปกติ fingerprint)
        const fpnDropzone = document.getElementById('fpn_photo_dropzone');
        if (fpnDropzone) {
            fpnDropzone.addEventListener('click', function() {
                inputPhotosFPN.trigger('click');
            });
            ['dragenter', 'dragover'].forEach(function(ev) {
                fpnDropzone.addEventListener(ev, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    fpnDropzone.style.borderColor = '#2196F3';
                    fpnDropzone.style.background = '#e3f2fd';
                });
            });
            ['dragleave', 'drop'].forEach(function(ev) {
                fpnDropzone.addEventListener(ev, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    fpnDropzone.style.borderColor = '#b0bec5';
                    fpnDropzone.style.background = '#f8f9fa';
                });
            });
            fpnDropzone.addEventListener('drop', function(e) {
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                    handleFilesFP(e.dataTransfer.files);
                }
            });
        }

        // PDF form buttons
        $('#btn_choose_file_fp').on('click', function() {
            inputPhotosFP.trigger('click');
        });
        $('#btn_open_camera_fp').on('click', function() {
            cameraInputFP.trigger('click');
        });

        inputPhotosFPN.on('change', function(e) {
            handleFilesFP(e.target.files);
            $(this).val('');
        });
        cameraInputFPN.on('change', function(e) {
            handleFilesFP(e.target.files);
            $(this).val('');
        });
        inputPhotosFP.on('change', function(e) {
            handleFilesFP(e.target.files);
            $(this).val('');
        });
        cameraInputFP.on('change', function(e) {
            handleFilesFP(e.target.files);
            $(this).val('');
        });

        function handleFilesFP(files) {
            if (files.length === 0) return;
            Array.from(files).forEach(function(file) {
                if (file.size > MAX_FILE_SIZE_MB_FP * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ไฟล์ขนาดใหญ่เกินไป',
                        text: 'ไฟล์ ' + file.name + ' มีขนาดเกิน ' + MAX_FILE_SIZE_MB_FP + 'MB',
                        timer: 3000
                    });
                    return;
                }
                var fileId = Date.now() + Math.random().toString(16).slice(2);
                var uploadingHtml = '<div id="uploading_fp_' + fileId + '" class="d-flex align-items-center mb-2 p-2 border rounded"><i class="fas fa-spinner fa-spin me-2 text-primary"></i><span class="small">' + file.name + '</span></div>';
                $('#uploading_container_fpn, #uploading_container_fp').append(uploadingHtml);
                completeUploadFP(file, fileId);
            });
        }

        function completeUploadFP(file, fileId) {
            setTimeout(function() {
                $('#uploading_fp_' + fileId).fadeOut(300, function() {
                    $(this).remove();
                });
                var objectUrl = URL.createObjectURL(file);
                attachmentStoreFP.push({
                    file: file,
                    id: fileId,
                    src: objectUrl,
                    name: file.name
                });
                updateFPRealInput();
                renderFPAttachmentGrid();
            }, 500);
        }

        // Fire Form Save Handler
        $('#btn_save_fire').on('click', function() {
            prepareDataForSubmissionFire();
        });

        // Initialize Select2 for Fire inspectors
        $('#addCheckListModalFire').on('shown.bs.modal', function() {
            $('.inspector-select-fire').each(function() {
                if (!$(this).data('select2')) {
                    $(this).select2({
                        dropdownParent: $('#addCheckListModalFire'),
                        width: '100%'
                    });
                }
            });

            // Initialize Select2 for user dropdowns (ผู้บันทึก, ผู้ถ่ายภาพ, พนักงานสอบสวน)
            $('.user-select-box-fire').each(function() {
                if (!$(this).data('select2')) {
                    $(this).select2({
                        dropdownParent: $('#addCheckListModalFire'),
                        width: '100%',
                        placeholder: '-- เลือก --',
                        allowClear: true
                    });
                }
            });

            // Auto Fill Position for fire selects with data-pos-target
            $('.user-select-box-fire').on('change', function() {
                var idEmp = $(this).val();
                var targetInput = $(this).data('pos-target');
                if (!targetInput) return;
                if (!idEmp) {
                    $(targetInput).val('');
                    return;
                }
                $.ajax({
                    url: "/csims/api/ReceiveNoti/getPos.php",
                    type: 'GET',
                    data: {
                        idEmp: idEmp
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.message == "success") {
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

            // Re-init SignaturePad on modal shown
            const fireCanvases = document.querySelectorAll('#addCheckListModalFire .signature-pad');
            fireCanvases.forEach((canvas) => {
                const canvasId = canvas.id;
                if (signaturePads[canvasId]) {
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    if (canvas.offsetWidth > 0) {
                        canvas.width = canvas.offsetWidth * ratio;
                        canvas.height = canvas.offsetHeight * ratio;
                        canvas.getContext('2d').scale(ratio, ratio);
                    }
                }
            });

            // Initialize Select2 for police station
            if (!$('#police_station_fire').data('select2')) {
                $('#police_station_fire').select2({
                    dropdownParent: $('#addCheckListModalFire'),
                    width: '100%'
                });
            }

            // วาดแผนผังที่เกิดเหตุใหม่หลัง canvas resize (กรณี AJAX เสร็จก่อน modal แสดง)
            var fireSketchVal = document.getElementById('scene_sketch_data_fire').value;
            if (fireSketchVal) {
                var sketchCanvas = document.getElementById('scene_sketch_canvas_fire');
                if (sketchCanvas && sketchCanvas.width > 0) {
                    var img = new Image();
                    img.onload = function() {
                        var ctx = sketchCanvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, sketchCanvas.width, sketchCanvas.height);
                    };
                    img.src = fireSketchVal;
                }
            }

            // โหลดข้อมูล fire หลัง modal shown เพื่อให้ Select2/Canvas พร้อมก่อน
            if (pendingFireLoadId) {
                const loadId = pendingFireLoadId;
                const loadMode = pendingFireLoadMode;
                pendingFireLoadId = null;
                pendingFireLoadMode = null;

                // แสดง loading overlay
                var $fireOverlay = $('#fireLoadingOverlay');
                $fireOverlay.removeClass('d-none').css('display', 'flex');
                var $fireModalBody = $fireOverlay.closest('.modal-body');
                lockModalBodyScroll($fireModalBody);
                var fireOverlayStart = Date.now();
                var FIRE_ANIM_MS = 2000;

                function hideFireOverlay() {
                    var elapsed = Date.now() - fireOverlayStart;
                    var remaining = FIRE_ANIM_MS - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() {
                            $fireOverlay.addClass('d-none');
                            unlockModalBodyScroll($fireModalBody);
                        }, remaining);
                    } else {
                        $fireOverlay.addClass('d-none');
                        unlockModalBodyScroll($fireModalBody);
                    }
                }

                if (loadMode === 'edit') {
                    loadFireDataToModal(loadId, function() {
                        hideFireOverlay();
                    });
                } else if (loadMode === 'prefill') {
                    prefillIncidentDataForFire(loadId, function() {
                        hideFireOverlay();
                    });
                }
            }
        });

        // ===== PDF form (fireFormPdfModal) เป็นหน้าต่างหลัก → โหลดข้อมูลหลัง shown =====
        $('#fireFormPdfModal').on('shown.bs.modal', function() {
            if (pendingFireLoadId) {
                const loadId = pendingFireLoadId;
                const loadMode = pendingFireLoadMode;
                pendingFireLoadId = null;
                pendingFireLoadMode = null;

                // แสดง loading overlay
                var $firePdfOverlay = $('#firePdfLoadingOverlay');
                $firePdfOverlay.removeClass('d-none').css('display', 'flex');
                var $firePdfModalBody = $firePdfOverlay.closest('.modal-body');
                lockModalBodyScroll($firePdfModalBody);
                var firePdfOverlayStart = Date.now();
                var FIRE_PDF_ANIM_MS = 2000;

                function hideFirePdfOverlay() {
                    var elapsed = Date.now() - firePdfOverlayStart;
                    var remaining = FIRE_PDF_ANIM_MS - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() {
                            $firePdfOverlay.addClass('d-none');
                            unlockModalBodyScroll($firePdfModalBody);
                        }, remaining);
                    } else {
                        $firePdfOverlay.addClass('d-none');
                        unlockModalBodyScroll($firePdfModalBody);
                    }
                }

                if (loadMode === 'edit') {
                    loadFireDataToModal(loadId, function() {
                        // sync dynamic rows + ข้อมูลจากฟอร์มมาตรฐาน → PDF form
                        syncAllDynamicRows('toPdf');
                        syncFireFormData('incidentCheckListFormFire', 'fireFormPdf');
                        _syncFireEvidenceToCollectionFallback();
                        // render รูปภาพลง PDF slots
                        if (typeof fpfRenderPhotosFromStore === 'function') fpfRenderPhotosFromStore();
                        if (typeof fpfFireUpdatePageNumbers === 'function') fpfFireUpdatePageNumbers();
                        // sync signatures จาก hidden input → วาดบน PDF canvas โดยตรง
                        var sigPairs = {
                            'scene_sketch_data_fire': 'fpf_scene_sketch_canvas',
                            'receiver_signature_data_fire': 'fpf_sig_receiver',
                            'sender_signature_data_fire': 'fpf_sig_sender'
                        };
                        Object.keys(sigPairs).forEach(function(hiddenId) {
                            var val = $('#' + hiddenId).val();
                            if (val && val.indexOf('existing_file_id:') === 0) {
                                var fileId = val.replace('existing_file_id:', '');
                                var imgUrl = './api/incidentCheckList/getFile.php?id=' + fileId;
                                var dst = document.getElementById(sigPairs[hiddenId]);
                                if (dst && dst.width > 0) {
                                    var img = new Image();
                                    img.crossOrigin = 'anonymous';
                                    img.onload = function() {
                                        var ctx = dst.getContext('2d');
                                        ctx.drawImage(img, 0, 0, dst.width, dst.height);
                                    };
                                    img.src = imgUrl;
                                }
                            }
                        });
                        hideFirePdfOverlay();
                    });
                } else if (loadMode === 'prefill') {
                    prefillIncidentDataForFire(loadId, function() {
                        hideFirePdfOverlay();
                    });
                }
            }
        });

        // ===== PDF form (trafficFormPdfModal) → โหลดข้อมูลจราจรหลัง shown =====
        $('#trafficFormPdfModal').on('shown.bs.modal', function() {
            if (pendingTrafficLoadId) {
                const loadId = pendingTrafficLoadId;
                const loadMode = pendingTrafficLoadMode;
                pendingTrafficLoadId = null;
                pendingTrafficLoadMode = null;

                // แสดง loading overlay
                var $tpfOverlay = $('#trafficPdfLoadingOverlay');
                $tpfOverlay.removeClass('d-none').css('display', 'flex');
                var $tpfModalBody = $tpfOverlay.closest('.modal-body');
                lockModalBodyScroll($tpfModalBody);
                var tpfOverlayStart = Date.now();
                var TPF_ANIM_MS = 2000;

                function hideTpfOverlay() {
                    var elapsed = Date.now() - tpfOverlayStart;
                    var remaining = TPF_ANIM_MS - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() {
                            $tpfOverlay.addClass('d-none');
                            unlockModalBodyScroll($tpfModalBody);
                        }, remaining);
                    } else {
                        $tpfOverlay.addClass('d-none');
                        unlockModalBodyScroll($tpfModalBody);
                    }
                }

                if (loadMode === 'edit') {
                    loadTrafficDataToModal(loadId, function() {
                        if (typeof tpfUpdatePageNumbers === 'function') tpfUpdatePageNumbers();
                        hideTpfOverlay();
                    });
                } else if (loadMode === 'prefill') {
                    if (typeof window.resetTrafficPhotos === 'function') window.resetTrafficPhotos();
                    prefillIncidentDataForTraffic(loadId, function() {
                        syncTrafficFormData('incidentCheckListFormTraffic', 'trafficFormPdf');
                        if (typeof syncTrafficDynamicRows === 'function') syncTrafficDynamicRows('toPdf');
                        if (typeof tpfUpdatePageNumbers === 'function') tpfUpdatePageNumbers();
                        hideTpfOverlay();
                    });
                } else {
                    if (typeof window.resetTrafficPhotos === 'function') window.resetTrafficPhotos();
                    hideTpfOverlay();
                }
            }
        });

        // ===== Standard form (addCheckListModalTraffic) → โหลดข้อมูลจราจรหลัง shown =====
        $('#addCheckListModalTraffic').on('shown.bs.modal', function() {
            if (pendingTrafficLoadId) {
                const loadId = pendingTrafficLoadId;
                const loadMode = pendingTrafficLoadMode;
                pendingTrafficLoadId = null;
                pendingTrafficLoadMode = null;

                var $tStdOverlay = $('#trafficStdLoadingOverlay');
                $tStdOverlay.removeClass('d-none').css('display', 'flex');
                var $tStdModalBody = $tStdOverlay.closest('.modal-body');
                lockModalBodyScroll($tStdModalBody);
                var tStdStart = Date.now();
                var T_STD_ANIM = 2000;

                function hideTStdOverlay() {
                    var elapsed = Date.now() - tStdStart;
                    var remaining = T_STD_ANIM - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() {
                            $tStdOverlay.addClass('d-none');
                            unlockModalBodyScroll($tStdModalBody);
                        }, remaining);
                    } else {
                        $tStdOverlay.addClass('d-none');
                        unlockModalBodyScroll($tStdModalBody);
                    }
                }

                if (loadMode === 'edit') {
                    loadTrafficDataToModal(loadId, function() {
                        hideTStdOverlay();
                    });
                } else if (loadMode === 'prefill') {
                    if (typeof window.resetTrafficPhotos === 'function') window.resetTrafficPhotos();
                    prefillIncidentDataForTraffic(loadId, function() {
                        hideTStdOverlay();
                    });
                } else {
                    if (typeof window.resetTrafficPhotos === 'function') window.resetTrafficPhotos();
                    hideTStdOverlay();
                }
            }
        });

        // ===== โหลดข้อมูลลายนิ้วมือแฝง (read / edit) =====
        function loadFingerprintDataToModal(incidentId, onComplete) {
            $.ajax({
                url: './api/incidentCheckList/getFingerprintData.php',
                method: 'GET',
                data: {
                    incident_id: incidentId
                },
                dataType: 'json',
                success: function(res) {
                    console.log('🔍 getFingerprintData response:', res);
                    if (res && res.success && res.data) {
                        const d = res.data;

                        // Set flags to skip scroll when loading data programmatically
                        window.fpnSkipScroll = true;
                        window.fpSkipScroll = true;

                        // --- Edit info ---
                        if (res.edit_info) {
                            const ei = res.edit_info;
                            $('#editCountFPN').text(ei.count_edit || 0);
                            $('#editCountFP').text(ei.count_edit || 0);
                            $('#editCountFPPdf').text(ei.count_edit || 0);
                            if (ei.edit_date) {
                                const ed = new Date(ei.edit_date);
                                const dd = String(ed.getDate()).padStart(2, '0');
                                const mm = String(ed.getMonth() + 1).padStart(2, '0');
                                const yyyy = ed.getFullYear() + 543;
                                const hh = String(ed.getHours()).padStart(2, '0');
                                const mi = String(ed.getMinutes()).padStart(2, '0');
                                const editDateStr = dd + '/' + mm + '/' + yyyy + ' ' + hh + ':' + mi + ' น.';
                                $('#editDateFPN').text(editDateStr);
                                $('#editDateFP').text(editDateStr);
                            } else {
                                $('#editDateFPN').text('-');
                                $('#editDateFP').text('-');
                            }
                            $('#editInfoFPN').removeClass('d-none');
                            $('#editInfoFP').removeClass('d-none');
                            $('#editInfoFPPdf').removeClass('d-none');
                        }

                        // --- General Info ---
                        if (d.general_info) {
                            const g = d.general_info;
                            if (g.doc_no) {
                                $('#doc_no_fpn').val(g.doc_no);
                                $('#receiveNoti_No_fpn').text(g.doc_no);
                            }
                            if (g.report_no) {
                                $('#report_no_fpn').val(g.report_no);
                                $('#receiveNotiReportNo_fpn').text(g.report_no);
                            }
                            if (g.receive_date) {
                                $('#fpn_receive_date').val(g.receive_date);
                                $('#fp_receive_date').val(g.receive_date);
                            }
                            if (g.receive_time) {
                                $('#fpn_receive_time').val(g.receive_time);
                                $('#fp_receive_time').val(g.receive_time);
                            }
                            if (g.case_no) {
                                $('#fpn_case_no').val(g.case_no);
                                $('#fp_case_no').val(g.case_no);
                            }
                            if (g.police_station) {
                                $('#fpn_police_station').val(g.police_station);
                                $('#fp_police_station').val(g.police_station);
                            }
                            if (g.letter_no) {
                                $('#fpn_letter_no').val(g.letter_no);
                                $('#fp_letter_no').val(g.letter_no);
                            } {
                                var _ld = g.letter_date || new Date().toISOString().slice(0, 10);
                                $('#fpn_letter_date').val(_ld);
                                $('#fp_letter_date').val(_ld);
                            }
                            if (g.evidence_sender) {
                                $('#fpn_evidence_sender').val(g.evidence_sender);
                                $('#fp_evidence_sender').val(g.evidence_sender);
                            }
                            if (g.evidence_sender_position) {
                                $('#fpn_evidence_sender_position').val(g.evidence_sender_position);
                                $('#fp_evidence_sender_position').val(g.evidence_sender_position);
                            }
                            if (g.evidence_sender_phone) {
                                $('#fpn_evidence_sender_phone').val(g.evidence_sender_phone);
                                $('#fp_evidence_sender_phone').val(g.evidence_sender_phone);
                            }
                            if (g.evidence_letter_no) {
                                $('#fpn_evidence_letter_no').val(g.evidence_letter_no);
                                $('#fp_evidence_letter_no').val(g.evidence_letter_no);
                            }
                            if (g.evidence_doc_no) {
                                $('#fpn_evidence_doc_no').val(g.evidence_doc_no);
                                $('#fp_evidence_doc_no').val(g.evidence_doc_no);
                            } {
                                var _edd = g.evidence_doc_date || new Date().toISOString().slice(0, 10);
                                $('#fpn_evidence_doc_date').val(_edd);
                                $('#fp_evidence_doc_date').val(_edd);
                            }
                            // Purposes checkboxes
                            if (g.purposes && Array.isArray(g.purposes)) {
                                g.purposes.forEach(function(val) {
                                    $('input[name="fpn_purpose[]"][value="' + val + '"]').prop('checked', true);
                                    $('input[name="fp_purpose[]"][value="' + val + '"]').prop('checked', true);
                                    if (val === 'อื่นๆ') {
                                        $('#fpn_purpose_other_text').show().prop('disabled', false);
                                        $('#fp_purpose_other_text').show().prop('disabled', false);
                                    }
                                });
                            }
                            if (g.purpose_other_text) {
                                $('#fpn_purpose_other_text').val(g.purpose_other_text).show().prop('disabled', false);
                                $('#fp_purpose_other_text').val(g.purpose_other_text).show().prop('disabled', false);
                            }
                            if (g.incident_date) {
                                $('#fpn_incident_date').val(g.incident_date);
                                $('#fp_incident_date').val(g.incident_date);
                            }
                            if (g.incident_time) {
                                $('#fpn_incident_time').val(g.incident_time);
                                $('#fp_incident_time').val(g.incident_time);
                            }
                            if (g.known_date) {
                                $('#fpn_known_date').val(g.known_date);
                                $('#fp_known_date').val(g.known_date);
                            }
                            if (g.known_time) {
                                $('#fpn_known_time').val(g.known_time);
                                $('#fp_known_time').val(g.known_time);
                            }
                            if (g.collect_date) {
                                $('#fpn_collect_date').val(g.collect_date);
                                $('#fp_collect_date').val(g.collect_date);
                            }
                            if (g.collect_time) {
                                $('#fpn_collect_time').val(g.collect_time);
                                $('#fp_collect_time').val(g.collect_time);
                            }
                        }

                        // --- Inspectors (new modal dynamic rows) ---
                        if (d.inspectors && d.inspectors.length > 0) {
                            d.inspectors.forEach(function(inspId, idx) {
                                if (idx > 0) {
                                    $('#btn_add_inspector_fpn').trigger('click');
                                }
                                var rows = $('#fpn_inspector_container .fpn-inspector-select');
                                rows.eq(idx).val(inspId).trigger('change');
                            });
                            // PDF modal inspectors
                            d.inspectors.forEach(function(inspId, idx) {
                                if (idx > 0) {
                                    $('#btn_add_inspector_fp').trigger('click');
                                }
                                var rows = $('#fp_inspector_container .fp-inspector-select');
                                rows.eq(idx).val(inspId).trigger('change');
                            });
                        }

                        // --- Package Storage ---
                        if (d.package_storage) {
                            const ps = d.package_storage;
                            if (ps.package_types && Array.isArray(ps.package_types)) {
                                ps.package_types.forEach(function(val) {
                                    $('input[name="fpn_package_type[]"][value="' + val + '"]').prop('checked', true);
                                    $('input[name="fp_package_type[]"][value="' + val + '"]').prop('checked', true);
                                });
                            }
                            if (ps.seal_conditions && Array.isArray(ps.seal_conditions)) {
                                ps.seal_conditions.forEach(function(val) {
                                    $('input[name="fpn_seal_condition[]"][value="' + val + '"]').prop('checked', true);
                                    $('input[name="fp_seal_condition[]"][value="' + val + '"]').prop('checked', true);
                                });
                            }
                            if (ps.collector_type) {
                                $('input[name="fpn_collector_type"][value="' + ps.collector_type + '"]').prop('checked', true);
                                $('input[name="fp_collector_type"][value="' + ps.collector_type + '"]').prop('checked', true);
                                if (ps.collector_type === 'อื่นๆ') {
                                    $('#fpn_collector_other_text').show().prop('disabled', false);
                                    $('#fp_collector_other_text').show().prop('disabled', false);
                                }
                            }
                            if (ps.collector_other_text) {
                                $('#fpn_collector_other_text').val(ps.collector_other_text).show().prop('disabled', false);
                                $('#fp_collector_other_text').val(ps.collector_other_text).show().prop('disabled', false);
                            }
                            if (ps.storage_date) {
                                $('#fpn_storage_date').val(ps.storage_date);
                                $('[name="fp_storage_date"]').val(ps.storage_date);
                            }
                            if (ps.storage_time) {
                                $('#fpn_storage_time').val(ps.storage_time);
                                $('[name="fp_storage_time"]').val(ps.storage_time);
                            }
                            if (ps.duration_year) {
                                $('#fpn_duration_year').val(ps.duration_year);
                                $('[name="fp_duration_year"]').val(ps.duration_year);
                            }
                            if (ps.duration_month) {
                                $('#fpn_duration_month').val(ps.duration_month);
                                $('[name="fp_duration_month"]').val(ps.duration_month);
                            }
                            if (ps.duration_day) {
                                $('#fpn_duration_day').val(ps.duration_day);
                                $('[name="fp_duration_day"]').val(ps.duration_day);
                            }
                        }

                        // --- Section 6 Sets (new modal dynamic sets) ---
                        if (d.section6_sets && d.section6_sets.length > 0) {
                            d.section6_sets.forEach(function(setData, sIdx) {
                                // สร้าง set ใหม่ (ชุดแรกมีอยู่แล้ว)
                                if (sIdx > 0) {
                                    $('#btn_add_section6_fpn').trigger('click');
                                }
                                var sets = $('#fpn_section6_wrapper .fpn-section6-set');
                                var curSet = sets.eq(sIdx);

                                // จำนวนวัตถุพยาน
                                curSet.find('.fpn-evidence-total').val(setData.evidence_total || '');

                                // Evidence items
                                if (setData.evidence_items && setData.evidence_items.length > 0) {
                                    setData.evidence_items.forEach(function(item, eIdx) {
                                        // เพิ่ม evidence card (อันแรกมีอยู่แล้ว)
                                        if (eIdx > 0) {
                                            curSet.find('.fpn-btn-add-evidence').trigger('click');
                                        }
                                        var cards = curSet.find('.fpn-evidence-card');
                                        var card = cards.eq(eIdx);
                                        card.find('[name="fpn_ev_description[]"]').val(item.description || '');
                                        card.find('[name="fpn_ev_width[]"]').val(item.width || '');
                                        card.find('[name="fpn_ev_length[]"]').val(item.length || '');
                                        card.find('[name="fpn_ev_height[]"]').val(item.height || '');
                                        card.find('[name="fpn_ev_quantity[]"]').val(item.quantity || '');
                                        card.find('[name="fpn_ev_label_no[]"]').val(item.label_no || '');
                                        window.setLabUnits(card.find('[name="fpn_ev_lab_unit[]"]'), window.labUnitsToString(item.lab_unit) || 'fingerprint');
                                    });
                                }

                                // Global methods (section 7 — only load for first set)
                                if (sIdx === 0 && setData.global_methods && setData.global_methods.length > 0) {
                                    var methodContainer = document.getElementById('fpn_method_global_container');
                                    if (methodContainer) {
                                        setData.global_methods.forEach(function(m, mIdx) {
                                            if (mIdx > 0) {
                                                $('#fpn_btn_add_method_global').trigger('click');
                                            }
                                            var rows = $(methodContainer).find('.fpn-method-row');
                                            var row = rows.eq(mIdx);
                                            row.find('.fpn-method-select').val(m.name || '');
                                            row.find('.fpn-method-detail-input').val(m.detail || '');
                                        });
                                    }
                                }

                                // Section 8 actions (only load for first set)
                                if (sIdx === 0) {
                                    if (setData.action_evidence_dest && setData.action_evidence_dest.length > 0) {
                                        setData.action_evidence_dest.forEach(function(val) {
                                            $('input[name="fpn_action_evidence_dest[]"][value="' + val + '"]').prop('checked', true);
                                            if (val === 'อื่นๆ') {
                                                $('input[name="fpn_action_evidence_other_text"]').show().prop('disabled', false);
                                            }
                                        });
                                    }
                                    if (setData.action_evidence_other_text) {
                                        $('input[name="fpn_action_evidence_other_text"]').val(setData.action_evidence_other_text).show().prop('disabled', false);
                                    }
                                    if (setData.action_exhibit) {
                                        $('input[name="fpn_action_exhibit"]').prop('checked', true);
                                    }
                                    if (setData.action_return_station) {
                                        $('select[name="fpn_action_return_station"]').val(setData.action_return_station);
                                    }
                                    if (setData.action_forward) {
                                        $('input[name="fpn_action_forward"]').prop('checked', true);
                                    }
                                    if (setData.action_forward_depts && setData.action_forward_depts.length > 0) {
                                        setData.action_forward_depts.forEach(function(val) {
                                            $('input[name="fpn_action_forward_dept[]"][value="' + val + '"]').prop('checked', true);
                                            if (val === 'อื่นๆ') {
                                                $('input[name="fpn_action_forward_other_text"]').show().prop('disabled', false);
                                            }
                                        });
                                    }
                                    if (setData.action_forward_other_text) {
                                        $('input[name="fpn_action_forward_other_text"]').val(setData.action_forward_other_text).show().prop('disabled', false);
                                    }
                                }
                            });
                        }

                        // --- Section 9 (การถ่ายภาพวัตถุพยาน / ผลการตรวจเก็บ) ---
                        if (d.section9) {
                            var s9 = d.section9;
                            var s9wrapper = document.getElementById('fpn_section9_wrapper');

                            if (s9.photo_types && Array.isArray(s9.photo_types)) {
                                s9.photo_types.forEach(function(val) {
                                    $('input[name="fpn_photo_type[]"][value="' + val + '"]').prop('checked', true);
                                });
                            }
                            if (s9.photo_sides && Array.isArray(s9.photo_sides)) {
                                s9.photo_sides.forEach(function(val) {
                                    $('input[name="fpn_photo_side[]"][value="' + val + '"]').prop('checked', true);
                                });
                            }
                            if (s9.color_adj_detail) {
                                $('#fpn_photo_color_adj_chk').prop('checked', true);
                                $('#fpn_color_adj_detail').val(s9.color_adj_detail).show().prop('disabled', false);
                            }
                            if (s9.resize_detail) {
                                $('#fpn_photo_resize_chk').prop('checked', true);
                                $('#fpn_resize_detail').val(s9.resize_detail).show().prop('disabled', false);
                            }
                            if (s9.result_type) {
                                var resultCb = $('input.fpn-radio-result[value="' + s9.result_type + '"]');
                                resultCb.prop('checked', true).trigger('change');

                                if (s9.result_type === 'ไม่พบรอยลายนิ้วมือแฝง' && s9.not_found_reason) {
                                    setTimeout(function() {
                                        var nfInput = s9wrapper ? s9wrapper.querySelector('.fpn-not-found-reason') : null;
                                        if (nfInput) {
                                            nfInput.value = s9.not_found_reason;
                                            nfInput.disabled = false;
                                        }
                                    }, 100);
                                }
                                if (s9.result_type === 'พบรอยลายนิ้วมือแฝง') {
                                    setTimeout(function() {
                                        if (s9.found_total_sheets) {
                                            var ftEl = s9wrapper ? s9wrapper.querySelector('.fpn-found-total') : null;
                                            if (ftEl) {
                                                ftEl.value = s9.found_total_sheets;
                                                ftEl.disabled = false;
                                            }
                                        }
                                        // Found items
                                        if (s9.found_items && s9.found_items.length > 0) {
                                            var fiContainer = s9wrapper ? s9wrapper.querySelector('.fpn-found-items-container') : null;
                                            if (fiContainer) {
                                                s9.found_items.forEach(function(fi, fiIdx) {
                                                    if (fiIdx > 0) {
                                                        var addBtn = s9wrapper.querySelector('.fpn-btn-add-found-item');
                                                        if (addBtn) addBtn.click();
                                                    }
                                                    var rows = fiContainer.querySelectorAll('.fpn-found-item-row');
                                                    var row = rows[fiIdx];
                                                    if (row) {
                                                        var fromInput = row.querySelector('input[name*="fpn_found_from_item"]');
                                                        var sheetsInput = row.querySelector('input[name*="fpn_found_item_sheets"]');
                                                        if (fromInput) {
                                                            fromInput.value = fi.from_item || '';
                                                            fromInput.disabled = false;
                                                        }
                                                        if (sheetsInput) {
                                                            sheetsInput.value = fi.sheets || '';
                                                            sheetsInput.disabled = false;
                                                        }
                                                    }
                                                });
                                            }
                                        }
                                    }, 100);
                                }
                            }
                            if (s9.has_photograph) {
                                var photoChk = s9wrapper ? s9wrapper.querySelector('.fpn-has-photograph') : null;
                                if (photoChk) {
                                    photoChk.checked = true;
                                    $(photoChk).trigger('change');
                                    setTimeout(function() {
                                        if (s9.photographs && s9.photographs.length > 0) {
                                            var photoContainer = s9wrapper ? s9wrapper.querySelector('.fpn-photograph-container') : null;
                                            if (photoContainer) {
                                                s9.photographs.forEach(function(desc, pIdx) {
                                                    if (pIdx > 0) {
                                                        var addBtn = s9wrapper.querySelector('.fpn-btn-add-photograph');
                                                        if (addBtn) addBtn.click();
                                                    }
                                                    var inputs = photoContainer.querySelectorAll('input[name*="fpn_photograph_desc"]');
                                                    if (inputs[pIdx]) {
                                                        inputs[pIdx].value = desc;
                                                        inputs[pIdx].disabled = false;
                                                    }
                                                });
                                            }
                                        }
                                    }, 150);
                                }
                            }
                        }

                        // ============================================================
                        // === PDF Modal (fp_) — Section 6, 7, 8, 9 ==================
                        // ============================================================
                        if (d.section6_sets && d.section6_sets.length > 0) {
                            d.section6_sets.forEach(function(setData, sIdx) {
                                if (sIdx > 0) {
                                    var addSetBtn = document.getElementById('btn_add_section6_set');
                                    if (addSetBtn) addSetBtn.click();
                                }
                                var fpSets = $('#fp_section6_wrapper .fp-section6-set');
                                var fpCurSet = fpSets.eq(sIdx);

                                // Evidence total
                                if (sIdx === 0) {
                                    $('#fp_evidence_total').val(setData.evidence_total || '');
                                } else {
                                    fpCurSet.find('.fp-s6-evidence-total').val(setData.evidence_total || '');
                                }

                                // Evidence items
                                if (setData.evidence_items && setData.evidence_items.length > 0) {
                                    setData.evidence_items.forEach(function(item, eIdx) {
                                        if (eIdx > 0) {
                                            if (sIdx === 0) {
                                                var addEvBtn = document.getElementById('btn_add_evidence_fp');
                                                if (addEvBtn) addEvBtn.click();
                                            } else {
                                                var addEvSetBtn = fpCurSet.find('.fp-btn-add-evidence-set')[0];
                                                if (addEvSetBtn) addEvSetBtn.click();
                                            }
                                        }
                                        var fpEvContainer;
                                        if (sIdx === 0) {
                                            fpEvContainer = $('#fp_evidence_container');
                                        } else {
                                            fpEvContainer = fpCurSet.find('.fp-s6-evidence-container');
                                        }
                                        var fpCards = fpEvContainer.find('.fp-evidence-card');
                                        var fpCard = fpCards.eq(eIdx);
                                        fpCard.find('[name="fp_ev_description[]"]').val(item.description || '');
                                        fpCard.find('[name="fp_ev_width[]"]').val(item.width || '');
                                        fpCard.find('[name="fp_ev_length[]"]').val(item.length || '');
                                        fpCard.find('[name="fp_ev_height[]"]').val(item.height || '');
                                        fpCard.find('[name="fp_ev_quantity[]"]').val(item.quantity || '');
                                        fpCard.find('[name="fp_ev_label_no[]"]').val(item.label_no || '');
                                        window.setLabUnits(fpCard.find('[name="fp_ev_lab_unit[]"]'), window.labUnitsToString(item.lab_unit) || 'fingerprint');
                                    });
                                }

                                // Section 7: global methods (only for first set)
                                if (sIdx === 0 && setData.global_methods && setData.global_methods.length > 0) {
                                    var fpMethodContainer = document.getElementById('fp_method_global_container');
                                    if (fpMethodContainer) {
                                        setData.global_methods.forEach(function(m, mIdx) {
                                            if (mIdx > 0) {
                                                var addMethodBtn = document.getElementById('btn_add_method_fp');
                                                if (addMethodBtn) addMethodBtn.click();
                                            }
                                            var fpMRows = $(fpMethodContainer).find('.fp-method-row');
                                            var fpMRow = fpMRows.eq(mIdx);
                                            fpMRow.find('.fp-method-select').val(m.name || '');
                                            fpMRow.find('.fp-method-detail-input').val(m.detail || '');
                                        });
                                    }
                                }

                                // Section 8: actions (only for first set)
                                if (sIdx === 0) {
                                    if (setData.action_evidence_dest && setData.action_evidence_dest.length > 0) {
                                        setData.action_evidence_dest.forEach(function(val) {
                                            $('input[name="fp_action_evidence_dest"][value="' + val + '"]').prop('checked', true);
                                            if (val === 'อื่นๆ') {
                                                $('#fp_action_evidence_other_text').show().prop('disabled', false);
                                            }
                                        });
                                    }
                                    if (setData.action_evidence_other_text) {
                                        $('#fp_action_evidence_other_text').val(setData.action_evidence_other_text).show().prop('disabled', false);
                                    }
                                    if (setData.action_exhibit) {
                                        $('input[name="fp_action_exhibit"][value="' + setData.action_exhibit + '"]').prop('checked', true);
                                    }
                                    if (setData.action_return_station) {
                                        $('select[name="fp_action_return_station"]').val(setData.action_return_station);
                                    }
                                    if (setData.action_forward) {
                                        $('input[name="fp_action_forward"]').prop('checked', true);
                                    }
                                    if (setData.action_forward_depts && setData.action_forward_depts.length > 0) {
                                        setData.action_forward_depts.forEach(function(val) {
                                            $('input[name="fp_action_forward_dept[]"][value="' + val + '"]').prop('checked', true);
                                            if (val === 'อื่นๆ') {
                                                $('#fp_action_forward_other_text').show().prop('disabled', false);
                                            }
                                        });
                                    }
                                    if (setData.action_forward_other_text) {
                                        $('#fp_action_forward_other_text').val(setData.action_forward_other_text).show().prop('disabled', false);
                                    }
                                }
                            });
                        }

                        // --- Section 9 (PDF modal fp_) ---
                        if (d.section9) {
                            var s9fp = d.section9;
                            if (s9fp.photo_types && Array.isArray(s9fp.photo_types)) {
                                s9fp.photo_types.forEach(function(val) {
                                    $('input[name="fp_photo_type[]"][value="' + val + '"]').prop('checked', true);
                                });
                            }
                            if (s9fp.photo_sides && Array.isArray(s9fp.photo_sides)) {
                                s9fp.photo_sides.forEach(function(val) {
                                    $('input[name="fp_photo_side[]"][value="' + val + '"]').prop('checked', true);
                                });
                            }
                            if (s9fp.color_adj_detail) {
                                $('#fp_photo_color_adj').prop('checked', true);
                                $('#fp_color_adj_detail').val(s9fp.color_adj_detail).show().prop('disabled', false);
                            }
                            if (s9fp.resize_detail) {
                                $('#fp_photo_resize').prop('checked', true);
                                $('#fp_resize_detail').val(s9fp.resize_detail).show().prop('disabled', false);
                            }
                            if (s9fp.result_type) {
                                $('input.fp-radio-result[value="' + s9fp.result_type + '"]').prop('checked', true);

                                if (s9fp.result_type === 'ไม่พบรอยลายนิ้วมือแฝง') {
                                    $('#fp_not_found_detail_wrapper').show();
                                    $('#fp_not_found_reason').prop('disabled', false);
                                    if (s9fp.not_found_reason) {
                                        $('#fp_not_found_reason').val(s9fp.not_found_reason);
                                    }
                                }
                                if (s9fp.result_type === 'พบรอยลายนิ้วมือแฝง') {
                                    $('#fp_found_detail_wrapper').show();
                                    $('#fp_found_detail_wrapper').find('input, button').prop('disabled', false);
                                    if (s9fp.found_total_sheets) {
                                        $('#fp_found_total_sheets').val(s9fp.found_total_sheets).prop('disabled', false);
                                    }
                                    if (s9fp.found_items && s9fp.found_items.length > 0) {
                                        s9fp.found_items.forEach(function(fi, fiIdx) {
                                            if (fiIdx > 0) {
                                                var addFIBtn = document.getElementById('btn_add_found_item_fp');
                                                if (addFIBtn) addFIBtn.click();
                                            }
                                            var fpFIRows = $('#fp_found_items_container .fp-found-item-row');
                                            var fpFIRow = fpFIRows.eq(fiIdx);
                                            if (fpFIRow.length) {
                                                fpFIRow.find('input[name="fp_found_from_item[]"]').val(fi.from_item || '').prop('disabled', false);
                                                fpFIRow.find('input[name="fp_found_item_sheets[]"]').val(fi.sheets || '').prop('disabled', false);
                                            }
                                        });
                                    }
                                }
                            }
                            if (s9fp.has_photograph) {
                                $('#fp_has_photograph').prop('checked', true);
                                $('#fp_photograph_wrapper').show();
                                $('#fp_photograph_wrapper').find('input, button').prop('disabled', false);
                                if (s9fp.photographs && s9fp.photographs.length > 0) {
                                    s9fp.photographs.forEach(function(desc, pIdx) {
                                        if (pIdx > 0) {
                                            var addPhotoBtn = document.getElementById('btn_add_photograph_fp');
                                            if (addPhotoBtn) addPhotoBtn.click();
                                        }
                                        var fpPhotoInputs = $('#fp_photograph_container input[name="fp_photograph_desc[]"]');
                                        if (fpPhotoInputs.eq(pIdx).length) {
                                            fpPhotoInputs.eq(pIdx).val(desc).prop('disabled', false);
                                        }
                                    });
                                }
                            }
                        }

                        // --- Photo Records (section 10) ---
                        if (d.photo_records) {
                            var pr = d.photo_records;
                            if (pr.id_start) {
                                $('#fpn_photo_id_start').val(pr.id_start);
                                $('[name="fp_photo_id_start"]').val(pr.id_start);
                            }
                            if (pr.id_end) {
                                $('#fpn_photo_id_end').val(pr.id_end);
                                $('[name="fp_photo_id_end"]').val(pr.id_end);
                            }
                            if (pr.amount) {
                                $('#fpn_photo_amount').val(pr.amount);
                                $('[name="fp_photo_amount"]').val(pr.amount);
                            }
                            // PDF modal photo record fields
                            if (pr.inspect_date) $('[name="fp_photo_inspect_date"]').val(pr.inspect_date);
                            if (pr.inspect_time) $('[name="fp_photo_inspect_time"]').val(pr.inspect_time);
                            if (pr.photographer_name) $('#fp_photographer_name').val(pr.photographer_name).trigger('change');
                            if (pr.photographer_datetime) $('#fp_photographer_datetime').val(pr.photographer_datetime);
                        }

                        // --- Photos (from BLOB) ---
                        if (d.photos && d.photos.length > 0) {
                            d.photos.forEach(function(p) {
                                if (p.file_id) {
                                    var fileId = Date.now() + '_' + Math.random().toString(16).slice(2);
                                    attachmentStoreFP.push({
                                        id: fileId,
                                        src: './api/incidentCheckList/getFile.php?id=' + p.file_id,
                                        name: 'photo_' + p.file_id,
                                        existing: true,
                                        db_file_id: p.file_id
                                    });
                                }
                            });
                            renderFPAttachmentGrid();
                            updateFPRealInput();
                        }

                        // --- Photo Captions (PDF modal) ---
                        if (d.photo_captions && d.photo_captions.length > 0) {
                            d.photo_captions.forEach(function(cap, ci) {
                                if (ci < attachmentStoreFP.length) {
                                    attachmentStoreFP[ci].caption = cap;
                                }
                            });
                            if (typeof fpPhotoRenderFromStore === 'function') fpPhotoRenderFromStore();
                        }

                        // Reset flags and scroll modal to top after loading
                        window.fpnSkipScroll = false;
                        window.fpSkipScroll = false;
                        setTimeout(function() {
                            $('#addCheckListModalFingerprintNew .modal-body').scrollTop(0);
                            $('#addCheckListModalFingerprint .modal-body').scrollTop(0);
                        }, 100);

                    } else if (res && !res.success && res.message) {
                        console.error('Fingerprint data load error:', res.message, res);
                        Swal.fire({
                            icon: 'warning',
                            title: 'ข้อมูลเดิมมีปัญหา',
                            html: res.message + '<br><small class="text-muted">กรุณากรอกข้อมูลใหม่และบันทึกอีกครั้ง</small>',
                            confirmButtonText: 'ตกลง'
                        });
                    }
                    if (typeof onComplete === 'function') onComplete();
                },
                error: function(xhr, status, error) {
                    console.error('Fingerprint data AJAX error:', status, error);
                    if (typeof onComplete === 'function') onComplete();
                }
            });
        }

        // ===== Fingerprint standard modal → โหลดข้อมูลหลัง shown =====
        $('#addCheckListModalFingerprintNew').on('shown.bs.modal', function() {
            console.log('[FP Modal New] shown.bs.modal fired, pendingFPLoadId:', pendingFPLoadId, 'pendingFPLoadMode:', pendingFPLoadMode);
            if (pendingFPLoadId) {
                const loadId = pendingFPLoadId;
                const loadMode = pendingFPLoadMode;
                pendingFPLoadId = null;
                pendingFPLoadMode = null;

                var $fpNewOverlay = $('#fpNewLoadingOverlay');
                $fpNewOverlay.removeClass('d-none').css('display', 'flex');
                var $fpNewModalBody = $fpNewOverlay.closest('.modal-body');
                lockModalBodyScroll($fpNewModalBody);
                var fpNewOverlayStart = Date.now();
                var FP_NEW_ANIM_MS = 2000;

                function hideFpNewOverlay() {
                    var elapsed = Date.now() - fpNewOverlayStart;
                    var remaining = FP_NEW_ANIM_MS - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() {
                            $fpNewOverlay.addClass('d-none');
                            unlockModalBodyScroll($fpNewModalBody);
                        }, remaining);
                    } else {
                        $fpNewOverlay.addClass('d-none');
                        unlockModalBodyScroll($fpNewModalBody);
                    }
                }

                if (loadMode === 'edit') {
                    loadFingerprintDataToModal(loadId, function() {
                        hideFpNewOverlay();
                    });
                } else if (loadMode === 'prefill') {
                    console.log('[FP Modal New] เรียก prefillIncidentDataForFingerprint, loadId:', loadId);
                    prefillIncidentDataForFingerprint(loadId);
                    hideFpNewOverlay();
                }
            }
        });

        // ===== Fingerprint PDF modal → โหลดข้อมูลหลัง shown =====
        $('#addCheckListModalFingerprint').on('shown.bs.modal', function() {
            console.log('[FP Modal PDF] shown.bs.modal fired, pendingFPLoadId:', pendingFPLoadId, 'pendingFPLoadMode:', pendingFPLoadMode);
            if (pendingFPLoadId) {
                const loadId = pendingFPLoadId;
                const loadMode = pendingFPLoadMode;
                pendingFPLoadId = null;
                pendingFPLoadMode = null;

                var $fpPdfOverlay = $('#fpPdfLoadingOverlay');
                $fpPdfOverlay.removeClass('d-none').css('display', 'flex');
                var $fpPdfModalBody = $fpPdfOverlay.closest('.modal-body');
                lockModalBodyScroll($fpPdfModalBody);
                var fpPdfOverlayStart = Date.now();
                var FP_PDF_ANIM_MS = 2000;

                function hideFpPdfOverlay() {
                    var elapsed = Date.now() - fpPdfOverlayStart;
                    var remaining = FP_PDF_ANIM_MS - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() {
                            $fpPdfOverlay.addClass('d-none');
                            unlockModalBodyScroll($fpPdfModalBody);
                        }, remaining);
                    } else {
                        $fpPdfOverlay.addClass('d-none');
                        unlockModalBodyScroll($fpPdfModalBody);
                    }
                }

                if (loadMode === 'edit') {
                    loadFingerprintDataToModal(loadId, function() {
                        if (typeof fpfUpdatePageNumbers === 'function') fpfUpdatePageNumbers();
                        hideFpPdfOverlay();
                    });
                } else if (loadMode === 'prefill') {
                    console.log('[FP Modal PDF] เรียก prefillIncidentDataForFingerprint, loadId:', loadId);
                    prefillIncidentDataForFingerprint(loadId);
                    hideFpPdfOverlay();
                }
            }
            if (typeof fpPhotoRenderFromStore === 'function') fpPhotoRenderFromStore();
        });

        // ===== Scene Evidence standard modal (type 07) → โหลดข้อมูลหลัง shown =====
        $('#addCheckListModalSceneEvidence').on('shown.bs.modal', function() {
            console.log('[EV7 Modal Std] shown.bs.modal fired, pendingEV7LoadId:', pendingEV7LoadId, 'pendingEV7LoadMode:', pendingEV7LoadMode);

            initializeSignaturePadCanvases(['sig-canvas-ev7-receiver', 'sig-canvas-ev7-sender']);

            if (pendingEV7LoadId) {
                const loadId = pendingEV7LoadId;
                const loadMode = pendingEV7LoadMode;
                pendingEV7LoadId = null;
                pendingEV7LoadMode = null;

                var $ev7Overlay = $('#ev7LoadingOverlay');
                $ev7Overlay.removeClass('d-none').css('display', 'flex');
                var $ev7ModalBody = $ev7Overlay.closest('.modal-body');
                lockModalBodyScroll($ev7ModalBody);
                var ev7OverlayStart = Date.now();
                var EV7_ANIM_MS = 2000;

                function hideEv7Overlay() {
                    var elapsed = Date.now() - ev7OverlayStart;
                    var remaining = EV7_ANIM_MS - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() {
                            $ev7Overlay.addClass('d-none');
                            unlockModalBodyScroll($ev7ModalBody);
                        }, remaining);
                    } else {
                        $ev7Overlay.addClass('d-none');
                        unlockModalBodyScroll($ev7ModalBody);
                    }
                }

                if (loadMode === 'edit') {
                    loadSceneEvidenceDataToModal(loadId, function() {
                        hideEv7Overlay();
                    });
                } else if (loadMode === 'prefill') {
                    console.log('[EV7 Modal Std] เรียก prefillIncidentDataForSceneEvidence, loadId:', loadId);
                    prefillIncidentDataForSceneEvidence(loadId);
                    hideEv7Overlay();
                }
            }
        });

        // ===== Scene Evidence PDF modal (type 07) → โหลดข้อมูลหลัง shown =====
        $('#sceneEvidenceFormPdfModal').on('shown.bs.modal', function() {
            console.log('[EV7 Modal PDF] shown.bs.modal fired, pendingEV7LoadId:', pendingEV7LoadId, 'pendingEV7LoadMode:', pendingEV7LoadMode);
            if (pendingEV7LoadId) {
                const loadId = pendingEV7LoadId;
                const loadMode = pendingEV7LoadMode;
                pendingEV7LoadId = null;
                pendingEV7LoadMode = null;

                var $ev7PdfOverlay = $('#sevpfLoadingOverlay');
                if ($ev7PdfOverlay.length) {
                    $ev7PdfOverlay.removeClass('d-none').css('display', 'flex');
                }
                var $ev7PdfModalBody = $ev7PdfOverlay.length ? $ev7PdfOverlay.closest('.modal-body') : null;
                if ($ev7PdfModalBody && $ev7PdfModalBody.length) lockModalBodyScroll($ev7PdfModalBody);
                var ev7PdfOverlayStart = Date.now();
                var EV7_PDF_ANIM_MS = 2000;

                function hideEv7PdfOverlay() {
                    var elapsed = Date.now() - ev7PdfOverlayStart;
                    var remaining = EV7_PDF_ANIM_MS - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() {
                            if ($ev7PdfOverlay.length) $ev7PdfOverlay.addClass('d-none');
                            if ($ev7PdfModalBody && $ev7PdfModalBody.length) unlockModalBodyScroll($ev7PdfModalBody);
                        }, remaining);
                    } else {
                        if ($ev7PdfOverlay.length) $ev7PdfOverlay.addClass('d-none');
                        if ($ev7PdfModalBody && $ev7PdfModalBody.length) unlockModalBodyScroll($ev7PdfModalBody);
                    }
                }

                if (loadMode === 'edit') {
                    loadSceneEvidenceDataToModal(loadId, function() {
                        hideEv7PdfOverlay();
                    });
                } else if (loadMode === 'prefill') {
                    console.log('[EV7 Modal PDF] เรียก prefillIncidentDataForSceneEvidence, loadId:', loadId);
                    prefillIncidentDataForSceneEvidence(loadId);
                    hideEv7PdfOverlay();
                }
            }
        });




        // ===== Person Evidence standard modal (type 08) → โหลดข้อมูลหลัง shown =====
        $('#addCheckListModalPersonEvidence').on('shown.bs.modal', function() {
            if (pendingEV8LoadId) {
                const loadId = pendingEV8LoadId;
                const loadMode = pendingEV8LoadMode;
                pendingEV8LoadId = null;
                pendingEV8LoadMode = null;

                var $ev8Overlay = $('#ev8LoadingOverlay');
                $ev8Overlay.removeClass('d-none').css('display', 'flex');
                var $ev8ModalBody = $ev8Overlay.closest('.modal-body');
                lockModalBodyScroll($ev8ModalBody);
                var ev8OverlayStart = Date.now();
                var EV8_ANIM_MS = 2000;
                var ev8LoadFinished = false;
                var EV8_LOAD_FAILSAFE_MS = 8000;

                function hideEv8Overlay() {
                    if (ev8LoadFinished) return;
                    ev8LoadFinished = true;
                    var elapsed = Date.now() - ev8OverlayStart;
                    var remaining = EV8_ANIM_MS - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() {
                            $ev8Overlay.addClass('d-none');
                            unlockModalBodyScroll($ev8ModalBody);
                        }, remaining);
                    } else {
                        $ev8Overlay.addClass('d-none');
                        unlockModalBodyScroll($ev8ModalBody);
                    }
                }

                setTimeout(function() {
                    if (!ev8LoadFinished) {
                        console.warn('[EV8] Loading timeout on standard modal, forcing overlay hide.');
                        hideEv8Overlay();
                    }
                }, EV8_LOAD_FAILSAFE_MS);

                try {
                    if (loadMode === 'edit') {
                        if (typeof loadPersonEvidenceDataToModal === 'function') {
                            loadPersonEvidenceDataToModal(loadId, function() {
                                hideEv8Overlay();
                            });
                        } else {
                            console.warn('[EV8] loadPersonEvidenceDataToModal is not defined.');
                            hideEv8Overlay();
                        }
                    } else if (loadMode === 'prefill') {
                        if (typeof prefillIncidentDataForPersonEvidence === 'function') {
                            prefillIncidentDataForPersonEvidence(loadId);
                        } else {
                            console.warn('[EV8] prefillIncidentDataForPersonEvidence is not defined.');
                        }
                        hideEv8Overlay();
                    } else {
                        hideEv8Overlay();
                    }
                } catch (e) {
                    console.error('[EV8] Error while loading standard modal:', e);
                    hideEv8Overlay();
                }
            }
        });

        // ===== Person Evidence PDF modal (type 08) → โหลดข้อมูลหลัง shown =====
        $('#personEvidenceFormPdfModal').on('shown.bs.modal', function() {
            if (pendingEV8LoadId) {
                const loadId = pendingEV8LoadId;
                const loadMode = pendingEV8LoadMode;
                pendingEV8LoadId = null;
                pendingEV8LoadMode = null;

                var $ev8PdfOverlay = $('#pepfLoadingOverlay');
                if ($ev8PdfOverlay.length) {
                    $ev8PdfOverlay.removeClass('d-none').css('display', 'flex');
                }
                var $ev8PdfModalBody = $ev8PdfOverlay.length ? $ev8PdfOverlay.closest('.modal-body') : null;
                if ($ev8PdfModalBody && $ev8PdfModalBody.length) lockModalBodyScroll($ev8PdfModalBody);
                var ev8PdfOverlayStart = Date.now();
                var EV8_PDF_ANIM_MS = 2000;
                var ev8PdfLoadFinished = false;
                var EV8_PDF_LOAD_FAILSAFE_MS = 8000;

                function hideEv8PdfOverlay() {
                    if (ev8PdfLoadFinished) return;
                    ev8PdfLoadFinished = true;
                    var elapsed = Date.now() - ev8PdfOverlayStart;
                    var remaining = EV8_PDF_ANIM_MS - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() {
                            if ($ev8PdfOverlay.length) $ev8PdfOverlay.addClass('d-none');
                            if ($ev8PdfModalBody && $ev8PdfModalBody.length) unlockModalBodyScroll($ev8PdfModalBody);
                        }, remaining);
                    } else {
                        if ($ev8PdfOverlay.length) $ev8PdfOverlay.addClass('d-none');
                        if ($ev8PdfModalBody && $ev8PdfModalBody.length) unlockModalBodyScroll($ev8PdfModalBody);
                    }
                }

                setTimeout(function() {
                    if (!ev8PdfLoadFinished) {
                        console.warn('[EV8] Loading timeout on PDF modal, forcing overlay hide.');
                        hideEv8PdfOverlay();
                    }
                }, EV8_PDF_LOAD_FAILSAFE_MS);

                try {
                    if (loadMode === 'edit') {
                        if (typeof loadPersonEvidenceDataToModal === 'function') {
                            loadPersonEvidenceDataToModal(loadId, function() {
                                hideEv8PdfOverlay();
                            });
                        } else {
                            console.warn('[EV8] loadPersonEvidenceDataToModal is not defined.');
                            hideEv8PdfOverlay();
                        }
                    } else if (loadMode === 'prefill') {
                        if (typeof prefillIncidentDataForPersonEvidence === 'function') {
                            prefillIncidentDataForPersonEvidence(loadId);
                        } else {
                            console.warn('[EV8] prefillIncidentDataForPersonEvidence is not defined.');
                        }
                        hideEv8PdfOverlay();
                    } else {
                        hideEv8PdfOverlay();
                    }
                } catch (e) {
                    console.error('[EV8] Error while loading PDF modal:', e);
                    hideEv8PdfOverlay();
                }
            }
        });


        // ========================================================================

        // 1. Logic การเลือกแบบสลับ (Exclusive Selection)
        // ถ้าเลือก "ไม่ใช้อาวุธ" -> ให้เอา "ใช้อาวุธ" ออก และเคลียร์ตัวเลือกย่อยทั้งหมด
        $('#weapon_none').on('change', function() {
            if ($(this).is(':checked')) {
                $('#weapon_used').prop('checked', false).trigger('change'); // เอาติ๊ก 'ใช้อาวุธ' ออก

                // เคลียร์และเอาติ๊กอาวุธย่อยออกให้หมด
                $('.weapon-type, .weapon-check-toggle').prop('checked', false).trigger('change');
            }
        });

        // ถ้าเลือก "ใช้อาวุธ" -> ให้เอา "ไม่ใช้อาวุธ" ออก และแสดงกล่องตัวเลือกย่อย
        $('#weapon_used').on('change', function() {
            if ($(this).is(':checked')) {
                $('#weapon_none').prop('checked', false);
            } else {
                // ถ้าเอาติ๊กออก ก็ควรเคลียร์ตัวเลือกย่อยด้วย
                $('.weapon-type, .weapon-check-toggle').prop('checked', false).trigger('change');
            }
        });

        $('#weapon_used').on('change', function() {
            const $options = $('#weapon_options_div');
            if ($(this).is(':checked')) {
                $('#weapon_none').prop('checked', false);
                $options.css({
                    'pointer-events': 'auto',
                    'opacity': '1'
                });
            } else {
                $options.css({
                    'pointer-events': 'none',
                    'opacity': '0.5'
                });
                // Optional: Clear sub-options if unchecking parent?
                $('.weapon-sub-check').prop('checked', false).trigger('change');
            }
        });

        $('.weapon-sub-check').on('change', function() {
            if ($(this).is(':checked')) {
                if (!$('#weapon_used').is(':checked')) {
                    $('#weapon_used').prop('checked', true).trigger('change');
                }
            }
        });

        // ถ้ามีการเลือกอาวุธย่อย (มีด/ปืน/เชือก/อื่นๆ) -> ให้ติ๊กหัวข้อ "ใช้อาวุธ" ให้เองโดยอัตโนมัติ
        $('.weapon-type, .weapon-check-toggle').on('change', function() {
            if ($(this).is(':checked')) {
                // ถ้า Parent ยังไม่ถูกติ๊ก ให้ติ๊กทันที
                if (!$('#weapon_used').is(':checked')) {
                    $('#weapon_used').prop('checked', true).trigger('change');
                }
            }
        });

        // 2. Logic สำหรับช่อง "อื่นๆ" (แสดง/ซ่อน + โฟกัสอัตโนมัติ)
        $('.weapon-check-toggle').on('change', function() {
            // ค้นหาช่อง input ที่อยู่ใน container เดียวกัน (ใช้ .closest เพื่อความชัวร์)
            const container = $(this).closest('.d-flex');
            const inputField = container.find('.weapon-input-group');

            if ($(this).is(':checked')) {
                inputField.removeClass('d-none');
                inputField.prop('required', true).focus(); // สั่งให้ Focus ทันทีที่ติ๊ก
            } else {
                inputField.addClass('d-none');
                inputField.prop('required', false).val(''); // ล้างค่าเมื่อเอาติ๊กออก
            }
        });

        // Logic: เมื่อติ๊ก Checkbox -> แสดงช่องกรอก -> และ Focus ทันที
        $(document).on('change', '.trace-check-toggle', function() {
            const container = $(this).closest('.bg-white');
            const inputDiv = container.find('.trace-input-div');
            const inputField = inputDiv.find('textarea, input');

            if ($(this).is(':checked')) {
                inputDiv.removeClass('d-none').addClass('animate__animated animate__fadeIn');

                // Auto Focus (ใส่ timeout เล็กน้อยเพื่อให้ UI render ทัน)
                setTimeout(() => {
                    inputField.focus();
                }, 100);

            } else {
                // ซ่อนและล้างค่า
                inputDiv.addClass('d-none').removeClass('animate__animated animate__fadeIn');
                inputField.val('');
            }
        });

        // เพิ่ม CSS Hover Effect สำหรับปุ่มลบผ่าน JS 
        $(document).on('mouseenter', '.remove-trace-btn', function() {
            $(this).removeClass('btn-outline-secondary').addClass('btn-danger text-white');
        }).on('mouseleave', '.remove-trace-btn', function() {
            $(this).removeClass('btn-danger text-white').addClass('btn-outline-secondary');
        });

        // (Blood test section ย้ายไปอยู่ section คราบสีแดงคล้ายโลหิต ด้านล่างแล้ว)

        // --- Logic สำหรับ "การรักษาสถานที่เกิดเหตุ" ---
        $('.preservation-check').on('change', function() {
            // 1. Uncheck ตัวอื่นในกลุ่มทันที (ทำให้เหมือน Radio)
            if ($(this).is(':checked')) {
                $('.preservation-check').not(this).prop('checked', false);
            }

            // 2. เช็คสถานะปัจจุบัน
            const isYes = $('#preservation_yes').is(':checked');
            const isNo = $('#preservation_no').is(':checked');

            // อ้างอิง Element กลาง
            const $detailDiv = $('#preservation_detail_div');
            const $input = $('#preservation_text');
            const $label = $('#preservation_label');

            // 3. แสดง/ซ่อน Input Field
            if (isYes || isNo) {
                $detailDiv.removeClass('d-none'); // แสดงกล่อง
                $input.prop('disabled', false).prop('required', true); // ปลดล็อค

                // 4. เปลี่ยนข้อความตามตัวเลือก
                if (isYes) {
                    // $label.text('รายละเอียดการรักษา:'); // (Optional) เปลี่ยน Label ถ้าต้องการ
                    $input.attr('placeholder', 'ระบุรายละเอียด (โดยใคร/อย่างไร)...');
                } else {
                    // $label.text('สาเหตุที่ไม่รักษา:'); // (Optional)
                    $input.attr('placeholder', 'ระบุสาเหตุที่ไม่มีการรักษา...');
                }

                // Focus ถ้าเพิ่งกดเลือก
                if ($(this).is(':checked')) {
                    setTimeout(() => $input.focus(), 100);
                }

            } else {
                // กรณีไม่มีการเลือกเลย (Uncheck ทั้งคู่)
                $detailDiv.addClass('d-none');
                $input.prop('disabled', true).prop('required', false).val('');
            }
        });


        // --- 2. Logic สำหรับ "ลักษณะภายนอก" (Multi-select + Enable inputs) ---
        $('.building-check').on('change', function() {
            const key = $(this).data('key');
            const isChecked = $(this).is(':checked');

            // Input Detail และ Floor ที่เกี่ยวข้อง
            const $detailInput = $('#detail_' + key);
            const $floorInput = $('#floor_' + key);

            if (isChecked) {
                // Enable ทั้งคู่
                $detailInput.prop('disabled', false).prop('required', true).focus(); // Auto Focus ที่ช่องรายละเอียดก่อน
                $floorInput.prop('disabled', false).prop('required', true);

                // เปลี่ยน Style Input ให้ดู Active (Optional: ถ้าต้องการ)
                $detailInput.removeClass('bg-transparent').addClass('bg-white');
            } else {
                // Disable และล้างค่า
                $detailInput.prop('disabled', true).prop('required', false).val('').removeClass('bg-white').addClass('bg-transparent');
                $floorInput.prop('disabled', true).prop('required', false).val('');
            }
        });


        // --- 3. Logic สำหรับ "รั้วกั้น" (Exclusive Checkbox) ---
        $('.fence-check').on('change', function() {
            if ($(this).is(':checked')) {
                // Uncheck ตัวอื่นในกลุ่มเดียวกัน
                $('.fence-check').not(this).prop('checked', false);
            }
        });

        // --- Logic สำหรับ Checkbox "อื่นๆ" (entry_other_trace) ---
        // ถูกจัดการโดย generic .toggle-input handler แล้ว

        // --- Logic สำหรับ "บริเวณ/ตำแหน่ง" (Generic Loop) ---
        $('.entry-location-check').on('change', function() {
            const isChecked = $(this).is(':checked');
            const targetId = $(this).data('target');
            const $target = $('#' + targetId);

            // ตรวจสอบว่า target เป็น input/textarea/select โดยตรง หรือเป็น container div
            if ($target.is('input, textarea, select')) {
                if (isChecked) {
                    $target.prop('disabled', false).prop('required', true).focus();
                } else {
                    $target.prop('disabled', true).prop('required', false).val('');
                }
            } else {
                const $input = $target.find('input, textarea, select');
                if (isChecked) {
                    $target.removeClass('d-none');
                    $input.prop('disabled', false).prop('required', true).first().focus();
                } else {
                    $target.addClass('d-none');
                    $input.prop('disabled', true).prop('required', false).val('');
                }
            }
        });

        // --- Logic สำหรับ "เครื่องมือที่คนร้ายใช้ (อื่นๆ)" ---
        // ถูกจัดการโดย generic .toggle-input handler แล้ว

        // --- Logic Level 1: การใช้อาวุธ (Main Toggle) ---
        $('input[name="weapon_use_status"]').on('change', function() {
            // 1. ทำให้ Checkbox 2 ตัวนี้ทำงานเหมือน Radio (เลือกได้อย่างใดอย่างหนึ่ง)
            if ($(this).is(':checked')) {
                $('input[name="weapon_use_status"]').not(this).prop('checked', false);
            }

            // 2. เช็คว่าเลือก "ใช้อาวุธ" (weapon_used) หรือไม่
            const isWeaponUsed = $('#weapon_used').is(':checked');
            const $optionsDiv = $('#weapon_options_div');

            if (isWeaponUsed) {
                $optionsDiv.removeClass('d-none');
                // (Optional) อาจจะ focus ไปที่ตัวเลือกแรกเพื่อ UX ที่ดี
            } else {
                $optionsDiv.addClass('d-none');
                // Reset ข้อมูลข้างในเมื่อซ่อน
                $optionsDiv.find('input[type="checkbox"]').prop('checked', false);
                $optionsDiv.find('input[type="text"]').prop('disabled', true).val('').parent().addClass('d-none');
            }
        });

        // --- Logic Level 2: ประเภทอาวุธ "อื่นๆ" (Sub Toggle) ---
        // ถูกจัดการโดย generic .toggle-input handler แล้ว

        // --- Logic สำหรับ "การพันธนาการ (คนร้ายใช้...)" ---
        $('#restraint_binding').on('change', function() {
            const isChecked = $(this).is(':checked');
            const $targetDiv = $('#binding_details');
            const $inputs = $targetDiv.find('input'); // หา input ทุกตัวข้างใน (เผื่อในอนาคตคุณเปิดใช้ช่อง "วิธี" ด้วย)

            if (isChecked) {
                $targetDiv.removeClass('d-none');
                // ปลดล็อค input, บังคับกรอก, และ focus ตัวแรก
                $inputs.prop('disabled', false).prop('required', true).first().focus();
            } else {
                $targetDiv.addClass('d-none');
                // ล็อค input, เลิกบังคับกรอก, และล้างค่า
                $inputs.prop('disabled', true).prop('required', false).val('');
            }
        });

        // --- Generic Logic สำหรับ Checkbox "อื่นๆ" (ใช้ได้กับทุกจุด) ---
        $(document).on('change', '.toggle-input', function() {
            const $checkbox = $(this);
            const isChecked = $checkbox.is(':checked');
            const targetId = $checkbox.data('target'); // รับ ID ของกล่อง input เป้าหมาย
            const $target = $('#' + targetId);

            // ตรวจสอบว่า target เป็น input/textarea/select โดยตรง หรือเป็น container div
            if ($target.is('input, textarea, select')) {
                // Target เป็น input element โดยตรง
                if (isChecked) {
                    $target.prop('disabled', false).prop('required', true).focus();
                } else {
                    $target.prop('disabled', true).prop('required', false).val('');
                }
            } else {
                // Target เป็น container div
                const $input = $target.find('input, textarea, select');
                if (isChecked) {
                    $target.removeClass('d-none');
                    $input.prop('disabled', false).prop('required', true).first().focus();
                } else {
                    $target.addClass('d-none');
                    $input.prop('disabled', true).prop('required', false).val('');
                }
            }
        });

        // --- Camera & File Upload Logic (Drag & Drop) ---
        const cameraInput = $('#camera_input_property');
        const $dropzone = $('#dropzone_property');

        // คลิก dropzone → เปิด file browser
        $dropzone.on('click', function(e) {
            if ($(e.target).closest('button').length) return;
            inputPhotos.trigger('click');
        });

        // ปุ่มเลือกไฟล์
        $('#btn_choose_file_property').on('click', function(e) {
            e.stopPropagation();
            inputPhotos.trigger('click');
        });

        // ปุ่มเปิดกล้อง
        $('#btn_open_camera_property').on('click', function(e) {
            e.stopPropagation();
            cameraInput.trigger('click');
        });

        // Drag & Drop events
        $dropzone.on('dragenter dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        }).on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        }).on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            var dt = e.originalEvent.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                handleFiles(dt.files);
            }
        });

        // จัดการไฟล์จากการเลือกในแกลลอรี่
        inputPhotos.on('change', function(e) {
            handleFiles(e.target.files);
            $(this).val('');
        });

        // จัดการไฟล์จากกล้อง
        cameraInput.on('change', function(e) {
            handleFiles(e.target.files);
            $(this).val('');
        });

        // ปุ่มลบรูปทั้งหมด
        $('#btn_clear_all_photos').on('click', function() {
            if (!attachmentStore.length) return;
            Swal.fire({
                title: 'ลบรูปทั้งหมด?',
                text: 'ต้องการลบรูปภาพที่แนบทั้งหมดหรือไม่',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ลบทั้งหมด',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#dc3545'
            }).then(function(result) {
                if (result.isConfirmed) {
                    attachmentStore.forEach(function(item) {
                        if (item.existing && item.db_file_id) {
                            deletedExistingPhotos.push({
                                file_id: item.db_file_id
                            });
                        } else if (item.existing && item.disk_filename) {
                            deletedExistingPhotos.push(item.disk_filename);
                        }
                    });
                    attachmentStore.length = 0;
                    renderPropertyAttachmentGrid();
                    updatePropertyRealInput();
                }
            });
        });

        // --- Camera & File Upload Logic สำหรับ modal_life ---
        // ปุ่มเลือกไฟล์จาก Gallery (Life Modal)
        $('#btn_choose_file_life').on('click', function(e) {
            e.stopPropagation();
            inputPhotosLife.trigger('click');
        });

        // ปุ่มเปิดกล้องถ่ายภาพ (Life Modal)
        $('#btn_open_camera_life').on('click', function(e) {
            e.stopPropagation();
            cameraInputLife.trigger('click');
        });

        // --- Drag & Drop สำหรับ dropzone_life ---
        var dropzoneLife = document.getElementById('dropzone_life');
        if (dropzoneLife) {
            dropzoneLife.addEventListener('click', function(e) {
                if (e.target.closest('#btn_choose_file_life') || e.target.closest('#btn_open_camera_life')) return;
                inputPhotosLife.trigger('click');
            });
            dropzoneLife.addEventListener('dragenter', function(e) { e.preventDefault(); this.classList.add('dragover'); });
            dropzoneLife.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('dragover'); });
            dropzoneLife.addEventListener('dragleave', function(e) { e.preventDefault(); this.classList.remove('dragover'); });
            dropzoneLife.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                    handleFilesLife(e.dataTransfer.files);
                }
            });
        }

        // จัดการไฟล์จากการเลือกในแกลลอรี่ (Life Modal)
        inputPhotosLife.on('change', function(e) {
            const files = e.target.files;
            handleFilesLife(files);
            $(this).val(''); // Reset เพื่อให้เลือกไฟล์เดิมซ้ำได้
        });

        // จัดการไฟล์จากกล้อง (Life Modal)
        cameraInputLife.on('change', function(e) {
            const files = e.target.files;
            handleFilesLife(files);
            $(this).val(''); // Reset เพื่อให้ถ่ายใหม่ได้
        });

        // ฟังก์ชันจัดการไฟล์สำหรับ Life Modal
        function handleFilesLife(files) {
            if (files.length === 0) return;

            Array.from(files).forEach(file => {
                // 1. Validate Size (10MB)
                if (file.size > MAX_FILE_SIZE_MB * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ไฟล์ขนาดใหญ่เกินไป',
                        text: `ไฟล์ ${file.name} มีขนาดเกิน ${MAX_FILE_SIZE_MB}MB`,
                        timer: 3000
                    });
                    return;
                }

                // 2. Generate UI ID
                const fileId = Date.now() + Math.random().toString(16).slice(2);

                // 3. Create Uploading UI
                const uploadingHtml = `
                    <div class="progress-wrapper p-3 mb-2 fade-in" id="uploading_life_${fileId}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-truncate" style="max-width: 80%;">${file.name}</span>
                            <button type="button" class="btn-close btn-sm" aria-label="Cancel" onclick="cancelUploadLife('${fileId}')"></button>
                        </div>
                        <div class="custom-progress mb-2">
                            <div class="custom-progress-bar" id="bar_life_${fileId}"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center small text-muted">
                            <span id="percent_life_${fileId}">0% uploaded</span>
                            <span id="size_life_${fileId}">0.00 of ${(file.size / 1024 / 1024).toFixed(2)} MB</span>
                        </div>
                    </div>
                `;
                $('#uploading_container_life').append(uploadingHtml);

                // 4. Simulate Upload
                simulateUploadLife(file, fileId);
            });
        }

        // ฟังก์ชัน Simulate Upload สำหรับ Life Modal
        function simulateUploadLife(file, fileId) {
            const totalSize = file.size;
            const totalSizeMB = (totalSize / 1024 / 1024).toFixed(2);
            let loaded = 0;

            const interval = setInterval(() => {
                const chunk = Math.random() * (totalSize / 10);
                loaded += chunk;

                if (loaded >= totalSize) {
                    loaded = totalSize;
                    clearInterval(interval);
                    completeUploadLife(file, fileId);
                }

                const percent = Math.min(100, Math.round((loaded / totalSize) * 100));
                const loadedMB = (loaded / 1024 / 1024).toFixed(2);

                $(`#bar_life_${fileId}`).css('width', percent + '%');
                $(`#percent_life_${fileId}`).text(`${percent}% uploaded`);
                $(`#size_life_${fileId}`).text(`${loadedMB} of ${totalSizeMB} MB`);

            }, 100);

            $(`#uploading_life_${fileId}`).data('interval', interval);
        }

        // ฟังก์ชัน Complete Upload สำหรับ Life Modal
        function completeUploadLife(file, fileId) {
            // 1. Remove Uploading UI
            $(`#uploading_life_${fileId}`).fadeOut(300, function() {
                $(this).remove();
            });

            // บีบอัดรูปก่อนเก็บ (max 1920px, quality 0.7)
            compressImage(file, 1920, 1920, 0.7).then(function(compressedFile) {
                // 2. สร้าง objectURL สำหรับ preview
                var objectUrl = URL.createObjectURL(compressedFile);

                // 3. เก็บไฟล์ที่บีบอัดแล้ว — รวม name + src เพื่อ sync กับ PDF form
                var storeItem = {
                    file: compressedFile,
                    id: fileId,
                    name: file.name,
                    src: objectUrl
                };
                attachmentStoreLife.push(storeItem);

                // 4. อัปเดต Input
                updateRealInputLife();

                // 5. Prepare Data
                const fileSizeMB = (compressedFile.size / 1024 / 1024).toFixed(2);
                const date = new Date();
                const dateString = date.toLocaleDateString('en-GB', {
                        day: 'numeric',
                        month: 'short'
                    }) + ' ' +
                    date.toLocaleTimeString('en-GB', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                // 6. Create Grid Item
                const safeName = (file.name || 'photo').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
                const gridHtml = `
                    <div class="col fade-in" id="attach_life_${fileId}">
                        <div class="card attachment-card h-100">

                            <div class="card-actions-bar">
                                <button type="button" class="action-btn" title="ดูรูปภาพ"
                                onclick="showImagePreview('${objectUrl}', '${safeName}')">
                                <i class="far fa-eye" style="font-size: 0.8rem;"></i>
                                </button>

                                <button type="button" class="action-btn delete"
                                onclick="removeAttachmentLife('${fileId}')"
                                        title="ลบไฟล์นี้">
                                    <i class="fas fa-times" style="font-size: 0.85rem;"></i>
                                </button>
                            </div>

                            <div class="img-thumbnail-box">
                                <img src="${objectUrl}" alt="${safeName}">
                            </div>

                            <div class="card-body d-flex flex-column">
                                <div class="filename-text mb-auto" title="${safeName}">${safeName}</div>
                                <div class="d-flex justify-content-between align-items-end pt-2">
                                    <span class="small text-muted" style="font-size: 0.7rem;">
                                        <i class="far fa-clock me-1"></i>${dateString}
                                    </span>
                                    <span class="badge bg-light text-secondary border fw-normal" style="font-size: 0.7rem;">
                                        ${fileSizeMB} MB
                                    </span>
                                </div>
                            </div>

                        </div>
                    </div>
                `;

                $('#attachments_wrapper_life').removeClass('d-none');
                $('#attachments_grid_life').prepend(gridHtml);

                // ★ อัปเดต photo_id / amount ใน standard form
                var stdStore = window.attachmentStoreLife || attachmentStoreLife;
                var totalPhotos = stdStore.length;
                if (totalPhotos > 0) {
                    $('#photo_id_start_life').val(stdStore[0].name || 'photo');
                    $('#photo_id_end_life').val(stdStore[totalPhotos - 1].name || 'photo');
                    $('#photo_amount_life').val(totalPhotos);
                }

                // ★ Sync render ลง PDF form photo grid ด้วย
                if (typeof window.lpfRenderPhotosFromStore === 'function') {
                    window.lpfRenderPhotosFromStore();
                }
            }); // end compressImage.then
        }

        // ฟังก์ชัน Update Input สำหรับ Life Modal
        function updateRealInputLife() {
            const dataTransfer = new DataTransfer();

            attachmentStoreLife.forEach(item => {
                if (item.file) dataTransfer.items.add(item.file);
            });

            if (inputPhotosLife.length > 0) {
                inputPhotosLife[0].files = dataTransfer.files;
            }

            const count = attachmentStoreLife.length;
            $('#file_count_badge_life').text(count);
        }

        // Window Global Functions สำหรับ Life Modal
        window.cancelUploadLife = function(fileId) {
            const item = $(`#uploading_life_${fileId}`);
            clearInterval(item.data('interval'));
            item.fadeOut(300, () => item.remove());
        };

        window.removeAttachmentLife = function(fileId) {
            $(`#attach_life_${fileId}`).fadeOut(300, function() {
                $(this).remove();
                if ($('#attachments_grid_life').children().length === 0) {
                    $('#attachments_wrapper_life').addClass('d-none');
                }
            });
            attachmentStoreLife = attachmentStoreLife.filter(item => item.id !== fileId);

            updateRealInputLife();

            // ★ อัปเดต photo_id / amount
            var stdStore = window.attachmentStoreLife || attachmentStoreLife;
            var totalPhotos = stdStore.length;
            if (totalPhotos > 0) {
                $('#photo_id_start_life').val(stdStore[0].name || 'photo');
                $('#photo_id_end_life').val(stdStore[totalPhotos - 1].name || 'photo');
                $('#photo_amount_life').val(totalPhotos);
            } else {
                $('#photo_id_start_life').val('');
                $('#photo_id_end_life').val('');
                $('#photo_amount_life').val('');
            }

            // ★ Sync render ลง PDF form ด้วย
            if (typeof window.lpfRenderPhotosFromStore === 'function') {
                window.lpfRenderPhotosFromStore();
            }
        };

        // --- ลบรูปเดิมที่โหลดมาจาก DB (ชีวิต) ---
        // --- ฟังก์ชันดูรูปภาพเดิม (ชีวิต) → รองรับ BLOB file_id + base64 legacy ---
        window.showImagePreviewLife = function(photoIndex) {
            const photo = window.existingPhotosStoreLife[photoIndex];
            if (!photo) return;
            if (photo.file_id) {
                showImagePreview('./api/incidentCheckList/getFile.php?id=' + photo.file_id, 'photo');
            } else if (photo.base64) {
                showImagePreview(photo.base64, photo.fileName);
            }
        };

        window.removeExistingPhotoLife = function(identifier) {
            Swal.fire({
                title: 'ลบรูปภาพนี้?',
                text: 'รูปภาพจะถูกลบเมื่อกดบันทึก',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    if (typeof identifier === 'number') {
                        // BLOB: file_id
                        deletedExistingPhotosLife.push({
                            file_id: identifier
                        });
                        $(`.attachment-item[data-file-id="${identifier}"]`).fadeOut(300, function() {
                            $(this).remove();
                            if ($('#attachments_grid_life').children().length === 0) {
                                $('#attachments_wrapper_life').addClass('d-none');
                            }
                        });
                    } else {
                        // Legacy: filename
                        deletedExistingPhotosLife.push(identifier);
                        $(`.attachment-item[data-filename="${identifier}"]`).fadeOut(300, function() {
                            $(this).remove();
                            if ($('#attachments_grid_life').children().length === 0) {
                                $('#attachments_wrapper_life').addClass('d-none');
                            }
                        });
                    }
                    console.log('🗑️ Marked for deletion (Life):', identifier, 'Total:', deletedExistingPhotosLife);

                    // ★ Sync ลบจาก window.attachmentStoreLife ด้วย (existing photos)
                    if (typeof window.attachmentStoreLife !== 'undefined') {
                        if (typeof identifier === 'number') {
                            window.attachmentStoreLife = window.attachmentStoreLife.filter(function(x) {
                                return !(x.existing && x.db_file_id === identifier);
                            });
                        } else {
                            window.attachmentStoreLife = window.attachmentStoreLife.filter(function(x) {
                                return !(x.existing && x.disk_filename === identifier);
                            });
                        }
                    }

                    // ★ อัปเดต photo_id / amount
                    var stdStore = window.attachmentStoreLife || [];
                    var totalPhotos = stdStore.length;
                    if (totalPhotos > 0) {
                        $('#photo_id_start_life').val(stdStore[0].name || 'photo');
                        $('#photo_id_end_life').val(stdStore[totalPhotos - 1].name || 'photo');
                        $('#photo_amount_life').val(totalPhotos);
                    } else {
                        $('#photo_id_start_life').val('');
                        $('#photo_id_end_life').val('');
                        $('#photo_amount_life').val('');
                    }

                    // ★ Sync render ลง PDF form ด้วย
                    if (typeof window.lpfRenderPhotosFromStore === 'function') {
                        window.lpfRenderPhotosFromStore();
                    }
                }
            });
        };

        // ★ renderLifeAttachmentGrid — re-render standard form grid จาก window.attachmentStoreLife
        // ฟังก์ชันนี้ถูกเรียกจาก PDF form (modal_life_pdf_form.php) เมื่อมีการเพิ่ม/ลบรูป
        window.renderLifeAttachmentGrid = function() {
            var store = window.attachmentStoreLife || [];
            var grid = $('#attachments_grid_life');
            grid.empty();

            if (store.length === 0) {
                $('#attachments_wrapper_life').addClass('d-none');
                $('#file_count_badge_life').text(0);
                return;
            }

            $('#attachments_wrapper_life').removeClass('d-none');
            $('#file_count_badge_life').text(store.length);

            store.forEach(function(item, idx) {
                var safeName = (item.name || 'photo').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
                var imgSrc = item.src || '';
                var isExisting = item.existing || false;

                if (isExisting) {
                    // รูปเดิมจาก DB
                    var fileId = item.db_file_id || '';
                    var safeFilename = item.disk_filename || '';
                    var deleteArg = fileId ? fileId : "'" + safeFilename + "'";
                    var dataAttr = fileId ?
                        'data-file-id="' + fileId + '"' :
                        'data-file-id="saved_life_' + safeFilename + '" data-filename="' + safeFilename + '"';

                    var cardHtml =
                        '<div class="col attachment-item" ' + dataAttr + '>' +
                            '<div class="card attachment-card h-100">' +
                                '<div class="card-actions-bar">' +
                                    '<button type="button" class="action-btn" title="ดูรูปภาพ" onclick="showImagePreview(\'' + imgSrc + '\', \'' + safeName + '\')"><i class="far fa-eye" style="font-size:0.8rem;"></i></button>' +
                                    '<button type="button" class="action-btn delete" onclick="removeExistingPhotoLife(' + deleteArg + ')" title="ลบรูปนี้"><i class="fas fa-times" style="font-size:0.85rem;"></i></button>' +
                                '</div>' +
                                '<div class="img-thumbnail-box"><img src="' + imgSrc + '" alt="' + safeName + '"></div>' +
                                '<div class="card-body d-flex flex-column">' +
                                    '<div class="filename-text mb-auto" title="' + safeName + '">' + safeName + '</div>' +
                                    '<div class="pt-2"><span class="badge bg-info text-white fw-normal" style="font-size:0.65rem;">รูปเดิม</span></div>' +
                                '</div>' +
                            '</div>' +
                        '</div>';
                    grid.append(cardHtml);
                } else {
                    // รูปใหม่ที่ upload
                    var cardHtml =
                        '<div class="col fade-in" id="attach_life_' + item.id + '">' +
                            '<div class="card attachment-card h-100">' +
                                '<div class="card-actions-bar">' +
                                    '<button type="button" class="action-btn" title="ดูรูปภาพ" onclick="showImagePreview(\'' + imgSrc + '\', \'' + safeName + '\')"><i class="far fa-eye" style="font-size:0.8rem;"></i></button>' +
                                    '<button type="button" class="action-btn delete" onclick="removeAttachmentLife(\'' + item.id + '\')" title="ลบไฟล์นี้"><i class="fas fa-times" style="font-size:0.85rem;"></i></button>' +
                                '</div>' +
                                '<div class="img-thumbnail-box"><img src="' + imgSrc + '" alt="' + safeName + '"></div>' +
                                '<div class="card-body d-flex flex-column">' +
                                    '<div class="filename-text mb-auto" title="' + safeName + '">' + safeName + '</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>';
                    grid.append(cardHtml);
                }
            });

            // อัปเดต photo_id / amount
            var totalPhotos = store.length;
            if (totalPhotos > 0) {
                $('#photo_id_start_life').val(store[0].name || 'photo');
                $('#photo_id_end_life').val(store[totalPhotos - 1].name || 'photo');
                $('#photo_amount_life').val(totalPhotos);
            } else {
                $('#photo_id_start_life').val('');
                $('#photo_id_end_life').val('');
                $('#photo_amount_life').val('');
            }
        };

        // ★ ปุ่มลบรูปทั้งหมด (Life)
        $('#btn_clear_all_photos_life').on('click', function() {
            if (!window.attachmentStoreLife || window.attachmentStoreLife.length === 0) return;
            Swal.fire({
                title: 'ลบรูปภาพทั้งหมด?',
                text: 'รูปภาพทั้งหมดจะถูกลบเมื่อกดบันทึก',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ลบทั้งหมด',
                cancelButtonText: 'ยกเลิก'
            }).then(function(result) {
                if (result.isConfirmed) {
                    // Track existing photos for server-side deletion
                    window.attachmentStoreLife.forEach(function(item) {
                        if (item.existing && item.db_file_id) {
                            deletedExistingPhotosLife.push({ file_id: item.db_file_id });
                        } else if (item.existing && item.disk_filename) {
                            deletedExistingPhotosLife.push(item.disk_filename);
                        }
                    });
                    window.attachmentStoreLife.length = 0;
                    attachmentStoreLife.length = 0;
                    renderLifeAttachmentGrid();
                    updateRealInputLife();
                    if (typeof window.lpfRenderPhotosFromStore === 'function') {
                        window.lpfRenderPhotosFromStore();
                    }
                }
            });
        });

        // --- Camera & File Upload Logic สำหรับ modal_bomb ---
        // ปุ่มเลือกไฟล์จาก Gallery (Bomb Modal)
        $('#btn_choose_file_bomb').on('click', function() {
            inputPhotosBomb.trigger('click');
        });

        // ปุ่มเปิดกล้องถ่ายภาพ (Bomb Modal)
        $('#btn_open_camera_bomb').on('click', function() {
            cameraInputBomb.trigger('click');
        });

        // ★ หมายเหตุ: การจัดการไฟล์ (change event) ของ #incident_photos_bomb / #camera_input_bomb
        //   ถูกจัดการโดย System เดียวใน modals/modal_bomb.php (renderAttachmentStoreBomb แบบ Life)
        //   จึงไม่ผูก handleFilesBomb ซ้ำที่นี่ เพื่อป้องกันรูปถูกเพิ่มซ้ำ/shape ไม่ตรงกัน

        // ฟังก์ชันจัดการไฟล์สำหรับ Bomb Modal
        function handleFilesBomb(files) {
            if (files.length === 0) return;

            Array.from(files).forEach(file => {
                // 1. Validate Size (10MB)
                if (file.size > MAX_FILE_SIZE_MB * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ไฟล์ขนาดใหญ่เกินไป',
                        text: `ไฟล์ ${file.name} มีขนาดเกิน ${MAX_FILE_SIZE_MB}MB`,
                        timer: 3000
                    });
                    return;
                }

                // 2. Generate UI ID
                const fileId = Date.now() + Math.random().toString(16).slice(2);

                // 3. Create Uploading UI
                const uploadingHtml = `
                    <div class="progress-wrapper p-3 mb-2 fade-in" id="uploading_bomb_${fileId}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-truncate" style="max-width: 80%;">${file.name}</span>
                            <button type="button" class="btn-close btn-sm" aria-label="Cancel" onclick="cancelUploadBomb('${fileId}')"></button>
                        </div>
                        <div class="custom-progress mb-2">
                            <div class="custom-progress-bar" id="bar_bomb_${fileId}"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center small text-muted">
                            <span id="percent_bomb_${fileId}">0% uploaded</span>
                            <span id="size_bomb_${fileId}">0.00 of ${(file.size / 1024 / 1024).toFixed(2)} MB</span>
                        </div>
                    </div>
                `;
                $('#uploading_container_bomb').append(uploadingHtml);

                // 4. Simulate Upload
                simulateUploadBomb(file, fileId);
            });
        }

        // ฟังก์ชัน Simulate Upload สำหรับ Bomb Modal
        function simulateUploadBomb(file, fileId) {
            const totalSize = file.size;
            const totalSizeMB = (totalSize / 1024 / 1024).toFixed(2);
            let loaded = 0;

            const interval = setInterval(() => {
                const chunk = Math.random() * (totalSize / 10);
                loaded += chunk;

                if (loaded >= totalSize) {
                    loaded = totalSize;
                    clearInterval(interval);
                    completeUploadBomb(file, fileId);
                }

                const percent = Math.min(100, Math.round((loaded / totalSize) * 100));
                const loadedMB = (loaded / 1024 / 1024).toFixed(2);

                $(`#bar_bomb_${fileId}`).css('width', percent + '%');
                $(`#percent_bomb_${fileId}`).text(`${percent}% uploaded`);
                $(`#size_bomb_${fileId}`).text(`${loadedMB} of ${totalSizeMB} MB`);

            }, 100);

            $(`#uploading_bomb_${fileId}`).data('interval', interval);
        }

        // ฟังก์ชัน Complete Upload สำหรับ Bomb Modal
        function completeUploadBomb(file, fileId) {
            // 1. Remove Uploading UI
            $(`#uploading_bomb_${fileId}`).fadeOut(300, function() {
                $(this).remove();
            });

            // 2. เก็บไฟล์
            attachmentStoreBomb.push({
                file: file,
                id: fileId
            });

            // 3. อัปเดต Input
            updateRealInputBomb();

            // 4. Prepare Data
            const fileSizeMB = (file.size / 1024 / 1024).toFixed(2);
            const date = new Date();
            const dateString = date.toLocaleDateString('en-GB', {
                    day: 'numeric',
                    month: 'short'
                }) + ' ' +
                date.toLocaleTimeString('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

            // 5. Create Grid Item
            const reader = new FileReader();
            reader.onload = function(e) {
                const gridHtml = `
                    <div class="col fade-in" id="attach_bomb_${fileId}">
                        <div class="card attachment-card h-100">

                            <div class="card-actions-bar">
                                <button type="button" class="action-btn" title="ดูรูปภาพ"
                                onclick="showImagePreview('${e.target.result}', '${file.name}')">
                                <i class="far fa-eye" style="font-size: 0.8rem;"></i>
                                </button>

                                <button type="button" class="action-btn delete"
                                onclick="removeAttachmentBomb('${fileId}')"
                                        title="ลบไฟล์นี้">
                                    <i class="fas fa-times" style="font-size: 0.85rem;"></i>
                                </button>
                            </div>

                            <div class="img-thumbnail-box">
                                <img src="${e.target.result}" alt="${file.name}">
                            </div>

                            <div class="card-body d-flex flex-column">
                                <div class="filename-text mb-auto" title="${file.name}">${file.name}</div>
                                <div class="d-flex justify-content-between align-items-end pt-2">
                                    <span class="small text-muted" style="font-size: 0.7rem;">
                                        <i class="far fa-clock me-1"></i>${dateString}
                                    </span>
                                    <span class="badge bg-light text-secondary border fw-normal" style="font-size: 0.7rem;">
                                        ${fileSizeMB} MB
                                    </span>
                                </div>
                            </div>

                        </div>
                    </div>
                `;

                $('#attachments_wrapper_bomb').removeClass('d-none');
                $('#attachments_grid_bomb').prepend(gridHtml);
            };
            reader.readAsDataURL(file);
        }

        // ฟังก์ชัน Update Input สำหรับ Bomb Modal
        function updateRealInputBomb() {
            const dataTransfer = new DataTransfer();

            attachmentStoreBomb.forEach(item => {
                dataTransfer.items.add(item.file);
            });

            if (inputPhotosBomb.length > 0) {
                inputPhotosBomb[0].files = dataTransfer.files;
            }

            const count = attachmentStoreBomb.length;
            $('#file_count_badge_bomb').text(count);
        }

        // Window Global Functions สำหรับ Bomb Modal
        window.cancelUploadBomb = function(fileId) {
            const item = $(`#uploading_bomb_${fileId}`);
            clearInterval(item.data('interval'));
            item.fadeOut(300, () => item.remove());
        };

        window.removeAttachmentBomb = function(fileId) {
            $(`#attach_bomb_${fileId}`).fadeOut(300, function() {
                $(this).remove();
                if ($('#attachments_grid_bomb').children().length === 0) {
                    $('#attachments_wrapper_bomb').addClass('d-none');
                }
            });
            attachmentStoreBomb = attachmentStoreBomb.filter(item => item.id !== fileId);
            updateRealInputBomb();
        };

        // --- Camera & File Upload Logic สำหรับ modal_traffic ---

        // ปุ่มเลือกไฟล์จาก Gallery (Traffic Modal)
        $('#btn_choose_file_traffic').on('click', function() {
            inputPhotosTraffic.trigger('click');
        });

        // ปุ่มเปิดกล้องถ่ายภาพ (Traffic Modal)
        $('#btn_open_camera_traffic').on('click', function() {
            cameraInputTraffic.trigger('click');
        });

        // ★ ตัด change handler ซ้ำซ้อนออก — modal_traffic.php จัดการ change ของ
        //   #incident_photos_traffic / #camera_input_traffic เป็นตัวเดียว (ระบบรูปรวม)
        //   เหลือไว้แค่ปุ่มเลือกไฟล์/กล้องด้านบนที่ trigger input

        // ฟังก์ชันจัดการไฟล์สำหรับ Traffic Modal
        function handleFilesTraffic(files) {
            if (files.length === 0) return;

            Array.from(files).forEach(file => {
                // 1. Validate Size (10MB)
                if (file.size > MAX_FILE_SIZE_MB * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'ไฟล์ขนาดใหญ่เกินไป',
                        text: `ไฟล์ ${file.name} มีขนาดเกิน ${MAX_FILE_SIZE_MB}MB`,
                        timer: 3000
                    });
                    return;
                }

                // 2. Generate UI ID
                const fileId = Date.now() + Math.random().toString(16).slice(2);

                // 3. Create Uploading UI
                const uploadingHtml = `
                    <div class="progress-wrapper p-3 mb-2 fade-in" id="uploading_traffic_${fileId}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-truncate" style="max-width: 80%;">${file.name}</span>
                            <button type="button" class="btn-close btn-sm" aria-label="Cancel" onclick="cancelUploadTraffic('${fileId}')"></button>
                        </div>
                        <div class="custom-progress mb-2">
                            <div class="custom-progress-bar" id="bar_traffic_${fileId}"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center small text-muted">
                            <span id="percent_traffic_${fileId}">0% uploaded</span>
                            <span id="size_traffic_${fileId}">0.00 of ${(file.size / 1024 / 1024).toFixed(2)} MB</span>
                        </div>
                    </div>
                `;
                $('#uploading_container_traffic').append(uploadingHtml);

                // 4. Simulate Upload
                simulateUploadTraffic(file, fileId);
            });
        }

        // ฟังก์ชัน Simulate Upload สำหรับ Traffic Modal
        function simulateUploadTraffic(file, fileId) {
            const totalSize = file.size;
            const totalSizeMB = (totalSize / 1024 / 1024).toFixed(2);
            let loaded = 0;

            const interval = setInterval(() => {
                const chunk = Math.random() * (totalSize / 10);
                loaded += chunk;

                if (loaded >= totalSize) {
                    loaded = totalSize;
                    clearInterval(interval);
                    completeUploadTraffic(file, fileId);
                }

                const percent = Math.min(100, Math.round((loaded / totalSize) * 100));
                const loadedMB = (loaded / 1024 / 1024).toFixed(2);

                $(`#bar_traffic_${fileId}`).css('width', percent + '%');
                $(`#percent_traffic_${fileId}`).text(`${percent}% uploaded`);
                $(`#size_traffic_${fileId}`).text(`${loadedMB} of ${totalSizeMB} MB`);

            }, 100);

            $(`#uploading_traffic_${fileId}`).data('interval', interval);
        }

        // ฟังก์ชัน Complete Upload สำหรับ Traffic Modal
        function completeUploadTraffic(file, fileId) {
            // 1. Remove Uploading UI
            $(`#uploading_traffic_${fileId}`).fadeOut(300, function() {
                $(this).remove();
            });

            // 2. เก็บไฟล์
            attachmentStoreTraffic.push({
                file: file,
                id: fileId
            });

            // 3. อัปเดต Input
            updateRealInputTraffic();

            // 4. Prepare Data
            const fileSizeMB = (file.size / 1024 / 1024).toFixed(2);
            const date = new Date();
            const dateString = date.toLocaleDateString('en-GB', {
                    day: 'numeric',
                    month: 'short'
                }) + ' ' +
                date.toLocaleTimeString('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

            // 5. Create Grid Item
            const reader = new FileReader();
            reader.onload = function(e) {
                const gridHtml = `
                    <div class="col fade-in" id="attach_traffic_${fileId}">
                        <div class="card attachment-card h-100">

                            <div class="card-actions-bar">
                                <button type="button" class="action-btn" title="ดูรูปภาพ"
                                onclick="showImagePreview('${e.target.result}', '${file.name}')">
                                <i class="far fa-eye" style="font-size: 0.8rem;"></i>
                                </button>

                                <button type="button" class="action-btn delete"
                                onclick="removeAttachmentTraffic('${fileId}')"
                                        title="ลบไฟล์นี้">
                                    <i class="fas fa-times" style="font-size: 0.85rem;"></i>
                                </button>
                            </div>

                            <div class="img-thumbnail-box">
                                <img src="${e.target.result}" alt="${file.name}">
                            </div>

                            <div class="card-body d-flex flex-column">
                                <div class="filename-text mb-auto" title="${file.name}">${file.name}</div>
                                <div class="d-flex justify-content-between align-items-end pt-2">
                                    <span class="small text-muted" style="font-size: 0.7rem;">
                                        <i class="far fa-clock me-1"></i>${dateString}
                                    </span>
                                    <span class="badge bg-light text-secondary border fw-normal" style="font-size: 0.7rem;">
                                        ${fileSizeMB} MB
                                    </span>
                                </div>
                            </div>

                        </div>
                    </div>
                `;

                $('#attachments_wrapper_traffic').removeClass('d-none');
                $('#attachments_grid_traffic').prepend(gridHtml);
            };
            reader.readAsDataURL(file);
        }

        // ฟังก์ชัน Update Input สำหรับ Traffic Modal
        function updateRealInputTraffic() {
            const dataTransfer = new DataTransfer();

            attachmentStoreTraffic.forEach(item => {
                dataTransfer.items.add(item.file);
            });

            if (inputPhotosTraffic.length > 0) {
                inputPhotosTraffic[0].files = dataTransfer.files;
            }

            const count = attachmentStoreTraffic.length;
            $('#file_count_badge_traffic').text(count);
        }

        // Window Global Functions สำหรับ Traffic Modal
        window.cancelUploadTraffic = function(fileId) {
            const item = $(`#uploading_traffic_${fileId}`);
            clearInterval(item.data('interval'));
            item.fadeOut(300, () => item.remove());
        };

        window.removeAttachmentTraffic = function(fileId) {
            $(`#attach_traffic_${fileId}`).fadeOut(300, function() {
                $(this).remove();
                if ($('#attachments_grid_traffic').children().length === 0) {
                    $('#attachments_wrapper_traffic').addClass('d-none');
                }
            });
            attachmentStoreTraffic = attachmentStoreTraffic.filter(item => item.id !== fileId);
            updateRealInputTraffic();
        };

        // --- Event Handlers สำหรับ Life Modal ---
        // จัดการ checkbox ผู้เสียชีวิต - บังคับกรอกข้อมูลเมื่อติ๊ก
        $('#life_victim_dead').on('change', function() {
            const isChecked = $(this).is(':checked');
            const nameInput = $('input[name="dead_name"]');
            const ageInput = $('input[name="dead_age"]');

            if (isChecked) {
                nameInput.prop('required', true).prop('disabled', false);
                ageInput.prop('required', true).prop('disabled', false);
                nameInput.focus();
            } else {
                nameInput.prop('required', false).prop('disabled', true).val('');
                ageInput.prop('required', false).prop('disabled', true).val('');
            }
        });

        // จัดการ checkbox ผู้บาดเจ็บ - บังคับกรอกข้อมูลเมื่อติ๊ก
        $('#life_victim_injured').on('change', function() {
            const isChecked = $(this).is(':checked');
            const nameInput = $('input[name="injured_name"]');
            const ageInput = $('input[name="injured_age"]');

            if (isChecked) {
                nameInput.prop('required', true).prop('disabled', false);
                ageInput.prop('required', true).prop('disabled', false);
                nameInput.focus();
            } else {
                nameInput.prop('required', false).prop('disabled', true).val('');
                ageInput.prop('required', false).prop('disabled', true).val('');
            }
        });

        // จัดการ checkbox ผู้สูญหาย - บังคับกรอกข้อมูลเมื่อติ๊ก
        $('#life_victim_missing').on('change', function() {
            const isChecked = $(this).is(':checked');
            const nameInput = $('input[name="missing_name"]');

            if (isChecked) {
                nameInput.prop('required', true).prop('disabled', false).focus();
            } else {
                nameInput.prop('required', false).prop('disabled', true).val('');
            }
        });

        // จัดการ Radio-like checkboxes สำหรับทุก Modal (เลือกได้ 1 อย่างในกลุ่มเดียวกัน)
        $(document).on('change', '.life-radio-toggle', function() {
            if ($(this).is(':checked')) {
                const group = $(this).data('group');
                $(`.life-radio-toggle[data-group="${group}"]`).not(this).prop('checked', false);
            }
        });

        // --- Custom Validation สำหรับ Life Modal (เหลือแค่ตัวเลข) ---
        window.validateLifeModal = function() {
            let errors = [];

            // ตรวจสอบหมายเลขโทรศัพท์ (ถ้ากรอกต้องมี 10 หลัก)
            const phone = $('input[name="investigator_phone"]').val();
            if (phone) {
                const phoneDigits = phone.replace(/\D/g, '');
                if (phoneDigits.length !== 10) {
                    errors.push('กรุณากรอกหมายเลขโทรศัพท์ให้ครบ 10 หลัก');
                }
            }

            return errors;
        };

        // --- ฟังก์ชัน Validation และรวบรวมข้อมูลสำหรับ Life Modal ---
        window.prepareDataForSubmissionLife = function() {
            // ★ ลองหาฟอร์มมาตรฐานก่อน ถ้าไม่เจอให้ใช้ฟอร์ม PDF แทน
            let form = document.getElementById('incidentCheckListFormLife');
            let usingPdfForm = false;
            if (!form) {
                form = document.getElementById('lifeFormPdf');
                usingPdfForm = true;
                console.log('[prepareDataForSubmissionLife] Using PDF form as fallback');
            }
            if (!form) {
                console.error('[prepareDataForSubmissionLife] No form found');
                Swal.fire('ผิดพลาด', 'ไม่พบฟอร์ม กรุณาลองใหม่', 'error');
                return;
            }

            // ★ ถ้าใช้ PDF form ให้ sync ข้อมูลก่อน
            if (usingPdfForm) {
                if (typeof syncLifeFormData === 'function') {
                    syncLifeFormData('lifeFormPdf', 'incidentCheckListFormLife');
                }
            }

            function normalizeLifeIndexedFieldsBeforeSave() {
                // Evidence card checkboxes (level 1-4) ต้องเรียง index ต่อเนื่อง 0..n-1
                $('#evidence_container_life .evidence-card-life').each(function(idx) {
                    const $card = $(this);
                    $card.find('input[name^="evidence_level_1_life_"]').attr('name', 'evidence_level_1_life_' + idx);
                    $card.find('input[name^="evidence_level_2_life_"]').attr('name', 'evidence_level_2_life_' + idx);
                    $card.find('input[name^="evidence_level_3_life_"]').attr('name', 'evidence_level_3_life_' + idx);
                    $card.find('input[name^="evidence_level_4_life_"]').attr('name', 'evidence_level_4_life_' + idx);
                });

                // Measurement card checkboxes + detail text ต้องเรียง index ต่อเนื่อง
                $('#measurement_container_life .measurement-card-life').each(function(idx) {
                    const $card = $(this);
                    $card.find('input[name^="measurement_package_plastic_check_"]').attr('name', 'measurement_package_plastic_check_' + idx);
                    $card.find('input[name^="measurement_package_plastic_text_"]').attr('name', 'measurement_package_plastic_text_' + idx);
                    $card.find('input[name^="measurement_package_paper_check_"]').attr('name', 'measurement_package_paper_check_' + idx);
                    $card.find('input[name^="measurement_package_paper_text_"]').attr('name', 'measurement_package_paper_text_' + idx);
                    $card.find('input[name^="measurement_package_other_check_"]').attr('name', 'measurement_package_other_check_' + idx);
                    $card.find('input[name^="measurement_package_other_text_"]').attr('name', 'measurement_package_other_text_' + idx);
                    $card.find('input[name^="measurement_action_return_check_"]').attr('name', 'measurement_action_return_check_' + idx);
                    $card.find('input[name^="measurement_action_return_text_"]').attr('name', 'measurement_action_return_text_' + idx);
                    $card.find('input[name^="measurement_action_other_check_"]').attr('name', 'measurement_action_other_check_' + idx);
                    $card.find('input[name^="measurement_action_other_text_"]').attr('name', 'measurement_action_other_text_' + idx);
                });
            }

            // 1. Custom Validation — เหลือแค่ตรวจเบอร์โทร (ถ้ากรอก ต้อง 10 หลัก)
            const customErrors = validateLifeModal();
            if (customErrors.length > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ถูกต้อง',
                    html: customErrors.map((e, i) => `${i + 1}. ${e}`).join('<br>'),
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#0d6efd'
                });
                return;
            }

            // 2. เก็บ body_diagram_strokes สำหรับโหลดกลับตอน edit
            const bodyDiagramPadLife = (typeof signaturePads !== 'undefined') ? signaturePads['body_diagram_canvas_life'] : null;
            if (bodyDiagramPadLife && !bodyDiagramPadLife.isEmpty()) {
                $('#body_diagram_strokes_life').val(JSON.stringify(bodyDiagramPadLife.toData()));
            }

            // 3. ยืนยันก่อนบันทึก
            Swal.fire({
                title: 'ยืนยันการบันทึกข้อมูล',
                text: "กรุณาตรวจสอบความถูกต้องก่อนบันทึก",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ยืนยัน, บันทึกเลย!',
                cancelButtonText: 'ยกเลิก',
            }).then(async (result) => {
                if (result.isConfirmed) {

                    // 4. แสดง Loading
                    Swal.fire({
                        title: 'กำลังบันทึกข้อมูล...',
                        html: 'กรุณารอสักครู่',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // 5. Build FormData
                    normalizeLifeIndexedFieldsBeforeSave();
                    const formData = new FormData(form);

                    // ลบ base64 hidden inputs ออก (จะส่งเป็น Blob file แทน)
                    formData.delete('receiver_signature_data_life');
                    formData.delete('sender_signature_data_life');
                    formData.delete('scene_sketch_data_life');
                    formData.delete('body_diagram_data_life');

                    // 6. ★ ส่ง signatures / sketch เป็น Blob file (BLOB-based เหมือน Property)
                    const preferPdf = !!window._savingFromLifePdfForm;
                    window._savingFromLifePdfForm = false;

                    const sigCanvasMap = {
                        'scene_sketch': preferPdf ?
                            ['lpf_scene_sketch_canvas', 'scene_sketch_canvas_life'] :
                            ['scene_sketch_canvas_life', 'lpf_scene_sketch_canvas'],
                        'receiver_signature': preferPdf ?
                            ['lpf_sig_receiver', 'sig-canvas-receiver-life'] :
                            ['sig-canvas-receiver-life', 'lpf_sig_receiver'],
                        'sender_signature': preferPdf ?
                            ['lpf_sig_sender', 'sig-canvas-sender-life'] :
                            ['sig-canvas-sender-life', 'lpf_sig_sender'],
                        'body_diagram': preferPdf ?
                            ['lpf_body_diagram_canvas', 'body_diagram_canvas_life'] :
                            ['body_diagram_canvas_life', 'lpf_body_diagram_canvas']
                    };

                    const clearedSignatures = [];
                    for (const [key, canvasIds] of Object.entries(sigCanvasMap)) {
                        let blobSent = false;
                        for (const canvasId of canvasIds) {
                            if (blobSent) break;

                            try {
                                // Body diagram ต้อง composite กับรูปพื้นหลัง
                                if (key === 'body_diagram') {
                                    const pad = (typeof signaturePads !== 'undefined') ? signaturePads[canvasId] : null;
                                    if (pad && !pad.isEmpty()) {
                                        const cvs = document.getElementById(canvasId);
                                        const backgroundImg = cvs ? cvs.parentElement.querySelector('img[src*="body_diagram"]') : null;

                                        let targetCanvas = cvs;
                                        if (backgroundImg && backgroundImg.naturalWidth > 0) {
                                            const tempCanvas = document.createElement('canvas');
                                            tempCanvas.width = cvs.width;
                                            tempCanvas.height = cvs.height;
                                            const tempCtx = tempCanvas.getContext('2d');
                                            tempCtx.fillStyle = '#FFFFFF';
                                            tempCtx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
                                            const imgW = backgroundImg.naturalWidth;
                                            const imgH = backgroundImg.naturalHeight;
                                            const canW = tempCanvas.width;
                                            const canH = tempCanvas.height;
                                            const scale = Math.min(canW / imgW, canH / imgH);
                                            const drawW = imgW * scale;
                                            const drawH = imgH * scale;
                                            const drawX = (canW - drawW) / 2;
                                            const drawY = (canH - drawH) / 2;
                                            tempCtx.drawImage(backgroundImg, drawX, drawY, drawW, drawH);
                                            tempCtx.drawImage(cvs, 0, 0);
                                            targetCanvas = tempCanvas;
                                        }

                                        if (targetCanvas) {
                                            const blob = await canvasToBlob(targetCanvas, 'image/png');
                                            if (blob) {
                                                formData.append('sig_file_' + key, blob, key + '.png');
                                                blobSent = true;
                                            }
                                        }
                                    }
                                    continue;
                                }

                                // Normal signature/sketch canvases
                                const padNormal = (typeof signaturePads !== 'undefined') ? signaturePads[canvasId] : null;
                                if (padNormal && !padNormal.isEmpty()) {
                                    const cvs = document.getElementById(canvasId);
                                    if (cvs) {
                                        const blob = await canvasToBlob(cvs, 'image/png');
                                        if (blob) {
                                            formData.append('sig_file_' + key, blob, key + '.png');
                                            blobSent = true;
                                        }
                                    }
                                } else {
                                    // Fallback: raw canvas (PDF form canvas อาจไม่มี SignaturePad)
                                    const cvs = document.getElementById(canvasId);
                                    if (cvs && cvs.width > 0 && cvs.height > 0) {
                                        const ctx = cvs.getContext('2d');
                                        const pixelData = ctx.getImageData(0, 0, cvs.width, cvs.height).data;
                                        let hasContent = false;
                                        for (let pi = 3; pi < pixelData.length; pi += 4) {
                                            if (pixelData[pi] > 0) {
                                                hasContent = true;
                                                break;
                                            }
                                        }
                                        if (hasContent) {
                                            const blob = await canvasToBlob(cvs, 'image/png');
                                            if (blob) {
                                                formData.append('sig_file_' + key, blob, key + '.png');
                                                blobSent = true;
                                            }
                                        }
                                    }
                                }
                            } catch (canvasErr) {
                                console.warn('[prepareDataForSubmissionLife] Canvas error for', canvasId, canvasErr);
                            }
                        }
                        // ★ ถ้าไม่มี blob ส่ง = canvas ถูกล้าง → แจ้ง API ให้ลบรูปเก่า
                        if (!blobSent) {
                            clearedSignatures.push(key);
                        }
                    }
                    if (clearedSignatures.length > 0) {
                        formData.append('cleared_signatures', JSON.stringify(clearedSignatures));
                    }

                    // 7. ★ Deleted photos (แยก BLOB file_id กับ filename legacy)
                    formData.delete('deleted_photos');
                    const deletedFileIds = [];
                    const deletedFilenames = [];
                    if (deletedExistingPhotosLife.length > 0) {
                        deletedExistingPhotosLife.forEach(function(item) {
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

                    // 8. Photos จาก attachmentStoreLife
                    formData.delete('incident_photos_life[]');
                    formData.delete('camera_photos_life[]');

                    if (typeof attachmentStoreLife !== 'undefined' && attachmentStoreLife.length > 0) {
                        attachmentStoreLife.forEach((item, index) => {
                            if (item.file) {
                                formData.append('incident_photos_life[]', item.file, item.file.name || `photo_${index + 1}.jpg`);
                            } else if (item.base64) {
                                const byteString = atob(item.base64.split(',')[1]);
                                const mimeString = item.base64.split(',')[0].split(':')[1].split(';')[0];
                                const ab = new ArrayBuffer(byteString.length);
                                const ia = new Uint8Array(ab);
                                for (let i = 0; i < byteString.length; i++) {
                                    ia[i] = byteString.charCodeAt(i);
                                }
                                const blob = new Blob([ab], {
                                    type: mimeString
                                });
                                formData.append('incident_photos_life[]', blob, item.filename || `photo_${index + 1}.jpg`);
                            }
                        });
                    }

                    // 9. AJAX Submit
                    // ★ เช็คเน็ตก่อนส่ง (Offline Mode)
                    const lifeUrl = form.action;
                    if (!navigator.onLine) {
                        await saveChecklistOffline(formData, lifeUrl, '#addCheckListModalLife');
                        return;
                    }
                    const backendOkLife = await checkBackendHealth();
                    if (!backendOkLife) {
                        await saveChecklistOffline(formData, lifeUrl, '#addCheckListModalLife');
                        return;
                    }

                    $.ajax({
                        url: lifeUrl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            Swal.close();

                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ',
                                    text: response.message || 'บันทึกข้อมูลเรียบร้อย',
                                    confirmButtonText: 'ตกลง'
                                }).then(() => {
                                    ['addCheckListModalLife', 'lifeFormPdfModal'].forEach(id => {
                                        const el = document.getElementById(id);
                                        if (el) {
                                            const m = bootstrap.Modal.getInstance(el);
                                            if (m) m.hide();
                                        }
                                    });
                                    if (typeof resetLifeForm === 'function') resetLifeForm();
                                    if (typeof loadData === 'function') loadData();
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
                        error: async function(xhr, status, error) {
                            Swal.close();
                            console.error('Error:', error);
                            // ★ ส่งไม่ได้ → fallback Offline
                            await saveChecklistOffline(formData, lifeUrl, '#addCheckListModalLife');
                        }
                    });

                }
            });
        };

        // 1. กดปุ่มเพิ่มวัตถุพยาน
        $('#btn_add_evidence').on('click', function() {
            const count = $('#evidence_container .evidence-card').length + 1;

            // Create & Append
            const htmlString = createEvidenceCard(count);
            const $newCard = $(htmlString);
            $('#evidence_container').append($newCard);

            // Auto Focus: ไปที่ Dropdown "ประเภทวัตถุพยาน" (เพราะเลขรัน Auto, ผู้ใช้ต้องเริ่มเลือกประเภทก่อน)
            setTimeout(() => {
                const typeSelect = $newCard.find('select[name*="[type]"]');
                typeSelect.focus(); // หรือจะ .click() เพื่อเปิด dropdown เลยก็ได้ในบาง browser

                // Scroll ลงมาหา
                $('html, body').animate({
                    scrollTop: $newCard.offset().top - 100
                }, 500);
            }, 50);
        });

        // 2. กดปุ่มลบ
        $(document).on('click', '.remove-evidence-btn', function() {
            $(this).closest('.evidence-card').remove();
            reIndexEvidence();
        });

        // กดปุ่ม ESC หรือคลิกพื้นหลังเพื่อปิด
        $(document).keydown(function(e) {
            if (e.key === "Escape") closeLightbox();
        });

        // คลิกที่ว่างๆ รอบรูปเพื่อปิด
        $('#customLightbox').on('click', function(e) {
            if (e.target === this || $(e.target).hasClass('lightbox-content')) {
                closeLightbox();
            }
        });

        // Case Type / Report Channel
        // --- 1. จัดการช่อง "ประเภทคดี" (Case Type) ---
        $('#case_type').on('change', function(e) {
            const otherDiv = $('#case_type_other_div');
            const otherInput = $('#case_type_other');

            if ($(this).val() == 'other') {
                // แสดงช่องกรอก และ บังคับกรอก
                otherDiv.removeClass('d-none');
                otherInput.prop('required', true);
                otherInput.focus();
            } else {
                // ซ่อนช่องกรอก, เลิกบังคับ, และล้างค่า
                otherDiv.addClass('d-none');
                otherInput.prop('required', false);
                otherInput.val('');
            }
        });

        // --- 2. จัดการช่อง "ช่องทางที่รับแจ้ง" (Report Channel) ---
        $('#report_channel').on('change', function(e) {
            const otherDiv = $('#report_channel_other_div');
            const otherInput = $('#report_channel_other');

            if ($(this).val() == 'other') {
                otherDiv.removeClass('d-none');
                otherInput.prop('required', true);
                otherInput.focus();
            } else {
                otherDiv.addClass('d-none');
                otherInput.prop('required', false);
                otherInput.val('');
            }
        });

        $('.victim-type-check').on('change', function() {
            // 1. ถ้าติ๊กเลือกอันนี้ ให้ไปเอาติ๊กออกจากอันอื่นทั้งหมดในกลุ่มเดียวกัน
            if ($(this).is(':checked')) {
                $('.victim-type-check').not(this).prop('checked', false);
            }

            // 2. เช็คว่าตัวที่ติ๊กอยู่คือ "อื่นๆ" (id="victim_type_other_cb") หรือไม่
            // หมายเหตุ: ต้องเช็คสถานะ checked ของตัว Other โดยตรง เพราะอาจจะถูก uncheck จากข้อ 1 ได้
            const isOtherChecked = $('#victim_type_other_cb').is(':checked');
            const otherDiv = $('#victim_type_other_div');
            const otherInput = $('#victim_type_other_text');

            if (isOtherChecked) {
                otherDiv.removeClass('d-none');
                otherInput.prop('disabled', false).prop('required', true);
                setTimeout(() => otherInput.focus(), 100);
            } else {
                otherDiv.addClass('d-none');
                otherInput.prop('disabled', true).prop('required', false).val('');
            }
        });

        // --- จัดการ Checkbox ที่มี Input ต่อท้าย (ประตู, หน้าต่าง, ร่องรอยอื่นๆ) ---
        $('.entry-check-toggle').on('change', function() {
            // หา Input Group หรือ Div ที่อยู่พี่น้องถัดไป
            const inputContainer = $(this).closest('.d-flex').find('.entry-input-group, .entry-input-div');
            const inputField = inputContainer.find('input[type="text"]');

            if ($(this).is(':checked')) {
                inputContainer.removeClass('d-none');
                inputField.prop('disabled', false).prop('required', true).focus();
            } else {
                inputContainer.addClass('d-none');
                inputField.prop('disabled', true).prop('required', false).val('');
            }
        });

        // --- 1. Logic: ถ้าเลือก "ไม่พบร่องรอย" (No Trace) ---
        $('#entry_no_trace').on('change', function() {
            if ($(this).is(':checked')) {
                // Uncheck รายการอื่นๆ ทั้งหมด (เพิ่ม .entry-main-check เข้าไปในกลุ่มด้วย)
                $('#entry_found_trace, #entry_unlocked, #entry_pry, #entry_cut, #entry_drill, #entry_other_trace').prop('checked', false).trigger('change');

                // Uncheck กลุ่ม Location
                $('.entry-check-toggle').prop('checked', false).trigger('change');
            }
        });

        // --- 2. Logic: ถ้าเลือกรายการอื่นๆ -> เอา "ไม่พบร่องรอย" ออก ---
        // รวม Selector ทุกตัวที่เกี่ยวข้อง (.entry-main-check, .entry-sub-check)
        $('.entry-main-check, .entry-sub-check, .entry-check-toggle').not('#entry_no_trace').on('change', function() {
            if ($(this).is(':checked')) {
                $('#entry_no_trace').prop('checked', false);
            }
        });

        // --- เครื่องมือที่คนร้ายใช้ในการโจรกรรม (Toggle Input) ---
        $('.tool-check-toggle').on('change', function() {
            const inputGroup = $(this).closest('.d-flex').find('.tool-input-group');
            const inputField = inputGroup.find('input');

            if ($(this).is(':checked')) {
                inputGroup.removeClass('d-none');
                inputField.prop('required', true).focus();
            } else {
                inputGroup.addClass('d-none');
                inputField.prop('required', false).val('');
            }
        });

        // --- อาวุธ (Toggle Input + Logic No Weapon) ---
        // Toggle Other Weapon
        $('.weapon-check-toggle').on('change', function() {
            const inputGroup = $(this).closest('.d-flex').find('.weapon-input-group');
            const inputField = inputGroup.find('input');
            if ($(this).is(':checked')) {
                inputGroup.removeClass('d-none');
                inputField.prop('required', true).focus();
            } else {
                inputGroup.addClass('d-none');
                inputField.prop('required', false).val('');
            }
        });

        // Logic: ไม่ใช้อาวุธ vs ใช้อาวุธ
        $('#weapon_none').on('change', function() {
            if ($(this).is(':checked')) {
                // Uncheck อาวุธอื่นๆ ทั้งหมด
                $('.weapon-check, .weapon-check-toggle').not(this).prop('checked', false).trigger('change');
            }
        });
        $('.weapon-check, .weapon-check-toggle').not('#weapon_none').on('change', function() {
            if ($(this).is(':checked')) {
                $('#weapon_none').prop('checked', false);
            }
        });

        // --- Logic: พบร่องรอย (Found Trace) -> เปิด/ปิด Opacity ---
        $('#entry_found_trace').on('change', function() {
            const isChecked = $(this).is(':checked');
            const $details = $('#trace_details');

            if (isChecked) {
                // เปิดใช้งาน: ลบ class opacity-50 ออก เพื่อให้เห็นชัดเต็ม 100%
                $details.removeClass('opacity-50');
                $details.css('pointer-events', 'auto');

                $('#trace_width').prop('disabled', false);
                // Re-enable text inputs ที่ checkbox ของมันยังถูก checked อยู่
                $details.find('.toggle-input:checked, .entry-check-toggle:checked').each(function() {
                    $(this).trigger('change');
                });
                // มั่นใจว่าเอาติ๊ก "ไม่พบร่องรอย" ออกแน่นอน
                $('#entry_no_trace').prop('checked', false);
            } else {
                // ปิดใช้งาน: ใส่ class opacity-50 กลับคืน
                $details.addClass('opacity-50');
                $details.css('pointer-events', 'none');

                // Clear ข้อมูลภายในเมื่อปิด (Optional: ถ้าต้องการให้ค่าหายเมื่อติ๊กออก)
                $details.find('input[type="checkbox"]').prop('checked', false);
                $details.find('input[type="text"]').prop('disabled', true).val('');
                $details.find('input[name="trace_width"]').val('');
            }
        });

        // --- Restraint Binding Logic ---
        $('#restraint_binding').on('change', function() {
            const $inputs = $('.binding-input'); // Selects both material and method inputs
            const isChecked = $(this).is(':checked');

            $inputs.prop('disabled', !isChecked);
            if (isChecked) {
                $inputs.prop('required', true);
                setTimeout(() => $inputs.first().focus(), 50);
            } else {
                $inputs.prop('required', false).val('');
            }
        });

        // --- 4. ความเสียหายต่อร่างกาย (Logic: Enable/Disable) ---
        $('.injury-check').on('change', function() {
            // เช็คว่ามี checkbox ในกลุ่มนี้ถูกติ๊กบ้างไหม
            const isAnyChecked = $('.injury-check:checked').length > 0;
            const $textarea = $('.injury-detail-div').find('textarea');

            if (isAnyChecked) {
                // ปลดล็อค: เอา disabled ออก, เปลี่ยนสีพื้นหลังเป็นสีขาว, บังคับกรอก
                $textarea.prop('disabled', false)
                    .prop('required', true)
                    .removeClass('bg-light').addClass('bg-white')
                    .focus();
            } else {
                // ล็อค: ใส่ disabled, เปลี่ยนสีพื้นเป็นสีเทา, เลิกบังคับกรอก, ล้างค่า
                $textarea.prop('disabled', true)
                    .prop('required', false)
                    .removeClass('bg-white').addClass('bg-light')
                    .val('');
            }
        });

        // --- Events ---

        // 1. กดปุ่มเพิ่มจุด
        $('#btn_add_trace_point').on('click', function() {
            const count = $('#trace_point_container .trace-card').length + 1;

            // 1. สร้าง HTML และแปลงเป็น jQuery Object
            const htmlString = createTraceCard(count);
            const $newCard = $(htmlString);

            // 2. Append ลงใน Container
            $('#trace_point_container').append($newCard);

            // 3. Auto Focus ไปที่ช่อง "บริเวณ" 
            // ใช้ setTimeout เล็กน้อยเพื่อให้ DOM render เสร็จสมบูรณ์ (ป้องกันบั๊กในบาง Browser)
            setTimeout(() => {
                const areaInput = $newCard.find('textarea[name*="[area_detail]"]');
                areaInput.focus();
            }, 50);
        });

        // 2. กดปุ่มลบจุด
        $(document).on('click', '.remove-trace-btn', function() {
            $(this).closest('.trace-card').remove();
            reIndexTracePoints();
        });

        // Initial Load: สร้างจุดที่ 1 ไว้รอเลย (Optional)
        if ($('#trace_point_container').children().length === 0) {
            $('#btn_add_trace_point').click();
        }

        // --- Logic 1: การบรรจุหีบห่อ (Checkbox เลือกได้ 1 เดียว + Auto Focus) ---
        $(document).on('change', '.package-check', function() {
            const wrapper = $(this).closest('.package-wrapper');

            // 1. หา DIV ที่คลุม (ตัวที่มี d-none)
            const inputDiv = wrapper.find('.package-other-input');
            // 2. หา INPUT ด้านใน (ตัวที่ต้องพิมพ์)
            const textInput = inputDiv.find('input');

            // Mutual Exclusion (เลือกได้อันเดียวในกลุ่ม)
            if ($(this).is(':checked')) {
                wrapper.find('.package-check').not(this).prop('checked', false);
            }

            // เช็คสถานะ "อื่นๆ"
            const isOtherChecked = wrapper.find('.package-other-check').is(':checked');

            if (isOtherChecked) {
                // สั่งเปิดที่ DIV
                inputDiv.removeClass('d-none');
                // สั่ง Required ที่ INPUT
                textInput.prop('required', true);

                // Auto Focus
                if ($(this).hasClass('package-other-check')) {
                    setTimeout(() => {
                        textInput.focus();
                    }, 50);
                }
            } else {
                // สั่งปิดที่ DIV
                inputDiv.addClass('d-none');
                // ล้างค่าที่ INPUT
                textInput.prop('required', false).val('');
            }
        });

        // --- Logic 2: การดำเนินการ (ทำเหมือนกัน) ---
        $(document).on('change', '.action-check', function() {
            const wrapper = $(this).closest('.action-wrapper');

            // 1. หา DIV ที่คลุม
            const inputDiv = wrapper.find('.action-other-input');
            // 2. หา INPUT ด้านใน
            const textInput = inputDiv.find('input');

            // Mutual Exclusion
            if ($(this).is(':checked')) {
                wrapper.find('.action-check').not(this).prop('checked', false);
            }

            // เช็คสถานะ "อื่นๆ"
            const isOtherChecked = wrapper.find('.action-other-check').is(':checked');

            if (isOtherChecked) {
                inputDiv.removeClass('d-none');
                textInput.prop('required', true);

                if ($(this).hasClass('action-other-check')) {
                    setTimeout(() => {
                        textInput.focus();
                    }, 50);
                }
            } else {
                inputDiv.addClass('d-none');
                textInput.prop('required', false).val('');
            }
        });

        // --- เพิ่มเติม: ป้องกันการคลิกที่ช่อง Input แล้วไป Trigger ปิด/เปิด Checkbox (เพราะ stretched-link) ---
        $(document).on('click', '.package-other-input, .action-other-input', function(e) {
            e.stopPropagation();
        });

        // Initial Load: สร้างไว้ 1 อัน
        if ($('#evidence_container').children().length === 0) {
            $('#btn_add_evidence').click();
        }

    });

    $('#user_ReviewType').on('change', function(e) {
        if ($('#user_ReviewType').val() == 'nvt') {
            $('.nvt').removeClass('d-none');
            $('.spt').addClass('d-none');
            $('.ptjv').addClass('d-none');
            $('#spt').val('');
            $('#ptjv').val('');
        } else if ($('#user_ReviewType').val() == 'spt') {
            $('.spt').removeClass('d-none');
            $('.nvt').addClass('d-none');
            $('.ptjv').addClass('d-none');
            $('#nvt').val('');
            $('#ptjv').val('');
        } else if ($('#user_ReviewType').val() == 'ptjv') {
            $('.ptjv').removeClass('d-none');
            $('.nvt').addClass('d-none');
            $('.spt').addClass('d-none');
            $('#nvt').val('');
            $('#spt').val('');
            $('#ptjv').val('');
        }
    });

    $('#userReviewID').on('change', function(e) {

        var idEmp = $(this).val();
        if (!idEmp) return; // ถ้าไม่มีค่า ไม่ต้องส่ง

        $.ajax({
            url: "/csims/api/ReceiveNoti/getPos.php",
            type: 'GET',
            data: {
                idEmp: idEmp
            },
            success: function(response) {
                console.log("Server Response:", response);
                if (response.message == "success") {
                    $('#posUserReview').val(response.data.position_name);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error Status:", status);
                console.error("Error Detail:", error);
                console.log("Full URLที่ส่งไป:", this.url); // เช็คใน console ว่า URL ถูกไหม
            }
        });
    });

    $('#user_ApproveType').on('change', function(e) {
        if ($('#user_ApproveType').val() == 'nvt') {
            $('.nvtApprove').removeClass('d-none');
            $('.sptApprove').addClass('d-none');
            $('.ptjvApprove').addClass('d-none');
            $('#sptApprove').val('');
            $('#ptjvApprove').val('');
        } else if ($('#user_ApproveType').val() == 'spt') {
            $('.sptApprove').removeClass('d-none');
            $('.nvtApprove').addClass('d-none');
            $('.ptjvApprove').addClass('d-none');
            $('#nvtApprove').val('');
            $('#ptjvApprove').val('');
        } else if ($('#user_ApproveType').val() == 'ptjv') {
            $('.ptjvApprove').removeClass('d-none');
            $('.nvtApprove').addClass('d-none');
            $('.sptApprove').addClass('d-none');
            $('#nvtApprove').val('');
            $('#sptApprove').val('');
            $('#ptjvApprove').val('');
        }
    });

    $('#userApproveID').on('change', function(e) {

        var idEmp = $(this).val();
        if (!idEmp) return; // ถ้าไม่มีค่า ไม่ต้องส่ง

        $.ajax({
            url: "/csims/api/ReceiveNoti/getPos.php",
            type: 'GET',
            data: {
                idEmp: idEmp
            },
            success: function(response) {
                console.log("Server Response:", response);
                if (response.message == "success") {
                    $('#posUserApprove').val(response.data.position_name);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error Status:", status);
                console.error("Error Detail:", error);
                console.log("Full URLที่ส่งไป:", this.url); // เช็คใน console ว่า URL ถูกไหม
            }
        });
    });

    $('#incident_type').on('change', function(e) {
        if ($(this).val() == '09') {
            $('#other_type_wrapper').removeClass('d-none');
        } else {
            $('#other_type_wrapper').addClass('d-none');
        }
    });

    // --- Filter Actions ---
    $('#btn_clear_filter').on('click', function() {
        $('#searchFilterForm')[0].reset();
        $('#searchFilterForm select').val('').trigger('change');
        setDefaultDate();
        searchPage(1);
    });

    $('#searchFilterForm').on('submit', function(e) {
        e.preventDefault();
        searchPage(1);
    });

    $('#btn_export').on('click', function() {
        const params = $('#searchFilterForm').serialize();
        // รอเปลี่ยน Path API Export ให้ตรงกับที่ใช้จริง
        window.location.href = '/csims/api/ReceiveNoti/exportExcel.php?' + params;
    });

    // --- Modal Actions (Clear form) ---
    $('.js-close-modal').on('click', function() {
        const $form = $(this).closest('.modal').find('form');
        $form[0].reset();
        $form.removeClass('was-validated');
        $form.find('select').val('').trigger('change');
    });

    // --- 5. AJAX Search Logic ---
    function searchPage(page) {
        console.log(page, "<-------------------page")
        const formData = $('#searchFilterForm').serializeArray();
        formData.push({
            name: 'page',
            value: page
        });

        $.ajax({
            // ข้อมูลต่างจากตอนที่ query มาแสดงในตารางตอนต้น ทำให้ตอน search แล้วมีจำนวนข้อมูลต่างจากที่เข้าสู่ page ครั้งแรก
            url: '/csims/api/incidentCheckList/searchData.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status == 'success') {
                    console.log(response, "<-------------res");
                    renderTable(response.data, response.offset);
                    $('#count_display').text(response.count);
                    const total = parseInt(response.totalPages);
                    const current = parseInt(response.currentPage);

                    console.log("กำลังส่งค่าไป SetupPagination:", total, current);

                    if (total > 0) {
                        setupPagination(total, current);
                    }
                }
            }
        });
    }

    // --- Render Table ---
    function renderTable(data, offset) {
        let html = '';
        let currentOffset = (isNaN(parseInt(offset)) || offset === undefined) ? 0 : parseInt(offset);
        let index = currentOffset + 1;

        if (data && data.length > 0) {
            data.forEach(function(row) {
                // กำหนดสีพื้นหลังตามสถานะ: บันทึกแล้ว = สีขาว, ยังไม่บันทึก = สีเทา
                let rowBgStyle = (row.statusChecklist && row.statusChecklist == 1) ?
                    'background-color: #ffffff !important;' :
                    'background-color: #e9ecef !important;';

                let draftButton = '';
                let reportButton = '';
                let qrButton = '';
                if (row.statusChecklist && row.statusChecklist == 1) {
                    draftButton = `<button type="button" class="btn btn-sm btn-draft-report btn-download-pdf" style="background:#7c3aed; box-shadow:0 2px 6px rgba(124,58,237,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-pdf-id="${row.id}" title="ดาวน์โหลดรายการตรวจสอบ">
                        <i class="fas fa-file-pdf me-1"></i>รายงาน Checklist
                    </button>`;
                    reportButton = `<button type="button" class="btn btn-sm btn-report-pdf" style="background:#0d9488; box-shadow:0 2px 6px rgba(13,148,136,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-pdf-id="${row.id}" data-pdf-type="${row.complaints_type}" title="ดาวน์โหลด F-CS-11">
                        <i class="fas fa-file-pdf me-1"></i>F-CS-11
                    </button>`;
                    qrButton = `<button type="button" class="btn btn-sm btn-qr" style="background:#4f46e5; box-shadow:0 2px 6px rgba(79,70,229,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" onclick="openQRCodeModal(${row.id})">
                        <i class="fa-solid fa-qrcode me-1"></i>วัตถุพยาน
                    </button>`;
                } else {
                    draftButton = `<button type="button" class="btn btn-sm btn-secondary btn-no-action" style="background:#9ca3af; box-shadow:none; color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; cursor:not-allowed; opacity:.55;" disabled title="ยังไม่มีข้อมูล">
                        <i class="fas fa-file-pdf me-1"></i>รายงาน Checklist
                    </button>`;
                    reportButton = `<button type="button" class="btn btn-sm btn-secondary btn-no-action" style="background:#9ca3af; box-shadow:none; color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; cursor:not-allowed; opacity:.55;" disabled title="ยังไม่มีข้อมูล">
                        <i class="fas fa-file-pdf me-1"></i>F-CS-11
                    </button>`;
                    qrButton = `<button type="button" class="btn btn-sm btn-no-action" style="background:#9ca3af; box-shadow:none; color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; cursor:not-allowed; opacity:.55;" disabled title="ยังไม่มีข้อมูล">
                        <i class="fa-solid fa-qrcode me-1"></i>วัตถุพยาน
                    </button>`;
                }

                html += `
                        <tr class="checklist-row" style="cursor: pointer; ${rowBgStyle}"
                            data-id="${row.id}"
                            data-doc-no="${row.receiveNoti_No}"
                            data-report-no="${row.receiveNotiReportNo}"
                            data-complaints-type="${row.complaints_type}"
                            data-status-checklist="${row.statusChecklist || '0'}">
                            <td class="text-center">${index}</td>
                            <td class="text-center">${row.receiveNoti_No_TH || (window.toThaiDocNo ? toThaiDocNo(row.receiveNoti_No) : row.receiveNoti_No) || ''}</td>
                            <td class="text-center">${row.receiveNotiReportNo_TH || (window.toThaiReportNo ? toThaiReportNo(row.receiveNotiReportNo) : row.receiveNotiReportNo) || ''}</td>
                            <td style="white-space: nowrap;">${row.complaints_From}</td>
                            <td class="text-center">${row.province}</td>
                            <td>${row.complaintstype}</td>
                            <td>${row.complaintsdevice}</td>
                            <td>${row.fullname_create}</td>
                            <td class="text-center">${draftButton}</td>
                            <td class="text-center">${reportButton}</td>
                            <td class="text-center">${qrButton}</td>
                        </tr>
                    `;
                index++;
            });
        } else {
            html = '<tr><td colspan="10" class="text-center">ไม่พบข้อมูล</td></tr>';
        }
        $('#table_body').html(html);
    }

    // ========================================================================
    // Fire Functions
    // ========================================================================

    function removeFireInspectorRow(btn) {
        $(btn).closest('.inspector-row-fire').remove();
        // Re-number indices
        $('#inspector_container_fire .inspector-row-fire').each(function(idx) {
            $(this).find('.index-label').text('5.' + (idx + 1));
        });
    }

    function removeFireAttachment(fileId) {
        const item = attachmentStoreFire.find(x => x.id === fileId);
        if (item && item.existing && item.db_file_id) {
            deletedExistingPhotosFire.push(item.db_file_id);
        }
        attachmentStoreFire = attachmentStoreFire.filter(x => x.id !== fileId);
        $('#fire_attach_' + fileId).remove();
        updateFireRealInput();
        if (attachmentStoreFire.length === 0) {
            $('#attachments_wrapper_fire').addClass('d-none');
        }
    }

    // =============================================================
    //  FINGERPRINT: Photo Attachment Functions
    // =============================================================
    function updateFPRealInput() {
        const count = attachmentStoreFP.length;
        $('#fpn_photo_amount').val(count);
        $('#fp_photo_amount').val(count);
        $('#file_count_badge_fpn').text(count);
        $('#file_count_badge_fp').text(count);

        // ★ รหัสภาพ = ชื่อไฟล์แรก/ล่าสุด (เหมือน Life) ทั้งฟอร์มปกติ (fpn_) และฟอร์มเสมือน (fp_)
        if (count > 0) {
            const startName = attachmentStoreFP[0].name || 'photo';
            const endName = attachmentStoreFP[count - 1].name || 'photo';
            $('#fpn_photo_id_start').val(startName);
            $('#fpn_photo_id_end').val(endName);
            $('#fp_photo_id_start').val(startName);
            $('#fp_photo_id_end').val(endName);
        } else {
            $('#fpn_photo_id_start, #fpn_photo_id_end, #fp_photo_id_start, #fp_photo_id_end').val('');
        }
    }

    function renderFPAttachmentGrid() {
        // Standard form grid (สไตล์ Life: ปุ่มตา + ลบ + badge รูปเดิม/รูปใหม่)
        const grid = $('#attachments_grid_fpn');
        grid.empty();
        if (attachmentStoreFP.length > 0) {
            $('#attachments_wrapper_fpn').removeClass('d-none');
            attachmentStoreFP.forEach(function(item, idx) {
                const displayName = (item.name || 'photo').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                const jsName = (item.name || 'photo').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                const imgSrc = item.src || '';
                const badgeHtml = item.existing ?
                    '<div class="pt-2"><span class="badge bg-info text-white fw-normal" style="font-size:0.65rem;">รูปเดิม</span></div>' :
                    '<div class="pt-2"><span class="badge bg-success text-white fw-normal" style="font-size:0.65rem;">รูปใหม่</span></div>';
                const card = `
                    <div class="col attachment-item" id="fpn_attach_${item.id}">
                        <div class="card attachment-card h-100">
                            <div class="card-actions-bar">
                                <button type="button" class="action-btn" title="ดูรูปภาพ" onclick="showImagePreview('${imgSrc}', '${jsName}')"><i class="far fa-eye" style="font-size:0.8rem;"></i></button>
                                <button type="button" class="action-btn delete" onclick="removeFPAttachment('${item.id}')" title="ลบรูปนี้"><i class="fas fa-times" style="font-size:0.85rem;"></i></button>
                            </div>
                            <div class="img-thumbnail-box"><img src="${imgSrc}" alt="${displayName}"></div>
                            <div class="card-body d-flex flex-column">
                                <div class="filename-text mb-auto" title="${displayName}">${displayName}</div>
                                ${badgeHtml}
                            </div>
                        </div>
                    </div>`;
                grid.append(card);
            });
        } else {
            $('#attachments_wrapper_fpn').addClass('d-none');
        }

        // PDF form grid
        const gridPdf = $('#attachments_grid_fp');
        gridPdf.empty();
        if (attachmentStoreFP.length > 0) {
            $('#attachments_wrapper_fp').removeClass('d-none');
            attachmentStoreFP.forEach(function(item, idx) {
                const card = `
                    <div class="col" id="fp_attach_${item.id}">
                        <div class="card h-100 border shadow-sm overflow-hidden">
                            <div class="position-relative">
                                <img src="${item.src}" class="card-img-top" style="height:120px;object-fit:cover;">
                                <div class="position-absolute top-0 end-0 p-1">
                                    <button type="button" class="btn btn-sm btn-danger rounded-circle shadow"
                                        style="width:24px;height:24px;padding:0;"
                                        onclick="removeFPAttachment('${item.id}')">
                                        <i class="fas fa-times" style="font-size:10px;"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-1">
                                <p class="card-text small text-truncate mb-0" style="font-size:10px;" title="${item.name}">${item.name}</p>
                            </div>
                        </div>
                    </div>`;
                gridPdf.append(card);
            });
        } else {
            $('#attachments_wrapper_fp').addClass('d-none');
        }
    }

    function removeFPAttachment(fileId) {
        const item = attachmentStoreFP.find(x => x.id === fileId);
        if (item && item.existing && item.db_file_id) {
            deletedExistingPhotosFP.push(item.db_file_id);
        }
        attachmentStoreFP = attachmentStoreFP.filter(x => x.id !== fileId);
        $('#fpn_attach_' + fileId + ', #fp_attach_' + fileId).remove();
        updateFPRealInput();
        if (attachmentStoreFP.length === 0) {
            $('#attachments_wrapper_fpn').addClass('d-none');
            $('#attachments_wrapper_fp').addClass('d-none');
        }
    }

    function resetFireForm() {
        const form = document.getElementById('incidentCheckListFormFire');
        if (form) form.reset();
        // Reset PDF form ด้วย
        const pdfForm = document.getElementById('fireFormPdf');
        if (pdfForm) pdfForm.reset();
        // ★ คืนค่า default วันที่ปัจจุบันให้ field "ลง"
        var _today = new Date().toISOString().slice(0, 10);
        $('#fire_document_date, #fpf_document_date').val(_today);
        // Clear hidden fields
        $('#receiveNoti_id_fire, #doc_no_fire, #report_no_fire').val('');
        $('#fpf_receiveNoti_id, #fpf_doc_no, #fpf_report_no').val('');
        $('#receiveNoti_No_fire, #receiveNotiReportNo_fire').text('');
        $('#case_doc_no_fire').val('');
        // Clear photos
        attachmentStoreFire = [];
        deletedExistingPhotosFire = [];
        renderFireAttachmentGrid();
        updateFireRealInput();
        // Clear sketch + signatures
        ['scene_sketch_canvas_fire', 'sig-canvas-receiver-fire', 'sig-canvas-sender-fire'].forEach(function(id) {
            if (signaturePads[id]) signaturePads[id].clear();
        });
        $('#scene_sketch_data_fire, #receiver_signature_data_fire, #sender_signature_data_fire').val('');
        // Clear handover position fields
        $('#receiver_position_fire, #sender_position_fire').val('');
        // Reset inspectors to just 1
        $('#inspector_container_fire .inspector-row-fire:not(:first)').remove();
        // Reset Select2
        $('#police_station_fire').val('').trigger('change');
        $('.inspector-select-fire').val('').trigger('change');
        // Reset user selects
        $('.user-select-box-fire').val('').trigger('change');
        // Clear dynamic persons — reset to 1 empty card
        const personContainer = $('#fire_person_container');
        personContainer.find('.victim-card-fire:not(:first)').remove();
        const firstCard = personContainer.find('.victim-card-fire').first();
        firstCard.find('select, input').val('');
        firstCard.find('.badge').text('รายการที่ 1');
        if (typeof fireVictimIndex !== 'undefined') fireVictimIndex = 1;
        // Uncheck all fire-radio-toggle checkboxes
        $('.fire-radio-toggle').prop('checked', false);
        // Uncheck notify method checkboxes
        $('input[name="fire_notify_method[]"]').prop('checked', false);
        // Hide/disable conditional detail fields
        $('#fire_notify_other_text').hide().prop('disabled', true).val('');
        $('#fire_extinguish_yes_detail').hide().prop('disabled', true).val('');
        $('#fire_cause_believed_detail').hide().prop('disabled', true).val('');
        $('#fire_cause_unknown_detail').hide().prop('disabled', true).val('');
        // Reset edit info
        $('#editInfoFire').addClass('d-none');
        $('#editCountFire').text('0');
        $('#editDateFire').text('-');
    }

    // =========================================================
    // PREFILL INCIDENT DATA FOR FIRE MODAL (ดึงข้อมูลรับแจ้งเหตุ)
    // =========================================================
    function prefillIncidentDataForFire(incidentId, onComplete) {
        $.ajax({
            url: '/csims/api/ReceiveNoti/getDataByID.php',
            type: 'GET',
            dataType: 'json',
            data: {
                id: incidentId
            },
            success: function(response) {
                if (response.status === 'success' && response.data) {
                    const data = response.data;

                    setSelectValueWithOption($('#police_station_fire'), data.complaints_From);
                    setSelectValueWithOption($('#fpf_police_station'), data.complaints_From);

                    $('input[name="fire_notify_method[]"]').prop('checked', false);
                    const channelVal = mapReceiveNotiChannel(data.complaints_From_Device);
                    if (channelVal) {
                        $('input[name="fire_notify_method[]"][value="' + channelVal + '"]').prop('checked', true);
                    }
                    if (channelVal === 'อื่นๆ') {
                        const otherText = (data.complaints_From_Device_Other || '').toString().trim();
                        if (otherText) {
                            $('#fire_notify_other_text, #fpf_notify_other_text').show().prop('disabled', false).val(otherText);
                        }
                    }

                    const receiveDateTime = splitReceiveNotiDateTime(data.create_date);
                    if (receiveDateTime.date) {
                        $('[name="fire_report_date"]').val(receiveDateTime.date);
                    }
                    if (receiveDateTime.time) {
                        $('[name="fire_report_time"]').val(receiveDateTime.time);
                    }

                    const investigatorName = [data.inquiry_official_first_name, data.inquiry_official_last_name]
                        .map(function(v) {
                            return (v || '').toString().trim();
                        })
                        .filter(Boolean)
                        .join(' ');
                    if (investigatorName) {
                        $('[name="fire_investigator_name"]').val(investigatorName);
                    }
                    if (data.inquiry_official_phone && data.inquiry_official_phone.toString().trim() !== '') {
                        $('[name="fire_investigator_phone"]').val(data.inquiry_official_phone);
                    }
                    if (data.location_crime && data.location_crime.toString().trim() !== '') {
                        $('[name="fire_incident_location"]').val(data.location_crime);
                    }

                    const occurDateTime = splitReceiveNotiDateTime(data.time_Occurrence);
                    if (occurDateTime.date) {
                        $('[name="fire_victim_known_date"]').val(occurDateTime.date);
                        $('[name="fire_investigator_known_date"]').val(occurDateTime.date);
                        $('[name="fire_inspection_date"]').val(occurDateTime.date);
                    }
                    if (occurDateTime.time) {
                        $('[name="fire_victim_known_time"]').val(occurDateTime.time);
                        $('[name="fire_investigator_known_time"]').val(occurDateTime.time);
                        $('[name="fire_inspection_time"]').val(occurDateTime.time);
                    }

                    const victimName = [data.suffer_first_name, data.suffer_last_name]
                        .map(function(v) {
                            return (v || '').toString().trim();
                        })
                        .filter(Boolean)
                        .join(' ');
                    if (victimName) {
                        $('[name="fire_person_name[]"]').first().val(victimName);
                    }

                    // พฤติการณ์คดี (basic_Info)
                    if (data.basic_Info && data.basic_Info.trim() !== '') {
                        $('[name="fire_case_behavior"]').val(data.basic_Info);
                        $('#fpf_case_behavior').val(data.basic_Info);
                        window._basicInfoFire = data.basic_Info;
                    }
                }
                if (typeof onComplete === 'function') onComplete();
            },
            error: function() {
                if (typeof onComplete === 'function') onComplete();
            }
        });
    }

    function loadFireDataToModal(incidentId, onComplete) {
        $.ajax({
            url: './api/incidentCheckList/getFireData.php',
            method: 'GET',
            data: {
                incident_id: incidentId
            },
            dataType: 'json',
            success: function(res) {
                console.log('🔥 getFireData response:', res);
                // ★ เก็บ basic_info ไว้ใน global เพื่อ auto-fill พฤติการณ์คดี
                if (res && res.basic_info) window._basicInfoFire = res.basic_info;

                if (res && res.success && res.data) {
                    const d = res.data;

                    if (res.edit_info) {
                        const ei = res.edit_info;
                        $('#editCountFire').text(ei.count_edit || 0);
                        $('#editCountFirePdf').text(ei.count_edit || 0);
                        if (ei.edit_date) {
                            const ed = new Date(ei.edit_date);
                            const dd = String(ed.getDate()).padStart(2, '0');
                            const mm = String(ed.getMonth() + 1).padStart(2, '0');
                            const yyyy = ed.getFullYear() + 543;
                            const hh = String(ed.getHours()).padStart(2, '0');
                            const mi = String(ed.getMinutes()).padStart(2, '0');
                            $('#editDateFire').text(dd + '/' + mm + '/' + yyyy + ' ' + hh + ':' + mi + ' น.');
                        } else {
                            $('#editDateFire').text('-');
                        }
                        $('#editInfoFire').removeClass('d-none');
                        $('#editInfoFirePdf').removeClass('d-none');
                    }

                    // General Info
                    if (d.general_info) {
                        if (d.general_info.case_doc_no) $('#case_doc_no_fire').val(thaiDocNo(d.general_info.case_doc_no));
                        // date/time split (new) or datetime (old)
                        if (d.general_info.report_date) $('[name="fire_report_date"]').val(d.general_info.report_date);
                        if (d.general_info.report_time) $('[name="fire_report_time"]').val(d.general_info.report_time);
                        if (d.general_info.report_datetime) {
                            // legacy: split datetime-local into date + time
                            const dt = d.general_info.report_datetime;
                            if (dt.includes('T')) {
                                $('[name="fire_report_date"]').val(dt.split('T')[0]);
                                $('[name="fire_report_time"]').val(dt.split('T')[1].substring(0, 5));
                            }
                        }
                        // notify method checkboxes
                        if (d.general_info.notify_method && Array.isArray(d.general_info.notify_method)) {
                            d.general_info.notify_method.forEach(function(val) {
                                $('input[name="fire_notify_method[]"][value="' + val + '"]').prop('checked', true);
                                if (val === 'อื่นๆ') {
                                    $('#fire_notify_other_text').show().prop('disabled', false);
                                }
                            });
                        }
                        if (d.general_info.notify_method_other_text) {
                            $('#fire_notify_other_text').val(d.general_info.notify_method_other_text).show().prop('disabled', false);
                        }
                        if (d.general_info.document_no) $('#fire_document_no').val(d.general_info.document_no);
                        if (d.general_info.source_station) {
                            $('#police_station_fire').val(d.general_info.source_station).trigger('change');
                        }
                        $('#fire_document_date').val(d.general_info.document_date || new Date().toISOString().slice(0, 10));
                        if (d.general_info.investigator) {
                            if (d.general_info.investigator.name) $('#fire_investigator_name').val(d.general_info.investigator.name);
                            if (d.general_info.investigator.phone) $('#fire_investigator_phone').val(d.general_info.investigator.phone);
                        }
                    }

                    // Scene Info
                    if (d.scene_info) {
                        if (d.scene_info.incident_location) $('[name="fire_incident_location"]').val(d.scene_info.incident_location);
                        // Dynamic persons — victim cards
                        if (d.scene_info.persons && d.scene_info.persons.length > 0) {
                            const container = document.getElementById('fire_person_container');
                            // Clear existing cards first
                            $(container).empty();
                            if (typeof fireVictimIndex !== 'undefined') fireVictimIndex = 0;
                            d.scene_info.persons.forEach(function(p) {
                                addVictimCardFire();
                                const lastCard = $(container).find('.victim-card-fire').last();
                                lastCard.find('[name="fire_person_type[]"]').val(p.type || '');
                                lastCard.find('[name="fire_person_name[]"]').val(p.name || '');
                                lastCard.find('[name="fire_person_age[]"]').val(p.age || '');
                                lastCard.find('[name="fire_person_remark[]"]').val(p.remark || '');
                            });
                        }
                    }

                    // Datetime Info — date/time split (new) or datetime (old)
                    if (d.datetime_info) {
                        // Helper to set date/time from either split or combined format
                        function setDateTimeSplit(dateField, timeField, dateVal, timeVal, datetimeVal) {
                            if (dateVal) $('[name="' + dateField + '"]').val(dateVal);
                            if (timeVal) $('[name="' + timeField + '"]').val(timeVal);
                            if (datetimeVal && datetimeVal.includes('T')) {
                                $('[name="' + dateField + '"]').val(datetimeVal.split('T')[0]);
                                $('[name="' + timeField + '"]').val(datetimeVal.split('T')[1].substring(0, 5));
                            }
                        }
                        setDateTimeSplit('fire_victim_known_date', 'fire_victim_known_time',
                            d.datetime_info.victim_known_date, d.datetime_info.victim_known_time,
                            d.datetime_info.victim_known_datetime);
                        setDateTimeSplit('fire_investigator_known_date', 'fire_investigator_known_time',
                            d.datetime_info.investigator_known_date, d.datetime_info.investigator_known_time,
                            d.datetime_info.investigator_known_datetime);
                        setDateTimeSplit('fire_inspection_date', 'fire_inspection_time',
                            d.datetime_info.inspection_date, d.datetime_info.inspection_time,
                            d.datetime_info.inspection_datetime);
                        setDateTimeSplit('fire_inspection_additional_date', 'fire_inspection_additional_time',
                            d.datetime_info.inspection_additional_date, d.datetime_info.inspection_additional_time,
                            d.datetime_info.inspection_additional_datetime);
                        if (d.datetime_info.known_detail) $('[name="fire_known_detail"]').val(d.datetime_info.known_detail);
                    }

                    // Inspectors
                    if (d.inspectors && d.inspectors.length > 0) {
                        d.inspectors.forEach(function(insp, idx) {
                            if (idx > 0) {
                                $('#btn_add_inspector_fire').trigger('click');
                            }
                            const rows = $('#inspector_container_fire .inspector-row-fire');
                            const row = rows.eq(idx);
                            row.find('select[name="fire_inspector_id[]"]').val(insp.id).trigger('change');
                        });
                    }

                    // Scene Characteristics
                    if (d.scene_characteristics) {
                        const sc = d.scene_characteristics;
                        if (sc.exterior) {
                            $('[name="fire_exterior_detail"]').val(sc.exterior.detail || '');
                            $('[name="fire_floor_count"]').val(sc.exterior.floor_count || '');
                            if (sc.exterior.fence) {
                                $('[name="fire_fence"][value="' + sc.exterior.fence + '"]').prop('checked', true);
                            }
                            $('[name="fire_scene_front"]').val(sc.exterior.front || '');
                            $('[name="fire_scene_back"]').val(sc.exterior.back || '');
                            $('[name="fire_scene_left"]').val(sc.exterior.left || '');
                            $('[name="fire_scene_right"]').val(sc.exterior.right || '');
                        }
                        if (sc.interior) {
                            $('[name="fire_interior_detail"]').val(sc.interior.detail || '');
                        }
                        if (sc.incident_area) {
                            $('[name="fire_incident_area_detail"]').val(sc.incident_area.detail || '');
                            $('[name="fire_area_size"]').val(sc.incident_area.size || '');
                            $('[name="fire_facing_direction"]').val(sc.incident_area.facing_direction || '');
                            if (sc.incident_area.structure) {
                                const st = sc.incident_area.structure;
                                $('[name="fire_structure_wall_front"]').val(st.wall_front || '');
                                $('[name="fire_structure_wall_left"]').val(st.wall_left || '');
                                $('[name="fire_structure_wall_right"]').val(st.wall_right || '');
                                $('[name="fire_structure_wall_back"]').val(st.wall_back || '');
                                $('[name="fire_structure_floor"]').val(st.floor || '');
                                $('[name="fire_structure_roof"]').val(st.roof || '');
                                $('[name="fire_structure_ceiling"]').val(st.ceiling || '');
                            }
                            if (sc.incident_area.objects) {
                                const obj = sc.incident_area.objects;
                                $('[name="fire_objects_wall_front"]').val(obj.wall_front || '');
                                $('[name="fire_objects_wall_left"]').val(obj.wall_left || '');
                                $('[name="fire_objects_wall_right"]').val(obj.wall_right || '');
                                $('[name="fire_objects_wall_back"]').val(obj.wall_back || '');
                                $('[name="fire_objects_other"]').val(obj.other || '');
                            }
                        }
                    }

                    // Case Behavior
                    if (d.case_behavior) {
                        $('[name="fire_case_behavior"]').val(d.case_behavior.detail || window._basicInfoFire || '');
                        if (d.case_behavior.insurance) {
                            $('[name="fire_insurance"][value="' + d.case_behavior.insurance + '"]').prop('checked', true);
                        }
                        $('[name="fire_burn_time"]').val(d.case_behavior.burn_time || '');
                        if (d.case_behavior.extinguish) {
                            $('[name="fire_extinguish"][value="' + d.case_behavior.extinguish + '"]').prop('checked', true);
                            if (d.case_behavior.extinguish === 'yes') {
                                $('#fire_extinguish_yes_detail').show().prop('disabled', false);
                            }
                        }
                        $('[name="fire_extinguish_detail"]').val(d.case_behavior.extinguish_detail || '');
                        $('[name="fire_damage_condition"]').val(d.case_behavior.damage_condition || '');
                        $('[name="fire_spread_detail"]').val(d.case_behavior.spread_detail || '');
                    } else if (window._basicInfoFire) {
                        $('[name="fire_case_behavior"]').val(window._basicInfoFire);
                    }

                    // Damage
                    if (d.damage) {
                        if (d.damage.structure) {
                            const ds = d.damage.structure;
                            $('[name="fire_damage_wall_front"]').val(ds.wall_front || '');
                            $('[name="fire_damage_wall_left"]').val(ds.wall_left || '');
                            $('[name="fire_damage_wall_right"]').val(ds.wall_right || '');
                            $('[name="fire_damage_wall_back"]').val(ds.wall_back || '');
                            $('[name="fire_damage_floor"]').val(ds.floor || '');
                            $('[name="fire_damage_roof"]').val(ds.roof || '');
                            $('[name="fire_damage_ceiling"]').val(ds.ceiling || '');
                        }
                        if (d.damage.objects) {
                            const dobj = d.damage.objects;
                            $('[name="fire_damage_obj_front"]').val(dobj.front || '');
                            $('[name="fire_damage_obj_left"]').val(dobj.left || '');
                            $('[name="fire_damage_obj_right"]').val(dobj.right || '');
                            $('[name="fire_damage_obj_back"]').val(dobj.back || '');
                            $('[name="fire_damage_obj_floor"]').val(dobj.floor || '');
                            $('[name="fire_damage_obj_roof"]').val(dobj.roof || '');
                            $('[name="fire_damage_obj_ceiling"]').val(dobj.ceiling || '');
                        }
                        $('[name="fire_first_area"]').val(d.damage.first_area || '');
                        $('[name="fire_switch_condition"]').val(d.damage.switch_condition || '');
                        if (d.damage.adjacent_damage) {
                            $('[name="fire_adjacent_damage"][value="' + d.damage.adjacent_damage + '"]').prop('checked', true);
                        }
                        $('[name="fire_adjacent_damage_detail"]').val(d.damage.adjacent_damage_detail || '');
                        $('[name="fire_evidence_found"]').val(d.damage.evidence_found || '');
                        $('[name="fire_evidence_collected"]').val(d.damage.evidence_collected || '');
                        if (d.damage.evidence_action && Array.isArray(d.damage.evidence_action)) {
                            d.damage.evidence_action.forEach(function(val) {
                                $('[name="fire_evidence_action[]"][value="' + val + '"]').prop('checked', true);
                            });
                        }
                    }

                    // Summary
                    if (d.summary) {
                        $('[name="fire_origin_area"]').val(d.summary.origin_area || '');
                        $('[name="fire_fuel_source"]').val(d.summary.fuel_source || '');
                        $('[name="fire_heat_source"]').val(d.summary.heat_source || '');
                        $('[name="fire_summary_other"]').val(d.summary.other || '');
                    }

                    // Opinion
                    if (d.opinion) {
                        $('[name="fire_opinion_first_area"]').val(d.opinion.first_area || '');
                        if (d.opinion.cause_type) {
                            $('[name="fire_cause_type"][value="' + d.opinion.cause_type + '"]').prop('checked', true);
                            // Show corresponding detail field
                            if (d.opinion.cause_type === 'believed') {
                                $('#fire_cause_believed_detail').show().prop('disabled', false);
                            } else if (d.opinion.cause_type === 'unknown') {
                                $('#fire_cause_unknown_detail').show().prop('disabled', false);
                            }
                        }
                        $('[name="fire_cause_believed_detail"]').val(d.opinion.cause_believed_detail || '');
                        $('[name="fire_cause_unknown_detail"]').val(d.opinion.cause_unknown_detail || '');
                    }

                    // Photo Records
                    if (d.photo_records) {
                        if (d.photo_records.inspect_date) $('[name="photo_inspect_date_fire"]').val(d.photo_records.inspect_date);
                        if (d.photo_records.inspect_time) $('[name="photo_inspect_time_fire"]').val(d.photo_records.inspect_time);
                        $('#photo_id_start_fire').val(d.photo_records.start || '');
                        $('#photo_id_end_fire').val(d.photo_records.end || '');
                        $('#photo_amount_fire').val(d.photo_records.amount || '');
                        if (d.photo_records.recorder_name) $('#recorder_name_fire').val(d.photo_records.recorder_name).trigger('change');
                        if (d.photo_records.recorder_datetime) $('#recorder_datetime_fire').val(d.photo_records.recorder_datetime);
                        if (d.photo_records.photographer_name) $('#photographer_name_fire').val(d.photo_records.photographer_name).trigger('change');
                        if (d.photo_records.photographer_datetime) $('#photographer_datetime_fire').val(d.photo_records.photographer_datetime);
                        if (d.photo_records.investigator_select) $('#fire_investigator_select').val(d.photo_records.investigator_select).trigger('change');
                        if (d.photo_records.investigator_phone_recorder) $('#fire_investigator_phone_recorder').val(d.photo_records.investigator_phone_recorder);
                    }

                    // Handover
                    if (d.handover) {
                        if (d.handover.inspection_end_date) $('[name="fire_inspection_end_date"]').val(d.handover.inspection_end_date);
                        if (d.handover.inspection_end_time) $('[name="fire_inspection_end_time"]').val(d.handover.inspection_end_time);
                        if (d.handover.receiver_name) $('#receiver_name_fire').val(d.handover.receiver_name).trigger('change');
                        if (d.handover.receiver_position) $('#receiver_position_fire').val(d.handover.receiver_position);
                        if (d.handover.sender_name) $('#sender_name_fire').val(d.handover.sender_name).trigger('change');
                        if (d.handover.sender_position) $('#sender_position_fire').val(d.handover.sender_position);
                    }

                    // === วัตถุพยานและตำแหน่งที่ตรวจพบ (evidence cards) ===
                    if (d.evidences && d.evidences.length > 0) {
                        const evContainer = $('#evidence_container_fire');
                        // ลบ cards เก่าทั้งหมด
                        evContainer.find('.evidence-card-fire').remove();
                        if (typeof evidenceIndexFire !== 'undefined') evidenceIndexFire = 0;
                        d.evidences.forEach(function(ev, idx) {
                            addEvidenceRowFire();
                            const lastCard = evContainer.find('.evidence-card-fire').last();
                            lastCard.find('[name="evidence_item_fire[]"]').val(ev.item || ev.detail || '');
                            lastCard.find('[name="evidence_azimuth_fire[]"]').val(ev.azimuth || '');
                            lastCard.find('[name="evidence_remark_fire[]"]').val(ev.remark || '');
                            window.setLabUnits(lastCard.find('[name="evidence_lab_unit_fire[]"]'), ev.lab_unit);
                            const evDist1 = ev.ref1_dist ?? (ev.level_1 === true || ev.level_1 === '1' ? '' : (ev.level_1 || ''));
                            const evDist2 = ev.ref2_dist ?? (ev.level_2 === true || ev.level_2 === '1' ? '' : (ev.level_2 || ''));
                            const evDist3 = ev.ref3_dist ?? (ev.level_3 === true || ev.level_3 === '1' ? '' : (ev.level_3 || ''));
                            const evDist4 = ev.ref4_dist ?? (ev.level_4 === true || ev.level_4 === '1' ? '' : (ev.level_4 || ''));
                            lastCard.find('[name^="evidence_level_1_fire_"]').val(evDist1);
                            lastCard.find('[name^="evidence_level_2_fire_"]').val(evDist2);
                            lastCard.find('[name^="evidence_level_3_fire_"]').val(evDist3);
                            lastCard.find('[name^="evidence_level_4_fire_"]').val(evDist4);
                        });
                    }

                    // === จุดอ้างอิง (evidence_meta) ===
                    if (d.evidence_meta) {
                        $('#reference_point_1_fire').val(d.evidence_meta.reference_point_1 || '');
                        $('#reference_point_2_fire').val(d.evidence_meta.reference_point_2 || '');
                        $('#reference_point_3_fire').val(d.evidence_meta.reference_point_3 || '');
                        $('#reference_point_4_fire').val(d.evidence_meta.reference_point_4 || '');
                        var $pdfFireForm = $('#fireFormPdf');
                        if ($pdfFireForm.length) {
                            $pdfFireForm.find('[name="reference_point_1_fire"]').val(d.evidence_meta.reference_point_1 || '');
                            $pdfFireForm.find('[name="reference_point_2_fire"]').val(d.evidence_meta.reference_point_2 || '');
                            $pdfFireForm.find('[name="reference_point_3_fire"]').val(d.evidence_meta.reference_point_3 || '');
                            $pdfFireForm.find('[name="reference_point_4_fire"]').val(d.evidence_meta.reference_point_4 || '');
                        }
                    }

                    // === บันทึกการตรวจเก็บวัตถุพยาน (measurement cards) ===
                    if (d.measurement_meta) {
                        if (d.measurement_meta.inspection_date) {
                            $('#measurement_inspection_date_fire').val(d.measurement_meta.inspection_date);
                        }
                    }
                    if (d.measurements && d.measurements.length > 0) {
                        const mContainer = $('#measurement_container_fire');
                        // ลบ cards เก่าทั้งหมด
                        mContainer.find('.measurement-card-fire').remove();
                        if (typeof measurementIndexFire !== 'undefined') measurementIndexFire = 0;
                        d.measurements.forEach(function(m, idx) {
                            addMeasurementCardFire();
                            const lastCard = mContainer.find('.measurement-card-fire').last();
                            lastCard.find('[name="measurement_item_fire[]"]').val(m.item || '');
                            lastCard.find('[name="measurement_quantity_fire[]"]').val(m.quantity || '');
                            lastCard.find('[name="measurement_area_fire[]"]').val(m.area || '');
                            lastCard.find('[name="measurement_label_number_fire[]"]').val(m.label_number || '');
                            lastCard.find('[name="measurement_remark_fire[]"]').val(m.remark || '');
                            window.setLabUnits(lastCard.find('[name="measurement_forensic_unit_fire[]"]'), m.forensic_unit);
                            // การบรรจุหีบ
                            if (m.package_plastic) {
                                lastCard.find('[name^="measurement_package_plastic_check_fire_"]').prop('checked', true);
                                lastCard.find('[name^="measurement_package_plastic_text_fire_"]').prop('disabled', false).val(m.package_plastic_text || '');
                            }
                            if (m.package_paper) {
                                lastCard.find('[name^="measurement_package_paper_check_fire_"]').prop('checked', true);
                                lastCard.find('[name^="measurement_package_paper_text_fire_"]').prop('disabled', false).val(m.package_paper_text || '');
                            }
                            if (m.package_other) {
                                lastCard.find('[name^="measurement_package_other_check_fire_"]').prop('checked', true);
                                lastCard.find('[name^="measurement_package_other_text_fire_"]').prop('disabled', false).val(m.package_other_text || '');
                            }
                            // การดำเนินการ
                            if (m.action_return) {
                                lastCard.find('[name^="measurement_action_return_check_fire_"]').prop('checked', true);
                                lastCard.find('[name^="measurement_action_return_text_fire_"]').prop('disabled', false).val(m.action_return_text || '');
                            }
                            if (m.action_other) {
                                lastCard.find('[name^="measurement_action_other_check_fire_"]').prop('checked', true);
                                lastCard.find('[name^="measurement_action_other_text_fire_"]').prop('disabled', false).val(m.action_other_text || '');
                            }
                        });
                    }

                    // Remark & Sketch
                    if (d.remark) $('[name="fire_remark"]').val(d.remark);
                    if (d.sketch_remark) $('#fire_sketch_remark').val(d.sketch_remark);
                    if (d.sketch_recorder) $('[name="fire_sketch_recorder"]').val(d.sketch_recorder);
                    if (d.sketch_datetime) $('[name="fire_sketch_datetime"]').val(d.sketch_datetime);

                    // Signatures - sketch + handover (โหลดจาก getFile.php)
                    if (d.signatures) {
                        const sigMap = {
                            'scene_sketch': {
                                data: 'scene_sketch_data_fire',
                                canvas: 'scene_sketch_canvas_fire'
                            },
                            'receiver_signature': {
                                data: 'receiver_signature_data_fire',
                                canvas: 'sig-canvas-receiver-fire'
                            },
                            'sender_signature': {
                                data: 'sender_signature_data_fire',
                                canvas: 'sig-canvas-sender-fire'
                            }
                        };
                        Object.keys(sigMap).forEach(function(key) {
                            if (d.signatures[key] && d.signatures[key].file_id) {
                                const imgUrl = './api/incidentCheckList/getFile.php?id=' + d.signatures[key].file_id;
                                // เก็บ file_id ไว้ใน hidden input เพื่ออ้างอิงตอน save
                                $('#' + sigMap[key].data).val('existing_file_id:' + d.signatures[key].file_id);
                                setTimeout(function() {
                                    var cvs = document.getElementById(sigMap[key].canvas);
                                    if (cvs && cvs.offsetWidth > 0) {
                                        var img = new Image();
                                        img.crossOrigin = 'anonymous';
                                        img.onload = function() {
                                            var ctx = cvs.getContext('2d');
                                            ctx.drawImage(img, 0, 0, cvs.width, cvs.height);
                                        };
                                        img.src = imgUrl;
                                    }
                                }, 600);
                            }
                        });
                    }

                    // Photos (โหลดจาก getFile.php)
                    if (d.photos && d.photos.length > 0) {
                        d.photos.forEach(function(p, idx) {
                            if (p.file_id) {
                                const fileId = Date.now() + '_' + Math.random().toString(16).slice(2);
                                const photoName = p.filename || p.name || ('photo_' + (idx + 1) + '.jpg');
                                attachmentStoreFire.push({
                                    id: fileId,
                                    src: './api/incidentCheckList/getFile.php?id=' + p.file_id,
                                    name: photoName,
                                    existing: true,
                                    db_file_id: p.file_id
                                });
                            }
                        });
                        renderFireAttachmentGrid();
                        updateFireRealInput();
                    }
                } else if (res && !res.success && res.message) {
                    console.error('Fire data load error:', res.message, res);
                    Swal.fire({
                        icon: 'warning',
                        title: 'ข้อมูลเดิมมีปัญหา',
                        html: res.message + '<br><small class="text-muted">กรุณากรอกข้อมูลใหม่และบันทึกอีกครั้ง</small>',
                        confirmButtonText: 'ตกลง'
                    });
                }
                // callback หลังโหลดเสร็จ
                if (typeof onComplete === 'function') onComplete();
            },
            error: function(xhr, status, error) {
                console.error('Fire data AJAX error:', status, error);
                if (typeof onComplete === 'function') onComplete();
            }
        });
    }

    // =========================================================
    // LOAD TRAFFIC DATA TO MODAL (โหลดข้อมูลจราจรกลับเข้าฟอร์ม)
    // =========================================================
    function loadTrafficDataToModal(incidentId, onComplete) {
        $.ajax({
            url: './api/incidentCheckList/getTrafficData.php',
            method: 'GET',
            data: {
                incident_id: incidentId
            },
            dataType: 'json',
            success: function(res) {
                console.log('🚗 getTrafficData response:', res);
                // ★ เก็บ basic_info ไว้ใน global เพื่อ auto-fill พฤติการณ์คดี
                if (res && res.basic_info) window._basicInfoTraffic = res.basic_info;

                if (res && res.success && res.data) {
                    const d = res.data;

                    // --- General Info (section 1) ---
                    if (d.general_info) {
                        const g = d.general_info;
                        if (g.case_doc_no) {
                            $('[name="case_doc_no"]').val(g.case_doc_no);
                            $('#tpf_case_doc_no').val(g.case_doc_no);
                            $('#case_doc_no_traffic').val(g.case_doc_no);
                        }
                        if (g.case_date) $('[name="case_date"]').val(g.case_date);
                        if (g.case_time) $('[name="case_time"]').val(g.case_time);
                        // notify method checkboxes
                        if (g.report_channel && Array.isArray(g.report_channel)) {
                            g.report_channel.forEach(function(val) {
                                $('input[name="notify_method[]"][value="' + val + '"]').prop('checked', true);
                            });
                        }
                        if (g.report_channel_other) $('[name="notify_method_other_text"]').val(g.report_channel_other);
                        if (g.source_station) $('[name="police_station"]').val(g.source_station);
                        if (g.investigator) {
                            if (g.investigator.name) $('[name="investigator_name"]').val(g.investigator.name);
                            if (g.investigator.phone) $('[name="investigator_phone"]').val(g.investigator.phone);
                        }
                        // section 3
                        if (g.victim_know_date) $('[name="victim_know_date"]').val(g.victim_know_date);
                        if (g.victim_know_time) $('[name="victim_know_time"]').val(g.victim_know_time);
                        if (g.officer_know_date) $('[name="officer_know_date"]').val(g.officer_know_date);
                        if (g.officer_know_time) $('[name="officer_know_time"]').val(g.officer_know_time);
                        // section 4
                        if (g.inspect_date) $('[name="inspect_date"]').val(g.inspect_date);
                        if (g.inspect_time) $('[name="inspect_time"]').val(g.inspect_time);
                    }

                    // --- Scene Info (section 2) ---
                    if (d.scene_info) {
                        if (d.scene_info.crime_location) $('[name="crime_location"]').val(d.scene_info.crime_location);
                        // vehicles
                        if (d.scene_info.vehicles_at_scene && d.scene_info.vehicles_at_scene.length > 0) {
                            var vContainer = document.getElementById('tpf_vehicle_container');
                            if (vContainer) {
                                // Keep first card, remove extras, reset counter
                                $(vContainer).find('.tpf-vehicle-card:not(:first-child)').remove();
                                if (typeof tpfResetVehicleIdx === 'function') tpfResetVehicleIdx();
                                var firstVCard = $(vContainer).find('.tpf-vehicle-card').first();
                                firstVCard.find('input[type="text"]').val('');
                                firstVCard.find('[name="vehicle_plate_attach[]"]').prop('checked', true);
                                firstVCard.find('[name="vehicle_plate_none[]"]').prop('checked', false);
                                d.scene_info.vehicles_at_scene.forEach(function(v, idx) {
                                    if (idx > 0) tpfAddVehicle();
                                    var card = $(vContainer).find('.tpf-vehicle-card').eq(idx);
                                    if (card.length) {
                                        card.find('[name="vehicle_detail[]"]').val(v.detail || '');
                                        card.find('[name="vehicle_brand[]"]').val(v.brand || '');
                                        card.find('[name="vehicle_model[]"]').val(v.model || '');
                                        card.find('[name="vehicle_color[]"]').val(v.color || '');
                                        card.find('[name="vehicle_plate_no[]"]').val(v.plate_no || '');
                                        if (v.plate_status === 'ติด') {
                                            card.find('[name="vehicle_plate_attach[]"]').prop('checked', true);
                                            card.find('[name="vehicle_plate_none[]"]').prop('checked', false);
                                        } else if (v.plate_status === 'ไม่ติด') {
                                            card.find('[name="vehicle_plate_attach[]"]').prop('checked', false);
                                            card.find('[name="vehicle_plate_none[]"]').prop('checked', true);
                                        }
                                    }
                                });
                            }
                        }
                    }

                    // --- Inspectors (section 5) ---
                    if (d.inspectors && d.inspectors.length > 0) {
                        var inspContainer = document.getElementById('tpf_inspector_container');
                        if (inspContainer) {
                            // Keep first row, remove extras, reset counter
                            $(inspContainer).find('.tpf-inspector-row:not(:first-child)').remove();
                            if (typeof tpfResetInspectorIdx === 'function') tpfResetInspectorIdx();
                            $(inspContainer).find('.tpf-inspector-row').first().find('select').val('');
                            d.inspectors.forEach(function(inspId, idx) {
                                if (idx > 0) tpfAddInspector();
                                var row = $(inspContainer).find('.tpf-inspector-row').eq(idx);
                                if (row.length) row.find('[name="inspector_id[]"]').val(inspId);
                            });
                        }
                    }

                    // --- Inspection Purpose (section 6) ---
                    if (d.inspection_purpose) {
                        var ip = d.inspection_purpose;
                        if (ip.selected_purposes && Array.isArray(ip.selected_purposes)) {
                            ip.selected_purposes.forEach(function(val) {
                                $('input[name="inspect_purpose[]"][value="' + val + '"]').prop('checked', true);
                            });
                        }
                        if (ip.qty_1) $('[name="purpose_qty_1"]').val(ip.qty_1);
                        if (ip.qty_2) $('[name="purpose_qty_2"]').val(ip.qty_2);
                        if (ip.other_text) $('[name="purpose_other_text"]').val(ip.other_text);
                    }

                    // --- Forensic Officers (section 7) ---
                    if (d.forensic_officers && d.forensic_officers.length > 0) {
                        var forContainer = document.getElementById('tpf_forensic_container');
                        if (forContainer) {
                            // Keep first row, remove extras, reset counter
                            $(forContainer).find('.tpf-forensic-row:not(:first-child)').remove();
                            if (typeof tpfResetForensicIdx === 'function') tpfResetForensicIdx();
                            var firstForRow = $(forContainer).find('.tpf-forensic-row').first();
                            firstForRow.find('select').val('');
                            firstForRow.find('input').val('');
                            d.forensic_officers.forEach(function(officer, idx) {
                                if (idx > 0) tpfAddForensic();
                                var row = $(forContainer).find('.tpf-forensic-row').eq(idx);
                                if (row.length) {
                                    row.find('[name="forensic_id[]"]').val(officer.user_id || '');
                                    row.find('[name="forensic_position[]"]').val(officer.position || '');
                                }
                            });
                        }
                    }

                    // --- Forensic Results (section 8) ---
                    if (d.forensic_results) {
                        var fr = d.forensic_results;
                        if (fr.case_behavior) {
                            $('[name="case_behavior"]').val(fr.case_behavior);
                        } else if (window._basicInfoTraffic) {
                            $('[name="case_behavior"]').val(window._basicInfoTraffic);
                        }
                        if (fr.inspection_target) $('[name="forensic_inspection_target"]').val(fr.inspection_target);
                        if (fr.inspection_location) $('[name="forensic_inspection_location"]').val(fr.inspection_location);
                        var trafficLabUnit = window.labUnitsToString(fr.lab_unit);
                        if (!trafficLabUnit && Array.isArray(d.evidences)) {
                            d.evidences.some(function(ev) {
                                var candidate = window.labUnitsToArray(ev && ev.lab_unit)
                                    .filter(function(k) { return k !== 'traffic'; });
                                if (candidate.length) {
                                    trafficLabUnit = candidate.join(',');
                                    return true;
                                }
                                return false;
                            });
                        }
                        window.setLabUnits($('[name="forensic_lab_unit"]'), trafficLabUnit);
                        if (fr.inspection_date) $('[name="forensic_inspect_date"]').val(fr.inspection_date);
                        if (fr.inspection_time) $('[name="forensic_inspect_time"]').val(fr.inspection_time);

                        // Vehicle analysis
                        if (fr.vehicle_analysis && fr.vehicle_analysis.length > 0) {
                            var aContainer = document.getElementById('tpf_analysis_vehicle_container');
                            if (aContainer) {
                                // Keep first card, remove extras, reset counter
                                $(aContainer).find('.tpf-analysis-card:not(:first-child)').remove();
                                if (typeof tpfResetAnalysisVIdx === 'function') tpfResetAnalysisVIdx();
                                var firstACard = $(aContainer).find('.tpf-analysis-card').first();
                                firstACard.find('input[type="text"], input[type="number"]').val('');
                                firstACard.find('.tpf-mod-check[value="ไม่มี"]').prop('checked', true);
                                firstACard.find('.tpf-mod-check[value="มี"]').prop('checked', false);
                                firstACard.find('[name="forensic_v_mod_detail[]"]').prop('disabled', true);
                                // Reset trace rows in first card
                                ['front', 'left', 'right', 'back'].forEach(function(side) {
                                    var tbody = firstACard.find('.tpf-trace-body[data-side="' + side + '"]');
                                    tbody.find('tr:not(:first-child)').remove();
                                    tbody.find('input').val('');
                                });
                                fr.vehicle_analysis.forEach(function(va, idx) {
                                    if (idx > 0) tpfAddAnalysisVehicle();
                                    var card = $(aContainer).find('.tpf-analysis-card').eq(idx);
                                    if (card.length) {
                                        card.find('[name="forensic_v_condition[]"]').val(va.condition || '');
                                        card.find('[name="forensic_v_mod_detail[]"]').val(va.mod_detail || '');
                                        // mod_status checkboxes
                                        var vIdx = card.data('v-idx') || (idx + 1);
                                        if (va.mod_status === 'มี') {
                                            card.find('[name="forensic_v_mod_status_' + vIdx + '"][value="มี"]').prop('checked', true).trigger('change');
                                        } else {
                                            card.find('[name="forensic_v_mod_status_' + vIdx + '"][value="ไม่มี"]').prop('checked', true);
                                        }
                                        // Traces per side
                                        if (va.traces) {
                                            var sides = ['front', 'left', 'right', 'back'];
                                            sides.forEach(function(side) {
                                                if (va.traces[side] && va.traces[side].length > 0) {
                                                    var tbody = card.find('.tpf-trace-body[data-side="' + side + '"]');
                                                    tbody.empty();
                                                    va.traces[side].forEach(function(trace, tIdx) {
                                                        var newVIdx = card.data('v-idx') || (idx + 1);
                                                        var tr = $('<tr><td><input type="text" name="trace_' + side + '_detail_' + newVIdx + '[]"><button type="button" class="btn btn-sm btn-hw-open btn-hw-dynamic" title="เขียนด้วยลายมือ"><i class="fas fa-pen"></i></button></td><td><input type="number" name="trace_' + side + '_height_' + newVIdx + '[]"></td><td>' + (tIdx > 0 ? '<button type="button" class="tpf-del-btn" onclick="this.closest(\'tr\').remove()">×</button>' : '') + '</td></tr>');
                                                        tr.find('[name^="trace_' + side + '_detail"]').val(trace.detail || '');
                                                        tr.find('[name^="trace_' + side + '_height"]').val(trace.height || '');
                                                        tbody.append(tr);
                                                    });
                                                }
                                            });
                                        }
                                    }
                                });
                            }
                        }
                    } else if (window._basicInfoTraffic) {
                        $('[name="case_behavior"]').val(window._basicInfoTraffic);
                    }

                    // --- Comparison Results (section 9) ---
                    if (d.comparison_results) {
                        if (d.comparison_results.total_items) $('[name="forensic_compare_v_count"]').val(d.comparison_results.total_items);
                        if (d.comparison_results.comparisons && d.comparison_results.comparisons.length > 0) {
                            var cmpContainer = document.getElementById('tpf_compare_container');
                            if (cmpContainer) {
                                // Keep first row, remove extras, reset counter
                                $(cmpContainer).find('.tpf-compare-row:not(:first-child)').remove();
                                if (typeof tpfResetCompareIdx === 'function') tpfResetCompareIdx();
                                $(cmpContainer).find('.tpf-compare-row').first().find('input').val('');
                                d.comparison_results.comparisons.forEach(function(cmp, idx) {
                                    if (idx > 0) tpfAddCompareRow();
                                    var row = $(cmpContainer).find('.tpf-compare-row').eq(idx);
                                    if (row.length) {
                                        row.find('[name="compare_trace_type[]"]').val(cmp.trace_type || '');
                                        row.find('[name="compare_area_a[]"]').val(cmp.area_a || '');
                                        row.find('[name="compare_ref_a[]"]').val(cmp.ref_a || '');
                                        row.find('[name="compare_area_b[]"]').val(cmp.area_b || '');
                                        row.find('[name="compare_ref_b[]"]').val(cmp.ref_b || '');
                                    }
                                });
                            }
                        }
                    }

                    // --- Opinion (section 10) ---
                    if (d.opinion) $('[name="forensic_opinion"]').val(d.opinion);

                    // --- Handover (section 11) ---
                    if (d.handover) {
                        var h = d.handover;
                        if (h.inspection_end_date) $('[name="inspection_end_date"]').val(h.inspection_end_date);
                        if (h.inspection_end_time) $('[name="inspection_end_time"]').val(h.inspection_end_time);
                        if (h.receiver) {
                            if (h.receiver.name_id) $('[name="receiver_name"]').val(h.receiver.name_id);
                            if (h.receiver.position) $('[name="receiver_position"]').val(h.receiver.position);
                        }
                        if (h.sender) {
                            if (h.sender.name_id) $('[name="sender_name"]').val(h.sender.name_id);
                            if (h.sender.position) $('[name="sender_position"]').val(h.sender.position);
                        }
                    }

                    // --- Photo Records (section 12) ---
                    if (d.photo_records) {
                        if (d.photo_records.start) $('[name="photo_id_start_traffic"]').val(d.photo_records.start);
                        if (d.photo_records.end) $('[name="photo_id_end_traffic"]').val(d.photo_records.end);
                        if (d.photo_records.amount) $('[name="photo_amount_traffic"]').val(d.photo_records.amount);
                        if (d.photo_records.recorder_name) {
                            let rName = d.photo_records.recorder_name;
                            if (!isNaN(rName) && typeof inspectorOptionsHTMLTraffic !== 'undefined') {
                                let tmpSel = document.createElement('select');
                                tmpSel.innerHTML = inspectorOptionsHTMLTraffic;
                                let opt = tmpSel.querySelector('option[value="' + rName + '"]');
                                if (opt) rName = opt.text;
                            }
                            $('[name="photographer_name_traffic"]').val(rName);
                        }
                        if (d.photo_records.recorder_datetime) $('[name="photographer_datetime_traffic"]').val(d.photo_records.recorder_datetime);
                    }

                    // --- Signatures ---
                    if (d.signatures) {
                        if (d.signatures.receiver_sig && d.signatures.receiver_sig.base64) {
                            $('#tpf_receiver_sig_data').val(d.signatures.receiver_sig.base64);
                            setTimeout(function() {
                                var cvs = document.getElementById('tpf_sig_receiver');
                                if (cvs && cvs.width > 0) {
                                    var img = new Image();
                                    img.onload = function() {
                                        var ctx = cvs.getContext('2d');
                                        ctx.drawImage(img, 0, 0, cvs.width, cvs.height);
                                    };
                                    img.src = d.signatures.receiver_sig.base64;
                                }
                            }, 300);
                        }
                        if (d.signatures.sender_sig && d.signatures.sender_sig.base64) {
                            $('#tpf_sender_sig_data').val(d.signatures.sender_sig.base64);
                            setTimeout(function() {
                                var cvs = document.getElementById('tpf_sig_sender');
                                if (cvs && cvs.width > 0) {
                                    var img = new Image();
                                    img.onload = function() {
                                        var ctx = cvs.getContext('2d');
                                        ctx.drawImage(img, 0, 0, cvs.width, cvs.height);
                                    };
                                    img.src = d.signatures.sender_sig.base64;
                                }
                            }, 300);
                        }
                    }

                    // --- Photos (store เดียวร่วมฟอร์มปกติ+เสมือน) ---
                    if (d.photos && d.photos.length > 0) {
                        window.__trafficLoadingPhotos = true; // กันไม่ให้ render เขียนทับรหัสภาพที่โหลดมา
                        if (typeof window.attachmentStoreTraffic === 'undefined') window.attachmentStoreTraffic = [];
                        attachmentStoreTraffic = [];
                        window.deletedExistingPhotosTraffic = [];
                        d.photos.forEach(function(p, idx) {
                            var photoSrc = '';
                            var photoName = p.filename || p.name || ('photo_' + (idx + 1) + '.jpg');

                            if (p.file_id) {
                                // กรณี A: BLOB ใน DB
                                photoSrc = './api/incidentCheckList/getFile.php?id=' + p.file_id;
                                attachmentStoreTraffic.push({
                                    id: 'existing_' + p.file_id,
                                    file: null,
                                    src: photoSrc,
                                    base64: photoSrc,
                                    name: photoName,
                                    filename: photoName,
                                    existing: true,
                                    isExisting: true,
                                    db_file_id: p.file_id,
                                    caption: p.caption || '',
                                    size: p.size || 'N/A',
                                    date: p.date || '-'
                                });
                            } else if (p.base64) {
                                // กรณี B: base64 (legacy) — ไม่มี file_id ลบไม่ได้ผ่าน BLOB
                                photoSrc = p.base64.startsWith('data:') ? p.base64 : ('data:image/jpeg;base64,' + p.base64);
                                attachmentStoreTraffic.push({
                                    id: 'existing_legacy_' + idx,
                                    file: null,
                                    src: photoSrc,
                                    base64: photoSrc,
                                    name: photoName,
                                    filename: photoName,
                                    existing: true,
                                    isExisting: true,
                                    db_file_id: null,
                                    caption: p.caption || '',
                                    size: p.size || 'N/A',
                                    date: p.date || '-'
                                });
                            }
                        });
                        if (typeof window.trafficRenderAll === 'function') {
                            window.trafficRenderAll();
                        } else {
                            if (typeof renderAttachmentStoreTraffic === 'function') renderAttachmentStoreTraffic();
                            if (typeof tpfRenderPhotosFromStore === 'function') tpfRenderPhotosFromStore();
                        }
                        setTimeout(function() {
                            window.__trafficLoadingPhotos = false; // โหลดเสร็จ — ให้แนบ/ลบอัปเดตรหัสได้
                        }, 250);
                    }

                }

                // --- Edit Info ---
                if (res && res.edit_info) {
                    const ei = res.edit_info;
                    $('#editCountTraffic').text(ei.count_edit || 0);
                    $('#editCountTrafficPdf').text(ei.count_edit || 0);
                    $('#editInfoTraffic').removeClass('d-none');
                    $('#editInfoTrafficPdf').removeClass('d-none');
                }

                if (res && !res.success && res.message) {
                    console.warn('Traffic data load:', res.message);
                }
                if (typeof onComplete === 'function') onComplete();
            },
            error: function(xhr, status, error) {
                console.error('Traffic data AJAX error:', status, error);
                if (typeof onComplete === 'function') onComplete();
            }
        });
    }

    // =========================================================
    // Scene Evidence (type 07): Load Data to Modal
    // =========================================================
    function loadSceneEvidenceDataToModal(incidentId, onComplete) {
        $.ajax({
            url: './api/incidentCheckList/getSceneEvidenceData.php',
            method: 'GET',
            data: {
                incident_id: incidentId
            },
            dataType: 'json',
            success: function(res) {
                console.log('[EV7] getSceneEvidenceData response:', res);
                if (res && res.success && res.data) {
                    const rd = res.data;
                    const d = rd.checklist_data;
                    if (!d) {
                        if (typeof onComplete === 'function') onComplete();
                        return;
                    }

                    // --- Edit info ---
                    if (rd.edit_info) {
                        const ei = rd.edit_info;
                        $('#editCountEV7').text(ei.count_edit || 0);
                        $('#editCountEV7Pdf').text(ei.count_edit || 0);
                        if (ei.edit_date) {
                            const ed = new Date(ei.edit_date);
                            const dd = String(ed.getDate()).padStart(2, '0');
                            const mm = String(ed.getMonth() + 1).padStart(2, '0');
                            const yyyy = ed.getFullYear() + 543;
                            const hh = String(ed.getHours()).padStart(2, '0');
                            const mi = String(ed.getMinutes()).padStart(2, '0');
                            const editDateStr = dd + '/' + mm + '/' + yyyy + ' ' + hh + ':' + mi + ' น.';
                            $('#editDateEV7').text(editDateStr);
                            $('#editDateEV7Pdf').text(editDateStr);
                        } else {
                            $('#editDateEV7').text('-');
                            $('#editDateEV7Pdf').text('-');
                        }
                        $('#editInfoEV7').removeClass('d-none');
                        $('#editInfoEV7Pdf').removeClass('d-none');
                    }

                    // --- General Info ---
                    if (d.general_info) {
                        const g = d.general_info;
                        if (g.doc_no) {
                            var docNoTH_ev7b = (window.toThaiDocNo ? toThaiDocNo(g.doc_no) : g.doc_no);
                            $('#doc_no_ev7').val(g.doc_no);
                            $('#receiveNoti_No_ev7').text(docNoTH_ev7b);
                            $('#sevpf_doc_no').val(g.doc_no);
                            $('#sevpf_receiveNoti_No').text(docNoTH_ev7b);
                        }
                        if (g.report_no) {
                            $('#report_no_ev7').val(g.report_no);
                            $('#receiveNotiReportNo_ev7').text(g.report_no);
                            $('#sevpf_report_no').val(g.report_no);
                            $('#sevpf_receiveNotiReportNo').text(g.report_no);
                        }
                        if (g.receive_date) {
                            $('#ev7_receive_date').val(g.receive_date);
                            $('[name="sevpf_receive_date"]').val(g.receive_date);
                        }
                        if (g.receive_time) {
                            $('#ev7_receive_time').val(g.receive_time);
                            $('[name="sevpf_receive_time"]').val(g.receive_time);
                        }
                        if (g.unit_type) $('#ev7_unit_type').val(g.unit_type);
                        if (g.unit_name) {
                            $('#ev7_unit_name').val(g.unit_name);
                            $('[name="sevpf_unit_name"]').val(g.unit_name);
                        }
                        if (g.notify_method) {
                            var nm = Array.isArray(g.notify_method) ? g.notify_method : [g.notify_method];
                            nm.forEach(function(v) {
                                var stdVal = v;
                                var pdfVal = v;

                                // Normalize legacy/current values between standard and PDF forms
                                if (v === 'ตามหนังสือ') pdfVal = 'ทางหนังสือ';
                                if (v === 'ทางหนังสือ') stdVal = 'ตามหนังสือ';

                                $('input[name="ev7_notify_method[]"][value="' + stdVal + '"]').prop('checked', true);
                                $('input[name="sevpf_notify_method[]"][value="' + pdfVal + '"]').prop('checked', true);
                            });
                        }
                        if (g.notify_method_other_text) {
                            $('#ev7_notify_method_other_text').val(g.notify_method_other_text).show().prop('disabled', false);
                            $('#sevpf_notify_method_other_text').val(g.notify_method_other_text);
                        }
                        if (g.police_station) {
                            if ($('#ev7_police_station option[value="' + g.police_station + '"]').length > 0) {
                                $('#ev7_police_station').val(g.police_station);
                            } else {
                                $('#ev7_police_station').append(new Option(g.police_station, g.police_station, true, true));
                            }
                            $('[name="sevpf_police_station"]').val(g.police_station);
                        }
                        if (g.document_no) {
                            $('#ev7_document_no').val(g.document_no);
                            $('[name="sevpf_document_no"]').val(g.document_no);
                        } {
                            var _dd7 = g.document_date || new Date().toISOString().slice(0, 10);
                            $('#ev7_document_date').val(_dd7);
                            $('[name="sevpf_document_date"]').val(_dd7);
                        }
                        if (g.case_no) {
                            var caseNoTH_ev7 = (window.toThaiDocNo ? toThaiDocNo(g.case_no) : g.case_no);
                            $('#ev7_case_no').val(caseNoTH_ev7);
                            $('[name="sevpf_case_no"]').val(caseNoTH_ev7);
                        }
                        var incidentLocationLoaded = (g.incident_location || g.location_detail || '').toString().trim();
                        if (incidentLocationLoaded) {
                            $('#ev7_incident_location').val(incidentLocationLoaded);
                            $('[name="sevpf_incident_location"]').val(incidentLocationLoaded);
                            $('[name="sevpf_incident_location_2"]').val('');
                            if (typeof window.sevpfRefreshAutoWrapGroups === 'function') window.sevpfRefreshAutoWrapGroups();
                        }
                        if (g.incident_date) {
                            $('#ev7_incident_date').val(g.incident_date);
                            $('[name="sevpf_incident_date"]').val(g.incident_date);
                        }
                        if (g.incident_time) {
                            $('#ev7_incident_time').val(g.incident_time);
                            $('[name="sevpf_incident_time"]').val(g.incident_time);
                        }
                        if (g.investigator_name) {
                            $('#ev7_investigator_name').val(g.investigator_name);
                            $('[name="sevpf_investigator_name"]').val(g.investigator_name);
                        }
                        if (g.send_request) {
                            $('#ev7_send_request').val(g.send_request);
                            $('[name="sevpf_send_request"]').val(g.send_request);
                        }
                    }

                    // --- Evidence Items (dynamic rows) — lab_unit ฝังใน row เดียวกัน ---
                    var ev7Items = (d.evidence_items && d.evidence_items.length) ? d.evidence_items : ((d.evidences && d.evidences.length) ? d.evidences : []);
                    if (ev7Items.length > 0) {
                        var labUnits = (d.collected_evidence && d.collected_evidence.lab_units) ? d.collected_evidence.lab_units : [];
                        // fallback: ดึงจาก evidences[].lab_unit ถ้า collected_evidence.lab_units ไม่มี
                        if (!labUnits.length && d.evidences && d.evidences.length) {
                            labUnits = d.evidences.map(function(ev) {
                                return ev.lab_unit || '';
                            });
                        }
                        ev7Items.forEach(function(item, idx) {
                            if (idx > 0 && typeof addEvidenceItemEV7 === 'function') addEvidenceItemEV7();
                            var rows = $('[name="ev7_evidence_item[]"]');
                            if (rows.eq(idx).length) rows.eq(idx).val((item && (item.description || item.detail || item.item)) || (typeof item === 'string' ? item : ''));
                            // set lab_unit inline
                            var stdLabRows = $('[name="ev7_lab_unit[]"]');
                            if (stdLabRows.eq(idx).length) window.setLabUnits(stdLabRows.eq(idx), labUnits[idx]);
                            // PDF form
                            if (idx > 0 && typeof sevpfAddEvidenceItem === 'function') sevpfAddEvidenceItem();
                            var pdfRows = $('[name="sevpf_evidence_item[]"]');
                            if (pdfRows.eq(idx).length) pdfRows.eq(idx).val((item && (item.description || item.detail || item.item)) || (typeof item === 'string' ? item : ''));
                            var pdfLabRows = $('[name="sevpf_lab_unit[]"]');
                            if (pdfLabRows.eq(idx).length) window.setLabUnits(pdfLabRows.eq(idx), labUnits[idx]);
                        });
                    }

                    // --- Purpose ---
                    if (d.purpose) {
                        if (d.purpose.types && Array.isArray(d.purpose.types)) {
                            d.purpose.types.forEach(function(v) {
                                $('input[name="ev7_purpose[]"][value="' + v + '"]').prop('checked', true);
                                $('input[name="sevpf_purpose[]"][value="' + v + '"]').prop('checked', true);
                            });
                        }
                        if (d.purpose.detail) {
                            $('#ev7_purpose_detail').val(d.purpose.detail);
                            $('[name="sevpf_purpose_detail"]').val(d.purpose.detail);
                        }
                    }

                    // --- Inspection ---
                    if (d.inspection) {
                        if (d.inspection.location) {
                            $('#ev7_inspect_location').val(d.inspection.location);
                            $('[name="sevpf_inspect_location"]').val(d.inspection.location);
                        }
                        if (d.inspection.date) {
                            $('#ev7_inspect_date').val(d.inspection.date);
                            $('[name="sevpf_inspect_date"]').val(d.inspection.date);
                        }
                        if (d.inspection.time) {
                            $('#ev7_inspect_time').val(d.inspection.time);
                            $('[name="sevpf_inspect_time"]').val(d.inspection.time);
                        }
                    }

                    // --- Exhibit Descriptions (dynamic) ---
                    if (d.exhibit_descriptions && d.exhibit_descriptions.length > 0) {
                        d.exhibit_descriptions.forEach(function(item, idx) {
                            var description = (item && typeof item === 'object') ? (item.description || item.detail || '') : (item || '');
                            if (idx > 0 && typeof addExhibitDescEV7 === 'function') addExhibitDescEV7();
                            var rows = $('[name="ev7_exhibit_desc[]"]');
                            if (rows.eq(idx).length) rows.eq(idx).val(description);
                            if (idx > 0 && typeof sevpfAddExhibitDesc === 'function') sevpfAddExhibitDesc();
                            var pdfRows = $('[name="sevpf_exhibit_desc[]"]');
                            if (pdfRows.eq(idx).length) pdfRows.eq(idx).val(description);
                        });
                    }

                    // --- Collected Evidence ---
                    if (d.collected_evidence) {
                        var ce = d.collected_evidence;
                        if (ce.types && Array.isArray(ce.types)) {
                            ce.types.forEach(function(v) {
                                $('input[name="ev7_collect_type[]"][value="' + v + '"]').prop('checked', true);
                                $('input[name="sevpf_collect_type[]"][value="' + v + '"]').prop('checked', true);
                            });
                        }
                        if (ce.sheet_count) {
                            $('#ev7_collect_sheet_count').val(ce.sheet_count);
                            $('[name="sevpf_collect_sheet_count"]').val(ce.sheet_count);
                        }
                        if (ce.location) {
                            $('#ev7_collect_location').val(ce.location);
                            $('[name="sevpf_collect_location"]').val(ce.location);
                        }
                        if (ce.details && ce.details.length > 0) {
                            ce.details.forEach(function(detail, idx) {
                                if (idx > 0 && typeof addCollectDetailEV7 === 'function') addCollectDetailEV7();
                                var rows = $('[name="ev7_collect_detail[]"]');
                                if (rows.eq(idx).length) rows.eq(idx).val(detail || '');
                                if (idx > 0 && typeof sevpfAddCollectDetail === 'function') sevpfAddCollectDetail();
                                var pdfRows = $('[name="sevpf_collect_detail[]"]');
                                if (pdfRows.eq(idx).length) pdfRows.eq(idx).val(detail || '');
                            });
                        }
                        if (ce.other_evidence && ce.other_evidence.length > 0) {
                            ce.other_evidence.forEach(function(oe, idx) {
                                if (idx > 0 && typeof addOtherEvidenceEV7 === 'function') addOtherEvidenceEV7();
                                var rows = $('[name="ev7_other_evidence[]"]');
                                if (rows.eq(idx).length) rows.eq(idx).val(oe || '');
                            });
                        }
                        if (ce.other_evidence_text) {
                            $('[name="sevpf_other_evidence_text"]').val(ce.other_evidence_text);
                        }

                        // lab_units ถูกโหลดพร้อม evidence_items ด้านบนแล้ว (inline in evidence rows)
                    }

                    // --- Evidence Handling ---
                    if (d.evidence_handling) {
                        var eh = d.evidence_handling;
                        if (eh.witness_name) {
                            $('#ev7_witness_name').val(eh.witness_name);
                            $('[name="sevpf_witness_name"]').val(eh.witness_name);
                        }
                        if (eh.witness_form) {
                            $('#ev7_witness_form').val(eh.witness_form);
                            $('[name="sevpf_witness_form"]').val(eh.witness_form);
                        }
                        if (eh.witness_detail) {
                            $('#ev7_witness_detail').val(eh.witness_detail);
                            $('[name="sevpf_witness_detail"]').val(eh.witness_detail);
                        }
                        if (eh.handover_method) {
                            $('#ev7_handover_method').val(eh.handover_method);
                        }
                        if (eh.handover_method_checks && Array.isArray(eh.handover_method_checks)) {
                            eh.handover_method_checks.forEach(function(v) {
                                $('input[name="sevpf_handover_method_check[]"][value="' + v + '"]').prop('checked', true);
                            });
                        }
                        if (eh.handover_method_detail) {
                            $('#ev7_handover_method_detail').val(eh.handover_method_detail);
                            $('[name="sevpf_handover_method_detail"]').val(eh.handover_method_detail);
                        } {
                            $('#ev7_handover_item_ref').val('3.2');
                            $('[name="sevpf_handover_item_ref"]').val('3.2');
                        }
                        if (eh.handover_to) {
                            $('#ev7_handover_to').val(eh.handover_to);
                            $('[name="sevpf_handover_to"]').val(eh.handover_to);
                        }
                        if (eh.handover_purpose) {
                            $('#ev7_handover_purpose').val(eh.handover_purpose);
                            $('[name="sevpf_handover_purpose"]').val(eh.handover_purpose);
                        }
                        if (eh.next_action_lines && Array.isArray(eh.next_action_lines)) {
                            $('#ev7_handover_next_action').val(eh.next_action_lines.join('\n').replace(/\n+$/g, ''));
                            var lineFields = $('[name="sevpf_handover_next_action"], [name^="sevpf_handover_more_"]');
                            lineFields.each(function(i) {
                                $(this).val(eh.next_action_lines[i] || '');
                            });
                        } else if (eh.next_action_full) {
                            $('#ev7_handover_next_action').val(eh.next_action_full);
                            var firstLine = $('[name="sevpf_handover_next_action"]');
                            if (firstLine.length) {
                                firstLine.val(eh.next_action_full).trigger('input');
                            }
                        }
                    }

                    // --- Inspectors ---
                    if (d.inspectors && d.inspectors.length > 0) {
                        d.inspectors.forEach(function(insp, idx) {
                            var inspId = (insp && typeof insp === 'object') ? (insp.id || insp.user_id || insp.value || '') : insp;
                            if (!inspId) return;
                            if (idx > 0) {
                                $('#btn_add_inspector_ev7').trigger('click');
                                if (typeof sevpfAddInspector === 'function') sevpfAddInspector();
                            }
                            var rows = $('#ev7_inspector_container .ev7-inspector-select');
                            if (rows.eq(idx).length) rows.eq(idx).val(inspId).trigger('change');
                            var pdfRows = $('#sevpf_inspector_container select[name="ev7_inspector_id[]"]');
                            if (pdfRows.eq(idx).length) pdfRows.eq(idx).val(inspId).trigger('change');
                        });
                    }

                    // --- Signer ---
                    if (d.signer) {
                        if (d.signer.id) {
                            $('#ev7_signer_id').val(d.signer.id).trigger('change');
                            $('#ev7_sender_id').val(d.signer.id).trigger('change');
                        }
                        if (d.signer.position) {
                            $('#ev7_signer_position').val(d.signer.position);
                            $('#ev7_sender_position').val(d.signer.position);
                            $('[name="sevpf_signer_position"]').val(d.signer.position);
                        }
                        if (d.signer.name) $('[name="sevpf_signer_name"]').val(d.signer.name);
                        if (d.signer.fullname) $('[name="sevpf_signer_fullname"]').val(d.signer.fullname);
                        if (d.signer.date) $('#ev7_sign_date').val(d.signer.date);
                    }

                    // --- PDF form specific fields ---
                    if (d.pdf_form) {
                        if (d.pdf_form.report_ref) $('[name="sevpf_report_ref"]').val(d.pdf_form.report_ref).trigger('input');
                        if (d.pdf_form.report_year) $('[name="sevpf_report_year"]').val(d.pdf_form.report_year).trigger('input');
                        if (d.pdf_form.unit_type_checks && Array.isArray(d.pdf_form.unit_type_checks)) {
                            d.pdf_form.unit_type_checks.forEach(function(v) {
                                $('input[name="sevpf_unit_type_check[]"][value="' + v + '"]').prop('checked', true);
                            });
                        }
                        if (d.pdf_form.center_name) $('[name="sevpf_center_name"]').val(d.pdf_form.center_name);
                        if (d.pdf_form.province_name) $('[name="sevpf_province_name"]').val(d.pdf_form.province_name);
                        if (d.pdf_form.sign_day) $('[name="sevpf_sign_day"]').val(d.pdf_form.sign_day);
                        if (d.pdf_form.sign_month) $('[name="sevpf_sign_month"]').val(d.pdf_form.sign_month);
                        if (d.pdf_form.sign_year) $('[name="sevpf_sign_year"]').val(d.pdf_form.sign_year);
                    }

                    // --- Photo Records ---
                    if (d.photo_records) {
                        if (d.photo_records.start) {
                            $('#ev7_photo_id_start').val(d.photo_records.start);
                            $('[name="photo_id_start_ev7"]').val(d.photo_records.start);
                        }
                        if (d.photo_records.end) {
                            $('#ev7_photo_id_end').val(d.photo_records.end);
                            $('[name="photo_id_end_ev7"]').val(d.photo_records.end);
                        }
                        if (d.photo_records.amount) {
                            $('#ev7_photo_amount').val(d.photo_records.amount);
                            $('[name="photo_amount_ev7"]').val(d.photo_records.amount);
                        }
                        if (d.photo_records.recorder_name || d.photo_records.photographer_name) {
                            let rName = d.photo_records.recorder_name || d.photo_records.photographer_name;
                            if (!isNaN(rName) && typeof inspectorOptionsEV7 !== 'undefined') {
                                let tmpSel = document.createElement('select');
                                tmpSel.innerHTML = inspectorOptionsEV7;
                                let opt = tmpSel.querySelector('option[value="' + rName + '"]');
                                if (opt) rName = opt.text;
                            }
                            $('[name="ev7_photographer_name"]').val(rName);
                        }
                        if (d.photo_records.recorder_datetime || d.photo_records.photographer_datetime) {
                            $('[name="ev7_photographer_datetime"]').val(d.photo_records.recorder_datetime || d.photo_records.photographer_datetime);
                        }
                    }

                    // --- Signatures (รองรับ BLOB file_id, base64, และ filename แบบเก่า) ---
                    if (d.signatures) {
                        var drawSignatureToCanvas = function(canvasId, sigInfo) {
                            if (!sigInfo) return;
                            var sigUrl = '';
                            if (sigInfo.file_id) {
                                sigUrl = './api/incidentCheckList/getFile.php?id=' + sigInfo.file_id;
                            } else if (sigInfo.base64) {
                                sigUrl = sigInfo.base64;
                            } else if (sigInfo.filename) {
                                sigUrl = './uploads/checklist_signatures/' + sigInfo.filename;
                            }
                            if (!sigUrl) return;
                            setTimeout(function() {
                                var cvs = document.getElementById(canvasId);
                                if (!cvs) return;
                                var ctx = cvs.getContext('2d');
                                if (!ctx) return;
                                var img = new Image();
                                img.crossOrigin = 'anonymous';
                                img.onload = function() {
                                    ctx.clearRect(0, 0, cvs.width, cvs.height);
                                    ctx.drawImage(img, 0, 0, cvs.width, cvs.height);
                                };
                                img.src = sigUrl;
                            }, 200);
                        };

                        drawSignatureToCanvas('sig-canvas-ev7-sender', d.signatures.signer_sig || d.signatures.deliverer_sig);
                        drawSignatureToCanvas('sig-canvas-ev7-receiver', d.signatures.receiver_sig);
                        drawSignatureToCanvas('sevpf_sig_receiver', d.signatures.receiver_sig);
                        drawSignatureToCanvas('sevpf_sig_sender', d.signatures.deliverer_sig);
                    }

                    // --- Photos (รองรับ BLOB file_id, base64, และ filename แบบเก่า) ---
                    if (d.photos && d.photos.length > 0) {
                        window.__ev7LoadingPhotos = true; // กันไม่ให้ render เขียนทับรหัสภาพที่โหลดมา
                        attachmentStoreEV7 = [];
                        d.photos.forEach(function(p) {
                            var photoSrc = '';
                            if (p.file_id) {
                                photoSrc = './api/incidentCheckList/getFile.php?id=' + p.file_id;
                            } else if (p.base64) {
                                photoSrc = p.base64;
                            } else if (p.filename) {
                                photoSrc = './uploads/checklist_photos/' + p.filename;
                            }
                            if (!photoSrc) return;
                            attachmentStoreEV7.push({
                                id: 'ev7_existing_' + (p.file_id || Date.now() + '_' + Math.random().toString(36).substr(2, 5)),
                                src: photoSrc,
                                name: p.original_name || p.filename || 'photo.png',
                                file: null,
                                existing: true,
                                db_file_id: p.file_id || null,
                                db_filename: p.filename || null
                            });
                        });
                        if (typeof renderEV7AttachmentGrid === 'function') renderEV7AttachmentGrid();
                        if (typeof sevpfRenderPhotosFromStore === 'function') sevpfRenderPhotosFromStore();
                        window.__ev7LoadingPhotos = false; // โหลดเสร็จ — ให้แนบ/ลบอัปเดตรหัสได้ตามปกติ
                    }

                    // --- PDF receiver / sender ---
                    if (d.handover) {
                        if (d.handover.receiver_id) $('#ev7_receiver_id').val(d.handover.receiver_id).trigger('change');
                        if (d.handover.receiver_pos) $('#ev7_receiver_position').val(d.handover.receiver_pos);
                        if (d.handover.deliverer_id) $('#ev7_sender_id').val(d.handover.deliverer_id).trigger('change');
                        if (d.handover.deliverer_pos) $('#ev7_sender_position').val(d.handover.deliverer_pos);
                        if (d.handover.receiver_id) $('#sevpf_receiver_id').val(d.handover.receiver_id).trigger('change');
                        if (d.handover.receiver_pos) $('#sevpf_receiver_position').val(d.handover.receiver_pos);
                        if (d.handover.deliverer_id) $('#sevpf_sender_id').val(d.handover.deliverer_id).trigger('change');
                        if (d.handover.deliverer_pos) $('#sevpf_sender_position').val(d.handover.deliverer_pos);
                    }

                } else if (res && !res.success && res.message) {
                    console.warn('[EV7] data load:', res.message);
                }
                if (typeof onComplete === 'function') onComplete();
            },
            error: function(xhr, status, error) {
                console.error('[EV7] data AJAX error:', status, error);
                if (typeof onComplete === 'function') onComplete();
            }
        });
    }

    // =========================================================
    // Scene Evidence (type 07): Prefill from Incident Data
    // =========================================================
    function prefillIncidentDataForSceneEvidence(incidentId) {
        console.log('[EV7 Prefill] เริ่มดึงข้อมูลรับแจ้งเหตุ ID:', incidentId);
        $.ajax({
            url: '/csims/api/ReceiveNoti/getDataByID.php',
            type: 'GET',
            dataType: 'json',
            data: {
                id: incidentId
            },
            success: function(response) {
                console.log('[EV7 Prefill] API response:', response);
                if (response.status === 'success' && response.data) {
                    const data = response.data;
                    const setValueIfBlank = function(selector, value, triggerChange) {
                        const val = (value || '').toString().trim();
                        if (!val) return;
                        $(selector).each(function() {
                            const current = ($(this).val() || '').toString().trim();
                            if (current) return;
                            $(this).val(val);
                            if (triggerChange) $(this).trigger('change');
                        });
                    };
                    const fillCheckboxIfNoneChecked = function(name, value) {
                        const val = (value || '').toString().trim();
                        if (!val) return;
                        if ($('input[name="' + name + '"]:checked').length > 0) return;
                        $('input[name="' + name + '"][value="' + val + '"]').prop('checked', true).trigger('change');
                    };

                    const docNo = (data.receiveNoti_No || $('#doc_no_ev7').val() || $('#sevpf_doc_no').val() || '').toString().trim();
                    const reportNo = (data.receiveNotiReportNo || $('#report_no_ev7').val() || $('#sevpf_report_no').val() || '').toString().trim();

                    if (docNo) {
                        // hidden input เก็บค่าอังกฤษไว้สำหรับสร้างเอกสาร, ช่องแสดงใช้ภาษาไทย
                        var docNoTH_ev7 = (window.toThaiDocNo ? toThaiDocNo(docNo) : docNo);
                        $('#doc_no_ev7, #sevpf_doc_no').val(docNo);
                        $('#receiveNoti_No_ev7, #sevpf_receiveNoti_No').text(docNoTH_ev7);
                        $('[name="sevpf_case_no"]').val(docNoTH_ev7);
                    }
                    if (reportNo) {
                        $('#report_no_ev7, #sevpf_report_no').val(reportNo);
                        $('#receiveNotiReportNo_ev7, #sevpf_receiveNotiReportNo').text(reportNo);
                    }

                    if (data.complaints_From && data.complaints_From.trim() !== '') {
                        setSelectValueWithOption($('#ev7_police_station'), data.complaints_From);
                        setSelectValueWithOption($('[name="sevpf_police_station"]'), data.complaints_From);
                    }

                    const stdChannelMap = {
                        'ทางหนังสือ': 'ตามหนังสือ',
                        'ทางโทรศัพท์': 'ทางโทรศัพท์',
                        'ทางวิทยุสื่อสาร': 'ทางวิทยุสื่อสาร',
                        'อื่นๆ': 'อื่นๆ'
                    };
                    const pdfChannelVal = mapReceiveNotiChannel(data.complaints_From_Device);
                    const stdChannelVal = stdChannelMap[pdfChannelVal] || 'อื่นๆ';
                    fillCheckboxIfNoneChecked('ev7_notify_method[]', stdChannelVal);
                    fillCheckboxIfNoneChecked('sevpf_notify_method[]', pdfChannelVal);

                    const otherChannelText = (data.complaints_From_Device_Other || '').toString().trim();
                    if (stdChannelVal === 'อื่นๆ') {
                        const stdOtherText = otherChannelText || '';
                        setValueIfBlank('#ev7_notify_other_text', stdOtherText, false);
                        $('#ev7_notify_other').prop('checked', true).trigger('change');
                    }
                    if (pdfChannelVal === 'อื่นๆ' && otherChannelText) {
                        setValueIfBlank('#sevpf_notify_method_other_text', otherChannelText, false);
                    }

                    const receiveDateTime = splitReceiveNotiDateTime(data.create_date);
                    if (receiveDateTime.date) setValueIfBlank('#ev7_receive_date, [name="sevpf_receive_date"]', receiveDateTime.date, false);
                    if (receiveDateTime.time) setValueIfBlank('#ev7_receive_time, [name="sevpf_receive_time"]', receiveDateTime.time, false);

                    const investigatorName = [data.inquiry_official_first_name, data.inquiry_official_last_name]
                        .map(function(v) {
                            return (v || '').toString().trim();
                        })
                        .filter(Boolean)
                        .join(' ');
                    if (investigatorName) {
                        setValueIfBlank('#ev7_investigator_name, [name="sevpf_investigator_name"]', investigatorName, false);
                    }

                    const incidentLocation = (data.location_crime || data.place_Occurrence || data.location_create || '').toString().trim();
                    if (incidentLocation) {
                        setValueIfBlank('#ev7_incident_location, [name="sevpf_incident_location"]', incidentLocation, false);
                        if (typeof window.sevpfRefreshAutoWrapGroups === 'function') window.sevpfRefreshAutoWrapGroups();
                    }

                    const occurDateTime = splitReceiveNotiDateTime(data.time_Occurrence);
                    if (occurDateTime.date) setValueIfBlank('#ev7_incident_date, [name="sevpf_incident_date"]', occurDateTime.date, false);
                    if (occurDateTime.time) setValueIfBlank('#ev7_incident_time, [name="sevpf_incident_time"]', occurDateTime.time, false);

                    console.log('[EV7 Prefill] ✅ Prefill ข้อมูลรับแจ้งเหตุเสร็จ');

                    // ★ ดึงวัตถุพยานจาก checklist หลัก (Property/Life/Bomb/Fire/Traffic) มาเติมใน EV7
                    _prefillEv7EvidenceFromMainChecklist(incidentId);

                } else {
                    console.warn('[EV7 Prefill] ⚠️ API ตอบกลับไม่สำเร็จ:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('[EV7 Prefill] ❌ AJAX Error:', status, error);
            }
        });
    }

    // ★ ดึงวัตถุพยาน (evidences) จาก checklist หลักมาเติมใน EV7
    function _prefillEv7EvidenceFromMainChecklist(incidentId) {
        $.ajax({
            url: '/csims/api/incidentCheckList/getEvidenceData.php',
            type: 'GET',
            dataType: 'json',
            data: { incident_id: incidentId },
            success: function(res) {
                if (!res.success || !res.data) {
                    console.log('[EV7 Prefill Evidence] ไม่พบข้อมูล checklist หลัก');
                    return;
                }
                var d = res.data;
                // ดึง evidences จาก checklist หลัก (ทุก type ใช้ key "evidences")
                var evidences = d.evidences;
                if (!evidences || !Array.isArray(evidences) || evidences.length === 0) {
                    console.log('[EV7 Prefill Evidence] ไม่พบ evidences ใน checklist หลัก');
                    return;
                }

                // กรองเฉพาะรายการที่มี detail (ข้าม summary-only เช่น blood)
                var items = [];
                evidences.forEach(function(ev) {
                    var detail = (ev.detail || ev.item || '').toString().trim();
                    if (detail && !ev._summary_only) {
                        items.push({ detail: detail, lab_unit: window.labUnitsToString(ev.lab_unit) });
                    }
                });
                if (items.length === 0) return;

                // ตรวจว่า ev7_evidence_item มีข้อมูลอยู่แล้วหรือไม่
                var existing = $('[name="ev7_evidence_item[]"]');
                var hasExisting = false;
                existing.each(function() {
                    if (($(this).val() || '').trim() !== '') hasExisting = true;
                });
                if (hasExisting) {
                    console.log('[EV7 Prefill Evidence] มีข้อมูลอยู่แล้ว ไม่ overwrite');
                    return;
                }

                console.log('[EV7 Prefill Evidence] เติม', items.length, 'รายการจาก checklist หลัก');

                // เติมข้อมูลลงฟอร์มมาตรฐาน (ev7)
                items.forEach(function(item, idx) {
                    if (idx > 0 && typeof addEvidenceItemEV7 === 'function') addEvidenceItemEV7();
                    var stdItems = $('[name="ev7_evidence_item[]"]');
                    if (stdItems.eq(idx).length) stdItems.eq(idx).val(item.detail);
                    var stdLabs = $('[name="ev7_lab_unit[]"]');
                    if (stdLabs.eq(idx).length) window.setLabUnits(stdLabs.eq(idx), item.lab_unit);
                });

                // เติมข้อมูลลงฟอร์ม PDF (sevpf)
                items.forEach(function(item, idx) {
                    if (idx > 0 && typeof sevpfAddEvidenceItem === 'function') sevpfAddEvidenceItem();
                    var pdfItems = $('[name="sevpf_evidence_item[]"]');
                    if (pdfItems.eq(idx).length) pdfItems.eq(idx).val(item.detail);
                    var pdfLabs = $('[name="sevpf_lab_unit[]"]');
                    if (pdfLabs.eq(idx).length) window.setLabUnits(pdfLabs.eq(idx), item.lab_unit);
                });
            },
            error: function(xhr, status, error) {
                console.error('[EV7 Prefill Evidence] AJAX Error:', status, error);
            }
        });
    }

    // =========================================================
    // Scene Evidence (type 07): Submit Data
    // =========================================================
    async function prepareDataForSubmissionSceneEvidence() {
        // Force one-way sync from currently active form before collecting payload
        // to avoid intermittent stale values between standard and virtual forms.
        if (typeof window.syncSceneEvidenceFormData === 'function') {
            if ($('#sceneEvidenceFormPdfModal').hasClass('show')) {
                window.syncSceneEvidenceFormData('sceneEvidenceFormPdf', 'incidentCheckListFormSceneEvidence');
            } else {
                window.syncSceneEvidenceFormData('incidentCheckListFormSceneEvidence', 'sceneEvidenceFormPdf');
            }
        }

        const getCheckboxValues = (name) => {
            const values = [];
            $('input[name="' + name + '"]:checked').each(function() {
                values.push($(this).val());
            });
            return values;
        };

        const normalizeSingleLine = (value) => {
            return (value || '').toString().replace(/\s+/g, ' ').trim();
        };

        const mergePdfIncidentLocation = () => {
            const line1 = ($('[name="sevpf_incident_location"]').val() || '').toString().trim();
            const line2 = ($('[name="sevpf_incident_location_2"]').val() || '').toString().trim();
            return normalizeSingleLine([line1, line2].filter(Boolean).join(' '));
        };

        const stdIncidentLocation = normalizeSingleLine($('[name="ev7_incident_location"]').val());
        const pdfIncidentLocation = mergePdfIncidentLocation();
        const fullIncidentLocation = pdfIncidentLocation.length > stdIncidentLocation.length ? pdfIncidentLocation : stdIncidentLocation;

        const payload = {
            receiveNoti_id_ev7: $('#receiveNoti_id_ev7').val() || $('#sevpf_receiveNoti_id').val(),
            doc_no_ev7: $('#doc_no_ev7').val() || $('#sevpf_doc_no').val(),
            report_no_ev7: $('#report_no_ev7').val() || $('#sevpf_report_no').val(),

            // Section 1: การรับแจ้ง
            ev7_receive_date: $('[name="ev7_receive_date"]').val() || $('[name="sevpf_receive_date"]').val(),
            ev7_receive_time: $('[name="ev7_receive_time"]').val() || $('[name="sevpf_receive_time"]').val(),
            ev7_unit_type: $('[name="ev7_unit_type"]').val() || '',
            ev7_unit_name: $('[name="ev7_unit_name"]').val() || $('[name="sevpf_unit_name"]').val(),
            'ev7_notify_method': getCheckboxValues('ev7_notify_method[]').length > 0 ? getCheckboxValues('ev7_notify_method[]') : getCheckboxValues('sevpf_notify_method[]'),
            ev7_notify_method_other_text: $('[name="ev7_notify_method_other_text"]').val() || $('[name="sevpf_notify_method_other_text"]').val() || '',
            ev7_police_station: $('#ev7_police_station').val() || $('[name="sevpf_police_station"]').val(),
            ev7_document_no: $('[name="ev7_document_no"]').val() || $('[name="sevpf_document_no"]').val(),
            ev7_document_date: $('[name="ev7_document_date"]').val() || $('[name="sevpf_document_date"]').val(),
            ev7_case_no: $('[name="ev7_case_no"]').val() || $('[name="sevpf_case_no"]').val(),
            ev7_incident_location: fullIncidentLocation,
            ev7_incident_date: $('[name="ev7_incident_date"]').val() || $('[name="sevpf_incident_date"]').val(),
            ev7_incident_time: $('[name="ev7_incident_time"]').val() || $('[name="sevpf_incident_time"]').val(),
            ev7_investigator_name: $('[name="ev7_investigator_name"]').val() || $('[name="sevpf_investigator_name"]').val(),
            ev7_send_request: $('[name="ev7_send_request"]').val() || $('[name="sevpf_send_request"]').val(),

            // Evidence items (dynamic)
            'ev7_evidence_item': [],

            // Section 2: Purpose
            'ev7_purpose': getCheckboxValues('ev7_purpose[]').length > 0 ? getCheckboxValues('ev7_purpose[]') : getCheckboxValues('sevpf_purpose[]'),
            ev7_purpose_detail: $('[name="ev7_purpose_detail"]').val() || $('[name="sevpf_purpose_detail"]').val(),

            // Section 3: Inspection
            ev7_inspect_location: $('[name="ev7_inspect_location"]').val() || $('[name="sevpf_inspect_location"]').val(),
            ev7_inspect_date: $('[name="ev7_inspect_date"]').val() || $('[name="sevpf_inspect_date"]').val(),
            ev7_inspect_time: $('[name="ev7_inspect_time"]').val() || $('[name="sevpf_inspect_time"]').val(),

            // 3.1 Exhibit descriptions
            'ev7_exhibit_desc': [],

            // 3.2 Collected evidence
            'ev7_collect_type': getCheckboxValues('ev7_collect_type[]').length > 0 ? getCheckboxValues('ev7_collect_type[]') : getCheckboxValues('sevpf_collect_type[]'),
            ev7_collect_sheet_count: $('[name="ev7_collect_sheet_count"]').val() || $('[name="sevpf_collect_sheet_count"]').val(),
            ev7_collect_location: $('[name="ev7_collect_location"]').val() || $('[name="sevpf_collect_location"]').val(),
            'ev7_collect_detail': [],
            'ev7_other_evidence': [],
            'ev7_lab_unit': [],

            // 3.3 Evidence handling
            ev7_witness_name: $('[name="ev7_witness_name"]').val() || $('[name="sevpf_witness_name"]').val(),
            ev7_witness_form: $('[name="ev7_witness_form"]').val() || $('[name="sevpf_witness_form"]').val(),
            ev7_handover_method: $('[name="ev7_handover_method"]').val() || '',
            ev7_handover_item_ref: $('[name="ev7_handover_item_ref"]').val() || $('[name="sevpf_handover_item_ref"]').val(),
            ev7_handover_to: $('[name="ev7_handover_to"]').val() || $('[name="sevpf_handover_to"]').val(),
            ev7_handover_purpose: $('[name="ev7_handover_purpose"]').val() || $('[name="sevpf_handover_purpose"]').val(),
            sevpf_handover_next_action: $('[name="sevpf_handover_next_action"]').val() || '',
            'sevpf_handover_more_lines': [],
            sevpf_handover_next_action_full: '',

            // PDF form-specific
            sevpf_report_ref: $('[name="sevpf_report_ref"]').val() || '',
            sevpf_report_year: $('[name="sevpf_report_year"]').val() || '',
            'sevpf_unit_type_check': getCheckboxValues('sevpf_unit_type_check[]'),
            sevpf_center_name: $('[name="sevpf_center_name"]').val() || '',
            sevpf_province_name: $('[name="sevpf_province_name"]').val() || '',
            sevpf_witness_detail: $('[name="ev7_witness_detail"]').val() || $('[name="sevpf_witness_detail"]').val() || '',
            'sevpf_handover_method_check': getCheckboxValues('sevpf_handover_method_check[]'),
            sevpf_handover_method_detail: $('[name="ev7_handover_method_detail"]').val() || $('[name="sevpf_handover_method_detail"]').val() || '',
            sevpf_other_evidence_text: $('[name="sevpf_other_evidence_text"]').val() || '',
            sevpf_signer_name: $('[name="sevpf_signer_name"]').val() || '',
            sevpf_signer_fullname: $('[name="sevpf_signer_fullname"]').val() || '',
            sevpf_signer_position: $('[name="sevpf_signer_position"]').val() || '',
            sevpf_sign_day: $('[name="sevpf_sign_day"]').val() || '',
            sevpf_sign_month: $('[name="sevpf_sign_month"]').val() || '',
            sevpf_sign_year: $('[name="sevpf_sign_year"]').val() || '',
            sevpf_receiver_id: $('[name="ev7_receiver_id"]').val() || $('[name="sevpf_receiver_id"]').val() || '',
            sevpf_receiver_position: $('[name="ev7_receiver_position"]').val() || $('[name="sevpf_receiver_position"]').val() || '',
            sevpf_sender_id: $('[name="ev7_sender_id"]').val() || $('[name="sevpf_sender_id"]').val() || '',
            sevpf_sender_position: $('[name="ev7_sender_position"]').val() || $('[name="sevpf_sender_position"]').val() || '',
            sevpf_receiver_sig_cleared: $('[name="sevpf_receiver_sig_cleared"]').val() || '0',
            sevpf_sender_sig_cleared: $('[name="sevpf_sender_sig_cleared"]').val() || '0',
            ev7_receiver_signature_present: '0',
            ev7_sender_signature_present: '0',

            // Inspectors
            'ev7_inspector_id': [],

            // Signer
            ev7_signer_id: $('[name="ev7_signer_id"]').val() || $('[name="ev7_sender_id"]').val() || $('[name="sevpf_sender_id"]').val() || '',
            ev7_signer_position: $('[name="ev7_signer_position"]').val() || $('[name="ev7_sender_position"]').val() || $('[name="sevpf_signer_position"]').val(),
            ev7_sign_date: $('[name="ev7_sign_date"]').val() || '',

            // Photo records
            ev7_photo_id_start: $('[name="ev7_photo_id_start"]').val() || $('[name="photo_id_start_ev7"]').val() || '',
            ev7_photo_id_end: $('[name="ev7_photo_id_end"]').val() || $('[name="photo_id_end_ev7"]').val() || '',
            ev7_photo_amount: $('[name="ev7_photo_amount"]').val() || $('[name="photo_amount_ev7"]').val() || '',
            ev7_photographer_name: $('[name="ev7_photographer_name"]').val() || '',
            ev7_photographer_datetime: $('[name="ev7_photographer_datetime"]').val() || ''
        };

        // Choose the fuller source between standard and PDF forms so added PDF rows are not dropped.
        var collectValues = function(selector) {
            var values = [];
            $(selector).each(function() {
                var value = $.trim($(this).val() || '');
                if (value) values.push(value);
            });
            return values;
        };
        var chooseBestValues = function(primarySelector, secondarySelector) {
            var primaryValues = collectValues(primarySelector);
            var secondaryValues = secondarySelector ? collectValues(secondarySelector) : [];
            if (secondaryValues.length > primaryValues.length) return secondaryValues;
            if (secondaryValues.length < primaryValues.length) return primaryValues;
            if (secondaryValues.join('\n').length > primaryValues.join('\n').length) return secondaryValues;
            return primaryValues;
        };

        payload['ev7_exhibit_desc'] = chooseBestValues('[name="ev7_exhibit_desc[]"]', '[name="sevpf_exhibit_desc[]"]');
        payload['ev7_collect_detail'] = chooseBestValues('[name="ev7_collect_detail[]"]', '[name="sevpf_collect_detail[]"]');
        payload['ev7_other_evidence'] = collectValues('[name="ev7_other_evidence[]"]');
        if (window.LabUnitMulti) {
            window.LabUnitMulti.syncAll(document.getElementById('sceneEvidenceFormPdf') || document);
            window.LabUnitMulti.syncAll(document.getElementById('incidentCheckListFormSceneEvidence') || document);
        }
        var readLabUnitValue = function(el) {
            if (!el) return [];
            try {
                if (typeof window.getLabUnits === 'function') {
                    var arr = window.getLabUnits(el);
                    if (arr && arr.length) return arr;
                }
            } catch (e) {}
            var raw = $(el).val();
            if (Array.isArray(raw)) return raw.filter(Boolean);
            if (typeof window.labUnitsToArray === 'function') return window.labUnitsToArray(raw);
            return String(raw || '').split(',').map(function(s) { return $.trim(s); }).filter(Boolean);
        };
        var collectEvidenceRows = function(rowSelector, itemName, labName) {
            var items = [];
            var labs = [];
            var rows = [];
            $(rowSelector).each(function() {
                var $row = $(this);
                var itemEl = $row.find('[name="' + itemName + '"]').get(0);
                var labEl = $row.find('[name="' + labName + '"]').get(0) || $row.find('select.lab-unit-multi').get(0);
                var item = $.trim($(itemEl).val() || '');
                var lab = readLabUnitValue(labEl);
                if (!item && !lab.length) return;
                items.push(item);
                labs.push(lab);
                rows.push({ description: item, detail: item, lab_unit: lab });
            });
            return { items: items, labs: labs, rows: rows };
        };
        var pdfEv = collectEvidenceRows('#sevpf_evidence_items_container .sevpf-evidence-item-row', 'sevpf_evidence_item[]', 'sevpf_lab_unit[]');
        var stdEv = collectEvidenceRows('#ev7_evidence_items_container .ev7-evidence-item-row', 'ev7_evidence_item[]', 'ev7_lab_unit[]');
        var chosenEv = (pdfEv.items.length > stdEv.items.length || (pdfEv.items.length === stdEv.items.length && pdfEv.items.join('\n').length >= stdEv.items.join('\n').length))
            ? pdfEv : stdEv;
        if (!chosenEv.items.length && stdEv.items.length) chosenEv = stdEv;
        payload['ev7_evidence_item'] = chosenEv.items;
        payload['ev7_lab_unit'] = chosenEv.labs;
        payload['ev7_evidence_rows'] = chosenEv.rows;

        // Keep visual multiline inputs but save as one full text + per-line array
        var handoverLines = [];
        $('[name="sevpf_handover_next_action"], [name^="sevpf_handover_more_"]').each(function() {
            handoverLines.push($(this).val() || '');
        });
        var stdNextAction = ($('[name="ev7_handover_next_action"]').val() || '').trim();
        var hasPdfNextAction = handoverLines.join('').trim() !== '';
        if (!hasPdfNextAction && stdNextAction) {
            handoverLines = stdNextAction.split(/\r?\n/);
        }
        payload['sevpf_handover_more_lines'] = handoverLines;
        payload['sevpf_handover_next_action_full'] = handoverLines.join('\n').replace(/\n+$/g, '');

        payload['ev7_inspector_id'] = chooseBestValues(
            '#ev7_inspector_container .ev7-inspector-select',
            '#sevpf_inspector_container select[name="ev7_inspector_id[]"]'
        );

        // === สร้าง FormData ===
        const submitData = new FormData();
        submitData.append('payload_json', JSON.stringify(payload));

        // Flatten payload into FormData (array ซ้อนส่งเป็น comma string — ค่าจริงอยู่ใน payload_json)
        for (const [key, val] of Object.entries(payload)) {
            if (key === 'ev7_evidence_rows') continue;
            if (Array.isArray(val)) {
                val.forEach(function(v) {
                    if (Array.isArray(v)) {
                        submitData.append(key + '[]', v.filter(Boolean).join(','));
                    } else if (v && typeof v === 'object') {
                        submitData.append(key + '[]', v.description || v.detail || '');
                    } else {
                        submitData.append(key + '[]', v == null ? '' : v);
                    }
                });
            } else {
                submitData.append(key, val || '');
            }
        }

        if (typeof attachmentStoreEV7 !== 'undefined' && attachmentStoreEV7.length > 0) {
            payload.ev7_photo_amount = payload.ev7_photo_amount || String(attachmentStoreEV7.length);
            if (!payload.ev7_photo_id_start) payload.ev7_photo_id_start = '1';
            if (!payload.ev7_photo_id_end) payload.ev7_photo_id_end = String(attachmentStoreEV7.length);
        }

        var canvasHasDrawing = function(canvas) {
            if (!canvas) return false;
            var ctx = canvas.getContext('2d');
            if (!ctx) return false;
            var pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
            for (var i = 3; i < pixels.length; i += 4) {
                if (pixels[i] !== 0) return true;
            }
            return false;
        };

        var pickSignatureDataUrl = function(canvasIds) {
            for (var i = 0; i < canvasIds.length; i++) {
                var c = document.getElementById(canvasIds[i]);
                if (!c || !canvasHasDrawing(c)) continue;
                try {
                    var dataUrl = c.toDataURL('image/png');
                    if (dataUrl && dataUrl !== 'data:,') return dataUrl;
                } catch (e) {
                    console.warn('[EV7] Cannot export signature canvas:', canvasIds[i], e);
                }
            }
            return '';
        };

        var senderHasDrawing = canvasHasDrawing(document.getElementById('sig-canvas-ev7-sender')) || canvasHasDrawing(document.getElementById('sevpf_sig_sender'));
        var receiverHasDrawing = canvasHasDrawing(document.getElementById('sig-canvas-ev7-receiver')) || canvasHasDrawing(document.getElementById('sevpf_sig_receiver'));

        payload.ev7_sender_signature_present = senderHasDrawing ? '1' : '0';
        payload.ev7_receiver_signature_present = receiverHasDrawing ? '1' : '0';
        submitData.append('ev7_sender_signature_present', payload.ev7_sender_signature_present);
        submitData.append('ev7_receiver_signature_present', payload.ev7_receiver_signature_present);

        // Signature (canvas → base64) — check both standard and PDF form canvases
        var preferPdf = $('#sceneEvidenceFormPdfModal').hasClass('show');
        var senderSigDataUrl = preferPdf ?
            pickSignatureDataUrl(['sevpf_sig_sender', 'sig-canvas-ev7-sender']) :
            pickSignatureDataUrl(['sig-canvas-ev7-sender', 'sevpf_sig_sender']);
        var receiverSigDataUrl = preferPdf ?
            pickSignatureDataUrl(['sevpf_sig_receiver', 'sig-canvas-ev7-receiver']) :
            pickSignatureDataUrl(['sig-canvas-ev7-receiver', 'sevpf_sig_receiver']);

        if (senderSigDataUrl) {
            submitData.append('ev7_signer_signature_data', senderSigDataUrl);
            submitData.append('sevpf_sender_signature_data', senderSigDataUrl);
        }
        if (receiverSigDataUrl) {
            submitData.append('sevpf_receiver_signature_data', receiverSigDataUrl);
        }

        submitData.set('payload_json', JSON.stringify(payload));
        submitData.set('ev7_photo_id_start', payload.ev7_photo_id_start || '');
        submitData.set('ev7_photo_id_end', payload.ev7_photo_id_end || '');
        submitData.set('ev7_photo_amount', payload.ev7_photo_amount || '');

        // Photos
        if (typeof attachmentStoreEV7 !== 'undefined' && attachmentStoreEV7.length > 0) {
            attachmentStoreEV7.forEach(function(item) {
                if (item.file) {
                    submitData.append('incident_photos_ev7[]', item.file);
                }
            });
        }

        // Deleted photos — ส่งทั้ง file_id (ลบ BLOB จาก DB) และ filename (ลบไฟล์ legacy บนดิสก์)
        if (typeof deletedExistingPhotosEV7 !== 'undefined' && deletedExistingPhotosEV7.length > 0) {
            var delFileIdsEV7 = deletedExistingPhotosEV7
                .map(function(x) { return x.file_id; })
                .filter(function(v) { return v !== null && v !== undefined && v !== ''; });
            if (delFileIdsEV7.length > 0) {
                submitData.append('deleted_photo_file_ids', JSON.stringify(delFileIdsEV7));
            }
            var delFilenamesEV7 = deletedExistingPhotosEV7
                .map(function(x) { return x.db_filename; })
                .filter(function(v) { return v !== null && v !== undefined && v !== ''; });
            if (delFilenamesEV7.length > 0) {
                submitData.append('deleted_photos_ev7', JSON.stringify(delFilenamesEV7));
            }
        }

        // === Confirm & Submit ===
        Swal.fire({
            title: 'ยืนยันการบันทึกข้อมูล',
            text: 'กรุณาตรวจสอบความถูกต้องก่อนบันทึก',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#d33',
            confirmButtonText: 'ยืนยัน, บันทึกเลย!',
            cancelButtonText: 'ยกเลิก',
        }).then(async (result) => {
            if (result.isConfirmed) {
                // Disable both standard and PDF form save buttons
                const btnSave = $('#btn_save_ev7');
                const btnSavePdf = $('#btn_save_ev7_pdf');
                const btnText = btnSave.html();
                const btnTextPdf = btnSavePdf.html();
                btnSave.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> กำลังบันทึก...');
                btnSavePdf.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> กำลังบันทึก...');

                const ev7Url = './api/incidentCheckList/saveSceneEvidence.php';

                if (!navigator.onLine) {
                    btnSave.prop('disabled', false).html(btnText);
                    btnSavePdf.prop('disabled', false).html(btnTextPdf);
                    if (typeof saveChecklistOffline === 'function') await saveChecklistOffline(submitData, ev7Url, '#addCheckListModalSceneEvidence');
                    return;
                }
                const backendOk = await checkBackendHealth();
                if (!backendOk) {
                    btnSave.prop('disabled', false).html(btnText);
                    btnSavePdf.prop('disabled', false).html(btnTextPdf);
                    if (typeof saveChecklistOffline === 'function') await saveChecklistOffline(submitData, ev7Url, '#addCheckListModalSceneEvidence');
                    return;
                }

                $.ajax({
                    url: ev7Url,
                    method: 'POST',
                    data: submitData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        btnSave.prop('disabled', false).html(btnText);
                        btnSavePdf.prop('disabled', false).html(btnTextPdf);
                        console.log('[EV7] Save response:', response);
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'สำเร็จ',
                                text: response.message || 'บันทึกข้อมูลเรียบร้อย',
                                timer: 900,
                                showConfirmButton: false
                            }).then(() => {
                                ['addCheckListModalSceneEvidence', 'sceneEvidenceFormPdfModal'].forEach(id => {
                                    const el = document.getElementById(id);
                                    if (el) {
                                        const m = bootstrap.Modal.getInstance(el);
                                        if (m) m.hide();
                                    }
                                });
                                // Force one refresh so latest saved data is immediately visible.
                                window.location.reload();
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
                    error: async function(xhr) {
                        btnSave.prop('disabled', false).html(btnText);
                        btnSavePdf.prop('disabled', false).html(btnTextPdf);
                        console.error('[EV7] AJAX Error:', xhr.responseText);
                        if (typeof saveChecklistOffline === 'function') await saveChecklistOffline(submitData, ev7Url, '#addCheckListModalSceneEvidence');
                    }
                });
            }
        });
    }

    async function prepareDataForSubmissionFire() {
        // === ไม่ต้อง toDataURL แล้ว — จะแปลงเป็น Blob แทน ===

        // === Helper: ดึงค่า checkbox group ที่ checked (return array) ===
        const getCheckboxValues = (name) => {
            const values = [];
            $('input[name="' + name + '"]:checked').each(function() {
                values.push($(this).val());
            });
            return values;
        };

        // === Helper: ดึงค่า checkbox เดี่ยว (single value จาก fire-radio-toggle) ===
        const getCheckboxValue = (name) => {
            const checked = $('input[name="' + name + '"]:checked');
            return checked.length > 0 ? checked.val() : '';
        };

        // === สร้าง structured JSON payload ===
        const payload = {
            receiveNoti_id_fire: $('#receiveNoti_id_fire').val(),
            doc_no_fire: $('#doc_no_fire').val(),
            report_no_fire: $('#report_no_fire').val(),
            case_doc_no_fire: $('#case_doc_no_fire').val(),

            // 1. ข้อมูลทั่วไป (date/time split)
            fire_report_date: $('[name="fire_report_date"]').val(),
            fire_report_time: $('[name="fire_report_time"]').val(),
            fire_notify_method: getCheckboxValues('fire_notify_method[]'),
            fire_notify_method_other_text: $('[name="fire_notify_method_other_text"]').val(),
            police_station_fire: $('#police_station_fire').val(),
            fire_document_no: $('#fire_document_no').val(),
            fire_document_date: $('#fire_document_date').val(),
            fire_investigator_name: $('#fire_investigator_name').val(),
            fire_investigator_phone: $('#fire_investigator_phone').val(),

            // 2. สถานที่เกิดเหตุ
            fire_incident_location: $('[name="fire_incident_location"]').val(),

            // 3. บุคคล (array)
            fire_person_type: [],
            fire_person_name: [],
            fire_person_age: [],
            fire_person_remark: [],

            // 4. วัน-เวลา (date/time split)
            fire_victim_known_date: $('[name="fire_victim_known_date"]').val(),
            fire_victim_known_time: $('[name="fire_victim_known_time"]').val(),
            fire_investigator_known_date: $('[name="fire_investigator_known_date"]').val(),
            fire_investigator_known_time: $('[name="fire_investigator_known_time"]').val(),
            fire_known_detail: $('[name="fire_known_detail"]').val(),
            fire_inspection_date: $('[name="fire_inspection_date"]').val(),
            fire_inspection_time: $('[name="fire_inspection_time"]').val(),
            fire_inspection_additional_date: $('[name="fire_inspection_additional_date"]').val(),
            fire_inspection_additional_time: $('[name="fire_inspection_additional_time"]').val(),

            // 5. ผู้ตรวจ (array)
            fire_inspector_id: [],

            // 6. ลักษณะสถานที่ - ภายนอก
            fire_exterior_detail: $('[name="fire_exterior_detail"]').val(),
            fire_floor_count: $('[name="fire_floor_count"]').val(),
            fire_fence: getCheckboxValue('fire_fence'),
            fire_scene_front: $('[name="fire_scene_front"]').val(),
            fire_scene_back: $('[name="fire_scene_back"]').val(),
            fire_scene_left: $('[name="fire_scene_left"]').val(),
            fire_scene_right: $('[name="fire_scene_right"]').val(),

            // 6. ลักษณะสถานที่ - ภายใน
            fire_interior_detail: $('[name="fire_interior_detail"]').val(),

            // 6. บริเวณที่เกิดเหตุ
            fire_incident_area_detail: $('[name="fire_incident_area_detail"]').val(),
            fire_area_size: $('[name="fire_area_size"]').val(),
            fire_facing_direction: $('[name="fire_facing_direction"]').val(),

            // โครงสร้าง
            fire_structure_wall_front: $('[name="fire_structure_wall_front"]').val(),
            fire_structure_wall_left: $('[name="fire_structure_wall_left"]').val(),
            fire_structure_wall_right: $('[name="fire_structure_wall_right"]').val(),
            fire_structure_wall_back: $('[name="fire_structure_wall_back"]').val(),
            fire_structure_floor: $('[name="fire_structure_floor"]').val(),
            fire_structure_roof: $('[name="fire_structure_roof"]').val(),
            fire_structure_ceiling: $('[name="fire_structure_ceiling"]').val(),

            // สิ่งของ
            fire_objects_wall_front: $('[name="fire_objects_wall_front"]').val(),
            fire_objects_wall_left: $('[name="fire_objects_wall_left"]').val(),
            fire_objects_wall_right: $('[name="fire_objects_wall_right"]').val(),
            fire_objects_wall_back: $('[name="fire_objects_wall_back"]').val(),
            fire_objects_other: $('[name="fire_objects_other"]').val(),

            // 7. พฤติการณ์คดี
            fire_case_behavior: $('[name="fire_case_behavior"]').val(),
            fire_insurance: getCheckboxValue('fire_insurance'),
            fire_burn_time: $('[name="fire_burn_time"]').val(),
            fire_extinguish: getCheckboxValue('fire_extinguish'),
            fire_extinguish_detail: $('[name="fire_extinguish_detail"]').val(),
            fire_damage_condition: $('[name="fire_damage_condition"]').val(),
            fire_spread_detail: $('[name="fire_spread_detail"]').val(),

            // ความเสียหาย - โครงสร้าง
            fire_damage_wall_front: $('[name="fire_damage_wall_front"]').val(),
            fire_damage_wall_left: $('[name="fire_damage_wall_left"]').val(),
            fire_damage_wall_right: $('[name="fire_damage_wall_right"]').val(),
            fire_damage_wall_back: $('[name="fire_damage_wall_back"]').val(),
            fire_damage_floor: $('[name="fire_damage_floor"]').val(),
            fire_damage_roof: $('[name="fire_damage_roof"]').val(),
            fire_damage_ceiling: $('[name="fire_damage_ceiling"]').val(),

            // ความเสียหาย - สิ่งของ
            fire_damage_obj_front: $('[name="fire_damage_obj_front"]').val(),
            fire_damage_obj_left: $('[name="fire_damage_obj_left"]').val(),
            fire_damage_obj_right: $('[name="fire_damage_obj_right"]').val(),
            fire_damage_obj_back: $('[name="fire_damage_obj_back"]').val(),
            fire_damage_obj_floor: $('[name="fire_damage_obj_floor"]').val(),
            fire_damage_obj_roof: $('[name="fire_damage_obj_roof"]').val(),
            fire_damage_obj_ceiling: $('[name="fire_damage_obj_ceiling"]').val(),

            // ความเสียหาย - อื่นๆ
            fire_first_area: $('[name="fire_first_area"]').val(),
            fire_switch_condition: $('[name="fire_switch_condition"]').val(),
            fire_adjacent_damage: getCheckboxValue('fire_adjacent_damage'),
            fire_adjacent_damage_detail: $('[name="fire_adjacent_damage_detail"]').val(),
            fire_evidence_found: $('[name="fire_evidence_found"]').val(),
            fire_evidence_collected: $('[name="fire_evidence_collected"]').val(),
            fire_evidence_action: getCheckboxValues('fire_evidence_action[]'),

            // 8. สรุป
            fire_origin_area: $('[name="fire_origin_area"]').val(),
            fire_fuel_source: $('[name="fire_fuel_source"]').val(),
            fire_heat_source: $('[name="fire_heat_source"]').val(),
            fire_summary_other: $('[name="fire_summary_other"]').val(),

            // 9. ความเห็น
            fire_opinion_first_area: $('[name="fire_opinion_first_area"]').val(),
            fire_cause_type: getCheckboxValue('fire_cause_type'),
            fire_cause_believed_detail: $('[name="fire_cause_believed_detail"]').val(),
            fire_cause_unknown_detail: $('[name="fire_cause_unknown_detail"]').val(),

            // 10. แผนผัง + หมายเหตุ (ไม่ส่ง base64 แล้ว — จะส่งเป็น Blob file ใน FormData)
            fire_sketch_remark: $('#fire_sketch_remark').val(),
            fire_sketch_recorder: $('[name="fire_sketch_recorder"]').val(),
            fire_sketch_datetime: $('[name="fire_sketch_datetime"]').val(),

            // 11. ข้อมูลภาพ
            photo_id_start_fire: $('#photo_id_start_fire').val(),
            photo_id_end_fire: $('#photo_id_end_fire').val(),
            photo_amount_fire: $('#photo_amount_fire').val(),

            // 12. การส่งมอบคืนสถานที่ (ลายเซ็นจะส่งเป็น Blob file ใน FormData)
            fire_inspection_end_date: $('[name="fire_inspection_end_date"]').val(),
            fire_inspection_end_time: $('[name="fire_inspection_end_time"]').val(),
            receiver_name_fire: $('#receiver_name_fire').val(),
            receiver_position_fire: $('#receiver_position_fire').val(),
            sender_name_fire: $('#sender_name_fire').val(),
            sender_position_fire: $('#sender_position_fire').val(),

            // 13. หมายเหตุ
            fire_remark: $('[name="fire_remark"]').val(),

            // 14. ผู้บันทึก
            recorder_name_fire: $('#recorder_name_fire').val(),
            recorder_datetime_fire: $('#recorder_datetime_fire').val(),
            photographer_name_fire: $('#photographer_name_fire').val(),
            photographer_datetime_fire: $('#photographer_datetime_fire').val(),
            fire_investigator_select: $('#fire_investigator_select').val(),
            fire_investigator_phone_recorder: $('#fire_investigator_phone_recorder').val(),

            // === วัตถุพยานและตำแหน่งที่ตรวจพบ ===
            evidence_item_fire: [],
            evidence_azimuth_fire: [],
            evidence_remark_fire: [],
            evidence_lab_unit_fire: [],
            reference_point_1_fire: $('#reference_point_1_fire').val() || '',
            reference_point_2_fire: $('#reference_point_2_fire').val() || '',
            reference_point_3_fire: $('#reference_point_3_fire').val() || '',
            reference_point_4_fire: $('#reference_point_4_fire').val() || '',

            // === บันทึกการตรวจเก็บวัตถุพยาน ===
            measurement_inspection_date_fire: $('#measurement_inspection_date_fire').val() || '',
            measurement_item_fire: [],
            measurement_quantity_fire: [],
            measurement_area_fire: [],
            measurement_label_number_fire: [],
            measurement_remark_fire: [],
            measurement_forensic_unit_fire: []
        };

        // === รวบรวมข้อมูล array: ผู้ตรวจ ===
        $('#inspector_container_fire .inspector-row-fire').each(function() {
            const id = $(this).find('select[name="fire_inspector_id[]"]').val();
            if (id) {
                payload.fire_inspector_id.push(id);
            }
        });

        // === รวบรวมข้อมูล array: บุคคล (victim cards) ===
        $('#fire_person_container .victim-card-fire').each(function() {
            payload.fire_person_type.push($(this).find('[name="fire_person_type[]"]').val() || '');
            payload.fire_person_name.push($(this).find('[name="fire_person_name[]"]').val() || '');
            payload.fire_person_age.push($(this).find('[name="fire_person_age[]"]').val() || '');
            payload.fire_person_remark.push($(this).find('[name="fire_person_remark[]"]').val() || '');
        });

        // === รวบรวมข้อมูล array: วัตถุพยานและตำแหน่งที่ตรวจพบ (evidence cards) ===
        $('#evidence_container_fire .evidence-card-fire').each(function(idx) {
            payload.evidence_item_fire.push($(this).find('[name="evidence_item_fire[]"]').val() || '');
            payload.evidence_azimuth_fire.push($(this).find('[name="evidence_azimuth_fire[]"]').val() || '');
            payload.evidence_remark_fire.push($(this).find('[name="evidence_remark_fire[]"]').val() || '');
            payload.evidence_lab_unit_fire.push(window.getLabUnitsString($(this).find('[name="evidence_lab_unit_fire[]"]')));
            // ระยะห่าง: เปลี่ยนจาก checkbox -> text
            payload['evidence_level_1_fire_' + idx] = $(this).find('[name^="evidence_level_1_fire_"]').val() || '';
            payload['evidence_level_2_fire_' + idx] = $(this).find('[name^="evidence_level_2_fire_"]').val() || '';
            payload['evidence_level_3_fire_' + idx] = $(this).find('[name^="evidence_level_3_fire_"]').val() || '';
            payload['evidence_level_4_fire_' + idx] = $(this).find('[name^="evidence_level_4_fire_"]').val() || '';
        });

        // === รวบรวมข้อมูล array: บันทึกการตรวจเก็บวัตถุพยาน (measurement cards) ===
        $('#measurement_container_fire .measurement-card-fire').each(function(idx) {
            payload.measurement_item_fire.push($(this).find('[name="measurement_item_fire[]"]').val() || '');
            payload.measurement_quantity_fire.push($(this).find('[name="measurement_quantity_fire[]"]').val() || '');
            payload.measurement_area_fire.push($(this).find('[name="measurement_area_fire[]"]').val() || '');
            payload.measurement_label_number_fire.push($(this).find('[name="measurement_label_number_fire[]"]').val() || '');
            payload.measurement_remark_fire.push($(this).find('[name="measurement_remark_fire[]"]').val() || '');
            payload.measurement_forensic_unit_fire.push(window.getLabUnitsString($(this).find('[name="measurement_forensic_unit_fire[]"]')));
            // Checkboxes + texts แบบ indexed
            payload['measurement_package_plastic_check_fire_' + idx] = $(this).find('[name^="measurement_package_plastic_check_fire_"]').is(':checked') ? '1' : '';
            payload['measurement_package_plastic_text_fire_' + idx] = $(this).find('[name^="measurement_package_plastic_text_fire_"]').val() || '';
            payload['measurement_package_paper_check_fire_' + idx] = $(this).find('[name^="measurement_package_paper_check_fire_"]').is(':checked') ? '1' : '';
            payload['measurement_package_paper_text_fire_' + idx] = $(this).find('[name^="measurement_package_paper_text_fire_"]').val() || '';
            payload['measurement_package_other_check_fire_' + idx] = $(this).find('[name^="measurement_package_other_check_fire_"]').is(':checked') ? '1' : '';
            payload['measurement_package_other_text_fire_' + idx] = $(this).find('[name^="measurement_package_other_text_fire_"]').val() || '';
            payload['measurement_action_return_check_fire_' + idx] = $(this).find('[name^="measurement_action_return_check_fire_"]').is(':checked') ? '1' : '';
            payload['measurement_action_return_text_fire_' + idx] = $(this).find('[name^="measurement_action_return_text_fire_"]').val() || '';
            payload['measurement_action_other_check_fire_' + idx] = $(this).find('[name^="measurement_action_other_check_fire_"]').is(':checked') ? '1' : '';
            payload['measurement_action_other_text_fire_' + idx] = $(this).find('[name^="measurement_action_other_text_fire_"]').val() || '';
        });

        // === สร้าง FormData สำหรับส่ง ===
        const submitData = new FormData();
        submitData.append('payload', JSON.stringify(payload));

        // === ส่ง signature / sketch เป็น Blob file ===
        const sigCanvasMap = {
            'scene_sketch': 'scene_sketch_canvas_fire',
            'receiver_signature': 'sig-canvas-receiver-fire',
            'sender_signature': 'sig-canvas-sender-fire'
        };
        for (const [key, canvasId] of Object.entries(sigCanvasMap)) {
            if (signaturePads[canvasId] && !signaturePads[canvasId].isEmpty()) {
                const cvs = document.getElementById(canvasId);
                if (cvs) {
                    const blob = await canvasToBlob(cvs, 'image/png');
                    if (blob) {
                        submitData.append('sig_file_' + key, blob, key + '.png');
                    }
                }
            }
        }

        // ส่งรายการรูปที่ลบ (file_id)
        if (deletedExistingPhotosFire.length > 0) {
            submitData.append('deleted_photo_file_ids', JSON.stringify(deletedExistingPhotosFire));
        }

        // Append new photo files (เฉพาะรูปใหม่ที่มี file object)
        if (attachmentStoreFire) {
            attachmentStoreFire.forEach(function(item) {
                if (item.file) {
                    submitData.append('incident_photos_fire[]', item.file);
                }
            });
        }

        // === Confirmation & AJAX Submission ===
        Swal.fire({
            title: 'ยืนยันการบันทึกข้อมูล',
            text: 'กรุณาตรวจสอบความถูกต้องก่อนบันทึก',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#d33',
            confirmButtonText: 'ยืนยัน, บันทึกเลย!',
            cancelButtonText: 'ยกเลิก',
        }).then(async (result) => {
            if (result.isConfirmed) {
                const btnSave = $('#btn_save_fire');
                const btnText = btnSave.html();
                btnSave.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> กำลังบันทึก...');

                // ★ เช็คเน็ตก่อนส่ง (Offline Mode)
                const fireUrl = './api/incidentCheckList/saveFire.php';
                if (!navigator.onLine) {
                    btnSave.prop('disabled', false).html(btnText);
                    await saveChecklistOffline(submitData, fireUrl, '#addCheckListModalFire');
                    return;
                }
                const backendOkFire = await checkBackendHealth();
                if (!backendOkFire) {
                    btnSave.prop('disabled', false).html(btnText);
                    await saveChecklistOffline(submitData, fireUrl, '#addCheckListModalFire');
                    return;
                }

                $.ajax({
                    url: fireUrl,
                    method: 'POST',
                    data: submitData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        btnSave.prop('disabled', false).html(btnText);
                        console.log('🔥 Save response:', response);
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'สำเร็จ',
                                text: response.message || 'บันทึกข้อมูลเรียบร้อย',
                                confirmButtonText: 'ตกลง'
                            }).then(() => {
                                ['addCheckListModalFire', 'fireFormPdfModal'].forEach(id => {
                                    const el = document.getElementById(id);
                                    if (el) {
                                        const m = bootstrap.Modal.getInstance(el);
                                        if (m) m.hide();
                                    }
                                });
                                resetFireForm();
                                if (typeof loadData === 'function') loadData();
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
                    error: async function(xhr) {
                        btnSave.prop('disabled', false).html(btnText);
                        console.error('AJAX Error:', xhr.responseText);
                        // ★ ส่งไม่ได้ → fallback Offline
                        await saveChecklistOffline(submitData, fireUrl, '#addCheckListModalFire');
                    }
                });
            }
        });
    }

    // --- ฟังก์ชันรวบรวมและส่งข้อมูล (Main Submission Function) ---
    // ★ เปลี่ยนเป็น async เพื่อรองรับ canvas-to-Blob (BLOB storage แบบ fire)
    async function prepareDataForSubmission() {
        const form = document.getElementById('incidentCheckListForm');

        // 1. Validation — removed mandatory field check (save อนุญาตให้บันทึกโดยไม่ต้องกรอกครบ)

        // 2. Custom Validation (ถ้ามี เช่น ต้องมีผู้ตรวจอย่างน้อย 1 คน)
        // if ($('select[name="inspector_id[]"]').length === 0 || !$('select[name="inspector_id[]"]').val()) {
        //     Swal.fire('ข้อมูลไม่ครบ', 'กรุณาระบุผู้ตรวจสถานที่เกิดเหตุอย่างน้อย 1 คน', 'warning');
        //     return;
        // }

        // 3. ★ ไม่ต้อง toDataURL แล้ว — จะแปลงเป็น Blob ก่อนส่ง (เหมือน fire)


        // 4. รวบรวมข้อมูลเป็น JSON Object (Structured Data)

        // --- 4.1 General Info (ข้อมูลทั่วไป + สถานที่ + เวลา) ---
        // ใช้ FormData เพื่อดึงค่าง่ายๆ จาก Form หลัก
        const formData = new FormData(form);

        // Helper function ดึงค่า Checkbox หลายตัว
        const getCheckboxValues = (name) => {
            return formData.getAll(name);
        };

        const payload = {
            // ID ของรายการ (สำคัญสำหรับ API)
            incident_id: $('#receiveNoti_id').val(), // ID จาก rn_ReceiveNoti | Ex: "123"

            // ส่วนที่ 1: ข้อมูลทั่วไป (fieldset 1: ข้อมูลการรับแจ้งเหตุ)
            general_info: {
                doc_no: $('#doc_no').val(), // เลขที่เอกสาร (Hidden) | Ex: "10-95-69-PS0001"
                report_no: $('#report_no').val(), // เลขที่รายงาน (Hidden) | Ex: "PS-0001/2569"
                case_type: $('#case_type').val(), // ประเภทคดี | Ex: "theft" (ลักทรัพย์)
                case_type_other: $('#case_type_other').val(), // อื่นๆ โปรดระบุรายละเอียด (ประเภทคดี) | Ex: "ทำให้เสียทรัพย์" โดยจะแสดงเมื่อเลือกประเภทคดี = "other" 
                report_datetime: $('#report_datetime').val(), // วันที่รับแจ้งเหตุ | Ex: "2024-03-01T10:30"
                source_station: $('#source_station').val(), // รับแจ้งเหตุจาก (สภ./สน.) | Ex: "สภ.เมืองยะลา"
                province_id: $('#provinceID').val(), // จังหวัด | Ex: "95" (ยะลา)
                report_channel: $('#report_channel').val(), // ช่องทางที่รับแจ้ง | Ex: "phone" (ทางโทรศัพท์)
                report_channel_other: $('#report_channel_other').val(), // อื่นๆ โปรดระบุรายละเอียด (ช่องทาง) | Ex: "แจ้งผ่านไลน์" โดยจะแสดงเมื่อเลือกช่องทางที่รับแจ้ง = "other"
                document_no: $('#document_no').val(), // ที่ (เลขที่หนังสือ) | Ex: "ตช 0015/123"
                document_date: $('#document_date').val(), // ลง (ลงวันที่ในหนังสือ) | Ex: "1 มีนาคม 2567" **แต่ตอนนี้รับเป็น text เหมือน "ที่" เพราะยังไม่ชัวร์เรื่องข้อมูลที่รับจริง ๆ

                // ข้อมูลพนักงานสอบสวน
                investigator: {
                    firstname: $('#investigator_firstname').val(), // ชื่อ (พงส.) | Ex: "สมชาย"
                    lastname: $('#investigator_lastname').val(), // นามสกุล (พงส.) | Ex: "ใจดี"
                    phone: $('#investigator_phone').val() // หมายเลขโทรศัพท์ (พงส.) | Ex: "081-234-5678"
                },

                // ส่วนที่ 2: สถานที่เกิดเหตุ
                location_detail: $('#incident_location').val(), // รายละเอียดสถานที่ | Ex: "บ้านเลขที่ 123 หมู่ 1 ต.สะเตง"
                victim: {
                    type: $('input[name="victim_type"]:checked').val(), // ประเภทบุคคล | Ex: "owner" (เจ้าของบ้าน)
                    type_other: $('#victim_type_other_text').val(), // ระบุ (กรณีผู้เกี่ยวข้องอื่นๆ) | Ex: "พยานผู้พบเห็น" โดยจะแสดงเมื่อเลือกประเภทบุคคล = "other"
                    firstname: $('#victim_firstname').val(), // ชื่อ (ผู้เสียหาย) | Ex: "มานี"
                    lastname: $('#victim_lastname').val(), // นามสกุล (ผู้เสียหาย) | Ex: "มีตา"
                    age: $('#victim_age').val() // อายุ (ปี) | Ex: "35"
                },

                // ส่วนที่ 3 & 4: วันเวลาต่างๆ
                incident_datetime: $('#incident_datetime').val(), // วันเวลาที่ผู้เสียหายทราบเหตุ/เกิดเหตุ | Ex: "2024-02-29T22:00"
                investigator_known_datetime: $('#investigator_known_datetime').val(), // วันเวลาที่พนักงานสอบสวนทราบเหตุ | Ex: "2024-03-01T08:00"
                inspection_datetime: $('#inspection_datetime').val(), // วันเวลาที่ทำการตรวจสถานที่เกิดเหตุ | Ex: "2024-03-01T11:00"
                inspection_additional_datetime: $('#inspection_additional_datetime').val(), // วันเวลาที่ทำการตรวจสถานที่เกิดเหตุเพิ่มเติม | Ex: "2024-03-02T10:00"
                inspection_end_datetime: $('#inspection_end_datetime').val() // วันเวลาที่ตรวจเสร็จ | Ex: "2024-03-01T15:00" 
            },

            // ส่วนที่ 6: ลักษณะสถานที่เกิดเหตุ
            scene_characteristics: {
                preservation: $('input[name="scene_preservation"]:checked').val(), // การรักษาสถานที่เกิดเหตุ | Ex: "yes" (มีการรักษา)
                preservation_detail: $('#preservation_text').val(), // รายละเอียดเพิ่มเติม (การรักษา) | Ex: "กั้นเชือกโดยตำรวจสายตรวจ" ซึ่งจะมี / ไม่มีการรักษาสถานที่เกิดเหตุ ก็จะต้องกรอก field นี้อยู่ดี

                // ลักษณะภายนอก (สิ่งปลูกสร้าง)
                building_floor: $('#building_floor').val(), // ชั้น | Ex: "2"
                building_types: getCheckboxValues('building_type[]'), // ประเภทสิ่งปลูกสร้าง (Array) | Ex: ["concrete", "wood"]
                building_detail_other: $('#building_detail_other').val(), // รายละเอียด อื่นๆ | Ex: "โกดังเก็บของ"

                fence: $('input[name="fence_type"]:checked').val(), // ลักษณะรั้วกั้น | Ex: "has_fence" (มีรั้วกั้น)
                surroundings: {
                    front: $('input[name="scene_front"]').val(), // ด้านหน้า | Ex: "ติดถนนใหญ่"
                    left: $('input[name="scene_left"]').val(), // ด้านซ้าย | Ex: "ติดบ้านเลขที่ 125"
                    right: $('input[name="scene_right"]').val(), // ด้านขวา | Ex: "ที่รกร้าง"
                    back: $('input[name="scene_back"]').val() // ด้านหลัง | Ex: "ติดคลอง"
                },
                interior_detail: $('#scene_interior').val(), // ลักษณะภายใน | Ex: "เป็นห้องโถงโล่ง มีเฟอร์นิเจอร์ไม้"
                point_detail: $('#scene_point').val() // บริเวณที่เกิดเหตุ | Ex: "ห้องนอนชั้น 2"
            },

            // ส่วนที่ 7: ผลการตรวจสถานที่เกิดเหตุ (พฤติการณ์คดี)
            case_behavior_info: {
                behavior_text: $('#case_behavior').val(), // พฤติการณ์ของคดี | Ex: "คนร้ายงัดหน้าต่างเข้ามาขโมยทรัพย์สิน..."

                // ทางเข้าของคนร้าย
                entry_points: getCheckboxValues('entry_point[]'), // ลักษณะทางเข้า (Array) | Ex: ["found_trace", "pry"] (พบร่องรอย, การงัด)
                entry_other_detail: $('input[name="entry_other_trace_detail"]').val(), // อื่นๆ (ร่องรอย) | Ex: "รอยทุบกระจก" โดยจะแสดงเมื่อเลือกประเภทบุคคล = "other"
                entry_locations_detail: {}, // รายละเอียดจุดเข้า (Loop เติมค่าทีหลัง) | Ex: { window: "หน้าต่างบานเลื่อนทิศตะวันออก" } โดยจะแสดงเมื่อเลือกตัวเลือกใดก็ตามทุกตัว

                // เครื่องมือที่คนร้ายใช้
                burglary_tools: getCheckboxValues('burglary_tool[]'), // เครื่องมือ (Array) | Ex: ["screwdriver"] (ไขควง)
                tool_other_detail: $('input[name="tool_other_detail"]').val(), // ระบุเครื่องมือ (อื่นๆ) | Ex: "เสียม" โดยจะแสดงเมื่อเลือกเครื่องมือ = "other"

                // การใช้อาวุธ
                weapon_status: $('input[name="weapon_use_status"]:checked').val(), // การใช้อาวุธ | Ex: "used" (ใช้อาวุธ)
                weapon_types: getCheckboxValues('weapon_type[]'), // ประเภทอาวุธ (Array) | Ex: ["knife", "gun"] โดยจะแสดงเมื่อเลือกการใช้อาวุธ= "used"
                weapon_other_detail: $('input[name="weapon_other_detail"]').val(), // ระบุ (อาวุธอื่นๆ) | Ex: "สนับมือ" โดยจะแสดงเมื่อเลือกประเภทอาวุธ = "other"

                // คดีชิงทรัพย์/ปล้นทรัพย์
                perpetrator_count: $('#perpetrator_count').val(), // จำนวนคนร้าย (คน) | Ex: "2"
                restraint_methods: getCheckboxValues('restraint_method[]'), // การพันธนาการ (Array) | Ex: ["binding"] (มัด)
                binding_material: $('input[name="binding_material"]').val(), // ระบุวัสดุ (การมัด) | Ex: "เชือกฟาง" โดยจะแสดงเมื่อเลือกการพันธนาการ = "binding"

                // ความเสียหายต่อร่างกาย
                victim_status: getCheckboxValues('victim_status[]'), // สถานะผู้เสียหาย (Array) | Ex: ["injured"] (บาดเจ็บ)
                injury_detail: $('textarea[name="injury_detail"]').val(), // รายละเอียดบาดแผล | Ex: "ศีรษะแตก เย็บ 5 เข็ม" โดยจะ enable เมื่อเลือกสถานะผู้เสียหายใดก็ตาม
                trace_width: $('#trace_width').val() // ขนาดความกว้างของรอย (ซม.) | Ex: "1.5"
            },

            // ส่วนที่ 5: ผู้ตรวจสถานที่เกิดเหตุ (Array)
            inspectors: formData.getAll('inspector_id[]'), // ผู้ตรวจ (ID) | Ex: ["101", "105"]

            // ส่วน Dynamic: จุดที่ตรวจพบร่องรอย & วัตถุพยาน (จะถูกเติมค่าด้านล่าง)
            trace_points: [],
            evidences: [],

            // ส่วนที่ 10: บันทึกการตรวจเก็บวัตถุพยาน (measurements - section 10) — จะถูกเติมค่าด้านล่าง
            measurements: [],
            measurement_meta: {
                inspection_date: $('#measurement_inspection_date_property').val() || '',
                recorder: $('#measurement_recorder_property').val() || '',
                datetime: $('#measurement_datetime_property').val() || ''
            },

            // ส่วนที่ 7: ทรัพย์สินที่หาย
            stolen_property: $('#stolen_property').val(), // ทรัพย์สินถูกโจรกรรม | Ex: "1. สร้อยคอทองคำ\n2. เงินสด 5,000 บาท"

            // ส่วนที่ 8: การส่งมอบคืนสถานที่เกิดเหตุ
            handover: {
                receiver_id: $('#receiver_id').val(), // ผู้รับมอบ (ID) | Ex: "205"
                receiver_pos: $('#receiver_position').val(), // ตำแหน่ง (ผู้รับมอบ) | Ex: "ร.ต.อ." โดยจะ Auto fill ตาม ID ที่เลือก
                receiver_sig: $('#receiver_signature_data').val(), // ลายเซ็นผู้รับมอบ (Base64) | Ex: "data:image/png;base64,..."

                deliverer_id: $('#deliverer_id').val(), // ผู้ส่งมอบ (ID) | Ex: "301"
                deliverer_pos: $('#deliverer_position').val(), // ตำแหน่ง (ผู้ส่งมอบ) | Ex: "นักวิทยาศาสตร์" โดยจะ Auto fill ตาม ID ที่เลือก
                deliverer_sig: $('#deliverer_signature_data').val() // ลายเซ็นผู้ส่งมอบ (Base64) | Ex: "data:image/png;base64,..."
            },

            // ส่วนที่ 9 & 10: แผนผังและรูปภาพ
            attachments_meta: {
                sketch: $('#scene_sketch_data').val(), // รูปแผนผังสังเขป (Base64) | Ex: "data:image/png;base64,..."
                sketch_remark: $('#sketch_remark').val(), // หมายเหตุ (แผนผัง) | Ex: "มาตราส่วนโดยประมาณ"
                photo_start: $('input[name="photo_id_start"]').val(), // รหัสภาพถ่ายที่ | Ex: "IMG_001"
                photo_end: $('input[name="photo_id_end"]').val(), // ถึง | Ex: "IMG_020"
                photo_amount: $('input[name="photo_amount"]').val() // จำนวน (ภาพ) | Ex: "20"
            },

            // ส่วนที่ 11: ข้อมูลผู้จดบันทึก
            recorder_info: {
                name: $('#recorder_name').val(), // ชื่อผู้จดบันทึก | Ex: "ร.ต.ท. สมชาย ใจดี"
                datetime: $('#recorder_datetime').val() // วัน/เวลา | Ex: "2026-03-04T10:30"
            },

            // Final Check
            final_check: getCheckboxValues('final_check[]') // การดำเนินการ (Array) | Ex: ["final_verified", "collected_all"]
        };

        // --- Logic เติมค่า Trace Points วนลูปหาทุก Element ที่มี class="trace-card" (การ์ดแต่ละใบ) ---
        $('.trace-card').each(function() {
            const $card = $(this); // $card คือ การ์ดใบปัจจุบันที่กำลังวนลูปอยู่
            // ดึงค่าจาก input ที่อยู่ "ภายในการ์ดใบนี้เท่านั้น" ($card.find)
            // การใช้ name*="..." เป็นการหา name ที่ "มีคำนี้ผสมอยู่" 
            // เพราะ name จริงๆ อาจจะเป็น trace_points[17123456][area_detail] ตาม id ที่สุ่มขึ้นมา เราจึงหาแค่คำว่า [area_detail]
            payload.trace_points.push({
                area_detail: $card.find('textarea[name*="[area_detail]"]').val(), // บริเวณที่ตรวจพบ | Ex: "ห้องนอนชั้น 2"
                entry: {
                    checked: $card.find('input[name*="[entry_check]"]').is(':checked'), // ทางเข้าของคนร้าย (Checkbox) | Ex: true (ถ้าติ๊ก), false (ถ้าไม่ติ๊ก)
                    detail: $card.find('textarea[name*="[entry_detail]"]').val() // รายละเอียด (ทางเข้า) | Ex: "หน้าต่างบานเลื่อนถูกงัด" โดยจะแสดงเมื่อเลือก ทางเข้าของคนร้าย = "true"
                },
                pry: {
                    checked: $card.find('input[name*="[pry_check]"]').is(':checked'), // รอยงัดแงะ (Checkbox) | Ex: true
                    detail: $card.find('textarea[name*="[pry_detail]"]').val() // ลักษณะรอยงัด | Ex: "รอยกว้าง 1 ซม. บริเวณขอบวงกบ" โดยจะแสดงเมื่อเลือก รอยงัดแงะ = "true"
                },
                rummage: {
                    checked: $card.find('input[name*="[rummage_check]"]').is(':checked'), // ร่องรอยรื้อค้น (Checkbox) | Ex: false
                    detail: $card.find('textarea[name*="[rummage_detail]"]').val() // รายการทรัพย์สิน/รายละเอียด | Ex: "ลิ้นชักตู้เสื้อผ้าถูกดึงออก" โดยจะแสดงเมื่อเลือก ร่องรอยรื้อค้น = "true"
                }
            });
        });

        // --- Logic เติมค่า Evidences วนลูปหาทุก Element ที่มี class="evidence-card" ---
        $('.evidence-card').each(function() {
            const $card = $(this);
            const evidenceTypeVal = $card.find('.evidence-type-select').val() || '';
            let evidenceDetailVal = $card.find('input[name*="[detail]"]:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea[name*="[detail]"]').first().val() || '';
            if (!String(evidenceDetailVal).trim()) {
                const evidenceTypeTextMap = {
                    blood: 'คราบสีแดงคล้ายโลหิต',
                    fingerprint: 'ลายนิ้วมือ/ฝ่ามือ/ฝ่าเท้าแฝง',
                    dna: 'สารพันธุกรรม',
                    toolmark: 'ร่องรอยการตัด (Toolmark)',
                    other: 'อื่น ๆ'
                };
                evidenceDetailVal = evidenceTypeTextMap[evidenceTypeVal] || '';
            }

            // สร้าง Object ข้อมูลของวัตถุพยานแต่ละรายการ แล้วเพิ่มเข้าสู่ Array payload.evidences
            payload.evidences.push({
                no: $card.find('.evidence-no-input').val(), // ลำดับที่ (auto-generated) | Ex: "0001", "0002"
                type: evidenceTypeVal, // ประเภทวัตถุพยาน | Ex: "blood" (เลือด), "dna" (ดีเอ็นเอ)
                lab_unit: window.getLabUnits($card.find('[name*="[lab_unit]"]')), // หน่วยงานที่ส่งตรวจ (array) | Ex: ["bio_dna","fingerprint"]
                detail: evidenceDetailVal, // รายละเอียดวัตถุพยาน | Ex: "คราบเลือดบนเสื้อเชิ้ตสีขาว"
                area_found: $card.find('textarea[name*="[area_found]"]').val(), // บริเวณที่พบ | Ex: "บนพื้นห้องนอน"
                label_no: $card.find('input[name*="[label_no]"]').val(), // ป้ายหมายเลข | Ex: "1", "2"
                azimuth: $card.find('input[name*="[azimuth]"]').val(), // พิกัด/Azimuth | Ex: "N 13.75, E 100.50"
                quantity: {
                    val: $card.find('input[name*="[quantity_val]"]').val(), // จำนวน | Ex: "1"
                    unit: $card.find('select[name*="[quantity_unit]"]').val() // หน่วย | Ex: "ชิ้น", "กล่อง"
                },
                // การบรรจุหีบห่อ (เก็บสถานะ true/false และข้อความเพิ่มเติม)
                packaging: {
                    plastic: $card.find('input[value="plastic"]').is(':checked'), // พลาสติก | Ex: true/false
                    paper: $card.find('input[value="paper"]').is(':checked'), // กระดาษ | Ex: true/false
                    other: $card.find('input[value="other"]').is(':checked'), // บรรจุภัณฑ์อื่นๆ | Ex: true/false
                    other_text: $card.find('input[name*="[package_other_text]"]').val() // รายละเอียดบรรจุภัณฑ์อื่นๆ | Ex: "กล่องโลหะ" แสดงกรณีที่เลือก "อื่น ๆ"
                },
                // จุดอ้างอิง (Reference Points) 1-4
                // ใช้ map เพื่อวนลูปสร้าง object สำหรับจุดอ้างอิงทั้ง 4 จุด
                ref_points: [1, 2, 3, 4].map(i => ({
                    dist: $card.find(`input[name*="[ref${i}_dist]"]`).val(), // ระยะห่าง (เมตร) | Ex: "1.5"
                    desc: $card.find(`input[name*="[ref${i}_desc]"]`).val() // รายละเอียดจุดอ้างอิง | Ex: "ผนังห้องด้านทิศเหนือ"
                })),
                // การดำเนินการต่อวัตถุพยาน
                action: {
                    return: $card.find('input[value="return_investigator"]').is(':checked'), // ส่งคืนพนักงานสอบสวน (พงส.) | Ex: true/false
                    other: $card.find('input[value="other"]').is(':checked'), // ดำเนินการอื่นๆ | Ex: true/false
                    other_text: $card.find('input[name*="[action_other_text]"]').val() // รายละเอียดการดำเนินการอื่นๆ | Ex: "เก็บรักษาที่ห้องเย็น" โดยจะแสดงเมื่อเลือก อื่น ๆ
                },
                remark: $card.find('textarea[name*="[remark]"]').val() // หมายเหตุเพิ่มเติม | Ex: "สภาพเปียกชื้น"
            });
        });

        // --- Logic เติมค่า Measurements (Section 10: บันทึกการตรวจเก็บวัตถุพยาน) ---
        $('#measurement_container_property .measurement-card-property').each(function(idx) {
            const $card = $(this);
            const item = ($card.find('input[name="measurement_item_property[]"]').val() || '').trim();
            const quantity = $card.find('input[name="measurement_quantity_property[]"]').val() || '';
            const area = $card.find('input[name="measurement_area_property[]"]').val() || '';
            const labelNumber = $card.find('input[name="measurement_label_number_property[]"]').val() || '';
            const remark = $card.find('input[name="measurement_remark_property[]"]').val() || '';
            const forensicUnit = window.getLabUnits($card.find('[name="measurement_forensic_unit_property[]"]'));

            // ข้าม card ที่ไม่มีข้อมูลเลย
            if (!item && !quantity && !area && !labelNumber && !remark && !forensicUnit.length) return;

            payload.measurements.push({
                item: item,
                quantity: quantity,
                area: area,
                label_number: labelNumber,
                remark: remark,
                package_plastic: $card.find('input[name^="measurement_package_plastic_check_prop_"]').is(':checked'),
                package_plastic_text: $card.find('input[name^="measurement_package_plastic_text_prop_"]').val() || '',
                package_paper: $card.find('input[name^="measurement_package_paper_check_prop_"]').is(':checked'),
                package_paper_text: $card.find('input[name^="measurement_package_paper_text_prop_"]').val() || '',
                package_other: $card.find('input[name^="measurement_package_other_check_prop_"]').is(':checked'),
                package_other_text: $card.find('input[name^="measurement_package_other_text_prop_"]').val() || '',
                action_return: $card.find('input[name^="measurement_action_return_check_prop_"]').is(':checked'),
                action_return_text: $card.find('input[name^="measurement_action_return_text_prop_"]').val() || '',
                action_other: $card.find('input[name^="measurement_action_other_check_prop_"]').is(':checked'),
                action_other_text: $card.find('input[name^="measurement_action_other_text_prop_"]').val() || '',
                forensic_unit: forensicUnit
            });
        });

        // --- Merge forensic_unit จาก measurements ลงใน evidences (ตาม index) ---
        // เพื่อให้ F-CS-11 generator ที่ดึงจาก evidences[].lab_unit แสดงค่าได้ถูกต้อง
        payload.evidences.forEach((ev, idx) => {
            if (!ev._summary_only) {
                // หา measurement ที่ idx เดียวกัน (ข้าม blood summary)
                const realIdx = payload.evidences.slice(0, idx).filter(e => !e._summary_only).length;
                if (payload.measurements[realIdx] && payload.measurements[realIdx].forensic_unit && payload.measurements[realIdx].forensic_unit.length) {
                    ev.lab_unit = payload.measurements[realIdx].forensic_unit;
                }
            }
        });

        // --- Logic เก็บข้อมูลจากส่วน "คราบสีแดงคล้ายโลหิต" (Static Blood Section) ---
        // ส่วนนี้อยู่แยกจาก .evidence-card เป็น section คงที่ในฟอร์ม
        // ต้อง map ค่าผลทดสอบจากภาษาไทยเป็น 'change'/'no_change' ตามที่ PHP คาดหวัง
        if ($('input[name="evidence_blood_stain"]').is(':checked')) {
            const hemastixResultVal = $('input[name="hemastix_result"]:checked').val() || '';
            const phenolResultVal = $('input[name="phenol_result"]:checked').val() || '';

            payload.evidences.push({
                _summary_only: true, // flag: ใช้แค่สรุป checkbox หน้า 3 ไม่ต้องแสดงในตารางหน้า 5/6
                type: 'blood',
                detail: $('input[name="blood_stain_detail"]').val() || '',
                blood_test: {
                    hemastix_tested: $('input[name="test_hemastix"]').is(':checked'),
                    hemastix_result: hemastixResultVal.indexOf('เขียวแกมน้ำเงิน') !== -1 ? 'change' : hemastixResultVal.indexOf('ไม่มีการเปลี่ยนแปลง') !== -1 ? 'no_change' : '',
                    phenol_tested: $('input[name="test_phenolphthalein"]').is(':checked'),
                    phenol_result: phenolResultVal.indexOf('สีชมพู') !== -1 ? 'change' : phenolResultVal.indexOf('ไม่มีการเปลี่ยนแปลง') !== -1 ? 'no_change' : ''
                }
            });
        }

        // --- Logic เติมค่า Entry Location Details วนลูปตาม Key ที่กำหนดไว้ (ประตู, หน้าต่าง, ฝ้า, หลังคา, อื่นๆ) ---
        ['door', 'window', 'ceiling', 'roof', 'other_location'].forEach(key => {
            if ($(`#entry_${key}`).is(':checked')) {

                // ถ้าติ๊ก -> ให้ดึงค่าจากช่อง Text (เช่น #entry_detail_door)
                // แล้วยัดใส่ Object โดยใช้ key เป็นชื่อ property
                payload.case_behavior_info.entry_locations_detail[key] = $(`#entry_detail_${key}`).val();
            }
        });

        // --- Confirmation & AJAX Submission (★ BLOB-based เหมือน fire) ---
        Swal.fire({
            title: 'ยืนยันการบันทึกข้อมูล',
            text: "กรุณาตรวจสอบความถูกต้องก่อนบันทึก",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#d33',
            confirmButtonText: 'ยืนยัน, บันทึกเลย!',
            cancelButtonText: 'ยกเลิก',
        }).then(async (result) => {
            if (result.isConfirmed) {

                // 📢 DEBUG: แสดงข้อมูลที่จะส่งใน Console
                console.group("📦 DATA PREPARED FOR SUBMIT (Property - BLOB mode)");
                console.log("JSON Payload:", payload);
                console.log("Attachment Store:", attachmentStore);
                console.groupEnd();

                // 1. Prepare FormData
                const submissionData = new FormData();
                submissionData.append('payload', JSON.stringify(payload));

                // 2. ★ ส่ง signature / sketch เป็น Blob file (เหมือน fire)
                //    ตรวจทั้ง standard canvas และ PDF canvas (กรณี save จาก PDF form)
                //    ★ ถ้า save จาก PDF form → เช็ค PDF canvas ก่อน (มีข้อมูลใหม่ที่ user วาด)
                const preferPdf = !!window._savingFromPdfForm;
                window._savingFromPdfForm = false; // reset flag
                // ★ แผนผังของฟอร์ม PDF เป็นแบบหลายหน้า (ppf_sketch_page_N_canvas)
                //    ppfCollectSketchPagesData() รวมภาพ (พื้นขาว + รูปพื้นหลัง + เส้นที่วาด)
                //    ไว้ใน hidden input #ppf_scene_sketch_data
                //    ของเดิมไปหา canvas ชื่อ ppf_scene_sketch_canvas ซึ่งไม่มีอยู่จริง
                //    ทำให้ไม่มีการอัปโหลดแผนผัง -> แผนผังไม่ขึ้นในไฟล์รายงาน
                let propSketchHandled = false;
                if (preferPdf) {
                    const ppfSketchHasContent = (window._ppfSketchPages || []).some(function(p) {
                        if (p.bgImage || p._bgImgEl) return true;
                        if (typeof window.sketchHasInk === 'function' && window.sketchHasInk(p.canvasId)) return true;
                        const c = document.getElementById(p.canvasId);
                        if (!c || !c.width || !c.height) return false;
                        try {
                            const px = c.getContext('2d').getImageData(0, 0, c.width, c.height).data;
                            for (let pi = 3; pi < px.length; pi += 4) {
                                if (px[pi] > 0) return true;
                            }
                        } catch (e) { /* skip */ }
                        return false;
                    });
                    if (typeof ppfCollectSketchPagesData === 'function') ppfCollectSketchPagesData();
                    const ppfSketchInp = document.getElementById('ppf_scene_sketch_data');
                    const ppfSketchVal = ppfSketchInp ? (ppfSketchInp.value || '') : '';
                    if (ppfSketchHasContent && ppfSketchVal.indexOf('data:image') === 0) {
                        const ppfSketchBlob = await dataURLtoBlob(ppfSketchVal);
                        if (ppfSketchBlob) {
                            submissionData.append('sig_file_scene_sketch', ppfSketchBlob, 'scene_sketch.png');
                            propSketchHandled = true;
                        }
                    }
                }

                const sigCanvasMap = {
                    // แผนผังจาก PDF form จัดการแยกด้านบนแล้ว จึงเหลือเฉพาะกรณี save จากฟอร์มปกติ
                    ...(propSketchHandled || preferPdf ? {} : { 'scene_sketch': ['scene_sketch_canvas'] }),
                    'receiver_signature': preferPdf ?
                        ['ppf_sig_receiver', 'sig-canvas-receiver'] :
                        ['sig-canvas-receiver', 'ppf_sig_receiver'],
                    'sender_signature': preferPdf ?
                        ['ppf_sig_sender', 'sig-canvas-deliverer'] :
                        ['sig-canvas-deliverer', 'ppf_sig_sender']
                };
                for (const [key, canvasIds] of Object.entries(sigCanvasMap)) {
                    let blobSent = false;
                    for (const canvasId of canvasIds) {
                        if (blobSent) break;
                        // ตรวจ SignaturePad ก่อน
                        if (signaturePads[canvasId] && !signaturePads[canvasId].isEmpty()) {
                            const cvs = document.getElementById(canvasId);
                            if (cvs) {
                                const blob = await canvasToBlob(cvs, 'image/png');
                                if (blob) {
                                    submissionData.append('sig_file_' + key, blob, key + '.png');
                                    blobSent = true;
                                }
                            }
                        } else {
                            // Fallback: ตรวจ raw canvas (PDF form canvas อาจไม่มี SignaturePad)
                            const cvs = document.getElementById(canvasId);
                            if (cvs && cvs.width > 0 && cvs.height > 0) {
                                try {
                                    const ctx = cvs.getContext('2d');
                                    const pixelData = ctx.getImageData(0, 0, cvs.width, cvs.height).data;
                                    let hasContent = false;
                                    for (let pi = 3; pi < pixelData.length; pi += 4) {
                                        if (pixelData[pi] > 0) {
                                            hasContent = true;
                                            break;
                                        }
                                    }
                                    if (hasContent) {
                                        const blob = await canvasToBlob(cvs, 'image/png');
                                        if (blob) {
                                            submissionData.append('sig_file_' + key, blob, key + '.png');
                                            blobSent = true;
                                        }
                                    }
                                } catch (e) {
                                    /* skip cross-origin or empty canvas */ }
                            }
                        }
                    }
                }

                // 3. ★ ส่งรายการรูปที่ลบ (BLOB file_id + filename จากดิสก์เดิม)
                // กรณี BLOB (file_id)
                const deletedFileIds = [];
                const deletedFilenames = [];
                if (typeof deletedExistingPhotos !== 'undefined') {
                    deletedExistingPhotos.forEach(function(item) {
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
                    submissionData.append('deleted_photo_file_ids', JSON.stringify(deletedFileIds));
                }
                if (deletedFilenames.length > 0) {
                    submissionData.append('deleted_photos', JSON.stringify(deletedFilenames));
                }

                // 4. Append new photo files (เฉพาะรูปใหม่ที่มี file object)
                if (typeof attachmentStore !== 'undefined' && attachmentStore.length > 0) {
                    attachmentStore.forEach(function(item) {
                        if (item.file) {
                            submissionData.append('incident_photos[]', item.file, item.file.name || 'photo.jpg');
                        }
                    });
                } else if (typeof inputPhotos !== 'undefined' && inputPhotos[0] && inputPhotos[0].files.length > 0) {
                    for (let i = 0; i < inputPhotos[0].files.length; i++) {
                        submissionData.append('incident_photos[]', inputPhotos[0].files[i]);
                    }
                }

                // 5. Show Loading
                Swal.fire({
                    title: 'กำลังบันทึกข้อมูล...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                // 6. AJAX Submit → saveProperty.php (BLOB-based)
                const btnSave = $('#btn_save_all');
                const btnText = btnSave.html();
                btnSave.prop('disabled', true);

                // ★ เช็คเน็ตก่อนส่ง (Offline Mode)
                const saveUrl = './api/incidentCheckList/saveProperty.php';
                if (!navigator.onLine) {
                    btnSave.prop('disabled', false).html(btnText);
                    await saveChecklistOffline(submissionData, saveUrl, '#addCheckListModal');
                    return;
                }
                const backendOk = await checkBackendHealth();
                if (!backendOk) {
                    btnSave.prop('disabled', false).html(btnText);
                    await saveChecklistOffline(submissionData, saveUrl, '#addCheckListModal');
                    return;
                }

                $.ajax({
                    url: saveUrl,
                    type: 'POST',
                    data: submissionData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(data) {
                        btnSave.prop('disabled', false).html(btnText);
                        if (data.status === 'success' || data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'สำเร็จ',
                                text: data.message || 'บันทึกข้อมูลเรียบร้อย',
                                confirmButtonText: 'ตกลง'
                            }).then(() => {
                                ['addCheckListModal', 'propertyFormPdfModal'].forEach(id => {
                                    const el = document.getElementById(id);
                                    if (el) {
                                        const m = bootstrap.Modal.getInstance(el);
                                        if (m) m.hide();
                                    }
                                });
                                if (typeof resetPropertyForm === 'function') resetPropertyForm();
                                if (typeof loadData === 'function') loadData();
                            });
                        } else {
                            Swal.fire('บันทึกไม่สำเร็จ', data.message || 'Unknown error', 'error');
                        }
                    },
                    error: async function(xhr, status, error) {
                        btnSave.prop('disabled', false).html(btnText);
                        console.error('AJAX Error:', error);
                        // ★ ส่งไม่ได้ → fallback Offline
                        await saveChecklistOffline(submissionData, saveUrl, '#addCheckListModal');
                    }
                });
            }
        });
    }

    // --- Save Data Logic  ---
    // $('#incidentCheckListForm').on('submit', function(e) {
    //     e.preventDefault();
    //     const form = this;

    //     // --- ตรวจสอบความถูกต้อง (Validation) ---
    //     if (!form.checkValidity()) {
    //         e.stopPropagation();
    //         $(form).addClass('was-validated');

    //         const invalidElement = findFirstInvalidInput(form);
    //         if (invalidElement) {
    //             $invalidElement = $(invalidElement);
    //             isSelect2 = $invalidElement.hasClass('select2-hidden-accessible');

    //             const $modal = $invalidElement.closest('.modal');
    //             const $target = isSelect2 ? $invalidElement.next('.select2-container') : $invalidElement;

    //             // --- กรณีอยู่ใน Modal ---
    //             if ($modal.length) {
    //                 const modalBody = $modal.find('.modal-body');
    //                 const scrollTopCurrent = modalBody.scrollTop();
    //                 const elementTop = $target.offset().top;
    //                 const modalBodyTop = modalBody.offset().top;
    //                 const modalHeight = modalBody.height();
    //                 const elementHeight = $target.outerHeight();

    //                 // สูตร: (ตำแหน่ง Element เทียบกับ Modal) + Scroll ปัจจุบัน - (ครึ่งหนึ่งของ Modal) + (ครึ่งหนึ่งของ Element)
    //                 // ผลลัพธ์: Element จะอยู่กลาง Modal พอดี
    //                 const targetScroll = (elementTop - modalBodyTop + scrollTopCurrent) - (modalHeight / 2) + (elementHeight / 2);

    //                 modalBody.animate({
    //                     scrollTop: targetScroll
    //                 }, 500);

    //             } else {
    //                 // --- กรณีอยู่หน้าปกติ (Window) ---
    //                 // ใช้ jQuery animate แทน scrollIntoView เพื่อคุมตำแหน่งได้แม่นกว่า
    //                 const windowHeight = $(window).height();
    //                 const elementTop = $target.offset().top;
    //                 const elementHeight = $target.outerHeight();

    //                 // สูตร: ตำแหน่ง Element ในหน้าเว็บ - (ครึ่งจอ) + (ครึ่ง Element)
    //                 // ผลลัพธ์: Element จะลอยอยู่กลางจอเป๊ะ
    //                 const targetScroll = elementTop - (windowHeight / 2) + (elementHeight / 2);

    //                 $('html, body').animate({
    //                     scrollTop: targetScroll
    //                 }, 500);
    //             }
    //         }

    //         Swal.fire({
    //             icon: 'warning',
    //             title: 'ข้อมูลไม่ครบถ้วน',
    //             text: 'กรุณากรอกข้อมูลในช่องที่มีเครื่องหมาย * ให้ครบ',
    //             confirmButtonText: 'ตกลง',
    //             confirmButtonColor: '#3085d6',
    //             returnFocus: false
    //         }).then((result) => {
    //             if (result.isConfirmed || result.isDismissed) {
    //                 if (invalidElement) {
    //                     setTimeout(() => {
    //                         if (isSelect2) {
    //                             $invalidElement.select2('open');
    //                         } else {
    //                             invalidElement.focus();
    //                         }
    //                     }, 300);
    //                 }
    //             }
    //         });
    //         return;
    //     }

    //     // --- เตรียมข้อมูลลายเซ็น (Signature) ---
    //     // 1. ลายเซ็นผู้รับมอบ
    //     const receiverPad = signaturePads['sig-canvas-receiver']; // ใช้ Global var ที่ประกาศไว้
    //     if (receiverPad && !receiverPad.isEmpty()) {
    //         $('#receiver_signature_data').val(receiverPad.toDataURL('image/png'));
    //     } else {
    //         $('#receiver_signature_data').val(''); // ถ้าว่างให้เคลียร์ค่า
    //     }

    //     // 2. ลายเซ็นผู้ส่งมอบ
    //     const delivererPad = signaturePads['sig-canvas-deliverer'];
    //     if (delivererPad && !delivererPad.isEmpty()) {
    //         $('#deliverer_signature_data').val(delivererPad.toDataURL('image/png'));
    //     } else {
    //         $('#deliverer_signature_data').val('');
    //     }

    //     // จัดการแผนผังสังเขป
    //     const sketchPad = signaturePads['scene_sketch_canvas'];
    //     if (sketchPad && !sketchPad.isEmpty()) {
    //         $('#scene_sketch_data').val(sketchPad.toDataURL('image/png'));
    //     } else {
    //         $('#scene_sketch_data').val('');
    //     }

    //     // --- ยืนยันก่อนบันทึก (Confirmation) ---
    //     Swal.fire({
    //         title: 'ยืนยันการบันทึกข้อมูล',
    //         text: "เมื่อบันทึกแล้วจะไม่สามารถกลับมาแก้ไขข้อมูลได้อีก คุณแน่ใจหรือไม่?",
    //         icon: 'warning',
    //         showCancelButton: true,
    //         confirmButtonColor: '#198754',
    //         cancelButtonColor: '#d33',
    //         confirmButtonText: 'ยืนยัน, บันทึกเลย!',
    //         cancelButtonText: 'ยกเลิก',
    //     }).then((result) => {
    //         if (result.isConfirmed) {

    //             // 1. สร้าง FormData Object ขึ้นมาก่อน
    //             var formData = new FormData(form);

    //             // 2. --- [Console Log แบบระบุ Type] ---
    //             console.group("📦 Form Data Inspector");
    //             console.log("Endpoint URL:", $(form).attr('action'));
    //             console.log("------------------------------------");

    //             for (var pair of formData.entries()) {
    //                 let key = pair[0];
    //                 let value = pair[1];
    //                 let typeDisplay = '';
    //                 let valueDisplay = value;
    //                 let typeStyle = 'color: green; font-style: italic;'; // สี Default ของ Type

    //                 // เช็คว่าเป็น File หรือไม่
    //                 if (value instanceof File) {
    //                     typeDisplay = 'FILE';
    //                     typeStyle = 'color: red; font-weight: bold;'; // สีแดงสำหรับไฟล์
    //                     // โชว์รายละเอียดไฟล์แทนที่จะโชว์ Object ดิบๆ
    //                     valueDisplay = `📄 Name: ${value.name} | 📏 Size: ${(value.size/1024).toFixed(2)} KB | 🏷️ Type: ${value.type}`;
    //                 } else {
    //                     // ถ้าไม่ใช่ไฟล์ ปกติ FormData จะมองเป็น String หมด
    //                     typeDisplay = typeof value;

    //                     // (Optional) เช็คว่าเป็นตัวเลขในร่าง String หรือไม่ (เพื่อการดูที่ง่ายขึ้น)
    //                     if (value !== "" && !isNaN(value)) {
    //                         typeDisplay += " (Numeric String)";
    //                     }

    //                     // ใส่เครื่องหมายคำพูด "" เพื่อให้เห็นชัดกรณีค่าว่าง หรือมีเว้นวรรค
    //                     valueDisplay = `"${value}"`;
    //                 }

    //                 // Log ออกมาบรรทัดเดียว: ชื่อตัวแปร [TYPE] => ค่า
    //                 console.log(
    //                     `%c${key} %c[${typeDisplay.toUpperCase()}]`,
    //                     'color: blue; font-weight: bold;',
    //                     typeStyle,
    //                     '=>',
    //                     valueDisplay
    //                 );
    //             }

    //             console.log("------------------------------------");
    //             console.groupEnd();
    //             // ------------------------------------

    //             // --- ส่งข้อมูลด้วย AJAX (ทำงานเมื่อกดยืนยันเท่านั้น) ---
    //             $.ajax({
    //                 url: $(form).attr('action'),
    //                 type: 'POST',
    //                 data: new FormData(form),
    //                 processData: false,
    //                 contentType: false,
    //                 dataType: 'json',
    //                 beforeSend: function() {
    //                     $('#btn_save_all').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...');
    //                 },
    //                 success: function(response) {
    //                     if (response.status == "success") {
    //                         Swal.fire({
    //                             icon: 'success',
    //                             title: 'บันทึกสำเร็จ',
    //                             text: 'ข้อมูลถูกบันทึกเรียบร้อยแล้ว',
    //                             timer: 1500,
    //                             showConfirmButton: false
    //                         }).then(() => {
    //                             window.location.reload();
    //                         });
    //                     } else {
    //                         Swal.fire('เกิดข้อผิดพลาด', response.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
    //                     }
    //                 },
    //                 error: function(xhr, status, error) {
    //                     console.error("AJAX Error:", xhr.responseText);
    //                     Swal.fire('Server Error', 'เกิดข้อผิดพลาดที่ระบบ กรุณาลองใหม่ หรือติดต่อผู้ดูแลระบบ', 'error');
    //                 },
    //                 complete: function() {
    //                     $('#btn_save_all').prop('disabled', false).html('<i class="fas fa-save me-2"></i> บันทึกข้อมูล');
    //                 }
    //             });
    //         }
    //     });
    // });

    // =====================================================
    // OFFLINE SAVE FUNCTIONS (ใช้ IndexedDB ผ่าน Dexie.js)
    // ★ เปลี่ยนจาก localStorage → IndexedDB เพื่อรองรับไฟล์ขนาดใหญ่
    //   - localStorage จำกัด ~5-10MB (base64 บวม 33%)
    //   - IndexedDB เก็บได้หลายร้อย MB และเก็บ Blob ตรงๆ ไม่ต้องแปลง base64
    // =====================================================

    // เช็ค Backend Health (with timeout)
    async function checkBackendHealth() {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout
            const res = await fetch('/csims/api/health_check.php', {
                method: 'GET',
                cache: 'no-store',
                signal: controller.signal
            });
            clearTimeout(timeoutId);
            const data = await res.json();
            return data.status === 'ok';
        } catch (err) {
            return false;
        }
    }

    // ★ บันทึกข้อมูล Checklist ลง IndexedDB (Offline Mode)
    // formData = FormData object ที่เตรียมไว้แล้ว, url = API endpoint
    // ★ เก็บ Blob ตรงๆ ใน IndexedDB — ไม่ต้องแปลง base64 → ประหยัดพื้นที่ + เร็วขึ้น
    async function saveChecklistOffline(submissionFormData, url, modalId) {
        try {
            Swal.fire({
                title: 'กำลังเตรียมข้อมูล Offline...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const offlineData = {
                url: url,
                method: 'POST',
                payload: null,
                signatures: {}, // { sig_file_xxx: Blob }
                photos: [], // [{ fieldName, filename, blob: Blob }]
                textFields: [], // [{ key, value }]
                deletedPhotoFileIds: null,
                deletedPhotos: null,
                clearedSignatures: null,
                existingPhotosJson: null,
                saved_at: new Date().toISOString()
            };

            // วน FormData เพื่อแยกเก็บแต่ละ field (รองรับทุก checklist type)
            for (const [key, value] of submissionFormData.entries()) {
                if (key === 'payload') {
                    offlineData.payload = value; // JSON string
                } else if (key.startsWith('sig_file_')) {
                    // ลายเซ็น/แผนผัง → เก็บ Blob ตรงๆ
                    offlineData.signatures[key] = value;
                } else if (value instanceof Blob || value instanceof File) {
                    // ไฟล์ทุกประเภท (รูปภาพ, camera) → เก็บ Blob ตรงๆ
                    offlineData.photos.push({
                        fieldName: key,
                        filename: value.name || 'photo.jpg',
                        blob: value
                    });
                } else if (key === 'deleted_photo_file_ids') {
                    offlineData.deletedPhotoFileIds = value;
                } else if (key === 'deleted_photos') {
                    offlineData.deletedPhotos = value;
                } else if (key === 'cleared_signatures') {
                    offlineData.clearedSignatures = value;
                } else if (key === 'existing_photos_json') {
                    offlineData.existingPhotosJson = value;
                } else {
                    // ฟิลด์ข้อความทั่วไป (สำหรับ Bomb/Traffic ที่ใช้ new FormData(form))
                    offlineData.textFields.push({
                        key: key,
                        value: value
                    });
                }
            }

            // ★ เก็บลง IndexedDB (Dexie) — รองรับ Blob ไม่จำกัดขนาด
            await db.checklistQueue.add(offlineData);

            // ปิด modal + แจ้ง user
            if (modalId) $(modalId).modal('hide');

            Swal.fire({
                icon: 'info',
                title: 'บันทึกแบบออฟไลน์',
                text: 'ระบบบันทึกข้อมูลไว้แล้ว จะส่งให้อัตโนมัติเมื่อเชื่อมต่ออินเทอร์เน็ต',
                showConfirmButton: false,
                timer: 2500
            });

            updateSyncUI();
        } catch (err) {
            console.error('saveChecklistOffline error:', err);
            Swal.fire('ผิดพลาด', 'ไม่สามารถบันทึกข้อมูลแบบ Offline ได้: ' + (err.message || err), 'error');
        }
    }

    // ★ Sync ข้อมูล Checklist ที่ค้างอยู่กลับไป Server (อ่านจาก IndexedDB)
    async function syncChecklistQueue() {
        if (!navigator.onLine) return;
        const backendOk = await checkBackendHealth();
        if (!backendOk) return;

        const queue = await db.checklistQueue.toArray();
        if (queue.length === 0) return;

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });

        Toast.fire({
            icon: 'info',
            title: 'กำลังส่งข้อมูลเช็คลิสต์ที่ค้างอยู่...'
        });

        let hasSynced = false;

        for (const item of queue) {
            try {
                // สร้าง FormData ใหม่จากข้อมูลที่เก็บไว้ใน IndexedDB
                const fd = new FormData();

                // 1. textFields (สำหรับ Bomb/Traffic ที่ใช้ new FormData(form))
                if (item.textFields && item.textFields.length > 0) {
                    item.textFields.forEach(f => fd.append(f.key, f.value));
                }

                // 2. payload (JSON string)
                if (item.payload) {
                    fd.append('payload', item.payload);
                }

                // 3. signatures (Blob ตรงๆ จาก IndexedDB — ไม่ต้องแปลง)
                if (item.signatures) {
                    for (const [sigKey, sigBlob] of Object.entries(item.signatures)) {
                        const filename = sigKey.replace('sig_file_', '') + '.png';
                        fd.append(sigKey, sigBlob, filename);
                    }
                }

                // 4. photos (Blob ตรงๆ จาก IndexedDB — ไม่ต้องแปลง)
                if (item.photos && item.photos.length > 0) {
                    item.photos.forEach(photo => {
                        fd.append(photo.fieldName, photo.blob, photo.filename);
                    });
                }

                // 5. deleted photo file ids
                if (item.deletedPhotoFileIds) {
                    fd.append('deleted_photo_file_ids', item.deletedPhotoFileIds);
                }
                if (item.deletedPhotos) {
                    fd.append('deleted_photos', item.deletedPhotos);
                }
                if (item.clearedSignatures) {
                    fd.append('cleared_signatures', item.clearedSignatures);
                }

                // 6. existing photos json (Traffic)
                if (item.existingPhotosJson) {
                    fd.append('existing_photos_json', item.existingPhotosJson);
                }

                // ส่ง AJAX
                const res = await $.ajax({
                    url: item.url,
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    dataType: 'json'
                });

                if (res.status === 'success' || res.success) {
                    // ★ ลบ record ที่ส่งสำเร็จออกจาก IndexedDB
                    await db.checklistQueue.delete(item.id);
                    hasSynced = true;
                } else {
                    break;
                }
            } catch (err) {
                console.error('syncChecklistQueue error:', err);
                break;
            }
        }

        updateSyncUI();

        if (hasSynced) {
            Toast.fire({
                icon: 'success',
                title: 'ส่งข้อมูลเช็คลิสต์ครบถ้วนแล้ว'
            }).then(() => {
                setTimeout(() => window.location.reload(), 1000);
            });
        }
    }

    // เมื่อเน็ตกลับมา → sync อัตโนมัติ
    window.addEventListener('online', async () => {
        let attempts = 0;
        const maxAttempts = 30;
        const checkInterval = setInterval(async () => {
            attempts++;
            const isServerReady = await checkBackendHealth();
            if (isServerReady) {
                clearInterval(checkInterval);
                syncChecklistQueue();
                updateSyncUI();
            } else if (attempts >= maxAttempts) {
                clearInterval(checkInterval);
            }
        }, 2000);
    });

    // --- Pagination ---
    function setupPagination(totalPages, currentPage) {
        const total = parseInt(totalPages);
        const current = parseInt(currentPage);
        $('#pagination-list').twbsPagination('destroy');
        if (total <= 0) return;

        setTimeout(function() {
            $('#pagination-list').twbsPagination({
                totalPages: total,
                startPage: current,
                visiblePages: 5,
                first: '<i class="fas fa-angle-double-left"></i>',
                prev: '<i class="fas fa-angle-left"></i>',
                next: '<i class="fas fa-angle-right"></i>',
                last: '<i class="fas fa-angle-double-right"></i>',
                initiateStartPageClick: false,
                onPageClick: function(event, page) {
                    if (page !== current) searchPage(page);
                }
            });
        }, 50);
    }

    // ===== Global: เรียกจาก save callback ของทุก modal =====
    function loadData() {
        searchPage(1);
    }
</script>

<!-- ========================================================================
     Handwriting Recognition Modal + Script
     ======================================================================== -->
<style>
    /* บังคับตัดบรรทัด (word wrap) ให้ textarea ทุกฟอร์ม PDF checklist
       แก้ปัญหาข้อความภาษาไทยยาว ๆ ไม่ขึ้นบรรทัดใหม่ (วิ่งไปทางขวาแทน) */
    [id*="PdfModal"] textarea,
    [id^="modalReport"] textarea {
        white-space: pre-wrap !important;
        overflow-wrap: break-word !important;
        word-break: break-word !important;
    }
    /* textarea แบบ inline เลียนแบบ input (เส้นจุดด้านล่าง) แต่ตัดบรรทัด + auto-grow
       ใช้แทน <input type="text"> สำหรับช่องข้อความอิสระ เช่น รายละเอียด/ความเห็น/หมายเหตุ */
    [id*="PdfModal"] textarea.csims-tline,
    [id^="modalReport"] textarea.csims-tline,
    textarea.csims-tline {
        padding: 0 2px; margin: 0 2px; color: #000; outline: none;
        flex: 1; min-width: 20px; width: auto;
        resize: none; overflow: hidden; line-height: 1.6;
        height: 22px; min-height: 22px;
        white-space: pre-wrap !important; overflow-wrap: break-word !important; word-break: break-word !important;
        vertical-align: bottom;
    }
    .btn-hw-open {
        transition: all 0.2s;
    }

    .btn-hw-open:hover {
        transform: scale(1.1);
    }

    #hwCanvasArea {
        position: relative;
        border: 2px solid #d1d5db;
        border-radius: 12px;
        background: #fff;
        overflow: hidden;
    }

    #hwCanvas {
        display: block;
        cursor: crosshair;
        touch-action: none;
    }

    .hw-canvas-placeholder {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #d1d5db;
        font-size: 1.1rem;
        pointer-events: none;
        transition: opacity .3s;
    }

    .hw-canvas-placeholder.hidden {
        opacity: 0;
    }

    .hw-result-badge {
        display: inline-block;
        padding: 6px 16px;
        margin: 4px;
        border-radius: 20px;
        background: #f3f4f6;
        border: 1.5px solid #e5e7eb;
        cursor: pointer;
        font-size: 1rem;
        transition: all .2s;
    }

    .hw-result-badge:hover {
        background: #6366f1;
        color: #fff;
        border-color: #6366f1;
        transform: scale(1.05);
    }

    .hw-result-badge.selected {
        background: #6366f1;
        color: #fff;
        border-color: #6366f1;
    }

    .hw-pen-size-group .btn.active {
        background: #6366f1 !important;
        border-color: #6366f1 !important;
        color: #fff !important;
    }
</style>

<div class="modal fade" id="hwModal" tabindex="-1" data-bs-backdrop="static" style="z-index:1070;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">
            <div class="modal-header" style="background:linear-gradient(135deg,#6366f1,#8b5cf6); border:none;">
                <h5 class="modal-title text-white"><i class="fas fa-pen-fancy me-2"></i>เขียนด้วยลายมือ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-muted mb-0">ภาษา:</label>
                        <select class="form-select form-select-sm" id="hwLang" style="width:130px;">
                            <option value="th" selected>ไทย</option>
                            <option value="en">English</option>
                            <option value="th,en">ไทย + English</option>
                        </select>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-muted mb-0">ขนาดปากกา:</label>
                        <div class="btn-group btn-group-sm hw-pen-size-group">
                            <button type="button" class="btn btn-outline-secondary" data-size="2">S</button>
                            <button type="button" class="btn btn-outline-secondary active" data-size="4">M</button>
                            <button type="button" class="btn btn-outline-secondary" data-size="7">L</button>
                        </div>
                    </div>
                </div>
                <div id="hwCanvasArea" class="mb-3">
                    <canvas id="hwCanvas" width="660" height="250"></canvas>
                    <div class="hw-canvas-placeholder" id="hwPlaceholder"><i class="fas fa-pen-alt me-2"></i>เขียนตัวอักษรที่นี่...</div>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-primary btn-sm" id="btnHwRecognize"><i class="fas fa-magic me-1"></i>แปลงเป็นข้อความ</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="btnHwUndo"><i class="fas fa-undo me-1"></i>Undo</button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnHwErase"><i class="fas fa-eraser me-1"></i>ลบทั้งหมด</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnHwSpace"><i class="fas fa-arrows-alt-h me-1"></i>เว้นวรรค</button>
                </div>
                <div class="small text-muted mb-2" id="hwStatus"></div>
                <div id="hwResults" class="mb-3">
                    <p class="text-muted small mb-0"><i class="fas fa-arrow-down me-1"></i>ผลลัพธ์จะแสดงที่นี่หลังกด "แปลงเป็นข้อความ"</p>
                </div>
                <hr>
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted mb-0 text-nowrap">ข้อความสะสม:</label>
                    <input type="text" class="form-control form-control-sm" id="hwAccumulated" readonly style="background:#f9fafb; font-size:1.1rem;">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnHwClearAccum" title="ล้างข้อความ"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-success" id="btnHwConfirm"><i class="fas fa-check me-1"></i>ยืนยัน ใส่ข้อความ</button>
            </div>
        </div>
    </div>
</div>

<script src="./js/handwriting.canvas.js"></script>
<script>
    (function() {
        var hwCanvasInstance = null;
        var hwTargetIds = [];
        var hwTargetElement = null; // direct element reference for dynamic rows
        var hwAccumulatedText = '';
        var hwHasDrawn = false;
        var hwStrokeCount = 0; // นับ stroke ที่วาดจริงๆ (ไม่ใช่แค่ click)
        var hwModalInitialized = false; // flag ป้องกัน binding ซ้ำ

        // เปิด modal จากปุ่มปากกา (ใช้ one-time binding หรือ check flag)
        $(document).off('click.hwOpen').on('click.hwOpen', '.btn-hw-open', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var targets = ($(this).data('hw-targets') || '').split(',').map(function(s) {
                return s.trim();
            }).filter(Boolean);
            hwTargetIds = targets;
            // For dynamic rows without data-hw-targets, find sibling input
            hwTargetElement = null;
            if (targets.length === 0 && $(this).hasClass('btn-hw-dynamic')) {
                // ★ ลำดับที่ 1: หา input/textarea ที่อยู่ก่อนหน้าปุ่มโดยตรง (prev sibling)
                // แก้ปัญหากรณี 1 div มี input หลายตัว เช่น tpf-fr ที่มี รถ+ยี่ห้อ หรือ รุ่น+สี
                var $prev = $(this).prev('input[type="text"], textarea');
                if ($prev.length) {
                    hwTargetElement = $prev[0];
                } else {
                    // ★ ลำดับที่ 2: หาจาก container ที่ใกล้ที่สุด (กรณี input เป็นตัวเดียวใน container)
                    var $row = $(this).closest('.input-group, .input-group-sm, .sevpf-si, .sevpf-fr, .fpf-fr, .fpf-si, .fpf-cg, .fpf-method-row, .fpf-photo-row, .fpf-found-row, .pepf-si, .pepf-fr, .pepf-person-card, .pepf-ed-line1, .ppf-fr, .ppf-si, .ppf-evidence-row, .ppf-trace-row, .lpf-fr, .lpf-victim-row, .tpf-fr, .tpf-vehicle-card, .bpf-fr, .bpf-cg, .bpf-i1, .bpf-i2, .bpf-body-row, div');
                    var $inp = $row.find('input[type="text"].form-control, input[type="text"].form-control-sm, input[type="text"].sevpf-inp, input[type="text"].fpf-inp, input[type="text"].fpf-inp-s, input[type="text"].pepf-inp, input[type="text"].pepf-inp-s, input[type="text"].pepf-inp-full, input[type="text"].pepf-evidence-desc-inp, input[type="text"].ppf-inp, input[type="text"].ppf-inp-s, input[type="text"].lpf-inp, input[type="text"].lpf-inp-s, input[type="text"].tpf-inp, input[type="text"].tpf-inp-s, input[type="text"].bpf-inp, input[type="text"].bpf-inp-s').first();
                    if ($inp.length) {
                        hwTargetElement = $inp[0];
                    } else {
                        // ★ ลำดับที่ 3: หา textarea ใน container หรือ sibling ถัดไป
                        var $ta = $row.find('textarea').first();
                        if (!$ta.length) $ta = $row.next('textarea');
                        if (!$ta.length) $ta = $row.nextAll('textarea').first();
                        if (!$ta.length) $ta = $row.next().find('textarea').first();
                        if (!$ta.length) $ta = $row.parent().find('textarea').first();
                        if ($ta.length) hwTargetElement = $ta[0];
                    }
                }
            }
            hwAccumulatedText = '';
            hwHasDrawn = false;
            hwStrokeCount = 0;
            $('#hwAccumulated').val('');
            $('#hwResults').html('<p class="text-muted small mb-0"><i class="fas fa-arrow-down me-1"></i>ผลลัพธ์จะแสดงที่นี่หลังกด "แปลงเป็นข้อความ"</p>');
            $('#hwStatus').text('');
            $('#hwPlaceholder').removeClass('hidden');

            // ใช้ Modal instance เดิมถ้ามี หรือสร้างใหม่
            var modalEl = document.getElementById('hwModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (!modal) modal = new bootstrap.Modal(modalEl);
            modal.show();
        });

        // Init modal events ครั้งเดียว (ใช้ namespace ป้องกันซ้ำ)
        if (!hwModalInitialized) {
            hwModalInitialized = true;
            var $modal = $('#hwModal');

            // Cleanup เมื่อ modal ปิด
            $modal.off('hidden.bs.modal.hw').on('hidden.bs.modal.hw', function() {
                if (hwCanvasInstance) {
                    var cvs = hwCanvasInstance.canvas;
                    var newCanvas = cvs.cloneNode(true);
                    cvs.parentNode.replaceChild(newCanvas, cvs);
                    hwCanvasInstance = null;
                }
                hwStrokeCount = 0;
                hwHasDrawn = false;
            });

            // Init canvas หลัง modal shown
            $modal.off('shown.bs.modal.hw').on('shown.bs.modal.hw', function() {
                // ลบ instance เก่าก่อนสร้างใหม่
                if (hwCanvasInstance) {
                    var oldCvs = hwCanvasInstance.canvas;
                    var newCvs = oldCvs.cloneNode(true);
                    oldCvs.parentNode.replaceChild(newCvs, oldCvs);
                }
                var canvas = document.getElementById('hwCanvas');
                var area = document.getElementById('hwCanvasArea');
                // รีเซ็ตขนาด canvas ตาม container
                canvas.width = area.clientWidth || 660;
                canvas.height = 250;

                hwCanvasInstance = new handwriting.Canvas(canvas, 4);
                hwCanvasInstance.set_Undo_Redo(true, true);
                hwCanvasInstance.setOptions({
                    language: $('#hwLang').val(),
                    numOfReturn: 5
                });
                hwCanvasInstance.setCallBack(function(results, err) {
                    $('#hwStatus').text('');
                    if (err) {
                        $('#hwResults').html('<span class="text-danger small"><i class="fas fa-exclamation-triangle me-1"></i>' + err.message + '</span>');
                        return;
                    }
                    if (!results || results.length === 0) {
                        $('#hwResults').html('<span class="text-warning small"><i class="fas fa-question-circle me-1"></i>ไม่พบผลลัพธ์ ลองเขียนใหม่</span>');
                        return;
                    }
                    var html = '<span class="small text-muted me-2">เลือกผลลัพธ์:</span>';
                    results.forEach(function(text, idx) {
                        html += '<span class="hw-result-badge' + (idx === 0 ? ' selected' : '') + '" data-text="' + $('<span>').text(text).html().replace(/"/g, '&quot;') + '">' + $('<span>').text(text).html() + '</span>';
                    });
                    $('#hwResults').html(html);
                    hwAppendText(results[0]);
                    hwStrokeCount = 0;
                    if (hwCanvasInstance) {
                        hwCanvasInstance.erase();
                        hwHasDrawn = false;
                        $('#hwPlaceholder').removeClass('hidden');
                    }
                });

                // ★ ผูก event tracking โดยตรงบน canvas element เพื่อแก้ปัญหา
                // touchStart/touchMove ของ handwriting library เรียก preventDefault()
                // ทำให้ jQuery delegated event ไม่ทำงานบน iPad/touch devices
                canvas.addEventListener('mousedown', function() {
                    $('#hwPlaceholder').addClass('hidden');
                });
                canvas.addEventListener('touchstart', function() {
                    $('#hwPlaceholder').addClass('hidden');
                }, {
                    passive: true
                });
                canvas.addEventListener('mousemove', function() {
                    hwStrokeCount++;
                    if (hwStrokeCount > 2) hwHasDrawn = true;
                });
                canvas.addEventListener('touchmove', function() {
                    hwStrokeCount++;
                    if (hwStrokeCount > 2) hwHasDrawn = true;
                }, {
                    passive: true
                });
            });

            // ปุ่มต่างๆ (bind ครั้งเดียวด้วย namespace)
            $('#btnHwRecognize').off('click.hw').on('click.hw', function() {
                if (!hwCanvasInstance || !hwHasDrawn || hwStrokeCount < 3) {
                    $('#hwStatus').html('<span class="text-warning"><i class="fas fa-info-circle me-1"></i>กรุณาเขียนก่อนกดแปลง</span>');
                    return;
                }
                hwCanvasInstance.setOptions({
                    language: $('#hwLang').val(),
                    numOfReturn: 5
                });
                $('#hwStatus').html('<i class="fas fa-spinner fa-spin me-1"></i>กำลังแปลง...');
                hwCanvasInstance.recognize();
            });

            $('#btnHwUndo').off('click.hw').on('click.hw', function() {
                if (hwCanvasInstance) {
                    hwCanvasInstance.undo();
                    if (hwCanvasInstance.step.length === 0) {
                        hwHasDrawn = false;
                        hwStrokeCount = 0;
                        $('#hwPlaceholder').removeClass('hidden');
                    }
                }
            });

            $('#btnHwErase').off('click.hw').on('click.hw', function() {
                if (hwCanvasInstance) {
                    hwCanvasInstance.erase();
                    hwHasDrawn = false;
                    hwStrokeCount = 0;
                    $('#hwPlaceholder').removeClass('hidden');
                }
            });

            $('#btnHwSpace').off('click.hw').on('click.hw', function() {
                hwAccumulatedText += ' ';
                $('#hwAccumulated').val(hwAccumulatedText);
            });

            $('#btnHwClearAccum').off('click.hw').on('click.hw', function() {
                hwAccumulatedText = '';
                $('#hwAccumulated').val('');
            });

            // เลือกผลลัพธ์ (delegate แต่ใช้ off ก่อน on กัน double bind)
            $(document).off('click.hwBadge').on('click.hwBadge', '#hwResults .hw-result-badge', function() {
                var prev = $('#hwResults .hw-result-badge.selected');
                if (prev.length) {
                    var prevText = prev.attr('data-text');
                    if (hwAccumulatedText.endsWith(prevText)) {
                        hwAccumulatedText = hwAccumulatedText.slice(0, -prevText.length);
                    }
                }
                $('#hwResults .hw-result-badge').removeClass('selected');
                $(this).addClass('selected');
                hwAppendText($(this).attr('data-text'));
            });

            // ขนาดปากกา
            $(document).off('click.hwPenSize').on('click.hwPenSize', '.hw-pen-size-group .btn', function() {
                $('.hw-pen-size-group .btn').removeClass('active');
                $(this).addClass('active');
                if (hwCanvasInstance) hwCanvasInstance.setLineWidth(parseInt($(this).data('size')));
            });

            // ยืนยัน
            $('#btnHwConfirm').off('click.hw').on('click.hw', function() {
                if (hwAccumulatedText.trim()) {
                    // Direct element reference (dynamic rows)
                    if (hwTargetElement) {
                        hwAppendToElement(hwTargetElement, hwAccumulatedText);
                    }
                    // ID-based targets (static fields)
                    if (hwTargetIds.length > 0) {
                        hwTargetIds.forEach(function(id) {
                            var el = document.getElementById(id);
                            if (el) {
                                hwAppendToElement(el, hwAccumulatedText);
                            }
                        });
                    }
                }
                var modalEl = document.getElementById('hwModal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            });
        }

        // ★ ต่อท้ายข้อความ โดยรองรับ auto-wrap multi-line group
        function hwAppendToElement(el, text) {
            var autoClass = null;
            if (el.classList) {
                for (var i = 0; i < el.classList.length; i++) {
                    if (el.classList[i].indexOf('auto-line') !== -1) {
                        autoClass = el.classList[i];
                        break;
                    }
                }
            }
            if (autoClass) {
                var scope = el.closest('.modal') || el.closest('form') || document;
                var allInputs = Array.prototype.slice.call(scope.querySelectorAll('.' + autoClass));
                if (allInputs.length > 1) {
                    var totalText = '';
                    allInputs.forEach(function(inp) {
                        totalText += inp.value || '';
                    });
                    totalText += text;
                    allInputs[0].value = totalText;
                    for (var j = 1; j < allInputs.length; j++) allInputs[j].value = '';
                    allInputs[0].dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                    return;
                }
            }
            el.value = (el.value || '') + text;
            $(el).trigger('change').trigger('input');
        }

        function hwAppendText(text) {
            hwAccumulatedText += text;
            $('#hwAccumulated').val(hwAccumulatedText);
        }
    })();

    // ★ Force set วันที่ปัจจุบันให้ทุก field "ลง" ที่ยังว่าง
    function _setDefaultDateForLongFields() {
        var today = new Date().toISOString().slice(0, 10);
        var dateFields = [
            '#document_date', '#ppf_document_date',
            '#record_date_bomb', '#bpf_record_date',
            '#fire_document_date', '#fpf_document_date',
            '#ev7_document_date', '#ev8_document_date',
            '#fpn_letter_date', '#fp_letter_date',
            '#fpn_evidence_doc_date', '#fp_evidence_doc_date',
            '[name="record_date"]',
            '[name="sevpf_document_date"]', '[name="pepf_document_date"]',
            '[name="rlfp_letter_date"]', '[name="rlfp_evidence_doc_date"]',
            '#rlfpdf_letter_date', '#rlfpdf_evidence_doc_date',
            '#rlf_letter_date', '#rlf_evidence_doc_date'
        ];
        dateFields.forEach(function(sel) {
            $(sel).each(function() {
                if (!$(this).val()) $(this).val(today);
            });
        });
    }
    $(function() {
        // หลัง page load
        setTimeout(_setDefaultDateForLongFields, 2000);
        // ทุกครั้งที่เปิด modal → set วันที่ "ลง" ที่ยังว่าง
        $(document).on('shown.bs.modal', function() {
            _setDefaultDateForLongFields();
        });
    });
</script>

<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>