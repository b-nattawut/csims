<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

require './db_config.php';
require __DIR__ . '/helpers/report_no.php';

// เพิ่มคอลumn report_no_TH ถ้ายังไม่มี
try {
    $pdo->query("SELECT report_no_TH FROM field_visit_logs LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE field_visit_logs ADD COLUMN report_no_TH VARCHAR(50) NULL AFTER report_no");
    } catch (PDOException $e2) { /* ignore */ }
}

$title = "บันทึกภาคสนาม - ระบบบริหารจัดการฐานข้อมูลวัตถุพยาน";

// ดึง สภ./สน.
$qryPS = "SELECT id, station_name, province_id FROM master_police_station ORDER BY id DESC";
$stmtPS = $pdo->query($qryPS);
$policeStations = $stmtPS->fetchAll(PDO::FETCH_ASSOC);

// ดึงรายชื่อเจ้าหน้าที่
$qryUsers = "SELECT t1.user_id, CONCAT(IFNULL(t2.rank_name,''),' ',t1.first_name,' ',t1.last_name) AS fullname
             FROM user_profile t1
             LEFT JOIN user_rank t2 ON t1.rank_id = t2.rank_id
             INNER JOIN users t4 ON t1.user_id = t4.user_id AND t4.is_active = 1
             ORDER BY t2.rank_name, t1.first_name ASC";
$stmtUsers = $pdo->query($qryUsers);
$userList = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// ชื่อผู้ล็อกอิน
$loggedInName = trim(($_SESSION['rank_name'] ?? '') . ' ' . ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));

ob_start();
?>

