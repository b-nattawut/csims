<?php
require_once __DIR__ . '/includes/session_config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require __DIR__ . '/helpers/report_no.php';

$qryData = "SELECT count(t1.id) as countData 
FROM rn_ReceiveNoti t1
LEFT JOIN users t2 ON t1.create_by = t2.user_id
WHERE t1.statusDelete = 0 AND t2.is_active = 1 AND t1.statusChecklist = 1";
$stmt = $pdo->prepare($qryData);
$stmt->execute();
$countData = $stmt->fetchColumn();

$limit = 15;
$total_pages = ceil($countData / $limit);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$title = "ร่างรายงาน - ระบบบริหารจัดการฐานข้อมูลวัตถุพยาน";

ob_start();
?>
<style>
/* Inspector row: force Select2 same height as form-control-sm (31px) */
.rp-inspector-row .select2-container--bootstrap-5 .select2-selection {
    min-height: 31px !important;
    height: 31px !important;
    padding: .25rem .5rem !important;
    font-size: .875rem !important;
}
.rp-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    padding: 0 !important;
    line-height: 1.5 !important;
    font-size: .875rem !important;
}
.rp-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__clear {
    height: auto !important;
    padding: .125rem !important;
}
.rp-inspector-row .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
    height: 100% !important;
}
.rp-inspector-row .form-control-sm {
    height: 31px !important;
}
</style>
<?php
$extra_css = ob_get_clean();
ob_start();
?>

<!-- Card ของ Filter Form -->
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
                    <div class="row g-2 justify-content-center align-items-end">

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">เลขที่เอกสาร</label>
                            <input type="text" class="form-control form-control-sm" id="filter_doc_no" name="filter_doc_no">
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">เลขที่รายงาน</label>
                            <input type="text" class="form-control form-control-sm" id="filter_report_no" name="filter_report_no">
                        </div>

                        <div class="col-md-2 col-sm-6">
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
                                $qryPoliceStation = "SELECT station_name, province_id FROM master_police_station ORDER BY id DESC";
                                $stmtPS = $pdo->query($qryPoliceStation);
                                $policeStations = $stmtPS->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <option value="" selected disabled>กรุณาเลือก</option>
                                <?php foreach ($policeStations as $ps): ?>
                                    <option value="<?= htmlspecialchars($ps['station_name'], ENT_QUOTES, 'UTF-8') ?>" data-province="<?= (int)($ps['province_id'] ?? '') ?>">
                                        <?= htmlspecialchars($ps['station_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 col-sm-6">
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

                        <div class="col-md-12 d-flex justify-content-center align-items-center gap-2 mt-4">
                            <button type="button" class="btn btn-warning btn-sm text-dark fw-bold px-3" id="btn_clear_filter">
                                <i class="fas fa-undo me-1"></i> ล้างค่า
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" id="btn_search_filter">
                                <i class="fas fa-search me-1"></i> ค้นหา
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Card ของ Table -->
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
                        <tr class="text-nowrap text-center">
                            <th style="width: 5%">ลำดับ</th>
                            <th style="width: 13%">เลขที่เอกสาร</th>
                            <th style="width: 13%">เลขที่รายงาน</th>
                            <th style="width: 15%">สภ./สน.</th>
                            <th style="width: 9%">จังหวัด</th>
                            <th style="width: 16%">เหตุที่รับแจ้ง</th>
                            <th style="width: 9%">ร่างรายงาน</th>
                            <th style="width: 9%">ร่างรายงาน</th>
                            <th class="memo-col" style="width: 11%">บันทึกข้อความ</th>
                        </tr>
                    </thead>
                    <tbody id="table_body" style="font-size: 14px;">
                        <?php
                        $qryDataTable = "SELECT t1.id, t1.create_by, t1.receiveNoti_No, t1.receiveNoti_No_TH, t1.receiveNotiReportNo, t1.receiveNotiReportNo_TH, t1.complaints_From, t1.province,
                                        t1.complaints_type,
                                        ict.incident_checklist_data,
                                        COALESCE(ict.count_report, 0) as count_report,
                                        CASE WHEN t1.complaints_type = '01' THEN 'ทรัพย์'
                                        WHEN t1.complaints_type = '02' THEN 'ชีวิต'
                                        WHEN t1.complaints_type = '03' THEN 'ระเบิด'
                                        WHEN t1.complaints_type = '04' THEN 'เพลิงไหม้'
                                        WHEN t1.complaints_type = '05' THEN 'จราจร'
                                        WHEN t1.complaints_type = '06' THEN 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)'
                                        WHEN t1.complaints_type = '07' THEN 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ'
                                        WHEN t1.complaints_type = '08' THEN 'ตรวจเก็บวัตถุพยานบุคคล'
                                        ELSE CONCAT('ไม่ทราบ (', t1.complaints_type, ')') END AS complaintstype,
                                        CASE WHEN t1.complaints_From_Device = 'r' THEN 'วิทยุสื่อสาร'
                                        WHEN t1.complaints_From_Device = 't' THEN 'ทางโทรศัพท์'
                                        ELSE 'อื่นๆ' END AS complaintsdevice
                                        FROM rn_ReceiveNoti t1
                                        LEFT JOIN users t2 ON t1.create_by = t2.user_id
                                        LEFT JOIN user_profile t3 ON t2.user_id = t3.user_id
                                        LEFT JOIN user_rank t4 ON t3.rank_id = t4.rank_id
                                        LEFT JOIN incident_checklist_transaction ict ON ict.incident_id = t1.id
                                        WHERE t1.statusDelete = 0 AND t2.is_active = 1 AND t1.statusChecklist = 1
                                        ORDER BY t1.id DESC
                                        LIMIT 15 OFFSET 0";
                        $stmt = $pdo->prepare($qryDataTable);
                        $stmt->execute();
                        if ($stmt->rowCount() > 0) {
                            $index = 1;
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                // ตรวจสอบ location_type สำหรับคดีชีวิต
                                //var_dump($row);
                                $locationType = '';
                                $complaintsDisplay = $row['complaintstype'];
                                if ($row['complaints_type'] == '02' && !empty($row['incident_checklist_data'])) {
                                    $ckData = json_decode($row['incident_checklist_data'], true);
                                    $sc = $ckData['scene_characteristics'] ?? [];
                                    if (!empty($sc['has_outdoor'])) {
                                        $locationType = 'outdoor';
                                        $complaintsDisplay = 'ชีวิต (นอกอาคาร)';
                                    } elseif (!empty($sc['has_indoor'])) {
                                        $locationType = 'indoor';
                                        $complaintsDisplay = 'ชีวิต (ในอาคาร)';
                                    }
                                }else if($row['complaints_type'] == '03' && !empty($row['incident_checklist_data'])){
                                    $ckData = json_decode($row['incident_checklist_data'], true);
                                    $sc = $ckData['scene_info'] ?? [];
                                    $indoor = $sc['indoor']['is_active'] == 1 ? 'true' : 'false';
                                    if ($indoor == 'true') {
                                        $locationType = 'indoor';
                                        $complaintsDisplay = 'ระเบิด (ในอาคาร)';
                                    } else {
                                        $locationType = 'outdoor';
                                        $complaintsDisplay = 'ระเบิด (นอกอาคาร)';
                                    }
                                }
                        ?>
<?php
                                    $countReport = intval($row['count_report'] ?? 0);
                                    $canPdf = false;
                                    if ($countReport >= 1) {
                                        if ($row['complaints_type'] == '01') {
                                            $canPdf = true;
                                        } elseif ($row['complaints_type'] == '02' && in_array($locationType, ['indoor','outdoor'])) {
                                            $canPdf = true;
                                        }elseif ($row['complaints_type'] == '03' && in_array($locationType, ['indoor','outdoor'])) {
                                            $canPdf = true;
                                        }elseif ($row['complaints_type'] == '04') {
                                            $canPdf = true;
                                        }elseif ($row['complaints_type'] == '05') {
                                            $canPdf = true;
                                        }elseif ($row['complaints_type'] == '07') {
                                            $canPdf = true;
                                        }elseif ($row['complaints_type'] == '08') {
                                            $canPdf = true;
                                        }elseif ($row['complaints_type'] == '06') {
                                            $canPdf = true;
                                        }
                                    }
                                ?>
                                <tr class="report-row" style="cursor: pointer;" data-id="<?= $row['id']; ?>" data-doc-no="<?= htmlspecialchars($row['receiveNoti_No'], ENT_QUOTES, 'UTF-8'); ?>" data-report-no="<?= htmlspecialchars($row['receiveNotiReportNo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-complaints-type="<?= $row['complaints_type']; ?>" data-location-type="<?= $locationType; ?>" data-create-by="<?= $row['create_by'] ?? ''; ?>">
                                    <td class="text-center"><?= $index++; ?></td>
                                    <td class="text-center"><?= htmlspecialchars((!empty($row['receiveNoti_No_TH']) ? $row['receiveNoti_No_TH'] : convertDocNoToThai($row['receiveNoti_No'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-center"><?= htmlspecialchars((!empty($row['receiveNotiReportNo_TH']) ? $row['receiveNotiReportNo_TH'] : convertReportNoToThai($row['receiveNotiReportNo'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td style="white-space: nowrap;"><?= htmlspecialchars($row['complaints_From'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['province'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($complaintsDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-center pdf-cell">
                                        <?php if ($canPdf): ?>
                                            <button type="button" class="btn btn-sm btn-draft-pdf" style="background:#7c3aed; box-shadow:0 2px 6px rgba(124,58,237,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-pdf-id="<?= $row['id']; ?>" data-pdf-type="<?= $row['complaints_type']; ?>" data-pdf-location="<?= $locationType; ?>" title="ดาวน์โหลด PDF">
                                                <i class="fas fa-file-pdf me-1"></i>PDF
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-secondary btn-no-action" style="background:#9ca3af; box-shadow:none; color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; cursor:not-allowed; opacity:.55;" disabled title="ยังไม่มีข้อมูล">
                                                <i class="fas fa-file-pdf me-1"></i>PDF
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center doc-cell">
                                        <?php if ($canPdf): ?>
                                            <button type="button" class="btn btn-sm btn-draft-doc" style="background:#2563eb; box-shadow:0 2px 6px rgba(37,99,235,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-doc-id="<?= $row['id']; ?>" data-doc-type="<?= $row['complaints_type']; ?>" data-doc-location="<?= $locationType; ?>" title="ดาวน์โหลดร่างรายงาน Word (เนื้อหาและรูปในไฟล์เดียวกัน เลขหน้านับรวมทั้งหมด)">
                                                <i class="fas fa-file-word me-1"></i>DOCX
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-secondary btn-no-action" style="background:#9ca3af; box-shadow:none; color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; cursor:not-allowed; opacity:.55;" disabled title="ยังไม่มีข้อมูล">
                                                <i class="fas fa-file-word me-1"></i>DOCX
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center memo-cell">
                                        <?php if ($canPdf): ?>
                                            <button type="button" class="btn btn-sm btn-draft-memo" style="background:#0891b2; box-shadow:0 2px 6px rgba(8,145,178,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-memo-id="<?= $row['id']; ?>" data-memo-type="<?= $row['complaints_type']; ?>" data-memo-location="<?= $locationType; ?>" title="ดาวน์โหลดบันทึกข้อความ (Word)">
                                                <i class="fas fa-file-word me-1"></i>บันทึกข้อความ
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-secondary btn-no-action" style="background:#9ca3af; box-shadow:none; color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; cursor:not-allowed; opacity:.55;" disabled title="ยังไม่มีข้อมูล">
                                                <i class="fas fa-file-word me-1"></i>บันทึกข้อความ
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                        <?php
                            }
                        } else {
                        ?>
                            <tr><td colspan="9" class="text-center">ไม่พบข้อมูล</td></tr>
                        <?php } ?>
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
$content = ob_get_clean();
ob_start();
?>

<!-- ★ Modal includes ย้ายมาไว้หลัง jQuery เพื่อให้ inline script ใช้ $ ได้ -->
<!-- Modal: ร่างรายงานคดีชีวิต (ในอาคาร) -->
<?php include 'modals/modal_report_life_indoor.php'; ?>
<!-- Modal: ร่างรายงานคดีชีวิต ในอาคาร (ฟอร์มเสมือน PDF) -->
<?php include 'modals/modal_life_report_indoor_pdf_form.php'; ?>
<!-- Modal: ร่างรายงานคดีชีวิต (นอกอาคาร) -->
<?php include 'modals/modal_report_life_outdoor.php'; ?>
<!-- Modal: ร่างรายงานคดีชีวิต นอกอาคาร (ฟอร์มเสมือน PDF) -->
<?php include 'modals/modal_life_report_outdoor_pdf_form.php'; ?>
<!-- Modal: ร่างรายงานคดีทรัพย์ -->
<?php include 'modals/modal_report_property.php'; ?>
<!-- Modal: ร่างรายงานคดีทรัพย์ (ฟอร์มเสมือน PDF) -->
<?php include 'modals/modal_property_report_pdf_form.php'; ?>
<!-- Modal: ร่างรายงานคดีระเบิด (ในอาคาร) -->
<?php include 'modals/modal_report_bomb_indoor.php'; ?>
<!-- Modal: ร่างรายงานคดีระเบิด (ในอาคาร) — ฟอร์มเสมือน PDF -->
<?php include 'modals/modal_bomb_report_indoor_pdf_form.php'; ?>
<!-- Modal: ร่างรายงานคดีระเบิด (นอกอาคาร) -->
<?php include 'modals/modal_report_bomb_outdoor.php'; ?>
<!-- Modal: ร่างรายงานคดีระเบิด (นอกอาคาร) — ฟอร์มเสมือน PDF -->
<?php include 'modals/modal_bomb_report_outdoor_pdf_form.php'; ?>
<!-- Modal: ร่างรายงานคดีเพลิงไหม้ -->
<?php include 'modals/modal_report_fire.php'; ?>
<!-- Modal: ร่างรายงานคดีเพลิงไหม้ (ฟอร์มเสมือน PDF) -->
<?php include 'modals/modal_fire_report_pdf_form.php'; ?>
<!-- Modal: ร่างรายงานคดีจราจร -->
<?php include 'modals/modal_report_traffic.php'; ?>
<!-- Modal: ร่างรายงานคดีจราจร (ฟอร์มเสมือน PDF) -->
<?php include 'modals/modal_traffic_report_pdf_form.php'; ?>
<!-- Modal: ร่างรายงานตรวจเก็บวัตถุพยาน -->
<?php include 'modals/modal_scene_evidence.php'; ?>
<!-- Modal: ร่างรายงานตรวจเก็บวัตถุพยาน (ฟอร์มเสมือน PDF) -->
<?php include 'modals/modal_scene_evidence_pdf_form_report.php'; ?>
<!-- Modal: ร่างรายงานตรวจเก็บวัตถุพยานบุคคล -->
<?php include 'modals/modal_report_person_evidence.php'; ?>
<!-- Modal: ร่างรายงานตรวจเก็บวัตถุพยานบุคคล (ฟอร์มเสมือน PDF) -->
<?php include 'modals/modal_report_person_evidence_pdf_form.php'; ?>
<!-- Modal: ร่างรายงานตรวจเก็บวัตถุพยาน (ลายนิ้วมือแฝง) -->
<?php include 'modals/modal_report_fingerprint.php'; ?>
<?php include 'modals/modal_fingerprint_report_pdf_form.php'; ?>

<script src="js/report_no_th.js"></script>
<script>
// ★ Session user_id สำหรับเช็คสิทธิ์ปุ่ม save ใน modal
window._sessionUserId = '<?= $_SESSION["user_id"] ?>';

function thaiDocNo(s) {
    return (window.toThaiDocNo ? window.toThaiDocNo(String(s || '')) : String(s || '')).trim();
}
function thaiReportNo(s) {
    return (window.toThaiReportNo ? window.toThaiReportNo(String(s || '')) : String(s || '')).trim();
}
function smartThaiReportOrDoc(s) {
    s = String(s || '').trim();
    if (!s) return '';
    if (/^\d{1,3}-\d{1,3}-\d{2}-/.test(s)) {
        return thaiDocNo(s);
    }
    return thaiReportNo(s);
}
/** ตั้งค่าเลขที่เอกสารในช่องแสดงผล modal ร่างรายงาน */
function setDraftDocNoDisplay(selectors, docNo) {
    var th = thaiDocNo(docNo);
    $(selectors).val(th).text(th);
}
/** คืนค่าเลขที่เอกสารไทยในช่อง PDF หลัง sync จากฟอร์มมาตรฐาน */
function restoreDraftPdfReportNoFromDocDisplay(docDisplayId, pdfIds) {
    var dn = $(docDisplayId).text();
    if (!dn) return;
    var th = smartThaiReportOrDoc(dn);
    (pdfIds || []).forEach(function(id) { $(id).val(th); });
}
function normalizeDraftReportNoDisplays($root) {
    ($root || $(document)).find('[id*="report_no_display"], [id*="report_ref"], [id$="_doc_no_display"], .sevpf-rpt-no-mirror, .pepf-rpt-no-mirror').each(function() {
        var el = this;
        if (el.type === 'hidden') return;
        var editable = el.tagName === 'INPUT' || el.tagName === 'TEXTAREA';
        var cur = editable ? el.value : $(el).text();
        if (!cur) return;
        var th = smartThaiReportOrDoc(cur);
        if (editable) el.value = th; else $(el).text(th);
    });
}

$(document).ready(function() {

    // --- เลือกจังหวัด → กรอง สภ./สน. ---
    $('#filter_province').on('change', function() {
        var provId = $(this).val();
        $('#filter_station option').each(function() {
            var prov = $(this).data('province');
            if (!prov) { $(this).show(); return; } // option "กรุณาเลือก"
            $(this).toggle(String(prov) === String(provId));
        });
        $('#filter_station').val(''); // reset ค่าที่เลือกไว้
    });

    // --- Initial Pagination ---
    const totalPages = <?= $total_pages ?>;
    if (totalPages > 0) {
        setupPagination(totalPages, 1);
    }

    // --- Search Form Submit ---
    $('#searchFilterForm').on('submit', function(e) {
        e.preventDefault();
        searchPage(1);
    });

    // --- Clear Filter ---
    $('#btn_clear_filter').on('click', function() {
        const $form = $('#searchFilterForm');
        $form[0].reset();
        $form.find('select').val('').trigger('change');
        searchPage(1);
    });

    // --- AJAX Search ---
    function searchPage(page) {
        const formData = $('#searchFilterForm').serializeArray();
        formData.push({ name: 'page', value: page });

        $.ajax({
            url: '/csims/api/incidentCheckList/searchDataReport.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status == 'success') {
                    renderTable(response.data, response.offset);
                    $('#count_display').text(response.count);
                    const total = parseInt(response.totalPages);
                    const current = parseInt(response.currentPage);
                    if (total > 0) {
                        setupPagination(total, current);
                    } else {
                        $('#pagination-list').twbsPagination('destroy');
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
                let complaintsDisplay = row.complaintstype;
                let locationType = row.location_type || '';
                let countReport = parseInt(row.count_report) || 0;
                let canPdf = false;
                if (countReport >= 1) {
                    if (row.complaints_type == '01') {
                        canPdf = true;
                    } else if (row.complaints_type == '02' && (locationType === 'indoor' || locationType === 'outdoor')) {
                        canPdf = true;
                    } else if (row.complaints_type == '03' && locationType === 'indoor') {
                        canPdf = true;
                    } else if (row.complaints_type == '04') {
                        canPdf = true;
                    } else if (row.complaints_type == '05') {
                        canPdf = true;
                    } else if (row.complaints_type == '07') {
                        canPdf = true;
                    } else if (row.complaints_type == '08') {
                        canPdf = true;
                    } else if (row.complaints_type == '06') {
                        canPdf = true;
                    }
                }

                let pdfButton = '';
                let docButton = '';
                let memoButton = '';
                if (canPdf) {
                    pdfButton = `<button type="button" class="btn btn-sm btn-draft-pdf" style="background:#7c3aed; box-shadow:0 2px 6px rgba(124,58,237,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-pdf-id="${row.id}" data-pdf-type="${row.complaints_type}" data-pdf-location="${locationType}" title="ดาวน์โหลด PDF">
                        <i class="fas fa-file-pdf me-1"></i>PDF
                    </button>`;
                    docButton = `<button type="button" class="btn btn-sm btn-draft-doc" style="background:#2563eb; box-shadow:0 2px 6px rgba(37,99,235,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-doc-id="${row.id}" data-doc-type="${row.complaints_type}" data-doc-location="${locationType}" title="ดาวน์โหลดร่างรายงาน Word (เนื้อหาและรูปในไฟล์เดียวกัน เลขหน้านับรวมทั้งหมด)">
                        <i class="fas fa-file-word me-1"></i>DOCX
                    </button>`;
                    memoButton = `<button type="button" class="btn btn-sm btn-draft-memo" style="background:#0891b2; box-shadow:0 2px 6px rgba(8,145,178,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-memo-id="${row.id}" data-memo-type="${row.complaints_type}" data-memo-location="${locationType}" title="ดาวน์โหลดบันทึกข้อความ (Word)">
                        <i class="fas fa-file-word me-1"></i>บันทึกข้อความ
                    </button>`;
                } else {
                    pdfButton = `<button type="button" class="btn btn-sm btn-secondary btn-no-action" style="background:#9ca3af; box-shadow:none; color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; cursor:not-allowed; opacity:.55;" disabled title="ยังไม่มีข้อมูล">
                        <i class="fas fa-file-pdf me-1"></i>PDF
                    </button>`;
                    docButton = `<button type="button" class="btn btn-sm btn-secondary btn-no-action" style="background:#9ca3af; box-shadow:none; color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; cursor:not-allowed; opacity:.55;" disabled title="ยังไม่มีข้อมูล">
                        <i class="fas fa-file-word me-1"></i>DOCX
                    </button>`;
                    memoButton = `<button type="button" class="btn btn-sm btn-secondary btn-no-action" style="background:#9ca3af; box-shadow:none; color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; cursor:not-allowed; opacity:.55;" disabled title="ยังไม่มีข้อมูล">
                        <i class="fas fa-file-word me-1"></i>บันทึกข้อความ
                    </button>`;
                }

                html += `
                    <tr class="report-row" style="cursor: pointer;" data-id="${row.id}" data-doc-no="${row.receiveNoti_No}" data-report-no="${row.receiveNotiReportNo || ''}" data-complaints-type="${row.complaints_type}" data-location-type="${locationType}" data-create-by="${row.create_by || ''}">
                        <td class="text-center">${index}</td>
                        <td class="text-center">${row.receiveNoti_No_TH || (window.toThaiDocNo ? toThaiDocNo(row.receiveNoti_No) : row.receiveNoti_No) || ''}</td>
                        <td class="text-center">${row.receiveNotiReportNo_TH || (window.toThaiReportNo ? toThaiReportNo(row.receiveNotiReportNo) : row.receiveNotiReportNo) || ''}</td>
                        <td style="white-space: nowrap;">${row.complaints_From}</td>
                        <td class="text-center">${row.province}</td>
                        <td>${complaintsDisplay}</td>
                        <td class="text-center pdf-cell">${pdfButton}</td>
                        <td class="text-center doc-cell">${docButton}</td>
                        <td class="text-center memo-cell">${memoButton}</td>
                    </tr>
                `;
                index++;
            });
        } else {
            html = '<tr><td colspan="9" class="text-center">ไม่พบข้อมูล</td></tr>';
        }
        $('#table_body').html(html);
    }

    // --- Row Click => Open Modal ---
    $(document).on('click', '.report-row', function(e) {
        // ถ้าคลิกที่ปุ่ม PDF ไม่ต้องเปิด modal
        if ($(e.target).closest('.btn-draft-pdf').length || $(e.target).closest('.btn-draft-doc').length || $(e.target).closest('.btn-draft-memo').length || $(e.target).closest('.btn-no-action').length) {
            return;
        }

        const $row = $(this);
        const id = $row.data('id');
        const docNo = $row.data('doc-no');
        const reportNo = $row.data('report-no') || '';
        const complaintsType = $row.data('complaints-type');
        const locationType = $row.data('location-type');

        // ★ เช็คสิทธิ์: create_by ตรงกับ session user_id หรือไม่
        const createBy = String($row.data('create-by') || '');
        const isOwner = (createBy === '' || createBy === String(window._sessionUserId));
        window._currentIsOwner = isOwner;

        // ชีวิต (02) => เลือกในอาคาร / นอกอาคาร อัตโนมัติจาก checklist data
        if (complaintsType == '01') {
            // ทรัพย์
            openPropertyReportModal(id, docNo, reportNo);
        } else if (complaintsType == '02') {
            if (locationType === 'indoor') {
                openLifeReportModal('indoor', id, docNo, reportNo);
            } else if (locationType === 'outdoor') {
                openLifeReportModal('outdoor', id, docNo, reportNo);
            } else {
                // ไม่มีข้อมูล location => ให้เลือก
                Swal.fire({
                    title: 'เลือกประเภทสถานที่',
                    text: 'ไม่พบข้อมูลประเภทสถานที่จาก Checklist กรุณาเลือก',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#0d6efd',
                    confirmButtonText: 'ในอาคาร',
                    cancelButtonText: 'นอกอาคาร',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        openLifeReportModal('indoor', id, docNo, reportNo);
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        openLifeReportModal('outdoor', id, docNo, reportNo);
                    }
                });
            }
        }
        else if (complaintsType == '03') {
            if (locationType === 'indoor') {
                openBombReportModal('indoor', id, docNo, reportNo);
            } else if (locationType === 'outdoor') {
                openBombReportModal('outdoor', id, docNo, reportNo);
            } else {
                Swal.fire({
                    icon: 'question',
                    title: 'เลือกประเภทสถานที่',
                    text: 'กรุณาเลือกประเภทการตรวจสถานที่เกิดเหตุ',
                    showCancelButton: true,
                    confirmButtonColor: '#6f42c1',
                    cancelButtonColor: '#0d6efd',
                    confirmButtonText: 'ในอาคาร',
                    cancelButtonText: 'นอกอาคาร',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        openBombReportModal('indoor', id, docNo, reportNo);
                    } else if (result.dismiss === Swal.DismissReason.cancel) {
                        openBombReportModal('outdoor', id, docNo, reportNo);
                    }
                });
            }
        }
        else if (complaintsType == '04') {
            openFireReportModal(id, docNo, reportNo);
        }
        else if (complaintsType == '05') {
            openTrafficReportModal(id, docNo, reportNo);
        }
        else if (complaintsType == '07') {
            openSceneEvidenceReportModal(id, docNo, reportNo);
        }
        else if (complaintsType == '08') {
            openPersonEvidenceReportModal(id, docNo, reportNo);
        }
        else if (complaintsType == '06') {
            openFingerprintReportModal(id, docNo, reportNo);
        }
        else {
            Swal.fire({
                icon: 'info',
                title: 'ยังไม่รองรับ',
                text: 'ขณะนี้รองรับเฉพาะคดีชีวิต/ทรัพย์/ระเบิด/เพลิงไหม้/จราจร/วัตถุพยานเท่านั้น',
                confirmButtonColor: '#6f42c1'
            });
        }

        // ★ Toggle ปุ่มบันทึกตาม permission
        setTimeout(function() {
            var saveBtns = [
                '#btn_save_report_property', '#btn_save_report_property_pdf',
                '#btn_save_report_life_indoor', '#btn_save_report_life_indoor_pdf',
                '#btn_save_report_life_outdoor', '#btn_save_report_life_outdoor_pdf',
                '#btn_save_report_bomb_indoor', '#btn_save_report_bomb_indoor_pdf',
                '#btn_save_report_bomb_outdoor', '#btn_save_report_bomb_outdoor_pdf',
                '#btn_save_report_fire', '#btn_save_report_fire_pdf',
                '#btn_save_report_traffic', '#btn_save_report_traffic_pdf',
                '#btn_save_report_person_evidence', '#btn_save_report_person_evidence_pdf',
                '#btn_save_report_fingerprint', '#btn_save_report_fingerprint_pdf',
                '#btn_save_ev7_pdf'
            ];
            saveBtns.forEach(function(sel) {
                var btn = document.querySelector(sel);
                if (btn) {
                    btn.disabled = !isOwner;
                    btn.title = isOwner ? '' : 'คุณไม่มีสิทธิ์แก้ไข (ไม่ใช่ผู้สร้างรายการ)';
                }
            });
        }, 300);
    });

    // --- Helper: Parse reportNo (e.g. "LC-0006/2569") and set display in modal ---
    function setReportNoDisplay(reportNo, noSelector, yearSelector) {
        var rptParts = (reportNo || '').split('/');
        var rptNum = smartThaiReportOrDoc(rptParts[0] || '');
        var rptYear = (rptParts[1] || '').toString().slice(-2);
        $(noSelector).val(rptNum).text(rptNum);
        $(yearSelector).val(rptYear).text(rptYear);
    }

    // --- Open Life Report Modal & Load Data ---
    function openLifeReportModal(type, incidentId, docNo, reportNo) {
        const prefix = type === 'indoor' ? 'rli' : 'rlo';
        const modalId = type === 'indoor' ? 'modalReportLifeIndoor' : 'modalReportLifeOutdoor';
        const formId = type === 'indoor' ? 'formReportLifeIndoor' : 'formReportLifeOutdoor';

        // Reset form & edit info
        document.getElementById(formId).reset();
        $(`#${prefix}_incident_id`).val(incidentId);
        $(`#${prefix}_doc_no_display`).text(thaiDocNo(docNo));

        // Set report_no in PDF modal header (ใช้เลขที่เอกสารเต็ม)
        var pdfPrefix = type === 'indoor' ? 'rlipdf' : 'rlopdf';
        var currentYear = '<?= substr((date('Y') + 543), -2) ?>';
        $(`#${pdfPrefix}_report_no_display`).val(thaiDocNo(docNo));
        $(`#${pdfPrefix}_report_year`).val(currentYear);

        $(`#${prefix}_editInfo`).addClass('d-none');
        $(`#${prefix}_editCount`).text('0');
        $(`#${prefix}_editDate`).text('-');

        // Reset inspector rows inside THIS modal only
        const $modal = $(`#${modalId}`);
        const $container = $modal.find(`#${prefix}_inspector_container`);
        $container.find('.rp-inspector-row').not(':first').remove();
        if ($container.find('.rp-inspector-row').length === 0) {
            $container.append(rpBuildInspectorRow('5.1', '', '', prefix));
        }
        $container.find('.rp-inspector-select').prop('selectedIndex', 0);
        $container.find('.rp-inspector-position').val('');

        // Load users for inspector dropdowns then load data
        rpLoadUsers(function() {
            rpPopulateAllInspectorSelects();
            rpInitSelect2InModal($modal);

            // Load data from API
            $.ajax({
                url: '/csims/api/incidentCheckList/getLifeReportData.php',
                type: 'GET',
                data: { incident_id: incidentId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data && response.data[type]) {
                        // Add extra inspector rows if needed
                        const inspectorNames = response.data[type][prefix + '_inspector_name[]'];
                        if (Array.isArray(inspectorNames) && inspectorNames.length > 1) {
                            for (let i = 1; i < inspectorNames.length; i++) {
                                const count = $container.find('.rp-inspector-row').length + 1;
                                $container.append(rpBuildInspectorRow('5.' + count, '', '', prefix));
                            }
                        }
                        prefillReportForm(prefix, response.data[type]);
                        rpInitSelect2InModal($modal);
                    }
                    // แสดงจำนวนครั้งที่แก้ไข
                    // console.log(response.data);
                    var rli_agency_name_in = response.data.indoor.rli_agency_name || '';
                    var rli_agency_name_out = response.data.outdoor.rlo_agency_name || '';
                    // console.log(rli_agency_name_in,"<------------rli_agency_name_in");
                    // console.log(rli_agency_name_out,"<------------rli_agency_name_out");
                    const countEdit = response.count_report || 0;
                    if (countEdit > 0) {
                        $(`#${prefix}_editInfo`).removeClass('d-none');
                        $(`#${prefix}_editCount`).text(countEdit);
                        $(`#${prefix}_editDate`).text(response.edit_date || '-');
                    }

                    // For outdoor: sync data to PDF form and open PDF modal
                    if (type === 'outdoor') {
                        syncLifeOutdoorReportInspectorRows('toPdf');
                        syncLifeOutdoorReportFormData('formReportLifeOutdoor', 'formReportLifeOutdoorPdf');
                        $('#rlopdf_incident_id').val(incidentId);
                        // Restore docNo after sync
                        $('#rlopdf_report_no_display').val(thaiDocNo(docNo));
                        $('#rlopdf_report_no_display_2').val(thaiDocNo(docNo));
                        $('#rlopdf_agency_name').val(rli_agency_name_out);
                        $('#rlopdf_agency_name_p2').val(rli_agency_name_out);
                        $('#rlopdf_report_year').val(currentYear);
                        const pdfModal = new bootstrap.Modal(document.getElementById('modalReportLifeOutdoorPdf'));
                        pdfModal.show();
                    }

                    // For indoor: sync data to PDF form and open PDF modal
                    if (type === 'indoor') {
                        syncLifeIndoorReportInspectorRows('toPdf');
                        syncLifeIndoorReportFormData('formReportLifeIndoor', 'formReportLifeIndoorPdf');
                        syncLifeIndoorReportSpecialFields('toPdf');
                        $('#rlipdf_incident_id').val(incidentId);
                        // Restore docNo after sync
                        $('#rlipdf_report_no_display').val(thaiDocNo(docNo));
                        $('#rlipdf_report_no_display_2').val(thaiDocNo(docNo));
                        $('#rlipdf_report_no_display_3').val(thaiDocNo(docNo));
                        $('#rlipdf_report_no_display_4').val(thaiDocNo(docNo));
                        $('#rlipdf_agency_name_p2').val(rli_agency_name_in);
                        $('#rlipdf_agency_name_p3').val(rli_agency_name_in);
                        $('#rlipdf_agency_name_p4').val(rli_agency_name_in);
                        
                        $('#rlipdf_report_year').val(currentYear);
                        const pdfModalIndoor = new bootstrap.Modal(document.getElementById('modalReportLifeIndoorPdf'));
                        pdfModalIndoor.show();
                    }
                },
                error: function() {
                    // No data yet — open PDF modal with empty form
                    if (type === 'outdoor') {
                        $('#rlopdf_incident_id').val(incidentId);
                        const pdfModal = new bootstrap.Modal(document.getElementById('modalReportLifeOutdoorPdf'));
                        pdfModal.show();
                        return;
                    }
                    if (type === 'indoor') {
                        $('#rlipdf_incident_id').val(incidentId);
                        const pdfModalIndoor = new bootstrap.Modal(document.getElementById('modalReportLifeIndoorPdf'));
                        pdfModalIndoor.show();
                        return;
                    }
                }
            });
        });

        // Open modal — both indoor and outdoor now use PDF by default
    }

    // --- Open Bomb Report Modal & Load Data ---
    function openBombReportModal(type, incidentId, docNo, reportNo) {
        const prefix = type === 'outdoor' ? 'rbo' : 'rbi';
        const modalId = type === 'outdoor' ? 'modalReportBombOutdoor' : 'modalReportBombIndoor';
        const formId = type === 'outdoor' ? 'formReportBombOutdoor' : 'formReportBombIndoor';

        // Reset form & edit info
        document.getElementById(formId).reset();
        $(`#${prefix}_incident_id`).val(incidentId);
        $(`#${prefix}_doc_no_display`).text(thaiDocNo(docNo));

        // Set report_no in PDF modal header (ใช้เลขที่เอกสารเต็ม)
        var pdfPrefix = type === 'outdoor' ? 'rbopdf' : 'rbipdf';
        var currentYear = '<?= substr((date('Y') + 543), -2) ?>';
        $(`#${pdfPrefix}_report_no_display`).val(thaiDocNo(docNo));
        $(`#${pdfPrefix}_report_year`).val(currentYear);

        $(`#${prefix}_editInfo`).addClass('d-none');
        $(`#${prefix}_editCount`).text('0');
        $(`#${prefix}_editDate`).text('-');

        // Reset inspector rows inside THIS modal only
        const $modal = $(`#${modalId}`);
        const $container = $modal.find(`#${prefix}_inspector_container`);
        $container.find('.rp-inspector-row').not(':first').remove();
        if ($container.find('.rp-inspector-row').length === 0) {
            $container.append(rpBuildInspectorRow('5.1', '', '', prefix));
        }
        $container.find('.rp-inspector-select').prop('selectedIndex', 0);
        $container.find('.rp-inspector-position').val('');

        // Load users for inspector dropdowns then load data
        rpLoadUsers(function() {
            rpPopulateAllInspectorSelects();
            rpInitSelect2InModal($modal);

            // Load data from API
            $.ajax({
                url: '/csims/api/incidentCheckList/getBombReportData.php',
                type: 'GET',
                data: { incident_id: incidentId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data && response.data[type]) {
                        // Add extra inspector rows if needed
                        const inspectorNames = response.data[type][prefix + '_inspector_name[]'];
                        if (Array.isArray(inspectorNames) && inspectorNames.length > 1) {
                            for (let i = 1; i < inspectorNames.length; i++) {
                                const count = $container.find('.rp-inspector-row').length + 1;
                                $container.append(rpBuildInspectorRow('5.' + count, '', '', prefix));
                            }
                        }
                        prefillReportForm(prefix, response.data[type]);
                        rpInitSelect2InModal($modal);
                    }
                    // แสดงจำนวนครั้งที่แก้ไข
                    const countEdit = response.count_report || 0;
                    if (countEdit > 0) {
                        $(`#${prefix}_editInfo`).removeClass('d-none');
                        $(`#${prefix}_editCount`).text(countEdit);
                        $(`#${prefix}_editDate`).text(response.edit_date || '-');
                    }

                    // For indoor: sync data to PDF form and open PDF modal
                    if (type === 'indoor') {
                        syncBombIndoorReportInspectorRows('toPdf');
                        syncBombIndoorReportFormData('formReportBombIndoor', 'formReportBombIndoorPdf');
                        syncBombIndoorReportSpecialFields('toPdf');
                        var agVal = $('#rbipdf_agency_name').val(); $('#rbipdf_agency_name_p2').val(agVal); $('#rbipdf_agency_name_p3').val(agVal);
                        $('#rbipdf_incident_id').val(incidentId);
                        // Restore docNo after sync
                        $('#rbipdf_report_no_display').val(thaiDocNo(docNo));
                        $('#rbipdf_report_no_display_2').val(thaiDocNo(docNo));
                        $('#rbipdf_report_no_display_3').val(thaiDocNo(docNo));
                        $('#rbipdf_report_year').val(currentYear);
                        const pdfModal = new bootstrap.Modal(document.getElementById('modalReportBombIndoorPdf'));
                        pdfModal.show();
                    } else if (type === 'outdoor') {
                        syncBombOutdoorReportInspectorRows('toPdf');
                        syncBombOutdoorReportFormData('formReportBombOutdoor', 'formReportBombOutdoorPdf');
                        var agValO = $('#rbopdf_agency_name').val(); $('#rbopdf_agency_name_p2').val(agValO); $('#rbopdf_agency_name_p3').val(agValO);
                        $('#rbopdf_incident_id').val(incidentId);
                        // Restore docNo after sync
                        $('#rbopdf_report_no_display').val(thaiDocNo(docNo));
                        $('#rbopdf_report_no_display_2').val(thaiDocNo(docNo));
                        $('#rbopdf_report_no_display_3').val(thaiDocNo(docNo));
                        $('#rbopdf_report_year').val(currentYear);
                        const pdfModalO = new bootstrap.Modal(document.getElementById('modalReportBombOutdoorPdf'));
                        pdfModalO.show();
                    }
                },
                error: function() {
                    // No data yet — open modal with empty form
                    if (type === 'indoor') {
                        $('#rbipdf_incident_id').val(incidentId);
                        const pdfModal = new bootstrap.Modal(document.getElementById('modalReportBombIndoorPdf'));
                        pdfModal.show();
                    } else {
                        $('#rbopdf_incident_id').val(incidentId);
                        const pdfModalO = new bootstrap.Modal(document.getElementById('modalReportBombOutdoorPdf'));
                        pdfModalO.show();
                    }
                }
            });
        });
    }

    // --- Prefill Report Form ---
    function prefillReportForm(prefix, data, $scope) {
        if (!data) return;
        // Scope to the correct form/modal to avoid cross-modal conflicts
        if (!$scope) {
                const formMap = { 'rli': '#formReportLifeIndoor', 'rlo': '#formReportLifeOutdoor', 'rp': '#formReportProperty', 'rbi': '#formReportBombIndoor', 'rbo': '#formReportBombOutdoor', 'rf': '#formReportFire', 'rt': '#formReportTraffic', 'pe': '#formReportPersonEvidence', 'rlf': '#formReportFingerprint' };
            $scope = $(formMap[prefix] || document);
        }

        Object.keys(data).forEach(function(key) {
            const val = data[key];
            const $field = $scope.find(`[name="${key}"]`);

            if ($field.length === 0) return;

            if ($field.is(':radio')) {
                $scope.find(`[name="${key}"][value="${val}"]`).prop('checked', true);
            } else if ($field.is(':checkbox')) {
                if (Array.isArray(val)) {
                    val.forEach(function(v) {
                        $scope.find(`[name="${key}"][value="${v}"]`).prop('checked', true);
                    });
                } else {
                    $scope.find(`[name="${key}"][value="${val}"]`).prop('checked', true);
                }
            } else if ($field.length > 1) {
                // Array fields like inspector_name[]
                if (Array.isArray(val)) {
                    $field.each(function(i) {
                        if (val[i] !== undefined) $(this).val(val[i]);
                    });
                }
            } else {
                // Single field — but val might be array (e.g., first element of array field)
                if (Array.isArray(val)) {
                    $field.val(val[0] !== undefined ? val[0] : '');
                } else {
                    $field.val(val);
                }
            }
        });
        // แปลงช่องเลขที่เอกสาร/รายงานที่แสดงผลให้เป็นไทย (modal ร่างรายงาน)
        $scope.find('[id*="report_no_display"], [id*="report_ref"], [id$="_doc_no_display"]').each(function() {
            var el = this;
            var editable = el.tagName === 'INPUT' || el.tagName === 'TEXTAREA';
            var cur = editable ? el.value : $(el).text();
            if (!cur) return;
            var th = smartThaiReportOrDoc(cur);
            if (editable) el.value = th; else $(el).text(th);
        });
    }
    window.prefillReportForm = prefillReportForm;

    // --- Save Report Life (Indoor) ---
    $('#btn_save_report_life_indoor').on('click', function() {
        saveLifeReport('indoor', 'formReportLifeIndoor', 'rli');
    });

    // --- Save Report Life (Outdoor) ---
    $('#btn_save_report_life_outdoor').on('click', function() {
        saveLifeReport('outdoor', 'formReportLifeOutdoor', 'rlo');
    });

    // --- Save Report Bomb Indoor ---
    $('#btn_save_report_bomb_indoor').on('click', function() {
        saveBombReport('indoor', 'formReportBombIndoor', 'rbi');
    });

    // --- Save Report Bomb Outdoor ---
    $('#btn_save_report_bomb_outdoor').on('click', function() {
        saveBombReport('outdoor', 'formReportBombOutdoor', 'rbo');
    });

    function saveBombReport(reportType, formId, prefix) {
        const incidentId = $(`#${prefix}_incident_id`).val();
        if (!incidentId) {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
            return;
        }

        const formData = {};
        const formEl = document.getElementById(formId);
        const elements = formEl.elements;

        for (let i = 0; i < elements.length; i++) {
            const el = elements[i];
            if (!el.name || el.name === 'incident_id') continue;

            if (el.type === 'radio') {
                if (el.checked) formData[el.name] = el.value;
            } else if (el.type === 'checkbox') {
                if (!formData[el.name]) formData[el.name] = [];
                if (el.checked) formData[el.name].push(el.value);
            } else {
                if (el.name.endsWith('[]')) {
                    if (!formData[el.name]) formData[el.name] = [];
                    formData[el.name].push(el.value);
                } else {
                    formData[el.name] = el.value;
                }
            }
        }

        const payload = {
            incident_id: incidentId,
            report_type: reportType,
            form_data: formData
        };

        $.ajax({
            url: '/csims/api/incidentCheckList/saveBombReport.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            beforeSend: function() {
                $(`#btn_save_report_bomb_${reportType}`).prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    let currentCount = parseInt($(`#${prefix}_editCount`).text()) || 0;
                    currentCount++;
                    $(`#${prefix}_editCount`).text(currentCount);
                    $(`#${prefix}_editDate`).text(new Date().toLocaleString('th-TH'));
                    $(`#${prefix}_editInfo`).removeClass('d-none');

                    const $pdfCell = $(`.report-row[data-id="${incidentId}"]`).find('.pdf-cell');
                    const loc = $(`.report-row[data-id="${incidentId}"]`).data('location-type') || reportType;
                    $pdfCell.html(`<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="${incidentId}" data-pdf-type="03" data-pdf-location="${loc}" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>`);

                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: response.message,
                        confirmButtonColor: '#0d6efd'
                    }).then(() => {
                        const mId = reportType === 'outdoor' ? 'modalReportBombOutdoor' : 'modalReportBombIndoor';
                        bootstrap.Modal.getInstance(document.getElementById(mId))?.hide();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
            },
            complete: function() {
                $(`#btn_save_report_bomb_${reportType}`).prop('disabled', false);
            }
        });
    }

// ========================================
// Bomb Indoor Report: Switch between Standard ↔ PDF Form
// (Global scope — called from inline onchange handlers)
// ========================================

function syncBombIndoorReportFormData(fromFormId, toFormId) {
    var fromForm = document.getElementById(fromFormId);
    var toForm = document.getElementById(toFormId);
    if (!fromForm || !toForm) return;

    var fromEls = fromForm.querySelectorAll('input, select, textarea');
    var dataMap = {};

    fromEls.forEach(function(el) {
        var name = el.name;
        if (!name || name === 'incident_id') return;
        if (el.type === 'file') return;

        if (el.type === 'checkbox') {
            if (!dataMap[name]) dataMap[name] = { type: 'checkbox', values: [] };
            if (el.checked) dataMap[name].values.push(el.value);
        } else if (el.type === 'radio') {
            if (el.checked) dataMap[name] = { type: 'radio', value: el.value };
        } else {
            if (!dataMap[name]) dataMap[name] = { type: 'value', values: [] };
            dataMap[name].values.push(el.value);
        }
    });

    Object.keys(dataMap).forEach(function(name) {
        var info = dataMap[name];
        var toEls = toForm.querySelectorAll('[name="' + name + '"]');
        if (toEls.length === 0) return;

        if (info.type === 'checkbox') {
            toEls.forEach(function(el) { el.checked = info.values.includes(el.value); });
        } else if (info.type === 'radio') {
            toEls.forEach(function(el) { el.checked = (el.value === info.value); });
        } else {
            toEls.forEach(function(el, idx) {
                if (idx < info.values.length) el.value = info.values[idx];
            });
        }
    });
}

function syncBombIndoorReportSpecialFields(direction) {
    if (direction === 'toPdf') {
        // building_type: checkboxes → combined text
        var types = [];
        $('input[name="rbi_building_type[]"]:checked').each(function() { types.push($(this).val()); });
        var otherText = $('#rbi_building_type_other').val();
        if (otherText) types.push(otherText);
        $('#rbipdf_building_type_text').val(types.join(', '));

        // mezzanine radio → PDF checkboxes
        var mezVal = $('input[name="rbi_mezzanine"]:checked').val() || '';
        $('#rbipdf_mezzanine_yes').prop('checked', mezVal === 'มี');
        $('#rbipdf_mezzanine_no').prop('checked', mezVal === 'ไม่มี');

        // rooftop radio → PDF checkboxes
        var rtVal = $('input[name="rbi_rooftop"]:checked').val() || '';
        $('#rbipdf_rooftop_yes').prop('checked', rtVal === 'มี');
        $('#rbipdf_rooftop_no').prop('checked', rtVal === 'ไม่มี');

        // fence radio → PDF checkboxes
        var fnVal = $('input[name="rbi_fence"]:checked').val() || '';
        $('#rbipdf_fence_yes').prop('checked', fnVal === 'มี');
        $('#rbipdf_fence_no').prop('checked', fnVal === 'ไม่มี');
    } else {
        // PDF text → standard checkboxes
        var text = ($('#rbipdf_building_type_text').val() || '').trim();
        var parts = text.split(',').map(function(s) { return s.trim(); });
        var known = ['บ้าน', 'ตึกแถว', 'อาคาร', 'อื่นๆ'];
        $('input[name="rbi_building_type[]"]').prop('checked', false);
        var others = [];
        parts.forEach(function(p) {
            if (!p) return;
            if (known.indexOf(p) >= 0) {
                $('input[name="rbi_building_type[]"][value="' + p + '"]').prop('checked', true);
            } else {
                others.push(p);
            }
        });
        $('#rbi_building_type_other').val(others.join(', '));

        // PDF checkboxes → standard radios
        if ($('#rbipdf_mezzanine_yes').is(':checked')) {
            $('input[name="rbi_mezzanine"][value="มี"]').prop('checked', true);
        } else if ($('#rbipdf_mezzanine_no').is(':checked')) {
            $('input[name="rbi_mezzanine"][value="ไม่มี"]').prop('checked', true);
        }

        if ($('#rbipdf_rooftop_yes').is(':checked')) {
            $('input[name="rbi_rooftop"][value="มี"]').prop('checked', true);
        } else if ($('#rbipdf_rooftop_no').is(':checked')) {
            $('input[name="rbi_rooftop"][value="ไม่มี"]').prop('checked', true);
        }

        if ($('#rbipdf_fence_yes').is(':checked')) {
            $('input[name="rbi_fence"][value="มี"]').prop('checked', true);
        } else if ($('#rbipdf_fence_no').is(':checked')) {
            $('input[name="rbi_fence"][value="ไม่มี"]').prop('checked', true);
        }
    }
}

function syncBombIndoorReportInspectorRows(direction) {
    if (direction === 'toPdf') {
        var stdRows = $('#rbi_inspector_container .rp-inspector-row');
        var pdfContainer = document.getElementById('rbipdf_inspector_container');
        while (pdfContainer.querySelectorAll('.rbipdf-inspector-row').length < stdRows.length) {
            rbipdfAddInspectorRow();
        }
        var currentPdf = pdfContainer.querySelectorAll('.rbipdf-inspector-row');
        for (var i = stdRows.length; i < currentPdf.length; i++) {
            currentPdf[i].remove();
        }
        rbipdfRenumberInspectors();
        stdRows.each(function(idx) {
            var pdfRow = pdfContainer.querySelectorAll('.rbipdf-inspector-row')[idx];
            if (!pdfRow) return;
            var nameVal = $(this).find('.rp-inspector-select').val() || '';
            var posVal = $(this).find('.rp-inspector-position').val() || '';
            var pdfSelect = $(pdfRow).find('.rp-inspector-select');
            if (pdfSelect.length) {
                if (nameVal && pdfSelect.find('option[value="' + nameVal + '"]').length === 0) {
                    pdfSelect.append('<option value="' + nameVal + '">' + nameVal + '</option>');
                }
                pdfSelect.val(nameVal);
                if (pdfSelect.hasClass('select2-hidden-accessible')) pdfSelect.trigger('change.select2');
            }
            $(pdfRow).find('.rp-inspector-position').val(posVal);
        });
    } else {
        var pdfContainer2 = document.getElementById('rbipdf_inspector_container');
        var pdfRows = pdfContainer2.querySelectorAll('.rbipdf-inspector-row');
        var $container = $('#rbi_inspector_container');
        while ($container.find('.rp-inspector-row').length < pdfRows.length) {
            var count = $container.find('.rp-inspector-row').length + 1;
            $container.append(rpBuildInspectorRow('5.' + count, '', '', 'rbi'));
        }
        var stdRowsNow = $container.find('.rp-inspector-row');
        for (var j = pdfRows.length; j < stdRowsNow.length; j++) {
            $(stdRowsNow[j]).remove();
        }
        pdfRows.forEach(function(pdfRow, idx) {
            var stdRow = $container.find('.rp-inspector-row').eq(idx);
            if (!stdRow.length) return;
            var pdfName = $(pdfRow).find('.rp-inspector-select').val() || '';
            var pdfPos = $(pdfRow).find('.rp-inspector-position').val() || '';
            var stdSelect = stdRow.find('.rp-inspector-select');
            if (pdfName && stdSelect.find('option[value="' + pdfName + '"]').length === 0) {
                stdSelect.append('<option value="' + pdfName + '">' + pdfName + '</option>');
            }
            stdSelect.val(pdfName);
            if (stdSelect.hasClass('select2-hidden-accessible')) stdSelect.trigger('change.select2');
            stdRow.find('.rp-inspector-position').val(pdfPos);
        });
    }
}

function switchBombIndoorReportToPdfForm() {
    syncBombIndoorReportInspectorRows('toPdf');
    syncBombIndoorReportFormData('formReportBombIndoor', 'formReportBombIndoorPdf');
    syncBombIndoorReportSpecialFields('toPdf');
    var agVal = $('#rbipdf_agency_name').val(); $('#rbipdf_agency_name_p2').val(agVal); $('#rbipdf_agency_name_p3').val(agVal);
    $('#rbipdf_incident_id').val($('#rbi_incident_id').val());
    restoreDraftPdfReportNoFromDocDisplay('#rbi_doc_no_display', ['#rbipdf_report_no_display', '#rbipdf_report_no_display_2', '#rbipdf_report_no_display_3']);

    var stdEl = document.getElementById('modalReportBombIndoor');
    var stdModal = bootstrap.Modal.getInstance(stdEl);
    if (stdModal) stdModal.hide();

    stdEl.addEventListener('hidden.bs.modal', function onHidden() {
        stdEl.removeEventListener('hidden.bs.modal', onHidden);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalReportBombIndoorPdf')).show();
    });
}
window.switchBombIndoorReportToPdfForm = switchBombIndoorReportToPdfForm;

function switchBombIndoorReportToStandard() {
    syncBombIndoorReportInspectorRows('toStd');
    syncBombIndoorReportFormData('formReportBombIndoorPdf', 'formReportBombIndoor');
    syncBombIndoorReportSpecialFields('toStd');
    $('#rbi_incident_id').val($('#rbipdf_incident_id').val());

    var pdfEl = document.getElementById('modalReportBombIndoorPdf');
    var pdfModal = bootstrap.Modal.getInstance(pdfEl);
    if (pdfModal) pdfModal.hide();

    pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
        pdfEl.removeEventListener('hidden.bs.modal', onHidden);
        var stdEl = document.getElementById('modalReportBombIndoor');
        stdEl.addEventListener('shown.bs.modal', function onShown() {
            stdEl.removeEventListener('shown.bs.modal', onShown);
            rpInitSelect2InModal($('#modalReportBombIndoor'));
        });
        bootstrap.Modal.getOrCreateInstance(stdEl).show();
    });
}
window.switchBombIndoorReportToStandard = switchBombIndoorReportToStandard;

