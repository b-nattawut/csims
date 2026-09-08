<?php
require_once __DIR__ . '/includes/session_config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require __DIR__ . '/helpers/report_no.php';

$qryData = "SELECT COUNT(t1.id) as countData
FROM rn_ReceiveNoti t1
LEFT JOIN users t2 ON t1.create_by = t2.user_id
WHERE t1.statusDelete = 0 AND t2.is_active = 1 AND t1.statusChecklist = 1";
$stmt = $pdo->prepare($qryData);
$stmt->execute();
$countData = (int)$stmt->fetchColumn();

$qryPoliceStation = "SELECT station_name, province_id FROM master_police_station ORDER BY id DESC";
$policeStations = $pdo->query($qryPoliceStation)->fetchAll(PDO::FETCH_ASSOC);

$title = "การตรวจร่างรายงาน - ระบบบริหารจัดการฐานข้อมูลวัตถุพยาน";

ob_start();
?>

<div class="card mb-3 shadow-sm" style="font-size:13px;">
    <div class="card-body p-2">
        <div class="d-flex flex-wrap justify-content-end align-items-center mb-2">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterSection" aria-expanded="true">
                <i class="fas fa-filter me-1"></i> ตัวกรอง
            </button>
        </div>
        <div class="collapse show" id="filterSection">
            <div class="bg-light p-3 rounded-3 border mb-2 shadow-sm">
                <form id="searchFilterForm">
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
                                <option value="" selected>ทั้งหมด</option>
                                <option value="95">ยะลา</option>
                                <option value="94">ปัตตานี</option>
                                <option value="96">นราธิวาส</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">สภ./สน.</label>
                            <select class="form-select form-select-sm" id="filter_station" name="filter_station">
                                <option value="" selected>ทั้งหมด</option>
                                <?php foreach ($policeStations as $ps): ?>
                                    <option value="<?= htmlspecialchars($ps['station_name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($ps['station_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">เหตุที่รับแจ้ง</label>
                            <select class="form-select form-select-sm" id="filter_incident_type" name="filter_incident_type">
                                <option value="" selected>ทั้งหมด</option>
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
                            <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                                <i class="fas fa-search me-1"></i> ค้นหา
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="fas fa-user-check me-2 text-primary"></i>การตรวจร่างรายงาน</h5>
            <div class="text-muted small">
                จำนวนข้อมูลทั้งหมด : <span id="count_display"><?= $countData ?></span> รายการ
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover table-custom align-middle mb-0">
                <thead style="font-size: 13px;">
                    <tr class="text-nowrap text-center">
                        <th style="width:5%">ลำดับ</th>
                        <th style="width:14%">เลขที่เอกสาร</th>
                        <th style="width:14%">เลขที่รายงาน</th>
                        <th style="width:16%">สภ./สน.</th>
                        <th style="width:10%">จังหวัด</th>
                        <th style="width:16%">เหตุที่รับแจ้ง</th>
                        <th style="width:8%">ไฟล์รายงาน</th>
                        <th style="width:12%">สถานะการเซ็น</th>
                        <th style="width:8%">ร่างรายงาน</th>
                    </tr>
                </thead>
                <tbody id="table_body" style="font-size: 13px;">
                    <tr><td colspan="9" class="text-center text-muted">กำลังโหลด...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="row col-md-12 d-flex justify-content-center mt-3">
            <nav>
                <ul id="pagination-list" class="pagination justify-content-center"></ul>
            </nav>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