<!-- Card: Filter + ปุ่มเพิ่ม -->
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

                        <div class="col-md-2 col-sm-2 mt-4">
                            <button class="mt-1 btn btn-success btn-sm px-3 fw-bold shadow-sm text-nowrap px-4 w-100" type="button" id="btn_add_field_visit">
                                <i class="fas fa-plus fa-xl me-2"></i><span class="d-none d-md-inline">เพิ่ม<span class="d-none d-lg-inline">รายการ</span></span>
                            </button>
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">วันที่เริ่ม</label>
                            <input type="date" class="form-control form-control-sm" id="filter_date_from" name="filter_date_from">
                        </div>

                        <div class="col-md-2 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">ถึงวันที่</label>
                            <input type="date" class="form-control form-control-sm" id="filter_date_to" name="filter_date_to">
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
                                <option value="" selected disabled>กรุณาเลือก</option>
                                <?php foreach ($policeStations as $ps): ?>
                                    <option value="<?= (int)$ps['id'] ?>" data-province="<?= (int)($ps['province_id'] ?? '') ?>"><?= htmlspecialchars($ps['station_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">คดี / เหตุ</label>
                            <select class="form-select form-select-sm" id="filter_case" name="filter_case">
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

                        <div class="col-md-3 col-sm-6">
                            <label class="form-label mb-0 text-muted" style="font-size: 11px;">ผู้บันทึก</label>
                            <select class="form-select form-select-sm" id="filter_recorder" name="filter_recorder">
                                <option value="" selected disabled>กรุณาเลือก</option>
                                <?php foreach ($userList as $u): ?>
                                    <option value="<?= htmlspecialchars(trim($u['fullname'])) ?>"><?= htmlspecialchars(trim($u['fullname'])) ?></option>
                                <?php endforeach; ?>
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

<!-- Card: Table -->
<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-end align-items-center mb-3">
            <div class="text-muted small">
                จำนวนข้อมูลทั้งหมด : <span id="count_display">0</span> รายการ
            </div>
        </div>

        <div class="table-responsive">
            <div class="table-wrapper-focus">
                <table class="table table-bordered table-striped table-hover table-custom align-middle mb-0">
                    <thead style="font-size: 14px;">
                        <tr class="text-nowrap text-center">
                            <th style="width: 5%">ลำดับ</th>
                            <th style="width: 12%">เลขที่รายงาน</th>
                            <th style="width: 14%">สภ./สน.</th>
                            <th style="width: 10%">จังหวัด</th>
                            <th style="width: 17%">เรื่อง / คดีเหตุ</th>
                            <th style="width: 17%">สถานที่</th>
                            <th style="width: 15%">ผู้บันทึก</th>
                            <th style="width: 10%">วันที่</th>
                        </tr>
                    </thead>
                    <tbody id="table_body" style="font-size: 14px;">
                        <tr><td colspan="8" class="text-center text-muted py-4">ไม่พบข้อมูล</td></tr>
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

<!-- Modal -->
<?php include 'modals/modal_field_visit.php'; ?>

<?php
$content = ob_get_clean();
ob_start();
?>

<script src="js/report_no_th.js"></script>
<script>
function fvlThaiReportNo(s) {
    s = String(s || '').trim();
    if (!s) return '';
    if (/^\d{1,3}-\d{1,3}-\d{2}-/.test(s)) {
        return (window.toThaiDocNo ? window.toThaiDocNo(s) : s);
    }
    return (window.toThaiReportNo ? window.toThaiReportNo(s) : s);
}
function fvlSetReportNoFields(raw, th) {
    $('#fvl_report_no').val(raw || '');
    $('#fvl_report_no_display').val(th || fvlThaiReportNo(raw || ''));
}

$(document).ready(function() {

    // --- โหลดข้อมูลครั้งแรก ---
    loadFieldVisitData();

    // --- เพิ่มรายการ ---
    $('#btn_add_field_visit').on('click', function() {
        openFieldVisitModal(null);
    });

    // --- Row click = ไปหน้า Detail ---
    $(document).on('click', '.fvl-row', function(e) {
        if ($(e.target).closest('button').length) return;
        var id = $(this).data('id');
        window.open('field_visit_log_detail.php?id=' + id, '_self');
    });

    // --- Delete button ---
    $(document).on('click', '.btn-delete-fvl', function(e) {
        e.stopPropagation();
        const id = $(this).data('id');
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: 'ข้อมูลที่ลบจะไม่สามารถกู้คืนได้',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ลบ',
            cancelButtonText: 'ยกเลิก'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: './api/Field_visit_log/delete.php',
                    type: 'POST',
                    data: JSON.stringify({ id: id }),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({ icon: 'success', title: 'สำเร็จ', text: 'ลบข้อมูลเรียบร้อย', timer: 1500, showConfirmButton: false });
                            loadFieldVisitData();
                        } else {
                            Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: res.message });
                        }
                    }
                });
            }
        });
    });


    // --- Province => filter สภ./สน. ---
    $('#filter_province').on('change', function() {
        var provId = $(this).val();
        $('#filter_station option').each(function() {
            var prov = $(this).data('province');
            if (!prov) { $(this).show(); return; }
            $(this).toggle(String(prov) === String(provId));
        });
        $('#filter_station').val('');
    });

    // --- Search filter ---
    $('#searchFilterForm').on('submit', function(e) {
        e.preventDefault();
        loadFieldVisitData();
    });
    $('#btn_clear_filter').on('click', function() {
        document.getElementById('searchFilterForm').reset();
        loadFieldVisitData();
    });

    // --- Load Data ---
    function loadFieldVisitData() {
        var params = {
            date_from:  $('#filter_date_from').val(),
            date_to:    $('#filter_date_to').val(),
            province:   $('#filter_province').val() || '',
            station:    $('#filter_station').val() || '',
            case_type:  $('#filter_case').val() || '',
            recorder:   $('#filter_recorder').val() || ''
        };

        $.getJSON('./api/Field_visit_log/getData.php', params, function(res) {
            if (res.status !== 'success') return;

            var data = res.data || [];
            $('#count_display').text(data.length);
            var tbody = $('#table_body');
            tbody.empty();

            if (data.length === 0) {
                tbody.html('<tr><td colspan="8" class="text-center text-muted py-4">ไม่พบข้อมูล</td></tr>');
                return;
            }

            // case_type mapping (รองรับทั้ง '01' และ '1')
            var caseMap = { '1': 'ทรัพย์', '2': 'ชีวิต', '3': 'ระเบิด', '4': 'เพลิงไหม้', '5': 'จราจร', '6': 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)', '7': 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ', '8': 'ตรวจเก็บวัตถุพยานบุคคล',
                '01': 'ทรัพย์', '02': 'ชีวิต', '03': 'ระเบิด', '04': 'เพลิงไหม้', '05': 'จราจร', '06': 'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)', '07': 'ตรวจเก็บวัตถุพยานที่เกิดเหตุ', '08': 'ตรวจเก็บวัตถุพยานบุคคล' };

            data.forEach(function(row, idx) {
                var caseName = caseMap[row.case_type_id] || row.case_type_id || '-';
                var visitDate = row.visit_date || '-';
                var tr = $('<tr class="fvl-row" style="cursor:pointer;" data-id="' + row.id + '">');
                tr.html(
                    '<td class="text-center">' + (idx + 1) + '</td>' +
                    '<td class="text-center">' + (row.report_no_display || fvlThaiReportNo(row.report_no) || '-') + '</td>' +
                    '<td>' + (row.station_name || '-') + '</td>' +
                    '<td class="text-center">' + (row.province_name || '-') + '</td>' +
                    '<td>' + caseName + '</td>' +
                    '<td>' + (row.location || '-') + '</td>' +
                    '<td>' + (row.recorder_name || '-') + '</td>' +
                    '<td class="text-center">' + visitDate + '</td>'
                );
                tbody.append(tr);
            });
        });
    }

    // --- Open Modal ---
    function openFieldVisitModal(id) {
        const form = document.getElementById('formFieldVisit');
        if (form) form.reset();
        $('#fvl_id').val('');
        fvlSetReportNoFields('', '');
        $('#fvl_photo_preview').html('<div class="text-center text-muted py-4"><i class="fas fa-image fa-2x mb-2 d-block opacity-50"></i>ยังไม่มีรูปภาพ</div>');
        $('#fvl_file_preview').html('<div class="text-center text-muted py-3"><i class="fas fa-paperclip fa-2x mb-2 d-block opacity-50"></i>ยังไม่มีไฟล์แนบ</div>');
        window.fvlPhotoStore = [];
        window.fvlFileStore = [];
        window.fvlDeletedPhotos = [];
        window.fvlDeletedFiles = [];
        window.fvlExistingPhotos = [];
        window.fvlExistingFiles = [];

        // set ผู้บันทึกจาก session
        $('#fvl_recorder_name').val('<?= addslashes($loggedInName) ?>');

        if (id) {
            $('#modalFieldVisitLabel').html('<i class="fas fa-edit me-2"></i>แก้ไขรายการ');
            // โหลดข้อมูลมา prefill
            $.getJSON('./api/Field_visit_log/getDataByID.php', { id: id }, function(res) {
                if (res.status !== 'success') return;
                var d = res.data;
                $('#fvl_id').val(d.id);
                fvlSetReportNoFields(d.report_no, d.report_no_display || d.report_no_TH);
                $('#fvl_visit_date').val(d.visit_date);
                $('#fvl_station').val(d.station_id);
                $('#fvl_province').val(String(d.province_id));
                $('#fvl_case_title').val(d.case_type_id);
                $('#fvl_location').val(d.location);
                $('#fvl_description').val(d.description);
                $('#fvl_recorder_name').val(d.recorder_name);

                // รูปภาพเดิม
                if (d.photos && d.photos.length) {
                    window.fvlExistingPhotos = d.photos;
                    d.photos.forEach(function(p) {
                        window.fvlPhotoStore.push({
                            id: 'existing_' + p.filename,
                            src: './uploads_field_visit/photos/' + p.filename,
                            caption: p.caption || '',
                            filename: p.filename,
                            isExisting: true
                        });
                    });
                    fvlRenderPhotos();
                }
                // ไฟล์แนบเดิม
                if (d.attachments && d.attachments.length) {
                    window.fvlExistingFiles = d.attachments;
                    d.attachments.forEach(function(a) {
                        window.fvlFileStore.push({
                            id: 'existing_' + a.filename,
                            name: a.original || a.filename,
                            size: a.size || 0,
                            filename: a.filename,
                            isExisting: true
                        });
                    });
                    fvlRenderFiles();
                }
            });
        } else {
            $('#modalFieldVisitLabel').html('<i class="fas fa-plus me-2"></i>เพิ่มรายการ');
        }

        const modalEl = document.getElementById('modalFieldVisit');
        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    // --- Modal: Station => Province auto-sync ---
    $('#fvl_station').on('change', function() {
        var provId = $(this).find(':selected').data('province');
        if (provId) $('#fvl_province').val(String(provId));
    });
    $('#fvl_province').on('change', function() {
        var provId = $(this).val();
        $('#fvl_station option').each(function() {
            var prov = $(this).data('province');
            if (!prov) { $(this).show(); return; }
            $(this).toggle(String(prov) === String(provId));
        });
        $('#fvl_station').val('');
    });

    // --- Photo choose source ---
    window.fvlChoosePhotoSource = function() {
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
                document.getElementById('fvl_photo_gallery').click();
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                document.getElementById('fvl_photo_camera').click();
            }
        });
    };

    // --- Photo preview ---
    $('#fvl_photo_gallery, #fvl_photo_camera').on('change', function() {
        if (!this.files || !this.files.length) return;
        if (!window.fvlPhotoStore) window.fvlPhotoStore = [];

        Array.from(this.files).forEach(function(file) {
            var id = 'fvl_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
            var url = URL.createObjectURL(file);
            window.fvlPhotoStore.push({ id: id, file: file, src: url, caption: '' });
        });
        this.value = '';
        fvlRenderPhotos();
    });

    // --- File attach ---
    $('#fvl_file_input').on('change', function() {
        if (!this.files || !this.files.length) return;
        if (!window.fvlFileStore) window.fvlFileStore = [];

        Array.from(this.files).forEach(function(file) {
            var id = 'fvlf_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
            window.fvlFileStore.push({ id: id, file: file, name: file.name, size: file.size });
        });
        this.value = '';
        fvlRenderFiles();
    });

    // --- Render photos ---
    window.fvlRenderPhotos = function() {
        var container = document.getElementById('fvl_photo_preview');
        if (!container) return;
        container.innerHTML = '';

        if (!window.fvlPhotoStore || window.fvlPhotoStore.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-image fa-2x mb-2 d-block opacity-50"></i>ยังไม่มีรูปภาพ</div>';
            return;
        }

        window.fvlPhotoStore.forEach(function(item, idx) {
            var card = document.createElement('div');
            card.className = 'fvl-photo-card';
            card.innerHTML =
                '<div class="fvl-photo-img-wrap">' +
                    '<img src="' + item.src + '" alt="photo">' +
                    '<button type="button" class="fvl-photo-remove" onclick="fvlRemovePhoto(\'' + item.id + '\')">&times;</button>' +
                    '<span class="fvl-photo-number">' + (idx + 1) + '</span>' +
                '</div>' +
                '<input type="text" class="form-control form-control-sm mt-1" placeholder="คำบรรยายภาพที่ ' + (idx + 1) + '" value="' + (item.caption || '') + '" onchange="fvlUpdateCaption(\'' + item.id + '\', this.value)">';
            container.appendChild(card);
        });
    };

    // --- Render files ---
    window.fvlRenderFiles = function() {
        var container = document.getElementById('fvl_file_preview');
        if (!container) return;
        container.innerHTML = '';

        if (!window.fvlFileStore || window.fvlFileStore.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-paperclip fa-2x mb-2 d-block opacity-50"></i>ยังไม่มีไฟล์แนบ</div>';
            return;
        }

        window.fvlFileStore.forEach(function(item, idx) {
            var sizeKB = (item.size / 1024).toFixed(1);
            var row = document.createElement('div');
            row.className = 'd-flex align-items-center justify-content-between py-2 px-3 border-bottom';
            row.innerHTML =
                '<div class="d-flex align-items-center gap-2">' +
                    '<i class="fas fa-file text-primary"></i>' +
                    '<span style="font-size:13px;">' + item.name + '</span>' +
                    '<span class="text-muted" style="font-size:11px;">(' + sizeKB + ' KB)</span>' +
                '</div>' +
                '<button type="button" class="btn btn-sm btn-outline-danger" onclick="fvlRemoveFile(\'' + item.id + '\')" style="padding:1px 8px;font-size:12px;"><i class="fas fa-times"></i></button>';
            container.appendChild(row);
        });
    };

    window.fvlRemovePhoto = function(id) {
        if (!window.fvlPhotoStore) return;
        var item = window.fvlPhotoStore.find(function(x) { return x.id === id; });
        if (item && item.isExisting && item.filename) {
            if (!window.fvlDeletedPhotos) window.fvlDeletedPhotos = [];
            window.fvlDeletedPhotos.push(item.filename);
        }
        window.fvlPhotoStore = window.fvlPhotoStore.filter(function(x) { return x.id !== id; });
        fvlRenderPhotos();
    };

    window.fvlRemoveFile = function(id) {
        if (!window.fvlFileStore) return;
        var item = window.fvlFileStore.find(function(x) { return x.id === id; });
        if (item && item.isExisting && item.filename) {
            if (!window.fvlDeletedFiles) window.fvlDeletedFiles = [];
            window.fvlDeletedFiles.push(item.filename);
        }
        window.fvlFileStore = window.fvlFileStore.filter(function(x) { return x.id !== id; });
        fvlRenderFiles();
    };

    window.fvlUpdateCaption = function(id, val) {
        if (!window.fvlPhotoStore) return;
        window.fvlPhotoStore.forEach(function(x) { if (x.id === id) x.caption = val; });
    };

    // --- Save ---
    $('#btn_save_field_visit').on('click', function() {
        var form = document.getElementById('formFieldVisit');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        var fd = new FormData();
        fd.append('fvl_id', $('#fvl_id').val());
        var reportRaw = $('#fvl_report_no').val() || $('#fvl_report_no_display').val();
        var reportDisplay = $('#fvl_report_no_display').val() || fvlThaiReportNo(reportRaw);
        if (!$('#fvl_report_no').val() && reportRaw) {
            $('#fvl_report_no').val(reportRaw);
        }
        fd.append('fvl_report_no', $('#fvl_report_no').val());
        fd.append('fvl_report_no_display', reportDisplay);
        fd.append('fvl_visit_date', $('#fvl_visit_date').val());
        fd.append('fvl_station', $('#fvl_station').val() || '');
        fd.append('fvl_province', $('#fvl_province').val() || '');
        fd.append('fvl_case_title', $('#fvl_case_title').val() || '');
        fd.append('fvl_location', $('#fvl_location').val());
        fd.append('fvl_description', $('#fvl_description').val());

        // รูปภาพใหม่
        var captions = [];
        (window.fvlPhotoStore || []).forEach(function(p) {
            if (p.file) fd.append('fvl_photos[]', p.file);
            captions.push(p.caption || '');
        });
        captions.forEach(function(c) { fd.append('fvl_photo_captions[]', c); });

        // ไฟล์แนบใหม่
        (window.fvlFileStore || []).forEach(function(f) {
            if (f.file) fd.append('fvl_files[]', f.file);
        });

        // ข้อมูลเดิมที่ยังอยู่
        var existPhotos = (window.fvlPhotoStore || []).filter(function(p) { return p.isExisting; }).map(function(p) { return { filename: p.filename, caption: p.caption }; });
        var existFiles = (window.fvlFileStore || []).filter(function(f) { return f.isExisting; }).map(function(f) { return { filename: f.filename, original: f.name, size: f.size }; });
        fd.append('existing_photos', JSON.stringify(existPhotos));
        fd.append('existing_attachments', JSON.stringify(existFiles));
        fd.append('deleted_photos', JSON.stringify(window.fvlDeletedPhotos || []));
        fd.append('deleted_attachments', JSON.stringify(window.fvlDeletedFiles || []));

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> กำลังบันทึก...');

        $.ajax({
            url: './api/Field_visit_log/save.php',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'สำเร็จ', text: res.message, timer: 1500, showConfirmButton: false });
                    bootstrap.Modal.getInstance(document.getElementById('modalFieldVisit')).hide();
                    loadFieldVisitData();
                } else {
                    Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: res.message });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้' });
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> บันทึกข้อมูล');
            }
        });
    });

});
</script>

<?php
$extra_scripts = ob_get_clean();
include 'layout.php';
?>