// Save from Bomb Indoor PDF form
$(document).on('click', '#btn_save_report_bomb_indoor_pdf', function() {
    var incidentId = $('#rbipdf_incident_id').val();
    if (!incidentId) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
        return;
    }

    var formData = {};
    var formEl = document.getElementById('formReportBombIndoorPdf');
    var elements = formEl.elements;

    for (var i = 0; i < elements.length; i++) {
        var el = elements[i];
        if (!el.name || el.name === 'incident_id') continue;

        if (el.type === 'radio') {
            if (el.checked) formData[el.name] = el.value;
        } else if (el.type === 'checkbox') {
            if (!formData[el.name]) formData[el.name] = [];
            if (el.checked) formData[el.name].push(el.value);
        } else {
            if (el.name.endsWith('[]')) {
                if (!formData[el.name]) formData[el.name] = [];
                formData[el.name].push(el.value);
            } else {
                formData[el.name] = el.value;
            }
        }
    }

    var payload = {
        incident_id: incidentId,
        report_type: 'indoor',
        form_data: formData
    };

    var btn = this;
    $.ajax({
        url: '/csims/api/incidentCheckList/saveBombReport.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        beforeSend: function() { $(btn).prop('disabled', true); },
        success: function(response) {
            if (response.success) {
                var $pdfCell = $('.report-row[data-id="' + incidentId + '"]').find('.pdf-cell');
                var loc = $('.report-row[data-id="' + incidentId + '"]').data('location-type') || 'indoor';
                $pdfCell.html('<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="' + incidentId + '" data-pdf-type="05" data-pdf-location="' + loc + '" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>');

                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: response.message,
                    confirmButtonColor: '#0d6efd'
                }).then(function() {
                    var m = bootstrap.Modal.getInstance(document.getElementById('modalReportBombIndoorPdf'));
                    if (m) m.hide();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
            }
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
        },
        complete: function() { $(btn).prop('disabled', false); }
    });
});

// ========================================
// Bomb Outdoor Report: Switch between Standard ↔ PDF Form
// (Global scope — called from inline onchange handlers)
// ========================================

function syncBombOutdoorReportFormData(fromFormId, toFormId) {
    var fromForm = document.getElementById(fromFormId);
    var toForm = document.getElementById(toFormId);
    if (!fromForm || !toForm) return;

    var fromEls = fromForm.querySelectorAll('input, select, textarea');
    var dataMap = {};

    fromEls.forEach(function(el) {
        var name = el.name;
        if (!name || name === 'incident_id') return;
        if (el.type === 'file') return;

        if (el.type === 'checkbox') {
            if (!dataMap[name]) dataMap[name] = { type: 'checkbox', values: [] };
            if (el.checked) dataMap[name].values.push(el.value);
        } else if (el.type === 'radio') {
            if (el.checked) dataMap[name] = { type: 'radio', value: el.value };
        } else {
            if (!dataMap[name]) dataMap[name] = { type: 'value', values: [] };
            dataMap[name].values.push(el.value);
        }
    });

    Object.keys(dataMap).forEach(function(name) {
        var info = dataMap[name];
        var toEls = toForm.querySelectorAll('[name="' + name + '"]');
        if (toEls.length === 0) return;

        if (info.type === 'checkbox') {
            toEls.forEach(function(el) { el.checked = info.values.includes(el.value); });
        } else if (info.type === 'radio') {
            toEls.forEach(function(el) { el.checked = (el.value === info.value); });
        } else {
            toEls.forEach(function(el, idx) {
                if (idx < info.values.length) el.value = info.values[idx];
            });
        }
    });
}

function syncBombOutdoorReportInspectorRows(direction) {
    if (direction === 'toPdf') {
        var stdRows = $('#rbo_inspector_container .rp-inspector-row');
        var pdfContainer = document.getElementById('rbopdf_inspector_container');
        while (pdfContainer.querySelectorAll('.rbopdf-inspector-row').length < stdRows.length) {
            rbopdfAddInspectorRow();
        }
        var currentPdf = pdfContainer.querySelectorAll('.rbopdf-inspector-row');
        for (var i = stdRows.length; i < currentPdf.length; i++) {
            currentPdf[i].remove();
        }
        rbopdfRenumberInspectors();
        stdRows.each(function(idx) {
            var pdfRow = pdfContainer.querySelectorAll('.rbopdf-inspector-row')[idx];
            if (!pdfRow) return;
            var nameVal = $(this).find('.rp-inspector-select').val() || '';
            var posVal = $(this).find('.rp-inspector-position').val() || '';
            var pdfSelect = $(pdfRow).find('.rp-inspector-select');
            if (pdfSelect.length) {
                if (nameVal && pdfSelect.find('option[value="' + nameVal + '"]').length === 0) {
                    pdfSelect.append('<option value="' + nameVal + '">' + nameVal + '</option>');
                }
                pdfSelect.val(nameVal);
                if (pdfSelect.hasClass('select2-hidden-accessible')) pdfSelect.trigger('change.select2');
            }
            $(pdfRow).find('.rp-inspector-position').val(posVal);
        });
    } else {
        var pdfContainer2 = document.getElementById('rbopdf_inspector_container');
        var pdfRows = pdfContainer2.querySelectorAll('.rbopdf-inspector-row');
        var $container = $('#rbo_inspector_container');
        while ($container.find('.rp-inspector-row').length < pdfRows.length) {
            var count = $container.find('.rp-inspector-row').length + 1;
            $container.append(rpBuildInspectorRow('5.' + count, '', '', 'rbo'));
        }
        var stdRowsNow = $container.find('.rp-inspector-row');
        for (var j = pdfRows.length; j < stdRowsNow.length; j++) {
            $(stdRowsNow[j]).remove();
        }
        pdfRows.forEach(function(pdfRow, idx) {
            var stdRow = $container.find('.rp-inspector-row').eq(idx);
            if (!stdRow.length) return;
            var pdfName = $(pdfRow).find('.rp-inspector-select').val() || '';
            var pdfPos = $(pdfRow).find('.rp-inspector-position').val() || '';
            var stdSelect = stdRow.find('.rp-inspector-select');
            if (pdfName && stdSelect.find('option[value="' + pdfName + '"]').length === 0) {
                stdSelect.append('<option value="' + pdfName + '">' + pdfName + '</option>');
            }
            stdSelect.val(pdfName);
            if (stdSelect.hasClass('select2-hidden-accessible')) stdSelect.trigger('change.select2');
            stdRow.find('.rp-inspector-position').val(pdfPos);
        });
    }
}

function switchBombOutdoorReportToPdfForm() {
    syncBombOutdoorReportInspectorRows('toPdf');
    syncBombOutdoorReportFormData('formReportBombOutdoor', 'formReportBombOutdoorPdf');
    var agVal = $('#rbopdf_agency_name').val(); $('#rbopdf_agency_name_p2').val(agVal); $('#rbopdf_agency_name_p3').val(agVal);
    $('#rbopdf_incident_id').val($('#rbo_incident_id').val());
    restoreDraftPdfReportNoFromDocDisplay('#rbo_doc_no_display', ['#rbopdf_report_no_display', '#rbopdf_report_no_display_2', '#rbopdf_report_no_display_3']);

    var stdEl = document.getElementById('modalReportBombOutdoor');
    var stdModal = bootstrap.Modal.getInstance(stdEl);
    if (stdModal) stdModal.hide();

    stdEl.addEventListener('hidden.bs.modal', function onHidden() {
        stdEl.removeEventListener('hidden.bs.modal', onHidden);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalReportBombOutdoorPdf')).show();
    });
}
window.switchBombOutdoorReportToPdfForm = switchBombOutdoorReportToPdfForm;

function switchBombOutdoorReportToStandard() {
    syncBombOutdoorReportInspectorRows('toStd');
    syncBombOutdoorReportFormData('formReportBombOutdoorPdf', 'formReportBombOutdoor');
    $('#rbo_incident_id').val($('#rbopdf_incident_id').val());

    var pdfEl = document.getElementById('modalReportBombOutdoorPdf');
    var pdfModal = bootstrap.Modal.getInstance(pdfEl);
    if (pdfModal) pdfModal.hide();

    pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
        pdfEl.removeEventListener('hidden.bs.modal', onHidden);
        var stdEl = document.getElementById('modalReportBombOutdoor');
        stdEl.addEventListener('shown.bs.modal', function onShown() {
            stdEl.removeEventListener('shown.bs.modal', onShown);
            rpInitSelect2InModal($('#modalReportBombOutdoor'));
        });
        bootstrap.Modal.getOrCreateInstance(stdEl).show();
    });
}
window.switchBombOutdoorReportToStandard = switchBombOutdoorReportToStandard;

