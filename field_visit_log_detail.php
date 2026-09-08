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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: field_visit_log.php");
    exit;
}

$id = intval($_GET['id']);

// case_type map
$caseMap = [
    '1'=>'ทรัพย์','2'=>'ชีวิต','3'=>'ระเบิด','4'=>'เพลิงไหม้','5'=>'จราจร',
    '6'=>'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)','7'=>'ตรวจเก็บวัตถุพยานที่เกิดเหตุ','8'=>'ตรวจเก็บวัตถุพยานบุคคล',
    '01'=>'ทรัพย์','02'=>'ชีวิต','03'=>'ระเบิด','04'=>'เพลิงไหม้','05'=>'จราจร',
    '06'=>'ตรวจเก็บวัตถุพยาน(ลายนิ้วมือแฝง)','07'=>'ตรวจเก็บวัตถุพยานที่เกิดเหตุ','08'=>'ตรวจเก็บวัตถุพยานบุคคล'
];

// ดึงข้อมูล
$sql = "SELECT fvl.*,
               mps.station_name,
               CASE fvl.province_id WHEN 95 THEN 'ยะลา' WHEN 94 THEN 'ปัตตานี' WHEN 96 THEN 'นราธิวาส' ELSE '' END AS province_name,
               CONCAT(IFNULL(ur.rank_name,''),' ',IFNULL(up.first_name,''),' ',IFNULL(up.last_name,'')) AS recorder_name
        FROM field_visit_logs fvl
        LEFT JOIN master_police_station mps ON fvl.station_id = mps.id
        LEFT JOIN user_profile up ON fvl.create_by = up.user_id
        LEFT JOIN user_rank ur ON up.rank_id = ur.rank_id
        WHERE fvl.id = ? AND fvl.status_delete = 0";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    header("Location: field_visit_log.php");
    exit;
}

$photos      = $row['photos'] ? json_decode($row['photos'], true) : [];
$attachments = $row['attachments'] ? json_decode($row['attachments'], true) : [];
$caseName    = $caseMap[strval($row['case_type_id'])] ?? '-';
$createBy    = $row['create_by'];
$reportNoDisplay = !empty($row['report_no_TH'])
    ? $row['report_no_TH']
    : smartThaiReportOrDoc($row['report_no'] ?? '');

// วันที่แบบไทย
function thaiDate($date) {
    if (empty($date)) return '-';
    $ts = strtotime($date);
    if (!$ts) return '-';
    $months = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . (date('Y', $ts) + 543);
}

$visitDateThai  = thaiDate($row['visit_date']);
$createDateThai = !empty($row['create_date']) ? thaiDate($row['create_date']) . ' ' . date('H:i', strtotime($row['create_date'])) . ' น.' : '-';

// ดึง สภ./สน. สำหรับ modal edit
$qryPS = "SELECT id, station_name, province_id FROM master_police_station ORDER BY id DESC";
$policeStations = $pdo->query($qryPS)->fetchAll(PDO::FETCH_ASSOC);

$loggedInName = trim(($_SESSION['rank_name'] ?? '') . ' ' . ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));

$title = "รายละเอียดบันทึกภาคสนาม - ระบบบริหารจัดการฐานข้อมูลวัตถุพยาน";

ob_start();
?>

<!-- Breadcrumb & Back -->
<div class="d-flex align-items-center mb-4">
    <a href="./field_visit_log.php" class="text-decoration-none text-secondary d-flex align-items-center fw-medium me-3 hover-text-primary">
        <i class="fa-solid fa-chevron-left me-2" style="margin-top: 2px;"></i> ย้อนกลับ
    </a>
    <div class="vr opacity-25 me-3" style="height: 25px;"></div>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="./field_visit_log.php" class="text-decoration-none text-muted"><i class="fa-solid fa-folder-open me-1"></i> บันทึกภาคสนาม</a></li>
            <li class="breadcrumb-item active text-primary" aria-current="page">รายละเอียด</li>
        </ol>
    </nav>
</div>