ob_start();
?>
<script src="js/report_no_th.js"></script>
<script>
$(document).ready(function () {
    function esc(v) {
        return $('<div>').text(v == null ? '' : v).html();
    }

    function statusCell(row) {
        var cls = row.status_class || 'secondary';
        var text = row.status_text || '-';
        return '<span class="badge bg-' + cls + '">' + esc(text) + '</span>';
    }

    function fileCell(row) {
        if (parseInt(row.has_file, 10) === 1) {
            return '<a class="btn btn-sm btn-outline-secondary" href="/csims/api/reportReview/downloadFile.php?incident_id=' + row.id + '" target="_blank" title="เปิดไฟล์"><i class="fas fa-file-alt"></i></a>';
        }
        return '<span class="text-muted">-</span>';
    }

    function renderTable(data, offset) {
        var html = '';
        var index = (parseInt(offset, 10) || 0) + 1;
        if (data && data.length) {
            data.forEach(function (row) {
                html += '<tr class="rr-row" style="cursor:pointer;" data-id="' + row.id + '">' +
                    '<td class="text-center">' + (index++) + '</td>' +
                    '<td class="text-center">' + esc(row.receiveNoti_No_TH || row.receiveNoti_No || '') + '</td>' +
                    '<td class="text-center">' + esc(row.receiveNotiReportNo_TH || row.receiveNotiReportNo || '') + '</td>' +
                    '<td>' + esc(row.complaints_From || '') + '</td>' +
                    '<td class="text-center">' + esc(row.province || '') + '</td>' +
                    '<td>' + esc(row.complaintstype || '') + '</td>' +
                    '<td class="text-center">' + fileCell(row) + '</td>' +
                    '<td class="text-center">' + statusCell(row) + '</td>' +
                    '<td class="text-center">' +
                        '<a class="btn btn-sm btn-draft-doc" href="/csims/api/reportReview/downloadDraftReviewDocx.php?incident_id=' + row.id + '" title="ดาวน์โหลดบันทึกการตรวจร่าง (Word)" ' +
                        'style="background:#2563eb; box-shadow:0 2px 6px rgba(37,99,235,.3); color:#fff; border:none; border-radius:6px; font-size:11px; white-space:nowrap; padding:4px 12px; font-weight:500; letter-spacing:.3px; text-decoration:none; display:inline-block;">' +
                        '<i class="fas fa-file-word me-1"></i>DOCX</a></td>' +
                    '</tr>';
            });
        } else {
            html = '<tr><td colspan="9" class="text-center">ไม่พบข้อมูล</td></tr>';
        }
        $('#table_body').html(html);
    }

    function setupPagination(totalPages, currentPage) {
        var total = parseInt(totalPages, 10) || 0;
        var current = parseInt(currentPage, 10) || 1;
        $('#pagination-list').twbsPagination('destroy');
        if (total <= 0) return;
        setTimeout(function () {
            $('#pagination-list').twbsPagination({
                totalPages: total,
                startPage: current,
                visiblePages: 5,
                first: '<i class="fas fa-angle-double-left"></i>',
                prev: '<i class="fas fa-angle-left"></i>',
                next: '<i class="fas fa-angle-right"></i>',
                last: '<i class="fas fa-angle-double-right"></i>',
                initiateStartPageClick: false,
                onPageClick: function (event, page) {
                    if (page !== current) searchPage(page);
                }
            });
        }, 80);
    }

    function searchPage(page) {
        var formData = $('#searchFilterForm').serializeArray();
        formData.push({ name: 'page', value: page });
        $.ajax({
            url: '/csims/api/reportReview/searchData.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    renderTable(res.data, res.offset);
                    $('#count_display').text(res.count);
                    setupPagination(res.totalPages, res.currentPage);
                }
            }
        });
    }

    $('#searchFilterForm').on('submit', function (e) {
        e.preventDefault();
        searchPage(1);
    });
    $('#btn_clear_filter').on('click', function () {
        $('#searchFilterForm')[0].reset();
        searchPage(1);
    });

    $(document).on('click', '.rr-row', function (e) {
        if ($(e.target).closest('a,button').length) return;
        var id = $(this).data('id');
        if (id) window.location.href = 'incidentReportReviewDetail.php?id=' + id;
    });

    searchPage(1);
});
</script>
<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