// Save from Bomb Outdoor PDF form
$(document).on('click', '#btn_save_report_bomb_outdoor_pdf', function() {
    var incidentId = $('#rbopdf_incident_id').val();
    if (!incidentId) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
        return;
    }

    var formData = {};
    var formEl = document.getElementById('formReportBombOutdoorPdf');
    var elements = formEl.elements;

    for (var i = 0; i < elements.length; i++) {
        var el = elements[i];
        if (!el.name || el.name === 'incident_id') continue;

        if (el.type === 'radio') {
            if (el.checked) formData[el.name] = el.value;
        } else if (el.type === 'checkbox') {
            if (!formData[el.name]) formData[el.name] = [];
            if (el.checked) formData[el.name].push(el.value);
        } else {
            if (el.name.endsWith('[]')) {
                if (!formData[el.name]) formData[el.name] = [];
                formData[el.name].push(el.value);
            } else {
                formData[el.name] = el.value;
            }
        }
    }

    var payload = {
        incident_id: incidentId,
        report_type: 'outdoor',
        form_data: formData
    };

    var btn = this;
    $.ajax({
        url: '/csims/api/incidentCheckList/saveBombReport.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        beforeSend: function() { $(btn).prop('disabled', true); },
        success: function(response) {
            if (response.success) {
                var $pdfCell = $('.report-row[data-id="' + incidentId + '"]').find('.pdf-cell');
                var loc = $('.report-row[data-id="' + incidentId + '"]').data('location-type') || 'outdoor';
                $pdfCell.html('<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="' + incidentId + '" data-pdf-type="03" data-pdf-location="' + loc + '" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>');

                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: response.message,
                    confirmButtonColor: '#0d6efd'
                }).then(function() {
                    var m = bootstrap.Modal.getInstance(document.getElementById('modalReportBombOutdoorPdf'));
                    if (m) m.hide();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
            }
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
        },
        complete: function() { $(btn).prop('disabled', false); }
    });
});

    // =====================================================
    // Dynamic Row Management for Property Report
    // =====================================================

    // --- Load users list for Section 5 ---
    let rpUsersList = [];
    window.rpUsersList = rpUsersList;

    function rpLoadUsers(callback) {
        if (rpUsersList.length > 0) { if (callback) callback(); return; }
        $.ajax({
            url: '/csims/api/get_users.php',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    rpUsersList = res.data;
                    window.rpUsersList = rpUsersList;
                    rpPopulateAllInspectorSelects();
                }
                if (callback) callback();
            }
        });
    }
    window.rpLoadUsers = rpLoadUsers;

    function rpBuildUserOptions(selectedName) {
        let html = '<option value="">-- เลือกผู้ตรวจ --</option>';
        rpUsersList.forEach(function(u) {
            const sel = (u.fullname === selectedName) ? ' selected' : '';
            html += `<option value="${u.fullname}"${sel}>${u.fullname}</option>`;
        });
        return html;
    }
    window.rpBuildUserOptions = rpBuildUserOptions;

    function rpPopulateAllInspectorSelects() {
        $('.rp-inspector-container-wrap .rp-inspector-select, #rp_inspector_container .rp-inspector-select').each(function() {
            const current = $(this).val();
            $(this).html(rpBuildUserOptions(current));
        });
    }
    window.rpPopulateAllInspectorSelects = rpPopulateAllInspectorSelects;

    // Initialize Select2 on inspector selects inside a given modal
    function rpInitSelect2InModal($modal) {
        $modal.find('.rp-inspector-select').each(function() {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).select2('destroy');
            }
            $(this).select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '-- เลือกผู้ตรวจ --',
                allowClear: true,
                dropdownParent: $modal,
                selectionCssClass: 'select2--small'
            });
        });
    }
    window.rpInitSelect2InModal = rpInitSelect2InModal;

    function rpBuildInspectorRow(num, selectedName, position, namePrefix) {
        const pfx = namePrefix || 'rp';
        return `
            <div class="row g-2 mb-2 align-items-center rp-inspector-row">
                <div class="col-auto">
                    <span class="fw-semibold rp-inspector-num" style="min-width:32px; display:inline-block;">${num}</span>
                </div>
                <div class="col">
                    <select class="form-select form-select-sm rp-inspector-select" name="${pfx}_inspector_name[]">
                        ${rpBuildUserOptions(selectedName || '')}
                    </select>
                </div>
                <div class="col-auto">
                    <span class="fw-semibold">ตำแหน่ง</span>
                </div>
                <div class="col">
                    <input type="text" class="form-control form-control-sm rp-inspector-position" name="${pfx}_inspector_position[]" placeholder="ตำแหน่ง" readonly value="${position || ''}">
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-sm btn-outline-danger rp-remove-inspector" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                </div>
            </div>`;
    }
    window.rpBuildInspectorRow = rpBuildInspectorRow;

    // Detect prefix from the inspector container in the current modal
    function rpGetPrefixFromContainer($container) {
        const id = $container.attr('id') || '';
        if (id.startsWith('rli_')) return 'rli';
        if (id.startsWith('rlo_')) return 'rlo';
        if (id.startsWith('rbi_')) return 'rbi';
        if (id.startsWith('rbo_')) return 'rbo';
        if (id.startsWith('rt_')) return 'rt';
        if (id.startsWith('rf_')) return 'rf';
        return 'rp';
    }

    // --- Section 5: Inspectors ---
    $(document).on('click', '#rp_add_inspector, .rp-add-inspector-btn', function() {
        const $fieldset = $(this).closest('fieldset');
        const $container = $fieldset.find('.rp-inspector-container-wrap, [id=rp_inspector_container]').first();
        const pfx = rpGetPrefixFromContainer($container);
        const count = $container.find('.rp-inspector-row').length + 1;
        const firstNumText = $container.find('.rp-inspector-row:first .rp-inspector-num').text();
        const secNum = (firstNumText.split('.')[0] || '5');
        $container.append(rpBuildInspectorRow(secNum + '.' + count, '', '', pfx));
        // Init Select2 on the newly added select
        const $modal = $(this).closest('.modal');
        const $newSelect = $container.find('.rp-inspector-row:last .rp-inspector-select');
        $newSelect.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: '-- เลือกผู้ตรวจ --',
            allowClear: true,
            dropdownParent: $modal,
            selectionCssClass: 'select2--small'
        });
    });

    $(document).on('click', '.rp-remove-inspector', function() {
        const $container = $(this).closest('.rp-inspector-container-wrap, [id=rp_inspector_container]');
        if ($container.find('.rp-inspector-row').length > 1) {
            $(this).closest('.rp-inspector-row').remove();
            rpRenumberInspectors($container);
        }
    });

    // Auto-fill position when selecting inspector
    $(document).on('change', '.rp-inspector-select', function() {
        const selectedName = $(this).val();
        const $row = $(this).closest('.rp-inspector-row, .rfpdf-inspector-row, .rlopdf-inspector-row, .rppdf-inspector-row, .rlipdf-inspector-row, .rbipdf-inspector-row, .rbopdf-inspector-row, .rtpdf-inspector-row');
        const $posInput = $row.find('.rp-inspector-position');
        const user = rpUsersList.find(function(u) { return u.fullname === selectedName; });
        $posInput.val(user ? (user.position_name || '') : '');
    });

    function rpRenumberInspectors($container) {
        if (!$container) $container = $('#rp_inspector_container');
        const firstNumText = $container.find('.rp-inspector-row:first .rp-inspector-num').text();
        const secNum = (firstNumText.split('.')[0] || '5');
        $container.find('.rp-inspector-row').each(function(i) {
            $(this).find('.rp-inspector-num').text(secNum + '.' + (i + 1));
        });
    }

    // --- Section 7.3: Rooms ---
    $(document).on('click', '#rp_add_room', function() {
        const count = $('#rp_room_container .rp-room-block').length + 1;
        const num = '7.3.' + count;
        const html = `
            <div class="ps-3 mb-4 border-start border-3 border-primary ms-2 rp-room-block">
                <div class="d-flex align-items-center mb-3 ps-2">
                    <h6 class="fw-semibold text-primary mb-0"><span class="rp-room-num">${num}</span> ที่ห้อง</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2 rp-remove-room" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                </div>
                <div class="ps-3">
                    <div class="mb-3">
                        <input type="text" class="form-control form-control-sm" name="rp_room_name[]" placeholder="ชื่อห้อง">
                    </div>
                    <div class="mb-2">
                        <label class="form-label"><span class="rp-room-sub-num">${num}</span>.1 ทางเข้าของคนร้าย พบ/ไม่พบรอยจัดที่</label>
                        <textarea class="form-control form-control-sm" name="rp_room_entry[]" rows="2"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label"><span class="rp-room-sub-num">${num}</span>.2 พบรอยจัดที่</label>
                        <textarea class="form-control form-control-sm" name="rp_room_marks[]" rows="2"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label"><span class="rp-room-sub-num">${num}</span>.3 พบร่องรอยรื้อค้นที่</label>
                        <textarea class="form-control form-control-sm" name="rp_room_search_marks[]" rows="2"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label"><span class="rp-room-sub-num">${num}</span>.4 วัตถุพยานอื่นๆที่ตรวจพบ</label>
                        <textarea class="form-control form-control-sm" name="rp_room_other_evidence[]" rows="2"></textarea>
                    </div>
                </div>
            </div>`;
        $('#rp_room_container').append(html);
    });

    $(document).on('click', '.rp-remove-room', function() {
        if ($('#rp_room_container .rp-room-block').length > 1) {
            $(this).closest('.rp-room-block').remove();
            rpRenumberRooms();
        }
    });

    function rpRenumberRooms() {
        $('#rp_room_container .rp-room-block').each(function(i) {
            const num = '7.3.' + (i + 1);
            $(this).find('.rp-room-num').text(num);
            $(this).find('.rp-room-sub-num').text(num);
        });
    }

    // --- Section 7.4: Stolen Block (add/remove whole block) ---
    function rpBuildStolenBlock(num) {
        return `
            <div class="ps-3 mb-4 border-start border-3 border-secondary ms-2 rp-stolen-block">
                <div class="d-flex align-items-center mb-3 ps-2">
                    <h6 class="fw-semibold text-dark mb-0"><span class="rp-stolen-block-num">${num}</span> ทรัพย์สินของผู้เสียหาย</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2 rp-remove-stolen-block" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                </div>
                <div class="ps-3">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">คำนำหน้า</label>
                            <select class="form-select form-select-sm" name="rp_stolen_victim_prefix[]">
                                <option value="">-- เลือก --</option>
                                <option value="นาย">นาย</option>
                                <option value="นาง">นาง</option>
                                <option value="นางสาว">นางสาว</option>
                                <option value="อื่นๆ">อื่นๆ</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">ชื่อ-นามสกุล</label>
                            <input type="text" class="form-control form-control-sm" name="rp_stolen_victim_name[]">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">สถานะ</label>
                            <select class="form-select form-select-sm" name="rp_stolen_victim_role[]">
                                <option value="">-- เลือก --</option>
                                <option value="เจ้าของบ้าน">เจ้าของบ้าน</option>
                                <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                                <option value="อื่นๆ">อื่นๆ</option>
                            </select>
                        </div>
                    </div>
                    <label class="form-label fw-semibold">รายการทรัพย์สิน</label>
                    <div class="rp-stolen-items mb-2">
                        <div class="row g-2 mb-2 rp-stolen-item-row">
                            <div class="col-auto d-flex align-items-center"><span class="rp-stolen-item-num text-muted" style="min-width:28px;">1.</span></div>
                            <div class="col"><input type="text" class="form-control form-control-sm" name="rp_stolen_item[]" placeholder="รายการทรัพย์สิน"></div>
                            <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger rp-remove-stolen-item" title="ลบ"><i class="fas fa-times"></i></button></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary rp-add-stolen-item">
                        <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                    </button>
                </div>
            </div>`;
    }

    $(document).on('click', '#rp_add_stolen_block', function() {
        const count = $('#rp_stolen_container .rp-stolen-block').length + 1;
        $('#rp_stolen_container').append(rpBuildStolenBlock('7.4.' + count));
    });

    $(document).on('click', '.rp-remove-stolen-block', function() {
        if ($('#rp_stolen_container .rp-stolen-block').length > 1) {
            $(this).closest('.rp-stolen-block').remove();
            $('#rp_stolen_container .rp-stolen-block').each(function(i) {
                $(this).find('.rp-stolen-block-num').text('7.4.' + (i + 1));
            });
        }
    });

    // Items within each 7.4 block
    $(document).on('click', '.rp-add-stolen-item', function() {
        const $items = $(this).closest('.rp-stolen-block').find('.rp-stolen-items');
        const count = $items.find('.rp-stolen-item-row').length + 1;
        $items.append(`
            <div class="row g-2 mb-2 rp-stolen-item-row">
                <div class="col-auto d-flex align-items-center"><span class="rp-stolen-item-num text-muted" style="min-width:28px;">${count}.</span></div>
                <div class="col"><input type="text" class="form-control form-control-sm" name="rp_stolen_item[]" placeholder="รายการทรัพย์สิน"></div>
                <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger rp-remove-stolen-item" title="ลบ"><i class="fas fa-times"></i></button></div>
            </div>`);
    });

    $(document).on('click', '.rp-remove-stolen-item', function() {
        const $items = $(this).closest('.rp-stolen-items');
        if ($items.find('.rp-stolen-item-row').length > 1) {
            $(this).closest('.rp-stolen-item-row').remove();
            $items.find('.rp-stolen-item-row').each(function(i) {
                $(this).find('.rp-stolen-item-num').text((i + 1) + '.');
            });
        }
    });

    // --- Section 7.5: Evidence Block (add/remove whole block) ---
    function rpBuildEvidenceBlock(num) {
        return `
            <div class="ps-3 mb-4 border-start border-3 border-secondary ms-2 rp-evidence-block">
                <div class="d-flex align-items-center gap-2 mb-3 ps-2">
                    <h6 class="fw-semibold text-dark mb-0">
                        <span class="rp-evidence-block-num">${num}</span> ตรวจเก็บวัตถุพยาน รอยลายนิ้วมือแฝง/ฝ่ามือแฝง/ฝ่าเท้าแฝง
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-danger rp-remove-evidence-block" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                </div>
                <div class="ps-3">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">จำนวน (แผ่น/ชิ้น)</label>
                            <input type="text" class="form-control form-control-sm" name="rp_evidence_count[]">
                        </div>
                    </div>
                    <label class="form-label fw-semibold">ที่ (รายละเอียดสถานที่ตรวจพบ)</label>
                    <div class="rp-evidence-locs mb-2">
                        <div class="row g-2 mb-2 rp-evidence-loc-row">
                            <div class="col-auto d-flex align-items-center"><span class="rp-evidence-loc-num text-muted" style="min-width:28px;">1.</span></div>
                            <div class="col"><input type="text" class="form-control form-control-sm" name="rp_evidence_loc[]"></div>
                            <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger rp-remove-evidence-loc" title="ลบ"><i class="fas fa-times"></i></button></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary rp-add-evidence-loc">
                        <i class="fas fa-plus me-1"></i> เพิ่มรายการ
                    </button>
                    <hr class="my-3">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label fw-semibold">และได้ให้ลงลายมือชื่อไว้เป็นหลักฐาน</label></div>
                        <div class="col-md-3">
                            <label class="form-label">คำนำหน้า</label>
                            <select class="form-select form-select-sm" name="rp_evidence_signer_prefix[]">
                                <option value="">-- เลือก --</option>
                                <option value="นาย">นาย</option>
                                <option value="นาง">นาง</option>
                                <option value="นางสาว">นางสาว</option>
                                <option value="อื่นๆ">อื่นๆ</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">ชื่อ-นามสกุล</label>
                            <input type="text" class="form-control form-control-sm" name="rp_evidence_signer_name[]">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">สถานะ</label>
                            <select class="form-select form-select-sm" name="rp_evidence_signer_role[]">
                                <option value="">-- เลือก --</option>
                                <option value="เจ้าของบ้าน">เจ้าของบ้าน</option>
                                <option value="ผู้เสียหาย">ผู้เสียหาย</option>
                                <option value="อื่นๆ">อื่นๆ</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>`;
    }

    $(document).on('click', '#rp_add_evidence_block', function() {
        const count = $('#rp_evidence_container .rp-evidence-block').length + 1;
        $('#rp_evidence_container').append(rpBuildEvidenceBlock('7.5.' + count));
    });

    $(document).on('click', '.rp-remove-evidence-block', function() {
        if ($('#rp_evidence_container .rp-evidence-block').length > 1) {
            $(this).closest('.rp-evidence-block').remove();
            $('#rp_evidence_container .rp-evidence-block').each(function(i) {
                $(this).find('.rp-evidence-block-num').text('7.5.' + (i + 1));
            });
        }
    });

    // Location items within each 7.5 block
    $(document).on('click', '.rp-add-evidence-loc', function() {
        const $locs = $(this).closest('.rp-evidence-block').find('.rp-evidence-locs');
        const count = $locs.find('.rp-evidence-loc-row').length + 1;
        $locs.append(`
            <div class="row g-2 mb-2 rp-evidence-loc-row">
                <div class="col-auto d-flex align-items-center"><span class="rp-evidence-loc-num text-muted" style="min-width:28px;">${count}.</span></div>
                <div class="col"><input type="text" class="form-control form-control-sm" name="rp_evidence_loc[]"></div>
                <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger rp-remove-evidence-loc" title="ลบ"><i class="fas fa-times"></i></button></div>
            </div>`);
    });

    $(document).on('click', '.rp-remove-evidence-loc', function() {
        const $locs = $(this).closest('.rp-evidence-locs');
        if ($locs.find('.rp-evidence-loc-row').length > 1) {
            $(this).closest('.rp-evidence-loc-row').remove();
            $locs.find('.rp-evidence-loc-row').each(function(i) {
                $(this).find('.rp-evidence-loc-num').text((i + 1) + '.');
            });
        }
    });

    // Section 7.7 is now static — no add/remove handlers needed.

    // =====================================================
    // Reset & Prefill Dynamic Containers
    // =====================================================

    function rpResetDynamicContainers() {
        const $propModal = $('#modalReportProperty');
        // Inspectors: keep 1 row, reset select + position (property modal only)
        const $propContainer = $propModal.find('[id=rp_inspector_container]');
        $propContainer.find('.rp-inspector-row').not(':first').remove();
        $propContainer.find('.rp-inspector-select').prop('selectedIndex', 0);
        $propContainer.find('.rp-inspector-position').val('');
        rpRenumberInspectors($propContainer);

        // Rooms: keep 1 block
        $('#rp_room_container .rp-room-block').not(':first').remove();
        $('#rp_room_container .rp-room-block:first').find('input, textarea').val('');
        rpRenumberRooms();

        // Stolen blocks: keep 1 block with 1 item
        $('#rp_stolen_container .rp-stolen-block').not(':first').remove();
        const $firstStolen = $('#rp_stolen_container .rp-stolen-block:first');
        $firstStolen.find('.rp-stolen-item-row').not(':first').remove();
        $firstStolen.find('input').val('');
        $firstStolen.find('select').prop('selectedIndex', 0);
        $firstStolen.find('.rp-stolen-block-num').text('7.4.1');

        // Evidence blocks: keep 1 block with 1 loc
        $('#rp_evidence_container .rp-evidence-block').not(':first').remove();
        const $firstEvidence = $('#rp_evidence_container .rp-evidence-block:first');
        $firstEvidence.find('.rp-evidence-loc-row').not(':first').remove();
        $firstEvidence.find('input').val('');
        $firstEvidence.find('select').prop('selectedIndex', 0);
        $firstEvidence.find('.rp-evidence-block-num').text('7.5.1');

        // Handover: static fields
        $('#rp_handover_agency').prop('selectedIndex', 0);
        $('#rp_handover_detail').val('');
    }

    function rpPrefillDynamic(data) {
        if (!data) return;

        // Add extra inspector rows (property modal only)
        const $propModal = $('#modalReportProperty');
        const $propContainer = $propModal.find('[id=rp_inspector_container]');
        const inspectorNames = data['rp_inspector_name[]'];
        if (Array.isArray(inspectorNames) && inspectorNames.length > 1) {
            for (let i = 1; i < inspectorNames.length; i++) {
                const count = $propContainer.find('.rp-inspector-row').length + 1;
                $propContainer.append(rpBuildInspectorRow('5.' + count, '', '', 'rp'));
            }
        }

        // Add extra room blocks
        const roomNames = data['rp_room_name[]'];
        if (Array.isArray(roomNames) && roomNames.length > 1) {
            for (let i = 1; i < roomNames.length; i++) $('#rp_add_room').trigger('click');
        }

        // Add extra stolen blocks (one per victim)
        const stolenVictims = data['rp_stolen_victim_name[]'];
        if (Array.isArray(stolenVictims) && stolenVictims.length > 1) {
            for (let i = 1; i < stolenVictims.length; i++) $('#rp_add_stolen_block').trigger('click');
        }

        // Add extra evidence blocks
        const evidenceTypes = data['rp_evidence_type[]'];
        if (Array.isArray(evidenceTypes) && evidenceTypes.length > 1) {
            for (let i = 1; i < evidenceTypes.length; i++) $('#rp_add_evidence_block').trigger('click');
        }

        // Handover is now static (no dynamic rows)
    }

    // --- Open Property Report Modal & Load Data ---
    function openPropertyReportModal(incidentId, docNo, reportNo) {
        document.getElementById('formReportProperty').reset();
        $('#rp_incident_id').val(incidentId);
        $('#rp_doc_no_display').text(thaiDocNo(docNo));

        // Set report_no in PDF modal header (ใช้เลขที่เอกสารเต็ม)
        var currentYear = '<?= substr((date('Y') + 543), -2) ?>';
        var rptYear = (reportNo || '').indexOf('/') !== -1 ? (reportNo.split('/')[1] || '').toString().slice(-2) : '';
        rptYear = rptYear || currentYear;
        $('#rppdf_report_no_display').val(thaiDocNo(docNo));
        $('#rppdf_report_year').val(rptYear);
        $('#rppdf_report_no_p2, #rppdf_report_no_p3, #rppdf_report_no_p4').text(thaiDocNo(docNo));
        $('#rppdf_report_year_p2, #rppdf_report_year_p3, #rppdf_report_year_p4').text(rptYear);
        $('#rp_editInfo').addClass('d-none');
        $('#rp_editCount').text('0');
        $('#rp_editDate').text('-');

        rpResetDynamicContainers();

        // Load users for inspector dropdowns (cached after first load)
        rpLoadUsers(function() {
            rpPopulateAllInspectorSelects();
            rpInitSelect2InModal($('#modalReportProperty'));

            $.ajax({
                url: '/csims/api/incidentCheckList/getPropertyReportData.php',
                type: 'GET',
                data: { incident_id: incidentId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data && response.data.property) {
                        rpPrefillDynamic(response.data.property);
                        prefillReportForm('rp', response.data.property);
                        rpInitSelect2InModal($('#modalReportProperty'));
                    }
                    const countEdit = response.count_report || 0;
                    if (countEdit > 0) {
                        $('#rp_editInfo').removeClass('d-none');
                        $('#rp_editCount').text(countEdit);
                        $('#rp_editDate').text(response.edit_date || '-');
                    }

                    // Sync data to PDF form and open PDF modal as default
                    syncPropertyReportInspectorRows('toPdf');
                    syncPropertyReportDynamicSections('toPdf');
                    syncPropertyReportFormData('formReportProperty', 'formReportPropertyPdf');
                    $('#rppdf_incident_id').val(incidentId);
                    // Restore docNo after sync
                    $('#rppdf_report_no_display').val(thaiDocNo(docNo));
                    $('#rppdf_report_year').val(rptYear);
                    $('#rppdf_report_no_p2, #rppdf_report_no_p3, #rppdf_report_no_p4').text(thaiDocNo(docNo));
                    $('#rppdf_report_year_p2, #rppdf_report_year_p3, #rppdf_report_year_p4').text(rptYear);
                    var agencyName = $('#rppdf_agency_name').val() || '';
                    $('#rppdf_agency_name_p2, #rppdf_agency_name_p3, #rppdf_agency_name_p4').text(agencyName);
                    var pdfModal = new bootstrap.Modal(document.getElementById('modalReportPropertyPdf'));
                    pdfModal.show();
                },
                error: function() {
                    // No data yet — open PDF modal with empty form
                    $('#rppdf_incident_id').val(incidentId);
                    var pdfModal = new bootstrap.Modal(document.getElementById('modalReportPropertyPdf'));
                    pdfModal.show();
                }
            });
        });
    }

    // --- Save Report Property ---
    $('#btn_save_report_property').on('click', function() {
        savePropertyReport();
    });

    function savePropertyReport() {
        const incidentId = $('#rp_incident_id').val();
        if (!incidentId) {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
            return;
        }

        const formData = {};
        const formEl = document.getElementById('formReportProperty');
        const elements = formEl.elements;

        for (let i = 0; i < elements.length; i++) {
            const el = elements[i];
            if (!el.name || el.name === 'incident_id') continue;

            if (el.type === 'radio') {
                if (el.checked) formData[el.name] = el.value;
            } else if (el.type === 'checkbox') {
                if (!formData[el.name]) formData[el.name] = [];
                if (el.checked) formData[el.name].push(el.value);
            } else {
                if (el.name.endsWith('[]')) {
                    if (!formData[el.name]) formData[el.name] = [];
                    formData[el.name].push(el.value);
                } else {
                    formData[el.name] = el.value;
                }
            }
        }

        const payload = {
            incident_id: incidentId,
            report_type: 'property',
            form_data: formData
        };

        $.ajax({
            url: '/csims/api/incidentCheckList/savePropertyReport.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            beforeSend: function() {
                $('#btn_save_report_property').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    let currentCount = parseInt($('#rp_editCount').text()) || 0;
                    currentCount++;
                    $('#rp_editCount').text(currentCount);
                    $('#rp_editDate').text(new Date().toLocaleString('th-TH'));
                    $('#rp_editInfo').removeClass('d-none');

                    // เปลี่ยนปุ่ม PDF ของแถวนี้จากเทาเป็นม่วง
                    const $pdfCell = $(`.report-row[data-id="${incidentId}"]`).find('.pdf-cell');
                    $pdfCell.html(`<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="${incidentId}" data-pdf-type="01" data-pdf-location="" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>`);

                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: response.message,
                        confirmButtonColor: '#0d6efd'
                    }).then(() => {
                        bootstrap.Modal.getInstance(document.getElementById('modalReportProperty'))?.hide();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
            },
            complete: function() {
                $('#btn_save_report_property').prop('disabled', false);
            }
        });
    }

    // --- Save Function ---
    function saveLifeReport(reportType, formId, prefix) {
        const incidentId = $(`#${prefix}_incident_id`).val();
        if (!incidentId) {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
            return;
        }

        // Collect form data as object
        const formData = {};
        const formEl = document.getElementById(formId);
        const elements = formEl.elements;

        for (let i = 0; i < elements.length; i++) {
            const el = elements[i];
            if (!el.name || el.name === 'incident_id') continue;

            if (el.type === 'radio') {
                if (el.checked) formData[el.name] = el.value;
            } else if (el.type === 'checkbox') {
                if (!formData[el.name]) formData[el.name] = [];
                if (el.checked) formData[el.name].push(el.value);
            } else {
                // Handle array fields (name ends with [])
                if (el.name.endsWith('[]')) {
                    if (!formData[el.name]) formData[el.name] = [];
                    formData[el.name].push(el.value);
                } else {
                    formData[el.name] = el.value;
                }
            }
        }

        const payload = {
            incident_id: incidentId,
            report_type: reportType,
            form_data: formData
        };

        $.ajax({
            url: '/csims/api/incidentCheckList/saveLifeReport.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            beforeSend: function() {
                $(`#btn_save_report_life_${reportType === 'indoor' ? 'indoor' : 'outdoor'}`).prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // อัพเดทจำนวนครั้งที่แก้ไข
                    let currentCount = parseInt($(`#${prefix}_editCount`).text()) || 0;
                    currentCount++;
                    $(`#${prefix}_editCount`).text(currentCount);
                    $(`#${prefix}_editDate`).text(new Date().toLocaleString('th-TH'));
                    $(`#${prefix}_editInfo`).removeClass('d-none');

                    // เปลี่ยนปุ่ม PDF ของแถวนี้จากเทาเป็นม่วง
                    const $pdfCell = $(`.report-row[data-id="${incidentId}"]`).find('.pdf-cell');
                    const loc = $(`.report-row[data-id="${incidentId}"]`).data('location-type') || reportType;
                    $pdfCell.html(`<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="${incidentId}" data-pdf-type="02" data-pdf-location="${loc}" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>`);

                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: response.message,
                        confirmButtonColor: '#0d6efd'
                    }).then(() => {
                        const modalId = reportType === 'indoor' ? 'modalReportLifeIndoor' : 'modalReportLifeOutdoor';
                        bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
            },
            complete: function() {
                $(`#btn_save_report_life_${reportType === 'indoor' ? 'indoor' : 'outdoor'}`).prop('disabled', false);
            }
        });
    }

    // --- PDF Button ---
    $(document).on('click', '.btn-draft-pdf', function(e) {
        e.stopPropagation();
        e.preventDefault();
        const id = $(this).data('pdf-id');
        const type = $(this).data('pdf-type');
        const location = $(this).data('pdf-location');
        if (type == '01') {
            window.open('/csims/api/incidentCheckList/gen_pdf_property_report_html.php?incident_id=' + id, '_blank');
        } else if (type == '02' && location === 'indoor') {
            window.open('/csims/api/incidentCheckList/gen_pdf_life_report_indoor_html.php?incident_id=' + id, '_blank');
        } else if (type == '02' && location === 'outdoor') {
            window.open('/csims/api/incidentCheckList/gen_pdf_life_report_outdoor_html.php?incident_id=' + id, '_blank');
        } else if (type == '03' && location === 'indoor') {
            window.open('/csims/api/incidentCheckList/gen_pdf_bomb_report_indoor_html.php?incident_id=' + id, '_blank');
        } else if (type == '03' && location === 'outdoor') {
            window.open('/csims/api/incidentCheckList/gen_pdf_bomb_report_outdoor_html.php?incident_id=' + id, '_blank');
        } else if (type == '04') {
            window.open('/csims/api/incidentCheckList/gen_pdf_fire_report_html.php?incident_id=' + id, '_blank');
        } else if (type == '05') {
            window.open('/csims/api/incidentCheckList/gen_pdf_traffic_report_html.php?incident_id=' + id, '_blank');
        } else if (type == '07') {
            window.open('/csims/api/incidentCheckList/gen_pdf_scene_evidence_report_html.php?incident_id=' + id, '_blank');
        } else if (type == '08') {
            window.open('/csims/api/incidentCheckList/gen_pdf_person_evidence_report_html.php?incident_id=' + id, '_blank');
        } else if (type == '06') {
            window.open('/csims/api/incidentCheckList/gen_pdf_fingerprint_report_html.php?incident_id=' + id, '_blank');
        }
    });

    // --- Word (docx) Button ---
    $(document).on('click', '.btn-draft-doc', function(e) {
        e.stopPropagation();
        e.preventDefault();
        const id = $(this).data('doc-id');
        const type = $(this).data('doc-type');
        const location = $(this).data('doc-location') || '';
        const reportNo = $(this).closest('.report-row').data('report-no') || '';
        // ร่างรายงาน = ไฟล์เดียว (เนื้อหา + รูป) เลขหน้านับรวมทั้งฉบับ
        const url = '/csims/api/incidentCheckList/gen_word_report.php?incident_id=' + id +
            '&type=' + encodeURIComponent(type) + '&location=' + encodeURIComponent(location) +
            '&report_no=' + encodeURIComponent(reportNo) + '&photos=all';
        window.location.href = url;
    });

    // --- บันทึกข้อความ (Word .doc) Button ---
    $(document).on('click', '.btn-draft-memo', function(e) {
        e.stopPropagation();
        e.preventDefault();
        const id = $(this).data('memo-id');
        const type = $(this).data('memo-type');
        const location = $(this).data('memo-location') || '';
        const reportNo = $(this).closest('.report-row').data('report-no') || '';
        const url = '/csims/api/incidentCheckList/gen_memo_report.php?incident_id=' + id +
            '&type=' + encodeURIComponent(type) + '&location=' + encodeURIComponent(location) +
            '&report_no=' + encodeURIComponent(reportNo);
        window.location.href = url;
    });

    // --- Sync DOCX cell to mirror the active PDF cell ---
    // เมื่อ handler บันทึกรายงานเปลี่ยนปุ่ม PDF (.pdf-cell) เป็นสีม่วงแล้ว
    // ให้ปุ่ม DOCX (.doc-cell) ในแถวเดียวกัน active ตามทันที (ไม่ต้องรีโหลด)
    function syncDocCell($row) {
        const $pdfBtn = $row.find('.pdf-cell .btn-draft-pdf');
        if (!$pdfBtn.length) return;
        const id = $pdfBtn.data('pdf-id');
        const type = $pdfBtn.data('pdf-type');
        const location = $pdfBtn.data('pdf-location') || '';
        if (!$row.find('.doc-cell .btn-draft-doc').length) {
            $row.find('.doc-cell').html(`<button type="button" class="btn btn-sm btn-draft-doc" style="background:#2563eb; box-shadow:0 2px 6px rgba(37,99,235,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-doc-id="${id}" data-doc-type="${type}" data-doc-location="${location}" title="ดาวน์โหลดร่างรายงาน Word (เนื้อหาและรูปในไฟล์เดียวกัน เลขหน้านับรวมทั้งหมด)"><i class="fas fa-file-word me-1"></i>DOCX</button>`);
        }
        if (!$row.find('.memo-cell .btn-draft-memo').length) {
            $row.find('.memo-cell').html(`<button type="button" class="btn btn-sm btn-draft-memo" style="background:#0891b2; box-shadow:0 2px 6px rgba(8,145,178,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; transition:all .2s ease;" data-memo-id="${id}" data-memo-type="${type}" data-memo-location="${location}" title="ดาวน์โหลดบันทึกข้อความ (Word)"><i class="fas fa-file-word me-1"></i>บันทึกข้อความ</button>`);
        }
    }
    const _tableBody = document.getElementById('table_body');
    if (_tableBody && window.MutationObserver) {
        const _docObserver = new MutationObserver(function() {
            $('#table_body .report-row').each(function() { syncDocCell($(this)); });
        });
        _docObserver.observe(_tableBody, { childList: true, subtree: true });
    }

    // --- Open Person Evidence Report Modal & Load Data ---
    function openPersonEvidenceReportModal(incidentId, docNo, reportNo) {
        const prefix = 'pe';
        const modalId = 'modalReportPersonEvidence';
        const formId = 'formReportPersonEvidence';

        // Reset form & edit info
        document.getElementById(formId).reset();
        $(`#${prefix}_incident_id`).val(incidentId);
        $(`#${prefix}_doc_no_display`).text(thaiDocNo(docNo));

        // Set report_no in PDF modal header (ใช้เลขที่เอกสารเต็ม)
        var currentYear = '<?= substr((date('Y') + 543), -2) ?>';
        $('#pepdf_report_no_display').val(thaiDocNo(docNo));
        $('#pepdf_report_year').val(currentYear);
        $('#pepdf_report_no_p2').text(thaiDocNo(docNo));
        $('#pepdf_report_year_p2').text(currentYear);

        $(`#${prefix}_editInfo`).addClass('d-none');
        $(`#${prefix}_editCount`).text('0');
        $(`#${prefix}_editDate`).text('-');

        // Reset inspector rows inside THIS modal only
        const $modal = $(`#${modalId}`);
        const $container = $modal.find(`#${prefix}_inspector_container`);
        $container.find('.rp-inspector-row').not(':first').remove();
        if ($container.find('.rp-inspector-row').length === 0) {
            $container.append(rpBuildInspectorRow('1', '', '', prefix));
        }
        $container.find('.rp-inspector-select').prop('selectedIndex', 0);
        $container.find('.rp-inspector-position').val('');

        // Reset dynamic rows in standard form
        $('#pe_persons_container .pe-person-row').not(':first').remove();
        $('#pe_person_info_container .pe-person-info-row').not(':first').remove();
        $('#pe_evidence_container .pe-evidence-row').not(':first').remove();

        // Reset PDF form & its dynamic containers
        var pdfFormEl = document.getElementById('formReportPersonEvidencePdf');
        if (pdfFormEl) pdfFormEl.reset();
        // Restore after reset
        $('#pepdf_report_no_display').val(thaiDocNo(docNo));
        $('#pepdf_report_year').val(currentYear);
        $('#pepdf_report_no_p2').text(thaiDocNo(docNo));
        $('#pepdf_report_year_p2').text(currentYear);
        $('#pepdf_persons_container .pepdf-person-row').not(':first').remove();
        $('#pepdf_person_info_container .pepdf-person-info-block').not(':first').remove();
        $('#pepdf_evidence_container .pepdf-evidence-row').not(':first').remove();
        $('#pepdf_inspector_container .pepdf-inspector-row').not(':first').remove();

        // Load users for inspector dropdowns then load data
        rpLoadUsers(function() {
            rpPopulateAllInspectorSelects();
            rpInitSelect2InModal($modal);

            // Load data from API
            $.ajax({
                url: '/csims/api/incidentCheckList/getPersonEvidenceReportData.php',
                type: 'GET',
                data: { incident_id: incidentId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        // Add extra dynamic rows if needed BEFORE prefill
                        peEnsureDynamicRows(response.data);

                        // Add extra inspector rows if needed
                        const inspectorNames = response.data['pe_inspector_name[]'];
                        if (Array.isArray(inspectorNames) && inspectorNames.length > 1) {
                            for (let i = 1; i < inspectorNames.length; i++) {
                                const count = $container.find('.rp-inspector-row').length + 1;
                                $container.append(rpBuildInspectorRow(count.toString(), '', '', prefix));
                            }
                        }

                        prefillReportForm(prefix, response.data, $(`#${formId}`));
                        rpInitSelect2InModal($modal);
                    }
                    // แสดงจำนวนครั้งที่แก้ไข
                    // console.log(response.data,"<--------response.data");
                    let agencyName = response.data.pe_agency_name;
                    console.log(agencyName,"<-------agencyName");
                    const countEdit = response.count_report || 0;
                    if (countEdit > 0) {
                        $(`#${prefix}_editInfo`).removeClass('d-none');
                        $(`#${prefix}_editCount`).text(countEdit);
                        $(`#${prefix}_editDate`).text(response.edit_date || '-');
                    }

                    // Sync data to PDF form and open PDF modal as default
                    syncPersonEvidenceInspectorRows('toPdf');
                    syncPersonEvidenceDynamicRows('toPdf');
                    syncPersonEvidenceFormData('formReportPersonEvidence', 'formReportPersonEvidencePdf');
                    $('#pepdf_incident_id').val(incidentId);

                    // แสดง report_no ในหัว modal (ใช้เลขที่เอกสารเต็ม)
                    $(`#${prefix}_report_no_display`).text(thaiDocNo(docNo));
                    $('#pepdf_report_no_display').val(thaiDocNo(docNo));
                    $('#pepdf_report_no_display_p2').val(thaiDocNo(docNo));
                    $('#pepdf_agency_name_2').val(agencyName);
                    // report_year from data or current year
                    const peRptYear = (response.data && response.data['pe_report_year']) || currentYear;
                    $('#pepdf_report_year').val(peRptYear);
                    $('#pepdf_report_year_p2').text(peRptYear);

                    const pdfModal = new bootstrap.Modal(document.getElementById('modalReportPersonEvidencePdf'));
                    pdfModal.show();
                },
                error: function() {
                    // No data yet — open PDF modal with empty form
                    $('#pepdf_incident_id').val(incidentId);
                    const pdfModal = new bootstrap.Modal(document.getElementById('modalReportPersonEvidencePdf'));
                    pdfModal.show();
                }
            });
        });
    }

    /**
     * Ensure the standard form has enough dynamic rows for the data
     */
    function peEnsureDynamicRows(data) {
        // Persons (Section 1)
        var personNames = data['pe_person_name[]'];
        if (Array.isArray(personNames) && personNames.length > 1) {
            for (var i = 1; i < personNames.length; i++) {
                peAddPersonRow();
            }
        }
        // Person info (Section 3.1)
        var piFullnames = data['pe_pi_fullname[]'];
        if (Array.isArray(piFullnames) && piFullnames.length > 1) {
            for (var j = 1; j < piFullnames.length; j++) {
                peAddPersonInfoRow();
            }
        }
        // Evidence (Section 3.2)
        var evDetails = data['pe_ev_detail[]'];
        if (Array.isArray(evDetails) && evDetails.length > 1) {
            for (var k = 1; k < evDetails.length; k++) {
                peAddEvidenceRow();
            }
        }
    }

    // --- Save Report Person Evidence (standard form) ---
    $('#btn_save_report_person_evidence').on('click', function() {
        savePersonEvidenceReport('formReportPersonEvidence', 'pe');
    });

    function savePersonEvidenceReport(formId, prefix) {
        const incidentId = $(`#${prefix}_incident_id`).val();
        if (!incidentId) {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
            return;
        }

        const formData = {};
        const formEl = document.getElementById(formId);
        const elements = formEl.elements;

        for (let i = 0; i < elements.length; i++) {
            const el = elements[i];
            if (!el.name || el.name === 'incident_id') continue;

            if (el.type === 'radio') {
                if (el.checked) formData[el.name] = el.value;
            } else if (el.type === 'checkbox') {
                if (!formData[el.name]) formData[el.name] = [];
                if (el.checked) formData[el.name].push(el.value);
            } else {
                if (el.name.endsWith('[]')) {
                    if (!formData[el.name]) formData[el.name] = [];
                    formData[el.name].push(el.value);
                } else {
                    formData[el.name] = el.value;
                }
            }
        }

        const payload = {
            incident_id: incidentId,
            form_data: formData
        };

        $.ajax({
            url: '/csims/api/incidentCheckList/savePersonEvidenceReport.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            beforeSend: function() {
                $(`#btn_save_report_person_evidence`).prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    let currentCount = parseInt($(`#${prefix}_editCount`).text()) || 0;
                    currentCount++;
                    $(`#${prefix}_editCount`).text(currentCount);
                    $(`#${prefix}_editDate`).text(new Date().toLocaleString('th-TH'));
                    $(`#${prefix}_editInfo`).removeClass('d-none');

                    // เปลี่ยนปุ่ม PDF จากเทาเป็นม่วง
                    const $pdfCell = $(`.report-row[data-id="${incidentId}"]`).find('.pdf-cell');
                    $pdfCell.html(`<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="${incidentId}" data-pdf-type="08" data-pdf-location="" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>`);

                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: response.message,
                        confirmButtonColor: '#0d6efd'
                    }).then(() => {
                        bootstrap.Modal.getInstance(document.getElementById('modalReportPersonEvidence'))?.hide();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
            },
            complete: function() {
                $(`#btn_save_report_person_evidence`).prop('disabled', false);
            }
        });
    }

    // --- Open Fingerprint Report Modal & Load Data ---
    function openFingerprintReportModal(incidentId, docNo, reportNo) {
        const prefix = 'rlf';
        const modalId = 'modalReportFingerprint';
        const formId = 'formReportFingerprint';

        // Reset form & edit info
        document.getElementById(formId).reset();
        $(`#${prefix}_incident_id`).val(incidentId);
        $(`#${prefix}_doc_no_display`).text(thaiDocNo(docNo));

        // Set report_no in PDF modal header (ใช้เลขที่เอกสารเต็ม)
        var currentYear = '<?= substr((date('Y') + 543), -2) ?>';
        $('#rlfpdf_report_no_display').val(thaiDocNo(docNo));
        $('#rlfpdf_report_year').val(currentYear);
        $('#rlfpdf_report_no_display_p2').val(thaiDocNo(docNo));
        $('#rlfpdf_report_year_p2').val(currentYear);

        $(`#${prefix}_editInfo`).addClass('d-none');
        $(`#${prefix}_editCount`).text('0');
        $(`#${prefix}_editDate`).text('-');

        // Reset inspector rows inside THIS modal only
        const $modal = $(`#${modalId}`);
        const $container = $modal.find(`#${prefix}_inspector_container`);
        $container.find('.rp-inspector-row').not(':first').remove();
        if ($container.find('.rp-inspector-row').length === 0) {
            $container.append(rpBuildInspectorRow('4.1', '', '', prefix));
        }
        $container.find('.rp-inspector-select').prop('selectedIndex', 0);
        $container.find('.rp-inspector-position').val('');
        

        // Reset dynamic rows
        $('#rlf_evidence_sets_container .rlf-evidence-set').not(':first').remove();
        $('#rlf_evidence_sets_container .rlf-evidence-set:first .rlf-evidence-card').not(':first').remove();
        $('#rlf_method_container .rlf-method-row').not(':first').remove();

        // Reset PDF form & its dynamic containers
        var pdfFormEl = document.getElementById('formReportFingerprintPdf');
        if (pdfFormEl) pdfFormEl.reset();
        // Restore report fields cleared by reset
        $('#rlfpdf_report_no_display').val(thaiDocNo(docNo));
        $('#rlfpdf_report_year').val(currentYear);
        $('#rlfpdf_report_no_display_p2').val(thaiDocNo(docNo));
        $('#rlfpdf_report_year_p2').val(currentYear);
        $('#rlfpdf_evidence_container .rlfpdf-ev-card').not(':first').remove();
        $('#rlfpdf_method_container .rlfpdf-method-row').not(':first').remove();
        $('#rlfpdf_inspector_container .rlfpdf-inspector-row').not(':first').remove();

        // Load users for inspector dropdowns then load data
        rpLoadUsers(function() {
            rpPopulateAllInspectorSelects();
            rpInitSelect2InModal($modal);

            // Load data from API
            $.ajax({
                url: '/csims/api/incidentCheckList/getFingerprintReportData.php',
                type: 'GET',
                data: { incident_id: incidentId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        // Ensure dynamic rows in standard form
                        rlfEnsureDynamicRows(response.data);

                        // Add extra inspector rows if needed
                        const inspectorNames = response.data['rlf_inspector_name[]'];
                        if (Array.isArray(inspectorNames) && inspectorNames.length > 1) {
                            for (let i = 1; i < inspectorNames.length; i++) {
                                const count = $container.find('.rp-inspector-row').length + 1;
                                $container.append(rpBuildInspectorRow('4.' + count, '', '', prefix));
                            }
                        }
                        prefillReportForm(prefix, response.data, $(`#${formId}`));
                        rpInitSelect2InModal($modal);
                    }
                    // แสดงจำนวนครั้งที่แก้ไข
                    let agencyNameFinger = response.data.rlf_agency_name;
                    $('#rlfpdf_agency_name_2').val(agencyNameFinger);
                    const countEdit = response.count_report || 0;
                    if (countEdit > 0) {
                        $(`#${prefix}_editInfo`).removeClass('d-none');
                        $(`#${prefix}_editCount`).text(countEdit);
                        $(`#${prefix}_editDate`).text(response.edit_date || '-');
                    }

                    // Sync data to PDF form and open PDF modal as default
                    syncFingerprintReportInspectorRows('toPdf');
                    syncFingerprintReportDynamicRows('toPdf');
                    syncFingerprintReportFormData('formReportFingerprint', 'formReportFingerprintPdf');
                    $('#rlfpdf_incident_id').val(incidentId);

                    // แสดง report_no ในหัว modal (ใช้เลขที่เอกสารเต็ม)
                    $('#rlfpdf_report_no_display').val(thaiDocNo(docNo));
                    $('#rlfpdf_report_no_display_p2').val(thaiDocNo(docNo));
                    $('#rlf_report_no_display_pdf').val(thaiDocNo(docNo));

                    // agency name
                    var agencyName = (response.data && response.data['rlf_agency_name']) || response.agency_name || '';
                    $('#rlfpdf_agency_name').val(agencyName);
                    $('#rlf_agency_name').val(agencyName);

                    // Set report year (พ.ศ. 2 หลักท้าย) - ใช้ค่าจาก response หรือ default เป็นปีปัจจุบัน
                    var reportYear = (response.data && response.data['rlf_report_year_pdf']) || '';
                    if (!reportYear) {
                        var buddhistYear = new Date().getFullYear() + 543;
                        reportYear = String(buddhistYear).slice(-2);
                    }
                    $('#rlfpdf_report_year').val(reportYear);
                    $('#rlfpdf_report_year_p2').text(reportYear);
                    $('#rlf_report_year_pdf').val(reportYear);

                    const pdfModal = new bootstrap.Modal(document.getElementById('modalReportFingerprintPdf'));
                    pdfModal.show();
                },
                error: function() {
                    // No data yet — open PDF modal with empty form
                    $('#rlfpdf_incident_id').val(incidentId);

                    // Set default report year (พ.ศ. 2 หลักท้าย)
                    var buddhistYear = new Date().getFullYear() + 543;
                    var reportYear = String(buddhistYear).slice(-2);
                    $('#rlfpdf_report_year').val(reportYear);
                    $('#rlfpdf_report_year_p2').text(reportYear);
                    $('#rlf_report_year_pdf').val(reportYear);

                    const pdfModal = new bootstrap.Modal(document.getElementById('modalReportFingerprintPdf'));
                    pdfModal.show();
                }
            });
        });
    }

    // ===== Ensure dynamic rows exist before prefill (fingerprint) =====
    function rlfEnsureDynamicRows(data) {
        // Evidence items
        var evDesc = data['rlf_ev_description[]'];
        if (Array.isArray(evDesc) && evDesc.length > 1) {
            var container = document.getElementById('rlf_evidence_sets_container');
            if (container) {
                var firstSet = container.querySelector('.rlf-evidence-set');
                if (firstSet) {
                    var evContainer = firstSet.querySelector('.rlf-evidence-container') || firstSet;
                    var cards = evContainer.querySelectorAll('.rlf-evidence-card');
                    for (var i = cards.length; i < evDesc.length; i++) {
                        var addBtn = firstSet.querySelector('.rlf-add-evidence-btn');
                        if (addBtn) addBtn.click();
                    }
                }
            }
        }
        // Methods
        var methods = data['rlf_method_name[]'];
        if (Array.isArray(methods) && methods.length > 1) {
            var mContainer = document.getElementById('rlf_method_container');
            if (mContainer) {
                var mRows = mContainer.querySelectorAll('.rlf-method-row');
                for (var j = mRows.length; j < methods.length; j++) {
                    var mBtn = document.getElementById('rlf_add_method_btn');
                    if (mBtn) mBtn.click();
                }
            }
        }
    }

    // ===== Sync form data between standard and PDF (fingerprint) =====
    function syncFingerprintReportFormData(fromFormId, toFormId) {
        var fromForm = document.getElementById(fromFormId);
        var toForm = document.getElementById(toFormId);
        if (!fromForm || !toForm) return;

        var fromEls = fromForm.querySelectorAll('input, select, textarea');
        var dataMap = {};

        fromEls.forEach(function(el) {
            var name = el.name;
            if (!name || name === 'incident_id') return;
            if (el.type === 'file') return;

            if (el.type === 'checkbox') {
                if (!dataMap[name]) dataMap[name] = { type: 'checkbox', values: [] };
                if (el.checked) dataMap[name].values.push(el.value);
            } else if (el.type === 'radio') {
                if (el.checked) dataMap[name] = { type: 'radio', value: el.value };
            } else {
                if (!dataMap[name]) dataMap[name] = { type: 'value', values: [] };
                dataMap[name].values.push(el.value);
            }
        });

        Object.keys(dataMap).forEach(function(name) {
            var info = dataMap[name];
            var toEls = toForm.querySelectorAll('[name="' + name + '"]');
            if (toEls.length === 0) return;

            if (info.type === 'checkbox') {
                toEls.forEach(function(el) { el.checked = info.values.includes(el.value); });
            } else if (info.type === 'radio') {
                toEls.forEach(function(el) { el.checked = (el.value === info.value); });
            } else {
                toEls.forEach(function(el, idx) {
                    if (idx < info.values.length) el.value = info.values[idx];
                });
            }
        });
    }

    // ===== Sync inspector rows between standard and PDF (fingerprint) =====
    function syncFingerprintReportInspectorRows(direction) {
        if (direction === 'toPdf') {
            var stdRows = $('#rlf_inspector_container .rp-inspector-row');
            var pdfContainer = document.getElementById('rlfpdf_inspector_container');
            // Ensure PDF has enough rows
            while (pdfContainer.querySelectorAll('.rlfpdf-inspector-row').length < stdRows.length) {
                rlfpdfAddInspectorRow();
            }
            // Remove extra PDF rows
            var currentPdf = pdfContainer.querySelectorAll('.rlfpdf-inspector-row');
            for (var i = stdRows.length; i < currentPdf.length; i++) {
                currentPdf[i].remove();
            }
            rlfpdfRenumberInspectors();
            // Copy name+position from standard → PDF
            stdRows.each(function(idx) {
                var pdfRow = pdfContainer.querySelectorAll('.rlfpdf-inspector-row')[idx];
                if (!pdfRow) return;
                var nameVal = $(this).find('.rp-inspector-select').val() || '';
                var posVal = $(this).find('.rp-inspector-position').val() || '';
                var pdfSelect = $(pdfRow).find('.rp-inspector-select');
                if (pdfSelect.length) {
                    if (nameVal && pdfSelect.find('option[value="' + nameVal + '"]').length === 0) {
                        pdfSelect.append('<option value="' + nameVal + '">' + nameVal + '</option>');
                    }
                    pdfSelect.val(nameVal);
                    if (pdfSelect.hasClass('select2-hidden-accessible')) pdfSelect.trigger('change.select2');
                }
                $(pdfRow).find('.rp-inspector-position').val(posVal);
            });
        } else {
            // toStd: PDF → Standard
            var pdfContainer2 = document.getElementById('rlfpdf_inspector_container');
            var pdfRows = pdfContainer2.querySelectorAll('.rlfpdf-inspector-row');
            var $container = $('#rlf_inspector_container');
            while ($container.find('.rp-inspector-row').length < pdfRows.length) {
                var count = $container.find('.rp-inspector-row').length + 1;
                $container.append(rpBuildInspectorRow('4.' + count, '', '', 'rlf'));
            }
            var stdRowsNow = $container.find('.rp-inspector-row');
            for (var j = pdfRows.length; j < stdRowsNow.length; j++) {
                $(stdRowsNow[j]).remove();
            }
            pdfRows.forEach(function(pdfRow, idx) {
                var stdRow = $container.find('.rp-inspector-row').eq(idx);
                if (!stdRow.length) return;
                var pdfName = $(pdfRow).find('.rp-inspector-select').val() || '';
                var pdfPos = $(pdfRow).find('.rp-inspector-position').val() || '';
                var stdSelect = stdRow.find('.rp-inspector-select');
                if (pdfName && stdSelect.find('option[value="' + pdfName + '"]').length === 0) {
                    stdSelect.append('<option value="' + pdfName + '">' + pdfName + '</option>');
                }
                stdSelect.val(pdfName);
                if (stdSelect.hasClass('select2-hidden-accessible')) stdSelect.trigger('change.select2');
                stdRow.find('.rp-inspector-position').val(pdfPos);
            });
        }
    }

    // ===== Sync dynamic rows (evidence, methods) between standard and PDF (fingerprint) =====
    function syncFingerprintReportDynamicRows(direction) {
        if (direction === 'toPdf') {
            // Sync evidence items: count std rows, ensure PDF has same count
            var stdEvCards = document.querySelectorAll('#rlf_evidence_sets_container .rlf-evidence-card');
            var pdfEvContainer = document.getElementById('rlfpdf_evidence_container');
            if (pdfEvContainer) {
                var pdfEvCards = pdfEvContainer.querySelectorAll('.rlfpdf-ev-card');
                while (pdfEvCards.length < stdEvCards.length) {
                    document.getElementById('rlfpdf_add_evidence').click();
                    pdfEvCards = pdfEvContainer.querySelectorAll('.rlfpdf-ev-card');
                }
            }
            // Sync methods
            var stdMethodRows = document.querySelectorAll('#rlf_method_container .rlf-method-row');
            var pdfMethodContainer = document.getElementById('rlfpdf_method_container');
            if (pdfMethodContainer) {
                var pdfMethodRows = pdfMethodContainer.querySelectorAll('.rlfpdf-method-row');
                while (pdfMethodRows.length < stdMethodRows.length) {
                    document.getElementById('rlfpdf_add_method').click();
                    pdfMethodRows = pdfMethodContainer.querySelectorAll('.rlfpdf-method-row');
                }
            }
        } else {
            // toPdf → toStd: ensure std has enough rows
            var pdfEvCards2 = document.querySelectorAll('#rlfpdf_evidence_container .rlfpdf-ev-card');
            var stdEvContainer = document.querySelector('#rlf_evidence_sets_container .rlf-evidence-set');
            if (stdEvContainer) {
                var stdCards = stdEvContainer.querySelectorAll('.rlf-evidence-card');
                while (stdCards.length < pdfEvCards2.length) {
                    var addBtn = stdEvContainer.querySelector('.rlf-add-evidence-btn');
                    if (addBtn) { addBtn.click(); stdCards = stdEvContainer.querySelectorAll('.rlf-evidence-card'); }
                    else break;
                }
            }
            var pdfMR = document.querySelectorAll('#rlfpdf_method_container .rlfpdf-method-row');
            var stdMC = document.getElementById('rlf_method_container');
            if (stdMC) {
                var stdMR = stdMC.querySelectorAll('.rlf-method-row');
                while (stdMR.length < pdfMR.length) {
                    var mBtn = document.getElementById('rlf_add_method_btn');
                    if (mBtn) { mBtn.click(); stdMR = stdMC.querySelectorAll('.rlf-method-row'); }
                    else break;
                }
            }
        }
    }

    // Switch from PDF form back to standard form (fingerprint)
    function switchFingerprintReportToStandard() {
        syncFingerprintReportInspectorRows('toStd');
        syncFingerprintReportDynamicRows('toStd');
        syncFingerprintReportFormData('formReportFingerprintPdf', 'formReportFingerprint');
        $('#rlf_incident_id').val($('#rlfpdf_incident_id').val());

        var pdfEl = document.getElementById('modalReportFingerprintPdf');
        var pdfInstance = bootstrap.Modal.getInstance(pdfEl);
        if (pdfInstance) pdfInstance.hide();

        pdfEl.addEventListener('hidden.bs.modal', function handler() {
            pdfEl.removeEventListener('hidden.bs.modal', handler);
            var stdEl = document.getElementById('modalReportFingerprint');
            stdEl.addEventListener('shown.bs.modal', function onShown() {
                stdEl.removeEventListener('shown.bs.modal', onShown);
                rpInitSelect2InModal($('#modalReportFingerprint'));
            });
            bootstrap.Modal.getOrCreateInstance(stdEl).show();
        });
    }

    // Switch from standard form to PDF form (fingerprint)
    function switchFingerprintReportToPdfForm() {
        syncFingerprintReportInspectorRows('toPdf');
        syncFingerprintReportDynamicRows('toPdf');
        syncFingerprintReportFormData('formReportFingerprint', 'formReportFingerprintPdf');
        $('#rlfpdf_incident_id').val($('#rlf_incident_id').val());
        restoreDraftPdfReportNoFromDocDisplay('#rlf_doc_no_display', ['#rlfpdf_report_no_display', '#rlfpdf_report_no_display_p2']);

        var stdEl = document.getElementById('modalReportFingerprint');
        var stdModal = bootstrap.Modal.getInstance(stdEl);
        if (stdModal) stdModal.hide();

        stdEl.addEventListener('hidden.bs.modal', function onHidden() {
            stdEl.removeEventListener('hidden.bs.modal', onHidden);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalReportFingerprintPdf')).show();
        });
    }

    // --- Switch toggles for Fingerprint Report (bound here because functions are inside document.ready) ---
    $('#switchFpReportToStd').on('change', function() {
        if (!this.checked) {
            this.checked = true;
            switchFingerprintReportToStandard();
        }
    });
    $('#switchFingerprintReportToPdf').on('change', function() {
        if (this.checked) {
            this.checked = false;
            switchFingerprintReportToPdfForm();
        }
    });

    // --- Save Fingerprint Report ---
    $('#btn_save_report_fingerprint').on('click', function() {
        saveFingerprintReport('formReportFingerprint', 'rlf');
    });
    $('#btn_save_report_fingerprint_pdf').on('click', function() {
        // Sync PDF → standard form ก่อนบันทึก
        syncFingerprintReportInspectorRows('toStandard');
        syncFingerprintReportDynamicRows('toStandard');
        syncFingerprintReportFormData('formReportFingerprintPdf', 'formReportFingerprint');
        $('#rlf_incident_id').val($('#rlfpdf_incident_id').val());
        saveFingerprintReport('formReportFingerprint', 'rlf', true);
    });

    function saveFingerprintReport(formId, prefix, fromPdf) {
        const incidentId = $(`#${prefix}_incident_id`).val();
        if (!incidentId) {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
            return;
        }

        const formData = {};
        const formEl = document.getElementById(formId);
        const elements = formEl.elements;

        for (let i = 0; i < elements.length; i++) {
            const el = elements[i];
            if (!el.name || el.name === 'incident_id') continue;

            if (el.type === 'radio') {
                if (el.checked) formData[el.name] = el.value;
            } else if (el.type === 'checkbox') {
                if (!formData[el.name]) formData[el.name] = [];
                if (el.checked) formData[el.name].push(el.value);
            } else {
                if (el.name.endsWith('[]')) {
                    if (!formData[el.name]) formData[el.name] = [];
                    formData[el.name].push(el.value);
                } else {
                    formData[el.name] = el.value;
                }
            }
        }

        const payload = {
            incident_id: incidentId,
            form_data: formData
        };

        const saveBtnId = fromPdf ? 'btn_save_report_fingerprint_pdf' : 'btn_save_report_fingerprint';

        $.ajax({
            url: '/csims/api/incidentCheckList/saveFingerprintReport.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            beforeSend: function() {
                $(`#${saveBtnId}`).prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    let currentCount = parseInt($(`#${prefix}_editCount`).text()) || 0;
                    currentCount++;
                    $(`#${prefix}_editCount`).text(currentCount);
                    $(`#${prefix}_editDate`).text(new Date().toLocaleString('th-TH'));
                    $(`#${prefix}_editInfo`).removeClass('d-none');

                    // เปลี่ยนปุ่ม PDF จากเทาเป็นม่วง
                    const $pdfCell = $(`.report-row[data-id="${incidentId}"]`).find('.pdf-cell');
                    $pdfCell.html(`<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="${incidentId}" data-pdf-type="06" data-pdf-location="" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>`);

                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: response.message,
                        confirmButtonColor: '#0d6efd'
                    }).then(() => {
                        const closeModalId = fromPdf ? 'modalReportFingerprintPdf' : 'modalReportFingerprint';
                        bootstrap.Modal.getInstance(document.getElementById(closeModalId))?.hide();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
            },
            complete: function() {
                $(`#${saveBtnId}`).prop('disabled', false);
            }
        });
    }

    function openFireReportModal(incidentId, docNo, reportNo) {
        const prefix = 'rf';
        const modalId = 'modalReportFire';
        const formId = 'formReportFire';

        // Reset form & edit info
        document.getElementById(formId).reset();
        $(`#${prefix}_incident_id`).val(incidentId);
        $(`#${prefix}_doc_no_display`).text(thaiDocNo(docNo));

        // Set report_no in PDF modal header (ใช้เลขที่เอกสารเต็ม)
        var currentYear = '<?= substr((date('Y') + 543), -2) ?>';
        $('#rfpdf_report_no_display').val(thaiDocNo(docNo));
        $('#rfpdf_report_no_display_2').val(thaiDocNo(docNo));
        $('#rfpdf_report_no_display_3').val(thaiDocNo(docNo));
        $('#rfpdf_report_year').val(currentYear);

        $(`#${prefix}_editInfo`).addClass('d-none');
        $(`#${prefix}_editCount`).text('0');
        $(`#${prefix}_editDate`).text('-');

        // Reset inspector rows inside THIS modal only
        const $modal = $(`#${modalId}`);
        const $container = $modal.find(`#${prefix}_inspector_container`);
        $container.find('.rp-inspector-row').not(':first').remove();
        if ($container.find('.rp-inspector-row').length === 0) {
            $container.append(rpBuildInspectorRow('5.1', '', '', prefix));
        }
        $container.find('.rp-inspector-select').prop('selectedIndex', 0);
        $container.find('.rp-inspector-position').val('');

        // Load users for inspector dropdowns then load data
        rpLoadUsers(function() {
            rpPopulateAllInspectorSelects();
            rpInitSelect2InModal($modal);

            // Load data from API
            $.ajax({
                url: '/csims/api/incidentCheckList/getFireReportData.php',
                type: 'GET',
                data: { incident_id: incidentId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        // Add extra inspector rows if needed
                        const inspectorNames = response.data['rf_inspector_name[]'];
                        if (Array.isArray(inspectorNames) && inspectorNames.length > 1) {
                            for (let i = 1; i < inspectorNames.length; i++) {
                                const count = $container.find('.rp-inspector-row').length + 1;
                                $container.append(rpBuildInspectorRow('5.' + count, '', '', prefix));
                            }
                        }
                        prefillReportForm(prefix, response.data);
                        rpInitSelect2InModal($modal);
                    }
                    // แสดงจำนวนครั้งที่แก้ไข
                    const countEdit = response.count_report || 0;
                    if (countEdit > 0) {
                        $(`#${prefix}_editInfo`).removeClass('d-none');
                        $(`#${prefix}_editCount`).text(countEdit);
                        $(`#${prefix}_editDate`).text(response.edit_date || '-');
                    }
                    // console.log(response.data,"<--------response,data")
                    let agencyData = response.data.rf_agency_name
                    // Sync data to PDF form and open PDF modal as default
                    syncFireReportInspectorRows('toPdf');
                    syncFireReportFormData('formReportFire', 'formReportFirePdf');
                    $('#rfpdf_incident_id').val(incidentId);
                    // Restore docNo after sync
                    $('#rfpdf_report_no_display').val(thaiDocNo(docNo));
                    $('#rfpdf_report_no_display_2').val(thaiDocNo(docNo));
                    $('#rfpdf_report_no_display_3').val(thaiDocNo(docNo)); 
                    $('#rfpdf_agency_name_2').val(agencyData); 
                    $('#rfpdf_agency_name_3').val(agencyData); 
                    $('#rfpdf_report_year').val(currentYear);
                    const pdfModal = new bootstrap.Modal(document.getElementById('modalReportFirePdf'));
                    pdfModal.show();
                },
                error: function() {
                    // No data yet — open PDF modal with empty form
                    $('#rfpdf_incident_id').val(incidentId);
                    const pdfModal = new bootstrap.Modal(document.getElementById('modalReportFirePdf'));
                    pdfModal.show();
                }
            });
        });
    }

    // --- Save Report Fire ---
    $('#btn_save_report_fire').on('click', function() {
        saveFireReport('formReportFire', 'rf');
    });

    function saveFireReport(formId, prefix) {
        const incidentId = $(`#${prefix}_incident_id`).val();
        if (!incidentId) {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
            return;
        }

        const formData = {};
        const formEl = document.getElementById(formId);
        const elements = formEl.elements;

        for (let i = 0; i < elements.length; i++) {
            const el = elements[i];
            if (!el.name || el.name === 'incident_id') continue;

            if (el.type === 'radio') {
                if (el.checked) formData[el.name] = el.value;
            } else if (el.type === 'checkbox') {
                if (!formData[el.name]) formData[el.name] = [];
                if (el.checked) formData[el.name].push(el.value);
            } else {
                if (el.name.endsWith('[]')) {
                    if (!formData[el.name]) formData[el.name] = [];
                    formData[el.name].push(el.value);
                } else {
                    formData[el.name] = el.value;
                }
            }
        }

        const payload = {
            incident_id: incidentId,
            form_data: formData
        };

        $.ajax({
            url: '/csims/api/incidentCheckList/saveFireReport.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            beforeSend: function() {
                $(`#btn_save_report_fire`).prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    let currentCount = parseInt($(`#${prefix}_editCount`).text()) || 0;
                    currentCount++;
                    $(`#${prefix}_editCount`).text(currentCount);
                    $(`#${prefix}_editDate`).text(new Date().toLocaleString('th-TH'));
                    $(`#${prefix}_editInfo`).removeClass('d-none');

                    // เปลี่ยนปุ่ม PDF ของแถวนี้จากเทาเป็นม่วง
                    const $pdfCell = $(`.report-row[data-id="${incidentId}"]`).find('.pdf-cell');
                    $pdfCell.html(`<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="${incidentId}" data-pdf-type="04" data-pdf-location="" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>`);

                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: response.message,
                        confirmButtonColor: '#0d6efd'
                    }).then(() => {
                        bootstrap.Modal.getInstance(document.getElementById('modalReportFire'))?.hide();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
            },
            complete: function() {
                $(`#btn_save_report_fire`).prop('disabled', false);
            }
        });
    }

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
});