<!-- Page Header -->
<div class="page-header d-flex flex-wrap justify-content-between align-items-end pb-3 border-bottom mb-4">
    <div class="d-flex align-items-center">
        <div class="bg-primary rounded-pill me-3 shadow-sm align-self-stretch" style="width: 6px;"></div>
        <div class="d-flex flex-column justify-content-center">
            <h3 class="fw-bold mb-0 lh-1 d-flex align-items-baseline">
                <span class="text-secondary fs-5 me-2">เลขที่รายงาน:</span>
                <span class="text-primary"><?= htmlspecialchars($reportNoDisplay ?: '-') ?></span>
            </h3>
            <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center gap-1 gap-lg-2 mt-2">
                <div class="text-muted small d-flex align-items-center">
                    <i class="fa-regular fa-clock me-1"></i>
                    <span>วันที่บันทึก: <?= $createDateThai ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-3 mt-md-0 align-items-center">
        <button type="button" class="btn btn-outline-primary px-3 shadow-sm" title="แก้ไขข้อมูล" onclick="openEditModal()" <?= ($_SESSION['user_id'] == $createBy) ? '' : 'disabled' ?>>
            <i class="fa-solid fa-pen-to-square me-1"></i>
            <span class="d-none d-xl-inline">แก้ไขข้อมูล</span>
            <span class="d-xl-none">แก้ไข</span>
        </button>

        <button type="button" class="btn btn-outline-danger px-3 shadow-sm" title="ลบข้อมูล" onclick="confirmDelete()" <?= ($_SESSION['user_id'] == $createBy) ? '' : 'disabled' ?>>
            <i class="fa-regular fa-trash-can me-1"></i>
            <span class="d-none d-xl-inline">ลบข้อมูล</span>
            <span class="d-xl-none">ลบ</span>
        </button>
    </div>
</div>