// ========================================
// Fire Report: Switch between Standard ↔ PDF Form
// (Global scope — called from inline onchange handlers)
// ========================================

function syncFireReportFormData(fromFormId, toFormId) {
    var fromForm = document.getElementById(fromFormId);
    var toForm = document.getElementById(toFormId);
    if (!fromForm || !toForm) return;

    var fromEls = fromForm.querySelectorAll('input, select, textarea');
    var dataMap = {};

    fromEls.forEach(function(el) {
        var name = el.name;
        if (!name || name === 'incident_id') return;
        if (el.type === 'file') return;

        if (el.type === 'checkbox') {
            if (!dataMap[name]) dataMap[name] = { type: 'checkbox', values: [] };
            if (el.checked) dataMap[name].values.push(el.value);
        } else if (el.type === 'radio') {
            if (el.checked) dataMap[name] = { type: 'radio', value: el.value };
        } else {
            if (!dataMap[name]) dataMap[name] = { type: 'value', values: [] };
            dataMap[name].values.push(el.value);
        }
    });

    Object.keys(dataMap).forEach(function(name) {
        var info = dataMap[name];
        var toEls = toForm.querySelectorAll('[name="' + name + '"]');
        if (toEls.length === 0) return;

        if (info.type === 'checkbox') {
            toEls.forEach(function(el) { el.checked = info.values.includes(el.value); });
        } else if (info.type === 'radio') {
            toEls.forEach(function(el) { el.checked = (el.value === info.value); });
        } else {
            toEls.forEach(function(el, idx) {
                if (idx < info.values.length) el.value = info.values[idx];
            });
        }
    });
}

function syncFireReportInspectorRows(direction) {
    if (direction === 'toPdf') {
        var stdRows = $('#rf_inspector_container .rp-inspector-row');
        var pdfContainer = document.getElementById('rfpdf_inspector_container');
        // Ensure PDF has enough rows
        while (pdfContainer.querySelectorAll('.rfpdf-inspector-row').length < stdRows.length) {
            rfpdfAddInspectorRow();
        }
        // Remove extra PDF rows
        var currentPdf = pdfContainer.querySelectorAll('.rfpdf-inspector-row');
        for (var i = stdRows.length; i < currentPdf.length; i++) {
            currentPdf[i].remove();
        }
        rfpdfRenumberInspectors();
        // Copy name+position from standard Select2 → PDF select
        stdRows.each(function(idx) {
            var pdfRow = pdfContainer.querySelectorAll('.rfpdf-inspector-row')[idx];
            if (!pdfRow) return;
            var nameVal = $(this).find('.rp-inspector-select').val() || '';
            var posVal = $(this).find('.rp-inspector-position').val() || '';
            // Set the PDF select value
            var pdfSelect = $(pdfRow).find('.rp-inspector-select');
            if (pdfSelect.length) {
                // Ensure option exists
                if (nameVal && pdfSelect.find('option[value="' + nameVal + '"]').length === 0) {
                    pdfSelect.append('<option value="' + nameVal + '">' + nameVal + '</option>');
                }
                pdfSelect.val(nameVal);
                if (pdfSelect.hasClass('select2-hidden-accessible')) pdfSelect.trigger('change.select2');
            }
            $(pdfRow).find('.rp-inspector-position').val(posVal);
        });
    } else {
        // toStd: PDF → Standard
        var pdfContainer2 = document.getElementById('rfpdf_inspector_container');
        var pdfRows = pdfContainer2.querySelectorAll('.rfpdf-inspector-row');
        var $container = $('#rf_inspector_container');
        while ($container.find('.rp-inspector-row').length < pdfRows.length) {
            var count = $container.find('.rp-inspector-row').length + 1;
            $container.append(rpBuildInspectorRow('5.' + count, '', '', 'rf'));
        }
        var stdRowsNow = $container.find('.rp-inspector-row');
        for (var j = pdfRows.length; j < stdRowsNow.length; j++) {
            $(stdRowsNow[j]).remove();
        }
        pdfRows.forEach(function(pdfRow, idx) {
            var stdRow = $container.find('.rp-inspector-row').eq(idx);
            if (!stdRow.length) return;
            var pdfName = $(pdfRow).find('.rp-inspector-select').val() || '';
            var pdfPos = $(pdfRow).find('.rp-inspector-position').val() || '';
            // Set standard select value
            var stdSelect = stdRow.find('.rp-inspector-select');
            if (pdfName && stdSelect.find('option[value="' + pdfName + '"]').length === 0) {
                stdSelect.append('<option value="' + pdfName + '">' + pdfName + '</option>');
            }
            stdSelect.val(pdfName);
            if (stdSelect.hasClass('select2-hidden-accessible')) stdSelect.trigger('change.select2');
            stdRow.find('.rp-inspector-position').val(pdfPos);
        });
    }
}

function switchFireReportToPdfForm() {
    syncFireReportInspectorRows('toPdf');
    syncFireReportFormData('formReportFire', 'formReportFirePdf');
    $('#rfpdf_incident_id').val($('#rf_incident_id').val());
    restoreDraftPdfReportNoFromDocDisplay('#rf_doc_no_display', ['#rfpdf_report_no_display', '#rfpdf_report_no_display_2', '#rfpdf_report_no_display_3']);

    var stdEl = document.getElementById('modalReportFire');
    var stdModal = bootstrap.Modal.getInstance(stdEl);
    if (stdModal) stdModal.hide();

    stdEl.addEventListener('hidden.bs.modal', function onHidden() {
        stdEl.removeEventListener('hidden.bs.modal', onHidden);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalReportFirePdf')).show();
    });
}

function switchFireReportToStandard() {
    syncFireReportInspectorRows('toStd');
    syncFireReportFormData('formReportFirePdf', 'formReportFire');
    $('#rf_incident_id').val($('#rfpdf_incident_id').val());

    var pdfEl = document.getElementById('modalReportFirePdf');
    var pdfModal = bootstrap.Modal.getInstance(pdfEl);
    if (pdfModal) pdfModal.hide();

    pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
        pdfEl.removeEventListener('hidden.bs.modal', onHidden);
        var stdEl = document.getElementById('modalReportFire');
        stdEl.addEventListener('shown.bs.modal', function onShown() {
            stdEl.removeEventListener('shown.bs.modal', onShown);
            rpInitSelect2InModal($('#modalReportFire'));
        });
        bootstrap.Modal.getOrCreateInstance(stdEl).show();
    });
}

// Save from PDF form
$(document).on('click', '#btn_save_report_fire_pdf', function() {
    var incidentId = $('#rfpdf_incident_id').val();
    if (!incidentId) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
        return;
    }

    var formData = {};
    var formEl = document.getElementById('formReportFirePdf');
    var elements = formEl.elements;

    for (var i = 0; i < elements.length; i++) {
        var el = elements[i];
        if (!el.name || el.name === 'incident_id') continue;

        if (el.type === 'radio') {
            if (el.checked) formData[el.name] = el.value;
        } else if (el.type === 'checkbox') {
            if (!formData[el.name]) formData[el.name] = [];
            if (el.checked) formData[el.name].push(el.value);
        } else {
            if (el.name.endsWith('[]')) {
                if (!formData[el.name]) formData[el.name] = [];
                formData[el.name].push(el.value);
            } else {
                formData[el.name] = el.value;
            }
        }
    }

    var payload = {
        incident_id: incidentId,
        form_data: formData
    };

    var btn = this;
    $.ajax({
        url: '/csims/api/incidentCheckList/saveFireReport.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        beforeSend: function() { $(btn).prop('disabled', true); },
        success: function(response) {
            if (response.success) {
                var $pdfCell = $('.report-row[data-id="' + incidentId + '"]').find('.pdf-cell');
                $pdfCell.html('<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="' + incidentId + '" data-pdf-type="04" data-pdf-location="" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>');

                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: response.message,
                    confirmButtonColor: '#0d6efd'
                }).then(function() {
                    var m = bootstrap.Modal.getInstance(document.getElementById('modalReportFirePdf'));
                    if (m) m.hide();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
            }
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
        },
        complete: function() { $(btn).prop('disabled', false); }
    });
});

// ========================================
// Person Evidence Report (วัตถุพยานบุคคล) — type 08
// Switch between Standard ↔ PDF Form, Sync, Save PDF
// ========================================

// --- Dynamic row builders for standard form ---
function peAddPersonRow() {
    var container = document.getElementById('pe_persons_container');
    var count = container.querySelectorAll('.pe-person-row').length + 1;
    var html = '<div class="row g-2 mb-2 align-items-center pe-person-row">' +
        '<div class="col-auto"><span class="fw-semibold pe-person-num" style="min-width:32px; display:inline-block;">1.' + count + '</span></div>' +
        '<div class="col-md-3"><select class="form-select form-select-sm" name="pe_person_prefix[]"><option value="">-- คำนำหน้า --</option><option value="นาย">นาย</option><option value="นาง">นาง</option><option value="นางสาว">นางสาว</option><option value="เด็กชาย">เด็กชาย</option><option value="เด็กหญิง">เด็กหญิง</option></select></div>' +
        '<div class="col"><input type="text" class="form-control form-control-sm" name="pe_person_name[]" placeholder="ชื่อ-นามสกุล"></div>' +
        '<div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger pe-remove-person" title="ลบ"><i class="fas fa-trash-alt"></i></button></div>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
}

function peAddPersonInfoRow() {
    var container = document.getElementById('pe_person_info_container');
    var count = container.querySelectorAll('.pe-person-info-row').length + 1;
    var html = '<div class="bg-light p-3 rounded mb-3 pe-person-info-row">' +
        '<div class="d-flex justify-content-between align-items-center mb-2">' +
        '<span class="fw-bold text-primary pe-person-info-num">บุคคลที่ ' + count + '</span>' +
        '<button type="button" class="btn btn-sm btn-outline-danger pe-remove-person-info" title="ลบ"><i class="fas fa-trash-alt"></i></button>' +
        '</div>' +
        '<div class="row g-2">' +
        '<div class="col-md-3"><label class="form-label">คำนำหน้า</label><select class="form-select form-select-sm" name="pe_pi_prefix[]"><option value="">-- เลือก --</option><option value="นาย">นาย</option><option value="นาง">นาง</option><option value="นางสาว">นางสาว</option><option value="เด็กชาย">เด็กชาย</option><option value="เด็กหญิง">เด็กหญิง</option></select></div>' +
        '<div class="col-md-5"><label class="form-label">ชื่อ-นามสกุล</label><input type="text" class="form-control form-control-sm" name="pe_pi_fullname[]"></div>' +
        '<div class="col-md-4"><label class="form-label">เลขบัตรประชาชน</label><input type="text" class="form-control form-control-sm" name="pe_pi_id_card[]" maxlength="13"></div>' +
        '<div class="col-md-4"><label class="form-label">หนังสือเดินทาง</label><input type="text" class="form-control form-control-sm" name="pe_pi_passport[]"></div>' +
        '<div class="col-md-2"><label class="form-label">ความสูง (ซม.)</label><input type="text" class="form-control form-control-sm" name="pe_pi_height[]"></div>' +
        '<div class="col-md-2"><label class="form-label">อายุ (ปี)</label><input type="text" class="form-control form-control-sm" name="pe_pi_age[]"></div>' +
        '<div class="col-md-2"><label class="form-label">สีผิว</label><input type="text" class="form-control form-control-sm" name="pe_pi_skin[]"></div>' +
        '<div class="col-md-2"><label class="form-label">มือที่ถนัด</label><select class="form-select form-select-sm" name="pe_pi_hand[]"><option value="">-- เลือก --</option><option value="ขวา">ขวา</option><option value="ซ้าย">ซ้าย</option><option value="ทั้งสอง">ทั้งสอง</option></select></div>' +
        '<div class="col-md-12"><label class="form-label">ลักษณะพิเศษ</label><textarea class="form-control form-control-sm" name="pe_pi_feature[]" rows="2"></textarea></div>' +
        '</div></div>';
    container.insertAdjacentHTML('beforeend', html);
}

function peAddEvidenceRow() {
    var container = document.getElementById('pe_evidence_container');
    var count = container.querySelectorAll('.pe-evidence-row').length + 1;
    var html = '<div class="row g-2 mb-2 align-items-end pe-evidence-row">' +
        '<div class="col-auto"><span class="fw-semibold pe-evidence-num" style="min-width:40px; display:inline-block;">3.2.' + count + '</span></div>' +
        '<div class="col"><label class="form-label">รายละเอียดตัวอย่าง</label><input type="text" class="form-control form-control-sm" name="pe_ev_detail[]" placeholder="เช่น เนื้อเยื่อบุกระพุ้งแก้ม"></div>' +
        '<div class="col-md-2"><label class="form-label">จำนวน</label><input type="text" class="form-control form-control-sm" name="pe_ev_qty[]"></div>' +
        '<div class="col-md-3"><label class="form-label">ส่งตรวจ</label><select class="form-select form-select-sm" name="pe_ev_lab_unit[]"><option value="">-- เลือกกลุ่มงาน --</option><option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option><option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option><option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option><option value="drug">กลุ่มงานตรวจยาเสพติด</option><option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option><option value="document">กลุ่มงานตรวจเอกสาร</option><option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option></select></div>' +
        '<div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger pe-remove-evidence" title="ลบ"><i class="fas fa-trash-alt"></i></button></div>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
}

// Standard form: add/remove event delegation
$(document).on('click', '#pe_add_person_btn', function() { peAddPersonRow(); });
$(document).on('click', '.pe-remove-person', function() {
    var container = document.getElementById('pe_persons_container');
    if (container.querySelectorAll('.pe-person-row').length > 1) {
        $(this).closest('.pe-person-row').remove();
        peRenumberRows();
    }
});
$(document).on('click', '#pe_add_person_info_btn', function() { peAddPersonInfoRow(); });
$(document).on('click', '.pe-remove-person-info', function() {
    var container = document.getElementById('pe_person_info_container');
    if (container.querySelectorAll('.pe-person-info-row').length > 1) {
        $(this).closest('.pe-person-info-row').remove();
        peRenumberRows();
    }
});
$(document).on('click', '#pe_add_evidence_btn', function() { peAddEvidenceRow(); });
$(document).on('click', '.pe-remove-evidence', function() {
    var container = document.getElementById('pe_evidence_container');
    if (container.querySelectorAll('.pe-evidence-row').length > 1) {
        $(this).closest('.pe-evidence-row').remove();
        peRenumberRows();
    }
});

function peRenumberRows() {
    $('#pe_persons_container .pe-person-row').each(function(i) {
        $(this).find('.pe-person-num').text('1.' + (i + 1));
    });
    $('#pe_person_info_container .pe-person-info-row').each(function(i) {
        $(this).find('.pe-person-info-num').text('บุคคลที่ ' + (i + 1));
    });
    $('#pe_evidence_container .pe-evidence-row').each(function(i) {
        $(this).find('.pe-evidence-num').text('3.2.' + (i + 1));
    });
}

// --- Dynamic row builders for PDF form ---
var thaiDigits = ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'];
function toThaiNum(n) {
    return String(n).split('').map(function(c) { return thaiDigits[parseInt(c)] || c; }).join('');
}

function pepdfAddPersonRow() {
    var container = document.getElementById('pepdf_persons_container');
    var count = container.querySelectorAll('.pepdf-person-row').length + 1;
    var html = '<div class="fr i2 pepdf-person-row">' +
        '<span class="fl">๑.' + toThaiNum(count) + ' (</span>' +
        '<select class="pepf-select" name="pe_person_prefix[]" style="flex:0 0 80px; min-width:60px;"><option value="">คำนำหน้า</option><option value="นาย">นาย</option><option value="นาง">นาง</option><option value="นางสาว">นางสาว</option><option value="เด็กชาย">เด็กชาย</option><option value="เด็กหญิง">เด็กหญิง</option></select>' +
        '<span class="fl">)</span>' +
        '<input type="text" class="pepf-inp" name="pe_person_name[]" placeholder="ชื่อ-นามสกุล">' +
        '<button type="button" class="pepf-del-btn" title="ลบ">✕</button>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
}

function pepdfAddPersonInfoRow() {
    var container = document.getElementById('pepdf_person_info_container');
    var count = container.querySelectorAll('.pepdf-person-info-block').length + 1;
    var html = '<div class="pepdf-person-info-block" style="margin-left:40px; margin-bottom:4px;">' +
        '<div class="fr"><span class="fl">๓.๑.' + toThaiNum(count) + ' บุคคลที่ ' + toThaiNum(count) + ' (</span>' +
        '<select class="pepf-select" name="pe_pi_prefix[]" style="flex:0 0 80px; min-width:60px;"><option value="">คำนำหน้า</option><option value="นาย">นาย</option><option value="นาง">นาง</option><option value="นางสาว">นางสาว</option><option value="เด็กชาย">เด็กชาย</option><option value="เด็กหญิง">เด็กหญิง</option></select>' +
        '<span class="fl">)</span><input type="text" class="pepf-inp" name="pe_pi_fullname[]" placeholder="ชื่อ-นามสกุล">' +
        '<button type="button" class="pepf-del-btn pepdf-remove-person-info" title="ลบ">✕</button></div>' +
        '<div class="fr" style="padding-left:20px; flex-wrap:nowrap;"><span class="fl">บัตรประชาชน</span><input type="text" class="pepf-inp pepf-inp-m" name="pe_pi_id_card[]" maxlength="13"><span class="fl">หนังสือเดินทาง</span><input type="text" class="pepf-inp pepf-inp-m" name="pe_pi_passport[]"></div>' +
        '<div class="fr" style="padding-left:20px; flex-wrap:nowrap;"><span class="fl">สูง</span><input type="text" class="pepf-inp pepf-inp-s" name="pe_pi_height[]"><span class="fl">ซม. อายุ</span><input type="text" class="pepf-inp pepf-inp-s" name="pe_pi_age[]"><span class="fl">ปี สีผิว</span><input type="text" class="pepf-inp pepf-inp-s" name="pe_pi_skin[]"><span class="fl">มือที่ถนัด</span><select class="pepf-select" name="pe_pi_hand[]" style="flex:0 0 60px; min-width:50px;"><option value="">-</option><option value="ขวา">ขวา</option><option value="ซ้าย">ซ้าย</option><option value="ทั้งสอง">ทั้งสอง</option></select></div>' +
        '<div class="fr" style="padding-left:20px;"><span class="fl">ลักษณะพิเศษ</span><input type="text" class="pepf-inp" name="pe_pi_feature[]"></div>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
}

function pepdfAddEvidenceRow() {
    var container = document.getElementById('pepdf_evidence_container');
    var count = container.querySelectorAll('.pepdf-evidence-row').length + 1;
    var html = '<div class="pepdf-evidence-row" style="margin-left:40px; margin-bottom:2px;">' +
        '<div class="fr"><span class="fl">๓.๒.' + toThaiNum(count) + ' ตัวอย่าง</span>' +
        '<input type="text" class="pepf-inp" name="pe_ev_detail[]" placeholder="เช่น เนื้อเยื่อบุกระพุ้งแก้ม">' +
        '<button type="button" class="pepf-del-btn pepdf-remove-evidence" title="ลบ">✕</button></div>' +
        '<div class="fr" style="padding-left:20px; flex-wrap:nowrap;"><span class="fl">จำนวน</span><input type="text" class="pepf-inp pepf-inp-s" name="pe_ev_qty[]"><span class="fl">ส่งตรวจ</span>' +
        '<select class="pepf-select" name="pe_ev_lab_unit[]" style="min-width:200px;"><option value="">-- เลือกกลุ่มงาน --</option><option value="bio_dna">กลุ่มงานตรวจชีววิทยา</option><option value="chemical">กลุ่มงานตรวจทางเคมีฟิสิกส์</option><option value="fingerprint">กลุ่มงานตรวจลายนิ้วมือแฝง</option><option value="drug">กลุ่มงานตรวจยาเสพติด</option><option value="gun">กลุ่มงานตรวจอาวุธปืนและเครื่องกระสุน</option><option value="document">กลุ่มงานตรวจเอกสาร</option><option value="digital">กลุ่มงานตรวจพิสูจน์หลักฐานดิจิทัล</option><option value="computer">กลุ่มงานตรวจพิสูจน์อาชญากรรมคอมพิวเตอร์</option></select></div>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
}

function pepdfAddInspectorRow() {
    var container = document.getElementById('pepdf_inspector_container');
    var count = container.querySelectorAll('.pepdf-inspector-row').length + 1;
    var opts = '<option value="">-- เลือกผู้ตรวจ --</option>';
    if (typeof rpUsersList !== 'undefined' && rpUsersList.length > 0) {
        rpUsersList.forEach(function(u) {
            opts += '<option value="' + u.fullname + '">' + u.fullname + '</option>';
        });
    }
    var html = '<div class="fr i2 pepdf-inspector-row">' +
        '<span class="fl pepdf-inspector-num">' + count + '.</span>' +
        '<select class="pepf-select pepdf-inspector-select" name="pe_inspector_name[]">' + opts + '</select>' +
        '<span class="fl">ตำแหน่ง</span>' +
        '<input type="text" class="pepf-inp pepf-inp-m pepdf-inspector-position" name="pe_inspector_position[]" readonly>' +
        '<button type="button" class="pepf-del-btn pepdf-remove-inspector" title="ลบ">✕</button>' +
        '</div>';
    container.insertAdjacentHTML('beforeend', html);
}

// PDF form: add/remove event delegation
$(document).on('click', '#pepdf_add_person_btn', function() { pepdfAddPersonRow(); });
$(document).on('click', '#pepdf_persons_container .pepf-del-btn', function() {
    var container = document.getElementById('pepdf_persons_container');
    if (container.querySelectorAll('.pepdf-person-row').length > 1) {
        $(this).closest('.pepdf-person-row').remove();
    }
});
$(document).on('click', '#pepdf_add_person_info_btn', function() { pepdfAddPersonInfoRow(); });
$(document).on('click', '.pepdf-remove-person-info', function() {
    var container = document.getElementById('pepdf_person_info_container');
    if (container.querySelectorAll('.pepdf-person-info-block').length > 1) {
        $(this).closest('.pepdf-person-info-block').remove();
    }
});
$(document).on('click', '#pepdf_add_evidence_btn', function() { pepdfAddEvidenceRow(); });
$(document).on('click', '.pepdf-remove-evidence', function() {
    var container = document.getElementById('pepdf_evidence_container');
    if (container.querySelectorAll('.pepdf-evidence-row').length > 1) {
        $(this).closest('.pepdf-evidence-row').remove();
    }
});
$(document).on('click', '#pepdf_add_inspector_btn', function() { pepdfAddInspectorRow(); });
$(document).on('click', '.pepdf-remove-inspector', function() {
    var container = document.getElementById('pepdf_inspector_container');
    if (container.querySelectorAll('.pepdf-inspector-row').length > 1) {
        $(this).closest('.pepdf-inspector-row').remove();
    }
});

// PDF inspector select → auto-fill position
$(document).on('change', '.pepdf-inspector-select', function() {
    var name = $(this).val();
    var $row = $(this).closest('.pepdf-inspector-row');
    if (typeof rpUsersList !== 'undefined') {
        var user = rpUsersList.find(function(u) { return u.fullname === name; });
        $row.find('.pepdf-inspector-position').val(user ? user.position : '');
    }
});

// --- Sync form data between standard ↔ PDF ---
function syncPersonEvidenceFormData(fromFormId, toFormId) {
    var fromForm = document.getElementById(fromFormId);
    var toForm = document.getElementById(toFormId);
    if (!fromForm || !toForm) return;

    var fromEls = fromForm.querySelectorAll('input, select, textarea');
    var dataMap = {};

    fromEls.forEach(function(el) {
        var name = el.name;
        if (!name || name === 'incident_id') return;
        if (el.type === 'file') return;

        if (el.type === 'checkbox') {
            if (!dataMap[name]) dataMap[name] = { type: 'checkbox', values: [] };
            if (el.checked) dataMap[name].values.push(el.value);
        } else if (el.type === 'radio') {
            if (el.checked) dataMap[name] = { type: 'radio', value: el.value };
        } else {
            if (!dataMap[name]) dataMap[name] = { type: 'value', values: [] };
            dataMap[name].values.push(el.value);
        }
    });

    Object.keys(dataMap).forEach(function(name) {
        var info = dataMap[name];
        var toEls = toForm.querySelectorAll('[name="' + name + '"]');
        if (toEls.length === 0) return;

        if (info.type === 'checkbox') {
            toEls.forEach(function(el) { el.checked = info.values.includes(el.value); });
        } else if (info.type === 'radio') {
            toEls.forEach(function(el) { el.checked = (el.value === info.value); });
        } else {
            toEls.forEach(function(el, idx) {
                if (idx < info.values.length) el.value = info.values[idx];
            });
        }
    });
}

// --- Sync dynamic rows (persons, person_info, evidences) between forms ---
function syncPersonEvidenceDynamicRows(direction) {
    if (direction === 'toPdf') {
        // Persons
        var stdPersons = $('#pe_persons_container .pe-person-row').length;
        var pdfPersons = $('#pepdf_persons_container .pepdf-person-row').length;
        while (pdfPersons < stdPersons) { pepdfAddPersonRow(); pdfPersons++; }
        while (pdfPersons > stdPersons) {
            $('#pepdf_persons_container .pepdf-person-row:last').remove(); pdfPersons--;
        }
        // Person info
        var stdPi = $('#pe_person_info_container .pe-person-info-row').length;
        var pdfPi = $('#pepdf_person_info_container .pepdf-person-info-block').length;
        while (pdfPi < stdPi) { pepdfAddPersonInfoRow(); pdfPi++; }
        while (pdfPi > stdPi) {
            $('#pepdf_person_info_container .pepdf-person-info-block:last').remove(); pdfPi--;
        }
        // Evidence
        var stdEv = $('#pe_evidence_container .pe-evidence-row').length;
        var pdfEv = $('#pepdf_evidence_container .pepdf-evidence-row').length;
        while (pdfEv < stdEv) { pepdfAddEvidenceRow(); pdfEv++; }
        while (pdfEv > stdEv) {
            $('#pepdf_evidence_container .pepdf-evidence-row:last').remove(); pdfEv--;
        }
    } else {
        // toStd
        var pdfPersons2 = $('#pepdf_persons_container .pepdf-person-row').length;
        var stdPersons2 = $('#pe_persons_container .pe-person-row').length;
        while (stdPersons2 < pdfPersons2) { peAddPersonRow(); stdPersons2++; }
        while (stdPersons2 > pdfPersons2) {
            $('#pe_persons_container .pe-person-row:last').remove(); stdPersons2--;
        }
        var pdfPi2 = $('#pepdf_person_info_container .pepdf-person-info-block').length;
        var stdPi2 = $('#pe_person_info_container .pe-person-info-row').length;
        while (stdPi2 < pdfPi2) { peAddPersonInfoRow(); stdPi2++; }
        while (stdPi2 > pdfPi2) {
            $('#pe_person_info_container .pe-person-info-row:last').remove(); stdPi2--;
        }
        var pdfEv2 = $('#pepdf_evidence_container .pepdf-evidence-row').length;
        var stdEv2 = $('#pe_evidence_container .pe-evidence-row').length;
        while (stdEv2 < pdfEv2) { peAddEvidenceRow(); stdEv2++; }
        while (stdEv2 > pdfEv2) {
            $('#pe_evidence_container .pe-evidence-row:last').remove(); stdEv2--;
        }
    }
}