<!-- Card: รายละเอียดบันทึกภาคสนาม -->
<div class="row">
  <div class="col-lg-12">
    <div class="card-incDetail">
      <div class="card-incDetail-header">
        <i class="fa-solid fa-circle-info me-2" style="margin-top: 3px;"></i> รายละเอียดบันทึกภาคสนาม
      </div>
      <div class="card-body">

        <!-- แถวบน: คดี / สถานี / วันที่ -->
        <div class="row g-4 mb-4">
          <div class="col-md-4">
            <div class="p-3 bg-light rounded border h-100 d-flex flex-column justify-content-center">
              <div class="info-label text-secondary mb-1">เรื่อง / คดีเหตุ</div>
              <div class="info-value text-danger fs-5 fw-bold text-break"><?= htmlspecialchars($caseName) ?></div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="d-flex flex-column justify-content-center">
              <div class="mb-3">
                <div class="info-label text-center lh-lg">สถานีตำรวจ (สภ./สน.)</div>
                <div class="info-value text-dark fw-bold fs-5 text-center text-break lh-sm"><?= htmlspecialchars($row['station_name'] ?: '-') ?></div>
              </div>
              <div>
                <div class="info-label text-center" style="margin-bottom: 6px;">จังหวัด</div>
                <div class="info-value text-center">
                  <span class="badge border rounded-pill px-3 py-2 d-inline-flex align-items-center bg-primary bg-opacity-10 text-primary border-primary">
                    <i class="fa-solid fa-map-marker-alt me-2"></i>
                    <?= htmlspecialchars($row['province_name'] ?: '-') ?>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="h-100 d-flex flex-column justify-content-center ps-md-2">
              <div class="info-label">วันที่ไปตรวจ</div>
              <div class="info-value text-dark fs-5 fw-bold"><?= $visitDateThai ?></div>
              <div class="text-muted small opacity-75">
                <i class="fa-solid fa-clock me-1"></i> เลขที่: <?= htmlspecialchars($reportNoDisplay ?: '-') ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Divider: ข้อมูลผู้บันทึก -->
        <div class="d-flex align-items-center my-4">
          <hr class="flex-grow-1 text-muted opacity-25">
          <span class="px-3 text-primary fw-bold bg-white text-uppercase" style="letter-spacing: 0.5px;">
            <i class="fa-solid fa-user-pen me-2"></i> ข้อมูลผู้บันทึก
          </span>
          <hr class="flex-grow-1 text-muted opacity-25">
        </div>

        <!-- การ์ดผู้บันทึก -->
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <div class="d-flex align-items-center bg-white border rounded p-3 shadow-sm h-100 position-relative overflow-hidden card-hover-effect hover-border-primary">
              <div class="position-absolute top-0 start-0 bottom-0 bg-primary" style="width: 6px;"></div>
              <div class="icon-circle bg-primary bg-opacity-10 text-primary me-3 ms-1 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; border-radius: 50%;">
                <i class="fa-solid fa-user-shield fa-lg ps-1"></i>
              </div>
              <div class="flex-grow-1" style="min-width: 0;">
                <div class="info-label text-uppercase lh-1 mb-1" style="letter-spacing: 0.5px;">ผู้บันทึก</div>
                <div class="info-value text-dark fw-bold mb-0 fs-6 text-break lh-sm"><?= htmlspecialchars(trim($row['recorder_name'])) ?></div>
                <div class="small text-muted d-flex align-items-center">
                  <i class="fa-solid fa-calendar me-2 opacity-75"></i>
                  <span style="letter-spacing: 0.25px;">บันทึกเมื่อ <?= $visitDateThai ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Divider: สถานที่และรายละเอียด -->
        <div class="d-flex align-items-center my-4">
          <hr class="flex-grow-1 text-muted opacity-25">
          <span class="px-3 text-primary fw-bold bg-white text-uppercase" style="letter-spacing: 0.5px;">
            <i class="fa-solid fa-map-location-dot me-2"></i> สถานที่และรายละเอียด
          </span>
          <hr class="flex-grow-1 text-muted opacity-25">
        </div>

        <div class="row g-3">
          <div class="col-12">
            <div class="d-flex align-items-start">
              <div class="flex-shrink-0 rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; background-color: rgba(253, 126, 20, 0.1); color: #fd7e14;">
                <i class="fa-solid fa-location-dot fa-lg"></i>
              </div>
              <div class="flex-grow-1">
                <div class="info-label text-secondary">สถานที่ตรวจ</div>
                <div class="fw-bold fs-5 text-dark text-break lh-sm"><?= htmlspecialchars($row['location'] ?: '-') ?></div>
              </div>
            </div>
          </div>

          <div class="col-12 mb-3">
            <div class="border rounded-3 overflow-hidden">
              <div class="bg-light px-3 py-2 border-bottom d-flex align-items-center justify-content-between">
                <span class="fw-bold text-secondary text-uppercase small">
                  <i class="fa-solid fa-align-left me-2"></i>รายละเอียด / บันทึก
                </span>
                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill fw-normal">Note</span>
              </div>
              <div class="bg-white p-3 px-md-5 px-3">
                <div class="text-dark text-break" style="font-size: 0.9rem; line-height: 1.7; white-space: pre-wrap;"><?= !empty($row['description']) ? htmlspecialchars(trim($row['description'])) : '<span class="text-muted fst-italic opacity-75">ไม่มีข้อมูลรายละเอียดเพิ่มเติม</span>' ?></div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Card: เอกสารแนบ -->
  <?php if (!empty($attachments)): ?>
  <div class="col-lg-12">
    <div class="card-incDetail">
      <div class="card-incDetail-header">
        <i class="fa-solid fa-paperclip me-2" style="margin-top: 3px;"></i> เอกสารแนบ
        <span class="badge bg-white bg-opacity-25 text-white rounded-pill ms-2" style="font-size: 0.75rem;"><?= count($attachments) ?> ไฟล์</span>
      </div>
      <div class="card-body p-0">
        <?php foreach ($attachments as $i => $att):
            $fname    = is_array($att) ? ($att['original'] ?? $att['filename'] ?? '-') : $att;
            $filename = is_array($att) ? ($att['filename'] ?? $att) : $att;
            $size     = is_array($att) ? ($att['size'] ?? 0) : 0;
            $sizeStr  = $size > 1048576 ? number_format($size / 1048576, 1) . ' MB' : ($size > 0 ? number_format($size / 1024, 1) . ' KB' : '-');
            $ext      = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
            $iconCls  = 'fa-file text-secondary';
            if (in_array($ext, ['pdf'])) $iconCls = 'fa-file-pdf text-danger';
            elseif (in_array($ext, ['doc','docx'])) $iconCls = 'fa-file-word text-primary';
            elseif (in_array($ext, ['xls','xlsx'])) $iconCls = 'fa-file-excel text-success';
            elseif (in_array($ext, ['jpg','jpeg','png','gif','bmp','webp'])) $iconCls = 'fa-file-image text-info';
        ?>
        <a href="./uploads_field_visit/files/<?= htmlspecialchars($filename) ?>" download="<?= htmlspecialchars($fname) ?>" class="d-flex align-items-center px-4 py-3 text-decoration-none border-bottom" style="transition: background .15s; gap: 12px;" onmouseover="this.style.background='#f0f7ff'" onmouseout="this.style.background=''">
          <i class="fas <?= $iconCls ?>" style="font-size: 1.25rem; width: 24px; text-align: center;"></i>
          <div class="flex-grow-1" style="min-width: 0;">
            <div class="text-dark fw-medium text-truncate" style="font-size: 0.9rem;"><?= htmlspecialchars($fname) ?></div>
          </div>
          <span class="text-muted flex-shrink-0" style="font-size: 0.78rem;"><?= $sizeStr ?></span>
          <i class="fas fa-download text-primary opacity-50 flex-shrink-0" style="font-size: 0.85rem;"></i>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Card: ภาพถ่ายประกอบ -->
  <?php if (!empty($photos)): ?>
  <div class="col-lg-12">
    <div class="card-incDetail">
      <div class="card-incDetail-header">
        <i class="fa-solid fa-camera me-2" style="margin-top: 3px;"></i> ภาพถ่ายประกอบการตรวจสถานที่
        <span class="badge bg-white bg-opacity-25 text-white rounded-pill ms-2" style="font-size: 0.75rem;"><?= count($photos) ?> ภาพ</span>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <?php foreach ($photos as $i => $photo):
              $filename = is_array($photo) ? ($photo['filename'] ?? '') : $photo;
              $caption  = is_array($photo) ? ($photo['caption'] ?? '') : '';
          ?>
          <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="bg-white border rounded-3 shadow-sm overflow-hidden h-100 card-hover-effect">
              <div class="position-relative">
                <a href="./uploads_field_visit/photos/<?= htmlspecialchars($filename) ?>" target="_blank">
                  <img src="./uploads_field_visit/photos/<?= htmlspecialchars($filename) ?>" class="w-100" alt="ภาพที่ <?= $i+1 ?>"
                       style="height: 160px; object-fit: cover; display: block;">
                </a>
                <span class="position-absolute top-0 start-0 badge bg-dark bg-opacity-75 m-2 rounded-pill px-2" style="font-size: 11px;">
                  <i class="fas fa-image me-1"></i> ภาพที่ <?= $i+1 ?>
                </span>
              </div>
              <div class="d-flex align-items-center justify-content-between px-3 py-2 border-top bg-light">
                <span class="text-muted small text-truncate" style="max-width: calc(100% - 30px);"><?= !empty($caption) ? htmlspecialchars($caption) : 'ภาพถ่ายประกอบ ลำดับที่ ' . ($i+1) ?></span>
                <a href="./uploads_field_visit/photos/<?= htmlspecialchars($filename) ?>" download class="text-primary flex-shrink-0 opacity-75" title="ดาวน์โหลดภาพ"><i class="fas fa-download" style="font-size: 12px;"></i></a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Modal สำหรับแก้ไข -->
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
    var currentId = <?= $id ?>;

    // --- แก้ไข ---
    window.openEditModal = function() {
        const form = document.getElementById('formFieldVisit');
        if (form) form.reset();
        window.fvlPhotoStore = [];
        window.fvlFileStore = [];
        window.fvlDeletedPhotos = [];
        window.fvlDeletedFiles = [];
        window.fvlExistingPhotos = [];
        window.fvlExistingFiles = [];

        $('#modalFieldVisitLabel').html('<i class="fas fa-edit me-2"></i>แก้ไขรายการ');

        $.getJSON('./api/Field_visit_log/getDataByID.php', { id: currentId }, function(res) {
            if (res.status !== 'success') return;
            var d = res.data;
            $('#fvl_id').val(d.id);
            fvlSetReportNoFields(d.report_no, d.report_no_display || d.report_no_TH);
            $('#fvl_visit_date').val(d.visit_date);
            $('#fvl_station').val(d.station_id);
            $('#fvl_province').val(String(d.province_id));
            var caseVal = String(d.case_type_id);
            if (caseVal.length === 1) caseVal = '0' + caseVal;
            $('#fvl_case_title').val(caseVal);
            $('#fvl_location').val(d.location);
            $('#fvl_description').val(d.description);
            $('#fvl_recorder_name').val(d.recorder_name);

            if (d.photos && d.photos.length) {
                window.fvlExistingPhotos = d.photos;
                d.photos.forEach(function(p) {
                    window.fvlPhotoStore.push({
                        id: 'existing_' + p.filename, src: './uploads_field_visit/photos/' + p.filename,
                        caption: p.caption || '', filename: p.filename, isExisting: true
                    });
                });
                fvlRenderPhotos();
            }
            if (d.attachments && d.attachments.length) {
                window.fvlExistingFiles = d.attachments;
                d.attachments.forEach(function(a) {
                    window.fvlFileStore.push({
                        id: 'existing_' + a.filename, name: a.original || a.filename,
                        size: a.size || 0, filename: a.filename, isExisting: true
                    });
                });
                fvlRenderFiles();
            }
        });

        var modalEl = document.getElementById('modalFieldVisit');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) modal = new bootstrap.Modal(modalEl);
        modal.show();
    };

    // --- ลบ ---
    window.confirmDelete = function() {
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
                    data: JSON.stringify({ id: currentId }),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({ icon: 'success', title: 'สำเร็จ', text: 'ลบข้อมูลเรียบร้อย', timer: 1500, showConfirmButton: false }).then(function() {
                                window.location.href = 'field_visit_log.php';
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: res.message });
                        }
                    }
                });
            }
        });
    };

    // --- Modal: Station => Province ---
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

    // --- Photo source ---
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
            if (result.isConfirmed) document.getElementById('fvl_photo_gallery').click();
            else if (result.dismiss === Swal.DismissReason.cancel) document.getElementById('fvl_photo_camera').click();
        });
    };

    // --- Photo preview ---
    $('#fvl_photo_gallery, #fvl_photo_camera').on('change', function() {
        if (!this.files || !this.files.length) return;
        if (!window.fvlPhotoStore) window.fvlPhotoStore = [];
        Array.from(this.files).forEach(function(file) {
            var id = 'fvl_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
            window.fvlPhotoStore.push({ id: id, file: file, src: URL.createObjectURL(file), caption: '' });
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
        window.fvlFileStore.forEach(function(item) {
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
        if (!form.checkValidity()) { form.reportValidity(); return; }

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

        var captions = [];
        (window.fvlPhotoStore || []).forEach(function(p) {
            if (p.file) fd.append('fvl_photos[]', p.file);
            captions.push(p.caption || '');
        });
        captions.forEach(function(c) { fd.append('fvl_photo_captions[]', c); });

        (window.fvlFileStore || []).forEach(function(f) {
            if (f.file) fd.append('fvl_files[]', f.file);
        });

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
                    Swal.fire({ icon: 'success', title: 'สำเร็จ', text: res.message, timer: 1500, showConfirmButton: false }).then(function() {
                        window.location.reload();
                    });
                    bootstrap.Modal.getInstance(document.getElementById('modalFieldVisit')).hide();
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