// --- Sync inspector rows ---
function syncPersonEvidenceInspectorRows(direction) {
    if (direction === 'toPdf') {
        var stdRows = $('#pe_inspector_container .rp-inspector-row');
        var pdfContainer = document.getElementById('pepdf_inspector_container');
        while (pdfContainer.querySelectorAll('.pepdf-inspector-row').length < stdRows.length) {
            pepdfAddInspectorRow();
        }
        var currentPdf = pdfContainer.querySelectorAll('.pepdf-inspector-row');
        for (var i = stdRows.length; i < currentPdf.length; i++) {
            currentPdf[i].remove();
        }
        stdRows.each(function(idx) {
            var pdfRow = pdfContainer.querySelectorAll('.pepdf-inspector-row')[idx];
            if (!pdfRow) return;
            var nameVal = $(this).find('.rp-inspector-select').val() || '';
            var posVal = $(this).find('.rp-inspector-position').val() || '';
            var pdfSelect = $(pdfRow).find('.pepdf-inspector-select');
            if (pdfSelect.length) {
                if (nameVal && pdfSelect.find('option[value="' + nameVal + '"]').length === 0) {
                    pdfSelect.append('<option value="' + nameVal + '">' + nameVal + '</option>');
                }
                pdfSelect.val(nameVal);
            }
            $(pdfRow).find('.pepdf-inspector-position').val(posVal);
        });
    } else {
        var pdfContainer2 = document.getElementById('pepdf_inspector_container');
        var pdfRows = pdfContainer2.querySelectorAll('.pepdf-inspector-row');
        var $container = $('#pe_inspector_container');
        while ($container.find('.rp-inspector-row').length < pdfRows.length) {
            var count = $container.find('.rp-inspector-row').length + 1;
            $container.append(rpBuildInspectorRow(count.toString(), '', '', 'pe'));
        }
        var stdRowsNow = $container.find('.rp-inspector-row');
        for (var j = pdfRows.length; j < stdRowsNow.length; j++) {
            $(stdRowsNow[j]).remove();
        }
        pdfRows.forEach(function(pdfRow, idx) {
            var stdRow = $container.find('.rp-inspector-row').eq(idx);
            if (!stdRow.length) return;
            var pdfName = $(pdfRow).find('.pepdf-inspector-select').val() || '';
            var pdfPos = $(pdfRow).find('.pepdf-inspector-position').val() || '';
            var stdSelect = stdRow.find('.rp-inspector-select');
            if (pdfName && stdSelect.find('option[value="' + pdfName + '"]').length === 0) {
                stdSelect.append('<option value="' + pdfName + '">' + pdfName + '</option>');
            }
            stdSelect.val(pdfName);
            if (stdSelect.hasClass('select2-hidden-accessible')) stdSelect.trigger('change.select2');
            stdRow.find('.rp-inspector-position').val(pdfPos);
        });
    }
}

// --- Switch Standard → PDF form ---
function switchPersonEvidenceReportToPdfForm() {
    syncPersonEvidenceInspectorRows('toPdf');
    syncPersonEvidenceDynamicRows('toPdf');
    syncPersonEvidenceFormData('formReportPersonEvidence', 'formReportPersonEvidencePdf');
    $('#pepdf_incident_id').val($('#pe_incident_id').val());
    restoreDraftPdfReportNoFromDocDisplay('#pe_doc_no_display', ['#pepdf_report_no_display', '#pepdf_report_no_display_p2']);
    var thDoc = thaiDocNo($('#pe_doc_no_display').text());
    if (thDoc) $('#pepdf_report_no_p2').text(thDoc);

    var stdEl = document.getElementById('modalReportPersonEvidence');
    var stdModal = bootstrap.Modal.getInstance(stdEl);
    if (stdModal) stdModal.hide();

    stdEl.addEventListener('hidden.bs.modal', function onHidden() {
        stdEl.removeEventListener('hidden.bs.modal', onHidden);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalReportPersonEvidencePdf')).show();
    });
}

// --- Switch PDF form → Standard ---
function switchPersonEvidenceReportToStandard() {
    syncPersonEvidenceInspectorRows('toStd');
    syncPersonEvidenceDynamicRows('toStd');
    syncPersonEvidenceFormData('formReportPersonEvidencePdf', 'formReportPersonEvidence');
    $('#pe_incident_id').val($('#pepdf_incident_id').val());

    var pdfEl = document.getElementById('modalReportPersonEvidencePdf');
    var pdfModal = bootstrap.Modal.getInstance(pdfEl);
    if (pdfModal) pdfModal.hide();

    pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
        pdfEl.removeEventListener('hidden.bs.modal', onHidden);
        var stdEl = document.getElementById('modalReportPersonEvidence');
        stdEl.addEventListener('shown.bs.modal', function onShown() {
            stdEl.removeEventListener('shown.bs.modal', onShown);
            rpInitSelect2InModal($('#modalReportPersonEvidence'));
        });
        bootstrap.Modal.getOrCreateInstance(stdEl).show();
    });
}

// --- Save from PDF form ---
$(document).on('click', '#btn_save_report_person_evidence_pdf', function() {
    var incidentId = $('#pepdf_incident_id').val();
    if (!incidentId) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
        return;
    }

    var formData = {};
    var formEl = document.getElementById('formReportPersonEvidencePdf');
    var elements = formEl.elements;

    for (var i = 0; i < elements.length; i++) {
        var el = elements[i];
        if (!el.name || el.name === 'incident_id') continue;

        if (el.type === 'radio') {
            if (el.checked) formData[el.name] = el.value;
        } else if (el.type === 'checkbox') {
            if (!formData[el.name]) formData[el.name] = [];
            if (el.checked) formData[el.name].push(el.value);
        } else {
            if (el.name.endsWith('[]')) {
                if (!formData[el.name]) formData[el.name] = [];
                formData[el.name].push(el.value);
            } else {
                formData[el.name] = el.value;
            }
        }
    }

    var payload = {
        incident_id: incidentId,
        form_data: formData
    };

    var btn = this;
    $.ajax({
        url: '/csims/api/incidentCheckList/savePersonEvidenceReport.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        beforeSend: function() { $(btn).prop('disabled', true); },
        success: function(response) {
            if (response.success) {
                var $pdfCell = $('.report-row[data-id="' + incidentId + '"]').find('.pdf-cell');
                $pdfCell.html('<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="' + incidentId + '" data-pdf-type="08" data-pdf-location="" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>');

                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: response.message,
                    confirmButtonColor: '#0d6efd'
                }).then(function() {
                    var m = bootstrap.Modal.getInstance(document.getElementById('modalReportPersonEvidencePdf'));
                    if (m) m.hide();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
            }
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
        },
        complete: function() { $(btn).prop('disabled', false); }
    });
});

// ========================================
// Traffic Report: Open Modal, Save, Switch Standard ↔ PDF
// ========================================

function openTrafficReportModal(incidentId, docNo, reportNo) {
    const prefix = 'rt';
    const modalId = 'modalReportTraffic';
    const formId = 'formReportTraffic';

    // Reset form & edit info
    document.getElementById(formId).reset();
    $(`#${prefix}_incident_id`).val(incidentId);
    $(`#${prefix}_doc_no_display`).text(thaiDocNo(docNo));

    // Set report_no in PDF modal header (ใช้เลขที่เอกสารเต็ม)
    var rptYear = (reportNo || '').indexOf('/') !== -1 ? (reportNo.split('/')[1] || '').toString().slice(-2) : '';
    $('#rtpdf_report_ref').val(thaiDocNo(docNo));
    $('#rtpdf_report_year').val(rptYear);

    $(`#${prefix}_editInfo`).addClass('d-none');
    $(`#${prefix}_editCount`).text('0');
    $(`#${prefix}_editDate`).text('-');

    // Reset inspector rows
    const $modal = $(`#${modalId}`);
    const $container = $modal.find(`#${prefix}_inspector_container`);
    $container.find('.rp-inspector-row').not(':first').remove();
    if ($container.find('.rp-inspector-row').length === 0) {
        $container.append(rpBuildInspectorRow('3.1', '', '', prefix));
    }
    $container.find('.rp-inspector-select').prop('selectedIndex', 0);
    $container.find('.rp-inspector-position').val('');

    // Reset vehicle containers
    $('#rt_vehicle_container .rt-vehicle-block').not(':first').remove();
    $('#rt_analysis_vehicle_container .rt-analysis-block').not(':first').remove();

    // Reset PDF form & its containers
    var pdfFormEl = document.getElementById('formReportTrafficPdf');
    if (pdfFormEl) pdfFormEl.reset();
    // Restore report fields cleared by reset
    $('#rtpdf_report_ref').val(thaiDocNo(docNo));
    var currentYear = '<?= substr((date('Y') + 543), -2) ?>';
    $('#rtpdf_report_year').val(rptYear || currentYear);
    $('#rtpdf_vehicle_container .rtpdf-vehicle-block').not(':first').remove();
    $('#rtpdf_analysis_vehicle_container .rtpdf-analysis-block').not(':first').remove();
    $('#rtpdf_inspector_container .rtpdf-inspector-row').not(':first').remove();

    // Load users for inspector dropdowns then load data
    rpLoadUsers(function() {
        rpPopulateAllInspectorSelects();
        rpInitSelect2InModal($modal);

        // Load data from API
        $.ajax({
            url: '/csims/api/incidentCheckList/getTrafficReportData.php',
            type: 'GET',
            data: { incident_id: incidentId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    // Add extra inspector rows if needed
                    const inspectorNames = response.data['rt_inspector_name[]'];
                    if (Array.isArray(inspectorNames) && inspectorNames.length > 1) {
                        for (let i = 1; i < inspectorNames.length; i++) {
                            const count = $container.find('.rp-inspector-row').length + 1;
                            $container.append(rpBuildInspectorRow('3.' + count, '', '', prefix));
                        }
                    }

                    // Add extra vehicle blocks if needed
                    const vTypes = response.data['rt_vehicle_type[]'];
                    if (Array.isArray(vTypes) && vTypes.length > 1) {
                        for (let i = 1; i < vTypes.length; i++) {
                            rtAddVehicleBlock();
                        }
                    }

                    // Add extra analysis blocks if needed
                    const aDescs = response.data['rt_analysis_vehicle_desc[]'];
                    if (Array.isArray(aDescs) && aDescs.length > 1) {
                        for (let i = 1; i < aDescs.length; i++) {
                            rtAddAnalysisVehicleBlock();
                        }
                    }

                    prefillReportForm(prefix, response.data, $(`#${formId}`));
                    rpInitSelect2InModal($modal);
                }
                // แสดงจำนวนครั้งที่แก้ไข
                const countEdit = response.count_report || 0;
                if (countEdit > 0) {
                    $(`#${prefix}_editInfo`).removeClass('d-none');
                    $(`#${prefix}_editCount`).text(countEdit);
                    $(`#${prefix}_editDate`).text(response.edit_date || '-');
                }

                // Store response data for use after modal shown
                var trafficReportData = response.data;
                console.log(trafficReportData,"<--------------trafficReportData");
                $('#rtpdf_incident_id').val(incidentId);
                const pdfModalEl = document.getElementById('modalReportTrafficPdf');
                const pdfModal = new bootstrap.Modal(pdfModalEl);

                // Prefill PDF form after modal is shown (DOM ready)
                pdfModalEl.addEventListener('shown.bs.modal', function onShown() {
                    pdfModalEl.removeEventListener('shown.bs.modal', onShown);

                    if (trafficReportData) {
                        // Expand PDF inspector rows
                        var pdfInspNames = trafficReportData['rt_inspector_name[]'];
                        if (Array.isArray(pdfInspNames) && pdfInspNames.length > 1) {
                            var pdfInspCount = document.querySelectorAll('#rtpdf_inspector_container .rtpdf-inspector-row').length;
                            for (var ii = pdfInspCount; ii < pdfInspNames.length; ii++) {
                                rtpdfAddInspector();
                            }
                        }

                        // Expand PDF vehicle blocks
                        var pdfVTypes = trafficReportData['rt_vehicle_type[]'];
                        if (Array.isArray(pdfVTypes) && pdfVTypes.length > 1) {
                            var pdfVehCount = document.querySelectorAll('#rtpdf_vehicle_container .rtpdf-vehicle-block').length;
                            for (var vi = pdfVehCount; vi < pdfVTypes.length; vi++) {
                                rtpdfAddVehicle();
                            }
                        }

                        // Expand PDF analysis blocks
                        var pdfADescs = trafficReportData['rt_analysis_vehicle_desc[]'];
                        if (Array.isArray(pdfADescs) && pdfADescs.length > 1) {
                            var pdfAnaCount = document.querySelectorAll('#rtpdf_analysis_vehicle_container .rtpdf-analysis-block').length;
                            for (var ai = pdfAnaCount; ai < pdfADescs.length; ai++) {
                                rtpdfAddAnalysisVehicle();
                            }
                        }

                        // Now prefill PDF form with all blocks ready
                        prefillReportForm('rt', trafficReportData, $('#formReportTrafficPdf'));

                        // Restore report_ref to docNo after prefill
                        $('#rtpdf_report_ref').val(thaiDocNo(docNo));
                        $('#rtpdf_report_ref_2').val(thaiDocNo(docNo));
                        $('#rtpdf_report_ref_3').val(thaiDocNo(docNo));
                        $('#rtpdf_agency_name_2').val(trafficReportData.rt_agency_name);
                        $('#rtpdf_agency_name_3').val(trafficReportData.rt_agency_name);
                        // Re-init Select2 for inspector dropdowns
                        if (typeof rpLoadUsers === 'function') {
                            rpLoadUsers(function() {
                                $('#rtpdf_inspector_container .rp-inspector-select').each(function() {
                                    var current = $(this).val();
                                    $(this).html(rpBuildUserOptions(current));
                                    if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
                                    $(this).select2({
                                        theme: 'bootstrap-5', width: '100%', placeholder: '-- เลือกผู้ตรวจ --',
                                        allowClear: true, dropdownParent: $('#modalReportTrafficPdf')
                                    });
                                });
                            });
                        }
                    }
                }, { once: true });

                pdfModal.show();
            },
            error: function() {
                // No data yet — open PDF modal with empty form
                $('#rtpdf_incident_id').val(incidentId);
                const pdfModal = new bootstrap.Modal(document.getElementById('modalReportTrafficPdf'));
                pdfModal.show();
            }
        });
    });
}

// --- Save Report Traffic (from standard form) ---
$('#btn_save_report_traffic').on('click', function() {
    saveTrafficReport('formReportTraffic', 'rt');
});

function saveTrafficReport(formId, prefix) {
    const incidentId = $(`#${prefix}_incident_id`).val();
    if (!incidentId) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
        return;
    }

    const formData = {};
    const formEl = document.getElementById(formId);
    const elements = formEl.elements;

    for (let i = 0; i < elements.length; i++) {
        const el = elements[i];
        if (!el.name || el.name === 'incident_id') continue;

        if (el.type === 'radio') {
            if (el.checked) formData[el.name] = el.value;
        } else if (el.type === 'checkbox') {
            if (!formData[el.name]) formData[el.name] = [];
            if (el.checked) formData[el.name].push(el.value);
        } else {
            if (el.name.endsWith('[]')) {
                if (!formData[el.name]) formData[el.name] = [];
                formData[el.name].push(el.value);
            } else {
                formData[el.name] = el.value;
            }
        }
    }

    const payload = {
        incident_id: incidentId,
        form_data: formData
    };

    $.ajax({
        url: '/csims/api/incidentCheckList/saveTrafficReport.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        beforeSend: function() {
            $(`#btn_save_report_traffic`).prop('disabled', true);
        },
        success: function(response) {
            if (response.success) {
                let currentCount = parseInt($(`#${prefix}_editCount`).text()) || 0;
                currentCount++;
                $(`#${prefix}_editCount`).text(currentCount);
                $(`#${prefix}_editDate`).text(new Date().toLocaleString('th-TH'));
                $(`#${prefix}_editInfo`).removeClass('d-none');

                const $pdfCell = $(`.report-row[data-id="${incidentId}"]`).find('.pdf-cell');
                $pdfCell.html(`<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="${incidentId}" data-pdf-type="05" data-pdf-location="" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>`);

                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: response.message,
                    confirmButtonColor: '#0d6efd'
                }).then(() => {
                    bootstrap.Modal.getInstance(document.getElementById('modalReportTraffic'))?.hide();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
            }
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
        },
        complete: function() {
            $(`#btn_save_report_traffic`).prop('disabled', false);
        }
    });
}

// --- Traffic Report: Form Data Sync ---
function syncTrafficReportFormData(fromFormId, toFormId) {
    var fromForm = document.getElementById(fromFormId);
    var toForm = document.getElementById(toFormId);
    if (!fromForm || !toForm) return;

    var fromEls = fromForm.querySelectorAll('input, select, textarea');
    var dataMap = {};

    fromEls.forEach(function(el) {
        var name = el.name;
        if (!name || name === 'incident_id') return;
        if (el.type === 'file') return;

        if (el.type === 'checkbox') {
            if (!dataMap[name]) dataMap[name] = { type: 'checkbox', values: [] };
            if (el.checked) dataMap[name].values.push(el.value);
        } else if (el.type === 'radio') {
            if (el.checked) dataMap[name] = { type: 'radio', value: el.value };
        } else {
            if (!dataMap[name]) dataMap[name] = { type: 'value', values: [] };
            dataMap[name].values.push(el.value);
        }
    });

    Object.keys(dataMap).forEach(function(name) {
        var info = dataMap[name];
        var toEls = toForm.querySelectorAll('[name="' + name + '"]');
        if (toEls.length === 0) return;

        if (info.type === 'checkbox') {
            toEls.forEach(function(el) { el.checked = info.values.includes(el.value); });
        } else if (info.type === 'radio') {
            toEls.forEach(function(el) { el.checked = (el.value === info.value); });
        } else {
            toEls.forEach(function(el, idx) {
                if (idx < info.values.length) el.value = info.values[idx];
            });
        }
    });
}

// --- Traffic Report: Inspector Rows Sync ---
function syncTrafficReportInspectorRows(direction) {
    if (direction === 'toPdf') {
        var stdRows = $('#rt_inspector_container .rp-inspector-row');
        var pdfContainer = document.getElementById('rtpdf_inspector_container');
        while (pdfContainer.querySelectorAll('.rtpdf-inspector-row').length < stdRows.length) {
            rtpdfAddInspector();
        }
        var currentPdf = pdfContainer.querySelectorAll('.rtpdf-inspector-row');
        for (var i = stdRows.length; i < currentPdf.length; i++) {
            currentPdf[i].remove();
        }
        rtpdfRenumberInspectors();
        stdRows.each(function(idx) {
            var pdfRow = pdfContainer.querySelectorAll('.rtpdf-inspector-row')[idx];
            if (!pdfRow) return;
            var nameVal = $(this).find('.rp-inspector-select').val() || '';
            var posVal = $(this).find('.rp-inspector-position').val() || '';
            var pdfSelect = $(pdfRow).find('.rp-inspector-select');
            if (pdfSelect.length) {
                if (nameVal && pdfSelect.find('option[value="' + nameVal + '"]').length === 0) {
                    pdfSelect.append('<option value="' + nameVal + '">' + nameVal + '</option>');
                }
                pdfSelect.val(nameVal);
                if (pdfSelect.hasClass('select2-hidden-accessible')) pdfSelect.trigger('change.select2');
            }
            $(pdfRow).find('.rp-inspector-position').val(posVal);
        });
    } else {
        var pdfContainer2 = document.getElementById('rtpdf_inspector_container');
        var pdfRows = pdfContainer2.querySelectorAll('.rtpdf-inspector-row');
        var $container = $('#rt_inspector_container');
        while ($container.find('.rp-inspector-row').length < pdfRows.length) {
            var count = $container.find('.rp-inspector-row').length + 1;
            $container.append(rpBuildInspectorRow('3.' + count, '', '', 'rt'));
        }
        var stdRowsNow = $container.find('.rp-inspector-row');
        for (var j = pdfRows.length; j < stdRowsNow.length; j++) {
            $(stdRowsNow[j]).remove();
        }
        pdfRows.forEach(function(pdfRow, idx) {
            var stdRow = $container.find('.rp-inspector-row').eq(idx);
            if (!stdRow.length) return;
            var pdfName = $(pdfRow).find('.rp-inspector-select').val() || '';
            var pdfPos = $(pdfRow).find('.rp-inspector-position').val() || '';
            var stdSelect = stdRow.find('.rp-inspector-select');
            if (pdfName && stdSelect.find('option[value="' + pdfName + '"]').length === 0) {
                stdSelect.append('<option value="' + pdfName + '">' + pdfName + '</option>');
            }
            stdSelect.val(pdfName);
            if (stdSelect.hasClass('select2-hidden-accessible')) stdSelect.trigger('change.select2');
            stdRow.find('.rp-inspector-position').val(pdfPos);
        });
    }
}

// --- Traffic Report: Switch to PDF Form ---
function switchTrafficReportToPdfForm() {
    syncTrafficReportInspectorRows('toPdf');
    syncTrafficReportFormData('formReportTraffic', 'formReportTrafficPdf');
    $('#rtpdf_incident_id').val($('#rt_incident_id').val());
    restoreDraftPdfReportNoFromDocDisplay('#rt_doc_no_display', ['#rtpdf_report_ref', '#rtpdf_report_ref_2', '#rtpdf_report_ref_3']);

    var stdEl = document.getElementById('modalReportTraffic');
    var stdModal = bootstrap.Modal.getInstance(stdEl);
    if (stdModal) stdModal.hide();

    stdEl.addEventListener('hidden.bs.modal', function onHidden() {
        stdEl.removeEventListener('hidden.bs.modal', onHidden);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalReportTrafficPdf')).show();
    });
}

// --- Traffic Report: Switch to Standard Form ---
function switchTrafficReportToStandard() {
    syncTrafficReportInspectorRows('toStd');
    syncTrafficReportFormData('formReportTrafficPdf', 'formReportTraffic');
    $('#rt_incident_id').val($('#rtpdf_incident_id').val());

    var pdfEl = document.getElementById('modalReportTrafficPdf');
    var pdfModal = bootstrap.Modal.getInstance(pdfEl);
    if (pdfModal) pdfModal.hide();

    pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
        pdfEl.removeEventListener('hidden.bs.modal', onHidden);
        var stdEl = document.getElementById('modalReportTraffic');
        stdEl.addEventListener('shown.bs.modal', function onShown() {
            stdEl.removeEventListener('shown.bs.modal', onShown);
            rpInitSelect2InModal($('#modalReportTraffic'));
        });
        bootstrap.Modal.getOrCreateInstance(stdEl).show();
    });
}

// --- Save from Traffic PDF form ---
$(document).on('click', '#btn_save_report_traffic_pdf', function() {
    var incidentId = $('#rtpdf_incident_id').val();
    if (!incidentId) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
        return;
    }

    var formData = {};
    var formEl = document.getElementById('formReportTrafficPdf');
    var elements = formEl.elements;

    for (var i = 0; i < elements.length; i++) {
        var el = elements[i];
        if (!el.name || el.name === 'incident_id') continue;

        if (el.type === 'radio') {
            if (el.checked) formData[el.name] = el.value;
        } else if (el.type === 'checkbox') {
            if (!formData[el.name]) formData[el.name] = [];
            if (el.checked) formData[el.name].push(el.value);
        } else {
            if (el.name.endsWith('[]')) {
                if (!formData[el.name]) formData[el.name] = [];
                formData[el.name].push(el.value);
            } else {
                formData[el.name] = el.value;
            }
        }
    }

    var payload = {
        incident_id: incidentId,
        form_data: formData
    };

    var btn = this;
    $.ajax({
        url: '/csims/api/incidentCheckList/saveTrafficReport.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        beforeSend: function() { $(btn).prop('disabled', true); },
        success: function(response) {
            if (response.success) {
                var $pdfCell = $('.report-row[data-id="' + incidentId + '"]').find('.pdf-cell');
                $pdfCell.html('<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="' + incidentId + '" data-pdf-type="05" data-pdf-location="" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>');

                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: response.message,
                    confirmButtonColor: '#0d6efd'
                }).then(function() {
                    var m = bootstrap.Modal.getInstance(document.getElementById('modalReportTrafficPdf'));
                    if (m) m.hide();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
            }
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
        },
        complete: function() { $(btn).prop('disabled', false); }
    });
});

// ========================================
// Scene Evidence Report (วัตถุพยานที่เกิดเหตุ) — type 07
// ========================================

var attachmentStoreEV7 = [];
var deletedExistingPhotosEV7 = [];

function openSceneEvidenceReportModal(incidentId, docNo, reportNo) {
    // Reset forms
    var stdForm = document.getElementById('incidentCheckListFormSceneEvidence');
    var pdfForm = document.getElementById('sceneEvidenceFormPdf');
    if (stdForm) stdForm.reset();
    if (pdfForm) pdfForm.reset();

    attachmentStoreEV7 = [];
    deletedExistingPhotosEV7 = [];

    // Set hidden fields
    $('#receiveNoti_id_ev7').val(incidentId);
    $('#sevpf_receiveNoti_id').val(incidentId);
    $('#doc_no_ev7').val(docNo);
    $('#sevpf_doc_no').val(docNo);
    $('#receiveNoti_No_ev7').text(thaiDocNo(docNo));

    // Set report_no in both standard and PDF forms (ใช้เลขที่เอกสารเต็ม)
    $('#report_no_ev7').val(reportNo || '');
    $('#sevpf_report_no').val(reportNo || '');
    var currentYear = '<?= substr((date('Y') + 543), -2) ?>';
    var rptYear = (reportNo || '').indexOf('/') !== -1 ? (reportNo.split('/')[1] || '').toString().slice(-2) : '';
    rptYear = rptYear || currentYear;
    $('#sevpf_report_ref').val(thaiDocNo(docNo));
    $('#sevpf_report_ref_2').val(thaiDocNo(docNo));
    $('#sevpf_report_year').val(rptYear);
    $('#sevpf_report_year_2').val(rptYear);
    $('.sevpf-rpt-no-mirror').text(thaiDocNo(docNo));
    $('.sevpf-rpt-year-mirror').text(rptYear);

    $('#editInfoEV7').addClass('d-none');
    $('#editCountEV7').text('0');
    $('#editDateEV7').text('-');

    // Reset dynamic containers (keep only first rows)
    ev7ResetDynamicContainers();

    // Load data
    $.ajax({
        url: '/csims/api/incidentCheckList/getSceneEvidenceData.php',
        type: 'GET',
        data: { incident_id: incidentId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data && response.data.checklist_data) {
                ev7PrefillFromChecklistData(response.data.checklist_data, incidentId, docNo, reportNo);
                console.log(response.data.checklist_data.general_info.unit_name,"<----------response.data.checklist_data.general_info.unit_name");
                let unitName = response.data.checklist_data.general_info.unit_name;
                $('#sevpf_unit_name_2').val(unitName);

                var editInfo = response.data.edit_info || {};
                var countEdit = editInfo.count_edit || 0;
                if (countEdit > 0) {
                    $('#editInfoEV7').removeClass('d-none');
                    $('#editCountEV7').text(countEdit);
                    $('#editDateEV7').text(editInfo.edit_date || '-');
                    $('#editInfoEV7Pdf').removeClass('d-none');
                    $('#editCountEV7Pdf').text(countEdit);
                    $('#editDateEV7Pdf').text(editInfo.edit_date || '-');
                } else {
                    $('#editInfoEV7Pdf').addClass('d-none');
                    $('#editCountEV7Pdf').text('0');
                    $('#editDateEV7Pdf').text('-');
                }

                // Load existing photos into store
                var photos = response.data.checklist_data.photos || [];
                photos.forEach(function(p) {
                    if (p.filename) {
                        attachmentStoreEV7.push({
                            id: p.filename,
                            src: '/csims/uploads/checklist_photos/' + p.filename,
                            name: p.original_name || p.filename,
                            existing: true,
                            db_filename: p.filename,
                            caption: p.caption || ''
                        });
                    }
                });
            }

            // อย่า sync มาตรฐาน → PDF ตรงนี้: ฟอร์มมาตรฐานยังว่าง
            // จะทับรายการของกลางที่เพิ่ง prefill ลงฟอร์มรายงาน
            if (typeof syncSceneEvidenceFormData === 'function') {
                syncSceneEvidenceFormData('sceneEvidenceFormPdf', 'incidentCheckListFormSceneEvidence');
            }
            if (typeof sevpfSyncReportNo === 'function') sevpfSyncReportNo();
            if (typeof sevpfRenderPhotosFromStore === 'function') sevpfRenderPhotosFromStore();
            if (typeof sevpfUpdatePageNumbers === 'function') sevpfUpdatePageNumbers();

            var pdfModal = new bootstrap.Modal(document.getElementById('sceneEvidenceFormPdfModal'));
            pdfModal.show();
        },
        error: function() {
            // No data yet — open empty PDF form
            if (typeof sevpfSyncReportNo === 'function') sevpfSyncReportNo();
            var pdfModal = new bootstrap.Modal(document.getElementById('sceneEvidenceFormPdfModal'));
            pdfModal.show();
        }
    });
}

function ev7ResetDynamicContainers() {
    // PDF form: Evidence items
    var evContainer = document.getElementById('sevpf_evidence_items_container');
    if (evContainer) {
        var evRows = evContainer.querySelectorAll('.sevpf-evidence-item-row');
        for (var i = evRows.length - 1; i > 0; i--) evRows[i].remove();
        var firstEvInput = evContainer.querySelector('[name="sevpf_evidence_item[]"]');
        if (firstEvInput) firstEvInput.value = '';
        var firstLabHidden = evContainer.querySelector('[name="sevpf_lab_unit[]"]');
        if (firstLabHidden) {
            if (typeof window.setLabUnits === 'function') window.setLabUnits(firstLabHidden, []);
            else firstLabHidden.value = '';
        }
    }
    if (typeof sevpfRenumberEvidenceItems === 'function') sevpfRenumberEvidenceItems();

    // PDF form: Exhibit descriptions
    var exContainer = document.getElementById('sevpf_exhibit_desc_container');
    if (exContainer) {
        var exRows = exContainer.querySelectorAll('.sevpf-exhibit-row');
        for (var i = exRows.length - 1; i > 0; i--) exRows[i].remove();
        var firstExInput = exContainer.querySelector('[name="sevpf_exhibit_desc[]"]');
        if (firstExInput) firstExInput.value = '';
    }
    if (typeof sevpfRenumberExhibits === 'function') sevpfRenumberExhibits();

    // PDF form: Collect details
    var colContainer = document.getElementById('sevpf_collect_detail_container');
    if (colContainer) {
        var colRows = colContainer.querySelectorAll('.sevpf-collect-row');
        for (var i = colRows.length - 1; i > 0; i--) colRows[i].remove();
        var firstColInput = colContainer.querySelector('[name="sevpf_collect_detail[]"]');
        if (firstColInput) firstColInput.value = '';
    }
    if (typeof sevpfRenumberCollectDetails === 'function') sevpfRenumberCollectDetails();

    // Standard form dynamic containers
    var ev7EvContainer = document.getElementById('ev7_evidence_items_container');
    if (ev7EvContainer) {
        var ev7EvRows = ev7EvContainer.querySelectorAll('.ev7-evidence-item-row');
        for (var i = ev7EvRows.length - 1; i > 0; i--) ev7EvRows[i].remove();
    }
    var ev7ExContainer = document.getElementById('ev7_exhibit_desc_container');
    if (ev7ExContainer) {
        var ev7ExRows = ev7ExContainer.querySelectorAll('.ev7-exhibit-desc-row');
        for (var i = ev7ExRows.length - 1; i > 0; i--) ev7ExRows[i].remove();
    }
    var ev7ColContainer = document.getElementById('ev7_collect_detail_container');
    if (ev7ColContainer) {
        var ev7ColRows = ev7ColContainer.querySelectorAll('.ev7-collect-detail-row');
        for (var i = ev7ColRows.length - 1; i > 0; i--) ev7ColRows[i].remove();
    }
    var ev7InsContainer = document.getElementById('ev7_inspector_container');
    if (ev7InsContainer) {
        var ev7InsRows = ev7InsContainer.querySelectorAll('.ev7-inspector-row');
        for (var i = ev7InsRows.length - 1; i > 0; i--) ev7InsRows[i].remove();
    }
}

function ev7PrefillFromChecklistData(data, incidentId, docNo, reportNo) {
    if (!data) return;
    var gi = data.general_info || {};
    var purpose = data.purpose || {};
    var inspection = data.inspection || {};
    var collected = data.collected_evidence || {};
    var handling = data.evidence_handling || {};
    var handover = data.handover || {};
    var signer = data.signer || {};
    var pdfForm = data.pdf_form || {};
    var photoRecords = data.photo_records || {};

    // ---- Standard form fields (ev7_ prefix) ----
    $('#ev7_receive_date').val(gi.receive_date || '');
    $('#ev7_receive_time').val(gi.receive_time || '');
    $('#ev7_unit_type').val(gi.unit_type || '');
    $('#ev7_unit_name').val(gi.unit_name || '');
    $('#ev7_police_station').val(gi.police_station || '');
    $('#ev7_document_no').val(gi.document_no || '');
    $('#ev7_document_date').val(gi.document_date || '');
    $('#ev7_case_no').val(gi.case_no || '');
    $('#ev7_incident_location').val(gi.incident_location || '');
    $('#ev7_incident_date').val(gi.incident_date || '');
    $('#ev7_incident_time').val(gi.incident_time || '');
    $('#ev7_investigator_name').val(gi.investigator_name || '');
    $('#ev7_send_request').val(gi.send_request || '');
    $('#ev7_purpose_detail').val(purpose.detail || '');
    $('#ev7_inspect_location').val(inspection.location || '');
    $('#ev7_inspect_date').val(inspection.date || '');
    $('#ev7_inspect_time').val(inspection.time || '');
    $('#ev7_collect_sheet_count').val(collected.sheet_count || '');
    $('#ev7_collect_location').val(collected.location || '');
    $('#ev7_witness_name').val(handling.witness_name || '');
    $('#ev7_witness_form').val(handling.witness_form || '');
    $('#ev7_handover_item_ref').val(handling.handover_item_ref || '');
    $('#ev7_handover_to').val(handling.handover_to || '');
    $('#ev7_handover_purpose').val(handling.handover_purpose || '');
    $('#ev7_signer_id').val(signer.id || '');
    $('#ev7_signer_position').val(signer.position || '');
    $('#ev7_sign_date').val(signer.date || '');
    $('#ev7_photo_id_start').val(photoRecords.start || '');
    $('#ev7_photo_id_end').val(photoRecords.end || '');
    $('#ev7_photo_amount').val(photoRecords.amount || '');
    $('#ev7_receiver_id').val(handover.receiver_id || '');
    $('#ev7_receiver_position').val(handover.receiver_position || '');
    $('#ev7_sender_id').val(handover.deliverer_id || '');
    $('#ev7_sender_position').val(handover.deliverer_pos || '');

    // ---- PDF form hidden fields ----
    $('#sevpf_receiveNoti_id').val(incidentId);
    $('#sevpf_doc_no').val(docNo);
    $('#sevpf_report_no').val(gi.report_no || '');
    $('#report_no_ev7').val(gi.report_no || '');
    var ev7CurrentYear = '<?= substr((date('Y') + 543), -2) ?>';
    $('#sevpf_report_ref').val(thaiDocNo(pdfForm.report_ref || docNo || ''));
    $('#sevpf_report_year').val(pdfForm.report_year || ev7CurrentYear || '');

    // ---- PDF form fields (sevpf_ prefix) ----
    $('[name="sevpf_receive_date"]').val(gi.receive_date || '');
    $('[name="sevpf_receive_time"]').val(gi.receive_time || '');
    $('[name="sevpf_unit_name"]').val(gi.unit_name || '');
    $('[name="sevpf_police_station"]').val(gi.police_station || '');
    $('[name="sevpf_document_no"]').val(gi.document_no || '');
    $('[name="sevpf_document_date"]').val(gi.document_date || '');
    $('[name="sevpf_case_no"]').val(gi.case_no || '');
    $('[name="sevpf_incident_location"]').val(gi.incident_location || '');
    $('[name="sevpf_incident_date"]').val(gi.incident_date || '');
    $('[name="sevpf_incident_time"]').val(gi.incident_time || '');
    $('[name="sevpf_investigator_name"]').val(gi.investigator_name || '');
    $('[name="sevpf_send_request"]').val(gi.send_request || '');
    $('[name="sevpf_purpose_detail"]').val(purpose.detail || '');
    $('[name="sevpf_inspect_location"]').val(inspection.location || '');
    $('[name="sevpf_inspect_date"]').val(inspection.date || '');
    $('[name="sevpf_inspect_time"]').val(inspection.time || '');
    $('[name="sevpf_collect_sheet_count"]').val(collected.sheet_count || '');
    $('[name="sevpf_collect_location"]').val(collected.location || '');
    $('[name="sevpf_witness_name"]').val(handling.witness_name || '');
    $('[name="sevpf_witness_form"]').val(handling.witness_form || '');
    $('[name="sevpf_witness_detail"]').val(handling.witness_detail || '');
    $('[name="sevpf_handover_method_detail"]').val(handling.handover_method_detail || '');
    $('[name="sevpf_handover_item_ref"]').val(handling.handover_item_ref || '');
    $('[name="sevpf_handover_to"]').val(handling.handover_to || '');
    $('[name="sevpf_handover_purpose"]').val(handling.handover_purpose || '');
    $('[name="sevpf_handover_next_action"]').val(handling.next_action || '');
    $('[name="sevpf_receiver_id"]').val(handover.receiver_id || '');
    $('[name="sevpf_receiver_position"]').val(handover.receiver_position || '');
    $('[name="sevpf_sender_id"]').val(handover.deliverer_id || '');
    $('[name="sevpf_sender_position"]').val(handover.deliverer_pos || '');
    $('[name="sevpf_center_name"]').val(pdfForm.center_name || '');
    $('[name="sevpf_province_name"]').val(pdfForm.province_name || '');
    $('[name="sevpf_sign_day"]').val(pdfForm.sign_day || '');
    $('[name="sevpf_sign_month"]').val(pdfForm.sign_month || '');
    $('[name="sevpf_sign_year"]').val(pdfForm.sign_year || '');
    $('[name="sevpf_other_evidence_text"]').val(collected.other_evidence_text || '');
    $('[name="photo_id_start_ev7"]').val(photoRecords.start || '');
    $('[name="photo_id_end_ev7"]').val(photoRecords.end || '');
    $('[name="photo_amount_ev7"]').val(photoRecords.amount || '');

    // Checkboxes: notify methods
    var notifyMethods = gi.notify_method || [];
    if (Array.isArray(notifyMethods)) {
        $('[name="ev7_notify_method[]"]').each(function() { this.checked = notifyMethods.indexOf(this.value) !== -1; });
        // Map standard→PDF values
        var pdfNotifyMap = { 'ตามหนังสือ': 'ทางหนังสือ', 'ทางโทรศัพท์': 'ทางโทรศัพท์', 'ทางวิทยุสื่อสาร': 'ทางวิทยุสื่อสาร', 'อื่นๆ': 'อื่นๆ' };
        var pdfNotify = notifyMethods.map(function(v) { return pdfNotifyMap[v] || v; });
        $('[name="sevpf_notify_method[]"]').each(function() { this.checked = pdfNotify.indexOf(this.value) !== -1; });
    }
    $('[name="ev7_notify_method_other_text"]').val(gi.notify_method_other_text || '');
    $('[name="sevpf_notify_method_other_text"]').val(gi.notify_method_other_text || '');

    // Checkboxes: unit type
    var unitTypeChecks = pdfForm.unit_type_checks || [];
    if (Array.isArray(unitTypeChecks)) {
        $('[name="sevpf_unit_type_check[]"]').each(function() { this.checked = unitTypeChecks.indexOf(this.value) !== -1; });
    }

    // Checkboxes: purpose
    var purposeTypes = purpose.types || [];
    if (Array.isArray(purposeTypes)) {
        $('[name="ev7_purpose[]"]').each(function() { this.checked = purposeTypes.indexOf(this.value) !== -1; });
        $('[name="sevpf_purpose[]"]').each(function() { this.checked = purposeTypes.indexOf(this.value) !== -1; });
    }

    // Checkboxes: collect type
    var collectTypes = collected.types || [];
    if (Array.isArray(collectTypes)) {
        $('[name="ev7_collect_type[]"]').each(function() { this.checked = collectTypes.indexOf(this.value) !== -1; });
        $('[name="sevpf_collect_type[]"]').each(function() { this.checked = collectTypes.indexOf(this.value) !== -1; });
    }

    // Checkboxes: handover method
    var handoverMethodChecks = handling.handover_method_checks || [];
    if (Array.isArray(handoverMethodChecks)) {
        $('[name="sevpf_handover_method_check[]"]').each(function() { this.checked = handoverMethodChecks.indexOf(this.value) !== -1; });
    }
    if (handling.handover_method) {
        $('[name="ev7_handover_method"][value="' + handling.handover_method + '"]').prop('checked', true);
    }

    // Dynamic: Evidence items — ใช้ชุดที่มีข้อมูล (evidences ว่าง [] ยังเป็น truthy อย่าข้าม evidence_items)
    var evidences = [];
    if (data.evidences && data.evidences.length) evidences = data.evidences;
    else if (data.evidence_items && data.evidence_items.length) evidences = data.evidence_items;
    var labUnits = (collected.lab_units && collected.lab_units.length) ? collected.lab_units : [];
    var evidenceText = function(ev) {
        if (ev == null) return '';
        if (typeof ev === 'string') return ev;
        return ev.detail || ev.item || ev.description || '';
    };
    if (evidences.length > 0) {
        for (var i = 1; i < evidences.length; i++) {
            if (typeof sevpfAddEvidenceItem === 'function') sevpfAddEvidenceItem();
            if (typeof addEvidenceItemEV7 === 'function') addEvidenceItemEV7();
        }
        $('[name="sevpf_evidence_item[]"]').each(function(idx) {
            this.value = evidenceText(evidences[idx]);
        });
        $('[name="ev7_evidence_item[]"]').each(function(idx) {
            this.value = evidenceText(evidences[idx]);
        });
        $('[name="sevpf_lab_unit[]"]').each(function(idx) {
            var lu = (evidences[idx] && evidences[idx].lab_unit) ? evidences[idx].lab_unit : (labUnits[idx] || '');
            if (typeof window.setLabUnits === 'function') window.setLabUnits(this, lu);
            else this.value = Array.isArray(lu) ? lu.join(',') : (lu || '');
        });
        $('[name="ev7_lab_unit[]"]').each(function(idx) {
            var lu = (evidences[idx] && evidences[idx].lab_unit) ? evidences[idx].lab_unit : (labUnits[idx] || '');
            if (typeof window.setLabUnits === 'function') window.setLabUnits(this, lu);
            else this.value = Array.isArray(lu) ? lu.join(',') : (lu || '');
        });
    }

    // Dynamic: Exhibit descriptions
    var exhibits = data.exhibit_descriptions || [];
    if (exhibits.length > 0) {
        for (var i = 1; i < exhibits.length; i++) {
            if (typeof sevpfAddExhibitDesc === 'function') sevpfAddExhibitDesc();
        }
        $('[name="sevpf_exhibit_desc[]"]').each(function(idx) {
            if (exhibits[idx]) this.value = exhibits[idx].description || '';
        });
    }

    // Dynamic: Collect details
    var collectDetails = collected.details || [];
    if (collectDetails.length > 0) {
        for (var i = 1; i < collectDetails.length; i++) {
            if (typeof sevpfAddCollectDetail === 'function') sevpfAddCollectDetail();
        }
        $('[name="sevpf_collect_detail[]"]').each(function(idx) {
            if (collectDetails[idx] !== undefined) this.value = collectDetails[idx];
        });
    }

    // Dynamic: Inspectors (standard form only)
    var inspectors = data.inspectors || [];
    if (inspectors.length > 0) {
        $('#ev7_inspector_container .ev7-inspector-select').each(function(idx) {
            if (inspectors[idx]) {
                var inspId = inspectors[idx].id || inspectors[idx].user_id || inspectors[idx];
                this.value = inspId;
                $(this).trigger('change');
            }
        });
    }

    // Signer fields
    $('[name="sevpf_signer_name"]').val(signer.name || '');
    $('[name="sevpf_signer_fullname"]').val(signer.fullname || '');
    $('[name="sevpf_signer_position"]').val(signer.position || '');
}

async function prepareDataForSubmissionSceneEvidence() {
    var getCheckboxValues = function(name) {
        var values = [];
        $('input[name="' + name + '"]:checked').each(function() { values.push($(this).val()); });
        return values;
    };

    var payload = {
        receiveNoti_id_ev7: $('#receiveNoti_id_ev7').val() || $('#sevpf_receiveNoti_id').val(),
        doc_no_ev7: $('#doc_no_ev7').val() || $('#sevpf_doc_no').val(),
        report_no_ev7: $('#report_no_ev7').val() || $('#sevpf_report_no').val(),

        // Section 1
        ev7_receive_date: $('[name="ev7_receive_date"]').val() || $('[name="sevpf_receive_date"]').val(),
        ev7_receive_time: $('[name="ev7_receive_time"]').val() || $('[name="sevpf_receive_time"]').val(),
        ev7_unit_type: $('[name="ev7_unit_type"]').val() || '',
        ev7_unit_name: $('[name="ev7_unit_name"]').val() || $('[name="sevpf_unit_name"]').val(),
        'ev7_notify_method': getCheckboxValues('ev7_notify_method[]').length > 0 ? getCheckboxValues('ev7_notify_method[]') : getCheckboxValues('sevpf_notify_method[]'),
        ev7_notify_method_other_text: $('[name="ev7_notify_method_other_text"]').val() || '',
        ev7_police_station: $('#ev7_police_station').val() || $('[name="sevpf_police_station"]').val(),
        ev7_document_no: $('[name="ev7_document_no"]').val() || $('[name="sevpf_document_no"]').val(),
        ev7_document_date: $('[name="ev7_document_date"]').val() || $('[name="sevpf_document_date"]').val(),
        ev7_case_no: $('[name="ev7_case_no"]').val() || $('[name="sevpf_case_no"]').val(),
        ev7_incident_location: $('[name="ev7_incident_location"]').val() || $('[name="sevpf_incident_location"]').val(),
        ev7_incident_date: $('[name="ev7_incident_date"]').val() || $('[name="sevpf_incident_date"]').val(),
        ev7_incident_time: $('[name="ev7_incident_time"]').val() || $('[name="sevpf_incident_time"]').val(),
        ev7_investigator_name: $('[name="ev7_investigator_name"]').val() || $('[name="sevpf_investigator_name"]').val(),
        ev7_send_request: $('[name="ev7_send_request"]').val() || $('[name="sevpf_send_request"]').val(),
        'ev7_evidence_item': [],
        'ev7_purpose': getCheckboxValues('ev7_purpose[]').length > 0 ? getCheckboxValues('ev7_purpose[]') : getCheckboxValues('sevpf_purpose[]'),
        ev7_purpose_detail: $('[name="ev7_purpose_detail"]').val() || $('[name="sevpf_purpose_detail"]').val(),
        ev7_inspect_location: $('[name="ev7_inspect_location"]').val() || $('[name="sevpf_inspect_location"]').val(),
        ev7_inspect_date: $('[name="ev7_inspect_date"]').val() || $('[name="sevpf_inspect_date"]').val(),
        ev7_inspect_time: $('[name="ev7_inspect_time"]').val() || $('[name="sevpf_inspect_time"]').val(),
        'ev7_exhibit_desc': [],
        'ev7_collect_type': getCheckboxValues('ev7_collect_type[]').length > 0 ? getCheckboxValues('ev7_collect_type[]') : getCheckboxValues('sevpf_collect_type[]'),
        ev7_collect_sheet_count: $('[name="ev7_collect_sheet_count"]').val() || $('[name="sevpf_collect_sheet_count"]').val(),
        ev7_collect_location: $('[name="ev7_collect_location"]').val() || $('[name="sevpf_collect_location"]').val(),
        'ev7_collect_detail': [],
        'ev7_other_evidence': [],
        ev7_witness_name: $('[name="ev7_witness_name"]').val() || $('[name="sevpf_witness_name"]').val(),
        ev7_witness_form: $('[name="ev7_witness_form"]').val() || $('[name="sevpf_witness_form"]').val(),
        ev7_handover_method: $('input[name="ev7_handover_method"]:checked').val() || '',
        ev7_handover_item_ref: $('[name="ev7_handover_item_ref"]').val() || $('[name="sevpf_handover_item_ref"]').val(),
        ev7_handover_to: $('[name="ev7_handover_to"]').val() || $('[name="sevpf_handover_to"]').val(),
        ev7_handover_purpose: $('[name="ev7_handover_purpose"]').val() || $('[name="sevpf_handover_purpose"]').val(),

        // PDF-specific
        sevpf_report_ref: $('[name="sevpf_report_ref"]').val() || '',
        sevpf_report_year: $('[name="sevpf_report_year"]').val() || '',
        'sevpf_unit_type_check': getCheckboxValues('sevpf_unit_type_check[]'),
        sevpf_center_name: $('[name="sevpf_center_name"]').val() || '',
        sevpf_province_name: $('[name="sevpf_province_name"]').val() || '',
        sevpf_witness_detail: $('[name="sevpf_witness_detail"]').val() || '',
        'sevpf_handover_method_check': getCheckboxValues('sevpf_handover_method_check[]'),
        sevpf_other_evidence_text: $('[name="sevpf_other_evidence_text"]').val() || '',
        sevpf_signer_name: $('[name="sevpf_signer_name"]').val() || '',
        sevpf_signer_fullname: $('[name="sevpf_signer_fullname"]').val() || '',
        sevpf_signer_position: $('[name="sevpf_signer_position"]').val() || '',
        sevpf_sign_day: $('[name="sevpf_sign_day"]').val() || '',
        sevpf_sign_month: $('[name="sevpf_sign_month"]').val() || '',
        sevpf_sign_year: $('[name="sevpf_sign_year"]').val() || '',
        sevpf_handover_next_action: $('[name="sevpf_handover_next_action"]').val() || '',
        sevpf_handover_method_detail: $('[name="sevpf_handover_method_detail"]').val() || '',

        // Inspectors & Signer
        'ev7_inspector_id': [],
        ev7_signer_id: $('[name="ev7_signer_id"]').val() || '',
        ev7_signer_position: $('[name="ev7_signer_position"]').val() || $('[name="sevpf_signer_position"]').val(),
        ev7_sign_date: $('[name="ev7_sign_date"]').val() || '',
        ev7_photo_id_start: $('[name="ev7_photo_id_start"]').val() || $('[name="photo_id_start_ev7"]').val() || '',
        ev7_photo_id_end: $('[name="ev7_photo_id_end"]').val() || $('[name="photo_id_end_ev7"]').val() || '',
        ev7_photo_amount: $('[name="ev7_photo_amount"]').val() || $('[name="photo_amount_ev7"]').val() || '',

        // Handover (PDF)
        sevpf_receiver_id: $('[name="sevpf_receiver_id"]').val() || '',
        sevpf_receiver_position: $('[name="sevpf_receiver_position"]').val() || '',
        sevpf_sender_id: $('[name="sevpf_sender_id"]').val() || '',
        sevpf_sender_position: $('[name="sevpf_sender_position"]').val() || '',
    };

    var readLabUnitValue = function(el) {
        if (!el) return [];
        try {
            if (window.LabUnitMulti) window.LabUnitMulti.syncHidden(window.LabUnitMulti.selectFor(el) || el);
            if (typeof window.getLabUnits === 'function') {
                var arr = window.getLabUnits(el);
                if (arr && arr.length) return arr;
            }
        } catch (e) {}
        var raw = $(el).val();
        if (Array.isArray(raw)) return raw.filter(Boolean);
        if (typeof window.labUnitsToArray === 'function') return window.labUnitsToArray(raw);
        return String(raw || '').split(',').map(function(s) { return s.trim(); }).filter(Boolean);
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

    if (window.LabUnitMulti) {
        window.LabUnitMulti.syncAll(document.getElementById('sceneEvidenceFormPdf') || document);
    }

    // บันทึกจากฟอร์มรายงาน — ใช้แถวใน PDF เป็นหลัก (อย่าไปอ่านฟอร์มมาตรฐานที่ค้างอยู่)
    var pdfEv = collectEvidenceRows('#sevpf_evidence_items_container .sevpf-evidence-item-row', 'sevpf_evidence_item[]', 'sevpf_lab_unit[]');
    var stdEv = collectEvidenceRows('#ev7_evidence_items_container .ev7-evidence-item-row', 'ev7_evidence_item[]', 'ev7_lab_unit[]');
    var chosenEv = pdfEv.items.length ? pdfEv : stdEv;
    payload['ev7_evidence_item'] = chosenEv.items;
    payload['ev7_lab_unit'] = chosenEv.labs;
    payload['ev7_evidence_rows'] = chosenEv.rows;

    $('[name="sevpf_exhibit_desc[]"]').each(function() { var v = $(this).val(); if (v) payload['ev7_exhibit_desc'].push(v); });
    if (payload['ev7_exhibit_desc'].length === 0) {
        $('[name="ev7_exhibit_desc[]"]').each(function() { var v = $(this).val(); if (v) payload['ev7_exhibit_desc'].push(v); });
    }
    $('[name="sevpf_collect_detail[]"]').each(function() { var v = $(this).val(); if (v) payload['ev7_collect_detail'].push(v); });
    if (payload['ev7_collect_detail'].length === 0) {
        $('[name="ev7_collect_detail[]"]').each(function() { var v = $(this).val(); if (v) payload['ev7_collect_detail'].push(v); });
    }
    $('[name="ev7_other_evidence[]"]').each(function() { var v = $(this).val(); if (v) payload['ev7_other_evidence'].push(v); });

    // Inspectors (standard form only, no sevpf_inspector_container in new PDF form)
    $('#ev7_inspector_container .ev7-inspector-select').each(function() {
        var val = $(this).val();
        if (val) payload['ev7_inspector_id'].push(val);
    });

    // FormData for file uploads — ส่ง payload_json เป็นหลัก เพื่อให้ array ต่อแถวไม่เพี้ยน
    var submitData = new FormData();
    submitData.append('payload_json', JSON.stringify(payload));
    for (var key in payload) {
        if (key === 'ev7_evidence_rows') continue;
        if (Array.isArray(payload[key])) {
            payload[key].forEach(function(v) {
                if (Array.isArray(v)) {
                    submitData.append(key + '[]', v.filter(Boolean).join(','));
                } else if (v && typeof v === 'object') {
                    submitData.append(key + '[]', v.description || v.detail || '');
                } else {
                    submitData.append(key + '[]', v == null ? '' : v);
                }
            });
        } else {
            submitData.append(key, payload[key] || '');
        }
    }

    // Confirm & Submit
    Swal.fire({
        title: 'ยืนยันการบันทึกข้อมูล',
        text: 'กรุณาตรวจสอบความถูกต้องก่อนบันทึก',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#d33',
        confirmButtonText: 'ยืนยัน, บันทึกเลย!',
        cancelButtonText: 'ยกเลิก',
    }).then(async function(result) {
        if (result.isConfirmed) {
            var btnSavePdf = $('#btn_save_ev7_pdf');
            var btnTextPdf = btnSavePdf.html();
            btnSavePdf.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> กำลังบันทึก...');

            $.ajax({
                url: '/csims/api/incidentCheckList/saveSceneEvidence.php',
                method: 'POST',
                data: submitData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    btnSavePdf.prop('disabled', false).html(btnTextPdf);
                    if (response.success) {
                        var incidentId = payload.receiveNoti_id_ev7;
                        var $pdfCell = $('.report-row[data-id="' + incidentId + '"]').find('.pdf-cell');
                        $pdfCell.html('<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="' + incidentId + '" data-pdf-type="07" data-pdf-location="" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>');

                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ',
                            text: response.message || 'บันทึกข้อมูลเรียบร้อย',
                            confirmButtonColor: '#7c5cbf',
                            confirmButtonText: 'ตกลง'
                        }).then(function() {
                            ['addCheckListModalSceneEvidence', 'sceneEvidenceFormPdfModal'].forEach(function(id) {
                                var el = document.getElementById(id);
                                if (el) { var m = bootstrap.Modal.getInstance(el); if (m) m.hide(); }
                            });
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message || 'ไม่สามารถบันทึกข้อมูลได้' });
                    }
                },
                error: function() {
                    btnSavePdf.prop('disabled', false).html(btnTextPdf);
                    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
                }
            });
        }
    });
}

// ========================================
// Life Report Outdoor: Switch between Standard ↔ PDF Form
// (Global scope — called from inline onchange handlers)
// ========================================

function syncLifeOutdoorReportFormData(fromFormId, toFormId) {
    var fromForm = document.getElementById(fromFormId);
    var toForm = document.getElementById(toFormId);
    if (!fromForm || !toForm) return;

    var fromEls = fromForm.querySelectorAll('input, select, textarea');
    var dataMap = {};

    fromEls.forEach(function(el) {
        var name = el.name;
        if (!name || name === 'incident_id') return;
        if (el.type === 'file') return;

        if (el.type === 'checkbox') {
            if (!dataMap[name]) dataMap[name] = { type: 'checkbox', values: [] };
            if (el.checked) dataMap[name].values.push(el.value);
        } else if (el.type === 'radio') {
            if (el.checked) dataMap[name] = { type: 'radio', value: el.value };
        } else {
            if (!dataMap[name]) dataMap[name] = { type: 'value', values: [] };
            dataMap[name].values.push(el.value);
        }
    });

    Object.keys(dataMap).forEach(function(name) {
        var info = dataMap[name];
        var toEls = toForm.querySelectorAll('[name="' + name + '"]');
        if (toEls.length === 0) return;

        if (info.type === 'checkbox') {
            toEls.forEach(function(el) { el.checked = info.values.includes(el.value); });
        } else if (info.type === 'radio') {
            toEls.forEach(function(el) { el.checked = (el.value === info.value); });
        } else {
            toEls.forEach(function(el, idx) {
                if (idx < info.values.length) el.value = info.values[idx];
            });
        }
    });
}

function syncLifeOutdoorReportInspectorRows(direction) {
    if (direction === 'toPdf') {
        var stdRows = $('#rlo_inspector_container .rp-inspector-row');
        var pdfContainer = document.getElementById('rlopdf_inspector_container');
        while (pdfContainer.querySelectorAll('.rlopdf-inspector-row').length < stdRows.length) {
            rlopdfAddInspectorRow();
        }
        var currentPdf = pdfContainer.querySelectorAll('.rlopdf-inspector-row');
        for (var i = stdRows.length; i < currentPdf.length; i++) {
            currentPdf[i].remove();
        }
        rlopdfRenumberInspectors();
        stdRows.each(function(idx) {
            var pdfRow = pdfContainer.querySelectorAll('.rlopdf-inspector-row')[idx];
            if (!pdfRow) return;
            var nameVal = $(this).find('.rp-inspector-select').val() || '';
            var posVal = $(this).find('.rp-inspector-position').val() || '';
            var pdfSelect = $(pdfRow).find('.rp-inspector-select');
            if (pdfSelect.length) {
                if (nameVal && pdfSelect.find('option[value="' + nameVal + '"]').length === 0) {
                    pdfSelect.append('<option value="' + nameVal + '">' + nameVal + '</option>');
                }
                pdfSelect.val(nameVal);
                if (pdfSelect.hasClass('select2-hidden-accessible')) pdfSelect.trigger('change.select2');
            }
            $(pdfRow).find('.rp-inspector-position').val(posVal);
        });
    } else {
        var pdfContainer2 = document.getElementById('rlopdf_inspector_container');
        var pdfRows = pdfContainer2.querySelectorAll('.rlopdf-inspector-row');
        var $container = $('#rlo_inspector_container');
        while ($container.find('.rp-inspector-row').length < pdfRows.length) {
            var count = $container.find('.rp-inspector-row').length + 1;
            $container.append(rpBuildInspectorRow('5.' + count, '', '', 'rlo'));
        }
        var stdRowsNow = $container.find('.rp-inspector-row');
        for (var j = pdfRows.length; j < stdRowsNow.length; j++) {
            $(stdRowsNow[j]).remove();
        }
        pdfRows.forEach(function(pdfRow, idx) {
            var stdRow = $container.find('.rp-inspector-row').eq(idx);
            if (!stdRow.length) return;
            var pdfName = $(pdfRow).find('.rp-inspector-select').val() || '';
            var pdfPos = $(pdfRow).find('.rp-inspector-position').val() || '';
            var stdSelect = stdRow.find('.rp-inspector-select');
            if (pdfName && stdSelect.find('option[value="' + pdfName + '"]').length === 0) {
                stdSelect.append('<option value="' + pdfName + '">' + pdfName + '</option>');
            }
            stdSelect.val(pdfName);
            if (stdSelect.hasClass('select2-hidden-accessible')) stdSelect.trigger('change.select2');
            stdRow.find('.rp-inspector-position').val(pdfPos);
        });
    }
}

function switchLifeOutdoorReportToPdfForm() {
    syncLifeOutdoorReportInspectorRows('toPdf');
    syncLifeOutdoorReportFormData('formReportLifeOutdoor', 'formReportLifeOutdoorPdf');
    $('#rlopdf_incident_id').val($('#rlo_incident_id').val());
    restoreDraftPdfReportNoFromDocDisplay('#rlo_doc_no_display', ['#rlopdf_report_no_display', '#rlopdf_report_no_display_2']);

    var stdEl = document.getElementById('modalReportLifeOutdoor');
    var stdModal = bootstrap.Modal.getInstance(stdEl);
    if (stdModal) stdModal.hide();

    stdEl.addEventListener('hidden.bs.modal', function onHidden() {
        stdEl.removeEventListener('hidden.bs.modal', onHidden);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalReportLifeOutdoorPdf')).show();
    });
}

function switchLifeOutdoorReportToStandard() {
    syncLifeOutdoorReportInspectorRows('toStd');
    syncLifeOutdoorReportFormData('formReportLifeOutdoorPdf', 'formReportLifeOutdoor');
    $('#rlo_incident_id').val($('#rlopdf_incident_id').val());

    var pdfEl = document.getElementById('modalReportLifeOutdoorPdf');
    var pdfModal = bootstrap.Modal.getInstance(pdfEl);
    if (pdfModal) pdfModal.hide();

    pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
        pdfEl.removeEventListener('hidden.bs.modal', onHidden);
        var stdEl = document.getElementById('modalReportLifeOutdoor');
        stdEl.addEventListener('shown.bs.modal', function onShown() {
            stdEl.removeEventListener('shown.bs.modal', onShown);
            rpInitSelect2InModal($('#modalReportLifeOutdoor'));
        });
        bootstrap.Modal.getOrCreateInstance(stdEl).show();
    });
}

// Save from Life Outdoor PDF form
$(document).on('click', '#btn_save_report_life_outdoor_pdf', function() {
    var incidentId = $('#rlopdf_incident_id').val();
    if (!incidentId) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
        return;
    }

    var formData = {};
    var formEl = document.getElementById('formReportLifeOutdoorPdf');
    var elements = formEl.elements;

    for (var i = 0; i < elements.length; i++) {
        var el = elements[i];
        if (!el.name || el.name === 'incident_id') continue;

        if (el.type === 'radio') {
            if (el.checked) formData[el.name] = el.value;
        } else if (el.type === 'checkbox') {
            if (!formData[el.name]) formData[el.name] = [];
            if (el.checked) formData[el.name].push(el.value);
        } else {
            if (el.name.endsWith('[]')) {
                if (!formData[el.name]) formData[el.name] = [];
                formData[el.name].push(el.value);
            } else {
                formData[el.name] = el.value;
            }
        }
    }

    var payload = {
        incident_id: incidentId,
        report_type: 'outdoor',
        form_data: formData
    };

    var btn = this;
    $.ajax({
        url: '/csims/api/incidentCheckList/saveLifeReport.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        beforeSend: function() { $(btn).prop('disabled', true); },
        success: function(response) {
            if (response.success) {
                var $pdfCell = $('.report-row[data-id="' + incidentId + '"]').find('.pdf-cell');
                var loc = $('.report-row[data-id="' + incidentId + '"]').data('location-type') || 'outdoor';
                $pdfCell.html('<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="' + incidentId + '" data-pdf-type="02" data-pdf-location="' + loc + '" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>');

                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: response.message,
                    confirmButtonColor: '#0d6efd'
                }).then(function() {
                    var m = bootstrap.Modal.getInstance(document.getElementById('modalReportLifeOutdoorPdf'));
                    if (m) m.hide();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
            }
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
        },
        complete: function() { $(btn).prop('disabled', false); }
    });
});

// ========================================
// Life Report Indoor: Switch between Standard ↔ PDF Form
// (Global scope — called from inline onchange handlers)
// ========================================

function syncLifeIndoorReportFormData(fromFormId, toFormId) {
    var fromForm = document.getElementById(fromFormId);
    var toForm = document.getElementById(toFormId);
    if (!fromForm || !toForm) return;

    var fromEls = fromForm.querySelectorAll('input, select, textarea');
    var dataMap = {};

    fromEls.forEach(function(el) {
        var name = el.name;
        if (!name || name === 'incident_id') return;
        if (el.type === 'file') return;

        if (el.type === 'checkbox') {
            if (!dataMap[name]) dataMap[name] = { type: 'checkbox', values: [] };
            if (el.checked) dataMap[name].values.push(el.value);
        } else if (el.type === 'radio') {
            if (el.checked) dataMap[name] = { type: 'radio', value: el.value };
        } else {
            if (!dataMap[name]) dataMap[name] = { type: 'value', values: [] };
            dataMap[name].values.push(el.value);
        }
    });

    Object.keys(dataMap).forEach(function(name) {
        var info = dataMap[name];
        var toEls = toForm.querySelectorAll('[name="' + name + '"]');
        if (toEls.length === 0) return;

        if (info.type === 'checkbox') {
            toEls.forEach(function(el) { el.checked = info.values.includes(el.value); });
        } else if (info.type === 'radio') {
            toEls.forEach(function(el) { el.checked = (el.value === info.value); });
        } else {
            toEls.forEach(function(el, idx) {
                if (idx < info.values.length) el.value = info.values[idx];
            });
        }
    });
}

function syncLifeIndoorReportSpecialFields(direction) {
    // Sync building_type checkboxes ↔ text, and radio fields ↔ PDF checkboxes
    if (direction === 'toPdf') {
        // building_type: checkboxes → combined text
        var types = [];
        $('input[name="rli_building_type[]"]:checked').each(function() { types.push($(this).val()); });
        var otherText = $('#rli_building_type_other').val();
        if (otherText) types.push(otherText);
        $('#rlipdf_building_type_text').val(types.join(', '));

        // mezzanine radio → PDF checkboxes
        var mezVal = $('input[name="rli_mezzanine"]:checked').val() || '';
        $('#rlipdf_mezzanine_yes').prop('checked', mezVal === 'มี');
        $('#rlipdf_mezzanine_no').prop('checked', mezVal === 'ไม่มี');

        // rooftop radio → PDF checkboxes
        var rtVal = $('input[name="rli_rooftop"]:checked').val() || '';
        $('#rlipdf_rooftop_yes').prop('checked', rtVal === 'มี');
        $('#rlipdf_rooftop_no').prop('checked', rtVal === 'ไม่มี');

        // fence radio → PDF checkboxes
        var fnVal = $('input[name="rli_fence"]:checked').val() || '';
        $('#rlipdf_fence_yes').prop('checked', fnVal === 'มี');
        $('#rlipdf_fence_no').prop('checked', fnVal === 'ไม่มี');
    } else {
        // PDF text → standard checkboxes
        var text = ($('#rlipdf_building_type_text').val() || '').trim();
        var parts = text.split(',').map(function(s) { return s.trim(); });
        var known = ['บ้าน', 'ตึกแถว', 'อาคาร', 'อื่นๆ'];
        $('input[name="rli_building_type[]"]').prop('checked', false);
        var others = [];
        parts.forEach(function(p) {
            if (!p) return;
            if (known.indexOf(p) >= 0) {
                $('input[name="rli_building_type[]"][value="' + p + '"]').prop('checked', true);
            } else {
                others.push(p);
            }
        });
        $('#rli_building_type_other').val(others.join(', '));

        // PDF checkboxes → standard radios
        if ($('#rlipdf_mezzanine_yes').is(':checked')) {
            $('input[name="rli_mezzanine"][value="มี"]').prop('checked', true);
        } else if ($('#rlipdf_mezzanine_no').is(':checked')) {
            $('input[name="rli_mezzanine"][value="ไม่มี"]').prop('checked', true);
        }

        if ($('#rlipdf_rooftop_yes').is(':checked')) {
            $('input[name="rli_rooftop"][value="มี"]').prop('checked', true);
        } else if ($('#rlipdf_rooftop_no').is(':checked')) {
            $('input[name="rli_rooftop"][value="ไม่มี"]').prop('checked', true);
        }

        if ($('#rlipdf_fence_yes').is(':checked')) {
            $('input[name="rli_fence"][value="มี"]').prop('checked', true);
        } else if ($('#rlipdf_fence_no').is(':checked')) {
            $('input[name="rli_fence"][value="ไม่มี"]').prop('checked', true);
        }
    }
}

function syncLifeIndoorReportInspectorRows(direction) {
    if (direction === 'toPdf') {
        var stdRows = $('#rli_inspector_container .rp-inspector-row');
        var pdfContainer = document.getElementById('rlipdf_inspector_container');
        while (pdfContainer.querySelectorAll('.rlipdf-inspector-row').length < stdRows.length) {
            rlipdfAddInspectorRow();
        }
        var currentPdf = pdfContainer.querySelectorAll('.rlipdf-inspector-row');
        for (var i = stdRows.length; i < currentPdf.length; i++) {
            currentPdf[i].remove();
        }
        rlipdfRenumberInspectors();
        stdRows.each(function(idx) {
            var pdfRow = pdfContainer.querySelectorAll('.rlipdf-inspector-row')[idx];
            if (!pdfRow) return;
            var nameVal = $(this).find('.rp-inspector-select').val() || '';
            var posVal = $(this).find('.rp-inspector-position').val() || '';
            var pdfSelect = $(pdfRow).find('.rp-inspector-select');
            if (pdfSelect.length) {
                if (nameVal && pdfSelect.find('option[value="' + nameVal + '"]').length === 0) {
                    pdfSelect.append('<option value="' + nameVal + '">' + nameVal + '</option>');
                }
                pdfSelect.val(nameVal);
                if (pdfSelect.hasClass('select2-hidden-accessible')) pdfSelect.trigger('change.select2');
            }
            $(pdfRow).find('.rp-inspector-position').val(posVal);
        });
    } else {
        var pdfContainer2 = document.getElementById('rlipdf_inspector_container');
        var pdfRows = pdfContainer2.querySelectorAll('.rlipdf-inspector-row');
        var $container = $('#rli_inspector_container');
        while ($container.find('.rp-inspector-row').length < pdfRows.length) {
            var count = $container.find('.rp-inspector-row').length + 1;
            $container.append(rpBuildInspectorRow('5.' + count, '', '', 'rli'));
        }
        var stdRowsNow = $container.find('.rp-inspector-row');
        for (var j = pdfRows.length; j < stdRowsNow.length; j++) {
            $(stdRowsNow[j]).remove();
        }
        pdfRows.forEach(function(pdfRow, idx) {
            var stdRow = $container.find('.rp-inspector-row').eq(idx);
            if (!stdRow.length) return;
            var pdfName = $(pdfRow).find('.rp-inspector-select').val() || '';
            var pdfPos = $(pdfRow).find('.rp-inspector-position').val() || '';
            var stdSelect = stdRow.find('.rp-inspector-select');
            if (pdfName && stdSelect.find('option[value="' + pdfName + '"]').length === 0) {
                stdSelect.append('<option value="' + pdfName + '">' + pdfName + '</option>');
            }
            stdSelect.val(pdfName);
            if (stdSelect.hasClass('select2-hidden-accessible')) stdSelect.trigger('change.select2');
            stdRow.find('.rp-inspector-position').val(pdfPos);
        });
    }
}

function switchLifeIndoorReportToPdfForm() {
    syncLifeIndoorReportInspectorRows('toPdf');
    syncLifeIndoorReportFormData('formReportLifeIndoor', 'formReportLifeIndoorPdf');
    syncLifeIndoorReportSpecialFields('toPdf');
    $('#rlipdf_incident_id').val($('#rli_incident_id').val());
    restoreDraftPdfReportNoFromDocDisplay('#rli_doc_no_display', ['#rlipdf_report_no_display', '#rlipdf_report_no_display_2', '#rlipdf_report_no_display_3', '#rlipdf_report_no_display_4']);

    var stdEl = document.getElementById('modalReportLifeIndoor');
    var stdModal = bootstrap.Modal.getInstance(stdEl);
    if (stdModal) stdModal.hide();

    stdEl.addEventListener('hidden.bs.modal', function onHidden() {
        stdEl.removeEventListener('hidden.bs.modal', onHidden);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalReportLifeIndoorPdf')).show();
    });
}

function switchLifeIndoorReportToStandard() {
    syncLifeIndoorReportInspectorRows('toStd');
    syncLifeIndoorReportFormData('formReportLifeIndoorPdf', 'formReportLifeIndoor');
    syncLifeIndoorReportSpecialFields('toStd');
    $('#rli_incident_id').val($('#rlipdf_incident_id').val());

    var pdfEl = document.getElementById('modalReportLifeIndoorPdf');
    var pdfModal = bootstrap.Modal.getInstance(pdfEl);
    if (pdfModal) pdfModal.hide();

    pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
        pdfEl.removeEventListener('hidden.bs.modal', onHidden);
        var stdEl = document.getElementById('modalReportLifeIndoor');
        stdEl.addEventListener('shown.bs.modal', function onShown() {
            stdEl.removeEventListener('shown.bs.modal', onShown);
            rpInitSelect2InModal($('#modalReportLifeIndoor'));
        });
        bootstrap.Modal.getOrCreateInstance(stdEl).show();
    });
}

// Save from Life Indoor PDF form
$(document).on('click', '#btn_save_report_life_indoor_pdf', function() {
    var incidentId = $('#rlipdf_incident_id').val();
    if (!incidentId) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
        return;
    }

    var formData = {};
    var formEl = document.getElementById('formReportLifeIndoorPdf');
    var elements = formEl.elements;

    for (var i = 0; i < elements.length; i++) {
        var el = elements[i];
        if (!el.name || el.name === 'incident_id') continue;

        if (el.type === 'radio') {
            if (el.checked) formData[el.name] = el.value;
        } else if (el.type === 'checkbox') {
            if (!formData[el.name]) formData[el.name] = [];
            if (el.checked) formData[el.name].push(el.value);
        } else {
            if (el.name.endsWith('[]')) {
                if (!formData[el.name]) formData[el.name] = [];
                formData[el.name].push(el.value);
            } else {
                formData[el.name] = el.value;
            }
        }
    }

    var payload = {
        incident_id: incidentId,
        report_type: 'indoor',
        form_data: formData
    };

    var btn = this;
    $.ajax({
        url: '/csims/api/incidentCheckList/saveLifeReport.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        beforeSend: function() { $(btn).prop('disabled', true); },
        success: function(response) {
            if (response.success) {
                var $pdfCell = $('.report-row[data-id="' + incidentId + '"]').find('.pdf-cell');
                var loc = $('.report-row[data-id="' + incidentId + '"]').data('location-type') || 'indoor';
                $pdfCell.html('<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="' + incidentId + '" data-pdf-type="02" data-pdf-location="' + loc + '" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>');

                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: response.message,
                    confirmButtonColor: '#0d6efd'
                }).then(function() {
                    var m = bootstrap.Modal.getInstance(document.getElementById('modalReportLifeIndoorPdf'));
                    if (m) m.hide();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
            }
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
        },
        complete: function() { $(btn).prop('disabled', false); }
    });
});

// ========================================
// Property Report: Switch between Standard ↔ PDF Form
// (Global scope — called from inline onchange handlers)
// ========================================

function syncPropertyReportFormData(fromFormId, toFormId) {
    var fromForm = document.getElementById(fromFormId);
    var toForm = document.getElementById(toFormId);
    if (!fromForm || !toForm) return;

    var fromEls = fromForm.querySelectorAll('input, select, textarea');
    var dataMap = {};

    fromEls.forEach(function(el) {
        var name = el.name;
        if (!name || name === 'incident_id') return;
        if (el.type === 'file') return;

        if (el.type === 'checkbox') {
            if (!dataMap[name]) dataMap[name] = { type: 'checkbox', values: [] };
            if (el.checked) dataMap[name].values.push(el.value);
        } else if (el.type === 'radio') {
            if (el.checked) dataMap[name] = { type: 'radio', value: el.value };
        } else {
            if (!dataMap[name]) dataMap[name] = { type: 'value', values: [] };
            dataMap[name].values.push(el.value);
        }
    });

    Object.keys(dataMap).forEach(function(name) {
        var info = dataMap[name];
        var toEls = toForm.querySelectorAll('[name="' + name + '"]');
        if (toEls.length === 0) return;

        if (info.type === 'checkbox') {
            toEls.forEach(function(el) { el.checked = info.values.includes(el.value); });
        } else if (info.type === 'radio') {
            toEls.forEach(function(el) { el.checked = (el.value === info.value); });
        } else {
            toEls.forEach(function(el, idx) {
                if (idx < info.values.length) el.value = info.values[idx];
            });
        }
    });
}

function syncPropertyReportInspectorRows(direction) {
    if (direction === 'toPdf') {
        var stdRows = $('#rp_inspector_container .rp-inspector-row');
        var pdfContainer = document.getElementById('rppdf_inspector_container');
        while (pdfContainer.querySelectorAll('.rppdf-inspector-row').length < stdRows.length) {
            rppdfAddInspectorRow();
        }
        var currentPdf = pdfContainer.querySelectorAll('.rppdf-inspector-row');
        for (var i = stdRows.length; i < currentPdf.length; i++) {
            currentPdf[i].remove();
        }
        rppdfRenumberInspectors();
        stdRows.each(function(idx) {
            var pdfRow = pdfContainer.querySelectorAll('.rppdf-inspector-row')[idx];
            if (!pdfRow) return;
            var nameVal = $(this).find('.rp-inspector-select').val() || '';
            var posVal = $(this).find('.rp-inspector-position').val() || '';
            var pdfSelect = $(pdfRow).find('.rp-inspector-select');
            if (pdfSelect.length) {
                if (nameVal && pdfSelect.find('option[value="' + nameVal + '"]').length === 0) {
                    pdfSelect.append('<option value="' + nameVal + '">' + nameVal + '</option>');
                }
                pdfSelect.val(nameVal);
                if (pdfSelect.hasClass('select2-hidden-accessible')) pdfSelect.trigger('change.select2');
            }
            $(pdfRow).find('.rp-inspector-position').val(posVal);
        });
    } else {
        var pdfContainer2 = document.getElementById('rppdf_inspector_container');
        var pdfRows = pdfContainer2.querySelectorAll('.rppdf-inspector-row');
        var $container = $('#rp_inspector_container');
        while ($container.find('.rp-inspector-row').length < pdfRows.length) {
            var count = $container.find('.rp-inspector-row').length + 1;
            $container.append(rpBuildInspectorRow('5.' + count, '', '', 'rp'));
        }
        var stdRowsNow = $container.find('.rp-inspector-row');
        for (var j = pdfRows.length; j < stdRowsNow.length; j++) {
            $(stdRowsNow[j]).remove();
        }
        pdfRows.forEach(function(pdfRow, idx) {
            var stdRow = $container.find('.rp-inspector-row').eq(idx);
            if (!stdRow.length) return;
            var pdfName = $(pdfRow).find('.rp-inspector-select').val() || '';
            var pdfPos = $(pdfRow).find('.rp-inspector-position').val() || '';
            var stdSelect = stdRow.find('.rp-inspector-select');
            if (pdfName && stdSelect.find('option[value="' + pdfName + '"]').length === 0) {
                stdSelect.append('<option value="' + pdfName + '">' + pdfName + '</option>');
            }
            stdSelect.val(pdfName);
            if (stdSelect.hasClass('select2-hidden-accessible')) stdSelect.trigger('change.select2');
            stdRow.find('.rp-inspector-position').val(pdfPos);
        });
    }
}

function syncPropertyReportDynamicSections(direction) {
    if (direction === 'toPdf') {
        // Sync rooms: standard → PDF
        var stdRoomBlocks = $('#rp_room_container .rp-room-block');
        var pdfRoomContainer = document.getElementById('rppdf_room_container');
        // Ensure enough PDF room blocks
        while (pdfRoomContainer.querySelectorAll('.rppdf-room-block').length < stdRoomBlocks.length) {
            rppdfAddRoomBlock();
        }
        var pdfRoomBlocks = pdfRoomContainer.querySelectorAll('.rppdf-room-block');
        for (var r = stdRoomBlocks.length; r < pdfRoomBlocks.length; r++) {
            pdfRoomBlocks[r].remove();
        }
        rppdfRenumberRooms();
        stdRoomBlocks.each(function(idx) {
            var pdfBlock = pdfRoomContainer.querySelectorAll('.rppdf-room-block')[idx];
            if (!pdfBlock) return;
            $(pdfBlock).find('[name="rp_room_name[]"]').val($(this).find('[name="rp_room_name[]"]').val());
            $(pdfBlock).find('[name="rp_room_entry[]"]').val($(this).find('[name="rp_room_entry[]"]').val());
            $(pdfBlock).find('[name="rp_room_marks[]"]').val($(this).find('[name="rp_room_marks[]"]').val());
            $(pdfBlock).find('[name="rp_room_search_marks[]"]').val($(this).find('[name="rp_room_search_marks[]"]').val());
            $(pdfBlock).find('[name="rp_room_other_evidence[]"]').val($(this).find('[name="rp_room_other_evidence[]"]').val());
        });

        // Sync stolen: standard → PDF
        var stdStolenBlocks = $('#rp_stolen_container .rp-stolen-block');
        var pdfStolenContainer = document.getElementById('rppdf_stolen_container');
        while (pdfStolenContainer.querySelectorAll('.rppdf-stolen-block').length < stdStolenBlocks.length) {
            rppdfAddStolenBlock();
        }
        var pdfStolenBlocks = pdfStolenContainer.querySelectorAll('.rppdf-stolen-block');
        for (var s = stdStolenBlocks.length; s < pdfStolenBlocks.length; s++) {
            pdfStolenBlocks[s].remove();
        }
        rppdfRenumberStolen();
        stdStolenBlocks.each(function(idx) {
            var pdfBlock = pdfStolenContainer.querySelectorAll('.rppdf-stolen-block')[idx];
            if (!pdfBlock) return;
            $(pdfBlock).find('[name="rp_stolen_victim_prefix[]"]').val($(this).find('[name="rp_stolen_victim_prefix[]"]').val());
            $(pdfBlock).find('[name="rp_stolen_victim_name[]"]').val($(this).find('[name="rp_stolen_victim_name[]"]').val());
            $(pdfBlock).find('[name="rp_stolen_victim_role[]"]').val($(this).find('[name="rp_stolen_victim_role[]"]').val());
            // Combine stolen items into text
            var items = [];
            $(this).find('[name="rp_stolen_item[]"]').each(function() {
                var v = $(this).val();
                if (v) items.push(v);
            });
            $(pdfBlock).find('[name="rp_stolen_item_text[]"]').val(items.join('\n'));
        });

        // Sync evidence: standard → PDF
        var stdEvidenceBlocks = $('#rp_evidence_container .rp-evidence-block');
        var pdfEvidenceContainer = document.getElementById('rppdf_evidence_container');
        while (pdfEvidenceContainer.querySelectorAll('.rppdf-evidence-block').length < stdEvidenceBlocks.length) {
            rppdfAddEvidenceBlock();
        }
        var pdfEvidenceBlocks = pdfEvidenceContainer.querySelectorAll('.rppdf-evidence-block');
        for (var e = stdEvidenceBlocks.length; e < pdfEvidenceBlocks.length; e++) {
            pdfEvidenceBlocks[e].remove();
        }
        rppdfRenumberEvidence();
        stdEvidenceBlocks.each(function(idx) {
            var pdfBlock = pdfEvidenceContainer.querySelectorAll('.rppdf-evidence-block')[idx];
            if (!pdfBlock) return;
            $(pdfBlock).find('[name="rp_evidence_count[]"]').val($(this).find('[name="rp_evidence_count[]"]').val());
            $(pdfBlock).find('[name="rp_evidence_signer_prefix[]"]').val($(this).find('[name="rp_evidence_signer_prefix[]"]').val());
            $(pdfBlock).find('[name="rp_evidence_signer_name[]"]').val($(this).find('[name="rp_evidence_signer_name[]"]').val());
            $(pdfBlock).find('[name="rp_evidence_signer_role[]"]').val($(this).find('[name="rp_evidence_signer_role[]"]').val());
            // Combine evidence locs into text
            var locs = [];
            $(this).find('[name="rp_evidence_loc[]"]').each(function() {
                var v = $(this).val();
                if (v) locs.push(v);
            });
            $(pdfBlock).find('[name="rp_evidence_loc_text[]"]').val(locs.join('\n'));
        });

        // Sync building type checkboxes to text
        var buildingTypes = [];
        $('#formReportProperty [name="rp_building_type[]"]:checked').each(function() {
            buildingTypes.push($(this).val());
        });
        var otherText = $('#rp_building_type_other').val();
        if (otherText) buildingTypes.push(otherText);
        $('#rppdf_building_type_text').val(buildingTypes.join(', '));

        // Sync radio fields (mezzanine, rooftop, fence)
        var mez = $('[name="rp_mezzanine"]:checked').val();
        if (mez) $('[name="rp_mezzanine_pdf"][value="' + mez + '"]').prop('checked', true);
        var roof = $('[name="rp_rooftop"]:checked').val();
        if (roof) $('[name="rp_rooftop_pdf"][value="' + roof + '"]').prop('checked', true);
        var fence = $('[name="rp_fence"]:checked').val();
        if (fence) $('[name="rp_fence_pdf"][value="' + fence + '"]').prop('checked', true);

    } else {
        // toStd: PDF → Standard
        // Sync rooms: PDF → standard
        var pdfRoomBlocks2 = $('#rppdf_room_container .rppdf-room-block');
        var $stdRoomContainer = $('#rp_room_container');
        while ($stdRoomContainer.find('.rp-room-block').length < pdfRoomBlocks2.length) {
            $('#rp_add_room').trigger('click');
        }
        var stdRoomBlocksNow = $stdRoomContainer.find('.rp-room-block');
        for (var r2 = pdfRoomBlocks2.length; r2 < stdRoomBlocksNow.length; r2++) {
            $(stdRoomBlocksNow[r2]).remove();
        }
        pdfRoomBlocks2.each(function(idx) {
            var stdBlock = $stdRoomContainer.find('.rp-room-block').eq(idx);
            if (!stdBlock.length) return;
            stdBlock.find('[name="rp_room_name[]"]').val($(this).find('[name="rp_room_name[]"]').val());
            stdBlock.find('[name="rp_room_entry[]"]').val($(this).find('[name="rp_room_entry[]"]').val());
            stdBlock.find('[name="rp_room_marks[]"]').val($(this).find('[name="rp_room_marks[]"]').val());
            stdBlock.find('[name="rp_room_search_marks[]"]').val($(this).find('[name="rp_room_search_marks[]"]').val());
            stdBlock.find('[name="rp_room_other_evidence[]"]').val($(this).find('[name="rp_room_other_evidence[]"]').val());
        });

        // Sync stolen: PDF → standard
        var pdfStolenBlocks2 = $('#rppdf_stolen_container .rppdf-stolen-block');
        var $stdStolenContainer = $('#rp_stolen_container');
        while ($stdStolenContainer.find('.rp-stolen-block').length < pdfStolenBlocks2.length) {
            $('#rp_add_stolen_block').trigger('click');
        }
        var stdStolenBlocksNow = $stdStolenContainer.find('.rp-stolen-block');
        for (var s2 = pdfStolenBlocks2.length; s2 < stdStolenBlocksNow.length; s2++) {
            $(stdStolenBlocksNow[s2]).remove();
        }
        pdfStolenBlocks2.each(function(idx) {
            var stdBlock = $stdStolenContainer.find('.rp-stolen-block').eq(idx);
            if (!stdBlock.length) return;
            stdBlock.find('[name="rp_stolen_victim_prefix[]"]').val($(this).find('[name="rp_stolen_victim_prefix[]"]').val());
            stdBlock.find('[name="rp_stolen_victim_name[]"]').val($(this).find('[name="rp_stolen_victim_name[]"]').val());
            stdBlock.find('[name="rp_stolen_victim_role[]"]').val($(this).find('[name="rp_stolen_victim_role[]"]').val());
            // Split text back into individual items
            var textVal = $(this).find('[name="rp_stolen_item_text[]"]').val() || '';
            var lines = textVal.split('\n').filter(function(l) { return l.trim() !== ''; });
            var $itemsContainer = stdBlock.find('.rp-stolen-items');
            // Ensure enough item rows
            while ($itemsContainer.find('.rp-stolen-item-row').length < lines.length) {
                stdBlock.find('.rp-add-stolen-item').trigger('click');
            }
            var itemRows = $itemsContainer.find('[name="rp_stolen_item[]"]');
            lines.forEach(function(line, li) {
                if (li < itemRows.length) $(itemRows[li]).val(line);
            });
        });

        // Sync evidence: PDF → standard
        var pdfEvidenceBlocks2 = $('#rppdf_evidence_container .rppdf-evidence-block');
        var $stdEvidenceContainer = $('#rp_evidence_container');
        while ($stdEvidenceContainer.find('.rp-evidence-block').length < pdfEvidenceBlocks2.length) {
            $('#rp_add_evidence_block').trigger('click');
        }
        var stdEvidenceBlocksNow = $stdEvidenceContainer.find('.rp-evidence-block');
        for (var e2 = pdfEvidenceBlocks2.length; e2 < stdEvidenceBlocksNow.length; e2++) {
            $(stdEvidenceBlocksNow[e2]).remove();
        }
        pdfEvidenceBlocks2.each(function(idx) {
            var stdBlock = $stdEvidenceContainer.find('.rp-evidence-block').eq(idx);
            if (!stdBlock.length) return;
            stdBlock.find('[name="rp_evidence_count[]"]').val($(this).find('[name="rp_evidence_count[]"]').val());
            stdBlock.find('[name="rp_evidence_signer_prefix[]"]').val($(this).find('[name="rp_evidence_signer_prefix[]"]').val());
            stdBlock.find('[name="rp_evidence_signer_name[]"]').val($(this).find('[name="rp_evidence_signer_name[]"]').val());
            stdBlock.find('[name="rp_evidence_signer_role[]"]').val($(this).find('[name="rp_evidence_signer_role[]"]').val());
            // Split text back into individual locs
            var textVal = $(this).find('[name="rp_evidence_loc_text[]"]').val() || '';
            var lines = textVal.split('\n').filter(function(l) { return l.trim() !== ''; });
            var $locsContainer = stdBlock.find('.rp-evidence-locs');
            while ($locsContainer.find('.rp-evidence-loc-row').length < lines.length) {
                stdBlock.find('.rp-add-evidence-loc').trigger('click');
            }
            var locRows = $locsContainer.find('[name="rp_evidence_loc[]"]');
            lines.forEach(function(line, li) {
                if (li < locRows.length) $(locRows[li]).val(line);
            });
        });

        // Sync building type text back to checkboxes
        var buildingText = $('#rppdf_building_type_text').val() || '';
        var types = buildingText.split(',').map(function(t) { return t.trim(); });
        $('#formReportProperty [name="rp_building_type[]"]').each(function() {
            $(this).prop('checked', types.includes($(this).val()));
        });

        // Sync radio fields back (mezzanine, rooftop, fence)
        var mezPdf = $('[name="rp_mezzanine_pdf"]:checked').val();
        if (mezPdf) $('[name="rp_mezzanine"][value="' + mezPdf + '"]').prop('checked', true);
        var roofPdf = $('[name="rp_rooftop_pdf"]:checked').val();
        if (roofPdf) $('[name="rp_rooftop"][value="' + roofPdf + '"]').prop('checked', true);
        var fencePdf = $('[name="rp_fence_pdf"]:checked').val();
        if (fencePdf) $('[name="rp_fence"][value="' + fencePdf + '"]').prop('checked', true);
    }
}

function switchPropertyReportToPdfForm() {
    syncPropertyReportInspectorRows('toPdf');
    syncPropertyReportDynamicSections('toPdf');
    syncPropertyReportFormData('formReportProperty', 'formReportPropertyPdf');
    $('#rppdf_incident_id').val($('#rp_incident_id').val());
    restoreDraftPdfReportNoFromDocDisplay('#rp_doc_no_display', ['#rppdf_report_no_display']);
    var thDoc = thaiDocNo($('#rp_doc_no_display').text());
    if (thDoc) {
        $('#rppdf_report_no_p2, #rppdf_report_no_p3, #rppdf_report_no_p4').text(thDoc);
    }

    // Sync agency name to page headers
    var agencyName = $('#rppdf_agency_name').val() || '';
    $('#rppdf_agency_name_p2, #rppdf_agency_name_p3, #rppdf_agency_name_p4').text(agencyName);

    var stdEl = document.getElementById('modalReportProperty');
    var stdModal = bootstrap.Modal.getInstance(stdEl);
    if (stdModal) stdModal.hide();

    stdEl.addEventListener('hidden.bs.modal', function onHidden() {
        stdEl.removeEventListener('hidden.bs.modal', onHidden);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalReportPropertyPdf')).show();
    });
}

function switchPropertyReportToStandard() {
    syncPropertyReportInspectorRows('toStd');
    syncPropertyReportDynamicSections('toStd');
    syncPropertyReportFormData('formReportPropertyPdf', 'formReportProperty');
    $('#rp_incident_id').val($('#rppdf_incident_id').val());

    var pdfEl = document.getElementById('modalReportPropertyPdf');
    var pdfModal = bootstrap.Modal.getInstance(pdfEl);
    if (pdfModal) pdfModal.hide();

    pdfEl.addEventListener('hidden.bs.modal', function onHidden() {
        pdfEl.removeEventListener('hidden.bs.modal', onHidden);
        var stdEl = document.getElementById('modalReportProperty');
        stdEl.addEventListener('shown.bs.modal', function onShown() {
            stdEl.removeEventListener('shown.bs.modal', onShown);
            rpInitSelect2InModal($('#modalReportProperty'));
        });
        bootstrap.Modal.getOrCreateInstance(stdEl).show();
    });
}

// Save from Property PDF form
$(document).on('click', '#btn_save_report_property_pdf', function() {
    var incidentId = $('#rppdf_incident_id').val();
    if (!incidentId) {
        Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่พบ incident_id' });
        return;
    }

    var formData = {};
    var formEl = document.getElementById('formReportPropertyPdf');
    var elements = formEl.elements;

    for (var i = 0; i < elements.length; i++) {
        var el = elements[i];
        if (!el.name || el.name === 'incident_id') continue;

        if (el.type === 'radio') {
            if (el.checked) formData[el.name] = el.value;
        } else if (el.type === 'checkbox') {
            if (!formData[el.name]) formData[el.name] = [];
            if (el.checked) formData[el.name].push(el.value);
        } else {
            if (el.name.endsWith('[]')) {
                if (!formData[el.name]) formData[el.name] = [];
                formData[el.name].push(el.value);
            } else {
                formData[el.name] = el.value;
            }
        }
    }

    var payload = {
        incident_id: incidentId,
        report_type: 'property',
        form_data: formData
    };

    var btn = this;
    $.ajax({
        url: '/csims/api/incidentCheckList/savePropertyReport.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        dataType: 'json',
        beforeSend: function() { $(btn).prop('disabled', true); },
        success: function(response) {
            if (response.success) {
                var $pdfCell = $('.report-row[data-id="' + incidentId + '"]').find('.pdf-cell');
                $pdfCell.html('<button type="button" class="btn btn-sm btn-draft-pdf" style="background-color: #6f42c1; color: white; border: none; box-shadow: 0 4px 6px rgba(111, 66, 193, 0.4); font-size: 11px; white-space: nowrap;" data-pdf-id="' + incidentId + '" data-pdf-type="01" data-pdf-location="" title="ดาวน์โหลด PDF"><i class="fas fa-file-pdf me-1"></i>PDF</button>');

                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: response.message,
                    confirmButtonColor: '#0d6efd'
                }).then(function() {
                    var m = bootstrap.Modal.getInstance(document.getElementById('modalReportPropertyPdf'));
                    if (m) m.hide();
                });
            } else {
                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: response.message });
            }
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้' });
        },
        complete: function() { $(btn).prop('disabled', false); }
    });
});

</script>

<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>
